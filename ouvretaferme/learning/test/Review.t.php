<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Qui donne un avis, et ce que la fiche et la carte en montrent (lot L15c) : seule une personne qui a
 * suivi la formation note, à une date passée où sa place était confirmée ou en ligne jusqu'à la
 * dernière leçon ; un avis par personne ; la note moyenne à partir du seuil, avis masqués exclus.
 *
 * Lancement : dev/test package=learning file=Review
 */
class ReviewTest extends LearningReviewFixtureTest {

	public function testWhoReviews(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUserFollowed = $this->createPerson();
		$eUserRequested = $this->createPerson();
		$eUserUpcoming = $this->createPerson();

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUserFollowed, 5), 'sans place, pas d\'avis');

		$eDateEnrollment = $this->followDate($eCourse, $eUserFollowed);

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUserFollowed, 5, 'Très concret.'), 'place confirmée à une date passée, l\'avis s\'écrit');

		$eReview = $this->getReview($eCourse, $eUserFollowed);

		$this->assertEquals([5, 'Très concret.', $this->day(-3), $eDateEnrollment['id'], Review::PUBLISHED], [$eReview['rating'], $eReview['text'], $eReview['experiencedAt'], $eReview['dateEnrollment']['id'], $eReview['status']], 'la note, le texte, le jour suivi et la place');
		$this->assertEquals('zzTest S.', $eReview->getAuthorName(), 'signé du prénom et de l\'initiale');

		// Une demande laissée à confirmer, puis la date passée.
		$eDate = $this->createDate($eCourse, 10);
		$this->enrollRequested($eDate, $eUserRequested);
		$this->moveDate($eDate, -3);

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUserRequested, 4), 'une place jamais confirmée n\'ouvre pas l\'avis');

		$this->followDate($eCourse, $eUserUpcoming, 2);

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUserUpcoming, 4), 'une date à venir n\'ouvre pas l\'avis');

		$this->assertEquals(['NotAllowedAction', NULL], $this->writeReview($eCourse, $this->eUserAdmin, 5), 'qui écrit la formation ne la note pas');

		$this->actAs(new \user\User());
		$this->assertFalse(ReviewLib::getCreateElement($this->getCourse($eCourse['id']), new \user\User())->canCreate(), 'un visiteur ne note pas');
		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Une place confirmée puis annulée par le réseau, et une place restée confirmée sur une date
	 * annulée : aucune n'ouvre l'avis, la date passée.
	 */
	public function testCanceled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();

		$eUserCanceled = $this->createPerson();
		$eUserDateCanceled = $this->createPerson();

		$eDate = $this->createDate($eCourse, 10);
		DateEnrollmentLib::cancel($this->enrollConfirmed($eDate, $eUserCanceled), DateEnrollment::COMMUNITY);
		$this->moveDate($eDate, -3);

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUserCanceled, 4), 'une place annulée n\'ouvre pas l\'avis');

		// La date est annulée sans passer par `DateLib::cancel()`, qui annulerait aussi la place : on
		// éprouve ici la condition sur la date, la place restant confirmée.
		$eDate = $this->createDate($eCourse, 10);
		$this->enrollConfirmed($eDate, $eUserDateCanceled);
		$eDate = $this->moveDate($eDate, -3);
		$eDate['status'] = Date::CANCELED;
		DateLib::update($eDate, ['status']);

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUserDateCanceled, 4), 'une date annulée n\'ouvre pas l\'avis');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * Hors du catalogue public, l'avis s'ouvre quel que soit le parcours de la formation, proposée,
	 * refusée ou en brouillon, et seul le réseau le lit (choix 4 bis B du lot glacons-parcours). Posé
	 * sur l'élément, `acceptCreate()` ne regardant que lui.
	 */
	public function testCatalogStates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser);

		$eReview = ReviewLib::getCreateElement($this->getCourse($eCourse['id']), $eUser);

		$this->assertTrue($eReview->acceptCreate(), 'au catalogue, l\'avis s\'ouvre');

		foreach([
			'proposée' => ['catalog' => Course::PROPOSED],
			'refusée' => ['catalog' => Course::REFUSED],
			'privée' => ['catalog' => Course::PRIVATE],
			'en brouillon' => ['status' => Course::DRAFT],
		] as $label => $values) {

			$eReviewState = ReviewLib::getCreateElement($this->getCourse($eCourse['id']), $eUser);

			foreach($values as $property => $value) {
				$eReviewState['course'][$property] = $value;
			}

			$this->assertTrue($eReviewState->acceptCreate(), 'une formation '.$label.' ouvre l\'avis');

		}

	}

	public function testOneReviewPerPerson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser);

		$this->writeReview($eCourse, $eUser, 4, 'Premier avis.');

		$this->assertEquals(NULL, $this->getReview($eCourse, $eUser)['updatedAt'], 'un avis neuf n\'est pas modifié');

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 2), 'donner son avis une seconde fois');

		$eReview = $this->getReview($eCourse, $eUser);

		$this->assertEquals(1, Review::model()->whereCourse($eCourse)->count(), 'modifie le premier');
		$this->assertEquals([2, NULL], [$eReview['rating'], $eReview['text']], 'avec la note et le texte postés');
		$this->assertTrue($eReview['updatedAt'] !== NULL, 'et la date de modification');

		// Écrite hors du Lib pour éprouver la base elle-même : l'`UNIQUE (course, user)` tient même
		// quand un chemin oublierait `ReviewLib::create()`.
		$eReviewDuplicate = new Review([
			'course' => $eCourse,
			'user' => $eUser,
			'dateEnrollment' => new DateEnrollment(),
			'rating' => 5,
			'text' => NULL,
			'experiencedAt' => $this->day(-3),
		]);

		try {
			Review::model()->insert($eReviewDuplicate);
			$this->assertFail('un second avis inséré en base');
		} catch(\DuplicateException) {
			$this->assertTrue(TRUE, 'la base refuse un second avis de la même personne');
		}

	}

	/**
	 * Deux dates suivies de la même formation : un seul avis, daté de la plus récente.
	 */
	public function testTwoDates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser, -20);
		$this->followDate($eCourse, $eUser, -5);

		$this->writeReview($eCourse, $eUser, 3);
		$this->writeReview($eCourse, $eUser, 4);

		$this->assertEquals(1, Review::model()->whereCourse($eCourse)->count(), 'une ligne pour les deux dates');
		$this->assertEquals([4, $this->day(-5)], [$this->getReview($eCourse, $eUser)['rating'], $this->getReview($eCourse, $eUser)['experiencedAt']], 'datée de la plus récente');

	}

	/**
	 * La formation supprimée emporte ses avis ; une leçon ajoutée après l'avis ne le retire pas.
	 */
	public function testCourseChanges(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $lessons] = $this->seedCatalog(Course::ONLINE);

		$eUser = $this->createPerson();
		$this->followOnline($eCourse, $eUser, $lessons);
		$this->writeReview($eCourse, $eUser, 4);

		$this->createLesson(ChapterLib::getByCourse($eCourse)->first(), 'zzTest leçon 3', 10);

		$eReview = $this->getReview($eCourse, $eUser);

		$this->assertTrue($eReview->notEmpty(), 'une leçon ajoutée laisse l\'avis');

		$this->actAs($eUser);
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '2', 'text' => '']), 'qui se modifie encore');
		$this->actAs($this->eUserAdmin);

		CourseLib::delete($this->getCourse($eCourse['id']));

		$this->assertEquals(0, Review::model()->whereCourse($eCourse)->count(), 'la formation supprimée emporte ses avis');

	}

	public function testValidation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser);

		$this->assertEquals(['FailAction', 'Review::rating.check'], $this->writeReview($eCourse, $eUser, 0), 'note sous 1');
		$this->assertEquals(['FailAction', 'Review::rating.check'], $this->writeReview($eCourse, $eUser, 6), 'note au-dessus de 5');
		$this->assertEquals(['FailAction', 'Review::text.check'], $this->writeReview($eCourse, $eUser, 5, str_repeat('a', 2001)), 'texte trop long');
		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 5, str_repeat('a', 2000)), 'texte à la limite');
		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 5, str_repeat('é🌱', 1000)), '2 000 caractères avec accents et emoji, sans espace');
		$this->assertEquals(['FailAction', 'Review::text.check'], $this->writeReview($eCourse, $eUser, 5, str_repeat('é🌱', 1000).'a'), 'et pas un de plus');
		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 3, ''), 'texte vide');

		$this->assertEquals(NULL, $this->getReview($eCourse, $eUser)['text'], 'un texte vide est une note seule');

	}

	public function testOnline(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $lessons] = $this->seedCatalog(Course::ONLINE);

		$eUser = $this->createPerson();

		$this->followOnline($eCourse, $eUser, [$lessons[0]]);

		$this->assertEquals(['NotExpectedAction', NULL], $this->writeReview($eCourse, $eUser, 5), 'une leçon sur deux, pas d\'avis');

		$this->actAs($eUser);
		ProgressLib::done(EnrollmentLib::getByUserAndCourse($eUser, $eCourse), $lessons[1]);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 5), 'la dernière leçon faite, l\'avis s\'écrit');

		$eReview = $this->getReview($eCourse, $eUser);

		$this->assertEquals([\Clock::date(), TRUE], [$eReview['experiencedAt'], $eReview['dateEnrollment']->empty()], 'le jour de la dernière leçon, sans place');

	}

	public function testCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser);
		$this->writeReview($eCourse, $eUser, 4);

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['catalog'] = Course::PRIVATE;
		CourseLib::update($eCourse, ['catalog']);

		$this->assertEquals([0, NULL], $this->getSummary($eCourse), 'hors du catalogue, aucun avis ne se compte');

		$this->actAs($eUser);

		$eReview = $this->getReview($eCourse, $eUser);

		$this->assertTrue($eReview->assertUpdate(), 'mais se modifie, lu par le réseau seul');
		$this->assertTrue($eReview->assertDelete(), 'et se retire');

		$this->actAs($this->eUserAdmin);

		$eReview = $this->getReview($eCourse, $eUser);

		$this->assertTrue($eReview->assertAnswer(), 'le réseau y répond, pour l\'auteur seul');
		$this->assertFalse($eReview->acceptReport(), 'rien de publié, rien à signaler');

		$answer = new ReviewUi()->answer($eReview)->body;

		$this->assertTrue(str_contains($answer, '/learning/review:doAnswer'), 'le panneau de réponse poste la réponse : '.$answer);
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doAnswer', ['id' => $eReview['id'], 'answer' => 'zzTest réponse privée']), 'la réponse s\'enregistre');

		$fiche = $this->renderFiche($eCourse, $eUser);

		$this->assertFalse(str_contains($fiche, 'id="avis"'), 'la fiche n\'a pas de section');
		$this->assertTrue(str_contains($fiche, '/learning/review:update?id='), 'l\'auteur y modifie son avis');

		$this->actAs($eUser);

		$panel = new ReviewUi()->update($this->getReview($eCourse, $eUser))->body;

		$this->actAs($this->eUserAdmin);

		$this->assertTrue(str_contains($panel, 'data-ajax="/learning/review:doDelete"'), 'l\'auteur retire son avis');
		$this->assertTrue(str_contains($panel, 'zzTest réponse privée'), 'l\'auteur lit la réponse');

		$eUserWaiting = $this->createPerson();
		$eDateEnrollment = $this->followDate($eCourse, $eUserWaiting);

		$this->assertTrue(str_contains($this->renderFiche($eCourse, $eUserWaiting), '/learning/review:create?course='), 'qui a suivi la formation donne son avis');
		$this->assertEquals(NULL, ReviewUi::getRefusal($eDateEnrollment, ReviewLib::getCreateElementByDateEnrollment($eDateEnrollment)), 'la page par lien ne refuse plus');

		$this->actAs($eUserWaiting);

		$create = new ReviewUi()->create(ReviewLib::getCreateElement($this->getCourse($eCourse['id']), $eUserWaiting))->body;

		$this->actAs($this->eUserAdmin);

		$this->assertFalse(str_contains($create, s("Publier l'avis")), 'l\'avis s\'envoie, il ne se publie pas : '.$create);

	}

	public function testSummary(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$cUser = new \Collection();

		foreach([5, 4, 2, 1] as $rating) {

			$eUser = $this->createPerson();
			$this->followDate($eCourse, $eUser);

			$cUser[$rating] = $eUser;

		}

		$this->assertEquals([0, NULL], $this->getSummary($eCourse), 'aucun avis');

		$this->writeReview($eCourse, $cUser[5], 5);
		$this->writeReview($eCourse, $cUser[4], 4);

		$this->assertEquals([2, NULL], $this->getSummary($eCourse), 'sous le seuil, le nombre sans la note');

		$this->writeReview($eCourse, $cUser[2], 2);

		$this->assertEquals([3, round(11 / 3, 4)], $this->getSummary($eCourse), 'au seuil, la moyenne');
		$this->assertEquals([5 => 1, 4 => 1, 3 => 0, 2 => 1, 1 => 0], ReviewLib::getDistribution($this->getCourse($eCourse['id'])), 'la répartition, chaque note présente');

		// Une ancre nue se résout contre `<base href>` et mène à l'accueil.
		$eCourseRated = $this->getCourse($eCourse['id']);
		ReviewLib::fillSummary(new \Collection([$eCourseRated['id'] => $eCourseRated]));

		$this->assertTrue(str_contains(ReviewUi::getFicheRating($eCourseRated), 'href="'.CourseUi::url($eCourseRated).'#avis"'), 'les étoiles de la fiche mènent à ses avis par l\'adresse de la fiche');

		$this->writeReview($eCourse, $cUser[1], 1);

		$eReview = $this->getReview($eCourse, $cUser[1]);
		$eReview['hideReason'] = 'zzTest propos injurieux';
		ReviewLib::hide($eReview);

		$this->assertEquals([3, round(11 / 3, 4)], $this->getSummary($eCourse), 'un avis masqué ne compte pas');

		$eReview = $this->getReview($eCourse, $cUser[2]);
		$eReview['hideReason'] = 'zzTest';
		ReviewLib::hide($eReview);

		$this->assertEquals([2, NULL], $this->getSummary($eCourse), 'trois avis dont un masqué, la moyenne ne paraît plus');

		[$eCourseEmpty] = $this->seedCatalog(first: FALSE);

		$cCourse = new \Collection([
			$eCourse['id'] => $this->getCourse($eCourse['id']),
			$eCourseEmpty['id'] => $this->getCourse($eCourseEmpty['id'])
		]);

		ReviewLib::fillSummary($cCourse);

		$this->assertEquals([[2, NULL], [0, NULL]], [[$cCourse[$eCourse['id']]['reviews'], $cCourse[$eCourse['id']]['rating']], [$cCourse[$eCourseEmpty['id']]['reviews'], $cCourse[$eCourseEmpty['id']]['rating']]], 'une collection mêlée, chaque formation son résumé');

	}

	/**
	 * Le fil suit la date de publication, jamais la note (L111-7-2) : un 1 récent passe avant un 5
	 * ancien, et une modification ne fait pas remonter un avis.
	 */
	public function testThreadOrder(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUserOld = $this->createPerson();
		$eUserRecent = $this->createPerson();

		$this->followDate($eCourse, $eUserOld);
		$this->followDate($eCourse, $eUserRecent);

		$this->setClock($this->day(0).' 13:00:00');
		$this->writeReview($eCourse, $eUserOld, 5);

		$this->setClock($this->day(0).' 14:00:00');
		$this->writeReview($eCourse, $eUserRecent, 1);

		$this->setClock($this->day(0).' 15:00:00');
		$this->writeReview($eCourse, $eUserOld, 5, 'Retouché.');

		$this->assertEquals(
			[$this->getReview($eCourse, $eUserRecent)['id'], $this->getReview($eCourse, $eUserOld)['id']],
			ReviewLib::getForCourse($this->getCourse($eCourse['id']), FALSE)->getIds(),
			'le plus récemment publié d\'abord, retouche comprise'
		);

	}

	/**
	 * Le fil par pages de `ReviewLib::PER_PAGE` : `review:more` reprend à l'offset donné, sans doublon
	 * ni trou, le bouton annonce ce qui reste puis disparaît au dernier lot. Un visiteur ne compte ni ne
	 * reçoit l'avis masqué ; l'équipe de Ouvretaferme, qui décide, les lit tous. Douze avis, dont un
	 * masqué, soit onze publics : une page pleine et un avis de suite.
	 */
	public function testMore(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();

		$eDate = $this->createDate($eCourse, 10);

		$users = [];

		for($person = 1; $person <= 12; $person++) {
			$users[$person] = $this->createPerson();
			$this->enrollConfirmed($eDate, $users[$person]);
		}

		$this->moveDate($eDate, -3);

		// Une minute d'écart par avis : le fil suit la date de publication, du plus récent au plus ancien.
		foreach($users as $person => $eUser) {
			$this->setClock($this->day(0).' 13:'.sprintf('%02d', $person).':00');
			$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 1 + $person % 5), 'avis '.$person.' publié');
		}

		$reviews = [];

		for($person = 12; $person >= 1; $person--) {
			$reviews[] = $this->getReview($eCourse, $users[$person])['id'];
		}

		$hidden = $reviews[2];

		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/admin/review:doHide', ['id' => $hidden, 'hideReason' => 'zzTest hors sujet']), 'l\'équipe masque le troisième avis du fil');

		$published = array_values(array_diff($reviews, [$hidden]));

		// Le visiteur : onze avis, dix sur la fiche et le bouton pour le onzième.
		$this->actAs(new \user\User());

		$eCourseRead = $this->getCourse($eCourse['id']);
		$cReviewFirst = ReviewLib::getForCourse($eCourseRead, FALSE);

		$this->assertEquals([11, array_slice($published, 0, ReviewLib::PER_PAGE)], [ReviewLib::countForCourse($eCourseRead, FALSE), $cReviewFirst->getIds()], 'la fiche montre les dix plus récents, le masqué absent');

		$data = $this->executeView('learning/review:more', ['course' => $eCourse['id'], 'offset' => ReviewLib::PER_PAGE]);

		$this->assertEquals([[$published[10]], 0], [$data->cReview->getIds(), $data->more], 'la suite reprend à l\'offset, dernier lot');
		$this->assertEquals('', new ReviewUi()->getMore($data->eCourse, $data->offset + $data->cReview->count(), $data->more), 'le dernier lot retire le bouton');

		// La réponse telle que le navigateur la reçoit : le client n'insère qu'un nœud racine par
		// instruction (`Element::renderAdjacentHTML()`), il faut donc un avis par instruction.
		$instructions = $this->renderMore($eCourse, ReviewLib::PER_PAGE - 1);

		$this->assertEquals([
			['#learning-reviews-thread', 'insertAdjacentHtml', 'learning-review-'.$published[9]],
			['#learning-reviews-thread', 'insertAdjacentHtml', 'learning-review-'.$published[10]],
			['#learning-reviews-more', 'outerHtml', NULL]
		], $instructions, 'un avis par instruction, un seul nœud racine chacune, puis le bouton retiré');

		$data = $this->executeView('learning/review:more', ['course' => $eCourse['id'], 'offset' => 4]);

		$this->assertEquals([array_slice($published, 4, ReviewLib::PER_PAGE), 0], [$data->cReview->getIds(), $data->more], 'un offset quelconque reprend au bon avis');
		$this->assertFalse(in_array($hidden, $data->cReview->getIds()), 'sans le masqué');

		$data = $this->executeView('learning/review:more', ['course' => $eCourse['id'], 'offset' => 0]);

		$this->assertEquals([array_slice($published, 0, ReviewLib::PER_PAGE), 1], [$data->cReview->getIds(), $data->more], 'depuis le début, un avis reste après la page');

		// L'équipe de Ouvretaferme : les douze, masqué compris, donc deux après la première page.
		$this->actAs($this->eUserAdmin);

		$data = $this->executeView('learning/review:more', ['course' => $eCourse['id'], 'offset' => ReviewLib::PER_PAGE]);

		$this->assertEquals([array_slice($reviews, ReviewLib::PER_PAGE), 0], [$data->cReview->getIds(), $data->more], 'l\'équipe lit la suite masqué compris');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit');

	}

	/**
	 * Les instructions de `review:more`, rendues par le cadriciel comme il les servirait : pour chacune,
	 * le sélecteur, la méthode et l'id de son seul nœud racine (`NULL` sans nœud). Un contenu à
	 * plusieurs racines rend « plusieurs racines », que le client refuserait.
	 *
	 * @return array<int, array{0: string, 1: string, 2: ?string}>
	 */
	private function renderMore(Course $eCourse, int $offset): array {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];
		$_GET = $_REQUEST = ['course' => $eCourse['id'], 'offset' => $offset];

		$action = NULL;

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'learning/review:more'), new \stdClass());
		} catch(\ViewAction $viewAction) {
			$action = $viewAction;
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

		if($action === NULL) {
			throw new \Exception('review:more ne lève pas de vue');
		}

		// Le registre des vues (`View::$views`) est indexé par le seul nom et lève sur un doublon : une
		// requête ne charge qu'un fichier de vue, un processus de tests en charge plusieurs, et le
		// `create` d'un autre fichier bloquerait celui-ci. Le fichier se rejoue donc sur un registre
		// vide, que `require_once` de `run()` trouve déjà posé, puis le registre est rendu tel quel.
		$views = new \ReflectionProperty(\View::class, 'views');
		$viewsInitial = $views->getValue();
		$views->setValue(NULL, []);

		// `ViewAction::run()` ouvre son propre tampon et ne le referme pas.
		$level = ob_get_level();
		ob_start();

		try {
			require \Package::getFileFromUri('learning/review', 'view');
			$action->run();
		} finally {
			$views->setValue(NULL, $viewsInitial);
		}

		$rendered = '';

		while(ob_get_level() > $level) {
			$rendered = ob_get_clean().$rendered;
		}

		$instructions = [];

		foreach(json_decode($rendered, TRUE)['__instructions'] as [$type, $selector, $calls]) {

			foreach($calls as [$method, $arguments]) {

				$html = end($arguments);

				// libxml ne connaît pas les balises SVG des icônes et lève un avertissement par balise.
				$errors = libxml_use_internal_errors(TRUE);

				$document = new \DOMDocument();
				$document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');

				libxml_clear_errors();
				libxml_use_internal_errors($errors);

				$roots = [];

				foreach($document->getElementsByTagName('body')->item(0)->childNodes as $node) {
					if($node instanceof \DOMElement) {
						$roots[] = $node->getAttribute('id');
					}
				}

				$instructions[] = [$selector, $method, match(count($roots)) {
					0 => NULL,
					1 => $roots[0],
					default => 'plusieurs racines'
				}];

			}

		}

		return $instructions;

	}

	/**
	 * Q12 : la note paraît sur une formation de l'équipe comme sur celle d'un réseau.
	 */
	public function testTeamCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$this->assertTrue($this->getCourse($eCourse['id'])->isInCatalog(), 'une formation publiée de l\'équipe est au catalogue');

		foreach([5, 4, 3] as $rating) {

			$eUser = $this->createPerson();

			$this->actAs($eUser);
			$this->followOnline($this->getCourse($eCourse['id']), $eUser, [$eLesson]);
			$this->actAs($this->eUserAdmin);

			$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, $rating), 'avis '.$rating);

		}

		$this->assertEquals([3, 4.0], $this->getSummary($eCourse), 'la moyenne de l\'équipe paraît au seuil');

		$this->assertFalse($this->getReview($eCourse, $eUser)->acceptReport(), 'une formation de l\'équipe n\'a pas de signalement');

	}

	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUserReviewed = $this->createPerson();
		$eUserWaiting = $this->createPerson();

		$this->followDate($eCourse, $eUserReviewed);
		$this->followDate($eCourse, $eUserWaiting);

		$this->writeReview($eCourse, $eUserReviewed, 4, 'Un texte <b>à échapper</b>.');

		$fiche = $this->renderFiche($eCourse, $eUserReviewed);

		$this->assertTrue(str_contains($fiche, 'id="avis"'), 'la section des avis');
		$this->assertTrue(str_contains($fiche, '/learning/review:update?id='), 'l\'auteur modifie son avis');
		$this->assertFalse(str_contains($fiche, 'Stagiaire'), 'jamais du nom entier, vignette comprise');
		$this->assertTrue(str_contains($fiche, 'Un texte &lt;b&gt;à échapper&lt;/b&gt;.'), 'le texte échappé');
		$this->assertEquals([1, NULL], $this->getSummary($eCourse), 'sous le seuil, pas de note moyenne');

		$this->assertTrue(str_contains($this->renderFiche($eCourse, $eUserWaiting), '/learning/review:create?course='), 'qui a suivi la formation donne son avis');
		$this->assertFalse(str_contains($this->renderFiche($eCourse, $this->eUserReader), '/learning/review:create?course='), 'qui ne l\'a pas suivie non');

	}

	/**
	 * Ce que la fiche garde et retire : un avis masqué ne paraît que pour qui écrit la formation, un
	 * avis sans compte signe de la saisie, un compte fermé garde sa signature, et hors du catalogue
	 * la fiche n'a plus de section alors que les lignes restent.
	 */
	public function testFicheStates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();

		$eUserHidden = $this->createPerson();
		$eUserClosed = $this->createPerson();

		$this->followDate($eCourse, $eUserHidden);
		$this->followDate($eCourse, $eUserClosed);

		$this->writeReview($eCourse, $eUserHidden, 1, 'zzTest avis masqué');
		$this->writeReview($eCourse, $eUserClosed, 4, 'zzTest compte fermé');

		$eReview = $this->getReview($eCourse, $eUserHidden);
		$eReview['hideReason'] = 'zzTest';
		ReviewLib::hide($eReview);

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.avis-fiche@example.org');
		$this->moveDate($eDate, -3);

		$this->actAs(new \user\User());
		$this->executePage('reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'].'/inscription/'.$eDateEnrollment['token'].'/avis/:doReview', ['rating' => '5', 'text' => 'zzTest sans compte']);
		$this->actAs($this->eUserAdmin);

		\user\DropLib::closeNow($eUserClosed);

		$ficheVisitor = $this->renderFiche($eCourse, $this->eUserReader);

		$this->assertFalse(str_contains($ficheVisitor, 'zzTest avis masqué'), 'l\'avis masqué ne paraît pas pour un lecteur');
		$this->assertEquals('Zztest P.', ReviewLib::getByDateEnrollment($eDateEnrollment)->getAuthorName(), 'l\'avis sans compte signe de la saisie');
		$this->assertEquals('zzTest S.', $this->getReview($eCourse, $eUserClosed)->getAuthorName(), 'le compte fermé garde sa signature');
		$this->assertTrue(str_contains($ficheVisitor, 'zzTest compte fermé'), 'et son avis paraît');

		$this->assertTrue(str_contains($this->renderFiche($eCourse, $this->eUserAdmin), 'zzTest avis masqué'), 'qui écrit la formation lit l\'avis masqué');

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['catalog'] = Course::PRIVATE;
		CourseLib::update($eCourse, ['catalog']);

		// L'équipe du réseau, qui seule lit encore la formation hors du catalogue : l'ouverture aux
		// membres ne donne pas la lecture.
		$this->assertFalse(str_contains($this->renderFiche($eCourse, $this->eUserAdmin), 'id="avis"'), 'hors du catalogue, la fiche n\'a plus de section');
		$this->assertEquals(3, Review::model()->whereCourse($eCourse)->count(), 'et les avis restent en base');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	private function renderFiche(Course $eCourse, \user\User $eUser): string {

		$this->actAs($eUser);

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($data instanceof \stdClass, 'la fiche '.$eCourse['id'].' se prépare');

		if(($data instanceof \stdClass) === FALSE) {
			return '';
		}

		$this->actAs($eUser);

		$html = new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

		$this->actAs($this->eUserAdmin);

		return $html;

	}

}
?>
