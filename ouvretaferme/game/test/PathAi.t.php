<?php
namespace game;

/**
 * Le chemin du joueur dans la cour (`GameBoardPath`, static/js/path.js) : il contourne les cases et le décor sans
 * jamais les traverser, et une arrivée visée dans un obstacle, hors d'atteinte ou hors de la cour revient au plus près
 * où l'on peut aller. Les obstacles sont ceux de la cour : un plateau de seize cases de 2 m au pas de 2,35 m, comme
 * `GameBoard.TILE` et `GameBoard.STEP`.
 */
class PathAiTest extends \Test {

	public function testPathGoesAroundTheTiles(): void {

		$result = $this->runPath('
			const path = new GameBoardPath(bounds, .2, tiles);
			const points = path.find({ x: -7, z: 0 }, { x: 7, z: .5 });
			emit({ count: points.length, crossed: crosses({ x: -7, z: 0 }, points, tiles), length: length({ x: -7, z: 0 }, points), last: points[points.length - 1] });
		');

		$this->assertTrue($result['count'] > 1, 'le chemin tourne au moins une fois');
		$this->assertFalse($result['crossed'], 'aucun point du chemin ne tombe dans une case');
		$this->assertTrue($result['length'] > 14, 'le chemin est plus long que la ligne droite, qui traverserait le plateau');
		$this->assertEquals(['x' => 7, 'z' => .5], $result['last'], 'une arrivée libre est atteinte telle quelle');

	}

	public function testPassePiedIsClosed(): void {

		// Les cases élargies de `GameBoard.REACH`, comme dans la cour : le passe-pied de 0,35 m se ferme, et un toucher
		// au milieu du plateau ramène le joueur à son bord
		$result = $this->runPath('
			const reached = tiles.map(t => ({ minX: t.minX - REACH, maxX: t.maxX + REACH, minZ: t.minZ - REACH, maxZ: t.maxZ + REACH }));
			const path = new GameBoardPath(bounds, .2, reached);
			const points = path.find({ x: 0, z: -7 }, { x: 0, z: 0 });
			const last = points[points.length - 1];
			emit({ count: points.length, crossed: crosses({ x: 0, z: -7 }, points, tiles), edge: Math.max(Math.abs(last.x), Math.abs(last.z)) });
		');

		$this->assertTrue($result['count'] > 0, 'un toucher au milieu du plateau mène quelque part');
		$this->assertFalse($result['crossed'], 'le joueur ne met pas le pied sur une case');
		$this->assertTrue($result['edge'] > 4.5, 'il s\'arrête au bord du plateau, hors du passe-pied');

	}

	public function testBlockedGoalMovesToTheEdge(): void {

		// Le centre d'une case est pris : l'arrivée revient à la cellule libre la plus proche
		$result = $this->runPath('
			const path = new GameBoardPath(bounds, .2, tiles);
			const points = path.find({ x: -7, z: -7 }, { x: -3.525, z: -3.525 });
			const last = points[points.length - 1];
			emit({ count: points.length, inside: tiles.some(tile => GameBoardPath.inside(last, tile)), distance: Math.hypot(last.x + 3.525, last.z + 3.525) });
		');

		$this->assertTrue($result['count'] > 0, 'une arrivée prise dans une case mène quand même quelque part');
		$this->assertFalse($result['inside'], 'le joueur s\'arrête hors de la case');
		$this->assertTrue($result['distance'] < 1.5, 'il s\'arrête au bord de la case visée, pas ailleurs');

	}

	public function testClosedBoardIsBypassed(): void {

		// Un plateau fermé, d'un seul tenant jusqu'à sa ganivelle, et un puits au milieu de l'allée
		$result = $this->runPath('
			const obstacles = [{ minX: -5.3, maxX: 5.3, minZ: -5.3, maxZ: 5.3 }, { minX: 7, maxX: 8.5, minZ: -1, maxZ: 1 }];
			const path = new GameBoardPath(bounds, .2, obstacles);
			const points = path.find({ x: -8, z: 0 }, { x: 11, z: 0 });
			emit({ count: points.length, crossed: crosses({ x: -8, z: 0 }, points, obstacles) });
		');

		$this->assertTrue($result['count'] > 1, 'le plateau fermé se contourne');
		$this->assertFalse($result['crossed'], 'ni le plateau fermé ni le puits ne sont traversés');

	}

	public function testEnclosedGoalStopsOutside(): void {

		// Une arrivée enclose de tous côtés : le joueur va au plus près, du bon côté du mur
		$result = $this->runPath('
			const walls = [{ minX: 2, maxX: 2.6, minZ: -3, maxZ: 3 }, { minX: 5.4, maxX: 6, minZ: -3, maxZ: 3 }, { minX: 2, maxX: 6, minZ: -3, maxZ: -2.4 }, { minX: 2, maxX: 6, minZ: 2.4, maxZ: 3 }];
			const path = new GameBoardPath(bounds, .2, walls);
			const points = path.find({ x: -6, z: 0 }, { x: 4, z: 0 });
			const last = points[points.length - 1];
			emit({ count: points.length, crossed: crosses({ x: -6, z: 0 }, points, walls), last });
		');

		$this->assertTrue($result['count'] > 0, 'le joueur avance vers l\'enclos');
		$this->assertFalse($result['crossed'], 'il ne traverse pas le mur');
		$this->assertTrue($result['last']['x'] < 2 and abs($result['last']['z']) < 1, 'il s\'arrête contre le mur, face au point visé');

	}

	public function testGoalOutsideTheYardStopsAtItsEdge(): void {

		// Un toucher sur l'herbe au-delà de la cour : le joueur s'arrête à son bord, jamais au point visé
		$result = $this->runPath('
			const path = new GameBoardPath(bounds, .2, tiles);
			const points = path.find({ x: -7, z: 0 }, { x: 80, z: -80 });
			emit({ count: points.length, last: points[points.length - 1] });
		');

		$this->assertTrue($result['count'] > 0, 'le joueur avance vers le point visé');
		$this->assertTrue(abs($result['last']['x']) <= 12 and abs($result['last']['z']) <= 12, 'il reste dans la cour');
		$this->assertTrue($result['last']['x'] > 11.5 and $result['last']['z'] < -11.5, 'il s\'arrête au coin le plus proche du point visé');

	}

	/**
	 * Charge `path.js` depuis le disque sous node et joue le scénario, avec le `REACH` de `board.js`. `tiles` est un plateau ouvert centré sur
	 * l'origine ; `crosses()` dit si un point pris tous les 5 cm le long du chemin tombe dans un obstacle, rétréci de la
	 * demi-diagonale d'une cellule : un chemin qui suit les centres de cellules libres peut frôler un bord de cette
	 * distance, pas davantage. Le script part par l'entrée standard (`ShellCommandSentinel`).
	 */
	private function runPath(string $scenario): array {

		preg_match('/static REACH = ([0-9.]+);/', file_get_contents(__DIR__.'/../static/js/board.js'), $match);

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/path.js').', "utf8");

			const context = vm.createContext({});
			vm.runInContext(source + ";globalThis.GameBoardPath = GameBoardPath;", context);

			const GameBoardPath = context.GameBoardPath;
			const REACH = '.$match[1].';
			const bounds = { minX: -12, maxX: 12, minZ: -12, maxZ: 12 };

			const tiles = [];

			for(let row = 0; row < 4; row++) {
				for(let column = 0; column < 4; column++) {
					const x = (column - 1.5) * 2.35, z = (row - 1.5) * 2.35;
					tiles.push({ minX: x - 1, maxX: x + 1, minZ: z - 1, maxZ: z + 1 });
				}
			}

			const crosses = (from, points, obstacles) => {

				const shrink = .2 * Math.SQRT1_2;
				let at = from;

				for(const point of points) {

					const steps = Math.ceil(Math.hypot(point.x - at.x, point.z - at.z) / .05);

					for(let step = 0; step <= steps; step++) {

						const x = at.x + (point.x - at.x) * step / steps, z = at.z + (point.z - at.z) * step / steps;

						if(obstacles.some(o => x > o.minX + shrink && x < o.maxX - shrink && z > o.minZ + shrink && z < o.maxZ - shrink)) {
							return true;
						}

					}

					at = point;

				}

				return false;

			};

			const length = (from, points) => points.reduce((total, point, index) => total + Math.hypot(point.x - (index === 0 ? from : points[index - 1]).x, point.z - (index === 0 ? from : points[index - 1]).z), 0);

			const emit = value => process.stdout.write(JSON.stringify(value));

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
