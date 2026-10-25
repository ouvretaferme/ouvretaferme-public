#!/bin/bash
#
# Contrôle de la migration do118 : l'exigibilité de la TVA d'une facture électronique (BT-8 et BT-7)
# passe du JSON de la plateforme dans les colonnes invoicing.vatDueDateCode et vatDueDate.
#
# Lecture seule, aucune écriture, aucun DDL. À lancer sur le serveur avant la mise en production, pour
# savoir ce que la reprise écrira, puis après `data/migrate`, où les deux compteurs d'écart doivent être
# à zéro.
#
#   bash internal/data/check/check118.sh
#
# En dev :
#   MYSQL="docker exec -i mysql-otf mysql -uroot" BASE=dev_ouvretaferme bash internal/data/check/check118.sh
#
# Les factures électroniques vivent dans toutes les bases de structure, comptabilité activée ou non :
# la liste vient donc des bases qui portent la table, et non de structureFeature.hasAccounting.

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

CODE="JSON_EXTRACT(raw, '\$.vat_point_date_code')"
DATE="JSON_UNQUOTE(JSON_EXTRACT(raw, '\$.vat_point_date'))"
CODE_A_REPRENDRE="JSON_TYPE(${CODE}) IN ('STRING', 'INTEGER')"
DATE_A_REPRENDRE="${DATE} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}\$'"

# Toute la liste d'un coup, jamais une requête dans la boucle qui la lit : le client mysql appelé
# dans un `while read` vide le flux d'entrée et la boucle s'arrête au premier tour.
bases=$($MYSQL -N -e "
	SELECT TABLE_SCHEMA
	FROM information_schema.TABLES
	WHERE TABLE_NAME = 'invoicing' AND TABLE_SCHEMA LIKE '${MOTIF}%'
	ORDER BY TABLE_SCHEMA
" < /dev/null)

nombre=$(echo "$bases" | grep -c .)

echo "Bases de structure qui portent la table invoicing : ${nombre}"
echo

examinees=0
non_migrees=0
exemples=""
factures=0
a_reprendre=0
codes_manquants=0
dates_manquantes=0
dates_mal_formees=0
divergences=0
repartition=""

for base in $bases; do

	colonne=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '${base}' AND TABLE_NAME = 'invoicing' AND COLUMN_NAME = 'vatDueDateCode'" < /dev/null)

	if [ "$colonne" != "1" ]; then

		non_migrees=$((non_migrees + 1))
		if [ "$non_migrees" -le 5 ]; then
			exemples="${exemples} ${base}"
		fi

		attendues=$($MYSQL -N -e "SELECT COUNT(*) FROM ${base}.invoicing WHERE ${CODE_A_REPRENDRE} OR ${DATE_A_REPRENDRE}" < /dev/null)
		a_reprendre=$((a_reprendre + attendues))

		continue

	fi

	examinees=$((examinees + 1))

	compte=$($MYSQL -N -e "
		SELECT
			COUNT(*),
			IFNULL(SUM(vatDueDateCode IS NULL AND ${CODE_A_REPRENDRE}), 0),
			IFNULL(SUM(vatDueDate IS NULL AND ${DATE_A_REPRENDRE}), 0),
			IFNULL(SUM(${DATE} IS NOT NULL AND ${DATE} != 'null' AND NOT (${DATE_A_REPRENDRE})), 0),
			IFNULL(SUM(vatDueDateCode IS NOT NULL AND ${CODE_A_REPRENDRE} AND vatDueDateCode != JSON_UNQUOTE(${CODE})), 0)
		FROM ${base}.invoicing
	" < /dev/null)

	read -r total codes dates mal_formees differents <<< "$compte"

	factures=$((factures + total))
	codes_manquants=$((codes_manquants + codes))
	dates_manquantes=$((dates_manquantes + dates))
	dates_mal_formees=$((dates_mal_formees + mal_formees))
	divergences=$((divergences + differents))

	if [ "$codes" != "0" ] || [ "$dates" != "0" ]; then
		echo "  ÉCART ${base} : ${codes} code(s) et ${dates} date(s) présents dans le JSON et absents de leur colonne"
	fi

	detail=$($MYSQL -N -e "
		SELECT direction, IFNULL(vatDueDateCode, '-'), IF(vatDueDate IS NULL, '-', 'date'), COUNT(*)
		FROM ${base}.invoicing
		GROUP BY 1, 2, 3
	" < /dev/null)

	if [ -n "$detail" ]; then
		repartition="${repartition}${detail}\n"
	fi

done

echo
echo "Bases examinées : ${examinees}, factures électroniques lues : ${factures}"
echo "Bases pas encore migrées (colonne vatDueDateCode absente) : ${non_migrees}${exemples:+, dont}${exemples}"
echo "Factures que la reprise remplira dans ces bases : ${a_reprendre}"
echo
echo -e "sens\tcode\tdate\tnombre"
echo -e "$repartition" | grep -v '^$' | awk -F'\t' '{c[$1"\t"$2"\t"$3]+=$4} END {for(k in c) print k"\t"c[k]}' | sort
echo
echo "Codes présents dans le JSON et absents de la colonne : ${codes_manquants}"
echo "Dates présentes dans le JSON et absentes de la colonne : ${dates_manquantes}"
echo "Dates du JSON mal formées, laissées hors colonne : ${dates_mal_formees}"
echo "Colonnes dont le code diffère de celui du JSON : ${divergences}"
echo
echo "Après data/migrate, les deux PREMIERS compteurs doivent être à zéro, et aucune base ne doit rester"
echo "non migrée. Une date mal formée n'est pas un écart : la reprise la laisse, et la facture est lue"
echo "comme si elle ne portait pas de date d'exigibilité. Un code qui diffère du JSON n'est pas repris non"
echo "plus : la colonne fait foi, elle a été remplie par une synchronisation plus récente que la reprise."
