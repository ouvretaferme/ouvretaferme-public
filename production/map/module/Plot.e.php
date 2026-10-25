<?php
namespace map;

class Plot extends PlotElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => ['name'],
			'zone' => ['name', 'coordinates', 'area', 'seasonFirst', 'seasonLast'],
		];

	}

	/**
	 * Le type d'un jardin devant son nom : une liste de planches ou de jardins mêle le plein champ et l'abri.
	 * Texte brut : les appelants qui écrivent du HTML encodent le résultat.
	 */
	public function getLabel(): string {

		$this->expects(['mode', 'name']);

		return match($this['mode']) {
			Plot::GREENHOUSE => s("Abri {value}", $this['name']),
			Plot::OPEN_FIELD => s("Jardin {value}", $this['name'])
		};

	}

	public function getArea(): string {

		if($this['area'] > 1000) {
			return s("{value} ha", \util\TextUi::number($this['area'] / 10000, 2));
		} else {
			return s("{value} m²", $this['area']);
		}

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Des planches posées à même la parcelle sont celles de la parcelle : elles se modifient et se suppriment avec elle
	 * (ZoneLib), jamais pour leur compte. La carte ne leur offre aucun geste, et l'API, qui n'interroge que les accept*, doit
	 * refuser les mêmes.
	 */
	public function acceptUpdate(): bool {

		$this->expects(['zoneFill']);

		return ($this['zoneFill'] === FALSE);

	}

	public function acceptDelete(): bool {

		$this->expects(['zoneFill']);

		return ($this['zoneFill'] === FALSE);

	}

	public function canBedLine(): bool {

		$this->expects([
			'zoneFill',
			'zone' => ['coordinates'],
			'coordinates'
		]);

		return (
			($this['zoneFill'] === TRUE and $this['zone']['coordinates'] !== NULL) or
			($this['zoneFill'] === FALSE and $this['coordinates'] !== NULL)
		);

	}

	/**
	 * Une copie de ce jardin se pose à côté de lui sur la carte (plot:doDuplicate) : seul un jardin qui a son propre contour en
	 * a la forme. Un abri garde ses dimensions, et des planches posées à même la parcelle n'ont pas de forme à elles.
	 */
	public function acceptPlacement(): bool {

		$this->expects(['mode', 'zoneFill', 'coordinates']);

		return (
			$this['mode'] === Plot::OPEN_FIELD and
			$this['zoneFill'] === FALSE and
			$this['coordinates'] !== NULL
		);

	}

	/**
	 * Le contour d'un jardin ou d'un abri se trace sur la carte quand il en a un à lui, dans une parcelle tracée : des
	 * planches posées à même la parcelle n'ont que le sien, et hors d'une parcelle tracée il n'a pas où se poser.
	 */
	public function acceptUpdateShape(): bool {

		$this->expects([
			'zoneFill',
			'zone' => ['coordinates']
		]);

		return ($this['zoneFill'] === FALSE and $this['zone']['coordinates'] !== NULL);

	}

	public function assertUpdateShape(): bool {

		return ($this->canWrite() and $this->acceptUpdateShape());

	}

	/**
	 * La longueur et la largeur d'un abri tracé, lues sur son contour (MapLib::getDimensions()) : celui qui vient d'être construit,
	 * sinon celui qui est enregistré. Sur un abri tracé, des dimensions postées sont remplacées plutôt que refusées, comme la surface
	 * d'un jardin tracé (étape `drawn` de `area`) : un client qui relit un abri puis le réécrit entier renverrait les siennes, et le
	 * contour ne doit plus jamais contredire les dimensions. Un abri sans contour garde les dimensions saisies.
	 * Un contour trop étroit pour un abri (PlotLib::isNarrow()) qui n'a pas bougé garde les dimensions enregistrées : le refus qui
	 * oblige à le redessiner ne tombe que sur un contour qu'on vient de tracer, jamais sur un abri qu'on renomme.
	 */
	protected function getDimensionsDrawn(\PropertyList $l): ?array {

		$this->expects(['mode']);

		if($this['mode'] === Plot::OPEN_FIELD) {
			return NULL;
		}

		if($l->isBuilt('coordinates')) {
			$l->skipIfInvalid('coordinates');
		} else if($l->for === 'create') {
			// Un abri créé sans contour (l'accueil, qui ne trace rien) n'a rien d'enregistré à relire
			return NULL;
		}

		// Une parcelle non tracée n'a pas de carte où tracer : le panneau y offre la saisie (PlotLib::getDimensionsDrawn()), qu'un
		// contour gardé d'un ancien tracé ne doit pas remplacer
		$l->skipIfInvalid('zone');

		$this->expects(['coordinates', 'zone' => ['coordinates']]);

		if($this['coordinates'] === NULL or $this['zone']['coordinates'] === NULL) {
			return NULL;
		}

		$dimensions = MapLib::getDimensions($this['coordinates']);

		if($l->for === 'update' and PlotLib::isNarrow($dimensions)) {

			$this->expects(['id', 'length', 'width']);

			if($this['length'] !== NULL and $this['width'] !== NULL and $this->isShapeKept($l)) {
				return [$this['length'], $this['width']];
			}

		}

		return $dimensions;

	}

	// Le contour construit est celui qui est enregistré, au refermement de l'anneau près, que la base ajoute à l'écriture
	private function isShapeKept(\PropertyList $l): bool {

		if($l->isBuilt('coordinates') === FALSE) {
			return TRUE;
		}

		$coordinatesBefore = Plot::model()
			->select('coordinates')
			->whereId($this['id'])
			->get()['coordinates'];

		return (
			$coordinatesBefore !== NULL and
			MapLib::getVertices(MapLib::castPoints($coordinatesBefore)) === MapLib::getVertices($this['coordinates'])
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('zone')
			// Construite par l'API seule, et à la création seule (cf. PlotLib::getPropertiesCreate). On la vérifie contre la
			// structure du jardin, et non par un simple contrôle de droits : un membre de deux
			// structures pourrait sinon poser un jardin dans la parcelle de l'autre.
			->addAfterCheck('owned', function(Zone $eZone): bool {

				$this->expects(['structure']);

				// Le contour sert à un abri, qui ne tire ses dimensions du sien que dans une parcelle tracée (étape `drawn` de `length`),
				// les saisons aux bornes de celles du jardin (étapes `zone` de `seasonFirst` et `seasonLast`)
				return (
					Zone::model()
						->select(['id', 'structure', 'coordinates', 'seasonFirst', 'seasonLast'])
						->whereStructure($this['structure'])
						->get($eZone)
				);

			});

		$l->property('name')
			->replaceCheck(function(?string $name): bool {

				return ($name !== NULL and Plot::model()->check('name', $name));

			});

		$l->property('mode')
			// En modification, le seul chemin qui construit 'mode' est la bascule couvrir/découvrir
			// — il est hors de getPropertiesUpdate(). Le mode visé s'y déduit de l'état enregistré,
			// rien dans la requête ne le choisit. En création il vient du lien cliqué, on le garde.
			->addAfterCast('toggle', function(&$mode, \PropertyList $l): bool {

				if($l->for === 'update') {

					$mode = match($this['mode']) {
						Plot::GREENHOUSE => Plot::OPEN_FIELD,
						Plot::OPEN_FIELD => Plot::GREENHOUSE
					};

				}

				return TRUE;

			});

		$l->property('decorGreenhouse')
			// Le modèle n'est saisi que sous abri, mais il survit au plein champ : un jardin découvert
			// puis recouvert retrouve le sien. En plein champ, ce qui est posté est donc ignoré et la
			// valeur enregistrée gardée. Sous abri, un champ vide dit que l'abri n'a pas de modèle.
			->addAfterCast('kept', function(&$decorGreenhouse, \PropertyList $l): bool {

				$this->expects(['mode']);

				if($this['mode'] === Plot::OPEN_FIELD) {

					$decorGreenhouse = match($l->for) {
						'create' => NULL,
						'update' => $this['decorGreenhouse']
					};

				}

				return TRUE;

			});

		$l->property('length')
			// Une dimension n'a de sens que sous abri. On l'écarte au lieu de la refuser : un client
			// d'API qui relit un jardin puis le réécrit entier renverrait les dimensions d'avant.
			->addAfterCast('mode', function(&$length): bool {

				$this->expects(['mode']);

				if($this['mode'] === Plot::OPEN_FIELD) {
					$length = NULL;
				}

				return TRUE;

			})
			// Un contour plus étroit que ce qu'admet le schéma ne se mesure pas en abri : c'est lui qui se redessine, ses dimensions
			// remplaçant celles postées. La largeur, jamais plus grande que la longueur, s'arrête alors sans second message (étape
			// `drawn` de `width`)
			->addAfterCast('drawn', function(&$length, \PropertyList $l): bool {

				$dimensions = $this->getDimensionsDrawn($l);

				if($dimensions !== NULL) {

					if(PlotLib::isNarrow($dimensions)) {
						return FALSE;
					}

					$length = $dimensions[0];

				}

				return TRUE;

			})
			->addAfterCheck('required', function(?float $length): bool {

				$this->expects(['mode']);

				return ($this['mode'] === Plot::OPEN_FIELD or $length !== NULL);

			});

		$l->property('width')
			->addAfterCast('mode', function(&$width): bool {

				$this->expects(['mode']);

				if($this['mode'] === Plot::OPEN_FIELD) {
					$width = NULL;
				}

				return TRUE;

			})
			->addAfterCast('drawn', function(&$width, \PropertyList $l): bool {

				$dimensions = $this->getDimensionsDrawn($l);

				if($dimensions !== NULL) {
					$l->skipIfInvalid('length');
					$width = $dimensions[1];
				}

				return TRUE;

			})
			->addAfterCheck('required', function(?float $width): bool {

				$this->expects(['mode']);

				return ($this['mode'] === Plot::OPEN_FIELD or $width !== NULL);

			});

		$l->property('area')
			// Sous abri la surface n'est pas saisie : elle vaut les dimensions de l'abri, construites juste avant
			->addAfterCast('computed', function(&$area, \PropertyList $l): bool {

				$this->expects(['mode']);

				if($this['mode'] === Plot::GREENHOUSE) {

					// Sans dimensions valides il n'y a pas de surface à calculer, et se rabattre
					// sur des valeurs absentes ajouterait un échec parasite au vrai message
					$l->skipIfInvalid(['length', 'width']);

					$area = (int)round($this['length'] * $this['width']);

				}

				return TRUE;

			})
			// En plein champ, un contour posté donne sa surface, comme pour une parcelle (Zone::properties())
			->addAfterCast('drawn', function(&$area, \PropertyList $l): bool {

				$this->expects(['mode']);

				if($this['mode'] === Plot::GREENHOUSE) {
					return TRUE;
				}

				$l->skipIfInvalid('coordinates');

				if($l->isBuilt('coordinates') and $this['coordinates'] !== NULL) {
					$area = MapLib::getArea($this['coordinates']);
				}

				return TRUE;

			})
			// Des planches posées à même la parcelle en ont la surface, que ZoneLib::update() leur reporte : une autre surface les ferait
			// mentir sur leur parcelle, donc la valeur postée est remplacée comme un contour remplace celle d'un jardin, quelle que soit
			// la porte qui construit l'élément. Un jardin créé ne remplit jamais sa parcelle (étape `filled` de `coordinates`).
			->addAfterCast('filled', function(&$area, \PropertyList $l): bool {

				if($l->for === 'create') {
					return TRUE;
				}

				$this->expects(['zoneFill']);

				if($this['zoneFill']) {

					$this->expects(['zone' => ['area']]);

					$area = $this['zone']['area'];

				}

				return TRUE;

			});

		$l->property('coordinates')
			// Même règle que Zone::properties() : le schéma ne juge que des points, et l'absence de contour
			// est la valeur d'un jardin pas encore tracé, sauf sur la route du tracé
			->addAfterCheck('ring', function(?array &$coordinates, \PropertyList $l): bool {

				if($coordinates === NULL) {
					return ($l->for !== 'shape');
				}

				$coordinates = MapLib::castPoints($coordinates);

				return MapLib::isRing($coordinates);

			})
			// Des planches posées à même la parcelle en prennent le contour, comme acceptUpdateShape() le dit au tracé : un
			// contour à elles décalerait leurs traits de ceux de la parcelle (PlotLib::update()), quelle que soit la porte qui
			// construit l'élément. Un jardin créé ne remplit jamais sa parcelle.
			->addAfterCheck('filled', function(?array $coordinates, \PropertyList $l): bool {

				if($coordinates === NULL or $l->for === 'create') {
					return TRUE;
				}

				$this->expects(['zoneFill']);

				return ($this['zoneFill'] === FALSE);

			});

		// Où se pose la copie d'un jardin dessiné (plot:doDuplicate) : un décalage en mètres et un angle, que la copie et les traits de
		// ses planches subissent ensemble (PlotLib::duplicate()). Sans placement, la copie n'a pas de contour
		$l->property('placement')
			->replaceCheck(function(mixed &$placement): bool {

				if($placement === NULL) {
					return TRUE;
				}

				try {
					$placement = json_decode($placement, TRUE, 4, JSON_THROW_ON_ERROR);
				} catch(\Throwable) {
					return FALSE;
				}

				if(
					is_array($placement) === FALSE or
					is_array($placement['shift'] ?? NULL) === FALSE or
					array_is_list($placement['shift']) === FALSE or
					count($placement['shift']) !== 2 or
					is_numeric($placement['angle'] ?? NULL) === FALSE
				) {
					return FALSE;
				}

				foreach($placement['shift'] as $meters) {
					if(is_numeric($meters) === FALSE or abs((float)$meters) > MapSetting::COPY_SHIFT_MAX) {
						return FALSE;
					}
				}

				if(abs((float)$placement['angle']) > 360) {
					return FALSE;
				}

				$placement = [
					'shift' => [(float)$placement['shift'][0], (float)$placement['shift'][1]],
					'angle' => (float)$placement['angle']
				];

				return TRUE;

			})
			->addAfterCheck('shape', function(?array $placement): bool {

				if($placement === NULL) {
					return TRUE;
				}

				$this->expects(['ePlotSource']);

				return $this['ePlotSource']->acceptPlacement();

			})
			// Le contour de l'original transformé doit rester un contour (étape `ring` de coordinates). L'origine de la transformation
			// part avec le placement, pour que les traits la reprennent telle quelle sans la relire sur un autre contour
			->addAfterCheck('ring', function(?array &$placement): bool {

				if($placement === NULL) {
					return TRUE;
				}

				$placement['origin'] = MapLib::getOrigin($this['ePlotSource']['coordinates']);

				return MapLib::isRing(MapLib::getTransformed($this['ePlotSource']['coordinates'], $placement['origin'], $placement['shift'], $placement['angle']));

			})
			// Une pseudo-propriété n'a pas d'écriture par défaut : la copie reçoit le placement et le contour qu'il donne
			->replaceSet(function(?array $placement) {

				$this['placement'] = $placement;

				if($placement !== NULL) {
					$this['coordinates'] = MapLib::getTransformed($this['ePlotSource']['coordinates'], $placement['origin'], $placement['shift'], $placement['angle']);
				}

			});

		$l->property('seasonFirst')
			// Une parcelle refusée n'est pas posée sur l'élément : sans ce garde, expects() lèverait
			// au lieu de laisser parler l'échec de la parcelle. Sans effet côté web, où la parcelle
			// n'est pas construite et n'est donc jamais invalide.
			->addAfterCheck('zone', function(?int $season, \PropertyList $l): bool {

				$l->skipIfInvalid('zone');

				$this->expects([
					'zone' => ['seasonFirst']
				]);

				return ($season === NULL or $this['zone']['seasonFirst'] === NULL or $season >= $this['zone']['seasonFirst']);

			});

		$l->property('seasonLast')
			->addAfterCheck('zone', function(?int $season, \PropertyList $l): bool {

				$l->skipIfInvalid('zone');

				$this->expects([
					'zone' => ['seasonLast']
				]);

				return ($season === NULL or $this['zone']['seasonLast'] === NULL or $season <= $this['zone']['seasonLast']);

			})
			->addAfterCheck('consistency', function(?int $season): bool {
				return (
					$this['seasonFirst'] === NULL or
					$season === NULL or
					$this['seasonFirst'] <= $season
				);
			});

	}

}
?>