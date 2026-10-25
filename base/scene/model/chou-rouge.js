// Chou rouge : pomme ronde et lisse pourpre, dans de larges feuilles violet bleuté étalées.
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

// Tour arrondi : profil [[rayon, y], …] du haut vers le bas, rayon 0 pour un pôle. Chaque sommet porte la normale moyenne des faces
// qui l'entourent, si bien que la pièce se lit ronde sous l'éclairage en paliers au lieu de se lire taillée. `wobble(pan, y)` écarte
// un sommet de l'axe (cloques, bosses) et `only(pan)` ne garde que certains pans, après le calcul des normales sur le tour entier.
function roundLathe(THREE, profile, sides, { phase = 0, wobble = () => 1, only = () => true } = {}) {
	const ring = i => (i + sides) % sides;
	const key = (k, i) => profile[k][0] === 0 ? `${k}` : `${k}:${ring(i)}`;
	const at = (k, i) => {
		const [r, y] = profile[k], a = phase + i * Math.PI * 2 / sides, s = r * wobble(ring(i), y);
		return new THREE.Vector3(s * Math.cos(a), y, s * Math.sin(a));
	};
	const faces = [];
	for(let k = 0; k < profile.length - 1; k++) {
		for(let i = 0; i < sides; i++) {
			if(profile[k][0] > 0) faces.push({ i, corners: [[k, i], [k + 1, i], [k, i + 1]] });
			if(profile[k + 1][0] > 0) faces.push({ i, corners: [[k, i + 1], [k + 1, i], [k + 1, i + 1]] });
		}
	}
	const normals = new Map();
	for(const { corners } of faces) {
		const [p, q, r] = corners.map(c => at(...c));
		const n = q.sub(p).cross(r.sub(p));
		for(const c of corners) normals.set(key(...c), (normals.get(key(...c)) ?? new THREE.Vector3()).add(n));
	}
	const position = [], normal = [];
	for(const { i, corners } of faces) {
		if(!only(i)) continue;
		for(const c of corners) {
			position.push(...at(...c).toArray());
			normal.push(...normals.get(key(...c)).clone().normalize().toArray());
		}
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
	g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
	return g;
}

export function build(THREE, helpers) {
	const { around, merge, facets } = helpers;
	const HEAD = '#7a2f7e', WRAP = '#8f5a9a', OUTER = '#6c6f9c';
	const outer = around(7, (a, i) => facets(blade(THREE, { length: .52, widths: [.05, .18, .27, .29, .2, 0], lift: .95, droop: 1.45, cup: .4, seed: i + 1 })).translate(0, .02, .08).rotateY(a + i * .1));
	const wrap = around(4, (a, i) => facets(blade(THREE, { length: .3, widths: [.12, .22, .22, .14, 0], lift: 1.1, droop: -.6, cup: .6, seed: i + 9 })).translate(0, .03, .25).rotateY(a + .4));
	// Pomme : 112 triangles en quatorze pans, normales lisses. Un anneau tous les 30° sur ce que la vue plongeante montre ; le dessous,
	// enfoui dans les feuilles, se ferme d'un seul pan de 60°.
	const head = roundLathe(THREE, [0, 30, 60, 90, 120, 180].map(t => [t % 180 === 0 ? 0 : .26 * Math.sin(t * Math.PI / 180), .25 + .2288 * Math.cos(t * Math.PI / 180)]), 14);
	const d = .5;
	const parts = [
		{ color: OUTER, geometry: merge(outer) },
		{ color: WRAP, sway: .3, geometry: merge(wrap) },
		{ color: HEAD, sway: 0, geometry: head },
	];
	return { d, h: heightFor(THREE, parts, d), parts };
}
