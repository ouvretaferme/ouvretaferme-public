// Le hangar agricole, d'après la photo de la fiche (hangar.reference.md) : un long volume de bardage beige sous un toit gris à
// faible pente, la grande porte coulissante décalée à droite du pignon. Les nervures de la tôle deviennent de larges couvre-joints
// taillés, un tous les 1,6 m, le toit est fait de deux rangs de plaques qui se chevauchent en marche, et la porte, plus claire que
// le mur sous un rail brun, porte le plus fort contraste. Pignon de la porte vers +Z, 10 × 18 m, faitage à 6,6 m.
// Les vantaux s'appellent « vantailGauche » et « vantailDroit », pour que la scène puisse les faire coulisser.
export function build(THREE, helpers) {
	const { P, facets, box, block, prism, collector, measure } = helpers;
	const { parts, add } = collector();
	const W = 10, D = 18, EAVES = 5, RIDGE = 6.6;
	const PLINTH = .55, RIB_STEP = 1.6, RIB = .22, OUT = .1, front = D / 2;
	const pitch = Math.atan2(RIDGE - EAVES, W / 2);
	// Hauteur du mur pignon à l'abscisse x
	const gableAt = x => EAVES + (RIDGE - EAVES) * (1 - Math.abs(x) / (W / 2));

	add(prism([[-W / 2, 0], [W / 2, 0], [W / 2, EAVES], [0, RIDGE], [-W / 2, EAVES]], D), P.pierre);

	// Le toit : deux rangs par pan, chacun un peu moins pentu que le pan, pour que le rang du haut recouvre celui du bas
	const OVERHANG = .3, run = W / 2 + OVERHANG, length = run / Math.cos(pitch), ROWS = 2, LIFT = .1;
	for(const side of [-1, 1]) {
		for(let i = 0; i < ROWS; i++) {
			const s = (i + .5) * length / ROWS;
			const plate = new THREE.BoxGeometry(length / ROWS + .25, .14, D + OVERHANG * 2).rotateZ(-side * (pitch - .04));
			add(facets(plate.translate(side * (s * Math.cos(pitch) + LIFT * Math.sin(pitch)), RIDGE - s * Math.sin(pitch) + LIFT * Math.cos(pitch), 0)), P.toleSombre);
		}
	}
	add(box(0, RIDGE + .2, 0, .7, .16, D + OVERHANG * 2 + .1), P.toleSombre);

	// Le soubassement de béton, sur les longs côtés et le pignon du fond : la façade est bardée jusqu'au sol
	for(const side of [-1, 1]) {
		add(block(side * W / 2, 0, -.1, .2, PLINTH, D - .2), P.gris);
	}
	add(block(0, 0, -front, W + .2, PLINTH, .2), P.gris);

	// La porte : deux vantaux coulissants, décalés à droite du milieu du pignon, plus clairs que le mur
	const doorWidth = W * .44, doorHeight = EAVES * .84, doorX = W * .03 + doorWidth / 2;
	// Le fond brun se voit dans le joint entre les vantaux
	add(box(doorX, doorHeight / 2, front + .03, doorWidth, doorHeight, .08), P.bois);
	for(const [name, sign] of [['vantailGauche', -1], ['vantailDroit', 1]]) {
		const x = doorX + sign * doorWidth / 4, leaf = doorWidth / 2 - .08;
		add(box(x, doorHeight / 2, front + .13, leaf, doorHeight, .14), P.creme, name);
		// La traverse à mi-hauteur et le couvre-joint du milieu de chaque vantail, bardé comme le mur
		add(box(x, doorHeight * .5, front + .22, leaf, .2, .08), P.bois, name);
		add(box(x, doorHeight * .75 + .05, front + .22, RIB, doorHeight * .5 - .1, .08), P.creme, name);
		add(box(x, doorHeight * .25 - .05, front + .22, RIB, doorHeight * .5 - .1, .08), P.creme, name);
	}
	// Le rail qui déborde des deux côtés, et le montant rouillé du bord droit
	add(box(doorX, doorHeight + .11, front + .16, doorWidth + .7, .22, .3), P.bois);
	add(block(doorX + doorWidth / 2 + .16, 0, front + .12, .26, doorHeight, .22), P.tuile);

	// Les couvre-joints du bardage, sur les quatre murs. Ceux du pignon s'arrêtent au-dessus du rail quand ils tombent sur la porte
	const across = Math.floor((W / 2 - .6) / RIB_STEP);
	for(let i = -across - 1; i <= across; i++) {
		// Décalés d'un demi-pas : aucun ne tombe dans l'axe du faitage, où il ferait un poinçon de charpente
		const x = (i + .5) * RIB_STEP;
		if(Math.abs(x) > W / 2 - .5) {
			continue;
		}
		const top = gableAt(x) - .12;
		const inDoor = Math.abs(x - doorX) < doorWidth / 2 + .5, bottom = inDoor ? doorHeight + .22 : 0;
		if(top - bottom > .5) {
			add(box(x, (top + bottom) / 2, front, RIB, top - bottom, OUT * 2), P.pierre);
		}
		add(box(x, (top + PLINTH) / 2, -front, RIB, top - PLINTH, OUT * 2), P.pierre);
	}
	const along = Math.floor((D / 2 - .8) / 2);
	for(let i = -along; i <= along; i++) {
		for(const side of [-1, 1]) {
			add(box(side * W / 2, (EAVES + PLINTH) / 2 - .06, i * 2, OUT * 2, EAVES - PLINTH - .12, RIB), P.pierre);
		}
	}

	// La descente d'eau à l'angle gauche de la façade
	add(block(-W / 2 - .04, 0, front - .04, .24, EAVES - .1, .24), P.gris);

	const { d, h } = measure(parts);
	return {
		d, h, aligned: false, parts,
		// porte est le milieu du seuil, porteCoin le coin haut droit de l'ouverture : la scène y lit la largeur et la hauteur de la
		// porte, pour le graphe de marche et les piles de caisses
		anchors: {
			porte: [doorX, 0, front], porteCoin: [doorX + doorWidth / 2, doorHeight, front],
			enseigne: [doorX, doorHeight + .6, front + .24], faitage: [0, RIDGE + .28, 0],
		},
	};
}
