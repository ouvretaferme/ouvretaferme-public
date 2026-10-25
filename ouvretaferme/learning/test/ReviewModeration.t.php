<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Ce qui arrive à un avis après sa publication (lot L15c) : le réseau répond et signale, l'équipe de
 * Ouvretaferme garde, masque et réaffiche, l'auteur modifie et retire. Chaque geste passe par sa
 * route, pour que le droit et l'état soient ceux de la page.
 *
 * Masquer écrit à l'auteur, un compte fictif en `example.org` : aucun e-mail ne s'écrit en base.
 *
 * Lancement : dev/test package=learning file=ReviewModeration
 */
class ReviewModerationTest extends LearningReviewFixtureTest {

	public function testAnswer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eReview, $eUser] = $this->seedReview();

		$this->actAs($eUser);
		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage('learning/review:doAnswer', ['id' => $eReview['id'], 'answer' => 'Merci']), 'l\'auteur ne répond pas à son avis');

		// Un POST forgé par qui lit la formation sans l'écrire, le cas d'un autre réseau comme d'une
		// ferme : `Course::canWrite()` est faux pour les deux.
		$this->actAs($this->eUserReader);
		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage('learning/review:doAnswer', ['id' => $eReview['id'], 'answer' => 'Merci']), 'un lecteur ne répond pas');
		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => 'zzTest']), 'ni ne signale');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(['FailAction', 'Review::answer.empty'], $this->executePage('learning/review:doAnswer', ['id' => $eReview['id'], 'answer' => '']), 'une réponse vide');
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doAnswer', ['id' => $eReview['id'], 'answer' => 'Merci de votre retour.']), 'le réseau répond');

		$eReview = $this->getReviewById($eReview['id']);

		$this->assertEquals(['Merci de votre retour.', $this->eUserAdmin['id']], [$eReview['answer'], $eReview['answeredBy']['id']], 'la réponse et son auteur');

		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doDeleteAnswer', ['id' => $eReview['id']]), 'la réponse se retire');
		$this->assertEquals([NULL, NULL], [$this->getReviewById($eReview['id'])['answer'], $this->getReviewById($eReview['id'])['answeredAt']], 'sans laisser de trace');

	}

	public function testReportKeep(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eReview, $eUser] = $this->seedReview();

		$this->actAs($eUser);
		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => 'zzTest']), 'l\'auteur ne signale pas');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(['FailAction', 'Review::reportReason.empty'], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => '']), 'un signalement sans motif');
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => 'zzTest hors sujet']), 'le réseau signale');

		$eReview = $this->getReviewById($eReview['id']);

		$this->assertEquals([Review::REPORTED, 'zzTest hors sujet'], [$eReview['status'], $eReview['reportReason']], 'l\'avis est signalé, avec son motif');
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => 'zzTest encore']), 'un signalement par avis');

		$data = $this->executeView('learning/admin/avis', []);
		$this->assertTrue($data instanceof \stdClass and in_array($eReview['id'], $data->cReviewReported->getIds()), 'l\'avis attend l\'équipe');

		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/admin/review:doKeep', ['id' => $eReview['id']]), 'l\'équipe garde l\'avis');
		$this->assertEquals(Review::KEPT, $this->getReviewById($eReview['id'])['status'], 'gardé');
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage('learning/review:doReport', ['id' => $eReview['id'], 'reportReason' => 'zzTest encore']), 'un avis gardé ne se signale plus');

		$this->actAs($eUser);
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '2', 'text' => 'Réécrit.']), 'l\'auteur le réécrit');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(Review::PUBLISHED, $this->getReviewById($eReview['id'])['status'], 'réécrit, il redevient publié et se signale de nouveau');

	}

	public function testHideShow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eReview, $eUser] = $this->seedReview();

		// Hors de l'équipe, `validateAdmin()` lève une page désactivée et non une action.
		$this->actAs($eUser);

		try {
			$this->executePage('learning/admin/review:doHide', ['id' => $eReview['id'], 'hideReason' => 'zzTest']);
			$this->assertFail('l\'auteur masque son avis');
		} catch(\DisabledPage) {
			$this->assertTrue(TRUE, 'l\'auteur ne masque pas');
		}

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(['FailAction', 'Review::hideReason.empty'], $this->executePage('learning/admin/review:doHide', ['id' => $eReview['id'], 'hideReason' => '']), 'un masquage sans motif');

		$mails = ReviewLib::getMails($this->getReviewById($eReview['id']), 'hidden');

		$this->assertEquals([$eUser['email']], array_column($mails, 'to'), 'l\'e-mail de masquage va à l\'auteur');

		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/admin/review:doHide', ['id' => $eReview['id'], 'hideReason' => 'zzTest propos injurieux']), 'l\'équipe masque');

		$eReview = $this->getReviewById($eReview['id']);

		$this->assertEquals([Review::HIDDEN, 'zzTest propos injurieux', $this->eUserAdmin['id']], [$eReview['status'], $eReview['hideReason'], $eReview['moderatedBy']['id']], 'masqué, avec le motif et qui l\'a masqué');
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage('learning/admin/review:doHide', ['id' => $eReview['id'], 'hideReason' => 'zzTest']), 'un avis masqué ne se masque pas deux fois');
		$this->assertEquals([0, NULL], $this->getSummary($eReview['course']), 'il ne se compte plus');

		$this->actAs($eUser);
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage('learning/review:doUpdate', ['id' => $eReview['id'], 'rating' => '5', 'text' => '']), 'l\'auteur ne modifie plus un avis masqué');
		$this->assertEquals(['NotExpectedAction', NULL], $this->executePage('learning/review:doCreate', ['course' => $eReview['course']['id'], 'rating' => '5', 'text' => 'zzTest réécrit']), 'ni par un second envoi du formulaire de création');
		$this->assertEquals([4, 'zzTest avis'], [$this->getReviewById($eReview['id'])['rating'], $this->getReviewById($eReview['id'])['text']], 'l\'avis masqué reste tel quel');
		$this->assertEquals(\stdClass::class, get_debug_type($this->executeView('learning/review:update', ['id' => $eReview['id']])), 'mais le relit');
		$this->actAs($this->eUserAdmin);

		// Un « Garder » affiché avant le masquage et cliqué après : l'élément lu signalé ne réécrit pas
		// l'état que la ligne a pris entre-temps.
		$eReviewStale = $this->getReviewById($eReview['id']);
		$eReviewStale['status'] = Review::REPORTED;
		ReviewLib::keep($eReviewStale);

		$this->assertEquals(Review::HIDDEN, $this->getReviewById($eReview['id'])['status'], 'un « Garder » périmé ne réaffiche pas l\'avis masqué');

		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/admin/review:doShow', ['id' => $eReview['id']]), 'l\'équipe le réaffiche');
		$this->assertEquals(Review::KEPT, $this->getReviewById($eReview['id'])['status'], 'réaffiché, il est gardé');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eReview, $eUser] = $this->seedReview();

		$eReview['hideReason'] = 'zzTest';
		ReviewLib::hide($eReview);

		$this->assertEquals(['NotAllowedAction', NULL], $this->executePage('learning/review:doDelete', ['id' => $eReview['id']]), 'qui écrit la formation ne retire pas un avis');

		$this->actAs($eUser);
		$this->assertEquals(['ReloadAction', NULL], $this->executePage('learning/review:doDelete', ['id' => $eReview['id']]), 'l\'auteur retire son avis, masqué compris');
		$this->actAs($this->eUserAdmin);

		$this->assertTrue($this->getReviewById($eReview['id'])->empty(), 'retiré');

	}

	/**
	 * La notification du réseau se décrit au passage de la file, par ce que l'avis est devenu : un avis
	 * masqué entre-temps n'est pas annoncé.
	 */
	public function testNotification(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eReview] = $this->seedReview();

		$eNotification = NotificationLib::describe(NotificationLib::REVIEW, $this->eStructureNetwork, $eReview['id']);

		$this->assertEquals(['author' => 'zzTest S.', 'rating' => 4, 'course' => $eReview['course']['name'], 'courseId' => $eReview['course']['id']], $eNotification['content'], 'l\'auteur signé, la note et la formation');
		$this->assertTrue(NotificationLib::describe(NotificationLib::REVIEW, $this->eStructureFarm, $eReview['id'])->empty(), 'rien pour une autre structure');

		$eReview['hideReason'] = 'zzTest';
		ReviewLib::hide($eReview);

		$this->assertTrue(NotificationLib::describe(NotificationLib::REVIEW, $this->eStructureNetwork, $eReview['id'])->empty(), 'rien pour un avis masqué');

	}

	/**
	 * Un avis publié par une personne de la ferme, sur la formation du réseau que le compte 1 écrit.
	 *
	 * @return array{0: Review, 1: \user\User}
	 */
	private function seedReview(): array {

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$this->followDate($eCourse, $eUser);

		$this->assertEquals(['ReloadAction', NULL], $this->writeReview($eCourse, $eUser, 4, 'zzTest avis'), 'avis publié');

		return [$this->getReview($eCourse, $eUser), $eUser];

	}

}
?>
