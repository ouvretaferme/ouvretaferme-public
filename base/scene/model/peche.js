// Pêcher en gobelet bas, pensé pour se lire sur un rang à 20 px/m : un tronc très court, quatre charpentières ouvertes en entonnoir
// autour d'un centre vide, des rameaux d'un an rougeâtres qui les prolongent, et un feuillage fait de longues lames étroites,
// courbées en faux, pliées en gouttière, qui pendent vers le dehors. Aucune masse ronde : c'est la mèche effilée et retombante qui
// le sépare de l'abricotier et du pommier (plaques et boules), le centre creux et le tronc court qui le séparent du saule, et le
// port bas et évasé de l'amandier et du prunier. Les pêches sont posées par le socle (FRUITS) le long des rameaux.
// Fiche : peche.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
function crown(THREE) {
	const TOP = '#9cc653'; const SHADE = '#5e9440'; const BARK = '#75604f'; const SHOOT = '#a24a3c';
	const groups = Object.fromEntries([TOP, SHADE, BARK, SHOOT].map(color => [color, { position: [], normal: [] }]));
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const up = v(0, 1, 0);
	// Tout le modèle est écrit en mètres pour une emprise de 3,5 m, puis étiré de 1,16 en hauteur ici, pour que le rapport de la
	// hauteur à l'emprise soit celui de la fiche (2,8 m pour 3 m)
	const triangle = (color, points) => {
		const p = points.map(q => v(q.x, q.y * 1.16, q.z));
		const n = new THREE.Vector3().subVectors(p[1], p[0]).cross(new THREE.Vector3().subVectors(p[2], p[0])).normalize();
		for(const q of p) {
			groups[color].position.push(...q.toArray());
			groups[color].normal.push(...n.toArray());
		}
	};
	// Hasard figé : le même arbre à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };
	const frame = axis => {
		const u = new THREE.Vector3().crossVectors(axis, Math.abs(axis.y) > .9 ? v(1, 0, 0) : up).normalize();
		return [u, new THREE.Vector3().crossVectors(axis, u).normalize()];
	};

	// Bois : un tube à trois pans par segment. Un rayon nul au bout ferme le tube en pointe, et ses triangles dégénérés ne s'écrivent pas
	const wood = (color, points, radii, phase) => {
		const rings = points.map((p, i) => {
			const axis = (points[i + 1] ?? p).clone().sub(points[i - 1] ?? p).normalize();
			const [u, w] = frame(axis);
			return [0, 1, 2].map(k => p.clone().addScaledVector(u, radii[i] * Math.cos(phase + k * 2.094)).addScaledVector(w, radii[i] * Math.sin(phase + k * 2.094)));
		});
		for(let s = 0; s < points.length - 1; s++) {
			for(let k = 0; k < 3; k++) {
				const a = rings[s][k], b = rings[s][(k + 1) % 3], c = rings[s + 1][(k + 1) % 3], d = rings[s + 1][k];
				triangle(color, [a, c, b]);
				if(radii[s + 1] > 0) triangle(color, [a, d, c]);
			}
		}
	};

	// Une feuille en lame de faux : la nervure part de `base` selon `dir`, tombe de `droop` et dévie de côté de `hook`, ce qui la
	// courbe dans son plan ; les deux bords, relevés au-dessus de la nervure, la plient en gouttière. Plus large au premier tiers,
	// longue pointe. 4 triangles
	const leaf = (color, base, dir, length, droop, hook) => {
		const side = new THREE.Vector3().crossVectors(dir, up).normalize();
		const along = t => base.clone().addScaledVector(dir, t * length).add(v(0, -droop * t * t * length, 0)).addScaledVector(side, hook * t * t * length);
		const mid = along(.4), tip = along(1);
		const forward = along(.5).sub(along(.3)).normalize();
		const across = new THREE.Vector3().crossVectors(forward, up).normalize();
		const lift = new THREE.Vector3().crossVectors(across, forward).normalize().multiplyScalar(length * .06);
		const left = mid.clone().addScaledVector(across, length * .15).add(lift);
		const right = mid.clone().addScaledVector(across, -length * .15).add(lift);
		triangle(color, [base, left, mid]);
		triangle(color, [base, mid, right]);
		triangle(color, [left, tip, mid]);
		triangle(color, [mid, tip, right]);
	};

	// Tronc court, un peu évasé au pied
	wood(BARK, [v(0, 0, 0), v(.02, .5, -.01)], [.13, .1], .4);

	// Les quatre charpentières : [azimut, rayon du coude, hauteur du coude, rayon de la tête, hauteur de la tête]. Inégales, pour que
	// le bord du gobelet ne soit pas une couronne régulière
	const LIMBS = [
		[.35, .52, 1.1, .82, 1.8], [1.85, .6, 1.15, .94, 1.78], [3.5, .5, 1.05, .78, 1.7], [4.9, .56, 1.15, .86, 1.9],
	];
	let rank = 0;
	LIMBS.forEach(([azimuth, kneeRadius, kneeHeight, headRadius, headHeight], i) => {
		const dir = (r, y, turn = 0) => v(Math.cos(azimuth + turn) * r, y, Math.sin(azimuth + turn) * r);
		const foot = v(.02, .46, -.01), knee = dir(kneeRadius, kneeHeight), head = dir(headRadius, headHeight);
		wood(BARK, [foot, knee, head], [.075, .055, .035], azimuth);
		// Deux rameaux d'un an par charpentière, effilés en pointe : l'un prolonge la tête et monte, l'autre part du milieu et s'arque
		// plus bas vers le dehors
		const shoots = [
			[head, dir(headRadius + .2, headHeight + .8 + jitter(i, 1) * .12, .3), i < 3 ? 6 : 5, 0],
			[knee.clone().lerp(head, .45), dir(headRadius + .32, headHeight + .2 + jitter(i, 2) * .2, -.5), 5, 1],
		];
		for(const [from, to, count, low] of shoots) {
			wood(SHOOT, [from, to], [.035, 0], azimuth + 1);
			const heading = Math.atan2(to.z - from.z, to.x - from.x);
			// Feuilles alternes le long du rameau, tournées d'un côté puis de l'autre, toujours vers le dehors ; celle du bout part en
			// avant. Les basses et celles du rameau du dessous sont dans l'ombre
			for(let k = 0; k < count; k++) {
				const t = .2 + .8 * k / (count - 1);
				const at = from.clone().lerp(to, t);
				const last = k === count - 1;
				const turn = last ? (jitter(rank, 3) - .5) * .5 : (k % 2 ? 1 : -1) * (.7 + jitter(rank, 4) * .5);
				const a = heading * .4 + azimuth * .6 + turn;
				const rise = last ? .2 : .1 + jitter(rank, 5) * .25;
				const direction = v(Math.cos(a), rise, Math.sin(a)).normalize();
				const color = (low && k < 3) || (!low && k === 0) ? SHADE : TOP;
				leaf(color, at, direction, .57 + jitter(rank, 6) * .18, .85 + jitter(rank, 7) * .5, (k % 2 ? 1 : -1) * (.12 + jitter(rank, 8) * .14));
				rank++;
			}
		}
		// Trois feuilles sur le haut de la charpentière, qui ferment le dessous du gobelet sans combler le centre
		for(let k = 0; k < 3; k++) {
			const at = knee.clone().lerp(head, .2 + k * .3);
			const a = azimuth + (k - 1) * 1.1 + (jitter(rank, 9) - .5) * .3;
			leaf(SHADE, at, v(Math.cos(a), .05, Math.sin(a)).normalize(), .6 + jitter(rank, 6) * .15, 1.1 + jitter(rank, 7) * .3, (k % 2 ? 1 : -1) * .18);
			rank++;
		}
	});

	const part = (color, sway) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(groups[color].position, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(groups[color].normal, 3));
		return { geometry, color, sway };
	};
	// Le bois de charpente ne bouge pas ; les rameaux d'un an sont souples et leurs feuilles pendent
	const parts = [part(BARK, 0), part(SHOOT, .3), part(TOP, .5), part(SHADE, .5)];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const { geometry } of parts) {
		geometry.computeBoundingBox();
		box.union(geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = 3;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}

// Le bois d'hiver : tronc, flèche, charpentières tendues jusqu'au bord du houppier et rameaux. Il remplace le bois de crown(), trop
// court pour porter l'arbre une fois les feuilles tombées. Calculé sur le houppier par verger/bois.html, recopié par bois.py :
// après une retouche du houppier, les deux se rejouent
const WOOD = [-0.123, 0, 0.042, -0.1, 1.006, 0.05, 0.098, 0, 0.086, 0.098, 0, 0.086, -0.1, 1.006, 0.05, 0.046, 1.008, 0.079, 0.098, 0, 0.086, 0.046, 1.008, 0.079, 0.025, 0.003, -0.127, 0.025, 0.003, -0.127, 0.046, 1.008, 0.079, -0.002, 1.01, -0.062, 0.025, 0.003, -0.127, -0.002, 1.01, -0.062, -0.123, 0, 0.042, -0.123, 0, 0.042, -0.002, 1.01, -0.062, -0.1, 1.006, 0.05, -0.055, 1.01, 0.023, 0.454, 1.322, 0.697, -0.035, 0.905, 0.059, -0.035, 0.905, 0.059, 0.454, 1.322, 0.697, 0.467, 1.252, 0.721, -0.035, 0.905, 0.059, 0.467, 1.252, 0.721, 0.034, 0.953, -0.015, 0.034, 0.953, -0.015, 0.467, 1.252, 0.721, 0.513, 1.284, 0.672, 0.034, 0.953, -0.015, 0.513, 1.284, 0.672, -0.055, 1.01, 0.023, -0.055, 1.01, 0.023, 0.513, 1.284, 0.672, 0.454, 1.322, 0.697, 0.443, 1.31, 0.703, 0.974, 1.825, 1.371, 0.484, 1.251, 0.72, 0.484, 1.251, 0.72, 0.974, 1.825, 1.371, 0.506, 1.297, 0.666, 0.506, 1.297, 0.666, 0.974, 1.825, 1.371, 0.443, 1.31, 0.703, 0.449, 1.308, 0.702, 1.412, 2.546, 0.653, 0.496, 1.273, 0.726, 0.496, 1.273, 0.726, 1.412, 2.546, 0.653, 0.488, 1.277, 0.662, 0.488, 1.277, 0.662, 1.412, 2.546, 0.653, 0.449, 1.308, 0.702, -0.03, 0.894, 0.039, -0.6, 1.216, 0.731, 0.031, 0.988, 0.049, 0.031, 0.988, 0.049, -0.6, 1.216, 0.731, -0.559, 1.278, 0.737, 0.031, 0.988, 0.049, -0.559, 1.278, 0.737, -0.057, 0.985, -0.022, -0.057, 0.985, -0.022, -0.559, 1.278, 0.737, -0.617, 1.276, 0.691, -0.057, 0.985, -0.022, -0.617, 1.276, 0.691, -0.03, 0.894, 0.039, -0.03, 0.894, 0.039, -0.617, 1.276, 0.691, -0.6, 1.216, 0.731, -0.609, 1.22, 0.733, -1.165, 1.781, 1.417, -0.556, 1.272, 0.738, -0.556, 1.272, 0.738, -1.165, 1.781, 1.417, -0.611, 1.279, 0.688, -0.611, 1.279, 0.688, -1.165, 1.781, 1.417, -0.609, 1.22, 0.733, -0.616, 1.233, 0.733, -1.128, 1.834, 0.781, -0.57, 1.275, 0.743, -0.57, 1.275, 0.743, -1.128, 1.834, 0.781, -0.59, 1.263, 0.683, -0.59, 1.263, 0.683, -1.128, 1.834, 0.781, -0.616, 1.233, 0.733, -0.057, 0.909, 0.045, -0.542, 1.181, -0.267, -0.011, 1.011, 0.056, -0.011, 1.011, 0.056, -0.542, 1.181, -0.267, -0.512, 1.249, -0.259, -0.011, 1.011, 0.056, -0.512, 1.249, -0.259, 0.012, 0.948, -0.034, 0.012, 0.948, -0.034, -0.512, 1.249, -0.259, -0.497, 1.207, -0.319, 0.012, 0.948, -0.034, -0.497, 1.207, -0.319, -0.057, 0.909, 0.045, -0.057, 0.909, 0.045, -0.497, 1.207, -0.319, -0.542, 1.181, -0.267, -0.547, 1.186, -0.266, -1.016, 1.6, -0.586, -0.505, 1.247, -0.259, -0.505, 1.247, -0.259, -1.016, 1.6, -0.586, -0.5, 1.205, -0.32, -0.5, 1.205, -0.32, -1.016, 1.6, -0.586, -0.547, 1.186, -0.266, -0.542, 1.189, -0.268, -1.331, 2.201, -0.054, -0.492, 1.228, -0.259, -0.492, 1.228, -0.259, -1.331, 2.201, -0.054, -0.517, 1.221, -0.318, -0.517, 1.221, -0.318, -1.331, 2.201, -0.054, -0.542, 1.189, -0.268, -0.048, 1.014, 0.02, 0.595, 1.3, -0.427, 0.022, 0.942, 0.071, 0.022, 0.942, 0.071, 0.595, 1.3, -0.427, 0.642, 1.253, -0.393, 0.022, 0.942, 0.071, 0.642, 1.253, -0.393, -0.03, 0.911, -0.024, -0.03, 0.911, -0.024, 0.642, 1.253, -0.393, 0.607, 1.233, -0.456, -0.03, 0.911, -0.024, 0.607, 1.233, -0.456, -0.048, 1.014, 0.02, -0.048, 1.014, 0.02, 0.607, 1.233, -0.456, 0.595, 1.3, -0.427, 0.589, 1.296, -0.424, 1.248, 1.757, -0.873, 0.643, 1.255, -0.394, 0.643, 1.255, -0.394, 1.248, 1.757, -0.873, 0.613, 1.235, -0.459, 0.613, 1.235, -0.459, 1.248, 1.757, -0.873, 0.589, 1.296, -0.424, 0.59, 1.289, -0.423, 1.538, 2.065, -0.228, 0.624, 1.243, -0.395, 0.624, 1.243, -0.395, 1.538, 2.065, -0.228, 0.63, 1.253, -0.458, 0.63, 1.253, -0.458, 1.538, 2.065, -0.228, 0.59, 1.289, -0.423];
export function build(THREE, helpers) {
	const model = crown(THREE, helpers), geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(WOOD, 3));
	geometry.computeVertexNormals();
	return { ...model, parts: [...model.parts.filter(part => part.sway !== 0), { geometry, color: '#75604f', sway: 0 }] };
}
