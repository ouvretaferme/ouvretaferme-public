<?php
namespace map;

class Draw extends DrawElement {


	public function canRead(): bool {
		return TRUE;
	}

	public static function properties(\PropertyList $l): void {

		$l->property('season')
			->replaceCheck(function(int $season): bool {
				$this->expects(['structure']);
				return $this['structure']->conf(Configuration::class)->checkSeason($season);
			})
			// La console ne montre un jardin que dans ses saisons d'exploitation et celles de sa parcelle : un trait écrit hors d'elles n'aurait pas d'écran où se lire ni se reprendre
			->addAfterCheck('exploited', function(int $season): bool {

				$this->expects([
					'plot' => ['seasonFirst', 'seasonLast', 'zone' => ['seasonFirst', 'seasonLast']]
				]);

				$ePlot = $this['plot'];
				$eZone = $ePlot['zone'];

				return (
					($ePlot['seasonFirst'] === NULL or $ePlot['seasonFirst'] <= $season) and
					($ePlot['seasonLast'] === NULL or $ePlot['seasonLast'] >= $season) and
					($eZone['seasonFirst'] === NULL or $eZone['seasonFirst'] <= $season) and
					($eZone['seasonLast'] === NULL or $eZone['seasonLast'] >= $season)
				);

			});

		// Une planche se pose toujours à une place : seule la marque d'une saison effacée, sans planche, n'a ni coin ni angle
		$l->property('corner')
			->addAfterCheck('placed', fn(?array $corner): bool => ($corner !== NULL or $this['bed']->empty()))
			// Le type point accepte deux nombres quelconques : un coin hors de la Terre n'aurait aucune place sur la carte
			->addAfterCheck('bounded', fn(?array $corner): bool => ($corner === NULL or (abs($corner[0]) <= 180 and abs($corner[1]) <= 90)));

		$l->property('angle')
			->addAfterCheck('placed', fn(?float $angle): bool => ($angle !== NULL or $this['bed']->empty()))
			// Une planche se pose dans le contour où le rang se dessine (Plot::canBedLine()). Un coin refusé l'est déjà, et une planche
			// sans longueur ou sans largeur n'a pas de rectangle (Draw::bed.sized)
			->addAfterCheck('inside', function(?float $angle, \PropertyList $l): bool {

				if($angle === NULL or $l->isBuilt('corner') === FALSE or $this['bed']->empty()) {
					return TRUE;
				}

				$this->expects([
					'bed' => ['length', 'width'],
					'plot' => ['zoneFill', 'coordinates', 'zone' => ['coordinates']]
				]);

				$eBed = $this['bed'];

				if($eBed['length'] === NULL or $eBed['width'] === NULL) {
					return TRUE;
				}

				$ePlot = $this['plot'];
				$contour = $ePlot['zoneFill'] ? $ePlot['zone']['coordinates'] : $ePlot['coordinates'];

				// L'angle n'est posé sur l'élément qu'après ses étapes
				$line = DrawLib::getLine(new Draw(['corner' => $this['corner'], 'angle' => $angle]), $eBed);

				return MapLib::isInside($line, $contour);

			});

	}

}
?>