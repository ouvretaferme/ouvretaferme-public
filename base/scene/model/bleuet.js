// Bleuet en fleur, pensé pour se lire en parterre sur un rang à 20 px/m : une messicole dressée, deux tiges raides gris vert
// qui se ramifient en candélabre, et au bout de chaque branche un capitule en couronne : huit fleurons bleu vif qui rayonnent
// autour d'un cœur bleu nuit, séparés par des encoches, d'où un bord dentelé. Quatre capitules, gros et inclinés vers
// l'extérieur, se lisent comme des étoiles frangées au-dessus d'une touffe de lanières grises.
// Ce qui le sépare du lin bleu : huit rayons fins et non cinq pétales ronds, un cœur sombre, des capitules tournés de côté et
// non des entonnoirs au ciel, des tiges épaisses et ramifiées qui portent des feuilles. Du coquelicot : des étoiles frangées
// et non des coupes rondes, une colonne raide sans rosette au pied.
// Fiche : bleuet.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const BLUE = '#5b93f7'; const HEART = '#2a2f86'; const LEAF = '#9db497'; const STEM = '#58744f';

	// Hasard à graine fixe : chaque branche et chaque capitule varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 61;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normalOf(c).toArray()), 3));
		return g;
	};
	// Normales lissées : chaque sommet porte la moyenne des faces qui le partagent, d'où une lanière pliée qui se lit ronde
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			if(n.y < 0) n.negate();
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		return geometry(tris, c => normals.get(key(c)).clone().normalize());
	};

	const up = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));

	// Une tige : deux triangles effilés en croix, l'un à plat face au vent, l'autre de chant. Le plant tourne au hasard sur la
	// planche : un ruban seul disparaîtrait de profil, la croix garde toujours un trait plein de la largeur voulue.
	const stems = [];
	const stalk = (from, to, width) => {
		const along = to.clone().sub(from).normalize();
		const side = new THREE.Vector3(0, 1, 0).cross(along);
		if(side.lengthSq() < 1e-6) side.set(1, 0, 0);
		side.normalize();
		const other = along.clone().cross(side).normalize();
		for(const s of [side, other]) {
			stems.push([from.clone().addScaledVector(s, width / 2).toArray(), from.clone().addScaledVector(s, -width / 2).toArray(), to.toArray()]);
		}
	};

	// Une feuille de tige : une lanière étroite pliée sur sa nervure, qui part de la tige et monte en s'écartant. 2 triangles.
	const leaves = [];
	const leaf = (base, azimuth, lean, length, width) => {
		const to = base.clone().addScaledVector(up(azimuth, lean), length);
		const along = to.clone().sub(base).normalize();
		const side = new THREE.Vector3(0, 1, 0).cross(along).normalize().multiplyScalar(width / 2);
		const crest = base.clone().add(side.clone().cross(along).normalize().multiplyScalar(width * .4)).addScaledVector(along, length * .3);
		leaves.push(
			[base.clone().add(side).toArray(), crest.toArray(), to.toArray()],
			[crest.toArray(), base.clone().sub(side).toArray(), to.toArray()],
		);
	};

	// Un capitule : huit fleurons du bord en éventail, chacun un coin qui part du centre et s'évase jusqu'au bord, relevé en
	// coupe peu profonde. Chaque coin couvre un peu moins que son huitième de tour : les encoches entre eux frangent le bord,
	// et la couronne se lit dentelée et rayonnante, ni disque plein ni fleur à cinq pétales ronds. Au centre, un losange bleu
	// nuit posé au-dessus des fleurons : c'est le cœur sombre qui fait le bleuet. Les normales suivent l'axe, à peine ouvertes
	// vers le bord, pour que la couronne reste claire sous tout angle.
	// Par capitule : 8 fleurons + 2 cœur = 10 triangles.
	const rays = [], hearts = [];
	const head = (center, axis, radius) => {
		const u = new THREE.Vector3(1, 0, 0).projectOnPlane(axis).normalize(), v = axis.clone().cross(u);
		const at = (a, r, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, lift);
		const phase = between(0, Math.PI * 2), step = Math.PI * 2 / 8;
		const tris = [];
		for(let j = 0; j < 8; j++) {
			const a = phase + j * step + between(-.08, .08), half = step * between(.36, .44);
			const reach = radius * between(.86, 1.1), lift = radius * between(.28, .4);
			tris.push([center.toArray(), at(a - half, reach, lift).toArray(), at(a + half, reach * between(.88, 1.12), lift).toArray()]);
		}
		rays.push(geometry(tris, c => {
			const out = new THREE.Vector3(...c).sub(center).projectOnPlane(axis);
			return axis.clone().addScaledVector(out.lengthSq() > 0 ? out.normalize() : out, .25).normalize();
		}));
		const turn = between(0, Math.PI / 2), core = radius * .36, lift = radius * .16;
		const [p0, p1, p2, p3] = [0, 1, 2, 3].map(k => at(turn + k * Math.PI / 2, core * (k % 2 ? .8 : 1), lift).toArray());
		hearts.push(geometry([[p0, p1, p2], [p0, p2, p3]], () => axis));
	};

	// Une branche : un trait de son départ au capitule, et le capitule incliné vers l'extérieur, d'où une étoile lisible de
	// côté et non une ligne vue de chant
	const branch = (from, azimuth, lean, length, width, radius) => {
		const top = from.clone().addScaledVector(up(azimuth, lean), length);
		stalk(from, top, width);
		head(top, up(azimuth + between(-.3, .3), .35 + lean * .5), radius);
	};

	// Une tige maîtresse fourche à un tiers de sa hauteur, une branche s'écarte, la tige monte jusqu'à une seconde fourche qui
	// en porte deux. Une seconde tige, simple et plus fine, part du pied de l'autre côté : la touffe a deux fûts et quatre
	// capitules étagés sur le dernier tiers et tournés chacun de son côté, d'où un V ramifié sous tout angle. Deux lanières sur chaque tige, dans la moitié basse.
	// Tiges 6 × 2, capitules 4 × 10, lanières 4 × 2 : 12 + 40 + 8 = 60 triangles.
	const foot = new THREE.Vector3(.016, 0, 0), forkLow = new THREE.Vector3(.016, .32, .006), forkHigh = new THREE.Vector3(.01, .5, -.004);
	stalk(foot, forkLow, .028);
	stalk(forkLow, forkHigh, .022);
	branch(forkLow, .3 + between(-.2, .2), .44 * between(.9, 1.1), .28 * between(.95, 1.05), .018, .06);
	branch(forkHigh, 1.6 + between(-.2, .2), .36 * between(.9, 1.1), .22 * between(.95, 1.05), .016, .056);
	branch(forkHigh, 4.9 + between(-.2, .2), .12 * between(.9, 1.1), .26 * between(.95, 1.05), .016, .062);
	const footOther = new THREE.Vector3(-.02, 0, .008);
	branch(footOther, 3.4 + between(-.2, .2), .26 * between(.9, 1.1), .58 * between(.95, 1.05), .02, .055);
	for(const [t, azimuth, lean, length] of [[.3, 1.6, .42, .2], [.7, 4.4, .36, .18]]) {
		leaf(foot.clone().lerp(forkLow, t), azimuth + between(-.3, .3), lean * between(.85, 1.15), length * between(.9, 1.1), .03);
	}
	const otherAxis = up(3.4, .26);
	for(const [y, azimuth, lean, length] of [[.1, 2.1, .4, .19], [.3, 4.6, .36, .17]]) {
		leaf(footOther.clone().addScaledVector(otherAxis, y), azimuth + between(-.3, .3), lean * between(.85, 1.15), length * between(.9, 1.1), .028);
	}

	const parts = [
		{ color: STEM, geometry: smooth(stems) },
		{ color: LEAF, sway: .6, geometry: smooth(leaves) },
		{ color: BLUE, geometry: helpers.merge(rays) },
		{ color: HEART, geometry: helpers.merge(hearts) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .25;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
