<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Les fichiers à télécharger d'une leçon : qui les lit et qui les écrit, ce que le dépôt refuse,
 * l'ordre, ce que les suppressions emportent, et le nettoyage de nuit des médias du type.
 *
 * Le dépôt écrit sur le disque du stockage, que le `rollBack()` de la fixture n'annule pas : chaque
 * test efface les fichiers qu'il a posés.
 */
class AttachmentTest extends LearningFixtureTest {

	private array $hashesStored = [];

	public function tearDown(): void {

		foreach($this->hashesStored as $hash) {
			\media\MediaSetting::$mediaDriver->delete(new \media\LearningAttachmentAiUi()->getBasenameByHash($hash));
		}

		$this->hashesStored = [];

		parent::tearDown();

	}

	/**
	 * Le fichier se lit comme sa leçon, brouillon compris pour l'équipe, et ne s'écrit que par
	 * l'équipe de la formation.
	 */
	public function testRights(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eAttachmentPublished = $this->getAttachment($this->upload($this->createLessonIn(Course::PUBLISHED), 'Plan.pdf', $this->getPdf())[1]['id']);
		$eAttachmentDraft = $this->getAttachment($this->upload($this->createLessonIn(Course::DRAFT), 'Brouillon.pdf', $this->getPdf())[1]['id']);

		$this->assertTrue($eAttachmentPublished->assertRead(), 'équipe, lecture');
		$this->assertTrue($eAttachmentDraft->assertRead(), 'équipe, lecture d\'un brouillon');
		$this->assertTrue($eAttachmentPublished->canWrite(), 'équipe, écriture');
		$this->assertTrue($eAttachmentPublished->acceptWrite(), 'formation d\'équipe, écriture acceptée');

		$this->actAs($this->eUserReader);

		$this->assertTrue($eAttachmentPublished->assertRead(), 'compte, lecture d\'une formation publiée');
		$this->assertFalse($eAttachmentDraft->assertRead(), 'compte, lecture d\'un brouillon');
		$this->assertFalse($eAttachmentPublished->canWrite(), 'compte, écriture');

		$this->actAs(new \user\User());

		$this->assertFalse($eAttachmentPublished->assertRead(), 'visiteur, formation avec compte');
		$this->assertFalse($eAttachmentPublished->canWrite(), 'visiteur, écriture');

	}

	/**
	 * Un PDF et une image se déposent sous le nom du fichier sans son extension, rangés dans l'ordre
	 * du dépôt, et leur média porte le type `learning-attachment`, actif.
	 */
	public function testUpload(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		[$failsPdf, $eAttachmentPdf] = $this->upload($eLesson, 'Plan de la ferme école 2027.pdf', $this->getPdf());
		[$failsPng, $eAttachmentPng] = $this->upload($eLesson, 'rotation.v2.png', $this->getPng());

		$this->assertEquals([], $failsPdf, 'PDF accepté');
		$this->assertEquals([], $failsPng, 'image accepté');

		$cAttachment = AttachmentLib::getByLesson($eLesson);

		$this->assertEquals([$eAttachmentPdf['id'], $eAttachmentPng['id']], $cAttachment->getIds(), 'rangés dans l\'ordre du dépôt');
		$this->assertEquals(['Plan de la ferme école 2027', 'rotation.v2'], $cAttachment->getColumn('name'), 'nom sans la seule extension');
		$this->assertEquals([0, 1], $cAttachment->getColumn('position'), 'positions');

		$this->assertEquals('f', $eAttachmentPdf['hash'][19], 'hash d\'un PDF');
		$this->assertEquals('p', $eAttachmentPng['hash'][19], 'hash d\'une image PNG');

		$ui = new \media\LearningAttachmentAiUi();

		$this->assertEquals($this->getPdf(), file_get_contents(\storage\DriverLib::directory().'/'.$ui->getBasenameByHash($eAttachmentPdf['hash'])), 'le PDF est stocké tel qu\'il a été déposé');
		$this->assertTrue(is_file(\storage\DriverLib::directory().'/'.$ui->getBasenameByHash($eAttachmentPng['hash'])), 'l\'image est stockée');

		$cMedia = \media\Media::model()
			->select('hash', 'type', 'status')
			->whereHash('IN', [$eAttachmentPdf['hash'], $eAttachmentPng['hash']])
			->getCollection();

		$this->assertEquals(2, $cMedia->count(), 'un média par fichier');
		$this->assertEquals(['learning-attachment'], array_values(array_unique($cMedia->getColumn('type'))), 'type des médias');
		$this->assertEquals([\media\Media::ACTIVE], array_values(array_unique($cMedia->getColumn('status'))), 'médias actifs');

	}

	/**
	 * Le type se lit dans le contenu, le poids est borné, et un refus ne laisse ni ligne ni média.
	 */
	public function testUploadRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		$medias = \media\Media::model()->whereType('learning-attachment')->count();

		[$fails] = $this->upload($eLesson, 'faux.pdf', 'Un texte qui se dit PDF.');
		$this->assertEquals(['Attachment::file.type'], $fails, 'contenu qui n\'est ni un PDF ni une image');

		[$fails] = $this->upload($eLesson, 'abimee.jpg', "\xFF\xD8\xFF\xE0\x00\x10");
		$this->assertEquals(['Attachment::file.type'], $fails, 'JPEG abîmé');

		[$fails] = $this->upload($eLesson, 'abimee.png', "\x89PNG\r\n\x1a\n\x00\x00");
		$this->assertEquals(['Attachment::file.type'], $fails, 'PNG abîmé');

		[$fails] = $this->upload($eLesson, 'lourd.pdf', '%PDF-'.str_repeat('0', \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024 - 4));
		$this->assertEquals(['attachmentSize'], $fails, 'un octet de trop');

		[$fails] = $this->upload($eLesson, '.pdf', $this->getPdf());
		$this->assertEquals(['Attachment::name.check'], $fails, 'nom vide');

		$this->assertEquals(0, AttachmentLib::getByLesson($eLesson)->count(), 'aucune ligne');
		$this->assertEquals($medias, \media\Media::model()->whereType('learning-attachment')->count(), 'aucun média');

		[$fails] = $this->upload($eLesson, 'juste.pdf', '%PDF-'.str_repeat('0', \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024 - 5));
		$this->assertEquals([], $fails, 'au plafond exactement');

	}

	/**
	 * La photo est servie sans le lieu de la prise de vue, et debout : l'orientation déclarée dans
	 * ses métadonnées est passée dans les pixels avant qu'elles partent.
	 */
	public function testUploadStripsMetadata(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		$content = $this->getJpegWithGps();

		$source = new \Imagick();
		$source->readImageBlob($content);
		$this->assertEquals('48/1,51/1,30/1', $source->getImageProperty('exif:GPSLatitude'), 'la photo déposée porte une position');

		[$fails, $eAttachment] = $this->upload($eLesson, 'Photo.jpg', $content);
		$this->assertEquals([], $fails, 'photo acceptée');

		$stored = new \Imagick(\storage\DriverLib::directory().'/'.new \media\LearningAttachmentAiUi()->getBasenameByHash($eAttachment['hash']));

		$this->assertEquals([], $stored->getImageProperties('exif:*'), 'aucune métadonnée EXIF servie');
		$this->assertEquals([30, 60], [$stored->getImageWidth(), $stored->getImageHeight()], 'l\'orientation est passée dans les pixels');
		$this->assertEquals('JPEG', $stored->getImageFormat(), 'toujours un JPEG');

	}

	public function testUploadTooMany(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		for($position = 0; $position < Attachment::MAX_BY_LESSON; $position++) {
			$this->upload($eLesson, 'Fichier '.$position.'.pdf', $this->getPdf());
		}

		$this->assertEquals(Attachment::MAX_BY_LESSON, AttachmentLib::getByLesson($eLesson)->count(), 'jusqu\'au plafond');

		$medias = \media\Media::model()->whereType('learning-attachment')->count();

		[$fails] = $this->upload($eLesson, 'De trop.pdf', $this->getPdf());
		$this->assertEquals(['Attachment::tooMany'], $fails, 'un fichier de trop');
		$this->assertEquals(Attachment::MAX_BY_LESSON, AttachmentLib::getByLesson($eLesson)->count(), 'aucune ligne de plus');
		$this->assertEquals($medias, \media\Media::model()->whereType('learning-attachment')->count(), 'aucun média de plus');

		AttachmentLib::delete($this->getAttachment(AttachmentLib::getByLesson($eLesson)->first()['id']));

		[$fails] = $this->upload($eLesson, 'Remplaçant.pdf', $this->getPdf());
		$this->assertEquals([], $fails, 'une place libérée se reprend');

	}

	public function testPositions(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		$ids = [];

		foreach(['Un', 'Deux', 'Trois'] as $name) {
			$ids[] = $this->upload($eLesson, $name.'.pdf', $this->getPdf())[1]['id'];
		}

		$fw = new \FailWatch();
		AttachmentLib::updatePositions($eLesson, array_reverse($ids));
		$this->assertFalse($fw->ko(), 'ordre complet accepté');

		$this->assertEquals(array_reverse($ids), AttachmentLib::getByLesson($eLesson)->getIds(), 'ordre inversé');

		$fw = new \FailWatch();
		AttachmentLib::updatePositions($eLesson, [$ids[0], $ids[1]]);
		$this->assertEquals(['Attachment::positionsIncomplete'], array_column($fw->get(), 1), 'ordre incomplet');

		$eLessonOther = $this->createLessonIn(Course::DRAFT);
		$idOther = $this->upload($eLessonOther, 'Autre.pdf', $this->getPdf())[1]['id'];

		$fw = new \FailWatch();
		AttachmentLib::updatePositions($eLesson, [$ids[0], $ids[1], $idOther]);
		$this->assertEquals(['Attachment::positionsIncomplete'], array_column($fw->get(), 1), 'un fichier d\'une autre leçon');

		$this->assertEquals(array_reverse($ids), AttachmentLib::getByLesson($eLesson)->getIds(), 'un ordre refusé n\'écrit rien');

		AttachmentLib::delete($this->getAttachment($ids[1]));
		$idLast = $this->upload($eLesson, 'Quatre.pdf', $this->getPdf())[1]['id'];

		$this->assertEquals([$ids[2], $ids[0], $idLast], AttachmentLib::getByLesson($eLesson)->getIds(), 'un dépôt après un retrait se range en dernier');
		$this->assertEquals([0, 1, 2], AttachmentLib::getByLesson($eLesson)->getColumn('position'), 'les positions sont resserrées');

	}

	/**
	 * Retirer un fichier retire la ligne et laisse le média à la nuit ; supprimer la leçon ou la
	 * formation emporte ses fichiers.
	 */
	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		$eAttachment = $this->getAttachment($this->upload($eLesson, 'Un.pdf', $this->getPdf())[1]['id']);
		$this->upload($eLesson, 'Deux.pdf', $this->getPdf());

		AttachmentLib::delete($eAttachment);

		$this->assertEquals(1, AttachmentLib::getByLesson($eLesson)->count(), 'la ligne est retirée');
		$this->assertEquals(\media\Media::ACTIVE, \media\Media::model()->select('status')->whereHash($eAttachment['hash'])->get()['status'], 'le média attend la nuit');

		$eLessonDeleted = $this->getLesson($eLesson['id']);
		$this->createLesson($this->getChapter($eLessonDeleted['chapter']['id']), 'Leçon qui reste', 10);

		$fw = new \FailWatch();
		LessonLib::delete($eLessonDeleted);
		$this->assertFalse($fw->ko(), 'leçon supprimée');

		$this->assertEquals(0, Attachment::model()->whereLesson($eLesson)->count(), 'la leçon emporte ses fichiers');

		$eLessonCourse = $this->createLessonIn(Course::DRAFT);
		$this->upload($eLessonCourse, 'Trois.pdf', $this->getPdf());

		CourseLib::delete($this->getCourse($eLessonCourse['course']['id']));

		$this->assertEquals(0, Attachment::model()->whereCourse($eLessonCourse['course'])->count(), 'la formation emporte les fichiers de ses leçons');

	}

	/**
	 * Le nettoyage de nuit passe tous les médias en attente, puis chaque type réactive ceux qu'il
	 * porte : un hash qu'une ligne porte encore, fût-ce une copie sur une autre leçon, reste ; un
	 * hash que plus rien ne porte reste en attente, donc part.
	 */
	public function testClean(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonIn(Course::DRAFT);

		$eAttachmentKept = $this->getAttachment($this->upload($eLesson, 'Gardé.pdf', $this->getPdf())[1]['id']);
		$eAttachmentShared = $this->getAttachment($this->upload($eLesson, 'Partagé.pdf', $this->getPdf())[1]['id']);
		$eAttachmentOrphan = $this->getAttachment($this->upload($eLesson, 'Orphelin.pdf', $this->getPdf())[1]['id']);

		$eLessonCopy = $this->createLessonIn(Course::DRAFT);

		$eAttachmentCopy = new Attachment([
			'lesson' => $eLessonCopy,
			'course' => $eLessonCopy['course'],
			'name' => 'Copie',
			'hash' => $eAttachmentShared['hash'],
		]);

		AttachmentLib::create($eAttachmentCopy);

		AttachmentLib::delete($eAttachmentShared);
		AttachmentLib::delete($eAttachmentOrphan);

		$hashes = [$eAttachmentKept['hash'], $eAttachmentShared['hash'], $eAttachmentOrphan['hash']];

		\media\Media::model()
			->whereHash('IN', $hashes)
			->update(['status' => \media\Media::SEARCHING]);

		$this->assertTrue(new \media\LearningAttachmentAiLib()->clean(), 'le nettoyage aboutit');

		$statuses = \media\Media::model()
			->select('hash', 'status')
			->whereHash('IN', $hashes)
			->getCollection(index: 'hash')
			->getColumn('status', index: 'hash');

		$this->assertEquals(\media\Media::ACTIVE, $statuses[$eAttachmentKept['hash']], 'hash porté par sa ligne');
		$this->assertEquals(\media\Media::ACTIVE, $statuses[$eAttachmentShared['hash']], 'hash porté par la seule copie');
		$this->assertEquals(\media\Media::SEARCHING, $statuses[$eAttachmentOrphan['hash']], 'hash que plus rien ne porte');

	}

	/**
	 * Une leçon neuve dans un module neuf d'une formation d'équipe au statut donné.
	 */
	private function createLessonIn(string $status): Lesson {

		$eCourse = $this->createCourse(new \structure\Structure(), $status, Course::PRIVATE);

		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module du test'), 'Leçon du test', 10);

		return $this->getLesson($eLesson['id']);

	}

	/**
	 * Le chemin de `page/attachment.p.php` après le contrôle de l'envoi : le nom construit, puis le
	 * dépôt. Rend les noms des refus et la pièce jointe.
	 */
	private function upload(Lesson $eLesson, string $filename, string $content): array {

		$fw = new \FailWatch();

		$eAttachment = new Attachment([
			'lesson' => $eLesson,
			'course' => $eLesson['course'],
		]);

		$eAttachment->build(AttachmentLib::getPropertiesCreate(), [
			'name' => AttachmentLib::getNameByFilename($filename)
		], new \PropertyList('create'));

		if($fw->ko() === FALSE) {
			AttachmentLib::upload($eAttachment, $content);
		}

		if($eAttachment->offsetExists('hash')) {
			$this->hashesStored[] = $eAttachment['hash'];
		}

		return [array_column($fw->get(), 1), $eAttachment];

	}

	private function getAttachment(int $id): Attachment {

		return Attachment::model()
			->select(Attachment::getSelection())
			->whereId($id)
			->get();

	}

	private function getPdf(): string {
		return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
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

	private function getPng(): string {

		$image = new \Imagick();
		$image->newImage(4, 4, 'white');
		$image->setImageFormat('png');

		return $image->getImageBlob();

	}

}
?>
