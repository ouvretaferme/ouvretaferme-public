// Les caisses : quatre caisses de plastique posées à la main, dont une gerbée, de 0,6 × 0,3 × 0,4 m chacune, sur 1,4 × 1 m.
// Aucune n'est d'équerre avec sa voisine, et trois montrent leur récolte : la pile se lit comme un coin de travail, pas comme
// un cube. La caisse seule que porte le paysan est caisse.js.
export function build(THREE, helpers) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const W = .6, H = .3, D = .4;
	// [x, y, z, rotation, couleur de la caisse, récolte]
	const crates = [
		[-.3, 0, -.2, .04, P.bleu, null],
		[.35, 0, -.2, -.07, P.orSombre, P.rouge],
		[-.3, .3, -.2, -.12, P.bleu, P.feuilleClaire],
		[0, 0, .3, .16, P.tuile, P.feuilleClaire],
	];
	for(const [x, y, z, angle, color, crop] of crates) {
		const place = g => facets(g.rotateY(angle).translate(x, y, z));
		add(place(new THREE.BoxGeometry(W - .06, H, D - .06).translate(0, H / 2, 0)), color);
		// Le rebord déborde de 3 cm : c'est lui qui accroche la lumière et sépare deux caisses gerbées
		add(place(new THREE.BoxGeometry(W, .07, D).translate(0, H - .035, 0)), color);
		if(crop !== null) {
			add(place(new THREE.IcosahedronGeometry(1, 0).scale(.24, .1, .15).translate(0, H, 0)), crop);
		}
	}

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
