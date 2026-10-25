<?php
namespace work;

class AnalyzeUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::js('main', 'chart.js');

	}

	public function getTime(Action $eAction, Category $eCategory, int $year, \Collection $cTimesheetTarget, \Collection $cTimesheetMonth, \Collection $cTimesheetMonthBefore, \Collection $cTimesheetWeek, \Collection $cTimesheetWeekBefore, \Collection $cTimesheetUser, \Collection $cMethod): \Panel {

		$h = '';

		if($cTimesheetTarget->notEmpty()) {

			$h .= $this->getTimesheet($eAction, $eCategory, $cTimesheetTarget, $year);

			if($cTimesheetMonth->notEmpty()) {

				$h .= '<br/>';
				$h .= '<h3>'.s("Temps de travail mensuel").'</h3>';
				$h .= '<div class="analyze-chart-table">';
					$h .= new \series\AnalyzeUi()->getPeriodMonthTable($cTimesheetMonth, $eCategory['structure']->canPersonalData() ? $cTimesheetUser : new \Collection());
					$h .= new \series\AnalyzeUi()->getPeriodMonthChart($cTimesheetMonth, $year, $cTimesheetMonthBefore, $year - 1);
				$h .= '</div>';

			} else {
				$h .= '<p class="util-empty">';
					$h .= s("Il n'y a aucune intervention en {value}.", $year);
				$h .= '</p>';
			}

			if($cTimesheetWeek->notEmpty()) {

				$h .= '<br/>';
				$h .= '<h3>'.s("Temps de travail hebdomadaire").'</h3>';
				$h .= new \series\AnalyzeUi()->getPeriodWeekChart($cTimesheetWeek, $year, $cTimesheetWeekBefore, $year - 1);
			}

			if($cMethod->notEmpty()) {

				$h .= '<br/>';
				$h .= '<h3>'.s("Temps de travail par méthode").'</h3>';
				$h .= '<div class="analyze-chart-table">';
					$h .= $this->getMethodTable($cMethod, $cTimesheetMonth->sum('timeTotal'));
					$h .= $this->getMethodPie($cMethod);
				$h .= '</div>';

			}

		} else {

			$h .= '<p class="util-empty">';
				if($eAction->empty()) {
					$h .= s("Vous n'avez jamais utilisé cette catégorie.");
				} else {
					$h .= s("Vous n'avez jamais utilisé cette intervention.");
				}
			$h .= '</p>';

		}

		if($eAction->empty()) {
			$title = s("{value} en {year}", ['value' => encode($eCategory['name']), 'year' => $year]);
			$header = '<h2 class="panel-title">'.$title.'</h2>';
		} else {
			$title = s("{value} en {year}", ['value' => encode($eAction['name']), 'year' => $year]);
			$header = '<h2 class="panel-title">'.$title.'</h2>';
			$header .= '<h4 class="panel-subtitle">'.encode($eCategory['name']).'</h4>';
		}

		return new \Panel(
			id: 'panel-action-analyze',
			documentTitle: $title,
			body: $h,
			header: $header,
		);

	}

	public function getMethodPie(\Collection $cMethod): string {

		return new \main\ChartUi()->buildPie(
			s("Répartition par méthode"),
			$cMethod,
			'timeTotal',
			fn($eMethod) => $eMethod->exists() === FALSE ? s("Non définie") : encode($eMethod['name'])
		);

	}

	public function getMethodTable(\Collection $cMethod, float $globalTime): string {

		$h = '<div class="util-overflow-xs stick-xs">';

			$h .= '<table class="tr-even analyze-values">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Méthode").'</th>';
						$h .= '<th class="text-end">'.s("Temps passé").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					foreach($cMethod as $eMethod) {

						$h .= '<tr>';
							$h .= '<td>';
								if($eMethod->exists() === FALSE) {
									$h .= '<i>'.s("Non définie").'</i>';
								} else {
									$h .= encode($eMethod['name']);
								}
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= \series\TaskUi::convertTime($eMethod['timeTotal']);
							$h .= '</td>';
							$h .= '<td>';
								$h .= '<div class="util-annotation">';
									$h .= \util\TextUi::pc($eMethod['timeTotal'] / $globalTime * 100, 0);
								$h .= '</div>';
							$h .= '</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getTimesheet(Action $eAction, Category $eCategory, \Collection $cTimesheetTarget, ?int $year): string {

		// Une seule tuile, déjà affichée, ne mène nulle part
		if($cTimesheetTarget->count() === 1 and $cTimesheetTarget->first()['year'] === $year) {
			return '';
		}

		$h = '<div class="util-figures util-figures-overflow">';

			foreach($cTimesheetTarget as $eTimesheet) {

				$h .= '<a data-ajax="/work/action:analyzeTime?'.($eAction->notEmpty() ? 'action='.$eAction['id'] : '').'&category='.$eCategory['id'].'&year='.$eTimesheet['year'].'" data-ajax-method="get" class="util-figure'.($eTimesheet['year'] === $year ? ' util-figure-selected' : '').'">';
					$h .= '<div class="util-figure-label util-figure-label-top">'.$eTimesheet['year'].'</div>';
					$h .= '<div class="util-figure-value">'.\series\TaskUi::convertTime($eTimesheet['timeTotal']).'</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getYear(
		array $years,
		int $selectedYear, ?int $selectedMonth, ?string $selectedWeek,
		\Closure $url
	): string {

		if(count($years) === 1) {
			return s("Année {value}", $selectedYear).$this->getPeriod($selectedYear, $selectedMonth, $selectedWeek, $url);
		}

		$h = '<a class="util-chip" data-dropdown="bottom-start">'.s("Année {value}", $selectedYear).'</a>';
		$h .= $this->getYearsList($years, $selectedYear, $selectedMonth, $url);
		$h .= $this->getPeriod($selectedYear, $selectedMonth, $selectedWeek, $url);

		return $h;

	}

	protected function getYearsList(array $years, int $selectedYear, ?int $selectedMonth, \Closure $url): string {

		$h = '<div class="dropdown-list bg-primary">';

			$h .= '<div class="dropdown-title">'.s("Changer l'année").'</div>';

			foreach($years as $year) {

				$h .= '<a href="'.$url($year).''.($selectedMonth ? '?month='.$selectedMonth : '').'" class="dropdown-item dropdown-item-full '.(($selectedYear === $year and $selectedMonth === NULL) ? 'selected' : '').'">'.s("Année {value}", $year).'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getPeriod(int $selectedYear, ?int $selectedMonth, ?string $selectedWeek, \Closure $url): string {

		$h = '';

		if($selectedMonth !== NULL) {
			$h .= ' '.\Asset::icon('chevron-right').' ';
			$h .= '<div class="btn-group">';
				$h .= '<a data-dropdown="bottom-start" data-dropdown-hover="true" class="btn btn-sm btn-outline-primary dropdown-toggle">'.mb_ucfirst(\util\DateUi::getMonthName($selectedMonth)).'</a>';
				$h .= '<div class="dropdown-list">';
					foreach(\util\DateUi::months() as $position => $month) {
						$h .= '<a href="'.$url($selectedYear).'?month='.$position.'" class="dropdown-item">'.mb_ucfirst($month).'</a>';
					}
				$h .= '</div>';
				$h .= '<a href="'.$url($selectedYear,).'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('x-circle').'</a>';
			$h .= '</div>';
		}

		if($selectedWeek !== NULL) {
			$h .= ' '.\Asset::icon('chevron-right').' ';
			$h .= s("Semaine {value}", week_number($selectedWeek));
			$h .= ' <a href="'.$url($selectedYear).'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('x-circle').'</a>';
		}

		return $h;

	}

}
?>
