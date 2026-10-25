// Kiwi palissé en T, pensé pour se lire sur un rang à 20 px/m : deux poteaux clairs coiffés d'une traverse, un seul tronc brun
// tordu qui monte nu jusqu'au fil, un cordon couché de part et d'autre, et dessus un toit plat de grandes feuilles rondes en cœur,
// vert sombre, tenues à l'horizontale. Sous le toit il n'y a que le jour, le tronc et les poteaux : les kiwis y pendent, posés par
// le socle (FRUITS). C'est cette table portée haut qui le sépare du pommier (houppier en masses), du figuier (cépée à feuilles
// palmées) et du framboisier (mur vertical entre deux fils). Les feuilles sont grossies (une feuille réelle de 15 cm ferait 3 px).
// Fiche : kiwi.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
export function build(THREE, helpers) {
	const DARK = '#2c5a30'; const MID = '#4f8a3a'; const BARK = '#6b4a34'; const POST = '#bdb6a4';
	const V = (x, y, z) => new THREE.Vector3(x, y, z);

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };

	// Tube sur un chemin de points, `sides` pans, 2 × sides triangles par tronçon
	const tube = (path, radii, sides) => {
		const rings = path.map((p, k) => {
			const axis = path[Math.min(k + 1, path.length - 1)].clone().sub(path[Math.max(k - 1, 0)]).normalize();
			const u = V(.31, .2, .93).cross(axis).normalize(), w = V().crossVectors(axis, u);
			return Array.from({ length: sides }, (_, j) => {
				const a = j * Math.PI * 2 / sides;
				return p.clone().addScaledVector(u, Math.cos(a) * radii[k]).addScaledVector(w, Math.sin(a) * radii[k]).toArray();
			});
		});
		const tris = [];
		for(let k = 0; k < rings.length - 1; k++) {
			for(let j = 0; j < sides; j++) {
				const [a, b, c, d] = [rings[k][j], rings[k][(j + 1) % sides], rings[k + 1][(j + 1) % sides], rings[k + 1][j]];
				tris.push([a, b, c], [a, c, d]);
			}
		}
		return tris;
	};
	// Pointe à trois pans, de la base au bout : trois triangles, la base ouverte reste dans le bois d'où elle part
	const spur = (base, tip, r) => {
		const axis = tip.clone().sub(base).normalize();
		const u = V(0, 1, 0).cross(axis).normalize(), w = V().crossVectors(axis, u);
		const ring = [0, 1, 2].map(j => base.clone().addScaledVector(u, Math.cos(j * 2.094) * r).addScaledVector(w, Math.sin(j * 2.094) * r).toArray());
		return [0, 1, 2].map(j => [ring[j], ring[(j + 1) % 3], tip.toArray()]);
	};

	// Feuille en cœur : contour [écart, avancée] en fraction de la longueur, depuis le sinus où s'attache le pétiole. Deux oreilles
	// rondes en arrière du sinus, le plus large au tiers, un bout court. Éventail de six triangles, plié le long de la nervure
	const HEART = [[-.34, -.22], [-.6, .3], [-.36, .74], [0, .94], [.36, .74], [.6, .3], [.34, -.22]];
	const leaf = (at, yaw, size, droop, roll, rank) => {
		const m = new THREE.Matrix4().makeRotationFromEuler(new THREE.Euler(droop, yaw, roll, 'YXZ')).setPosition(at);
		const rim = HEART.map(([w, z], k) => {
			const grow = 1 + (jitter(rank, k) - .5) * .28;
			return V(w * size * grow, -Math.abs(w) * size * .22, (z - .4) * size * grow).applyMatrix4(m).toArray();
		});
		const heart = V(0, 0, -.4 * size).applyMatrix4(m).toArray();
		return rim.slice(1).map((p, k) => [heart, p, rim[k]]);
	};

	const WIRE_Y = 1.8, HALF = 1.32, ARM = .8;
	// Les feuilles s'arrêtent un peu avant les poteaux : leur demi-largeur déborde, et l'emprise doit rester celle du palissage
	const SPREAD = 1.2;

	// Le tronc : unique, tordu, nu du sol au fil central. 24 triangles
	const trunk = tube([V(.04, 0, .02), V(.17, .55, .1), V(-.12, 1.15, -.09), V(0, WIRE_Y, 0)], [.1, .085, .07, .06], 4);
	// Le cordon : deux bras couchés sur le fil central, inégaux, un peu ondulés. 12 triangles
	const cordon = [-1, 1].flatMap(side => tube([V(0, WIRE_Y - .02, 0), V(side * (HALF - .08), WIRE_Y + .03, side * .05)], [.06, .035], 3));
	// Les cannes : elles partent du cordon à angle droit, jusqu'au fil latéral. Le socle y pend les fruits. 18 triangles
	const CANES = [[-1.02, 1], [-.66, -1], [-.28, 1], [.2, -1], [.6, 1], [.98, -1]];
	const canes = CANES.flatMap(([x, side], i) => spur(V(x, WIRE_Y, 0), V(x + (jitter(i, 70) - .5) * .2, WIRE_Y - .03, side * (ARM - .05)), .03));

	// Le palissage : deux poteaux, leur traverse en T, les deux fils latéraux. 16 + 12 + 12 triangles
	const posts = [-1, 1].flatMap(side => [
		...tube([V(side * HALF, 0, 0), V(side * HALF, WIRE_Y, 0)], [.055, .05], 4),
		...tube([V(side * HALF, WIRE_Y, -ARM), V(side * HALF, WIRE_Y, ARM)], [.04, .04], 3),
		...tube([V(-HALF, WIRE_Y, side * (ARM - .05)), V(HALF, WIRE_Y, side * (ARM - .05))], [.012, .012], 3),
	]);

	// Le toit : trois files de feuilles le long du rang, une sur le cordon et une sur chaque fil latéral, décalées en quinconce.
	// Celles du milieu sont à plat et hautes, celles des bords pointent vers le dehors et retombent : la nappe a une épaisseur et un
	// bord frangé, pas une arête. 34 feuilles, 204 triangles
	const dark = [], mid = [];
	const ROWS = [[0, 12], [-1, 11], [1, 11]];
	let rank = 0;
	for(const [side, count] of ROWS) {
		for(let i = 0; i < count; i++) {
			rank++;
			const x = -SPREAD + (i + .5 + (jitter(rank, 20) - .5) * .5) * (2 * SPREAD / count);
			const z = side * (.6 + (jitter(rank, 21) - .5) * .22) + (side ? 0 : (jitter(rank, 22) - .5) * .5);
			const y = WIRE_Y + (side ? .04 : .16) + (jitter(rank, 23) - .5) * .12;
			const size = (.5 + jitter(rank, 24) * .14) * (side ? 1 : .94);
			const yaw = side ? (side > 0 ? 0 : Math.PI) + (jitter(rank, 25) - .5) * 1.5 : jitter(rank, 25) * 6.28;
			const droop = side ? .3 + jitter(rank, 26) * .45 : (jitter(rank, 26) - .5) * .3;
			const roll = (jitter(rank, 27) - .5) * .4;
			((rank * 5 + (side ? 0 : 1)) % (side ? 4 : 2) === 0 ? mid : dark).push(...leaf(V(x, y, z), yaw, size, droop, roll, rank));
		}
	}

	const parts = [
		{ color: DARK, sway: .35, geometry: mesh(dark) },
		{ color: MID, sway: .35, geometry: mesh(mid) },
		{ color: BARK, sway: 0, geometry: mesh([...trunk, ...cordon, ...canes]) },
		{ color: POST, sway: 0, geometry: mesh(posts) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = 3;
	return { d, h: d * size.y / Math.max(size.x, size.z), aligned: true, parts };
}
