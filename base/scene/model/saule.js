// Le saule têtard : un fût court, trapu et un peu penché, couronné à 2 m d'une tête noueuse qui le déborde comme un poing, faite
// de bourrelets de taille. D'elle jaillit une gerbe de rejets droits et minces, ouverte en V, que le feuillage gris vert couvre en
// une touffe dense, plus haute que large, dont les pointes nues dépassent. Le fût épais sous une touffe posée à même la tête le
// sépare du feuillu en boule (arbre.js), dont la boule est portée haut sur un tronc mince.
export function build(THREE, helpers) {
	const SEED = 3;
	const { P, facets, collector, measure, merge } = helpers;
	const { parts, add } = collector();

	// Suite déterministe (mulberry32) : la cuisson rend toujours le même objet
	let state = (Math.imul(SEED, 2654435761) + 1013) >>> 0;
	const random = () => {
		state = (state + 0x6D2B79F5) >>> 0;
		let t = Math.imul(state ^ (state >>> 15), 1 | state);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const between = (a, b) => a + (b - a) * random();
	// Un bruit lu sur le sommet arrondi : deux faces qui partagent un sommet le déplacent pareil, la masse reste fermée
	const noise = (v, salt) => {
		const r = n => Math.round(n * 500) / 500;
		const s = Math.sin(r(v.x) * 127.1 + r(v.y) * 311.7 + r(v.z) * 74.7 + salt) * 43758.5453;
		return (s - Math.floor(s)) * 2 - 1;
	};
	// Une masse bosselée : un icosaèdre (20 triangles, 80 en detail 1) étiré de (sx, sy, sz), centré en c, jamais sous le sol
	const lump = (radius, [sx, sy, sz], [cx, cy, cz], detail = 0, rough = .18) => {
		const solid = new THREE.IcosahedronGeometry(1, detail).rotateY(between(0, 6)), g = solid.index ? solid.toNonIndexed() : solid;
		const position = g.attributes.position, v = new THREE.Vector3(), salt = between(0, 50);
		for(let i = 0; i < position.count; i++) {
			v.fromBufferAttribute(position, i);
			const k = radius * (1 + rough * noise(v, salt));
			position.setXYZ(i, cx + v.x * k * sx, Math.max(0, cy + v.y * k * sy), cz + v.z * k * sz);
		}
		return g;
	};
	// Un membre de a à b, rayon r0 au départ et r1 au bout, tube ouvert à `sides` pans (2 × sides triangles)
	const limb = (a, b, r0, r1, sides = 4) => {
		const A = new THREE.Vector3(...a), dir = new THREE.Vector3(...b).sub(A), length = dir.length();
		const g = new THREE.CylinderGeometry(r1, r0, length, sides, 1, true).translate(0, length / 2, 0);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir.normalize()));
		return g.translate(A.x, A.y, A.z);
	};
	// Une lame mince se voit des deux côtés : le matériau du décor ne dessine qu'une face
	const twoFaced = g => {
		const source = g.index ? g.toNonIndexed() : g, a = source.attributes.position.array, back = [];
		for(let i = 0; i < a.length; i += 9) back.push(...a.slice(i, i + 3), ...a.slice(i + 6, i + 9), ...a.slice(i + 3, i + 6));
		const out = new THREE.BufferGeometry();
		out.setAttribute('position', new THREE.Float32BufferAttribute([...a, ...back], 3));
		return out;
	};
	// Range les faces en clair et sombre selon qu'elles regardent le soleil de la scène
	const SUN = new THREE.Vector3(-.3, .9, .2).normalize();
	const tone = (geometries, light, shade, name) => {
		const lists = [[], []], a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), n = new THREE.Vector3();
		for(const g of geometries) {
			const array = (g.index ? g.toNonIndexed() : g).attributes.position.array;
			for(let i = 0; i < array.length; i += 9) {
				a.fromArray(array, i);
				b.fromArray(array, i + 3);
				c.fromArray(array, i + 6);
				n.subVectors(b, a).cross(c.clone().sub(a)).normalize();
				lists[n.dot(SUN) < .2 ? 1 : 0].push(...array.slice(i, i + 9));
			}
		}
		lists.forEach((list, k) => {
			if(list.length === 0) return;
			const g = new THREE.BufferGeometry();
			g.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
			add(facets(g), k === 0 ? light : shade, name);
		});
	};
	const paint = (geometries, color, name) => add(facets(merge(geometries)), color, name);

	const trunk = [], shoots = [], leaves = [];
	// Fût ouvert : la tête couvre son haut et le sol son pied
	trunk.push(new THREE.CylinderGeometry(.5, .64, 1.85, 7, 1, true).translate(0, .92, 0).rotateZ(.06).translate(0, .04, 0));
	// La tête : trois bourrelets larges qui débordent du fût d'une demi-largeur
	for(let i = 0; i < 3; i++) {
		const a = i / 3 * Math.PI * 2 + between(-.3, .3);
		trunk.push(lump(between(.46, .54), [1.05, 1, 1.05], [.11 + Math.cos(a) * .36, between(1.9, 2.1), Math.sin(a) * .36], 0, .3));
	}
	const N = 11;
	for(let i = 0; i < N; i++) {
		const a = i / N * Math.PI * 2 + between(-.25, .25), lean = between(.2, .5), length = between(2.8, 3.4);
		const from = [.11 + Math.cos(a) * .35, 2.2, Math.sin(a) * .35];
		const dir = new THREE.Vector3(Math.cos(a) * lean, 1, Math.sin(a) * lean).normalize();
		shoots.push(limb(from, [from[0] + dir.x * length, from[1] + dir.y * length, from[2] + dir.z * length], .05, .02, 3));
	}
	// La touffe : une masse au cœur et six fuseaux autour, dressés et un peu ouverts, qui se recouvrent en une seule silhouette
	leaves.push(lump(.85, [1, 1.35, 1], [.11, 3.95, 0], 0, .2));
	for(let i = 0; i < 6; i++) {
		const a = i / 6 * Math.PI * 2 + between(-.25, .25), r = between(.85, 1.1);
		const plume = lump(between(.52, .6), [.85, 1.6, .85], [0, 0, 0], 0, .2).rotateZ(-.25).rotateY(-a);
		leaves.push(plume.translate(.11 + Math.cos(a) * r, between(3.6, 4.1), Math.sin(a) * r));
	}
	paint(trunk, P.boisSombre);
	paint(shoots, '#8a6a3c');
	tone(leaves, '#a9c290', '#6f8f60', 'feuillage');

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
