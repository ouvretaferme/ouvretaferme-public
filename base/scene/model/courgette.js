// Courgette, pensée pour se lire à une cinquantaine de pixels : un dôme large de douze très grandes feuilles palmées à cinq
// lobes, levées sur de longs pétioles clairs qui partent tous du même cœur. Le pied ne court pas : l'emprise est ronde,
// le dessous reste ouvert sur la gerbe des pétioles et laisse sortir les courgettes que le socle couche au bord.
// Le plus fort contraste est entre le limbe sombre et les pétioles clairs. Fiche : courgette.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#3d7a2e'; const PETIOLE = '#a6c46e'; const SILVER = '#c3d4ad'; const FLOWER = '#f4b41a';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (aspect taillé)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };
	const spread = (i, k, amount) => 1 + (jitter(i, k) - .5) * 2 * amount;

	// Limbe palmé, taillé en éventail depuis le point d'attache du pétiole, d'où partent les cinq nervures. Moitié droite du
	// contour en [angle depuis la pointe, rayon] : lobe terminal, sinus, lobe latéral, sinus, lobe basal, sinus, oreillette
	// de la base en cœur, qui passe un peu derrière le point d'attache. Les pointes des lobes retombent, les sinus restent
	// hauts, ce qui plisse le limbe le long des nervures au lieu d'en faire une plaque.
	const HALF = [[0, 1, 1], [30, .72, 0], [55, .92, 1], [85, .66, 0], [112, .72, 1], [145, .48, 0], [170, .3, 0]];
	const blade = (rank, length) => {
		const half = HALF.map(([a, r, tip], k) => [a * spread(rank, k, .08), r * spread(rank, k + 7, .12), tip]);
		const ring = [half[0], ...half.slice(1), ...half.slice(1).reverse().map(([a, r, tip]) => [-a, r, tip])];
		const point = ([a, r, tip]) => {
			const t = a * Math.PI / 180, rr = r * length;
			return [Math.sin(t) * rr, -tip * .08 * length * r - .06 * rr * rr / length, Math.cos(t) * rr];
		};
		const center = [0, .02 * length, 0];
		return ring.map((p, i) => [center, point(p), point(ring[(i + 1) % ring.length])]);
	};
	// Marbrure argentée : une étoile basse qui suit les trois nervures du haut, posée juste au-dessus du limbe, dont elle
	// reprend la pente du point d'attache vers la pointe des lobes
	const MARBLE = [[0, .48], [26, .14], [52, .4], [80, .12], [-80, .12], [-52, .4], [-26, .14]];
	const marble = length => {
		const point = ([a, r]) => [Math.sin(a * Math.PI / 180) * r * length, (.02 - .2 * r) * length + .006, Math.cos(a * Math.PI / 180) * r * length];
		const center = [0, .02 * length + .006, 0];
		return MARBLE.map((p, i) => [center, point(p), point(MARBLE[(i + 1) % MARBLE.length])]);
	};

	// Douze feuilles en spirale (angle d'or), du cœur dressé au tour couché. Chacune varie en azimut, en ouverture du
	// pétiole depuis la verticale, en longueur et en inclinaison du limbe. [ouverture, pétiole, limbe, inclinaison du limbe]
	const COUNT = 12, GOLDEN = 2.39996;
	const leaves = [], marbles = [], petioles = [];
	for(let i = 0; i < COUNT; i++) {
		const ring = i / (COUNT - 1);
		const azimuth = i * GOLDEN + (jitter(i, 3) - .5) * .4;
		const open = (.4 + ring * .85) * spread(i, 4, .12);
		const petiole = (.27 + ring * .07) * spread(i, 5, .1);
		const length = .34 * spread(i, 6, .14);
		const pitch = (.45 - ring * .7) * spread(i, 7, .15);
		const foot = [0, .05, .025];
		const top = [0, foot[1] + Math.cos(open) * petiole, foot[2] + Math.sin(open) * petiole];
		// Pétiole : prisme ouvert à trois pans, plus gros au pied
		const tube = new THREE.Vector3(...top).sub(new THREE.Vector3(...foot));
		petioles.push(helpers.stem(.012, .02, tube.length(), 3).translate(0, tube.length() / 2, 0)
			.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), tube.normalize()))
			.translate(...foot).rotateY(azimuth));
		// Le limbe se pose à plat, pointe vers l'extérieur, puis se relève de `pitch` (rotateX négatif lève la pointe)
		leaves.push(mesh(blade(i, length)).rotateX(-pitch).translate(...top).rotateY(azimuth));
		if(i < 7) marbles.push(mesh(marble(length)).rotateX(-pitch).translate(...top).rotateY(azimuth));
	}

	// Deux fleurs en trompette au cœur, longues pour partir du pied et sortir au-dessus des feuilles dressées, dans le creux du dôme
	const flowers = [[.4, .24, .22], [3.3, .21, .4]].map(([azimuth, y, lean]) =>
		helpers.stem(.07, .01, .26, 5).translate(0, y, 0).rotateX(lean).translate(0, 0, .05).rotateY(azimuth));

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF, geometry: merge(leaves) },
		{ color: PETIOLE, sway: .6, geometry: merge(petioles) },
		{ color: SILVER, geometry: merge(marbles) },
		{ color: FLOWER, geometry: merge(flowers) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = 1;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
