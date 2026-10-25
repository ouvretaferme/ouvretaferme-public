// Un tronçon de haie taillée de 3 m, que la scène répète le long d'une ligne dessinée : un cordon de 1,6 m de large et 2 m de
// haut, tiré selon +Z depuis z = 0, flancs penchés et épaules abattues, en deux verts selon l'orientation des faces.
// Les sections du milieu varient en largeur et en hauteur, celles des deux bouts sont fixes et symétriques : deux tronçons mis
// bout à bout, l'un retourné, se raccordent sans marche.
export function build(THREE, helpers) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const LENGTH = 3, SECTIONS = 6, SEED = 1;

	// Suite déterministe (mulberry32) : la cuisson rend toujours le même tronçon
	let state = (Math.imul(SEED, 2654435761) + 1013) >>> 0;
	const random = () => {
		state = (state + 0x6D2B79F5) >>> 0;
		let t = Math.imul(state ^ (state >>> 15), 1 | state);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const between = (a, b) => a + (b - a) * random();

	// Le profil à six points d'une section : pied, épaule, dessus, de part et d'autre de l'axe
	const profile = (w, top, shoulder, shift) => [[-w * .94, 0], [-w, shoulder], [-w * .5, top], [w * .5, top], [w, shoulder], [w * .94, 0]].map(([x, y]) => [x + shift, y]);
	const rings = [];
	for(let i = 0; i <= SECTIONS; i++) {
		const z = i * LENGTH / SECTIONS, end = i === 0 || i === SECTIONS;
		const top = end ? 1.9 : between(1.8, 2);
		const points = end ? profile(.76, top, 1.3, 0) : profile(between(.7, .8), top, top - between(.5, .7), between(-.05, .05));
		rings.push(points.map(([x, y]) => [x, y, z]));
	}
	const position = [];
	const quad = (a, b, c, d) => position.push(...a, ...c, ...b, ...a, ...d, ...c);
	for(let i = 0; i < SECTIONS; i++) {
		for(let j = 0; j < 5; j++) quad(rings[i][j], rings[i][j + 1], rings[i + 1][j + 1], rings[i + 1][j]);
	}
	// Les deux tranches, en éventail depuis le pied, pour qu'un bout de haie ne s'ouvre pas sur son vide
	for(const [ring, flip] of [[rings[0], true], [rings[SECTIONS], false]]) {
		for(let j = 1; j < 5; j++) {
			if(flip) position.push(...ring[0], ...ring[j + 1], ...ring[j]);
			else position.push(...ring[0], ...ring[j], ...ring[j + 1]);
		}
	}

	// Dessus clair, flancs et dessous sombres : les deux verts que la scène teinte d'un tronçon à l'autre
	const SUN = new THREE.Vector3(-.3, .9, .2).normalize();
	const light = [], shade = [], a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), n = new THREE.Vector3();
	for(let i = 0; i < position.length; i += 9) {
		a.fromArray(position, i); b.fromArray(position, i + 3); c.fromArray(position, i + 6);
		n.subVectors(b, a).cross(c.clone().sub(a)).normalize();
		(n.dot(SUN) < .5 ? shade : light).push(...position.slice(i, i + 9));
	}
	for(const [list, color] of [[light, P.feuille], [shade, P.feuilleSombre]]) {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
		add(facets(geometry), color);
	}
	return { ...measure(parts), aligned: false, parts };
}
