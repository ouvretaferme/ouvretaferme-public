// Une travée de clôture à mouton de 3 m, que la scène répète le long d'une ligne dessinée, tirée selon +Z depuis z = 0 : un
// piquet de bois à l'origine et le grillage noué agrafé sur sa face extérieure, jusqu'au piquet de la travée suivante.
// Le grillage est de la géométrie et non un motif : six fils horizontaux, plus serrés au pied comme la maille qui arrête le petit
// gibier, et quatre fils verticaux. Chacun est épaissi à 2,4 cm pour se lire de loin, et posé en lame à deux faces.
export function build(THREE, helpers) {
	const { P, facets, drum, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 3, OUTSIDE = .07, WIRE = '#b9bebb';

	add(drum(0, 0, 0, .06, 1.3, 5), P.boisSombre);
	const position = [];
	// Une lame à deux faces : le matériau de la scène n'en éclaire qu'une
	const blade = (a, b, c, d) => position.push(...a, ...b, ...c, ...a, ...c, ...d, ...a, ...c, ...b, ...a, ...d, ...c);
	for(const y of [.03, .18, .35, .55, .78, 1.01]) {
		blade([OUTSIDE, y - .012, 0], [OUTSIDE, y + .012, 0], [OUTSIDE, y + .012, LENGTH], [OUTSIDE, y - .012, LENGTH]);
	}
	for(const z of [.6, 1.2, 1.8, 2.4]) {
		blade([OUTSIDE, .03, z - .012], [OUTSIDE, 1.01, z - .012], [OUTSIDE, 1.01, z + .012], [OUTSIDE, .03, z + .012]);
	}
	const geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
	add(facets(geometry), WIRE);
	return { ...measure(parts), aligned: false, parts };
}
