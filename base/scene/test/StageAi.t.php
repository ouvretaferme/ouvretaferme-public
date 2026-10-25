<?php
namespace scene;

/**
 * Le calendrier d'une série (`static/js/stage.js`), la résolution d'une culture vers une espèce
 * (`species.js`), la disposition des plants sur une planche (`crop.layout`) et le montage d'un
 * personnage cuit et de ses gestes (`figure.js`), éprouvés sous node tels que le navigateur les
 * charge. Chaque date se compte en jours entiers : le même scénario joué
 * sous deux fuseaux, dont l'un passe à l'heure d'été pendant la quinzaine qui suit la plantation,
 * doit rendre exactement la même chose.
 *
 * Aucune écriture, aucune base ouverte : le catalogue des espèces est monté par le test, pour que
 * le comportement éprouvé ne dépende pas de ce que la session des modèles y range.
 *
 * Lancement : dev/test package=scene file=StageAi
 */
class StageAiTest extends \Test {

	const array TIMEZONES = ['Europe/Paris', 'Pacific/Auckland'];

	public function testStage(): void {

		$results = $this->runEverywhere('
			const stage = scene.stage;

			const series = {
				start: "2026-03-16", stop: "2026-06-28", cycle: "annual", status: "open",
				cultivations: [{ harvestWeeksExpected: ["2026-W20", "2026-W22"] }]
			};
			const tasks = [
				{ fqn: "plantation", planned: "2026-03-16", done: "2026-03-20" },
				{ fqn: "desherbage", planned: "2026-04-01", done: null }
			];
			const timeline = stage.timeline(series, tasks, [{ date: "2026-05-20" }], 2026);

			emit({
				weeks: ["2026-01-01", "2027-01-01", "2020-12-31", "2026-03-29"].map(stage.week),
				mondays: [stage.monday(2026, 1), stage.monday(2026, 53), stage.monday(2027, 1)],
				withoutStart: stage.timeline({ ...series, start: null }, tasks, [], 2026),
				withoutStop: stage.timeline({ ...series, stop: null }, tasks, [], 2026),
				timeline,
				at: [
					["2026-03-01", "real"], ["2026-04-02", "real"], ["2026-04-03", "real"],
					["2026-05-25", "planned"], ["2026-06-10", "planned"], ["2026-07-01", "planned"]
				].map(([date, mode]) => stage.at(timeline, date, mode)).map(state => ({ ...state, progress: +state.progress.toFixed(6), growth: +state.growth.toFixed(6) })),
				harvestWeeks: [stage.harvestWeeks(timeline, "planned"), stage.harvestWeeks(timeline, "real")],
				undatedStart: stage.timeline(series, [{ fqn: "plantation", planned: "2026-03-16", done: null }, { fqn: "semis-direct", planned: null, done: null }], [], 2026).kind,
				noHarvest: stage.harvestWeeks(stage.timeline({ ...series, cultivations: [] }, tasks, [], 2026), "planned")
			});
		');

		$result = $results[self::TIMEZONES[0]];

		$this->assertEquals([['year' => 2026, 'week' => 1], ['year' => 2026, 'week' => 53], ['year' => 2020, 'week' => 53], ['year' => 2026, 'week' => 13]], $result['weeks']);
		$this->assertEquals(['2025-12-29', '2026-12-28', '2027-01-04'], $result['mondays']);

		$this->assertEquals(NULL, $result['withoutStart'], "une série sans début n'a pas de calendrier");
		$this->assertEquals(NULL, $result['withoutStop'], "une série sans fin n'a pas de calendrier");

		$this->assertEquals([
			'kind' => 'planting',
			'nurseryDone' => NULL,
			// La semaine 22 prévue court jusqu'à son dimanche
			'planned' => ['start' => '2026-03-16', 'harvestFirst' => '2026-05-11', 'harvestLast' => '2026-05-31', 'end' => '2026-06-28'],
			// Une récolte saisie le 20 mai tient jusqu'au 26, la semaine prévue la prolonge jusqu'au 31
			'real' => ['start' => '2026-03-20', 'harvestFirst' => '2026-05-20', 'harvestLast' => '2026-05-31', 'end' => '2026-06-28'],
		], $result['timeline']);

		// Planté le 20 mars, récolté dès le 20 mai : 61 jours. Le 3 avril en est le quatorzième, le premier qui ne soit plus frais,
		// et le passage à l'heure d'été du 29 mars ne le décale pas
		$this->assertEquals([
			['key' => 'todo', 'on' => FALSE, 'progress' => 0, 'growth' => 0],
			['key' => 'planted', 'on' => TRUE, 'progress' => round(13 / 61, 6), 'growth' => round(.22 + .78 * 13 / 61, 6)],
			['key' => 'growing', 'on' => TRUE, 'progress' => round(14 / 61, 6), 'growth' => round(.22 + .78 * 14 / 61, 6)],
			['key' => 'harvest', 'on' => TRUE, 'progress' => 1, 'growth' => 1],
			['key' => 'over', 'on' => TRUE, 'progress' => 1, 'growth' => 1],
			['key' => 'done', 'on' => FALSE, 'progress' => 0, 'growth' => 0],
		], $result['at']);

		$this->assertEquals([
			[['year' => 2026, 'week' => 20], ['year' => 2026, 'week' => 22]],
			[['year' => 2026, 'week' => 21], ['year' => 2026, 'week' => 22]],
		], $result['harvestWeeks']);
		$this->assertEquals('planting', $result['undatedStart'], "une mise en place sans date ne décide pas de la nature de la série");
		$this->assertEquals(NULL, $result['noHarvest'], "une série sans récolte prévue ni saisie n'a pas de semaines de récolte");

		$this->assertEquals($results[self::TIMEZONES[0]], $results[self::TIMEZONES[1]], "le calendrier change avec le fuseau de la machine, alors qu'il se compte en jours entiers");

	}

	public function testSpecies(): void {

		$result = $this->runEverywhere('
			const tints = { color: "#111111", leaf: "#222222", accent: "#333333" };
			const species = scene.createSpecies({
				species: [
					{ plant: "Achillée", slug: "achillee", fqn: "achillee-millefeuille", family: "rosette", ...tints },
					{ plant: "Roquette cultivée", slug: "roquette-cultivee", family: "rosette", ...tints },
					{ plant: "Aneth", slug: "aneth", family: "upright", ...tints },
					{ plant: "Carotte", slug: "carotte", family: "fanes", ...tints }
				],
				alias: {
					"Roquette": ["roquette-cultivee", "même espèce"],
					"Daucus carota": ["aneth", "proche", { color: "#eadfe2" }],
					"Carotte": ["aneth", "proche"],
					"Bâche d\'ensilage": [null, "rien"]
				}
			});

			const slug = entry => entry === null ? null : entry.slug;
			const derived = species.get(null, "Daucus carota");

			// Les familles du catalogue, et une espèce dérivée du fraisier qui garde son module
			const shrubs = scene.createSpecies({
				species: [
					{ plant: "Pommier", slug: "pommier", family: "tree", ...tints },
					{ plant: "Cassis", slug: "cassis", family: "berry", ...tints },
					{ plant: "Framboise", slug: "framboise", family: "berry", ...tints },
					{ plant: "Fraise", slug: "fraise", family: "berry", ...tints },
					{ plant: "Fraise remontante", slug: "fraise-remontante", module: "fraise", family: "berry", ...tints },
					{ plant: "Carotte", slug: "carotte", family: "fanes", ...tints }
				],
				alias: {
					"Fraise des bois": ["fraise", "proche", { color: "#c0392b" }]
				}
			});

			emit({
				list: species.list.map(slug),
				byFqn: slug(species.get("achillee-millefeuille", "Carotte")),
				byName: slug(species.get(null, "  ROQUETTE cultivée ")),
				byAlias: slug(species.get(null, "roquette")),
				nameBeforeAlias: slug(species.get(null, "Carotte")),
				derived: [derived.slug, derived.module, derived.color, derived.leaf, derived.base.slug],
				aliasToNothing: species.get(null, "Bâche d\'ensilage"),
				unknown: species.get("inconnue", "Inconnue"),
				woody: Object.fromEntries(shrubs.list.map(entry => [entry.slug, shrubs.woody(entry)]))
			});
		')[self::TIMEZONES[0]];

		$this->assertEquals(['achillee', 'roquette-cultivee', 'aneth', 'carotte', 'aneth~daucus-carota'], $result['list']);
		$this->assertEquals('achillee', $result['byFqn'], "le fqn passe avant le nom");
		$this->assertEquals('roquette-cultivee', $result['byName'], "le nom se compare sans casse ni espaces de bord");
		$this->assertEquals('roquette-cultivee', $result['byAlias']);
		$this->assertEquals('carotte', $result['nameBeforeAlias'], "une entrée du catalogue passe avant un alias du même nom");
		$this->assertEquals(['aneth~daucus-carota', 'aneth', '#eadfe2', '#222222', 'aneth'], $result['derived']);
		$this->assertEquals(NULL, $result['aliasToNothing'], "un alias vers rien rend null");
		$this->assertEquals(NULL, $result['unknown'], "une espèce inconnue rend null, jamais une forme de remplacement");

		$this->assertEquals([
			'pommier' => TRUE,
			'cassis' => TRUE,
			'framboise' => TRUE,
			'fraise' => FALSE,
			'fraise-remontante' => FALSE,
			'carotte' => FALSE,
			'fraise~fraise-des-bois' => FALSE
		], $result['woody'], "un arbre ou un arbuste est ligneux, le fraisier et ce qui en dérive ne le sont pas, quelle que soit sa famille");

	}

	public function testLayout(): void {

		$result = $this->runEverywhere('
			const model = (d, h, species) => ({ d, h, species: { slug: "x", ...species } });
			const round = layout => ({ ...layout, size: { d: +layout.size.d.toFixed(6), h: +layout.size.h.toFixed(6) }, pitch: +layout.pitch.toFixed(6) });
			const bed = { width: .8, length: 10, scale: 3, density: .8 };

			emit({
				plant: round(scene.crop.layout(model(.4, .5, { family: "upright" }), bed)),
				minPitch: round(scene.crop.layout(model(.4, .5, { family: "upright" }), { ...bed, minPitch: 1, inset: .5 })),
				cover: round(scene.crop.layout(model(.4, .5, { family: "upright", module: "couvert" }), { ...bed, width: 3 })),
				tree: round(scene.crop.layout(model(3, 4, { family: "tree" }), { ...bed, width: 1.2, length: 30 })),
				whole: round(scene.crop.layout(model(3, 4, { family: "tree" }), { ...bed, width: 20, length: 30, whole: { bed: 1.2, plants: 10 } })),
				cabbageNarrow: round(scene.crop.layout(model(.4, .3, { family: "cabbage" }), bed)),
				cabbageWide: round(scene.crop.layout(model(.55, .3, { family: "cabbage" }), { ...bed, width: 2, scale: 2.5 })),
				cabbageSplit: round(scene.crop.layout(model(.9, .5, { family: "cabbage" }), { ...bed, width: 5.25, scale: 2.5 })),
				cabbageOverlap: round(scene.crop.layout(model(.4, .3, { family: "cabbage" }), { ...bed, width: 1.87, scale: 2.5 })),
				cabbagePointed: round(scene.crop.layout(model(.45, .3, { family: "cabbage" }), { ...bed, width: 2, scale: 2.5 })),
				cabbageThree: round(scene.crop.layout(model(.3, .3, { family: "cabbage" }), { ...bed, width: 3, scale: 2.5 })),
				cabbageField: round(scene.crop.layout(model(.4, .3, { family: "cabbage" }), { ...bed, width: 10, scale: 2.5 })),
				cabbageRows: round(scene.crop.layout(model(.4, .3, { family: "cabbage", rows: 2, pitch: 1.5 }), { ...bed, width: 10, scale: 2.5 }))
			});
		')[self::TIMEZONES[0]];

		// Emprise 0,4 m sur une planche de 0,8 m : grossie 2,6 fois pour viser 1,3 fois la planche, la hauteur suit
		$this->assertEquals(['size' => ['d' => 1.04, 'h' => 1.5], 'pitch' => .832, 'rows' => 1, 'count' => 12, 'band' => .86], $result['plant']);
		$this->assertEquals(['size' => ['d' => 1.04, 'h' => 1.5], 'pitch' => 1, 'rows' => 1, 'count' => 9, 'band' => .86], $result['minPitch']);

		// Un couvert se sème à la moitié de son emprise, sur quatre rangs au plus
		$this->assertEquals(4, $result['cover']['rows']);
		$this->assertEquals(round(.5 * $result['cover']['size']['d'], 6), $result['cover']['pitch']);

		// Un arbre garde sa taille vraie et tient un rang
		$this->assertEquals(['size' => ['d' => 3, 'h' => 4], 'pitch' => 2.4, 'rows' => 1, 'count' => 9, 'band' => .86], $result['tree']);

		// Sur une parcelle entière, dix plants sur 600 m² : le pas s'élargit à la racine de 60 m², les rangs n'ont pas de plafond
		$this->assertEquals(['size' => ['d' => 3, 'h' => 4], 'pitch' => round(sqrt(60), 6), 'rows' => 2, 'count' => 3, 'band' => .86], $result['whole']);

		// Un chou couvre son pas sur le rang. Sur une planche de 0,8 m, plus étroite que lui, il garde la taille qui le fait lire
		$this->assertEquals(['size' => ['d' => 1.04, 'h' => .9], 'pitch' => 1.04, 'rows' => 1, 'count' => 9, 'band' => .86], $result['cabbageNarrow']);

		// Sur 2 m, un chou de 1,375 m s'élargit aux 86 centièmes de la planche, 1,72 m, sans ouvrir de second rang (1,25 fois sous 1,6)
		$this->assertEquals(['size' => ['d' => 1.72, 'h' => .938182], 'pitch' => 1.72, 'rows' => 1, 'count' => 5, 'band' => .86], $result['cabbageWide']);

		// Sur 5,25 m, un chou de 2,25 m seul ferait deux fois sa taille : deux rangs, chacun à sa part de bande
		$this->assertEquals(['size' => ['d' => 2.2575, 'h' => 1.254167], 'pitch' => 2.2575, 'rows' => 2, 'count' => 4, 'band' => .86], $result['cabbageSplit']);

		// Sur 1,87 m, juste au-delà de 1,6 fois le chou : deux rangs de 0,80 m s'ouvrent et le chou de 1 m garde sa taille, le
		// recouvrement de deux rangs voisins le plus fort que la règle laisse, un cinquième du chou
		$this->assertEquals(['size' => ['d' => 1, 'h' => .75], 'pitch' => 1, 'rows' => 2, 'count' => 10, 'band' => .86], $result['cabbageOverlap']);

		// Un chou pointu de 1,125 m sur 2 m (1,53 fois) garde un seul rang et s'élargit à 1,72 m
		$this->assertEquals(['size' => ['d' => 1.72, 'h' => 1.146667], 'pitch' => 1.72, 'rows' => 1, 'count' => 5, 'band' => .86], $result['cabbagePointed']);

		// Sur 3 m, un chou de 0,75 m ouvre trois rangs : chacun s'élargit à sa part de bande, 0,86 m
		$this->assertEquals(['size' => ['d' => .86, 'h' => .86], 'pitch' => .86, 'rows' => 3, 'count' => 11, 'band' => .86], $result['cabbageThree']);

		// Sur 10 m, la planche se remplit de rangs plutôt que d'un chou géant : six rangs, grossis de 1,43
		$this->assertEquals(['size' => ['d' => 1.433333, 'h' => 1.075], 'pitch' => 1.433333, 'rows' => 6, 'count' => 6, 'band' => .86], $result['cabbageField']);

		// Une espèce qui déclare ses rangs et son pas les garde, et le chou ne dépasse pas le double de sa taille, même sur deux rangs de 4,3 m
		$this->assertEquals(['size' => ['d' => 2, 'h' => 1.5], 'pitch' => 3, 'rows' => 2, 'count' => 3, 'band' => .86], $result['cabbageRows']);

	}

	public function testLayoutSpacing(): void {

		$result = $this->runEverywhere('
			const model = (d, h, species) => ({ d, h, species: { slug: "x", ...species } });
			const bed = { width: .8, length: 10, scale: 3, density: .8 };
			const cases = {
				plant: [model(.4, .5, { family: "upright" }), bed],
				minPitch: [model(.4, .5, { family: "upright" }), { ...bed, minPitch: 1, inset: .5 }],
				cover: [model(.4, .5, { family: "upright", module: "couvert" }), { ...bed, width: 3 }],
				parterre: [model(.3, .2, { family: "upright", parterre: true }), { ...bed, width: 1.2 }],
				declared: [model(.4, .5, { family: "upright", pitch: 2 }), bed],
				tree: [model(3, 4, { family: "tree" }), { ...bed, width: 1.2, length: 30 }],
				whole: [model(3, 4, { family: "tree" }), { ...bed, width: 20, length: 30, whole: { bed: 1.2, plants: 10 } }],
				cabbage: [model(.4, .3, { family: "cabbage" }), { ...bed, width: 10, scale: 2.5 }]
			};
			const out = {};

			for(const [name, [m, options]] of Object.entries(cases)) {
				out[name] = {
					one: scene.crop.layout(m, { ...options, spacing: 1 }),
					two: scene.crop.layout(m, { ...options, spacing: 2 })
				};
			}

			emit(out);
		')[self::TIMEZONES[0]];

		// À 1, posé explicitement, une planche porte ce que `testLayout()` attend sans spacing : le plan 3D, qui le pose à 1, garde
		// son image
		$round = fn(array $layout) => ['size' => ['d' => round($layout['size']['d'], 6), 'h' => round($layout['size']['h'], 6)], 'pitch' => round($layout['pitch'], 6)] + $layout;

		$this->assertEquals(['size' => ['d' => 1.04, 'h' => 1.5], 'pitch' => .832, 'rows' => 1, 'count' => 12, 'band' => .86], $round($result['plant']['one']));
		$this->assertEquals(['size' => ['d' => 1.04, 'h' => 1.5], 'pitch' => 1.0, 'rows' => 1, 'count' => 9, 'band' => .86], $round($result['minPitch']['one']));
		$this->assertEquals(['size' => ['d' => 3.0, 'h' => 4.0], 'pitch' => 2.4, 'rows' => 1, 'count' => 9, 'band' => .86], $round($result['tree']['one']));
		$this->assertEquals(['size' => ['d' => 1.433333, 'h' => 1.075], 'pitch' => 1.433333, 'rows' => 6, 'count' => 6, 'band' => .86], $round($result['cabbage']['one']));

		foreach($result as $name => ['one' => $one, 'two' => $two]) {

			// À 2, le pas double sur le rang, la taille et les rangs restent ceux de la planche, et la planche porte deux fois
			// moins de pieds, à l'arrondi près
			$this->assertEquals(round(2 * $one['pitch'], 9), round($two['pitch'], 9), $name.' : le pas double');
			$this->assertEquals($one['size'], $two['size'], $name.' : la taille des pieds ne change pas');
			$this->assertEquals($one['rows'], $two['rows'], $name.' : le nombre de rangs ne change pas');
			$this->assertTrue(abs($two['count'] - $one['count'] / 2) <= 1, $name.' : la planche porte deux fois moins de pieds ('.$one['count'].' puis '.$two['count'].')');

		}

	}

	public function testFigure(): void {

		$result = $this->runEverywhere('
			const material = new scene.THREE.MeshBasicMaterial({ vertexColors: true });
			const round = n => +n.toFixed(6);
			const triangles = object => { let count = 0; object.traverse(child => { if(child.isMesh) count += child.geometry.attributes.position.count / 3; }); return count; };

			const farmer = scene.figure.assemble(scene.baked, "paysan.houe", material);
			const head = farmer.bones.tete.position;
			scene.figure.pose(farmer, "walk", { phase: Math.PI / 2 });
			const walkLegs = farmer.legs.map(leg => round(leg.rotation.x));
			const walkStill = +scene.figure.pose(farmer, "walk", { phase: 0 }).toFixed(4) + 0;

			// Le plus bas des sommets de chaque pied, la racine descendue de ce que pose() rend, sur un pas entier
			const feet = (figure, gesture = "walk") => {
				const ground = [];
				for(let k = 0; k < 16; k++) {
					figure.root.position.y = scene.figure.pose(figure, gesture, { phase: k / 16 * Math.PI * 2, time: k / 16 });
					figure.root.updateMatrixWorld(true);
					ground.push(+(Math.min(...figure.legs.map(leg => {
						let low = Infinity;
						const point = new scene.THREE.Vector3();
						leg.traverse(child => { if(child.isMesh) { const position = child.geometry.attributes.position; for(let i = 0; i < position.count; i++) { low = Math.min(low, point.fromBufferAttribute(position, i).applyMatrix4(child.matrixWorld).y); } } });
						return low;
					})).toFixed(4)) + 0);
				}
				figure.root.position.y = 0;
				return [...new Set(ground)];
			};
			const walkGround = feet(farmer);
			// Les six corps de la scène, à tibias et semelles modelées
			const castGround = ["paysan-a", "paysan-b", "paysan-c", "paysan-d", "paysan-e", "paysan-f"].map(slug => feet(scene.figure.assemble(scene.baked, slug, material)));
			const castHoeGround = ["paysan-a", "paysan-b", "paysan-c", "paysan-d", "paysan-e", "paysan-f"].map(slug => feet(scene.figure.assemble(scene.baked, slug, material), "hoe"));
			const crouch = scene.figure.pose(farmer, "crouch");
			const crouchTorso = round(farmer.torso.position.y);
			const hoe = (scene.figure.pose(farmer, "hoe", { phase: Math.PI / 6 }), farmer.arms.map(arm => round(arm.rotation.x)));
			const held = scene.figure.prop(scene.baked, "caisse", material);
			held.geometry.computeBoundingBox();
			held.scale.setScalar(.44 / held.geometry.boundingBox.getSize(new scene.THREE.Vector3()).x);
			farmer.torso.add(held);
			const hold = scene.figure.hold(farmer, held);
			const carry = [Math.PI / 2, -Math.PI / 2].map(phase => (scene.figure.pose(farmer, "walk", { phase, carry: { crate: held, hold, lift: .5 } }), farmer.arms.map(arm => [round(arm.rotation.x), round(arm.rotation.z) * Math.sign(arm.position.x)])));
			farmer.torso.remove(held);

			let unknown = null;
			try { scene.figure.pose(farmer, "wave"); } catch(error) { unknown = error.message; }

			const crate = scene.figure.prop(scene.baked, "caisse", material, { contenu: "#ff0000" });
			const colors = crate.geometry.attributes.color.array;
			let red = 0;
			for(let i = 0; i < colors.length; i += 3) { if(colors[i] === 1 && colors[i + 1] === 0 && colors[i + 2] === 0) red++; }

			// Un corps à genoux, monté à la main : le genou du contrat s\'éprouve sans dépendre du catalogue cuit
			const box = () => new scene.THREE.BoxGeometry(.1, .1, .1).toNonIndexed();
			const kneeled = scene.createFigure(() => ({
				d: .5, h: 1.8, aligned: false,
				parts: ["bassin", "torse", "jambeG", "jambeD", "tibiaG", "tibiaD", "brasG", "brasD"].map(name => ({ geometry: box(), color: "336699", sway: 0, name })),
				pivots: { bassin: [0, .62, 0], torse: [0, .62, 0], jambeG: [.1, .62, 0], jambeD: [-.1, .62, 0], tibiaG: [.1, .35, 0], tibiaD: [-.1, .35, 0], brasG: [.2, 1, 0], brasD: [-.2, 1, 0] },
				parents: { tibiaG: "jambeG", tibiaD: "jambeD", brasG: "torse", brasD: "torse" }
			})).assemble(null, "genoux", material);
			const kneeledDrop = scene.figure.pose(kneeled, "crouch");
			const kneeledBend = kneeled.knees.map(knee => round(knee.rotation.x));
			const kneeledTorso = round(kneeled.torso.position.y);

			// À demi : la racine descendue de ce que pose() rend, le plus bas des sommets de chaque pied, et la même descente
			// relue par ground() sur les os posés
			const half = (figure, depth) => {
				figure.root.position.y = scene.figure.pose(figure, "crouch", { depth });
				const ground = scene.figure.ground(figure);
				figure.root.updateMatrixWorld(true);
				const low = Math.min(...figure.legs.map(leg => {
					let low = Infinity;
					const point = new scene.THREE.Vector3();
					leg.traverse(child => { if(child.isMesh) { const position = child.geometry.attributes.position; for(let i = 0; i < position.count; i++) { low = Math.min(low, point.fromBufferAttribute(position, i).applyMatrix4(child.matrixWorld).y); } } });
					return low;
				}));
				const drop = figure.root.position.y;
				figure.root.position.y = 0;
				return { drop: round(drop), ground: round(ground), low: +low.toFixed(4) + 0, legs: [...figure.legs, ...(figure.knees ?? [])].map(bone => round(bone.rotation.x)), bend: round(figure.torso.rotation.x) };
			};
			const kneeledHalf = half(kneeled, .5);
			const farmerHalf = half(farmer, .5);

			emit({
				bones: Object.keys(farmer.bones).sort(),
				triangles: triangles(farmer.root),
				head: [round(head.x), round(head.y), round(head.z)],
				knees: farmer.knees,
				walkLegs, walkStill, walkGround, castGround, castHoeGround,
				crouch, crouchTorso,
				hoe, hoeArms: [triangles(farmer.bones.brasD), triangles(farmer.bones.brasG)],
				carry, unknown,
				crateTriangles: triangles(crate),
				crateAnchors: Object.keys(crate.userData.anchors),
				red: red > 0,
				shin: kneeled.knees.map(knee => [round(knee.position.y), knee.parent === kneeled.bones.jambeD || knee.parent === kneeled.bones.jambeG]),
				kneeledDrop, kneeledHalf, farmerHalf,
				kneeledBend, kneeledTorso,
				kneeledCrouchGround: feet(kneeled, "crouch"),
				// Mesuré après le geste accroupi, que la marche défait
				kneeledGround: feet(kneeled)
			});
		')[self::TIMEZONES[0]];

		// Les os du paysan cuit, chacun à son pivot, la tête posée depuis celui du buste qui la porte
		$this->assertEquals(['bassin', 'brasD', 'brasG', 'jambeD', 'jambeG', 'tete', 'torse'], $result['bones']);
		$this->assertEquals([0, .49, 0], $result['head']);

		// Toutes ses pièces sont montées : la cuisson en compte 314
		$this->assertEquals(314, $result['triangles']);

		// Sans tibias, ses jambes restent d'un bloc, et l'accroupi descend le buste du tiers de ce que descend le corps
		$this->assertEquals(NULL, $result['knees']);
		$this->assertEquals(-.22, $result['crouch']);
		$this->assertEquals(round(.62 - .22 * .3, 6), $result['crouchTorso']);

		// Au milieu du pas, la jambe droite est au plus loin devant, la gauche derrière ; jambes croisées, le corps ne bouge
		// pas. Tout le long du pas, le pied d'appui touche le sol sans y entrer, au dixième de millimètre près que laissent les
		// sommets en simple précision, sur le paysan sans genou comme sur les six de la scène
		$this->assertEquals([.6, -.6], $result['walkLegs']);
		$this->assertEquals(0, $result['walkStill']);
		$this->assertEquals([0], $result['walkGround']);
		$this->assertEquals(array_fill(0, 6, [0]), $result['castGround']);

		// En binant, la jambe portée en arrière plongerait la pointe de sa botte dans la terre : le corps se recale sur elle à chaque
		// coup, et les six corps gardent leurs pieds au sol
		$this->assertEquals(array_fill(0, 6, [0]), $result['castHoeGround']);

		// En binant, les deux bras balancent chacun de son côté, et la houe est dans la main gauche, au second rang des bras
		$this->assertEquals([-.675, -.725], $result['hoe']);
		$this->assertTrue($result['hoeArms'][1] > $result['hoeArms'][0]);

		// Une caisse tenue prend les deux bras quel que soit le pas, chacun en avant et vers le corps
		$this->assertEquals($result['carry'][0], $result['carry'][1]);

		foreach($result['carry'][0] as [$forward, $inward]) {
			$this->assertTrue($forward < 0, 'bras tenant la caisse à '.$forward.' rad vers l\'avant');
			$this->assertTrue($inward < 0, 'bras tenant la caisse à '.$inward.' rad vers le corps');
		}

		$this->assertEquals('sceneCreateFigure : geste inconnu wave', $result['unknown']);

		// La caisse est un maillage entier, son contenu à la couleur demandée
		$this->assertEquals(56, $result['crateTriangles']);
		$this->assertEquals(['dessus'], $result['crateAnchors']);
		$this->assertTrue($result['red']);

		// Avec des tibias, le genou pend à sa cuisse, plie autant que la cuisse se lève, et le buste reste sur le bassin
		$this->assertEquals([[-.27, TRUE], [-.27, TRUE]], $result['shin']);
		// Accroupi, le corps descend jusqu'à poser la botte au sol, sans l'y enfoncer, quels que soient la cuisse et le tibia
		$this->assertTrue($result['kneeledDrop'] < 0, 'corps accroupi à '.$result['kneeledDrop'].' sous ses pieds');
		$this->assertEquals([0], $result['kneeledCrouchGround']);
		$this->assertEquals([1.5, 1.5], $result['kneeledBend']);
		$this->assertEquals(.62, $result['kneeledTorso']);
		$this->assertEquals([0], $result['kneeledGround']);

		// À demi, cuisses, genoux et buste plient de la moitié de l'accroupi, la botte toujours au sol, et ground() rend la
		// descente que pose() a rendue ; sans tibias, le corps descend de la moitié
		$this->assertEquals([-.75, -.75, .75, .75], $result['kneeledHalf']['legs']);
		$this->assertEquals(.275, $result['kneeledHalf']['bend']);
		$this->assertEquals(0, $result['kneeledHalf']['low']);
		$this->assertEquals($result['kneeledHalf']['drop'], $result['kneeledHalf']['ground']);
		$this->assertTrue($result['kneeledHalf']['drop'] < 0 and $result['kneeledHalf']['drop'] > $result['kneeledDrop'], 'à demi, le corps descend moins qu\'accroupi ('.$result['kneeledHalf']['drop'].' contre '.$result['kneeledDrop'].')');
		$this->assertEquals(-.11, $result['farmerHalf']['drop']);
		$this->assertEquals([-.525, -.525], $result['farmerHalf']['legs']);

	}

	public function testHold(): void {

		$result = $this->runEverywhere('
			const material = new THREE.MeshBasicMaterial({ vertexColors: true });
			const round = n => +n.toFixed(3);
			const crateOf = long => {
				const crate = scene.figure.prop(scene.baked, "caisse", material);
				crate.geometry.computeBoundingBox();
				crate.scale.setScalar(long / crate.geometry.boundingBox.getSize(new THREE.Vector3()).x);
				return crate;
			};

			const grips = [];

			for(const slug of ["paysan", "paysan.casquette"]) {
				for(const gesture of ["walk", "stand"]) {
					for(const lift of [0, .5, 1]) {

						const farmer = scene.figure.assemble(scene.baked, slug, material);
						const crate = crateOf(.44);
						const hold = scene.figure.hold(farmer, crate);

						farmer.torso.add(crate);
						scene.figure.pose(farmer, gesture, { phase: 1, time: 2, carry: { crate, hold, lift } });
						farmer.root.updateMatrixWorld(true);

						// Le bas de chaque bras, dans le repère de la caisse : son bord intérieur contre un grand côté, sa hauteur dans la sienne
						const box = crate.geometry.boundingBox;
						const inverse = crate.matrixWorld.clone().invert();
						const hands = farmer.arms.map(arm => {
							const mesh = arm.children.find(child => child.isMesh);
							mesh.geometry.computeBoundingBox();
							const bottom = mesh.geometry.boundingBox.min.y;
							const position = mesh.geometry.attributes.position;
							let inner = Infinity, low = Infinity, high = -Infinity;
							for(let i = 0; i < position.count; i++) {
								if(position.getY(i) > bottom + .08) continue;
								const point = new THREE.Vector3().fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld).applyMatrix4(inverse);
								inner = Math.min(inner, Math.abs(point.z));
								low = Math.min(low, point.y);
								high = Math.max(high, point.y);
							}
							return { side: round((inner - box.max.z) * crate.scale.z), middle: round(((low + high) / 2 / box.max.y) - .5) };
						});

						// Dans le repère du buste, que la marche penche : la caisse y est posée, la poitrine y est fixe
						const torso = farmer.torso.children.find(child => child.isMesh);
						torso.geometry.computeBoundingBox();
						const chest = torso.geometry.boundingBox.clone().applyMatrix4(torso.matrix);
						const held = box.clone().applyMatrix4(crate.matrix);

						// Serrée, son dessus arrive aux épaules
						const shoulder = Math.min(...farmer.arms.map(arm => arm.position.y));

						grips.push({ slug, gesture, lift, hands, back: round(held.min.z - chest.max.z), top: round(held.max.y - shoulder), raise: round(hold.high - hold.low) });

					}
				}
			}

			let far = null;
			try { scene.figure.hold(scene.figure.assemble(scene.baked, "paysan", material), crateOf(1.2)); } catch(error) { far = error.message; }

			// Un bras en deux os, la main pendue au bras par `parents` : la prise lit le bras entier, et trouve la main au bout
			const box = (width, height, y) => new THREE.BoxGeometry(width, height, .08).translate(0, y, 0).toNonIndexed();
			const parts = { bassin: box(.3, .2, .6), torse: box(.3, .5, .95), jambeG: box(.1, .6, .3), jambeD: box(.1, .6, .3), brasG: box(.08, .3, 1.05), brasD: box(.08, .3, 1.05), mainG: box(.08, .3, .75), mainD: box(.08, .3, .75) };
			const jointed = scene.createFigure(() => ({
				d: .5, h: 1.8, aligned: false,
				parts: Object.entries(parts).map(([name, geometry]) => ({ geometry, color: "336699", sway: 0, name })),
				pivots: { bassin: [0, .6, 0], torse: [0, .6, 0], jambeG: [.1, .6, 0], jambeD: [-.1, .6, 0], brasG: [.2, 1.2, 0], brasD: [-.2, 1.2, 0], mainG: [.2, .9, 0], mainD: [-.2, .9, 0] },
				parents: { brasG: "torse", brasD: "torse", mainG: "brasG", mainD: "brasD" }
			})).assemble(null, "bras", material);
			const jointedHands = scene.figure.hold(jointed, crateOf(.3)).hands.map(({ hand }) => round(hand.y));

			emit({ grips, far, jointedHands });
		')[self::TIMEZONES[0]];

		foreach($result['grips'] as $grip) {

			$name = $grip['slug'].' en '.$grip['gesture'].' levée à '.$grip['lift'];

			// Chaque main touche un grand côté de la caisse, à un centimètre près, et la tient vers le milieu de sa hauteur
			foreach($grip['hands'] as $hand) {
				$this->assertTrue(abs($hand['side']) <= .01, $name.' : main à '.$hand['side'].' du côté de la caisse');
				$this->assertTrue(abs($hand['middle']) <= .25, $name.' : main à '.$hand['middle'].' du milieu de la hauteur');
			}

			// L'arrière de la caisse est contre la poitrine, sans y entrer
			$this->assertTrue($grip['back'] >= 0 and $grip['back'] <= .03, $name.' : caisse à '.$grip['back'].' du buste');

			// Portée bas, elle monte quand on la serre, jusqu'à ce que son dessus arrive aux épaules
			$this->assertTrue($grip['raise'] > .1, $name.' : la caisse serrée ne monte que de '.$grip['raise']);

			if($grip['lift'] === 1) {
				$this->assertTrue(abs($grip['top']) <= .01, $name.' : dessus de la caisse à '.$grip['top'].' des épaules');
			}

		}

		// Une caisse trop longue pour les bras est refusée, plutôt que tenue dans le vide
		$this->assertTrue($result['far'] !== NULL and str_contains($result['far'], 'hors de portée des bras'));

		// La main est le bas du bras entier, à 60 cm sous l'épaule, et non le bas du haut du bras, à 30 cm
		$this->assertEquals([-.6, -.6], $result['jointedHands']);

	}

	public function testPaint(): void {

		$result = $this->runEverywhere('
			const box = new scene.THREE.BoxGeometry(1, 1, 1).toNonIndexed();
			const painted = scene.geometry.paint(box, new scene.THREE.Color(.1, .2, .3));
			const values = painted.attributes.color.array;
			const triples = new Set();
			for(let i = 0; i < values.length; i += 3) {
				triples.add([values[i], values[i + 1], values[i + 2]].map(n => +n.toFixed(6)).join(","));
			}
			// Une pièce qui porte déjà ses couleurs par sommet les garde dans un personnage, sauf quand l\'appelant en demande une autre
			const colored = () => {
				const geometry = new scene.THREE.BoxGeometry(.1, .1, .1).toNonIndexed();
				geometry.setAttribute("color", new scene.THREE.Float32BufferAttribute(Array.from({ length: geometry.attributes.position.count }, () => [.25, .5, .75]).flat(), 3));
				return geometry;
			};
			const model = () => ({ d: .1, h: .1, aligned: false, parts: [{ geometry: colored(), color: "000000", sway: 0, name: "contenu" }] });
			const tints = mesh => [...new Set(Array.from({ length: mesh.geometry.attributes.color.count }, (_, i) => [0, 1, 2].map(k => +mesh.geometry.attributes.color.array[i * 3 + k].toFixed(6)).join(",")))];

			emit({
				kept: tints(scene.createFigure(model).prop(null, "teintes", new scene.THREE.MeshBasicMaterial())),
				replaced: tints(scene.createFigure(model).prop(null, "teintes", new scene.THREE.MeshBasicMaterial(), { contenu: "#ff0000" })),
				same: painted === box,
				size: values.length === box.attributes.position.count * 3,
				triples: [...triples]
			});
		')[self::TIMEZONES[0]];

		// La pièce est peinte sur place : chaque sommet porte la couleur, chaque canal à sa place
		$this->assertTrue($result['same']);
		$this->assertEquals(['0.25,0.5,0.75'], $result['kept']);
		$this->assertEquals(['1,0,0'], $result['replaced']);
		$this->assertTrue($result['size']);
		$this->assertEquals(['0.1,0.2,0.3'], $result['triples']);

	}

	public function testBakedColors(): void {

		$result = $this->runEverywhere('
			// Un fichier cuit à la main, deux pièces de trois sommets, la première seule colorée par sommet
			const index = colored => ({
				vertices: 6,
				...(colored ? { colors: 3 } : {}),
				models: { essai: { d: 1, h: 1, aligned: false, box: [[0, 0, 0], [1, 1, 1]], offset: 0, parts: [
					{ color: "336699", sway: 0, offset: 0, count: 3, ...(colored ? { colorOffset: 0 } : {}) },
					{ color: "996633", sway: 0, offset: 3, count: 3 }
				] } }
			});
			const buffer = new ArrayBuffer(6 * 9 + 9);
			new Uint8Array(buffer, 6 * 9, 9).set([255, 0, 0, 128, 128, 128, 0, 0, 0]);
			const read = colored => scene.buildModel({ index: index(colored), buffer }, "essai").parts.map(part => part.geometry.hasAttribute("color") ? Array.from(part.geometry.attributes.color.array, n => +n.toFixed(4)) : null);

			let overflow = null;
			const beyond = index(true);
			beyond.models.essai.parts[0].colorOffset = 2;
			try { scene.buildModel({ index: beyond, buffer }, "essai"); } catch(error) { overflow = error.name; }

			emit({ before: read(false), after: read(true), overflow });
		')[self::TIMEZONES[0]];

		// L'ancien format se lit sans couleur par sommet. Le nouveau rend en linéaire ce que la cuisson écrit en sRGB, la pièce sans
		// colorOffset restant sans attribut
		$this->assertEquals([NULL, NULL], $result['before']);
		$this->assertEquals([[1, 0, 0, .2159, .2159, .2159, 0, 0, 0], NULL], $result['after']);
		// Un index qui lit au-delà du fichier échoue, au lieu de peindre en NaN
		$this->assertEquals('RangeError', $result['overflow']);

	}

	public function testBakeHooksWithoutData(): void {

		// Les outils de node des skills enregistrent les crochets de la cuisson sans rien leur passer, et un .js de model/ doit
		// alors se lire en module ES. La cuisson d'un autre package leur passe son dossier (bake.mjs), ce qui ne retire rien aux
		// premiers. Le script ne charge rien du socle : node 18 tombe en erreur de segmentation quand helpers.js suit ses modules
		$result = $this->node('
			require("node:module").register("file://" + '.json_encode(LIME_DIRECTORY.'/base/scene/bake/resolve.mjs').');

			import("file://" + '.json_encode(LIME_DIRECTORY.'/base/scene/model/helpers.js').').then(helpers => process.stdout.write(JSON.stringify({ facets: typeof helpers.facets })));
		', self::TIMEZONES[0]);

		$this->assertEquals('function', $result['facets'] ?? NULL, "bake/resolve.mjs ne se laisse plus enregistrer sans data : son initialize() doit garder ce package quand rien ne lui est passé");

	}

	/**
	 * Le scénario joué sous chaque fuseau de TIMEZONES, avec `scene.stage`, `scene.crop` et
	 * `scene.createSpecies` chargés depuis les fichiers servis, et `emit()` qui rend son résultat.
	 *
	 * @return array<string, array> le résultat de chaque fuseau
	 */
	private function runEverywhere(string $scenario): array {

		$results = [];

		foreach(self::TIMEZONES as $timezone) {
			$results[$timezone] = $this->run($scenario, $timezone);
		}

		return $results;

	}

	private function run(string $scenario, string $timezone): array {

		$script = '
			const directory = "file://" + '.json_encode(LIME_DIRECTORY.'/base/scene/static').';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire en modules ES les .js du package, qu\'aucun package.json ne déclare tels
			require("node:module").register("file://" + '.json_encode(LIME_DIRECTORY.'/base/scene/bake/resolve.mjs').');

			(async () => {
				const THREE = await import(directory + "/lib/three-0.170.0.module.min.js");
				const addons = await import(directory + "/lib/three-0.170.0.BufferGeometryUtils.js");
				const { sceneCreateGeometry } = await import(directory + "/js/geometry.js");
				const { sceneCreateCrop } = await import(directory + "/js/crop.js");
				const { sceneCreateSpecies } = await import(directory + "/js/species.js");
				const { sceneCreateStage } = await import(directory + "/js/stage.js");
				const { sceneBuildModel } = await import(directory + "/js/model.js");
				const { sceneCreateFigure } = await import(directory + "/js/figure.js");
				const { readFileSync } = await import("node:fs");
				const bin = readFileSync(new URL(directory + "/model/models.bin"));

				const scene = {
					stage: sceneCreateStage(),
					crop: sceneCreateCrop(THREE, addons, sceneCreateGeometry(THREE, addons), sceneBuildModel, sceneCreateSpecies({ species: [], alias: {} }).woody),
					createSpecies: sceneCreateSpecies,
					geometry: sceneCreateGeometry(THREE, addons),
					figure: sceneCreateFigure(THREE, addons, sceneCreateGeometry(THREE, addons), sceneBuildModel),
					createFigure: build => sceneCreateFigure(THREE, addons, sceneCreateGeometry(THREE, addons), build),
					buildModel: (baked, slug) => sceneBuildModel(THREE, baked, slug),
					baked: { index: JSON.parse(readFileSync(new URL(directory + "/model/models.json"), "utf8")), buffer: bin.buffer.slice(bin.byteOffset, bin.byteOffset + bin.byteLength) },
					THREE
				};

				'.$scenario.'
			})();
		';

		return $this->node($script, $timezone);

	}

	/**
	 * Un script joué par node sous un fuseau, qui écrit son résultat en JSON sur sa sortie.
	 */
	private function node(string $script, string $timezone): array {

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['TZ' => $timezone, 'PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve les modules du socle 3D sous node, l'installer sur la machine qui lance les tests") === FALSE) {
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

		$this->assertTrue(is_array($result), 'node rend son résultat sous '.$timezone.' (un « command not found » dit que node manque à la machine) : '.$output.$errors);

		return $result ?? [];

	}

}
