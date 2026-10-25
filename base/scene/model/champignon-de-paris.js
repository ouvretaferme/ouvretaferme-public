// Champignon de Paris, pensé pour se lire sur un rang à 20 px/m : une touffe de chapeaux ronds blanc crème, de tailles
// inégales, serrés sur une motte basse de terre de gobetage brun noir. Ni feuille ni vert : le contraste est entre le
// blanc des chapeaux et le sol sombre dessiné dans le modèle. Deux des cinq se dégagent sur un pied trapu, et le plus
// grand, ouvert et penché, montre un dessous brun rosé. Ce qui le sépare du pleurote : des chapeaux ronds centrés sur leur
// pied, et non des éventails étagés ; du shiitake : du blanc lisse sur de la terre, et non du brun sur une bûche.
// Fiche : champignon-de-paris.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const CAP = '#f4eedf'; const STIPE = '#d6cab3'; const GILL = '#a98478'; const CASING = '#33241c';

	// Pièce arrondie : une liste de triangles [[x, y, z] × 3] dont chaque sommet porte la moyenne des normales des faces
	// qui le partagent. Chaque face est d'abord tournée vers l'extérieur du centre donné : les pièces sont convexes.
	const smooth = (tris, center) => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const middle = new THREE.Vector3(...center), normals = new Map(), faces = [];
		for(const tri of tris) {
			const [a, b, c] = tri.map(p => new THREE.Vector3(...p));
			const n = b.clone().sub(a).cross(c.clone().sub(a));
			const outward = a.clone().add(b).add(c).divideScalar(3).sub(middle);
			const face = n.dot(outward) < 0 ? [tri[0], tri[2], tri[1]] : tri;
			if(n.dot(outward) < 0) n.negate();
			faces.push(face);
			for(const p of face) normals.set(key(p), (normals.get(key(p)) ?? new THREE.Vector3()).add(n));
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(faces.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(faces.flat().flatMap(p => normals.get(key(p)).clone().normalize().toArray()), 3));
		return g;
	};
	const ring = (count, radius, y, phase) => Array.from({ length: count }, (_, j) => {
		const a = phase + j * Math.PI * 2 / count;
		return [Math.cos(a) * radius, y, Math.sin(a) * radius];
	});

	// Chapeau bombé : une calotte à trois sommets, élargie en un bord à six pans. Dix triangles, un contour hexagonal
	// vu de dessus, une épaule vue de profil, et des normales partagées qui arrondissent le tout.
	const dome = (radius, height, phase) => {
		const top = ring(3, radius * .5, height, phase), rim = ring(6, radius, height * .36, phase);
		const tris = [[top[0], top[1], top[2]]];
		for(let k = 0; k < 3; k++) {
			const before = rim[(2 * k + 5) % 6], at = rim[2 * k], after = rim[2 * k + 1];
			tris.push([top[k], before, at], [top[k], at, after], [top[k], after, top[(k + 1) % 3]]);
		}
		return { geometry: smooth(tris, [0, 0, 0]), rim };
	};
	// Bouton fermé, posé à même la terre : cinq pans autour d'un pôle bas, sans pied visible
	const button = (radius, height, phase) => {
		const rim = ring(5, radius, height * .34, phase);
		return smooth(rim.map((p, j) => [[0, height, 0], p, rim[(j + 1) % 5]]), [0, 0, 0]);
	};
	// Pied trapu, renflé à la base : trois pans qui se rejoignent à l'intérieur du chapeau
	const stipe = (radius, height, phase) => {
		const foot = ring(3, radius, 0, phase);
		return smooth(foot.map((p, j) => [[0, height, 0], p, foot[(j + 1) % 3]]), [0, 0, 0]);
	};

	// Motte : sept pans de rayons inégaux autour d'un sommet bas
	const moundTop = .03;
	const edge = [.14, .126, .138, .122, .14, .13, .135].map((r, j) => [Math.cos(j * Math.PI * 2 / 7 + .2) * r, 0, Math.sin(j * Math.PI * 2 / 7 + .2) * r]);
	const mound = smooth(edge.map((p, j) => [[0, moundTop, 0], p, edge[(j + 1) % 7]]), [0, -.1, 0]);
	const ground = (x, z) => moundTop * (1 - Math.hypot(x, z) / .132);

	const caps = [], stipes = [], gills = [];
	// Penche une pièce depuis son pied vers l'azimut donné, puis la pose sur la motte
	const place = (g, x, z, lean = 0, azimuth = 0) => g
		.applyMatrix4(new THREE.Matrix4().makeRotationAxis(new THREE.Vector3(Math.sin(azimuth), 0, -Math.cos(azimuth)), lean))
		.translate(x, ground(x, z), z);

	// Le grand, ouvert, dépasse de la touffe sur son pied et penche vers l'extérieur : le bord relevé laisse voir les
	// lames, un cône renversé brun rosé qui descend du bord jusqu'au pied.
	{
		const x = .034, z = -.034, stalk = .05, lean = .4, azimuth = -Math.PI / 4;
		const { geometry, rim } = dome(.068, .034, .4);
		const under = smooth(rim.map((p, j) => [[0, -.026, 0], p, rim[(j + 1) % 6]]), [0, .05, 0]);
		caps.push(place(geometry.translate(0, stalk, 0), x, z, lean, azimuth));
		gills.push(place(under.translate(0, stalk, 0), x, z, lean, azimuth));
		stipes.push(place(stipe(.027, stalk + .012, .2), x, z, lean, azimuth));
	}
	// Deux moyens, bombés en boule : [x, z, rayon, hauteur, pied, phase, penché, azimut]. Le premier se dégage sur un
	// pied court et penche hors de la touffe, le second garde le bord au ras de la terre.
	for(const [x, z, radius, height, stalk, phase, lean, azimuth] of [[-.05, -.005, .052, .056, .03, 1.1, .34, -2], [.036, .072, .044, .05, 0, 2.3, .16, 1.2]]) {
		caps.push(place(dome(radius, height, phase).geometry.translate(0, stalk - height * .12, 0), x, z, lean, azimuth));
		if(stalk > 0) stipes.push(place(stipe(radius * .5, stalk + height * .6, phase), x, z, lean, azimuth));
	}
	// Deux boutons fermés, plus petits, glissés entre les autres
	for(const [x, z, radius, height, phase] of [[.092, .018, .028, .03, 1.7], [-.045, .088, .032, .036, 2.6]]) {
		caps.push(place(button(radius, height, phase), x, z));
	}

	const parts = [
		{ color: CAP, sway: 0, geometry: helpers.merge(caps) },
		{ color: STIPE, sway: 0, geometry: helpers.merge(stipes) },
		{ color: GILL, sway: 0, geometry: helpers.merge(gills) },
		{ color: CASING, sway: 0, geometry: mound },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .3;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
