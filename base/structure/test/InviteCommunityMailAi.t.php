<?php
namespace structure;

/**
 * L'e-mail d'invitation à un groupe dit qui invite, le rôle reçu et la présentation du groupe (lot MR5
 * du chantier e-learning « mise en route »). Il vaut pour tout groupe, réseau, groupe de formation et
 * communauté de producteurs, et ne parle plus du logiciel d'une ferme.
 *
 * Une ferme et le bureau d'une AMAP gardent le message d'avant, et le producteur d'une AMAP le sien :
 * sans ces témoins, une recherche qui ne trouve plus l'ancien texte ne prouverait pas qu'il a été
 * remplacé là où il devait l'être.
 *
 * Les mots attendus sont figés en clair : calculés par le même `getText()` que le code, une clé mal
 * écrite rendrait la même chaîne vide des deux côtés et le test resterait vert. Les invitations sont
 * construites en mémoire et jamais écrites, sur les structures et les rôles du décor (`FixtureAiLib`).
 *
 * Lancement : dev/test package=structure file=InviteCommunityMailAi
 */
class InviteCommunityMailAiTest extends \Test {

	const DESCRIPTION = '<main><p>zzTest presentation du groupe qui invite.</p></main>';

	const FARM_TEXT = 'gérer toute leur ferme au même endroit';

	public function testNetworkMail(): void {

		$eStructure = $this->getStructure(FixtureAiLib::G3);

		if($this->assertDecorPresent($eStructure->isNetwork(), 'le réseau G3') === FALSE) {
			return;
		}

		foreach(['member' => 'Producteur', 'advisor' => 'Conseiller', 'admin' => 'Administrateur'] as $fqn => $name) {

			$eRole = RoleLib::getOne($eStructure, $fqn);

			if($this->assertDecorPresent($eRole->notEmpty() and $eRole['name'] === $name, 'le rôle '.$name.' du réseau G3') === FALSE) {
				return;
			}

			[$subject, $text, $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, $eRole));

			$this->assertEquals($eStructure['name'].' vous invite à rejoindre le réseau', $subject, $name.' : l\'objet nomme le réseau qui invite');
			$this->assertTrue(str_contains($html, encode($eStructure['name']).' vous invite à rejoindre le réseau'), $name.' : le titre aussi');
			$this->assertTrue(str_contains($html, 'Vous y serez <b>'.$name.'</b>.'), $name.' : le rôle reçu, avec la phrase de la page');
			$this->assertTrue(str_contains($text, 'Vous y serez '.$name.'.'), $name.' : dans le texte brut aussi');
			$this->assertTrue(str_contains($html, 'est le logiciel qu\'utilise <b>'.encode($eStructure['name']).'</b>.'), $name.' : le corps nomme le réseau');
			$this->assertTrue(str_contains($html, 'Voir l\'invitation'), $name.' : le bouton ouvre l\'invitation');
			$this->assertFalse(str_contains($html, 'Activer mon compte'), $name.' : sans promettre un compte à activer');
			$this->assertFalse(str_contains($html, self::FARM_TEXT), $name.' : sans le paragraphe sur la gestion d\'une ferme');

		}

	}

	public function testNetworkPresentationAndExpiry(): void {

		$eStructure = $this->getStructure(FixtureAiLib::G3);

		if($this->assertDecorPresent($eStructure->isNetwork(), 'le réseau G3') === FALSE) {
			return;
		}

		$eRole = RoleLib::getOne($eStructure, 'advisor');

		$eStructure['description'] = NULL;

		[, , $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, $eRole));

		$this->assertFalse(str_contains($html, 'Présentation du réseau'), 'sans présentation, aucun bloc');
		$this->assertFalse(str_contains($html, 'reste valable'), 'sans échéance, aucune phrase d\'échéance');

		$eStructure['description'] = self::DESCRIPTION;

		$eInvite = $this->buildInvite($eStructure, $eRole);
		$eInvite['expiresAt'] = '2026-10-30';

		[, $text, $html] = InviteUi::getInviteMail($eInvite);

		$this->assertTrue(str_contains($html, '<p><b>Présentation du réseau</b></p>'), 'la présentation est titrée par le libellé de son champ');
		$this->assertTrue(str_contains($html, 'zzTest presentation du groupe qui invite.'), 'et porte le texte saisi');
		$this->assertTrue(strpos($html, 'Voir l\'invitation') < strpos($html, 'Présentation du réseau'), 'après le bouton, comme sur la page que le bouton ouvre');
		$this->assertTrue(str_contains($text, 'zzTest presentation du groupe qui invite.'), 'le texte brut la porte aussi');
		$this->assertTrue(str_contains($html, 'L\'invitation reste valable jusqu\'au '.\util\DateUi::textual('2026-10-30', \util\DateUi::DATE).'.'), 'une échéance posée se dit');

		[, , $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, new Role()));

		$this->assertTrue(str_contains($html, 'Voir l\'invitation'), 'une invitation sans rôle se rend');
		$this->assertFalse(str_contains($html, 'Vous y serez'), 'sans dire de rôle');

	}

	/**
	 * Le nom du groupe et celui du rôle sont saisis par le groupe : posés en mémoire avec des
	 * caractères que le HTML interprète, ils doivent arriver échappés dans le corps et en clair dans
	 * l'objet, qui n'est pas du HTML.
	 */
	public function testNamesAreEscaped(): void {

		$eStructure = $this->getStructure(FixtureAiLib::G3);
		$eRole = RoleLib::getOne($eStructure, 'advisor');

		if($this->assertDecorPresent($eStructure->isNetwork() and $eRole->notEmpty(), 'le réseau G3 et son rôle Conseiller') === FALSE) {
			return;
		}

		$eStructure['name'] = 'zzTest Terres & Voisins d\'Auvergne';
		$eRole['name'] = 'Conseiller <technique>';

		[$subject, , $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, $eRole));

		$this->assertEquals('zzTest Terres & Voisins d\'Auvergne vous invite à rejoindre le réseau', $subject, 'l\'objet porte le nom en clair');
		$this->assertTrue(str_contains($html, 'zzTest Terres &amp; Voisins d&#039;Auvergne vous invite à rejoindre le réseau'), 'le titre porte le nom échappé');
		$this->assertTrue(str_contains($html, 'qu\'utilise <b>zzTest Terres &amp; Voisins d&#039;Auvergne</b>.'), 'le corps aussi');
		$this->assertTrue(str_contains($html, 'Vous y serez <b>Conseiller &lt;technique&gt;</b>.'), 'le rôle aussi');
		$this->assertFalse(str_contains($html, 'Voisins d\'Auvergne'), 'le nom n\'apparaît jamais sans échappement');
		$this->assertFalse(str_contains($html, '<technique>'), 'le rôle non plus');

	}

	public function testOtherGroupsMail(): void {

		$eStructureEducation = $this->getStructure(FixtureAiLib::G2);
		$eStructureCommunity = $this->getStructure(FixtureAiLib::G4);

		if($this->assertDecorPresent(
			$eStructureEducation->isEducation() and $eStructureCommunity->isCommunity() and $eStructureCommunity['communityType'] === Structure::PRODUCER,
			'le groupe de formation G2 et la communauté G4'
		) === FALSE) {
			return;
		}

		$cases = [
			[$eStructureEducation, 'admin', 'Formateur'],
			[$eStructureEducation, 'student', 'Étudiant'],
			[$eStructureCommunity, 'member', 'Producteur'],
			[$eStructureCommunity, 'admin', 'Administrateur'],
		];

		foreach($cases as [$eStructure, $fqn, $name]) {

			$eRole = RoleLib::getOne($eStructure, $fqn);

			if($this->assertDecorPresent($eRole->notEmpty() and $eRole['name'] === $name, 'le rôle '.$name.' de '.$eStructure['name']) === FALSE) {
				return;
			}

			$eStructure['description'] = self::DESCRIPTION;

			[$subject, , $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, $eRole));

			$this->assertEquals($eStructure['name'].' vous invite à rejoindre le groupe', $subject, $name.' : l\'objet nomme le groupe qui invite');
			$this->assertTrue(str_contains($html, 'Vous y serez <b>'.$name.'</b>.'), $name.' : le rôle reçu');
			$this->assertTrue(str_contains($html, '<p><b>Présentation du groupe</b></p>'), $name.' : la présentation du groupe');
			$this->assertFalse(str_contains($html, self::FARM_TEXT), $name.' : sans le paragraphe sur la gestion d\'une ferme');

		}

	}

	public function testFarmAndAmapUnchanged(): void {

		$eStructureFarm = $this->getStructure(FixtureAiLib::F1);
		$eStructureAmap = $this->getStructure(FixtureAiLib::A1);

		if($this->assertDecorPresent($eStructureFarm->isProducer() and $eStructureAmap->isAmap(), 'la ferme F1 et l\'AMAP A1') === FALSE) {
			return;
		}

		foreach([$eStructureFarm, $eStructureAmap] as $eStructure) {

			$eStructure['description'] = self::DESCRIPTION;

			[$subject, , $html] = InviteUi::getInviteMail($this->buildInvite($eStructure, RoleLib::getOne($eStructure, 'admin')));

			$this->assertEquals($eStructure['name'].' vous invite à rejoindre son équipe', $subject, $eStructure['name'].' : l\'objet est inchangé');
			$this->assertTrue(str_contains($html, self::FARM_TEXT), $eStructure['name'].' : le paragraphe est inchangé');
			$this->assertTrue(str_contains($html, 'Activer mon compte'), $eStructure['name'].' : le bouton est inchangé');
			$this->assertFalse(str_contains($html, 'zzTest presentation'), $eStructure['name'].' : sans présentation');

		}

		[$subject] = InviteUi::getInviteMail($this->buildInvite($eStructureAmap, RoleLib::getOne($eStructureAmap, 'producer')));

		$this->assertEquals($eStructureAmap['name'].' vous invite à la livrer', $subject, 'le producteur d\'une AMAP garde son e-mail');

	}

	private function buildInvite(Structure $eStructure, Role $eRole): Invite {

		return new Invite([
			'id' => 1,
			'key' => 'zztestinvitecommunitymail',
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'email' => 'zztest.invite@example.org',
			'memberRole' => $eRole,
			'expiresAt' => NULL,
		]);

	}

	private function getStructure(int $id): Structure {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId($id)
			->get();

	}

}
?>
