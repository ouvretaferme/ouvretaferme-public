<?php
namespace structure;

/**
 * Le panneau d'invitation d'un groupe quand le lien porte `&memberRole=` (lot AMAP C5).
 *
 * Sur une AMAP, le lien impose le rôle : titre qui le nomme, rôle en texte, champ caché
 * (`commercialisation/amap/test/Bureau.t.php`). Sur un groupe, le même paramètre ne fait que
 * présélectionner : la ligne d'une promotion, le bandeau d'accueil et l'étape de démarrage choisissent
 * un rôle, et la personne qui invite garde le choix. `Invite::isAmapRoleImposed()` décide, et la page
 * comme le panneau l'appellent : ce test la vérifie sur un groupe du décor, puis le champ que le
 * panneau poste.
 *
 * Décor : le groupe partageant G4 et son rôle `member`. Le test ne fait que lire, sans transaction.
 *
 * Lancement : dev/test package=structure file=InviteRoleAi
 */
class InviteRoleAiTest extends CommunityFixtureAiTest {

	public function testGroupLinkPreselectsWithoutImposing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$cRole = RoleLib::getByStructure($eStructure);
		$eRole = RoleLib::getOne($eStructure, 'member');

		if($eRole->empty()) {
			$this->assertFail('le groupe « '.self::NAME_COMMUNITY_SHARING.' » ne porte pas de rôle member : le décor ne prouve rien');
			return;
		}

		$eInvite = new Invite([
			'structure' => $eStructure,
			'member' => new Member(),
			'eMemberRevoked' => new Member(),
			'type' => Invite::MEMBER,
			'cRole' => $cRole,
			'memberRole' => $eRole,
			'cSection' => new \Collection(),
			'memberSection' => new Section()
		]);

		$this->assertFalse($eInvite->isAmapRoleImposed(), 'sur un groupe, un rôle porté par le lien n\'est pas imposé');

		try {

			$panel = new InviteUi()->createMember($eInvite);

		} catch(\Throwable $e) {
			$this->assertFail('le panneau d\'invitation d\'un groupe ne se rend pas avec le rôle member présélectionné : '.$e->getMessage());
			return;
		}

		$html = $panel->body;

		$this->assertFalse(str_contains($html, 'name="memberRole" type="hidden"'), 'le rôle n\'est pas figé en champ caché');
		$this->assertEquals($cRole->count(), substr_count($html, 'name="memberRole" type="radio"'), 'chaque rôle du groupe reste proposé');
		$this->assertTrue((bool)preg_match('/<input[^>]*checked="checked"[^>]*name="memberRole" type="radio" value="'.$eRole['id'].'"/', $html), 'le rôle du lien est présélectionné');

	}

}
?>
