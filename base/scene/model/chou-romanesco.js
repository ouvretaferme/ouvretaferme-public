// Chou romanesco, pensé pour se lire à une quinzaine de pixels : une pomme chartreuse en pyramide pointue, hérissée de petits
// cônes rangés en spirale, assise dans une collerette de grandes feuilles bleu-vert ouvertes et retombantes. La pointe et le
// vert-jaune le séparent du chou-fleur (dôme crème caché dans un vase de feuilles dressées) et du brocoli (dôme sombre en l'air
// sur une tige nue).
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.

// Feuille en ruban de la base (origine) vers +z, qui monte de `lift` radians et retombe de `droop` vers la pointe. Chaque coupe
// porte deux bords et une nervure ; `cup` relève les bords vers l'axe, `wave` les fait onduler d'une coupe à l'autre.
// 4 triangles par segment, 2 pour la pointe.
function blade(THREE, { length, widths, lift, droop, cup = .3, wave = 0 }) {
	const segs = widths.length - 1, pos = [], index = [];
	let y = 0, z = 0;
	for(let i = 0; i <= segs; i++) {
		const a = lift - droop * i / segs;
		if(i > 0) { y += Math.sin(a) * length / segs; z += Math.cos(a) * length / segs; }
		const ny = Math.cos(a), nz = -Math.sin(a), w = widths[i];
		const e = (cup + (i % 2 ? wave : -wave)) * w;
		if(w === 0) pos.push(0, y, z);
		else pos.push(-w, y + ny * e, z + nz * e, 0, y, z, w, y + ny * e, z + nz * e);
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

// Tour arrondi : profil [[rayon, y], …] du haut vers le bas, rayon 0 pour un pôle. Chaque sommet porte la normale moyenne des
// faces qui l'entourent, pour que la pièce se lise ronde sous l'éclairage en paliers au lieu de se lire taillée.
function roundLathe(THREE, profile, sides, phase = 0) {
	const ring = i => (i + sides) % sides;
	const key = (k, i) => profile[k][0] === 0 ? `${k}` : `${k}:${ring(i)}`;
	const at = (k, i) => {
		const [r, y] = profile[k], a = phase + i * Math.PI * 2 / sides;
		return new THREE.Vector3(r * Math.cos(a), y, r * Math.sin(a));
	};
	const faces = [];
	for(let k = 0; k < profile.length - 1; k++) {
		for(let i = 0; i < sides; i++) {
			if(profile[k][0] > 0) faces.push([[k, i], [k + 1, i], [k, i + 1]]);
			if(profile[k + 1][0] > 0) faces.push([[k, i + 1], [k + 1, i], [k + 1, i + 1]]);
		}
	}
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
}

// Pyramide ouverte à `sides` pans, pointe en +y, base à y = 0 cachée dans la pomme : `sides` triangles. Les pans restent
// taillés, c'est la facette qui fait lire une pointe et non un bouton.
function spike(THREE, radius, length, sides, phase) {
	const pos = [];
	for(let i = 0; i < sides; i++) {
		const a0 = phase + i * Math.PI * 2 / sides, a1 = phase + (i + 1) * Math.PI * 2 / sides;
		pos.push(radius * Math.cos(a1), 0, radius * Math.sin(a1), radius * Math.cos(a0), 0, radius * Math.sin(a0), 0, length, 0);
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.computeVertexNormals();
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
	const { merge, facets } = helpers;
	const FLORET = '#cfe04e', CORE = '#98b52c', WRAP = '#7da39a', OUTER = '#456f6b';

	// Pomme : un cône arrondi (largeur sur hauteur ≈ 1,2), du pôle pointu à un ventre rentré dessous, caché dans les feuilles
	const TOP = .45;
	const coreProfile = [[0, TOP], [.08, TOP - .09], [.15, TOP - .2], [.11, TOP - .26]];
	const core = roundLathe(THREE, coreProfile, 9, .3);
	// Rayon du cône à la hauteur y, lu sur les deux premiers tronçons du profil
	const radiusAt = y => {
		for(let k = 0; k < coreProfile.length - 1; k++) {
			const [r0, y0] = coreProfile[k], [r1, y1] = coreProfile[k + 1];
			if(y <= y0 && y >= y1) return r0 + (r1 - r0) * (y0 - y) / (y0 - y1);
		}
		return coreProfile[coreProfile.length - 1][0];
	};

	// Fleurettes : treize pyramides en spirale d'or, du haut vers le ventre, chacune pointée hors de la pomme et un peu vers le
	// ciel. Leur taille croît vers le bas comme sur la pomme réelle, et chacune varie en longueur et en rayon d'après son rang.
	const GOLDEN = Math.PI * (3 - Math.sqrt(5)), COUNT = 13;
	const up = new THREE.Vector3(0, 1, 0);
	const florets = Array.from({ length: COUNT }, (_, i) => {
		const t = (i + .6) / COUNT, y = TOP - .035 - t * .16, r = radiusAt(y) * .92, a = i * GOLDEN + .4;
		const outward = new THREE.Vector3(Math.cos(a) * .82, .57, Math.sin(a) * .82);
		const axis = outward.add(new THREE.Vector3(0, .45, 0)).normalize();
		const jitter = 1 + .14 * Math.sin(i * 2.7);
		const g = spike(THREE, (.028 + .02 * t) * jitter, (.05 + .035 * t) * (2 - jitter), 5, i);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(up, axis));
		return g.translate(r * Math.cos(a), y, r * Math.sin(a));
	});
	// Pyramide sommitale, plus haute que les autres : c'est elle qui fait la pointe de la silhouette
	florets.push(spike(THREE, .05, .11, 6, 0).translate(0, TOP - .04, 0));

	// Feuilles du cœur : quatre, relevées contre le ventre de la pomme, assez courtes pour en laisser voir toute la pyramide.
	// [azimut, pied, longueur, ouverture, retombée]
	const wrapLeaves = [
		[.4, .08, .22, 1.05, .5],
		[2.0, .085, .2, 1.12, .6],
		[3.5, .075, .23, .98, .45],
		[5.0, .09, .19, 1.1, .65],
	].map(([azimuth, foot, length, lift, droop], rank) => facets(blade(THREE, {
		length, lift, droop, cup: .6, wave: .08,
		widths: [.04, .08, .09, .065, 0].map((w, i) => w * (1 + .1 * Math.sin(rank * 2.3 + i))),
	})).translate(0, .05, foot).rotateY(azimuth));

	// Grandes feuilles : six, oblongues à bout coupé large plutôt qu'en pointe, qui partent raides du pied puis s'ouvrent et retombent en collerette, le bout plus bas que le milieu.
	// Chacune varie en azimut, longueur, ouverture et retombée pour qu'aucune ne soit la copie d'une autre.
	const outerLeaves = [
		[0, .42, 1.05, 1.2],
		[1.05, .37, 1.12, 1.3],
		[2.15, .45, .98, 1.1],
		[3.15, .39, 1.1, 1.35],
		[4.25, .44, 1.0, 1.05],
		[5.3, .36, 1.16, 1.25],
	].map(([azimuth, length, lift, droop], rank) => facets(blade(THREE, {
		length, lift, droop, cup: .4, wave: .14,
		widths: [.05, .11, .14, .155, .13, .07].map((w, i) => w * (1 + .12 * Math.cos(rank * 1.7 + i))),
	})).translate(0, .02, .08).rotateY(azimuth));

	const d = .7;
	const parts = [
		{ color: OUTER, geometry: merge(outerLeaves) },
		{ color: WRAP, sway: .3, geometry: merge(wrapLeaves) },
		{ color: CORE, sway: 0, geometry: core },
		{ color: FLORET, sway: 0, geometry: merge(florets) },
	];
	return { d, h: heightFor(THREE, parts, d), parts };
}
