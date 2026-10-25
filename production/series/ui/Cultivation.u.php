<?php
namespace series;

class CultivationUi {

	public function __construct() {

		\Asset::css('series', 'series.css');
		\Asset::js('series', 'series.js');

		\Asset::js('series', 'cultivation.js');
		\Asset::css('series', 'cultivation.css');

		\Asset::js('sequence', 'crop.js');
		\Asset::css('sequence', 'crop.css');

	}

	/*
	 * $filterable : la graduation ouvre le filtre par semaine ou par mois. Réservé aux
	 * écrans qui portent l'omnibox, sinon les cellules prennent l'apparence d'une cible
	 * sans rien déclencher. Deux graduations affichent les semaines, une seule filtre.
	 *
	 * $sparse : l'appelant sait que sa piste est étroite et n'attend pas le seuil de
	 * largeur du composant. La barre ne peut pas le deviner, elle ignore ce qui l'entoure :
	 * mesuré, les mêmes 87 semaines font 12 px sur le plan de culture, qui prend la page
	 * entière, et 7,8 px sur la comparaison d'un groupe, dont le rail des membres mange
	 * 430 px. Un numéro à deux chiffres en demande 11.
	 *
	 * $weekMonths : l'axe d'une grille dont chaque semaine est une colonne de même largeur,
	 * semaine ISO => mois `Y-m`. Chaque mois y prend autant de colonnes que de semaines qui lui
	 * sont rattachées, et non ses jours : au jour près, la graduation se décalerait sur les
	 * colonnes dès que la première semaine commence dans le mois d'avant. L'appelant rattache
	 * la semaine à son mois par `\util\DateLib::convertWeeksToMonths()`, l'axe ne fait que compter.
	 */
	public function getListSeason(\map\Configuration $eConfiguration, int $season, bool $hasWeeks = FALSE, bool $filterable = FALSE, bool $sparse = FALSE, array $weekMonths = []): string {

		if($weekMonths === []) {

			$sizes = $this->getListDays($eConfiguration, $season);

			$weeks = new PlaceUi()->getWeeksInBounds($eConfiguration, $season);
			[$startTs, $stopTs] = new PlaceUi()->getBounds($eConfiguration, $season);

			$left = (strtotime(week_date_starts($weeks[0])) - $startTs) / ($stopTs - $startTs) * 100;
			$width = 7 / array_sum($sizes) * 100;

		} else {

			$sizes = array_count_values($weekMonths);

			$weeks = array_keys($weekMonths);
			$startTs = strtotime(week_date_starts($weeks[0]));
			$stopTs = strtotime(week_date_ends(end($weeks)).' + 1 DAY');

			$left = 0;
			$width = 100 / count($weeks);

		}

		$total = array_sum($sizes);

		$widths = [];
		foreach($sizes as $size) {
			$widths[] = ($size / $total * 100).'%';
		}

		$h = '<div class="series-timeline-season series-season '.($filterable ? 'series-season-filterable' : '').'" style="grid-template-columns: '.implode(' ', $widths).';">';

			$years = array_count_values(array_map(fn($month) => (int)substr($month, 0, 4), array_keys($sizes)));

			foreach($years as $year => $count) {

				if($year === $season) {
					$h .= '<div class="series-season-year series-season-year-current"'.(($count !== 12) ? ' style="grid-column: span '.$count.';"' : '').'>';
				} else {
					$h .= '<div class="series-season-year series-season-year-external" style="grid-column: span '.$count.';">';
				}
					$h .= $year;
				$h .= '</div>';

			}

			// Le nom long quand la place le permet, l'initiale sinon : c'est le CSS qui
			// choisit selon la largeur, le markup porte les deux.
			$names = [s("janv"), s("fév"), s("mars"), s("avr"), s("mai"), s("juin"), s("juil"), s("août"), s("sept"), s("oct"), s("nov"), s("déc")];
			$months = array_map(fn($name) => '<span class="series-season-month-long">'.$name.'</span><span class="series-season-month-short">'.mb_strtoupper(mb_substr($name, 0, 1)).'</span>', $names);

			$position = 0;

			foreach(array_keys($sizes) as $month) {
				$external = ((int)substr($month, 0, 4) !== $season);
				$h .= '<div class="series-season-month series-season-month-'.($position++ % 2).' '.($hasWeeks ? 'series-season-month-with-weeks' : '').($external ? ' series-season-month-external' : '').'"'.($filterable ? ' data-month="'.$month.'"' : '').'>'.$months[(int)substr($month, 5, 2) - 1].'</div>';
			}

			if($hasWeeks) {

				$h .= '<div class="series-season-weeks util-graduation '.($sparse ? 'util-graduation-sparse' : '').'" style="grid-column: span '.count($sizes).'">';

					// Une semaine à cheval sur une borne de la saison ne garde que ses jours dans l'axe, et
					// perd son numéro quand la plupart tombent dehors : entière, sa boîte déborderait de l'axe.
					$marks = [];

					foreach($weeks as $week) {

						$markLeft = max($left, 0);
						$markRight = min($left + $width, 100);

						if($markRight - $markLeft > $width / 2) {
							$marks[$week] = [$markLeft, $markRight - $markLeft];
						}

						$left += $width;

					}

					$weekFirst = array_key_first($marks);
					$weekLast = array_key_last($marks);

					foreach($marks as $week => [$markLeft, $markWidth]) {

						$class = PlaceUi::getGraduationClass($week, $weekFirst, $weekLast);

						$h .= '<div class="series-season-week util-graduation-mark'.$class.'"'.($filterable ? ' data-week="'.$week.'"' : '').' style="left: '.$markLeft.'%; width: '.$markWidth.'%">'.week_number($week).'</div>';

					}

					$h .= $this->getNowGrid($startTs, $stopTs);

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getListGrid(\map\Configuration $eConfiguration, int $season, bool $hasWeeks = FALSE): string {

		$days = array_sum($this->getListDays($eConfiguration, $season));

		$h = '';
		if($hasWeeks) {
			$h .= $this->getWeeklyListGrid($eConfiguration, $season, $days);
		}
		$h .= $this->getMonthlyListGrid($eConfiguration, $season, $days);

		return $h;

	}

	protected function getListDays(\map\Configuration $eConfiguration, int $season): array {

		$days = [];

		if($eConfiguration['calendarMonthStart'] !== NULL) {
			for($month = $eConfiguration['calendarMonthStart']; $month <= 12; $month++) {
				$days[($season - 1).'-'.sprintf('%02d', $month)] = date('t', strtotime(($season - 1).'-'.sprintf('%02d', $month).'-01'));
			}
		}

		for($month = 1; $month <= 12; $month++) {
			$days[$season.'-'.sprintf('%02d', $month)] = date('t', strtotime($season.'-'.sprintf('%02d', $month).'-01'));
		}

		if($eConfiguration['calendarMonthStop'] !== NULL) {
			for($month = 1; $month <= $eConfiguration['calendarMonthStop']; $month++) {
				$days[($season + 1).'-'.sprintf('%02d', $month)] = date('t', strtotime(($season + 1).'-'.sprintf('%02d', $month).'-01'));
			}
		}

		return $days;

	}

	public function getWeeklyListGrid(\map\Configuration $eConfiguration, int $season, int $days): string {

		$weeks = new PlaceUi()->getWeeksInBounds($eConfiguration, $season);
		[$startTs, $stopTs] = new PlaceUi()->getBounds($eConfiguration, $season);
		$startWeekTs = strtotime(week_date_starts($weeks[0]));

		$left = ($startWeekTs - $startTs) / ($stopTs - $startTs) * 100;
		$width = 7 / $days * 100;

		return '<div class="series-grid series-grid-weeks" style="--series-grid-left: '.$left.'; --series-grid-step: '.$width.'; --series-grid-count: '.count($weeks).'"></div>';

	}

	public function getMonthlyListGrid(\map\Configuration $eConfiguration, int $season, int $days): string {

		$h = '<div class="series-grid series-grid-months">';

			$left = 0;

			if($eConfiguration['calendarMonthStart'] !== NULL) {
				for($month = $eConfiguration['calendarMonthStart']; $month <= 12; $month++) {
					$width = date('t', strtotime(($season - 1).'-'.sprintf('%02d', $month).'-01')) / $days * 100;
					$h .= '<div class="series-grid-line" style="left: '.$left.'%; width: '.$width.'%"></div>';
					$left += $width;
				}
			}

			for($month = 1; $month <= 12; $month++) {
				$width = date('t', strtotime($season.'-'.sprintf('%02d', $month).'-01')) / $days * 100;
				$h .= '<div class="series-grid-line" style="left: '.$left.'%; width: '.$width.'%"></div>';
				$left += $width;
			}

			if($eConfiguration['calendarMonthStop'] !== NULL) {
				for($month = 1; $month <= $eConfiguration['calendarMonthStop']; $month++) {
					$width = date('t', strtotime(($season + 1).'-'.sprintf('%02d', $month).'-01')) / $days * 100;
					$h .= '<div class="series-grid-line" style="left: '.$left.'%; width: '.$width.'%"></div>';
					$left += $width;
				}
			}

			[$startTs, $stopTs] = new PlaceUi()->getBounds($eConfiguration, $season);
			$h .= $this->getNowGrid($startTs, $stopTs);

		$h .= '</div>';


		return $h;

	}

	protected function getNowGrid(int $startTs, int $stopTs): string {

		$currentTs = \Clock::timestamp();

		if($currentTs < $stopTs) {

			$left = ($currentTs - $startTs) / ($stopTs - $startTs) * 100;
			return '<div class="series-grid-now" style="right: '.(100 - $left).'%"></div>';

		} else {
			return '';
		}

	}

	public function getPrinting(int $season, \structure\Structure $eStructure, \Collection $ccCultivation) {

		$h = \main\PdfUi::getPage('0.75cm');

		$h .= '<style>';
			$h .= 'html { font-size: 9px !important; }';
		$h .= '</style>';

		$h .= '<table class="series-printing series-item-planning-wrapper">';
			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<td>';
						$h .= '<div class="series-item-header series-item-planning">';
							$h .= '<div class="util-table-header">';
								$h .= s("Série");
							$h .= '</div>';
							$h .= '<div class="util-table-header text-end">';
								$h .= s("Linéaire");
							$h .= '</div>';
							$h .= '<div class="util-table-header text-end">';
								$h .= s("Objectif de récolte<br/>total");
							$h .= '</div>';
							$h .= '<div class="util-table-header series-item-planning-timeline">';
								$h .= $this->getListSeason($eStructure->conf(\map\Configuration::class), $season);
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</td>';
				$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';
				$h .= '<tr>';
					$h .= '<td>';

						$hasTargeted = FALSE;

						$h .= '<div class="series-item-body">';

							$h .= $this->getListGrid($eStructure->conf(\map\Configuration::class), $season);

							foreach($ccCultivation as $cCultivation) {

								$ePlant = $cCultivation->first()['plant'];

								$cultivations = '';

								$totalHarvestExpected = [];
								$totalArea = [
									'length' => 0,
									'lengthTarget' => 0,
									'area' => 0,
									'areaTarget' => 0,
								];

								foreach($cCultivation as $eCultivation) {

									$eSeries = $eCultivation['series'];

									$eCultivation->expects(['cTask']);

									$cultivations .= '<div class="series-item series-item-planning">';

										$cultivations .= '<a href="/serie/'.$eSeries['id'].'" class="series-item-planning-details">';
											$cultivations .= $this->getSeriesForDisplay($eSeries, $eCultivation, tag: 'span').$this->getClosed($eSeries);
											$cultivations .= '<span class="series-item-planning-details-variety">'.new \sequence\SliceUi()->getLine($eCultivation, $eCultivation['cSlice']).'</span>';
										$cultivations .= '</a>';

										[$area, $areaMissing] = $this->getAreaBySeries($eSeries, \structure\Member::LENGTH, $totalArea);

										$cultivations .= '<div class="series-item-planning-summary '.($areaMissing ? 'color-warning' : '').'">';
											$cultivations .= $area;
										$cultivations .= '</div>';

										$cultivations .= '<div class="series-item-planning-summary">';
											$cultivations .= $this->getHarvestBySeries($eCultivation, \structure\Member::TOTAL, $totalHarvestExpected, $hasTargeted);
										$cultivations .= '</div>';

										$cultivations .= '<div class="series-item-planning-timeline">';
											$cultivations .= $this->getTimeline($eStructure, $season, $eSeries, $eCultivation, $eCultivation['cTask']);
										$cultivations .= '</div>';

									$cultivations .= '</div>';

								}

								$h .= '<div class="series-item series-item-planning series-item-planning-'.Series::ANNUAL.' series-item-title">';
									$h .= '<div class="series-item-title-plant">';
										$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
										$h .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
									$h .= '</div>';

									$h .= '<div class="series-item-planning-summary">';
										$h .= $this->getAreaSummary($totalArea);
									$h .= '</div>';

									$h .= '<div class="series-item-planning-summary">';
										foreach($totalHarvestExpected as $unit => $value) {
											$h .= '<div>'.\product\UnitUi::getValue($value, $unit, short: TRUE).'</div>';
										}
									$h .= '</div>';

								$h .= '</div>';
								$h .= $cultivations;

							}

						$h .= '</div>';

					$h .= '</td>';
				$h .= '</tr>';
			$h .= '</tbody>';
		$h .= '</table>';

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		return $h;

	}

	protected function getHarvestBySeries(Cultivation $eCultivation, ?string $viewHarvestExpected, array &$totalHarvestExpected, bool &$hasTargeted): string {

		$h = '';

		switch($viewHarvestExpected) {

			case \structure\Member::TOTAL :

				if($eCultivation['harvestExpected'] !== NULL) {

					$h .= $eCultivation->format('harvestExpected', ['short' => TRUE]);

					$totalHarvestExpected[$eCultivation['mainUnit']] ??= 0;
					$totalHarvestExpected[$eCultivation['mainUnit']] += $eCultivation['harvestExpected'];

				} else if($eCultivation['harvestExpectedTarget'] !== NULL) {

					$h .= '<div class="color-warning">';
						$h .= $eCultivation->format('harvestExpectedTarget', ['short' => TRUE]).'&nbsp;*';
					$h .= '</div>';

					$totalHarvestExpected[$eCultivation['mainUnit']] ??= 0;
					$totalHarvestExpected[$eCultivation['mainUnit']] += $eCultivation['harvestExpectedTarget'];

					$hasTargeted = TRUE;

				}

				break;

			case \structure\Member::WEEKLY :

				if($eCultivation['harvestExpected'] !== NULL) {

					if($eCultivation['harvestWeeksExpected']) {
						$h .= \product\UnitUi::getValue(round($eCultivation['harvestExpected'] / count($eCultivation['harvestWeeksExpected'])), $eCultivation['mainUnit'], TRUE);
					}

				} else if($eCultivation['harvestExpectedTarget'] !== NULL) {

					$h .= '<div class="color-warning">';
						if($eCultivation['harvestWeeksExpected']) {
							$h .= \product\UnitUi::getValue(round($eCultivation['harvestExpectedTarget'] / count($eCultivation['harvestWeeksExpected'])), $eCultivation['mainUnit'], TRUE).'&nbsp;*';
						}
					$h .= '</div>';

					$hasTargeted = TRUE;

				}

				break;

		}

		return $h;

	}

	protected function getHarvestBar(Cultivation $eCultivation, array $bounds): string {

		[$startTs, $stopTs] = $bounds;

		$place = fn(int $ts) => max(0, min(100, ($ts - $startTs) / ($stopTs - $startTs) * 100));

		$block = function(string $class, int $fromTs, int $toTs) use($place) {

			$left = $place($fromTs);
			$right = $place($toTs);

			return ($right > $left) ? '<div class="'.$class.'" style="left: '.round($left, 2).'%; width: '.round($right - $left, 2).'%"></div>' : '';

		};

		$h = '';
		$run = [];

		$flush = function() use(&$run, &$h, $block) {

			if($run !== []) {
				$h .= $block('series-item-harvesting-run', strtotime(week_date_starts($run[0])), strtotime(week_date_starts(end($run))) + 7 * 86400);
				$run = [];
			}

		};

		$weeks = $eCultivation['harvestWeeksExpected'] ?? [];
		sort($weeks);

		foreach($weeks as $week) {

			if($run !== [] and strtotime(week_date_starts($week)) - strtotime(week_date_starts(end($run))) > 7 * 86400) {
				$flush();
			}

			$run[] = $week;

		}

		$flush();

		if(($eCultivation['harvesting']['firstHarvest'] ?? NULL) !== NULL) {
			$h .= $block('series-item-harvesting-done', $eCultivation['harvesting']['firstHarvest'], $eCultivation['harvesting']['lastHarvest'] + 7 * 86400);
		}

		return $h;

	}

	public static function getAreaUnit(Series $eSeries, string $viewArea): string {

		return ($viewArea === \structure\Member::LENGTH and $eSeries['use'] === Series::BED) ? s("mL") : s("m²");

	}

	protected function getAreaBySeries(Series $eSeries, string $viewArea, array &$totalArea): array {

		$area = '';
		$areaMissing = FALSE;

		if($viewArea === \structure\Member::LENGTH and $eSeries['use'] === Series::BED) {

			if($eSeries['length'] !== NULL) {
				if($eSeries['lengthTarget'] !== NULL and $eSeries['lengthTarget'] > $eSeries['length']) {
					$area = s("{value} / {target} mL", ['value' => $eSeries['length'], 'target' => $eSeries['lengthTarget']]);
					$areaMissing = TRUE;
				} else if($eSeries['lengthTarget'] !== NULL and $eSeries['lengthTarget'] < $eSeries['length']) {
					$area = s("{value} / {target} mL", ['value' => $eSeries['length'], 'target' => $eSeries['lengthTarget']]);
				} else {
					$area = s("{value} mL", $eSeries['length']);
				}
			} else if($eSeries['lengthTarget'] !== NULL) {
				$area = s("0 / {value} mL", $eSeries['lengthTarget']);
				$areaMissing = TRUE;
			}

			$totalArea['length'] += ($eSeries['length'] ?? 0);
			$totalArea['lengthTarget'] += ($eSeries['lengthTarget'] ?? $eSeries['length'] ?? 0);

		} else {

			if($eSeries['area'] !== NULL) {
				if($eSeries['areaTarget'] !== NULL and $eSeries['areaTarget'] > $eSeries['area']) {
					$area = s("{value} / {target} m²", ['value' => $eSeries['area'], 'target' => $eSeries['areaTarget']]);
					$areaMissing = TRUE;
				} else if($eSeries['areaTarget'] !== NULL and $eSeries['areaTarget'] < $eSeries['area']) {
					$area = s("{value} / {target} m²", ['value' => $eSeries['area'], 'target' => $eSeries['areaTarget']]);
				} else {
					$area = s("{value} m²", $eSeries['area']);
				}
			} else if($eSeries['areaTarget'] !== NULL) {
				$area = s("0 / {value} m²", $eSeries['areaTarget']);
				$areaMissing = TRUE;
			}

			$totalArea['area'] += ($eSeries['area'] ?? 0);
			$totalArea['areaTarget'] += ($eSeries['areaTarget'] ?? $eSeries['area'] ?? 0);

		}

		return [$area, $areaMissing];

	}

	protected function getAreaSummary(array $totalArea): string {

		$h = '';

		if($totalArea['lengthTarget'] > 0) {

			$h .= '<div>';
				$h .= $totalArea['length'];
				if($totalArea['length'] !== $totalArea['lengthTarget']) {
					$h .= ' / '.$totalArea['lengthTarget'];
				}
				$h .= ' '.s("mL");
			$h .= '</div>';

		}

		if($totalArea['areaTarget'] > 0) {

			$h .= '<div>';
				$h .= $totalArea['area'];
				if($totalArea['area'] !== $totalArea['areaTarget']) {
					$h .= ' / '.$totalArea['areaTarget'];
				}
				$h .= ' '.s("m²");
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Le plan de culture d'une saison.
	 *
	 * ⚠️ **Deux sélections, jamais ensemble, et le lecteur les départage tout seul.** Qui a le
	 * planning sur cette ferme coche pour agir dessus ; qui la lit par un partage de groupe coche
	 * pour emporter chez lui. Un même contrôle ne peut pas vouloir dire deux choses, et aucun
	 * réglage ne tranche à la place du droit : `$hasBatch` d'un côté, `$eStructureCommunityExport`
	 * de l'autre, et le premier gagne.
	 */
	public function displayByArea(int $season, \structure\Structure $eStructure, \Collection $ccCultivation, \work\Action $eActionHarvest = new \work\Action(), \structure\Structure $eStructureCommunityExport = new \structure\Structure()) {


		$viewField = $eStructure->getView('viewPlanningField');
		// Une seule colonne de chiffres, au choix : surface, linéaire, objectif de
		// récolte total ou hebdomadaire. Le diagramme garde le reste de la largeur.
		$viewColumn = $eStructure->getView('viewPlanningArea');
		$viewArea = in_array($viewColumn, [\structure\Member::AREA, \structure\Member::LENGTH]) ? $viewColumn : \structure\Member::AREA;
		$viewHarvestExpected = in_array($viewColumn, [\structure\Member::TOTAL, \structure\Member::WEEKLY]) ? $viewColumn : NULL;

		[$startTs, $stopTs] = new PlaceUi()->getBounds($eStructure->conf(\map\Configuration::class), $season);

		// La grille hebdomadaire s'ancre sur le lundi de la première semaine ISO,
		// pas sur le début de saison : la règle doit partir du même point.
		$weeks = new PlaceUi()->getWeeksInBounds($eStructure->conf(\map\Configuration::class), $season);
		$startWeekTs = strtotime(week_date_starts($weeks[0]));

		// La grille se dessine sur le nombre de jours des mois affichés, qui ne vaut
		// pas toujours (stopTs - startTs) : la règle prend le même diviseur qu'elle,
		// sinon elle tombe entre deux traits en fin de saison.
		$gridDays = array_sum($this->getListDays($eStructure->conf(\map\Configuration::class), $season));

		$hasBatch = $ccCultivation->contains(fn($eCultivation) => $eCultivation->assertWrite(), depth: 2);

		// L'emport n'existe que là où le lot n'est pas : c'est le lecteur qui les exclut.
		$hasExport = ($hasBatch === FALSE and $eStructureCommunityExport->notEmpty());

		// ⚠️ Les **deux** conditions du contrat, et `canExportProduction()` n'en porte qu'une et
		// demie : elle répond vrai dès que le rôle accorde l'emport, y compris sur une ferme dont
		// aucun groupe ne l'a ouvert — le rôle `admin` porte `productionExport`, donc elle est
		// vraie chez soi sans le moindre rattachement. Le groupe rendu par `getExportCommunity()`
		// est celui qui a ouvert la copie **quand il en existe un**, et le premier venu sinon,
		// parce que la phrase de refus a besoin de le nommer. C'est donc ici que l'autorisation se
		// vérifie, et pas à la présence du groupe.
		$canExport = (
			$hasExport and
			$eStructureCommunityExport->grantsCommunityRight('shareProductionExport') and
			$eStructure->canExportProduction()
		);

		$h = '<script type="text/javascript">Series.labels = '.json_encode([
			'week' => s("s"),
			'weeksShort' => s("sem."),
			'withFollowing' => s("avec les suivantes"),
			'alone' => s("seule"),
		]).';</script>';

		$h .= HeaderUi::getSeriesViews($eStructure, $season, \structure\Member::AREA);

		// ⚠️ Une fois par écran et non une fois par ligne : la fermeture appartient à la ferme, pas
		// à chacune de ses cultures, et le même mot répété soixante fois cesse d'être lu.
		if($hasExport and $canExport === FALSE) {
			$h .= '<p class="color-muted font-sm">'.s("Cette ferme n'a pas ouvert la copie de son plan de culture à {value}.", encode($eStructureCommunityExport['name'])).'</p>';
		}

		$h .= '<div id="series-wrapper" class="series-item-wrapper series-item-planning-wrapper  util-overflow-md stick-md" data-batch="#batch-series" data-start="'.$startWeekTs.'" data-duration="'.($stopTs - $startTs).'" data-offset="'.($startWeekTs - $startTs).'" data-days="'.$gridDays.'" onrender="Series.plugRuler(); Series.settlePositions(this); Series.replayPlanFilter()">';

			$h .= '<div class="series-item-header series-item-planning">';

				$h .= '<div class="util-table-header util-checkbox">';
					if($hasBatch or $canExport) {
						$h .= '<label>';
							$h .= '<input type="checkbox" class="series-item-planning-checkbox" oninput="Series.changeAllSelection(this)"/>';
						$h .= '</label>';
					}
				$h .= '</div>';
				$h .= '<div class="util-table-header series-item-planning-title">';

					$h .= self::getFieldSelector($eStructure, $season, \structure\Member::AREA, $viewField);

				$h .= '</div>';

				$h .= '<div class="util-table-header text-end">';

					$label = match($viewColumn) {
						\structure\Member::AREA => s("Surface"),
						\structure\Member::LENGTH => s("Linéaire"),
						\structure\Member::TOTAL => s("Objectif de récolte<br/>total"),
						\structure\Member::WEEKLY => s("Objectif de récolte<br/>hebdo")
					};

					$url = \structure\StructureUi::urlCultivationSeries($eStructure, \structure\Member::AREA, $season).'&area=';

					$h .= '<a class="dropdown-toggle" data-dropdown="bottom-end">'.$label.'</a>';
					$h .= '<div class="dropdown-list bg-secondary">';
						$h .= '<a href="'.$url.\structure\Member::AREA.'" class="dropdown-item">'.s("Afficher la surface").'</a>';
						$h .= '<a href="'.$url.\structure\Member::LENGTH.'" class="dropdown-item">'.s("Afficher en priorité le linéaire").'</a>';
						$h .= '<div class="dropdown-divider"></div>';
						$h .= '<a href="'.$url.\structure\Member::TOTAL.'" class="dropdown-item">'.s("Afficher l'objectif de récolte total").'</a>';
						$h .= '<a href="'.$url.\structure\Member::WEEKLY.'" class="dropdown-item">'.s("Afficher l'objectif de récolte hebdomadaire").'</a>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="util-table-header series-item-planning-timeline">';
					$h .= $this->getListSeason($eStructure->conf(\map\Configuration::class), $season, hasWeeks: TRUE, filterable: TRUE);
				$h .= '</div>';

			$h .= '</div>';

			$hasTargeted = FALSE;

			$h .= '<div class="series-item-body">';

				$h .= $this->getListGrid($eStructure->conf(\map\Configuration::class), $season, hasWeeks: TRUE);

				foreach($ccCultivation as $cCultivation) {

					$ePlant = $cCultivation->first()['plant'];

					$cultivations = '';
					$hasPlantBatch = FALSE;

					$totalHarvestExpected = [];
					$totalArea = [
						'length' => 0,
						'lengthTarget' => 0,
						'area' => 0,
						'areaTarget' => 0,
					];

					foreach($cCultivation as $eCultivation) {

						$eSeries = $eCultivation['series'];

						$eCultivation->expects(['cTask']);

						$cultivations .= '<div class="series-item series-item-planning series-item-planning-'.$eSeries['cycle'].' series-item-status-'.$eSeries['status'].'" id="series-item-'.$eCultivation['id'].'" data-plant="'.$ePlant['id'].'" '.$this->getSearchAttributes($ePlant, $eSeries, $eCultivation).'>';

							$cultivations .= '<label class="util-checkbox batch-checkbox">';

								if($eCultivation->assertWrite()) {

									$batch = [];

									if($eSeries->acceptOpen() === FALSE) {
										$batch[] = 'not-open';
									}

									if($eSeries->acceptClose() === FALSE) {
										$batch[] = 'not-close';
									}

									if($eSeries->acceptDuplicate() === FALSE) {
										$batch[] = 'not-duplicate';
									}

									if($eSeries->acceptSeason() === FALSE) {
										$batch[] = 'not-season';
									}

									// Ce que la ligne accepte : une action qui ne vaut pas pour toute la
									// sélection s'affiche avec son compteur (1 / 2), comme dans les ventes.
									$batch[] = 'accept-update';

									if($eSeries['status'] === Series::OPEN) {
										$batch[] = 'accept-task';
										if($eActionHarvest->notEmpty()) {
											$batch[] = 'accept-harvest';
										}
									}

									if($eSeries->acceptOpen()) {
										$batch[] = 'accept-open';
									}

									if($eSeries->acceptClose()) {
										$batch[] = 'accept-close';
									}

									if($eSeries->acceptDuplicate()) {
										$batch[] = 'accept-duplicate';
									}

									if($eSeries->acceptSeason()) {
										$batch[] = 'accept-season';
									}

									$cultivations .= '<input type="checkbox" class="series-item-planning-checkbox" name="batch[]" value="'.$eSeries['id'].'" oninput="Series.changeSelection()" data-plant="'.$ePlant['id'].'" data-batch="'.implode(' ', $batch).'" data-series="'.$eSeries['id'].'" data-cultivation="'.$eCultivation['id'].'" data-plant-name="'.encode($ePlant['name']).'"/>';

									$hasPlantBatch = TRUE;

								} else if($hasExport) {

									// ⚠️ Désactivée et non absente : une case qui manque ne dit pas
									// qu'on ne peut pas prendre cette ligne, elle laisse croire
									// qu'il n'y a rien à prendre. La raison de la ferme se dit une
									// fois au-dessus du plan ; celle d'une ligne tient dans son
									// survol, faute de menu où l'écrire.
									$exportable = ($canExport and $eSeries['cycle'] !== Series::PERENNIAL);

									// ⚠️ La pérenne est refusée par la copie elle-même, qui l'écarte
									// de son export (`CsvLib::getExportCultivations()`) : une case
									// cochable rendrait une sélection dont cette ligne disparaîtrait
									// sans un mot.
									$title = ($canExport and $eSeries['cycle'] === Series::PERENNIAL)
										? ' title="'.s("Une culture pérenne ne se copie pas d'une ferme à l'autre.").'"'
										: '';

									$cultivations .= '<input type="checkbox" class="series-item-planning-checkbox" name="batch[]" value="'.$eSeries['id'].'" oninput="Series.changeSelection()" data-plant="'.$ePlant['id'].'" data-batch="accept-export" data-series="'.$eSeries['id'].'"'.($exportable ? '' : ' disabled').$title.'/>';

									if($exportable) {
										$hasPlantBatch = TRUE;
									}

								}


							$cultivations .= '</label>';

							$info = $this->getFieldInfo($viewField, $eCultivation, $eSeries);

							$cultivations .= '<div class="series-item-planning-details">';
								$cultivations .= '<a href="/serie/'.$eSeries['id'].'" class="series-item-planning-details-link">';
									$cultivations .= '<span class="series-item-planning-details-title">'.$this->getSeriesForDisplay($eSeries, $eCultivation, tag: 'span').'</span>';
									// Sans seconde ligne, la ligne se resserre sur le seul nom : l'espace
									// insécable ne sert qu'à aligner des sous-lignes entre elles.
									if($viewField !== \structure\Member::SERIES) {
										$cultivations .= '<span class="series-item-planning-details-info">'.($info ?: '&nbsp;').'</span>';
									}
								$cultivations .= '</a>';

								$cultivations .= $this->getClosedCell($eSeries);

							$cultivations .= '</div>';

							[$area, $areaMissing] = $this->getAreaBySeries($eSeries, $viewArea, $totalArea);

							if($viewHarvestExpected) {
								$cultivations .= '<div class="series-item-planning-summary">';
									$cultivations .= $this->getHarvestBySeries($eCultivation, $viewHarvestExpected, $totalHarvestExpected, $hasTargeted);
								$cultivations .= '</div>';
							} else {
								$cultivations .= '<div class="series-item-planning-summary '.($areaMissing ? 'color-warning' : '').'">';
									$cultivations .= $area;
								$cultivations .= '</div>';
							}

							$cultivations .= '<div class="series-item-planning-timeline">';
								$cultivations .= $this->getTimeline($eStructure, $season, $eSeries, $eCultivation, $eCultivation['cTask']);
							$cultivations .= '</div>';

						$cultivations .= '</div>';

					}

					$h .= '<div class="series-item series-item-planning series-item-title" data-plant="'.$ePlant['id'].'">';
						$h .= '<div class="util-checkbox">';
							if($hasPlantBatch) {
								$h .= '<label>';
									$h .= '<input type="checkbox" class="series-item-planning-checkbox" oninput="Series.changePlantSelection(this, '.$ePlant['id'].')"/>';
								$h .= '</label>';
							}
						$h .= '</div>';
						$h .= '<div class="series-item-title-plant">';
							$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
							$h .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
						$h .= '</div>';

					$h .= '<div class="series-item-planning-summary">';
						if($viewHarvestExpected) {
							foreach($totalHarvestExpected as $unit => $value) {
								$h .= '<div>'.\product\UnitUi::getValue($value, $unit, short: TRUE).'</div>';
							}
						} else {
							$h .= $this->getAreaSummary($totalArea);
						}
					$h .= '</div>';

					$h .= '<div class="series-item-planning-timeline"></div>';

					$h .= '</div>';
					$h .= $cultivations;

				}

			$h .= '</div>';

		$h .= '</div>';

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		if($hasBatch) {
			$h .= $this->getBatch($season, $eStructure, $eActionHarvest);
		} else if($canExport) {
			$h .= CopyAiUi::getBatchExport($eStructure, $eStructureCommunityExport, $season);
		}

		return $h;

	}

	// La fiche d'implantation d'un semis ou d'une plantation : ce qui décide de la
	// quantité de graines et de la place prise, réuni sous la pastille.
	public function getImplantation(Cultivation $eCultivation, \work\Action $eAction, string $week): string {

		if(in_array($eAction['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION]) === FALSE) {
			return '';
		}

		$lines = [];

		if($eCultivation['seedling'] !== NULL) {
			$lines[s("Implantation")] = match($eCultivation['seedling']) {
				Cultivation::SOWING => s("Semis direct"),
				Cultivation::YOUNG_PLANT => s("Plant autoproduit"),
				Cultivation::YOUNG_PLANT_BOUGHT => s("Plant acheté")
			};
		}

		if($eCultivation['rows'] !== NULL) {
			$lines[s("Rangs par planche")] = $eCultivation['rows'];
		}

		if($eCultivation['plantSpacing'] !== NULL) {
			$lines[s("Espace sur le rang")] = s("{value} cm", $eCultivation['plantSpacing']);
		}

		if($eCultivation['rowSpacing'] !== NULL) {
			$lines[s("Espace inter-rangs")] = s("{value} cm", $eCultivation['rowSpacing']);
		}

		if($eCultivation['density'] !== NULL) {
			$lines[s("Densité")] = s("{value} / m²", $eCultivation['density']);
		}

		if($eCultivation['seedlingSeeds'] !== NULL and $eCultivation['seedling'] !== Cultivation::YOUNG_PLANT_BOUGHT) {
			$lines[($eCultivation['seedling'] === Cultivation::YOUNG_PLANT) ? s("Graines par plant") : s("Graines par trou")] = $eCultivation['seedlingSeeds'];
		}

		if($lines === []) {
			return '';
		}

		$h = '<div class="dropdown-list bg-secondary">';

			$h .= '<div class="dropdown-title">'.encode($eAction['name']).' <span>'.s("s{value}", week_number($week)).'</span></div>';

			$h .= '<div class="series-item-implantation">';
				foreach($lines as $label => $value) {
					$h .= '<span class="series-item-implantation-label">'.$label.'</span>';
					$h .= '<span class="series-item-implantation-value">'.$value.'</span>';
				}
			$h .= '</div>';

			$h .= '<a href="/series/cultivation:update?id='.$eCultivation['id'].'" class="dropdown-item">'.s("Modifier la culture").'</a>';

		$h .= '</div>';

		return $h;

	}

	public function getBatch(int $season, \structure\Structure $eStructure = new \structure\Structure(), \work\Action $eActionHarvest = new \work\Action()): string {

		// Chaque action porte son test : une sélection mixte l'affiche avec le
		// compte de ce à quoi elle s'appliquera (1 / 2), et ne poste que ceux-là.
		$count = fn(string $test) => ' <span class="batch-item-count badge badge-solid bg-primary" data-batch-test="'.$test.'" data-batch-contains="count" data-batch-only="hide" data-batch-not-contains="hide"></span>';

		$menu = '<a data-ajax="/series/series:doUpdateStatusCollection" post-status="'.Series::OPEN.'" class="batch-open batch-item" data-batch-test="accept-open" data-batch-contains="post" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('unlock');
			$menu .= '<span>'.s("Réouvrir").$count('accept-open').'</span>';
		$menu .= '</a>';

		$menu .= '<a data-ajax="/series/series:closeCollection" data-ajax-method="get" class="batch-close batch-item" data-batch-test="accept-close" data-batch-contains="get" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('lock');
			$menu .= '<span>'.s("Clôturer").$count('accept-close').'</span>';
		$menu .= '</a>';

		$menu .= '<a data-ajax="/series/series:duplicate?origin=plan" data-ajax-method="get" class="batch-duplicate batch-item" data-batch-test="accept-duplicate" data-batch-contains="get" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('copy');
			$menu .= '<span>'.s("Dupliquer").$count('accept-duplicate').'</span>';
		$menu .= '</a>';

		$menu .= '<a data-ajax="/series/series:updateSeasonCollection" data-ajax-method="get" class="batch-season batch-item" data-batch-test="accept-season" data-batch-contains="get" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('calendar');
			$menu .= '<span>'.s("Changer de saison").$count('accept-season').'</span>';
		$menu .= '</a>';

		$menu .= '<a data-ajax="/series/series:numberCollection" data-ajax-method="get" class="batch-number batch-item" data-batch-test="accept-update" data-batch-contains="get" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('list-ol');
			$menu .= '<span>'.s("Numéroter").$count('accept-update').'</span>';
		$menu .= '</a>';

		$danger = '<a data-ajax-submit="/series/series:doDeleteCollection" data-confirm="'.s("Vous vous apprêtez à supprimer définitivement des séries de la saison {value}. Voulez-vous continuer ?", $season).'" class="batch-item batch-item-danger">';
			$danger .= \Asset::icon('trash');
			$danger .= '<span>'.s("Supprimer").'</span>';
		$danger .= '</a>';

		// Une seule culture cochée : un menu compact posé contre sa case, avec ses
		// actions à elle (les liens sont complétés par Series.changeSelection) puis
		// celles de la série.
		$one = '';

		if($eStructure->notEmpty()) {

			$one .= '<a class="batch-one-item batch-culture-update" data-url="/series/cultivation:update?id=">'.\Asset::icon('pencil-fill').'<span>'.s("Modifier").'</span></a>';
			$one .= '<a class="batch-one-item batch-culture-task" data-batch-test="accept-task" data-batch-not-contains="hide" data-url="/series/task:createFromSeries?structure='.$eStructure['id'].'&status='.Task::TODO.'">'.\Asset::icon('calendar-plus-badge').'<span>'.s("Intervention").'</span></a>';

			if($eActionHarvest->notEmpty()) {
				$one .= '<a class="batch-one-item batch-culture-harvest" data-batch-test="accept-harvest" data-batch-not-contains="hide" data-url="/series/task:createFromSeries?structure='.$eStructure['id'].'&doneWeek='.\Clock::week().'&action='.$eActionHarvest['id'].'&status='.Task::DONE.'">'.\Asset::icon('basket3-plus').'<span>'.s("Récolte").'</span></a>';
			}

		}

		$one .= '<a data-ajax-submit="/series/series:doUpdateStatusCollection" post-status="'.Series::OPEN.'" class="batch-open batch-one-item" data-batch-test="accept-open" data-batch-not-contains="hide">'.\Asset::icon('unlock').'<span>'.s("Réouvrir").'</span></a>';
		$one .= '<a data-ajax-submit="/series/series:closeCollection" data-ajax-method="get" class="batch-close batch-one-item" data-batch-test="accept-close" data-batch-not-contains="hide">'.\Asset::icon('lock').'<span>'.s("Clôturer").'</span></a>';
		$one .= '<a data-ajax-submit="/series/series:duplicate?origin=plan" data-ajax-method="get" class="batch-duplicate batch-one-item" data-batch-test="accept-duplicate" data-batch-not-contains="hide">'.\Asset::icon('copy').'<span>'.s("Dupliquer").'</span></a>';
		$one .= '<a data-ajax-submit="/series/series:updateSeasonCollection" data-ajax-method="get" class="batch-season batch-one-item" data-batch-test="accept-season" data-batch-not-contains="hide">'.\Asset::icon('reply-fill', ['class' => 'asset-icon-flip-h']).'<span>'.s("Changer de saison").'</span></a>';
		$one .= '<a data-ajax-submit="/series/series:doDeleteCollection" data-confirm="'.s("Vous vous apprêtez à supprimer définitivement une série de la saison {value}. Voulez-vous continuer ?", $season).'" class="batch-one-item batch-item-danger">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';

		return \util\BatchUi::one('batch-series-one', $one).\util\BatchUi::group('batch-series', $menu, $danger, title: s("Pour les séries sélectionnées"), hide: 'Series.hideSelection()');

	}

	public function displayPlaces(string $use, \Collection $cccPlace, ?int $limit = NULL): string {

		$list = [];
		$names = 0;

		foreach($cccPlace as $ccPlace) {

			foreach($ccPlace as $cPlace) {

				$group = $cPlace->first()->getGroupName();

				$beds = [];

				if($use === Series::BED) {
					$beds = $cPlace
						->find(fn($ePlace) => $ePlace->hasBedName())
						->makeArray(fn($ePlace) => $ePlace['bed']['name']);
				}

				$remaining = ($limit === NULL) ? NULL : $limit - $names;
				$names += max(count($beds), 1);

				if($remaining !== NULL and $remaining <= 0) {
					continue;
				}

				$h = encode($group);

				if($beds) {
					$h .= ' '.\Asset::icon('chevron-right').' ';
					$h .= implode(', ', array_map(fn($bed) => '<span class="series-place-bed">'.encode($bed).'</span>', array_slice($beds, 0, $remaining)));
				}

				$list[] = $h;

			}

		}

		$h = implode('<span>&nbsp;+&nbsp;</span>', $list);

		if($limit !== NULL and $names > $limit) {
			return $h.'&nbsp;…';
		}

		return $h;

	}

	// Le PDF n'est pas une copie de l'écran mais un bon de commande : une ligne par
	// variété, une seule quantité, et la feuille se découpe fournisseur par
	// fournisseur puisque c'est ainsi qu'on commande.
	public function getSeedlingPrinting(array $sections, \Search $search): string {

		if(array_filter($sections) === []) {
			return '<div class="util-empty">'.s("Il n'y a ni semence ni plant à commander pour cette recherche.").'</div>';
		}

		$h = \main\PdfUi::getPage();

		$h .= '<style>';
			$h .= 'html { font-size: 10px !important; }';
		$h .= '</style>';

		$hasTargeted = FALSE;

		$getSeedsWeight = fn(\plant\Variety $eVariety, int $quantity) => \plant\VarietyUi::getSeedsWeight1000($eVariety, $quantity);
		$getPlantsWeight = fn(\plant\Variety $eVariety, int $quantity) => $eVariety['numberPlantKilogram'] === NULL ? NULL : \plant\VarietyUi::getPlantsWeight($eVariety, $quantity);

		$columnsSeeds = ['needed' => s("Nécessaires"), 'stock' => s("En stock"), 'quantity' => s("À acheter")];
		$columnsPlants = ['quantity' => s("Quantité")];

		// Le fournisseur est déjà rappelé sous le titre quand la recherche le fixe : ni
		// l'en-tête de groupe ni les lignes n'ont à le redire.
		$hasSupplier = $search->get('supplier')->empty();

		$h .= $this->getSeedlingOrder(s("Semences à acheter"), $sections['seeds'], TRUE, $hasSupplier, $columnsSeeds, $hasTargeted, $getSeedsWeight);
		$h .= $this->getSeedlingOrder(s("Plants à produire"), $sections['youngPlantsProduced'], FALSE, FALSE, $columnsPlants, $hasTargeted, $getPlantsWeight);
		$h .= $this->getSeedlingOrder(s("Plants à acheter"), $sections['youngPlantsBought'], TRUE, $hasSupplier, $columnsPlants, $hasTargeted, $getPlantsWeight);

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		return $h;

	}

	// Le <thead> est là pour le moteur d'impression, qui le répète en haut de chaque
	// page quand un tableau déborde. Même procédé que getPrinting() pour le plan de
	// culture.
	protected function getSeedlingOrder(string $title, array $groups, bool $bySupplier, bool $withSupplier, array $columns, bool &$hasTargeted, \Closure $getWeight): string {

		if($groups === []) {
			return '';
		}

		$h = '<h3 class="series-order-title">'.$title.'</h3>';

		foreach($groups as ['supplier' => $eSupplier, 'week' => $week, 'lines' => $lines]) {

			// Le nom du groupe tient lieu d'en-tête de colonne : il se répète en haut de
			// page avec le reste du <thead> quand le tableau déborde.
			if($week !== NULL) {
				$label = s("Semaine {week}, {year}", ['week' => week_number($week), 'year' => week_year($week)]);
			} else if($bySupplier and $withSupplier) {
				$label = $eSupplier->notEmpty() ? encode($eSupplier['name']) : s("Fournisseur non renseigné");
			} else {
				$label = s("Variété");
			}

			$h .= '<table class="tr-even series-order-table">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th class="series-order-check"></th>';
						$h .= '<th class="series-order-group">'.$label.'</th>';

						foreach($columns as $column) {
							$h .= '<th class="text-end series-order-quantity">'.$column.'</th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					// L'astérisque des quantités déduites d'un objectif de surface ne se pose que
					// sur ce qu'on commande.
					$columnLast = array_key_last($columns);

					foreach($lines as $line) {

						$eVariety = $line['variety'];

						$h .= '<tr>';

							$h .= '<td class="series-order-check"><span class="series-order-box"></span></td>';

							$h .= '<td>';
								$h .= '<b>'.encode($line['plant']['name']).'</b>';
								$h .= ' '.($eVariety->notEmpty() ? encode($eVariety['name']) : s("variété non renseignée"));

								// Groupé par semaine, l'en-tête ne dit plus chez qui commander.
								if($week !== NULL and $withSupplier and $line['supplier']->notEmpty()) {
									$h .= '<div class="series-order-weight">'.encode($line['supplier']['name']).'</div>';
								}

							$h .= '</td>';

							foreach($columns as $column => $label) {

								$h .= '<td class="text-end series-order-quantity">';

									if($line[$column] === NULL) {
										$h .= '-';
									} else {
										$h .= number_format($line[$column], 0, NULL, ' ');
									}

									if($column === $columnLast and $line['targeted']) {
										$h .= '&nbsp;*';
										$hasTargeted = TRUE;
									}

									// Une quantité nulle n'a pas de masse à donner : le zéro se lit seul.
									if($eVariety->notEmpty() and $line[$column] > 0) {

										$weight = $getWeight($eVariety, $line[$column]);

										if($weight !== NULL) {
											$h .= '<div class="series-order-weight">('.$weight.')</div>';
										}

									}

								$h .= '</td>';

							}

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		}

		return $h;

	}

	public function displayBySeedlingByWeek(\structure\Structure $eStructure, array $items, \Collection $cSupplier, \Search $search): string {

		if($items === []) {
			return self::getSeedlingEmpty($search);
		}

		$classItem = self::getSeedlingClass($search);
		$hasTargeted = FALSE;

		$h = '<div id="series-wrapper" class="series-item-wrapper series-item-seeds-wrapper util-overflow-md stick-md">';

			$h .= $this->getSeedlingHeader($classItem);

			foreach($items as $week => $itemsWeek) {

				$h .= '<div class="util-title series-item-seeds-week">';

					if($week === '') {
						$h .= '<h3>'.s("Semaine non renseignée").'</h3>';
					} else {
						$h .= '<h3>'.s("Semaine {week}, {year}", ['week' => (int)substr($week, 6), 'year' => substr($week, 0, 4)]).'</h3>';
						$h .= '<a href="'.\structure\StructureUi::urlPlanningWeekly($eStructure, $week).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('calendar3').'</a>';
					}

				$h .= '</div>';

				$h .= $this->getSeedlingBody($eStructure, $itemsWeek, $cSupplier, $search, $classItem, $hasTargeted);

			}

		$h .= '</div>';

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		return $h;

	}

	public function displayBySeedling(\structure\Structure $eStructure, array $items, \Collection $cSupplier, \Search $search): string {

		if($items === []) {
			return self::getSeedlingEmpty($search);
		}

		$classItem = self::getSeedlingClass($search);
		$hasTargeted = FALSE;

		$h = '<div id="series-wrapper" class="series-item-wrapper series-item-seeds-wrapper util-overflow-md stick-md">';

			$h .= $this->getSeedlingHeader($classItem);
			$h .= $this->getSeedlingBody($eStructure, $items, $cSupplier, $search, $classItem, $hasTargeted);

		$h .= '</div>';

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		return $h;

	}

	// Un écran vide a deux causes opposées : aucune série n'existe, ou la recherche
	// n'en retient aucune. Dire la première quand c'est la seconde envoie créer des
	// séries qui sont déjà là.
	protected static function getSeedlingEmpty(\Search $search): string {

		$isSearched = (
			$search->get('seedling') !== NULL or
			$search->get('supplier')->notEmpty() or
			$search->get('week') !== NULL
		);

		if($isSearched) {
			return '<div class="util-empty">'.s("Aucune semence ni aucun plant ne correspond à cette recherche.").'</div>';
		}

		return '<div class="util-empty">'.s("Vos achats de semences et plants ne peuvent pas encore s'afficher car vous n'avez pas créé de série pour cette saison.").'</div>';

	}

	protected static function getSeedlingClass(\Search $search): string {
		return 'series-item-seeds '.($search->get('seedling') ? 'series-item-seeds-'.$search->get('seedling') : 'series-item-seeds-all');
	}

	protected function getSeedlingHeader(string $classItem): string {

		$h = '<div class="series-item-header '.$classItem.'">';

			$h .= '<div class="util-table-header">';
				$h .= s("Variété");
			$h .= '</div>';
			$h .= '<div class="util-table-header text-end series-item-sowing">';
				$h .= s("Semences<br/> nécessaires");
			$h .= '</div>';
			$h .= '<div class="util-table-header text-end series-item-stock">';
				$h .= s("Semences<br/> en stock");
			$h .= '</div>';
			$h .= '<div class="util-table-header text-end series-item-buy">';
				$h .= s("Semences<br/> à acheter");
			$h .= '</div>';
			$h .= '<div class="util-table-header text-end series-item-young-plant">';
				$h .= s("Plants<br/> à produire");
			$h .= '</div>';
			$h .= '<div class="util-table-header text-end series-item-young-plant-bought">';
				$h .= s("Plants<br/> à acheter");
			$h .= '</div>';
			$h .= '<div class="util-table-header series-item-seeds-series">';
				$h .= s("Séries");
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getSeedlingBody(\structure\Structure $eStructure, array $items, \Collection $cSupplier, \Search $search, string $classItem, bool &$hasTargeted): string {

		$eSupplier = $search->get('supplier');

		$h = '';

			$h .= '<div class="series-item-body">';

				foreach($items as ['plant' => $ePlant, 'seeds' => $seeds]) {

					$h .= '<div class="series-item series-item-seeds series-item-title"  data-ref="plant-'.$ePlant['id'].'">';
						$h .= '<div class="series-item-title-plant">';
							$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
							$h .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
							if($ePlant['seedsSafetyMargin'] !== NULL and $ePlant['plantsSafetyMargin'] !== NULL) {
								$h .= '<a href="/plant/plant:update?id='.$ePlant['id'].'" class="series-item-title-plant-safety">'.\Asset::icon('arrow-right').' '.s("marge de sécurité de {seedsSafetyMargin} % sur semis directs et de {plantsSafetyMargin} % sur plants autoproduits", $ePlant).'</a>';
							} else if($ePlant['seedsSafetyMargin'] !== NULL) {
								$h .= '<a href="/plant/plant:update?id='.$ePlant['id'].'" class="series-item-title-plant-safety">'.\Asset::icon('arrow-right').' '.s("marge de sécurité de {seedsSafetyMargin} % sur semis directs", $ePlant).'</a>';
							} else if($ePlant['plantsSafetyMargin'] !== NULL) {
								$h .= '<a href="/plant/plant:update?id='.$ePlant['id'].'" class="series-item-title-plant-safety">'.\Asset::icon('arrow-right').' '.s("marge de sécurité de {plantsSafetyMargin} % sur plants autoproduits", $ePlant).'</a>';
							}
						$h .= '</div>';
					$h .= '</div>';

					foreach($seeds as $seedsVariety) {

						$eVariety = $seedsVariety['variety'];

						$rows = count($seedsVariety['cultivations']);

						// Le stock est une colonne de la variété : le référentiel partagé, que la
						// structure ne possède pas, n'en porte donc jamais. La question se pose
						// avant la ligne suivante, qui substitue la structure connectée.
						$isOwnVariety = ($eVariety->notEmpty() and $eVariety['structure']->notEmpty());

						if($eVariety->notEmpty()) {

							$eVariety['structure'] = $eStructure;

							$isSeedSupplier = ($seedsVariety['seeds'] > 0 and $eSupplier->notEmpty() and $eVariety['supplierSeed']->notEmpty() and $eSupplier['id'] === $eVariety['supplierSeed']['id']);
							$isPlantSupplier = ($seedsVariety['youngPlantsBought'] > 0 and $eSupplier->notEmpty() and $eVariety['supplierPlant']->notEmpty() and $eSupplier['id'] === $eVariety['supplierPlant']['id']);

						} else {
							$isSeedSupplier = FALSE;
							$isPlantSupplier = FALSE;
						}

						$h .= '<div class="series-item '.$classItem.'">';

							$h .= '<div style="grid-row: span '.$rows.'">';
								if($eVariety->notEmpty()) {
									$h .= '<a href="/plant/variety?id='.$eStructure['id'].'&plant='.$ePlant['id'].'">'.encode($eVariety['name']).'</a>';
								} else {
									$h .= '<i>'.s("Non renseignée").'</i>';
								}
							$h .= '</div>';
							$stock = $eVariety->empty() ? NULL : $eVariety['stock'];
							$toBuy = max($seedsVariety['seeds'] - (int)$stock, 0);

							$h .= '<div style="grid-row: span '.$rows.'" class="series-item-seeds-value series-item-sowing color-muted">';
								$h .= ($seedsVariety['seeds'] === 0) ? '-' : number_format($seedsVariety['seeds'], 0, NULL, ' ');
							$h .= '</div>';
							$h .= '<div style="grid-row: span '.$rows.'" class="series-item-seeds-value series-item-stock">';
								if($stock !== NULL) {

									$h .= $eVariety->quick('stock', number_format($stock, 0, NULL, ' '));

									if($eVariety['weightSeed1000'] !== NULL) {
										$h .= '<div class="series-item-seeds-weight">('.\plant\VarietyUi::getSeedsWeight1000($eVariety, $stock).')</div>';
									}

								} else if($isOwnVariety and $seedsVariety['seeds'] > 0 and $eVariety->assertUpdate()) {
									// Sans droit d'écriture, le « + » serait un bouton mort.
									$h .= $eVariety->quick('stock', '<span class="btn btn-dashed btn-xs">'.\Asset::icon('plus-lg').'</span>');
								} else {
									$h .= '-';
								}
							$h .= '</div>';
							$h .= '<div style="grid-row: span '.$rows.'; '.($isSeedSupplier ? 'font-weight: bold;' : '').'" class="series-item-seeds-value series-item-buy '.($seedsVariety['error'] ? 'color-danger' : ($seedsVariety['targeted'] ? 'color-warning' : '')).'">';
								if($seedsVariety['seeds'] === 0) {
									$h .= '-';
								} else {

									$number = number_format($toBuy, 0, NULL, ' ');
									if($eVariety->notEmpty() and $cSupplier->notEmpty()) {
										$h .= $eVariety->quick('supplierSeed', '<span title="'.s("Modifier le fournisseur de semences").'">'.$number.'</span>');
									} else {
										$h .= $number;
									}
									if($seedsVariety['targeted']) {
										$h .= '&nbsp;*';
									}
									if($toBuy > 0 and $eVariety->notEmpty() and $eVariety['weightSeed1000'] !== NULL) {
										$h .= '<div class="series-item-seeds-weight">('.\plant\VarietyUi::getSeedsWeight1000($eVariety, $toBuy).')</div>';
									}
									if($eVariety->notEmpty() and $eVariety['supplierSeed']->notEmpty()) {
										$h .= '<div class="series-item-seeds-supplier">'.encode($eVariety['supplierSeed']['name']).'</div>';
									}

								}
							$h .= '</div>';
							$h .= '<div style="grid-row: span '.$rows.'" class="series-item-seeds-value series-item-young-plant '.($seedsVariety['error'] ? 'color-danger' : ($seedsVariety['targeted'] ? 'color-warning' : '')).'">';
								if($seedsVariety['youngPlantsProduced'] === 0) {
									$h .= '-';
								} else {
									$h .= number_format($seedsVariety['youngPlantsProduced'], 0, NULL, ' ');
									if($seedsVariety['targeted']) {
										$h .= '&nbsp;*';
									}
									if($eVariety->notEmpty() and $eVariety['numberPlantKilogram'] !== NULL) {
										$h .= '<div class="series-item-seeds-weight">('.\plant\VarietyUi::getPlantsWeight($eVariety, $seedsVariety['youngPlantsProduced']).')</div>';
									}
								}
							$h .= '</div>';
							$h .= '<div style="grid-row: span '.$rows.'; '.($isPlantSupplier ? 'font-weight: bold;' : '').'" class="series-item-seeds-value series-item-young-plant-bought '.($seedsVariety['error'] ? 'color-danger' : ($seedsVariety['targeted'] ? 'color-warning' : '')).'">';
								if($seedsVariety['youngPlantsBought'] === 0) {
									$h .= '-';
								} else {
									$number = number_format($seedsVariety['youngPlantsBought'], 0, NULL, ' ');
									if($eVariety->notEmpty() and $cSupplier->notEmpty()) {
										$h .= $eVariety->quick('supplierPlant', '<span title="'.s("Modifier le fournisseur de plants").'">'.$number.'</span>');
									} else {
										$h .= $number;
									}
									if($seedsVariety['targeted']) {
										$h .= '&nbsp;*';
									}
									if($eVariety->notEmpty() and $eVariety['numberPlantKilogram'] !== NULL) {
										$h .= '<div class="series-item-seeds-weight">('.\plant\VarietyUi::getPlantsWeight($eVariety, $seedsVariety['youngPlantsBought']).')</div>';
									}
									if($eVariety->notEmpty() and $eVariety['supplierPlant']->notEmpty()) {
										$h .= '<div class="series-item-seeds-supplier">'.encode($eVariety['supplierPlant']['name']).'</div>';
									}
								}
							$h .= '</div>';

							foreach($seedsVariety['cultivations'] as $cultivation) {

								$eSeries = $cultivation['series'];
								$eCultivation = $cultivation['cultivation'];

								$h .= '<div class="series-item-seeds-series">';

									$h .= SeriesUi::link($eSeries);
									$h .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);

									if($cultivation['error'] !== NULL) {

										$label = match($cultivation['error']) {
											'seedling' => s("Implantation à définir"),
											'area' => s("Surface à définir"),
											'length' => s("Longueur de planche à définir"),
											'density' => s("Densité à définir"),
										};

										$h .= '<div class="series-item-seeds-alert color-danger">';
											$h .= \Asset::icon('exclamation-circle').' '.$label;
										$h .= '</div>';

									} else if($cultivation['targeted']) {

										$h .= '<div class="series-item-seeds-alert color-warning">';
											$h .= \Asset::icon('exclamation-circle').' '.s("Assolement à définir *");
										$h .= '</div>';

									}

								$h  .= '</div>';

							}

						$h .= '</div>';

					}
					
					if($seedsVariety['targeted']) {
						$hasTargeted = TRUE;
					}

				}

			$h .= '</div>';

		return $h;

	}


	public function getWarningTargeted(): string {

		$h = '<p class="util-warning">';
			$h .= s("* Valeur basée sur l'objectif de surface car l'assolement n'a pas encore été défini.");
		$h .= '</p>';

		return $h;

	}

	// Le choix « variétés / assolement / série seule » vaut pour les trois vues du
	// plan : il est stocké sur le membre, chaque vue le lit et le propose.
	protected static function getFieldSelector(\structure\Structure $eStructure, ?int $season, string $view, string $viewField): string {

		$label = match($viewField) {
			\structure\Member::VARIETY => s("Série et variétés"),
			\structure\Member::SOIL => s("Série et assolement"),
			\structure\Member::SERIES => s("Série")
		};

		$url = \structure\StructureUi::urlCultivationSeries($eStructure, $view, $season);

		$h = '<a class="dropdown-toggle series-item-planning-field" data-dropdown="bottom-start">'.$label.'</a>';
		$h .= '<div class="dropdown-list bg-secondary">';
			$h .= '<a href="'.$url.'&field='.\structure\Member::VARIETY.'" class="dropdown-item">'.s("Voir les variétés").'</a>';
			$h .= '<a href="'.$url.'&field='.\structure\Member::SOIL.'" class="dropdown-item">'.s("Voir l'assolement").'</a>';
			$h .= '<a href="'.$url.'&field='.\structure\Member::SERIES.'" class="dropdown-item">'.s("Juste les séries").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getFieldInfo(string $viewField, Cultivation $eCultivation, Series $eSeries): string {

		return match($viewField) {
			\structure\Member::VARIETY => new \sequence\SliceUi()->getLine($eCultivation, $eCultivation['cSlice']),
			\structure\Member::SOIL => $this->displayPlaces($eSeries['use'], $eSeries['cccPlace']),
			\structure\Member::SERIES => ''
		};

	}

	public function displayByWorkingTime(\structure\Structure $eStructure, \Collection $ccCultivation): string {

		$season = $ccCultivation->empty() ? NULL : $ccCultivation->first()->first()['season'];
		$viewField = $eStructure->getView('viewPlanningField');

		$h = ($season === NULL) ? '' : HeaderUi::getSeriesViews($eStructure, $season, \structure\Member::WORKING_TIME);

		$h .= '<div id="series-wrapper" class="series-item-wrapper series-item-working-time-wrapper util-overflow-md stick-md" onrender="Series.replayPlanFilter()">';

			$h .= '<div class="series-item-header series-item-working-time">';

				$h .= '<div class="util-table-header">';
					$h .= self::getFieldSelector($eStructure, $season, \structure\Member::WORKING_TIME, $viewField);
				$h .= '</div>';
				$h .= '<div class="util-table-header text-end">';
					$h .= s("Surface");
				$h .= '</div>';
				$h .= '<div class="util-table-header series-item-working-time-harvested text-end">';
					$h .= s("Récolté");
				$h .= '</div>';
				$h .= '<div class="util-table-header text-center">';
					$h .= s("Travaillé");
				$h .= '</div>';
				$h .= '<div class="util-table-header series-item-working-time-tasks">';
					$h .= s("Tâches chronophages, dans l'ordre du calendrier");
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="series-item-body">';

				foreach($ccCultivation as $cCultivation) {

					$ePlant = $cCultivation->first()['plant'];

					$cultivations = '';

					foreach($cCultivation as $eCultivation) {

						$cultivations .= '<div class="series-item series-item-working-time series-item-status-'.$eCultivation['series']['status'].'" id="series-item-'.$eCultivation['id'].'" data-plant="'.$ePlant['id'].'" '.$this->getSearchAttributes($ePlant, $eCultivation['series'], $eCultivation).'>';
							$cultivations .= '<div class="series-item-planning-details">';
								$cultivations .= '<span class="series-item-planning-details-title">'.$this->getSeriesForDisplay($eCultivation['series'], $eCultivation).$this->getClosed($eCultivation['series']).'</span>';
								$info = $this->getFieldInfo($viewField, $eCultivation, $eCultivation['series']);
								if($info !== '') {
									$cultivations .= '<span class="series-item-planning-details-info">'.$info.'</span>';
								}
							$cultivations .= '</div>';
							$cultivations .= '<div class="text-end">';
								if($eCultivation['area'] !== NULL) {
									$cultivations .= s("{value} m²", $eCultivation['area']);
								} else {
									$cultivations .= '-';
								}
							$cultivations .= '</div>';
							$cultivations .= '<div class="series-item-working-time-harvested text-end">';
								if($eCultivation['harvestedByUnit'] !== NULL) {
									$cultivations .= $this->getHarvestedByUnits($eCultivation);
								} else {
									$cultivations .= '-';
								}
							$cultivations .= '</div>';
							$cultivations .= '<div class="text-center">';
								$cultivations .= '<b>'.TaskUi::convertTime($eCultivation['totalTimeSoil'] + $eCultivation['totalTimePlant']).'</b>';
							$cultivations .= '</div>';

							$cTask = $eCultivation['cTask']->sort(fn($a, $b) => ($a['firstWeek'] ?? '9999') <=> ($b['firstWeek'] ?? '9999'), natural: TRUE);

							$cultivations .= '<div class="series-item-working-time-tasks">';

								foreach($cTask as $eTask) {

									if($eTask['totalTime'] > 0) {
										$cultivations .= new SeriesUi()->getWorkingTimeBox($eCultivation['series'], $eCultivation, $eTask['action'], $eTask['totalTime'], $eCultivation['cTaskHarvested']);
									}

								}

							$cultivations .= '</div>';

						$cultivations .= '</div>';

					}

					$hPlant = '<div class="series-item series-item-working-time series-item-title" id="series-item-'.$eCultivation['id'].'" data-ref="plant-'.$ePlant['id'].'" data-plant="'.$ePlant['id'].'">';
						$hPlant .= '<div class="series-item-title-plant">';
							$hPlant .= \plant\PlantUi::getVignette($ePlant, '2rem');
							$hPlant .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
						$hPlant .= '</div>';
					$hPlant .= '</div>';

					$h .= $hPlant;
					$h .= $cultivations;

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function displayByHarvesting(\Collection $ccCultivation): string {

		$bounds = NULL;
		$eConfiguration = NULL;
		$season = NULL;

		if($ccCultivation->notEmpty()) {
			$eCultivationFirst = $ccCultivation->first()->first();
			$eConfiguration = $eCultivationFirst['structure']->conf(\map\Configuration::class);
			$season = $eCultivationFirst['season'];
			$bounds = new PlaceUi()->getBounds($eConfiguration, $season);
		}

		$eStructure = ($eConfiguration === NULL) ? new \structure\Structure() : $eConfiguration['structure'];
		$viewField = $eStructure->empty() ? \structure\Member::VARIETY : $eStructure->getView('viewPlanningField');

		$h = ($eConfiguration === NULL) ? '' : HeaderUi::getSeriesViews($eStructure, $season, \structure\Member::HARVESTING);

		$h .= '<div id="series-wrapper" class="series-item-wrapper series-item-harvesting-wrapper util-overflow-md stick-md" onrender="Series.replayPlanFilter()">';

			$h .= '<div class="series-item-header series-item-harvesting">';

				$h .= '<div style="grid-row: span 2" class="util-table-header">';
					if($eStructure->notEmpty()) {
						$h .= self::getFieldSelector($eStructure, $season, \structure\Member::HARVESTING, $viewField);
					}
				$h .= '</div>';
				$h .= '<div style="grid-row: span 2" class="util-table-header text-end">';
					$h .= s("Surface");
				$h .= '</div>';
				$h .= '<div style="grid-column: span 2" class="util-table-header series-item-header-highlight text-center">';
					$h .= s("Récolte");
				$h .= '</div>';
				$h .= '<div style="grid-column: span 2" class="util-table-header series-item-header-highlight text-center">';
					$h .= s("Rendement par m²");
				$h .= '</div>';
				$h .= '<div style="grid-row: span 2" class="util-table-header series-item-harvesting-weeks">';
					if($eConfiguration !== NULL) {
						$h .= $this->getListSeason($eConfiguration, $season);
					}
				$h .= '</div>';

				$h .= '<div class="util-table-header text-end">';
				$h .= s("Obtenue");
				$h .= '</div>';

				$h .= '<div class="util-table-header text-end">';
				$h .= s("Objectif");
				$h .= '</div>';

				$h .= '<div class="util-table-header text-end">';
					$h .= s("Obtenu");
				$h .= '</div>';

				$h .= '<div class="util-table-header text-end">';
					$h .= s("Objectif");
				$h .= '</div>';

			$h .= '</div>';

			$hasTargeted = FALSE;

			$h .= '<div class="series-item-body">';

				if($eConfiguration !== NULL) {
					$h .= $this->getListGrid($eConfiguration, $season, hasWeeks: TRUE);
				}

				foreach($ccCultivation as $cCultivation) {

					$ePlant = $cCultivation->first()['plant'];
					$cCultivationTotal = new \Collection();

					$cultivations = '';

					$totalHarvest = [];
					$totalHarvestExpected = [];

					foreach($cCultivation as $eCultivation) {

						$unit = $eCultivation['mainUnit'];

						if($cCultivationTotal->offsetExists($unit) === FALSE) {

							$cCultivationTotal[$unit] = new Cultivation([
								'area' => NULL,
								'harvested' => NULL,
								'harvestExpected' => NULL,
								'mainUnit' => $unit
							]);

						}

						if($eCultivation['area'] !== NULL) {
							$cCultivationTotal[$unit]['area'] += $eCultivation['area'];
						}

						if($eCultivation['harvested'] !== NULL) {
							$cCultivationTotal[$unit]['harvested'] += $eCultivation['harvested'];
						}

						if($eCultivation['harvestExpected'] !== NULL) {
							$cCultivationTotal[$unit]['harvestExpected'] += $eCultivation['harvestExpected'];
						}

						if($eCultivation['harvestedByUnit'] === NULL) {
							$eCultivation['harvestedByUnit'][$eCultivation['mainUnit']] = NULL;
						}

						$legend = '';

						if($eCultivation['harvesting']['firstHarvest'] !== NULL) {
							$legend = self::getHarvestInterval($eCultivation['harvesting']['firstHarvest'], $eCultivation['harvesting']['lastHarvest']);
							if($eCultivation['harvesting']['firstHarvest'] !== $eCultivation['harvesting']['lastHarvest']) {
								$legend .= ' — '.p("{value} semaine", "{value} semaines", $eCultivation['harvesting']['nHarvest']);
							}
						}

						$position = 0;

						foreach($eCultivation['harvestedByUnit'] as $unit => $harvested) {

							$position++;

							$cultivations .= '<div class="series-item series-item-harvesting series-item-status-'.$eCultivation['series']['status'].' '.($position > 1 ? 'series-item-group' : '').'" id="series-item-'.$eCultivation['id'].'" data-plant="'.$ePlant['id'].'" '.$this->getSearchAttributes($ePlant, $eCultivation['series'], $eCultivation).'>';
								$cultivations .= '<div class="series-item-planning-details">';

									if($position === 1) {

										$cultivations .= '<span class="series-item-planning-details-title">'.$this->getSeriesForDisplay($eCultivation['series'], $eCultivation).$this->getClosed($eCultivation['series']).'</span>';

										$info = $this->getFieldInfo($viewField, $eCultivation, $eCultivation['series']);

										if($info !== '') {
											$cultivations .= '<span class="series-item-planning-details-info">'.$info.'</span>';
										}

									}

								$cultivations .= '</div>';
								$cultivations .= '<div class="text-end">';

									if($position === 1) {
										if($eCultivation['area'] !== NULL) {
											$cultivations .= s("{value} m²", $eCultivation['area']);
										} else {
											$cultivations .= '-';
										}
									}

								$cultivations .= '</div>';
								$cultivations .= '<div class="series-item-harvesting-yield text-end">';

									if($harvested !== NULL) {

										$cultivations .= '<a href="/series/cultivation:harvest?id='.$eCultivation['id'].'" style="color: inherit">'.\product\UnitUi::getValue($harvested, $unit, TRUE).'</a>';

										$totalHarvest[$unit] ??= 0;
										$totalHarvest[$unit] += $harvested;

									} else {
										$cultivations .= '-';
									}
								$cultivations .= '</div>';
								$cultivations .= '<div class="series-item-harvesting-yield text-end">';
									if($eCultivation['mainUnit'] === $unit) {

										if($eCultivation['harvestExpected'] !== NULL) {

											$cultivations .= $eCultivation->format('harvestExpected', ['short' => TRUE]);

											$totalHarvestExpected[$eCultivation['mainUnit']] ??= 0;
											$totalHarvestExpected[$eCultivation['mainUnit']] += $eCultivation['harvestExpected'];

										} else if($eCultivation['harvestExpectedTarget'] !== NULL) {

											$cultivations .= '<span class="color-warning">'.$eCultivation->format('harvestExpectedTarget', ['short' => TRUE]).' *</span>';
											$hasTargeted = TRUE;

											$totalHarvestExpected[$eCultivation['mainUnit']] ??= 0;
											$totalHarvestExpected[$eCultivation['mainUnit']] += $eCultivation['harvestExpectedTarget'];

										} else {
											$cultivations .= '-';
										}

									} else {
										$cultivations .= '-';
									}
								$cultivations .= '</div>';
								$cultivations .= '<div class="series-item-harvesting-yield text-end">';

									if($eCultivation['yieldByUnit'] !== NULL and $eCultivation['mainUnit'] === $unit and $eCultivation['series']['area'] > 0 and $eCultivation['yieldByUnit'][$eCultivation['mainUnit']] !== NULL) {

										$yield = round($eCultivation['yieldByUnit'][$eCultivation['mainUnit']] / $eCultivation['series']['area'], 1);
										$cultivations .= '<b>'.\product\UnitUi::getValue($yield, $unit, TRUE).'</b>';

										if($eCultivation['mainUnit'] === $unit and $eCultivation['harvestExpected'] > 0) {

											$value = round(($eCultivation['yieldByUnit'][$eCultivation['mainUnit']] / $eCultivation['harvestExpected'] - 1) * 100);

											$cultivations .= '<div class="series-item-harvesting-evolution">';
											if($value < 0) {
												$cultivations .= '<span class="color-danger">'.$value.' %</span>';
											} else if($value > 0) {
												$cultivations .= '<span class="color-success">+'.$value.' %</span>';
											} else {
												$cultivations .= '=';
											}
											$cultivations .= '</div>';

										}

									} else {
										$cultivations .= '-';
									}

								$cultivations .= '</div>';
								$cultivations .= '<div class="series-item-harvesting-yield-expected text-end">';
									if($eCultivation['mainUnit'] === $unit and $eCultivation['yieldExpected'] !== NULL) {
										$cultivations .= '<span class="color-muted">'.$eCultivation->format('yieldExpected', ['short' => TRUE]).'</span>';
									} else {
										$cultivations .= '-';
									}
								$cultivations .= '</div>';

								$cultivations .= '<div class="series-item-harvesting-weeks"'.($legend === '' ? '' : ' title="'.encode($legend).'"').'>';

									if($position === 1 and $bounds !== NULL) {
										$cultivations .= $this->getHarvestBar($eCultivation, $bounds);
									}

								$cultivations .= '</div>';

							$cultivations .= '</div>';
						}

					}

					$cCultivationTotal->ksort();

					$hPlant = '<div class="series-item-harvesting series-item series-item-title" id="series-item-'.$eCultivation['id'].'" data-ref="plant-'.$ePlant['id'].'" data-plant="'.$ePlant['id'].'">';
						$hPlant .= '<div class="series-item-title-plant" style="grid-column: span 2">';
							$hPlant .= \plant\PlantUi::getVignette($ePlant, '2rem');
							$hPlant .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
						$hPlant .= '</div>';
						if($cCultivation->count() > 1) {
							$hPlant .= '<div class="series-item-harvesting-yield text-end">';
								foreach($totalHarvest as $unit => $value) {
									$hPlant .= '<div>'.\product\UnitUi::getValue($value, $unit, TRUE).'</div>';
								}
							$hPlant .= '</div>';
							$hPlant .= '<div class="series-item-harvesting-yield text-end">';
								foreach($totalHarvestExpected as $unit => $value) {
									$hPlant .= '<div>'.\product\UnitUi::getValue($value, $unit, TRUE).'</div>';
								}
							$hPlant .= '</div>';
						}
					$hPlant .= '</div>';

					$h .= $hPlant;
					$h .= $cultivations;

				}

			$h .= '</div>';

		$h .= '</div>';

		if($hasTargeted) {
			$h .= $this->getWarningTargeted();
		}

		return $h;

	}

	public static function getHarvestInterval(string $first, string $last): string {

		if($first !== $last) {
			return s("Semaines {from} à {to}", ['from' => date('W', $first), 'to' => date('W', $last)]).', '.date('Y', $last);
		} else {
			return s("Semaine {value}", date('W', $first)).' '.date('Y', $first);
		}

	}

	// Ce sur quoi le filtre de la page cherche, groupe par groupe : le déroulant
	// propose les espèces, les variétés et le matériel séparément, la recherche
	// elle-même les concatène.
	protected function getSearchAttributes(\plant\Plant $ePlant, Series $eSeries, Cultivation $eCultivation): string {

		$varieties = [];

		foreach(($eCultivation['cSlice'] ?? []) as $eSlice) {
			$varieties[] = $eSlice['variety']['name'];
		}

		$groups = [
			'plant' => [$ePlant['name']],
			'series' => [$eSeries['name']],
			'variety' => $varieties,
			'tool' => ($eCultivation['toolNames'] ?? []),
			'idle' => $eCultivation['idle'] ? [s("Sans intervention à venir")] : []
		];

		$attributes = [];

		foreach($groups as $group => $values) {
			$attributes[] = 'data-search-'.$group.'="'.encode(implode('|', $values)).'"';
		}

		return implode(' ', $attributes);

	}

	protected function getClosed(Series $eSeries): string {

		if($eSeries->isClosed() === FALSE) {
			return '';
		}

		return \Asset::icon('lock-fill', ['style' => 'margin-left: 0.25rem']);

	}

	// Sur le plan, le cadenas quitte le lien et gagne le bout de la cellule : une
	// série clôturée se repère alors sur toute la colonne.
	protected function getClosedCell(Series $eSeries): string {

		if($eSeries->isClosed() === FALSE) {
			return '';
		}

		if($eSeries['outcome'] !== NULL) {
			return '<span class="series-item-planning-closed" title="'.SeriesUi::p('outcome')->values[$eSeries['outcome']].'">'.SeriesUi::getOutcomeIcon($eSeries['outcome']).'</span>';
		}

		return '<span class="series-item-planning-closed" title="'.s("Série clôturée").'">'.\Asset::icon('lock-fill').'</span>';

	}

	protected function getSeriesForDisplay(Series $eSeries, Cultivation $eCultivation = new Cultivation(), string $tag = 'a'): string {

		$h = '<'.$tag.' href="/serie/'.$eSeries['id'].'" class="series-item-planning-details-name">'.SeriesUi::name($eSeries).'</'.$tag.'> ';

		if($eCultivation->notEmpty()) {
			$h .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);
		}

		$h .= self::getPerennialSeason($eSeries);

		return $h;

	}

	/**
	 * L'année d'une série pérenne, après la pastille de sa semaine de départ : sur le plan de culture et sur
	 * la ligne d'une culture des Récoltes (`\planning\HarvestWeekUi::getRow()`). Rien pour une annuelle.
	 */
	public static function getPerennialSeason(Series $eSeries): string {

		if($eSeries['cycle'] !== Series::PERENNIAL) {
			return '';
		}

		return '<span class="series-item-planning-details-cycle">'.s("Année {value}", $eSeries['perennialSeason']).'</span>';

	}

	public function harvest(Cultivation $eCultivation, \Collection $cTask): \Panel {

		$varieties = $cTask->reduce(fn(Task $eTask, int $n) => $n + $eTask['variety']->notEmpty(), 0);

		$h = '<table>';

			$h .= '<thead>';
				$h .= '<tr>';
					if($varieties) {
						$h .= '<th>'.s("Variété").'</th>';
					}
					$h .= '<th>'.s("Qualité").'</th>';
					$h .= '<th class="text-end">'.s("Récolte").'</th>';
						$h .= '<th class="text-end">'.s("Rendement").'</th>';
					$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';

				foreach($cTask as $eTask) {

					$area = NULL;

					if($eTask['variety']->notEmpty()) {
						foreach($eCultivation['cSlice'] as $eSlice) {
							if($eSlice['variety']->is($eTask['variety'])) {
								$area = $eSlice['area'];
							}
						}
					} else {
						$area = $eCultivation['series']['area'];
					}

					$h .= '<tr>';
						if($varieties) {
							$h .= '<td>'.($eTask['variety']->empty() ? '<i>'.s("Non spécifiée").'</u>' : encode($eTask['variety']['name'])).'</td>';
						}
						$h .= '<td>'.($eTask['harvestSize']->empty() ? '-' : encode($eTask['harvestSize']['name'])).'</td>';
						$h .= '<td class="text-end">'.\product\UnitUi::getValue(sprintf('%.1f', $eTask['totalHarvest']), $eTask['harvestUnit'], TRUE).'</td>';
						$h .= '<td class="text-end">';
							if($area > 0) {
								$h .= s("{value} / m²", \product\UnitUi::getValue(sprintf('%.2f', $eTask['totalHarvest'] / $area), $eTask['harvestUnit'], TRUE));
							} else {
								$h .= '-';
							}
							$h .= '</td>';
					$h .= '</tr>';

				}

			$h .= '<tbody>';

		$h .= '</table>';

		return new \Panel(
			id: 'panel-series-harvest',
			title: s("Récoltes"),
			subTitle: SeriesUi::getPanelHeader($eCultivation['series']),
			body: $h
		);

	}

	public function getTimeline(\structure\Structure $eStructure, int $season, Series $eSeries, Cultivation $eCultivation, \Collection $cTask): string {

		$eCultivation->expects(['harvestWeeks', 'harvestWeeksExpected']);

		$uiPlace = new PlaceUi();

		$items = [];

		[$startTs, $stopTs] = $uiPlace->getBounds($eStructure->conf(\map\Configuration::class), $season);

		[$minTs, $maxTs] = $this->getTimestampBounds($eSeries, $eCultivation, $cTask);

		// Une série clôturée ne se retouche pas : tout ce qu'elle prévoyait reste sur
		// le plan, interventions à faire comprises, mais rien ne s'y saisit plus (pas
		// de data-cultivation ni de data-task, donc pas de geste, et pas de bornes).
		// C'est le planning de la semaine qui sort ses interventions à faire.
		$editable = ($eSeries['status'] === Series::OPEN and $eSeries->canWrite());

		$showHarvest = function(bool $expected, int $first, int $last, \Closure $label) use($uiPlace, $eCultivation, $startTs, $stopTs, $minTs, $maxTs, $editable) {

			if($expected) {
				$css = 'color: var(--harvest); border-color: var(--harvest);';
				$class = 'series-item-timeline-event-expected';
			} else {
				$css = 'background-color: var(--harvest);';
				$class = 'series-item-timeline-event-done';
			}

			$harvestFirstWeek = date('W', $first);
			$harvestLastWeek = date('W', $last);

			if($harvestFirstWeek === $harvestLastWeek) {
				$week = s("S{value}", $harvestFirstWeek);
			} else {
				$week = s("S{from} à S{to}", ['from' => $harvestFirstWeek, 'to' => $harvestLastWeek]);
			}

			// La barre couvre ses semaines du lundi au dimanche, calée sur la grille
			// hebdomadaire. Pas de largeur minimale ici : elle déplacerait le début
			// dessiné, et la campagne paraîtrait sauter au premier étirement. La
			// lisibilité du « R » est assurée par le min-width CSS.
			$css .= $uiPlace->getPositionStyle($startTs, $stopTs, $first, $last + 7 * 86400);

			$item = '<div class="util-timeline-event series-item-timeline-event '.$class.($expected ? ' series-item-timeline-event-period' : ' series-item-timeline-event-harvest').'" '.(($expected and $editable) ? 'data-cultivation="'.$eCultivation['id'].'" ' : '').'data-first="'.$first.'" data-last="'.$last.'" data-action="'.encode(s("Récolte")).'" style="'.$css.'" title="'.$week.' - '.$label($eCultivation).'">';
				if($expected and $editable) {
					$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-first" title="'.encode(s("Glisser pour avancer ou retarder le début de la récolte attendue")).'"></div>';
				}
				$item .= '<span>'.s("R").'</span>';
				if($expected and $editable) {
					$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-last" title="'.encode(s("Glisser pour allonger ou raccourcir la récolte attendue")).'"></div>';
				}
			$item .= '</div>';

			return [$first, $last, $item];

		};

		// Récolte attendue
		if($eCultivation['harvestWeeksExpected'] !== NULL) {

			$label = fn(Cultivation $eCultivation) => s("Récolte attendue de {plant}", ['plant' => encode($eCultivation['plant']['name'])]);

			$first = NULL;
			$last = NULL;

			foreach($eCultivation['harvestWeeksExpected'] as $week) {

				$current = strtotime($week);

				if($first !== NULL) {

					$difference = ($current - $last) / 86400;

					if($difference > 45) {
						$items[] = $showHarvest(TRUE, $first, $last, $label);
						$first = NULL;
					}

				}

				$first ??= $current;
				$last = $current;

			}

			if($first !== NULL and $last !== NULL) {
				$items[] = $showHarvest(TRUE, $first, $last, $label);
			}

		}

		if($eCultivation['harvestWeeks'] !== NULL) {

			$label = fn(Cultivation $eCultivation) => s("Récolte de {plant}", ['plant' => encode($eCultivation['plant']['name'])]);

			$first = NULL;
			$last = NULL;

			foreach($eCultivation['harvestWeeks'] as $week) {

				$current = strtotime($week);

				if($first !== NULL) {

					$difference = ($current - $last) / 86400;

					if($difference > 45) {
						$items[] = $showHarvest(FALSE, $first, $last, $label);
						$first = NULL;
					}

				}

				$first ??= $current;
				$last = $current;


			}

			if($first !== NULL and $last !== NULL) {
				$items[] = $showHarvest(FALSE, $first, $last, $label);
			}

		}

		// Ce qui est passé se lit sur le ruban : la portion jusqu'à la dernière
		// intervention faite ou la dernière semaine récoltée est teintée.
		$doneUntilTs = NULL;

		if(($eCultivation['harvestWeeks'] ?? []) !== []) {
			$doneUntilTs = strtotime(max($eCultivation['harvestWeeks']).' + 7 DAY');
		}

		// Interventions
		foreach($cTask as $eTask) {

			$eAction = $eTask['action'];

			$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'];

			if($week === NULL) {
				continue;
			}

			$ts = strtotime($week);

			if($eTask['status'] === Task::DONE) {
				$color = 'background-color: '.$eAction['color'].';';
				$class = 'series-item-timeline-event-done';
				$doneUntilTs = max($doneUntilTs ?? $ts, $ts);
			} else {
				$color = 'color: '.$eAction['color'].'; border-color: '.$eAction['color'].';';
				$class = 'series-item-timeline-event-expected';
			}

			$movable = ($editable and $eTask['status'] === Task::TODO and $eTask['plannedWeek'] !== NULL);

			// Semis et plantation ouvrent leur fiche : espacement, densité, graines —
			// ce qu'on veut relire sans quitter le plan.
			$implantation = $this->getImplantation($eCultivation, $eAction, $week);

			$color .= $uiPlace->getPositionStyle($startTs, $stopTs, $ts, $ts, width: '1.2rem');

			$item = '<div class="util-timeline-event series-item-timeline-event '.$class.($movable ? ' series-item-timeline-event-movable' : '').($implantation === '' ? '' : ' series-item-timeline-event-detailed dropdown-toggle').'" '.($movable ? 'data-task="'.$eTask['id'].'" ' : '').($implantation === '' ? '' : 'data-dropdown="bottom-start" ').'data-week="'.$week.'" data-action="'.encode($eAction['name']).'" style="'.$color.'" title="'.s("S{week} - {action} de {plant}", ['week' => week_number($week), 'action' => encode($eAction['name']), 'plant' => encode($eCultivation['plant']['name'])]).'">';
				$item .= \work\ActionUi::getShort($eAction);
			$item .= '</div>';

			$item .= $implantation;

			$items[] = [$ts, $ts, $item];

		}

		if($eSeries['season'] !== $season) {
			$classMuted = 'bed-item-cultivation-muted';
		} else {
			$classMuted = '';
		}

		$full = ($minTs !== $maxTs);

		$h = '<div class="series-timeline-season series-item-timeline '.($full ? 'series-item-timeline-full' : '').' '.$classMuted.'">';

			if($full) {

				// Le ruban ne se saisit que si rien n'est encore fait : une intervention
				// faite ne bouge pas, le geste étirerait la série au lieu de la déplacer.
				$shiftable = ($editable and ($eSeries['tasksDone'] ?? 0) === 0);

				$done = ($doneUntilTs === NULL) ? 0 : min(100, max(0, ($doneUntilTs - $minTs) / ($maxTs - $minTs) * 100));

				// Chaque pastille occupe au moins 1.45rem à partir de sa semaine (min-width de
				// .util-timeline-event) : sans ce plancher, une récolte d'une semaine ou une
				// intervention en fin de série déborde du ruban. Une pérenne peut n'en porter aucune.
				if($items === []) {
					$minWidth = NULL;
				} else {
					$reachTs = max(array_column($items, 0));
					$minWidth = 'calc('.(($reachTs - $minTs) / ($stopTs - $startTs) * 100).'% + 1.45rem)';
				}

				$style = ($done > 0 ? '--series-done: '.round($done, 2).'%;' : '').$uiPlace->getPositionStyle($startTs, $stopTs, $minTs, $maxTs, minWidth: $minWidth);

				$h .= '<div class="util-timeline-event util-timeline-event-frame series-item-timeline-event series-item-timeline-event-full'.($shiftable ? ' series-item-timeline-event-shiftable' : '').'" data-first="'.$minTs.'" data-last="'.$maxTs.'" style="'.$style.'"></div>';

			}

			foreach($items as [$first, $last, $item]) {
				$class = '';
				if($first === $minTs) {
					$class .= 'series-item-timeline-event-min ';
				}
				if($last === $maxTs) {
					$class .= 'series-item-timeline-event-max ';
				}
				if($class) {
					$h .= '<div class="'.$class.'">'.$item.'</div>';
				} else {
					$h .= $item;
				}
			}

		$h .= '</div>';

		return $h;

	}

	public function getTimestampBounds(Series $eSeries, Cultivation $eCultivation, \Collection $cTask): array {

		$minTs = NULL;
		$maxTs = NULL;

		if($eSeries['cycle'] === Series::PERENNIAL) {

			if($eSeries['perennialSeason'] > 1) {
				$minTs = mktime(0, 0, 0, 1, 0, $eSeries['season']);
			}

			if($eSeries['perennialLifetime'] === NULL or $eSeries['perennialSeason'] < $eSeries['perennialLifetime']) {

				$maxTs = mktime(0, 0, 0, 12, 31, $eSeries['season']);

			}

		}

		[$minHarvest, $maxHarvest] = $eCultivation->getHarvestBounds();

		$minHarvestTs = ($minHarvest !== NULL) ? strtotime($minHarvest) : NULL;
		$maxHarvestTs = ($maxHarvest !== NULL) ? strtotime($maxHarvest.' + 7 DAY') : NULL;

		$minTs = ($minTs === NULL) ? $minHarvestTs : min($minTs, $minHarvestTs);
		$maxTs = ($maxTs === NULL) ? $maxHarvestTs : max($maxTs, $maxHarvestTs);

		// Interventions
		foreach($cTask as $eTask) {

			$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'];

			if($week === NULL) {
				continue;
			}

			$ts = strtotime($week);

			$minTs = ($minTs === NULL) ? $ts : min($minTs, $ts);
			$maxTs = ($maxTs === NULL) ? $ts : max($maxTs, $ts);

		}


		if(
			$eSeries['cycle'] === Series::PERENNIAL and
			$minTs === NULL
		) {
			$minTs = mktime(0, 0, 0, 1, 0, $eSeries['season']);
		}


		if(
			$eSeries['cycle'] === Series::PERENNIAL and
			$maxTs === NULL
		) {
			$maxTs = mktime(0, 0, 0, 12, 31, $eSeries['season']);
		}

		return [$minTs, $maxTs];

	}

	public function getList(Series $eSeries, \Collection $cSeriesPerennial, \Collection $cCultivation, \Collection $cTask, \Collection $cPlace, \Collection $cActionMain): string {

		$h = '';

		if($eSeries['cycle'] === Series::PERENNIAL) {

			$h .= '<div class="series-header-perennial-seasons util-block">';

				$h .= '<div class="series-header-perennial-seasons-label">'.s("Année").'</div>';

				for($i = 1; $cSeriesPerennial->notEmpty(); $i++) {

					if($cSeriesPerennial->offsetExists($i)) {

						$eSeriesPerennial = $cSeriesPerennial[$i];

						$h .= '<a href="'.SeriesUi::url($eSeriesPerennial).'" class="series-header-perennial-seasons-one '.($eSeriesPerennial['perennialSeason'] === $eSeries['perennialSeason'] ? 'series-header-perennial-seasons-one-selected' : '').'">'.$eSeriesPerennial['perennialSeason'].'</a>';

						$cSeriesPerennial->offsetUnset($i);

					} else {
						$h .= '<div class="series-header-perennial-seasons-one series-header-perennial-seasons-one-future">'.$i.'</div>';
					}

				}

				if($eSeries['perennialLifetime'] !== NULL) {

					for($i = $eSeriesPerennial['perennialSeason'] + 1; $i <= $eSeries['perennialLifetime']; $i++) {

						$h .= '<div class="series-header-perennial-seasons-one series-header-perennial-seasons-one-future">'.$i.'</div>';

					}

				}

			$h .= '</div>';

		}

		$h .= '<div class="crop-items">';

		foreach($cCultivation as $eCultivation) {
			$h .= $this->getOne($eSeries, $eCultivation, $cTask, $cActionMain);
		}

		$h .= '</div>';

		$h .= new SeriesUi()->updatePlace($eSeries, $cPlace);
		$h .= new SeriesUi()->getComment($eSeries);

		return $h;

	}

	public function getOne(Series $eSeries, Cultivation $eCultivation, \Collection $cTask, \Collection $cActionMain): string {

		$ePlant = $eCultivation['plant'];

		$h = '<div class="crop-item" id="cultivation-item-'.$eCultivation['id'].'">';
			$h .= '<div class="crop-item-header">';

				$h .= '<div class="crop-item-title">';
					$h .= \plant\PlantUi::getVignette($ePlant, '3rem').' ';
					$h .= '<h2>';
						$h .= \plant\PlantUi::link($ePlant);
						$h .= \sequence\CropUi::start($eCultivation, $cActionMain, fontSize: '0.7em');
					$h .= '</h2>';

					if(
						$eSeries->canWrite() and
						$eSeries['status'] === Series::OPEN
					) {

						$h .= '<div>';
							$h .= '<a data-dropdown="bottom-end" class="btn btn-color-primary dropdown-toggle" title="'.s("Ajouter une intervention").'">'.\Asset::icon('calendar-plus').'</a>';
							$h .= '<div class="dropdown-list">';
								$h .= '<div class="dropdown-title">'.encode($ePlant['name']).'</div>';
								$h .= '<a href="/series/task:createFromSeries?structure='.$eSeries['structure']['id'].'&series='.$eSeries['id'].'&cultivation='.$eCultivation['id'].'&status='.Task::TODO.'" class="dropdown-item">'.s("Planifier une future intervention").'</a>';
								$h .= '<a href="/series/task:createFromSeries?structure='.$eSeries['structure']['id'].'&series='.$eSeries['id'].'&cultivation='.$eCultivation['id'].'&status='.Task::DONE.'" class="dropdown-item">'.s("Ajouter une intervention déjà réalisée").'</a>';
								$h .= '<a href="/series/task:createFromSeries?structure='.$eSeries['structure']['id'].'&series='.$eSeries['id'].'&cultivation='.$eCultivation['id'].'&doneWeek='.\Clock::week().'&action='.$cActionMain[ACTION_RECOLTE]['id'].'&status='.Task::DONE.'" class="dropdown-item">'.s("Saisir une récolte").'</a>';
							$h .= '</div>';
							$h .= ' <a data-dropdown="bottom-end" class="btn btn-color-primary dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
							$h .= '<div class="dropdown-list">';
								$h .= '<div class="dropdown-title">'.encode($ePlant['name']).'</div>';
								$h .= '<a href="/series/cultivation:update?id='.$eCultivation['id'].'" class="dropdown-item">'.s("Modifier la production").'</a>';
								if($eSeries['plants'] > 1) {
									$h .= '<div class="dropdown-divider"></div>';
									$h .= '<a data-ajax="/series/cultivation:doDelete" post-id="'.$eCultivation['id'].'" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de la production de la série ?").'">'.s("Supprimer la production").'</a>';
								}
							$h .= '</div>';
						$h .= '</div>';
					}

				$h .= '</div>';

				$h .= new \sequence\CropUi()->getVarieties($eCultivation, $eCultivation['cSlice']);

			$h .= '</div>';

			$h .= '<div class="crop-item-presentation">';

				$filled = 0;
				$presentation = $this->getPresentation($eSeries, $eCultivation, $filled, plans: new \sequence\DensityPlanAiUi()->getFigures($eCultivation['densityPlans']));

				if($filled > 0) {
					$h .= $presentation;
				} else {
					$h .= '<div class="text-center">';
						$h .= '<a href="/series/cultivation:update?id='.$eCultivation['id'].'" class="btn btn-outline-primary">'.s("Configurer maintenant").'</a>';
					$h .= '</div>';
				}

			$h .= '</div>';

			$infos = $this->getVarietiesInfo($eCultivation, $cTask);

			if($infos) {
				$h .= '<div class="crop-item-info">';
					foreach($infos as $info) {
						$h .= '<div>'.$info.'</div>';
					}
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getVarietiesInfo(Cultivation $eCultivation, \Collection $cTask): array {

		$infos = [];

		switch($eCultivation['varietiesUnit']) {

			case Cultivation::PERCENT :

				if($eCultivation['cSlice']->notEmpty()) {

					$sum = $eCultivation['cSlice']->sum('partPercent');

					if($sum < 100) {
						$infos[] = s("La production couvre {value} % de l'assolement.", $sum);
					} else if($sum > 100) {
						$infos[] = \Asset::icon('exclamation-circle').'  '.s("La production couvre plus de 100 % de l'assolement.");
					}

				}

				break;

			case Cultivation::LENGTH :

				if($eCultivation['series']['use'] === Series::BLOCK) {
					$infos[] = \Asset::icon('exclamation-circle').'  '.s("Les variétés ont été réparties au mL alors que la culture est sur surface au libre au m².");
				} else {

					$sum = $eCultivation['cSlice']->sum('partLength');
					$limit = $eCultivation['series']['length'] ?? $eCultivation['series']['lengthTarget'];

					if(
						$limit !== NULL and
						$sum !== $limit
					) {

						$text = s("La production couvre {value} / {limit} mL.", ['limit' => $limit, 'value' => $sum]);

						if($sum < $limit) {
							$infos[] = $text;
						} else if($sum > $limit) {
							$infos[] = \Asset::icon('exclamation-circle').'  '.$text;
						}
					}

				}

				break;

			case Cultivation::AREA :

				if($eCultivation['series']['use'] === Series::BED) {
					$infos[] = \Asset::icon('exclamation-circle').'  '.s("Les variétés ont été réparties au m³ alors que la culture est sur planches au mL.");
				} else {

					$sum = $eCultivation['cSlice']->sum('partArea');
					$limit = $eCultivation['series']['area'] ?? $eCultivation['series']['areaTarget'];

					if(
						$limit !== NULL and
						$sum !== $limit
					) {

						$text = s("La production couvre {value} / {limit} m².", ['limit' => $limit, 'value' => $sum]);

						if($sum < $limit) {
							$infos[] = $text;
						} else if($sum > $limit) {
							$infos[] = \Asset::icon('exclamation-circle').'  '.$text;
						}
					}

				}

				break;

			case Cultivation::PLANT :

				$sum = $eCultivation['cSlice']->sum('partPlant');

				if($eCultivation['seedling'] === Cultivation::SOWING) {

					$limit = $eCultivation->getSeeds();

					if(
						$limit !== NULL and
						$sum !== $limit
					) {
						$infos[] = s("La production demande {limit} graines mais vous avez réparti les variétés sur {value} graines.", ['limit' => $limit, 'value' => $sum]);
					}

				} else {

					$limit = $eCultivation->getYoungPlants();

					if(
						$limit !== NULL and
						$sum !== $limit
					) {
						$infos[] = s("La production demande {limit} plants mais vous avez réparti les variétés sur {value} plants.", ['limit' => $limit, 'value' => $sum]);
					}

				}

				break;

			case Cultivation::TRAY :

				$sum = (int)ceil($eCultivation['cSlice']->sum('partTray'));

				if($eCultivation['seedling'] === Cultivation::SOWING) {
					$infos[] = \Asset::icon('exclamation-circle').'  '.s("Vous avez réparti les variétés en nombre de plateaux de semis alors que la production est implantée en semis direct.");
				} else {

					$plants = $eCultivation->getYoungPlants();
					$limit = $plants ? (int)ceil($plants / $eCultivation['varietiesTool']['routineValue']['value']) : NULL;

					if(
						$limit !== NULL and
						$sum !== $limit
					) {
						$infos[] = \Asset::icon('exclamation-circle').'  '.s("La production demande {limit} plateaux de semis mais vous avez réparti les variétés sur {value} plateaux.", ['limit' => $limit, 'value' => $sum]);
					}

					$tools = array_merge(...$cTask
						->find(fn($eTask) => (
							$eTask['action']['fqn'] === ACTION_SEMIS_PEPINIERE and
							($eTask['cultivation']->empty() or $eTask['cultivation']->is($eCultivation))
						))
						->getColumn('tools'));

					if(
						$tools !== [] and
						in_array($eCultivation['varietiesTool']['id'], $tools) === FALSE
					) {
						$infos[] = \Asset::icon('exclamation-circle').'  '.s("Vous avez réparti les variétés avec des plateaux de semis {value} alors que les interventions de semis en pépinière sur la série utilisent d'autres plateaux.", '<u>'.encode($eCultivation['varietiesTool']['name']).'</u>');
					}

				}

				break;

		}

		return $infos;

	}

	public function getPresentation(Series $eSeries, Cultivation $eCultivation, int &$filled = 0, $withYields = TRUE, string $plans = ''): string {

		$uiCrop = new \sequence\CropUi();

		$h = '<dl class="util-presentation util-presentation-max-content util-presentation-2">';

			$h .= $uiCrop->getPresentationYieldExpected($eSeries, $eCultivation, $filled);

			$h .= $uiCrop->getPresentationDistance($eSeries, $eCultivation, $filled, $plans);

			if($withYields) {
				$h .= '<dt>'.$this->p('yield').'</dt>';
				$h .= '<dd>'.$this->getYieldByUnits($eSeries, $eCultivation, $filled).'</dd>';
			}

			$h .= '<dt>'.s("Implantation").'</dt>';
			$h .= '<dd>';
				if($eCultivation['seedling']) {

					$filled++;

					$h .= [
						\series\Cultivation::SOWING => s("Semis direct"),
						\series\Cultivation::YOUNG_PLANT => '<span title="'.s("Autoproduction du plant").'">'.s("Plant autoproduit").'</span>',
						\series\Cultivation::YOUNG_PLANT_BOUGHT => '<span title="'.s("Achat du plant").'">'.s("Plant acheté").'</span>'
					][$eCultivation['seedling']];

				}
			$h .= '</dd>';

			if($withYields) {
				$h .= '<dt>'.$this->p('harvested').'</dt>';
				$h .= '<dd>'.$this->getHarvestedByUnits($eCultivation, $filled).'</dd>';
			}

			$h .= $uiCrop->getPresentationSeedlingSeeds($eSeries, $eCultivation);

			$h .= '<dt class="crop-item-harvest-label">'.self::p('harvestMonths')->label.'</dt>';

			if($eCultivation['harvestMonthsExpected'] !== NULL) {

				$h .= '<dd class="crop-item-harvest-value">';
					$h .= $this->getPeriod($eSeries['season'], 'month', $eSeries, $eCultivation, filled: $filled);
				$h .= '</dd>';

			} else {

				$h .= '<dd class="crop-item-harvest-value color-muted">';
					$h .= $this->getPeriod($eSeries['season'], 'month', $eSeries, $eCultivation, filled: $filled);
				$h .= '</dd>';

			}

		$h .= '</dl>';

		return $h;

	}

	public function getHarvestedByUnits(Cultivation $eCultivation, ?int &$filled = 0): ?string {

		if($eCultivation['harvestedByUnit'] === NULL) {
			return NULL;
		}

		$this->sortUnits($eCultivation);

		$harvested = [];

		foreach($eCultivation['harvestedByUnit'] as $unit => $value) {
			if($value > 0) {
				$harvested[] = \product\UnitUi::getValue($value, $unit, TRUE);
			}
		}

		if($harvested) {
			$filled++;
			return '<a href="/series/cultivation:harvest?id='.$eCultivation['id'].'">'.implode('<br/>', $harvested).'</a>';
		} else {
			return NULL;
		}

	}

	public function getYieldByUnits(Series $eSeries, Cultivation $eCultivation, ?int &$filled = 0): ?string {

		if($eCultivation['harvestedByUnit'] === NULL) {
			return NULL;
		}

		$values = [];

		foreach($eCultivation['yieldByUnit'] as $unit => $value) {
			if($eSeries['area'] > 0 and $value > 0) {
				$values[] = s("{value} / m²", \product\UnitUi::getValue(round($value / $eSeries['area'], 1), $unit, TRUE));
			}
		}

		if($values) {
			$filled++;
		}

		return $values ? implode('<br/>', $values) : NULL;

	}

	protected function sortUnits(Cultivation $eCultivation): void {

		$eCultivation->expects(['harvestedByUnit']);

		if($eCultivation['harvestedByUnit'] === NULL) {
			throw new \Exception('harvestedByUnit must not be NULL');
		}

		uksort($eCultivation['harvestedByUnit'], function($key1, $key2) use($eCultivation) {

			if($key1 === $eCultivation['mainUnit']) {
				return -1;
			}

			if($key2 === $eCultivation['mainUnit']) {
				return 1;
			}

			return strcmp($key1, $key2);

		});

	}

	public function getPeriod(int $season, string $interval, Series $eSeries, Cultivation $eCultivation, ?\util\FormUi $form = NULL, ?string $name = NULL, ?int $filled = 0): string {

		$eSeries->expects([
			'cycle'
		]);

		if($eSeries['cycle'] === Series::PERENNIAL) {

			$months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

			$h = '<div class="cultivation-periods cultivation-periods-00 '.($form === NULL ? '' : 'cultivation-periods-form').'">';
				$h .= '<div class="cultivation-periods-season cultivation-periods-season-12">';
					$h .= match($interval) {
						'month' => $this->getPeriodMonths($form, $name, $this->getHarvestMonths($eCultivation, $form, $season, $months)),
						'week' => $this->getPeriodWeeks($eCultivation, $form, $name, $season, $months)
					};
				$h .= '</div>';
			$h .= '</div>';

		} else {

			$monthsList = [
				$season - 1 => [10, 11, 12],
				$season => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
				$season + 1 => [1, 2, 3, 4, 5, 6],
			];

			$harvestMonths = [];
			$harvestMonthsFilled = [];

			foreach($monthsList as $year => $months) {

				$harvestMonths[$year] = $this->getHarvestMonths($eCultivation, $form, $year, $months);
				$harvestMonthsFilled[$year] = array_filter($harvestMonths[$year]);

			}

			if($harvestMonthsFilled) {
				$filled++;
			}

			if($form !== NULL) {
				$formSequence = '11';
			} else {
				$formSequence = ($harvestMonthsFilled[$season - 1] ? '1' : '0').''.($harvestMonthsFilled[$season + 1] ? '1' : '0');
			}

			$h = '<div class="cultivation-periods cultivation-periods-'.$formSequence.' '.($form === NULL ? '' : 'cultivation-periods-form').'">';

			foreach($monthsList as $year => $months) {

				if(
					$form === NULL and
					$year !== $season and
					$harvestMonthsFilled[$year] === []
				) {
					continue;
				}

				$h .= '<div class="cultivation-periods-season cultivation-periods-season-'.count($months).'">';
					$h .= '<div class="cultivation-periods-year" style="grid-column: span '.count($months).'">'.$year.'</div>';
					$h .= match($interval) {
						'month' => $this->getPeriodMonths($form, $name, $harvestMonths[$year]),
						'week' => $this->getPeriodWeeks($eCultivation, $form, $name, $year, $months)
					};
				$h .= '</div>';

			}

			$h .= '</div>';

		}

		return $h;

	}

	protected function getHarvestMonths(Cultivation $eCultivation, ?\util\FormUi $form, int $year, array $months): array {

		$harvestMonths = [];

		foreach($months as $month) {
			$harvestMonths[$year.'-'.sprintf('%02d', $month)] = NULL;
		}

		if($form === NULL) {

			$eCultivation->expects(['harvestMonths', 'harvestMonthsExpected']);

			foreach($eCultivation['harvestMonths'] ?? [] as $monthHarvest) {
				if(array_key_exists($monthHarvest, $harvestMonths)) {
					$harvestMonths[$monthHarvest] = 'done';
				}
			}

		}

		foreach($eCultivation['harvestMonthsExpected'] ?? [] as $monthExpected) {

			if(array_key_exists($monthExpected, $harvestMonths)) {

				if($harvestMonths[$monthExpected] === NULL) {
					$harvestMonths[$monthExpected] = 'expected';
				}

			}

		}

		return $harvestMonths;

	}

	protected function getPeriodMonths(?\util\FormUi $form, ?string $name, array $harvestMonths): string {

		$labels = \util\DateUi::months(type: 'letter');

		$h = '';

		foreach($harvestMonths as $date => $status) {

			$checked = ($status !== NULL);

			$h .= '<label class="cultivation-month">';

				if($form !== NULL) {
					$h .= $form->inputCheckbox($name.'[]', $date, ['checked' => $checked]);
				} else {
					if($checked) {
						$h .= '<span class="cultivation-month-checked cultivation-month-'.$status.'"></span>';
					}
				}

				$h .= '<div>'.$labels[date_month($date)].'</div>';

			$h .= '</label>';

		}

		return $h;

	}

	protected function getPeriodWeeks(Cultivation $eCultivation, \util\FormUi $form, string $name, int $year, array $months): string {

		$labels = \util\DateUi::months(type: 'letter');

		$h = '';

		foreach($months as $monthNumber) {

			$month = $year.'-'.sprintf('%02d', $monthNumber);

			$h .= '<div class="cultivation-week">';
				$h .= '<div class="cultivation-week-item">'.$labels[date_month($month)].'</div>';

				$weeks = \util\DateLib::convertMonthsToWeeks([$month]);

				foreach($weeks as $position => $week) {

					$monday = strtotime($week);

					$begin = \util\DateUi::textual($monday, \util\DateUi::DAY_MONTH);
					$end = \util\DateUi::textual(strtotime($week.' + 6 days'), \util\DateUi::DAY_MONTH);

					$h .= '<label title="'.$begin.' → '.$end.'"';

					if($position === 0) {

						$day = (strtotime($month.'-04') - $monday) / 86400;
						$h .= ' style="margin-top: '.((6 - $day) / 6 * 1.5).'rem"';

					}

					$checked = in_array($week, $eCultivation['harvestWeeksExpected'] ?? []);

					$h .= '>';
						$h .= $form->inputCheckbox($name.'[]', $week, ['checked' => $checked]);
						$h .= '<div class="cultivation-week-item">'.date('W', $monday).'</div>';
					$h .= '</label>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	public function getCropTitle(\structure\Structure $eStructure, \plant\Plant $ePlant): string {

		$h = '<div class="series-create-plant-title" data-plant-name="'.encode($ePlant['name']).'">';

			$h .= \plant\PlantUi::getVignette($ePlant, '3rem');
			$h .= '<h4>'.\plant\PlantUi::link($ePlant).'</h4>';

		$h .= '</div>';

		return $h;

	}

	public function create(Series $eSeries): \Panel {

		$eCultivation = new Cultivation([
			'structure' => $eSeries['structure'],
			'series' => $eSeries
		]);

		$form = new \util\FormUi();

		$h = $form->openAjax('/series/cultivation:doCreate', ['id' => 'cultivation-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('series', $eSeries['id']);
			$h .= $form->dynamicGroup($eCultivation, 'plant', function($d) {
				$d->autocompleteDispatch = '#cultivation-create';
			});
			$h .= '<div id="cultivation-create-content"></div>';

		$h .= $form->close();

		return new \Panel(
			id: 'panel-cultivation-create',
			title: s("Ajouter une production"),
			body: $h,
			subTitle: SeriesUi::getPanelHeader($eSeries)
		);

	}

	public function createContent(Series $eSeries, Cultivation $eCultivation, \Collection $cAction): string {

		$form = new \util\FormUi([
			'firstColumnSize' => 50
		]);

		$h = '<div id="series-create-plant-list">';
			$h .= '<div class="series-create-plant series-write-plant">';
				$h .= $this->getFieldsCreate($form, $eSeries['use'], $eCultivation, $cAction, '');
			$h .= '</div>';
		$h .= '</div>';

		$h .= $form->group(
			content: $form->submit(s("Ajouter la production"))
		);

		return $h;

	}

	public function getFieldsCreate(\util\FormUi $form, string $use, Cultivation $eCultivation, \Collection $cAction, string $suffix): string {

		$eCultivation->expects([
			'sequence', 'season',
			'series' => ['season', 'cycle', 'area', 'length'],
			'distance',
			'cSlice'
		]);

		$eCultivation['harvestedByUnit'] ??= [];
		$eCultivation['mainUnit'] ??= Cultivation::model()->getDefaultValue('mainUnit');

		$h = '<div class="cultivation-write">';

			$h .= new \sequence\CropUi()->getVarietyGroup($form, $eCultivation, $eCultivation['cSlice'], $suffix);
			$h .= new \sequence\CropUi()->getDistanceField($form, $eCultivation, $use, $suffix);
			$h .= self::getStaggerField($form, $eCultivation, $suffix);

			$h .= $form->dynamicGroup($eCultivation, 'seedling'.$suffix);
			$h .= $form->dynamicGroup($eCultivation, 'seedlingSeeds'.$suffix);

			if($eCultivation['sequence']->empty()) {
				$h .= self::getActionsField($form, $eCultivation, $cAction, $suffix);
			}

			$h .= self::getMainUnitField($form, $eCultivation, $suffix);
			$h .= self::getYieldExpectedField($form, $eCultivation, $suffix);

			if($eCultivation['sequence']->empty()) {
				$h .= self::getHarvestExpectedField($form, $eCultivation, $suffix);
			}

		$h .= '</div>';

		return $h;

	}

	protected static function getStaggerField(\util\FormUi $form, Cultivation $eCultivation, string $suffix = ''): string {

		return $form->dynamicGroup($eCultivation, 'stagger'.$suffix, function(\PropertyDescriber $d) use($eCultivation) {
			$d->group['class'] = ($eCultivation['distance'] === Cultivation::SPACING ? '' : 'hide');
		});

	}

	public function update(Cultivation $eCultivation, \Collection $cAction, array $implantation = []): \Panel {

		$eCultivation->expects([
			'series' => ['use', 'plants'],
			'cSlice'
		]);

		$form = new \util\FormUi();

		$h = $form->openAjax('/series/cultivation:doUpdate', ['id' => 'cultivation-update', 'class' => 'series-write-plant', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $eCultivation['id']);
			$h .= '<div class="cultivation-write" data-length="'.($eCultivation['series']['length'] ?? $eCultivation['series']['lengthTarget']).'" data-area="'.($eCultivation['series']['area'] ?? $eCultivation['series']['areaTarget']).'" data-use="'.$eCultivation['series']['use'].'" data-bed-width="'.$eCultivation['series']['bedWidth'].'" data-alley-width="'.$eCultivation['series']['alleyWidth'].'">';

				$h .= $form->dynamicGroup($eCultivation, 'plant', function($d) use($eCultivation) {
					$d->autocompleteDispatch = '#cultivation-update';
					$d->attributes = [
						'post-id' => $eCultivation['id']
					];
				});

				$h .= new \sequence\CropUi()->getVarietyGroup($form, $eCultivation, $eCultivation['cSlice']);
				$h .= new \sequence\CropUi()->getDistanceField($form, $eCultivation, $eCultivation['series']['use']);
				$h .= self::getStaggerField($form, $eCultivation);

				$h .= $form->dynamicGroup($eCultivation, 'seedling');
				$h .= $form->dynamicGroup($eCultivation, 'seedlingSeeds');

				if($cAction->notEmpty()) {
					$h .= self::getActionsField($form, $eCultivation, $cAction, implantation: $implantation);
				}

				$h .= self::getMainUnitField($form, $eCultivation);
				$h .= self::getYieldExpectedField($form, $eCultivation);
				$h .= self::getHarvestExpectedField($form, $eCultivation);
			$h .= '</div>';

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		$title = \plant\PlantUi::getVignette($eCultivation['plant'], '3rem').' '.encode($eCultivation['plant']['name']);

		return new \Panel(
			id: 'panel-cultivation-update',
			title: $title,
			body: $h,
			subTitle: SeriesUi::getPanelHeader($eCultivation['series']),
		);

	}

	private static function getHarvestExpectedField(\util\FormUi $form, Cultivation $eCultivation, string $suffix = ''): string {

		$eCultivation->expects(['harvestPeriodExpected']);

		$h = '<div class="cultivation-periods-wrapper">';

			$h .= $form->hidden('harvestPeriodExpected'.$suffix, $eCultivation['harvestPeriodExpected']);
			$h .= $form->dynamicGroup($eCultivation, 'harvestMonthsExpected'.$suffix, function($d) use($eCultivation) {
				$d->after = '<div class="field-action cultivation-periods-field"><a '.attr('onclick', 'Cultivation.changeExpectedHarvest(this, "week")').'>'.s("Raisonner par semaine").'</a></div>';
				$d->group['class'] = ($eCultivation['harvestPeriodExpected'] === Cultivation::MONTH ? '' : 'hide');
			});
			$h .= $form->dynamicGroup($eCultivation, 'harvestWeeksExpected'.$suffix, function($d) use($eCultivation) {
				$d->after = '<div class="field-action cultivation-periods-field"><a '.attr('onclick', 'Cultivation.changeExpectedHarvest(this, "month")').'>'.s("Raisonner par mois").'</a></div>';
				$d->group['class'] = ($eCultivation['harvestPeriodExpected'] === Cultivation::WEEK ? '' : 'hide');
			});

		$h .= '</div>';

		return $h;

	}

	private static function getMainUnitField(\util\FormUi $form, Cultivation $eCultivation, ?string $suffix = NULL): string {

		return $form->dynamicGroup($eCultivation, 'mainUnit'.$suffix, function(\PropertyDescriber $d) use($eCultivation, $suffix) {
			$d->attributes = ['onchange' => 'Cultivation.changeUnit(this, "'.$suffix.'")'];
		});

	}

	private static function getYieldExpectedField(\util\FormUi $form, Cultivation $eCultivation, ?string $suffix = NULL): string {

		$h = $form->dynamicGroup($eCultivation, 'yieldExpected'.$suffix, function(\PropertyDescriber $d) use($eCultivation, $suffix) {
			$d->append = s("{value}&nbsp;/ m²", '<span data-ref="cultivation-unit-'.$suffix.'">'.self::p('mainUnit')->values[$eCultivation['mainUnit']].'</span>');
		});

		if($eCultivation['harvestedByUnit']) {

			$h .= '<div data-ref="cultivation-weight-'.$suffix.'" '.($eCultivation['mainUnit'] !== Cultivation::KG ? 'hide' : '').'>';

			foreach(['bunch', 'unit'] as $unit) {

				if(array_key_exists($unit, $eCultivation['harvestedByUnit'])) {

					$h .= $form->dynamicGroup($eCultivation, $unit.'Weight'.$suffix);

				} else {
					$h .= $form->hidden($unit.'Weight', '');
				}

			}

			$h .= '</div>';

		}

		return $h;

	}

	private static function getActionsLabels(): array {

		return [
			ACTION_SEMIS_PEPINIERE => s("Semaine de semis en pépinière"),
			ACTION_PLANTATION => s("Semaine de plantation"),
			ACTION_SEMIS_DIRECT => s("Semaine de semis direct"),
		];

	}

	// À la création, l'implantation n'est pas encore choisie : les trois champs
	// sont masqués et Cultivation.changeSeedling en montre deux. En modification,
	// chaque action se saisit ou non selon l'intervention qu'elle désigne déjà :
	// une plantation à faire se ressaisit même si le semis, lui, est fait.
	private static function getActionsField(\util\FormUi $form, Cultivation $eCultivation, \Collection $cAction, ?string $suffix = NULL, array $implantation = []): string {

		$visible = match($eCultivation['seedling']) {
			Cultivation::SOWING => [ACTION_SEMIS_DIRECT],
			Cultivation::YOUNG_PLANT => [ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION],
			Cultivation::YOUNG_PLANT_BOUGHT => [ACTION_PLANTATION],
			default => []
		};

		$h = '';

		foreach(self::getActionsLabels() as $fqn => $label) {

			$weeks = $implantation[$fqn]['weeks'] ?? [];

			if($implantation[$fqn]['writable'] ?? TRUE) {

				$content = $form->week('actions'.$suffix.'['.$fqn.']', $weeks[0]['week'] ?? $eCultivation['season']);

			} else {

				$values = array_map(
					fn($item) => $item['done'] ?
						$form->fake(s("Fait semaine {value}", week_number($item['week'])), \Asset::icon('check')) :
						$form->fake(s("À faire semaine {value}", week_number($item['week'])), \Asset::icon('x')),
					$weeks
				);

				$content = '<div class="cultivation-implantation-weeks">'.implode('', $values).'</div>';

			}

			$h .= $form->group(
				'<span class="badge badge-solid ml-1" style="background-color: '.$cAction[$fqn]['color'].'">'.$label.'</span>',
				$content,
				['class' => in_array($fqn, $visible) ? '' : 'hide']
			);

		}

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Cultivation::model()->describer($property, [
			'structure' => s("Ferme"),
			'sequence' => s("Itinéraire technique"),
			'plant' => s("Espèce"),
			'density' => s("Densité d'implantation"),
			'rows' => s("Nombre de rangs par planche"),
			'rowSpacing' => s("Espace inter-rangs"),
			'plantSpacing' => s("Espace sur le rang"),
			'stagger' => s("Quinconce"),
			'seedling' => s("Implantation"),
			'seedlingSeeds' => '<span class="cultivation-field-young-plant">'.s("Nombre de graines par plant").'</span><span class="cultivation-field-sowing">'.s("Nombre de graines par trou").'</span>',
			'harvested' => s("Récolte"),
			'yield' => s("Rendement obtenu"),
			'yieldExpected' => s("Objectif de rendement"),
			'mainUnit' => s("Unité de récolte principale"),
			'bunchWeight' => s("Poids d'une botte"),
			'unitWeight' => s("Poids d'une unité"),
			'harvestMonths' => s("Mois de récolte"),
			'harvestMonthsExpected' => s("Mois de récolte attendus"),
			'harvestWeeks' => s("Semaines de récolte"),
			'harvestWeeksExpected' => s("Semaines de récolte attendues"),
			'createdAt' => s("Créé le"),
		]);

		switch($property) {

			case 'rowSpacing' :
				$d->append = s("cm inter-rangs");
				$d->attributes['oninput'] = 'Cultivation.updateDensity(this)';
				break;

			case 'plantSpacing' :
				$d->append = s("cm sur le rang");
				$d->attributes['oninput'] = 'Cultivation.updateDensity(this)';
				break;

			case 'rows' :
				$d->append = s("rangs");
				$d->attributes['oninput'] = 'Cultivation.updateDensity(this)';
				break;

			case 'stagger' :
				$d->field = 'switch';
				break;

			case 'density' :
				$d->append = s("plantes / m²");
				$d->attributes['oninput'] = 'Cultivation.updateDensity(this)';
				break;

			case 'harvested' :
				$d->values = \product\UnitUi::getBasicList();
				break;

			case 'plant' :
				$d->autocompleteBody = function(\util\FormUi $form, Cultivation $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id'],
						'new' => TRUE
					];
				};
				new \plant\PlantUi()->query($d);
				break;

			case 'sequence' :
				new \sequence\SequenceUi()->query($d);
				break;

			case 'seedling' :
				$d->field = 'select';
				$d->values = [
					Cultivation::SOWING => s("Semis direct"),
					Cultivation::YOUNG_PLANT => s("Plant autoproduit"),
					Cultivation::YOUNG_PLANT_BOUGHT => s("Plant acheté")
				];
				$d->attributes = fn(\util\FormUi $form, $e) => [
					'onchange' => 'Cultivation.changeSeedling(this)'
				];
				break;

			case 'seedlingSeeds' :
				$d->append = '<span class="cultivation-field-young-plant">'.s("graine(s) / plant").'</span><span class="cultivation-field-sowing">'.s("graine(s) / trou").'</span>';
				$d->group = fn($e) => [
					'data-action' => $e['seedling'] ?? ''
				];
				$d->attributes = [
					'onfocus' => 'this.select()'
				];
				break;

			case 'mainUnit' :
				$d->field = 'select';
				$d->required = TRUE;
				$d->values = \product\UnitUi::getBasicList();
				break;

			case 'unitWeight' :
				$d->append = s("kg / unité");
				break;

			case 'bunchWeight' :
				$d->append = s("kg / botte");
				break;

			case 'harvestMonthsExpected' :
				$d->field = function(\util\FormUi $form, Cultivation $e, string $property, \PropertyDescriber $d) {
					return new CultivationUi()->getPeriod($e['series']['season'], 'month', $e['series'], $e, form: $form, name: $d->name);
				};
				break;

			case 'harvestWeeksExpected' :
				$d->field = function(\util\FormUi $form, Cultivation $e, string $property, \PropertyDescriber $d) {
					return new CultivationUi()->getPeriod($e['series']['season'], 'week', $e['series'], $e, form: $form, name: $d->name);
				};
				break;

		}

		return $d;

	}

}
?>
