<?php
/**
 * Reconstruit les tables de la bibliothèque de cultures, celles des compositions de panier, celles
 * des prix au détail et des saisons de vente, puis l'année en cours de celles du tableau de bord
 * maraîchage des groupes, et le 1er janvier aussi l'année écoulée : le reste du passé du tableau de
 * bord ne se refait que par la reprise `aggregate/configure/vegetableAi`.
 *
 * De nuit, parce que le calcul porte sur toutes les structures et qu'aucun de ses
 * résultats ne dépend de l'instant : une contribution du jour paraît le lendemain,
 * ce qui ne se voit pas sur un agrégat de plusieurs centaines de fermes.
 */
new Page()
	->cron('index', function($data) {

		\aggregate\CultivationProjectionAiLib::project();
		\plant\PlantLib::projectCommunity();
		\aggregate\VegetableProjectionAiLib::projectNight();

	}, interval: '0 4 * * *');
?>
