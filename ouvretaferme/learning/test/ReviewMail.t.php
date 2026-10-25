<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Les e-mails des avis (lot L15c), lus sans être envoyés : l'invitation du lendemain de la date et
 * l'avis masqué. `getReviewableAmong()` est borné aux lignes du test, jamais `getReviewable()` qui
 * balaie toute la base, et le cron `invite` n'est jamais lancé.
 *
 * Toutes les adresses sont en `example.org`, que `MailSetting::$devSendOnly` ne porte pas : chaque
 * méthode qui traverse `invite()` ou `hide()` affirme que `MAX(mailEmail.id)` n'a pas bougé.
 *
 * Lancement : dev/test package=learning file=ReviewMail
 */
class ReviewMailTest extends LearningReviewFixtureTest {

	/**
	 * Qui l'invitation retient : une place confirmée d'une date planifiée finie hier ou avant-hier,
	 * d'une formation au catalogue, qui n'a pas encore d'avis et a une adresse où écrire.
	 */
	public function testReviewable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();
		[$eCoursePrivate] = $this->seedCatalog(first: FALSE);

		$lines = [
			'hier' => $this->enrollOn($eCourse, -1, 'hier'),
			'avant-hier' => $this->enrollOn($eCourse, -2, 'avant-hier'),
			'aujourd\'hui' => $this->enrollOn($eCourse, 0, 'aujourdhui'),
			'il y a trois jours' => $this->enrollOn($eCourse, -3, 'trois-jours'),
			'date annulée' => $this->enrollOn($eCourse, -1, 'date-annulee'),
			'formation hors catalogue' => $this->enrollOn($eCoursePrivate, -1, 'hors-catalogue'),
			'avis déjà donné' => $this->enrollOn($eCourse, -1, 'avis-donne'),
		];

		// La date annulée par son statut seul : `DateLib::cancel()` annulerait aussi la place, et c'est
		// la condition sur la date qu'on éprouve ici.
		$eDate = DateLib::getById($lines['date annulée']['date']['id']);
		$eDate['status'] = Date::CANCELED;
		DateLib::update($eDate, ['status']);

		$eCoursePrivate = $this->getCourse($eCoursePrivate['id']);
		$eCoursePrivate['catalog'] = Course::PRIVATE;
		CourseLib::update($eCoursePrivate, ['catalog']);

		$this->actAs(new \user\User());
		$this->executePage($this->getPath().'/inscription/'.$lines['avis déjà donné']['token'].'/avis/:doReview', ['rating' => '4']);
		$this->actAs($this->eUserAdmin);

		$eUserClosed = $this->createPerson();
		$eDateClosed = $this->createDate($eCourse, 10);
		$lines['compte fermé'] = $this->enrollConfirmed($eDateClosed, $eUserClosed);
		$this->moveDate($eDateClosed, -1);
		\user\DropLib::closeNow($eUserClosed);

		// Un compte de l'équipe du réseau ne note pas la formation du réseau : l'invitation le mènerait
		// à une page qui refuse.
		$eUserStaff = $this->createPerson();
		$this->join($eUserStaff, $this->eStructureNetwork);
		$eDateStaff = $this->createDate($eCourse, 10);
		$eDateEnrollmentStaff = $this->enrollPublicConfirmed($eDateStaff, $eUserStaff['email']);
		DateEnrollmentLib::attachByUser($eUserStaff);
		$this->moveDate($eDateStaff, -1);
		$lines['compte de l\'équipe du réseau'] = $this->getLine($eDateEnrollmentStaff['id']);

		$cDateEnrollment = new \Collection();

		foreach($lines as $eDateEnrollment) {
			$cDateEnrollment[$eDateEnrollment['id']] = $eDateEnrollment;
		}

		$this->assertEquals(
			[$lines['hier']['id'], $lines['avant-hier']['id']],
			DateEnrollmentLib::getReviewableAmong($cDateEnrollment)->getIds(),
			'hier et avant-hier seulement, écartés : '.implode(', ', array_keys(array_slice($lines, 2)))
		);

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * L'invitation part une fois, à la saisie de la ligne, avec le lien de l'avis ; le second passage
	 * n'écrit rien.
	 */
	public function testInvite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();

		$eDateEnrollment = $this->enrollOn($eCourse, -1, 'invitation');

		$mails = DateEnrollmentLib::getMails($this->getLine($eDateEnrollment['id']), 'reviewInvited');

		$this->assertEquals(['zztest.avis-invitation@example.org'], array_column($mails, 'to'), 'l\'invitation va à la saisie');
		$this->assertTrue(str_contains(implode(' ', $mails[0]['content']), '/inscription/'.$eDateEnrollment['token'].'/avis'), 'avec le lien de l\'avis');

		$cDateEnrollment = DateEnrollmentLib::getReviewableAmong(new \Collection([$eDateEnrollment['id'] => $eDateEnrollment]));

		$this->assertEquals(1, DateEnrollmentLib::invite($cDateEnrollment), 'une invitation');

		$mailedAt = $this->getLine($eDateEnrollment['id'])['reviewMailedAt'];

		$this->assertTrue($mailedAt !== NULL, 'datée sur la ligne');

		$this->setClock($this->day(0).' 13:00:00');

		$this->assertEquals(0, DateEnrollmentLib::invite($cDateEnrollment), 'rejouée sur la même collection, elle ne part pas');
		$this->assertEquals($mailedAt, $this->getLine($eDateEnrollment['id'])['reviewMailedAt'], 'et la date d\'envoi ne bouge pas');
		$this->assertEquals([], DateEnrollmentLib::getReviewableAmong(new \Collection([$eDateEnrollment['id'] => $eDateEnrollment]))->getIds(), 'la ligne n\'est plus retenue');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * L'e-mail de masquage va à l'adresse du compte, ou à la saisie de la ligne sans compte ; un compte
	 * fermé n'a plus d'adresse, rien ne part.
	 */
	public function testHiddenMail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCatalog();

		$eUser = $this->createPerson();
		$eUserClosed = $this->createPerson();

		$this->followDate($eCourse, $eUser);
		$this->followDate($eCourse, $eUserClosed);

		$this->writeReview($eCourse, $eUser, 2, 'zzTest compte');
		$this->writeReview($eCourse, $eUserClosed, 2, 'zzTest fermé');

		$eDateEnrollment = $this->enrollOn($eCourse, -3, 'masque');

		$this->actAs(new \user\User());
		$this->executePage($this->getPath().'/inscription/'.$eDateEnrollment['token'].'/avis/:doReview', ['rating' => '1']);
		$this->actAs($this->eUserAdmin);

		\user\DropLib::closeNow($eUserClosed);

		$reviews = [
			'compte' => $this->getReview($eCourse, $eUser),
			'ligne' => ReviewLib::getByDateEnrollment($eDateEnrollment),
			'fermé' => $this->getReview($eCourse, $eUserClosed),
		];

		foreach($reviews as $eReview) {
			$eReview['hideReason'] = 'zzTest propos injurieux';
			ReviewLib::hide($eReview);
		}

		$to = fn(Review $eReview) => array_column(ReviewLib::getMails($this->getReviewById($eReview['id']), 'hidden'), 'to');

		$this->assertEquals([$eUser['email']], $to($reviews['compte']), 'à l\'adresse du compte');
		$this->assertEquals(['zztest.avis-masque@example.org'], $to($reviews['ligne']), 'à la saisie de la ligne sans compte');
		$this->assertEquals([], $to($reviews['fermé']), 'rien pour un compte fermé');

		$this->assertTrue(str_contains(implode(' ', ReviewLib::getMails($this->getReviewById($reviews['compte']['id']), 'hidden')[0]['content']), 'zzTest propos injurieux'), 'avec le motif');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * Une ligne sans compte confirmée sur une date de la formation, portée au jour `$offset`.
	 */
	private function enrollOn(Course $eCourse, int $offset, string $label): DateEnrollment {

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollPublicConfirmed($eDate, 'zztest.avis-'.$label.'@example.org');
		$this->moveDate($eDate, $offset);

		return $this->getLine($eDateEnrollment['id']);

	}

	private function getPath(): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
	}

}
?>
