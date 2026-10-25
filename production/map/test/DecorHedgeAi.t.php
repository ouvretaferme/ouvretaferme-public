<?php
namespace map;

/**
 * La haie mêlée du décor (`static/js/sceneDecor.js`, `MapSceneDecor::hedgeSpans()` et `spans()`), éprouvée sous node telle que le
 * navigateur charge les fichiers : la même graine redonne la même haie, les formes changent par séries, la charmille a ses deux
 * bouts, l'arbre garde sa forme sur une travée étirée, chaque travée porte sa saison, et ni la bande fleurie ni la clôture ne
 * tirent quoi que ce soit.
 *
 * Aucune écriture, aucune base ouverte. Les modèles sont lus dans `base/scene/model/` et fondus comme la cuisson les rend, une
 * géométrie par modèle, les pièces dans l'ordre : le test ne dépend pas de ce qui est cuit.
 *
 * Lancement : dev/test package=map file=DecorHedgeAi
 */
class DecorHedgeAiTest extends \Test {

	public function testSameSeedSameHedge(): void {

		$result = $this->run('
			vm.runInContext("Math.random = () => { throw new Error(\"Math.random\"); }", context);

			const posts = line(30, 2.4);
			const draw = seed => print(rig(ALL).spans(MapSceneDecor.hedge(), posts, seed).geometry);

			emit({ first: draw(41), again: draw(41), other: draw(42) });
		');

		$this->assertEquals($result['first'], $result['again'], 'une haie se redessine à l\'identique avec la même graine');
		$this->assertTrue($result['first'] !== $result['other'], 'deux graines donnent deux haies');

	}

	public function testRunsWallEndsAndTrees(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const faults = [];
			const seen = { forms: new Set(), greens: new Set(), flips: new Set(), trees: 0 };

			for(let seed = 1; seed <= 200; seed++) {

				for(const count of [2, 3, 5, 12, 40]) {

					const spans = D.hedgeSpans(Array(count).fill(1), seed);
					const at = seed + "/" + count;
					const forms = spans.map(span => span.slug);

					// Les séries, arbres ôtés : un arbre prend une travée de sa série sans la couper
					let length = 0;

					forms.filter(slug => slug !== D.HEDGE_TREE).forEach((slug, k, list) => {
						length = (slug === list[k - 1]) ? length + 1 : 1;
						if(length > D.HEDGE_RUN[1]) {
							faults.push(at + " série de plus de " + D.HEDGE_RUN[1] + " travées");
						}
					});

					spans.forEach((span, k) => {

						seen.forms.add(span.slug);
						seen.greens.add(span.green);
						seen.flips.add(span.flip);

						if(span.height < 1 - D.HEDGE_HEIGHT || span.height > 1 + D.HEDGE_HEIGHT) {
							faults.push(at + " hauteur " + span.height);
						}

						if(span.slug !== D.HEDGE_WALL) {
							if(span.end !== 0) {
								faults.push(at + " bout hors charmille en " + k);
							}
							return;
						}

						const first = (forms[k - 1] !== D.HEDGE_WALL);
						const last = (forms[k + 1] !== D.HEDGE_WALL);

						if(first && last) {
							faults.push(at + " charmille d\'une seule travée en " + k);
						} else if(first && (span.end !== -1 || span.flip !== true)) {
							faults.push(at + " première travée de charmille sans son bout retourné en " + k);
						} else if(last && (span.end !== 1 || span.flip !== false)) {
							faults.push(at + " dernière travée de charmille sans son bout à l\'endroit en " + k);
						} else if(first === false && last === false && span.end !== 0) {
							faults.push(at + " bout au milieu d\'une charmille en " + k);
						}

					});

					const trees = forms.flatMap((slug, k) => (slug === D.HEDGE_TREE) ? [k] : []);
					seen.trees += trees.length;

					trees.forEach((k, rank) => {

						const before = (rank === 0) ? -1 : trees[rank - 1];

						if(k === 0 || k === count - 1) {
							faults.push(at + " arbre au bout de la haie en " + k);
						}

						if(k - before < D.HEDGE_TREE_EVERY[0]) {
							faults.push(at + " arbres trop proches en " + k);
						}

						// Un arbre en retard a attendu la fin d\'une charmille
						if(k - before > D.HEDGE_TREE_EVERY[1] && forms.slice(before + D.HEDGE_TREE_EVERY[1], k).some(slug => slug !== D.HEDGE_WALL)) {
							faults.push(at + " arbre en retard sans charmille en " + k);
						}

					});

				}

				// Une travée de plus au bout ne change pas celles d\'avant, sauf la dernière, qui n\'est plus un bout
				if(JSON.stringify(D.hedgeSpans(Array(20).fill(1), seed).slice(0, 11)) !== JSON.stringify(D.hedgeSpans(Array(12).fill(1), seed).slice(0, 11))) {
					faults.push(seed + " une haie allongée change ses premières travées");
				}

				// Une travée trop courte ou trop longue pour l\'arbre le laisse à la suivante
				D.hedgeSpans(Array.from({ length: 40 }, (_, k) => (k % 2 === 0) ? 1 : .5), seed).forEach((span, k) => {
					if(span.slug === D.HEDGE_TREE && k % 2 === 1) {
						faults.push(seed + " arbre sur une travée hors de sa plage en " + k);
					}
				});

			}

			emit({ faults, forms: [...seen.forms].sort(), greens: [...seen.greens].sort(), flips: seen.flips.size, trees: seen.trees });
		');

		$this->assertEquals([], array_slice($result['faults'], 0, 10), 'les séries, les bouts de charmille et les arbres tiennent leurs règles sur 200 graines');
		$this->assertEquals(['haie', 'haie.baies', 'haie.bocage', 'haie.charmille'], $result['forms'], 'les quatre formes sortent');
		$this->assertEquals([0, 1, 2], $result['greens'], 'les trois verts sortent');
		$this->assertEquals(2, $result['flips'], 'une travée se pose dans un sens ou dans l\'autre');
		$this->assertTrue($result['trees'] > 200, 'des arbres sont plantés');

	}

	public function testEachSpanFollowsItsDraw(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const posts = line(41, 2.4);
			const seed = 7;
			const drawn = D.hedgeSpans(Array(40).fill(1), seed);

			const whole = rig(ALL);
			const made = whole.spans(D.hedge(), posts, seed);
			const tops = ranges(whole).map(([from, to]) => Math.max(...Array.from({ length: to - from }, (_, i) => made.geometry.attributes.position.getY(from + i))));

			const bare = rig(ALL.filter(slug => slug !== D.HEDGE_WALL_END));
			bare.spans(D.hedge(), posts, seed);

			const alone = rig(["haie"]);
			const single = alone.spans(D.hedge(), posts, seed);

			emit({
				drawn: drawn.map(span => [(span.end !== 0) ? D.HEDGE_WALL_END : span.slug, span.green]),
				laid: whole.laid,
				cycled: drawn.every((span, k) => span.green === (k + 1) % D.GREENS.length),
				tops: tops.map((top, k) => Math.abs(top - whole.baked.index.models[whole.laid[k][0]].h * drawn[k].height)),
				height: Math.abs(made.height - Math.max(...tops)),
				bare: bare.laid.map(([slug]) => slug),
				wall: drawn.map(span => span.slug),
				alone: [...new Set(alone.laid.map(([slug]) => slug))],
				vertices: [single.geometry.attributes.position.count, 40 * alone.base("haie", 0, false).attributes.position.count]
			});
		');

		$this->assertEquals($result['drawn'], $result['laid'], 'chaque travée prend la forme et le vert tirés, et une charmille son bout aux deux extrémités de sa série');
		$this->assertTrue(in_array('haie.charmille.bout', array_column($result['laid'], 0)), 'la graine du test pose une charmille');
		$this->assertTrue($result['cycled'] === FALSE, 'les verts sont tirés, pas distribués à tour de rôle');
		$this->assertTrue(max($result['tops']) < 0.001, 'chaque travée monte à la hauteur de son modèle fois celle qu\'elle a tirée');
		$this->assertTrue($result['height'] < 0.001, 'la hauteur rendue est celle de la plus haute travée');
		$this->assertEquals($result['wall'], $result['bare'], 'sans son bout cuit, une charmille garde son tronçon aux extrémités');
		$this->assertEquals(['haie'], $result['alone'], 'sans les formes cuites, la haie se pose en entier avec son tronçon');
		$this->assertEquals($result['vertices'][1], $result['vertices'][0], 'et compte alors une travée du tronçon par intervalle');

	}

	public function testTreeKeepsItsShapeOnStretchedSpan(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const seed = 7;
			const model = sources[D.HEDGE_TREE];
			const flat = fuse(model).attributes;

			// Les sommets de l\'arbre dans son modèle : le bois et la tête
			const own = [];
			let offset = 0;

			for(const part of model.parts) {
				const count = part.geometry.attributes.position.count;
				if(part.name === "arbre" || part.name === "feuillage") {
					own.push([offset, offset + count]);
				}
				offset += count;
			}

			const measure = (stretch, slugs = ALL, anchored = true) => {

				const decor = rig(slugs, anchored);
				const posts = line(41, stretch * 3 - .6);
				const drawn = D.hedgeSpans(Array(40).fill(stretch), seed);
				const k = drawn.findIndex(span => span.slug === D.HEDGE_TREE);
				const geometry = decor.spans(D.hedge(), posts, seed).geometry;
				const [from] = ranges(decor)[k];
				const box = new THREE.Box3();
				const point = new THREE.Vector3();
				const side = new THREE.Vector3();
				const facing = new THREE.Vector3();
				let normal = 0;
				let foot = Infinity;

				// Ce qu\'une normale fait avec un côté de son triangle, que ni un déplacement ni une rotation ne changent. Sa longueur
				// ne dirait rien : une normale penchée par l\'étirement est remise à 1 par three
				const lean = (attributes, i, j, scale) => facing.fromBufferAttribute(attributes.normal, i).dot(
					side.fromBufferAttribute(attributes.position, j).sub(point.fromBufferAttribute(attributes.position, i))
				) / scale;

				for(const [first, last] of own) {
					for(let i = first; i < last; i++) {

						for(const step of [1, 2]) {
							const j = i - i % 3 + (i % 3 + step) % 3;
							normal = Math.max(normal, Math.abs(lean(geometry.attributes, from + i, from + j, drawn[k].height) - lean(flat, i, j, 1)));
						}

						box.expandByPoint(point.fromBufferAttribute(geometry.attributes.position, from + i));
						foot = Math.min(foot, point.y);

					}
				}

				const size = box.getSize(new THREE.Vector3()).divideScalar(drawn[k].height);

				// La haie du test court sur X : la longueur du modèle, en Z, s\'y lit en X
				return { k, along: size.x, up: size.y, across: size.z, normal, foot, middle: (box.min.x + box.max.x) / 2 - posts[k].x };

			};

			const bounds = new THREE.Box3();
			const point = new THREE.Vector3();

			for(const part of model.parts) {
				const position = (part.geometry.index ? part.geometry.toNonIndexed() : part.geometry).attributes.position;
				if(part.name === "arbre" || part.name === "feuillage") {
					for(let i = 0; i < position.count; i++) {
						bounds.expandByPoint(point.fromBufferAttribute(position, i));
					}
				}
			}

			const size = bounds.getSize(new THREE.Vector3());

			// En deçà des bornes de HEDGE_STRETCH : l\'étirement relu sur les piquets s\'en écarte d\'un rien, et sortirait de la plage
			emit({ model: { along: size.z, up: size.y, across: size.x }, short: measure(.75), long: measure(1.25), loose: measure(1.25, ALL, false) });
		');

		foreach(['short', 'long'] as $case) {

			foreach(['along', 'up', 'across'] as $axis) {
				$this->assertTrue(abs($result[$case][$axis] - $result['model'][$axis]) < 0.001, 'l\'arbre d\'une travée étirée garde ses proportions ('.$case.', '.$axis.')');
			}

			$this->assertTrue($result[$case]['normal'] < 0.001, 'ses normales restent celles du modèle, tournées avec lui ('.$case.')');
			$this->assertTrue(abs($result[$case]['foot']) < 0.001, 'son pied reste au sol ('.$case.')');

		}

		$this->assertTrue($result['short']['k'] === $result['long']['k'], 'les deux mesures portent sur la même travée');
		$this->assertTrue($result['long']['middle'] - $result['short']['middle'] > 0.3, 'l\'arbre suit sa place le long de la travée étirée');
		$this->assertTrue($result['loose']['along'] > $result['model']['along'] * 1.2, 'un modèle sans ancre garde son arbre étiré avec la travée, sans erreur');

	}

	public function testEachSpanCarriesItsSeason(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const seed = 7;
			const decor = rig(ALL);
			const geometry = decor.spans(D.hedge(), line(41, 2.4), seed).geometry;
			const season = geometry.attributes.season;
			const point = new THREE.Vector3();
			const anchor = new THREE.Vector3();

			const counted = slug => sources[slug].parts.reduce((sum, part) => {
				const count = (part.geometry.index ? part.geometry.index.count : part.geometry.attributes.position.count);
				sum[part.name ?? "tronçon"] = (sum[part.name ?? "tronçon"] ?? 0) + count;
				return sum;
			}, {});

			const spans = ranges(decor).map(([from, to], k) => {

				const kinds = {};
				const lates = new Set();
				const drops = new Set();
				let far = 0;

				for(let i = from; i < to; i++) {
					kinds[season.getX(i)] = (kinds[season.getX(i)] ?? 0) + 1;
					lates.add(season.getY(i));
					if(season.getX(i) === D.SEASON_FRUIT) {
						drops.add(season.getZ(i));
						far = Math.max(far, point.fromBufferAttribute(geometry.attributes.position, i).distanceTo(anchor.fromBufferAttribute(geometry.attributes.anchor, i)));
					}
				}

				return { slug: decor.laid[k][0], kinds, lates: [...lates], drops: [...drops], far };

			});

			emit({ spans, fruit: counted("haie.baies").fruit, wood: counted("haie.bocage").arbre, head: counted("haie.bocage").feuillage, hedge: D.SEASON_HEDGE, fruits: D.SEASON_FRUIT });
		');

		$lates = [];
		$drops = [];
		$forms = [];

		foreach($result['spans'] as $span) {

			$forms[$span['slug']] = TRUE;
			$lates[$span['lates'][0]] = TRUE;

			if($span['slug'] === 'haie.baies') {
				$this->assertTrue(count($span['drops']) === 1, 'les baies d\'une travée tombent ensemble, comme les fruits d\'un arbre');
				$drops += array_fill_keys(array_map(strval(...), $span['drops']), TRUE);
			}

			$this->assertTrue(count($span['lates']) === 1, 'une travée a un seul retard');
			$this->assertTrue(($span['kinds'][$result['hedge']] ?? 0) > 0, 'le feuillage de chaque travée suit la saison de la haie');
			$this->assertTrue($span['far'] < 3, 'un fruit se réduit vers un point de sa propre travée, pas vers l\'origine du plan');

			$fruit = $span['kinds'][$result['fruits']] ?? 0;
			$still = $span['kinds'][0] ?? 0;

			match($span['slug']) {
				'haie.baies' => $this->assertEquals($result['fruit'], $fruit, 'les baies sont les seuls fruits, et le sont toutes'),
				'haie.bocage' => $this->assertEquals([0, $result['wood']], [$fruit, $still], 'le bois de l\'arbre garde sa couleur, tronc, charpentières et rameaux, et sa tête ternit avec la haie'),
				default => $this->assertEquals([0, 0], [$fruit, $still], 'une travée sans baie ni bois n\'a que du feuillage')
			};

		}

		$this->assertTrue(isset($forms['haie.baies']) and isset($forms['haie.bocage']), 'la graine du test pose des baies et un arbre');
		$this->assertTrue(count($lates) > 1, 'les travées ne perdent pas toutes leurs fruits le même jour');
		$this->assertTrue(count($drops) > 1, 'et deux travées de baies ne tombent pas au même rang de la chute');

	}

	public function testStripAndFenceDrawNothing(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const posts = line(12, 3.7);

			const lay = (piece, seed) => {
				const decor = rig(ALL);
				const geometry = decor.spans(piece, posts, seed).geometry;
				return { print: print(geometry), laid: decor.laid, season: geometry.attributes.season !== undefined && Array.from(geometry.attributes.season.array).some((value, i) => i % 3 === 0 && value !== 0) };
			};

			emit({ strip: [lay(D.strip(), 1), lay(D.strip(), 99)], fence: [lay(D.fence("paling"), 1), lay(D.fence("paling"), 99)], greens: D.GREENS.length });
		');

		$this->assertEquals($result['strip'][0]['print'], $result['strip'][1]['print'], 'une bande fleurie ne dépend pas de la graine');
		$this->assertEquals($result['fence'][0]['print'], $result['fence'][1]['print'], 'une clôture non plus');
		$this->assertEquals(array_map(fn($k) => ['bande-fleurie', $k % $result['greens']], range(1, 11)), $result['strip'][0]['laid'], 'une bande fleurie garde ses verts à tour de rôle');
		$this->assertTrue($result['strip'][0]['season'] === FALSE, 'une bande fleurie garde ses fleurs toute l\'année');
		$this->assertEquals(['cloture.ganivelle', 'cloture.ganivelle.bout'], array_values(array_unique(array_column($result['fence'][0]['laid'], 0))), 'une clôture ne pose que sa travée et son piquet');

	}

	public function testHedgedSaysWhoDrawsFromItsSeed(): void {

		$result = $this->run('
			const D = MapSceneDecor;
			const traced = (kit, model = null) => D.hedged({ def: D.KITS[kit], model });
			const enclosed = (model, hedge) => D.hedged({ def: { enclosure: true }, model, hedge });

			emit({ hedge: traced("haie"), strip: traced("bande-fleurie"), fence: traced("cloture", "paling"), path: D.hedged({ def: D.KITS.chemin, model: null }), both: enclosed("paling", true), alone: enclosed(null, false), bare: enclosed("paling", false) });
		');

		$this->assertEquals(['hedge' => TRUE, 'strip' => FALSE, 'fence' => FALSE, 'path' => FALSE, 'both' => TRUE, 'alone' => TRUE, 'bare' => FALSE], $result, 'seuls une haie tracée et un enclos qui porte une haie tirent leurs formes d\'une graine');

	}

	public function testHedgeTakesItsSeedWhenLaid(): void {

		$result = $this->run('
			const D = MapSceneDecor;

			// La pose réduite à ce que `shapeTrace()` lit : elle note la graine de l\'élément à chaque fois qu\'il se monte
			const pose = (e, points, elements = 3) => {

				const seeds = [];
				const decor = Object.assign(Object.create(D.prototype), {
					elements: Array(elements).fill(null), sheet: { dataset: { length: "" } }, refusals: {},
					build: built => seeds.push(built.seed), tell: () => {}, refresh: () => {}
				});

				// Le tracé tel qu\'il avance : son deuxième point au pointeur, puis posé, puis un troisième
				for(const [kept, cursor] of [[1, points[1]], [2, null], [2, points[2]], [3, null]]) {
					decor.tracing = { e, points: points.slice(0, kept), cursor, freehand: null, strokes: [] };
					decor.shapeTrace();
				}

				return seeds;

			};

			const points = [{ x: 12, n: -40 }, { x: 18, n: -40 }, { x: 18, n: -31 }];
			const seeds = [];

			for(let x = 0; x < 40; x++) {
				for(let rank = 0; rank < 5; rank++) {
					seeds.push(D.hedgeSeed({ x: x * .5, n: -x * 1.5 }, rank));
				}
			}

			// Ce que `makeTrace()` passe à `spans()` pour une haie qui porte une graine, un id, ou ni l\'un ni l\'autre
			const drawn = (seed, id) => {

				const decor = rig(ALL);
				let passed = null;

				decor.spans = (piece, posts, given) => {
					passed = given;
					return { geometry: null, height: 1 };
				};

				decor.makeTrace({ ...D.element("haie", points[0]), trace: [{ x: -6, n: 0 }, { x: 6, n: 0 }], seed, id });

				return passed;

			};

			emit({
				drawn: [drawn(5, 12), drawn(null, 12), drawn(null, null)],
				apart: new Set([D.hedgeSeed({ x: 3, n: 5 }, 0), D.hedgeSeed({ x: 3, n: 6 }, 0), D.hedgeSeed({ x: 4, n: 5 }, 0), D.hedgeSeed({ x: -3, n: -5 }, 0)]).size,
				hedge: pose(D.element("haie", points[0]), points),
				again: pose(D.element("haie", points[0]), points),
				next: pose(D.element("haie", points[0]), points, 4),
				moved: pose(D.element("haie", points[1]), [points[1], points[0], points[2]]),
				known: pose({ ...D.element("haie", points[0]), id: 12 }, points),
				kept: pose({ ...D.element("haie", points[0]), seed: 5 }, points),
				fence: pose(D.element("cloture", points[0]), points),
				ranged: seeds.every(seed => Number.isInteger(seed) && seed >= 0 && seed <= 2147483647),
				distinct: new Set(seeds).size
			});
		');

		$this->assertTrue($result['hedge'][0] !== NULL, 'une haie neuve tire sa graine dès qu\'elle se monte, avant d\'avoir un id');
		$this->assertEquals(1, count(array_unique($result['hedge'])), 'et la garde tant qu\'on la trace : sa forme ne change pas à Terminer');
		$this->assertEquals($result['hedge'], $result['again'], 'la même pose redonne la même graine');
		$this->assertTrue($result['next'][0] !== $result['hedge'][0], 'une haie posée au même point après une autre n\'est pas sa jumelle');
		$this->assertTrue($result['moved'][0] !== $result['hedge'][0], 'une haie qui part d\'un autre point tire une autre graine');
		$this->assertEquals([NULL, NULL, NULL, NULL], $result['known'], 'une haie déjà enregistrée sans graine suit toujours son id');
		$this->assertEquals([5, 5, 5, 5], $result['kept'], 'une graine déjà portée ne se retire pas');
		$this->assertEquals([NULL, NULL, NULL, NULL], $result['fence'], 'une clôture ne tire pas de graine');
		$this->assertTrue($result['ranged'], 'la graine tient dans ce que la base retient, un entier positif de 31 bits');
		$this->assertEquals(200, $result['distinct'], 'deux points ou deux rangs voisins ne donnent pas la même graine');
		$this->assertEquals(4, $result['apart'], 'ni deux points qui ne diffèrent que d\'un côté, ni deux points opposés par l\'origine du plan');
		$this->assertEquals([5, 12, 0], $result['drawn'], 'la haie se trace de sa graine avant son id : celle tirée à la pose reste la sienne quand l\'id arrive');

	}

	/**
	 * Joue un scénario sous node. Il dispose de :
	 * - `sources`, ce que rend `build()` pour chaque modèle de `ALL`, les formes de la haie, la bande fleurie et une clôture ;
	 * - `rig(slugs, anchored)`, un `MapSceneDecor` réduit à ce que `spans()` lit, dont l'index ne connait que `slugs`, sans leurs
	 *   ancres si `anchored` est faux, et qui note dans `laid` la forme et le vert de chaque travée posée ;
	 * - `line(count, gap)`, `count` piquets en ligne droite sur X, loin de l'origine, à `gap` mètres l'un de l'autre ;
	 * - `ranges(decor)`, la plage de sommets de chaque travée dans la géométrie fusionnée ;
	 * - `print(geometry)`, l'empreinte de ses positions et de ses couleurs.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = require("node:fs");
				const { createHash } = require("node:crypto");
				const vm = require("node:vm");

				const ALL = ["haie", "haie.baies", "haie.charmille", "haie.charmille.bout", "haie.bocage", "bande-fleurie", "cloture.ganivelle", "cloture.ganivelle.bout"];

				// D\'un seul tenant : sous node 18, un script lu sur l\'entrée standard qui attend ses imports un par un tombe en
				// erreur de segmentation au-delà d\'une dizaine
				const [THREE, { mergeGeometries }, helpers, ...modules] = await Promise.all([
					"static/lib/three-0.170.0.module.min.js", "static/lib/three-0.170.0.BufferGeometryUtils.js", "model/helpers.js", ...ALL.map(slug => "model/" + slug + ".js")
				].map(file => import("file://" + directory + "/base/scene/" + file)));

				const sources = Object.fromEntries(ALL.map((slug, k) => [slug, modules[k].build(THREE, helpers)]));

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console });
				const { MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneDecor })", context);

				// Un modèle fondu comme figure.prop() le rend d\'un modèle cuit : sans index, les pièces dans l\'ordre, chaque sommet à
				// la couleur de sa pièce
				const fuse = model => mergeGeometries(model.parts.map(part => {

					const piece = part.geometry.index ? part.geometry.toNonIndexed() : part.geometry.clone();

					if(piece.attributes.normal === undefined) {
						piece.computeVertexNormals();
					}

					const geometry = new THREE.BufferGeometry();
					const color = new THREE.Color(part.color);

					geometry.setAttribute("position", piece.attributes.position);
					geometry.setAttribute("normal", piece.attributes.normal);
					geometry.setAttribute("color", new THREE.Float32BufferAttribute(Array.from({ length: piece.attributes.position.count }, () => color.toArray()).flat(), 3));

					return geometry;

				}));

				const rig = (slugs, anchored = true) => {

					const models = {};
					const fused = {};

					for(const slug of slugs) {

						const model = sources[slug];

						fused[slug] = fuse(model);
						fused[slug].computeBoundingBox();

						models[slug] = {
							d: model.d, h: model.h,
							box: [fused[slug].boundingBox.min.toArray(), fused[slug].boundingBox.max.toArray()],
							parts: model.parts.map(part => ({ name: part.name, count: (part.geometry.index ? part.geometry.index.count : part.geometry.attributes.position.count) }))
						};

						if(anchored && model.anchors !== undefined) {
							models[slug].anchors = model.anchors;
						}

					}

					const decor = Object.assign(Object.create(MapSceneDecor.prototype), {
						THREE, addons: { mergeGeometries }, baked: { index: { models } }, geometry: { relief: null }, material: null, geometries: new Map(), laid: [],
						sizes: Object.fromEntries(slugs.map(slug => [slug, fused[slug].attributes.position.count])),
						figure: { prop: (baked, slug) => ({ geometry: fused[slug].clone() }) }
					});

					const base = decor.base;

					decor.base = (slug, green, centered) => {
						decor.laid.push([slug, green]);
						return base.call(decor, slug, green, centered);
					};

					return decor;

				};

				const line = (count, gap) => Array.from({ length: count }, (_, k) => ({ x: 200 + k * gap, n: 50 }));

				const ranges = decor => {
					let from = 0;
					return decor.laid.map(([slug]) => [from, from += decor.sizes[slug]]);
				};

				const print = geometry => createHash("sha1").update(Buffer.from(geometry.attributes.position.array.buffer)).update(Buffer.from(geometry.attributes.color.array.buffer)).digest("hex");

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
?>
