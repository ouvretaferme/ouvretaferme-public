// Myrtillier cultivé (Vaccinium corymbosum), pied adulte feuillé, pensé pour se lire sur un rang à 20 px/m : un arbuste en vase,
// six cannes de bois gris-brun qui sortent du sol en gerbe, nues sur le premier tiers, et une couronne arrondie de touffes de petites
// feuilles pointues vert sombre bleuté, portées par des rameaux de l'année rouge brique.
// Le pied ligneux visible sous la couronne le sépare de la boule de feuillage posée au sol (groseillier, buisson générique) ; les
// cannes multiples, sans tronc, de l'arbre fruitier ; le vase plus haut que large et le feuillage sombre, du groseillier et du cassis.
// Les baies bleu pruiné sont posées par le socle (FRUITS), au pourtour haut de la couronne.
// Fiche : myrtille.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 300 triangles).
export function build(THREE) {
	const DARK = '#2e5440'; const LIGHT = '#5f8a5c'; const WOOD = '#6f5f52'; const TWIG = '#a5483a';
	const groups = Object.fromEntries([DARK, LIGHT, WOOD, TWIG].map(color => [color, { position: [], normal: [] }]));
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const triangle = (color, a, b, c) => {
		const n = new THREE.Vector3().subVectors(b, a).cross(new THREE.Vector3().subVectors(c, a)).normalize();
		for(const q of [a, b, c]) {
			groups[color].position.push(q.x, q.y, q.z);
			groups[color].normal.push(n.x, n.y, n.z);
		}
	};
	const direction = (tilt, azimuth) => v(Math.sin(tilt) * Math.cos(azimuth), Math.cos(tilt), Math.sin(tilt) * Math.sin(azimuth));
	const frame = axis => {
		const u = new THREE.Vector3().crossVectors(axis, Math.abs(axis.y) > .95 ? v(1, 0, 0) : v(0, 1, 0)).normalize();
		return [u, new THREE.Vector3().crossVectors(u, axis).normalize()];
	};
	// Graine fixe : le pied est le même à chaque chargement, le socle le tourne au hasard
	let seed = 7;
	const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;

	// Une canne : un tube à trois pans en deux segments, qui s'effile et s'ouvre en montant, 12 triangles
	const tube = (color, points, radii, phase) => {
		const rings = points.map((p, k) => {
			const axis = (points[k + 1] ?? p).clone().sub(points[k - 1] ?? p).normalize();
			const [u, w] = frame(axis);
			return [0, 1, 2].map(i => p.clone().addScaledVector(u, radii[k] * Math.cos(phase + i * 2.094)).addScaledVector(w, radii[k] * Math.sin(phase + i * 2.094)));
		});
		for(let k = 0; k < rings.length - 1; k++) {
			for(let i = 0; i < 3; i++) {
				const j = (i + 1) % 3;
				triangle(color, rings[k][i], rings[k][j], rings[k + 1][j]);
				triangle(color, rings[k][i], rings[k + 1][j], rings[k + 1][i]);
			}
		}
	};
	// Un rameau de l'année : une pyramide à trois pans sans fond, 3 triangles
	const twig = (foot, head, radius, phase) => {
		const [u, w] = frame(head.clone().sub(foot).normalize());
		const corners = [0, 1, 2].map(i => foot.clone().addScaledVector(u, radius * Math.cos(phase + i * 2.094)).addScaledVector(w, radius * Math.sin(phase + i * 2.094)));
		for(let i = 0; i < 3; i++) triangle(TWIG, corners[i], corners[(i + 1) % 3], head);
	};
	// Une feuille elliptique pointue : six sommets le long d'une nervure qui se courbe, 4 triangles, le contour s'élargit au tiers
	// puis s'effile en pointe ; le bout retombe un peu
	const leaf = (color, base, axis, side, length, width, droop) => {
		const n = new THREE.Vector3().crossVectors(axis, side).normalize();
		const along = (t, s, d) => base.clone().addScaledVector(axis, length * t).addScaledVector(side, width * s).addScaledVector(n, d);
		const l1 = along(.3, .5, 0), r1 = along(.3, -.5, 0), l2 = along(.66, .42, droop * .4), r2 = along(.66, -.42, droop * .4), tip = along(1, 0, droop);
		triangle(color, base, l1, r1);
		triangle(color, l1, l2, r1);
		triangle(color, r1, l2, r2);
		triangle(color, l2, tip, r2);
	};
	// Une pousse de l'année : un rameau rouge qui se courbe vers le dehors, et des feuilles alternes le long de lui, presque à plat,
	// écartées de l'axe vers ses deux flancs, plus une feuille terminale. Les feuilles sont grossies trois fois : une à une elles ne se
	// liraient pas, ensemble elles font la masse de la couronne. Celles qui regardent le ciel sont éclairées
	const shoot = (base, tilt, azimuth, length, count, size) => {
		const bend = .3;
		const middle = base.clone().addScaledVector(direction(tilt, azimuth), length * .5);
		const tip = middle.clone().addScaledVector(direction(tilt + bend, azimuth), length * .5);
		twig(base, tip, .022, azimuth);
		const flank = v(-Math.sin(azimuth), 0, Math.cos(azimuth));
		for(let k = 0; k < count; k++) {
			const t = (k + 1) / count;
			const at = t < .5 ? base.clone().lerp(middle, t * 2) : middle.clone().lerp(tip, t * 2 - 1);
			const axis = direction(tilt + bend * t, azimuth);
			const last = k === count - 1;
			const sign = k % 2 ? 1 : -1;
			// Les feuilles s'étalent presque à plat, vers le flanc et le dehors : debout le long du rameau, elles se lisaient en lames
			const radial = v(Math.cos(azimuth), 0, Math.sin(azimuth));
			const out = last
				? axis.clone().setY(Math.min(axis.y, .45)).normalize()
				: radial.clone().multiplyScalar(.55).addScaledVector(flank, sign * (.85 + rnd() * .3)).add(v(0, .1 + rnd() * .3, 0)).normalize();
			const side = new THREE.Vector3().crossVectors(out, v(0, 1, 0)).normalize();
			// La feuille vrille un peu sur sa nervure : pas deux plans identiques d'une pousse à l'autre
			side.applyAxisAngle(out, (rnd() - .5) * .6);
			const length = size * (.85 + rnd() * .3) * (last ? 1.1 : 1);
			const n = new THREE.Vector3().crossVectors(side, out);
			leaf(Math.abs(n.y) > .85 && at.y > .75 ? LIGHT : DARK, at, out, side, length, length * .6, -length * .18);
		}
	};

	// Sept cannes d'azimut, d'ouverture et de longueur irréguliers, nues sur le premier tiers de la hauteur, la plus droite au centre.
	// Chacune se divise en deux pousses : l'une s'arque vers le dehors et arrondit le flanc de la couronne, l'autre monte et en ferme le dessus
	const canes = [
		{ azimuth: .2, tilt: .5, length: .62 },
		{ azimuth: 1.15, tilt: .58, length: .55 },
		{ azimuth: 2.1, tilt: .46, length: .64 },
		{ azimuth: 3.05, tilt: .6, length: .52 },
		{ azimuth: 4.1, tilt: .52, length: .6 },
		{ azimuth: 5.15, tilt: .44, length: .66 },
		{ azimuth: 2.7, tilt: .1, length: .78 },
	];
	canes.forEach(({ azimuth, tilt, length }, i) => {
		const foot = v(.06 * Math.cos(azimuth), 0, .06 * Math.sin(azimuth));
		const head = foot.clone().addScaledVector(direction(tilt, azimuth), length);
		tube(WOOD, [foot, head], [.045, .022], azimuth);
		const center = i === canes.length - 1;
		shoot(head, center ? .5 : tilt + .45 + rnd() * .15, azimuth + (i % 2 ? .4 : -.4), .46 + rnd() * .08, center ? 3 : 4, .34);
		shoot(head, center ? .2 : .12 + rnd() * .12, azimuth + (i % 2 ? -.7 : .7) + (center ? 3.1 : 0), .4 + rnd() * .08, center ? 3 : 4, .32);
	});

	const part = (color, sway) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(groups[color].position, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(groups[color].normal, 3));
		return { geometry, color, sway };
	};
	// Le bois ne bouge pas au vent, les rameaux et les feuilles un peu
	const parts = [part(WOOD, 0), part(TWIG, .2), part(DARK, .35), part(LIGHT, .35)];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const { geometry } of parts) {
		geometry.computeBoundingBox();
		box.union(geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = 1.2;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
