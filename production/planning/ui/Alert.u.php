<?php
namespace planning;

/**
 * Les messages du package. Le cadriciel les cherche sous ce nom, `\planning\AlertUi`
 * (`framework/core/Fail.php`, `format()`), d'après le package de la classe qui lève l'échec.
 * Les refus de `build()` sur les modules de `series` restent dans `series/ui/Alert.u.php`.
 */
class AlertUi {

	public static function getError(string $fqn, array $arguments = []): mixed {

		return match($fqn) {

			'Reminder::text.check' => s("Écrivez le rappel en {value} caractères au plus.", Reminder::model()->getPropertyRange('text')[1]),
			'Reminder::week.check' => s("Choisissez la semaine du rappel."),
			'Reminder::week.notPast' => s("Une semaine passée ne prend plus de rappel."),
			'Reminder::updatePast' => s("Ce rappel a été coché dans une semaine passée et ne se corrige plus."),
			'Reminder::deletePast' => s("Ce rappel a été coché dans une semaine passée et ne s'efface plus."),
			'Reminder::date.check' => s("Ce jour n'est pas une date valide."),
			'Reminder::date.dateInWeek' => s("Choisissez un jour de la semaine du rappel."),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn, array $options = []): ?string {

		return match($fqn) {

			'Task::harvested' => s("La récolte est enregistrée."),

			// `TaskCreateLib::getSuccessOutside()`
			'Task::createdOutside' => ($options['year'] === NULL) ?
				p("L'intervention a été ajoutée en semaine {week}.", "Les {value} interventions ont été ajoutées en semaine {week}.", $options['count'], ['week' => $options['week']]) :
				p("L'intervention a été ajoutée en semaine {week} de {year}.", "Les {value} interventions ont été ajoutées en semaine {week} de {year}.", $options['count'], ['week' => $options['week'], 'year' => $options['year']]),

			default => NULL

		};

	}

}
?>
