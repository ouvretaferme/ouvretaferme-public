<?php
/**
 * Remplit le cache des prévisions météo que lit le bloc de l'accueil (`structure\WeatherAiLib`).
 *
 * Toutes les trois heures, la moitié de la durée de vie d'une clé : un passage manqué ne retire
 * rien. Sans clé d'abonnement, `refresh()` n'appelle rien.
 */
new Page()
	->cron('refresh', function($data) {

		\structure\WeatherAiLib::refresh(\structure\WeatherAiLib::getCells());

	}, interval: '7 */3 * * *');
?>
