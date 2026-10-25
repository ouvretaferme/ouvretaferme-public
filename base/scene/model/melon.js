// Melon, pensé pour l'écran de travail : un tapis bas et bombé de feuilles rondes vert sombre, à lobes peu profonds, d'où
// partent en étoile quelques coureurs plus clairs piqués de fleurs jaunes. Le feuillage reste sombre et dégage l'anneau où
// le socle couche les melons beige vert brodés : le plus fort contraste de l'objet est entre ce fruit et ce tapis.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : melon.reference.md.
// `d` vaut 0,4 et non l'emprise vraie de 0,8 à 1 m : FRUITS.melon pose les fruits à 46 % de l'emprise de scène, et au-delà
// de 0,52 le facteur de scène porte l'emprise à 1,3 m sur une planche de 1 m, les fruits sortiraient de la planche.
// La géométrie garde les proportions du plant entier ; `h` s'en déduit.
export function build(THREE, helpers) {
	const LEAF_LOW = '#2f5a24'; const LEAF_TOP = '#4c7d2e'; const VINE = '#86a24a'; const FLOWER = '#f2c230';

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };

	// Feuille ronde à cinq lobes peu profonds et base en cœur, en éventail depuis le point d'attache du pétiole. Le contour
	// d'une moitié se donne en polaire, [angle depuis la pointe en degrés, rayon], la gauche en miroir avec un léger décalage
	// pour que la feuille ne soit pas symétrique à l'identique. La nervure est relevée et les bords baissés (pli en V).
	// Posée pointe vers +z, cœur à l'origine, puis relevée de `tilt` (radians, vers le ciel quand il est positif).
	const HALF = [[0, 1], [24, .84], [46, 1], [76, .8], [106, .98], [136, .84], [160, .7], [177, .3]];
	const leaf = (size, tilt, rank) => {
		const sway = (jitter(rank, 5) - .5) * 10;
		const right = HALF.map(([a, r], k) => [a + (k > 0 && k < HALF.length - 1 ? sway : 0), r * (1 + (jitter(rank, k) - .5) * .12)]);
		const left = HALF.slice(1, -1).reverse().map(([a, r], k) => [-a - sway * .6, r * (1 + (jitter(rank, k + 9) - .5) * .12)]);
		const ring = [...right, [-HALF.at(-1)[0], HALF.at(-1)[1]], ...left];
		// La feuille est plus large que longue (réniforme) : l'écart latéral est majoré
		const point = ([a, r]) => {
			const x = Math.sin(a * Math.PI / 180) * r * size * 1.12, z = Math.cos(a * Math.PI / 180) * r * size * .5 + size * .42;
			return [x, -Math.abs(x) * .2, z];
		};
		const heart = [0, size * .05, size * .38];
		const tris = ring.map((p, i) => [heart, point(p), point(ring[(i + 1) % ring.length])]);
		return mesh(tris).rotateX(-tilt);
	};

	// Dôme : trois étages. Les feuilles basses, grandes et presque à plat, font le fond sombre ; celles du dessus, plus petites
	// et relevées vers le ciel, bombent le cœur. Le tapis s'arrête vers 36 % de l'emprise : l'anneau des fruits reste dégagé.
	// [azimut, distance au collet, hauteur, taille, relèvement]
	const low = [0, 1, 2, 3, 4, 5].map(i => [i * Math.PI * 2 / 6 + jitter(i, 1) * .5, .14 + jitter(i, 2) * .05, .045 + jitter(i, 3) * .03, .25 + jitter(i, 4) * .05, .22 + jitter(i, 6) * .18]);
	const top = [0, 1, 2, 3, 4].map(i => [i * Math.PI * 2 / 5 + .5 + jitter(i, 7) * .6, .07 + jitter(i, 8) * .03, .15 + jitter(i, 9) * .03, .16 + jitter(i, 10) * .03, .35 + jitter(i, 11) * .2]);
	// Une feuille au cœur, la plus haute, ferme le dôme : sans elle le tapis se lit en couronne vide vu d'en haut
	top.push([2.6, .01, .2, .15, .25]);
	const place = ([azimuth, r, y, size, tilt], rank) => leaf(size, tilt, rank).translate(0, y, r - size * .38).rotateY(azimuth);
	const lowLeaves = low.map((spot, i) => place(spot, i));
	const topLeaves = top.map((spot, i) => place(spot, i + 20));
	// Les feuilles du dessus tiennent en parasol sur un pétiole court : une lame du collet au cœur du limbe, sans quoi elles flottent
	const petioles = top.map(([azimuth, r, y, size, tilt]) => {
		const heart = [0, y + size * (.05 * Math.cos(tilt) + .38 * Math.sin(tilt)), r - size * .38 * (1 - Math.cos(tilt)) - size * .05 * Math.sin(tilt)];
		return mesh([[[-.012, .02, 0], heart, [.012, .02, 0]]]).rotateY(azimuth);
	});

	// Coureurs : quatre lames effilées couchées au sol, légèrement coudées, qui débordent du tapis jusqu'au bord de
	// l'emprise ; une petite feuille au bout de chacun. Leurs azimuts sont irréguliers, leurs longueurs aussi.
	const runners = [[.35, .5], [1.9, .44], [3.4, .48], [4.85, .4]];
	const vines = [], tips = [];
	for(const [rank, [azimuth, length]] of runners.entries()) {
		const bend = (jitter(rank, 12) - .5) * .5;
		const path = [0, .35, .7, 1].map(u => [Math.sin(bend * u * u) * length * u, .012 + .02 * Math.sin(Math.PI * u) * (1 - u), Math.cos(bend * u * u) * length * u]);
		const widths = [.02, .016, .011, .004];
		const tris = [];
		for(let k = 0; k < 3; k++) {
			const [a, b] = [path[k], path[k + 1]];
			const A = [a[0] - widths[k], a[1], a[2]], B = [a[0] + widths[k], a[1], a[2]];
			const C = [b[0] - widths[k + 1], b[1], b[2]], D = [b[0] + widths[k + 1], b[1], b[2]];
			tris.push([A, C, B], [B, C, D]);
		}
		vines.push(mesh(tris).rotateY(azimuth));
		const end = path[2];
		tips.push(leaf(.13 + jitter(rank, 13) * .03, .25, rank + 40).rotateY(bend * .6 + (jitter(rank, 14) - .5) * .8).translate(end[0], .03, end[2] - .02).rotateY(azimuth));
	}

	// Fleurs : étoiles jaunes à cinq pointes posées sur le tapis, cinq triangles chacune, tournées vers le ciel
	const star = (r, rank) => {
		const tris = [];
		for(let k = 0; k < 5; k++) {
			const a = k * Math.PI * 2 / 5 + rank, b = a + Math.PI * 2 / 5, m = (a + b) / 2;
			tris.push([[Math.cos(a) * r * .35, 0, Math.sin(a) * r * .35], [Math.cos(m) * r, .004, Math.sin(m) * r], [Math.cos(b) * r * .35, 0, Math.sin(b) * r * .35]]);
		}
		return mesh(tris);
	};
	const flowers = [[.6, .3, .095], [2.2, .27, .09], [3.9, .29, .085], [5.4, .25, .11], [1.3, .1, .15]].map(([azimuth, r, y], i) => star(.035, i).translate(0, y, r).rotateY(azimuth));

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF_LOW, geometry: merge([...lowLeaves, ...tips]) },
		{ color: LEAF_TOP, geometry: merge(topLeaves) },
		{ color: VINE, sway: .3, geometry: merge([...vines, ...petioles]) },
		{ color: FLOWER, geometry: merge(flowers) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .4;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
