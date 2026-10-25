// Lupin vivace en fleur (hybrides Russell), pensé pour se lire seul ou en ligne sur un rang à 20 px/m : trois épis en cône
// plein et trapu, violet mauve à pointe pâle en boutons, au-dessus d'un dôme de trois feuilles palmées en étoile plate.
// Ce qui le sépare du delphinium : un cône plein, large à la base, quatre fois plus long que large, sans jour entre les
// fleurs, et une pointe claire, contre une chandelle mince, aérée, bleu profond à pointe sombre ; des étoiles de folioles
// fines rayonnant à plat, contre des lanières en coin retombant en éventail. De la digitale : des fleurs tout autour de
// l'axe, en cône symétrique, et non des clochettes pendues d'un seul côté.
// Fiche : lupin.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const MAUVE = '#7a52c2'; const BUD = '#f6f0fc'; const LEAF = '#5c9440'; const STEM = '#33602a';

	// Hasard à graine fixe : chaque épi et chaque foliole varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 113;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const outwardOf = azimuth => v(Math.cos(azimuth), 0, Math.sin(azimuth));
	const sideOf = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));
	const faceNormal = tri => tri[1].clone().sub(tri[0]).cross(tri[2].clone().sub(tri[0])).normalize();

	// Une géométrie depuis des triangles [a, b, c], chacun avec ses normales [na, nb, nc] ou, à défaut, sa normale de face
	// tournée vers le ciel (une foliole ou une tige est une lame vue des deux côtés)
	const geometry = list => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(list.flatMap(({ tri }) => tri.flatMap(p => p.toArray())), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(list.flatMap(({ tri, normals }) => {
			if(normals) return normals.flatMap(n => n.toArray());
			const n = faceNormal(tri);
			if(n.y < 0) n.negate();
			return [n, n, n].flatMap(p => p.toArray());
		}), 3));
		return g;
	};

	const mauve = [], bud = [], leaf = [], stem = [];

	// Un épi : un cône plein de fleurs serrées, tourné comme au tour, aux normales lissées par sommet pour que le contour
	// reste rond. Les anneaux alternent d'un demi-pan et leur rayon varie de ±8 % : le contour se bosselle en verticilles de
	// fleurs au lieu d'une pyramide lisse. Corps mauve par bandes, pointe en boutons pâle. Une tige sombre, lame doublée en
	// croix, le relie au sol à travers le feuillage.
	// Pans × 2 par bande + pans pour la pointe + 2 pour la tige.
	const spike = (foot, lean, leanAzimuth, base, length, radius, sides, rings) => {
		const axis = outwardOf(leanAzimuth).multiplyScalar(Math.sin(lean)).setY(Math.cos(lean));
		const a = sideOf(leanAzimuth), b = axis.clone().cross(a).normalize();
		const center = t => foot.clone().addScaledVector(axis, base + length * t);
		const turn = between(0, Math.PI * 2);
		const ring = (t, r, offset) => Array.from({ length: sides }, (_, k) => {
			const angle = turn + (k + offset) * Math.PI * 2 / sides;
			const radial = a.clone().multiplyScalar(Math.cos(angle)).addScaledVector(b, Math.sin(angle));
			return { p: center(t).addScaledVector(radial, r * radius * between(.88, 1.12)), n: radial.clone().addScaledVector(axis, .45).normalize() };
		});
		const levels = rings.map(([t, r], i) => ring(t, r, i % 2 ? .5 : 0));
		for(let i = 0; i < levels.length - 1; i++) {
			const low = levels[i], high = levels[i + 1], shift = i % 2 ? 1 : 0;
			for(let k = 0; k < sides; k++) {
				const l0 = low[k], l1 = low[(k + 1) % sides];
				const h0 = high[(k + sides - 1 + shift) % sides], h1 = high[(k + shift) % sides];
				mauve.push({ tri: [l0.p, h1.p, l1.p], normals: [l0.n, h1.n, l1.n] });
				mauve.push({ tri: [l0.p, h0.p, h1.p], normals: [l0.n, h0.n, h1.n] });
			}
		}
		const top = levels[levels.length - 1], tip = center(1);
		for(let k = 0; k < sides; k++) bud.push({ tri: [top[k].p, tip, top[(k + 1) % sides].p], normals: [top[k].n, axis, top[(k + 1) % sides].n] });
		const s1 = a.clone().multiplyScalar(.008), s2 = b.clone().multiplyScalar(.008), neck = center(.02);
		stem.push({ tri: [foot.clone().add(s1), foot.clone().sub(s1), neck] }, { tri: [foot.clone().add(s2), foot.clone().sub(s2), neck] });
	};

	// Épi maître : 0,58 m de 0,42 à 1 m, 15 cm de diamètre à la base (exagéré d'un tiers pour l'écran), cinq pans, deux bandes
	// bosselées qui l'effilent en cône et la pointe pâle sur le dernier quart : 25 triangles. Deux secondaires plus courts et
	// penchés de 8 à 11°, quatre pans, une bande : 12 triangles chacun. Leur pied sort du dernier étage de feuilles.
	// 49 triangles d'épis et 6 de tiges.
	const main = between(0, Math.PI * 2);
	spike(v(0, 0, 0), between(.02, .05), main, .42, .58, .074, 5, [[0, 1], [.4, .64], [.76, .3]]);
	const second = main + Math.PI * .75 + between(-.25, .25), third = main - Math.PI * .7 + between(-.25, .25);
	spike(outwardOf(second).multiplyScalar(.045), between(.14, .17), second, .37, .4, .062, 4, [[0, 1], [.66, .38]]);
	spike(outwardOf(third).multiplyScalar(.045), between(.16, .2), third, .33, .34, .056, 4, [[0, 1], [.64, .38]]);

	// Une feuille : une étoile plate de folioles étroites rayonnant d'un même point, en parasol au bout de son pétiole. Chaque
	// foliole est une lancette pliée sur sa nervure (la nervure plus haute que les bords), la plus large aux deux tiers, sa
	// longueur et son angle variant de ±15 %, et les pointes retombent un peu. C'est la feuille digitée du lupin, en roue, et
	// non les lanières en coin du delphinium. 2 triangles par foliole.
	const palmate = (azimuth, height, length, count, tilt) => {
		const origin = outwardOf(azimuth).multiplyScalar(.07).setY(height);
		const slope = outwardOf(azimuth).multiplyScalar(tilt);
		const turn = between(0, Math.PI * 2);
		for(let k = 0; k < count; k++) {
			const angle = turn + (k + between(-.15, .15)) * Math.PI * 2 / count;
			const size = length * between(.85, 1.12);
			const dir = outwardOf(angle), w = sideOf(angle);
			const at = (t, s, lift) => {
				const p = origin.clone().addScaledVector(dir, size * t).addScaledVector(w, size * s);
				return p.setY(height + p.clone().sub(origin).dot(slope) + lift - .35 * size * t * t);
			};
			const tip = at(1, 0, 0), left = at(.62, .12, -.012), right = at(.62, -.12, -.012), heart = at(.05, 0, .01);
			leaf.push({ tri: [heart, left, tip] }, { tri: [heart, tip, right] });
		}
	};
	// Trois feuilles étagées sur un angle d'or bousculé, la basse plus large et penchée vers dehors, la haute plus petite :
	// un dôme de 0,5 m d'où sortent les épis. 32 folioles, 64 triangles.
	const leaves = [[.14, .2, 11, .3], [.24, .18, 11, .22], [.33, .16, 10, .15]];
	leaves.forEach(([height, length, count, tilt], i) => palmate(main + .9 + i * 2.39996 + between(-.3, .3), height + between(-.02, .02), length, count, -tilt));

	const parts = [
		{ color: LEAF, sway: .4, geometry: geometry(leaf) },
		{ color: STEM, sway: .5, geometry: geometry(stem) },
		{ color: MAUVE, sway: .7, geometry: geometry(mauve) },
		{ color: BUD, sway: .8, geometry: geometry(bud) },
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
