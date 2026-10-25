// L'arbre du décor, celui du bourg de la scène comptable, ramené à l'emprise des arbres de la scène.
// Un tronc sombre et court sous un feuillage massif taillé à facettes, dont la silhouette seule dit l'essence : boule du feuillu
// ici, étages du sapin (arbre.sapin.js), fuseau du peuplier (arbre.peuplier.js). Le feuillage est un seul volume bosselé, partagé
// en deux verts d'après l'orientation de ses faces : la lumière dessus, l'ombre dessous. Il s'appelle « feuillage », pour que la
// scène le teinte à la saison et retire l'hiver celui des feuillus ; la charpente nue garde alors la largeur de la couronne.
// Les variantes appellent build() avec leur essence, parce que la cuisson n'en passe aucune. options : essence ('rond', 'sapin',
// 'peuplier'), graine (un entier, qui fait la forme), taille (la couronne d'un feuillu fait `taille` mètres de large, celle d'un
// sapin 0,64 × `taille`, celle d'un peuplier 0,36 × `taille`).
export function build(THREE, helpers, options = {}) {
	const { P, facets, collector, measure } = helpers;
	const { parts, add } = collector();
	const ESSENCES = ['rond', 'peuplier', 'sapin'];
	const essence = options.essence ?? 'rond';
	// La taille de chaque essence à l'échelle 1 du décor du prototype : la scène n'a plus qu'à en varier l'échelle
	const taille = options.taille ?? { rond: 3.6, sapin: 5, peuplier: 5.4 }[essence];

	// Suite déterministe (mulberry32) : la même graine rend le même arbre, et deux essences de même graine ne tirent pas les mêmes valeurs
	let state = (Math.imul((options.graine ?? 1) | 0, 2654435761) + ESSENCES.indexOf(essence) * 40503) >>> 0;
	const random = () => {
		state = (state + 0x6D2B79F5) >>> 0;
		let t = Math.imul(state ^ (state >>> 15), 1 | state);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	const between = (a, b) => a + (b - a) * random();
	const salt = between(0, 50);
	// La charpente tire d'une suite à elle : le feuillage d'une graine reste celui qu'il était avant qu'elle n'existe
	let woodState = (state ^ 0x9E3779B9) >>> 0;
	const wood = (a, b) => {
		woodState = (woodState + 0x6D2B79F5) >>> 0;
		let t = Math.imul(woodState ^ (woodState >>> 15), 1 | woodState);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return a + (b - a) * ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
	// Bosse de -1 à 1 tirée de la position : les sommets qu'un icosaèdre non indexé duplique reçoivent la même, d'où l'arrondi
	const bump = (v, shift = 0) => {
		const r = n => Math.round(n * 500) / 500;
		const s = Math.sin(r(v.x) * 127.1 + r(v.y) * 311.7 + r(v.z) * 74.7 + salt + shift) * 43758.5453;
		return (s - Math.floor(s)) * 2 - 1;
	};
	// Sculpte une géométrie de rayon 1 : place(direction, bosse) écrit le sommet dans le repère du modèle
	const sculpt = (geometry, place) => {
		const g = geometry.index ? geometry.toNonIndexed() : geometry;
		const position = g.attributes.position, v = new THREE.Vector3();
		for(let i = 0; i < position.count; i++) {
			v.fromBufferAttribute(position, i);
			place(v, bump(v), bump(v, 9.7));
			position.setXYZ(i, v.x, v.y, v.z);
		}
		return g;
	};
	// Les faces tournées vers le sol ou à l'opposé du soleil passent au vert sombre. Le seuil suit l'essence :
	// un fuseau n'a presque pas de dessous, son ombre doit monter sur le flanc
	const SUN = new THREE.Vector3(-.3, .9, .2).normalize();
	const foliage = (geometry, threshold = .12) => {
		const position = geometry.attributes.position, light = [], shade = [];
		const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), n = new THREE.Vector3();
		for(let i = 0; i < position.count; i += 3) {
			a.fromBufferAttribute(position, i);
			b.fromBufferAttribute(position, i + 1);
			c.fromBufferAttribute(position, i + 2);
			n.subVectors(b, a).cross(c.clone().sub(a)).normalize();
			const list = n.dot(SUN) < threshold ? shade : light;
			list.push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z);
		}
		for(const [list, color] of [[light, P.feuille], [shade, P.feuilleSombre]]) {
			if(list.length === 0) {
				continue;
			}
			const g = new THREE.BufferGeometry();
			g.setAttribute('position', new THREE.Float32BufferAttribute(list, 3));
			add(facets(g), color, 'feuillage');
		}
	};
	// Tronc à six pans, sans fonds (le pied est au sol, la tête dans le feuillage), penché vers (leanX, leanZ) à son sommet
	const leanX = between(-.22, .22), leanZ = between(-.22, .22);
	const trunk = (height, rBottom, rTop, sides = 6) => {
		const g = new THREE.CylinderGeometry(rTop, rBottom, height, sides, 1, true).translate(0, height / 2, 0).toNonIndexed();
		const position = g.attributes.position;
		for(let i = 0; i < position.count; i++) {
			const k = position.getY(i) / height;
			position.setX(i, position.getX(i) + leanX * k);
			position.setZ(i, position.getZ(i) + leanZ * k);
		}
		add(facets(g.rotateY(between(0, 1))), P.boisSombre);
	};
	// Une branche à trois pans sans fonds, d'un point à un autre. Elle porte l'arbre une fois ses feuilles tombées : sans elle il ne
	// reste qu'un pieu. Le rameau est d'un bois plus clair, qui détache le bout de la silhouette sur le pré
	const limb = (from, to, r0, r1, color = P.boisSombre) => {
		const a = new THREE.Vector3(...from), b = new THREE.Vector3(...to), length = a.distanceTo(b);
		const g = new THREE.CylinderGeometry(r1, r0, length, 3, 1, true).translate(0, length / 2, 0);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), b.clone().sub(a).normalize()));
		add(facets(g.translate(a.x, a.y, a.z).toNonIndexed()), color);
	};
	const TWIG = '#8a6444';
	// Quatre rameaux en éventail au bout d'une branche maitresse : ils s'écartent de sa direction et finissent à `reach` du point
	// `center`, en deçà du feuillage, qui les couvre l'été. Un éventail sans brindilles : le bout de la charpente nue reste touffu
	// pour deux fois moins de triangles, et un bosquet en pose jusqu'à huit
	const twigs = (from, to, center, reach) => {
		const along = new THREE.Vector3(...to).sub(new THREE.Vector3(...from)).normalize();
		const side = new THREE.Vector3(along.z, 0, -along.x).normalize();
		for(const turn of [-1, -.35, .35, 1]) {
			const out = new THREE.Vector3(...to).sub(new THREE.Vector3(...center)).normalize().addScaledVector(side, turn * wood(.45, .9)).add(new THREE.Vector3(0, wood(-.1, .5), 0)).normalize();
			limb(to, [center[0] + out.x * reach[0], center[1] + out.y * reach[1], center[2] + out.z * reach[0]], .12, .05, TWIG);
		}
	};
	// Point de l'axe du tronc à la hauteur y, pour y poser le feuillage
	const axis = (y, height) => [leanX * y / height, y, leanZ * y / height];
	const turned = g => g.rotateY(between(0, Math.PI * 2)).rotateX(between(-.4, .4));

	// Largeur de couronne à laquelle l'essence est dessinée ci-dessous, et celle que `taille` lui demande
	let drawn = 4.2, wanted = taille;
	if(essence === 'rond') {
		// Boule de 4 m sur un tronc de 2,2 m, plus une bosse sur l'épaule : sans elle la boule se lit comme une sucette
		const [cx, cy, cz] = axis(4, 3.2), R = 2;
		trunk(3.2, .4, .25);
		const ball = (v, k) => {
			v.multiplyScalar(R * (1 + .17 * k));
			if(v.y < 0) {
				v.y *= .86;
			}
			v.x += cx; v.y += cy; v.z += cz;
		};
		foliage(sculpt(turned(new THREE.IcosahedronGeometry(1, 1)), ball));
		const azimuth = between(0, Math.PI * 2), rise = between(.15, .6), r = between(.95, 1.2);
		const lobe = (v, k) => {
			v.multiplyScalar(r * (1 + .12 * k));
			v.x += cx + Math.cos(azimuth) * R * .82 * Math.cos(rise);
			v.y += cy + R * .82 * Math.sin(rise);
			v.z += cz + Math.sin(azimuth) * R * .82 * Math.cos(rise);
		};
		foliage(sculpt(turned(new THREE.IcosahedronGeometry(1, 0)), lobe));
		// Cinq branches maitresses en couronne et une flèche, parties du haut du tronc. La plus proche de la bosse y va : l'arbre nu
		// garde l'épaule de sa frondaison. Chacune se coude à mi-rayon et finit au bord de la boule, un rameau part du coude et
		// quatre du bout : nu, l'arbre garde la largeur de sa frondaison d'été
		const center = [cx, cy, cz], top = axis(3.2, 3.2), turn = wood(0, Math.PI * 2);
		const mains = Array.from({ length: 5 }, (_, i) => [turn + (i + wood(-.3, .3)) * Math.PI * .4, wood(-.1, .5)]);
		const nearest = mains.reduce((best, main) => Math.cos(main[0] - azimuth) > Math.cos(best[0] - azimuth) ? main : best);
		nearest[0] = azimuth;
		nearest[1] = rise;
		const around = (a, up, reach) => [cx + Math.cos(a) * Math.cos(up) * reach, cy + Math.sin(up) * reach, cz + Math.sin(a) * Math.cos(up) * reach];
		for(const [a, up] of [...mains, [turn, 1.45]]) {
			const from = up > 1 ? top : axis(wood(2.5, 3.1), 3.2), elbow = around(a, up, R * wood(.45, .55)), tip = around(a + wood(-.25, .25), up + wood(.05, .25), R * wood(.84, .92));
			limb(from, elbow, .22, .14);
			limb(elbow, tip, .14, .07);
			limb(elbow, around(a + (wood(0, 1) < .5 ? -1 : 1) * wood(.5, .8), up + wood(.1, .4), R * wood(.8, .9)), .1, .05, TWIG);
			twigs(elbow, tip, center, [R * .98, R * .98]);
		}
	}
	else if(essence === 'peuplier') {
		// Fuseau de 2 m de large, ventre au tiers bas et pointe effilée : la hauteur est le trait, le tronc dépasse à peine
		trunk(1.6, .26, .2);
		const [cx, , cz] = axis(.9, 1.6), sway = between(-.25, .25), swayZ = between(-.25, .25);
		const spindle = (v, k, k2) => {
			const t = (v.y + 1) / 2;
			const width = (1.12 - .62 * t * t) * (1 + .2 * k);
			v.x = v.x * width + cx + sway * t * t;
			v.z = v.z * width + cz + swayZ * t * t;
			v.y = .9 + t * 6.1 + .18 * k2 * (1 - t);
		};
		foliage(sculpt(turned(new THREE.IcosahedronGeometry(1, 1)), spindle), .3);
		// Une flèche qui suit la courbure du fuseau, et huit branches dressées contre elle jusqu'au bord du fuseau, de plus en plus
		// courtes vers la cime. Un rameau part du milieu de chacune, de l'autre côté
		const spine = t => [cx + sway * t * t, .9 + t * 6.1, cz + swayZ * t * t];
		limb(axis(1.6, 1.6), spine(.45), .2, .11);
		limb(spine(.45), spine(.97), .11, .05, TWIG);
		const turn = wood(0, Math.PI * 2);
		for(let i = 0; i < 8; i++) {
			const t = .08 + i * .1, a = turn + i * 2.4 + wood(-.4, .4), from = spine(t), out = (1.12 - .62 * t * t) * wood(.82, .95), rise = wood(1.2, 1.7);
			const tip = [from[0] + Math.cos(a) * out, from[1] + rise, from[2] + Math.sin(a) * out];
			limb(from, tip, .1, .05);
			const middle = from.map((n, k) => (n + tip[k]) / 2), fork = a + wood(.9, 1.4);
			limb(middle, [middle[0] + Math.cos(fork) * out * .6, middle[1] + rise * .6, middle[2] + Math.sin(fork) * out * .6], .06, .03, TWIG);
		}
		drawn = 2.3;
		wanted = taille * .36;
	}
	else {
		// Trois jupes coniques qui se recouvrent. Le bord de chaque jupe est dentelé et son dessous sombre : c'est le cran
		// entre deux étages qui fait le sapin, un cône lisse serait un cyprès. Deux fois plus haut que large
		trunk(2, .32, .24);
		const foot = axis(1.3, 2);
		const tiers = [[1.3, 2, 2.5], [3.1, 1.55, 2.4], [4.8, 1.1, 2.9]];
		for(const [y0, r, h] of tiers) {
			const ox = foot[0] + between(-.08, .08), oz = foot[2] + between(-.08, .08);
			const skirt = (v, k, k2) => {
				// Seuls les sommets du bord bougent : la pointe et le centre du dessous restent sur l'axe
				if(v.y < 0 && Math.hypot(v.x, v.z) > .5) {
					const reach = 1 + .15 * k;
					v.x *= reach;
					v.z *= reach;
					v.y += .12 * k2;
				}
				v.x = v.x * r + ox;
				v.z = v.z * r + oz;
				v.y = y0 + (v.y + .5) * h;
			};
			// Un cône n'a pas de face tournée vers le sol sur son flanc : le seuil monte pour que le côté opposé au soleil s'assombrisse
			foliage(sculpt(new THREE.ConeGeometry(1, 1, 8).rotateY(between(0, 1)), skirt), .47);
		}
		drawn = 4.3;
		wanted = taille * .64;
	}

	const scale = wanted / drawn;
	for(const part of parts) {
		part.geometry.scale(scale, scale, scale);
	}
	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts, anchors: { cime: [0, h, 0] } };
}
