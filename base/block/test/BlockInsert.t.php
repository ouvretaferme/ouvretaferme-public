<?php
namespace block;

require_once __DIR__.'/../../../ouvretaferme/learning/test/LearningFixture.t.php';

/**
 * Ce que l'éditeur rend pour ajouter un bloc quand l'usage en accepte plusieurs types : un « + » qui
 * ouvre la liste des types, la même que celle du « / », rendue une fois dans la page. Un bloc composé
 * se lit avec ses enfants, chacun avec son propre formulaire, et le « + » d'une étape ajoute une étape
 * sans rien demander.
 *
 * Lancement : dev/test package=block file=BlockInsert
 */
class BlockInsertTest extends \learning\LearningFixtureTest {

	const OWNER = \learning\BlockOwnerLib::LESSON;

	public function testInsert(): void {

		$h = new BlockUi()->getInsert(BlockLib::getChoices([BlockTypeLib::TEXT]));

		$this->assertTrue(str_contains($h, 'data-block-insert data-type="'.BlockTypeLib::TEXT.'"'), 'un seul type : le « + » l\'ajoute sans rien demander');
		$this->assertTrue(str_contains($h, 'data-dropdown') === FALSE, 'sans liste');

		$h = new BlockUi()->getInsert(BlockLib::getChoices([BlockTypeLib::TEXT, BlockTypeLib::CALLOUT]));

		$this->assertTrue(str_contains($h, 'data-dropdown'), 'plusieurs types : le « + » ouvre une liste');
		$this->assertTrue(str_contains($h, 'data-block-insert-menu'), 'que le script remplit depuis la liste de la page');
		$this->assertTrue(str_contains($h, 'block-insert-list'), 'où le script retrouve les choix du menu');

		foreach([[BlockTypeLib::TEXT], [BlockTypeLib::TEXT, BlockTypeLib::CALLOUT]] as $types) {
			$this->assertTrue(str_starts_with(new BlockUi()->getInsert(BlockLib::getChoices($types)), '<div class="block-insert"><a '), count($types).' type(s) : le « + » est le lien posé en tête de `.block-insert`, où le script retrouve celui du bloc né');
		}

	}

	/**
	 * Un bloc à écrire poste son type, et c'est aussi là que le script le lit : choisir au menu d'un
	 * « + » un autre type que celui du bloc ouvert sous lui fait naître ce bloc et pose le bloc choisi
	 * après lui, le même type y remet seulement le curseur (`BlockEditor.insert()`). Un enfant à écrire
	 * porte le type de l'enfant, celui que demande le « + » de son parent.
	 */
	public function testNewCarriesType(): void {

		foreach(BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER)) as $eBlockNew) {

			$h = new BlockUi()->getNew($eBlockNew);

			$this->assertEquals(1, preg_match_all('/<input[^>]* name="type" type="hidden" value="([a-z-]+)"\/>/', $h, $matches), $eBlockNew['type'].' sous '.($eBlockNew['parentType'] ?? 'aucun parent').' : un seul champ `type`');
			$this->assertEquals([$eBlockNew['type']], $matches[1], 'qui porte le type du bloc à écrire');

		}

	}

	public function testMenuAndTemplates(): void {

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$this->assertEquals(array_merge(\learning\BlockOwnerLib::getTypes(self::OWNER), [BlockTypeLib::ITEM, BlockTypeLib::ANSWER], array_fill(0, 3, BlockTypeLib::ITEM)), array_values($cBlockNew->getColumn('type')), 'un bloc à écrire par type de l\'usage, et un enfant par parent, qui prend la forme de son parent ou son propre type');
		$this->assertEquals([BlockTypeLib::STEPS, BlockTypeLib::ANSWERS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::FIGURE], array_values(array_filter($cBlockNew->getColumn('parentType'))), 'un enfant à écrire pour chacun des cinq parents');

		$choices = BlockLib::getChoices(\learning\BlockOwnerLib::getTypes(self::OWNER));
		$h = new BlockUi()->getMenu($choices);

		$this->assertTrue(str_starts_with($h, '<template data-block-menu>'), 'la liste des types est rendue une fois, dans un template');
		$this->assertEquals(13, count($choices), 'un choix par type');
		$this->assertFalse(in_array(BlockTypeLib::ITEM, array_column($choices, 'type'), TRUE), 'une étape ne se choisit pas au premier niveau');

		// L'encadré porte ses couleurs, la vidéo ses dispositions, les étapes leur repère et la réponse sa marque, qui se choisissent dans la barre du bloc à écrire ;
		// un type sans variante ou à la forme fixée au « + » n'en propose pas.
		$variants = 0;

		foreach($cBlockNew as $eBlockNew) {

			$choosable = ($eBlockNew['variants'] !== [] and $eBlockNew['variantFixed'] === FALSE);

			if($choosable) {
				$variants += count($eBlockNew['variants']);
			}

			$this->assertEquals($choosable, str_contains(new BlockUi()->getNew($eBlockNew), 'data-block-variant='), $eBlockNew['type'].' sous '.($eBlockNew['parentType'] ?? 'aucun parent').' : un menu de variantes selon son type');

		}

		$this->assertEquals(11, $variants, 'les trois couleurs de l\'encadré, les quatre dispositions de la vidéo (pleine largeur, petite, à gauche et à droite du texte), les deux repères des étapes et les deux variantes d\'une réponse');

	}

	public function testComposite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), \learning\Course::DRAFT, \learning\Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon composée', 10);

		$eBlockSteps = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'type' => BlockTypeLib::STEPS, 'variant' => BlockTypeLib::NUMBERS, 'title' => 'Gratter', 'content' => '<main><p>Un mètre.</p></main>']);
		BlockLib::create($eBlockSteps);

		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);

		foreach($cBlock as $eBlock) {
			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));
		}

		$h = new BlockUi()->getList($cBlock);

		$eBlockS1 = $cBlock->first()['cBlock']->first();

		$this->assertTrue(str_contains($h, 'data-block="'.$eBlockSteps['id'].'" data-block-composite'), 'le parent se désigne comme composé');
		$this->assertTrue(str_contains($h, 'id="block-'.$eBlockS1['id'].'"'), 'l\'étape a son enveloppe');
		$this->assertTrue(str_contains($h, 'id="editor-block-'.$eBlockS1['id'].'"'), 'et son formulaire');
		$this->assertTrue(str_contains($h, 'data-block-child="'.BlockTypeLib::STEPS.'"'), 'son « + » ajoute une étape sans liste, au bout des étapes');

		// L'écran d'une leçon qui a déjà des blocs, préparé par sa page : la liste des types du « + » et
		// du « / » y est rendue comme sur une leçon vide.
		$data = $this->executeView('formation/lecon/'.$eLesson['id'].'/edition', []);
		$edition = new \learning\LessonUi()->getEdition($data->eLesson);

		$this->assertTrue(str_contains($edition, '<template data-block-menu>'), 'la liste des types est rendue sur une leçon qui a des blocs');

		$read = new BlockUi()->render(BlockLib::getReadable(self::OWNER, $eLesson['id']));

		$this->assertTrue(str_contains($read, 'Gratter'), 'la lecture rend l\'étape');
		$this->assertTrue(str_contains($read, 'data-block') === FALSE, 'sans rien de l\'éditeur');

	}

	/**
	 * Plusieurs chiffres clés se lisent sous un seul parent, en lecture comme dans l'éditeur. La lecture
	 * ne pose rien de l'éditeur.
	 */
	public function testFigures(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), \learning\Course::DRAFT, \learning\Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon chiffrée', 10);

		$eBlockFigure = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'type' => BlockTypeLib::FIGURE, 'title' => '80 %', 'content' => '<main><p>de levée</p></main>']);
		BlockLib::create($eBlockFigure);

		$eBlockFirst = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->first()['cBlock']->first();

		$eBlockSecond = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'parent' => $eBlockFigure, 'type' => BlockTypeLib::ITEM, 'title' => '12 jours', 'content' => NULL, 'eBlockAfter' => $eBlockFirst]);
		BlockLib::create($eBlockSecond);

		$cBlockReadable = BlockLib::getReadable(self::OWNER, $eLesson['id']);

		$this->assertEquals(1, $cBlockReadable->count(), 'un seul parent');
		$this->assertEquals(['80 %', '12 jours'], array_values($cBlockReadable->first()['cBlock']->getColumn('title')), 'qui porte les deux chiffres');

		$read = new BlockUi()->render($cBlockReadable);

		$this->assertTrue(str_contains($read, '>12 jours<'), 'le second chiffre se lit');
		$this->assertTrue(str_contains($read, 'data-block') === FALSE, 'sans rien de l\'éditeur');

		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);

		foreach($cBlock as $eBlock) {
			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));
		}

		$h = new BlockUi()->getList($cBlock);

		$this->assertTrue(str_contains($h, 'id="block-'.$eBlockSecond['id'].'"'), 'le second chiffre a son enveloppe');
		$this->assertTrue(str_contains($h, 'id="editor-block-'.$eBlockSecond['id'].'"'), 'et son formulaire');
		$this->assertTrue(str_contains($h, 'data-block-child="'.BlockTypeLib::FIGURE.'"'), 'le « + » d\'un chiffre est au bout de la rangée');

	}

	/**
	 * Un parent qui naît a déjà le « + » de ses enfants au bout du premier : on ajoute une deuxième
	 * étape, une deuxième réponse, un deuxième volet, un deuxième onglet ou un deuxième chiffre sans
	 * enregistrer d'abord. Le script enregistre le parent avec son premier enfant, puis rejoue le geste
	 * sur le « + » du parent rendu (`BlockEditor.insertInNew()`).
	 */
	public function testNewParentHasInsert(): void {

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		foreach([BlockTypeLib::STEPS, BlockTypeLib::ANSWERS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::FIGURE] as $type) {

			$h = new BlockUi()->getNew($cBlockNew[$type]);

			$this->assertEquals(1, substr_count($h, 'data-block-child="'.$type.'"'), $type.' : un « + » d\'enfant dès la création');
			$this->assertEquals(1, substr_count($h, 'data-block-child='), $type.' : un seul « + » d\'enfant');

		}

		$h = new BlockUi()->getNew($cBlockNew[BlockTypeLib::TABS]);

		$this->assertTrue((bool)preg_match('/<div class="tabs-item">.*block-insert-tab.*<\/div><div class="tab-panel/s', $h), 'le « + » des onglets est dans la rangée des têtes');

		$h = new BlockUi()->getNew($cBlockNew[BlockTypeLib::ANSWERS]);

		$this->assertTrue(str_contains($h, 'data-block-insert data-type="'.BlockTypeLib::ANSWER.'"'), 'le « + » des réponses ajoute une réponse : '.$h);

	}

}
?>
