// Anémone de Caen en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : trois soucoupes simples de cinq pétales
// arrondis, deux violettes et une blanche, chacune marquée d'un gros cœur noir bombé, portées sur des tiges nues que coiffe
// juste sous la fleur une collerette de deux lanières vertes opposées, au-dessus d'une rosette basse en fourches étroites. Le plus
// fort contraste est entre le cœur noir et la couleur franche des pétales.
// Ce qui la sépare du coquelicot : cinq pétales en soucoupe plate et non quatre en coupe froissée, un cœur noir deux fois plus
// gros, une collerette verte sous la fleur, une touffe plus basse sans bouton penché, et du violet et du blanc au lieu du rouge ;
// de la renoncule : une fleur simple et plate à cœur noir, et non une boule pleine à cœur clair ; de la tulipe : une soucoupe
// ouverte sur des feuilles fines découpées, et non un gobelet fermé sur des lames.
// Fiche : anemone.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const VIOLET = '#5a2fa6'; const WHITE = '#f3f0f4'; const HEART = '#17141c'; const LEAF = '#5f9a45';

	// Hasard à graine fixe : chaque feuille et chaque fleur varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 97;
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

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const flatAt = (azimuth, r, y = 0) => new THREE.Vector3(Math.cos(azimuth) * r, y, Math.sin(azimuth) * r);

	// Une feuille de la rosette : deux lanières étroites qui partent du pied et s'écartent en fourche, l'une plus longue que
	// l'autre, couchées et pliée l'une contre l'autre le long du pétiole. Trois feuilles font six pointes : le persil. 2 triangles.
	const leaves = [];
	const leaf = (azimuth, length, fork) => {
		const foot = flatAt(azimuth, .004, .003);
		const lobe = (a, l, w, lift) => {
			const side = new THREE.Vector3(-Math.sin(a), 0, Math.cos(a));
			const shoulder = flatAt(a, l * .5, lift * 1.25).addScaledVector(side, w);
			leaves.push([foot.toArray(), shoulder.toArray(), flatAt(a, l, lift * .5).toArray()]);
			return shoulder;
		};
		lobe(azimuth - fork, length, .019, between(.022, .032));
		lobe(azimuth + fork * between(.8, 1.2), length * between(.7, .85), -.017, between(.012, .02));
	};
	for(let i = 0; i < 3; i++) {
		leaf(i * 2.0944 + 1.25 + between(-.3, .3), between(.096, .112), between(.36, .5));
	}

	// Une fleur : une soucoupe en éventail depuis son fond vers dix points du bord, deux épaules par pétale. Chaque pétale a
	// son rayon et sa hauteur, si bien que les cinq se lisent l'un à côté de l'autre et non en décagone régulier. Les normales
	// rayonnent depuis l'axe vers le haut, d'où une soucoupe ronde sans arête. Au centre, une pyramide noire basse à cinq pans qui dépasse
	// du bord : le cœur bombé et sa couronne d'étamines. Sous la fleur, deux lanières vertes opposées, plus longues que les
	// pétales, pour que la collerette pointe de chaque côté de la soucoupe, de profil comme vue d'en haut.
	// Par fleur : soucoupe 10 + cœur 5 + tige 1 + collerette 2 = 18 triangles.
	const cups = { [VIOLET]: [], [WHITE]: [] }, hearts = [], stems = [], ruffs = [];
	const flower = (color, azimuth, offset, lean, height, radius, phase) => {
		const axis = direction(azimuth, lean);
		const tilt = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const center = flatAt(azimuth, offset, height);
		const place = (r, a, y) => new THREE.Vector3(Math.cos(a) * r, y, Math.sin(a) * r).applyAxisAngle(tilt, -lean).add(center);
		const base = place(0, 0, -radius * .2);
		const rim = [];
		for(let k = 0; k < 5; k++) {
			const a = phase + k * Math.PI * 2 / 5, r = radius * between(.82, 1.12), y = between(-.08, .1) * radius;
			rim.push(place(r * .96, a - .4, y));
			rim.push(place(r, a + .4, y + between(-.03, .03) * radius));
		}
		const radial = p => p.clone().sub(base).projectOnPlane(axis).normalize().multiplyScalar(.3).add(axis).normalize();
		const tris = rim.map((p, j) => [base.toArray(), rim[(j + 1) % 10].toArray(), p.toArray()]);
		cups[color].push(geometry(tris, c => {
			const v = new THREE.Vector3(...c);
			return v.distanceTo(base) < 1e-6 ? axis.clone() : radial(v);
		}));

		const top = place(0, 0, radius * .2);
		const ring = [0, 1, 2, 3, 4].map(k => place(radius * between(.4, .47), phase + .3 + k * Math.PI * 2 / 5, -radius * .09));
		hearts.push(geometry(ring.map((p, k) => [top.toArray(), ring[(k + 1) % 5].toArray(), p.toArray()]), c => {
			const v = new THREE.Vector3(...c);
			return v.distanceTo(top) < 1e-6 ? axis.clone() : v.clone().sub(top).projectOnPlane(axis).normalize().multiplyScalar(.6).add(axis).normalize();
		}));

		const foot = flatAt(azimuth, .008), width = .02;
		stems.push([foot.clone().addScaledVector(tilt, width / 2).toArray(), base.toArray(), foot.clone().addScaledVector(tilt, -width / 2).toArray()]);

		const collar = place(0, 0, -radius * .5);
		for(let k = 0; k < 2; k++) {
			const a = azimuth + Math.PI / 2 + k * Math.PI + between(-.35, .35);
			const tip = place(radius * between(1.35, 1.6), a, -radius * .55);
			const sideways = new THREE.Vector3(-Math.sin(a), 0, Math.cos(a)).multiplyScalar(radius * .34);
			ruffs.push([collar.clone().add(sideways).toArray(), tip.toArray(), collar.clone().sub(sideways).toArray()]);
		}
	};

	// Trois fleurs à des hauteurs, des écarts et des tailles différents, qui s'ouvrent vers l'extérieur : deux violettes, une blanche
	const blooms = [[VIOLET, .2, .058, .42, .32, .05], [WHITE, 2.3, .064, .5, .27, .046], [VIOLET, 4.2, .054, .36, .3, .052]];
	for(const [color, azimuth, offset, lean, height, radius] of blooms) {
		flower(color, azimuth + between(-.2, .2), offset, lean + between(-.05, .05), height * between(.96, 1.04), radius, between(0, 1.2));
	}

	const parts = [
		{ color: LEAF, geometry: helpers.merge([smooth(leaves), smooth(ruffs)]) },
		{ color: LEAF, sway: .8, geometry: smooth(stems) },
		{ color: VIOLET, sway: .8, geometry: helpers.merge(cups[VIOLET]) },
		{ color: WHITE, sway: .8, geometry: helpers.merge(cups[WHITE]) },
		{ color: HEART, sway: .8, geometry: helpers.merge(hearts) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .2;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
