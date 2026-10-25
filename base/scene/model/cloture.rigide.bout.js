// Le poteau seul du panneau rigide, coiffé de noir, qui ferme le dernier segment d'une ligne (cloture.rigide.js).
export function build(THREE, helpers) {
	const { P, block, collector, measure } = helpers;
	const { parts, add } = collector();

	add(block(0, 0, 0, .06, 1.38, .06), '#244630');
	add(block(0, 1.38, 0, .08, .03, .08), P.noir);
	return { ...measure(parts), aligned: false, parts };
}
