// La poche d'eau, d'après les photos de la fiche (poche-eau.reference.md) : une réserve souple d'irrigation, grand coussin
// turquoise posé à même le sol, rectangulaire aux angles très arrondis, pleine et bombée, de 10 × 5 × 1,6 m, longueur selon X. La
// toile gonfle au tiers de sa hauteur, là où court la soudure cerclée de rouge, puis s'arrondit en épaules pleines jusqu'au milieu
// du dessus, son point le plus haut. Elle n'a pas de sortie : la vanne et le tuyau se posent à part, par les calques d'irrigation.
export function build(THREE, helpers) {
	const { P, drum, collector, measure } = helpers;
	const { parts, add } = collector();
	const L = 10, l = 5, H = 1.6;
	const a = L / 2, b = l / 2, R = Math.min(a, b) * .8, CORNER = 6;

	// Le turquoise franc de la toile, et le même plus sombre pour le flanc sous la soudure, que son propre ventre ombre : P n'a
	// rien d'approchant, vitre est trop pâle et bleu tire vers le bleu roi
	const TOILE = '#3fa3b0', TOILE_OMBRE = '#2c8592';
	// Le profil de la toile, du sol au dessus : retrait par rapport au plus large, pour 5 m de large, et hauteur en part de H
	const profile = [[.35, 0], [.08, .12], [0, .3], [.06, .48], [.22, .64], [.55, .78], [1.05, .89], [1.75, .97]];
	const seam = 2;

	// Un anneau du contour arrondi, en retrait de inset : CORNER + 1 points par angle, les côtés droits entre deux angles
	const ring = (inset, y) => {
		const points = [], r = Math.max(R - inset, .12);
		for(const [cx, cz, start] of [[a - R, b - R, 0], [-(a - R), b - R, Math.PI / 2], [-(a - R), -(b - R), Math.PI], [a - R, -(b - R), Math.PI * 1.5]]) {
			for(let i = 0; i <= CORNER; i++) {
				const t = start + i / CORNER * Math.PI / 2;
				points.push([cx + Math.cos(t) * r, y, cz + Math.sin(t) * r]);
			}
		}
		return points;
	};

	// La toile, en deux bandes lissées qui se rejoignent sous la soudure, dont le ruban couvre le pli des normales
	const rings = profile.map(([inset, y]) => ring(inset, y * H));
	const count = rings[0].length;
	const skin = (from, to, summit) => {
		const positions = rings.slice(from, to + 1).flat(), index = [];
		for(let k = 0; k < to - from; k++) {
			for(let i = 0; i < count; i++) {
				const j = (i + 1) % count, p = k * count;
				index.push(p + i, p + count + i, p + j, p + j, p + count + i, p + count + j);
			}
		}
		// Le dessus se referme sur son point le plus haut, au milieu : l'eau bombe la toile jusque-là
		if(summit) {
			const top = positions.length, last = (to - from) * count;
			positions.push([0, H, 0]);
			for(let i = 0; i < count; i++) {
				index.push(last + i, top, last + (i + 1) % count);
			}
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(positions.flat(), 3));
		g.setIndex(index);
		g.computeVertexNormals();
		return g;
	};
	add(skin(0, seam, false), TOILE_OMBRE);
	add(skin(seam, rings.length - 1, true), TOILE);

	// La soudure cerclée de rouge, au plus large : un ruban à peine sorti de la toile
	const y = profile[seam][1] * H, band = .05;
	const low = ring(-.025, y - band), high = ring(-.025, y + band);
	const strip = [];
	for(let i = 0; i < count; i++) {
		const j = (i + 1) % count;
		strip.push(...low[i], ...high[i], ...low[j], ...low[j], ...high[i], ...high[j]);
	}
	const belt = new THREE.BufferGeometry();
	belt.setAttribute('position', new THREE.Float32BufferAttribute(strip, 3));
	belt.computeVertexNormals();
	add(belt, P.rouge);

	// Le bouchon d'évent sur le dessus, vers le bout +X
	add(drum(a - 1.6, H * .93, 0, .12, .1, 6), P.gris);

	const { d, h } = measure(parts);
	return { d, h, aligned: false, parts };
}
