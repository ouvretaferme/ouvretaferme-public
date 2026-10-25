// Delphinium vivace en fleur, pensé pour se lire en parterre sur un rang à 20 px/m : une hampe maîtresse très haute et une
// secondaire plus courte, chacune un épi mince fait de fleurs séparées, étoiles bleu gentiane à cinq pointes et œil blanc,
// étagées en spirale autour d'une tige verte qu'on voit entre elles, espacées en bas, serrées en boutons en haut. Au pied, une
// touffe basse de feuilles découpées en lanières inégales, en éventail retombant.
// Ce qui le sépare du lupin, sa confusion du tour précédent : des fleurs ouvertes et plates qui laissent voir la hampe, et non
// un cône plein de pois serrés ; un bleu profond et non bleu-mauve ; des feuilles en lanières fourchues et retombantes, en
// éventail, et non des folioles rondes rayonnant en étoile plate au bout d'un long pétiole ; une hampe deux fois plus haute que
// la touffe. De la lavande : trois fois plus haut que large, deux épis et non une brosse ; du bleuet : le bleu en colonne.
// Fiche : delphinium.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const GENTIAN = '#2846c8'; const EYE = '#f2efe4'; const LEAF = '#4a8436'; const BUD = '#1e2a80';

	// Hasard à graine fixe : chaque fleur et chaque lanière varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 71;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const UP = v(0, 1, 0);
	const outwardOf = azimuth => v(Math.cos(azimuth), 0, Math.sin(azimuth));
	const sideOf = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Une géométrie depuis des triangles de Vector3, chaque triangle avec sa normale (une fleur ou une lanière est plane)
	const geometry = (tris, normalOf = tri => tri[1].clone().sub(tri[0]).cross(tri[2].clone().sub(tri[0])).normalize()) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat().flatMap(p => p.toArray()), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flatMap(tri => {
			const n = normalOf(tri);
			if(n.y < 0) n.negate();
			return [n, n, n].flatMap(p => p.toArray());
		}), 3));
		return g;
	};

	const gentian = [], eyes = [], buds = [], leaf = [];

	// Une fleur : cinq pétales pointus autour d'un trou pentagonal, en coupelle tournée vers l'extérieur de l'épi, et un œil
	// blanc posé dans le trou. Les cinq pointes séparées par une échancrure font l'étoile, l'œil fait la fleur et non la
	// pastille. 5 + 1 triangles.
	const flower = (center, facing, radius) => {
		const n = facing.clone().normalize();
		const a = UP.clone().cross(n).normalize(), b = n.clone().cross(a);
		const turn = between(0, Math.PI * 2);
		const at = (angle, r, forward) => center.clone().addScaledVector(a, Math.cos(angle) * r).addScaledVector(b, Math.sin(angle) * r).addScaledVector(n, forward);
		const step = Math.PI * 2 / 5;
		for(let k = 0; k < 5; k++) {
			const angle = turn + k * step;
			const r = radius * between(.9, 1.1);
			gentian.push([at(angle - step / 2, radius * .58, 0), at(angle, r, r * .22), at(angle + step / 2, radius * .58, 0)]);
		}
		eyes.push([0, 1, 2].map(k => at(turn + .3 + k * Math.PI * 2 / 3, radius * .42, radius * .06)));
	};

	// Un épi : des fleurs sur une spirale d'angle d'or, l'écart diminuant vers le haut et la taille avec lui, chaque fleur
	// écartée de l'axe par son pédicelle et regardant dehors, un peu vers le bas comme les fleurs ouvertes. Au-dessus, la
	// grappe de boutons : une pointe à trois pans, effilée et serrée. La hampe est une lame verte du pied à la pointe,
	// doublée en croix pour se voir de tous les côtés. Fleurs × 6 + boutons 3 + hampe 2 triangles.
	const spike = (foot, lean, leanAzimuth, start, length, count, radius) => {
		const axis = outwardOf(leanAzimuth).multiplyScalar(Math.sin(lean)).setY(Math.cos(lean));
		const point = t => foot.clone().addScaledVector(axis, t);
		const budStart = start + length * .82;
		let azimuth = between(0, Math.PI * 2);
		for(let i = 0; i < count; i++) {
			const k = i / (count - 1);
			const t = start + (budStart - start - radius * .6) * (1 - Math.pow(1 - k, 1.6));
			azimuth += 2.39996 + between(-.25, .25);
			const r = radius * (1.1 - k * .4) * between(.92, 1.08);
			const out = outwardOf(azimuth);
			flower(point(t).addScaledVector(out, radius * .45), out.clone().setY(.35), r);
		}
		const tip = point(start + length), twist = between(0, Math.PI * 2);
		const ring = [0, 1, 2].map(k => point(budStart - radius * .2).addScaledVector(outwardOf(twist + k * Math.PI * 2 / 3), radius * between(.4, .5)));
		for(let k = 0; k < 3; k++) buds.push([ring[k], tip, ring[(k + 1) % 3]]);
		const s1 = sideOf(leanAzimuth).multiplyScalar(.009), s2 = outwardOf(leanAzimuth).multiplyScalar(.009);
		leaf.push([foot.clone().add(s1), foot.clone().sub(s1), tip.clone()], [foot.clone().add(s2), foot.clone().sub(s2), tip.clone()]);
	};

	// Hampe maîtresse : épi de 0,9 m, de 0,7 à 1,6 m, neuf fleurs. Secondaire, penchée de 15° : épi de 0,46 m, trois fleurs.
	// Les fleurs sont exagérées deux fois (13 cm) : à 20 px/m, elles doivent rester des points bleus séparés et non une traînée.
	// 12 fleurs × 6 + 2 × (3 + 2) = 82 triangles.
	const main = between(0, 6.28);
	spike(v(0, .04, 0), between(.02, .04), main, .7, .9, 9, .066);
	const second = main + Math.PI * .8 + between(-.3, .3);
	spike(outwardOf(second).multiplyScalar(.04).setY(.04), between(.13, .17), second, .58, .46, 3, .058);

	// Une feuille : trois segments en coin, chacun fendu au bout en deux dents pointues inégales, ouverts en éventail de 150°
	// et retombant vers l'extérieur. C'est la feuille profondément découpée du delphinium, déchiquetée, et non l'étoile plate
	// de folioles rondes du lupin. Coin 1 + dents 2 triangles par segment, 9 par feuille.
	const palmate = (azimuth, height, length, droop) => {
		const origin = outwardOf(azimuth).multiplyScalar(.02).setY(height);
		const fan = between(1.1, 1.35);
		for(let k = 0; k < 3; k++) {
			const angle = azimuth + (k - 1) * fan * .5 + between(-.12, .12);
			const size = length * (k === 1 ? 1 : .8) * between(.85, 1.1);
			const dir = outwardOf(angle), w = sideOf(angle);
			const at = (t, s) => origin.clone().addScaledVector(dir, size * t).addScaledVector(w, size * s).setY(height + size * (.25 * t - droop * t * t));
			const left = at(.55, .2 * between(.85, 1.15)), right = at(.55, -.2 * between(.85, 1.15)), notch = at(.68, between(-.04, .04));
			leaf.push([origin, left, right]);
			leaf.push([left, at(between(.92, 1.05), between(.16, .26)), notch], [notch, at(between(.85, 1), -between(.14, .24)), right]);
		}
	};
	// Quatre feuilles sur un angle d'or bousculé, les basses longues et couchées, les hautes plus courtes et plus dressées : un
	// dôme bas d'où sortent les hampes. 36 triangles.
	for(let i = 0; i < 4; i++) palmate(main + i * 2.39996 + between(-.3, .3), .1 + i * .07 + between(-.02, .02), (.28 - i * .03) * between(.92, 1.08), .75 - i * .12);

	const parts = [
		{ color: LEAF, sway: .4, geometry: geometry(leaf) },
		{ color: GENTIAN, sway: .8, geometry: geometry(gentian) },
		{ color: EYE, sway: .8, geometry: geometry(eyes) },
		{ color: BUD, sway: .8, geometry: geometry(buds) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .5;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
