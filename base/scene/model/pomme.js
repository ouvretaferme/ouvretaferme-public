// Pommier basse tige en gobelet, pensé pour se lire sur un rang à 20 px/m : un tronc court et trapu gris brun qui se divise bas en
// quatre charpentières ouvertes presque à l'horizontale, sous un houppier rond, bas et étalé, plus large que haut, au sommet
// aplati, fait de masses bosselées. Le dessous et le pourtour sont sombres, le dessus clair. Les pommes sont posées par le socle
// (FRUITS). C'est ce qui le sépare du poirier (pyramidal, plus haut que large), du cerisier (charpentières montantes, houppier
// haut porté), du pêcher (gobelet creux) et du noyer (tronc clair plus long, couronne haute).
// Fiche : pomme.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
function crown(THREE, helpers) {
	const { ico, merge } = helpers;
	const TOP = '#5e9a3a'; const SHADE = '#3f6f2c'; const BARK = '#84705c';
	const V = (x, y, z) => new THREE.Vector3(x, y, z);

	// Tube effilé le long d'une suite de points, `sides` pans, normales lissées par computeVertexNormals sur une géométrie indexée
	const tube = (points, radii, sides) => {
		const position = [], index = [];
		points.forEach((p, i) => {
			const axis = (points[i + 1] ?? p).clone().sub(points[i - 1] ?? p).normalize();
			const u = V(.31, 0, .95).cross(axis).normalize(), w = V().crossVectors(axis, u);
			for(let k = 0; k < sides; k++) {
				const a = k * Math.PI * 2 / sides;
				position.push(...p.clone().addScaledVector(u, Math.cos(a) * radii[i]).addScaledVector(w, Math.sin(a) * radii[i]).toArray());
			}
		});
		for(let s = 0; s < points.length - 1; s++) {
			for(let k = 0; k < sides; k++) {
				const a = s * sides + k, b = s * sides + (k + 1) % sides, c = a + sides, d = b + sides;
				index.push(a, b, c, b, d, c);
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setIndex(index);
		g.computeVertexNormals();
		return g;
	};

	// Le tronc : court, évasé au pied, un peu penché. 10 triangles
	const fork = V(.06, .68, -.03);
	const trunk = tube([V(0, 0, 0), fork], [.2, .13], 5);

	// Les quatre charpentières : [azimut, rayon du bout, hauteur du bout]. Inégales, elles partent de la fourche presque à
	// l'horizontale et se redressent à peine. 6 triangles chacune
	const LIMBS = [[.35, 1.12, 1.04], [1.85, 1.0, 1.12], [3.5, 1.15, 1.0], [5.0, 1.05, 1.1]];
	const limbs = LIMBS.map(([azimuth, radius, height]) => tube([fork.clone().add(V(0, -.06, 0)), V(Math.cos(azimuth) * radius, height, Math.sin(azimuth) * radius)], [.1, .06], 3));

	// Une masse de feuillage : un icosaèdre aux normales lissées, aplati, tourné pour que deux masses ne montrent pas les mêmes pans
	const clump = (r, x, y, z, flatten, turn) => ico(r).rotateY(turn).rotateX(turn * .37).scale(1, flatten, 1).translate(x, y, z);

	// Le pourtour bas, sombre : une masse au bout de chaque charpentière et une entre deux, à des rayons et des hauteurs inégaux.
	// C'est lui qui porte le plus grand diamètre, bas dans le houppier
	const low = Array.from({ length: 7 }, (_, i) => {
		const a = .35 + i * .898 + (i % 2) * .12, d = .9 + (i % 3) * .07;
		return clump(.66 - (i % 3) * .05, Math.cos(a) * d, 1.58 + (i % 2) * .13, Math.sin(a) * d, .8, i * 1.3);
	});
	// Les épaules : cinq bosses à mi-hauteur qui ferment le jour entre le pourtour et le sommet. Deux sont sombres, pour que le
	// houppier ne se lise pas en deux étages de couleur
	const shoulders = Array.from({ length: 5 }, (_, i) => {
		const a = .8 + i * 1.257 + (i % 2) * .2, d = .86 + (i % 2) * .1;
		return clump(.64 - (i % 3) * .06, Math.cos(a) * d, 2.3 + (i % 3) * .12, Math.sin(a) * d, .82, i * 2.1 + 1);
	});
	// Le sommet : une masse aplatie un peu plus étroite que le pourtour, qui ferme le dôme par un dessus plat et non par une pointe
	const cap = clump(1.08, .08, 2.9, -.05, .55, .4);
	const high = [cap, ...shoulders.filter((_, i) => i % 3 !== 2)];
	low.push(...shoulders.filter((_, i) => i % 3 === 2));

	return {
		d: 3, h: 3.5,
		parts: [
			{ color: TOP, sway: .12, geometry: merge(high) },
			{ color: SHADE, sway: .12, geometry: merge(low) },
			{ color: BARK, sway: 0, geometry: merge([trunk, ...limbs]) },
		],
	};
}

// Le bois d'hiver : tronc, flèche, charpentières tendues jusqu'au bord du houppier et rameaux. Il remplace le bois de crown(), trop
// court pour porter l'arbre une fois les feuilles tombées. Calculé sur le houppier par verger/bois.html, recopié par bois.py :
// après une retouche du houppier, les deux se rejouent
const WOOD = [-0.189, 0, 0.064, -0.144, 0.973, 0.065, 0.15, 0, 0.132, 0.15, 0, 0.132, -0.144, 0.973, 0.065, 0.081, 0.977, 0.109, 0.15, 0, 0.132, 0.081, 0.977, 0.109, 0.039, 0.005, -0.196, 0.039, 0.005, -0.196, 0.081, 0.977, 0.109, 0.007, 0.98, -0.107, 0.039, 0.005, -0.196, 0.007, 0.98, -0.107, -0.189, 0, 0.064, -0.189, 0, 0.064, 0.007, 0.98, -0.107, -0.144, 0.973, 0.065, -0.114, 0.98, 0.053, 0.113, 3.226, 0.184, 0.055, 0.968, 0.089, 0.055, 0.968, 0.089, 0.113, 3.226, 0.184, 0.002, 0.983, -0.075, 0.002, 0.983, -0.075, 0.113, 3.226, 0.184, -0.114, 0.98, 0.053, 0.011, 2.233, 0.121, 0.562, 3.096, 0.593, 0.075, 2.185, 0.143, 0.075, 2.185, 0.143, 0.562, 3.096, 0.593, 0.076, 2.224, 0.07, 0.076, 2.224, 0.07, 0.562, 3.096, 0.593, 0.011, 2.233, 0.121, 0.008, 2.219, 0.125, -0.072, 3.242, -0.695, 0.089, 2.237, 0.135, 0.089, 2.237, 0.135, -0.072, 3.242, -0.695, 0.064, 2.186, 0.074, 0.064, 2.186, 0.074, -0.072, 3.242, -0.695, 0.008, 2.219, 0.125, -0.062, 0.813, 0.055, -1.194, 1.816, 0.852, 0.053, 0.94, 0.076, 0.053, 0.94, 0.076, -1.194, 1.816, 0.852, -0.048, 0.938, -0.064, -0.048, 0.938, -0.064, -1.194, 1.816, 0.852, -0.062, 0.813, 0.055, -0.65, 1.37, 0.457, -0.727, 2.306, 1.343, -0.556, 1.384, 0.456, -0.556, 1.384, 0.456, -0.727, 2.306, 1.343, -0.612, 1.435, 0.398, -0.612, 1.435, 0.398, -0.727, 2.306, 1.343, -0.65, 1.37, 0.457, -0.077, 0.824, 0.058, -1.274, 1.659, -0.501, 0.005, 0.975, 0.08, 0.005, 0.975, 0.08, -1.274, 1.659, -0.501, 0.017, 0.892, -0.071, 0.017, 0.892, -0.071, -1.274, 1.659, -0.501, -0.077, 0.824, 0.058, -0.699, 1.314, -0.221, -0.914, 1.992, -0.859, -0.612, 1.353, -0.216, -0.612, 1.353, -0.216, -0.914, 1.992, -0.859, -0.629, 1.286, -0.281, -0.629, 1.286, -0.281, -0.914, 1.992, -0.859, -0.699, 1.314, -0.221, -0.085, 0.971, 0.028, 0.807, 1.75, -1.376, 0.066, 0.901, 0.075, 0.066, 0.901, 0.075, 0.807, 1.75, -1.376, -0.037, 0.818, -0.037, -0.037, 0.818, -0.037, 0.807, 1.75, -1.376, -0.085, 0.971, 0.028, 0.342, 1.36, -0.659, 0.004, 2.304, -1.622, 0.429, 1.4, -0.655, 0.429, 1.4, -0.655, 0.004, 2.304, -1.622, 0.412, 1.33, -0.717, 0.412, 1.33, -0.717, 0.004, 2.304, -1.622, 0.342, 1.36, -0.659, -0.054, 0.99, 0.014, 1.224, 1.435, 0.777, -0.043, 0.841, 0.102, -0.043, 0.841, 0.102, 1.224, 1.435, 0.777, 0.041, 0.859, -0.049, 0.041, 0.859, -0.049, 1.224, 1.435, 0.777, -0.054, 0.99, 0.014, 0.55, 1.206, 0.417, 0.891, 2.14, 1.257, 0.64, 1.173, 0.424, 0.64, 1.173, 0.424, 0.891, 2.14, 1.257, 0.618, 1.239, 0.358, 0.618, 1.239, 0.358, 0.891, 2.14, 1.257, 0.55, 1.206, 0.417];
export function build(THREE, helpers) {
	const model = crown(THREE, helpers), geometry = new THREE.BufferGeometry();
	geometry.setAttribute('position', new THREE.Float32BufferAttribute(WOOD, 3));
	geometry.computeVertexNormals();
	return { ...model, parts: [...model.parts.filter(part => part.sway !== 0), { geometry, color: '#84705c', sway: 0 }] };
}
