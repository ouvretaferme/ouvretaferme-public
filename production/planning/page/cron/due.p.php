<?php
/**
 * Recalcule chaque semaine ce que les sources disent dû au planning (`\planning\DueLib::refreshAll()`).
 *
 * C'est un filet : chaque geste d'une source recalcule sa structure, et le temps qui passe ne demande
 * rien, la lecture comparant la date à l'horloge. La passe rattrape une écriture qui n'aurait pas
 * recalculé, une reprise de données ou un geste interrompu. Le lundi à 5 h, avant le point du lundi
 * du carnet (`toolbox/cron/maintenance`, 6 h), pour que la semaine s'ouvre sur un état juste.
 */
new Page()
	->cron('index', function($data) {

		\planning\DueLib::refreshAll();

	}, interval: '0 5 * * 1');
?>
