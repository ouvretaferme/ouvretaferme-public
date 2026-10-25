<?php
namespace planning;

/**
 * Le nouveau planning, écran par écran.
 *
 * Il vit à côté du planning de `series` jusqu'à le remplacer, et ne possède aucune de ses données :
 * chaque écran lit celles de `series`.
 *
 * Seule table du package, `Due` porte ce qui est dû hors cultures, que d'autres packages calculent
 * et que le planning de `series` affiche déjà (`DueLib`).
 */
class PlanningSetting extends \Settings {

	/**
	 * Les écrans codés, par la clé de leur onglet. Un onglet ne s'affiche que si son écran est ici
	 * (`TabUi::get()`), et chaque écran a son fichier de page (`ScreenSentinel.t.php`) : un lot allonge
	 * cette liste en même temps qu'il pose sa page.
	 */
	const array PAGES = ['semaine', 'recoltes', 'temps', 'absences'];

	/**
	 * Les semaines que la frise des Récoltes montre de chaque côté de la semaine affichée : une période
	 * qui en sort est coupée au bord (`HarvestWeekLib::prepareExpected()`).
	 */
	const int FRIEZE_WEEKS = 13;

	/**
	 * Le panneau « Nouvelle récolte », avant toute frappe : toutes les cultures de la semaine, puis au plus
	 * `SUGGESTION_NEAR` récoltes proches (`HarvestWeekLib::getSuggested()`). À la frappe, au plus
	 * `HARVEST_QUERY_SERIES` séries, coupées après le rangement (`HarvestWeekLib::getForQuery()`).
	 */
	const int SUGGESTION_NEAR = 10;
	const int HARVEST_QUERY_SERIES = 10;

	/**
	 * La recherche « Espèce ou série » de « Nouvelle intervention »
	 * (`HarvestWeekLib::getForTaskQuery()`, `TaskCreateLib::getPlantsForQuery()`) : les séries dont la récolte
	 * croise `TASK_QUERY_MONTHS` mois de chaque côté de la semaine, au plus `TASK_QUERY_SERIES`, puis au plus
	 * `TASK_QUERY_PLANTS` espèces, dès l'ouverture de la liste, chaque coupe levée par le « Voir les N autres » de
	 * sa section (retour 382). La même fenêtre de
	 * `TASK_QUERY_MONTHS` mois borne « Récolte proche » dans « Nouvelle récolte » (`HarvestWeekLib::isNear()`).
	 */
	const int TASK_QUERY_MONTHS = 3;
	const int TASK_QUERY_SERIES = 15;
	const int TASK_QUERY_PLANTS = 5;

	/**
	 * La section « Fréquentes » du champ « Intervention » de « Nouvelle intervention »,
	 * à l'ouverture de la liste (retour 257) : au plus `TASK_ACTION_FREQUENT` actions, celles qui comptent le
	 * plus d'interventions sur les `TASK_ACTION_FREQUENT_WEEKS` dernières semaines, la semaine en cours
	 * comprise, et sur autant de semaines à venir l'année d'avant (`TaskDayLib::getActionsFrequent()`).
	 */
	const int TASK_ACTION_FREQUENT = 5;
	const int TASK_ACTION_FREQUENT_WEEKS = 3;

	/**
	 * Les pastilles sous le champ « Intervention » (piste F de R-CreationRapide) : les `TASK_CREATE_SHORTCUTS`
	 * actions les plus fréquentes, sur la même fenêtre que la section « Fréquentes ».
	 */
	const int TASK_CREATE_SHORTCUTS = 6;

	/**
	 * Les fenêtres de « Ventes à venir » proposées sur l'écran Récoltes, en jours après aujourd'hui, qu'elles
	 * comprennent toutes (0 pour aujourd'hui seul, R-Retours-0510, W) : celle du membre
	 * (`Member::viewPlanningSalesDays`) est l'une d'elles, le schéma ne bornant que l'intervalle.
	 */
	const array SALES_DAYS = [0, 2, 3, 4, 5, 7, 14];

}
?>
