<?php
namespace map;

class PlanUi {

	const string VIEW_PLAN = 'plan';
	const string VIEW_MAP = 'map';
	const string VIEW_SCENE = 'scene';

	// Le conteneur de la carte de la vue Carte, que ses liens, ses fiches et plan.js nomment tous
	const string MAP = 'plan-mapview-map';

	// Le niveau du bloc où se nomme une parcelle qu'on dessine pour la créer
	const string ZONE_CREATE = 'plan-create-zone';

	const array FORMAT_COLORS_FIELD = ['#cce2d1', '#a9d3bd', '#86c3a8', '#63b393'];
	const array FORMAT_COLORS_GREENHOUSE = ['#f1cf8f', '#e6bb6c', '#dba84a', '#c9942f'];

	// Les trois marches du pas à pas d'une parcelle, dans l'ordre où elles se tracent
	const string STEP_ZONE = 'zone';
	const string STEP_PLOT = 'plot';
	const string STEP_BEDS = 'beds';

	private bool $print = FALSE;

	public function __construct() {

		\Asset::css('map', 'plan.css');

		\Asset::js('map', 'cartography.js');
		\Asset::js('map', 'plan.js');

	}

	public static function urlView(\structure\Structure $eStructure, ?int $season = NULL): string {

		return self::url($eStructure, $season, match($eStructure->getView('viewMap')) {
			\structure\Member::MAP => self::VIEW_MAP,
			\structure\Member::PLAN => self::VIEW_PLAN
		});

	}

	public static function url(\structure\Structure $eStructure, ?int $season = NULL, string $view = self::VIEW_PLAN): string {

		return match($view) {
			self::VIEW_PLAN => \structure\StructureUi::urlStructure($eStructure).'/plan'.($season ? '/'.$season : ''),
			self::VIEW_MAP => \structure\StructureUi::urlStructure($eStructure).'/cartographie'.($season ? '/'.$season : ''),
			self::VIEW_SCENE => \structure\StructureUi::urlStructure($eStructure).'/plan-3d'.($season ? '/'.$season : ''),
		};

	}

	public static function urlZone(\structure\Structure $eStructure, int $season, Zone $eZone, ?Plot $ePlot = NULL, bool $table = FALSE): string {

		$url = self::url($eStructure, $season).'?zone='.$eZone['id'];

		if($ePlot !== NULL) {
			$url .= '&plot='.$ePlot['id'];
		}

		if($table) {
			$url .= '&table=1';
		}

		return $url;

	}
	public function getTitle(\structure\Structure $eStructure, int $season, string $view, bool $withZones = TRUE, bool $createOnMap = FALSE): string {

		$tools = '';
		$create = '';

		// Un seul bouton plein à l'écran. Sans parcelle, la carte porte elle-même « Nouvelle parcelle » ; pendant un tracé ou
		// une création, celui-ci passe en contour, et plan.js le tient à jour (MapPlan.refreshZoneCreate())
		// Sur la carte, la parcelle se dessine où on la voit, et se nomme dans le bloc
		if($eStructure->canManage() and $withZones) {

			$class = 'btn btn-secondary';
			$label = \Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle parcelle").'</span>';

			if($createOnMap) {
				$create = $this->getMapZoneCreate($class, $label, 'plan-zone-create');
			} else {
				$create = '<a href="/map/zone:create?structure='.$eStructure['id'].'&season='.$season.'" id="plan-zone-create" class="'.$class.'">'.$label.'</a>';
			}

		}

		if($withZones) {
			// Chaque parcelle tracée a sa carte rendue avant l'impression : la vue satellite de la plus grande structure du parc,
			// 55 parcelles tracées, s'est mesurée entre 32 et 56 s, et le menu ne doit pas redevenir cliquable avant
			$download = fn(string $basemap, string $label) => '<a href="/map/map:downloadPlan?id='.$eStructure['id'].'&season='.$season.'&basemap='.$basemap.'" data-ajax-download="'.s("Plan de la ferme {value}", $season).'.pdf" data-waiter="'.s("Création en cours").'" data-waiter-timeout="60" class="dropdown-item">'.$label.'</a>';

			$tools = '<a data-dropdown="bottom-end" class="btn btn-outline-secondary dropdown-toggle">'.\Asset::icon('file-pdf').'<span class="hide-xs-down"> '.s("PDF").'</span></a>';
			$tools .= '<div class="dropdown-list">';
				$tools .= $download(\structure\Member::DRAWING, s("Plan dessiné"));
				$tools .= $download(\structure\Member::SATELLITE, s("Vue satellite"));
			$tools .= '</div>';
		}

		return \main\DesignUi::getPageHeader(
			s("Plan de la ferme"),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $selectedSeason) => self::url($eStructure, $selectedSeason, $view), $season),
			tools: $tools,
			create: $create
		);

	}

	/**
	 * Le PDF du plan : la carte de la structure en tête, en A4 paysage, puis le plan en liste en A4 portrait, où chaque parcelle
	 * qui a sa carte (MapLib::getZonesPrinted()) la porte sous sa barre, avant ses jardins.
	 */
	public function getPlanPrinting(\structure\Structure $eStructure, int $season, \Collection $cZone, \Collection $cZonePrinted, ?array $bounds, string $basemap): string {

		$this->print = TRUE;

		$h = \main\PdfUi::getPage('0.75cm');

		$h .= '<style>';
			$h .= '@page plan-print { size: A4 landscape; margin: 0.75cm; }';
			$h .= 'html { font-size: 12px !important; }';
		$h .= '</style>';

		// Sans parcelle tracée, il n'y a rien à cadrer : le PDF commence par le plan en liste
		if($bounds !== NULL) {
			$h .= $this->getMapPrintingPage($eStructure, $season, $basemap);
		}

		$h .= \main\PdfUi::getHeader().\main\PdfUi::getStructure($eStructure, s("Plan de la ferme {value}", $season), s("Saison {value}", $season));

		$h .= '<div class="plan-schema plan-schema-print">';

			foreach($cZone as $eZone) {

				$map = $cZonePrinted->offsetExists($eZone['id']) ?
					$this->getMapPrinting('plan-print-zone-'.$eZone['id'], 'plan-print-zone plan-print-'.$basemap, $this->getMapPrintingLegend()) :
					'';

				$h .= $this->getPlanZone($eStructure, $season, $eZone, new Plot(), map: $map);

			}

		$h .= '</div>';

		$h .= \main\PdfUi::getFooter($eStructure, note: $this->getPrintingNote());

		if($bounds !== NULL) {
			$h .= $this->getMapPrintingScript($season, $cZone, $cZonePrinted, $bounds, $basemap);
		}

		return $h;

	}

	protected function getPrintingNote(): string {
		return s("Plan de ferme édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]);
	}

	/**
	 * La page de la carte de la structure, dont la légende accompagne le titre.
	 */
	protected function getMapPrintingPage(\structure\Structure $eStructure, int $season, string $basemap): string {

		$h = '<div class="plan-print-page plan-print-'.$basemap.'">';

			$h .= \main\PdfUi::getHeader();

			$h .= '<div class="plan-print-head">';
				$h .= '<div class="plan-print-title">';
					$h .= '<div class="pdf-structure-heading">'.s("Plan de la ferme").'</div>';
					$h .= '<div class="pdf-structure-meta">'.s("Saison {value}", $season).'</div>';
				$h .= '</div>';
				$h .= $this->getMapPrintingLegend();
			$h .= '</div>';

			$h .= $this->getMapPrinting('plan-print-structure');

			$h .= \main\PdfUi::getFooter($eStructure, note: $this->getPrintingNote());

		$h .= '</div>';

		return $h;

	}

	protected function getMapPrintingLegend(): string {

		$legend = [
			'zone' => s("Parcelle"),
			Plot::OPEN_FIELD => s("Plein champ"),
			Plot::GREENHOUSE => s("Abri"),
			'bed' => s("Planche")
		];

		$h = '<div class="util-legend plan-print-legend">';
			foreach($legend as $key => $label) {
				$h .= '<span class="util-legend-item" data-legend="'.$key.'"><span class="util-legend-mark plan-print-legend-'.$key.'"></span>'.$label.'</span>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une carte du PDF : elle y est une image, que MapCartography.printMaps() rend. Le nord, l'échelle, le logo, le crédit
	 * et la légende se posent sur elle, l'échelle et la légende d'après ce que la carte montre.
	 */
	protected function getMapPrinting(string $id, string $class = '', string $legend = ''): string {

		$h = '<div id="'.$id.'" class="plan-print-map '.$class.'">';
			$h .= '<img alt=""/>';
			$h .= $legend;
			$h .= '<div class="plan-print-north">'.\Asset::icon('caret-up-fill').s("N").'</div>';
			$h .= '<div class="plan-print-logo"></div>';
			$h .= '<div class="plan-print-credit"></div>';
			$h .= '<div class="plan-print-scale"><span></span><span class="plan-print-scale-bar"></span></div>';
		$h .= '</div>';

		return $h;

	}

	protected function getMapPrintingScript(int $season, \Collection $cZone, \Collection $cZonePrinted, array $bounds, string $basemap): string {

		MapboxUi::load();

		$uiMap = new MapUi();

		$maps = [['map' => 'plan-print-structure', 'zone' => NULL]];

		foreach($cZonePrinted as $eZone) {
			$maps[] = ['map' => 'plan-print-zone-'.$eZone['id'], 'zone' => $eZone['id']];
		}

		// La carte qui rend les images passe de l'une à l'autre, sous elle et à sa taille (MapCartography.printMaps())
		$h = '<div id="plan-print-live" class="plan-print-live"></div>';

		$h .= '<script>';
			$h .= 'window.pdfWait = true;';
			$h .= 'document.ready(() => setTimeout(() => {';
				$h .= 'new MapCartography("plan-print-live", '.$season.', false, true, {bounds: [['.$bounds[0].', '.$bounds[1].'], ['.$bounds[2].', '.$bounds[3].']], interactive: false, controls: false, attribution: {compact: false}, basemap: "'.$basemap.'", plotWidth: 2, preserveDrawingBuffer: true, fadeDuration: 0, pixelRatio: 2, print: true})';
				foreach($cZone as $eZone) {
					$h .= $uiMap->addZone($eZone);
				}
				foreach($cZone as $eZone) {
					$h .= $uiMap->addZoneBeds($eZone);
				}
				$h .= '.printMaps('.json_encode($maps).');';
			$h .= '}, 100));';
		$h .= '</script>';

		return $h;

	}

	public function getViews(\structure\Structure $eStructure, int $season, string $selected): string {

		$views = [
			self::VIEW_PLAN => s("Plan"),
			self::VIEW_MAP => s("Carte"),
			self::VIEW_SCENE => s("3D"),
		];

		$tabs = '';

		foreach($views as $view => $label) {
			$tabs .= '<a href="'.self::url($eStructure, $season, $view).'" class="tab-item '.($view === $selected ? 'selected' : '').'">'.$label.'</a>';
		}

		return \main\DesignUi::getPageTabs($tabs);

	}
	protected function getZoneSummary(Zone $eZone, bool $withBeds = FALSE): string {

		$parts = [$eZone->getArea()];

		if($withBeds and $eZone['beds'] > 0) {
			$parts[] = p("{value} planche", "{value} planches", $eZone['beds']);
		}

		if($eZone['plots'] + $eZone['plotsGreenhouse'] > 0) {
			$parts[] = PlotUi::getCount($eZone['plots'], $eZone['plotsGreenhouse']);
		}

		return implode(' · ', $parts);

	}
	protected function getPlotTag(Plot $ePlot): string {

		return match($ePlot['mode']) {
			Plot::GREENHOUSE => '<span class="badge badge-solid bg-greenhouse plan-tag">'.s("Abri").'</span>',
			Plot::OPEN_FIELD => '<span class="badge badge-solid bg-open-field plan-tag">'.s("Plein champ").'</span>',
		};

	}

	/**
	 * Le nom d'un jardin sur la carte, où il est désigné seul : des planches posées à même la parcelle y portent le nom de
	 * leur parcelle, la seule forme qu'on y voit.
	 */
	protected function getMapPlotName(Zone $eZone, Plot $ePlot): string {
		return encode($ePlot['zoneFill'] ? $eZone['name'] : $ePlot['name']);
	}

	protected function getPlotName(Plot $ePlot): string {

		if($ePlot['zoneFill']) {
			return s("Planches de la parcelle");
		} else {
			return encode($ePlot['name']);
		}

	}

	protected function getPlotMenu(int $season, Zone $eZone, Plot $ePlot): string {

		$h = '<a data-dropdown="bottom-end" class="btn btn-sm btn-outline-primary dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';

			$dimensions = $this->getPlotDimensions($ePlot);

			$h .= '<div class="dropdown-title">'.$this->getPlotName($ePlot);
				if($dimensions !== '') {
					$h .= '<small class="plan-plot-dimensions">'.$dimensions.'</small>';
				}
			$h .= '</div>';

			if($ePlot['zoneFill']) {

				$h .= '</div>';

				return $h;

			}

			$h .= '<a href="/map/plot:update?id='.$ePlot['id'].'&season='.$season.'" class="dropdown-item">'.s("Modifier le jardin").'</a>';
			$h .= '<a href="/map/plot:duplicate?id='.$ePlot['id'].'&season='.$season.'" class="dropdown-item">'.s("Dupliquer le jardin").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a href="/map/plot:updateGreenhouse?id='.$ePlot['id'].'&season='.$season.'" class="dropdown-item">'.match($ePlot['mode']) {
				Plot::GREENHOUSE => s("Revenir en plein champ"),
				Plot::OPEN_FIELD => s("Couvrir le jardin avec un abri")
			}.'</a>';
			$h .= '<a data-ajax="/map/plot:doDelete" post-id="'.$ePlot['id'].'" post-season="'.$season.'" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de ce jardin ?").'">'.s("Supprimer le jardin").'</a>';

		$h .= '</div>';

		return $h;

	}

	protected function groupBeds(\Collection $cBed): array {

		$groups = [];

		foreach($cBed as $eBed) {

			$key = $eBed['length'].'|'.$eBed['width'].'|'.$eBed['alleyWidth'];

			if(isset($groups[$key]) === FALSE) {
				$groups[$key] = [
					'eBed' => $eBed,
					'cBed' => new \Collection()
				];
			}

			$groups[$key]['cBed'][$eBed['id']] = $eBed;

		}

		uasort($groups, fn($a, $b) => $b['cBed']->count() <=> $a['cBed']->count());

		foreach($groups as $group) {
			$group['cBed']->sort('name', natural: TRUE);
		}

		return $groups;

	}
	protected function getGroupLabel(Bed $eBed): string {

		if($eBed['length'] === NULL or $eBed['width'] === NULL) {
			return s("Sans dimensions");
		}

		return s("{length} m × {width} cm", $eBed);

	}
	protected function getGroupDetails(Bed $eBed): string {

		if($eBed['length'] === NULL or $eBed['width'] === NULL) {
			return '';
		}

		$parts = [];

		if($eBed['alleyWidth'] !== NULL) {
			$parts[] = s("passe-pied {value} cm", $eBed['alleyWidth']);
		}

		return implode(' · ', $parts);

	}

	protected function getCollectionActions(string $formId, bool $canDraw, string $urlDraw): string {

		$h = '<a class="dropdown-item" data-ajax-target="#'.$formId.'" data-ajax-method="get" data-ajax-submit="/map/bed:updateSizeCollection">'.s("Modifier les dimensions").'</a>';
		$h .= '<a class="dropdown-item" data-ajax-target="#'.$formId.'" data-ajax-method="get" data-ajax-submit="/map/bed:updateSeasonCollection">'.s("Modifier les saisons d'exploitation").'</a>';
		$h .= '<a class="dropdown-item" data-ajax-target="#'.$formId.'" data-ajax-method="get" data-ajax-submit="/map/bed:updatePlotCollection">'.s("Déplacer les planches").'</a>';
		$h .= '<a class="dropdown-item" data-ajax-target="#'.$formId.'" data-ajax-method="post" data-ajax-submit="/map/bed:doDeleteCollection" data-confirm="'.s("Êtes-vous sûr de vouloir supprimer les planches sélectionnées ? Si vous souhaitez conserver un historique de votre plan de ferme, modifiez plutôt les saisons d'exploitation.").'">'.s("Supprimer les planches").'</a>';

		if($canDraw) {

			$h .= '<div class="dropdown-title">'.s("Dessiner").'</div>';
			// MapPlan.changeSelection() complète l'adresse des planches cochées, que la vue Carte coche à son tour
			$h .= '<a class="dropdown-item" href="'.$urlDraw.'" data-batch="draw" data-url="'.$urlDraw.'">'.s("Dessiner sur la carte").'</a>';

			$h .= '<a class="dropdown-item hide" data-batch="draw-delete" data-ajax-target="#'.$formId.'" data-ajax-method="post" data-ajax-submit="/map/bed:doDeleteBedLineCollection" data-confirm="'.s("Le dessin des planches sélectionnées sera effacé de la carte, voulez-vous continuer ?").'">'.s("Effacer le dessin").'</a>';

		}

		return $h;

	}
	protected function getBedsByTable(\structure\Structure $eStructure, int $season, Plot $ePlot, \Collection $cBed, bool $canDraw): string {

		$canManage = $eStructure->canManage();

		$form = new \util\FormUi();
		$formId = 'plan-table-'.$ePlot['id'];

		$cBed->sort('name', natural: TRUE);

		$h = $form->open($formId);
		$h .= $form->hidden('plot', $ePlot);
		$h .= $form->hidden('season', $season);
		$h .= $form->hidden('origin', self::VIEW_PLAN);

		$h .= '<div class="util-overflow">';
			$h .= '<table class="tr-even plan-table">';

				$h .= '<thead>';
					$h .= '<tr>';
						if($canManage) {
							$h .= '<th class="plan-table-select"><input type="checkbox" onclick="MapPlan.toggleSelection(this)" title="'.s("Tout cocher / Tout décocher").'"/></th>';
						}
						$h .= '<th>'.s("Planche").'</th>';
						$h .= '<th class="text-end">'.s("Longueur").'</th>';
						$h .= '<th class="text-end">'.s("Largeur").'</th>';
						$h .= '<th class="text-end">'.s("Passe-pied").'</th>';
						$h .= '<th class="text-end">'.s("Surface").'</th>';
						$h .= '<th>'.s("Saisons").'</th>';
						$h .= '<th></th>';
						if($canManage) {
							$h .= '<th></th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cBed as $eBed) {

					$h .= '<tr>';
						if($canManage) {
							$h .= '<td class="plan-table-select">'.$form->inputCheckbox('ids[]', $eBed['id'], [
								'onclick' => 'MapPlan.changeSelection(this)',
								'data-drawn' => $eBed['drawn'] ? 1 : 0,
							]).'</td>';
						}
						$h .= '<td class="plan-table-name">';
							$h .= $canManage ? $eBed->quick('name', encode($eBed['name'])) : encode($eBed['name']);
						$h .= '</td>';
						$h .= '<td class="text-end">'.($eBed['length'] !== NULL ? s("{value} m", $eBed['length']) : '').'</td>';
						$h .= '<td class="text-end">'.($eBed['width'] !== NULL ? s("{value} cm", $eBed['width']) : '').'</td>';
						$h .= '<td class="text-end">'.($eBed['alleyWidth'] !== NULL ? s("{value} cm", $eBed['alleyWidth']) : '<span class="color-muted">-</span>').'</td>';
						$h .= '<td class="text-end">'.s("{value} m²", $eBed['area']).'</td>';
						$h .= '<td class="color-muted">'.SeasonUi::getInterval($eBed).'</td>';
						$h .= '<td>'.($eBed['drawn'] ? '<span title="'.s("Dessinée sur la carte").'">'.\Asset::icon('geo-fill').'</span>' : '').'</td>';
						if($canManage) {
							$h .= '<td class="text-end plan-table-actions">';
								$h .= '<a href="/map/bed:update?id='.$eBed['id'].'&season='.$season.'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('pencil-fill').'</a> ';
								$h .= '<a data-ajax="/map/bed:doDelete" post-id="'.$eBed['id'].'" post-season="'.$season.'" data-confirm="'.s("Souhaitez-vous réellement supprimer cette planche ? Si vous souhaitez conserver un historique de votre plan de ferme, modifiez plutôt les saisons d'exploitation.").'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('trash').'</a>';
							$h .= '</td>';
						}
					$h .= '</tr>';

				}

				$h .= '</tbody>';

			$h .= '</table>';
		$h .= '</div>';

		if($canManage) {

			$h .= '<div class="plan-table-selection">';
				$h .= '<a class="dropdown-toggle btn btn-sm btn-outline-primary" data-dropdown="top-start">'.s("Modifier les planches sélectionnées").'</a>';
				$h .= '<div class="dropdown-list">';
					$h .= $this->getCollectionActions($formId, $canDraw, DrawUi::urlPlot($eStructure, $season, $ePlot));
				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= $form->close();

		return $h;

	}

	/**
	 * La vue Carte. Le troisième niveau, les planches d'un jardin, est rendu par DrawUi : la vue passe ici le jardin
	 * que la page ouvre (`$ePlotDrawing`), son nœud dans le bloc (`$drawingLevel`) et ce qu'il pose sur la carte (`$drawingMap`).
	 */
	public function getMapView(\structure\Structure $eStructure, \structure\Member $eMember, int $season, \Collection $cZone, Zone $eZoneSelected, Plot $ePlotSelected, ?array $center, ?array $bounds, string $basemap, Plot $ePlotDrawing = new Plot(), string $drawingLevel = '', string $drawingMap = ''): string {

		if($center === NULL) {

			if($eStructure->canManage()) {

				$h = '<div class="util-block-cta">';
					$h .= '<h3>'.s("Situez votre ferme pour afficher la carte").'</h3>';
					$h .= '<p>'.s("La carte s'ouvrira sur votre lieu de production, prête pour y dessiner vos parcelles.").'</p>';
					$h .= '<div class="util-buttons">';
						$h .= '<a href="/structure/system:updatePlace?id='.$eStructure->getSystem('id').'" class="btn btn-primary">'.s("Renseigner le lieu de production").'</a>';
					$h .= '</div>';
				$h .= '</div>';

			} else {

				$h = '<div class="util-block-empty">';
					$h .= '<h3>'.s("La carte n'est pas encore disponible").'</h3>';
					$h .= '<p>'.s("Le lieu de production de la ferme n'a pas encore été renseigné.").'</p>';
				$h .= '</div>';

			}

			return $h;

		}

		MapboxUi::load();

		$container = self::MAP;

		$h = '<div class="plan-mapview">';

			$h .= '<div id="'.$container.'"></div>';

			if($cZone->empty()) {

				$h .= '<div class="util-block-empty plan-mapview-empty">';
					$h .= '<h3>'.s("Aucune parcelle pour le moment").'</h3>';
					if($eStructure->canManage()) {
						$h .= '<p>'.s("Créez votre première parcelle et dessinez son contour. Vous y placerez ensuite vos jardins et vos planches.").'</p>';
						$h .= '<div class="util-buttons">';
							$h .= $this->getMapZoneCreate('btn btn-primary', \Asset::icon('bounding-box').' '.s("Nouvelle parcelle"));
						$h .= '</div>';
					}
				$h .= '</div>';

			}

			foreach($cZone as $eZone) {
				$h .= $this->getMapZoneActions($eStructure, $season, $eZone);
				foreach($eZone['cPlot'] as $ePlot) {
					$h .= $this->getMapPlotActions($eStructure, $season, $eZone, $ePlot);
				}
			}

			if($eStructure->canManage()) {
				$h .= $this->getMapDrawing($eStructure);
			}

			$h .= $drawingMap;

		$h .= '</div>';

		$drawing = $ePlotDrawing->notEmpty();

		// Une parcelle sans contour n'a qu'un geste, son tracé : l'ouvrir le lance, depuis la liste des parcelles qu'« Annuler » rend,
		// sauf quand l'adresse désigne un de ses jardins, qui s'ouvre à sa place
		$zoneDrawing = ($eZoneSelected->notEmpty() and $eZoneSelected['coordinates'] === NULL and $ePlotSelected->empty() and $eStructure->canManage());

		// Le bloc descend de la structure à la parcelle, posé sur la carte. Sans parcelle, il n'a rien à lister, et ne
		// sert qu'à nommer la première qu'on dessine
		if($cZone->notEmpty() or $eStructure->canManage()) {

			$side = '<div class="util-card plan-console-side">';
				if($cZone->notEmpty()) {
					$side .= $this->getMapStructureLevel($eStructure, $cZone, ($eZoneSelected->empty() or $zoneDrawing));
				}
				$side .= $this->getMapZoneCreateLevel($eStructure, $season);
				foreach($cZone as $eZone) {
					$side .= $this->getMapZoneLevel($eStructure, $eZone, ($drawing === FALSE and $zoneDrawing === FALSE and $eZoneSelected->notEmpty() and $eZoneSelected['id'] === $eZone['id']), $ePlotSelected);
					$side .= $this->getMapPlotCreateLevel($eStructure, $eZone, Plot::OPEN_FIELD);
					$side .= $this->getMapPlotCreateLevel($eStructure, $eZone, Plot::GREENHOUSE);
				}
				$side .= $drawingLevel;
			$side .= '</div>';

			$h = '<div class="plan-console">'.$side.$h.'</div>';

		}

		$uiMap = new MapUi();

		$h .= '<script>';
			$h .= 'document.ready(() => setTimeout(() => {';
				// Sans écran tactile la molette zoome seule. Sur un écran tactile, un doigt doit continuer de faire défiler la page par-dessus la carte : cooperativeGestures lui réserve ce geste, et demande alors Ctrl à la molette
				$h .= 'const carto = new MapCartography("'.$container.'", '.$season.', true, true, {zoom: '.($cZone->empty() ? 16 : 14).', center: ['.$center[0].', '.$center[1].'], scrollZoom: true, cooperativeGestures: isTouch(), locale: '.json_encode(MapboxUi::getLocale()).', attribution: {compact: true}, basemap: "'.$basemap.'", basemapSwitch: '.json_encode([
					'member' => $eMember->empty() ? NULL : $eMember['id'],
					'preview' => MapboxUi::getStaticSatelliteUrl($bounds, $center, 144, 112),
					'labels' => [
						\structure\Member::SATELLITE => s("Satellite"),
						\structure\Member::DRAWING => s("Dessin"),
					],
				]).'});';
				$h .= 'MapPlan.bindMap(carto);';
				$h .= 'carto';
				foreach($cZone as $eZone) {
					$h .= $uiMap->addZone($eZone, withUndrawn: $eStructure->canManage());
				}
				foreach($cZone as $eZone) {
					$h .= $uiMap->addZoneBeds($eZone);
				}
				// Sans parcelle il n'y a rien à cadrer : la carte reste sur le lieu de production, d'assez près pour y reconnaitre ses champs
				if($cZone->notEmpty()) {
					if($eZoneSelected->empty()) {
						$h .= '.fitStructureBounds({duration: 0})';
					} else if($zoneDrawing) {
						// Le tracé part de la liste, où « Annuler » ramène : l'adresse la rejoint, et son geste attend que la carte puisse le porter
						$h .= '.fitStructureBounds({duration: 0}).do(() => qs(\'.plan-console-level[data-level="structure"] [data-zone="'.$eZoneSelected['id'].'"]\').click()); MapPlan.showStructure()';
					} else if($drawing and $ePlotSelected['zoneFill']) {
						$h .= '.selectZone('.$eZoneSelected['id'].').fitZoneBounds('.$eZoneSelected['id'].', {duration: 0}); MapPlan.showDrawing(false)';
					} else if($drawing) {
						// Le jardin désigné et sa parcelle, sans passer par un clic : rouvrir son niveau décocherait les planches de l'adresse (MapPlan.openDrawing())
						$h .= '.selectPlot('.$ePlotSelected['id'].').fitPlotBounds('.$ePlotSelected['id'].', {duration: 0}); MapPlan.showDrawing(false)';
					} else if($ePlotSelected->notEmpty()) {
						$h .= '.clickPlot('.$ePlotSelected['id'].', '.$eZoneSelected['id'].')';
					} else {
						$h .= '.clickZone('.$eZoneSelected['id'].')';
					}
				}
				$h .= ';';
			$h .= '}, 100));';
		$h .= '</script>';

		return $h;

	}

	/**
	 * Ce qu'un contour ou des planches enregistrés changent dans la vue Carte, par sélecteur : les actions de la parcelle,
	 * les fiches de ses jardins, dont le dessin des planches dépend de la forme de la parcelle, et les deux niveaux du
	 * bloc où elle se compte. Un contour se dessine depuis la liste des parcelles ou les actions de la parcelle désignée.
	 * Des planches se dessinent depuis le niveau de leur jardin (`$ePlotDrawing`), qui reste devant.
	 */
	public function getMapZoneNodes(\structure\Structure $eStructure, int $season, \Collection $cZone, Zone $eZone, Plot $ePlotDrawing = new Plot()): array {

		$nodes = [
			'.plan-mapview [data-zone-actions="'.$eZone['id'].'"]' => $this->getMapZoneActions($eStructure, $season, $eZone),
			'.plan-console-level[data-level="structure"]' => $this->getMapStructureLevel($eStructure, $cZone, FALSE),
			'.plan-console-level[data-level="zone"][data-zone="'.$eZone['id'].'"]' => $this->getMapZoneLevel($eStructure, $eZone, $ePlotDrawing->empty(), $ePlotDrawing)
		];

		// Le nom proposé à un jardin ou un abri suit ceux de la parcelle, et un contour donné à la parcelle l'ouvre à leur création
		foreach([Plot::OPEN_FIELD, Plot::GREENHOUSE] as $mode) {
			$nodes['#'.self::getMapPlotCreateId($eZone, $mode)] = $this->getMapPlotCreateLevel($eStructure, $eZone, $mode);
		}

		foreach($eZone['cPlot'] as $ePlot) {
			$nodes['.plan-mapview [data-plot-actions="'.$ePlot['id'].'"]'] = $this->getMapPlotActions($eStructure, $season, $eZone, $ePlot);
		}

		return $nodes;

	}

	/**
	 * Le premier niveau de la colonne : les parcelles de la structure, celles où il reste à dessiner en tête pour qui
	 * peut le faire. Un lecteur les lit d'un seul tenant, dans l'ordre de la carte.
	 */
	protected function getMapStructureLevel(\structure\Structure $eStructure, \Collection $cZone, bool $visible): string {

		$isRemaining = fn(Zone $eZone) => ($eStructure->canManage() and $eZone['remaining'] > 0);

		$cZoneRemaining = $cZone->find($isRemaining);
		$cZoneDone = $cZone->find(fn($eZone) => $isRemaining($eZone) === FALSE);

		$h = '<div class="plan-console-level'.($visible ? '' : ' hide').'" data-level="structure">';

			$h .= $this->getMapLevelHead(encode($eStructure['name']), '');

			$h .= '<div class="plan-console-list">';

				if($cZoneRemaining->notEmpty()) {

					$h .= '<div class="plan-column-title">'.s("Reste à dessiner").' <span class="util-counter">'.$cZoneRemaining->count().'</span></div>';
					foreach($cZoneRemaining as $eZone) {
						$h .= $this->getMapZoneItem($eStructure, $eZone);
					}

					if($cZoneDone->notEmpty()) {
						$h .= '<div class="plan-column-title">'.s("Dessinées").' <span class="util-counter">'.$cZoneDone->count().'</span></div>';
					}

				}

				foreach($cZoneDone as $eZone) {
					$h .= $this->getMapZoneItem($eStructure, $eZone);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le second niveau de la colonne : une parcelle et ses jardins, ceux où il reste à dessiner en tête,
	 * puis les planches posées à même la parcelle quand elle en a.
	 */
	protected function getMapZoneLevel(\structure\Structure $eStructure, Zone $eZone, bool $visible, Plot $ePlotSelected): string {

		$withRemaining = ($this->getMapStep($eStructure, $eZone) !== NULL);

		$cPlot = $eZone['cPlot']->find(fn($ePlot) => $ePlot['zoneFill'] === FALSE or $ePlot['beds'] > 0);
		$isRemaining = fn(Plot $ePlot) => ($withRemaining and $ePlot['isRemaining']);
		$isSelected = fn(Plot $ePlot) => ($ePlotSelected->notEmpty() and $ePlotSelected['id'] === $ePlot['id']);

		$cPlotRemaining = $cPlot->find($isRemaining);
		$cPlotDone = $cPlot->find(fn($ePlot) => $isRemaining($ePlot) === FALSE);

		// Les planches posées à même la parcelle se listent comme un jardin sans en être un : les compteurs de jardins les taisent
		$counter = function(\Collection $cPlot): string {
			$count = $cPlot->find(fn($ePlot) => $ePlot['zoneFill'] === FALSE)->count();
			return ($count === 0) ? '' : ' <span class="util-counter">'.$count.'</span>';
		};

		$up = $this->getMapUp(attr('onclick', 'MapCartography.get("'.self::MAP.'").clickStructure()'));

		if($eZone['coordinates'] === NULL) {
			$summary = s("non dessinée");
		} else if($withRemaining) {
			$summary = $eZone->getArea();
		} else {
			$summary = $this->getZoneSummary($eZone, withBeds: TRUE);
		}

		$h = '<div class="plan-console-level'.($visible ? '' : ' hide').'" data-level="zone" data-zone="'.$eZone['id'].'">';

			$h .= $this->getMapLevelHead(encode($eZone['name']), $summary, $up);

			$h .= '<div class="plan-console-list">';

				$add = $this->getMapPlotAdd($eStructure, $eZone);

				if($cPlotRemaining->notEmpty()) {

					$h .= '<div class="plan-column-title plan-column-title-action">';
						$h .= '<span>'.s("Reste à dessiner").$counter($cPlotRemaining).'</span>';
						$h .= $add;
					$h .= '</div>';
					foreach($cPlotRemaining as $ePlot) {
						$h .= $this->getMapPlotItem($eZone, $ePlot, TRUE, $isSelected($ePlot));
					}

					if($cPlotDone->notEmpty()) {
						$h .= '<div class="plan-column-title">'.s("Dessinés").$counter($cPlotDone).'</div>';
					}

				} else if($add !== '') {

					$h .= '<div class="plan-column-title plan-column-title-action">';
						$h .= '<span>'.s("Jardins").$counter($cPlotDone).'</span>';
						$h .= $add;
					$h .= '</div>';

				}

				foreach($cPlotDone as $ePlot) {
					$h .= $this->getMapPlotItem($eZone, $ePlot, FALSE, $isSelected($ePlot));
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le « + » de la liste d'une parcelle, qui pose sur la carte le contour d'un jardin à créer, du type choisi. Il faut
	 * à la parcelle un contour où le poser : une parcelle sans forme se dessine d'abord, et un jardin sans forme se crée
	 * depuis l'onglet Plan.
	 */
	protected function getMapPlotAdd(\structure\Structure $eStructure, Zone $eZone): string {

		if($eStructure->canManage() === FALSE or $eZone['coordinates'] === NULL) {
			return '';
		}

		$h = '<a data-dropdown="bottom-end" class="btn btn-sm btn-outline-primary dropdown-toggle">'.\Asset::icon('plus').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';
			$h .= '<div class="dropdown-title">'.s("Nouveau jardin").'</div>';
			$h .= $this->getMapPlotCreate($eZone, Plot::OPEN_FIELD, 'dropdown-item', s("Plein champ"));
			$h .= $this->getMapPlotCreate($eZone, Plot::GREENHOUSE, 'dropdown-item', s("Abri"));
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ouvre le tracé d'un jardin à créer (drawing.js). Le nom se saisit dans le bloc (getMapPlotCreateLevel()),
	 * dont le formulaire part avec la forme. En plein champ, un jardin se trace d'abord en contour, et en rectangle s'il le
	 * préfère (MapDrawingPolygon). Sous abri, il reste un rectangle : ses dimensions font sa surface (Plot::properties(),
	 * `computed`).
	 */
	protected function getMapPlotCreate(Zone $eZone, string $mode, string $class, string $label): string {

		$h = '<a '.match($mode) {
			Plot::GREENHOUSE => 'data-drawing="rectangle" data-drawing-dimensions',
			Plot::OPEN_FIELD => 'data-drawing="polygon" data-drawing-rectangle data-drawing-free'
		}.$this->getMapDrawingError('Plot::coordinates.drawingMap');
		$h .= ' data-drawing-sheet="plot-create-'.$mode.'" data-drawing-map="'.self::MAP.'" data-drawing-url="/map/plot:doCreate" data-drawing-target="plotCreated"';
		$h .= ' data-drawing-level="'.self::getMapPlotCreateId($eZone, $mode).'" data-drawing-coordinates="null" '.attr('data-drawing-contour', json_encode($eZone['coordinates']));
		$h .= ' class="'.$class.'">';
			$h .= $label;
		$h .= '</a>';

		return $h;

	}

	public static function getMapPlotCreateId(Zone $eZone, string $mode): string {
		return 'plan-create-'.$eZone['id'].'-'.$mode;
	}

	/**
	 * Le niveau du bloc pendant qu'on pose un jardin à créer : le nom proposé, à la suite des jardins du même mode
	 * dans la parcelle. Il reste caché, et MapDrawing le montre le temps du geste.
	 *
	 * L'enveloppe existe même vide, pour la parcelle sans contour : la réponse qui lui en donne un la remplace
	 * (getMapZoneNodes()), et ne saurait où insérer un nœud absent de la page.
	 */
	protected function getMapPlotCreateLevel(\structure\Structure $eStructure, Zone $eZone, string $mode): string {

		$id = self::getMapPlotCreateId($eZone, $mode);

		if($eStructure->canManage() === FALSE) {
			return '';
		}

		if($eZone['coordinates'] === NULL) {
			return '<div class="plan-console-level hide" data-level="create" id="'.$id.'"></div>';
		}

		$ePlot = new Plot([
			'zone' => $eZone,
			'mode' => $mode,
			'length' => NULL,
			'width' => NULL,
			'name' => match($mode) {
				Plot::GREENHOUSE => s("Abri {value}", $eZone['plotDefault'][$mode]),
				Plot::OPEN_FIELD => s("Jardin {value}", $eZone['plotDefault'][$mode])
			}
		]);

		$name = s("Nouveau jardin");

		// Le bloc est étroit : le libellé passe au-dessus du champ
		$form = new \util\FormUi(['columns' => 1]);

		$h = '<div class="plan-console-level hide" data-level="create" id="'.$id.'">';

			$h .= $this->getMapLevelHead($name, '<span data-drawing-summary></span>', $this->getMapCreateUp());

			$h .= $form->open(NULL, ['class' => 'plan-console-form']);
				$h .= $form->hidden('zone', $eZone);
				$h .= $form->hidden('mode', $mode);
				$h .= $form->hidden('origin', self::VIEW_MAP);
				$h .= $form->dynamicGroup($ePlot, 'name');

				// Les dimensions d'un abri se saisissent comme elles se lisent : le rectangle les suit, et elles suivent le rectangle
				// (MapDrawing.changeDimensions(), refreshDimensions())
				if($mode === Plot::GREENHOUSE) {
					$dimension = fn(string $property) => function(\PropertyDescriber $d) use($property) {
						$d->attributes = ['data-drawing-dimension' => $property];
					};
					$h .= $form->dynamicGroups($ePlot, ['length', 'width'], ['length' => $dimension('length'), 'width' => $dimension('width')]);
				}

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	// Le retour d'un niveau de création quitte le geste sans rien écrire, comme « Annuler » : seul « Créer » crée la forme
	protected function getMapCreateUp(): string {
		return $this->getMapUp();
	}

	/**
	 * Le lien qui ouvre le tracé d'une parcelle à créer : un contour posé point par point, comme un jardin, ou un rectangle qui
	 * se tire d'un glisser (MapDrawingPolygon, drawing.js). Le nom se saisit dans le bloc (getMapZoneCreateLevel()), dont le
	 * formulaire part avec la forme.
	 */
	public function getMapZoneCreate(string $class, string $label, ?string $id = NULL): string {

		$h = '<a'.($id === NULL ? '' : ' id="'.$id.'"').' data-drawing="polygon" data-drawing-rectangle data-drawing-free data-drawing-pac data-drawing-sheet="zone-create" data-drawing-map="'.self::MAP.'" data-drawing-url="/map/zone:doCreate"';
		$h .= $this->getMapDrawingError('Zone::coordinates.drawingMap');
		$h .= ' data-drawing-level="'.self::ZONE_CREATE.'" data-drawing-coordinates="null" class="'.$class.'">';
			$h .= $label;
		$h .= '</a>';

		return $h;

	}

	protected function getMapZoneCreateLevel(\structure\Structure $eStructure, int $season): string {

		if($eStructure->canManage() === FALSE) {
			return '';
		}

		// Le bloc est étroit : le libellé passe au-dessus du champ
		$form = new \util\FormUi(['columns' => 1]);

		$h = '<div class="plan-console-level hide" data-level="create" id="'.self::ZONE_CREATE.'">';

			$h .= $this->getMapLevelHead(s("Nouvelle parcelle"), '<span data-drawing-summary></span>', $this->getMapCreateUp());

			$h .= $form->open(NULL, ['class' => 'plan-console-form']);
				$h .= $form->hidden('structure', $eStructure);
				$h .= $form->hidden('origin', self::VIEW_MAP);
				$h .= $form->dynamicGroup(new Zone(), 'name');
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	// La tête d'un niveau de la colonne : le retour au niveau du dessus, devant le nom de ce qu'on regarde, puis sa mesure dessous
	protected function getMapLevelHead(string $name, string $summary, string $up = '', string $tag = ''): string {

		$h = '<div class="plan-console-plot">';
			if($up !== '') {
				$h .= '<span class="plan-console-up">'.$up.'</span>';
			}
			$h .= '<div class="plan-console-plot-name">';
				$h .= '<div class="plan-console-plot-title">'.$name.$tag.'</div>';
				if($summary !== '') {
					$h .= '<div class="plan-console-plot-summary">'.$summary.'</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	// Le disque du retour, comme celui de la tête d'un écran : la flèche se comprend seule, sans le nom du niveau où l'on revient
	protected function getMapUp(string $attributes = ''): string {
		return '<a '.$attributes.' class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('arrow-left').'</a>';
	}

	protected function getMapZoneItem(\structure\Structure $eStructure, Zone $eZone): string {

		// Une parcelle sans contour n'a qu'un geste, son tracé, que son clic lance pour qui peut tracer
		if($eZone['coordinates'] === NULL and $eStructure->canManage()) {
			$click = attr('onclick', 'MapPlan.openZoneDrawing('.$eZone['id'].')').' '.$this->getMapZoneDrawingAttributes($eZone);
		} else {
			$click = attr('onclick', 'MapCartography.get("'.self::MAP.'").clickZone('.$eZone['id'].')');
		}

		$h = '<a '.$click.' class="btn btn-neutral-secondary plan-item" data-zone="'.$eZone['id'].'">';
			$h .= '<div class="plan-item-title">';
				// Le badge passe sous le nom : à côté de lui, il ne laisse pas la place de distinguer deux parcelles
				$h .= '<span class="plan-item-text">';
					$h .= '<span class="plan-item-name">'.encode($eZone['name']).'</span>';
					// Un seul badge par ligne : la parcelle sans forme le dit à tous, celle qui ouvre un pas à pas à qui peut le suivre
					if($eZone['coordinates'] === NULL) {
						$h .= '<span class="badge badge-solid bg-muted plan-tag">'.s("non dessinée").'</span>';
					} else if($this->getMapStep($eStructure, $eZone) !== NULL) {
						$h .= '<span class="badge badge-neutral plan-tag">'.$this->getMapRemaining($eZone).'</span>';
					}
				$h .= '</span>';
				$h .= '<span class="plan-item-value">'.$eZone->getArea().'</span>';
				$h .= \Asset::icon('chevron-right', ['class' => 'plan-item-chevron']);
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	// Une ligne de jardin ou d'abri, qui le désigne sans ouvrir ses planches. Des planches posées à même la parcelle n'ont pas d'autre
	// forme qu'elle : leur ligne désigne la parcelle, et les ouvre à qui peut les dessiner, faute d'un jardin dont la rangée porte « Planches »
	protected function getMapPlotItem(Zone $eZone, Plot $ePlot, bool $withRemaining, bool $selected): string {

		if($ePlot['zoneFill'] and $ePlot->canWrite() and $ePlot->canBedLine()) {
			$click = 'MapPlan.openPlot('.$ePlot['id'].', '.$eZone['id'].', true)';
		} else if($ePlot['zoneFill']) {
			$click = 'MapCartography.get("'.self::MAP.'").clickZone('.$eZone['id'].')';
		} else {
			$click = 'MapCartography.get("'.self::MAP.'").clickPlot('.$ePlot['id'].', '.$eZone['id'].')';
		}

		$h = '<a '.attr('onclick', $click).' class="btn btn-neutral-secondary plan-item'.($selected ? ' selected' : '').'" data-plot="'.$ePlot['id'].'">';
			$h .= '<div class="plan-item-title">';
				// Comme pour une parcelle, le badge passe sous le nom : à côté de lui, il ne lui laisse pas de place
				$h .= '<span class="plan-item-text">';
					$h .= '<span class="plan-item-name">'.$this->getPlotName($ePlot).'</span>';
					if($withRemaining) {
						$h .= $this->getMapPlotRemaining($ePlot);
					}
				$h .= '</span>';
				if($ePlot['zoneFill'] === FALSE) {
					$h .= $this->getPlotTag($ePlot);
				}
				$h .= \Asset::icon('chevron-right', ['class' => 'plan-item-chevron']);
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Ce que le mode de tracé (MapDrawing, drawing.js) prend dans la page : la fiche de chaque geste, qui se pose
	 * sur la carte au-dessus de la forme, la rangée de ses réglages qu'il pose en haut de la carte, et la croix qui retire
	 * un point.
	 */
	protected function getMapDrawing(\structure\Structure $eStructure): string {

		// MapDrawing écrit la surface à mesure qu'on trace, dans les deux gabarits de Zone::getArea(), et les cotes d'un rectangle, ses deux
		// côtés en mètres, qu'on le crée ou qu'on retrace en rectangle une forme existante
		$measures = 'data-area-small="'.s("{value} m²", '{value}').'" data-area-large="'.s("{value} ha", '{value}').'" data-length="'.s("{value} m", '{value}').'"';

		// Une parcelle se reprend des parcelles PAC qu'on la crée ou qu'on la retrace, un jardin jamais
		$h = $this->getMapDrawingActions('zone', 'coordinates area', $measures.$this->getMapPac($eStructure));
		$h .= $this->getMapDrawingActions('plot', 'coordinates area', $measures);

		// Une forme qu'on crée a sa surface dans la tête du bloc, où se saisit son nom, et ses boutons sous ce nom : la fiche posée ne garde que les refus de la forme
		$h .= $this->getMapDrawingActions('zone-create', 'coordinates area', $measures.$this->getMapPac($eStructure), finish: s("Créer la parcelle"), withSummary: FALSE);
		$h .= $this->getMapDrawingActions('plot-create-'.Plot::OPEN_FIELD, 'coordinates area', $measures, finish: s("Créer le jardin"), withSummary: FALSE);
		$h .= $this->getMapDrawingActions('plot-create-'.Plot::GREENHOUSE, 'coordinates area length width', $measures, finish: s("Créer le jardin"), withSummary: FALSE);

		$unpoint = $this->getMapUnpoint();

		// Une parcelle ou un jardin passe d'un contour à un rectangle et retour, par la paire posée à côté de sa rangée. Sous 768 px,
		// la rangée part au bord gauche de la carte (plan.css), et la paire passe en tête (`util-mapbars-start-md`)
		$h .= '<div class="hide" data-drawing-kit="polygon">';
			$h .= '<div class="util-mapbars util-mapbars-start-md plan-mapview-bar" data-drawing-tools>';
				$h .= '<div class="util-mapbar" data-drawing-gestures>';
					$h .= $this->getMapShapeChoice();
					$h .= $this->getMapModes('polygon');
					$h .= $this->getMapTool('remove', 'trash', s("Recommencer"));
					$h .= $this->getMapFinish();
					$h .= $this->getMapCancel();
				$h .= '</div>';
				$h .= $this->getMapShapes();
			$h .= '</div>';
			$h .= $unpoint;
		$h .= '</div>';

		// Un abri reste un rectangle, qui ne passe pas en forme libre : sa rangée est seule, dans la boite qui reçoit la fiche d'un abri qu'on crée
		$h .= '<div class="hide" data-drawing-kit="rectangle">';
			$h .= '<div class="util-mapbars plan-mapview-bar" data-drawing-tools>';
				$h .= '<div class="util-mapbar">';
					$h .= $this->getMapModes('rectangle');
					$h .= $this->getMapTool('remove', 'trash', s("Recommencer"));
					$h .= $this->getMapFinish();
					$h .= $this->getMapCancel();
				$h .= '</div>';
			$h .= '</div>';
			$h .= $unpoint;
		$h .= '</div>';

		return $h;

	}

	/**
	 * La fiche d'un geste qui se pose sur la carte, à la place des actions qui l'ont ouvert (MapDrawing.pose()) : qui on
	 * dessine, ce que le geste dit à mesure et ce qui le refuse (`$wrapper`, les propriétés de la route). Ses actions sont
	 * dans la rangée du tracé, ou sous le nom saisi dans le bloc pour une forme qu'on crée, où « Enregistrer » prend le nom de `$finish`.
	 */
	protected function getMapDrawingActions(string $card, string $wrapper, string $attributes, string $more = '', ?string $finish = NULL, bool $withSummary = TRUE): string {

		if($finish !== NULL) {
			$attributes .= ' '.attr('data-finish', $finish);
		}

		$h = '<div class="util-card plan-mapview-actions hide" data-drawing-card="'.$card.'" data-drawing-pose '.$attributes.'>';
			$h .= '<div class="plan-mapview-actions-say" data-wrapper="'.$wrapper.'">';
				$h .= '<b data-drawing-name></b>';
				if($withSummary) {
					$h .= '<span class="color-muted" data-drawing-summary></span>';
				}
				$h .= $more;
			$h .= '</div>';
			$h .= $this->getMapIssues();
		$h .= '</div>';

		return $h;

	}

	// Une case de la rangée d'outils, que la forme montre quand elle en a l'usage (`data-tool`, getTools())
	protected function getMapTool(string $name, string $icon, string $label): string {
		return '<a class="util-mapbar-item" data-tool="'.$name.'">'.\Asset::icon($icon).'<span>'.$label.'</span></a>';
	}

	// Les formes d'une parcelle ou d'un jardin, partout où il se trace : leur propre rangée, au bout de celle du tracé du côté où
	// elles s'ancrent (`util-mapbars`), pour qu'elle ne bouge pas quand les modes apparaissent. La séance ne montre que les formes que
	// le lien propose (`drawingFree`, `drawingRectangle`, `drawingPac`) et désigne celle qu'on trace (MapDrawing.refreshTools())
	public function getMapShapes(): string {

		$h = '<div class="util-mapbar" data-drawing-shapes>';
			foreach($this->getShapeList() as $shape => [$icon, $label]) {
				$h .= $this->getMapTool($shape, $icon, $label);
			}
		$h .= '</div>';

		return $h;

	}

	// La forme qu'on trace, en tête de la rangée des gestes dès que celle-ci se montre, et les autres formes dans son menu, comme la
	// roue des planches (getBedsMenu()). Une case par forme, que la séance montre à la place de la rangée des formes : les entrées du
	// menu sont des formes comme les autres, qu'elle cache quand le lien ne les propose pas (MapDrawing.refreshTools())
	public function getMapShapeChoice(): string {

		$shapes = $this->getShapeList();

		$h = '';

		foreach($shapes as $shape => [$icon, $label]) {

			$h .= '<a class="util-mapbar-item util-mapbar-choice hide" data-drawing-choice="'.$shape.'" data-dropdown="bottom-start">';
				$h .= '<div class="util-mapbar-choice-icon">'.\Asset::icon($icon).\Asset::icon('chevron-down').'</div>';
				$h .= '<span>'.$label.'</span>';
			$h .= '</a>';
			$h .= '<div class="dropdown-list bg-primary" data-drawing-choices>';
				foreach($shapes as $other => [$otherIcon, $otherLabel]) {
					if($other !== $shape) {
						$h .= '<a class="dropdown-item" data-tool="'.$other.'">'.\Asset::icon($otherIcon).' '.$otherLabel.'</a>';
					}
				}
			$h .= '</div>';

		}

		return $h;

	}

	protected function getShapeList(): array {

		return [
			'free' => ['pentagon', s("Forme libre")],
			'rectangle' => ['square', s("Rectangle")],
			'pac' => ['cursor', s("Parcelle PAC")]
		];

	}

	// Ce que dit un clic de l'outil « Parcelle PAC » (MapDrawing.pickPac(), hoverPac()), et l'année de la déclaration dont vient le
	// contour, à côté de sa surface. Les gabarits se posent sur la fiche du tracé, comme ceux de la surface
	/* La structure part avec chaque lecture du registre : le formulaire qui porte la carte ne la nomme pas partout de la même façon */
	public function getMapPac(\structure\Structure $eStructure): string {

		$h = ' '.attr('data-pac-structure', $eStructure['id']);
		$h .= ' '.attr('data-pac-none', s("Aucune parcelle PAC sous ce point"));
		$h .= ' '.attr('data-pac-apart', s("Les parcelles doivent se toucher pour être fusionnées ensemble"));
		$h .= ' '.attr('data-pac-split', s("Couperait la parcelle en deux"));
		$h .= ' '.attr('data-pac-hole', s("Ferait un trou dans la parcelle"));
		$h .= ' '.attr('data-pac-detailed', s("Contour trop détaillé"));
		$h .= ' '.attr('data-pac-add', s("+ {value}", '{value}'));
		$h .= ' '.attr('data-pac-remove', s("− {value}", '{value}'));
		$h .= ' '.attr('data-pac-year', s("PAC {value}", '{value}'));

		return $h;

	}

	// Le refus qu'oppose MapDrawing.finish(), sans requête, à une forme qui ne peut pas encore s'enregistrer
	protected function getMapDrawingError(string $error): string {
		return ' data-drawing-error="'.encode(AlertUi::getError($error)).'" data-drawing-error-name="'.$error.'"';
	}

	// Les modes d'une forme, comme dans le panneau (MapUi::getDrawingEmbedded()) : ses poignées se tirent, elle se déplace
	// d'un bloc ou elle tourne sous le doigt. La séance montre ceux de la forme et choisit le premier (MapDrawing.refreshTools()).
	// Un rang de planches arrive posé d'office, et c'est d'abord sa place qu'on règle : il ouvre sur « Déplacer », dans l'ordre
	// de MapDrawingLine.getModes()
	public function getMapModes(string $shape): string {

		$modes = [
			'edit' => \Asset::icon('bounding-box').'<span>'.s("Tracer").'</span>',
			'move' => \Asset::icon('arrows-move').'<span>'.s("Déplacer").'</span>',
			'rotate' => \Asset::icon('arrow-clockwise').'<span>'.s("Rotation").'</span>'
		];

		$order = match($shape) {
			'line' => ['move', 'rotate', 'edit'],
			'polygon', 'rectangle', 'copy' => ['edit', 'move', 'rotate']
		};

		$h = '';

		foreach($order as $mode) {
			$h .= '<a class="util-mapbar-item" data-drawing-mode="'.$mode.'">'.$modes[$mode].'</a>';
		}

		return $h;

	}

	// Les deux issues du geste, au bout de la rangée : « Enregistrer » en avant, que le geste renomme quand il crée
	protected function getMapFinish(): string {
		return '<a class="util-mapbar-item util-mapbar-primary hide-sm-down" data-drawing-action="finish">'.\Asset::icon('check-lg').'<span data-drawing-finish>'.s("Enregistrer").'</span></a>';
	}

	protected function getMapCancel(): string {
		return '<a class="util-mapbar-item hide-sm-down" data-drawing-action="cancel">'.\Asset::icon('x-lg').'<span>'.s("Annuler").'</span></a>';
	}

	// La croix qui retire le point choisi, posée sur la carte à côté de lui
	protected function getMapUnpoint(): string {
		return '<a class="util-maptool util-maptool-unpoint" data-tool="unpoint" data-drawing-unpoint><span>'.\Asset::icon('x-lg').'</span></a>';
	}

	// Sous 768 px, une carte de la hauteur d'un téléphone n'a pas la place de porter les issues sous la forme : la fiche, collée sous elle, les reprend.
	// Une forme qu'on crée les prend sous son nom, dans le bloc, à toute largeur (MapDrawing.placeCard())
	protected function getMapIssues(): string {

		$h = '<div class="plan-mapview-actions-issues hide-md-up">';
			$h .= '<a class="btn btn-primary" data-drawing-action="finish">'.\Asset::icon('check-lg').' <span data-drawing-finish>'.s("Enregistrer").'</span></a>';
			$h .= '<a class="btn btn-outline-primary" data-drawing-action="cancel">'.s("Annuler").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ouvre le tracé du contour d'une parcelle sur la carte : MapDrawing y lit la forme à tracer,
	 * celle qu'il retrace, ce qu'il masque de la carte pendant le geste et la route qui l'enregistre.
	 */
	protected function getMapZoneDrawing(Zone $eZone, string $class, string $label): string {
		return '<a '.$this->getMapZoneDrawingAttributes($eZone).' class="'.$class.'">'.$label.'</a>';
	}

	protected function getMapZoneDrawingAttributes(Zone $eZone): string {

		$h = 'data-drawing="polygon" data-drawing-rectangle data-drawing-free data-drawing-pac data-drawing-sheet="zone" data-drawing-map="'.self::MAP.'" data-drawing-url="/map/zone:doUpdateShape" data-drawing-target="zone" data-drawing-mask="zones zoneLabels"';
		$h .= ' data-drawing-id="'.$eZone['id'].'" '.attr('data-drawing-name', $eZone['name']).' '.attr('data-drawing-area', $eZone->getArea()).' '.attr('data-drawing-coordinates', json_encode($eZone['coordinates']));
		$h .= $this->getMapDrawingError('Zone::coordinates.redraw');

		return $h;

	}

	/**
	 * L'étape du pas à pas d'une parcelle, NULL quand elle n'en ouvre pas : rien ne lui reste à tracer, ou
	 * l'utilisateur ne peut pas tracer. Elle se lit sur la parcelle à chaque rendu, rien n'en est retenu.
	 */
	protected function getMapStep(\structure\Structure $eStructure, Zone $eZone): ?string {

		if($eStructure->canManage() === FALSE or $eZone['remaining'] === 0) {
			return NULL;
		}

		if($eZone['coordinates'] === NULL) {
			return self::STEP_ZONE;
		}

		if($eZone['ePlotNext']['zoneFill'] === FALSE and $eZone['ePlotNext']['coordinates'] === NULL) {
			return self::STEP_PLOT;
		}

		return self::STEP_BEDS;

	}

	// Ce qui reste à tracer dans une parcelle qui a sa forme. Ses jardins d'abord, quel que soit leur type : le pas à pas les réclame avant leurs planches
	protected function getMapRemaining(Zone $eZone): string {

		$plots = $eZone['plotsUndrawn'][Plot::OPEN_FIELD] + $eZone['plotsUndrawn'][Plot::GREENHOUSE];

		if($plots > 0) {
			return p("{value} jardin à dessiner", "{value} jardins à dessiner", $plots);
		} else {
			return p("{value} planche à dessiner", "{value} planches à dessiner", $eZone['bedsUndrawn']);
		}

	}

	// Le geste qui trace les planches d'un jardin, dit par ce qui lui reste à tracer
	// Dans un menu qui porte aussi « Modifier le jardin », le tracé se dit des planches
	protected function getDrawLabel(Plot $ePlot, bool $withBeds = FALSE): string {

		return match($ePlot['cBedUndrawn']->count()) {
			0 => $withBeds ? s("Dessiner les planches") : s("Dessiner"),
			1 => s("Dessiner la planche {value}", encode($ePlot['cBedUndrawn']->first()['name'])),
			default => s("Dessiner {value} planches", $ePlot['cBedUndrawn']->count())
		};

	}

	// Ce qui reste à dessiner dans un jardin : sa forme d'abord, sans laquelle ses planches n'ont pas où se poser.
	// Des planches posées à même la parcelle ont la forme de celle-ci, et ne se comptent qu'en planches
	protected function getMapPlotRemaining(Plot $ePlot): string {

		if($ePlot['zoneFill'] === FALSE and $ePlot['coordinates'] === NULL) {
			$remaining = s("à dessiner");
		} else if($ePlot['bedsUndrawn'] > 0) {
			$remaining = p("{value} planche à dessiner", "{value} planches à dessiner", $ePlot['bedsUndrawn']);
		} else {
			return '';
		}

		return '<span class="badge badge-neutral plan-tag">'.$remaining.'</span>';

	}

	/**
	 * Les actions d'une parcelle dessinée et désignée sans jardin, que MapPlan.refreshActions() pose au-dessus de son
	 * contour dans le même marqueur que celles d'un jardin. L'enveloppe existe pour chaque parcelle, même vide, pour la
	 * même raison que celle d'un jardin (getMapPlotActions()).
	 */
	protected function getMapZoneActions(\structure\Structure $eStructure, int $season, Zone $eZone): string {

		$h = '<div class="util-card plan-mapview-actions hide" data-zone-actions="'.$eZone['id'].'"';

		if($eStructure->canManage() === FALSE or $eZone['coordinates'] === NULL) {
			return $h.'></div>';
		}

		$h .= ' '.attr('data-anchor', json_encode($eZone['coordinates'])).'>';
			$h .= $this->getMapZoneGestures($eStructure, $season, $eZone, FALSE);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les gestes d'une parcelle, comme ceux d'un jardin (getMapPlotGestures()) : son nom, son contour, les planches posées à
	 * même la parcelle dans le bloc, que « Planches » ouvre et referme (`$bedsShown`), le cadrage sur son contour s'il est
	 * tracé, puis la roue.
	 */
	protected function getMapZoneGestures(\structure\Structure $eStructure, int $season, Zone $eZone, bool $bedsShown): string {

		$ePlotFill = $eZone['cPlot']->find(fn($ePlot) => $ePlot['zoneFill'], limit: 1, clone: FALSE);

		$h = '<div class="util-mapbar util-mapbar-labels">';
			$h .= '<b class="util-mapbar-name">'.encode($eZone['name']).'</b>';
			$h .= $this->getMapZoneDrawing($eZone, 'util-mapbar-item', \Asset::icon('pencil').'<span>'.s("Dessiner").'</span>');
			$h .= '<a '.attr('onclick', 'MapPlan.toggleBeds('.$ePlotFill['id'].', '.$eZone['id'].', true)').' class="util-mapbar-item'.($bedsShown ? ' selected' : '').'">'.\Asset::icon('layout-three-columns').'<span>'.s("Planches").'</span></a>';
			if($eZone['coordinates'] !== NULL) {
				$h .= '<a '.attr('onclick', 'MapPlan.zoomZone('.$eZone['id'].')').' class="util-mapbar-item">'.\Asset::icon('zoom-in').'<span>'.s("Zoomer").'</span></a>';
			}
			$h .= $this->getMapZoneMenu($eStructure, $season, $eZone);
		$h .= '</div>';

		return $h;

	}

	/**
	 * La roue d'une parcelle. Le jardin qui reste à tracer, s'il y en a un, vient en tête : c'est le pas suivant du dessin
	 * de la parcelle, que le bloc compte dans sa liste. Pas de titre : la rangée nomme déjà la parcelle
	 */
	protected function getMapZoneMenu(\structure\Structure $eStructure, int $season, Zone $eZone): string {

		$total = 0;

		foreach($eZone['cPlot'] as $ePlot) {
			$total += $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE)->count();
		}

		$h = '<a data-dropdown="bottom-end" class="util-mapbar-item color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';
			if($this->getMapStep($eStructure, $eZone) === self::STEP_PLOT) {
				$ePlotNext = $eZone['ePlotNext'];
				// Le nom seul : un jardin s'appelle souvent « Jardin A », et « le jardin » devant le doublerait
				$h .= $this->getMapPlotUpdate($season, $eZone, $ePlotNext, 'dropdown-item', s("Dessiner {value}", encode($ePlotNext['name'])));
				$h .= '<div class="dropdown-divider"></div>';
			}
			$h .= '<a href="/map/zone:update?id='.$eZone['id'].'&season='.$season.'&origin='.self::VIEW_MAP.'" class="dropdown-item">'.s("Modifier la parcelle").'</a>';
			if($total > 0) {
				$h .= $this->getMapDrawMenu($eStructure, $season, $eZone);
			}
			// Le même geste que le « + » de la liste des jardins (getMapPlotAdd()), qui demande lui aussi un contour où poser le jardin
			if($eStructure->canManage() and $eZone['coordinates'] !== NULL) {
				$h .= '<div class="dropdown-divider"></div>';
				$h .= $this->getMapPlotCreate($eZone, Plot::OPEN_FIELD, 'dropdown-item', s("Ajouter un jardin en plein champ"));
				$h .= $this->getMapPlotCreate($eZone, Plot::GREENHOUSE, 'dropdown-item', s("Ajouter un abri"));
			}
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/map/zone:doDelete" post-id="'.$eZone['id'].'" post-season="'.$season.'" post-origin="'.self::VIEW_MAP.'" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de la parcelle ?").'">'.s("Supprimer la parcelle").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les actions d'un jardin désigné, que MapPlan.refreshActions() pose sur la carte au-dessus de son contour, ou de celui
	 * de sa parcelle tant qu'il n'en a pas : la page les garde cachées, une par jardin, et la carte n'en montre qu'une à
	 * la fois. Ses planches ouvertes, il a les siennes au troisième niveau (DrawUi::getCard()), et des planches posées à même
	 * la parcelle n'ont que celles de la parcelle.
	 */
	public function getMapPlotActions(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlot): string {

		// L'enveloppe existe pour chaque jardin, même vide : une réponse qui donne un contour à sa parcelle la remplace
		// (getMapZoneNodes()), et ne saurait où insérer un nœud absent de la page
		$h = '<div class="util-card plan-mapview-actions hide" data-plot-actions="'.$ePlot['id'].'"';

		if($eStructure->canManage() === FALSE or $ePlot['zoneFill']) {
			return $h.'></div>';
		}

		$anchor = ($ePlot['coordinates'] === NULL) ? $eZone['coordinates'] : $ePlot['coordinates'];

		// Sans contour où se poser, le jardin attend celui de sa parcelle, que son clic dans la liste lance
		if($anchor === NULL) {
			return $h.'></div>';
		}

		$h .= ' '.attr('data-anchor', json_encode($anchor)).'>';
			$h .= $this->getMapPlotGestures($season, $eZone, $ePlot, FALSE);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le contour d'un jardin ou d'un abri se trace sur la carte, et dans son formulaire tant que sa parcelle n'a pas de contour
	 * où le poser (Plot::acceptUpdateShape()).
	 */
	protected function getMapPlotUpdate(int $season, Zone $eZone, Plot $ePlot, string $class, string $label, bool $bedsShown = FALSE): string {

		if($ePlot->assertUpdateShape()) {
			return $this->getMapPlotDrawing($eZone, $ePlot, $class, $label, $bedsShown);
		}

		return '<a href="/map/plot:update?id='.$ePlot['id'].'&season='.$season.'&origin='.self::VIEW_MAP.'" class="'.$class.'">'.$label.'</a>';

	}

	/**
	 * Les gestes d'un jardin, au-dessus de son contour, ou de celui de sa parcelle tant qu'il n'en a pas : son nom, puis son
	 * tracé, ses planches dans le bloc, le cadrage sur son contour s'il est tracé, puis la roue. « Planches » est enfoncé quand ses planches sont ouvertes (`$bedsShown`), et
	 * les referme alors (MapPlan.toggleBeds()).
	 */
	protected function getMapPlotGestures(int $season, Zone $eZone, Plot $ePlot, bool $bedsShown): string {

		$h = '<div class="util-mapbar util-mapbar-labels">';
			$h .= '<b class="util-mapbar-name">'.$this->getPlotName($ePlot).'</b>';
			$h .= $this->getMapPlotUpdate($season, $eZone, $ePlot, 'util-mapbar-item', \Asset::icon('pencil').'<span>'.s("Dessiner").'</span>', $bedsShown);
			$h .= '<a '.attr('onclick', 'MapPlan.toggleBeds('.$ePlot['id'].', '.$eZone['id'].')').' class="util-mapbar-item'.($bedsShown ? ' selected' : '').'">'.\Asset::icon('layout-three-columns').'<span>'.s("Planches").'</span></a>';
			if($ePlot['coordinates'] !== NULL) {
				$h .= '<a '.attr('onclick', 'MapPlan.zoomPlot('.$ePlot['id'].')').' class="util-mapbar-item">'.\Asset::icon('zoom-in').'<span>'.s("Zoomer").'</span></a>';
			}
			$h .= $this->getMapPlotMenu($season, $ePlot, $this->getMapPlotModify($season, $ePlot));
		$h .= '</div>';

		return $h;

	}

	protected function getMapPlotModify(int $season, Plot $ePlot): string {
		return '<a href="/map/plot:update?id='.$ePlot['id'].'&season='.$season.'&origin='.self::VIEW_MAP.'" class="dropdown-item">'.s("Modifier le jardin").'</a>';
	}

	// La roue d'un jardin sur la carte : ses actions (`more`), puis sa suppression, qui rend la main à sa parcelle. Des planches
	// posées à même la parcelle n'ont pas de jardin à supprimer. Pas de titre : la roue se pose toujours à droite du nom
	protected function getMapPlotMenu(int $season, Plot $ePlot, string $more): string {

		$h = '<a data-dropdown="bottom-end" class="util-mapbar-item color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';
			$h .= $more;
			if($ePlot['zoneFill'] === FALSE) {
				$h .= '<div class="dropdown-divider"></div>';
				$h .= '<a data-ajax="/map/plot:doDelete" post-id="'.$ePlot['id'].'" post-season="'.$season.'" post-origin="'.self::VIEW_MAP.'" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de ce jardin ?").'">'.s("Supprimer le jardin").'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ouvre le tracé du contour d'un jardin. Sa fiche se pose au-dessus du contour, qu'elle suit quand on le déplace,
	 * ou, pour un jardin qui n'en a pas encore, au-dessus de celui de sa parcelle (`data-drawing-anchor`), là où étaient ses actions.
	 */
	protected function getMapPlotDrawing(Zone $eZone, Plot $ePlot, string $class, string $label, bool $bedsShown = FALSE): string {

		// Un jardin se retrace en contour ou en rectangle, comme à sa création ; un abri garde le contour qu'il a
		$h = '<a data-drawing="polygon"'.match($ePlot['mode']) {
			Plot::OPEN_FIELD => ' data-drawing-rectangle data-drawing-free',
			Plot::GREENHOUSE => ''
		}.' data-drawing-sheet="plot" data-drawing-map="'.self::MAP.'" data-drawing-url="/map/plot:doUpdateShape" data-drawing-target="plot" data-drawing-mask="plots plotLabels"';
		$h .= ' data-drawing-id="'.$ePlot['id'].'" '.attr('data-drawing-name', $ePlot['name']).' '.attr('data-drawing-area', $ePlot->getArea());
		$h .= ' '.attr('data-drawing-coordinates', json_encode($ePlot['coordinates']));
		$h .= $this->getMapDrawingError('Plot::coordinates.redraw');
		if($ePlot['coordinates'] === NULL) {
			$h .= ' '.attr('data-drawing-anchor', json_encode($eZone['coordinates']));
		}
		// Le contour qu'on tire d'un bloc s'aimante aux bords de sa parcelle
		$h .= ' '.attr('data-drawing-contour', json_encode($eZone['coordinates']));
		// Tiré depuis ses planches ouvertes, le jardin les y retrouve une fois enregistré (plot:doUpdateShape), comme à l'annulation
		if($bedsShown) {
			$h .= ' data-drawing-beds';
		}
		$h .= ' class="'.$class.'">';
			$h .= $label;
		$h .= '</a>';

		return $h;

	}

	protected function getMapDrawMenu(\structure\Structure $eStructure, int $season, Zone $eZone): string {

		$ePlot = $eZone['cPlot']->find(fn($ePlot) => $ePlot['zoneFill'], limit: 1, clone: FALSE);

		if($ePlot->empty() or $ePlot['beds'] === 0) {
			return '';
		}

		$h = '<div class="dropdown-divider"></div>';
		$h .= '<a '.attr('onclick', 'MapPlan.drawPlot('.$ePlot['id'].', '.$eZone['id'].', true)').' class="dropdown-item">'.match($ePlot['bedsUndrawn']) {
			0 => $this->getDrawLabel($ePlot, withBeds: TRUE),
			default => s("Dessiner les planches de la parcelle")
		}.'</a>';

		return $h;

	}

	// Le titre ne porte que la surface ; les dimensions d'un abri vivent dans son
	// menu, en sous-titre, là où on va pour les modifier.
	protected function getSchemaPlotSummary(Plot $ePlot): string {

		if($ePlot['beds'] > 0) {
			return s("{value} m²", $ePlot['bedsArea']);
		}

		return '';

	}

	protected function getPlotDimensions(Plot $ePlot): string {

		if($ePlot['mode'] !== Plot::GREENHOUSE or $ePlot['length'] === NULL or $ePlot['width'] === NULL) {
			return '';
		}

		return s("{length} × {width} m", $ePlot);

	}

	public function getPlan(\structure\Structure $eStructure, int $season, \Collection $cZone, Zone $eZoneSelected, Plot $ePlotSelected, bool $table = FALSE): string {

		$canManage = ($eStructure->canManage() and $this->print === FALSE);

		$h = '<div class="plan-schema '.($this->print ? 'plan-schema-print' : '').'" id="plan-container" data-season="'.$season.'" data-origin="'.self::VIEW_PLAN.'" data-batch="#plan-batch">';

			foreach($cZone as $eZone) {
				$h .= $this->getPlanZone($eStructure, $season, $eZone, $ePlotSelected, $table);
			}

		$h .= '</div>';

		if($canManage) {
			$h .= $this->getPlanToolbar($season);
		}

		if($ePlotSelected->notEmpty()) {
			$h .= '<script>document.ready(() => qs("#plan-plot-'.$ePlotSelected['id'].'", node => node.scrollIntoView({block: "center"})));</script>';
		} else if($eZoneSelected->notEmpty()) {
			$h .= '<script>document.ready(() => qs("#plan-zone-'.$eZoneSelected['id'].'", node => node.scrollIntoView({block: "start"})));</script>';
		}

		return $h;

	}

	public function getPlanZone(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlotSelected, bool $table = FALSE, string $map = ''): string {

		$canManage = ($eStructure->canManage() and $this->print === FALSE);

		$h = '<div class="plan-schema-zone" id="plan-zone-'.$eZone['id'].'">';

			$h .= '<div class="plan-schema-zone-header">';
				$h .= '<span class="plan-schema-zone-name">'.encode($eZone['name']).'</span>';
				$h .= '<span class="plan-schema-zone-summary">'.$eZone->getArea().'</span>';
				if($this->print === FALSE) {
					$h .= '<div class="plan-schema-zone-actions">';
						$h .= '<a href="'.self::url($eStructure, $season, self::VIEW_MAP).'?zone='.$eZone['id'].'" class="btn btn-only-icon btn-transparent-light" title="'.s("Voir sur la carte").'">'.\Asset::icon('map').'</a>';
						if($canManage) {
							$h .= '<a data-dropdown="bottom-end" class="btn btn-transparent-light dropdown-toggle">'.\Asset::icon('plus').' '.s("Jardin").'</a>';
							$h .= '<div class="dropdown-list bg-primary">';
								$h .= '<a href="/map/plot:create?zone='.$eZone['id'].'&season='.$season.'&mode='.Plot::OPEN_FIELD.'" class="dropdown-item">'.s("Plein champ").'</a>';
								$h .= '<a href="/map/plot:create?zone='.$eZone['id'].'&season='.$season.'&mode='.Plot::GREENHOUSE.'" class="dropdown-item">'.s("Abri").'</a>';
							$h .= '</div>';
							$h .= $this->getZoneMenu($eStructure, $season, $eZone);
						}
					$h .= '</div>';
				}
			$h .= '</div>';

			$h .= $map;

			$h .= '<div class="plan-schema-plots" data-zone="'.$eZone['id'].'">';

				foreach($eZone['cPlot'] as $ePlot) {

					$cBed = $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE);

					if(
						$ePlot['zoneFill'] and
						$cBed->empty() and
						$eZone['cPlot']->count() > 1
					) {
						continue;
					}

					$selected = ($ePlotSelected->notEmpty() and $ePlot['id'] === $ePlotSelected['id']);

					$h .= $this->getPlanPlot($eStructure, $season, $eZone, $ePlot, $cBed, $selected, ($selected and $table));

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getZoneMenu(\structure\Structure $eStructure, int $season, Zone $eZone): string {

		$h = '<a data-dropdown="bottom-end" class="btn btn-transparent-light dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';
			$h .= '<div class="dropdown-title">'.encode($eZone['name']).'</div>';
			// Le contour se trace sur la carte, où « Dessiner la parcelle » est le geste qui lance le tracé d'une parcelle sans contour
			$h .= '<a href="'.self::url($eStructure, $season, self::VIEW_MAP).'?zone='.$eZone['id'].($eZone['coordinates'] === NULL ? '&draw=1' : '').'" class="dropdown-item">'.($eZone['coordinates'] === NULL ? s("Dessiner la parcelle") : s("Modifier le contour")).'</a>';
			$h .= '<a href="/map/zone:update?id='.$eZone['id'].'&season='.$season.'" class="dropdown-item">'.s("Modifier la parcelle").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/map/zone:doDelete" post-id="'.$eZone['id'].'" post-season="'.$season.'" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de la parcelle ?").'">'.s("Supprimer la parcelle").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getPlanPlot(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlot, \Collection $cBed, bool $selected, bool $table): string {

		$canManage = ($eStructure->canManage() and $this->print === FALSE);
		$canDraw = $ePlot['drawable'];

		$groups = $this->groupBeds($cBed);
		$colors = ($ePlot['mode'] === Plot::GREENHOUSE) ? self::FORMAT_COLORS_GREENHOUSE : self::FORMAT_COLORS_FIELD;

		$byFormat = (count($groups) <= MapSetting::$planFormatsMax);

		$h = '<div class="util-card plan-schema-plot '.($ePlot['mode'] === Plot::GREENHOUSE ? 'plan-schema-plot-greenhouse' : '').' '.($selected ? 'selected' : '').' '.($table ? 'plan-schema-plot-table' : '').'" id="plan-plot-'.$ePlot['id'].'" data-plot="'.$ePlot['id'].'" data-draw="'.($canDraw ? 1 : 0).'" data-draw-url="'.DrawUi::urlPlot($eStructure, $season, $ePlot).'">';

			$h .= '<div class="util-card-title plan-schema-plot-header">';
				if($canManage) {
					$h .= '<span class="plan-item-name plan-schema-plot-handle" draggable="true" title="'.s("Glisser pour déplacer").'">'.\Asset::icon('grip-vertical').' '.$this->getPlotName($ePlot).'</span>';
				} else {
					$h .= '<span class="plan-item-name">'.$this->getPlotName($ePlot).'</span>';
				}
				$h .= $this->getPlotTag($ePlot);
				$h .= '<span class="util-card-info">'.$this->getSchemaPlotSummary($ePlot).'</span>';
				if($canManage and $ePlot['zoneFill'] === FALSE) {
					$h .= '<span class="plan-schema-plot-menu">'.$this->getPlotMenu($season, $eZone, $ePlot).'</span>';
				}
			$h .= '</div>';

			$h .= '<div class="plan-schema-plot-body">';
				if($cBed->count() >= 3) {
					$h .= '<div class="plan-schema-count">'.p("{value} planche", "{value} planches", $cBed->count()).'</div>';
				}
				$add = '';

				if($canManage) {

					// Sans aucune planche, le même lien est un bouton au milieu du jardin,
					// avec son icône ; en fin de rang, le libellé suit l'écriture
					// verticale des noms de planche, signe compris.
					$text = $cBed->empty() ? s("Ajouter des planches") : s("Planche");

					$add = '<a href="/map/bed:create?plot='.$ePlot['id'].'&season='.$season.'" class="plan-schema-bed plan-schema-bed-add" title="'.s("Ajouter des planches").'">';
						$add .= '<span>'.\Asset::icon('plus-circle').'<span>'.$text.'</span></span>';
					$add .= '</a>';

				}

				$h .= $this->getPlanBeds($cBed, $groups, $colors, $canManage, $byFormat, $add);
			$h .= '</div>';

			$h .= '<div class="plan-schema-plot-footer">';

				if($cBed->notEmpty() and $byFormat) {

					$h .= '<div class="plan-schema-formats">';
						foreach(array_values($groups) as $index => ['eBed' => $eBedGroup, 'cBed' => $cBedGroup]) {
							$h .= '<span class="plan-schema-format">';
								$h .= '<i style="background-color: '.$colors[$index % count($colors)].'"></i>';
								$h .= $this->getGroupLabel($eBedGroup);
								$h .= ' <span class="color-muted">'.$this->getGroupDetails($eBedGroup).'</span>';
								if(count($groups) > 1) {
									$h .= ' <span class="util-counter">'.$cBedGroup->count().'</span>';
								}
							$h .= '</span>';
						}
					$h .= '</div>';

				}

				$h .= '<div class="util-card-buttons plan-schema-plot-buttons">';
					if($cBed->notEmpty() and $this->print === FALSE) {
						if($table) {
							$h .= '<a href="'.self::urlZone($eStructure, $season, $eZone, $ePlot).'" data-ajax-navigation="never" '.attr('onclick', 'MapPlan.toggleSheet('.$eZone['id'].', '.$ePlot['id'].', "table", false); event.stopPropagation(); return false;').' class="btn btn-sm btn-primary">'.\Asset::icon('x-lg').' '.s("Tableau").'</a>';
						} else {
							$h .= '<a href="'.self::urlZone($eStructure, $season, $eZone, $ePlot, table: TRUE).'" data-ajax-navigation="never" '.attr('onclick', 'MapPlan.toggleSheet('.$eZone['id'].', '.$ePlot['id'].', "table", true); event.stopPropagation(); return false;').' class="btn btn-sm btn-outline-primary">'.\Asset::icon('table').' '.s("Tableau").'</a>';
						}
						// Une demande de dessin : l'adresse coche les planches qui restent à tracer
						if($canManage and $canDraw) {
							$h .= '<a href="'.DrawUi::urlPlot($eStructure, $season, $ePlot, $ePlot['cBedUndrawn']->getIds()).'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('geo-fill').' '.$this->getDrawLabel($ePlot).'</a>';
						}
					}
				$h .= '</div>';

			$h .= '</div>';

			if($table) {
				$h .= '<div class="plan-schema-plot-sheet">';
					$h .= $this->getBedsByTable($eStructure, $season, $ePlot, $cBed, $canDraw);
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getPlanBeds(\Collection $cBed, array $groups, array $colors, bool $canManage, bool $byFormat, string $add = ''): string {

		if($cBed->empty()) {
			return '<div class="plan-schema-beds plan-schema-beds-empty">'.($add ?: s("sans planche")).'</div>';
		}

		$cBed->sort('name', natural: TRUE);

		$colorByBed = [];

		foreach(array_values($groups) as $index => ['cBed' => $cBedGroup]) {
			foreach($cBedGroup as $eBed) {
				$colorByBed[$eBed['id']] = $byFormat ? $colors[$index % count($colors)] : $colors[0];
			}
		}

		$widths = array_filter($cBed->getColumn('width'));
		$defaultWidth = $widths ? (int)round(array_sum($widths) / count($widths)) : 80;

		$h = '<div class="plan-schema-beds">';

			foreach($cBed as $eBed) {

				$title = encode($eBed['name']);

				if($eBed['length'] !== NULL and $eBed['width'] !== NULL) {
					$title .= ' · '.s("{length} m × {width} cm", $eBed);
				}

				if($eBed['alleyWidth'] !== NULL) {
					$title .= ' · '.s("passe-pied {value} cm", $eBed['alleyWidth']);
				}

				$style = 'width: calc('.($eBed['width'] ?? $defaultWidth).' * var(--plan-scale));';
				$style .= ' background-color: '.$colorByBed[$eBed['id']].';';

				$attributes = ' data-bed="'.$eBed['id'].'" data-name="'.encode($eBed['name']).'" data-drawn="'.($eBed['drawn'] ? 1 : 0).'"';

				if($canManage) {
					$attributes .= ' '.attr('onclick', 'MapPlan.clickBed(this, event)').' '.attr('onpointerdown', 'MapPlan.startDrag(this, event)');
				}

				$label = encode($eBed['name']);

				if($byFormat === FALSE and $eBed['length'] !== NULL and $eBed['width'] !== NULL) {
					$label .= '<small>'.s("{length} m × {width} cm", $eBed).'</small>';
				}

				$h .= '<div class="plan-schema-bed" style="'.$style.'" title="'.$title.'"'.$attributes.'>';
					if($canManage) {
						$h .= '<input type="checkbox" name="batch[]" value="'.$eBed['id'].'" data-batch="'.($eBed['drawn'] ? 'drawn' : '').'" class="hide"/>';
					}
					$h .= '<span>'.$label.'</span>';
				$h .= '</div>';

			}

			// Ajouter des planches se fait là où elles apparaîtront : une planche
			// fictive en fin de rang, plutôt qu'un bouton sous le schéma.
			$h .= $add;

		$h .= '</div>';

		return $h;

	}

	protected function getPlanToolbar(int $season): string {

		$menu = '<a id="plan-batch-update" href="" class="batch-item">'.\Asset::icon('pencil-fill').'<span>'.s("Modifier").'</span></a>';
		$menu .= '<a data-ajax-submit="/map/bed:updateSizeCollection" data-ajax-method="get" class="batch-item">'.\Asset::icon('rulers').'<span>'.s("Dimensions").'</span></a>';
		$menu .= '<a data-ajax-submit="/map/bed:updateSeasonCollection" data-ajax-method="get" class="batch-item">'.\Asset::icon('calendar3').'<span>'.s("Saisons").'</span></a>';
		$menu .= '<a data-ajax-submit="/map/bed:updatePlotCollection" data-ajax-method="get" class="batch-item">'.\Asset::icon('arrow-left-right').'<span>'.s("Déplacer").'</span></a>';
		// MapPlan.refreshSelection() y écrit l'adresse du jardin sur la vue Carte, avec les planches choisies
		$menu .= '<a id="plan-batch-draw" href="" class="batch-item">'.\Asset::icon('geo-fill').'<span>'.s("Dessiner").'</span></a>';
		$menu .= '<a data-batch-test="drawn" data-batch-not-contains="hide" data-ajax-submit="/map/bed:doDeleteBedLineCollection" data-ajax-method="post" data-confirm="'.s("Le dessin des planches sélectionnées sera effacé de la carte, voulez-vous continuer ?").'" class="batch-item">'.\Asset::icon('geo').'<span>'.s("Effacer le dessin").'</span></a>';

		$danger = '<a data-ajax-submit="/map/bed:doDeleteCollection" data-ajax-method="post" data-confirm="'.s("Êtes-vous sûr de vouloir supprimer les planches sélectionnées ? Si vous souhaitez conserver un historique de votre plan de ferme, modifiez plutôt les saisons d'exploitation.").'" class="batch-item batch-item-danger">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';

		return \util\BatchUi::group('plan-batch', $menu, $danger, title: s("Pour les planches sélectionnées"), hide: 'MapPlan.clearSelection()');

	}

}
?>
