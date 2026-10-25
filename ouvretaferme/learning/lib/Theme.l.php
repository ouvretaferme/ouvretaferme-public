<?php
namespace learning;

/**
 * Les thématiques du catalogue. Elles sont communes à toutes les formations, celles de l'équipe
 * comme celles des groupes.
 */
class ThemeLib extends ThemeCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'icon', 'color'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'icon', 'color'];
	}

	/**
	 * Chaque thématique porte ses sous-thématiques dans l'ordre (`cSubtheme`, indexées par leur id) :
	 * le formulaire d'une formation, le filtre du catalogue et l'API les lisent avec elle.
	 */
	public static function getAll(): \Collection {

		return Theme::model()
			->select(Theme::getSelection() + [
				'cSubtheme' => Subtheme::model()
					->select(['id', 'name', 'theme', 'position'])
					->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('theme', index: 'id')
			])
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Pose sur chaque thématique le nombre de formations qui la portent (`courses`), toutes
	 * formations confondues : c'est ce que la fusion et la suppression déplaceraient.
	 */
	public static function fillCourses(\Collection $cTheme): void {

		if($cTheme->empty()) {
			return;
		}

		$cCourseCount = Course::model()
			->select([
				'theme',
				'courses' => new \Sql('COUNT(*)', 'int')
			])
			->whereTheme('IN', $cTheme)
			->group('theme')
			->getCollection(index: 'theme');

		foreach($cTheme as $eTheme) {
			$eTheme['courses'] = $cCourseCount[$eTheme['id']]['courses'] ?? 0;
		}

	}

	public static function create(Theme $e): void {

		$e['position'] = Theme::model()->count();

		parent::create($e);

	}

	public static function updatePositions(array $ids): void {

		$cTheme = Theme::model()
			->select('id')
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cTheme->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cTheme->getIds()) !== []
		) {
			Theme::fail('positionsIncomplete');
			return;
		}

		Theme::model()->beginTransaction();

			foreach($cTheme as $eTheme) {

				$eTheme['position'] = $positions[$eTheme['id']];

				Theme::model()
					->select('position')
					->update($eTheme);

			}

		Theme::model()->commit();

	}

	/**
	 * Les formations de la thématique passent à celle qui la reçoit, puis elle disparaît avec ses
	 * sous-thématiques. Les formations déplacées perdent la leur, qui n'appartient pas à la
	 * thématique qui les reçoit (`CourseLib::update()`).
	 */
	public static function merge(Theme $e, Theme $eThemeTarget): void {

		if($eThemeTarget->empty()) {
			Theme::fail('mergeTargetMissing');
			return;
		}

		if($e['id'] === $eThemeTarget['id']) {
			Theme::fail('mergeSelf');
			return;
		}

		Theme::model()->beginTransaction();

			$cCourse = Course::model()
				->select(['id'])
				->whereTheme($e)
				->getCollection();

			foreach($cCourse as $eCourse) {

				$eCourse['theme'] = $eThemeTarget;

				CourseLib::update($eCourse, ['theme']);

			}

			SubthemeLib::deleteByTheme($e);

			parent::delete($e);

			self::reorder();

		Theme::model()->commit();

	}

	/**
	 * Une thématique qui porte encore des formations ne se supprime pas : elle se fusionne. Ses
	 * sous-thématiques partent avec elle, aucune formation ne pouvant les porter.
	 */
	public static function delete(Theme $e): void {

		// Le refus se pose avant la transaction : un `return` sous une transaction ouverte laisse le
		// compteur à 1 et le COMMIT ne part jamais.
		if(Course::model()->whereTheme($e)->exists()) {
			Theme::fail('deleteUsed');
			return;
		}

		Theme::model()->beginTransaction();

			SubthemeLib::deleteByTheme($e);

			parent::delete($e);

			self::reorder();

		Theme::model()->commit();

	}

	/**
	 * Les positions renumérotées à partir de zéro, après un retrait. `create()` pose
	 * `position = Theme::model()->count()` : un trou laissé au milieu ferait naître la thématique
	 * suivante à égalité de position avec une autre.
	 */
	public static function reorder(): void {

		$position = 0;

		foreach(self::getAll() as $eTheme) {

			Theme::model()->update($eTheme, [
				'position' => $position++
			]);

		}

	}

}
?>
