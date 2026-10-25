<?php
namespace block;

require_once __DIR__.'/../../../ouvretaferme/learning/test/LearningFixture.t.php';
require_once __DIR__.'/OpenTypeFixture.php';

/**
 * Un type de bloc d'un autre package que le moteur, tel qu'un package fermé l'apporte, éprouvé sur le
 * type de `OpenTypeFixture.php`, que le moteur ne connaît que par son nom. Le moteur lui demande la
 * forme de son réglage (`castSettings()`), et lui laisse dire les refus dont les mots dépendent du
 * type (`AlertUi::ERRORS_BY_TYPE`). Ces cas construisent leurs blocs en mémoire.
 *
 * Le type n'est proposé qu'aux leçons de l'équipe, par un observateur de `learning`, que `setUp()`
 * inscrit seul à l'événement, à la place de ceux qu'un package y inscrit, et que `tearDown()` retire.
 * Les types qu'un autre package ajoute aux leçons ne se mêlent pas ainsi à ceux de la fixture. Comme
 * le code, ces cas ne l'attendent que sous l'application comptable (`hasAccountingApp()`), seule à
 * écouter l'événement. Ils écrivent leurs leçons dans la transaction du test.
 *
 * Lancement : dev/test package=block file=BlockOpenType
 */
class BlockOpenTypeTest extends \learning\LearningFixtureTest {

	const OWNER = \learning\BlockOwnerLib::LESSON;

	private array $observersInitial = [];

	public function setUp(): void {

		parent::setUp();

		$this->observersInitial = \Package::getObservers();

		$observers = $this->observersInitial;
		$observers['lib']['learning']['getBlockTypes'] = ['blockFixture'];

		\Package::setObservers($observers);

	}

	public function tearDown(): void {

		\Package::setObservers($this->observersInitial);

		parent::tearDown();

	}

	/**
	 * Le réglage est mis en forme par le type, et refusé sous le nom qu'il lui donne, rattaché au champ
	 * `settings`. Ce que le type ne sait pas lire est refusé par le moteur (`settings.format`), et les
	 * bornes des types du moteur le laissent passer.
	 */
	public function testSettingsAreFormedByTheType(): void {

		$this->assertEquals([[], ['label' => 'Semis']], $this->build(['label' => " Semis \t"]), 'le libellé est mis en forme par le type');
		$this->assertEquals([[], ['label' => NULL]], $this->build(\blockFixture\BlockTypeLib::getDefaultSettings(\blockFixture\BlockTypeLib::ENTRIES, \blockFixture\BlockTypeLib::A)), 'le réglage d\'un bloc neuf s\'enregistre tel quel');

		$this->assertEquals([['blockFixture\Block::settings.label', 'settings']], $this->build(['label' => str_repeat('é', \blockFixture\BlockTypeLib::LABEL_LIMIT + 1)])[0], 'le type nomme son refus dans son package, sur le champ settings');

		foreach(['texte', [], ['label' => ['A']], ['rows' => [['A']]]] as $settings) {
			$this->assertEquals([['block\Block::settings.format', 'settings']], $this->build($settings)[0], 'ce que le type ne sait pas lire est refusé en entier : '.var_export($settings, TRUE));
		}

	}

	/**
	 * Un refus du moteur dont les mots dépendent du type se dit avec les mots du type, lus dans son
	 * package sous la même clé. Ceux que le type ne dit pas restent sans message, sans rien lever, et
	 * les types du moteur gardent les leurs.
	 */
	public function testRefusalsSpeakTheWordsOfTheType(): void {

		$fw = new \FailWatch();
		$this->buildBlock(['label' => str_repeat('é', \blockFixture\BlockTypeLib::LABEL_LIMIT + 1)]);
		$this->buildBlock('texte');

		$this->assertEquals([
			['Block::settings.label', 'Un libellé contient au plus '.\blockFixture\BlockTypeLib::LABEL_LIMIT.' caractères.', 'settings'],
			['Block::settings.format', 'Ce libellé n\'a pas pu être enregistré.', 'settings'],
		], $fw->format(), 'le refus du type comme celui du moteur se disent avec les mots du type');

		$message = fn(string $type, string $failName): ?string => new \FailWatch()->getMessage('block', $failName, [new Block(['type' => $type]), 'c']);

		$this->assertEquals('Choisissez la forme a ou la forme b.', $message(\blockFixture\BlockTypeLib::ENTRIES, 'Block::variant.allowed'), 'le refus d\'une variante se dit avec les mots du type');
		$this->assertEquals('Ce libellé n\'a pas pu être enregistré.', $message(\blockFixture\BlockTypeLib::ENTRIES, 'Block::settings.check'), 'le refus du schéma aussi');
		$this->assertEquals(NULL, $message(\blockFixture\BlockTypeLib::ENTRIES, 'Block::title.missing'), 'un refus que le type ne dit pas reste sans message, sans lever');
		$this->assertEquals('Ces étiquettes n\'ont pas pu être enregistrées, merci de les reprendre.', $message(BlockTypeLib::TAGS, 'Block::settings.format'), 'un type du moteur garde ses mots');

	}

	/**
	 * Une leçon de l'équipe propose le type, après ceux de l'usage ; une leçon de réseau ne le propose
	 * pas. Sans observateur inscrit, aucune ne le propose.
	 */
	public function testTypesFollowTheLesson(): void {

		$offered = \structure\StructureSetting::hasAccountingApp();

		$eLessonTeam = new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure()])]);
		$eLessonNetwork = new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure(['id' => self::COMMUNITY_EDUCATION])])]);

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$this->assertEquals($offered ? array_merge($types, [\blockFixture\BlockTypeLib::ENTRIES]) : $types, \learning\BlockOwnerLib::getTypesByOwner(self::OWNER, $eLessonTeam), 'une leçon de l\'équipe propose le type, après ceux de l\'usage');
		$this->assertEquals($types, \learning\BlockOwnerLib::getTypesByOwner(self::OWNER, $eLessonNetwork), 'une leçon de réseau ne le propose pas');

		$observers = $this->observersInitial;
		unset($observers['lib']['learning']['getBlockTypes']);

		\Package::setObservers($observers);

		$this->assertEquals($types, \learning\BlockOwnerLib::getTypesByOwner(self::OWNER, $eLessonTeam), 'sans observateur, la leçon de l\'équipe ne le propose pas');

	}

	/**
	 * Le « + » crée le type dans une leçon de l'équipe et le refuse dans une leçon de réseau, sur
	 * l'objet que la page relit et non sur une liste de l'usage.
	 */
	public function testCreateFollowsTheLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$post = fn(\learning\Lesson $eLesson) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => \blockFixture\BlockTypeLib::ENTRIES,
			'variant' => \blockFixture\BlockTypeLib::A,
			'settings' => ['label' => 'Semis'],
		];

		$eLessonTeam = $this->createLessonIn(new \structure\Structure());

		if(\structure\StructureSetting::hasAccountingApp()) {
			$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post($eLessonTeam)), 'créé dans une leçon de l\'équipe');
			$this->assertEquals([[\blockFixture\BlockTypeLib::ENTRIES, \blockFixture\BlockTypeLib::A, ['label' => 'Semis']]], $this->getBlocks($eLessonTeam), 'avec sa forme et son réglage mis en forme par le type');
		} else {
			$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $post($eLessonTeam)), 'sans l\'application comptable, le type n\'est proposé nulle part');
			$this->assertEquals([], $this->getBlocks($eLessonTeam), 'rien n\'est créé');
		}

		$eLessonNetwork = $this->createLessonIn($this->getCommunity(self::COMMUNITY_EDUCATION));

		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $post($eLessonNetwork)), 'refusé dans une leçon de réseau');
		$this->assertEquals([], $this->getBlocks($eLessonNetwork), 'rien n\'est créé');

	}

	/**
	 * Le « / » juge le type choisi sur la leçon du texte coupé, comme le « + » : un type refusé ne
	 * coupe rien.
	 */
	public function testSplitFollowsTheLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$split = function(\learning\Lesson $eLesson): array {

			$eBlockText = new Block([
				'owner' => self::OWNER,
				'reference' => $eLesson['id'],
				'type' => BlockTypeLib::TEXT,
				'content' => '<main><p>Un Deux</p></main>',
			]);

			BlockLib::create($eBlockText);

			return $this->executePage('block/block:doSplit', [
				'id' => (string)$eBlockText['id'],
				'type' => \blockFixture\BlockTypeLib::ENTRIES,
				'content' => '<p>Un</p>',
				'rest' => '<p>Deux</p>',
			]);

		};

		$eLessonTeam = $this->createLessonIn(new \structure\Structure());

		if(\structure\StructureSetting::hasAccountingApp()) {
			$this->assertEquals([\ViewAction::class, NULL], $split($eLessonTeam), 'coupé dans une leçon de l\'équipe');
			$this->assertEquals(2, count($this->getBlocks($eLessonTeam)), 'en deux textes');
		} else {
			$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $split($eLessonTeam), 'sans l\'application comptable, le type n\'est proposé nulle part');
		}

		$eLessonNetwork = $this->createLessonIn($this->getCommunity(self::COMMUNITY_EDUCATION));

		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $split($eLessonNetwork), 'refusé dans une leçon de réseau');
		$this->assertEquals(1, count($this->getBlocks($eLessonNetwork)), 'rien n\'est coupé');

	}

	/**
	 * Un type à forme fixée a une entrée par variante, à la suite des types qui le précèdent. Chaque
	 * entrée a sa clé, que portent sa tuile, sa ligne du menu, son gabarit et le formulaire de son bloc
	 * à écrire. Ce formulaire poste la variante, et la barre n'a pas de menu qui la change. Un type à
	 * variantes du moteur garde une seule entrée, et son menu.
	 */
	public function testFixedFormHasOneChoicePerVariant(): void {

		$typesOwner = \learning\BlockOwnerLib::getTypes(self::OWNER);
		$types = array_merge($typesOwner, [\blockFixture\BlockTypeLib::ENTRIES]);

		$keyA = \blockFixture\BlockTypeLib::ENTRIES.':'.\blockFixture\BlockTypeLib::A;
		$keyB = \blockFixture\BlockTypeLib::ENTRIES.':'.\blockFixture\BlockTypeLib::B;

		$choices = BlockLib::getChoices($types);

		$this->assertEquals(array_merge($typesOwner, [$keyA, $keyB]), array_keys($choices), 'une entrée par type, une par variante de la forme fixée, dans l\'ordre des types');
		$this->assertEquals(['type' => \blockFixture\BlockTypeLib::ENTRIES, 'variant' => \blockFixture\BlockTypeLib::B], $choices[$keyB], 'l\'entrée porte son type et sa variante');
		$this->assertEquals(['type' => BlockTypeLib::CALLOUT, 'variant' => NULL], $choices[BlockTypeLib::CALLOUT], 'un type à variantes du moteur n\'a qu\'une entrée');

		$h = new BlockUi()->getEditor(new \Collection(), $choices, BlockLib::getNew(self::OWNER, 123, $types), self::OWNER, 123);

		$start = explode('<div id="block-list"', explode('<div id="block-start"', $h)[1])[0];
		$menu = explode('</template>', explode('<template data-block-menu>', $h)[1])[0];

		preg_match_all('/<a class="util-deck" data-block-choice data-type="([^"]+)">/', $start, $tiles);
		preg_match_all('/<a class="dropdown-item" data-block-choice data-type="([^"]+)">/', $menu, $items);

		$this->assertEquals(array_keys($choices), $tiles[1], 'une tuile par entrée, à sa clé');
		$this->assertEquals(array_keys($choices), $items[1], 'une ligne du menu par entrée, à sa clé');
		$this->assertTrue(str_contains($start, 'data-type="'.$keyB.'"><div class="util-deck-head"><div class="util-icon">'.\Asset::icon('journal-text').'</div><div class="util-deck-body"><div class="util-deck-title">Forme b</div><p>Une grille</p>'), 'la tuile dit le nom et la ligne de la forme, sous l\'icône du type');
		$this->assertTrue(str_contains($menu, 'data-type="'.$keyA.'">'.\Asset::icon('journal-text').' Forme a</a>'), 'la ligne du menu dit le nom de la forme');

		$templateA = $this->getTemplate($h, $keyA);
		$templateB = $this->getTemplate($h, $keyB);
		$templateCallout = $this->getTemplate($h, BlockTypeLib::CALLOUT);

		foreach([$keyA => $templateA, $keyB => $templateB] as $key => $template) {

			$this->assertTrue(str_contains($template, 'data-block-key="'.$key.'"'), $key.' : le formulaire porte la clé de son entrée');
			$this->assertTrue(str_contains($template, 'name="type" type="hidden" value="'.\blockFixture\BlockTypeLib::ENTRIES.'"'), $key.' : le type posté est le type seul');
			$this->assertFalse(str_contains($template, 'data-dropdown="bottom-end"'), $key.' : la barre n\'a pas de menu de variante');

		}

		$this->assertTrue(str_contains($templateA, 'name="variant" type="hidden" value="'.\blockFixture\BlockTypeLib::A.'"'), 'la forme a poste sa variante');
		$this->assertTrue(str_contains($templateB, 'name="variant" type="hidden" value="'.\blockFixture\BlockTypeLib::B.'"'), 'la forme b poste la sienne');
		$this->assertTrue(str_contains($templateA, 'name="settings[label]"'), 'la forme a s\'écrit avec son libellé');
		$this->assertTrue(str_contains($templateB, '<span class="block-type">'.\Asset::icon('journal-text').' Forme b</span>'), 'la barre dit le nom de la forme');

		$this->assertTrue(str_contains($templateCallout, 'data-block-key="'.BlockTypeLib::CALLOUT.'"'), 'un type du moteur a pour clé son type');
		$this->assertTrue(str_contains($templateCallout, 'data-dropdown="bottom-end"'), 'et sa barre garde son menu de variante');

	}

	/**
	 * La forme b s'écrit avec la grille que le type rend depuis son `getEditable()`, dans le bloc à
	 * écrire de son entrée : ses champs sont ceux que son `castSettings()` lit.
	 */
	public function testFixedFormWritesItsGrid(): void {

		$types = [\blockFixture\BlockTypeLib::ENTRIES];

		$h = new BlockUi()->getEditor(new \Collection(), BlockLib::getChoices($types), BlockLib::getNew(self::OWNER, 123, $types), self::OWNER, 123);

		$template = $this->getTemplate($h, \blockFixture\BlockTypeLib::ENTRIES.':'.\blockFixture\BlockTypeLib::B);

		preg_match_all('/name="(settings\[rows\]\[\d+\]\[\d+\])"/', $template, $fields);

		$this->assertEquals(['settings[rows][0][0]', 'settings[rows][0][1]', 'settings[rows][1][0]', 'settings[rows][1][1]'], $fields[1], 'une cellule par case du réglage neuf, au nom que lit le type');

		$eBlock = new Block(['type' => \blockFixture\BlockTypeLib::ENTRIES, 'variant' => \blockFixture\BlockTypeLib::B]);
		$eBlock->build(['settings'], ['settings' => ['rows' => [[' Semis ', 'Date'], ['Radis', '']]]]);

		$this->assertEquals(['rows' => [['Semis', 'Date'], ['Radis', '']]], $eBlock['settings'], 'la grille postée est lue par le type');

	}

	/**
	 * La forme se choisit à la création : une variante hors de la liste du type est refusée, et une
	 * variante postée à la mise à jour est ignorée, le réglage restant lu selon la forme d'origine.
	 */
	public function testFixedFormIsWrittenOnceAtCreate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(new \structure\Structure());

		$post = fn(string $variant, array $settings) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => \blockFixture\BlockTypeLib::ENTRIES,
			'variant' => $variant,
			'settings' => $settings,
		];

		if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
			$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $post(\blockFixture\BlockTypeLib::B, ['rows' => [['A']]])), 'sans l\'application comptable, le type n\'est proposé nulle part');
			return;
		}

		$this->assertEquals([\FailAction::class, 'Block::variant.allowed'], $this->executePage('block/block:doCreate', $post('c', ['label' => 'Semis'])), 'une variante hors de la liste du type est refusée');
		$this->assertEquals([], $this->getBlocks($eLesson), 'rien n\'est créé');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post(\blockFixture\BlockTypeLib::B, ['rows' => [['Semis', 'Date']]])), 'créé sur la forme b');
		$this->assertEquals([[\blockFixture\BlockTypeLib::ENTRIES, \blockFixture\BlockTypeLib::B, ['rows' => [['Semis', 'Date']]]]], $this->getBlocks($eLesson), 'avec la grille, lue selon la forme b');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->first();

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', [
			'id' => (string)$eBlock['id'],
			'variant' => \blockFixture\BlockTypeLib::A,
			'settings' => ['rows' => [['Semis', 'Récolte']]],
			'close' => '1',
		]), 'mis à jour');
		$this->assertEquals([[\blockFixture\BlockTypeLib::ENTRIES, \blockFixture\BlockTypeLib::B, ['rows' => [['Semis', 'Récolte']]]]], $this->getBlocks($eLesson), 'la variante postée est ignorée, la grille est lue selon la forme b');

	}

	/**
	 * Le `<template>` du bloc à écrire d'une entrée de premier niveau, par sa clé.
	 */
	private function getTemplate(string $h, string $key): string {

		return explode('</template>', explode('<template data-block-new="'.$key.'">', $h)[1])[0];

	}

	/**
	 * Une leçon vide, dans une formation de l'équipe pour une structure vide, de réseau sinon.
	 */
	private function createLessonIn(\structure\Structure $eStructureCommunity): \learning\Lesson {

		$eCourse = $this->createCourse($eStructureCommunity, \learning\Course::DRAFT, \learning\Course::PRIVATE);

		return $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon à blocs', 10);

	}

	/**
	 * Les blocs de premier niveau d'une leçon, relus en base : type, forme et réglage.
	 */
	private function getBlocks(\learning\Lesson $eLesson): array {

		return array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->makeArray(fn(Block $eBlock) => [$eBlock['type'], $eBlock['variant'], $eBlock['settings']]));

	}

	/**
	 * Les refus levés par `build()` (package et nom, champ), et le réglage mis en forme.
	 */
	private function build(mixed $settings): array {

		$fw = new \FailWatch();
		$eBlock = $this->buildBlock($settings);

		return [
			array_map(fn(array $fail) => [$fail[0].'\\'.$fail[1], $fail[3]], $fw->get()),
			$eBlock['settings'] ?? NULL
		];

	}

	private function buildBlock(mixed $settings): Block {

		$eBlock = new Block(['type' => \blockFixture\BlockTypeLib::ENTRIES, 'variant' => \blockFixture\BlockTypeLib::A]);
		$eBlock->build(['settings'], ['settings' => $settings]);

		return $eBlock;

	}

}
?>
