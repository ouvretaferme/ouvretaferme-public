<?php
namespace hr;

/**
 * Ce que le package `hr` apporte au centre de notifications côté affichage : les mots et l'icône d'une demande
 * d'absence.
 *
 * Pendant du `NotificationLib` du même package. Le contrat de la paire est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 */
class NotificationUi {

	public function getTypeName(string $type): string {

		return match($type) {
			NotificationLib::ABSENCE_REQUESTED => s("Demande d'absence")
		};

	}

	public function getTypeDescription(string $type, \structure\Structure $eStructure): string {

		return match($type) {
			NotificationLib::ABSENCE_REQUESTED => s("Une personne de l'équipe demande une absence depuis son planning.")
		};

	}

	public function getLock(string $right): string {

		return match($right) {
			'admin' => s("Réservé aux administrateurs")
		};

	}

	/** @return array [classes, svg] */
	public function getIcon(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::ABSENCE_REQUESTED => ['util-icon-settings', \Asset::icon('calendar-x')]
		};

	}

	public function getText(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			NotificationLib::ABSENCE_REQUESTED => s("{user} demande une absence {dates}", [
				'user' => $this->getActor($eNotification),
				'dates' => AbsenceUi::getDates($this->getAbsence($eNotification))
			])
		};

	}

	/**
	 * Ce type ne se groupe pas (`group` à NULL) : une tête de groupe n'en porte jamais qu'une. Le texte d'un
	 * groupe existe pour le contrat de la paire.
	 */
	public function getGroupText(\notification\Notification $eNotificationHead): string {

		return $this->getText($eNotificationHead);

	}

	public function getActor(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			NotificationLib::ABSENCE_REQUESTED => \user\UserUi::name(new \user\User([
				'firstName' => $eNotification['content']['firstName'],
				'lastName' => $eNotification['content']['lastName']
			]))
		};

	}

	public function getDetails(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::ABSENCE_REQUESTED => [AbsenceUi::getDuration($this->getAbsence($eNotification))]
		};

	}

	public function getGroupDetails(\notification\Notification $eNotificationHead): array {

		return $this->getDetails($eNotificationHead);

	}

	private function getAbsence(\notification\Notification $eNotification): Absence {

		return new Absence([
			'from' => $eNotification['content']['from'],
			'to' => $eNotification['content']['to'],
			'unit' => $eNotification['content']['unit'],
			'duration' => $eNotification['content']['duration']
		]);

	}

}
?>
