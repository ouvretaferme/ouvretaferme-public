<?php
namespace structure;

class SurveyLib extends SurveyCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_diff(Survey::model()->getProperties(), ['id', 'structure', 'createdAt']);
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate($origin);
	}

	public static function getByStructure(Structure $eStructure): Survey {

		return Survey::model()
			->select(Survey::getSelection())
			->whereStructure($eStructure)
			->get();

	}

	public static function create(Survey $e): void {

		try {
			Survey::model()->insert($e);
		} catch(\DuplicateException) {
			Survey::fail('structure.duplicate');
		}

	}

	public static function getAll(): \Collection {

		return Survey::model()
			->select(Survey::getSelection() + [
				'structure' => StructureElement::getSelection()
			])
			->sort([
				'id' => SORT_DESC
			])
			->getCollection();

	}

	public static function count(): int {
		return Survey::model()->count();
	}

	/**
	 * Le nombre de réponses par fréquence, univers par univers, chaque fréquence présente même à zéro.
	 */
	public static function countByFrequency(): array {

		$counts = [];

		foreach(Survey::UNIVERSES as $universe) {

			$counts[$universe] = array_fill_keys(Survey::model()->getPropertyEnum($universe), 0);

			$cSurvey = Survey::model()
				->select([$universe, 'count' => new \Sql('COUNT(*)', 'int')])
				->group($universe)
				->getCollection();

			foreach($cSurvey as $eSurvey) {
				$counts[$universe][$eSurvey[$universe]] = $eSurvey['count'];
			}

		}

		return $counts;

	}

	/**
	 * Le nombre de réponses par jour, du premier jour de réponse à aujourd'hui, les jours sans réponse à zéro.
	 */
	public static function countByDay(): array {

		$cSurvey = Survey::model()
			->select(['createdAt', 'count' => new \Sql('COUNT(*)', 'int')])
			->group('createdAt')
			->sort(['createdAt' => SORT_ASC])
			->getCollection();

		if($cSurvey->empty()) {
			return [];
		}

		$counts = [];

		foreach($cSurvey as $eSurvey) {
			$counts[$eSurvey['createdAt']] = $eSurvey['count'];
		}

		$days = [];
		$cursor = new \DateTime(array_key_first($counts));
		$today = new \DateTime(\Clock::date());

		while($cursor <= $today) {
			$day = $cursor->format('Y-m-d');
			$days[$day] = $counts[$day] ?? 0;
			$cursor->modify('+1 day');
		}

		return $days;

	}

	public static function getStructuresToAnswer(\user\User $eUser): \Collection {

		$cStructure = StructureLib::getByUser($eUser, Structure::PRODUCER)->find(fn($eStructure) => (
			$eStructure->canManage() and
			$eStructure->acceptSurvey()
		));

		if($cStructure->empty()) {
			return $cStructure;
		}

		$structureIds = Survey::model()
			->select('structure')
			->whereStructure('IN', $cStructure)
			->getCollection()
			->getColumnCollection('structure')
			->getIds();

		return $cStructure->find(fn($eStructure) => in_array($eStructure['id'], $structureIds) === FALSE);

	}

	public static function existsByStructure(Structure $eStructure): bool {

		return Survey::model()
			->whereStructure($eStructure)
			->exists();

	}

}
?>
