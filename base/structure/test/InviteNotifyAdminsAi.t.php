<?php
namespace structure;

/**
 * À qui part l'annonce qu'une invitation a servi (« X a rejoint … »).
 *
 * Elle partait à l'adresse de contact de la structure (`legalEmail`). Une personne qui quitte
 * l'équipe laisse souvent cette adresse derrière elle, et continuait donc de recevoir chaque arrivée
 * sur un lien d'invitation sans limite d'usage, sans pouvoir l'arrêter : le réglage « Ne plus recevoir
 * d'e-mail » n'était proposé qu'aux invitations limitées à plusieurs usages. Signalé sur Discord le
 * 2026-09-25, invitation 2980 de la structure 6251.
 *
 * L'annonce va désormais aux administrateurs du moment (`InviteLib::getNotifyEmails()`). L'envoi ne
 * s'observe pas sur `mailEmail` : en dev, `SendLib::send()` n'écrit aucune ligne pour une adresse
 * hors de `MailSetting::$devSendOnly`, et l'y ajouter ferait partir un vrai courriel. Ce test lit
 * donc la liste des destinataires que `InviteLib::accept()` parcourt.
 *
 * Décor : le groupe partageant G4, que les deux lecteurs du décor administrent, avec une adresse de
 * contact en `example.org` que ne porte aucun des deux. Le second est celui qui rejoint : sa propre
 * arrivée ne le prévient pas. Isolation : une transaction par méthode, annulée en `tearDown()`.
 *
 * Lancement : dev/test package=structure file=InviteNotifyAdminsAi
 */
class InviteNotifyAdminsAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {
		Member::model()->beginTransaction();
	}

	public function tearDown(): void {
		Member::model()->rollBack();
	}

	private function getInvite(Structure $eStructure): Invite {

		return new Invite([
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'limit' => NULL,
			'notifyEmail' => TRUE
		]);

	}

	private function getUser(int $id): \user\User {
		return \user\UserLib::getById($id, \user\User::getSelection());
	}

	public function testCurrentAdminsAreNotifiedInsteadOfContactEmail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$eUserAdmin = $this->getUser($this->user);

		$emails = InviteLib::getNotifyEmails($this->getInvite($eStructure), $this->getUser($this->userOther));

		$this->assertTrue(in_array($eUserAdmin['email'], $emails), 'l\'administrateur est prévenu ('.implode(', ', $emails).')');
		$this->assertFalse(in_array($eStructure['legalEmail'], $emails), 'l\'adresse de contact de la structure ne l\'est pas');
		$this->assertFalse(in_array(self::EMAIL_USER_OTHER, $emails), 'un simple membre ne l\'est pas');

	}

	public function testAdminWhoLeftIsNoLongerNotified(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$eUserAdmin = $this->getUser($this->user);

		Member::model()
			->whereStructure($eStructure)
			->whereUser($eUserAdmin)
			->update(['status' => Member::OUT]);

		$emails = InviteLib::getNotifyEmails($this->getInvite($eStructure), $this->getUser($this->userOther));

		$this->assertFalse(in_array($eUserAdmin['email'], $emails), 'un administrateur parti de l\'équipe n\'est plus prévenu ('.implode(', ', $emails).')');

	}

	public function testJoiningAdminIsNotNotifiedOfOwnArrival(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$eUserAdmin = $this->getUser($this->user);

		$emails = InviteLib::getNotifyEmails($this->getInvite($eStructure), $eUserAdmin);

		$this->assertFalse(in_array($eUserAdmin['email'], $emails), 'celui qui rejoint avec un rôle d\'administrateur n\'est pas prévenu de sa propre arrivée');

	}

}
?>
