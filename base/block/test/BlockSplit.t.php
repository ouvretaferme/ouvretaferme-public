<?php
namespace block;

require_once __DIR__.'/../../../ouvretaferme/learning/test/LearningFixture.t.php';

/**
 * Le « / » coupe un bloc texte en deux (`BlockLib::split()`, `doSplit`) : le texte d'avant reste dans
 * le bloc, celui d'après devient un texte sous lui. Le bloc choisi dans la liste n'est pas écrit ici :
 * il naît par `doCreate` à son premier enregistrement, comme celui du « + », et un bloc choisi puis
 * refermé vide ne laisse donc rien.
 *
 * Lancement : dev/test package=block file=BlockSplit
 */
class BlockSplitTest extends \learning\LearningFixtureTest {

	const OWNER = \learning\BlockOwnerLib::LESSON;

	public function testSplitMiddle(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'Un Deux');
		$this->addText($eLesson, 'B');

		$this->assertTrue(BlockLib::split($this->getBefore($this->reload($eBlockA), '<p>Un</p>'), $this->getRest($eBlockA, '<p>Deux</p>')), 'le bloc coupé reste');

		$this->assertEquals(['Un', 'Deux', 'B'], $this->getTexts($eLesson), 'le texte d\'après se range sous le bloc coupé');
		$this->assertEquals([0, 1, 2], $this->getPositions($eLesson), 'rangs contigus');

	}

	/**
	 * Au début d'un bloc, rien ne reste au-dessus : le bloc garde le texte d'après, et le bloc choisi
	 * se posera avant lui.
	 */
	public function testSplitStart(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'Deux');

		$this->assertTrue(BlockLib::split($this->getBefore($this->reload($eBlockA), ''), $this->getRest($eBlockA, '<p>Deux</p>')), 'le bloc reste');
		$this->assertEquals(['Deux'], $this->getTexts($eLesson), 'aucun bloc vide au-dessus');

	}

	public function testSplitEnd(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'Un');

		$this->assertTrue(BlockLib::split($this->getBefore($this->reload($eBlockA), '<p>Un</p>'), $this->getRest($eBlockA, '')), 'le bloc reste');
		$this->assertEquals(['Un'], $this->getTexts($eLesson), 'aucun bloc vide en dessous');

	}

	/**
	 * Un bloc qui ne portait que le « / » n'a plus rien : il se retire, et le bloc choisi prend sa
	 * place.
	 */
	public function testSplitAlone(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$this->addText($eLesson, 'A');
		$eBlockB = $this->addText($eLesson, '/');
		$this->addText($eLesson, 'C');

		$this->assertTrue(BlockLib::split($this->getBefore($this->reload($eBlockB), ''), $this->getRest($eBlockB, '')) === FALSE, 'le bloc est retiré');
		$this->assertEquals(['A', 'C'], $this->getTexts($eLesson), 'aucun bloc vide');
		$this->assertEquals([0, 1], $this->getPositions($eLesson), 'rangs resserrés');

	}

	public function testSplitPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'Un Deux');

		$post = fn(string $type) => [
			'id' => (string)$eBlockA['id'],
			'type' => $type,
			'content' => '<p>Un</p>',
			'rest' => '<p>Deux</p>',
		];

		$this->assertEquals([\FailAction::class, 'Block::type.allowed'], $this->executePage('block/block:doSplit', $post(BlockTypeLib::ITEM)), 'un type que le premier niveau refuse ne coupe rien');
		$this->assertEquals(['Un Deux'], $this->getTexts($eLesson), 'rien n\'est écrit sur un refus');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doSplit', $post(BlockTypeLib::CALLOUT)), 'coupé');
		$this->assertEquals(['Un', 'Deux'], $this->getTexts($eLesson), 'deux textes');

		$eBlockCallout = new Block(['owner' => self::OWNER, 'reference' => $eLesson['id'], 'type' => BlockTypeLib::CALLOUT, 'variant' => BlockTypeLib::INFO, 'title' => 'À retenir', 'content' => NULL]);
		BlockLib::create($eBlockCallout);

		$postCallout = $post(BlockTypeLib::TEXT);
		$postCallout['id'] = (string)$eBlockCallout['id'];

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('block/block:doSplit', $postCallout), 'seul un texte se coupe');

		$this->actAs($this->eUserReader);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('block/block:doSplit', $post(BlockTypeLib::TEXT)), 'un lecteur ne coupe rien');

	}

	/**
	 * Le « / » en début de ligne poste un texte d'avant vide (`BlockSlash.choose()`) : le bloc coupé
	 * garde le texte d'après sans qu'un texte vide naisse au-dessus, et se retire quand rien ne suit.
	 */
	public function testSplitPageStart(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = $this->createLessonEmpty();

		$eBlockA = $this->addText($eLesson, 'Deux');

		$post = [
			'id' => (string)$eBlockA['id'],
			'type' => BlockTypeLib::CALLOUT,
			'content' => '',
			'rest' => '<p>Deux</p>',
		];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doSplit', $post), 'coupé en début de ligne');
		$this->assertEquals(['Deux'], $this->getTexts($eLesson), 'un seul bloc, qui porte le texte d\'après');
		$this->assertEquals([$eBlockA['id']], BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->getIds(), 'et c\'est le bloc coupé');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('block/block:doSplit', ['rest' => ''] + $post), 'coupé sans rien avant ni après');
		$this->assertTrue(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->empty(), 'le bloc est retiré');

	}

	/**
	 * La mise à jour du bloc coupé et la création du reste s'écrivent dans la même transaction. Le
	 * reste est mis en échec par son rang, hors de `int16` non signé en mode strict : aucune donnée postée ne
	 * peut le faire échouer, `build()` les ayant toutes jugées avant.
	 *
	 * La transaction du décor est annulée d'abord, comme dans `BlockTest::testCompositeCreateIsAtomic()`,
	 * et le bloc de départ est écrit hors transaction, donc réellement : il est retiré à la fin quoi
	 * qu'il arrive. Son rang est posé sans le Lib, qui n'en poserait jamais un pareil : c'est le
	 * rang du reste qui doit échouer. La référence est factice, et l'échec survient avant `touch()`.
	 */
	/**
	 * Le « / » ne s'ouvre que dans un texte de premier niveau qui existe : il le coupe. Un bloc à écrire
	 * n'a rien à couper, et le texte d'un encadré garde son « / ».
	 */
	public function testSplitMarks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$marked = fn(string $h) => preg_match('/<form[^>]+data-block-split/', $h) === 1;

		$eLesson = $this->createLessonEmpty();

		$eBlockText = $this->addText($eLesson, 'Un texte à couper');
		$eBlockCallout = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'type' => BlockTypeLib::CALLOUT,
			'variant' => BlockTypeLib::INFO,
			'content' => '<main><p>Un conseil</p></main>',
		]);

		BlockLib::create($eBlockCallout);

		$cBlock = BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']);

		foreach($cBlock as $eBlock) {
			BlockLib::fillEditor($eBlock, \learning\BlockOwnerLib::getTypes(self::OWNER));
		}

		$this->assertTrue($marked(new BlockUi()->getItem($cBlock[$eBlockText['id']])), 'un texte de premier niveau qui existe ouvre le « / »');
		$this->assertFalse($marked(new BlockUi()->getItem($cBlock[$eBlockCallout['id']])), 'un encadré qui existe ne l\'ouvre pas');

		$cBlockNew = BlockLib::getNew(self::OWNER, 123, \learning\BlockOwnerLib::getTypes(self::OWNER));

		$this->assertFalse($marked(new BlockUi()->getNew($cBlockNew[BlockTypeLib::TEXT])), 'le bloc à écrire du « + » ne l\'ouvre pas, un seul pouvant être posé à la fois');
		$this->assertFalse($marked(new BlockUi()->getNew($cBlockNew[BlockTypeLib::CALLOUT])), 'un encadré ne l\'ouvre pas');

	}

	public function testSplitIsAtomic(): void {

		$reference = 2147480001;

		\learning\Course::model()->rollBack();

		try {

			$eBlock = new Block([
				'owner' => self::OWNER,
				'reference' => $reference,
				'type' => BlockTypeLib::TEXT,
				'content' => '<main><p>Un Deux</p></main>',
				'position' => 65535,
			]);

			// Écrit hors de `build()` et du Lib : `build()` aurait jugé le type contre l'usage et le texte
			// par le cast de l'éditeur, et `BlockLib::create()` aurait recalculé le rang à partir des
			// voisins, qui n'en donnent jamais un pareil. Or c'est le rang 65535 qui fait échouer le
			// reste, rangé juste après : le type et le texte, eux, sont ceux qu'un formulaire poste.
			Block::model()->insert($eBlock);

			try {
				BlockLib::split($this->getBefore(BlockLib::getById($eBlock['id']), '<p>Un</p>'), $this->getRest($eBlock, '<p>Deux</p>'));
				$this->assertFail('l\'écriture du reste aurait dû échouer');
			} catch(\Exception) {
			}

			Block::model()->rollBack();

			$this->assertEquals('Un Deux', strip_tags(BlockLib::getById($eBlock['id'])['content']), 'le bloc coupé n\'a pas changé');
			$this->assertEquals(1, Block::model()->whereOwner(self::OWNER)->whereReference($reference)->count(), 'aucun reste écrit');

		} finally {

			Block::model()->whereOwner(self::OWNER)->whereReference($reference)->delete();

			\learning\Course::model()->beginTransaction();

		}

	}

	private function createLessonEmpty(): \learning\Lesson {

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), \learning\Course::DRAFT, \learning\Course::PRIVATE);

		return $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon à couper', 10);

	}

	private function addText(\learning\Lesson $eLesson, string $text): Block {

		$eBlock = new Block([
			'owner' => self::OWNER,
			'reference' => $eLesson['id'],
			'type' => BlockTypeLib::TEXT,
			'content' => '<main><p>'.$text.'</p></main>',
		]);

		BlockLib::create($eBlock);

		return $eBlock;

	}

	/**
	 * Le texte d'avant, construit sur le bloc coupé comme la page le construit.
	 */
	private function getBefore(Block $eBlock, string $html): Block {

		$eBlock->build(['content'], ['content' => $html]);

		return $eBlock;

	}

	/**
	 * Le reste, construit comme la page le construit : par `build()`, qui passe le texte au cast de
	 * l'usage.
	 */
	private function getRest(Block $eBlock, string $html): Block {

		$eBlockRest = new Block([
			'owner' => $eBlock['owner'],
			'reference' => $eBlock['reference'],
			'type' => BlockTypeLib::TEXT,
		]);

		$eBlockRest->build(['content'], ['content' => $html]);

		return $eBlockRest;

	}

	private function reload(Block $eBlock): Block {
		return BlockLib::getById($eBlock['id']);
	}

	private function getTexts(\learning\Lesson $eLesson): array {

		$texts = [];

		foreach(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id']) as $eBlock) {
			$texts[] = strip_tags((string)$eBlock['content']);
		}

		return $texts;

	}

	private function getPositions(\learning\Lesson $eLesson): array {
		return array_values(BlockLib::getByOwnerAndReference(self::OWNER, $eLesson['id'])->getColumn('position'));
	}

}
?>
