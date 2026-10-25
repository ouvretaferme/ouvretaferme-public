<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * La feuille signée d'une date en présentiel (lot L8e-2) : quand elle se dépose, ce que le dépôt
 * refuse, le plafond de dix fichiers, le téléchargement réservé à l'équipe, le retrait, le
 * nettoyage de nuit de son type, et la suppression d'une date ou d'une formation qu'une feuille
 * garde.
 *
 * Le dépôt écrit sur le disque du stockage, que le `rollBack()` de la fixture n'annule pas : chaque
 * test efface les fichiers qu'il a posés.
 *
 * Lancement : dev/test package=learning file=DateSheet
 */
class DateSheetTest extends LearningDateFixtureTest {

	private array $hashesStored = [];

	public function tearDown(): void {

		foreach($this->hashesStored as $hash) {
			\media\MediaSetting::$mediaDriver->delete(new \media\LearningDateSheetAiUi()->getBasenameByHash($hash));
		}

		$this->hashesStored = [];

		parent::tearDown();

	}

	/**
	 * Le dépôt se propose sur une date passée en présentiel, tant qu'elle a moins de dix fichiers.
	 */
	public function testAccept(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);

		$this->assertFalse($this->prepareSheets($eDate)->acceptSheetUpload(), 'pas avant la date');

		$eDate = $this->moveDate($eDate, -2);
		$this->assertTrue($this->prepareSheets($eDate)->acceptSheetUpload(), 'après une date sur place');

		$eDateRemote = $this->prepareSheets($eDate);
		foreach($eDateRemote['cDateSequence'] as $eDateSequence) {
			$eDateSequence['mode'] = DateSequence::REMOTE;
		}
		$this->assertFalse($eDateRemote->acceptSheetUpload(), 'une visio n\'a pas de feuille');

		$eDateFull = $this->prepareSheets($eDate);
		$eDateFull['sheets'] = DateSheet::MAX_BY_DATE;
		$this->assertFalse($eDateFull->acceptSheetUpload(), 'pas au-delà de dix fichiers');

		$eDateCanceled = $this->createDate($eCourse, 3);
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));
		$eDateCanceled = $this->moveDate($eDateCanceled, -2);
		$this->assertFalse($this->prepareSheets($eDateCanceled)->acceptSheetUpload(), 'pas sur une date annulée');

	}

	/**
	 * Un PDF et une photo se déposent sous le nom du fichier ; le type se lit dans le contenu, le
	 * poids est borné, et le onzième fichier est refusé.
	 */
	public function testUpload(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->moveDate($this->createDate($eCourse, 2), -2);

		$this->actAs($this->eUserAdmin);

		[$fails, $eDateSheetPdf] = $this->upload($eDate, 'Feuille du matin.pdf', $this->getPdf());
		$this->assertEquals([], $fails, 'PDF accepté');
		$this->assertEquals('Feuille du matin', $eDateSheetPdf['name'], 'le nom du fichier sans son extension');
		$this->assertEquals($eCourse['id'], $eDateSheetPdf['course']['id'], 'la formation de la date');

		[$fails, $eDateSheetPng] = $this->upload($eDate, 'photo.png', $this->getPng());
		$this->assertEquals([], $fails, 'photo acceptée');

		$this->assertEquals('learning-date-sheet', \media\Media::model()->select('type')->whereHash($eDateSheetPng['hash'])->get()['type'], 'rangée sous son propre type, que le nettoyage des pièces jointes ne touche pas');
		$this->assertEquals($this->getPdf(), file_get_contents(\storage\DriverLib::directory().'/'.new \media\LearningDateSheetAiUi()->getBasenameByHash($eDateSheetPdf['hash'])), 'le PDF stocké tel quel');

		[$fails] = $this->upload($eDate, 'faux.pdf', 'Un texte qui se dit PDF.');
		$this->assertEquals(['DateSheet::file.type'], $fails, 'ni PDF ni image');

		$gif = new \Imagick();
		$gif->newImage(10, 10, 'white');
		$gif->setImageFormat('gif');

		[$fails] = $this->upload($eDate, 'photo.gif', $gif->getImageBlob());
		$this->assertEquals(['DateSheet::file.type'], $fails, 'une image qu\'Imagick lit, mais ni JPEG ni PNG');

		[$fails] = $this->upload($eDate, 'abimee.png', "\x89PNG\r\n\x1a\n\x00\x00");
		$this->assertEquals(['DateSheet::file.type'], $fails, 'image abîmée');

		[$fails] = $this->upload($eDate, 'lourd.pdf', '%PDF-'.str_repeat('0', \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024 - 4));
		$this->assertEquals(['attachmentSize'], $fails, 'trop lourd');

		foreach(range(3, DateSheet::MAX_BY_DATE) as $position) {
			$this->upload($eDate, 'Page '.$position.'.pdf', $this->getPdf());
		}

		$this->assertEquals(DateSheet::MAX_BY_DATE, DateSheetLib::countByDate($eDate), 'dix fichiers');

		[$fails] = $this->upload($eDate, 'De trop.pdf', $this->getPdf());
		$this->assertEquals(['DateSheet::tooMany'], $fails, 'le onzième est refusé, même sans passer par la page');
		$this->assertEquals(DateSheet::MAX_BY_DATE, DateSheetLib::countByDate($eDate), 'toujours dix');

	}

	/**
	 * Une photo de feuille prise au téléphone perd sa position et ses métadonnées au dépôt, comme une
	 * pièce jointe de leçon : la feuille porte déjà des signatures, pas le lieu où elle a été photographiée.
	 */
	public function testMetadata(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->moveDate($this->createDate($eCourse, 2), -2);

		$this->actAs($this->eUserAdmin);

		$content = $this->getJpegWithGps();

		$source = new \Imagick();
		$source->readImageBlob($content);
		$this->assertEquals('48/1,51/1,30/1', $source->getImageProperty('exif:GPSLatitude'), 'la photo déposée porte une position');

		[$fails, $eDateSheet] = $this->upload($eDate, 'Feuille.jpg', $content);
		$this->assertEquals([], $fails, 'photo acceptée');

		$stored = new \Imagick(\storage\DriverLib::directory().'/'.new \media\LearningDateSheetAiUi()->getBasenameByHash($eDateSheet['hash']));

		$this->assertEquals([], $stored->getImageProperties('exif:*'), 'aucune métadonnée EXIF servie');

	}

	/**
	 * La route de dépôt juge la date elle-même : à venir, elle refuse, comme une visio.
	 */
	public function testUploadRoute(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->createDate($eCourse, 2);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateSheet:doUpload', ['date' => (string)$eDate['id']]), 'refusé avant la date');

		$this->moveDate($eDate, -2);

		$this->assertEquals([\FailAction::class, 'DateSheet::file.check'], $this->executePage('learning/dateSheet:doUpload', ['date' => (string)$eDate['id']]), 'sans fichier');

	}

	/**
	 * La feuille se télécharge et se retire par l'équipe seule, date passée comprise.
	 */
	public function testReadAndDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->moveDate($this->createDate($eCourse, 2), -2);

		$this->actAs($this->eUserAdmin);

		[, $eDateSheet] = $this->upload($eDate, 'Feuille.pdf', $this->getPdf());

		$this->assertEquals(\DataAction::class, $this->executeView('formation/date/feuille/'.$eDateSheet['id'], []), 'l\'équipe télécharge');

		$this->actAs($this->eUserReader);

		$this->assertEquals(\NotAllowedAction::class, $this->executeView('formation/date/feuille/'.$eDateSheet['id'], []), 'une personne hors de l\'équipe ne télécharge pas');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/dateSheet:doDelete', ['id' => (string)$eDateSheet['id']]), 'ni ne retire');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/dateSheet:doDelete', ['id' => (string)$eDateSheet['id']]), 'l\'équipe retire');
		$this->assertEquals(0, DateSheetLib::countByDate($eDate), 'plus de fichier');

	}

	/**
	 * Le nettoyage de nuit réactive les feuilles que la table porte, et laisse partir celles
	 * qu'on a retirées ; celui des pièces jointes de leçon ne les réactive pas.
	 */
	public function testClean(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->moveDate($this->createDate($eCourse, 2), -2);

		$this->actAs($this->eUserAdmin);

		[, $eDateSheetKept] = $this->upload($eDate, 'Gardée.pdf', $this->getPdf());
		[, $eDateSheetDeleted] = $this->upload($eDate, 'Retirée.pdf', $this->getPdf());

		DateSheetLib::delete(DateSheetLib::getById($eDateSheetDeleted['id']));

		$hashes = [$eDateSheetKept['hash'], $eDateSheetDeleted['hash']];

		\media\Media::model()
			->whereHash('IN', $hashes)
			->update(['status' => \media\Media::SEARCHING]);

		$this->assertTrue(new \media\LearningAttachmentAiLib()->clean(), 'le nettoyage des pièces jointes aboutit');
		$this->assertEquals(\media\Media::SEARCHING, $this->getMediaStatuses($hashes)[$eDateSheetKept['hash']], 'les pièces jointes ne réactivent pas une feuille');

		$this->assertTrue(new \media\LearningDateSheetAiLib()->clean(), 'le nettoyage des feuilles aboutit');

		$statuses = $this->getMediaStatuses($hashes);
		$this->assertEquals(\media\Media::ACTIVE, $statuses[$eDateSheetKept['hash']], 'feuille portée par sa ligne');
		$this->assertEquals(\media\Media::SEARCHING, $statuses[$eDateSheetDeleted['hash']], 'feuille retirée');

	}

	/**
	 * Une feuille garde sa date et sa formation de la suppression : la formation refuse sans rien
	 * effacer, et une date sans inscrit refuse aussi.
	 */
	public function testDeleteGuard(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDate = $this->moveDate($this->createDate($eCourse, 2), -2);
		$eDateOther = $this->createDate($eCourse, 5);

		$this->actAs($this->eUserAdmin);

		$this->upload($eDate, 'Feuille.pdf', $this->getPdf());

		$fw = new \FailWatch();
		DateLib::delete(DateLib::getById($eDate['id']));
		$this->assertEquals(['Date::deleteSheets'], array_column($fw->get(), 1), 'une date sans inscrit mais avec sa feuille ne se supprime pas');
		$this->assertTrue(DateLib::getById($eDate['id'])->notEmpty(), 'la date reste');

		$fw = new \FailWatch();
		CourseLib::delete($this->getCourse($eCourse['id']));
		$this->assertEquals(['Course::deleteSheets'], array_column($fw->get(), 1), 'la formation ne se supprime pas');
		$this->assertTrue($this->getCourse($eCourse['id'])->notEmpty(), 'la formation reste');
		$this->assertTrue(DateLib::getById($eDateOther['id'])->notEmpty(), 'ses autres dates restent');
		$this->assertEquals(1, DateSheetLib::countByDate($eDate), 'sa feuille reste');

		$cCourse = new \Collection([$this->getCourse($eCourse['id'])]);
		DateSheetLib::fillCountByCourse($cCourse);
		$this->assertEquals(1, $cCourse->first()['sheets'], 'le compte que la roue lit');

		[$eCourseEmpty] = $this->seedCourse(quiz: FALSE, first: FALSE);
		$this->createDate($eCourseEmpty, 2);

		$cCourse = new \Collection([$this->getCourse($eCourseEmpty['id'])]);
		DateSheetLib::fillCountByCourse($cCourse);
		$this->assertEquals(0, $cCourse->first()['sheets'], 'une formation sans feuille');

		$fw = new \FailWatch();
		CourseLib::delete($this->getCourse($eCourseEmpty['id']));
		$this->assertEquals([], array_column($fw->get(), 1), 'sans feuille, la formation se supprime');
		$this->assertTrue($this->getCourse($eCourseEmpty['id'])->empty(), 'supprimée');

	}

	private function prepareSheets(Date $eDate): Date {

		$eDate = $this->prepareDate($eDate);
		$eDate['sheets'] = DateSheetLib::countByDate($eDate);

		return $eDate;

	}

	/**
	 * Le chemin de `page/dateSheet.p.php` après le contrôle de l'envoi : le nom construit, puis le
	 * dépôt. Rend les noms des refus et la feuille.
	 */
	private function upload(Date $eDate, string $filename, string $content): array {

		$fw = new \FailWatch();

		$eDate = DateLib::getById($eDate['id']);

		$eDateSheet = new DateSheet([
			'date' => $eDate,
			'course' => $eDate['course'],
		]);

		$eDateSheet->build(DateSheetLib::getPropertiesCreate(), [
			'name' => AttachmentLib::getNameByFilename($filename)
		], new \PropertyList('create'));

		if($fw->ko() === FALSE) {
			DateSheetLib::upload($eDateSheet, $content);
		}

		if($eDateSheet->offsetExists('hash')) {
			$this->hashesStored[] = $eDateSheet['hash'];
		}

		return [array_column($fw->get(), 1), $eDateSheet];

	}

	private function getMediaStatuses(array $hashes): array {

		return \media\Media::model()
			->select('hash', 'status')
			->whereHash('IN', $hashes)
			->getCollection(index: 'hash')
			->getColumn('status', index: 'hash');

	}

	private function getPdf(): string {
		return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
	}

	private function getPng(): string {

		$image = new \Imagick();
		$image->newImage(40, 20, 'white');
		$image->setImageFormat('png');

		return $image->getImageBlob();

	}

	/**
	 * Un JPEG de 60 × 30 dont l'EXIF, écrit à la main faute d'outil qui le fasse, porte une latitude
	 * et une orientation « tourner de 90° » : ce qu'enregistre un téléphone tenu debout.
	 */
	private function getJpegWithGps(): string {

		$image = new \Imagick();
		$image->newImage(60, 30, 'green');
		$image->setImageFormat('jpeg');

		$jpeg = $image->getImageBlob();

		$ifd0 = 8;
		$gps = $ifd0 + 2 + 2 * 12 + 4;
		$latitude = $gps + 2 + 2 * 12 + 4;

		$tiff = "II*\0".pack('V', $ifd0);
		$tiff .= pack('v', 2).pack('vvVvv', 0x0112, 3, 1, 6, 0).pack('vvVV', 0x8825, 4, 1, $gps).pack('V', 0);
		$tiff .= pack('v', 2).pack('vvVa4', 0x0001, 2, 2, "N\0").pack('vvVV', 0x0002, 5, 3, $latitude).pack('V', 0);
		$tiff .= pack('VVVVVV', 48, 1, 51, 1, 30, 1);

		$exif = "Exif\0\0".$tiff;

		return "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

	}

}
?>
