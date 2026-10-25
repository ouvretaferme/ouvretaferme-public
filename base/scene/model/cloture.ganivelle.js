// Une travée de ganivelle de 2,5 m, que la scène répète le long d'une ligne dessinée, tirée selon +Z depuis z = 0 : un piquet de
// bois à l'origine et neuf lattes de châtaignier fendu, plantées au pied, pointe taillée en biseau, chacune de sa hauteur et de
// l'une des deux teintes du bois, liées par deux rangs de fil torsadé.
// Les lattes sont plus larges et moins nombreuses que les vraies (10 cm au lieu de 5,5) : à l'échelle du jeu, la clôture se lit
// à ses pointes et à ses jours. Chaque latte et chaque fil est une lame à deux faces.
export function build(THREE, helpers) {
	const { P, facets, drum, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 2.5, OUTSIDE = .07, SLATS = 9, WIDTH = .1;
	const CHESTNUT = ['#b48457', '#9e7048'], WIRE = '#5d5a55', TOPS = [1.19, 1.25, 1.16, 1.22, 1.14, 1.24, 1.18, 1.23, 1.15];

	add(drum(0, 0, 0, .06, 1.32, 5), P.bois);
	const slats = [[], []], wires = [];
	const face = (list, points) => {
		for(let k = 1; k < points.length - 1; k++) list.push(...points[0], ...points[k], ...points[k + 1], ...points[0], ...points[k + 1], ...points[k]);
	};
	for(let i = 0; i < SLATS; i++) {
		const z0 = (i + .5) * LENGTH / SLATS - WIDTH / 2, z1 = z0 + WIDTH, top = TOPS[i], bevel = i % 2 ? .02 : -.02;
		face(slats[i % 2], [[OUTSIDE, 0, z0], [OUTSIDE, top - .12, z0], [OUTSIDE, top, (z0 + z1) / 2 + bevel], [OUTSIDE, top - .12, z1], [OUTSIDE, 0, z1]]);
	}
	for(const y of [.12, .95]) {
		face(wires, [[OUTSIDE + .005, y - .012, 0], [OUTSIDE + .005, y + .012, 0], [OUTSIDE + .005, y + .012, LENGTH], [OUTSIDE + .005, y - .012, LENGTH]]);
	}
	for(const [list, color] of [[slats[0], CHESTNUT[0]], [slats[1], CHESTNUT[1]], [wires, WIRE]]) {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
		add(facets(geometry), color);
	}
	return { ...measure(parts), aligned: false, parts };
}
