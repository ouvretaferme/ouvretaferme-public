<?php
namespace structure;

class Invite extends InviteElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => StructureElement::getSelection(),
			'memberRole' => RoleElement::getSelection(),
			'memberSection' => SectionElement::getSelection()
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function acceptCreate(): bool {

		$this->expects(['type']);

		// La fiche qu'une invitation de client rattache et la boutique où entre un producteur se pèsent
		// chez la commercialisation (`selling\Customer::acceptInvite()`, `shop\Shop::acceptInviteProducer()`)
		return match($this['type']) {
			Invite::CUSTOMER => TRUE,
			Invite::MEMBER, Invite::PRODUCER => $this->acceptAmapActive()
		};

	}

	/**
	 * Une AMAP fermée ne fait plus entrer personne dans son équipe, producteur ni administrateur, ni
	 * de producteur dans sa boutique, qu'elle garde pour servir ce qui court. L'invitation d'un
	 * adhérent reste, elle relie un compte à une fiche qui existe déjà.
	 */
	public function acceptAmapActive(): bool {

		$this->expects(['type', 'structure' => ['type', 'status']]);

		return (
			$this['type'] === Invite::CUSTOMER or
			$this['structure']->isAmap() === FALSE or
			$this['structure']->active()
		);

	}

	public function canDelete(): bool {

		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$this->canWrite() or
			(
				$eUserOnline->notEmpty() and
				$this['email'] !== NULL and
				$this['email'] === $eUserOnline['email']
			)
		);

	}

	/**
	 * Une invitation épuisée ne se change plus et ne se supprime plus : elle a servi autant de fois
	 * qu'elle le promettait, et reste lisible comme telle.
	 */
	public function acceptUpdate(): bool {

		$this->expects(['limit', 'used']);

		return $this->acceptLimit();

	}

	public function acceptDelete(): bool {
		return $this->acceptUpdate();
	}

	/**
	 * Le rôle d'une invitation d'une AMAP ne change pas : il est le sens du lien qui l'a créée
	 * (inviter un producteur, inviter un administrateur), et le changer en ferait une autre invitation.
	 */
	public function acceptUpdateRole(): bool {

		$this->expects(['type', 'status', 'structure' => ['type']]);

		return (
			$this['type'] === Invite::MEMBER and
			$this['status'] === Invite::PENDING and
			$this['structure']->isAmap() === FALSE and
			$this->acceptUpdate()
		);

	}

	public function isValid(): bool {

		if($this->empty()) {
			return FALSE;
		}

		$this->expects(['status', 'expiresAt']);

		return (
			$this['status'] === Invite::PENDING and
			$this->acceptLimit()
		);

	}

	public function acceptResend(): bool {

		return (
			$this['email'] !== NULL and
			$this['status'] === Invite::PENDING and
			$this->acceptExpired() and
			$this->acceptAmapActive()
		);

	}

	public function acceptExpired(): bool {

		return (
			$this['expiresAt'] === NULL or
			$this['expiresAt'] >= \Clock::date()
		);

	}

	public function acceptLimit(): bool {

		return (
			$this['limit'] === NULL or
			$this['used'] < $this['limit']
		);

	}

	public function acceptEmail(string $email): bool {

		return (
			$this['email'] === NULL or
			$this['email'] === mb_strtolower($email)
		);

	}

	public function isPending(): bool {

		if($this->empty()) {
			return FALSE;
		}

		return (
			$this['status'] === Invite::PENDING
		);

	}

	public function isAmapProducer(): bool {

		$this->expects(['type', 'structure' => ['type'], 'memberRole']);

		return (
			$this['type'] === Invite::MEMBER and
			$this['structure']->isAmap() and
			$this['memberRole']->notEmpty() and
			$this['memberRole']->isProducer()
		);

	}

	public function isAmapAdmin(): bool {

		$this->expects(['type', 'structure' => ['type'], 'memberRole']);

		return (
			$this['type'] === Invite::MEMBER and
			$this['structure']->isAmap() and
			$this['memberRole']->notEmpty() and
			$this['memberRole']['admin']
		);

	}

	/**
	 * L'invitation rattache une personne sans compte au compte qui l'accepte.
	 */
	public function designatesMember(): bool {

		$this->expects(['type', 'member']);

		return (
			$this['type'] === Invite::MEMBER and
			$this['member']->notEmpty()
		);

	}

	public function isAmapRoleImposed(): bool {

		$this->expects(['type', 'structure' => ['type'], 'memberRole']);

		return (
			$this['type'] === Invite::MEMBER and
			$this['structure']->isAmap() and
			$this['memberRole']->notEmpty()
		);

	}

	public function getLink(): string {

		$this->expects(['key']);

		return \Lime::getUrl().'/invitation?key='.$this['key'];

	}

	public static function properties(\PropertyList $l): void {

		$l->property('member')
			// Seule une personne sans compte de la structure qui invite se désigne : le POST est
			// contournable, et le schéma ne dit rien de l'adhésion qu'il nomme.
			->replaceCheck(function(Member $eMember): bool {

				$this->expects(['structure']);

				return (
					$eMember->empty() or
					MemberLib::getGhostById($this['structure'], $eMember['id'])->notEmpty()
				);

			})
			// La personne qui accepte la première emporte le passé : deux invitations qui courent pour
			// la même personne en laisseraient une sans objet.
			->addAfterCheck('duplicate', function(Member $eMember): bool {

				return (
					$eMember->empty() or
					InviteLib::getPendingByMember(new Member([
						'id' => $eMember['id'],
						'structure' => $this['structure']
					]))->empty()
				);

			});

		$l->property('email')
			->addAfterCast('required', function(?string &$email, \PropertyList $l): bool {

				// Une invitation qui désigne une personne sans compte lui transmet son passé : elle se
				// réserve à une adresse, un lien servirait à n'importe qui.
				$designates = (
					$l->isBuilt('member') and
					$this['member']->notEmpty()
				);

				if(
					$this['type'] !== Invite::CUSTOMER and
					$designates === FALSE
				) {
					return TRUE;
				}

				if($email !== NULL) {
					$email = mb_strtolower($email);
					return TRUE;
				} else {
					return FALSE;
				}

			})
			->addAfterCheck('duplicate', function(?string $email): bool {

				$this->expects(['structure', 'type']);

				return (
					$email === NULL or
					Invite::model()
						->whereStructure($this['structure'])
						->whereEmail($email)
						->whereStatus(Invite::PENDING)
						// Une fiche n'a qu'une invitation qui court, et la nouvelle remplace celle-là
						->whereId('!=', fn() => $this['eInvitePending'], if: ($this['type'] === Invite::CUSTOMER and $this['eInvitePending']->notEmpty()))
						->exists() === FALSE
				);

			})
			->addAfterCheck('duplicateCustomer', function(?string $email): bool {

				$this->expects(['structure', 'type']);

				if($this['type'] !== Invite::CUSTOMER) {
					return TRUE;
				}

				$eUser = \user\UserLib::getByEmail($email);

				if($eUser->empty()) {
					return TRUE;
				}

				return InviteLib::existsCustomerUser($this['structure'], $eUser) === FALSE;

			})
			->addAfterCheck('duplicateMember', function(?string $email): bool {

				$this->expects(['structure', 'type']);

				if($this['type'] !== Invite::MEMBER or $email === NULL) {
					return TRUE;
				}

				$eUser = \user\UserLib::getByEmail($email);

				if($eUser->empty()) {
					return TRUE;
				}

				// Une adhésion gardée sans rôle n'est pas un doublon : l'invitation est le geste qui redonne l'accès, et
				// l'acceptation rend cette même ligne (`InviteLib::acceptMember()`).
				return Member::model()
					->whereUser($eUser)
					->whereStructure($this['structure'])
					->whereRole('!=', NULL)
					->exists() === FALSE;

			})
			// Le compte qui accepte reprend la fiche d'équipe, les périodes et le temps de travail de la
			// personne sans compte, qui sont uniques par personne : il ne doit pas en avoir déjà.
			// `MemberLib::transferGhost()` le relit sous verrou à l'acceptation.
			->addAfterCheck('duplicateTeammate', function(?string $email, \PropertyList $l): bool {

				if(
					$email === NULL or
					$l->isBuilt('member') === FALSE or
					$this['member']->empty()
				) {
					return TRUE;
				}

				$eUser = \user\UserLib::getByEmail($email);

				return (
					$eUser->empty() or
					\hr\TeammateLib::hasHistory($this['structure'], $eUser) === FALSE
				);

			})
			->addAfterCheck('admin', function(?string $email, \PropertyList $l): bool {

				if($this['type'] !== Invite::MEMBER) {
					return TRUE;
				}

				$l->skipIfInvalid('memberRole');

				$eRole = $this['cRole'][$this['memberRole']['id']];

				return (
					$eRole['admin'] === FALSE or
					$email !== NULL
				);

			});

		$l->property('memberRole')
			->replaceCheck(function(Role $eRole): bool {

				$this->expects(['cRole']);

				return (
					$eRole->notEmpty() and
					$this['cRole']->offsetExists($eRole['id'])
				);

			})
			// La règle de `email.admin`, pour un rôle qui change sans son adresse : une invitation
			// sans adresse vaut pour qui ouvre le lien, elle ne fait donc pas entrer d'administrateur.
			// Quand l'adresse se construit avec le rôle, c'est `email.admin` qui juge.
			->addAfterCheck('admin', function(Role $eRole, \PropertyList $l): bool {

				if($l->hasProperty('email')) {
					return TRUE;
				}

				$this->expects(['email']);

				return (
					$this['cRole'][$eRole['id']]['admin'] === FALSE or
					$this['email'] !== NULL
				);

			});

		$l->property('memberSection')
			// Même garde que memberRole juste au-dessus, et pour la même raison : le POST est
			// contournable, et rien dans le schéma ne dit qu'un sous-groupe désigné appartient au
			// groupe qui invite. Mesuré ouvert le 2026-09-06, un sous-groupe d'un autre groupe
			// passait. Facultatif à la différence du rôle : on peut inviter sans ranger.
			->replaceCheck(function(Section $eSection): bool {

				$this->expects(['cSection']);

				return (
					$eSection->empty() or
					$this['cSection']->offsetExists($eSection['id'])
				);

			})
			// Un rôle borné qui entre sans sous-groupe ne voit rien : la portée est faite de
			// sous-groupes, et n'en avoir aucun ne rend pas tout, ça ne rend rien. L'invitation
			// pose donc le rangement, ou elle crée un compte aveugle. La règle se déduit de la
			// portée du rôle et ne teste jamais le type du groupe.
			->addAfterCheck('required', function(Section $eSection, \PropertyList $l): bool {

				$l->skipIfInvalid('memberRole');

				// ⚠️ La portée se lit dans `cRole` et jamais sur l'élément bâti : celui-ci sort
				// d'un cast d'id et ne porte que cet id. `email.admin` juste au-dessus fait le
				// même détour, et pour la même raison.
				$eRole = $this['cRole'][$this['memberRole']['id']];

				return (
					$eRole['sections'] !== Role::OWN or
					$eSection->notEmpty()
				);

			})
			// L'encadrement voit tout le groupe : un sous-groupe ne le bornerait pas, il le
			// rangerait parmi ceux qu'il encadre.
			->addAfterCheck('staff', function(Section $eSection, \PropertyList $l): bool {

				$l->skipIfInvalid('memberRole');

				$eRole = $this['cRole'][$this['memberRole']['id']];

				return (
					$eRole->isStaff() === FALSE or
					$eSection->empty()
				);

			});

		$l->property('expiresAt')
			->addAfterCheck('past', function(?string $expiresAt): bool {
				return (
					$expiresAt === NULL or
					$expiresAt >= \Clock::date()
				);
			});

		$l->property('limit')
			->addAfterCast('email', function(?int &$limit, \PropertyList $l) {

				$l->skipIfInvalid('memberRole');
				$l->skipIfInvalid('email');

				if($this['email'] !== NULL) {
					$limit = 1;
				}

			});

	}

}
?>