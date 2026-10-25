<?php
namespace hr;

class Teammate extends TeammateElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function acceptCreate(): bool {

		$this->expects(['structure']);
		return $this['structure']->acceptProduction();

	}

	/**
	 * Une personne sort du planning tant qu'une de ses périodes n'est pas finie.
	 */
	public function acceptLeave(): bool {

		$this->expects(['cPresence']);

		return $this['cPresence']->find(fn(Presence $ePresence) => $ePresence->isOpenOn(\Clock::date()))->notEmpty();

	}

	public function assertLeave(): bool {
		return ($this->acceptLeave() and $this->canWrite());
	}

	public static function getSelection(): array {

		return parent::getSelection() + [
			// `name` en premier : un menu déroulant prend le deuxième champ de l'élément pour libellé, comme pour
			// `Member::getSelection()`.
			'user' => [
				'name' => new \Sql('IF(firstName IS NULL, lastName, CONCAT(firstName, " ", lastName))'),
				'vignette',
				'firstName', 'lastName',
				'status', 'visibility'
			],
		];

	}

	public static function properties(\PropertyList $l): void {

		$l->property('user')
			->addAfterCheck('member', function(\user\User $eUser): bool {

				$this->expects(['structure']);

				// Tout statut : une personne sans compte est rattachée sortie (`MemberLib::createGhostUser()`),
				// et un ancien membre peut revenir travailler sans avoir retrouvé d'accès.
				return \structure\MemberLib::isMember($eUser, $this['structure'], NULL);

			})
			->addAfterCheck('duplicate', function(\user\User $eUser): bool {

				$this->expects(['structure']);

				return TeammateLib::isTeammate($this['structure'], $eUser) === FALSE;

			});

	}

}
?>
