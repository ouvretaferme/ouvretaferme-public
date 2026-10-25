<?php
namespace game;

/**
 * La cour tirée de la graine du joueur (`GameBoardCourt`, static/js/court.js) : ses quatre plateaux de seize cases,
 * son décor sous le budget de triangles, aucun arbre dont la couronne couvre l'eau dans l'image, et une marche qui
 * atteint la maison, le cellier et l'entrée de chaque plateau sans laisser de poche d'herbe enfermée, plateaux
 * ouverts comme fermés. Les graines sont celles de la cour de l'accueil, de trois des plus lentes et des trois plus
 * lourdes du balayage des 553 graines de la planche, mesurées sur ses modèles, et de 577, dont la mare d'ilot est
 * bordée d'arbres ; les modèles du test sont ceux du produit.
 */
class CourtAiTest extends \Test {

	const SEEDS = [129, 18241, 25, 22081, 2625, 2817, 3009, 577];

	public function testEveryCourtIsWalkable(): void {

		$result = $this->runCourt('
			const out = {};

			// Ouverts, tous fermés, puis les moments de la partie : plateaux 2 à 4 fermés, 3 et 4, 4 seul
			const states = [{}, { 1: true, 2: true, 3: true, 4: true }, { 2: true, 3: true, 4: true }, { 3: true, 4: true }, { 4: true }];

			// Une couronne se juge au pied de l\'arbre, puis projetée au sol par la vue depuis sa mi-hauteur et son sommet
			const [fx, fy, fz] = GameBoardCourt.FORWARD;
			const isWet = (court, [slug, x, z, scale, angle]) => {
				const box = GameBoardCourt.transform(catalogue(slug).box, x, 0, z, scale, angle);
				const crown = Math.min(box.max.x - box.min.x, box.max.z - box.min.z) / 2;
				return [0, .5, 1].some(height => court.rects.some(rectangle => GameBoardCourt.getDistanceToRect(rectangle, x + fx * box.max.y * height / -fy, z + fz * box.max.y * height / -fy) < crown));
			};

			seeds.forEach(seed => {
				const court = new GameBoardCourt(seed, catalogue);
				out[seed] = {
					tiles: court.parcels.map(parcel => parcel.tiles.length),
					triangles: court.triangles,
					wet: court.decor.filter(piece => GameBoardCourt.TREES.has(piece[0]) && isWet(court, piece)).length,
					blocked: states.map(closed => court.analyse(closed)).map(({ pockets, unreached }) => pockets.length + unreached.length)
				};
			});

			emit(out);
		');

		foreach(self::SEEDS as $seed) {

			$court = $result[$seed];

			$this->assertEquals([16, 16, 16, 16], $court['tiles'], 'graine '.$seed.' : quatre plateaux de seize cases');
			$this->assertTrue($court['triangles'] <= $result['budget'], 'graine '.$seed.' : le décor tient dans le budget de triangles');
			$this->assertEquals(0, $court['wet'], 'graine '.$seed.' : aucune couronne d\'arbre sur l\'eau');
			$this->assertEquals([0, 0, 0, 0, 0], $court['blocked'], 'graine '.$seed.' : aucune poche, et chaque cible atteinte, quels que soient les plateaux fermés');

		}

	}

	public function testStreamIsCrossedOnlyByTheBridge(): void {

		// Le tablier barré, la marche se coupe en deux rives : d'une rive à l'autre, le joueur ne passe que par le pont
		$result = $this->runCourt('
			const out = {};

			seeds.forEach(seed => {

				const court = new GameBoardCourt(seed, catalogue);
				const { x, z, ux, uz } = court.bridge;
				const box = catalogue("pont").box;
				const length = (box.max.x - box.min.x) / 2 * GameBoardCourt.BRIDGE_SCALE, width = (box.max.z - box.min.z) / 2 * GameBoardCourt.BRIDGE_SCALE;

				const deck = [];

				for(let across = -length; across <= length; across += .1) {
					for(let along = -width; along <= width; along += .1) {
						deck.push(GameBoardCourt.around(x + ux * across - uz * along, z + uz * across + ux * along, .08));
					}
				}

				const open = court.getPath({});
				const barred = new context.GameBoardPath(open.bounds, GameBoardCourt.CELL, [...court.getObstacles({}), ...deck]);

				const banks = grid => {
					const sizes = {};
					grid.zones.forEach(zone => {
						if(zone >= 0) {
							sizes[zone] = (sizes[zone] ?? 0) + 1;
						}
					});
					return Object.values(sizes).filter(size => size * GameBoardCourt.CELL ** 2 > 20).length;
				};

				out[seed] = { open: banks(open), barred: banks(barred) };

			});

			emit(out);
		');

		foreach(self::SEEDS as $seed) {
			$this->assertEquals(['open' => 1, 'barred' => 2], array_intersect_key($result[$seed], ['open' => 1, 'barred' => 1]), 'graine '.$seed.' : une seule cour à pied, et deux rives quand le pont est barré');
		}

	}

	public function testSeedDrawsTheSameCourt(): void {

		$result = $this->runCourt('
			const draw = seed => JSON.stringify([new GameBoardCourt(seed, catalogue)].map(court => [court.decor, court.parcels.map(parcel => parcel.tiles), court.stones])[0]);
			emit({ same: draw(129) === draw(129), other: draw(129) !== draw(193) });
		');

		$this->assertTrue($result['same'], 'une graine rend toujours la même cour, d\'une ouverture à l\'autre');
		$this->assertTrue($result['other'], 'deux graines rendent deux cours');

	}

	public function testCourtSurvivesTheWorkerCopy(): void {

		// Le Worker est rejoué dans un contexte neuf, qui ne connait que son texte et les modèles qu'il reçoit
		$result = $this->runCourt('
			const worker = vm.createContext({});
			let posted = null;
			worker.postMessage = data => posted = structuredClone(data);
			vm.runInContext(GameBoardCourt.getWorkerSource(), worker);
			worker.onmessage({ data: { seed: 18241, models: GameBoardCourt.getModels(index) } });

			const court = new GameBoardCourt(18241, catalogue);
			const copy = GameBoardCourt.revive(posted, catalogue);
			const walk = court => JSON.stringify([court.decor, court.getRadiusAt(1), court.analyse({ 2: true, 3: true, 4: true }).main, court.getPath({}).zones.length]);
			emit({ same: walk(court) === walk(copy) });
		');

		$this->assertTrue($result['same'], 'la cour tirée par le Worker, copiée sans ses fonctions, porte le même décor, la même ile et la même marche');

	}

	/**
	 * Charge `path.js` et `court.js` depuis le disque sous node, avec le catalogue de modèles du produit, et joue le
	 * scénario. `budget` est ajouté au résultat. Le script part par l'entrée standard (`ShellCommandSentinel`).
	 */
	private function runCourt(string $scenario): array {

		$script = '
			const vm = require("vm");
			const fs = require("fs");

			const context = vm.createContext({});
			vm.runInContext(fs.readFileSync('.json_encode(__DIR__.'/../static/js/path.js').', "utf8") + ";globalThis.GameBoardPath = GameBoardPath;globalThis.GameBoardPathHeap = GameBoardPathHeap;", context);
			vm.runInContext(fs.readFileSync('.json_encode(__DIR__.'/../static/js/court.js').', "utf8") + ";globalThis.GameBoardCourt = GameBoardCourt;", context);

			const GameBoardCourt = context.GameBoardCourt;
			const index = JSON.parse(fs.readFileSync('.json_encode(\Package::getPath('scene').'/static/model/models.json').', "utf8"));
			const catalogue = GameBoardCourt.getCatalogue(index);
			const seeds = '.json_encode(self::SEEDS).';

			const emit = value => process.stdout.write(JSON.stringify({ ...value, budget: GameBoardCourt.BUDGET }));

			'.$scenario.'
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

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
