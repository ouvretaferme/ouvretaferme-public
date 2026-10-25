<?php
namespace plant;

class Variety extends VarietyElement {

	const UNIT_SEED = 'seed';
	const UNIT_GRAM = 'gram';

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => PlantElement::getSelection(),
			'supplierSeed' => ['name'],
			'supplierPlant' => ['name']
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->notEmpty() and
			$this['structure']->canManage()
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('structure')
			->addAfterCheck('manage', function(\structure\Structure $eStructure): bool {

				return (
					(\user\ConnectionLib::getOnline()->isAdmin() and $eStructure->empty()) or
					$eStructure->canManage()
				);

			});

		$l->property('plant')
			->replaceCheck(function(Plant $ePlant): bool {

				$this->expects(['structure']);

				return Plant::model()
					->whereStructure($this['structure'])
					->exists($ePlant);

			});

		$l->property('supplierSeed')
			->replaceCheck(function(\work\Supplier $eSupplier): bool {

				$this->expects(['structure']);

				return (
					$eSupplier->empty() or
					\work\Supplier::model()
						->whereStructure($this['structure'])
						->exists($eSupplier)
				);

			});

		$l->property('supplierPlant')
			->replaceCheck(function(\work\Supplier $eSupplier): bool {

				$this->expects(['structure']);

				return (
					$eSupplier->empty() or
					\work\Supplier::model()
						->whereStructure($this['structure'])
						->exists($eSupplier)
				);

			});

		$l->property('unit')
			->replaceCheck(function(?string $unit): bool {

				return ($unit === NULL or in_array($unit, [Variety::UNIT_SEED, Variety::UNIT_GRAM], TRUE));

			})
			->replaceSet(function(?string $unit): bool {

				$this['unit'] = $unit ?? Variety::UNIT_SEED;

				return TRUE;

			});

		$l->property('stock')
			// Le stock se saisit en graines ou en grammes et se range en graines. La
			// conversion remplace le cast plutôt que de le suivre : convertir un entier
			// déjà arrondi ferait tomber à zéro un demi-gramme de tomate, qui vaut
			// pourtant cent cinquante graines.
			->replaceCast(function(&$value, \PropertyList $l): bool {

				// ⚠️ `unit` dit dans quelle unité lire la valeur : sans elle, il n'y a rien à
				// décider. Elle manque quand `unit.check` vient de la refuser — `unit.set` n'a
				// alors pas tourné et la propriété n'est pas posée. Sans ce saut, la lecture
				// ci-dessous lève un warning, et un warning dans une réponse d'API en casse le
				// JSON avant que le refus n'arrive au client. Mesuré le 2026-09-05 sur un PUT
				// portant `unit: kilogram`.
				$l->skipIfInvalid('unit');

				if($this['unit'] === Variety::UNIT_GRAM) {

					$this->expects(['weightSeed1000']);

					if($this['weightSeed1000'] === NULL) {
						return FALSE;
					}

					// Une graine ne se partage pas : la conversion s'arrête à la graine entière inférieure,
					// le cast en entier refusant une valeur décimale au lieu de la tronquer
					if(is_numeric($value)) {
						$value = floor($value * 1000 / $this['weightSeed1000']);
					}

				}

				Variety::model()->cast('stock', $value);

				return TRUE;

			});

		$l->property('tags')
			->addAfterCast('plant', function(mixed &$tags): bool {

				$this->expects(['plant']);

				$tags = array_map('intval', (array)($tags ?? []));

				return array_diff($tags, TagLib::getByPlant($this['plant'])->getIds()) === [];

			});

		$l->property('ratingList')
			->replaceCheck(function(mixed $value, \PropertyList $l): bool {

				$this->expects(['structure', 'plant']);

				$this['cVarietyRating'] = VarietyRatingAiLib::buildFromInput($this, $l->input);

				return TRUE;

			});

	}

}
?>
