<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * La boite des avis d'un réseau (choix 4 A du lot glacons-parcours) : les avis donnés sur ses
 * formations, au catalogue public ou non, les non lus en tête. Le premier membre de l'équipe qui
 * en marque un lu le marque pour tous. Tant que le catalogue public est fermé, un avis neuf ne
 * part ni en notification ni en e-mail : seul le compteur de la boite le dit.
 *
 * Lancement : dev/test package=learning file=ReviewInbox
 */
class ReviewInboxTest extends LearningReviewFixtureTest {

	public function testInbox(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUserFirst = $this->createPerson();
		$eUserSecond = $this->createPerson();

		$this->followDate($eCourse, $eUserFirst);
		$this->followDate($eCourse, $eUserSecond);

		$this->writeReview($eCourse, $eUserFirst, 4, 'zzTest premier avis');

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['catalog'] = Course::PRIVATE;
		CourseLib::update($eCourse, ['catalog']);

		$this->writeReview($eCourse, $eUserSecond, 5, 'zzTest avis hors catalogue');

		$this->assertEquals(2, ReviewLib::countNewByStructure($this->eStructureNetwork), 'deux avis non lus, au catalogue ou non');

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations/avis', []);

		$this->assertTrue($data instanceof \stdClass, 'l\'équipe ouvre la boite : '.(is_string($data) ? $data : 'vue'));
		$this->assertEquals(2, $data->cReviewNew->count(), 'les deux en tête');
		$this->assertTrue($data->cReviewRead->empty(), 'aucun lu');

		$page = new ReviewUi()->getInbox($data->eStructure, $data->cReviewNew, $data->cReviewRead, $data->older);

		$this->assertTrue(in_array('zzTest avis hors catalogue', $data->cReviewNew->getColumn('text')), 'l\'avis hors catalogue s\'y lit');
		$this->assertTrue(str_contains($page, 'data-ajax="/learning/review:doRead"'), 'à marquer comme lu');
		$this->assertTrue(str_contains($page, '/learning/review:answer?id='), 'et à quoi répondre');

		$title = new ReviewUi()->getInboxTitle($data->eStructure, $data->reviewsNew);

		$this->assertTrue(str_contains($title, 'data-ajax="/learning/community:doReadAllReviews"'), 'le titre propose de tout marquer comme lu');

		// `doRead` ne redessine que ces deux nœuds : sans leur id, la boite ne se met plus à jour.
		$this->assertTrue(str_contains($title, 'id="learning-review-inbox-counter"'), 'le compteur se désigne');
		$this->assertTrue(str_contains($title, 'id="learning-review-inbox-read-all"'), 'le geste se désigne');

		$eNotification = new \notification\Notification([
			'type' => NotificationLib::REVIEW,
			'eStructure' => $this->eStructureNetwork,
			'content' => ['courseId' => $eCourse['id']]
		]);

		$this->assertEquals(ReviewUi::urlInbox($this->eStructureNetwork), NotificationLib::getUrl($eNotification), 'la notification d\'un avis mène à la boite');

		$eReview = $this->getReview($eCourse, $eUserSecond);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/review:doRead', ['id' => $eReview['id']]), 'l\'équipe le marque lu');

		$eReview = $this->getReviewById($eReview['id']);

		$this->assertEquals($this->eUserAdmin['id'], $eReview['readBy']['id'], 'lu par qui l\'a marqué');
		$this->assertEquals(1, ReviewLib::countNewByStructure($this->eStructureNetwork), 'un seul reste à lire');
		$this->assertFalse(str_contains(new ReviewUi()->getInboxRow($eReview), 'review:doRead'), 'lu, la ligne n\'est plus à marquer');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/community:doReadAllReviews', ['id' => $this->eStructureNetwork['id']]), 'tout marquer comme lu');
		$this->assertEquals(0, ReviewLib::countNewByStructure($this->eStructureNetwork), 'plus rien à lire');

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations/avis', []), 'un membre qui n\'est pas de l\'équipe n\'ouvre pas la boite');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/review:doRead', ['id' => $eReview['id']]), 'ni ne marque un avis lu');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Un avis lu que son auteur réécrit repasse en non lu, le réseau n'ayant pas lu le nouveau ; le
	 * renvoyer tel quel ne change rien (décision de V-Learning du 07/10).
	 */
	public function testUpdateUnread(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();

		$this->followDate($eCourse, $eUser);
		$this->writeReview($eCourse, $eUser, 4, 'zzTest avis lu puis réécrit');

		$eReview = $this->getReview($eCourse, $eUser);

		$this->executePage('learning/review:doRead', ['id' => $eReview['id']]);

		$this->assertEquals(0, ReviewLib::countNewByStructure($this->eStructureNetwork), 'lu');

		$this->actAs($eUser);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '4', 'text' => 'zzTest avis lu puis réécrit']), 'renvoyé tel quel');
		$this->assertEquals(0, ReviewLib::countNewByStructure($this->eStructureNetwork), 'toujours lu');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '2', 'text' => 'zzTest avis lu puis réécrit']), 'la note changée');

		$this->actAs($this->eUserAdmin);

		$eReview = $this->getReviewById($eReview['id']);

		$this->assertEquals([NULL, TRUE], [$eReview['readAt'], $eReview['readBy']->empty()], 'non lu, sans lecteur');
		$this->assertEquals(1, ReviewLib::countNewByStructure($this->eStructureNetwork), 'de nouveau à lire');

		$this->executePage('learning/review:doRead', ['id' => $eReview['id']]);

		$this->actAs($eUser);
		$this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '2', 'text' => 'zzTest avis réécrit une seconde fois']);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(1, ReviewLib::countNewByStructure($this->eStructureNetwork), 'le texte changé aussi');

	}

	/**
	 * Le compte des avis non lus, lu à chaque affichage des formations du réseau, passe par l'index
	 * `(course, readAt)` et non par toute la table.
	 */
	public function testIndex(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$sql = (string)new ReviewModel()
			->select('id')
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($this->eStructureNetwork)->getSql('t'))
			->whereReadAt(NULL)
			->getSql();

		$rows = Review::model()->pdo()->query('EXPLAIN '.mb_substr($sql, 1, -1))->fetchAll(\PDO::FETCH_ASSOC);

		$this->assertTrue(in_array('learningReview_index_2', explode(',', implode(',', array_column($rows, 'possible_keys')))), 'l\'index (course, readAt) sert la requête : '.json_encode($rows));

	}

	/**
	 * Catalogue public fermé, un avis neuf ne met rien en file et n'écrit aucun e-mail ; ouvert, la
	 * notification du réseau part.
	 */
	public function testClosedCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$enabledInitial = \notification\NotificationSetting::$enabled;
		\notification\NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

		[$eCourse] = $this->seedCatalog();

		$eUserClosed = $this->createPerson();
		$eUserOpen = $this->createPerson();

		$this->followDate($eCourse, $eUserClosed);
		$this->followDate($eCourse, $eUserOpen);

		$activeInitial = LearningSetting::$active;

		try {

			LearningSetting::$active = FALSE;
			$eReviewClosed = $this->createReview($eCourse, $eUserClosed);

			LearningSetting::$active = TRUE;
			$eReviewOpen = $this->createReview($eCourse, $eUserOpen);

		} finally {
			LearningSetting::$active = $activeInitial;
			\notification\NotificationSetting::$enabled = $enabledInitial;
		}

		$this->assertEquals(0, $this->countTasks($eReviewClosed), 'catalogue fermé, aucune notification');
		$this->assertEquals(1, $this->countTasks($eReviewOpen), 'catalogue ouvert, la notification part');
		$this->assertEquals(2, ReviewLib::countNewByStructure($this->eStructureNetwork), 'le compteur de la boite compte les deux');
		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/** L'avis écrit par le Lib, la page refusant tout réseau hors de la liste quand l'interrupteur est éteint. */
	private function createReview(Course $eCourse, \user\User $eUser): Review {

		$this->actAs($eUser);

		$eReview = ReviewLib::getCreateElement($this->getCourse($eCourse['id']), $eUser);
		$eReview['rating'] = 4;
		$eReview['text'] = NULL;

		ReviewLib::create($eReview);

		$this->actAs($this->eUserAdmin);

		return $eReview;

	}

	private function countTasks(Review $eReview): int {

		return \queue\Task::model()
			->whereAction(\notification\NotificationLib::getAction(NotificationLib::REVIEW))
			->whereElement($eReview['id'])
			->count();

	}

}
?>
