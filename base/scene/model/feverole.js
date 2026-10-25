// Féverole, pensée pour se lire en rang dense à 20 px/m : une seule tige carrée, raide, en chandelle étroite, garnie de
// paires de folioles ovales gris vert bleuté. Sur la moitié haute, des fleurs blanches à macule noire serrées à l'aisselle ;
// en dessous, de petites gousses courtes dressées contre la tige, vertes puis noires vers le pied.
// Ce qui la sépare de la fève : une tige au lieu d'une touffe, plus haute, plus étroite, plus grise, et des gousses en
// petits traits sombres collés à la tige au lieu de grosses gousses posées par le socle.
// Fiche : feverole.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#8fa49a'; const STEM = '#4a6744'; const FLOWER = '#f6f5ec'; const BLACK = '#1d1a20';
	const HEIGHT = 1.1;

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};
	const add = (a, b, k = 1) => [a[0] + b[0] * k, a[1] + b[1] * k, a[2] + b[2] * k];
	const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
	const unit = a => { const n = Math.hypot(...a); return a.map(c => c / n); };
	// Direction d'azimut a, levée de rise au-dessus de l'horizontale
	const toward = (a, rise) => [Math.cos(a) * Math.cos(rise), Math.sin(rise), Math.sin(a) * Math.cos(rise)];

	const leaflets = [], flowers = [], greens = [], blacks = [];

	// Foliole ovale : cinq points de contour en éventail depuis la base, trois triangles. Le ventre est plus près de la base
	// d'un côté que de l'autre et les bords sont baissés sous la nervure : un ovale plié, jamais un losange.
	const leaflet = (base, dir, length, width, skew) => {
		const side = unit(cross(dir, [0, 1, 0])), normal = unit(cross(side, dir));
		const at = (u, v) => add(add(add(base, dir, u * length), side, v * width), normal, -.35 * width * Math.abs(v));
		const p = [at(0, 0), at(.3 + skew, .5), at(.78, .42), at(1, 0), at(.55 - skew, -.52)];
		for(let k = 1; k < 4; k++) leaflets.push([p[0], p[k], p[k + 1]]);
	};

	// Gousse courte dressée contre la tige : une lame en fuseau et une arête perpendiculaire, pour garder une épaisseur de
	// quelque côté qu'on la regarde. 3 triangles.
	const pod = (list, y, a, length) => {
		const out = toward(a, 0), side = [-out[2], 0, out[0]], dir = unit(add([0, 1, 0], out, .22));
		const base = add([0, y, 0], out, .02), tip = add(base, dir, length), belly = add(base, dir, length * .45);
		list.push([add(belly, side, -.024), tip, add(belly, side, .024)], [base, add(belly, side, -.024), add(belly, side, .024)], [base, add(belly, out, .028), tip]);
	};

	// Onze nœuds de 0,18 à 0,99 m, chacun tourné de 2,4 rad sur le précédent. Les feuilles sont longues et ouvertes au
	// milieu, plus courtes et plus dressées vers la pointe.
	const COUNT = 11;
	for(let i = 0; i < COUNT; i++) {
		const t = i / (COUNT - 1);
		const y = HEIGHT * (.16 + .74 * t) + (i % 2) * .012;
		const a = .5 + i * 2.4 + (i % 3) * .18;
		const node = [0, y, 0];
		const rise = .3 + t * .55 + (i % 2) * .08;
		const petiole = add(node, toward(a, rise + .2), .035);
		const length = (.16 - Math.abs(t - .4) * .09) * (1 + ((i % 3) - 1) * .12), width = length * .56;
		const open = .5 + (i * 3 % 4) * .06;
		leaflet(petiole, toward(a + open, rise), length, width, .06 * (i % 3 - 1));
		leaflet(petiole, toward(a - open * .85, rise + .1), length * .9, width, -.05 * (i % 2));

		if(i >= 5) {
			// Fleur contre la tige, tournée à l'écart de sa feuille pour ne pas passer dessous, couchée vers l'extérieur et offerte au ciel (la lumière vient d'en haut), la macule noire
			// posée sur l'aile, juste au-dessus de la corolle
			const out = a + 1.2 + (i % 2) * .15, dir = toward(out, 0), side = [-dir[2], 0, dir[0]];
			const c = add(add(node, dir, .03), [0, 1, 0], .02);
			const lift = toward(out, .4), p = (h, w) => add(add(c, lift, h), side, w);
			const petals = [p(0, 0), p(.055, .062), p(.13, .014), p(.06, -.058)];
			flowers.push([petals[0], petals[2], petals[1]], [petals[0], petals[3], petals[2]]);
			const front = toward(out, .4 + Math.PI / 2).map(c => c * .006);
			blacks.push([add(p(.04, -.006), front), add(p(.088, -.032), front), add(p(.086, .03), front)]);
		} else {
			// Gousses sous les fleurs : vertes en haut, noires vers le pied, deux aux nœuds du milieu
			const list = i <= 3 ? blacks : greens;
			pod(list, y + .01, a + 1.3, .12 + (i % 2) * .02);
			if(i === 2 || i === 3) pod(list, y + .02, a - 1.5, .105);
		}
	}

	// La pointe : deux jeunes folioles serrées, presque droites, qui ferment la tige sans faire massue
	leaflet([0, HEIGHT - .09, 0], toward(1.1, 1.2), .09, .034, .05);
	leaflet([0, HEIGHT - .08, 0], toward(4.1, 1.25), .078, .03, -.05);

	// Tige carrée : quatre pans plats, un peu grossie pour rester un trait à l'écran
	const stem = helpers.facets(helpers.stem(.009, .015, HEIGHT - .06, 4).translate(0, (HEIGHT - .06) / 2, 0));

	return {
		d: .3, h: HEIGHT,
		parts: [
			{ color: LEAF, geometry: mesh(leaflets) },
			{ color: STEM, sway: .3, geometry: helpers.merge([stem, mesh(greens)]) },
			{ color: FLOWER, sway: .4, geometry: mesh(flowers) },
			{ color: BLACK, sway: .3, geometry: mesh(blacks) },
		],
	};
}
