<?php
namespace structure;

/**
 * Le rôle d'une invitation qui attend, changé depuis la roue de la liste des invitations.
 *
 * Il se change sur une invitation de membre en attente, hors AMAP, où le rôle est le sens du lien
 * qui l'a créée. Il se choisit parmi les rôles de la structure, et un rôle d'administrateur demande
 * une invitation réservée à une adresse, comme à la création (`email.admin`), l'adresse ne se
 * construisant pas ici.
 *
 * Décor : le groupe partageant G4 et ses rôles. Le test bâtit les invitations en mémoire et
 * n'écrit rien.
 *
 * Lancement : dev/test package=structure file=InviteUpdateRoleAi
 */
class InviteUpdateRoleAiTest extends CommunityFixtureAiTest {

	public function testRoleChangesOnlyOnAPendingMemberInvite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$this->assertTrue($this->getInvite($eStructure)->acceptUpdateRole(), 'une invitation de membre en attente change de rôle');
		$this->assertFalse($this->getInvite($eStructure, ['status' => Invite::INVALID])->acceptUpdateRole(), 'une invitation qui n\'attend plus ne change plus');
		$this->assertFalse($this->getInvite($eStructure, ['type' => Invite::CUSTOMER])->acceptUpdateRole(), 'une invitation de client n\'a pas de rôle');
		$this->assertFalse($this->getInvite($eStructure, ['limit' => 1, 'used' => 1])->acceptUpdateRole(), 'une invitation épuisée ne change plus');

		$eStructureAmap = new Structure(['id' => $eStructure['id'], 'type' => Structure::AMAP]);

		$this->assertFalse($this->getInvite($eStructureAmap)->acceptUpdateRole(), 'le rôle d\'une invitation d\'AMAP est le sens de son lien');

	}

	public function testAdminRoleNeedsAnEmail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$cRole = RoleLib::getByStructure($eStructure);

		$eRoleAdmin = $cRole->find(fn(Role $eRole) => $eRole['admin'])->first();
		$eRoleMember = RoleLib::getOne($eStructure, 'member');

		if($eRoleAdmin === NULL or $eRoleMember->empty()) {
			$this->assertFail('le groupe « '.self::NAME_COMMUNITY_SHARING.' » ne porte pas de rôle administrateur et de rôle member : le décor ne prouve rien');
			return;
		}

		$this->assertFalse($this->build($this->getInvite($eStructure), $eRoleMember)->ko(), 'un lien sans adresse passe à un rôle sans administration');
		$this->assertTrue($this->build($this->getInvite($eStructure), $eRoleAdmin)->has('Invite::memberRole.admin'), 'un lien sans adresse ne fait pas entrer un administrateur');
		$this->assertFalse($this->build($this->getInvite($eStructure, ['email' => 'zz@example.org']), $eRoleAdmin)->ko(), 'une invitation réservée à une adresse passe administrateur');

		$eRoleOther = RoleLib::getOne($this->getStructure($this->communitySilent), 'member');

		$this->assertTrue($this->build($this->getInvite($eStructure), $eRoleOther)->has('Invite::memberRole.check'), 'un rôle d\'une autre structure est refusé');

	}

	private function getInvite(Structure $eStructure, array $values = []): Invite {

		return new Invite($values + [
			'id' => 1,
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'status' => Invite::PENDING,
			'email' => NULL,
			'limit' => NULL,
			'used' => 0,
			'cRole' => RoleLib::getByStructure($eStructure),
			'cSection' => new \Collection(),
			'memberRole' => new Role(),
			'memberSection' => new Section()
		]);

	}

	private function build(Invite $eInvite, Role $eRole): \FailWatch {

		$fw = new \FailWatch();

		$eInvite->build(['memberRole', 'memberSection'], [
			'memberRole' => $eRole['id']
		], new \PropertyList('update'));

		return $fw;

	}

}
?>
