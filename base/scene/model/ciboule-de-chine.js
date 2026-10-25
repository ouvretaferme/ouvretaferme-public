// Ciboule, pensée pour se lire sur un rang à 20 px/m : une touffe de quatre pieds séparés, chacun un fût blanc droit et
// fin, sans bulbe, qui s'écarte un peu du centre et porte à un petit tiers de la hauteur trois tubes ronds vert franc,
// dressés, effilés en pointe. Peu de pièces, mais grosses, et le plus fort contraste entre les fûts blancs et les tubes.
// Ce qui la sépare de l'oignon : plusieurs pieds et aucun bulbe ; du poireau : des fûts fins et nombreux, des tubes
// dressés au lieu de lames plates qui retombent en éventail ; de la ciboulette : peu de brins, épais, sur des fûts blancs
// nus. Fiche : ciboule-de-chine.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const SHAFT = '#ecead8'; const LEAF = '#5c9a42'; const DARK = '#386d31';

	// Pièce arrondie : une liste de triangles [[x, y, z] × 3] dont chaque sommet porte la moyenne des normales des faces
	// qui le partagent. Un tube à trois pans se lit alors rond sous l'éclairage, au lieu d'un prisme taillé.
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normals.get(key(c)).clone().normalize().toArray()), 3));
		return g;
	};

	// Direction penchée de `lean` depuis la verticale, vers l'azimut `azimuth`, et un anneau de trois points de rayon r
	// autour d'un centre, perpendiculaire à cette direction.
	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const ring = (center, axis, r, phase) => {
		const u = new THREE.Vector3(0, 0, 1).cross(axis).normalize(), v = axis.clone().cross(u).normalize();
		return [0, 1, 2].map(j => {
			const a = phase + j * Math.PI * 2 / 3;
			return center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).toArray();
		});
	};

	// Pieds : quatre, à des azimuts, des penchés et des longueurs tous différents, pour que la touffe ne soit pas une étoile.
	// [azimut, penché du fût, écart du pied au centre, longueur du fût, rayon du fût, feuilles]
	// Une feuille : [écart d'azimut au fût, ouverture depuis la verticale, longueur, rayon, sombre]. Les plus ouvertes, qui
	// passent dehors, prennent la couleur sombre : la touffe a deux valeurs de vert.
	const tillers = [
		[.3, .3, .02, .13, .017, [[0, .1, .33, .016, false], [.9, .34, .28, .015, true], [-.8, .24, .3, .016, false]]],
		[1.95, .24, .018, .118, .016, [[.2, .06, .35, .016, false], [1.1, .38, .27, .015, true], [-.6, .22, .3, .015, false]]],
		[3.45, .36, .022, .108, .016, [[-.1, .14, .3, .016, false], [.7, .42, .26, .015, true], [-1, .3, .28, .015, true]]],
		[4.85, .2, .016, .136, .017, [[.1, .05, .34, .016, false], [-.9, .3, .29, .015, true], [.8, .2, .31, .016, false]]],
	];

	// Un fût est un tube à trois pans, à peine plus large au pied, ouvert aux deux bouts : 6 triangles. Le pied, dans la
	// terre, et le haut, caché dans la naissance des feuilles, ne se voient pas. Une feuille part d'un anneau à trois pans
	// posé dans le haut du fût et s'effile jusqu'à sa pointe : 3 triangles. 4 × (6 + 3 × 3) = 60 triangles.
	const shafts = [], blades = { true: [], false: [] };
	tillers.forEach(([azimuth, lean, offset, length, radius, leaves], t) => {
		const axis = direction(azimuth, lean);
		const foot = new THREE.Vector3(Math.cos(azimuth) * offset, 0, Math.sin(azimuth) * offset);
		const top = foot.clone().addScaledVector(axis, length);
		const low = ring(foot, axis, radius * 1.12, t), high = ring(top, axis, radius, t);
		const tris = [];
		for(let j = 0; j < 3; j++) {
			const k = (j + 1) % 3;
			tris.push([low[j], high[j], low[k]], [low[k], high[j], high[k]]);
		}
		shafts.push(smooth(tris));
		for(const [turn, open, run, r, dark] of leaves) {
			const leafAxis = direction(azimuth + turn, open);
			const base = top.clone().addScaledVector(axis, -.012);
			const around = ring(base, leafAxis, r, t + turn), tip = base.clone().addScaledVector(leafAxis, run).toArray();
			blades[dark].push(smooth([0, 1, 2].map(j => [around[j], tip, around[(j + 1) % 3]])));
		}
	});

	const parts = [
		{ color: LEAF, geometry: helpers.merge(blades.false) },
		{ color: DARK, geometry: helpers.merge(blades.true) },
		{ color: SHAFT, sway: .2, geometry: helpers.merge(shafts) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .2;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
