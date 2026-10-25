<?php
namespace learning;

/**
 * L'interrupteur « Édition » / « Visualisation » du contenu d'une leçon (retours du
 * 01/10/2026, points 7 et 14) : c'est un lien vers le même écran, sur l'onglet « Contenu de la
 * leçon », dans l'autre mode. La visualisation ne change que ce panneau, qui rend les blocs sans
 * leur éditeur. Les onglets, les fichiers et le quiz sont les mêmes dans les deux modes.
 *
 * Les leçons sont construites en mémoire : aucune lecture ni écriture en base.
 *
 * Lancement : dev/test package=learning file=LessonEditionPreview
 */
class LessonEditionPreviewTest extends \Test {

	const REFERENCE = 123;

	public function testSwitch(): void {

		$eLesson = $this->getLessonInMemory();

		$url = '/formation/lecon/'.self::REFERENCE.'/edition';

		$this->assertEquals($url, LessonUi::urlEdition($eLesson), 'l\'écran à l\'arrivée, sans paramètre');
		$this->assertEquals($url.'?tab=content&mode=preview', LessonUi::urlEditionMode($eLesson, LessonUi::MODE_PREVIEW), 'la visualisation, sur l\'onglet du contenu');
		$this->assertEquals($url.'?tab=content', LessonUi::urlEditionMode($eLesson, LessonUi::MODE_EDITION), 'le retour rouvre l\'onglet du contenu, quel que soit l\'onglet retenu');

		$switch = new LessonUi()->getEditionMode($eLesson, LessonUi::MODE_EDITION);

		$this->assertTrue(str_contains($switch, 'href="'.encode($url.'?tab=content&mode=preview').'"'), 'en édition, le lien mène à la visualisation : '.$switch);

		$switch = new LessonUi()->getEditionMode($eLesson, LessonUi::MODE_PREVIEW);

		$this->assertTrue(str_contains($switch, 'href="'.$url.'?tab=content"'), 'en visualisation, le lien ramène à l\'édition : '.$switch);

	}

	public function testHead(): void {

		$cChapter = new \Collection([1 => new Chapter(['id' => 1, 'number' => 1, 'name' => 'Module'])]);

		$this->assertTrue(new LessonUi()->getEditionHead($this->getLessonInMemory(), $cChapter) !== '', 'la tête de page se rend');

	}

	public function testPreview(): void {

		$preview = new LessonUi()->getEdition($this->getLessonInMemory(attachments: 1, questions: 2, validation: Lesson::QUIZ), 'content', LessonUi::MODE_PREVIEW);

		$this->assertTrue(str_contains($preview, 'onrender="'.encode('Lime.Tab.restore(this, "content", "content"); LearningLesson.updateOrigin()').'"'), 'les onglets restent, celui du contenu demandé');

		$this->assertFalse(str_contains($this->getPanel($preview, 'content'), 'block-editor'), 'le contenu visualisé ne porte pas l\'éditeur des blocs');

		foreach([
			'response:doAnswer' => 'l\'envoi du quiz',
			'lesson:doDone' => 'la leçon déclarée suivie',
			'learning/comment' => 'les commentaires',
		] as $needle => $name) {
			$this->assertFalse(str_contains($preview, $needle), 'l\'écran ne porte pas '.$name);
		}

	}

	/**
	 * Les fichiers et le quiz ne connaissent pas le mode : leurs onglets et leurs panneaux sont
	 * rendus à l'identique en édition et en visualisation.
	 */
	public function testOtherTabs(): void {

		$edition = new LessonUi()->getEdition($this->getLessonInMemory(attachments: 1, questions: 2, validation: Lesson::QUIZ), 'content', LessonUi::MODE_EDITION);
		$preview = new LessonUi()->getEdition($this->getLessonInMemory(attachments: 1, questions: 2, validation: Lesson::QUIZ), 'content', LessonUi::MODE_PREVIEW);

		$this->assertTrue(str_contains($this->getPanel($edition, 'content'), 'class="block-editor'), 'en édition, l\'éditeur des blocs');

		foreach(['attachments' => 'attachment:doUpload', 'quiz' => 'question:create'] as $tab => $action) {

			$panel = $this->getPanel($preview, $tab);

			$this->assertTrue(str_contains($panel, $action), $tab.', le panneau garde son édition en visualisation');
			$this->assertEquals($this->withoutFieldId($this->getPanel($edition, $tab)), $this->withoutFieldId($panel), $tab.', le même panneau dans les deux modes');

		}

		$this->assertEquals($this->getTabs($edition), $this->getTabs($preview), 'la même rangée d\'onglets');

	}

	/**
	 * Le contenu d'un panneau. Les panneaux se suivent dans l'ordre de `LessonUi::EDITION_TABS`, le
	 * dernier s'arrêtant à la barre : un panneau porte des `<div>` imbriqués, que seule cette borne
	 * sépare sans les compter.
	 */
	private function getPanel(string $screen, string $tab): string {

		$open = '<div class="tab-panel'.($tab === 'content' ? ' selected' : '').'" data-tab="'.$tab.'">';

		$start = strpos($screen, $open);

		$this->assertTrue($start !== FALSE, 'le panneau '.$tab.' est rendu');

		$start += strlen($open);

		$position = array_search($tab, LessonUi::EDITION_TABS);

		$end = array_key_exists($position + 1, LessonUi::EDITION_TABS) ?
			strpos($screen, '<div class="tab-panel" data-tab="'.LessonUi::EDITION_TABS[$position + 1].'">', $start) :
			strpos($screen, 'learning-lesson-bar', $start);

		return substr($screen, $start, $end - $start);

	}

	/**
	 * Le cadriciel tire un identifiant de champ à chaque rendu (`field-<aléa>`) : deux rendus du même
	 * panneau ne se comparent qu'une fois cet aléa retiré.
	 */
	private function withoutFieldId(string $html): string {
		return preg_replace('/id="field-[0-9a-f]+"/', 'id="field"', $html);
	}

	private function getTabs(string $screen): string {

		$start = strpos($screen, '<div class="tabs-item">');

		return substr($screen, $start, strpos($screen, '<div class="tab-panel', $start) - $start);

	}

	private function getLessonInMemory(int $attachments = 0, int $questions = 0, string $validation = Lesson::DECLARED): Lesson {

		$cAttachment = new \Collection();

		for($position = 1; $position <= $attachments; $position++) {
			$cAttachment[$position] = new Attachment(['id' => $position, 'name' => 'Fichier '.$position, 'hash' => str_repeat('a', 20).'.pdf']);
		}

		$cQuestion = new \Collection();

		for($position = 1; $position <= $questions; $position++) {

			$cChoice = new \Collection();

			for($choice = 1; $choice <= 2; $choice++) {
				$cChoice[$choice] = new Choice(['id' => $position * 10 + $choice, 'label' => 'Choix '.$choice.' de la question '.$position, 'image' => NULL, 'correct' => ($choice === 1)]);
			}

			$cQuestion[$position] = new Question([
				'id' => $position,
				'number' => $position,
				'type' => Question::SINGLE,
				'statement' => 'Question '.$position,
				'image' => NULL,
				'explanation' => NULL,
				'hasImage' => FALSE,
				'hasChoiceImage' => FALSE,
				'cChoice' => $cChoice,
			]);

		}

		return new Lesson([
			'id' => self::REFERENCE,
			'name' => 'Semer',
			'objective' => 'Savoir semer',
			'minutes' => NULL,
			'number' => 1,
			'chapter' => new Chapter(['id' => 1]),
			'course' => new Course(['id' => 1, 'name' => 'Potager', 'status' => Course::DRAFT]),
			'validation' => $validation,
			'quizThreshold' => 80,
			'needed' => 0,
			// Sans bloc, la leçon n'est écrite que par son quiz (`Lesson::isWritten()`).
			'written' => ($validation === Lesson::QUIZ),
			'format' => match($validation) {
				Lesson::QUIZ => Lesson::TEXT,
				Lesson::DECLARED => Lesson::EMPTY,
			},
			'cBlock' => new \Collection(),
			'blockChoices' => \block\BlockLib::getChoices(BlockOwnerLib::getTypes(BlockOwnerLib::LESSON)),
			'cBlockNew' => \block\BlockLib::getNew(BlockOwnerLib::LESSON, self::REFERENCE, BlockOwnerLib::getTypes(BlockOwnerLib::LESSON)),
			'cAttachment' => $cAttachment,
			'cQuestion' => $cQuestion,
			'eLessonPrevious' => new Lesson(),
			'eLessonNext' => new Lesson(['id' => self::REFERENCE + 1]),
		]);

	}

}
?>
