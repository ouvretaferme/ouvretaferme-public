// Scabieuse des jardins en fleur, pensée pour se lire en parterre sur un rang à 20 px/m : un tapis bas de feuilles lyrées
// gris-vert, d'où montent des tiges raides portant chacune une pelote : un dôme rond, bombé, bordeaux ou mauve, piqué de
// points blancs (les étamines en épingles), sans collerette en étoile. Deux boutons ronds plus bas, et sur deux tiges, à
// mi-hauteur, une feuille découpée en lanières.
// Le plus fort contraste est entre le blanc des épingles et le bordeaux de la pelote, puis entre la pelote et le gris-vert.
// Ce qui la sépare du cosmos : pas de marguerite plate en étoile à cœur jaune, une boule pleine de la même couleur jusqu'au
// bord ; pas de buisson de plumes jusqu'aux deux tiers, un tapis au pied et des tiges presque nues. De la nigelle : deux
// fois plus haute, des têtes sombres et bombées au lieu de soucoupes pâles. Du coquelicot, qui a le même port : une
// pelote bombée au lieu d'une coupe plate rouge à cœur noir, et des boutons ronds dressés au lieu de crosses penchées.
// Fiche : scabieuse.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond 60 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE, helpers) {
	const WINE = '#7b1d43'; const MAUVE = '#a77fc4'; const PIN = '#f4eff4'; const LEAF = '#739a58';

	// Hasard à graine fixe : chaque feuille et chaque tête varie, et la touffe reste la même d'un chargement à l'autre
	let seed = 97;
	const rand = () => {
		seed = Math.imul(seed ^ (seed >>> 15), 2246822507) ^ Math.imul(seed ^ (seed >>> 13), 3266489909);
		return (seed >>> 0) / 4294967296;
	};
	const between = (min, max) => min + rand() * (max - min);

	// Le matériau est double face : three retourne la normale d'une face vue de dos, si bien qu'un triangle tourné à l'envers
	// s'éteint quelle que soit la normale qu'on lui donne. Chaque triangle est donc remis dans le sens de sa normale voulue.
	const geometry = (tris, normalOf) => {
		tris = tris.map(([p, q, r]) => {
			const a = new THREE.Vector3(...p), face = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
			const wanted = normalOf(p).add(normalOf(q)).add(normalOf(r));
			return face.dot(wanted) < 0 ? [p, r, q] : [p, q, r];
		});
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normalOf(c).toArray()), 3));
		return g;
	};
	const v = (x, y, z) => new THREE.Vector3(x, y, z);
	const toward = (azimuth, lean) => v(Math.sin(lean) * Math.cos(azimuth), Math.cos(lean), Math.sin(lean) * Math.sin(azimuth));
	// Feuillage et tiges s'éclairent comme une touffe : normale tirée de l'axe et penchée vers le ciel, sans face noire
	const tuftNormal = c => v(c[0], 0, c[2]).normalize().multiplyScalar(.6).add(v(0, 1, 0)).normalize();

	// Le tapis : six feuilles couchées en éventail, assez longues pour que deux pieds posés à 0,7 × d se touchent sur le rang.
	// Chacune est lyrée : un lobe terminal large et tronqué, et un lobe latéral plus bas, d'un côté puis de l'autre.
	// Par feuille : 2 triangles, 12 pour six.
	const leaves = [];
	const phase = between(0, Math.PI * 2);
	for(let k = 0; k < 6; k++) {
		const a = phase + k * Math.PI * 2 / 6 + between(-.25, .25);
		const length = between(.2, .24), rise = between(.05, .08), width = between(.09, .105), sign = k % 2 ? 1 : -1;
		const out = v(Math.cos(a), 0, Math.sin(a)), side = v(-Math.sin(a), 0, Math.cos(a));
		const along = t => v(0, .01, 0).addScaledVector(out, .01 + length * t).add(v(0, rise * Math.sin(t * Math.PI * .6) / Math.sin(Math.PI * .6), 0));
		leaves.push([along(.3).toArray(), along(1).addScaledVector(side, width * .55).toArray(), along(.92).addScaledVector(side, -width * .55).toArray()]);
		leaves.push([along(0).toArray(), along(.55).toArray(), along(.38).addScaledVector(side, sign * width * between(.75, .95)).add(v(0, .012, 0)).toArray()]);
	}

	// Une tige : un ruban effilé, pointe au collet, plus large sous la tête qu'elle porte, pour tenir en silhouette à 64 px là
	// où le pied ne la cache plus. Sa largeur est tournée de côté pour qu'on la voie aussi d'en haut. 1 triangle.
	const stems = [];
	const stalk = (foot, top, width) => {
		const side = top.clone().sub(foot).cross(v(0, 1, 0));
		if(side.lengthSq() < 1e-8) side.set(1, 0, 0);
		side.normalize().multiplyScalar(width / 2);
		stems.push([foot.toArray(), top.clone().add(side).toArray(), top.clone().sub(side).toArray()]);
	};

	// Une feuille de tige, découpée en deux lanières écartées en V, posée à mi-hauteur. 2 triangles.
	const stemLeaves = [];
	const cutLeaf = (base, azimuth) => {
		const out = v(Math.cos(azimuth), 0, Math.sin(azimuth)), side = v(-Math.sin(azimuth), 0, Math.cos(azimuth));
		for(const spread of [-.5, .45]) {
			const dir = out.clone().addScaledVector(side, spread * between(.85, 1.15)).normalize();
			const tip = base.clone().addScaledVector(dir, between(.09, .11)).add(v(0, between(.02, .045), 0));
			const across = side.clone().multiplyScalar(.012);
			stemLeaves.push([base.clone().add(across).toArray(), base.clone().sub(across).add(v(0, .015, 0)).toArray(), tip.toArray()]);
		}
	};

	// Une pelote : un dôme plein et bombé, six pans montés vers un sommet mousse, le bord rabattu un peu sous le centre pour
	// que le contour se lise arrondi et non en étoile. Les normales partent d'un point sous la tête : l'ombre tourne autour
	// comme sur une boule. Deux épingles blanches sont piquées sur le haut, à des places différentes.
	// Par tête : dôme 6 + épingles 2 = 8 triangles.
	const heads = { [WINE]: [], [MAUVE]: [] }, pins = [];
	const head = (center, axis, radius, color) => {
		const u = v(1, 0, 0).projectOnPlane(axis).normalize(), w = axis.clone().cross(u);
		const at = (a, r, lift) => center.clone().addScaledVector(u, Math.cos(a) * r).addScaledVector(w, Math.sin(a) * r).addScaledVector(axis, lift);
		const start = between(0, Math.PI * 2), sixth = Math.PI / 3;
		const tip = at(0, 0, radius * between(.56, .62));
		const rim = Array.from({ length: 6 }, (_, k) => at(start + k * sixth + between(-.08, .08), radius * between(.94, 1.04), -radius * .22));
		const heart = center.clone().addScaledVector(axis, -radius * .5);
		heads[color].push(geometry(rim.map((c, k) => [tip.toArray(), c.toArray(), rim[(k + 1) % 6].toArray()]), c => v(...c).sub(heart).normalize()));
		const turn = between(0, Math.PI * 2);
		for(const [a, r, size] of [[turn, radius * .22, radius * .26], [turn + between(2.2, 2.8), radius * .58, radius * .22]]) {
			const spot = at(a, r, radius * .59 * (1 - r / radius) - radius * .22 * r / radius);
			const normal = spot.clone().sub(heart).normalize();
			const p = spot.clone().addScaledVector(normal, radius * .06);
			const e1 = normal.clone().cross(v(0, 0, 1)).normalize().multiplyScalar(size), e2 = normal.clone().cross(e1).normalize().multiplyScalar(size);
			pins.push(geometry([[p.clone().add(e1), p.clone().addScaledVector(e1, -.5).add(e2), p.clone().addScaledVector(e1, -.5).sub(e2)].map(c => c.toArray())], () => normal.clone()));
		}
	};

	// Un bouton : une boule dressée en quatre pans autour d'un sommet mousse, aux normales lissées. 4 triangles.
	const bud = (top, radius, color) => {
		const tip = top.clone().add(v(0, radius * .95, 0)), heart = top.clone().add(v(0, radius * .1, 0));
		const turn = between(0, Math.PI * 2);
		const ring = [0, 1, 2, 3].map(k => top.clone().add(v(Math.cos(turn + k * Math.PI / 2) * radius, radius * .15, Math.sin(turn + k * Math.PI / 2) * radius)));
		heads[color].push(geometry(ring.map((c, j) => [tip.toArray(), c.toArray(), ring[(j + 1) % 4].toArray()]), c => v(...c).sub(heart).normalize()));
	};

	// Quatre têtes ouvertes étagées dans le dernier quart, écartées du centre, et deux boutons plus bas : le mélange d'un semis,
	// bordeaux et mauve. Chaque tête a sa tige, qui part du collet ; celle de la tête mauve porte une feuille découpée.
	// Tapis 12, tiges 6, feuille de tige 2, têtes 4 × 8 = 32, boutons 2 × 4 = 8 : 60 triangles.
	const spots = [[.4, .15, .74, .18, WINE], [2.0, .17, .63, .25, MAUVE], [3.4, .13, .69, .15, WINE], [5.0, .17, .58, .28, WINE]];
	for(const [azimuth, offset, height, tilt, color] of spots) {
		const a = azimuth + between(-.25, .25);
		const top = v(Math.cos(a) * offset * between(.9, 1.1), height * between(.98, 1.02), Math.sin(a) * offset * between(.9, 1.1));
		const foot = v(Math.cos(a) * .012, 0, Math.sin(a) * .012);
		stalk(foot, top, .02);
		if(color === MAUVE) {
			cutLeaf(foot.clone().lerp(top, between(.42, .5)), a + between(-.6, .6));
		}
		head(top, toward(a, tilt + between(-.08, .08)), between(.082, .09), color);
	}
	for(const [azimuth, offset, height, color] of [[1.2, .12, .5, WINE], [4.3, .11, .44, MAUVE]]) {
		const a = azimuth + between(-.2, .2);
		const top = v(Math.cos(a) * offset, height * between(.97, 1.03), Math.sin(a) * offset);
		stalk(v(Math.cos(a) * .01, 0, Math.sin(a) * .01), top, .016);
		bud(top, .036, color);
	}

	const parts = [
		{ color: LEAF, sway: .3, geometry: geometry(leaves, tuftNormal) },
		{ color: LEAF, sway: .8, geometry: geometry(stems.concat(stemLeaves), tuftNormal) },
		{ color: WINE, sway: 1, geometry: helpers.merge(heads[WINE]) },
		{ color: MAUVE, sway: 1, geometry: helpers.merge(heads[MAUVE]) },
		{ color: PIN, sway: 1, geometry: helpers.merge(pins) },
	];
	return { d: .3, h: .75, parts };
}
