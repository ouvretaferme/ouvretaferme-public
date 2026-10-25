// Sarrasin en fleur, pensé pour se lire en nappe sur un rang à 20 px/m : trois tiges rouges, fines et dressées, qui fourchent
// à mi-hauteur, quelques feuilles en fer de flèche d'un vert clair espacées le long des tiges, et au bout de chaque axe un
// bouquet lâche et plat de toutes petites fleurs blanc rosé. Le rouge des tiges reste à découvert sur toute la hauteur.
// Ce qui le sépare du coton : aucune boule, chaque bouquet est un semis de fleurettes à plat, avec du jour entre elles.
// De la pomme de terre : pas de masse de feuillage, les feuilles sont peu nombreuses, claires, et ne cachent pas les tiges.
// De l'achillée : les bouquets sont troués, à des hauteurs inégales, et jamais un plateau plein. De la phacélie : rien ne
// s'enroule, rien n'est bleu.
// Fiche : sarrasin.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const FLOWER = '#fbf1ee'; const BUD = '#f0b4c0'; const LEAF = '#86bd52'; const STEM = '#c63f3c';

	// Hasard à graine fixe : chaque tige, chaque feuille et chaque fleurette varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 211;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const toward = (azimuth, lean) => v(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const across = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Le matériau éclaire les deux faces et retourne la normale d'une face vue de dos : chaque triangle est écrit dans le sens
	// de la normale qu'on lui veut, sinon il s'éteint. Sans normalOf, la normale est celle de la face, tournée vers le ciel.
	const mesh = (tris, normalOf) => {
		const position = [], normal = [];
		for(let [p, q, r] of tris) {
			const face = q.clone().sub(p).cross(r.clone().sub(p)).normalize();
			const wanted = normalOf ? normalOf(p).clone().add(normalOf(q)).add(normalOf(r)) : v(0, 1, 0);
			if(face.dot(wanted) < 0) {
				[q, r] = [r, q];
				face.negate();
			}
			for(const c of [p, q, r]) {
				position.push(...c.toArray());
				normal.push(...(normalOf ? normalOf(c) : face).toArray());
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		return g;
	};

	const stems = [], leaves = [], flowers = [], buds = [];

	// Un ruban de tige, à peine effilé : il garde sa largeur jusqu'en haut pour que le rouge se lise sous les fleurs. 2 triangles.
	const ribbon = (foot, tip, azimuth, width) => {
		const low = across(azimuth).multiplyScalar(width / 2), high = low.clone().multiplyScalar(.6);
		const a = foot.clone().add(low), b = foot.clone().sub(low), c = tip.clone().sub(high), d = tip.clone().add(high);
		stems.push([a, b, c], [a, c, d]);
	};
	// Une tige ou un rameau : deux rubans croisés, visibles de partout. 4 triangles.
	const stalk = (foot, tip, width) => {
		const azimuth = between(0, 3.14);
		ribbon(foot, tip, azimuth, width);
		ribbon(foot, tip, azimuth + 1.5708, width);
	};
	// Une feuille en fer de flèche : l'échancrure contre le pétiole, deux oreillettes qui reviennent en arrière, une pointe
	// aiguë, pliée sur sa nervure. 2 triangles.
	const arrow = (origin, azimuth, length, droop) => {
		const out = toward(azimuth, 1.5708 + droop * .4), side = across(azimuth), width = length * between(.52, .6);
		const notch = origin.clone().addScaledVector(out, length * .3).add(v(0, length * .08, 0));
		const tip = origin.clone().addScaledVector(toward(azimuth, 1.5708 + droop), length * 1.15);
		const ear = turn => origin.clone().addScaledVector(side, turn * width * between(.9, 1.1)).addScaledVector(out, -length * .06).add(v(0, -length * .1, 0));
		leaves.push([notch, ear(-1), tip], [notch, tip, ear(1)]);
	};
	// Un bouquet : des fleurettes séparées, semées à plat autour du bout de l'axe, à des hauteurs à peine inégales. Il reste du
	// jour entre elles, le bouquet ne fait jamais une tête. La première tient au bout de la tige. 1 triangle par fleurette.
	const corymb = (center, count, radius, pinks) => {
		const start = between(0, 6.28);
		for(let i = 0; i < count; i++) {
			const a = start + i * 6.2832 / (count - 1) + between(-.35, .35), reach = i === 0 ? 0 : radius * between(.7, 1.15);
			const heart = center.clone().add(v(Math.cos(a) * reach, i === 0 ? .004 : between(-.012, .014), Math.sin(a) * reach));
			const turn = between(0, 6.28), size = radius * between(.44, .58);
			const petal = k => heart.clone().add(v(Math.cos(turn + k * 2.094) * size * between(.8, 1.2), between(-.004, .004), Math.sin(turn + k * 2.094) * size * between(.8, 1.2)));
			(i > 0 && i <= pinks ? buds : flowers).push([petal(0), petal(1), petal(2)]);
		}
	};

	// Trois tiges serrées au pied, qui s'écartent peu : la nappe est un semis, la touffe en porte trois brins
	// [azimut, écart à la verticale, hauteur, rameaux : [part de la tige, côté, écart, longueur]]
	const plants = [
		[.5, .2, .45, [[.38, 1.3, .68, .2], [.62, -1.6, .58, .14]]],
		[2.7, .32, .4, [[.34, 1.5, .72, .18], [.6, -1.2, .55, .13]]],
		[4.6, .27, .36, [[.4, -1.4, .7, .17], [.66, 1.7, .6, .11]]],
	];
	plants.forEach(([azimuth, lean, height, shoots], i) => {
		const foot = toward(azimuth, 1.5708).multiplyScalar(.022);
		const top = foot.clone().addScaledVector(toward(azimuth + between(-.3, .3), lean), height);
		stalk(foot, top, .015);
		corymb(top, 9, .056, 2);
		shoots.forEach(([part, turn, spread, length], j) => {
			const from = foot.clone().lerp(top, part), a = azimuth + turn + between(-.2, .2);
			const tip = from.clone().addScaledVector(toward(a, spread * between(.9, 1.1)), length * between(.92, 1.08));
			// Un rameau ne porte qu'un ruban, tourné en travers de sa course pour s'offrir à la vue de dessus comme de côté
			ribbon(from, tip, a + 1.5708 * between(.35, .65), .015);
			corymb(tip, 8, .05, j === 0 ? 3 : 1);
			// Une petite feuille sessile sous un rameau sur deux
			if((i + j) % 2 === 0) {
				arrow(from.clone().lerp(tip, .3), a + between(1.2, 1.8) * (j ? 1 : -1), .07 * between(.9, 1.1), between(.2, .45));
			}
		});
		// Deux feuilles par tige, alternes : la plus basse, la plus grande, tenue à l'écart par son pétiole
		const low = foot.clone().lerp(top, between(.16, .28)), away = azimuth + between(-.5, .5);
		const hold = low.clone().addScaledVector(toward(away, 1.25), .045);
		stems.push([low.clone().add(v(0, .008, 0)), low.clone().add(v(0, -.008, 0)), hold]);
		arrow(hold, away, .1 * between(.9, 1.1), between(.25, .45));
		arrow(foot.clone().lerp(top, between(.48, .58)), azimuth + 3.14 + between(-.6, .6), .085 * between(.88, 1.1), between(.15, .4));
	});

	// Tiges 3 × 4 + rameaux 6 × 2 + pétioles 3 = 27, feuilles 9 × 2 = 18, fleurettes 3 × 9 + 6 × 8 = 75 : 120 triangles.
	const parts = [
		{ color: FLOWER, geometry: mesh(flowers) },
		{ color: BUD, geometry: mesh(buds) },
		{ color: LEAF, sway: .8, geometry: mesh(leaves) },
		{ color: STEM, sway: .6, geometry: mesh(stems, c => v(c.x, 0, c.z).add(v(.01, .15, .01)).normalize()) },
	];
	return { d: .3, h: .45, parts };
}
