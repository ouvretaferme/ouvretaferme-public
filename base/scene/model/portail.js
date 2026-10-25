// Le portail d'une clôture d'emprise, une barrière de champ d'un seul battant, fermée : deux poteaux de bois sombre aux deux
// bouts, trois lisses, deux montants et l'écharpe qui monte du gond bas vers le haut du côté du loquet, deux gonds noirs.
// La pièce part de z = 0 et court selon +Z jusqu'à z = 3,6, les deux poteaux compris. La scène l'étire jusqu'à 4 m pour le
// ruban électrique : l'écharpe est une poutre droite d'un poteau à l'autre, qui ne fait que se coucher un peu plus.
export function build(THREE, helpers) {
	const { P, box, drum, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 3.6, FROM = .13, LEAF = LENGTH - .26, OUTSIDE = .02;

	add(drum(0, 0, 0, .08, 1.6, 6), P.boisSombre);
	add(drum(0, 0, LENGTH, .08, 1.55, 6), P.boisSombre);
	for(const y of [.3, .75, 1.2]) {
		add(box(OUTSIDE, y, FROM + LEAF / 2, .06, .1, LEAF), P.bois);
	}
	for(const z of [FROM + .06, FROM + LEAF - .06]) {
		add(box(OUTSIDE, .75, z, .06, 1.05, .09), P.bois);
	}
	// L'écharpe se pose par-dessus les lisses, en compression du gond bas vers l'angle haut du loquet, comme sur une vraie barrière
	const z0 = FROM + .1, z1 = FROM + LEAF - .1, rise = .9, run = z1 - z0;
	add(facets(new THREE.BoxGeometry(.05, .09, Math.hypot(rise, run)).rotateX(-Math.atan2(rise, run)).translate(OUTSIDE + .055, .75, (z0 + z1) / 2)), P.bois);
	for(const y of [.3, 1.2]) {
		add(box(OUTSIDE, y, .1, .1, .1, .1), P.noir);
	}
	return { ...measure(parts), aligned: false, parts };
}
