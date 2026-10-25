<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le présentiel au relevé, à l'attestation, au journal et à l'export (lot L8e-3) : les places
 * pointées d'un compte, leurs heures, corrigées ou suivant la durée de la date, leurs modes, la
 * durée retenue qui les ajoute aux leçons validées ; puis leur rendu.
 *
 * Une date mixte pointée compte en entier (porte du 03/10) : le pointage se fait par date et non par
 * séance. Le groupe porte le nom des modes des dates pointées, « Présentiel », « Visio » ou
 * « Présentiel et visio ».
 *
 * Le décor est celui de `LearningDateFixtureTest` : le lecteur fictif suit les deux leçons d'une
 * formation du réseau et s'inscrit à quatre dates, toutes écrites à venir puis portées dans le passé,
 * une inscription ne se prenant que sur une date à venir.
 *
 * Lancement : dev/test package=learning file=AttendanceReport
 */
class AttendanceReportTest extends LearningDateFixtureTest {

	/**
	 * Rien de pointé, puis le présentiel de deux jours, puis la visio et la date mixte aux heures
	 * corrigées ; la date où le lecteur est absent, la place pointée d'une autre personne et celle du
	 * lecteur sur une autre formation ne comptent jamais.
	 */
	public function testFill(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$this->attend($decor['elsewhere']);

		$this->assertEquals([[], 0, [], 30], $this->readAttended($decor['eCourse']), 'rien de pointé sur la formation : la durée retenue est celle des leçons, la place pointée d\'une autre formation écartée');

		$this->attend($decor['onsite']);

		$this->assertEquals([[$decor['onsite']['id']], 840, [DateSequence::ONSITE], 870], $this->readAttended($decor['eCourse']), 'deux jours en présentiel, la durée corrigée de la date');

		$this->attend($decor['remote']);
		$this->attend($decor['mixed']);
		$this->assertEquals([\ViewAction::class, NULL], $this->correct($decor['mixed'], '5'), 'cinq heures sur la date mixte');

		$this->assertEquals(
			[[$decor['onsite']['id'], $decor['remote']['id'], $decor['mixed']['id']], 840 + 180 + 300, [DateSequence::ONSITE, DateSequence::REMOTE], 30 + 840 + 180 + 300],
			$this->readAttended($decor['eCourse']),
			'trois dates dans l\'ordre des jours, la mixte en entier aux heures corrigées, l\'absente, l\'autre personne et l\'autre formation écartées'
		);

		DateEnrollmentLib::unattend($this->getLine($decor['onsite']['id']));
		DateEnrollmentLib::unattend($this->getLine($decor['mixed']['id']));

		$this->assertEquals([[$decor['remote']['id']], 180, [DateSequence::REMOTE], 210], $this->readAttended($decor['eCourse']), 'la visio seule');

	}

	/**
	 * Par place : une place sans compte, que L8f reprendra, porte ses heures et ses modes ; une place
	 * qui n'est pas pointée ne porte rien.
	 */
	public function testFillAttendance(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$this->attend($decor['public']);

		$cDateEnrollment = new \Collection([
			$decor['public']['id'] => $this->getLine($decor['public']['id']),
			$decor['absent']['id'] => $this->getLine($decor['absent']['id']),
		]);

		DateEnrollmentLib::fillAttendance($cDateEnrollment);

		$this->assertEquals([840, [DateSequence::ONSITE]], [$cDateEnrollment[$decor['public']['id']]['minutesAttended'], $cDateEnrollment[$decor['public']['id']]['attendedModes']], 'la place sans compte, pointée');
		$this->assertEquals([0, []], [$cDateEnrollment[$decor['absent']['id']]['minutesAttended'], $cDateEnrollment[$decor['absent']['id']]['attendedModes']], 'la place absente');

	}

	/**
	 * Le relevé : un groupe après les modules, une ligne par date pointée, le total du groupe et la
	 * durée retenue, qui devient aussi la figure. Sans présence, le relevé reste celui de L8d.
	 */
	public function testReport(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$html = $this->renderReport($decor['eCourse']);
		$this->assertEquals(2, substr_count($html, '<tr class="pdf-tr-ml-1">'), 'rien de pointé : les deux leçons seules');
		$this->assertFalse(str_contains($html, 'Présente, pointée'), 'aucune ligne de présence');
		$this->assertFalse(str_contains($html, '<b>Durée retenue</b>'), 'pas de ligne de durée retenue');
		$this->assertFalse(str_contains($html, 'présences pointées'), 'le pied de L8d');

		$this->attend($decor['onsite']);

		$eDateOnsite = DateLib::getById($decor['onsite']['date']['id']);
		$attendedAt = \util\DateUi::numeric($this->getLine($decor['onsite']['id'])['attendedAt'], \util\DateUi::DATE);

		$html = $this->renderReport($decor['eCourse']);

		$this->assertTrue(str_contains($html, '<tr class="tr-title"><td colspan="5"><b>'.s("Présentiel").'</b></td></tr>'), 'le groupe « Présentiel »');
		$this->assertTrue(str_contains($html, '<tr class="pdf-tr-ml-1"><td>'.DateUi::getDays($eDateOnsite).'<div class="util-annotation">Salle des fêtes, Crest</div></td><td>Présente, pointée</td><td class="text-end">'.$attendedAt.'</td><td class="text-end">'.CourseUi::getDuration(840).'</td><td class="text-end">'.CourseUi::getDuration(NULL).'</td></tr>'), 'la ligne de la date, jours, lieu, pointage et heures corrigées');
		$this->assertTrue(str_contains($html, '<td colspan="3"><b>'.s("Présentiel").'</b></td><td class="text-end"><b>'.CourseUi::getDuration(840).'</b></td>'), 'le total du présentiel');
		$this->assertTrue(str_contains($html, '<td colspan="3"><b>'.s("Durée retenue").'</b></td><td class="text-end"><b>'.CourseUi::getDuration(870).'</b></td>'), 'la durée retenue, leçons et présentiel');
		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.CourseUi::getDuration(870).'</div>'), 'la figure suit la durée retenue');
		$this->assertTrue(str_contains($html, 'plus les heures de présentiel pointées par l\'équipe du réseau'), 'la note dit les heures pointées');
		$this->assertTrue(str_contains($html, 'et des présences pointées'), 'le pied aussi');

		$this->attend($decor['remote']);
		$this->attend($decor['mixed']);

		$html = $this->renderReport($decor['eCourse']);

		$this->assertTrue(str_contains($html, '<b>'.s("Présentiel et visio").'</b>'), 'deux modes, un titre qui les dit tous deux');
		$this->assertEquals(2 + 3, substr_count($html, '<tr class="pdf-tr-ml-1">'), 'deux leçons, trois dates, l\'absente sans ligne');
		$this->assertTrue(str_contains($html, '<div class="util-annotation">'.s("En visio").'</div>'), 'la visio dit « En visio »');
		$this->assertTrue(str_contains($html, '<div class="util-annotation">Mairie, Die</div><div class="util-annotation">'.s("En visio").'</div></td>'), 'la date mixte dit son lieu et sa visio, faute de colonne du format');
		$this->assertFalse(str_contains($html, '<div class="util-annotation">'.s("Présentiel et visio").'</div>'), 'le relevé ne nomme pas les modes sous les jours');

		DateEnrollmentLib::unattend($this->getLine($decor['onsite']['id']));
		DateEnrollmentLib::unattend($this->getLine($decor['mixed']['id']));

		$html = $this->renderReport($decor['eCourse']);
		$this->assertTrue(str_contains($html, '<b>'.s("Visio").'</b>'), 'la visio seule');
		$this->assertTrue(str_contains($html, '<tr class="pdf-tr-ml-1"><td>'.DateUi::getDays(DateLib::getById($decor['remote']['date']['id'])).'</td>'), 'sous un titre « Visio », rien sous les jours');
		$this->assertFalse(str_contains($html, s("En visio")), 'jamais « En visio » sous le titre « Visio »');
		$this->assertTrue(str_contains($html, 'plus les heures de visio pointées'), 'la note suit le mode');

	}

	/**
	 * L'attestation : le même groupe au programme, puis trois totaux, les leçons en ligne, les heures
	 * pointées et le total ; les figures restent celles des leçons.
	 */
	public function testAttestation(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$html = $this->renderAttestation($decor['eCourse']);
		$this->assertEquals(1, substr_count($html, '<tr class="pdf-tr-title">'), 'rien de pointé : le titre du module seul, sans total');
		$this->assertFalse(str_contains($html, 'heures de présentiel'), 'la note de L8b');
		$this->assertTrue(str_contains($html, ', '.$this->period(\Clock::date(), \Clock::date()).'.'), 'rien de pointé : la période des leçons seules');

		$this->attend($decor['onsite']);
		$this->attend($decor['mixed']);

		$eDateOnsite = DateLib::getById($decor['onsite']['date']['id']);

		$html = $this->renderAttestation($decor['eCourse']);

		$this->assertTrue(str_contains($html, '<tr class="pdf-tr-title"><td colspan="4">'.s("Présentiel et visio").'</td></tr>'), 'le groupe aux deux modes');
		$this->assertTrue(str_contains($html, '<tr class="pdf-tr-ml-1"><td>'.DateUi::getDays($eDateOnsite).'<div class="util-annotation">Salle des fêtes, Crest</div></td><td>'.s("Présentiel").'</td><td class="text-end">'.CourseUi::getDuration(840).'</td><td class="text-end">'.\util\DateUi::numeric($eDateOnsite['endAt'], \util\DateUi::DATE).'</td></tr>'), 'la date en présentiel, suivie le dernier jour');
		$this->assertTrue(str_contains($html, '<div class="util-annotation">Mairie, Die</div></td><td>'.s("Présentiel et visio").'</td><td class="text-end">'.CourseUi::getDuration(360).'</td>'), 'la date mixte, en entier, son lieu sous les jours et ses modes une fois');
		$this->assertEquals(1, substr_count($html, '<td>'.s("Présentiel et visio").'</td>'), 'les modes de la date mixte dits une fois');
		$this->assertTrue(str_contains($html, '<td colspan="2">'.s("Leçons en ligne").'</td><td class="text-end">'.CourseUi::getDuration(30).'</td>'), 'les leçons en ligne');
		$this->assertTrue(str_contains($html, '<td colspan="2">'.s("Présentiel et visio").'</td><td class="text-end">'.CourseUi::getDuration(840 + 360).'</td>'), 'les heures pointées');
		$this->assertTrue(str_contains($html, '<td colspan="2">'.s("Total").'</td><td class="text-end">'.CourseUi::getDuration(30 + 840 + 360).'</td>'), 'le total');
		$this->assertTrue(str_contains($html, '<div class="pdf-figure-value">'.CourseUi::getDuration(30).'</div>'), 'la figure reste celle des leçons');
		$this->assertTrue(str_contains($html, 'et les heures de présentiel et visio pointées par l\'équipe du réseau'), 'la note dit les heures pointées');
		$this->assertFalse(str_contains($html, 'À distance'), 'plus de « À distance » à côté de la visio');
		$this->assertTrue(str_contains($html, ', '.$this->period($this->day(-9), \Clock::date()).'.'), 'la période part de la première date pointée et va jusqu\'à la dernière leçon');

		$this->attend($decor['remote']);

		$eDateRemote = DateLib::getById($decor['remote']['date']['id']);

		$html = $this->renderAttestation($decor['eCourse']);

		$this->assertTrue(str_contains($html, '<tr class="pdf-tr-ml-1"><td>'.DateUi::getDays($eDateRemote).'</td><td>'.s("Visio").'</td>'), 'la visio, son mode dans la colonne du format et rien sous les jours');
		$this->assertFalse(str_contains($html, s("En visio")), 'jamais « En visio » à côté de « Visio »');

	}

	/**
	 * Une date dont les séances sur place ont eu deux lieux les dit tous deux, chacun sur sa ligne
	 * et encodé, au relevé comme à l'attestation ; une séance sur place sans lieu n'écrit rien.
	 */
	public function testPlaces(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$this->writeModes($decor['onsite']['date'], [
			[$this->day(-9).' 09:00:00', $this->day(-9).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
			[$this->day(-8).' 09:00:00', $this->day(-8).' 12:00:00', DateSequence::ONSITE, 'Die & co'],
			[$this->day(-8).' 14:00:00', $this->day(-8).' 17:00:00', DateSequence::ONSITE, NULL],
		]);

		$eDate = DateLib::getById($decor['onsite']['date']['id']);

		$expected = DateUi::getDays($eDate).'<div class="util-annotation">Salle des fêtes, Crest</div><div class="util-annotation">Die &amp; co</div>';

		$this->assertEquals($expected, AttestationUi::getAttendedDate($eDate, [DateSequence::ONSITE]), 'deux lieux, chacun sur sa ligne, la séance sans lieu muette');
		$this->assertEquals($expected, AttestationUi::getAttendedDate($eDate, [DateSequence::ONSITE, DateSequence::REMOTE]), 'le relevé, sans séance en visio, dit les mêmes lieux');

	}

	/**
	 * Le journal du stagiaire lit la même durée retenue que le relevé.
	 */
	public function testJournal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$this->attend($decor['onsite']);

		[$cChapter, $eUser] = $this->prepareReader($decor['eCourse']);

		$this->assertEquals(870, $eUser['minutesRetained'], 'la durée retenue du journal, leçons et présentiel');
		$this->assertTrue(new ReadingUi()->getSummary($decor['eCourse'], $eUser) !== '', 'le journal se rend');

	}

	/**
	 * L'export d'une date passée gagne « Présence » et « Heures », remplies pour les confirmées et
	 * vides ailleurs ; une date à venir n'en a pas.
	 */
	public function testExport(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$decor = $this->seedDecor();

		$this->assertEquals(7, count($this->export($decor['upcoming'])[0]), 'une date à venir : les sept colonnes d\'avant');

		$this->attend($decor['onsite']);
		$this->assertEquals([\ViewAction::class, NULL], $this->correct($decor['onsite'], '10,5'), 'dix heures et demie');

		$lines = $this->export($decor['onsite']['date']);

		$this->assertEquals([s("Présence"), s("Heures")], array_slice($lines[0], 7), 'les deux colonnes en fin de ligne');

		$byId = $this->exportById($decor['onsite']['date'], $lines);

		$this->assertEquals(['Présente', '10,5'], array_slice($byId[$decor['onsite']['id']], 7), 'pointée, aux heures corrigées');
		$this->assertEquals(['Absente', ''], array_slice($byId[$decor['other']['id']], 7), 'confirmée, pas pointée');
		$this->assertEquals(['Absente', ''], array_slice($byId[$decor['public']['id']], 7), 'la place sans compte, pas pointée');
		$this->assertEquals(['', ''], array_slice($byId[$decor['requested']['id']], 7), 'une demande restée à confirmer, vide');

		$this->attend($decor['public']);
		$byId = $this->exportById($decor['onsite']['date'], $this->export($decor['onsite']['date']));
		$this->assertEquals(['Présente', '14'], array_slice($byId[$decor['public']['id']], 7), 'la place sans compte suit la durée de la date');

	}

	/**
	 * La formation du réseau aux deux leçons de 10 et 20 minutes, suivies par le lecteur, et ses
	 * dates, portées dans le passé une fois les inscriptions prises :
	 * - `onsite` : deux jours en présentiel, 16 h aux horaires, durée corrigée à 14 h ; le lecteur,
	 *   une autre personne confirmée (`other`), une place sans compte (`public`), une demande restée à
	 *   confirmer (`requested`) ;
	 * - `remote` : trois heures en visio ;
	 * - `mixed` : trois heures en présentiel à Die, trois en visio le même jour ;
	 * - `absent` : une journée où le lecteur n'est pas pointé ;
	 * - `upcoming` : une date à venir ;
	 * - `elsewhere` : la place du lecteur sur une date d'une autre formation du réseau, qu'aucun
	 *   rendu de la première ne doit compter.
	 *
	 * @return array<string, mixed>
	 */
	private function seedDecor(): array {

		$eCourse = $this->seed(Course::ONSITE);
		$this->assertEquals([], $this->shareNetwork($eCourse), 'formation ouverte aux membres du réseau');

		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLesson1 = $this->createLessonWritten($eChapter, 'zzTest leçon 1', 10);
		$eLesson2 = $this->createLessonWritten($eChapter, 'zzTest leçon 2', 20);

		$eCourse = $this->getCourse($eCourse['id']);

		$this->actAs($this->eUserReader);

		$eEnrollment = new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => $this->eStructureFarm]);
		EnrollmentLib::create($eEnrollment);
		$eEnrollment = EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse);

		ProgressLib::done($eEnrollment, $eLesson1);
		ProgressLib::done($eEnrollment, $eLesson2);

		$eDateOnsite = $this->createDate($eCourse, 10);
		$eDateRemote = $this->createDate($eCourse, 11);
		$eDateMixed = $this->createDate($eCourse, 12);
		$eDateAbsent = $this->createDate($eCourse, 13);
		$eDateUpcoming = $this->createDate($eCourse, 14);

		$eCourseElsewhere = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eDateElsewhere = $this->createDate($eCourseElsewhere, 15);

		$decor = [
			'eCourse' => $eCourse,
			'onsite' => $this->enrollConfirmed($eDateOnsite, $this->eUserReader),
			'other' => $this->enrollConfirmed($eDateOnsite, $this->createPerson()),
			'public' => $this->enrollPublicConfirmed($eDateOnsite, 'zztest.public-l8e3@example.org'),
			'requested' => $this->enrollRequested($eDateOnsite, $this->createPerson()),
			'remote' => $this->enrollConfirmed($eDateRemote, $this->eUserReader),
			'mixed' => $this->enrollConfirmed($eDateMixed, $this->eUserReader),
			'absent' => $this->enrollConfirmed($eDateAbsent, $this->eUserReader),
			'upcoming' => $eDateUpcoming,
			'elsewhere' => $this->enrollConfirmed($eDateElsewhere, $this->eUserReader),
		];

		$this->writeModes($eDateOnsite, [
			[$this->day(-9).' 09:00:00', $this->day(-9).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
			[$this->day(-8).' 09:00:00', $this->day(-8).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		]);

		$this->writeModes($eDateRemote, [
			[$this->day(-6).' 14:00:00', $this->day(-6).' 17:00:00', DateSequence::REMOTE, NULL],
		]);

		$this->writeModes($eDateMixed, [
			[$this->day(-4).' 09:00:00', $this->day(-4).' 12:00:00', DateSequence::ONSITE, 'Mairie, Die'],
			[$this->day(-4).' 14:00:00', $this->day(-4).' 17:00:00', DateSequence::REMOTE, NULL],
		]);

		$this->writeModes($eDateAbsent, [
			[$this->day(-2).' 09:00:00', $this->day(-2).' 17:00:00', DateSequence::ONSITE, 'Salle des fêtes, Crest'],
		]);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('@module/learning/Date/doQuick', ['id' => (string)$eDateOnsite['id'], 'property' => 'minutes', 'minutes' => '14']), 'la durée des deux jours corrigée à 14 h');

		return $decor;

	}

	/**
	 * Les séances d'une date, mode et lieu compris, à des jours passés. Sans `build()`, qui refuse
	 * une séance passée : le décor pose une date déjà tenue, ce que le formulaire ne fait jamais.
	 */
	private function writeModes(Date $eDate, array $sequences): void {

		$eDate = DateLib::getById($eDate['id']);

		$eDate['cDateSequence'] = new \Collection(array_map(fn(array $sequence) => new DateSequence([
			'startAt' => $sequence[0],
			'endAt' => $sequence[1],
			'mode' => $sequence[2],
			'place' => $sequence[3],
		]), $sequences));

		$eDate['startAt'] = $sequences[0][0];
		$eDate['endAt'] = $sequences[count($sequences) - 1][1];

		DateLib::update($eDate, ['sequences']);

	}

	private function attend(DateEnrollment $eDateEnrollment): void {

		$this->actAs($this->eUserAdmin);

		DateEnrollmentLib::attend($this->getLine($eDateEnrollment['id']));

	}

	private function correct(DateEnrollment $eDateEnrollment, string $hours): array {
		return $this->executePage('@module/learning/DateEnrollment/doQuick', ['id' => (string)$eDateEnrollment['id'], 'property' => 'attendedMinutes', 'attendedMinutes' => $hours]);
	}

	/**
	 * Le lecteur préparé comme le relevé, l'attestation et le journal le préparent.
	 *
	 * @return array{0: \Collection, 1: \user\User}
	 */
	private function prepareReader(Course $eCourse): array {

		$cChapter = ChapterLib::getByCourse($eCourse);
		LessonLib::fillChapterFormat($cChapter);
		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);

		$eUser = $this->getUser($this->eUserReader['id']);
		$cUser = new \Collection([$eUser['id'] => $eUser]);

		ProgressLib::fillTrack($eCourse, $cChapter, $cUser);
		ProgressLib::fillDetail($eCourse, $cChapter, $cUser);
		ProgressLib::fillSummary($cChapter, $cUser);
		ReadingLib::fillMeasured($eCourse, $cChapter, $cUser);
		DateEnrollmentLib::fillAttended($eCourse, $cUser);

		return [$cChapter, $eUser];

	}

	private function readAttended(Course $eCourse): array {

		[, $eUser] = $this->prepareReader($eCourse);

		return [
			array_values($eUser['cDateEnrollmentAttended']->getIds()),
			$eUser['minutesAttended'],
			$eUser['attendedModes'],
			$eUser['minutesRetained'],
		];

	}

	private function renderReport(Course $eCourse): string {

		[$cChapter, $eUser] = $this->prepareReader($eCourse);

		return new ReportUi()->get($eCourse, $cChapter, $eUser, \structure\StructureLib::getById($eCourse['community']), [], \Clock::date());

	}

	private function renderAttestation(Course $eCourse): string {

		[$cChapter, $eUser] = $this->prepareReader($eCourse);

		return new AttestationUi()->get($eCourse, $cChapter, $eUser, $this->eStructureFarm, CourseLib::getIssuer($eCourse, $this->eUserReader), \Clock::date());

	}

	/**
	 * La période que l'attestation écrit, par sa propre méthode : la forme de la phrase (mois et
	 * année répétés ou non) dépend du jour où le test tourne.
	 */
	private function period(string $from, string $to): string {
		return new \ReflectionMethod(AttestationUi::class, 'getPeriod')->invoke(new AttestationUi(), $from, $to);
	}

	/**
	 * L'export comme la page le prépare.
	 */
	private function export(Date $eDate): array {

		$eDate = $this->prepareDate($eDate);

		return new DateEnrollmentUi()->getExport($eDate, DateEnrollmentLib::getByDate($eDate));

	}

	/**
	 * Les lignes de l'export rangées par place, dans l'ordre des demandes que suivent l'export et
	 * `getByDate()`.
	 */
	private function exportById(Date $eDate, array $lines): array {

		return array_combine(DateEnrollmentLib::getByDate($eDate)->getIds(), array_slice($lines, 1));

	}

}
?>
