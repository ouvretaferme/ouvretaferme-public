<?php
namespace entry;

class AlertUi {

	public static function getError(string $fqn, array $options = []): mixed {

		return match($fqn) {

			'Entry::text.incomplete' => s("Cette saisie n'est pas complète."),
			'Entry::harvest.cancellable' => s("Cette récolte ne peut plus être annulée."),
			'Entry::time.cancellable' => s("Ce temps de travail ne peut plus être annulé."),
			'Entry::done.cancellable' => s("Cette intervention ne peut plus être annulée."),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn, array $options = []): ?string {

		return match($fqn) {

			'Entry::harvested' => HarvestIntentionUi::getSuccess($options),
			'Entry::cancelled' => HarvestIntentionUi::getCancelled($options),
			'Entry::harvestTimed' => HarvestIntentionUi::getTimeSuccess($options),
			'Entry::timed' => TimeIntentionUi::getSuccess($options),
			'Entry::timeCancelled' => TimeIntentionUi::getCancelled($options),
			'Entry::done' => DoneIntentionUi::getSuccess($options),
			'Entry::doneCancelled' => DoneIntentionUi::getCancelled($options),
			'Entry::doneRemoved' => DoneIntentionUi::getRemoved($options),
			'Entry::planned' => InterventionIntentionUi::getPlanned($options),

			default => NULL

		};

	}

}
?>
