// Poivron, pensé pour se lire à une trentaine de pixels : un buisson feuillu jusqu'au sol, de grandes feuilles ovales et
// pointues, pliées sur leur nervure, portées tout le long de trois tiges qui s'ouvrent en vase depuis le pied.
// Les trois tiges partent entre les trois places où le socle pend ses cloches (FRUITS.poivron, à 0,27 de l'emprise, entre
// 0,3 et 0,4 de la hauteur, une par tiers du tour) : à mi-hauteur, les feuilles suivent la tige au lieu de barrer ces
// places, une couronne basse les reprend au ras du sol et les feuilles de la pousse centrale les couvrent. Le contraste
// le plus fort est entre les cloches rouges et ce vert franc.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : poivron.reference.md.
export function build(THREE) {
	const DARK = '#1f6b2a'; const LEAF = '#2f8f34'; const SHINE = '#6fc75a'; const STEM = '#5f8f3a';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (la nervure pliée prend deux lumières)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const add = (a, b) => a.map((x, i) => x + b[i]);
	const lerp = (a, b, t) => a.map((x, i) => x + (b[i] - x) * t);
	const polar = (azimuth, radius, y) => [Math.cos(azimuth) * radius, y, Math.sin(azimuth) * radius];

	// Tube à trois pans ouvert entre deux points, qui s'affine de r0 à r1 (6 triangles)
	const tube = (from, to, r0, r1) => {
		const axis = new THREE.Vector3(...to).sub(new THREE.Vector3(...from)).normalize();
		const side = new THREE.Vector3(0, 1, 0).cross(axis);
		if(side.lengthSq() < 1e-6) side.set(1, 0, 0);
		side.normalize();
		const up = new THREE.Vector3().crossVectors(axis, side);
		const ring = (center, r) => [0, 1, 2].map(j => {
			const a = j * Math.PI * 2 / 3;
			return add(center, [...side.clone().multiplyScalar(Math.cos(a) * r).add(up.clone().multiplyScalar(Math.sin(a) * r))]);
		});
		const A = ring(from, r0), B = ring(to, r1), tris = [];
		for(let j = 0; j < 3; j++) {
			const k = (j + 1) % 3;
			tris.push([A[j], B[j], A[k]], [A[k], B[j], B[k]]);
		}
		return tris;
	};

	// Limbe entier et ovale, large avant son milieu, pointe effilée, en huit triangles : moitié du contour en (avancée, écart),
	// de la base à la pointe. La nervure est relevée et les bords baissés (pli en V), et le limbe retombe vers sa pointe.
	const outline = [[0, 0], [.32, .96], [.68, .74], [1, 0]];
	// [azimut, inclinaison (positive vers le haut), longueur, retombée, roulis]
	const leaf = (base, [azimuth, pitch, length, droop, roll]) => {
		const width = length * .37;
		const point = (u, v) => {
			const y = .3 * width * (1 - Math.abs(v)) - droop * u * u * length, x = u * length, z = v * width;
			const yr = y * Math.cos(roll) - z * Math.sin(roll), zr = y * Math.sin(roll) + z * Math.cos(roll);
			const xp = x * Math.cos(pitch) - yr * Math.sin(pitch), yp = x * Math.sin(pitch) + yr * Math.cos(pitch);
			return add(base, [xp * Math.cos(azimuth) - zr * Math.sin(azimuth), yp, xp * Math.sin(azimuth) + zr * Math.cos(azimuth)]);
		};
		const tris = [];
		for(const side of [-1, 1]) {
			const p = outline.map(([u, v]) => point(u, side * v));
			const rib = u => point(u, 0);
			tris.push([rib(0), p[1], rib(.32)], [rib(.32), p[1], p[2]], [rib(.32), p[2], rib(.68)], [rib(.68), p[2], p[3]]);
		}
		return tris;
	};

	// Une suite fixe fait varier longueur et retombée d'une feuille à l'autre, pour qu'aucune ne soit la copie de sa voisine
	const jitter = [.92, 1.1, .97, 1.05, .88, 1.14, 1, .94, 1.09, .9, 1.03, 1.12, .95, 1.07, .89, 1.01, 1.13];
	const layers = { [DARK]: [], [LEAF]: [], [SHINE]: [] };
	let n = 0;
	const put = (base, [azimuth, pitch, length, droop, roll], color) => {
		const k = jitter[n++ % jitter.length];
		layers[color].push(...leaf(base, [azimuth, pitch, length * k, droop * (2 - k), roll]));
	};

	// Charpente : trois tiges qui partent du pied et s'ouvrent en vase, chacune en deux segments [azimut, rayon et hauteur
	// du coude, du bout], et une pousse centrale plus haute
	const FOOT = [0, 0, 0];
	const wood = [];
	const stems = [
		[Math.PI / 2 + .12, [.07, .2], [.15, .45]],
		[Math.PI * 7 / 6 - .1, [.08, .19], [.16, .42]],
		[Math.PI * 11 / 6 + .05, [.065, .21], [.14, .47]],
	];
	const crown = [-.01, .54, .015];
	wood.push(...tube(FOOT, crown, .012, .005));

	// Le long de chaque tige, de bas en haut, une feuille de chaque côté à tour de rôle : [place sur la tige (0 au pied,
	// 1 au coude, 2 au bout), écart d'azimut, inclinaison, longueur, retombée, couleur]. En bas et au milieu, les feuilles
	// suivent la tige vers l'extérieur et laissent libres les places des cloches ; plus haut, elles s'écartent au-dessus.
	const along = [
		[.4, .55, .12, .17, .55, DARK],
		[.85, -.6, .18, .17, .55, DARK],
		[1.25, .9, .08, .19, .6, LEAF],
		[1.55, -1, .12, .19, .6, LEAF],
		[2, .3, .15, .17, .5, SHINE],
		[1.78, -.35, .05, .17, .55, LEAF],
	];
	for(const [i, [azimuth, [r1, y1], [r2, y2]]] of stems.entries()) {
		const elbow = polar(azimuth, r1, y1), tip = polar(azimuth + .1 * (i - 1), r2, y2);
		wood.push(...tube(FOOT, elbow, .009, .007), ...tube(elbow, tip, .007, .005));
		const at = t => t <= 1 ? lerp(FOOT, elbow, t) : lerp(elbow, tip, t - 1);
		for(const [k, [t, shift, pitch, length, droop, color]] of along.entries())
			put(at(t), [azimuth + shift * (i === 1 ? -1 : 1), pitch + .04 * ((i + k) % 3 - 1), length, droop, .25 * (k % 2 ? 1 : -1)], i === 2 && color === LEAF ? (k === 2 ? DARK : LEAF) : color);
	}

	// Couronne basse : sept grandes feuilles qui partent du pied et retombent au ras du sol, tout autour, sous les cloches
	for(const [k, azimuth] of [.35, 1.25, 2.2, 3.05, 3.95, 4.9, 5.75].entries())
		put(polar(azimuth, .02, .045 + .01 * (k % 3)), [azimuth, .1 + .04 * (k % 2), .19, .35, .2 * (k % 2 ? 1 : -1)], DARK);

	// Sommet : trois feuilles qui s'ouvrent vers le ciel et prennent la lumière, le brillant du feuillage
	for(const [k, azimuth] of [.6, 2.75, 4.7].entries()) put(crown, [azimuth, .22 - .05 * k, .16, .45, .2 * (k % 2 ? 1 : -1)], SHINE);
	// Sur la pousse centrale, au-dessus des cloches : quatre feuilles qui s'étendent vers elles et les couvrent
	for(const [k, azimuth] of [.45, 2.65, 4.75, 3.7].entries()) put(lerp(FOOT, crown, .58 + .09 * k), [azimuth, .05 + .03 * k, .18, .6, .2 * (k % 2 ? -1 : 1)], LEAF);

	const parts = [
		{ color: LEAF, geometry: mesh(layers[LEAF]) },
		{ color: DARK, geometry: mesh(layers[DARK]) },
		{ color: SHINE, geometry: mesh(layers[SHINE]) },
		{ color: STEM, sway: .4, geometry: mesh(wood) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .48;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
