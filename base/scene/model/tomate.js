// Tomate, pensée pour se lire à une trentaine de pixels de large : un tuteur de bois clair, droit, qui dépasse la tête,
// et contre lui sept feuilles composées vert sombre qui partent vers le haut puis retombent en arc, chacune faite de
// folioles étroites, pointues et dentées, séparées par des vides. Les feuilles sont groupées en trois étages, et les deux
// bandes qui les séparent restent ouvertes : c'est là que le socle pose les grappes rouges (FRUITS.tomate, à 0,35 et 0,6
// de la hauteur), qui doivent se détacher sur le vert sombre avec le plus fort contraste de la planche. Le pied reste nu.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Fiche : tomate.reference.md.
export function build(THREE, helpers) {
	const STAKE = '#c9a066'; const LEAF = '#3a6e27'; const SHADE = '#24501c';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (la nervure pliée prend deux lumières)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const V = (...a) => new THREE.Vector3(...a);
	const UP = V(0, 1, 0);

	// Repère d'une foliole : d le long de la nervure, w en travers, n la normale ; les bords sont baissés sous la nervure (pli en V)
	const frame = direction => {
		const d = direction.clone().normalize();
		const w = V().crossVectors(d, UP);
		if(w.lengthSq() < 1e-4) w.set(1, 0, 0);
		w.normalize();
		return { d, w, n: V().crossVectors(w, d).normalize() };
	};
	// Foliole latérale, étroite et pointue : un éventail de trois triangles depuis sa base. Le côté denté porte une dent puis
	// un sinus avant la pointe, le côté d'en face un seul cran ; `toothed` (±1) choisit le côté denté.
	const leaflet = (base, direction, length, width, toothed, tris) => {
		const { d, w, n } = frame(direction);
		const at = (u, v) => base.clone().addScaledVector(d, u * length).addScaledVector(w, v * width * toothed).addScaledVector(n, -width * .35 * Math.abs(v)).toArray();
		const o = base.toArray();
		tris.push([o, at(.38, 1), at(.62, .4)], [o, at(.62, .4), at(1, 0)], [o, at(1, 0), at(.48, -.8)]);
	};
	// Foliole terminale, la plus grande, qui pend au bout du rachis : une dent de chaque côté, la pointe en bas
	const terminal = (base, direction, length, width, tris) => {
		const { d, w, n } = frame(direction);
		const at = (u, v) => base.clone().addScaledVector(d, u * length).addScaledVector(w, v * width).addScaledVector(n, -width * .35 * Math.abs(v)).toArray();
		const o = base.toArray();
		tris.push([o, at(.42, 1), at(1, 0)], [o, at(1, 0), at(.6, -.55)], [o, at(.6, -.55), at(.3, -.9)]);
	};

	// Sept feuilles en trois étages autour du tuteur, la tige qu'il porte étant invisible en jeu : deux en bas sous la grappe
	// basse, deux entre les grappes, trois en tête au-dessus de la grappe haute. Chacune varie en azimut, ouverture,
	// longueur et retombée. [hauteur du nœud, azimut, ouverture depuis la verticale, longueur, retombée]
	const leaves = [
		[.42, .4, .8, .6, 1.7],
		[.52, 3.3, .75, .58, 1.85],
		[.9, 1.75, .7, .6, 1.95],
		[.98, 4.95, .75, .56, 2.1],
		[1.24, 2.9, .65, .52, 1.85],
		[1.34, .15, .6, .46, 1.9],
		[1.44, 4.3, .45, .36, 1.6],
	];
	const shade = [], light = [];
	for(const [rank, [y, azimuth, open, length, droop]] of leaves.entries()) {
		const out = V(Math.cos(azimuth), 0, Math.sin(azimuth));
		const side = V().crossVectors(UP, out).normalize();
		const node = V(0, y, 0).addScaledVector(out, .03);
		// Le pétiole monte vers l'extérieur, la feuille s'arque : chaque segment penche un peu plus que le précédent
		const direction = s => out.clone().multiplyScalar(Math.sin(open + droop * s)).addScaledVector(UP, Math.cos(open + droop * s));
		const spine = [node.clone()];
		for(let k = 1; k <= 4; k++) spine.push(spine[k - 1].clone().addScaledVector(direction((k - .5) / 4), length / 4));
		const tris = [];
		// Rachis : un filet mince du nœud au milieu de l'arc
		const rib = .01;
		tris.push([spine[0].clone().addScaledVector(side, rib).toArray(), spine[0].clone().addScaledVector(side, -rib).toArray(), spine[2].toArray()]);
		// Deux paires de folioles, la seconde plus longue, ouvertes de part et d'autre du rachis et penchées vers le sol ;
		// chaque foliole varie en longueur d'après son rang, et le côté denté alterne d'une paire à l'autre
		for(const [k, spread, size] of [[1, 1.2, .5], [3, 1, .6]]) {
			for(const sign of [-1, 1]) {
				const along = direction(k / 4);
				const lean = along.clone().multiplyScalar(Math.cos(spread)).addScaledVector(side, sign * Math.sin(spread)).addScaledVector(UP, -.55 - .15 * k);
				const jitter = 1 + .15 * Math.sin(rank * 2.3 + k * 1.7 + sign);
				leaflet(spine[k], lean, length * size * jitter, length * size * .46 * jitter, sign * (rank % 2 ? 1 : -1) * (k === 1 ? 1 : -1), tris);
			}
		}
		terminal(spine[4], direction(1.2), length * .62, length * .26, tris);
		(rank < 2 ? shade : light).push(mesh(tris));
	}

	// Tuteur à quatre pans, assez épais pour faire deux pixels en jeu, qui dépasse la tête d'une vingtaine de centimètres
	const stake = helpers.stem(.03, .03, 1.78, 4).translate(0, .89, 0);
	const parts = [
		{ color: STAKE, sway: 0, geometry: stake },
		{ color: SHADE, geometry: helpers.merge(shade) },
		{ color: LEAF, geometry: helpers.merge(light) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = Math.max(size.x, size.z);
	return { d, h: size.y, aligned: true, parts };
}
