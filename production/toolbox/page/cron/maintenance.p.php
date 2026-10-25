<?php
/**
 * Met en file le point du lundi du carnet d'entretien : les entretiens en retard et ceux à faire dans
 * la semaine (`\toolbox\NotificationLib::MAINTENANCE`).
 *
 * Le lundi matin, pour que la ligne arrive avec la semaine de travail. Une seule fois par semaine et
 * sans fenêtre de rattrapage : le type revient chaque lundi (`once` à FALSE), une fenêtre de plusieurs
 * jours l'annoncerait donc deux fois. Un lundi sans cron saute la semaine, le carnet garde ses
 * retards à l'écran.
 */
new Page()
	->cron('index', function($data) {

		\toolbox\NotificationLib::emitMaintenances();

	}, interval: '0 6 * * 1');
?>
