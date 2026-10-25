<?php
namespace sequence;

class Crop extends CropElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => ['name', 'fqn', 'vignette'],
			'cSlice' => SliceLib::delegateByCrop(),
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function format(string $property, array $options = []): ?string {

		switch($property) {

			case 'yieldExpected' :
				return \sequence\CropUi::getYield($this, 'yieldExpected', 'mainUnit', $options);

			default :
				return parent::format($property, $options);

		}

	}

	public static function properties(\PropertyList $l): void {

		$spacing = function(float|int|null &$value): bool {

			$this->expects(['distance']);

			if($this['distance'] !== Crop::SPACING) {
				$value = NULL;
			}

			return TRUE;

		};

		$l->property('plant')
			->replaceCheck(function(\plant\Plant $ePlant): bool {

				return (
					$ePlant->empty() === FALSE and
					\plant\Plant::model()
						->select('structure')
						->get($ePlant) and
					$ePlant->canRead()
				);

			});

		$l->property('seedlingSeeds')
			->addAfterCast('seedling', function(?int &$seeds): bool {

				$this->expects(['seedling']);

				if(in_array($this['seedling'], [Crop::SOWING, Crop::YOUNG_PLANT]) === FALSE) {
					$seeds = NULL;
				} else {
					$seeds = (int)$seeds;
				}

				return TRUE;

			});

		$l->property('density')
			->addAfterCast('distance', function(?float &$density): bool {

				$this->expects(['distance']);

				if($this['distance'] !== Crop::DENSITY) {
					$density = NULL;
				}

				return TRUE;

			});

		$l->property('rowSpacing')
			->addAfterCast('spacing', $spacing)
			->addAfterCast('use', function(?float &$rowSpacing): bool {

				switch($this['sequence']['use']) {

					case Sequence::BED :
						$this['rowSpacing'] = NULL;
						return TRUE;

					case Sequence::BLOCK :
						return Crop::model()->check('rowSpacing', $rowSpacing);

				}

			});

		$l->property('rows')
			->addAfterCast('spacing', $spacing)
			->addAfterCast('use', function(?int &$rows): bool {

				switch($this['sequence']['use']) {

					case Sequence::BED :
						return Crop::model()->check('rows', $rows);

					case Sequence::BLOCK :
						$this['rows'] = NULL;
						return TRUE;

				}

			});

		$l->property('plantSpacing')
			->addAfterCast('spacing', $spacing);

		$l->property('varieties')
			->replaceCheck(function(?array $varieties, \PropertyList $l, string $wrapper) {

				if($l->isBuilt('plant') === FALSE) {
					return TRUE;
				}

				$this['cSlice'] = SliceLib::prepare($this, $varieties, $wrapper);

				return TRUE;

			});

		$l->property('actions')
			->replaceSet(function(?array $actions): bool {

				$this->expects(['seedling']);

				$this['actions'] = [];

				$check = function($action) use($actions) {

					$year = (int)($actions[$action]['year'] ?? 0);
					$week = ($actions[$action]['week'] ?? NULL);

					if(
						\Filter::check('week', $week) and
						in_array($year, [-1, 0, 1])
					) {
						$this['actions'][$action] = [week_number($week), $year];
					}

				};

				switch($this['seedling']) {

					case Crop::SOWING :
						$check->call($this, ACTION_SEMIS_DIRECT);
						break;

					case Crop::YOUNG_PLANT :
						$check->call($this, ACTION_SEMIS_PEPINIERE);
						$check->call($this, ACTION_PLANTATION);
						break;

					case Crop::YOUNG_PLANT_BOUGHT :
						$check->call($this, ACTION_PLANTATION);
						break;

				}

				return TRUE;

			});

	}

	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		$this->expects([
			'sequence' => ['use']
		]);

		parent::build($properties, $input, $l);

	}

}
?>
