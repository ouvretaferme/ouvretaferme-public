<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * Le sommaire des tutoriels, `/tutoriels` (lot T3b) : ce que la page prépare pour le visiteur, dont
 * le nombre de questions de la FAQ par module (lot T7), et le réseau des tutoriels écarté de la
 * page publique des formations d'un réseau.
 *
 * Le réseau des tutoriels est celui du décor (`LearningTutorialFixtureTest`) ; le sommaire se lit
 * sans compte.
 *
 * Lancement : dev/test package=learning file=TutorialPage
 */
class TutorialPageTest extends LearningTutorialFixtureTest {

	/**
	 * Seule la formation en ligne publiée du réseau des tutoriels paraît : ni son brouillon, ni la
	 * formation publiée d'un autre réseau, ni celle de l'équipe, que le droit de lire n'écarterait
	 * pas toutes (`Course::acceptTutorial()` ouvre le brouillon). Ses modules se rangent par phase,
	 * dans l'ordre des phases et non dans celui de leur création, ceux sans phase à la fin, et se
	 * numérotent dans cet ordre.
	 */
	public function testSummary(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eCourseDraft = $this->createCourse($this->eStructureTutorial, Course::DRAFT, Course::PRIVATE);
		$eCourseOther = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$ePhaseFirst = $this->createPhase($eCourse, 'zzTest première phase');
		$ePhaseSecond = $this->createPhase($eCourse, 'zzTest seconde phase');

		// Créés dans un autre ordre que celui du programme : la position seule les rangerait mal.
		$eChapterSecond = $this->createChapterInPhase($eCourse, 'zzTest module de la seconde phase', $ePhaseSecond);
		$eChapterWithout = $this->createChapterInPhase($eCourse, 'zzTest module sans phase', new Phase());
		$eChapterFirst = $this->createChapterInPhase($eCourse, 'zzTest module de la première phase', $ePhaseFirst);

		$this->createBlock($this->createLesson($eChapterFirst, 'zzTest leçon 1', 3), \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');
		$this->createLesson($eChapterFirst, 'zzTest leçon 2', 5);
		$this->createLesson($eChapterSecond, 'zzTest leçon 3', 4);

		$this->actAs(new \user\User());

		$data = $this->executeView('tutoriels', []);

		$this->assertTrue($data instanceof \stdClass, 'la page répond au visiteur sans compte');
		$this->assertEquals([$eCourse['id']], $data->cCourse->getIds(), 'seule la formation publiée du réseau des tutoriels paraît, ni '.$eCourseDraft['id'].', ni '.$eCourseOther['id'].', ni '.$eCourseTeam['id']);

		$eCourseRead = $data->cCourse->first();

		$this->assertEquals(3, $eCourseRead['chapters'], 'trois modules');
		$this->assertEquals(3, $eCourseRead['lessons'], 'trois leçons, vides comprises');
		$this->assertEquals(3, $eCourseRead['minutes'], 'les trois minutes de la seule leçon écrite, la durée d\'une leçon vide ne comptant pas');

		$this->assertEquals([$ePhaseFirst['id'], $ePhaseSecond['id']], $eCourseRead['cPhase']->getIds(), 'les phases dans leur ordre');
		$this->assertEquals([$eChapterFirst['id']], $eCourseRead['cPhase'][$ePhaseFirst['id']]['cChapter']->getIds(), 'la première phase porte son module');
		$this->assertEquals([$eChapterSecond['id']], $eCourseRead['cPhase'][$ePhaseSecond['id']]['cChapter']->getIds(), 'la seconde aussi');
		$this->assertEquals([$eChapterWithout['id']], $eCourseRead['cChapterWithoutPhase']->getIds(), 'le module sans phase est à part');

		$numbers = [];

		foreach([$eChapterFirst, $eChapterSecond, $eChapterWithout] as $eChapter) {
			$numbers[] = $this->getChapterRead($eCourseRead, $eChapter)['number'];
		}

		$this->assertEquals([1, 2, 3], $numbers, 'les modules se numérotent phase par phase, sans phase à la fin');

		$lessons = [];

		foreach([$eChapterFirst, $eChapterSecond] as $eChapter) {
			foreach($this->getChapterRead($eCourseRead, $eChapter)['cLesson'] as $eLesson) {
				$lessons[] = [$eLesson['number'], $eLesson['chapterNumber'], $eLesson['numberInChapter']];
			}
		}

		$this->assertEquals([[1, 1, 1], [2, 1, 2], [3, 2, 1]], $lessons, 'une leçon garde son rang dans la formation, porte le numéro de son module et prend son rang dans le module, qui repart à 1');

		$ePhaseFirstRead = $eCourseRead['cPhase'][$ePhaseFirst['id']];
		$ePhaseSecondRead = $eCourseRead['cPhase'][$ePhaseSecond['id']];

		$this->assertEquals([1, 1, 1, 1, 2, 3], [$ePhaseFirstRead['number'], $ePhaseFirstRead['tint'], $ePhaseFirstRead['chapterFrom'], $ePhaseFirstRead['chapterTo'], $ePhaseFirstRead['lessons'], $ePhaseFirstRead['minutes']], 'la première phase : rang, teinte, modules, leçons et minutes de sa leçon écrite');
		$this->assertEquals([2, 2, 2, 2, 1, NULL], [$ePhaseSecondRead['number'], $ePhaseSecondRead['tint'], $ePhaseSecondRead['chapterFrom'], $ePhaseSecondRead['chapterTo'], $ePhaseSecondRead['lessons'], $ePhaseSecondRead['minutes']], 'la seconde, dont la seule leçon est vide : sans durée');

		$this->assertEquals([3, 3, 3], [$data->chapters, $data->lessons, $data->minutes], 'les chiffres de la bande');
		$this->assertEquals($eChapterFirst['id'], $data->eChapterStart['id'], 'on commence par le premier module montré');

	}

	/**
	 * Une phase sans module ne prend pas de rang, et la teinte reprend la première au-delà des
	 * quatre du design system. Le départ saute un module sans leçon.
	 */
	public function testPhaseRanks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$phases = [];

		foreach(['un', 'vide', 'deux', 'trois', 'quatre', 'cinq'] as $name) {
			$phases[$name] = $this->createPhase($eCourse, 'zzTest phase '.$name);
		}

		$chapters = [];

		foreach(['un', 'deux', 'trois', 'quatre', 'cinq'] as $name) {
			$chapters[$name] = $this->createChapterInPhase($eCourse, 'zzTest module '.$name, $phases[$name]);
		}

		$this->createBlock($this->createLesson($chapters['deux'], 'zzTest leçon', 3), \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');

		$this->actAs(new \user\User());

		$data = $this->executeView('tutoriels', []);

		$this->assertTrue($data instanceof \stdClass, 'la page répond');
		$this->assertEquals([$eCourse['id']], $data->cCourse->getIds(), 'la formation du test est la seule publiée du réseau');

		$eCourseRead = $data->cCourse->first();

		$ranks = [];

		foreach($phases as $name => $ePhase) {
			$ePhaseRead = $eCourseRead['cPhase'][$ePhase['id']];
			$ranks[$name] = $ePhaseRead['cChapter']->empty() ? NULL : [$ePhaseRead['number'], $ePhaseRead['tint']];
		}

		$this->assertEquals(['un' => [1, 1], 'vide' => NULL, 'deux' => [2, 2], 'trois' => [3, 3], 'quatre' => [4, 4], 'cinq' => [5, 1]], $ranks, 'la phase vide est sautée, la cinquième reprend la première teinte');
		$this->assertEquals($chapters['deux']['id'], $data->eChapterStart['id'], 'le départ est le premier module qui a une leçon');

	}

	/**
	 * Une leçon vide (`Lesson::isWritten()`) se montre « À venir » et ne mène nulle part : le sommaire
	 * se lit comme le visiteur, qui ne peut rien cocher. Le départ et la recherche d'un module mènent
	 * à sa première leçon écrite, et le départ saute le module qui n'en a aucune. Les chiffres
	 * comptent toutes les leçons, comme la fiche. L'absence de lien se lit dans le rendu : c'est le
	 * contrat de la règle (aucune adresse vers une page vide), que l'écran ne montre pas.
	 */
	public function testEmptyLessons(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$eChapterEmpty = $this->createChapter($eCourse, 'zzTest module sans leçon écrite');
		$eChapterWritten = $this->createChapter($eCourse, 'zzTest module écrit en partie');

		$eLessonAlone = $this->createLesson($eChapterEmpty, 'zzTest leçon seule et vide', 3);
		$eLessonBlank = $this->createLesson($eChapterWritten, 'zzTest leçon aux blocs vides', 3);
		$this->createBlock($eLessonBlank, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, NULL);
		$eLessonWritten = $this->createLesson($eChapterWritten, 'zzTest leçon écrite', 4);
		$this->createBlock($eLessonWritten, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'Ouvrir le plan');

		$this->actAs(new \user\User());

		$data = $this->executeView('tutoriels', []);

		$this->assertTrue($data instanceof \stdClass, 'la page répond au visiteur');

		$eCourseRead = $data->cCourse->first();
		$eChapterEmptyRead = $this->getChapterRead($eCourseRead, $eChapterEmpty);
		$eChapterWrittenRead = $this->getChapterRead($eCourseRead, $eChapterWritten);

		$written = [];

		foreach([$eChapterEmptyRead, $eChapterWrittenRead] as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$written[$eLesson['id']] = [$eLesson['written'], $eLesson->isLinked($eCourseRead)];
			}
		}

		$this->assertEquals([
			$eLessonAlone['id'] => [FALSE, FALSE],
			$eLessonBlank['id'] => [FALSE, FALSE],
			$eLessonWritten['id'] => [TRUE, TRUE],
		], $written, 'seule la leçon qui a un bloc lisible est écrite, et seule une leçon écrite est un lien');

		$this->assertEquals($eChapterWritten['id'], $data->eChapterStart['id'], 'le départ saute le module sans leçon écrite');
		$this->assertEquals($eLessonWritten['id'], $data->eChapterStart['eLessonFirst']['id'], 'et mène à la première leçon écrite de son module, après la leçon aux blocs vides');
		$this->assertTrue($eChapterEmptyRead['eLessonFirst']->empty(), 'le module sans leçon écrite n\'a rien à ouvrir');
		$this->assertEquals([2, 3], [$data->chapters, $data->lessons], 'les chiffres comptent toutes les leçons');

		$ui = new TutorialUi();
		$h = $ui->getHero($data->cCourse, $data->chapters, $data->lessons, $data->minutes).$ui->getSummary($data->cCourse, $data->faqsByChapter).$ui->getStart($data->eChapterStart);

		// Une adresse se cherche entière, jamais comme le début d'une autre (`/lecon/12` dans `/lecon/123`),
		// dans les liens comme dans les suggestions de la recherche, dont le JSON échappe les barres.
		$h = str_replace('\\/', '/', $h);
		$hasUrl = fn(Lesson $eLesson): bool => preg_match('#'.preg_quote(LessonUi::url($eLesson), '#').'(?![0-9])#', $h) === 1;

		$this->assertFalse($hasUrl($eLessonAlone), 'aucune adresse vers la leçon seule et vide');
		$this->assertFalse($hasUrl($eLessonBlank), 'ni vers la leçon aux blocs vides, recherche comprise');
		$this->assertTrue($hasUrl($eLessonWritten), 'la leçon écrite a la sienne');

	}

	/**
	 * Un module compte ses questions publiées de la FAQ, comme la FAQ les montre : ni le brouillon,
	 * ni la question d'une autre structure. Un module sans question n'a pas d'entrée.
	 */
	public function testFaqsByChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$eChapterAsked = $this->createChapter($eCourse, 'zzTest module questionné');
		$eChapterDraft = $this->createChapter($eCourse, 'zzTest module au brouillon de question');
		$eChapterQuiet = $this->createChapter($eCourse, 'zzTest module sans question');

		$this->createFaqInChapter($this->eStructureTutorial, $eChapterAsked, Faq::PUBLISHED);
		$this->createFaqInChapter($this->eStructureTutorial, $eChapterAsked, Faq::PUBLISHED);
		$this->createFaqInChapter($this->eStructureTutorial, $eChapterAsked, Faq::DRAFT);
		$this->createFaqInChapter($this->eStructureTutorial, $eChapterDraft, Faq::DRAFT);
		$this->createFaqInChapter($this->getCommunity(self::COMMUNITY_EDUCATION), $eChapterAsked, Faq::PUBLISHED);

		$this->actAs(new \user\User());

		$data = $this->executeView('tutoriels', []);

		$this->assertEquals([$eChapterAsked['id'] => 2], $data->faqsByChapter, 'les deux questions publiées du module, sans les brouillons ni la question d\'une autre structure, et aucune entrée pour '.$eChapterDraft['id'].' ni '.$eChapterQuiet['id']);

	}

	/**
	 * Sans réseau des tutoriels, l'adresse n'existe pas.
	 */
	public function testWithoutNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$structureTutorial = NULL;

		$this->actAs(new \user\User());

		$this->assertEquals(\NotExistsAction::class, $this->executeView('tutoriels', []), 'sans réseau des tutoriels, la page répond 404');

	}

	/**
	 * Un tutoriel ne s'inscrit pas : la page publique des formations du réseau des tutoriels répond
	 * comme une adresse inconnue, même avec une formation publiée. La vue d'erreur se lève comme
	 * celle de la liste (`ViewAction`) : ce qui les distingue, c'est que la page d'erreur part avant
	 * d'avoir lu les formations.
	 */
	public function testPublicExcluded(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureTutorial);

		if($eCommunity->empty()) {
			$this->assertFail('le réseau des tutoriels '.self::COMMUNITY_TUTORIAL.' n\'a pas d\'adresse publique dans cette base');
			return;
		}

		$this->actAs(new \user\User());

		$data = $this->executeView('reseau/'.$eCommunity['fqn'].'/formations', ['fqn' => $eCommunity['fqn']]);

		$this->assertTrue($data instanceof \stdClass, 'la page d\'erreur se lève');
		$this->assertFalse(property_exists($data, 'cCourse'), 'la page publique du réseau des tutoriels ne lit pas ses formations');

		LearningSetting::$structureTutorial = NULL;

		$data = $this->executeView('reseau/'.$eCommunity['fqn'].'/formations', ['fqn' => $eCommunity['fqn']]);

		$this->assertTrue(property_exists($data, 'cCourse'), 'redevenu un réseau ordinaire, il montre ses formations');

	}

	private function createPhase(Course $eCourse, string $name): Phase {

		$ePhase = new Phase(['course' => $eCourse]);

		$fw = new \FailWatch();

		$ePhase->build(['name'], ['name' => $name], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('phase '.$name.' refusée : '.json_encode($fw->get())));

		PhaseLib::create($ePhase);

		return $ePhase;

	}

	private function createChapterInPhase(Course $eCourse, string $name, Phase $ePhase): Chapter {

		$eChapter = $this->createChapter($eCourse, $name);

		// Posée sans `build()`, qui vérifierait que la phase est de la formation du module
		// (`Chapter::phase.consistent`) : le décor la prend dans cette formation même.
		$eChapter['phase'] = $ePhase;

		ChapterLib::update($eChapter, ['phase']);

		return $eChapter;

	}

	/**
	 * Posée sans `build()`, qui refuserait par `Faq::chapter.owned` le module d'une autre structure :
	 * c'est l'état que le compte doit ignorer, et que seule une écriture forgée produirait.
	 */
	private function createFaqInChapter(\structure\Structure $eStructure, Chapter $eChapter, string $status): void {

		FaqLib::create(new Faq([
			'community' => $eStructure,
			'question' => 'zzTest question '.uniqid(),
			'answer' => '<p>zzTest réponse</p>',
			'symptoms' => NULL,
			'steps' => NULL,
			'notes' => NULL,
			'demoPage' => NULL,
			'demoPath' => NULL,
			'keywords' => NULL,
			'screen' => NULL,
			'verdict' => Faq::POSSIBLE,
			'status' => $status,
			'chapter' => $eChapter,
			'discordUrl' => NULL,
		]));

	}

	private function getChapterRead(Course $eCourse, Chapter $eChapter): Chapter {

		foreach($eCourse['cPhase'] as $ePhase) {
			if($ePhase['cChapter']->offsetExists($eChapter['id'])) {
				return $ePhase['cChapter'][$eChapter['id']];
			}
		}

		return $eCourse['cChapterWithoutPhase'][$eChapter['id']];

	}

}
?>
