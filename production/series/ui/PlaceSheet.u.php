<?php
namespace series;

/**
 * La fiche d'une série sur le plan d'assolement, ouverte par un clic sur un de ses blocs en lecture : celle du plan 3D
 * (`\map\SceneUi::getSheet()`), dont le corps est rendu par le même script (`SeriesSheet`, seriesSheet.js) sur la même
 * donnée (`SeriesLib::getSheet()`).
 *
 * Seule une série en place sur l'année en cours porte des gestes, sous sa tête : « Récolter » et « Intervention ».
 */
class PlaceSheetUi {

	public function __construct() {

		\Asset::css('series', 'place.css');
		\Asset::js('series', 'place.js');
		\Asset::js('scene', 'loader.js');

	}

	/** Le support vide, posé sous le plan, que le clic sur un bloc remplit. */
	public function getEmpty(\structure\Structure $eStructure): string {

		return '<div id="place-sheet" class="hide" data-structure="'.$eStructure['id'].'"></div>';

	}

	/**
	 * `$sheet` est la donnée de la fiche (`SeriesLib::getSheet()`), que le script rend au jour `$date`, et `$cCultivation`
	 * les cultures que « Récolter » propose à une série en place (`$inPlace`).
	 */
	public function get(\structure\Structure $eStructure, Series $eSeries, string $date, array $sheet, ?string $color, bool $inPlace, \Collection $cCultivation, bool $canHarvest, bool $canTask): string {

		$uiSeriesSheet = new SeriesSheetUi();

		$h = '<div id="place-sheet" class="util-card series-sheet" data-structure="'.$eStructure['id'].'" data-series="'.$eSeries['id'].'">';

			$h .= '<div class="series-sheet-head">';
				$h .= '<div>';
					$h .= '<div class="util-label">'.s("Série {value}", $eSeries['season']).'</div>';
					$h .= '<h3>'.$uiSeriesSheet->getTitle($eSeries, $color).'</h3>';
				$h .= '</div>';
				$h .= '<button type="button" class="btn btn-only-icon" data-place-sheet-close="true">'.\Asset::icon('x-lg').'</button>';
			$h .= '</div>';
			$h .= '<div class="util-mores"><a href="'.encode(SeriesUi::url($eSeries)).'" class="util-more util-more-lg">'.s("Voir la série").'</a></div>';

			$gestures = '';

			if($inPlace and $canHarvest) {
				$gestures .= $this->getHarvest($eStructure, $cCultivation);
			}

			if($inPlace and $canTask) {
				$gestures .= '<a href="'.encode(\planning\TaskCreateUi::url($eStructure, 'taskWeek', \Clock::week(), eSeries: $eSeries)).'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Intervention").'</a>';
			}

			if($gestures !== '') {
				$h .= '<div class="place-sheet-actions">'.$gestures.'</div>';
			}

			$h .= '<div';
				$h .= ' data-scene-directory="'.encode(\Asset::directory('scene')).'"';
				$h .= ' data-texts="'.encode(json_encode(SeriesSheetUi::getTexts(), JSON_UNESCAPED_UNICODE)).'"';
				$h .= ' data-sheet="'.encode(json_encode($sheet, JSON_UNESCAPED_UNICODE)).'"';
				$h .= ' data-date="'.$date.'"';
				$h .= ' onrender="SeriesPlaceSheet.render(this)"';
			$h .= '></div>';

		$h .= '</div>';

		return $h;

	}

	/** Une série à plusieurs cultures choisit la sienne dans un menu : le panneau de récolte en prend une seule. */
	private function getHarvest(\structure\Structure $eStructure, \Collection $cCultivation): string {

		$url = \planning\HarvestWeekUi::urlCreate($eStructure, \Clock::week(), origin: 'assolement');
		$label = \Asset::icon('basket').' '.s("Récolter");

		if($cCultivation->count() === 1) {
			return '<a href="'.encode($url.'&cultivation='.$cCultivation->first()['id']).'" class="btn btn-primary">'.$label.'</a>';
		}

		$h = '<a data-dropdown="bottom-start" class="btn btn-primary dropdown-toggle">'.$label.'</a>';
		$h .= '<div class="dropdown-list">';
			foreach($cCultivation as $eCultivation) {
				$h .= '<a href="'.encode($url.'&cultivation='.$eCultivation['id']).'" class="dropdown-item">'.encode($eCultivation['plant']['name']).'</a>';
			}
		$h .= '</div>';

		return $h;

	}

}
?>
