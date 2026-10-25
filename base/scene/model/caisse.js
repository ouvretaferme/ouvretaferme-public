// La caisse de récolte : une boite sombre ceinte de deux rangs de lattes claires, comble d'une récolte en dôme, 0,9 × 0,45 × 0,6 m.
// Ce sont les deux bandes claires sur fond sombre qui disent « caisse à claire-voie » à la taille de la scène, une latte
// dessinée une à une passerait sous le plus petit détail. La récolte porte le nom « contenu », pour que la scène la teinte.
export function build(THREE, helpers) {
	const { P, facets, block, collector, measure } = helpers;
	const { parts, add } = collector();
	const W = .9, D = .6, H = .45;

	// La boite sombre dépasse la ceinture haute : vu de dessus, le bord reste un cadre et non un couvercle
	add(block(0, 0, 0, W - .08, H - .02, D - .08), P.boisSombre);
	for(const y of [.05, .25]) {
		add(block(0, y, 0, W, .13, D), P.bois);
	}

	// Un dôme bosselé qui dépasse du bord : une caisse pleine se voit de loin, une caisse rase se confond avec une caisse vide.
	// Les bosses sont tirées d'un hasard fixe sur la position de chaque sommet, pour que la cuisson rende toujours le même dôme
	const solid = new THREE.IcosahedronGeometry(1, 0).rotateY(1.7), g = solid.index ? solid.toNonIndexed() : solid, position = g.attributes.position;
	for(let i = 0; i < position.count; i++) {
		const x = position.getX(i), y = position.getY(i), z = position.getZ(i);
		const s = Math.sin(Math.round(x * 50) * 12.9 + Math.round(y * 50) * 78.2 + Math.round(z * 50) * 37.7 + 1) * 43758.5453;
		const k = 1 + .16 * ((s - Math.floor(s)) * 2 - 1);
		position.setXYZ(i, x * .37 * k, H - .04 + y * .14 * k, z * .23 * k);
	}
	add(facets(g), P.feuilleClaire, 'contenu');

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts, anchors: { dessus: [0, H, 0] } };
}
