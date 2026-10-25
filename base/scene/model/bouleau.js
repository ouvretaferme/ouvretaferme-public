// Le bouleau : un tronc fin et blanc taché de noir, visible jusqu'aux deux tiers, sous une couronne légère et irrégulière d'un vert
// clair, faite de touffes de tailles différentes qui pendent un peu. L'écorce blanche le sépare de tout autre arbre du décor ; la
// couronne lâche et claire, du peuplier dont le fuseau est plein.
export function build(THREE, helpers) {
	const SEED = 5;
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

	const bark = [], marks = [], leaves = [];
	bark.push(new THREE.CylinderGeometry(.07, .15, 5, 6).translate(0, 2.5, 0));
	for(const [a, y, l] of [[0, 3.2, 1.6], [2.2, 3.6, 1.5], [4.1, 3.9, 1.3]]) bark.push(limb([0, y, 0], [Math.cos(a) * .8, y + l, Math.sin(a) * .8], .05, .025, 3));
	for(const [y, a] of [[.5, 0], [1.1, 2], [1.7, 4], [2.3, 1], [2.9, 3.2]]) {
		marks.push(new THREE.BoxGeometry(.06, .07, .14).translate(.14 - y * .015, y, 0).rotateY(a));
	}
	const crown = [[0, 5, 0, .85], [.75, 4.5, .3, .62], [-.7, 4.6, -.35, .7], [.2, 5.7, -.2, .5], [-.35, 3.9, .55, .48], [.5, 3.8, -.55, .42], [-.15, 4.3, -.05, .55]];
	for(const [x, y, z, r] of crown) leaves.push(lump(r, [1, 1.3, 1], [x, y, z], 0, .28));
	paint(bark, P.blanc);
	paint(marks, P.noir);
	tone(leaves, '#a8cf6a', '#6f9a45', 'feuillage');

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
