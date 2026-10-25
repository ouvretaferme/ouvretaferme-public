<?php
namespace plant;

class ForecastLib extends ForecastCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_merge(['season', 'plant', 'unit'], self::getPropertiesUpdate());
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		// Private avant Pro pour les vérifications dans build()
		return ['quantity', 'privatePrice', 'privatePart', 'proPrice', 'proPart'];
	}

	public static function getByStructure(\structure\Structure $eStructure, int $season, \Collection $cccCultivation): \Collection {

		$ccForecast = self::getReadOnlyByStructure($eStructure, $season, Forecast::getSelection() + [
			'cCultivation' => fn() => new \Collection(),
			'harvestExpectedOrTarget' => fn() => 0
		]);

		// Pas de prévisionnel, on copie celui de l'année dernière
		if($ccForecast->empty()) {

			$maxSeason = Forecast::model()
				->whereStructure($eStructure)
				->getValue(new \Sql('MAX(season)', 'int'));

			if($maxSeason !== NULL) {

				$cForecast = Forecast::model()
					->select(ForecastElement::getSelection())
					->whereStructure($eStructure)
					->whereSeason($maxSeason)
					->getCollection();

				foreach($cForecast as $eForecast) {

					$eForecast['id'] = NULL;
					$eForecast['season'] = $season;

					if($maxSeason > $season) {
						$eForecast['quantity'] = NULL;
					}

					Forecast::model()
						->option('add-ignore')
						->insert($eForecast);

				}

				$ccForecast = self::getReadOnlyByStructure($eStructure, $season, Forecast::getSelection() + [
						'cCultivation' => fn() => new \Collection(),
						'harvestExpectedOrTarget' => fn() => 0
				]);

			}

		}

		foreach($cccCultivation as $ccCultivation) {

			$ePlant = $ccCultivation->first()->first()['plant'];

			foreach($ccCultivation as $unit => $cCultivation) {

				$eForecast = $ccForecast[$ePlant['id']][$unit] ?? new Forecast();

				if($eForecast->empty()) {

					$eForecastBefore = Forecast::model()
						->select([
							'quantity',
							'privatePrice', 'privatePart',
							'proPrice', 'proPart'
						])
						->whereStructure($eStructure)
						->wherePlant($ePlant)
						->whereUnit($unit)
						->sort(['season' => SORT_DESC])
						->get();

					$eForecast->merge([
						'structure' => $eStructure,
						'season' => $season,
						'plant' => $ePlant,
						'unit' => $unit,
					]);

					$eForecast->merge($eForecastBefore);

					Forecast::model()
						->option('add-ignore')
						->insert($eForecast);

					$ccForecast[$ePlant['id']] ??= new \Collection();
					$ccForecast[$ePlant['id']][$unit] = $eForecast;

				}

				$eForecast['cCultivation'] = $cCultivation;
				$eForecast['harvestExpectedOrTarget'] = self::calculateHarvestExpectedOrTarget($cCultivation);

			}

		}

		foreach($ccForecast as $cForecast) {

			foreach($cForecast as $eForecast) {

				$eForecast['area'] = round($eForecast['cCultivation']->reduce(fn($eCultivation, $n) => $n + ($eCultivation['series']['area'] ?? $eCultivation['series']['areaTarget'] ?? 0), 0));
				$eForecast['salesForecast'] = ($eForecast['quantity'] === NULL) ? 0.0 : $eForecast->getSales($eForecast['quantity']);
				$eForecast['salesSeries'] = $eForecast->getSales($eForecast['harvestExpectedOrTarget']);

				foreach($eForecast['cCultivation'] as $eCultivation) {
					$eCultivation['sales'] = $eForecast->getSales((int)$eCultivation['harvestExpectedOrTarget']);
				}

			}

		}

		\series\CultivationLib::orderByPlant($ccForecast);

		return $ccForecast;

	}

	/**
	 * La récolte attendue, ou visée à défaut, de chaque culture d'une espèce dans une unité, posée sur la
	 * culture, et leur somme arrondie culture par culture : c'est le volume que le « Total planifié »
	 * valorise, ici comme dans le prévisionnel financier.
	 */
	public static function calculateHarvestExpectedOrTarget(\Collection $cCultivation): float {

		$harvestExpectedOrTarget = 0.0;

		foreach($cCultivation as $eCultivation) {
			$eCultivation['harvestExpectedOrTarget'] = $eCultivation->getHarvestExpectedOrTarget();
			$harvestExpectedOrTarget += round($eCultivation['harvestExpectedOrTarget'] ?? 0);
		}

		return $harvestExpectedOrTarget;

	}

	public static function getReadOnlyByStructure(\structure\Structure $eStructure, int $season, ?array $select = NULL): \Collection {

		return Forecast::model()
			->select($select ?? Forecast::getSelection())
			->whereStructure($eStructure)
			->whereSeason($season)
			->getCollection(NULL, NULL, ['plant', 'unit']);

	}

	public static function countCultivations(Forecast $e): int {

		$e->expects(['structure', 'season', 'plant', 'unit']);

		return \series\Cultivation::model()
			->whereStructure($e['structure'])
			->whereSeason($e['season'])
			->wherePlant($e['plant'])
			->whereMainUnit($e['unit'])
			->count();

	}

	public static function create(Forecast $e): void {

		try {

			parent::create($e);

		} catch(\DuplicateException) {
			Forecast::fail('plant.duplicate');
		}

	}

	public static function update(Forecast $e, array $properties): void {

		if(in_array('proPart', $properties) === TRUE and in_array('privatePart', $properties) === FALSE) {

			$e['privatePart'] = 100 - $e['proPart'];
			$properties[] = 'privatePart';

		}

		if(in_array('privatePart', $properties) === TRUE and in_array('proPart', $properties) === FALSE) {

			$e['proPart'] = 100 - $e['privatePart'];
			$properties[] = 'proPart';

		}

		parent::update($e, $properties);

	}

	public static function delete(Forecast $e): void {

		$e->expects(['id']);

		if(self::countCultivations($e) > 0) {
			Forecast::fail('deleteUsed');
			return;
		}

		Forecast::model()->delete($e);

	}

}
?>
