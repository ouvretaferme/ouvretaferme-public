/**
 * Le décor par défaut d'une structure qui n'a encore rien posé, repris du prototype (`landDecor()`, `.claude/state/3d/poc/index.html`) :
 * des bosquets et des arbres en lisière et dans les vides d'herbe. Seuls des éléments ponctuels s'y tirent, jamais un élément
 * qui se trace sur un parcours (haie, clôture, chemin, cours d'eau), ni un bâtiment, que la structure pose elle-même.
 *
 * Tout se tire d'une graine qui est l'id de la structure, et le regard qui sert à ne cacher aucune planche est celui de la caméra
 * d'ouverture, dont l'azimut ne change pas : le même plan donne le même décor à chaque chargement, quel que soit le jour montré.
 *
 * Les éléments sont ceux du catalogue de pose (`MapSceneDecor.KITS`), montés par `MapSceneDecor` comme un décor posé, sans id.
 * Rien ne se pose sur une planche, une serre, une parcelle tracée, une pancarte, le sol de l'enseigne ni sur le trait entre deux
 * îlots, et rien ne les cache.
 */
class MapSceneDecorDefault {

	// Les arbres du décor par défaut sont grossis : à l'échelle du site, un arbre à sa taille de catalogue se lit moins que son ombre.
	// Un bosquet y garde un seul massif : grossi, le bosquet de trois massifs couvrirait 30 m d'herbe d'un seul tenant
	static GROVES = ['massif', 'verger', 'buissons', 'massif', 'alignement', 'massif', 'buissons', 'verger'];
	static GROVE_SCALE = [1.3, 1.9];
	static GROVE_EVERY = 150;
	static GROVE_COUNT = [4, 12];
	static GROVE_APART = 22;
	static TREES = ['arbre', 'fruitier', 'arbre'];
	static TREE_SCALE = [1.2, 1.9];
	static TREE_EVERY = 200;
	static TREE_COUNT = [3, 8];
	static TREE_APART = 12;

	// Les places des arbres : tous les EDGE_STEP mètres de la lisière, à EDGE_OUT mètres au-delà, et une par carré de VOID_STEP
	// mètres à l'intérieur de l'îlot
	static EDGE_STEP = 10;
	static EDGE_OUT = [8, 14];
	static VOID_STEP = 9;

	constructor({ decor, plan, geometry, boards, sign, eye }) {

		const G = ScenePlanGeometry;

		this.decor = decor;
		this.geometry = geometry;
		this.r = MapSceneCrops.random(plan.structure);
		this.serial = plan.structure * 7919;

		this.away = G.norm({ x: -eye.x, n: eye.z });
		this.slope = eye.y / Math.hypot(eye.x, eye.z);

		this.beds = geometry.beds.filter(bed => bed.greenhouse === false);
		this.glass = [...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ rectangle }) => rectangle);
		// Le sol de l'enseigne, de son pied à ce que ses lettres cachent derrière elles, compte comme une parcelle tracée
		const ground = (sign.length === 4) ? [[sign[0], sign[1], sign[3], sign[2]]] : [];

		this.plots = [...geometry.plots.values()].filter(({ drawn }) => drawn).map(({ points }) => points).concat(ground);
		this.zones = [...geometry.zones.values()];
		this.folds = geometry.land.folds().map(fold => [G.sub(fold.center, G.mul(fold.along, fold.length / 2)), G.add(fold.center, G.mul(fold.along, fold.length / 2))]);

		// Les pancartes sont posées avant le décor, face à la caméra d'ouverture
		const turn = Math.atan2(eye.x, eye.z);
		this.boards = boards.map(({ base }) => MapSceneBoards.rectAt(base, turn, MapSceneBoards.BOARD, .5));

		// Ce qu'un volume ne doit pas cacher : chaque planche tous les trois mètres, le pied de chaque pancarte et celui de l'enseigne
		this.watched = geometry.beds
			.flatMap(bed => Array.from({ length: Math.ceil(bed.length / 3) + 1 }, (_, i) => G.add(G.add(bed.origin, G.mul(bed.w, bed.width / 2)), G.mul(bed.u, Math.min(bed.length, i * 3)))))
			.concat(boards.map(({ base }) => base))
			.concat(ground.flatMap(([left, , , right]) => {
				const count = Math.ceil(G.length(G.sub(right, left)) / 3);
				return Array.from({ length: count + 1 }, (_, i) => G.lerp(left, right, i / count));
			}));

		this.placed = [];
		this.elements = [];

	}

	draw() {

		this.scatter();

		return this.elements;

	}

	/**
	 * Un élément du kit, à sa taille, monté pour connaitre son emprise. Sa graine suit son rang dans le tirage : deux bosquets ne se
	 * ressemblent pas, et le même rang redonne le même bosquet.
	 */
	make(kit, p, angle, size = 1) {

		const e = MapSceneDecor.element(kit, p);

		e.seed = this.serial++;
		e.angle = angle;
		e.size = size;

		this.decor.build(e);

		return e;

	}

	keep(e, rectangle) {

		this.decor.settle(e);
		this.elements.push(e);
		this.placed.push(rectangle);

	}

	// Un contour étroit passe entre les points sondés d'une emprise sans y poser un seul sommet : ses côtés la coupent
	static straddles(points, corners) {
		return points.some((q, i) => corners.some((c, j) => MapSceneDecorDefault.crosses(q, points[(i + 1) % points.length], c, corners[(j + 1) % 4])));
	}

	static crosses(a, b, c, d) {

		const side = (p, q, r) => (q.x - p.x) * (r.n - p.n) - (q.n - p.n) * (r.x - p.x);

		return side(a, b, c) * side(a, b, d) < 0 && side(c, d, a) * side(c, d, b) < 0;

	}

	static grown(rectangle, by) {

		const G = ScenePlanGeometry;

		return { origin: G.sub(rectangle.origin, G.add(G.mul(rectangle.u, by), G.mul(rectangle.w, by))), w: rectangle.w, u: rectangle.u, width: rectangle.width + 2 * by, length: rectangle.length + 2 * by };

	}

	// Un volume cache le sol derrière lui sur sa hauteur divisée par la pente du regard
	hides(rectangle, height) {

		const G = ScenePlanGeometry;
		const long = (rectangle.length > rectangle.width);
		const radius = Math.min(rectangle.length, rectangle.width) / 2 + .5;
		const count = Math.ceil(Math.max(rectangle.length, rectangle.width) / (radius * 2));
		const middle = MapSceneBoards.center(rectangle);
		const axis = long ? rectangle.u : rectangle.w;
		const reach = height / this.slope + radius;
		const span = reach + Math.max(rectangle.length, rectangle.width);
		const close = this.watched.filter(q => Math.abs(q.x - middle.x) < span && Math.abs(q.n - middle.n) < span);

		return Array.from({ length: count }, (_, i) => G.add(middle, G.mul(axis, (i + .5 - count / 2) * radius * 2))).some(c => close.some(q => {
			const d = G.sub(q, c);
			const along = G.dot(d, this.away);
			return along > 0 && along < reach && Math.abs(d.x * this.away.n - d.n * this.away.x) < radius;
		}));

	}

	/**
	 * L'îlot dont l'emprise porte un point, ou null hors de toute emprise. Le décor par défaut se tient dans l'emprise des îlots,
	 * à MARGIN de leurs parcelles, et non dans la portée où l'utilisateur pose (`MapSceneDecor.islandOf()`) : tiré pour habiller
	 * les parcelles, il n'a rien à faire loin d'elles dans le pré.
	 */
	islandOf(p) {

		const G = ScenePlanGeometry;
		const land = this.geometry.land;

		return land.islands.find(island => G.inside(p, island.plateau) && land.at(G.sub(p, island.offset)) === island) ?? null;

	}

	/**
	 * Une emprise tient : sur un seul îlot, hors de toute parcelle tracée, à `bedClear` mètres des planches et des serres, à deux
	 * mètres de ce qui est déjà posé et des pancartes, sans cacher de planche. `zoneClear` écarte de toute zone ce qui se pose
	 * autour du foncier ; à null, l'élément peut tenir sur l'herbe d'une zone.
	 */
	fits(rectangle, height, bedClear, zoneClear) {

		const G = ScenePlanGeometry;
		const D = MapSceneDecor;
		const corners = D.corners(rectangle);
		const probes = [...corners, MapSceneBoards.center(rectangle), ...corners.map((q, i) => G.lerp(q, corners[(i + 1) % 4], .5))];
		const islands = new Set(probes.map(p => this.islandOf(p)));

		if(islands.has(null) || islands.size > 1) {
			return false;
		}

		if(probes.some(q => this.plots.some(points => G.inside(q, points))) || this.plots.some(points => points.some(q => G.inside(q, corners)) || MapSceneDecorDefault.straddles(points, corners))) {
			return false;
		}

		if(zoneClear !== null && probes.some(q => this.zones.some(points => G.inside(q, points) || Math.min(...points.map((a, i) => G.distanceToSegment(q, a, points[(i + 1) % points.length]))) < zoneClear))) {
			return false;
		}

		const grown = MapSceneDecorDefault.grown(rectangle, bedClear);
		const spaced = MapSceneDecorDefault.grown(rectangle, 2);

		return this.beds.every(bed => D.overlaps(bed, grown) === false) &&
			this.glass.every(other => D.overlaps(other, grown) === false) &&
			this.boards.every(other => D.overlaps(other, spaced) === false) &&
			this.placed.every(other => D.overlaps(other, spaced) === false) &&
			this.hides(rectangle, height) === false;

	}

	/**
	 * Les bosquets puis les arbres seuls, en lisière du foncier et dans ses vides d'herbe : un groupe pour GROVE_EVERY mètres de
	 * pourtour, un arbre pour TREE_EVERY. Les places se prennent îlot après îlot, à tour de rôle : tirées d'un seul tas, elles iraient
	 * presque toutes au plus grand. Aucun arbre sur le trait entre deux îlots.
	 */
	scatter() {

		const G = ScenePlanGeometry;
		const B = MapSceneDecorDefault;
		const r = this.r;
		const land = this.geometry.land;
		const outlines = land.islands.map(island => land.moved(island));
		const perimeter = outlines.reduce((sum, outline) => sum + MapSceneDecor.loopLength(outline), 0);
		const shuffled = list => list.map(item => [r(), item]).sort((a, b) => a[0] - b[0]).map(([, item]) => item);

		const byIsland = outlines.map(outline => {

			const middle = G.centroid(outline);
			const xs = outline.map(q => q.x);
			const ns = outline.map(q => q.n);

			const edge = outline.flatMap((a, i) => {

				const b = outline[(i + 1) % outline.length];
				const length = G.length(G.sub(b, a));
				const along = G.norm(G.sub(b, a));
				const normal = { x: along.n, n: -along.x };
				const out = (G.dot(normal, G.sub(G.lerp(a, b, .5), middle)) < 0) ? G.mul(normal, -1) : normal;

				return Array.from({ length: Math.floor(length / B.EDGE_STEP) }, (_, k) => ({ p: G.add(G.add(a, G.mul(along, (k + .5) * B.EDGE_STEP)), G.mul(out, B.EDGE_OUT[0] + r() * (B.EDGE_OUT[1] - B.EDGE_OUT[0]))), edge: true }));

			});

			const voids = [];

			for(let x = Math.min(...xs); x < Math.max(...xs); x += B.VOID_STEP) {
				for(let n = Math.min(...ns); n < Math.max(...ns); n += B.VOID_STEP) {

					const p = { x: x + r() * B.VOID_STEP, n: n + r() * B.VOID_STEP };

					if(G.inside(p, outline)) {
						voids.push({ p, edge: false });
					}

				}
			}

			return shuffled([...edge, ...voids]);

		});

		const spots = Array.from({ length: Math.max(0, ...byIsland.map(list => list.length)) }, (_, k) => byIsland.flatMap(list => list[k] ?? []));
		const kept = [];
		const count = (every, [min, max]) => Math.max(min, Math.min(max, Math.round(perimeter / every)));

		// L'élément à placer se monte une fois et s'essaie de place en place : seuls sa place et son angle changent
		const plant = (kits, wanted, apart, [low, high]) => {

			let done = 0;
			let e = null;

			for(const { p, edge } of spots.flat()) {

				if(done === wanted) {
					break;
				}

				if(kept.some(q => G.length(G.sub(q, p)) < apart)) {
					continue;
				}

				e ??= this.make(kits[done % kits.length], p, 0, low + r() * (high - low));
				e.p = p;
				e.angle = Math.floor(r() * 24) * Math.PI / 12;

				const rectangle = MapSceneDecor.rectangle(e);
				const radius = Math.max(rectangle.width, rectangle.length) / 2;

				if(this.folds.some(([a, b]) => G.distanceToSegment(p, a, b) <= radius + 2) || this.fits(rectangle, e.height, 5, edge ? 3 : null) === false) {
					continue;
				}

				kept.push(p);
				this.keep(e, rectangle);
				done++;
				e = null;

			}

			if(e !== null) {
				this.decor.drop(e);
			}

		};

		plant(shuffled(B.GROVES), count(B.GROVE_EVERY, B.GROVE_COUNT), B.GROVE_APART, B.GROVE_SCALE);
		plant(B.TREES, count(B.TREE_EVERY, B.TREE_COUNT), B.TREE_APART, B.TREE_SCALE);

	}

}
