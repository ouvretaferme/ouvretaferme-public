// Tournesol, pensé pour se lire sur un rang à 20 px/m : une haute tige unique et droite qui se courbe au sommet, et au bout
// un gros capitule tourné vers l'avant et vers le ciel, couronne de ligules jaune vif autour d'un disque brun très sombre.
// Le long de la tige, de grandes feuilles en cœur, alternes, qui pendent et rapetissent en montant. Le capitule fait partie
// du plant, comme les épis du maïs : le socle n'y pose aucun fruit. Le plus fort contraste est entre le disque et ses ligules.
// Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const RAY = '#f6c21c'; const DISK = '#4a2a12'; const LEAF = '#5d8f2e'; const STEM = '#7f9f3c';

	const add = (a, b, k = 1) => a.map((v, i) => v + b[i] * k);

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	// Géométrie indexée : les sommets partagés gardent une normale lissée, pour qu'un volume rond le reste à l'écran
	const smooth = (points, faces) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(points.flat(), 3));
		g.setIndex(faces.flat());
		g.computeVertexNormals();
		return g;
	};

	// Tige : un tube à cinq pans le long d'un chemin droit qui se courbe vers l'avant sous le capitule
	const path = [[0, 0, 0], [0, .9, 0], [.01, 1.6, 0], [.05, 1.8, 0]];
	const radii = [.026, .021, .018, .016];
	const SIDES = 5;
	const stemRings = path.map((p, k) => {
		const next = path[Math.min(k + 1, path.length - 1)], prev = path[Math.max(k - 1, 0)];
		const tx = next[0] - prev[0], ty = next[1] - prev[1], n = Math.hypot(tx, ty);
		const side = [ty / n, -tx / n, 0];
		return Array.from({ length: SIDES }, (_, i) => {
			const a = i * Math.PI * 2 / SIDES;
			return add(p, add([0, 0, Math.sin(a) * radii[k]], side, Math.cos(a) * radii[k]));
		});
	});
	const stemFaces = [];
	for(let k = 0; k < path.length - 1; k++) {
		for(let i = 0; i < SIDES; i++) {
			const j = (i + 1) % SIDES, A = k * SIDES + i, B = k * SIDES + j, C = A + SIDES, D = B + SIDES;
			stemFaces.push([A, B, C], [B, D, C]);
		}
	}
	const stalk = smooth(stemRings.flat(), stemFaces);

	// Capitule, modelé à plat face vers le ciel puis basculé : sa face regarde l'avant, 74° au-dessus de l'horizon, pour que la vue plongeante du jeu la voie de face quel que soit l'azimut du plant
	const DISK_R = .13;
	const SEG = 16;
	// Disque : dôme bas à deux anneaux, lissé, peint d'un seul brun
	const diskPoints = [[0, .048, 0]];
	for(const [r, y] of [[DISK_R * .55, .036], [DISK_R, .004]]) {
		for(let i = 0; i < SEG; i++) {
			const a = i * Math.PI * 2 / SEG;
			diskPoints.push([r * Math.cos(a), y, r * Math.sin(a)]);
		}
	}
	const diskFaces = [];
	for(let i = 0; i < SEG; i++) {
		const j = (i + 1) % SEG;
		diskFaces.push([0, 1 + j, 1 + i], [1 + i, 1 + j, 1 + SEG + i], [1 + j, 1 + SEG + j, 1 + SEG + i]);
	}
	const disk = smooth(diskPoints, diskFaces);

	// Ligules : vingt-six, chacune en lame pointue à trois triangles, qui varient en longueur, en largeur et en cambrure
	const RAYS = 26;
	const rays = [];
	for(let i = 0; i < RAYS; i++) {
		const a = (i + .3 * Math.sin(i * 2.7)) * Math.PI * 2 / RAYS;
		const length = .14 * (1 + .16 * Math.sin(i * 1.9 + .4));
		const width = .025 * (1 + .18 * Math.cos(i * 2.3));
		const curl = .022 * Math.sin(i * 3.1 + 1);
		const dir = [Math.cos(a), 0, Math.sin(a)], perp = [-Math.sin(a), 0, Math.cos(a)];
		const r0 = DISK_R * .92, base = Math.sin(Math.PI / RAYS) * r0 * 1.05;
		const at = (r, y) => [dir[0] * r, y, dir[2] * r];
		const BL = add(at(r0, 0), perp, -base), BR = add(at(r0, 0), perp, base);
		const ML = add(at(r0 + length * .5, .009), perp, -width), MR = add(at(r0 + length * .5, .009), perp, width);
		const T = at(r0 + length, curl);
		rays.push([BL, BR, MR], [BL, MR, ML], [ML, MR, T]);
	}
	const crown = mesh(rays);

	// Dos du capitule : une coupe verte qui le relie à la tige
	const back = [];
	for(let i = 0; i < 10; i++) {
		const a0 = i * Math.PI * 2 / 10, a1 = (i + 1) * Math.PI * 2 / 10, r = DISK_R * 1.02;
		back.push([[0, -.08, 0], [r * Math.cos(a0), -.004, r * Math.sin(a0)], [r * Math.cos(a1), -.004, r * Math.sin(a1)]]);
	}
	const cup = mesh(back);

	const neck = path[path.length - 1];
	const tilt = 16 * Math.PI / 180;
	const normal = [Math.sin(tilt), Math.cos(tilt), 0];
	const center = add(neck, normal, .072);
	const place = g => g.rotateZ(-tilt).translate(...center);

	// Feuilles : neuf, alternes en spirale. Les basses sont grandes et pendent, les hautes plus petites et plus dressées.
	// [hauteur, azimut, pétiole, longueur du limbe, demi-largeur, ouverture du pétiole, retombée du limbe]
	const leaves = [
		[.3, 0, .11, .3, .17, .9, 1.3],
		[.46, 2.5, .12, .31, .18, .8, 1.4],
		[.62, 4.95, .12, .3, .17, .85, 1.25],
		[.78, 1.15, .12, .28, .16, .75, 1.3],
		[.93, 3.7, .11, .26, .15, .7, 1.15],
		[1.08, 6.05, .1, .23, .13, .65, 1.05],
		[1.22, 2.3, .09, .2, .115, .6, .95],
		[1.36, 4.8, .07, .16, .09, .5, .85],
		[1.48, .9, .05, .12, .07, .45, .75],
	];
	// Contour d'une moitié de limbe en cœur, en (avancée, écart) : l'oreillette passe derrière le point d'attache, ce qui
	// creuse le sinus du cœur, puis le bord s'élargit et file vers la pointe.
	const outline = [[-.16, .62], [.27, 1], [.64, .66]];
	const blades = [], petioles = [];
	for(const [rank, [y, azimuth, petiole, length, half, open, droop]] of leaves.entries()) {
		const foot = [.016, y, 0];
		const tip = add(foot, [Math.sin(open), Math.cos(open), 0], petiole);
		// Limbe : la nervure part dans l'axe du pétiole couché davantage, et retombe vers la pointe
		const start = open + .5;
		const angle = u => start + droop * u;
		const rib = u => {
			const steps = 6;
			let p = tip;
			for(let s = 0; s < steps; s++) {
				const m = angle(u * (s + .5) / steps);
				p = add(p, [Math.sin(m), Math.cos(m), 0], u * length / steps);
			}
			return p;
		};
		// Point du limbe : la nervure est relevée, les bords baissés, pour un pli en V
		const point = (u, v) => {
			const m = angle(Math.max(u, 0)), base = u < 0 ? add(tip, [Math.sin(start), Math.cos(start), 0], u * length) : rib(u);
			const lift = .3 * half * (1 - Math.abs(v));
			return [base[0] - Math.cos(m) * lift, base[1] + Math.sin(m) * lift, v * half];
		};
		const tris = [];
		for(const side of [-1, 1]) {
			const skew = 1 + .12 * Math.sin(rank * 1.7 + side);
			const [lobe, wide, shoulder] = outline.map(([u, v]) => point(u, side * v * skew));
			tris.push(
				[point(0, 0), lobe, wide],
				[point(0, 0), wide, point(.35, 0)],
				[point(.35, 0), wide, shoulder],
				[point(.35, 0), shoulder, point(.72, 0)],
				[point(.72, 0), shoulder, point(1, 0)],
			);
		}
		blades.push(mesh(tris).rotateY(azimuth));
		// Pétiole : un ruban étroit, posé à plat
		const w = .006;
		petioles.push(mesh([
			[add(foot, [0, 0, -w]), add(foot, [0, 0, w]), add(tip, [0, 0, w * .6])],
			[add(foot, [0, 0, -w]), add(tip, [0, 0, w * .6]), add(tip, [0, 0, -w * .6])],
		]).rotateY(azimuth));
	}

	const merge = list => helpers.merge(list);
	const parts = [
		{ color: LEAF, geometry: merge(blades) },
		{ color: STEM, sway: .5, geometry: merge([stalk, ...petioles, place(cup)]) },
		{ color: RAY, sway: .4, geometry: place(crown) },
		{ color: DISK, sway: .4, geometry: place(disk) },
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
