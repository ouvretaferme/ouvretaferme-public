// Figuier en cépée basse, pensé pour se lire sur un rang à 20 px/m : trois gros troncs gris clair coudés depuis le sol, une fourche
// chacun, et au bout de chaque rameau un bouquet de très grandes feuilles palmées tenues à plat. Pas de houppier : on voit le bois et
// le jour entre les bouquets. Les feuilles sont grossies (une feuille réelle de 20 cm ferait 4 px) et peu nombreuses.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : figue.reference.md.
function crown(THREE, helpers) {
	const LEAF = '#2f5a2c'; const MID = '#477a36'; const YOUNG = '#6b9a3e'; const BARK = '#c9c5ba';

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const V = (x, y, z) => new THREE.Vector3(x, y, z);
	const xyz = v => [v.x, v.y, v.z];
	const UP = V(0, 1, 0);
	const radial = (azimuth, radius, y) => V(Math.cos(azimuth) * radius, y, Math.sin(azimuth) * radius);

	// Anneau de `sides` pans autour d'un point, perpendiculaire à un axe
	const ring = (center, axis, r, sides) => {
		const side = V(.31, 0, .95).cross(axis).normalize();
		const up = V().crossVectors(axis, side);
		return Array.from({ length: sides }, (_, j) => {
			const a = j * Math.PI * 2 / sides;
			return center.clone().addScaledVector(side, Math.cos(a) * r).addScaledVector(up, Math.sin(a) * r);
		});
	};
	// Manchon entre deux anneaux de même nombre de pans
	const sleeve = (A, B) => A.flatMap((_, j) => {
		const k = (j + 1) % A.length;
		return [[xyz(A[j]), xyz(B[j]), xyz(A[k])], [xyz(A[k]), xyz(B[j]), xyz(B[k])]];
	});

	// Feuille palmée : chaque lobe est un cerf-volant depuis le point d'attache, le plus large aux trois quarts, donc à bout camus, séparé de ses voisins
	// par un sinus qui descend jusqu'à l'attache. `forward` est le lobe du milieu, `normal` le dessus du limbe ; les bouts retombent.
	const LOBES = {
		5: [[0, 1], [-.9, .88], [.9, .88], [-1.8, .62], [1.8, .62]],
		3: [[0, 1], [-.95, .8], [.95, .8]],
	};
	const leaf = (base, forward, normal, r, lobes) => {
		const across = V().crossVectors(normal, forward).normalize();
		const at = (angle, length, droop) => base.clone()
			.addScaledVector(forward, Math.cos(angle) * length)
			.addScaledVector(across, Math.sin(angle) * length)
			.addScaledVector(normal, -droop);
		return LOBES[lobes].flatMap(([angle, length]) => {
			const L = length * r, half = lobes === 5 ? .38 : .44;
			const left = xyz(at(angle - half, L * .78, 0)), right = xyz(at(angle + half, L * .78, 0)), tip = xyz(at(angle, L, r * .08));
			return [[xyz(base), left, tip], [xyz(base), tip, right]];
		});
	};

	// Les troncs : [azimut, dévers du coude, rayon et hauteur de la fourche, les rameaux [écart d'azimut, rayon et hauteur du bout]].
	// Le quatrième est un rejet court et couché, à un seul rameau : il élargit le pied et casse la symétrie par tiers.
	const TRUNKS = [
		[.35, .5, .74, 1.0, [[-.62, 1.3, 1.6], [.5, 1.1, 2.05]]],
		[2.5, -.55, .82, .9, [[-.5, 1.15, 1.9], [.66, 1.35, 1.5]]],
		[4.5, .45, .68, 1.1, [[-.56, 1.0, 2.1], [.6, 1.3, 1.8]]],
		[5.55, -.4, .85, .6, [[.1, 1.45, 1.1]]],
	];

	const wood = [], leaves = [], mid = [], young = [];
	let n = 0;
	for(const [i, [azimuth, swerve, forkRadius, forkY, limbs]] of TRUNKS.entries()) {
		const slim = limbs.length === 1 ? .7 : 1;
		const foot = radial(azimuth, .16, 0);
		// Le coude part de travers puis revient : le tronc est tortueux, pas un rayon droit
		const knee = radial(azimuth + swerve, forkRadius * .55, forkY * .5);
		const fork = radial(azimuth, forkRadius, forkY);
		const lower = knee.clone().sub(foot).normalize(), upper = fork.clone().sub(knee).normalize();
		const A = ring(foot, UP, .19 * slim, 4), B = ring(knee, lower.clone().add(upper).normalize(), .15 * slim, 4), C = ring(fork, upper, .12 * slim, 4);
		wood.push(...sleeve(A, B), ...sleeve(B, C));
		// Une feuille basse à la fourche du premier tronc : le feuillage ne se réduit pas à un plumet en tête
		if(i === 0) {
			const away = radial(azimuth - swerve, 1, 0);
			leaves.push(...leaf(fork.clone().addScaledVector(away, .2), away, UP.clone().addScaledVector(away, .4).normalize(), .8, 5));
		}

		for(const [turn, tipRadius, tipY] of limbs) {
			const tip = radial(azimuth + turn, tipRadius, tipY);
			const axis = tip.clone().sub(fork).normalize();
			wood.push(...sleeve(ring(fork, axis, .1 * slim, 3), ring(tip, axis, .055, 3)));

			// Trois feuilles alternes par bout de rameau, chacune au bout de son pétiole (un écart nu qui la détache du bois) : une
			// grande tournée vers le dehors, une grande et une jeune à trois lobes de part et d'autre, étagées le long du rameau
			for(let k = 0; k < 3; k++) {
				n++;
				const spin = azimuth + turn + [0, 2.0, -2.2][k] + .3 * Math.sin(n * 2.7);
				const away = radial(spin, 1, 0);
				const normal = UP.clone().addScaledVector(away, k ? .3 : .5).normalize();
				const forward = away.clone().addScaledVector(normal, -away.dot(normal)).normalize();
				const lobes = k === 2 ? 3 : 5;
				const r = (lobes === 5 ? .92 : .74) * (1 + .12 * Math.sin(n * 1.9));
				const base = tip.clone().addScaledVector(axis, [-.22, -.05, .12][k]).addScaledVector(away, .26).add(V(0, [-.06, .04, .12][k], 0));
				[leaves, mid, young][k].push(...leaf(base, forward, normal, r, lobes));
			}
		}
	}

	return { d: 3.5, h: 3, parts: [
		{ geometry: mesh(wood), color: BARK, sway: 0 },
		{ geometry: mesh(leaves), color: LEAF, sway: .5 },
		{ geometry: mesh(mid), color: MID, sway: .5 },
		{ geometry: mesh(young), color: YOUNG, sway: .5 },
	] };
}

// Le bois d'hiver : tronc, flèche, charpentières tendues jusqu'au bord du houppier et rameaux. Il remplace le bois de crown(), trop
// court pour porter l'arbre une fois les feuilles tombées. Calculé sur le houppier par verger/bois.html, recopié par bois.py :
// après une retouche du houppier, les deux se rejouent
const WOOD = [-0.228, 0.002, 0.076, -0.152, 0.707, 0.027, 0.076, 0.007, 0.228, 0.076, 0.007, 0.228, -0.152, 0.707, 0.027, 0.049, 0.71, 0.128, 0.076, 0.007, 0.228, 0.049, 0.71, 0.128, 0.228, 0, -0.076, 0.228, 0, -0.076, 0.049, 0.71, 0.128, 0.149, 0.704, -0.072, 0.228, 0, -0.076, 0.149, 0.704, -0.072, -0.076, 0, -0.228, -0.076, 0, -0.228, 0.149, 0.704, -0.072, -0.051, 0.701, -0.173, -0.076, 0, -0.228, -0.051, 0.701, -0.173, -0.228, 0.002, 0.076, -0.228, 0.002, 0.076, -0.051, 0.701, -0.173, -0.152, 0.707, 0.027, -0.115, 0.7, 0.017, -0.135, 1.525, -0.047, 0.089, 0.717, 0.056, 0.089, 0.717, 0.056, -0.135, 1.525, -0.047, -0.001, 1.536, -0.021, 0.089, 0.717, 0.056, -0.001, 1.536, -0.021, 0.022, 0.7, -0.14, 0.022, 0.7, -0.14, -0.001, 1.536, -0.021, -0.045, 1.525, -0.15, 0.022, 0.7, -0.14, -0.045, 1.525, -0.15, -0.115, 0.7, 0.017, -0.115, 0.7, 0.017, -0.045, 1.525, -0.15, -0.135, 1.525, -0.047, -0.135, 1.525, -0.047, -0.108, 2.203, -0.114, -0.001, 1.536, -0.021, -0.001, 1.536, -0.021, -0.108, 2.203, -0.114, -0.045, 1.525, -0.15, -0.045, 1.525, -0.15, -0.108, 2.203, -0.114, -0.135, 1.525, -0.047, -0.099, 0.557, 0.022, -0.021, 1.084, 0.719, 0.107, 0.565, 0.003, 0.107, 0.565, 0.003, -0.021, 1.084, 0.719, 0.116, 1.09, 0.707, 0.107, 0.565, 0.003, 0.116, 1.09, 0.707, -0.012, 0.707, -0.091, -0.012, 0.707, -0.091, 0.116, 1.09, 0.707, 0.037, 1.184, 0.644, -0.012, 0.707, -0.091, 0.037, 1.184, 0.644, -0.099, 0.557, 0.022, -0.099, 0.557, 0.022, 0.037, 1.184, 0.644, -0.021, 1.084, 0.719, -0.025, 1.093, 0.719, 0.09, 1.783, 1.402, 0.112, 1.087, 0.715, 0.112, 1.087, 0.715, 0.09, 1.783, 1.402, 0.046, 1.177, 0.636, 0.046, 1.177, 0.636, 0.09, 1.783, 1.402, -0.025, 1.093, 0.719, -0.012, 1.152, 0.702, 0.668, 2.059, 1.082, 0.071, 1.082, 0.737, 0.071, 1.082, 0.737, 0.668, 2.059, 1.082, 0.074, 1.124, 0.631, 0.074, 1.124, 0.631, 0.668, 2.059, 1.082, -0.012, 1.152, 0.702, 0.023, 1.421, 1.067, -0.185, 1.977, 1.262, 0.114, 1.46, 1.079, 0.114, 1.46, 1.079, -0.185, 1.977, 1.262, 0.065, 1.472, 0.993, 0.065, 1.472, 0.993, -0.185, 1.977, 1.262, 0.023, 1.421, 1.067, -0.041, 0.502, 0.014, -0.521, 0.961, 0.705, 0.101, 0.654, 0.023, 0.101, 0.654, 0.023, -0.521, 0.961, 0.705, -0.427, 1.061, 0.711, 0.101, 0.654, 0.023, -0.427, 1.061, 0.711, -0.063, 0.672, -0.104, -0.063, 0.672, -0.104, -0.427, 1.061, 0.711, -0.536, 1.073, 0.627, -0.063, 0.672, -0.104, -0.536, 1.073, 0.627, -0.041, 0.502, 0.014, -0.041, 0.502, 0.014, -0.536, 1.073, 0.627, -0.521, 0.961, 0.705, -0.534, 0.969, 0.708, -0.988, 1.632, 1.384, -0.425, 1.052, 0.713, -0.425, 1.052, 0.713, -0.988, 1.632, 1.384, -0.525, 1.076, 0.622, -0.525, 1.076, 0.622, -0.988, 1.632, 1.384, -0.534, 0.969, 0.708, -0.535, 0.986, 0.705, -1.296, 1.771, 0.751, -0.458, 1.068, 0.723, -0.458, 1.068, 0.723, -1.296, 1.771, 0.751, -0.491, 1.043, 0.616, -0.491, 1.043, 0.616, -1.296, 1.771, 0.751, -0.535, 0.986, 0.705, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.741, 1.332, 1.033, -0.067, 0.518, 0.019, -0.83, 1.015, -0.202, 0.032, 0.698, 0.052, 0.032, 0.698, 0.052, -0.83, 1.015, -0.202, -0.765, 1.134, -0.181, 0.032, 0.698, 0.052, -0.765, 1.134, -0.181, 0.031, 0.613, -0.138, 0.031, 0.613, -0.138, -0.765, 1.134, -0.181, -0.765, 1.077, -0.306, 0.031, 0.613, -0.138, -0.765, 1.077, -0.306, -0.067, 0.518, 0.019, -0.067, 0.518, 0.019, -0.765, 1.077, -0.306, -0.83, 1.015, -0.202, -0.837, 1.022, -0.201, -1.572, 1.713, -0.437, -0.755, 1.13, -0.182, -0.755, 1.13, -0.182, -1.572, 1.713, -0.437, -0.767, 1.074, -0.307, -0.767, 1.074, -0.307, -1.572, 1.713, -0.437, -0.837, 1.022, -0.201, -0.835, 1.038, -0.205, -1.236, 1.922, 0.179, -0.732, 1.087, -0.194, -0.732, 1.087, -0.194, -1.236, 1.922, 0.179, -0.792, 1.102, -0.29, -0.792, 1.102, -0.29, -1.236, 1.922, 0.179, -0.835, 1.038, -0.205, -1.231, 1.38, -0.313, -1.327, 1.647, -0.547, -1.148, 1.435, -0.307, -1.148, 1.435, -0.307, -1.327, 1.647, -0.547, -1.158, 1.367, -0.38, -1.158, 1.367, -0.38, -1.327, 1.647, -0.547, -1.231, 1.38, -0.313, -0.113, 0.639, 0.009, -0.171, 1.084, -0.774, 0.09, 0.681, 0.008, 0.09, 0.681, 0.008, -0.171, 1.084, -0.774, -0.036, 1.111, -0.774, 0.09, 0.681, 0.008, -0.036, 1.111, -0.774, 0.02, 0.509, -0.084, 0.02, 0.509, -0.084, -0.036, 1.111, -0.774, -0.083, 0.998, -0.835, 0.02, 0.509, -0.084, -0.083, 0.998, -0.835, -0.113, 0.639, 0.009, -0.113, 0.639, 0.009, -0.083, 0.998, -0.835, -0.171, 1.084, -0.774, -0.171, 1.08, -0.773, -0.192, 1.68, -1.566, -0.037, 1.109, -0.766, -0.037, 1.109, -0.766, -0.192, 1.68, -1.566, -0.081, 1.004, -0.844, -0.081, 1.004, -0.844, -0.192, 1.68, -1.566, -0.171, 1.08, -0.773, -0.153, 1.097, -0.783, 0.337, 1.998, -1.339, -0.047, 1.067, -0.75, -0.047, 1.067, -0.75, 0.337, 1.998, -1.339, -0.09, 1.029, -0.85, -0.09, 1.029, -0.85, 0.337, 1.998, -1.339, -0.153, 1.097, -0.783, -0.189, 1.343, -1.159, -0.676, 1.997, -1.437, -0.116, 1.41, -1.147, -0.116, 1.41, -1.147, -0.676, 1.997, -1.437, -0.128, 1.364, -1.235, -0.128, 1.364, -1.235, -0.676, 1.997, -1.437, -0.189, 1.343, -1.159, -0.067, 0.71, -0.022, 0.477, 1.023, -0.673, 0.094, 0.6, 0.049, 0.094, 0.6, 0.049, 0.477, 1.023, -0.673, 0.584, 0.951, -0.626, 0.094, 0.6, 0.049, 0.584, 0.951, -0.626, -0.031, 0.518, -0.095, -0.031, 0.518, -0.095, 0.584, 0.951, -0.626, 0.501, 0.897, -0.721, -0.031, 0.518, -0.095, 0.501, 0.897, -0.721, -0.067, 0.71, -0.022, -0.067, 0.71, -0.022, 0.501, 0.897, -0.721, 0.477, 1.023, -0.673, 0.468, 1.016, -0.669, 1.042, 1.465, -1.324, 0.583, 0.955, -0.625, 0.583, 0.955, -0.625, 1.042, 1.465, -1.324, 0.51, 0.899, -0.727, 0.51, 0.899, -0.727, 1.042, 1.465, -1.324, 0.468, 1.016, -0.669, 0.458, 0.965, -0.654, 0.327, 1.786, -1.636, 0.568, 0.996, -0.649, 0.568, 0.996, -0.649, 0.327, 1.786, -1.636, 0.536, 0.91, -0.717, 0.536, 0.91, -0.717, 0.327, 1.786, -1.636, 0.458, 0.965, -0.654, 0.755, 1.262, -1.001, 1.456, 1.545, -1.161, 0.803, 1.192, -0.949, 0.803, 1.192, -0.949, 1.456, 1.545, -1.161, 0.786, 1.179, -1.046, 0.786, 1.179, -1.046, 1.456, 1.545, -1.161, 0.755, 1.262, -1.001, -0.056, 0.716, -0.027, 0.741, 1.088, 0.195, 0.002, 0.548, 0.081, 0.002, 0.548, 0.081, 0.741, 1.088, 0.195, 0.779, 0.978, 0.266, 0.002, 0.548, 0.081, 0.779, 0.978, 0.266, 0.05, 0.564, -0.121, 0.05, 0.564, -0.121, 0.779, 0.978, 0.266, 0.811, 0.988, 0.133, 0.05, 0.564, -0.121, 0.811, 0.988, 0.133, -0.056, 0.716, -0.027, -0.056, 0.716, -0.027, 0.811, 0.988, 0.133, 0.741, 1.088, 0.195, 0.73, 1.082, 0.2, 1.555, 1.589, 0.419, 0.789, 0.976, 0.264, 0.789, 0.976, 0.264, 1.555, 1.589, 0.419, 0.812, 0.997, 0.13, 0.812, 0.997, 0.13, 1.555, 1.589, 0.419, 0.73, 1.082, 0.2, 0.714, 1.021, 0.218, 1.105, 1.96, 1.063, 0.819, 0.976, 0.228, 0.819, 0.976, 0.228, 1.105, 1.96, 1.063, 0.798, 1.056, 0.148, 0.798, 1.056, 0.148, 1.105, 1.96, 1.063, 0.714, 1.021, 0.218, 1.13, 1.348, 0.311, 1.65, 1.719, -0.078, 1.206, 1.296, 0.35, 1.206, 1.296, 0.35, 1.65, 1.719, -0.078, 1.163, 1.266, 0.264, 1.163, 1.266, 0.264, 1.65, 1.719, -0.078, 1.13, 1.348, 0.311];
export function build(THREE, helpers) {
	const model = crown(THREE, helpers), geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(WOOD, 3));
	geometry.computeVertexNormals();
	return { ...model, parts: [...model.parts.filter(part => part.sway !== 0), { geometry, color: '#9c9486', sway: 0 }] };
}
