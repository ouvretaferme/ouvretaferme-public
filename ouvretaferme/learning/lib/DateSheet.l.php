<?php
namespace learning;

/**
 * La feuille signée d'une date en présentiel (lot L8e) : les photos et les PDF de la feuille
 * d'émargement signée, dix au plus par date.
 *
 * Le fichier vit dans `base/media`, sous son propre type `learning-date-sheet`, et la ligne ne porte
 * que son hash. Un type à part et non celui des pièces jointes de leçon : chaque type a son
 * nettoyage de nuit, qui efface les hashes que sa table ne porte plus, et celui des pièces jointes
 * effacerait les feuilles. Retirer une ligne ne touche pas au média, que ce nettoyage
 * (`LearningDateSheetAiLib::clean()`) laisse partir.
 *
 * Une feuille garde la formation de la suppression (`CourseLib::delete()`) et la date aussi
 * (`DateLib::delete()`) : une signature est la preuve d'une présence, que l'effacement d'une
 * formation ne doit pas emporter sans qu'on l'ait voulu.
 */
class DateSheetLib extends DateSheetCrud {

	/**
	 * Le nom vient du fichier déposé et ne se renomme pas : aucune modification.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getByDate(Date $eDate): \Collection {

		return DateSheet::model()
			->select(DateSheet::getSelection())
			->whereDate($eDate)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	public static function countByDate(Date $eDate): int {

		return DateSheet::model()
			->whereDate($eDate)
			->count();

	}

	/**
	 * Pose `sheets` sur chaque formation, le nombre de fichiers signés de toutes ses dates, que la
	 * roue lit pour griser « Supprimer ». Une requête groupée pour toute la liste.
	 */
	public static function fillCountByCourse(\Collection $cCourse): void {

		if($cCourse->empty()) {
			return;
		}

		$cDateSheetCount = DateSheet::model()
			->select([
				'course',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse('IN', $cCourse)
			->group('course')
			->getCollection(index: 'course');

		foreach($cCourse as $eCourse) {
			$eCourse['sheets'] = $cDateSheetCount->offsetExists($eCourse['id']) ? $cDateSheetCount[$eCourse['id']]['count'] : 0;
		}

	}

	/**
	 * Dépose un fichier de la feuille signée, dont le nom est construit, dans les règles des pièces
	 * jointes de leçon (`AttachmentLib::upload()`) : le type se lit dans le contenu, le poids est
	 * borné, une image perd ses métadonnées. Le plafond se compte sous le verrou de la date, comme
	 * les places à l'inscription : deux dépôts simultanés ne passent pas le dixième fichier.
	 */
	public static function upload(DateSheet $e, string $content): void {

		$e->expects(['date', 'course', 'name']);

		if(strlen($content) > \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024) {
			\Fail::log('media\\attachmentSize');
			return;
		}

		$fileType = \media\UtilLib::detectFileType($content);

		if($fileType === NULL) {
			DateSheet::fail('file.type');
			return;
		}

		if($fileType !== 'pdf') {

			// La signature seule a désigné une image : un contenu tronqué ou abîmé ne se révèle qu'à
			// la lecture par Imagick, et se refuse alors comme un type inconnu.
			try {
				$content = AttachmentLib::stripMetadata($content);
			} catch(\ImagickException) {
				DateSheet::fail('file.type');
				return;
			}

		}

		DateSheet::model()->beginTransaction();

			DateLib::lock($e['date']);

			if(self::countByDate($e['date']) >= DateSheet::MAX_BY_DATE) {
				DateSheet::model()->commit();
				DateSheet::fail('tooMany');
				return;
			}

			$hash = NULL;
			new \media\LearningDateSheetAiLib()->send(new DateSheet(), $hash, $content, $fileType);

			$e['hash'] = $hash;

			self::create($e);

		DateSheet::model()->commit();

	}

}
?>
