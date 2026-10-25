// Blette, pensée pour se lire à une vingtaine de pixels : une touffe haute et dressée de grandes feuilles ovales vert sombre,
// luisantes et cloquées, portées par des côtes larges et charnues jaune d'or et rouge cerise, qui montent du pied jusqu'au
// milieu du limbe. La betterave montre un dôme pourpre au pied et des feuilles petites, le pak-choï un vase trapu blanc
// serré en bulbe, la rhubarbe des pétioles rouges seuls sous des feuilles immenses : ici le trait propre est la couleur vive
// des côtes sous le vert sombre, et c'est là que porte le plus fort contraste. Un plant réel n'a qu'une couleur de côte ;
// la scène instancie la même géométrie sur tout le rang, et le mélange jaune et rouge tient lieu du rang arc-en-ciel.
// Contrat et budget : base/scene/CLAUDE.md (plafond 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const LEAF = '#2b5f27'; const YELLOW = '#f2c230'; const RED = '#d4283f'; const WHITE = '#f1eedc';

	// Géométrie libre : une liste de triangles [[x, y, z] × 3], normales par face (les cloques se lisent pan par pan)
	const mesh = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};

	// Feuilles : sept, en touffe. Les extérieures s'ouvrent et retombent au bout, celle du cœur reste dressée. Chacune varie en
	// azimut, en ouverture, en longueurs, en largeur et en retombée, et porte sa couleur de côte.
	// [azimut, ouverture de la côte, longueur du pétiole, longueur du limbe, demi-largeur du limbe, retombée, couleur]
	const leaves = [
		[.15, .42, .2, .23, .095, .36, YELLOW],
		[1.05, .3, .22, .25, .1, .3, RED],
		[2.0, .44, .18, .22, .09, .4, YELLOW],
		[2.95, .36, .21, .24, .102, .34, RED],
		[3.9, .46, .19, .21, .088, .38, WHITE],
		[4.85, .33, .21, .25, .098, .3, RED],
		[5.7, .14, .23, .23, .085, .2, YELLOW],
	];
	// Demi-contour du limbe, en (avancée, écart) : base en cœur un peu ramenée vers le pétiole, le plus large au tiers, bout
	// arrondi. Chaque demi-limbe décale ses points d'après son rang, pour qu'aucun ne soit le décalque d'un autre.
	const edge = [[.04, .66], [.36, 1], [.72, .86]];
	const shift = [[.05, -.06, .07], [-.04, .05, -.05], [.02, .07, -.07]];
	// Cloques : le bord se soulève ou se creuse par rapport à la nervure, d'après le rang et le côté
	const pucker = [[.22, -.16, .2], [-.18, .2, -.14], [.14, .22, -.2]];
	const blades = [], ribs = new Map([[YELLOW, []], [RED, []], [WHITE, []]]);
	for(const [rank, [azimuth, open, petiole, length, width, droop, color]] of leaves.entries()) {
		const foot = [.018, 0];
		const top = [foot[0] + Math.sin(open) * petiole, foot[1] + Math.cos(open) * petiole];

		// Côte : un ruban plat dessus et bombé dessous, large et charnu, qui se resserre à peine jusqu'au limbe. Le bombé
		// est porté vers l'extérieur de la touffe, là où l'œil le voit.
		const out = [Math.cos(open), -Math.sin(open)], thick = .016;
		const section = (p, half) => [
			[p[0], p[1], -half],
			[p[0] + out[0] * thick, p[1] + out[1] * thick, 0],
			[p[0], p[1], half],
		];
		const s0 = section(foot, .03), s1 = section(top, .024);
		const rib = [[s0[0], s1[0], s0[1]], [s0[1], s1[0], s1[1]], [s0[1], s1[1], s0[2]], [s0[2], s1[1], s1[2]]];

		// Limbe : la nervure s'ouvre de `open` à `open + droop` vers le bout. La nervure est relevée, les bords baissés, et
		// chaque bord se soulève ou se creuse (cloques) ; la face du dessus regarde le cœur de la touffe.
		const along = u => {
			const a = open + droop * u, mid = open + droop * u * .5;
			return [top[0] + Math.sin(mid) * u * length, top[1] + Math.cos(mid) * u * length, a];
		};
		// Le limbe pivote un peu sur sa nervure (`roll`) : une feuille vue de profil montre encore sa face au lieu de se réduire à une lame
		const roll = [.6, -.35, .65, -.45, .7, -.4, .2][rank];
		const point = (u, v, bump = 0, rise = 0) => {
			const [x, y, a] = along(u), lift = .16 * width * (1 - Math.abs(v)) + bump * width + rise;
			return [x - Math.cos(a) * lift, y + Math.sin(a) * lift + roll * v * width, v * width];
		};
		const tris = [];
		for(const side of [-1, 1]) {
			const k = (rank + (side > 0 ? 1 : 0)) % 3, jitter = shift[k];
			const e = edge.map(([u, v], i) => point(u + jitter[i] * .5, side * v * (1 + jitter[i]), pucker[k][i] * (side > 0 ? 1 : -.8)));
			const r0 = point(0, 0), r1 = point(.42, 0), r2 = point(.72, 0), tip = point(1, 0);
			tris.push([r0, e[0], e[1]], [r0, e[1], r1], [r1, e[1], e[2]], [r1, e[2], r2], [r2, e[2], tip]);
		}
		blades.push(mesh(tris).rotateY(azimuth));
		// La côte file dans le limbe jusqu'au milieu, posée sur la face du dessus, aussi large au départ que le pétiole
		const a0 = point(0, -.26, 0, .005), b0 = point(0, .26, 0, .005), m = point(.62, 0, 0, .005);
		rib.push([a0, m, b0]);
		const a1 = point(.02, -.27, 0, -.005), b1 = point(.02, .27, 0, -.005);
		rib.push([a1, b1, m]);
		ribs.get(color).push(mesh(rib).rotateY(azimuth));
	}

	const merge = list => helpers.merge(list);
	const parts = [
		// Les limbes balancent, les côtes à peine
		{ color: LEAF, sway: .5, geometry: merge(blades) },
		...[YELLOW, RED, WHITE].map(color => ({ color, sway: .2, geometry: merge(ribs.get(color)) })),
	];
	// Le socle ramène l'emprise à d et la hauteur à h indépendamment : h se déduit de la forme pour qu'elle ne soit pas étirée
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3()), d = .48;
	return { d, h: d * size.y / Math.max(size.x, size.z), parts };
}
