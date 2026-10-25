<?php
namespace learning;

/**
 * Ce que le package `learning` apporte au centre de notifications côté affichage : les mots et
 * l'icône de ce que les stagiaires d'un groupe font de ses formations.
 *
 * Pendant du `NotificationLib` du même package. Le contrat de la paire est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 * Nom fixe, pour la même raison que le Lib.
 *
 * Les phrases sont celles de la planche L3g-2, validée par V-Learning le 22/09 : le verbe est celui
 * du produit (on « commence » une formation, c'est le bouton du stagiaire), et « terminé » est le
 * mot de la fin, distinct de « Suivie en entier », qui compte aussi les leçons facultatives. Le mot
 * qui nomme le groupe suit son type (« du groupe », « du réseau »), par `StructureUi::getText()`.
 */
class NotificationUi {

	public function getTypeName(string $type): string {

		return match($type) {
			NotificationLib::COMMENT => s("Nouveau commentaire d'un stagiaire"),
			NotificationLib::ENROLLMENT => s("Formation commencée"),
			NotificationLib::COMPLETED => s("Formation terminée"),
			NotificationLib::REVIEW => s("Nouvel avis sur une formation")
		};

	}

	public function getTypeDescription(string $type, \structure\Structure $eStructure): string {

		$of = \structure\StructureUi::getText($eStructure, 'of');

		return match($type) {
			NotificationLib::COMMENT => s("Un stagiaire écrit un commentaire sous une leçon d'une formation {of}.", ['of' => $of]),
			NotificationLib::ENROLLMENT => s("Un stagiaire commence une formation {of}, ou demande à s'y inscrire quand l'inscription attend votre confirmation.", ['of' => $of]),
			NotificationLib::COMPLETED => s("Un stagiaire a suivi toutes les leçons obligatoires d'une formation {of}.", ['of' => $of]),
			NotificationLib::REVIEW => s("Une personne donne son avis sur une formation {of}.", ['of' => $of])
		};

	}

	public function getLock(string $right): string {

		return match($right) {
			'fqn' => s("Réservé aux formateurs")
		};

	}

	/** @return array [classes, svg] */
	public function getIcon(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::COMMENT => ['util-icon-settings', \Asset::icon('chat-left-text')],
			NotificationLib::ENROLLMENT => ['util-icon-settings', \Asset::icon(self::isRequest($eNotification) ? 'hourglass-split' : 'mortarboard')],
			NotificationLib::COMPLETED => ['util-icon-settings', \Asset::icon('check-lg')],
			NotificationLib::REVIEW => ['util-icon-settings', \Asset::icon('star')]
		};

	}

	public function getText(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			NotificationLib::COMMENT => s("{user} a commenté la leçon {lesson}", [
				'user' => $this->getActor($eNotification),
				'lesson' => encode($eNotification['content']['lesson'])
			]),
			NotificationLib::ENROLLMENT => self::isRequest($eNotification)
				? s("La demande de {user} pour la formation {course} attend votre confirmation", [
					'user' => $this->getActor($eNotification),
					'course' => encode($eNotification['content']['course'])
				])
				: s("{user} a commencé la formation {course}", [
					'user' => $this->getActor($eNotification),
					'course' => encode($eNotification['content']['course'])
				]),
			NotificationLib::COMPLETED => s("{user} a terminé la formation {course}", [
				'user' => $this->getActor($eNotification),
				'course' => encode($eNotification['content']['course'])
			]),
			NotificationLib::REVIEW => p("{user} a donné {value} étoile à la formation {course}", "{user} a donné {value} étoiles à la formation {course}", $eNotification['content']['rating'], [
				'user' => $this->getActor($eNotification),
				'course' => encode($eNotification['content']['course'])
			])
		};

	}

	/**
	 * Une inscription en demande, que `content.requested` désigne (`NotificationLib::ENROLLMENT`).
	 * Une notification écrite avant les demandes est en base sans cette clé, et c'est une inscription.
	 */
	public static function isRequest(\notification\Notification $eNotification): bool {
		return ($eNotification['content']['requested'] ?? FALSE);
	}

	/**
	 * Seules les inscriptions commencées se groupent (`group` = formation) : la tête compte les
	 * stagiaires du jour. Une demande, un commentaire, une fin et un avis ont une tête seule, dont le
	 * texte est celui de la ligne.
	 */
	public function getGroupText(\notification\Notification $eNotificationHead): string {

		if(self::isRequest($eNotificationHead)) {
			return $this->getText($eNotificationHead);
		}

		return match($eNotificationHead['type']) {
			NotificationLib::ENROLLMENT => $this->getEnrollmentGroupText($eNotificationHead),
			NotificationLib::COMMENT, NotificationLib::COMPLETED, NotificationLib::REVIEW => $this->getText($eNotificationHead)
		};

	}

	private function getEnrollmentGroupText(\notification\Notification $eNotificationHead): string {

		$number = $eNotificationHead['cNotificationGroup']->count();
		$values = ['course' => encode($eNotificationHead['content']['course'])];

		// La part reçue avant les nouvelles (`earlier`, cf. `\notification\NotificationLib::group()`) ne
		// se lit pas comme une seconde vague.
		if($eNotificationHead['earlier']) {
			return p("{value} stagiaire a commencé plus tôt la formation {course}", "{value} stagiaires ont commencé plus tôt la formation {course}", $number, $values);
		}

		return p("{value} stagiaire a commencé la formation {course}", "{value} stagiaires ont commencé la formation {course}", $number, $values);

	}

	/**
	 * L'avis porte la signature qu'il affiche partout, prénom et initiale, jamais le nom entier.
	 */
	public function getActor(\notification\Notification $eNotification): string {

		if($eNotification['type'] === NotificationLib::REVIEW) {
			return encode($eNotification['content']['author']);
		}

		return \user\UserUi::name(new \user\User([
			'firstName' => $eNotification['content']['firstName'],
			'lastName' => $eNotification['content']['lastName']
		]));

	}

	/**
	 * La ligne grise de la planche : la formation sous un commentaire, la promotion du stagiaire sous
	 * un commencement ou une fin, quand il en a une seule (`NotificationLib::getSectionName()`).
	 */
	public function getDetails(\notification\Notification $eNotification): array {

		return match($eNotification['type']) {
			NotificationLib::COMMENT => [encode($eNotification['content']['course'])],
			NotificationLib::ENROLLMENT, NotificationLib::COMPLETED => ($eNotification['content']['section'] === NULL)
				? []
				: [encode($eNotification['content']['section'])],
			NotificationLib::REVIEW => []
		};

	}

	/**
	 * La tête d'un groupe d'inscriptions n'a pas de ligne grise, la formation étant dans sa phrase et
	 * les stagiaires venant de promotions différentes. Une demande est seule, et garde la sienne.
	 */
	public function getGroupDetails(\notification\Notification $eNotificationHead): array {

		if(self::isRequest($eNotificationHead)) {
			return $this->getDetails($eNotificationHead);
		}

		return match($eNotificationHead['type']) {
			NotificationLib::ENROLLMENT => [],
			NotificationLib::COMMENT, NotificationLib::COMPLETED, NotificationLib::REVIEW => $this->getDetails($eNotificationHead)
		};

	}

}
?>
