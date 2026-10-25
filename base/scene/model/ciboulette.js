// Ciboulette, pensée pour se lire sur un rang à 20 px/m : une brosse dense de brins fins et raides, vert du pied à la
// pointe, qui s'ouvrent en gerbe depuis un pied étroit, et trois pompons mauves ronds portés juste au-dessus. Le plus fort
// contraste est entre les pompons clairs et le feuillage sombre.
// Ce qui la sépare de la ciboule : des dizaines de brins fins et aucun fût blanc ; de l'oignon : ni bulbe ni pied unique ;
// du poireau : rien de plat ni de retombant ; d'une touffe d'herbe : des brins raides, un vert bleuté et les pompons.
// Fiche : ciboulette.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const BLOOM = '#b98fd0'; const LEAF = '#4f8f3e'; const DARK = '#2f6232';

	// Hasard à graine fixe : chaque brin varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 23;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Pièce arrondie : une liste de triangles [[x, y, z] × 3] dont chaque sommet porte la moyenne des normales des faces
	// qui le partagent. Un brin plié en V se lit alors rond sous l'éclairage, comme le tube qu'il est.
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

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));

	// Un brin : deux triangles pliés le long d'une nervure qui va du pied à la pointe. Sa largeur est tangente à la touffe,
	// et la nervure ressort vers l'extérieur, d'où un V ouvert qui se voit plein de face comme de profil.
	const blade = (azimuth, lean, offset, length, width) => {
		const outward = new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth));
		const side = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth)).multiplyScalar(width / 2);
		const foot = outward.clone().multiplyScalar(offset);
		const tip = foot.clone().addScaledVector(direction(azimuth, lean), length).toArray();
		const left = foot.clone().add(side).toArray(), right = foot.clone().sub(side).toArray();
		const ridge = foot.clone().addScaledVector(outward, width * .45).toArray();
		return smooth([[left, ridge, tip], [ridge, right, tip]]);
	};

	// Brins : douze, sur un angle d'or bousculé pour qu'aucun ne double son voisin. Les penchés croissent du cœur vers
	// le bord ; les plus ouverts, qui passent dehors, prennent la couleur sombre et donnent sa deuxième valeur à la touffe.
	// 12 brins × 2 + 3 hampes × 2 + 3 pompons × 10 = 60 triangles.
	const blades = { light: [], dark: [] };
	for(let i = 0; i < 12; i++) {
		const azimuth = i * 2.39996 + between(-.3, .3);
		const lean = .05 + (i % 4) * .12 + between(0, .06);
		const length = between(.25, .29) * (1 - lean * .2);
		blades[lean > .24 ? 'dark' : 'light'].push(blade(azimuth, lean, between(.008, .022), length, between(.017, .021)));
	}

	// Pompon : une bipyramide à cinq pans, dont les normales lissées rayonnent depuis le centre. Elle se lit comme une
	// boule de profil comme de dessus, là où un octaèdre se lit comme un losange. Un léger aplatissement en fait une ombelle.
	const pompon = (center, radius, phase) => {
		const ring = [0, 1, 2, 3, 4].map(j => {
			const a = phase + j * Math.PI * 2 / 5;
			return [center.x + Math.cos(a) * radius, center.y, center.z + Math.sin(a) * radius];
		});
		const top = [center.x, center.y + radius * .85, center.z], bottom = [center.x, center.y - radius * .85, center.z];
		return smooth(ring.flatMap((p, j) => {
			const q = ring[(j + 1) % 5];
			return [[p, top, q], [q, bottom, p]];
		}));
	};

	// Hampes et pompons : trois, à des hauteurs et des penchés différents, juste au-dessus du feuillage
	const stalks = [], blooms = [];
	for(const [azimuth, lean, length, radius] of [[.8, .16, .3, .025], [2.9, .24, .285, .023], [4.7, .07, .315, .026]]) {
		stalks.push(blade(azimuth, lean, .008, length, .008));
		const center = new THREE.Vector3(Math.cos(azimuth) * .008, 0, Math.sin(azimuth) * .008).addScaledVector(direction(azimuth, lean), length + radius * .5);
		blooms.push(pompon(center, radius, azimuth));
	}

	const parts = [
		{ color: LEAF, geometry: helpers.merge([...blades.light, ...stalks]) },
		{ color: DARK, geometry: helpers.merge(blades.dark) },
		{ color: BLOOM, sway: .6, geometry: helpers.merge(blooms) },
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
