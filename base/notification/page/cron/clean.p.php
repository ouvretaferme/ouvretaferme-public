<?php
/**
 * Le nettoyage des notifications de plus de trente jours, lues ou non, dans toutes les bases de
 * structure. Il tourne même drapeau coupé, sinon les lignes déjà émises ne partiraient jamais.
 */
new Page()
	->cron('index', function($data) {

		\notification\NotificationLib::clean();

	}, interval: '0 2 * * *');
?>
