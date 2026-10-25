<?php
namespace plant;

class Plant extends PlantElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'family' => ['name', 'fqn'],
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->empty() === FALSE and
			$this['structure']->canWrite()
		);

	}

	public function canUpdate(): bool {

		return (
			$this->isOwner() or
			$this['structure']->canManage()
		);

	}

	public function canDelete(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	/**
	 * Une copie du référentiel (`fqn` posé) ne naît qu'avec sa structure, et c'est par son `fqn`
	 * qu'elle reçoit ce que le référentiel partage (étiquettes, classement communautaire des
	 * critères) : la structure qui n'en veut plus la désactive. Le référentiel lui-même, sans structure,
	 * n'appartient à aucune structure qui puisse le supprimer.
	 */
	public function acceptDelete(): bool {

		$this->expects(['structure', 'fqn']);

		return (
			$this['structure']->notEmpty() and
			$this['fqn'] === NULL
		);

	}

	/**
	 * L'espèce entre-t-elle dans le périmètre de la notation des variétés ?
	 *
	 * La campagne ne couvre que le légume : sur le reste (arbre fruitier, aromatique,
	 * engrais vert), les critères n'ont rien à décrire.
	 */
	public function acceptRating(): bool {

		$this->expects(['usageVegetable']);

		return $this['usageVegetable'];

	}

	public function isOwner(): bool {

		$this->expects(['structure', 'fqn']);

		return (
			$this['structure']->empty() === FALSE and
			$this['structure']->canManage() and
			$this['fqn'] === NULL
		);

	}

	public static function properties(\PropertyList $l): void {

		// Le cast par défaut passe la valeur au `(float)` de PHP, qui lit « 6,5 » comme 6 et « 1e3 » comme 1000 avant tout contrôle : la valeur se lit ici, avant lui, et sans `Filter::check('float')`, qui refuserait « .5 » qu'un champ `number` poste.
		$l->property('crateWeight')
			->addBeforeCast('decimal', function(mixed &$crateWeight): bool {

				if($crateWeight === NULL or $crateWeight === '') {
					return TRUE;
				}

				if(is_string($crateWeight) === FALSE) {
					return FALSE;
				}

				$crateWeight = str_replace(',', '.', $crateWeight);

				return (preg_match('/^-?(\d+\.?\d*|\.\d+)$/', $crateWeight) === 1);

			})
			->addAfterCheck('missing', fn(?float $crateWeight): bool => $crateWeight !== NULL);

		$l->property('family')
			->replaceCheck(function(Family $eFamily): bool {

				return (
					$eFamily->empty() or
					Family::model()->exists($eFamily)
				);

			});

		$l->property('criteria')
			->addAfterCast('tooMany', function(array &$criteria): bool {

				$criteria = array_values(array_unique(array_map('intval', (array)($criteria ?? []))));

				return (count($criteria) <= PlantSetting::PLANT_CRITERIA_MAX);

			})
			->addAfterCheck('known', function(array $criteria): bool {

				return Criterion::model()
					->whereId('IN', $criteria)
					->count() === count($criteria);

			});

	}

}
?>
