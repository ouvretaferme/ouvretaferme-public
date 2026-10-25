// Zinnia de massif en fleur, pensé pour se lire en parterre sur un rang à 20 px/m : trois tiges raides qui portent chacune
// une rosace plate tournée vers le ciel, deux rangs de ligules larges étagés autour d'un petit cœur jaune-brun, magenta ou
// écarlate, au-dessus d'un buisson dressé de feuilles ovales opposées, vert moyen, qui monte jusqu'à mi-hauteur.
// Le plus fort contraste est entre les têtes vives, mélangées, et le feuillage ; le second, entre les ligules et le cœur.
// Ce qui le sépare du dahlia : une rosace ronde à ligules larges et cœur jaune, et non une étoile orange sans cœur, des
// feuilles entières par paires et non un buisson penné sombre ; de la pivoine : des têtes plates et petites à cœur visible,
// et non des coupes froissées ; du souci : une touffe deux fois plus haute, des rosaces magenta et rouges à deux rangs ; du
// cosmos : des ligules qui se recouvrent en disque plein et des feuilles larges, pas une couronne lâche sur des plumes.
// Fiche : zinnia.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#64a040'; const MAGENTA = '#e3237e'; const SCARLET = '#e8402e'; const HEART = '#d9a020';

	// Hasard à graine fixe : chaque feuille et chaque ligule varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 23;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Géométrie à partir d'une liste de triangles de Vector3, chacun avec sa normale propre
	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flatMap(t => t.flatMap(p => p.toArray())), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flatMap(t => {
			const n = normalOf(t);
			return [n, n, n].flatMap(v => v.toArray());
		}), 3));
		return g;
	};
	// Normale de face tournée vers le haut, tirée vers une direction donnée : un pan reste lisible sans dents de scie
	const faceNormal = ([a, b, c], toward, pull) => {
		const n = b.clone().sub(a).cross(c.clone().sub(a)).normalize();
		if(n.dot(toward) < 0) n.negate();
		return n.addScaledVector(toward, pull).normalize();
	};

	const up = new THREE.Vector3(0, 1, 0);
	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const flatAt = (azimuth, r, y = 0) => new THREE.Vector3(Math.cos(azimuth) * r, y, Math.sin(azimuth) * r);
	const sideOf = azimuth => new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Une tige : deux triangles pliés en gouttière, du pied au dessous de la tête, pour qu'elle se voie sous tous les angles. 2 triangles.
	const green = [];
	const stalk = (from, to, width) => {
		const axis = to.clone().sub(from);
		const side = up.clone().cross(axis).normalize().multiplyScalar(width / 2);
		const ridge = side.clone().cross(axis).normalize().multiplyScalar(width * .5);
		green.push([from.clone().add(side), from.clone().add(ridge), to], [from.clone().add(ridge), from.clone().sub(side), to]);
	};

	// Une feuille ovale et pointue, sessile : la base contre la tige, la plus grande largeur au tiers, la nervure relevée et
	// les bords rabattus, d'où un limbe plié qui accroche la lumière d'un côté. 2 triangles.
	const leaf = (node, azimuth, lean, length, width) => {
		const side = sideOf(azimuth);
		const along = direction(azimuth, lean);
		const at = (t, s, droop) => node.clone().addScaledVector(along, length * t).addScaledVector(side, width * s).add(new THREE.Vector3(0, -droop, 0));
		const B = at(0, 0, 0), T = at(1, 0, length * .2);
		const L = at(.36, .5, length * .1), R = at(.36, -.5, length * .1);
		green.push([B, R, T], [B, T, L]);
	};

	// Une rosace de zinnia double, tournée vers le ciel : un premier rang de cinq ligules en dôme bas, dont les pointes
	// dépassent entre celles du second rang, cinq ligules larges, plus longues et plus basses, à base qui se recouvre, et
	// posé au centre un cœur pentagonal jaune-brun. Vue de dessus, une rosace ronde à dix pointes étagées sur deux hauteurs
	// autour d'un point jaune. 13 triangles.
	const petals = { [MAGENTA]: [], [SCARLET]: [] };
	const hearts = [];
	const head = (center, axis, radius, color) => {
		const u = up.clone().cross(axis);
		if(u.lengthSq() < 1e-6) u.set(1, 0, 0);
		u.normalize();
		const v = axis.clone().cross(u).normalize();
		const step = Math.PI * 2 / 5, phase = between(0, step);
		const place = (r, a, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, lift * radius);
		const inner = Array.from({ length: 5 }, (_, j) => place(radius * between(.76, .86), phase + (j + .5) * step + between(-.14, .14), 0));
		const outer = Array.from({ length: 5 }, (_, j) => {
			const a = phase + j * step + between(-.1, .1), reach = between(.94, 1.06);
			return [place(radius * .78, a - step * .56, -.05), place(radius * .78, a + step * .56, -.05), place(radius * reach, a, -.12 * reach)];
		});
		const apex = place(0, 0, .16);
		const core = Array.from({ length: 5 }, (_, j) => place(radius * .27, phase + (j + .5) * step + between(-.15, .15), .17));
		const ring = [...outer, ...inner.map((p, j) => [apex, p, inner[(j + 1) % 5]])];
		// Le second rang tire sa normale vers le dehors, le premier vers l'axe : les deux rangs prennent deux valeurs sous la même lumière
		petals[color].push(geometry(ring, t => faceNormal(t, axis, ring.indexOf(t) < 5 ? .5 : .9)));
		hearts.push(geometry([[core[0], core[1], core[2]], [core[0], core[2], core[3]], [core[0], core[3], core[4]]], () => axis));
	};

	// Trois tiges qui partent du cœur en gerbe, à des hauteurs et des écarts différents. Chacune porte des paires de feuilles
	// opposées, en croix d'un étage à l'autre, jusqu'à mi-hauteur, puis sa rosace.
	// Têtes 3 × 13 + tiges 3 × 2 + feuilles 7 × 2 = 59 triangles.
	const plan = [
		{ azimuth: .5, offset: .12, height: .48, color: MAGENTA, nodes: [.24, .54] },
		{ azimuth: 2.6, offset: .13, height: .4, color: SCARLET, nodes: [.36] },
		{ azimuth: 4.4, offset: .13, height: .44, color: MAGENTA, nodes: [.5] },
	];
	let leaves = 0;
	plan.forEach(({ azimuth, offset, height, color, nodes }) => {
		azimuth += between(-.2, .2);
		const foot = flatAt(azimuth, .012, 0);
		const top = flatAt(azimuth, offset * between(.92, 1.08), height * between(.96, 1.04));
		stalk(foot, top, .014);

		nodes.forEach((node, k) => {
			const at = foot.clone().lerp(top, node * between(.92, 1.08));
			const across = azimuth + Math.PI / 2 + k * Math.PI / 2 + between(-.25, .25);
			for(const turn of [0, Math.PI]) {
				if(leaves === 7) {
					return;
				}
				leaf(at, across + turn + between(-.2, .2), between(.95, 1.25), between(.12, .14) * (1 - k * .15), between(.055, .065));
				leaves++;
			}
		});

		const axis = direction(azimuth, between(.12, .25));
		head(top.clone().addScaledVector(axis, .004), axis, between(.07, .078), color);
	});

	const parts = [
		{ color: LEAF, sway: .7, geometry: geometry(green, t => faceNormal(t, up, .8)) },
		{ color: MAGENTA, sway: .8, geometry: helpers.merge(petals[MAGENTA]) },
		{ color: SCARLET, sway: .8, geometry: helpers.merge(petals[SCARLET]) },
		{ color: HEART, sway: .8, geometry: helpers.merge(hearts) },
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
