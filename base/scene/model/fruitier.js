// L'arbre fruitier : un arbre de verger conduit en gobelet. Un tronc court qui se partage à hauteur d'épaule en deux
// charpentières bien visibles, sous une couronne en parasol, large et aplatie dessous. C'est la fourche à nu sous le parasol qui
// le sépare du feuillu en boule (arbre.js), et les fruits de ses variantes (fruitier.rouge.js, fruitier.or.js) qui le séparent
// d'un arbre d'ornement. La couronne s'appelle « feuillage » et les fruits « fruit », pour que la scène les teinte à la saison
// et les retire l'hiver ; la charpente nue garde alors la largeur du parasol.
// Les variantes appellent build() avec leur fruit, parce que la cuisson n'en passe aucun. options : graine (un entier), taille
// (la couronne fait `taille` mètres de large), fruit (un nom de couleur de P, rien pour un arbre sans fruits).
export function build(THREE, helpers, options = {}) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	// La taille à l'échelle 1 du décor du prototype : la scène n'a plus qu'à en varier l'échelle
	const taille = options.taille ?? 3.4, fruit = options.fruit ?? null;

	let state = (Math.imul((options.graine ?? 1) | 0, 2654435761) + 7) >>> 0;
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

	// Un membre à pans sans fonds, d'un point à un autre. Trois pans suffisent à une charpentière, le tronc en a quatre
	const limb = (from, to, r0, r1, sides = 3, color = P.boisSombre) => {
		const a = new THREE.Vector3(...from), b = new THREE.Vector3(...to), length = a.distanceTo(b);
		const g = new THREE.CylinderGeometry(r1, r0, length, sides, 1, true).translate(0, length / 2, 0);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), b.clone().sub(a).normalize()));
		add(facets(g.translate(a.x, a.y, a.z)), color);
	};
	// Les rameaux tirent d'une suite à eux : la couronne et les fruits d'une graine restent ceux qu'ils étaient avant eux
	let woodState = (state ^ 0x9E3779B9) >>> 0;
	const wood = (a, b) => {
		woodState = (woodState + 0x6D2B79F5) >>> 0;
		let t = Math.imul(woodState ^ (woodState >>> 15), 1 | woodState);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return a + (b - a) * ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const TWIG = '#8a6444';
	const FORK = 1.15, CROWN = 2.5;
	limb([0, 0, 0], [between(-.08, .08), FORK, between(-.08, .08)], .26, .2, 4);
	// Deux charpentières opposées, d'inégale ouverture : elles entrent dans la couronne par le dessous
	const start = between(0, Math.PI * 2), forks = [];
	for(let i = 0; i < 2; i++) {
		const azimuth = start + (i + between(-.15, .15)) * Math.PI, reach = between(.75, 1.15);
		limb([0, FORK - .1, 0], [Math.cos(azimuth) * reach, CROWN + .1, Math.sin(azimuth) * reach], .15, .1);
		// Cinq rameaux en éventail au bout de chaque charpentière, jusqu'au bord du dôme : le gobelet reste ouvert au centre, et
		// l'arbre nu garde la largeur de son parasol
		for(const k of [-2, -1, 0, 1, 2]) {
			const a = azimuth + k * wood(.5, .7), out = wood(1.45, 1.7), up = CROWN + wood(.5, .9) - Math.abs(k) * .15;
			const from = [Math.cos(azimuth) * reach, CROWN + .1, Math.sin(azimuth) * reach], to = [Math.cos(a) * out, up, Math.sin(a) * out];
			limb(from, to, .12, .05, 3, TWIG);
			forks.push({ from: from.map((n, axis) => (n + to[axis]) / 2), a, out, up });
		}
	}
	// Deux brindilles par rameau, parties de son milieu et finies en deçà de lui, sous la couronne : sans elles l'arbre nu est un
	// éventail de dix baguettes. Tirées après tous les rameaux, pour que leur hasard ne déplace aucun d'eux
	for(const { from, a, out, up } of forks) {
		for(const side of [-1, 1]) {
			const turn = a + side * wood(.22, .4), far = out * wood(.82, .97);
			limb(from, [Math.cos(turn) * far, up + wood(-.25, .2), Math.sin(turn) * far], .06, .025, 3, TWIG);
		}
	}

	// La couronne : un icosaèdre aplati dessous et étalé, deux verts selon l'orientation des faces
	const crown = v => {
		const k = bump(v);
		v.x *= 2 * (1 + .15 * k);
		v.z *= 2 * (1 + .15 * k);
		v.y = CROWN + v.y * (v.y < 0 ? .35 : 1.25) * (1 + .15 * k);
		return v;
	};
	const solid = new THREE.IcosahedronGeometry(1, 1).rotateY(between(0, 6)), g = solid.index ? solid.toNonIndexed() : solid;
	const position = g.attributes.position, v = new THREE.Vector3();
	// Les faces du fond du parasol, tournées vers le sol, ne se voient d'aucune caméra placée au-dessus : elles ne sont pas gardées
	const hidden = i => [0, 1, 2].every(k => position.getY(i + k) < -.4);
	const kept = Array.from({ length: position.count / 3 }, (_, i) => i * 3).filter(i => !hidden(i));
	for(let i = 0; i < position.count; i++) {
		crown(v.fromBufferAttribute(position, i));
		position.setXYZ(i, v.x, v.y, v.z);
	}
	const SUN = new THREE.Vector3(-.3, .9, .2).normalize(), light = [], shade = [];
	const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), n = new THREE.Vector3();
	for(const i of kept) {
		a.fromBufferAttribute(position, i);
		b.fromBufferAttribute(position, i + 1);
		c.fromBufferAttribute(position, i + 2);
		n.subVectors(b, a).cross(c.clone().sub(a)).normalize();
		(n.dot(SUN) < .12 ? shade : light).push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z);
	}
	for(const [list, color] of [[light, P.feuille], [shade, P.feuilleSombre]]) {
		const part = new THREE.BufferGeometry();
		part.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
		add(facets(part), color, 'feuillage');
	}

	// Quatre fruits à demi pris dans le dôme, du côté du ciel et du bord : dessous, la caméra de la scène ne les verrait pas.
	// Un octaèdre se lit comme une boule à cette taille
	if(fruit !== null) {
		const turn = between(0, Math.PI * 2);
		for(let i = 0; i < 4; i++) {
			const azimuth = turn + (i + between(-.25, .25)) * Math.PI / 2, rise = between(.15, .75);
			crown(v.set(Math.cos(azimuth) * Math.cos(rise), Math.sin(rise), Math.sin(azimuth) * Math.cos(rise)));
			add(facets(new THREE.OctahedronGeometry(between(.2, .27)).rotateY(between(0, 1.5)).translate(v.x, v.y, v.z)), P[fruit], 'fruit');
		}
	}

	const scale = taille / 4.2;
	for(const part of parts) {
		part.geometry.scale(scale, scale, scale);
	}
	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts, anchors: { cime: [0, h, 0], pied: [0, 0, 0] } };
}
