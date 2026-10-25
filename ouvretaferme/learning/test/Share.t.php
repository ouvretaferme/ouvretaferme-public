<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le partage d'une formation de groupe à ses promotions : qui partage quoi, ce que l'écriture
 * aligne, et ce que le stagiaire en tire, de l'annonce à l'échéance.
 *
 * Les promotions du décor sont créées dans la transaction du test, dans le groupe de formation de
 * l'utilisateur 1, et le lecteur fictif y est rangé : il n'est pas membre du groupe, la promotion
 * suffit à lui ouvrir la formation.
 */
class ShareTest extends LearningFixtureTest {

	/**
	 * Seule une formation de groupe publiée se partage : un brouillon partagé serait un état que
	 * personne ne voit, et une formation de l'équipe se lit déjà par tous.
	 */
	public function testAcceptShare(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$this->assertFalse($this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE)->acceptShare(), 'formation de l\'équipe');
		$this->assertFalse($this->createCourse($eCommunity, Course::DRAFT, Course::PRIVATE)->acceptShare(), 'brouillon du groupe');

		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		$this->assertTrue($eCourse->acceptShare(), 'formation publiée du groupe');
		$this->assertTrue($eCourse->canUpdate(), 'l\'équipe du groupe écrit le partage');

		$this->actAs($this->eUserReader);

		$this->assertFalse($this->getCourse($eCourse['id'])->canUpdate(), 'un stagiaire n\'écrit pas le partage');

	}

	/**
	 * Le panneau propose les promotions ouvertes du groupe, et une promotion close seulement si elle
	 * est déjà partagée. Celle d'un autre groupe n'y figure jamais, donc une case forgée à son id
	 * ne désigne rien.
	 */
	public function testSections(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		$eSectionOpen = $this->createSection($eCommunity, 'zzTest promotion ouverte', [$this->eUserReader]);
		$eSectionClosed = $this->createSection($eCommunity, 'zzTest promotion close', [], \structure\Section::CLOSED);
		$eSectionClosedLater = $this->createSection($eCommunity, 'zzTest promotion close ensuite', []);
		$eSectionForeign = $this->createSection($this->getForeignCommunity(), 'zzTest promotion ailleurs', [$this->eUserReader]);

		$cSection = ShareLib::getSections($eCourse);

		$this->assertTrue($cSection->offsetExists($eSectionOpen['id']), 'promotion ouverte proposée');
		$this->assertFalse($cSection->offsetExists($eSectionClosed['id']), 'promotion close sans partage, écartée');
		$this->assertFalse($cSection->offsetExists($eSectionForeign['id']), 'promotion d\'un autre groupe, jamais proposée');
		$this->assertEquals(1, $cSection[$eSectionOpen['id']]['learners'], 'le nombre de stagiaires');
		$this->assertFalse($cSection[$eSectionOpen['id']]['eShare']->exists(), 'un partage à créer');
		$this->assertEquals(\Clock::date(), $cSection[$eSectionOpen['id']]['eShare']['opensAt'], 'ouvert du jour');

		$this->share($eCourse, [
			$eSectionForeign['id'] => [\Clock::date(), NULL],
			$eSectionClosed['id'] => [\Clock::date(), NULL]
		]);

		$this->assertEquals(0, ShareLib::getByCourse($eCourse)->count(), 'cases forgées pour une promotion d\'un autre groupe et une promotion close : rien n\'est partagé');

		$this->share($eCourse, [$eSectionClosedLater['id'] => [\Clock::date(), NULL]]);

		$eSectionClosedLater['status'] = \structure\Section::CLOSED;
		\structure\SectionLib::update($eSectionClosedLater, ['status']);

		$cSection = ShareLib::getSections($eCourse);

		$this->assertTrue($cSection->offsetExists($eSectionClosedLater['id']), 'promotion close après son partage, proposée pour qu\'on le retire');
		$this->assertTrue($cSection[$eSectionClosedLater['id']]['eShare']->exists(), 'et elle porte son partage');
		$this->assertEquals(1, ShareLib::getByCourse($eCourse)->count(), 'clore une promotion garde son partage');

	}

	/**
	 * L'écriture aligne les partages sur le panneau : une case cochée crée, une case gardée met à
	 * jour ses dates, une case décochée retire.
	 */
	public function testSynchronize(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		$eSectionFirst = $this->createSection($eCommunity, 'zzTest promotion A', []);
		$eSectionSecond = $this->createSection($eCommunity, 'zzTest promotion B', []);

		$due = $this->day(30);

		$this->share($eCourse, [
			$eSectionFirst['id'] => [\Clock::date(), NULL],
			$eSectionSecond['id'] => [\Clock::date(), $due]
		]);

		$cShare = ShareLib::getByCourse($eCourse);

		$this->assertEquals([$eSectionFirst['id'], $eSectionSecond['id']], $this->sorted($cShare->getKeys()), 'deux partages créés');
		$this->assertEquals($due, $cShare[$eSectionSecond['id']]['dueAt'], 'l\'échéance enregistrée');

		$shareId = $cShare[$eSectionSecond['id']]['id'];

		$this->share($eCourse, [
			$eSectionSecond['id'] => [$this->day(2), NULL]
		]);

		$cShare = ShareLib::getByCourse($eCourse);

		$this->assertEquals([$eSectionSecond['id']], $cShare->getKeys(), 'la case décochée retire son partage');
		$this->assertEquals($shareId, $cShare[$eSectionSecond['id']]['id'], 'la case gardée garde sa ligne');
		$this->assertEquals($this->day(2), $cShare[$eSectionSecond['id']]['opensAt'], 'et prend ses nouvelles dates');
		$this->assertEquals(NULL, $cShare[$eSectionSecond['id']]['dueAt'], 'échéance retirée');

		$this->share($eCourse, []);

		$this->assertTrue(ShareLib::getByCourse($eCourse)->empty(), 'tout décocher retire tout');

	}

	/**
	 * Une échéance ne précède pas l'ouverture. Le refus porte l'index de la promotion dans son
	 * wrapper (`buildIndex()`), ce qui le rattache à sa cellule, et garde son nom pour le message.
	 */
	public function testDueAtConsistent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion', []);

		$fw = new \FailWatch();
		ShareLib::buildFromInput($eCourse, [
			'section' => [(string)$eSection['id']],
			'opensAt' => [$eSection['id'] => $this->day(10)],
			'dueAt' => [$eSection['id'] => $this->day(5)]
		]);

		$this->assertEquals([['Share::dueAt.consistent', 'dueAt['.$eSection['id'].']']], $this->getFails($fw), 'échéance avant l\'ouverture, rattachée à sa cellule');

		$fw = new \FailWatch();
		ShareLib::buildFromInput($eCourse, [
			'section' => [(string)$eSection['id']],
			'opensAt' => [$eSection['id'] => $this->day(10)],
			'dueAt' => [$eSection['id'] => $this->day(10)]
		]);

		$this->assertFalse($fw->ko(), 'échéance le jour de l\'ouverture');

		$fw = new \FailWatch();
		ShareLib::buildFromInput($eCourse, [
			'section' => [(string)$eSection['id']],
			'opensAt' => [$eSection['id'] => ''],
			'dueAt' => [$eSection['id'] => '']
		]);

		$this->assertEquals([['Share::opensAt.check', 'opensAt['.$eSection['id'].']']], $this->getFails($fw), 'sans ouverture, refusé ; sans échéance, accepté');

	}

	/**
	 * Annoncée, la formation ne se lit pas encore ; le jour venu, elle se lit. Après l'échéance, elle
	 * se relit sans plus rien se cocher, sauf pour qui l'écrit.
	 */
	public function testWindow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion', [$this->eUserReader]);

		$this->actAs($this->eUserReader);

		$this->assertFalse($this->getCourse($eCourse['id'])->canRead(), 'sans partage, la formation du groupe ne se lit pas');
		$this->assertTrue($this->getCourse($eCourse['id'])->canProgress(), 'sans fenêtre, rien à figer');

		$this->share($eCourse, [$eSection['id'] => [$this->day(3), NULL]]);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->canRead(), 'annoncée, pas encore lisible');
		$this->assertEquals(1, ShareLib::getByUser($this->eUserReader)->count(), 'mais listée dans « Mes formations »');

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), NULL]]);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseRead->canRead(), 'le jour de l\'ouverture, lisible');
		$this->assertTrue($eCourseRead->canProgress(), 'sans échéance, elle se coche');

		$this->share($eCourse, [$eSection['id'] => [$this->day(-10), \Clock::date()]]);

		$this->assertTrue($this->getCourse($eCourse['id'])->canProgress(), 'le jour de l\'échéance compte encore');

		$this->share($eCourse, [$eSection['id'] => [$this->day(-10), $this->day(-1)]]);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourseRead->canRead(), 'échéance passée, elle se relit');
		$this->assertFalse($eCourseRead->canProgress(), 'mais ne se coche plus');

		// Hors de `build()`, comme `Progress.t.php` inscrit son lecteur : le test éprouve ce que la
		// fenêtre échue retire à une inscription, pas la demande qui y mène.
		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
		EnrollmentLib::create($eEnrollment);

		$this->assertFalse($eCourseRead->canFollow($eEnrollment), 'inscrit, le stagiaire ne coche plus rien, ni ne voit de lien vers une leçon vide');

		$this->actAs($this->eUserAdmin);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 5);
		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/lesson:doDone', ['id' => (string)$eLesson['id']]), 'doDone refusé après l\'échéance');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/lesson:doUndo', ['id' => (string)$eLesson['id']]), 'doUndo aussi');

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($this->getCourse($eCourse['id'])->canProgress(), 'l\'équipe du groupe n\'a pas d\'échéance');

	}

	/**
	 * Après l'échéance, la formation ne se commence plus : `acceptCreate()` refuse l'inscription,
	 * ce que la page (`enrollment:doCreate`) et l'API lisent tous deux. Une inscription hors de
	 * tout partage, à une formation de l'équipe, n'est pas touchée, et une inscription existante se
	 * supprime encore (`acceptDelete()` retombe sur `acceptWrite()`, que la règle ne touche pas).
	 */
	public function testEnrollmentClosed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion', [$this->eUserReader]);

		$this->share($eCourse, [$eSection['id'] => [$this->day(-10), \Clock::date()]]);

		$this->actAs($this->eUserReader);

		$newEnrollment = fn(Course $eCourse) => new Enrollment([
			'user' => $this->eUserReader,
			'course' => $this->getCourse($eCourse['id']),
			'structure' => new \structure\Structure()
		]);

		$this->assertTrue($newEnrollment($eCourse)->acceptCreate(), 'le jour de l\'échéance, elle se commence encore');
		$this->assertTrue($newEnrollment($eCourseTeam)->acceptCreate(), 'une formation de l\'équipe, hors partage, se commence');

		$eEnrollment = $newEnrollment($eCourse);
		EnrollmentLib::create($eEnrollment);

		$this->actAs($this->eUserAdmin);
		$this->share($eCourse, [$eSection['id'] => [$this->day(-10), $this->day(-1)]]);
		$this->actAs($this->eUserReader);

		$this->assertFalse($newEnrollment($eCourse)->acceptCreate(), 'échéance passée, l\'inscription est refusée');
		$this->assertTrue($newEnrollment($eCourseTeam)->acceptCreate(), 'et la formation de l\'équipe se commence toujours');

		// Le bouton d'un onglet ouvert la veille : la route refuse avec son message, et n'inscrit rien.
		$this->assertEquals(
			[\FailAction::class, 'learning\Enrollment::closed'],
			$this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '']),
			'échéance passée, le POST est refusé avec son message'
		);
		$this->assertEquals(1, Enrollment::model()->whereCourse($eCourse)->count(), 'échéance passée, aucune inscription de plus');

		$this->assertEquals(
			[\ReloadAction::class, NULL],
			$this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourseTeam['id'], 'structure' => '']),
			'hors partage, le POST inscrit, puis recharge la fiche d\'une formation sans leçon'
		);
		$this->assertTrue(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourseTeam)->notEmpty(), 'hors partage, l\'inscription est créée');

		$eEnrollmentExisting = EnrollmentLib::getByUserAndCourse($this->eUserReader, $this->getCourse($eCourse['id']));

		$this->assertTrue($eEnrollmentExisting->acceptDelete(), 'une inscription existante se supprime encore');

	}

	/**
	 * La ligne sous le titre de l'éditeur lit les partages que `fillShares()` pose, avec leurs dates :
	 * sans elles, elle ne dirait ni l'échéance ni l'ouverture à venir.
	 */
	public function testEditionSummary(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSectionFirst = $this->createSection($eCommunity, 'zzTest promotion A', [$this->eUserReader]);
		$eSectionSecond = $this->createSection($eCommunity, 'zzTest promotion B', [$this->eUserReader]);

		$this->share($eCourse, [
			$eSectionFirst['id'] => [$this->day(-5), $this->day(20)]
		]);

		$eCourse = $this->getCourse($eCourse['id']);
		$eCourse['sections'] = 2;

		ShareLib::fillShares(new \Collection([$eCourse['id'] => $eCourse]));
		EnrollmentLib::fillReaders(new \Collection([$eCourse['id'] => $eCourse]));
		EnrollmentLib::fillRequests(new \Collection([$eCourse['id'] => $eCourse]));

		$this->assertEquals([$this->day(-5), $this->day(20)], [$eCourse['cShare']->first()['opensAt'], $eCourse['cShare']->first()['dueAt']], 'les dates du partage');
		$context = new CourseUi()->getEditionContext($eCourse);
		$this->assertTrue(str_contains($context, 'zzTest promotion A') and str_contains($context, \util\DateUi::numeric($this->day(20), \util\DateUi::DATE)), 'la promotion et son échéance sous le titre');

		$this->share($eCourse, [
			$eSectionFirst['id'] => [$this->day(-5), $this->day(20)],
			$eSectionSecond['id'] => [$this->day(2), $this->day(20)]
		]);

		ShareLib::fillShares(new \Collection([$eCourse['id'] => $eCourse]));

		$this->assertEquals(2, $eCourse['cShare']->count(), 'deux promotions');
		$this->assertFalse(str_contains(new CourseUi()->getEditionContext($eCourse), \util\DateUi::numeric($this->day(20), \util\DateUi::DATE)), 'ouvertes à des dates différentes, sans période');

	}

	/**
	 * Une formation ouverte à deux promotions du stagiaire prend la fenêtre la plus large : la
	 * première ouverture, la dernière échéance, aucune si l'une n'en a pas.
	 */
	public function testWidestWindow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSectionFirst = $this->createSection($eCommunity, 'zzTest promotion A', [$this->eUserReader]);
		$eSectionSecond = $this->createSection($eCommunity, 'zzTest promotion B', [$this->eUserReader]);

		$this->share($eCourse, [
			$eSectionFirst['id'] => [$this->day(-5), $this->day(-1)],
			$eSectionSecond['id'] => [$this->day(2), $this->day(20)]
		]);

		$eShare = ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse);

		$this->assertEquals($this->day(-5), $eShare['opensAt'], 'la première ouverture');
		$this->assertEquals($this->day(20), $eShare['dueAt'], 'la dernière échéance');

		$this->share($eCourse, [
			$eSectionFirst['id'] => [$this->day(-5), NULL],
			$eSectionSecond['id'] => [$this->day(2), $this->day(20)]
		]);

		$this->assertEquals(NULL, ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse)['dueAt'], 'une promotion sans échéance, aucune échéance');

		$cShare = ShareLib::getByUser($this->eUserReader);

		$this->assertEquals(2, $cShare->count(), 'une carte par promotion');
		$this->assertTrue($cShare->first()['course'] === $cShare->last()['course'], 'les deux cartes portent le même élément de formation');

	}

	/**
	 * Dépubliée, la formation sort de la liste du stagiaire et ne se lit plus ; ses partages restent,
	 * et la republier la rend avec les mêmes dates.
	 */
	public function testUnpublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion', [$this->eUserReader]);

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), $this->day(30)]]);

		$eCourse['status'] = Course::DRAFT;
		CourseLib::update($eCourse, ['status']);

		$this->actAs($this->eUserReader);

		$this->assertTrue(ShareLib::getByUser($this->eUserReader)->empty(), 'hors de « Mes formations »');
		$this->assertFalse($this->getCourse($eCourse['id'])->acceptRead(), 'ne se lit plus');
		$this->assertEquals(1, ShareLib::getByCourse($eCourse)->count(), 'le partage reste');

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$cShare = ShareLib::getByUser($this->eUserReader);

		$this->assertEquals(1, $cShare->count(), 'republiée, elle revient');
		$this->assertEquals($this->day(30), ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse)['dueAt'], 'avec la même échéance');

	}

	/**
	 * Une promotion supprimée laisse ses partages, que les lectures écartent ; la suppression de la
	 * formation les emporte, orphelins compris.
	 */
	public function testDeleted(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSectionKept = $this->createSection($eCommunity, 'zzTest promotion gardée', [$this->eUserReader]);
		$eSectionDeleted = $this->createSection($eCommunity, 'zzTest promotion supprimée', [$this->eUserReader]);

		$this->share($eCourse, [
			$eSectionKept['id'] => [\Clock::date(), $this->day(10)],
			$eSectionDeleted['id'] => [\Clock::date(), NULL]
		]);

		\structure\SectionLib::delete($eSectionDeleted);

		$this->assertEquals([$eSectionKept['id']], ShareLib::getByCourse($eCourse)->getKeys(), 'le partage orphelin écarté');
		$this->assertEquals($this->day(10), ShareLib::getWindowByUserAndCourse($this->eUserReader, $eCourse)['dueAt'], 'la fenêtre ne compte plus la promotion supprimée');
		$this->assertEquals(2, Share::model()->whereCourse($eCourse)->count(), 'mais la ligne reste en base');

		CourseLib::delete($eCourse);

		$this->assertEquals(0, Share::model()->whereCourse($eCourse)->count(), 'la suppression de la formation emporte tout');

	}

	/**
	 * Les fenêtres sont gardées par compte et vidées à chaque écriture d'un partage.
	 */
	public function testMemoization(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);
		$eSection = $this->createSection($eCommunity, 'zzTest promotion', [$this->eUserReader]);

		$this->assertTrue(ShareLib::getWindowsByUser($this->eUserReader)->empty(), 'aucune fenêtre avant le partage');

		$this->share($eCourse, [$eSection['id'] => [\Clock::date(), NULL]]);

		$this->assertTrue(ShareLib::getWindowsByUser($this->eUserReader)->offsetExists($eCourse['id']), 'la création vide la mémoire');
		$this->assertFalse(ShareLib::getWindowsByUser($this->eUserAdmin)->offsetExists($eCourse['id']), 'un autre compte garde la sienne');

		$this->share($eCourse, []);

		$this->assertTrue(ShareLib::getWindowsByUser($this->eUserReader)->empty(), 'la suppression la vide aussi');

	}

	/**
	 * La carte d'une formation annoncée n'est pas un lien et dit sa date d'ouverture. Ouverte, elle
	 * dit son échéance, à venir ou passée (planche carte-hierarchie du 02/10, bandes C).
	 */
	public function testCard(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCommunity = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCourse = $this->createCourse($eCommunity, Course::PUBLISHED, Course::PRIVATE);

		// Une leçon au moins : la carte d'une formation sans leçon ne montre pas de progression.
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		CourseLib::fillCounts($cCourse);
		ProgressLib::fillDone($this->eUserReader, $cCourse);

		$announced = new CourseUi()->getCard($eCourse, TRUE, FALSE, eShare: new Share(['opensAt' => $this->day(3), 'dueAt' => NULL]));

		$this->assertFalse(str_contains($announced, '<a href='), 'annoncée, pas un lien');
		$this->assertTrue(str_contains($announced, \util\DateUi::numeric($this->day(3), \util\DateUi::DATE)), 'annoncée, la date d\'ouverture');

		$past = new CourseUi()->getCard($eCourse, TRUE, FALSE, eShare: new Share(['opensAt' => $this->day(-10), 'dueAt' => $this->day(-1)]));

		$this->assertTrue(str_contains($past, '<a href='), 'figée, un lien');
		$this->assertTrue(str_contains($past, \util\DateUi::numeric($this->day(-1), \util\DateUi::DATE)), 'figée, l\'échéance passée : '.$past);

		$open = new CourseUi()->getCard($eCourse, TRUE, FALSE, eShare: new Share(['opensAt' => \Clock::date(), 'dueAt' => $this->day(10)]));

		$this->assertTrue(str_contains($open, \util\DateUi::numeric($this->day(10), \util\DateUi::DATE)), 'avec échéance, l\'échéance à venir : '.$open);

	}

	/**
	 * Le panneau du partage s'envoie pour un groupe et pour un réseau ; sans rien à partager, il n'a
	 * pas d'envoi, sa croix suffisant à le fermer. Le décor est en mémoire : seul le rendu est
	 * éprouvé, pas ce que la base propose.
	 */
	public function testPanelButtons(): void {

		$eCourse = new Course(['id' => 1, 'type' => Course::ONLINE, 'community' => new \structure\Structure(['id' => 1, 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::EDUCATION]), 'lessons' => 2, 'dates' => 0]);

		$cSection = new \Collection([
			7 => new \structure\Section(['id' => 7, 'name' => 'zzTest promotion', 'status' => \structure\Section::OPEN, 'learners' => 3, 'eShare' => new Share(['opensAt' => \Clock::date(), 'dueAt' => NULL])])
		]);

		$eCourseNetwork = new Course(['id' => 2, 'type' => Course::ONLINE, 'community' => new \structure\Structure(['id' => 2, 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]), 'lessons' => 2, 'dates' => 0, 'members' => 2]);
		$eCourseNetworkEmpty = new Course(['members' => 0] + $eCourseNetwork->getArrayCopy());

		$panels = [
			'groupe' => new ShareUi()->share($eCourse, new \Collection(), $cSection),
			'réseau' => new ShareUi()->shareNetwork($eCourseNetwork, new \Collection(), new Share(['course' => $eCourseNetwork, 'audience' => Share::MEMBERS]))
		];

		foreach($panels as $case => $panel) {
			$this->assertTrue(str_contains($panel->body, 'type="submit"'), $case.' : un bouton d\'envoi');
		}

		$empties = [
			'groupe sans promotion' => new ShareUi()->share($eCourse, new \Collection(), new \Collection()),
			'réseau sans membre' => new ShareUi()->shareNetwork($eCourseNetworkEmpty, new \Collection(), new Share(['course' => $eCourseNetworkEmpty]))
		];

		foreach($empties as $case => $panel) {
			$this->assertFalse(str_contains($panel->body, 'type="submit"'), $case.' : aucun envoi, rien ne s\'enregistre');
		}

	}

	/** Un groupe de formation qui n'est pas celui du décor. */
	private function getForeignCommunity(): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereType(\structure\Structure::COMMUNITY)
			->whereId('!=', self::COMMUNITY_EDUCATION)
			->get();

	}

	private function sorted(array $values): array {
		sort($values);
		return $values;
	}

}
?>
