// Le bureau, d'après les deux photos de la fiche (bureau.reference.md) : la maisonnette de crépi clair sous un toit de tuile à
// faible pente, ses volets blancs, sa porte vitrée au bord droit du pignon et son bac de lavage en béton. La tuile est en deux
// rangs qui se recouvrent, posés sur des plaques grises qui débordent, les volets et les appuis sortent franchement du mur, les
// vitres sont des aplats. Pignon de la porte vers +Z, 5 × 7,5 m, faitage à 3,8 m. Les vitres et l'eau du bac s'appellent
// « vitre », pour que la scène puisse leur garder un matériau à reflets.
export function build(THREE, helpers) {
	const { P, facets, box, block, prism, collector, measure } = helpers;
	const { parts, add } = collector();
	const W = 5, D = 7.5, EAVES = 2.8, RIDGE = 3.8;
	const EAVE_OVERHANG = .3, VERGE_OVERHANG = .45;
	const pitch = Math.atan2(RIDGE - EAVES, W / 2), front = D / 2;
	const gableAt = x => EAVES + (RIDGE - EAVES) * (1 - Math.abs(x) / (W / 2));
	const glass = geometry => add(geometry, P.vitre, 'vitre');
	// Une pièce plaquée sur un long côté : largeur en z, épaisseur en x, dont la moitié sort du mur
	const onSide = (side, z, y, width, height, thickness) => box(side * W / 2, y, z, thickness * 2, height, width);

	add(prism([[-W / 2, 0], [W / 2, 0], [W / 2, EAVES], [0, RIDGE], [-W / 2, EAVES]], D), P.creme);

	// Le toit : une plaque grise par pan, qui déborde aux rives et à l'égout, et deux rangs de tuile par-dessus, sur la longueur des murs
	const run = W / 2 + EAVE_OVERHANG, length = run / Math.cos(pitch);
	const onRoof = (side, s, lift, sx, sy, sz, tilt = 0) => facets(new THREE.BoxGeometry(sx, sy, sz).rotateZ(-side * (pitch - tilt))
		.translate(side * (s * Math.cos(pitch) + lift * Math.sin(pitch)), RIDGE - s * Math.sin(pitch) + lift * Math.cos(pitch), 0));
	for(const side of [-1, 1]) {
		add(onRoof(side, length / 2, .04, length, .08, D + VERGE_OVERHANG * 2), P.gris);
		for(const i of [0, 1]) {
			add(onRoof(side, (i + .5) * (length - .1) / 2, .16, (length - .1) / 2 + .2, .12, D + .3, .05), P.tuile);
		}
		// La gouttière blanche sous l'égout
		add(box(side * (run + .06), EAVES - Math.tan(pitch) * EAVE_OVERHANG - .06, 0, .18, .16, D + VERGE_OVERHANG * 2), P.blanc);
	}
	add(box(0, RIDGE + .26, 0, .5, .14, D + .3), P.tuile);
	// Les bouts de pannes en bois sous le débord des deux rives : la faitière et une panne par pan
	for(const end of [-1, 1]) {
		for(const x of [-W * .36, 0, W * .36]) {
			add(box(x, gableAt(x) - .14, end * (front + VERGE_OVERHANG / 2), .16, .2, VERGE_OVERHANG), P.boisSombre);
		}
	}
	// La cheminée, sur le pan droit vers le fond, et son chapeau
	const chimneyX = W * .16, chimneyZ = -D * .3;
	add(block(chimneyX, gableAt(chimneyX) - .1, chimneyZ, .5, .95, .5), P.creme);
	add(block(chimneyX, gableAt(chimneyX) + .85, chimneyZ, .68, .12, .68), P.gris);

	// Une fenêtre d'un long côté : appui de béton, vitrage, et ses volets blancs, ouverts à plat sur le mur ou clos
	const sideWindow = (side, z, width, height, sill, bars, shutters) => {
		const y = sill + height / 2;
		add(onSide(side, z, sill - .08, width + .3, .16, .16), P.gris);
		if(shutters === 'closed') {
			add(onSide(side, z, y, width, height, .1), P.blanc);
			return;
		}
		glass(onSide(side, z, y, width, height, .05));
		for(let i = 1; i <= bars; i++) {
			add(onSide(side, z - width / 2 + i * width / (bars + 1), y, .08, height, .09), P.blanc);
		}
		const leaf = shutters === 'pair' ? width * .42 : width;
		for(const way of shutters === 'pair' ? [-1, 1] : [-side]) {
			add(onSide(side, z + way * (width / 2 + leaf / 2 + .05), y, leaf, height + .08, .1), P.blanc);
		}
	};
	// Côté droit : une large fenêtre à petits bois entre deux volets ouverts
	sideWindow(1, D * .06, 1.5, 1.1, 1, 3, 'pair');
	// Côté gauche : une petite fenêtre haute près de la façade, puis deux fenêtres aux volets clos
	sideWindow(-1, D * .3, .5, .65, 1.5, 0, 'single');
	sideWindow(-1, D * .02, .6, 1.25, .95, 0, 'closed');
	sideWindow(-1, -D * .2, .55, 1.2, 1, 0, 'closed');

	// La porte d'entrée, au bord droit du pignon : une porte blanche vitrée entre ses deux volets ouverts à plat sur le mur
	const doorWidth = 1.1, doorHeight = 2.1, doorX = W / 2 - .62 - doorWidth / 2;
	add(box(doorX, doorHeight / 2 + .03, front, doorWidth + .16, doorHeight + .06, .16), P.blanc);
	glass(box(doorX, doorHeight * .58, front + .06, .62, doorHeight * .62, .1));
	for(const i of [1, 2]) {
		add(box(doorX, doorHeight * (.27 + i * .207), front + .09, .62, .08, .1), P.blanc);
	}
	for(const way of [-1, 1]) {
		const leafX = doorX + way * (doorWidth / 2 + .08 + .29);
		add(box(leafX, doorHeight / 2, front + .03, .58, doorHeight, .18), P.blanc);
		// Les deux barres de chaque volet, en relief : elles accrochent la lumière sur un aplat blanc
		for(const y of [.42, 1.72]) {
			add(box(leafX, y, front + .14, .58, .16, .08), P.blanc);
		}
	}

	// Le bac de lavage en béton contre le pignon, à gauche de la porte, sur deux plots, son eau et son robinet
	const troughX = -W * .14, troughZ = front + .42;
	add(box(troughX, .5, troughZ, 2.3, .5, .74), P.gris);
	glass(box(troughX, .745, troughZ, 2, .04, .5));
	for(const way of [-1, 1]) {
		add(block(troughX + way * .8, 0, troughZ, .34, .25, .5), P.gris);
	}
	add(block(troughX + .1, .75, front + .08, .16, .5, .16), P.gris);

	const { d, h } = measure(parts);
	return {
		d, h, aligned: false, parts,
		// porte est le milieu du seuil, porteCoin le coin haut droit de l'ouverture : la scène y lit la largeur et la hauteur de la
		// porte, pour le graphe de marche
		anchors: {
			porte: [doorX, 0, front], porteCoin: [doorX + doorWidth / 2, doorHeight, front],
			cheminee: [chimneyX, gableAt(chimneyX) + 1, chimneyZ], bac: [troughX, .75, troughZ],
		},
	};
}
