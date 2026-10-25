<?php
namespace learning;

/**
 * Les sous-thématiques, rangées dans leur thématique. Leur place se compte à l'intérieur de la
 * thématique, de zéro : `create()` pose la place suivante, et chaque retrait renumérote.
 */
class SubthemeLib extends SubthemeCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['theme', 'name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getByTheme(Theme $eTheme): \Collection {

		if($eTheme->empty()) {
			return new \Collection();
		}

		// Le nom vient juste après l'id : une liste de choix lit le libellé d'un élément dans sa
		// deuxième propriété (`FormUi::getOptionValue()`).
		return Subtheme::model()
			->select(['id', 'name', 'theme', 'position'])
			->whereTheme($eTheme)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Pose sur chaque sous-thématique le nombre de formations qui la portent (`courses`) : la
	 * colonne du panneau et le choix de l'entrée « Supprimer » le lisent.
	 */
	public static function fillCourses(\Collection $cSubtheme): void {

		if($cSubtheme->empty()) {
			return;
		}

		$cCourseCount = Course::model()
			->select([
				'subtheme',
				'courses' => new \Sql('COUNT(*)', 'int')
			])
			->whereSubtheme('IN', $cSubtheme)
			->group('subtheme')
			->getCollection(index: 'subtheme');

		foreach($cSubtheme as $eSubtheme) {
			$eSubtheme['courses'] = $cCourseCount[$eSubtheme['id']]['courses'] ?? 0;
		}

	}

	public static function create(Subtheme $e): void {

		$e->expects(['theme']);

		$e['position'] = Subtheme::model()
			->whereTheme($e['theme'])
			->count();

		parent::create($e);

	}

	/**
	 * L'ordre posté doit nommer toutes les sous-thématiques de la thématique, et elles seules : une
	 * liste partielle, venue d'un panneau ouvert avant un ajout, laisserait deux lignes à la même
	 * place.
	 */
	public static function updatePositions(Theme $eTheme, array $ids): void {

		$cSubtheme = Subtheme::model()
			->select('id')
			->whereTheme($eTheme)
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cSubtheme->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cSubtheme->getIds()) !== []
		) {
			Subtheme::fail('positionsIncomplete');
			return;
		}

		Subtheme::model()->beginTransaction();

			foreach($cSubtheme as $eSubtheme) {

				$eSubtheme['position'] = $positions[$eSubtheme['id']];

				Subtheme::model()
					->select('position')
					->update($eSubtheme);

			}

		Subtheme::model()->commit();

	}

	/**
	 * Une sous-thématique que des formations portent encore ne se supprime pas : elles en changent
	 * d'abord, depuis leurs réglages.
	 */
	public static function delete(Subtheme $e): void {

		$e->expects(['theme']);

		// Le refus se pose avant la transaction : un `return` sous une transaction ouverte laisse le
		// compteur à 1 et le COMMIT ne part jamais.
		if(Course::model()->whereSubtheme($e)->exists()) {
			Subtheme::fail('deleteUsed');
			return;
		}

		Subtheme::model()->beginTransaction();

			parent::delete($e);

			self::reorder($e['theme']);

		Subtheme::model()->commit();

	}

	/**
	 * Les sous-thématiques d'une thématique qui disparaît, fusionnée ou supprimée. Aucune formation
	 * ne les porte plus : une formation ne porte qu'une sous-thématique de sa thématique, et la
	 * fusion lui a donné une autre thématique, ce qui a vidé sa sous-thématique (`CourseLib::update()`).
	 */
	public static function deleteByTheme(Theme $eTheme): void {

		$cSubtheme = Subtheme::model()
			->select('id')
			->whereTheme($eTheme)
			->getCollection();

		foreach($cSubtheme as $eSubtheme) {
			parent::delete($eSubtheme);
		}

	}

	/**
	 * Les places renumérotées à partir de zéro dans la thématique, après un retrait : `create()`
	 * pose la place suivante d'après le nombre de lignes, qu'un trou ferait tomber sur une place
	 * prise.
	 */
	public static function reorder(Theme $eTheme): void {

		$position = 0;

		foreach(self::getByTheme($eTheme) as $eSubtheme) {

			Subtheme::model()->update($eSubtheme, [
				'position' => $position++
			]);

		}

	}

}
?>
