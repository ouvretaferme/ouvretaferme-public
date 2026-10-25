// Vesce de couvert en début de floraison, pensée pour se lire en nappe sur un parterre à 20 px/m : une touffe emmêlée, plus
// haute que large, de six grandes feuilles pennées portées à des hauteurs différentes et qui se croisent. Chaque feuille
// a trois paires de folioles larges, en ovale tronqué, bien détachées le long d'un axe pâle, et finit par une
// longue vrille fourchue et enroulée, plus claire que le feuillage, qui dépasse la touffe. Trois grappes courtes, penchées,
// portent leurs fleurs d'un seul côté, pourpres et violettes en alternance, sous le niveau des feuilles hautes.
// Ce qui la sépare de la lavande et de la sauge : aucun épi dressé en tête, des folioles larges au lieu de lanières, et la
// vrille. Du fenouil : aucun fil, aucune ombelle. Du pois : aucun filet, des folioles nombreuses et petites, des fleurs
// pourpres. De la lentille : des folioles larges et peu nombreuses, une vrille longue, la couleur en grappes.
// Fiche : vesce.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#a4dc62'; const TENDRIL = '#e2f0a2'; const PURPLE = '#a43cb4'; const VIOLET = '#6e58d8';

	// Hasard à graine fixe : chaque feuille et chaque grappe varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 47;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);
	const flip = () => rand() < .5 ? 1 : -1;
	const UP = new THREE.Vector3(0, 1, 0);

	// Le matériau éclaire les deux faces et retourne la normale d'une face vue de dos. Chaque sommet porte la moyenne des faces
	// qui le partagent, retournées puis tirées vers le ciel : une foliole dressée reste d'un vert tendre au lieu de virer à l'olive.
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			if(n.y < 0) n.negate();
			n.normalize().addScaledVector(UP, 2.5);
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normals.get(key(c)).clone().normalize().toArray()), 3));
		return g;
	};
	const tri = (...points) => points.map(point => point.toArray());

	const outward = azimuth => new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth));
	const across = azimuth => new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
	// Un brin : un triangle effilé, large au départ, pointu à l'arrivée
	const strand = (from, to, wide, width) => tri(from.clone().addScaledVector(wide, width), from.clone().addScaledVector(wide, -width), to);

	const leaflets = [], pale = [], purple = [], violet = [];

	// Une feuille pennée : un axe droit et pâle, et de chaque côté des folioles en ovale tronqué (deux triangles : la base
	// étroite sur l'axe, deux épaules décalées, une pointe courte), couchées vers le bout de la feuille et un peu relevées
	// en gouttière. L'écart entre deux paires dépasse la largeur d'une foliole : elles restent détachées.
	// L'axe se prolonge en vrille : deux brins qui s'écartent, chacun coudé puis ramené en crochet vers le haut.
	// pairs × 4 + 1 axe + 4 de vrille : 17 triangles à trois paires.
	const leaf = (origin, azimuth, pitch, length, pairs) => {
		const along = outward(azimuth).multiplyScalar(Math.cos(pitch)).addScaledVector(UP, Math.sin(pitch));
		const flat = across(azimuth), roll = between(.15, .4) * flip();
		const lift = new THREE.Vector3().crossVectors(flat, along).normalize();
		if(lift.y < 0) lift.negate();
		const side = flat.clone().multiplyScalar(Math.cos(roll)).addScaledVector(lift, Math.sin(roll));
		const face = lift.clone().multiplyScalar(Math.cos(roll)).addScaledVector(flat, -Math.sin(roll));
		const rib = u => origin.clone().addScaledVector(along, u * length);
		const step = .66 / (pairs - 1);
		for(let i = 0; i < pairs; i++) {
			for(const hand of [1, -1]) {
				const u = .16 + i * step + between(-.02, .02);
				const size = length * .42 * (.82 + .18 * Math.sin(Math.PI * (i + .5) / pairs)) * between(.88, 1.14), width = size * between(.62, .74);
				const sweep = between(.85, 1.1);
				const reach = along.clone().multiplyScalar(Math.cos(sweep)).addScaledVector(side, hand * Math.sin(sweep)).addScaledVector(face, between(.2, .4)).normalize();
				const broad = new THREE.Vector3().crossVectors(reach, face).normalize();
				const base = rib(u);
				const near = base.clone().addScaledVector(reach, size * between(.42, .52)).addScaledVector(broad, width * .5);
				const far = base.clone().addScaledVector(reach, size * between(.7, .8)).addScaledVector(broad, -width * .5);
				const point = base.clone().addScaledVector(reach, size).addScaledVector(broad, width * between(-.1, .2)).addScaledVector(face, -size * .12);
				leaflets.push(tri(base, near, far), tri(near, point, far));
			}
		}
		pale.push(strand(origin, rib(1.02), side, length * .035));
		const fork = rib(.98), curl = flip(), thick = length * .036;
		[[curl, .34, .3, .22], [-curl, .22, .14, .42]].forEach(([turn, away, ahead, rise]) => {
			const elbow = fork.clone().addScaledVector(side, turn * length * away * between(.8, 1.2)).addScaledVector(along, length * ahead).addScaledVector(UP, length * rise * between(.8, 1.2));
			const hook = elbow.clone().addScaledVector(side, -turn * length * between(.2, .28)).addScaledVector(UP, length * between(.1, .18)).addScaledVector(along, -length * between(.14, .22));
			pale.push(strand(fork, elbow, face, thick), strand(elbow, hook, side, thick * .8));
		});
	};

	// Six tiges pâles partent du pied en s'écartant et s'arrêtent à six hauteurs étagées. Chaque feuille repart de sa tige
	// vers l'autre bord en repassant au-dessus du cœur, presque à plat : elle montre son dessus, d'un vert tendre, et les six
	// se croisent en colonne. C'est ce croisement qui fait le coussin plein. La plus haute se redresse pour fermer le dôme.
	// 6 × (1 tige + 17) = 108 triangles.
	const stems = [
		{ azimuth: 4.8, radius: .09, height: .26, turn: 2.6, pitch: .2, length: .22 },
		{ azimuth: .9, radius: .1, height: .34, turn: -2.7, pitch: .3, length: .22 },
		{ azimuth: 3.3, radius: .09, height: .18, turn: 2.9, pitch: .1, length: .22 },
		{ azimuth: 2.4, radius: .08, height: .5, turn: 2.8, pitch: .55, length: .21 },
		{ azimuth: 5.7, radius: .09, height: .42, turn: -2.5, pitch: .35, length: .22 },
		{ azimuth: 0, radius: .09, height: .09, turn: -2.8, pitch: .12, length: .22 },
	].map(stem => {
		const a = stem.azimuth + between(-.2, .2);
		const foot = outward(a).multiplyScalar(.012);
		const knee = outward(a).multiplyScalar(stem.radius * between(.85, 1.15)).addScaledVector(UP, stem.height * between(.92, 1.08));
		pale.push(strand(foot, knee, across(a), .008));
		leaf(knee, a + stem.turn + between(-.2, .2), stem.pitch + between(-.08, .08), stem.length * between(.92, 1.08), 3);
		return { azimuth: a, foot, knee };
	});

	// Trois grappes courtes, sur les trois tiges de mi-hauteur : un pédoncule penché vers le dehors et trois fleurs
	// tubulaires qui pendent du même flanc, pourpres et violettes en alternance. Elles restent sur le flanc du coussin.
	// 3 × (1 + 3) = 12 triangles.
	stems.slice(0, 3).forEach((stem, k) => {
		const a = stem.azimuth + between(-.5, .5), lean = between(.9, 1.2), length = between(.1, .12);
		const base = stem.foot.clone().lerp(stem.knee, between(.6, .75)), side = across(a).multiplyScalar(k % 2 ? 1 : -1);
		const along = outward(a).multiplyScalar(Math.sin(lean)).addScaledVector(UP, Math.cos(lean));
		const tip = base.clone().addScaledVector(along, length);
		pale.push(strand(base, tip, side, .005));
		const hang = side.clone().multiplyScalar(.45).addScaledVector(UP, -.9);
		for(let i = 0; i < 3; i++) {
			const u = .42 + i * .22, size = .055 * (1 - .12 * i) * between(.88, 1.12);
			((i + k) % 2 ? violet : purple).push(tri(
				base.clone().lerp(tip, u - .1), base.clone().lerp(tip, u + .1),
				base.clone().lerp(tip, u + .04).addScaledVector(hang, size).addScaledVector(along, size * between(.25, .45)),
			));
		}
	});

	const parts = [
		{ color: LEAF, sway: .7, geometry: smooth(leaflets) },
		{ color: TENDRIL, sway: .7, geometry: smooth(pale) },
		{ color: PURPLE, sway: .8, geometry: smooth(purple) },
		{ color: VIOLET, sway: .8, geometry: smooth(violet) },
	];
	// Emprise et hauteur de la fiche : la touffe est plus haute que large d'un tiers.
	return { d: .35, h: .47, parts };
}
