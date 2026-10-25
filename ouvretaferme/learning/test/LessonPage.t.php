<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * La page d'une leçon, `/formation/lecon/{id}` : ce que la page prépare pour dire ce que la leçon
 * contient, lu sur ses blocs et non sur le format que l'auteur a saisi (lot T12a), puis les leçons
 * écrites du programme, que la colonne et la paire du bas de page lisent (lot T12b).
 *
 * Les leçons sont celles d'un tutoriel publié du décor (`LearningTutorialFixtureTest`), que le
 * visiteur lit sans compte. Toutes se saisissent au format texte : la page ne doit rien en tirer.
 *
 * Lancement : dev/test package=learning file=LessonPage
 */
class LessonPageTest extends LearningTutorialFixtureTest {

	/**
	 * Les étapes numérotées se comptent sur toutes les listes de la leçon, sans les étapes vides,
	 * que la lecture retire (`BlockLib::getReadable()`), ni une liste de critères cochés. Une vidéo
	 * se lit sur son bloc. Une leçon sans bloc ne porte rien à lire.
	 */
	public function testContent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module tutoriel');

		$eLessonSteps = $this->createLesson($eChapter, 'zzTest leçon en étapes', 5);
		$eBlockSteps = $this->createBlock($eLessonSteps, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');
		$this->createStep($eBlockSteps, 'Dessiner le contour');
		$this->createStep($eBlockSteps, NULL);
		$this->createStep($this->createBlock($eLessonSteps, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Nommer le jardin'), 'Enregistrer');
		$this->createBlock($eLessonSteps, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::CHECK, 'Le jardin a un nom');

		$eLessonVideo = $this->createLesson($eChapter, 'zzTest leçon en vidéo', 4);
		$this->createStep($this->createBlock($eLessonVideo, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Lancer la vidéo'), 'La regarder');

		// La vidéo s'écrit hors de `build()`, comme `Api.t.php` la pose : l'adresse est celle d'une
		// source reconnue, ce que le test éprouve n'est pas sa validation.
		\block\BlockLib::create(new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $eLessonVideo['id'],
			'type' => \block\BlockTypeLib::VIDEO,
			'variant' => \block\BlockTypeLib::FULL,
			'url' => 'https://vimeo.com/76979871',
			'title' => 'Le jardin',
		]));

		$eLessonEmpty = $this->createLesson($eChapter, 'zzTest leçon vide', 3);
		$this->createBlock($eLessonEmpty, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, NULL);

		$this->actAs(new \user\User());

		$read = function(Lesson $eLesson): Lesson {

			$data = $this->executeView('formation/lecon/'.$eLesson['id'], []);

			$this->assertTrue($data instanceof \stdClass, 'la leçon '.$eLesson['name'].' répond au visiteur : '.(is_string($data) ? $data : ''));

			return ($data instanceof \stdClass) ? $data->eLesson : new Lesson();

		};

		$eLessonStepsRead = $read($eLessonSteps);
		$this->assertEquals(4, $eLessonStepsRead['steps'], 'quatre étapes écrites sur deux listes numérotées, ni l\'étape vide ni le critère coché');
		$this->assertTrue($eLessonStepsRead['eBlockVideo']->empty(), 'sans bloc vidéo, la leçon n\'a pas de vidéo, quel que soit son format');

		$eLessonVideoRead = $read($eLessonVideo);
		$this->assertTrue($eLessonVideoRead['eBlockVideo']->notEmpty(), 'la vidéo se lit sur son bloc, la leçon étant saisie au format texte');
		$this->assertEquals(2, $eLessonVideoRead['steps'], 'ses étapes se comptent aussi');

		$eLessonEmptyRead = $read($eLessonEmpty);
		$this->assertTrue($eLessonEmptyRead['cBlock']->empty(), 'une liste sans étape écrite ne se lit pas : la leçon est vide');
		$this->assertEquals(0, $eLessonEmptyRead['steps'], 'une leçon vide n\'a pas d\'étape');

	}

	/**
	 * Une leçon est écrite quand elle a quelque chose à lire ou à faire (`Lesson::isWritten()`) : un
	 * bloc que la lecture garde (`BlockLib::getReadable()`), ou un quiz. Une leçon sans bloc ni quiz,
	 * ou dont les blocs sont vides, ne l'est pas. La paire mène aux voisines écrites sur toute la
	 * formation, d'un module à l'autre, et la progression compte toutes les leçons, comme la fin de la
	 * formation (`Course::isDone()`). Une leçon vide ouverte par un lien direct a ses voisines comme
	 * les autres.
	 */
	public function testWritten(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$eChapterSecond = $this->createChapter($eCourse, 'zzTest second module');

		$eLessonFirst = $this->createLesson($eChapterFirst, 'zzTest première leçon', 5);
		$this->createBlock($eLessonFirst, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');

		$eLessonBare = $this->createLesson($eChapterFirst, 'zzTest leçon sans bloc', 5);

		$eLessonThird = $this->createLesson($eChapterFirst, 'zzTest troisième leçon', 5);
		$this->createBlock($eLessonThird, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Nommer le jardin');

		$eLessonBlank = $this->createLesson($eChapterSecond, 'zzTest leçon aux blocs vides', 5);
		$this->createBlock($eLessonBlank, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, NULL);

		$eLessonQuiz = $this->createLesson($eChapterSecond, 'zzTest quiz sans bloc', 5);

		// Posé sans `build()`, qui refuse un quiz sans question (`Lesson::validation.questioned`) : la
		// règle de la leçon vide ne lit que la validation, et c'est elle que le cas éprouve.
		$eLessonQuiz['validation'] = Lesson::QUIZ;
		$eLessonQuiz['quizThreshold'] = 50;
		LessonLib::update($eLessonQuiz, ['validation', 'quizThreshold']);

		$eLessonLast = $this->createLesson($eChapterSecond, 'zzTest dernière leçon', 5);
		$this->createBlock($eLessonLast, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Enregistrer');

		$this->actAs(new \user\User());

		$read = function(Lesson $eLesson): \stdClass {

			$data = $this->executeView('formation/lecon/'.$eLesson['id'], []);

			$this->assertTrue($data instanceof \stdClass, 'la leçon '.$eLesson['name'].' répond au visiteur : '.(is_string($data) ? $data : ''));

			return ($data instanceof \stdClass) ? $data : new \stdClass();

		};

		$neighbours = fn(\stdClass $data): array => [
			$data->eLesson['eLessonPreviousWritten']->empty() ? NULL : $data->eLesson['eLessonPreviousWritten']['id'],
			$data->eLesson['eLessonNextWritten']->empty() ? NULL : $data->eLesson['eLessonNextWritten']['id'],
		];

		$data = $read($eLessonThird);

		$written = [];

		foreach($data->cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$written[$eLesson['id']] = $eLesson['written'];
			}
		}

		$this->assertEquals([
			$eLessonFirst['id'] => TRUE,
			$eLessonBare['id'] => FALSE,
			$eLessonThird['id'] => TRUE,
			$eLessonBlank['id'] => FALSE,
			$eLessonQuiz['id'] => TRUE,
			$eLessonLast['id'] => TRUE,
		], $written, 'écrites, les leçons dont la lecture garde un bloc, et le quiz sans bloc');

		$this->assertEquals([6, 0], [$data->eCourse['lessons'], $data->eCourse['done']], 'la progression compte toutes les leçons, comme la fin de la formation, et le visiteur n\'en a suivi aucune');
		$this->assertEquals([$eLessonFirst['id'], $eLessonQuiz['id']], $neighbours($data), 'la paire saute les leçons vides, d\'un module à l\'autre, et propose le quiz sans bloc');
		$this->assertTrue($data->eCourse['isTutorial'], 'un tutoriel ramène au sommaire des tutoriels');

		$this->assertEquals([NULL, $eLessonThird['id']], $neighbours($read($eLessonFirst)), 'la première leçon n\'a pas de précédente');
		$this->assertEquals([$eLessonQuiz['id'], NULL], $neighbours($read($eLessonLast)), 'la dernière n\'a pas de suivante');

		$dataQuiz = $read($eLessonQuiz);

		$this->assertEquals([TRUE, TRUE], [$dataQuiz->eLesson->isWritten($dataQuiz->eLesson['cBlock']), $dataQuiz->eLesson['cBlock']->empty()], 'ouvert, le quiz sans bloc est écrit sur sa propre page');
		$this->assertEquals([$eLessonFirst['id'], $eLessonThird['id']], $neighbours($read($eLessonBare)), 'une leçon vide ouverte par son adresse a ses voisines écrites');

	}

	/**
	 * Une leçon vide n'est un lien de la colonne que pour qui peut la cocher : le droit de `doDone`
	 * (`Course::canFollow()`), qu'une leçon obligatoire vide doit laisser atteindre pour que la
	 * formation se termine. Le visiteur et le compte qui n'est pas inscrit n'ont pas ce droit, et le
	 * geste leur est refusé ; inscrit, le même compte l'a, et coche la leçon vide.
	 */
	public function testEmptyLessonFollow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module tutoriel');

		$eLessonWritten = $this->createLesson($eChapter, 'zzTest leçon écrite', 5);
		$this->createBlock($eLessonWritten, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');

		$eLessonEmpty = $this->createLesson($eChapter, 'zzTest leçon vide', 5);

		$follow = function() use($eLessonWritten, $eLessonEmpty): array {

			$data = $this->executeView('formation/lecon/'.$eLessonWritten['id'], []);

			$this->assertTrue($data instanceof \stdClass, 'la leçon écrite répond : '.(is_string($data) ? $data : ''));

			return [
				($data instanceof \stdClass) ? $data->eCourse['canFollow'] : NULL,
				$this->executePage('learning/lesson:doDone', ['id' => (string)$eLessonEmpty['id']])[0],
			];

		};

		$this->actAs(new \user\User());

		[$canFollow, $done] = $follow();
		$this->assertFalse($canFollow, 'le visiteur ne coche rien : la leçon vide n\'est pas un lien');
		$this->assertEquals(\RedirectAction::class, $done, 'et le geste le renvoie à la connexion');

		$this->actAs($this->eUserReader);

		[$canFollow, $done] = $follow();
		$this->assertFalse($canFollow, 'connecté sans inscription, pas davantage');
		$this->assertEquals(\NotAllowedAction::class, $done, 'ni le geste');

		// Hors de `build()`, comme `Progress.t.php` inscrit son lecteur : le test éprouve ce que
		// l'inscription ouvre, pas la demande qui y mène.
		EnrollmentLib::create(new Enrollment(['user' => $this->eUserReader, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		[$canFollow, $done] = $follow();
		$this->assertTrue($canFollow, 'inscrit, le lecteur coche : la leçon vide est un lien');
		$this->assertEquals(\ReloadAction::class, $done, 'et il la coche');
		$this->assertTrue(ProgressLib::getDoneByUserAndCourse($this->eUserReader, $eCourse)->offsetExists($eLessonEmpty['id']), 'la leçon vide est suivie');

	}

	private function createStep(\block\Block $eBlockParent, ?string $title): void {

		// Hors de `build()`, pour la même raison que `createBlock()` : l'étape vide est le cas éprouvé.
		\block\BlockLib::create(new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $eBlockParent['reference'],
			'parent' => $eBlockParent,
			'type' => \block\BlockTypeLib::ITEM,
			'title' => $title,
			'content' => NULL,
		]));

	}

}
?>
