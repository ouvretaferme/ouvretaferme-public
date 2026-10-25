// Chou-rave, pensé pour se lire à une vingtaine de pixels : une boule vert pâle posée au-dessus du sol sur un pied court,
// et sur ses flancs, à des hauteurs différentes, de longs pétioles nus qui partent presque à l'horizontale puis se
// redressent, chacun portant une feuille ovale vert bleuté. Le navet et le céleri-rave portent leurs feuilles au sommet
// seulement : ici les pétioles hérissent la boule de tous côtés, et le plus fort contraste est entre la boule et le limbe.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const BULB = '#c9dc94'; const STALK = '#a9c977'; const LEAF = '#46776d';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Tour arrondi : profil [[rayon, y], …] du haut vers le bas, rayon 0 pour un pôle. Chaque sommet porte la normale moyenne
	// des faces qui l'entourent, calculée sur le tour entier, pour que la boule se lise ronde et non taillée.
	const roundLathe = (profile, sides, phase = 0) => {
		const key = (k, i) => profile[k][0] === 0 ? `${k}` : `${k}:${(i + sides) % sides}`;
		const at = (k, i) => {
			const [r, y] = profile[k], a = phase + i * Math.PI * 2 / sides;
			return new THREE.Vector3(r * Math.cos(a), y, r * Math.sin(a));
		};
		const faces = [];
		for(let k = 0; k < profile.length - 1; k++) {
			for(let i = 0; i < sides; i++) {
				if(profile[k][0] > 0) faces.push([[k, i], [k + 1, i], [k, i + 1]]);
				if(profile[k + 1][0] > 0) faces.push([[k, i + 1], [k + 1, i], [k + 1, i + 1]]);
			}
		}
		const normals = new Map();
		for(const corners of faces) {
			const [p, q, r] = corners.map(c => at(...c));
			const n = q.sub(p).cross(r.sub(p));
			for(const c of corners) normals.set(key(...c), (normals.get(key(...c)) ?? new THREE.Vector3()).add(n));
		}
		const position = [], normal = [];
		for(const corners of faces) {
			for(const c of corners) {
				position.push(...at(...c).toArray());
				normal.push(...normals.get(key(...c)).clone().normalize().toArray());
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		return g;
	};

	// Boule : tour à sept pans, un peu plus large que haute, qui se resserre en pied court au ras du sol. Le pied est ouvert :
	// le socle le pose à y = 0 et personne ne voit le dessous.
	const profile = [[0, .136], [.058, .116], [.07, .06], [.036, 0]];
	const bulb = roundLathe(profile, 7, .2);
	// Rayon de la boule à la hauteur y, pour planter chaque pétiole sur son flanc
	const radiusAt = y => {
		for(let k = 0; k < profile.length - 1; k++) {
			const [r0, y0] = profile[k], [r1, y1] = profile[k + 1];
			if(y <= y0 && y >= y1) return r0 + (r1 - r0) * (y0 - y) / (y0 - y1);
		}
		return 0;
	};

	// Feuilles : cinq, quatre plantées sur les flancs à des hauteurs différentes et une au sommet, à des azimuts inégaux.
	// Chacune varie en hauteur de départ, portée du pétiole, montée, inclinaison du limbe, longueur, largeur et retombée.
	// [azimut, départ, portée, montée, inclinaison, longueur, largeur, retombée]
	const fronds = [
		[.3, .07, .15, .075, .5, .16, .072, .35],
		[1.6, .098, .125, .09, .34, .15, .066, .4],
		[2.85, .046, .16, .06, .62, .14, .074, .3],
		[4.1, .084, .14, .08, .44, .165, .078, .25],
		[5.3, .124, .07, .1, .22, .15, .064, .3],
	];
	// Demi-contour d'un limbe ovale, en (avancée, écart) : large au tiers, arrondi au bout. Chaque demi-feuille déplace ses
	// points d'après son rang et relève ou creuse son bord, ce qui casse la lumière comme un limbe ondulé.
	const outline = [[.18, .78], [.48, 1], [.78, .78]];
	const shift = [[.04, -.06, .03], [-.03, .05, -.05], [.02, .03, .06]];
	const crinkle = [[.35, -.3, .4], [-.4, .35, -.2], [.3, .25, -.4]];
	const stalks = [], blades = [];
	for(const [rank, [azimuth, start, reach, rise, lean, length, width, droop]] of fronds.entries()) {
		// Pétiole : du flanc presque à l'horizontale jusqu'au coude, puis redressé jusqu'à la base du limbe
		const foot = new THREE.Vector3(radiusAt(start) * .85, start, 0);
		const knee = new THREE.Vector3(reach, start + rise * .2, 0);
		const base = new THREE.Vector3(reach + .012, start + rise, 0);
		const section = (center, direction, r) => {
			const n = new THREE.Vector3(-direction.y, direction.x, 0).normalize();
			return [0, 1, 2].map(j => {
				const a = j * Math.PI * 2 / 3;
				return center.clone().addScaledVector(n, r * Math.cos(a)).add(new THREE.Vector3(0, 0, r * Math.sin(a))).toArray();
			});
		};
		const d1 = knee.clone().sub(foot).normalize(), d2 = base.clone().sub(knee).normalize();
		const ringFoot = section(foot, d1, .017), ringKnee = section(knee, d1.clone().add(d2), .012), tip = base.toArray();
		const tube = [];
		for(let j = 0; j < 3; j++) {
			const k = (j + 1) % 3;
			tube.push([ringFoot[j], ringKnee[j], ringFoot[k]], [ringFoot[k], ringKnee[j], ringKnee[k]], [ringKnee[j], tip, ringKnee[k]]);
		}
		stalks.push(mesh(tube).rotateY(azimuth));

		// Limbe : la nervure part de la base, inclinée vers l'extérieur, et retombe au bout
		const spine = u => {
			const angle = lean + droop * u * u, mid = lean + droop * u * u * .5;
			return [base.x + Math.sin(mid) * u * length, base.y + Math.cos(mid) * u * length, angle];
		};
		// La nervure est relevée, les bords baissés (pli en V), et chaque bord monte ou descend d'un cran
		const point = (u, v, bump = 0) => {
			const [x, y, angle] = spine(u);
			const lift = .3 * width * (1 - Math.abs(v)) + bump * width * .3;
			return [x - Math.cos(angle) * lift, y + Math.sin(angle) * lift, v * width];
		};
		const tris = [];
		for(const side of [-1, 1]) {
			const s = shift[(rank + (side > 0 ? 1 : 0)) % 3], c = crinkle[(rank * 2 + (side > 0 ? 1 : 0)) % 3];
			const [a, b, e] = outline.map(([u, v], k) => point(u + s[k], side * v * (1 + s[(k + 1) % 3]), c[k]));
			const middle = point(.45, 0);
			tris.push([point(0, 0), a, middle], [middle, a, b], [middle, b, e], [middle, e, point(1, 0)]);
		}
		blades.push(mesh(tris).rotateY(azimuth));
	}

	const parts = [
		{ color: LEAF, geometry: helpers.merge(blades) },
		{ color: STALK, geometry: helpers.merge(stalks) },
		{ color: BULB, sway: 0, geometry: bulb },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .35;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
