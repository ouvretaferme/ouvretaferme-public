// Chou pointu, pensé pour se lire à une vingtaine de pixels : une pomme en obus, deux fois plus haute que large, lisse et vert
// clair, dressée au centre et finie en pointe, qui dépasse d'une bonne moitié une coupe de huit feuilles vert moyen montées raides
// puis ouvertes au bout. Le chou blanc a une pomme ronde posée bas dans une coupe gris-bleu, le pë-tsaï un tonneau à sommet plat
// strié de côtes blanches : ici le trait propre est la pointe, et le contraste le plus fort est entre la pomme et la coupe.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const HEAD = '#d6eaa0'; const WRAP = '#97bb68'; const LEAF = '#5c8a48';

	// Tour lisse : point(k, i) donne le sommet de l'anneau k au pan i. Chaque sommet porte la normale moyenne des faces qui le
	// touchent, si bien qu'un volume rond se lit rond sous l'éclairage en paliers. Un pôle est un anneau dont tous les points se
	// confondent : il se déclare dans `poles` pour ne pas tracer de triangles nuls.
	const smooth = (rings, pans, point, { closed = true, poles = [] } = {}) => {
		const columns = closed ? pans : pans + 1;
		const key = (k, i) => poles.includes(k) ? `${k}` : `${k}:${i % columns}`;
		const faces = [];
		for(let k = 0; k < rings - 1; k++) {
			for(let i = 0; i < pans; i++) {
				if(!poles.includes(k)) faces.push([[k, i], [k + 1, i], [k, i + 1]]);
				if(!poles.includes(k + 1)) faces.push([[k, i + 1], [k + 1, i], [k + 1, i + 1]]);
			}
		}
		const at = (k, i) => new THREE.Vector3(...point(k, closed ? i % pans : i));
		const normals = new Map();
		for(const corners of faces) {
			const [p, q, r] = corners.map(c => at(...c));
			const n = q.sub(p).cross(r.sub(p));
			for(const c of corners) normals.set(key(...c), (normals.get(key(...c)) ?? new THREE.Vector3()).add(n));
		}
		const position = [], normal = [];
		for(const corners of faces) {
			for(const c of corners) {
				position.push(...at(...c).toArray());
				normal.push(...normals.get(key(...c)).clone().normalize().toArray());
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		return g;
	};

	// Pomme : profil d'obus de la pointe vers le pied, la plus grande largeur au tiers bas, le dos resserré en ligne presque droite
	// jusqu'à la pointe. Douze pans suffisent à un contour rond à cette taille ; le dessous, caché par la coupe, reste ouvert.
	const PANS = 12;
	const profile = [[0, .37], [.021, .334], [.038, .29], [.051, .242], [.059, .19], [.057, .14], [.044, .1]];
	const head = smooth(profile.length, PANS, (k, i) => {
		const a = i * Math.PI * 2 / PANS, [r, y] = profile[k];
		return [r * Math.cos(a), y, r * Math.sin(a)];
	}, { poles: [0] });
	// Rayon de la pomme à la hauteur y, lu sur le profil, pour coller les feuilles d'enveloppe contre elle
	const radiusAt = y => {
		for(let k = 0; k < profile.length - 1; k++) {
			const [r0, y0] = profile[k], [r1, y1] = profile[k + 1];
			if(y <= y0 && y >= y1) return r0 + (r1 - r0) * (y0 - y) / (y0 - y1);
		}
		return profile[profile.length - 1][0];
	};

	// Feuilles d'enveloppe : trois coques sur quatre pans, plaquées sur la moitié basse de la pomme, le bord au milieu plus haut que
	// les côtés et à peine décollé. [azimut de départ, hauteur du bord au milieu, décollement du bord]
	const wraps = [[.2, .225, 1.14], [2.3, .2, 1.18], [4.3, .235, 1.12]].map(([start, crest, open]) => smooth(3, 4, (k, i) => {
		const a = start + i * Math.PI * 2 / PANS, middle = 1 - Math.abs(i - 2) / 2;
		const y = [.1, .15, .16 + (crest - .16) * middle][k];
		const r = radiusAt(y) * [1.1, 1.08, open][k];
		return [r * Math.cos(a), y, r * Math.sin(a)];
	}, { closed: false }));

	// Feuilles extérieures : huit, chacune différente en azimut, longueur, largeur, montée et ouverture, pour que la coupe ne soit
	// pas une étoile : cinq longues qui s'ouvrent en coupe basse et assoient le plant, trois plus courtes qui montent raides contre
	// la pomme et s'ouvrent à mi-hauteur, laissant la moitié haute et la pointe dégagées.
	// [azimut, longueur, demi-largeur, angle au pied, angle au bout]
	const fronds = [
		[.3, .24, .1, .86, -.1],
		[1.35, .22, .092, .74, -.2],
		[2.75, .25, .104, .92, -.04],
		[3.85, .21, .09, .78, -.18],
		[5.25, .235, .098, .88, -.08],
		[.85, .2, .08, 1.3, .55],
		[3.3, .185, .074, 1.38, .7],
		[4.55, .205, .082, 1.24, .46],
	];
	// Contour d'un limbe ovale : pétiole étroit, largeur maximale aux deux tiers, bout arrondi. Le limbe est creusé en cuillère
	// (bords relevés le long de sa normale), et la nervure reste nette parce que chaque face reçoit sa propre lumière.
	const stations = [[0, .14], [.36, .78], [.7, 1], [.92, .58]];
	const blades = [];
	for(const [azimuth, length, width, rise, open] of fronds) {
		const angle = u => rise + (open - rise) * u * u;
		const steps = 12, spine = [[.03, .035]];
		for(let s = 1; s <= steps; s++) {
			const [x, y] = spine[s - 1], a = angle((s - .5) / steps);
			spine.push([x + Math.cos(a) * length / steps, y + Math.sin(a) * length / steps]);
		}
		const along = u => {
			const [x, y] = spine[Math.round(u * steps)], a = angle(u);
			return { x, y, nx: -Math.sin(a), ny: Math.cos(a) };
		};
		const cross = ([u, w]) => {
			const { x, y, nx, ny } = along(u), half = width * w, lift = .45 * half;
			return [[x + nx * lift, y + ny * lift, -half], [x, y, 0], [x + nx * lift, y + ny * lift, half]];
		};
		const rows = stations.map(cross), tip = along(1);
		const tris = [];
		for(let s = 0; s < rows.length - 1; s++) {
			const [a0, b0, c0] = rows[s], [a1, b1, c1] = rows[s + 1];
			tris.push([a0, a1, b0], [b0, a1, b1], [b0, b1, c0], [c0, b1, c1]);
		}
		const [aN, bN, cN] = rows[rows.length - 1], end = [tip.x, tip.y, 0];
		tris.push([aN, end, bN], [bN, end, cN]);
		blades.push(mesh(tris).rotateY(azimuth));
	}

	function mesh(tris) {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	}

	const parts = [
		{ color: LEAF, geometry: helpers.merge(blades) },
		{ color: WRAP, sway: .2, geometry: helpers.merge(wraps) },
		{ color: HEAD, sway: 0, geometry: head },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .45;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
