// Le puits de cour : une margelle de pierre ronde, l'eau sombre au fond, deux montants de bois qui portent le treuil, sa corde et son
// seau, sous un petit toit de tuile à deux pans dont le faitage court selon X. Ce qui le fait reconnaitre d'en haut est le cercle
// de pierre claire autour d'un disque sombre, puis le toit posé au-dessus sans toucher la margelle : une citerne n'a ni l'un ni
// l'autre. 1,5 m de diamètre, 2,9 m au faitage, pied à y = 0.
export function build(THREE, helpers) {
	const { P, facets, box, merge, collector, measure } = helpers;
	const { parts, add } = collector();
	const R = .7, E = .2, HAUT = .8, SIDES = 12;

	// La margelle : un anneau de pierre, son chant épaissi en couronne pour qu'il accroche la lumière
	const anneau = (r0, r1, y0, y1) => {
		const profil = [[r0, y0], [r1, y0], [r1, y1], [r0, y1], [r0, y0]].map(([r, y]) => new THREE.Vector2(r, y));
		return facets(new THREE.LatheGeometry(profil, SIDES));
	};
	add(merge([anneau(R - E, R, 0, HAUT), anneau(R - E - .04, R + .05, HAUT, HAUT + .1)]), P.pierre);
	// L'eau, à 20 cm sous la margelle : on la voit d'en haut
	add(facets(new THREE.CircleGeometry(R - E, SIDES).rotateX(-Math.PI / 2).translate(0, HAUT - .2, 0)), P.encre);

	// Les deux montants posés sur la margelle, la traverse du treuil, la manivelle et le seau suspendu à sa corde
	const bois = [];
	for(const sens of [-1, 1]) {
		bois.push(box(sens * (R - E / 2), HAUT + .1 + .7, 0, .12, 1.4, .12));
	}
	bois.push(facets(new THREE.CylinderGeometry(.08, .08, R * 2 - .1, 6).rotateZ(Math.PI / 2).translate(0, HAUT + 1.15, 0)));
	bois.push(box(R + .02, HAUT + 1.05, .1, .05, .25, .05));
	bois.push(box(0, HAUT + .82, 0, .025, .62, .025));
	bois.push(facets(new THREE.CylinderGeometry(.13, .1, .24, 6).translate(0, HAUT + .4, 0)));
	add(merge(bois), P.boisSombre);

	// Le toit à deux pans, sur les montants : étroit selon Z, pour que l'anneau de pierre se lise des deux côtés vu de haut
	const pied = HAUT + 1.5, faite = pied + .45, demi = .42, pente = Math.atan2(faite - pied, demi);
	const longueur = Math.hypot(demi, faite - pied) + .06;
	const pans = [];
	for(const sens of [-1, 1]) {
		pans.push(new THREE.BoxGeometry(1.6, .06, longueur).rotateX(sens * pente).translate(0, (pied + faite) / 2, sens * demi / 2));
	}
	add(merge(pans.map(facets)), P.tuile);
	add(box(0, faite + .02, 0, 1.64, .08, .14), P.boisSombre);

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
