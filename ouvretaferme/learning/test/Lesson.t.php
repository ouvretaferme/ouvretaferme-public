<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les leçons d'un module restent rangées par `position` à partir de 0, sans trou : une leçon
 * supprimée ou partie dans un autre module fait remonter d'un rang celles qui la suivaient, et une
 * leçon créée ou reçue se range en dernier. Le numéro que l'écran donne à une leçon suit ce rang.
 */
class LessonTest extends LearningFixtureTest {

	/**
	 * Après la première leçon supprimée sur trois, les deux autres remontent, et la leçon créée
	 * ensuite prend la dernière place, sans égalité avec une autre.
	 */
	public function testCreateAfterDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$eLessonFirst = $this->createLesson($eChapter, 'zzTest première leçon', 5);
		$this->createLesson($eChapter, 'zzTest deuxième leçon', 5);
		$this->createLesson($eChapter, 'zzTest troisième leçon', 5);

		LessonLib::delete($this->getLesson($eLessonFirst['id']));

		$this->assertEquals([0, 1], $this->getPositions($eChapter), 'les suivantes remontent d\'un rang');

		$eLessonNew = $this->createLesson($eChapter, 'zzTest leçon ajoutée', 5);

		$this->assertEquals([0, 1, 2], $this->getPositions($eChapter), 'la leçon créée en dernier, sans égalité');
		$this->assertEquals(2, $this->getLesson($eLessonNew['id'])['position'], 'à la dernière place');

	}

	/**
	 * Une leçon du milieu changée de module : celui qu'elle quitte se resserre, celui qui la reçoit
	 * la range en dernier.
	 */
	public function testMoveToOtherChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eChapterFrom = $this->createChapter($eCourse, 'zzTest module quitté');
		$eChapterTo = $this->createChapter($eCourse, 'zzTest module d\'arrivée');

		$eLessonFirst = $this->createLesson($eChapterFrom, 'zzTest première leçon', 5);
		$eLessonMiddle = $this->createLesson($eChapterFrom, 'zzTest leçon déplacée', 5);
		$eLessonLast = $this->createLesson($eChapterFrom, 'zzTest dernière leçon', 5);

		$this->createLesson($eChapterTo, 'zzTest leçon déjà là', 5);

		// Ce que la page des réglages pose avant le `build()` : `Lesson::chapter.owned` cherche le
		// module posté dans le programme de la formation.
		$eLesson = $this->getLesson($eLessonMiddle['id']);
		$eLesson['cChapter'] = ChapterLib::getByCourse($eCourse);

		$fw = new \FailWatch();

		$eLesson->build(['chapter'], ['chapter' => (string)$eChapterTo['id']], new \PropertyList('update'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('déplacement refusé : '.json_encode($fw->get())));

		LessonLib::update($eLesson, ['chapter']);

		$this->assertEquals([$eLessonFirst['id'] => 0, $eLessonLast['id'] => 1], $this->getPositionsById($eChapterFrom), 'le module quitté se resserre');
		$this->assertEquals([0, 1], $this->getPositions($eChapterTo), 'le module d\'arrivée la range en dernier');
		$this->assertEquals(1, $this->getLesson($eLessonMiddle['id'])['position'], 'après la leçon qui y était');

		LessonLib::update($this->getLesson($eLessonFirst['id']), ['name']);

		$this->assertEquals([$eLessonFirst['id'] => 0, $eLessonLast['id'] => 1], $this->getPositionsById($eChapterFrom), 'une écriture qui ne change pas de module ne touche à aucun rang');

	}

	/**
	 * La dernière leçon d'un module d'une formation en brouillon se supprime, et le module reste,
	 * vide.
	 */
	public function testDeleteLast(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eChapterOther = $this->createChapter($eCourse, 'zzTest autre module');

		$eLesson = $this->createLesson($eChapter, 'zzTest seule leçon', 5);
		$this->createLesson($eChapterOther, 'zzTest première leçon de l\'autre', 5);
		$this->createLesson($eChapterOther, 'zzTest seconde leçon de l\'autre', 5);

		$fw = new \FailWatch();

		LessonLib::delete($this->getLesson($eLesson['id']));

		$this->assertEquals([], $fw->get(), 'aucun refus sous une formation en brouillon');
		$this->assertEquals([], $this->getPositions($eChapter), 'le module est vide');
		$this->assertEquals([0, 1], $this->getPositions($eChapterOther), 'l\'autre module est intact');

	}

	/**
	 * La tête de l'éditeur de leçon situe la leçon par son rang dans le programme. Une leçon sans
	 * durée perd le dernier terme de la ligne de contexte. Rendue par morceaux comme
	 * `DateEnrollmentTest::testListHead()` : la page entière demande en CLI tout le démarrage de
	 * l'application.
	 */
	public function testEditionHead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');

		$eLessonTimed = $this->createLesson($eChapter, 'zzTest leçon estimée', 5);
		$eLessonUntimed = $this->createLesson($eChapter, 'zzTest leçon sans durée', 5);

		$eLessonUntimed = $this->getLesson($eLessonUntimed['id']);

		$fw = new \FailWatch();

		$eLessonUntimed->build(['minutes'], ['minutes' => ''], new \PropertyList('update'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('durée retirée refusée : '.json_encode($fw->get())));

		LessonLib::update($eLessonUntimed, ['minutes']);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$ui = new LessonUi();

		$eLessonTimed = $this->getLesson($eLessonTimed['id']);
		ChapterLib::locate($cChapter, $eLessonTimed);

		$this->assertEquals(1, $eLessonTimed['number'], 'le rang de la première leçon');

		$head = $ui->getEditionHead($eLessonTimed, $cChapter);

		$this->assertTrue(str_contains($head, CourseUi::getDuration(5)), 'la durée ferme la ligne de contexte');

		$eLessonUntimed = $this->getLesson($eLessonUntimed['id']);
		ChapterLib::locate($cChapter, $eLessonUntimed);

		$this->assertEquals(NULL, $eLessonUntimed['minutes'], 'la durée est retirée');
		$this->assertEquals(2, $eLessonUntimed['number'], 'le rang de la seconde leçon');

		$head = $ui->getEditionHead($eLessonUntimed, $cChapter);

		$this->assertFalse(str_contains($head, CourseUi::getDuration(NULL)), 'sans durée, le terme disparaît');

	}

	private function getPositions(Chapter $eChapter): array {
		return array_values($this->getPositionsById($eChapter));
	}

	private function getPositionsById(Chapter $eChapter): array {

		$cLesson = Lesson::model()
			->select(['id', 'position'])
			->whereChapter($eChapter)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

		$positions = [];

		foreach($cLesson as $eLesson) {
			$positions[$eLesson['id']] = $eLesson['position'];
		}

		return $positions;

	}

	/**
	 * Ce que la page de la leçon tire de ses blocs : une étape numérotée qui a sa capture ouvre le
	 * lecteur, vidéo ou pas ; une leçon à étapes qui a sa vidéo la met dans une carte. Ce qui précède
	 * les étapes passe au-dessus, la vidéo n'est dans aucun des deux morceaux.
	 */
	public function testFillReader(): void {

		$eBlockVideo = new \block\Block(['id' => 1, 'type' => \block\BlockTypeLib::VIDEO, 'cBlock' => new \Collection()]);
		$eBlockText = new \block\Block(['id' => 2, 'type' => \block\BlockTypeLib::TEXT, 'cBlock' => new \Collection()]);
		$eBlockTextAfter = new \block\Block(['id' => 6, 'type' => \block\BlockTypeLib::TEXT, 'cBlock' => new \Collection()]);

		$getSteps = fn(?string $media, string $variant = \block\BlockTypeLib::NUMBERS) => new \block\Block([
			'id' => 3,
			'type' => \block\BlockTypeLib::STEPS,
			'variant' => $variant,
			'cBlock' => new \Collection([
				4 => new \block\Block(['id' => 4, 'type' => \block\BlockTypeLib::ITEM, 'media' => NULL, 'settings' => []]),
				5 => new \block\Block(['id' => 5, 'type' => \block\BlockTypeLib::ITEM, 'media' => $media, 'settings' => []]),
			]),
		]);

		$fill = function(array $cBlock): Lesson {

			$eLesson = new Lesson(['cBlock' => new \Collection($cBlock)]);
			LessonLib::fillReader($eLesson);

			return $eLesson;

		};

		// Une leçon à étapes et à captures, sans vidéo (7975).
		$eLesson = $fill([2 => $eBlockText, 3 => $getSteps('capture'), 6 => $eBlockTextAfter]);

		$this->assertTrue($eLesson['reader'], 'une étape a sa capture, sans vidéo');
		$this->assertFalse($eLesson['videoCard'], 'sans vidéo');
		$this->assertEquals(5, $eLesson['eBlockCapture']['id'], 'la première étape qui a sa capture');
		$this->assertEquals([2], $eLesson['cBlockIntro']->getIds(), 'ce qui précède les étapes');
		$this->assertEquals([3, 6], $eLesson['cBlockSteps']->getIds(), 'les étapes et ce qui les suit');
		$this->assertEquals(2, $eLesson['steps'], 'les étapes numérotées');

		// La même avec sa vidéo (32034) : la vidéo passe dans la carte, hors des deux morceaux.
		$eLesson = $fill([1 => $eBlockVideo, 2 => $eBlockText, 3 => $getSteps('capture')]);

		$this->assertTrue($eLesson['reader'], 'une étape a sa capture, avec la vidéo');
		$this->assertTrue($eLesson['videoCard'], 'des étapes et une vidéo');
		$this->assertEquals(1, $eLesson['eBlockVideo']['id'], 'la vidéo de la leçon');
		$this->assertEquals([2], $eLesson['cBlockIntro']->getIds(), 'sans la vidéo au-dessus des étapes');
		$this->assertEquals([3], $eLesson['cBlockSteps']->getIds(), 'ni dans les étapes');

		// Une vidéo et des étapes sans capture : la carte, les étapes dans le fil.
		$eLesson = $fill([1 => $eBlockVideo, 3 => $getSteps(NULL)]);

		$this->assertFalse($eLesson['reader'], 'aucune étape n\'a de capture');
		$this->assertTrue($eLesson['videoCard'], 'la vidéo passe quand même dans la carte');

		// Une vidéo sans étape reste dans le fil, où elle est tout le contenu.
		$eLesson = $fill([1 => $eBlockVideo, 2 => $eBlockText]);

		$this->assertFalse($eLesson['reader'], 'pas d\'étape');
		$this->assertFalse($eLesson['videoCard'], 'une vidéo sans étape');

		// Une liste de critères n'est pas une suite d'étapes, même avec ses captures.
		$eLesson = $fill([3 => $getSteps('capture', \block\BlockTypeLib::CHECK)]);

		$this->assertFalse($eLesson['reader'], 'des critères à captures n\'ouvrent pas le lecteur');
		$this->assertEquals(0, $eLesson['steps'], 'ni ne comptent comme étapes');
		$this->assertEquals([3], $eLesson['cBlockIntro']->getIds(), 'ils restent dans ce qui se lit avant les étapes');

		// Une liste ouverte par une section se coupe avant la section, qui garde son titre avec ses étapes (7977).
		$eBlockSection = new \block\Block(['id' => 7, 'type' => \block\BlockTypeLib::SECTION, 'cBlock' => new \Collection()]);
		$eLesson = $fill([2 => $eBlockText, 7 => $eBlockSection, 3 => $getSteps('capture')]);

		$this->assertEquals([2], $eLesson['cBlockIntro']->getIds(), 'ce qui précède la section');
		$this->assertEquals([7, 3], $eLesson['cBlockSteps']->getIds(), 'la section et ses étapes');

		// Du texte entre la section et ses étapes reste sous la section.
		$eLesson = $fill([7 => $eBlockSection, 2 => $eBlockText, 3 => $getSteps('capture')]);

		$this->assertEquals([], $eLesson['cBlockIntro']->getIds(), 'rien avant la section');
		$this->assertEquals([7, 2, 3], $eLesson['cBlockSteps']->getIds(), 'la section, son texte et ses étapes');

		// Deux listes dont seule la seconde a sa capture : le lecteur s'ouvre dès la première.
		$eBlockStepsLater = new \block\Block([
			'id' => 8,
			'type' => \block\BlockTypeLib::STEPS,
			'variant' => \block\BlockTypeLib::NUMBERS,
			'cBlock' => new \Collection([
				9 => new \block\Block(['id' => 9, 'type' => \block\BlockTypeLib::ITEM, 'media' => 'capture', 'settings' => []]),
			]),
		]);
		$eLesson = $fill([2 => $eBlockText, 3 => $getSteps(NULL), 8 => $eBlockStepsLater]);

		$this->assertTrue($eLesson['reader'], 'une capture dans la seconde liste');
		$this->assertEquals(9, $eLesson['eBlockCapture']['id'], 'la première étape qui a sa capture, dans la seconde liste');
		$this->assertEquals([3, 8], $eLesson['cBlockSteps']->getIds(), 'coupé avant la première liste');
		$this->assertEquals(3, $eLesson['steps'], 'les étapes des deux listes');

		$this->assertTrue($fill([])['eBlockVideo']->empty(), 'une leçon sans bloc n\'a pas de vidéo');

	}

	/**
	 * La forme d'une leçon se lit sur ses blocs lisibles : une vidéo d'abord, même avec des étapes,
	 * puis des étapes numérotées, puis un texte ou un quiz seul (arbitrage Q1), sinon rien. Une liste
	 * de critères cochés n'est pas une suite d'étapes.
	 */
	public function testGetFormat(): void {

		$eBlockVideo = new \block\Block(['id' => 1, 'type' => \block\BlockTypeLib::VIDEO, 'cBlock' => new \Collection()]);
		$eBlockText = new \block\Block(['id' => 2, 'type' => \block\BlockTypeLib::TEXT, 'cBlock' => new \Collection()]);

		$getSteps = fn(string $variant) => new \block\Block([
			'id' => 3,
			'type' => \block\BlockTypeLib::STEPS,
			'variant' => $variant,
			'cBlock' => new \Collection([
				4 => new \block\Block(['id' => 4, 'type' => \block\BlockTypeLib::ITEM]),
			]),
		]);

		$format = fn(array $cBlock, bool $written = TRUE) => LessonLib::getFormat(new Lesson(['written' => $written]), new \Collection($cBlock));

		$this->assertEquals(Lesson::VIDEO, $format([1 => $eBlockVideo, 3 => $getSteps(\block\BlockTypeLib::NUMBERS)]), 'la vidéo passe devant les étapes');
		$this->assertEquals(Lesson::STEPS, $format([2 => $eBlockText, 3 => $getSteps(\block\BlockTypeLib::NUMBERS)]), 'des étapes numérotées');
		$this->assertEquals(Lesson::TEXT, $format([3 => $getSteps(\block\BlockTypeLib::CHECK)]), 'des critères cochés se lisent comme un texte');
		$this->assertEquals(Lesson::TEXT, $format([2 => $eBlockText]), 'un texte');
		$this->assertEquals(Lesson::TEXT, $format([]), 'un quiz seul, que la colonne dit écrite');
		$this->assertEquals(Lesson::EMPTY, $format([], FALSE), 'rien à lire ni à faire');

	}

	/**
	 * « Leçon n sur N » : en ligne, dans son module ; sur place, en visio ou en mixte, sur toute la
	 * formation.
	 */
	public function testFillPosition(): void {

		$eLesson1 = new Lesson(['id' => 1, 'chapter' => new Chapter(['id' => 10]), 'number' => 1, 'numberInChapter' => 1]);
		$eLesson2 = new Lesson(['id' => 2, 'chapter' => new Chapter(['id' => 20]), 'number' => 2, 'numberInChapter' => 1]);
		$eLesson3 = new Lesson(['id' => 3, 'chapter' => new Chapter(['id' => 20]), 'number' => 3, 'numberInChapter' => 2]);

		$cChapter = new \Collection([
			10 => new Chapter(['id' => 10, 'cLesson' => new \Collection([1 => $eLesson1])]),
			20 => new Chapter(['id' => 20, 'cLesson' => new \Collection([2 => $eLesson2, 3 => $eLesson3])]),
		]);

		$eLesson = new Lesson(['id' => 3, 'chapter' => new Chapter(['id' => 20]), 'number' => 3, 'numberInChapter' => 2]);

		ChapterLib::fillPosition(new Course(['type' => Course::ONLINE, 'lessons' => 3]), $cChapter, $eLesson);
		$this->assertEquals([2, 2], [$eLesson['positionNumber'], $eLesson['positionTotal']], 'en ligne, dans son module');

		foreach([Course::REMOTE, Course::ONSITE, Course::MIXED] as $type) {
			ChapterLib::fillPosition(new Course(['type' => $type, 'lessons' => 3]), $cChapter, $eLesson);
			$this->assertEquals([3, 3], [$eLesson['positionNumber'], $eLesson['positionTotal']], $type.', sur toute la formation');
		}

	}

}
?>
