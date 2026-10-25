// La bande fleurie : une prairie semée de 4 m sur 1,5, basse et souple, sur laquelle les fleurs des champs s'ouvrent en taches de
// couleur, coquelicot, bleuet et marguerite, comme on les voit dans un semis : par plaques d'une même fleur, quelques pieds d'une
// autre mêlés au bord. Les étoiles de couleur posées à plat sur l'herbe basse la séparent d'une haie ou d'un engrais vert.
export function build(THREE, helpers) {
	const SEED = 29;
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

	const grass = [];
	// Deux rangs de touffes décalés d'une demi-touffe : un tapis, et non une file de boules
	for(let i = 0; i < 10; i++) grass.push(lump(between(.4, .46), [1.35, .48, 1.15], [-1.62 + i * .36 + between(-.05, .05), .06, (i % 2 ? .24 : -.24) + between(-.05, .05)], 0, .1));
	// Les fleurs se posent sur l'herbe : un rayon tiré d'en haut donne la hauteur de la touffe sous chaque fleur
	const bed = new THREE.Mesh(merge(grass)), ray = new THREE.Raycaster(), down = new THREE.Vector3(0, -1, 0);
	const surface = (x, z) => {
		ray.set(new THREE.Vector3(x, 3, z), down);
		return ray.intersectObject(bed)[0]?.point.y ?? null;
	};
	// Une fleur : deux triangles croisés en étoile à six pointes, tournée vers le ciel, un peu penchée
	const star = (radius, x, y, z) => {
		const g = new THREE.BufferGeometry(), p = [];
		for(const shift of [0, Math.PI]) for(let k = 0; k < 3; k++) {
			const a = shift + k * Math.PI * 2 / 3;
			p.push(Math.cos(a) * radius, 0, -Math.sin(a) * radius);
		}
		g.setAttribute('position', new THREE.Float32BufferAttribute([...p.slice(0, 9), ...p.slice(9, 18)], 3));
		return g.rotateY(between(0, 2)).rotateX(between(-.15, .15)).rotateZ(between(-.15, .15)).translate(x, y + .025, z);
	};
	const colors = [P.rouge, P.bleu, P.blanc], flowers = { [P.rouge]: [], [P.bleu]: [], [P.blanc]: [] };
	const drifts = [[-1.5, 0], [-.6, 2], [.3, 1], [1.1, 0], [1.5, 2]];
	for(const [cx, kind] of drifts) {
		for(let i = 0; i < 9; i++) {
			const x = Math.min(1.75, cx + between(-.45, .45)), z = between(-.5, .5), y = surface(x, z);
			if(y === null) continue;
			const color = random() < .8 ? colors[kind] : colors[Math.floor(random() * 3)];
			flowers[color].push(star(color === P.blanc ? between(.15, .18) : between(.16, .2), x, y, z));
		}
	}
	paint(grass, '#86b65c');
	for(const color of colors) if(flowers[color].length > 0) paint(flowers[color], color);

	const { d, h } = measure(parts);
	return { d, h, aligned: true, parts };
}
