// Échalote, pensée pour se lire sur un rang à 20 px/m : une grappe de quatre petits bulbes allongés en goutte, serrés
// côte à côte et penchés vers l'extérieur, à moitié sortis de terre, rosés et cuivrés en alternance pour qu'on en compte
// plusieurs ; de chaque col montent deux feuilles fines vert bleuté, dressées. Ce qui la sépare de l'oignon : plusieurs
// bulbes allongés et non un seul gros bulbe rond ; de l'ail et du poireau : pas de tête blanche, pas de fût, pas de
// lames plates. Fiche : echalote.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const ROSE = '#d99a7e'; const COPPER = '#a8623a'; const LEAF = '#79a593'; const SHADE = '#4f7a6a';

	// Pièce arrondie : une liste de triangles [[x, y, z] × 3] dont chaque sommet porte la moyenne des normales des faces
	// qui le partagent. Une feuille pliée en deux triangles se lit alors ronde comme un tube, et non comme une gouttière.
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

	// Tour arrondi : profil [[rayon, y], …] du haut vers le bas, rayon 0 pour un pôle (un triangle par pan au lieu de
	// deux). Les normales sont calculées sur le tour entier avant que `only(pan, tranche)` n'écarte des faces : une face
	// retirée ne change pas la lumière de ses voisines, et le bulbe reste rond.
	const roundLathe = (profile, sides, { phase = 0, only = () => true } = {}) => {
		const ring = i => (i + sides) % sides;
		const key = (k, i) => profile[k][0] === 0 ? `${k}` : `${k}:${ring(i)}`;
		const at = (k, i) => {
			const [r, y] = profile[k], a = phase + i * Math.PI * 2 / sides;
			return new THREE.Vector3(r * Math.cos(a), y, r * Math.sin(a));
		};
		const faces = [];
		for(let k = 0; k < profile.length - 1; k++) {
			for(let i = 0; i < sides; i++) {
				if(profile[k][0] > 0) faces.push({ i, k, corners: [[k, i], [k + 1, i], [k, i + 1]] });
				if(profile[k + 1][0] > 0) faces.push({ i, k, corners: [[k, i + 1], [k + 1, i], [k + 1, i + 1]] });
			}
		}
		const normals = new Map();
		for(const { corners } of faces) {
			const [p, q, r] = corners.map(c => at(...c));
			const n = q.sub(p).cross(r.sub(p));
			for(const c of corners) normals.set(key(...c), (normals.get(key(...c)) ?? new THREE.Vector3()).add(n));
		}
		const position = [], normal = [];
		for(const { i, k, corners } of faces) {
			if(!only(i, k)) continue;
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

	// Bulbes : quatre gouttes plus hautes que larges, pointues au col, le ventre à mi-hauteur, rentrées au pied qui se
	// perd dans la terre. Chacune penche vers
	// l'extérieur de la grappe et varie en azimut, rayon, hauteur et penchant. Le budget va d'abord à elles (11 triangles
	// chacune) : la calotte fait le tour entier, mais la bande basse ne garde que les trois pans tournés vers l'extérieur,
	// les deux autres étant cachés par les bulbes voisins. [azimut, écart au centre, rayon, hauteur, penchant, couleur]
	const bulbs = [
		[.35, .032, .038, .13, .26, ROSE],
		[1.95, .035, .042, .15, .34, COPPER],
		[3.45, .03, .035, .118, .3, ROSE],
		[4.95, .033, .04, .138, .4, COPPER],
	];
	const OUTER = .5;
	const bulb = ([azimuth, offset, radius, height, lean]) => roundLathe(
		[[0, height], [radius, height * .5], [radius * .72, -.01]],
		5,
		{ phase: -Math.PI / 5, only: (i, k) => k === 0 || Math.cos((i + .5) * Math.PI * 2 / 5 - Math.PI / 5) > -OUTER },
	).rotateZ(-lean).translate(offset, 0, 0).rotateY(azimuth);

	// Feuilles : deux par bulbe, nées de son col. Chacune est une lame repliée sur sa nervure, deux triangles, effilée en
	// pointe, aux normales lissées pour se lire comme un tube. Elles varient en ouverture, en décalage d'azimut et en
	// longueur ; l'ombre range dans la couleur sombre les plus ouvertes, qui passent dehors.
	// [bulbe, décalage d'azimut, ouverture depuis la verticale, longueur, ombre]
	const leaves = [
		[0, -.3, .06, .37, false],
		[0, .4, .36, .3, true],
		[1, .25, .12, .43, false],
		[1, -.5, .44, .32, true],
		[2, .35, .02, .35, false],
		[2, -.25, .32, .27, true],
		[3, -.2, .16, .4, false],
		[3, .55, .5, .3, true],
	];
	const WIDTH = .011;
	const leaf = ([index, shift, open, length]) => {
		const [azimuth, offset, , height, lean] = bulbs[index];
		const foot = new THREE.Vector3(0, height * .96, 0).applyAxisAngle(new THREE.Vector3(0, 0, 1), -lean).add(new THREE.Vector3(offset, 0, 0));
		const tip = [Math.sin(open) * length, Math.cos(open) * length, 0];
		const left = [-Math.cos(open) * WIDTH * .3, Math.sin(open) * WIDTH * .3, -WIDTH];
		const right = [left[0], left[1], WIDTH];
		const ridge = [Math.cos(open) * WIDTH, -Math.sin(open) * WIDTH, 0];
		return smooth([[left, ridge, tip], [ridge, right, tip]]).rotateY(shift).translate(foot.x, foot.y, foot.z).rotateY(azimuth);
	};

	const parts = [
		{ color: LEAF, geometry: helpers.merge(leaves.filter(l => !l[4]).map(leaf)) },
		{ color: SHADE, geometry: helpers.merge(leaves.filter(l => l[4]).map(leaf)) },
		{ color: ROSE, sway: 0, geometry: helpers.merge(bulbs.filter(b => b[5] === ROSE).map(bulb)) },
		{ color: COPPER, sway: 0, geometry: helpers.merge(bulbs.filter(b => b[5] === COPPER).map(bulb)) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .22;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
