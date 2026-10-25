// Campanule à grosses fleurs en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : une rosette pleine qui fait
// tapis au sol, d'où montent deux tiges raides garnies de feuilles étroites sur toute leur hauteur, et le long des tiges des
// cloches violettes étagées, vues de côté : un corps plus long que large, fond fermé, bouche ouverte vers le haut ou le dehors.
// Ce qui la sépare de la digitale : deux tiges feuillées et non une hampe nue, des cloches courtes ouvertes vers le ciel tout
// autour, et non des tubes roses pendants d'un seul côté. Du delphinium : des cloches creuses, peu nombreuses et grosses, et
// non des étoiles plates bleu gentiane en épi serré. Du pois de senteur : des tiges dressées seules, sans rame ni vrilles,
// et des cloches et non des pétales en ailes.
// Fiche : campanule.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE) {
	const BELL = '#6d4fc6'; const THROAT = '#cfc2f2'; const LEAF = '#6f9655'; const STEM = '#47663a';

	// Hasard à graine fixe : chaque cloche et chaque feuille varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 29;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const UP = v(0, 1, 0);
	const outwardOf = azimuth => v(Math.cos(azimuth), 0, Math.sin(azimuth));
	const sideOf = azimuth => v(-Math.sin(azimuth), 0, Math.cos(azimuth));
	const toward = (azimuth, lean) => outwardOf(azimuth).multiplyScalar(Math.sin(lean)).setY(Math.cos(lean));

	// Le matériau éclaire les deux faces et retourne la normale d'une face vue de dos : chaque triangle est donc écrit dans le
	// sens où sa face avant regarde du côté de ses normales, sinon une cloche ou une feuille vue par en dessous s'éteint.
	const geometry = tris => {
		const oriented = tris.map(({ points, normals }) => {
			const [a, b, c] = points;
			const face = b.clone().sub(a).cross(c.clone().sub(a)).normalize();
			if(normals === undefined) {
				normals = Array(3).fill(face.y < 0 ? face.clone().negate() : face);
			}
			const mean = normals[0].clone().add(normals[1]).add(normals[2]);
			return face.dot(mean) < 0 ? { points: [a, c, b], normals: [normals[0], normals[2], normals[1]] } : { points, normals };
		});
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(oriented.flatMap(t => t.points).flatMap(p => p.toArray()), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(oriented.flatMap(t => t.normals).flatMap(n => n.clone().normalize().toArray()), 3));
		return g;
	};
	const flatTri = (a, b, c) => ({ points: [a, b, c] });

	const bells = [], throats = [], stems = [], leaves = [];

	// Une cloche vue de côté : un tronc à trois pans, étroit et arrondi au fond, qui s'élargit vers une bouche deux fois et demie
	// plus large, et plus long que la bouche n'est large. Le fond reste assez étroit pour se lire fermé, sans triangle de plus.
	// Les normales des pans partent de l'axe, partagées par sommet, penchées vers le fond en bas et vers la bouche en haut, pour
	// que trois pans se lisent en corps rond et ventru et non en prisme. Un fond lilas ferme la bouche juste en retrait du bord :
	// le cerne violet autour fait lire une cloche creuse et non une massue. 6 + 1 triangles.
	const bell = (attach, axis, length, mouth) => {
		axis = axis.clone().normalize();
		const u = (Math.abs(axis.y) > .9 ? v(1, 0, 0) : UP).clone().cross(axis).normalize(), w = axis.clone().cross(u).normalize();
		const turn = between(0, Math.PI * 2);
		const radial = k => {
			const angle = turn + k * Math.PI * 2 / 3;
			return u.clone().multiplyScalar(Math.cos(angle)).addScaledVector(w, Math.sin(angle));
		};
		const foot = [0, 1, 2].map(k => attach.clone().addScaledVector(radial(k), mouth * .5));
		const lip = [0, 1, 2].map(k => attach.clone().addScaledVector(axis, length).addScaledVector(radial(k), mouth * between(.92, 1.08)));
		for(let k = 0; k < 3; k++) {
			const j = (k + 1) % 3;
			const nFootK = radial(k).addScaledVector(axis, -.7), nFootJ = radial(j).addScaledVector(axis, -.7);
			const nLipK = radial(k).addScaledVector(axis, .1), nLipJ = radial(j).addScaledVector(axis, .1);
			bells.push(
				{ points: [foot[k], lip[k], lip[j]], normals: [nFootK, nLipK, nLipJ] },
				{ points: [foot[k], lip[j], foot[j]], normals: [nFootK, nLipJ, nFootJ] },
			);
		}
		const centre = attach.clone().addScaledVector(axis, length * .9);
		throats.push({ points: lip.map(p => p.clone().lerp(centre, .16)), normals: [axis, axis, axis] });
	};

	// La rosette : une étoile pleine de cinq feuilles lancéolées dont les flancs se touchent, le cœur un peu levé, les pointes
	// couchées au sol, les creux entre deux feuilles plus bas que leurs flancs. Elle couvre son disque sans trou : de touffe en
	// touffe, le vert fait tapis sur la planche. 5 × 2 = 10 triangles.
	const facing = between(0, Math.PI * 2);
	const tips = 5;
	const heart = v(0, .05, 0);
	const notch = i => {
		const azimuth = facing + (i + .5) * Math.PI * 2 / tips + between(-.15, .15);
		return outwardOf(azimuth).multiplyScalar(.13 * between(.9, 1.1)).setY(.03);
	};
	const notches = Array.from({ length: tips }, (_, i) => notch(i));
	for(let i = 0; i < tips; i++) {
		const azimuth = facing + i * Math.PI * 2 / tips + between(-.2, .2);
		const tip = outwardOf(azimuth).multiplyScalar(.21 * between(.88, 1.1)).setY(.012);
		leaves.push(flatTri(heart, notches[(i + tips - 1) % tips], tip), flatTri(heart, tip, notches[i]));
	}

	// Deux tiges raides du cœur, la maîtresse presque droite, la seconde penchée de 17 à 21° du côté opposé, la seconde plus courte. Une lame effilée, large de
	// 2,2 cm au pied, dont le plan est tourné au hasard. 2 triangles.
	const stemPoint = (azimuth, lean, length) => t => toward(azimuth, lean).multiplyScalar(length * t);
	const stalkFacing = facing + between(0, Math.PI), otherFacing = stalkFacing + Math.PI + between(-.4, .4);
	const stalks = [
		{ azimuth: stalkFacing, point: stemPoint(stalkFacing, .1 * between(.6, 1.2), .66) },
		{ azimuth: otherFacing, point: stemPoint(otherFacing, .34 * between(.85, 1.1), .56) },
	];
	for(const { point } of stalks) {
		const side = sideOf(between(0, Math.PI * 2)).multiplyScalar(.011);
		stems.push(flatTri(point(0).add(side), point(0).sub(side), point(1)));
	}

	// Le long de chaque tige, de bas en haut, trois feuilles étroites et trois cloches qui alternent : une feuille sessile à
	// 14, 30 et 50 % de la tige, puis une cloche à 40, 66 et 92 %, tournées d'un angle d'or l'une à l'autre. Les feuilles
	// montent à 40° de la tige, larges de 5,6 cm à la base, longues de 13 à 9 cm du bas vers le haut. Les cloches, attachées au flanc de la tige, sortent
	// presque à l'horizontale en bas et se redressent vers le haut, la dernière dans l'axe. Grosses pour rester des cloches
	// séparées à 20 px/m : 14 cm de long, bouche de 10 cm. 2 × (3 + 3 × 7) = 48 triangles.
	for(const { point, azimuth: stalkAzimuth } of stalks) {
		let azimuth = stalkAzimuth + between(0, Math.PI * 2);
		[.14, .3, .5].forEach((t, i) => {
			azimuth += 2.39996 + between(-.3, .3);
			const base = point(t), length = (.13 - i * .02) * between(.9, 1.1);
			const dir = toward(azimuth, .7 * between(.85, 1.15)), side = sideOf(azimuth).multiplyScalar(.028);
			leaves.push(flatTri(base.clone().add(side), base.clone().sub(side), base.clone().addScaledVector(dir, length)));
		});
		[.4, .66, .92].forEach((t, i) => {
			azimuth += 2.39996 + between(-.3, .3);
			const crown = i === 2;
			const lean = crown ? between(.1, .35) : (1.45 - i * .45) * between(.9, 1.1);
			const size = (1 - i * .08) * between(.92, 1.08);
			const attach = point(t + between(-.02, .02)).addScaledVector(outwardOf(azimuth), crown ? 0 : .012);
			bell(attach, toward(azimuth, lean), .14 * size, .062 * size);
		});
	}

	const parts = [
		{ color: LEAF, sway: .2, geometry: geometry(leaves) },
		{ color: STEM, sway: .7, geometry: geometry(stems) },
		{ color: BELL, sway: .8, geometry: geometry(bells) },
		{ color: THROAT, sway: .8, geometry: geometry(throats) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .3;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
