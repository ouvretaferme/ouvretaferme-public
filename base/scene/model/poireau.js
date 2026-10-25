// Poireau, pensé pour se lire sur un rang à 20 px/m : un fût blanc, épais et court, tel qu'il sort de la butte, qui s'aplatit
// et s'élargit en une gaine vert pâle en éventail, deux fois plus large en haut qu'au pied. De cette gaine partent six feuilles
// larges, vert bleuté, repliées en V, alternées de part et d'autre : elles s'écartent d'abord en V, puis les plus vieilles
// s'arquent à mi-longueur et retombent, la pointe sous le coude. Les feuilles sortent du plan de l'éventail d'un quart de
// tour au plus, une de chaque côté : un plant vu par la tranche garde ainsi une largeur, au lieu de se réduire à une lame. Ce qui le sépare du palmier : aucune couronne
// en étoile au sommet d'un tronc fin, mais un éventail plat qui naît en V d'un fût épais ; de l'oignon et de la ciboule :
// ni bulbe, ni tubes dressés, des lames larges qui retombent. Fiche : poireau.reference.md.
// Contrat et budget : base/scene/CLAUDE.md (plafond relevé à 120 triangles). Les helpers (helpers.js) se lisent, ne se modifient pas.
export function build(THREE) {
	const SHAFT = '#f1ede0'; const PALE = '#b9cf98'; const LEAF = '#5f9488'; const DARK = '#3d6e66';

	// Normales lissées partagées par sommet, calculées sur toutes les listes ensemble : le fût et la gaine, peints de deux
	// couleurs, gardent une lumière continue à leur jonction, et le blanc passe au vert sans arête.
	const key = p => p.map(v => v.toFixed(5)).join(',');
	const smooth = lists => {
		const normals = new Map();
		for(const tris of lists) {
			for(const [p, q, r] of tris) {
				const a = new THREE.Vector3(...p), n = new THREE.Vector3(...q).sub(a).cross(new THREE.Vector3(...r).sub(a));
				for(const c of [p, q, r]) normals.set(key(c), (normals.get(key(c)) ?? new THREE.Vector3()).add(n));
			}
		}
		return lists.map(tris => {
			const g = new THREE.BufferGeometry();
			g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
			g.setAttribute('normal', new THREE.Float32BufferAttribute(tris.flat().flatMap(c => normals.get(key(c)).clone().normalize().toArray()), 3));
			return g;
		});
	};
	// Normales par face : la nervure pliée d'une feuille se lit par ses deux pans, l'un éclairé, l'autre dans l'ombre
	const faceted = tris => {
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
		g.computeVertexNormals();
		return g;
	};

	// Fût et gaine : un tube à six pans en trois étages, 36 triangles. Le pied, rond, s'évase à peine ; le haut du fût s'aplatit
	// vers une ellipse couchée dans le plan de l'éventail (x), d'où sortent les feuilles. L'éventail est plus large en haut
	// qu'au pied, comme sur la plante, et le contour ne casse pas au changement de couleur.
	const SHAFT_TOP = .14, SHEATH_MID = .25, SHEATH_TOP = .36;
	const ring = (y, rx, rz) => Array.from({ length: 6 }, (_, j) => [Math.cos(j * Math.PI / 3) * rx, y, Math.sin(j * Math.PI / 3) * rz]);
	const band = (low, high) => low.flatMap((p, j) => {
		const q = low[(j + 1) % 6], P = high[j], Q = high[(j + 1) % 6];
		return [[p, P, q], [q, P, Q]];
	});
	const foot = ring(0, .052, .05), neck = ring(SHAFT_TOP, .047, .043), middle = ring(SHEATH_MID, .062, .034), crown = ring(SHEATH_TOP, .1, .024);
	const [shaft, sheath] = smooth([band(foot, neck), [...band(neck, middle), ...band(middle, crown)]]);

	// Feuilles : six, alternes, l'axe d'une feuille dans le plan (x, y) de l'éventail, à quelques degrés près en azimut. Les
	// plus vieilles sortent plus bas et plus au bord, sont plus longues, et s'arquent jusqu'à retomber ; celles du cœur
	// restent dressées. La largeur est portée par z, en travers du plan : une lame se courbe sur le plat, comme un ruban, et
	// le dessus de la retombée se voit large depuis la caméra du jeu. Le pli en V lève les deux bords vers la face interne
	// (le côté qui regarde le fût, puis le ciel quand la feuille retombe) : une gouttière qui garde une épaisseur visible
	// même quand l'éventail est vu par la tranche.
	// [côté, azimut, écart du pied au fût, longueur, angles de la nervure depuis la verticale par segment, demi-largeur, sombre]
	// Les deux feuilles du cœur, dressées et serrées, finissent par une pointe plate d'un triangle : le pli ne s'y voit pas.
	const leaves = [
		[1, .34, .085, .5, [.45, 1.15, 2.2], .062, true],
		[-1, -.38, .08, .47, [.5, 1.2, 2.3], .06, true],
		[1, -.3, .055, .46, [.3, .95, 1.7], .056, false],
		[-1, .34, .05, .44, [.28, 1, 1.8], .054, false],
		[1, .1, .025, .42, [.13, .5], .047, false],
		[-1, -.12, .02, .36, [.1, .4], .043, false],
	];
	// Part de la longueur prise par chaque segment, selon le nombre de segments : un coude à mi-longueur pour toutes
	const RUNS = { 2: [.55, .45], 3: [.48, .28, .24] };
	const blades = { true: [], false: [] }, feet = [];
	// Hauteur sur laquelle le pied d'une feuille reste dans la gaine : il y prend naissance, vert pâle, et ne vire au bleuté
	// qu'en sortant. Une feuille qui démarre au bord de la gaine se lit posée sur le fût, pas sortie de lui.
	const ROOT = .11;
	for(const [side, azimuth, x0, length, angles, width, dark] of leaves) {
		// Stations le long de la nervure : [x, y, angle], la première au bord de la gaine
		const stations = [[side * x0, SHEATH_TOP, angles[0]]];
		angles.forEach((angle, k) => {
			const [x, y] = stations[k], run = RUNS[angles.length][k] * length;
			stations.push([x + side * Math.sin(angle) * run, y + Math.cos(angle) * run, angles[Math.min(k + 1, angles.length - 1)]]);
		});
		// Face interne d'une nervure qui monte vers `side` : vers le fût tant qu'elle se dresse, vers le ciel quand elle retombe
		const inner = angle => [-side * Math.cos(angle), Math.sin(angle), 0];
		// Bord à la station s, côté u (-1, 1), demi-largeur w : en travers sur z, relevé en V vers la face interne
		const edge = ([x, y, angle], u, w) => {
			const n = inner(angle);
			return [x + n[0] * .8 * w, y + n[1] * .8 * w, u * w * .75];
		};
		const rib = ([x, y]) => [x, y, 0];
		const widths = angles.length === 3 ? [.8, 1, .8] : [.8, 1];
		const tris = [];
		const [s0, s1] = stations;
		// Pied pâle : il remonte la gaine dans l'axe où la feuille part, plus étroit en bas, et sort d'elle sur le premier tiers
		// de la lame avant de virer au bleuté, comme la gaine d'une feuille qui s'en détache
		const root = [s0[0] - side * Math.sin(s0[2]) * ROOT, SHEATH_TOP - Math.cos(s0[2]) * ROOT, s0[2]];
		const out = [s0[0] + (s1[0] - s0[0]) * .3, s0[1] + (s1[1] - s0[1]) * .3, s0[2]], outWidth = widths[0] + (widths[1] - widths[0]) * .3;
		const foot = [];
		foot.push([edge(root, -1, .55 * width), edge(out, -1, outWidth * width), rib(out)]);
		foot.push([edge(root, -1, .55 * width), rib(out), edge(root, 1, .55 * width)]);
		foot.push([edge(root, 1, .55 * width), rib(out), edge(out, 1, outWidth * width)]);
		feet.push(faceted(foot).rotateY(azimuth));
		// Lame : deux bords dans le prolongement du pied, la nervure n'y est pas encore pliée
		tris.push([edge(out, -1, outWidth * width), edge(s1, -1, width * widths[1]), rib(s1)]);
		tris.push([edge(out, -1, outWidth * width), rib(s1), edge(out, 1, outWidth * width)]);
		tris.push([edge(out, 1, outWidth * width), rib(s1), edge(s1, 1, width * widths[1])]);
		// Segments pliés, jusqu'à la pointe, qui n'a plus de largeur
		const heart = !dark && angles[0] < .12;
		if(heart) {
			const [, a, b] = stations;
			tris.push([edge(a, -1, width), rib(b), edge(a, 1, width)]);
		}
		for(let k = 1; k < stations.length - 1 && !heart; k++) {
			const a = stations[k], b = stations[k + 1], last = k === stations.length - 2;
			for(const u of [-1, 1]) {
				const flip = u > 0 ? t => t : t => [t[0], t[2], t[1]];
				if(last) {
					tris.push(flip([rib(a), edge(a, u, width * widths[k]), rib(b)]));
				} else {
					tris.push(flip([rib(a), edge(a, u, width * widths[k]), edge(b, u, width * widths[k + 1])]));
					tris.push(flip([rib(a), edge(b, u, width * widths[k + 1]), rib(b)]));
				}
			}
		}
		blades[dark].push(faceted(tris).rotateY(azimuth));
	}
	const merge = list => {
		const g = new THREE.BufferGeometry();
		const join = name => new THREE.Float32BufferAttribute(list.flatMap(part => Array.from(part.attributes[name].array)), 3);
		g.setAttribute('position', join('position'));
		g.setAttribute('normal', join('normal'));
		return g;
	};

	const parts = [
		{ color: DARK, geometry: merge(blades.true) },
		{ color: LEAF, geometry: merge(blades.false) },
		// Le vent déplace chaque sommet en proportion de sa hauteur : gaine et fût prennent le balancement des feuilles, sans
		// quoi elles glissent sur un pied immobile. Le bas du fût, près du sol, ne bouge presque pas
		{ color: PALE, geometry: merge([sheath, ...feet]) },
		{ color: SHAFT, geometry: shaft },
	];
	// Le modèle est en mètres : emprise et hauteur se lisent sur la boîte, pour que le socle ne l'étire pas
	const box = new THREE.Box3();
	for(const part of parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	const size = box.getSize(new THREE.Vector3());
	return { d: Math.max(size.x, size.z), h: size.y, parts };
}
