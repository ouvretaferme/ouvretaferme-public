<?php
namespace structure;

/**
 * La mise en route d'un groupe ne tient que l'accueil (lot MR2 du chantier e-learning).
 *
 * Trois contrats :
 * - le parcours s'ouvre sur la seule route qui le déclare (`startApply`), les autres écrans d'un
 *   groupe en mise en route ne portent aucun guide, et le bandeau de fin se pose là où la fin ramène ;
 * - l'écran d'où l'on part voyage par `origin`, borné par `CommunityStartAiUi::ORIGINS`, jusqu'à la
 *   fin du parcours, qui y ramène ;
 * - un numéro d'étape que la suite du groupe ne porte pas rouvre la première étape qui manque.
 *
 * Décor : le groupe partageant G4, administré par le lecteur principal, dont `hasStart` est rallumé
 * dans la transaction de `CommunityRouteAiTest`. Le cas du réseau prend G3, le seul du décor, le
 * temps de sa méthode.
 *
 * Lancement : dev/test package=structure file=CommunityStartRouteAi
 */
class CommunityStartRouteAiTest extends CommunityRouteAiTest {

	public function setUp(): void {

		parent::setUp();

		if($this->hasDecor()) {
			FeatureLib::updateFeature($this->getStructure($this->communitySharing), 'hasStart', TRUE);
			$this->forgetStatics();
		}

	}

	public function testStartHoldsOnlyHome(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->run('', $this->user);

		$this->assertTrue($data->start !== NULL, 'l\'accueil ouvre le parcours');

		foreach(['fermes', 'membres'] as $path) {

			$data = $this->run($path, $this->user);

			$this->assertTrue($data->start === NULL, $path.' : le parcours ne s\'y ouvre pas');

		}

		// L'écran des fermes déclare l'appel à l'action, qui proposerait les gestes du parcours.
		$data = $this->run('fermes', $this->user);

		$this->assertEquals(StructureCommunityLib::NOTHING, $data->onboarding['state'], 'fermes : l\'appel à l\'action se tait pendant la mise en route');

		FeatureLib::updateFeature($this->getStructure($this->communitySharing), 'hasStart', FALSE);
		$this->forgetStatics();

		$data = $this->run('fermes', $this->user);

		$this->assertFalse($data->startPending, 'une mise en route finie ne tait plus rien');

	}

	public function testWelcomeWhereTheEndLeads(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->run('fermes', $this->user, ['demarrage' => 'fini']);

		$this->assertTrue($data->startWelcome, 'le bandeau de fin se pose sur l\'écran où la fin ramène');

	}

	public function testUnknownStepOpensFirstMissing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->run('', $this->user);
		$first = CommunityStartAiUi::getFirstMissing($data->eStructure, $data->cSection, $data->startCounts);

		$this->assertEquals($first, $data->start, 'sans numéro, la première étape qui manque');

		foreach(['9', '0'] as $step) {
			$data = $this->run('', $this->user, ['demarrage' => $step]);
			$this->assertEquals($first, $data->start, '?demarrage='.$step.' n\'est pas une étape du groupe : la première qui manque');
		}

	}

	public function testOriginLeadsBack(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->run('', $this->user, ['origin' => 'learning']);

		$this->assertEquals('learning', $data->startOrigin, 'l\'origine connue est lue');

		$html = CommunityStartAiUi::getStartSteps($data->eStructure, $data->cSection, $data->startCounts, $data->eInviteLink, $data->start, $data->startOrigin);

		$this->assertTrue(str_contains($html, '<a href="'.StructureUi::urlCommunityLearning($data->eStructure).'" class="onboarding-header-skip">'), 'le parcours ouvert depuis les formations y ramène');

		$data = $this->run('', $this->user, ['origin' => 'forgee']);

		$this->assertEquals(CommunityStartAiUi::ORIGIN_HOME, $data->startOrigin, 'une origine inconnue est l\'accueil');

		$html = CommunityStartAiUi::getStartSteps($data->eStructure, $data->cSection, $data->startCounts, $data->eInviteLink, $data->start, $data->startOrigin);

		$this->assertFalse(str_contains($html, 'onboarding-header-skip'), 'et le parcours ouvert depuis l\'accueil n\'a pas de lien de retour');

	}

	public function testActionsKeepOrigin(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->communitySharing);
		$url = StructureUi::urlCommunity($eStructure);
		$step = CommunityStartAiUi::getStepNumber($eStructure, 'feature');

		$feature = $eStructure->getCommunityFeatures()[0];
		$value = $eStructure->conf(Feature::class)[$feature] ? '1' : '0';

		$input = ['id' => (string)$eStructure['id'], 'feature' => $feature, 'value' => $value];

		$this->assertEquals([\ReloadAction::class, NULL], $this->post('structure/community:doUpdateFeature', $input), 'l\'onglet de réglage, sans origine, recharge');
		$this->assertEquals([\RedirectAction::class, $url.'?demarrage='.$step], $this->post('structure/community:doUpdateFeature', $input + ['origin' => 'start']), 'l\'accueil rouvre l\'étape');
		$this->assertEquals([\RedirectAction::class, $url.'?demarrage='.$step.'&origin=learning'], $this->post('structure/community:doUpdateFeature', $input + ['origin' => 'learning']), 'et garde l\'écran d\'où l\'on est parti');

		$input = ['id' => (string)$eStructure->conf(Feature::class)['id'], 'hasStart' => '0'];

		$this->assertEquals([\RedirectAction::class, StructureUi::urlCommunityLearning($eStructure).'?demarrage=fini'], $this->post('structure/feature:doUpdateStart', $input + ['origin' => 'learning']), 'la fin ramène aux formations');
		$this->assertEquals([\RedirectAction::class, $url.'?demarrage=fini'], $this->post('structure/feature:doUpdateStart', $input + ['origin' => 'start']), 'ou à l\'accueil');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->post('structure/feature:doUpdateStart', $input + ['origin' => 'forgee']), 'une origine inconnue est refusée');

	}

	/**
	 * Un réseau ne voit l'étape de partage que si Maraîchage est allumé, et elle paraît ou
	 * disparaît au rechargement que l'interrupteur de l'étape 2 provoque (lot MR3).
	 */
	public function testNetworkSharesOnlyWithVegetable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		// L'instance sert toutes les méthodes : G3 ne doit pas survivre à celle-ci
		$communitySharingBefore = $this->communitySharing;

		try {

			if($this->startNetwork() === FALSE) {
				return;
			}

			$eStructure = $this->getStructure($this->communitySharing);

			FeatureLib::updateFeature($eStructure, 'hasCommunityVegetable', FALSE);
			$this->forgetStatics();

			$data = $this->run('', $this->user);

			$this->assertTrue($data->start !== NULL, 'l\'administrateur posé ouvre le parcours de G3');
			$this->assertEquals(0, CommunityStartAiUi::getStepNumber($data->eStructure, 'share'), 'Maraîchage éteint : pas d\'étape de partage');

			$step = CommunityStartAiUi::getStepNumber($data->eStructure, 'feature');
			$input = ['id' => (string)$eStructure['id'], 'feature' => 'hasCommunityVegetable', 'origin' => 'start'];

			$this->assertEquals([\RedirectAction::class, StructureUi::urlCommunity($eStructure).'?demarrage='.$step], $this->post('structure/community:doUpdateFeature', $input + ['value' => '1']), 'allumer Maraîchage rouvre l\'étape des fonctionnalités');

			$data = $this->run('', $this->user, ['demarrage' => (string)$step]);

			$this->assertEquals(3, CommunityStartAiUi::getStepNumber($data->eStructure, 'share'), 'Maraîchage allumé : le partage en troisième');
			$this->assertEquals(4, CommunityStartAiUi::getStepNumber($data->eStructure, 'invite'), 'les invitations en quatrième');

			$this->post('structure/community:doUpdateFeature', $input + ['value' => '0']);

			$data = $this->run('', $this->user, ['demarrage' => '4']);

			$this->assertEquals(0, CommunityStartAiUi::getStepNumber($data->eStructure, 'share'), 'Maraîchage éteint de nouveau : l\'étape disparaît');
			$this->assertEquals(CommunityStartAiUi::getFirstMissing($data->eStructure, $data->cSection, $data->startCounts), $data->start, 'et le numéro qu\'elle laissait rouvre la première étape qui manque');

		} finally {
			$this->communitySharing = $communitySharingBefore;
		}

	}

	/**
	 * Le bandeau de fin d'un réseau reçoit le compte de ses formations, et ne compte parties que les
	 * invitations envoyées par adresse (lot MR4).
	 *
	 * Les invitations et les formations de G3 ne sont pas supposées nulles : le test compare à ce
	 * qu'il lit avant d'agir.
	 */
	public function testNetworkWelcome(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$communitySharingBefore = $this->communitySharing;

		try {

			if($this->startNetwork() === FALSE) {
				return;
			}

			$eStructure = $this->getStructure($this->communitySharing);

			foreach(['hasCommunityVegetable' => FALSE, 'hasQuotation' => FALSE, 'hasCommunitySections' => FALSE, 'hasCommunityLearning' => TRUE] as $feature => $value) {
				FeatureLib::updateFeature($eStructure, $feature, $value);
			}

			$this->forgetStatics();

			$data = $this->run('', $this->user, ['demarrage' => 'fini']);
			$sent = $data->startCounts['invitesSent'];

			$this->assertEquals(\learning\CourseLib::countByCommunity($data->eStructure), $data->startCounts['courses'], 'le bandeau reçoit le compte des formations');

			// Le lien n'est envoyé à personne
			$this->post('structure/community:doStartLink', ['id' => (string)$eStructure['id'], 'origin' => CommunityStartAiUi::ORIGIN_HOME]);

			$data = $this->run('', $this->user, ['demarrage' => 'fini']);

			$this->assertTrue(InviteLib::getLinkByStructure($data->eStructure)->notEmpty(), 'le lien du réseau existe');
			$this->assertEquals($sent, $data->startCounts['invitesSent'], 'le lien ne compte pas comme une invitation partie');

			$this->assertEquals(
				[\RedirectAction::class, CommunityStartAiUi::urlFinish($eStructure, CommunityStartAiUi::ORIGIN_HOME)],
				$this->post('structure/community:doStartInvites', ['id' => (string)$eStructure['id'], 'origin' => CommunityStartAiUi::ORIGIN_HOME, 'audience' => 'member', 'emails' => 'zztest.mr4.invite@example.org']),
				'l\'invitation par adresse termine le parcours'
			);

			$data = $this->run('', $this->user, ['demarrage' => 'fini']);

			$this->assertEquals($sent + 1, $data->startCounts['invitesSent'], 'l\'invitation par adresse compte');

		} finally {
			$this->communitySharing = $communitySharingBefore;
		}

	}

	/**
	 * G3 en mise en route, administré par le lecteur principal.
	 *
	 * ⚠️ G3 est le seul réseau du décor et n'a pas d'administrateur : son parcours ne s'ouvre pour
	 * personne. Le lecteur principal y devient administrateur dans la transaction, que
	 * `tearDown()` annule ; aucune ligne semée ne servirait mieux, puisque c'est le type de G3 que
	 * le cas éprouve. L'appelant remet `communitySharing` en partant.
	 */
	private function startNetwork(): bool {

		$this->communitySharing = FixtureAiLib::G3;

		$eStructure = $this->getStructure($this->communitySharing);
		$eRole = RoleLib::getOne($eStructure, 'admin');

		if($this->assertDecorPresent($eStructure->isNetwork() and $eRole->notEmpty(), 'le réseau G3 du décor et son rôle d\'administrateur') === FALSE) {
			return FALSE;
		}

		// Hors build(), dispense des .t.php : son étape known refuserait un rôle d'une autre structure, et $eRole est lu sur G3 juste au-dessus
		MemberLib::create(new Member([
			'user' => new \user\User(['id' => $this->user]),
			'structure' => $eStructure,
			'role' => $eRole,
			'status' => Member::IN
		]));

		FeatureLib::updateFeature($eStructure, 'hasStart', TRUE);
		$this->forgetStatics();

		return TRUE;

	}

	/**
	 * L'action jouée comme un formulaire : la classe de l'action levée, et l'adresse d'une
	 * redirection.
	 */
	private function post(string $path, array $input): array {

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user));

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'POST');

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		[$getBefore, $postBefore, $requestBefore] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), new \stdClass());
			return ['aucune action', NULL];
		} catch(\RedirectAction $action) {
			return [\RedirectAction::class, new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action)];
		} catch(\Action $action) {
			return [get_class($action), NULL];
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getBefore, $postBefore, $requestBefore];
			$method->setValue(NULL, $methodBefore);
			\user\ConnectionLib::setOnline(new \user\User());
			$this->forgetStatics();
		}

	}

}
?>
