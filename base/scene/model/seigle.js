// Seigle à l'épiaison, pensé pour se lire sur un rang à 20 px/m : quatre longs chaumes gris bleuté, nus sur presque toute leur
// hauteur, et en tête de chacun un grand épi paille pâle, long fuseau plié sur sa nervure, penché en arc vers le dehors, à deux
// pans pour ses deux rangs de grains, et frangé de barbes fines qui le longent. Le plus fort contraste est entre l'épi blond pâle et le chaume glauque.
// Ce qui le sépare du blé : presque deux fois plus haut, chaume bleuté, épi mince, arqué et barbu et non un bloc doré dressé ;
// de l'orge : des barbes moyennes couchées le long de l'épi et non un pinceau blond qui l'engloutit ; de l'avoine : un épi
// plein, d'une pièce ; d'un roseau : peu de feuilles, toutes en bas, et un épi net au lieu d'un plumeau.
// Fiche : seigle.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles).
export function build(THREE) {
	const LEAF = '#4f8580'; const CULM = '#7ba7a6'; const EAR = '#d7dcae'; const AWN = '#f0e8b4';
	const groups = Object.fromEntries([LEAF, CULM, EAR, AWN].map(color => [color, { position: [], normal: [] }]));
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const outwardOf = azimuth => v(Math.cos(azimuth), 0, Math.sin(azimuth));
	const acrossOf = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));
	const direction = (lean, azimuth) => v(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));

	// Une pièce est plate et vue des deux côtés. Sa normale est celle de sa face, tournée vers `toward`, et le sens de parcours
	// du triangle suit : le matériau à deux faces retourne la normale d'une face vue de dos, une pièce mal enroulée s'éteindrait
	// vue de devant. Elle est ensuite relevée d'une part de verticale, pour qu'une lame presque de chant reste éclairée.
	const push = (color, points, toward, lift = .25) => {
		const [a, b, c] = points;
		const normal = new THREE.Vector3().subVectors(b, a).cross(new THREE.Vector3().subVectors(c, a)).normalize();
		if(normal.dot(toward) < 0) {
			normal.negate();
			[points[1], points[2]] = [c, b];
		}
		normal.multiplyScalar(1 - lift).add(v(0, lift, 0)).normalize();
		for(const p of points) {
			groups[color].position.push(...p.toArray());
			groups[color].normal.push(...normal.toArray());
		}
	};

	// Hasard à graine fixe : chaque talle varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 41;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Une talle. Le chaume : deux lames croisées, l'une large au pied, l'autre large au col, pour qu'il ne disparaisse sous aucun angle.
	// L'épi : un long fuseau plié en V sur une arête médiane, qui penche en arc vers le dehors, l'arête sur le dos de l'arc. Les
	// deux pans prennent la lumière différemment : ce sont les deux rangs de grains, et le pli lui garde un corps de quelque
	// côté qu'on le regarde. Il s'ouvre du col au ventre, tient sa largeur jusqu'à l'épaule, puis s'effile. 2 + 4 + 2 triangles.
	// Les barbes : quatre aiguilles pâles, parallèles, une qui part d'un bord au ventre, deux des bords à l'épaule, une qui
	// prolonge la pointe. 2 + 8 + 4 = 14 triangles.
	const tiller = (azimuth, spread, lean, length, earLength, curve) => {
		const axis = direction(lean, azimuth), outward = outwardOf(azimuth);
		const foot = outward.clone().multiplyScalar(spread);
		const neck = foot.clone().addScaledVector(axis, length);
		const turn = azimuth + between(-.5, .5), side = acrossOf(turn), front = outwardOf(turn);
		push(CULM, [foot.clone().addScaledVector(side, -.024), foot.clone().addScaledVector(side, .024), neck], front);
		push(CULM, [neck.clone().addScaledVector(front, -.016), neck.clone().addScaledVector(front, .016), foot], side);

		const bendAzimuth = azimuth + between(-.5, .5), bend = outwardOf(bendAzimuth), cross = acrossOf(bendAzimuth);
		const half = between(.058, .068);
		// Une section de l'épi à une part de sa longueur : les deux bords, l'arête relevée sur le dos de l'arc, et la tangente
		let point = neck.clone();
		const section = (advance, ratio, angle) => {
			const along = axis.clone().multiplyScalar(Math.cos(angle)).addScaledVector(bend, Math.sin(angle)).normalize();
			point = point.clone().addScaledVector(along, earLength * advance);
			const back = new THREE.Vector3().crossVectors(cross, along).normalize();
			if(back.y < 0) back.negate();
			return {
				along, back,
				left: point.clone().addScaledVector(cross, -half * ratio), right: point.clone().addScaledVector(cross, half * ratio),
				ridge: point.clone().addScaledVector(back, half * ratio * 1.1),
			};
		};
		const belly = section(.25, 1, curve * .2), shoulder = section(.45, .85, curve * .6), end = section(.3, 0, curve);
		const faces = (list, back) => {
			list.forEach((points, i) => push(EAR, points, back.clone().addScaledVector(cross, i % 2 ? .6 : -.6), .35));
		};
		faces([[neck, belly.left, belly.ridge], [neck, belly.ridge, belly.right]], belly.back);
		faces([[belly.left, shoulder.left, belly.ridge], [belly.ridge, shoulder.ridge, belly.right], [shoulder.left, shoulder.ridge, belly.ridge], [shoulder.ridge, shoulder.right, belly.right]], shoulder.back);
		faces([[shoulder.left, end.ridge, shoulder.ridge], [shoulder.ridge, end.ridge, shoulder.right]], end.back);

		const awn = (root, heel, lengthOf, drift) => {
			push(AWN, [root, heel, root.clone().addScaledVector(end.along, lengthOf).addScaledVector(cross, drift)], end.back, .5);
		};
		const first = rand() < .5 ? belly.left : belly.right;
		awn(first, first.clone().lerp(shoulder.ridge, .35), earLength * between(.6, .75), first === belly.left ? -half * .4 : half * .4);
		awn(shoulder.left, shoulder.left.clone().lerp(end.ridge, .4), earLength * between(.55, .7), -half * .5);
		awn(shoulder.right, shoulder.right.clone().lerp(end.ridge, .4), earLength * between(.55, .7), half * .5);
		awn(shoulder.ridge.clone().lerp(end.ridge, .6), end.ridge.clone().addScaledVector(cross, .014), earLength * between(.6, .75), 0);
		return { foot, axis };
	};

	// Quatre talles, une par quart de tour bousculé, ouvertes de 6 à 9° et étagées pour que les épis ne se confondent pas, tous
	// penchés vers le dehors
	const tillers = [];
	for(let i = 0; i < 4; i++) {
		tillers.push(tiller(i * Math.PI / 2 + between(-.4, .4), between(.025, .04), between(.1, .15), [1.14, .97, 1.07, .89][i] + between(-.02, .02), between(.36, .41), between(.4, .6)));
	}

	// Une feuille : une lame attachée au bas du chaume, qui monte jusqu'à un genou puis retombe en pointe. 2 triangles.
	const leaf = ({ foot, axis }, height, azimuth, reach) => {
		const attach = foot.clone().addScaledVector(axis, height), outward = outwardOf(azimuth), side = acrossOf(azimuth);
		const knee = attach.clone().addScaledVector(outward, reach * .55).add(v(0, reach * 1.6, 0));
		const kneeLeft = knee.clone().addScaledVector(side, -.024), kneeRight = knee.clone().addScaledVector(side, .024);
		const tip = attach.clone().addScaledVector(outward, reach).add(v(0, reach * .7, 0));
		const toward = outward.clone().add(v(0, 1, 0));
		push(LEAF, [attach, kneeLeft, kneeRight], toward);
		push(LEAF, [kneeLeft, tip, kneeRight], toward);
	};
	// Deux feuilles seulement, dans le quart bas : au-dessus, le chaume est nu
	[.12, .3].forEach((height, i) => {
		leaf(tillers[i * 2], height + between(-.03, .03), i * 2.39996 + 1 + between(-.3, .3), between(.14, .18));
	});

	const part = (color, sway) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(groups[color].position, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(groups[color].normal, 3));
		return { geometry, color, sway };
	};
	const parts = [part(LEAF, .6), part(CULM, .8), part(EAR, 1), part(AWN, 1)];
	// d et h sont les dimensions réelles. La scène borne l'exagération d'un plant haut (0,75 m × 2,4 m à l'écran pour celui-ci) :
	// la gerbe est donc dessinée dans ce rapport de 3,2, et non dans le rapport réel de 5, pour ne pas être écrasée en largeur
	return { d: .3, h: 1.5, parts };
}
