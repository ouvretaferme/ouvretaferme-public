<?php
namespace map;

/**
 * L'outil « Parcelle PAC » de la création d'une parcelle (`static/js/drawing.js`, `MapDrawingPac`, `MapDrawing.pickPac()`) : un clic
 * dans une parcelle déclarée pose son contour, un clic dans une voisine qui le touche l'y ajoute, un clic dans une parcelle prise
 * l'en retire. Une parcelle qui ne touche pas le contour, ou dont le retrait le couperait ou le percerait, est refusée, et un
 * contour trop détaillé se simplifie avant d'être refusé. Les parcelles se lisent une fois pour le carré autour du clic, et
 * l'année de la déclaration reste attachée au contour tant qu'il en vient.
 *
 * Lancement : dev/test package=map file=DrawingPacAi
 */
class DrawingPacAiTest extends DrawingBenchAiTest {

	// Quatre parcelles en rang d'ouest en est, A, B et D côte à côte, C seule plus loin, et la réponse du registre qui les porte
	const string PAC = '
		const U = 0.001;
		const box = (west, south) => [[west, south], [west + U, south], [west + U, south + U], [west, south + U]];
		const A = box(3.099, 45.599);
		const B = box(3.100, 45.599);
		const D = box(3.101, 45.599);
		const C = box(3.104, 45.599);
		const inside = parcel => [parcel[0][0] + U / 2, parcel[0][1] + U / 2];

		answer = { year: 2025, box: [3.09, 45.59, 3.11, 45.61], parcels: [A, B, D, C].map((coordinates, index) => ({ id: index + 1, coordinates, crop: (index === 1) ? "Prairie permanente" : null })) };

		const said = [];
		const details = [];

		// Le lien de la création, ou celui du retracé d\'une parcelle que `link` décrit
		const open = (link = {}) => {

			MapDrawing.start({ dataset: { drawing: "polygon", drawingMap: "map", drawingSheet: "zone-create", drawingCoordinates: "null", drawingFree: "", drawingRectangle: "", drawingPac: "", ...link } });

			const drawing = MapDrawing.current;
			const labels = { pacNone: "none", pacApart: "apart", pacSplit: "split", pacHole: "hole", pacDetailed: "detailed", pacAdd: "+ {value}", pacRemove: "− {value}", pacYear: "PAC {value}", pacStructure: "6914" };

			drawing.card = new Proxy(any, { get: (target, key) => (key === "dataset") ? new Proxy(labels, { get: (target, key) => target[key] ?? "{value}" }) : any[key] });
			drawing.getForm = () => ({ qs: () => any, qsa: () => [] });
			drawing.sayPac = (point, text, detail = null) => { said.push(text); details.push(detail); };
			// La séance de la Carte n\'a pas le cadrage d\'un panneau, que les méthodes prêtées de MapDrawingEmbedded appellent
			drawing.frameShape = MapDrawingEmbedded.prototype.frameShape;
			use(drawing, "pac");

			return drawing;

		};

		const use = (drawing, name) => drawing.useTool({ dataset: { tool: name }, classList: { contains: () => false } });

		const click = async (drawing, point) => {
			drawing.pickPac(point);
			await new Promise(resolve => setTimeout(resolve, 0));
		};

		const area = drawing => drawing.shape.closed ? Math.round(turf.area(turf.polygon([drawing.shape.getRing()]))) : 0;
		const parcel = coordinates => Math.round(turf.area(turf.polygon([[...coordinates, coordinates[0]]])));
	';

	public function testAClickTakesAddsAndRemovesAParcel(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();
			result.shape = drawing.shape.constructor.name;
			result.parcels = [parcel(A), parcel(B)];

			await click(drawing, inside(A));
			result.taken = { area: area(drawing), points: drawing.shape.points.length, year: drawing.pacYear, said: said.at(-1) };

			await click(drawing, inside(B));
			result.added = { area: area(drawing), points: drawing.shape.points.length };

			await click(drawing, inside(B));
			result.removed = { area: area(drawing), points: drawing.shape.points.length };

			await click(drawing, inside(A));
			result.empty = { closed: drawing.shape.closed, year: drawing.pacYear };

			result.posts = posts.map(body => ({ ...body, lng: Number(body.lng.toFixed(6)), lat: Number(body.lat.toFixed(6)) }));
			drawing.cancel();
		');

		$this->assertEquals('MapDrawingPac', $result['shape'] ?? NULL, 'l\'outil « Parcelle PAC » remplace la forme libre');

		[$areaA, $areaB] = $result['parcels'] ?? [0, 0];

		$this->assertEquals(['area' => $areaA, 'points' => 4, 'year' => 2025, 'said' => NULL], $result['taken'] ?? NULL, 'un clic dans une parcelle pose son contour et retient l\'année de la déclaration');
		$this->assertEquals(['area' => $areaA + $areaB, 'points' => 4], $result['added'] ?? NULL, 'la voisine s\'ajoute en un seul anneau, leur bord commun effacé');
		$this->assertEquals(['area' => $areaA, 'points' => 4], $result['removed'] ?? NULL, 'un clic dans une parcelle prise l\'en retire');
		$this->assertEquals(['closed' => FALSE, 'year' => NULL], $result['empty'] ?? NULL, 'la dernière parcelle retirée ne laisse aucun contour ni d\'année');

		$this->assertEquals([['structure' => '6914', 'season' => 2026, 'lng' => 3.1, 'lat' => 45.6]], $result['posts'] ?? NULL, 'les parcelles se lisent une seule fois, autour du centre de la carte dès l\'outil choisi, avec la structure et la saison que la route attend');

	}

	public function testAClickOutsideTheBoxReadsTheRegisterAgain(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			await click(drawing, inside(A));
			await click(drawing, [3.2, 45.6]);

			result.posts = posts.length;
			result.said = said.at(-1);
			drawing.cancel();
		');

		$this->assertEquals(2, $result['posts'] ?? NULL, 'un clic hors du carré lu relit le registre');
		$this->assertEquals('none', $result['said'] ?? NULL, 'sans parcelle sous le point, le clic le dit');

	}

	public function testAParcelThatWouldBreakTheContourIsRefused(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			await click(drawing, inside(A));
			await click(drawing, inside(C));
			result.apart = { said: said.at(-1), kept: area(drawing) === parcel(A) };

			await click(drawing, inside(B));
			await click(drawing, inside(D));
			const before = area(drawing);
			await click(drawing, inside(B));
			result.split = { said: said.at(-1), kept: area(drawing) === before };

			// Une parcelle au cœur d\'un contour plus grand qu\'elle le percerait
			const shape = new MapDrawingPac(null);
			shape.take({ points: [[3.098, 45.598], [3.102, 45.598], [3.102, 45.602], [3.098, 45.602]] });
			result.hole = shape.getTaken(box(3.0995, 45.5995)).refusal;

			drawing.cancel();
		');

		$this->assertEquals(['said' => 'apart', 'kept' => TRUE], $result['apart'] ?? NULL, 'une parcelle qui ne touche pas le contour est refusée, le contour reste celui de la première');
		$this->assertEquals(['said' => 'split', 'kept' => TRUE], $result['split'] ?? NULL, 'retirer la parcelle du milieu couperait le contour en deux : refusé, le contour reste entier');
		$this->assertEquals('hole', $result['hole'] ?? NULL, 'retirer une parcelle au cœur du contour le percerait : refusé');

	}

	public function testTheParcelsAreReadAndHoveredAsSoonAsTheToolIsChosen(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			// Un clic pendant la première lecture l\'attend
			const first = click(drawing, inside(A));
			result.waiting = posts.length;
			await first;
			result.taken = { area: area(drawing), posts: posts.length };

			use(drawing, "remove");
			use(drawing, "pac");
			await new Promise(resolve => setTimeout(resolve, 0));

			// Avant tout contour, le survol dit la surface de la parcelle, sans signe
			drawing.hoverPac(inside(B));
			result.hover = said.at(-1);
			result.parcel = parcel(A);

			// La vue qui sort du carré lu le relit autour de son centre
			camera.center = [3.2, 45.6];
			drawing.listeners.move();
			result.moved = posts.at(-1);

			drawing.cancel();
		');

		$this->assertEquals(1, $result['waiting'] ?? NULL, 'le carré se lit dès l\'outil choisi, et un clic pendant la lecture ne la redemande pas');
		$this->assertEquals(['area' => $result['parcel'] ?? -1, 'posts' => 1], $result['taken'] ?? NULL, 'le clic pris pendant la lecture pose la parcelle une fois la lecture faite');
		$this->assertTrue(is_string($result['hover'] ?? NULL) and str_starts_with($result['hover'], '+') === FALSE and in_array($result['hover'], ['none', 'apart', 'split', 'hole', 'detailed']) === FALSE, 'avant tout contour, la parcelle survolée dit sa surface, sans signe');
		$this->assertEquals(['structure' => '6914', 'season' => 2026, 'lng' => 3.2, 'lat' => 45.6], $result['moved'] ?? NULL, 'la vue sortie du carré lu le relit autour de son nouveau centre');

	}

	public function testAParcelWithinFiveMetresIsJoinedAndRemovedCleanly(): void {

		$result = $this->run(1512, self::PAC.'
			// F à 3 m à l\'est de A, G à 8 m au nord : les mètres d\'un degré de longitude se comptent à la latitude des parcelles
			const east = metres => metres / (111320 * Math.cos(45.6 * Math.PI / 180));
			const F = box(A[1][0] + east(3), 45.599);
			const G = box(3.099, A[2][1] + 8 / 111320);

			answer.parcels = [{ id: 1, coordinates: A, crop: null }, { id: 2, coordinates: F, crop: null }, { id: 3, coordinates: G, crop: null }];

			const drawing = open();

			await click(drawing, inside(A));
			const points = JSON.stringify(drawing.shape.points);

			drawing.hoverPac(inside(F));
			result.hover = said.at(-1).startsWith("+");

			await click(drawing, inside(F));
			result.joined = { points: drawing.shape.points.length, area: Math.abs(area(drawing) - parcel([A[0], F[1], F[2], A[3]])) <= 2 };

			await click(drawing, inside(G));
			result.apart = said.at(-1);

			await click(drawing, inside(F));
			result.removed = JSON.stringify(drawing.shape.points) === points;

			drawing.cancel();
		');

		$this->assertTrue($result['hover'] ?? FALSE, 'une parcelle à 3 m du contour se survole comme une parcelle qui le touche');
		$this->assertEquals(['points' => 4, 'area' => TRUE], $result['joined'] ?? NULL, 'elle s\'y recolle : un seul rectangle, l\'interstice compris');
		$this->assertEquals('apart', $result['apart'] ?? NULL, 'une parcelle à 8 m reste refusée');
		$this->assertTrue($result['removed'] ?? FALSE, 'la parcelle recollée retirée, le contour redevient celui de A au sommet près');

	}

	public function testRemovingAParcelLeavesNoSpike(): void {

		$result = $this->run(1512, self::PAC.'
			// Une voisine de A dont le bord sud passe 30 cm plus haut : l\'union fond son coin dans celui de A, à moins d\'un mètre
			const up = 0.3 / 111320;
			const E = [[A[1][0], A[1][1] + up], [A[1][0] + U, A[1][1] + up], [A[1][0] + U, A[2][1]], [A[1][0], A[2][1]]];

			answer.parcels = [{ id: 1, coordinates: A, crop: null }, { id: 2, coordinates: E, crop: null }];

			const drawing = open();

			await click(drawing, inside(A));
			const points = JSON.stringify(drawing.shape.points);

			await click(drawing, [E[0][0] + U / 2, E[0][1] + U / 2]);

			await click(drawing, [E[0][0] + U / 2, E[0][1] + U / 2]);
			result.removed = JSON.stringify(drawing.shape.points) === points;
			result.points = drawing.shape.points.length;

			drawing.cancel();
		');

		$this->assertEquals([TRUE, 4], [$result['removed'] ?? NULL, $result['points'] ?? NULL], 'la voisine retirée, le contour redevient celui de A, sans pointe le long de son bord sud');

	}

	public function testAParcelThatClosesARingFillsItsHole(): void {

		$result = $this->run(1512, self::PAC.'
			// Un U de trois unités de côté, ouvert au nord sur l\'unité du milieu, et la parcelle qui le referme
			const shape = new MapDrawingPac(null);
			shape.take({ points: [[3.099, 45.599], [3.102, 45.599], [3.102, 45.602], [3.101, 45.602], [3.101, 45.600], [3.100, 45.600], [3.100, 45.602], [3.099, 45.602]] });

			const contour = Math.round(turf.area(turf.polygon([shape.getRing()])));
			const taken = shape.getTaken(box(3.100, 45.601));
			shape.take(taken);

			result.added = taken.area;
			result.parcel = parcel(box(3.100, 45.601));
			result.total = Math.round(turf.area(turf.polygon([shape.getRing()])));
			result.square = parcel([[3.099, 45.599], [3.102, 45.599], [3.102, 45.602], [3.099, 45.602]]);
			result.contour = contour;
		');

		$this->assertEquals($result['square'] ?? NULL, $result['total'] ?? -1, 'le contour refermé n\'a qu\'un anneau : le trou qu\'il entoure en fait partie');
		$this->assertTrue(($result['added'] ?? 0) > 1.9 * ($result['parcel'] ?? 0), 'la surface ajoutée annoncée compte le trou comblé avec la parcelle');

	}

	public function testATooDetailedContourIsSimplifiedThenRefused(): void {

		$result = $this->run(1512, self::PAC.'
			// Un cercle de 200 m en 2 000 sommets, et une étoile de 500 m dont les 1 200 branches de 10 m ne se simplifient pas sous 2 m
			const ring = (count, radius) => Array.from({ length: count }, (_, index) => turf.destination([3.1, 45.6], radius(index) / 1000, index * 360 / count - 180).geometry.coordinates);

			const circle = MapDrawingPac.getPoints(ring(2000, () => 200), 0);
			result.circle = circle.points.length;

			result.star = MapDrawingPac.getPoints(ring(1200, index => (index % 2 === 0) ? 500 : 510), 0).refusal ?? null;
		');

		$this->assertTrue(($result['circle'] ?? 0) > 3 and ($result['circle'] ?? 1000) <= 500, 'un contour trop détaillé se simplifie sous le plafond de sommets');
		$this->assertEquals('detailed', $result['star'] ?? NULL, 'un contour qui reste trop détaillé une fois simplifié est refusé');

	}

	public function testAClickWhileMovingOrTurningTakesNothing(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();
			const at = point => ({ originalEvent: { detail: 1 }, point: map.project(point), lngLat: { toArray: () => point } });

			await click(drawing, inside(A));
			const taken = area(drawing);
			const points = JSON.stringify(drawing.shape.points);

			result.kept = [];

			for(const mode of ["move", "rotate"]) {
				drawing.setMode(mode);
				drawing.clickMap(at(inside(A)));
				drawing.clickMap(at(inside(B)));
				await new Promise(resolve => setTimeout(resolve, 0));
				result.kept.push(JSON.stringify(drawing.shape.points) === points);
			}

			drawing.setMode("edit");
			drawing.clickMap(at(inside(B)));
			await new Promise(resolve => setTimeout(resolve, 0));
			result.edit = area(drawing) > taken;

			drawing.cancel();
		');

		$this->assertEquals([TRUE, TRUE], $result['kept'] ?? NULL, 'en Déplacer comme en Rotation, un clic ne retire ni n\'ajoute de parcelle');
		$this->assertTrue($result['edit'] ?? FALSE, 'revenu en retouche, le clic ajoute de nouveau');

	}

	public function testAHandDrawnContourDoesNotBecomeDeclared(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			// Un contour tracé à la main à l\'ouest de A, qui en mord le cinquième, puis A ajoutée par un clic
			drawing.shape.take({ points: box(3.0982, 45.599) });
			await click(drawing, inside(A));

			result.added = Math.abs(area(drawing) - parcel([[3.0982, 45.599], [3.1, 45.599], [3.1, 45.6], [3.0982, 45.6]])) <= 2;
			result.year = String(drawing.pacYear);
			drawing.cancel();
		');

		$this->assertTrue($result['added'] ?? FALSE, 'la parcelle s\'ajoute au contour tracé à la main');
		$this->assertEquals('null', $result['year'] ?? NULL, 'le contour garde une part tracée à la main : il ne se dit pas déclaré à la PAC');

	}

	public function testTheYearFollowsTheContourOnlyWhileItComesFromTheDeclaration(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			await click(drawing, inside(A));
			use(drawing, "free");
			result.free = { year: drawing.pacYear, area: area(drawing) };

			use(drawing, "pac");
			use(drawing, "rectangle");
			result.rectangle = String(drawing.pacYear);

			use(drawing, "pac");
			await click(drawing, inside(B));
			result.added = String(drawing.pacYear);

			use(drawing, "remove");
			result.remove = String(drawing.pacYear);

			use(drawing, "pac");
			await click(drawing, inside(A));
			result.again = drawing.pacYear;

			result.parcel = parcel(A);
			drawing.cancel();
		');

		$this->assertEquals(['year' => 2025, 'area' => $result['parcel'] ?? -1], $result['free'] ?? NULL, 'retouché en « Forme libre », le contour garde sa forme et son année');
		$this->assertEquals('null', $result['rectangle'] ?? NULL, 'passé en rectangle, il ne vient plus de la déclaration');
		$this->assertEquals('null', $result['added'] ?? NULL, 'une parcelle ajoutée au rectangle ne rend pas l\'année au contour');
		$this->assertEquals('null', $result['remove'] ?? NULL, '« Recommencer » efface l\'année');
		$this->assertEquals(2025, $result['again'] ?? NULL, 'une parcelle reprise sur un contour vide rend l\'année');

	}

	public function testARetracedZoneTakesParcelsAroundItsContour(): void {

		$result = $this->run(1512, self::PAC.'
			// La parcelle retracée mord le cinquième de A, à l\'ouest
			const contour = box(3.0982, 45.599);
			const drawing = open({ drawingSheet: "zone", drawingTarget: "zone", drawingId: "5", drawingUrl: "/map/zone:doUpdateShape", drawingCoordinates: JSON.stringify(contour) });
			const joined = () => Math.abs(area(drawing) - parcel([[3.0982, 45.599], [3.1, 45.599], [3.1, 45.6], [3.0982, 45.6]])) <= 2;

			result.kept = { shape: drawing.shape.constructor.name, tools: drawing.shape.getTools(), same: Math.abs(area(drawing) - parcel(contour)) <= 2 };

			await click(drawing, inside(A));
			result.added = { joined: joined(), year: String(drawing.pacYear) };

			use(drawing, "free");
			result.free = { shape: drawing.shape.constructor.name, joined: joined() };

			use(drawing, "pac");
			use(drawing, "remove");
			await click(drawing, inside(A));
			result.declared = { area: area(drawing), year: drawing.pacYear };

			result.parcel = parcel(A);
			drawing.cancel();
		');

		$this->assertEquals(['shape' => 'MapDrawingPac', 'tools' => ['free', 'rectangle', 'pac', 'rotate', 'remove'], 'same' => TRUE], $result['kept'] ?? NULL, 'au retracé, l\'outil « Parcelle PAC » reprend le contour de la parcelle tel quel, parmi les trois formes de la création');
		$this->assertEquals(['joined' => TRUE, 'year' => 'null'], $result['added'] ?? NULL, 'une parcelle déclarée s\'ajoute au contour existant, qui ne se dit pas déclaré pour autant');
		$this->assertEquals(['shape' => 'MapDrawingPolygon', 'joined' => TRUE], $result['free'] ?? NULL, 'revenu en « Forme libre », le contour garde la parcelle ajoutée');
		$this->assertEquals(['area' => $result['parcel'] ?? -1, 'year' => 2025], $result['declared'] ?? NULL, 'recommencé puis repris d\'une parcelle, le contour vient de la déclaration et en prend l\'année');

	}

	// Le cas précédent prend `drawingPac` du lien qu'il se donne : c'est PlanUi qui le pose au retracé d'une parcelle, et les gabarits
	// de l'outil sur sa fiche, que MapDrawing.loadPac() lit (`pacStructure`). Un jardin ne se reprend pas de la PAC
	public function testTheRetracedZoneLinkAndCardCarryThePacTool(): void {

		$ui = new class extends PlanUi {

			public function getZoneLink(Zone $eZone): string {
				return $this->getMapZoneDrawingAttributes($eZone);
			}

			public function getKit(\structure\Structure $eStructure): string {
				return $this->getMapDrawing($eStructure);
			}

		};

		$link = $ui->getZoneLink(new Zone(['id' => 5, 'name' => 'Parcelle', 'area' => 1200, 'coordinates' => [[3.1, 45.6], [3.101, 45.6], [3.101, 45.601]]]));
		$kit = $ui->getKit(new \structure\Structure(['id' => 6914]));

		$this->assertTrue(str_contains($link, 'data-drawing-url="/map/zone:doUpdateShape"') and preg_match('/\sdata-drawing-pac(\s|$)/', $link) === 1, 'le lien du retracé d\'une parcelle propose l\'outil « Parcelle PAC »');
		$this->assertEquals(1, preg_match('/data-drawing-card="zone"[^>]* data-pac-structure="6914"/', $kit), 'la fiche du retracé porte la structure que la lecture du registre envoie');
		$this->assertTrue(str_contains($kit, 'data-drawing-card="plot"') and preg_match('/data-drawing-card="plot"[^>]* data-pac-structure=/', $kit) === 0, 'la fiche du retracé d\'un jardin n\'a pas l\'outil');

	}

	public function testTheHoverSaysTheDeclaredCrop(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();
			await new Promise(resolve => setTimeout(resolve, 0));

			// Avant tout contour, A n\'a pas de libellé : la cote ne dit que sa surface
			drawing.hoverPac(inside(A));
			result.unnamed = { said: said.length, detail: details.at(-1) };

			await click(drawing, inside(A));

			// B touche le contour : la cote dit la surface ajoutée, et sa culture dessous
			drawing.hoverPac(inside(B));
			result.named = { added: said.at(-1).startsWith("+"), detail: details.at(-1) };

			// C ne le touche pas : le refus reste seul
			drawing.hoverPac(inside(C));
			result.refused = { said: said.at(-1), detail: details.at(-1) };

			drawing.cancel();
		');

		$this->assertEquals(['said' => 1, 'detail' => NULL], $result['unnamed'] ?? NULL, 'une parcelle sans libellé connu n\'a pas de seconde ligne');
		$this->assertEquals(['added' => TRUE, 'detail' => 'Prairie permanente'], $result['named'] ?? NULL, 'la parcelle survolée dit sa culture sous la surface qu\'elle ajoute');
		$this->assertEquals(['said' => 'apart', 'detail' => NULL], $result['refused'] ?? NULL, 'un refus reste seul, sans la culture de la parcelle refusée');

	}

	public function testAResumedSessionKeepsItsPacOrigin(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			const tool = { dataset: { tool: "pac" }, classList: { contains: () => false } };
			const bar = drawing.bar;
			drawing.bar = new Proxy({}, { get: (target, key) => (key === "qs") ? () => tool : bar[key] });

			await click(drawing, inside(A));
			use(drawing, "free");

			const read = { box: [-180, -90, 180, 90], parcels: [A], crops: new Map([[A, null]]), year: 2024 };
			const sent = posts.length;
			const moved = moves.length;
			MapDrawingEmbedded.prototype.resumePac.call(drawing, 2024, read);

			result.resumed = { shape: drawing.shape.constructor.name, year: drawing.pacYear, parcels: drawing.pac.parcels.length, sent: posts.length - sent, framed: moves.slice(moved).includes("fitBounds") };
			drawing.cancel();
		');

		$this->assertEquals(['shape' => 'MapDrawingPac', 'year' => 2024, 'parcels' => 1, 'sent' => 0, 'framed' => TRUE], $result['resumed'] ?? NULL, 'la séance rouverte sur un contour déclaré reprend l\'outil « Parcelle PAC », l\'année et les parcelles déjà lues sans relire le registre, et se cadre sur le contour');

	}

	// Le cadrage vaut pour tout contour fermé, tracé à la main comme pris à la PAC, et ne bouge pas la carte sans contour
	public function testAnyClosedContourIsFramed(): void {

		$result = $this->run(1512, self::PAC.'
			const drawing = open();

			drawing.shape = new MapDrawingPolygon(A);
			let moved = moves.length;
			drawing.frameShape();
			result.drawn = moves.slice(moved);

			drawing.shape = new MapDrawingPolygon(null);
			moved = moves.length;
			drawing.frameShape();
			result.empty = moves.slice(moved);

			drawing.cancel();
		');

		$this->assertEquals(['fitBounds'], $result['drawn'] ?? NULL, 'la carte se cadre sur le contour tracé à la main');
		$this->assertEquals([], $result['empty'] ?? NULL, 'sans contour, la carte reste sur le lieu');

	}

}
?>
