<?php
namespace series;

class AnalyzeUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::js('main', 'chart.js');

		\Asset::css('series', 'analyze.css');
		\Asset::css('series', 'series.css');

	}

	public function getPeriod(int $year, \Collection $cWorkingTimeMonth, \Collection $cWorkingTimeMonthBefore, \Collection $cWorkingTimeWeek, \Collection $cWorkingTimeWeekBefore): string {

		if($cWorkingTimeMonth->empty()) {
			$h = '<div class="util-empty">';
				$h .= s("La saisonnalité du travail sera disponible lorsque vous aurez renseigné du temps de travail réel cette année.");
			$h .= '</div>';
			return $h;
		}

		$h = '<h2>'.s("Temps de travail mensuel").'</h2>';
		$h .= $this->getPeriodMonthChart($cWorkingTimeMonth, $year, $cWorkingTimeMonthBefore, $year - 1);

		$h .= '<h2>'.s("Temps de travail hebdomadaire").'</h2>';
		$h .= $this->getPeriodWeekChart($cWorkingTimeWeek, $year, $cWorkingTimeWeekBefore, $year - 1);

		return $h;

	}


	public function getPeriodMonthTable(\Collection $cTimesheetMonth, \Collection $cTimesheetUser = new \Collection()): string {

		$globalTime = $cTimesheetMonth->sum('timeTotal');

		if($cTimesheetUser->empty()) {
			$columns = 0;
		} else {

			$maxColumns = 4;
			$userColumns = ($cTimesheetUser->count() > $maxColumns) ? $maxColumns - 1 : $cTimesheetUser->count();
			$cTimesheetUserSlice = $cTimesheetUser->slice(0, $userColumns);
			$columns = ($cTimesheetUser->count() >= $maxColumns) ? $maxColumns : $cTimesheetUser->count();

		}

		$h = '<div class="util-overflow-xs stick-xs">';

			$h .= '<table class="tr-even analyze-values">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Mois").'</th>';
						$h .= '<th class="text-end">'.s("Temps passé").'</th>';
						$h .= '<th></th>';

						if($columns > 0) {

							foreach($cTimesheetUserSlice as $eTimesheet) {
								$h .= '<th class="text-end">'.\user\UserUi::getVignette($eTimesheet['user'], '2rem').'</th>';
							}

							if($cTimesheetUser->count() > $maxColumns) {
								$h .= '<th class="text-end">'.s("Autres").'</th>';
							}

						}

					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					for($month = 1; $month <= 12; $month++) {

						if($cTimesheetMonth->offsetExists($month)) {
							$time = $cTimesheetMonth[$month]['timeTotal'];
						} else {
							$time = NULL;
						}

						$h .= '<tr>';
							$h .= '<td>';
								$h .= ucfirst(\util\DateUi::getMonthName($month));
							$h .= '</td>';
							$h .= '<td class="text-end">';
								if($time !== NULL) {
									$h .= \series\TaskUi::convertTime($time);
								} else {
									$h .= '-';
								}
							$h .= '</td>';
							$h .= '<td>';
								if($time !== NULL) {
									$h .= '<div class="util-annotation">';
										$h .= \util\TextUi::pc($time / $globalTime * 100, 0);
									$h .= '</div>';
								}
							$h .= '</td>';

							if($time !== NULL and $columns > 0) {

								$cTimesheet = $cTimesheetMonth[$month]['cTimesheetUser'];
								$totalTime = 0;

								foreach($cTimesheetUserSlice as $eTimesheetSlice) {

									$user = $eTimesheetSlice['user']['id'];

									if($cTimesheet->offsetExists($user)) {

										$userTime = $cTimesheet[$user]['timeTotal'];

										$h .= '<td class="text-end">';
											$h .= TaskUi::convertTime($userTime);
										$h .= '</td>';

										$totalTime += $userTime;

									} else {
										$h .= '<td class="text-end">-</td>';
									}

								}

								if($cTimesheetUser->count() > $maxColumns) {

									$remainingTime = $time - $totalTime;

									$h .= '<td class="text-end">';
										if($remainingTime > 0) {
											$h .= TaskUi::convertTime($remainingTime);
										}
									$h .= '</td>';

								}

							} else {
								$h .= str_repeat('<td class="text-end">-</td>', $columns);
							}

						$h .= '</tr>';

					}

					$h .= '<tr class="analyze-total">';

						$h .= '<td>';
							$h .= s("Total");
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($globalTime);
						$h .= '<td></td>';

						if($columns > 0) {

							foreach($cTimesheetUserSlice as $eTimesheet) {
								$h .= '<td class="text-end">'.TaskUi::convertTime($eTimesheet['timeTotal']).'</td>';
							}

							if($cTimesheetUser->count() > $maxColumns) {

								$remainingTime = $globalTime - $cTimesheetUserSlice->sum('timeTotal');
								$h .= '<td class="text-end">'.TaskUi::convertTime($remainingTime).'</td>';

							}

						}

					$h .= '</tr>';

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getPeriodWeekChart(\Collection $cWorkingTimeWeek, int $yearNow, \Collection $cWorkingTimeWeekBefore, int $yearBefore): string {

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		[$timesNow, $labelsNow] = $this->extractWeekChartValues($cWorkingTimeWeek);
		[$timesBefore] = $this->extractWeekChartValues($cWorkingTimeWeekBefore);

		$h = '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createDoubleBar(this, "'.s("Temps de travail {value}", $yearNow).'", '.json_encode($timesNow).', "'.s("Temps de travail {value}", $yearBefore).'", '.json_encode($timesBefore).', '.json_encode($labelsNow).', "h")').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	protected function extractWeekChartValues(\Collection $cWorkingTimeWeek, Product|\plant\Plant|null $e = NULL): array {

		$times = [];
		$labels = [];

		for($week = 1; $week <= 52; $week++) {

			if($cWorkingTimeWeek->offsetExists($week)) {

				$eWorkingTimeWeek = $cWorkingTimeWeek[$week];

				$times[] = round($eWorkingTimeWeek['timeTotal'], 2);

			} else {
				$times[] = 0;
			}

			$labels[] = $week;

		}

		return [$times, $labels];

	}

	public function getPeriodMonthChart(\Collection $cWorkingTimeMonth, int $yearNow, \Collection $cWorkingTimeMonthBefore, int $yearBefore): string {

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		[$timesNow, $labelsNow] = $this->extractMonthChartValues($cWorkingTimeMonth);
		[$timesBefore] = $this->extractMonthChartValues($cWorkingTimeMonthBefore);

		$h = '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createDoubleBar(this, "'.s("Temps de travail {value}", $yearNow).'", '.json_encode($timesNow).', "'.s("Temps de travail {value}", $yearBefore).'", '.json_encode($timesBefore).', '.json_encode($labelsNow).', "h")').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	protected function extractMonthChartValues(\Collection $cWorkingTimeMonth): array {

		$times = [];
		$labels = [];

		for($month = 1; $month <= 12; $month++) {

			if($cWorkingTimeMonth->offsetExists($month)) {

				$eWorkingTimeMonth = $cWorkingTimeMonth[$month];

				$times[] = round($eWorkingTimeMonth['timeTotal'], 2);

			} else {
				$times[] = 0;
			}

			$labels[] = \util\DateUi::getMonthName($month, type: 'short');

		}

		return [$times, $labels];

	}

	public function getWorkingTime(\structure\Structure $eStructure, int $year, ?int $month, ?string $week, \Collection $ccWorkingTimeMonthly, array $workingTimeWeekly, \Collection $ccTimesheetAction, \Collection $ccTimesheetCategory): string {

		if($ccWorkingTimeMonthly->empty()) {
			$h = '<div class="util-empty">';
				$h .= s("Le suivi du temps de travail en équipe sera disponible lorsque vous aurez renseigné du temps de travail réel cette année.");
			$h .= '</div>';
			return $h;
		}

		$tabs = '';

		if($month === NULL and $week === NULL) {
			$tabs .= '<a class="tab-item selected" data-tab="analyze-month" onclick="Lime.Tab.select(this)">'.s("Par mois").'</a>';
			$tabs .= '<a class="tab-item" data-tab="analyze-week" onclick="Lime.Tab.select(this)">'.s("Par semaine").'</a>';
		}

		$tabs .= '<a class="tab-item" data-tab="analyze-action" onclick="Lime.Tab.select(this)">'.s("Par intervention").'</a>';
		$tabs .= '<a class="tab-item" data-tab="analyze-category" onclick="Lime.Tab.select(this)">'.s("Par catégorie").'</a>';

		$panels = '';

			foreach($ccWorkingTimeMonthly as $cWorkingTimeMonthly) {

				$globalTime = $cWorkingTimeMonthly->sum('timeTotal');
				$eUser = $cWorkingTimeMonthly->first()['user'];

				$cTimesheetAction = $ccTimesheetAction[$eUser['id']] ??  new \Collection();
				$cTimesheetCategory = $ccTimesheetCategory[$eUser['id']] ??  new \Collection();

				$this->addDeadTime($cTimesheetAction, $globalTime, [
					'action' => new \work\Action([
						'id' => NULL,
						'color' => '#eee',
						'name' => s("Temps mort")
					]),
					'category' => new \work\Category()
				]);

				$this->addDeadTime($cTimesheetCategory, $globalTime, [
					'category' => new \work\Category([
						'id' => NULL,
						'name' => s("Temps mort")
					])
				]);

				$panels .= '<div class="analyze-working-time-wrapper">';

					$panels .= '<div class="analyze-working-time-user">';

						$panels .= '<h4>'.\user\UserUi::getVignette($eUser, '2rem').' '.$eUser->getName().'</h4>';

						$panels .= '<div class="analyze-working-time-value">'.\series\TaskUi::convertTime($globalTime).'</div>';

					$panels .= '</div>';

						$panels .= '<div class="tab-panel selected" data-tab="analyze-month">';
							$panels .= '<div class="analyze-working-time-months">';
								for($month = 1; $month <= 12; $month++) {

									$time = $cWorkingTimeMonthly[$year.'-'.sprintf('%02d', $month)]['timeTotal'] ?? 0;

									$panels .= '<div class="analyze-working-time-month">';
										$panels .= '<div class="analyze-working-time-month-name">'.\util\DateUi::getMonthName($month, type: 'short').'</div>';
										$panels .= ($time > 0) ? \series\TaskUi::convertTime($time) : '-';
									$panels .= '</div>';

								}
							$panels .= '</div>';
						$panels .= '</div>';

						$panels .= '<div class="tab-panel" data-tab="analyze-week">';
							$panels .= '<div class="analyze-working-time-weeks">';

								foreach([10 => "😍", 40 => "🙂", 50 => "🙁", 51 => "🤬"] as $hours => $icon) {

									$weeks = $workingTimeWeekly[$eUser['id']][$hours] ?? 0;

									if($hours === 10) {
										$weeks += $workingTimeWeekly[$eUser['id']][0] ?? 0;
									}

									$panels .= '<div class="analyze-working-time-week text-end">';
										$panels .= '<span class="analyze-working-time-week-name">';
											$panels .= match($hours) {
												10 => s("< {value}", TaskUi::convertTime(10, FALSE)),
												40 => s("{from} à {to}", ['from' => TaskUi::convertTime(11, FALSE), 'to' => TaskUi::convertTime(39, FALSE)]),
												50 => s("{from} à {to}", ['from' => TaskUi::convertTime(40, FALSE), 'to' => TaskUi::convertTime(49, FALSE)]),
												51 => s("> {value}", TaskUi::convertTime(50, FALSE))
											};
											$panels .= '<span class="analyze-working-time-icon">'.$icon.'</span>';
										$panels .= '</span> ';
									$panels .= '</div>';
									$panels .= '<div class="analyze-working-time-week">';
										$panels .= ($weeks > 0) ? p("{value} semaine", "{value} semaines", $weeks) : '-';
									$panels .= '</div>';

								}
							$panels .= '</div>';
						$panels .= '</div>';

					$panels .= '</div>';

					$panels .= '<div class="tab-panel" data-tab="analyze-action">';
						$panels .= '<div class="analyze-working-time-actions analyze-chart-table">';
							$panels .= $this->getActionTimesheetTable($eStructure, $year, $eUser, $cTimesheetAction);
							$panels .= $this->getActionPie($cTimesheetAction);
						$panels .= '</div>';
					$panels .= '</div>';

					$panels .= '<div class="tab-panel" data-tab="analyze-category">';
						$panels .= '<div class="analyze-working-time-categories analyze-chart-table">';
							$panels .= $this->getCategoryTimesheetTable($eStructure, $year, $eUser, $cTimesheetCategory);
							$panels .= $this->getCategoryPie($cTimesheetCategory);
						$panels .= '</div>';
					$panels .= '</div>';


			}

		return \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'series-analyze-team',
			'onrender' => 'Lime.Tab.restore(this, "analyze-month")'
		]);

	}

	public function getPace(\structure\Structure $eStructure, array $years, int $year, \Collection $cAction, \Collection $cPlant, \Collection $cPlantCompare, ?int $yearCompare): string {

		if($cPlant->empty()) {
			$h = '<div class="util-empty">';
				$h .= s("Le suivi de la productivité sera disponible lorsque vous aurez saisi du temps de travail cette année sur les interventions pour lesquelles elle est mesurée, à savoir {value}.", implode(', ', array_map('encode', $cAction->getColumn('name'))));
			$h .= '</div>';
			return $h;
		}

		$h = '';

		if($year >= \Clock::year()) {

			$h .= '<p class="util-info">';
				$h .= s("Les chiffres de productivité sont à prendre avec des pincettes pour les cultures encore en cours, car les données peuvent encore évoluer en fonction de vos saisies de temps.");
			$h .= '</p>';

		}

		$h .= '<div class="util-title">';
			$h .= '<h2>'.s("Par heure travaillée").'</h2>';
			if(count($years) > 1) {
				$h .= '<a data-dropdown="bottom-end" class="btn btn-outline-primary dropdown-toggle">'.s("Comparer").'</a>';
				$h .=' <div class="dropdown-list">';
					foreach($years as $selectedYear) {
						if($year !== $selectedYear) {
							$h .= '<a href="'.\structure\StructureUi::urlAnalyzeWorkingTime($eStructure, $year, \structure\Member::PACE).'?compare='.$selectedYear.'" class="dropdown-item">'.s("avec {value}", $selectedYear).'</a>';
						}
					}
					if($cPlantCompare->notEmpty()) {
						$h .= '<a href="'.\structure\StructureUi::urlAnalyzeWorkingTime($eStructure, $year, \structure\Member::PACE).'" class="dropdown-item">'.s("ne plus comparer").'</a>';
					}
				$h .= '</div>';
			}
		$h .= '</div>';

		$h .= '<div id="series-wrapper" class="series-item-wrapper series-item-working-time-wrapper util-overflow-md stick-md">';

			$h .= '<div class="series-item-header series-item-header-not-sticky series-item-working-time" style="grid-template-columns: 2rem 10rem 6rem 6rem '.str_repeat('8rem ', $cAction->count()).';">';

				$h .= '<div class="util-table-header" style="grid-column: span 2">';
					$h .= s("Espèce");
				$h .= '</div>';
				$h .= '<div class="util-table-header text-end">';
					$h .= s("Surface");
				$h .= '</div>';
				$h .= '<div class="util-table-header series-item-working-time-harvested text-end">';
					$h .= s("Récolté");
				$h .= '</div>';
				foreach($cAction as $eAction) {
					$h .= '<div class="util-table-header text-end" style="color: '.encode($eAction['color']).'">';
						$h .= encode($eAction['name']);
					$h .= '</div>';
				}
			$h .= '</div>';

			$h .= '<div class="series-item-body" style="width: fit-content">';

				foreach($cPlant as $ePlant) {

					$h .= $this->getPaceByPlant($ePlant, $cAction);

					if($cPlantCompare->offsetExists($ePlant['id'])) {
						$h .= $this->getPaceByPlant($cPlantCompare[$ePlant['id']], $cAction, $yearCompare);
					}

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

		return $h;

	}

	protected function getPaceByPlant(\plant\Plant $ePlant, \Collection $cAction, ?int $yearCompare = NULL): string {

		$area = $ePlant['area'];
		$cTask = $ePlant['cTask'];
		$cTaskHarvested = $ePlant['cTaskHarvested'];

		$h = '<div class="series-item series-item-working-time '.($yearCompare ? 'series-item-compare' : '').'" style="grid-template-columns: 2rem 10rem 6rem 6rem '.str_repeat('8rem ', $cAction->count()).';">';
			$h .= '<div>';
				if($yearCompare === NULL) {
					$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
				}
			$h .= '</div>';
			$h .= '<div>';
				if($yearCompare === NULL) {
					$h .= encode($ePlant['name']);
				} else {
					$h .= '<div class="text-end"><i>('.$yearCompare.')</i></div>';
				}
			$h .= '</div>';
			$h .= '<div class="text-end">';
				$h .= s("{value} m²", $area);
			$h .= '</div>';
			$h .= '<div class="series-item-working-time-harvested text-end">';
				foreach($cTaskHarvested as $eTaskHarvested) {
					$h .= '<div>'.\product\UnitUi::getValue(round($eTaskHarvested['totalHarvested']), $eTaskHarvested['harvestUnit'], TRUE).'</div>';
				}
			$h .= '</div>';

			foreach($cAction as $eAction) {

				$h .= '<div class="text-end" style="color: '.encode($eAction['color']).'">';

					if($cTask->offsetExists($eAction['id'])) {

						$eTask = $cTask[$eAction['id']];

						if($eTask['totalTime'] > 0) {
							$h .= new SeriesUi()->getPace($cTask[$eAction['id']]['area'], $cTask[$eAction['id']]['plants'], $eTask['action'], $eTask['totalTime'], $cTaskHarvested);
						}

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getActionTimesheetTable(\structure\Structure $eStructure, int $year, \user\User $eUser, \Collection $cTimesheet): string {

		$globalTime = $cTimesheet->sum('timeTotal');

		$h = '<table class="tr-even analyze-values stick-xs">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Intervention").'</th>';
					$h .= '<th>'.s("Catégorie").'</th>';
					$h .= '<th class="text-center" colspan="2">'.s("Temps passé").'</th>';
					$h .= '<th></th>';
				$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';

				$position = 0;

				foreach($cTimesheet as $eTimesheet) {

					if($position++ === 20) {
						break;
					}

					$isLost = ($eTimesheet['action']->notEmpty() and $eTimesheet['action']['id'] === NULL);

					$h .= '<tr class="'.($isLost ? 'analyze-lost' : '').'">';

						$h .= '<td>';
							$h .= encode($eTimesheet['action']['name']);
						$h .= '</td>';
						$h .= '<td class="color-muted">';
							if($eTimesheet['category']->notEmpty()) {
								$h .= encode($eTimesheet['category']['name']);
							}
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
						$h .= '</td>';
						$h .= '<td class="util-annotation">';
							if($globalTime > 0) {
								$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $globalTime * 100);
							}
						$h .= '</td>';
						$h .= '<td class="text-end">';
							if(
								$eStructure->canPersonalData() and
								$eTimesheet['action']['id'] !== NULL
							) {
								$h .= '<a href="/series/analyze:tasks?id='.$eStructure['id'].'&action='.$eTimesheet['action']['id'].'&status='.Task::DONE.'&year='.$year.'&category='.$eTimesheet['category']['id'].'&user='.$eUser['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('calendar3').'</a> ';
							}
						$h .= '</td>';

					$h .= '</tr>';

				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	protected function getCategoryTimesheetTable(\structure\Structure $eStructure, int $year, \user\User $eUser, \Collection $cTimesheet): string {

		$globalTime = $cTimesheet->sum('timeTotal');

		$h = '<table class="tr-even analyze-values stick-xs">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Catégorie").'</th>';
					$h .= '<th class="text-center" colspan="2">'.s("Temps passé").'</th>';
					$h .= '<th></th>';
				$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';

				$position = 0;

				foreach($cTimesheet as $eTimesheet) {

					if($position++ === 15) {
						break;
					}

					$isLost = ($eTimesheet['category']->notEmpty() and $eTimesheet['category']['id'] === NULL);

					$h .= '<tr class="'.($isLost ? 'analyze-lost' : '').'">';

						$h .= '<td>';
							$h .= encode($eTimesheet['category']['name']);
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
						$h .= '</td>';
						$h .= '<td class="util-annotation">';
							if($globalTime > 0) {
								$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $globalTime * 100);
							}
						$h .= '</td>';
						$h .= '<td class="text-end">';
							if(
								$eStructure->canPersonalData() and
								$eTimesheet['category']['id'] !== NULL
							) {
								$h .= '<a href="/series/analyze:tasks?id='.$eStructure['id'].'&category='.$eTimesheet['category']['id'].'&status='.Task::DONE.'&year='.$year.'&category='.$eTimesheet['category']['id'].'&user='.$eUser['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('calendar3').'</a> ';
							}
						$h .= '</td>';

					$h .= '</tr>';

				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function getPlantTime(\plant\Plant $ePlant, int $year, \Collection $cPlantTimesheet, \Collection $cTimesheetByAction, \Collection $cTimesheetByUser, \Collection $cPlantMonth, \Collection $cPlantMonthBefore, \Collection $cPlantWeek, \Collection $cPlantWeekBefore): \Panel {

		$h = '';

		if($cPlantTimesheet->notEmpty()) {

			$h .= $this->getPlantTimesheet($ePlant, $cPlantTimesheet, $year);

			if($cTimesheetByAction->notEmpty()) {

				$h .= '<h3>'.s("Temps de travail par intervention").'</h3>';
				$h .= '<div class="analyze-chart-table">';
					$h .= $this->getActionPie($cTimesheetByAction);
					$h .= $this->getPlantActionsTable($ePlant, $year, $cTimesheetByAction, $cTimesheetByUser);
				$h .= '</div>';

				$h .= '<br/>';

				$h .= '<h3>'.s("Temps de travail mensuel").'</h3>';
				$h .= '<div class="analyze-chart-table">';
					$h .= $this->getPeriodMonthTable($cPlantMonth);
					$h .= $this->getPeriodMonthChart($cPlantMonth, $year, $cPlantMonthBefore, $year - 1);
				$h .= '</div>';

				$h .= '<br/>';

				$h .= '<h3>'.s("Temps de travail hebdomadaire").'</h3>';
				$h .= $this->getPeriodWeekChart($cPlantWeek, $year, $cPlantWeekBefore, $year - 1);

			} else {
				$h .= '<p class="util-empty">';
					$h .= s("Il n'y a aucune intervention pour cette espèce en {value}.", $year);
				$h .= '</p>';
			}

		} else {

			$h .= '<p class="util-empty">';
				$h .= s("Vous n'avez pas travaillé sur une série sur cette espèce.");
			$h .= '</p>';

		}

		$title = s("{value} en {year}", ['value' => encode($ePlant['name']), 'year' => $year]);

		return new \Panel(
			id: 'panel-plant-analyze',
			documentTitle: $title,
			body: $h,
			header: '<h4>'.s("ESPÈCE CULTIVÉE").'</h4><h2 class="panel-title">'.\plant\PlantUi::getVignette($ePlant, '3rem').' '.$title.'</h2>',
		);

	}

	public function getPlantTimesheet(\plant\Plant $ePlant, \Collection $cPlantTimesheet, ?int $year): string {

		// Une seule tuile, déjà affichée, ne mène nulle part
		if($cPlantTimesheet->count() === 1 and $cPlantTimesheet->first()['year'] === $year) {
			return '';
		}

		$h = '<div class="util-figures util-figures-overflow">';

			foreach($cPlantTimesheet as $ePlantTimesheet) {

				$h .= '<a data-ajax="/plant/plant:analyzeTime?id='.$ePlant['id'].'&year='.$ePlantTimesheet['year'].'" data-ajax-method="get" class="util-figure'.($ePlantTimesheet['year'] === $year ? ' util-figure-selected' : '').'">';
					$h .= '<div class="util-figure-label util-figure-label-top">'.$ePlantTimesheet['year'].'</div>';
					$h .= '<div class="util-figure-value">'.TaskUi::convertTime($ePlantTimesheet['timeTotal']).'</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getActionPie($cTimesheetByAction): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition par intervention"),
			$cTimesheetByAction,
			'timeTotal',
			fn($eTimesheet) => encode($eTimesheet['action']['name']),
			fn($eTimesheet) => encode($eTimesheet['action']['color'])
		);

	}

	protected function getPlantActionsTable(\plant\Plant $ePlant, int $year, \Collection $cTimesheetByAction, \Collection $cTimesheetByUser): string {

		$timesheetGlobalTime = $cTimesheetByAction->sum('timeTotal');

		$displayUsers = $ePlant['structure']->canPersonalData();
		$maxColumns = 4;
		$userColumns = ($cTimesheetByUser->count() > $maxColumns) ? $maxColumns - 1 : $cTimesheetByUser->count();
		$cTimesheetByUserSlice = $cTimesheetByUser->slice(0, $userColumns);

		$h = '<div class="util-overflow-sm stick-xs analyze-values">';

			$h .= '<table class="tr-even">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Espèce").'</th>';
						$h .= '<th class="text-center" colspan="2">'.s("Temps passé").'</th>';

						if($displayUsers) {

							foreach($cTimesheetByUserSlice as $eTimesheet) {
								$h .= '<th class="text-end">'.\user\UserUi::getVignette($eTimesheet['user'], '2rem').'</th>';
							}

							if($cTimesheetByUser->count() > $maxColumns) {
								$h .= '<th class="text-end">'.s("Autres").'</th>';
							}

						}

					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';
					foreach($cTimesheetByAction as $eTimesheet) {

						$h .= '<tr>';

							$h .= '<td>';
								$h .= encode($eTimesheet['action']['name']);
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
							$h .= '</td>';
							$h .= '<td class="util-annotation">';
								$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $timesheetGlobalTime * 100, 0);
							$h .= '</td>';

							if($displayUsers) {

								$cTimesheet = $eTimesheet['cTimesheetUser'];
								$totalTime = 0;

								foreach($cTimesheetByUserSlice as $eTimesheetSlice) {

									$user = $eTimesheetSlice['user']['id'];

									if($cTimesheet->offsetExists($user)) {

										$time = $cTimesheet[$user]['timeTotal'];

										$h .= '<td class="text-end">';
											$h .= TaskUi::convertTime($time);
										$h .= '</td>';

										$totalTime += $time;

									} else {
										$h .= '<td class="text-end">-</td>';
									}

								}

								if($cTimesheetByUser->count() > $maxColumns) {

									$remainingTime = $eTimesheet['timeTotal'] - $totalTime;

									$h .= '<td class="text-end">';
										if($remainingTime > 0) {
											$h .= TaskUi::convertTime($remainingTime);
										}
									$h .= '</td>';

								}

							}

						$h .= '</tr>';

					}

					$h .= '<tr class="analyze-total">';

						$h .= '<td>';
							$h .= s("Total");
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($timesheetGlobalTime);
						$h .= '<td></td>';

						if($displayUsers) {

							foreach($cTimesheetByUserSlice as $eTimesheet) {
								$h .= '<td class="text-end">'.TaskUi::convertTime($eTimesheet['timeTotal']).'</td>';
							}

							if($cTimesheetByUser->count() > $maxColumns) {
								$remainingTime = $timesheetGlobalTime - $cTimesheetByUserSlice->sum('timeTotal');
								$h .= '<td class="text-end">'.TaskUi::convertTime($remainingTime).'</td>';
							}

						}

					$h .= '</tr>';
				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getBestActions(
		\structure\Structure $eStructure, int $year, ?int $month, ?string $week, ?float $globalTime,
		\Collection $cTimesheetAction, \Collection $cccTimesheetActionMonthly,
		\Collection $cTimesheetCategory, \Collection $ccTimesheetCategoryMonthly,
		\Collection $cTimesheetPlant, \Collection $ccTimesheetPlantMonthly,
		\Collection $cTimesheetSeries,
		\Collection $ccTimesheetSeriesMonthly,
		bool $monthly
	): string {

		if(
			$cTimesheetPlant->empty() and
			$cTimesheetSeries->empty() and
			$cTimesheetAction->empty()
		) {
			$h = '<div class="util-empty">';
				$h .= s("L'analyse du temps de travail sera disponible lorsque vous aurez saisi du temps de travail cette année.");
			$h .= '</div>';
			return $h;
		}

		if($globalTime !== NULL) {

			$this->addDeadTime($cTimesheetCategory, $globalTime, [
				'category' => new \work\Category([
					'id' => NULL,
					'name' => s("Temps mort")
				])
			]);

		}

		$tabs = '<a class="tab-item selected" data-tab="analyze-plant" onclick="Lime.Tab.select(this)">'.s("Par espèce").'</a>';
		$tabs .= '<a class="tab-item" data-tab="analyze-task" onclick="Lime.Tab.select(this)">'.s("Par intervention").'</a>';
		$tabs .= '<a class="tab-item" data-tab="analyze-category" onclick="Lime.Tab.select(this)">'.s("Par catégorie").'</a>';
		$tabs .= '<a class="tab-item" data-tab="analyze-series" onclick="Lime.Tab.select(this)">'.s("Par série").'</a>';

		$panels = '';

			$panels .= '<div class="tab-panel selected" data-tab="analyze-category">';
				$panels .= '<div class="'.($monthly ? '' : 'analyze-chart-table').'">';
					if($monthly === FALSE) {
						$panels .= $this->getCategoryPie($cTimesheetCategory);
					}
					$panels .= $this->getCategoryTable($eStructure, $year, $month, $week, $globalTime, $cTimesheetCategory, $ccTimesheetCategoryMonthly, $monthly);
				$panels .= '</div>';
			$panels .= '</div>';

			$panels .= '<div class="tab-panel" data-tab="analyze-plant">';
				if($cTimesheetPlant->empty()) {
					$panels .= '<div class="util-block util-block-empty">';
						$panels .= s("Vous n'avez pas encore travaillé sur des espèces cette année.");
					$panels .= '</div>';
				} else {
					$panels .= '<div class="'.($monthly ? '' : 'analyze-chart-table').'">';
						if($monthly === FALSE) {
							$panels .= $this->getPlantPie($cTimesheetPlant);
						}
						$panels .= $this->getPlantTable($eStructure, $year, $month, $week, $cTimesheetPlant, $ccTimesheetPlantMonthly, $monthly);
					$panels .= '</div>';
				}
			$panels .= '</div>';

			$panels .= '<div class="tab-panel" data-tab="analyze-series">';
				if($cTimesheetPlant->empty()) {
					$panels .= '<div class="util-block util-block-empty">';
						$panels .= s("Vous n'avez pas encore travaillé sur des séries cette année.");
					$panels .= '</div>';
				} else {
					$panels .= '<div class="analyze-chart-table">';
						$panels .= $this->getSeriesPie($cTimesheetSeries);
						$panels .= $this->getSeriesTable($cTimesheetSeries);
					$panels .= '</div>';
					if($ccTimesheetSeriesMonthly->notEmpty()) {
						$panels .= '<h2>'.s("Évolution mensuelle").'</h2>';
						$panels .= $this->getSeriesMonthly($ccTimesheetSeriesMonthly);
					}
				}
			$panels .= '</div>';

			$panels .= '<div class="tab-panel" data-tab="analyze-task">';
				$panels .= '<div class="'.($monthly ? '' : 'analyze-chart-table').'">';
					if($monthly === FALSE) {
						$panels .= $this->getBestActionsPie($cTimesheetAction);
					}
					$panels .= $this->getBestActionsTable($eStructure, $year, $month, $week, $cTimesheetAction, $cccTimesheetActionMonthly, $monthly);
				$panels .= '</div>';
			$panels .= '</div>';

		return \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'series-analyze-working-time',
			'onrender' => 'Lime.Tab.restore(this, "analyze-plant")'
		]);

	}

	protected function addDeadTime(\Collection $cTimesheet, float $globalTime, array $properties): void {

		$timesheetGlobalTime = $cTimesheet->sum('timeTotal');

		$difference = round($globalTime - $timesheetGlobalTime, 2);

		if($difference > 0) {

			$cTimesheet[] = new Timesheet($properties + [
				'timeTotal' => $difference
			]);

			$cTimesheet->sort(['timeTotal' => SORT_DESC]);

		}

	}

	public function getCategoryPie(\Collection $cTimesheet): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition par catégorie"),
			$cTimesheet,
			'timeTotal',
			fn($eTimesheet) => encode($eTimesheet['category']['name'])
		);

	}

	protected function getCategoryTable(\structure\Structure $eStructure, int $year, ?int $month, ?string $week, ?float $globalTime, \Collection $cTimesheet, \Collection $ccTimesheetMonthly, bool $monthly): string {

		$search = new \Search()
			->sort(GET('sort'))
			->validateSort(['category', 'time'], 'time-');

		$cTimesheet->sort($search->buildSort([
			'category' => fn($direction) => [
				'category' => ['name' => $direction]
			]
		]));

		$globalTime ??= $cTimesheet->sum('timeTotal');

		$h = '<div class="'.($monthly ? 'util-overflow-lg' : '').' stick-xs">';

			$h .= '<table class="tr-even analyze-values '.($monthly ? 'analyze-month-table-5' : '').'">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.$search->linkSort('category', s("Catégorie")).'</th>';
						$h .= '<th class="text-end">'.$search->linkSort('time', s("Temps passé"), SORT_DESC).'</th>';
						$h .= '<th>';
							$h .= new \main\ChartUi()->getMonthlyLink($monthly ? 'time' : NULL, 'time');
						$h .= '</th>';

						if($monthly) {
							for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {
								$h .= '<th class="text-center">'.\util\DateUi::getMonthName($monthlyMonth, type: 'short').'</th>';
							}
						}

						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					foreach($cTimesheet as $eTimesheet) {

						$eCategory = $eTimesheet['category'];
						$isLost = ($eCategory['id'] === NULL);

						$h .= '<tr class="'.($isLost ? 'analyze-lost' : '').'">';

							$h .= '<td>';
								$h .= encode($eCategory['name']);
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
							$h .= '</td>';
							$h .= '<td class="util-annotation">';
								if($globalTime > 0) {
									$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $globalTime * 100);
								}
							$h .= '</td>';

							if($monthly) {

								if($isLost) {

									$h .= '<td colspan="13"></td>';

								} else {

									for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {

										$eTimesheetMonthly = $ccTimesheetMonthly[$eCategory['id']][$monthlyMonth] ?? new Timesheet();

										$h .= '<td class="text-end analyze-month-value">';

											if($eTimesheetMonthly->notEmpty()) {
												$h .= TaskUi::convertTime(max(1, $eTimesheetMonthly['timeTotal']), showMinutes: FALSE);
											}

										$h .= '</td>';

									}

								}

							} else {

								if($isLost) {
									$h .= '<td></td>';
								}

							}

							if($isLost === FALSE) {
								$h .= '<td class="text-end">';
									if($eStructure->canPersonalData()) {
										$h .= '<a href="/series/analyze:tasks?id='.$eStructure['id'].'&status='.Task::DONE.'&category='.$eTimesheet['category']['id'].'&year='.$year.($month ? '&month='.$month : '').($week ? '&week='.$week : '').'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('calendar3').'</a> ';
									}
									if($month === NULL and $week === NULL) {
										$h .= '<a href="/work/action:analyzeTime?category='.$eTimesheet['category']['id'].'&year='.$year.'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('search').'</a>';
									}
								$h .= '</td>';
							}

						$h .= '</tr>';

					}

					$h .= '<tr class="analyze-total">';

						$h .= '<td>';
							$h .= s("Total");
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($globalTime);
						$h .= '<td colspan="2">';
						$h .= '</td>';

					$h .= '</tr>';
				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getPlantPie(\Collection $cTimesheet): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition par espèce"),
			$cTimesheet,
			'timeTotal',
			fn($eTimesheet) => encode($eTimesheet['plant']['name'])
		);

	}

	protected function getPlantTable(\structure\Structure $eStructure, int $year, ?int $month, ?string $week, \Collection $cTimesheet, \Collection $ccTimesheetMonthly, bool $monthly): string {

		$timesheetGlobalTime = (float)$cTimesheet->sum('timeTotal');

		if($timesheetGlobalTime === 0.0) {
			return '<div class="util-info">'.s("Vous n'avez travaillé sur aucune espèce sur la période !").'</div>';
		}

		$search = new \Search()
			->sort(GET('sort'))
			->validateSort(['plant', 'timeTotal'], 'time-');

		$cTimesheet->sort($search->buildSort([
			'plant' => fn($direction) => [
				'plant' => ['name' => $direction]
			]
		]));

		$h = '<div class="analyze-values">';

			$h .= '<div class="'.($monthly ? 'util-overflow-lg' : '').' stick-xs">';

				$h .= '<table class="tr-even  '.($monthly ? 'analyze-month-table-5' : '').'">';

					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.$search->linkSort('plant', s("Espèce")).'</th>';
							$h .= '<th class="text-end">'.$search->linkSort('time', s("Temps passé"), SORT_DESC).'</th>';
							$h .= '<th>';
								$h .= new \main\ChartUi()->getMonthlyLink($monthly ? 'time' : NULL, 'time');
							$h .= '</th>';

							if($monthly) {
								for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {
									$h .= '<th class="text-center">'.\util\DateUi::getMonthName($monthlyMonth, type: 'short').'</th>';
								}
							} else {
								$h .= '<th class="text-end">'.s("Dont hors série").'</th>';
							}

							$h .= '<th></th>';
						$h .= '</tr>';
					$h .= '</thead>';
					$h .= '<tbody>';

						foreach($cTimesheet as $eTimesheet) {

							$h .= '<tr>';

								$h .= '<td>';
									$h .= \plant\PlantUi::getVignette($eTimesheet['plant'], '2rem').'  ';
									$h .= encode($eTimesheet['plant']['name']);
								$h .= '</td>';
								$h .= '<td class="text-end">';
									$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
								$h .= '</td>';
								$h .= '<td class="util-annotation">';
									$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $timesheetGlobalTime * 100, 0);
								$h .= '</td>';

								if($monthly) {

									for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {

										$eTimesheetMonthly = $ccTimesheetMonthly[$eTimesheet['plant']['id']][$monthlyMonth] ?? new Timesheet();

										$h .= '<td class="text-end analyze-month-value">';

											if($eTimesheetMonthly->notEmpty()) {
												$h .= TaskUi::convertTime(max(1, $eTimesheetMonthly['timeTotal']), showMinutes: FALSE);
											}

										$h .= '</td>';

									}

								} else {
									$h .= '<td class="text-end">';
										if($eTimesheet['timeNoSeries'] > 0) {
											$h .= TaskUi::convertTime($eTimesheet['timeNoSeries']);
										} else {
											$h .= '-';
										}
									$h .= '</td>';
								}

								$h .= '<td class="text-end td-min-content">';
									if($eStructure->canPersonalData()) {
										$h .= '<a href="/series/analyze:tasks?id='.$eStructure['id'].'&status='.Task::DONE.'&plant='.$eTimesheet['plant']['id'].'&year='.$year.($month ? '&month='.$month : '').($week ? '&week='.$week : '').'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('calendar3').'</a> ';
									}
									if($month === NULL and $week === NULL) {
										$h .= '<a href="/plant/plant:analyzeTime?id='.$eTimesheet['plant']['id'].'&year='.$year.'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('search').'</a>';
									}
								$h .= '</td>';

							$h .= '</tr>';

						}

						$h .= '<tr class="analyze-total">';

							$h .= '<td>';
								$h .= s("Total");
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= TaskUi::convertTime($timesheetGlobalTime);
							$h .= '</td>';
							$h .= '<td colspan="'.($monthly ? 2 + 12 : 3).'">';
							$h .= '</td>';

						$h .= '</tr>';

					$h .= '</tbody>';
				$h .= '</table>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getSeriesPie(\Collection $cTimesheet): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition par série"),
			$cTimesheet,
			'timeTotal',
			fn($eTimesheet) => $eTimesheet['series']->empty() ? s("Hors série") : encode($eTimesheet['series']['name'])
		);

	}

	public function getSeriesMonthly(\Collection $ccTimesheetMonthly): string {

		return new \main\ChartUi()->buildMonthly(
			$ccTimesheetMonthly,
			'timeTotal',
			fn($eTimesheet) => $eTimesheet['hasSeries'] ? s("Dans une série") : s("Hors série")
		);

	}

	protected function getSeriesTable(\Collection $cTimesheet): string {

		$search = new \Search()
			->sort(GET('sort'))
			->validateSort(['series', 'time'], 'time-');

		$cTimesheet->sort($search->buildSort([
			'series' => fn($direction) => [
				'series' => ['name' => $direction]
			]
		]));

		$timesheetGlobalTime = $cTimesheet->sum('timeTotal');

		$h = '<div class="analyze-values">';

			$h .= '<table class="tr-even stick-xs">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.$search->linkSort('series', s("Série")).'</th>';
						$h .= '<th class="text-center" colspan="2">'.$search->linkSort('time', s("Temps passé"), SORT_DESC).'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					$limit = 20;

					foreach($cTimesheet as $eTimesheet) {

						$h .= '<tr>';

							$h .= '<td>';
								$h .= $eTimesheet['series']->empty() ? s("Hors série") : SeriesUi::link($eTimesheet['series']);
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
							$h .= '</td>';
							$h .= '<td class="util-annotation">';
								$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $timesheetGlobalTime * 100, 0);
							$h .= '</td>';

						$h .= '</tr>';

						if(--$limit === 0) {
							break;
						}

					}

					$h .= '<tr class="analyze-total">';

						$h .= '<td>';
							$h .= s("Total");
						$h .= '</td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($timesheetGlobalTime);
						$h .= '<td colspan="2">';
						$h .= '</td>';

					$h .= '</tr>';
				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getBestActionsPie(\Collection $cTimesheet): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition du temps de travail"),
			$cTimesheet,
			'timeTotal',
			fn($eTimesheet) => $eTimesheet['action']['name'],
			fn($eTimesheet) => $eTimesheet['action']['color']
		);

	}

	protected function getBestActionsTable(\structure\Structure $eStructure, int $year, ?int $month, ?string $week, \Collection $cTimesheet, \Collection $cccTimesheetMonthly, bool $monthly): string {

		$search = new \Search()
			->sort(GET('sort'))
			->validateSort(['action', 'time'], 'time-');

		$cTimesheet->sort($search->buildSort([
			'action' => fn($direction) => [
				'action' => ['name' => $direction]
			]
		]));

		$timesheetGlobalTime = $cTimesheet->sum('timeTotal');

		$h = '<div class="'.($monthly ? 'util-overflow-lg' : 'util-overflow-xs').' stick-xs">';

			$h .= '<table class="tr-even analyze-values '.($monthly ? 'analyze-month-table-5' : '').'">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.$search->linkSort('action', s("Intervention")).'</th>';
						if($monthly === FALSE) {
							$h .= '<th class="hide-xs-down">'.s("Catégorie").'</th>';
						}
						$h .= '<th class="text-end">'.$search->linkSort('time', s("Temps passé"), SORT_DESC).'</th>';
						$h .= '<th>';
							$h .= new \main\ChartUi()->getMonthlyLink($monthly ? 'time' : NULL, 'time');
						$h .= '</th>';

						if($monthly) {
							for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {
								$h .= '<th class="text-center">'.\util\DateUi::getMonthName($monthlyMonth, type: 'short').'</th>';
							}
						}

						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					foreach($cTimesheet as $eTimesheet) {

						$h .= '<tr>';
							$h .= '<td>';
								$h .= encode($eTimesheet['action']['name']);
								if($monthly) {
									$h .= '<div class="color-muted" style="line-height: 1"> ';
										$h .= '<small>'.encode($eTimesheet['category']['name']).'</small>';
									$h .= '</div>';
								}
							$h .= '</td>';
							if($monthly === FALSE) {
								$h .= '<td class="hide-xs-down color-muted">';
									$h .= encode($eTimesheet['category']['name']);
								$h .= '</td>';
							}
							$h .= '<td class="text-end">';
								$h .= TaskUi::convertTime($eTimesheet['timeTotal']);
							$h .= '</td>';
							$h .= '<td class="util-annotation">';
								$h .= \util\TextUi::pc($eTimesheet['timeTotal'] / $timesheetGlobalTime * 100, 0);
							$h .= '</td>';

							if($monthly) {

								for($monthlyMonth = 1; $monthlyMonth <= 12; $monthlyMonth++) {

									$eTimesheetMonthly = $cccTimesheetMonthly[$eTimesheet['action']['id']][$eTimesheet['category']['id']][$monthlyMonth] ?? new Timesheet();

									$h .= '<td class="text-end analyze-month-value">';

										if($eTimesheetMonthly->notEmpty()) {
											$h .= TaskUi::convertTime(max(1, $eTimesheetMonthly['timeTotal']), showMinutes: FALSE);
										}

									$h .= '</td>';

								}

							}

							$h .= '<td class="text-end">';
								if($eStructure->canPersonalData()) {
									$h .= '<a href="/series/analyze:tasks?id='.$eStructure['id'].'&status='.Task::DONE.'&action='.$eTimesheet['action']['id'].'&year='.$year.($month ? '&month='.$month : '').($week ? '&week='.$week : '').'&category='.$eTimesheet['category']['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('calendar3').'</a> ';
								}
								if($month === NULL and $week === NULL) {
									$h .= '<a href="/work/action:analyzeTime?action='.$eTimesheet['action']['id'].'&category='.$eTimesheet['category']['id'].'&year='.$year.'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('search').'</a>';
								}
							$h .= '</td>';

						$h .= '</tr>';

					}

					$h .= '<tr class="analyze-total">';

						$h .= '<td>';
							$h .= s("Total");
						$h .= '</td>';
						$h .= '<td class="hide-xs-down"></td>';
						$h .= '<td class="text-end">';
							$h .= TaskUi::convertTime($timesheetGlobalTime);
						$h .= '</td>';
						$h .= '<td colspan="2">';
						$h .= '</td>';

					$h .= '</tr>';

				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getTasks(\structure\Structure $eStructure, \Collection $cTask, \Collection $cUser, float $pages, int $page, \Search $search): string {

		$uiTask = new TaskUi();

		$h = '';

		$filters = [];

		if($search->get('action')) {
			$filters[] = s("Intervention : {value}", '<b>'.encode($search->get('action')['name']).'</b>');
		}

		if($search->get('category')) {
			$filters[] = s("Catégorie : {value}", '<b>'.encode($search->get('category')['name']).'</b>');
		}

		if($search->get('status')) {
			$filters[] = s("Statut : {value}", '<b>'.\series\TaskUi::p('status')->values[$search->get('status')].'</b>');
		}

		if($search->get('series')) {
			$filters[] = s("Série : {value}", '<b>'.encode($search->get('series')->empty() ? s("Hors série") : $search->get('series')['name']).'</b>');
		}

		if($search->get('plant')) {
			$filters[] = s("Espèce : {value}", '<b>'.encode($search->get('plant')['name']).'</b>');
		}

		if($search->get('year')) {
			$filters[] = s("Année : {value}", '<b>'.$search->get('year').'</b>');
		}

		if($search->get('month')) {
			$filters[] = s("Mois : {value}", '<b>'.mb_ucfirst(\util\DateUi::getMonthName($search->get('month'))).'</b>');
		}

		if($search->get('week')) {
			$filters[] = s("Semaine : {value}", '<b>'.week_number($search->get('week')).'</b>');
		}

		if($search->get('user')->notEmpty()) {
			$filters[] = s("Utilisateur : {value}", '<b>'.$search->get('user')->getName().'</b>');
		}

		if($filters !== []) {
			$h .= '<div class="util-block" style="margin-bottom: 1rem">'.implode(' / ', $filters).'</div>';
		}

		if($cTask->empty()) {
			$h .= '<div class="util-empty">'.s("Aucune intervention ne correspond à ce critère.").'</div>';
		} else {

			$h .= '<div class="analyze-tasks-wrapper">';

				$h .= \util\TextUi::pagination($page, $pages);

				$h .= '<table class="tr-even analyze-tasks">';

					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Date").'</th>';
							$h .= '<th>'.s("Détail").'</th>';
							$h .= '<th class="analyze-tasks-users">'.s("Utilisateurs").'</th>';
							if($eStructure->conf()['time']) {
								$h .= '<th>'.s("Temps passé").'</th>';
							}
							$h .= '<th></th>';
						$h .= '</tr>';
					$h .= '</thead>';

					$h .= '<tbody>';
						foreach($cTask as $eTask) {

							foreach($cUser as $eUser) {
								$eUser['time'] = $eTask['times'][$eUser['id']] ?? NULL;
							}

							$h .= '<tr id="analyze-task-'.$eTask['id'].'">';
								$h .= '<td>';

									switch($eTask['status']) {

										case Task::TODO :
											if($eTask['plannedWeek'] !== NULL) {
												$h .= s("Planifié s{week}, {year}", ['week' => week_number($eTask['plannedWeek']), 'year' => week_year($eTask['plannedWeek'])]);
											} else {
												$h .= s("À planifier");
											}
											break;

										case Task::DONE :
											if($eTask['timesheetStart'] === NULL) {
												$h .= \util\DateUi::numeric($eTask['updatedAt'] ?? $eTask['createdAt']);
											} else {
												if($eTask['timesheetStart'] === $eTask['timesheetStop']) {
													$h .= \util\DateUi::numeric($eTask['timesheetStart']);
												} else {
													$h .= \util\DateUi::numeric($eTask['timesheetStart']).' '.\Asset::icon('arrow-right').'&nbsp;'.\util\DateUi::numeric($eTask['timesheetStop']);
												}
											}
											break;

									}

								$h .= '</td>';
								$h .= '<td>';
									$h .= $uiTask->getTaskPlace($eTask);
									$h .= $uiTask->getTaskContent($eTask);
									$h .= $uiTask->getTaskDescription($eTask);
								$h .= '</td>';
								$h .= '<td class="analyze-tasks-users">';
									$h .= $uiTask->getUsersReadOnly($eTask, $cUser);
								$h .= '</td>';
								if($eStructure->conf()['time']) {
									$h .= '<td>';
										$h .= $uiTask->getTime($eTask);
									$h .= '</td>';
								}
								$h .= '<td>';
									if($eTask->canWrite()) {
										$h .= '<a href="/series/task:update?id='.$eTask['id'].'" data-ajax-origin="analyze" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
									}
								$h .= '</td>';

							$h .= '</tr>';

						}
					$h .= '</tbody>';
				$h .= '</table>';

				$h .= \util\TextUi::pagination($page, $pages);

			$h .= '</div>';


		}

		return $h;

	}

}
?>
