// Une travée de panneau rigide soudé de 2,5 m, que la scène répète le long d'une ligne dessinée, tirée selon +Z depuis z = 0 :
// un poteau carré vert sombre à l'origine, coiffé de noir, et le panneau vert de 1,23 m posé à 5 cm du sol.
// Le panneau est de la géométrie et non un motif : quatre fils horizontaux, dont deux doublés aux plis qui le raidissent, et neuf
// fils verticaux tous les 25 cm au lieu de 5, épaissis à 2,4 cm pour se lire de loin. Chaque fil est une lame à deux faces.
export function build(THREE, helpers) {
	const { P, facets, block, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 2.5, OUTSIDE = .035, GREEN = '#2f5a3c', GREEN_DARK = '#244630';

	add(block(0, 0, 0, .06, 1.38, .06), GREEN_DARK);
	add(block(0, 1.38, 0, .08, .03, .08), P.noir);
	const position = [];
	const blade = (a, b, c, d) => position.push(...a, ...b, ...c, ...a, ...c, ...d, ...a, ...c, ...b, ...a, ...d, ...c);
	for(const y of [.06, .38, .93, 1.27]) {
		blade([OUTSIDE, y - .012, 0], [OUTSIDE, y + .012, 0], [OUTSIDE, y + .012, LENGTH], [OUTSIDE, y - .012, LENGTH]);
	}
	for(let k = 1; k <= 9; k++) {
		const z = k * LENGTH / 10;
		blade([OUTSIDE, .05, z - .012], [OUTSIDE, 1.28, z - .012], [OUTSIDE, 1.28, z + .012], [OUTSIDE, .05, z + .012]);
	}
	const geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
	add(facets(geometry), GREEN);
	return { ...measure(parts), aligned: false, parts };
}
