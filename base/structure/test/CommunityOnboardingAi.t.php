<?php
namespace structure;

/**
 * L'appel à inviter n'a pas sa place sur l'onglet Membres (lot membres-sans-accueil du chantier
 * INRAE) : le bouton d'invitation est déjà dans le titre de cet onglet. Les autres écrans du groupe
 * le gardent.
 *
 * Décor : le groupe silencieux G5, dont le lecteur principal est l'administrateur et le seul membre,
 * sans sous-groupe, c'est-à-dire exactement l'état `INVITE` (`StructureCommunityLib::getStateManage()`).
 *
 * Lancement : dev/test package=structure file=CommunityOnboardingAi
 */
class CommunityOnboardingAiTest extends CommunityRouteAiTest {

	public function setUp(): void {

		parent::setUp();

		// `run()` lit le groupe sur cette propriété : G4 a plusieurs membres, donc aucun appel à inviter.
		$this->communitySharing = $this->communitySilent;

		// Le décor laisse G5 en mise en route, qui tait l'appel à l'action hors de l'accueil.
		if($this->hasDecor()) {
			FeatureLib::updateFeature($this->getStructure($this->communitySilent), 'hasStart', FALSE);
			$this->forgetStatics();
		}

	}

	public function testMembersHideInvite(): void {

		$data = $this->run('membres', $this->user);

		$this->assertTrue($data instanceof \stdClass, 'la page Membres de G5 n\'a pas rendu sa vue');
		$this->assertEquals(StructureCommunityLib::NOTHING, $data->onboarding['state'], 'Membres affiche encore l\'appel à inviter');

	}

	public function testFarmsKeepInvite(): void {

		$data = $this->run('fermes', $this->user);

		$this->assertTrue($data instanceof \stdClass, 'la page Fermes de G5 n\'a pas rendu sa vue');
		$this->assertEquals(StructureCommunityLib::INVITE, $data->onboarding['state'], 'Fermes a perdu l\'appel à inviter : le contrôle de Membres ne prouve plus rien');

	}

	/**
	 * Une invitation en attente ne fait pas un membre de plus : l'état reste `INVITE`, et Membres le
	 * cache tout autant.
	 */
	public function testMembersHideInviteWithPendingInvitation(): void {

		$eStructure = $this->getStructure($this->communitySilent);

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user, \user\User::getSelection()));

		$cRole = RoleLib::getByStructure($eStructure);

		$eInvite = new Invite([
			'structure' => $eStructure,
			'type' => Invite::MEMBER,
			'cRole' => $cRole,
			'cSection' => new \Collection(),
		]);

		$fw = new \FailWatch();

		$eInvite->build(['memberRole', 'memberSection', 'email'], [
			'memberRole' => RoleLib::getOne($eStructure, 'member')['id'],
			'memberSection' => '',
			'email' => 'decor.onboarding.invite@example.org',
		]);

		$this->assertTrue($fw->ok(), 'l\'invitation du décor est refusée par build()');

		InviteLib::create($eInvite);

		$this->assertEquals(StructureCommunityLib::INVITE, $this->run('fermes', $this->user)->onboarding['state'], 'une invitation en attente a changé l\'état de Fermes');
		$this->assertEquals(StructureCommunityLib::NOTHING, $this->run('membres', $this->user)->onboarding['state'], 'Membres affiche l\'appel à inviter quand une invitation est en attente');

	}

	/**
	 * Le conseiller d'un réseau encadre le groupe, il n'y apporte pas de ferme : l'accueil ne lui
	 * demande ni d'en créer une, ni d'en partager une. Relevé le 2026-10-07 par la recette à froid
	 * côté réseau, où il lisait « Créez votre ferme ».
	 */
	public function testAdvisorIsNotAskedForFarm(): void {

		$this->communitySharing = FixtureAiLib::G3;

		$eUser = \user\User::model()
			->select('id')
			->whereEmail('decor.g3.conseiller@example.org')
			->get();

		$this->assertTrue($eUser->notEmpty(), 'le conseiller de G3 manque au décor');

		$data = $this->run('fermes', $eUser['id']);

		$this->assertTrue($data instanceof \stdClass, 'la page Fermes de G3 n\'a pas rendu sa vue');
		$this->assertEquals(StructureCommunityLib::NOTHING, $data->onboarding['state'], 'l\'accueil demande une ferme au conseiller');

	}

}
?>
