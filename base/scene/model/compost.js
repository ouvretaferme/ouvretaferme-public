// Le compost : trois cases de planches ouvertes sur l'avant (+Z), de 4 × 1 × 1,4 m. Les trois tas ne sont ni de la même hauteur
// ni de la même couleur (paille fraiche, tas qui chauffe, terreau mûr) : c'est cette suite, du clair et haut au sombre et bas, qui
// dit « compost » plutôt que « trois stalles ».
export function build(THREE, helpers) {
	const { P, facets, box, block, collector, measure } = helpers;
	const { parts, add } = collector();

	for(const y of [.28, .72]) {
		add(box(0, y, -.62, 3.98, .36, .08), P.bois);
	}
	for(const x of [-1.95, -.65, .65, 1.95]) {
		add(box(x, .5, -.02, .08, .86, 1.16), P.bois);
		add(block(x, 0, .6, .15, 1, .15), P.boisSombre);
	}
	// Un tas : une demi-boule à six quartiers, bosselée. La bosse se tire de la position pour que les sommets doublés
	// par la géométrie reçoivent la même
	const heap = (x, radius, height, color, salt) => {
		const g = new THREE.SphereGeometry(1, 6, 2, salt, Math.PI * 2, 0, Math.PI / 2), position = g.attributes.position;
		for(let i = 0; i < position.count; i++) {
			const px = position.getX(i), py = position.getY(i), pz = position.getZ(i);
			const s = Math.sin(Math.round(px * 40) * 12.9 + Math.round(py * 40) * 78.2 + Math.round(pz * 40) * 37.7 + salt) * 43758.5453;
			const k = py < .01 ? 1 : 1 + .2 * ((s - Math.floor(s)) * 2 - 1);
			position.setXYZ(i, x + px * radius * k, py * height * k, pz * radius * .9 * k - .02);
		}
		add(facets(g), color);
	};
	heap(-1.3, .56, 1.05, P.paille, 1.3);
	heap(0, .54, .8, P.terre, 2.9);
	heap(1.3, .52, .5, P.boisSombre, 4.1);

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
