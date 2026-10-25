<?php
namespace map;

/**
 * Les bulles de récolte de la lecture de l'année (`static/js/scene.js`, `MapSceneBubbles`), éprouvées sous node telles que le
 * navigateur charge les fichiers : deux bulles d'une même semaine ne se couvrent plus, la plus grosse récolte garde sa place, une
 * bulle reste cachée tant qu'elle n'a pas la sienne, et elle tient tant que la scène montre sa semaine, sans jamais être reposée.
 *
 * Aucune écriture, aucune base ouverte. Les rectangles sont écrits dans le scénario, en pixels, aux tailles relevées à 360 px sur
 * la structure 18, saison 2026 (26 px de haut, 65 à 95 px de large) ; le DOM est esquissé par ce que `show()` et `frame()` en
 * lisent.
 *
 * Lancement : dev/test package=map file=BubblesAi
 */
class BubblesAiTest extends \Test {

	public function testNeighboursNoLongerOverlap(): void {

		$result = $this->run('
			// Cinq bulles d\'une semaine à 360 px, de la plus grosse récolte à la plus petite : trois voisines qui se couvrent, une
			// isolée, et une dernière qui ne la couvre pas mais la touche presque, à 4 px sous elle
			const boxes = [box(140, 300, 88), box(180, 310, 95), box(150, 290, 70), box(20, 100, 65), box(30, 130, 80)];
			const lifts = climbs(boxes, MapSceneBubbles.GAP);
			const moved = boxes.map((one, index) => ({ ...one, top: one.top + lifts[index], bottom: one.bottom + lifts[index] }));

			emit({ lifts, gap: MapSceneBubbles.GAP, before: close(boxes, MapSceneBubbles.GAP), after: close(moved, MapSceneBubbles.GAP) });
		');

		if($this->assertDecorPresent($result['before'] > 0, 'des bulles voisines qui se couvrent avant la pose') === FALSE) {
			return;
		}

		$this->assertEquals(0, $result['after'], 'aucune paire de bulles à moins de '.$result['gap'].' px l\'une de l\'autre après la pose, il en reste '.$result['after']);
		$this->assertEquals(0, $result['lifts'][0], 'la plus grosse récolte garde sa place');
		$this->assertEquals(0, $result['lifts'][3], 'une bulle isolée garde sa place');

	}

	public function testEachBubbleClimbsUntilItFindsItsPlace(): void {

		$result = $this->run('
			// La troisième couvre la première, puis, posée au-dessus, la deuxième : elle doit monter deux fois
			emit(climbs([box(100, 200, 88), box(120, 205, 70), box(130, 190, 70)], 8));
		');

		$this->assertEquals([0, -39, -58], $result, 'la deuxième passe au-dessus de la première, la troisième au-dessus des deux');

	}

	public function testHiddenBubbleDoesNotMove(): void {

		$result = $this->run('
			// En mode photo, les étiquettes sont en `display: none` : le rectangle d\'une bulle est vide, posé à l\'origine de la page
			const empty = { left: 0, right: 0, top: 0, bottom: 0 };

			emit({ after: climbs([box(0, 0, 88), empty], 8), before: climbs([empty, box(2, 2, 88)], 8) });
		');

		$this->assertEquals([0, 0], $result['after'], 'une bulle sans largeur ne monte pas au-dessus de ses voisines');
		$this->assertEquals([0, 0], $result['before'], 'une bulle sans largeur ne repousse pas une voisine posée près de l\'origine');

	}

	public function testFractionalRectanglesFindTheirPlace(): void {

		$result = $this->run('
			// Des rectangles en doubles quelconques : un bas recalculé par différence restait sous la voisine et la boucle ne finissait plus
			const boxes = [{ left: 100, right: 180, top: 19.694145085624836, bottom: 45.69414508562484 }, { left: 110, right: 190, top: 20.19153297406193, bottom: 46.19153297406193 }];
			const lifts = climbs(boxes, 8);

			emit({ lifts, clear: Math.abs(boxes[1].bottom + lifts[1] - (boxes[0].top - 8)) < 1e-9 });
		');

		if($this->assertDecorPresent($result !== [], 'les montées rendues par node, que tue une boucle sans fin') === FALSE) {
			return;
		}

		$this->assertEquals(0, $result['lifts'][0], 'la première bulle garde sa place');
		$this->assertTrue($result['clear'], 'la seconde passe au-dessus de la première, à l\'écart près : '.json_encode($result['lifts']));

	}

	public function testBubbleIsBornHiddenAndBiggestFirst(): void {

		$result = $this->run('
			const { bubbles } = sketch();

			bubbles.show(37, [
				{ at: { x: 0, n: 0 }, harvests: [{ quantity: 10, unit: "kg" }] },
				{ at: { x: 4, n: 0 }, harvests: [{ quantity: 90, unit: "kg" }] }
			], {});

			emit(bubbles.shown.map(({ bubble, placed }) => ({ visibility: bubble.style.visibility, placed, text: bubble.text })));
		');

		$this->assertEquals([
			['visibility' => 'hidden', 'placed' => FALSE, 'text' => ' +90 kg'],
			['visibility' => 'hidden', 'placed' => FALSE, 'text' => ' +10 kg'],
		], $result, 'chaque bulle naît cachée et sans place, la plus grosse récolte en tête');

	}

	public function testFrameMeasuresAllThenWritesAllOnce(): void {

		$result = $this->run('
			const log = [];
			const bubble = (name, rectangle, isConnected = true) => ({
				isConnected,
				rising: -60,
				getBoundingClientRect() { log.push("lit " + name); return rectangle; },
				style: new Proxy({ visibility: "hidden" }, { set(target, key, value) { log.push("écrit " + name); target[key] = value; return true; } })
			});

			const bubbles = new MapSceneBubbles({}, { remove() {} }, { getBoundingClientRect() { log.push("lit canevas"); return { left: 0, top: 0, width: 1000, height: 1000 }; } }, () => [], { height: () => 0 });
			const entries = [bubble("a", box(140, 300, 88)), bubble("b", box(180, 310, 95)), bubble("c", box(20, 100, 65), false)];

			bubbles.shown = entries.map(one => ({ label: {}, bubble: one, shift: 0, lift: 0, placed: false }));

			bubbles.frame(false);
			const first = log.splice(0);

			bubbles.frame(false);
			const second = log.splice(0);

			emit({
				first,
				second,
				styles: entries.map(one => ({ translate: one.style.translate ?? null, visibility: one.style.visibility })),
				placed: bubbles.shown.map(one => one.placed)
			});
		');

		$reads = array_values(array_filter($result['first'], fn($entry) => str_starts_with($entry, 'lit ')));
		$firstWrite = array_search(TRUE, array_map(fn($entry) => str_starts_with($entry, 'écrit '), $result['first']), TRUE);

		$this->assertEquals(['lit canevas', 'lit a', 'lit b'], $reads, 'le canevas et les bulles posées par CSS2DRenderer se mesurent, celle qui ne l\'est pas encore attend');
		$this->assertEquals(3, $firstWrite, 'toutes les mesures avant la première écriture : '.implode(', ', $result['first']));
		$this->assertEquals([], $result['second'], 'sans bulle à placer, ni le canevas ni une bulle ne se mesurent, rien ne s\'écrit');
		$this->assertEquals([
			['translate' => '0px 0px', 'visibility' => ''],
			['translate' => '0px -44px', 'visibility' => ''],
			['translate' => NULL, 'visibility' => 'hidden'],
		], $result['styles'], 'les bulles placées montent de ce que rend stack() et se montrent, celle qui attend reste cachée');
		$this->assertEquals([TRUE, TRUE, FALSE], $result['placed'], 'seules les bulles mesurées sont placées');

	}

	public function testWeekOutlivesTime(): void {

		$result = $this->run('
			// Une semaine de lecture à la vitesse 1 dure 1 050 ms : les images qui passent ne retirent aucune bulle, la semaine seule le fait
			const { parent, bubbles } = sketch();

			bubbles.show(37, series(2), {});
			Array.from({ length: 600 }).forEach(() => bubbles.frame(false));

			emit({ shown: bubbles.shown.length, removed: parent.removed });
		');

		$this->assertEquals(['shown' => 2, 'removed' => 0], $result, 'les bulles de la semaine restent quel que soit le nombre d\'images');

	}

	public function testPauseKeepsTheWeek(): void {

		$result = $this->run('
			// La pause et le clic d\'un jour redessinent la même semaine ; une semaine sans récolte choisie ensuite retire les bulles
			const { parent, bubbles } = sketch();

			bubbles.show(37, series(2), {});
			bubbles.frame(false);
			bubbles.hold(37);
			bubbles.hold(37);
			const paused = { shown: bubbles.shown.length, removed: parent.removed };

			bubbles.hold(38);
			const left = { shown: bubbles.shown.length, removed: parent.removed };

			bubbles.show(37, series(2), {});

			emit({ paused, left, back: bubbles.shown.length });
		');

		$this->assertEquals(['shown' => 2, 'removed' => 0], $result['paused'], 'la semaine redessinée garde ses bulles');
		$this->assertEquals(['shown' => 0, 'removed' => 2], $result['left'], 'une autre semaine retire celles de la précédente');
		$this->assertEquals(2, $result['back'], 'revenue sur une semaine quittée, la lecture en montre de nouveau les bulles');

	}

	public function testShownWeekIsNotShownAgain(): void {

		$result = $this->run('
			// Un geste qui redessine la scène un lundi de lecture (zone, espèce) rappelle `show()` pour la même semaine
			const { parent, bubbles } = sketch();

			bubbles.show(37, series(6), {});
			bubbles.frame(false);
			bubbles.show(37, series(6), {});

			emit({ max: MapSceneBubbles.MAX, shown: bubbles.shown.length, added: parent.added, placed: bubbles.shown.every(one => one.placed) });
		');

		$this->assertEquals($result['max'], $result['shown'], 'deux show() de la même semaine laissent '.$result['max'].' bulles au plus, il y en a '.$result['shown']);
		$this->assertEquals($result['max'], $result['added'], 'la seconde fois, aucune étiquette n\'est posée dans la scène');
		$this->assertTrue($result['placed'], 'toutes les bulles à l\'écran ont été placées par stack()');

	}

	public function testCameraGestureReplacesBubbles(): void {

		$result = $this->run('
			const { bubbles } = sketch();
			const effective = () => bubbles.shown.map(({ bubble }) => bubble.getBoundingClientRect());

			bubbles.show(37, series(2), {});
			const [first, second] = bubbles.shown.map(({ bubble }) => bubble);

			// Première vue : la seconde bulle couvre la première et monte au-dessus d\'elle
			first.rect = { left: 0, right: 80, top: 100, bottom: 126 };
			second.rect = { left: 10, right: 90, top: 110, bottom: 136 };
			bubbles.frame(false);
			const before = { lift: bubbles.shown[1].lift, close: close(effective(), 8) };

			// La caméra a tourné : les ancres s\'écartent, la seconde n\'a plus à monter. Tant que la caméra bouge, rien ne se remesure
			second.rect = { left: 10, right: 90, top: 150, bottom: 176 };
			bubbles.loosen();
			bubbles.frame(true);
			const moving = bubbles.shown[1].lift;

			bubbles.frame(false);
			const after = { lift: bubbles.shown[1].lift, close: close(effective(), 8) };

			// Une image où la caméra s\'arrête sans geste ne replace rien
			second.rect = { left: 10, right: 90, top: 110, bottom: 136 };
			bubbles.frame(false);

			emit({ before, moving, after, idle: bubbles.shown[1].lift });
		');

		$this->assertEquals(['lift' => -44, 'close' => 0], $result['before'], 'sur la première vue, la seconde bulle passe au-dessus de la première');
		$this->assertEquals(-44, $result['moving'], 'tant que la caméra bouge, les bulles gardent leur place');
		$this->assertEquals(['lift' => 0, 'close' => 0], $result['after'], 'la caméra arrêtée, la seconde bulle redescend sur son ancre, sans s\'approcher de la première à moins de 8 px');
		$this->assertEquals(0, $result['idle'], 'sans geste de caméra, aucune bulle ne se replace');

	}

	public function testBubbleStaysInsideCanvas(): void {

		$result = $this->run('
			// Un canevas de 360 px : une bulle coupée par le bord droit, une autre par le bord gauche
			emit(MapSceneBubbles.stack([box(320, 100, 80), box(-30, 300, 80)], 8, { left: 0, top: 0, right: 360, bottom: 600 }, []));
		');

		$this->assertEquals([['x' => -48, 'y' => 0], ['x' => 38, 'y' => 0]], $result, 'chaque bulle revient à 8 px du bord qu\'elle dépassait, sans monter');

	}

	public function testBubbleAnchoredOffCanvasIsHidden(): void {

		$result = $this->run('
			// Sur un canevas de 360 × 600 px, quatre bulles dont l\'ancre, milieu de leur bas, sort à droite, à gauche, en haut et en bas
			emit(MapSceneBubbles.stack([box(330, 100, 80), box(-70, 200, 80), box(100, -40, 80), box(200, 590, 80)], 8, { left: 0, top: 0, right: 360, bottom: 600 }, []));
		');

		$this->assertEquals([NULL, NULL, NULL, NULL], $result, 'une bulle dont l\'ancre est hors du canevas reste cachée au lieu d\'être ramenée sur une autre série');

	}

	public function testBubbleRisesOverTimeline(): void {

		$result = $this->run('
			// La frise couvre le bas du canevas de 380 à 490 px, et le bas de la bulle descend à 386 px
			const timeline = [16, 380, 1183, 490];
			const [position] = MapSceneBubbles.stack([box(160, 360, 80)], 8, { left: 0, top: 0, right: 1199, bottom: 500 }, [timeline]);

			emit({ position, close: close([{ left: 160, right: 240, top: 360 + position.y, bottom: 386 + position.y }, { left: 16, top: 380, right: 1183, bottom: 490 }], 8) });
		');

		$this->assertEquals(['x' => 0, 'y' => -14], $result['position'], 'la bulle passe au-dessus de la frise');
		$this->assertEquals(0, $result['close'], 'la bulle reste à 8 px de la frise');

	}

	public function testBubbleWithoutRoomAboveGoesBelow(): void {

		$result = $this->run('
			// La première tient en haut du canevas ; la seconde la couvre, et passer au-dessus la ferait sortir par le haut
			emit(MapSceneBubbles.stack([box(100, 10, 80), box(110, 12, 80)], 8, { left: 0, top: 0, right: 360, bottom: 600 }, []).map(one => one.y));
		');

		$this->assertEquals([0, 32], $result, 'la seconde descend sous la première, à 8 px d\'elle');

	}

	public function testBubbleWithoutRoomIsHidden(): void {

		$result = $this->run('
			// Un canevas de 60 px de haut : la seconde bulle ne tient ni au-dessus ni au-dessous de la première. La troisième couvrirait
			// la seconde à son ancre, mais pas la première
			const positions = MapSceneBubbles.stack([box(20, 17, 80), box(30, 20, 80), box(115, 20, 80)], 8, { left: 0, top: 0, right: 360, bottom: 60 }, []);

			// Dans la scène : la bulle sans place reste cachée et compte pour placée, jusqu\'au prochain geste de caméra
			const { bubbles } = sketch(360, 60);
			bubbles.show(37, series(2), {});
			const [big, small] = bubbles.shown.map(({ bubble }) => bubble);
			big.rect = box(20, 17, 80);
			small.rect = box(30, 20, 80);
			const measured = () => { let count = 0; [big, small].forEach(one => { const read = one.getBoundingClientRect; one.getBoundingClientRect = function() { count++; return read.call(this); }; }); return () => count; };

			const reads = measured();
			bubbles.frame(false);
			const first = reads();
			bubbles.frame(false);
			const idle = reads() - first;
			bubbles.loosen();
			bubbles.frame(false);

			emit({ positions, visibility: small.style.visibility, placed: bubbles.shown[1].placed, first, idle, loosened: reads() - first - idle });
		');

		$this->assertEquals([['x' => 0, 'y' => 0], NULL, ['x' => 0, 'y' => 0]], $result['positions'], 'la bulle sans place rend null et ne repousse pas la troisième');
		$this->assertEquals('hidden', $result['visibility'], 'la bulle sans place reste cachée, sa récolte se lit au compteur');
		$this->assertTrue($result['placed'], 'la bulle sans place compte pour placée');
		$this->assertEquals(['first' => 2, 'idle' => 0, 'loosened' => 2], ['first' => $result['first'], 'idle' => $result['idle'], 'loosened' => $result['loosened']], 'elle ne se remesure pas à l\'image suivante, seulement après loosen()');

	}

	public function testDescentEndsOnFractionalRectangles(): void {

		$result = $this->run('
			// La barre descend à un bas fractionnaire, et la bulle n\'a pas de place au-dessus : un haut recalculé par différence restait
			// sous la barre et la boucle ne finissait plus. Chaque recherche de voisine est un tour, compté et borné : les voisines sont
			// rangées dans un tableau tiré des panneaux, donc du contexte du scénario
			let turns = 0;
			const find = Array.prototype.find;
			Array.prototype.find = function(...args) {
				if(++turns > 50) {
					throw new Error("boucle");
				}
				return find.apply(this, args);
			};

			try {
				const [position] = MapSceneBubbles.stack([box(100, 20, 80)], 8, { left: 0, top: 0, right: 360, bottom: 600 }, [[0, -100, 400, 23.348668770134]]);
				emit({ ended: true, turns, clear: position.y === 23.348668770134 + 8 - 20 });
			} catch(error) {
				emit({ ended: false, turns, clear: false });
			}
		');

		$this->assertTrue($result['ended'], 'la descente finit en moins de 50 tours, elle en a fait '.$result['turns']);
		$this->assertTrue($result['clear'], 'la bulle se pose exactement à 8 px sous la barre');

	}

	public function testReplacedBubbleMeasuredWithoutItsShift(): void {

		$result = $this->run('
			// Une bulle coupée par le bord droit d\'un canevas de 360 px, ramenée, puis replacée après un geste de caméra sans avoir bougé
			const { bubbles } = sketch(360, 600);
			bubbles.show(37, series(1), {});
			bubbles.shown[0].bubble.rect = box(320, 100, 80);

			bubbles.frame(false);
			const before = bubbles.shown[0].shift;
			bubbles.loosen();
			bubbles.frame(false);

			emit({ before, after: bubbles.shown[0].shift, translate: bubbles.shown[0].bubble.style.translate });
		');

		$this->assertEquals(['before' => -48, 'after' => -48, 'translate' => '-48px 0px'], $result, 'une bulle replacée se mesure sans le décalage qu\'elle porte, et le garde');

	}

	public function testBubbleMeasuredMidRiseIsPlacedWhereItRises(): void {

		$result = $this->run('
			// La barre couvre le haut d\'un canevas de 360 px jusqu\'à 44 px. Une fois montée, la bulle s\'arrêterait à 30 px, sous la
			// barre ; mesurée au début de sa montée, elle est encore 60 px plus bas, à l\'écart de la barre
			const { bubbles } = sketch(360, 600, [[9, 0, 351, 44]]);
			bubbles.show(37, series(1), {});
			const [bubble] = bubbles.shown.map(one => one.bubble);
			bubble.rect = box(100, 30, 80);
			bubble.rising = 0;

			bubbles.frame(false);

			emit({ translate: bubble.style.translate, visibility: bubble.style.visibility });
		');

		$this->assertEquals(['translate' => '0px 22px', 'visibility' => ''], $result, 'la bulle se place là où sa montée l\'amène, à 8 px sous la barre');

	}

	public function testOtherWeekClears(): void {

		$result = $this->run('
			const { parent, bubbles } = sketch();

			bubbles.show(37, series(3), {});
			const before = bubbles.shown.map(one => one.label.element);

			bubbles.show(38, series(2), {});

			emit({ shown: bubbles.shown.length, removed: parent.removed, connected: before.filter(element => element.isConnected).length });
		');

		$this->assertEquals(['shown' => 2, 'removed' => 3, 'connected' => 0], $result, 'les bulles de la semaine 38 remplacent celles de la 37, retirées de la scène et de la page');

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et rend ce qu'il émet.
	 * `box(left, top, width)` rend le rectangle d'une bulle de 26 px de haut, `close(boxes, gap)` le nombre de paires à moins de
	 * `gap` pixels l'une de l'autre.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			const { readFileSync } = require("node:fs");
			const vm = require("node:vm");

			// Ce que `show()`, `frame()` et `hold()` lisent du DOM : un élément qui garde son style et son texte, se mesure et se retire
			const element = () => ({
				style: {},
				text: "",
				innerHTML: "",
				isConnected: true,
				// `rect` est le rectangle de la bulle montée, `rising` l\'instant de sa montée animée, `-60` une fois finie
				rect: { left: 0, right: 80, top: 0, bottom: 26 },
				rising: -60,
				append(child) { if(typeof child === "string") { this.text += child; } },
				// Comme le navigateur, le rectangle compte le décalage posé par `translate` et l\'instant de la montée
				getBoundingClientRect() {
					const [shift, lift] = (this.style.translate === undefined) ? [0, 0] : this.style.translate.split(" ").map(parseFloat);
					const late = this.rising + 60;
					return { left: this.rect.left + shift, right: this.rect.right + shift, top: this.rect.top + lift + late, bottom: this.rect.bottom + lift + late };
				},
				remove() { this.isConnected = false; }
			});

			const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
			// Le style calculé d\'une bulle porte sa montée animée à l\'instant, en `transform`, et la montée entière (`scene.css`)
			const getComputedStyle = one => ({ transform: one.rising, getPropertyValue: () => "-60px" });
			const DOMMatrixReadOnly = class { constructor(rising) { this.m42 = rising; } };
			const context = vm.createContext({ window: {}, document: { addEventListener: () => {}, createElement: element }, getComputedStyle, DOMMatrixReadOnly, console, performance, Lime: { Asset: { icon: () => "" } } });
			const { SeriesSheet, MapSceneBubbles } = vm.runInContext(source + "\n;({ SeriesSheet, MapSceneBubbles })", context);

			const box = (left, top, width) => ({ left, right: left + width, top, bottom: top + 26 });
			// Un canevas assez grand pour qu\'aucun bord ni panneau ne compte : seul l\'écart entre bulles décide de leur montée
			const climbs = (boxes, gap) => MapSceneBubbles.stack(boxes, gap, { left: -1e4, top: -1e4, right: 1e4, bottom: 1e4 }, []).map(one => one.y);
			const canvas = (width, height) => ({ getBoundingClientRect: () => ({ left: 0, top: 0, width, height }) });
			// Une scène qui compte les étiquettes posées et retirées, sur un canevas de `width` × `height` que `covers` recouvrent, et
			// `count` séries récoltées dans la semaine, la dernière en tête
			const sketch = (width = 1000, height = 1000, covers = []) => {
				SeriesSheet.quantities = (texts, harvests) => harvests[0].quantity + " " + harvests[0].unit;
				const parent = { added: 0, removed: 0, add() { this.added++; }, remove() { this.removed++; } };
				const addons = { CSS2DObject: class { constructor(element) { this.element = element; this.position = { set() {} }; } } };
				return { parent, bubbles: new MapSceneBubbles(addons, parent, canvas(width, height), () => covers, { height: () => 0 }) };
			};
			const series = count => Array.from({ length: count }, (_, index) => ({ at: { x: index, n: 0 }, harvests: [{ quantity: 10 * (index + 1), unit: "kg" }] }));

			const close = (boxes, gap) => boxes.reduce((count, one, index) => count + boxes.slice(index + 1).filter(other => one.left < other.right + gap && one.right > other.left - gap && one.top < other.bottom + gap && one.bottom > other.top - gap).length, 0);

			'.$scenario.'
		';

		// Une boucle de placement qui ne finit pas bloquerait toute la passe : node est arrêté au bout de dix secondes, et le cas rougit
		$process = proc_open(['timeout', '10', 'node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

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
