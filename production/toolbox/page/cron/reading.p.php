<?php
/**
 * Met en file le rappel des compteurs qu'on ne relève plus, ceux du carnet d'entretien
 * (`\toolbox\NotificationLib::READING`) et ceux de l'eau (`\toolbox\NotificationLib::WATER_READING`).
 *
 * Le 5 de chaque mois : c'est le rythme que chaque ferme règle dans son carnet, ou dans sa
 * consommation d'eau, qui dit si le rappel part ce mois-ci (`NotificationLib::getReadings()`,
 * `getWaterReadings()`), le défaut gardant janvier, avril, juillet et octobre (arbitrage du 01/10/2026, « Envoie le 5e jour de janvier/avril/juillet/octobre »).
 */
new Page()
	->cron('index', function($data) {

		\toolbox\NotificationLib::emitReadings();
		\toolbox\NotificationLib::emitWaterReadings();

	}, interval: '0 6 5 * *');
?>
