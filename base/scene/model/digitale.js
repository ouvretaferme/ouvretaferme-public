// Digitale pourpre de deuxième année, en fleur, plantée en ligne ou isolée, pensée pour se lire sur un rang à 20 px/m : une
// rosette plate de grandes feuilles entières, ovales et pointues, vert gris, d'où monte une hampe unique presque nue, garnie
// d'un seul côté de clochettes tubulaires roses pendantes, et terminée par une pointe de boutons pourpres.
// Ce qui la sépare du lupin : une seule hampe, des cloches qui tombent d'un seul côté et laissent voir la tige, et non un cône
// plein de fleurs serrées tout autour ; des feuilles pleines couchées en rosette, et non des étoiles palmées. Du delphinium :
// rose et non bleu, des tubes pendants et non des étoiles ouvertes, une grappe dissymétrique, une rosette et non un dôme de
// lanières.
// Fiche : digitale.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE) {
	const BELL = '#d0609f'; const BUD = '#7c2a63'; const STEM = '#7f9c5a'; const ROSETTE = '#4a6a3e';

	// Hasard à graine fixe : chaque cloche et chaque feuille varie, et le pied reste le même d'un chargement à l'autre
	let seed = 47;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const outwardOf = azimuth => v(Math.cos(azimuth), 0, Math.sin(azimuth));
	const sideOf = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));
	const faceNormal = tri => {
		const n = tri[1].clone().sub(tri[0]).cross(tri[2].clone().sub(tri[0])).normalize();
		return n.y < 0 ? n.negate() : n;
	};

	// Une géométrie depuis des triangles [a, b, c], chacun avec ses trois normales (la normale du triangle par défaut)
	const geometry = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flatMap(t => t.points).flatMap(p => p.toArray()), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flatMap(t => t.normals ?? Array(3).fill(faceNormal(t.points))).flatMap(n => n.toArray()), 3));
		return g;
	};
	const flatTri = (a, b, c) => ({ points: [a, b, c] });

	const bells = [], buds = [], stem = [], rosette = [];

	// Une clochette : tube ouvert à trois pans, étroit à l'attache, évasé à la bouche, pendant vers l'extérieur. Les normales
	// partent de l'axe du tube, partagées par sommet, pour que trois pans se lisent en cylindre et non en prisme. 6 triangles.
	const bell = (attach, azimuth, droop, length, mouth) => {
		const axis = outwardOf(azimuth).multiplyScalar(Math.cos(droop)).setY(-Math.sin(droop));
		const u = sideOf(azimuth).normalize(), w = axis.clone().cross(u).normalize();
		const turn = between(0, Math.PI * 2);
		const ring = (center, radius) => [0, 1, 2].map(k => {
			const angle = turn + k * Math.PI * 2 / 3;
			const radial = u.clone().multiplyScalar(Math.cos(angle)).addScaledVector(w, Math.sin(angle));
			return { p: center.clone().addScaledVector(radial, radius), n: radial };
		});
		const neck = ring(attach, mouth * .3), lip = ring(attach.clone().addScaledVector(axis, length), mouth);
		for(let k = 0; k < 3; k++) {
			const a = neck[k], b = neck[(k + 1) % 3], c = lip[k], d = lip[(k + 1) % 3];
			bells.push({ points: [a.p, c.p, b.p], normals: [a.n, c.n, b.n] }, { points: [b.p, c.p, d.p], normals: [b.n, c.n, d.n] });
		}
	};

	// La hampe, droite, à peine penchée : une lame doublée en croix pour se voir de tous les côtés. 2 triangles.
	const facing = between(0, Math.PI * 2);
	const lean = outwardOf(facing + Math.PI).multiplyScalar(.04);
	const axisAt = y => v(lean.x * y, y, lean.z * y);
	const top = 1.2;
	const s1 = sideOf(facing).multiplyScalar(.01), s2 = outwardOf(facing).multiplyScalar(.01);
	stem.push(flatTri(v(0, .06, 0).add(s1), v(0, .06, 0).sub(s1), axisAt(top)), flatTri(v(0, .06, 0).add(s2), v(0, .06, 0).sub(s2), axisAt(top)));

	// La grappe : dix cloches d'un seul côté, dans un secteur de 80° autour de `facing`, alternées sur deux rangs décalés,
	// grandes et espacées en bas, plus courtes et serrées en haut. Les cloches sont grossies deux fois et demie (12 cm) pour
	// rester des points roses séparés à 20 px/m, et pendent loin de la tige pour que la grappe se lise d'un seul côté. 60 triangles.
	const count = 10;
	for(let i = 0; i < count; i++) {
		const k = i / (count - 1);
		const y = .54 + .5 * (1 - Math.pow(1 - k, 1.3)) + between(-.012, .012);
		const azimuth = facing + (i % 2 ? .3 : -.3) + between(-.15, .15);
		const size = (1.05 - k * .35) * between(.9, 1.1);
		bell(axisAt(y).addScaledVector(outwardOf(azimuth), .035), azimuth, between(.85, 1.15), .12 * size, .04 * size);
	}

	// La pointe en boutons : une flèche à trois pans au-dessus de la dernière cloche, et deux boutons fermés penchés du même
	// côté que les cloches. 3 + 2 × 3 triangles.
	const tip = axisAt(top + .02), twist = between(0, Math.PI * 2);
	const ring = [0, 1, 2].map(k => axisAt(1.07).addScaledVector(outwardOf(twist + k * Math.PI * 2 / 3), .022));
	for(let k = 0; k < 3; k++) buds.push(flatTri(ring[k], tip, ring[(k + 1) % 3]));
	for(const [y, side] of [[1.09, .5], [1.13, -.4]]) {
		const azimuth = facing + side, base = axisAt(y);
		const end = base.clone().addScaledVector(outwardOf(azimuth), .05).setY(y - .02);
		const r = sideOf(azimuth).multiplyScalar(.014);
		const up = v(0, .016, 0);
		const a = base.clone().add(r), b = base.clone().sub(r), c = base.clone().add(up);
		buds.push(flatTri(a, end, c), flatTri(c, end, b), flatTri(a, b, end));
	}

	// Une feuille entière : ovale, pétiole ailé à la base, plus large vers le milieu, la pointe courte, la nervure médiane creusée
	// en gouttière pour que la feuille se lise pleine sous la lumière. Elle part du collet, presque couchée, et retombe un peu au
	// bout. 8 triangles.
	const leaf = (list, origin, azimuth, length, width, rise, droop) => {
		const dir = outwardOf(azimuth), side = sideOf(azimuth);
		const at = (t, s, fold = 0) => origin.clone().addScaledVector(dir, length * t).addScaledVector(side, width * s)
			.setY(origin.y + length * (rise * t - droop * t * t) - fold);
		const m0 = at(0, 0), m1 = at(.42, 0, width * .12), m2 = at(.74, 0, width * .08), tip = at(1, between(-.05, .05));
		const l1 = at(.42, .5 * between(.92, 1.08)), r1 = at(.42, -.5 * between(.92, 1.08));
		const l2 = at(.76, .4 * between(.9, 1.1)), r2 = at(.76, -.4 * between(.9, 1.1));
		list.push(flatTri(m0, l1, m1), flatTri(m0, m1, r1), flatTri(m1, l1, l2), flatTri(m1, l2, m2),
			flatTri(m2, l2, tip), flatTri(m2, tip, r2), flatTri(m1, m2, r2), flatTri(m1, r2, r1));
	};
	// Six feuilles de rosette sur un angle d'or bousculé, larges et couchées, qui se recouvrent en plateau : la rosette pleine
	// et non l'étoile de folioles du lupin. Les intérieures, plus courtes, se dressent un peu. 48 triangles.
	for(let i = 0; i < 6; i++) {
		const length = (.27 - i * .01) * between(.9, 1.06);
		leaf(rosette, v(0, .03 + i * .006, 0), facing + .9 + i * 2.39996 + between(-.25, .25), length, length * between(.5, .58), .35 + i * .06, .5);
	}

	const parts = [
		{ color: ROSETTE, sway: .2, geometry: geometry(rosette) },
		{ color: STEM, sway: .7, geometry: geometry(stem) },
		{ color: BELL, sway: .8, geometry: geometry(bells) },
		{ color: BUD, sway: .8, geometry: geometry(buds) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .45;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
