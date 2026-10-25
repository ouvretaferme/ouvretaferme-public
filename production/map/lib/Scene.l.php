<?php
namespace map;

/**
 * Le plan d'une structure pour une saison, à plat, tel que le lit une scène 3D.
 *
 * `version` (`MapSetting::SCENE_VERSION`) ne monte que sur un retrait, un changement de sens ou un
 * changement d'ordre dans un tuple, jamais sur une clé ajoutée ni sur une colonne ajoutée en fin de
 * tuple : une scène lit `tasks`, `harvests` et `crew` par les noms de `columns`, pas par leur rang.
 *
 * Chaque bloc se lit seul et se borne par la structure reçue, les identifiants passant de l'un à
 * l'autre en `IN` : les tâches, les récoltes et l'équipe peuvent partir dans une seconde réponse sans
 * que le format change.
 */
class SceneLib {

	const COLUMNS_TASK = \series\TaskLib::COLUMNS_SCENE;
	const COLUMNS_HARVEST = \series\HarvestLib::COLUMNS_SCENE;
	const COLUMNS_CREW = ['date', 'members', 'team'];

	/**
	 * La 3D est-elle ouverte à l'une des structures du compte ? Seule une structure qui a la production a un
	 * plan, donc une scène où paraître : l'avatar se fait pour elle, et ses entrées n'existent qu'à cette
	 * condition. `$ccStructure` est la liste des structures du compte par type (`StructureLib::getOnline()`),
	 * déjà chargée pour le menu, comme pour `CourseLib::isOpenByStructures()`.
	 */
	public static function isOpenByStructures(\Collection $ccStructure): bool {

		foreach($ccStructure as $cStructure) {
			foreach($cStructure as $eStructure) {
				if($eStructure->hasFeatureProduction()) {
					return TRUE;
				}
			}
		}

		return FALSE;

	}

	/**
	 * `$avatars` dit si l'équipe sort avec ses avatars (`getCrew()`) : le lecteur voit alors qui est présent chaque jour de
	 * la saison, ce que le planning ne montre qu'à un membre qui a le droit d'y voir les autres. La page le décide, le bourg
	 * et toute autre scène reçoivent l'équipe anonyme.
	 */
	public static function getByStructure(\structure\Structure $eStructure, int $season, bool $avatars = FALSE): array {

		$eStructure->expects(['id', 'name']);

		$ground = self::getGround($eStructure, $season);
		$bedIds = array_column($ground['beds'], 'id');
		$series = \series\SeriesLib::getForScene($eStructure, $season, $bedIds);
		$seriesIds = array_column($series['series'], 'id');
		$tasks = \series\TaskLib::getForScene($eStructure, $seriesIds);
		$eConfiguration = $eStructure->conf(Configuration::class);
		$decorAdopted = DecorLib::isAdopted($eStructure, $eConfiguration);
		$bedsDrawn = self::getBedsDrawn($eStructure, $season, array_column($ground['plots'], 'id'), $bedIds);
		$crew = self::getCrew($eStructure, $season, $avatars);

		return [
			'version' => MapSetting::SCENE_VERSION,
			'structure' => $eStructure['id'],
			'name' => $eStructure['name'],
			'season' => $season,
			'center' => self::getCenter($ground['zones']),
			'zones' => $ground['zones'],
			'plots' => $ground['plots'],
			'beds' => $ground['beds'],
			'bedsDrawn' => $bedsDrawn,
			'layers' => self::getLayers($eStructure),
			'decor' => self::getDecor($eStructure, $ground),
			// Les bâtiments que la structure a composés, posés ou non : une pose `batiment` cite l'un d'eux, la rangée les montre tous
			'buildings' => DecorBuildingLib::getForScene($eStructure),
			// Le décor par défaut ne se tire que tant que la structure n'a pas son propre décor, même vidé depuis : il se copie au
			// premier geste, ce que `DecorLib::adopt()` refuse une fois l'adoption faite
			'decorAdopted' => $decorAdopted,
			// Le décor par défaut ne tire que ce que `DecorLib::adopt()` enregistrera : la règle `near` se lit autour du centre de
			// toutes les parcelles tracées, que la saison affichée peut ne pas toutes montrer
			'decorReach' => $decorAdopted ? NULL : [
				'center' => DecorLib::getCenter($eStructure),
				'distance' => MapSetting::DECOR_NEAR
			],
			'terrain' => self::getTerrain($eConfiguration),
			'places' => $series['places'],
			'series' => $series['series'],
			'actions' => $tasks['actions'],
			'columns' => [
				'tasks' => self::COLUMNS_TASK,
				'harvests' => self::COLUMNS_HARVEST,
				'crew' => self::COLUMNS_CREW,
			],
			'tasks' => $tasks['tasks'],
			'harvests' => \series\HarvestLib::getForScene($eStructure, $season, $seriesIds),
			'crew' => $crew['crew'],
			'team' => $crew['team'],
		];

	}

	/**
	 * La scène du plan 3D, que la page monte et que la bascule du relief relit : celle du bourg, avec les avatars pour un membre
	 * qui voit les autres au planning, et le relief, que le bourg n'a pas.
	 */
	public static function getForPlan(\structure\Structure $eStructure, int $season): array {

		$scene = self::getByStructure($eStructure, $season, avatars: ($eStructure->isMember() and $eStructure->canPlanningLimited()));
		$scene['relief'] = ReliefLib::getForScene($eStructure);

		return $scene;

	}

	private static function getGround(\structure\Structure $eStructure, int $season): array {

		$cZone = SeasonLib::whereSeason(Zone::model(), $season)
			->select(['id', 'name', 'area', 'coordinates'])
			->whereStructure($eStructure)
			->getCollection();

		$cPlot = SeasonLib::whereSeason(Plot::model(), $season)
			->select(['id', 'name', 'zone', 'zoneFill', 'mode', 'decorGreenhouse', 'length', 'width', 'area', 'coordinates'])
			->whereStructure($eStructure)
			->whereZone('IN', $cZone)
			->getCollection();

		$cBed = SeasonLib::whereSeason(Bed::model(), $season)
			->select(['id', 'name', 'plot', 'zoneFill', 'plotFill', 'length', 'width', 'alleyWidth'])
			->whereStructure($eStructure)
			->whereStatus(Bed::ACTIVE)
			->wherePlot('IN', $cPlot)
			->getCollection();

		$ground = ['zones' => [], 'plots' => [], 'beds' => []];

		foreach($cZone as $eZone) {
			$ground['zones'][] = [
				'id' => $eZone['id'],
				'name' => $eZone['name'],
				'area' => $eZone['area'],
				'coordinates' => $eZone['coordinates'],
			];
		}

		foreach($cPlot as $ePlot) {
			$ground['plots'][] = [
				'id' => $ePlot['id'],
				'name' => $ePlot['name'],
				'zone' => $ePlot['zone']['id'],
				'zoneFill' => $ePlot['zoneFill'],
				'mode' => $ePlot['mode'],
				'decorGreenhouse' => $ePlot['decorGreenhouse'],
				'length' => $ePlot['length'],
				'width' => $ePlot['width'],
				'area' => $ePlot['area'],
				'coordinates' => $ePlot['coordinates'],
			];
		}

		foreach($cBed as $eBed) {
			$ground['beds'][] = [
				'id' => $eBed['id'],
				'name' => $eBed['name'],
				'plot' => $eBed['plot']['id'],
				'zoneFill' => $eBed['zoneFill'],
				'plotFill' => $eBed['plotFill'],
				'length' => $eBed['length'],
				'width' => $eBed['width'],
				'alleyWidth' => $eBed['alleyWidth'],
			];
		}

		return $ground;

	}

	/**
	 * L'origine de projection d'une scène, en [longitude, latitude] : la moyenne des sommets des zones
	 * tracées. Rien ne s'enregistre en mètres depuis elle, elle n'a donc pas à être un centre exact.
	 */
	private static function getCenter(array $zones): ?array {

		$coordinates = [];

		foreach($zones as $zone) {
			if($zone['coordinates'] !== NULL) {
				$coordinates = array_merge($coordinates, $zone['coordinates']);
			}
		}

		if($coordinates === []) {
			return NULL;
		}

		return [
			round(array_sum(array_column($coordinates, 0)) / count($coordinates), 6),
			round(array_sum(array_column($coordinates, 1)) / count($coordinates), 6),
		];

	}

	/**
	 * Les planches tracées de la saison, une entrée par planche, par leur coin et leur angle (DrawLib::getLine() en donne le
	 * rectangle). Dans chaque jardin, la saison affichée est la plus récente, marque comprise, et les planches s'y suivent dans
	 * l'ordre de pose.
	 */
	private static function getBedsDrawn(\structure\Structure $eStructure, int $season, array $plotIds, array $bedIds): array {

		$cDraw = Draw::model()
			->select(['id', 'plot', 'season', 'bed' => ['length', 'width'], 'corner', 'angle'])
			->whereStructure($eStructure)
			->wherePlot('IN', $plotIds)
			->whereSeason('<=', $season)
			->sort(['season' => SORT_DESC, 'id' => SORT_ASC])
			->getCollection();

		$bedsDrawn = [];
		$seasons = [];

		foreach($cDraw as $eDraw) {

			$plotId = $eDraw['plot']['id'];
			$seasons[$plotId] ??= $eDraw['season'];

			if(
				$eDraw['season'] !== $seasons[$plotId] or
				$eDraw['bed']->empty() or
				in_array($eDraw['bed']['id'], $bedIds) === FALSE or
				$eDraw['bed']['length'] === NULL or
				$eDraw['bed']['width'] === NULL
			) {
				continue;
			}

			$bedsDrawn[] = [
				'bed' => $eDraw['bed']['id'],
				'plot' => $plotId,
				'season' => $eDraw['season'],
				'corner' => $eDraw['corner'],
				'angle' => $eDraw['angle'],
			];

		}

		return $bedsDrawn;

	}

	/**
	 * Le décor posé, toutes saisons confondues puisqu'un retrait l'efface. Une clôture ne sort que si
	 * l'espace qu'elle entoure est rendu avec un contour, pour que toute référence du format se résolve
	 * dans le format et que la scène ait un tour à dessiner. Celle qui ne sort pas reste en base.
	 */
	private static function getDecor(\structure\Structure $eStructure, array $ground): array {

		$zonesOutlined = [];

		foreach($ground['zones'] as $zone) {
			$zonesOutlined[$zone['id']] = ($zone['coordinates'] !== NULL);
		}

		$plotsOutlined = [];

		foreach($ground['plots'] as $plot) {
			$plotsOutlined[$plot['id']] = $plot['zoneFill'] ? $zonesOutlined[$plot['zone']] : ($plot['coordinates'] !== NULL);
		}

		$cDecor = Decor::model()
			->select(['id', 'layer' => ['type'], 'kit', 'building', 'coordinates', 'angle', 'size', 'seed', 'width', 'model', 'trace', 'diameter', 'laying', 'zone', 'plot', 'hedge', 'gate'])
			->whereStructure($eStructure)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$irrigation = MapSetting::isIrrigationEnabled($eStructure);

		$decor = [];

		foreach($cDecor as $eDecor) {

			// Hors du drapeau de l'irrigation, ses éléments restent en base sans sortir, rangés dans un calque d'irrigation ou non
			if(
				$irrigation === FALSE and
				(in_array($eDecor['kit'], MapSetting::DECOR_IRRIGATION_KITS, TRUE) or ($eDecor['layer']->notEmpty() and $eDecor['layer']['type'] === DecorLayer::IRRIGATION))
			) {
				continue;
			}

			$zoneId = $eDecor['zone']->empty() ? NULL : $eDecor['zone']['id'];
			$plotId = $eDecor['plot']->empty() ? NULL : $eDecor['plot']['id'];

			if(
				($zoneId !== NULL and ($zonesOutlined[$zoneId] ?? FALSE) === FALSE) or
				($plotId !== NULL and ($plotsOutlined[$plotId] ?? FALSE) === FALSE)
			) {
				continue;
			}

			$decor[] = [
				'id' => $eDecor['id'],
				'layer' => $eDecor['layer']->empty() ? NULL : $eDecor['layer']['id'],
				'kit' => $eDecor['kit'],
				'building' => $eDecor['building']->empty() ? NULL : $eDecor['building']['id'],
				'coordinates' => $eDecor['coordinates'],
				'angle' => $eDecor['angle'],
				'size' => $eDecor['size'],
				'seed' => $eDecor['seed'],
				'width' => $eDecor['width'],
				'model' => $eDecor['model'],
				'trace' => $eDecor['trace'],
				'diameter' => $eDecor['diameter'],
				'laying' => $eDecor['laying'],
				'zone' => $zoneId,
				'plot' => $plotId,
				'hedge' => $eDecor['hedge'],
				'gate' => $eDecor['gate'],
			];

		}

		return $decor;

	}

	/**
	 * Les calques ajoutés au décor, dans l'ordre de la carte des calques. Le calque de base « Décor »
	 * n'en est pas : un décor dont `layer` est nul lui appartient. Un calque d'irrigation ne sort que sous
	 * son drapeau.
	 */
	private static function getLayers(\structure\Structure $eStructure): array {

		$irrigation = MapSetting::isIrrigationEnabled($eStructure);

		$layers = [];

		foreach(DecorLayerLib::getByStructure($eStructure) as $eDecorLayer) {

			if($irrigation === FALSE and $eDecorLayer['type'] === DecorLayer::IRRIGATION) {
				continue;
			}

			$layers[] = [
				'id' => $eDecorLayer['id'],
				'name' => $eDecorLayer['name'],
				'type' => $eDecorLayer['type'],
			];

		}

		return $layers;

	}

	private static function getTerrain(Configuration $eConfiguration): array {

		return [
			'grass' => $eConfiguration['decorGrass'],
			'soil' => $eConfiguration['decorSoil'],
		];

	}

	/**
	 * Combien de membres l'équipe du planning compte chaque jour de l'année de la saison, en tuples au format de
	 * `COLUMNS_CREW`. L'équipe et la présence d'un jour sont celles du planning : la même liste de membres
	 * (`MemberLib::getUsersByStructureForPeriod()`), et le même jugement que le temps de travail de la semaine
	 * (`PlanningUi::getWeekTime()`), présent sur sa période et pas en absence ce jour-là. Un jour où personne n'est
	 * présent ne sort pas.
	 *
	 * Deux régimes, selon `$avatars` :
	 * - sans, la scène ne sait de l'équipe que son nombre : `team` est vide et la colonne `team` de chaque jour à `NULL`.
	 *   Un rang stable, même vers un corps anonyme, dirait déjà qui manque quel jour ;
	 * - avec, `team` porte une entrée par présent de la saison (`getTeam()`), et la colonne `team` d'un jour les rangs
	 *   dans `team` de ses présents, dans l'ordre croissant, donc les avatars d'abord. Aucun id d'utilisateur ne sort.
	 */
	private static function getCrew(\structure\Structure $eStructure, int $season, bool $avatars): array {

		$cUser = \structure\MemberLib::getUsersByStructureForPeriod($eStructure, $season.'-01-01', $season.'-12-31', withPresenceAbsence: TRUE);

		$days = [];

		for($date = $season.'-01-01'; $date <= $season.'-12-31'; $date = date('Y-m-d', strtotime($date.' + 1 day'))) {

			$userIds = $cUser->find(fn($eUser) => (
				\hr\Presence::isDatePresent($eUser['cPresence'], $date)->notEmpty() and
				\hr\Absence::isDateAbsent($eUser['cAbsence'], $date)->empty()
			), clone: FALSE)->getIds();

			if($userIds !== []) {
				$days[$date] = $userIds;
			}

		}

		if($avatars === FALSE) {

			$crew = [];

			foreach($days as $date => $userIds) {
				$crew[] = [$date, count($userIds), NULL];
			}

			return ['crew' => $crew, 'team' => []];

		}

		$team = self::getTeam($eStructure, $cUser, array_count_values(array_merge(...array_values($days))));
		$ranks = array_flip(array_keys($team));

		$crew = [];

		foreach($days as $date => $userIds) {

			$present = array_map(fn(int $userId) => $ranks[$userId], $userIds);
			sort($present);

			$crew[] = [$date, count($userIds), $present];

		}

		return ['crew' => $crew, 'team' => array_values($team)];

	}

	/**
	 * Les présents de la saison, indexés par id, dans l'ordre de leurs rangs : ceux dont la scène monte le modèle
	 * téléversé, puis ceux dont elle monte la composition, puis les autres, chaque groupe des plus présents aux moins
	 * présents (`$presences`, id vers nombre de jours). La scène ne montre que neuf corps à la fois : les jours où l'équipe
	 * les dépasse, les premiers rangs passent avant.
	 *
	 * Un avatar ne paraît que pour un auteur que la structure admet (`UserAvatarUploadLib::getAuthorsByStructure()`),
	 * composition comprise : la même règle que la route qui sert le modèle. Les autres, fantôme, ancien membre, compte
	 * suspendu ou banni, ont `NULL`, le corps par défaut. Un avatar porte `fiche` (`UserAvatarLib::getFiche()`) et
	 * `eUserAvatarUpload`, le modèle téléversé pour les `MapSetting::CREW_UPLOADS_MAX` plus présents qui en ont un,
	 * vide pour les autres, que la fiche montre.
	 */
	private static function getTeam(\structure\Structure $eStructure, \Collection $cUser, array $presences): array {

		$cUserAuthor = \user\UserAvatarUploadLib::getAuthorsByStructure($eStructure, $cUser);
		$cUserAvatar = \user\UserAvatarLib::getByUsers($cUserAuthor);
		$cUserAvatarUpload = \user\UserAvatarUploadLib::getByUsers($eStructure, $cUserAuthor);

		$userIds = array_keys($presences);

		// À présence égale, l'id départage : les rangs ne changent pas d'une lecture à l'autre
		usort($userIds, fn(int $userId1, int $userId2) => [$presences[$userId2], $userId1] <=> [$presences[$userId1], $userId2]);

		// Trois groupes : le modèle, la composition, le corps par défaut
		$groups = [[], [], []];
		$uploads = 0;

		foreach($userIds as $userId) {

			if($cUserAvatar->offsetExists($userId) === FALSE) {
				$groups[2][$userId] = NULL;
				continue;
			}

			$upload = ($cUserAvatarUpload->offsetExists($userId) and $uploads < MapSetting::CREW_UPLOADS_MAX);

			if($upload) {
				$uploads++;
			}

			$groups[$upload ? 0 : 1][$userId] = [
				'fiche' => \user\UserAvatarLib::getFiche($cUserAvatar[$userId]),
				'eUserAvatarUpload' => $upload ? $cUserAvatarUpload[$userId] : new \user\UserAvatarUpload()
			];

		}

		return $groups[0] + $groups[1] + $groups[2];

	}

}
?>
