// Maïs : une grande tige droite, de longues feuilles en ruban qui s'arquent puis retombent, alternées de part et d'autre,
// la panicule dorée en plumeau au sommet et deux épis à mi-hauteur, spathe verte entrouverte sur les grains et soies rousses.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const { stem, cone, ico, merge } = helpers;
	const LEAF = '#5f9f35'; const GOLD = '#efc643'; const HUSK = '#a9cc68'; const SILK = '#a4532c';

	// Ruban le long d'un chemin du plan (r, y), sa largeur portée à l'horizontale : 2 triangles par tronçon, 1 pour la pointe
	const ribbon = (path, widths) => {
		const a = [];
		const at = i => [[path[i][0], path[i][1], widths[i] / 2], [path[i][0], path[i][1], -widths[i] / 2]];
		for(let i = 0; i < path.length - 1; i++) {
			const [l0, r0] = at(i), [l1, r1] = at(i + 1);
			a.push(...l0, ...r0, ...l1);
			if(widths[i + 1] > 0) a.push(...r0, ...r1, ...l1);
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(a, 3));
		return g;
	};

	const H = 2.2;
	// Neuf feuilles, deux rangs opposés qui tournent un peu en montant ; les basses longues et retombantes, les hautes plus dressées
	const leaves = Array.from({ length: 9 }, (_, i) => {
		const y = .16 + i * .17;
		const k = .8 - i * .036;
		const lift = .06 + i * .02;
		const path = [[0, 0], [.22 * k, .2 + lift], [.46 * k, .3 + lift], [.68 * k, .22 + lift * .6], [.84 * k, .02 + lift * .3]];
		return ribbon(path, [.11, .21, .2, .13, 0]).rotateY((i % 2 ? Math.PI : 0) + i * .35).translate(0, y, 0);
	});

	// Panicule : une flèche centrale et cinq branches qui s'écartent en plumeau
	const tassel = [
		cone(.03, .5, 3).translate(0, H + .2, 0),
		...Array.from({ length: 5 }, (_, i) => cone(.026, .44, 3).translate(0, .22, 0).rotateZ(.95).rotateY(i * 1.26).translate(0, H + .02, 0)),
	];

	// Deux épis penchés contre la tige : la spathe verte, le haut de l'épi doré qui dépasse, la touffe de soies
	const ears = [[1.05, .4], [.82, .4 + Math.PI]].map(([y, a]) => ({ y, a }));
	const out = (g, e) => g.rotateZ(-.4).rotateY(e.a).translate(Math.cos(e.a) * .07, e.y, -Math.sin(e.a) * .07);
	const husks = ears.map(e => out(cone(.1, .42, 5).translate(0, .21, 0), e));
	const cobs = ears.map(e => out(ico(.075).scale(1, 1.6, 1).translate(0, .34, 0), e));
	const silks = ears.map(e => out(cone(.05, .18, 4).translate(0, .5, 0), e));

	return {
		d: .55, h: 2.2,
		parts: [
			{ color: LEAF, geometry: merge([stem(.04, .065, H, 5).translate(0, H / 2, 0), ...leaves]) },
			{ color: GOLD, sway: .6, geometry: merge([...tassel, ...cobs]) },
			{ color: HUSK, sway: .3, geometry: merge(husks) },
			{ color: SILK, sway: .3, geometry: merge(silks) },
		],
	};
}
