// Safran en fleur, pensé pour se lire en parterre sur un rang à 20 px/m : trois coupes lilas ouvertes à six pointes,
// posées bas sur un tube violet, d'où sortent des fils rouge vif, dans une gerbe d'herbe fine et sombre qui les dépasse à
// peine. Le plus fort contraste est entre le rouge des stigmates et le lilas des coupes.
// Ce qui le sépare de la ciboulette : des coupes ouvertes et non des pompons pleins, posées dans le feuillage et non
// au-dessus sur des hampes, une touffe deux fois plus basse, et le rouge ; du colchique : la gerbe d'herbe et le rouge.
// Fiche : safran.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LILAC = '#b18ad9'; const VIOLET = '#7c55a8'; const LEAF = '#3d6b35'; const STIGMA = '#d6301c';

	// Hasard à graine fixe : chaque feuille et chaque fleur varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 41;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Géométrie à partir d'une liste de triangles [[x, y, z] × 3] et d'une fonction qui donne la normale de chaque sommet
	const geometry = (tris, normalOf) => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normalOf(c).toArray()), 3));
		return g;
	};
	// Normales lissées : chaque sommet porte la moyenne des faces qui le partagent, d'où une feuille pliée qui se lit ronde
	const smooth = tris => {
		const key = p => p.map(v => v.toFixed(5)).join(',');
		const normals = new Map();
		for(const [p, q, r] of tris) {
			const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		return geometry(tris, c => normals.get(key(c)).clone().normalize());
	};

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));

	// Une feuille : deux triangles pliés le long d'une nervure, la largeur tangente à la touffe et la nervure vers l'extérieur
	const blade = (azimuth, lean, length, width) => {
		const outward = new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth));
		const side = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth)).multiplyScalar(width / 2);
		const foot = outward.clone().multiplyScalar(.004);
		const tip = foot.clone().addScaledVector(direction(azimuth, lean), length).toArray();
		const left = foot.clone().add(side).toArray(), right = foot.clone().sub(side).toArray();
		const ridge = foot.clone().addScaledVector(outward, width * .4).toArray();
		return [[left, ridge, tip], [ridge, right, tip]];
	};

	// Feuilles : six, sur un angle d'or bousculé, des plus droites au cœur aux plus couchées au bord. Elles dépassent les
	// coupes de peu et les encadrent sans les couvrir. 6 × 2 = 12 triangles.
	const leaves = [];
	for(let i = 0; i < 6; i++) {
		const lean = .26 + (i % 3) * .2 + between(0, .08);
		leaves.push(...blade(i * 2.39996 + between(-.35, .35), lean, between(.13, .155) * (1 - lean * .15), between(.006, .0075)));
	}

	// Une fleur : un tube fin plié qui sort de terre, puis une coupe. La coupe part d'une pointe, s'évase jusqu'à un anneau
	// de six sommets au ventre et finit en six pointes décalées d'un demi-pas, qui dessinent les tépales. Les normales
	// rayonnent depuis l'axe, d'où une coupe ronde sans arête. La coupe entière est lilas : vue d'en haut, comme en jeu, on
	// en voit surtout l'intérieur, et un fond sombre éteindrait le tapis mauve. Seul le tube prend le violet sourd.
	// Les stigmates : deux fils qui partent du fond de la coupe, passent le bord et retombent dehors.
	// Par fleur : tube 2 + coupe 6 + 6 + fils 2 = 16 triangles.
	const tubes = [], cups = [], stigmas = [];
	const flower = (azimuth, offset, lean, height, radius, phase) => {
		const axis = direction(azimuth, lean);
		const tilt = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const foot = new THREE.Vector3(Math.cos(azimuth) * offset, 0, Math.sin(azimuth) * offset);
		const place = (r, a, y) => new THREE.Vector3(Math.cos(a) * r, y, Math.sin(a) * r).applyAxisAngle(tilt, -lean).add(foot);
		const top = height, belly = top - radius * .75, bottom = top - radius * 1.55;
		const base = place(0, 0, bottom);
		const ring = [0, 1, 2, 3, 4, 5].map(j => place(radius * .8, phase + j * Math.PI / 3, belly));
		const tips = [0, 1, 2, 3, 4, 5].map(j => place(radius * between(1, 1.15), phase + (j + .5) * Math.PI / 3, top - between(0, radius * .2)));
		const center = place(0, 0, belly);
		const radial = p => p.clone().sub(center).projectOnPlane(axis).normalize().addScaledVector(axis, -.2).normalize();
		const normalOf = c => {
			const v = new THREE.Vector3(...c);
			return v.distanceTo(base) < 1e-6 ? axis.clone().negate() : radial(v);
		};
		const lower = [], upper = [];
		ring.forEach((p, j) => {
			const q = ring[(j + 1) % 6];
			lower.push([base.toArray(), q.toArray(), p.toArray()]);
			upper.push([p.toArray(), q.toArray(), tips[j].toArray()]);
		});
		cups.push(geometry(lower, normalOf));
		cups.push(geometry(upper, normalOf));

		// Le tube, plié comme une feuille, du sol au fond de la coupe
		const side = tilt.clone().multiplyScalar(.0028), out = new THREE.Vector3(Math.cos(azimuth), 0, Math.sin(azimuth)).multiplyScalar(.0022);
		const ground = new THREE.Vector3(foot.x * .4, 0, foot.z * .4);
		tubes.push(smooth([
			[ground.clone().add(side).toArray(), ground.clone().add(out).toArray(), base.toArray()],
			[ground.clone().add(out).toArray(), ground.clone().sub(side).toArray(), base.toArray()],
		]));

		// Les fils : du fond de la coupe vers deux pointes opposées, et au-delà, plus bas que le bord
		const throat = place(0, 0, belly - radius * .3);
		const fork = j => phase + (j + .5) * Math.PI / 3;
		const thread = (a, droop) => [throat.toArray(), place(radius * .22, a + 1.6, belly).toArray(), place(radius * 1.5, a, top - radius * droop).toArray()];
		stigmas.push(smooth([thread(fork(0), .45), thread(fork(3) + .4, .6)]));
	};

	// Trois fleurs à des hauteurs, des penchés et des tailles différents, dans le premier tiers de la touffe
	for(const [azimuth, offset, lean, height, radius] of [[.4, .012, .22, .088, .024], [2.6, .01, .14, .078, .022], [4.5, .014, .27, .083, .025]]) {
		flower(azimuth + between(-.2, .2), offset, lean + between(-.04, .04), height * between(.95, 1.05), radius, between(0, 1));
	}

	const parts = [
		{ color: LEAF, geometry: smooth(leaves) },
		{ color: VIOLET, sway: .5, geometry: helpers.merge(tubes) },
		{ color: LILAC, sway: .5, geometry: helpers.merge(cups) },
		{ color: STIGMA, sway: .5, geometry: helpers.merge(stigmas) },
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .12;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
