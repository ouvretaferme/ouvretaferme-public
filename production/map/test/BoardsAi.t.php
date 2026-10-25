<?php
namespace map;

/**
 * Les pancartes de parcelle de la scène (`static/js/scene.js`, `MapSceneBoards`), éprouvées sous node telles que le navigateur
 * charge le fichier : toute parcelle a la sienne, y compris celle que des planches serrent de tous côtés, toutes ont la même
 * taille, et un nom qui dépasse de la face passe sur deux lignes dans un corps plus petit, puis se coupe.
 *
 * Aucune écriture, aucune base ouverte : le parcellaire est écrit dans le scénario, en mètres, et la face peinte s'écrit sur une
 * toile esquissée dont la police se mesure en parts du corps.
 *
 * Lancement : dev/test package=map file=BoardsAi
 */
class BoardsAiTest extends \Test {

	public function testEveryParcelHasItsBoard(): void {

		$result = $this->run('
			// Des planches d’un mètre, au pas de 1,2 m, sur tout le rectangle
			const fill = (x0, n0, x1, n1) => Array.from({ length: Math.floor((n1 - n0) / 1.2) }, (_, i) => ({ origin: { x: x0, n: n0 + i * 1.2 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, length: x1 - x0, width: 1, plot: 0 }));
			const rectangle = (x0, n0, x1, n1) => [{ x: x0, n: n0 }, { x: x1, n: n0 }, { x: x1, n: n1 }, { x: x0, n: n1 }];
			const square = (x, n, half) => rectangle(x - half, n - half, x + half, n + half);
			const field = (name, zone, points) => ({ plot: { name, zone, mode: "open-field" }, points, drawn: true });

			const geometry = {
				beds: [
					// Devant « Devant », sur ses deux côtés tournés vers la caméra
					...fill(105.5, 60, 140, 105), ...fill(95, 60, 105.5, 94.5),
					// Tout autour de « Enclave », à plus de vingt mètres à la ronde, et dessus
					...fill(260, 260, 340, 340),
					// Sur « Trouée » et tout autour, sauf un carré d’herbe devant elle, où ne tient qu’une pancarte, à trois mètres du bord
					...fill(560, 560, 640, 595), ...fill(560, 605, 640, 640), ...fill(560, 595, 601, 605), ...fill(611.5, 595, 640, 605),
					// Sur « Serrée », et rien autour
					...fill(-605, -605, -595, -595),
					// Sur « Lointaine » et à cinq mètres et demi autour
					...fill(-608.5, 591.5, -591.5, 608.5),
					// Sur « Couverte », et rien autour
					...fill(8995, -5, 9005, 5),
					// Sur « Cernée » et à cinq mètres et demi autour
					...fill(9991.5, -8.5, 10008.5, 8.5),
					// Sur « Assiégée » et à cinq mètres et demi autour, puis derrière les places qui lui tournent le dos
					...fill(10991.5, -8.5, 11008.5, 8.5), ...fill(10976, 4, 10981, 9), ...fill(10992, 19, 10998, 24),
					// Une planche de la zone « Zone libre », hors de tout tracé
					{ origin: { x: 4020, n: 20 }, u: { x: 1, n: 0 }, w: { x: 0, n: 1 }, length: 5, width: 1, plot: 29 }
				],
				plots: new Map([
					[1, field("Seule", 1, square(0, 0, 5))],
					[2, field("Devant", 1, square(100, 100, 5))],
					[3, field("Enclave", 1, square(300, 300, 3))],
					[4, field("Miette", 1, [{ x: 500, n: 500 }, { x: 500.3, n: 500 }, { x: 500.3, n: 500.3 }])],
					[5, { plot: { name: "Abri", zone: 1, mode: "greenhouse" }, points: square(-100, -100, 5), drawn: true }],
					// « Mitoyenne » touche une voisine sur chacun de ses deux côtés tournés vers la caméra
					[6, field("Mitoyenne", 1, square(-200, 200, 5))],
					[7, field("Est", 1, square(-185, 200, 10))],
					[8, field("Sud", 1, square(-200, 185, 10))],
					[9, field("Trouée", 1, square(600, 600, 3))],
					// « Serrée » a une voisine à six mètres de chacun de ses côtés, et à cinq mètres et demi au couchant
					[10, field("Serrée", 1, square(-600, -600, 5))],
					[11, field("Levant", 1, rectangle(-589, -640, -560, -560))],
					[12, field("Couchant", 1, rectangle(-640, -640, -610.5, -560))],
					[13, field("Midi", 1, rectangle(-611, -640, -589, -611))],
					[14, field("Nord", 1, rectangle(-611, -589, -589, -560))],
					[15, field("Lointaine", 1, square(-600, 600, 3))],
					// « Bordée » a une voisine à 2,7 m de chacun de ses deux côtés tournés vers la caméra : une place à 1,5 m d’elle
					// y est à 1,2 m de la voisine
					[16, field("Bordée", 1, square(1000, 0, 5))],
					[17, field("Bord est", 1, rectangle(1007.7, -5, 1017.7, 5))],
					[18, field("Bord sud", 1, rectangle(995, -17.7, 1005, -7.7))],
					// « Jumelle » et sa voisine sont côte à côte face à la caméra, à moins d’une largeur de pancarte
					[19, field("Jumelle", 1, square(2000, 0, 1))],
					[20, field("Jumelle bis", 1, square(2003, 3, 1))],
					// « Voisin abri » a un abri à un mètre et demi de son côté le plus proche de la caméra
					[21, field("Voisin abri", 1, rectangle(-111.5, -102, -105.5, -98))],
					// « Dedans » est tracée dans une zone qui fait parcelle elle aussi, avec les deux voisines de « Bordée »
					[22, field("Dedans", 4, square(4000, 0, 5))],
					[23, field("Dedans est", 4, rectangle(4007.7, -5, 4017.7, 5))],
					[24, field("Dedans sud", 4, rectangle(3995, -17.7, 4005, -7.7))],
					// « Vitrée » a un abri en bande à un mètre de ses deux côtés tournés vers la caméra, « Éloignée » un anneau d’abris d’un à six mètres
					[25, field("Vitrée", 1, square(5000, 0, 5))],
					[26, field("Éloignée", 1, square(6000, 0, 5))],
					// « Losange » présente à la caméra un côté dont toutes les places sont à la même distance d’elle
					[27, field("Losange", 1, [{ x: 7000, n: -5 }, { x: 7005, n: 0 }, { x: 7000, n: 5 }, { x: 6995, n: 0 }])],
					// Une parcelle tracée sans nom
					[28, field(null, 1, square(8000, 0, 5))],
					[29, { plot: { name: "Non tracée", zone: 4, mode: "open-field" }, points: [], drawn: false }],
					...[rectangle(5005.5, -6, 5007, 6), rectangle(4994, -7, 5007, -5.5), rectangle(5988.5, -11.5, 5994, 11.5), rectangle(6006, -11.5, 6011.5, 11.5), rectangle(5994, -11.5, 6006, -6), rectangle(5994, 6, 6006, 11.5)].map((points, i) => [30 + i, { plot: { name: "Abri " + i, zone: 1, mode: "greenhouse" }, points, drawn: true }]),
					[40, field("Couverte", 1, square(9000, 0, 5))],
					// « Cernée » a une voisine contre chacun de ses deux côtés qui tournent le dos à la caméra, sur treize mètres
					[41, field("Cernée", 1, square(10000, 0, 3))],
					[42, field("Cerne ouest", 1, rectangle(9984, -4, 9997, 4))],
					[43, field("Cerne nord", 1, rectangle(9996, 3, 10004, 16))],
					// « Assiégée » a une voisine contre chacun de ses deux côtés tournés vers la caméra, et toute place y cache des planches
					[44, field("Assiégée", 1, square(11000, 0, 3))],
					[45, field("Siège est", 1, rectangle(11003, -4, 11016, 4))],
					[46, field("Siège sud", 1, rectangle(10996, -16, 11004, -3))],
					// « Coincée » et « Reculée » sont cernées d’abris qui ne leur laissent que deux places, à 1,5 m et à 3 m devant elles.
					// La pancarte de « Gêneuse » recouvre la première à la taille où une place se juge, et la seconde à la plus grande
					// taille seulement ; celle de « Devancière » ne recouvre que la première, et à la plus grande taille seulement. « Frôlée »
					// est cernée de même, et la pancarte de « Frôleuse » recouvre ses deux places à la plus grande taille, la seconde moins.
					// « Bouchée » n’a que la place à 1,5 m, que la pancarte de « Boucheuse » recouvre à la taille où une place se juge
					[47, field("Gêneuse", 1, square(11997.65, -2.6, .5))],
					[48, field("Coincée", 1, square(12000, 0, 1))],
					[49, field("Devancière", 1, square(12995.18, 9.07, .5))],
					[50, field("Reculée", 1, square(13000, 0, 1))],
					[51, field("Frôleuse", 1, square(13996.24, 8.01, .5))],
					[52, field("Frôlée", 1, square(14000, 0, 1))],
					[53, field("Boucheuse", 1, square(14997.65, -2.6, .5))],
					[54, field("Bouchée", 1, square(15000, 0, 1))],
					...[[12000, 4.5], [13000, 4.5], [14000, 4.5], [15000, 3]].flatMap(([x, second], at) => [rectangle(x + 2.2, .25, x + 6.2, 1), rectangle(x + 2.2, -1, x + 6.2, -.25), rectangle(x + second, -.25, x + 6.2, .25), rectangle(x - 1, -6.2, x + 1, -2), rectangle(x - 1, 2, x + 1, 6.2), rectangle(x - 6.2, -1, x - 2, 1)].map((points, i) => [60 + at * 6 + i, { plot: { name: "Abri de coin " + i, zone: 1, mode: "greenhouse" }, points, drawn: true }]))
				]),
				zones: new Map([[1, square(150, 150, 500)], [2, square(-300, 300, 20)], [3, []], [4, square(4000, 0, 30)]]),
				height: () => 0,
				relief: null
			};

			const plan = {
				plots: [...geometry.plots.values()].map(({ plot }) => ({ ...plot, coordinates: [] })),
				zones: [{ id: 1, name: "Zone des parcelles" }, { id: 2, name: "Zone nue" }, { id: 3, name: "Zone sans contour" }, { id: 4, name: "Zone libre" }]
			};

			const parent = new THREE.Group();
			const boards = new MapSceneBoards({ THREE, addons, plan, geometry, camera, controls, parent, gradientMap: null });

			const sizes = zoom => {
				camera.zoom = zoom;
				boards.frame(camera, controls);
				return boards.boards.map(({ board }) => board.scale.x);
			};

			const G = ScenePlanGeometry;
			const toCamera = G.norm({ x: 1, n: -1 });
			const front = (index, center) => G.dot(G.sub(boards.boards[index].base, center), toCamera);

			const adjoining = boards.boards[4].base;

			// À quelle distance du bord de sa parcelle une pancarte est plantée
			const gap = (index, id) => Math.round(MapSceneBoards.distance(boards.boards[index].base, geometry.plots.get(id).points) * 1000) / 1000;

			// De combien le pied d’une pancarte s’écarte de la place où elle est plantée
			const stepped = (zoom, index) => {
				camera.zoom = zoom;
				boards.frame(camera, controls);
				const { board, base } = boards.boards[index];
				return Math.round(Math.hypot(board.position.x - base.x, -board.position.z - base.n) * 1000) / 1000;
			};

			emit({
				bordered: front(14, { x: 1000, n: 0 }),
				squeezed: front(8, { x: -600, n: -600 }),
				covered: [front(26, { x: 9000, n: 0 }), gap(26, 40)],
				besieged: [front(30, { x: 11000, n: 0 }), gap(30, 44), [45, 46].some(id => G.inside(boards.boards[30].base, geometry.plots.get(id).points))],
				squeezedAt: boards.boards[8].base,
				ringedIn: [front(27, { x: 10000, n: 0 }), gap(27, 41), [42, 43].some(id => G.inside(boards.boards[27].base, geometry.plots.get(id).points))],
				sheltered: G.inside(boards.boards[19].base, geometry.plots.get(5).points),
				within: front(20, { x: 4000, n: 0 }),
				glazed: gap(23, 25),
				ringed: gap(24, 26),
				diamond: [Math.round(boards.boards[25].base.x * 1000) / 1000, Math.round(boards.boards[25].base.n * 1000) / 1000],
				cornered: [gap(34, 48), front(34, { x: 12000, n: 0 }) > 0],
				receded: [gap(36, 50), front(36, { x: 13000, n: 0 }) > 0],
				brushed: [gap(38, 52), front(38, { x: 14000, n: 0 }) > 0],
				plugged: gap(40, 54),
				twins: [G.norm({ x: 1, n: 1 }), toCamera].map(axis => Math.round(Math.abs(G.dot(G.sub(boards.boards[18].base, boards.boards[17].base), axis)) * 1000) / 1000),
				stepped: [stepped(1, 0), stepped(20, 0)],
				names: written,
				lent: [7, 8].some(id => G.inside(adjoining, geometry.plots.get(id).points)),
				adjoining: Math.hypot(adjoining.x + 200, adjoining.n - 200),
				meshes: parent.children.length,
				far: sizes(1),
				near: sizes(20),
				// De quel côté de sa parcelle la pancarte est plantée : positif, entre elle et la caméra
				front: { alone: front(0, { x: 0, n: 0 }), behind: front(1, { x: 100, n: 100 }) },
				enclosed: { away: G.length(G.sub(boards.boards[2].base, { x: 300, n: 300 })), front: front(2, { x: 300, n: 300 }) },
				pierced: boards.boards[7].base,
				gaps: { alone: gap(0, 1), behind: gap(1, 2), squeezed: gap(8, 10), distant: gap(13, 15) },
				crumb: boards.boards[3].base
			});
		');

		$this->assertEquals(['Seule', 'Devant', 'Enclave', 'Miette', 'Mitoyenne', 'Est', 'Sud', 'Trouée', 'Serrée', 'Levant', 'Couchant', 'Midi', 'Nord', 'Lointaine', 'Bordée', 'Bord est', 'Bord sud', 'Jumelle', 'Jumelle bis', 'Voisin abri', 'Dedans', 'Dedans est', 'Dedans sud', 'Vitrée', 'Éloignée', 'Losange', 'Couverte', 'Cernée', 'Cerne ouest', 'Cerne nord', 'Assiégée', 'Siège est', 'Siège sud', 'Gêneuse', 'Coincée', 'Devancière', 'Reculée', 'Frôleuse', 'Frôlée', 'Boucheuse', 'Bouchée', 'Zone nue', 'Zone libre'], $result['names'], "chaque parcelle de plein champ tracée et chaque zone sans parcelle a sa pancarte, pas l'abri, ni la zone qui porte des parcelles, ni la parcelle sans nom");
		$this->assertEquals(43, $result['meshes'], 'une pancarte dans la scène par nom écrit');

		$this->assertEquals([3], array_values(array_unique($result['far'])), 'vue de loin, toutes les pancartes ont la même taille, la plus grande');
		$this->assertEquals([1], array_values(array_unique($result['near'])), 'vue de près, toutes ont la même taille');

		$this->assertEquals(6.364, round($result['front']['alone'], 3), 'une parcelle dégagée plante sa pancarte devant elle, à la place la plus proche de la caméra');
		$this->assertEquals(1.5, $result['gaps']['alone'], 'au plus court des écarts quand rien n\'est caché');
		$this->assertTrue($result['front']['behind'] < 0, 'une parcelle dont le devant est couvert de planches plante la sienne sur un autre côté : '.$result['front']['behind']);
		$this->assertEquals(1.5, $result['gaps']['behind'], 'au plus court de ses écarts, sans passer aux plus longs');

		$this->assertEquals(3, $result['gaps']['squeezed'], 'une parcelle dont toute place dégagée est plus près d\'une voisine prend la moins disputée, plutôt que de cacher ses planches');
		$this->assertTrue($result['squeezed'] < 0, 'derrière elle, où elle ne cache rien : '.$result['squeezed']);
		$this->assertEquals([-3.889, 3], [round($result['covered'][0], 3), $result['covered'][1]], 'une parcelle plantée jusqu\'au bord porte sa pancarte derrière elle, au premier écart où elle n\'écrase rien : devant, à la taille où une place se juge, elle cacherait ses planches');
		$this->assertEquals([11.667, 12, FALSE], [round($result['ringedIn'][0], 3), $result['ringedIn'][1], $result['ringedIn'][2]], 'une parcelle dont toute place dégagée est chez une voisine plante la sienne devant elle, quitte à cacher ses planches, plutôt que chez la voisine');
		$this->assertEquals([-9.546, 12, FALSE], [round($result['besieged'][0], 3), $result['besieged'][1], $result['besieged'][2]], 'une parcelle dont toute place cache des planches plante la sienne hors de chez ses voisines, même derrière elle');
		$this->assertEquals(-592, $result['squeezedAt']['n'], 'entre deux places disputées, celle dont la voisine est la plus loin');
		$this->assertEquals(12, $result['gaps']['distant'], 'une parcelle cernée de planches plante la sienne au premier des grands écarts qui n\'en cache aucune');

		$this->assertEquals(-2.828, round($result['bordered'], 3), 'une place plus près d\'une voisine que de sa parcelle n\'est pas la sienne, même hors du terrain de la voisine : la pancarte passe derrière');
		$this->assertEquals([9.546, 2.475], $result['twins'], 'deux pancartes de parcelles voisines ne se recouvrent pas à leur plus grande taille : côte à côte face à la caméra, elles s\'écartent de plus des 8,4 mètres de leur largeur');
		$this->assertEquals([3, TRUE], $result['cornered'], 'une place qui recouvre une autre pancarte à la taille où elle se juge n\'en est pas une, et celle qui ne la recouvre qu\'à la plus grande taille sert avant les grands écarts');
		$this->assertEquals([3, TRUE], $result['receded'], 'entre deux places près de la parcelle, celle qui ne recouvre aucune pancarte à la plus grande taille, même plus loin');
		$this->assertEquals([3, TRUE], $result['brushed'], 'entre deux places qui en recouvrent une autre à la plus grande taille, celle qui la recouvre le moins');
		$this->assertEquals(8, $result['plugged'], 'une parcelle dont la seule place proche recouvre une autre pancarte à la taille où elle se juge plante la sienne au premier des grands écarts');
		$this->assertEquals([0, 0], $result['stepped'], 'une pancarte grossit depuis son pied, qui reste où il est planté, de loin comme de près');

		$this->assertTrue($result['sheltered'] === FALSE, 'une place sous un abri voisin n\'en est pas une, même la plus proche de la caméra');
		$this->assertEquals(-2.828, round($result['within'], 3), 'la zone qui entoure une parcelle n\'est pas sa voisine, les parcelles d\'à côté le restent');
		$this->assertEquals(3, $result['glazed'], 'un côté tourné vers la caméra garde la pancarte à son écart suivant, plutôt qu\'un autre côté au plus court');
		$this->assertEquals(8, $result['ringed'], 'le premier des grands écarts sert avant le second');
		$this->assertEquals([7003.561, -3.561], $result['diamond'], 'à écart et distance de la caméra égaux, la pancarte se plante au milieu du côté');

		$this->assertTrue($result['lent'] === FALSE, 'une parcelle dont le devant touche ses voisines plante sa pancarte hors de leur terrain');
		$this->assertTrue($result['adjoining'] < 20, 'et la garde près d\'elle : '.$result['adjoining']);

		$this->assertEquals(['x' => 604.5, 'n' => 598.5], $result['pierced'], 'une parcelle dont toute place cache des planches plante la sienne là où elle n\'en écrase aucune, à la taille où une place se juge');

		$this->assertEquals(4.743, round($result['enclosed']['away'], 3), 'une parcelle sans aucune place libre plante la sienne à la première place essayée, au plus court des écarts');
		$this->assertTrue($result['enclosed']['front'] > 0, 'devant elle : '.$result['enclosed']['front']);
		$this->assertEquals([500.2, 500.1], [round($result['crumb']['x'], 3), round($result['crumb']['n'], 3)], "un contour sans côté où se planter porte sa pancarte en son milieu");

	}

	public function testLines(): void {

		$result = $this->run('
			// Onze caractères tiennent sur la toile esquissée
			const fits = name => Array.from(name).length <= 11;

			// Ce qu’une face porte : ses lignes, leur corps, et l’encre de la première à la dernière, en points de la toile
			const face = (name, advance = .5) => {

				metrics.advance = advance;
				const from = written.length;

				MapSceneBoards.texture(THREE, name, 512, 184);

				const lines = written.slice(from);
				const size = body(fonts[from]);
				const top = heights[from] - ink(lines[0], fonts[from]).ascent;
				const bottom = heights[written.length - 1] + ink(lines[lines.length - 1], fonts[from]).descent;

				return {
					lines,
					size: Math.round(size * 100) / 100,
					sizes: new Set(fonts.slice(from)).size,
					top: Math.round(top * 100) / 100,
					bottom: Math.round(bottom * 100) / 100,
					middle: Math.round((top + bottom) / 2 * 100) / 100,
					leading: (lines.length === 2) ? Math.round((heights[from + 1] - heights[from]) / size * 1000) / 1000 : null,
					// Ce qui sépare le bas de l’encre de la première ligne du haut de celle de la seconde
					between: (lines.length === 2) ? Math.round((heights[from + 1] - ink(lines[1], fonts[from]).ascent - heights[from] - ink(lines[0], fonts[from]).descent) * 100) / 100 : null
				};

			};

			emit({
				short: face("Jardin 3"),
				long: face("Le jardin des vignes"),
				capitals: face("CHAMP DU HAUT"),
				accents: face("Égypte et Épire"),
				endless: face("Le grand jardin des topinambours du bas"),
				// La chasse de quatre polices, de la plus étroite à la plus large : Ubuntu, Arial, Noto Sans, DejaVu Sans
				typefaces: [.5, .517, .523, .603].map(advance => face("Le jardin des vignes", advance).lines),
				wide: [.5, .517].map(advance => face("Poulailler", advance)),
				lines: ["Parcelle 12", "Parcelle 123", "Le jardin des vignes", "Un deux trois", "Le grand jardin des topinambours du bas", "Topinambours", "🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕", ""].map(name => MapSceneBoards.lines(name, fits)),
				// Une toile qui mesure les unités de code, deux par carotte, et en laisse tenir vingt et une : dix carottes et demie
				halves: MapSceneBoards.lines("🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕", name => name.length <= 21).map(line => line.length),
				shortened: ["Parcelle 12", "Parcelle 123", "Le jardin des", ""].map(name => MapSceneBoards.shorten(name, fits)),
				nothing: MapSceneBoards.shorten("Jardin", () => false)
			});
		');

		$this->assertEquals([['Jardin 3'], 95.68], [$result['short']['lines'], $result['short']['size']], 'un nom qui tient sur une ligne s\'écrit en 95 points sur une face de 184');
		$this->assertEquals(92, $result['short']['middle'], 'au milieu de la face');

		$this->assertEquals([['Le jardin', 'des vignes'], 77.28, 1], [$result['long']['lines'], $result['long']['size'], $result['long']['sizes']], 'un nom plus long s\'écrit en entier sur deux lignes, en 77 points l\'une et l\'autre');
		$this->assertEquals(92, $result['long']['middle'], 'deux lignes se centrent sur leur encre, du haut de la première au bas de la seconde');
		$this->assertTrue($result['long']['between'] >= 4, 'le jambage de la première ligne ne touche pas la seconde : '.$result['long']['between']);

		$this->assertEquals(0.95, $result['capitals']['leading'], 'deux lignes sans jambage ni accent se suivent à 0,95 corps');

		$this->assertTrue($result['accents']['size'] < 77.28, 'deux lignes dont l\'encre ne tient pas dans le cadre s\'écrivent plus petit : '.$result['accents']['size']);
		$this->assertEquals([14, 170, 4], [$result['accents']['top'], $result['accents']['bottom'], $result['accents']['between']], 'jusqu\'à tenir dans le cadre sans se toucher');

		$this->assertEquals(['Le grand', 'jardin des…'], $result['endless']['lines'], 'un nom qui ne tient pas en deux lignes de 77 points se coupe sur la seconde');

		$this->assertEquals(array_fill(0, 4, ['Le jardin', 'des vignes']), $result['typefaces'], 'le nom tient en entier quelle que soit la police que le poste donne à la face');
		$this->assertEquals([95.68, 77.28], array_column($result['wide'], 'size'), 'une ligne plus large que 94 % de la face passe au petit corps, même si elle tient dans la face');
		$this->assertEquals([['Poulailler'], ['Poulailler']], array_column($result['wide'], 'lines'), 'sans se couper');

		$this->assertEquals([
			['Parcelle 12'],
			['Parcelle', '123'],
			['Le jardin', 'des vignes'],
			// Au plus égal quand plusieurs partages tiennent
			['Un deux', 'trois'],
			// La première ligne prend tout ce qui tient, seule la seconde se coupe
			['Le grand', 'jardin des…'],
			// Un mot plus long que la face se partage là où elle s'arrête
			['Topinambour', 's'],
			['🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕🥕', '🥕'],
			['']
		], $result['lines']);

		$this->assertEquals([20, 4], $result['halves'], 'aucun signe n\'est coupé en deux : la ligne s\'arrête à dix carottes entières');

		// L'espace qui précèderait la coupe tombe
		$this->assertEquals(['Parcelle 12', 'Parcelle 1…', 'Le jardin…', ''], $result['shortened']);

		$this->assertEquals('…', $result['nothing'], 'un nom dont rien ne tient se réduit au signe de coupe');

	}

	public function testReads(): void {

		$result = $this->run('
			const rectangle = (x0, n0, x1, n1) => [{ x: x0, n: n0 }, { x: x1, n: n0 }, { x: x1, n: n1 }, { x: x0, n: n1 }];

			const pose = parcels => {

				const plots = new Map(parcels.map(([name, points], i) => [i + 1, { plot: { name, zone: 1, mode: "open-field" }, points, drawn: true }]));

				const boards = new MapSceneBoards({
					THREE, addons, camera, controls, parent: new THREE.Group(), gradientMap: null,
					plan: { plots: [...plots.values()].map(({ plot }) => ({ ...plot, coordinates: [] })), zones: [] },
					geometry: { beds: [], plots, zones: new Map(), height: () => 0, relief: null }
				});

				return boards.boards.map(({ base }) => [Math.round(base.x * 100) / 100, Math.round(base.n * 100) / 100]);

			};

			emit({
				// Une bande de 2 m devant une grande parcelle : dressée devant le milieu de la bande, la face monte jusque sur la grande
				behind: pose([["Bande", rectangle(0, 0, 10, 2)], ["Fond", rectangle(0, 5, 10, 15)]]),
				// La même, cernée : aucune place ne laisse la face hors de la grande
				ringed: pose([["Bande", rectangle(0, 0, 10, 2)], ["Fond", rectangle(-20, 2.6, 30, 15)], ["Gauche", rectangle(-12, -12, -2, 2)], ["Droite", rectangle(12, -12, 22, 2)]])
			});
		');

		$this->assertEquals([2.5, -1.5], $result['behind'][0], 'une pancarte dont la face se lirait aussi sur une voisine prend la première place où elle ne se lit que sur sa parcelle');
		$this->assertEquals([7.5, -1.5], $result['ringed'][0], 'faute d\'une telle place, elle garde la première dégagée');

	}

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

				// La toile de la face, esquissée : elle retient chaque ligne écrite, son corps et sa hauteur. Sa police se mesure en parts
				// du corps, comme une vraie : la chasse d’un caractère, réglable, l’encre au-dessus de la ligne d’écriture, plus haute
				// sous une capitale accentuée, et l’encre en dessous, plus basse sous un jambage
				const written = [];
				const fonts = [];
				const heights = [];
				const metrics = { advance: .5 };
				const body = font => parseFloat(font.split(" ")[1]);
				const ink = (text, font) => ({ ascent: body(font) * (/[ÔÉ]/.test(text) ? .6 : .37), descent: body(font) * (/[gjpqy]/.test(text) ? .57 : .35) });
				const context = new Proxy({ measureText: text => ({ width: Array.from(text).length * body(context.font) * metrics.advance, actualBoundingBoxAscent: ink(text, context.font).ascent, actualBoundingBoxDescent: ink(text, context.font).descent }), fillText: (text, x, y) => { written.push(text); fonts.push(context.font); heights.push(y); } }, { get: (target, key) => target[key] ?? (() => {}) });
				const document = { addEventListener: () => {}, createElement: () => ({ getContext: () => context }) };

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const { MapSceneBoards, ScenePlanGeometry } = vm.runInContext(source + "\n;({ MapSceneBoards, ScenePlanGeometry })", vm.createContext({ window: {}, document, console }));

				// La caméra d’ouverture de la scène, et la fusion des pièces d’une pancarte réduite à sa première
				const camera = { position: new THREE.Vector3(300, 300, 300), zoom: 1 };
				const controls = { target: new THREE.Vector3(0, 0, 0) };
				const addons = { mergeGeometries: pieces => pieces[0] };

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
