// Framboisier, pensé pour l'écran de travail : un rang de cannes brun rouge palissées entre deux fils tendus, qui fait avec ses
// voisines un mur de feuillage étroit, haut et plat. Les cannes sortent du sol le long du rang, montent dressées et nues sur le
// bas, et leurs pointes s'arquent au-dessus des fils. Les feuilles composées à trois folioles pointues couvrent tout le haut des
// deux faces ; le vent en retourne quelques-unes, dont les folioles latérales montrent leur revers blanchâtre. De courts latéraux
// sortent des cannes à travers le mur, et les framboises pendent à leur bout, posées par le socle : un fruit isolé au bout d'un
// rameau, et non une grappe serrée, c'est ce qui sépare le framboisier du groseillier.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : framboise.reference.md.
export function build(THREE, helpers) {
	const SHADE = '#2f5a2a'; const LEAF = '#5f9640'; const PALE = '#c9cfbd'; const CANE = '#8a4636';

	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Hasard figé : la même plante à chaque chargement
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };
	const up = new THREE.Vector3(0, 1, 0);

	// Foliole ovale et acuminée, pliée le long de la nervure : contour [écart, avancée] d'une moitié, en fraction de la longueur.
	// Le large tôt et le resserrement vers la pointe la séparent d'un losange. Éventail depuis le point d'attache, quatre
	// triangles, dont deux bordent la nervure : le pli y passe
	const HALF = [[.4, .34], [.22, .72]];
	const leaflet = (length, rank) => {
		const lean = (jitter(rank, 1) - .5) * .12;
		const side = sign => HALF.map(([w, z], k) => [sign * w * (1 + (jitter(rank, k + (sign > 0 ? 2 : 6)) - .5) * .3), z]);
		const ring = [...side(-1), [lean, 1], ...side(1).reverse()];
		const point = ([w, z]) => [w * length, -Math.abs(w) * length * .5 - z * z * length * .18, z * length];
		const tris = [];
		for(let k = 0; k < ring.length - 1; k++) tris.push([[0, 0, 0], point(ring[k + 1]), point(ring[k])]);
		return tris;
	};
	// Feuille composée, pointe vers +z, face vers +y : une foliole terminale au bout du rachis, et une paire de latérales plus
	// petites, ouvertes à 55-75°. Quand le vent la retourne, les latérales se vrillent sur leur nervure et montrent leur revers :
	// elles sont rendues à part pour en prendre la couleur. Douze triangles
	const leaf = (size, rank, turned) => {
		const terminal = [], laterals = [];
		const put = (list, target, angle, at, twist = 0) => {
			const m = new THREE.Matrix4().makeRotationY(angle).setPosition(0, 0, at).multiply(new THREE.Matrix4().makeRotationZ(twist));
			for(const t of list) target.push(t.map(p => new THREE.Vector3(...p).applyMatrix4(m).toArray()));
		};
		put(leaflet(size * .62, rank * 3), terminal, 0, size * .38);
		const open = 1 + (jitter(rank, 9) - .5) * .35, twist = turned ? .9 : 0;
		put(leaflet(size * .5 * (1 + (jitter(rank, 10) - .5) * .2), rank * 3 + 1), laterals, open, size * .2, twist);
		put(leaflet(size * .5 * (1 + (jitter(rank, 11) - .5) * .2), rank * 3 + 2), laterals, -open * (.85 + jitter(rank, 12) * .25), size * .2, -twist);
		return [mesh(terminal), mesh(laterals)];
	};

	// Matrice qui pose une feuille construite face +y, pointe +z, au point `at` : la pointe part selon `out` et penche de `droop`
	// vers le sol, la face se relève de `lift` vers le ciel
	const placing = (at, out, lift, droop) => {
		const normal = out.clone().addScaledVector(up, lift).normalize();
		const reach = out.clone().setY(-droop);
		reach.addScaledVector(normal, -reach.dot(normal)).normalize();
		const right = new THREE.Vector3().crossVectors(normal, reach);
		return new THREE.Matrix4().makeBasis(right, normal, reach).setPosition(at);
	};

	// Tube à trois pans sur un chemin de points, effilé de r0 à r1, six triangles par tronçon
	const tube = (path, r0, r1) => {
		const tris = [];
		const rings = path.map((p, k) => {
			const r = r0 + (r1 - r0) * k / (path.length - 1);
			const dir = path[Math.min(k + 1, path.length - 1)].clone().sub(path[Math.max(k - 1, 0)]).normalize();
			const a = new THREE.Vector3(Math.abs(dir.x) > .9 ? 0 : 1, Math.abs(dir.x) > .9 ? 1 : 0, 0).cross(dir).normalize(), b = dir.clone().cross(a);
			return [0, 1, 2].map(j => p.clone().addScaledVector(a, Math.cos(j * 2.094) * r).addScaledVector(b, Math.sin(j * 2.094) * r).toArray());
		});
		for(let k = 0; k < rings.length - 1; k++) {
			for(let j = 0; j < 3; j++) {
				const [a, b, c, d] = [rings[k][j], rings[k][(j + 1) % 3], rings[k + 1][(j + 1) % 3], rings[k + 1][j]];
				tris.push([a, b, c], [a, c, d]);
			}
		}
		return tris;
	};
	// Latéral fruitier : une pointe à trois pans, du bord de la canne où il naît jusqu'au point où pend la framboise, trois triangles.
	// La base ouverte reste dans la canne
	const spur = (base, tip, r) => {
		const dir = tip.clone().sub(base).normalize();
		const a = new THREE.Vector3(0, 1, 0).cross(dir).normalize(), b = dir.clone().cross(a);
		const ring = [0, 1, 2].map(j => base.clone().addScaledVector(a, Math.cos(j * 2.094) * r).addScaledVector(b, Math.sin(j * 2.094) * r).toArray());
		return [0, 1, 2].map(j => [ring[j], ring[(j + 1) % 3], tip.toArray()]);
	};

	// Les cannes : [x au pied, x au sommet, z du sommet entre les fils, hauteur, côté où la pointe s'arque]. Les drageons sortent le
	// long du rang, pas d'une souche : les pieds s'étalent sur x et les cannes montent dressées, presque parallèles, en une bande
	// verticale tenue entre les fils jusqu'au-dessus de 1,1 m. C'est ce qui sépare le framboisier du V ouvert de la mûre, couchée en
	// éventail depuis un seul pied
	const CANES = [[-.21, -.24, .04, 1.58, 1], [-.12, -.14, -.06, 1.5, -1], [-.04, -.03, .06, 1.62, 1], [.05, .07, -.05, 1.46, -1], [.13, .15, .03, 1.56, -1], [.21, .24, -.04, 1.4, 1]];
	const canes = [], spurs = [], shade = [], light = [], pale = [], paths = [];
	for(const [rank, [x0, x1, z1, height, side]] of CANES.entries()) {
		const base = new THREE.Vector3(x0, 0, (jitter(rank, 20) - .5) * .06);
		const mid = new THREE.Vector3(x0 + (x1 - x0) * .55, height * .62, z1 * .6);
		const top = new THREE.Vector3(x1, height, z1 + side * .12);
		canes.push(...tube([base, mid, top], .014, .005));
		const along = u => u < .62 ? base.clone().lerp(mid, u / .62) : mid.clone().lerp(top, (u - .62) / .38);
		paths.push(along);

		// Feuilles alternes du tiers bas jusqu'en haut, sur les deux faces de la haie, rabattues vers le sol le long du mur : le bas
		// reste nu et montre les cannes, le reste fait mur. Les plus basses, sombres, font le fond. Sur les cannes impaires, la feuille
		// de la pointe, retournée par l'arcure, montre le revers de ses latérales. Trois feuilles sur les cannes paires, deux sur les
		// impaires : 15 feuilles, 180 triangles
		const stops = rank % 2 ? [.52, .98] : [.38, .62, .86];
		for(const [k, u] of stops.entries()) {
			const at = along(u + (jitter(rank, k + 30) - .5) * .05);
			const face = (k + rank) % 2 ? 1 : -1;
			const tip = u > .95;
			// Sur les cannes du bout, les feuilles partent aussi le long du rang et allongent la haie. Celle de la pointe se dresse dans
			// le fil de la canne et en couvre le bout
			const out = new THREE.Vector3((jitter(rank, k + 40) - .5) * .5 + x0 * 1.2, 0, tip ? side * .6 : face).normalize();
			const size = (tip ? .34 : .44) * (.88 + jitter(rank, k + 50) * .24);
			const turned = tip;
			const m = placing(at, out, tip ? .6 : .25 + jitter(rank, k + 60) * .3, tip ? -1.2 : .6);
			const [terminal, laterals] = leaf(size, rank * 4 + k, turned).map(g => g.applyMatrix4(m));
			(k === 0 ? shade : light).push(terminal);
			(turned ? pale : k === 0 ? shade : light).push(laterals);
		}
	}

	// Les latéraux fruitiers, dans la moitié haute : [canne, avancée sur la canne, face]. Ils sortent du mur à travers les fils, un
	// peu relevés, pour que la framboise pende à leur bout devant le feuillage et non dedans. Le socle y accroche les fruits (FRUITS)
	const SPURS = [[0, .58, 1], [0, .78, -1], [1, .66, -1], [2, .52, -1], [2, .74, 1], [3, .6, 1], [3, .8, -1], [4, .56, 1], [4, .76, -1], [5, .7, -1]];
	for(const [rank, u, face] of SPURS) {
		const from = paths[rank](u);
		const to = from.clone().add(new THREE.Vector3((jitter(rank, u * 10) - .5) * .08, .04, face * .2 - from.z * .3));
		spurs.push(...spur(from, to, .008));
	}

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: CANE, sway: .2, geometry: mesh([...canes, ...spurs]) },
		{ color: SHADE, sway: .5, geometry: merge(shade) },
		{ color: LEAF, geometry: merge(light) },
		{ color: PALE, geometry: merge(pale) },
	];
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	// Les deux fils du palissage, un de chaque côté du rang, à 1,1 m, d'un bout à l'autre de l'emprise du feuillage : les fils des
	// pieds voisins les prolongent et font la ligne du rang. Les cannes passent entre eux
	const WIRE_Y = 1.1, WIRE_Z = .2;
	const wires = [-1, 1].flatMap(side => tube([new THREE.Vector3(box.min.x + .01, WIRE_Y, side * WIRE_Z), new THREE.Vector3(box.max.x - .01, WIRE_Y, side * WIRE_Z)], .01, .01));
	parts.push({ color: PALE, sway: 0, geometry: mesh(wires) });
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const size = box.getSize(new THREE.Vector3()), d = .7;
	return { d, h: d * size.y / Math.max(size.x, size.z), aligned: true, parts };
}
