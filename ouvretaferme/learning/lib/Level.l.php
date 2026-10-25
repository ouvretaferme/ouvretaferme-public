<?php
namespace learning;

/**
 * Les niveaux du catalogue. Leur ordre est un sens, du plus simple au plus avancé : le catalogue
 * et ses filtres le suivent.
 */
class LevelLib extends LevelCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getAll(): \Collection {

		return Level::model()
			->select(Level::getSelection())
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	public static function fillCourses(\Collection $cLevel): void {

		if($cLevel->empty()) {
			return;
		}

		$cCourseCount = Course::model()
			->select([
				'level',
				'courses' => new \Sql('COUNT(*)', 'int')
			])
			->whereLevel('IN', $cLevel)
			->group('level')
			->getCollection(index: 'level');

		foreach($cLevel as $eLevel) {
			$eLevel['courses'] = $cCourseCount[$eLevel['id']]['courses'] ?? 0;
		}

	}

	public static function create(Level $e): void {

		$e['position'] = Level::model()->count();

		parent::create($e);

	}

	public static function updatePositions(array $ids): void {

		$cLevel = Level::model()
			->select('id')
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cLevel->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cLevel->getIds()) !== []
		) {
			Level::fail('positionsIncomplete');
			return;
		}

		Level::model()->beginTransaction();

			foreach($cLevel as $eLevel) {

				$eLevel['position'] = $positions[$eLevel['id']];

				Level::model()
					->select('position')
					->update($eLevel);

			}

		Level::model()->commit();

	}

	/**
	 * Les formations du niveau passent à celui qui le reçoit, puis il disparaît.
	 */
	public static function merge(Level $e, Level $eLevelTarget): void {

		if($eLevelTarget->empty()) {
			Level::fail('mergeTargetMissing');
			return;
		}

		if($e['id'] === $eLevelTarget['id']) {
			Level::fail('mergeSelf');
			return;
		}

		Level::model()->beginTransaction();

			$cCourse = Course::model()
				->select(['id'])
				->whereLevel($e)
				->getCollection();

			foreach($cCourse as $eCourse) {

				$eCourse['level'] = $eLevelTarget;

				CourseLib::update($eCourse, ['level']);

			}

			parent::delete($e);

			self::reorder();

		Level::model()->commit();

	}

	public static function delete(Level $e): void {

		// Le refus se pose avant la transaction : un `return` sous une transaction ouverte laisse le
		// compteur à 1 et le COMMIT ne part jamais.
		if(Course::model()->whereLevel($e)->exists()) {
			Level::fail('deleteUsed');
			return;
		}

		Level::model()->beginTransaction();

			parent::delete($e);

			self::reorder();

		Level::model()->commit();

	}

	/**
	 * Les positions renumérotées à partir de zéro, après un retrait. `create()` pose
	 * `position = Level::model()->count()` : un trou laissé au milieu ferait naître le niveau
	 * suivant à égalité de position avec un autre.
	 */
	public static function reorder(): void {

		$position = 0;

		foreach(self::getAll() as $eLevel) {

			Level::model()->update($eLevel, [
				'position' => $position++
			]);

		}

	}

}
?>
