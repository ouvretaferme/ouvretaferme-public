<?php
namespace structure;

/**
 * Un rôle que le code nomme par son `fqn` ne se modifie ni ne se supprime (lot AMAP C5).
 *
 * `Role::acceptUpdate()` est le seul verrou que lisent à la fois `ModulePage` (`role:doUpdate`,
 * `role:doDelete`) et `ModuleApi` (`PUT` et `DELETE role/{id}`), ce dernier n'appelant que les
 * `accept*`. Aucun écran de groupe ne modifie un rôle : ce qui est fermé ici, c'est le POST forgé et
 * le jeton d'API, qui pouvaient renommer ou supprimer `member`, et avec lui ce que le code relit
 * par `RoleLib::getOne($eStructure, 'member')`.
 *
 * Le pendant AMAP (`producer`) est dans `commercialisation/amap/test/Bureau.t.php` ; le chemin de
 * l'API jusqu'au `412 accept_update` est déjà prouvé par `RoleApiAiTest::testAdminRoleIsNotWritable()`.
 *
 * Décor : le rôle `member` du groupe partageant G4, que `createForStructure()` pose sur tout groupe
 * de producteurs. Le test ne fait que lire, sans transaction.
 *
 * Lancement : dev/test package=structure file=RoleFqnAi
 */
class RoleFqnAiTest extends CommunityFixtureAiTest {

	public function testGroupMemberRoleIsNotWritable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eRole = RoleLib::getOne($this->getStructure($this->communitySharing), 'member');

		if($eRole->empty()) {
			$this->assertFail('le groupe « '.self::NAME_COMMUNITY_SHARING.' » ne porte pas de rôle member : le décor ne prouve rien');
			return;
		}

		$this->assertFalse($eRole['admin'], 'le rôle member n\'est pas administrateur : le refus ne peut venir que du fqn');
		$this->assertEquals(Role::PUBLIC, $eRole['visibility'], 'ni privé');

		$this->assertFalse($eRole->acceptUpdate(), 'le rôle member d\'un groupe ne se modifie pas');
		$this->assertFalse($eRole->acceptDelete(), 'ni ne se supprime');

		$eRoleWithoutFqn = new Role($eRole->getArrayCopy());
		$eRoleWithoutFqn['fqn'] = NULL;

		$this->assertTrue($eRoleWithoutFqn->acceptUpdate(), 'le même rôle sans fqn se modifie : le refus vient bien du fqn');
		$this->assertTrue($eRoleWithoutFqn->acceptDelete(), 'et se supprime');

	}

}
?>
