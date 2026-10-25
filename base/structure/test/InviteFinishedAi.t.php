<?php
namespace structure;

/**
 * Les invitations épuisées sur les écrans communs de l'équipe d'une ferme et des membres d'un groupe.
 *
 * Une invitation qui a servi autant de fois qu'elle le promettait reste PENDING jusqu'à son nettoyage
 * (`InviteLib::clean()`). Parmi les invitations en attente, elle ferait mentir le titre : elle est
 * rangée avec les invitations terminées, comme sur la page des producteurs d'une AMAP
 * (`commercialisation/amap/test/ProducerInviteFinished.t.php`). Sur une ferme, elles forment le groupe
 * replié « Invitations terminées » de l'onglet Personnes, sous les invitations en attente.
 *
 * Décor : la ferme STRUCTURE_SHARED et le groupe partageant G4, administrés par le lecteur principal.
 * Isolation : une transaction par méthode, annulée en tearDown() (`CommunityRouteAiTest`).
 *
 * Lancement : dev/test package=structure file=InviteFinishedAi
 */
class InviteFinishedAiTest extends CommunityRouteAiTest {

	public function testFarmTeamPutsFinishedInvitesApart(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure(self::STRUCTURE_SHARED);

		$eInviteOpen = $this->createInvite($eStructure, 3);
		$eInviteFinished = $this->createInvite($eStructure, 10);

		$data = $this->runTeam($eStructure);

		if($data === NULL) {
			return;
		}

		$this->assertEquals([$eInviteOpen['id']], array_values($data->cInvite->getIds()), 'l\'invitation qui sert encore n\'est pas seule en cours');
		$this->assertEquals([$eInviteFinished['id']], array_values($data->cInviteFinished->getIds()), 'l\'invitation épuisée n\'est pas rangée à part');

	}

	public function testGroupMembersPutFinishedInvitesApart(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);

		$eInviteOpen = $this->createInvite($eStructure, 3);
		$eInviteFinished = $this->createInvite($eStructure, 10);

		$data = $this->run('membres', $this->user);

		if(($data instanceof \stdClass) === FALSE) {
			$this->assertFail('la page des membres du groupe ne se rend pas : '.$data);
			return;
		}

		$this->assertEquals([$eInviteOpen['id']], array_values($data->cInvite->getIds()), 'l\'invitation qui sert encore n\'est pas seule en cours');
		$this->assertEquals([$eInviteFinished['id']], array_values($data->cInviteFinished->getIds()), 'l\'invitation épuisée n\'est pas rangée à part');

	}

	/**
	 * Une invitation par lien, utilisable dix fois, déjà utilisée `$used` fois. Écrite sans `build()` :
	 * `used` n'est pas une propriété de formulaire, seul `InviteLib::accept()` la fait avancer, et le
	 * formulaire d'invitation enverrait un courriel.
	 */
	private function createInvite(Structure $eStructure, int $used): Invite {

		$eInvite = new Invite([
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'memberRole' => RoleLib::getOne($eStructure, 'admin'),
			'email' => NULL,
			'limit' => 10,
			'notifyEmail' => FALSE
		]);

		InviteLib::create($eInvite);

		$eInvite['used'] = $used;
		InviteLib::update($eInvite, ['used']);

		return $eInvite;

	}

	/**
	 * La page `/ferme/{id}/equipe` jusqu'à sa vue, dont on garde les données. `run()` ne sert que les
	 * adresses d'un groupe.
	 */
	private function runTeam(Structure $eStructure): ?\stdClass {

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user, \user\User::getSelection()));

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		$getBefore = $_GET;
		$_GET = ['id' => (string)$eStructure['id']];

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		try {
			\Page::execute(\Route::getCompatiblePages('ferme/'.$eStructure['id'].'/equipe'), $data);
			$this->assertFail('la page de l\'équipe ne rend pas de vue');
			return NULL;
		} catch(\ViewAction $action) {
			return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
		} catch(\Action $action) {
			$this->assertFail('la page de l\'équipe rend '.get_class($action));
			return NULL;
		} finally {
			$_GET = $getBefore;
			$method->setValue(NULL, $methodBefore);
			\user\ConnectionLib::setOnline(new \user\User());
		}

	}

}
?>
