// Les fruits mûrs d'une culture, posés à la récolte : une forme et une couleur par espèce, un pool d'instances par forme.
// Module ES sans import : three, les pièces de géométrie (geometry.js) et la règle des ligneux (species.js) sont passés
// en argument. Rien ici ne crée de scène, ne lit l'heure ni ne touche au document : les fruits se posent sur un parent
// fourni, dans son repère, et le hasard est une fonction de l'appelant, random(graine) rendant un tirage de 0 à 1.
// Les tampons de pose, les tirages gardés et la texture du melon vivent dans l'instance que rend la fabrique : deux scènes
// d'une même page ne partagent ni une pose en cours ni un générateur en pause.

export function sceneCreateFruit(THREE, geometry, woody) {

	// Une forme = une géométrie à couleurs de sommet, de taille 1 m, centrée, axe long vertical. La couleur d'instance la
	// multiplie : les formes blanches prennent la couleur de l'espèce, celles qui portent deux couleurs (pastèque rayée,
	// aubergine et son calice) sont dessinées une fois pour toutes.
	// smooth : normales arrondies, pour les fruits ronds.
	// vertex : la couleur se lit à chaque sommet et s'interpole sur la face. Un motif peint face par face suit les diagonales
	// des quadrilatères et crante en dents de scie ; peint aux sommets, son bord devient un dégradé. paint(sommet, face,
	// centre de la face) rend la même couleur pour deux sommets confondus, sans quoi la couture se verrait
	const painted = (shape, paint, smooth = false, vertex = false) => {
		const flat = smooth ? geometry.smooth(geometry.flat(shape)) : geometry.facets(geometry.flat(shape));
		const position = flat.attributes.position, colors = [];
		const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), center = new THREE.Vector3(), color = new THREE.Color();
		for(let i = 0; i < position.count; i += 3) {
			a.fromBufferAttribute(position, i);
			b.fromBufferAttribute(position, i + 1);
			c.fromBufferAttribute(position, i + 2);
			center.copy(a).add(b).add(c).divideScalar(3);
			for(const point of [a, b, c]) {
				color.set(paint(vertex ? point : center, i / 3, center));
				colors.push(color.r, color.g, color.b);
			}
		}
		flat.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
		return flat;
	};
	const white = () => '#ffffff';

	// Un profil qui touche l'axe replie en segments la moitié des triangles de sa première et de sa dernière tranche : sans
	// surface, ils ne dessinent rien et se compteraient quand même, sur chaque fruit de la scène. Le tour les laisse de côté
	const lathe = (profile, sides) => {
		const position = new THREE.LatheGeometry(profile.map(([r, y]) => new THREE.Vector2(r, y)), sides).toNonIndexed().attributes.position;
		const kept = [], a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3();
		for(let i = 0; i < position.count; i += 3) {
			a.fromBufferAttribute(position, i);
			b.fromBufferAttribute(position, i + 1);
			c.fromBufferAttribute(position, i + 2);
			if(c.clone().sub(b).cross(a.clone().sub(b)).lengthSq() > 1e-16) {
				kept.push(...a.toArray(), ...b.toArray(), ...c.toArray());
			}
		}
		const turned = new THREE.BufferGeometry();
		turned.setAttribute('position', new THREE.Float32BufferAttribute(kept, 3));
		return turned;
	};

	// Profil de boule de rayon 0,5, d'un pôle à l'autre, en `rings` tranches
	const globe = rings => Array.from({ length: rings + 1 }, (_, i) => [.5 * Math.sin(Math.PI * i / rings), -.5 * Math.cos(Math.PI * i / rings)]);

	// Réseau du melon : des mailles claires sur un fond vert-beige, cellules de 4 × 4 points en quinconce, peu décalés. Un
	// pixel est sur le réseau quand il est presque aussi proche de deux points, ou du bord : chaque face de melonSphere porte
	// la texture entière, et le demi-trait de bord de deux faces voisines fait un trait plein sur l'arête, qui cache le
	// raccord des mailles.
	// La texture se calcule dans un tableau d'octets, sans canvas : le module ne dépend alors d'aucun document
	let melonTexture = null;
	const melonNet = () => {
		if(melonTexture !== null) {
			return melonTexture;
		}
		const SIZE = 128, COLUMNS = 4, ROWS = 4, LINE = 5;
		const draw = i => {
			const x = Math.sin(i * 12.9898 + 7.3) * 43758.5453;
			return x - Math.floor(x);
		};
		const seeds = Array.from({ length: COLUMNS * ROWS }, (_, i) => {
			const column = i % COLUMNS, row = Math.floor(i / COLUMNS);
			return [(column + .5 + (row % 2 ? .25 : -.25) + (draw(i) - .5) * .3) * SIZE / COLUMNS, (row + .5 + (draw(i + 50) - .5) * .3) * SIZE / ROWS];
		});
		// Un tableau borné arrondit au plus proche, là où un Uint8Array tronque : les octets sont ceux qu'un canvas recevrait
		const pixels = new Uint8ClampedArray(SIZE * SIZE * 4);
		const skin = new THREE.Color('#a3b272'), net = new THREE.Color('#ffffff'), color = new THREE.Color();
		for(let y = 0; y < SIZE; y++) {
			for(let x = 0; x < SIZE; x++) {
				let first = Infinity, second = Infinity;
				for(const [sx, sy] of seeds) {
					const distance = Math.hypot(x + .5 - sx, y + .5 - sy);
					if(distance < first) {
						second = first;
						first = distance;
					} else if(distance < second) {
						second = distance;
					}
				}
				const border = 2 * Math.min(x + .5, SIZE - x - .5, y + .5, SIZE - y - .5);
				color.copy(skin).lerp(net, 1 - THREE.MathUtils.smoothstep(Math.min(second - first, border), LINE * .4, LINE));
				// Une texture de données se lit de bas en haut, une image de haut en bas : la ligne y s'écrit à l'envers pour
				// que le réseau tombe sur les faces comme une image le ferait
				const at = ((SIZE - 1 - y) * SIZE + x) * 4;
				pixels[at] = color.r * 255;
				pixels[at + 1] = color.g * 255;
				pixels[at + 2] = color.b * 255;
				pixels[at + 3] = 255;
			}
		}
		melonTexture = new THREE.DataTexture(new Uint8Array(pixels.buffer), SIZE, SIZE, THREE.RGBAFormat);
		melonTexture.colorSpace = THREE.SRGBColorSpace;
		// Une texture de données nait sans filtrage ni niveaux réduits : vu de loin, le réseau scintillerait
		melonTexture.magFilter = THREE.LinearFilter;
		melonTexture.minFilter = THREE.LinearMipmapLinearFilter;
		melonTexture.generateMipmaps = true;
		melonTexture.needsUpdate = true;
		return melonTexture;
	};

	// Boule de rayon 0,5 faite d'un cube dont chaque face est un quadrillage de n × n, gonflé en sphère (12 n² triangles),
	// chaque face portant la texture entière. Un tour avec ses deux pôles resserre toutes les mailles en étoile au pôle, que le
	// melon couché montre de face ; une texture projetée triangle par triangle crante aux raccords. Ici chaque raccord suit une
	// arête du cube, sous un trait du réseau. La tangente répartit les sommets à angles égaux plutôt qu'à pas égaux sur la face plate
	const melonSphere = n => {
		const axes = [[0, 1, 2], [0, 2, 1], [1, 2, 0]];
		// La texture tourne d'un quart de tour d'une face à l'autre, pour que les six faces ne se lisent pas comme un même carreau
		const turned = (u, v, quarter) => [[u, v], [v, 1 - u], [1 - u, 1 - v], [1 - v, u]][quarter % 4];
		const positions = [], uvs = [], vertex = new THREE.Vector3(), normal = new THREE.Vector3(), edge1 = new THREE.Vector3(), edge2 = new THREE.Vector3();
		const corner = (axis, sign, i, j) => {
			const [u, v, w] = axes[axis], point = [0, 0, 0];
			point[u] = Math.tan((i / n * 2 - 1) * Math.PI / 4);
			point[v] = Math.tan((j / n * 2 - 1) * Math.PI / 4);
			point[w] = sign;
			return vertex.fromArray(point).normalize().multiplyScalar(.5).toArray();
		};
		axes.forEach((_, axis) => [1, -1].forEach(sign => {
			for(let i = 0; i < n; i++) {
				for(let j = 0; j < n; j++) {
					const quad = [[i, j], [i + 1, j], [i + 1, j + 1], [i, j + 1]];
					const points = quad.map(([a, b]) => corner(axis, sign, a, b));
					const coordinates = quad.map(([a, b]) => turned(a / n, b / n, axis * 2 + (sign > 0 ? 0 : 1)));
					// Triangles tournés vers l'extérieur : l'ordre du quadrillage s'inverse d'une face à son opposée
					edge1.fromArray(points[1]).sub(vertex.fromArray(points[0]));
					edge2.fromArray(points[2]).sub(vertex.fromArray(points[0]));
					const outward = normal.crossVectors(edge1, edge2).dot(vertex.fromArray(points[0])) > 0;
					const order = outward ? [0, 1, 2, 0, 2, 3] : [0, 2, 1, 0, 3, 2];
					for(const index of order) {
						positions.push(...points[index]);
						uvs.push(...coordinates[index]);
					}
				}
			}
		}));
		const cube = new THREE.BufferGeometry();
		cube.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		const sphere = painted(cube, white, true);
		sphere.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
		sphere.userData.map = melonNet();
		return sphere;
	};

	const SHAPES = {
		// Les fruits ronds sont des tours à normales lisses : un icosaèdre gardait ses facettes en gros plan
		// Tomate : 8 pans et 5 tranches, 64 triangles
		boule: () => painted(lathe(globe(5), 8), white, true),
		// Courge : 12 pans creusés en six côtes
		courge: () => {
			const g = lathe(globe(6), 12);
			const position = g.attributes.position;
			for(let i = 0; i < position.count; i++) {
				const x = position.getX(i), z = position.getZ(i);
				const rib = .92 + .08 * Math.cos(6 * Math.atan2(z, x));
				position.setXYZ(i, x * rib, position.getY(i), z * rib);
			}
			return painted(g, white, true);
		},
		// Peau blanche que la couleur d'instance teinte, et réseau porté par une texture (melonNet) : peint par sommet, un trait ne
		// peut pas être plus fin que l'écart entre deux sommets, et le tour de la pastèque (336 triangles) le brisait en taches.
		// 192 triangles : le réseau est dans la texture, la maille ne porte que le galbe
		melon: () => melonSphere(4),
		// Bandes sombres sur fond clair, chacune de largeur et de place tirées d'une graine fixe, aux bords ondulés d'un pôle à l'autre :
		// des pans réguliers faisaient un ballon de plage. Peintes par sommet, leurs bords se fondent au lieu de suivre les pans du tour
		pasteque: () => {
			const draw = i => { const x = Math.sin(i * 12.9898 + 4.1) * 43758.5453; return x - Math.floor(x); };
			const STRIPES = 9;
			const stripes = Array.from({ length: STRIPES }, (_, i) => ({
				center: (i + (draw(i) - .5) * .5) / STRIPES,
				half: (.22 + draw(i + 20) * .2) / STRIPES,
				wave: .25 / STRIPES, phase: draw(i + 40) * 6.28, folds: 2 + Math.floor(draw(i + 60) * 2),
			}));
			const light = new THREE.Color('#8fbf6a'), dark = new THREE.Color('#2f6b2f'), color = new THREE.Color();
			const paint = p => {
				const turn = (Math.atan2(p.x, p.z) / (Math.PI * 2) + 1) % 1, along = p.y + .5;
				const inside = stripes.some(({ center, half, wave, phase, folds }) => {
					const offset = Math.abs(((turn - center - wave * Math.sin(along * Math.PI * folds + phase)) % 1 + 1.5) % 1 - .5);
					return offset < half * (.75 + .25 * Math.sin(along * Math.PI));
				});
				return color.copy(inside ? dark : light);
			};
			return painted(lathe(globe(8), 24), paint, true, true);
		},
		// Poivron à quatre lobes : seize côtés de rayon alterné 0,5 et 0,4, le fond creusé
		cloche: () => {
			const g = lathe([[0, -.36], [.42, -.5], [.5, .15], [.34, .5], [0, .5]], 16);
			const position = g.attributes.position;
			for(let i = 0; i < position.count; i++) {
				const x = position.getX(i), z = position.getZ(i);
				const lobe = .9 + .1 * Math.cos(4 * Math.atan2(z, x));
				position.setXYZ(i, x * lobe, position.getY(i), z * lobe);
			}
			return painted(g, white, true);
		},
		// Peinte dans la forme et non par la couleur d'instance, qui ne fait que foncer : le reflet clair doit dépasser le violet
		aubergine: () => painted(geometry.merge([
			lathe([[0, -.5], [.32, -.38], [.46, -.05], [.36, .3], [.18, .42], [0, .44]], 6),
			geometry.cone(.24, .16, 5).translate(0, .46, 0),
		]), (p, face, center) => {
			// Le calice se décide à la face, pour que sa base ne prenne pas le violet du fruit ; le reflet aux sommets, en dégradé
			if(center.y > .4) return '#4f7a2e';
			return p.x > .2 && p.z > -.15 && p.y > -.3 && p.y < .25 ? '#b07ce0' : '#6a2a9a';
		}, false, true),
		cylindre: () => painted(lathe([[0, -.5], [.4, -.44], [.5, -.3], [.5, .3], [.4, .44], [0, .5]], 5), white),
		gousse: () => painted(geometry.ico(.5, 0), white),
		// Gousse fine, pointue aux deux bouts : 8 triangles, pour pendre par bouquets sans peser plus que les gousses rondes qu'elle remplace
		cosse: () => painted(new THREE.OctahedronGeometry(.5, 0), white),
		// Tubercule pointu aux deux bouts (patate douce) : ronds et roses, ils se lisaient en fraises
		fuseau: () => painted(lathe([[0, -.5], [.22, -.36], [.42, -.12], [.5, .06], [.4, .28], [.2, .44], [0, .5]], 5), white),
		poire: () => painted(lathe([[0, -.5], [.34, -.42], [.5, -.18], [.42, .08], [.2, .3], [.13, .46], [0, .5]], 5), white),
		// Pédoncule de cerise à trois pans, douze triangles
		pedoncule: () => painted(lathe([[0, -.5], [.5, -.5], [.5, .5], [0, .5]], 3), white),
		cone: () => painted(geometry.cone(.5, 1, 5).rotateX(Math.PI), white),
	};

	// Placement dans le repère du plant : x et z en fraction de l'emprise d, y en fraction de la hauteur h, autour du pied.
	// size en mètres, grossissement de la scène compris, tilt penche l'axe long, yaw le tourne autour du plant.
	const around = (count, rnd, make) => Array.from({ length: count }, (_, i) => make((i + rnd() * .5) * Math.PI * 2 / count, i));
	const at = (angle, radius) => ({ x: Math.cos(angle) * radius, z: Math.sin(angle) * radius });
	// Gousses : place(rnd, i) rend le point d'attache du plant (rames en A, filet, tiges), la gousse pend dessous ou se dresse le long de la tige
	// Un bouquet (bunch : le plus petit nombre de gousses, puis ce qui s'y ajoute au hasard) part d'un seul point d'attache et s'ouvre en éventail
	// de ±fan radians. depth aplatit la section : une gousse est plus large qu'épaisse
	const pod = (color, length, width, place, { up = false, fewest = 5, spread = 3, bunch = [1, 0], fan = .25, depth = 1, shape = 'gousse' } = {}) => rnd => Array.from({ length: fewest + Math.floor(rnd() * spread) }, (_, i) => {
		const attach = place(rnd, i), yaw = rnd() * 6.28, count = bunch[0] + Math.floor(rnd() * (bunch[1] + 1));
		return Array.from({ length: count }, (_, k) => ({
			shape, color, ...attach, size: [width, length, width * depth],
			tilt: up ? .15 : count > 1 ? (k / (count - 1) - .5) * 2 * fan + (rnd() - .5) * .08 : (rnd() - .5) * .3,
			yaw: count > 1 ? yaw + (rnd() - .5) * .5 : yaw,
			drop: up ? -length * .5 : length * .5,
		}));
	}).flat();
	// Haricot : les deux rames partent des bords de l'emprise et se croisent vers 83 % de la hauteur, dans le plan du filet (x)
	const onPoles = (rnd, i) => { const y = .2 + ((i * .37 + rnd() * .3) % 1) * .5, side = i % 2 ? 1 : -1; return { x: side * .45 * (1 - y / .83), y, z: (rnd() - .5) * .1 }; };
	const onNet = (rnd, i) => ({ x: ((i * .41 + rnd() * .2) % 1 - .5) * .8, y: .3 + rnd() * .45, z: (i % 2 ? 1 : -1) * .07 });
	const onStems = (rnd, i) => ({ ...at(i * 2.4 + rnd(), .24), y: .35 + rnd() * .35 });
	// Fruits des arbres posés en surface de frondaison, sur le dessus et les épaules : vus de haut, un fruit pendu sous le houppier
	// ne se voit pas. Chaque point est relevé sur le houppier cuit de l'espèce, [x, y, z] en fraction de l'emprise et de la hauteur ;
	// le fruit est poussé un peu au-dehors pour sortir à demi des feuilles
	// Un arbre porte des fruits d'une seule couleur : elle se tire une fois par arbre, un arbre sur quatre prend la seconde
	const onCrown = (spots, { shape, colors, size, share, bunch = 1 }) => rnd => {
		const color = colors[rnd() < .25 ? 1 : 0];
		return spots.filter(() => rnd() < share).flatMap(([x, y, z]) => Array.from({ length: bunch }, (_, k) => ({
			shape, color, x: x * 1.07 + k * .035, y: y + .01 - k * .012, z: z * 1.07 + k * .02, size, tilt: (rnd() - .5) * .4, yaw: rnd() * 6.28,
		})));
	};

	// Pas de maïs : ses épis sont dans le plant. Les fruits sont grossis de 1,5 à 1,8 sur la taille réelle et sortis du feuillage :
	// à 20 px/m, c'est le premier signe vu sur chaque planche
	const registry = {
		// Deux grappes de trois ou quatre fruits, une par bande laissée libre entre les étages de feuilles (tomate.js), sorties du feuillage.
		// Les fruits s'étagent autour du milieu de la bande pour ne pas mordre sur les feuilles du dessus
		// Lanternes : un calice de papier qui s'effile vers le bas (le fuseau tourné sur cinq côtés, cinq côtes, allongé), pendu par un court
		// pédoncule au bout des rameaux de physalis.js, car un juge ne reconnait le physalis qu'à elles. Orange paille, une sur quatre vert tendre.
		// Les bouts de rameau sont ceux de `laterals` dans physalis.js, [azimut en degrés, rayon et hauteur en mètres du modèle], ramenés à
		// l'emprise et à la hauteur que rend crop.js (boite du modèle de 1,352 m d'emprise et 1,892 m de haut) : ils suivent ce fichier.
		physalis: (() => {
			const tips = [[31, .7, .42], [62, .69, .39], [118, .71, .44], [149, .7, .4], [212, .71, .38], [239, .69, .43], [302, .7, .4], [328, .71, .44]]
				.map(([degrees, radius, y]) => ({ angle: degrees * Math.PI / 180, radius: radius / 1.352, y: (y + .003) / 1.892 }));
			const STALK = .035, WIDTH = .1, LENGTH = .14;
			return rnd => {
				const keep = 5 + Math.floor(rnd() * 4), skip = Math.floor(rnd() * 8);
				return tips.filter((_, i) => (i - skip + 8) % 8 < keep).flatMap(tip => {
					const color = rnd() < .25 ? '#b5cf7a' : '#e8b46a';
					const base = { ...at(tip.angle, tip.radius), y: tip.y, yaw: rnd() * 6.28 };
					return [
						{ ...base, shape: 'fuseau', color, size: [WIDTH, LENGTH, WIDTH], drop: STALK + LENGTH / 2 },
						{ ...base, shape: 'cylindre', color: '#6b7a3e', size: [.012, STALK, .012], drop: STALK / 2 },
					];
				});
			};
		})(),
		tomate: rnd => [.35, .6].flatMap((y, cluster) => {
			const angle = cluster * 2.6 + rnd(), count = 3 + Math.floor(rnd() * 2), size = .2 + rnd() * .02;
			return Array.from({ length: count }, (_, k) => ({ shape: 'boule', color: '#d8262b', ...at(angle + (k - (count - 1) / 2) * .5, .2), y: y + (k - (count - 1) / 2) * .035, size: [size, size * .94, size] }));
		}),
		// Une couronne rouge autour de la touffe
		fraise: rnd => around(5, rnd, angle => ({ shape: 'cone', color: '#e3242b', ...at(angle, .44), y: .3, size: [.13, .17, .13], tilt: (rnd() - .5) * .4 })),
		// Les baies sont des `gousse` de 20 triangles et non des `boule` de 64 : à 20 px/m une baie fait un ou deux pixels, et une grappe
		// de boules passerait le plafond de 120 triangles. Deux grappes courtes sous le bout nu de chaque rameau, [azimut en degrés,
		// hauteur en mètres du modèle], à 0,88 et 0,76 m de l'axe, ramenés à l'emprise et à la hauteur que rend crop.js. Relevés sur
		// le cassis écrit à la main (rameaux BRANCHES, boite de 1,831 m d'emprise et 1,632 m de haut), qu'un export Blender a remplacé :
		// un modèle réexporté déplace ses rameaux, et ces coordonnées se relèvent alors sur le modèle cuit. Tirées au hasard autour du
		// buisson, elles flottaient à côté de lui. Trois baies en file, à peine déhanchées : serrées en bouquet, elles faisaient le cône
		// d'une grappe de raisin
		cassis: (() => {
			const branches = [[14, .82], [63, .7], [118, .88], [171, .74], [222, .86], [274, .68], [325, .78]]
				.map(([degrees, y]) => ({ angle: degrees * Math.PI / 180, y: (y + .011) / 1.632 }));
			const RADII = [.88 / 1.831, .76 / 1.831], SWAY = .012, SIZE = .11;
			return rnd => branches.filter(() => rnd() < .86).flatMap(({ angle, y }) => RADII.flatMap((radius, strig) => Array.from({ length: 3 - (strig && rnd() < .5 ? 1 : 0) }, (_, k) => {
				const side = (k % 2 ? 1 : -1) * SWAY, size = SIZE * (.88 + rnd() * .2);
				return { shape: 'gousse', color: '#141a3a', x: Math.cos(angle) * radius - Math.sin(angle) * side, z: Math.sin(angle) * radius + Math.cos(angle) * side, y: y + strig * .012, size: [size, size, size], yaw: rnd() * 6.28, drop: .05 + k * .09 };
			})));
		})(),
		// Au pourtour de la couronne en vase de myrtille.js, dans sa moitié haute où sont les rameaux de l'an passé : le bleu clair
		// n'a de contraste que sur le feuillage sombre bleuté, pas sous la couronne
		myrtille: rnd => around(4, rnd, angle => {
			const center = at(angle, .38 + rnd() * .07), y = .55 + rnd() * .25;
			return Array.from({ length: 5 }, () => ({ shape: 'gousse', color: '#6f82b8', x: center.x + (rnd() - .5) * .1, z: center.z + (rnd() - .5) * .1, y: y + (rnd() - .5) * .04, size: [.07, .07, .07], drop: .02 + rnd() * .04 }));
		}).flat(),
		// Chapelets pendus sous les feuilles, dans l'étage que le gobelet de groseille.js laisse dégagé à mi-hauteur, aux azimuts des
		// charpentières. Six baies et pas de pédoncule : six `gousse` font déjà les 120 triangles d'une grappe
		groseille: rnd => {
			const AZIMUTHS = [10, 62, 114, 163, 211, 262, 311];
			return Array.from({ length: 10 + Math.floor(rnd() * 3) }, (_, i) => {
				const angle = (AZIMUTHS[i % 7] + (rnd() - .5) * 30) * Math.PI / 180, base = { ...at(angle, .22 + rnd() * .05), y: .34 + rnd() * .08, tilt: (rnd() - .5) * .3, yaw: rnd() * 6.28 };
				return Array.from({ length: 6 }, (_, k) => ({ ...base, shape: 'gousse', color: '#d42a2e', size: [.045, .045, .045], drop: .0225 + k * .042 }));
			}).flat();
		},
		// Grappes de trois pendues sous les trois fils du bas de mure.js, des deux côtés de la souche, devant et derrière le plan des
		// fils, une grappe sur deux de chaque côté : tirées au hasard, elles tombaient parfois toutes du même côté, derrière les feuilles.
		// Mêlées de rouges et de vertes, car une ronce cultivée mûrit en continu et c'est ce mélange qui la sépare du cassis
		mure: rnd => Array.from({ length: 3 + Math.floor(rnd() * 2) }, (_, i) => {
			const x = (i % 2 ? 1 : -1) * (.15 + rnd() * .27), y = [.32, .48, .66][i % 3], z = i & 2 ? .18 : -.18;
			return Array.from({ length: 3 }, (_, k) => {
				const ripe = rnd();
				return { shape: 'gousse', color: ripe < .67 ? '#1d1422' : ripe < .92 ? '#b8203a' : '#8fb04a', x: x + (k - 1) * .05, z, y, size: [.14, .15, .14], drop: .06 + (k % 2) * .05 };
			});
		}).flat(),
		// Calices rouges de la roselle en chapelet serré sur la moitié haute des quatre tiges de hibiscus.js, qui la laisse nue : c'est
		// cet alignement dressé contre une baguette rouge qui les sépare d'un piment pendant ou d'une grappe de baies. Chacun est couché
		// sur sa tige, dans son axe, et se touche presque avec le suivant. Le fuseau, ovoïde et pointu, est retourné pour porter son
		// ventre vers le bas ; le cône fin se lisait en piment. `wood` reprend le pied et la tête de chaque tige, [x, y, z] en mètres du
		// modèle, ramenés à l'emprise et à la hauteur que rend crop.js (boite du modèle de 0,935 m d'emprise et 1,465 m de haut) : ils
		// suivent ce fichier. 60 triangles par calice, dix-sept calices au plus par plant
		hibiscus: (() => {
			const wood = [[[0, 0, 0], [.03, 1.46, -.02]], [[0, .08, 0], [.21, 1.3, .1]], [[0, .14, 0], [-.2, 1.2, .14]], [[0, .2, 0], [-.03, 1.34, -.23]]];
			const BOX = .935, HEIGHT = 1.465;
			return rnd => wood.flatMap(([from, to], branch) => {
				// Axe de la tige une fois le plant ramené à son emprise (0,8 m) et à sa hauteur (1,5 m)
				const axis = new THREE.Vector3((to[0] - from[0]) / BOX * .8, (to[1] - from[1]) / HEIGHT * 1.5, (to[2] - from[2]) / BOX * .8).normalize();
				const tilt = Math.PI + Math.acos(axis.y), yaw = Math.atan2(axis.x, axis.z);
				return (branch === 0 ? [.52, .64, .76, .87, .97] : [.54, .68, .82, .96]).filter(() => rnd() < .92).map((t, k) => {
					const along = t + (rnd() - .5) * .03, angle = branch * 1.7 + k * 2.4 + rnd() * .6;
					return {
						shape: 'fuseau', color: k % 3 === 2 ? '#a80f2a' : '#c4122f',
						x: (from[0] + (to[0] - from[0]) * along) / BOX + Math.cos(angle) * .035, z: (from[2] + (to[2] - from[2]) * along) / BOX + Math.sin(angle) * .035,
						y: (from[1] + (to[1] - from[1]) * along) / HEIGHT, size: [.17, .25, .17], tilt: tilt + (rnd() - .5) * .16, yaw: yaw + (rnd() - .5) * .16,
					};
				});
			});
		})(),
		framboise: (() => {
			const tips = [[-.256, .537, .26], [-.323, .714, -.173], [-.127, .578, -.286], [-.016, .495, -.218], [-.065, .696, .311], [.027, .514, .217], [.053, .678, -.323], [.125, .513, .258], [.203, .688, -.26], [.266, .573, -.243]];
			return rnd => tips.filter(() => rnd() < .85).flatMap(([x, y, z]) => Array.from({ length: rnd() < .35 ? 2 : 1 }, (_, k) => ({
				shape: 'cone', color: rnd() < .17 ? '#e0607a' : '#c8213e', x: x + k * .045, z, y: y - k * .015,
				size: [.13, .15, .13], tilt: (rnd() - .5) * .3, yaw: rnd() * 6.28, drop: .1 + k * .03,
			})));
		})(),
		// Une cloche par secteur laissé libre entre les trois tiges de poivron.js. Chacune porte au sommet un petit calice et un court
		// pédoncule vert, comme les cornes du piment, pour se lire pendue à la plante et non posée devant
		poivron: (() => {
			const HEIGHT = .3, CALYX = .03, STALK = .06;
			return rnd => around(3, rnd, (angle, i) => {
				const fruit = { shape: 'cloche', color: (i + Math.floor(rnd() * 3)) % 3 === 2 ? '#f2b705' : '#d01c1c', ...at(angle, .27), y: .3 + rnd() * .1, size: [.25, HEIGHT, .25] };
				return [
					fruit,
					{ ...fruit, shape: 'cylindre', color: '#4f7a2e', size: [.14, CALYX, .14], drop: -(HEIGHT + CALYX) / 2 },
					{ ...fruit, shape: 'cylindre', color: '#7a9a3c', size: [.03, STALK, .03], drop: -(HEIGHT / 2 + CALYX + STALK / 2) },
				];
			}).flat();
		})(),
		// Cornes pendues chacune à un court pédoncule vert, comme les lanternes du physalis : sans lui, elles paraissaient posées à côté
		// du feuillage. Le pédoncule prend l'inclinaison de sa corne, qui pend sous lui dans le même axe ; une sur cinq encore verte
		piment: (() => {
			const STALK = .04, LENGTH = .3;
			return rnd => around(8 + Math.floor(rnd() * 3), rnd, angle => {
				const color = rnd() < .2 ? '#3f8f2a' : '#c81e1a';
				const base = { ...at(angle, .3 + rnd() * .1), y: .4 + rnd() * .4, tilt: (rnd() - .5) * .5, yaw: rnd() * 6.28 };
				return [
					{ ...base, shape: 'cone', color, size: [.08, LENGTH, .08], drop: STALK + LENGTH / 2 },
					{ ...base, shape: 'cylindre', color: '#7a9a3c', size: [.018, STALK, .018], drop: STALK / 2 },
				];
			}).flat();
		})(),
		aubergine: rnd => around(3, rnd, angle => ({ shape: 'aubergine', color: '#ffffff', ...at(angle, .34), y: .24 + rnd() * .1, size: [.2, .46, .2], tilt: (rnd() - .5) * .3, yaw: angle })),
		// Contre le filet, des deux côtés : le filet suit l'axe x du plant
		concombre: rnd => Array.from({ length: 4 }, (_, i) => ({ shape: 'cylindre', color: '#1f4a1c', x: (i - 1.5) * .25, z: (i % 2 ? 1 : -1) * .2, y: .25 + rnd() * .35, size: [.13, .44, .13], tilt: (rnd() - .5) * .2 })),
		// Sur le même filet que le concombre, plus nombreux, deux fois plus courts et d'un vert plus sombre
		cornichon: rnd => Array.from({ length: 7 }, (_, i) => ({ shape: 'cylindre', color: '#163a17', x: (i - 3) * .12, z: (i % 2 ? 1 : -1) * .2, y: .2 + rnd() * .45, size: [.08, .22, .08], tilt: (rnd() - .5) * .3 })),
		// En rayons depuis le collet : un court pédoncule part du cœur, sous le dôme des feuilles, et la courgette le prolonge vers
		// l'extérieur, la base cachée sous le feuillage et la pointe qui en sort et retombe au sol. Posées au bord de la couronne, elles
		// semblaient tombées à côté du plant. Le pédoncule et le fruit partagent le point d'attache et l'axe ; drop négatif les pousse
		// vers l'extérieur le long de cet axe
		courgette: (() => {
			const LENGTH = .7, STALK = .1;
			return rnd => around(2 + Math.floor(rnd() * 2), rnd, angle => {
				const base = { ...at(angle, .06), y: 0, tilt: Math.PI / 2 + .1, yaw: Math.PI / 2 - angle, lift: .16 };
				return [
					{ ...base, shape: 'cylindre', color: '#1f4a1c', size: [.19, LENGTH, .19], drop: -(STALK + LENGTH / 2) },
					{ ...base, shape: 'cylindre', color: '#8fae5a', size: [.07, STALK, .07], drop: -STALK / 2 },
				];
			}).flat();
		})(),
		// Deux ou trois courges par pied, couchées dans le feuillage autour du collet, de la largeur de la pastèque
		courge: rnd => around(2 + Math.floor(rnd() * 2), rnd, angle => ({ shape: 'courge', color: '#e57a1a', ...at(angle, .24 + rnd() * .08), y: 0, size: [.52, .36, .52], tilt: (rnd() - .5) * .3, yaw: rnd() * 6.28, lift: .17 })),
		// Plus petit que la courge, qui reste la grosse boule orange au sol
		melon: rnd => around(1 + Math.floor(rnd() * 2), rnd, angle => ({ shape: 'melon', color: '#d9d6a4', ...at(angle, .46), y: 0, size: [.32, .28, .3], tilt: Math.PI / 2, yaw: Math.PI / 2 - angle, lift: .14 })),
		pasteque: rnd => [{ shape: 'pasteque', color: '#ffffff', ...at(rnd() * 6.28, .3), y: 0, size: [.5, .74, .5], tilt: Math.PI / 2, yaw: rnd() * 6.28, lift: .25 }],
		// Tubercules couchés, à demi sortis de terre au bord du tapis : seul organe qui sépare la planche de la mâche
		'patate-douce': rnd => around(2, rnd, angle => ({ shape: 'fuseau', color: '#c4703a', ...at(angle, .38), y: 0, size: [.17, .48, .17], tilt: Math.PI / 2, yaw: Math.PI / 2 - angle, lift: .06 })),
		// Posés au pied à la récolte seulement : modélisés dans le plant, ils resteraient visibles toute l'année
		// Une grappe serrée d'un côté du pied, à demi couchée
		'poire-de-terre': rnd => {
			const side = rnd() * 6.28, count = 4 + Math.floor(rnd() * 2);
			return Array.from({ length: count }, (_, i) => {
				const angle = side + (i - (count - 1) / 2) * .42;
				return { shape: 'gousse', color: '#c27a68', ...at(angle, .3 + rnd() * .06), y: 0, size: [.3, .6, .3], tilt: Math.PI / 2 - .3 - rnd() * .3, yaw: Math.PI / 2 - angle, lift: .1 };
			});
		},
		// Haricot : une gousse de 42 cm sur 4 (rapport 10,5), à 9 cm de large elle se lisait en concombre. À la vue de travail (20 px/m, fruits
		// grossis de 1,7) elle fait 1,4 px de large : en dessous de 3 cm elle passerait sous le pixel
		haricot: pod('#9fca4a', .42, .04, onPoles, { fewest: 4, spread: 2, bunch: [3, 1], depth: .6, shape: 'cosse' }),
		// Pois : entre le haricot et la fève, par paires sur le filet
		pois: pod('#a6d65a', .3, .06, onNet, { fewest: 6, spread: 3, bunch: [2, 0], fan: .2, depth: .7, shape: 'cosse' }),
		// Fève : une grosse gousse charnue, dressée le long de la tige, à peine aplatie
		feve: pod('#2f5a20', .36, .085, onStems, { up: true, depth: .8 }),
		// Gousses dressées comme la fève, mais en cône pointe en haut (tilt π) et collées à la tige : le cône retourné monte de son point d'attache
		gombo: rnd => Array.from({ length: 4 + Math.floor(rnd() * 3) }, (_, i) => ({ shape: 'cone', color: '#7aa83e', ...at(i * 2.4 + rnd(), .07), y: .5 + rnd() * .35, size: [.08, .38, .08], tilt: Math.PI, yaw: rnd() * 6.28, drop: .19 })),
		// Tubercules bosselés au pied, sortis à la récolte (novembre à mars), à demi couchés
		topinambour: rnd => around(5 + Math.floor(rnd() * 3), rnd, angle => ({ shape: 'fuseau', color: '#c9a07a', ...at(angle, .2 + rnd() * .1), y: 0, size: [.2, .32, .2], tilt: Math.PI / 2 - .4 + rnd() * .3, yaw: Math.PI / 2 - angle, lift: .08 })),
		// Noix mûres, brun doré : le brou vert ne se détache pas du feuillage, c'est le stade de la récolte qui se lit
		noix: onCrown([[.08, .97, 0], [-.24, .77, .31], [-.32, .76, -.17], [.23, .75, .26], [.3, .75, -.19], [.07, .8, -.26], [-.26, .71, .06], [.01, .73, .32], [-.1, .87, .16], [-.12, .83, -.26], [.07, .82, .14]], { shape: 'gousse', colors: ['#b8862e', '#9a6a1e'], size: [.3, .3, .3], share: .75, bunch: 1 }),
		// Fruitiers : d et h sont la taille réelle de l'arbre, les fruits restent grossis pour se lire à 20 px/m.
		// Pommes à la surface du houppier de pomme.js, sur trois étages (pourtour bas, épaules, sommet), rien sous le tronc dégagé
		pomme: (rnd, color = rnd() < .25 ? '#e8b23a' : '#d42a22') => [[8, .46, .52, .36, .52], [5, .4, .46, .58, .7], [2, .2, .3, .84, .92]].flatMap(([count, r0, r1, y0, y1]) => around(count + Math.floor(rnd() * 2), rnd, angle => ({
			shape: 'gousse', color, ...at(angle, r0 + rnd() * (r1 - r0)), y: y0 + rnd() * (y1 - y0), size: [.36, .32, .36], drop: .1,
		}))),
		// Cerises rouge vif par deux, sur le dessus et les épaules du houppier
		cerise: onCrown([[-.04, .95, .07], [.27, .87, -.19], [-.13, .84, -.28], [.24, .75, .26], [-.31, .73, .07], [.17, .71, .02], [-.18, .85, .25], [.08, .7, -.23], [.03, .84, .3], [-.27, .84, -.13], [-.18, .85, -.01]], { shape: 'gousse', colors: ['#d8121f', '#a30f1c'], size: [.24, .24, .24], share: .85, bunch: 2 }),
		// Mirabelles jaune d'or par deux, une sur quatre rosée
		mirabelle: onCrown([[-.04, .95, .07], [.29, .75, -.18], [-.12, .73, -.29], [.21, .73, .23], [-.29, .72, .06], [.15, .8, .01], [-.2, .74, .26], [.08, .71, -.26], [.01, .69, .24], [-.26, .72, -.14], [-.08, .84, -.15], [-.17, .83, -.01]], { shape: 'gousse', colors: ['#f5c518', '#f29a1c'], size: [.24, .24, .24], share: .85, bunch: 2 }),
		// Abricots orange, une joue plus rouge une fois sur quatre
		abricot: onCrown([[.08, .95, 0], [-.33, .74, .07], [-.12, .74, -.31], [.01, .71, .28], [.3, .75, -.19], [.26, .7, .04], [-.21, .75, .27], [.09, .72, -.27], [-.26, .72, -.14], [.21, .73, .24], [-.09, .83, .14], [-.07, .81, -.14]], { shape: 'gousse', colors: ['#f59a1c', '#e8641c'], size: [.3, .29, .3], share: .8, bunch: 1 }),
		// Coings jaunes, gros et trapus
		coing: onCrown([[-.04, .95, .07], [.27, .73, -.17], [-.3, .75, -.16], [.23, .74, .25], [-.21, .75, .27], [-.09, .69, -.21], [-.23, .69, .05], [.01, .7, .25], [.15, .8, .01], [.08, .7, -.25], [.29, .72, .04], [.07, .8, .14]], { shape: 'poire', colors: ['#e8b81a', '#d9c94a'], size: [.36, .38, .36], share: .7, bunch: 1 }),
		// Ombelles de baies noires, aplaties, posées sur le dessus du buisson
		sureau: onCrown([[.08, .96, 0], [-.39, .8, -.21], [.02, .8, .45], [.14, .81, -.43], [-.26, .8, .07], [-.17, .8, -.41], [.29, .75, -.18], [.26, .77, .29], [-.09, .88, -.17], [.33, .74, .05], [-.23, .77, .3], [.1, .92, .19]], { shape: 'gousse', colors: ['#2b1f33', '#4a2a4e'], size: [.4, .18, .4], share: .7, bunch: 1 }),
		// Figues violettes, le stade mûr : vertes, elles se perdaient dans les feuilles
		figue: onCrown([[-.22, .93, -.01], [.31, .76, -.2], [.22, .74, .24], [-.18, .72, .23], [-.11, .8, -.24], [.09, .81, -.13], [.02, .81, .28], [-.28, .73, -.15], [-.25, .7, .05], [-.09, .81, .13], [.08, .71, -.26]], { shape: 'poire', colors: ['#5a2a4e', '#7a3a5e'], size: [.3, .36, .3], share: .75, bunch: 1 }),
		// Pêches collées contre les rameaux rouges de peche.js, deux points d'attache par rameau, [x, y, z] en fraction de l'emprise et
		// de la hauteur ; deux sur trois portent un fruit, une sur trois montre sa joue rouge sombre
		peche: (() => {
			const spots = [[.211, .742, .062], [.215, .846, .096], [.207, .577, -.017], [.258, .652, -.057], [-.122, .742, .217], [-.159, .854, .222], [-.033, .581, .205], [.016, .652, .256], [-.23, .71, -.155], [-.235, .82, -.188], [-.228, .555, -.079], [-.279, .635, -.041], [.06, .781, -.3], [.094, .89, -.309], [-.02, .613, -.283], [-.07, .699, -.328]];
			return (rnd, color = rnd() < .33 ? '#b3262b' : '#f2a13a') => spots.filter(() => rnd() < .65).map(([x, y, z]) => ({ shape: 'gousse', color, x: x * 1.1, y, z: z * 1.1, size: [.34, .32, .34] }));
		})(),
		// Prunes ovales bleu violet par deux, sur le dessus et les épaules du houppier
		prune: onCrown([[.07, .89, 0], [-.25, .7, .05], [.01, .71, .27], [.08, .7, -.25], [.16, .76, .16], [-.08, .8, .13], [.19, .68, -.12], [-.21, .69, -.11], [.09, .81, -.13], [-.16, .81, -.01]], { shape: 'gousse', colors: ['#5a5fae', '#8088c8'], size: [.27, .36, .27], share: .9, bunch: 2 }),
		// Poires pendues par le col sous les masses de poire.js, au bord d'une couronne qui se resserre vers la cime, aucune sur la flèche
		poire: (rnd, color = rnd() < .25 ? '#d9c94a' : '#c3cf52') => around(10 + Math.floor(rnd() * 5), rnd, (angle, i) => {
			const y = .38 + ((i * .37 + rnd() * .2) % 1) * .4;
			return { shape: 'poire', color, ...at(angle, .44 - (y - .38) / .4 * .14), y, size: [.27, .38, .27], tilt: (rnd() - .5) * .4, yaw: rnd() * 6.28, drop: .14 };
		}),
		// Kiwis ovales pendus sous le toit de feuilles de kiwi.js, par groupes de 2 à 4 le long des six cannes (x en fraction de
		// l'emprise, côté alterné), entre le tronc et les poteaux
		kiwi: (rnd, color = rnd() < .25 ? '#74552e' : '#8a6a3c') => [-.34, -.22, -.09, .07, .2, .33].flatMap((x, i) => Array.from({ length: 2 + Math.floor(rnd() * 3) }, () => ({
			shape: 'gousse', color, x: x + (rnd() - .5) * .04, y: .78, z: (i % 2 ? -1 : 1) * (.06 + rnd() * .18), size: [.24, .32, .24], tilt: (rnd() - .5) * .4, yaw: rnd() * 6.28, drop: .42 + rnd() * .12,
		}))),
		// Grappes en amas de six baies sur trois rangs (3, 2, 1), pendues sous les bras de vigne.js au pied des sarments, de part et
		// d'autre du fil porteur, sous le rideau de feuilles. 120 triangles par grappe, le plafond
		vigne: rnd => [-.39, -.24, -.09, .07, .23, .4].filter(() => rnd() < .85).flatMap((x, i) => {
			const cx = x + (rnd() - .5) * .06, cz = (i % 2 ? -.06 : .06), y = .36 + (rnd() - .5) * .06;
			return [[3, .05], [2, .14], [1, .23]].flatMap(([count, drop]) => Array.from({ length: count }, (_, k) => ({
				shape: 'gousse', color: '#2a1a3a', x: cx + (k - (count - 1) / 2) * .05, y, z: cz + (k % 2 ? .02 : -.02), size: [.11, .11, .11], drop,
			})));
		}),
	};

	// Les organes souterrains du registre : en terre jusqu'à l'arrachage, qui emporte le pied entier, quand les fruits se nouent
	// et grossissent à la vue avant la récolte. Ce qui en affleure avant l'arrachage est un choix de la scène qui les pose
	const underground = new Set(['patate-douce', 'poire-de-terre', 'topinambour']);

	// Les matrices et les couleurs passent par des tableaux gardés d'un appel à l'autre : une scène qui rejoue l'année repose
	// les fruits chaque jour, et une matrice clonée par fruit fait plusieurs mégaoctets de déchets par jour, puis des arrêts
	// du ramasse-miettes
	const fruitMatrix = new THREE.Matrix4(), plantMatrix = new THREE.Matrix4(), placed = new THREE.Matrix4();
	const turn = new THREE.Quaternion(), euler = new THREE.Euler(0, 0, 0, 'YXZ'), scaling = new THREE.Vector3(), position = new THREE.Vector3();
	const up = new THREE.Vector3(0, 1, 0), drop = new THREE.Vector3();
	const fruitRows = new Map();

	// Une forme qui porte sa texture (le réseau du melon) prend une copie du matériau des fruits qui la lit, une seule par
	// matériau : le reste de la copie, réglages d'ambiance compris, est celui de l'appelant
	const texturedMaterials = new WeakMap();
	const shapeMaterial = (material, shape) => {
		const map = shape.userData.map;
		if(!map) {
			return material;
		}
		if(!texturedMaterials.has(material)) {
			const textured = material.clone();
			textured.map = map;
			// clone() ne recopie ni l'un ni l'autre
			textured.onBeforeCompile = material.onBeforeCompile;
			textured.customProgramCacheKey = material.customProgramCacheKey;
			texturedMaterials.set(material, textured);
		}
		return texturedMaterials.get(material);
	};

	// Les fruits d'un plant ne dépendent que de son espèce et de sa graine : tirés une fois, ils resservent à chaque pose
	const fruitDraws = new Map();
	const drawFruits = (slug, seed, random) => {
		let draws = fruitDraws.get(slug);
		if(!draws) {
			fruitDraws.set(slug, draws = new Map());
		}
		let fruits = draws.get(seed);
		if(!fruits) {
			draws.set(seed, fruits = registry[slug](random(seed)));
		}
		return fruits;
	};

	// Plants traités entre deux points de reprise de la pose étalée
	const FRUIT_CHUNK = 16;

	// La pose en générateur : elle rend la main tous les FRUIT_CHUNK plants pendant le calcul des matrices, puis entre deux
	// formes pendant l'écriture des pools, jamais au milieu d'une forme, qui se dessinerait alors à moitié refaite. Le résultat,
	// { pools, count, triangles }, est la valeur de retour.
	// plants : [{ slug, seed, x, y, z, d, h, rot, fruit }] dans le repère de parent, fruit la taille des fruits du jour (1 par
	// défaut). grow grossit chaque fruit autour de son point d'attache, sans toucher au nombre ni à la forme : la chute sous
	// l'attache et la levée au-dessus du sol suivent la même échelle, un fruit pendu reste pendu et un fruit couché reste posé
	function* placePass(parent, material, shapes, plants, pools = new Map(), random, { grow = 1 } = {}) {

		for(const name of shapes.keys()) {
			// Les tableaux se remplissent par leur compteur et ne sont jamais vidés : vidés par length, ils rendraient leur
			// mémoire à chaque pose
			if(!fruitRows.has(name)) {
				fruitRows.set(name, { matrices: [], colors: [], count: 0 });
			}
			fruitRows.get(name).count = 0;
		}

		let done = 0;
		for(const plant of plants) {
			if(!registry[plant.slug]) {
				continue;
			}
			if(++done % FRUIT_CHUNK === 0) {
				yield;
			}
			plantMatrix.compose(position.set(plant.x, plant.y, plant.z), turn.setFromAxisAngle(up, plant.rot), scaling.set(1, 1, 1));
			const scale = grow * (plant.fruit ?? 1);
			for(const fruit of drawFruits(plant.slug, plant.seed, random)) {
				euler.set(fruit.tilt ?? 0, fruit.yaw ?? 0, 0);
				turn.setFromEuler(euler);
				// Un fruit posé au sol garde son centre à une demi-épaisseur au-dessus du sol, quel que soit le plant
				drop.set(0, -(fruit.drop ?? 0) * scale, 0).applyQuaternion(turn);
				fruitMatrix.compose(position.set(fruit.x * plant.d + drop.x, (fruit.lift ?? fruit.y * plant.h) * (fruit.lift === undefined ? 1 : scale) + drop.y, fruit.z * plant.d + drop.z), turn, scaling.fromArray(fruit.size).multiplyScalar(scale));
				const rows = fruitRows.get(fruit.shape), at = rows.count++;
				placed.multiplyMatrices(plantMatrix, fruitMatrix);
				for(let k = 0; k < 16; k++) {
					rows.matrices[at * 16 + k] = placed.elements[k];
				}
				rows.colors[at] = fruit.color;
			}
		}

		let count = 0, triangles = 0;
		const color = new THREE.Color();
		for(const name of shapes.keys()) {
			const { matrices, colors, count: length } = fruitRows.get(name);
			let pool = pools.get(name);
			if(length === 0 && !pool) {
				continue;
			}
			yield;
			if(!pool || pool.capacity < length) {
				if(pool) {
					parent.remove(pool.mesh);
					pool.mesh.dispose();
				}
				const capacity = Math.max(64, Math.ceil(length * 1.3));
				const mesh = new THREE.InstancedMesh(shapes.get(name), shapeMaterial(material, shapes.get(name)), capacity);
				// La couleur d'instance n'existe qu'après une première écriture
				mesh.setColorAt(0, color.set('#ffffff'));
				mesh.castShadow = true;
				// Les instances débordent la sphère de la forme, qui ne borne qu'un fruit à l'origine
				mesh.frustumCulled = false;
				parent.add(mesh);
				pool = { mesh, capacity };
				pools.set(name, pool);
			}
			for(let i = 0; i < length; i++) {
				pool.mesh.setMatrixAt(i, placed.fromArray(matrices, i * 16));
				pool.mesh.setColorAt(i, color.set(colors[i]));
			}
			pool.mesh.count = length;
			pool.mesh.visible = length > 0;
			pool.mesh.instanceMatrix.needsUpdate = true;
			pool.mesh.instanceColor.needsUpdate = true;
			count += length;
			triangles += length * geometry.triangles(shapes.get(name));
		}

		return { pools, count, triangles };

	}

	return {

		// slug → random => [{ shape, color, x, y, z, size, tilt, yaw, drop, lift }], les fruits d'un plant dans son repère
		registry,

		underground,

		// Une Map nom → géométrie, une par forme : un appel de dessin par forme à la pose
		shapes: () => new Map(Object.entries(SHAPES).map(([name, make]) => [name, make()])),

		material: ({ gradientMap }) => new THREE.MeshToonMaterial({ gradientMap, vertexColors: true }),

		placePass,

		// La même pose, d'une traite
		place(parent, material, shapes, plants, pools = new Map(), random, options = {}) {

			const pass = placePass(parent, material, shapes, plants, pools, random, options);

			let step = pass.next();
			while(!step.done) {
				step = pass.next();
			}

			return step.value;

		},

		// Une espèce récoltée entière (racine, pomme, bulbe, tubercule) quitte la planche à sa récolte. Une espèce récoltée sur
		// pied y reste entière jusqu'à la fin de sa série : un ligneux, une vivace, une fleur ou un couvert en parterre, celle
		// dont on prélève des feuilles, des tiges ou des fruits que son modèle ne sépare pas (standing, au catalogue), et celle
		// qui porte des fruits distincts du pied. entry est une entrée du catalogue, cycle celui de la série
		harvestedWhole(entry, cycle) {

			if(woody(entry) || cycle === 'perennial' || entry.parterre || entry.standing) {
				return false;
			}

			const base = entry.slug.split('~')[0];

			return underground.has(base) || !(entry.fruitModule || registry[entry.slug] || registry[base]);

		}

	};

}
