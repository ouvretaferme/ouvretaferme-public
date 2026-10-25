<?php
namespace series;

/**
 * Les séries assolées mais sans dates, dans la vue chronologie.
 *
 * Elles occupent une planche sans pouvoir se poser sur l'axe du temps : la
 * chronologie les rend en lignes fines sous la barre de la planche, comme le
 * jardin les rend en pied de colonne. Le critère « invisible » n'est pas le
 * même dans les deux vues — ici il suffit qu'aucune date de démarrage ne soit
 * positionnable — donc il vit avec le rendu qui s'en sert.
 */
class PlaceNoDateAiUi extends PlaceUi {

	/**
	 * Les séries qu'une chronologie laisse de côté sur cette planche :
	 * getTimeline() écarte tout emplacement sans positionStart, et positionPlace()
	 * est le seul à savoir qu'une pérenne au-delà de sa première saison s'en passe.
	 */
	public function getByBed(\map\Bed $eBed, int $season): array {

		$cSeries = [];

		foreach($eBed['cPlace'] as $ePlace) {

			$eSeries = $ePlace['series'];

			if($eSeries->empty() or $eSeries['season'] !== $season) {
				continue;
			}

			$ePlaceTest = new Place([
				'missing' => FALSE,
				'series' => $eSeries,
				'task' => new Task(),
			]);

			$this->positionPlace($ePlaceTest, $season, 0, 100);

			if($ePlaceTest['positionStart'] === NULL) {
				$cSeries[] = $eSeries;
			}

		}

		return $cSeries;

	}

	/**
	 * Le bloc, plafonné à la moitié de la hauteur disponible et défilant en
	 * interne : une surface libre porte jusqu'à 198 séries sans dates.
	 */
	public function getBlock(array $cSeries, \map\Bed $eBed): string {

		if($cSeries === []) {
			return '';
		}

		$h = '<div class="place-nodate-block place-nodate-block-timeline">';

			if(count($cSeries) > 1) {
				$h .= '<span class="place-nodate-head">'.s("Sans dates · {value}", count($cSeries)).'</span>';
			}

			$h .= '<div class="place-nodate-list">';

				foreach($cSeries as $eSeries) {
					$h .= '<a class="place-nodate-line" data-series="'.$eSeries['id'].'" data-bed="'.$eBed['id'].'" title="'.encode($eSeries['name']).'">'.encode($eSeries['name']).'</a>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

}
?>
