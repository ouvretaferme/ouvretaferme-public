// Le piquet de bois seul de la clôture à mouton, qui ferme le dernier segment d'une ligne (cloture.mouton.js).
export function build(THREE, helpers) {
	const { P, drum, collector, measure } = helpers;
	const { parts, add } = collector();

	add(drum(0, 0, 0, .06, 1.3, 5), P.boisSombre);
	return { ...measure(parts), aligned: false, parts };
}
