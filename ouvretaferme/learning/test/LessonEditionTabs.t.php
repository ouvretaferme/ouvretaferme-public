<?php
namespace learning;

/**
 * L'écran d'édition d'une leçon en onglets (retours du 01/10/2026, point 5) : le contenu,
 * avec le plan de ses blocs, les fichiers à télécharger et le quiz de fin, dans la forme de l'édition
 * d'une formation. Le contenu s'ouvre par défaut, un lien peut demander un autre onglet. Les
 * fichiers et le quiz portent leur nombre dès qu'ils en ont un. Le quiz a toujours son onglet, et propose sa première question même
 * sous une formation en brouillon, où il ne rendait jusqu'ici qu'un bloc vide.
 *
 * Les leçons sont construites en mémoire : aucune lecture ni écriture en base.
 *
 * Lancement : dev/test package=learning file=LessonEditionTabs
 */
class LessonEditionTabsTest extends \Test {

	const REFERENCE = 123;

	public function testTabs(): void {

		$edition = new LessonUi()->getEdition($this->getLessonInMemory());

		$this->assertTrue(str_contains($edition, 'onrender="'.encode('Lime.Tab.restore(this, "content", ""); LearningLesson.updateOrigin()').'"'), 'le contenu par défaut, aucun onglet demandé');
		$this->assertEquals(0, preg_match('/<div class="tabs tabs-h tabs-h-fold"[^>]* id=/', $edition), 'la rangée sans id, sous lequel Lime.Tab retiendrait le dernier onglet ouvert');
		$this->assertTrue(str_contains($edition, '<a class="tab-item selected" id="learning-lesson-tab-content"'), 'le contenu sélectionné côté serveur');
		$this->assertEquals(1, preg_match_all('/tab-panel selected" data-tab="(content|attachments|quiz)"/', $edition), 'un seul panneau ouvert, hors des onglets d\'un bloc');

		$panels = $this->getPanels($edition);

		$this->assertTrue(str_contains($panels['content'], 'class="block-editor"'), 'les blocs dans le contenu');
		$this->assertTrue(str_contains($panels['content'], 'id="block-plan"'), 'le plan avec eux');
		$this->assertFalse(str_contains($panels['attachments'].$panels['quiz'], 'block-plan'), 'et nulle part ailleurs');

		$edition = new LessonUi()->getEdition($this->getLessonInMemory(), 'quiz');

		$this->assertTrue(str_contains($edition, 'onrender="'.encode('Lime.Tab.restore(this, "content", "quiz"); LearningLesson.updateOrigin()').'"'), 'l\'onglet demandé en troisième argument');

	}

	/**
	 * La barre ne porte que les déplacements vers les leçons voisines (retour du 09/10/2026) :
	 * on revient à la formation par la flèche du titre. Une leçon seule dans sa formation n'a donc pas
	 * de barre, plutôt qu'une barre vide.
	 */
	public function testBar(): void {

		$eLesson = $this->getLessonInMemory();

		$edition = new LessonUi()->getEdition($eLesson);

		$this->assertTrue(str_contains($edition, 'href="'.LessonUi::urlLeave($eLesson, 'next').'"'), 'vers la leçon suivante');
		$this->assertFalse(str_contains($edition, LessonUi::urlLeave($eLesson, 'previous')), 'rien vers une leçon précédente qui n\'existe pas');
		$this->assertFalse(str_contains($edition, LessonUi::urlLeave($eLesson, 'course')), 'aucun bouton vers la formation');

		$eLesson['eLessonNext'] = new Lesson();

		$edition = new LessonUi()->getEdition($eLesson);

		$this->assertFalse(str_contains($edition, LessonUi::urlLeave($eLesson, 'next')), 'sans voisine, pas de barre');

	}

	/**
	 * La roue de la tête de page ouvre son panneau avec l'onglet d'où elle est cliquée (`origin`),
	 * où l'enregistrement ramène (`LessonEditionOrigin`). Son lien est rendu sans lui : c'est
	 * `LearningLesson.updateOrigin()` qui l'y écrit, à la suite de ce qui choisit l'onglet, à
	 * l'ouverture de l'écran (`testTabs()`) comme à chaque clic. Le script vise la roue par son
	 * adresse et l'onglet par son `id`, que le rendu doit donc porter tels quels.
	 */
	public function testOrigin(): void {

		$eLesson = $this->getLessonInMemory();

		foreach(LessonUi::EDITION_TABS as $tab) {
			$this->assertTrue(str_contains(new LessonUi()->getEdition($eLesson, $tab), 'id="learning-lesson-tab-'.$tab.'" data-tab="'.$tab.'" onclick="Lime.Tab.select(this); LearningLesson.updateOrigin()"'), $tab.' écrit son origine une fois sélectionné');
		}

		$script = file_get_contents(\Package::getPath('learning').'/static/js/learning.js');
		$method = substr($script, strpos($script, 'static updateOrigin()'), 400);

		$this->assertTrue(str_contains($method, 'qs(\'[id^="learning-lesson-tab-"].selected\').dataset.tab'), 'l\'onglet sélectionné parmi ceux de la leçon, et non ceux d\'un bloc : '.$method);
		$this->assertTrue(str_contains($method, 'qs(\'a[href^="/learning/lesson:updateSettings"]\''), 'le lien visé par son adresse');
		$this->assertTrue(str_starts_with(new LessonUi()->getEditionActions($eLesson), '<a href="/learning/lesson:updateSettings?id='.self::REFERENCE.'"'), 'cette adresse est celle de la roue');

	}

	/**
	 * Une leçon sans bloc ouvre son contenu sur le choix du premier bloc, et non sur un bloc ouvert
	 * d'office.
	 */
	public function testContentStart(): void {

		$content = $this->getPanels(new LessonUi()->getEdition($this->getLessonInMemory()))['content'];

		$this->assertTrue(str_contains($content, '<div id="block-start">'), 'le choix est montré');
		$this->assertTrue(str_contains($content, '<div id="block-list" class="block-list"></div>'), 'devant une liste vide');

	}

	/**
	 * Sous un groupe, les onglets montent dans la bande et le gabarit pose leur `.tabs` avec les
	 * attributs que rend `getEditionTabs()` (`lesson.v.php`), les mêmes que sous l'équipe
	 * (`testTabs()`) : un onglet demandé, le contenu par défaut, et aucun `id`, sous lequel
	 * `Lime.Tab` retiendrait le dernier onglet ouvert.
	 */
	public function testTabsInMasthead(): void {

		$eLesson = $this->getLessonInMemory();

		$edition = new LessonUi()->getEditionTabs($eLesson, 'quiz');

		$this->assertEquals(['onrender' => 'Lime.Tab.restore(this, "content", "quiz"); LearningLesson.updateOrigin()'], $edition['attributes'], 'l\'onglet demandé, le contenu par défaut, sans id');

	}

	/**
	 * La tête d'onglet que rend un ajout ou un retrait (`view/attachment.v.php`, `view/question.v.php`) :
	 * revenue à zéro, elle ne porte plus de nombre.
	 */
	public function testCountersBackToZero(): void {

		$eLesson = $this->getLessonInMemory(attachments: 1, questions: 1);

		$this->assertTrue(str_contains(new LessonUi()->getEditionTab($eLesson, 'attachments'), '1'), 'un fichier');
		$this->assertTrue(str_contains(new LessonUi()->getEditionTab($eLesson, 'quiz'), '1'), 'une question');

		$eLesson = $this->getLessonInMemory();

		$this->assertEquals(0, preg_match('/[0-9]/', new LessonUi()->getEditionTab($eLesson, 'attachments')), 'plus de fichier, plus de nombre');
		$this->assertEquals(0, preg_match('/[0-9]/', new LessonUi()->getEditionTab($eLesson, 'quiz')), 'plus de question, plus de nombre');

	}

	public function testCounters(): void {

		$eLesson = $this->getLessonInMemory(attachments: 2, questions: 3);

		$this->assertTrue(str_contains(new LessonUi()->getEditionTab($eLesson, 'attachments'), '2'), 'le nombre des fichiers');
		$this->assertTrue(str_contains(new LessonUi()->getEditionTab($eLesson, 'quiz'), '3'), 'le nombre des questions');
		$this->assertEquals(s("Contenu de la leçon"), new LessonUi()->getEditionTab($eLesson, 'content'), 'le contenu ne compte pas ses blocs, que le plan compte');
		$this->assertEquals(s("Quiz de fin"), LessonUi::getEditionPanelTitle('quiz'), 'un titre de panneau retombe sur le nom de l\'onglet');
		$this->assertEquals(s("Contenu de la leçon"), LessonUi::getEditionPanelTitle('content'));

	}

	/**
	 * Une formation en brouillon dont la leçon n'a ni question ni quiz posé : l'onglet garde le geste
	 * de la première question, sans liste (retour du 12/10/2026). Un quiz posé sans
	 * question garde son appel, qui dit pourquoi la leçon ne peut pas se valider.
	 */
	public function testQuizEmpty(): void {

		$quiz = new QuestionUi()->getEditor($this->getLessonInMemory());

		$this->assertTrue(str_contains($quiz, '/learning/question:create?lesson='.self::REFERENCE), 'le geste qui ajoute une question : '.$quiz);
		$this->assertFalse(str_contains($quiz, 'question:doUpdatePositions'), 'sans question, pas de liste');

		$eLessonPosed = $this->getLessonInMemory();
		$eLessonPosed['validation'] = Lesson::QUIZ;

		$quiz = new QuestionUi()->getEditor($eLessonPosed);

		$this->assertTrue(str_contains($quiz, '/learning/question:create?lesson='.self::REFERENCE), 'posé sans question, l\'appel à la première');

		$this->assertTrue(str_contains(new QuestionUi()->getEditor($this->getLessonInMemory(questions: 2)), 'question:doUpdatePositions'), 'avec des questions, la liste');

	}

	/**
	 * Une leçon sans fichier n'a pas de liste, qui paraît avec le premier fichier.
	 */
	public function testAttachmentsEmpty(): void {

		$attachments = new AttachmentUi()->getEditor($this->getLessonInMemory());

		$this->assertFalse(str_contains($attachments, 'attachment:doUpdatePositions'), 'sans fichier, pas de liste : '.$attachments);

		$attachments = new AttachmentUi()->getEditor($this->getLessonInMemory(attachments: 2));

		$this->assertTrue(str_contains($attachments, 'attachment:doUpdatePositions'), 'avec des fichiers, la liste');

	}

	private function getLessonInMemory(int $attachments = 0, int $questions = 0): Lesson {

		$cAttachment = new \Collection();

		for($position = 1; $position <= $attachments; $position++) {
			$cAttachment[$position] = new Attachment(['id' => $position, 'name' => 'Fichier '.$position, 'hash' => str_repeat('a', 20).'.pdf']);
		}

		$cQuestion = new \Collection();

		for($position = 1; $position <= $questions; $position++) {
			$cQuestion[$position] = new Question(['id' => $position, 'number' => $position, 'type' => Question::SINGLE, 'statement' => 'Question '.$position, 'hasImage' => FALSE]);
		}

		return new Lesson([
			'id' => self::REFERENCE,
			'course' => new Course(['id' => 1, 'status' => Course::DRAFT]),
			'validation' => Lesson::DECLARED,
			'quizThreshold' => 80,
			'needed' => 0,
			'cBlock' => new \Collection(),
			'blockChoices' => \block\BlockLib::getChoices(BlockOwnerLib::getTypes(BlockOwnerLib::LESSON)),
			'cBlockNew' => \block\BlockLib::getNew(BlockOwnerLib::LESSON, self::REFERENCE, BlockOwnerLib::getTypes(BlockOwnerLib::LESSON)),
			'cAttachment' => $cAttachment,
			'cQuestion' => $cQuestion,
			'eLessonPrevious' => new Lesson(),
			'eLessonNext' => new Lesson(['id' => self::REFERENCE + 1]),
		]);

	}

	/**
	 * Le contenu de chaque panneau, du sien au suivant : le panneau du contenu porte des onglets de
	 * blocs, dont les panneaux ont un `data-tab` en `block-`.
	 */
	private function getPanels(string $edition): array {

		$panels = [];

		foreach(LessonUi::EDITION_TABS as $position => $name) {

			$start = strpos($edition, '<div class="tab-panel'.($position === 0 ? ' selected' : '').'" data-tab="'.$name.'">');
			$next = LessonUi::EDITION_TABS[$position + 1] ?? NULL;
			$end = ($next === NULL) ? strpos($edition, 'learning-lesson-bar') : strpos($edition, '<div class="tab-panel" data-tab="'.$next.'">');

			$panels[$name] = substr($edition, $start, $end - $start);

		}

		return $panels;

	}

}
?>
