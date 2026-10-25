<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le classement d'une formation est facultatif à l'écriture et exigé à la publication, dans les
 * deux sens : on ne publie pas une formation incomplète, et on ne vide pas ce qui la classe tant
 * qu'elle est publiée. Chaque règle est éprouvée des deux côtés, le refus et le passage : une règle
 * qui refuserait tout serait verte sur la moitié des contrôles.
 */
class ClassifiedTest extends LearningFixtureTest {

	/**
	 * Une formation se crée avec son seul titre, et ce que le formulaire ne poste pas reste vide.
	 */
	public function testCreateWithNameOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE, classified: FALSE);

		$this->assertEquals(NULL, $eCourse['summary'], 'la présentation reste vide');
		$this->assertTrue($eCourse['theme']->empty(), 'la thématique reste vide');
		$this->assertTrue($eCourse['level']->empty(), 'le niveau reste vide');
		$this->assertEquals(Course::DRAFT, $eCourse['status'], 'la formation naît brouillon');

	}

	/**
	 * Sens aller : `Course::status.classified`.
	 */
	public function testPublishNeedsClassification(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE, classified: FALSE);
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 15);

		$this->assertEquals(['Course::status.classified'], $this->publish($eCourse), 'publier sans classement');

		$eCourse->build(['summary', 'theme', 'level'], [
			'summary' => 'Une présentation.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id']
		]);
		CourseLib::update($eCourse, ['summary', 'theme', 'level']);

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertEquals([], $this->publish($eCourse), 'publier une formation classée');

	}

	/**
	 * Sens aller, la durée : une leçon sans durée retient la publication de toute la formation.
	 */
	public function testPublishNeedsDurations(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLesson = $this->createLessonWithoutMinutes($eChapter, 'zzTest leçon sans durée');

		$this->assertEquals(['Course::status.classified'], $this->publish($eCourse), 'publier avec une leçon sans durée');

		[$properties, $cLesson] = CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse));

		$this->assertEquals([], $properties, 'le classement ne manque pas');
		$this->assertEquals([$eLesson['id']], $cLesson->getIds(), 'le panneau nomme la leçon sans durée');

		$fw = new \FailWatch();
		$eLesson->build(['minutes'], ['minutes' => 30]);
		$this->assertFalse($fw->ko(), 'la durée se pose tant que la formation est en brouillon');

		LessonLib::update($eLesson, ['minutes']);

		$this->assertEquals([], $this->publish($this->getCourse($eCourse['id'])), 'publier une fois la durée posée');

	}

	/**
	 * Sens aller, le quiz : une leçon qui se valide par un quiz encore sans question retient la
	 * publication, et le panneau la nomme avec ce qui lui manque.
	 */
	public function testPublishNeedsQuestion(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eLesson = $this->getLesson($this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon à quiz', 15)['id']);

		$fw = new \FailWatch();
		$eLesson->build(['validation', 'quizThreshold'], ['validation' => Lesson::QUIZ, 'quizThreshold' => '80']);
		$this->assertFalse($fw->ko(), 'la leçon passe en quiz sans question tant que la formation est en brouillon');

		LessonLib::update($eLesson, ['validation', 'quizThreshold']);

		$this->assertEquals(['Course::status.classified'], $this->publish($eCourse), 'publier avec un quiz sans question');

		[, $cLesson] = CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse));

		$this->assertEquals([$eLesson['id']], $cLesson->getIds(), 'le panneau nomme la leçon');
		$this->assertEquals(['questions'], $cLesson[$eLesson['id']]['missing'], 'il lui manque une question');

		// Le décor écrit la question sans le panneau : ce contrôle ne pèse que sa présence.
		QuestionLib::create(new Question(['lesson' => $eLesson, 'statement' => 'Une question ?', 'image' => NULL, 'type' => Question::SINGLE, 'explanation' => NULL]), new \Collection([
			new Choice(['label' => 'Oui', 'image' => NULL, 'correct' => TRUE, 'position' => 0]),
			new Choice(['label' => 'Non', 'image' => NULL, 'correct' => FALSE, 'position' => 1]),
		]));

		$this->assertEquals([], $this->publish($this->getCourse($eCourse['id'])), 'publier une fois la question posée');

	}

	/**
	 * Une formation en ligne se publie avec au moins une leçon (décision du 05/10) : sans
	 * module, ou avec un module vide, l'étape `status.program` la refuse et le panneau le nomme. La
	 * formation en présentiel, qui se publie sans leçon, est éprouvée dans `PublishProgram.t.php`.
	 */
	public function testPublishEmptyProgram(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$this->assertEquals(['Course::status.program'], $this->publish($eCourse), 'publier une formation en ligne sans module');

		$eChapter = $this->createChapter($eCourse, 'zzTest module vide');

		$this->assertEquals(['Course::status.program'], $this->publish($eCourse), 'publier une formation en ligne dont le seul module est vide');

		[$properties, $cLesson, $programEmpty] = CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse));

		$this->assertEquals([[], [], TRUE], [$properties, $cLesson->getIds(), $programEmpty], 'le panneau nomme la leçon qui manque, et rien d\'autre');

		$this->createLesson($eChapter, 'zzTest première leçon', 15);

		$this->assertEquals([], $this->publish($eCourse), 'publier une fois la première leçon écrite');
		$this->assertFalse(CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse))[2], 'le panneau ne la nomme plus');

		// Une propriété s'arrête à sa première étape refusée : le classement parle d'abord.
		$eCourseUnclassified = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE, classified: FALSE);

		$this->assertEquals(['Course::status.classified'], $this->publish($eCourseUnclassified), 'vide et sans classement, le classement est refusé');

	}

	/**
	 * Sens retour : les trois propriétés du classement ne se vident pas sous une formation publiée,
	 * et se vident une fois dépubliée.
	 */
	public function testEmptyClassificationWhilePublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCoursePublished = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eCourseDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		foreach(['summary', 'theme'] as $property) {

			$this->assertEquals(
				['Course::'.$property.'.classified'],
				$this->emptyProperty($eCoursePublished, $property),
				'vider '.$property.' sous une formation publiée'
			);

			$this->assertEquals([], $this->emptyProperty($eCourseDraft, $property), 'vider '.$property.' en brouillon');

		}

		// Le niveau ne suit pas les deux autres : il est facultatif jusqu'au bout, donc il se vide
		// aussi sous une formation publiée.
		$this->assertEquals([], $this->emptyProperty($eCoursePublished, 'level'), 'vider le niveau sous une formation publiée');
		$this->assertEquals([], $this->emptyProperty($eCourseDraft, 'level'), 'vider le niveau en brouillon');

	}

	/**
	 * Le niveau est facultatif jusqu'au bout, publication comprise : la publication passe sans lui,
	 * le panneau « Avant de publier » ne le nomme pas, et il se vide sur une formation publiée sans
	 * qu'il faille la dépublier.
	 */
	public function testPublishWithoutLevel(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE, classified: FALSE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$this->createLesson($eChapter, 'zzTest leçon', 15);

		$eCourse->build(['summary', 'theme'], ['summary' => 'Une présentation.', 'theme' => $this->eTheme['id']]);
		CourseLib::update($eCourse, ['summary', 'theme']);

		$eCourse = $this->getCourse($eCourse['id']);

		$this->assertTrue($eCourse['level']->empty(), 'la formation n\'a pas de niveau');

		[$properties] = CourseLib::getMissingForPublication($eCourse, ChapterLib::getByCourse($eCourse));

		$this->assertEquals([], $properties, 'le panneau ne nomme pas le niveau');
		$this->assertEquals([], $this->publish($eCourse), 'publier sans niveau');

		$eCourse['status'] = Course::PUBLISHED;
		CourseLib::update($eCourse, ['status']);

		$eCoursePublished = $this->getCourse($eCourse['id']);

		// Le niveau se pose et se retire sous une formation publiée, dans les deux sens.
		$eCoursePublished->build(['level'], ['level' => $this->eLevel['id']]);
		CourseLib::update($eCoursePublished, ['level']);

		$this->assertEquals($this->eLevel['id'], $this->getCourse($eCourse['id'])['level']['id'], 'poser le niveau sur une formation publiée');

		$this->assertEquals([], $this->emptyProperty($this->getCourse($eCourse['id']), 'level'), 'le vider ensuite, sans dépublier');

	}

	/**
	 * Sens retour, la durée, et la création d'une leçon sous une formation publiée : `minutes` reste
	 * dans les propriétés de création, c'est l'étape qui décide.
	 */
	public function testLessonMinutes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCoursePublished = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapterPublished = $this->createChapter($eCoursePublished, 'zzTest module publié');
		$eLessonPublished = $this->createLesson($eChapterPublished, 'zzTest leçon publiée', 20);

		$eCourseDraft = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapterDraft = $this->createChapter($eCourseDraft, 'zzTest module brouillon');
		$eLessonDraft = $this->createLesson($eChapterDraft, 'zzTest leçon brouillon', 20);

		$this->assertEquals(['Lesson::minutes.classified'], $this->emptyMinutes($eLessonPublished), 'vider la durée sous une formation publiée');
		$this->assertEquals([], $this->emptyMinutes($eLessonDraft), 'vider la durée en brouillon');

		$this->assertEquals(['Lesson::minutes.classified'], $this->createWithoutMinutes($eChapterPublished), 'créer une leçon sans durée sous une formation publiée');
		$this->assertEquals([], $this->createWithoutMinutes($eChapterDraft), 'créer une leçon sans durée en brouillon');

	}

	/**
	 * Les bornes du programme : `ChapterLib::locate()` traverse les modules, et rend un élément vide
	 * aux deux bouts. La barre de l'écran d'une leçon n'affiche son bouton que là-dessus.
	 */
	public function testLocateBounds(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);

		$eChapterFirst = $this->createChapter($eCourse, 'zzTest module 1');
		$eLessonFirst = $this->createLesson($eChapterFirst, 'zzTest leçon 1', 10);
		$eLessonLastOfFirst = $this->createLesson($eChapterFirst, 'zzTest leçon 2', 10);

		$eChapterSecond = $this->createChapter($eCourse, 'zzTest module 2');
		$eLessonFirstOfSecond = $this->createLesson($eChapterSecond, 'zzTest leçon 3', 10);

		$cChapter = ChapterLib::getByCourse($eCourse);

		$eLesson = $this->getLesson($eLessonFirst['id']);
		ChapterLib::locate($cChapter, $eLesson);
		$this->assertTrue($eLesson['eLessonPrevious']->empty(), 'la première leçon n\'a pas de précédente');
		$this->assertEquals($eLessonLastOfFirst['id'], $eLesson['eLessonNext']['id'], 'la suivante dans le même module');

		$eLesson = $this->getLesson($eLessonLastOfFirst['id']);
		ChapterLib::locate($cChapter, $eLesson);
		$this->assertEquals($eLessonFirstOfSecond['id'], $eLesson['eLessonNext']['id'], 'la suivante est la première du module d\'après');

		$eLesson = $this->getLesson($eLessonFirstOfSecond['id']);
		ChapterLib::locate($cChapter, $eLesson);
		$this->assertEquals($eLessonLastOfFirst['id'], $eLesson['eLessonPrevious']['id'], 'la précédente est la dernière du module d\'avant');
		$this->assertTrue($eLesson['eLessonNext']->empty(), 'la dernière leçon n\'a pas de suivante');

	}

	/**
	 * La durée non estimée est une valeur, pas un zéro : un module dont aucune leçon n'est estimée
	 * et une formation sans leçon rendent `NULL`, que `CourseUi::getDuration()` ne rend pas comme un zéro.
	 */
	public function testDurationNotEstimated(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$this->createLessonWithoutMinutes($eChapter, 'zzTest leçon sans durée');

		$cChapter = ChapterLib::getByCourse($eCourse);

		$this->assertEquals(NULL, $cChapter->first()['minutes'], 'un module sans aucune estimation');
		$this->assertEquals(NULL, ChapterLib::sumMinutes($cChapter), 'une formation sans aucune estimation');

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		CourseLib::fillCounts($cCourse);
		$this->assertEquals(NULL, $cCourse->first()['minutes'], 'les compteurs de la liste');

		$this->createLessonWritten($eChapter, 'zzTest leçon estimée', 45);

		$cChapter = ChapterLib::getByCourse($eCourse);
		$this->assertEquals(45, $cChapter->first()['minutes'], 'le module n\'additionne que ce qui est estimé');

		$this->assertTrue(CourseUi::getDuration(NULL) !== CourseUi::getDuration(0), 'non estimée, la durée ne se rend pas comme un zéro');
		$this->assertEquals(s("{value}&nbsp;min", 45), CourseUi::getDuration(45), 'une durée estimée');

	}

	/**
	 * Une formation en ligne publiée garde au moins une leçon, le retour de l'étape `status.program`
	 * (décision du 05/10, retour tranché par V-Learning) : ni la dernière leçon ni le
	 * module qui la porte ne se proposent, sans qu'aucun inscrit l'ait suivie, et le Lib les refuse en
	 * le nommant. Un module vide reste libre, une leçon parmi d'autres aussi, et la même formation se
	 * vide une fois dépubliée. La formation en présentiel, qui se vide, est dans `PublishProgram.t.php`.
	 */
	public function testDeleteLastLessonWhilePublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::PRIVATE);
		$eChapterEmpty = $this->createChapter($eCourse, 'zzTest module vide');
		$eChapter = $this->createChapter($eCourse, 'zzTest module unique');
		$eLesson = $this->createLesson($eChapter, 'zzTest leçon unique', 15);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);
		[$eChapterEmptyProgram] = $this->getProgram($eCourse, $eChapterEmpty, new Lesson());

		$this->assertFalse($eLessonProgram->assertDelete(), 'l\'écran ne propose pas la dernière leçon');
		$this->assertFalse($eChapterProgram->assertDelete(), 'ni le module qui la porte');
		$this->assertTrue($eChapterEmptyProgram->assertDelete(), 'un module sans leçon reste libre');

		$this->assertFalse(str_contains(new LessonUi()->getActions($eLessonProgram), 'data-ajax="/learning/lesson:doDelete"'), 'la roue de la leçon n\'offre pas la suppression');
		$this->assertFalse(str_contains(new ChapterUi()->getActions($eChapterProgram), 'data-ajax="/learning/chapter:doDelete"'), 'celle du module non plus');

		$this->assertEquals(['Lesson::deleteLastPublished'], $this->deleteLesson($eLessonProgram), 'le Lib refuse la dernière leçon');

		// Le Lib relit le statut et le type sous le verrou de la formation, pas ceux de l'élément qu'on lui passe.
		$eLessonStale = clone $eLessonProgram;
		$eLessonStale['course'] = new Course(['id' => $eCourse['id'], 'status' => Course::DRAFT, 'type' => Course::ONSITE]);

		$this->assertEquals(['Lesson::deleteLastPublished'], $this->deleteLesson($eLessonStale), 'un statut périmé sur l\'élément ne fait pas passer la suppression');
		$this->assertEquals(['Chapter::deleteLastPublished'], $this->deleteChapter($eChapterProgram), 'et le module qui la porte');
		$this->assertEquals([1, 2], [
			Lesson::model()->whereCourse($eCourse)->count(),
			Chapter::model()->whereCourse($eCourse)->count()
		], 'rien n\'est parti');

		$eLessonSecond = $this->createLesson($eChapterEmpty, 'zzTest seconde leçon', 20);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);

		$this->assertTrue($eLessonProgram->assertDelete(), 'avec une autre leçon ailleurs, la leçon se propose');
		$this->assertTrue($eChapterProgram->assertDelete(), 'et son module aussi');

		LessonLib::delete($this->getProgram($eCourse, $eChapterEmpty, $eLessonSecond)[1]);

		$eCourseDraft = $this->getCourse($eCourse['id']);
		$eCourseDraft['status'] = Course::DRAFT;
		CourseLib::update($eCourseDraft, ['status']);

		[$eChapterProgram, $eLessonProgram] = $this->getProgram($eCourse, $eChapter, $eLesson);

		$this->assertTrue($eLessonProgram->assertDelete(), 'en brouillon, la dernière leçon se propose');
		$this->assertTrue(str_contains(new LessonUi()->getActions($eLessonProgram), 'data-ajax="/learning/lesson:doDelete"'), 'et sa roue la propose');
		$this->assertEquals([], $this->deleteChapter($eChapterProgram), 'et le module se supprime');
		$this->assertEquals(0, Lesson::model()->whereCourse($eCourse)->count(), 'la formation en brouillon n\'a plus de leçon');

	}

	/**
	 * `acceptDelete()` d'une leçon retombe sur `acceptWrite()`, qui porte « une ferme ne porte pas
	 * de formation » : `canWrite()` ne pèse que le membre connecté, et rien d'autre ne fermerait la
	 * suppression à une formation qu'aucune structure du produit ne peut écrire. La leçon ne retient
	 * ni tout le programme d'une formation publiée (`holdsProgram`) ni de lecteur complet
	 * (`holdsCompleted`), pour que le seul refus possible soit celui-là.
	 */
	public function testDeleteKeepsAcceptWrite(): void {

		$lessonOf = function(string $type, ?string $communityType): Lesson {

			$eStructure = new \structure\Structure(['id' => 99999102, 'type' => $type, 'communityType' => $communityType]);
			// Les formations allumées : la règle éprouvée est celle du type de structure.
			$eStructure->conf(\structure\Feature::class)['hasCommunityLearning'] = TRUE;

			return new Lesson([
				'holdsProgram' => FALSE,
				'holdsCompleted' => FALSE,
				'course' => new Course([
					'community' => $eStructure
				])
			]);

		};

		$this->assertFalse($lessonOf(\structure\Structure::PRODUCER, NULL)->acceptDelete(), 'une ferme ne porte pas de formation');
		$this->assertFalse($lessonOf(\structure\Structure::COMMUNITY, \structure\Structure::ACCOUNTING)->acceptDelete(), 'ni un groupe comptable');

		$this->assertTrue($lessonOf(\structure\Structure::COMMUNITY, \structure\Structure::EDUCATION)->acceptDelete(), 'un groupe de formation, oui');
		$this->assertTrue($lessonOf(\structure\Structure::COMMUNITY, \structure\Structure::NETWORK)->acceptDelete(), 'un réseau aussi');

	}

	/** Le module et la leçon tels que l'écran du programme les tient. */
	private function getProgram(Course $eCourse, Chapter $eChapter, Lesson $eLesson): array {

		$eCourseProgram = $this->getCourse($eCourse['id']);

		$cChapter = ChapterLib::getByCourse($eCourseProgram);
		ChapterLib::fillHolds($eCourseProgram, $cChapter);

		$eChapterProgram = $cChapter[$eChapter['id']];
		$eLessonProgram = $eChapterProgram['cLesson']->find(fn(Lesson $e) => $e['id'] === $eLesson['id'])->first();

		return [$eChapterProgram, $eLessonProgram];

	}

	/** Les noms des refus levés en supprimant une leçon. */
	private function deleteLesson(Lesson $eLesson): array {

		$fw = new \FailWatch();

		LessonLib::delete($eLesson);

		return array_column($fw->get(), 1);

	}

	/** Les noms des refus levés en supprimant un module. */
	private function deleteChapter(Chapter $eChapter): array {

		$fw = new \FailWatch();

		ChapterLib::delete($eChapter);

		return array_column($fw->get(), 1);

	}

	/** Les noms des refus levés par la publication, sans l'écrire. */
	private function publish(Course $eCourse): array {

		$fw = new \FailWatch();

		$eCourseClone = clone $eCourse;
		$eCourseClone->build(['status'], ['status' => Course::PUBLISHED]);

		return array_column($fw->get(), 1);

	}

	/** Les noms des refus levés en vidant une propriété du classement, sans l'écrire. */
	private function emptyProperty(Course $eCourse, string $property): array {

		$fw = new \FailWatch();

		$eCourseClone = clone $eCourse;
		$eCourseClone->build([$property], [$property => '']);

		return array_column($fw->get(), 1);

	}

	private function emptyMinutes(Lesson $eLesson): array {

		$fw = new \FailWatch();

		$eLessonClone = $this->getLesson($eLesson['id']);
		$eLessonClone['cChapter'] = ChapterLib::getByCourse($eLessonClone['course']);
		$eLessonClone->build(['minutes'], ['minutes' => '']);

		return array_column($fw->get(), 1);

	}

	private function createWithoutMinutes(Chapter $eChapter): array {

		$fw = new \FailWatch();

		$eLesson = new Lesson(['course' => $eChapter['course'], 'chapter' => $eChapter]);
		$eLesson['cChapter'] = ChapterLib::getByCourse($eChapter['course']);
		$eLesson->build(LessonLib::getPropertiesCreate(), ['name' => 'zzTest sans durée'], new \PropertyList('create'));

		return array_column($fw->get(), 1);

	}

	private function createLessonWithoutMinutes(Chapter $eChapter, string $name): Lesson {

		$eLesson = new Lesson(['course' => $eChapter['course'], 'chapter' => $eChapter]);

		$fw = new \FailWatch();

		$eLesson->build(['name'], ['name' => $name], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('leçon '.$name.' refusée : '.json_encode($fw->get())));

		LessonLib::create($eLesson);

		return $eLesson;

	}

}
?>
