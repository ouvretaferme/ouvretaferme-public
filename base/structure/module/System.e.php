<?php
namespace structure;

class System extends SystemElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Une liste d'ateliers vide s'accepte au partage d'une ferme, jamais dans sa configuration.
	 *
	 * « Je ne déclare pas d'atelier » et « je supprime tous mes ateliers » sont deux gestes
	 * différents, et ce qui les sépare est l'état de la ferme avant l'écriture :
	 * ProductionLib::replaceCollection() efface avant de recréer, donc accepter la liste vide sur
	 * une ferme qui en porte reviendrait à les supprimer d'un clic.
	 */
	protected function acceptEmptyProductions(\PropertyList $l): bool {

		if($l->for !== 'share') {
			return FALSE;
		}

		$this->expects(['structure']);

		return ProductionLib::getByStructure($this['structure'])->empty();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('placeLngLat')
			->addAfterCast('place', function(?array &$placeLngLat) {

				$this->expects(['place']);

				if($this['place'] !== NULL) {

					if(
						$placeLngLat === NULL or
						System::model()->check('placeLngLat', $placeLngLat) === FALSE
					) {
						System::fail('place.check');
					}

				} else {
					$placeLngLat = NULL;
				}

				return TRUE;

			});

		$l->property('sellingLong')
			->addAfterCheck('repartition', function(?int &$sellingLong, \PropertyList $l): bool {

				$l->skipIfInvalid(['sellingDirect', 'sellingSemi']);

				$total = ($this['sellingDirect'] ?? 0) + ($this['sellingSemi'] ?? 0) + ($sellingLong ?? 0);

				// Aucune répartition renseignée
				if($total === 0) {

					$this['sellingDirect'] = NULL;
					$this['sellingSemi'] = NULL;
					$sellingLong = NULL;

					return TRUE;

				}

				return ($total === 100);

			});

		$l->property('productionsList')
			->replaceCheck(function(mixed $value, \PropertyList $l): bool {

				$fw = new \FailWatch();

				$this['cProduction'] = ProductionLib::buildFromInput($this['structure'], $_POST, FALSE);

				if($fw->ko()) {
					Production::fail('createCollectionError');
				} else if($this['cProduction']->empty() and $this->acceptEmptyProductions($l) === FALSE) {
					Production::fail('general.createCollectionEmpty');
				}

				return TRUE;

			})
			->addAfterCheck('duplicate', function(mixed $value, \PropertyList $l): bool {

				$l->skipIfInvalid('productionsList');

				$fqn = $this['cProduction']->getColumn('fqn');

				return count(array_count_values($fqn)) === count($fqn);

			});

	}

}
?>