<?php
namespace learning;

class Enrollment extends EnrollmentElement {

	public static function getSelection(): array {

		return self::getSelectionWithoutCourse() + [
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	/**
	 * Sans la formation, que l'appelant qui la tient déjà accroche à chaque ligne.
	 */
	public static function getSelectionWithoutCourse(): array {

		return parent::getSelection() + [
			'user' => ['firstName'],
		];

	}

	public function canRead(): bool {

		$this->expects(['user']);

		return $this['user']->isOnline();

	}

	/**
	 * La personne retire sa propre demande depuis la fiche ; le réseau l'annule par `canManage()`,
	 * depuis le suivi, et l'e-mail part alors à la personne.
	 */
	public function canCancel(): bool {

		$this->expects(['user']);

		return $this['user']->isOnline();

	}

	/**
	 * Confirmer ou annuler une demande : ce que peut l'équipe qui écrit la formation, comme pour une
	 * date (`DateEnrollment::canManage()`).
	 */
	public function canManage(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	/**
	 * Seule une demande se confirme ou s'annule : une inscription confirmée ne revient pas en arrière,
	 * et une demande annulée ne l'est pas deux fois.
	 */
	public function acceptConfirm(): bool {
		return $this->isRequested();
	}

	public function acceptCancel(): bool {
		return $this->isRequested();
	}

	public function isRequested(): bool {

		$this->expects(['status']);

		return ($this['status'] === Enrollment::REQUESTED);

	}

	/**
	 * La personne doit-elle dire si elle demande une prise en charge ? Seulement quand le réseau ne
	 * confirme que ces demandes-là. Le réglage est posé sur la formation par la page
	 * (`EnrollmentLib::getOnlineConfirm()`) : aucune requête ici.
	 */
	public function hasFundingQuestion(): bool {

		$this->expects(['course' => ['onlineConfirm']]);

		return ($this['course']['onlineConfirm'] === \structure\Community::FUNDING);

	}

	/**
	 * Tout compte s'inscrit à une formation qui s'offre à l'inscription (`Course::acceptPublic()`),
	 * sans la lire encore : c'est son inscription qui la lui ouvre.
	 */
	public function canCreate(): bool {

		$this->expects(['user', 'course']);

		return (
			$this['user']->isOnline() and
			(
				$this['course']->canRead() or
				$this['course']->acceptPublic()
			)
		);

	}

	/**
	 * Une formation ne se commence plus une fois l'échéance de la promotion du compte passée. C'est un
	 * état de la fenêtre du compte inscrit, pas un droit : l'API, qui n'appelle que les `accept*`,
	 * le lit aussi. Écrit en `acceptCreate()` et non en `acceptWrite()`, que la suppression d'une
	 * inscription emprunte et qu'un compte figé doit garder.
	 *
	 * Une formation datée ne se commence qu'une fois l'inscription à l'une de ses dates confirmée
	 * par le réseau, ou immédiate si le réseau ne confirme pas : « S'inscrire » sur la date est le
	 * seul geste de la fiche avant, « Commencer » le seul après. Elle peut aussi n'avoir aucune
	 * leçon (son contenu est la journée), et une inscription à une formation vide ne mènerait nulle
	 * part. Un tutoriel de Ouvretaferme se lit sans jamais se commencer. La règle est ici et non dans
	 * l'affichage, pour qu'un POST forgé la lise aussi, et en cinq questions, pour que la page qui
	 * refuse dise laquelle a échoué.
	 */
	public function acceptCreate(): bool {

		$this->expects(['user', 'course']);

		return (
			$this->acceptCourse() and
			$this->acceptDated() and
			$this->acceptLessons() and
			$this->acceptWindow() and
			$this->acceptPause()
		);

	}

	/**
	 * Une formation en ligne dont les inscriptions sont en pause ne se commence plus ; celle d'une
	 * formation datée suit une inscription à une date déjà reçue, que la pause laisse continuer.
	 * Relu sous verrou à l'écriture (`EnrollmentLib::create()`).
	 */
	public function acceptPause(): bool {

		$this->expects(['course' => ['type', 'community', 'enrollmentPaused']]);

		return (
			$this['course']->isDated() or
			$this['course']->isEnrollmentPaused() === FALSE
		);

	}

	/**
	 * Un tutoriel s'ouvre à tous, sans compte (`Course::acceptTutorial()`) : il n'a ni progression à
	 * tenir ni promotion à rejoindre, et la leçon n'y propose que la suivante.
	 */
	public function acceptCourse(): bool {

		$this->expects(['course']);

		return ($this['course']->acceptTutorial() === FALSE);

	}

	public function acceptDated(): bool {

		$this->expects(['user', 'course']);

		return (
			$this['course']->isDated() === FALSE or
			DateEnrollmentLib::hasConfirmed($this['user'], $this['course'])
		);

	}

	public function acceptLessons(): bool {

		$this->expects(['course']);

		return (
			$this['course']->isDated() === FALSE or
			ChapterLib::hasLessons($this['course'])
		);

	}

	/**
	 * L'échéance est celle d'une promotion : une formation de réseau n'en a pas, elle se commence
	 * tant qu'elle est publiée.
	 */
	public function acceptWindow(): bool {

		$this->expects(['user', 'course']);

		if($this['course']->isNetwork()) {
			return TRUE;
		}

		$eShare = ShareLib::getWindowByUserAndCourse($this['user'], $this['course']);

		return (
			$eShare->empty() or
			$eShare->acceptProgress()
		);

	}

	public static function properties(\PropertyList $l): void {

		// « Aucune, à titre personnel » poste une chaîne vide ; rien de coché ne poste rien, et le
		// cast ferait des deux le même élément vide. La valeur brute n'est pas typée : un POST
		// forgé peut porter un tableau.
		$l->property('structure')
			->addBeforeCast('chosen', fn(mixed $structure): bool => $structure !== NULL)
			->addAfterCheck('owned', function(\structure\Structure $eStructure): bool {

				if($eStructure->empty()) {
					return TRUE;
				}

				return EnrollmentLib::getStructures()->offsetExists($eStructure['id']);

			});

		// Aucun choix n'est coché d'avance, pour que la personne réponde : rien de posté est un refus,
		// et non un « non ». La chaîne vide en est un aussi, que la conversion rendrait à NULL sans
		// rien refuser. La propriété n'est construite que là où la question se pose
		// (`EnrollmentLib::getPropertiesCreate()`).
		$l->property('fundingRequested')
			->addBeforeCast('chosen', fn(mixed $fundingRequested): bool => ($fundingRequested !== NULL and $fundingRequested !== ''));

	}

}
?>
