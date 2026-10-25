<?php
namespace map;

/**
 * Le pont de pierre qu'un chemin ou une route pose sur un cours d'eau qu'il croise (`static/js/sceneDecor.js`, `MapSceneDecor.bridge()`
 * et `crossings()`) : personne ne le pose, la scène le déduit des tracés montrés, et le refait quand l'un d'eux bouge, disparait ou se
 * cache. Le modèle `pont` s'étire par tranches (`shapeBridge()`) : l'arche s'ouvre à l'eau, les parapets gardent leur mesure, les
 * rampes aussi sur un chemin, et s'allongent sur une route, dont le tablier prend la chaussée. Avec eux se refait le marquage du
 * milieu des routes (`stripe()`), qui s'interrompt aux carrefours (`junctions()`) et monte sur les ponts.
 *
 * Éprouvé sous node, avec le vrai modèle cuit (`base/scene/static/model/models.json` et `models.bin`) lu par `sceneBuildModel()` et
 * monté par `sceneCreateFigure()`, comme le navigateur le monte. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=DecorBridgeAi
 */
class DecorBridgeAiTest extends \Test {

	/**
	 * Un chemin d'un mètre en travers d'un cours d'eau de deux : un pont, au croisement, ouvert à l'eau et à sa berge, large du chemin
	 * et de ses parapets. Ses sommets sont ceux du modèle et les six de la nappe d'eau sous l'arche.
	 */
	public function testAStraightCrossingMakesOneBridge(): void {

		$result = $this->run('
			const decor = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), trace(2, "cours-eau", [[0, -20], [0, 20]], 2)]);
			await settled();
			const [bridge] = crossingsOf(decor);
			emit({ count: crossingsOf(decor).length, x: bridge.point.x, n: bridge.point.n, sine: bridge.sine, track: bridge.track, water: bridge.water, vertices: decor.bridges.geometry.attributes.position.count, model: modelVertices, ...extent(decor) });
		');

		$this->assertEquals(1, $result['count'] ?? NULL, "un chemin qui croise un cours d'eau y pose un pont");
		$this->assertEquals(0.0, round($result['x'] ?? 99, 3), "le pont est au croisement, en x");
		$this->assertEquals(0.0, round($result['n'] ?? 99, 3), "le pont est au croisement, en n");
		$this->assertEquals(1.0, round($result['sine'] ?? 0, 3), "un croisement droit a un sinus de un");
		$this->assertEquals(1.3, round($result['track'] ?? 0, 3), "le chemin d'un mètre compte sa marge de 0,3 m");
		$this->assertEquals(2.0, round($result['water'] ?? 0, 3), "l'eau a la largeur du cours");
		$this->assertEquals(($result['model'] ?? -1) + 6, $result['vertices'] ?? NULL, "le pont est le modèle et une nappe de deux triangles");
		$this->assertEquals(2.4 + 2.4, round($result['length'] ?? 0, 2), "l'arche s'ouvre à 2,4 m (2 m d'eau et 0,4 de berge), les rampes gardent 2,4 m");
		$this->assertEquals(1.9, round($result['width'] ?? 0, 2), "le tablier fait la largeur du chemin, sa marge et 0,6 m de parapets");

	}

	/**
	 * L'arche s'ouvre d'autant que le chemin croise l'eau en biais ; sous trente degrés, elle s'allongerait sans fin, et le gué reste.
	 */
	public function testAnObliqueCrossingStretchesAndAGrazingOneMakesNone(): void {

		$result = $this->run('
			const oblique = draw([trace(1, "chemin", [[-20, -20], [20, 20]], 1), trace(2, "cours-eau", [[-20, 0], [20, 0]], 2)]);
			const grazing = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), trace(2, "cours-eau", [[-20, -7.28], [20, 7.28]], 2)]);
			await settled();
			emit({ oblique: crossingsOf(oblique).map(bridge => bridge.sine), grazing: crossingsOf(grazing).length, grazingMesh: grazing.bridges === null });
		');

		$this->assertEquals([0.707], array_map(fn($sine) => round($sine, 3), $result['oblique'] ?? []), "un croisement à 45° fait un pont, au sinus de 0,707");
		$this->assertEquals(0, $result['grazing'] ?? NULL, "un croisement à 20° ne fait pas de pont");
		$this->assertTrue($result['grazingMesh'] ?? FALSE, "la scène n'a aucun pont à montrer");

	}

	/**
	 * Sur un croisement en biais, la nappe sous l'arche reste dans l'eau : ses six sommets, les derniers de la géométrie, sont tous à
	 * la demi-largeur du cours, que le tracé aille dans un sens ou dans l'autre. Un pont tourné du mauvais côté, ou une nappe biaisée
	 * à l'envers, l'en écarterait.
	 */
	public function testTheWaterSheetFollowsAnObliqueStream(): void {

		$result = $this->run('
			const sheetDistances = decor => {
				const positions = decor.bridges.geometry.attributes.position;
				return Array.from({ length: 6 }, (_, i) => Math.abs(positions.getZ(positions.count - 6 + i)));
			};
			const road = draw([trace(1, "route", [[-20, -20], [20, 20]], null), trace(2, "cours-eau", [[-20, 0], [20, 0]], 2)]);
			const reversed = draw([trace(1, "chemin", [[20, 20], [-20, -20]], 1), trace(2, "cours-eau", [[-20, 0], [20, 0]], 1)]);
			await settled();
			emit({ road: sheetDistances(road), reversed: sheetDistances(reversed) });
		');

		$this->assertEquals(array_fill(0, 6, 1.0), array_map(fn($distance) => round($distance, 3), $result['road'] ?? []), "une route à 45° sur 2 m d'eau garde sa nappe à 1 m de l'axe du cours");
		$this->assertEquals(array_fill(0, 6, 0.5), array_map(fn($distance) => round($distance, 3), $result['reversed'] ?? []), "un chemin tracé à l'envers sur 1 m d'eau garde sa nappe à 0,5 m de l'axe du cours");

	}

	/**
	 * Un chemin qui recroise l'eau à moins d'une longueur de pont du premier croisement passe sur le premier pont. Un pont qui
	 * dépasserait le bout du chemin ne se pose pas. Un chemin et une route sans eau gardent leur carrefour.
	 */
	public function testMeandersEndsAndDryCrossingsMakeNoSecondBridge(): void {

		$result = $this->run('
			const meander = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), trace(2, "cours-eau", [[-1, -20], [-1, 1.5], [1, 1.5], [1, -20]], 1)]);
			const end = draw([trace(1, "chemin", [[-1, 0], [20, 0]], 1), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			const dry = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), trace(2, "route", [[0, -20], [0, 20]], null)]);
			await settled();
			emit({ meander: crossingsOf(meander).length, end: crossingsOf(end).length, dry: dry.bridges === null });
		');

		$this->assertEquals(1, $result['meander'] ?? NULL, "deux croisements à deux mètres d'écart font un seul pont");
		$this->assertEquals(0, $result['end'] ?? NULL, "un pont qui dépasserait le bout du chemin ne se pose pas");
		$this->assertTrue($result['dry'] ?? FALSE, "un chemin qui croise une route ne fait pas de pont");

	}

	/**
	 * Une route de cinq mètres sur l'eau : son tablier couvre la chaussée et son bas-côté d'un mètre, plus les parapets.
	 */
	public function testARoadBridgeCoversItsVerge(): void {

		$result = $this->run('
			const decor = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "cours-eau", [[0, -20], [0, 20]], 3)]);
			await settled();
			emit({ count: crossingsOf(decor).length, lift: crossingsOf(decor)[0]?.lift, ...extent(decor) });
		');

		$this->assertEquals(1, $result['count'] ?? NULL, "une route sur l'eau fait un pont");
		$this->assertEquals(6.6, round($result['width'] ?? 0, 2), "le tablier d'une route fait 5 m, 1 m de bas-côté et 0,6 m de parapets");
		$this->assertEquals(3.4 + 2.4 * 3, round($result['length'] ?? 0, 2), "l'arche s'ouvre à 3,4 m sur 3 m d'eau, les rampes s'allongent du triple");
		$this->assertEquals(0.12, round($result['lift'] ?? 0, 3), "la nappe se pose sur la chaussée, la plus haute couche de la route");

	}

	/**
	 * Sur une route, les rampes s'allongent et le tablier prend le gris de la chaussée ; la longueur que `crossings()` compte pour
	 * placer le pont est celle du pont dessiné. Sur un chemin, rien ne change : rampes du modèle, tablier clair.
	 */
	public function testARoadBridgeHasLongRampsAndAGreyDeck(): void {

		$result = $this->run('
			const colors = decor => {
				const color = decor.bridges.geometry.attributes.color;
				const count = hex => {
					const tint = new THREE.Color(hex);
					return Array.from({ length: color.count }, (_, i) => Math.abs(color.getX(i) - tint.r) + Math.abs(color.getY(i) - tint.g) + Math.abs(color.getZ(i) - tint.b) < 1e-4).filter(Boolean).length;
				};
				return { deck: count(MapSceneDecor.BRIDGE_DECK), road: count(MapSceneDecor.ROAD.at(-1)[2]) };
			};
			const road = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			const path = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			await settled();
			emit({ road: { counted: crossingsOf(road)[0].length, ...extent(road), ...colors(road) }, path: { counted: crossingsOf(path)[0].length, ...extent(path), ...colors(path) } });
		');

		$this->assertEquals(round($result['road']['counted'] ?? -1, 3), round($result['road']['length'] ?? 0, 3), "le pont d'une route est aussi long que celui que ses règles comptent");
		$this->assertEquals(1.4 + 2.4 * 3, round($result['road']['length'] ?? 0, 2), "sur 1 m d'eau, l'arche d'une route s'ouvre à 1,4 m et ses rampes s'allongent du triple");
		$this->assertEquals(72, $result['road']['road'] ?? NULL, "les 72 sommets du tablier d'une route prennent le gris de la chaussée");
		$this->assertEquals(0, $result['road']['deck'] ?? NULL, "il ne reste rien du tablier clair sur une route");
		$this->assertEquals(round($result['path']['counted'] ?? -1, 3), round($result['path']['length'] ?? 0, 3), "le pont d'un chemin est aussi long que celui que ses règles comptent");
		$this->assertEquals(1.4 + 2.4, round($result['path']['length'] ?? 0, 2), "un chemin garde les rampes du modèle");
		$this->assertEquals(72, $result['path']['deck'] ?? NULL, "un chemin garde son tablier clair");

	}

	/**
	 * Le pont allongé d'une route tient la règle du bout de tracé : sur une route courte, il dépasserait et ne se pose pas, là où le
	 * pont plus court d'un chemin du même tracé se pose.
	 */
	public function testAShortRoadMakesNoLongBridge(): void {

		$result = $this->run('
			const road = draw([trace(1, "route", [[-4, 0], [6, 0]], null), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			const path = draw([trace(1, "chemin", [[-4, 0], [6, 0]], 1), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			await settled();
			emit({ road: crossingsOf(road).length, path: crossingsOf(path).length });
		');

		$this->assertEquals(0, $result['road'] ?? NULL, "à 4 m du bout de la route, un pont de 8,6 m ne se pose pas");
		$this->assertEquals(1, $result['path'] ?? NULL, "le même tracé en chemin porte un pont de 3,8 m");

	}

	/**
	 * Une route droite porte des tirets de ROAD_DASH mètres tous les ROAD_PITCH mètres, deux triangles chacun, à ROAD_MARK du sol ; un
	 * chemin n'en porte aucun, ni une route au calque caché.
	 */
	public function testAStraightRoadIsDashedAndAPathIsNot(): void {

		$result = $this->run('
			const road = draw([trace(1, "route", [[-20, 0], [20, 0]], null)]);
			const path = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1)]);
			const hidden = draw([{ ...trace(1, "route", [[-20, 0], [20, 0]], null), layer: 12 }]);
			await settled();
			hidden.layers.hidden.add(12);
			hidden.layersChanged();
			await settled();
			const positions = road.stripes.geometry.attributes.position;
			const xs = Array.from({ length: positions.count }, (_, i) => positions.getX(i));
			emit({ vertices: positions.count, heights: [...new Set(Array.from({ length: positions.count }, (_, i) => Math.round(positions.getY(i) * 1000)))], from: Math.min(...xs), to: Math.max(...xs), path: path.stripes === null, hidden: hidden.stripes === null });
		');

		$this->assertEquals(7 * 6, $result['vertices'] ?? NULL, "40 m de route droite portent sept tirets de deux triangles");
		$this->assertEquals([140], $result['heights'] ?? NULL, "le marquage est à 0,14 m, un cran au-dessus de la chaussée");
		$this->assertEquals(-18.5, round($result['from'] ?? 0, 2), "le premier tiret commence à un demi-intervalle du bout");
		$this->assertEquals(20.0, round($result['to'] ?? 0, 2), "le dernier tiret s'arrête au bout de la route");
		$this->assertTrue($result['path'] ?? FALSE, "un chemin n'a pas de marquage");
		$this->assertTrue($result['hidden'] ?? FALSE, "une route au calque caché n'a pas de marquage");

	}

	/**
	 * Le marquage s'interrompt à un carrefour, en croix comme en T : aucun tiret dans la chaussée de l'autre route, bas-côté compris.
	 * Une route qui s'arrête au bord d'une autre en coupe le milieu ; deux routes qui se touchent bout à bout se continuent, trois
	 * routes parties du même point font un carrefour.
	 */
	public function testTheMarkingStopsAtJunctions(): void {

		$result = $this->run('
			const near = decor => {
				const positions = decor.stripes.geometry.attributes.position;
				return Math.min(...Array.from({ length: positions.count }, (_, i) => Math.hypot(positions.getX(i), positions.getZ(i))));
			};
			const cross = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "route", [[0, -20], [0, 20]], null)]);
			const tee = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "route", [[0, 3], [0, 20]], null)]);
			const path = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "chemin", [[0, -20], [0, 20]], 2)]);
			await settled();
			const bands = decor => decor.stripes.geometry.attributes.position.count / 6;
			const angle = 20 * Math.PI / 180;
			const tip = [20 * Math.cos(angle), 20 * Math.sin(angle)];
			const joined = draw([trace(1, "route", [[-20, 0], [0, 0]], null), trace(2, "route", [[0, 0], tip], null)]);
			const bent = draw([trace(1, "route", [[-20, 0], [0, 0], tip], null)]);
			const star = draw([trace(1, "route", [[0, 0], [-20, 0]], null), trace(2, "route", [[0, 0], [20, 0]], null), trace(3, "route", [[0, 0], [0, 20]], null)]);
			const overshoot = draw([trace(1, "route", [[-20, 0], [1, 0]], null), trace(2, "route", [[0, 0], [0, 20]], null)]);
			await settled();
			emit({ cross: near(cross), tee: near(tee), path: near(path), joined: bands(joined), bent: bands(bent), star: near(star), overshoot: near(overshoot) });
		');

		$this->assertTrue(($result['cross'] ?? 0) >= 2.99, "en croix, le marquage s'arrête à 3 m du centre, au bord de l'autre route et de son bas-côté : ".($result['cross'] ?? 'rien'));
		$this->assertTrue(($result['tee'] ?? 0) >= 2.99, "en T, la route qui s'arrête au bord de l'autre en coupe le marquage : ".($result['tee'] ?? 'rien'));
		// Le coude d'une route seule y ajoute deux bandes à un tiret, que deux routes séparées n'ont pas : le tiret s'arrête au bout
		// de la première et reprend au pas de la seconde
		$this->assertTrue(($result['joined'] ?? 0) >= ($result['bent'] ?? 0) - 2, "deux routes bout à bout à 20° gardent leur marquage comme une seule route coudée : ".($result['joined'] ?? 'rien')." bandes contre ".($result['bent'] ?? 'rien'));
		$this->assertTrue(($result['star'] ?? 0) >= 2.99, "trois routes parties du même point font un T et coupent leur marquage : ".($result['star'] ?? 'rien'));
		$this->assertTrue(($result['overshoot'] ?? 0) >= 2.99, "une route qui dépasse d'un mètre la branche qui la rejoint garde son carrefour : ".($result['overshoot'] ?? 'rien'));
		$this->assertTrue(($result['path'] ?? 0) >= 1.14, "un chemin de 2 m coupe aussi le marquage, sur sa largeur et sa marge : ".($result['path'] ?? 'rien'));

	}

	/**
	 * Sur un pont, le marquage quitte la chaussée pour le dessus du tablier, au même pas : au sommet de l'arche, il passe au-dessus
	 * d'un mètre, et il ne reste aucun tiret au niveau du sol sous le pont.
	 */
	public function testTheMarkingClimbsOnTheBridge(): void {

		$result = $this->run('
			const decor = draw([trace(1, "route", [[-20, 0], [20, 0]], null), trace(2, "cours-eau", [[0, -20], [0, 20]], 1)]);
			await settled();
			const [bridge] = crossingsOf(decor);
			const positions = decor.stripes.geometry.attributes.position;
			const points = Array.from({ length: positions.count }, (_, i) => [positions.getX(i), positions.getY(i)]);
			emit({ top: Math.max(...points.map(([, y]) => y)), sunk: points.filter(([x, y]) => Math.abs(x) < bridge.length / 2 - .01 && y < .15).length, deck: points.filter(([x, y]) => Math.abs(x) < 1 && y > 1).length });
		');

		$this->assertTrue(($result['top'] ?? 0) > 1.05, "au sommet de l'arche, le marquage passe au-dessus du tablier : ".($result['top'] ?? 'rien'));
		$this->assertTrue(($result['deck'] ?? 0) > 0, "un tiret passe sur le milieu du pont");
		$this->assertEquals(0, $result['sunk'] ?? NULL, "aucun tiret ne reste au sol sous le pont");

	}

	/**
	 * Une route se pose en travers d'un cours d'eau, qu'elle passe sur son pont, dans un sens comme dans l'autre. Une haie, une bande
	 * fleurie et une clôture butent toujours sur elle (`MapSceneDecor.clash()`).
	 */
	public function testARoadCrossesAStreamButNotAHedge(): void {

		$result = $this->run('
			const crossing = [[0, -20], [0, 20]];
			const decor = draw([
				trace(1, "route", [[-20, 0], [20, 0]], null),
				trace(2, "cours-eau", crossing, 2),
				trace(3, "haie", crossing, null),
				trace(4, "bande-fleurie", crossing, null),
				{ ...trace(5, "cloture", crossing, null), model: "sheep" }
			]);
			await settled();
			const [road, stream, ...barred] = decor.elements;
			const clash = (e, other) => decor.clash(e, other, MapSceneDecor.footprint(e), null);
			emit({
				bridges: crossingsOf(decor).length,
				stream: [clash(road, stream), clash(stream, road)],
				barred: Object.fromEntries(barred.map(e => [e.kit, [clash(road, e), clash(e, road)]]))
			});
		');

		$this->assertEquals(1, $result['bridges'] ?? NULL, "une route posée en travers d'un cours d'eau y trouve son pont");
		$this->assertEquals([FALSE, FALSE], $result['stream'] ?? NULL, "une route et un cours d'eau se croisent, quel que soit celui qu'on pose");
		$this->assertEquals([
			'haie' => [TRUE, TRUE],
			'bande-fleurie' => [TRUE, TRUE],
			'cloture' => [TRUE, TRUE]
		], $result['barred'] ?? NULL, "une haie, une bande fleurie et une clôture butent sur la route, quel que soit celui qu'on pose");

	}

	/**
	 * L'étirement par tranches garde l'épaisseur des parapets (0,3 m) et la longueur des rampes (1,2 m) à toutes les largeurs.
	 */
	public function testStretchingKeepsParapetsAndRamps(): void {

		$result = $this->run('
			const positions = Float32Array.from(modelPositions);
			const kept = [1.9, 3.9, 6.6].map(width => {
				const shaped = MapSceneDecor.shapeBridge(Float32Array.from(positions), 1.3, 3, (width - MapSceneDecor.BRIDGE_ACROSS) / 2);
				const at = (axis, value) => [...positions.keys()].filter(i => i % 3 === axis && Math.abs(positions[i] - value) < 1e-3).map(i => shaped[i]);
				return { parapet: Math.min(...at(2, 1.3)) - Math.max(...at(2, 1)), ramp: Math.min(...at(0, 2.5)) - Math.max(...at(0, 1.3)) };
			});
			emit(kept);
		');

		foreach([1.9, 3.9, 6.6] as $i => $width) {
			$this->assertEquals(0.3, round($result[$i]['parapet'] ?? 0, 3), "les parapets gardent 0,3 m sur un tablier de ".$width." m");
			$this->assertEquals(1.2, round($result[$i]['ramp'] ?? 0, 3), "les rampes gardent 1,2 m sur un tablier de ".$width." m");
		}

	}

	/**
	 * Le pont suit ses tracés : il se déplace avec l'eau qu'on déplace, disparait avec un calque caché ou un tracé masqué, revient
	 * quand on le remontre, part avec l'eau retirée, et la scène fermée ne le refait plus.
	 */
	public function testTheBridgeFollowsMovesLayersAndClosing(): void {

		$result = $this->run('
			const decor = draw([trace(1, "chemin", [[-20, 0], [20, 0]], 1), { ...trace(2, "cours-eau", [[0, -20], [0, 20]], 2), layer: 12 }]);
			const stream = decor.elements.find(e => e.kit === "cours-eau");
			const steps = {};
			await settled();

			stream.p = { x: stream.p.x + 5, n: stream.p.n };
			decor.settle(stream);
			await settled();
			steps.moved = center(decor);

			decor.layers.hidden.add(12);
			decor.layersChanged();
			await settled();
			steps.hidden = (decor.bridges === null);

			decor.layers.hidden.delete(12);
			decor.layersChanged();
			await settled();
			steps.shown = (decor.bridges !== null);

			decor.mask(stream, true);
			await settled();
			steps.masked = (decor.bridges === null);

			decor.mask(stream, false);
			await settled();
			steps.unmasked = (decor.bridges !== null);

			// Comme `erase()`, qui retire de la scène puis de la liste dans le même tour
			decor.drop(stream);
			decor.elements.splice(decor.elements.indexOf(stream), 1);
			await settled();
			steps.removed = (decor.bridges === null);

			let rebuilt = 0;
			decor.bridge = () => rebuilt++;
			decor.dispose();
			await settled();
			steps.rebuiltAfterClose = rebuilt;

			emit(steps);
		');

		$this->assertEquals(5.0, round($result['moved'] ?? 0, 2), "l'eau déplacée de 5 m emporte le pont");
		$this->assertTrue($result['hidden'] ?? FALSE, "le calque de l'eau caché retire le pont");
		$this->assertTrue($result['shown'] ?? FALSE, "le calque remontré remet le pont");
		$this->assertTrue($result['masked'] ?? FALSE, "l'eau masquée retire le pont");
		$this->assertTrue($result['unmasked'] ?? FALSE, "l'eau remontrée remet le pont");
		$this->assertTrue($result['removed'] ?? FALSE, "l'eau retirée emporte le pont");
		$this->assertEquals(0, $result['rebuiltAfterClose'] ?? NULL, "la scène fermée ne refait pas ses ponts");

	}

	/**
	 * Le pire que la scène admette, 2 700 m de tracés posés au mètre et lissés, se croise sans comparer chaque segment de chemin à
	 * chaque segment d'eau : la grille ne lui montre que l'eau de ses cases. Comparés deux à deux, les quelque 5 400 segments de chaque
	 * côté feraient 29 millions de calculs, plusieurs secondes ; par la grille, quelques millisecondes dans le navigateur, et dix fois
	 * plus sous `vm`, où chaque nom global se cherche par le contexte. La borne de 100 ms, sur la meilleure de cinq passes, sépare les
	 * deux sans dépendre de la charge de la machine.
	 */
	public function testTheWorstCaseIsCheap(): void {

		$result = $this->run('
			const zigzag = (from, to, step, shift) => Array.from({ length: Math.round((to - from) / step) + 1 }, (_, i) => ({ x: from + i * step, n: (i % 2) * shift }));
			const walked = [{ kit: "chemin", p: { x: 0, n: 0 }, path: MapSceneDecor.smooth(zigzag(0, 1350, 1, .5)), wide: 1, def: { layers: MapSceneDecor.TRACK } }];
			const streams = Array.from({ length: 45 }, (_, k) => ({ kit: "cours-eau", p: { x: 15 + k * 30, n: -15 }, path: MapSceneDecor.smooth(Array.from({ length: 31 }, (_, i) => ({ x: (i % 2) * .5, n: i }))), wide: 1, def: { wide: 1 } }));
			const times = [];
			let bridges;
			for(let i = 0; i < 5; i++) {
				const start = performance.now();
				bridges = MapSceneDecor.crossings(walked, streams, 2.4);
				times.push(performance.now() - start);
			}
			emit({ times, ms: Math.min(...times), bridges: bridges.length, segments: walked[0].path.length + streams.reduce((sum, stream) => sum + stream.path.length, 0) });
		');

		$this->assertTrue(($result['segments'] ?? 0) > 5000, "le cas éprouvé compte plus de 5 000 segments lissés : ".($result['segments'] ?? 0));
		$this->assertEquals(45, $result['bridges'] ?? NULL, "chaque eau croisée fait son pont");
		$this->assertTrue(($result['ms'] ?? 1000) < 100, "les croisements du pire cas se trouvent sans comparer tout à tout : ".json_encode($result['times'] ?? NULL)." ms");

	}

	/**
	 * Ponts et marquage se refont ensemble à chaque geste sur un tracé. Sur le pire cas de la scène, 1 350 m de route en zigzag au
	 * mètre et 45 cours d'eau qui la croisent, les deux passes réunies prennent 120 ms environ sous `vm`, où chaque nom global se
	 * cherche par le contexte, dix fois moins dans le navigateur ; une comparaison de chaque segment de la route aux autres en
	 * prendrait plusieurs secondes. La borne de 300 ms, sur la meilleure de cinq passes, sépare les deux sans dépendre de la charge
	 * de la machine.
	 */
	public function testBridgesAndMarkingTogetherAreCheap(): void {

		$result = $this->run('
			const zigzag = (from, to, step, shift) => Array.from({ length: Math.round((to - from) / step) + 1 }, (_, i) => [from + i * step, (i % 2) * shift]);
			const decor = draw([
				trace(1, "route", zigzag(0, 1350, 1, .5), null),
				...Array.from({ length: 45 }, (_, k) => trace(2 + k, "cours-eau", Array.from({ length: 31 }, (_, i) => [15 + k * 30 + (i % 2) * .5, i - 15]), 1))
			]);
			await settled();
			const times = [];
			for(let i = 0; i < 5; i++) {
				const start = performance.now();
				decor.stripe(decor.bridge());
				times.push(performance.now() - start);
			}
			emit({ times, ms: Math.min(...times), bridges: crossingsOf(decor).length, dashes: decor.stripes.geometry.attributes.position.count / 6 });
		');

		$this->assertEquals(45, $result['bridges'] ?? NULL, "chaque eau croisée fait son pont");
		$this->assertTrue(($result['dashes'] ?? 0) > 150, "la route porte son marquage : ".($result['dashes'] ?? 0)." bandes");
		$this->assertTrue(($result['ms'] ?? 1000) < 300, "ponts et marquage du pire cas se refont sans comparer tout à tout : ".json_encode($result['times'] ?? NULL)." ms");

	}

	/**
	 * Joue un scénario sous node avec les fichiers du décor chargés comme le navigateur les charge, three, et le vrai modèle cuit, et
	 * rend ce qu'il émet.
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
				const addons = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js");
				const { sceneBuildModel } = await import("file://" + directory + "/base/scene/static/js/model.js");
				const { sceneCreateGeometry } = await import("file://" + directory + "/base/scene/static/js/geometry.js");
				const { sceneCreateFigure } = await import("file://" + directory + "/base/scene/static/js/figure.js");

				const load = file => readFileSync(directory + "/" + file);
				const bin = load("base/scene/static/model/models.bin");
				const baked = { index: JSON.parse(load("base/scene/static/model/models.json").toString("utf8")), buffer: bin.buffer.slice(bin.byteOffset, bin.byteOffset + bin.byteLength) };
				const figure = sceneCreateFigure(THREE, addons, sceneCreateGeometry(THREE, addons), sceneBuildModel);
				const modelPositions = sceneBuildModel(THREE, baked, "pont").parts.flatMap(part => [...part.geometry.attributes.position.array]);
				const modelVertices = modelPositions.length / 3;

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapSceneDecor } = vm.runInContext(source + "\n;({ MapSceneDecor })", context);

				const trace = (id, kit, points, width) => ({ id, kit, seed: id, angle: 0, size: 1, building: null, layer: null, diameter: null, laying: null, width, model: null, hedge: false, gate: null, zone: null, plot: null, coordinates: [0, 0], trace: points });
				const draw = rows => new MapSceneDecor({
					socle: { THREE, addons, figure, baked },
					plan: { center: [4.85, 45.75], terrain: { grass: "meadow", soil: "brown" }, zones: [], decor: rows, layers: [{ id: 12, name: "Eau", type: null }] },
					geometry: { beds: [], plots: new Map(), zones: new Map(), project: ([x, n]) => ({ x, n }), height: () => 0, relief: null },
					parent: new THREE.Group(),
					gradientMap: null,
					diameterLabel: null,
					changed: () => {},
					walked: () => {}
				});
				// Les ponts se refont dans une microtâche du contexte de la scène : un tour de la boucle les laisse passer
				const settled = () => new Promise(resolve => setTimeout(resolve, 0));
				const shown = decor => decor.elements.filter(e => decor.shows(e));
				const crossingsOf = decor => MapSceneDecor.crossings(shown(decor).filter(e => MapSceneDecor.WALKED.includes(e.kit)), shown(decor).filter(e => e.kit === "cours-eau"), 2.4);
				const box = decor => new THREE.Box3().setFromBufferAttribute(decor.bridges.geometry.attributes.position);
				const extent = decor => {
					const { min, max } = box(decor);
					return { length: max.x - min.x, width: Math.max(Math.abs(min.z), Math.abs(max.z)) * 2 - 2 * (1.34 - 1.3) };
				};
				const center = decor => {
					const { min, max } = box(decor);
					return (min.x + max.x) / 2;
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
