// Le panneau : une planche épaisse posée sur deux poteaux sombres, coiffée d'un chaperon qui déborde, de 2 × 1,9 m, faces vers
// ±Z. La face claire est une pièce à part, « face », une par côté : la scène y pose son texte, ou la laisse en aplat. Deux
// lignes d'écriture figurées la marquent tant qu'aucun texte n'y est posé.
export function build(THREE, helpers) {
	const { P, box, block, collector, measure } = helpers;
	const { parts, add } = collector();
	const WIDTH = 2, HEIGHT = 1.9, BOARD = .9;
	const top = HEIGHT - .08, middle = top - BOARD / 2, post = WIDTH * .375;

	// Les poteaux s'arrêtent sous la planche, qui pose dessus : rien ne passe devant la face où la scène écrit
	const bottom = middle - BOARD / 2;
	add(block(-post, 0, 0, .15, bottom, .15), P.boisSombre);
	add(block(post, 0, 0, .15, bottom, .15), P.boisSombre);
	add(box(0, middle, 0, WIDTH, BOARD, .2), P.bois);
	add(box(0, top + .04, 0, WIDTH + .16, .08, .36), P.boisSombre);
	for(const side of [-1, 1]) {
		add(box(0, middle, side * .1, WIDTH - .2, BOARD - .2, .03), P.creme, 'face');
	}
	add(box(-WIDTH * .08, middle + .14, .12, WIDTH * .54, .13, .02), P.encre);
	add(box(-WIDTH * .17, middle - .14, .12, WIDTH * .36, .13, .02), P.encre);

	const { d, h } = measure(parts);
	return {
		d, h, aligned: false, parts,
		// texte est le centre de la face avant, texteCoin son coin haut droit : la scène y lit le rectangle où elle écrit
		anchors: { texte: [0, middle, .116], texteCoin: [(WIDTH - .2) / 2, middle + (BOARD - .2) / 2, .116] },
	};
}
