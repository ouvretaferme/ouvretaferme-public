// Physalis (coqueret du Pérou), pensé pour se lire à une cinquantaine de pixels de haut : un plant dressé et ramifié, plus
// haut que large, fait de trois tiges brun vert qui montent du pied et portent en haut des feuilles ovales à pointe effilée,
// vert grisé clair. À mi-hauteur, huit rameaux s'arquent vers l'extérieur sous une feuille plus sombre qui retombe : leur
// bout descend juste au-dessus de l'anneau où le socle pend les lanternes, qui ont ainsi l'air d'y être accrochées. Le bas
// reste nu, des tiges seules, pour que les lanternes s'y détachent.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Fiche : physalis.reference.md.
export function build(THREE, helpers) {
	const TOP = '#9cb27f'; const SHADE = '#5f7a4a'; const STEM = '#4e5a36';

	// Le socle ramène l'emprise à 1 et la hauteur à 1 indépendamment, et la scène les grossit d'un facteur commun borné : pour d = 0,8
	// et h = 0,9 sur une planche de 1 m, le plant fait 1,30 m d'emprise sur 1,83 m de haut. Le modèle se dessine dans ces proportions,
	// en mètres de scène, pour que ses feuilles n'y soient pas étirées.
	const d = .8, h = .9;

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const V = (...a) => new THREE.Vector3(...a);
	const UP = V(0, 1, 0);
	const outward = azimuth => V(Math.cos(azimuth), 0, Math.sin(azimuth));
	const toward = (azimuth, radius, y) => outward(azimuth).multiplyScalar(radius).setY(y);

	// Tronçon de tige : deux triangles croisés, la base autour du départ, la pointe à l'arrivée ; la matière est vue des deux côtés
	const segment = (from, to, radius, tris) => {
		const axis = to.clone().sub(from).normalize();
		const u = V().crossVectors(axis, Math.abs(axis.y) > .9 ? V(1, 0, 0) : UP).normalize(), w = V().crossVectors(u, axis);
		for(const side of [u, w]) tris.push([from.clone().addScaledVector(side, radius).toArray(), from.clone().addScaledVector(side, -radius).toArray(), to.toArray()]);
	};

	// Feuille ovale à pointe effilée, pliée sur sa nervure : six triangles, la base, deux épaules au tiers (le plus large), deux
	// flancs qui se resserrent, la pointe. La nervure est relevée au-dessus des bords, et la feuille s'incurve vers le bas de
	// `droop` à sa pointe.
	const OUTLINE = [[.24, .95], [.62, .66]];
	const leaf = (base, azimuth, pitch, length, droop, tris) => {
		const dir = outward(azimuth).multiplyScalar(Math.cos(pitch)).addScaledVector(UP, Math.sin(pitch));
		const side = V().crossVectors(dir, UP).normalize(), normal = V().crossVectors(side, dir).normalize();
		const width = length * .7;
		const at = (u, v, lift) => base.clone().addScaledVector(dir, u * length).addScaledVector(side, v * width / 2)
			.addScaledVector(normal, lift * width).addScaledVector(UP, -droop * u * u).toArray();
		const b = at(0, 0, 0), m = at(.45, 0, .16), t = at(1, 0, 0);
		for(const s of [-1, 1]) {
			const e1 = at(OUTLINE[0][0], s * OUTLINE[0][1], 0), e2 = at(OUTLINE[1][0], s * OUTLINE[1][1], 0);
			tris.push([b, e1, m], [m, e1, e2], [m, e2, t]);
		}
	};

	// Trois tiges qui montent du pied : [azimut, coude, sommet], le coude et le sommet en [écart au pied, hauteur]
	const stems = [
		[.4, [.03, .9], [.06, 1.55]],
		[2.5, [.1, .8], [.2, 1.32]],
		[4.4, [.1, .7], [.17, 1.18]],
	];
	const stemTris = [], topLeaves = [], shadeLeaves = [];
	const tops = stems.map(([azimuth, elbow, top], rank) => {
		const e = toward(azimuth, ...elbow), t = toward(azimuth, ...top);
		// La troisième tige, la plus courte, monte d'un seul trait : son coude est sur la droite du pied au sommet
		if(rank === 2) segment(V(0, 0, 0), t, .02, stemTris);
		else {
			segment(V(0, 0, 0), e, .02, stemTris);
			segment(e, t, .014, stemTris);
		}
		return [azimuth, e, t];
	});

	// Huit rameaux qui partent des tiges et s'arquent vers l'extérieur : [tige, hauteur du départ, azimut en degrés, rayon et hauteur
	// du coude, rayon et hauteur du bout]. Le socle pose le centre des lanternes entre 0,15 et 0,25 de la hauteur (0,27 à 0,46 m) et
	// entre 0,5 et 0,58 de l'emprise, mesurée sur la boîte du plant selon x et z : un rameau tiré sur un de ces axes fixerait le bord
	// et laisserait toujours les lanternes au-delà de lui. Les rameaux s'écartent donc des axes d'au moins 30°, ce qui leur laisse
	// porter jusqu'à 0,75 m du pied, au droit des lanternes, sans élargir la boîte. La feuille du rameau, presque à plat, fait le
	// toit au-dessus de l'anneau, et le bout du rameau descend dessous jusqu'au haut des lanternes.
	const laterals = [
		[0, .28, 31, [.36, .6], [.7, .42]],
		[1, .24, 62, [.34, .56], [.69, .39]],
		[0, .36, 118, [.38, .64], [.71, .44]],
		[1, .26, 149, [.33, .58], [.7, .4]],
		[2, .22, 212, [.37, .57], [.71, .38]],
		[2, .32, 239, [.34, .62], [.69, .43]],
		[0, .2, 302, [.36, .55], [.7, .4]],
		[1, .34, 328, [.35, .61], [.71, .44]],
	];
	for(const [rank, [stem, y, degrees, elbow, tip]] of laterals.entries()) {
		const [, foot] = tops[stem];
		const azimuth = degrees * Math.PI / 180;
		const start = foot.clone().multiplyScalar(y / foot.y);
		const e = toward(azimuth, ...elbow), t = toward(azimuth, ...tip);
		segment(start, e, .016, stemTris);
		segment(e, t, .014, stemTris);
		// La feuille part du coude, tournée vers la diagonale la plus proche pour ne pas élargir la boîte, et couvre le bout du rameau
		const toDiagonal = Math.sign(45 - degrees % 90) * .12;
		leaf(e.clone().addScaledVector(UP, .02), azimuth + toDiagonal, -.1 - (rank % 3) * .05, .41 + (rank % 3) * .025, .15, shadeLeaves);
	}

	// Feuilles du haut, dressées et ouvertes autour du sommet des tiges : [tige, part de la hauteur du coude au sommet, écart à l'azimut, inclinaison, longueur]
	const uppers = [
		[0, 1, 0, 1.05, .46],
		[0, .4, 2.6, .45, .44],
		[1, 1, -.3, .8, .42],
		[1, .25, 1.7, .35, .42],
		[2, 1, .35, .7, .43],
	];
	for(const [stem, s, yaw, pitch, length] of uppers) {
		const [azimuth, e, t] = tops[stem];
		leaf(e.clone().lerp(t, s), azimuth + yaw, pitch, length, .06, topLeaves);
	}

	return {
		d, h,
		parts: [
			{ color: TOP, geometry: mesh(topLeaves) },
			{ color: SHADE, geometry: mesh(shadeLeaves) },
			{ color: STEM, sway: .5, geometry: mesh(stemTris) },
		],
	};
}
