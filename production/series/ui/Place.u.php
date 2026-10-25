<?php
namespace series;

class PlaceUi {

	const FAMILY_FALLBACK = '#3d3d3d';

	public static function getFamilyColor(Cultivation $eCultivation): string {

		$eFamily = $eCultivation['plant']['family'];

		if(
			$eFamily->notEmpty() and
			$eFamily['color'] and
			strtolower($eFamily['color']) !== '#000000'
		) {
			return $eFamily['color'];
		}

		return self::FAMILY_FALLBACK;

	}

	public static function getPaint(Series|Task $ePlaced): ?string {

		if($ePlaced->empty()) {
			return NULL;
		}

		if($ePlaced instanceof Task) {
			return ($ePlaced['action'] ?? new \work\Action())->notEmpty() ? $ePlaced['action']['color'] : NULL;
		}

		return ($ePlaced['cCultivation'] ?? new \Collection())->first()['plant']['color'] ?? NULL;

	}

	public static function isPlaceOf(Place $ePlace, Series|Task $ePlaced): bool {

		if($ePlaced->empty()) {
			return FALSE;
		}

		if($ePlaced instanceof Task) {
			return ($ePlace['task']->notEmpty() and $ePlace['task']->is($ePlaced));
		}

		return ($ePlace['series']->notEmpty() and $ePlace['series']->is($ePlaced));

	}

	public static function isLocked(\series\Series $eSeries, int $season): bool {

		return (
			$eSeries['status'] === Series::CLOSED or
			$eSeries['season'] !== $season
		);

	}

	public function __construct() {

		\Asset::css('series', 'place.css');
		\Asset::js('series', 'place.js');
		// Les réponses ajax du plan évaluent SeriesSelector, même là où le bac
		// n'est pas affiché : sans la classe, la première instruction lève et
		// toutes les suivantes tombent, dont le rendu du jardin.
		\Asset::js('series', 'seriesSelector.js');

	}

	public function update(\structure\Structure $eStructure, string $source, Series|Task $e, \Collection $cZone, \Search $search, string $viewSoil = \structure\Member::PLAN, \map\Zone $eZoneSelected = new \map\Zone(), ?string $origin = NULL): \Panel {

		$e->expects(['cPlace']);

		$title = match($source) {
			'series' => match($e['use']) {
				Series::BED => s("Assolement sur planches"),
				Series::BLOCK => s("Assolement sur surface libre")
			},
			'task' => s("Assolement")
		};

		$subTitle = match($source) {
			'series' => SeriesUi::getPanelHeader($e),
			'task' => TaskUi::getPanelHeader($e)
		};

		if($cZone->empty()) {

			$h = '<div class="util-block-info">';
				$h .= '<p>';
					$h .= match($source) {
						'series' => s("Vous ne pouvez pas assoler cette série car vous n'avez pas encore configuré vos parcelles et vos planches de culture."),
						'task' => s("Vous ne pouvez pas assoler cette intervention car vous n'avez pas encore configuré vos parcelles et vos planches de culture.")
					};
				$h .= '</p>';
				$h .= '<a href="'.\structure\StructureUi::urlSoil($eStructure, $e['season']).'" class="btn btn-secondary">'.s("Configurer mes emplacements").'</a>';
			$h .= '</div>';

			return new \Panel(
				id: 'panel-place-update',
				title: $title,
				subTitle: $subTitle,
				body: $h,
			);

		}

		$h = self::getLabels();

		// Positionné avant pour récupérer les alertes
		$places = ($viewSoil === \structure\Member::GARDEN) ?
			new PlaceGardenAiUi()->setPlaced($e)->getGarden($eStructure, $cZone, $eZoneSelected, $e['season']) :
			new \map\ZoneUi()->setPlaced($e)->getPlan($eStructure, $cZone, $eZoneSelected, $e['season']);

		if($e['use'] === Series::BED) {

			if($source === 'series') {

				if($e['bedStartCalculated'] === NULL or $e['bedStopCalculated'] === NULL) {

					$h .= '<div class="util-warning">';
						$h .= \Asset::icon('exclamation-circle-fill').' '.s("Pensez à renseigner sur la série {value} les dates de semis direct ou de plantation ainsi que les périodes de récolte attendues afin qu'elle s'affiche au bon endroit sur le diagramme de temps.", SeriesUi::link($e, TRUE));
					$h .= '</div>';

				}

				$h .= $this->getPlaceSearch($e, $search, inline: TRUE, origin: $origin);

			}

		}

		$h .= '<div class="tabs tabs-lg soil-views" onrender="MapZone.measureSoilViews()">';
			$h .= '<div class="tabs-item-wrapper">';
				$h .= '<div class="tabs-item">';

					foreach([
						\structure\Member::GARDEN => s("Plan"),
						\structure\Member::PLAN => s("Liste"),
					] as $viewKey => $viewLabel) {

						$url = '/series/place:update?'.$source.'='.$e['id'].'&view='.$viewKey.($origin === NULL ? '' : '&origin='.$origin);

						$h .= '<a onclick="new Ajax.Query().url(\''.$url.'\' + SeriesPlace.filters() + SeriesUngroup.param()).method(\'get\').fetch()" class="tab-item '.($viewKey === $viewSoil ? 'selected' : '').'">'.$viewLabel.'</a>';

					}

				$h .= '</div>';
				$h .= '<div class="tabs-item-label soil-views-aside">'.new \map\ZoneUi()->setPlaced($e)->getPlanCorner($eStructure, $cZone, $eZoneSelected, $e['season']).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		// Le raccourci de saut vit sur un observateur posé au rendu : la page du
		// panneau ne passe par aucune des vues qui l'arment.
		// L'origine se pose sur le panneau plutôt que d'être relue dans l'adresse : les
		// onglets et le filtre rejouent place:update, et c'est de ce nœud que les
		// écritures suivantes la reprennent.
		$h .= '<div id="place-update" class="place-update-live"'.($origin === NULL ? '' : ' data-origin="'.$origin.'"').' onrender="SeriesResize.updateJumpers(); SeriesPlace.search(); SeriesPlace.centerOnSeries()">'.$places.'</div>';

		return new \Panel(
			id: 'panel-place-update',
			title: $title,
			subTitle: $subTitle,
			body: $h,
			footer: $this->getSubmit($source, $e),
			close: INPUT('close', ['reloadIgnoreCascade', 'passthrough'], 'passthrough')
		);

	}

	/**
	 * Le pied du panneau, rendu aussi seul par les gestes du plan (`doTargetAi`, `doMove`) : ils écrivent
	 * l'assolement sans recharger le panneau, et le pied resterait sinon à la valeur lue à son ouverture.
	 */
	public function getSubmit(string $source, Series|Task $e): string {

		$h = '<div class="place-update-submit">';

			if($source === 'series') {

				switch($e['use']) {

					case Series::BED;
						$h .= s("Sélectionné : {value} mL", '<span id="place-update-value">'.($e['length'] ?? 0).'</span>');
						if($e['lengthTarget']) {
							$h .= s(" / Objectif : {value} mL", $e['lengthTarget']);
						}
						break;

					case Series::BLOCK;
						$h .= s("Sélectionné : {value} m²", '<span id="place-update-value">'.($e['area'] ?? 0).'</span>');
						if($e['areaTarget']) {
							$h .= s(" / Objectif : {value} m²", $e['areaTarget']);
						}
						break;

				}

			}

		$h .= '</div>';

		return $h;

	}

	public function getPlaceSearch(Series $eSeries, \Search $search, bool $inline = FALSE, ?string $origin = NULL): string {

		\Asset::js('series', 'place.js');

		$form = new \util\FormUi();

		$hasDates = (
			$eSeries['bedStartCalculated'] !== NULL and
			$eSeries['bedStopCalculated'] !== NULL
		);

		$h = '<div id="place-search" class="place-search'.($inline ? ' place-search-inline' : '').'">';

			// Le titre n'apparaît que dans la fiche : sur place:update les filtres
			// tiennent sur une seule ligne, et une ligne de titre au-dessus
			// reprendrait la hauteur qu'on venait de rendre au plan.
			if($inline === FALSE) {
				$h .= '<div class="place-search-title">'.s("Filtrer les planches").'</div>';
			}

			$h .= $form->openAjax(LIME_REQUEST_PATH, ['method' => 'get', 'class' => 'place-search-form']);
				$h .= $form->hidden('search', 1);
				$h .= $form->hidden('series', $eSeries['id']);

				// Le formulaire rejoue place:update : sans ce report, filtrer les
				// planches ferait perdre l'origine au panneau.
				if($origin !== NULL) {
					$h .= $form->hidden('origin', $origin);
				}

				$h .= $this->getPlaceChip($form, 'mode', s("Mode"), [
					NULL => s("Plein champ et tunnel"),
					\map\Plot::OPEN_FIELD => s("Plein champ"),
					\map\Plot::GREENHOUSE => s("Abri"),
				], $search->get('mode'), neutral: NULL);

				if($search->get('canWidth')) {

					$h .= $this->getPlaceChip($form, 'width', $inline ? s("Largeur") : s("Largeur de planche"), [
						0 => s("Toutes"),
						1 => s("{value} cm", $eSeries['bedWidth']),
					], (int)$search->get('width'), neutral: 0);

				}

				if($hasDates) {

					$h .= $this->getPlaceChip($form, 'free', s("Planches libres"), [
						0 => s("Peu importe"),
						100 => s("Oui"),
						1 => s("À ± 1 semaine"),
						2 => s("À ± 2 semaines"),
					], $search->get('available'), neutral: 0);

				}

				$h .= $this->getPlaceChip($form, 'rotation', $inline ? s("Rotation") : s("Rotation par famille"), [
					0 => s("Peu importe"),
					2 => s("2 ans"),
					3 => s("3 ans"),
					4 => s("4 ans"),
					5 => s("5 ans"),
				], $search->get('rotation'), neutral: 0);

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	protected function getPlaceChip(\util\FormUi $form, string $name, string $label, array $options, mixed $current, mixed $neutral): string {

		$current = array_key_exists((string)$current, array_change_key_case($options)) ? $current : $neutral;

		$value = $options[$current] ?? reset($options);

		// Comparaison sur la chaîne : le mode a NULL pour valeur neutre et revient
		// du formulaire en chaîne vide, si bien que « Plein champ et tunnel » se
		// peignait comme un filtre actif alors qu'il ne filtre rien.
		$isActive = ((string)$current !== (string)$neutral);

		$h = '<div class="place-search-filter">';

			$h .= '<a class="util-chip place-chip'.($isActive ? ' is-solid place-chip-on' : '').'" data-dropdown="bottom-start">';
				$h .= '<span class="place-chip-label">'.$label.'</span>';
				$h .= '<span class="place-chip-value">'.$value.'</span>';
			$h .= '</a>';

			$h .= '<div class="dropdown-list">';

				foreach($options as $key => $option) {
					$h .= '<a onclick="SeriesPlace.setFilter(this, \''.$name.'\', \''.$key.'\')" class="dropdown-item'.((string)$key === (string)$current ? ' selected' : '').'">'.$option.'</a>';
				}

			$h .= '</div>';

			$h .= $form->select($name, $options, $current, ['required' => TRUE, 'class' => 'hide', 'onchange' => 'SeriesPlace.search()']);

		$h .= '</div>';

		return $h;

	}

	public function getPlaceContext(Series $eSeries, \Collection $cCultivation, ?\Collection $cZone = NULL, string $actions = '', ?\Search $search = NULL, string $footer = '', string $remove = ''): string {

		$isBed = ($eSeries['use'] === Series::BED);

		$value = $isBed ? ($eSeries['length'] ?? 0) : ($eSeries['area'] ?? 0);
		$target = $isBed ? $eSeries['lengthTarget'] : $eSeries['areaTarget'];
		$unit = $isBed ? s("mL") : s("m²");

		$h = '<div id="place-context" class="place-context" data-series="'.$eSeries['id'].'" data-name="'.encode($eSeries['name']).'" data-use="'.$eSeries['use'].'" data-value="'.$value.'" data-target="'.$target.'">';

			if($actions !== '') {
				$h .= '<div class="place-context-actions">'.$actions.'</div>';
			}

			if($target > 0) {

				$percent = min(100, round($value / $target * 100));

				$h .= '<div class="place-context-gauge'.(($value > $target) ? ' place-context-gauge-over' : '').'"><i style="width: '.$percent.'%"></i></div>';

			}

			$h .= '<div class="place-context-counts">';

				$h .= '<span>'.(($target > 0) ?
					s("<b>{value} / {target} {unit}</b> assolés", ['value' => $value, 'target' => $target, 'unit' => $unit]) :
					s("<b>{value} {unit}</b> assolés", ['value' => $value, 'unit' => $unit])
				).'</span>';

				// La suppression vit sur la ligne du chiffre qu'elle efface, pas
				// dans la rangée d'actions : elle défait, elle n'entreprend pas.
				$h .= $remove;

			$h .= '</div>';

			if($search !== NULL) {
				$h .= $this->getPlaceSearch($eSeries, $search);
			}

			$h .= '<div class="place-context-foot">'.$footer.'</div>';

		$h .= '</div>';

		return $h;

	}

	public static function getLockReason(Series $eSeries, int $season): ?string {

		if($eSeries['status'] === Series::CLOSED) {
			return s("Série clôturée : son assolement ne se rejoue pas.");
		}

		if($eSeries['cycle'] === Series::PERENNIAL) {
			return s("Série pérenne : elle occupe sa planche à l'année.");
		}

		if($eSeries['season'] !== $season) {
			return s("Série d'une autre saison.");
		}

		return NULL;

	}

	public static function getSearchAttributes(Series $eSeries, \Collection $cCultivation): string {

		$plants = [];
		$varieties = [];
		$tools = [];

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['plant']->notEmpty()) {
				$plants[$eCultivation['plant']['name']] = TRUE;
			}

			foreach(($eCultivation['varietyNames'] ?? []) as $name) {
				$varieties[$name] = TRUE;
			}

			foreach(($eCultivation['toolNames'] ?? []) as $name) {
				$tools[$name] = TRUE;
			}

		}

		$h = '';

		foreach([
			'plant' => array_keys($plants),
			'variety' => array_keys($varieties),
			'tool' => array_keys($tools),
		] as $group => $values) {

			if($values === []) {
				continue;
			}

			$h .= ' data-search-'.$group.'="'.encode(implode('|', $values)).'"';

		}

		return $h;

	}

	public static function getEditingTip(string $helper): string {

		if($helper === '') {
			return '';
		}

		return '<div id="place-helper" class="hide" data-helper="'.encode($helper).'">'.s("Sélectionnez une série sur le plan pour la déplacer ou changer son assolement.").'</div>';

	}

	public static function getLabels(): string {

		return '<script type="text/javascript">window.seriesPlaceLabels = '.json_encode([
			'place' => s("Ajouter"),
			'armedCopy' => s("Copier"),
			'bedUnit' => s("pl."),
			'bedOne' => s("{value} planche", '{value}'),
			'bedMany' => s("{value} planches", '{value}'),
			'lockedClosed' => s("Série clôturée"),
			'lockedSeason' => s("Série d'une autre saison"),
			'sendBack' => s("Envoyer à l'arrière-plan"),
			'poseOpen' => s("Ouvrir la série"),
			'poseClose' => s("Fermer"),
			'tokenBack' => s("← Espèces"),
			'tokenSearch' => s("Rechercher"),
			'filterHere' => s("dont {value} ici", '{value}'),
			'filterNone' => s("aucune ici"),
			'tokenSearchIcon' => \Asset::icon('search'),
			'tokenSearchBack' => \Asset::icon('arrow-left'),
			'outsideShow' => s("Afficher le hors jardin"),
			'outsideHide' => s("Masquer le hors jardin"),
			'removeBed' => s("Retirer cette planche"),
			'removeBedConfirm' => s("Cette planche sera retirée de l'assolement de la série. Continuer ?"),
			'removeBedMany' => s("Retirer ces {value} planches", '{value}'),
			'removeBedConfirmMany' => s("Ces {value} planches seront retirées de l'assolement de la série. Continuer ?", '{value}'),
			'sizeOnBed' => s("{series} sur la planche {bed}", ['series' => '{series}', 'bed' => '{bed}']),
			'sizeOnFill' => s("{series} sur la surface libre", ['series' => '{series}']),
			'sizeBedOnly' => s("Longueur à assoler · planche {bed}", ['bed' => '{bed}']),
			'sizeFree' => s("{value} libres", '{value}'),
			'sizeConfirmValue' => s("Assoler {value} {unit}", ['value' => '{value}', 'unit' => '{unit}']),
			'sizeConfirmEmpty' => s("Assoler"),
			'sizeCancel' => s("Annuler"),
			'lengthMl' => s("mL"),
			'chipEdit' => s("Régler la longueur assolée"),
			'ungroup' => s("Dissocier"),
			'group' => s("Regrouper"),
			'areaUnit' => s("m²"),
			'hiddenOne' => s("bloc à l'arrière-plan"),
			'hiddenMany' => s("blocs à l'arrière-plan"),
			'hiddenRestore' => s("Tout ramener devant"),
			'manySelected' => s("séries retenues"),
			'manyOutOfPlot' => s("Les séries retenues ne tiennent pas à cet endroit : l'emplacement d'arrivée n'a pas assez de planches."),
			'deselect' => s("Tout désélectionner"),
			'hiddenOneByOne' => s("1 planche masquée par « {value} »", '{value}'),
			'hiddenManyByOne' => s("{count} planches masquées par « {value} »", ['count' => '{count}', 'value' => '{value}']),
			'hiddenOneByMany' => s("1 planche masquée par {value} filtres", '{value}'),
			'hiddenManyByMany' => s("{count} planches masquées par {value} filtres", ['count' => '{count}', 'value' => '{value}']),
		]).';</script>';

	}

	public function getWeeksShown(\structure\Structure $eStructure, int $season): array {

		return [
			$eStructure->conf(\map\Configuration::class)['calendarMonthStart'] ? (int)date('W', strtotime(($season - 1).'-'.$eStructure->conf(\map\Configuration::class)['calendarMonthStart'].'-01')) - 100 : 0,
			$eStructure->conf(\map\Configuration::class)['calendarMonthStop'] ? 100 + (int)date('W', strtotime(($season + 1).'-'.$eStructure->conf(\map\Configuration::class)['calendarMonthStop'].'-28')) : 100,
		];

	}

	public function getTimelineLines(\structure\Structure $eStructure, \Collection $cPlace, int $season): array {

		$lines = [];
		$stops = [];

		[$firstWeekShown, $lastWeekShown] = $this->getWeeksShown($eStructure, $season);

		foreach($cPlace as $ePlace) {
			$this->positionPlace($ePlace, $season, $firstWeekShown, $lastWeekShown);
		}

		$cPlace->sort('positionStart');

		foreach($cPlace as $ePlace) {

			// Il faut au moins une date de démarrage
			if($ePlace['positionStart'] === NULL) {
				continue;
			}

			$added = FALSE;

			foreach($lines as $key => $line) {

				if(
					$ePlace['visible'] === FALSE or
					isset($stops[$key]) === FALSE or
					$ePlace['positionStart'] >= $stops[$key]
				) {

					$lines[$key][] = $ePlace;
					$added = TRUE;
					break;

				}

			}

			if($added === FALSE) {
				$key = count($lines);
				$lines[] = [$ePlace];
			}

			if($ePlace['visible']) {
				$stops[$key] = $ePlace['positionStop'];
			}

		}

		return $lines;

	}

	public function getTimelineHeight(\structure\Structure $eStructure, \Collection $cPlace, int $season, bool $print = FALSE, int $lanesMin = 0): float {

		$lanes = max($lanesMin, count($this->getTimelineLines($eStructure, $cPlace, $season)));

		if($lanes === 0) {
			return 0.0;
		}

		$basePadding = $print ? ($eStructure->getView('viewSoilTasks') ? 0.2 : 0.1) : 0.125;
		$baseGap = $print ? 0.2 : 0.125;

		if($lanes === 1) {
			return $print ? 2.2 : 2.625;
		}

		return $basePadding * 2 + $lanes * ($print ? 0.9 : 1.75) + ($lanes - 1) * $baseGap;

	}

	public function getTimeline(\structure\Structure $eStructure, \map\Bed $eBed, \Collection $cPlace, int $season, Series|Task|\Element $ePlaceholder, bool $print): string {

		$lines = $this->getTimelineLines($eStructure, $cPlace, $season);

		// Une planche vide n'a pas de ligne, mais elle a une place à offrir : sans
		// cela le fantôme du mode Poser manquait sur les surfaces libres, qui sont
		// justement celles qui ne portent encore rien.
		if($lines === [] and $ePlaceholder->empty()) {
			return '';
		}

		[$firstWeekShown, $lastWeekShown] = $this->getWeeksShown($eStructure, $season);

		$basePadding = $print ? ($eStructure->getView('viewSoilTasks') ? 0.2 : 0.1) : 0.125;
		$baseGap = $print ? 0.2 : 0.125;
		$lanes = count($lines);

		if($lanes > 1) {
			// Sur le papier, une série garde sa hauteur quel que soit le nombre de
			// séries qui se partagent la planche : c'est la planche qui grandit. La
			// réduire à 0,9 rem rendait son nom illisible dès qu'il y avait
			// compétition, c'est-à-dire dans un tiers des cas.
			$baseHeight = $print ? 1.8 : 1.75;
			$totalHeight = $basePadding * 2 + $lanes * $baseHeight + ($lanes - 1) * $baseGap;
		} else {
			$totalHeight = $print ? 2.2 : 2.625;
			$baseHeight = $totalHeight - $basePadding * 2;
		}

		$list = '';
		$gap = 0;

		$hasCurrent = FALSE;

		foreach($lines as $key => $line) {

			$positions = [];

			foreach($line as $ePlace) {

				$isCurrent = self::isPlaceOf($ePlace, $ePlaceholder);

				if($isCurrent) {
					$hasCurrent = TRUE;
				}

				$top = ($key === 0) ?
					$basePadding.'rem' :
					$basePadding.'rem + ('.$key.' * ('.$baseHeight.'rem + '.$baseGap.'rem))';

				$list .= $ePlace['series']->empty() ?
					$this->getTaskTimeline($eStructure, $eBed, $season, $ePlace, 'height: '.$baseHeight.'rem; top: calc('.$top.');', $isCurrent) :
					$this->getSeriesTimeline($eStructure, $eBed, $season, $ePlace, $ePlace['series'], $ePlace['series']['cCultivation'], FALSE, 'height: '.$baseHeight.'rem; top: calc('.$top.');', $print, $isCurrent);

			}

		}

		if($ePlaceholder->notEmpty() and $hasCurrent === FALSE) {

			$ePlace = new Place([
				'missing' => FALSE,
				'series' => $ePlaceholder instanceof Series ? $ePlaceholder : new Series(),
				'task' => $ePlaceholder instanceof Task ? $ePlaceholder : new Task(),
			]);

			$this->positionPlace($ePlace, $season, $firstWeekShown, $lastWeekShown);

			if($ePlace['positionStart'] !== NULL and $ePlace['positionStop'] !== NULL) {

				$list .= ($ePlaceholder instanceof Task) ?
					$this->getTaskTimeline($eStructure, $eBed, $season, $ePlace, 'height: '.$totalHeight.'rem; top: 0rem;', TRUE, TRUE) :
					$this->getSeriesTimeline($eStructure, $eBed, $season, $ePlace, $ePlaceholder, $ePlaceholder['cCultivation'], TRUE, 'height: '.$totalHeight.'rem; top: 0rem;', $print);

			}

		}

		if($list === '') {
			return '';
		} else {

			$h = '<div class="place-grid-series-timeline-lines" style="height: '.$totalHeight.'rem">';
				$h .= $list;
			$h .= '</div>';

			return $h;

		}

	}

	protected function positionPlaceTask(Place $ePlace, int $season): void {

		$ePlace['positionStart'] = NULL;
		$ePlace['positionStop'] = NULL;
		$ePlace['missing'] = FALSE;

		if(($ePlace['task'] ?? new Task())->empty()) {
			return;
		}

		$week = $ePlace['task']['doneWeek'] ?? $ePlace['task']['plannedWeek'] ?? NULL;

		if($week === NULL) {
			return;
		}

		$offset = (week_year($week) - $season) * 100;

		$ePlace['positionStart'] = week_number($week) + $offset;
		$ePlace['positionStop'] = week_number($week) + 1 + $offset;
		$ePlace['visible'] = TRUE;

	}

	protected function positionPlace(Place $ePlace, int $season, int $firstWeekShown, int $lastWeekShown): void {

		if($ePlace['series']->empty()) {

			$this->positionPlaceTask($ePlace, $season);

			return;

		}

		$ePlace['missing'] = FALSE;

		if(
			$ePlace['series']['cycle'] === Series::PERENNIAL and
			$ePlace['series']['perennialSeason'] > 1
		) {

			$ePlace['positionStart'] = 1 + ($ePlace['series']['season'] - $season) * 100;

		} else {

			$ePlace['positionStart'] = $ePlace['series']['bedStartCalculated'] !== NULL ?
				($ePlace['series']['bedStartCalculated'] + ($ePlace['series']['season'] - $season) * 100) :
				NULL;

		}

		if(
			$ePlace['series']['cycle'] === Series::PERENNIAL and
			($ePlace['series']['perennialLifetime'] === NULL or $ePlace['series']['perennialSeason'] < $ePlace['series']['perennialLifetime'])
		) {

			$ePlace['positionStop'] = 52 + ($ePlace['series']['season'] - $season) * 100;

		} else {

			$ePlace['positionStop'] = $ePlace['series']['bedStopCalculated'] !== NULL ?
				($ePlace['series']['bedStopCalculated'] + ($ePlace['series']['season'] - $season) * 100) :
				NULL;

		}

		if($ePlace['positionStop'] === NULL) {

			$ePlace['missing'] = TRUE;

			if($ePlace['positionStart'] !== NULL) {

				$position = $ePlace['positionStart'] + SeriesSetting::MISSING_WEEKS + ($ePlace['series']['season'] - $season) * 100;
				$positionSeason = floor($position / 100);
				$positionWeek = $position - $positionSeason * 100;

				if($positionWeek > 52) {
					$position = $positionSeason + 100 + $positionWeek % 52;
				}

				$ePlace['positionStop'] = $position;

			}

		}

		$ePlace['visible'] = (
			$ePlace['positionStart'] !== NULL and
			$ePlace['positionStop'] !== NULL and
			$ePlace['positionStop'] >= $firstWeekShown and
			$ePlace['positionStart'] < $lastWeekShown
		);

	}

	public function getFilterState(\Collection $cZone): array {

		$beds = [];

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				foreach($ePlot['cBed'] as $eBed) {

					$attributes = \map\BedUi::getTestAttributes($eBed);

					if($attributes !== []) {
						$beds[$eBed['id']] = $attributes;
					}

				}

			}

		}

		return $beds;

	}

	public function getPlacementState(\structure\Structure $eStructure, \Collection $cZone, Series $eSeries, int $season): array {

		[$startTs, $stopTs] = $this->getBounds($eStructure->conf(\map\Configuration::class), $season);

		$target = $eSeries->notEmpty() ? $this->getSeriesBounds($eSeries, $season, $startTs, $stopTs) : NULL;

		$state = [
			'series' => $eSeries->notEmpty() ? $eSeries['id'] : NULL,
			'ghost' => NULL,
			'beds' => [],
		];

		if($target !== NULL) {

			$state['ghost'] = [
				'left' => $target[0],
				'width' => $target[1] - $target[0],
				'color' => $eSeries['cCultivation']->first()['plant']['color'] ?? NULL,
			];

		}

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				foreach($ePlot['cBed'] as $eBed) {

					$pose = \map\BedUi::getBedPoseParts($eBed, $eSeries, $target);
					$height = $this->getTimelineHeight($eStructure, $eBed['cPlace'], $season);

					$state['beds'][$eBed['id']] = [
						'a' => \map\BedUi::getTestAttributes($eBed),
						'p' => $pose,
						'g' => $this->getGhostHeight($eStructure, $eBed, $eSeries, $season, $height),
						'h' => \map\BedUi::isBedHidden($eBed, $eSeries, 'x'),
						'l' => $height,
					];

				}

			}

		}

		return $state;

	}

	protected function getGhostHeight(\structure\Structure $eStructure, \map\Bed $eBed, Series $eSeries, int $season, float $height): ?float {

		if($eSeries->empty()) {
			return NULL;
		}

		foreach($eBed['cPlace'] as $ePlace) {

			if(self::isPlaceOf($ePlace, $eSeries)) {
				return NULL;
			}

		}

		return ($height > 0) ? $height : $this->getTimelineHeight($eStructure, $eBed['cPlace'], $season, lanesMin: 1);

	}

	/**
	 * Où se pose ce qu'on assole sur la frise d'une planche : sans ces bornes, une planche
	 * n'offre pas de bouton Ajouter.
	 */
	public function getPlacedBounds(\structure\Structure $eStructure, Series|Task $ePlaced, int $season): ?array {

		if($ePlaced->empty()) {
			return NULL;
		}

		[$startTs, $stopTs] = $this->getBounds($eStructure->conf(\map\Configuration::class), $season);

		return ($ePlaced instanceof Task) ?
			$this->getTaskBounds($ePlaced, $startTs, $stopTs) :
			$this->getSeriesBounds($ePlaced, $season, $startTs, $stopTs);

	}

	public function getSeriesBounds(Series $eSeries, int $season, int $startTs, int $stopTs, int $firstWeekShown = 0, int $lastWeekShown = 100): ?array {

		if($eSeries->empty()) {
			return NULL;
		}

		$ePlaceTest = new Place([
			'missing' => FALSE,
			'series' => $eSeries,
			'task' => new Task(),
		]);

		$this->positionPlace($ePlaceTest, $season, $firstWeekShown, $lastWeekShown);

		if(
			$ePlaceTest['positionStart'] === NULL or
			$ePlaceTest['positionStop'] === NULL
		) {
			return NULL;
		}

		$week = NULL;
		$minTs = $this->positionToWeek($eSeries, $ePlaceTest['positionStart'], $season, 0, $week);
		$maxTs = $this->positionToWeek($eSeries, $ePlaceTest['positionStop'], $season, 6, $week);

		$from = max(0.0, ($minTs - $startTs) / ($stopTs - $startTs) * 100);
		$to = min(100.0, ($maxTs - $startTs) / ($stopTs - $startTs) * 100);

		if($to <= $from) {
			return NULL;
		}

		return [$from, $to];

	}

	public function getTaskBounds(Task $eTask, int $startTs, int $stopTs): ?array {

		$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'] ?? NULL;

		if($week === NULL) {
			return [0.0, 100.0];
		}

		$from = max(0.0, (strtotime(week_date_starts($week)) - $startTs) / ($stopTs - $startTs) * 100);
		$to = min(100.0, (strtotime(week_date_ends($week)) - $startTs) / ($stopTs - $startTs) * 100);

		if($to <= $from) {
			return [0.0, 100.0];
		}

		return [$from, $to];

	}

	protected function positionToWeek(Series $eSeries, int $position, int $season, int $gap, ?string &$week): int {

		$positionSeason = (int)floor($position / 100);
		$positionWeek = (int)($position - $positionSeason * 100);

		$year = $positionSeason + $season;

		if($eSeries['cycle'] === Series::PERENNIAL) {

			if($positionWeek === 1) {
				return strtotime($year.'-01-01 + '.$gap.' DAYS');
			}

			if($positionWeek >= 52) {
				return strtotime($year.'-12-25 + '.$gap.' DAYS');
			}

		}

		$week = $year.'-W'.sprintf('%02d', $positionWeek);

		return strtotime($week.' + '.$gap.' DAYS');

	}

	protected function getTaskTimeline(\structure\Structure $eStructure, \map\Bed $eBed, int $season, Place $ePlace, string $style, bool $isCurrent = FALSE, bool $isPlaceholder = FALSE): string {

		$eTask = $ePlace['task'];

		if($eTask->empty()) {
			return '';
		}

		$minTs = $this->positionToWeekTask($ePlace['positionStart'], $season, 0);
		$maxTs = $this->positionToWeekTask($ePlace['positionStop'], $season, 6);

		[$startTs, $stopTs] = $this->getBounds($eStructure->conf(\map\Configuration::class), $season);

		if($maxTs <= $startTs or $minTs >= $stopTs) {
			return '';
		}

		$class = $isPlaceholder ?
			'place-grid-series-timeline-placeholder bed-write' :
			'place-grid-series-timeline-task';

		$class .= ($isCurrent and $isPlaceholder === FALSE) ? ' place-current' : '';

		$name = $eTask['action']->notEmpty() ? $eTask['action']['name'] : s("Intervention");

		$style .= $this->getPositionStyle($startTs, $stopTs, $minTs, $maxTs);

		$h = '<div class="place-grid-series-timeline '.$class.'" style="'.$style.'" data-task="'.$eTask['id'].'" data-span="1" data-bed="'.$eBed['id'].'" data-bed-length="'.($eBed['length'] ?? 0).'">';
			$h .= '<span class="place-grid-series-timeline-name">'.encode($name).'</span>';
		$h .= '</div>';

		return $h;

	}

	protected function positionToWeekTask(int $position, int $season, int $gap): int {

		$positionSeason = (int)floor($position / 100);
		$positionWeek = max(1, min(53, (int)($position - $positionSeason * 100)));

		return strtotime(($positionSeason + $season).'-W'.sprintf('%02d', $positionWeek).' + '.$gap.' DAYS');

	}

	protected function getSeriesTimeline(\structure\Structure $eStructure, \map\Bed $eBed, int $season, Place $ePlace, Series $eSeries, \Collection $cCultivation, bool $isPlaceholder, string $style, bool $print, bool $isCurrent = FALSE): string {

		$ePlace->expects(['missing']);

		$cCultivation->expects([
			'harvestWeeks', 'harvestWeeksExpected',
			'cTask'
		]);

		$soilColor = $eStructure->getView('viewSoilColor');

		$minWeek = NULL;
		$maxWeek = NULL;
		$minTs = $this->positionToWeek($eSeries, $ePlace['positionStart'], $season, 0, $minWeek);
		$maxTs = $this->positionToWeek($eSeries, $ePlace['positionStop'], $season, 6, $maxWeek);

		$h = '';

		$isLocked = self::isLocked($eSeries, $season);

		if($isPlaceholder) {
			$class = 'place-grid-series-timeline-placeholder bed-write';
		} else {
			$class = ($season === $eSeries['season']) ? 'place-grid-series-timeline-season' : 'place-grid-series-timeline-not-season';
			$class .= $isLocked ? ' place-locked' : '';
			$class .= $isCurrent ? ' place-current' : '';
		}

		$actions = $isPlaceholder ? [] : self::getSoilActions($eStructure, $eSeries, $cCultivation, $minWeek, $maxWeek);

		[$startTs, $stopTs] = $this->getBounds($eStructure->conf(\map\Configuration::class), $season);

		if($maxTs <= $startTs or $minTs >= $stopTs) {
			return '';
		}

		$dropdown = '';

		if($soilColor !== \structure\Member::WHITE and $isPlaceholder === FALSE) {

			$colors = match($soilColor) {
				\structure\Member::PLANT => $cCultivation->getColumnCollection('plant')->getColumn('color'),
				\structure\Member::FAMILY => array_values(array_unique($cCultivation->makeArray(fn(Cultivation $eCultivation) => self::getFamilyColor($eCultivation))))
			};
			$nColors = count($colors);

			if($nColors === 1) {
				$style .= 'background-color: '.$colors[0].';';
			} else {

				$style .= 'background: linear-gradient(to right';
					foreach($colors as $position => $color) {
						$style .= ', '.$color.' '.(100 * $position / ($nColors - 1)).'%';
					}
				$style .= ');';

			}

		}

		$blockColor = $cCultivation->first()['plant']['color'] ?? NULL;

		if($blockColor) {
			$style .= '--block-color: '.$blockColor.';';
		}

		if($isPlaceholder === FALSE) {

			if($eSeries['season'] === $season) {
				$style .= 'z-index: '.(1 + (int)round(max(0, min(200, (int)($ePlace['positionStart'] ?? 0) + 60)) / 5)).';';
			}

		}

		$tag = 'div';

		$style .= $this->getPositionStyle($startTs, $stopTs, $minTs, $maxTs);

		$h .= '<'.$tag.' class="place-grid-series-timeline '.$class.' '.($ePlace['missing'] ? 'place-grid-series-timeline-alert' : '').' " style="'.$style.'" '.$dropdown.' data-ajax-navigation="notouch" '.($isPlaceholder ? '': 'data-series="'.$eSeries['id'].'" data-span="1" data-bed="'.$eBed['id'].'" data-length="'.($ePlace['length'] ?? '').'" data-bed-length="'.($eBed['length'] ?? 0).'"'.($isLocked ? ' data-locked="1" data-locked-status="'.$eSeries['status'].'"' : '').self::getSearchAttributes($eSeries, $cCultivation)).'>';

			if($isPlaceholder) {

				if($ePlace['missing']) {
					$h .= \Asset::icon('exclamation-circle-fill', ['class' => 'color-white']).' ';
				}

			} else {

				if($soilColor !== \structure\Member::PLANT) {

					$h .= '<div class="place-grid-series-timeline-images">';

						foreach($cCultivation as $eCultivation) {
							$h .= '<div class="place-grid-series-timeline-plant">';
								$h .= \plant\PlantUi::getVignette($eCultivation['plant'], $print ? '1.2rem' : '1.3rem');
							$h .= '</div>';
						}

					$h .= '</div>';

				}

				if($isLocked) {
					$h .= PlaceGardenAiUi::getLockBadge($eSeries, $season);
				}

				$h .= '<div class="place-grid-series-timeline-name">';

					if($ePlace['missing']) {
						$h .= \Asset::icon('exclamation-circle-fill').' ';
					}

					$h .= encode($eSeries['name']);

					if(
						$ePlace->notEmpty() and
						$eSeries['status'] === Series::OPEN
					) {

						if($eSeries['use'] === Series::BED) {
							$h .= PlaceGardenAiUi::getBlockFill($eSeries, $ePlace, $eBed, 1);
						} else {
							$h .= '<span class="place-grid-series-timeline-more">'.s("{area} m²", $ePlace).'</span>';
						}

					}

				$h .= '</div>';

			}

			$h .= self::getSoilActionsBars($actions, $minTs, $maxTs, vertical: FALSE);

		$h .= '</'.$tag.'>';

		return $h;

	}

	protected static function getSoilActions(\structure\Structure $eStructure, Series $eSeries, \Collection $cCultivation, ?string $minWeek, ?string $maxWeek): array {

		if($eStructure->getView('viewSoilTasks') === FALSE) {
			return [];
		}

		$actions = [];

		if($eSeries['cycle'] === Series::ANNUAL) {

			foreach($eSeries['cTaskSoil'] as $eTaskSoil) {

				if($eTaskSoil['minWeek'] === $minWeek) {
					$actions[] = ['action' => $eTaskSoil['action']['id'], 'weekStart' => $eTaskSoil['minWeek'], 'weekStop' => $eTaskSoil['minWeek']];
				}

				if($eTaskSoil['maxWeek'] === $maxWeek) {
					$actions[] = ['action' => $eTaskSoil['action']['id'], 'weekStart' => $eTaskSoil['maxWeek'], 'weekStop' => $eTaskSoil['maxWeek']];
				}

			}

		}

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['startAction'] !== NULL) {

				$actions[] = [
					'action' => match($eCultivation['startAction']) {
						Cultivation::PLANTING => ACTION_PLANTATION,
						Cultivation::SOWING => ACTION_SEMIS_DIRECT,
					},
					'weekStart' => $eCultivation->getStartWeek($eCultivation['season']),
					'weekStop' => $eCultivation->getStartWeek($eCultivation['season']),
				];

			}

			$harvests = array_merge($eCultivation['harvestWeeksExpected'] ?? [], $eCultivation['harvestWeeks'] ?? []);

			if($harvests !== []) {
				$actions[] = ['action' => ACTION_RECOLTE, 'weekStart' => min($harvests), 'weekStop' => max($harvests)];
			}

		}

		return $actions;

	}

	protected static function getSoilActionsBars(array $actions, int $minTs, int $maxTs, bool $vertical): string {

		if($actions === []) {
			return '';
		}

		usort($actions, fn($action1, $action2) => $action1['weekStart'] > $action2['weekStart'] ? -1 : 1);

		$cAction = \work\WorkSetting::$mainActions;

		$h = '';

		foreach($actions as ['action' => $action, 'weekStart' => $startWeek, 'weekStop' => $stopWeek]) {

			$eAction = $cAction[$action] ?? NULL;

			if($eAction === NULL) {
				continue;
			}

			$startActionTs = strtotime(week_date_starts($startWeek).' 00:00:00');
			$stopActionTs = strtotime(week_date_ends($stopWeek).' 23:59:59');

			$from = ($startActionTs - $minTs) / ($maxTs - $minTs) * 100;
			$size = ($stopActionTs - $minTs) / ($maxTs - $minTs) * 100 - $from;

			$from = max(0, min(100, $from));
			$size = max(0, min(100 - $from, $size));

			if($size <= 0) {
				continue;
			}

			$style = $vertical ?
				'top: '.$from.'%; height: '.$size.'%' :
				'left: '.$from.'%; width: '.$size.'%';

			$h .= '<div class="place-grid-series-timeline-week" style="'.$style.'; background-color: '.$eAction['color'].'"></div>';

		}

		return $h;

	}

	public function getBounds(\map\Configuration $eConfiguration, int $season): array {

		// L'axe court jusqu'à la fin du dernier jour, borne exclue : il compte alors
		// autant de jours que la grille des mois, et les deux échelles coïncident.
		$startTs = strtotime($eConfiguration->getCalendarStartDay($season));
		$stopTs = strtotime($eConfiguration->getCalendarStopDay($season).' + 1 DAY');

		return [$startTs, $stopTs];

	}

	public function getWeeksInBounds(\map\Configuration $eConfiguration, int $season): array {

		$startWeek = toWeek($eConfiguration->getCalendarStartDay($season));
		$stopWeek = toWeek($eConfiguration->getCalendarStopDay($season));

		$currentWeek = $startWeek;
		$weeks = [];

		do {
			$weeks[] = $currentWeek;
			$currentWeek = toWeek(strtotime($currentWeek.' + 1 WEEK'));
		} while($currentWeek <= $stopWeek);

		return $weeks;

	}

	/**
	 * La classe de densité d'une graduation, pour le composant `util-graduation`.
	 *
	 * Les multiples de dix, ceux de cinq, et les deux bornes : ce sont les repères qui survivent
	 * quand la place manque. Ici et non dans chaque écran, parce que deux écrans les posent déjà
	 * et qu'un troisième les poserait autrement — c'est le classement qui est commun, le placement
	 * restant à l'écran, les deux axes ne coïncidant pas.
	 */
	public static function getGraduationClass(string $week, string $weekFirst, string $weekLast): string {

		if(week_number($week) % 10 === 0) {
			return ' util-graduation-mark-decade';
		}

		if(week_number($week) % 5 === 0) {
			return ' util-graduation-mark-five';
		}

		if($week === $weekFirst or $week === $weekLast) {
			return ' util-graduation-mark-bound';
		}

		return '';

	}

	// Des déclarations, à poser dans l'attribut style du bloc, et non une règle dans
	// un <style> : chaque bloc en portait un, et une feuille de style coûte à chaque
	// recalcul quel que soit le nombre de règles qu'elle contient. Mesuré le
	// 03/09/2026 sur /ferme/32 : recalcul complet 79 ms avec 384 feuilles sur le plan
	// de culture contre 22 ms sans, 185 ms contre 125 ms sur l'assolement et ses 503.
	public function getPositionStyle($startTs, $stopTs, $minTs, $maxTs, ?string $width = NULL, ?int $fixThreshold = NULL, string $fixDirection = 'left', ?string $shiftLeft = NULL, ?string $shiftRight = NULL, ?string $minWidth = NULL): string {

		$left = ($minTs - $startTs) / ($stopTs - $startTs) * 100;
		$right = ($maxTs - $startTs) / ($stopTs - $startTs) * 100;

		if($fixThreshold !== NULL and $right - $left < $fixThreshold) {

			if($fixDirection === 'left') {
				$left = $right - $fixThreshold;
			} else {
				$right = $left + $fixThreshold;
			}

		}

		if($left < 0 or $right <= 100) {
			return 'left: calc('.$left.'%'.($shiftLeft ? ' + '.$shiftLeft : '').'); width: '.($width ?: ($right - $left).'%').';'.($minWidth ? ' min-width: '.$minWidth.';' : '');
		} else {
			return 'right: calc('.(100 - $right).'%'.($shiftRight ? ' + '.$shiftRight : '').'); width: '.($width ?: ($right - $left).'%').';';
		}

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Series::model()->describer($property, [
		]);

		return $d;

	}

}
?>
