// Le piquet de plastique seul de la clôture électrique, avec son étrier, qui ferme le dernier segment d'une ligne
// (cloture.electrique.js). Dessiné une fois et demie plus gros qu'il n'est, comme celui de la travée.
export function build(THREE, helpers) {
	const { box, drum, collector, measure } = helpers;
	const { parts, add } = collector();

	add(drum(0, 0, 0, .036, 1.05, 4), '#ece8dc');
	add(box(0, .14, 0, .03, .02, .12), '#ece8dc');
	return { ...measure(parts), aligned: false, parts };
}
