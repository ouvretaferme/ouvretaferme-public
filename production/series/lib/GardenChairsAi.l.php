<?php
namespace series;

/**
 * Les jardins identiques : jardins d'une structure qui ont le même nombre de planches et les mêmes
 * dimensions, et dont on déplace les séries de l'un à l'autre, planche de rang k sur planche de
 * rang k.
 *
 * Le déplacement ne porte que sur la saison affichée. Une série reste où elle est si elle est
 * verrouillée (vivace, clôturée, d'une autre saison, cf. PlaceLib::isLocked()) ou si une de ses
 * planches de la saison est hors des jardins du groupe : la couper en deux ferait une série posée
 * à deux endroits que personne n'a choisis.
 */
class GardenChairsAiLib {

	/**
	 * Les groupes de la structure dans la saison, avec les séries de chaque jardin (putGroup()), leurs
	 * dimensions et s'ils s'étendent sur plusieurs parcelles, où un nom de jardin ne suffit plus.
	 * Les saisons de la rotation sont chargées avec la saison : c'est sur elles que se comptent les
	 * planches à surveiller, comme dans Rotations.
	 */
	public static function getByStructure(\structure\Structure $eStructure, int $season): array {

		$cZone = \map\ZoneLib::getByStructure($eStructure, season: $season);

		\map\PlotLib::putFromZoneWithSeries($eStructure, $cZone, $season, $eStructure->conf(\map\Configuration::class)->getRotationSeasons($season), onlySeries: TRUE);

		$groups = [];

		foreach(self::getGroups($cZone) as $cPlot) {

			self::putGroup($cPlot, $season, $eStructure->conf(\map\Configuration::class)['rotationYears']);

			$groups[] = [
				'cPlot' => $cPlot,
				'dimensions' => self::getDimensions($cPlot),
				'severalZones' => count(array_unique($cPlot->getColumnCollection('zone')->getIds())) > 1,
			];

		}

		return $groups;

	}

	public static function getGroupByPlot(array $groups, int $plotId): \Collection {

		foreach($groups as $group) {

			if($group['cPlot']->offsetExists($plotId)) {
				return $group['cPlot'];
			}

		}

		return new \Collection();

	}

	/**
	 * Les groupes de la structure, dans l'ordre des parcelles puis des jardins du plan.
	 * Chaque jardin reçoit `cBedGarden`, ses planches cultivables dans l'ordre naturel des noms
	 * (celui du plan, qui fixe le rang), et `zone`, sa parcelle complète.
	 *
	 * @return array<string, \Collection> les jardins de chaque groupe, indexés par id
	 */
	public static function getGroups(\Collection $cZone): array {

		$groups = [];

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				if($ePlot['zoneFill']) {
					continue;
				}

				$cBed = $ePlot['cBed']->find(fn(\map\Bed $eBed) => $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE);

				// Une planche sans dimension ne se compare à aucune autre : le jardin entier sort
				// de la détection, plutôt que de rapprocher des jardins qu'on ne sait pas mesurer.
				if(
					$cBed->count() < 2 or
					$cBed->find(fn(\map\Bed $eBed) => $eBed['length'] === NULL or $eBed['width'] === NULL)->notEmpty()
				) {
					continue;
				}

				// Les dimensions restent dans l'ordre des planches, sans tri : le déplacement envoie la
				// k-ième planche sur la k-ième, et deux jardins aux mêmes tailles rangées autrement
				// feraient passer une série sur une planche d'une autre taille.
				$dimensions = [];

				foreach($cBed as $eBed) {
					$dimensions[] = $eBed['length'].'x'.$eBed['width'];
				}

				$ePlot['cBedGarden'] = $cBed;
				$ePlot['zone'] = $eZone;

				$key = $ePlot['mode'].'|'.implode(',', $dimensions);

				$groups[$key] ??= new \Collection();
				$groups[$key][$ePlot['id']] = $ePlot;

			}

		}

		return array_filter($groups, fn(\Collection $cPlot) => $cPlot->count() >= 2);

	}

	/**
	 * Les dimensions d'un groupe, lues sur son premier jardin puisque tous les jardins du groupe ont
	 * les mêmes : ses longueurs, le nombre de planches par largeur de la plus fréquente à la plus
	 * rare, et la surface cultivée d'un jardin en m².
	 *
	 * @return array{lengths: int[], widths: array<int, int>, surface: float}
	 */
	protected static function getDimensions(\Collection $cPlot): array {

		$lengths = [];
		$widths = [];
		$surface = 0.0;

		foreach($cPlot->first()['cBedGarden'] as $eBed) {

			$lengths[$eBed['length']] = $eBed['length'];

			$widths[$eBed['width']] ??= 0;
			$widths[$eBed['width']]++;

			$surface += $eBed['length'] * $eBed['width'] / 100;

		}

		ksort($lengths);
		arsort($widths);

		return [
			'lengths' => array_values($lengths),
			'widths' => $widths,
			'surface' => $surface,
		];

	}

	/**
	 * Les séries de chaque jardin du groupe dans la saison, et ce que coûterait leur déplacement.
	 * Chaque planche reçoit `cPlant`, les espèces de la saison dans l'ordre du plan. Chaque jardin
	 * reçoit :
	 * - `seriesMoving`, les séries qui le suivent s'il bouge. Une série posée aussi hors de ce jardin
	 *   n'y déplace que ses planches, les autres restent où elles sont ;
	 * - `seriesPerennial`, les séries de plantes pérennes de la saison, qui restent en place quand
	 *   le jardin bouge, avec leur nom ;
	 * - `watch`, pour chaque jardin du groupe, les planches de ce jardin où une famille des séries
	 *   qui partent d'ici revient trop tôt dans la saison ;
	 * - `returns`, pour chaque jardin du groupe, ce qui fait surveiller ces planches : `families`,
	 *   chaque famille qui revient trop tôt avec son écart le plus court et son nombre de planches,
	 *   et `plants`, rang par rang, les espèces qui partent d'ici et dont la famille revient, avec
	 *   leur écart. Chaque planche comptée dans `watch` porte donc au moins une espèce de `plants`.
	 */
	public static function putGroup(\Collection $cPlot, int $season, int $years): void {

		// Pour chaque planche, les familles qu'elle garde quoi qu'il arrive et celles qui partent
		// avec son jardin : la planche d'arrivée réunit les premières et celles de la planche de
		// même rang du jardin qui arrive.
		$familiesStaying = [];
		$familiesMoving = [];

		// Les espèces qui partent, rang par rang et par famille : ce sont elles que la bande désigne
		// quand leur famille revient trop tôt sur la planche d'arrivée.
		$plantsMoving = [];
		$familiesName = [];

		foreach($cPlot as $ePlot) {

			$seriesMoving = [];
			$seriesPerennial = [];

			foreach($ePlot['cBedGarden'] as $eBed) {

				$cPlaceMoving = new \Collection();
				$cPlaceStaying = new \Collection();
				$cPlant = new \Collection();

				foreach($eBed['cPlace'] as $ePlace) {

					$eSeries = $ePlace['series'];

					if($ePlace['season'] === $season) {

						foreach($eSeries['cCultivation'] as $eCultivation) {
							$cPlant[$eCultivation['plant']['id']] ??= $eCultivation['plant'];
						}

						if($eSeries['cycle'] === Series::PERENNIAL) {
							$seriesPerennial[$eSeries['id']] = $eSeries['name'];
						}

					}

					if(
						$ePlace['season'] !== $season or
						PlaceLib::isLocked($eSeries, $season)
					) {
						$cPlaceStaying[] = $ePlace;
					} else {
						$cPlaceMoving[] = $ePlace;
						$seriesMoving[$eSeries['id']] = TRUE;
					}

				}

				$eBed['cPlant'] = $cPlant;

				$plants = [];

				foreach($cPlaceMoving as $ePlace) {

					foreach($ePlace['series']['cCultivation'] as $eCultivation) {

						$eFamily = $eCultivation['plant']['family'];

						if(
							$eCultivation['season'] === $season and
							$eFamily->notEmpty()
						) {
							$plants[$eFamily['id']][$eCultivation['plant']['id']] = TRUE;
							$familiesName[$eFamily['id']] = $eFamily['name'];
						}

					}

				}

				$plantsMoving[$ePlot['id']][] = $plants;

				$familiesStaying[$ePlot['id']][] = \map\BedLib::getFamiliesBySeason($cPlaceStaying);
				$familiesMoving[$ePlot['id']][] = \map\BedLib::getFamiliesBySeason($cPlaceMoving);

			}

			$ePlot['seriesMoving'] = array_keys($seriesMoving);
			$ePlot['seriesPerennial'] = $seriesPerennial;

		}

		// Deux foreach imbriqués sur la même Collection partagent son curseur : la boucle intérieure
		// parcourt les ids.
		foreach($cPlot as $ePlotFrom) {

			$watch = [];
			$returns = [];

			foreach(array_keys($familiesStaying) as $plotTo) {

				$watch[$plotTo] = 0;
				$returns[$plotTo] = ['families' => [], 'plants' => []];

				foreach($familiesStaying[$plotTo] as $rank => $families) {

					$families = array_replace_recursive($families, $familiesMoving[$ePlotFrom['id']][$rank]);

					$returnsBed = \map\BedLib::getReturnsFromFamilies($families, $season, $years);

					// Seules comptent les familles des séries qui partent : un retour que porte déjà la
					// planche d'arrivée, par les séries qui y restent, existe que le jardin bouge ou non.
					$plants = $plantsMoving[$ePlotFrom['id']][$rank];
					$gaps = array_intersect_key($returnsBed[$season]['families'] ?? [], $plants);

					if($gaps === []) {
						continue;
					}

					$watch[$plotTo]++;

					foreach($gaps as $familyId => $gap) {

						$returns[$plotTo]['families'][$familyId] ??= ['name' => $familiesName[$familyId], 'gap' => $gap, 'beds' => 0];
						$returns[$plotTo]['families'][$familyId]['gap'] = min($returns[$plotTo]['families'][$familyId]['gap'], $gap);
						$returns[$plotTo]['families'][$familyId]['beds']++;

						foreach(array_keys($plants[$familyId]) as $plantId) {
							$returns[$plotTo]['plants'][$rank][$plantId] = $gap;
						}

					}

				}

			}

			$ePlotFrom['watch'] = $watch;
			$ePlotFrom['returns'] = $returns;

		}

	}

	/**
	 * Déplace les séries des jardins du groupe selon `$destinations` (jardin de départ => jardin
	 * d'arrivée), qui doit nommer chaque jardin du groupe une fois de chaque côté.
	 *
	 * @return array<int, int> les jardins qui ont bougé, indexés par id
	 */
	public static function move(\structure\Structure $eStructure, \Collection $cPlot, array $destinations, int $season): array {

		if(self::isPermutation($cPlot, $destinations) === FALSE) {
			Place::fail('plotsPermutation');
			return [];
		}

		$moves = array_filter($destinations, fn($to, $from) => $to !== $from, ARRAY_FILTER_USE_BOTH);

		if($moves === []) {
			return [];
		}

		// Le rang d'une planche dans son jardin donne celle qui la reçoit dans le jardin d'arrivée.
		$bedsTo = [];

		foreach($moves as $plotFrom => $plotTo) {

			$bedsFrom = $cPlot[$plotFrom]['cBedGarden']->getIds();
			$bedsDestination = $cPlot[$plotTo]['cBedGarden']->getIds();

			foreach($bedsFrom as $rank => $bedId) {
				$bedsTo[$bedId] = $bedsDestination[$rank];
			}

		}

		$series = [];

		foreach($moves as $plotFrom => $plotTo) {
			foreach($cPlot[$plotFrom]['seriesMoving'] as $seriesId) {
				$series[$seriesId] = TRUE;
			}
		}

		$cSeries = Series::model()
			->select(Series::getSelection())
			->whereId('IN', array_keys($series))
			->whereStructure($eStructure)
			->getCollection();

		$cPlace = Place::model()
			->select(['series', 'bed'])
			->whereSeries('IN', $cSeries)
			->whereSeason($season)
			->whereBed('IN', array_keys($bedsTo))
			->getCollection();

		$beds = [];

		foreach($cPlace as $ePlace) {
			$beds[$ePlace['series']['id']][] = $ePlace['bed']['id'];
		}

		Place::model()->beginTransaction();

		foreach($cSeries as $eSeries) {

			$from = $beds[$eSeries['id']] ?? [];

			if($from === []) {
				continue;
			}

			PlaceLib::moveBeds($eSeries, $from, array_map(fn($bedId) => $bedsTo[$bedId], $from));

		}

		Place::model()->commit();

		return $moves;

	}

	protected static function isPermutation(\Collection $cPlot, array $destinations): bool {

		$plots = $cPlot->getIds();
		sort($plots);

		$from = array_keys($destinations);
		sort($from);

		$to = array_values($destinations);
		sort($to);

		return ($from === $plots and $to === $plots);

	}

}
?>
