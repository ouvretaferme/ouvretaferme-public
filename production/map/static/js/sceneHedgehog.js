/**
 * Le hérisson de la scène du plan, qui longe les haies et les bandes fleuries. Il vit dans `MapSceneFauna`, dont il emprunte le
 * tirage, le cadre, le décor relu et le grossissement : ce qu'il fait suit comme les oiseaux l'horloge des images, et seule sa
 * saison lit la semaine montrée.
 *
 * Il tient dans un fichier à part parce que le bourg de la comptabilité monte la faune sans hérisson (`hedgehog: false`) : il ne
 * charge que les oiseaux.
 */
class MapSceneHedgehog {

	// Il hiberne de la semaine 45 à la semaine 12
	static WEEKS = [13, 44];

	// Il s'arrête REST secondes entre deux trottes, à TROT mètres par seconde de sa taille réelle. Il longe la haie à ASIDE mètres de
	// sa taille réelle de son bord, se juge tous les STEP mètres, et ne marche qu'à moins de TURN_GATE radians de sa route
	static REST = [5, 15];
	static ASIDE = .35;
	static STEP = 1;
	static TROT = .3;
	static TURN_SPEED = 3;
	static TURN_GATE = 1;

	constructor(fauna, figure, size) {

		this.fauna = fauna;
		this.figure = figure;
		this.size = size;
		this.scale = 0;
		this.faint = false;
		this.e = null;
		this.side = 1;
		this.aside = 0;
		this.path = null;
		this.s = 0;
		this.goal = 0;
		this.at = { x: 0, n: 0, dx: 1, dn: 0 };
		this.heading = 0;
		this.rest = 0;

	}

	/**
	 * La ligne qui longe `line` à `distance` mètres, à sa gauche ou à sa droite selon le signe. Dans un coude le point s'écarte pour
	 * que les deux côtés restent à distance, du double au plus dans un coude serré.
	 */
	static beside(line, distance) {

		const G = ScenePlanGeometry;

		const normals = line.slice(1).map((b, i) => {
			const d = G.norm(G.sub(b, line[i]));
			return { x: -d.n, n: d.x };
		});

		return line.map((p, i) => {

			const before = normals[Math.max(0, i - 1)];
			const middle = G.norm(G.add(before, normals[Math.min(normals.length - 1, i)]));

			return G.add(p, G.mul(middle, distance / Math.max(.5, G.dot(middle, before))));

		});

	}

	// Le point d'une ligne à `s` mètres de son début et sa direction, écrits dans `out` : la trotte le lit à chaque image
	static along(path, s, out) {

		let left = s;

		for(let i = 1; i < path.length; i++) {

			const dx = path[i].x - path[i - 1].x;
			const dn = path[i].n - path[i - 1].n;
			const length = Math.hypot(dx, dn);

			if(length > 0 && (left <= length || i === path.length - 1)) {

				const k = Math.min(1, left / length);

				out.x = path[i - 1].x + dx * k;
				out.n = path[i - 1].n + dn * k;
				out.dx = dx / length;
				out.dn = dn / length;

				return;

			}

			left -= length;

		}

	}

	// L'abscisse du point d'une ligne le plus proche de `p`, et sa distance
	static project(path, p) {

		const G = ScenePlanGeometry;
		let best = { s: 0, distance: Infinity };
		let s = 0;

		for(let i = 1; i < path.length; i++) {

			const d = G.sub(path[i], path[i - 1]);
			const length = G.length(d);
			const k = (length > 0) ? Math.max(0, Math.min(1, G.dot(G.sub(p, path[i - 1]), d) / (length * length))) : 0;
			const distance = G.length(G.sub(p, G.lerp(path[i - 1], path[i], k)));

			if(distance < best.distance) {
				best = { s: s + k * length, distance };
			}

			s += length;

		}

		return best;

	}

	static lengthOf(path) {
		return path.slice(1).reduce((total, p, i) => total + ScenePlanGeometry.length(ScenePlanGeometry.sub(p, path[i])), 0);
	}

	/**
	 * La ligne qu'il longe déjà, relue dans le décor du moment, et son abscisse sur elle. `null` s'il n'en longe aucune, ou si
	 * elle a bougé, s'est masquée ou a disparu.
	 */
	held(lines) {

		const H = MapSceneHedgehog;

		if(this.path === null) {
			return null;
		}

		const line = lines.find(line => line.e === this.e);

		if(line === undefined) {
			return null;
		}

		// La ligne se compare à l'écart où il la longeait, puis se rend à celui de son grossissement du moment : un zoom qui l'a
		// grossi depuis l'écarte de sa haie sans la lui faire perdre
		if(H.project(H.beside(line.line, this.side * (line.half + this.aside)), this.at).distance >= MapSceneFauna.SAME) {
			return null;
		}

		const path = H.beside(line.line, this.side * (line.half + H.ASIDE * this.scale));

		return { e: this.e, side: this.side, path, s: H.project(path, this.at).s };

	}

	/**
	 * Une place le long d'une haie ou d'une bande fleurie : dans le cadre, tirée parmi les plus proches du point regardé, et quand
	 * le cadre n'en offre aucune la plus proche de tout le plan ; du côté de la caméra d'abord, où la haie ne le cache pas, de
	 * l'autre sinon. Une ligne longue déborde du cadre : elle se sonde en cinq points.
	 */
	settle(lines, blocks, pick, camera, controls) {

		const G = ScenePlanGeometry;
		const H = MapSceneHedgehog;

		const toCamera = { x: camera.position.x - controls.target.x, n: controls.target.z - camera.position.z };

		for(const [framed, facing] of [[true, 1], [true, -1], [false, 1], [false, -1]]) {

			const found = [];

			for(const { e, line, half } of lines) {

				const d = G.sub(line[line.length - 1], line[0]);
				const side = facing * ((G.dot({ x: -d.n, n: d.x }, toCamera) >= 0) ? 1 : -1);
				const path = H.beside(line, side * (half + H.ASIDE * this.scale));
				const total = H.lengthOf(path);

				for(let i = 0; i < 5; i++) {

					const s = total * (.1 + .8 * i / 4);
					const p = { x: 0, n: 0, dx: 1, dn: 0 };
					H.along(path, s, p);

					if((framed === false || this.fauna.seen(p)) && MapSceneFauna.clear(p, blocks, MapSceneFauna.ROOM * this.scale)) {
						found.push({ e, side, path, s, p });
					}

				}

			}

			if(found.length > 0) {
				const near = this.fauna.nearest(found, place => place.p, controls);
				return near[framed ? Math.floor(pick * near.length) : 0];
			}

		}

		return null;

	}

	/**
	 * La décision à la fin d'un arrêt : il repart le long de sa ligne, jusqu'où elle reste libre et dans le cadre. Sorti du cadre,
	 * il y reparait si une place s'y offre, passe à la ligne la plus proche du point regardé si ce n'est plus la sienne, et trotte
	 * sinon où il est. Sa ligne perdue, il en prend une autre, et disparait si le plan n'en a aucune. Comme pour un oiseau, une
	 * décision consomme le même nombre de tirages quoi qu'elle trouve.
	 */
	decide(camera, controls) {

		const H = MapSceneHedgehog;
		const fauna = this.fauna;

		fauna.look(camera);

		const { lines } = fauna.habitat();
		const blocks = (lines.length > 0) ? fauna.blocks() : [];
		const rest = fauna.delay(H.REST);
		const share = fauna.random();
		const pick = fauna.random();

		const held = this.held(lines);
		let place = held;

		if(place === null) {
			place = this.settle(lines, blocks, pick, camera, controls);
		} else if(fauna.seen(this.at) === false) {

			const other = this.settle(lines, blocks, pick, camera, controls);

			// Hors du cadre, il ne quitte sa place que pour y rentrer ou pour une ligne plus proche du point regardé : le long de
			// la sienne, il trotte
			if(other !== null && (fauna.seen(other.p) || other.e !== place.e)) {
				place = other;
			}

		}

		if(place === null) {
			this.path = null;
			this.rest = MapSceneFauna.RETRY;
			return;
		}

		// Une place neuve se prend avant de choisir où aller : rentré dans le cadre, c'est de là qu'il ne doit plus en sortir
		if(place !== held) {
			H.along(place.path, place.s, this.at);
			this.heading = Math.atan2(this.at.dn, this.at.dx);
		}

		this.e = place.e;
		this.side = place.side;
		this.path = place.path;
		this.aside = H.ASIDE * this.scale;
		this.s = place.s;
		this.rest = rest;

		// Il va vers le point tiré, et vers l'autre bout de sa ligne si ce point ne le fait pas avancer d'un pas, pris ou trop
		// proche : un arrêt ne se prolonge que s'il est cerné
		const total = H.lengthOf(this.path);
		const goal = share * total;

		this.goal = this.reach(blocks, goal);

		if(this.goal === this.s) {
			this.goal = this.reach(blocks, (goal > this.s) ? 0 : total);
		}

	}

	// Jusqu'où il avance vers `goal` le long de sa ligne : le dernier pas libre, et dans le cadre s'il y est déjà
	reach(blocks, goal) {

		const H = MapSceneHedgehog;
		const way = Math.sign(goal - this.s);
		const probe = { x: 0, n: 0, dx: 1, dn: 0 };

		const framed = this.fauna.seen(this.at);
		let reached = this.s;

		for(let s = this.s + way * H.STEP; way !== 0 && (goal - s) * way >= 0; s += way * H.STEP) {

			H.along(this.path, s, probe);

			if((framed && this.fauna.seen(probe) === false) || MapSceneFauna.clear(probe, blocks, MapSceneFauna.ROOM * this.scale) === false) {
				break;
			}

			reached = s;

		}

		return reached;

	}

	trot(dt, seconds, week, camera, controls) {

		const H = MapSceneHedgehog;
		const { root, bones } = this.figure;

		if(week < H.WEEKS[0] || week > H.WEEKS[1]) {
			this.path = null;
			this.rest = 0;
			root.visible = false;
			return;
		}

		const way = (this.path === null) ? 0 : Math.sign(this.goal - this.s);
		let trotting = false;

		if(way === 0 || Math.abs(this.goal - this.s) < 1e-3) {

			this.rest -= dt;

			if(this.rest <= 0) {
				this.decide(camera, controls);
			}

		} else {

			const turn = Math.atan2(this.at.dn * way, this.at.dx * way) - this.heading;
			const gap = Math.atan2(Math.sin(turn), Math.cos(turn));

			this.heading += Math.max(-H.TURN_SPEED * dt, Math.min(H.TURN_SPEED * dt, gap));

			// Il se tourne avant de partir, plutôt que de trotter à reculons
			if(Math.abs(gap) < H.TURN_GATE) {
				this.s += way * Math.min(Math.abs(this.goal - this.s), H.TROT * this.scale * dt);
				trotting = true;
			}

		}

		root.visible = (this.path !== null && this.faint === false);

		if(this.path === null) {
			return;
		}

		H.along(this.path, this.s, this.at);

		root.position.set(this.at.x, this.fauna.geometry.height(this.at.x, this.at.n) + (trotting ? Math.abs(Math.sin(seconds * 12)) * .012 * this.scale : 0), -this.at.n);
		root.rotation.set(0, this.heading, 0);
		root.scale.setScalar(this.scale);

		// En marche il se dandine, à l'arrêt il flaire
		bones.corps.rotation.x = trotting ? Math.sin(seconds * 12) * .08 : 0;
		bones.tete.rotation.y = trotting ? 0 : Math.sin(seconds * 2.1) * .3;
		bones.tete.rotation.z = trotting ? 0 : Math.sin(seconds * 3.3) * .12;

	}

}
