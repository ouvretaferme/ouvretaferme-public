// Pomme de terre, parti pris B : un port étalé et retombant, plus large que haut au niveau des feuilles, que des bouquets de fleurs
// blanches à cœur jaune dépassent sur des hampes fines. La butte est posée par le socle (RIDGES), le plant n'en dessine pas.
// Huit feuilles pennées partent du collet, montent, s'arquent vers l'extérieur et retombent en bord de touffe : vues de dessus, leurs
// folioles font un contour déchiqueté, sans dôme. Les pétioles et les hampes sont sombres : le feuillage se lit vert sombre et
// ouvert, et le seul clair est celui des fleurs, au-dessus.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const DEEP = '#24491f'; const LEAF = '#376c2c'; const FLOWER = '#f4efe0'; const HEART = '#e8c440';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const add = (a, b, k = 1) => a.map((v, i) => v + b[i] * k);
	const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
	const unit = a => {
		const l = Math.hypot(...a);
		return a.map(v => v / l);
	};

	// Une foliole ovale pointue, pliée sur la nervure : la base, la pointe, et deux bords baissés au tiers de la longueur, 2 triangles
	const leaflet = (base, dir, normal, length, width) => {
		const tip = add(base, dir, length), side = unit(cross(normal, dir));
		const mid = add(add(base, dir, length * .42), normal, -width * .22);
		return [[base, add(mid, side, width / 2), tip], [base, tip, add(mid, side, -width / 2)]];
	};

	// Les feuilles : [azimut, portée, hauteur du sommet de l'arc, hauteur de la pointe, taille des folioles].
	// Six longues retombent jusqu'au bord, deux courtes restent dressées au centre pour que la touffe ne soit pas creuse vue de
	// dessus. Les azimuts sont inégalement espacés et les portées, sommets et pointes varient d'au moins 15 % d'une feuille à l'autre.
	const fronds = [
		[.15, .5, .5, .26, 1.05],
		[1.0, .42, .57, .33, .92],
		[1.85, .53, .46, .2, 1.1],
		[2.9, .45, .53, .28, .96],
		[3.85, .5, .48, .22, 1.02],
		[5.0, .4, .55, .31, .9],
		[.6, .2, .6, .5, .8],
		[3.4, .17, .63, .56, .72],
	];
	const blades = [], ribs = [];
	for(const [azimuth, reach, top, tipY, size] of fronds) {
		const radial = [Math.cos(azimuth), 0, Math.sin(azimuth)], across = [-Math.sin(azimuth), 0, Math.cos(azimuth)];
		// Le rachis est un arc de Bézier dans le plan vertical de l'azimut : du collet, il monte au sommet vers 40 % de la portée, puis
		// retombe jusqu'à la pointe
		const p0 = [.03, .14], p1 = [.03 + reach * .35, top * 2 - (.14 + tipY) / 2], p2 = [.03 + reach, tipY];
		const at = s => {
			const r = (1 - s) ** 2 * p0[0] + 2 * s * (1 - s) * p1[0] + s * s * p2[0];
			const y = (1 - s) ** 2 * p0[1] + 2 * s * (1 - s) * p1[1] + s * s * p2[1];
			return add(radial.map(v => v * r), [0, y, 0]);
		};
		const tangentAt = s => unit(add(at(Math.min(s + .02, 1)), at(Math.max(s - .02, 0)), -1));
		const normalAt = s => {
			const n = unit(cross(tangentAt(s), across));
			return n[1] < 0 ? n.map(v => -v) : n;
		};
		// Le pétiole, une lame sombre et fine du collet jusqu'à la première paire : au-delà, les folioles se touchent et cachent le rachis
		ribs.push([add(at(0), across, .014), at(.31), add(at(0), across, -.014)]);
		// Deux paires latérales, la seconde plus grande, qui s'écartent du rachis vers l'avant, et la terminale, la plus grande
		for(const [s, spread, scale] of [[.3, 1.2, .9], [.54, 1.0, 1.0]]) {
			const t = tangentAt(s), n = normalAt(s);
			for(const side of [-1, 1]) {
				const dir = unit(add(add(t.map(v => v * Math.cos(spread)), across, side * Math.sin(spread)), n, .12));
				blades.push(...leaflet(at(s), dir, n, .22 * scale * size, .18 * scale * size));
			}
		}
		// La terminale prolonge l'arc mais se relève vers l'horizontale, pour fermer le bout de la feuille au lieu de pendre seule
		const n = normalAt(.78);
		blades.push(...leaflet(at(.76), unit(add(add(tangentAt(.78), radial, .6), n, .15)), n, .25 * size, .2 * size));
	}

	// Les bouquets : [x, z, hauteur, inclinaison, nombre de fleurs]. Chaque hampe est une lame fine qui part du collet, avec une
	// fourche pour la seconde fleur ; les fleurs sont des étoiles à six pointes, deux triangles croisés, presque à plat, et un cœur
	// jaune juste au-dessus.
	const clusters = [
		[.06, -.04, 1.1, .3, 2],
		[-.12, .1, .92, 2.4, 2],
		[.12, .15, .78, 4.3, 2],
	];
	const stems = [], petals = [], hearts = [];
	clusters.forEach(([x, z, height, lean, count], c) => {
		const base = [x * .3, .2, z * .3], top = [x + Math.cos(lean) * .04, height, z + Math.sin(lean) * .04];
		const side = unit(cross(unit(add(top, base, -1)), [Math.cos(lean + 1.4), 0, Math.sin(lean + 1.4)])).map(v => v * .014);
		stems.push([add(base, side), top, add(base, side, -1)]);
		for(let k = 0; k < count; k++) {
			const a = lean + c * 1.1 + k * 2.3, spread = .045 + k * .02;
			const center = k === 0 ? top : add(top, [Math.cos(a) * spread, -.03 - k * .025, Math.sin(a) * spread]);
			if(k > 0) {
				const fork = add(base, top, 0).map((v, i) => base[i] + (top[i] - base[i]) * .7);
				stems.push([add(fork, side, .6), center, add(fork, side, -.6)]);
			}
			const r = .085 - k * .01 - c * .007, tilt = .18 + k * .07;
			for(const half of [0, 1]) {
				petals.push([0, 1, 2].map(j => {
					const b = a + half * Math.PI / 3 + j * 2.094;
					return [center[0] + Math.cos(b) * r, center[1] + Math.cos(b - a) * r * tilt, center[2] + Math.sin(b) * r];
				}));
			}
			hearts.push([0, 1, 2].map(j => {
				const b = a + .5 + j * 2.094;
				return [center[0] + Math.cos(b) * .028, center[1] + .012, center[2] + Math.sin(b) * .028];
			}));
		}
	});

	const parts = [
		{ color: LEAF, geometry: mesh(blades) },
		{ color: DEEP, geometry: mesh([...ribs, ...stems]) },
		{ color: FLOWER, sway: .7, geometry: mesh(petals) },
		{ color: HEART, sway: .7, geometry: mesh(hearts) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .6;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
