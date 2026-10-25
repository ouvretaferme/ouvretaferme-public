// Narcisse trompette en fleur, pensé pour se lire en parterre sur un rang à 20 px/m : une touffe de six rubans glauques
// étroits et dressés, d'où sortent deux hampes nues, et au bout de chacune une fleur tournée vers le dehors, faite d'une
// étoile jaune pâle à six branches et d'une trompette orangée qui en sort vers l'avant. Le plus fort contraste est entre
// l'orange de la trompette et le jaune pâle de l'étoile qui la porte.
// Ce qui le sépare de la tulipe : une étoile ouverte tournée sur le côté et non un gobelet fermé dans l'axe de la tige, du
// jaune et non du rose, des rubans étroits qui montent haut au lieu de larges feuilles basses. De l'iris : une touffe ronde
// et non un éventail de glaives, une fleur plate à cône. De la marguerite et du cosmos : des fleurs qui regardent
// l'horizon, un cœur en cône saillant, des hampes nues.
// Fiche : narcisse.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const TEPAL = '#f7e47c'; const TRUMPET = '#f0951a'; const LEAF = '#6f9a82'; const STEM = '#93b25e';

	// Hasard à graine fixe : chaque feuille et chaque fleur varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 73;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Géométrie à partir d'une liste de triangles [Vector3 × 3] et d'une fonction qui donne la normale de chaque sommet
	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat().flatMap(v => v.toArray()), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(v => normalOf(v).toArray()), 3));
		return g;
	};

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const flatAt = (azimuth, r, y = 0) => new THREE.Vector3(Math.cos(azimuth) * r, y, Math.sin(azimuth) * r);

	// Un ruban : une lame plate de largeur presque constante, qui monte droit puis s'arque vers le dehors, et se resserre
	// seulement au bout, arrondi par un dernier triangle court. 5 triangles, 3 pour une jeune lame courte.
	const leaves = [];
	const leaf = (azimuth, lean, arc, length, width, twist, short = false) => {
		const out = flatAt(azimuth, 1);
		const side = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const foot = flatAt(azimuth, .008);
		const along = t => foot.clone().addScaledVector(direction(azimuth, lean + t * t * arc), length * t);
		// Le ruban vrille un peu en montant, comme une vraie lame : il ne se présente jamais tout entier par la tranche
		const pair = (t, w) => {
			const across = side.clone().applyAxisAngle(new THREE.Vector3(0, 1, 0), t * twist).multiplyScalar(w / 2);
			return [along(t).add(across), along(t).sub(across)];
		};
		const [l0, r0] = pair(0, width * .8), [l1, r1] = pair(between(.42, .5), width), [l2, r2] = pair(between(.86, .9), width * .75);
		const tip = along(1);
		// La normale suit la lame : elle regarde le dehors au pied et le ciel là où le ruban s'arque
		const normalOf = v => out.clone().multiplyScalar(.8).add(new THREE.Vector3(0, .3 + (v.y / length) * .6, 0)).normalize();
		leaves.push(geometry(short ? [[l0, l1, r0], [r0, l1, r1], [l1, tip, r1]] : [[l0, l1, r0], [r0, l1, r1], [l1, l2, r1], [r1, l2, r2], [l2, tip, r2]], normalOf));
	};

	// Une hampe : deux triangles pliés sur une arête, du pied au dos de la fleur, raide et nue
	const stems = [];
	const stalk = (from, to, width) => {
		const axis = to.clone().sub(from);
		const side = new THREE.Vector3(0, 1, 0).cross(axis).normalize().multiplyScalar(width / 2);
		const ridge = side.clone().cross(axis).normalize().multiplyScalar(width * .5);
		const normalOf = v => v.clone().sub(from).projectOnPlane(axis).add(ridge).normalize();
		stems.push(geometry([
			[from.clone().add(side), from.clone().add(ridge), to],
			[from.clone().add(ridge), from.clone().sub(side), to],
		], normalOf));
	};

	// Une fleur, tournée vers le dehors et un peu relevée pour montrer sa face au ciel comme à l'horizon.
	// L'étoile : deux grands triangles croisés à 60°, soit six branches larges, chaque pointe à sa longueur, face et dos : 4 triangles.
	// La trompette : un tube à cinq pans qui part du cœur et s'évase vers l'avant, bord inégal, 10 triangles.
	// Par fleur : étoile 4 + trompette 10 + hampe 2 = 16 triangles.
	const stars = [], trumpets = [];
	const flower = (azimuth, offset, height, up, radius, phase) => {
		const outward = flatAt(azimuth, 1);
		const axis = outward.clone().multiplyScalar(Math.cos(up)).add(new THREE.Vector3(0, Math.sin(up), 0));
		const u = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const v = axis.clone().cross(u).normalize();
		const foot = flatAt(azimuth, .006);
		const heart = flatAt(azimuth, offset, height);
		const place = (r, a, forward) => heart.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(v, Math.sin(a) * r).addScaledVector(axis, forward);

		const tips = Array.from({ length: 6 }, (_, k) => place(radius * between(.86, 1.12), phase + k * Math.PI / 3 + between(-.1, .1), radius * between(-.06, .1)));
		// Le matériau double face retourne la normale au revers, qui virerait au kaki : l'étoile a donc une face et un dos,
		// décollés d'un cheveu, chacun avec une normale qui regarde de son côté et vers le ciel. Les branches penchent vers
		// leur pointe et ne prennent pas toutes la même lumière.
		const starNormal = sign => p => axis.clone().multiplyScalar(sign * .6).add(new THREE.Vector3(0, .7, 0)).addScaledVector(p.clone().sub(heart).normalize(), .3).normalize();
		const back = tips.map(p => p.clone().addScaledVector(axis, -radius * .04));
		stars.push(geometry([[tips[0], tips[2], tips[4]], [tips[1], tips[3], tips[5]]], starNormal(1)));
		stars.push(geometry([[back[0], back[4], back[2]], [back[1], back[5], back[3]]], starNormal(-1)));

		const long = radius * between(.85, 1);
		const throat = [], mouth = [];
		for(let k = 0; k < 5; k++) {
			const a = phase + k * Math.PI * 2 / 5;
			throat.push(place(radius * .26, a, radius * .02));
			mouth.push(place(radius * between(.46, .56), a + Math.PI / 5, long * between(.92, 1.06)));
		}
		const tube = [];
		for(let k = 0; k < 5; k++) {
			const next = (k + 1) % 5;
			tube.push([throat[k], mouth[k], throat[next]], [mouth[k], mouth[next], throat[next]]);
		}
		const tubeNormal = p => p.clone().sub(heart).projectOnPlane(axis).normalize().addScaledVector(axis, -.3).normalize();
		trumpets.push(geometry(tube, tubeNormal));

		stalk(foot, heart.clone().addScaledVector(axis, -radius * .08), .011);
	};

	// Deux hampes d'un bulbe double : la plus haute fleur à 0,40 m, la seconde plus bas, dos à dos
	const first = between(0, Math.PI * 2);
	flower(first, .014, .4, between(.3, .4), between(.054, .058), between(0, 1));
	flower(first + Math.PI + between(-.5, .5), .016, .345, between(.2, .3), between(.05, .054), between(0, 1));

	// Cinq rubans autour du pied, les plus longs presque à hauteur des fleurs, et une jeune lame courte
	for(let k = 0; k < 5; k++) {
		const azimuth = first + Math.PI / 2 + k * Math.PI * 2 / 5 + between(-.3, .3);
		leaf(azimuth, between(.05, .12), between(.1, .2), between(.27, .37), between(.024, .028), between(.5, .9) * (k % 2 ? 1 : -1));
	}
	leaf(first + Math.PI / 2 + between(-.4, .4), between(.1, .14), between(.1, .16), between(.17, .2), .024, .5, true);

	const parts = [
		{ color: LEAF, sway: .6, geometry: helpers.merge(leaves) },
		{ color: STEM, sway: .8, geometry: helpers.merge(stems) },
		{ color: TEPAL, sway: .8, geometry: helpers.merge(stars) },
		{ color: TRUMPET, sway: .8, geometry: helpers.merge(trumpets) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .15;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
