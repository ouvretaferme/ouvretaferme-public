<?php
namespace map;

class MapLib {

	// Le rayon moyen de la Terre en mètres, celui de turf (`earthRadius` de `@turf/helpers`), que getArea() doit reprendre pour
	// donner la surface que le navigateur affiche
	const float EARTH_RADIUS = 6371008.8;

	/**
	 * L'origine d'un panneau, qui choisit la réponse de son enregistrement. La vue Carte ouvre ses panneaux par-dessus elle, en
	 * ajax : un panneau rechargé en page entière garde `origin=map` dans son adresse sans avoir de carte où rendre cette réponse.
	 */
	public static function loadOrigin(\stdClass $data): void {
		$data->origin = (GET('origin') === PlanUi::VIEW_MAP and \Route::getRequestedWith() === 'ajax') ? PlanUi::VIEW_MAP : NULL;
	}

	public static function load(\stdClass $data, \structure\Structure $eStructure, bool $redirectOnEmpty = TRUE, bool $validate = TRUE): void {

		if($validate) {
			$eStructure->validate('canProduction');
		}

		$data->eStructure = $eStructure;

		$data->season = \structure\MemberLib::getDynamicSeason($eStructure, GET('season', 'int'));
		SeasonLib::setOnline($data->season);

		$data->cZone = ZoneLib::getByStructure($eStructure, season: $data->season);

		if($redirectOnEmpty and $data->cZone->empty()) {
			throw new \RedirectAction(OnboardingUi::url($eStructure, $data->season));
		}

		PlotLib::putFromZone($data->cZone, withBeds: TRUE, withDraw: TRUE, season: $data->season);

		self::putDrawingState($data->cZone);
		self::putPlotDefault($data->cZone);

		$data->mapCenter = self::getMapCenter($eStructure, $data->cZone);

	}

	/**
	 * Pose sur les parcelles chargées avec leurs tracés ce qui leur reste à tracer (`remaining`, un par jardin ou
	 * abri à reprendre, que chacun d'eux dit par `isRemaining`), et le jardin de l'étape en cours (`ePlotNext`,
	 * vide quand l'étape est la parcelle elle-même ou qu'il ne reste rien).
	 *
	 * Une forme passe avant des planches : le premier jardin ou abri sans forme, sinon le premier à qui il
	 * reste une planche, dans l'ordre de la liste. Un jardin sans forme compte pour un, ses planches ne
	 * pouvant pas être tracées avant lui.
	 *
	 * Le libellé de ce qui reste se lit sur trois comptes : les jardins et abris sans forme de la parcelle
	 * (`plotsUndrawn`, par mode), ses planches sans tracé dans une forme qui peut les recevoir (`bedsUndrawn`),
	 * et, sur chaque jardin, ses planches sans tracé (`cBedUndrawn`).
	 *
	 * Chaque jardin dit aussi s'il peut recevoir un tracé de planches (`drawable`), par Plot::canBedLine(), qui
	 * lit la forme de la parcelle : le jardin reçoit la parcelle qui le porte, la même pour tous ses jardins.
	 */
	public static function putDrawingState(\Collection $cZone): void {

		foreach($cZone as $eZone) {

			$remaining = ($eZone['coordinates'] === NULL) ? 1 : 0;
			$plotsUndrawn = [Plot::OPEN_FIELD => 0, Plot::GREENHOUSE => 0];
			$bedsUndrawn = 0;

			$ePlotShape = new Plot();
			$ePlotBeds = new Plot();

			foreach($eZone['cPlot'] as $ePlot) {

				$cBedUndrawn = new \Collection();

				foreach($ePlot['cBed'] as $eBed) {

					if($eBed['plotFill'] === FALSE and $eBed['drawn'] === FALSE) {
						$cBedUndrawn[$eBed['id']] = $eBed;
					}

				}

				$ePlot['cBedUndrawn'] = $cBedUndrawn;
				$ePlot['bedsUndrawn'] = $cBedUndrawn->count();
				$ePlot['zone'] = $eZone;
				$ePlot['drawable'] = $ePlot->canBedLine();
				$ePlot['isRemaining'] = FALSE;

				// Les planches posées à même la parcelle n'ont pas de jardin à tracer avant elles
				if($ePlot['zoneFill'] === FALSE and $ePlot['coordinates'] === NULL) {

					$ePlot['isRemaining'] = TRUE;
					$remaining++;
					$plotsUndrawn[$ePlot['mode']]++;

					if($ePlotShape->empty()) {
						$ePlotShape = $ePlot;
					}

				} else if($ePlot['bedsUndrawn'] > 0) {

					$ePlot['isRemaining'] = TRUE;
					$remaining++;
					$bedsUndrawn += $ePlot['bedsUndrawn'];

					if($ePlotBeds->empty()) {
						$ePlotBeds = $ePlot;
					}

				}

			}

			$eZone['remaining'] = $remaining;
			$eZone['plotsUndrawn'] = $plotsUndrawn;
			$eZone['bedsUndrawn'] = $bedsUndrawn;

			if($eZone['coordinates'] === NULL) {
				$eZone['ePlotNext'] = new Plot();
			} else if($ePlotShape->notEmpty()) {
				$eZone['ePlotNext'] = $ePlotShape;
			} else {
				$eZone['ePlotNext'] = $ePlotBeds;
			}

		}

	}

	/**
	 * Pose sur chaque parcelle le numéro du jardin et de l'abri que la vue Carte propose d'y créer (`plotDefault`, par
	 * mode), celui qui suit les jardins ou les abris de la parcelle.
	 */
	public static function putPlotDefault(\Collection $cZone): void {

		foreach($cZone as $eZone) {

			$plots = [Plot::OPEN_FIELD => 0, Plot::GREENHOUSE => 0];

			foreach($eZone['cPlot'] as $ePlot) {
				if($ePlot['zoneFill'] === FALSE) {
					$plots[$ePlot['mode']]++;
				}
			}

			$eZone['plotDefault'] = [
				Plot::OPEN_FIELD => $plots[Plot::OPEN_FIELD] + 1,
				Plot::GREENHOUSE => $plots[Plot::GREENHOUSE] + 1,
			];

		}

	}

	/**
	 * Relit les parcelles d'une structure dans une saison, avec ce qui leur reste à tracer : un niveau du bloc de la
	 * vue Carte se rend avec ceux du dessus, dont les comptes changent avec lui.
	 *
	 * Pose sur `$data` la structure, la saison et les parcelles (`cZone`).
	 */
	public static function loadStructure(\stdClass $data, \structure\Structure $eStructure, int $season): void {

		$data->eStructure = \structure\StructureLib::getById($eStructure['id']);
		$data->season = $season;

		$data->cZone = ZoneLib::getByStructure($data->eStructure, season: $season);
		PlotLib::putFromZone($data->cZone, withBeds: TRUE, withDraw: TRUE, season: $season);
		self::putDrawingState($data->cZone);
		self::putPlotDefault($data->cZone);

	}

	/**
	 * Comme loadStructure(), et pose en plus la parcelle relue parmi les autres (`eZone`), vide quand la saison ne
	 * l'exploite pas.
	 */
	public static function loadZone(\stdClass $data, Zone $eZone, int $season): void {

		self::loadStructure($data, $eZone['structure'], $season);

		$data->eZone = $data->cZone->find(fn($eZoneSeason) => $eZoneSeason['id'] === $eZone['id'], limit: 1, clone: FALSE, default: new Zone());

	}

	/**
	 * Comme loadStructure(), et pose en plus la parcelle et le jardin relus parmi les autres (`eZone`, `ePlot`), vides
	 * quand la saison ne les exploite pas.
	 */
	public static function loadDrawing(\stdClass $data, Plot $ePlot, int $season): void {

		self::loadStructure($data, $ePlot['structure'], $season);

		$data->eZone = $data->cZone->find(fn($eZone) => $eZone['id'] === $ePlot['zone']['id'], limit: 1, clone: FALSE, default: new Zone());
		$data->ePlot = $data->eZone->empty() ?
			new Plot() :
			$data->eZone['cPlot']->find(fn($ePlotZone) => $ePlotZone['id'] === $ePlot['id'], limit: 1, clone: FALSE, default: new Plot());

	}

	/**
	 * Pose sur un jardin dont le troisième niveau s'ouvre ce que ce niveau lit : les planches cochées (`cBedSelected`), que
	 * désigne l'adresse ou que laisse un geste, et aucune sans elles, une case ne se cochant que d'un geste de l'utilisateur.
	 * Celles qui sont nommées hors du jardin sont ignorées sans refus : une adresse se garde et se partage, et le geste repasse
	 * de toute façon par BedLib::buildIds().
	 */
	public static function putDrawing(Plot $ePlot, array $ids = []): void {

		// L'adresse donne des chaînes, un geste des entiers
		$ids = array_map('intval', $ids);

		$ePlot['cBedSelected'] = $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE and in_array($eBed['id'], $ids, TRUE), indexByKey: 'id');

	}

	public static function getMapCenter(\structure\Structure $eStructure, \Collection $cZone): ?array {

		if($eStructure->getSystem('placeLngLat') !== NULL) {
			return $eStructure->getSystem('placeLngLat');
		}

		$eZone = $cZone->find(fn($eZone) => $eZone['coordinates'] !== NULL, limit: 1, clone: FALSE);

		if($eZone->empty()) {
			return NULL;
		}

		return $eZone['coordinates'][0];

	}

	/**
	 * Les parcelles qui ont leur carte à l'impression, après celle de la structure : celles qui sont tracées, et aucune quand
	 * une seule l'est, la carte de la structure étant alors cadrée sur elle.
	 */
	public static function getZonesPrinted(\Collection $cZone): \Collection {

		$cZoneDrawn = $cZone->find(fn($eZone) => $eZone['coordinates'] !== NULL, indexByKey: 'id');

		return ($cZoneDrawn->count() > 1) ? $cZoneDrawn : new \Collection();

	}

	/**
	 * L'emprise des parcelles tracées, en [ouest, sud, est, nord], ou NULL si aucune ne l'est.
	 */
	public static function getBounds(\Collection $cZone): ?array {

		$bounds = NULL;

		foreach($cZone as $eZone) {

			if($eZone['coordinates'] === NULL) {
				continue;
			}

			foreach($eZone['coordinates'] as [$longitude, $latitude]) {

				if($bounds === NULL) {
					$bounds = [$longitude, $latitude, $longitude, $latitude];
				} else {
					$bounds = [min($bounds[0], $longitude), min($bounds[1], $latitude), max($bounds[2], $longitude), max($bounds[3], $latitude)];
				}

			}

		}

		return $bounds;

	}

	/**
	 * Le schéma admet un nombre écrit en chaîne, et la base ne tient un anneau pour fermé que si son
	 * dernier point est identique au premier, type compris (`encodePolygon()`). Sans ce passage en
	 * nombres, `["3", "45"]` face à `[3, 45]` serait refermé une seconde fois à l'écriture.
	 */
	public static function castPoints(array $points): array {

		return array_map(fn(array $point): array => [(float)$point[0], (float)$point[1]], $points);

	}

	/**
	 * Un contour s'enregistre s'il compte trois sommets distincts au moins, SHAPE_POINTS_MAX au plus,
	 * tous sur le globe. L'anneau n'a pas à être fermé, la base le referme à l'écriture : un dernier
	 * point qui répète le premier ne compte donc pas pour un sommet.
	 */
	public static function isRing(array $points): bool {

		if(array_is_list($points) === FALSE) {
			return FALSE;
		}

		if(count($points) > 1 and $points[0] == $points[count($points) - 1]) {
			array_pop($points);
		}

		if(count($points) > MapSetting::SHAPE_POINTS_MAX) {
			return FALSE;
		}

		$pointsDistinct = [];

		foreach($points as [$longitude, $latitude]) {

			if(abs((float)$longitude) > 180 or abs((float)$latitude) > 90) {
				return FALSE;
			}

			$pointsDistinct[(float)$longitude.' '.(float)$latitude] = TRUE;

		}

		return (count($pointsDistinct) >= 3);

	}

	/**
	 * La surface d'un anneau en mètres carrés, par la formule de `ringArea()` de `@turf/area` 7.2.0, celle avec laquelle le
	 * navigateur affiche la surface pendant le tracé (`MapDrawingPolygon.getArea()`) : les deux côtés donnent le même nombre pour
	 * les mêmes points. Comme dans isRing(), un dernier point qui répète le premier ne compte pas.
	 */
	public static function getArea(array $points): int {

		if(count($points) > 1 and $points[0] == $points[count($points) - 1]) {
			array_pop($points);
		}

		$count = count($points);

		if($count < 3) {
			return 0;
		}

		$total = 0.0;

		for($i = 0; $i < $count; $i++) {

			$lower = $points[$i];
			$middle = $points[($i + 1) % $count];
			$upper = $points[($i + 2) % $count];

			$total += (deg2rad($upper[0]) - deg2rad($lower[0])) * sin(deg2rad($middle[1]));

		}

		return (int)round(abs($total) * self::EARTH_RADIUS * self::EARTH_RADIUS / 2);

	}

	/* getDistance(), getBearing() et getDestination() reprennent `distance()`, `bearing()` et `destination()` de turf 7.2.0, avec
	   lesquelles le navigateur pose les planches d'un tracé (`MapCartography.getBeds()`) : une planche se retrouve au même endroit
	   des deux côtés */

	/* La distance entre deux points, en mètres */
	public static function getDistance(array $from, array $to): float {

		$latitudeFrom = deg2rad($from[1]);
		$latitudeTo = deg2rad($to[1]);

		$a = sin(($latitudeTo - $latitudeFrom) / 2) ** 2 + sin(deg2rad($to[0] - $from[0]) / 2) ** 2 * cos($latitudeFrom) * cos($latitudeTo);

		return 2 * atan2(sqrt($a), sqrt(1 - $a)) * self::EARTH_RADIUS;

	}

	/* Le cap d'un point vers l'autre, en degrés dans le sens horaire depuis le nord, entre -180 et 180 */
	public static function getBearing(array $from, array $to): float {

		$latitudeFrom = deg2rad($from[1]);
		$latitudeTo = deg2rad($to[1]);
		$longitudeDelta = deg2rad($to[0] - $from[0]);

		return rad2deg(atan2(
			sin($longitudeDelta) * cos($latitudeTo),
			cos($latitudeFrom) * sin($latitudeTo) - sin($latitudeFrom) * cos($latitudeTo) * cos($longitudeDelta)
		));

	}

	/* Le point à `$distance` mètres de `$from`, au cap `$bearing` en degrés */
	public static function getDestination(array $from, float $distance, float $bearing): array {

		$longitude = deg2rad($from[0]);
		$latitude = deg2rad($from[1]);
		$bearing = deg2rad($bearing);
		$radians = $distance / self::EARTH_RADIUS;

		$latitudeTo = asin(sin($latitude) * cos($radians) + cos($latitude) * sin($radians) * cos($bearing));
		$longitudeTo = $longitude + atan2(sin($bearing) * sin($radians) * cos($latitude), cos($radians) - sin($latitude) * sin($latitudeTo));

		return [rad2deg($longitudeTo), rad2deg($latitudeTo)];

	}

	/**
	 * Le rectangle de trois points (DrawLib::getLine()) tient-il dans le contour : ses quatre coins y tombent, chacun rentré d'un
	 * cinquantième vers le milieu, parce qu'une planche posée contre un bord a deux coins sur le contour même, que l'arrondi peut
	 * faire tomber dehors. Le navigateur fait le même test (`MapDrawingLine.isInside()`), mais sur le rectangle du rang entier :
	 * sur un contour non convexe, il peut refuser un rang que le serveur accepte. Seuls les coins se jugent, ici comme là : une planche
	 * posée en travers d'un coin rentrant passe.
	 */
	public static function isInside(array $points, array $contour): bool {

		[$start, $across, $end] = $points;

		$corners = [$start, $across, $end, [$start[0] + $end[0] - $across[0], $start[1] + $end[1] - $across[1]]];
		$center = [($start[0] + $end[0]) / 2, ($start[1] + $end[1]) / 2];

		foreach($corners as $corner) {

			$inner = [$corner[0] + ($center[0] - $corner[0]) / 50, $corner[1] + ($center[1] - $corner[1]) / 50];

			if(self::isPointInside($inner, $contour) === FALSE) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/* Un point dans un contour, par le nombre de côtés que croise la demi-droite qui part de lui vers l'est, en longitude et latitude
	   comme `booleanPointInPolygon()` de turf : à l'échelle d'un jardin, la courbure de la Terre n'y change rien */
	private static function isPointInside(array $point, array $contour): bool {

		[$x, $y] = $point;
		$inside = FALSE;
		$count = count($contour);

		for($i = 0, $j = $count - 1; $i < $count; $j = $i++) {

			[$xi, $yi] = $contour[$i];
			[$xj, $yj] = $contour[$j];

			if((($yi > $y) !== ($yj > $y)) and ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
				$inside = !$inside;
			}

		}

		return $inside;

	}

	/**
	 * Le mouvement d'un contour tiré d'un bloc ou tourné, rendu comme MapLib::getTransformed() le prend : l'origine au milieu de la
	 * boite de l'ancien contour, le décalage en mètres et l'angle en degrés. L'angle se lit sur le segment qui va du premier sommet au
	 * plus éloigné de lui, le décalage sur le premier sommet, puis chaque sommet doit tomber à sa place au centimètre près. Un contour
	 * retracé, dont un sommet a bougé seul, qui n'en avait pas ou qui n'a pas bougé n'a pas de mouvement. Il se lit sur les deux
	 * contours plutôt que de se recevoir, pour que ce que porte une parcelle ou un jardin ne suive qu'un mouvement qu'il a vraiment subi.
	 */
	public static function getMotion(?array $before, array $after): ?array {

		if($before === NULL) {
			return NULL;
		}

		$before = self::getVertices($before);
		$after = self::getVertices($after);

		if($before === [] or count($before) !== count($after)) {
			return NULL;
		}

		$origin = self::getOrigin($before);
		$scale = MapSetting::PLANE_METERS * cos(deg2rad($origin[1]));

		// Les points en mètres dans le plan de l'origine, où se mesurent l'angle, le décalage et l'écart de chaque sommet
		$plane = fn(array $point): array => [($point[0] - $origin[0]) * $scale, ($point[1] - $origin[1]) * MapSetting::PLANE_METERS];

		$distances = array_map(fn(array $point): float => hypot(...self::getDifference($plane($point), $plane($before[0]))), $before);
		$farthest = array_search(max($distances), $distances, TRUE);

		// Un contour réduit à un point n'a pas d'angle
		if($farthest === 0) {
			return NULL;
		}

		[$beforeX, $beforeY] = self::getDifference($plane($before[$farthest]), $plane($before[0]));
		[$afterX, $afterY] = self::getDifference($plane($after[$farthest]), $plane($after[0]));

		// getTransformed() tourne dans le sens horaire
		$angle = rad2deg(atan2($beforeY, $beforeX) - atan2($afterY, $afterX));
		$angle = fmod(fmod($angle, 360) + 540, 360) - 180;

		// Un glisser lu en flottants rend un angle de l'ordre de 1e-13°, parfois négatif : ajouté à une planche posée à 0, il la
		// ferait réécrire depuis le coin opposé (DrawLib::getCanonical()). Une rotation qui ne déplace aucun sommet du centimètre
		// n'en est pas une
		if(deg2rad(abs($angle)) * max($distances) <= MapSetting::MOTION_TOLERANCE) {
			$angle = 0.0;
		}

		[$turned] = self::getTransformed([$before[0]], $origin, [0, 0], $angle);
		$shift = self::getDifference($plane($after[0]), $plane($turned));

		$moved = FALSE;

		foreach(self::getTransformed($before, $origin, $shift, $angle) as $index => $point) {

			if(hypot(...self::getDifference($plane($point), $plane($after[$index]))) > MapSetting::MOTION_TOLERANCE) {
				return NULL;
			}

			if(hypot(...self::getDifference($plane($before[$index]), $plane($after[$index]))) > MapSetting::MOTION_TOLERANCE) {
				$moved = TRUE;
			}

		}

		if($moved === FALSE) {
			return NULL;
		}

		return [
			'origin' => $origin,
			'shift' => $shift,
			'angle' => $angle
		];

	}

	private static function getDifference(array $point, array $from): array {
		return [$point[0] - $from[0], $point[1] - $from[1]];
	}

	/* Les sommets d'un anneau comme le navigateur les lit (MapDrawingPolygon) : la base referme l'anneau, et des contours anciens le
	   sont deux fois, donc un point qui répète celui d'avant, ou le premier en fin d'anneau, n'est pas un sommet */
	public static function getVertices(array $ring): array {

		$points = [];

		foreach($ring as $point) {
			if($points === [] or $point !== $points[array_key_last($points)]) {
				$points[] = $point;
			}
		}

		if(count($points) > 1 and $points[0] === $points[array_key_last($points)]) {
			array_pop($points);
		}

		return $points;

	}

	/**
	 * La longueur puis la largeur d'un contour d'abri, en mètres au décimètre. Un rectangle se mesure sur deux côtés consécutifs, avec
	 * la distance de turf, comme MapDrawingRectangle.getDimensions() dans le navigateur : le panneau de création annonce les dimensions
	 * qui seront enregistrées. Un autre contour se mesure sur le plus petit rectangle qui l'englobe, que MapDrawingPolygon.getDimensions()
	 * calcule de la même façon.
	 */
	public static function getDimensions(array $ring): array {

		$points = self::getVertices($ring);
		$plane = self::getPlane($points);

		if(self::isRectangle($plane)) {
			$sides = [self::getDistance($points[0], $points[1]), self::getDistance($points[1], $points[2])];
		} else {
			$sides = self::getBoundingSides($plane);
		}

		$sides = array_map(fn(float $side) => round($side, 1), $sides);

		return [max($sides), min($sides)];

	}

	/* Un contour d'abri qui se reprend en rectangle, comme getDimensions() le mesure */
	public static function isRectangleRing(array $ring): bool {

		return self::isRectangle(self::getPlane(self::getVertices($ring)));

	}

	/* Des sommets en mètres autour de leur origine : à l'échelle d'un jardin, la Terre est plate */
	protected static function getPlane(array $points): array {

		$origin = self::getOrigin($points);
		$scale = MapSetting::PLANE_METERS * cos(deg2rad($origin[1]));

		return array_map(fn(array $point) => [
			($point[0] - $origin[0]) * $scale,
			($point[1] - $origin[1]) * MapSetting::PLANE_METERS
		], $points);

	}

	/* Quatre sommets du plan local dont chaque angle est droit, à MapSetting::RECTANGLE_SKEW près */
	protected static function isRectangle(array $plane): bool {

		if(count($plane) !== 4) {
			return FALSE;
		}

		for($i = 0; $i < 4; $i++) {

			[$x1, $y1] = $plane[$i];
			[$x2, $y2] = $plane[($i + 1) % 4];
			[$x3, $y3] = $plane[($i + 2) % 4];

			$a = [$x2 - $x1, $y2 - $y1];
			$b = [$x3 - $x2, $y3 - $y2];

			$norms = hypot(...$a) * hypot(...$b);

			if($norms === 0.0 or abs($a[0] * $b[0] + $a[1] * $b[1]) / $norms > MapSetting::RECTANGLE_SKEW) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/* Les deux côtés du plus petit rectangle qui englobe des points du plan local. Un de ses côtés porte un côté de l'enveloppe
	   convexe des points : il suffit d'essayer chacun d'eux. Deux rectangles de même aire, aux arrondis près, se départagent par le
	   plus carré, pour que le navigateur (MapDrawingPolygon.getBoundingSides()) montre les dimensions qui s'enregistrent */
	protected static function getBoundingSides(array $plane): array {

		$hull = self::getHull($plane);
		$count = count($hull);

		$best = NULL;

		for($i = 0; $i < $count; $i++) {

			[$x1, $y1] = $hull[$i];
			[$x2, $y2] = $hull[($i + 1) % $count];

			$length = hypot($x2 - $x1, $y2 - $y1);

			if($length === 0.0) {
				continue;
			}

			$u = [($x2 - $x1) / $length, ($y2 - $y1) / $length];

			$alongs = array_map(fn(array $point) => $point[0] * $u[0] + $point[1] * $u[1], $hull);
			$acrosses = array_map(fn(array $point) => - $point[0] * $u[1] + $point[1] * $u[0], $hull);

			$sides = [max($alongs) - min($alongs), max($acrosses) - min($acrosses)];

			if($best === NULL or self::isSmaller($sides, $best)) {
				$best = $sides;
			}

		}

		return $best ?? [0.0, 0.0];

	}

	protected static function isSmaller(array $sides, array $best): bool {

		$area = $sides[0] * $sides[1];
		$areaBest = $best[0] * $best[1];

		if(abs($area - $areaBest) > $areaBest * MapSetting::DIMENSIONS_TIE) {
			return ($area < $areaBest);
		}

		return (max($sides) < max($best));

	}

	/* L'enveloppe convexe de points du plan, par la chaîne monotone d'Andrew */
	protected static function getHull(array $plane): array {

		usort($plane, fn(array $a, array $b) => ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]));

		$cross = fn(array $o, array $a, array $b) => ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);

		$lower = [];

		foreach($plane as $point) {
			while(count($lower) >= 2 and $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $point) <= 0) {
				array_pop($lower);
			}
			$lower[] = $point;
		}

		$upper = [];

		foreach(array_reverse($plane) as $point) {
			while(count($upper) >= 2 and $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $point) <= 0) {
				array_pop($upper);
			}
			$upper[] = $point;
		}

		array_pop($lower);
		array_pop($upper);

		return array_merge($lower, $upper);

	}

	/* Le milieu de la boite d'un contour, d'où une forme se tourne */
	public static function getOrigin(array $points): array {

		$longitudes = array_column($points, 0);
		$latitudes = array_column($points, 1);

		return [(min($longitudes) + max($longitudes)) / 2, (min($latitudes) + max($latitudes)) / 2];

	}

	/**
	 * Des points tournés de `$angle` degrés dans le sens horaire autour de `$origin`, puis décalés de `$shift` mètres [est, nord], dans
	 * le plan métrique local à l'origine : tourner des degrés bruts écraserait la forme d'est en ouest. MapDrawingCopy.transform()
	 * fait le même calcul dans le navigateur, pour que l'aperçu et ce qui s'enregistre coïncident.
	 */
	public static function getTransformed(array $points, array $origin, array $shift, float $angle): array {

		$scale = MapSetting::PLANE_METERS * cos(deg2rad($origin[1]));
		$cos = cos(deg2rad($angle));
		$sin = sin(deg2rad($angle));

		return array_map(function(array $point) use($origin, $shift, $scale, $cos, $sin): array {

			$x = ($point[0] - $origin[0]) * $scale;
			$y = ($point[1] - $origin[1]) * MapSetting::PLANE_METERS;

			return [
				$origin[0] + ($x * $cos + $y * $sin + $shift[0]) / $scale,
				$origin[1] + (- $x * $sin + $y * $cos + $shift[1]) / MapSetting::PLANE_METERS
			];

		}, $points);

	}

}
?>