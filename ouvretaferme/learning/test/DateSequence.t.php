<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Les séances d'une date (lot date-mixte) : la reprise des dates d'avant, ce que l'écriture des
 * séances entraîne (bornes, rappel, lien, lieu, marque de la formation, effacement), le calcul de
 * la marque par le cron, et les rendus des quatre cas de la planche validée le 03/10.
 *
 * Le décor est celui des dates (`LearningDateFixture.t.php`), écrit dans la transaction de chaque
 * test.
 *
 * Lancement : dev/test package=learning file=DateSequence
 */
class DateSequenceTest extends LearningDateFixtureTest {

	const MIGRATION = 'migration/2026-10-03-learning-date-mixte.md';

	/**
	 * La reprise de la migration, lue dans son fichier et jouée telle quelle dans la transaction du
	 * test : une date d'avant, sans séance, en reçoit une par jour couvert, aux heures de la date,
	 * au mode du type de sa formation, avec le lieu de la date en présentiel seulement. Rejouée,
	 * elle ne pose rien sur une date qui a ses séances.
	 */
	public function testReprise(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$sql = $this->getRepriseSql();

		if($sql === NULL) {
			$this->assertTrue(FALSE, 'la reprise est introuvable dans '.self::MIGRATION.' : la requête INSERT de la base de dev a changé de forme');
			return;
		}

		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::REMOTE);

		$eDateTwoDays = $this->toLegacy($this->extendDate($this->createDate($eCourseOnsite, 10), 11), 'Salle des fêtes, Crest');
		$eDateRemote = $this->toLegacy($this->createDate($eCourseRemote, 12), 'Salle des fêtes, Crest');

		$this->assertEquals(0, $this->countSequences($eDateTwoDays) + $this->countSequences($eDateRemote), 'les deux dates sont revenues à l\'état d\'avant le lot, sans séance');

		Course::model()->pdo()->exec($sql);

		$this->assertEquals([
			[$this->day(10).' 09:00:00', $this->day(10).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
			[$this->day(11).' 09:00:00', $this->day(11).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		], $this->readSequences($eDateTwoDays), 'deux jours en présentiel : une séance par jour, aux heures de la date, avec son lieu');

		$this->assertEquals([
			[$this->day(12).' 09:00:00', $this->day(12).' 23:00:00', DateSequence::REMOTE, NULL],
		], $this->readSequences($eDateRemote), 'un jour en visio : une séance, sans le lieu que la date portait');

		$eDateRead = DateLib::getById($eDateTwoDays['id']);
		$this->assertEquals([$eDateRead['startAt'], $eDateRead['endAt']], [$eDateRead['cDateSequence']->first()['startAt'], $eDateRead['cDateSequence']->last()['endAt']], 'les bornes de la date sont celles de ses séances');

		Course::model()->pdo()->exec($sql);

		$this->assertEquals([2, 1], [$this->countSequences($eDateTwoDays), $this->countSequences($eDateRemote)], 'rejouée, la reprise ne pose rien sur une date qui a ses séances');

	}

	/**
	 * Écrire les séances écrit les bornes de la date, et relance le rappel quand le jour de début
	 * change ; une autre heure le même jour ne relance rien, le rappel en était juste.
	 */
	public function testBoundsAndReminder(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollConfirmed($eDate, $this->createPerson());

		$this->remind($eDateEnrollment);

		$eDate = $this->setSequences($eDate, [
			[$this->day(10).' 14:00:00', $this->day(10).' 17:00:00', DateSequence::ONSITE, 'Crest'],
			[$this->day(11).' 09:30:00', $this->day(11).' 12:30:00', DateSequence::ONSITE, 'Die'],
		]);

		$this->assertEquals([$this->day(10).' 14:00:00', $this->day(11).' 12:30:00'], [$eDate['startAt'], $eDate['endAt']], 'les bornes suivent la première et la dernière séance');
		$this->assertTrue($this->getLine($eDateEnrollment['id'])['remindedAt'] !== NULL, 'le même jour de début, à une autre heure : le rappel reste');

		$this->setSequences($eDate, [
			[$this->day(12).' 09:30:00', $this->day(12).' 17:00:00', DateSequence::ONSITE, 'Crest'],
		]);

		$this->assertEquals(NULL, $this->getLine($eDateEnrollment['id'])['remindedAt'], 'un autre jour de début : le rappel repart');

		$this->assertEquals(1, $this->getLine($eDateEnrollment['id'])['date']['cDateSequence']->count(), 'la ligne d\'un inscrit charge les séances de sa date, que le rappel et les e-mails lisent');

	}

	/**
	 * Le lien ne s'écrit que tant qu'une séance est en visio, le lieu que sur une séance en
	 * présentiel, quoi que le formulaire ait posté.
	 */
	public function testLinkAndPlace(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);

		$eDate = $this->setSequences($this->createDate($eCourse, 10), [
			[$this->day(10).' 09:30:00', $this->day(10).' 12:30:00', DateSequence::ONSITE, 'Crest'],
			[$this->day(10).' 14:00:00', $this->day(10).' 17:00:00', DateSequence::REMOTE, 'Salle oubliée'],
		], 'https://visio.example.org/zztest');

		$this->assertEquals('https://visio.example.org/zztest', $eDate['link'], 'une séance en visio : le lien s\'écrit');
		$this->assertEquals(['Crest', NULL], $eDate['cDateSequence']->makeArray(fn(DateSequence $eDateSequence) => $eDateSequence['place']), 'le lieu posté sur la séance en visio ne s\'écrit pas');

		$eDate = $this->setSequences($eDate, [
			[$this->day(10).' 09:30:00', $this->day(10).' 17:00:00', DateSequence::ONSITE, 'Crest'],
		], 'https://visio.example.org/zztest');

		$this->assertEquals(NULL, $eDate['link'], 'la dernière séance en visio partie, le lien part avec elle');

	}

	/**
	 * La marque d'une formation datée suit les séances de ses dates à venir, à chaque écriture, à
	 * l'annulation et à la suppression d'une date ; sans date à venir, celles de la dernière date
	 * planifiée ; sans date planifiée, elle ne change pas. Les séances partent avec leur date.
	 */
	public function testSynchronizeType(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$type = fn() => $this->getCourse($eCourse['id'])['type'];

		$eDateA = $this->createDate($eCourse, 10);
		$this->assertEquals(Course::ONSITE, $type(), 'une date en présentiel');

		$eDateA = $this->setSequences($eDateA, [
			[$this->day(10).' 09:30:00', $this->day(10).' 12:30:00', DateSequence::ONSITE, 'Crest'],
			[$this->day(10).' 14:00:00', $this->day(10).' 17:00:00', DateSequence::REMOTE, NULL],
		]);
		$this->assertEquals(Course::MIXED, $type(), 'une journée coupée en deux modes : présentiel et visio');

		$eDateB = $this->setSequences($this->createDate($eCourse, 20), [
			[$this->day(20).' 09:30:00', $this->day(20).' 12:30:00', DateSequence::REMOTE, NULL],
		]);
		$this->assertEquals(Course::MIXED, $type(), 'une seconde date en visio ne change rien, la première mêle les deux');

		DateLib::cancel(DateLib::getById($eDateA['id']));
		$this->assertEquals(Course::REMOTE, $type(), 'la date mixte annulée, il reste la visio');

		DateLib::delete(DateLib::getById($eDateB['id']));
		$this->assertEquals(0, $this->countSequences($eDateB), 'les séances partent avec leur date');
		$this->assertEquals(Course::REMOTE, $type(), 'plus aucune date planifiée : la marque ne change pas');

		$this->createDate($eCourse, -3);
		$this->assertEquals(Course::ONSITE, $type(), 'sans date à venir, la dernière date planifiée décide');

	}

	/**
	 * Le temps qui passe change la marque sans que personne écrive : le cron horaire recalcule les
	 * formations dont une date planifiée a fini dans les deux dernières heures, et pas avant.
	 */
	public function testSynchronizeTypeOfEnded(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::DRAFT, Course::PRIVATE, type: Course::ONSITE);
		$type = fn() => $this->getCourse($eCourse['id'])['type'];

		// `createDate()` porte la date sur une journée finissant à 23:00 (`moveDate()`).
		$this->createDate($eCourse, 5);
		$this->setSequences($this->createDate($eCourse, 15), [
			[$this->day(15).' 09:30:00', $this->day(15).' 12:30:00', DateSequence::REMOTE, NULL],
		]);

		$this->assertEquals(Course::MIXED, $type(), 'une date en présentiel, une autre en visio');

		$before = $this->day(5).' 22:30:00';
		$after = $this->day(5).' 23:30:00';

		$this->setClock($before);
		CourseLib::synchronizeTypeOfEnded();
		$this->assertEquals(Course::MIXED, $type(), 'la date en présentiel n\'a pas fini');

		$this->setClock($after);
		CourseLib::synchronizeTypeOfEnded();
		$this->assertEquals(Course::REMOTE, $type(), 'elle vient de finir : il reste la visio');

	}

	/**
	 * Les rendus des quatre cas de la planche : la pile (`getLine()`), la phrase de l'engagement
	 * (`getCommitmentLine()`), la case de présence et les faits des e-mails.
	 */
	public function testRenders(): void {

		$nyons = 'Salle des fêtes, Nyons';
		$buis = 'Maison de pays, Buis-les-Baronnies';

		$un = $this->getDate([
			['2026-10-13 09:30:00', '2026-10-13 17:00:00', DateSequence::ONSITE, $nyons],
		]);
		$meme = $this->getDate([
			['2026-11-05 09:30:00', '2026-11-05 17:00:00', DateSequence::ONSITE, $buis],
			['2026-11-06 09:30:00', '2026-11-06 17:00:00', DateSequence::ONSITE, $buis],
		]);
		$mixte = $this->getDate([
			['2026-11-09 09:30:00', '2026-11-09 17:00:00', DateSequence::ONSITE, $nyons],
			['2026-11-10 09:30:00', '2026-11-10 12:30:00', DateSequence::REMOTE, NULL],
		]);
		$coupee = $this->getDate([
			['2026-11-26 09:00:00', '2026-11-26 12:30:00', DateSequence::ONSITE, $buis],
			['2026-11-26 14:00:00', '2026-11-26 16:00:00', DateSequence::REMOTE, NULL],
		]);

		$this->assertEquals('09:30 à 17:00 · '.$nyons, DateUi::getLine($un, withLink: FALSE), 'un jour : la ligne d\'aujourd\'hui');
		$this->assertEquals('09:30 à 17:00 · '.$buis, DateUi::getLine($meme, withLink: FALSE), 'deux jours identiques : une seule ligne');
		$this->assertEquals('Lundi 9 · 09:30 à 17:00 · '.$nyons.'<br/>Mardi 10 · 09:30 à 12:30 · '.s("En visio"), DateUi::getLine($mixte, withLink: FALSE), 'deux jours, deux modes : une ligne par séance, le jour devant');
		$this->assertEquals('09:00 à 12:30 · '.$buis.'<br/>14:00 à 16:00 · '.s("En visio"), DateUi::getLine($coupee, withLink: FALSE), 'une journée coupée : une ligne par séance, sans le jour');

		$sansLieu = $this->getDate([
			['2026-11-27 14:00:00', '2026-11-27 16:00:00', DateSequence::ONSITE, NULL],
		]);
		$this->assertEquals('14:00 à 16:00', DateUi::getLine($sansLieu, withLink: FALSE), 'une séance reprise d\'une date sans lieu garde ses seules heures, comme la date avant le lot');
		$this->assertEquals('Vendredi 27 novembre, de 14:00 à 16:00.', DateUi::getCommitmentLine($sansLieu), 'son engagement s\'arrête aux heures');

		$sansLieuMixte = $this->getDate([
			['2026-11-30 09:00:00', '2026-11-30 12:00:00', DateSequence::ONSITE, NULL],
			['2026-12-01 09:00:00', '2026-12-01 12:00:00', DateSequence::REMOTE, NULL],
		]);
		$this->assertEquals('Lundi 30 novembre, de 09:00 à 12:00.<br/>Mardi 1er décembre, de 09:00 à 12:00, en visio.', DateUi::getCommitmentLine($sansLieuMixte), 'et chaque séance sans lieu de même, à côté d\'une visio');

		$this->assertEquals([$nyons, $buis, s("Présentiel et visio"), s("Présentiel et visio")], [DateUi::getWhere($un), DateUi::getWhere($meme), DateUi::getWhere($mixte), DateUi::getWhere($coupee)], 'où se tient la date, d\'un mot');

		$this->assertEquals('Lundi 9 novembre, de 09:30 à 17:00, '.$nyons.'.<br/>Mardi 10 novembre, de 09:30 à 12:30, en visio.', DateUi::getCommitmentLine($mixte), 'l\'engagement énumère les séances, comme la planche');
		$this->assertEquals('Jeudi 5 et vendredi 6 novembre, de 09:30 à 17:00, '.$buis.'.', DateUi::getCommitmentLine($meme), 'des séances identiques gardent la phrase d\'aujourd\'hui');

		$presence = new \ReflectionMethod(DateEnrollmentUi::class, 'getPresence');
		$this->assertEquals([
			s("Je participerai à la formation en entier"),
			s("Je participerai aux deux journées"),
			s("Je participerai aux deux séances"),
			s("Je participerai aux deux séances"),
		], [$presence->invoke(NULL, $un), $presence->invoke(NULL, $meme), $presence->invoke(NULL, $mixte), $presence->invoke(NULL, $coupee)], 'la case de présence');

		$facts = fn(Date $eDate) => new \ReflectionMethod(DateEnrollmentUi::class, 'getFacts')->invoke(new DateEnrollmentUi(), new DateEnrollment(['date' => $eDate]), FALSE, FALSE);

		$this->assertEquals([
			s("Date") => 'Mardi 13 octobre 2026',
			s("Horaire") => '09:30 à 17:00',
			s("Lieu") => $nyons,
		], $facts($un), 'un jour : Horaire et Lieu, comme aujourd\'hui');

		$this->assertEquals([
			s("Date") => DateUi::getDays($mixte),
			'Lundi 9 novembre' => '09:30 à 17:00, '.$nyons,
			'Mardi 10 novembre' => '09:30 à 12:30, en visio',
		], $facts($mixte), 'deux modes : un fait par séance, le jour en libellé, comme la planche');

		$this->assertEquals([
			s("Date") => DateUi::getDays($coupee),
			'Jeudi 26 novembre, 09:00 à 12:30' => $buis,
			'Jeudi 26 novembre, 14:00 à 16:00' => s("En visio"),
		], $facts($coupee), 'une journée coupée : l\'horaire passe dans le libellé, qui doit rester unique');

		$this->assertEquals([
			s("Date") => DateUi::getDays($sansLieuMixte),
			'Lundi 30 novembre' => '09:00 à 12:00',
			'Mardi 1er décembre' => '09:00 à 12:00, en visio',
		], $facts($sansLieuMixte), 'une séance sans lieu garde ses seules heures');

	}

	/**
	 * Une date construite sans être écrite, planifiée, sans lien.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string, 3: ?string}> $sequences
	 */
	private function getDate(array $sequences): Date {

		return new Date([
			'course' => new Course(['type' => Course::ONSITE]),
			'status' => Date::PLANNED,
			'startAt' => $sequences[0][0],
			'endAt' => $sequences[count($sequences) - 1][1],
			'link' => NULL,
			'linkMailedAt' => NULL,
			'cDateSequence' => new \Collection(array_map(fn(array $sequence) => new DateSequence([
				'startAt' => $sequence[0],
				'endAt' => $sequence[1],
				'mode' => $sequence[2],
				'place' => $sequence[3],
			]), $sequences)),
		]);

	}

	/**
	 * Remplace les séances de la date par celles-ci, `[début, fin, mode, lieu]`, et pose le lien, par
	 * `DateLib::update()` comme `doUpdate`. Sans `build()`, qui refuse une date du jour ou passée :
	 * le décor de `createDate()` en porte. `writeSequences()` garde le mode de la première séance,
	 * ce test les change.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string, 3: ?string}> $sequences
	 */
	private function setSequences(Date $eDate, array $sequences, ?string $link = NULL): Date {

		$eDate = DateLib::getById($eDate['id']);

		$eDate['cDateSequence'] = new \Collection(array_map(fn(array $sequence) => new DateSequence([
			'startAt' => $sequence[0],
			'endAt' => $sequence[1],
			'mode' => $sequence[2],
			'place' => $sequence[3],
		]), $sequences));

		$eDate['startAt'] = $sequences[0][0];
		$eDate['endAt'] = $sequences[count($sequences) - 1][1];
		$eDate['link'] = $link;

		DateLib::update($eDate, ['sequences']);

		return DateLib::getById($eDate['id']);

	}

	/**
	 * La date ramenée à l'état d'avant le lot : sans séance, son lieu sur la date. Écrit par le
	 * modèle : le produit ne fabrique plus de date sans séance, et `Date.place` n'est plus une
	 * propriété du Lib.
	 */
	private function toLegacy(Date $eDate, string $place): Date {

		DateSequenceLib::deleteByDate($eDate);

		Date::model()->update($eDate, ['place' => $place]);

		return $eDate;

	}

	/**
	 * Le rappel parti, écrit par le modèle : seul le cron de 7 h 30 le pose, sur une date du
	 * lendemain.
	 */
	private function remind(DateEnrollment $eDateEnrollment): void {

		DateEnrollment::model()->update($eDateEnrollment, ['remindedAt' => \Clock::datetime()]);

	}

	private function countSequences(Date $eDate): int {
		return DateSequence::model()->whereDate($eDate)->count();
	}

	/**
	 * @return array<int, array{0: string, 1: string, 2: string, 3: ?string}>
	 */
	private function readSequences(Date $eDate): array {

		return DateSequence::model()
			->select(['startAt', 'endAt', 'mode', 'place'])
			->whereDate($eDate)
			->sort('startAt')
			->getCollection()
			->makeArray(fn(DateSequence $eDateSequence) => [$eDateSequence['startAt'], $eDateSequence['endAt'], $eDateSequence['mode'], $eDateSequence['place']]);

	}

	/**
	 * La requête de reprise de la base de dev, telle que la migration la fait jouer par `lime.php -r`.
	 */
	private function getRepriseSql(): ?string {

		$content = file_get_contents(\LIME_DIRECTORY.'/'.self::MIGRATION);

		if(preg_match('/exec\("(INSERT INTO dev_ouvretaferme\.learningDateSequence .+?)"\);/', $content, $match) === 0) {
			return NULL;
		}

		return str_replace('\\"', '"', $match[1]);

	}

}
?>
