// Chou-fleur, pensé pour l'écran de travail : une pomme blanc crème faite de bouquets ronds serrés en grappe, au cœur d'un vase de
// feuilles dressées, épaisses et bleutées qui l'encadrent. Le blanc granuleux au milieu du vert bleuté fait reconnaître l'espèce ;
// la grappe de bosses la sépare du chou pommé (boule lisse), la couleur du brocoli, le vase dressé de la scarole et de la laitue.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.

// Feuille en ruban de la base (origine) vers +z, qui monte de `lift` radians et retombe de `droop` vers la pointe. Chaque coupe
// porte deux bords et une nervure ; `cup` relève les bords vers l'axe, ce qui donne à la feuille une gouttière et donc une épaisseur
// vue de biais. 4 triangles par segment, 2 pour la pointe.
function blade(THREE, { length, widths, lift, droop, cup }) {
	const segs = widths.length - 1, pos = [], index = [];
	let y = 0, z = 0;
	for(let i = 0; i <= segs; i++) {
		const a = lift - droop * i / segs;
		if(i > 0) { y += Math.sin(a) * length / segs; z += Math.cos(a) * length / segs; }
		const ny = Math.cos(a), nz = -Math.sin(a), w = widths[i], e = cup * w;
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

// Tour arrondi : profil [[rayon, y], …] du haut vers le bas, rayon 0 pour un pôle. Chaque sommet porte la normale moyenne des faces
// qui l'entourent : la pièce se lit ronde sous l'éclairage en paliers au lieu de se lire taillée.
function roundLathe(THREE, profile, sides, phase = 0) {
	const at = (k, i) => {
		const [r, y] = profile[k], a = phase + i * Math.PI * 2 / sides;
		return new THREE.Vector3(r * Math.cos(a), y, r * Math.sin(a));
	};
	const key = (k, i) => profile[k][0] === 0 ? `${k}` : `${k}:${(i + sides) % sides}`;
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

// Bouquet : petit dôme à normales lissées, posé sur la pomme et tourné selon sa normale. Chaque bouquet garde ses propres normales,
// si bien que la lumière casse à la jonction de deux bouquets : c'est ce pli d'ombre qui donne le grain de la pomme.
function floret(THREE, { radius, height, sides, phase, center, direction }) {
	const g = roundLathe(THREE, [[0, height], [radius * .72, height * .72], [radius, 0]], sides, phase);
	const turn = new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), direction.clone().normalize());
	return g.applyQuaternion(turn).translate(center.x, center.y, center.z);
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
	const CURD = '#fbf6e4', WRAP = '#6b968a', OUTER = '#456f68';

	// Pomme : un bouquet au sommet et une couronne de sept autour, penchés vers l'extérieur, sur un dôme surbaissé. Les bouquets
	// varient en taille, en inclinaison et en azimut pour que la grappe ne se lise pas comme une rosace.
	const CURD_Y = .27;
	const crown = [
		[.15, .55, .085], [1.02, .62, .078], [1.9, .52, .088], [2.71, .64, .08],
		[3.6, .56, .086], [4.48, .62, .076], [5.4, .5, .084],
	];
	const curd = [
		floret(THREE, { radius: .1, height: .075, sides: 6, phase: .3, center: new THREE.Vector3(.01, CURD_Y + .055, -.005), direction: new THREE.Vector3(.08, 1, .05) }),
		...crown.map(([azimuth, tilt, radius], rank) => {
			const direction = new THREE.Vector3(Math.sin(tilt) * Math.cos(azimuth), Math.cos(tilt) * 1.25, Math.sin(tilt) * Math.sin(azimuth));
			const center = new THREE.Vector3(.11 * Math.cos(azimuth), CURD_Y + .02, .11 * Math.sin(azimuth));
			return floret(THREE, { radius, height: radius * .9, sides: 5, phase: rank, center, direction });
		}),
		// Cœur caché sous la couronne : il bouche les jours entre bouquets par du crème plutôt que par le vert des feuilles
		roundLathe(THREE, [[0, CURD_Y + .07], [.15, CURD_Y], [.08, CURD_Y - .09]], 6, .5),
	];

	// Feuilles du cœur : cinq, presque verticales, creusées en gouttière autour de la pomme, la pointe à peine rabattue vers elle.
	// [azimut, longueur, ouverture, retombée]
	const wrapLeaves = [
		[.35, .31, 1.3, .3], [1.6, .28, 1.38, .45], [2.85, .32, 1.25, .25], [4.05, .29, 1.35, .4], [5.2, .3, 1.28, .35],
	].map(([azimuth, length, lift, droop], rank) => facets(blade(THREE, {
		length, lift, droop, cup: .75,
		widths: [.05, .1, .11, .085, 0].map((w, i) => w * (1 + .1 * Math.sin(rank * 2.3 + i))),
	})).translate(0, .03, .13).rotateY(azimuth));

	// Grandes feuilles : quatre, dressées à 25-35° de la verticale, à peine ouvertes au bout, oblongues, larges et creusées, le bout
	// coupé rond plutôt qu'en pointe ; leur bord ondule d'une coupe à l'autre. [azimut, longueur, ouverture, retombée]
	const outerLeaves = [
		[.95, .42, 1.2, .5], [2.55, .46, 1.12, .6], [4.05, .4, 1.26, .45], [5.45, .45, 1.15, .55],
	].map(([azimuth, length, lift, droop], rank) => facets(blade(THREE, {
		length, lift, droop, cup: .5,
		widths: [.05, .12, .1, .15, .13, .06].map((w, i) => w * (1 + .12 * Math.cos(rank * 1.7 + i))),
	})).translate(0, .01, .1).rotateY(azimuth));

	const d = .65;
	const parts = [
		{ color: OUTER, geometry: merge(outerLeaves) },
		{ color: WRAP, sway: .3, geometry: merge(wrapLeaves) },
		{ color: CURD, sway: 0, geometry: merge(curd) },
	];
	return { d, h: heightFor(THREE, parts, d), parts };
}
