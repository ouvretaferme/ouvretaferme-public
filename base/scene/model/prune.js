// Prunier demi-tige (quetsche, prune d'Ente), pensé pour se lire sur un rang à 20 px/m : un tronc droit, mince et presque noir,
// nu sur un bon mètre, trois charpentières dressées, et un houppier ovale, sombre et mat, resserré en bas et en haut, fait de
// masses inégales que hérissent les pousses claires de l'année. Les prunes bleu violet sont posées par le socle (FRUITS), sous
// le pourtour bas. C'est ce qui le sépare du pommier (bas, étalé, sommet plat, tronc court gris brun), du cerisier (plus grand,
// étagé), de l'abricotier (parasol luisant), du pêcher (gobelet creux et clair) et du mirabellier (rond et clair).
// Fiche : prune.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
function crown(THREE, helpers) {
	const { merge } = helpers;
	const TOP = '#487a38'; const SHADE = '#2f5a2e'; const SHOOT = '#6f9a48'; const BARK = '#2b2422';
	const V = (x, y, z) => new THREE.Vector3(x, y, z);
	// Hasard figé : le même arbre à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };

	// Tube effilé le long d'une suite de points, normales lissées par computeVertexNormals sur une géométrie indexée
	const tube = (points, radii, sides) => {
		const position = [], index = [];
		points.forEach((p, i) => {
			const axis = (points[i + 1] ?? p).clone().sub(points[i - 1] ?? p).normalize();
			const u = V(.31, 0, .95).cross(axis).normalize(), w = V().crossVectors(axis, u);
			for(let k = 0; k < sides; k++) {
				const a = k * Math.PI * 2 / sides;
				position.push(...p.clone().addScaledVector(u, Math.cos(a) * radii[i]).addScaledVector(w, Math.sin(a) * radii[i]).toArray());
			}
		});
		for(let s = 0; s < points.length - 1; s++) {
			for(let k = 0; k < sides; k++) {
				const a = s * sides + k, b = s * sides + (k + 1) % sides, c = a + sides, d = b + sides;
				index.push(a, b, c, b, d, c);
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setIndex(index);
		g.computeVertexNormals();
		return g;
	};

	// Le tronc : droit, mince, à peine évasé au pied, un rien sinueux. 20 triangles
	const fork = V(.04, 1.12, -.03);
	const trunk = tube([V(0, 0, 0), V(-.03, .55, .02), fork], [.17, .12, .11], 5);

	// Les trois charpentières dressées : [azimut, rayon du bout, hauteur du bout]. 6 triangles chacune
	const LIMBS = [[.5, .62, 1.95], [2.7, .7, 1.85], [4.6, .58, 2.0]];
	const limbs = LIMBS.map(([azimuth, radius, height]) => tube([fork.clone().add(V(0, -.05, 0)), V(Math.cos(azimuth) * radius, height, Math.sin(azimuth) * radius)], [.075, .045], 3));

	// Une masse de feuillage : un icosaèdre dont chaque sommet est poussé ou rentré d'après son rang, étiré en hauteur, aux normales
	// rayonnantes partagées par sommet, pour un contour bosselé sans facettes. 20 triangles
	const clump = (r, x, y, z, stretch, seed) => {
		const g = new THREE.IcosahedronGeometry(1, 0).rotateY(seed * 1.3).rotateX(seed * .37);
		const position = g.attributes.position, normal = [], p = V();
		for(let i = 0; i < position.count; i++) {
			p.fromBufferAttribute(position, i);
			// Les sommets confondus d'une face à l'autre reçoivent la même poussée : elle se tire de la position, pas du rang
			const bump = .82 + .36 * jitter(Math.round(p.x * 50) + Math.round(p.y * 50) * 7 + Math.round(p.z * 50) * 13, seed);
			normal.push(p.x, p.y / stretch, p.z);
			position.setXYZ(i, x + p.x * r * bump, y + p.y * r * bump * stretch, z + p.z * r * bump);
		}
		g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		g.normalizeNormals();
		return g;
	};
	const ringOf = (count, start, make) => Array.from({ length: count }, (_, i) => {
		const a = start + i * Math.PI * 2 / count + (jitter(i, start) - .5) * .35;
		return make(a, i);
	});

	// Le houppier est un œuf dressé, et non un dôme posé à plat : trois petites masses sombres resserrées sur les charpentières,
	// cinq grosses qui portent le plus grand diamètre vers 2,4 m, puis trois étirées en hauteur et une en pointe décentrée
	const skirt = ringOf(3, .5, (a, i) => clump(.5 + jitter(i, 2) * .08, Math.cos(a) * .5, 1.72 + jitter(i, 1) * .15, Math.sin(a) * .5, 1, i + 20));
	const belly = ringOf(5, .3, (a, i) => {
		const d = .92 + jitter(i, 3) * .16;
		return clump(.7 + jitter(i, 5) * .12, Math.cos(a) * d, 2.22 + jitter(i, 4) * .28, Math.sin(a) * d, 1.1, i + 1);
	});
	const crown = [
		...ringOf(3, 1.3, (a, i) => {
			const d = .36 + jitter(i, 8) * .14;
			return clump(.54 + jitter(i, 9) * .08, Math.cos(a) * d, 3.0 + jitter(i, 6) * .18, Math.sin(a) * d, 1.3, i + 7);
		}),
		clump(.42, .1, 3.62, -.08, 1.4, 12),
	];
	// Deux des masses du ventre prennent la lumière du haut : la limite entre les deux verts ne fait pas un étage
	const low = [...skirt, belly[0], belly[2], belly[3]];
	const high = [...crown, belly[1], belly[4]];

	// Les pousses de l'année : des lames pointues pliées sur leur nervure, plantées dans les masses et dressées vers le dehors.
	// [azimut, rayon du pied, hauteur du pied, ouverture depuis la verticale, longueur]. 2 triangles chacune
	const SHOOTS = [
		[.2, .12, 3.85, .1, .7], [2.2, .32, 3.55, .35, .8], [4.3, .36, 3.5, .4, .68],
		[.9, .8, 3.0, .6, .82], [3.0, .85, 2.9, .65, .7], [5.3, .8, 3.05, .55, .78],
		[1.7, 1.3, 2.5, .95, .7], [3.8, 1.35, 2.4, 1.05, .62], [5.9, 1.3, 2.55, .9, .72], [4.8, 1.2, 1.9, 1.35, .6],
	];
	const shoots = [];
	for(const [azimuth, radius, height, lean, length] of SHOOTS) {
		const out = V(Math.cos(azimuth), 0, Math.sin(azimuth));
		const foot = out.clone().multiplyScalar(radius).add(V(0, height, 0));
		const axis = out.clone().multiplyScalar(Math.sin(lean)).add(V(0, Math.cos(lean), 0));
		const side = V().crossVectors(axis, out).normalize();
		if(side.lengthSq() < .5) side.set(-out.z, 0, out.x);
		const tip = foot.clone().addScaledVector(axis, length);
		const belly = foot.clone().addScaledVector(axis, length * .38);
		const left = belly.clone().addScaledVector(side, length * .24).addScaledVector(out, -length * .06);
		const right = belly.clone().addScaledVector(side, -length * .24).addScaledVector(out, -length * .06);
		shoots.push(foot, tip, left, foot, right, tip);
	}
	const shootGeometry = new THREE.BufferGeometry().setFromPoints(shoots);
	shootGeometry.computeVertexNormals();

	return {
		d: 3.5, h: 4,
		parts: [
			{ color: TOP, sway: .12, geometry: merge(high) },
			{ color: SHADE, sway: .12, geometry: merge(low) },
			{ color: SHOOT, sway: .4, geometry: shootGeometry },
			{ color: BARK, sway: 0, geometry: merge([trunk, ...limbs]) },
		],
	};
}

// Le bois d'hiver : tronc, flèche, charpentières tendues jusqu'au bord du houppier et rameaux. Il remplace le bois de crown(), trop
// court pour porter l'arbre une fois les feuilles tombées. Calculé sur le houppier par verger/bois.html, recopié par bois.py :
// après une retouche du houppier, les deux se rejouent
const WOOD = [-0.161, 0, 0.054, -0.125, 1.206, 0.058, 0.128, 0, 0.112, 0.128, 0, 0.112, -0.125, 1.206, 0.058, 0.066, 1.208, 0.096, 0.128, 0, 0.112, 0.066, 1.208, 0.096, 0.033, 0.004, -0.167, 0.033, 0.004, -0.167, 0.066, 1.208, 0.096, 0.003, 1.21, -0.088, 0.033, 0.004, -0.167, 0.003, 1.21, -0.088, -0.161, 0, 0.054, -0.161, 0, 0.054, 0.003, 1.21, -0.088, -0.125, 1.206, 0.058, -0.099, 1.211, 0.049, 0.161, 4.265, 0.242, 0.044, 1.2, 0.079, 0.044, 1.2, 0.079, 0.161, 4.265, 0.242, -0.001, 1.213, -0.061, -0.001, 1.213, -0.061, 0.161, 4.265, 0.242, -0.099, 1.211, 0.049, 0.044, 2.906, 0.152, 0.618, 3.824, 0.653, 0.098, 2.864, 0.17, 0.098, 2.864, 0.17, 0.618, 3.824, 0.653, 0.098, 2.898, 0.108, 0.098, 2.898, 0.108, 0.618, 3.824, 0.653, 0.044, 2.906, 0.152, 0.041, 2.894, 0.155, -0.047, 3.927, -0.671, 0.11, 2.909, 0.163, 0.11, 2.909, 0.163, -0.047, 3.927, -0.671, 0.089, 2.866, 0.111, 0.089, 2.866, 0.111, -0.047, 3.927, -0.671, 0.041, 2.894, 0.155, -0.072, 1.081, 0.053, -1.17, 2.597, 0.835, 0.047, 1.166, 0.069, 0.047, 1.166, 0.069, -1.17, 2.597, 0.835, -0.031, 1.173, -0.055, -0.031, 1.173, -0.055, -1.17, 2.597, 0.835, -0.072, 1.081, 0.053, -0.636, 1.889, 0.446, -0.694, 3.166, 1.176, -0.555, 1.892, 0.452, -0.555, 1.892, 0.452, -0.694, 3.166, 1.176, -0.592, 1.927, 0.389, -0.592, 1.927, 0.389, -0.694, 3.166, 1.176, -0.636, 1.889, 0.446, -0.08, 1.091, 0.054, -1.528, 2.627, -0.607, 0.018, 1.198, 0.072, 0.018, 1.198, 0.072, -1.528, 2.627, -0.607, 0.006, 1.131, -0.058, 0.006, 1.131, -0.058, -1.528, 2.627, -0.607, -0.08, 1.091, 0.054, -0.818, 1.915, -0.277, -0.984, 2.813, -0.781, -0.741, 1.939, -0.266, -0.741, 1.939, -0.266, -0.984, 2.813, -0.781, -0.762, 1.898, -0.333, -0.762, 1.898, -0.333, -0.984, 2.813, -0.781, -0.818, 1.915, -0.277, -0.086, 1.191, 0.034, 0.797, 2.544, -1.359, 0.05, 1.149, 0.072, 0.05, 1.149, 0.072, 0.797, 2.544, -1.359, -0.02, 1.081, -0.039, -0.02, 1.081, -0.039, 0.797, 2.544, -1.359, -0.086, 1.191, 0.034, 0.345, 1.874, -0.653, 0.197, 2.652, -1.135, 0.422, 1.899, -0.644, 0.422, 1.899, -0.644, 0.197, 2.652, -1.135, 0.401, 1.855, -0.709, 0.401, 1.855, -0.709, 0.197, 2.652, -1.135, 0.345, 1.874, -0.653, -0.072, 1.206, 0.026, 1.304, 2.168, 0.826, -0.015, 1.085, 0.087, -0.015, 1.085, 0.087, 1.304, 2.168, 0.826, 0.031, 1.129, -0.046, 0.031, 1.129, -0.046, 1.304, 2.168, 0.826, -0.072, 1.206, 0.026, 0.598, 1.688, 0.439, 0.936, 3.298, 1.296, 0.676, 1.668, 0.45, 0.676, 1.668, 0.45, 0.936, 3.298, 1.296, 0.654, 1.708, 0.383, 0.654, 1.708, 0.383, 0.936, 3.298, 1.296, 0.598, 1.688, 0.439];
export function build(THREE, helpers) {
	const model = crown(THREE, helpers), geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(WOOD, 3));
	geometry.computeVertexNormals();
	return { ...model, parts: [...model.parts.filter(part => part.sway !== 0), { geometry, color: '#6b5646', sway: 0 }] };
}
