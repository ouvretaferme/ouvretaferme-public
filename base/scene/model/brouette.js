// La brouette que pousse le paysan : une caisse évasée, verte, chargée de terre, sur une roue noire à l'avant (+Z), deux longerons
// de bois qui font les bras et deux pieds. La caisse évasée et la roue unique sont les deux traits ; la roue est grossie et la
// caisse raccourcie par rapport à nature, sans quoi la roue disparait sous la caisse à la taille de la scène.
export function build(THREE, helpers) {
	const { P, facets, block, wheel, collector, measure } = helpers;
	const { parts, add } = collector();

	// Tronc de pyramide : 0,7 × 0,9 m au bord, 0,46 × 0,6 m au fond
	add(facets(new THREE.CylinderGeometry(.5, .33, .3, 4).rotateY(Math.PI / 4).scale(.99, 1, 1.27).translate(0, .52, .1)), P.feuilleSombre);
	add(facets(new THREE.SphereGeometry(1, 6, 2, .4, Math.PI * 2, 0, Math.PI / 2).scale(.3, .17, .38).translate(0, .66, .1)), P.terre);
	add(wheel(0, .22, .66, .22, .12, 8), P.noir);
	// Un longeron va d'une poignée à l'axe de la roue, d'un seul tenant, sous la caisse
	const bar = (from, to) => {
		const a = new THREE.Vector3(...from), b = new THREE.Vector3(...to), length = a.distanceTo(b);
		const g = new THREE.BoxGeometry(.06, .06, length);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 0, 1), b.clone().sub(a).normalize()));
		const middle = a.add(b).multiplyScalar(.5);
		add(facets(g.translate(middle.x, middle.y, middle.z)), P.bois);
	};
	for(const side of [-1, 1]) {
		bar([side * .29, .62, -.8], [side * .09, .22, .66]);
		add(block(side * .22, 0, -.22, .06, .42, .06), P.toleSombre);
	}

	const { d, h } = measure(parts);
	// Les poignées sont là où le paysan pose les mains, la roue là où la brouette bascule
	return { d, h, aligned: false, parts, anchors: { poignees: [0, .62, -.8], roue: [0, .22, .66] } };
}
