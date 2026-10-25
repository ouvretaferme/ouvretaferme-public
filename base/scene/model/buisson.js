// Le buisson : le pied de haie du prototype, un buisson taillé d'un seul bloc bosselé,
// aplati au pied, avec une épaule plus petite, en deux verts selon l'orientation des faces. 2 m d'emprise au plus, 1,5 m de haut.
// Les buissons du décor le reprennent à d'autres échelles et d'autres rotations, le cordon d'une haie tracée est un autre modèle.
export function build(THREE, helpers) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const SEED = 1;

	// Suite déterministe (mulberry32) : la cuisson rend toujours le même buisson
	let state = (Math.imul(SEED, 2654435761) + 1013) >>> 0;
	const random = () => {
		state = (state + 0x6D2B79F5) >>> 0;
		let t = Math.imul(state ^ (state >>> 15), 1 | state);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const between = (a, b) => a + (b - a) * random();
	const salt = between(0, 50);
	const bump = v => {
		const r = n => Math.round(n * 500) / 500;
		const s = Math.sin(r(v.x) * 127.1 + r(v.y) * 311.7 + r(v.z) * 74.7 + salt) * 43758.5453;
		return (s - Math.floor(s)) * 2 - 1;
	};
	const blob = (radius, cx, cy, cz, squash) => {
		const solid = new THREE.IcosahedronGeometry(1, 0).rotateY(between(0, 6)).rotateX(between(-.3, .3)), g = solid.index ? solid.toNonIndexed() : solid;
		const position = g.attributes.position, v = new THREE.Vector3(), out = [];
		for(let i = 0; i < position.count; i++) {
			v.fromBufferAttribute(position, i);
			v.multiplyScalar(radius * (1 + .14 * bump(v)));
			// Le dessous s'écrase : le buisson est assis sur le sol, pas posé dessus comme un ballon
			v.y = Math.max(cy + v.y * (v.y < 0 ? squash : 1), 0);
			out.push(v.x + cx, v.y, v.z + cz);
		}
		return out;
	};
	const azimuth = between(0, Math.PI * 2);
	const position = [
		...blob(.9, 0, .72, 0, .8),
		...blob(.5, Math.cos(azimuth) * .62, .45, Math.sin(azimuth) * .62, .9),
	];

	// Dessus clair, flancs et dessous sombres
	const SUN = new THREE.Vector3(-.3, .9, .2).normalize(), light = [], shade = [];
	const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), n = new THREE.Vector3();
	for(let i = 0; i < position.length; i += 9) {
		a.fromArray(position, i);
		b.fromArray(position, i + 3);
		c.fromArray(position, i + 6);
		n.subVectors(b, a).cross(c.clone().sub(a)).normalize();
		(n.dot(SUN) < .2 ? shade : light).push(...position.slice(i, i + 9));
	}
	for(const [list, color] of [[light, P.feuille], [shade, P.feuilleSombre]]) {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
		add(facets(g).scale(1, .9, 1), color);
	}

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
