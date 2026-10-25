<?php
namespace map;

/**
 * Les animaux de la scène (`static/js/sceneFauna.js`, `MapSceneFauna`, et son hérisson `static/js/sceneHedgehog.js`,
 * `MapSceneHedgehog`) et la saison et la taille des insectes (`static/js/scene.js`, `MapSceneInsects`), éprouvés sous node tels
 * que le navigateur charge les fichiers : qui se montre selon le décor et la semaine, à quelle taille, deux oiseaux jamais en vol
 * ensemble, un hérisson qui longe sa haie sans monter sur une planche.
 *
 * Aucune écriture, aucune base ouverte : le décor est écrit dans le scénario, en mètres, et un personnage monté est esquissé
 * par la géométrie fondue de ses pièces. L'horloge est celle que le scénario passe à `frame()`, par pas d'un dixième de seconde.
 *
 * Lancement : dev/test package=map file=FaunaAi
 */
class FaunaAiTest extends \Test {

	public function testNobodyWithoutTreeHedgeOrStrip(): void {

		$result = $this->run('
			// Un rocher et des buissons ne sont ni un arbre ni une haie
			const scene = stage([wild("rocher", 0, 0), thicket(4, 2)]);
			play(scene, 0, 20, 20);

			emit({ shown: shown(scene), meshes: scene.parent.children.length });
		');

		$this->assertEquals([FALSE, FALSE, FALSE], $result['shown'], 'sans arbre, haie ni bande fleurie, ni merle, ni rouge-gorge, ni hérisson');
		$this->assertEquals(5, $result['meshes'], 'les cinq montages restent dans la scène, masqués : deux par oiseau, un pour le hérisson');

	}

	public function testBirdsStayAroundTreesAllYear(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0), tree(10, 5)]);
			play(scene, 0, 1, 20);

			const away = scene.fauna.birds.map(bird => Math.round(Math.hypot(bird.place.p.x - bird.place.tree.p.x, bird.place.p.n - bird.place.tree.p.n) * 100) / 100);
			const summer = shown(scene);

			play(scene, 1, 100, 50);

			emit({ summer, winter: shown(scene), away, reach: scene.fauna.habitat().trees[0].reach, scales: scene.fauna.birds.map(bird => bird.scale) });
		');

		$this->assertEquals([TRUE, TRUE, FALSE], $result['summer'], 'avec deux arbres et aucune haie, les deux oiseaux sont posés dès la première image et le hérisson est absent');
		$this->assertEquals([TRUE, TRUE, FALSE], $result['winter'], 'en semaine 50, le merle et le rouge-gorge sont toujours là');

		foreach($result['away'] as $position => $away) {
			$this->assertTrue($away >= $result['reach'] + 0.3 * $result['scales'][$position] - 0.01 and $away <= $result['reach'] + 0.9 * $result['scales'][$position] + 0.01, 'un oiseau se pose au bord de la cime, pas dessous : '.$away.' m du pied pour une cime de '.$result['reach'].' m');
		}

	}

	public function testBirdsNeverFlyTogether(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0), tree(10, 5), tree(-8, -6)]);

			const together = [];
			const flights = [[], []];
			const flying = [false, false];

			play(scene, 0, 1200, 20, time => {

				const now = scene.fauna.birds.map(bird => bird.flight !== null);

				if(now[0] && now[1]) {
					together.push(time);
				}

				now.forEach((value, i) => {
					if(value !== flying[i]) {
						flights[i].push(time);
						flying[i] = value;
					}
				});

			});

			// Un envol et une pose alternent : le temps posé va d’une pose à l’envol suivant
			const posed = flights.map(times => times.slice(1).filter((_, i) => i % 2 === 1).map((time, i) => Math.round((time - times[2 * i + 1]) * 10) / 10));
			const aloft = flights.map(times => times.filter((_, i) => i % 2 === 1).map((time, i) => Math.round((time - times[2 * i]) * 10) / 10));

			// Le délai du rouge-gorge tombe pendant le vol du merle : il attend sa pose, puis part
			const pair = stage([tree(0, 0), tree(10, 5), tree(-8, -6)]);
			play(pair, 0, 1, 20);
			pair.fauna.birds[0].wait = 0;
			pair.fauna.birds[1].wait = .5;
			let overlap = 0;
			let waited = null;
			let landed = null;

			play(pair, 1, 12, 20, time => {

				const [merle, robin] = pair.fauna.birds.map(bird => bird.flight !== null);

				if(merle && robin) {
					overlap++;
				}
				if(landed === null && merle === false && time > 1.2) {
					landed = time;
				}
				if(waited === null && robin) {
					waited = time;
				}

			});

			emit({ overlap, waited, landed, together: together.length, counts: flights.map(times => Math.floor(times.length / 2)), first: flights.map(times => times[0]), posed, aloft });
		');

		$this->assertEquals(0, $result['together'], 'les deux oiseaux ne sont jamais en vol à la même image');
		$this->assertEquals(0, $result['overlap'], 'un délai qui tombe pendant le vol de l\'autre ne fait pas partir le second');
		$this->assertTrue($result['waited'] !== NULL and $result['landed'] !== NULL and $result['waited'] >= $result['landed'], 'le second attend la pose du premier, puis s\'envole : pose à '.$result['landed'].' s, envol à '.$result['waited'].' s');

		foreach([0 => 'le merle', 1 => 'le rouge-gorge'] as $position => $bird) {

			$this->assertTrue($result['counts'][$position] >= 18 and $result['counts'][$position] <= 40, 'en vingt minutes, '.$bird.' s\'envole toutes les 30 à 60 secondes : '.$result['counts'][$position].' envols');
			$this->assertTrue($result['first'][$position] >= 30 and $result['first'][$position] <= 66.2, 'le premier envol se tire comme les suivants : '.$result['first'][$position].' s');
			$this->assertTrue(min($result['posed'][$position]) >= 30, $bird.' reste posé au moins 30 secondes : '.min($result['posed'][$position]));
			// Au plus 60 secondes, plus le vol de l'autre qu'il attend, de 6 secondes au plus
			$this->assertTrue(max($result['posed'][$position]) <= 66.2, $bird.' reste posé au plus 60 secondes, et le temps du vol de l\'autre : '.max($result['posed'][$position]));
			$this->assertTrue(min($result['aloft'][$position]) >= 2.5 and max($result['aloft'][$position]) <= 6.1, 'un vol dure de 2,5 à 6 secondes : '.min($result['aloft'][$position]).' à '.max($result['aloft'][$position]));

		}

	}

	public function testBirdIsNeverBehindAnotherCrown(): void {

		$result = $this->run('
			const grove = [];
			for(let x = -6; x <= 6; x += 2) {
				for(let n = -6; n <= 6; n += 2) {
					grove.push(tree(x, n));
				}
			}

			const scene = stage(grove);
			// La caméra du banc regarde depuis les x croissants et les n décroissants
			const toward = { x: Math.SQRT1_2, n: -Math.SQRT1_2 };
			const trees = scene.fauna.habitat().trees;
			const hidden = [];
			let places = 0;

			for(let minute = 0; minute < 10; minute++) {

				play(scene, minute * 60, minute * 60 + 60, 20);

				for(const bird of scene.fauna.birds) {
					if(bird.place !== null) {
						places++;
						if(MapSceneFauna.shaded(bird.place.p, trees, toward)) {
							hidden.push([bird.place.p.x, bird.place.p.n]);
						}
					}
				}

			}

			const reach = trees[0].reach;

			emit({ places, hidden, behind: MapSceneFauna.shaded({ x: -2, n: 2 }, [{ p: { x: 0, n: 0 }, reach }], toward), front: MapSceneFauna.shaded({ x: 2, n: -2 }, [{ p: { x: 0, n: 0 }, reach }], toward), aside: MapSceneFauna.shaded({ x: 2, n: 2 }, [{ p: { x: 0, n: 0 }, reach }], toward) });
		');

		$this->assertEquals([TRUE, FALSE, FALSE], [$result['behind'], $result['front'], $result['aside']], 'une cime cache le sol derrière elle, ni devant ni à côté');
		$this->assertTrue($result['places'] >= 18, 'dans un bosquet de 49 arbres serrés, les oiseaux trouvent une place à presque chaque minute : '.$result['places'].' relevées sur 20');
		$this->assertEquals([], $result['hidden'], 'aucune place n\'est derrière la cime d\'un autre arbre du bosquet');

	}

	public function testBirdLeavesHiddenTree(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0), tree(10, 5)]);
			play(scene, 0, 1, 20);

			// Le calque est masqué, ou les arbres en ruine : le décor ne les montre plus
			scene.hidden = true;
			play(scene, 1, 2, 20);
			const kept = shown(scene);
			play(scene, 2, 70, 20);

			const gone = shown(scene);

			scene.hidden = false;
			play(scene, 70, 73, 20);

			emit({ kept, gone, back: shown(scene) });
		');

		$this->assertEquals([TRUE, TRUE, FALSE], $result['kept'], 'un oiseau ne relit le décor qu\'à sa décision : il reste jusqu\'à elle près d\'un arbre qu\'on vient de masquer');
		$this->assertEquals([FALSE, FALSE, FALSE], $result['gone'], 'à sa décision suivante, sans arbre montré, il disparait');
		$this->assertEquals([TRUE, TRUE, FALSE], $result['back'], 'les arbres de nouveau montrés, il revient en moins de trois secondes');

	}

	public function testHedgehogFollowsItsHedgeInSeason(): void {

		$result = $this->run('
			// Une haie d’est en ouest, et une planche du côté de la caméra, à l’est : la trotte s’arrête avant elle
			const bed = { origin: { x: 5, n: -4 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, length: 6, width: 4 };
			const scene = stage([hedge("haie", [{ x: -12, n: 0 }, { x: 12, n: 0 }])], [bed]);

			const h = scene.fauna.hedgehog;
			const aside = [];
			const xs = [];
			const rests = [];
			let onBed = 0;
			let still = null;

			play(scene, 0, 600, 20, time => {

				if(h.path === null) {
					return;
				}

				aside.push(Math.abs(h.at.n));
				xs.push(h.at.x);

				if(h.at.x > 5 - .01 && h.at.x < 11 + .01 && h.at.n > -4 && h.at.n < 0) {
					onBed++;
				}

				const moving = (Math.abs(h.goal - h.s) > 1e-3);

				if(moving === false && still === null) {
					still = time;
				} else if(moving && still !== null) {
					rests.push(Math.round((time - still) * 10) / 10);
					still = null;
				}

			});

			const summer = shown(scene);
			const weeks = {};

			for(const week of [12, 13, 44, 45]) {
				play(scene, 600 + week * 10, 600 + week * 10 + 5, week);
				weeks[week] = h.figure.root.visible;
			}

			emit({ summer, weeks, aside: [Math.min(...aside), Math.max(...aside)], expected: .8 + .35 * h.scale, range: Math.max(...xs) - Math.min(...xs), onBed, rests: rests.slice(1), side: Math.sign(h.at.n) });
		');

		$this->assertEquals([FALSE, FALSE, TRUE], $result['summer'], 'avec une haie et aucun arbre, le hérisson est là en semaine 20 et aucun oiseau');
		$this->assertEquals([12 => FALSE, 13 => TRUE, 44 => TRUE, 45 => FALSE], $result['weeks'], 'le hérisson est absent de la semaine 45 à la semaine 12');
		$this->assertEquals([round($result['expected'], 3), round($result['expected'], 3)], [round($result['aside'][0], 3), round($result['aside'][1], 3)], 'il longe la haie à distance constante de son axe');
		$this->assertEquals(-1, $result['side'], 'du côté de la caméra, où la haie ne le cache pas');
		$this->assertTrue($result['range'] > 4, 'il se déplace le long de la haie : '.$result['range'].' m parcourus d\'un bout à l\'autre');
		$this->assertEquals(0, $result['onBed'], 'il ne monte jamais sur la planche');
		$this->assertTrue(count($result['rests']) >= 10, 'il s\'arrête et repart : '.count($result['rests']).' arrêts en dix minutes');
		$this->assertTrue(min($result['rests']) >= 5 and max($result['rests']) <= 15.2, 'un arrêt dure de 5 à 15 secondes : '.min($result['rests']).' à '.max($result['rests']));

	}

	public function testHedgehogAlsoFollowsStripAndEnclosureHedge(): void {

		$result = $this->run('
			const strip = stage([hedge("bande-fleurie", [{ x: -10, n: 2 }, { x: 10, n: 2 }])]);
			play(strip, 0, 5, 20);

			const square = [{ x: -6, n: -6 }, { x: 6, n: -6 }, { x: 6, n: 6 }, { x: -6, n: 6 }];
			const closed = stage([{ kit: "enclos", def: MapSceneDecor.KITS.enclos, root: {}, p: { x: 0, n: 0 }, ring: square, gate: .1, model: null, hedge: true, width: 12, depth: 12, angle: 0 }]);
			play(closed, 0, 5, 20);

			const fenced = stage([{ kit: "enclos", def: MapSceneDecor.KITS.enclos, root: {}, p: { x: 0, n: 0 }, ring: square, gate: .1, model: "sheep", hedge: false, width: 12, depth: 12, angle: 0 }]);
			play(fenced, 0, 5, 20);

			emit({ strip: shown(strip), closed: shown(closed), fenced: shown(fenced), lines: [strip, closed, fenced].map(scene => scene.fauna.habitat().lines.length) });
		');

		$this->assertEquals([1, 1, 0], $result['lines'], 'une bande fleurie et la haie d\'un enclos sont des lignes à longer, pas la clôture d\'un enclos sans haie');
		$this->assertEquals([FALSE, FALSE, TRUE], $result['strip'], 'le hérisson longe une bande fleurie');
		$this->assertEquals([FALSE, FALSE, TRUE], $result['closed'], 'il longe la haie d\'un enclos');
		$this->assertEquals([FALSE, FALSE, FALSE], $result['fenced'], 'un enclos clôturé sans haie ne l\'attire pas');

	}

	public function testWholePlanWhenFrameShowsNoTreeNorHedge(): void {

		$result = $this->run('
			// À 16 pixels par mètre le cadre montre 75 m sur 50 : les arbres et la haie sont à 400 m et plus
			const scene = stage([tree(400, 0), tree(900, 0), hedge("haie", [{ x: 390, n: 10 }, { x: 430, n: 10 }]), hedge("haie", [{ x: 890, n: 10 }, { x: 930, n: 10 }])]);
			play(scene, 0, 1, 20);

			const first = shown(scene);
			const h = scene.fauna.hedgehog;
			let away = 0;
			let leap = 0;
			let trotted = 0;
			let last = { ...h.at };

			// Une heure : chaque oiseau décide une soixantaine de fois, le hérisson plus de cent
			play(scene, 1, 3600, 20, () => {

				for(const bird of scene.fauna.birds) {
					away = Math.max(away, Math.hypot(bird.place.p.x - 400, bird.place.p.n));
				}

				const step = Math.hypot(h.at.x - last.x, h.at.n - last.n);
				leap = Math.max(leap, step);
				trotted += step;
				last = { ...h.at };

			});

			// Un arbre entre dans le cadre : il passe devant ceux du reste du plan
			const mixed = stage([tree(400, 0), tree(5, 5)]);
			play(mixed, 0, 1, 20);

			emit({ first, away: Math.round(away), hedgehog: [Math.round(h.at.x), Math.round(h.at.n)], leap: Math.round(leap * 100) / 100, trotted: Math.round(trotted), mixed: mixed.fauna.birds.map(bird => bird.place.tree.p.x) });
		');

		$this->assertEquals([TRUE, TRUE, TRUE], $result['first'], 'sans arbre ni haie dans le cadre, les trois prennent place ailleurs dans le plan');
		$this->assertTrue($result['away'] <= 10, 'en une heure, aucun oiseau ne quitte l\'arbre le plus proche du point regardé pour celui de 900 m : '.$result['away'].' m au plus loin de son pied');
		$this->assertTrue($result['hedgehog'][0] >= 385 and $result['hedgehog'][0] <= 435 and abs($result['hedgehog'][1] - 10) <= 3, 'le hérisson est le long de la haie la plus proche : '.implode(', ', $result['hedgehog']));
		$this->assertTrue($result['trotted'] >= 100, 'hors cadre il trotte toujours, pour que la vue qui arrive le trouve en chemin : '.$result['trotted'].' m en une heure');
		$this->assertTrue($result['leap'] <= .5, 'et il ne change jamais de place d\'un bond le long de sa haie : '.$result['leap'].' m au plus entre deux images');
		$this->assertEquals([5, 5], $result['mixed'], 'un arbre du cadre passe avant ceux du reste du plan');

	}

	public function testHedgehogOutOfFrameMovesOnlyToComeBackOrToACloserLine(): void {

		$result = $this->run('
			const aim = (scene, x, n) => {
				scene.controls.target.set(x, 0, -n);
				scene.camera.position.set(x + 300, 300, -n + 300);
				scene.camera.updateMatrixWorld();
			};

			// Une haie de 400 m, sondée tous les 80 m, dont le cadre ne montre rien : le hérisson en prend le point le plus proche
			const long = hedge("haie", [{ x: 200, n: 0 }, { x: 600, n: 0 }]);
			const far = hedge("haie", [{ x: -900, n: 0 }, { x: -860, n: 0 }]);
			const scene = stage([long, far]);
			const h = scene.fauna.hedgehog;
			play(scene, 0, 1, 20);
			const start = [h.e === long, Math.abs(h.at.x - 240) <= 2];

			const leap = (from, until) => {
				let most = 0;
				let last = { ...h.at };
				play(scene, from, until, 20, () => {
					most = Math.max(most, Math.hypot(h.at.x - last.x, h.at.n - last.n));
					last = { ...h.at };
				});
				return Math.round(most);
			};

			// Une demi-minute sans que la vue bouge : il trotte le long de sa haie, sans bond
			const still = leap(1, 30);

			// La vue vient sur l’autre bout de sa haie : il y rentre d’un bond
			aim(scene, 550, 0);
			const back = [leap(30, 600) >= 20, h.e === long, scene.fauna.seen(h.at)];

			// La vue part près de l’autre haie, sans la montrer : il la rejoint
			aim(scene, -700, 300);
			play(scene, 600, 1200, 20);

			emit({ start, still, back, moved: [h.e === far, h.at.x < -850] });
		');

		$this->assertEquals([TRUE, TRUE], $result['start'], 'hors du cadre, il part du point sondé le plus proche de la haie de 400 m, à 240 m');
		$this->assertEquals(0, $result['still'], 'tant que la vue ne bouge pas, il trotte sans bond');
		$this->assertEquals([TRUE, TRUE, TRUE], $result['back'], 'la vue venue sur l\'autre bout de sa haie, il y rentre d\'un bond et y reste');
		$this->assertEquals([TRUE, TRUE], $result['moved'], 'la vue partie près d\'une autre haie qu\'elle ne montre pas, il la rejoint');

	}

	public function testHedgehogKeepsItsPlaceWhenZoomChanges(): void {

		$result = $this->run('
			// Ouverture à 16 pixels par mètre, une haie de 40 m dans le cadre à tous les paliers
			const scene = stage([hedge("haie", [{ x: -20, n: 0 }, { x: 20, n: 0 }])]);
			const h = scene.fauna.hedgehog;
			play(scene, 0, 30, 20);

			let leap = 0;
			let last = { ...h.at };
			let time = 30;
			const scales = [];

			for(const zoom of [2.5, 2, 1.5, 2.5, 4, 2.5, 1.5, 2.5, 4]) {

				scene.camera.zoom = zoom;
				scene.camera.updateProjectionMatrix();

				play(scene, time, time + 30, 20, () => {
					leap = Math.max(leap, Math.hypot(h.at.x - last.x, h.at.n - last.n));
					last = { ...h.at };
				});

				time += 30;
				scales.push(Math.round(h.scale * 100) / 100);

			}

			emit({ leap: Math.round(leap * 100) / 100, scales, shown: shown(scene)[2] });
		');

		$this->assertEquals([4.33, 5.42, 6.14, 4.33, 3.9, 4.33, 6.14, 4.33, 3.9], $result['scales'], 'le grossissement du hérisson change bien à chaque palier');
		$this->assertTrue($result['shown'], 'il est toujours là');
		$this->assertTrue($result['leap'] <= 1.5, 'grossi ou réduit, il s\'écarte ou se rapproche de sa haie sans changer de place le long d\'elle : '.$result['leap'].' m au plus entre deux images');

	}

	public function testBirdsKeepApartByDrawnSize(): void {

		$result = $this->run('
			// Un seul arbre, une heure. De près le merle fait 1,05 m ; dans une vue lointaine les deux oiseaux font 1,7 m, leur plafond
			const apart = zoom => {

				const scene = stage([tree(0, 0)], [], { zoom, intro: null });
				let closest = Infinity;
				let landings = 0;
				let flying = false;

				play(scene, 0, 3600, 20, () => {

					const [merle, robin] = scene.fauna.birds;
					const now = (merle.flight !== null || robin.flight !== null);

					if(flying && now === false) {
						landings++;
					}
					flying = now;

					if(now === false && merle.place !== null && robin.place !== null) {
						closest = Math.min(closest, Math.hypot(merle.place.p.x - robin.place.p.x, merle.place.p.n - robin.place.p.n));
					}

				});

				return { closest: Math.floor(closest * 100) / 100, landings, drawn: Math.round(Math.max(...scene.fauna.birds.map(bird => bird.size * bird.scale)) * 100) / 100 };

			};

			emit({ near: apart(4), far: apart(1.5) });
		');

		$this->assertTrue($result['near']['landings'] >= 100, 'en une heure autour d\'un seul arbre, les oiseaux se posent plus de cent fois : '.$result['near']['landings']);
		$this->assertEquals([1.05, 1.7], [$result['near']['drawn'], $result['far']['drawn']], 'le plus grand des deux oiseaux fait 1,05 m de près et 1,7 m dans une vue lointaine');
		$this->assertTrue($result['near']['closest'] >= 1.56, 'de près, jamais à moins d\'une fois et demie la taille du merle : '.$result['near']['closest'].' m au plus près');
		$this->assertTrue($result['far']['closest'] >= 2.55, 'grossis, l\'écart suit : '.$result['far']['closest'].' m au plus près pour 2,55 m attendus');

	}

	public function testFruitTreesArePerches(): void {

		$result = $this->run('
			// Ni arbre ni haie du décor : un rang de trois fruitiers cultivés, que la scène pose à la date affichée
			const scene = stage([]);
			scene.blooms.set(7, { date: "2026-06-01", greenhouse: false, perches: [{ x: -6, n: 0, reach: 1.5 }, { x: 0, n: 0, reach: 1.5 }, { x: 6, n: 0, reach: 1.5 }] });
			scene.blooms.set(8, { date: "2026-01-15", greenhouse: false, perches: [{ x: 300, n: 0, reach: 1.5 }] });
			play(scene, 0, 1, 20);

			const places = scene.fauna.birds.map(bird => [Math.round(Math.min(...[-6, 0, 6].map(x => Math.hypot(bird.place.p.x - x, bird.place.p.n))) * 10) / 10, bird.place.tree.e]);
			const first = shown(scene);

			// La frise passe à une date où ce rang n’est plus rendu : ses places se perdent à la décision suivante
			scene.date = "2026-06-08";
			play(scene, 1, 120, 20);

			// Une haie voisine : le hérisson la longe, jamais le rang de fruitiers
			const hedged = stage([hedge("haie", [{ x: -5, n: -8 }, { x: 5, n: -8 }])]);
			hedged.blooms.set(7, { date: "2026-06-01", greenhouse: false, perches: [{ x: 0, n: 0, reach: 1.5 }] });
			play(hedged, 0, 1, 20);

			// Un rang de fruitiers sous abri : l’abri cacherait l’oiseau posé à son pied
			const sheltered = stage([]);
			sheltered.blooms.set(7, { date: "2026-06-01", greenhouse: true, perches: [{ x: 0, n: 0, reach: 1.5 }] });
			play(sheltered, 0, 1, 20);

			emit({ first, places, sheltered: shown(sheltered), gone: shown(scene), trees: scene.fauna.habitat().trees.length, hedged: [shown(hedged), Math.round(hedged.fauna.hedgehog.at.n)] });
		');

		$this->assertEquals([TRUE, TRUE, FALSE], $result['first'], 'autour de fruitiers cultivés les deux oiseaux se posent, le hérisson non');
		$this->assertTrue($result['places'][0][0] >= 1.5 and $result['places'][0][0] <= 6 and $result['places'][1][0] >= 1.5 and $result['places'][1][0] <= 6, 'ils sont au bord de la cime d\'un fruitier du rang : '.$result['places'][0][0].' et '.$result['places'][1][0].' m de son pied');
		$this->assertEquals([7, 7], [$result['places'][0][1], $result['places'][1][1]], 'et jamais près du rang rendu pour une autre date');
		$this->assertEquals([[FALSE, FALSE, FALSE], 0], [$result['gone'], $result['trees']], 'un rang que la date affichée ne rend plus ne porte plus personne');
		$this->assertEquals([FALSE, FALSE, FALSE], $result['sheltered'], 'un rang de fruitiers sous abri ne porte personne');
		$this->assertEquals([[TRUE, TRUE, TRUE], -8], [$result['hedged'][0], abs($result['hedged'][1]) > 6 ? -8 : 0], 'avec une haie, le hérisson est le long d\'elle');

	}

	public function testUsefulFrameExcludesPanels(): void {

		$result = $this->run('
			// Canevas de 800 px à 16 pixels par mètre : la rangée des réglages couvre les 60 px du haut, la frise le bas à partir de 600
			const covers = [[16, 16, 256, 60], [755, 16, 1406, 60], [16, 600, 1406, 760]];
			const toward = { x: Math.SQRT1_2, n: -Math.SQRT1_2 };
			const aside = { x: Math.SQRT1_2, n: Math.SQRT1_2 };
			const at = (t, u) => ({ x: toward.x * t + aside.x * u, n: toward.n * t + aside.n * u });
			const row = p => Math.round((1 - new THREE.Vector3(p.x, 0, -p.n).project(scene.camera).y) / 2 * 800);

			// Un arbre sous la frise, plus près du point regardé qu’un arbre du cadre utile, un autre juste au-dessus d’elle, et une
			// haie qui traverse le canevas de haut en bas
			const under = at(32, 0);
			const edge = at(19, -20);
			const open = at(0, 35);
			let scene = stage([tree(under.x, under.n), tree(edge.x, edge.n), tree(open.x, open.n), hedge("haie", [at(-42, 10), at(42, 10)])], [], { zoom: 4, intro: null, height: 400 });

			// La scène s’ouvre sur un canevas de 400 px, que la frise couvre à partir de 300, puis prend ses 800 px sans que le zoom
			// bouge, comme au passage en plein écran : le cadre utile doit suivre
			scene.covers = [[16, 300, 1406, 380]];
			play(scene, -60, -1, 20);
			scene.canvas.clientHeight = 800;
			scene.covers = covers;
			scene.fauna.resize();

			const rows = { birds: [Infinity, -Infinity], hedgehog: [Infinity, -Infinity] };
			let nearUnder = Infinity;

			play(scene, 0, 3600, 20, () => {

				for(const bird of scene.fauna.birds) {
					if(bird.flight === null && bird.place !== null) {
						const y = row(bird.place.p);
						rows.birds = [Math.min(rows.birds[0], y), Math.max(rows.birds[1], y)];
						nearUnder = Math.min(nearUnder, Math.hypot(bird.place.p.x - under.x, bird.place.p.n - under.n));
					}
				}

				const y = row(scene.fauna.hedgehog.at);
				rows.hedgehog = [Math.min(rows.hedgehog[0], y), Math.max(rows.hedgehog[1], y)];

			});

			emit({ rows, nearUnder: Math.round(nearUnder), trees: [row(under), row(edge), row(open)], shown: shown(scene) });
		');

		$this->assertEquals([696, 576, 400], $result['trees'], 'le premier arbre est sous la frise, le deuxième juste au-dessus, le troisième au milieu du canevas');
		$this->assertEquals([TRUE, TRUE, TRUE], $result['shown'], 'les trois ont une place');
		$this->assertTrue($result['rows']['birds'][0] >= 60 and $result['rows']['birds'][1] <= 600, 'en une heure, aucun oiseau ne se pose sous la rangée des réglages ni sous la frise, même au pied de l\'arbre qui la borde : lignes '.implode(' à ', $result['rows']['birds']).' du canevas');
		$this->assertTrue($result['nearUnder'] >= 10, 'ni près de l\'arbre que la frise couvre, pourtant le plus proche du point regardé : '.$result['nearUnder'].' m');
		$this->assertTrue($result['rows']['hedgehog'][0] >= 60 and $result['rows']['hedgehog'][1] <= 600, 'le hérisson ne trotte pas dessous non plus : lignes '.implode(' à ', $result['rows']['hedgehog']));

	}

	public function testScaleFollowsZoomBetweenFloorAndCap(): void {

		$result = $this->run('
			// À 40 pixels par mètre, la taille naturelle de chacun passe ses 12 pixels
			const scene = stage([tree(0, 0), hedge("haie", [{ x: -3, n: -4 }, { x: 3, n: -4 }])], [], { zoom: 10, intro: null });
			let reads = 0;
			let height = 800;
			Object.defineProperty(scene.canvas, "clientHeight", { get: () => { reads++; return height; } });

			const at = (zoom, seconds = 30) => {
				scene.camera.zoom = zoom;
				scene.camera.updateProjectionMatrix();
				const before = reads;
				let drawn = false;
				play(scene, at.time, at.time + seconds, 20, () => {
					drawn = drawn || shown(scene).some(visible => visible);
				});
				at.time += seconds;
				const perMetre = height * zoom / 200;
				const subjects = [...scene.fauna.birds, scene.fauna.hedgehog];
				return {
					drawn,
					reads: reads - before,
					scales: subjects.map(subject => Math.round(subject.scale * 100) / 100),
					metres: subjects.map(subject => Math.round(subject.size * subject.scale * 100) / 100),
					pixels: subjects.map(subject => Math.round(subject.size * subject.scale * perMetre * 10) / 10),
					shown: shown(scene)
				};
			};
			at.time = 0;

			const near = at(10);
			const middle = at(2);

			// Le canevas perd la moitié de sa hauteur sans que le zoom bouge, comme un téléphone qu’on tourne
			height = 400;
			scene.fauna.resize();
			const resized = at(2);
			height = 800;
			scene.fauna.resize();

			// Deux minutes sous le seuil : chaque oiseau s’envole au moins une fois
			emit({ near, middle, resized, far: at(.9), lost: at(.88, 120), back: at(10) });
		');

		$this->assertEquals([3.9, 3.9, 3.9], $result['near']['scales'], 'de près, chacun a son grossissement naturel, 3 fois le 1,3 que ce banc donne aux paysans');
		$this->assertEquals([[12, 12, 12], [1.5, 1.5, 1.5], [TRUE, TRUE, TRUE]], [$result['middle']['pixels'], $result['middle']['metres'], $result['middle']['shown']], 'à 8 pixels par mètre, chacun grossit jusqu\'à 1,5 m pour tenir 12 pixels');
		$this->assertEquals([[1.7, 1.7, 1.7], [6.8, 6.8, 6.8], 1], [$result['resized']['metres'], $result['resized']['pixels'], $result['resized']['reads']], 'le canevas réduit de moitié sans que le zoom bouge, chacun grossit jusqu\'à son plafond, sur une seule lecture de la hauteur');
		$this->assertEquals([[1.7, 1.7, 1.7], [6.1, 6.1, 6.1], [TRUE, TRUE, TRUE]], [$result['far']['metres'], $result['far']['pixels'], $result['far']['shown']], 'à 3,6 pixels par mètre, tous sont au plafond de 1,7 m, font 6,1 pixels et se dessinent encore');
		$this->assertEquals([[1.7, 1.7, 1.7], [6, 6, 6], [FALSE, FALSE, FALSE]], [$result['lost']['metres'], $result['lost']['pixels'], $result['lost']['shown']], 'à 3,52 pixels par mètre ils feraient 5,98 pixels à leur plafond, juste sous le seuil de 6 : ni les oiseaux ni le hérisson ne se dessinent');
		$this->assertEquals(FALSE, $result['lost']['drawn'], 'ni posé ni en vol, pendant deux minutes où chaque oiseau s\'envole au moins une fois');
		$this->assertEquals([[3.9, 3.9, 3.9], [TRUE, TRUE, TRUE]], [$result['back']['scales'], $result['back']['shown']], 'la vue revenue, chacun reprend sa taille naturelle et se montre');
		$this->assertEquals([0, 1, 0, 0], [$result['middle']['reads'], $result['far']['reads'], $result['lost']['reads'], $result['back']['reads']], 'la hauteur du canevas ne se lit qu\'après resize(), une fois, et jamais à un changement de zoom, par image ni par décision : seul le palier qui suit le second resize() la relit, sur 301 images au moins jouées à chaque palier');

	}

	public function testSizeIsNaturalRaisedToPixelFloorUnderCap(): void {

		$result = $this->run('
			// Les hauteurs de canevas relevées dans Chrome à 360 et à 1199 px de large
			const heights = { 360: 350, 1199: 592 };
			const zooms = { near: 20, wide: 3 };
			const read = (scene, zoom) => {
				const perMetre = scene.canvas.clientHeight * zoom / 200;
				return [...scene.fauna.birds, scene.fauna.hedgehog].map(subject => [Math.round(subject.size * subject.scale * 100) / 100, Math.round(subject.size * subject.scale * perMetre * 10) / 10]);
			};
			const open = (height, zoom) => {
				const scene = stage([tree(0, 0), hedge("haie", [{ x: -3, n: -4 }, { x: 3, n: -4 }])], [], { zoom, intro: null, height });
				play(scene, 0, 1, 20);
				return scene;
			};

			const table = {};

			for(const [width, height] of Object.entries(heights)) {
				for(const [view, zoom] of Object.entries(zooms)) {
					table[width + " " + view] = read(open(height, zoom), zoom);
				}
			}

			// Une fenêtre ouverte étroite puis agrandie, et la même ouverte grande
			const grown = {};

			for(const [view, zoom] of Object.entries(zooms)) {
				const scene = open(heights[360], zoom);
				const narrow = read(scene, zoom);
				scene.canvas.clientHeight = heights[1199];
				scene.fauna.resize();
				play(scene, 1, 2, 20);
				grown[view] = [narrow, read(scene, zoom)];
			}

			// Le survol est parti de la moitié du zoom où il se pose : la caméra est encore à 1
			const far = stage([tree(0, 0)], [], { zoom: 1, intro: { zoom: 2 } });
			play(far, 0, 1, 20);
			const flying = shown(far);
			far.ambience.intro = null;
			far.camera.zoom = 2;
			far.camera.updateProjectionMatrix();
			play(far, 1, 2, 20);

			// Un canevas sans hauteur ne cadre rien : personne ne s’y montre avant qu’il en ait une
			const flat = stage([tree(0, 0)], [], { zoom: 2, intro: null, height: 0 });
			play(flat, 0, 1, 20);
			const waiting = shown(flat);
			flat.canvas.clientHeight = 800;
			flat.fauna.resize();
			play(flat, 1, 2, 20);

			emit({ table, grown, flying, landed: [shown(far), read(far, 2).map(size => size[1])], waiting, flat: shown(flat) });
		');

		// Dans l'ordre : le merle, le rouge-gorge, le hérisson, chacun en mètres puis en pixels
		$this->assertEquals([[1.05, 36.6], [.54, 18.8], [1.08, 37.8]], $result['table']['360 near'], 'de près à 360, à 35 pixels par mètre, chacun a sa taille naturelle et le rouge-gorge est plus petit que le merle');
		$this->assertEquals([[1.05, 61.9], [.54, 31.9], [1.08, 64]], $result['table']['1199 near'], 'de près à 1199, à 59,2 pixels par mètre, les mêmes tailles en mètres');
		$this->assertEquals([[1.7, 8.9], [1.7, 8.9], [1.7, 8.9]], $result['table']['360 wide'], 'en vue d\'ensemble à 360, à 5,25 pixels par mètre, 12 pixels demanderaient 2,29 m : chacun s\'arrête au plafond de 1,7 m');
		$this->assertEquals([[1.35, 12], [1.35, 12], [1.35, 12]], $result['table']['1199 wide'], 'en vue d\'ensemble à 1199, à 8,88 pixels par mètre, chacun tient son plancher de 12 pixels');

		foreach(['near', 'wide'] as $view) {
			$this->assertEquals($result['table']['360 '.$view], $result['grown'][$view][0], 'la fenêtre étroite, avant d\'être agrandie, est bien celle du tableau');
			$this->assertEquals($result['table']['1199 '.$view], $result['grown'][$view][1], 'une fenêtre ouverte étroite puis agrandie donne les tailles d\'une fenêtre ouverte grande');
		}

		$this->assertEquals([[FALSE, FALSE, FALSE], [[TRUE, TRUE, FALSE], [12, 12, 12]]], [$result['flying'], $result['landed']], 'personne ne prend place pendant le survol, dont le cadre est plus large que celui où il se pose ; quand il s\'achève les oiseaux se posent, à la taille du cadrage où il s\'est posé');
		$this->assertEquals([[FALSE, FALSE, FALSE], [TRUE, TRUE, FALSE]], [$result['waiting'], $result['flat']], 'sans hauteur de canevas personne ne se montre, et les oiseaux se posent quand elle vient');

	}

	public function testPlantPublishesItsFruitTreesAsPerches(): void {

		$result = $this->run('
			// plant() tel que la scène le joue, sur une planche de 12 m par 4 : seuls ses voisins sont esquissés, le calepinage (trois
			// pieds sur un rang), le registre des modèles et le tampon des instances
			const planted = [];
			const crops = Object.assign(Object.create(MapSceneCrops.prototype), {
				models: new Map([["pommier", { d: 3, h: 4, aligned: false, bare: null, picked: null, dressed: null }], ["carotte", { d: .2, h: .3, aligned: false, bare: null, picked: null, dressed: null }]]),
				crop: { layout: model => ({ size: { d: model.d, h: model.h }, pitch: 4, band: .5, count: 3, rows: 1 }), scatter: () => 0, ridge: () => null },
				woody: entry => entry.family === "tree",
				fruit: { underground: new Set() },
				first: "2020-01-01",
				blooms: new Map(),
				instance: (instances, key, x, top, n, d) => planted.push({ key, x, n, d })
			});

			const bed = greenhouse => ({ whole: null, rectangle: { width: 4, length: 12, origin: { x: -6, n: -2 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, greenhouse } });
			const series = (slug, family) => ({ plant: slug, pulled: false, entry: { slug, family }, timeline: { kind: "planting", real: { start: "2021-03-01", harvestFirst: null } } });
			const plant = (id, slug, family, greenhouse) => crops.plant({ instances: [], fruits: [], fruitKeys: [], ridges: [] }, { id }, series(slug, family), { key: "growing", growth: 1, progress: .2 }, bed(greenhouse), 0, 12, 0, "2026-06-01", "2026-06-01", false);

			plant(1, "pommier", "tree", false);
			const trees = planted.splice(0);
			const perches = crops.blooms.get(1).perches;

			plant(2, "carotte", "vegetable", false);
			plant(3, "pommier", "tree", true);

			// Les oiseaux lisent ce que plant() a publié, sans arbre ni haie du décor
			const scene = stage([]);
			scene.blooms.set(1, crops.blooms.get(1));
			play(scene, 0, 1, 20);

			const round = value => Math.round(value * 100) / 100;

			emit({
				trees: trees.map(tree => [tree.key, round(tree.x), round(tree.n), round(tree.d / 2)]),
				perches: perches.map(perch => ["pommier", round(perch.x), round(perch.n), round(perch.reach)]),
				others: [crops.blooms.get(2).perches.length, crops.blooms.get(3).perches.length, crops.blooms.get(3).greenhouse],
				shown: shown(scene),
				away: scene.fauna.birds.map(bird => round(Math.hypot(bird.place.p.x - bird.place.tree.p.x, bird.place.p.n - bird.place.tree.p.n) - bird.place.tree.reach))
			});
		');

		$this->assertEquals(3, count($result['trees']), 'plant() pose trois pommiers sur la planche');
		$this->assertEquals($result['trees'], $result['perches'], 'et publie pour chacun un perchoir à son pied, du rayon de sa cime');
		$this->assertEquals([0, 3, TRUE], $result['others'], 'une culture qui n\'est pas un arbre ne publie aucun perchoir, et un rang sous abri publie les siens avec son abri');
		$this->assertEquals([TRUE, TRUE, FALSE], $result['shown'], 'les deux oiseaux se posent autour de ces pommiers');
		$this->assertTrue(min($result['away']) >= .3 * 3.9 - .01 and max($result['away']) <= .9 * 5.44, 'au bord de leur cime, de 0,3 à 0,9 fois leur grossissement au-delà : '.implode(' et ', $result['away']).' m au-delà');

	}

	public function testButterfliesSeasonAndSize(): void {

		$result = $this->run('
			const blooms = new Map([[1, { date: "2026-06-01", a: { x: -3, n: 0 }, b: { x: 3, n: 0 }, top: .5, height: .4, greenhouse: false, perches: [] }]]);

			const insects = (zoom, height = 800) => {
				const view = lens({ zoom, intro: null, height });
				return { view, insects: new MapSceneInsects({ THREE, addons: { mergeGeometries: pieces => pieces[0] }, crops: { blooms }, geometry: { height: () => 0 }, scale: 1.3, canvas: view.canvas, parent: new THREE.Group(), gradientMap: null }) };
			};

			const I = MapSceneInsects;
			const drawn = {};
			const far = insects(5);

			for(const week of [13, 14, 44, 45]) {
				for(let i = 0; i < 30; i++) {
					far.insects.frame(week * 100000 + i * 100, "2026-06-01", week, far.view.camera, far.view.controls);
				}
				drawn[week] = far.insects.pieces[0].mesh.count > 0;
			}

			let reads = 0;
			let height = 800;
			Object.defineProperty(far.view.canvas, "clientHeight", { get: () => { reads++; return height; } });

			const size = () => [Math.round(I.BUTTERFLY_SPAN * far.insects.butterflyScale * 100) / 100, Math.round(I.BUTTERFLY_SPAN * far.insects.butterflyScale * height * far.view.camera.zoom / 200 * 10) / 10, far.insects.pieces[0].mesh.count > 0];

			const at = zoom => {
				far.view.camera.zoom = zoom;
				far.view.camera.updateProjectionMatrix();
				for(let i = 0; i < 30; i++) {
					far.insects.frame(9000000 + zoom * 100000 + i * 100, "2026-06-01", 20, far.view.camera, far.view.controls);
				}
				return size();
			};

			const sizes = [at(10), at(5), at(2.5), at(2), at(6.25), at(2.1), at(2.05), at(10)];

			// Le canevas perd la moitié de sa hauteur sans que le zoom bouge : 20 pixels par mètre au lieu de 40
			height = 400;
			far.insects.resize();
			far.insects.frame(9900000, "2026-06-01", 20, far.view.camera, far.view.controls);
			const resized = size();

			// Une fenêtre ouverte grande donne la même taille que celle-ci, ouverte grande puis réduite
			const small = insects(10, 400);
			small.insects.frame(1, "2026-06-01", 20, small.view.camera, small.view.controls);

			emit({ drawn, sizes, reads, resized, small: Math.round(I.BUTTERFLY_SPAN * small.insects.butterflyScale * 100) / 100 });
		');

		$this->assertEquals([13 => FALSE, 14 => TRUE, 44 => TRUE, 45 => FALSE], $result['drawn'], 'les papillons volent de la semaine 14 à la semaine 44');
		$this->assertEquals([.39, 15.6, TRUE], $result['sizes'][0], 'à 40 pixels par mètre, un papillon a son envergure naturelle, 0,39 m, soit 3 fois le 1,3 que ce banc donne aux paysans');
		$this->assertEquals([.6, 12, TRUE], $result['sizes'][1], 'à 20 pixels par mètre il grossit pour tenir 12 pixels, ce qui le porte à son plafond de 0,6 m');
		$this->assertEquals([.6, 6, TRUE], $result['sizes'][2], 'à 10 pixels par mètre il reste au plafond et fait 6 pixels');
		$this->assertEquals([0, 0, FALSE], $result['sizes'][3], 'à 8 pixels par mètre il ferait 4,8 pixels, sous ses 5 pixels : il ne se dessine pas');
		$this->assertEquals([.39, 15.6, TRUE], $result['sizes'][7], 'la vue revenue, il reprend sa taille naturelle');
		$this->assertEquals([.48, 12, TRUE], $result['sizes'][4], 'à 25 pixels par mètre il fait 0,48 m, entre sa taille naturelle et son plafond, et tient 12 pixels');
		$this->assertEquals([[.6, 5, TRUE], [0, 0, FALSE]], [$result['sizes'][5], $result['sizes'][6]], 'au bord du seuil, il se dessine encore à 5,04 pixels et plus à 4,92');
		$this->assertEquals(9, $result['reads'], 'la hauteur du canevas se lit une fois par changement de zoom ou de taille, jamais par image : 241 images jouées sur neuf paliers, redimensionnement compris');
		$this->assertEquals([[.6, 12, TRUE], .6], [$result['resized'], $result['small']], 'le canevas réduit de moitié sans que le zoom bouge, le papillon prend la taille d\'une scène ouverte à cette hauteur');

	}

	public function testNoInsectIsDrawnAsADisc(): void {

		$result = $this->run('
			const blooms = new Map([[1, { date: "2026-06-01", a: { x: -3, n: 0 }, b: { x: 3, n: 0 }, top: .5, height: .4, greenhouse: false, perches: [] }]]);
			const shapes = {};
			const fused = [];
			const melted = new Set();
			const points = pieces => pieces.map(geometry => geometry.attributes.position.count);

			for(const zoom of [1, 2.5, 5, 20]) {

				const view = lens({ zoom, intro: null });
				const parent = new THREE.Group();
				const insects = new MapSceneInsects({ THREE, addons: { mergeGeometries: pieces => { fused.push(points(pieces)); melted.add(pieces[0]); return pieces[0]; } }, crops: { blooms }, geometry: { height: () => 0 }, scale: 1.3, canvas: view.canvas, parent, gradientMap: null });

				for(let i = 0; i < 30; i++) {
					insects.frame(i * 100, "2026-06-01", 26, view.camera, view.controls);
				}

				const pieces = new Set(insects.pieces.map(piece => piece.mesh));
				let drawn = 0;
				parent.traverse(object => { drawn += object.isMesh ? 1 : 0; });
				shapes[zoom] = [drawn, parent.children.filter(mesh => pieces.has(mesh) === false).length, insects.pieces.filter(piece => melted.has(piece.mesh.geometry) === false).length];

			}

			// La forme du papillon, mesurée sur ses vraies pièces : une boite englobante par pièce, dans le repère du modèle
			const boxes = MapSceneInsects.butterfly(THREE).map(piece => {
				const box = new THREE.Box3();
				piece.geometries.forEach(geometry => { geometry.computeBoundingBox(); box.union(geometry.boundingBox); });
				const size = box.getSize(new THREE.Vector3());
				return [piece.name, Math.round(size.x * 1000), Math.round(size.y * 1000), Math.round(size.z * 1000)];
			});

			emit({ shapes, boxes, fused: fused.slice(0, 3), pieces: MapSceneInsects.butterfly(THREE).map(piece => points(piece.geometries)) });
		');

		$this->assertEquals(['corps', 'aile-g', 'aile-d'], array_column($result['boxes'], 0), 'un papillon a un corps et deux ailes');

		foreach($result['boxes'] as [$piece, $x, $y, $z]) {
			$this->assertTrue(max($x, $y, $z) >= 3 * min($x, $y, $z), 'ni boule ni disque : la pièce '.$piece.' est au moins trois fois plus longue dans un sens que dans un autre, '.$x.' par '.$y.' par '.$z.' mm');
		}

		$this->assertEquals($result['pieces'], $result['fused'], 'et ce sont ces pièces, sommet pour sommet, que la scène fond');

		foreach($result['shapes'] as $zoom => $shapes) {
			$this->assertEquals([3, 0, 0], $shapes, 'au zoom '.$zoom.', trois maillages en tout sous la scène, enfants compris, tous des pièces du papillon, et chacun porte la géométrie fondue de sa pièce');
		}

	}

	public function testButterfliesAreAThirdFewer(): void {

		$result = $this->run('
			const blooms = new Map(Array.from({ length: 40 }, (_, i) => [i + 1, { date: "2026-06-01", a: { x: -3, n: i - 20 }, b: { x: 3, n: i - 20 }, top: .5, height: .4, greenhouse: false, perches: [] }]));
			const view = lens({ zoom: 10, intro: null });
			const insects = new MapSceneInsects({ THREE, addons: { mergeGeometries: pieces => pieces[0] }, crops: { blooms }, geometry: { height: () => 0 }, scale: 1.3, canvas: view.canvas, parent: new THREE.Group(), gradientMap: null });
			const drawn = {};

			for(const week of [14, 26, 44]) {
				let most = 0;
				for(let i = 0; i < 600; i++) {
					insects.frame(week * 100000 + i * 100, "2026-06-01", week, view.camera, view.controls);
					most = Math.max(most, insects.pieces[0].mesh.count);
				}
				drawn[week] = most;
			}

			// Le tirage de la scène, semé de la même graine sur toute structure
			const random = MapSceneCrops.random(41)();
			emit({ count: insects.count, before: 6 + Math.floor(random * 5), drawn });
		');

		$this->assertEquals([10, 7], [$result['before'], $result['count']], 'une scène portait 10 papillons, elle en porte 7 : un tiers de moins');

		foreach($result['drawn'] as $week => $drawn) {
			$this->assertEquals($result['count'], $drawn, 'en semaine '.$week.', au-dessus de quarante planches en culture, ils volent tous et jamais davantage');
		}

	}

	/**
	 * `MapScene` ne se monte pas sous node : son constructeur demande un rendu WebGL, un canevas mesuré et la page. Ce qui la
	 * relie aux animaux tient en trois appels, lus ici dans sa source, là où ils doivent être.
	 */
	public function testSceneResizesAndPlaysItsAnimals(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/map/static/js/scene.js');

		$between = function(string $from, string $to) use ($source): string {
			$start = strpos($source, $from);
			$end = ($start === FALSE) ? FALSE : strpos($source, $to, $start);
			return ($start === FALSE or $end === FALSE) ? '' : substr($source, $start, $end - $start);
		};

		$resize = $between("\t\tconst resize = () => {\n", "\t\tthis.observer = new ResizeObserver(resize);");
		$loop = $between("\t\tthis.renderer.setAnimationLoop(time => {\n", "\n\t\t});\n");

		$this->assertTrue($resize !== '' and $loop !== '', 'le redimensionnement et la boucle des images de MapScene se retrouvent dans sa source');
		$this->assertEquals([1, 1], [substr_count($resize, "\t\t\tthis.insects.resize();\n"), substr_count($resize, "\t\t\tthis.fauna.resize();\n")], 'quand le canevas change de taille, MapScene fait relire leur taille aux papillons et aux animaux');
		$this->assertEquals([1, 1], [substr_count($loop, "\t\t\tthis.insects.frame(time, this.shown, this.week, this.camera, this.controls);\n"), substr_count($loop, "\t\t\tthis.fauna.frame(time, this.shown, this.week, this.camera, this.controls);\n")], 'à chaque image, MapScene joue les papillons et les animaux à la date et à la semaine affichées');

	}

	public function testSameRunTwiceWithoutMathRandom(): void {

		$result = $this->run('
			// Le hasard de la scène est un tirage semé (MapSceneCrops.random) : la même suite d’images rend les mêmes gestes
			vm.runInContext("Math.random = () => { throw new Error(\"Math.random\"); }", context);

			const trace = () => {

				const scene = stage([tree(0, 0), tree(10, 5), hedge("haie", [{ x: -12, n: -3 }, { x: 12, n: -3 }])]);
				const seen = [];

				play(scene, 0, 300, 20, time => {
					if(Math.round(time * 10) % 100 === 0) {
						seen.push([...scene.fauna.roots.map(root => [root.visible, Math.round(root.position.x * 1000), Math.round(root.position.z * 1000)])]);
					}
				});

				return JSON.stringify(seen);

			};

			const first = trace();

			emit({ same: first === trace(), length: first.length });
		');

		$this->assertTrue($result['same'] ?? FALSE, 'deux scènes sur le même décor et la même horloge rendent les mêmes places, image pour image, sans Math.random');

	}

	public function testBonesCarryTheirPartsAndDisposeLeavesSharedMaterial(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0)]);
			play(scene, 0, 1, 20);

			// Le rang de pièce de chaque sommet d’un os, ramené de son pivot, et la place de l’os
			const merle = scene.fauna.birds[0].perched;
			const ranks = Object.fromEntries(Object.entries(merle.bones).map(([name, bone]) => [name, [...new Set(Array.from(bone.children[0].geometry.attributes.position.array).filter((_, i) => i % 3 === 0).map(x => Math.round(x + bone.position.x)))]]));
			const colors = merle.bones.tete.children[0].geometry.attributes.color.count;

			const before = scene.parent.children.length;
			scene.fauna.dispose();

			emit({ ranks, colors, tete: merle.bones.tete.position.toArray(), before, after: scene.parent.children.length, geometries: scene.disposed.geometries, meshes: scene.disposed.meshes, material: scene.disposed.material });
		');

		$this->assertEquals(['corps' => [4], 'tete' => [0, 5], 'queue' => [1], 'aile-g' => [2], 'aile-d' => [3]], $result['ranks'], 'chaque os porte les pièces de son nom, y compris deux pièces qui ne se suivent pas dans la fusion');
		$this->assertEquals(9, $result['colors'], 'avec leurs couleurs par sommet : six sommets et trois pour la tête');
		$this->assertEquals([0.05, 0.09, 0], $result['tete'], 'l\'os est posé à son pivot');
		$this->assertEquals([5, 0], [$result['before'], $result['after']], 'les cinq montages quittent la scène');
		$this->assertEquals(22, $result['meshes'], 'un maillage par os : cinq par montage d\'oiseau, deux pour le hérisson');
		$this->assertEquals($result['meshes'], $result['geometries'], 'chaque géométrie montée est libérée, et la géométrie fondue dont elles sont tirées ne compte pas ici : elle est libérée dès le montage');
		$this->assertEquals(0, $result['material'], 'la matière est celle de l\'équipe, qui la libère elle-même');

	}

	/**
	 * Un kit d'arbres ajouté au décor se range : un oiseau en fait le tour, le hérisson le longe, ou aucun des deux.
	 */
	public function testEveryTreeKitIsSorted(): void {

		$result = $this->run('
			emit({ kits: Object.keys(MapSceneDecor.KITS).filter(kit => MapSceneDecor.KITS[kit].family === "tree"), trees: MapSceneFauna.TREES, lines: MapSceneFauna.LINES });
		');

		// Ni arbre ni haie : rien ne s'y pose et rien ne les longe
		$low = ['buissons', 'souche', 'roncier', 'fougeres', 'roseaux', 'rocher', 'pierrier'];

		$sorted = array_merge($result['trees'], $result['lines'], $low);
		sort($sorted);

		$kits = $result['kits'];
		sort($kits);

		$this->assertEquals($sorted, $kits, 'un kit de la famille des arbres (MapSceneDecor.KITS) est dans MapSceneFauna.TREES, dans MapSceneFauna.LINES, ou déclaré ici comme ni arbre ni haie');

	}

	/**
	 * La scène du plan joue exactement ce qu'elle jouait avant que le hérisson ne devienne optionnel et que la taille ne suive une
	 * caméra en perspective : une heure de séance, relevée image par image, rend l'empreinte prise sur `sceneFauna.js` à e0dd24d75.
	 * Un écart d'un milliardième sur une place, une taille ou une visibilité la change.
	 */
	public function testPlanSceneUnchangedByDefault(): void {

		$result = $this->run('
			const { createHash } = require("node:crypto");
			const scene = stage([tree(0, 0), tree(10, 5), tree(-8, -6), hedge("haie", [{ x: -12, n: 14 }, { x: 12, n: 14 }])], [], { zoom: 10, intro: null });
			const r = value => (value === null || value === undefined) ? null : Math.round(value * 1e9) / 1e9;
			const hash = createHash("sha256");
			const subjects = [...scene.fauna.birds, scene.fauna.hedgehog];
			let frames = 0;
			let exact = true;
			const record = () => {
				frames++;
				const birds = scene.fauna.birds.map(bird => [
					bird.place === null ? null : [r(bird.place.p.x), r(bird.place.p.n)],
					bird.flight === null ? null : [r(bird.flight.to.p.x), r(bird.flight.to.p.n), r(bird.flight.elapsed)],
					r(bird.scale), bird.faint, bird.perched.root.visible, bird.flying.root.visible
				]);
				const h = scene.fauna.hedgehog;
				hash.update(JSON.stringify([birds, [h.path === null ? null : [r(h.at.x), r(h.at.n)], r(h.scale), h.faint, h.figure.root.visible]]));
				const perMetre = MapSceneInsects.perMetre(scene.canvas.clientHeight, scene.camera);
				exact = exact && subjects.every(subject => subject.scale === MapSceneInsects.sized(subject.size, scene.fauna.natural, MapSceneFauna.PIXELS, perMetre, MapSceneFauna.CAP));
			};
			// Une heure au dixième de seconde, en semaine 26 : paliers de zoom 10, 2, 0,9 puis 10, et la hauteur du canevas réduite de
			// moitié à mi-parcours, suivie de resize()
			const steps = [[0, 900, 10], [900, 1800, 2], [1800, 2700, .9], [2700, 3600, 10]];
			for(const [from, until, zoom] of steps) {
				scene.camera.zoom = zoom;
				scene.camera.updateProjectionMatrix();
				if(from === 1800) {
					scene.canvas.clientHeight = 400;
					scene.fauna.resize();
				}
				for(let step = from * 10; step < until * 10; step++) {
					scene.fauna.frame(step * 100, scene.date, 26, scene.camera, scene.controls);
					record();
				}
			}
			emit({ frames, exact, roots: scene.parent.children.length, hedgehog: scene.fauna.hedgehog.figure.root.parent === scene.parent, print: hash.digest("hex") });
		');

		$this->assertEquals(36000, $result['frames'], 'la séance joue une heure au dixième de seconde');
		$this->assertEquals('f242611bcf1d73f54d0ca241a4d9ba22e1f47c4edf95502cbd91feb364804c8d', $result['print'], 'l\'heure de séance rend l\'empreinte relevée sur sceneFauna.js tel que poussé à e0dd24d75 : places, vols, tailles et visibilités inchangés');
		$this->assertEquals([5, TRUE], [$result['roots'], $result['hedgehog']], 'sans `hedgehog` passé, les quatre modèles des oiseaux et celui du hérisson sont montés');
		$this->assertTrue($result['exact'], 'à chaque image, la taille de chaque animal vaut au bit près celle que MapSceneInsects donne sous la caméra orthographique');

	}

	/**
	 * Une scène qui écarte le hérisson ne le monte pas, et ses oiseaux vivent l'année entière sans lui.
	 */
	public function testWithoutHedgehog(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0), tree(10, 5), hedge("haie", [{ x: -12, n: 4 }, { x: 12, n: 4 }])], [], { zoom: 4, intro: null, hedgehog: false });
			let perched = 0;
			for(let week = 1; week <= 53; week++) {
				play(scene, (week - 1) * 60, week * 60 - .1, week, () => perched += scene.fauna.birds.filter(bird => bird.perched.root.visible).length);
			}
			emit({ hedgehog: scene.fauna.hedgehog, roots: scene.parent.children.length, perched });
		');

		$this->assertEquals([NULL, 4], [$result['hedgehog'], $result['roots']], 'sans hérisson, seuls les quatre modèles des oiseaux sont montés, même au bord d\'une haie');
		$this->assertTrue($result['perched'] > 0, 'les oiseaux se posent des semaines 1 à 53, sans que l\'absence du hérisson fasse lever une image');

	}

	/**
	 * Sous une caméra en perspective, les pixels par mètre au point regardé sont ceux que la caméra projette, décalage de vue compris,
	 * et la taille suit le recul sans relire la hauteur du canevas.
	 */
	public function testPerspectiveCameraSizesByDistance(): void {

		$result = $this->run('
			const scene = stage([tree(0, 0), tree(10, 5)], [], { zoom: 4, intro: null, hedgehog: false });
			const camera = new THREE.PerspectiveCamera(30, 1.5, 1, 3000);
			const measure = () => {
				camera.lookAt(0, 0, 0);
				camera.updateProjectionMatrix();
				camera.updateMatrixWorld();
				// Un mètre debout au point regardé, que la plongée de la caméra ne raccourcit pas
				const low = new THREE.Vector3(0, 0, 0).project(camera);
				const high = new THREE.Vector3(0, 0, 0).add(new THREE.Vector3(0, 1, 0).applyQuaternion(camera.quaternion)).project(camera);
				return { formula: MapSceneFauna.perMetre(800, camera, scene.controls), projected: (high.y - low.y) / 2 * 800 };
			};
			camera.position.set(-200, 300, 450);
			const plain = measure();
			camera.setViewOffset(1200, 900, 0, 0, 1200, 800);
			const offset = measure();
			camera.clearViewOffset();

			let reads = 0;
			const canvas = scene.canvas;
			scene.fauna.canvas = { get clientHeight() { reads++; return canvas.clientHeight; } };
			const sizes = [];
			for(let step = 0; step < 300; step++) {
				const away = 600 - step * 1.8;
				camera.position.set(-.35 * away, .47 * away, .7 * away);
				camera.lookAt(0, 0, 0);
				camera.updateProjectionMatrix();
				scene.fauna.frame(step * 100, scene.date, 26, camera, scene.controls);
				// À 600 m le merle est à son plafond, à 170 m entre plafond et taille naturelle, à 62 m à sa taille naturelle
				if([0, 239, 299].includes(step)) {
					sizes.push(scene.fauna.birds[0].scale);
				}
			}
			const during = reads;
			scene.fauna.resize();
			scene.fauna.frame(30000, scene.date, 26, camera, scene.controls);
			scene.fauna.frame(30100, scene.date, 26, camera, scene.controls);
			emit({ plain, offset, during, after: reads - during, sizes });
		');

		foreach(['plain' => 'sans décalage de vue', 'offset' => 'avec un décalage de vue'] as $key => $label) {
			$this->assertTrue(abs($result[$key]['formula'] / $result[$key]['projected'] - 1) < 0.01, 'un mètre au point regardé tient les pixels que donne perMetre(), à 1 % près, '.$label.' : '.json_encode($result[$key]));
		}

		$this->assertEquals([1, 1], [$result['during'], $result['after']], 'la hauteur se lit à la première image, jamais pendant 300 images de recul, et une fois après resize()');
		$this->assertTrue($result['sizes'][0] > $result['sizes'][1] and $result['sizes'][1] > $result['sizes'][2], 'la caméra qui s\'approche, l\'oiseau se rapproche de sa taille naturelle : '.json_encode($result['sizes']));

	}

	/**
	 * Joue un scénario sous node. Il dispose de :
	 * - `stage(elements, beds, view)`, qui rend une scène : ses animaux (`fauna`), son parent, son canevas, et `hidden` pour masquer tout le décor ;
	 * - `lens(view)`, la caméra d'ouverture de la scène, son canevas de 800 px et son survol ;
	 * - `play(scene, from, until, week, each)`, qui passe les images de `from` à `until` secondes par pas d'un dixième ;
	 * - `shown(scene)`, qui dit si le merle, le rouge-gorge et le hérisson se montrent ;
	 * - `tree()`, `wild()`, `thicket()` et `hedge()`, qui écrivent un élément du décor tel que `MapSceneDecor` le monte.
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

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js", "production/map/static/js/sceneFauna.js", "production/map/static/js/sceneHedgehog.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console });
				const { MapScene, MapSceneCrops, MapSceneFauna, MapSceneInsects, MapSceneDecor } = vm.runInContext(source + "\n;({ MapScene, MapSceneCrops, MapSceneFauna, MapSceneInsects, MapSceneDecor })", context);

				// Les cinq modèles tels que la cuisson les indexe : l’emprise, un pivot par os, et les pièces dans l’ordre de la fusion,
				// un os pouvant en porter deux qui ne se suivent pas
				const bird = d => ({ d, pivots: { corps: [0, .07, 0], tete: [.05, .09, 0], queue: [-.06, .06, 0], "aile-g": [.03, .09, -.02], "aile-d": [.03, .09, .02] }, parts: [{ name: "tete", count: 6 }, { name: "queue", count: 3 }, { name: "aile-g", count: 3 }, { name: "aile-d", count: 3 }, { name: "corps", count: 9 }, { name: "tete", count: 3 }] });
				const models = { merle: bird(.268), "merle.vol": bird(.333), "rouge-gorge": bird(.138), "rouge-gorge.vol": bird(.25), herisson: { d: .277, pivots: { corps: [0, .06, 0], tete: [.045, .04, 0] }, parts: [{ name: "corps", count: 12 }, { name: "tete", count: 6 }] } };

				// La caméra de la scène : orthographique, de trois quarts, sur un canevas de 800 px de haut et de 1 200 de large
				const lens = ({ zoom = 4, intro = null, height = 800 } = {}) => {

					const camera = new THREE.OrthographicCamera(-MapScene.FRUSTUM * 1.5, MapScene.FRUSTUM * 1.5, MapScene.FRUSTUM, -MapScene.FRUSTUM, .1, 4000);
					camera.position.set(300, 300, 300);
					camera.lookAt(0, 0, 0);
					camera.zoom = zoom;
					camera.updateProjectionMatrix();

					return { camera, controls: { target: new THREE.Vector3(0, 0, 0) }, canvas: { clientHeight: height }, ambience: { intro } };

				};

				const tree = (x, n) => ({ kit: "arbre", def: MapSceneDecor.KITS.arbre, root: {}, p: { x, n }, angle: 0, size: 1, feet: [{ x: 0, z: 0, half: .3, trunk: .3 }] });
				const wild = (kit, x, n) => ({ kit, def: MapSceneDecor.KITS[kit], root: {}, p: { x, n }, angle: 0, size: 1, feet: [{ x: 0, z: 0, half: .5, trunk: .5 }] });
				const thicket = (x, n) => ({ kit: "buissons", def: MapSceneDecor.KITS.buissons, root: {}, p: { x, n }, angle: 0, size: 1, feet: [{ x: 0, z: 0, half: .4, trunk: null }] });
				const hedge = (kit, line) => ({ kit, def: MapSceneDecor.KITS[kit], root: {}, p: { x: 0, n: 0 }, path: line, thick: MapSceneDecor.KITS[kit].thick });

				const stage = (elements, beds = [], view = {}) => {

					const scene = { ...lens(view), parent: new THREE.Group(), hidden: false, blooms: new Map(), covers: [], date: "2026-06-01", disposed: { geometries: 0, meshes: 0, material: 0 } };
					const material = { dispose: () => scene.disposed.material++ };

					// La géométrie fondue d’un modèle, comme figure.prop() la rend : chaque sommet porte en x le rang de sa pièce
					const prop = (baked, slug) => {

						const model = baked.index.models[slug];
						const positions = model.parts.flatMap((part, rank) => Array.from({ length: part.count }, () => [rank, 0, 0]).flat());
						const geometry = new THREE.BufferGeometry();
						geometry.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
						geometry.setAttribute("color", new THREE.Float32BufferAttribute(positions, 3));

						return { geometry };

					};

					// Chaque géométrie montée compte, et dit quand elle est libérée
					const Geometry = THREE.BufferGeometry;
					const three = { ...THREE, BufferGeometry: class extends Geometry {
						constructor() {
							super();
							scene.disposed.meshes++;
						}
						dispose() {
							scene.disposed.geometries++;
						}
					} };

					scene.fauna = new MapSceneFauna({
						THREE: three,
						socle: { baked: { index: { models } }, figure: { prop } },
						crew: { scale: 1.3, material },
						crops: { blooms: scene.blooms },
						covers: () => scene.covers,
						// Les solides du décor : l’emprise de chaque haie, comme MapSceneDecor.solids()
						decor: { elements, shows: () => scene.hidden === false, solids: () => elements.filter(e => e.path !== undefined).flatMap(e => MapSceneDecor.segments(e.path, e.thick / 2)) },
						geometry: { beds, plots: new Map(), height: () => 0 },
						ambience: scene.ambience,
						canvas: scene.canvas,
						parent: scene.parent,
						// Sans `hedgehog` dans la vue, le constructeur reçoit les mêmes clés que celui de la scène du plan
						...((view.hedgehog === undefined) ? {} : { hedgehog: view.hedgehog })
					});

					return scene;

				};

				const play = (scene, from, until, week, each = () => {}) => {
					for(let step = Math.round(from * 10); step <= Math.round(until * 10); step++) {
						scene.fauna.frame(step * 100, scene.date, week, scene.camera, scene.controls);
						each(step / 10);
					}
				};

				const shown = scene => [...scene.fauna.birds.map(bird => bird.perched.root.visible || bird.flying.root.visible), scene.fauna.hedgehog.figure.root.visible];

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
