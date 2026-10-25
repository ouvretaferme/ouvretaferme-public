// Lavande vraie en fleur, pensée pour se lire sur un rang à 20 px/m : un coussin bas de touffes de tiges gris-vert dressées,
// au contour hérissé, d'où montent des hampes fines serrées terminées par des épis violets ; leurs têtes font une boule
// violette posée au-dessus du gris. Deux étages, gris en bas, violet en haut.
// Ce qui la sépare du romarin : pas de vase vert sombre sur un pied de bois, mais un coussin gris sans pied, plus large que
// haut, et le violet en boule d'épis au bout de hampes qui dépassent nettement du feuillage ; du thym : la boule d'épis ;
// de la ciboulette : le coussin gris et des épis allongés au lieu de pompons.
// Fiche : lavande.reference.md. Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles).
export function build(THREE) {
	const SILVER = '#aab4a0'; const SAGE = '#7f8c74'; const VIOLET = '#5b3f8f'; const LAVENDER = '#8f74c4';
	const groups = Object.fromEntries([SILVER, SAGE, VIOLET, LAVENDER].map(color => [color, { position: [], normal: [] }]));
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const push = (color, points, normals) => {
		points.forEach((p, i) => {
			groups[color].position.push(...p.toArray());
			groups[color].normal.push(...normals[i].toArray());
		});
	};
	const faceNormal = ([a, b, c]) => new THREE.Vector3().subVectors(b, a).cross(new THREE.Vector3().subVectors(c, a)).normalize();
	const direction = (lean, azimuth) => v(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const across = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));

	// Hasard à graine fixe : chaque tige varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 29;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Le coussin : un cœur bas et sombre, pyramide à cinq pans irréguliers sans fond, qui fait l'ombre du dedans de la touffe et la
	// ferme au sol, et autour, quatre faisceaux de quatre tiges feuillées claires, des lames effilées qui partent du sol et
	// s'ouvrent vers le dehors. Ce sont les faisceaux qui dessinent le contour, irrégulier et hérissé, et non le cœur, qu'ils
	// débordent de partout. Les normales sont celles d'une demi-boule de 25 cm, penchées vers la face de chaque lame : le
	// coussin s'éclaire comme un volume rond, chaque tige garde son plan. 5 + 16 = 21 triangles.
	const R = .25, H = .25;
	const mound = p => v(p.x / (R * R), Math.max(p.y, .03) / (H * H), p.z / (R * R)).normalize();
	const apex = v(.01, .16, -.01);
	const rim = Array.from({ length: 5 }, (_, i) => {
		const a = i * Math.PI * 2 / 5 + between(-.2, .2), r = between(.14, .17);
		return v(r * Math.cos(a), 0, r * Math.sin(a));
	});
	for(let i = 0; i < 5; i++) {
		const tri = [rim[i], apex, rim[(i + 1) % 5]];
		push(SAGE, tri, tri.map(p => p === apex ? v(0, 1, 0) : mound(p.clone().setY(.06))));
	}
	const blade = (foot, lean, azimuth, length, width) => {
		const axis = direction(lean, azimuth), side = across(azimuth + between(-.5, .5));
		const tip = foot.clone().addScaledVector(axis, length);
		const tri = [foot.clone().addScaledVector(side, -width / 2), foot.clone().addScaledVector(side, width / 2), tip];
		let face = faceNormal(tri);
		if(face.dot(v(Math.cos(azimuth), .5, Math.sin(azimuth))) < 0) face = face.negate();
		push(SILVER, tri, tri.map(p => mound(p).multiplyScalar(.6).addScaledVector(face, .4).normalize()));
	};
	for(let i = 0; i < 4; i++) {
		const outward = i * Math.PI / 2 + .4 + between(-.3, .3), distance = between(.07, .1);
		const center = v(distance * Math.cos(outward), 0, distance * Math.sin(outward));
		for(let k = 0; k < 4; k++) {
			const fan = (k - 1.5) * .45 + between(-.12, .12), inner = k === 1 || k === 2;
			blade(center.clone().addScaledVector(across(outward), (k - 1.5) * .025), inner ? between(.2, .4) : between(.7, .9), outward + fan, inner ? between(.25, .29) : between(.2, .23), between(.06, .075));
		}
	}

	// Une hampe : un fil gris-vert d'un triangle très effilé qui part de l'intérieur du coussin, puis l'épi, un triangle
	// étroit pointé vers le haut, tourné au hasard pour que les épis restent pleins sous tous les angles. Les hampes du centre
	// sont droites, celles du bord s'ouvrent jusqu'à 30° : les têtes, serrées, dessinent une boule violette au-dessus du gris.
	// 2 triangles par hampe.
	const spike = (spread, azimuth, lean, stalk, length, width, color) => {
		const foot = v(spread * Math.cos(azimuth), .08, spread * Math.sin(azimuth));
		const axis = direction(lean, azimuth), side = across(between(0, Math.PI * 2));
		const base = foot.clone().addScaledVector(axis, stalk);
		const wire = [foot.clone().addScaledVector(side, .005), base, foot.clone().addScaledVector(side, -.005)];
		const n = faceNormal(wire);
		push(SAGE, wire, [n, n, n]);
		const ear = [base.clone().addScaledVector(side, -width / 2), base.clone().addScaledVector(side, width / 2), base.clone().addScaledVector(axis, length)];
		const m = faceNormal(ear);
		push(color, ear, ear.map(p => p.clone().sub(v(0, .3, 0)).normalize().multiplyScalar(.5).addScaledVector(m, .5).normalize()));
	};

	// Dix-neuf hampes : cinq au cœur, quatorze sur un angle d'or bousculé autour. Un épi sur trois est d'un violet plus clair.
	for(let i = 0; i < 19; i++) {
		const outer = i >= 5;
		const ratio = outer ? between(.45, 1) : between(0, .3);
		spike(ratio * .08, i * 2.39996 + between(-.3, .3), ratio * .45 + between(0, .06), between(.24, .28) - ratio * .05, between(.12, .14), between(.05, .058), i % 3 === 1 ? LAVENDER : VIOLET);
	}

	const part = (color, sway) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(groups[color].position, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(groups[color].normal, 3));
		return { geometry, color, sway };
	};
	// Le coussin ligneux bouge à peine ; les hampes et leurs épis oscillent au vent
	const parts = [part(SILVER, .2), part(SAGE, .4), part(VIOLET, 1), part(LAVENDER, 1)];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const { geometry } of parts) {
		geometry.computeBoundingBox();
		box.union(geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .5;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
