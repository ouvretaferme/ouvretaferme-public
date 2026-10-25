// Concombre, pensé pour se lire à une vingtaine de pixels de large : un mur étroit et haut, deux fois et demie plus haut
// que large, porté par un filet clair dont les poteaux et la traverse encadrent le feuillage. Sur les deux faces, de
// grandes feuilles triangulaires à lobes pointus pendent devant les mailles, pointe en bas, comme des boucliers. Peu de
// pièces, mais grosses, et le plus fort contraste entre le filet et la feuille : c'est parce qu'il grimpe que le concombre
// ne se lit ni en courgette, ni en melon, ni en pastèque, qui rampent ou font un vase au sol.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : concombre.reference.md. Les concombres
// mûrs sont posés par le socle (famille « palissé »), devant le filet, entre le quart et les trois cinquièmes de la hauteur.
export function build(THREE, helpers) {
	const NET = '#efe8d4'; const LEAF = '#3f8a2c'; const SHADE = '#2b6623'; const FLOWER = '#f6c51f';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (la nervure pliée prend deux lumières)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Tube à trois pans ouvert entre deux points
	const tube = (from, to, r) => {
		const a = new THREE.Vector3(...from), b = new THREE.Vector3(...to);
		const axis = b.clone().sub(a).normalize();
		const side = new THREE.Vector3(0, 0, 1).cross(axis);
		if(side.lengthSq() < 1e-6) side.set(1, 0, 0);
		side.normalize();
		const up = new THREE.Vector3().crossVectors(axis, side);
		const ring = center => [0, 1, 2].map(j => {
			const t = j * Math.PI * 2 / 3;
			return center.clone().addScaledVector(side, Math.cos(t) * r).addScaledVector(up, Math.sin(t) * r).toArray();
		});
		const A = ring(a), B = ring(b), tris = [];
		for(let j = 0; j < 3; j++) {
			const k = (j + 1) % 3;
			tris.push([A[j], B[j], A[k]], [A[k], B[j], B[k]]);
		}
		return tris;
	};

	// Filet tendu le long du rang (x), 1,2 m sur 0,45 : deux poteaux et une traverse assez gros pour se voir en jeu, trois
	// fils et deux montants fins pour les mailles du gros plan.
	const TOP = 2.67;
	const net = [
		...tube([-.5, 0, 0], [-.5, TOP + .06, 0], .034),
		...tube([.5, 0, 0], [.5, TOP + .06, 0], .034),
		...tube([-.5, TOP, 0], [.5, TOP, 0], .032),
		...[.72, 1.36, 2.02].flatMap(y => tube([-.5, y, 0], [.5, y, 0], .011)),
		...[-.17, .17].flatMap(x => tube([x, 0, 0], [x, TOP, 0], .009)),
	];

	// Liane : monte en zigzag dans les mailles et dépasse un peu la traverse
	const vinePath = [[.04, 0, 0], [.16, .62, .02], [-.14, 1.25, -.02], [.12, 1.86, .02], [-.1, 2.4, 0], [.04, 2.78, .01]];
	const vine = vinePath.slice(1).flatMap((p, i) => tube(vinePath[i], p, .018));

	// Limbe triangulaire ovale, base en cœur, cinq lobes peu profonds et pointus. Moitié du contour en (avancée le long de
	// la nervure depuis le pétiole, écart) : le lobe basal remonte derrière le point d'attache, d'où le cœur. Chaque
	// demi-feuille déplace ses pointes et ses sinus d'après son rang, pour qu'aucun limbe ne soit le décalque d'un autre.
	const outline = [[-.12, .46], [.18, .44], [.34, .66], [.56, .3], [1, 0]];
	const shift = [[.03, -.04, .05, -.03, 0], [-.04, .05, -.03, .04, 0], [.02, .03, -.05, -.02, 0], [-.03, -.02, .04, .05, 0]];
	// Construit nervure vers le bas (-y), face vers +z ; la nervure avance devant les bords (pli en V ouvert vers le filet)
	const blade = (length, width, rank) => {
		const point = (u, v) => [v * width * length, -u * length, .22 * width * length * (1 - Math.min(1, Math.abs(v) * 1.6))];
		const tris = [];
		for(const side of [-1, 1]) {
			const jitter = shift[(rank + (side > 0 ? 1 : 0)) % shift.length];
			const p = outline.map(([u, v], k) => point(u + jitter[k], side * v * (1 + jitter[k] * 1.5)));
			const rib = u => point(u, 0);
			const t = side > 0 ? (a, b, c) => [a, c, b] : (a, b, c) => [a, b, c];
			tris.push(t(rib(0), p[0], p[1]), t(rib(0), p[1], rib(.4)), t(rib(.4), p[1], p[2]), t(rib(.4), p[2], p[3]), t(rib(.4), p[3], p[4]));
		}
		return mesh(tris);
	};

	// Dix-huit feuilles réparties sur les deux faces et toute la hauteur. [face, hauteur du nœud, position le long du rang,
	// longueur, largeur relative, sortie du pétiole, bascule vers l'extérieur, roulis, lacet]. Les feuilles du bas et celles
	// qui restent collées au filet sont sombres : elles font la profondeur du mur.
	const fronds = [
		[1, .3, -.28, .41, 1.05, .13, .65, .2, -.25],
		[-1, .42, .22, .45, .98, .16, .55, -.3, .2],
		[1, .62, .3, .48, 1.1, .18, .7, -.15, .35],
		[-1, .78, -.3, .44, 1.02, .14, .6, .35, -.1],
		[1, .98, -.05, .5, .95, .12, .5, .1, .05],
		[-1, 1.12, .05, .46, 1.08, .17, .72, -.2, -.3],
		[1, 1.3, .34, .44, 1, .15, .58, .3, .15],
		[-1, 1.42, -.34, .49, .96, .18, .65, .15, .3],
		[1, 1.6, -.3, .45, 1.12, .16, .76, -.35, -.2],
		[-1, 1.76, .3, .51, 1, .13, .52, .25, .1],
		[1, 1.9, .08, .48, 1.04, .17, .62, .05, -.35],
		[-1, 2.06, -.06, .43, 1.1, .15, .7, -.25, .25],
		[1, 2.22, .32, .5, .97, .14, .54, .3, .2],
		[-1, 2.34, -.3, .45, 1.06, .18, .68, -.1, -.15],
		[1, 2.42, -.26, .49, 1, .16, .6, .2, .3],
		[-1, 2.5, .26, .46, 1.08, .13, .56, -.3, -.25],
		[1, 2.48, .3, .41, 1.02, .15, .8, -.1, .1],
		[-1, 2.56, -.3, .4, .95, .16, .82, .2, -.2],
	];
	const light = [], shade = [];
	for(const [rank, [face, y, x, length, width, reach, tilt, roll, yaw]] of fronds.entries()) {
		const g = blade(length, width * .95, rank)
			.rotateZ(roll)
			.rotateX(-tilt)
			.rotateY((face > 0 ? 0 : Math.PI) + yaw)
			.translate(x, y + length * .16, face * reach);
		(y < .9 || reach < .14 ? shade : light).push(g);
	}

	// Fleurs jaunes à cinq pétales, aux aisselles, tournées vers le dehors et un peu vers le ciel
	const flower = (r, rank) => {
		const rim = Array.from({ length: 10 }, (_, i) => {
			const a = i * Math.PI / 5 + rank, k = i % 2 ? .45 : 1;
			return [Math.cos(a) * r * k, Math.sin(a) * r * k, 0];
		});
		return mesh(rim.map((p, i) => [[0, 0, r * .25], p, rim[(i + 1) % 10]]));
	};
	const flowers = [[1, .95, .2, .3], [-1, 1.7, -.22, .5], [1, 2.35, -.12, .4]].map(([face, y, x, tilt], rank) =>
		flower(.075, rank).rotateX(-tilt).rotateY(face > 0 ? 0 : Math.PI).translate(x, y, face * .26));

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF, geometry: merge(light) },
		{ color: SHADE, geometry: merge([...shade, mesh(vine)]) },
		{ color: NET, sway: 0, geometry: mesh(net) },
		{ color: FLOWER, geometry: merge(flowers) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .45;
	return { d, h: d * size.y / Math.max(size.x, size.z), aligned: true, parts };
}
