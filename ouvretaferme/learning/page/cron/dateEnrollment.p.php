<?php
/**
 * Le rappel d'une date, chaque matin, aux personnes confirmées dont la date commence dans un ou deux
 * jours (`DateEnrollmentLib::remind()`). Deux matins pour une date : le second rattrape un passage
 * manqué, et la ligne déjà rappelée n'en reçoit pas un second. Une visio dont le réseau n'a pas
 * encore envoyé le lien, qu'il reste à poser ou qu'il soit posé sans être parti
 * (`Date::isLinkAwaited()`), est rappelée quand même : l'e-mail dit que le lien suit sans le
 * montrer, et le réseau reçoit une alerte par date à son adresse de contact, qui lui dit de le
 * poser ou de l'envoyer.
 *
 * L'invitation à donner son avis, chaque matin, aux personnes confirmées d'une date terminée la
 * veille ou l'avant-veille (`DateEnrollmentLib::invite()`, lot L15c), sur le même principe. Jamais
 * lancée à la main en dev : elle balaie toute la base.
 */
new Page()
	->cron('remind', function($data) {

		if(\learning\LearningSetting::isActiveAnywhere() === FALSE) {
			return;
		}

		\learning\DateEnrollmentLib::remind();

	}, interval: '30 7 * * *')
	->cron('invite', function($data) {

		if(\learning\LearningSetting::isActiveAnywhere() === FALSE) {
			return;
		}

		\learning\DateEnrollmentLib::invite(\learning\DateEnrollmentLib::getReviewable());

	}, interval: '0 9 * * *');
?>
