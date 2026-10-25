<?php
/**
 * L'annonce des formations partagées, chaque matin (`ShareLib::announceOpened()`, F35) : les
 * partages qui s'ouvrent ce jour-là, ou qu'un passage manqué a laissés en attente. Ceux qui
 * s'ouvrent le jour de leur enregistrement sont annoncés tout de suite par le panneau de partage,
 * et le cron ne les retrouve pas. Jamais lancé à la main en dev : il balaie toute la base et envoie.
 */
new Page()
	->cron('announce', function($data) {

		if(\learning\LearningSetting::isActiveAnywhere() === FALSE) {
			return;
		}

		\learning\ShareLib::announceOpened();

	}, interval: '0 8 * * *');
?>
