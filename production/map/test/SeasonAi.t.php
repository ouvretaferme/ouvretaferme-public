<?php
namespace map;

/**
 * La lumière et les teintes de saison de la scène (`static/js/scene.js`, `MapSceneSeason`), éprouvées sous node telles que le
 * navigateur charge les fichiers : du 1er novembre au 31 décembre, l'herbe, les chemins, le ciel, le soleil et l'appoint du ciel
 * gardent leur valeur du 1er novembre, pendant que les arbres du décor (`static/js/sceneDecor.js`, `MapSceneDecor.season()`)
 * continuent leur année. Le 1er janvier reprend cette lumière et rejoint l'hiver en neuf jours, la haie en quatorze, sans qu'aucun
 * jour ne saute.
 *
 * La scène du plan lit sa propre saison (`MapScenePlanSeason`, « Plan clair ») : des herbes de la même famille claire, un ciel
 * blanc et un soleil plus haut. Le bourg garde les tables de `MapSceneSeason`, dont les étalons ci-dessous sont aussi les siens.
 * Le terrain qu'on choisit dans le décor a les valeurs de l'enum de `map\Configuration`, des couleurs distinctes, et un cadre sur
 * les planches d'une terre claire.
 *
 * Aucune écriture, aucune base ouverte. La scène n'a pas d'horloge à figer : le jour montré est la date que le scénario passe à
 * `at()`, comme `MapScene.light()` la lui passe.
 *
 * Lancement : dev/test package=map file=SeasonAi
 */
class SeasonAiTest extends \Test {

	// Les tables partagées avec le bourg (`MapSceneSeason`), lues au mois de scène 10, celui du 1er novembre, au 10 janvier et au
	// 1er juillet. Les valeurs sont écrites ici et non relues dans la scène : c'est l'étalon des teintes de saison du bourg, qui
	// rougit le jour où une table partagée est retouchée, y compris par une retouche voulue pour le seul plan
	const PLATEAU = ['grass' => '9dca7e', 'sky' => 'dfe6e6', 'sun' => 'fdecd2', 'path' => 'dccfae', 'force' => 1.864, 'fill' => 1.25, 'height' => 0.757];

	// Le 10 janvier, l'hiver est arrivé et les tables se lisent seules, au mois de scène 9 / 31
	const WINTER_REACHED = ['grass' => 'a8cc89', 'sky' => 'dce6ee', 'sun' => 'faf1e4', 'path' => 'dcd1b3', 'force' => 1.562, 'fill' => 1.6, 'height' => 0.624];

	const JULY = ['grass' => '8fbf72', 'sky' => 'bfe2f6', 'sun' => 'fff0ca', 'path' => 'dccfae', 'force' => 2.236, 'fill' => 1.25, 'height' => 1.15];

	// Le pré par défaut du plan (« Plan clair », la table partagée éclaircie de 25 % vers le blanc, le cran qui suit le dosage 3 de 40 %,
	// pour un vert un peu plus soutenu demandé) aux jours de ses clés, et au 10 janvier, l'hiver arrivé, toujours vert
	const PLAN_GRASS = ['2026-03-01' => 'bedba1', '2026-07-01' => 'abcf95', '2026-09-01' => 'aed397', '2027-01-10' => 'bed9a7'];

	// La verte pâle, le dosage 2 de la planche, aux jours de ses clés
	const PALE_GRASS = ['2026-03-01' => 'd8e9c7', '2026-07-01' => 'cde2c0', '2026-09-01' => 'cee4c0'];

	// La presque blanche, le fond de la première piste de « Plan clair », qui ne suit pas la saison
	const CLEAR_GRASS = 'eef2e4';

	// Le ciel blanc du plan au 1er juillet, et la hauteur de son soleil, 64° au lieu de 56
	const PLAN_JULY = ['sky' => 'f0f6fa', 'height' => 1.6];

	// Les terres claires, dont les planches prennent un cadre sur le pré pâle ; les autres n'en ont pas
	const SOILS_FRAMED = ['loam', 'sand', 'chalk', 'ochre', 'sandstone'];

	// L'écart CIE76 au-dessous duquel deux herbes ou deux sols du décor se confondraient à l'œil, en vignette comme dans le plan.
	// Le limon et le sable, les plus proches des sols, sont à 9,4
	const DELTA_E_GRASS = 10;
	const DELTA_E_SOIL = 9;

	// Le plus grand pas d'un jour au suivant, en niveaux de couleur sRGB sur une composante pour les teintes. Les plafonds de la force,
	// de l'appoint et de la hauteur du soleil sont ceux que l'arrivée de l'hiver atteint, arrondis au centième supérieur
	const STEP_COLOR = 3;
	const STEP_FORCE = 0.06;
	const STEP_FILL = 0.06;
	const STEP_HEIGHT = 0.03;

	public function testLightFromNovemberToJanuary(): void {

		$result = $this->run('
			const season = new MapSceneSeason(THREE);

			emit(Object.fromEntries(["2026-10-31", "2026-11-01", "2026-11-15", "2026-12-31", "2027-01-01", "2027-01-05", "2027-01-10"].map(date => [date, read(season, date)])));
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(self::PLATEAU, $result['2026-11-01'], 'au 1er novembre, les tables se lisent au mois de scène 10');
		$this->assertEquals(self::PLATEAU, $result['2026-11-15'], 'au 15 novembre, la lumière et les teintes sont celles du 1er novembre');
		$this->assertEquals(self::PLATEAU, $result['2026-12-31'], 'au 31 décembre, la lumière et les teintes sont celles du 1er novembre');

		// La veille du plafond, l'année suit encore son cours : la force et la hauteur du soleil n'ont pas fini de baisser
		$this->assertTrue($result['2026-10-31']['force'] > self::PLATEAU['force'], 'au 31 octobre, le soleil est plus fort qu\'au 1er novembre : '.$result['2026-10-31']['force']);
		$this->assertTrue($result['2026-10-31']['height'] > self::PLATEAU['height'], 'au 31 octobre, le soleil est plus haut qu\'au 1er novembre : '.$result['2026-10-31']['height']);
		$this->assertEquals(self::PLATEAU['fill'], $result['2026-10-31']['fill'], 'au 31 octobre, l\'appoint du ciel est celui du reste de l\'année');

		$this->assertEquals(self::PLATEAU, $result['2027-01-01'], 'au 1er janvier, la lumière et les teintes sont encore celles du 31 décembre');
		$this->assertTrue(($result['2027-01-05']['force'] < self::PLATEAU['force'] and $result['2027-01-05']['force'] > self::WINTER_REACHED['force']), 'au 5 janvier, la force du soleil est en chemin vers l\'hiver : '.$result['2027-01-05']['force']);
		$this->assertTrue(($result['2027-01-05']['fill'] > self::PLATEAU['fill'] and $result['2027-01-05']['fill'] < self::WINTER_REACHED['fill']), 'au 5 janvier, l\'appoint du ciel est en chemin vers l\'hiver : '.$result['2027-01-05']['fill']);
		$this->assertEquals(self::WINTER_REACHED, $result['2027-01-10'], 'au 10 janvier, l\'hiver est arrivé et les tables se lisent seules');

	}

	public function testSharedTablesInJuly(): void {

		$result = $this->run('
			emit(read(new MapSceneSeason(THREE), "2026-07-01"));
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(self::JULY, $result, 'au 1er juillet, les tables partagées avec le bourg n\'ont pas bougé');

	}

	/**
	 * L'herbe par défaut du plan prend la table pâle ; le ciel et le soleil du plan sont les siens, ceux du bourg restent.
	 */
	public function testPlanDefaultGrassIsPale(): void {

		$result = $this->run('
			const season = new MapScenePlanSeason(THREE);
			season.useGrass("tender");

			emit({
				grass: Object.fromEntries(' . json_encode(array_keys(self::PLAN_GRASS)) . '.map(date => [date, read(season, date).grass])),
				july: read(season, "2026-07-01")
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(self::PLAN_GRASS, $result['grass'], 'l\'herbe par défaut du plan suit sa table éclaircie de 25 %, hiver compris');
		$this->assertEquals(self::PLAN_JULY, ['sky' => $result['july']['sky'], 'height' => $result['july']['height']], 'au 1er juillet, le plan a un ciel blanc et un soleil plus haut');
		$this->assertEquals(array_diff_key(self::JULY, ['grass' => TRUE, 'sky' => TRUE, 'height' => TRUE]), array_diff_key($result['july'], ['grass' => TRUE, 'sky' => TRUE, 'height' => TRUE]), 'au 1er juillet, le soleil, sa force, l\'appoint et le chemin du plan sont ceux du bourg');

		// La composante dominante dit le vert, et non un écart de teinte qu'un réglage voisin ferait passer
		[$red, $green, $blue] = array_map('hexdec', str_split($result['grass']['2027-01-10'], 2));

		$this->assertTrue(($green > $red and $green > $blue), 'au 10 janvier, le pré du plan est vert : #'.$result['grass']['2027-01-10']);

	}

	/**
	 * Toutes les herbes du plan sont de la même famille claire : la verte pâle suit sa table, la presque blanche garde sa couleur
	 * toute l'année, et les autres herbes ont chaque jour la teinte des tables partagées éclaircie comme la verte tendre.
	 */
	public function testPlanGrassesAreOneFamily(): void {

		$result = $this->run('
			const plan = new MapScenePlanSeason(THREE);
			const shared = new MapSceneSeason(THREE);
			const differ = {};
			const days = [];

			for(let time = Date.parse("2026-01-01"); time <= Date.parse("2026-12-31"); time += 86400000) {
				days.push(new Date(time).toISOString().slice(0, 10));
			}

			for(const grass of ["dry", "lush", "grazed"]) {

				plan.useGrass(grass);
				shared.setGrass(MapScene.GRASSES[grass]);

				differ[grass] = days.filter(date => {
					const [plain, light] = [read(plan, date).grass, MapScenePlanSeason.lighten("#"+ read(shared, date).grass).slice(1)];
					return [0, 2, 4].some(i => Math.abs(parseInt(plain.slice(i, i + 2), 16) - parseInt(light.slice(i, i + 2), 16)) > 1);
				});

			}

			plan.useGrass("pale");
			const pale = Object.fromEntries(' . json_encode(array_keys(self::PALE_GRASS)) . '.map(date => [date, read(plan, date).grass]));

			plan.useGrass("clear");
			const clear = [...new Set(days.map(date => read(plan, date).grass))];

			plan.useGrass("tender");

			emit({ differ, pale, clear, tender: read(plan, "2026-07-01").grass, paleColor: MapScene.GRASSES.pale.color });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		// Un niveau d'écart par canal au plus : l'arrivée de l'hiver (`at()`) mêle deux jours lus et déjà éclaircis
		$this->assertEquals(['dry' => [], 'lush' => [], 'grazed' => []], $result['differ'], 'une autre herbe a dans le plan, chaque jour, la teinte des tables partagées éclaircie de 25 %');
		$this->assertEquals(self::PALE_GRASS, $result['pale'], 'la verte pâle suit le dosage 2 aux jours de ses clés');
		$this->assertEquals('#'.self::PALE_GRASS['2026-07-01'], $result['paleColor'], 'GRASSES.pale.color, que reçoit setGrass() avant que la saison du plan ait posé ses tables, est le 1er juillet de la verte pâle dans le plan');
		$this->assertEquals([self::CLEAR_GRASS], $result['clear'], 'la presque blanche garde sa couleur toute l\'année');
		$this->assertEquals(self::PLAN_GRASS['2026-07-01'], $result['tender'], 'revenue à la verte tendre, la saison du plan reprend le pré par défaut');

	}

	/**
	 * Le terrain du client est celui du schéma : chaque herbe et chaque sol que `map\Configuration` enregistre a sa couleur dans la
	 * scène, et le défaut du schéma est la première clé de chaque table, que `readTerrain()` donne à une valeur inconnue. Une herbe
	 * retirée du schéma, comme la neige, retombe donc sur la verte tendre, et un sol inconnu sur le limon.
	 */
	public function testTerrainFollowsTheSchema(): void {

		$result = $this->run('
			emit({
				grasses: Object.keys(MapScene.GRASSES),
				soils: Object.keys(MapScene.SOILS),
				snow: MapScene.readTerrain({ grass: "snow", soil: "clay" }),
				unknown: MapScene.readTerrain({ grass: "lush", soil: "moss" })
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$mConfiguration = Configuration::model();

		$this->assertEquals($mConfiguration->getPropertyEnum('decorGrass'), $result['grasses'], 'les herbes de la scène sont celles de l\'enum decorGrass, dans son ordre');
		$this->assertEquals($mConfiguration->getPropertyEnum('decorSoil'), $result['soils'], 'les sols de la scène sont ceux de l\'enum decorSoil, dans son ordre');
		$this->assertEquals([$mConfiguration->getDefaultValue('decorGrass'), $mConfiguration->getDefaultValue('decorSoil')], [$result['grasses'][0], $result['soils'][0]], 'la première herbe et le premier sol de la scène sont les défauts du schéma');
		$this->assertEquals(['grass' => Configuration::TENDER, 'soil' => Configuration::CLAY], $result['snow'], 'un terrain enregistré en neige se peint en verte tendre et garde son sol');
		$this->assertEquals(['grass' => Configuration::LUSH, 'soil' => Configuration::LOAM], $result['unknown'], 'un sol inconnu se peint en limon et l\'herbe reste');

	}

	/**
	 * Les herbes du décor, telles que le plan les montre au 1er juillet, et les sols restent distincts deux à deux.
	 */
	public function testTerrainColorsStayApart(): void {

		$result = $this->run('
			emit({
				grasses: Object.fromEntries(Object.keys(MapScene.GRASSES).map(grass => [grass, MapScenePlanSeason.grassColor(grass)])),
				soils: MapScene.SOILS
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		foreach(['grasses' => self::DELTA_E_GRASS, 'soils' => self::DELTA_E_SOIL] as $table => $floor) {

			$close = [];
			$colors = $result[$table];

			foreach($colors as $first => $firstColor) {
				foreach($colors as $second => $secondColor) {
					if($first < $second and $this->deltaE($firstColor, $secondColor) < $floor) {
						$close[] = $first.'/'.$second.' '.round($this->deltaE($firstColor, $secondColor), 1);
					}
				}
			}

			$this->assertEquals([], $close, 'les '.$table.' du décor s\'écartent deux à deux d\'au moins '.$floor.' en CIE76');

		}

	}

	/**
	 * Une terre claire se perd sur le pré pâle : ses planches prennent un cadre, que `setTerrain()` allume par un uniform du
	 * nuanceur de la terre, et l'éteint sur une terre foncée.
	 */
	public function testLightSoilIsFramed(): void {

		$result = $this->run('
			const frame = { value: 0 };
			const material = { onBeforeCompile: null, customProgramCacheKey: null };

			MapScene.bedTexture(THREE, material, frame);

			const shader = { uniforms: {}, vertexShader: "#include <begin_vertex>", fragmentShader: "#include <clipping_planes_fragment>\n#include <color_fragment>" };
			material.onBeforeCompile(shader);

			const scene = {
				season: { useGrass: () => {} },
				grassStep: { value: 0 },
				bedFrame: frame,
				materials: { soil: new THREE.MeshToonMaterial(), soilGreenhouse: new THREE.MeshToonMaterial() }
			};

			const framed = {};

			for(const soil of Object.keys(MapScene.SOILS)) {
				MapScene.prototype.setTerrain.call(scene, { grass: "tender", soil });
				framed[soil] = frame.value;
			}

			emit({ framed, shared: shader.uniforms.bedFrame === frame, declared: shader.fragmentShader.startsWith("uniform float bedFrame;"), mixed: shader.fragmentShader.includes("bedFrame * (1. - smoothstep(") });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$expected = array_map(fn($soil) => in_array($soil, self::SOILS_FRAMED) ? 1 : 0, array_combine(array_keys($result['framed']), array_keys($result['framed'])));

		$this->assertEquals($expected, $result['framed'], 'le cadre s\'allume sur une terre claire et s\'éteint sur une terre foncée');
		$this->assertEquals(['shared' => TRUE, 'declared' => TRUE, 'mixed' => TRUE], ['shared' => $result['shared'], 'declared' => $result['declared'], 'mixed' => $result['mixed']], 'le nuanceur de la terre lit le cadre dans l\'objet que setTerrain() règle, et le cadre pèse sur le mélange de son liseré');

	}

	/**
	 * La vignette d'une herbe dans l'onglet Terrain du décor montre ce que le plan montre au 1er juillet : la verte tendre, cochée par
	 * défaut, le pré pâle du plan, et non la table partagée qu'il éclaircit. Les vignettes sont peintes par `showTerrain()` lui-même.
	 */
	public function testGrassTileShowsThePlanMeadow(): void {

		$result = $this->run('
			const plan = new MapScenePlanSeason(THREE);
			const fill = src => decodeURIComponent(src.slice("data:image/svg+xml,".length)).match(/fill="#([0-9a-f]{6})"/)[1];
			const paint = terrain => {

				const tiles = [...Object.keys(MapScene.GRASSES).map(grass => ({ grass })), { soil: "clay" }].map(dataset => {
					const img = { src: "" };
					return { dataset, img, qs: () => img, classList: { toggle: () => {} } };
				});
				let ground = null;

				MapSceneDecor.prototype.showTerrain.call({ terrain, terrainTiles: tiles, enclosure: { style: { setProperty: (name, value) => ground = value } } });

				return { tiles: Object.fromEntries(tiles.map(tile => [tile.dataset.grass ?? tile.dataset.soil, fill(tile.img.src)])), ground: ground.slice(1) };

			};

			const meadow = {};

			for(const grass of Object.keys(MapScene.GRASSES)) {
				plan.useGrass(grass);
				meadow[grass] = read(plan, "2026-07-01").grass;
			}

			emit({ meadow, tender: paint({ grass: "tender", soil: "clay" }), lush: paint({ grass: "lush", soil: "clay" }) });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals($result['meadow']['tender'], $result['tender']['tiles']['tender'], 'la vignette de la verte tendre a la teinte du pré du plan au 1er juillet');
		$this->assertEquals($result['meadow']['tender'], $result['tender']['ground'], 'sur la verte tendre, les clôtures du décor se montrent sur le pré du plan');
		$this->assertEquals($result['meadow']['tender'], $result['tender']['tiles']['clay'], 'sur la verte tendre, les planches d\'une vignette de sol se posent sur le pré du plan');

		// Une autre herbe a dans le plan la teinte de la table partagée, au rapport près : un niveau d'écart par canal au plus
		foreach(['pale', 'clear', 'dry', 'lush', 'grazed'] as $grass) {
			$tile = array_map('hexdec', str_split($result['tender']['tiles'][$grass], 2));
			$meadow = array_map('hexdec', str_split($result['meadow'][$grass], 2));
			$this->assertTrue(max(array_map(fn($a, $b) => abs($a - $b), $tile, $meadow)) <= 1, 'la vignette de l\'herbe '.$grass.' a la teinte qu\'elle prend dans le plan au 1er juillet');
		}

		$this->assertEquals($result['lush']['tiles']['lush'], $result['lush']['ground'], 'une herbe choisie donne aux clôtures le fond de sa propre vignette');

	}

	/**
	 * La scène du plan n'a pas de brouillard, ni à sa pose, ni quand le ciel d'une saison se peint, ni à chaque image : un voile
	 * pâlit le haut d'une vue inclinée et délave les arbres du fond, et le pré couvre déjà la vue la plus rasante au zoom minimal
	 * (`reach()`), sans bord à cacher.
	 */
	public function testPlanHasNoFog(): void {

		$result = $this->run('
			const scene = new THREE.Scene();
			const ambience = new MapSceneAmbience(THREE, scene, { center: null });
			const mounted = scene.fog;

			ambience.sky(new MapScenePlanSeason(THREE).at("2026-07-01").sky);
			ambience.frame(1000);
			ambience.frame(2000);

			emit({ mounted, framed: scene.fog });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(['mounted' => NULL, 'framed' => NULL], $result, 'la scène du plan se pose sans brouillard et n\'en reprend aucun en se peignant ni à chaque image');

	}

	/**
	 * L'avivage des cultures du plan (`MapScene.saturate()`) s'ajoute au nuanceur que la matière avait déjà, le balancement et la
	 * rafale, sans le remplacer, et sous une clé de programme à lui : three ne le partage pas avec la matière du bourg restée sans.
	 * Sa teinte se juge à l'écran, pas ici.
	 */
	public function testCropSaturationWrapsTheMaterial(): void {

		$result = $this->run('
			const material = new THREE.MeshToonMaterial({ vertexColors: true });
			let compiled = 0;

			material.onBeforeCompile = () => { compiled++; };
			material.customProgramCacheKey = () => "crop";

			MapScene.saturate(material);

			const fragment = "void main() {\n#include <color_fragment>\n}";
			const shader = { vertexShader: "", fragmentShader: fragment };

			material.onBeforeCompile(shader, null);

			emit({ compiled, key: material.customProgramCacheKey(), changed: shader.fragmentShader !== fragment, anchor: shader.fragmentShader.includes("#include <color_fragment>") });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(1, $result['compiled'], 'l\'avivage appelle une fois le nuanceur que la matière avait déjà');
		$this->assertEquals('crop-saturate', $result['key'], 'l\'avivage a sa propre clé de programme, dérivée de celle de la matière nue');
		$this->assertEquals(['changed' => TRUE, 'anchor' => TRUE], ['changed' => $result['changed'], 'anchor' => $result['anchor']], 'l\'avivage se pose après la couleur de la matière, qu\'il garde');

	}

	public function testAnotherGrassKeepsItsRatio(): void {

		$result = $this->run('
			const season = new MapSceneSeason(THREE);

			season.setGrass(MapScene.GRASSES.lush);
			const lush = ["2026-11-01", "2026-12-31", "2027-01-10"].map(date => read(season, date).grass);

			season.setGrass(MapScene.GRASSES.clear);
			const clear = ["2026-07-01", "2026-12-31", "2027-01-01"].map(date => read(season, date).grass);

			emit({ lush, clear, color: MapScene.GRASSES.clear.color });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals($result['lush'][0], $result['lush'][1], 'une herbe grasse garde au 31 décembre sa teinte du 1er novembre');
		$this->assertDiffer($result['lush'][0], $result['lush'][2], 'une herbe grasse a pris sa teinte d\'hiver au 10 janvier');
		$this->assertDiffer(self::PLATEAU['grass'], $result['lush'][0], 'une herbe grasse n\'a pas la teinte de la verte tendre');
		$this->assertEquals(array_fill(0, 3, substr($result['color'], 1)), $result['clear'], 'une herbe hors saison garde sa couleur toute l\'année');

	}

	public function testTreesKeepTheirYear(): void {

		$result = $this->run('
			const season = new MapSceneSeason(THREE);
			const uniforms = { decorAutumn: { value: new THREE.Vector4() }, decorSpring: { value: 0 }, decorFoliage: { value: new THREE.Color() }, decorHedge: { value: new THREE.Color() }, decorPath: { value: new THREE.Color() } };
			const decor = { THREE, seasonUniforms: uniforms, changed: () => {} };

			emit(Object.fromEntries(["2026-11-01", "2026-11-15", "2026-12-31"].map(date => {

				MapSceneDecor.prototype.season.call(decor, date, season.at(date).path);

				return [date, { turn: decor.turn, spring: decor.spring, autumn: new THREE.Color(...uniforms.decorAutumn.value.toArray().slice(0, 3)).getHexString(), path: uniforms.decorPath.value.getHexString() }];

			})));
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals([31, 45, 91], array_column($result, 'turn'), 'le virage des feuilles se compte en jours depuis le 1er octobre, sans plafond');
		$this->assertEquals([14, 60], [$result['2026-11-15']['spring'] - $result['2026-11-01']['spring'], $result['2026-12-31']['spring'] - $result['2026-11-01']['spring']], 'la pousse des feuilles se compte sur le jour montré, sans plafond');
		$this->assertDiffer($result['2026-11-01']['autumn'], $result['2026-11-15']['autumn'], 'la teinte d\'automne continue de virer après le 1er novembre');
		$this->assertEquals(array_fill(0, 3, $result['2026-11-01']['path']), array_column($result, 'path'), 'le chemin du décor garde sa teinte du 1er novembre');

	}

	public function testHedgeTurnsGreyFromJanuary(): void {

		$result = $this->run('
			const season = new MapSceneSeason(THREE);
			emit({
				summer: new THREE.Color(MapSceneDecor.HEDGE_SUMMER).getHexString(),
				winter: new THREE.Color(MapSceneDecor.HEDGE_WINTER).getHexString(),
				days: Object.fromEntries(["2026-12-31", "2027-01-01", "2027-01-08", "2027-01-15", "2027-01-31"].map(date => [date, hedge(season, date)]))
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$days = $result['days'];

		$this->assertEquals($result['summer'], $days['2026-12-31'], 'au 31 décembre, la haie a sa teinte d\'été');
		$this->assertEquals($result['summer'], $days['2027-01-01'], 'au 1er janvier, la haie a encore sa teinte du 31 décembre');
		$this->assertTrue(($days['2027-01-08'] !== $result['summer'] and $days['2027-01-08'] !== $result['winter']), 'au 8 janvier, la haie est en chemin vers sa teinte d\'hiver : '.$days['2027-01-08']);
		$this->assertEquals([$result['winter'], $result['winter']], [$days['2027-01-15'], $days['2027-01-31']], 'du 15 janvier à la fin du mois, la haie a sa teinte d\'hiver');

	}

	/**
	 * Aucun jour ne saute, ni au bourg ni au plan : du premier au dernier jour du plan 2026, semaines 1 à 53, puis sur l'année bissextile 2028 entière, les
	 * teintes du pré, du ciel, du soleil, du chemin et de la haie, la force et la hauteur du soleil et l'appoint du ciel ne bougent
	 * d'un jour au suivant que sous leur plafond.
	 */
	public function testNoDayJumps(): void {

		$result = $this->run('
			const plan = new MapScenePlanSeason(THREE);
			const pale = new MapScenePlanSeason(THREE);
			pale.useGrass("pale");
			const levels = hex => [0, 2, 4].map(i => parseInt(hex.slice(i, i + 2), 16));
			const steps = {};

			for(const season of [new MapSceneSeason(THREE), plan, pale]) {

				for(const [from, to] of [["2025-12-29", "2027-01-03"], ["2028-01-01", "2029-01-01"]]) {

					let previous = null;

					for(let time = Date.parse(from); time <= Date.parse(to); time += 86400000) {

						const date = new Date(time).toISOString().slice(0, 10);
						const current = { ...read(season, date), hedge: hedge(season, date) };

						if(previous !== null) {

							for(const [name, value] of Object.entries(current)) {

								const step = (typeof value === "number") ?
									Math.round(Math.abs(value - previous[name]) * 1000) / 1000 :
									Math.max(...levels(value).map((x, j) => Math.abs(x - levels(previous[name])[j])));

								if(steps[name] === undefined || step > steps[name].step) {
									steps[name] = { step, date };
								}

							}

						}

						previous = current;

					}

				}

			}

			emit(steps);
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$caps = ['grass' => self::STEP_COLOR, 'sky' => self::STEP_COLOR, 'sun' => self::STEP_COLOR, 'path' => self::STEP_COLOR, 'hedge' => self::STEP_COLOR, 'force' => self::STEP_FORCE, 'fill' => self::STEP_FILL, 'height' => self::STEP_HEIGHT];

		foreach($caps as $name => $cap) {
			$this->assertTrue($result[$name]['step'] <= $cap, $name.' ne bouge pas de plus de '.$cap.' d\'un jour au suivant, il bouge de '.$result[$name]['step'].' en arrivant au '.$result[$name]['date']);
		}

	}

	/**
	 * L'écart CIE76 entre deux couleurs `#rrggbb`, sous l'illuminant D65.
	 */
	private function deltaE(string $first, string $second): float {

		$lab = function(string $color): array {

			$linear = fn(float $channel) => ($channel <= 0.04045) ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
			[$red, $green, $blue] = array_map(fn($hex) => $linear(hexdec($hex) / 255), str_split(ltrim($color, '#'), 2));

			$f = fn(float $t) => ($t > 0.008856) ? $t ** (1 / 3) : 7.787 * $t + 16 / 116;
			$x = $f(($red * 0.4124 + $green * 0.3576 + $blue * 0.1805) / 0.95047);
			$y = $f($red * 0.2126 + $green * 0.7152 + $blue * 0.0722);
			$z = $f(($red * 0.0193 + $green * 0.1192 + $blue * 0.9505) / 1.08883);

			return [116 * $y - 16, 500 * ($x - $y), 200 * ($y - $z)];

		};

		return sqrt(array_sum(array_map(fn($a, $b) => ($a - $b) ** 2, $lab($first), $lab($second))));

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et rend ce qu'il émet.
	 * `read(season, date)` rend ce que `MapSceneSeason.at()` lit ce jour-là : les couleurs en hexadécimal, les nombres au millième.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				// La toile du ciel (`MapSceneAmbience.paint()`) se peint sans navigateur : chaque appel au contexte ne fait rien
				const canvas = () => ({ getContext: () => ({ createLinearGradient: () => ({ addColorStop: () => {} }), fillRect: () => {} }) });
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {}, createElement: canvas }, console });
				const { MapScene, MapSceneSeason, MapScenePlanSeason, MapSceneDecor, MapSceneAmbience } = vm.runInContext(source + "\n;({ MapScene, MapSceneSeason, MapScenePlanSeason, MapSceneDecor, MapSceneAmbience })", context);

				const hex = rgb => new THREE.Color().setRGB(...rgb).getHexString();
				const round = value => Math.round(value * 1000) / 1000;

				const read = (season, date) => {

					const light = season.at(date);

					return { grass: hex(light.grass), sky: hex(light.sky), sun: hex(light.sun), path: hex(light.path), force: round(light.force), fill: round(light.fill), height: round(light.offset.y / 150) };

				};

				// `hedge(season, date)` rend la teinte de la haie ce jour-là, telle que `MapSceneDecor.season()` la pose en rapport à son vert d\'été
				const hedge = (season, date) => {

					const uniforms = { decorAutumn: { value: new THREE.Vector4() }, decorSpring: { value: 0 }, decorFoliage: { value: new THREE.Color() }, decorHedge: { value: new THREE.Color() }, decorPath: { value: new THREE.Color() } };

					MapSceneDecor.prototype.season.call({ THREE, seasonUniforms: uniforms, changed: () => {} }, date, season.at(date).path);

					const summer = new THREE.Color(MapSceneDecor.HEDGE_SUMMER);
					const ratio = uniforms.decorHedge.value;

					return hex([ratio.r * summer.r, ratio.g * summer.g, ratio.b * summer.b]);

				};

				'.$scenario.'
			})();
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve la scène sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return [];
		}

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? [];

	}

}
