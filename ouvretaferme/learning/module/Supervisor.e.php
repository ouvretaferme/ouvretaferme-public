<?php
namespace learning;

/**
 * Un encadrant, saisi une fois pour son réseau et rattaché ensuite à chacune de ses formations par
 * une ligne de `Staff`. `community` vide désigne l'équipe Ouvretaferme, comme sur `Course`.
 */
class Supervisor extends SupervisorElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'community' => ['name', 'type', 'communityType', 'legalEmail'],
		];

	}

	public function isTeam(): bool {

		$this->expects(['community']);

		return $this['community']->empty();

	}

	/**
	 * Le droit de `Course::canWrite()` : les encadrants d'un réseau se lisent et s'écrivent par toute
	 * son équipe, ceux de l'équipe Ouvretaferme par ses administrateurs. `canWrite()` retombe ici.
	 */
	public function canRead(): bool {

		if($this->isTeam()) {
			return \user\ConnectionLib::getOnline()->isAdmin();
		}

		return $this['community']->getMember()->isStaff();

	}

	/**
	 * L'état de `Course::acceptWrite()` : un encadrant ne s'écrit que là où une formation s'écrit,
	 * chez l'équipe, un groupe de formation ou un réseau ouverts aux formations. Le type du groupe
	 * n'est pas redit ici : `acceptLearning()` le pèse déjà, les formations ne s'allumant que sur
	 * ces deux types (`structure\CommunityFeatureAiLib::FEATURES`).
	 */
	public function acceptWrite(): bool {

		if($this->isTeam()) {
			return LearningSetting::$active;
		}

		return $this['community']->acceptLearning();

	}

	/**
	 * Un contact donne son adresse sur la fiche et dans les e-mails : sans adresse, il n'aurait rien
	 * à y donner. Les deux étapes gardent chacune un des deux contacts qu'une personne peut être.
	 *
	 * `email.contact` garde le contact d'une formation, que porte la liaison et non la personne :
	 * `coursesContact` compte les formations dont elle est le contact, posé par
	 * `SupervisorLib::fillCourses()` et à zéro par `SupervisorLib::getCreateElement()`.
	 *
	 * `contact.noEmail` garde le contact du réseau. Elle lit `email`, construit juste avant (les
	 * listes de `SupervisorLib` le rangent devant), et rien quand il a été refusé : une adresse
	 * vidée et le drapeau retiré dans le même envoi passent donc ensemble.
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('email')
			->addAfterCheck('contact', function(?string $email): bool {

				$this->expects(['coursesContact']);

				return (
					$email !== NULL or
					$this['coursesContact'] === 0
				);

			});

		$l->property('contact')
			->addAfterCheck('noEmail', function(bool $contact, \PropertyList $l): bool {

				$l->skipIfInvalid('email');

				return (
					$contact === FALSE or
					$this['email'] !== NULL
				);

			});

	}

}
?>
