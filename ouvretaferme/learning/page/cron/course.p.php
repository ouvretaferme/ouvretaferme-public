<?php
/**
 * La marque d'une formation datée suit les séances de ses dates à venir (`CourseLib::synchronizeType()`,
 * lot date-mixte), recalculée à chaque écriture d'une date. Le temps qui passe la change aussi sans
 * que personne écrive : une date qui finit sort des dates à venir. Chaque heure, les formations dont
 * une date planifiée a fini dans les deux dernières heures sont recalculées ; le second passage
 * rattrape un passage manqué, et un recalcul sans changement n'écrit rien.
 */
new Page()
	->cron('type', function($data) {

		if(\learning\LearningSetting::isActiveAnywhere() === FALSE) {
			return;
		}

		\learning\CourseLib::synchronizeTypeOfEnded();

	}, interval: '5 * * * *');
?>
