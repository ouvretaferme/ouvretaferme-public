// Hibiscus (roselle), pensé pour se lire sur un rang à 20 px/m : quatre baguettes rouge sombre qui s'écartent depuis le pied, plus
// haut que large. Le feuillage est rare et grand, des pattes à trois ou cinq doigts étroits bien écartés, tenues loin du bois par
// un pétiole rouge : les cinq doigts en bas, quelques trois doigts plus haut. La moitié haute des baguettes porte le chapelet de
// calices rouges que le socle y pose (FRUITS.hibiscus dans socle.js, qui recopie WOOD). Cinq grandes fleurs en entonnoir crème à cœur pourpre
// sortent du faisceau au bout d'un court pédoncule, tournées vers l'extérieur et vers le ciel.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Fiche : hibiscus.reference.md.
export function build(THREE, helpers) {
	const LEAF = '#3f7d34'; const STEM = '#8a1f2b'; const PETAL = '#f6ecc0'; const HEART = '#6e1f45';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (chaque doigt prend sa lumière)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const add = (a, b, k = 1) => a.map((v, i) => v + b[i] * k);
	const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);
	const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
	const unit = a => { const l = Math.hypot(...a); return a.map(v => v / l); };
	const direction = (azimuth, rise) => [Math.cos(rise) * Math.cos(azimuth), Math.sin(rise), Math.cos(rise) * Math.sin(azimuth)];
	// Point d'un cercle de rayon r autour de center, dans le plan (v, w), avancé de lift le long de u
	const ring = (center, u, v, w, r, lift, count, phase) => Array.from({ length: count }, (_, k) => {
		const a = phase + k * Math.PI * 2 / count;
		return add(add(add(center, v, Math.cos(a) * r), w, Math.sin(a) * r), u, lift);
	});

	// Tube à trois pans ouvert entre deux points
	const tube = (from, to, r0, r1) => {
		const axis = unit(add(to, from, -1)), side = unit(cross([.31, 0, .95], axis)), up = cross(axis, side);
		const A = ring(from, axis, side, up, r0, 0, 3, 0), B = ring(to, axis, side, up, r1, 0, 3, 0), tris = [];
		for(let j = 0; j < 3; j++) {
			const k = (j + 1) % 3;
			tris.push([A[j], B[j], A[k]], [A[k], B[j], B[k]]);
		}
		return tris;
	};

	// Limbe en patte : chaque doigt est un seul triangle étroit, large à l'attache et pointu au bout, qui retombe. Tous partent
	// du même point, ce qui soude la paume. Le rang décale longueurs et angles.
	const LOBES = { 3: [[-44, .8], [0, 1], [44, .76]], 5: [[-82, .62], [-40, .88], [0, 1], [40, .85], [82, .6]] };
	const WIDTH = .15;
	const leaf = (base, azimuth, pitch, length, lobes, rank) => {
		const point = (degrees, r) => {
			const a = degrees * Math.PI / 180;
			const x = Math.cos(a) * r * length, z = Math.sin(a) * r * length;
			const y = -.3 * r * r * length;
			const xp = x * Math.cos(pitch) - y * Math.sin(pitch), yp = x * Math.sin(pitch) + y * Math.cos(pitch);
			return add(base, [xp * Math.cos(azimuth) - z * Math.sin(azimuth), yp, xp * Math.sin(azimuth) + z * Math.cos(azimuth)]);
		};
		return LOBES[lobes].map(([degrees, r], k) => {
			const swing = degrees + 6 * Math.sin(rank * 2.3 + k * 1.9);
			const reach = r * (1 + .15 * Math.sin(rank * 1.7 + k * 2.1));
			return [point(swing - 90, WIDTH), point(swing, reach), point(swing + 90, WIDTH)];
		});
	};

	// Le bois : la tige centrale, et trois branches qui partent près du sol et montent presque aussi haut qu'elle.
	// [pied, tête, rayon au pied]. FRUITS.hibiscus de socle.js recopie pieds et têtes : les deux se retouchent ensemble.
	const WOOD = [
		[[0, 0, 0], [.03, 1.46, -.02], .036],
		[[0, .08, 0], [.21, 1.3, .1], .028],
		[[0, .14, 0], [-.2, 1.2, .14], .028],
		[[0, .2, 0], [-.03, 1.34, -.23], .028],
	];
	const wood = WOOD.flatMap(([from, to, r]) => tube(from, to, r, r * .5));

	// Feuilles : [branche, position le long de la branche, azimut, doigts, longueur du limbe]. Les cinq doigts garnissent le bas,
	// les trois doigts, plus courts, sortent entre les calices jusqu'en haut. Aucune sous le cinquième bas, qui se dégarnit.
	const PLAN = [
		[0, .26, 1.5, 5, .36], [0, .5, 4.3, 3, .35], [0, .68, 2.9, 3, .34], [0, .84, .3, 3, .3],
		[1, .22, 5.7, 5, .36], [1, .4, .9, 5, .33], [1, .66, 5.6, 3, .3],
		[2, .24, 3.3, 5, .35], [2, .7, 3.6, 3, .3],
		[3, .2, 4.9, 5, .36], [3, .4, 3.9, 5, .32], [3, .64, 5.3, 3, .3],
	];
	const blades = [], red = [...wood];
	for(const [i, [branch, t, azimuth, lobes, length]] of PLAN.entries()) {
		const [from, to] = WOOD[branch];
		const node = mix(from, to, t);
		const base = add(node, direction(azimuth, .5), .1 + .02 * Math.sin(i * 2.1));
		// Le pétiole est une lame d'un triangle, vue des deux côtés : à 20 px/m il se lit comme un trait rouge
		red.push([node, add(node, [0, .04, 0]), add(base, direction(azimuth, 0), length * .15)]);
		blades.push(...leaf(base, azimuth, -.5 + .9 * t + .15 * Math.sin(i * 1.9), length, lobes, i));
	}

	// Fleurs : [branche, position, azimut, montée de l'axe, rayon]. Un entonnoir à cinq pétales crème, ouvert vers l'extérieur et
	// vers le ciel, un cœur pourpre au fond, et un pédoncule rouge d'un triangle qui le sort du faisceau
	const FLOWERS = [[0, .7, 1.1, .8, .13], [1, .66, .1, .7, .14], [2, .64, 2.7, .75, .13], [3, .62, 4.4, .7, .14], [0, .88, 3.6, .9, .12]];
	const petals = [], heart = [];
	for(const [i, [branch, t, azimuth, rise, radius]] of FLOWERS.entries()) {
		const [from, to] = WOOD[branch];
		const node = mix(from, to, t);
		const u = direction(azimuth, rise), v = unit(cross([0, 1, 0], u)), w = cross(u, v);
		const center = add(node, direction(azimuth, .35), .13);
		const rim = ring(center, u, v, w, radius, radius * .65, 5, i * 1.3);
		rim.forEach((p, k) => petals.push([center, p, rim[(k + 1) % 5]]));
		heart.push(ring(center, u, v, w, radius * .48, radius * .4, 3, i + .5));
		red.push([node, add(node, [0, .05, 0]), center]);
	}

	return { d: .8, h: 1.5, parts: [
		{ color: LEAF, geometry: mesh(blades) },
		{ color: STEM, sway: .3, geometry: mesh(red) },
		{ color: PETAL, geometry: mesh(petals) },
		{ color: HEART, geometry: mesh(heart) },
	] };
}
