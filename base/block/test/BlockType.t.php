<?php
namespace block;

require_once __DIR__.'/../../../ouvretaferme/learning/test/LearningFixture.t.php';

/**
 * Les types du lot B1 : ce que chacun écrit, ce qu'il accepte sous lui, ses variantes, et ce que
 * `build()` refuse. La création d'un bloc composé depuis la page écrit le parent et sa première
 * étape, avec ce qui a été écrit dans le formulaire.
 *
 * Lancement : dev/test package=block file=BlockType
 */
class BlockTypeTest extends \learning\LearningFixtureTest {

	const OWNER = \learning\BlockOwnerLib::LESSON;

	// La leçon construite en mémoire (`getLessonInMemory()`) n'a pas d'id : ses blocs se rapportent à la
	// référence 0, qu'aucune leçon ne porte, où `Block::url.video` ne trouve donc aucune vidéo.
	const int REFERENCE_IN_MEMORY = 0;

	/**
	 * Une adresse de vidéo par hébergeur de la liste de l'éditeur de texte, sous la clé qu'il y porte.
	 * `testVideoHosts()` échoue pour un hébergeur de la liste qui n'a pas la sienne ici.
	 */
	const array VIDEO_EXAMPLES = [
		'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		'vimeo' => 'https://vimeo.com/76979871',
	];

	/**
	 * Des adresses d'un hébergeur qui ne sont pas celles d'une vidéo : son accueil, une chaine sous ses
	 * trois formes d'adresse, une liste de lecture. Le bloc Vidéo n'a pas de motif à lui, il juge par
	 * la liste de l'éditeur de texte (`\editor\EditorSetting::VIDEO`), et un lecteur posé sur une de
	 * ces adresses n'afficherait qu'une erreur.
	 */
	const array VIDEO_NOT_A_VIDEO = [
		'https://www.youtube.com/',
		'https://www.youtube.com/@zzPotagerBocage',
		'https://www.youtube.com/user/zzPotagerBocage',
		'https://www.youtube.com/channel/UC1234567890abcdefghijkl',
		'https://www.youtube.com/playlist?list=PL1234567890',
	];

	public function testProperties(): void {

		$this->assertEquals(['content'], BlockTypeLib::getProperties(BlockTypeLib::TEXT));
		$this->assertEquals(['variant', 'title', 'content'], BlockTypeLib::getProperties(BlockTypeLib::CALLOUT));
		$this->assertEquals(['variant'], BlockTypeLib::getProperties(BlockTypeLib::STEPS), 'un bloc d\'étapes n\'écrit lui-même que son repère');
		$this->assertEquals([], BlockTypeLib::getProperties(BlockTypeLib::TABS), 'un bloc d\'onglets n\'écrit rien lui-même');
		$this->assertEquals(['title', 'content', 'settings'], BlockTypeLib::getProperties(BlockTypeLib::ITEM), 'une étape écrit aussi son instant dans la vidéo');
		$this->assertEquals([], BlockTypeLib::getProperties(BlockTypeLib::FIGURE), 'le bloc chiffres clés est un parent, ses chiffres écrivent');
		$this->assertEquals(['title', 'content'], BlockTypeLib::getProperties(BlockTypeLib::QUOTE));

		$this->assertEquals(['title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::TABS), 'un parent se crée avec ce qu\'écrit sa première étape');
		$this->assertEquals(['variant', 'title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::STEPS), 'un bloc d\'étapes se crée avec son repère et ce qu\'écrit sa première étape');
		$this->assertEquals(['variant', 'title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::CALLOUT));
		$this->assertEquals(['title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::FIGURE), 'le bloc chiffres clés se crée avec son premier chiffre, valeur et légende');
		$this->assertEquals(['title'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::FIGURE), 'un premier chiffre sans valeur est refusé dès la création du parent');

	}

	/**
	 * Le tableau écrit sa grille et rien d'autre, ne porte rien sous lui, et naît en trois colonnes :
	 * l'en-tête et deux lignes, toutes vides. Il est proposé aux leçons, après la citation.
	 */
	public function testTable(): void {

		$this->assertEquals(['settings'], BlockTypeLib::getProperties(BlockTypeLib::TABLE));
		$this->assertEquals(['settings'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::TABLE), 'la grille est postée dès la création');
		$this->assertEquals([], BlockTypeLib::getPropertiesRequired(BlockTypeLib::TABLE));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::TABLE));
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::TABLE));

		$this->assertEquals(['rows' => [['', '', ''], ['', '', ''], ['', '', '']]], BlockTypeLib::getDefaultSettings(BlockTypeLib::TABLE), 'un tableau neuf a trois colonnes, l\'en-tête et deux lignes');
		$this->assertEquals([], BlockTypeLib::getDefaultSettings(BlockTypeLib::TEXT), 'les autres types n\'ont pas de réglage');

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals(BlockTypeLib::TABLE, end($types), 'le tableau est proposé aux leçons, après la citation');

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$this->assertEquals(BlockTypeLib::getDefaultSettings(BlockTypeLib::TABLE), $cBlockNew[BlockTypeLib::TABLE]['settings'], 'le bloc à écrire du « + » porte la grille neuve');

		$h = new BlockUi()->getNew($cBlockNew[BlockTypeLib::TABLE]);

		$this->assertTrue(str_contains($h, 'name="settings[rows][2][2]"'), 'une cellule à écrire nommée par sa ligne et sa colonne');
		$this->assertTrue(str_contains($h, 'data-wrapper="settings"'), 'les refus de la grille ont leur place');

	}

	public function testChildrenAndVariants(): void {

		foreach([BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::FIGURE] as $type) {
			$this->assertEquals([BlockTypeLib::ITEM], BlockTypeLib::getChildren($type), $type.' porte des enfants');
		}

		foreach([BlockTypeLib::TEXT, BlockTypeLib::CALLOUT, BlockTypeLib::QUOTE, BlockTypeLib::ITEM] as $type) {
			$this->assertEquals([], BlockTypeLib::getChildren($type), $type.' ne porte rien, un seul niveau d\'arbre');
		}

		$this->assertEquals(4, BlockTypeLib::getChildrenMax(BlockTypeLib::FIGURE), 'quatre chiffres au plus, ce qui tient sur une rangée de la colonne de lecture');

		foreach([BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS] as $type) {
			$this->assertEquals(NULL, BlockTypeLib::getChildrenMax($type), $type.' n\'a pas de plafond');
		}

		$this->assertEquals([BlockTypeLib::INFO, BlockTypeLib::SUCCESS, BlockTypeLib::WARNING], BlockTypeLib::getVariants(BlockTypeLib::CALLOUT));
		$this->assertEquals([BlockTypeLib::NUMBERS, BlockTypeLib::CHECK], BlockTypeLib::getVariants(BlockTypeLib::STEPS), 'des étapes numérotées par défaut, ou des critères cochés');
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::TEXT));
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::ITEM), 'une étape prend le repère de sa liste');

	}

	/**
	 * Le repère des étapes : des numéros, ou des coches pour des critères qui ne se suivent pas. Il se
	 * construit comme les autres variantes, se lit par une classe de la liste, et se choisit dans la
	 * barre du bloc, par le champ caché du bloc à écrire ou, pour un bloc enregistré, par son id.
	 */
	public function testSteps(): void {

		$build = function(array $input): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'variant', 'title', 'content'], $input + ['type' => BlockTypeLib::STEPS, 'title' => 'Paillage posé', 'content' => NULL], new \PropertyList('create'));

			return $this->getFails($fw);

		};

		$this->assertEquals([], $build(['variant' => BlockTypeLib::CHECK]), 'des coches');
		$this->assertEquals([], $build(['variant' => BlockTypeLib::NUMBERS]), 'des numéros');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['variant' => BlockTypeLib::INFO]), 'une couleur d\'encadré n\'est pas un repère');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['variant' => '']), 'des étapes sans repère sont refusées');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build([]), 'un repère non posté aussi');

		$eBlockItem = new Block(['id' => 2, 'type' => BlockTypeLib::ITEM, 'title' => 'Paillage posé', 'content' => NULL, 'media' => NULL, 'settings' => []]);
		$read = fn(string $variant) => new BlockTypeUi()->render(new Block(['id' => 1, 'type' => BlockTypeLib::STEPS, 'variant' => $variant, 'cBlock' => new \Collection([$eBlockItem])]), []);

		$this->assertDiffer($read(BlockTypeLib::NUMBERS), $read(BlockTypeLib::CHECK), 'une liste à coches ne se lit pas comme une liste numérotée');

		$eBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER))[BlockTypeLib::STEPS];

		$this->assertEquals(BlockTypeLib::NUMBERS, $eBlockNew['variant'], 'un bloc d\'étapes neuf est numéroté');

		$h = new BlockUi()->getNew($eBlockNew);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.preg_replace('/<(\/?)template\b/', '<$1div', $h), LIBXML_NOERROR);

		$this->assertEquals(BlockTypeLib::NUMBERS, $document->querySelector('form input[name="variant"]')?->getAttribute('value'), 'le repère part avec le premier enregistrement : '.$h);
		$this->assertTrue($document->querySelector('form [data-block-variant-target]') !== NULL, 'le menu échange la classe de la liste à écrire');

		$entries = $document->querySelectorAll('.dropdown-item[data-block-variant]');

		foreach($entries as $entry) {
			$this->assertEquals('block-form-new', $entry->getAttribute('data-form'), 'chaque entrée écrit le formulaire du bloc à écrire');
		}

		$this->assertEquals(['', 'util-procedure-check'], array_map(fn($entry) => $entry->getAttribute('data-block-variant-class'), iterator_to_array($entries)), 'chaque entrée dit la classe qu\'elle pose, aucune pour les numéros');

		$eBlockSaved = new Block([
			'id' => 1,
			'owner' => self::OWNER,
			'reference' => 123,
			'parent' => new Block(),
			'type' => BlockTypeLib::STEPS,
			'variant' => BlockTypeLib::CHECK,
			'title' => NULL,
			'content' => NULL,
			'position' => 0,
			'cBlock' => new \Collection([2 => new Block(['id' => 2, 'owner' => self::OWNER, 'reference' => 123, 'parent' => new Block(['id' => 1]), 'type' => BlockTypeLib::ITEM, 'variant' => NULL, 'title' => 'Paillage posé', 'content' => NULL, 'media' => NULL, 'settings' => [], 'position' => 0, 'cBlock' => new \Collection()])]),
		]);

		BlockLib::fillEditor($eBlockSaved, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$h = new BlockUi()->getItem($eBlockSaved);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.preg_replace('/<(\/?)template\b/', '<$1div', $h), LIBXML_NOERROR);
		$entries = $document->querySelectorAll('.dropdown-item[data-block-variant]');

		$this->assertTrue($entries->length > 0, 'un bloc d\'étapes enregistré garde son menu : '.$h);

		foreach($entries as $entry) {
			$this->assertEquals('1', $entry->getAttribute('post-id'), 'chaque entrée porte l\'id du parent, qui s\'enregistre au choix');
			$this->assertEquals(NULL, $entry->getAttribute('data-form'), 'sans formulaire à remplir');
		}

		$selected = $document->querySelector('.dropdown-item.selected[data-block-variant="'.BlockTypeLib::CHECK.'"]');

		$this->assertTrue($selected !== NULL, 'le repère enregistré est choisi');
		$class = $selected?->getAttribute('data-block-variant-class') ?? '';

		$this->assertTrue($class !== '' and $document->querySelector('.'.$class) !== NULL, 'la liste de l\'éditeur porte la classe de l\'entrée choisie');

		// Le message se lit comme `build()` le demande, sur l'élément et la valeur refusés.
		$message = fn(string $type): ?string => new \FailWatch()->getMessage('block', 'Block::variant.allowed', [new Block(['type' => $type]), '']);

		$this->assertEquals('Choisissez des numéros ou des coches.', $message(BlockTypeLib::STEPS));
		$this->assertEquals('Choisissez une des dispositions proposées.', $message(BlockTypeLib::VIDEO), 'une vidéo n\'a pas de couleur');
		$this->assertEquals('Choisissez une des couleurs proposées.', $message(BlockTypeLib::CALLOUT));

	}

	/**
	 * Les réponses : un parent qui n'écrit rien lui-même, et ses réponses, ordinaires ou meilleure, une
	 * seule meilleure par fratrie. À la lecture, la meilleure se rend ordinaire et attend dans son
	 * `<template>` le clic sur « Voir la meilleure réponse » ; l'éditeur la montre toujours.
	 */
	public function testAnswers(): void {

		$this->assertEquals([], BlockTypeLib::getProperties(BlockTypeLib::ANSWERS), 'le parent n\'écrit rien lui-même');
		$this->assertEquals(['title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::ANSWERS), 'il naît avec le titre et le texte de sa première réponse, sans variante, qu\'il jugerait contre les siennes');
		$this->assertEquals(['variant', 'title', 'content'], BlockTypeLib::getProperties(BlockTypeLib::ANSWER));
		$this->assertEquals(['title'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::ANSWERS), 'une première réponse sans titre est refusée dès la création du parent');
		$this->assertEquals(['title'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::ANSWER));
		$this->assertEquals([BlockTypeLib::ANSWER], BlockTypeLib::getChildren(BlockTypeLib::ANSWERS));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::ANSWER), 'une réponse ne porte rien');
		$this->assertEquals(NULL, BlockTypeLib::getChildrenMax(BlockTypeLib::ANSWERS), 'pas de plafond de réponses');
		$this->assertEquals([BlockTypeLib::REGULAR, BlockTypeLib::BEST], BlockTypeLib::getVariants(BlockTypeLib::ANSWER), 'une réponse naît ordinaire');
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::ANSWERS));
		$this->assertEquals(BlockTypeLib::BEST, BlockTypeLib::getVariantUnique(BlockTypeLib::ANSWER), 'une seule meilleure réponse par fratrie');
		$this->assertEquals(NULL, BlockTypeLib::getVariantUnique(BlockTypeLib::CALLOUT), 'deux encadrés voisins ont la couleur qu\'ils veulent');

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals(BlockTypeLib::ANSWERS, $types[array_search(BlockTypeLib::ACCORDION, $types) - 1], 'les réponses viennent juste avant l\'accordéon, qui porte le corrigé');

		$buildAnswer = function(array $input): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block(), 'type' => BlockTypeLib::ANSWER]);
			$fw = new \FailWatch();
			$eBlock->build(['variant', 'title', 'content'], $input + ['title' => 'Pailler tout de suite', 'content' => NULL]);

			return $this->getFails($fw);

		};

		$this->assertEquals([], $buildAnswer(['variant' => BlockTypeLib::BEST]), 'la meilleure');
		$this->assertEquals([], $buildAnswer(['variant' => BlockTypeLib::REGULAR]), 'une ordinaire');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $buildAnswer(['variant' => BlockTypeLib::INFO]), 'une couleur d\'encadré n\'est pas une marque de réponse');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $buildAnswer(['variant' => '']), 'une réponse sans variante est refusée');
		$this->assertEquals([['Block::title.missing', 'title']], $buildAnswer(['variant' => BlockTypeLib::REGULAR, 'title' => '']), 'une réponse sans titre aussi');
		$this->assertEquals('Donnez un titre à la réponse.', new \FailWatch()->getMessage('block', 'Block::title.missing', [new Block(['type' => BlockTypeLib::ANSWER]), NULL]), 'le refus nomme la réponse');
		$this->assertEquals('Donnez un titre à la réponse.', new \FailWatch()->getMessage('block', 'Block::title.missing', [new Block(['type' => BlockTypeLib::ANSWERS]), NULL]), 'celle qui naît avec son parent aussi');

		$this->assertEquals('Choisissez une réponse ordinaire ou la meilleure réponse.', new \FailWatch()->getMessage('block', 'Block::variant.allowed', [new Block(['type' => BlockTypeLib::ANSWER]), '']));

		$answer = fn(int $id, string $variant, string $title) => new Block(['id' => $id, 'type' => BlockTypeLib::ANSWER, 'variant' => $variant, 'title' => $title, 'content' => '<main><p>'.$title.'</p></main>', 'cBlock' => new \Collection()]);
		$parse = fn(string $h) => \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR);

		// La lecture
		$read = new BlockTypeUi()->render(new Block(['id' => 1, 'type' => BlockTypeLib::ANSWERS, 'variant' => NULL, 'cBlock' => new \Collection([
			2 => $answer(2, BlockTypeLib::REGULAR, 'Attendre'),
			3 => $answer(3, BlockTypeLib::BEST, 'Pailler tout de suite'),
		])]));
		$document = $parse($read);

		$best = $document->querySelector('.block-answers > [data-block-answer-best]');
		$regular = $document->querySelector('.block-answers > :not([data-block-answer-best])');

		$this->assertTrue($best !== NULL and $regular !== NULL, 'la meilleure attend parmi les réponses : '.$read);
		$this->assertEquals($regular?->getAttribute('class'), $best?->getAttribute('class'), 'rendue ordinaire');

		$revealed = $parse($best?->querySelector('template')?->innerHTML ?? '')->querySelector('body > *');

		$this->assertTrue($revealed !== NULL and $revealed->hasAttribute('data-block-answer-best') === FALSE, 'son template porte sa forme révélée, que le script met à sa place : '.$read);
		$this->assertDiffer($best?->getAttribute('class'), $revealed?->getAttribute('class'), 'qui ne se lit pas ordinaire');
		$this->assertTrue($document->querySelector('.block-answers + div > [data-block-answers-reveal]') !== NULL, 'le bouton suit la grille, dans la parenté que lit le script');

		$read = new BlockTypeUi()->render(new Block(['id' => 1, 'type' => BlockTypeLib::ANSWERS, 'variant' => NULL, 'cBlock' => new \Collection([
			2 => $answer(2, BlockTypeLib::REGULAR, 'Attendre'),
			3 => $answer(3, BlockTypeLib::REGULAR, 'Bâcher'),
		])]));

		$this->assertTrue(str_contains($read, 'data-block-answers-reveal') === FALSE, 'sans meilleure, rien à révéler : '.$read);
		$this->assertTrue(str_contains($read, '<template>') === FALSE, 'et aucune forme en attente');

		// L'éditeur
		$eBlockSaved = new Block([
			'id' => 1,
			'owner' => self::OWNER,
			'reference' => 123,
			'parent' => new Block(),
			'type' => BlockTypeLib::ANSWERS,
			'variant' => NULL,
			'title' => NULL,
			'content' => NULL,
			'position' => 0,
			'cBlock' => new \Collection([
				2 => new Block(['id' => 2, 'owner' => self::OWNER, 'reference' => 123, 'parent' => new Block(['id' => 1]), 'type' => BlockTypeLib::ANSWER, 'variant' => BlockTypeLib::REGULAR, 'title' => 'Attendre', 'content' => NULL, 'position' => 0, 'cBlock' => new \Collection()]),
				3 => new Block(['id' => 3, 'owner' => self::OWNER, 'reference' => 123, 'parent' => new Block(['id' => 1]), 'type' => BlockTypeLib::ANSWER, 'variant' => BlockTypeLib::BEST, 'title' => 'Pailler tout de suite', 'content' => NULL, 'position' => 1, 'cBlock' => new \Collection()]),
			]),
		]);

		BlockLib::fillEditor($eBlockSaved, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$h = new BlockUi()->getItem($eBlockSaved);
		$document = $parse(preg_replace('/<(\/?)template\b/', '<$1div', $h));

		$this->assertDiffer($document->querySelector('#block-2 > .block-item > *')?->getAttribute('class'), $document->querySelector('#block-3 > .block-item > *')?->getAttribute('class'), 'l\'éditeur montre la meilleure autrement qu\'une ordinaire : '.$h);
		$this->assertTrue(str_contains($h, 'data-block-answers-reveal') === FALSE, 'sans bouton à cliquer');
		$this->assertTrue(str_contains($h, 'data-block-answer-best') === FALSE, 'ni réponse en attente');
		$this->assertTrue($document->querySelector('[data-block-composite] [data-block-insert][data-type="'.BlockTypeLib::ANSWER.'"][data-block-child="'.BlockTypeLib::ANSWERS.'"]') !== NULL, 'le « + » du parent ajoute une réponse');

		$form = $document->querySelector('#block-form-3');

		$this->assertEquals(BlockTypeLib::BEST, $form?->querySelector('input[name="variant"]')?->getAttribute('value'), 'la réponse ouverte porte sa variante dans son formulaire');
		$this->assertTrue($form?->querySelector('[data-block-variant-target]')?->classList->contains($document->querySelector('#block-3 .dropdown-item.selected[data-block-variant]')?->getAttribute('data-block-variant-class') ?? '') === TRUE, 'que le menu échange sur la carte');
		$this->assertTrue($form?->querySelector('div[data-wrapper="title"]:empty') !== NULL, 'le refus du titre a sa place dans le formulaire, et non au bandeau');

		$entries = $document->querySelectorAll('#block-3 .dropdown-item[data-block-variant]');

		$this->assertEquals(['util-card-accent-info', 'util-card-accent-success'], array_map(fn($entry) => $entry->getAttribute('data-block-variant-class'), iterator_to_array($entries)));
		$this->assertEquals(['block-form-3', 'block-form-3'], array_map(fn($entry) => $entry->getAttribute('data-form'), iterator_to_array($entries)), 'une réponse a son formulaire, que chaque entrée écrit');

		// Le bloc à écrire
		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$this->assertEquals(BlockTypeLib::REGULAR, $cBlockNew[BlockTypeLib::ANSWER.'-'.BlockTypeLib::ANSWERS]['variant'], 'une réponse ajoutée au « + » s\'ouvre ordinaire');

		$h = new BlockUi()->getNew($cBlockNew[BlockTypeLib::ANSWERS]);

		$this->assertTrue(str_contains($h, 'name="variant"') === FALSE, 'un parent qui naît ne poste pas de variante : '.$h);
		$this->assertTrue(str_contains($h, 'data-block-variant=') === FALSE, 'ni n\'en propose');
		$this->assertTrue($parse(preg_replace('/<(\/?)template\b/', '<$1div', $h))->querySelector('div[data-wrapper="title"]') !== NULL, 'la première réponse a la même place pour le refus de son titre : '.$h);

	}

	/**
	 * Un titre manquant se refuse avec le nom de ce qui le porte, et le refus s'écrit sous le champ,
	 * dans le formulaire du bloc ouvert. Un enfant reçoit le message de son parent : une étape se
	 * refuse de la même façon, qu'elle naisse avec ses étapes ou s'y ajoute.
	 */
	public function testTitleMissing(): void {

		$message = fn(string $type) => new \FailWatch()->getMessage('block', 'Block::title.missing', [new Block(['type' => $type]), NULL]);

		$this->assertEquals('Donnez un titre à la section.', $message(BlockTypeLib::SECTION));
		$this->assertEquals('Donnez un titre à l\'étape.', $message(BlockTypeLib::STEPS), 'la première étape, qui naît avec son parent');
		$this->assertEquals('Donnez un titre au volet.', $message(BlockTypeLib::ACCORDION));
		$this->assertEquals('Écrivez le chiffre.', $message(BlockTypeLib::FIGURE), 'le titre d\'un chiffre est sa valeur');

		foreach([BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::FIGURE] as $parentType) {
			$eBlockItem = new Block(['type' => BlockTypeLib::ITEM, 'parent' => new Block(['id' => 1, 'type' => $parentType])]);
			$this->assertEquals($message($parentType), new \FailWatch()->getMessage('block', 'Block::title.missing', [$eBlockItem, NULL]), 'un enfant de '.$parentType.' a le message de la première, qui naît avec son parent');
		}

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		foreach([BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::FIGURE] as $parentType) {

			foreach([$cBlockNew[$parentType], $cBlockNew[BlockTypeLib::ITEM.'-'.$parentType]] as $eBlock) {

				$h = new BlockTypeUi()->getEditable($eBlock, new \util\FormUi(), NULL);
				$wrapper = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR)->querySelector('[data-wrapper="title"]');

				$this->assertTrue($wrapper !== NULL and $wrapper->childElementCount === 0, $eBlock['type'].' sous '.$parentType.' : le refus du titre a sa place dans le formulaire : '.$h);
				$this->assertEquals(1, substr_count($h, 'data-wrapper="title"'), 'une seule, le message ne s\'écrivant qu\'une fois');

			}

		}

	}

	public function testIsEmpty(): void {

		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::CALLOUT, 'title' => NULL, 'content' => NULL])), 'encadré sans titre ni texte');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::CALLOUT, 'title' => 'À retenir', 'content' => NULL])) === FALSE, 'un titre seul se montre');

		$eBlockChildEmpty = new Block(['type' => BlockTypeLib::ITEM, 'title' => NULL, 'content' => NULL]);
		$eBlockChildFull = new Block(['type' => BlockTypeLib::ITEM, 'title' => 'Gratter', 'content' => NULL]);

		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::FIGURE, 'cBlock' => new \Collection([new Block(['type' => BlockTypeLib::ITEM, 'title' => '80 %', 'content' => NULL])])])) === FALSE, 'un chiffre sans légende se montre');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::FIGURE, 'cBlock' => new \Collection()])), 'un bloc chiffres clés sans chiffre est vide, même s\'il porte encore une valeur à lui');

		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::STEPS, 'cBlock' => new \Collection([$eBlockChildEmpty])])), 'un parent dont les étapes sont vides est vide');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::STEPS, 'cBlock' => new \Collection([$eBlockChildEmpty, $eBlockChildFull])])) === FALSE, 'une étape écrite suffit');

	}

	/**
	 * Un bloc neuf sans rien d'écrit se referme sans requête : le script le dit vide quand aucun de ses
	 * champs de texte ne porte de valeur (`BlockEditor.isDraftEmpty()`). Un champ né prérempli ferait
	 * donc poster un bloc que l'auteur n'a pas touché, ou enregistrer une valeur qu'il n'a pas choisie.
	 *
	 * Le sélecteur est celui du script, recopié : un champ d'un type que le script ne compte pas
	 * (un nombre, une date) échappe aux deux, et se verrait à ce qu'il ne retient pas le bloc ouvert.
	 */
	public function testNewFieldsEmpty(): void {

		$fields = 0;

		foreach(BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER)) as $key => $eBlockNew) {

			// Le contenu d'un `<template>` n'appartient pas au document qui le porte.
			$h = preg_replace('/<(\/?)template\b/', '<$1div', new BlockUi()->getNew($eBlockNew));

			foreach(\Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR)->querySelectorAll('input[type="text"], input[type="url"]') as $field) {

				$fields++;

				$this->assertEquals('', $field->getAttribute('value') ?? '', 'le champ `'.$field->getAttribute('name').'` d\'un bloc neuf `'.$key.'` naît sans valeur');

			}

		}

		$this->assertTrue($fields > 0, 'le contrôle a trouvé des champs à lire');

	}

	/**
	 * Le refus de l'adresse cite tous les hébergeurs que la liste de l'éditeur de texte nomme
	 * (`\editor\EditorUi::getVideoNames()`) : le bloc doit donc savoir lire une vidéo de chacun, sans
	 * quoi la phrase promet un hébergeur que l'écriture refuse.
	 */
	public function testVideoHosts(): void {

		$this->assertEquals(array_keys(\editor\EditorSetting::VIDEO), array_keys(self::VIDEO_EXAMPLES), 'chaque hébergeur de la liste a son adresse d\'exemple, et aucun exemple ne nomme un hébergeur sorti de la liste');

		foreach(self::VIDEO_EXAMPLES as $source => $url) {
			$this->assertEquals($source, BlockSetting::getVideo($url)['source'], 'le bloc lit une vidéo de `'.$source.'` : '.$url);
		}

		$names = new \editor\EditorUi()->getVideoNames();

		$this->assertTrue(str_contains(new \FailWatch()->getMessage('block', 'Block::url.host', [new Block(['type' => BlockTypeLib::VIDEO]), 'https://example.org/']), $names), 'le refus de l\'adresse cite les hébergeurs de la liste');

	}

	/**
	 * La vidéo du lot C1 : ce qu'elle écrit, ses deux tailles, ce que `build()` refuse de son adresse,
	 * et ce que sa lecture pose dans la page. Les adresses sont celles de la liste de l'éditeur de
	 * texte (`\editor\EditorSetting::VIDEO`) : le test échoue si YouTube ou Vimeo en sort, et c'est voulu.
	 */
	public function testVideo(): void {

		$this->assertEquals(['variant', 'url', 'title', 'content'], BlockTypeLib::getProperties(BlockTypeLib::VIDEO));
		$this->assertEquals(['variant', 'url', 'title', 'content'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::VIDEO), 'l\'adresse est postée dès la création');
		$this->assertEquals(['url'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::VIDEO));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::VIDEO));
		$this->assertEquals([BlockTypeLib::FULL, BlockTypeLib::SMALL, BlockTypeLib::LEFT, BlockTypeLib::RIGHT], BlockTypeLib::getVariants(BlockTypeLib::VIDEO), 'un bloc neuf s\'ouvre en pleine largeur');

		$this->assertTrue(in_array(BlockTypeLib::VIDEO, \learning\BlockOwnerLib::getTypes(self::OWNER), TRUE), 'la vidéo est proposée aux leçons');

		$this->assertEquals('youtube', BlockSetting::getVideoSource('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
		$this->assertEquals('youtube', BlockSetting::getVideoSource('https://youtu.be/dQw4w9WgXcQ'));
		$this->assertEquals('vimeo', BlockSetting::getVideoSource('https://vimeo.com/76979871'));
		$this->assertEquals(NULL, BlockSetting::getVideoSource('https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ'), 'un hôte qui commence comme un hébergeur n\'en est pas un');
		$this->assertEquals(NULL, BlockSetting::getVideoSource('https://example.org/video.mp4'));

		foreach(self::VIDEO_NOT_A_VIDEO as $url) {
			$this->assertEquals(NULL, BlockSetting::getVideoSource($url), $url.' n\'est pas l\'adresse d\'une vidéo');
		}

		$this->assertEquals(
			['source' => 'vimeo', 'name' => 'Vimeo', 'player' => 'https://player.vimeo.com/video/76979871?dnt=1', 'playerAutoplay' => 'https://player.vimeo.com/video/76979871?dnt=1&autoplay=1', 'playerStart' => 'https://player.vimeo.com/video/76979871?dnt=1&autoplay=1#t={s}s'],
			BlockSetting::getVideo('https://vimeo.com/76979871'),
			'le lecteur d\'un bloc est demandé à suivi réduit, avec démarrage pour le clic et sans pour le lien'
		);
		$this->assertEquals(['source' => NULL, 'name' => NULL, 'player' => NULL, 'playerAutoplay' => NULL, 'playerStart' => NULL], BlockSetting::getVideo('https://example.org/video.mp4'));

		$this->assertEquals('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&autoplay=1&start={s}', BlockSetting::getVideo('https://youtu.be/dQw4w9WgXcQ')['playerStart'], 'le saut à un instant pose le départ là où l\'hébergeur le lit');
		$this->assertEquals(['0:00', '1:05', '59:59', '1:00:00', '23:59:59'], array_map(new BlockTypeUi()->formatAt(...), [0, 65, 3599, 3600, BlockSetting::STEP_AT_MAX]), 'un instant s\'écrit comme sous une vidéo');

		$build = function(array $input): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'variant', 'url', 'title'], $input + ['type' => BlockTypeLib::VIDEO, 'variant' => BlockTypeLib::FULL],new \PropertyList('create'));

			return $this->getFails($fw);

		};

		$this->assertEquals([], $build(['url' => 'https://vimeo.com/76979871', 'title' => 'Le paillage en dix minutes']), 'vidéo acceptée avec sa légende');
		$this->assertEquals([], $build(['url' => '  https://vimeo.com/76979871 ', 'title' => '']), 'la légende est facultative, les espaces d\'un collage ne comptent pas');
		$this->assertEquals([['Block::url.missing', 'url']], $build(['url' => '']), 'une vidéo sans adresse est refusée');
		$this->assertEquals([['Block::url.missing', 'url']], $build([]), 'un POST sans le champ aussi');
		$this->assertEquals([['Block::url.host', 'url']], $build(['url' => 'https://example.org/video.mp4']), 'une adresse hors de la liste des hébergeurs est refusée');
		$this->assertEquals([['Block::url.host', 'url']], $build(['url' => 'https://www.dailymotion.com/video/x5myp5']), 'un hébergeur de vidéos qui n\'est pas dans la liste est refusé comme n\'importe quelle adresse');
		$this->assertEquals([['Block::url.host', 'url']], $build(['url' => 'https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ']), 'un hôte qui imite un hébergeur est refusé');
		$this->assertEquals([['Block::url.host', 'url']], $build(['url' => 'https://www.youtube.com/@zzPotagerBocage']), 'l\'adresse d\'une chaine ne s\'enregistre pas, même chez un hébergeur de la liste');
		$this->assertEquals([['Block::url.storable', 'url']], $build(['url' => 'https://vimeo.com/76979871?t=😀']), 'un caractère de quatre octets, que la colonne ne sait pas écrire, est refusé avant elle');
		$this->assertEquals([['Block::url.storable', 'url']], $build(['url' => 'https://vimeo.com/76979871?'.str_repeat('a', 70000)]), 'une adresse plus longue que la colonne aussi');
		$this->assertEquals([['Block::url.check', 'url']], $build(['url' => 'javascript:alert(1)']), 'ce qui n\'est pas une adresse est refusé par le schéma');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['url' => 'https://vimeo.com/76979871', 'variant' => BlockTypeLib::INFO]), 'une couleur d\'encadré n\'est pas une taille de vidéo');

		$eBlock = new Block(['id' => 1, 'type' => BlockTypeLib::VIDEO, 'variant' => BlockTypeLib::SMALL, 'url' => 'https://vimeo.com/76979871', 'title' => 'Paillage & semis', 'content' => NULL]);

		$this->assertTrue(BlockTypeLib::isEmpty($eBlock) === FALSE, 'une vidéo reconnue se montre');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::VIDEO, 'url' => NULL])), 'sans adresse, rien à montrer');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::VIDEO, 'url' => 'https://example.org/video.mp4'])), 'une adresse que la liste ne reconnait plus est écartée de la lecture');

		$h = new BlockTypeUi()->render($eBlock, []);

		$this->assertDiffer(new BlockTypeUi()->render(new Block(['variant' => BlockTypeLib::FULL] + $eBlock->getArrayCopy()), []), $h, 'la taille change la lecture : '.$h);

		$poster = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR)->querySelector('.block-video-frame > a[data-block-video]');

		$this->assertEquals('https://player.vimeo.com/video/76979871?dnt=1', $poster?->getAttribute('href'), 'le bouton est un lien vers le lecteur, posé directement dans le cadre : '.$h);
		$this->assertEquals('https://player.vimeo.com/video/76979871?dnt=1&autoplay=1', $poster?->getAttribute('data-block-video'), 'et le clic y pose le même lecteur avec le démarrage');
		$this->assertEquals(0, substr_count($h, 'https://vimeo.com/76979871'), 'l\'adresse enregistrée ne sort pas dans la lecture');

		// L'hébergeur refuse un lecteur dont il ne connait pas l'origine (`read.js`) : le lien suivi
		// sans script doit l'annoncer comme le lecteur posé au clic, et rien ne doit la lui retirer.
		$this->assertEquals('strict-origin-when-cross-origin', $poster->getAttribute('referrerpolicy'), 'le lien du cadre annonce l\'origine de la page à l\'hébergeur');
		$this->assertFalse(str_contains(strtolower($poster->getAttribute('rel') ?? ''), 'noreferrer'), 'aucun `rel` du lien ne retire le référent');

		// Ce qui suit l'identifiant dans l'adresse collée, un jeton de partage, un instant, une ancre,
		// peut désigner celui qui l'a copiée : ni le lien ni le lecteur ne le reprennent.
		$hShared = new BlockTypeUi()->render(new Block(['id' => 3, 'type' => BlockTypeLib::VIDEO, 'variant' => BlockTypeLib::FULL, 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=zzJeton&t=42#zzAncre', 'title' => NULL, 'content' => NULL]), []);

		$this->assertEquals(1, preg_match('/<a href="([^"]+)"[^>]* data-block-video="([^"]+)"[^>]*>/', $hShared, $addresses), 'le cadre d\'une adresse partagée porte son lien et son lecteur : '.$hShared);
		$this->assertTrue(str_contains($addresses[1] ?? '', 'dQw4w9WgXcQ'), 'le lien garde l\'identifiant de la vidéo');
		$this->assertEquals(($addresses[1] ?? '').'&amp;autoplay=1', $addresses[2] ?? '', 'le lecteur du clic ne diffère du lien que par le paramètre de démarrage');

		// Le `#` se cherche dans les deux adresses seulement : l'icône du bouton en porte un.
		foreach(['si=', 'zzJeton', 't=42', '#', 'zzAncre', 'watch'] as $shared) {
			$this->assertEquals(0, substr_count(($addresses[1] ?? '').($addresses[2] ?? ''), $shared), 'rien de ce qui suit l\'identifiant ne passe dans le lien ni dans le lecteur ('.$shared.') : '.$hShared);
		}

		$this->assertEquals(0, substr_count($hShared, 'zzJeton') + substr_count($hShared, 'zzAncre'), 'ni ailleurs dans la lecture');

		$this->assertEquals(0, substr_count($h, '<iframe'), 'rien ne part chez l\'hébergeur avant le clic');
		$this->assertEquals(0, substr_count($h, '<img'), 'ni lecteur ni image');
		$this->assertTrue(str_contains($h, '>Paillage &amp; semis<'), 'la légende est encodée');

		$eBlock['title'] = NULL;

		$this->assertEquals(0, substr_count(new BlockTypeUi()->render($eBlock, []), '<figcaption'), 'pas de légende vide');

		// Le script ne lance que l'enfant direct d'un cadre de vidéo qui porte `data-block-video`
		// (`read.js`) : ce qu'un texte ne doit jamais produire est cette parenté, et c'est elle que le
		// test garde. Qu'il ne garde ni l'attribut ni la classe est ce que l'éditeur de texte nettoie
		// aujourd'hui, figé à part pour qu'un relâchement de son nettoyage se voie ici avant d'atteindre
		// la parenté. Le rendu est lu par l'analyseur HTML, comme un navigateur le lit, puisqu'un `<li>`
		// ouvert dans un autre s'en retrouve le frère.
		$countPlayable = fn(string $html): int => \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html, LIBXML_NOERROR)->querySelectorAll('.block-video-frame > [data-block-video]')->length;

		$this->assertEquals(1, $countPlayable($h), 'la mesure trouve le lien d\'une vraie vidéo');

		$player = 'data-block-video="https://example.org/lecteur"';

		$contents = [
			'<p class="block-video-frame"><a href="https://example.org/" '.$player.'>Un lien</a></p>',
			'<ul class="block-video-frame"><li '.$player.'>Un lien</li></ul>',
			'<ul><li class="block-video-frame" '.$player.'>Un lien</li></ul>',
			'<ul><li class="block-video-frame"><a href="https://example.org/" '.$player.'>Un lien</a></li></ul>',
			'<ul><li class="block-video-frame"><b '.$player.'>Un lien</b></li></ul>',
			'<ul><li class="block-video-frame"><li '.$player.'>Un lien</li></li></ul>',
			'<ul><li class="block-video-frame"><ul><li '.$player.'>Un lien</li></ul></li></ul>',
			'<ul><li class="block-video-frame"><ol><li '.$player.'>Un lien</li></ol></li></ul>',
			'<ul><li class="block-video-frame"><b><li '.$player.'>Un lien</li></b></li></ul>',
			'<ul><li class="block-video-frame"></li></ul><ul><li '.$player.'>Un lien</li></ul>',
			// Les deux parentés du saut à l'instant d'une étape : le cadre qui porte le départ, et le bouton
			// d'une étape.
			'<p class="block-video-frame" data-block-video-start="https://example.org/lecteur?start={s}">Un lien</p>',
			'<ul class="util-procedure-step"><li class="block-step-at"><a href="https://example.org/" data-block-at="30">Un lien</a></li></ul>',
		];

		foreach($contents as $content) {

			$eBlockText = new Block(['id' => 2, 'owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block(), 'variant' => NULL, 'title' => NULL]);

			$fw = new \FailWatch();
			$eBlockText->build(['type', 'content'], ['type' => BlockTypeLib::TEXT, 'content' => '<p>Avant</p>'.$content], new \PropertyList('create'));

			$this->assertEquals([], $this->getFails($fw), 'le texte est accepté : '.$content);

			$h = new BlockTypeUi()->render($eBlockText, []);

			$this->assertTrue(str_contains($h, 'Avant') and str_contains($h, 'Un lien'), 'le texte posté se lit : '.$h);
			$this->assertEquals(0, $countPlayable($h), 'aucun texte ne rend ce que le script lance : '.$h);
			$this->assertEquals(0, substr_count($h, 'data-block-video') + substr_count($h, 'block-video-frame'), 'le texte ne garde ni l\'attribut ni la classe du cadre : '.$h);
			$this->assertEquals(0, substr_count($h, 'data-block-at') + substr_count($h, 'block-step-at'), 'ni l\'attribut ni la classe du bouton d\'instant : '.$h);

		}

	}

	/**
	 * La vidéo à côté d'un texte, du lot C9 : deux dispositions de plus, qui lisent le texte de la
	 * vidéo (`content`) dans l'ordre de la lecture, à gauche ou à droite de lui. Hors d'elles, le texte
	 * reste écrit et ne se lit pas, et son champ reste dans le formulaire, masqué. Le menu sépare les
	 * deux tailles des deux places à côté d'un texte (`hasVariantDivider()`).
	 */
	public function testVideoBeside(): void {

		$build = function(array $input): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'variant', 'url', 'title', 'content'], $input + ['type' => BlockTypeLib::VIDEO, 'url' => 'https://vimeo.com/76979871'], new \PropertyList('create'));

			return [$this->getFails($fw), $eBlock['content']];

		};

		$this->assertEquals([[], '<main><p>Paillez épais.</p></main>'], $build(['variant' => BlockTypeLib::LEFT, 'content' => '<p>Paillez épais.</p>']), 'à gauche du texte, le texte est écrit');
		$this->assertEquals([], $build(['variant' => BlockTypeLib::RIGHT, 'content' => '<p>Paillez épais.</p>'])[0], 'à droite du texte');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['variant' => BlockTypeLib::INFO, 'content' => NULL])[0], 'une couleur d\'encadré n\'est pas une disposition');
		// Ce que l'éditeur laisse dans un champ qu'on croit vide : la vidéo se lirait à côté d'une colonne
		// sans rien à lire.
		foreach(['<p></p>', '<p><br></p>', '<p>&nbsp;</p>', '<p> </p>', '<p></p><p></p>', '<p><br></p><p><br></p>', '<ul><li></li></ul>', '<h2></h2>', '<p><b> </b></p>'] as $empty) {
			$this->assertEquals([[], NULL], $build(['variant' => BlockTypeLib::LEFT, 'content' => $empty]), 'un texte sans rien à lire ne s\'écrit pas : '.$empty);
		}

		$this->assertEquals([[], '<main><p>0</p></main>'], $build(['variant' => BlockTypeLib::LEFT, 'content' => '<p>0</p>']), 'un texte qui ne dit que 0 en dit assez');

		// La règle est celle de la vidéo, dont le texte décide de la mise en page : un encadré garde ce
		// que l'éditeur a posté.
		$eBlockCallout = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
		$eBlockCallout->build(['type', 'content'], ['type' => BlockTypeLib::CALLOUT, 'content' => '<p></p><p></p>'], new \PropertyList('create'));

		$this->assertTrue($eBlockCallout['content'] !== NULL, 'le texte d\'un encadré n\'est pas jugé ici');

		$image = '<figure data-interactive="true"><div data-type="image" data-w="100" data-h="80" data-url="https://media.exemple.org/photo.jpg" data-color="#ffffff"></div></figure>';

		$this->assertEquals([[], '<main><p>Avant.</p><p>Après.</p></main>'], $build(['variant' => BlockTypeLib::LEFT, 'content' => '<p>Avant.</p>'.$image.'<p>Après.</p>']), 'le texte d\'une vidéo ne porte aucune photo, comme celui d\'un encadré');

		$read = fn(string $variant, ?string $content) => new BlockTypeUi()->render(new Block(['id' => 1, 'type' => BlockTypeLib::VIDEO, 'variant' => $variant, 'url' => 'https://vimeo.com/76979871', 'title' => 'Le paillage', 'content' => $content]), []);

		$content = '<main><p>Un paillage épais garde l\'humidité.</p></main>';

		$h = $read(BlockTypeLib::LEFT, $content);

		$this->assertTrue(str_contains($h, 'garde l\'humidit'), 'à gauche du texte, le texte se lit : '.$h);
		$this->assertTrue(str_contains($read(BlockTypeLib::RIGHT, $content), 'garde l\'humidit'), 'à droite du texte aussi');
		$this->assertDiffer($h, $read(BlockTypeLib::RIGHT, $content), 'à gauche et à droite du texte ne se lisent pas de même');

		foreach([BlockTypeLib::LEFT, BlockTypeLib::RIGHT] as $variant) {
			$this->assertEquals($read(BlockTypeLib::SMALL, NULL), $read($variant, NULL), 'sans texte, la vidéo se lit à la petite taille, sans colonne vide');
		}

		foreach([BlockTypeLib::FULL, BlockTypeLib::SMALL] as $variant) {
			$this->assertEquals(0, substr_count($read($variant, $content), 'humidit'), 'en '.$variant.', le texte gardé ne se lit pas');
		}

		// L'écriture : le champ du texte est rendu dans toutes les dispositions, masqué par `block.css` en
		// pleine largeur et en petite taille, sans quoi `build()` viderait le texte au premier
		// enregistrement d'une vidéo repassée à une taille.
		$document = fn(string $h): \Dom\HTMLDocument => \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.preg_replace('/<(\/?)template\b/', '<$1div', $h), LIBXML_NOERROR);

		$eBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER))[BlockTypeLib::VIDEO];

		$this->assertEquals(BlockTypeLib::FULL, $eBlockNew['variant'], 'une vidéo neuve naît en pleine largeur');

		$hNew = new BlockUi()->getNew($eBlockNew);
		$documentNew = $document($hNew);

		$this->assertTrue($documentNew->querySelector('form [data-block-variant-target]')?->classList->contains($documentNew->querySelector('.dropdown-item.selected[data-block-variant]')?->getAttribute('data-block-variant-class') ?? '') === TRUE, 'le menu échange la classe de la disposition choisie : '.$hNew);
		$this->assertTrue($documentNew->querySelector('form [data-name="content"]') !== NULL, 'le champ du texte est là en pleine largeur : '.$hNew);

		$this->assertEquals(
			[BlockTypeLib::LEFT],
			array_values(array_filter(BlockTypeLib::getVariants(BlockTypeLib::VIDEO), fn(string $variant) => new BlockTypeUi()->hasVariantDivider(BlockTypeLib::VIDEO, $variant))),
			'un seul séparateur au menu, avant les places à côté d\'un texte'
		);

		$editable = function(string $variant) use($content, $document): \Dom\HTMLDocument {

			$eBlock = new Block([
				'id' => 1,
				'owner' => self::OWNER,
				'reference' => 123,
				'parent' => new Block(),
				'type' => BlockTypeLib::VIDEO,
				'variant' => $variant,
				'url' => 'https://vimeo.com/76979871',
				'title' => NULL,
				'content' => $content,
				'position' => 0,
				'cBlock' => new \Collection(),
			]);

			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));

			return $document(new BlockUi()->getItem($eBlock));

		};

		foreach([BlockTypeLib::FULL, BlockTypeLib::SMALL, BlockTypeLib::LEFT] as $variant) {

			$documentSaved = $editable($variant);

			$this->assertTrue(str_contains($documentSaved->querySelector('form [data-name="content"]')?->innerHTML ?? '', 'humidit'), 'en '.$variant.', le texte gardé est dans son champ');
			$this->assertTrue($documentSaved->querySelector('.dropdown-item.selected[data-block-variant="'.$variant.'"]') !== NULL, 'la disposition enregistrée est choisie');

		}

		$message = fn(string $type): ?string => new \FailWatch()->getMessage('block', 'Block::variant.allowed', [new Block(['type' => $type]), '']);

		$this->assertEquals('Choisissez une des dispositions proposées.', $message(BlockTypeLib::VIDEO));
		$this->assertTrue($message(BlockTypeLib::VIDEO) !== $message(BlockTypeLib::CALLOUT), 'le refus d\'une disposition ne parle pas de couleur');

		// Seuls les blocs Texte sortent par l'API (`BlockLib::getText()`), comme pour un encadré.
		$this->assertEquals(NULL, BlockLib::getText(new \Collection([new Block(['type' => BlockTypeLib::VIDEO, 'variant' => BlockTypeLib::LEFT, 'content' => $content])])), 'le texte d\'une vidéo ne sort pas avec le texte de la leçon');

	}

	/**
	 * Le bouton du lot C3 : un libellé et une adresse, tous deux exigés, chacun refusé avec ses mots ;
	 * une adresse du web, quel que soit son hôte ; un lien qui ouvre un autre onglet, encodé.
	 */
	public function testButton(): void {

		$this->assertEquals(['title', 'url'], BlockTypeLib::getProperties(BlockTypeLib::BUTTON), 'dans l\'ordre du formulaire, le libellé puis l\'adresse');
		$this->assertEquals(['title', 'url'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::BUTTON));
		$this->assertEquals(['title', 'url'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::BUTTON));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::BUTTON));
		$this->assertEquals(NULL, BlockTypeLib::getChildrenMax(BlockTypeLib::BUTTON));
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::BUTTON));
		$this->assertEquals([], BlockTypeLib::getDefaultSettings(BlockTypeLib::BUTTON));

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals(array_search(BlockTypeLib::VIDEO, $types, TRUE) + 1, array_search(BlockTypeLib::BUTTON, $types, TRUE), 'le bouton est proposé aux leçons, juste après la vidéo');

		$build = function(array $input): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'title', 'url'], $input + ['type' => BlockTypeLib::BUTTON], new \PropertyList('create'));

			return $this->getFails($fw);

		};

		$this->assertEquals([], $build(['title' => 'Lire la fiche technique', 'url' => 'https://www.example.org/fiche.pdf']), 'bouton accepté en https');
		$this->assertEquals([], $build(['title' => 'Lire la fiche technique', 'url' => 'http://www.example.org/fiche']), 'et en http');
		$this->assertEquals([], $build(['title' => 'Voir la vidéo', 'url' => 'https://www.dailymotion.com/video/x5myp5']), 'l\'hôte d\'un bouton n\'est jugé contre aucune liste, celle des hébergeurs de vidéo ne vaut que pour la vidéo');

		$this->assertEquals([['Block::title.buttonLabel', 'title']], $build(['title' => '', 'url' => 'https://www.example.org/']), 'un bouton sans libellé est refusé, avec ses mots');
		$this->assertEquals([['Block::title.buttonLabel', 'title']], $build(['url' => 'https://www.example.org/']), 'un POST sans le champ aussi');
		$this->assertEquals([['Block::url.missing', 'url']], $build(['title' => 'Lire la fiche', 'url' => '']), 'un bouton sans adresse est refusé');
		$this->assertEquals([['Block::title.buttonLabel', 'title'], ['Block::url.missing', 'url']], $build([]), 'les deux refus ensemble, chacun sous son champ');

		foreach(['ftp://www.example.org/fiche.pdf', 'ftps://www.example.org/fiche.pdf'] as $url) {
			$this->assertEquals([['Block::url.scheme', 'url']], $build(['title' => 'Lire la fiche', 'url' => $url]), 'le schéma admet '.$url.', le bouton n\'ouvre que le web');
		}

		foreach(['www.example.org/fiche', 'mailto:contact@example.org', 'tel:+33100000000', 'javascript:alert(1)'] as $url) {
			$this->assertEquals([['Block::url.check', 'url']], $build(['title' => 'Lire la fiche', 'url' => $url]), $url.' est refusé par le schéma');
		}

		$this->assertEquals([['Block::url.storable', 'url']], $build(['title' => 'Lire la fiche', 'url' => 'https://www.example.org/?t=😀']), 'un caractère de quatre octets est refusé avant la colonne');

		$eBlockVideo = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
		$fw = new \FailWatch();
		$eBlockVideo->build(['type', 'variant', 'url'], ['type' => BlockTypeLib::VIDEO, 'variant' => BlockTypeLib::FULL, 'url' => 'ftp://www.youtube.com/watch?v=dQw4w9WgXcQ'], new \PropertyList('create'));

		$this->assertEquals([['Block::url.scheme', 'url']], $this->getFails($fw), 'une vidéo n\'est lue qu\'en http ou https');

		// Le message se lit comme `build()` le demande, sur l'élément et la valeur refusés
		// (`\Data::build()`, `$onError`) : celui de l'adresse dépend du type de l'élément.
		$message = fn(string $type, string $failName): ?string => new \FailWatch()->getMessage('block', $failName, [new Block(['type' => $type]), '']);

		foreach(['Block::url.missing', 'Block::url.check', 'Block::url.storable', 'Block::url.scheme'] as $failName) {

			$this->assertTrue($message(BlockTypeLib::BUTTON, $failName) !== $message(BlockTypeLib::VIDEO, $failName), $failName.' se dit autrement pour un bouton et pour une vidéo');
			$this->assertFalse(str_contains($message(BlockTypeLib::BUTTON, $failName), 'vidéo'), $failName.' ne parle pas de vidéo à un bouton : '.$message(BlockTypeLib::BUTTON, $failName));

		}

		$this->assertEquals('Collez l\'adresse complète de la page, avec https:// ou http://.', $message(BlockTypeLib::BUTTON, 'Block::url.scheme'));
		$this->assertEquals('Écrivez le texte du bouton.', $message(BlockTypeLib::BUTTON, 'Block::title.buttonLabel'));

		$eBlock = new Block(['id' => 1, 'type' => BlockTypeLib::BUTTON, 'variant' => NULL, 'title' => 'Paillage & semis', 'url' => 'https://www.example.org/a"onclick="x', 'content' => NULL]);

		$this->assertTrue(BlockTypeLib::isEmpty($eBlock) === FALSE, 'un bouton complet se montre');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::BUTTON, 'title' => 'Lire la fiche', 'url' => NULL])), 'sans adresse, rien à ouvrir');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::BUTTON, 'title' => NULL, 'url' => 'https://www.example.org/'])), 'sans libellé, rien à cliquer');

		$h = new BlockTypeUi()->render($eBlock, []);
		$link = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR)->querySelector('a');

		$this->assertTrue($link !== NULL, 'le bouton lu est un lien : '.$h);
		$this->assertEquals('https://www.example.org/a"onclick="x', $link->getAttribute('href'), 'l\'adresse entière reste dans l\'attribut, un guillemet ne le ferme pas');
		$this->assertEquals(NULL, $link->getAttribute('onclick'), 'aucun attribut ne naît de l\'adresse');
		$this->assertEquals('_blank', $link->getAttribute('target'), 'la page s\'ouvre dans un autre onglet');
		$this->assertEquals('nofollow', $link->getAttribute('rel'), 'l\'adresse est écrite par un auteur');
		$this->assertTrue(str_contains($h, '>Paillage &amp; semis '), 'le libellé est encodé : '.$h);

		$h = new BlockTypeUi()->getEditable(BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER))[BlockTypeLib::BUTTON], new \util\FormUi(), NULL);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR);

		$this->assertEquals(0, substr_count($h, '<a '), 'rien à suivre pendant qu\'on écrit : '.$h);
		$this->assertTrue($document->querySelector('[data-wrapper="title"] input[name="title"]') !== NULL, 'le libellé s\'écrit avec la place de son refus : '.$h);
		$this->assertTrue($document->querySelector('[data-wrapper="url"] input[name="url"]') !== NULL, 'l\'adresse aussi : '.$h);

		$cBlock = new \Collection([
			new Block(['type' => BlockTypeLib::TEXT, 'content' => '<main><p>Avant</p></main>']),
			new Block(['type' => BlockTypeLib::BUTTON, 'title' => 'Lire la fiche', 'url' => 'https://www.example.org/', 'content' => NULL]),
		]);

		$this->assertEquals('<main><p>Avant</p></main>', BlockLib::getText($cBlock), 'l\'API ne lit que les blocs texte, le bouton n\'y passe pas');

	}

	/**
	 * La section du lot C5 : un titre exigé et un surtitre facultatif rangé dans `settings`, sans enfant
	 * ni variante, proposée juste après le texte. Sa forme est donnée par l'aiguillage `format` de
	 * `Block::settings`, qui garde aussi celle du tableau. La lecture regroupe chaque section avec les
	 * blocs qui la suivent jusqu'à la suivante ; l'éditeur l'écrit dans sa tête, marque son enveloppe
	 * pour le bandeau et écrit son titre dans le plan.
	 */
	public function testSection(): void {

		$this->assertEquals(['settings', 'title'], BlockTypeLib::getProperties(BlockTypeLib::SECTION), 'dans l\'ordre du formulaire, le surtitre puis le titre');
		$this->assertEquals(['settings', 'title'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::SECTION));
		$this->assertEquals(['title'], BlockTypeLib::getPropertiesRequired(BlockTypeLib::SECTION));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::SECTION), 'une section ne porte rien, elle emporte les blocs qui la suivent');
		$this->assertEquals(NULL, BlockTypeLib::getChildrenMax(BlockTypeLib::SECTION));
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::SECTION), 'une seule teinte, celle du gabarit ou du réseau');
		$this->assertEquals(['kicker' => NULL], BlockTypeLib::getDefaultSettings(BlockTypeLib::SECTION), 'une section neuve n\'a pas de surtitre');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::SECTION, 'title' => NULL, 'settings' => ['kicker' => 'Étape 1']])), 'sans titre, rien ne l\'annonce');
		$this->assertFalse(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::SECTION, 'title' => 'Observer', 'settings' => ['kicker' => NULL]])), 'un titre suffit');

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals(BlockTypeLib::SECTION, $types[1], 'la section est proposée aux leçons juste après le texte');

		$build = function(mixed $settings, ?string $title = 'Observer le sol', Block $eBlockParent = new Block(), string $type = BlockTypeLib::SECTION): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => $eBlockParent]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'settings', 'title'], ['type' => $type, 'settings' => $settings, 'title' => $title], new \PropertyList('create'));

			return [$this->getFails($fw), $eBlock['settings'] ?? NULL];

		};

		$this->assertEquals([[], ['kicker' => 'Étape 1']], $build(['kicker' => 'Étape 1']), 'surtitre gardé');
		$this->assertEquals([[], ['kicker' => NULL]], $build(['kicker' => '']), 'un surtitre vide est l\'absence de surtitre');
		$this->assertEquals([[], ['kicker' => NULL]], $build(['kicker' => " \t "]), 'des blancs aussi');
		$this->assertEquals([[], ['kicker' => 'Étape 1 du paillage']], $build(['kicker' => " Étape 1\r\ndu paillage "]), 'un saut de ligne devient une espace, les bords sont retirés');
		$this->assertEquals([[], ['kicker' => 'A']], $build(['kicker' => 'A', 'rows' => [['x']]]), 'une clé étrangère postée n\'est pas gardée');
		$this->assertEquals([[], ['kicker' => NULL]], $build(BlockTypeLib::getDefaultSettings(BlockTypeLib::SECTION)), 'le réglage d\'une section neuve s\'enregistre tel quel');

		foreach(['texte', ['rows' => [['A']]], ['kicker' => ['A']], ['kicker' => 12], []] as $settings) {
			$this->assertEquals([['Block::settings.format', 'settings']], $build($settings)[0], 'refusé en entier : '.var_export($settings, TRUE));
		}

		$this->assertEquals([], $build(['kicker' => str_repeat('é', BlockSetting::SECTION_KICKER_LIMIT)])[0], 'un surtitre à la borne, compté en caractères');
		$this->assertEquals([['Block::settings.kicker', 'settings']], $build(['kicker' => str_repeat('é', BlockSetting::SECTION_KICKER_LIMIT + 1)])[0], 'un surtitre trop long est refusé');
		$this->assertEquals([['Block::title.missing', 'title']], $build(['kicker' => 'Étape 1'], '')[0], 'une section sans titre est refusée');
		$this->assertEquals([['Block::title.missing', 'title']], $build(['kicker' => 'Étape 1'], NULL)[0], 'un POST sans le champ aussi');

		// L'aiguillage : chaque type a sa forme, et chaque borne ne juge que celle de son type. Une
		// section qui passerait les bornes du tableau lèverait sur `rows`, absent de sa forme.
		$this->assertEquals([['Block::settings.format', 'settings']], $build(['kicker' => 'A'], NULL, new Block(), BlockTypeLib::TABLE)[0], 'un tableau ne prend pas la forme d\'une section');
		$this->assertEquals([[], ['rows' => [['A', 'B']]]], $build(['rows' => [['A', 'B']]], NULL, new Block(), BlockTypeLib::TABLE), 'un tableau valide passe inchangé');

		// Un type refusé ne laisse aucune forme à juger : sous un bloc d'étapes, qui ne porte que des
		// étapes, la section est refusée, et `settings` ne dit rien. Sans la garde de `format`, le
		// `match` sur un type absent lèverait.
		$this->assertEquals([['Block::type.allowed', 'type']], $build(['kicker' => ['A']], 'Observer', new Block(['type' => BlockTypeLib::STEPS]))[0], 'seul le type est refusé');

		// Le message se lit comme `build()` le demande, sur l'élément et la valeur refusés.
		$message = fn(string $type, string $failName): ?string => new \FailWatch()->getMessage('block', $failName, [new Block(['type' => $type]), '']);

		foreach(['Block::settings.check', 'Block::settings.format'] as $failName) {
			$this->assertTrue($message(BlockTypeLib::SECTION, $failName) !== $message(BlockTypeLib::TABLE, $failName), $failName.' se dit autrement pour une section et pour un tableau');
		}

		$this->assertEquals('Ce surtitre n\'a pas pu être enregistré, merci de le reprendre.', $message(BlockTypeLib::SECTION, 'Block::settings.format'));
		$this->assertEquals('Un surtitre contient au plus '.BlockSetting::SECTION_KICKER_LIMIT.' caractères.', $message(BlockTypeLib::SECTION, 'Block::settings.kicker'));

		// La lecture : un texte avant la première section, A emporte deux blocs, B (sans surtitre) un
		// encadré, C, en dernier, rien.
		$text = fn(string $text) => new Block(['type' => BlockTypeLib::TEXT, 'content' => '<main><p>'.$text.'</p></main>']);
		$section = fn(?string $kicker, string $title) => new Block(['type' => BlockTypeLib::SECTION, 'settings' => ['kicker' => $kicker], 'title' => $title]);

		$cBlock = new \Collection([
			$text('Avant'),
			$section('Étape 1 & <b>', 'Observer <le> sol'),
			$text('A1'),
			$text('A2'),
			$section(NULL, 'Couvrir'),
			new Block(['type' => BlockTypeLib::CALLOUT, 'variant' => BlockTypeLib::INFO, 'title' => 'B1', 'content' => NULL]),
			$section('Étape 3', 'Pour aller plus loin'),
		]);

		$h = new BlockUi()->render($cBlock);
		$sections = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR)->querySelectorAll('section');
		$sectionText = fn(int $position): string => $sections->item($position)?->textContent ?? '';

		$this->assertTrue(str_contains($h, 'Avant') and str_contains($sectionText(0), 'Avant') === FALSE, 'le bloc d\'avant la première section reste hors d\'elle : '.$h);
		$this->assertTrue(str_contains($sectionText(0), 'A1') and str_contains($sectionText(0), 'A2') and str_contains($sectionText(0), 'B1') === FALSE, 'A emporte deux blocs');
		$this->assertTrue(str_contains($sectionText(1), 'B1'), 'B un');
		$this->assertTrue($sections->item(1)?->firstElementChild?->childElementCount < $sections->item(0)?->firstElementChild?->childElementCount, 'B n\'a pas de surtitre');
		$this->assertTrue(str_contains($h, 'Étape 1 &amp; &lt;b&gt;') and str_contains($h, 'Observer &lt;le&gt; sol'), 'surtitre et titre, encodés : '.$h);

		$this->assertEquals(NULL, BlockLib::getText(new \Collection([$section('Étape 1', 'Observer')])), 'l\'API ne lit que les blocs texte, la section n\'y passe pas');

		// L'éditeur.
		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));
		$h = preg_replace('/<(\/?)template\b/', '<$1div', new BlockUi()->getNew($cBlockNew[BlockTypeLib::SECTION]));
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR);
		$head = $document->querySelector('#block-new[data-block-section] [data-wrapper="settings title"]');

		$this->assertTrue($head !== NULL, 'la section à écrire marque son enveloppe pour le bandeau, et sa tête reçoit les deux refus : '.$h);
		$this->assertTrue($head?->querySelector('input[name="settings[kicker]"]') !== NULL and $head?->querySelector('input[name="title"]') !== NULL, 'le surtitre et le titre s\'écrivent dans la tête');
		$this->assertFalse(str_contains(new BlockUi()->getNew($cBlockNew[BlockTypeLib::TEXT]), 'data-block-section'), 'un texte à écrire n\'est pas marqué');

		$eBlockSection = new Block(['id' => 1, 'type' => BlockTypeLib::SECTION, 'variant' => NULL, 'settings' => ['kicker' => NULL], 'title' => 'Couvrir & pailler']);
		$eBlockText = new Block(['id' => 2, 'type' => BlockTypeLib::TEXT, 'variant' => NULL]);

		$this->assertTrue(str_contains(new BlockUi()->getPlanRow($eBlockSection), 'Couvrir &amp; pailler'), 'la ligne du plan écrit le titre de la section, encodé : '.new BlockUi()->getPlanRow($eBlockSection));
		$this->assertTrue(str_contains(new BlockUi()->getPlanRow($eBlockText), new BlockTypeUi()->getName(BlockTypeLib::TEXT)), 'celle d\'un autre bloc, le nom de son type');

	}

	/**
	 * Les étiquettes du lot C8 : des mots rangés dans `settings`, sans enfant ni variante, proposées
	 * juste avant le tableau. `format` les nettoie sans rien dire (bords, blancs, vides, doublons à la
	 * casse près) et garde l'ordre de la saisie ; `full` et `tag` refusent ce qui reste au-delà des
	 * bornes, et laissent passer les autres types.
	 */
	public function testTags(): void {

		$this->assertEquals(['settings'], BlockTypeLib::getProperties(BlockTypeLib::TAGS));
		$this->assertEquals(['settings'], BlockTypeLib::getPropertiesCreate(BlockTypeLib::TAGS));
		$this->assertEquals([], BlockTypeLib::getPropertiesRequired(BlockTypeLib::TAGS));
		$this->assertEquals([], BlockTypeLib::getChildren(BlockTypeLib::TAGS));
		$this->assertEquals(NULL, BlockTypeLib::getChildrenMax(BlockTypeLib::TAGS));
		$this->assertEquals([], BlockTypeLib::getVariants(BlockTypeLib::TAGS));
		$this->assertEquals(['tags' => ['']], BlockTypeLib::getDefaultSettings(BlockTypeLib::TAGS), 'des étiquettes neuves s\'ouvrent sur une pastille vide');
		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::TAGS, 'settings' => ['tags' => []]])), 'sans mot, rien à lire');
		$this->assertFalse(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::TAGS, 'settings' => ['tags' => ['Sol vivant']]])), 'un mot suffit');

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals(BlockTypeLib::TAGS, $types[count($types) - 2], 'les étiquettes sont proposées aux leçons juste avant le tableau');

		$build = function(mixed $settings, string $type = BlockTypeLib::TAGS): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => new Block()]);
			$fw = new \FailWatch();
			$eBlock->build(['type', 'settings'], ['type' => $type, 'settings' => $settings], new \PropertyList('create'));

			return [$this->getFails($fw), $eBlock['settings'] ?? NULL];

		};

		$words = fn(int $count): array => array_map(fn(int $position) => 'Mot '.$position, range(1, $count));

		$this->assertEquals([[], ['tags' => ['Sol vivant', 'Vente directe']]], $build(['tags' => [" Sol \t vivant\r\n", '', 'sol vivant', ' ', 'Vente directe', 'SOL VIVANT']]), 'bords et blancs retirés, vides et doublons écartés à la casse près, le premier écrit gardé à sa place');
		$this->assertEquals([[], ['tags' => ['Sol vivant']]], $build(['tags' => ["Sol\u{00A0}vivant", 'sol vivant']]), 'une espace insécable compte pour une espace');
		$this->assertEquals([[], ['tags' => ['Élevage']]], $build(['tags' => ['Élevage', 'élevage']]), 'la casse se compare aussi sur une lettre accentuée');
		$this->assertEquals([[], ['tags' => ['Sol vivant', 'Bocage']]], $build(['tags' => ["\u{200B}", "Sol\u{200B} vivant\u{FEFF}", 'sol vivant', "Bo\u{2060}cage"]]), 'un caractère de format invisible est retiré, et seul il ne fait pas d\'étiquette');
		$this->assertEquals([[], ['tags' => ["\u{1F469}\u{200D}\u{1F33E}"]]], $build(['tags' => ["\u{1F469}\u{200D}\u{1F33E}"]]), 'le liant d\'un emoji composé reste');
		$this->assertEquals([[], ['tags' => ['élevage']]], $build(['tags' => ["e\u{0301}levage", "\u{00E9}levage"]]), 'un accent écrit à part ou composé fait la même étiquette, gardée en forme composée');
		$this->assertEquals([[], ['tags' => ['B', 'A']]], $build(['tags' => ['B', 'A']]), 'l\'ordre de la saisie est gardé');
		$this->assertEquals([[], ['tags' => []]], $build(['tags' => ['', ' ']]), 'aucun mot n\'est l\'absence d\'étiquettes');
		$this->assertEquals([[], ['tags' => ['A']]], $build(['tags' => ['A'], 'rows' => [['x']]]), 'une clé étrangère postée n\'est pas gardée');

		foreach(['texte', [], ['tags' => 'A'], ['tags' => [['A']]], ['tags' => [12]], ['tags' => ["\xC3\x28"]], ['kicker' => 'A']] as $settings) {
			$this->assertEquals([['Block::settings.format', 'settings']], $build($settings)[0], 'refusé en entier : '.var_export($settings, TRUE));
		}

		$this->assertEquals([], $build(['tags' => $words(BlockSetting::TAGS_MAX)])[0], 'des étiquettes au plafond');
		$this->assertEquals([['Block::settings.full', 'settings']], $build(['tags' => $words(BlockSetting::TAGS_MAX + 1)])[0], 'une de trop est refusée');
		$this->assertEquals([], $build(['tags' => [...$words(BlockSetting::TAGS_MAX), 'mot 1']])[0], 'un doublon ne compte pas : le compte se fait une fois les mots nettoyés');
		$this->assertEquals([], $build(['tags' => [str_repeat('é', BlockSetting::TAG_LIMIT)]])[0], 'un mot à la borne, compté en caractères');
		$this->assertEquals([['Block::settings.tag', 'settings']], $build(['tags' => ['A', str_repeat('é', BlockSetting::TAG_LIMIT + 1)]])[0], 'un mot trop long est refusé, plutôt que coupé');

		// Les bornes des étiquettes laissent passer les autres types, et celles des autres types les
		// étiquettes : un tableau de onze lignes de mots de plus de 40 caractères reste admis.
		$rows = array_fill(0, BlockSetting::TAGS_MAX + 1, [str_repeat('é', BlockSetting::TAG_LIMIT + 1)]);
		$this->assertEquals([[], ['rows' => $rows]], $build(['rows' => $rows], BlockTypeLib::TABLE), 'un tableau n\'est pas jugé comme des étiquettes');
		$this->assertEquals([['Block::settings.format', 'settings']], $build(['tags' => ['A']], BlockTypeLib::TABLE)[0], 'un tableau ne prend pas la forme des étiquettes');

		// Le message se lit comme `build()` le demande, sur l'élément et la valeur refusés.
		$message = fn(string $type, string $failName): ?string => new \FailWatch()->getMessage('block', $failName, [new Block(['type' => $type]), '']);

		$this->assertEquals('Ces étiquettes n\'ont pas pu être enregistrées, merci de les reprendre.', $message(BlockTypeLib::TAGS, 'Block::settings.format'));
		$this->assertEquals($message(BlockTypeLib::TAGS, 'Block::settings.format'), $message(BlockTypeLib::TAGS, 'Block::settings.check'), 'le refus du schéma se dit de même');
		$this->assertEquals('Un bloc porte au plus '.BlockSetting::TAGS_MAX.' étiquettes.', $message(BlockTypeLib::TAGS, 'Block::settings.full'));
		$this->assertEquals('Une étiquette contient au plus '.BlockSetting::TAG_LIMIT.' caractères.', $message(BlockTypeLib::TAGS, 'Block::settings.tag'));

		// La lecture.
		$h = new BlockTypeUi()->render(new Block(['type' => BlockTypeLib::TAGS, 'settings' => ['tags' => ['Vente directe', 'Sol <b>vivant</b>']]]));

		$this->assertTrue(str_contains($h, '>Vente directe<') and str_contains($h, '>Sol &lt;b&gt;vivant&lt;/b&gt;<'), 'chaque mot se lit, encodé : '.$h);

		// L'éditeur : le bloc neuf, puis un bloc enregistré sans mot, à neuf mots et au plafond.
		$editable = function(array $tags): \Dom\HTMLDocument {

			$eBlock = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER))[BlockTypeLib::TAGS];
			$eBlock['settings'] = ['tags' => $tags];

			$h = preg_replace('/<(\/?)template\b/', '<$1div', new BlockUi()->getNew($eBlock));

			return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body>'.$h, LIBXML_NOERROR);

		};

		$document = $editable(['']);
		$list = $document->querySelector('#block-new .block-tags.block-tags-edit[data-block-tags]');

		$this->assertTrue($list !== NULL, 'la rangée ouverte garde la classe de sa lecture');
		$this->assertEquals((string)BlockSetting::TAGS_MAX, $list->getAttribute('data-block-tags-max'), 'le plafond vient du serveur');
		$this->assertEquals(1, $document->querySelectorAll('#block-new [data-block-tags] > .badge > input[name="settings[tags][]"]')->length, 'une pastille vide à écrire');
		$this->assertTrue($document->querySelector('#block-new [data-block-tags] > .badge > [data-block-tags-remove]') !== NULL, 'avec sa croix');
		$this->assertFalse(str_contains($document->querySelector('#block-new [data-block-tags] > [data-block-tags-add]')->getAttribute('class'), 'hide'), '« + Étiquette » au bout de la rangée');
		$this->assertTrue($document->querySelector('#block-new [data-block-tags-new] > .badge > input[name="settings[tags][]"]') !== NULL, 'le modèle d\'une pastille neuve');
		$this->assertTrue($document->querySelector('#block-new [data-wrapper="settings"]') !== NULL, 'les refus ont leur place');

		$this->assertEquals(1, $editable([])->querySelectorAll('[data-block-tags] > .badge > input[value=""]')->length, 'un bloc enregistré sans mot s\'ouvre aussi sur une pastille vide');

		$document = $editable(['Sol & <b>', ...$words(BlockSetting::TAGS_MAX - 2)]);

		$this->assertEquals('Sol & <b>', $document->querySelector('[data-block-tags] > .badge > input')->getAttribute('value'), 'le mot dans son champ');
		$this->assertFalse(str_contains($document->querySelector('[data-block-tags-add]')->getAttribute('class'), 'hide'), 'le « + » reste sous le plafond');
		$this->assertTrue(str_contains($editable($words(BlockSetting::TAGS_MAX))->querySelector('[data-block-tags-add]')->getAttribute('class'), 'hide'), 'et disparait au plafond');

	}

	public function testBuild(): void {

		$build = function(array $properties, array $input, Block $eBlockParent = new Block()): array {

			$eBlock = new Block(['owner' => self::OWNER, 'reference' => self::REFERENCE_IN_MEMORY, 'eOwner' => $this->getLessonInMemory(), 'parent' => $eBlockParent]);
			$fw = new \FailWatch();
			$eBlock->build($properties, $input, new \PropertyList('create'));

			return $this->getFails($fw);

		};

		$this->assertEquals([], $build(['type', 'variant', 'title', 'content'], ['type' => BlockTypeLib::CALLOUT, 'variant' => BlockTypeLib::WARNING, 'title' => 'Attention', 'content' => '<p>Sol humide.</p>']), 'encadré accepté');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['type', 'variant'], ['type' => BlockTypeLib::CALLOUT, 'variant' => 'danger']), 'une variante hors de la liste du type est refusée');
		$this->assertEquals([['Block::variant.allowed', 'variant']], $build(['type', 'variant'], ['type' => BlockTypeLib::CALLOUT, 'variant' => '']), 'un encadré sans variante est refusé');
		$this->assertEquals([['Block::type.allowed', 'type']], $build(['type'], ['type' => BlockTypeLib::ITEM]), 'une étape ne se pose pas au premier niveau');
		$this->assertEquals([['Block::title.tabName', 'title']], $build(['type', 'title'], ['type' => BlockTypeLib::TABS, 'title' => '']), 'un onglet sans nom est refusé dès la création du parent, par le refus propre au nom d\'un onglet');
		$this->assertEquals([['Block::title.missing', 'title']], $build(['type', 'title'], ['type' => BlockTypeLib::STEPS, 'title' => '']), 'une étape sans titre garde le refus du titre');

		$eBlockSteps = new Block(['type' => BlockTypeLib::STEPS]);
		$eBlockText = new Block(['type' => BlockTypeLib::TEXT]);

		$this->assertEquals([], $build(['type'], ['type' => BlockTypeLib::ITEM], $eBlockSteps), 'une étape sous un parent qui en porte');
		$this->assertEquals([['Block::type.allowed', 'type']], $build(['type'], ['type' => BlockTypeLib::TEXT], $eBlockSteps), 'un texte sous un parent qui ne porte que des étapes est refusé');
		$this->assertEquals([['Block::type.allowed', 'type']], $build(['type'], ['type' => BlockTypeLib::ITEM], $eBlockText), 'rien sous un texte');

	}

	/**
	 * Le « + » d'un bloc composé crée le parent et sa première étape d'un seul enregistrement ; le
	 * « + » d'une étape crée sa voisine, dont le parent se déduit de l'étape désignée.
	 */
	public function testCreatePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), \learning\Course::DRAFT, \learning\Course::PRIVATE);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon à types', 10);

		$eBlockA = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'type' => BlockTypeLib::TEXT, 'content' => '<main><p>A</p></main>']);
		BlockLib::create($eBlockA);

		$post = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'after' => (string)$eBlockA['id'],
			'type' => BlockTypeLib::STEPS,
			'title' => 'Gratter le sol',
			'content' => '<p>Sur un mètre de rang.</p>',
		];

		$this->assertEquals([\FailAction::class, 'Block::variant.allowed'], $this->executePage('block/block:doCreate', $post), 'des étapes sans repère sont refusées');
		$this->assertEquals(1, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est créé');

		$post['variant'] = BlockTypeLib::CHECK;

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post), 'étapes créées');

		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);
		$eBlockSteps = $cBlock->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::STEPS)->first();

		$this->assertTrue($eBlockSteps !== NULL, 'le parent existe');
		$this->assertEquals(NULL, $eBlockSteps['title'], 'le parent ne garde pas le titre');
		$this->assertEquals(BlockTypeLib::CHECK, $eBlockSteps['variant'], 'le parent porte le repère posté');
		$this->assertEquals(NULL, $eBlockSteps['cBlock']->first()['variant'], 'sa première étape n\'en porte pas');
		$this->assertEquals(['Gratter le sol'], array_values($eBlockSteps['cBlock']->getColumn('title')), 'la première étape porte ce qui a été écrit');

		$eBlockS1 = $eBlockSteps['cBlock']->first();

		$postChild = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'after' => (string)$eBlockS1['id'],
			'type' => BlockTypeLib::ITEM,
			'title' => 'Vérifier le stade',
			'content' => '<p>Trois feuilles.</p>',
		];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $postChild), 'étape ajoutée');

		$eBlockSteps = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::STEPS)->first();
		$this->assertEquals(['Gratter le sol', 'Vérifier le stade'], array_values($eBlockSteps['cBlock']->getColumn('title')), 'rangée après l\'étape désignée, sous le même parent');
		$this->assertEquals(2, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'le premier niveau n\'a pas bougé');

		$postChild['type'] = BlockTypeLib::TEXT;
		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $postChild), 'un texte ne se pose pas parmi les étapes');

		$postRoot = $post;
		$postRoot['type'] = BlockTypeLib::ITEM;
		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $postRoot), 'une étape ne se pose pas au premier niveau');

		$eBlockS2 = $eBlockSteps['cBlock']->find(fn(Block $eBlock) => $eBlock['title'] === 'Vérifier le stade')->first();

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockS2['id'], 'title' => 'Vérifier', 'content' => '<p>Trois.</p>', 'close' => '1']), 'étape refermée');
		$this->assertEquals([\FailAction::class, 'Block::title.missing'], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockS2['id'], 'title' => '', 'content' => '<p>Trois.</p>']), 'une étape garde son titre');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doIncrementPosition', ['id' => (string)$eBlockS2['id'], 'increment' => '-1']), 'étape montée');
		$this->assertEquals(['Vérifier', 'Gratter le sol'], $this->getChildTitles($eLesson), 'dans sa fratrie');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doDuplicate', ['id' => (string)$eBlockS2['id']]), 'étape copiée');
		$this->assertEquals(['Vérifier', 'Vérifier', 'Gratter le sol'], $this->getChildTitles($eLesson), 'sous l\'originale');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doDelete', ['id' => (string)$eBlockS2['id']]), 'étape retirée');
		$this->assertEquals(['Vérifier', 'Gratter le sol'], $this->getChildTitles($eLesson), 'les autres restent');

		// Un POST forgé qui porte une variante sur un parent qui n'en a pas : rien ne la construit, le
		// parent naît sans, au lieu de lever dans le message de `variant.allowed`, qui ne connaît que
		// les types à variantes.
		$postForged = ['type' => BlockTypeLib::ACCORDION, 'variant' => BlockTypeLib::CHECK] + $post;

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $postForged), 'accordéon créé malgré la variante postée');

		$eBlockAccordion = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::ACCORDION)->first();

		$this->assertEquals(NULL, $eBlockAccordion['variant'], 'sans variante');
		$this->assertEquals(NULL, $eBlockAccordion['cBlock']->first()['variant'], 'son premier volet non plus');

	}

	private function getChildTitles(\learning\Lesson $eLesson): array {

		$eBlockSteps = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::STEPS)->first();

		return array_values($eBlockSteps['cBlock']->getColumn('title'));

	}

	/**
	 * La leçon que la page relit, construite en mémoire : un type de premier niveau se juge sur ce
	 * qu'elle propose (`Block::type.allowed`).
	 */
	private function getLessonInMemory(): \learning\Lesson {
		return new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure()])]);
	}

}
?>
