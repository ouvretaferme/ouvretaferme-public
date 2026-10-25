<?php
namespace map;

class Decor extends DecorElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Ce qui clôt l'emprise : une zone ou un jardin, jamais les deux.
	 */
	public function hasEnclosure(): bool {

		$this->expects(['zone', 'plot']);
		return ($this['zone']->notEmpty() or $this['plot']->notEmpty());

	}

	/**
	 * `center` est posé par la page avant `build()` (`DecorLib::getCenter()`), une fois pour toutes les
	 * étapes `near` : chaque point d'un tracé le relirait sinon en base.
	 *
	 * L'ordre de `DecorLib::getPropertiesCreate()` porte les étapes qui lisent une autre propriété :
	 * `building` et `object` avant `kit`, `trace` avant `width`, `diameter` et `laying`, `zone` avant `plot`, et `trace`, `zone` et `plot` avant `model`, `hedge`
	 * et `gate`.
	 * Une telle étape s'abandonne quand la propriété qu'elle lit a été refusée (`skipIfInvalid()`) :
	 * le refus est déjà dit, et la propriété refusée n'est jamais posée sur l'élément.
	 */
	public static function properties(\PropertyList $l): void {

		// Un bâtiment que la structure a composé (`DecorBuilding`), relu borné par elle : `DecorLib::create()` le relit sous
		// verrou avant d'écrire, pour qu'une suppression concurrente ne laisse pas une pose sans bâtiment
		$l->property('building')
			->addAfterCheck('owned', function(DecorBuilding &$eDecorBuilding): bool {

				if($eDecorBuilding->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return DecorBuilding::model()
					->select(['id'])
					->whereStructure($this['structure'])
					->get($eDecorBuilding);

			});

		// Un élément que la structure a importé (`DecorObject`), relu borné par elle, comme un bâtiment, avec sa famille, que suit
		// le kit qui le pose
		$l->property('object')
			->addAfterCheck('owned', function(DecorObject &$eDecorObject): bool {

				if($eDecorObject->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return DecorObject::model()
					->select(['family'])
					->whereStructure($this['structure'])
					->get($eDecorObject);

			});

		$l->property('kit')
			->addAfterCheck('format', function(string $kit): bool {
				return (preg_match('/^[a-z0-9-]+$/', $kit) === 1);
			})
			// Le kit `batiment` n'a pas de modèle à lui : il pose un bâtiment de la structure, et lui seul en pose un
			->addAfterCheck('building', function(string $kit, \PropertyList $l): bool {

				$l->skipIfInvalid('building');

				return (($kit === 'batiment') === $this['building']->notEmpty());

			})
			// Les kits `objet-*` posent un élément importé de la structure, et seulement de la rangée que le kit désigne
			->addAfterCheck('object', function(string $kit, \PropertyList $l): bool {

				$l->skipIfInvalid('object');

				if($this['object']->empty()) {
					return (in_array($kit, DecorObject::KITS, TRUE) === FALSE);
				}

				$this['object']->expects(['family']);

				return ($kit === DecorObject::KITS[$this['object']['family']]);

			})
			->addAfterCheck('irrigation', function(string $kit): bool {

				$this->expects(['structure']);

				return (in_array($kit, MapSetting::DECOR_IRRIGATION_KITS, TRUE) === FALSE or MapSetting::isIrrigationEnabled($this['structure']));

			});

		$l->property('layer')
			->addAfterCheck('owned', function(DecorLayer &$eDecorLayer): bool {

				if($eDecorLayer->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				// Le type du calque dit ce que pèse un tracé qu'on y pose (`DecorLib::getTraceCost()`)
				return DecorLayer::model()
					->select(['type'])
					->whereStructure($this['structure'])
					->get($eDecorLayer);

			})
			->addAfterCheck('irrigation', function(DecorLayer $eDecorLayer): bool {

				$this->expects(['structure']);

				return ($eDecorLayer->empty() or $eDecorLayer['type'] !== DecorLayer::IRRIGATION or MapSetting::isIrrigationEnabled($this['structure']));

			});

		$l->property('coordinates')
			->addAfterCheck('drawn', function(?array $coordinates): bool {

				$this->expects(['center']);
				return ($this['center'] !== NULL);

			})
			->addAfterCheck('near', function(?array $coordinates): bool {
				return DecorLib::isNear($this['center'], $coordinates);
			});

		// Un tracé se poste en JSON, comme `coordinates` : le type `point` se décode par le cadriciel,
		// mais un `json` y est converti en tableau sans être décodé
		$l->property('trace')
			->replaceCast(function(mixed &$trace): bool {

				if($trace === '') {
					$trace = NULL;
				} else if(is_string($trace)) {
					$trace = json_decode($trace, TRUE) ?? $trace;
				}

				return TRUE;

			})
			// La forme se juge ici et non dans la conversion, pour que tout refus porte `trace.check`
			->replaceCheck(function(mixed &$trace): bool {

				if($trace === NULL) {
					return TRUE;
				}

				if(
					is_array($trace) === FALSE or
					array_is_list($trace) === FALSE or
					count($trace) < 2 or
					count($trace) > MapSetting::DECOR_TRACE_POINTS
				) {
					return FALSE;
				}

				foreach($trace as $key => $point) {

					if(
						is_array($point) === FALSE or
						array_is_list($point) === FALSE or
						count($point) !== 2 or
						is_numeric($point[0]) === FALSE or
						is_numeric($point[1]) === FALSE
					) {
						return FALSE;
					}

					$trace[$key] = [(float)$point[0], (float)$point[1]];

					if(is_finite($trace[$key][0]) === FALSE or is_finite($trace[$key][1]) === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			})
			->addAfterCheck('near', function(?array $trace): bool {

				// Sans centre, `coordinates.drawn` a déjà refusé l'élément. L'étape passe plutôt que de
				// s'abandonner : un abandon laisserait le tracé hors de l'élément, que lit `model.fence`
				if($trace === NULL or $this['center'] === NULL) {
					return TRUE;
				}

				foreach($trace as $point) {

					if(DecorLib::isNear($this['center'], $point) === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			})
			// Un tracé dont tous les points sont confondus n'a ni longueur ni direction : la scène ne saurait où le poser
			->addAfterCheck('collapsed', function(?array $trace): bool {

				if($trace === NULL) {
					return TRUE;
				}

				foreach($trace as $point) {

					if($point !== $trace[0]) {
						return TRUE;
					}

				}

				return FALSE;

			})
			// Un tracé enregistré ne s'efface pas : sans ses points, `DecorLib::getPropertiesPosition()` le prendrait pour un objet
			// ponctuel, qu'on déplace, tourne et retaille
			->addAfterCheck('kept', function(?array $trace): bool {
				return ($trace !== NULL or $this->exists() === FALSE or $this['trace'] === NULL);
			});

		// Une largeur est celle d'un chemin ou d'un cours d'eau, qui se dessine toujours
		$l->property('width')
			->addAfterCheck('traced', function(?int $width, \PropertyList $l): bool {

				$l->skipIfInvalid('trace');

				return ($width === NULL or $this['trace'] !== NULL);

			});

		// Un diamètre et une pose sont ceux d'un tuyau, qui se dessine toujours
		$l->property('diameter')
			->addAfterCheck('pipe', function(?int $diameter, \PropertyList $l): bool {

				$l->skipIfInvalid('trace');

				return ($diameter === NULL or $this['trace'] !== NULL);

			});

		$l->property('laying')
			->addAfterCheck('pipe', function(?string $laying, \PropertyList $l): bool {

				$l->skipIfInvalid('trace');

				return ($laying === NULL or $this['trace'] !== NULL);

			});

		$l->property('zone')
			->addAfterCheck('owned', function(Zone &$eZone): bool {

				if($eZone->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return Zone::model()
					->select(['id', 'coordinates'])
					->whereStructure($this['structure'])
					->get($eZone);

			})
			->addAfterCheck('outlined', function(Zone $eZone): bool {
				return ($eZone->empty() or $eZone['coordinates'] !== NULL);
			})
			// `alone`, ici comme sur `plot`, ne se juge qu'à la création : une mise à jour qui construirait l'emprise y trouverait le décor
			// lui-même et le refuserait. Le jardin qui remplit une parcelle se clôt sur le contour de celle-ci, si bien qu'une clôture
			// de l'un est déjà celle de l'autre
			->addAfterCheck('alone', function(Zone $eZone): bool {

				if($eZone->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				$ePlotFill = Plot::model()
					->select('id')
					->whereStructure($this['structure'])
					->whereZone($eZone)
					->whereZoneFill(TRUE)
					->get();

				return (
					Decor::model()->whereStructure($this['structure'])->whereZone($eZone)->exists() === FALSE and
					($ePlotFill->empty() or Decor::model()->whereStructure($this['structure'])->wherePlot($ePlotFill)->exists() === FALSE)
				);

			});

		$l->property('plot')
			->addAfterCheck('owned', function(Plot &$ePlot): bool {

				if($ePlot->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return Plot::model()
					->select(['id', 'zoneFill', 'coordinates', 'zone' => ['coordinates']])
					->whereStructure($this['structure'])
					->get($ePlot);

			})
			->addAfterCheck('outlined', function(Plot $ePlot): bool {
				return ($ePlot->empty() or $ePlot->canBedLine());
			})
			->addAfterCheck('alone', function(Plot $ePlot): bool {

				if($ePlot->empty()) {
					return TRUE;
				}

				$this->expects(['structure']);

				return (
					Decor::model()->whereStructure($this['structure'])->wherePlot($ePlot)->exists() === FALSE and
					($ePlot['zoneFill'] === FALSE or Decor::model()->whereStructure($this['structure'])->whereZone($ePlot['zone'])->exists() === FALSE)
				);

			})
			// L'emprise est fixée à la création, et `exclusive` ne se juge qu'alors : un changement d'emprise
			// est un retrait suivi d'une pose
			->addAfterCheck('exclusive', function(Plot $ePlot, \PropertyList $l): bool {

				$l->skipIfInvalid('zone');

				return ($ePlot->empty() or $this['zone']->empty());

			});

		$l->property('model')
			->addAfterCheck('fence', function(?string $model, \PropertyList $l): bool {

				$l->skipIfInvalid(['trace', 'zone', 'plot']);

				return ($model === NULL or $this['trace'] !== NULL or $this->hasEnclosure());

			});

		$l->property('hedge')
			->addAfterCheck('fence', function(bool $hedge, \PropertyList $l): bool {

				$l->skipIfInvalid(['trace', 'zone', 'plot', 'model']);

				return ($hedge === FALSE or $this->hasEnclosure());

			})
			// Une emprise se clôt d'un modèle, d'une haie, ou des deux
			->addAfterCheck('enclosure', function(bool $hedge): bool {
				return ($this->hasEnclosure() === FALSE or $hedge or $this['model'] !== NULL);
			});

		$l->property('gate')
			->addAfterCheck('fence', function(?float $gate, \PropertyList $l): bool {

				$l->skipIfInvalid(['zone', 'plot']);

				return ($gate === NULL or $this->hasEnclosure());

			});

	}

}
?>
