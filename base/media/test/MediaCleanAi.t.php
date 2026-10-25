<?php
namespace media;

/**
 * Le nettoyage des médias (`CleanLib::check()`, cron `media/cron/clean`).
 *
 * Ce qui s'y prouve :
 * 1. une image posée dans le pied de page d'un site (`website\Website.footer`) est retrouvée par
 *    `EditorLib::clean()` et repasse ACTIVE ; avant le correctif, elle restait SEARCHING et partait
 *    en DELETED à la fin du passage ;
 * 2. `MediaLib::clean()` réactive les médias par lots d'au plus 101 hashes, chacun une seule fois ;
 *    avant le correctif, le lot n'était jamais vidé, chaque élément au-delà du centième relançait une
 *    mise à jour sur toute la liste accumulée, et `product-vignette` tournait des heures ;
 * 3. une photo citée par une note (`note\Note.content`) repasse ACTIVE, et une photo que plus aucun
 *    contenu ne cite reste SEARCHING, que la fin du passage met en DELETED ; sans les notes parmi les
 *    contenus lus, la photo d'une note partirait comme l'orpheline.
 *
 * Décor : un média fictif en SEARCHING et un pied de page réécrit sur un site existant, ou une note du
 * décor F1, dans une transaction annulée à la fin de chaque méthode.
 *
 * Lancement : dev/test package=media file=MediaCleanAi
 */
class MediaCleanAiTest extends \Test {

	const HASH = 'zztestfootercleanaip';

	public function setUp(): void {
		Media::model()->beginTransaction();
	}

	public function tearDown(): void {

		if(Media::model()->pdo()->inTransaction()) {
			Media::model()->rollBack();
		}

	}

	public function testFooterImageStaysActive(): void {

		$eWebsite = \website\Website::model()
			->select('id', 'footer')
			->sort('id')
			->get();

		$this->assertTrue($eWebsite->notEmpty(), 'aucun site en base pour porter le pied de page');

		if($eWebsite->empty()) {
			return;
		}

		$eMedia = new Media([
			'type' => 'editor',
			'hash' => self::HASH,
			'status' => Media::SEARCHING,
		]);

		Media::model()->insert($eMedia);

		\website\Website::model()->update($eWebsite, [
			'footer' => '<div xyz="'.self::HASH.'"></div>'
		]);

		MediaLib::getInstance('editor')->clean();

		$status = Media::model()
			->select('status')
			->whereId($eMedia['id'])
			->getValue('status');

		$this->assertEquals(Media::ACTIVE, $status, 'image du pied de page laissée en '.$status);

	}

	/**
	 * 3. un PDF de facture électronique (`invoicing-pdf`) ou d'exercice (`financial-year-pdf`) vit dans
	 *    une base structure_<id> : la passe le remet ACTIVE sans le chercher, et laisse DELETED ce qu'un
	 *    effacement voulu a marqué. Sous l'ancien type partagé `pdf-content`, seul `selling\PdfContent`
	 *    était cherché, et 581 de ces PDF partaient en DELETED sur la copie de prod du 03/10/2026.
	 */
	public function testStructurePdfStaysActive(): void {

		foreach(['invoicing-pdf', 'financial-year-pdf'] as $type) {

			$eMediaSearching = new Media([
				'type' => $type,
				'hash' => self::HASH,
				'status' => Media::SEARCHING,
			]);

			$eMediaDeleted = new Media([
				'type' => $type,
				'hash' => substr(self::HASH, 0, 19).'d',
				'status' => Media::DELETED,
			]);

			Media::model()->insert($eMediaSearching);
			Media::model()->insert($eMediaDeleted);

			MediaLib::getInstance($type)->clean();

			$statuses = Media::model()
				->select('id', 'status')
				->whereId('IN', [$eMediaSearching['id'], $eMediaDeleted['id']])
				->getCollection(index: 'id');

			$this->assertEquals(Media::ACTIVE, $statuses[$eMediaSearching['id']]['status'], $type.' cité depuis une base de structure, laissé en '.$statuses[$eMediaSearching['id']]['status']);
			$this->assertEquals(Media::DELETED, $statuses[$eMediaDeleted['id']]['status'], $type.' effacé volontairement, remis en '.$statuses[$eMediaDeleted['id']]['status']);

		}

	}

	public function testNotePhotoStaysActive(): void {

		$eStructure = \structure\Structure::model()
			->select('id')
			->whereId(\structure\FixtureAiLib::F1)
			->get();

		$eUser = \user\User::model()
			->select('id')
			->whereEmail(\structure\FixtureAiLib::AUTHOR)
			->get();

		if($this->assertDecorPresent(($eStructure->notEmpty() and $eUser->notEmpty()), 'la structure F1 du décor et son administratrice, qui portent la note') === FALSE) {
			return;
		}

		$hashCited = substr(self::HASH, 0, 19).'n';
		$hashOrphan = substr(self::HASH, 0, 19).'o';

		$cMedia = new \Collection();

		foreach([$hashCited, $hashOrphan] as $hash) {

			$eMedia = new Media([
				'type' => 'editor',
				'hash' => $hash,
				'status' => Media::SEARCHING,
			]);

			Media::model()->insert($eMedia);
			$cMedia[$hash] = $eMedia;

		}

		// Le contenu s'écrit sous sa forme stockée, sans `build()` : ce test éprouve ce que `clean()` lit
		// en base, et `note/test/Note.t.php` éprouve déjà ce que `build()` garde d'une photo postée.
		$eNote = new \note\Note([
			'structure' => $eStructure,
			'title' => 'Photo du nettoyage',
			'content' => '<main><p>Avant</p><figure interactive="true"><image url="https://media.example.org/editor/'.$hashCited.'.jpg" color="#ffffff" width="1000" height="750" xyz="'.$hashCited.'" xyz-width="1000" xyz-height="750" xyz-version="1"/></figure></main>',
			'readers' => \note\Note::ADMIN,
			'createdBy' => $eUser,
			'cTarget' => new \Collection()
		]);

		\note\NoteLib::create($eNote);

		MediaLib::getInstance('editor')->clean();

		$statuses = Media::model()
			->select('id', 'status')
			->whereId('IN', $cMedia->getIds())
			->getCollection(index: 'id');

		$this->assertEquals(Media::ACTIVE, $statuses[$cMedia[$hashCited]['id']]['status'], 'photo citée par une note laissée en '.$statuses[$cMedia[$hashCited]['id']]['status']);
		$this->assertEquals(Media::SEARCHING, $statuses[$cMedia[$hashOrphan]['id']]['status'], 'photo orpheline passée en '.$statuses[$cMedia[$hashOrphan]['id']]['status']);

	}

	public function testBatchesStayBounded(): void {

		$lib = new MediaCleanBatchProbeLib();

		$expected = \user\User::model()
			->where('vignette', '!=', NULL)
			->count();

		$this->assertTrue($expected > 101, 'moins de 102 vignettes en base : le lot ne se remplit jamais, le cas ne s\'éprouve pas ('.$expected.')');

		$lib->clean();

		$this->assertTrue(max($lib->batches ?: [0]) <= 101, 'lot de '.max($lib->batches ?: [0]).' hashes');
		$this->assertEquals($expected, array_sum($lib->batches), 'hashes envoyés au total');

	}

}

/**
 * Les vignettes d'utilisateur, dont on retient la taille de chaque lot au lieu d'écrire.
 */
class MediaCleanBatchProbeLib extends MediaLib {

	public array $batches = [];

	public function __construct() {

		$this->type = 'user-vignette';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	protected function activeMedias(array $hashes): void {
		$this->batches[] = count($hashes);
	}

}
?>
