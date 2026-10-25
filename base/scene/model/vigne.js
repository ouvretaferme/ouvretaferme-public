// Vigne en cordon bilatéral, pensée pour l'écran de travail : un T de bois sombre (cep court et tordu, deux bras couchés sur le fil
// porteur) sous un rideau droit de grandes feuilles à cinq lobes, qui ne commence qu'au-dessus des bras. Le pied reste nu : c'est là,
// de part et d'autre du fil porteur, que le socle pend les grappes. Piquet et fils clairs courent d'un plant à l'autre.
// Les feuilles sont grossies (une feuille réelle de 15 cm ferait 3 px) et peu nombreuses, pour se lire une par une.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : vigne.reference.md.
export function build(THREE, helpers) {
	const BARK = '#3a2a22'; const SHADE = '#3f7d2b'; const LEAF = '#6aa838'; const TRELLIS = '#cdbb94';

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };
	const V = (x, y, z) => new THREE.Vector3(x, y, z);
	const xyz = v => [v.x, v.y, v.z];
	const up = V(0, 1, 0), front = V(0, 0, 1);

	// Mètres réels : 1,5 m le long du rang (x), 1,8 m de haut. Fil porteur à 0,6 m, releveurs à 1,05 et 1,5 m, piquet au bout gauche
	const HALF = .75, WIRES = [.6, 1.05, 1.5], POST = 1.8;

	// Manchon de `sides` pans le long d'un chemin [x, y, z, rayon] : le bois du cep et des bras
	const ring = (center, axis, r, sides) => {
		const side = V(.31, .2, .93).cross(axis).normalize();
		const lift = V().crossVectors(axis, side);
		return Array.from({ length: sides }, (_, j) => {
			const a = j * Math.PI * 2 / sides;
			return center.clone().addScaledVector(side, Math.cos(a) * r).addScaledVector(lift, Math.sin(a) * r);
		});
	};
	const tube = (path, sides) => {
		const points = path.map(([x, y, z]) => V(x, y, z));
		const rings = points.map((p, k) => {
			const axis = points[Math.min(k + 1, points.length - 1)].clone().sub(points[Math.max(k - 1, 0)]).normalize();
			return ring(p, axis, path[k][3], sides);
		});
		const tris = [];
		for(let k = 0; k < rings.length - 1; k++) {
			const [A, B] = [rings[k], rings[k + 1]];
			for(let j = 0; j < sides; j++) {
				const n = (j + 1) % sides;
				tris.push([xyz(A[j]), xyz(B[j]), xyz(A[n])], [xyz(A[n]), xyz(B[j]), xyz(B[n])]);
			}
		}
		return mesh(tris);
	};

	// Le cep : épais au pied, deux coudes de travers et un renflement à la tête, d'où partent les bras. Les bras ondulent sur le fil
	// porteur et s'effilent, inégaux d'un côté à l'autre
	const trunk = tube([[0, 0, 0, .11], [.05, .22, .02, .08], [-.035, .42, -.015, .092], [0, .6, 0, .075]], 4);
	const arms = [
		tube([[0, .57, 0, .06], [.34, .64, .015, .045], [.7, .6, 0, .028]], 3),
		tube([[0, .58, 0, .06], [-.3, .615, -.015, .048], [-.66, .6, 0, .028]], 3),
	];

	// Sarments : un ruban dressé depuis chaque courson, dans le plan des fils, qui penche un peu et s'effile
	const ribbon = (path, w0, w1) => {
		const tris = [];
		for(let k = 0; k < path.length - 1; k++) {
			const [a, b] = [path[k], path[k + 1]];
			const across = V().subVectors(b, a).cross(front).normalize();
			const wa = w0 + (w1 - w0) * k / (path.length - 1), wb = w0 + (w1 - w0) * (k + 1) / (path.length - 1);
			const p = (v, w, s) => xyz(v.clone().addScaledVector(across, w * s));
			tris.push([p(a, wa, -1), p(b, wb, -1), p(a, wa, 1)], [p(a, wa, 1), p(b, wb, -1), p(b, wb, 1)]);
		}
		return mesh(tris);
	};
	// [pied sur le bras, dévers au fil du milieu, dévers et hauteur de la pointe]
	const SHOOTS = [[-.58, -.03, -.08, 1.66], [-.36, .04, .02, 1.78], [-.13, -.02, -.07, 1.6], [.11, .03, .08, 1.74], [.35, -.04, -.01, 1.63], [.6, .02, .07, 1.72]];
	const shoots = SHOOTS.map(([x, lean, tip, top]) => [V(x, .62, 0), V(x + lean, 1.15, 0), V(x + tip, top, 0)]);
	const point = (path, t) => {
		const u = t * (path.length - 1), k = Math.min(Math.floor(u), path.length - 2);
		return path[k].clone().lerp(path[k + 1], u - k);
	};

	// Feuille de vigne : cinq lobes pointus autour du point d'attache du pétiole, sinus peu profonds, échancrure ouverte entre les
	// deux lobes du bas. Éventail de huit triangles ; les pointes retombent et les sinus se relèvent, ce qui gaufre le limbe.
	// Contour en [angle depuis la pointe, rayon] ; construite pointe vers +z, face vers +y
	const OUTLINE = [[-2.5, .62], [-1.8, .56], [-1.12, .9], [-.56, .64], [0, 1.06], [.56, .64], [1.12, .9], [1.8, .56], [2.5, .62]];
	const leaf = (size, rank) => {
		const ring = OUTLINE.map(([a, r], k) => {
			const tip = k % 2 === 0;
			const R = size * r * (tip ? .88 + jitter(rank, k) * .24 : 1);
			const angle = a + (tip ? (jitter(rank, k + 10) - .5) * .16 : 0);
			return [Math.sin(angle) * R, tip ? -R * r * .16 : size * .04, Math.cos(angle) * R];
		});
		return mesh(ring.slice(0, -1).map((p, k) => [[0, 0, 0], ring[k + 1], p]));
	};
	// Pose une feuille au point `at` : la face regarde `out` relevé de `lift` vers le ciel, la pointe pend
	const place = (g, at, out, lift, turn) => {
		const normal = out.clone().addScaledVector(up, lift).normalize();
		const reach = V(turn, -1, 0);
		reach.addScaledVector(normal, -reach.dot(normal)).normalize();
		const right = V().crossVectors(normal, reach);
		g.applyMatrix4(new THREE.Matrix4().makeBasis(right, normal, reach).setPosition(at));
		g.computeVertexNormals();
		return g;
	};

	// Quatre feuilles alternes par sarment, de 1 m à la pointe, alternativement devant et derrière le plan des fils, plus deux
	// en tête du rideau. Aucune sous 0,9 m : le T de bois et la zone des grappes restent dégagés. Les basses et celles de
	// derrière sont sombres, les hautes franches.
	const light = [], shade = [];
	let rank = 0;
	const add = (at, side) => {
		rank++;
		const size = .225 * (.86 + jitter(rank, 30) * .28);
		at.x = Math.max(-HALF + size * .8, Math.min(HALF - size * .8, at.x));
		const out = V((jitter(rank, 31) - .5) * .7, 0, side).normalize();
		const g = place(leaf(size, rank), at.setZ(side * (.05 + jitter(rank, 32) * .06)), out, .5 + jitter(rank, 33) * .5, (jitter(rank, 34) - .5) * .7);
		(at.y > 1.2 + (side < 0 ? .25 : 0) + (jitter(rank, 35) - .5) * .3 ? light : shade).push(g);
	};
	for(const [s, path] of shoots.entries()) {
		for(let k = 0; k < 4; k++) {
			const at = point(path, .34 + k * .21 + (jitter(s, k + 40) - .5) * .08);
			const side = (s + k) % 2 ? 1 : -1;
			at.x += side * (k % 2 ? .07 : -.05) + (jitter(s, k + 44) - .5) * .1;
			add(at, side);
		}
	}
	add(V(-.02, 1.72, 0), 1);
	add(V(.5, 1.36, 0), -1);

	// Palissage : un piquet carré au bout gauche, trois fils d'un bout à l'autre du plant. Chaque fil est un ruban debout, plus
	// épais que le vrai fil pour tenir le pixel au rang
	const wire = y => {
		const t = .014;
		return mesh([
			[[-HALF, y - t, 0], [HALF, y - t, 0], [HALF, y + t, 0]], [[-HALF, y - t, 0], [HALF, y + t, 0], [-HALF, y + t, 0]],
		]);
	};
	const post = helpers.stem(.035, .045, POST, 4).rotateY(Math.PI / 4).translate(-HALF + .04, POST / 2, 0);

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: BARK, sway: 0, geometry: merge([trunk, ...arms, ...shoots.map(path => ribbon(path, .017, .008))]) },
		{ color: SHADE, sway: .4, geometry: merge(shade) },
		{ color: LEAF, sway: .7, geometry: merge(light) },
		{ color: TRELLIS, sway: 0, geometry: merge([post, ...WIRES.map(wire)]) },
	];
	return { d: 1.5, h: POST, aligned: true, parts };
}
