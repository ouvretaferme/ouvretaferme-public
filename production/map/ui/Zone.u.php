<?php
namespace map;

class ZoneUi {

	private \series\Series|\series\Task $ePlaced;

	private bool $withForm = FALSE;

	public function __construct() {

		\Asset::css('map', 'zone.css');
		\Asset::js('map', 'zone.js');

		$this->ePlaced = new \series\Series();

	}

	public function setPlaced(\series\Series|\series\Task $e, bool $withForm = FALSE): ZoneUi {

		$e->expects(['cPlace']);

		$this->ePlaced = $e;
		$this->withForm = $withForm;

		return $this;

	}

	/**
	 * La série que le plan arme, portée par le plan lui-même : la fiche du bac
	 * n'est pas toujours là pour la dire.
	 */
	private function getArmed(): string {

		if($this->ePlaced->empty()) {
			return '';
		}

		return ($this->ePlaced instanceof \series\Task) ?
			' data-task="'.$this->ePlaced['id'].'"' :
			' data-series="'.$this->ePlaced['id'].'" data-use="'.$this->ePlaced['use'].'" data-name="'.encode($this->ePlaced['name']).'"';

	}

	public function getPlan(\structure\Structure $eStructure, \Collection $cZone, Zone $eZoneSelected, int $season) {

		[$startTs, $stopTs] = new \series\PlaceUi()->getBounds($eStructure->conf(Configuration::class), $season);

		$paint = new \series\PlaceUi()->getPaint($this->ePlaced);

		$h = '<div id="zone-container"'.$this->getArmed().($paint === NULL ? '' : ' style="--target-paint: '.$paint.'"').'>';

			$h .= '<style>';
				$h .= ':root {';
					$h .= '--zone-content-months: '.$eStructure->conf(Configuration::class)['calendarMonths'].';';
				$h .= '}';
			$h .= '</style>';

			$h .= '<div id="zone-content" data-start="'.$startTs.'" data-stop="'.$stopTs.'">';

				foreach($cZone as $eZone) {

					$h .= '<div class="zone-wrapper" data-zone="'.$eZone['id'].'" data-path-zone="'.encode($eZone['name']).'" data-soil-color="'.$eStructure->getView('viewSoilColor').'">';
						$h .= $this->getPlanZone($eStructure, $eZone, $season);
					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getPrinting(\structure\Structure $eStructure, \Collection $cZone, int $season) {

		$h = \main\PdfUi::getPage('0.75cm');

		$h .= '<style>';
			$h .= 'html { font-size: 8px !important; }';
		$h .= '</style>';

		$h .= '<div id="zone-container" class="zone-printing">';

			$h .= '<style>';
				$h .= ':root {';
					$h .= '--zone-content-months: '.$eStructure->conf(Configuration::class)['calendarMonths'].';';
				$h .= '}';
			$h .= '</style>';
			$h .= '<div id="zone-content">';

				foreach($cZone as $eZone) {

					$h .= '<div class="zone-wrapper" data-soil-color="'.$eStructure->getView('viewSoilColor').'">';

						$h .= '<div class="zone-title">';
							$h .= '<span class="color-muted">'.s("Parcelle").'</span>';
							$h .= '<h2>';
								$h .= encode($eZone['name']);
							$h .= '</h2>';
							$h .= '<span class="color-muted">'.$eZone->getArea().'</span>';
						$h .= '</div>';

						$h .= '<table class="zone-table">';

							$h .= '<thead>';
								$h .= '<tr>';
									$h .= '<th>';
										$h .= '<div class="zone-header-season">';
											$h .= new \series\CultivationUi()->getListSeason($eStructure->conf(Configuration::class), $season);
										$h .= '</div>';
									$h .= '</th>';
								$h .= '</tr>';
							$h .= '</thead>';

							$h .= '<tbody>';
								$h .= '<tr>';

									$h .= '<td>';

										$h .= '<div class="zone-plots">';
											$h .= new PlotUi()->getPlots($eStructure, $eZone['cPlot'], $eZone, $season, new \series\Series(), TRUE);
										$h .= '</div>';

									$h .= '</td>';
								$h .= '</tr>';
							$h .= '</tbody>';
						$h .= '</table>';

					$h .= '</div>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getRotation(\structure\Structure $eStructure, \Collection $cZone, Zone $eZoneSelected, int $season, \Search $search = new \Search()) {

		if($search->notEmpty(['cFamily'])) {

			$this->zoneCounts = [];

			foreach($cZone as $eZone) {

				$this->zoneCounts[$eZone['id']] = $search->get('seen') === 0 ?
					$eZone['cPlot']->reduce(fn($ePlot, $v) => $ePlot['cBed']->find(fn($eBed) => ($eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE))->count() + $v, 0) :
					$eZone['cPlot']->reduce(fn($ePlot, $v) => $ePlot['cBed']->count() + $v, 0);

			}

		}

		// Les mêmes emplacements que la chronologie et le jardin, et la même règle :
		// tous visibles tant qu'aucun n'est choisi. Un système d'onglets n'en
		// montrait qu'un, et se souvenait du dernier consulté.
		$paint = new \series\PlaceUi()->getPaint($this->ePlaced);

		$h = '<div id="zone-container"'.$this->getArmed().($paint === NULL ? '' : ' style="--target-paint: '.$paint.'"').'>';


			$h .= '<div class="bed-item-wrapper">';

				foreach($cZone as $eZone) {

					$h .= '<div class="zone-wrapper util-print-block" data-zone="'.$eZone['id'].'">';
						$eZone->expects(['cPlot']);

						$h .= $this->getZoneBar($eZone, $eStructure->conf(Configuration::class)['rotationYears']);

						$h .= '<div class="garden-plots">';
							$h .= new PlotUi()->getRotations($eStructure, $eZone['cPlot'], $season);
						$h .= '</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected array $zoneCounts = [];

	public function getZoneBar(Zone $eZone, ?int $rotationYears = NULL): string {

		$eZone->expects(['cPlot']);

		$h = '<div class="place-zone-bar">';

			$h .= '<b>'.s("Parcelle {value}", encode($eZone['name'])).'</b>';
			$h .= '<span class="place-zone-bar-info">'.$eZone->getArea().'</span>';

			if($rotationYears !== NULL) {
				$h .= '<span class="place-zone-bar-rule">'.s("{value} ans entre deux familles", $rotationYears).'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	public static function getPathLabels(): string {

		return '<script type="text/javascript">window.mapZonePathLabels = '.json_encode([
			'zone' => s("Parcelle {value}", '{value}'),
			'bedOne' => s("{value} planche", '{value}'),
			'bedMany' => s("{value} planches", '{value}'),
		]).';</script>';

	}

	public function getPlanCorner(\structure\Structure $eStructure, \Collection $cZone, Zone $eZoneSelected, int $season): string {

		$h = '<div class="zone-corner util-print-invisible">';

			if(
				$cZone->count() >= 2 or
				($cZone->count() === 1 and $cZone->first()['cPlot']->count() >= 2)
			) {

				$h .= '<a data-dropdown="bottom-start" class="zone-button btn btn-secondary dropdown-toggle" data-dropdown-hover="true">';
					$h .= '<span class="zone-dropdown" data-placeholder="'.s("Mes parcelles").'">';
						$h .= $eZoneSelected->notEmpty() ? s("Parcelle {value}", encode($cZone->findById($eZoneSelected)['name'])) : s("Mes parcelles");
					$h .= '</span>';
				$h .= '</a>';
				$h .= '<div id="zone-selector" class="dropdown-list dropdown-list-2" data-wrapper="#zone-container">';

					$h .= '<a class="dropdown-item '.($eZoneSelected->empty() ? 'selected' : '').'" data-tab="" onclick="MapZone.select(undefined)" style="grid-column: span 2">';
						$h .= s("Toutes mes parcelles");
					$h .= '</a>';

					$h .= '<div class="dropdown-divider"></div>';

					if($this->ePlaced->notEmpty()) {
						$zones = array_count_values($this->ePlaced['cPlace']->getColumnCollection('zone')->getIds());
						asort($zones);
						$plots = array_count_values($this->ePlaced['cPlace']->getColumnCollection('plot')->getIds());
						asort($plots);
					}

					foreach($cZone as $eZone) {

						$beds = $zones[$eZone['id']] ?? 0;

						$h .= '<a class="dropdown-item '.($eZoneSelected->is($eZone) ? 'selected' : '').'" data-zone="'.$eZone['id'].'" onclick="MapZone.select('.$eZone['id'].')" '.attr('data-placeholder', s("Parcelle {value}", $eZone['name'])).' style="grid-column: span 2">';
							$h .= s("Parcelle {value}", encode($eZone['name']));
							if($this->ePlaced->notEmpty()) {
								$h .= '<span class="badge badge-solid bg-primary zone-count" id="zone-count-'.$eZone['id'].'">';
									if($beds > 0) {
										$h .= $beds;
									}
								$h .= '</span>';
							} else if(($this->zoneCounts[$eZone['id']] ?? 0) > 0) {
								$h .= '<span class="badge badge-solid bg-primary zone-count">'.$this->zoneCounts[$eZone['id']].'</span>';
							}
						$h .= '</a>';

						foreach($eZone['cPlot'] as $ePlot) {

							if($ePlot['zoneFill']) {
								continue;
							}

							$beds = $plots[$ePlot['id']] ?? 0;

							$h .= '<a class="dropdown-item" data-plot="'.$ePlot['id'].'" onclick="MapZone.select('.$eZone['id'].', '.$ePlot['id'].')" '.attr('data-placeholder', $ePlot['name']).'>';
								$h .= \Asset::icon('chevron-right').' '.encode($ePlot->getLabel());
								if($this->ePlaced->notEmpty()) {
									$h .= '<span class="badge badge-solid bg-primary zone-count" id="plot-count-'.$ePlot['id'].'">';
										if($beds > 0) {
											$h .= $beds;
										}
									$h .= '</span>';
								}
							$h .= '</a>';

						}

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function displayHeaderByRotation(int $season, int $number): string {

		$h = '';

		for($i = $season; $i > $season - $number; $i--) {
			$h .= '<div class="util-table-header '.($i === $season ? 'ml-1' : '').'">'.$i.'</div>';
		}

		return $h;

	}

	public function getPlanZone(\structure\Structure $eStructure, Zone $eZone, int $season): string {

		$eZone->expects(['cPlot']);

		$cPlot = $eZone['cPlot'];

		$h = '<div class="zone-title zone-sticky-left" id="zone-title-'.$eZone['id'].'" data-ref="zone" data-name="'.encode($eZone['name']).'">';

			// Le même bandeau que les rotations, rendu par la même méthode : trois
			// copies du même markup divergeaient à chaque retouche.
			$h .= $this->getZoneBar($eZone);

		$h .= '</div>';
		$h .= '<div class="zone-plots">';
			$h .= new PlotUi()->getPlots($eStructure, $cPlot, $eZone, $season, $this->ePlaced, FALSE, $this->withForm);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une parcelle à créer se dessine d'abord, en contour ou en rectangle, sur la carte centrée sur `$center` (MapLib::getMapCenter()),
	 * et sa surface se lit sur le dessin. Remise à plus tard, ou sans centre où l'ouvrir, sa surface se saisit.
	 */
	public function create(\structure\Structure $eStructure, int $season, ?array $center): \Panel {

		$form = new \util\FormUi();

		$eZone = new Zone([
			'structure' => $eStructure,
			'area' => NULL
		]);

		$h = '';

		$h .= $form->openAjax('/map/zone:doCreate', ['id' => 'zone-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('structure', $eStructure['id']);
			$h .= $form->hidden('season', \main\MainSetting::$onlineSeason);
			$h .= $form->hidden('origin', GET('origin'));

			$h .= $form->dynamicGroups($eZone, ['name', 'seasonFirst']);

			if($center !== NULL) {
				$h .= $this->getDrawingCreate($form, $eStructure, $season, $center);
			}

			$h .= new MapUi()->getDrawingArea($form, $eZone, s("Surface de la parcelle"), $center !== NULL);

			$h .= $form->group(
				content: $form->submit(s("Créer la parcelle"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-zone-create',
			title: s("Ajouter une parcelle"),
			body: $h
		);

	}

	/**
	 * Le tracé de la parcelle à créer : un contour posé point par point, ou un rectangle s'il le préfère (MapDrawingPolygon), comme sur la
	 * carte et à l'onboarding.
	 */
	protected function getDrawingCreate(\util\FormUi $form, \structure\Structure $eStructure, int $season, array $center): string {

		// La cote d'une parcelle PAC dit la surface qu'elle ajoute ou retire, dans les gabarits de Zone::getArea()
		$attributes = 'data-drawing-rectangle data-drawing-free data-drawing-pac data-drawing-area-field="area" data-length="'.s("{value} m", '{value}').'"';
		$attributes .= ' data-area-small="'.s("{value} m²", '{value}').'" data-area-large="'.s("{value} ha", '{value}').'"'.new PlanUi()->getMapPac($eStructure);

		$map = new MapUi()->getDrawingZonePanel($form, 'zone-create', $season, $center, 'polygon', 'coordinates', $attributes, [
			'remove' => ['trash', s("Recommencer")]
		]);

		$map .= '<div class="form-info hide" '.attr('data-drawing-pac-info', s("Contour déclaré à la PAC en {value}. Retouchez-le s'il a changé depuis.", '{value}')).'></div>';

		return new MapUi()->getDrawingGroup($form, $map, 'coordinates', s("Dessiner maintenant la parcelle"), 'Zone::coordinates.drawing');

	}

	public function update(Zone $eZone, ?string $origin): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/map/zone:doUpdate', ['id' => 'zone-update']);

			$h .= $form->hidden('id', $eZone['id']);
			$h .= $form->hidden('season', \main\MainSetting::$onlineSeason);
			$h .= $form->hidden('origin', $origin);

			$h .= $form->dynamicGroup($eZone, 'name');

			$h .= $form->group(
				s("Exploité"),
				new SeasonUi()->getField($form, $eZone),
				['data-wrapper' => 'seasonFirst seasonLast']
			);

			// Une parcelle dessinée a la surface de son contour (ZoneLib::getPropertiesUpdate()) : elle se lit sans se saisir
			if($eZone['coordinates'] === NULL) {
				$h .= $form->dynamicGroup($eZone, 'area');
			} else {
				$h .= $form->group(s("Surface de la parcelle"), '<b>'.s("{value} m²", \util\TextUi::number($eZone['area'], 0)).'</b>');
			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-zone-update',
			title: s("Modifier une parcelle"),
			body: $h
		);

	}

	public function getZonePlotWidget(\util\FormUi $form, \Collection $cZone, Plot $ePlot = new Plot(), ?string $placeholder = NULL): string {

		$h = '<div>';

			$select = [];

			foreach($cZone as $eZoneSelect) {

				$selectPlots = [];

				foreach($eZoneSelect['cPlot'] as $ePlotSelect) {

					if($ePlotSelect['zoneFill'] === FALSE) {

						$selectPlots[] = [
							'value' => $ePlotSelect['id'],
							'label' => '> '.$ePlotSelect->getLabel(),
							'attributes' => ['data-zone' => $eZoneSelect['id'], 'data-plot' => $ePlotSelect['id']]
						];

					} else {

						$select[] = [
							'value' => $ePlotSelect['id'],
							'label' => s("Parcelle {value}", $eZoneSelect['name']),
							'attributes' => ['style' => 'font-weight: bold; background-color: var(--background)', 'data-zone' => $eZoneSelect['id']]
						];

					}

				}

				$select = array_merge($select, $selectPlots);

			}

			$h .= $form->select('plot', $select, $ePlot, [
				'placeholder' => $placeholder
			]);

		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Zone::model()->describer($property, [
			'name' => s("Nom de la parcelle"),
			'structure' => s("Ferme"),
			'area' => s("Surface de la parcelle"),
			'seasonFirst' => s("Exploité depuis"),
			'seasonLast' => s("Exploité jusqu'à"),
			'createdAt' => s("Créé le"),
		]);

		switch($property) {

			case 'name' :
				$d->attributes = [
					'placeholder' => s("Ex. : Parcelle principale")
				];
				break;

			case 'area' :
				$d->append = s("m²");
				break;

			case 'seasonFirst' :
			case 'seasonLast' :
				$d->field = function(\util\FormUi $form, \Element $e, $property) {

					$placeholder = [
						'seasonFirst' => s("la nuit des temps"),
						'seasonLast' => s("la fin des temps")
					][$property];

					return new SeasonUi()->getDescriberField($form, $e, $e['structure'], NULL, NULL, $property, $placeholder);
				};
				break;

		}

		return $d;

	}

}
?>
