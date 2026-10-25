// Mâche, version allégée : la même rosette plate de cuillères vert sombre que la version p, en dix feuilles et 60 triangles.
// La mâche est semée dense, plus de mille plants le même jour sur le site : chaque triangle se paie mille fois.
// Chaque feuille garde ce qui la fait lire cuillère : un limbe ovale à bout rond, creusé autour de son centre, et un
// pétiole noir-vert qui le détache des voisines. Le limbe passe de neuf pans à cinq, le pétiole reste un triangle.
// Contrat et budget : base/scene/CLAUDE.md. Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const NECK = '#163d1a', OUTER = '#22592a', INNER = '#3a843c', GLOSS = '#347238';

	// Contour d'une cuillère, en (avancée, demi-largeur rapportée à la plus grande) : le pétiole s'ouvre au tiers, la plus
	// grande largeur passe aux deux tiers, et le bout est un pan plat à mi-largeur. Sans sommet de pointe, les trois pans
	// du bout font un angle ouvert qui se lit rond ; un sommet au bout de la nervure ferait une pointe d'épinard.
	const NECK_AT = [.34, .16], EDGE = [[.68, 1], [.97, .56]], CENTER = .66;

	// Feuilles : [azimut en degrés, longueur, demi-largeur rapportée à la longueur, élévation au pied, retombée].
	// Huit feuilles couchées par paires croisées au tour, une paire plus courte et relevée au cœur, posée sur leur pied :
	// les deux feuilles retirées par rapport à la version p sont au cœur, que la vue de jeu ne lit pas. Aucune jumelle
	// n'est la copie de l'autre.
	const leaves = [
		[0, .06, .27, .34, .4], [186, .055, .29, .3, .36],
		[84, .058, .26, .32, .38], [273, .062, .28, .36, .42],
		[47, .051, .29, .3, .34], [222, .057, .27, .34, .4],
		[131, .054, .28, .32, .36], [323, .049, .29, .28, .32],
		[20, .038, .27, .7, .5], [204, .041, .26, .66, .46],
	];

	const necks = [], outer = [], inner = [], glosses = [];
	for(const [rank, [azimuth, length, width, rise, droop]] of leaves.entries()) {
		// Nervure : part du collet, monte de `rise` puis se couche vers le bout
		const along = u => {
			let x = 0, y = .001;
			const steps = 5;
			for(let s = 0; s < steps; s++) {
				const a = rise - droop * u * (s + .5) / steps;
				x += Math.cos(a) * length * u / steps;
				y += Math.sin(a) * length * u / steps;
			}
			return [x, y, rise - droop * u];
		};
		// Point du limbe : la cuillère est creusée, les bords relevés au-dessus de la nervure
		const point = (u, v, above = 0) => {
			const [x, y, a] = along(u), up = .45 * width * length * Math.abs(v) + above;
			return [x - Math.sin(a) * up, y + Math.cos(a) * up, v * width * length];
		};
		// 0 collet, 1 centre du limbe, puis le contour de gauche à droite en passant par le bout
		const contour = [[NECK_AT[0], -NECK_AT[1]], ...EDGE.map(([u, v]) => [u, -v]), ...EDGE.slice().reverse(), NECK_AT];
		const vertices = [point(0, 0), point(CENTER, 0, -.12 * width * length), ...contour.map(([u, v]) => point(u, v))];
		// Éventail autour du centre, un triangle par pan du contour, sans refermer le pied où passe le pétiole. Les
		// normales se calculent sur la cuillère entière avant d'en détacher le reflet : pas d'arête entre les deux couleurs.
		const fan = [];
		for(let i = 2; i < contour.length + 1; i++) fan.push([1, i + 1, i]);
		// Pétiole : un coin du collet au pied du limbe, dans la même géométrie pour partager le sommet du collet
		const stalk = [0, contour.length + 1, 2];
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(vertices.flat(), 3));
		g.setIndex([...fan.flat(), ...stalk]);
		g.computeVertexNormals();
		const angle = azimuth * Math.PI / 180;
		const piece = triangles => {
			const out = g.clone();
			out.setIndex(triangles.flat());
			return out.rotateY(angle);
		};
		// Reflet : le pan le plus large, d'un côté de la nervure ; le côté alterne
		const shine = rank % 2 ? 3 : 1;
		if(rank < 8) {
			outer.push(piece(fan.filter((_, i) => i !== shine)));
			glosses.push(piece([fan[shine]]));
		}
		else {
			inner.push(piece(fan));
		}
		necks.push(piece([stalk]));
	}

	const parts = [
		{ color: OUTER, sway: .1, geometry: helpers.merge(outer) },
		{ color: INNER, sway: .1, geometry: helpers.merge(inner) },
		{ color: GLOSS, sway: .1, geometry: helpers.merge(glosses) },
		{ color: NECK, sway: .05, geometry: helpers.merge(necks) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .12;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
