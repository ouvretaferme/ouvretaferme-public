#!/bin/bash
#
# Contrôle de la migration do100 : le régime de TVA et la fréquence passent sur l'exercice.
#
# Lecture seule, aucune écriture, aucun DDL. À lancer sur le serveur avant la mise en production
# (volet « avant »), puis après que les structures se sont connectées (volet « après »).
#
#   bash internal/data/check/check100.sh avant     ce que la migration va supprimer
#   bash internal/data/check/check100.sh apres     ce qu'elle a repris, et ce qui manque
#   bash internal/data/check/check100.sh           les deux
#
# En dev :
#   MYSQL="docker exec -i mysql-otf mysql -uroot" BASE=dev_ouvretaferme bash internal/data/check/check100.sh
#
# Le volet « avant » ventile les déclarations par statut : sans lui, on ne sait pas combien de CA12
# en brouillon la migration supprimera, et on l'apprendrait après.

set -u

MYSQL=${MYSQL:-"mysql -uroot"}
BASE=${BASE:-ouvretaferme}
VOLET=${1:-tout}

# Les bases de structure portent le même préfixe d'environnement que la base commune
if [ "$BASE" = "ouvretaferme" ]; then
	PREFIXE="structure_"
else
	PREFIXE="${BASE%ouvretaferme}structure_"
fi

# Les régimes fiscaux qui disent une activité agricole (CGI, art. 298 bis) : ce sont eux qui font
# passer un exercice au simplifié agricole, et donc ses CA12 en brouillon à la suppression.
AGRICOLE="'micro-ba', 'ba-reel-simplifie', 'ba-reel-normal'"

# Dernier jour où un exercice peut s'ouvrir sous le régime simplifié (VatSetting::SIMPLIFIED_END)
SIMPLIFIE_FIN="2026-12-31"

# Toute la liste d'un coup, jamais une requête dans la boucle qui la lit : le client mysql appelé
# dans un `while read` vide le flux d'entrée et la boucle s'arrête au premier tour.
ids=$($MYSQL -N -e "
	SELECT s.id
	FROM ${BASE}.structure s
	JOIN ${BASE}.structureFeature f ON f.structure = s.id
	WHERE f.hasAccounting = 1
	ORDER BY s.id
" < /dev/null)

nombre=$(echo "$ids" | grep -c .)

echo "Structures avec la comptabilité : ${nombre}"
echo

if [ "$VOLET" = "avant" ] || [ "$VOLET" = "tout" ]; then

	echo "=== AVANT LA MIGRATION ==="
	echo "Déclarations CA12, par statut et par régime fiscal de leur exercice."
	echo "Celles en brouillon dont l'exercice est agricole seront SUPPRIMÉES par do100."
	echo

	examinees=0
	sans_table=0
	total_ca12=0
	supprimees=0
	lignes=""

	for id in $ids; do

		base="${PREFIXE}${id}"

		existe=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${base}' AND TABLE_NAME = 'vatDeclaration'" < /dev/null)

		if [ "$existe" != "1" ]; then
			sans_table=$((sans_table + 1))
			continue
		fi

		examinees=$((examinees + 1))

		detail=$($MYSQL -N -e "
			SELECT d.status, f.taxSystem, COUNT(*)
			FROM ${base}.vatDeclaration d
			JOIN ${base}.financialYear f ON f.id = d.financialYear
			WHERE d.cerfa = 'ca12'
			GROUP BY d.status, f.taxSystem
		" < /dev/null)

		if [ -z "$detail" ]; then
			continue
		fi

		while IFS=$'\t' read -r statut regime nombre_lignes; do

			[ -z "$statut" ] && continue

			total_ca12=$((total_ca12 + nombre_lignes))
			lignes="${lignes}${statut}\t${regime}\t${nombre_lignes}\n"

			if [ "$statut" = "draft" ]; then
				case ",${AGRICOLE}," in
					*"'${regime}'"*) supprimees=$((supprimees + nombre_lignes)) ;;
				esac
			fi

		done <<< "$detail"

	done

	echo "Bases examinées : ${examinees} (sans table vatDeclaration : ${sans_table})"
	echo "Déclarations CA12 trouvées : ${total_ca12}"
	echo
	echo -e "statut\trégime fiscal\tnombre"
	echo -e "$lignes" | grep -v '^$' | sort | awk -F'\t' '{c[$1"\t"$2]+=$3} END {for(k in c) print k"\t"c[k]}' | sort
	echo
	echo "À SUPPRIMER par do100 (brouillons CA12 d'un exercice agricole) : ${supprimees}"
	echo

fi

if [ "$VOLET" = "apres" ] || [ "$VOLET" = "tout" ]; then

	echo "=== APRÈS LA MIGRATION ==="
	echo "Un exercice d'une structure assujettie doit porter son régime et sa fréquence."
	echo

	examinees=0
	sans_table=0
	non_migrees=0
	exemples=""
	a_reprendre=0
	laisses_2027=0
	options_a_confirmer=0
	brouillons_restants=0
	repartition=""

	for id in $ids; do

		base="${PREFIXE}${id}"

		existe=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '${base}' AND TABLE_NAME = 'financialYear'" < /dev/null)

		if [ "$existe" != "1" ]; then
			sans_table=$((sans_table + 1))
			continue
		fi

		colonne=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '${base}' AND TABLE_NAME = 'financialYear' AND COLUMN_NAME = 'vatSystem'" < /dev/null)

		if [ "$colonne" != "1" ]; then
			non_migrees=$((non_migrees + 1))
			if [ "$non_migrees" -le 5 ]; then
				exemples="${exemples} #${id}"
			fi
			continue
		fi

		examinees=$((examinees + 1))

		# Une structure assujettie et pourvue d'une fréquence : tous ses exercices doivent être repris
		assujettie=$($MYSQL -N -e "SELECT COUNT(*) FROM ${BASE}.structureConfiguration WHERE structure = ${id} AND hasVat = 1 AND vatFrequency IS NOT NULL" < /dev/null)

		if [ "$assujettie" = "1" ]; then

			# Un exercice ouvert après la fin du régime simplifié qui aurait reçu le réel simplifié est
			# laissé à NULL par do100, volontairement : la structure doit choisir son nouveau régime.
			# Le compter comme un écart ferait passer pour un défaut ce que la migration doit faire.
			manquants=$($MYSQL -N -e "
				SELECT COUNT(*) FROM ${base}.financialYear
				WHERE (vatSystem IS NULL OR vatFrequency IS NULL)
					AND (taxSystem IN (${AGRICOLE}) OR startDate <= '${SIMPLIFIE_FIN}')
			" < /dev/null)

			attendus=$($MYSQL -N -e "
				SELECT COUNT(*) FROM ${base}.financialYear
				WHERE vatSystem IS NULL
					AND taxSystem NOT IN (${AGRICOLE})
					AND startDate > '${SIMPLIFIE_FIN}'
			" < /dev/null)

			laisses_2027=$((laisses_2027 + attendus))

			if [ "$manquants" != "0" ]; then
				echo "  ÉCART #${id} : ${manquants} exercice(s) sans régime ni fréquence alors que la structure est assujettie"
				a_reprendre=$((a_reprendre + manquants))
			fi

		fi

		# L'option de l'annuelle (do102) n'est pas reprise : un exercice ouvert au simplifié agricole annuel
		# qui ne tient pas dans une année civile close au 31/12 la garde à NULL et reverra la carte. Attendu.
		option=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '${base}' AND TABLE_NAME = 'financialYear' AND COLUMN_NAME = 'vatOptionFinancialYear'" < /dev/null)

		if [ "$option" = "1" ]; then
			a_confirmer=$($MYSQL -N -e "
				SELECT COUNT(*) FROM ${base}.financialYear
				WHERE status = 'open' AND vatSystem = 'simplifie-agricole' AND vatFrequency = 'annually'
					AND vatOptionFinancialYear IS NULL
					AND NOT (YEAR(startDate) = YEAR(endDate) AND DATE_FORMAT(endDate, '%m-%d') = '12-31')
			" < /dev/null)
			options_a_confirmer=$((options_a_confirmer + a_confirmer))
		fi

		restants=$($MYSQL -N -e "
			SELECT COUNT(*)
			FROM ${base}.vatDeclaration d
			JOIN ${base}.financialYear f ON f.id = d.financialYear
			WHERE d.status = 'draft' AND d.cerfa = 'ca12' AND f.vatSystem = 'simplifie-agricole'
		" < /dev/null)

		if [ "$restants" != "0" ]; then
			echo "  ÉCART #${id} : ${restants} brouillon(s) CA12 sur un exercice agricole"
			brouillons_restants=$((brouillons_restants + restants))
		fi

		detail=$($MYSQL -N -e "
			SELECT vatSystem, vatFrequency, COUNT(*)
			FROM ${base}.financialYear
			WHERE vatSystem IS NOT NULL
			GROUP BY vatSystem, vatFrequency
		" < /dev/null)

		if [ -n "$detail" ]; then
			repartition="${repartition}${detail}\n"
		fi

	done

	echo
	echo "Bases examinées : ${examinees} (sans table financialYear : ${sans_table})"
	echo "Structures pas encore migrées (colonne vatSystem absente) : ${non_migrees}${exemples:+, dont}${exemples}"
	echo
	echo -e "régime\tfréquence\tnombre"
	echo -e "$repartition" | grep -v '^$' | awk -F'\t' '{c[$1"\t"$2]+=$3} END {for(k in c) print k"\t"c[k]}' | sort
	echo
	echo "Exercices non repris sur une structure assujettie : ${a_reprendre}"
	echo "Brouillons CA12 restants sur un exercice agricole : ${brouillons_restants}"
	echo "Exercices laissés à NULL volontairement (ouverts après le ${SIMPLIFIE_FIN}, hors agricole) : ${laisses_2027}"
	echo "Exercices ouverts dont l'option de l'annuelle est à confirmer (simplifié agricole annuel hors année civile) : ${options_a_confirmer}"
	echo
	echo "Les deux PREMIERS compteurs doivent être à zéro. Un exercice non repris est une structure qui"
	echo "reverra la carte « Ouvrir ma première déclaration » alors qu'elle déclare déjà."
	echo "Le troisième n'est pas un écart : le régime simplifié n'existe plus pour ces exercices, et"
	echo "c'est à la structure de choisir le sien. Elle verra la carte, ce qui est le comportement voulu."
	echo "Le quatrième non plus : la structure confirme sur quelle période porte son annuelle en ouvrant sa"
	echo "déclaration."

fi
