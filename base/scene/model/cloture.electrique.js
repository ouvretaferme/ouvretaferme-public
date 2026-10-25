// Une travée de clôture électrique de 5 m, que la scène répète le long d'une ligne dessinée, tirée selon +Z depuis z = 0 : un
// piquet de plastique blanc à l'origine, avec l'étrier qui sert à l'enfoncer, et deux rubans orange de 4 cm à 45 et 90 cm, qui
// fléchissent d'un rien au milieu de la travée.
// Le piquet est dessiné une fois et demie plus gros qu'il n'est : à sa vraie section, la vue d'ensemble ne montre que deux rubans
// sans appui. Chaque ruban est une lame à deux faces.
export function build(THREE, helpers) {
	const { box, drum, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 5, OUTSIDE = .03, SAG = .04, PLASTIC = '#ece8dc', TAPE = '#ee8a2e';

	add(drum(0, 0, 0, .036, 1.05, 4), PLASTIC);
	add(box(0, .14, 0, .03, .02, .12), PLASTIC);
	const position = [];
	const blade = (a, b, c, d) => position.push(...a, ...b, ...c, ...a, ...c, ...d, ...a, ...c, ...b, ...a, ...d, ...c);
	for(const y of [.45, .9]) {
		blade([OUTSIDE, y - .02, 0], [OUTSIDE, y + .02, 0], [OUTSIDE, y + .02 - SAG, LENGTH / 2], [OUTSIDE, y - .02 - SAG, LENGTH / 2]);
		blade([OUTSIDE, y - .02 - SAG, LENGTH / 2], [OUTSIDE, y + .02 - SAG, LENGTH / 2], [OUTSIDE, y + .02, LENGTH], [OUTSIDE, y - .02, LENGTH]);
	}
	const geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
	add(facets(geometry), TAPE);
	return { ...measure(parts), aligned: false, parts };
}
