// Chou pë-tsaï, pensé pour se lire à une dizaine de pixels : une pomme en tonneau, deux fois et demie plus haute que large, bombée
// au milieu et fermée en haut par un dôme frisé vert franc, dont le bas blanc est strié de larges côtes qui montent du
// pied ; autour du pied, une collerette de cinq feuilles vert franc à moitié ouvertes. La romaine et le céleri se lisent en touffe
// de pièces dressées, le chou pointu finit en pointe : ici la pomme est un seul volume plein à sommet rond, et le contraste le plus
// fort est entre le bas blanc de la pomme et les feuilles vertes qui l'entourent.
// Fiche : pe-tsai.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
export function build(THREE) {
	// Le blanc du pied est neutre et le haut franchement vert : un crème se lit jaune et un vert-jaune pâle blanchit sous un soleil
	// haut, et la pomme ne se lit plus comme un chou chinois, côtes blanches en bas et vert en haut
	const CREAM = '#f6f6ee'; const PALE = '#e3eecb'; const HEART = '#9cc85a'; const LEAF = '#5e9a3c';

	// Pomme : profil du sommet vers le pied, en mètres, [rayon, hauteur]. La plus grande largeur est au milieu, le dôme du haut se
	// referme en deux anneaux, et le pied se resserre à peine. Le dessous, posé au sol, reste ouvert. Six anneaux et dix pans
	// suffisent à un volume qui se lit à une dizaine de pixels : la 7 en porte 355 pieds un même jour, et chaque triangle de la
	// pomme y compte 355 fois.
	const profile = [[0, .48], [.064, .462], [.09, .41], [.097, .3], [.088, .14], [.06, .006]];
	const RINGS = profile.length;

	// Dix pans, alternativement une côte et un intervalle. Une côte est plus large en bas qu'en haut : l'angle qu'elle occupe
	// varie avec la hauteur, sans pan de plus, si bien que la bande blanche s'évase vers le pied comme la nervure d'une vraie feuille.
	const PANS = 10, RIBS = PANS / 2, STEP = Math.PI * 2 / RIBS;
	const ribShare = y => .55 + .25 * Math.max(-.6, 1 - y / .3);
	// Hauteur où chaque côte se perd dans le limbe : inégale d'une côte à l'autre, pour que le bas ne soit pas un rayé régulier
	const ribTop = [.36, .31, .4, .33, .38];
	// Le dôme est frisé : ses sommets avancent et reculent d'un pan à l'autre, ce qui casse la silhouette ronde en petites cloques
	const crinkle = (k, i) => profile[k][1] > .36 ? 1 + .09 * Math.sin(i * 2.3 + k * 1.7) : 1;

	const point = (k, i) => {
		const [radius, y] = profile[k];
		const rib = Math.floor(i / 2) % RIBS;
		const angle = rib * STEP + (i % 2) * STEP * ribShare(y);
		const r = radius * crinkle(k, i % PANS);
		return new THREE.Vector3(r * Math.cos(angle), y, r * Math.sin(angle));
	};

	// Chaque sommet porte la normale moyenne des faces qui le touchent : le tonneau se lit rond sous l'éclairage en paliers, et le
	// sommet du dôme, confondu en un seul point, reçoit la normale de toutes ses faces.
	const key = (k, i) => k === 0 ? '0' : `${k}:${i % PANS}`;
	const faces = [];
	for(let k = 0; k < RINGS - 1; k++) {
		for(let i = 0; i < PANS; i++) {
			const middle = (profile[k][1] + profile[k + 1][1]) / 2;
			// Sur le quadrilatère où la côte s'arrête, seul le triangle du bas est blanc : la bande finit en pointe, pas en marche
			const rib = i % 2 === 0 && profile[k + 1][1] < ribTop[i / 2];
			const ribEnd = rib && middle >= ribTop[i / 2];
			// Entre les côtes, le limbe passe du blanc au vert par un vert très pâle, à une hauteur qui change d'un intervalle à l'autre :
			// une limite au même niveau sur tout le tour se lirait comme une bague peinte
			const blade = middle < .21 + .02 * ((i * 5) % 7) ? PALE : HEART;
			const base = middle < .12 || (rib && !ribEnd);
			if(k > 0) faces.push({ color: base ? CREAM : blade, corners: [[k, i], [k + 1, i], [k, i + 1]] });
			faces.push({ color: base || ribEnd ? CREAM : blade, corners: [[k, i + 1], [k + 1, i], [k + 1, i + 1]] });
		}
	}
	const normals = new Map();
	for(const { corners } of faces) {
		const [p, q, r] = corners.map(c => point(...c));
		const n = q.sub(p).cross(r.sub(p));
		for(const c of corners) normals.set(key(...c), (normals.get(key(...c)) ?? new THREE.Vector3()).add(n));
	}
	const headParts = new Map([CREAM, PALE, HEART].map(color => [color, { position: [], normal: [] }]));
	for(const { color, corners } of faces) {
		const target = headParts.get(color);
		for(const c of corners) {
			target.position.push(...point(...c).toArray());
			target.normal.push(...normals.get(key(...c)).clone().normalize().toArray());
		}
	}
	const geometryOf = ({ position, normal }) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		return g;
	};

	// Rayon de la pomme à la hauteur y, lu sur le profil, pour que les feuilles extérieures partent contre elle sans la traverser
	const radiusAt = y => {
		for(let k = 0; k < RINGS - 1; k++) {
			const [r0, y0] = profile[k], [r1, y1] = profile[k + 1];
			if(y <= y0 && y >= y1) return r0 + (r1 - r0) * (y0 - y) / (y0 - y1);
		}
		return profile[RINGS - 1][0];
	};

	// Feuilles extérieures : cinq, chacune différente en azimut, longueur, largeur et ouverture. Elles partent du pied en s'écartant
	// à quarante-cinq degrés environ, puis leur bout se relève vers la pomme sans monter plus haut que son quart : une collerette à
	// moitié ouverte qui tient l'emprise à 0,3 m, pas un bouquet qui monterait le long du tonneau et le cacherait.
	// [azimut, longueur, demi-largeur, angle au pied, angle au bout]
	const fronds = [
		[.35, .15, .075, .78, .95],
		[1.6, .135, .066, .88, 1.1],
		[2.75, .155, .08, .72, .86],
		[4.05, .125, .062, .92, 1.18],
		[5.15, .145, .072, .8, 1.0],
	];
	// Contour d'un limbe large : pétiole étroit, largeur maximale passé la moitié, bout arrondi. Le limbe est creusé (bords relevés
	// vers la pomme) et ses bords ondulent, comme le limbe gaufré du pë-tsaï ; chaque face garde sa propre lumière pour que le pli de
	// la nervure se voie.
	const stations = [[0, .25], [.5, .95], [.85, .75]];
	const blades = [];
	fronds.forEach(([azimuth, length, width, rise, open], f) => {
		const angle = u => rise + (open - rise) * u * u;
		const steps = 12, foot = radiusAt(.01) - .014, spine = [[foot, .004]];
		for(let s = 1; s <= steps; s++) {
			const [x, y] = spine[s - 1], a = angle((s - .5) / steps);
			spine.push([x + Math.cos(a) * length / steps, y + Math.sin(a) * length / steps]);
		}
		const along = u => {
			const [x, y] = spine[Math.round(u * steps)], a = angle(u);
			return { x, y, nx: -Math.sin(a), ny: Math.cos(a) };
		};
		const cross = ([u, w], s) => {
			const { x, y, nx, ny } = along(u), half = width * w, lift = .35 * half;
			const wave = .012 * Math.sin(s * 2.1 + f);
			return [[x + nx * lift, y + ny * lift + wave, -half], [x, y, 0], [x + nx * lift, y + ny * lift - wave, half]];
		};
		const rows = stations.map(cross), tip = along(1);
		const tris = [];
		for(let s = 0; s < rows.length - 1; s++) {
			const [a0, b0, c0] = rows[s], [a1, b1, c1] = rows[s + 1];
			tris.push([a0, a1, b0], [b0, a1, b1], [b0, b1, c0], [c0, b1, c1]);
		}
		const [aN, bN, cN] = rows[rows.length - 1], end = [tip.x, tip.y, 0];
		tris.push([aN, end, bN], [bN, end, cN]);
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		blades.push(g.rotateY(azimuth));
	});
	const leaves = new THREE.BufferGeometry();
	leaves.setAttribute('position', new THREE.Float32BufferAttribute(blades.flatMap(g => [...g.attributes.position.array]), 3));
	leaves.setAttribute('normal', new THREE.Float32BufferAttribute(blades.flatMap(g => [...g.attributes.normal.array]), 3));

	const parts = [
		{ color: LEAF, sway: .5, geometry: leaves },
		{ color: CREAM, sway: 0, geometry: geometryOf(headParts.get(CREAM)) },
		{ color: PALE, sway: 0, geometry: geometryOf(headParts.get(PALE)) },
		{ color: HEART, sway: 0, geometry: geometryOf(headParts.get(HEART)) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .3;
	return { d, h: Math.round(d * size.y / Math.max(size.x, size.z) * 1000) / 1000, parts };
}
