/**
 * Le plan projeté en mètres : les contours des parcelles et des jardins, et le rectangle de chaque
 * planche, sans rien de three. Les tracés de planches se lisent comme sur la carte du plan, en deux dimensions.
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'plan.js')`) : ses appelants le lisent en synchrone,
 * jusque dans le décor, là où un module ES demanderait d'attendre son import.
 */
class ScenePlanGeometry {

	// Le passe-pied d'une planche qui n'en déclare pas, en mètres
	static DEFAULT_ALLEY = .5;

	// Le rayon de la sphère de MapLib::EARTH_RADIUS, en mètres, sur laquelle la console trace les planches
	static EARTH_RADIUS = 6371008.8;

	// Montée de la couverture d'un abri au-dessus de ses pieds droits, en mètres : celle d'un grand tunnel maraîcher
	static VAULT_RISE = 3.5;

	// Les modèles d'abri par leur profil en travers, clés du `decorGreenhouse` d'une parcelle du plan : la hauteur des pieds droits, la
	// montée de la couverture selon la largeur, puis la couverture d'un pied à l'autre
	static GREENHOUSES = {
		// La voûte ne passe jamais sous le demi-cercle d'un abri étroit
		'straight': { wall: 1, rise: width => Math.max(.35 * width, width / 2 - 1), roof: (width, wall, rise) => ScenePlanGeometry.arc(width, wall, rise, 16) },
		'round': { wall: 0, rise: width => width / 2, roof: (width, wall, rise) => ScenePlanGeometry.arc(width, wall, rise, 24) },
		'gothic': { wall: .6, rise: width => .48 * width, roof: (width, wall, rise) => ScenePlanGeometry.ogive(width, wall, rise) },
		'chapel-2': ScenePlanGeometry.chapels(2),
		'chapel-3': ScenePlanGeometry.chapels(3),
		'chapel-4': ScenePlanGeometry.chapels(4)
	};

	/**
	 * Une multichapelle : des nefs accolées, d'ordinaire de 8 à 9,60 m sur des poteaux de 3 à 4 m, chacune sous son toit en pointe
	 * et ouverte au pignon par sa propre porte. La largeur du jardin se partage entre les nefs, la montée aussi.
	 */
	static chapels(count) {
		return {
			naves: count,
			wall: 3,
			rise: width => .28 * width / count,
			roof: (width, wall, rise) => ScenePlanGeometry.naves(width, count, span => [[0, wall], [span / 2, wall + rise], [span, wall]]),
			door: { width: span => Math.min(3, .35 * span), height: 2.2 }
		};
	}

	constructor(plan) {

		const [lng0, lat0] = plan.center;
		const kx = 111320 * Math.cos(lat0 * Math.PI / 180);
		const ky = 110540;

		const real = ([lng, lat]) => ({ x: (lng - lng0) * kx, n: (lat - lat0) * ky });

		// Le chemin inverse, d'un point aux coordonnées réelles vers sa longitude et sa latitude
		this.unproject = ({ x, n }) => [lng0 + x / kx, lat0 + n / ky];

		// Le relief que la scène pose sous le plan, `MapSceneRelief` ; sans lui, tout est à plat
		this.relief = null;

		// Les tracés GeoJSON répètent leur premier point en dernier
		this.land = new ScenePlanLand(plan.zones.filter(zone => zone.coordinates !== null).map(zone => ({ id: zone.id, points: zone.coordinates.slice(0, -1).map(real) })));

		// Les coordonnées gardent leurs distances réelles : le décalage de l'îlot s'ajoute à la projection, et tout ce qui se dessine le suit
		this.project = coordinates => {
			const p = real(coordinates);
			return ScenePlanGeometry.add(p, this.land.at(p).offset);
		};
		this.outline = coordinates => coordinates.slice(0, -1).map(this.project);

		this.zones = new Map();

		for(const zone of plan.zones) {
			if(zone.coordinates !== null) {
				this.zones.set(zone.id, this.outline(zone.coordinates));
			}
		}

		// Un jardin sans tracé occupe sa parcelle
		this.plots = new Map();

		for(const plot of plan.plots) {

			const drawn = (plot.coordinates !== null);
			const points = drawn ? this.outline(plot.coordinates) : this.zones.get(plot.zone);

			if(points !== undefined) {
				this.plots.set(plot.id, { plot, points, drawn, rectangle: ScenePlanGeometry.minRectangle(points) });
			}

		}

		this.beds = [];
		this.arrange(plan);

	}

	/**
	 * La hauteur du sol sous un point de la scène, en mètres.
	 */
	height(x, n) {
		return (this.relief === null) ? 0 : this.relief.height(x, n);
	}

	/**
	 * Une matrice posée sur le sol à sa translation, inclinée sur la pente sans que ses verticales ne penchent. Elle change en place.
	 */
	lean(matrix) {

		if(this.relief !== null) {
			this.relief.lean(matrix.elements);
		}

		return matrix;

	}

	/**
	 * Une planche tracée se pose sur son coin et son angle.
	 * Les autres se rangent par nom à la suite des deux dernières planches posées de leur jardin, ou à défaut dans le plus
	 * petit rectangle qui le contient, en rangs successifs.
	 *
	 * Une planche qui couvre son jardin ou sa parcelle n'a pas de rectangle : elle n'est que le support
	 * des séries posées sur le jardin entier.
	 */
	arrange(plan) {

		const bedsById = new Map(plan.beds.map(bed => [bed.id, bed]));
		const drawn = new Set();
		const ends = new Map();

		for(const bedDrawn of plan.bedsDrawn) {

			const bed = bedsById.get(bedDrawn.bed);

			if(bed === undefined || bed.plotFill || bed.zoneFill || bed.length === null || bed.width === null) {
				continue;
			}

			// Le rectangle de DrawLib::getLine(), tracé sur la sphère puis projeté : la projection n'a pas la même échelle sur ses deux
			// axes, et la planche ne garde sa place dans le contour de son jardin qu'en passant par les mêmes degrés que lui
			const width = bed.width / 100;
			const degrees = bedDrawn.angle * 180 / Math.PI;
			const across = ScenePlanGeometry.destination(bedDrawn.corner, width, degrees);
			const [corner, side, far] = [bedDrawn.corner, across, ScenePlanGeometry.destination(across, bed.length, degrees + 90)].map(this.project);
			const along = ScenePlanGeometry.norm(ScenePlanGeometry.sub(side, corner));
			const direction = ScenePlanGeometry.norm(ScenePlanGeometry.sub(far, side));

			this.add(bed, corner, along, direction, bed.length, width);
			drawn.add(bed.id);

			// Comme DrawLib::getLineNext() pour la page du plan : le rang continue de l'avant-dernière planche posée vers la dernière
			const previous = ends.get(bedDrawn.plot);
			const reversed = (previous !== undefined && ScenePlanGeometry.dot(ScenePlanGeometry.sub(corner, previous.corner), along) < 0);
			const end = width + ((bed.alleyWidth !== null) ? bed.alleyWidth / 100 : ScenePlanGeometry.DEFAULT_ALLEY);

			ends.set(bedDrawn.plot, reversed ?
				{ corner, origin: side, along: ScenePlanGeometry.mul(along, -1), direction, end } :
				{ corner, origin: corner, along, direction, end });

		}

		const undrawn = new Map();

		for(const bed of plan.beds) {

			if(bed.plotFill || bed.zoneFill || drawn.has(bed.id)) {
				continue;
			}

			if(undrawn.has(bed.plot) === false) {
				undrawn.set(bed.plot, []);
			}

			undrawn.get(bed.plot).push(bed);

		}

		for(const [plotId, beds] of undrawn) {

			const end = ends.get(plotId);
			const plot = this.plots.get(plotId);

			let origin, along, direction, position;
			let rowLength = Infinity;
			let rowWidth = Infinity;

			if(end !== undefined) {
				({ origin, along, direction } = end);
				position = end.end;
			} else if(plot !== undefined) {
				({ origin, w: along, u: direction } = plot.rectangle);
				position = 0;
				rowLength = plot.rectangle.length;
				rowWidth = plot.rectangle.width;
			} else {
				continue;
			}

			beds.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? '', 'fr', { numeric: true }));

			let row = 0;

			for(const bed of beds) {

				const width = (bed.width ?? 80) / 100;
				const length = Math.min(bed.length ?? 10, rowLength);
				const alley = (bed.alleyWidth !== null) ? bed.alleyWidth / 100 : ScenePlanGeometry.DEFAULT_ALLEY;

				if(position + width > rowWidth && position > 0) {
					position = 0;
					row++;
				}

				const rowOrigin = ScenePlanGeometry.add(origin, ScenePlanGeometry.mul(direction, row * Math.min(rowLength, (bed.length ?? 10) + 1)));

				this.add(bed, ScenePlanGeometry.add(rowOrigin, ScenePlanGeometry.mul(along, position)), along, direction, length, width);

				position += width + alley;

			}

		}

	}

	add(bed, origin, along, direction, length, width) {

		const plot = this.plots.get(bed.plot)?.plot;

		this.beds.push({ id: bed.id, name: bed.name, plot: bed.plot, zone: plot?.zone ?? null, greenhouse: (plot?.mode === 'greenhouse'), origin, w: along, u: direction, length, width });

	}

	corners(bed) {

		const p1 = bed.origin;
		const p2 = ScenePlanGeometry.add(bed.origin, ScenePlanGeometry.mul(bed.w, bed.width));

		return [p1, p2, ScenePlanGeometry.add(p2, ScenePlanGeometry.mul(bed.u, bed.length)), ScenePlanGeometry.add(p1, ScenePlanGeometry.mul(bed.u, bed.length))];

	}

	/**
	 * Le plus petit rectangle qui contient un contour, sa longueur `u` le long de son grand côté.
	 */
	static minRectangle(points) {

		const hull = ScenePlanGeometry.hull(points);
		let best = null;

		for(let i = 0; i < hull.length; i++) {

			const u = ScenePlanGeometry.norm(ScenePlanGeometry.sub(hull[(i + 1) % hull.length], hull[i]));
			const w = { x: -u.n, n: u.x };

			let minU = Infinity, maxU = -Infinity, minW = Infinity, maxW = -Infinity;

			for(const q of hull) {

				const a = ScenePlanGeometry.dot(q, u);
				const b = ScenePlanGeometry.dot(q, w);

				minU = Math.min(minU, a);
				maxU = Math.max(maxU, a);
				minW = Math.min(minW, b);
				maxW = Math.max(maxW, b);

			}

			const area = (maxU - minU) * (maxW - minW);

			if(best === null || area < best.area) {
				best = { area, u, w, minU, maxU, minW, maxW };
			}

		}

		let { u, w, minU, maxU, minW, maxW } = best;

		if(maxU - minU < maxW - minW) {
			[u, w, minU, maxU, minW, maxW] = [w, { x: -u.x, n: -u.n }, minW, maxW, -maxU, -minU];
		}

		return { origin: ScenePlanGeometry.add(ScenePlanGeometry.mul(u, minU), ScenePlanGeometry.mul(w, minW)), u, w, length: maxU - minU, width: maxW - minW };

	}

	/**
	 * Un abri sur la largeur de son jardin : sa couverture, [largeur, hauteur] d'un pied droit à l'autre, sa
	 * porte quand le modèle en a, et sa hauteur au faîtage. Le modèle est facultatif : un abri qui n'en a pas
	 * se montre à pieds droits.
	 */
	static greenhouse(type, width) {

		const { naves = 1, wall, rise, roof, door = null } = ScenePlanGeometry.GREENHOUSES[type ?? 'straight'];

		// Le jardin sous abri est une emprise saisie, parfois une multichapelle de vingt mètres : la couverture s'arrête à la
		// hauteur d'un grand tunnel maraîcher plutôt que de grandir avec la largeur
		const cover = roof(width, wall, Math.min(rise(width), ScenePlanGeometry.VAULT_RISE));

		return {
			cover,
			doors: (door === null) ? [] : Array.from({ length: naves }, (_, nave) => ScenePlanGeometry.door(cover, width / naves, nave, door)),
			height: Math.max(...cover.map(([, y]) => y))
		};

	}

	/**
	 * La porte d'une nef au milieu de son pignon, `from` comptée depuis le pied droit de gauche de l'abri. Sur un abri étroit, la
	 * couverture descend sous le linteau au droit des montants : la porte s'arrête sous elle, et le film, les montants, le linteau
	 * et le meneau suivent la même hauteur.
	 */
	static door(cover, span, nave, door) {

		const doorWidth = door.width(span);
		const from = nave * span + (span - doorWidth) / 2;

		return {
			from,
			width: doorWidth,
			height: Math.min(door.height, ScenePlanGeometry.roofAt(cover, from), ScenePlanGeometry.roofAt(cover, from + doorWidth))
		};

	}

	/**
	 * Le profil de `count` nefs accolées sur une largeur, d'après le profil d'une nef selon sa largeur : deux nefs voisines
	 * partagent le point de leur chéneau, qui n'apparaît qu'une fois.
	 */
	static naves(width, count, profileOf) {

		const span = width / count;
		const profile = profileOf(span);

		return Array.from({ length: count }, (_, k) => profile.slice((k === 0) ? 0 : 1).map(([across, height]) => [k * span + across, height])).flat();

	}

	/**
	 * La hauteur de la couverture à une distance du pied droit de gauche.
	 */
	static roofAt(cover, across) {

		// Au-delà du dernier sommet, la hauteur se lit sur le dernier segment : la somme des nefs peut finir un epsilon sous la largeur
		const found = cover.findIndex(([a]) => a >= across);
		const i = (found === -1) ? cover.length - 1 : Math.max(1, found);
		const [a0, h0] = cover[i - 1];
		const [a1, h1] = cover[i];

		return (a1 > a0) ? h0 + (h1 - h0) * (across - a0) / (a1 - a0) : h1;

	}

	static arc(width, wall, rise, steps) {
		return Array.from({ length: steps + 1 }, (_, i) => [width / 2 - Math.cos(Math.PI * i / steps) * width / 2, wall + Math.sin(Math.PI * i / steps) * rise]);
	}

	/**
	 * Une ogive : deux arcs dont les centres s'écartent d'un sixième de la largeur, ramenés à la montée voulue.
	 */
	static ogive(width, wall, rise) {

		const shift = width / 6;
		const radius = width / 2 + shift;
		const top = Math.acos(shift / radius);
		const k = rise / (radius * Math.sin(top));

		const half = Array.from({ length: 9 }, (_, i) => [radius - radius * Math.cos(top * i / 8), wall + k * radius * Math.sin(top * i / 8)]);

		return [...half, ...half.slice(0, -1).reverse().map(([across, height]) => [width - across, height])];

	}

	static inside(point, polygon) {

		let inside = false;

		for(let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {

			const a = polygon[i];
			const b = polygon[j];

			if((a.n > point.n) !== (b.n > point.n) && point.x < (b.x - a.x) * (point.n - a.n) / (b.n - a.n) + a.x) {
				inside = !inside;
			}

		}

		return inside;

	}

	static hull(points) {

		const sorted = [...points].sort((a, b) => a.x - b.x || a.n - b.n);
		const cross = (o, a, b) => (a.x - o.x) * (b.n - o.n) - (a.n - o.n) * (b.x - o.x);
		const lower = [];
		const upper = [];

		for(const q of sorted) {
			while(lower.length >= 2 && cross(lower.at(-2), lower.at(-1), q) <= 0) {
				lower.pop();
			}
			lower.push(q);
		}

		for(const q of sorted.reverse()) {
			while(upper.length >= 2 && cross(upper.at(-2), upper.at(-1), q) <= 0) {
				upper.pop();
			}
			upper.push(q);
		}

		return lower.slice(0, -1).concat(upper.slice(0, -1));

	}

	static add(a, b) {
		return { x: a.x + b.x, n: a.n + b.n };
	}

	static sub(a, b) {
		return { x: a.x - b.x, n: a.n - b.n };
	}

	static mul(a, k) {
		return { x: a.x * k, n: a.n * k };
	}

	static dot(a, b) {
		return a.x * b.x + a.n * b.n;
	}

	/**
	 * Le point à `distance` mètres de `from` ([lng, lat]) au cap `bearing`, en degrés comptés dans le sens horaire depuis le nord,
	 * sur la sphère de MapLib::getDestination(), dont il rend les mêmes degrés.
	 */
	static destination([lng, lat], distance, bearing) {

		const longitude = lng * Math.PI / 180;
		const latitude = lat * Math.PI / 180;
		const heading = bearing * Math.PI / 180;
		const radians = distance / ScenePlanGeometry.EARTH_RADIUS;

		const latitudeTo = Math.asin(Math.sin(latitude) * Math.cos(radians) + Math.cos(latitude) * Math.sin(radians) * Math.cos(heading));
		const longitudeTo = longitude + Math.atan2(Math.sin(heading) * Math.sin(radians) * Math.cos(latitude), Math.cos(radians) - Math.sin(latitude) * Math.sin(latitudeTo));

		return [longitudeTo * 180 / Math.PI, latitudeTo * 180 / Math.PI];

	}

	static length(a) {
		return Math.hypot(a.x, a.n);
	}

	static norm(a) {
		const length = Math.hypot(a.x, a.n) || 1;
		return { x: a.x / length, n: a.n / length };
	}

	static lerp(a, b, k) {
		return { x: a.x + (b.x - a.x) * k, n: a.n + (b.n - a.n) * k };
	}

	static centroid(points) {
		return ScenePlanGeometry.mul(points.reduce((sum, p) => ScenePlanGeometry.add(sum, p), { x: 0, n: 0 }), 1 / points.length);
	}

	static area(points) {
		return Math.abs(points.reduce((sum, p, i) => sum + p.x * points[(i + 1) % points.length].n - points[(i + 1) % points.length].x * p.n, 0)) / 2;
	}

	static distanceToSegment(p, a, b) {

		const d = ScenePlanGeometry.sub(b, a);
		const k = Math.max(0, Math.min(1, ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, a), d) / Math.max(1e-6, ScenePlanGeometry.dot(d, d))));

		return ScenePlanGeometry.length(ScenePlanGeometry.sub(p, ScenePlanGeometry.add(a, ScenePlanGeometry.mul(d, k))));

	}

	/**
	 * La distance entre deux polygones qui ne se recouvrent pas, et les deux points qui la donnent, `a` sur le premier et `b` sur le
	 * second.
	 */
	static gap(polygonA, polygonB) {

		let best = { distance: Infinity, a: null, b: null };

		for(const [from, to, swapped] of [[polygonA, polygonB, false], [polygonB, polygonA, true]]) {
			for(const p of from) {
				for(let i = 0; i < to.length; i++) {

					const a = to[i];
					const d = ScenePlanGeometry.sub(to[(i + 1) % to.length], a);
					const q = ScenePlanGeometry.add(a, ScenePlanGeometry.mul(d, Math.max(0, Math.min(1, ScenePlanGeometry.dot(ScenePlanGeometry.sub(p, a), d) / Math.max(1e-6, ScenePlanGeometry.dot(d, d))))));
					const distance = ScenePlanGeometry.length(ScenePlanGeometry.sub(q, p));

					if(distance < best.distance) {
						best = { distance, a: swapped ? q : p, b: swapped ? p : q };
					}

				}
			}
		}

		return best;

	}

}

/**
 * Le foncier éclaté, rapproché à l'affichage : les zones à moins de `JOIN` mètres l'une de l'autre forment un
 * îlot, et quand la structure en a plusieurs, seul le vide qui les sépare se resserre. Chaque îlot garde sa forme, son orientation
 * et son échelle. Le plus grand ne bouge pas ; les autres viennent, du plus proche au plus lointain, se ranger contre l'îlot déjà
 * posé le plus proche d'eux, dans la direction réelle qui les en sépare, à `GAP` mètres entre deux plateaux qui débordent
 * chacun de `MARGIN` mètres autour de leurs zones.
 */
class ScenePlanLand {

	static JOIN = 70;
	static GAP = 28;
	static MARGIN = 20;

	// Jusqu'où le décor se pose autour des zones : une mare, une route ou un hangar vivent souvent hors des parcelles dessinées.
	// À part de MARGIN, qui écarte deux îlots et ne peut pas grandir sans les éloigner à l'affichage
	static REACH = 100;

	constructor(zones) {

		const G = ScenePlanGeometry;

		// Union des zones voisines, par leurs racines
		const group = zones.map((zone, i) => i);
		const find = i => (group[i] === i) ? i : (group[i] = find(group[i]));

		for(let i = 0; i < zones.length; i++) {
			for(let j = i + 1; j < zones.length; j++) {
				if(find(i) !== find(j) && G.gap(zones[i].points, zones[j].points).distance < ScenePlanLand.JOIN) {
					group[find(i)] = find(j);
				}
			}
		}

		this.islands = [...new Set(zones.map((zone, i) => find(i)))]
			.map(root => {

				const own = zones.filter((zone, i) => find(i) === root);
				const hull = G.hull(own.flatMap(zone => zone.points));

				return { zones: own, hull, center: G.centroid(hull), area: G.area(hull), offset: { x: 0, n: 0 }, plateau: [], reach: [] };

			})
			.sort((a, b) => b.area - a.area);

		this.links = [];

		const placed = this.islands.slice(0, 1);
		const waiting = this.islands.slice(1);

		while(waiting.length > 0) {

			let next = null;

			for(const island of waiting) {
				for(const host of placed) {

					const gap = G.gap(island.hull, host.hull).distance;

					if(next === null || gap < next.gap) {
						next = { island, host, gap };
					}

				}
			}

			const { island, host } = next;
			waiting.splice(waiting.indexOf(island), 1);

			// L'îlot avance de mètre en mètre depuis le centre de son hôte, jusqu'au premier endroit où il ne touche ni ne contient aucun
			// îlot déjà posé
			const bearing = G.norm(G.sub(island.center, host.center));
			const from = G.add(host.center, host.offset);
			const reach = G.length(G.sub(island.center, host.center));

			for(let d = 0; d <= reach; d++) {

				island.offset = G.sub(G.add(from, G.mul(bearing, d)), island.center);
				const shifted = this.moved(island);

				if(placed.every(other => {
					const outline = this.moved(other);
					return G.gap(shifted, outline).distance >= ScenePlanLand.GAP + ScenePlanLand.MARGIN * 2 && G.inside(shifted[0], outline) === false && G.inside(outline[0], shifted) === false;
				})) {
					break;
				}

			}

			placed.push(island);
			this.links.push({ a: host, b: island });

		}

		// L'emprise d'un îlot : son enveloppe gonflée de MARGIN. Rien ne la dessine, elle borne le trait qui sépare deux îlots. Sa
		// portée est la même enveloppe gonflée de REACH, où le décor se pose
		for(const island of this.islands) {

			const outline = this.moved(island);

			island.plateau = ScenePlanLand.swollen(outline, ScenePlanLand.MARGIN);
			island.reach = ScenePlanLand.swollen(outline, ScenePlanLand.REACH);

		}

	}

	/**
	 * Une enveloppe convexe gonflée de `by` mètres, aux coins arrondis.
	 */
	static swollen(outline, by) {

		const G = ScenePlanGeometry;

		return outline.flatMap((q, i) => {

			const a = G.norm(G.sub(q, outline[(i + outline.length - 1) % outline.length]));
			const b = G.norm(G.sub(outline[(i + 1) % outline.length], q));
			const start = Math.atan2(-a.x, a.n);
			const turn = ((Math.atan2(-b.x, b.n) - start) % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);
			const steps = Math.max(1, Math.ceil(turn / .25));

			return Array.from({ length: steps + 1 }, (_, k) => G.add(q, { x: Math.cos(start + turn * k / steps) * by, n: Math.sin(start + turn * k / steps) * by }));

		});

	}

	/**
	 * La distance d'un point de la scène à l'enveloppe d'un îlot telle qu'elle est posée, nulle dedans.
	 */
	away(p, island) {

		const outline = this.moved(island);

		if(ScenePlanGeometry.inside(p, outline)) {
			return 0;
		}

		return Math.min(...outline.map((q, i) => ScenePlanGeometry.distanceToSegment(p, q, outline[(i + 1) % outline.length])));

	}

	moved(island) {
		return island.hull.map(q => ScenePlanGeometry.add(q, island.offset));
	}

	/**
	 * L'îlot d'un point aux coordonnées réelles : celui de la zone qui le contient, sinon de la zone la plus proche.
	 */
	at(p) {

		if(this.islands.length === 1) {
			return this.islands[0];
		}

		let best = { island: this.islands[0], distance: Infinity };

		for(const island of this.islands) {
			for(const zone of island.zones) {

				if(ScenePlanGeometry.inside(p, zone.points)) {
					return island;
				}

				const distance = Math.min(...zone.points.map((q, i) => ScenePlanGeometry.distanceToSegment(p, q, zone.points[(i + 1) % zone.points.length])));

				if(distance < best.distance) {
					best = { island, distance };
				}

			}
		}

		return best.island;

	}

	/**
	 * Le trait au sol entre deux îlots rapprochés : il court sur toute la largeur des deux, s'arrête avant d'entrer dans l'emprise
	 * d'un troisième.
	 */
	folds() {

		const G = ScenePlanGeometry;

		return this.links.map(link => {

			const gap = G.gap(link.a.plateau, link.b.plateau);
			const across = G.norm(G.sub(gap.b, gap.a));
			const along = { x: -across.n, n: across.x };
			const middle = G.lerp(gap.a, gap.b, .5);

			const reach = [...link.a.plateau, ...link.b.plateau].map(q => G.dot(G.sub(q, middle), along));
			const free = at => this.islands.every(island => G.inside(G.add(middle, G.mul(along, at)), island.plateau) === false);

			let from = 0;
			let to = 0;

			while(from > Math.min(...reach) && free(from - 2)) {
				from -= 2;
			}

			while(to < Math.max(...reach) && free(to + 2)) {
				to += 2;
			}

			return { center: G.add(middle, G.mul(along, (from + to) / 2)), along, length: to - from };

		});

	}

}
