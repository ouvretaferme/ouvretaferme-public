<?php
namespace main;

class ChartUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::js('main', 'chart.js');

	}

	public function buildPie(string $title, \Collection $c, string $property, \Closure $label, ?\Closure $color = NULL): string {

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		$global = (float)$c->sum($property);

		if($global === 0.0) {
			return '';
		}

		$missing = 100;

		$values = [];
		$labels = [];

		if($color !== NULL) {
			$colors = [];
		} else {
			$colors = NULL;
		}

		foreach($c as $e) {

			$part = round($e[$property] / $global * 100);

			$values[] = $part;
			$labels[] = $label->call($this, $e);

			if($color !== NULL) {
				$colors[] = $color->call($this, $e);
			}

			$missing -= $part;

			if(count($values) === 6 and $c->count() > 7) {
				break;
			}

		}

		if($c->count() > 7) {
			$values[] = $missing;
			$labels[] = s("Autres");
			if($color !== NULL) {
				$colors[] = '#AAA';
			}
		}

		$h = '<div class="analyze-pie">';
			$h .= '<h5>'.$title.'</h5>';
			$h .= '<div class="analyze-pie-canvas"><canvas '.attr('onrender', 'UtilChart.createPie(this, '.json_encode($values).', '.json_encode($labels).', '.($colors ? json_encode($colors) : 'undefined').')').'</canvas></div>';
		$h .= '</div>';

		return $h;

	}

	public function buildMonthly(\Collection $cc, string $property, \Closure $legend, ?\Closure $color = NULL): string {

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		$values = [];
		$legends = [];

		if($color !== NULL) {
			$colors = [];
		} else {
			$colors = NULL;
		}

		foreach($cc as $c) {

			$legends[] = $legend->call($this, $c->first());

			$elementValues = [];

			for($i = 1; $i <= 12; $i++) {
				$elementValues[] = $c->offsetExists($i) ? $c[$i][$property] : 0;
			}

			$values[] = $elementValues;

			if($color !== NULL) {
				$colors[] = $color->call($this, $c->first());
			}

			if(count($values) === 5 and $c->count() > 6) {
				break;
			}

		}

		$labels = [];

		for($month = 1; $month <= 12; $month++) {
			$labels[] = \util\DateUi::getMonthName($month, type: 'short');
		}

		$h = '<div class="analyze-pie">';
			$h .= '<div class="analyze-pie-canvas"><canvas '.attr('onrender', 'UtilChart.createMonthly(this, '.json_encode($values).', '.json_encode($labels).', '.json_encode($legends).', '.($colors ? json_encode($colors) : 'undefined').', "h")').'</canvas></div>';
		$h .= '</div>';

		return $h;

	}

	public function getMonthlyLink(?string $monthly, string $value): string {

		// Une période d'un mois ou d'une semaine n'a pas de détail mois par mois à montrer
		if(
			get_exists('week') or
			get_exists('month')
		) {
			return '';
		}

		if($monthly === $value) {
			$request = \util\HttpUi::removeArgument(LIME_REQUEST, 'monthly');
			return '<a href="'.$request.'" class="btn btn-only-icon btn-xs btn-secondary" title="'.s("Revenir sur la vue annuelle").'">'.\Asset::icon('arrows-angle-contract').'</a>';
		} else {
			$request = \util\HttpUi::setArgument(LIME_REQUEST, 'monthly', $value);
			return '<a href="'.$request.'" class="btn btn-only-icon btn-xs btn-secondary" title="'.s("Voir le détail mois par mois").'">'.\Asset::icon('arrows-angle-expand').'</a>';
		}

	}

}
?>
