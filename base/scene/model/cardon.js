// Cardon, pensé pour se lire sur un rang à 20 px/m : une grande touffe gris argenté dressée en vase, aussi haute que large,
// faite de longues feuilles découpées en segments étroits et dentés, hérissées d'épines jaune paille, qui partent d'une
// botte de côtes larges et pâles. Rien au-dessus : l'artichaut, son voisin, est une fontaine plus basse coiffée de têtes
// rondes ; ici c'est le feuillage lui-même qui monte jusqu'au sommet de la silhouette.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const SILVER = '#c3cfc6'; const GREY = '#8a9e90'; const RIB = '#e2e4cf'; const SPINE = '#c4a64e';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (aspect taillé)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Oriente un triangle pour que sa face regarde `up` : la lumière tombe pareil sur toutes les dents d'une feuille
	const facing = (p, q, r, up) => {
		const u = [q[0] - p[0], q[1] - p[1], q[2] - p[2]], v = [r[0] - p[0], r[1] - p[1], r[2] - p[2]];
		const n = [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
		return n[0] * up[0] + n[1] * up[1] + n[2] * up[2] >= 0 ? [p, q, r] : [p, r, q];
	};

	// Feuilles : [azimut, ouverture depuis la verticale, longueur, demi-largeur, retombée de la pointe]. Les trois
	// premières font le cœur, dressé, peint plus sombre pour creuser le vase ; les cinq suivantes s'ouvrent à 30-40° et
	// remplissent le vase, les quatre dernières s'ouvrent à 50-60° et ne retombent que par le dernier quart. Aucune ne
	// reprend l'angle, la longueur ou l'écart de sa voisine.
	const fronds = [
		[.3, .28, 1.04, .24, .5],
		[2.4, .38, .98, .23, .55],
		[4.4, .33, 1.02, .25, .5],
		[1.22, .56, .97, .27, .6],
		[2.53, .64, .94, .26, .66],
		[3.75, .52, 1, .28, .58],
		[5.06, .68, .95, .27, .62],
		[6.2, .6, .99, .26, .64],
		[.52, .92, .88, .25, .8],
		[2.06, 1.02, .84, .24, .86],
		[3.58, .88, .9, .26, .78],
		[5.24, .98, .86, .25, .84],
	];
	// La côte nue occupe le premier tronçon, le limbe les quatre suivants. Demi-largeur relative de chaque
	// segment de limbe, du bas à la pointe.
	const STALK = 1, BLADE = 4, SEGMENTS = STALK + BLADE;
	const lobes = [.8, 1, .95, .6];
	const silver = [], grey = [], ribs = [], spines = [];
	for(const [rank, [azimuth, open, length, width, droop]] of fronds.entries()) {
		const step = length / SEGMENTS;
		// Nervure : chaque tronçon s'incline un peu plus, la pointe seule retombe
		const rib = [[0, 0, 0]], dirs = [];
		for(let k = 0; k < SEGMENTS; k++) {
			const angle = open + droop * Math.pow((k + .5) / SEGMENTS, 2.4);
			const [x, y] = rib[k];
			dirs.push([Math.sin(angle), Math.cos(angle)]);
			rib.push([x + Math.sin(angle) * step, y + Math.cos(angle) * step, 0]);
		}
		const up = k => [-dirs[k][1], dirs[k][0], 0];

		// Côte : un ruban large et pâle qui s'effile vers le limbe
		const ribTris = [];
		for(let k = 0; k < STALK; k++) {
			const a = rib[k], b = rib[k + 1], wa = .07, wb = .05;
			const a0 = [a[0], a[1], -wa], a1 = [a[0], a[1], wa], b0 = [b[0], b[1], -wb], b1 = [b[0], b[1], wb];
			ribTris.push(facing(a0, b0, b1, up(k)), facing(a0, b1, a1, up(k)));
		}
		ribs.push(mesh(ribTris).rotateY(azimuth));

		// Limbe : par tronçon et par côté, un segment étroit qui file en avant et sur le côté, sous la nervure (pli en V),
		// et une dent qui sort de son bord avant. Les tronçons pairs portent une épine au bout du segment.
		const bladeTris = [], spineTris = [];
		for(let j = 0; j < BLADE; j++) {
			const k = STALK + j, [dx, dy] = dirs[k], a = rib[k], b = rib[k + 1];
			for(const side of [-1, 1]) {
				const jitter = 1 + .2 * Math.sin(rank * 2.3 + j * 1.7 + side * 1.1);
				const reach = width * lobes[j] * jitter, ahead = step * (.7 + .25 * Math.cos(rank + j * 2.1 + side));
				const tip = [b[0] + dx * ahead + dy * reach * .35, b[1] + dy * ahead - dx * reach * .35, side * reach];
				const mid = [(b[0] + tip[0]) / 2, (b[1] + tip[1]) / 2, (b[2] + tip[2]) / 2];
				const tooth = [mid[0] + dx * step * .5, mid[1] + dy * step * .5, mid[2] * .7];
				bladeTris.push(facing(a, b, tip, up(k)), facing(b, mid, tooth, up(k)));
				if(j % 2 === 0) {
					const spike = [tip[0] + dx * .05 + dy * .015, tip[1] + dy * .05 - dx * .015, tip[2] + side * .045];
					const base = [tip[0] - dx * .02, tip[1] - dy * .02, tip[2] - side * .012];
					spineTris.push(facing(tip, base, spike, up(k)));
				}
			}
		}
		// Segment terminal en pointe, épine au bout
		const [dx, dy] = dirs[SEGMENTS - 1], end = rib[SEGMENTS], w = width * .35;
		const tipEnd = [end[0] + dx * step * .6, end[1] + dy * step * .6, 0];
		bladeTris.push(facing([end[0], end[1], -w], [end[0], end[1], w], tipEnd, up(SEGMENTS - 1)));
		spineTris.push(facing(tipEnd, [tipEnd[0] - dy * .015, tipEnd[1] + dx * .015, .012], [tipEnd[0] + dx * .06, tipEnd[1] + dy * .06, 0], up(SEGMENTS - 1)));
		(rank < 3 ? grey : silver).push(mesh(bladeTris).rotateY(azimuth));
		spines.push(mesh(spineTris).rotateY(azimuth));
	}

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: SILVER, geometry: merge(silver) },
		{ color: GREY, geometry: merge(grey) },
		{ color: RIB, sway: .3, geometry: merge(ribs) },
		{ color: SPINE, geometry: merge(spines) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = 1.3;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
