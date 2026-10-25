// Marguerite en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : une touffe dressée de quatre tiges raides,
// garnies jusqu'à mi-hauteur de feuilles dentées vert soutenu qui montent le long des tiges, et au sommet quatre grands
// capitules plats, chacun une large frange de ligules blanches autour d'un gros cœur jaune. Le plus fort contraste est entre
// le blanc des capitules et le vert soutenu de la touffe.
// Ce qui la sépare de la pâquerette : une plante dressée, feuillée sur la tige, et non une rosette plaquée au sol d'où montent
// des hampes nues ; des capitules deux fois plus grands. De la camomille : quatre grands capitules au lieu d'une nuée de
// petits, et des feuilles pleines dentées vert soutenu au lieu d'un buisson de fils vert tendre.
// Fiche : marguerite.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#3f7a2c'; const STEM = '#5e9038'; const WHITE = '#fbfaf3'; const DISC = '#f3be1c';

	// Hasard à graine fixe : chaque feuille et chaque fleur varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 59;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Géométrie à partir d'une liste de triangles [[x, y, z] × 3] et d'une fonction qui donne la normale de chaque sommet
	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normalOf(c).toArray()), 3));
		return g;
	};
	// Normales tirées de l'axe de la touffe vers l'extérieur et le ciel : le feuillage s'éclaire comme un volume, et une
	// feuille ou une tige vue par la tranche reste claire au lieu de virer au noir
	const bushy = tris => geometry(tris, c => new THREE.Vector3(c[0], 0, c[2]).normalize().add(new THREE.Vector3(0, 1.4, 0)).normalize());
	const up = () => new THREE.Vector3(0, 1, 0);

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const outwardOf = azimuth => new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth));

	const leaves = [], stems = [], rays = [], discs = [];

	// Une feuille de tige, sessile, oblongue et dentée : deux triangles décalés le long de la nervure. Le second, plus étroit,
	// déborde du premier en son milieu puis le premier reprend le dessus aux épaules : chaque bord porte deux dents, d'où un
	// contour en scie et non un losange. La feuille monte en biais le long de la tige. 2 triangles.
	const leaf = (base, azimuth, lean, length, width) => {
		const along = direction(azimuth, lean);
		const side = new THREE.Vector3(0, 1, 0).cross(along).normalize();
		const at = (t, s) => base.clone().addScaledVector(along, length * t).addScaledVector(side, width * s).toArray();
		leaves.push([at(0, 0), at(.62, .5), at(.52, -.5)]);
		leaves.push([at(.4, .44), at(.36, -.44), at(1, between(-.05, .05))]);
	};

	// Un capitule : sept ligules longues, chacune un triangle du cœur au bord, plus large au bout et séparée de sa voisine par
	// un jour : vu de près, une frange nette ; en jeu, une grande pastille blanche. Au-dessus, un pentagone jaune de trois
	// triangles fait le cœur, gros et plat, à peu près 0,4 du rayon. Normales sur la verticale : la fleur entière reçoit la
	// même lumière, en tache franche. 7 + 3 = 10 triangles.
	const flower = (center, axis, radius) => {
		const u = new THREE.Vector3(1, 0, 0).projectOnPlane(axis).normalize(), v = axis.clone().cross(u);
		const at = (a, r, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, lift).toArray();
		const step = Math.PI * 2 / 7, phase = between(0, step);
		for(let j = 0; j < 7; j++) {
			const a = phase + j * step + between(-.08, .08), half = step * between(.36, .42);
			const r = radius * between(.9, 1.08);
			rays.push([at(a, radius * .15, 0), at(a - half, r, 0), at(a + half, r * between(.94, 1.06), 0)]);
		}
		const turn = between(0, Math.PI * 2), disc = k => at(turn + k * Math.PI * 2 / 5, radius * .4, radius * .1);
		discs.push([disc(0), disc(1), disc(2)], [disc(0), disc(2), disc(3)], [disc(0), disc(3), disc(4)]);
	};

	// Quatre tiges étagées, chacune un triangle effilé du pied au dessous du capitule, écartées en gerbe. Deux feuilles par
	// tige : une basse, longue et ouverte, qui garnit le pied, une plus courte vers le tiers, qui monte le feuillage jusqu'à
	// mi-hauteur de la touffe. Tiges 4 × 1, feuilles 4 × 2 × 2, capitules 4 × 10 : 60 triangles.
	const heads = [[.4, .11, 1, .2], [2.0, .1, .9, .24], [3.5, .11, .95, .18], [5.1, .09, .84, .28]];
	for(const [azimuth, offset, height, tilt] of heads) {
		const a = azimuth + between(-.25, .25);
		const top = outwardOf(a).multiplyScalar(offset * between(.9, 1.1)).setY(.53 * height * between(.97, 1.03));
		const foot = outwardOf(a).multiplyScalar(.012).setY(0);
		const side = new THREE.Vector3(0, 1, 0).cross(top.clone().sub(foot)).normalize().multiplyScalar(.014);
		stems.push([foot.clone().add(side).toArray(), foot.clone().sub(side).toArray(), top.toArray()]);

		const low = foot.clone().lerp(top, between(.04, .1));
		leaf(low, a + between(-.6, .6), between(.75, .95), between(.18, .2), between(.1, .11));
		const mid = foot.clone().lerp(top, between(.32, .42));
		leaf(mid, a + Math.PI * between(.6, 1.2), between(.5, .65), between(.15, .165), between(.085, .095));

		flower(top.clone().add(new THREE.Vector3(0, .004, 0)), direction(a, tilt + between(-.06, .06)), between(.066, .071));
	}

	const parts = [
		{ color: LEAF, sway: .4, geometry: bushy(leaves) },
		{ color: STEM, sway: .6, geometry: bushy(stems) },
		{ color: WHITE, sway: .8, geometry: geometry(rays, up) },
		{ color: DISC, sway: .8, geometry: geometry(discs, up) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .35;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
