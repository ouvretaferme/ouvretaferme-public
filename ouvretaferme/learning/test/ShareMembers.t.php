<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * L'ouverture d'une formation de réseau à ses membres : ce que le panneau propose, ce que l'écriture
 * aligne entre « Tous les membres du réseau » et « Personne pour l'instant », et ce qu'un membre en
 * tire. L'ouverture ne donne aucune lecture : elle prévient et propose, l'inscription ouvre.
 *
 * Un membre est un compte IN du réseau hors de son équipe (rôle « Producteur », `member`) ; le
 * conseiller du décor (l'utilisateur 1) est de l'équipe et ne compte pas.
 *
 * Le décor, et ce qu'il attend de la base, sont dans `LearningNetworkFixture.t.php`.
 */
class ShareMembersTest extends LearningNetworkFixtureTest {

	/**
	 * Seule une formation de réseau publiée s'ouvre, comme celle d'un groupe de formation.
	 */
	public function testAcceptShare(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->assertFalse($this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE)->acceptShare(), 'brouillon du réseau');
		$this->assertTrue($this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE)->acceptShare(), 'formation publiée du réseau');

	}

	/**
	 * Un réseau dont seule l'équipe est membre n'a personne à qui ouvrir la formation : ni « Ouvrir
	 * aux membres » dans l'en-tête de l'éditeur, ni lien sur « Pas encore ouverte aux membres ». Le
	 * panneau ouvert par son adresse n'a rien à enregistrer. Un
	 * membre hors de l'équipe rend le bouton et le lien. Une fois ouverte, le bouton s'efface et
	 * « Ouverte à tous les membres » devient le lien du panneau.
	 */
	public function testShareAudience(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->leaveMembers();

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals(0, ShareLib::countMembers($eCourse), 'l\'équipe seule ne compte pas');

		$share = '<a href="/learning/course:share?id='.$eCourse['id'].'"';

		$head = $this->getEditionHeadNetwork($eCourse);

		$this->assertFalse(str_contains($head, $share), 'sans membre, rien ne mène au panneau : '.$head);

		$data = $this->executeView('learning/course:share', ['id' => $eCourse['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le panneau ouvert par son adresse se prépare ('.(is_string($data) ? $data : 'vue').')');

		$panel = new ShareUi()->shareNetwork($data->e, $data->cChapter, $data->eShare);

		$this->assertFalse(str_contains($panel->body, 'type="submit"'), 'il n\'a rien à enregistrer');

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$this->assertEquals(1, ShareLib::countMembers($eCourse), 'un membre hors de l\'équipe');

		$head = $this->getEditionHeadNetwork($eCourse);

		$this->assertEquals(2, substr_count($head, $share), 'un membre : « Ouvrir aux membres » dans l\'en-tête, et « Pas encore ouverte aux membres » mène au panneau');

		$data = $this->executeView('learning/course:share', ['id' => $eCourse['id']]);
		$panel = new ShareUi()->shareNetwork($data->e, $data->cChapter, $data->eShare);

		$this->assertTrue(str_contains($panel->body, 'type="submit"'), 'le panneau s\'enregistre');

		$this->assertEquals([], $this->shareNetwork($eCourse), 'ouverte aux membres');

		$head = $this->getEditionHeadNetwork($eCourse);

		$this->assertEquals(1, substr_count($head, $share), 'ouverte : plus de bouton « Ouvrir aux membres », qui proposerait ce qui est fait, et « Ouverte à tous les membres » mène seul au panneau : '.$head);

		$data = $this->executeView('learning/course:share', ['id' => $eCourse['id']]);
		$this->assertTrue($data instanceof \stdClass, 'le panneau rouvert se prépare ('.(is_string($data) ? $data : 'vue').')');

		$panel = new ShareUi()->shareNetwork($data->e, $data->cChapter, $data->eShare);

		$this->assertTrue(str_contains($panel->body, 'type="submit"'), 'rouvert, il s\'enregistre encore');

	}

	/**
	 * `doShare` est gardé comme les écrans qui offrent l'ouverture : un réseau sans membre hors de
	 * son équipe n'ouvre rien, même par un envoi écrit à la main ; dès qu'il en a un, le même envoi
	 * ouvre la formation.
	 */
	public function testDoShareRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->leaveMembers();

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$post = ['id' => (string)$eCourse['id'], 'audience' => Share::MEMBERS];

		$this->assertEquals(\NotExpectedAction::class, $this->executeView('learning/course:doShare', $post, 'POST'), 'aucun membre hors de l\'équipe : refusé');
		$this->assertFalse(ShareLib::getMembersByCourse($eCourse)->exists(), 'et rien n\'est écrit');

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$this->assertEquals(\ReloadAction::class, $this->executeView('learning/course:doShare', $post, 'POST'), 'un membre : l\'envoi passe');
		$this->assertTrue(ShareLib::getMembersByCourse($eCourse)->exists(), 'et ouvre la formation');

	}

	private function getEditionHeadNetwork(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);

		if(is_string($data)) {
			throw new \Exception('l\'éditeur de la formation '.$eCourse['id'].' a levé '.$data);
		}

		return new CourseUi()->getEditionHead($data->eCourse);

	}

	/**
	 * Le compte des membres écarte l'équipe, les comptes sortis du réseau et ceux d'un réseau fermé.
	 */
	public function testCountMembers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->leaveMembers();

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');
		$this->assertEquals(1, ShareLib::countMembers($eCourse), 'le lecteur, membre');

		$this->leave($this->eUserReader, $this->eStructureNetwork);
		$this->assertEquals(0, ShareLib::countMembers($eCourse), 'sorti du réseau, il ne compte plus');

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$eStructureNetwork = clone $this->eStructureNetwork;
		$eStructureNetwork['status'] = \structure\Structure::CLOSED;

		\structure\StructureLib::update($eStructureNetwork, ['status']);

		$this->assertEquals(0, ShareLib::countMembers($eCourse), 'un réseau fermé n\'a plus de membre à prévenir');

	}

	/**
	 * Le panneau d'une formation ouverte à personne propose les membres ; une fois ouverte, il
	 * reprend l'ouverture enregistrée.
	 */
	public function testPanelDefault(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$eShare = ShareLib::getForNetworkPanel($eCourse);

		$this->assertFalse($eShare->exists(), 'une ouverture encore à créer');
		$this->assertEquals(Share::MEMBERS, $eShare['audience'], 'aux membres');
		$this->assertEquals(NULL, $eShare['dueAt'], 'sans échéance');

		$this->assertEquals([], $this->shareNetwork($eCourse), 'ouverte aux membres');

		$eShare = ShareLib::getForNetworkPanel($eCourse);

		$this->assertTrue($eShare->exists() and $eShare['audience'] === Share::MEMBERS, 'le panneau reprend l\'ouverture');
		$this->assertEquals('members', $eShare->getKey(), 'sous sa clé');

	}

	/**
	 * « Personne pour l'instant » retire l'ouverture ; rouvrir n'écrit qu'une ligne.
	 */
	public function testNobody(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->assertEquals([], $this->shareNetwork($eCourse), 'ouverte aux membres');
		$this->assertTrue(ShareLib::getMembersByCourse($eCourse)->exists(), 'une ligne');

		$this->assertEquals([], $this->shareNetwork($eCourse, members: FALSE), 'personne pour l\'instant');
		$this->assertFalse(ShareLib::getMembersByCourse($eCourse)->exists(), 'la ligne est retirée');

		$this->shareNetwork($eCourse);
		$this->shareNetwork($eCourse);

		$this->assertEquals(1, Share::model()->whereCourse($eCourse)->count(), 'deux enregistrements, une seule ligne');

	}

	/**
	 * Le panneau d'un réseau ne prend que les membres, ou personne : une promotion postée est
	 * refusée, une audience absente aussi.
	 */
	public function testAudienceRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		foreach([
			'une promotion' => [['audience' => Share::SECTION], [['Share::audience.chosen', 'audience']]],
			'une audience absente' => [[], [['Share::audience.check', 'audience']]],
			'une audience forgée' => [['audience' => 'affiliates'], [['Share::audience.check', 'audience']]],
		] as $case => [$input, $fails]) {

			$fw = new \FailWatch();

			ShareLib::buildFromInput($eCourse, $input);

			$this->assertEquals($fails, $this->getFails($fw), $case.' : refusée');

		}

		$this->assertFalse(ShareLib::getMembersByCourse($eCourse)->exists(), 'rien n\'est écrit');

	}

	/**
	 * L'ouverture aux membres ne donne aucune lecture : le membre ne lit pas la formation tant qu'il
	 * n'est pas inscrit, et aucune fenêtre ne s'ouvre pour lui.
	 */
	public function testMembersOpenNoReading(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$this->assertEquals([], $this->shareNetwork($eCourse), 'ouverte aux membres');

		$this->actAs($this->eUserReader);

		$this->assertTrue(ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse)->empty(), 'aucune fenêtre');
		$this->assertFalse($this->getCourse($eCourse['id'])->canRead(), 'le membre ne lit pas sans inscription');

		$cShare = ShareLib::getByUser($this->eUserReader)->find(fn(Share $eShare) => $eShare['course']['id'] === $eCourse['id']);

		$this->assertEquals(1, $cShare->count(), 'la formation lui est proposée');
		$this->assertEquals('community-'.self::COMMUNITY_NETWORK, $cShare->first()['key'], 'sous le réseau');
		$this->assertTrue($cShare->first()['section']->empty(), 'sans promotion');

		$this->enrollReader($eCourse);

		$this->assertTrue($this->getCourse($eCourse['id'])->canRead(), 'inscrit, il lit');

	}

	/**
	 * « Perdent l'accès » (Vincent, 11/10) : qui lisait par un partage aux fermes ne lit plus,
	 * s'inscrit, et retrouve son avancement. Dans l'ordre de la décision : l'avancement lu avant, le
	 * partage retiré, plus de lecture, l'inscription, l'avancement intact.
	 */
	public function testLoseAccessKeepProgress(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLesson1 = $this->createLessonWritten($eChapter, 'zzTest leçon 1', 8);
		$eLesson2 = $this->createLessonWritten($eChapter, 'zzTest leçon 2', 12);
		$this->createLessonWritten($eChapter, 'zzTest leçon 3', 5);
		$eCourse = $this->getCourse($eCourse['id']);

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		// Ce que le lecteur a suivi du temps où le partage aux fermes lui ouvrait la lecture : deux
		// leçons faites. « J'ai suivi cette leçon » (`ProgressLib::done()`) ne s'écrit plus que sur
		// une inscription, et c'est justement l'avancement d'avant l'inscription que le test éprouve :
		// les lignes sont donc écrites comme `done()` les écrit, sans lui.
		foreach([$eLesson1, $eLesson2] as $eLesson) {
			// Sans `build()`, qui n'aurait jugé que les bornes du schéma (statut, source, score) : aucun
			// écran ne poste un avancement, que le produit construit toujours lui-même, comme ici.
			ProgressLib::create(new Progress([
				'user' => $this->eUserReader,
				'course' => $eCourse,
				'lesson' => $eLesson,
				'status' => Progress::DONE,
				'source' => Progress::DECLARED,
				'doneAt' => new \Sql('NOW()'),
				'updatedAt' => new \Sql('NOW()'),
			]));
		}

		$this->assertEquals([2, 20], $this->readDone($eCourse), '1. l\'avancement lu avant : deux leçons, vingt minutes');

		$this->assertEquals([], $this->shareNetwork($eCourse), '2. le partage aux fermes repris en ouverture aux membres');
		$this->assertTrue(Share::model()->whereCourse($eCourse)->whereAudience('!=', Share::MEMBERS)->count() === 0, 'plus aucun partage qui ouvre la lecture');

		$this->actAs($this->eUserReader);

		$this->assertTrue(ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse)->empty(), '3. plus de fenêtre');
		$this->assertFalse($this->getCourse($eCourse['id'])->canRead(), '3. plus de lecture');

		$this->enrollReader($eCourse);

		$this->assertTrue(EnrollmentLib::isEnrolled($this->eUserReader, $eCourse), '4. l\'inscription');
		$this->assertTrue($this->getCourse($eCourse['id'])->canRead(), '4. qui rend la lecture');

		$this->assertEquals([2, 20], $this->readDone($eCourse), '5. l\'avancement intact');

	}

	private function readDone(Course $eCourse): array {

		$cCourse = new \Collection([$eCourse['id'] => $this->getCourse($eCourse['id'])]);

		ProgressLib::fillDone($this->eUserReader, $cCourse);

		return [$cCourse[$eCourse['id']]['done'], $cCourse[$eCourse['id']]['doneMinutes']];

	}

	/**
	 * Une formation de réseau publiée enchaîne sur le panneau d'ouverture dès que le réseau a un
	 * membre à prévenir ; la dépublier, ou publier celle d'un groupe de formation, recharge l'écran.
	 */
	public function testPublishThenShare(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->join($this->eUserReader, $this->eStructureNetwork, 'member');

		$eCourse = $this->setFree($this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE));
		$this->createLessonWritten($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$data = $this->executeView('learning/course:doUpdateStatus', ['id' => (string)$eCourse['id'], 'status' => Course::PUBLISHED], 'POST');

		$this->assertTrue($data instanceof \stdClass, 'publiée : une vue, qui ouvre le panneau ('.(is_string($data) ? $data : 'vue').')');
		$this->assertEquals(Course::PUBLISHED, $this->getCourse($eCourse['id'])['status'], 'et la formation est publiée');

		$this->assertEquals(\ReloadAction::class, $this->executeView('learning/course:doUpdateStatus', ['id' => (string)$eCourse['id'], 'status' => Course::DRAFT], 'POST'), 'dépubliée : l\'écran se recharge');

		$this->assertEquals([], $this->shareNetwork($this->getCourse($eCourse['id'])), 'ouverte pendant qu\'elle était publiée');

		$this->assertEquals(\ReloadAction::class, $this->executeView('learning/course:doUpdateStatus', ['id' => (string)$eCourse['id'], 'status' => Course::PUBLISHED], 'POST'), 'republiée déjà ouverte : l\'écran se recharge, sans rouvrir le panneau');

		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);
		$this->createLessonWritten($this->createChapter($eCourseEducation, 'zzTest module'), 'zzTest leçon', 10);

		$this->assertEquals(\ReloadAction::class, $this->executeView('learning/course:doUpdateStatus', ['id' => (string)$eCourseEducation['id'], 'status' => Course::PUBLISHED], 'POST'), 'un groupe de formation : l\'écran se recharge');

	}

	/**
	 * Les cartes d'un réseau disent si la formation est ouverte aux membres, et un brouillon n'en
	 * porte aucune.
	 */
	public function testFillShares(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eCourseDraft = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE);

		$this->shareNetwork($eCourse);

		$cCourse = new \Collection([$eCourse['id'] => $eCourse, $eCourseDraft['id'] => $eCourseDraft]);

		ShareLib::fillShares($cCourse);

		$this->assertEquals(1, $eCourse['cShare']->count(), 'une ligne sur la formation ouverte');
		$this->assertEquals(Share::MEMBERS, $eCourse['cShare']->first()['audience'], 'aux membres');
		$this->assertTrue($eCourseDraft['cShare']->empty(), 'rien sur le brouillon');

		$this->shareNetwork($eCourse, members: FALSE);

		ShareLib::fillShares($cCourse);

		$this->assertTrue($eCourse['cShare']->empty(), 'plus rien une fois retirée');

	}

	/**
	 * Le réseau du décor ne garde que son équipe, dans la transaction du test : chaque membre IN hors
	 * de l'équipe sort, comme le ferait un départ.
	 */
	private function leaveMembers(): void {

		$cMember = \structure\Member::model()
			->select(['user', 'role' => ['fqn']])
			->whereStructure($this->eStructureNetwork)
			->whereStatus(\structure\Member::IN)
			->getCollection();

		foreach($cMember as $eMember) {

			if(in_array($eMember['role']['fqn'], \structure\StructureSetting::ROLE_FQNS, TRUE) === FALSE) {
				$this->leave($eMember['user'], $this->eStructureNetwork);
			}

		}

	}

}
?>
