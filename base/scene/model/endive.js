// Endive : chicon trapu en fuseau, plus large et plus court qu'une asperge, feuilles crème qui se recouvrent en écailles, pointe jaune pâle refermée.
// Contrat et budget : base/scene/CLAUDE.md. Les helpers (helpers.js) se lisent, ne se modifient pas.
// Feuille en ruban de la base (origine) vers +z, qui monte de `lift` radians et retombe de `droop` vers la pointe.
// Chaque coupe porte deux bords et une nervure ; `cup` relève les bords vers l'axe, `wave` les fait onduler d'une coupe à l'autre (frisure),
// `bump` cabosse la nervure (cloques). Une largeur nulle en bout donne une pointe : 4 triangles par segment, 2 pour la pointe.
function blade(THREE, { length, widths, lift = .5, droop = .5, cup = .3, wave = 0, bump = 0, seed = 1 }) {
	const segs = widths.length - 1, pos = [], index = [];
	let y = 0, z = 0, s = seed * 9301 + 49297;
	const rnd = () => (s = (s * 16807) % 2147483647) / 2147483647 - .5;
	for(let i = 0; i <= segs; i++) {
		const a = lift - droop * i / segs;
		if(i > 0) { y += Math.sin(a) * length / segs; z += Math.cos(a) * length / segs; }
		const ny = Math.cos(a), nz = -Math.sin(a), w = widths[i];
		const e = (cup + (i % 2 ? wave : -wave)) * w, m = bump * rnd() * length;
		if(w === 0) pos.push(0, y, z);
		else pos.push(-w, y + ny * e, z + nz * e, 0, y + ny * m, z + nz * m, w, y + ny * e, z + nz * e);
	}
	for(let i = 0; i < segs; i++) {
		const b = i * 3, c = b + 3;
		if(widths[i + 1] === 0) index.push(b, b + 1, c, b + 1, b + 2, c);
		else index.push(b, b + 1, c + 1, b, c + 1, c, b + 1, b + 2, c + 2, b + 1, c + 2, c + 1);
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.setIndex(index);
	return g;
}

// Hauteur réelle tirée des proportions du modèle : le socle ramène emprise et hauteur à 1 chacune, un h posé à part étirerait la forme
function heightFor(THREE, parts, d) {
	const box = new THREE.Box3();
	for(const { geometry } of parts) { geometry.computeBoundingBox(); box.union(geometry.boundingBox); }
	const size = box.getSize(new THREE.Vector3());
	return d * size.y / Math.max(size.x, size.z);
}

export function build(THREE, helpers) {
	const { around, merge, facets } = helpers;
	const WHITE = '#f5f1d8', SCALE = '#e4dcb2', TIP = '#e9d867';
	const lathe = profile => new THREE.LatheGeometry(profile.map(([x, y]) => new THREE.Vector2(x, y)), 7);
	const body = lathe([[0, 0], [.04, .01], [.08, .05], [.095, .13], [.09, .2], [.08, .25]]);
	const point = lathe([[.08, .25], [.06, .29], [.02, .32], [0, .325]]);
	// Écailles : bout arrondi (largeur jamais nulle) pour que les feuilles se lisent en recouvrement et non en pointes d'asperge
	const scale = { widths: [.06, .1, .1, .07], cup: .6 };
	const scales = around(6, (a, i) => facets(blade(THREE, { ...scale, length: .19, lift: 1.4, droop: -.35, seed: i + 1 })).translate(0, .02, .085).rotateY(a));
	const tips = around(4, (a, i) => facets(blade(THREE, { widths: [.035, .05, .045, .025], cup: .9, length: .07, lift: 1.6, droop: -.9, seed: i + 7 })).translate(0, .24, .085).rotateY(a + .4));
	const d = .16;
	const parts = [
		{ color: WHITE, sway: .1, geometry: facets(body) },
		{ color: SCALE, sway: .1, geometry: merge(scales) },
		{ color: TIP, sway: .1, geometry: merge([facets(point), ...tips]) },
	];
	return { d, h: heightFor(THREE, parts, d), parts };
}
