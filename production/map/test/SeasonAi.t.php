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

	// Un jardin sans planche, trapèze de 40 m sur 10 m (300 m²) dont le côté droit est oblique, dans son rectangle le long de x
	const GARDEN = '
		const points = [{ x: 0, n: 0 }, { x: 40, n: 0 }, { x: 20, n: 10 }, { x: 0, n: 10 }];
		const r = { origin: { x: 0, n: 0 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, length: 40, width: 10 };
	';

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
	 * toute l'année, et la sèche a chaque jour la teinte des tables partagées éclaircie comme la verte tendre.
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

			for(const grass of ["dry"]) {

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
		$this->assertEquals(['dry' => []], $result['differ'], 'la sèche a dans le plan, chaque jour, la teinte des tables partagées éclaircie de 25 %');
		$this->assertEquals(self::PALE_GRASS, $result['pale'], 'la verte pâle suit le dosage 2 aux jours de ses clés');
		$this->assertEquals('#'.self::PALE_GRASS['2026-07-01'], $result['paleColor'], 'GRASSES.pale.color, que reçoit setGrass() avant que la saison du plan ait posé ses tables, est le 1er juillet de la verte pâle dans le plan');
		$this->assertEquals([self::CLEAR_GRASS], $result['clear'], 'la presque blanche garde sa couleur toute l\'année');
		$this->assertEquals(self::PLAN_GRASS['2026-07-01'], $result['tender'], 'revenue à la verte tendre, la saison du plan reprend le pré par défaut');

	}

	/**
	 * La verte vive et la verte lumineuse dérivent de la verte tendre, clé par clé : aux mêmes mois, chacune plus claire et plus
	 * saturée qu'elle en Lab, la lumineuse plus claire que la vive et la vive plus saturée que la lumineuse, pour qu'aucune des trois
	 * ne se confonde avec une autre. Leur couleur, que reçoit setGrass() avant les tables, est leur 1er juillet.
	 */
	public function testVividAndBrightDeriveFromTender(): void {

		$result = $this->run('
			emit({ tables: MapScenePlanSeason.TABLES, colors: { lush: MapScene.GRASSES.lush.color, grazed: MapScene.GRASSES.grazed.color } });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$tables = $result['tables'];
		$chroma = fn(array $lab) => hypot($lab[1], $lab[2]);
		$colorsOf = fn(array $table) => array_merge([$table['winter']], array_column($table['keys'], 1));
		$wrong = [];

		$this->assertEquals(array_column($tables['tender']['keys'], 0), array_column($tables['lush']['keys'], 0), 'la verte vive a les mois de la verte tendre');
		$this->assertEquals(array_column($tables['tender']['keys'], 0), array_column($tables['grazed']['keys'], 0), 'la verte lumineuse a les mois de la verte tendre');

		foreach(array_map(NULL, $colorsOf($tables['tender']), $colorsOf($tables['lush']), $colorsOf($tables['grazed'])) as [$tender, $lush, $grazed]) {

			[$labTender, $labLush, $labGrazed] = array_map(fn($color) => $this->lab($color), [$tender, $lush, $grazed]);

			if(($labLush[0] > $labTender[0] and $chroma($labLush) > $chroma($labTender) and $labGrazed[0] > $labLush[0] and $chroma($labGrazed) > $chroma($labTender) and $chroma($labLush) > $chroma($labGrazed)) === FALSE) {
				$wrong[] = $tender.' '.$lush.' '.$grazed;
			}

		}

		$this->assertEquals([], $wrong, 'à chaque clé, la vive et la lumineuse sont plus claires et plus saturées que la verte tendre');

		foreach(['lush', 'grazed'] as $grass) {
			$july = array_values(array_filter($tables[$grass]['keys'], fn(array $key) => $key[0] === 6))[0][1];
			$this->assertEquals($july, $result['colors'][$grass], 'GRASSES.'.$grass.'.color est son 1er juillet dans le plan');
		}

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
				meadows: Object.keys(MapScene.MEADOWS),
				snow: MapScene.readTerrain({ grass: "snow", soil: "clay", meadow: "flowery" }),
				unknown: MapScene.readTerrain({ grass: "lush", soil: "moss", meadow: "moor" })
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$mConfiguration = Configuration::model();

		$this->assertEquals($mConfiguration->getPropertyEnum('decorGrass'), $result['grasses'], 'les herbes de la scène sont celles de l\'enum decorGrass, dans son ordre');
		$this->assertEquals($mConfiguration->getPropertyEnum('decorSoil'), $result['soils'], 'les sols de la scène sont ceux de l\'enum decorSoil, dans son ordre');
		$this->assertEquals([$mConfiguration->getDefaultValue('decorGrass'), $mConfiguration->getDefaultValue('decorSoil')], [$result['grasses'][0], $result['soils'][0]], 'la première herbe et le premier sol de la scène sont les défauts du schéma');
		$this->assertEquals(['grass' => Configuration::TENDER, 'soil' => Configuration::CLAY, 'meadow' => Configuration::FLOWERY], $result['snow'], 'un terrain enregistré en neige se peint en verte tendre et garde son sol et son pré');
		$this->assertEquals(['grass' => Configuration::LUSH, 'soil' => Configuration::LOAM, 'meadow' => Configuration::MIXED], $result['unknown'], 'un sol et un pré inconnus prennent les défauts du schéma, et l\'herbe reste');
		$this->assertEquals($mConfiguration->getPropertyEnum('decorMeadow'), $result['meadows'], 'les prés de la scène sont ceux de l\'enum decorMeadow, dans son ordre');
		$this->assertEquals($mConfiguration->getDefaultValue('decorMeadow'), $result['meadows'][0], 'le premier pré de la scène est le défaut du schéma');

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

			emit({ framed, shared: shader.uniforms.bedFrame === frame, declared: shader.fragmentShader.startsWith("uniform float bedFrame;"), mixed: shader.fragmentShader.includes("bFramed * (1. - smoothstep(") && shader.fragmentShader.includes(": bedFrame;") });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$expected = array_map(fn($soil) => in_array($soil, self::SOILS_FRAMED) ? 1 : 0, array_combine(array_keys($result['framed']), array_keys($result['framed'])));

		$this->assertEquals($expected, $result['framed'], 'le cadre s\'allume sur une terre claire et s\'éteint sur une terre foncée');
		$this->assertEquals(['shared' => TRUE, 'declared' => TRUE, 'mixed' => TRUE], ['shared' => $result['shared'], 'declared' => $result['declared'], 'mixed' => $result['mixed']], 'le nuanceur de la terre lit le cadre dans l\'objet que setTerrain() règle, et le cadre pèse sur le mélange de son liseré');

	}

	/**
	 * Une parcelle qui a son sol le porte dans l'attribut `bedSoil` de ses planches : sa couleur, et le cadre que ce sol prend ou non,
	 * à la place de ceux de la structure. Le changer réécrit la plage de ses planches dans la terre fusionnée sans toucher la voisine,
	 * et ses buttes prennent sa couleur dans le rapport à la terre de la structure. Un sol inconnu reprend celui de la structure.
	 */
	public function testZoneSoilRidesTheSlab(): void {

		$result = $this->run('
			const thousandth = list => Array.from(list, v => Math.round(v * 1000) / 1000);
			const bed = { id: 1, origin: { x: 0, n: 0 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, length: 4, width: 1 };
			const slab = soil => MapScene.bed(THREE, null, bed, [{ x: 0, n: 0 }, { x: 4, n: 0 }, { x: 4, n: 1 }, { x: 0, n: 1 }], soil).attributes.bedSoil;
			const humus = new THREE.Color(MapScene.SOILS.humus);

			const attribute = new THREE.BufferAttribute(new Float32Array(32), 4);
			const mesh = { geometry: { attributes: { bedSoil: attribute } } };
			const painted = [];
			const field = {
				THREE,
				soils: new Map(),
				geometry: { beds: [{ id: 1, zone: 5 }, { id: 2, zone: 6 }] },
				slabs: new Map([[1, { mesh, start: 0, count: 4 }], [2, { mesh, start: 4, count: 4 }]]),
				crops: { paintSoil: (zone, soil) => painted.push([zone, soil]) }
			};
			MapSceneField.prototype.paintSoil.call(field, 5, "loam");

			const crops = { THREE, soils: new Map([[5, "humus"]]), ridgeMaterial: { color: new THREE.Color(MapScene.SOILS.clay) }, ridgeOpen: new THREE.Color("#ffffff"), ridgeShade: new THREE.Color("#ffffff").multiplyScalar(MapScene.GREENHOUSE_SHADE) };
			// La teinte d\'instance multiplie la couleur de la matière, et leur produit est ce qui se voit
			const ridge = r => thousandth(MapSceneCrops.prototype.ridgeColor.call(crops, r).multiply(crops.ridgeMaterial.color).toArray());

			const material = { onBeforeCompile: null, customProgramCacheKey: null };
			MapScene.bedTexture(THREE, material, { value: 0 }, MapScene.GREENHOUSE_SHADE);
			const shader = { uniforms: {}, vertexShader: "#include <begin_vertex>", fragmentShader: "#include <clipping_planes_fragment>\n#include <color_fragment>" };
			material.onBeforeCompile(shader);

			// Ce qui se voit d\'une terre à soi : la matière de la planche, que le nuanceur ramène au sol de la parcelle et à l\'ombre
			// de son abri, ou la copie de ruine, de matière blanche, qui porte sa teinte de sommet
			const clay = new THREE.Color(MapScene.SOILS.clay);
			const scene = { crops: { THREE }, materials: { soil: { color: clay.clone() }, soilGreenhouse: { color: clay.clone().multiplyScalar(MapScene.GREENHOUSE_SHADE) } } };
			const seen = shade => thousandth(humus.clone().multiplyScalar(shade).toArray());
			const ruin = (material, soil) => thousandth(MapScene.prototype.ruinSoil.call(scene, material, soil).multiply(soil === null ? new THREE.Color("#ffffff") : humus).toArray());
			const plan = { zones: [{ id: 5, decorSoil: "humus" }, { id: 6, decorSoil: null }] };

			emit({
				slabSeen: seen(1),
				ruinSeen: ruin(scene.materials.soil, "humus"),
				slabSeenSheltered: seen(MapScene.GREENHOUSE_SHADE),
				ruinSeenSheltered: ruin(scene.materials.soilGreenhouse, "humus"),
				ruinCommon: ruin(scene.materials.soil, null),
				clay: thousandth(clay.toArray()),
				planSoils: Array.from(MapScene.zoneSoils(plan, true)),
				townSoils: MapScene.zoneSoils(plan, false).size,
				none: thousandth(slab(null).array.slice(0, 4)),
				humus: thousandth(slab("humus").array.slice(0, 4)),
				loam: slab("loam").array[3],
				every: slab("humus").count === slab(null).count,
				humusColor: thousandth([humus.r, humus.g, humus.b]).concat([1]),
				unknown: MapScene.readSoil("lave"),
				known: MapScene.readSoil("clay"),
				zone: thousandth(attribute.array.slice(0, 16)).filter((v, i) => i % 4 === 3),
				neighbour: thousandth(attribute.array.slice(16, 32)).filter(v => v !== 0).length,
				painted,
				kept: field.soils.get(5),
				ridgeOwn: ridge({ zone: 5, greenhouse: false }),
				ridgeShade: ridge({ zone: 5, greenhouse: true }),
				ridgeCommon: ridge({ zone: 6, greenhouse: false }),
				humusRidge: thousandth([humus.r, humus.g, humus.b]),
				clayRidge: thousandth(crops.ridgeMaterial.color.toArray()),
				shade: shader.uniforms.bedShade.value,
				greenhouse: MapScene.GREENHOUSE_SHADE
			});
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals([0, 0, 0, 0], $result['none'], 'une planche sans sol à elle suit la terre de la structure');
		$this->assertEquals($result['humusColor'], $result['humus'], 'une planche d\'une parcelle en humus porte sa couleur, sans cadre');
		$this->assertEquals(2, $result['loam'], 'une parcelle en limon, terre claire, prend le cadre');
		$this->assertTrue($result['every'], 'chaque sommet de la planche porte le sol');
		$this->assertEquals([NULL, 'clay'], [$result['unknown'], $result['known']], 'un sol inconnu reprend celui de la structure');
		$this->assertEquals([2, 2, 2, 2], $result['zone'], 'changer le sol de la parcelle réécrit la plage de ses planches');
		$this->assertEquals(0, $result['neighbour'], 'la planche d\'une autre parcelle n\'est pas touchée');
		$this->assertEquals([[5, 'loam']], $result['painted'], 'les cultures reprennent le sol de la parcelle');
		$this->assertEquals('loam', $result['kept'], 'le champ retient le sol de la parcelle pour ses planches à venir');
		$this->assertEquals($result['humusRidge'], $result['ridgeOwn'], 'la butte d\'une parcelle en humus prend sa couleur');
		$this->assertDiffer($result['ridgeOwn'], $result['ridgeShade'], 'sous abri, la butte garde l\'ombre de l\'abri');
		$this->assertEquals($result['clayRidge'], $result['ridgeCommon'], 'la butte d\'une parcelle sans sol à elle garde la terre de la structure');
		$this->assertEquals($result['greenhouse'], $result['shade'], 'la terre sous abri garde l\'ombre de l\'abri sur le sol de sa parcelle');
		$this->assertEquals($result['slabSeen'], $result['ruinSeen'], 'la copie de ruine d\'une planche à sol propre a la couleur de la planche');
		$this->assertEquals($result['slabSeenSheltered'], $result['ruinSeenSheltered'], 'sous abri, la copie de ruine garde la couleur de la planche, ombre de l\'abri comprise');
		$this->assertEquals($result['clay'], $result['ruinCommon'], 'la copie de ruine d\'une planche sans sol à elle garde la terre de la structure');
		$this->assertEquals([[5, 'humus'], [6, NULL]], $result['planSoils'], 'le plan lit le sol de chaque parcelle');
		$this->assertEquals(0, $result['townSoils'], 'le bourg ne lit pas le sol des parcelles, toute sa terre est la sienne');

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

	/**
	 * Chaque herbe d'une parcelle (`grass.<herbe>`) est celle que la structure montrerait si elle l'avait choisie, quelle que soit
	 * l'herbe de la structure : une table, une herbe hors saison, la sèche éclaircie, avant, pendant et après l'arrivée de l'hiver.
	 */
	public function testZoneGrassIsReadByItsName(): void {

		$result = $this->run('
			const dates = ["2026-01-04", "2026-04-01", "2026-07-01", "2026-10-15", "2026-12-31"];
			const names = Object.keys(MapScene.GRASSES);
			const alone = Object.fromEntries(names.map(grass => {
				const season = new MapScenePlanSeason(THREE);
				season.useGrass(grass);
				return [grass, dates.map(date => hex(season.at(date).grass))];
			}));
			const under = Object.fromEntries(["tender", "dry", "lush", "clear"].map(structure => {
				const season = new MapScenePlanSeason(THREE);
				season.useGrass(structure);
				return [structure, Object.fromEntries(names.map(grass => [grass, dates.map(date => hex(season.at(date)["grass." + grass]))]))];
			}));
			emit({ alone, under });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		foreach($result['under'] as $structure => $grasses) {
			$this->assertEquals($result['alone'], $grasses, 'sous une structure en herbe '.$structure.', chaque herbe de parcelle a les teintes qu\'elle aurait si la structure l\'avait choisie');
		}

	}

	/**
	 * Le rapport de chaque herbe de parcelle à celle de la structure (`MapScene.grassRatios()`), que le nuanceur applique à la
	 * couleur de la matière, rend la teinte de cette herbe ; le rang 0, la structure, reste à 1, et chaque rang garde l'écart des
	 * aplats de son herbe.
	 */
	public function testGrassRatiosRebuildTheZoneGrass(): void {

		$result = $this->run('
			const season = new MapScenePlanSeason(THREE);
			season.useGrass("lush");
			const light = season.at("2026-05-10");
			const zones = MapScene.grassZones(THREE);
			MapScene.grassRatios(zones, light);
			const names = Object.keys(MapScene.GRASSES);
			const rebuilt = Object.fromEntries(names.map((grass, k) => {
				const ratio = zones.grassRatio.value[k + 1];
				return [grass, hex([light.grass[0] * ratio.x, light.grass[1] * ratio.y, light.grass[2] * ratio.z])];
			}));
			const expected = Object.fromEntries(names.map(grass => [grass, hex(light["grass." + grass])]));
			emit({ rebuilt, expected, structure: zones.grassRatio.value[0].toArray(), steps: zones.grassSteps.value, schema: names.map(grass => MapScene.GRASSES[grass].step) });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals($result['expected'], $result['rebuilt'], 'la couleur de la matière multipliée par le rapport rend l\'herbe de chaque parcelle');
		$this->assertEquals([1, 1, 1], $result['structure'], 'hors parcelle, le rapport laisse l\'herbe de la structure');
		$this->assertEquals(array_merge([0], $result['schema']), $result['steps'], 'chaque rang porte l\'écart des aplats de son herbe');

	}

	/**
	 * L'herbe tachetée du bourg, qui ne passe pas les herbes des parcelles, garde son nuanceur et sa clé de programme ; celle du plan
	 * déclare le masque et prend sa clé à elle, que le pré prolonge.
	 */
	public function testTownGrassShaderIsUnchanged(): void {

		$result = $this->run('
			const compile = zones => {
				const material = new THREE.MeshToonMaterial();
				MapScene.grassTexture(material, { value: .045 }, zones);
				const shader = { uniforms: {}, vertexShader: "#include <project_vertex>", fragmentShader: "void main() {\n#include <color_fragment>\n}" };
				material.onBeforeCompile(shader);
				const key = material.customProgramCacheKey();
				MapScene.meadowTexture(material, {});
				return { key, meadow: material.customProgramCacheKey(), mask: shader.fragmentShader.includes("grassMask"), uniforms: Object.keys(shader.uniforms).sort() };
			};
			emit({ town: compile(null), plan: compile(MapScene.grassZones(THREE)) });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals(['key' => 'map-scene-grass', 'meadow' => 'map-scene-grass-meadow', 'mask' => FALSE, 'uniforms' => ['grassStep']], $result['town'], 'le bourg garde le nuanceur et la clé de l\'herbe sans parcelles');
		$this->assertEquals(['key' => 'map-scene-grass-zones', 'meadow' => 'map-scene-grass-zones-meadow', 'mask' => TRUE, 'uniforms' => ['grassBox', 'grassMask', 'grassRatio', 'grassStep', 'grassSteps']], $result['plan'], 'le plan déclare les herbes des parcelles sous sa propre clé, que le pré prolonge');

	}

	/**
	 * La glissade d'une semaine à l'autre porte les rapports des herbes des parcelles avec la couleur de la structure : la photo de
	 * départ les copie, et le fondu les mène de l'une à l'autre comme il mène la couleur. Le trait entre deux ilots, fait de
	 * l'herbe sous lui, lit le même masque.
	 */
	public function testZoneGrassGlidesWithTheStructure(): void {

		$result = $this->run('
			const zones = MapScene.grassZones(THREE);
			const lights = { sun: new THREE.DirectionalLight(), hemisphere: new THREE.HemisphereLight(), ground: new THREE.MeshToonMaterial(), fold: new THREE.MeshToonMaterial(), grassRatio: zones.grassRatio.value };
			const owner = { top: new THREE.Color(), horizon: new THREE.Color(), paint: () => {} };
			const state = MapSceneAmbience.prototype.state;
			const blend = MapSceneAmbience.prototype.blend;
			const from = state.call(owner, lights);
			zones.grassRatio.value[4].set(2, .5, 1);
			const to = state.call(owner, lights);
			blend.call(owner, from, to, .5, lights);
			const middle = zones.grassRatio.value[4].toArray();
			const material = new THREE.MeshToonMaterial();
			MapScene.foldTexture(material, zones);
			const shader = { uniforms: {}, vertexShader: "#include <project_vertex>", fragmentShader: "void main() {\n#include <color_fragment>\n}" };
			material.onBeforeCompile(shader);
			emit({ copied: from.grassRatio[4].toArray(), middle, fold: { key: material.customProgramCacheKey(), mask: shader.fragmentShader.includes("diffuseColor.rgb *= grassRatio[gRank]"), uniforms: Object.keys(shader.uniforms).sort() } });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals([1, 1, 1], $result['copied'], 'la photo de départ de la glissade copie les rapports au lieu de les suivre');
		$this->assertEquals([1.5, .75, 1], $result['middle'], 'à mi-glissade, le rapport d\'une herbe de parcelle est à mi-chemin');
		$this->assertEquals(['key' => 'map-scene-fold-zones', 'mask' => TRUE, 'uniforms' => ['grassBox', 'grassMask', 'grassRatio', 'grassSteps']], $result['fold'], 'le trait entre deux ilots prend le rapport de l\'herbe de la parcelle qu\'il traverse');

	}

	public function testAnotherGrassKeepsItsRatio(): void {

		$result = $this->run('
			const season = new MapSceneSeason(THREE);

			season.setGrass(MapScene.GRASSES.dry);
			const dry = ["2026-11-01", "2026-12-31", "2027-01-10"].map(date => read(season, date).grass);

			season.setGrass(MapScene.GRASSES.clear);
			const clear = ["2026-07-01", "2026-12-31", "2027-01-01"].map(date => read(season, date).grass);

			emit({ dry, clear, color: MapScene.GRASSES.clear.color });
		');

		if($this->assertDecorPresent($result !== [], 'les valeurs lues par node') === FALSE) {
			return;
		}

		$this->assertEquals($result['dry'][0], $result['dry'][1], 'une herbe sèche garde au 31 décembre sa teinte du 1er novembre');
		$this->assertDiffer($result['dry'][0], $result['dry'][2], 'une herbe sèche a pris sa teinte d\'hiver au 10 janvier');
		$this->assertDiffer(self::PLATEAU['grass'], $result['dry'][0], 'une herbe sèche n\'a pas la teinte de la verte tendre');
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
	 * Un jardin sans planche dessinée porte ses places en bandes (`MapSceneCrops.bands()`) : chacune a la surface de sa place dans le
	 * contour, à 1 % près, et deux places debout ensemble ne se recouvrent pas. Le jardin est un trapèze de 40 m sur 10 m, posé dans
	 * son rectangle, dont un côté est oblique : la longueur d'une bande ne se déduit pas de sa surface par une division.
	 */
	public function testWholeGardenBandsHoldTheirArea(): void {

		$result = $this->run(self::GARDEN.'
			const bands = MapSceneCrops.bands(r, points, [
				{ id: 1, area: 60, start: "2026-03-01", end: "2026-07-31" },
				{ id: 2, area: 120, start: "2026-04-01", end: "2026-10-31" },
				{ id: 3, area: 45, start: "2026-08-15", end: "2026-11-30" },
				{ id: 4, area: 30, start: null, end: null }
			]);
			emit(Object.fromEntries([...bands].map(([id, band]) => [id, { from: band.from, to: band.to, area: band.area, inside: band.outline.every(p => ScenePlanGeometry.inside({ x: p.x - 1e-6 * Math.sign(p.x - 20), n: p.n - 1e-6 * Math.sign(p.n - 5) }, points)) }])));
		');

		if($this->assertDecorPresent($result !== [], 'les bandes lues par node') === FALSE) {
			return;
		}

		foreach([1 => 60, 2 => 120, 3 => 45] as $id => $area) {
			$this->assertTrue(abs($result[$id]['area'] - $area) <= $area * 0.01, 'la bande de la place '.$id.' a sa surface dans le contour : '.$result[$id]['area'].' m² pour '.$area);
			$this->assertTrue($result[$id]['inside'], 'la terre de la place '.$id.' reste dans le contour');
		}

		$this->assertFalse(isset($result[4]), 'une place jamais en terre ne prend aucune bande');
		$this->assertTrue($result[2]['from'] >= $result[1]['to'] - 0.001, 'deux places debout ensemble tiennent deux bandes distinctes');
		$this->assertEquals(0, $result[3]['from'], 'une place qui entre après une place finie reprend sa longueur libre, au début du jardin');

	}

	/**
	 * Une bande ne bouge pas de la saison : la place 2 garde la même longueur du jardin quand sa voisine 1 est arrachée deux mois plus
	 * tôt, et quand une troisième place entre après elle.
	 */
	public function testWholeGardenBandDoesNotMove(): void {

		$result = $this->run(self::GARDEN.'
			const band = places => {
				const { from, to } = MapSceneCrops.bands(r, points, places).get(2);
				return [from, to];
			};
			emit({
				planned: band([{ id: 1, area: 60, start: "2026-03-01", end: "2026-07-31" }, { id: 2, area: 120, start: "2026-04-01", end: "2026-10-31" }]),
				pulled: band([{ id: 1, area: 60, start: "2026-03-01", end: "2026-05-15" }, { id: 2, area: 120, start: "2026-04-01", end: "2026-10-31" }]),
				joined: band([{ id: 1, area: 60, start: "2026-03-01", end: "2026-07-31" }, { id: 2, area: 120, start: "2026-04-01", end: "2026-10-31" }, { id: 3, area: 80, start: "2026-09-01", end: null }])
			});
		');

		if($this->assertDecorPresent($result !== [], 'les bandes lues par node') === FALSE) {
			return;
		}

		$this->assertEquals($result['planned'], $result['pulled'], 'une voisine arrachée plus tôt ne déplace pas la bande');
		$this->assertEquals($result['planned'], $result['joined'], 'une place qui entre plus tard ne déplace pas la bande');

	}

	/**
	 * Des places debout ensemble qui demandent plus que le jardin s'y ramènent au prorata de leur surface, et une place sans surface
	 * prend ce que les autres laissent.
	 */
	public function testWholeGardenBandsShareTheGarden(): void {

		$result = $this->run(self::GARDEN.'
			const total = ScenePlanGeometry.area(points);
			const area = places => [...MapSceneCrops.bands(r, points, places).values()].map(band => band.area / total);
			emit({
				crowded: area([{ id: 1, area: total, start: "2026-03-01", end: "2026-10-31" }, { id: 2, area: total / 2, start: "2026-03-02", end: "2026-10-31" }]),
				unknown: area([{ id: 1, area: total / 4, start: "2026-03-01", end: "2026-10-31" }, { id: 2, area: null, start: "2026-03-02", end: "2026-10-31" }])
			});
		');

		if($this->assertDecorPresent($result !== [], 'les bandes lues par node') === FALSE) {
			return;
		}

		$this->assertEquals([0.667, 0.333], array_map(fn($share) => round($share, 3), $result['crowded']), 'deux places qui demandent une fois et demie le jardin en prennent les deux tiers et le tiers');
		$this->assertEquals([0.25, 0.75], array_map(fn($share) => round($share, 2), $result['unknown']), 'une place sans surface prend les trois quarts que l\'autre laisse');

	}

	/**
	 * Les 150 pieds d'un jardin entier (`MapSceneCrops.share()`) se partagent entre deux séries au prorata de leur bande, et une
	 * bande minuscule en garde un.
	 */
	public function testWholeGardenSeriesSharePlants(): void {

		$result = $this->run('
			emit({
				two: MapSceneCrops.share([100, 50]),
				alone: MapSceneCrops.share([300]),
				tiny: MapSceneCrops.share([1000, 0.1])
			});
		');

		if($this->assertDecorPresent($result !== [], 'les pieds lus par node') === FALSE) {
			return;
		}

		$this->assertEquals([100, 50], $result['two'], 'deux séries se partagent les 150 pieds au prorata de leur bande');
		$this->assertEquals([150], $result['alone'], 'une série seule garde les 150 pieds');
		$this->assertEquals([150, 1], $result['tiny'], 'une bande minuscule garde un pied');

	}

	/**
	 * Un jardin dont toutes les places de l'année montrée portent une série pérenne reste en herbe (`MapSceneCrops.grassed()`) ; un
	 * jardin mixte, un jardin de pérennes où une annuelle de la saison passée tient encore une planche, un jardin de pérennes sous
	 * abri, un jardin d'annuelles et un jardin sans place gardent leur terre.
	 */
	public function testPerennialGardenStaysGrass(): void {

		$result = $this->run('
			const plan = {
				beds: [{ id: 11, plot: 1 }, { id: 12, plot: 1 }, { id: 21, plot: 2 }, { id: 31, plot: 3 }, { id: 41, plot: 4 }, { id: 51, plot: 5 }, { id: 61, plot: 6 }],
				plots: [1, 2, 3, 4, 5].map(id => ({ id, mode: "'.\map\Plot::OPEN_FIELD.'" })).concat([{ id: 6, mode: "'.\map\Plot::GREENHOUSE.'" }]),
				series: [{ id: 101, cycle: "'.\series\Series::PERENNIAL.'" }, { id: 102, cycle: "'.\series\Series::PERENNIAL.'" }, { id: 103, cycle: "'.\series\Series::ANNUAL.'" }, { id: 104, season: 2025, cycle: "'.\series\Series::ANNUAL.'" }],
				places: [
					{ id: 1, bed: 11, series: 101 }, { id: 2, bed: 12, series: 102 },
					{ id: 3, bed: 21, series: 101 }, { id: 4, bed: 21, series: 103 },
					{ id: 5, bed: 31, series: 103 },
					{ id: 6, bed: 51, series: 102 }, { id: 7, bed: 51, series: 104 },
					{ id: 8, bed: 61, series: 101 }
				]
			};
			emit({ grassed: [...MapSceneCrops.grassed(plan)] });
		');

		if($this->assertDecorPresent($result !== [], 'les jardins lus par node') === FALSE) {
			return;
		}

		$this->assertEquals([1], $result['grassed'], 'seul le jardin de pérennes reste en herbe, ni le jardin mixte, ni celui où une annuelle de la saison passée tient encore, ni le jardin de pérennes sous abri, ni le jardin d\'annuelles, ni le jardin sans place');

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
		return sqrt(array_sum(array_map(fn($a, $b) => ($a - $b) ** 2, $this->lab($first), $this->lab($second))));
	}

	// Une couleur `#rrggbb` en CIE Lab, blanc D65
	private function lab(string $color): array {

		$linear = fn(float $channel) => ($channel <= 0.04045) ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
		[$red, $green, $blue] = array_map(fn($hex) => $linear(hexdec($hex) / 255), str_split(ltrim($color, '#'), 2));

		$f = fn(float $t) => ($t > 0.008856) ? $t ** (1 / 3) : 7.787 * $t + 16 / 116;
		$x = $f(($red * 0.4124 + $green * 0.3576 + $blue * 0.1805) / 0.95047);
		$y = $f($red * 0.2126 + $green * 0.7152 + $blue * 0.0722);
		$z = $f(($red * 0.0193 + $green * 0.1192 + $blue * 0.9505) / 1.08883);

		return [116 * $y - 16, 500 * ($x - $y), 200 * ($y - $z)];

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
				const { MapScene, MapSceneSeason, MapScenePlanSeason, MapSceneDecor, MapSceneAmbience, MapSceneCrops, MapSceneField, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapScene, MapSceneSeason, MapScenePlanSeason, MapSceneDecor, MapSceneAmbience, MapSceneCrops, MapSceneField, ScenePlanGeometry })", context);

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
