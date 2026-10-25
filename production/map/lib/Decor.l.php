<?php
namespace map;

class DecorLib extends DecorCrud {

	// Un sommet intérieur d'un tracé compte pour CORNER mètres : les piquets d'angle d'une clôture électrique coûtent 53 triangles
	// par coude (mesurés dans Chrome le 2026-10-10), et le mètre du plafond en vaut 29, soit 1,8 m
	const CORNER = 1.8;

	// Le plafond se compte en mètres à 29 triangles, ce que pesait la haie d'un seul tronçon quand il a été fixé (mesuré dans Chrome
	// le 2026-10-11) : un tracé plus lourd ou plus léger au mètre y pèse autant de fois son poids. La bande fleurie, 86 triangles au
	// mètre ; la route, 4,3 (896 triangles sur 210 m), arrondi au centième supérieur. Un kit absent pèse 1.
	// La haie mêle ses formes au hasard de sa graine et n'a pas un poids fixe au mètre : le plafond ne tient que si elle compte pour
	// son pire tirage (`MapSceneDecor.hedgeSpans()`). Le plus lourd qui puisse sortir est quatre travées de baies, une d'une autre
	// forme et un arbre, 1 124 triangles par six travées de 3 m, 2,15 fois 29 au mètre : HEDGE_WEIGHT. Une haie de deux points tient
	// en une travée aussi courte soit-elle, 210 triangles pour une travée de baies, et quatre travées de baies en font 840 dès
	// 9,45 m : HEDGE_START, les mètres comptés une fois par haie, que le poids au mètre ne couvre pas. Le tirage moyen pèse 1,43
	// (2 000 graines par longueur, de 0,1 à 300 m, le 2026-10-13).
	// Le tour d'un enclos ne compte pas ses coudes, alors que la haie y commence une travée à chacun : le pire mesuré d'un enclos à
	// quatre coudes est un rectangle de 1,25 m sur 1,5 m à haie seule, 1 544 triangles, 53,2 m à 29, pour 20,3 m comptés (189 840
	// carrés et rectangles de 0,5 à 40 m, le 2026-10-13).
	// Une haie tracée ferme aussi une travée à chaque coude, que CORNER ne couvre pas tout à fait : le pire que ses règles permettent
	// est un zigzag à angle droit de 80 côtés de 3,74 m, 29 854 triangles pour 28 399 comptés, 50,2 m à 29 de trop, qu'aucune graine
	// sur 20 000 n'atteint (pire tiré 23 002, sur 181 764 lignes brisées, le 2026-10-13). Un petit tracé, lui, dépasse pour de bon :
	// deux côtés de 3,75 m à angle droit pèsent 840 triangles pour 831 comptés, ce qu'une graine sur neuf tire
	const HEDGE_WEIGHT = 2.2;
	const HEDGE_START = 8.2;

	const TRACE_WEIGHT = [
		'bande-fleurie' => 3,
		'haie' => self::HEDGE_WEIGHT,
		'route' => 0.15,
	];

	const TRACE_START = [
		'haie' => self::HEDGE_START,
	];

	// Ce qu'un objet posé pèse dans la scène, en triangles : le plus lourd tirage que son kit puisse sortir, compté par construction
	// d'après `MapSceneDecor.KITS` et le champ `triangles` de `base/scene/static/model/models.json`, jamais d'après un tirage
	// observé, qui ne dit pas le pire. Un arbre tire parmi `arbre` (364), `arbre.sapin` et `arbre.peuplier`, un fruitier parmi
	// `fruitier` et ses deux variantes à 298. Un bosquet fait trois massifs de huit arbres au plus, 3 × 8 × 364 ; un massif seul,
	// 8 × 364 ; un verger, deux rangs de trois fruitiers, 6 × 298 ; des buissons, neuf `buisson` de 40 ; un alignement, quatre
	// arbres de 364. La mare porte ses quatre rides, 48 triangles chacune, en plus de son modèle de 221. La pompe et la vanne sont
	// des formes bâties par la scène (`makeShape()`), 164 triangles chacune. Un bâtiment de la structure, composé ou importé,
	// pèse le plafond d'un import (`maxTriangles` de `base/scene/upload/decor.json`). Un tracé, un enclos et le réseau
	// d'irrigation tracé pèsent 0 ici : ils se comptent au mètre sous `DECOR_TRACE_LENGTH`, ou par leur propre mesure (`getTraceCost()`).
	// Un kit absent du tableau pèse le plus lourd : un kit neuf oublié ici serait compté trop cher, jamais trop peu.
	// `DecorWeightSentinel` recalcule chaque poids depuis `KITS` et `models.json`, et rougit sur un écart dans un sens ou dans l'autre
	const KIT_TRIANGLES = [
		'hangar' => 616,
		'bureau' => 568,
		'abri' => 184,
		'stabulation' => 1024,
		'bergerie' => 808,
		'fenil' => 1124,
		'hangar-solaire' => 1012,
		'laverie' => 1324,
		'chambre-froide' => 292,
		'magasin' => 952,
		'poulailler-mobile' => 584,
		'sechoir' => 820,
		'batiment' => 2500,
		'mare' => 413,
		'bassin-bache' => 472,
		'cuve' => 156,
		'puits' => 336,
		'cours-eau' => 0,
		'arbre' => 364,
		'fruitier' => 298,
		'bosquet' => 8736,
		'massif' => 2912,
		'verger' => 1788,
		'buissons' => 360,
		'alignement' => 1456,
		'chene' => 288,
		'saule' => 280,
		'bouleau' => 242,
		'arbre-mort' => 88,
		'souche' => 87,
		'roncier' => 228,
		'fougeres' => 292,
		'roseaux' => 260,
		'rocher' => 124,
		'pierrier' => 280,
		'bande-fleurie' => 0,
		'haie' => 0,
		'chemin' => 0,
		'route' => 0,
		'cloture' => 0,
		'portail' => 144,
		'enclos' => 0,
		'panneau' => 96,
		'compost' => 174,
		'pompe' => 164,
		'vanne' => 164,
		'electrovanne' => 228,
		'tuyau' => 0,
		'rampe' => 0,
		'caisses' => 156,
		'tracteur' => 348,
		'utilitaire' => 284,
		'pick-up' => 262,
		'remorque' => 224,
	];

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['layer', 'building', 'kit', 'coordinates', 'angle', 'size', 'seed', 'trace', 'width', 'diameter', 'laying', 'zone', 'plot', 'model', 'hedge', 'gate'];
	}

	/**
	 * Un tracé ne se déplace pas d'un bloc, il se modifie point par point : sa place est celle de ses points, que `update()`
	 * reporte sur `coordinates`. Un objet ponctuel se déplace, se tourne et se retaille, et ne prend jamais de tracé.
	 */
	public static function getPropertiesPosition(Decor $e): array {

		$e->expects(['trace']);

		return ($e['trace'] !== NULL) ? ['trace', 'width'] : ['coordinates', 'angle', 'size'];

	}

	/**
	 * L'origine des positions du décor, en [longitude, latitude] : la moyenne des sommets des zones
	 * tracées, toutes saisons confondues puisque le décor n'en a pas. NULL quand aucune zone n'est
	 * tracée, et rien ne se pose alors.
	 */
	public static function getCenter(\structure\Structure $eStructure): ?array {

		$zones = Zone::model()
			->whereStructure($eStructure)
			->where('coordinates IS NOT NULL')
			->getColumn('coordinates');

		$coordinates = array_merge(...$zones);

		if($coordinates === []) {
			return NULL;
		}

		return [
			round(array_sum(array_column($coordinates, 0)) / count($coordinates), 6),
			round(array_sum(array_column($coordinates, 1)) / count($coordinates), 6),
		];

	}

	public static function isNear(array $center, array $point): bool {

		[$x, $n] = self::toMeters($center, $point);

		return (hypot($x, $n) <= MapSetting::DECOR_NEAR);

	}

	/**
	 * Le hangar du catalogue posé par la structure, que l'éditeur des bâtiments personnalise : un id d'une autre structure ou
	 * d'un autre kit ne rend rien.
	 */
	public static function getHangar(\structure\Structure $eStructure, ?int $id): Decor {

		if($id === NULL) {
			return new Decor();
		}

		return Decor::model()
			->select('id')
			->whereStructure($eStructure)
			->whereKit('hangar')
			->whereId($id)
			->get();

	}

	public static function countByLayer(DecorLayer $eDecorLayer): int {

		$eDecorLayer->expects(['structure']);

		return Decor::model()
			->whereStructure($eDecorLayer['structure'])
			->whereLayer($eDecorLayer)
			->count();

	}

	public static function create(Decor $e): void {

		$e->expects(['structure', 'building']);

		$cDecor = self::getForLimit($e['structure']);

		if($cDecor->count() >= MapSetting::DECOR_MAX) {
			Decor::fail('structure.limit');
			return;
		}

		if(self::getTraceLength($cDecor) + self::getTraceCost($e) > MapSetting::DECOR_TRACE_LENGTH) {
			Decor::fail('trace.limit');
			return;
		}

		if(self::sumTriangles($cDecor) + self::getKitTriangles($e['kit']) > MapSetting::DECOR_TRIANGLES) {
			Decor::fail('structure.triangles');
			return;
		}

		Decor::model()->beginTransaction();

		// Le bâtiment posé se relit sous verrou : `DecorBuildingLib::delete()` attend alors la fin de cette pose, et trouve la
		// ligne qui le cite. Lu hors transaction par `building.owned`, il a pu disparaitre depuis
		if(
			$e['building']->notEmpty() and
			DecorBuilding::model()
				->whereStructure($e['structure'])
				->whereId($e['building'])
				->option('for-update')
				->exists() === FALSE
		) {
			Decor::model()->commit();
			Decor::fail('building.owned');
			return;
		}

		parent::create($e);

		// Une pose fait du plan celui de la structure : le décor par défaut ne revient plus, même une fois le plan vidé
		ConfigurationLib::adoptDecor($e['structure']);

		Decor::model()->commit();

	}

	/**
	 * Les éléments du décor par défaut que la scène poste au premier geste, chacun à l'index où il est posté. Le nombre se refuse
	 * avant toute construction : chaque élément construit relit sa place contre le centre du plan. Seuls des objets ponctuels des
	 * kits du défaut se construisent, et rien ne les rattache à un calque ni à une emprise : un kit hors de cette liste refuse
	 * l'envoi entier, qui n'est alors pas celui de la scène.
	 */
	public static function buildDefault(Decor $eDecorSource, array $input): \Collection {

		$cDecor = new \Collection();

		// Une valeur postée n'a pas la forme que la scène envoie quand ce n'est pas un tableau indexé par rang
		$kits = $input['kit'] ?? NULL;

		if(is_array($kits) === FALSE) {
			Decor::fail('default.check');
			return $cDecor;
		}

		$indexes = array_keys($kits);

		if(
			$indexes === [] or
			count($indexes) > MapSetting::DECOR_DEFAULT_MAX or
			array_filter($indexes, fn($index) => is_int($index) === FALSE) !== []
		) {
			Decor::fail('default.check');
			return $cDecor;
		}

		if(array_filter($kits, fn($kit) => in_array($kit, MapSetting::DECOR_DEFAULT_KITS, TRUE) === FALSE) !== []) {
			Decor::fail('kit.default');
			return $cDecor;
		}

		foreach($indexes as $index) {

			// Un objet ponctuel du calque de base, sans tracé ni emprise : ce que `create()` pèse contre les plafonds
			$eDecor = clone $eDecorSource;
			$eDecor->merge([
				'layer' => new DecorLayer(),
				'building' => new DecorBuilding(),
				'trace' => NULL,
				'width' => NULL,
				'model' => NULL,
				'diameter' => NULL,
				'laying' => NULL,
				'zone' => new Zone(),
				'plot' => new Plot(),
				'hedge' => FALSE,
				'gate' => NULL
			]);
			$eDecor->buildIndex(['kit', 'coordinates', 'angle', 'size', 'seed'], $input, $index, new \PropertyList('create'));

			$cDecor[$index] = $eDecor;

		}

		return $cDecor;

	}

	/**
	 * Le décor par défaut devient celui de la structure, tout ou rien, et seulement si elle n'a pas encore son propre décor, même
	 * vidé depuis. Deux fenêtres qui font chacune leur premier geste en même temps passent l'une après l'autre sur le verrou de la
	 * structure, et la seconde trouve l'adoption de la première.
	 */
	public static function adopt(\structure\Structure $eStructure, \Collection $cDecor): void {

		Decor::model()->beginTransaction();

		// Première lecture de la transaction : celle qui suit le verrou voit ce qu'une autre fenêtre vient de valider
		\structure\Structure::model()
			->whereId($eStructure)
			->getForUpdate();

		// Rien n'est encore écrit : la transaction se clôt sans rien annuler, et rend le verrou
		if(self::isAdopted($eStructure, ConfigurationLib::readByStructure($eStructure))) {
			Decor::model()->commit();
			Decor::fail('structure.adopted');
			return;
		}

		$fw = new \FailWatch();

		foreach($cDecor as $eDecor) {

			self::create($eDecor);

			// Un seul élément refusé annule l'adoption entière, et le refus remonte tel quel jusqu'à la scène. Les plafonds du décor
			// (DECOR_MAX, DECOR_TRACE_LENGTH, DECOR_TRIANGLES) dépassent ce que le défaut tire sur une structure vide : la branche
			// garde la transaction entière si l'un d'eux descend un jour sous ce tirage
			if($fw->ok() === FALSE) {
				Decor::model()->rollBack();
				throw new \FailAction($fw);
			}

		}

		Decor::model()->commit();

	}

	/**
	 * La structure a son propre décor : elle l'a adopté, ou elle a des lignes. Une ligne vaut adoption, drapeau posé ou non : le
	 * drapeau ne fait que retenir un plan vidé, et le défaut ne se tire jamais par-dessus un décor enregistré.
	 */
	public static function isAdopted(\structure\Structure $eStructure, Configuration $eConfiguration): bool {
		return ($eConfiguration['decorAdopted'] or Decor::model()->whereStructure($eStructure)->exists());
	}

	/**
	 * Annuler défait l'adoption : les éléments repris du défaut s'en vont, et le plan retrouve le décor par défaut. Seulement si
	 * la structure n'a rien d'autre en base, sans quoi le défaut reviendrait par-dessus un décor qu'une autre fenêtre a posé.
	 */
	public static function unadopt(\structure\Structure $eStructure, array $ids): void {

		Decor::model()->beginTransaction();

		\structure\Structure::model()
			->whereId($eStructure)
			->getForUpdate();

		$cDecor = Decor::model()
			->select('id')
			->whereStructure($eStructure)
			->getCollection(index: 'id');

		// Annuler défait une adoption de la séance, donc toujours une structure adoptée et au moins une ligne posée par la séance.
		// Sans id, ou sur une structure qui n'a rien adopté, il n'y a rien à annuler, et un plan vidé reste vide. Les ids postés
		// sont ceux de la séance : une ligne qu'ils ne nomment pas vient d'une autre fenêtre, et le défaut ne revient pas
		// par-dessus. Un id posté sans ligne est un élément que la séance a retiré, puisqu'Annuler suit un plan qu'on a pu vider
		// avant de se raviser. Rien n'est encore écrit : la transaction se clôt et rend le verrou
		if(
			$ids === [] or
			ConfigurationLib::readByStructure($eStructure)['decorAdopted'] === FALSE or
			array_diff($cDecor->getIds(), $ids) !== []
		) {
			Decor::model()->commit();
			Decor::fail('structure.unadopted');
			return;
		}

		foreach($cDecor as $eDecor) {
			self::delete($eDecor);
		}

		ConfigurationLib::unadoptDecor($eStructure);

		Decor::model()->commit();

	}

	public static function update(Decor $e, array $properties): void {

		$e->expects(['id', 'structure']);

		// L'emprise est fixée à la création (un changement d'emprise est un retrait suivi d'une pose) :
		// seuls le tracé, sa largeur, le modèle et la haie font varier le coût d'un décor posé
		if(array_intersect(['trace', 'width', 'model', 'hedge'], $properties) !== []) {

			$cDecor = self::getForLimit($e['structure']);

			$lengthBefore = self::getTraceLength($cDecor);

			$eDecorStored = $cDecor[$e['id']];

			foreach(array_intersect(['trace', 'width', 'model', 'hedge'], $properties) as $property) {
				$eDecorStored[$property] = $e[$property];
			}

			$lengthAfter = self::getTraceLength($cDecor);

			// Un plafond abaissé laisse des structures au-delà : elles raccourcissent sans être bloquées
			if($lengthAfter > MapSetting::DECOR_TRACE_LENGTH and $lengthAfter > $lengthBefore) {
				Decor::fail('trace.limit');
				return;
			}

		}

		// La place d'un tracé est le centre de la boite de ses points, celle où la scène le pose (`lay()`, `sceneDecor.js`)
		if(in_array('trace', $properties) and $e['trace'] !== NULL) {
			$e['coordinates'] = self::getTraceCenter($e['trace']);
			$properties[] = 'coordinates';
		}

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Une clôture s'en va avec l'espace qu'elle entoure : sans lui, la scène ne la rend plus et rien ne
	 * permettrait de la retirer. Une parcelle emporte aussi les clôtures de ses jardins, qu'elle
	 * supprime avec elle.
	 */
	public static function deleteByZone(Zone $eZone): void {

		$eZone->expects(['id', 'structure']);

		$cDecor = Decor::model()
			->select('id')
			->whereStructure($eZone['structure'])
			->or(
				fn() => $this->whereZone($eZone),
				fn() => $this->wherePlot('IN', Plot::model()->select('id')->whereZone($eZone)->getSql())
			)
			->getCollection();

		foreach($cDecor as $eDecor) {
			self::delete($eDecor);
		}

	}

	public static function deleteByPlot(Plot $ePlot): void {

		$ePlot->expects(['id', 'structure']);

		$cDecor = Decor::model()
			->select('id')
			->whereStructure($ePlot['structure'])
			->wherePlot($ePlot)
			->getCollection();

		foreach($cDecor as $eDecor) {
			self::delete($eDecor);
		}

	}

	/**
	 * Les poses d'un bâtiment qui quitte la rangée, dans la transaction de `DecorBuildingLib::delete()`, qui l'a verrouillé :
	 * lues sous verrou, une pose validée juste avant la suppression part aussi.
	 */
	public static function deleteByBuilding(DecorBuilding $eDecorBuilding): void {

		$eDecorBuilding->expects(['id', 'structure']);

		$cDecor = Decor::model()
			->select('id')
			->whereStructure($eDecorBuilding['structure'])
			->whereBuilding($eDecorBuilding)
			->option('for-update')
			->getCollection();

		foreach($cDecor as $eDecor) {
			self::delete($eDecor);
		}

	}

	private static function getForLimit(\structure\Structure $eStructure): \Collection {

		return Decor::model()
			->select([
				'id', 'kit', 'trace', 'width', 'model', 'hedge',
				'layer' => ['type'],
				'zone' => ['coordinates'],
				'plot' => ['zoneFill', 'coordinates', 'zone' => ['coordinates']]
			])
			->whereStructure($eStructure)
			->getCollection(index: 'id');

	}

	/* Ce que le décor posé d'une structure pèse dans la scène, en triangles (KIT_TRIANGLES) */
	public static function getTriangles(\structure\Structure $eStructure): int {
		return self::sumTriangles(self::getForLimit($eStructure));
	}

	public static function getKitTriangles(string $kit): int {
		return self::KIT_TRIANGLES[$kit] ?? max(self::KIT_TRIANGLES);
	}

	private static function sumTriangles(\Collection $cDecor): int {

		$triangles = 0;

		foreach($cDecor as $eDecor) {
			$triangles += self::getKitTriangles($eDecor['kit']);
		}

		return $triangles;

	}

	private static function getTraceLength(\Collection $cDecor): float {

		$length = 0.0;

		foreach($cDecor as $eDecor) {
			$length += self::getTraceCost($eDecor);
		}

		return $length;

	}

	/**
	 * La longueur qu'un décor pèse dans la scène, en mètres du plafond : celle de son tracé, chaque coude compté
	 * en plus, multipliée par son poids (TRACE_WEIGHT), ou le tour de son emprise, une fois pour sa clôture et HEDGE_WEIGHT fois pour sa haie. Une haie y ajoute HEDGE_START.
	 * Le poids multiplie aussi les coudes, que le kit ne rend pas plus lourds : le plafond en est atteint plus tôt, jamais plus tard, sauf pour une haie, dont chaque coude ferme une travée (limite mesurée en tête de classe).
	 * Un objet posé n'en pèse aucune, il compte dans `DECOR_MAX`. Un chemin ou un cours d'eau non plus :
	 * une bande coûte ses sommets, que sa longueur n'augmente pas, et le plafond ne vaut que pour ce
	 * qui se répète au mètre.
	 */
	private static function getTraceCost(Decor $e): float {

		$e->expects(['kit', 'trace', 'width', 'model', 'hedge', 'layer', 'zone', 'plot']);

		if($e['width'] !== NULL) {
			return 0.0;
		}

		// Un tuyau ou une rampe d'irrigation non plus, mesuré le 2026-10-11 : 2 km de tuyau et 40 rampes de 60 m pèsent
		// 36 284 triangles, et laissent la pire journée de la structure 7 à 1 197 063, sous 1,2 M
		if($e['layer']->notEmpty() and $e['layer']['type'] === DecorLayer::IRRIGATION) {
			return 0.0;
		}

		if($e['trace'] !== NULL) {
			return (self::getLength($e['trace']) + self::CORNER * (count($e['trace']) - 2)) * (self::TRACE_WEIGHT[$e['kit']] ?? 1) + (self::TRACE_START[$e['kit']] ?? 0);
		}

		if($e['zone']->notEmpty()) {
			$outline = $e['zone']['coordinates'];
		} else if($e['plot']->notEmpty()) {
			$outline = $e['plot']['zoneFill'] ? $e['plot']['zone']['coordinates'] : $e['plot']['coordinates'];
		} else {
			return 0.0;
		}

		// Une emprise qui a perdu son contour ne sort pas dans la scène, et n'y pèse donc rien
		if($outline === NULL) {
			return 0.0;
		}

		$weight = (int)($e['model'] !== NULL) + self::HEDGE_WEIGHT * (int)$e['hedge'];

		return self::getLength($outline) * $weight + self::HEDGE_START * (int)$e['hedge'];

	}

	private static function getTraceCenter(array $trace): array {

		$longitudes = array_column($trace, 0);
		$latitudes = array_column($trace, 1);

		return [
			round((min($longitudes) + max($longitudes)) / 2, 6),
			round((min($latitudes) + max($latitudes)) / 2, 6)
		];

	}

	/**
	 * La longueur d'une ligne de points [longitude, latitude], en mètres. Un contour se ferme déjà sur
	 * son premier point.
	 */
	private static function getLength(array $points): float {

		$length = 0.0;

		for($i = 1; $i < count($points); $i++) {
			$length += hypot(...self::toMeters($points[$i - 1], $points[$i]));
		}

		return $length;

	}

	/**
	 * Un point [longitude, latitude] en mètres [est, nord] depuis une origine, par la projection
	 * qu'emploie la scène (`ScenePlanGeometry`, `base/scene/static/js/plan.js`) : à l'échelle d'une structure, l'écart à la
	 * sphère est sans effet.
	 */
	private static function toMeters(array $origin, array $point): array {

		return [
			($point[0] - $origin[0]) * 111320 * cos(deg2rad($origin[1])),
			($point[1] - $origin[1]) * 110540
		];

	}

}
?>
