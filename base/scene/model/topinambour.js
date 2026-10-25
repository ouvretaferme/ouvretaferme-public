// Topinambour, pensé pour se lire sur un rang à 20 px/m : une colonne haute et étroite de trois tiges serrées, feuillues de bas en
// haut de petites feuilles ovales pointues vert sombre, alternes et étagées, et au sommet une couronne lâche de dix petits capitules
// jaunes, jaunes jusqu'au cœur. Ce qui le sépare du tournesol : plusieurs tiges pleines au lieu d'une tige nue, dix petites fleurs au
// lieu d'un grand disque à cœur brun. Du maïs : des feuilles courtes et ovales, aucun ruban. Le plus fort contraste est entre les
// capitules et le feuillage. Les tubercules sont sous terre toute la saison : le socle les pose à la récolte.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Fiche : topinambour.reference.md.
export function build(THREE, helpers) {
	const LEAF = '#466f2b'; const STEM = '#7c8a3c'; const RAY = '#f4c418'; const DISK = '#d9961a';

	const add = (a, b, k = 1) => a.map((v, i) => v + b[i] * k);
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Variation déterministe : chaque élément répété s'écarte de ±15 % au moins du précédent, sans tirage au hasard
	const vary = (i, k = 1) => Math.sin(i * 12.9898 * k + 78.233) * .5 + Math.sin(i * 4.1 * k + 1.3) * .5;

	// Tiges : trois, serrées au pied, presque droites, de hauteurs différentes. [azimut du pied, hauteur, inclinaison]
	const stalks = [[.3, 2.18, .03], [2.4, 2.3, .02], [4.4, 2.04, .035]].map(([azimuth, height, lean]) => {
		const out = [Math.cos(azimuth), 0, -Math.sin(azimuth)];
		const base = add([0, 0, 0], out, .035);
		const dir = [out[0] * Math.sin(lean), Math.cos(lean), out[2] * Math.sin(lean)];
		return { azimuth, height, out, base, dir, at: y => add(base, dir, y) };
	});

	// Une tige : tube à trois pans d'un seul tronçon, qui s'affine en montant : le feuillage la cache presque entière
	const tube = ({ at, height, out }) => {
		const side = [-out[2], 0, out[0]];
		const ring = (y, r) => [0, 1, 2].map(i => {
			const a = i * Math.PI * 2 / 3;
			return add(add(at(y), out, Math.cos(a) * r), side, Math.sin(a) * r);
		});
		const rings = [ring(0, .022), ring(height, .01)];
		const tris = [];
		for(let k = 0; k < 1; k++) {
			for(let i = 0; i < 3; i++) {
				const j = (i + 1) % 3;
				tris.push([rings[k][i], rings[k][j], rings[k + 1][i]], [rings[k][j], rings[k + 1][j], rings[k + 1][i]]);
			}
		}
		return mesh(tris);
	};

	// Feuille ovale pointue, modelée le long de +x : un pli en V sur la nervure, la pointe qui retombe. Six triangles.
	const leaf = (length, half, droop) => {
		const sag = x => -droop * x * x / length;
		const rib = u => [u * length, sag(u * length) + .18 * half, 0];
		const edge = (u, v) => [u * length, sag(u * length), v * half];
		const tris = [];
		for(const s of [-1, 1]) {
			const s1 = edge(.34, s), s2 = edge(.68, s * .72), tip = edge(1, 0);
			tris.push([[0, 0, 0], s1, rib(.36)], [rib(.36), s1, s2], [rib(.36), s2, tip]);
		}
		return mesh(tris);
	};

	// Feuilles : dix, neuf et neuf par tige, sur toute la hauteur. Les deux du bas sont opposées, les autres alternes en spirale.
	// Les basses sont les plus grandes et les plus couchées, les hautes rapetissent et se redressent.
	const leaves = [];
	const counts = [10, 9, 9];
	stalks.forEach((stalk, s) => {
		const n = counts[s];
		for(let k = 0; k < n; k++) {
			const i = s * 10 + k;
			const t = (k + .5) / n;
			const y = stalk.height * (.06 + .84 * t + .03 * vary(i, 2));
			const length = (.25 - .08 * t) * (1 + .14 * vary(i));
			const azimuth = k < 2 ? stalk.azimuth + 1.6 + k * Math.PI : stalk.azimuth + k * 2.4 + .35 * vary(i, 3);
			const lift = .5 + .3 * t + .12 * vary(i, 5);
			// La feuille roule sur sa nervure : vue de côté, elle montre sa face au lieu de sa tranche
			const roll = (k % 2 ? 1 : -1) * (.35 + .15 * vary(i, 8));
			leaves.push(leaf(length, length * (.47 + .05 * vary(i, 7)), .3 + .1 * vary(i, 4))
				.rotateX(roll)
				.rotateZ(lift)
				.rotateY(azimuth)
				.translate(...stalk.at(y)));
		}
	});

	// Capitules : quatre, trois et trois, en panicule lâche au-dessus du dernier étage de feuilles, chacun sur un pédoncule.
	// Six ligules en pointe autour d'un disque hexagonal d'or, face tournée vers le ciel et l'extérieur. Dix triangles.
	const head = (rays, disk) => {
		const R = .028, ray = j => .054 * (1 + .18 * vary(j * 7 + rays.length, 6));
		const hex = [0, 1, 2, 3, 4, 5].map(i => [Math.cos(i * Math.PI / 3) * R, .006, Math.sin(i * Math.PI / 3) * R]);
		const ring = [0, 1, 2, 3, 4, 5].map(i => [Math.cos(i * Math.PI / 3) * R * .85, 0, Math.sin(i * Math.PI / 3) * R * .85]);
		for(let i = 0; i < 6; i++) {
			const j = (i + 1) % 6, a = (i + .5) * Math.PI / 3, r = R + ray(i);
			rays.push([ring[i], ring[j], [Math.cos(a) * r, .012 * vary(i, 9), Math.sin(a) * r]]);
		}
		disk.push([hex[0], hex[1], hex[2]], [hex[0], hex[2], hex[3]], [hex[0], hex[3], hex[5]], [hex[3], hex[4], hex[5]]);
	};
	const heads = [], peduncles = [];
	const headCounts = [4, 3, 3];
	stalks.forEach((stalk, s) => {
		for(let j = 0; j < headCounts[s]; j++) {
			const i = s * 5 + j;
			const a = stalk.azimuth + j * 2.2 + .4 * vary(i, 11);
			const out = [Math.cos(a), 0, -Math.sin(a)];
			const from = stalk.at(stalk.height - .08 - .1 * j);
			const center = add(add(from, out, .07 + .03 * vary(i, 13) + .02 * j), [0, 1, 0], .13 + .04 * vary(i, 17) - .01 * j);
			const rays = [], disk = [];
			head(rays, disk);
			const tilt = .4 + .12 * vary(i, 19);
			heads.push({ rays: mesh(rays).rotateZ(-tilt).rotateY(a).translate(...center), disk: mesh(disk).rotateZ(-tilt).rotateY(a).translate(...center) });
			const side = [-out[2], 0, out[0]];
			peduncles.push(mesh([[add(from, side, -.008), add(from, side, .008), center]]));
		}
	});

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF, geometry: merge(leaves) },
		{ color: STEM, sway: .5, geometry: merge([...stalks.map(tube), ...peduncles]) },
		{ color: RAY, sway: .6, geometry: merge(heads.map(h => h.rays)) },
		{ color: DISK, sway: .6, geometry: merge(heads.map(h => h.disk)) },
	];
	// Modelé en mètres : l'emprise et la hauteur se lisent sur la forme
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3());
	return { d: Math.max(size.x, size.z), h: size.y, parts };
}
