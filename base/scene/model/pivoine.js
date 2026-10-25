// Pivoine herbacée en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : trois grosses boules rose pâle,
// rondes et pleines, posées sur le dessus d'un dôme bas de feuilles en éventail vert très sombre. Le plus fort contraste
// est entre le rose de la boule et le vert sombre du feuillage ; le dessous de la fleur, plus sombre, la détache du dôme.
// Ce qui la sépare du dahlia : un dôme plus large que haut et des boules rondes enfoncées dans le feuillage, et non une
// touffe haute à fleurs magenta aplaties sur des tiges nues ; de la tomate : des boules roses sur le dessus, tournées vers
// le ciel, et non des boules rouges pendues sous le feuillage ; du chou et de la laitue : des feuilles découpées en
// folioles étroites, et des fleurs au-dessus.
// Fiche : pivoine.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#2c5527'; const PINK = '#f19ab8'; const LIGHT = '#fbd6e1'; const DEEP = '#a8385f';

	// Hasard à graine fixe : chaque feuille et chaque fleur varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 67;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Géométrie à partir d'une liste de triangles [[x, y, z] × 3] et d'une fonction qui donne la normale de chaque sommet
	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normalOf(c).toArray()), 3));
		return g;
	};
	// Normales lissées : chaque sommet porte la moyenne des faces qui le partagent, d'où une foliole pliée qui se lit
	// creusée le long de sa nervure sans arête dure
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			if(n.y < 0) n.negate();
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		return geometry(tris, c => normals.get(key(c)).clone().normalize());
	};

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const outwardOf = azimuth => new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth));
	const sideOf = azimuth => new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Une feuille biternée réduite à son éventail : trois folioles elliptiques-lancéolées qui partent du bout d'un pétiole
	// caché dans la touffe, la plus large au tiers, pointues, pliées le long de la nervure (les bords plus bas que la
	// nervure) et qui retombent au bout. La terminale est la plus longue. 3 × 2 = 6 triangles.
	const leaves = [];
	const leaf = (azimuth, footY, lean, petiole, length, width) => {
		const fork = outwardOf(azimuth).multiplyScalar(.015).setY(footY).addScaledVector(direction(azimuth, lean), petiole);
		const tris = [];
		const spread = between(.55, .7);
		for(const [j, turn] of [-spread, between(-.08, .08), spread * between(.9, 1.1)].entries()) {
			const a = azimuth + turn, side = sideOf(a);
			const along = direction(a, lean + .25 + Math.abs(turn) * .3);
			const span = length * (j === 1 ? 1 : between(.78, .9));
			const at = (t, s, fold) => fork.clone().addScaledVector(along, span * t).addScaledVector(side, width * s).setY(fork.y + along.y * span * t - span * .45 * t * t - fold).toArray();
			const L = at(.34, .5, width * .3), R = at(.34, -.5, width * .3), T = at(1, between(-.06, .06), 0);
			tris.push([fork.toArray(), L, T], [fork.toArray(), T, R]);
		}
		leaves.push(smooth(tris));
	};

	// Neuf feuilles sur un angle d'or bousculé, étagées le long des tiges : les basses longues et presque couchées font
	// l'emprise du dôme, les hautes plus courtes et plus dressées le ferment autour des fleurs. 9 × 6 = 54 triangles.
	for(let i = 0; i < 9; i++) {
		const k = i / 8;
		leaf(i * 2.39996 + between(-.3, .3), .18 + k * .28 * between(.92, 1.08), 1.2 - k * .6 + between(-.06, .06), (.15 - k * .07) * between(.9, 1.1), (.25 - k * .05) * between(.92, 1.08), (.14 - k * .03) * between(.9, 1.1));
	}

	// Une fleur double : une boule à cinq pans, un sommet plus clair qui fait le cœur froissé, et un dessous sombre qui
	// la détache du feuillage. L'anneau du haut est décalé d'un demi-pas sur celui du milieu, et chaque sommet porte la
	// normale qui part du centre de la boule : le contour se lit rond et non taillé. Chaque bande est peinte d'une couleur
	// entière, jamais face par face.
	// La tige : un seul triangle effilé, rougeâtre, du cœur de la touffe au dessous de la boule.
	// Par fleur : dessous 5 + flanc 10 + sommet 5 + tige 1 = 21 triangles.
	const sides = 5;
	const stems = [], undersides = [], flanks = [], tops = [];
	const flower = (azimuth, offset, height, tilt, radius) => {
		const axis = direction(azimuth, tilt);
		const center = outwardOf(azimuth).multiplyScalar(offset).setY(height);
		const u = sideOf(azimuth), v = axis.clone().cross(u).normalize();
		const place = (r, a, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, lift).toArray();
		const round = c => new THREE.Vector3(...c).sub(center).normalize();
		const step = Math.PI * 2 / sides, phase = between(0, step);
		const middle = Array.from({ length: sides }, (_, j) => place(radius * between(.94, 1.06), phase + j * step, radius * between(-.06, .06)));
		const upper = Array.from({ length: sides }, (_, j) => place(radius * between(.6, .68), phase + (j + .5) * step, radius * .62));
		const crown = place(0, 0, radius * .9), bottom = place(0, 0, -radius * .6);
		const next = j => (j + 1) % sides;
		undersides.push(geometry(middle.map((p, j) => [bottom, middle[next(j)], p]), round));
		flanks.push(geometry(middle.flatMap((p, j) => [[p, middle[next(j)], upper[j]], [upper[j], middle[next(j)], upper[next(j)]]]), round));
		tops.push(geometry(upper.map((p, j) => [p, upper[next(j)], crown]), round));

		const foot = outwardOf(azimuth).multiplyScalar(offset * .2).setY(.15);
		const width = sideOf(azimuth).multiplyScalar(.007);
		stems.push(smooth([[foot.clone().add(width).toArray(), foot.clone().sub(width).toArray(), bottom]]));
	};

	// Trois boules sur le dessus du dôme, à moitié enfoncées dans le feuillage, à des hauteurs, des écarts et des penchés
	// différents : la plus haute au centre, les deux autres penchées vers l'extérieur sous leur poids
	const heads = [[.5, .05, .57, .12, .078], [2.6, .17, .52, .45, .07], [4.5, .15, .5, .55, .066]];
	for(const [azimuth, offset, height, tilt, radius] of heads) {
		flower(azimuth + between(-.25, .25), offset * between(.9, 1.1), height * between(.97, 1.03), tilt + between(-.08, .08), radius * between(.95, 1.05));
	}

	const parts = [
		{ color: LEAF, geometry: helpers.merge(leaves) },
		{ color: PINK, sway: .5, geometry: helpers.merge(flanks) },
		{ color: LIGHT, sway: .5, geometry: helpers.merge(tops) },
		{ color: DEEP, sway: .5, geometry: helpers.merge([...undersides, ...stems]) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .75;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
