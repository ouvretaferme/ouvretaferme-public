// L'électrovanne, en ligne sur la conduite comme la vanne manuelle de la scène et à la même taille : le même tube bleu couché sur
// l'axe X, le même corps gris, mais coiffée d'une bobine noire carrée, plus étroite que le corps, au lieu du volant rouge, avec la
// petite manette de purge au flanc. Un connecteur sur le devant de la bobine laisse partir un câble orange qui descend au sol et y
// court. Une bobine ronde et large se lisait comme un bouchon ou la tête d'un compteur. La bobine noire sans volant et le câble la séparent de la vanne
// manuelle ; le câble, d'un clapet ou d'un manchon.
export function build(THREE, helpers) {
	const { facets, merge, block, drum, wheel, collector, measure } = helpers;
	const { parts, add } = collector();
	const BLUE = '#1c64b8', BODY = '#5e6b78', COIL = '#26282b', CABLE = '#f0a020';

	add(wheel(0, .08, 0, .06, .5, 8), BLUE);
	add(merge([
		block(0, .01, 0, .2, .16, .16),
		drum(0, .17, 0, .045, .03, 8),
	]), BODY);
	add(merge([
		block(0, .2, 0, .12, .12, .12),
		drum(0, .32, 0, .022, .04, 6),
		block(0, .21, .085, .08, .09, .05),
		block(.06, .1, -.09, .03, .03, .03),
		block(.03, .115, -.115, .09, .015, .02),
	]), COIL);

	// Le câble sort du connecteur, plonge au pied du corps et file au sol en s'écartant de la conduite
	const path = new THREE.CatmullRomCurve3([
		[0, .225, .11], [0, .2, .15], [.02, .1, .2], [.05, .02, .24], [.11, .012, .31], [.2, .012, .36], [.3, .012, .38],
	].map(p => new THREE.Vector3(...p)));
	add(facets(new THREE.TubeGeometry(path, 10, .012, 4, false)), CABLE);

	return {
		...measure(parts), aligned: false, parts,
		// entree et sortie sont les deux bouts du tube, sur son axe, comme la vanne que la scène cale sur la conduite
		anchors: { entree: [-.25, .08, 0], sortie: [.25, .08, 0] },
	};
}
