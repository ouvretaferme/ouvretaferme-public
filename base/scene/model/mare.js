// La mare : une berge de sable au contour irrégulier, une eau en aplat, des touffes de roseaux sur le bord, quelques pierres et
// des nénuphars, sur 9,2 × 6,7 m. Les roseaux vont par touffes de trois brins dont un porte sa massette brune : moins de pièces
// que des brins semés un à un, et un bord qui se lit. L'eau s'appelle « eau », pour que la scène puisse lui garder un matériau à reflets.
export function build(THREE, helpers) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const WIDTH = 9.2, DEPTH = 6.7, REEDS = 7, LILIES = 3, STONES = 3, SEED = 11;

	// Suite déterministe (mulberry32) : la cuisson rend toujours la même mare
	let state = (Math.imul(SEED, 2654435761) + 31) >>> 0;
	const random = () => {
		state = (state + 0x6D2B79F5) >>> 0;
		let t = Math.imul(state ^ (state >>> 15), 1 | state);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const between = (a, b) => a + (b - a) * random();

	const N = 14, radii = Array.from({ length: N }, () => between(.82, 1));
	// Point du contour au rang i (fractionnaire), à la part k du rayon, dans le plan du sol
	const at = (i, k) => {
		const low = Math.floor(i) % N, high = (low + 1) % N, t = i - Math.floor(i);
		const angle = i / N * Math.PI * 2, radius = (radii[low] * (1 - t) + radii[high] * t) * k;
		return [Math.cos(angle) * radius * WIDTH / 2, Math.sin(angle) * radius * DEPTH / 2];
	};
	const sheet = (k, y) => {
		const shape = new THREE.Shape(Array.from({ length: N }, (_, i) => at(i, k)).map(([x, z]) => new THREE.Vector2(x, -z)));
		return facets(new THREE.ShapeGeometry(shape).rotateX(-Math.PI / 2).translate(0, y, 0));
	};
	add(sheet(1, .03), P.route);
	add(sheet(.84, .07), P.vitre, 'eau');

	for(let j = 0; j < REEDS; j++) {
		const [x, z] = at(between(0, N), between(.8, .93)), turn = between(0, Math.PI * 2);
		for(let b = 0; b < 3; b++) {
			const height = between(1.1, 1.9), lean = between(.05, .28), azimuth = turn + b * 2.1;
			const blade = new THREE.ConeGeometry(.13, height, 3, 1, true).translate(0, height / 2, 0).rotateZ(lean).rotateY(azimuth);
			add(facets(blade.translate(x, 0, z)), P.feuilleSombre);
			if(b === 0) {
				const head = new THREE.OctahedronGeometry(.11).scale(1, 2.6, 1).translate(0, height * .86, 0).rotateZ(lean).rotateY(azimuth);
				add(facets(head.translate(x, 0, z)), P.boisSombre);
			}
		}
	}
	for(let j = 0; j < STONES; j++) {
		const [x, z] = at(between(0, N), between(.9, 1.02)), radius = between(.32, .6);
		add(facets(new THREE.IcosahedronGeometry(radius, 0).rotateY(between(0, 3)).scale(1, .6, 1).translate(x, radius * .2, z)), P.gris);
	}
	for(let j = 0; j < LILIES; j++) {
		const [x, z] = at(between(0, N), between(.12, .5)), radius = between(.4, .65);
		// La feuille est entaillée : un disque plein se lirait comme une pierre plate
		add(facets(new THREE.CircleGeometry(radius, 6, between(0, 6), Math.PI * 1.72).rotateX(-Math.PI / 2).translate(x, .1, z)), P.feuilleClaire);
	}

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts, anchors: { centre: [0, .07, 0] } };
}
