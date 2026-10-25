<?php
namespace structure;

/**
 * Les e-mails de l'invitation d'un adhérent d'AMAP ne parlent pas de compte client et ne promettent
 * pas de créer un compte : une AMAP n'a pas de clients, elle a des adhérents, et le bureau ne sait pas
 * si l'adresse a déjà un compte, et ne doit pas l'apprendre. Ceux de l'invitation client d'une ferme
 * gardent leurs textes.
 *
 * Les invitations sont construites en mémoire et jamais écrites ; les structures et le compte sont
 * ceux du décor (`FixtureAiLib`), qui existe en dev comme en production.
 *
 * Lancement : dev/test package=structure file=InviteAmapWordingAi
 */
class InviteAmapWordingAiTest extends \Test {

	/** Client du décor qui n'adhère à aucune structure, destinataire de l'e-mail d'acceptation */
	const EMAIL_USER = 'decor.sans.structure@example.org';

	public function testAmapMailsAndPanelJoin(): void {

		$eStructureAmap = $this->getStructure(FixtureAiLib::A1);
		$eStructureFarm = $this->getStructure(FixtureAiLib::F1);
		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::EMAIL_USER)
			->get();

		if($this->assertDecorPresent(
			$eStructureAmap->isAmap() and $eStructureFarm->isProducer() and $eUser->notEmpty(),
			'l\'AMAP A1, la ferme F1 et le compte '.self::EMAIL_USER
		) === FALSE) {
			return;
		}

		$eInviteAmap = $this->buildInvite($eStructureAmap, Invite::PENDING, 'zztest.attendu@example.org');

		[$subject, , $html] = InviteUi::getInviteCustomerMail($eInviteAmap);

		$this->assertEquals(s("{value} vous invite à la rejoindre", $eStructureAmap['name']), $subject, 'AMAP, invitation : l\'objet de l\'e-mail fait rejoindre l\'AMAP');
		$this->assertTrue(str_contains($html, s("{value} vous invite à la rejoindre sur {siteName}", encode($eStructureAmap['name']))), 'et son titre aussi');
		$this->assertTrue(str_contains($html, s("Rejoindre l'AMAP")), 'par le bouton « Rejoindre l\'AMAP »');
		$this->assertFalse(str_contains($html, s("Activer mon compte")), 'sans bouton d\'activation de compte');
		$this->assertFalse(str_contains($subject.$html, 'créer votre compte'), 'sans promettre de créer un compte');

		[$subject, , $html] = InviteUi::getAcceptCustomerMail($eInviteAmap, $eUser);

		$this->assertEquals(s("Vous avez rejoint {value}", $eStructureAmap['name']), $subject, 'AMAP, acceptation : l\'invité a rejoint l\'AMAP');
		$this->assertFalse(str_contains($subject.$html, 'a été créé'), 'sans annoncer un compte créé');
		$this->assertFalse(str_contains($subject.$html, 'créer votre compte'), 'ni l\'invitation à en créer un');

		$eInviteFarm = $this->buildInvite($eStructureFarm, Invite::PENDING, 'zztest.attendu@example.org');

		[$subject, , $html] = InviteUi::getInviteCustomerMail($eInviteFarm);

		$this->assertEquals(s("{value} vous invite à créer votre compte client", $eStructureFarm['name']), $subject, 'ferme, invitation : l\'objet est inchangé');
		$this->assertTrue(str_contains($html, s("Activer mon compte client")), 'et son bouton aussi');

		[$subject] = InviteUi::getAcceptCustomerMail($eInviteFarm, $eUser);

		$this->assertEquals(s("Votre compte client {value} a été créé", $eStructureFarm['name']), $subject, 'ferme, acceptation : l\'objet est inchangé');

	}

	/**
	 * Une invitation expirée ou épuisée renvoie toujours vers la structure qui l'a envoyée, dans l'erreur
	 * levée à l'acceptation : l'invité ne sait pas toujours qui, dans la structure, l'a envoyée.
	 */
	public function testExpiredOrUsedSendsBackToTheStructure(): void {

		$sentences = [
			'erreur, invitation expirée' => AlertUi::getError('Invite::invalidExpired', []),
			'erreur, invitation épuisée' => AlertUi::getError('Invite::invalidLimit', [])
		];

		foreach($sentences as $where => $sentence) {
			$this->assertTrue(str_contains($sentence, "la structure qui vous l'a envoyée"), $where.' : renvoie vers la structure');
			$this->assertFalse(str_contains($sentence, "la personne qui vous l'a envoyée"), $where.' : ne renvoie pas vers une personne');
		}

	}

	private function buildInvite(Structure $eStructure, string $status, ?string $email): Invite {

		return new Invite([
			'id' => 1,
			'key' => 'zztestwording',
			'structure' => $eStructure,
			'type' => Invite::CUSTOMER,
			'email' => $email,
			'status' => $status,
			'expiresAt' => NULL,
			'limit' => NULL,
			'used' => 0,
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
