<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * L'avis sans compte (lot L15c) : par le lien d'une inscription, la personne note la date où sa
 * place était confirmée, modifie et retire son avis ; l'avis suit la ligne quand un compte se
 * connecte à la même adresse. L'invitation qui porte ce lien est dans `ReviewMail.t.php`.
 *
 * Les lignes sans compte sont en `example.org`.
 *
 * Lancement : dev/test package=learning file=ReviewPublic
 */
class ReviewPublicTest extends LearningReviewFixtureTest {

	public function testByLink(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.avis-public@example.org');

		$this->actAs(new \user\User());

		$path = $this->getPath().'/inscription/'.$eDateEnrollment['token'].'/avis';

		$this->assertEquals(['ViewAction', NULL], $this->executePage($path, [], 'GET'), 'la page de l\'avis se lit avant la date');
		$this->assertEquals('Vous pourrez donner votre avis une fois la formation passée.', ReviewUi::getRefusal($this->getLine($eDateEnrollment['id']), ReviewLib::getCreateElementByDateEnrollment($this->getLine($eDateEnrollment['id']))), 'avec la raison');
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage($path.'/:doReview', ['rating' => '5']), 'mais n\'écrit rien');

		$this->moveDate($eDate, -3);

		$this->assertEquals(['FailAction', 'Review::rating.check'], $this->executePage($path.'/:doReview', ['rating' => '']), 'une note est exigée');
		$this->assertEquals(['ViewAction', NULL], $this->executePage($path.'/:doReview', ['rating' => '5', 'text' => 'zzTest sans compte']), 'la date passée, l\'avis s\'écrit');

		$eReview = ReviewLib::getByDateEnrollment($eDateEnrollment);

		$this->assertEquals([5, TRUE, $this->day(-3), 'Zztest P.'], [$eReview['rating'], $eReview['user']->empty(), $eReview['experiencedAt'], $eReview->getAuthorName()], 'sans compte, signé de la saisie');

		$this->assertEquals(['ViewAction', NULL], $this->executePage($path.'/:doReview', ['rating' => '3']), 'le même lien le modifie');
		$this->assertEquals([1, 3], [Review::model()->whereCourse($eCourse)->count(), ReviewLib::getByDateEnrollment($eDateEnrollment)['rating']], 'sans en écrire un second');

		$this->assertEquals(['ViewAction', NULL], $this->executePage($path.'/:doDelete', []), 'et le retire');
		$this->assertTrue(ReviewLib::getByDateEnrollment($eDateEnrollment)->empty(), 'retiré');

		$this->assertEquals(['ViewAction', '/error:404'], $this->call($this->getPath().'/inscription/zzTestJetonInconnu/avis'), 'un jeton inconnu rend la 404');
		$this->assertEquals(['ViewAction', '/error:404'], $this->call('reseau/'.$this->getFqnOther().'/inscription/'.$eDateEnrollment['token'].'/avis'), 'comme le jeton lu sous l\'adresse d\'un autre réseau');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Hors catalogue public (lot glacons-parcours, choix 4 bis B), le lien ouvre l'avis aussi, et la
	 * page ne parle ni de publication ni de fiche : le réseau seul le lit.
	 */
	public function testByLinkPrivate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.avis-prive@example.org');
		$this->moveDate($eDate, -3);

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['catalog'] = Course::PRIVATE;
		CourseLib::update($eCourse, ['catalog']);

		$this->actAs(new \user\User());

		$path = $this->getPath().'/inscription/'.$eDateEnrollment['token'].'/avis';

		$data = $this->executeView($path, []);
		$ui = new DatePublicUi($data->eStructureCommunity, $data->eCommunity, $data->embed);

		$page = $ui->getReview($data->eDateEnrollment, $data->eReview);

		$this->assertTrue(str_contains($page, 'doReview'), 'le formulaire s\'ouvre hors catalogue : '.$page);
		$this->assertFalse(str_contains($page, s("Publier l'avis")), 'sans parler de publication');

		$data = $this->executeView($path.'/:doReview', ['rating' => '4', 'text' => 'zzTest hors catalogue'], 'POST');
		$sent = $ui->getReviewSent($data->eDateEnrollment, $data->eReview, $data->deleted);

		$this->assertFalse(str_contains($sent, s("fiche")), 'sans fiche');

		$page = $ui->getReview($data->eDateEnrollment, ReviewLib::getByDateEnrollment($eDateEnrollment));

		$this->assertFalse(str_contains($page, s("de la fiche de la formation")), 'le retrait ne parle pas de la fiche');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Le lien d'une place rattachée à un compte de l'équipe du réseau ne note pas la formation du
	 * réseau (Q1, V-Learning le 28/09) : la page donne la raison au lieu du formulaire, et l'envoi
	 * forgé n'écrit rien. La place sans compte de la même date note.
	 */
	public function testNetworkMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eDate = $this->createDate($eCourse, 10);

		$eUserStaff = $this->createPerson();
		$this->join($eUserStaff, $this->eStructureNetwork);

		// Une place prise par la page publique à l'adresse du compte, puis rattachée à lui : seule une
		// ligne de la page publique porte un lien, celle du panneau du membre n'en a pas.
		$eDateEnrollmentStaff = $this->enrollPublicConfirmed($eDate, $eUserStaff['email']);
		DateEnrollmentLib::attachByUser($eUserStaff);
		$eDateEnrollmentStaff = $this->getLine($eDateEnrollmentStaff['id']);
		$eDateEnrollmentPublic = $this->enrollPublicConfirmed($eDate, 'zztest.avis-equipe@example.org');

		$this->moveDate($eDate, -3);

		$this->actAs(new \user\User());

		$this->assertEquals([$eUserStaff['id'], TRUE], [$eDateEnrollmentStaff['user']['id'], $eDateEnrollmentStaff['token'] !== NULL], 'la place rattachée au compte garde son lien');

		$pathStaff = $this->getPath().'/inscription/'.$eDateEnrollmentStaff['token'].'/avis';
		$pathPublic = $this->getPath().'/inscription/'.$eDateEnrollmentPublic['token'].'/avis';

		$eReviewStaff = ReviewLib::getCreateElementByDateEnrollment($this->getLine($eDateEnrollmentStaff['id']));

		$this->assertEquals([TRUE, FALSE, FALSE], [$eReviewStaff->acceptCreate(), $eReviewStaff->canCreateByLink(), $eReviewStaff->assertCreateByLink()], 'la date suivie, mais le compte de la place écrit la formation');
		$this->assertEquals('Les avis viennent des personnes qui suivent la formation, pas de celles qui l\'écrivent.', ReviewUi::getRefusal($this->getLine($eDateEnrollmentStaff['id']), $eReviewStaff), 'la raison à la place du formulaire');
		$data = $this->executeView($pathStaff, []);
		$html = new DatePublicUi($data->eStructureCommunity, $data->eCommunity, FALSE)->getReview($data->eDateEnrollment, $data->eReview);

		$this->assertEquals([FALSE, FALSE], [str_contains($html, '<form'), str_contains($html, 'doReview')], 'la page rendue est sans formulaire ni bouton');

		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage($pathStaff.'/:doReview', ['rating' => '5']), 'l\'envoi forgé est refusé');
		$this->assertTrue(ReviewLib::getByDateEnrollment($eDateEnrollmentStaff)->empty(), 'sans rien écrire');

		$eReviewPublic = ReviewLib::getCreateElementByDateEnrollment($this->getLine($eDateEnrollmentPublic['id']));

		$this->assertTrue($eReviewPublic->assertCreateByLink(), 'la place sans compte de la même date note');

		$data = $this->executeView($pathPublic, []);
		$html = new DatePublicUi($data->eStructureCommunity, $data->eCommunity, FALSE)->getReview($data->eDateEnrollment, $data->eReview);

		$this->assertTrue(str_contains($html, '<form') and str_contains($html, 'doReview'), 'sa page porte le formulaire');
		$this->assertEquals(['ViewAction', NULL], $this->executePage($pathPublic.'/:doReview', ['rating' => '4']), 'et publie');
		$this->assertEquals(4, ReviewLib::getByDateEnrollment($eDateEnrollmentPublic)['rating'], 'l\'avis est écrit');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'avis donné sans compte suit la ligne quand un compte se connecte à la même adresse, et le plus
	 * récent reste quand le compte en avait déjà un.
	 */
	public function testAttach(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.avis-rattache@example.org');
		$this->moveDate($eDate, -3);

		$this->actAs(new \user\User());
		$this->executePage($this->getPath().'/inscription/'.$eDateEnrollment['token'].'/avis/:doReview', ['rating' => '4']);
		$this->actAs($this->eUserAdmin);

		$eUser = $this->createUser('zztest.avis-rattache@example.org');

		DateEnrollmentLib::attachByUser($eUser);

		$eReview = ReviewLib::getByDateEnrollment($eDateEnrollment);

		$this->assertEquals([$eUser['id'], 4], [$eReview['user']['id'], $eReview['rating']], 'l\'avis passe au compte');
		$this->assertEquals($eReview['id'], $this->getReview($eCourse, $eUser)['id'], 'et se lit par lui depuis la fiche');

	}

	private function getPath(): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
	}

	/**
	 * L'adresse d'un autre réseau de la base, comme `DatePublic.t.php` la cherche.
	 */
	private function getFqnOther(): string {

		$eCommunity = \structure\Community::model()
			->select('fqn')
			->whereStructure('!=', $this->eStructureNetwork)
			->whereStructure('IN', \structure\Structure::model()
				->select('id')
				->whereType(\structure\Structure::COMMUNITY)
				->whereCommunityType(\structure\Structure::NETWORK)
				->getSql())
			->get();

		if($eCommunity->empty()) {
			throw new \Exception('aucun autre réseau avec une adresse dans cette base');
		}

		return $eCommunity['fqn'];

	}

	/**
	 * La route lue en GET comme `DatePublic.t.php` la lit : la 404 d'un jeton inconnu est une vue, dont
	 * seul le chemin dit qu'elle n'est pas la page. La méthode de requête se pose et se rend, sinon
	 * celle du dernier `executePage()` en POST choisit les routes et la page GET n'est pas trouvée.
	 *
	 * @return array{0: string, 1: ?string}
	 */
	private function call(string $path): array {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		try {
			\Page::execute(\Route::getCompatiblePages($path), new \stdClass());
			return ['aucune action', NULL];
		} catch(\ViewAction $action) {
			return [\ViewAction::class, new \ReflectionProperty(\ViewAction::class, 'path')->getValue($action)];
		} catch(\Action $action) {
			return [get_class($action), NULL];
		} finally {
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
