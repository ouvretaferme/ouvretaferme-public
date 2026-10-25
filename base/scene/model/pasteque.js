// Pastèque, pensée pour se lire à une vingtaine de pixels : quatre coureurs pâles couchés en étoile au ras du sol, et le long
// d'eux six grandes feuilles vert bleuté découpées jusqu'à la nervure en lobes pennés, relevées sur leur pétiole. Peu de
// pièces, mais grosses : c'est la découpe de chaque feuille qui la sépare du melon (feuille ronde), de la courgette (main
// dressée) et du concombre (cœur à pointes), et le port couché qui la sépare des deux derniers.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les pastèques mûres sont posées par le socle (famille « rampant »).
// `d` est l'emprise d'une butte, pas l'étalement réel des coureurs (2 à 4 m) : trois plants par planche de 4 m (RENDU-lot3.md).
export function build(THREE, helpers) {
	const LEAF = '#3d6a58'; const YOUNG = '#6f9a7f'; const VINE = '#b3c98a'; const FLOWER = '#f2c230';

	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };

	// Géométrie libre : une liste de triangles [[x, y, z] × 3]. Chaque triangle est retourné face vers le haut avant la pose,
	// pour que l'éclairage ne dépende pas du sens dans lequel le contour a été parcouru ; les triangles plats sont écartés.
	const mesh = tris => {
		const kept = [];
		for(const [a, b, c] of tris) {
			const u = [b[0] - a[0], b[1] - a[1], b[2] - a[2]], v = [c[0] - a[0], c[1] - a[1], c[2] - a[2]];
			const n = [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
			if(Math.hypot(...n) < 1e-7) continue;
			kept.push(n[1] >= 0 ? [a, b, c] : [a, c, b]);
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(kept.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};

	// Contour d'une moitié de limbe, en (avancée, écart, point de la nervure auquel il se rattache) : un lobe basal en deux
	// lobules, un lobe médian en deux lobules, puis le lobe terminal. Chaque lobe pointe vers l'avant, pour que la feuille
	// garde son contour triangulaire, et les sinus sont des U ouverts qui descendent presque à la nervure : c'est la terre vue
	// entre les lobes qui fait lire la découpe. Le rattachement groupe les points d'un lobe sur un point de nervure, un éventail
	// par lobe.
	const OUTLINE = [
		[0, 0, 0],
		[.04, .3, .12], [.16, .68, .12], [.3, .96, .12], [.36, .84, .12], [.33, .6, .12], [.44, .7, .12], [.44, .4, .12],
		[.42, .1, .42], [.49, .1, .42],
		[.6, .52, .6], [.72, .66, .6], [.73, .5, .6], [.77, .48, .6], [.76, .3, .6],
		[.75, .08, .76], [.79, .08, .76],
		[.88, .3, .9], [.93, .28, .9], [.97, .15, .9], [1, 0, .9],
	];
	// Limbe posé pétiole à l'origine, pointe vers +z, nervure relevée et bords baissés (pli en V). Chaque demi-feuille déplace
	// ses lobules d'après son rang pour qu'aucun limbe ne soit le décalque d'un autre ni symétrique à l'identique.
	const blade = (length, width, rank) => {
		const point = (u, v) => [v * width * length, .14 * width * length * (1 - Math.abs(v)), u * length];
		const tris = [];
		for(const side of [-1, 1]) {
			const p = OUTLINE.map(([u, v, a], k) => {
				const shift = k === 0 || k === OUTLINE.length - 1 ? 0 : (jitter(rank * 2 + (side > 0 ? 1 : 0), k) - .5) * .3;
				return [point(u + shift * .12, side * v * (1 + shift)), a];
			});
			for(let i = 0; i < p.length - 1; i++) {
				const [A, a] = p[i], [B, b] = p[i + 1];
				tris.push([point(a, 0), A, B]);
				if(b !== a) tris.push([point(a, 0), B, point(b, 0)]);
			}
		}
		return mesh(tris);
	};

	// Coureur : un ruban couché en deux tronçons, le second dévié, qui part du collet [azimut, longueur, déviation]
	const RUNNERS = [[.35, .52, .35], [1.95, .44, -.4], [3.45, .56, .28], [4.95, .47, -.3]];
	const along = ([azimuth, length, bend], s) => {
		const half = length / 2;
		if(s <= half) return [Math.sin(azimuth) * s, Math.cos(azimuth) * s, azimuth];
		const turn = azimuth + bend;
		return [Math.sin(azimuth) * half + Math.sin(turn) * (s - half), Math.cos(azimuth) * half + Math.cos(turn) * (s - half), turn];
	};
	const vines = RUNNERS.map(runner => {
		const w = .02, ys = .012, tris = [];
		const edge = s => {
			const [x, z, a] = along(runner, s);
			return [[x - Math.cos(a) * w, ys, z + Math.sin(a) * w], [x + Math.cos(a) * w, ys, z - Math.sin(a) * w]];
		};
		const [l0, r0] = edge(0), [l1, r1] = edge(runner[1] / 2), [l2, r2] = edge(runner[1]);
		tris.push([l0, r0, l1], [r0, r1, l1], [l1, r1, [(l2[0] + r2[0]) / 2, ys, (l2[2] + r2[2]) / 2]]);
		return mesh(tris);
	});

	// Feuilles : [coureur, distance au collet, côté, longueur, ouverture au-dessus du sol, jeune]. Près du collet, elles se
	// relèvent davantage ; au bout des coureurs, elles sont plus petites, plus plates et plus pâles.
	const LEAVES = [
		[0, .16, 1, .36, .3, false],
		[1, .2, -1, .33, .24, false],
		[2, .14, 1, .37, .36, false],
		[3, .38, 1, .27, .16, true],
		[0, .42, -1, .26, .14, true],
	];
	const old = [], young = [], stalks = [];
	for(const [rank, [r, s, side, length, open, isYoung]] of LEAVES.entries()) {
		const [x, z, heading] = along(RUNNERS[r], s);
		const azimuth = heading + side * (.75 + jitter(rank, 3) * .3);
		const rise = .03 + open * .12;
		const g = blade(length, .44 + jitter(rank, 4) * .08, rank)
			.rotateX(-open)
			.rotateY(azimuth)
			.translate(x + Math.sin(azimuth) * .02, rise, z + Math.cos(azimuth) * .02);
		(isYoung ? young : old).push(g);
		// Pétiole : une lame du coureur jusqu'à la base du limbe
		const foot = [x, .012, z], head = [x + Math.sin(azimuth) * .02, rise, z + Math.cos(azimuth) * .02];
		const n = .01;
		stalks.push(mesh([[[foot[0] - Math.cos(azimuth) * n, foot[1], foot[2] + Math.sin(azimuth) * n], [foot[0] + Math.cos(azimuth) * n, foot[1], foot[2] - Math.sin(azimuth) * n], head]]));
	}

	// Fleurs : deux, jaunes, à l'aisselle d'une feuille, au ras du feuillage
	const flowers = [[1, .3], [3, .34]].map(([r, s], i) => {
		const [x, z] = along(RUNNERS[r], s);
		return helpers.oct(.026 + i * .004).translate(x, .05, z);
	});

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF, geometry: merge(old) },
		{ color: YOUNG, geometry: merge(young) },
		{ color: VINE, sway: .3, geometry: merge([...vines, ...stalks]) },
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
