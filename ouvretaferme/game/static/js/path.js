/**
 * Le chemin du joueur dans la cour : une grille posée sur le sol, où une cellule est prise quand son centre tombe dans un
 * obstacle, un A* d'une cellule libre à l'autre, puis un lissage qui garde les seuls tournants. Les obstacles sont des
 * rectangles du sol, d'équerre avec les axes de la cour, `{ minX, maxX, minZ, maxZ }`, en mètres.
 *
 * Sans three ni page : `board.js` lui passe les emprises, et `test/PathAi.t.php` l'éprouve sous node.
 */
class GameBoardPath {

	// Le poids, dans le choix d'une arrivée prise, de la distance à celui qui marche : assez faible pour que la plus
	// proche du point visé l'emporte, assez pour départager deux bords à égale distance
	static TOWARD = .1;

	constructor(bounds, cell, rectangles) {

		this.bounds = bounds;
		this.cell = cell;
		this.columns = Math.ceil((bounds.maxX - bounds.minX) / cell);
		this.rows = Math.ceil((bounds.maxZ - bounds.minZ) / cell);
		this.rectangles = rectangles;
		this.blocked = new Uint8Array(this.columns * this.rows);

		for(const rectangle of rectangles) {

			const fromColumn = Math.max(0, Math.floor((rectangle.minX - bounds.minX) / cell));
			const toColumn = Math.min(this.columns - 1, Math.floor((rectangle.maxX - bounds.minX) / cell));
			const fromRow = Math.max(0, Math.floor((rectangle.minZ - bounds.minZ) / cell));
			const toRow = Math.min(this.rows - 1, Math.floor((rectangle.maxZ - bounds.minZ) / cell));

			for(let row = fromRow; row <= toRow; row++) {
				for(let column = fromColumn; column <= toColumn; column++) {

					const center = this.getCenter(row * this.columns + column);

					if(GameBoardPath.inside(center, rectangle)) {
						this.blocked[row * this.columns + column] = 1;
					}

				}
			}

		}

		this.zones = this.getZones();

	}

	/**
	 * Le numéro de la zone d'un seul tenant où tombe chaque cellule libre, -1 pour une cellule prise : une arrivée se
	 * cherche dans la zone de celui qui marche, et la recherche ne parcourt jamais toute la grille pour conclure qu'une
	 * arrivée enclose est hors d'atteinte
	 */
	getZones() {

		const zones = new Int32Array(this.blocked.length).fill(-1);
		let zone = 0;

		for(let cell = 0; cell < this.blocked.length; cell++) {

			if(this.blocked[cell] === 1 || zones[cell] !== -1) {
				continue;
			}

			const queue = [cell];
			zones[cell] = zone;

			for(let head = 0; head < queue.length; head++) {
				for(const next of this.getNeighbors(queue[head], true)) {
					if(zones[next] === -1) {
						zones[next] = zone;
						queue.push(next);
					}
				}
			}

			zone++;

		}

		return zones;

	}

	static inside(point, rectangle) {
		return point.x > rectangle.minX && point.x < rectangle.maxX && point.z > rectangle.minZ && point.z < rectangle.maxZ;
	}

	getCell(point) {

		const column = Math.min(this.columns - 1, Math.max(0, Math.floor((point.x - this.bounds.minX) / this.cell)));
		const row = Math.min(this.rows - 1, Math.max(0, Math.floor((point.z - this.bounds.minZ) / this.cell)));

		return row * this.columns + column;

	}

	getCenter(cell) {
		return {
			x: this.bounds.minX + (cell % this.columns + .5) * this.cell,
			z: this.bounds.minZ + (Math.floor(cell / this.columns) + .5) * this.cell
		};
	}

	isFree(point) {
		return this.blocked[this.getCell(point)] === 0 && this.rectangles.every(rectangle => GameBoardPath.inside(point, rectangle) === false);
	}

	/**
	 * La cellule libre la plus proche de `point`, dans la zone `zone` quand elle est donnée, de qui vient de `from` : un
	 * point visé dans un obstacle va au bord de celui-ci. Le parcours en largeur trouve l'anneau de la première cellule
	 * retenue, compté en pas de grille, puis pousse assez loin pour que la plus proche à vol d'oiseau en soit (une
	 * diagonale vaut √2 pas). Entre deux bords à même distance, celui du côté de `from` évite un tour d'obstacle pour rien.
	 */
	getNearest(point, from, zone) {

		const accepts = cell => this.blocked[cell] === 0 && (zone === null || this.zones[cell] === zone);
		const cell = this.getCell(point);

		if(accepts(cell)) {
			return cell;
		}

		const ring = new Int32Array(this.blocked.length).fill(-1);
		const queue = [cell];
		ring[cell] = 0;

		let found = [], last = Infinity;

		for(let head = 0; head < queue.length; head++) {

			const current = queue[head];

			if(ring[current] > last) {
				break;
			}

			for(const next of this.getNeighbors(current, false)) {

				if(ring[next] !== -1) {
					continue;
				}

				ring[next] = ring[current] + 1;

				if(accepts(next)) {

					if(found.length === 0) {
						last = Math.ceil(ring[next] * Math.SQRT2) + 1;
					}

					found.push(next);

				} else {
					queue.push(next);
				}

			}

		}

		if(found.length === 0) {
			return null;
		}

		const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z);
		const score = candidate => {
			const center = this.getCenter(candidate);
			return distance(center, point) + GameBoardPath.TOWARD * distance(center, from);
		};

		return found.reduce((best, candidate) => (score(candidate) < score(best)) ? candidate : best);

	}

	// Les voisines d'une cellule, diagonales comprises ; une diagonale ne coupe pas le coin d'un obstacle
	getNeighbors(cell, free) {

		const column = cell % this.columns, row = Math.floor(cell / this.columns);
		const neighbors = [];

		for(let dz = -1; dz <= 1; dz++) {
			for(let dx = -1; dx <= 1; dx++) {

				if(dx === 0 && dz === 0) {
					continue;
				}

				const x = column + dx, z = row + dz;

				if(x < 0 || z < 0 || x >= this.columns || z >= this.rows) {
					continue;
				}

				const next = z * this.columns + x;

				if(free) {

					if(this.blocked[next] === 1) {
						continue;
					}

					if(dx !== 0 && dz !== 0 && (this.blocked[row * this.columns + x] === 1 || this.blocked[z * this.columns + column] === 1)) {
						continue;
					}

				}

				neighbors.push(next);

			}
		}

		return neighbors;

	}

	/**
	 * Les points où passer pour aller de `from` à `to`, le dernier étant l'arrivée, ou une liste vide si rien ne mène à
	 * l'arrivée. Une arrivée hors de la grille est ramenée à son bord, une arrivée prise dans un obstacle ou hors
	 * d'atteinte devient le centre de la cellule la plus proche où l'on peut aller.
	 */
	find(from, to) {

		const inner = this.cell / 2;
		const target = {
			x: Math.min(this.bounds.maxX - inner, Math.max(this.bounds.minX + inner, to.x)),
			z: Math.min(this.bounds.maxZ - inner, Math.max(this.bounds.minZ + inner, to.z))
		};

		const start = this.getNearest(from, from, null);

		if(start === null) {
			return [];
		}

		const goal = this.getNearest(target, from, this.zones[start]);
		const arrival = (goal === this.getCell(target) && this.isFree(target)) ? target : this.getCenter(goal);

		if(start === goal) {
			return [arrival];
		}

		const cells = this.search(start, goal);
		const points = [from, ...cells.slice(1, -1).map(cell => this.getCenter(cell)), arrival];

		return this.smooth(points).slice(1);

	}

	// Un A* d'une cellule à une autre de la même zone, qui aboutit donc toujours. L'heuristique octile se calcule en
	// pas de grille, sans objet, et préfère de très peu la cellule la plus avancée à égalité de coût : sans ce
	// départage, toutes les cellules d'un même coût total s'ouvrent avant d'avancer
	search(start, goal) {

		const columns = this.columns;
		const goalColumn = goal % columns, goalRow = Math.floor(goal / columns);
		const cost = new Float64Array(this.blocked.length).fill(Infinity);
		const previous = new Int32Array(this.blocked.length).fill(-1);

		const heuristic = cell => {
			const dx = Math.abs(cell % columns - goalColumn), dz = Math.abs(Math.floor(cell / columns) - goalRow);
			return (Math.max(dx, dz) + (Math.SQRT2 - 1) * Math.min(dx, dz)) * 1.001;
		};

		const heap = new GameBoardPathHeap();

		cost[start] = 0;
		heap.push(heuristic(start), start);

		while(heap.size() > 0) {

			const [estimate, cell] = heap.pop();

			if(cell === goal) {
				break;
			}

			// Une entrée périmée du tas : la cellule a été atteinte depuis par un chemin plus court
			if(estimate > cost[cell] + heuristic(cell) + 1e-9) {
				continue;
			}

			for(const next of this.getNeighbors(cell, true)) {

				const step = (next % columns !== cell % columns && Math.floor(next / columns) !== Math.floor(cell / columns)) ? Math.SQRT2 : 1;
				const candidate = cost[cell] + step;

				if(candidate < cost[next]) {
					cost[next] = candidate;
					previous[next] = cell;
					heap.push(candidate + heuristic(next), next);
				}

			}

		}

		const cells = [goal];

		for(let at = goal; previous[at] !== -1; at = previous[at]) {
			cells.push(previous[at]);
		}

		return cells.reverse();

	}

	// D'un point, on va droit au plus lointain qu'on voit sans traverser de cellule prise : on avance tant que le
	// suivant se voit depuis le dernier tournant gardé, et le point d'avant devient le tournant suivant
	smooth(points) {

		const kept = [points[0]];

		for(let at = 1; at < points.length - 1; at++) {
			if(this.sees(kept[kept.length - 1], points[at + 1]) === false) {
				kept.push(points[at]);
			}
		}

		kept.push(points[points.length - 1]);

		return kept;

	}

	sees(from, to) {

		const distance = Math.hypot(to.x - from.x, to.z - from.z);
		const samples = Math.ceil(distance / (this.cell / 2));

		for(let sample = 1; sample < samples; sample++) {

			const ratio = sample / samples;

			if(this.blocked[this.getCell({ x: from.x + (to.x - from.x) * ratio, z: from.z + (to.z - from.z) * ratio })] === 1) {
				return false;
			}

		}

		return true;

	}

}

// Un tas binaire de paires [priorité, cellule], la plus petite priorité en tête
class GameBoardPathHeap {

	constructor() {
		this.items = [];
	}

	size() {
		return this.items.length;
	}

	push(priority, cell) {

		const items = this.items;
		items.push([priority, cell]);

		for(let i = items.length - 1; i > 0;) {

			const parent = (i - 1) >> 1;

			if(items[parent][0] <= items[i][0]) {
				break;
			}

			[items[parent], items[i]] = [items[i], items[parent]];
			i = parent;

		}

	}

	pop() {

		const items = this.items;
		const top = items[0];
		const last = items.pop();

		if(items.length > 0) {

			items[0] = last;

			for(let i = 0; ;) {

				const left = 2 * i + 1, right = left + 1;
				let smallest = i;

				if(left < items.length && items[left][0] < items[smallest][0]) {
					smallest = left;
				}

				if(right < items.length && items[right][0] < items[smallest][0]) {
					smallest = right;
				}

				if(smallest === i) {
					break;
				}

				[items[smallest], items[i]] = [items[i], items[smallest]];
				i = smallest;

			}

		}

		return top;

	}

}
