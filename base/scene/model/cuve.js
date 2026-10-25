// La cuve de 1 000 litres sur palette, de 1,2 × 1,3 × 1 m : un cube blanc dans sa cage, dont il ne reste que ce qui se lit.
// Quatre montants et deux ceintures, plus épais que nature (des barreaux de 3 cm passent sous le plus petit détail de la scène),
// le bouchon noir dessus et la vanne rouge au pied de la face avant, vers +Z.
export function build(THREE, helpers) {
	const { P, box, block, drum, collector, measure } = helpers;
	const { parts, add } = collector();

	for(const x of [-.48, .48]) {
		add(block(x, 0, 0, .16, .1, 1), P.bois);
	}
	add(block(0, .1, 0, 1.2, .05, 1), P.bois);
	add(block(0, .15, 0, 1.06, 1.02, .86), P.blanc);
	for(const x of [-.56, .56]) {
		for(const z of [-.46, .46]) {
			add(block(x, .15, z, .07, 1.06, .07), P.tole);
		}
	}
	// Chaque ceinture est une dalle qui traverse la cuve : seule sa tranche dépasse, pour douze triangles au lieu de quarante-huit
	for(const y of [.5, .88]) {
		add(box(0, y, 0, 1.18, .07, .98), P.tole);
	}
	add(drum(0, 1.17, 0, .14, .08, 6), P.noir);
	add(box(0, .3, .47, .16, .16, .12), P.rouge);

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts, anchors: { vanne: [0, .3, .53] } };
}
