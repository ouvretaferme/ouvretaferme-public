// Mûre cultivée, pensée pour l'écran de travail : un éventail lu canne par canne sur son palissage. Six cannes pourpres partent
// d'une seule souche, s'ouvrent en V des deux côtés et se couchent le long des trois fils du bas ; au centre, la botte des cannes
// neuves monte droit et se couche sur le fil du haut. Piquet et fils clairs barrent le V de lignes horizontales qui courent d'un plant
// à l'autre. Des feuilles à trois folioles, au bout des cannes et sur les latéraux, sortent du plan des fils de part et d'autre.
// Le V ouvert et les cannes couchées séparent la mûre du framboisier, bande de cannes dressées et parallèles.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : mure.reference.md. Les mûres sont posées par le socle.
export function build(THREE, helpers) {
	const SHADE = '#264a1f'; const LEAF = '#4a8636'; const CANE = '#7d3b4c'; const TRELLIS = '#cdbb94';

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };
	const up = new THREE.Vector3(0, 1, 0), front = new THREE.Vector3(0, 0, 1);

	// Mètres réels : 0,8 m le long du rang (x), 1,7 m de haut. Fils à 0,6, 0,9, 1,2 et 1,5 m, piquet au bout gauche
	const HALF = .42, WIRES = [.6, .9, 1.2, 1.5], POST = 1.7;

	// Ruban dans le plan du palissage, largeur effilée de la souche à la pointe : deux triangles par tronçon
	const ribbon = (path, w0, w1) => {
		const tris = [];
		for(let k = 0; k < path.length - 1; k++) {
			const [a, b] = [path[k], path[k + 1]];
			const across = new THREE.Vector3().subVectors(b, a).cross(front).normalize();
			const wa = w0 + (w1 - w0) * k / (path.length - 1), wb = w0 + (w1 - w0) * (k + 1) / (path.length - 1);
			const p = (v, w, s) => v.clone().addScaledVector(across, w * s).toArray();
			tris.push([p(a, wa, -1), p(b, wb, -1), p(a, wa, 1)], [p(a, wa, 1), p(b, wb, -1), p(b, wb, 1)]);
		}
		return mesh(tris);
	};

	// Cannes : chaque fruitière monte de la souche, se coude sous son fil et s'y couche jusqu'au bout du plant, où la pointe retombe.
	// Une par fil et par côté, sur les trois fils du bas ; les deux neuves montent droit au centre et se couchent sur le fil du haut.
	// C'est l'étagement couché qui sépare la mûre du framboisier, dont les cannes restent dressées. Coudes, longueurs et pointes
	// inégaux d'un côté à l'autre, parce qu'un éventail symétrique se lit en construction (grille du skill 3d)
	const fruiting = (side, y, bend, reach, fall, z) => ({ z, points: [[side * .02, 0], [side * bend, y - .02], [side * (bend + (reach - bend) * .6), y + .01], [side * reach, y - fall]], leaves: [.45, .68, .9] });
	const CANES = [
		fruiting(1, WIRES[0], .3, .44, .1, .02),
		fruiting(1, WIRES[1], .2, .43, .14, -.03),
		fruiting(1, WIRES[2], .12, .41, .08, .03),
		fruiting(-1, WIRES[0], .27, .43, .13, -.02),
		fruiting(-1, WIRES[1], .17, .44, .09, .03),
		fruiting(-1, WIRES[2], .1, .4, .12, -.03),
		{ z: .01, points: [[.01, 0], [.01, 1.35], [.07, 1.51], [.26, 1.52]], leaves: [.85], neuve: true },
		{ z: -.01, points: [[-.01, 0], [-.02, 1.38], [-.08, 1.5], [-.22, 1.49]], leaves: [], neuve: true },
	];
	const canes = CANES.map(({ z, points }) => points.map(([x, y]) => new THREE.Vector3(x, y, z * Math.min(1, y * 2))));
	const point = (path, t) => {
		const u = t * (path.length - 1), k = Math.min(Math.floor(u), path.length - 2);
		return path[k].clone().lerp(path[k + 1], u - k);
	};

	// Foliole ovale pointue, pliée sur sa nervure : quatre triangles en éventail depuis le milieu relevé de la nervure.
	// Construite pointe vers +z, face vers +y
	const leaflet = (length, width, rank) => {
		const w = width * (.9 + jitter(rank, 1) * .2);
		const B = [0, 0, 0], W1 = [-w, -w * .3, length * .38], T = [0, -length * .18, length], W2 = [w, -w * .3, length * .4], M = [0, length * .06, length * .45];
		return [[M, B, W1], [M, W1, T], [M, T, W2], [M, W2, B]];
	};
	// Feuille à trois folioles : la terminale plus longue, les latérales écartées de 45 à 60°, chacune sur un court pétiolule
	const leaf = (size, rank) => {
		const tris = [];
		const spread = .8 + jitter(rank, 2) * .25;
		for(const [k, angle] of [0, -spread, spread].entries()) {
			const length = size * (k === 0 ? 1 : .78 + jitter(rank, k + 3) * .1);
			const rot = new THREE.Matrix4().makeRotationY(angle + (jitter(rank, k + 6) - .5) * .15).setPosition(0, 0, k === 0 ? size * .12 : size * .05);
			for(const tri of leaflet(length, length * .34, rank * 3 + k)) tris.push(tri.map(v => new THREE.Vector3(...v).applyMatrix4(rot).toArray()));
		}
		return mesh(tris);
	};
	// Pose une feuille construite face +y, pointe +z, au point `at` : la pointe part selon `out` et penche de `droop`,
	// la face se relève de `lift` vers le ciel
	const place = (g, at, out, lift, droop) => {
		const normal = out.clone().addScaledVector(up, lift).normalize();
		const reach = out.clone().setY(-droop);
		reach.addScaledVector(normal, -reach.dot(normal)).normalize();
		const right = new THREE.Vector3().crossVectors(normal, reach);
		g.applyMatrix4(new THREE.Matrix4().makeBasis(right, normal, reach).setPosition(at));
		g.computeVertexNormals();
		return g;
	};

	// Feuilles : trois par canne fruitière, sur la partie couchée et la pointe qui retombe, portées par les latéraux qui sortent du
	// plan des fils, alternativement devant (claires) et derrière (sombres). Elles dessinent un étage de feuillage par fil.
	// Une seule sur les cannes neuves, au bout couché : leur botte nue au centre est le second geste de la conduite
	const light = [], shade = [];
	let rank = 0;
	for(const [c, path] of canes.entries()) {
		for(const [k, t] of CANES[c].leaves.entries()) {
			const at = point(path, t + (jitter(c, k + 20) - .5) * .06);
			const side = (c + k) % 2 ? 1 : -1;
			const out = new THREE.Vector3(Math.sign(at.x) * (.2 + jitter(c, k + 22) * .5), 0, side).normalize();
			const g = place(leaf(.19 * (.88 + jitter(c, k + 24) * .24), rank++), at.clone().setZ(at.z + side * .03), out, .4 + jitter(c, k + 26) * .5, .4);
			(side > 0 ? light : shade).push(g);
		}
	}

	// Palissage : un piquet carré au bout gauche, quatre fils d'un bout à l'autre du plant. Chaque fil est un ruban debout, vu
	// de face comme sous l'angle du jeu ; plus épais que le vrai fil pour tenir le pixel au rang
	const wire = y => {
		const t = .016;
		return mesh([
			[[-HALF, y - t, 0], [HALF, y - t, 0], [HALF, y + t, 0]], [[-HALF, y - t, 0], [HALF, y + t, 0], [-HALF, y + t, 0]],
		]);
	};
	const post = helpers.stem(.035, .045, POST, 4).rotateY(Math.PI / 4).translate(-HALF + .03, POST / 2, 0);

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: SHADE, sway: .3, geometry: merge(shade) },
		{ color: LEAF, sway: .8, geometry: merge(light) },
		{ color: CANE, sway: .2, geometry: merge(canes.map((path, c) => ribbon(path, CANES[c].neuve ? .016 : .02, .008))) },
		{ color: TRELLIS, sway: 0, geometry: merge([post, ...WIRES.map(wire)]) },
	];
	return { d: .8, h: POST, aligned: true, parts };
}
