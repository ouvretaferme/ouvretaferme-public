<?php
namespace hr;

/**
 * Ce que le package `hr` apporte au centre de notifications, hors affichage : une demande d'absence qui attend
 * la réponse du gérant.
 *
 * Le contrat de la paire `NotificationLib` + `NotificationUi` est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 *
 * ⚠️ Le nom de cette classe est fixe, parce que c'est lui que la convention résout depuis le segment qui
 * précède le premier tiret du type (`hr-absence-requested` → `hr`).
 *
 * La personne n'apprend pas la réponse par une notification : le moteur notifie un droit, jamais une personne.
 * Elle la lit sur son planning, dans le bloc de ses absences.
 */
class NotificationLib {

	/**
	 * Une personne au planning demande une absence. `reference` porte l'absence.
	 */
	const string ABSENCE_REQUESTED = 'hr-absence-requested';

	/**
	 * Réservé à qui administre, seul à valider (`Absence::canValidate()`), et borné aux producteurs qui ont allumé
	 * la RH. Le moteur ne lit qu'un drapeau : la règle entière, production comprise, se lit par `isHrReceivable()`.
	 */
	public static function getTypes(): array {

		return [
			self::ABSENCE_REQUESTED => [
				'right' => 'admin',
				'feature' => 'hasHr',
				'structureTypes' => [\structure\Structure::PRODUCER],
				'family' => \notification\NotificationSetting::STRUCTURE,
				'values' => [\notification\NotificationSetting::OFF, \notification\NotificationSetting::ON],
				'default' => \notification\NotificationSetting::ON,
				'once' => TRUE,
				'receivable' => [self::class, 'isHrReceivable']
			]
		];

	}

	/**
	 * Production éteinte, la RH s'éteint avec elle (`Structure::acceptHr()`) : une demande restée dans la file ne
	 * s'annonce plus.
	 */
	public static function isHrReceivable(\structure\Structure $eStructure): bool {
		return $eStructure->acceptHr();
	}

	public static function absenceRequested(Absence $eAbsence): void {

		\notification\NotificationLib::emit($eAbsence['structure'], self::ABSENCE_REQUESTED, $eAbsence['id']);

	}

	/**
	 * Relit l'absence au moment du traitement : une demande déjà validée, refusée ou retirée n'a plus rien à
	 * annoncer. Bornée par la structure, `hrAbsence` vivant dans la base commune. Le motif n'est pas repris :
	 * il peut être une donnée de santé, et la notification se lit hors de l'écran des absences.
	 */
	public static function describe(string $type, \structure\Structure $eStructure, ?int $reference): \notification\Notification {

		return match($type) {
			self::ABSENCE_REQUESTED => self::describeAbsenceRequested($eStructure, $reference)
		};

	}

	private static function describeAbsenceRequested(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		$eAbsence = Absence::model()
			->select([
				'from', 'to', 'unit', 'duration',
				'user' => ['firstName', 'lastName']
			])
			->whereId($reference)
			->whereStructure($eStructure)
			->whereStatus(Absence::PENDING)
			->get();

		if($eAbsence->empty()) {
			return new \notification\Notification();
		}

		return new \notification\Notification([
			'group' => NULL,
			'content' => [
				'firstName' => $eAbsence['user']['firstName'],
				'lastName' => $eAbsence['user']['lastName'],
				'from' => $eAbsence['from'],
				'to' => $eAbsence['to'],
				'unit' => $eAbsence['unit'],
				'duration' => $eAbsence['duration']
			]
		]);

	}

	public static function getAmount(\notification\Notification $eNotification): ?float {

		return NULL;

	}

	public static function getGroupName(\notification\Notification $eNotification): ?string {

		return NULL;

	}

	/**
	 * ⚠️ Construite depuis la structure qui reçoit, jamais depuis un identifiant du contenu.
	 */
	public static function getUrl(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			self::ABSENCE_REQUESTED => AbsenceUi::urlStructure(new \structure\Structure(['id' => $eNotification['eStructure']['id']]))
		};

	}

}
?>
