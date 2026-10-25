// Rudbeckia en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : trois tiges raides et presque nues qui
// portent chacune une marguerite jaune d'or, ligules retombantes en jupe sous un cône brun-noir bombé qui dépasse, très
// au-dessus d'une rosette basse de feuilles lancéolées, vert olive sombre. Le plus fort contraste est entre le cône noir
// et le jaune des ligules, puis entre ce jaune et le feuillage sombre.
// Ce qui la sépare du souci, lu rudbeckia par un juge : une touffe une fois et demie plus haute que large et non un
// coussin, du jaune d'or sans orange, un cœur noir en cône qui dépasse et non un disque plat brun-orangé, des ligules qui
// retombent au lieu de remonter en coupe, et un feuillage sombre loin sous les fleurs ; du zinnia : une fleur ouverte à
// œil noir et non un dôme plein magenta.
// Fiche : rudbeckia.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#4c6a2c'; const STEM = '#5e7f33'; const GOLD = '#f6c823'; const CONE = '#2b1810';

	// Hasard à graine fixe : chaque feuille et chaque tête varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 67;
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
	// Normales lissées : chaque sommet porte la moyenne des faces qui le partagent, d'où une feuille pliée qui se lit ronde
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			if(n.y < 0) n.negate();
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		return geometry(tris, c => normals.get(key(c)).clone().normalize());
	};
	const arrays = tris => tris.map(t => t.map(p => p.toArray()));

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const flatAt = (azimuth, r, y = 0) => new THREE.Vector3(Math.cos(azimuth) * r, y, Math.sin(azimuth) * r);
	const sideOf = azimuth => new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Une tige : deux triangles pliés en gouttière, du pied au dessous du cône, pour qu'elle se voie sous tous les angles.
	// 2 triangles.
	const stems = [];
	const stalk = (from, to, width) => {
		const axis = to.clone().sub(from);
		const side = new THREE.Vector3(0, 1, 0).cross(axis).normalize().multiplyScalar(width / 2);
		const ridge = side.clone().cross(axis).normalize().multiplyScalar(width * .5);
		stems.push(
			[from.clone().add(side), from.clone().add(ridge), to],
			[from.clone().add(ridge), from.clone().sub(side), to],
		);
	};

	// Une feuille lancéolée et pointue : la plus grande largeur au tiers, une épaule d'un seul côté aux trois quarts,
	// alternée d'une feuille à l'autre, puis une pointe longue. Le contour a cinq côtés, et les bords plus bas que la
	// nervure plient le limbe. 3 triangles.
	const leaves = [];
	const leaf = (node, azimuth, lean, length, width, hand) => {
		const side = sideOf(azimuth);
		const along = direction(azimuth, lean);
		const at = (t, s, droop) => node.clone().addScaledVector(along, length * t).addScaledVector(side, width * s * hand).add(new THREE.Vector3(0, -droop, 0));
		const B = at(0, 0, 0), T = at(1, 0, length * .2);
		const L = at(.32, .5, length * .06), R = at(.32, -.5, length * .06);
		const S = at(.7, .3, length * .13);
		leaves.push([B, R, T], [B, T, S], [B, S, L]);
	};

	// Une tête : sept ligules qui partent d'un anneau sous le cône et retombent en jupe, pointes décalées d'un demi-pas, puis
	// un cône à cinq pans, dressé au-dessus du plan des ligules. Sept et non huit : le pan gagné va au cône, dont quatre pans
	// se lisaient carrés de dessus, et c'est le cône qui fait reconnaître. Les normales des ligules suivent l'axe, d'où une
	// tache jaune franche ; celles du cône rayonnent, d'où un bouton rond et non une pyramide.
	// Ligules 7 + cône 5 = 12 triangles.
	const rays = [], cones = [];
	const head = (center, axis, radius) => {
		const u = new THREE.Vector3(0, 1, 0).cross(axis).normalize();
		if(u.lengthSq() < 1e-6) u.set(1, 0, 0);
		const v = axis.clone().cross(u).normalize();
		const place = (r, a, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, lift);
		const step = Math.PI * 2 / 7, phase = between(0, step);
		const coneRadius = radius * .44;
		const ring = Array.from({ length: 7 }, (_, j) => place(coneRadius * .9, phase + j * step, 0));
		const tips = Array.from({ length: 7 }, (_, j) => place(radius * between(.9, 1.08), phase + (j + .5) * step + between(-.08, .08), -radius * between(.3, .42)));
		const up = () => axis.clone();
		rays.push(geometry(arrays(ring.map((p, j) => [p, tips[j], ring[(j + 1) % 7]])), up));

		const apex = place(0, 0, coneRadius * 1.05);
		const base = Array.from({ length: 5 }, (_, j) => place(coneRadius, phase + j * Math.PI * 2 / 5, coneRadius * .12));
		const outward = c => {
			const p = new THREE.Vector3(...c).sub(center).projectOnPlane(axis);
			return p.lengthSq() < 1e-10 ? axis.clone() : p.normalize().add(axis.clone().multiplyScalar(.6)).normalize();
		};
		cones.push(geometry(arrays(base.map((p, j) => [apex, base[(j + 1) % 5], p])), outward));
	};

	// Quatre grandes feuilles basses, presque couchées, qui font la rosette, sur un angle d'or bousculé.
	// 4 × 3 = 12 triangles.
	for(let i = 0; i < 4; i++) {
		leaf(flatAt(i * 2.39996 + 1, .012, .012), i * 2.39996 + between(-.3, .3), between(.95, 1.12), between(.2, .23), between(.072, .082), i % 2 ? 1 : -1);
	}

	// Trois tiges qui partent du cœur en gerbe étroite, à des hauteurs et des écarts différents, chacune coiffée de sa tête.
	// Les deux premières portent une feuille sessile, étroite, aux deux cinquièmes de leur hauteur.
	// Tiges 3 × 2 + feuilles 2 × 3 + têtes 3 × 12 = 48 triangles, 60 avec la rosette.
	const plan = [
		{ azimuth: .5, offset: .1, height: .46, node: .3 },
		{ azimuth: 2.6, offset: .09, height: .4, node: .36 },
		{ azimuth: 4.5, offset: .12, height: .43, node: 0 },
	];
	plan.forEach(({ azimuth, offset, height, node }, i) => {
		azimuth += between(-.2, .2);
		const foot = flatAt(azimuth, .012, 0);
		const top = flatAt(azimuth, offset * between(.92, 1.08), height * between(.96, 1.04));
		stalk(foot, top, .016);

		if(node > 0) {
			leaf(foot.clone().lerp(top, node * between(.9, 1.1)), azimuth + Math.PI / 2 + between(-.4, .4), between(.9, 1.05), between(.12, .14), between(.04, .046), i % 2 ? 1 : -1);
		}

		const axis = direction(azimuth, between(.25, .4));
		head(top.clone().addScaledVector(axis, .004), axis, between(.06, .066));
	});

	const parts = [
		{ color: LEAF, geometry: smooth(arrays(leaves)) },
		{ color: STEM, sway: .8, geometry: smooth(arrays(stems)) },
		{ color: GOLD, sway: .8, geometry: helpers.merge(rays) },
		{ color: CONE, sway: .8, geometry: helpers.merge(cones) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .4;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
