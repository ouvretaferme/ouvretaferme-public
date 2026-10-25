#!/bin/bash
#
# Contrôle de la migration do216 : les comptes 671 et 675, que le règlement ANC 2022-06 retire du plan
# pour les exercices ouverts à compter du 1er janvier 2025, passent inactifs dans les exercices encore
# ouverts au plan agricole commencés à cette date ou après, sauf s'ils portent une écriture sur
# l'exercice, la leur ou celle d'un de leurs sous-comptes.
#
# Lecture seule, aucune écriture, aucun DDL. À lancer sur le serveur avant la mise en production, pour
# savoir ce que la migration écrira, puis après `data/migrate`, où le compteur d'écart doit être à zéro.
#
#   bash internal/data/check/check216.sh
#
# En dev :
#   MYSQL="docker exec -i mysql-otf mysql -uroot" BASE=dev_ouvretaferme bash internal/data/check/check216.sh
#
# Le plan comptable ne vit que dans les bases dont la comptabilité est activée : la liste vient donc des
# bases qui portent la table account.

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

# Toute la liste d'un coup, jamais une requête dans la boucle qui la lit : le client mysql appelé
# dans un `while read` vide le flux d'entrée et la boucle s'arrête au premier tour.
bases=$($MYSQL -N -e "
	SELECT TABLE_SCHEMA
	FROM information_schema.TABLES
	WHERE TABLE_NAME = 'account' AND TABLE_SCHEMA LIKE '${MOTIF}%'
	ORDER BY TABLE_SCHEMA
" < /dev/null)

nombre=$(echo "$bases" | grep -c .)

echo "Bases de structure qui portent la table account : ${nombre}"
echo

exercices=0
inactifs=0
gardes=0
ecarts=0

for base in $bases; do

	compte=$($MYSQL -N -e "
		SELECT
			(SELECT COUNT(*) FROM ${base}.financialYear f
				WHERE f.status = 'open' AND f.plan = 'agricultural' AND f.startDate >= '2025-01-01'),
			IFNULL(SUM(a.status = 'inactive'), 0),
			IFNULL(SUM(a.status = 'active' AND EXISTS (
				SELECT 1 FROM ${base}.journalOperation o
				WHERE o.financialYear = a.financialYear AND o.accountLabel LIKE CONCAT(a.class, '%')
			)), 0),
			IFNULL(SUM(a.status = 'active' AND NOT EXISTS (
				SELECT 1 FROM ${base}.journalOperation o
				WHERE o.financialYear = a.financialYear AND o.accountLabel LIKE CONCAT(a.class, '%')
			)), 0)
		FROM ${base}.account a
		JOIN ${base}.financialYear f ON f.id = a.financialYear
		WHERE a.class IN ('671', '675')
		AND f.status = 'open' AND f.plan = 'agricultural' AND f.startDate >= '2025-01-01'
	" < /dev/null)

	read -r visees inactives mouvementes restants <<< "$compte"

	exercices=$((exercices + visees))
	inactifs=$((inactifs + inactives))
	gardes=$((gardes + mouvementes))
	ecarts=$((ecarts + restants))

	if [ "$mouvementes" != "0" ]; then
		echo "  GARDÉ ${base} : ${mouvementes} compte(s) 671 ou 675 actif(s), mouvementé(s) sur l'exercice"
	fi

	if [ "$restants" != "0" ]; then
		echo "  ÉCART ${base} : ${restants} compte(s) 671 ou 675 actif(s) sans écriture sur l'exercice"
	fi

done

echo
echo "Exercices ouverts au plan agricole commencés depuis le 01/01/2025 : ${exercices}"
echo "Comptes 671 ou 675 inactifs dans ces exercices : ${inactifs}"
echo "Comptes 671 ou 675 restés actifs parce que mouvementés : ${gardes}"
echo "Comptes 671 ou 675 actifs sans écriture : ${ecarts}"
echo
echo "Avant data/migrate, le dernier compteur dit combien de comptes la migration désactivera. Après, il"
echo "doit être à zéro. Un compte gardé n'est pas un écart : le point de clôture des comptes"
echo "exceptionnels le montre."
