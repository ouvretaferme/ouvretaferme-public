// L'abri : une cabane de bois à deux pans, de 3 × 4 m, faitage à 2,8 m selon Z. Un bardage clair rythmé de couvre-joints
// sombres, une porte pleine décalée sur le pignon et une petite fenêtre à côté : la porte décalée et la fenêtre le séparent d'un
// hangar en réduction. Pignon de la porte vers +Z.
export function build(THREE, helpers) {
	const { P, facets, box, block, prism, collector, measure } = helpers;
	const { parts, add } = collector();
	const W = 3, D = 4, EAVES = 2.1, RIDGE = 2.8, BASE = .2;

	add(prism([[-W / 2, 0], [W / 2, 0], [W / 2, EAVES], [0, RIDGE], [-W / 2, EAVES]], D), P.bois);
	add(block(0, 0, 0, W + .12, BASE, D + .12), P.gris);

	const pitch = Math.atan2(RIDGE - EAVES, W / 2), run = W / 2 + .2, slope = run / Math.cos(pitch);
	for(const side of [-1, 1]) {
		add(facets(new THREE.BoxGeometry(slope, .12, D + .4).rotateZ(-side * pitch).translate(side * run / 2, RIDGE - Math.tan(pitch) * run / 2 + .07, 0)), P.toleSombre);
	}
	add(box(0, RIDGE + .13, 0, .34, .1, D + .44), P.tole);

	// Les couvre-joints des deux longs murs, un par mètre
	for(const side of [-1, 1]) {
		for(const z of [-1.5, -.5, .5, 1.5]) {
			add(block(side * (W / 2 + .03), BASE, z, .08, EAVES - BASE - .1, .14), P.boisSombre);
		}
	}
	const doorX = -.4, doorWidth = 1.1, doorHeight = 1.75, front = D / 2 + .04;
	add(block(doorX, BASE, front, doorWidth, doorHeight, .1), P.boisSombre);
	add(box(.85, 1.4, front, .62, .62, .1), P.vitre);

	const { d, h } = measure(parts);
	return {
		d, h, aligned: false, parts,
		// porte est le milieu du seuil, porteCoin le coin haut droit de l'ouverture : la scène y lit la largeur et la hauteur de la porte
		anchors: { porte: [doorX, 0, D / 2], porteCoin: [doorX + doorWidth / 2, BASE + doorHeight, D / 2], faitage: [0, RIDGE, 0] },
	};
}
