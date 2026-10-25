// Tulipe en fleur coupée, pensée pour se lire en parterre sur un rang à 20 px/m : deux bulbes par touffe, donc deux tiges
// épaisses et raides qui portent chacune un gobelet fermé en œuf, rose vif à fond carmin, dressé dans leur axe, et au pied
// trois larges feuilles glauques en lame, pliées sur leur nervure et arquées vers l'extérieur. Le plus fort contraste est
// entre le rose des gobelets et le vert bleuté du pied.
// Ce qui la sépare du coquelicot : un gobelet haut et fermé, sans cœur noir, au lieu d'une coupe ouverte à plat ; des tiges
// droites et épaisses au lieu de tiges fines penchées ; des feuilles larges et entières qui montent au tiers de la tige au
// lieu d'une rosette basse découpée ; du rose et non du rouge. Des alliums : une tête colorée au sommet des lames.
// Fiche : tulipe.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const PETAL = '#ef5b93'; const DEEP = '#b3285f'; const LEAF = '#6f9483'; const STEM = '#8fb05c';

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
			if(n.y < 0) n.negate();
			for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
		}
		return geometry(tris, c => normals.get(key(c)).clone().normalize());
	};

	const direction = (azimuth, lean) => new THREE.Vector3(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	const flatAt = (azimuth, r, y = 0) => new THREE.Vector3(Math.cos(azimuth) * r, y, Math.sin(azimuth) * r);

	// Une feuille : une lame large dans son tiers bas, pliée en gouttière sur une nervure qui monte puis s'arque vers
	// l'extérieur. Les deux bords partent du pied, s'écartent au tiers, se resserrent sur la nervure aux trois quarts et
	// finissent en pointe : le contour se lit en lame de lance et non en losange. 6 triangles.
	const leaves = [];
	const leaf = (azimuth, foot, lean, length, width) => {
		const out = flatAt(azimuth, 1).normalize();
		const side = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const along = t => foot.clone().addScaledVector(direction(azimuth, lean + t * t * .3), length * t);
		const m1 = along(.3), m2 = along(.72), tip = along(1);
		// Les bords sont rentrés vers la tige et un peu plus bas que la nervure : la lame se plie en gouttière ouverte vers le dedans
		const edge = (t, sign, w) => along(t).addScaledVector(side, sign * w).addScaledVector(out, -w * .14).add(new THREE.Vector3(0, -w * .12, 0));
		const l1 = edge(.3, 1, width * between(.45, .55)), r1 = edge(.3, -1, width * between(.45, .55));
		leaves.push(...[
			[foot, l1, m1], [foot, m1, r1],
			[l1, m2, m1], [m1, m2, r1],
			[l1, tip, m2], [m2, tip, r1],
		].map(t => t.map(v => v.toArray())));
	};

	// Une tige : deux triangles pliés, du pied au fond du gobelet, épais et droits
	const stems = [];
	const stalk = (from, to, width) => {
		const axis = to.clone().sub(from);
		const side = new THREE.Vector3(0, 1, 0).cross(axis).normalize().multiplyScalar(width / 2);
		const ridge = side.clone().cross(axis).normalize().multiplyScalar(width * .5);
		stems.push(
			[from.clone().add(side).toArray(), from.clone().add(ridge).toArray(), to.toArray()],
			[from.clone().add(ridge).toArray(), from.clone().sub(side).toArray(), to.toArray()],
		);
	};

	// Un gobelet : un fond en éventail depuis le haut de la tige vers un anneau de six points, au plus large aux deux
	// cinquièmes de la hauteur, puis six pointes de tépales un peu resserrées, les trois extérieures plus hautes que les trois
	// intérieures, ce qui festonne le bord. Le fond est carmin, le haut rose : l'œuf se lit rond et fermé. Les normales
	// rayonnent depuis l'axe, sans arête. Le dedans se voit d'en haut, le matériau est double face.
	// Par fleur : fond 6 + flanc 12 + tige 2 = 20 triangles.
	const bottoms = [], tops = [];
	const goblet = (azimuth, offset, lean, height, size, phase) => {
		const axis = direction(azimuth, lean);
		const tilt = new THREE.Vector3(-Math.sin(azimuth), 0, Math.cos(azimuth));
		const foot = flatAt(azimuth, offset);
		const base = foot.clone().addScaledVector(axis, height);
		const place = (r, a, y) => new THREE.Vector3(Math.cos(a) * r, y, Math.sin(a) * r).applyAxisAngle(tilt, -lean).add(base);
		const radius = size * .38, tall = size;
		const ring = [], tips = [];
		for(let k = 0; k < 6; k++) {
			const a = phase + k * Math.PI / 3;
			ring.push(place(radius * between(.93, 1.05), a, tall * .32));
			tips.push(place(radius * between(.74, .8), a + Math.PI / 6, tall * (k % 2 ? .9 : 1) * between(.97, 1.03)));
		}
		const normalOf = c => {
			const v = new THREE.Vector3(...c);
			if(v.distanceTo(base) < 1e-6) return axis.clone().negate();
			const h = v.clone().sub(base).dot(axis);
			const radial = v.clone().sub(base).projectOnPlane(axis).normalize();
			return radial.addScaledVector(axis, h < tall * .5 ? -.35 : .25).normalize();
		};
		bottoms.push(geometry(ring.map((p, k) => [base.toArray(), p.toArray(), ring[(k + 1) % 6].toArray()]), normalOf));
		const flank = [];
		for(let k = 0; k < 6; k++) {
			const next = (k + 1) % 6;
			flank.push([ring[k].toArray(), tips[k].toArray(), ring[next].toArray()]);
			flank.push([tips[k].toArray(), tips[next].toArray(), ring[next].toArray()]);
		}
		tops.push(geometry(flank, normalOf));
		stalk(foot, base, .011);
	};

	// Deux bulbes côte à côte : la plus haute fleur à 0,45 m, la seconde plus bas, chacune à peine écartée de la verticale
	const first = between(0, Math.PI * 2);
	for(const [azimuth, offset, lean, height, size] of [[first, .02, .06, .385, .074], [first + Math.PI + between(-.4, .4), .02, .1, .315, .07]]) {
		goblet(azimuth, offset, lean + between(-.02, .02), height * between(.97, 1.03), size * between(.95, 1.05), between(0, 1));
	}

	// Deux grandes feuilles basales qui s'ouvrent de part et d'autre, une petite qui embrasse une tige plus haut
	leaf(first + Math.PI / 2 + between(-.3, .3), flatAt(first + Math.PI / 2, .01, .004), between(.05, .08), between(.18, .2), between(.08, .088));
	leaf(first - Math.PI / 2 + between(-.3, .3), flatAt(first - Math.PI / 2, .01, .004), between(.07, .1), between(.16, .18), between(.072, .08));
	leaf(first + between(-.4, .4), flatAt(first, .018, .07), between(.06, .1), between(.1, .12), between(.05, .056));

	const parts = [
		{ color: LEAF, sway: .5, geometry: smooth(leaves) },
		{ color: STEM, sway: .8, geometry: smooth(stems) },
		{ color: DEEP, sway: .8, geometry: helpers.merge(bottoms) },
		{ color: PETAL, sway: .8, geometry: helpers.merge(tops) },
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
