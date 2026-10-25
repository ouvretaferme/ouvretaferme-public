// Le piquet de bois seul de la ganivelle, qui ferme le dernier segment d'une ligne (cloture.ganivelle.js).
export function build(THREE, helpers) {
	const { P, drum, collector, measure } = helpers;
	const { parts, add } = collector();

	add(drum(0, 0, 0, .06, 1.32, 5), P.bois);
	return { ...measure(parts), aligned: false, parts };
}
