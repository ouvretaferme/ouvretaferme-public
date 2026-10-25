// Groseillier à grappes, pensé pour se lire à une quarantaine de pixels de haut : un gobelet de huit charpentières gris-brun
// qui partent d'une souche étroite, s'ouvrent puis se redressent, nues sur le premier tiers. Le feuillage, fait de feuilles
// palmées à cinq lobes pointus, vert franc et clair en tête, plus sombre dessous, se tient en bouquets étagés le long du haut
// des branches : la tête est plus large que le pied, le centre reste aéré. Sous le premier étage de feuilles, le long des
// coudes des branches, un étage dégagé où le socle pend les grappes rouges.
// Le cassissier voisin est une touffe ronde, sombre, garnie jusqu'au sol : le groseillier s'en sépare par le pied nu, la tête
// évasée et le vert clair.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : groseille.reference.md.
export function build(THREE) {
	const LIGHT = '#7fb043'; const SHADE = '#4c7e2c'; const WOOD = '#6b5a48';

	// Sur une planche de 1 m, la scène affiche d = 1,0 et h = 1,2 en 1,3 m d'emprise sur 1,95 m de haut (plantSize, socle.js) :
	// le modèle se dessine dans ces proportions de scène, pour que ses feuilles n'y soient pas étirées.
	const d = 1.0, h = 1.2;

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
	// Écart pseudo-aléatoire stable entre -1 et 1 : branches et feuilles varient sans changer d'un rechargement à l'autre
	const jitter = n => {
		const x = Math.sin(n * 12.9898 + 4.1414) * 43758.5453;
		return (x - Math.floor(x)) * 2 - 1;
	};

	// Bois du bas : tronc de pyramide à trois pans, ouvert, vu sous tous les angles
	const prism = (from, to, r0, r1, tris) => {
		const axis = to.clone().sub(from).normalize();
		const u = V().crossVectors(axis, Math.abs(axis.y) > .9 ? V(1, 0, 0) : UP).normalize(), w = V().crossVectors(u, axis);
		const ring = (center, r) => [0, 1, 2].map(k => {
			const a = k * Math.PI * 2 / 3;
			return center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(w, Math.sin(a) * r).toArray();
		});
		const a = ring(from, r0), b = ring(to, r1);
		for(let k = 0; k < 3; k++) {
			const n = (k + 1) % 3;
			tris.push([a[k], b[k], b[n]], [a[k], b[n], a[n]]);
		}
	};
	// Bois du haut, caché pour l'essentiel par les feuilles : une lame d'un triangle, tournée vers l'axe de la touffe pour se voir
	// de profil depuis l'extérieur, la pointe à l'arrivée
	const spike = (from, to, r, tris) => {
		const axis = to.clone().sub(from).normalize();
		const side = V().crossVectors(axis, UP).normalize();
		tris.push([from.clone().addScaledVector(side, r).toArray(), from.clone().addScaledVector(side, -r).toArray(), to.toArray()]);
	};

	// Feuille palmée à cinq lobes larges et pointus, plus large que longue : un éventail de dix triangles autour d'un centre relevé, le
	// contour passe de pointe en sinus peu profond : des lobes de groseillier, pas les doigts d'un érable. Lobe terminal le plus long, deux latéraux ouverts, deux basaux courts tournés vers
	// l'arrière. Les pointes retombent de `droop`.
	const OUTLINE = [[.05, 0], [.14, .66], [.34, .44], [.6, .8], [.62, .3], [1, 0]];
	const leaf = (base, azimuth, pitch, length, droop, tris, seed) => {
		const dir = outward(azimuth).multiplyScalar(Math.cos(pitch)).addScaledVector(UP, Math.sin(pitch));
		const side = V().crossVectors(dir, UP).normalize(), normal = V().crossVectors(side, dir).normalize();
		const width = length * 1.15 * (1 + .12 * jitter(seed));
		const at = (u, v, lift) => base.clone().addScaledVector(dir, u * length).addScaledVector(side, v * width / 2)
			.addScaledVector(normal, lift * length).addScaledVector(UP, -droop * u * u).toArray();
		const center = at(.42, 0, .1);
		const ring = [
			...OUTLINE.map(([u, v]) => at(u, -v, 0)),
			...OUTLINE.slice(0, -1).reverse().map(([u, v]) => at(u, v, 0)),
		];
		for(let k = 0; k < ring.length - 1; k++) tris.push([center, ring[k], ring[k + 1]]);
	};

	// Sept charpentières : [azimut en degrés, rayon et hauteur du coude, rayon et hauteur de la tête]. Le coude est sous le premier
	// étage de feuilles ; les têtes finissent à des hauteurs inégales pour que le haut du gobelet ne soit pas une coupe nette.
	const branches = [
		[10, [.28, .46], [.5, 1.8]],
		[62, [.25, .42], [.45, 1.62]],
		[114, [.3, .5], [.53, 1.74]],
		[163, [.26, .44], [.48, 1.88]],
		[211, [.29, .4], [.52, 1.66]],
		[262, [.24, .48], [.44, 1.82]],
		[311, [.31, .43], [.54, 1.7]],
	];
	const wood = [], light = [], shade = [];
	branches.forEach(([degrees, elbow, top], i) => {
		const azimuth = degrees * Math.PI / 180;
		const foot = toward(azimuth, .04, 0), e = toward(azimuth, ...elbow), t = toward(azimuth, ...top);
		prism(foot, e, .035, .022, wood);
		spike(e, t, .018, wood);
		const along = s => e.clone().lerp(t, s);
		const side = i % 2 ? 1 : -1;
		// Les feuilles se dressent face au dehors : vues de flanc comme de trois quarts, elles font une paroi de feuillage et non
		// une collection de lames vues par la tranche.
		// Feuille du bas, à l'ombre, qui fait le toit au-dessus des grappes
		leaf(along(.22), azimuth - side * (.55 + .15 * jitter(i + 70)), .55 + .1 * jitter(i + 80), .56 + .04 * jitter(i + 90), .14, shade, i + 100);
		// Feuille du milieu, tournée vers la branche voisine pour fermer le creux entre deux
		leaf(along(.5), azimuth + side * (.8 + .2 * jitter(i + 30)), .7 + .12 * jitter(i + 40), .6 + .04 * jitter(i + 50), .12, i % 3 ? light : shade, i + 60);
		// Feuille de tête, dressée
		leaf(along(.86), azimuth + .3 * jitter(i), 1 + .12 * jitter(i + 10), .56 + .04 * jitter(i + 20), .1, light, i);
	});
	// Quatre feuilles du dedans, en haut, accrochées entre deux branches : la tête du gobelet se lit pleine en silhouette, le pied reste nu
	[40, 140, 235, 330].forEach((degrees, k) => {
		const azimuth = degrees * Math.PI / 180;
		leaf(toward(azimuth, .2, 1.32 + .1 * jitter(k + 300)), azimuth, 1.05 + .1 * jitter(k + 310), .54, .08, k % 2 ? light : shade, k + 320);
	});

	return {
		d, h,
		parts: [
			{ color: LIGHT, geometry: mesh(light) },
			{ color: SHADE, geometry: mesh(shade) },
			{ color: WOOD, sway: 0, geometry: mesh(wood) },
		],
	};
}
