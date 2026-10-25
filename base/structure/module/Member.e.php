<?php
namespace structure;

class Member extends MemberElement {

	public static function getSelection(): array {

		return [
			'user' => [
				'name' => new \Sql('IF(firstName IS NULL, lastName, CONCAT(firstName, " ", lastName))'),
				'email', 'firstName', 'lastName', 'visibility', 'vignette', 'status', 'createdAt'
			],
			'role' => fn($e) => RoleLib::ask($e['role'], $e['structure']),
		] + parent::getSelection();

	}

	public function isAdmin(): bool {

		return (
			$this->notEmpty() and
			$this['role']['admin']
		);

	}

	public function isStaff(): bool {

		return (
			$this->notEmpty() and
			$this['role']->isStaff()
		);

	}

	public function isProducer(): bool {

		return (
			$this->notEmpty() and
			$this['role']->isProducer()
		);

	}

	public function isRole(string $property, mixed $expected = TRUE): bool {

		return (
			$this->notEmpty() and (
				$this['role']['admin'] or
				$this['role'][$property] === $expected
			)
		);

	}

	/**
	 * Une personne de l'équipe à qui l'on a retiré tout rôle : son adhésion reste, sortie et sans rôle, tant qu'elle
	 * est dans l'équipe (`MemberLib::deleteInternal()`), et l'accès se redonne par une invitation.
	 */
	public function isRevoked(): bool {

		$this->expects(['status', 'role']);

		return (
			$this['status'] === self::OUT and
			$this['role']->empty()
		);

	}

	public function acceptInvite(): bool {

		return (
			$this->isRevoked() === FALSE and
			$this['role']->isGhost()
		);

	}

	/**
	 * Le rôle se retire d'une adhésion qui en donne un, l'adhésion sortie ne portant plus d'accès à retirer.
	 */
	public function acceptDelete(): bool {

		$this->expects(['status']);

		return ($this['status'] === self::IN);

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function canSelf(): bool {

		$this->expects(['structure']);
		return $this['structure']->getMember()->is($this);

	}

	public function canUpdateRole(): bool {

		$this->expects(['user', 'structure', 'role']);
		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$this->canWrite() and
			$this['role']->isGhost() === FALSE and
			$eUserOnline->notEmpty() and
			($eUserOnline['id'] !== $this['user']['id'])
		);

	}

	/**
	 * Le rôle d'une adhésion sortie ne se change pas : il se redonne par une invitation, qui la fait entrer.
	 */
	public function acceptUpdateRole(): bool {

		$this->expects(['status', 'structure' => ['type']]);

		return (
			$this['structure']->isAmap() === FALSE and
			$this['status'] === self::IN
		);

	}

	public function assertUpdateRole(): bool {
		return ($this->acceptUpdateRole() and $this->canUpdateRole());
	}

	public static function properties(\PropertyList $l): void {

		$l->property('role')
			->addAfterCheck('known', function(Role $eRole): bool {

				$this->expects(['cRole']);

				return $this['cRole']->offsetExists($eRole['id']);

			})
			// Le rangement ne vit pas sur Member mais dans Section.users : c'est le seul endroit
			// où le rôle peut le refuser avant que ModulePage ne l'écrive.
			->addAfterCheck('staff', function(Role $eRole, \PropertyList $l): bool {

				return (
					$this['cRole'][$eRole['id']]->isStaff() === FALSE or
					($l->input['sections'] ?? []) === []
				);

			});

		$l->property('notifications')
			->addAfterCheck('type', function(array $notifications): bool {

				// Les réglages de notification, type par type, que `notification\NotificationLib`
				// compose et que le schéma json accepte tels quels : le module ne connaît pas ces
				// types, il refuse seulement ce qui n'associe pas une chaîne à une chaîne.
				foreach($notifications as $type => $value) {

					if(is_string($type) === FALSE or is_string($value) === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			});

		$l->property('status')
			->addAfterCheck('can', function(string $status): bool {

				$this->expects(['role']);

				return $this['role']->isGhost();

			});

		// L'ordre du planning et les interventions suivies sur l'accueil sont deux listes d'ids d'actions de
		// la même structure, sans doublon : une seule règle, posée sur les deux.
		$actions = function(?array $order): bool {

			if($order === NULL) {
				return TRUE;
			}

			$this->expects(['cAction']);

			foreach($order as $action) {

				if(is_int($action) === FALSE or $this['cAction']->offsetExists($action) === FALSE) {
					return FALSE;
				}

			}

			return count(array_unique($order)) === count($order);

		};

		$l->property('viewPlanningOrder')
			->addAfterCheck('actions', $actions);

		$l->property('viewDashboardActions')
			->addAfterCheck('actions', $actions);

		$l->property('viewPlanningSalesDays')
			->addAfterCheck('days', fn(int $days): bool => in_array($days, \planning\PlanningSetting::SALES_DAYS, TRUE));

	}

}
?>