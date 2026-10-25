#!/bin/bash
#
# Mesure du parc pour le compte 404 des fournisseurs d'immobilisations (comptabilité à l'engagement).
#
# À l'engagement, une facture électronique reçue crédite le 404 de ce que ses lignes de classe 2
# portent, TVA comprise, et le 401 du reste : une facture mixte est partagée entre les deux comptes.
# Une facture importée avant ce changement est restée entière au 401 : ce script dit combien il y en
# a, et lesquelles, pour décider d'une reprise en connaissance de cause. Sur la copie de dev du
# 21/09/2026 il n'en trouvait aucune, sur 169 factures reçues.
#
# Lecture seule, aucune écriture, aucun DDL. Aucune migration ne lui est liée.
#
#   bash internal/data/check/checkAssetSupplierAi.sh
#
# En dev :
#   MYSQL="docker exec -i mysql-otf mysql -uroot" BASE=dev_ouvretaferme bash internal/data/check/checkAssetSupplierAi.sh
#
# Une facture est « à reprendre » quand une de ses lignes porte un compte d'immobilisation, qu'elle est
# déjà importée, datée d'un exercice tenu à l'engagement, et que sa pièce n'a aucune ligne au 404. La
# porte de la bêta (FinancialYear::isAccrualEnabled()) ne se lit pas en SQL : le script retient le type
# de l'exercice, ce qui peut compter en trop une structure hors bêta, jamais en oublier une.

set -u

MYSQL=${MYSQL:-"mysql -uroot"}
BASE=${BASE:-ouvretaferme}

# Les bases de structure portent le même préfixe d'environnement que la base commune
if [ "$BASE" = "ouvretaferme" ]; then
	PREFIXE="structure_"
else
	PREFIXE="${BASE%ouvretaferme}structure_"
fi

MOTIF=$(echo "$PREFIXE" | sed 's/_/\\\\_/g')

# Une base sans exercice n'a pas de comptabilité, donc rien à reprendre : elle n'est pas lue.
# Toute la liste d'un coup, jamais une requête dans la boucle qui la lit : le client mysql appelé
# dans un `while read` vide le flux d'entrée et la boucle s'arrête au premier tour.
bases=$($MYSQL -N -e "
	SELECT TABLE_SCHEMA
	FROM information_schema.TABLES
	WHERE TABLE_NAME IN ('invoicingLine', 'financialYear', 'journalOperation') AND TABLE_SCHEMA LIKE '${MOTIF}%'
	GROUP BY TABLE_SCHEMA
	HAVING COUNT(*) = 3
	ORDER BY TABLE_SCHEMA
" < /dev/null)

nombre=$(echo "$bases" | grep -c .)

echo "Bases de structure qui portent les factures électroniques, les exercices et le journal : ${nombre}"

examinees=0
recues=0
avec_compte=0
immobilisations=0
mixtes=0
a_reprendre=0
details=""

for base in $bases; do

	ligne=$($MYSQL -N -e "
		SELECT
			COUNT(*),
			COALESCE(SUM(nCompte > 0), 0),
			COALESCE(SUM(nImmobilisation > 0 AND nImmobilisation = nLigne), 0),
			COALESCE(SUM(nImmobilisation > 0 AND nImmobilisation < nLigne), 0),
			COALESCE(SUM(nImmobilisation > 0 AND importee AND engagement AND sans404), 0)
		FROM (
			SELECT
				i.id,
				COUNT(l.id) AS nLigne,
				COALESCE(SUM(l.accountNumber IS NOT NULL AND l.accountNumber != ''), 0) AS nCompte,
				COALESCE(SUM(l.accountNumber LIKE '2%'), 0) AS nImmobilisation,
				i.accountingHash IS NOT NULL AS importee,
				EXISTS (
					SELECT 1 FROM ${base}.financialYear f
					WHERE f.accountingType = 'accrual' AND i.date BETWEEN f.startDate AND f.endDate
				) AS engagement,
				NOT EXISTS (
					SELECT 1 FROM ${base}.journalOperation o
					WHERE o.hash = i.accountingHash AND o.accountLabel LIKE '404%'
				) AS sans404
			FROM ${base}.invoicing i
			LEFT JOIN ${base}.invoicingLine l ON l.invoicing = i.id
			WHERE i.direction = 'in'
			GROUP BY i.id
		) factures
	" < /dev/null)

	if [ -z "$ligne" ]; then
		continue
	fi

	examinees=$((examinees + 1))

	set -- $ligne

	recues=$((recues + $1))
	avec_compte=$((avec_compte + $2))
	immobilisations=$((immobilisations + $3))
	mixtes=$((mixtes + $4))
	a_reprendre=$((a_reprendre + $5))

	if [ "$(( $3 + $4 ))" -gt 0 ]; then

		detail=$($MYSQL -N -e "
			SELECT
				'${base}', i.id, i.number, i.date,
				IF(SUM(COALESCE(l.accountNumber LIKE '2%', 0)) = COUNT(l.id), 'immobilisations', 'mixte'),
				IF(i.accountingHash IS NULL, 'non importée', 'importée')
			FROM ${base}.invoicing i
			JOIN ${base}.invoicingLine l ON l.invoicing = i.id
			WHERE i.direction = 'in'
			GROUP BY i.id
			HAVING SUM(COALESCE(l.accountNumber LIKE '2%', 0)) > 0
		" < /dev/null)

		details="${details}${detail}"$'\n'

	fi

done

echo "Bases examinées : ${examinees}"
echo "Factures reçues examinées : ${recues}, dont ${avec_compte} avec un compte sur une ligne"
echo "Tout entières en immobilisations : ${immobilisations}"
echo "Mixtes, partagées entre le 401 et le 404 : ${mixtes}"
echo "À reprendre, importées sans ligne au 404 dans un exercice à l'engagement : ${a_reprendre}"

if [ -n "$details" ]; then
	echo
	echo "base	facture	numéro	date	lignes	import"
	printf '%s' "$details"
fi
