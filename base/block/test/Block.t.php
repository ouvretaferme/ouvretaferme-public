<?php
namespace block;

require_once __DIR__.'/../../../ouvretaferme/learning/test/LearningFixture.t.php';

/**
 * Le moteur des blocs, sur son seul usage réel : les leçons d'une formation. Un usage factice
 * demanderait une table et une paire `BlockOwnerLib` qui n'existent que pour le test ; le décor de
 * `learning` écrit une vraie leçon dans la transaction de chaque méthode, annulée à la fin (les blocs
 * vivent dans la même base commune).
 *
 * Lancement : dev/test package=block file=Block
 */
class BlockTest extends \learning\LearningFixtureTest {

	const OWNER = \learning\BlockOwnerLib::LESSON;

	public function testCreate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$this->assertEquals(0, $eBlockA['position'], 'le premier bloc d\'une leçon vide prend le rang 0');

		$eBlockC = $this->addText($eLesson, 'C');
		$this->assertEquals(1, $eBlockC['position'], 'sans bloc désigné, le bloc se range en dernier');

		$eBlockB = $this->addText($eLesson, 'B', $this->reload($eBlockA));
		$this->assertEquals(1, $eBlockB['position'], 'inséré juste après le bloc désigné');

		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'les suivants descendent d\'un rang');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs contigus');

		$eBlockN = $this->addText($eLesson, 'N', eBlockBefore: $this->reload($eBlockA));
		$this->assertEquals(0, $eBlockN['position'], 'le « + » de tête range le bloc avant le premier');

		$this->assertEquals(['N', 'A', 'B', 'C'], $this->getTexts($eLesson), 'tous les blocs descendent d\'un rang');
		$this->assertEquals([0, 1, 2, 3], $this->getPositions($eLesson), 'rangs contigus');

		$eBlockM = $this->addText($eLesson, 'M', eBlockBefore: $this->reload($eBlockC));
		$this->assertEquals(3, $eBlockM['position'], 'avant un bloc du milieu, le bloc prend son rang');
		$this->assertEquals(['N', 'A', 'B', 'M', 'C'], $this->getTexts($eLesson), 'seuls les suivants descendent');
		$this->assertEquals([0, 1, 2, 3, 4], $this->getPositions($eLesson), 'rangs contigus');

	}

	public function testIncrementPosition(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$this->addText($eLesson, 'B');
		$eBlockC = $this->addText($eLesson, 'C');

		BlockLib::incrementPosition($this->reload($eBlockA), 1);
		$this->assertEquals(['B', 'A', 'C'], $this->getTexts($eLesson), 'descendu d\'un rang');

		BlockLib::incrementPosition($this->reload($eBlockA), -1);
		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'remonté d\'un rang');

		BlockLib::incrementPosition($this->reload($eBlockA), -1);
		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'le premier ne monte pas');

		BlockLib::incrementPosition($this->reload($eBlockC), 1);
		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'le dernier ne descend pas');

		BlockLib::incrementPosition($this->reload($eBlockA), 2);
		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'un incrément de deux rangs est ignoré');

		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs contigus');

	}

	public function testUpdatePositions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$eBlockB = $this->addText($eLesson, 'B');
		$eBlockC = $this->addText($eLesson, 'C');

		$fw = new \FailWatch();
		BlockLib::updatePositions(self::OWNER, $eLesson['id'], [$eBlockC['id'], $eBlockA['id'], $eBlockB['id']]);
		$this->assertEquals([], $fw->get(), 'liste complète acceptée');
		$this->assertEquals(['C', 'A', 'B'], $this->getTexts($eLesson), 'rangée comme la liste');

		$fw = new \FailWatch();
		BlockLib::updatePositions(self::OWNER, $eLesson['id'], [$eBlockA['id'], $eBlockB['id']]);
		$this->assertEquals([['Block::positionsIncomplete', NULL]], $this->getFails($fw), 'liste qui oublie un bloc refusée');
		$this->assertEquals(['C', 'A', 'B'], $this->getTexts($eLesson), 'rien n\'est écrit sur un refus');

	}

	public function testDeleteAndDuplicate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$eBlockB = $this->addText($eLesson, 'B');
		$this->addText($eLesson, 'C');

		$eBlockCopy = BlockLib::duplicate($this->reload($eBlockA));

		$this->assertEquals(['A', 'A', 'B', 'C'], $this->getTexts($eLesson), 'la copie se range juste après l\'original');
		$this->assertEquals(1, $this->reload($eBlockCopy)['position'], 'rang de la copie');

		BlockLib::delete($this->reload($eBlockB));

		$this->assertEquals(['A', 'A', 'C'], $this->getTexts($eLesson), 'bloc retiré');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs resserrés');

	}

	public function testDuplicateByOwnerAndReference(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLessonSource = $this->createLessonEmpty();
		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		$this->addText($eLessonSource, 'A');
		$this->addText($eLessonSource, 'B');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLessonSource['id'], $eLessonCopy['id']);

		$this->assertEquals(['A', 'B'], $this->getTexts($eLessonCopy), 'même ordre, même contenu');
		$this->assertEquals([0, 1], $this->getPositions($eLessonCopy), 'mêmes rangs');
		$this->assertEquals(['A', 'B'], $this->getTexts($eLessonSource), 'source intacte');

	}

	/**
	 * La borne `owner` se prouve par une ligne d'un autre usage au même `reference`, écrite sans
	 * passer par le Lib : aucun autre usage n'existe encore pour l'écrire par le produit.
	 */
	public function testDeleteByOwnerAndReference(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();
		$eLessonOther = $this->createLessonEmpty('Autre leçon');

		$this->addText($eLesson, 'A');
		$this->addText($eLesson, 'B');
		$this->addText($eLessonOther, 'Z');

		$eBlockForeign = new Block([
			'owner' => 'zztest-other',
			'reference' => $eLesson['id'],
			'type' => BlockTypeLib::TEXT,
			'position' => 0,
		]);

		Block::model()->insert($eBlockForeign);

		BlockLib::deleteByOwnerAndReference(self::OWNER, $eLesson['id']);

		$this->assertEquals([], $this->getTexts($eLesson), 'les blocs de la leçon sont retirés');
		$this->assertEquals(['Z'], $this->getTexts($eLessonOther), 'une autre leçon garde les siens');
		$this->assertEquals(1, Block::model()->whereOwner('zztest-other')->whereReference($eLesson['id'])->count(), 'un autre usage au même objet garde les siens');

	}

	public function testBuild(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		// La leçon que la page relit, construite en mémoire : le type se juge sur ce qu'elle propose.
		$eLesson = new \learning\Lesson(['course' => new \learning\Course(['community' => new \structure\Structure()])]);

		$eBlock = new Block(['owner' => self::OWNER, 'parent' => new Block(), 'eOwner' => $eLesson]);
		$fw = new \FailWatch();
		$eBlock->build(['type'], ['type' => BlockTypeLib::TEXT], new \PropertyList('create'));
		$this->assertEquals([], $fw->get(), 'un type que l\'usage déclare est accepté');

		$eBlock = new Block(['owner' => self::OWNER, 'parent' => new Block(), 'eOwner' => $eLesson]);
		$fw = new \FailWatch();
		$eBlock->build(['type'], ['type' => 'block-zztest'], new \PropertyList('create'));
		$this->assertEquals([['Block::type.allowed', 'type']], $this->getFails($fw), 'un type que l\'usage ne déclare pas est refusé');

		$eBlock = new Block(['owner' => self::OWNER, 'type' => NULL, 'parent' => new Block(), 'eOwner' => $eLesson]);
		$fw = new \FailWatch();
		$eBlock->build(['type', 'content'], ['type' => 'block-zztest', 'content' => '<p>Texte.</p>'], new \PropertyList('create'));
		$this->assertEquals([['Block::type.allowed', 'type']], $this->getFails($fw), 'un type refusé reste le seul refus quand un texte le suit');
		$this->assertTrue($eBlock->offsetExists('content') === FALSE, 'et le texte, qui n\'a pas de type où lire ses options, n\'est pas posé');

	}

	/**
	 * Dans une leçon, le bloc texte accepte une photo entre deux paragraphes, avec sa légende, et rien
	 * d'autre d'une figure : pas de vidéo. Le nombre de photos d'une figure n'est tenu que par l'écran. Le
	 * texte des autres types n'en porte aucune. Les options sont
	 * celles de l'usage pour le type du bloc (`BlockOwnerLib::getEditorOptions()`), un parent lisant
	 * celles de son propre type pour le texte de sa première étape.
	 */
	public function testBuildFigure(): void {

		$image = '<div data-type="image" data-w="100" data-h="80" data-url="https://media.exemple.org/photo.jpg" data-color="#ffffff"></div>';
		$video = '<div data-type="video" data-w="100" data-h="80" data-url="https://media.exemple.org/video" data-source="youtube"></div>';

		$build = function(string $type, string $content): ?string {

			$eBlock = new Block(['owner' => self::OWNER, 'type' => $type]);

			$fw = new \FailWatch();
			$eBlock->build(['content'], ['content' => $content]);
			$this->assertEquals([], $fw->get(), $type.', texte accepté');

			return $eBlock['content'];

		};

		$this->assertEquals(
			'<main><p>Avant la photo.</p><figure interactive="true"><image url="https://media.exemple.org/photo.jpg" color="#ffffff" width="100" height="80"/></figure><p>Après.</p></main>',
			$build(BlockTypeLib::TEXT, '<p>Avant la photo.</p><figure data-interactive="true">'.$image.'</figure><p>Après.</p>'),
			'le bloc texte garde la photo, à sa place entre les paragraphes'
		);

		$content = $build(BlockTypeLib::TEXT, '<p>Avant.</p><figure data-interactive="true">'.$image.$video.'<figcaption>Une légende</figcaption></figure>');

		$this->assertEquals(1, substr_count($content, '<image '), 'la photo reste');
		$this->assertTrue(str_contains($content, '<video') === FALSE, 'la vidéo est retirée du bloc texte : '.$content);
		$this->assertTrue(str_ends_with($content, '<figcaption>Une légende</figcaption></figure></main>'), 'la légende reste, en dernier enfant de la figure : '.$content);

		$this->assertTrue(str_contains($build(BlockTypeLib::TEXT, '<p>Avant.</p><figure data-interactive="true">'.$video.'</figure>'), '<figure') === FALSE, 'une figure sans photo ne laisse rien');

		// L'écran ne pose qu'une photo par figure (`editor.js`, collage et envoi), le moteur n'en plafonne
		// pas le nombre : un POST direct ou l'API écrivent une figure à deux photos, lue en mosaïque.
		$this->assertEquals(
			'<main><p>Avant.</p><figure interactive="true"><image url="https://media.exemple.org/photo.jpg" color="#ffffff" width="100" height="80"/><image url="https://media.exemple.org/photo.jpg" color="#ffffff" width="100" height="80"/></figure></main>',
			$build(BlockTypeLib::TEXT, '<p>Avant.</p><figure data-interactive="true">'.$image.$image.'</figure>'),
			'deux photos postées dans une même figure sont gardées toutes les deux'
		);

		$this->assertEquals('<main><p>Un texte <b>sans photo</b>.</p></main>', $build(BlockTypeLib::TEXT, '<p>Un texte <b>sans photo</b>.</p>'), 'un texte sans figure s\'écrit comme avant');

		foreach([BlockTypeLib::CALLOUT, BlockTypeLib::QUOTE, BlockTypeLib::ITEM, BlockTypeLib::STEPS, BlockTypeLib::ACCORDION, BlockTypeLib::TABS, BlockTypeLib::FIGURE] as $type) {

			$this->assertEquals(
				'<main><p>Avant la photo.</p><p>Après.</p></main>',
				$build($type, '<p>Avant la photo.</p><figure data-interactive="true">'.$image.'</figure><p>Après.</p>'),
				$type.', la photo est retirée et le texte gardé'
			);

		}

	}

	/**
	 * Le même partage par les gestes de la page, que l'écran ne garde pas : un POST direct qui met une
	 * photo dans un encadré ou une étape l'écrit sans elle, à la création comme à la modification. Le
	 * formulaire de chaque bloc porte les options de son type : seul l'éditeur d'un bloc texte reçoit
	 * les figures (`data-figure`), en photo seule.
	 */
	public function testFigurePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$figure = '<figure data-interactive="true"><div data-type="image" data-w="100" data-h="80" data-url="https://media.exemple.org/photo.jpg" data-color="#ffffff"></div></figure>';

		$post = fn(string $type, array $values = []) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => $type,
			'content' => '<p>Avant.</p>'.$figure,
		] + $values;

		$getLast = fn() => BlockLib::getWithChildren(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last()['id']);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post(BlockTypeLib::TEXT)), 'texte créé');

		$eBlockText = $getLast();

		$this->assertEquals(1, substr_count($eBlockText['content'], '<figure'), 'le bloc texte garde la photo : '.$eBlockText['content']);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post(BlockTypeLib::CALLOUT, ['variant' => BlockTypeLib::INFO, 'title' => 'Encadré'])), 'encadré créé');

		$eBlockCallout = $getLast();

		$this->assertEquals('<main><p>Avant.</p></main>', $eBlockCallout['content'], 'l\'encadré est écrit sans la photo');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post(BlockTypeLib::STEPS, ['variant' => BlockTypeLib::NUMBERS, 'title' => 'Étape'])), 'étapes créées');

		$eBlockItem = $getLast()['cBlock']->first();

		$this->assertEquals('<main><p>Avant.</p></main>', $eBlockItem['content'], 'la première étape, née avec son parent, est écrite sans la photo');

		$update = fn(Block $eBlock, array $values = []) => $this->executePage('block/block:doUpdate', [
			'id' => (string)$eBlock['id'],
			'content' => '<p>Modifié.</p>'.$figure,
		] + $values);

		$this->assertEquals([\ViewAction::class, NULL], $update($eBlockCallout, ['variant' => BlockTypeLib::INFO, 'title' => 'Encadré']), 'encadré modifié');
		$this->assertEquals('<main><p>Modifié.</p></main>', $this->reload($eBlockCallout)['content'], 'toujours sans la photo');

		$this->assertEquals([\ViewAction::class, NULL], $update($eBlockItem, ['title' => 'Étape']), 'étape modifiée');
		$this->assertEquals('<main><p>Modifié.</p></main>', $this->reload($eBlockItem)['content'], 'toujours sans la photo');

		$this->assertEquals([\ViewAction::class, NULL], $update($eBlockText), 'texte modifié');
		$this->assertEquals(1, substr_count($this->reload($eBlockText)['content'], '<figure'), 'le bloc texte garde la photo à la modification');

		$getForm = function(Block $eBlock): string {

			$eBlock = BlockLib::getWithChildren($eBlock['id']);
			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));

			return new BlockUi()->getItem($eBlock);

		};

		$this->assertEquals(1, substr_count($getForm($eBlockText), 'data-figure="1" data-figure-only-image="1" data-figure-caption="1"'), 'l\'éditeur du bloc texte reçoit les figures, en photo seule et avec leur légende');
		$this->assertEquals([1, 0], [substr_count($getForm($eBlockCallout), 'data-figure="0"'), substr_count($getForm($eBlockCallout), 'data-figure="1"')], 'celui de l\'encadré n\'en reçoit pas');
		$this->assertEquals([1, 0], [substr_count($getForm($getLast()), 'data-figure="0"'), substr_count($getForm($getLast()), 'data-figure="1"')], 'celui de l\'étape non plus');

		$figures = [];

		foreach(BlockLib::getNew(self::OWNER, $eLesson['id'], \learning\BlockOwnerLib::getTypes(self::OWNER)) as $eBlockNew) {
			if($eBlockNew['editorOptions']['acceptFigure']) {
				$figures[] = $eBlockNew['type'];
			}
		}

		$this->assertEquals([BlockTypeLib::TEXT], $figures, 'parmi les blocs à écrire du « + », seul le texte reçoit les figures');

	}

	public function testGetText(): void {

		$cBlock = new \Collection([
			new Block(['type' => BlockTypeLib::TEXT, 'content' => '<main><p>Un.</p></main>']),
			new Block(['type' => BlockTypeLib::TEXT, 'content' => '<main><p>Deux.</p></main>']),
		]);

		$this->assertEquals('<main><p>Un.</p><p>Deux.</p></main>', BlockLib::getText($cBlock), 'deux textes sous une seule enveloppe');
		$this->assertEquals(NULL, BlockLib::getText(new \Collection()), 'pas de texte sans bloc');
		$this->assertEquals(NULL, BlockLib::getText(new \Collection([new Block(['type' => BlockTypeLib::TEXT, 'content' => NULL])])), 'pas de texte sur un texte vide');

	}

	public function testReadable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->addText($eLesson, 'A');

		$eBlockEmpty = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'type' => BlockTypeLib::TEXT,
			'content' => NULL,
		]);

		BlockLib::create($eBlockEmpty);

		$this->assertEquals(2, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'l\'éditeur garde le texte vidé');
		$this->assertEquals(['A'], array_map(strip_tags(...), array_values(BlockLib::getReadable(self::OWNER, $eLesson['id'])->getColumn('content'))), 'la lecture l\'écarte');

		BlockLib::delete($this->reload($this->getFirstBlock($eLesson)));

		$this->assertTrue(BlockLib::getReadable(self::OWNER, $eLesson['id'])->empty(), 'une leçon dont le seul texte est vide se lit sans contenu');

	}

	/**
	 * La lecture de plusieurs objets en une requête rend, pour chacun, ce que `getReadable()` rend de
	 * lui seul, enfants compris, et une collection vide pour un objet qui n'a rien à montrer ou pas de
	 * bloc du tout.
	 */
	public function testReadableReferences(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLessonText = $this->createLessonEmpty('Leçon écrite');
		$this->addText($eLessonText, 'A');
		$this->addComposite($eLessonText, BlockTypeLib::ACCORDION, 'Volet');

		$eLessonBlank = $this->createLessonEmpty('Leçon aux blocs vides');

		BlockLib::create(new Block([
			'owner' => self::OWNER,
			'reference' => $eLessonBlank['id'],
			'type' => BlockTypeLib::TEXT,
			'content' => NULL,
		]));

		$eLessonBare = $this->createLessonEmpty('Leçon sans bloc');

		$references = [$eLessonText['id'], $eLessonBlank['id'], $eLessonBare['id']];

		$readable = BlockLib::getReadableReferences(self::OWNER, $references);

		$this->assertEquals($references, array_keys($readable), 'une collection par objet demandé');

		$tree = fn(\Collection $cBlock): array => $cBlock->makeArray(fn(Block $eBlock) => [$eBlock['id'], $eBlock['cBlock']->getIds()]);

		foreach($references as $reference) {
			$this->assertEquals($tree(BlockLib::getReadable(self::OWNER, $reference)), $tree($readable[$reference]), 'la même lecture que getReadable() pour l\'objet '.$reference);
		}

		$this->assertEquals([2, 0, 0], [$readable[$eLessonText['id']]->count(), $readable[$eLessonBlank['id']]->count(), $readable[$eLessonBare['id']]->count()], 'le texte et l\'accordéon se lisent, un texte vide non');
		$this->assertTrue($readable[$eLessonText['id']]->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::ACCORDION, limit: 1)['cBlock']->notEmpty(), 'l\'accordéon garde son volet');

		$this->assertEquals([], BlockLib::getReadableReferences(self::OWNER, []), 'aucun objet, aucune requête');

	}

	/**
	 * L'instant d'une étape dans la vidéo de l'objet : il s'écrit en secondes ou comme il se lit sous
	 * une vidéo, se garde quand l'éditeur enregistre l'étape sans le poster, et n'existe que sous des
	 * étapes, borné à une journée. La lecture ne le rend que si l'objet a une vidéo lisible : sans elle,
	 * il ne mènerait nulle part.
	 */
	public function testStepAt(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::STEPS, 'Gratter');
		$eBlockItem = $this->getChildren($eBlockSteps)->first();

		$build = function(Block $eBlock, mixed $settings): array {

			$eBlock = $this->reload($eBlock);
			$eBlock['parent'] = BlockLib::getParent($eBlock);

			$fw = new \FailWatch();
			$eBlock->build(['settings'], ['settings' => $settings]);

			return [$this->getFails($fw), $eBlock['settings'] ?? NULL];

		};

		$this->assertEquals([[], ['at' => 83]], $build($eBlockItem, ['at' => '1:23']), 'écrit comme sous une vidéo');
		$this->assertEquals([[], ['at' => 3725]], $build($eBlockItem, ['at' => '1:02:05']), 'avec les heures');
		$this->assertEquals([[], ['at' => 90]], $build($eBlockItem, ['at' => '90']), 'en secondes, chiffres postés');
		$this->assertEquals([[], ['at' => 0]], $build($eBlockItem, ['at' => 0]), 'le début de la vidéo');
		$this->assertEquals([[], []], $build($eBlockItem, ['at' => '']), 'un instant vide est l\'absence d\'instant');
		$this->assertEquals([[], []], $build($eBlockItem, []), 'aucun instant');
		$this->assertEquals([[], ['at' => 12]], $build($eBlockItem, ['at' => 12, 'autre' => 'x']), 'une clé étrangère n\'est pas gardée');

		foreach([['at' => '1:2'], ['at' => '1:60'], ['at' => 'une minute'], ['at' => 1.5], ['at' => ['1']]] as $settings) {
			$this->assertEquals([['Block::settings.format', 'settings']], $build($eBlockItem, $settings)[0], 'refusé : '.var_export($settings, TRUE));
		}

		$this->assertEquals([['Block::settings.at', 'settings']], $build($eBlockItem, ['at' => BlockSetting::STEP_AT_MAX + 1])[0], 'au-delà d\'une journée');
		$this->assertEquals([['Block::settings.at', 'settings']], $build($eBlockItem, ['at' => -1])[0], 'avant le début');
		$this->assertEquals([[], ['at' => BlockSetting::STEP_AT_MAX]], $build($eBlockItem, ['at' => BlockSetting::STEP_AT_MAX]), 'la borne comprise');

		$eBlockAccordion = $this->addComposite($eLesson, BlockTypeLib::ACCORDION, 'Volet');

		$this->assertEquals([['Block::settings.format', 'settings']], $build($this->getChildren($eBlockAccordion)->first(), ['at' => 30])[0], 'un volet n\'a pas d\'instant');

		$eBlockItem = $this->reload($eBlockItem);
		$eBlockItem['settings'] = ['at' => 30];
		BlockLib::update($eBlockItem, ['settings']);

		$this->assertEquals([[], ['at' => 30]], $build($eBlockItem, NULL), 'l\'éditeur enregistre l\'étape sans poster son instant, qui reste');

		$readAt = fn() => BlockLib::getReadable(self::OWNER, $eLesson['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::STEPS, limit: 1)['cBlock']->first()['settings'];

		$this->assertEquals([], $readAt(), 'sans vidéo, la lecture ne rend pas l\'instant');

		$eBlockVideo = $this->addVideoUnchecked($eLesson, 'https://www.dailymotion.com/video/x5myp5');

		$this->assertEquals([], $readAt(), 'ni avec une vidéo que plus aucun lecteur ne lit');

		$eBlockVideo['url'] = 'https://vimeo.com/76979871';
		BlockLib::update($eBlockVideo, ['url']);

		$this->assertEquals(['at' => 30], $readAt(), 'avec une vidéo lisible, l\'instant se lit');

	}

	/**
	 * Les repères de la capture d'une étape : une liste de cadres et d'anneaux en pourcentage de
	 * l'image, reconstruits clé par clé, au plus `STEP_MARKS_MAX`, chacun dans la capture. Ils se
	 * posent à côté de l'instant ou sans lui, ne valent que sous des étapes, se gardent quand l'éditeur
	 * enregistre l'étape sans les poster, et la lecture les rend même sans vidéo, qui ne retire que
	 * l'instant.
	 */
	public function testStepMarks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::STEPS, 'Gratter');
		$eBlockItem = $this->getChildren($eBlockSteps)->first();

		$build = function(Block $eBlock, mixed $settings): array {

			$eBlock = $this->reload($eBlock);
			$eBlock['parent'] = BlockLib::getParent($eBlock);

			$fw = new \FailWatch();
			$eBlock->build(['settings'], ['settings' => $settings]);

			return [$this->getFails($fw), $eBlock['settings'] ?? NULL];

		};

		$box = ['type' => 'box', 'x' => 10, 'y' => 20, 'w' => 30, 'h' => 40];
		$ring = ['type' => 'ring', 'x' => 50.5, 'y' => 60];

		$this->assertEquals([[], ['marks' => [['type' => 'box', 'x' => 10.0, 'y' => 20.0, 'w' => 30.0, 'h' => 40.0]]]], $build($eBlockItem, ['marks' => [$box]]), 'un cadre seul, sans instant');
		$this->assertEquals([[], ['at' => 83, 'marks' => [['type' => 'ring', 'x' => 50.5, 'y' => 60.0]]]], $build($eBlockItem, ['at' => '1:23', 'marks' => [$ring]]), 'un anneau à côté de l\'instant');
		$this->assertEquals([[], ['marks' => [['type' => 'ring', 'x' => 12.35, 'y' => 0.0]]]], $build($eBlockItem, ['marks' => [['y' => '0', 'type' => 'ring', 'x' => '12.345']]]), 'chiffres postés, dans le désordre, arrondis au centième');
		$this->assertEquals([[], ['marks' => [['type' => 'ring', 'x' => 100.0, 'y' => 5.0]]]], $build($eBlockItem, ['marks' => [['type' => 'ring', 'x' => '1e2', 'y' => ' 5']]]), 'tout nombre que PHP lit, notation exponentielle et espace de tête compris');
		$this->assertEquals([[], []], $build($eBlockItem, ['marks' => []]), 'une liste vide est l\'absence de repère');
		$this->assertEquals([[], ['at' => 5]], $build($eBlockItem, ['at' => 5, 'marks' => NULL]), 'pas de repère, l\'instant reste');
		$this->assertEquals([[], ['marks' => [['type' => 'box', 'x' => 0.0, 'y' => 0.0, 'w' => 100.0, 'h' => 100.0]]]], $build($eBlockItem, ['marks' => [['type' => 'box', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]]]), 'la capture entière, bornes comprises');

		foreach([
			['marks' => 'box'],
			['marks' => ['a' => $box]],
			['marks' => [$box + ['z' => 1]]],
			['marks' => [['type' => 'box', 'x' => 1, 'y' => 1, 'w' => 1]]],
			['marks' => [['type' => 'ring', 'x' => 1, 'y' => 1, 'w' => 1, 'h' => 1]]],
			['marks' => [['type' => 'arrow', 'x' => 1, 'y' => 1]]],
			['marks' => [['type' => ['ring'], 'x' => 1, 'y' => 1]]],
			['marks' => [['type' => 'ring', 'x' => 'dix', 'y' => 1]]],
			['marks' => [['type' => 'ring', 'x' => [1], 'y' => 1]]],
			['marks' => [['type' => 'ring', 'x' => TRUE, 'y' => 1]]],
			['marks' => ['ring']],
			['marks' => [['type' => 'ring', 'x' => '1e400', 'y' => 1]]],
		] as $settings) {
			$this->assertEquals([['Block::settings.format', 'settings']], $build($eBlockItem, $settings)[0], 'refusé : '.json_encode($settings));
		}

		foreach([
			['type' => 'ring', 'x' => -1, 'y' => 10],
			['type' => 'ring', 'x' => 10, 'y' => 100.01],
			['type' => 'box', 'x' => 80, 'y' => 10, 'w' => 30, 'h' => 10],
			['type' => 'box', 'x' => 10, 'y' => 95, 'w' => 10, 'h' => 6],
			['type' => 'box', 'x' => 10, 'y' => 10, 'w' => 0, 'h' => 10],
			['type' => 'box', 'x' => 10, 'y' => 10, 'w' => 10, 'h' => -5],
		] as $mark) {
			$this->assertEquals([['Block::settings.mark', 'settings']], $build($eBlockItem, ['marks' => [$mark]])[0], 'hors de la capture : '.json_encode($mark));
		}

		$this->assertEquals([], $build($eBlockItem, ['marks' => array_fill(0, BlockSetting::STEP_MARKS_MAX, $ring)])[0], 'le plafond compris');
		$this->assertEquals([['Block::settings.marks', 'settings']], $build($eBlockItem, ['marks' => array_fill(0, BlockSetting::STEP_MARKS_MAX + 1, $ring)])[0], 'au-delà du plafond');
		$this->assertEquals([['Block::settings.marks', 'settings']], $build($eBlockItem, ['marks' => array_fill(0, BlockSetting::STEP_MARKS_MAX + 1, 'rien')])[0], 'au-delà du plafond, refusé sous son nom sans que les repères soient lus');

		$eBlockAccordion = $this->addComposite($eLesson, BlockTypeLib::ACCORDION, 'Volet');

		$this->assertEquals([['Block::settings.format', 'settings']], $build($this->getChildren($eBlockAccordion)->first(), ['marks' => [$ring]])[0], 'un volet n\'a pas de repère');

		$eBlockItem = $this->reload($eBlockItem);
		$eBlockItem['settings'] = ['at' => 30, 'marks' => [['type' => 'ring', 'x' => 50.0, 'y' => 60.0]]];
		BlockLib::update($eBlockItem, ['settings']);

		$this->assertEquals([[], ['at' => 30, 'marks' => [['type' => 'ring', 'x' => 50.0, 'y' => 60.0]]]], $build($eBlockItem, NULL), 'l\'éditeur enregistre l\'étape sans poster ses repères, qui restent');

		$settings = BlockLib::getReadable(self::OWNER, $eLesson['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::STEPS, limit: 1)['cBlock']->first()['settings'];

		// La colonne json rend ses clés dans l'ordre que MySQL choisit, et non dans celui de l'écriture.
		ksort($settings['marks'][0]);

		$this->assertEquals(['marks' => [['type' => 'ring', 'x' => 50.0, 'y' => 60.0]]], $settings, 'sans vidéo, la lecture retire l\'instant et garde les repères');

	}

	/**
	 * Un objet sans bloc s'ouvre sur le choix de son premier bloc : une tuile par type de l'usage,
	 * devant une liste vide, sans « + » ni bloc ouvert d'office : le « + » d'un bloc à écrire
	 * attend dans un `<template>`, que le script pose avec lui. Le plan n'a rien à ranger. Les
	 * types viennent de l'usage et non d'un compte écrit ici : une leçon en accepte onze aujourd'hui.
	 */
	public function testStartEmpty(): void {

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);

		$h = new BlockUi()->getEditor(new \Collection(), BlockLib::getChoices($types), BlockLib::getNew(self::OWNER, 123, $types), self::OWNER, 123);

		$start = $this->getStartOf($h);

		$this->assertTrue(str_starts_with($start, '<div id="block-start">'), 'le choix est montré : '.substr($start, 0, 60));
		$this->assertEquals($types, $this->getStartTypes($start), 'une tuile par type de l\'usage, dans son ordre');
		$this->assertTrue(str_contains($h, '<div id="block-list" class="block-list"></div>'), 'la liste est vide, les tuiles sont devant elle');
		$this->assertTrue(strpos($h, 'id="block-start"') < strpos($h, 'id="block-list"'), 'et non dedans');

		$beside = explode('</template>', explode('<template data-block-beside>', $h)[1])[0];

		$this->assertEquals(new BlockUi()->getInsert(BlockLib::getChoices($types)), $beside, 'le « + » d\'un bloc à écrire attend dans la page, avec les types de l\'usage');
		$this->assertEquals(1, substr_count($h, 'class="block-insert"'), 'et nulle part ailleurs : un objet vide ne montre aucun « + »');
		$this->assertTrue(str_contains(explode('</template>', explode('<template data-block-menu>', $h)[1])[0], 'data-block-choice'), 'la liste du « + » et du « / » reste dans la page');

		$plan = new BlockUi()->getPlan(new \Collection(), self::OWNER, 123);

		$this->assertFalse(str_contains($plan, 'data-sort-url'), 'le plan n\'a rien à ranger : '.$plan);

	}

	/**
	 * Le choix reste dans la page d'un objet écrit, masqué : le retrait du dernier bloc n'a qu'une
	 * classe à lui retirer. « Annuler » y est dans la barre de chaque bloc à écrire de premier niveau,
	 * celui que pose un « + », et nulle part ailleurs : ni dans celle d'un enfant à écrire, qui prend
	 * la barre de son parent, ni dans celle d'un bloc qui existe. « Enregistrer », lui, est dans la barre
	 * de tout bloc ouvert en écriture.
	 */
	public function testStartWritten(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->addText($eLesson, 'A');
		$this->addComposite($eLesson, BlockTypeLib::STEPS, 'Gratter');

		$types = \learning\BlockOwnerLib::getTypes(self::OWNER);
		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);

		foreach($cBlock as $eBlock) {
			BlockLib::fillEditor($eBlock, $types);
		}

		$cBlockNew = BlockLib::getNew(self::OWNER, $eLesson['id'], $types);

		$h = new BlockUi()->getEditor($cBlock, BlockLib::getChoices($types), $cBlockNew, self::OWNER, $eLesson['id']);

		$start = $this->getStartOf($h);

		$this->assertTrue(str_starts_with($start, '<div id="block-start" class="hide">'), 'le choix est masqué : '.substr($start, 0, 60));
		$this->assertEquals($types, $this->getStartTypes($start), 'et rendu en entier');
		$this->assertEquals(3, substr_count(explode('<div class="block-main">', $h)[1], 'class="block-insert"'), 'la liste garde son « + » de tête et celui de chaque bloc');
		$this->assertEquals(2, $cBlock->count(), 'le plan compte les blocs');
		$this->assertTrue(str_contains($h, 'data-sort-url="/block/block:doUpdatePositions"'), 'et les range');

		$list = new BlockUi()->getList($cBlock);

		$this->assertTrue(str_contains($list, 'data-ajax="/block/block:doDelete"'), 'un bloc qui existe se supprime');
		$this->assertFalse(str_contains($list, 'data-block-discard'), 'il ne s\'annule pas');

		foreach($cBlockNew as $eBlockNew) {

			$this->assertEquals(
				($eBlockNew['parentType'] === NULL) ? 1 : 0,
				substr_count(new BlockUi()->getNew($eBlockNew), '<a data-block-discard="'),
				$eBlockNew['type'].' sous '.($eBlockNew['parentType'] ?? 'aucun parent').' : « Annuler » dans la barre d\'un bloc à écrire de premier niveau seulement'
			);

		}

		$new = new BlockUi()->getNew($cBlockNew[BlockTypeLib::TEXT]);

		preg_match('/<a data-block-discard="([^"]+)"/', $new, $match);

		$this->assertTrue(str_ends_with($match[1] ?? '', '.'), 'le bouton porte la question que le script pose quand le bloc est écrit');
		$this->assertFalse(str_contains($new, 'data-confirm'), 'et non un `data-confirm`, qui la poserait aussi sur un bloc vide');

		// « Enregistrer » : dans la barre de tout bloc ouvert en écriture, à écrire ou rouvert, enfant
		// compris. Un parent n'a pas de formulaire, sa barre n'en porte pas.
		foreach($cBlockNew as $eBlockNew) {
			$this->assertEquals(1, substr_count(new BlockUi()->getNew($eBlockNew), 'data-block-save'), $eBlockNew['type'].' sous '.($eBlockNew['parentType'] ?? 'aucun parent').' : « Enregistrer » dans la barre du bloc à écrire');
		}

		$this->assertEquals(2, substr_count($list, 'data-block-save'), 'un par bloc rouvert, le texte et l\'étape, et aucun dans la barre du parent, qui n\'a pas de formulaire');

	}

	/**
	 * Les deux chemins rendent le même écran : la page décide de la classe du choix au rendu
	 * (`testStartEmpty()`, `testStartWritten()`), et la réponse d'un geste la bascule quand l'objet
	 * passe de vide à écrit ou l'inverse, sans toucher au choix le reste du temps.
	 */
	public function testStartInstructions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::TEXT,
			'content' => '<p>A</p>',
		];

		$instructions = $this->getInstructions('doCreate', $post);
		$eBlockA = $this->getFirstBlock($eLesson);

		$this->assertEquals([['hide']], $instructions['#block-start'] ?? NULL, 'le premier bloc enregistré masque le choix');
		$this->assertTrue(str_contains($instructions['#block-list'][0][1][0] ?? '', 'id="block-'.$eBlockA['id'].'"'), 'et rend la liste entière');
		$this->assertTrue(str_contains($instructions['#block-plan'][0][1][0] ?? '', 'data-sort-url'), 'avec le plan');

		$instructions = $this->getInstructions('doCreate', ['after' => (string)$eBlockA['id'], 'content' => '<p>B</p>'] + $post);

		$this->assertFalse(array_key_exists('#block-start', $instructions), 'un bloc ajouté sous un autre ne touche pas au choix');
		$this->assertTrue(array_key_exists('#block-new', $instructions), 'il remplace le bloc à écrire');

		$instructions = $this->getInstructions('doDelete', ['id' => (string)$eBlockA['id']]);

		$this->assertFalse(array_key_exists('#block-start', $instructions), 'un bloc retiré parmi d\'autres ne touche pas au choix');

		$instructions = $this->getInstructions('doDelete', ['id' => (string)$this->getFirstBlock($eLesson)['id']]);

		$this->assertEquals([['removeHide']], $instructions['#block-start'] ?? NULL, 'le dernier bloc retiré montre de nouveau le choix');
		$this->assertEquals([['innerHtml', ['']]], $instructions['#block-list'] ?? NULL, 'devant une liste vidée, « + » de tête compris');
		$this->assertTrue(array_key_exists('#block-plan', $instructions) and str_contains($instructions['#block-plan'][0][1][0] ?? '', 'data-sort-url') === FALSE, 'et le plan n\'a plus rien à ranger');

	}

	/**
	 * Chaque tuile dit ce que son type contient. La ligne des chiffres clés écrit en toutes lettres le
	 * plafond de `BlockTypeLib::getChildrenMax()` : changer l'un sans l'autre échoue ici.
	 */
	public function testStartDescriptions(): void {

		$descriptions = [];

		foreach(\learning\BlockOwnerLib::getTypes(self::OWNER) as $type) {
			$descriptions[$type] = new BlockTypeUi()->getDescription($type);
		}

		$this->assertEquals([], array_keys(array_filter($descriptions, fn(string $description) => $description === '')), 'chaque type a sa ligne');
		$this->assertEquals(count($descriptions), count(array_unique($descriptions)), 'et aucune ne sert deux types');

		$this->assertEquals(4, BlockTypeLib::getChildrenMax(BlockTypeLib::FIGURE), 'le plafond des chiffres clés a changé : la ligne de sa tuile, `BlockTypeUi::getDescription()`, dit « quatre » et se réécrit avec lui');
		$this->assertTrue(str_contains($descriptions[BlockTypeLib::FIGURE], 'quatre'), 'la ligne des chiffres clés ne dit plus son plafond : '.$descriptions[BlockTypeLib::FIGURE]);

	}

	/**
	 * Le bloc à écrire du « + » : un par type de l'usage et un enfant par parent, sans brouillon,
	 * puisque rien ne le rend de nouveau au rechargement. Son éditeur a un id fixe, que cite le script
	 * de son template.
	 */
	public function testGetNew(): void {

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$this->assertEquals(array_merge(\learning\BlockOwnerLib::getTypes(self::OWNER), [BlockTypeLib::ITEM, BlockTypeLib::ANSWER], array_fill(0, 3, BlockTypeLib::ITEM)), array_values($cBlockNew->getColumn('type')), 'un bloc à écrire par type de l\'usage, et un enfant par parent');

		$h = new BlockUi()->getNew($cBlockNew[BlockTypeLib::TEXT]);

		$this->assertTrue(str_contains($h, '<template data-block-new="'.BlockTypeLib::TEXT.'">'), 'dans un template, que le « + » clone');
		$this->assertTrue(str_contains($h, 'data-draft') === FALSE, 'sans brouillon');
		$this->assertTrue(str_contains($h, 'id="editor-block-new-'.BlockTypeLib::TEXT.'"'), 'l\'éditeur porte un id fixe');
		$this->assertTrue(str_contains($h, 'name="type" type="hidden" value="'.BlockTypeLib::TEXT.'"'), 'le type est posté');
		$this->assertTrue(str_contains($h, 'name="draft" type="hidden" value="1"'), 'il se crée comme un bloc à écrire');

	}

	/**
	 * `doCreate` comme le bloc à écrire du « + » le poste : après le bloc désigné, ou avant le premier
	 * pour le « + » de tête, avec ce qui y est écrit. Jamais des deux côtés à la fois. Un type que
	 * l'usage ne déclare pas est refusé par `build()`, sans lever, et un type absent aussi.
	 */
	public function testCreatePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$this->addText($eLesson, 'C');

		$post = fn(string $type) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'after' => (string)$eBlockA['id'],
			'type' => $type,
			'content' => '<p>B</p>',
		];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post(BlockTypeLib::TEXT)), 'créé');
		$this->assertEquals(['A', 'B', 'C'], $this->getTexts($eLesson), 'après le bloc désigné, avec son texte');

		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doCreate', $post('block-zztest')), 'un type hors de l\'usage est refusé');
		$this->assertEquals(3, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est créé');

		$post = $post(BlockTypeLib::TEXT);

		$postHead = $post;
		unset($postHead['after']);
		$postHead['before'] = (string)$eBlockA['id'];
		$postHead['content'] = '<p>N</p>';

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $postHead), 'créé par le « + » de tête');
		$this->assertEquals(['N', 'A', 'B', 'C'], $this->getTexts($eLesson), 'avant le premier bloc');
		$this->assertEquals([0, 1, 2, 3], $this->getPositions($eLesson), 'rangs contigus');

		$postBoth = $postHead;
		$postBoth['after'] = (string)$eBlockA['id'];

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('block/block:doCreate', $postBoth), 'une place ne se désigne que d\'un côté');
		$this->assertEquals(4, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est créé');

		$eLessonOther = $this->createLessonEmpty('Autre leçon');
		$eBlockOther = $this->addText($eLessonOther, 'Z');

		$postOther = $postHead;
		$postOther['before'] = (string)$eBlockOther['id'];

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('block/block:doCreate', $postOther), 'un bloc d\'une autre leçon ne désigne pas de place');
		$this->assertEquals(4, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est créé dans la leçon');
		$this->assertEquals(['Z'], $this->getTexts($eLessonOther), 'ni dans l\'autre');

		$postLast = $post;
		unset($postLast['after']);
		$postLast['content'] = '<p>Z</p>';

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $postLast), 'créé sans place, comme le premier bloc d\'un objet');
		$this->assertEquals(['N', 'A', 'B', 'C', 'Z'], $this->getTexts($eLesson), 'sans place, en dernier');

		$postBare = $postLast;
		unset($postBare['type']);

		$this->assertEquals([\FailAction::class, 'Block::type.check'], $this->executePage('block/block:doCreate', $postBare), 'sans place ni type, aucun type ne se devine : `build()` refuse');
		$this->assertEquals(5, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est créé');

		unset($post['draft']);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('block/block:doCreate', $post), 'un bloc ne naît que d\'un bloc à écrire');

	}

	/**
	 * La liste de l'éditeur rend tous les blocs lus, chacun avec son formulaire dans un `<template>`
	 * que le script sort au clic : l'ouvrir ne demande rien au serveur. L'éditeur du formulaire porte un
	 * id fixe, celui de son brouillon, et le script qui l'arme reste dans le template, où rien ne court.
	 * Les « + » sont hors des templates : un en tête, un dans l'enveloppe de chaque bloc.
	 */
	public function testGetList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$eBlockB = $this->addText($eLesson, 'B');

		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);

		foreach($cBlock as $eBlock) {
			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));
		}

		$h = new BlockUi()->getList($cBlock);

		$this->assertTrue(str_contains($h, 'data-block="'.$eBlockA['id'].'"') and str_contains($h, 'data-block="'.$eBlockB['id'].'"'), 'les deux blocs');
		$this->assertEquals(2, substr_count($h, '<template>'), 'chacun avec son formulaire');

		$outside = preg_replace('/<template>.*?<\/template>/s', '', $h);

		$this->assertEquals(0, substr_count($outside, 'block-item-selected'), 'aucun ouvert');
		$this->assertEquals(0, substr_count($outside, 'Editor.startInstance'), 'aucun éditeur armé');
		$this->assertTrue(str_contains($h, 'id="editor-block-'.$eBlockA['id'].'"'), 'l\'éditeur du premier porte son id');
		$this->assertTrue(str_contains($h, 'data-draft="block-'.$eBlockB['id'].'"'), 'le second range son brouillon sous le sien');
		$this->assertEquals(2, substr_count($h, 'data-block-move="-1"'), 'les flèches sont toutes rendues, le premier et le dernier bloc les masquent en CSS');

		$this->assertEquals(3, substr_count($outside, 'data-block-insert'), 'un « + » en tête et un sous chaque bloc, fermé ou non');
		$this->assertEquals(3, substr_count($h, 'data-block-insert'), 'aucun dans un template : ouvrir un bloc ne le retire pas');
		$this->assertTrue(str_starts_with($h, '<div class="block-insert">'), 'celui de tête précède le premier bloc');
		$this->assertEquals(1, preg_match('/<\/template><div class="block-insert">.*?<\/div><\/div>$/s', $h), 'celui du dernier bloc est dans son enveloppe, après le template');

	}

	public function testCanWrite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->actAs($this->eUserAdmin);
		$this->assertTrue($this->getBlockOf($eLesson)->canWrite(), 'l\'administrateur de la formation écrit ses blocs');

		$this->actAs($this->eUserReader);
		$this->assertTrue($this->getBlockOf($eLesson)->canWrite() === FALSE, 'un lecteur sans droit sur la formation ne les écrit pas');

		$eLessonFull = \learning\LessonLib::getById($eLesson['id']);

		$this->actAs($this->eUserAdmin);
		$this->assertTrue(\learning\BlockOwnerLib::canWrite($eLessonFull), 'formations ouvertes, l\'administrateur écrit');
		$this->assertTrue(\learning\BlockOwnerLib::canRead($eLessonFull), 'formations ouvertes, il lit');

		$eBlockGone = new Block(['owner' => self::OWNER, 'eOwner' => new \learning\Lesson()]);

		$this->actAs($this->eUserAdmin);
		$this->assertTrue($eBlockGone->canWrite() === FALSE, 'une leçon disparue ne s\'écrit pas');

	}

	/**
	 * Les deux routes qui font naître et disparaître un bloc refusent un lecteur sans droit d'écriture,
	 * et un objet qui ne s'écrit pas (`acceptWrite()` : une ferme ne porte pas de formation). Rien
	 * n'est créé ni supprimé. Une leçon ferme son droit avec son état (`BlockOwnerLib::canWrite()` lit
	 * `acceptLearning()`), le refus d'une ferme est donc celui du droit. Un type posté en tableau n'est
	 * pas un type.
	 */
	public function testWritePagesRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();
		$eBlock = $this->addText($eLesson, 'A');

		$post = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::TEXT,
			'content' => '<p>B</p>',
		];

		$this->assertEquals([\FailAction::class, 'Block::type.check'], $this->executePage('block/block:doCreate', ['type' => [BlockTypeLib::TEXT]] + $post), 'un type posté en tableau est refusé');
		$this->assertEquals(['A'], $this->getTexts($eLesson), 'rien n\'est créé');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doCreate', $post), 'un lecteur ne crée pas de bloc');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doDelete', ['id' => (string)$eBlock['id']]), 'ni n\'en supprime');
		$this->assertEquals(['A'], $this->getTexts($eLesson), 'rien n\'est créé ni supprimé');

		$eStructureFarm = $this->getFarm();

		if($eStructureFarm->empty()) {
			$this->assertFail('aucune ferme active dans cette base');
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLessonFarm = $this->createLesson($this->createChapter($this->createCourse($eStructureFarm, \learning\Course::DRAFT, \learning\Course::PRIVATE), 'Module'), 'Leçon d\'une ferme', 10);
		$eBlockFarm = $this->addText($eLessonFarm, 'A');

		$this->assertFalse($this->getBlockOf($eLessonFarm)->acceptWrite(), 'la leçon d\'une ferme ne s\'écrit pas');

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doCreate', ['reference' => (string)$eLessonFarm['id']] + $post), 'un objet qui ne s\'écrit pas ne reçoit pas de bloc');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doDelete', ['id' => (string)$eBlockFarm['id']]), 'ni n\'en perd');
		$this->assertEquals(['A'], $this->getTexts($eLessonFarm), 'rien n\'est créé ni supprimé');

	}

	/**
	 * Les dates sont reculées d'une heure sans passer par le Lib : c'est le seul moyen de voir
	 * avancer un `NOW()` dans la seconde du test.
	 */
	public function testTouch(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		\learning\Lesson::model()->whereId($eLesson['id'])->update(['updatedAt' => new \Sql('NOW() - INTERVAL 1 HOUR')]);
		\learning\Course::model()->whereId($eLesson['course']['id'])->update(['updatedAt' => new \Sql('NOW() - INTERVAL 1 HOUR')]);

		$this->addText($eLesson, 'A');

		$eLessonAfter = \learning\Lesson::model()->select('updatedAt')->whereId($eLesson['id'])->get();
		$eCourseAfter = \learning\Course::model()->select('updatedAt')->whereId($eLesson['course']['id'])->get();

		$this->assertTrue(strtotime($eLessonAfter['updatedAt']) > time() - 600, 'la leçon est datée de l\'écriture de son bloc : '.$eLessonAfter['updatedAt']);
		$this->assertTrue(strtotime($eCourseAfter['updatedAt']) > time() - 600, 'sa formation aussi : '.$eCourseAfter['updatedAt']);

	}

	/**
	 * Les rangs se comptent par fratrie (`owner`, `reference`, `parent`) : les étapes d'un bloc ont
	 * leurs propres rangs à partir de 0, et un geste sur un bloc de premier niveau ne rencontre jamais
	 * l'étape d'un voisin.
	 */
	public function testSiblingPositions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::STEPS, 'S1');
		$eBlockB = $this->addText($eLesson, 'B');

		$this->assertEquals(['A', 'block-steps[S1]', 'B'], $this->getTree($eLesson), 'le bloc composé naît avec sa première étape');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs de premier niveau contigus');

		$eBlockS1 = $this->getChildren($eBlockSteps)->first();
		$this->assertEquals(0, $eBlockS1['position'], 'la première étape prend le rang 0 dans sa fratrie');

		$eBlockS2 = $this->addChild($eBlockSteps, 'S2');
		$this->assertEquals(1, $eBlockS2['position'], 'sans bloc désigné, une étape se range en dernier de sa fratrie');

		$this->addChild($eBlockSteps, 'S3', $this->reload($eBlockS1));

		$this->assertEquals(['A', 'block-steps[S1,S3,S2]', 'B'], $this->getTree($eLesson), 'inséré après l\'étape désignée');
		$this->assertEquals([0, 1, 2], $this->getChildPositions($eBlockSteps), 'rangs des étapes contigus');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'les blocs de premier niveau n\'ont pas bougé');

		BlockLib::incrementPosition($this->reload($eBlockS1), -1);
		$this->assertEquals(['A', 'block-steps[S1,S3,S2]', 'B'], $this->getTree($eLesson), 'la première étape ne monte pas hors de sa fratrie');

		BlockLib::incrementPosition($this->reload($eBlockB), -1);
		$this->assertEquals(['A', 'B', 'block-steps[S1,S3,S2]'], $this->getTree($eLesson), 'un bloc de premier niveau s\'échange avec son voisin, pas avec une étape');

		BlockLib::incrementPosition($this->reload($eBlockS2), -1);
		$this->assertEquals(['A', 'B', 'block-steps[S1,S2,S3]'], $this->getTree($eLesson), 'une étape s\'échange avec sa voisine');

		BlockLib::delete($this->reload($eBlockS1));
		$this->assertEquals(['A', 'B', 'block-steps[S2,S3]'], $this->getTree($eLesson), 'étape retirée');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockSteps), 'rangs des étapes resserrés');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'le premier niveau n\'est pas resserré par une étape');

		BlockLib::delete($this->reload($eBlockA));
		$this->assertEquals([0, 1], $this->getPositions($eLesson), 'premier niveau resserré');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockSteps), 'les étapes gardent leurs rangs');

	}

	/**
	 * Le plan ne range que le premier niveau : la liste complète est celle des blocs de premier
	 * niveau, et une étape n'y figure pas.
	 */
	public function testUpdatePositionsWithChildren(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'A');
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::STEPS, 'S1');
		$eBlockS2 = $this->addChild($eBlockSteps, 'S2');

		$fw = new \FailWatch();
		BlockLib::updatePositions(self::OWNER, $eLesson['id'], [$eBlockSteps['id'], $eBlockA['id']]);
		$this->assertEquals([], $fw->get(), 'la liste du premier niveau suffit, les étapes présentes');
		$this->assertEquals(['block-steps[S1,S2]', 'A'], $this->getTree($eLesson), 'rangée comme la liste');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockSteps), 'les étapes gardent leurs rangs');

		$fw = new \FailWatch();
		BlockLib::updatePositions(self::OWNER, $eLesson['id'], [$eBlockA['id'], $eBlockSteps['id'], $eBlockS2['id']]);
		$this->assertEquals([['Block::positionsIncomplete', NULL]], $this->getFails($fw), 'une étape dans la liste du plan est refusée');
		$this->assertEquals(['block-steps[S1,S2]', 'A'], $this->getTree($eLesson), 'rien n\'est écrit sur un refus');

	}

	public function testDuplicateAndDeleteParent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->addText($eLesson, 'A');
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::ACCORDION, 'S1');
		$this->addChild($eBlockSteps, 'S2');
		$this->addText($eLesson, 'B');

		$eBlockCopy = BlockLib::duplicate($this->reload($eBlockSteps));

		$this->assertEquals(['A', 'block-accordion[S1,S2]', 'block-accordion[S1,S2]', 'B'], $this->getTree($eLesson), 'la copie d\'un parent emporte ses enfants, juste après l\'original');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockCopy), 'rangs des enfants copiés');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockSteps), 'l\'original garde les siens');
		$this->assertEquals([0, 1, 2, 3], $this->getPositions($eLesson), 'rangs contigus');

		BlockLib::delete($this->reload($eBlockSteps));

		$this->assertEquals(['A', 'block-accordion[S1,S2]', 'B'], $this->getTree($eLesson), 'la suppression d\'un parent emporte ses enfants');
		$this->assertEquals(0, Block::model()->whereParent($eBlockSteps)->count(), 'aucun enfant orphelin');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs resserrés');

		$eBlockS1 = $this->getChildren($eBlockCopy)->first();
		BlockLib::delete($this->reload($eBlockS1));
		$this->assertEquals(['A', 'block-accordion[S2]', 'B'], $this->getTree($eLesson), 'un enfant retiré parmi d\'autres laisse le parent');

		BlockLib::delete($this->reload($this->getChildren($eBlockCopy)->first()));
		$this->assertEquals(['A', 'B'], $this->getTree($eLesson), 'le dernier enfant retiré emporte son parent, qui n\'aurait plus rien à montrer');
		$this->assertEquals([0, 1], $this->getPositions($eLesson), 'rangs resserrés');

	}

	public function testDuplicateByOwnerAndReferenceWithChildren(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLessonSource = $this->createLessonEmpty();
		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		$this->addText($eLessonSource, 'A');
		$eBlockTabs = $this->addComposite($eLessonSource, BlockTypeLib::TABS, 'T1');
		$this->addChild($eBlockTabs, 'T2');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLessonSource['id'], $eLessonCopy['id']);

		$this->assertEquals(['A', 'block-tabs[T1,T2]'], $this->getTree($eLessonCopy), 'même arbre');

		$eBlockTabsCopy = BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id'])->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::TABS)->first();

		$this->assertDiffer($eBlockTabs['id'], $eBlockTabsCopy['id'], 'le parent copié a son propre id');
		$this->assertEquals([0, 1], $this->getChildPositions($eBlockTabsCopy), 'les enfants copiés suivent le parent copié, aux mêmes rangs');
		$this->assertEquals(['A', 'block-tabs[T1,T2]'], $this->getTree($eLessonSource), 'source intacte');

	}

	/**
	 * Le parent et sa première étape s'écrivent dans la même transaction : une seconde écriture en
	 * échec ne laisse aucune ligne.
	 *
	 * La transaction du décor est d'abord annulée : le cadriciel compte les transactions imbriquées
	 * sans point de sauvegarde, et un retour en arrière n'a d'effet mesurable qu'au premier niveau. Le
	 * titre trop long de l'étape fait échouer la seconde écriture en mode strict : `build()` l'aurait
	 * refusé avant, ce qui n'éprouverait pas la transaction. La référence est factice et aucune leçon
	 * n'est datée, l'échec survenant avant `touch()`.
	 */
	public function testCompositeCreateIsAtomic(): void {

		$reference = 2147480000;

		\learning\Course::model()->rollBack();

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $reference,
			'parent' => new Block(),
			'type' => BlockTypeLib::STEPS,
			'title' => str_repeat('x', 300),
			'content' => '<main><p>Étape</p></main>',
		]);

		try {
			BlockLib::create($eBlock);
			$this->assertFail('l\'écriture de l\'étape aurait dû échouer');
		} catch(\Exception) {
		}

		Block::model()->rollBack();

		$this->assertEquals(0, Block::model()->whereOwner(self::OWNER)->whereReference($reference)->count(), 'ni parent ni étape ne restent');

		\learning\Course::model()->beginTransaction();

	}

	/**
	 * Un bloc chiffres clés porte quatre chiffres au plus, vérifié côté serveur par la page, qui pose le
	 * parent avant la validation (`Block::type.full`). La copie d'un chiffre passe par la même garde.
	 * Retirer un chiffre d'un parent plein rend le parent entier dans l'éditeur, où la copie et le « + »
	 * d'un chiffre reparaissent.
	 */
	public function testFigureChildrenMax(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::FIGURE,
			'title' => '80 %',
			'content' => '<p>de levée</p>',
		];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post), 'bloc chiffres clés créé avec son premier chiffre');

		$eBlockFigure = $this->getFirstBlock($eLesson);

		$this->assertEquals(BlockTypeLib::FIGURE, $eBlockFigure['type'], 'le parent est un bloc chiffres clés');
		$this->assertEquals(NULL, $eBlockFigure['title'], 'le parent ne garde pas la valeur');
		$this->assertEquals(['80 %'], array_values($eBlockFigure['cBlock']->getColumn('title')), 'le premier chiffre porte la valeur écrite');

		foreach(['12 jours', '3 feuilles', '2 cm'] as $title) {

			$postChild = [
				'owner' => self::OWNER,
				'reference' => (string)$eLesson['id'],
				'draft' => '1',
				'after' => (string)$this->getChildren($eBlockFigure)->last()['id'],
				'type' => BlockTypeLib::ITEM,
				'title' => $title,
				'content' => '<p>légende</p>',
			];

			$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $postChild), 'chiffre '.$title.' ajouté');

		}

		$this->assertEquals(4, $this->getChildren($eBlockFigure)->count(), 'quatre chiffres');
		$this->assertEquals([0, 1, 2, 3], $this->getChildPositions($eBlockFigure), 'rangs contigus');

		$postChild['after'] = (string)$this->getChildren($eBlockFigure)->last()['id'];
		$postChild['title'] = '5 fois';

		$this->assertEquals([\FailAction::class, 'Block::type.full'], $this->executePage('block/block:doCreate', $postChild), 'un cinquième chiffre est refusé');
		$this->assertEquals(4, $this->getChildren($eBlockFigure)->count(), 'rien n\'est écrit sur un refus');

		$eBlockChild = $this->getChildren($eBlockFigure)->first();

		$this->assertEquals([\FailAction::class, 'Block::type.full'], $this->executePage('block/block:doDuplicate', ['id' => (string)$eBlockChild['id']]), 'la copie d\'un chiffre d\'un parent plein est refusée');
		$this->assertEquals(4, $this->getChildren($eBlockFigure)->count(), 'la copie n\'a rien écrit');

		$h = $this->getEditorItem($eLesson);

		$this->assertEquals(1, substr_count($h, 'data-block-duplicate'), 'plein, aucun chiffre ne propose sa copie, seul le parent propose la sienne');
		$this->assertTrue(str_contains($h, 'data-block-child="'.BlockTypeLib::FIGURE.'"') === FALSE, 'plein, le « + » d\'un chiffre disparaît');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doDelete', ['id' => (string)$eBlockChild['id']]), 'un chiffre retiré du parent plein');
		$this->assertEquals(3, $this->getChildren($eBlockFigure)->count(), 'trois chiffres');

		$h = $this->getEditorItem($eLesson);

		$this->assertEquals(4, substr_count($h, 'data-block-duplicate'), 'le parent et ses trois chiffres proposent de nouveau leur copie');
		$this->assertTrue(str_contains($h, 'data-block-child="'.BlockTypeLib::FIGURE.'"'), 'le « + » d\'un chiffre reparaît');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doDuplicate', ['id' => (string)$this->getChildren($eBlockFigure)->first()['id']]), 'à trois, un chiffre se copie');
		$this->assertEquals(4, $this->getChildren($eBlockFigure)->count(), 'de nouveau quatre');

	}

	/**
	 * `isFull()` compte la fratrie depuis une étape de `build()`, pendant qu'une requête peut être en
	 * cours d'écriture sur le modèle singleton : elle doit la laisser intacte.
	 */
	public function testIsFullLeavesSingleton(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockFigure = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'type' => BlockTypeLib::FIGURE, 'title' => '80 %', 'content' => '<main><p>de levée</p></main>']);
		BlockLib::create($eBlockFigure);

		$eBlockText = $this->addText($eLesson, 'Témoin');

		// Relu avant que la requête ne se compose : la relecture passe elle-même par le singleton.
		$eBlockFigure = $this->reload($eBlockFigure);

		$mBlock = Block::model()
			->select(['id'])
			->whereId($eBlockText['id']);

		$this->assertTrue(BlockLib::isFull($eBlockFigure) === FALSE, 'un chiffre sur quatre');

		$this->assertEquals([$eBlockText['id']], $mBlock->getCollection()->getIds(), 'la requête posée sur le singleton avant le compte garde sa condition');

	}

	/**
	 * Un onglet sans nom est refusé par un message qui parle de son nom, rattaché au champ « Nom de
	 * l'onglet » : le nom du refus (`title`) est celui du `data-wrapper` qui porte le champ dans le
	 * formulaire, pour l'onglet d'un bloc d'onglets qui naît comme pour un onglet existant. Une étape
	 * garde le refus du titre.
	 */
	public function testTabNameRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::TABS,
			'title' => '',
			'content' => '<p>Semer sous abri.</p>',
		];

		$this->assertEquals([\FailAction::class, 'Block::title.tabName'], $this->executePage('block/block:doCreate', $post), 'un bloc d\'onglets dont le premier onglet n\'a pas de nom est refusé');

		$post['title'] = 'Printemps';

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post), 'bloc d\'onglets créé');

		$eBlockTab = $this->getChildren($this->getFirstBlock($eLesson))->first();

		$this->assertEquals([\FailAction::class, 'Block::title.tabName'], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockTab['id'], 'title' => '', 'content' => '<p>Semer sous abri.</p>', 'close' => '1']), 'un onglet vidé de son nom est refusé');
		$this->assertEquals('Printemps', $this->reload($eBlockTab)['title'], 'le nom reste');

		$this->assertTrue(AlertUi::getError('Block::title.tabName') !== NULL, 'le refus a son message');

		$h = $this->getEditorItem($eLesson);

		$this->assertTrue(str_contains($h, '<div data-block-head data-wrapper="title">'), 'le champ d\'un onglet existant attend dans le conteneur que nomme le refus');

		$eBlockTabsNew = BlockLib::getNew(self::OWNER, $eLesson['id'], \learning\BlockOwnerLib::getTypes(self::OWNER))->find(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::TABS)->first();

		$this->assertEquals(1, substr_count(new BlockUi()->getNew($eBlockTabsNew), 'data-wrapper="title"'), 'le premier onglet d\'un bloc qui naît a le même conteneur');

	}

	/**
	 * Le bloc de premier niveau d'une leçon, tel que l'éditeur le rend.
	 */
	private function getEditorItem(\learning\Lesson $eLesson): string {

		$eBlock = $this->getFirstBlock($eLesson);
		BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));

		return new BlockUi()->getItem($eBlock);

	}

	/**
	 * La grille d'un tableau (`Block::settings`) : remise en forme avant d'être jugée, puis bornée par
	 * `BlockSetting`. Chaque refus nomme sa clé, que l'éditeur affiche sous la grille.
	 */
	public function testBuildTable(): void {

		$build = function(mixed $settings): Block {

			$eBlock = new Block(['owner' => self::OWNER, 'type' => BlockTypeLib::TABLE]);
			$eBlock->build(['settings'], ['settings' => $settings]);

			return $eBlock;

		};

		$fails = function(mixed $settings) use($build): array {

			$fw = new \FailWatch();
			$build($settings);

			return $this->getFails($fw);

		};

		$fw = new \FailWatch();
		$eBlock = $build(['rows' => [['A', 'B', 'C'], ['1'], [2 => 'z', 0 => "x\ny", 1 => "\tw "]], 'other' => 'retiré']);
		$this->assertEquals([], $fw->get(), 'une grille postée est acceptée');
		$this->assertEquals(['rows' => [['A', 'B', 'C'], ['1', '', ''], ['x y', 'w', 'z']]], $eBlock['settings'], 'lignes complétées, cellules remises dans l\'ordre, sauts de ligne remplacés, clés étrangères retirées');

		$this->assertEquals([['Block::settings.format', 'settings']], $fails('texte'), 'une grille qui n\'est pas une liste est refusée');
		$this->assertEquals([['Block::settings.format', 'settings']], $fails(['rows' => 'texte']), 'des lignes qui ne sont pas une liste sont refusées');
		$this->assertEquals([['Block::settings.format', 'settings']], $fails(['rows' => [['A', ['B']]]]), 'une cellule qui n\'est pas du texte refuse tout le tableau');
		$this->assertEquals([['Block::settings.rows', 'settings']], $fails(['rows' => []]), 'un tableau sans ligne est refusé');
		$this->assertEquals([['Block::settings.columns', 'settings']], $fails(['rows' => [[]]]), 'un tableau sans colonne est refusé');

		$this->assertEquals([], $fails(['rows' => array_fill(0, BlockSetting::TABLE_ROWS_MAX, ['A'])]), 'autant de lignes que la borne');
		$this->assertEquals([['Block::settings.rows', 'settings']], $fails(['rows' => array_fill(0, BlockSetting::TABLE_ROWS_MAX + 1, ['A'])]), 'une ligne de plus que la borne est refusée');
		$this->assertEquals([], $fails(['rows' => [array_fill(0, BlockSetting::TABLE_COLUMNS_MAX, 'A')]]), 'autant de colonnes que la borne');
		$this->assertEquals([['Block::settings.columns', 'settings']], $fails(['rows' => [array_fill(0, BlockSetting::TABLE_COLUMNS_MAX + 1, 'A')]]), 'une colonne de plus que la borne est refusée');
		$this->assertEquals([], $fails(['rows' => [[str_repeat('é', BlockSetting::TABLE_CELL_LIMIT)]]]), 'une cellule à la borne, comptée en caractères');
		$this->assertEquals([['Block::settings.cell', 'settings']], $fails(['rows' => [[str_repeat('é', BlockSetting::TABLE_CELL_LIMIT + 1)]]]), 'une cellule trop longue est refusée');

		foreach(['Block::settings.format', 'Block::settings.rows', 'Block::settings.columns', 'Block::settings.cell'] as $fqn) {
			$this->assertTrue(AlertUi::getError($fqn) !== NULL, $fqn.' a son message');
		}

		$this->assertTrue(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::TABLE, 'settings' => BlockTypeLib::getDefaultSettings(BlockTypeLib::TABLE)])), 'un tableau neuf n\'a rien à montrer');
		$this->assertFalse(BlockTypeLib::isEmpty(new Block(['type' => BlockTypeLib::TABLE, 'settings' => ['rows' => [['', ''], ['', 'x']]]])), 'une seule cellule écrite suffit');

	}

	/**
	 * Le tableau par les gestes de la page : il naît avec la grille que l'éditeur poste dès le premier
	 * enregistrement (`getPropertiesCreate()` d'un type sans enfant), un collage au-delà d'une borne est
	 * refusé sans rien écrire, et la copie emporte la grille.
	 */
	public function testTablePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = fn(array $rows) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::TABLE,
			'settings' => ['rows' => $rows],
		];

		$this->assertEquals([\FailAction::class, 'Block::settings.rows'], $this->executePage('block/block:doCreate', $post(array_fill(0, BlockSetting::TABLE_ROWS_MAX + 1, ['A']))), 'un collage de 101 lignes est refusé');
		$this->assertEquals([\FailAction::class, 'Block::settings.columns'], $this->executePage('block/block:doCreate', $post([array_fill(0, BlockSetting::TABLE_COLUMNS_MAX + 1, 'A')])), 'un collage de 13 colonnes est refusé');
		$this->assertEquals(0, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post([['Culture', 'Levée'], ['Laitue', '<script>alert(1)</script>']])), 'tableau créé avec sa grille');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		$this->assertEquals(['rows' => [['Culture', 'Levée'], ['Laitue', '<script>alert(1)</script>']]], $eBlock['settings'], 'la grille est écrite à la création');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['rows' => [['Culture'], ['Oignon']]]]), 'tableau modifié');
		$this->assertEquals(['rows' => [['Culture'], ['Oignon']]], $this->reload($eBlock)['settings'], 'la grille est remplacée');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['rows' => [['<script>alert(1)</script>']]]]), 'une cellule qui porte du code est écrite telle quelle');

		$h = new BlockTypeUi()->render($this->reload($eBlock));

		$this->assertTrue(str_contains($h, '&lt;script&gt;'), 'et rendue échappée');
		$this->assertFalse(str_contains($h, '<script>'), 'jamais exécutable');

		$eBlockCopy = BlockLib::duplicate($this->reload($eBlock));

		$this->assertEquals($this->reload($eBlock)['settings'], $this->reload($eBlockCopy)['settings'], 'la copie emporte la grille');

	}

	/**
	 * Une vidéo écrite pour de bon : la page l'enregistre, la colonne reçoit la plus longue adresse que
	 * `build()` accepte, et la copie de l'objet entier emporte l'adresse. Une copie sans adresse serait
	 * écartée de la lecture sans rien dire à son auteur (`BlockTypeLib::isEmpty()`). L'objet ne porte
	 * qu'une vidéo : une seconde est refusée, à la page comme à la copie du bloc (`Block::url.video`).
	 */
	public function testVideoWriteAndCopy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = fn(string $url) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::VIDEO,
			'variant' => BlockTypeLib::SMALL,
			'url' => $url,
			'title' => 'Le paillage',
		];

		$this->assertEquals([\FailAction::class, 'Block::url.storable'], $this->executePage('block/block:doCreate', $post('https://vimeo.com/76979871?t=😀')), 'un emoji collé avec l\'adresse est refusé par la page, pas par la base');
		$this->assertEquals([\FailAction::class, 'Block::url.storable'], $this->executePage('block/block:doCreate', $post('https://vimeo.com/76979871?'.str_repeat('a', 70000))), 'une adresse trop longue aussi');
		$this->assertEquals(0, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est écrit');

		// Des caractères de trois octets jusqu'au dernier octet accepté : le cas le plus lourd que
		// l'étape `storable` laisse passer.
		$prefix = 'https://vimeo.com/76979871?';
		$url = $prefix.str_repeat('é', intdiv(\Filter::MAX_TEXT16_SIZE - strlen($prefix), 2));

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post($url)), 'vidéo créée');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		// Comparées par `===` : un échec d'`assertEquals()` écrirait deux fois trente mille caractères.
		$this->assertTrue($eBlock['url'] === $url, 'la plus longue adresse acceptée est écrite en entier');
		$this->assertEquals(BlockTypeLib::SMALL, $eBlock['variant'], 'avec sa taille');

		$this->assertEquals([\FailAction::class, 'Block::url.video'], $this->executePage('block/block:doCreate', $post('https://vimeo.com/76979872')), 'une seconde vidéo est refusée');

		$this->assertEquals([\FailAction::class, 'Block::url.video'], $this->executePage('block/block:doDuplicate', ['id' => (string)$eBlock['id']]), 'la copie de la vidéo aussi');
		$this->assertEquals(1, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'la leçon garde sa seule vidéo');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$this->assertTrue(array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id'])->getColumn('url')) === [$url], 'la copie de la leçon emporte l\'adresse de sa vidéo');

	}

	/**
	 * Une vidéo à côté d'un texte, écrite par les pages comme l'éditeur la poste : le texte s'écrit avec
	 * elle, reste écrit quand elle repasse à une taille, puisque son champ part toujours avec le
	 * formulaire, et se relit quand elle revient à côté de lui. La copie de la leçon l'emporte.
	 */
	public function testVideoBesideWriteAndCopy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$url = 'https://vimeo.com/76979871';
		$text = '<p>Un paillage épais garde l\'humidité.</p>';
		$content = '<main>'.$text.'</main>';

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::VIDEO,
			'variant' => BlockTypeLib::LEFT,
			'url' => $url,
			'title' => 'Le paillage',
			'content' => $text,
		]), 'vidéo créée à gauche de son texte');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		$this->assertEquals([BlockTypeLib::LEFT, $content], [$eBlock['variant'], $eBlock['content']], 'le texte est écrit avec la vidéo');

		$update = fn(string $variant, array $input) => $this->executePage('block/block:doUpdate', [
			'id' => (string)$eBlock['id'],
			'variant' => $variant,
			'url' => $url,
			'title' => 'Le paillage',
			'close' => '1',
		] + $input);

		$this->assertEquals([\ViewAction::class, NULL], $update(BlockTypeLib::SMALL, ['content' => $text]), 'repassée à la petite taille');
		$eBlockSmall = $this->reload($eBlock);

		$this->assertEquals([BlockTypeLib::SMALL, $content], [$eBlockSmall['variant'], $eBlockSmall['content']], 'le texte posté par son champ masqué reste écrit');

		$this->assertEquals([\ViewAction::class, NULL], $update(BlockTypeLib::RIGHT, ['content' => $text]), 'puis à droite du texte');

		$cBlockReadable = BlockLib::getReadable(self::OWNER, $eLesson['id']);
		$h = new BlockTypeUi()->render($cBlockReadable->first(), []);

		$this->assertTrue(str_contains($h, 'garde l\'humidit'), 'le texte se relit : '.$h);

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$eBlockCopy = BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id'])->first();

		$this->assertEquals([BlockTypeLib::RIGHT, $content], [$eBlockCopy['variant'], $eBlockCopy['content']], 'la copie de la leçon emporte la disposition de sa vidéo et son texte');

		// Ce que le champ toujours rendu évite : un formulaire sans lui viderait le texte, `build()`
		// lisant à `NULL` une propriété de la liste qui n'est pas postée.
		$this->assertEquals([\ViewAction::class, NULL], $update(BlockTypeLib::SMALL, []), 'enregistrée sans le champ du texte');
		$this->assertEquals(NULL, $this->reload($eBlock)['content'], 'le texte est perdu');

	}

	/**
	 * Le repère d'un bloc d'étapes enregistré se choisit sans formulaire : la page l'écrit par
	 * `doUpdate` avec `close`, date la leçon, refuse un repère hors de la liste et un lecteur sans
	 * droit, et n'en construit aucun sur une étape. Les deux copies, celle du bloc et celle de la
	 * leçon, l'emportent.
	 */
	public function testStepsVariantPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonEmpty();
		$eBlockSteps = $this->addComposite($eLesson, BlockTypeLib::STEPS, 'Paillage posé');
		$eBlockItem = $this->addChild($eBlockSteps, 'Sol couvert');

		$this->assertEquals(BlockTypeLib::NUMBERS, $this->reload($eBlockSteps)['variant'], 'les étapes naissent numérotées');

		// Reculée d'une heure hors du Lib : seul moyen de voir avancer un `NOW()` dans la seconde du test.
		\learning\Lesson::model()->whereId($eLesson['id'])->update(['updatedAt' => new \Sql('NOW() - INTERVAL 1 HOUR')]);

		$update = fn(Block $eBlock, string $variant) => $this->executePage('block/block:doUpdate', [
			'id' => (string)$eBlock['id'],
			'variant' => $variant,
			'close' => '1',
		]);

		$this->assertEquals([\ViewAction::class, NULL], $update($eBlockSteps, BlockTypeLib::CHECK), 'repère choisi');
		$this->assertEquals(BlockTypeLib::CHECK, $this->reload($eBlockSteps)['variant'], 'les étapes sont cochées');

		$eLessonAfter = \learning\Lesson::model()->select('updatedAt')->whereId($eLesson['id'])->get();

		$this->assertTrue(strtotime($eLessonAfter['updatedAt']) > time() - 600, 'la leçon est datée du choix : '.$eLessonAfter['updatedAt']);

		$this->assertEquals([\FailAction::class, 'Block::variant.allowed'], $update($eBlockSteps, BlockTypeLib::INFO), 'une couleur d\'encadré n\'est pas un repère');
		$this->assertEquals(BlockTypeLib::CHECK, $this->reload($eBlockSteps)['variant'], 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', [
			'id' => (string)$eBlockItem['id'],
			'variant' => BlockTypeLib::CHECK,
			'title' => 'Sol couvert',
			'content' => '<p>Sol couvert</p>',
		]), 'une étape s\'enregistre');
		$this->assertEquals(NULL, $this->reload($eBlockItem)['variant'], 'sans le repère posté, qu\'elle ne construit pas');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $update($eBlockSteps, BlockTypeLib::NUMBERS), 'un lecteur ne choisit pas le repère');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(BlockTypeLib::CHECK, $this->reload($eBlockSteps)['variant'], 'le repère reste celui de l\'auteur');

		$eBlockCopy = BlockLib::duplicate($this->reload($eBlockSteps));

		$this->assertEquals(BlockTypeLib::CHECK, $this->reload($eBlockCopy)['variant'], 'la copie du bloc emporte le repère');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$this->assertEquals([BlockTypeLib::CHECK, BlockTypeLib::CHECK], array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id'])->getColumn('variant')), 'la copie de la leçon emporte le repère de ses deux blocs d\'étapes');

	}

	/**
	 * Un bouton écrit pour de bon : la page refuse celui qui n'a pas de libellé et celui dont l'adresse
	 * n'est pas du web, sans rien écrire, enregistre le bouton complet, et les deux copies, celle du bloc
	 * et celle de l'objet entier, emportent libellé et adresse.
	 */
	public function testButtonWriteAndCopy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = fn(string $title, string $url) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::BUTTON,
			'title' => $title,
			'url' => $url,
		];

		$title = 'Lire la fiche technique';
		$url = 'https://www.example.org/fiche-paillage.pdf';

		$this->assertEquals([\FailAction::class, 'Block::title.buttonLabel'], $this->executePage('block/block:doCreate', $post('', $url)), 'un bouton sans libellé est refusé par la page');
		$this->assertEquals([\FailAction::class, 'Block::url.scheme'], $this->executePage('block/block:doCreate', $post($title, 'ftp://www.example.org/fiche-paillage.pdf')), 'une adresse ftp aussi');
		$this->assertEquals(0, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post($title, $url)), 'bouton créé');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		$this->assertEquals([BlockTypeLib::BUTTON, $title, $url], [$eBlock['type'], $eBlock['title'], $eBlock['url']], 'le bouton est écrit avec son libellé et son adresse');
		$this->assertEquals(1, BlockLib::getReadable(self::OWNER, $eLesson['id'])->count(), 'et se lit');

		$eBlockCopy = $this->reload(BlockLib::duplicate($this->reload($eBlock)));

		$this->assertEquals([$title, $url], [$eBlockCopy['title'], $eBlockCopy['url']], 'la copie du bloc emporte libellé et adresse');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$cBlockCopy = BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id']);

		$this->assertEquals([$title, $title], array_values($cBlockCopy->getColumn('title')), 'la copie de la leçon emporte le libellé de ses deux boutons');
		$this->assertEquals([$url, $url], array_values($cBlockCopy->getColumn('url')), 'et leur adresse');

	}

	/**
	 * Une vidéo enregistrée dont l'hébergeur a quitté la liste de l'éditeur de texte : son adresse est
	 * restée en base, et plus rien ne sait en composer un lecteur. Le lecteur ne voit pas le bloc,
	 * l'auteur le retrouve avec l'adresse qu'il avait collée, à remplacer, sans cadre vide ni lien.
	 */
	public function testVideoHostRemoved(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();
		$url = 'https://www.dailymotion.com/video/x5myp5?zz=1&b=2';

		$this->addVideoUnchecked($eLesson, $url);

		$this->assertEquals(1, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'le bloc est en base');
		$this->assertEquals(0, BlockLib::getReadable(self::OWNER, $eLesson['id'])->count(), 'le lecteur ne le voit pas');
		$this->assertEquals('', new BlockUi()->render(BlockLib::getReadable(self::OWNER, $eLesson['id'])), 'et la lecture ne pose rien, pas même la liste');

		$h = $this->getEditorItem($eLesson);

		$this->assertTrue(str_contains($h, '>'.encode($url).'<'), 'l\'auteur lit l\'adresse collée dans le cadre, encodée');
		$this->assertEquals(0, substr_count($h, 'data-block-video') + substr_count($h, '<iframe'), 'sans lecteur ni lien à lancer');
		$this->assertEquals(0, substr_count($h, '<div class="block-video-frame"></div>'), 'le cadre n\'est jamais vide');
		$this->assertTrue(str_contains($h, 'value="'.encode($url).'"'), 'le champ porte l\'adresse à remplacer');

	}

	/**
	 * Un bloc écarté de la lecture le dit à son auteur : sans la phrase du cadre, la vidéo disparait de
	 * la leçon lue sans que rien, dans l'éditeur, n'en donne la raison. La phrase tient à l'absence de
	 * lecteur, d'où une adresse qui n'a jamais été celle d'un hébergeur de vidéos.
	 */
	public function testVideoUnreadSaysSo(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->addVideoUnchecked($eLesson, 'https://example.org/video.mp4');

		$this->assertEquals('', new BlockUi()->render(BlockLib::getReadable(self::OWNER, $eLesson['id'])), 'rien en lecture, ni cadre ni phrase');
		$this->assertEquals(NULL, BlockSetting::getVideo('https://example.org/video.mp4')['player'], 'aucun lecteur ne se compose de cette adresse');
		$this->assertTrue(str_contains($this->getEditorItem($eLesson), 'util-annotation'), 'l\'éditeur le dit sous l\'adresse');

		$eLessonRead = $this->createLessonEmpty('Leçon à vidéo lue');

		$this->addVideoUnchecked($eLessonRead, 'https://vimeo.com/76979871');

		$this->assertEquals(1, BlockLib::getReadable(self::OWNER, $eLessonRead['id'])->count(), 'une vidéo qui a son lecteur se lit');
		$this->assertFalse(str_contains($this->getEditorItem($eLessonRead), 'util-annotation'), 'et l\'éditeur n\'en dit rien');

	}

	/**
	 * Une section écrite pour de bon : la page refuse celle qui n'a pas de titre et celle dont le
	 * surtitre dépasse sa borne, sans rien écrire, enregistre la section, et sa ligne du plan se rend de
	 * nouveau, au titre neuf, quand elle se referme. Les deux copies, celle du bloc et celle de l'objet
	 * entier, emportent surtitre et titre. Un lecteur ne l'écrit pas.
	 */
	public function testSectionWriteAndCopy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = fn(string $kicker, string $title) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::SECTION,
			'settings' => ['kicker' => $kicker],
			'title' => $title,
		];

		$kicker = 'Étape 1';
		$title = 'Observer le sol & le couvrir';

		$this->assertEquals([\FailAction::class, 'Block::title.missing'], $this->executePage('block/block:doCreate', $post($kicker, '')), 'une section sans titre est refusée par la page');
		$this->assertEquals([\FailAction::class, 'Block::settings.kicker'], $this->executePage('block/block:doCreate', $post(str_repeat('é', BlockSetting::SECTION_KICKER_LIMIT + 1), $title)), 'un surtitre trop long aussi');
		$this->assertEquals(0, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post($kicker, $title)), 'section créée');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		$this->assertEquals([BlockTypeLib::SECTION, ['kicker' => $kicker], $title], [$eBlock['type'], $eBlock['settings'], $eBlock['title']], 'la section est écrite avec son surtitre et son titre');
		$this->assertEquals(1, BlockLib::getReadable(self::OWNER, $eLesson['id'])->count(), 'et se lit');

		$instructions = $this->getInstructions('doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['kicker' => ''], 'title' => 'Couvrir le sol', 'close' => '1']);
		$planRow = $instructions['#block-plan-'.$eBlock['id']][0][1][0] ?? '';

		$this->assertEquals(['kicker' => NULL], $this->reload($eBlock)['settings'], 'un surtitre vidé n\'est plus un surtitre');
		$this->assertTrue(str_contains($planRow, 'Couvrir le sol'), 'la section refermée rend sa ligne du plan, au titre neuf : '.$planRow);
		$this->assertTrue(str_contains($instructions['#block-'.$eBlock['id']][0][1][0] ?? '', 'data-block-section'), 'et son enveloppe, qui porte le bandeau');

		// La ligne du plan d'un autre type ne nomme que ce type : refermé, il ne la renvoie pas.
		$eLessonText = $this->createLessonEmpty('Leçon au texte');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', ['owner' => self::OWNER, 'reference' => (string)$eLessonText['id'], 'draft' => '1', 'type' => BlockTypeLib::TEXT, 'content' => '<p>Pailler</p>']), 'texte créé');

		$eBlockText = BlockLib::getByOwnerAndReference(self::OWNER, $eLessonText['id'])->last();
		$instructions = $this->getInstructions('doUpdate', ['id' => (string)$eBlockText['id'], 'content' => '<p>Pailler épais</p>', 'close' => '1']);

		$this->assertEquals(['#block-'.$eBlockText['id']], array_keys($instructions), 'un texte refermé ne rend que son enveloppe');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['kicker' => $kicker], 'title' => $title]), 'section modifiée');

		$eBlockCopy = $this->reload(BlockLib::duplicate($this->reload($eBlock)));

		$this->assertEquals([['kicker' => $kicker], $title], [$eBlockCopy['settings'], $eBlockCopy['title']], 'la copie du bloc emporte surtitre et titre');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$cBlockCopy = BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id']);

		$this->assertEquals([$title, $title], array_values($cBlockCopy->getColumn('title')), 'la copie de la leçon emporte le titre de ses deux sections');
		$this->assertEquals([['kicker' => $kicker], ['kicker' => $kicker]], array_values($cBlockCopy->getColumn('settings')), 'et leur surtitre');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doCreate', $post($kicker, $title)), 'un lecteur n\'écrit pas de section');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['kicker' => 'Autre'], 'title' => 'Autre']), 'ni ne la modifie');
		$this->assertEquals([['kicker' => $kicker], $title], [$this->reload($eBlock)['settings'], $this->reload($eBlock)['title']], 'rien n\'a changé');

	}

	/**
	 * Des réponses écrites par les pages, comme l'éditeur les poste : la première naît ordinaire avec
	 * son parent, une seule réponse de la fratrie est la meilleure, et la marquer remet la précédente,
	 * que la réponse rend à sa place, éclairée. La copie d'une meilleure réponse est ordinaire, celle du
	 * parent ou de la leçon garde la meilleure de chaque fratrie.
	 */
	public function testAnswersPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$create = fn(array $post) => $this->executePage('block/block:doCreate', $post + [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
		]);

		$this->assertEquals([\ViewAction::class, NULL], $create([
			'type' => BlockTypeLib::ANSWERS,
			'variant' => BlockTypeLib::BEST,
			'title' => 'Attendre',
			'content' => '<p>Attendre la pluie</p>',
		]), 'réponses créées, la variante postée sur le parent étant ignorée');

		$eBlockAnswers = $this->getFirstBlock($eLesson);
		$eBlockFirst = $eBlockAnswers['cBlock']->first();

		$this->assertEquals([BlockTypeLib::ANSWERS, NULL], [$eBlockAnswers['type'], $eBlockAnswers['variant']], 'le parent ne porte pas de variante');
		$this->assertEquals([BlockTypeLib::ANSWER, BlockTypeLib::REGULAR, 'Attendre'], [$eBlockFirst['type'], $eBlockFirst['variant'], $eBlockFirst['title']], 'la première réponse naît ordinaire, avec ce qui a été écrit');

		$add = function(Block $eBlockAfter, string $variant, string $title) use($create, $eLesson): Block {

			$this->assertEquals([\ViewAction::class, NULL], $create([
				'after' => (string)$eBlockAfter['id'],
				'type' => BlockTypeLib::ANSWER,
				'variant' => $variant,
				'title' => $title,
				'content' => '<p>'.$title.'</p>',
			]), 'réponse ajoutée : '.$title);

			return $this->getFirstBlock($eLesson)['cBlock']->find(fn(Block $eBlock) => $eBlock['title'] === $title)->first();

		};

		$eBlockSecond = $add($eBlockFirst, BlockTypeLib::REGULAR, 'Bâcher');
		$eBlockThird = $add($eBlockSecond, BlockTypeLib::BEST, 'Pailler');

		$variants = fn() => array_values($this->getChildren($eBlockAnswers)->getColumn('variant'));

		$this->assertEquals([BlockTypeLib::REGULAR, BlockTypeLib::REGULAR, BlockTypeLib::BEST], $variants(), 'une réponse ajoutée peut naître meilleure');

		$this->assertEquals([\FailAction::class, 'Block::variant.allowed'], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockSecond['id'], 'variant' => BlockTypeLib::INFO, 'title' => 'Bâcher']), 'une couleur d\'encadré n\'est pas une variante de réponse');

		// Marquée sans être refermée : la réponse ouverte reste à l'écran, la sœur remise revient lue.
		$instructions = $this->getInstructions('doUpdate', ['id' => (string)$eBlockSecond['id'], 'variant' => BlockTypeLib::BEST, 'title' => 'Bâcher', 'content' => '<p>Bâcher</p>']);

		$this->assertEquals([BlockTypeLib::REGULAR, BlockTypeLib::BEST, BlockTypeLib::REGULAR], $variants(), 'marquer la deuxième remet la troisième');
		$this->assertEquals(['#block-'.$eBlockThird['id']], array_keys($instructions), 'la réponse ne rend que la sœur remise');

		[$method, [, $options]] = $instructions['#block-'.$eBlockThird['id']][0] + [1 => [NULL, NULL]];

		$this->assertEquals(['outerHtml', ['highlight' => TRUE]], [$method, $options], 'à sa place, éclairée');
		$this->assertTrue(BlockLib::getById($eBlockThird['id'])['updatedAt'] !== NULL, 'la sœur remise est datée');

		// Refermée, la réponse revient lue avec la sœur remise.
		$instructions = $this->getInstructions('doUpdate', ['id' => (string)$eBlockFirst['id'], 'variant' => BlockTypeLib::BEST, 'title' => 'Attendre', 'content' => '<p>Attendre la pluie</p>', 'close' => '1']);

		$this->assertEquals([BlockTypeLib::BEST, BlockTypeLib::REGULAR, BlockTypeLib::REGULAR], $variants(), 'marquer la première remet la deuxième');
		$this->assertTrue(array_key_exists('#block-'.$eBlockFirst['id'], $instructions), 'la réponse refermée revient');
		$this->assertEquals(['highlight' => TRUE], $instructions['#block-'.$eBlockSecond['id']][0][1][1] ?? NULL, 'la sœur remise revient éclairée');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockFirst['id'], 'variant' => BlockTypeLib::BEST, 'title' => 'Attendre', 'content' => '<p>Attendre</p>']), 'une meilleure réponse enregistrée de nouveau');
		$this->assertEquals([BlockTypeLib::BEST, BlockTypeLib::REGULAR, BlockTypeLib::REGULAR], $variants(), 'ne remet personne');

		$eBlockFourth = $add($this->reload($eBlockThird), BlockTypeLib::BEST, 'Semer un engrais vert');

		$this->assertEquals([BlockTypeLib::REGULAR, BlockTypeLib::REGULAR, BlockTypeLib::REGULAR, BlockTypeLib::BEST], $variants(), 'une réponse ajoutée meilleure remet la précédente');

		$eBlockCopy = $this->reload(BlockLib::duplicate($this->reload($eBlockFourth)));

		$this->assertEquals([BlockTypeLib::REGULAR, 'Semer un engrais vert'], [$eBlockCopy['variant'], $eBlockCopy['title']], 'la copie de la meilleure réponse est ordinaire');
		$this->assertEquals(BlockTypeLib::BEST, $this->reload($eBlockFourth)['variant'], 'l\'original reste la meilleure');

		$eBlockAnswersCopy = BlockLib::duplicate($this->reload($eBlockAnswers));

		$this->assertEquals([BlockTypeLib::REGULAR, BlockTypeLib::REGULAR, BlockTypeLib::REGULAR, BlockTypeLib::BEST, BlockTypeLib::REGULAR], array_values($this->getChildren($eBlockAnswersCopy)->getColumn('variant')), 'la copie des réponses garde leur meilleure');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		foreach(BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id']) as $eBlock) {
			$this->assertEquals(1, $eBlock['cBlock']->find(fn(Block $eBlockChild) => $eBlockChild['variant'] === BlockTypeLib::BEST)->count(), 'la copie de la leçon garde une meilleure réponse par fratrie');
		}

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlockFirst['id'], 'variant' => BlockTypeLib::BEST, 'title' => 'Attendre']), 'un lecteur ne marque pas de réponse');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(BlockTypeLib::BEST, $this->reload($eBlockFourth)['variant'], 'la meilleure reste celle de l\'auteur');

	}

	/**
	 * Des étiquettes écrites par les pages, comme l'éditeur les poste : la page refuse un mot trop long
	 * et une étiquette de trop sans rien écrire, enregistre les mots nettoyés, et les deux copies, celle
	 * du bloc et celle de la leçon, les emportent dans leur ordre. Un lecteur ne les écrit pas.
	 */
	public function testTagsWriteAndCopy(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$post = fn(array $tags) => [
			'owner' => self::OWNER,
			'reference' => (string)$eLesson['id'],
			'draft' => '1',
			'type' => BlockTypeLib::TAGS,
			'settings' => ['tags' => $tags],
		];

		$tags = ['Maraîchage diversifié', '1,2 ha', 'Vente directe'];

		$this->assertEquals([\FailAction::class, 'Block::settings.tag'], $this->executePage('block/block:doCreate', $post([...$tags, str_repeat('a', BlockSetting::TAG_LIMIT + 1)])), 'un mot trop long est refusé par la page');
		$this->assertEquals([\FailAction::class, 'Block::settings.full'], $this->executePage('block/block:doCreate', $post(array_map(fn(int $position) => 'Mot '.$position, range(0, BlockSetting::TAGS_MAX)))), 'une étiquette de trop aussi');
		$this->assertEquals(0, BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->count(), 'rien n\'est écrit');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doCreate', $post([' Maraîchage diversifié ', '', ...array_slice($tags, 1), 'vente DIRECTE'])), 'étiquettes créées');

		$eBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->last();

		$this->assertEquals([BlockTypeLib::TAGS, ['tags' => $tags]], [$eBlock['type'], $eBlock['settings']], 'les mots sont écrits nettoyés, dans l\'ordre de la saisie');
		$this->assertEquals(1, BlockLib::getReadable(self::OWNER, $eLesson['id'])->count(), 'et se lisent');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['tags' => ['']], 'close' => '1']), 'étiquettes vidées');
		$this->assertEquals(['tags' => []], $this->reload($eBlock)['settings'], 'aucun mot gardé');
		$this->assertEquals(0, BlockLib::getReadable(self::OWNER, $eLesson['id'])->count(), 'la lecture écarte le bloc vide');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['tags' => $tags]]), 'étiquettes modifiées');

		$eBlockCopy = $this->reload(BlockLib::duplicate($this->reload($eBlock)));

		$this->assertEquals(['tags' => $tags], $eBlockCopy['settings'], 'la copie du bloc emporte les mots');

		$eLessonCopy = $this->createLessonEmpty('Leçon copiée');

		BlockLib::duplicateByOwnerAndReference(self::OWNER, $eLesson['id'], $eLessonCopy['id']);

		$this->assertEquals([['tags' => $tags], ['tags' => $tags]], array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLessonCopy['id'])->getColumn('settings')), 'la copie de la leçon aussi');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doCreate', $post($tags)), 'un lecteur n\'écrit pas d\'étiquettes');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doUpdate', ['id' => (string)$eBlock['id'], 'settings' => ['tags' => ['Autre']]]), 'ni ne les modifie');
		$this->assertEquals(['tags' => $tags], $this->reload($eBlock)['settings'], 'rien n\'a changé');

	}

	public function testThirteenTypes(): void {

		$this->assertEquals(13, count(\learning\BlockOwnerLib::getTypes(self::OWNER)), 'la leçon accepte les sept types du lot B1, le tableau du lot L7c, la vidéo du lot C1, le bouton du lot C3, la section du lot C5, les réponses du lot C7 et les étiquettes du lot C8');

	}

	/**
	 * Le choix du premier bloc dans un éditeur rendu, jusqu'à la liste qui le suit.
	 */
	private function getStartOf(string $editor): string {
		return explode('<div id="block-list"', '<div id="block-start"'.explode('<div id="block-start"', $editor)[1])[0];
	}

	private function getStartTypes(string $start): array {

		preg_match_all('/data-block-choice data-type="([a-z-]+)"/', $start, $matches);

		return $matches[1];

	}

	/**
	 * Les instructions que la vue d'un geste rend, par sélecteur : la route est jouée comme le script la
	 * poste, puis sa vue sur ce que la page a préparé. Le registre des vues se lit par réflexion,
	 * `View::get()` demandant l'action que `executeView()` a déjà consommée.
	 */
	private function getInstructions(string $name, array $post): array {

		$data = $this->executeView('block/block:'.$name, $post, 'POST');

		if(is_string($data)) {
			$this->assertFail($name.' : '.$data.' au lieu d\'une vue');
			return [];
		}

		require_once __DIR__.'/../view/block.v.php';

		$view = new \ReflectionProperty(\View::class, 'views')->getValue()[$name];
		$output = new \ReflectionMethod(\View::class, 'getOutput')->invoke($view, $data, NULL);

		$instructions = [];

		foreach($output['__instructions'] as [, $selector, $list]) {
			$instructions[$selector] = $list;
		}

		return $instructions;

	}

	private function createLessonEmpty(string $name = 'Leçon à blocs'): \learning\Lesson {

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), \learning\Course::DRAFT, \learning\Course::PRIVATE);

		return $this->createLesson($this->createChapter($eCourse, 'Module'), $name, 10);

	}

	/**
	 * Une vidéo écrite avec l'adresse donnée, quelle qu'elle soit.
	 */
	private function addVideoUnchecked(\learning\Lesson $eLesson, string $url): Block {

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'parent' => new Block(),
			'type' => BlockTypeLib::VIDEO,
			'variant' => BlockTypeLib::FULL,
			// Sans `build()` : `Block::url.host` refuse une adresse sans lecteur, et c'est le cas éprouvé,
			// celui d'une adresse acceptée à son écriture par une liste qui a changé depuis.
			'url' => $url,
			'title' => 'Le paillage',
			'eBlockAfter' => new Block(),
			'eBlockBefore' => new Block(),
		]);

		BlockLib::create($eBlock);

		return $eBlock;

	}

	private function addText(\learning\Lesson $eLesson, string $text, Block $eBlockAfter = new Block(), Block $eBlockBefore = new Block()): Block {

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'parent' => new Block(),
			'type' => BlockTypeLib::TEXT,
			'content' => '<main><p>'.$text.'</p></main>',
			'eBlockAfter' => $eBlockAfter,
			'eBlockBefore' => $eBlockBefore,
		]);

		BlockLib::create($eBlock);

		return $eBlock;

	}

	private function addComposite(\learning\Lesson $eLesson, string $type, string $title): Block {

		$variants = BlockTypeLib::getVariants($type);

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'parent' => new Block(),
			'type' => $type,
			'variant' => ($variants === []) ? NULL : $variants[0],
			'title' => $title,
			'content' => '<main><p>'.$title.'</p></main>',
		]);

		BlockLib::create($eBlock);

		return $eBlock;

	}

	private function addChild(Block $eBlockParent, string $title, Block $eBlockAfter = new Block()): Block {

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $eBlockParent['reference'],
			'parent' => $eBlockParent,
			'type' => BlockTypeLib::ITEM,
			'title' => $title,
			'content' => '<main><p>'.$title.'</p></main>',
			'eBlockAfter' => $eBlockAfter,
		]);

		BlockLib::create($eBlock);

		return $eBlock;

	}

	private function getChildren(Block $eBlockParent): \Collection {

		return Block::model()
			->select(Block::getSelection())
			->whereParent($eBlockParent)
			->sort(['position' => SORT_ASC])
			->getCollection();

	}

	private function getChildPositions(Block $eBlockParent): array {
		return array_values($this->getChildren($eBlockParent)->getColumn('position'));
	}

	/**
	 * L'arbre d'une leçon, lisible d'un coup : un texte par son contenu, un bloc composé par son type
	 * et les titres de ses enfants.
	 */
	private function getTree(\learning\Lesson $eLesson): array {

		$tree = [];

		foreach(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']) as $eBlock) {

			if($eBlock['type'] === BlockTypeLib::TEXT) {
				$tree[] = strip_tags($eBlock['content']);
			} else {
				$tree[] = $eBlock['type'].'['.implode(',', array_values($eBlock['cBlock']->getColumn('title'))).']';
			}

		}

		return $tree;

	}

	private function getFirstBlock(\learning\Lesson $eLesson): Block {
		return BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->first();
	}

	private function reload(Block $eBlock): Block {
		return BlockLib::getById($eBlock['id']);
	}

	private function getBlockOf(\learning\Lesson $eLesson): Block {

		return new Block([
			'owner' => self::OWNER,
			'eOwner' => \learning\BlockOwnerLib::getOwner(self::OWNER, $eLesson['id']),
		]);

	}

	private function getTexts(\learning\Lesson $eLesson): array {

		$texts = [];

		foreach(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']) as $eBlock) {
			$texts[] = strip_tags($eBlock['content']);
		}

		return $texts;

	}

	private function getPositions(\learning\Lesson $eLesson): array {
		return array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->getColumn('position'));
	}

}
?>
