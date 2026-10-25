<?php
namespace learning;

/**
 * Les fichiers à télécharger d'une leçon : des PDF et des images, rangés à la souris, renommés,
 * retirés.
 *
 * Le fichier vit dans `base/media`, sous le type `learning-attachment`, et la ligne ne porte que
 * son hash. Retirer une ligne ne touche pas au média : une copie de formation peut porter le même
 * hash, et c'est le nettoyage de nuit (`LearningAttachmentAiLib::clean()`) qui le laisse partir
 * quand plus aucune ligne ne le porte.
 */
class AttachmentLib extends AttachmentCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getByLesson(Lesson $eLesson): \Collection {

		return Attachment::model()
			->select(['id', 'name', 'hash', 'position'])
			->whereLesson($eLesson)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Lue pour la copie seule : `CourseLib::getClone()` reprend chaque colonne du modèle, et la leçon
	 * et la formation y sont remplacées par celles de la copie, donc leurs ids suffisent.
	 */
	public static function getByCourse(Course $eCourse): \Collection {

		return Attachment::model()
			->select(Attachment::model()->getProperties())
			->whereCourse($eCourse)
			->sort(['lesson' => SORT_ASC, 'position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Le nom du fichier déposé, sans son extension, devient le nom affiché, coupé à la longueur que
	 * le schéma accepte : un nom trop long ne refuse pas le dépôt, il se renomme ensuite.
	 */
	public static function getNameByFilename(string $filename): string {

		[, $max] = Attachment::model()->getPropertyRange('name');

		return mb_substr(pathinfo($filename, PATHINFO_FILENAME), 0, $max);

	}

	/**
	 * Dépose le fichier d'une pièce jointe dont le nom est construit.
	 *
	 * Le type se lit dans le contenu (`UtilLib::detectFileType()`), jamais dans le nom ni dans ce que
	 * le navigateur annonce, comme pour les pièces jointes de facturation électronique
	 * (`invoicing\EventLib::storeAttachment()`). Un PDF est stocké tel qu'il a été déposé, une image
	 * perd ses métadonnées (`stripMetadata()`) mais n'est ni recadrée ni réduite.
	 *
	 * Le poids se juge sur le contenu et non sur ce que PHP rapporte de l'envoi, pour qu'un appel qui
	 * ne passe pas par un formulaire soit borné de même.
	 */
	public static function upload(Attachment $e, string $content): void {

		$e->expects(['lesson', 'course', 'name']);

		if(strlen($content) > \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024) {
			\Fail::log('media\\attachmentSize');
			return;
		}

		$fileType = \media\UtilLib::detectFileType($content);

		if($fileType === NULL) {
			Attachment::fail('file.type');
			return;
		}

		// Compté sans verrou, que le cadriciel n'offre pas : deux dépôts simultanés à un fichier du
		// plafond peuvent le passer d'un, ce qu'un auteur seul sur sa leçon ne fait pas.
		if(Attachment::model()->whereLesson($e['lesson'])->count() >= Attachment::MAX_BY_LESSON) {
			Attachment::fail('tooMany');
			return;
		}

		if($fileType !== 'pdf') {

			// La signature seule a désigné une image : un contenu tronqué ou abîmé ne se révèle qu'à
			// la lecture par Imagick, et se refuse alors comme un type inconnu.
			try {
				$content = self::stripMetadata($content);
			} catch(\ImagickException) {
				Attachment::fail('file.type');
				return;
			}

		}

		Attachment::model()->beginTransaction();

			$hash = NULL;
			new \media\LearningAttachmentAiLib()->send(new Attachment(), $hash, $content, $fileType);

			$e['hash'] = $hash;

			self::create($e);

		Attachment::model()->commit();

	}

	/**
	 * Une photo de téléphone porte souvent le lieu où elle a été prise, que le lecteur n'a pas à
	 * recevoir : toutes les métadonnées partent, sauf le profil de couleur. L'orientation vit parmi
	 * elles, elle est donc d'abord appliquée aux pixels, sans quoi la photo se servirait couchée.
	 * Un JPEG réencodé garde la qualité lue à l'ouverture, Imagick la reprenant par défaut.
	 */
	public static function stripMetadata(string $content): string {

		$resource = new \Imagick();
		$resource->readImageBlob($content);

		\storage\ImageLib::rotateImage($resource);

		$profiles = $resource->getImageProfiles('icc', TRUE);

		$resource->stripImage();

		if($profiles !== []) {
			$resource->profileImage('icc', $profiles['icc']);
		}

		return $resource->getImageBlob();

	}

	/**
	 * Le nouveau fichier se range en dernier : les positions d'une leçon vont de 0 à n - 1, que
	 * `delete()` resserre après chaque retrait.
	 */
	public static function create(Attachment $e): void {

		$e->expects(['lesson', 'course', 'hash']);

		Attachment::model()->beginTransaction();

			$e['position'] = Attachment::model()
				->whereLesson($e['lesson'])
				->count();

			parent::create($e);

			CourseLib::touch($e['course']);

		Attachment::model()->commit();

	}

	public static function update(Attachment $e, array $properties): void {

		$e->expects(['course']);

		Attachment::model()->beginTransaction();

			parent::update($e, $properties);

			CourseLib::touch($e['course']);

		Attachment::model()->commit();

	}

	public static function updatePositions(Lesson $eLesson, array $ids): void {

		$cAttachment = Attachment::model()
			->select('id')
			->whereLesson($eLesson)
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cAttachment->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cAttachment->getIds()) !== []
		) {
			Attachment::fail('positionsIncomplete');
			return;
		}

		Attachment::model()->beginTransaction();

			foreach($cAttachment as $eAttachment) {

				$eAttachment['position'] = $positions[$eAttachment['id']];

				Attachment::model()
					->select('position')
					->update($eAttachment);

			}

		Attachment::model()->commit();

	}

	/**
	 * Les fichiers qui suivaient remontent d'un rang : les positions restent 0 à n - 1, ce qui range
	 * un nouveau dépôt en dernier par le seul compte des lignes (`create()`). L'écriture de masse
	 * garde un tableau de valeurs : un élément porteur d'un id la bornerait à une ligne.
	 */
	public static function delete(Attachment $e): void {

		$e->expects(['course', 'lesson', 'position']);

		Attachment::model()->beginTransaction();

			parent::delete($e);

			Attachment::model()
				->whereLesson($e['lesson'])
				->wherePosition('>', $e['position'])
				->update([
					'position' => new \Sql('position - 1')
				]);

			CourseLib::touch($e['course']);

		Attachment::model()->commit();

	}

	/**
	 * Les fichiers d'une leçon qu'on supprime, sans dater la formation, pour la même raison que
	 * `LessonLib::deleteWithCourse()`, qui l'appelle et tient la transaction.
	 */
	public static function deleteByLesson(Lesson $eLesson): void {

		$cAttachment = Attachment::model()
			->select('id')
			->whereLesson($eLesson)
			->getCollection();

		foreach($cAttachment as $eAttachment) {
			parent::delete($eAttachment);
		}

	}

}
?>
