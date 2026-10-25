// Personnage paysan-c, sur le personnage du skill 3d-perso.
// Ce module est produit par l'outil de livraison du skill 3d-perso, qui y recopie le cadre commun du personnage. Il ne se
// retouche pas à la main : on corrige le skill ou la fiche, puis on relivre.

// ---------- pieces.js ----------
const module_pieces = (() => {
// Généré par Blender depuis afro.blend, bandeau-frontal.blend, barbe-longue.blend, bataille.blend, beret.blend, bouc.blend, cagoule.blend, carre.blend, casquette-plate.blend, casquette.blend, chapeau-brousse.blend, chapeau-conique.blend, charlotte.blend, chemise-carreaux.blend, cheveux-longs.blend, cow-boy.blend, debardeur.blend, degarnie.blend, epis-courts.blend, favoris.blend, fichu.blend, fleur.blend, frisee.blend, gants.blend, hijab-travail.blend, locks.blend, longs-degarnis.blend, longs-ondules.blend, longs-raie.blend, mi-longue.blend, moustache-fournie.blend, nattes-collees.blend, pied.blend, queue-de-cheval.blend, queue-tressee.blend, salopette.blend, tresse.blend, tresses-africaines.blend, tresses-dessus.blend, turban.blend, veste-travail.blend (perso_export.py, skill 3d-perso). Se retouche dans Blender puis se réexporte.
// Chaque pièce : ses cibles (`avatar`, `scenes` : livrer.mjs ne garde pour une cible que les pièces qui la nomment), ses
// réglages `{ nom: [base, forme clé] }` et ses morceaux, un par objet et par teinte, avec os, zone,
// condition, positions (three, figure de 1,8 m), index, formes clés en écarts et, pour une pièce teinte par sommet, `fondus`
// (trois doses par sommet). outils.js les monte par depuisBlender().
const PIECES = {
};

return { PIECES };
})();

// ---------- outils.js ----------
const module_outils = (() => {
// Outils communs du personnage. Aucun chargement de three : chaque fonction reçoit THREE en argument,
// pour que le même module se compte depuis node (compte.mjs) et se rende dans le banc. Il porte les
// grilles de révolution de la quille, que le corps, les vêtements et la coiffure partagent, et monte les pièces faites dans
// Blender (pieces.js).
const { PIECES } = module_pieces;

const ENCRE = '#1d4b53', CREME = '#fff6ee';

const triangles = g => (g.index ? g.index.count : g.attributes.position.count) / 3;

const mix = (THREE, a, b, t) => '#' + new THREE.Color(a).lerp(new THREE.Color(b), t).getHexString();

// Plus claire ou plus sombre d'un cran, en gardant la teinte : pour une couleur dérivée de la fiche, sans choix de plus
const nuance = (THREE, c, dl) => {
	const hsl = {};
	new THREE.Color(c).getHSL(hsl);
	return '#' + new THREE.Color().setHSL(hsl.h, hsl.s, Math.min(.95, Math.max(.04, hsl.l + dl))).getHexString();
};

// Fusionne des géométries en une seule, non indexée, position et normale seulement
function merge(THREE, geometries) {
	const list = geometries.filter(Boolean).map(g => {
		const n = g.index ? g.toNonIndexed() : g.clone();
		if(!n.attributes.normal) n.computeVertexNormals();
		return n;
	});
	const total = list.reduce((s, g) => s + g.attributes.position.count, 0);
	const pos = new Float32Array(total * 3), nor = new Float32Array(total * 3);
	let o = 0;
	for(const g of list) {
		pos.set(g.attributes.position.array, o);
		nor.set(g.attributes.normal.array, o);
		o += g.attributes.position.array.length;
	}
	const out = new THREE.BufferGeometry();
	out.setAttribute('position', new THREE.BufferAttribute(pos, 3));
	out.setAttribute('normal', new THREE.BufferAttribute(nor, 3));
	return out;
}

// Normales par face, pour un objet aux arêtes franches (papier, carton)
function flat(THREE, g) {
	const n = g.index ? g.toNonIndexed() : g;
	n.deleteAttribute('normal');
	n.computeVertexNormals();
	return n;
}

// Solide de révolution, profil [[rayon, y], …] du bas vers le haut
const lathe = (THREE, profile, sides = 12, phase = 0) =>
	new THREE.LatheGeometry(profile.map(([r, y]) => new THREE.Vector2(Math.max(r, 0), y)), sides, phase, Math.PI * 2);

const ell = (THREE, x, y, z, rx, ry, rz, w = 10, h = 7) => new THREE.SphereGeometry(1, w, h).scale(rx, ry, rz).translate(x, y, z);

// Capsule d'un point a à un point b
function limb(THREE, a, b, r, sides = 8, cap = 2) {
	const A = new THREE.Vector3(...a), B = new THREE.Vector3(...b);
	const len = A.distanceTo(B);
	const g = new THREE.CapsuleGeometry(r, Math.max(len, .001), cap, sides);
	const q = new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), B.clone().sub(A).normalize());
	g.applyQuaternion(q);
	g.translate((A.x + B.x) / 2, (A.y + B.y) / 2, (A.z + B.z) / 2);
	return g;
}

// Déplace chaque sommet le long de sa normale d'un bruit qui ne dépend que de sa position arrondie : deux sommets confondus
// bougent pareil, la surface ne s'ouvre pas. Pour la pâte à modeler
function bosseler(THREE, g, amp, graine = 1, freq = 9) {
	const h = (x, y, z) => {
		const s = Math.sin(x * 12.9898 * freq + y * 78.233 * freq + z * 37.719 * freq + graine * 3.17) * 43758.5453;
		return s - Math.floor(s);
	};
	const geo = g;
	geo.computeVertexNormals();
	const p = geo.attributes.position, n = geo.attributes.normal;
	const keyNormal = new Map();
	for(let i = 0; i < p.count; i++) {
		const k = `${p.getX(i).toFixed(4)},${p.getY(i).toFixed(4)},${p.getZ(i).toFixed(4)}`;
		const v = keyNormal.get(k) ?? [0, 0, 0];
		v[0] += n.getX(i); v[1] += n.getY(i); v[2] += n.getZ(i);
		keyNormal.set(k, v);
	}
	for(let i = 0; i < p.count; i++) {
		const x = p.getX(i), y = p.getY(i), z = p.getZ(i);
		const k = `${x.toFixed(4)},${y.toFixed(4)},${z.toFixed(4)}`;
		const v = keyNormal.get(k), l = Math.hypot(...v) || 1;
		const d = (h(Math.round(x * 40) / 40, Math.round(y * 40) / 40, Math.round(z * 40) / 40) - .5) * 2 * amp;
		p.setXYZ(i, x + v[0] / l * d, y + v[1] / l * d, z + v[2] / l * d);
	}
	p.needsUpdate = true;
	geo.computeVertexNormals();
	return geo;
}

// Contour 2D [[x, y], …] extrudé sur une épaisseur, face avant à z0 + epaisseur. Pour le carton découpé
function plaque(THREE, contour, z0, epaisseur, bevel = 0) {
	const shape = new THREE.Shape(contour.map(([x, y]) => new THREE.Vector2(x, y)));
	const g = new THREE.ExtrudeGeometry(shape, { depth: epaisseur, bevelEnabled: bevel > 0, bevelSize: bevel, bevelThickness: bevel, bevelSegments: 1, curveSegments: 6 });
	g.translate(0, 0, z0);
	return g;
}

// Ellipse en contour, n points, pour plaque()
const ovale = (cx, cy, rx, ry, n = 16, a0 = 0) => Array.from({ length: n }, (_, i) => {
	const a = a0 + i / n * Math.PI * 2;
	return [cx + Math.cos(a) * rx, cy + Math.sin(a) * ry];
});

// Une pièce, rangée dans la liste des parts
const collector = () => {
	const parts = [];
	const add = (geometry, color, name, pivot = null) => { parts.push({ name, geometry, color, pivot }); return geometry; };
	return { parts, add };
};

function geometrie(THREE, positions, normales) {
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.BufferAttribute(new Float32Array(positions), 3));
	g.setAttribute('normal', new THREE.BufferAttribute(new Float32Array(normales), 3));
	return g;
}

// Une géométrie sans index dont chaque normale de sommet qui s'écarte de plus de `degres` de sa face prend celle de la face.
// Une normale arrondie sur une pièce mince et repliée peut regarder à l'opposé de sa face : la face, vue du côté où elle se
// dessine, prend alors la lumière de l'autre côté et s'allume en éclat. Ailleurs, l'arrondi reste
function redresser(THREE, g, degres = 60) {
	const pos = g.attributes.position, nor = g.attributes.normal, seuil = Math.cos(degres * Math.PI / 180);
	const [a, b, c, face, normale] = [0, 0, 0, 0, 0].map(() => new THREE.Vector3());
	for(let i = 0; i < pos.count; i += 3) {
		a.fromBufferAttribute(pos, i);
		b.fromBufferAttribute(pos, i + 1);
		c.fromBufferAttribute(pos, i + 2);
		face.crossVectors(b.sub(a), c.sub(a));
		if(face.lengthSq() === 0) continue;
		face.normalize();
		for(let s = i; s < i + 3; s++) if(normale.fromBufferAttribute(nor, s).normalize().dot(face) < seuil) nor.setXYZ(s, face.x, face.y, face.z);
	}
	return g;
}

// Une grille de révolution : chaque rang est une liste de S points, ou { pole } pour un rang réduit à un point. cle(r, c) dit à
// quelle bande appartient le quadrilatère entre le rang r et le rang r + 1, colonne c. Les normales se calculent sur la grille
// entière, avant la répartition : deux bandes voisines partagent la normale de leur raccord
function grille(THREE, rangs, S, cle) {
	const positions = [], debut = [];
	for(const rang of rangs) {
		debut.push(positions.length / 3);
		if(rang.pole) positions.push(...rang.pole);
		else for(const p of rang) positions.push(...p);
	}
	const sommet = (r, c) => rangs[r].pole ? debut[r] : debut[r] + ((c % S) + S) % S;
	const faces = [];
	for(let r = 0; r < rangs.length - 1; r++) {
		for(let c = 0; c < S; c++) {
			const k = cle(r, c);
			if(k === null) continue;
			const a0 = sommet(r, c), a1 = sommet(r, c + 1), b0 = sommet(r + 1, c), b1 = sommet(r + 1, c + 1);
			if(!rangs[r].pole) faces.push([a0, a1, b1, k]);
			if(!rangs[r + 1].pole) faces.push([a0, b1, b0, k]);
		}
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
	g.setIndex(faces.flatMap(f => f.slice(0, 3)));
	g.computeVertexNormals();
	const N = g.attributes.normal.array, bandes = {};
	for(const [a, b, c, k] of faces) {
		const o = bandes[k] ??= { p: [], n: [] };
		for(const v of [a, b, c]) {
			o.p.push(positions[3 * v], positions[3 * v + 1], positions[3 * v + 2]);
			o.n.push(N[3 * v], N[3 * v + 1], N[3 * v + 2]);
		}
	}
	return Object.fromEntries(Object.entries(bandes).map(([k, o]) => [k, geometrie(THREE, o.p, o.n)]));
}

// Les rangs d'un profil tourné autour d'un axe quelconque : profil [[rayon, s], …] du départ de l'axe vers sa fin
function rangsAutour(THREE, profil, S, depart, axe) {
	const a = axe.clone().normalize();
	const e1 = new THREE.Vector3(0, 0, 1).cross(a);
	if(e1.lengthSq() < 1e-6) e1.set(1, 0, 0);
	e1.normalize();
	const e2 = e1.clone().cross(a).normalize();
	return profil.map(([r, s]) => {
		const c = depart.clone().addScaledVector(a, s);
		if(r === 0) return { pole: [c.x, c.y, c.z] };
		return Array.from({ length: S }, (_, j) => {
			const t = j / S * Math.PI * 2;
			const p = c.clone().addScaledVector(e1, Math.sin(t) * r).addScaledVector(e2, Math.cos(t) * r);
			return [p.x, p.y, p.z];
		});
	});
}

// Les rangs d'un fuseau le long d'une courbe : rayons [[rayon, t], …], t de 0 à 1 sur la courbe
function rangsLe(THREE, courbe, rayons, S) {
	return rayons.map(([r, t]) => {
		const c = courbe.getPoint(t);
		if(r === 0) return { pole: [c.x, c.y, c.z] };
		const a = courbe.getTangent(t).normalize();
		const e1 = new THREE.Vector3(0, 0, 1).cross(a).normalize(), e2 = e1.clone().cross(a).normalize();
		return Array.from({ length: S }, (_, j) => {
			const u = j / S * Math.PI * 2;
			const p = c.clone().addScaledVector(e1, Math.sin(u) * r).addScaledVector(e2, Math.cos(u) * r);
			return [p.x, p.y, p.z];
		});
	});
}

// Le bout rond d'un membre, une demi-sphère de rayon r centrée sur son pivot, à l'abscisse s : deux rangs [rayon, abscisse],
// à 45° puis au pôle, vers l'arrière de l'axe (sens -1) ou vers l'avant (sens 1). Un cône qui dépasse du pivot laisse sortir
// sa pointe du membre voisin dès que l'articulation plie, et marque une arête là où il rejoint l'autre ; une demi-sphère centrée
// sur le pivot reste dans le même volume quelle que soit la rotation, et deux membres qui s'y rejoignent se recouvrent.
// `pas` rangs au lieu de deux arrondissent une demi-sphère qu'on voit tourner contre une autre pièce : à deux, ses facettes
// sortaient en dents de celle qui l'entoure
const boutRond = (r, s, sens, pas = 2) => {
	if(pas === 2) return sens < 0 ? [[0, s - r], [r * Math.SQRT1_2, s - r * Math.SQRT1_2]] : [[r * Math.SQRT1_2, s + r * Math.SQRT1_2], [0, s + r]];
	const rangs = Array.from({ length: pas }, (_, k) => {
		const t = Math.PI / 2 * (pas - k) / pas;
		return [k === 0 ? 0 : r * Math.cos(t), s + sens * r * Math.sin(t)];
	});
	return sens < 0 ? rangs : rangs.reverse();
};

// Le pas lissé de 0 à 1, à pente nulle aux deux bouts
const lisse = t => {
	const u = Math.min(1, Math.max(0, t));
	return u * u * (3 - 2 * u);
};

// Interpole un profil [[rayon, abscisse], …] trié par abscisse croissante
function interpoler(profil, s) {
	if(s <= profil[0][1]) return profil[0][0];
	for(let i = 0; i < profil.length - 1; i++) {
		const [r0, s0] = profil[i], [r1, s1] = profil[i + 1];
		if(s >= s0 && s <= s1) return s1 === s0 ? r1 : r0 + (r1 - r0) * (s - s0) / (s1 - s0);
	}
	return profil.at(-1)[0];
}

// Une anse : un tube à trois pans le long d'une suite de points
function anse(THREE, points, r) {
	const geos = [];
	for(let i = 0; i < points.length - 1; i++) {
		const A = new THREE.Vector3(...points[i]), B = new THREE.Vector3(...points[i + 1]);
		const g = new THREE.CylinderGeometry(r, r, A.distanceTo(B), 3, 1, true);
		g.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), B.clone().sub(A).normalize()));
		g.translate((A.x + B.x) / 2, (A.y + B.y) / 2, (A.z + B.z) / 2);
		geos.push(g);
	}
	return merge(THREE, geos);
}

// Une pièce faite dans Blender (pieces.js, écrit par perso_export.py), montée pour une personne : une géométrie par morceau,
// avec son os, sa zone, sa condition et sa couleur. pieces.js ne porte, pour une cible, que les pièces qui la nomment
// (livrer.mjs) : `aPiece()` dit à une fabrique si elle monte la pièce ou garde sa version en JS.
//   valeurs : la valeur de chaque réglage de la pièce (corpulence, rayon…) ; un réglage absent reste à sa base
//   teintes : la couleur de chaque teinte que la fiche règle (couleur, visiere, peau…) ; une teinte fixe se lit telle quelle
// Chaque forme clé se mêle en ligne droite, extrapolation comprise, comme Blender la mêle : une morphologie hors des deux
// valeurs exportées se prolonge sans autre clé. Les normales se calculent après le mélange, sur les sommets partagés : la
// forme change, et une normale exportée ne la suivrait pas. Une arête vive se fait dans Blender en séparant ses sommets
// (aretes_vives()), marquée sur la base : un pli que la morphologie creuse au-delà se redresse ici, comme toute pièce mince
const aPiece = nom => PIECES[nom] !== undefined;

function depuisBlender(THREE, nom, valeurs = {}, teintes = {}) {
	const piece = PIECES[nom];
	const montes = piece.morceaux.map(m => {
		const positions = Float32Array.from(m.positions);
		for(const [reglage, ecarts] of Object.entries(m.cles)) {
			const [base, cle] = piece.parametres[reglage], poids = ((valeurs[reglage] ?? base) - base) / (cle - base);
			if(poids !== 0) for(let i = 0; i < positions.length; i++) positions[i] += poids * ecarts[i];
		}
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.BufferAttribute(positions, 3));
		g.setIndex(m.index);
		// Les doses d'une pièce teinte par sommet suivent les sommets jusque dans la géométrie dépliée, où la fabrique les lit
		if(m.fondus) g.setAttribute('fondu', new THREE.BufferAttribute(Float32Array.from(m.fondus), 3));
		g.computeVertexNormals();
		return g;
	});
	lisser(THREE, piece.morceaux, montes);
	return piece.morceaux.map((m, k) => {
		const fond = m.teinte.startsWith('#') ? m.teinte : teintes[m.teinte];
		if(fond === undefined) throw new Error(`${nom} : la teinte ${m.teinte} n'est pas donnée`);
		return { objet: m.objet, os: m.os, zone: m.zone, condition: m.condition, teinte: m.teinte, geometry: redresser(THREE, montes[k].toNonIndexed()), color: m.nuance === 0 ? fond : nuance(THREE, fond, m.nuance) };
	});
}

// Un objet de Blender marqué `lisse` et peint de plusieurs teintes (les carreaux d'une chemise) sort en un morceau par teinte,
// et une normale calculée morceau par morceau casserait la lumière à chaque changement de teinte, sur une surface qui ne plie
// pas. Les sommets que ses morceaux partagent, reconnus à leur position sur la base, prennent donc une seule normale, celle
// de toutes leurs faces pesées par leur aire, comme computeVertexNormals() le fait dans un morceau
function lisser(THREE, morceaux, montes) {
	const objets = new Map();
	morceaux.forEach((m, k) => {
		if(m.lisse) objets.set(m.objet, [...(objets.get(m.objet) ?? []), k]);
	});
	const [a, b, c] = [0, 0, 0].map(() => new THREE.Vector3());
	for(const rangs of objets.values()) {
		const sommes = new Map(), cle = (m, i) => `${m.positions[3 * i]},${m.positions[3 * i + 1]},${m.positions[3 * i + 2]}`;
		for(const k of rangs) {
			const pos = montes[k].attributes.position, index = morceaux[k].index;
			for(let t = 0; t < index.length; t += 3) {
				a.fromBufferAttribute(pos, index[t]);
				b.fromBufferAttribute(pos, index[t + 1]).sub(a);
				c.fromBufferAttribute(pos, index[t + 2]).sub(a);
				b.cross(c);
				for(const i of index.slice(t, t + 3)) {
					const n = cle(morceaux[k], i);
					sommes.set(n, (sommes.get(n) ?? new THREE.Vector3()).add(b));
				}
			}
		}
		for(const k of rangs) {
			const nor = montes[k].attributes.normal;
			for(let i = 0; i < nor.count; i++) {
				const n = a.copy(sommes.get(cle(morceaux[k], i)) ?? a.fromBufferAttribute(nor, i)).normalize();
				nor.setXYZ(i, n.x, n.y, n.z);
			}
		}
	}
}

return { ENCRE, CREME, triangles, mix, nuance, merge, flat, lathe, ell, limb, bosseler, plaque, ovale, collector, geometrie, redresser, grille, rangsAutour, rangsLe, boutRond, lisse, interpoler, anse, aPiece, depuisBlender };
})();

// ---------- catalogue.js ----------
const module_catalogue = (() => {
// Le catalogue des éléments : tout ce qu'une fiche peut poser sur le corps commun. Une fiche ne
// nomme que des entrées d'ici, avec des réglages pris dans leurs bornes ; retirer un élément, c'est l'ôter de la fiche, le
// remplacer, c'est nommer une autre entrée, qui arrive avec ses réglages par défaut.
// Chaque entrée dit sa famille, sa forme (la fabrique qui la dessine), son emplacement (un seul élément par emplacement, sauf
// les mains, qui en portent deux), ce que sa coupe fixe et ce que la photo peut régler. Le même contenu se lit en JSON dans
// catalogue.json, qu'écrit `node catalogue.mjs` : ce module en est la source, parce qu'un modèle se charge sans fetch.
// Aucune dépendance à three.

const couleur = defaut => ({ type: 'couleur', defaut });
const couleurOuRien = (defaut = null) => ({ type: 'couleur?', defaut });
const couleurs = (max, defaut = []) => ({ type: 'couleurs', max, defaut });
const nombre = (min, max, defaut) => ({ type: 'nombre', min, max, defaut });
const choix = (valeurs, defaut) => ({ type: 'choix', valeurs, defaut });

// La couche dit à quelle étape de la planche la famille se montre : le corps habillé, puis le visage, puis le reste
const FAMILLES = {
	hauts: { titre: 'Hauts', couche: 'vetements', defaut: 't-shirt' },
	bas: { titre: 'Bas', couche: 'vetements', defaut: 'pantalon' },
	vestes: { titre: 'Vestes', couche: 'vetements', defaut: null },
	cravates: { titre: 'Cravates', couche: 'vetements', defaut: null },
	chaussures: { titre: 'Chaussures', couche: 'vetements', defaut: 'chaussures' },
	visage: { titre: 'Visage', couche: 'visage', defaut: null },
	coiffures: { titre: 'Coiffures', couche: 'elements', defaut: 'courte' },
	lunettes: { titre: 'Lunettes', couche: 'elements', defaut: 'lunettes-rondes' },
	barbes: { titre: 'Barbes', couche: 'elements', defaut: 'barbe-courte' },
	bijoux: { titre: 'Bijoux', couche: 'elements', defaut: 'boucles-oreilles' },
	'couvre-chefs': { titre: 'Couvre-chefs', couche: 'elements', defaut: 'casquette' },
	echarpes: { titre: 'Écharpes', couche: 'elements', defaut: null },
	objets: { titre: 'Objets en main', couche: 'elements', defaut: 'panier' },
	accessoires: { titre: 'Accessoires', couche: 'elements', defaut: null },
	gants: { titre: 'Gants', couche: 'elements', defaut: null },
};

// Ce que la photo règle sur une coiffure, la longueur et sa forme mises à part, qui font l'entrée
const cheveux = ({ volume, frange, boucles = 0, raie = 'cote', degrade = 0 }) => ({
	couleur: couleur('#483e30'),
	reflets: couleurOuRien(),
	meches: couleurOuRien(),
	// La part de gris que les mèches de couleur mêlent au devant de la chevelure, de 0 (aucune) à 1
	gris: nombre(0, 1, 0),
	volume: nombre(0, 1, volume),
	degrade: nombre(0, 1, degrade),
	boucles: nombre(0, 1, boucles),
	golfes: nombre(0, 1, 0),
	frange: nombre(0, 1, frange),
	raie: choix(['aucune', 'milieu', 'cote'], raie),
	libres: nombre(0, 1, 0),
});
// Une coiffure modelée dans Blender porte sa forme entière : là où sa pièce est livrée, la fiche n'en règle plus que les
// teintes, et `reglagesPiece` les nomme pour qu'un éditeur ne propose pas les autres, qui n'y changent rien. Une liste de
// ce qui agit plutôt que de ce qui est sans effet : un réglage ajouté demain à `cheveux()` reste caché tant que la pièce
// ne le lit pas. Les scènes, qui rendent la même clé par la fabrique en JS, les lisent tous
const TEINTES_CHEVEUX = ['couleur', 'reflets', 'meches', 'gris'];
// Une boule à zéro ne rend aucun cheveu : `sansTeinte` dit à l'éditeur d'avatar que la couleur n'y changerait rien
const coiffure = (titre, fixe, defauts, plus = {}) => ({
	famille: 'coiffures', titre, forme: 'coiffure', emplacement: 'coiffure', fixe: { queue: 0, chignon: false, ...fixe }, reglages: { ...cheveux(defauts), ...plus },
	...(fixe.piece === undefined ? {} : { reglagesPiece: TEINTES_CHEVEUX }),
	...(fixe.zero === true ? { sansTeinte: true } : {}),
});

// Un haut zippé règle la couleur de sa fermeture : null garde le trait d'encre de tous, une couleur la fait lire de loin
const haut = (titre, fixe, teinte) => ({ famille: 'hauts', titre, forme: 'haut', emplacement: 'haut', fixe, reglages: { couleur: couleur(teinte), lisere: couleurs(2), ecusson: couleurOuRien(), ...(fixe.fermeture === 'zip' ? { zip: couleurOuRien() } : {}) } });
const bas = (titre, coupe, teinte) => ({ famille: 'bas', titre, forme: 'bas', emplacement: 'bas', fixe: { coupe }, reglages: { couleur: couleur(teinte) } });
const chaussures = (titre, coupe, teinte) => ({ famille: 'chaussures', titre, forme: 'chaussures', emplacement: 'chaussures', fixe: { coupe }, reglages: { couleur: couleur(teinte) } });
const lunettes = (titre, carre, largeur, hauteur, epaisseur, teinte) => ({
	famille: 'lunettes', titre, forme: 'lunettes', emplacement: 'lunettes', fixe: { carre },
	reglages: { couleur: couleur(teinte), verres: couleurOuRien(), largeur: nombre(.085, .11, largeur), hauteur: nombre(.07, .1, hauteur), epaisseur: nombre(.006, .016, epaisseur) },
});
// La couleur d'une barbe est celle des cheveux quand la fiche n'en dit rien
const barbe = titre => ({ famille: 'barbes', titre, emplacement: 'barbe', reglages: { couleur: couleurOuRien() } });
const bijou = (titre, emplacement, reglages) => ({ famille: 'bijoux', titre, emplacement, reglages });
const couvreChef = (titre, reglages) => ({ famille: 'couvre-chefs', titre, emplacement: 'couvre-chef', reglages });
const objet = titre => ({ famille: 'objets', titre, emplacement: 'main', reglages: {} });
const marque = (titre, reglages) => ({ famille: 'visage', titre, reglages });
// Un accessoire se porte sur soi, à son emplacement, sans prendre de main ; `exclut` nomme les entrées avec lesquelles il ne
// se porte pas, parce qu'il passerait au travers
const accessoire = (titre, reglages, exclut = []) => ({ famille: 'accessoires', titre, emplacement: 'accessoire', reglages, exclut });

const CATALOGUE = {
	'rase': coiffure('Rasée', { longueur: .006 }, { volume: .1, frange: .1, raie: 'aucune', degrade: .6 }),
	'boule-a-zero': coiffure('Boule à zéro', { longueur: 0, zero: true }, { volume: 0, frange: 0, raie: 'aucune', degrade: 1 }),
	'courte': coiffure('Courte', { longueur: .05 }, { volume: .4, frange: .35, degrade: .5 }),
	'mi-longue': coiffure('Mi-longue', { longueur: .16, piece: 'mi-longue' }, { volume: .45, frange: .5, raie: 'milieu' }),
	'queue-de-cheval': coiffure('Queue de cheval', { longueur: .03, queue: .75, piece: 'queue-de-cheval' }, { volume: .25, frange: 0 }, { queueCote: choix(['dos', 'epaule'], 'dos') }),
	'chignon': coiffure('Chignon', { longueur: .03, chignon: true }, { volume: .4, frange: 0, boucles: .3 }),
	// Sans leur pièce, les scènes rendent ces deux-là par la fabrique en JS, d'après ce qui est fixé ici : des cheveux qui
	// tombent aux épaules, et une queue plus longue pour la tresse
	'cheveux-longs': coiffure('Cheveux longs', { longueur: .28, piece: 'cheveux-longs' }, { volume: .45, frange: .3, raie: 'milieu' }),
	'tresse': coiffure('Tresse', { longueur: .03, queue: 1, piece: 'tresse' }, { volume: .25, frange: 0 }),
	// Les coiffures qui suivent n'existent que par leur pièce : ce qui est fixé et réglé ici est la coupe en JS la plus
	// proche, celle qu'une scène rendrait d'une fiche qui les nomme
	'afro': coiffure('Afro', { longueur: .12, piece: 'afro' }, { volume: 1, frange: .2, boucles: 1, raie: 'aucune' }),
	'frisee': coiffure('Frisée mi-longue', { longueur: .16, piece: 'frisee' }, { volume: .8, frange: .5, boucles: 1, raie: 'aucune' }),
	'epis-courts': coiffure('Courts en épis', { longueur: .05, piece: 'epis-courts' }, { volume: .6, frange: .2, degrade: .3, raie: 'aucune' }),
	'bataille': coiffure('Mi-longs en bataille', { longueur: .16, piece: 'bataille' }, { volume: .6, frange: .6, boucles: .3, raie: 'aucune' }),
	'carre': coiffure('Carré court', { longueur: .13, piece: 'carre' }, { volume: .45, frange: .8, raie: 'aucune' }),
	'degarnie': coiffure('Couronne dégarnie', { longueur: .02, piece: 'degarnie' }, { volume: .1, frange: 0, golfes: 1, degrade: 0, raie: 'aucune' }),
	'nattes-collees': coiffure('Nattes collées', { longueur: .01, piece: 'nattes-collees' }, { volume: .1, frange: 0, degrade: .3, raie: 'aucune' }),
	'tresses-dessus': coiffure('Tresses sur le dessus', { longueur: .03, piece: 'tresses-dessus' }, { volume: .5, frange: 0, degrade: 1, raie: 'aucune' }),
	'locks': coiffure('Locks mi-longues', { longueur: .2, piece: 'locks' }, { volume: .6, frange: .2, boucles: .5, raie: 'aucune' }),
	'tresses-africaines': coiffure('Tresses africaines', { longueur: .28, piece: 'tresses-africaines' }, { volume: .45, frange: 0, raie: 'milieu' }),
	'queue-tressee': coiffure('Queue tressée', { longueur: .03, queue: 1, piece: 'queue-tressee' }, { volume: .25, frange: 0 }),
	'longs-raie': coiffure('Longs raides, raie au milieu', { longueur: .28, piece: 'longs-raie' }, { volume: .3, frange: 0, raie: 'milieu' }),
	'longs-ondules': coiffure('Longs ondulés, en volume', { longueur: .28, piece: 'longs-ondules' }, { volume: 1, frange: .2, boucles: .6, raie: 'milieu' }),
	'longs-degarnis': coiffure('Longs dégarnis', { longueur: .28, piece: 'longs-degarnis' }, { volume: .2, frange: 0, golfes: 1, raie: 'aucune' }),

	't-shirt': haut('T-shirt', { col: 'rond', manches: 'courtes', fermeture: 'aucune', epaisseur: .008 }, '#2fbb75'),
	'polo': haut('Polo', { col: 'polo', manches: 'courtes', fermeture: 'boutons', epaisseur: .008 }, '#1d4b53'),
	'pull': haut('Pull', { col: 'rond', manches: 'longues', fermeture: 'aucune', epaisseur: .013 }, '#8290fd'),
	'polaire-zip': haut('Polaire zippée', { col: 'montant', manches: 'longues', fermeture: 'zip', epaisseur: .014 }, '#4d6d46'),
	// `piece` nomme la pièce faite dans Blender que monte l'avatar ; une scène, dont pieces.js ne la porte pas, garde le haut en JS
	'debardeur': haut('Débardeur', { col: 'debardeur', manches: 'aucune', fermeture: 'aucune', epaisseur: .007, piece: 'debardeur' }, '#3a3a3a'),
	// Une chemise sous une veste ne montre que son plastron et son col
	'chemise': haut('Chemise', { col: 'chemise', manches: 'longues', fermeture: 'aucune', epaisseur: .008 }, '#eef0f2'),
	// Les carreaux sont trois teintes de la couleur de la fiche, peintes face par face dans Blender : aucun réglage ne les porte
	'chemise-carreaux': { ...haut('Chemise à carreaux', { col: 'chemise', manches: 'longues', fermeture: 'aucune', epaisseur: .009, piece: 'chemise-carreaux' }, '#c4363c'), reglages: { couleur: couleur('#c4363c') } },
	'sweat-capuche': haut('Sweat à capuche', { col: 'rond', manches: 'longues', fermeture: 'aucune', epaisseur: .015, capuche: true }, '#6b6d70'),

	// La veste se porte par-dessus un haut, à son propre emplacement
	'veste-costume': { ...haut('Veste de costume', { col: 'veste', manches: 'longues', fermeture: 'aucune', epaisseur: .018 }, '#23263a'), famille: 'vestes', emplacement: 'veste' },
	// La veste de travail : col de chemise, boutons, deux poches de poitrine et deux poches basses
	'veste-travail': { ...haut('Veste de travail', { col: 'chemise', manches: 'longues', fermeture: 'boutons', epaisseur: .016, piece: 'veste-travail' }, '#2f4f8a'), famille: 'vestes', emplacement: 'veste', reglages: { couleur: couleur('#2f4f8a') } },
	// Un gilet sans manches laisse voir les manches du haut ; la doudoune, longue et matelassée, descend sur les cuisses et
	// porte sa capuche
	'gilet': { ...haut('Gilet', { col: 'rond', manches: 'aucune', fermeture: 'zip', epaisseur: .022 }, '#cdbb98'), famille: 'vestes', emplacement: 'veste' },
	'doudoune-longue': { ...haut('Doudoune longue sans manches', { col: 'montant', manches: 'aucune', fermeture: 'zip', epaisseur: .02, longue: true, matelasse: true, capuche: true }, '#4a3b34'), famille: 'vestes', emplacement: 'veste' },
	// La veste de pluie : manches longues, col montant zippé, l'ourlet à l'entrejambe, la capuche rabattue dans le
	// dos. Un haut à col montant porté dessous dépasse de son col
	'veste-pluie': { ...haut('Veste de pluie à capuche', { col: 'montant', manches: 'longues', fermeture: 'zip', epaisseur: .018, longue: true, ourlet: 0, capuche: true }, '#4a4b50'), famille: 'vestes', emplacement: 'veste' },
	'cravate': { famille: 'cravates', titre: 'Cravate', forme: 'cravate', emplacement: 'cravate', reglages: { couleur: couleur('#2b3a6b') } },

	'pantalon': bas('Pantalon', 'pantalon', '#1d4b53'),
	'pantalon-costume': bas('Pantalon de costume', 'pantalon', '#23263a'),
	'jupe': bas('Jupe', 'jupe', '#7a1f33'),
	// Un short s'arrête au-dessus du genou : la jambe nue se voit dessous, jusqu'aux chaussettes s'il y en a
	'short': bas('Short', 'short', '#4a4a3e'),
	// La salopette, ou cotte à bretelles : un pantalon, une bavette sur la poitrine et deux bretelles
	'salopette': bas('Salopette', 'salopette', '#3b5a7a'),

	'chaussures': chaussures('Chaussures', 'basses', '#483e30'),
	'bottes': chaussures('Bottes', 'bottes', '#2e3b2e'),
	'chaussures-ville': chaussures('Chaussures de ville', 'ville', '#17171a'),
	// Une chaussure de marche monte sur la cheville et se lace sur le devant ; sous un short ou une jupe, la chaussette monte
	// au-dessus de la tige
	'chaussures-marche': { ...chaussures('Chaussures de marche', 'montantes', '#8c7356'), reglages: { couleur: couleur('#8c7356'), lacets: couleurOuRien(), chaussettes: couleurOuRien() } },

	'sourcils': { ...marque('Sourcils', { couleur: couleur('#5b4636'), epaisseur: nombre(0, 1, .5) }), emplacement: 'sourcils' },
	'yeux': { ...marque('Couleur des yeux', { couleur: couleur('#5a3a22') }), emplacement: 'yeux' },
	'trait-yeux': { ...marque('Trait d\'yeux', { couleur: couleur('#1b1717') }), emplacement: 'trait-yeux' },
	'rouge-levres': { ...marque('Rouge à lèvres', { couleur: couleur('#b8323f') }), emplacement: 'levres' },
	'joues-rosees': { ...marque('Joues rosées', { couleur: couleur('#f08fa6'), intensite: nombre(.15, .7, .4) }), emplacement: 'joues' },
	'taches-rousseur': { ...marque('Taches de rousseur', { couleur: couleur('#b06a3e'), densite: nombre(0, 1, .5) }), emplacement: 'taches' },
	'grain-de-beaute': { ...marque('Grain de beauté', { place: choix(['joue-gauche', 'joue-droite', 'menton', 'levre', 'tempe'], 'joue-gauche'), couleur: couleur('#4a2e22') }), emplacement: 'grain' },
	'pattes-oie': { ...marque('Pattes d\'oie', { marque: nombre(0, 1, .5) }), emplacement: 'pattes-oie' },
	'sillons': { ...marque('Rides d\'expression', { marque: nombre(0, 1, .5) }), emplacement: 'sillons' },
	'fossettes': { ...marque('Fossettes', { marque: nombre(0, 1, .5) }), emplacement: 'fossettes' },

	'lunettes-rectangle': lunettes('Lunettes rectangulaires', .7, .1, .075, .009, '#3a3236'),
	'lunettes-rondes': lunettes('Lunettes rondes', .05, .095, .095, .008, '#9c7b4f'),

	'barbe-courte': barbe('Barbe courte'),
	'barbe-pleine': barbe('Barbe pleine'),
	'moustache': barbe('Moustache'),
	// Une barbe taillée court mais pleine, des joues au menton, et sa moustache, qui garde souvent un ton à elle
	'barbe-taillee': { ...barbe('Barbe taillée'), reglages: { couleur: couleurOuRien(), moustache: couleurOuRien() } },
	// Les quatre barbes qui suivent sont modelées dans Blender pour l'avatar (BARBES de elements.js)
	'barbe-longue': barbe('Barbe longue'),
	'bouc': barbe('Bouc'),
	'moustache-fournie': barbe('Moustache fournie'),
	'favoris': barbe('Favoris'),

	'boucles-oreilles': bijou('Boucles d\'oreilles', 'oreilles', { couleur: couleur('#d9b45a') }),
	'piercing-levre': bijou('Piercing à la lèvre', 'levre', { couleur: couleur('#c9ccd0') }),
	'pendentif': bijou('Pendentif', 'cou', { couleur: couleur('#d9b45a'), cordon: couleur('#3a3236') }),

	'casquette': couvreChef('Casquette', { couleur: couleur('#f39c43'), visiere: couleur('#1d4b53') }),
	// Une fleur se glisse dans le ruban quand `fleur` porte sa couleur
	'chapeau-paille': couvreChef('Chapeau de paille', { couleur: couleur('#e8c872'), ruban: couleur('#1d4b53'), fleur: couleurOuRien() }),
	'bob': couvreChef('Bob', { couleur: couleur('#69ab5b') }),
	'chapeau-feutre': couvreChef('Chapeau de feutre', { couleur: couleur('#7a5a3e'), ruban: couleur('#3a2a20') }),
	'bonnet': couvreChef('Bonnet', { couleur: couleur('#c4363c'), revers: couleurOuRien(), pompon: couleurOuRien() }),
	// Les couvre-chefs qui suivent sont modelés dans Blender pour l'avatar (COUVRE_CHEFS de elements.js)
	'beret': couvreChef('Béret', { couleur: couleur('#23263a') }),
	'cow-boy': couvreChef('Chapeau de cow-boy', { couleur: couleur('#b98a5a'), ruban: couleur('#3a2a20') }),
	'chapeau-conique': couvreChef('Chapeau conique', { couleur: couleur('#e8c872'), jugulaire: couleur('#3a2a20') }),
	'bandeau-frontal': couvreChef('Bandeau frontal', { couleur: couleur('#c4363c'), motif: couleur('#f4f1ea') }),
	'hijab-travail': couvreChef('Hijab de travail', { couleur: couleur('#1d4b53') }),
	'fichu': couvreChef('Fichu', { couleur: couleur('#c4363c') }),
	'chapeau-brousse': couvreChef('Chapeau de brousse', { couleur: couleur('#a89868'), ruban: couleur('#4a3a28') }),
	'casquette-plate': couvreChef('Casquette plate', { couleur: couleur('#8f8a80') }),
	'charlotte': couvreChef('Charlotte', { couleur: couleur('#f4f1ea'), elastique: couleur('#8fb4d9') }),
	'cagoule': couvreChef('Cagoule', { couleur: couleur('#7a1f33') }),
	'turban': couvreChef('Turban', { couleur: couleur('#8290fd') }),

	// Portée en bandoulière, de l'épaule droite à la hanche gauche, le bleu vers le col
	'echarpe-tricolore': { famille: 'echarpes', titre: 'Écharpe tricolore', forme: 'echarpe', emplacement: 'bandouliere', fixe: { couleurs: ['#22408f', '#f2f0ea', '#c8202f'] }, reglages: { gland: couleur('#c9a23a') } },

	'rateau': objet('Râteau'),
	'binette': objet('Binette'),
	'panier': objet('Panier'),
	'arrosoir': objet('Arrosoir'),
	'tomate': objet('Tomate'),
	'navet': objet('Navet'),
	// Quand la pose prend les deux mains, un objet porte sur soi se range au col au lieu d'être posé au sol
	'lunettes-pliees': { ...objet('Lunettes pliées'), fixe: { rangement: 'col' }, reglages: { couleur: couleur('#1d1d20') } },

	// Le tablier passerait au travers d'une veste qui descend sur les cuisses ou dont les boutons saillent sur sa bavette, la
	// hotte au travers d'une capuche rabattue dans le dos
	'tablier': accessoire('Tablier', { couleur: couleur('#2f5d7c') }, ['doudoune-longue', 'veste-pluie', 'veste-travail']),
	// Le foulard et l'écharpe se nouent au cou, et passeraient au travers d'une coiffe qui le couvre jusqu'aux épaules
	'foulard': accessoire('Foulard', { couleur: couleur('#c4363c'), pois: couleur('#f6efe2') }, ['pendentif', 'hijab-travail', 'cagoule']),
	'echarpe-laine': accessoire('Écharpe de laine', { couleur: couleur('#d9a43a'), bande: couleur('#7a2f2a') }, ['pendentif', 'cravate', 'hijab-travail', 'fichu', 'cagoule']),
	'besace': accessoire('Besace', { couleur: couleur('#8a5a32'), boucle: couleur('#d9b45a') }, ['echarpe-tricolore']),
	'hotte': accessoire('Hotte de légumes', { couleur: couleur('#c8a064'), fanes: couleur('#4d9a4a') }, ['sweat-capuche', 'doudoune-longue', 'veste-pluie']),

	'gants': { famille: 'gants', titre: 'Gants de travail', emplacement: 'gants', reglages: { couleur: couleur('#d9a43a'), manchette: couleurOuRien() } },
};
for(const [id, entree] of Object.entries(CATALOGUE)) {
	entree.forme ??= id;
	entree.fixe ??= {};
}

return { FAMILLES, CATALOGUE };
})();

// ---------- poses.js ----------
const module_poses = (() => {
// Les poses qui cassaient au bourg, sur le corps articulé : un instant de chacune, en angles par
// pièce [x, y, z] (radians, ordre XYZ de three), chaque pièce tournée autour de son pivot et rangée sous son parent
// (personnage.js, `parents`). Les angles du bourg sont repris de acteurs.js (3d-compta/bourg3d-plan/js/acteurs.js) au même
// instant : jg et jd pour les cuisses, bgx, bgz, bdx et bdz pour les bras, torse et tete. S'y ajoutent ce que la quille
// n'avait pas, les genoux (molletG, molletD : positif replie la jambe vers l'arrière) et les coudes (avantBrasG,
// avantBrasD : négatif replie l'avant-bras vers l'avant).
// `mainsLibres` dit que les mains sont prises par la pose : l'objet tenu (pièces outilG et outilD) se pose au sol à côté
// de la personne le temps de la pose, sinon il passe dans le visage.
// `descente` est la part de `anchors.descenteAssis` dont le corps entier descend : 1 pour être assis, la cuisse à
// l'horizontale et le pied au sol. Un pied qui pose sur sa pointe descend plus ou moins selon la chaussure, celle de ville et
// celle de marche, plus longues, plus bas que les autres : la descente d'une telle pose est celle de la chaussure qui descend
// le plus, pour qu'aucune ne passe sous le sol, et une scène qui veut le pied juste au sol le pose sur son point le plus bas,
// comme le bourg, ou sur la descente que l'avatar calcule pour sa personne (avatar/contrat.js, `poses`).
// `expression` nomme la bouche de la pose, prise dans `expressions` du modèle et posée sur la pièce de rôle `bouche` ; sans
// elle, le sourire.
const POSES = {
	// Le pas d'une marche, le pied de devant posé à plat et celui de derrière qui décolle du talon : au pas plus large d'avant
	// (cuisses à 0,8), aucun pied ne touchait le sol et la marche se lisait comme une course. `descente` pose le pied de devant
	// au sol. Les bras balancent moins que les jambes, collés au corps : à 0,7, un bras lancé devant passait à l'horizontale à
	// la taille du bourg
	marche: { descente: .093, jambeG: [.5, 0, 0], molletG: [.5, 0, 0], jambeD: [-.45, 0, 0], molletD: [.08, 0, 0], brasG: [-.45, 0, -.04], avantBrasG: [-.3, 0, 0], brasD: [.45, 0, .04], avantBrasD: [-.15, 0, 0], torse: [.1, 0, 0], tete: [-.08, 0, 0] },
	// Sur un banc, les coudes un peu écartés du corps et les mains posées sur les genoux : ce sont les avant-bras qui rentrent
	// vers les genoux, depuis le coude. L'angle des bras de acteurs.js (-.95) tendait les mains en l'air devant soi, comme sur un
	// volant ; des bras qui tombent droit des épaules posent les paumes à côté des cuisses, la carrure étant plus large que les
	// deux jambes ; des bras rentrés depuis l'épaule font sortir le haut du bras par le flanc du vêtement, et un coude collé au
	// corps passe à travers un gilet sans manches
	assis: { descente: 1, jambeG: [-1.571, 0, 0], molletG: [1.571, 0, 0], jambeD: [-1.571, 0, 0], molletD: [1.571, 0, 0], brasG: [-.1, 0, .1], avantBrasG: [-1.3, 0, -.45], brasD: [-.1, 0, -.1], avantBrasD: [-1.3, 0, .45], torse: [.12, 0, 0], tete: [-.05, 0, 0] },
	// Accroupi sur un rang, les mains vers le sol de part et d'autre des genoux. Le buste penché de acteurs.js (0,85) sur
	// des genoux à peine fléchis faisait sortir le pantalon par le flanc du pull, et sa descente (0,3) enfonçait les pieds de
	// 10 cm dans le sol ; une demi-flexion buste droit laissait les mains sur les genoux, comme assis sur une chaise absente.
	// Accroupi, la cuisse couvre le devant de l'ourlet. Les bras, courts pour la tête, arrêtent les mains à 27 cm du sol
	recolte: { descente: .785, jambeG: [-1.6, 0, 0], molletG: [2.3, 0, 0], jambeD: [-1.45, 0, 0], molletD: [2.2, 0, 0], brasG: [-.75, 0, .12], avantBrasG: [0, 0, 0], brasD: [-.6, 0, -.12], avantBrasD: [-.05, 0, 0], torse: [.65, 0, 0], tete: [-.45, 0, 0] },
	// Assis, le buste tombé en avant, les coudes sur les genoux, les mains qui pendent devant les tibias, la tête basse : la tête dans
	// les mains de acteurs.js (bras à -2.05) passait les poings dans le visage, la tête étant plus grosse que la portée du bras.
	// C'est la tête qui tombe plus que le buste, pour la même raison qu'à la récolte : le torse plié à 0,5 faisait sortir le
	// pantalon par le flanc du pull.
	// Assis comme accablé, la cuisse est à l'horizontale et le tibia d'aplomb, à π/2 : c'est la seule position où la descente
	// de `descenteAssis` pose le pied à plat sur le sol. À 1,5, le genou tombait de 4° sous la hanche, et le pied de 2,2 cm sous
	// le sol à 1,80 m, d'autant plus que la personne est grande
	accable: { descente: 1, expression: 'triste', jambeG: [-1.571, 0, 0], molletG: [1.571, 0, 0], jambeD: [-1.571, 0, 0], molletD: [1.571, 0, 0], brasG: [-.8, 0, -.1], avantBrasG: [-.25, 0, 0], brasD: [-.8, 0, .1], avantBrasD: [-.25, 0, 0], torse: [.3, 0, 0], tete: [.5, 0, 0] },
	// La fin du lancer : le bras droit est parti devant et vers le haut, le gauche fait contrepoids derrière, le buste plonge
	// et tourne sur sa droite, et le regard suit ce qui part. Les deux mains devant la poitrine du lancer de caisse se lisaient,
	// sans caisse, comme deux mains qui tiennent quelque chose ; un bras qui part droit devant soi se raccourcit vers la caméra
	// de l'éditeur, posée sur la gauche de la personne, d'où le buste tourné qui envoie le geste sur le côté. Le bourg ne lit de
	// cette pose que l'écart des bras (z), qu'il garde à la caisse tenue par ses deux côtés, et les jambes : la jambe d'appui
	// pousse en arrière sur la pointe du pied, celle de devant pose le pied à plat ; la cheville seule tournée de 0,22 enfonçait
	// la pointe du pied arrière de 2,7 cm, et le bourg, qui pose la personne sur son point le plus bas, décollait le pied avant
	// d'autant. Les gestes du bourg, bras, buste et tête, sont les siens (acteurs.js, LACHE), et la caisse lancée porte ses propres
	// angles (personnage.js, CAISSE_LANCEE)
	lance: { descente: -.026, mainsLibres: true, brasG: [1.1, 0, -.06], avantBrasG: [-.3, 0, 0], brasD: [-2.4, 0, .06], avantBrasD: [-.1, 0, 0], torse: [.35, -.7, 0], tete: [-.45, -.1, 0], jambeG: [.4, 0, 0], molletG: [.32, 0, 0], jambeD: [-.12, 0, 0], molletD: [.08, 0, 0] },
};

return { POSES };
})();

// ---------- emotions.js ----------
const module_emotions = (() => {
// Les émotions : ce que la personne ressent, dit d'abord par la silhouette (le buste, la tête, les bras, l'appui des jambes),
// puis par le visage commun (les yeux, l'inclinaison des sourcils, la bouche). Une émotion est une donnée que la scène choisit,
// ou que la fiche donne pour la personne au repos (`emotion`), sur le modèle de `expression` dans poses.js ; elle ne change
// ni la forme de la tête ni la place des traits.
// Chaque émotion porte, comme une pose de poses.js, des angles par pièce [x, y, z], `descente` et `mainsLibres`, qui disent
// si l'objet tenu se pose au sol le temps de l'émotion. `base` reprend une pose du catalogue dont l'émotion ne change que le
// visage et les angles qu'elle redonne. `visage` nomme la variante de chaque trait dans visage.js.
// Les gestes d'une seule main sont faits de la main droite (D) : l'objet tenu va d'abord dans la gauche (personnage.js), et
// la personne le garde.
// Ce fichier n'est pas dans POSES : l'empreinte du bourg (compte.mjs) porte la table des poses, qu'une émotion ajoutée
// changerait chez toutes les personnes déjà faites, sans qu'aucune de leurs pièces ne bouge.
const { POSES } = module_poses;

const EMOTIONS = {
	// Les deux bras levés en V, la tête relevée : la joie d'une récolte ou d'une bonne nouvelle, qui se lit à la taille du
	// bourg, là où le visage n'est qu'un point
	joyeux: {
		visage: { yeux: 'rieurs', sourcils: 'hauts', bouche: 'rire' },
		mainsLibres: true,
		brasG: [-.35, 0, 2.3], avantBrasG: [0, 0, .25], brasD: [-.35, 0, -2.3], avantBrasD: [0, 0, -.25],
		torse: [-.06, 0, 0], tete: [-.18, 0, 0],
	},
	// Les poings sur les hanches, le buste en arrière et le menton levé, les pieds écartés : devant son étal ou son champ
	fier: {
		visage: { yeux: 'ouverts', sourcils: 'leves', bouche: 'large' },
		mainsLibres: true,
		// Les pieds écartés posent le corps sur le bord des semelles, qui passait 4 mm sous le sol
		descente: -.012,
		brasG: [.85, 0, 1.15], avantBrasG: [-2, 0, -.6], brasD: [.85, 0, -1.15], avantBrasD: [-2, 0, .6],
		torse: [-.1, 0, 0], tete: [-.16, 0, 0], jambeG: [0, 0, .09], jambeD: [0, 0, -.09],
	},
	// Plié en deux, les mains au-dessus des genoux, la tête relevée pour souffler : à bout de forces après l'effort, au bout du
	// rang. Vu d'en haut, un buste plié à 0,45 ne raccourcit pas la silhouette et se lisait comme de la tristesse debout ; plié à
	// 0,85, il ouvrait l'encolure des vêtements à la caméra. 0,65 est le pli de la récolte. La tête relevée davantage montrait le
	// dessous du menton et l'encolure par-dessus l'épaule
	fatigue: {
		visage: { yeux: 'lourds', sourcils: 'tombants', bouche: 'rond' },
		mainsLibres: true,
		descente: .296,
		jambeG: [-.8, 0, .05], molletG: [.8, 0, 0], jambeD: [-.8, 0, -.05], molletD: [.8, 0, 0],
		brasG: [-.7, 0, -.12], avantBrasG: [-.1, 0, 0], brasD: [-.7, 0, .12], avantBrasD: [-.1, 0, 0],
		torse: [.65, 0, 0], tete: [-.4, 0, 0],
	},
	// Le buste rejeté en arrière, les deux mains levées devant soi, un pied qui avance sur le talon : un animal qui surgit, la
	// grêle, une livraison qu'on n'attendait pas
	surpris: {
		visage: { yeux: 'ronds', sourcils: 'hauts', bouche: 'rond' },
		mainsLibres: true,
		brasG: [-.5, 0, .6], avantBrasG: [-1.7, 0, 0], brasD: [-.5, 0, -.6], avantBrasD: [-1.7, 0, 0],
		torse: [-.16, 0, 0], tete: [-.12, 0, 0], jambeG: [-.24, 0, 0], molletG: [.2, 0, 0],
	},
	// Les mains jointes serrées sous le menton, les coudes collés au corps, le buste rentré, les genoux qui se serrent, la tête
	// penchée de côté et le regard qui file : la grêle annoncée, une bête qui s'échappe, la gelée qui vient. La surprise ouvre
	// la silhouette, coudes écartés et buste en arrière ; l'inquiétude la referme sur elle-même. Les mains levées de part et
	// d'autre des joues de la version d'avant se lisaient comme la surprise, de loin comme de près. La tête ne se baisse pas :
	// un chapeau large cachait tout le visage
	inquiet: {
		visage: { yeux: 'fuyants', sourcils: 'inquiets', bouche: 'moue' },
		mainsLibres: true,
		// Les genoux serrés et pliés font descendre les pieds : sans relever le corps, la chaussure de ville, la plus longue, passait
		// 1,4 cm sous le sol à 1,92 m
		descente: -.043,
		brasG: [-.55, 0, -.1], avantBrasG: [-2.15, 0, -.75], brasD: [-.55, 0, .1], avantBrasD: [-2.15, 0, .75],
		torse: [.28, 0, 0], tete: [-.04, -.15, .14],
		jambeG: [-.08, 0, .05], molletG: [.16, 0, 0], jambeD: [-.08, 0, -.05], molletD: [.16, 0, 0],
	},
	// Une main au menton, l'autre au creux de la hanche, la tête penchée : on regarde son plan, on cherche où placer la culture
	// suivante. L'autre bras croisé en travers du ventre, qui porte le coude, se lisait de loin comme des bras croisés qui boudent
	pensif: {
		visage: { yeux: 'plisses', sourcils: 'fronces', bouche: 'plate' },
		mainsLibres: true,
		brasG: [.85, 0, 1.15], avantBrasG: [-2, 0, -.6], brasD: [-.8, 0, 1.1], avantBrasD: [-1.6, 0, 0],
		torse: [.04, 0, 0], tete: [.06, 0, -.24],
	},
	// Assis, la tête tombée, les bras sur les genoux : la pose accablée du catalogue, avec le visage qui va avec
	accable: {
		base: 'accable',
		mainsLibres: true,
		visage: { yeux: 'lourds', sourcils: 'inquiets', bouche: 'moue' },
	},
};

// La pose complète d'une émotion : les angles de sa pose de base, puis les siens ; rien pour une pièce que ni l'une ni l'autre
// ne tourne
function poseEmotion(nom) {
	const { visage, base, ...propre } = EMOTIONS[nom];
	const { expression, ...depart } = base ? POSES[base] : {};
	return { descente: 0, ...depart, ...propre };
}

return { EMOTIONS, poseEmotion };
})();

// ---------- fiche.js ----------
const module_fiche = (() => {
// La fiche d'une personne : le seul endroit où une personne diffère d'une autre. Elle a deux
// parties, et une clé facultative.
//   corps    : la peau et la morphologie, dans leurs bornes. Rien d'autre du corps commun ne change.
//   elements : la liste de ce qui se pose dessus, chaque élément une entrée du catalogue, avec les réglages que la photo
//              fonde : { catalogue: 'polo', reglages: { couleur: '#1e2442' }, fonde: '…' }.
// Une valeur s'écrit nue ou avec ce qui la fonde ({ valeur, fonde }). Valider une personne, c'est éditer cette liste : retirer
// un élément l'ôte, le remplacer nomme une autre entrée. resoudre() ramène tout dans les bornes et nomme chaque écart au lieu
// de l'avaler. La clé facultative, `emotion`, nomme l'émotion de la personne au repos (emotions.js). Aucune dépendance à
// three.
const { CATALOGUE, FAMILLES } = module_catalogue;
const { EMOTIONS } = module_emotions;

const COULEUR = /^#[0-9a-f]{6}$/i;

const BORNES_CORPS = {
	'peau': { type: 'couleur', defaut: '#f2c9a5' },
	'morphologie.hauteur': { type: 'nombre', min: 1.5, max: 1.92, defaut: 1.8 },
	'morphologie.carrure': { type: 'nombre', min: .9, max: 1.12, defaut: 1 },
	'morphologie.corpulence': { type: 'nombre', min: .9, max: 1.15, defaut: 1 },
	// Le galbe de la poitrine, posé par la skill à l'chargement de la photo d'une femme. À 0, le défaut, le torse est celui de tous
	'morphologie.poitrine': { type: 'nombre', min: 0, max: 1, defaut: 0 },
	'morphologie.visage': { type: 'choix', valeurs: ['ronde', 'ovale', 'allongee', 'carree', 'coeur'], defaut: 'ronde' },
};

const nue = v => v !== null && typeof v === 'object' && Array.isArray(v) === false && 'valeur' in v ? v.valeur : v;

// Une valeur ramenée dans sa borne ; ecart(texte) nomme ce qui a été refusé ou ramené
function borner(borne, valeur, ecart) {
	if(valeur === undefined) return borne.defaut;
	if(borne.type === 'nombre') {
		const n = Number(valeur);
		if(Number.isFinite(n) === false) { ecart(`${valeur} n'est pas un nombre`); return borne.defaut; }
		const v = Math.min(borne.max, Math.max(borne.min, n));
		if(v !== n) ecart(`${n} ramené à ${v}`);
		return v;
	}
	if(borne.type === 'couleur' || borne.type === 'couleur?') {
		if(valeur === null && borne.type === 'couleur?') return null;
		if(COULEUR.test(valeur)) return valeur;
		ecart(`${valeur} n'est pas une couleur`);
		return borne.defaut;
	}
	if(borne.type === 'couleurs') {
		const liste = (Array.isArray(valeur) ? valeur : []).filter(c => COULEUR.test(c)).slice(0, borne.max);
		if(liste.length !== (Array.isArray(valeur) ? valeur.length : -1)) ecart(`liste ramenée à ${liste.length} couleurs`);
		return liste;
	}
	if(borne.valeurs.includes(valeur)) return valeur;
	ecart(`${valeur} n'est pas parmi ${borne.valeurs.join(', ')}`);
	return borne.defaut;
}

// Rend { corps, elements, ecarts }. Un élément résolu porte son entrée, sa famille, sa forme, son emplacement et ses
// paramètres complets : ce que sa coupe fixe, les défauts de l'entrée, puis les réglages de la fiche
function resoudre(fiche = {}) {
	const ecarts = [];
	const entree = fiche.corps ?? {};
	for(const cle of Object.keys(entree)) if(BORNES_CORPS[cle] === undefined) ecarts.push(`corps.${cle} : clé inconnue, ignorée`);
	const valeurs = Object.fromEntries(Object.entries(BORNES_CORPS).map(([cle, borne]) => [cle, borner(borne, nue(entree[cle]), t => ecarts.push(`corps.${cle} : ${t}`))]));
	const corps = { peau: valeurs['peau'], hauteur: valeurs['morphologie.hauteur'], carrure: valeurs['morphologie.carrure'], corpulence: valeurs['morphologie.corpulence'], poitrine: valeurs['morphologie.poitrine'], visage: valeurs['morphologie.visage'] };

	const elements = [], occupes = {};
	for(const element of fiche.elements ?? []) {
		const id = element.catalogue, e = CATALOGUE[id];
		if(e === undefined) { ecarts.push(`${id} : absent du catalogue, ignoré`); continue; }
		const place = occupes[e.emplacement] ?? [];
		if(place.length >= (e.emplacement === 'main' ? 2 : 1)) { ecarts.push(`${id} : ${e.emplacement} déjà occupé par ${place.join(', ')}, ignoré`); continue; }
		place.push(id);
		occupes[e.emplacement] = place;
		const reglages = element.reglages ?? {};
		for(const cle of Object.keys(reglages)) if(e.reglages[cle] === undefined) ecarts.push(`${id}.${cle} : réglage inconnu de cette entrée, ignoré`);
		const params = { ...e.fixe };
		for(const [cle, borne] of Object.entries(e.reglages)) params[cle] = borner(borne, nue(reglages[cle]), t => ecarts.push(`${id}.${cle} : ${t}`));
		elements.push({ id, titre: e.titre, famille: e.famille, couche: FAMILLES[e.famille].couche, forme: e.forme, emplacement: e.emplacement, params });
	}
	// L'émotion de la personne au repos, facultative : une scène peut toujours en montrer une autre
	let emotion = nue(fiche.emotion) ?? null;
	if(emotion !== null && EMOTIONS[emotion] === undefined) {
		ecarts.push(`emotion : ${emotion} n'est pas parmi ${Object.keys(EMOTIONS).join(', ')}, ignorée`);
		emotion = null;
	}
	return { corps, elements, ecarts, emotion };
}

return { BORNES_CORPS, resoudre };
})();

// ---------- corps.js ----------
const module_corps = (() => {
// Le corps commun : un humain articulé dans le style de la quille de bois, bois tourné peint,
// grosse tête, visage commun, aplats. Le tronc est tourné en deux pièces, le bassin et le torse, qui se recouvrent à la
// taille pour qu'un buste penché ne s'ouvre pas ; deux jambes tournées, chacune une cuisse et un mollet articulés à la hanche
// et au genou, un pied posé sous le mollet ; deux bras, chacun un haut de bras et un avant-bras articulés à l'épaule et au
// coude, la main en boule au bout. Rien n'y est peint que la peau : ni cheveux, ni vêtement, ni barbe. La tête, le visage et
// la place des traits sont fixes ; seules la couleur de la peau et la morphologie, dans leurs bornes, viennent de la fiche.
// Chaque bande du corps porte la zone qu'elle occupe (hanches, taille, cuisses, mollets, pieds, ventre, poitrine, épaules,
// encolure, cou, brasHaut, avantBras, main, oreilles, crane) : un vêtement ou une coiffure qui couvre une zone entière la
// retire, au lieu de la laisser cachée dessous et de payer ses triangles deux fois.
// Pièces et pivots, les noms que le bourg anime : bassin (fixe), torse (taille), tete (cou), brasG et brasD (épaules),
// avantBrasG et avantBrasD (coudes), jambeG et jambeD (hanches), molletG et molletD (genoux). G est la gauche du personnage,
// du côté des x positifs ; il regarde vers les z positifs.
// Le corps se dessine sur une figure de 1,8 m ; la mise à la hauteur de la fiche se fait une fois, sur la personne entière
// (personnage.js). Il rend ses pièces et les ancres où le reste se pose.
const { grille, rangsAutour, interpoler, nuance, ell, merge, boutRond, aPiece, depuisBlender } = module_outils;

const deg = d => d * Math.PI / 180;
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

// Les profils tournés d'un membre, pour qu'un vêtement le suive : [[rayon, abscisse le long de l'axe]]
function profilMembre(points) {
	return points.filter(([r]) => r > 0).map(([r, s]) => [r, s]);
}

// Le pied en JS, que gardent les cibles dont pieces.js ne porte pas le pied fait dans Blender
function piedJs(THREE, cotes, c, centre) {
	const g = new THREE.SphereGeometry(1, cotes, 4, 0, Math.PI * 2, 0, Math.PI / 2).toNonIndexed();
	g.deleteAttribute('uv');
	return g.scale(.07 * c, .075, .125).translate(...centre);
}

// `raccords` (l'avatar seul, avatar/contrat.js) ferme les articulations par des bouts ronds centrés sur leurs pivots, et donne à
// l'assise de tout couvre-chef le contour de la tête coiffée, que les scènes ne mesurent que pour un couvre-chef qui se ceint
// (`contour` d'`assise()`) ; sans lui, le corps reste celui que les scènes ont cuit
function corps(THREE, { peau, carrure, corpulence, poitrine = 0 }, { raccords = null } = {}) {
	const parts = [];
	const add = (geometry, color, name, pivot, zone) => parts.push({ name, geometry, color, pivot, zone });

	// La boule de la tête fait un tiers de la hauteur ; ses trois derniers centimètres sont gardés pour les cheveux
	const R = .29, rCou = .1, hc = 1.8 - R - .03;
	const yCou = hc - Math.sqrt(R * R - rCou * rCou) - .02;
	// L'entrejambe à 38 % de la hauteur : avec une tête d'un tiers, plus bas, les jambes se lisaient courtes sous un
	// torse long (retour du 2026-10-10)
	const yEpaule = yCou - .035, yHanche = .68, yTaille = yHanche + .1, yGenou = .37, xJambe = .105;
	const pivots = { tete: [0, yCou, 0], torse: [0, yTaille, 0], bassin: [0, yHanche, 0] };

	// Le corps est un peu plus plat d'avant en arrière qu'il n'est large ; l'aplatissement s'efface vers le cou rond
	const aplati = y => .88 + .12 * clamp((y - (yEpaule - .1)) / (yCou - (yEpaule - .1)), 0, 1);
	// Le plafond de 2 000 triangles (décidé le 2026-10-10) paie d'abord les côtés : à 16 pour le tronc, 20 pour la tête, 10 pour
	// une jambe et 8 pour un bras, l'ombre lissée ne dessine plus de facette et la silhouette garde son contour
	const S = 16, SJ = 10, SB = 8;
	// Le galbe de la poitrine : le rang de la poitrine avance sur le devant, et retombe à zéro au rang du dessous de la poitrine,
	// 8 cm plus bas, et au rang des épaules, en ligne droite comme les facettes qui les joignent. Retombé à la taille, il
	// s'étalait sur 23 cm et ne se voyait plus de profil. Tout ce qui habille le torse ajoute le même galbe à son rayon et reste
	// posé dessus. À 0, le défaut, il ne change rien
	const yPoitrine = yEpaule - .14, ySousPoitrine = yPoitrine - .08;
	const galbe = (az, y) => {
		if(poitrine === 0 || y <= ySousPoitrine || y >= yEpaule - .025) return 0;
		const h = y < yPoitrine ? (y - ySousPoitrine) / (yPoitrine - ySousPoitrine) : (yEpaule - .025 - y) / (yEpaule - .025 - yPoitrine);
		return poitrine * .09 * h * Math.max(0, Math.cos(az)) ** 1.5;
	};
	const tourner = (profil, bombe = () => 0) => profil.map(([r, y]) => r === 0 ? { pole: [0, y, 0] } : Array.from({ length: S }, (_, j) => {
		const t = j / S * Math.PI * 2, rb = r + bombe(t, y);
		return [Math.sin(t) * rb, y, Math.cos(t) * rb * aplati(y)];
	}));

	// Le bassin : de l'entrejambe à la taille, puis un dôme qui monte sous le torse. Droit, le torse le cache ; penché, le
	// torse découvre le dôme au lieu d'un jour
	const bassin = [
		[0, yHanche - .1, 'hanches'],
		[.17 * corpulence, yHanche - .07, 'hanches'],
		[.242 * corpulence, yHanche + .06, 'hanches'],
		[.24 * corpulence, yTaille, 'taille'],
		[0, yTaille + .1, null],
	];
	for(const [zone, g] of Object.entries(grille(THREE, tourner(bassin), S, r => bassin[r][2]))) add(g, peau, 'bassin', pivots.bassin, zone);

	// Le torse : il descend sous la taille, par-dessus le bassin, et se referme dedans
	const torse = [
		[0, yTaille - .02, 'ventre'],
		[.243 * corpulence, yTaille - .04, 'ventre'],
		[.24 * corpulence, yTaille, 'ventre'],
		[.24 * corpulence + (.256 * carrure - .24 * corpulence) * (ySousPoitrine - yTaille) / (yPoitrine - yTaille), ySousPoitrine, 'ventre'],
		[.256 * carrure, yPoitrine, 'poitrine'],
		[.196 * carrure, yEpaule - .025, 'epaules'],
		[.158, yEpaule - .004, 'encolure'],
		[.13, yEpaule + .02, 'cou'],
		[rCou, yCou, null],
	];
	for(const [zone, g] of Object.entries(grille(THREE, tourner(torse, galbe), S, r => torse[r][2]))) add(g, peau, 'torse', pivots.torse, zone);
	if(raccords) {
		// Le cou de l'avatar se ferme par une demi-sphère centrée sur le pivot de la tête, à l'intérieur de la boule tant que la
		// tête est droite. Le cou et la tête sont deux tubes ouverts qui se rejoignent au pivot : dès que la tête penchait, on
		// voyait le fond par l'intérieur du cou, en anneau blanc sous le menton. Elle reste dans la zone du cou, et part avec
		// lui sous un col montant
		const dome = [[rCou, yCou, 'cou'], ...boutRond(rCou, yCou, 1).map(([r, y], k) => [r, y, k === 0 ? 'cou' : null])];
		for(const [zone, g] of Object.entries(grille(THREE, tourner(dome), S, r => dome[r][2]))) add(g, peau, 'torse', pivots.torse, zone);
	}

	// Rayon du tronc à une hauteur, du bassin au cou, pour poser ce qui l'habille
	const principal = [...bassin.slice(1, 4), ...torse.slice(2)].map(([r, y]) => [r, y]);
	const rayon = y => interpoler(principal, y);
	const surCorps = (az, y, lift) => {
		const r = rayon(y) + lift + galbe(az, y);
		return [Math.sin(az) * r, y, Math.cos(az) * r * aplati(y)];
	};
	const normaleCorps = az => new THREE.Vector3(Math.sin(az), 0, Math.cos(az) / .88).normalize();

	// Les jambes : une cuisse qui part de l'intérieur du bassin et s'arrondit sous le genou, un mollet qui s'arrondit au-dessus
	// du genou et finit dans le pied. Les deux bouts ronds se recouvrent : un genou plié reste fermé
	const c = Math.sqrt(corpulence), bas = new THREE.Vector3(0, -1, 0);
	// Le pied fait dans Blender (pied.blend) suit la corpulence par sa forme clé
	const jambes = {}, pieds = {}, piedsBlender = aPiece('pied') ? depuisBlender(THREE, 'pied', { corpulence }, { peau }) : null;
	for(const [cote, sx, mollet] of [['jambeG', 1, 'molletG'], ['jambeD', -1, 'molletD']]) {
		const x = sx * xJambe;
		const hanche = new THREE.Vector3(x, yHanche, 0), genou = new THREE.Vector3(x, yGenou, 0);
		const A = new THREE.Vector3(x, yHanche + .08, 0), B = new THREE.Vector3(x, yGenou + .06, 0);
		const pc = [[0, 0, 'cuisses'], [.098 * c, .07, 'cuisses'], [.074 * c, A.y - yGenou + .01, 'cuisses'], [0, A.y - yGenou + .045, null]];
		const pm = [[0, 0, 'mollets'], [.068 * c, .06, 'mollets'], [.046 * c, B.y - .075, 'mollets'], [0, B.y - .03, null]];
		// Les profils que suivent les vêtements sont ceux d'avant les bouts ronds : une jambe de pantalon part du genou
		const profilCuisse = profilMembre(pc), profilJambe = profilMembre(pm);
		if(raccords) {
			// La cuisse commence par une demi-sphère centrée sur la hanche, et finit, comme le mollet commence, par une demi-sphère
			// centrée sur le genou, au lieu des cônes d'avant : le cône du haut de la cuisse montait 8 cm au-dessus de la hanche et
			// sortait du bassin en dents dès que la jambe partait en arrière
			const sHanche = A.y - yHanche, sGenou = A.y - yGenou, rHanche = .098 * c, rCuisse = .074 * c, rMollet = .068 * c;
			pc.splice(2, 2, [rCuisse, sGenou, 'cuisses'], ...boutRond(rCuisse, sGenou, 1).map(([r, s], k) => [r, s, k === 0 ? 'cuisses' : null]));
			pc.splice(0, 2, ...boutRond(rHanche, sHanche, -1, 3).map(([r, s]) => [r, s, 'cuisses']), [rHanche, sHanche, 'cuisses']);
			pm.splice(0, 1, ...boutRond(rMollet, .06, -1).map(([r, s]) => [r, s, 'mollets']));
		}
		for(const [zone, g] of Object.entries(grille(THREE, rangsAutour(THREE, pc.map(([r, s]) => [r, s]), SJ, A, bas), SJ, r => pc[r][2]))) add(g, peau, cote, hanche.toArray(), zone);
		for(const [zone, g] of Object.entries(grille(THREE, rangsAutour(THREE, pm.map(([r, s]) => [r, s]), SJ, B, bas), SJ, r => pm[r][2]))) add(g, peau, mollet, genou.toArray(), zone);
		// Le pied, un demi-galet sous la cheville, la pointe vers l'avant
		const centre = [x + sx * .008, 0, .055];
		if(piedsBlender) for(const m of piedsBlender.filter(m => m.os === mollet)) add(m.geometry, m.color, mollet, genou.toArray(), m.zone);
		else add(piedJs(THREE, SJ, c, centre), peau, mollet, genou.toArray(), 'pieds');
		jambes[cote] = { sx, hanche, genou, mollet, cuisse: { A, axe: bas, profil: profilCuisse, S: SJ }, jambe: { A: B, axe: bas, profil: profilJambe, S: SJ } };
		pieds[cote] = { centre, pivot: genou.toArray(), piece: mollet };
	}

	// Les bras : un haut de bras et un avant-bras, chacun arrondi au coude, la main en boule. Le profil dit où finit chaque
	// zone : une manche courte couvre le haut du bras, une manche longue le bras jusqu'au poignet
	const ra = .056 * c;
	const bras = {};
	for(const [cote, sx, avant] of [['brasG', 1, 'avantBrasG'], ['brasD', -1, 'avantBrasD']]) {
		// Le poignet pend à hauteur de l'entrejambe : assez long pour qu'un bras levé dépasse la tête, malgré sa taille
		const yA = yEpaule - .035, yB = yHanche - .06;
		const A = new THREE.Vector3(sx * (rayon(yA) + ra * .35), yA, 0);
		const B = new THREE.Vector3(sx * (rayon(yB) + ra * .75 + .012), yB, .03);
		const L = A.distanceTo(B), axe = B.clone().sub(A).normalize(), Le = L * .47;
		const E = A.clone().addScaledVector(axe, Le);
		const ph = [[0, 0, 'brasHaut'], [ra, .03, 'brasHaut'], [ra * .96, Le, 'brasHaut'], [0, Le + .04, null]];
		const pa = [[0, Le - .035, 'avantBras'], [ra * .95, Le, 'avantBras'],
			[ra * .76, L - .02, 'main'], [ra * 1.14, L + .035, 'main'], [ra * .75, L + .09, 'main'], [0, L + .108, null]];
		if(raccords) {
			// L'épaule, le bout du haut du bras et le haut de l'avant-bras en demi-sphères centrées sur l'épaule et le coude. Au coude,
			// celle de l'avant-bras est assez petite pour rester dans les facettes de celle du bras, et l'avant-bras reprend sa
			// largeur 5 cm plus loin : de même rayon, les deux se perçaient l'une l'autre en bosses dès que le coude pliait
			ph.splice(0, 1, ...boutRond(ra, 0, -1).map(([r, s]) => [r, s, 'brasHaut']), [ra, 0, 'brasHaut']);
			ph.splice(-1, 1, ...boutRond(ra * .96, Le, 1, 3).map(([r, s], k) => [r, s, k < 2 ? 'brasHaut' : null]));
			pa.splice(0, 2, ...boutRond(ra * .8, Le, -1).map(([r, s]) => [r, s, 'avantBras']), [ra * .8, Le, 'avantBras'], [ra * .92, Le + .05, 'avantBras']);
		}
		for(const [zone, g] of Object.entries(grille(THREE, rangsAutour(THREE, ph.map(([r, s]) => [r, s]), SB, A, axe), SB, r => ph[r][2]))) add(g, peau, cote, A.toArray(), zone);
		for(const [zone, g] of Object.entries(grille(THREE, rangsAutour(THREE, pa.map(([r, s]) => [r, s]), SB, A, axe), SB, r => pa[r][2]))) add(g, peau, avant, E.toArray(), zone);
		bras[cote] = {
			A, E, axe, L, Le, ra, sx, avant, S: SB,
			profil: [[ra, .03], [ra * .96, Le], [ra * .76, L - .02], [ra * 1.14, L + .035], [ra * .75, L + .09]],
			// La paume est le centre de la boule de la main, là où un manche passe dans le poing
			paume: A.clone().addScaledVector(axe, L + .035),
		};
	}

	// La tête regarde un peu vers le haut et penche : rien de strictement vertical, et le visage se lit mieux en plongée
	const pivotCou = new THREE.Vector3(0, yCou, 0);
	const M = new THREE.Matrix4().makeTranslation(pivotCou.x, pivotCou.y, pivotCou.z)
		.multiply(new THREE.Matrix4().makeRotationFromEuler(new THREE.Euler(deg(-6), deg(5), deg(-4), 'YXZ')))
		.multiply(new THREE.Matrix4().makeTranslation(-pivotCou.x, -pivotCou.y, -pivotCou.z));
	const C = new THREE.Vector3(0, hc, 0);
	const sph = (theta, phi, r) => [C.x + r * Math.sin(theta) * Math.sin(phi), C.y + r * Math.cos(theta), C.z + r * Math.sin(theta) * Math.cos(phi)];
	const tourne = p => { const v = new THREE.Vector3(...p).applyMatrix4(M); return [v.x, v.y, v.z]; };

	// La boule entière, du cou au sommet, à 12 côtés : une coiffure se pose dessus
	const SH = 20, thetaCou = Math.PI - Math.asin(rCou / R);
	const rangsTete = [Array.from({ length: SH }, (_, j) => {
		const phi = j / SH * Math.PI * 2;
		return [Math.sin(phi) * rCou, yCou, Math.cos(phi) * rCou];
	})];
	for(const fr of [0, .12, .25, .38, .5, .62, .73, .83, .92]) {
		rangsTete.push(Array.from({ length: SH }, (_, j) => tourne(sph(thetaCou * (1 - fr), j / SH * Math.PI * 2, R))));
	}
	rangsTete.push({ pole: tourne(sph(0, 0, R)) });
	// Le haut du crâne, au-dessus de 45° environ, est sous toute coiffure du catalogue : une coiffure le retire
	const boule = grille(THREE, rangsTete, SH, r => r >= 7 ? 'crane' : 'tete'), tete = merge(THREE, Object.values(boule));
	for(const [zone, g] of Object.entries(boule)) add(g, peau, 'tete', pivots.tete, zone);
	// La peau qu'une coiffure peut laisser voir, sa géométrie et ses points, chacun une fois : le crâne, toujours couvert, n'en
	// est pas. Les points sont les sommets et des points semés le long des arêtes, à moins de 8 mm l'un de l'autre : entre
	// deux surfaces faites de facettes plates, l'écart le plus faible tombe sur un sommet de l'une ou de l'autre, ou là où une
	// arête de l'une croise une arête de l'autre
	const peauVisible = (...geometries) => {
		const vus = new Map(), [a, b] = [0, 0].map(() => new THREE.Vector3());
		for(const geometrie of geometries) {
			const g = geometrie.index ? geometrie.toNonIndexed() : geometrie, pos = g.attributes.position;
			for(let t = 0; t < pos.count; t += 3) for(let k = 0; k < 3; k++) {
				a.fromBufferAttribute(pos, t + k);
				b.fromBufferAttribute(pos, t + (k + 1) % 3);
				const n = Math.max(1, Math.ceil(a.distanceTo(b) / .008));
				for(let i = 0; i < n; i++) {
					const v = a.clone().lerp(b, i / n);
					vus.set(v.toArray().map(x => x.toFixed(6)).join(','), v);
				}
			}
		}
		return { geometrie: merge(THREE, geometries), points: [...vus.values()] };
	};
	// Les oreilles, deux galets tournés sur les côtés de la boule. La coiffure lit leur étendue (`oreille` des ancres de la
	// tête) pour savoir si elle les couvre
	const thOreille = Math.PI / 2 + deg(4), rOreille = R - .008, oreille = { epaisseur: .03, demiHauteur: .066, demiLargeur: .046 };
	const galets = [1, -1].map(sx => {
		const [x, y, z] = sph(thOreille, sx * Math.PI / 2, rOreille);
		const g = ell(THREE, x, y, z, oreille.epaisseur, oreille.demiHauteur, oreille.demiLargeur, 8, 3).applyMatrix4(M);
		add(g, nuance(THREE, peau, -.04), 'tete', pivots.tete, 'oreilles');
		return g;
	});

	// Ce qui se pose sur la tête se pose sur les facettes réelles, par un rayon tiré du centre de la boule : posé sur la sphère
	// exacte, un trait flotterait d'un centimètre au milieu de chaque facette
	const centre = C.clone().applyMatrix4(M);
	const lanceur = new THREE.Raycaster();
	const cible = geos => new THREE.Mesh(merge(THREE, geos), new THREE.MeshBasicMaterial({ side: THREE.DoubleSide }));
	const peauVisee = cible([tete]);
	const poser = (visee, dW, lift, repli) => {
		lanceur.set(centre, dW);
		const touche = lanceur.intersectObject(visee, false)[0];
		return centre.clone().addScaledVector(dW, (touche ? touche.distance : repli) + lift);
	};
	// Un point du plan du visage (x à gauche du personnage, y vers le haut, en mètres sur la boule), posé sur la peau
	const versBoule = (x, y, lift) => {
		const dW = new THREE.Vector3(x, y, R).normalize().transformDirection(M);
		return { p: poser(peauVisee, dW, lift, R - .006), n: dW };
	};

	// Le chapeau s'assoit sur un cercle du crâne, assez haut pour laisser libres les sourcils et la monture : son rayon est le
	// plus grand écart, à cette hauteur, entre l'axe de la tête et la peau ou ce qui la coiffe, relevé tout autour
	const axeTete = new THREE.Vector3(0, 1, 0).transformDirection(M), avantTete = new THREE.Vector3(0, 0, 1).transformDirection(M);
	// `restent` est ce qui reste de la coiffure sous un couvre-chef, sans les mèches posées qu'il efface (`rabattre()` de
	// personnage.js). `contour` demande le contour de la tête coiffée hors de l'avatar, pour un couvre-chef qui se ceint
	const assise = (coiffe, hauteurCoiffe, restent = coiffe, { contour = raccords !== null } = {}) => {
		const haut = .15, centreAssise = new THREE.Vector3(0, hc + haut, 0).applyMatrix4(M), crane = cible([tete, ...coiffe]);
		let rayonAssise = 0;
		for(let i = 0; i < 24; i++) {
			const t = i / 24 * Math.PI * 2;
			lanceur.set(centreAssise, new THREE.Vector3(Math.sin(t), 0, Math.cos(t)).transformDirection(M));
			for(const touche of lanceur.intersectObject(crane, false)) rayonAssise = Math.max(rayonAssise, touche.distance);
		}
		// Le plus grand écart à l'axe de la tête coiffée sur une bande sous le plan d'assise, `profondeur` de haut : ce que doit
		// contenir un bord qui y descend, la peau et tout ce qui reste des cheveux, une queue ou des mèches libres comprises. La tête s'élargit sous le cercle d'assise, de trois centimètres sur une coupe rase au
		// pied d'un revers de bonnet. L'écart d'un triangle coupé par la bande est le plus grand à l'un des coins de ce qui en
		// reste, sommet gardé ou point où une arête passe un des deux plans
		const sous = profondeur => {
			const q = [0, 1, 2].map(() => new THREE.Vector3()), d = new THREE.Vector3();
			const hauteur = p => d.subVectors(p, centreAssise).dot(axeTete);
			const ecart = p => {
				const y = hauteur(p);
				return d.subVectors(p, centreAssise).addScaledVector(axeTete, -y).length();
			};
			let rayon = 0;
			for(const g of [tete, ...restent]) {
				const plat = g.index ? g.toNonIndexed() : g, pos = plat.attributes.position;
				for(let t = 0; t < pos.count; t += 3) {
					q.forEach((v, k) => v.fromBufferAttribute(pos, t + k));
					const y = q.map(hauteur);
					if(Math.max(...y) < -profondeur || Math.min(...y) > 0) continue;
					for(let k = 0; k < 3; k++) {
						if(y[k] >= -profondeur && y[k] <= 0) rayon = Math.max(rayon, ecart(q[k]));
						const l = (k + 1) % 3;
						for(const plan of [0, -profondeur]) if((y[k] - plan) * (y[l] - plan) < 0) rayon = Math.max(rayon, ecart(q[k].clone().lerp(q[l], (plan - y[k]) / (y[l] - y[k]))));
					}
				}
			}
			return rayon;
		};
		const rendu = { chapeau: centreAssise.toArray(), crane: rayonAssise, sous, peau: Math.sqrt(R * R - haut * haut), chapeauAxe: axeTete.toArray(), chapeauAvant: avantTete.toArray(), calotte: R + hauteurCoiffe - haut };
		if(contour) {
			// Le contour de la tête coiffée sous le plan d'assise, azimut par azimut : le plus grand écart à l'axe touché entre le plan
			// (ou `depuis` dessous, pour une bande qui ne remonte pas jusqu'à lui) et `profondeur` dessous, dans la direction phi
			// d'un tour de lathe (x = sin phi, z = cos phi dans le repère du couvre-chef, z vers l'avant). La tête coiffée n'est
			// pas centrée sur l'axe : un bord taillé sur le seul cercle d'assise, le plus large, restait à 5 cm du front et
			// touchait la nuque
			const lateral = axeTete.clone().cross(avantTete).normalize(), visee = cible([tete, ...restent]), d = new THREE.Vector3();
			rendu.contour = (phi, profondeur, depuis = 0) => {
				d.copy(lateral).multiplyScalar(Math.sin(phi)).addScaledVector(avantTete, Math.cos(phi)).normalize();
				let r = 0;
				for(let k = 0; k <= 4; k++) {
					lanceur.set(centreAssise.clone().addScaledVector(axeTete, -(depuis + (profondeur - depuis) * k / 4)), d);
					for(const touche of lanceur.intersectObject(visee, false)) r = Math.max(r, touche.distance);
				}
				return r;
			};
		}
		return rendu;
	};

	const anchors = {
		peau, pivots,
		// Les réglages de la morphologie, que les pièces faites dans Blender lisent pour mêler leurs formes clés
		morphologie: { corpulence, carrure, poitrine },
		tete: {
			R, C, M, hc, yCou, sph, tourne, centre, poser, cible, versBoule, assise, sommet: hc + R,
			// L'oreille en angles sur la boule : `bas` est l'angle depuis le sommet de son point le plus bas, `demiLargeur` son
			// demi-angle autour du côté
			oreille: { bas: thOreille + Math.atan(oreille.demiHauteur / rOreille), demiLargeur: Math.atan(oreille.demiLargeur / rOreille) },
			peau: { tete: peauVisible(boule.tete), oreilles: peauVisible(...galets) },
		},
		corps: { profil: principal, rayon, galbe, aplati, surCorps, normaleCorps, yTaille, ySousPoitrine, yPoitrine, yEpaule, yCou, yHanche, yGenou, S, SJ, SB },
		bras, jambes, pieds,
		mainG: bras.brasG.paume.toArray(),
		mainD: bras.brasD.paume.toArray(),
		// Les traits du visage, à la même place pour tout le monde
		yeux: { x: .112, y: .025, r: .042, poser: versBoule },
		bouche: { y: -.082, largeur: .09 },
		// Un point de la boule donné en angles (th depuis le sommet, phi depuis l'avant, vers la gauche du personnage), sur la peau
		menton: {
			poser: (th, phi, lift) => {
				const dW = new THREE.Vector3(...sph(th, phi, 1)).sub(C).transformDirection(M);
				return { p: poser(peauVisee, dW, lift, R - .006), n: dW };
			},
		},
		oreilles: [1, -1].map(sx => new THREE.Vector3(...sph(thOreille, sx * Math.PI / 2, rOreille)).applyMatrix4(M)),
		dos: surCorps(Math.PI, yEpaule - .12, 0),
	};
	return { parts, anchors };
}

return { profilMembre, corps };
})();

// ---------- vetements.js ----------
const module_vetements = (() => {
// Les vêtements : des pièces de garde-robe qui se posent sur le corps commun, chacune une gaine
// tournée à quelques millimètres du corps, de sa coupe et de sa couleur. Une gaine reprend les hauteurs du profil du corps pour
// en suivre la forme, et se referme à chaque bord par un rang ramené contre le corps : vue d'en dessous, un ourlet n'ouvre
// aucun jour. Chaque fabrique dit les zones du corps qu'elle couvre en entier, que le personnage retire.
//   haut       : col (rond, polo, montant, debardeur, chemise, veste), manches (aucune, courtes, longues), fermeture
//                (aucune, boutons, zip), lisere (une ou deux couleurs au col et aux manches), ecusson (couleur ou null),
//                zip (couleur de la fermeture, ou null pour l'encre), epaisseur. Une chemise portée sous une veste ne
//                montre que son plastron et son col, un col montant dépasse de celui de la veste
//   bas        : coupe (pantalon, jupe, short)
//   chaussures : coupe (basses, bottes, ville, montantes), lacets (couleur ou null), chaussettes (couleur ou null, vues
//                seulement quand le bas laisse les mollets nus)
//   cravate    : couleur, posée sur la chemise, coupée par l'ouverture de la veste
// Le corps est articulé : une pièce de vêtement se range dans la pièce du corps qu'elle habille (torse, bras, avant-bras,
// bassin, cuisse, mollet), pour suivre la taille qui plie, le coude et le genou.
// Aucune branche par prénom, aucun chargement de three : THREE arrive en argument.
const { grille, rangsAutour, interpoler, mix, nuance, geometrie, anse, ell, boutRond, ENCRE, CREME, aPiece, depuisBlender } = module_outils;

const deg = d => d * Math.PI / 180;

// Un manchon autour d'un membre, de son profil écarté de e : bandes [[abscisse, bande]], la bande d'un rang valant jusqu'au
// suivant ; chaque bout se referme contre le membre, ou par un pôle quand il est rond (debut/fin à 'pole'). `rond` (l'avatar,
// a.raccords) fait d'un bout rond une demi-sphère centrée sur son abscisse, qui est le pivot de l'articulation, au lieu d'un
// cône qui le dépasse de 3,5 cm ; une fin ronde se dessine alors de la couleur de la dernière bande, même quand celle-ci
// laissait le bout ouvert dans le manchon suivant : plié, le coude montrait l'intérieur
// `pasDebut` et `pasFin` donnent leurs rangs aux bouts ronds (boutRond()). `retrait` réduit d'autant la demi-sphère du début,
// que le manchon quitte en reprenant sa largeur 5 cm plus loin : au coude, elle reste dans celle de la manche du bras au lieu
// de la percer. `ourlet` referme un bord de fin jusqu'à l'axe. `aplatir` ramène le pôle d'un début, demi-sphère d'un bout
// rond ou pointe du cône, à cette part de sa profondeur le long du membre, sans rang de plus
function manchon(THREE, membre, bandes, e, { debut = 'bord', fin = 'bord', S = membre.S, rond = false, pasDebut = 2, pasFin = 2, retrait = 1, ourlet = false, aplatir = 1 } = {}) {
	const r = s => interpoler(membre.profil, s) + e;
	const m = [];
	const [s0] = bandes[0], [s1] = bandes[bandes.length - 1];
	if(debut === 'pole' && rond) m.push(...boutRond(r(s0) * retrait, s0, -1, pasDebut).map(([rv, sv]) => [rv, s0 + (sv - s0) * aplatir, bandes[0][1]]));
	else if(debut === 'pole') m.push([0, s0 - .035 * aplatir, bandes[0][1]]);
	else m.push([r(s0) - e - .002, s0, bandes[0][1]]);
	if(retrait < 1) m.push([r(s0) * retrait, s0, bandes[0][1]], [r(s0 + .05), s0 + .05, bandes[0][1]]);
	for(const [sv, b] of retrait < 1 ? bandes.slice(1) : bandes) m.push([r(sv), sv, b]);
	if(fin === 'pole' && rond) {
		const bande = bandes.at(-1)[1] ?? bandes.at(-2)?.[1] ?? null;
		m.at(-1)[2] = bande;
		m.push(...boutRond(r(s1), s1, 1, pasFin).map(([rv, sv], k) => [rv, sv, k < pasFin - 1 ? bande : null]));
	}
	else if(fin === 'pole') m.push([0, s1 + .035, null]);
	else {
		// L'ourlet de la manche courte de l'avatar se referme jusqu'à l'axe, de la couleur de la dernière bande : la manche
		// retire toute la peau du haut du bras, et refermé seulement sur le membre il laissait un trou de la largeur du bras,
		// par où se voyait le fond dès que le coude pliait. Il rentre en pente sur 12 mm puis se ferme en 8 : à plat, il
		// tournait le dos à la lumière et se lisait comme une arête sombre. L'avant-bras qui le perce y sort comme de la manche
		if(ourlet) {
			const bande = bandes.at(-1)[1] ?? bandes.at(-2)?.[1] ?? null;
			m.at(-1)[2] = bande;
			m.push([r(s1 + .012) - e - .001, s1 + .012, bande], [0, s1 + .02, null]);
		}
		else m.push([r(s1) - e - .002, s1, null]);
	}
	return grille(THREE, rangsAutour(THREE, m.map(([rv, sv]) => [rv, sv]), S, membre.A, membre.axe), S, k => m[k][2]);
}

// Une pièce plate posée sur la gaine du torse : un polygone convexe donné en [azimut, hauteur], en éventail depuis son premier
// point. Un triangle haut de plus de 7 cm se redécoupe : sa corde passerait sous le bombé de la poitrine et la pièce
// disparaîtrait dans ce qu'elle couvre. Les scènes ne dessinent que les faces tournées vers elles : un contour donné dans le
// sens des aiguilles d'une montre en [azimut, hauteur] tournerait ses faces vers le corps, et la pièce, vue au banc qui
// dessine les deux côtés, manquerait dans la scène. Il se remet donc dans le sens inverse avant l'éventail
function plaque(THREE, a, contour, ecart, pas = .07) {
	const { surCorps } = a.corps;
	const aire = contour.reduce((s, [x0, y0], i) => { const [x1, y1] = contour[(i + 1) % contour.length]; return s + x0 * y1 - x1 * y0; }, 0);
	const points = aire < 0 ? [contour[0], ...contour.slice(1).reverse()] : contour;
	const pos = [];
	for(let i = 1; i < points.length - 1; i++) {
		const [A, B, C] = [points[0], points[i], points[i + 1]];
		const ys = [A[1], B[1], C[1]], k = Math.max(1, Math.ceil((Math.max(...ys) - Math.min(...ys)) / pas));
		const at = (u, v) => surCorps(A[0] + (B[0] - A[0]) * u + (C[0] - A[0]) * v, A[1] + (B[1] - A[1]) * u + (C[1] - A[1]) * v, ecart);
		for(let r = 0; r < k; r++) {
			for(let c = 0; c < k - r; c++) {
				const u0 = r / k, v0 = c / k, d = 1 / k;
				pos.push(...at(u0, v0), ...at(u0 + d, v0), ...at(u0, v0 + d));
				if(c < k - r - 1) pos.push(...at(u0 + d, v0), ...at(u0 + d, v0 + d), ...at(u0, v0 + d));
			}
		}
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.computeVertexNormals();
	return g;
}

// Une gaine autour du tronc : rangs [[rayon, y, bande]], le rayon déjà écarté du corps ; la bande d'un rang vaut pour le
// quadrilatère qui part de lui vers le rang suivant, et chaque rang compte S côtés, ceux du corps par défaut. Avec moins de
// côtés que le corps, le milieu d'une facette rentre davantage, et ce qui se porte dessous (le fût du pantalon) y perçait :
// le rang s'élargit donc jusqu'à ce que le milieu de ses facettes revienne où était celui des facettes du corps
const elargi = (a, S) => Math.cos(Math.PI / a.corps.S) / Math.cos(Math.PI / S);
function gaine(THREE, a, rangs, S = a.corps.S) {
	const { aplati, galbe } = a.corps;
	const k = elargi(a, S);
	const points = rangs.map(([r, y]) => r === 0 ? { pole: [0, y, 0] } : Array.from({ length: S }, (_, j) => {
		const t = j / S * Math.PI * 2, rb = (r + galbe(t, y)) * k;
		return [Math.sin(t) * rb, y, Math.cos(t) * rb * aplati(y)];
	}));
	return grille(THREE, points, S, r => rangs[r][2]);
}

// Un disque posé à plat sur la gaine, couché sur la pente du profil pour que sa moitié basse ne s'enfonce pas
function disque(THREE, a, az, y, rd, ecart) {
	const { rayon, surCorps } = a.corps;
	const c = new THREE.Vector3(...surCorps(az, y, ecart));
	const pente = (rayon(y + .01) - rayon(y - .01)) / .02;
	const u = new THREE.Vector3(Math.cos(az), 0, -Math.sin(az));
	const v = new THREE.Vector3(Math.sin(az) * pente, 1, Math.cos(az) * pente * .88).normalize();
	const n = new THREE.Vector3().crossVectors(u, v).normalize();
	const pos = [], nor = [], seg = 6;
	for(let i = 0; i < seg; i++) {
		const t0 = i / seg * Math.PI * 2, t1 = (i + 1) / seg * Math.PI * 2;
		const p0 = c.clone().addScaledVector(u, Math.cos(t0) * rd).addScaledVector(v, Math.sin(t0) * rd);
		const p1 = c.clone().addScaledVector(u, Math.cos(t1) * rd).addScaledVector(v, Math.sin(t1) * rd);
		pos.push(c.x, c.y, c.z, p0.x, p0.y, p0.z, p1.x, p1.y, p1.z);
		for(let k = 0; k < 3; k++) nor.push(n.x, n.y, n.z);
	}
	return geometrie(THREE, pos, nor);
}

// Une bande de largeur 2 l entre deux points du corps (azimut, hauteur), sa largeur prise en travers de son tracé déroulé sur
// le corps, pour qu'une bande qui fait le tour de l'épaule à hauteur constante ne s'écrase pas en fil. Son premier côté est
// à gauche du tracé, quel que soit son sens : les triangles se ferment donc de p0 vers p3, pour tourner leurs faces vers
// le dehors
function bande(THREE, a, az0, y0, az1, y1, l, ecart) {
	const { rayon, surCorps } = a.corps;
	const r0 = rayon(y0), r1 = rayon(y1);
	const du = (az1 - az0) * (r0 + r1) / 2, dv = y1 - y0, n = Math.hypot(du, dv) || 1;
	const nu = -dv / n * l, nv = du / n * l;
	const p = [surCorps(az0 - nu / r0, y0 - nv, ecart), surCorps(az0 + nu / r0, y0 + nv, ecart), surCorps(az1 + nu / r1, y1 + nv, ecart), surCorps(az1 - nu / r1, y1 - nv, ecart)];
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute([...p[0], ...p[2], ...p[1], ...p[0], ...p[3], ...p[2]], 3));
	g.computeVertexNormals();
	return g;
}

// Un trait droit d'encre le long du devant ou du dos de la gaine, sur ses propres rangs [[rayon, y]] écartés de ecart : une
// corde unique sauterait le creux du profil et flotterait, et un rang pris sur le corps plutôt que sur la gaine passe sous
// tout ce qu'elle gonfle (les boudins d'une doudoune, l'évasement de l'ourlet, un col montant), qui ne laisse voir que des
// tronçons
function couture(THREE, a, az, rangs, ecart, w) {
	const { galbe, aplati } = a.corps;
	const pos = [], nor = [], n = new THREE.Vector3(Math.sin(az), 0, Math.cos(az));
	const at = ([rg, y], s) => {
		const r = rg + ecart + galbe(az, y), b = az + s * w / r;
		return [Math.sin(b) * r, y, Math.cos(b) * r * aplati(y)];
	};
	// Le côté -1 est à gauche vu de dehors : un rang qui descend se ferme dans l'autre sens, pour tourner ses faces vers le dehors
	for(let i = 0; i < rangs.length - 1; i++) {
		const [ra, rb] = [rangs[i], rangs[i + 1]];
		if(rb[1] > ra[1]) pos.push(...at(ra, -1), ...at(ra, 1), ...at(rb, 1), ...at(ra, -1), ...at(rb, 1), ...at(rb, -1));
		else pos.push(...at(ra, -1), ...at(rb, 1), ...at(ra, 1), ...at(ra, -1), ...at(rb, -1), ...at(rb, 1));
		for(let k = 0; k < 6; k++) nor.push(n.x, n.y, n.z);
	}
	return geometrie(THREE, pos, nor);
}

// Le tracé d'une fermeture, du haut vers le bas, en [rayon, y] sur la gaine. Suivie rang par rang, elle reprend chaque coude
// du profil, et de trois quarts ces coudes se lisent comme une ligne cassée : sous le col, le profil rentre vers le cou plus
// vite qu'il ne descend, et ce palier faisait partir la fermeture en oblique, puis le coude de la poitrine la cassait en
// baïonnette, puis le dessous de la poitrine la renvoyait d'un coup vers le ventre. Aucun de ces coudes ne s'arrondit sans
// quitter la gaine, la fermeture flotte donc là de quelques millimètres :
// - le palier, rentrant, se franchit par une courbe, du rang au-dessus de lui au rang en dessous, tangente à ce qu'elle
//   quitte et à ce qu'elle rejoint. Parmi les poignées essayées, on garde celle qui s'écarte le moins de la gaine sans la
//   percer, mesurée sur les cordes que la fermeture dessine vraiment. Sous un col rond, la fermeture s'arrête au haut du
//   palier, et rien ne la précède dont la courbe serait tangente : elle garde le palier ;
// - le coude de la poitrine, saillant, se coupe à 30 % de chaque rang par une courbe en quatre cordes, relevée jusqu'à ce
//   que son milieu passe par le coude : elle reste ainsi au-dessus des deux rangs, et la fermeture y tourne en cinq fois au
//   lieu d'une ;
// - le dessous de la poitrine, rentrant, se coupe de même par une courbe en trois cordes, qui passe au-dessus du coude sans
//   qu'on la relève.
// Les boudins d'une doudoune gardent leurs coudes, que la fermeture suit pour rester posée dessus
function trace(a, rangs, boudins) {
	const { galbe } = a.corps;
	const pts = rangs.map(([r, y]) => [y, r + galbe(0, y)]);
	const k = pts.findIndex(([y0, r0], i) => i < pts.length - 2 && Math.abs(pts[i + 1][1] - r0) > Math.abs(pts[i + 1][0] - y0));
	if(k === -1) return rangs;
	const surface = y => {
		for(let i = 0; i < pts.length - 1; i++) {
			const [[y0, r0], [y1, r1]] = [pts[i], pts[i + 1]];
			if(y <= y0 && y >= y1) return y0 === y1 ? Math.max(r0, r1) : r0 + (r1 - r0) * (y - y0) / (y1 - y0);
		}
		return -Infinity;
	};
	const vers = (p, q) => { const d = [q[0] - p[0], q[1] - p[1]], l = Math.hypot(...d); return [d[0] / l, d[1] / l]; };
	const [S, P] = [pts[k + 1], pts[k + 2]];

	// Le coude de la poitrine, s'il est saillant et qu'un rang le suit
	let poitrine = [P], suite = k + 3;
	const Q = pts[k + 3];
	if(!boudins && Q !== undefined && (P[0] - S[0]) * (Q[1] - S[1]) - (P[1] - S[1]) * (Q[0] - S[0]) > 0) {
		const c1 = [P[0] + .3 * (S[0] - P[0]), P[1] + .3 * (S[1] - P[1])], c2 = [P[0] + .3 * (Q[0] - P[0]), P[1] + .3 * (Q[1] - P[1])];
		const v = [(2 * P[0] - c1[0] - c2[0]) / 4, (2 * P[1] - c1[1] - c2[1]) / 4];
		poitrine = [0, .25, .5, .75, 1].map(t => [0, 1].map(j => (1 - t) * (1 - t) * c1[j] + 2 * t * (1 - t) * P[j] + t * t * c2[j] + v[j]));
		const [D, T] = [poitrine.at(-1), pts[k + 4]];
		if(T !== undefined && (Q[0] - D[0]) * (T[1] - D[1]) - (Q[1] - D[1]) * (T[0] - D[0]) < 0) {
			const d1 = [Q[0] + .3 * (D[0] - Q[0]), Q[1] + .3 * (D[1] - Q[1])], d2 = [Q[0] + .3 * (T[0] - Q[0]), Q[1] + .3 * (T[1] - Q[1])];
			poitrine.push(...[0, 1 / 3, 2 / 3, 1].map(t => [0, 1].map(j => (1 - t) * (1 - t) * d1[j] + 2 * t * (1 - t) * Q[j] + t * t * d2[j])));
			suite = k + 4;
		}
	}

	let palier = null;
	const A = pts[Math.max(0, k - 1)], L = Math.hypot(S[0] - A[0], S[1] - A[1]), N = 4, essais = [.2, .35, .5, .65, .8];
	// Le haut de la fermeture, sans rang au-dessus de lui, descend droit
	const tA = k > 1 ? vers(pts[k - 2], A) : [-1, 0], tS = vers(S, poitrine[0]);
	if(k > 0) for(const h1 of essais) for(const h2 of essais) {
		const c = [A, [A[0] + tA[0] * h1 * L, A[1] + tA[1] * h1 * L], [S[0] - tS[0] * h2 * L, S[1] - tS[1] * h2 * L], S];
		const points = Array.from({ length: N + 1 }, (_, i) => {
			const t = i / N, u = 1 - t, w = [u * u * u, 3 * u * u * t, 3 * u * t * t, t * t * t];
			return [0, 1].map(j => w.reduce((s, wi, m) => s + wi * c[m][j], 0));
		});
		if(points.some(([y], i) => i > 0 && y >= points[i - 1][0])) continue;
		let ecart = 0, perce = false;
		for(let i = 0; i < N && !perce; i++) for(let m = 0; m <= 8; m++) {
			const [[y0, r0], [y1, r1]] = [points[i], points[i + 1]], y = y0 + (y1 - y0) * m / 8, d = r0 + (r1 - r0) * m / 8 - surface(y);
			if(d < -1e-6) { perce = true; break; }
			ecart = Math.max(ecart, d);
		}
		if(!perce && (palier === null || ecart < palier.ecart)) palier = { ecart, points };
	}
	const franchi = palier === null ? pts.slice(0, k + 2) : [...pts.slice(0, k - 1), ...palier.points];
	return [...franchi, ...poitrine, ...pts.slice(suite)].map(([y, rt]) => [rt - galbe(0, y), y]);
}

// L'ouverture d'une veste sur la chemise : un V du col jusque sous la poitrine, en [azimut, hauteur]
const ouverture = a => {
	const { yEpaule } = a.corps;
	return { haut: yEpaule - .004, bas: yEpaule - .2, demi: .42 };
};

// Le contour d'un revers, côté c (1 à gauche, -1 à droite), en [azimut, hauteur], posé à e + .006 sur le corps. L'écharpe le
// lit pour passer par-dessus
const revers = (a, c) => {
	const v = ouverture(a);
	return [[0, v.bas - .006], [c * (v.demi + .05), v.haut - .01], [c * (v.demi + .2), v.haut - .06], [c * .05, v.bas + .02]];
};

// Les deux boutons qui ferment la veste sous l'ouverture, [azimut, hauteur, rayon], posés à e + .004
const boutons = a => {
	const v = ouverture(a);
	return [[0, v.bas - .05, .011], [0, v.bas - .13, .011]];
};

// La part du bras, de l'épaule vers la main, que couvre une manche courte. Elle se lit d'ici : l'écart des bras de l'avatar
// compte l'épaisseur de la manche là où elle s'arrête (personnage.js, manche()), et une valeur recopiée là-bas compterait une
// manche que haut() ne taille plus au même endroit
const MANCHE_COURTE = .42;

// La profondeur de la calotte d'une manche au-dessus du pivot de l'épaule, en part de sa profondeur entière : le rayon de la
// demi-sphère sur l'avatar, les 3,5 cm du cône dans les scènes. Partie du pivot où finit la pente du buste, une calotte
// entière dépasse de cette pente de toute sa profondeur et se lit en bosse au bout de l'épaule ; cette part la garde sous
// la pente, de face comme de trois quarts (décidé le 2026-10-19)
const MANCHE_CALOTTE = .4;

// Un haut fait dans Blender (debardeur.blend, chemise-carreaux.blend, veste-travail.blend), monté pour une personne. Chaque
// objet dit par sa condition ce qui le retire : `veste` pour ce qu'une veste portée par-dessus couvre du col à l'ourlet,
// `manches` pour ce qu'une veste à manches longues couvre, `col` pour un col que cache celui d'une veste aussi haut que lui.
// Sa forme clé `rentre` ramène l'ourlet contre le corps sous une salopette, dont la ceinture passe par-dessus : évasé comme il
// l'est sur un pantalon, il en traversait les facettes.
// Le profil, que lisent l'écart des bras de l'avatar et l'écharpe, se mesure sur la pièce montée : la forme se retouche dans
// Blender, et des rangs recopiés ici ne la suivraient pas
function hautBlender(THREE, a, p) {
	const { rayon, yEpaule } = a.corps, e = p.epaisseur, manches = p.manches !== 'aucune' && a.veste?.manches !== 'longues';
	const retirees = new Set([...(a.veste ? ['veste'] : []), ...(manches ? [] : ['manches']), ...(a.veste?.encolure && a.veste.encolure.y >= yEpaule + .02 ? ['col'] : [])]);
	const pivots = { torse: a.pivots.torse, bassin: a.pivots.bassin };
	for(const cote of ['brasG', 'brasD']) {
		pivots[cote] = a.bras[cote].A.toArray();
		pivots[a.bras[cote].avant] = a.bras[cote].E.toArray();
	}
	const montes = depuisBlender(THREE, p.piece, { ...a.morphologie, rentre: a.raccords?.sousSalopette ? 1 : 0 }, p).filter(m => retirees.has(m.condition) === false);
	const parts = montes.map(m => ({ name: m.os, geometry: m.geometry, color: m.color, pivot: pivots[m.os] }));
	// Le tour du tronc est l'objet `<pièce>.corps` : ses anneaux sont à plat, un rang du profil par anneau, au millimètre
	const anneaux = new Map();
	for(const m of montes) {
		if(m.objet.endsWith('.corps') === false) continue;
		const pos = m.geometry.attributes.position;
		for(let i = 0; i < pos.count; i++) {
			const k = Math.round(pos.getY(i) * 1000);
			anneaux.set(k, Math.max(anneaux.get(k) ?? 0, Math.abs(pos.getX(i))));
		}
	}
	const profil = [...anneaux].sort(([x], [y]) => x - y).map(([k, r]) => [r, k / 1000]);
	const couvre = ['ventre'];
	let encolure = null;
	if(p.col !== 'debardeur') {
		couvre.push('poitrine', 'epaules', 'encolure');
		encolure = { y: yEpaule + .03, r: rayon(yEpaule + .03) + e + .006 };
	}
	if(manches) couvre.push('brasHaut', ...(p.manches === 'longues' ? ['avantBras'] : []));
	return { parts, couvre, encolure, ecart: e, profil, revers: false };
}

function haut(THREE, a, p) {
	if(p.piece !== undefined && aPiece(p.piece)) return hautBlender(THREE, a, p);
	const { rayon, yTaille, ySousPoitrine, yPoitrine, yEpaule, yHanche } = a.corps;
	const e = p.epaisseur, lis = p.lisere ?? [];
	// Un vêtement à capuche tourne en douze côtés au lieu de seize. La capuche coûte à elle seule autant qu'un haut sans
	// manches, et ces vêtements (sweat, veste de pluie, doudoune) font passer le personnage au-dessus du plafond quand
	// s'y ajoutent un couvre-chef et un accessoire. Douze côtés ne se voient pas à la taille des scènes : ils gardent un
	// sommet devant, où passe la fermeture, et un de chaque côté, qui fait la largeur vue de face
	const S = p.capuche ? 12 : a.corps.S;
	const parts = [], couvre = [];
	const couleurs = { haut: p.couleur, col: lis[0] ?? nuance(THREE, p.couleur, -.06), lisere0: lis[0], lisere1: lis[1] ?? lis[0], revers: nuance(THREE, p.couleur, -.05), pique: nuance(THREE, p.couleur, -.06) };
	// Un haut long descend sous la taille, où le buste plie : penché avec lui, son bas partait en arrière et faisait un sac
	// derrière les talons à l'accroupi. Les triangles tout entiers sous la taille restent donc au bassin, qui ne plie pas ;
	// debout, rien ne bouge
	const torse = (geometry, color) => {
		if(!p.longue) return parts.push({ name: 'torse', geometry, color, pivot: a.pivots.torse });
		const g = geometry.index ? geometry.toNonIndexed() : geometry;
		if(!g.attributes.normal) g.computeVertexNormals();
		const pos = g.attributes.position.array, nor = g.attributes.normal.array, cotes = { torse: [[], []], bassin: [[], []] };
		for(let i = 0; i < pos.length; i += 9) {
			const cote = Math.max(pos[i + 1], pos[i + 4], pos[i + 7]) < yTaille ? 'bassin' : 'torse';
			cotes[cote][0].push(...pos.slice(i, i + 9));
			cotes[cote][1].push(...nor.slice(i, i + 9));
		}
		// Le bas porte `ourlet` : un module qui recoupe le bassin à son propre pli sait qu'il ne s'agit pas du pantalon
		for(const [name, [cp, cn]] of Object.entries(cotes)) if(cp.length) parts.push({ name, geometry: geometrie(THREE, cp, cn), color, pivot: a.pivots[name], ourlet: name === 'bassin' });
	};

	// Une chemise sous une veste : seuls son plastron, dans l'ouverture, et son col autour du cou se voient
	if(p.col === 'chemise' && a.veste) {
		// Le plastron, en deux pièces : sous l'épaule marquée de la veste, le profil tourne vite et une corde de 7 cm passait
		// sous la veste, qui coupait la chemise en deux entre le col et le V. Le haut se découpe donc en rangs de 2 cm
		const v = ouverture(a), ev = a.veste.ecart, y1 = v.haut - .06, l1 = v.demi * (y1 - v.bas) / (v.haut - v.bas);
		torse(plaque(THREE, a, [[0, v.bas], [l1, y1], [-l1, y1]], ev + .002), p.couleur);
		torse(plaque(THREE, a, [[-l1, y1], [l1, y1], [v.demi, v.haut], [.18, v.haut + .012], [0, v.haut + .004], [-.18, v.haut + .012], [-v.demi, v.haut]], ev + .002, .02), p.couleur);
		const col = [[.13 + .016, yEpaule - .004, 'haut'], [.128 + .012, yEpaule + .035, null], [.12, yEpaule + .035, null]];
		for(const g of Object.values(gaine(THREE, a, col))) torse(g, p.couleur);
		return { parts, couvre: ['encolure'], ecart: ev };
	}

	// L'ourlet descend sur la ceinture du bas, qui passe dessous ; celui d'une veste couvre le haut des hanches, celui d'un
	// vêtement long le haut des cuisses
	// Un vêtement long dit de combien son ourlet descend sous l'entrejambe, 14 cm par défaut, le haut des cuisses
	const y0 = p.longue ? yHanche - (p.ourlet ?? .14) : p.col === 'veste' ? yTaille - .11 : yTaille - .05, rangs = [];
	couvre.push('ventre');
	const pt = (y, b, r = rayon(y) + e) => rangs.push([r, y, b]);
	// Sous la hanche, le profil du tronc se referme vers l'entrejambe : un vêtement long garde la largeur des hanches, qui
	// passe au large des cuisses écartées
	const large = y => Math.max(rayon(y), rayon(yHanche + .06));
	// L'ourlet s'évase un peu sous la ceinture : un buste penché soulève l'arrière du haut, et un ourlet serré y croisait la
	// ceinture du bas facette contre facette, en dents de scie
	pt(y0 - .015, 'haut', large(y0) + .004);
	pt(y0 - .015, 'haut', large(y0) + e + (p.longue ? 0 : .012));
	if(p.matelasse) {
		// Le matelassage : un rang tous les 10 cm, un rang gonflé sur deux, jusque sous les épaules. Le tissu piqué entre deux
		// boudins prend un ton plus sombre. Le nombre de rangs reste impair, pour que le dernier, sous les épaules, soit gonflé
		const n = 2 * Math.round(((yPoitrine - y0) / .1 - 1) / 2) + 1;
		for(let k = 1; k <= n; k++) {
			const y = y0 + (yPoitrine - y0) * k / n;
			pt(y, k % 2 ? 'haut' : 'pique', large(y) + e + (k % 2 ? .01 : 0));
		}
	} else {
		if(p.longue) pt(yHanche + .06, 'haut');
		pt(yTaille, 'haut');
		pt(ySousPoitrine, 'haut');
		pt(yPoitrine, 'haut');
	}
	// Le haut du col, rayon et hauteur, que lit un haut porté dessous pour en dépasser
	let yHaut, encolure = null;
	if(p.col === 'debardeur') {
		// Le débardeur s'arrête sous les épaules, qui restent nues ; deux bretelles le tiennent
		yHaut = yEpaule - .075;
		pt(yHaut, 'haut');
		pt(yHaut, null, rayon(yHaut) + .002);
	} else {
		couvre.push('poitrine', 'epaules');
		// Une veste a des épaules marquées
		pt(yEpaule - .025, 'haut', rayon(yEpaule - .025) + e + (p.col === 'veste' ? .01 : 0));
		if(p.col === 'rond' || p.col === 'veste') {
			yHaut = yEpaule + .008;
			pt(yEpaule - .004, 'col', .158 + e);
			pt(yHaut, null, rayon(yHaut) + e * .6);
			pt(yHaut, null, rayon(yHaut) - .002);
			encolure = { y: yHaut, r: rayon(yHaut) + e * .6 };
		} else if(p.col === 'polo' || p.col === 'chemise') {
			couvre.push('encolure');
			yHaut = yEpaule + .03;
			pt(yEpaule - .004, lis.length ? 'lisere0' : 'haut', .158 + e);
			pt(yEpaule + .006, lis.length > 1 ? 'lisere1' : 'haut', rayon(yEpaule + .006) + e + .008);
			pt(yEpaule + .014, 'haut', rayon(yEpaule + .014) + e + .01);
			pt(yHaut, null, rayon(yHaut) + e + .006);
			pt(yHaut, null, rayon(yHaut) - .002);
			encolure = { y: yHaut, r: rayon(yHaut) + e + .006 };
		} else {
			// Le col montant d'une polaire monte devant le cou, qu'il cache en entier
			couvre.push('encolure', 'cou');
			yHaut = yEpaule + .052;
			pt(yEpaule - .004, 'haut', .158 + e);
			pt(yEpaule + .025, 'col', .148 + e);
			pt(yHaut, null, .146 + e);
			// Sur l'avatar, le dedans du col descend jusque sous la base de la tête et s'y referme en pointe : ouvert à son bord, il
			// laissait un anneau vide entre lui et le menton, qui s'ouvrait dès que le buste penchait, et ouvert en bas on voyait le
			// fond au travers
			if(a.raccords) {
				rangs.at(-1)[2] = 'col';
				pt(yHaut, 'col', .118);
				pt(a.corps.yCou - .01, 'col', .095);
				pt(a.corps.yCou - .04, null, 0);
			}
			else pt(yHaut, null, .118);
			encolure = { y: yHaut, r: .146 + e, S };
		}
	}
	// Sous une veste, le corps du haut ne se voit pas, la veste le couvre du col à l'ourlet : seules ses manches et sa capuche
	// sortent. Les deux épaisseurs de tissu coûtaient deux fois le torse
	const cache = a.veste !== undefined;
	// Sur l'avatar, le buste tourne au pli des hanches (a.raccords.pli) : d'un haut dont l'ourlet descend sous le pli, il
	// emporte la gaine au-dessus du premier rang qui le passe, et penché il en levait le bord et ouvrait un jour sur le fond,
	// que rien ne ferme sous une veste. Le retour de l'ourlet contre le corps, qu'aucune vue ne prend d'en dessous, devient
	// une doublure à la manière de celle du pantalon (bas()) : le rang du pli, sous la gaine, tourné de 60° autour de l'axe
	// du pli et rangé au bassin entier. Le bord de la gaine passe dessus sans la quitter ; ses côtés ne bougent pas, comme
	// ceux du buste. Le buste le plus penché (Fatigué, la récolte) ne tourne que de 0,65 rad, mais un regard qui entre par le
	// jour du dos ressort par le flanc opposé, où la doublure, qui monte avec l'écart à l'axe, est plus basse : à 40°, il y
	// passait encore. Même nombre de triangles : un rang en chasse un autre
	const doublure = a.raccords !== undefined && y0 < a.raccords.pli && !cache;
	if(!cache) for(const [b, g] of Object.entries(gaine(THREE, a, doublure ? rangs.slice(1) : rangs, S))) torse(g, couleurs[b]);
	if(doublure) {
		// La doublure se prend sur la gaine elle-même, rentrée de 2,5 % vers l'axe, à la hauteur où elle passe : de la même
		// largeur jusqu'en haut, elle sortait sur les reins, où la gaine se resserre de la hanche à la taille
		const pli = a.raccords.pli, k = elargi(a, S);
		const sur = (j, y, rentrer = true) => {
			const i = Math.max(1, rangs.findLastIndex(([, yr]) => yr <= y)), u = (y - rangs[i][1]) / (rangs[i + 1][1] - rangs[i][1]), t = j / S * Math.PI * 2;
			// Le rang qui chevauche le pli reste au bassin, et le contrat de l'avatar en rentre de 40 % les sommets d'arrière au-dessus
			// du pli (avatar/contrat.js) : la doublure le suit, sinon elle sortait sous le bord du buste au milieu du dos
			const rentre = (yr, z) => rentrer && rangs[i][1] < pli && yr > pli && z < 0 ? z * .6 : z;
			const [[x0, z0], [x1, z1]] = [rangs[i], rangs[i + 1]].map(([r, yr]) => { const rb = (r + a.corps.galbe(t, yr)) * k; return [Math.sin(t) * rb, rentre(yr, Math.cos(t) * rb * a.corps.aplati(yr))]; });
			return [(x0 + (x1 - x0) * u) * .975, (z0 + (z1 - z0) * u) * .975];
		};
		const points = [0, 60].map(d => Array.from({ length: S }, (_, j) => {
			// Elle monte d'après la gaine avant le retrait : rentrée, elle s'arrêtait sous le jour que le dos du buste ouvre
			const z = sur(j, pli)[1], y = pli + Math.abs(sur(j, pli, false)[1]) * Math.sin(deg(d));
			return [sur(j, y)[0], y, z * Math.cos(deg(d))];
		}));
		for(const g of Object.values(grille(THREE, points, S, r => r === 0 ? 'haut' : null))) parts.push({ name: 'bassin', geometry: g, color: couleurs.pique, pivot: a.pivots.bassin, cote: 'bassin' });
	}
	// Un col montant porté sous une veste en dépasse d'un centimètre et demi, à sa propre hauteur si la veste a le col plus
	// bas : c'est la couleur qui se voit au menton. Il part de l'intérieur du col de la veste, qui en cache le pied, et prend
	// ses côtés : plus fin qu'elle, il sortirait au milieu de chacune de ses facettes, rentrées sous les sommets
	if(cache && p.col === 'montant' && a.veste.encolure) {
		const v = a.veste.encolure, sommet = Math.max(yHaut, v.y + .014);
		for(const g of Object.values(gaine(THREE, a, [[v.r - .006, v.y - .03, 'haut'], [Math.max(.146 + e, v.r + .002), sommet, 'haut'], [.118, sommet, null]], v.S ?? a.corps.S))) torse(g, p.couleur);
		couvre.push('encolure', 'cou');
	}

	if(p.col === 'debardeur' && !cache) {
		// Une bretelle est un cordon rond posé sur l'épaule : peinte à plat, elle se brisait sur les arêtes de la pente
		const pas = [[.5, yHaut + .01], [.9, yEpaule - .012], [Math.PI / 2, yEpaule - .002]];
		const tour = [...pas, ...pas.slice(0, -1).reverse().map(([az, y]) => [Math.PI - az, y])];
		for(const c of [1, -1]) torse(anse(THREE, tour.map(([az, y]) => a.corps.surCorps(c * az, y, e + .006)), .009), p.couleur);
	}
	// Les pointes du col de chemise, deux triangles sur le devant
	if(p.col === 'chemise' && !cache) {
		for(const c of [1, -1]) torse(plaque(THREE, a, [[c * .05, yEpaule + .012], [c * .26, yEpaule + .004], [c * .1, yEpaule - .05]], e + .012), p.couleur);
	}
	// La veste : les revers bordent l'ouverture, deux boutons la ferment dessous
	if(p.col === 'veste') {
		const v = ouverture(a);
		for(const c of [1, -1]) torse(plaque(THREE, a, revers(a, c), e + .006), couleurs.revers);
		for(const [az, y, rd] of boutons(a)) torse(disque(THREE, a, az, y, rd, e + .004), nuance(THREE, p.couleur, -.12));
	}

	// Fermeture : un zip du col à l'ourlet, ou une patte à deux boutons sous le col
	// La fermeture suit les rangs du dehors de la gaine, de l'ourlet au dernier rang peint du col. Le premier rang est le
	// retour de l'ourlet contre le corps, les deux derniers le bord et l'intérieur du col. Un col montant la porte jusqu'à 8 mm
	// de son bord, comme une fermeture qui monte au menton ; un col rond l'arrête à son pied
	if(p.fermeture === 'zip' && !cache) {
		// Le rang élargi d'une gaine à moins de côtés (gaine()) l'est aussi devant, où la fermeture se pose
		const k = elargi(a, S), devant = ([r, y]) => [(r + a.corps.galbe(0, y)) * k - a.corps.galbe(0, y), y];
		const dehors = rangs.slice(1, -2).map(devant);
		if(p.col === 'montant') {
			const [[r0, ya], [r1, yb]] = [devant(rangs.at(-3)), devant(rangs.at(-2))], y = yb - .008;
			dehors.push([r0 + (r1 - r0) * (y - ya) / (yb - ya), y]);
		}
		torse(couture(THREE, a, 0, trace(a, dehors.reverse(), p.matelasse), .003, .008), p.zip ?? ENCRE);
	} else if(p.fermeture === 'boutons' && !cache) {
		// À 4 mm, le bouton du haut s'enfonçait à moitié dans la pente sous le col et se lisait en croissant
		for(const dy of [.045, .095]) torse(disque(THREE, a, 0, yEpaule - dy, .012, e + .006), CREME);
	}
	// L'écusson est un blason, pointe en bas : un hexagone sur la poitrine ne se lisait comme rien
	if(p.ecusson && !cache) torse(plaque(THREE, a, [[.5, yPoitrine - .03], [.57, yPoitrine - .005], [.57, yPoitrine + .025], [.43, yPoitrine + .025], [.43, yPoitrine - .005]], e + .004), p.ecusson);
	// La capuche rabattue : un bourrelet qui fait le tour de la nuque, d'une épaule à l'autre, et le fond de la capuche qui
	// retombe à plat sur le haut du dos, par-dessus la veste qui la laisse sortir. Une boule posée sous la nuque, sans le
	// bourrelet, ne se lisait comme rien. Elle prend la finesse du vêtement : la moitié de ses côtés sur le demi-tour du
	// bourrelet et autour du fond
	if(p.capuche) {
		const ev = a.veste ? a.veste.ecart : 0, r = rayon(yEpaule - .04) + Math.max(e, ev);
		torse(new THREE.TorusGeometry(.15 + Math.max(e, ev), .022, 3, S / 2, Math.PI).rotateX(-Math.PI / 2).translate(0, yEpaule + .004, -.012), p.couleur);
		torse(ell(THREE, 0, yEpaule - .07, -r * .92 - .012, .13, .1, .03, S / 2, S / 4 + 1), p.couleur);
	}

	// Les manches : un manchon sur le haut du bras, et pour une manche longue un second sur l'avant-bras, qui se recouvrent
	// au coude par leurs bouts ronds. Sous une veste à manches longues, celles du haut ne se verraient nulle part
	if(p.manches !== 'aucune' && a.veste?.manches !== 'longues') {
		couvre.push('brasHaut');
		if(p.manches === 'longues') couvre.push('avantBras');
		for(const cote of ['brasG', 'brasD']) {
			const b = a.bras[cote], membre = { A: b.A, axe: b.axe, profil: b.profil, S: b.S };
			// La manche longue de l'avatar passe d'un centimètre sur la main, qui commence 2 cm avant le bout de l'avant-bras : arrêtée
			// 3,5 cm avant, elle laissait 15 mm de vide au poignet, où se voyait ce qu'il y a derrière
			const s1 = p.manches === 'courtes' ? b.L * MANCHE_COURTE : b.L - (a.raccords ? .012 : .035), rond = a.raccords !== undefined;
			const poignet = lis.length ? [[s1 - .03, 'lisere0'], [s1 - .015, lis.length > 1 ? 'lisere1' : 'lisere0'], [s1, null]] : [[s1 - .025, p.manches === 'longues' ? 'col' : 'haut'], [s1, null]];
			const range = (piece, pivot, g) => { for(const [k, geo] of Object.entries(g)) parts.push({ name: piece, geometry: geo, color: couleurs[k], pivot }); };
			if(p.manches === 'courtes') {
				range(cote, b.A.toArray(), manchon(THREE, membre, [[.0, 'haut'], [.03, 'haut'], ...poignet], e, { debut: 'pole', rond, ourlet: rond, aplatir: MANCHE_CALOTTE }));
			} else {
				range(cote, b.A.toArray(), manchon(THREE, membre, [[.0, 'haut'], [.03, 'haut'], [b.Le, null]], e, { debut: 'pole', fin: 'pole', rond, pasFin: rond ? 3 : 2, aplatir: MANCHE_CALOTTE }));
				range(b.avant, b.E.toArray(), manchon(THREE, membre, [[b.Le, 'haut'], ...poignet], e, { debut: 'pole', rond, retrait: rond ? .88 : 1 }));
			}
		}
	}
	// Le profil rend les rangs du torse, [rayon, hauteur], que lit ce qui se pose par-dessus
	return { parts, couvre, encolure, ecart: e + (p.col === 'veste' ? .01 : p.matelasse ? .014 : 0), profil: rangs.map(([r, y]) => [r, y]), revers: p.col === 'veste' };
}

// La salopette, ou cotte à bretelles, faite dans Blender (salopette.blend) : le fond sur le bassin, la ceinture par-dessus
// l'ourlet du haut, la bavette et sa poche, les bretelles qui passent l'épaule et descendent d'un trait jusqu'à la ceinture
// du dos, la cuisse et la jambe sur chaque membre. Ses formes clés suivent la morphologie ; `dessous` est l'épaisseur du
// haut porté dessous, sur lequel passent la ceinture, la bavette et les bretelles, et `pli` la hauteur du pli des hanches
// de l'avatar, où le fond porte un rang qui coupe la ceinture net.
// Elle se calcule après le haut et passe sous une veste, en deçà de son écart moins les 5 mm que perd le milieu d'une
// facette de la veste sur ses arêtes : une veste retire le dos des bretelles, une veste fermée la bavette et leur devant,
// une veste longue la ceinture. La doublure du pli ne sert qu'à l'avatar, où elle reste au bassin entière (`cote`).
// Sur l'avatar, la ceinture se range au torse : elle est au-dessus du pli des hanches, qu'elle suit avec le buste, et le pli
// d'une personne de moins de 1,61 m passe dans sa hauteur. Rangée au bassin, ses triangles à cheval sur le pli y restaient,
// leurs sommets d'arrière rentrés de 40 %, et la ceinture disparaissait dans le dos sous l'ourlet du haut
function salopette(THREE, a, p) {
	const sous = a.ecartSousVeste ?? .008, dessous = a.veste ? Math.min(sous, a.veste.ecart - .029) : sous;
	const retirees = new Set([...(a.veste ? ['veste'] : []), ...(a.veste?.fermee ? ['vesteFermee'] : []), ...(a.veste?.longue ? ['vesteLongue'] : []), ...(a.raccords ? [] : ['raccords'])]);
	const pivots = { bassin: a.pivots.bassin, torse: a.pivots.torse, jambeG: a.jambes.jambeG.hanche.toArray(), jambeD: a.jambes.jambeD.hanche.toArray(), molletG: a.jambes.jambeG.genou.toArray(), molletD: a.jambes.jambeD.genou.toArray() };
	const parts = depuisBlender(THREE, 'salopette', { ...a.morphologie, dessous, ...(a.raccords ? { pli: a.raccords.pli } : {}) }, p)
		.filter(m => retirees.has(m.condition) === false)
		.map(m => {
			const os = a.raccords && m.objet === 'salopette.ceinture' ? 'torse' : m.os;
			return { name: os, geometry: m.geometry, color: m.color, pivot: pivots[os], ...(m.condition === 'raccords' ? { cote: 'bassin' } : {}) };
		});
	return { parts, couvre: ['hanches', 'taille', 'cuisses', 'mollets'] };
}

function bas(THREE, a, p) {
	if(p.coupe === 'salopette' && aPiece('salopette')) return salopette(THREE, a, p);
	const { yTaille, yHanche } = a.corps;
	const e = .008, parts = [];
	const bassin = (geometry, color) => parts.push({ name: 'bassin', geometry, color, pivot: a.pivots.bassin });
	const pose = (piece, pivot, g, color) => { for(const geo of Object.values(g)) parts.push({ name: piece, geometry: geo, color, pivot }); };

	// La ceinture et le fond, sur le bassin, et au-dessus de la taille un fût qui monte dans le torse. Un buste penché relève
	// l'arrière du haut jusqu'à 15 cm au-dessus de la taille : le dôme d'avant, qui s'arrêtait à 10 cm, laissait voir l'intérieur
	// du haut par une fente. Le fût reste ouvert en haut, où il est toujours dans le torse
	const corps = a.corps;
	const rangs = [[0, yHanche - .105, 'bas'], [corps.rayon(yHanche - .07) + e, yHanche - .07, 'bas'], [corps.rayon(yHanche + .06) + e, yHanche + .06, 'bas'],
		[corps.rayon(yTaille) + e, yTaille, 'bas'], [corps.rayon(yTaille) - .055, yTaille + .12, null]];
	// L'avatar plie le buste au pli des hanches (a.raccords.pli), où ses pièces se partagent par triangle entier : un rang pris
	// juste au-dessus du pli coupe la ceinture net, sans triangle qui l'enjambe et reste au bassin quand le haut s'en va
	// Un buste qui part en arrière lève de 3 cm le devant de ce qui le suit, et penché en avant il en lève le dos. Une doublure
	// reste au bassin entière (`cote`) et se voit à la place de l'intérieur du pantalon : c'est le rang de la ceinture au pli,
	// 1 mm dessous, tourné jusqu'à 60° autour de l'axe du pli, qui est celui autour duquel le buste tourne. Le bord du haut glisse
	// ainsi dessus sans la quitter ; un manchon droit, 3 mm sous la ceinture, laissait une marche dès que le bord s'en écartait.
	// Elle ne descend pas sous le pli, où elle sortait devant les cuisses
	if(a.raccords) {
		const y = a.raccords.pli + .002;
		rangs.splice(2, 0, [corps.rayon(y) + e, y, 'bas']);
		// Chaque point du rang décrit son cercle autour de l'axe, vers le haut devant comme derrière : de rang en rang la doublure
		// monte, comme une gaine, et ses faces regardent dehors. Elle prend le ton de l'envers d'un pan, un cran plus sombre :
		// découverte quand le buste bascule en arrière, de la couleur du pantalon elle accrochait la lumière comme une plaque
		const pli = a.raccords.pli, k = elargi(a, corps.S);
		const doublure = [0, 30, 60].map(d => Array.from({ length: corps.S }, (_, j) => {
			const t = j / corps.S * Math.PI * 2, r = (corps.rayon(pli) + e - .001 + corps.galbe(t, pli)) * k, z = Math.cos(t) * r * corps.aplati(pli);
			return [Math.sin(t) * r, pli + Math.abs(z) * Math.sin(deg(d)), z * Math.cos(deg(d))];
		}));
		for(const g of Object.values(grille(THREE, doublure, corps.S, r => r === doublure.length - 1 ? null : 'bas'))) parts.push({ name: 'bassin', geometry: g, color: nuance(THREE, p.couleur, -.1), pivot: a.pivots.bassin, cote: 'bassin' });
	}
	for(const g of Object.values(gaine(THREE, a, rangs))) bassin(g, p.couleur);

	if(p.coupe === 'jupe') {
		// Deux pans, chacun rangé sur sa cuisse : ils s'écartent à la marche et suivent les genoux quand on s'assoit. Un pan est
		// une moitié de cône évasé, de la hanche au-dessus du genou
		const N = 6, haut = yHanche + .04, ourlet = a.corps.yGenou + .04;
		for(const [cote, sx] of [['jambeG', 1], ['jambeD', -1]]) {
			const j = a.jambes[cote];
			const demi = (y, r) => Array.from({ length: N }, (_, k) => {
				const t = sx * (k / (N - 1)) * Math.PI;
				return [Math.sin(t) * r, y, Math.cos(t) * r * .92];
			});
			const pan = [demi(haut, corps.rayon(haut) + e + .004), demi((haut + ourlet) / 2, corps.rayon(yHanche - .03) + .03), demi(ourlet, corps.rayon(yHanche - .03) + .055)];
			const g = grille(THREE, pan, N, (r, k) => k === N - 1 ? null : 'pan');
			pose(cote, j.hanche.toArray(), g, p.couleur);
			// L'envers du pan, qu'on voit par-dessous et par l'écart entre les deux pans à la marche
			const envers = grille(THREE, pan.map(rang => [...rang].reverse()), N, (r, k) => k === N - 1 ? null : 'pan');
			pose(cote, j.hanche.toArray(), envers, nuance(THREE, p.couleur, -.1));
		}
		return { parts, couvre: ['hanches', 'taille'] };
	}

	// Le short : une jambe ample sur chaque cuisse, l'ourlet 7 cm au-dessus du genou ; la cuisse nue se voit dessous, et le
	// genou plié reste nu
	if(p.coupe === 'short') {
		for(const [cote, j] of Object.entries(a.jambes)) {
			const pc = j.cuisse.profil;
			pose(cote, j.hanche.toArray(), manchon(THREE, j.cuisse, [[pc[0][1], 'bas'], [pc[1][1] - .08, null]], e + .008, { debut: 'pole' }), p.couleur);
		}
		return { parts, couvre: ['hanches', 'taille'] };
	}

	// La salopette, ou cotte à bretelles : le pantalon, une bavette sur la poitrine par-dessus le haut, avec sa poche, et deux
	// bretelles qui passent sur les épaules près du cou et descendent se croiser dans le dos. Elle se calcule après le haut,
	// dont elle lit l'épaisseur, et passe sous une veste
	if(p.coupe === 'salopette') {
		// La bavette passe au large de l'ourlet du haut, évasé de 1,6 cm sous la ceinture : plus près, elle s'y enfonçait
		// Sous une veste, calculée avant elle, elle reste en deçà de son écart, moins les 5 mm que perd le milieu d'une facette
		// de la veste sur ses arêtes
		const { yPoitrine, yEpaule, surCorps } = a.corps, eb = Math.min((a.ecartSousVeste ?? .008) + .018, a.veste ? a.veste.ecart - .011 : 1);
		const torse = (geometry, color) => parts.push({ name: 'torse', geometry, color, pivot: a.pivots.torse });
		const yBav = yTaille - .085, yHaut = yPoitrine + .035;
		// Une veste fermée cache la bavette et les bretelles en entier : rien ne s'en dessine, rien n'en perce. Le dos d'une veste
		// ouverte couvre celui des bretelles, que le corps à taille marquée faisait sortir entre ses facettes : elles s'arrêtent
		// sur l'épaule
		const cachee = a.veste !== undefined && a.veste.fermee;
		if(cachee === false) {
			torse(plaque(THREE, a, [[-.5, yBav], [.5, yBav], [.4, yHaut], [-.4, yHaut]], eb), p.couleur);
			torse(plaque(THREE, a, [[-.2, yPoitrine - .085], [.2, yPoitrine - .085], [.2, yPoitrine - .005], [-.2, yPoitrine - .005]], eb + .004), nuance(THREE, p.couleur, -.1));
		}
		// La ceinture fait le tour par-dessus l'ourlet du haut, qui se lit rentré : laissé dehors, il faisait sous la bavette une
		// basque, et dans le dos les bretelles s'arrêtaient sur la chemise sans rien rejoindre. Une veste longue la couvre.
		// Ouverte en bas, où la jambe du pantalon la ferme et d'où la vue plongeante des scènes ne la voit jamais. Elle passe à
		// 3 mm de l'ourlet évasé du haut et revient sur le haut au-dessus : plus large, elle faisait une collerette que les
		// bras traversaient. Elle a les côtés du haut, sommet sur sommet : avec moins de côtés, les sommets de l'ourlet
		// perçaient au milieu de ses facettes
		const yCeinture = yTaille - .035;
		if(a.veste === undefined || a.veste.longue !== true) {
			const eh = a.ecartSousVeste ?? .008, large = y => Math.max(corps.rayon(y), corps.rayon(yHanche + .06));
			const rc = large(yTaille - .065) + eh + .015;
			for(const g of Object.values(gaine(THREE, a, [[rc, yBav, 'bas'], [rc, yTaille - .055, 'bas'], [corps.rayon(yCeinture) + eh + .004, yCeinture, null]]))) bassin(g, p.couleur);
		}
		const pas = [[.36, yHaut - .005], [.62, yEpaule - .03], [1.05, yEpaule - .008], [Math.PI / 2, yEpaule - .002], [Math.PI - .95, yEpaule - .012], [Math.PI - .5, yPoitrine - .02], [Math.PI - .12, yCeinture]];
		const chemin = a.veste === undefined ? pas : pas.slice(0, 4);
		// Une bretelle est une bande plate posée sur le haut, de face à qui la regarde : un tube rond coûtait trois fois plus
		// pour un relief qu'on ne voit pas à la taille du jeu
		const { normaleCorps } = a.corps;
		if(cachee === false) for(const c of [1, -1]) {
			const P = chemin.map(([az, y], i) => new THREE.Vector3(...surCorps(c * az, y, eb + (i === 0 ? 0 : .002))));
			const N = chemin.map(([az]) => normaleCorps(c * az));
			const bords = P.map((pt, i) => {
				const t = P[Math.min(i + 1, P.length - 1)].clone().sub(P[Math.max(i - 1, 0)]).normalize(), w = t.cross(N[i]).normalize().multiplyScalar(.017);
				return [pt.clone().add(w), pt.clone().sub(w)];
			});
			const pos = [];
			for(let i = 0; i < P.length - 1; i++) {
				const [a0, b0] = bords[i], [a1, b1] = bords[i + 1];
				// Le sens des triangles suit la normale du corps, quel que soit le côté de la bretelle
				const direct = b0.clone().sub(a0).cross(a1.clone().sub(a0)).dot(N[i]) > 0;
				for(const v of direct ? [a0, b0, a1, b0, b1, a1] : [a0, a1, b0, b0, a1, b1]) pos.push(...v.toArray());
			}
			const bande = new THREE.BufferGeometry().setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
			bande.computeVertexNormals();
			torse(bande, nuance(THREE, p.couleur, -.04));
		}
	}

	// Le pantalon : une jambe par cuisse et par mollet, du profil du corps, l'ourlet sur la cheville
	for(const [cote, j] of Object.entries(a.jambes)) {
		const pc = j.cuisse.profil, pm = j.jambe.profil, rond = a.raccords !== undefined;
		// Sur l'avatar, la jambe de pantalon commence à la hanche et finit au genou, où se centrent ses bouts ronds ; elle
		// commençait 1 cm au-dessus et finissait 1 cm dessous
		const hanche = rond ? j.cuisse.A.y - j.hanche.y : pc[0][1], genou = rond ? j.cuisse.A.y - j.genou.y : pc[1][1];
		pose(cote, j.hanche.toArray(), manchon(THREE, j.cuisse, [[hanche, 'bas'], [genou, 'bas']], e, { debut: 'pole', fin: 'pole', rond, pasDebut: rond ? 3 : 2 }), p.couleur);
		const ourlet = j.jambe.A.y - .075;
		pose(j.mollet, j.genou.toArray(), manchon(THREE, j.jambe, [[pm[0][1], 'bas'], [ourlet, null]], e, { debut: 'pole', rond }), p.couleur);
	}
	return { parts, couvre: ['hanches', 'taille', 'cuisses', 'mollets'] };
}

function chaussures(THREE, a, p) {
	const parts = [];
	// Les pieds chaussés : un galet plus grand que le pied ; une chaussure de ville est plus longue et plus fine, une botte
	// monte en tige sur le mollet
	const [lx, ly, lz] = p.coupe === 'ville' ? [.074, .07, .145] : p.coupe === 'montantes' ? [.084, .09, .15] : [.08, .085, .14];
	for(const [cote, pied] of Object.entries(a.pieds)) {
		const g = new THREE.SphereGeometry(1, 6, 3, 0, Math.PI * 2, 0, Math.PI / 2).toNonIndexed();
		g.deleteAttribute('uv');
		g.scale(lx, ly, lz).translate(pied.centre[0], 0, pied.centre[2] + (p.coupe === 'ville' ? .008 : 0));
		parts.push({ name: pied.piece, geometry: g, color: p.couleur, pivot: pied.pivot });
		const j = a.jambes[cote], range = (g, color) => { for(const geo of Object.values(g)) parts.push({ name: pied.piece, geometry: geo, color, pivot: pied.pivot }); };
		if(p.coupe === 'bottes') range(manchon(THREE, j.jambe, [[j.jambe.A.y - .2, 'c'], [j.jambe.A.y - .04, null]], .012, { fin: 'bord' }), p.couleur);
		if(p.coupe === 'montantes') {
			// La tige monte de 12 cm sur la cheville, un peu plus large que la jambe, comme une chaussure de marche rembourrée
			const s0 = j.jambe.A.y - .125, s1 = j.jambe.A.y - .045;
			range(manchon(THREE, j.jambe, [[s0, 'c'], [s1, null]], .016, { fin: 'bord' }), p.couleur);
			if(p.lacets) parts.push({ name: pied.piece, geometry: lacage(THREE, j, s0, [pied.centre[0], pied.centre[2]], [lx, ly, lz]), color: p.lacets, pivot: pied.pivot });
		}
		// Une chaussette ne se voit qu'au-dessus de la chaussure, et seulement sous un bas qui laisse le mollet nu : sous un
		// pantalon, elle coûterait des triangles que rien ne montre
		if(p.chaussettes && a.jambesNues) range(manchon(THREE, j.jambe, [[j.jambe.A.y - .26, 'c'], [j.jambe.A.y - .1, null]], .005, { fin: 'bord' }), p.chaussettes);
	}
	return { parts, couvre: ['pieds'] };
}

// Le laçage d'une chaussure montante : une bande sur le devant de la tige, du haut de la tige au cou-de-pied, puis sur le
// dessus du galet jusqu'aux orteils, chaque point posé 3 mm au-dessus de la surface qu'il suit. Les lacets clairs d'une tige
// sombre, ou sombres d'une tige claire, sont ce qui fait lire une chaussure de marche plutôt qu'un sabot
function lacage(THREE, j, s0, [cx, cz], [lx, ly, lz]) {
	const a = j.jambe.axe.clone().normalize(), devant = new THREE.Vector3(0, 0, 1).addScaledVector(a, -a.z).normalize();
	const l = .016, pts = [];
	for(const s of [s0 + .005, j.jambe.A.y - .07, j.jambe.A.y - .055]) {
		const c = j.jambe.A.clone().addScaledVector(a, s), r = interpoler(j.jambe.profil, s) + .016 + .003;
		pts.push([c.clone().addScaledVector(devant, r), new THREE.Vector3(1, 0, 0)]);
	}
	for(const el of [deg(62), deg(48), deg(34)]) {
		const y = ly * Math.sin(el) + .003 * Math.sin(el), z = cz + (lz + .003) * Math.cos(el);
		pts.push([new THREE.Vector3(cx, y, z), new THREE.Vector3(1, 0, 0)]);
	}
	const pos = [];
	for(let i = 0; i < pts.length - 1; i++) {
		const [p0, u0] = pts[i], [p1, u1] = pts[i + 1], w0 = l * (i < 3 ? 1 : 1 - (i - 3) * .2), w1 = l * (i + 1 < 3 ? 1 : 1 - (i - 2) * .2);
		const a0 = p0.clone().addScaledVector(u0, -w0), b0 = p0.clone().addScaledVector(u0, w0), a1 = p1.clone().addScaledVector(u1, -w1), b1 = p1.clone().addScaledVector(u1, w1);
		pos.push(...a0.toArray(), ...b0.toArray(), ...b1.toArray(), ...a0.toArray(), ...b1.toArray(), ...a1.toArray());
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.computeVertexNormals();
	return g;
}

// La cravate : un nœud au col, un trapèze plus large en haut, posé sur la bande et d'un ton plus sombre qu'elle, pour se
// détacher d'elle sous la même lumière ; puis la bande, qui s'élargit en descendant. Sous une veste, elle passe sous
// l'ouverture : ses côtés suivent ceux du V jusqu'à sa pointe, comme une cravate qui rentre sous une veste boutonnée, sinon
// elle finit en pointe sur le ventre
function cravate(THREE, a, p) {
	const { yEpaule, yTaille } = a.corps;
	// À 3 mm du plastron, ses sommets perçaient la bande là où le bombé de la poitrine la creuse entre deux rangs
	const ecart = (a.veste ? a.veste.ecart : a.ecartHaut ?? .01) + .009, parts = [];
	const torse = (g, couleur) => parts.push({ name: 'torse', geometry: g, color: couleur, pivot: a.pivots.torse });
	const yNoeud = yEpaule - .042;
	torse(plaque(THREE, a, [[-.105, yEpaule - .002], [-.06, yNoeud], [.06, yNoeud], [.105, yEpaule - .002]], ecart + .004, .02), nuance(THREE, p.couleur, -.08));
	// La demi-largeur en azimut : 0,075 sous le nœud, 0,19 au bas de la bande, 9 cm de large sur la poitrine
	const y0 = yNoeud + .006, l0 = .075, l1 = .19;
	if(a.veste) {
		// Là où le côté de la bande croise celui du V, qui part de sa pointe : au-dessous, c'est le V qui borde la cravate
		const v = ouverture(a), pente = v.demi / (v.haut - v.bas), s = l1 / (pente + (l1 - l0) / (y0 - v.bas));
		const croise = [l1 - (l1 - l0) * s / (y0 - v.bas), v.bas + s];
		torse(plaque(THREE, a, [[0, v.bas], croise, [l0, y0], [-l0, y0], [-croise[0], croise[1]]], ecart), p.couleur);
	} else {
		const bout = yTaille + .1;
		torse(plaque(THREE, a, [[0, bout - .03], [l1, bout + .02], [l0, y0], [-l0, y0], [-l1, bout + .02]], ecart), p.couleur);
	}
	return { parts, couvre: [] };
}

const VETEMENTS = { haut, bas, chaussures, cravate };

return { disque, bande, ouverture, revers, boutons, MANCHE_COURTE, haut, bas, chaussures, cravate, VETEMENTS };
})();

// ---------- coiffure.js ----------
const module_coiffure = (() => {
// La coiffure : un fond sculpté posé sur la tête nue du corps commun, et de grosses mèches posées dessus. Une coque lisse et
// d'un seul tenant, montée en rangs horizontaux, se lisait comme un bonnet : la lisière se découpe sans motif au front et
// aux tempes, s'échancre sur les oreilles quand les cheveux sont courts et finit en pointe sur la nuque, les rangs tournent
// autour d'un épi, et les mèches partent de la raie et passent la lisière. Une queue de cheval est un fuseau courbe qui
// sort à la nuque, sous le bord de tout couvre-chef, et tombe derrière l'épaule gauche ; un chignon, une boule posée haut
// sur l'arrière du crâne.
// Réglages : couleur, reflets (couleur des mèches saillantes, ou null pour un ton à peine plus clair), meches (couleur de
// quelques mèches, ou null), gris (0 à 1 : la part de cette couleur mêlée au devant), longueur, volume, degrade (0 à 1 : les côtés et la nuque coupés près du crâne, le volume gardé
// sur le dessus), boucles, golfes, frange, raie (aucune, milieu, cote), queue (0 à 1), queueCote (dos, ou epaule : ramenée
// devant l'épaule droite), chignon (vrai ou faux), libres (0 à 1 : deux mèches qui s'échappent aux tempes et tombent le long
// des joues). Des cheveux longs couvrent les oreilles, que le personnage retire.
const { grille, redresser, rangsLe, mix, nuance, ell, aPiece, depuisBlender } = module_outils;

const deg = d => d * Math.PI / 180;
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

// Une coiffure modelée dans Blender (`piece` dans ce que l'entrée fixe) remplace la fabrique en JS là où sa pièce est
// livrée ; ailleurs la clé garde sa version en JS (SKILL.md, « Pas de mélange »). Le gris des mèches se mêle ensuite de la
// même façon sur l'une et l'autre
function coiffure(THREE, a, p) {
	// Une boule à zéro n'a pas de cheveux : elle ne rend rien et ne retire pas le crâne, qui reste nu, teint de la peau
	if(p.zero) return { parts: [], couvre: [], volumes: [], hauteur: 0 };
	const rendu = p.piece !== undefined && aPiece(p.piece) ? coiffureBlender(THREE, a, p) : coiffureJs(THREE, a, p);
	griser(THREE, a, p, rendu.parts);
	return rendu;
}

// La pièce porte sa forme entière, lisière, mèches et pointes : la fiche n'en règle plus que les teintes. Chaque sommet
// donne trois doses (`fondu` de perso_export.py) : la part du reflet du dessus qu'il prend, son fondu vers la peau à la
// lisière, et ce qu'il fonce, au creux d'un sillon ou vers une pointe. Un objet de la pièce qui porte une zone dit ce que
// la chevelure couvre en plus du crâne
function coiffureBlender(THREE, a, p) {
	const { R, C, M, hc } = a.tete, chev = p.couleur;
	const inverse = M.clone().invert(), v = new THREE.Vector3(), c = new THREE.Color();
	const base = new THREE.Color(chev), peau = new THREE.Color(a.peau), sombre = new THREE.Color(nuance(THREE, chev, -.09));
	const clair = new THREE.Color(p.reflets ? mix(THREE, chev, p.reflets, .45) : nuance(THREE, chev, .07));
	const parts = [], volumes = [], couvre = ['crane'];
	let sommet = hc + R;
	// Sous un couvre-chef, une chevelure en volume prend sa forme clé `couvreChef` : écrasée sous la coiffe et resserrée au
	// plan d'assise, que le chapeau mesure ensuite sur elle. Sans elle, il s'élargirait et se hausserait d'autant que les
	// cheveux gonflent. Une pièce qui ne porte pas la clé garde sa forme. L'avatar seul le dit (`raccords`), les scènes ne
	// montant aucune de ces pièces
	const sousCouvreChef = a.raccords?.sousCouvreChef ? 1 : 0;
	for(const m of depuisBlender(THREE, p.piece, { couvreChef: sousCouvreChef }, { couleur: chev, peau: a.peau })) {
		const g = m.geometry, pos = g.attributes.position, doses = g.attributes.fondu, couleurs = new Float32Array(pos.count * 3);
		// Un morceau teint `peau` est le crâne qu'une chevelure dégarnie laisse nu : la pièce le porte parce qu'une coiffure
		// retire la zone du crâne, et il part avec elle sous un couvre-chef
		const nu = m.teinte === 'peau';
		for(let i = 0; i < pos.count; i++) {
			if(nu) {
				peau.toArray(couleurs, i * 3);
				continue;
			}
			v.fromBufferAttribute(pos, i).applyMatrix4(inverse);
			sommet = Math.max(sommet, v.y);
			const phi = Math.atan2(v.x - C.x, v.z - C.z), th = Math.acos(clamp((v.y - hc) / v.distanceTo(C), -1, 1));
			c.copy(base).lerp(clair, doses.getX(i) * .5 * clamp(Math.cos(th) * 1.4 - .2, 0, 1) * (.6 + .4 * Math.cos(phi)));
			c.lerp(peau, doses.getY(i) * .3).lerp(sombre, doses.getZ(i));
			c.toArray(couleurs, i * 3);
		}
		g.deleteAttribute('fondu');
		g.setAttribute('color', new THREE.BufferAttribute(couleurs, 3));
		parts.push({ name: 'cheveux', geometry: g, color: chev, pivot: a.pivots.tete });
		volumes.push(g);
		if(m.zone && couvre.includes(m.zone) === false) couvre.push(m.zone);
	}
	return { parts, couvre, volumes, hauteur: sommet - hc - R };
}

function coiffureJs(THREE, a, p) {
	const { R, C, M, hc, sph, tourne } = a.tete;
	const { surCorps, yEpaule } = a.corps;
	const parts = [], couvre = ['crane'];
	// `posee` marque une mèche posée, que le personnage retire sous un couvre-chef (`rabattre()` de personnage.js)
	const add = (geometry, color, posee = false) => parts.push({ name: 'cheveux', geometry, color, pivot: a.pivots.tete, ...(posee ? { posee } : {}) });
	const { couleur: chev, longueur, volume, boucles, golfes, frange } = p;

	// L'épaisseur au sommet suit la longueur des cheveux courts, puis cesse de croître : des cheveux longs tombent, ils ne
	// montent pas
	const tTop = .016 + Math.min(longueur, .06) * .5 + volume * .03;
	// Sur les côtés, la calotte reste à plus d'un centimètre et demi de la boule : ses cordes passent sous les arêtes, et
	// plus près, les sommets du crâne la percent
	// Un dégradé rapproche les côtés du crâne jusqu'à un centimètre, la marge sous laquelle les sommets de la boule percent
	// la calotte : c'est la différence d'épaisseur entre le dessus et les côtés qui fait lire une coupe, et non un casque
	const degrade = p.degrade ?? 0;
	const tSide = Math.max(.01, (.017 + volume * .03) * (1 - .7 * degrade));

	// La lisière des cheveux, en élévation au-dessus de l'équateur de la boule, selon l'azimut (0 devant)
	// Des côtés dégradés dégagent l'oreille même quand le dessus est long : une coupe courte de 5 cm couvrait les oreilles en bol
	const oreilles = clamp(1 - longueur * 6 + degrade * .6, 0, 1);
	// Une coupe dégradée dégage aussi le front : à la même lisière qu'une coupe longue, le dessus tombait en bol jusqu'aux sourcils
	const eFront = Math.max(deg(25), deg(40) + degrade * deg(12) - frange * deg(40));
	const dega = p.raie === 'milieu' ? clamp((longueur - .08) * 12, 0, 1) * clamp(1 - frange * 4, 0, 1) : 0;
	const eBack = Math.max(deg(-64), deg(-50) - longueur * deg(150));
	const lisiere = phi => {
		const az = Math.atan2(Math.sin(phi), Math.cos(phi));
		let e = eBack + (eFront - eBack) * Math.pow((1 + Math.cos(az)) / 2, 1.8);
		e += golfes * deg(9) * Math.exp(-(((Math.abs(az) - .6) / .25) ** 2));
		e += boucles * deg(4) * Math.sin(7 * az);
		// Des cheveux qui tombent finissent en pointes inégales : une lisière droite tout autour faisait d'une coupe mi-longue un
		// casque
		// Des boucles creusent les pointes plus profond : à 7°, une chevelure bouclée tombait encore d'une seule pièce
		e -= deg(7 + 12 * boucles) * clamp((longueur - .08) * 12, 0, 1) * (1 + Math.sin(9 * az)) / 2 * (1 - Math.max(0, Math.cos(az)));
		// Une mèche au milieu du front et une à chaque tempe, une colonne sur deux : sur 12 côtés, la dent la plus fine que la
		// grille porte. Elle ne descend pas sous les sourcils
		const devant = Math.sqrt(Math.max(0, Math.cos(az)));
		if(dega > 0) {
			// Une raie au milieu sur des cheveux longs sans frange les sépare et les rejette sur les côtés : le front se dégage en
			// pointe vers la raie, au lieu de la dent du milieu, qui ramenait une mèche sur le front
			// Tout le front se dégage, et pas seulement la pointe : de trois quarts, le côté éloigné descendait au sourcil et se
			// lisait comme une mèche rabattue. La lisière reste sous 56°, où la peau du crâne s'arrête : plus haut, la pointe
			// ouvrait un jour sur l'intérieur de la tête
			e = Math.min(e + dega * (deg(9) * Math.exp(-((az / 1.1) ** 2)) + deg(9) * Math.exp(-((az / .4) ** 2))), Math.max(e, deg(56)));
		} else {
			e = Math.max(e - deg(7) * devant * (1 + Math.cos(6 * az)) / 2, Math.min(e, deg(26)));
		}
		e += (deg(20) - e) * oreilles * Math.exp(-(((Math.abs(az) - Math.PI / 2) / .3) ** 2));
		// Trois ondes de périodes étrangères l'une à l'autre découpent la lisière sans motif : des dents régulières faisaient
		// le bandeau d'un bonnet. Elles s'apaisent derrière, où leurs dents laissaient voir la peau de la nuque en dentelle. La
		// nuque d'une coupe courte finit en pointe, et la patte descend devant l'oreille dégagée
		e -= deg(4) * (.3 + .7 * Math.max(0, Math.cos(az))) * (Math.sin(5 * az + 1.3) + .7 * Math.sin(11 * az + .4) + .5 * Math.sin(17 * az + 2.1) + 1.2) / 2.4;
		const courte = clamp(1 - (longueur - .06) * 12, 0, 1);
		e -= deg(16) * courte * Math.exp(-(((Math.PI - Math.abs(az)) / .3) ** 2));
		e -= deg(14) * oreilles * Math.exp(-(((Math.abs(az) - 1.28) / .1) ** 2));
		return Math.PI / 2 - e;
	};

	// Des cheveux qui passent sous l'oreille sur toute sa largeur, des deux côtés, la couvrent, et le personnage la retire.
	// Le critère est la lisière, et non la longueur : sous une coupe mi-longue de 16 cm, l'échancrure gardait un poids de
	// 0,04 et les oreilles restaient sous les cheveux, plus saillantes qu'eux de quelques millimètres, qu'elles perçaient
	const { oreille } = a.tete;
	const oreillesCouvertes = [1, -1].every(sx => [-1, 0, 1].every(k => lisiere(sx * (Math.PI / 2 + k * oreille.demiLargeur)) > oreille.bas));
	if(oreillesCouvertes) couvre.push('oreilles');

	const SH = 20;
	// Le dessus garde son épaisseur plus bas sur le crâne quand les côtés sont dégradés : la marche se lit en silhouette
	const epaisseur = th => tSide + (tTop - tSide) * Math.min(1, Math.max(0, Math.cos(th)) * (1 + degrade));
	// Des boucles qui tombent bossellent toute la calotte, plus fort à l'arrière, en damier d'un rang à l'autre : à 2 cm sur
	// cinq lobes, elle restait un dôme lisse au-dessus des grappes, de face comme de dos
	const tombe = boucles > .4 && longueur > .1 ? 1 : 0;
	// La raie est un écart entre deux masses de cheveux, jamais un trait peint : d'un ton plus sombre, le trait se lisait de
	// près comme une fente posée sur le sommet, et sous le gris, d'un ton qu'aucun cheveu voisin ne porte. Sur des cheveux qui
	// tombent, elle se creuse dans la calotte ; sur des mèches posées, elle sépare leurs racines (plus bas)
	const phiRaie = p.raie === 'cote' ? deg(28) : 0;
	const autourRaie = phi => Math.atan2(Math.sin(phi - phiRaie), Math.cos(phi - phiRaie));
	const sillon = phi => tombe * (p.raie === 'aucune' ? 0 : 1) * .016 * Math.exp(-((autourRaie(phi) / .22) ** 2));
	// Le damier des boucles monte vers le sommet plus vite que le reste, et deux fois plus fort à l'arrière : à la même pente
	// que les lobes, le haut du dos restait un dôme lisse au-dessus des mèches
	const bosse = (th, phi) => boucles * Math.sin(th) * .02 * Math.sin(5 * phi + 3 * th) + tombe * boucles * Math.sqrt(Math.sin(th)) * (.018 + .026 * Math.max(0, -Math.cos(phi))) * Math.sin(8 * phi + 9 * th) - sillon(phi) * Math.cos(th);
	// Le volume qui dépasse du crâne sur la nuque, et les mèches en relief : une colonne sur deux plus saillante, qui tourne
	// d'un rang à l'autre depuis la lisière vers le sommet, comme une chevelure peignée en épi. Des côtes droites liraient un
	// bonnet tricoté
	const nuque = phi => (.012 + volume * .025) * (1 - degrade) * Math.max(0, -Math.cos(phi)) ** 2;
	// La torsion se répartit sur quatre rangs : à 14° par rang, la calotte tournait en turban. Le relief reste à 4 mm : à 8, une
	// colonne sur deux prenait la lumière et l'autre l'ombre, et la tête se lisait en quartiers d'éventail
	const relief = .004, torsion = deg(6);
	const saillie = j => j % 2 ? relief : -relief * .25;
	// Sur des cheveux qui ne tombent pas, et assez longs pour qu'une mèche se soulève, la coiffure est faite de grosses mèches
	// posées sur un fond : la calotte n'est plus que ce fond, plus près du crâne, et ses deux rangs du haut reviennent aux
	// mèches. Une coque d'épaisseur égale, montée en rangs horizontaux, se lisait comme un bonnet
	const posees = !tombe && longueur >= .02;
	// Des cheveux qui tombent restent plats sur le dessus et se resserrent sur la nuque sous la masse : d'une épaisseur
	// partout, une coupe mi-longue vue de dos était une boule
	const longs = clamp((longueur - .06) * 10, 0, 1);
	// Le fond ne descend pas sous un centimètre et demi, ou sous l'épaisseur entière quand elle est moindre : ses rangs
	// tournent vers l'épi et leurs cordes passent sous les arêtes du crâne, qui le perçaient en éclats de peau
	const fond = th => posees ? Math.max(Math.min(epaisseur(th), .015), epaisseur(th) * .7 * (1 - .6 * longs * Math.max(0, Math.cos(th)))) : epaisseur(th);
	const serre = phi => 1 - .7 * longs * Math.max(0, -Math.cos(phi));
	const rangs = [];
	// Le bord part sous la peau : la calotte en sort sur une ligne nette, sans laisser de lèvre au-dessus des facettes
	rangs.push(Array.from({ length: SH }, (_, j) => {
		const phi = j / SH * Math.PI * 2;
		return tourne(sph(lisiere(phi), phi, R - .012));
	}));
	rangs.push(Array.from({ length: SH }, (_, j) => {
		const phi = j / SH * Math.PI * 2, th = lisiere(phi) - deg(2);
		// Coupés près, les cheveux finissent au ras de la peau : un bord aussi épais que le dessus faisait une lèvre de bol au
		// front et autour des oreilles. Il suit l'épaisseur du rang qui le surmonte : plus épais, il faisait de dos le rebord
		// d'une chapka
		return tourne(sph(th, phi, R + (fond(th) * .85 * (1 - .6 * degrade) + nuque(phi)) * serre(phi) + .003 + saillie(j) * .5));
	}));
	// D'un rang à l'autre, la calotte saute un tiers de la hauteur des cheveux : la corde entre deux rangs passe sous l'arc, et
	// sur une coupe rase, la peau du crâne perçait au milieu. Chaque rang monte de la flèche de la corde qui le précède
	// Des boucles qui tombent prennent deux rangs de moins sur le dessus, que les boucles de l'arrière dépensent : le damier
	// facette déjà le sommet. Sous des mèches posées, le fond en prend deux de moins aussi, et son premier rang reste bas : posé
	// à mi-chemin de l'épi, la corde qui le joint au bord passait sous le crâne, qui perçait la nuque en dentelle de peau
	const etages = tombe ? [[1, .33, .33], [2, .66, .33]] : posees ? [[1, .28, .28], [2, .66, .38]] : [[1, .22, .22], [2, .44, .22], [3, .64, .2], [4, .82, .18]];
	// Les rangs convergent vers un épi en arrière du sommet, un peu de côté, là où tourne une chevelure : un pôle au sommet
	// faisait le bouton d'un bonnet. Chaque point glisse du bord vers l'épi sur le grand cercle qui les joint, et la torsion
	// des rangs fait tourner les cheveux autour de lui. Il reste à 10° du sommet : à 24°, les rangs du côté de l'épi se
	// croisaient, et leurs triangles retournés laissaient voir la peau par l'envers de la calotte
	const unite = (th, phi) => new THREE.Vector3(Math.sin(th) * Math.sin(phi), Math.cos(th), Math.sin(th) * Math.cos(phi));
	const epi = unite(deg(10), Math.PI - .5);
	const vers = (th, phi, fr) => {
		const d = unite(th, phi), angle = d.angleTo(epi), s0 = Math.sin(angle);
		const v = s0 < 1e-4 ? epi.clone() : d.multiplyScalar(Math.sin((1 - fr) * angle) / s0).add(epi.clone().multiplyScalar(Math.sin(fr * angle) / s0));
		return { th: Math.acos(clamp(v.y, -1, 1)), phi: Math.atan2(v.x, v.z), angle };
	};
	for(const [k, fr, saut] of etages) {
		rangs.push(Array.from({ length: SH }, (_, j) => {
			const phi = j / SH * Math.PI * 2, o = vers(lisiere(phi) - deg(2), phi + torsion * k, fr);
			const fleche = R * (1 - Math.cos(o.angle * saut / 2 + torsion / 2));
			return tourne(sph(o.th, o.phi, R + fond(o.th) + fleche + bosse(o.th, o.phi) + nuque(phi) * Math.max(0, .9 - k * .2) + saillie(j)));
		}));
	}
	{
		const th = Math.acos(epi.y), phi = Math.atan2(epi.x, epi.z);
		rangs.push({ pole: tourne(sph(th, phi, R + fond(th) + .004)) });
	}
	// La peau ne passe jamais à moins de 4 mm sous la calotte. Les rangs sont espacés de 20 à 50° vers l'épi, plus du côté
	// qui en est loin : une facette de la calotte y passait en corde sous la boule, et la peau la perçait en filet, ou la
	// pointe d'une oreille à demi couverte sortait de la chevelure, plus mince qu'elle. Deux contrôles, parce que l'écart le
	// plus faible entre deux surfaces de facettes tombe sur un sommet de l'une ou de l'autre : chaque point de la peau sous
	// une facette de la calotte, qui s'écarte alors tout entière, et chaque sommet de la calotte au-dessus de la peau, qui
	// s'écarte seul. L'écart se fait le long du rayon, de ce qui manque, en trois passes pour les voisins qu'une première
	// poussée découvre. La bande du bord, qui part sous la peau, n'est pas reprise : c'est la lisière, où la peau sort des
	// cheveux
	{
		const centreTete = new THREE.Vector3(...tourne([C.x, C.y, C.z])), jeu = .004;
		const peau = [a.tete.peau.tete, ...(oreillesCouvertes ? [] : [a.tete.peau.oreilles])];
		const points = peau.flatMap(p => p.points.map(q => {
			const d = q.clone().sub(centreTete);
			return { distance: d.length(), direction: d.normalize() };
		}));
		const surPeau = a.tete.cible(peau.map(p => p.geometrie)), lanceur = new THREE.Raycaster();
		const rayon = new THREE.Ray(), touche = new THREE.Vector3();
		// Chaque point ne se teste que contre les facettes qui peuvent le couvrir : contre toutes, les quelque 5 000 points de la
		// peau et les 120 facettes faisaient deux millions de tests par coiffure, pour 4 % de facettes retenues. Les points ne
		// bougent pas d'une passe à l'autre ; ils se rangent par direction en cases de 15°, et chaque case a son axe et son
		// ouverture, le plus grand angle entre l'axe et l'un de ses points. Une facette dont l'axe s'écarte de celui de la case de
		// plus que la somme des deux ouvertures ne couvre aucun de ses points (inégalité triangulaire sur la sphère) : le test sur
		// toutes l'aurait écartée au premier critère pour chacun. Les autres gardent leur ordre et passent le même test, si bien
		// que les poussées sont celles de la recherche sur toutes, à l'octet. La marge, un millionième de radian, passe de loin
		// l'arrondi des cosinus ; une ouverture qui n'est pas un nombre garde la facette
		const NT = 12, NP = 24, marge = 1e-6, angle = cosinus => Math.acos(clamp(cosinus, -1, 1));
		const cases = new Map();
		for(const point of points) {
			const { x, y, z } = point.direction;
			const cle = Math.min(NT - 1, Math.floor(angle(y) / Math.PI * NT)) * NP + Math.min(NP - 1, Math.floor((Math.atan2(x, z) + Math.PI) / (Math.PI * 2) * NP));
			if(cases.has(cle) === false) cases.set(cle, []);
			cases.get(cle).push(point);
		}
		const groupes = [...cases.values()].map(liste => {
			const axe = liste.reduce((somme, { direction }) => somme.add(direction), new THREE.Vector3()).normalize();
			return { liste, axe, ouverture: angle(Math.min(...liste.map(({ direction }) => direction.dot(axe)))) };
		});
		const sommet = (r, c) => new THREE.Vector3(...(rangs[r].pole ?? rangs[r][((c % SH) + SH) % SH]));
		for(let passe = 0; passe < 3; passe++) {
			const pousse = new Map(), pousser = (i, j, manque) => {
				if(rangs[i].pole) return;
				const cle = `${i},${((j % SH) + SH) % SH}`;
				pousse.set(cle, Math.max(pousse.get(cle) ?? 0, manque));
			};
			// Une facette ne couvre que les directions de la plus petite calotte sphérique qui contient ses sommets
			const facettes = [];
			for(let r = 1; r < rangs.length - 1; r++) for(let c = 0; c < SH; c++) {
				for(const coins of [[[r, c], [r, c + 1], [r + 1, c + 1]], [[r, c], [r + 1, c + 1], [r + 1, c]]]) {
					const sommets = coins.map(([i, j]) => sommet(i, j)), directions = sommets.map(q => q.clone().sub(centreTete).normalize());
					const axe = directions[0].clone().add(directions[1]).add(directions[2]).normalize();
					const ouverture = Math.min(...directions.map(d => d.dot(axe))) - 1e-6;
					facettes.push({ coins, sommets, axe, ouverture, demiAngle: angle(ouverture) });
				}
			}
			for(const groupe of groupes) {
				const proches = facettes.filter(f => !(angle(groupe.axe.dot(f.axe)) - groupe.ouverture > f.demiAngle + marge));
				for(const { distance, direction } of groupe.liste) {
					rayon.set(centreTete, direction);
					for(const { coins, sommets, axe, ouverture } of proches) {
						if(direction.dot(axe) < ouverture || rayon.intersectTriangle(...sommets, false, touche) === null) continue;
						const manque = distance + jeu - touche.distanceTo(centreTete);
						if(manque > 0) for(const [i, j] of coins) pousser(i, j, manque);
					}
				}
			}
			for(let r = 1; r < rangs.length; r++) if(rangs[r].pole === undefined) for(let c = 0; c < SH; c++) {
				const q = sommet(r, c), d = q.clone().sub(centreTete);
				lanceur.set(centreTete, d.clone().normalize());
				const dessous = lanceur.intersectObject(surPeau, false).at(-1);
				if(dessous && dessous.distance + jeu > d.length()) pousser(r, c, dessous.distance + jeu - d.length());
			}
			if(pousse.size === 0) break;
			for(const [cle, manque] of pousse) {
				const [i, j] = cle.split(',').map(Number), d = sommet(i, j).sub(centreTete);
				rangs[i][j] = centreTete.clone().addScaledVector(d, 1 + manque / d.length()).toArray();
			}
		}
	}
	// Une seule teinte de bande, posée ensuite sommet par sommet : une bande de reflet à mi-hauteur et un rang du bas d'une
	// autre teinte avaient des bords horizontaux, les côtes et l'ourlet d'un bonnet
	const coupe = grille(THREE, rangs, SH, () => 'c');
	// Des boucles qui tombent gardent les facettes de la calotte, comme celles des grappes : sous des normales lissées, les
	// bosses du damier ne prenaient pas la lumière, et le dessus restait un dôme d'une seule teinte
	if(tombe) coupe.c.computeVertexNormals();
	add(coupe.c, chev);
	{
		const inverse = M.clone().invert(), v = new THREE.Vector3(), c = new THREE.Color();
		// Sous les mèches posées, le fond est d'un ton plus sombre : c'est l'ombre d'une mèche sur ce qu'elle recouvre
		const base = new THREE.Color(posees ? nuance(THREE, chev, -.03) : chev), peau = new THREE.Color(a.peau), sombre = new THREE.Color(nuance(THREE, chev, -.07));
		const clair = new THREE.Color(p.reflets ? mix(THREE, chev, p.reflets, .45) : nuance(THREE, chev, .07));
		// Des cheveux qui tombent foncent vers leurs pointes ; coupés près, ils laissent voir la peau sur le dernier centimètre,
		// devant comme derrière : c'est ce fondu qui remplace l'ourlet
		const peauBord = longueur > .1 ? .15 : .45 + .3 * degrade;
		// Le fondu tient aux deux rangs du bord, reconnus à leurs sommets : mesuré à la lisière sous chaque sommet, il
		// peignait de peau les rangs du haut qui tournent au-dessus de la patte ou de la pointe de nuque, en éclats clairs
		const cle = (x, y, z) => [x, y, z].map(Math.fround).join(',');
		const sousPeau = new Set(rangs[0].map(q => cle(...q))), auBord = new Set(rangs[1].map(q => cle(...q)));
		const pos = coupe.c.attributes.position, couleurs = new Float32Array(pos.count * 3);
		for(let i = 0; i < pos.count; i++) {
			const k = cle(pos.getX(i), pos.getY(i), pos.getZ(i));
			v.fromBufferAttribute(pos, i).applyMatrix4(inverse);
			const phi = Math.atan2(v.x - C.x, v.z - C.z), th = Math.acos(clamp((v.y - hc) / v.distanceTo(C), -1, 1));
			// Le fondu est plus léger au front, où il salissait la peau, que sur la nuque et les côtés
			const bord = (sousPeau.has(k) ? .5 : auBord.has(k) ? .2 : 0) * (.5 + .5 * (1 - Math.cos(phi)) / 2);
			const pointes = longueur > .1 && Math.cos(phi) < .3;
			c.copy(base).lerp(clair, .5 * clamp(Math.cos(th) * 1.4 - .2, 0, 1) * (.6 + .4 * Math.cos(phi)));
			if(pointes) c.lerp(sombre, bord);
			else c.lerp(peau, bord * peauBord);
			c.toArray(couleurs, i * 3);
		}
		coupe.c.setAttribute('color', new THREE.BufferAttribute(couleurs, 3));
	}
	const calotte = coupe.c;
	const volumes = [calotte];

	// Les mèches posées : huit fuseaux larges à la racine et pointus au bout, qui partent de la raie, ou d'un épi en arrière
	// du sommet sans raie, et rayonnent vers la lisière en se recouvrant d'un tiers. Devant, leur bout passe la lisière et se
	// soulève, en frange ou en houppe : c'est ce bout qui casse l'ourlet, et l'ombre d'une mèche sur sa voisine qui dit des
	// cheveux plutôt qu'une étoffe. Des mèches fines se lisaient comme des éclats plantés dans la calotte. Chaque fuseau est
	// un toit de deux pans, dont les bords plongent dans le fond de ce que la tête s'arrondit sous sa largeur : posés à plat,
	// ils sortaient de la silhouette en ailettes, et de dos se lisaient comme des épis détachés. Le toit n'a pas de dessous :
	// tourné vers le crâne et noyé dans le fond, il ne se voit d'aucun côté, et il tirait vers lui la normale des bords
	if(posees) {
		const centreTete = new THREE.Vector3(...tourne([C.x, C.y, C.z]));
		// Une mèche prend la teinte de la calotte qu'elle couvre, décalée de sa propre nuance : peinte de son seul ton, elle
		// se détachait en plaque sombre partout où la calotte s'éclaircit, vers le reflet du dessus et surtout dans le fondu de
		// peau de la lisière, où le bout de chaque mèche vient se poser. Le bout passé la lisière, devant, prend la teinte de
		// la lisière au-dessus de lui : gardé à son ton, il faisait un éclat d'une autre couleur au bord de la frange
		const surCalotte = new THREE.Mesh(calotte, new THREE.MeshBasicMaterial({ side: THREE.DoubleSide })), rayonCalotte = new THREE.Raycaster();
		const fondCalotte = new THREE.Color(nuance(THREE, chev, -.03));
		const inverseTete = M.clone().invert();
		const teinteSous = (g, ton) => {
			const pos = g.attributes.position, couleurs = new Float32Array(pos.count * 3), teintes = calotte.attributes.color;
			const v = new THREE.Vector3(), direction = new THREE.Vector3(), c = new THREE.Color(), decalage = new THREE.Color(ton).sub(fondCalotte);
			const [ca, cb, cc] = [0, 0, 0].map(() => new THREE.Color()), [pa, pb, pc] = [0, 0, 0].map(() => new THREE.Vector3()), poids = new THREE.Vector3();
			for(let i = 0; i < pos.count; i++) {
				v.fromBufferAttribute(pos, i);
				const local = v.clone().applyMatrix4(inverseTete);
				const phi = Math.atan2(local.x - C.x, local.z - C.z), th = Math.acos(clamp((local.y - hc) / local.distanceTo(C), -1, 1));
				const vise = th > lisiere(phi) - deg(3) ? new THREE.Vector3(...tourne(sph(lisiere(phi) - deg(3), phi, R))) : v;
				rayonCalotte.set(centreTete, direction.copy(vise).sub(centreTete).normalize());
				const touche = rayonCalotte.intersectObject(surCalotte).at(-1);
				if(touche) {
					const { a, b, c: s3 } = touche.face;
					THREE.Triangle.getBarycoord(touche.point, pa.fromBufferAttribute(calotte.attributes.position, a), pb.fromBufferAttribute(calotte.attributes.position, b), pc.fromBufferAttribute(calotte.attributes.position, s3), poids);
					c.setRGB(0, 0, 0).add(ca.fromBufferAttribute(teintes, a).multiplyScalar(poids.x)).add(cb.fromBufferAttribute(teintes, b).multiplyScalar(poids.y)).add(cc.fromBufferAttribute(teintes, s3).multiplyScalar(poids.z)).add(decalage);
				}
				else c.set(ton);
				c.toArray(couleurs, i * 3);
			}
			return new THREE.BufferAttribute(couleurs, 3);
		};
		const fuseau = (courbe, profil) => {
			const rangsF = profil.map(([w, h, t]) => {
				const c = courbe.getPoint(t);
				if(w === 0) return { pole: [c.x, c.y, c.z] };
				const n = c.clone().sub(centreTete).normalize(), tangente = courbe.getTangent(t).normalize();
				const e1 = tangente.clone().cross(n).normalize(), e2 = e1.clone().cross(tangente).normalize();
				// Les bords descendent sous le fond, plus bas que ce que la tête s'arrondit sous la largeur : la mèche, bombée
				// au-dessus du fond, n'a pas de dessous, et des bords qui l'affleuraient laissaient voir le jour entre elle et la
				// calotte, en silhouette
				const plonge = w * w / (2 * R) + w * .22;
				// Le bord de gauche d'abord : dans cet ordre, la grille tourne les faces vers le dehors. Dans l'ordre inverse, une
				// scène qui ne dessine que les faces avant montre l'intérieur de la mèche et efface son dehors
				return [[-w, -plonge], [0, h], [w, -plonge]].map(([u, hauteur]) => c.clone().addScaledVector(e1, u).addScaledVector(e2, hauteur).toArray());
			});
			const g = grille(THREE, rangsF, 3, (r, colonne) => colonne === 2 ? null : 'r').r;
			// Chaque pan d'une mèche est large de plusieurs centimètres et la rampe toon pose la lumière par paliers : sous sa
			// seule normale, un pan entier prenait un palier, borné de droites, et se lisait comme un pan plat taillé dans la
			// chevelure. La normale de chaque sommet penche à moitié vers celle de la calotte sous lui : la mèche garde son relief,
			// et ses deux pans passent d'un palier à l'autre comme la calotte
			const pos = g.attributes.position, normales = g.attributes.normal, rayon = new THREE.Vector3(), normale = new THREE.Vector3();
			for(let i = 0; i < pos.count; i++) {
				rayon.fromBufferAttribute(pos, i).sub(centreTete).normalize();
				normale.fromBufferAttribute(normales, i).normalize().lerp(rayon, .5).normalize();
				normales.setXYZ(i, normale.x, normale.y, normale.z);
			}
			// Mince et repliée en pointe, une mèche a des normales arrondies qui, au bout, regardent à l'opposé de leur face :
			// vue de dos, la pointe prenait la lumière de devant et s'allumait en éclat clair dans la chevelure
			return redresser(THREE, g);
		};
		const origine = p.raie === 'cote' ? [deg(30), phiRaie] : p.raie === 'milieu' ? [deg(24), 0] : [deg(26), Math.PI - .4];
		// L'azimut du bout de chaque mèche et sa largeur
		const bouts = p.raie === 'aucune'
			? [[0, .075], [.6, .07], [-.6, .07], [1.3, .068], [-1.3, .068], [2.1, .066], [-2.1, .066], [Math.PI, .07]]
			: [[.45, .075], [-.25, .072], [1.1, .07], [-1.0, .07], [1.8, .068], [-1.85, .068], [2.6, .066], [-2.6, .066]];
		// L'écart de la raie : chaque mèche part d'un côté de la raie, à un centimètre et demi d'elle, et son bout s'en tient
		// assez loin pour que son bord ne la recouvre pas. Entre les deux masses, le fond, d'un ton plus sombre, reste à nu de
		// l'origine à la lisière. Parties toutes du même point, les mèches recouvraient la raie, qu'il fallait alors peindre
		const ecartRaie = .015;
		bouts.forEach(([az, w], i) => {
			const [thO, phRaie] = origine;
			const cote = p.raie === 'aucune' ? 0 : Math.sign(autourRaie(az)) || 1;
			// Une mèche ondule d'un côté puis de l'autre, une sur deux ; de part et d'autre d'une raie, elle ondule en s'en écartant
			const sens = cote !== 0 ? cote : i % 2 ? -1 : 1;
			const marge = (w * .8 + ecartRaie) / (R * Math.sin(lisiere(phiRaie)));
			const azB = cote !== 0 && Math.abs(autourRaie(az)) < marge ? phiRaie + cote * marge : az;
			const phO = phRaie + cote * ecartRaie / (R * Math.sin(thO));
			// La part du devant que prend le bout, de rien à 72° du milieu du front jusqu'à tout en son milieu. Devant, le bout passe
			// la lisière et se relève, en frange ou en houppe ; ailleurs il s'arrête au-dessus d'elle, sans quoi il pendait dans le
			// vide, sur la nuque resserrée ou derrière l'oreille dégagée. Un bout relevé se décolle de la tête le long du rayon, qui
			// à la tempe pointe de côté : à plein relèvement dès qu'il passait au devant, le bout de la tempe sortait du contour du
			// visage vu de face, sous le revers d'un bonnet comme sur une coupe nue
			const front = clamp((Math.cos(azB) - .3) / .7, 0, 1);
			// Le bout ne descend pas sous 22° au-dessus de l'équateur, le haut d'une monture : plus bas, une mèche traversait les
			// lunettes
			const thB = Math.min(lisiere(azB) + deg(3 + 8 * longs) * front - deg(5 - 3 * longs) * (1 - front), Math.max(lisiere(azB), Math.PI / 2 - deg(22)));
			const dphi = Math.atan2(Math.sin(azB - phO), Math.cos(azB - phO));
			const pts = [0, .35, .7, 1].map(f => {
				const th = thO + (thB - thO) * f;
				const phi = phO + dphi * Math.pow(f, .7) + sens * .12 * Math.sin(f * Math.PI);
				// Le corps de la mèche bombe au-dessus du fond et redescend s'y poser au bout ; devant, le bout se relève
				const leve = .01 * Math.sin(f * Math.PI * .9) + (f === 1 ? (.012 * (1 - longs) + .004) * front : 0);
				return new THREE.Vector3(...tourne(sph(th, phi, R + fond(th) + w * .2 * (1 - .8 * f * f) + leve)));
			});
			// Cinq rangs le long de la mèche, que paient les triangles du dessous : sur quatre, son contour était un losange de
			// quatre droites, un éclat plutôt qu'un fuseau
			const g = fuseau(new THREE.CatmullRomCurve3(pts), [[0, 0, 0], [w * .92, w * (.4 - .17 * longs), .18], [w, w * (.42 - .18 * longs), .42], [w * .66, w * (.32 - .11 * longs), .7], [0, 0, 1]]);
			// Une sur trois plus sombre, aucune plus claire, comme les mèches qui tombent : posée sur la teinte de la calotte, déjà
			// éclaircie par le reflet du dessus, une mèche plus claire ressortait en pan clair au bord de la frange, et de dessus
			// en mèches plus claires que toute la calotte
			const ton = i % 3 === 2 ? nuance(THREE, chev, -.03) : chev;
			g.setAttribute('color', teinteSous(g, ton));
			add(g, ton, true);
			volumes.push(g);
		});
	}

	// La queue de cheval : sous l'équateur de la boule, donc sous le bord de tout couvre-chef, elle passe un lien couleur de
	// raie. Dans le dos, elle tombe derrière l'épaule gauche ; ramenée sur l'épaule, elle passe par-dessus l'épaule droite et
	// se pose sur le devant du haut, où on la voit de face, au lieu de se perdre sur la pente de l'épaule
	if(p.queue > .05) {
		const ecart = (a.ecartHaut ?? 0) + .02;
		const devant = p.queueCote === 'epaule';
		const thRacine = Math.PI / 2 + deg(6);
		const cote = devant ? -1 : 1;
		const racine = new THREE.Vector3(...tourne(sph(thRacine, cote * (Math.PI - .35), R + epaisseur(thRacine) - .03)));
		const lien = new THREE.Vector3(...tourne(sph(Math.PI / 2 + deg(16), cote * (Math.PI - .55), R + .045)));
		const points = [racine, lien];
		if(devant) {
			points.push(new THREE.Vector3(...surCorps(-1.85, yEpaule + .02, ecart + .07)));
			points.push(new THREE.Vector3(...surCorps(-1.2, yEpaule - .05, ecart + .045)));
			points.push(new THREE.Vector3(...surCorps(-.8, yEpaule - .1 - p.queue * .12, ecart + .03)));
		} else {
			const pointe = new THREE.Vector3(...surCorps(Math.PI - .55, yEpaule - .05 - p.queue * .14, .04));
			points.push(lien.clone().lerp(pointe, .45).add(new THREE.Vector3(0, .01, -.045)), pointe);
		}
		const courbe = new THREE.CatmullRomCurve3(points);
		const rayons = [0, .036, .05, .056, .054, .048, .036, 0];
		add(grille(THREE, rangsLe(THREE, courbe, rayons.map((r, i) => [r, i / (rayons.length - 1)]), 8), 8, () => 'q').q, chev);
		const tLien = devant ? .1 : .14;
		add(grille(THREE, rangsLe(THREE, courbe, [[.044, tLien], [.044, tLien + .05]], 8), 8, () => 'l').l, mix(THREE, chev, a.peau, .45));
	}

	// Les mèches libres : deux fuseaux qui partent sous la lisière, aux tempes, et tombent le long de la joue jusqu'à la
	// mâchoire, ou jusqu'au cou quand elles sont longues. Elles ondulent si les cheveux bouclent. Elles frôlent la joue à un ou
	// deux centimètres : à quatre, elles se décollaient du visage en ailettes
	if((p.libres ?? 0) > .05) {
		for(const sx of [1, -1]) {
			const phi = sx * 1.2;
			const pts = [
				tourne(sph(lisiere(phi) - deg(4), phi, R + epaisseur(lisiere(phi)) * .5)),
				tourne(sph(Math.PI / 2 + deg(8), sx * 1.32, R + .02)),
				tourne(sph(Math.PI / 2 + deg(32) + boucles * deg(4), sx * (1.38 - boucles * .12), R + .022)),
				tourne(sph(Math.PI / 2 + deg(52) + p.libres * deg(18), sx * 1.45, R + .016 + boucles * .01)),
			].map(v => new THREE.Vector3(...v));
			const courbe = new THREE.CatmullRomCurve3(pts);
			add(grille(THREE, rangsLe(THREE, courbe, [[0, 0], [.018, .12], [.024, .4], [.018, .75], [0, 1]], 5), 5, () => 'l').l, p.reflets ? mix(THREE, chev, p.reflets, .5) : nuance(THREE, chev, .04));
		}
	}

	// Les boucles en volume, sur des cheveux qui tombent : sept mèches ondulées, qui partent de la calotte, plus haut dans le
	// dos pour que le relief y monte, et descendent en S jusqu'aux épaules, trois de chaque côté et une dans le dos. Posées en grappes ou en petites
	// boucles sur une calotte, des formes closes se lisaient comme des boules ou des pointes plantées sur un casque : c'est
	// l'ondulation d'une mèche continue qui dit une chevelure bouclée. Chaque mèche est un tube à cinq pans, gonflé au
	// milieu, refermé en pointe ; deux voisines ondulent à contretemps
	if(tombe) {
		const n = clamp((boucles - .4) / .4, 0, 1);
		const inverse = M.clone().invert();
		// Le rayon de la calotte à une hauteur donnée : une mèche posée plus près du centre passait dessous et ne montrait que sa
		// pointe, si bien que la chevelure se lisait raide et lisse
		const surCalotte = y => {
			const dy = clamp((y - hc) / R, -1, 1);
			return R * Math.sqrt(1 - dy * dy) + epaisseur(Math.acos(dy)) + .02;
		};
		// Chaque mèche finit posée sur le haut, à l'azimut et à la hauteur de sa ligne : arrêtées au-dessus des épaules, elles
		// faisaient un carré à la mâchoire, quand la photo les montre sur la doudoune. Celles des côtés partent derrière l'oreille
		// et finissent sur l'arrière de l'épaule : le bras qui se lève jusqu'à la verticale, une caisse au-dessus de la tête,
		// monte devant l'oreille et passait dans les mèches posées sur le dessus. Celle du dos finit sur la capuche. Les mèches
		// suivent la tête, qui tombe en avant accablée et se relève accroupie : posées plus bas ou plus près, elles entraient
		// dans le bras ou dans la capuche, et devant l'épaule, dans la poitrine. Chaque ligne donne l'azimut et la hauteur du
		// départ sur la calotte, l'épaisseur, puis l'azimut, la hauteur sous l'épaule et l'écart au haut (en épaisseurs) du bout
		const ecart = (a.ecartHaut ?? 0) + .02;
		const meches = [
			[2.3, deg(92), .05, 2.2, 0, .3],
			[2.5, deg(80), .056, 2.5, -.03, .3],
			[2.85, deg(66), .06, 2.7, -.03, .3],
			[Math.PI, deg(52), .062, Math.PI, -.09, .7],
		];


		let k = 0;
		for(const [az, th0, rayon, azPose, dy, leve] of meches) {
			for(const sx of az === Math.PI ? [1] : [1, -1]) {
				const phi = sx * az, onde = (k % 2 ? 1 : -1) * (.014 + .018 * n);
				const sous = th0 > Math.PI / 2, depart = sph(th0, phi, R + epaisseur(th0) + rayon * (sous ? -.5 : .2));
				const pose = new THREE.Vector3(...surCorps(sx * azPose, yEpaule + dy, ecart + rayon * leve)).applyMatrix4(inverse);
				// Chaque mèche ressort de la calotte de la moitié de son épaisseur et ondule aussi en profondeur : vue de face ou de
				// dos, c'est le relief qui va et vient sur le contour qui dit la boucle. Sur les côtés,
				// où elle fait le contour vue de face et de trois quarts, elle ne s'écarte pas plus que son départ, et celle qui part
				// sous le milieu de la calotte y entre de la moitié de son épaisseur : rien ne les tient en dessous, et elles flottaient
				// derrière l'oreille comme des anses. Dans le dos, l'écart fait le relief
				const rDepart = Math.hypot(depart[0] - C.x, depart[2] - C.z);
				const points = [0, .33, .66].map((f, i) => {
					const y = depart[1] + (pose.y - depart[1]) * f;
					const decale = f === 0 ? 0 : onde * (i % 2 ? 1 : -1);
					const r = f === 0 ? rDepart : Math.min(Math.max(surCalotte(y), .21) + rayon * (i % 2 ? .55 : .25), az < 2.7 ? rDepart : Infinity);
					const angle = phi + decale / Math.max(r, .1);
					return new THREE.Vector3(C.x + Math.sin(angle) * r, y, C.z + Math.cos(angle) * r);
				});
				points.push(pose);
				const courbe = new THREE.CatmullRomCurve3(points);
				const g = grille(THREE, rangsLe(THREE, courbe, [[rayon * .8, 0], [rayon, .3], [rayon * .95, .62], [rayon * .7, .88], [0, 1]], 5), 5, () => 'm').m;
				g.applyMatrix4(M);
				// Une sur trois plus sombre, aucune plus claire : celles qui prenaient le reflet se lisaient dans le dos comme des
				// planches collées sur la masse
				add(g, k % 3 === 1 ? nuance(THREE, chev, -.07) : chev);
				volumes.push(g);
				k++;
			}
		}
	}

	// Le chignon : une boule un peu aplatie, haut sur l'arrière du crâne, qui dépasse en silhouette de profil et de dos
	if(p.chignon) {
		const th = deg(62), [x, y, z] = sph(th, Math.PI, R + epaisseur(th) + .03);
		const g = ell(THREE, x, y, z, .085, .075, .07, 6, 4).applyMatrix4(M);
		g.deleteAttribute('uv');
		add(g, nuance(THREE, chev, .03));
		volumes.push(g);
	}

	// Ce que la coiffure ajoute au-dessus du crâne, pour la hauteur d'un couvre-chef et de la personne
	return { parts, couvre, volumes, hauteur: tTop + boucles * .02 };
}

// Le gris des mèches de couleur se mêle sommet par sommet, sur toutes les pièces de la chevelure : peint par facettes, il
// faisait des plaques ou des lames, et au sommet, où les colonnes se rejoignent, une corne. Une colonne de la calotte sur
// deux ou trois, à intervalles irréguliers pour ne pas rayer, est grise du front vers l'arrière, les autres gardent le ton
// franc : le gris passe de l'une à l'autre en dégradé dans chaque facette, et se lit en mèches mêlées. Une part tirée
// sommet par sommet donnait une calotte grise unie, un casque. La part vient de la fiche (`gris`), sur le devant de la
// tête et pas sur les longueurs qui tombent plus bas ; elle ne dépend que de la place sur la tête, si bien que les pièces
// voisines s'accordent sur leurs coins communs. La couleur de la pièce reste son ton de base, pour qui la peint d'une
// seule teinte
function griser(THREE, a, p, parts) {
	if(p.meches === null || (p.gris ?? 0) <= 0) return;
	const { C, M, hc, R } = a.tete, SH = 20;
	const inverse = M.clone().invert(), gris = new THREE.Color(p.meches), v = new THREE.Vector3(), c = new THREE.Color();
	const grise = k => 'g.g..g.gg..g.g..gg.g'[((k % SH) + SH) % SH] === 'g';
	for(const part of parts) {
		const base = new THREE.Color(part.color), pos = part.geometry.attributes.position, couleurs = new Float32Array(pos.count * 3);
		const avant = part.geometry.attributes.color;
		for(let i = 0; i < pos.count; i++) {
			if(avant) base.fromBufferAttribute(avant, i);
			v.fromBufferAttribute(pos, i).applyMatrix4(inverse);
			const phi = Math.atan2(v.x - C.x, v.z - C.z), th = Math.acos(clamp((v.y - hc) / R, -1, 1));
			const devant = clamp((Math.cos(phi) + .2) / 1.2, 0, 1) ** 1.5 * clamp((2 - th) / .4, 0, 1);
			const dose = grise(Math.round(phi * SH / (Math.PI * 2))) ? p.gris * devant : 0;
			c.copy(base).lerp(gris, dose).toArray(couleurs, i * 3);
		}
		part.geometry.setAttribute('color', new THREE.BufferAttribute(couleurs, 3));
	}
}

return { coiffure };
})();

// ---------- visage.js ----------
const module_visage = (() => {
// Le visage : des marques peintes sur la peau de la tête commune, qui ne la déforment jamais. La
// forme de la tête, le menton, les joues et la place des traits restent les mêmes pour tout le monde ; une personne n'y ajoute
// que de la couleur et des traits fins, posés à un ou deux millimètres des facettes.
// Le visage de base est celui du corps commun : deux points d'encre à éclat crème, deux sourcils, un petit sourire. La couche
// visage de la fiche le modifie élément par élément :
//   sourcils (couleur, epaisseur), yeux (couleur de l'iris), trait-yeux (couleur), rouge-levres (couleur), joues-rosees
//   (couleur, intensite), taches-rousseur (couleur, densite), grain-de-beaute (place, couleur), pattes-oie (marque),
//   sillons (marque), fossettes (marque).
// Chaque point se donne dans le plan du visage (x à gauche du personnage, y vers le haut, en mètres sur la boule) et se pose
// sur la peau par l'ancre `yeux.poser`. Aucune branche par prénom.
const { geometrie, mix, nuance, ENCRE, CREME } = module_outils;
const { EMOTIONS } = module_emotions;

// Les taches de rousseur, toujours aux mêmes places, sur l'arête du nez et le haut des joues ; la densité dit combien
const ROUSSEUR = [[.03, -.012], [-.03, -.012], [.07, -.02], [-.07, -.02], [.1, -.035], [-.1, -.035], [.05, -.04], [-.05, -.04],
	[.13, -.022], [-.13, -.022], [.085, -.05], [-.085, -.05], [.015, -.03], [-.015, -.03], [.115, -.055], [-.115, -.055]];
const GRAINS = { 'joue-gauche': [.13, -.06], 'joue-droite': [-.13, -.06], 'menton': [.035, -.13], 'levre': [.06, -.062], 'tempe': [.19, .04] };

function visage(THREE, a, marques) {
	const poser = a.yeux.poser, peau = a.peau;
	const parts = [];
	const add = (geometry, color, role = null) => parts.push({ name: 'visage', geometry, color, pivot: a.pivots.tete, ...(role ? { role } : {}) });
	const pousse = (o, pos, nor) => { pos.push(o.p.x, o.p.y, o.p.z); nor.push(o.n.x, o.n.y, o.n.z); };
	const disque = (cx, cy, rx, ry, lift, seg = 10) => {
		const pos = [], nor = [], c = poser(cx, cy, lift);
		for(let i = 0; i < seg; i++) {
			const t0 = i / seg * Math.PI * 2, t1 = (i + 1) / seg * Math.PI * 2;
			pousse(c, pos, nor);
			pousse(poser(cx + Math.cos(t0) * rx, cy + Math.sin(t0) * ry, lift), pos, nor);
			pousse(poser(cx + Math.cos(t1) * rx, cy + Math.sin(t1) * ry, lift), pos, nor);
		}
		return geometrie(THREE, pos, nor);
	};
	// Un trait d'une largeur donnée le long d'une suite de points du plan du visage
	const trait = (points, largeur, lift) => {
		const pos = [], nor = [];
		const bords = points.map(([x, y], i) => {
			const [xa, ya] = points[Math.max(0, i - 1)], [xb, yb] = points[Math.min(points.length - 1, i + 1)];
			const tx = xb - xa, ty = yb - ya, l = Math.hypot(tx, ty) || 1;
			const nx = ty / l * largeur / 2, ny = -tx / l * largeur / 2;
			return [poser(x + nx, y + ny, lift), poser(x - nx, y - ny, lift)];
		});
		for(let i = 0; i < bords.length - 1; i++) {
			const [b0, h0] = bords[i], [b1, h1] = bords[i + 1];
			for(const o of [b0, b1, h1, b0, h1, h0]) pousse(o, pos, nor);
		}
		return geometrie(THREE, pos, nor);
	};
	const m = marques;
	const { x: xOeil, y: yOeil, r: rOeil } = a.yeux, yBouche = a.bouche.y;
	// Une marque fine se fonce sur la peau d'autant que la fiche la dit marquée
	const ride = k => nuance(THREE, peau, -.08 - .1 * k);

	// Les joues et les taches se posent les premières, au plus près de la peau : les traits passent par-dessus
	if(m['joues-rosees']) {
		const c = mix(THREE, peau, m['joues-rosees'].couleur, m['joues-rosees'].intensite);
		for(const sx of [1, -1]) add(disque(sx * .158, -.04, .05, .034, .0018, 8), c);
	}
	if(m['taches-rousseur']) {
		const n = Math.round(6 + m['taches-rousseur'].densite * 10);
		for(const [x, y] of ROUSSEUR.slice(0, n)) add(disque(x, y, .0055, .0055, .0024, 4), m['taches-rousseur'].couleur);
	}
	if(m['grain-de-beaute']) {
		const [x, y] = GRAINS[m['grain-de-beaute'].place];
		add(disque(x, y, .0075, .0075, .0026, 5), m['grain-de-beaute'].couleur);
	}

	// Une surface pleine bordée par une suite de points du plan du visage, triangulée en éventail depuis le premier : elle a
	// deux triangles de moins que de points
	const plein = (points, lift) => {
		const pos = [], nor = [], bord = points.map(([x, y]) => poser(x, y, lift));
		for(let i = 1; i < bord.length - 1; i++) for(const o of [bord[0], bord[i], bord[i + 1]]) pousse(o, pos, nor);
		return geometrie(THREE, pos, nor);
	};
	const vide = () => geometrie(THREE, [], []);

	// Les traits qui changent avec l'émotion (yeux, sourcils, bouche) se dessinent par une variante. Chacun porte un rôle, pour
	// qu'une émotion remplace sa géométrie le temps où la scène la montre. Une variante n'a jamais plus de triangles que le
	// trait du visage de base qu'elle remplace : la personne la plus chargée du catalogue est à 18 triangles du plafond, et
	// une émotion ne doit pas l'en faire sortir
	// Les yeux : un point d'encre (8 triangles), l'iris de la fiche à l'intérieur (8), un éclat crème (5). `rieurs` ferme
	// l'œil en arc retourné, `ronds` l'agrandit, `lourds` coupe le haut par une paupière à plat, `plisses` le resserre,
// `fuyants` détourne le regard.
	// `releve` décolle l'œil de la peau : un disque plan creuse sous la tête bombée d'autant plus qu'il est large, et celui des
	// yeux ronds, posé à la hauteur des autres, laissait la peau percer l'encre au-dessus de l'iris, à côté de l'éclat
	const YEUX = {
		ouverts: { r: 1, h: 1 },
		ronds: { r: 1.28, h: 1.12, releve: .003 },
		plisses: { r: 1.05, h: .5 },
		lourds: { paupiere: .1 },
		rieurs: { arc: true },
		// Le regard qui file de côté et vers le haut, sous les sourcils relevés de l'inquiétude : l'iris et l'éclat glissent dans
		// l'œil, d'autant de rayons d'œil (`regard`), et l'œil se resserre un peu en largeur
		fuyants: { r: .92, h: 1.06, regard: [-.3, .22] },
	};
	const oeil = (sx, nom) => {
		const v = YEUX[nom], x = sx * xOeil;
		if(v.arc) {
			// Un arc d'encre bombé vers le haut, l'œil fermé de qui rit : 5 points, 8 triangles
			const arc = [0, .25, .5, .75, 1].map(t => [x + (t - .5) * rOeil * 2.3, yOeil - .012 + rOeil * .95 * Math.sin(t * Math.PI)]);
			return { oeil: trait(arc, .017, .0032), iris: vide(), eclat: vide() };
		}
		if(v.paupiere !== undefined) {
			// La paupière tombe jusqu'au-dessus du centre : un demi-disque d'encre, le bord du haut à plat, sans éclat
			const haut = yOeil + rOeil * v.paupiere, demi = [0, .2, .4, .6, .8, 1].map(t => [x + Math.cos(Math.PI + t * Math.PI) * rOeil * 1.05, haut + Math.sin(Math.PI + t * Math.PI) * rOeil * 1.1]);
			return { oeil: plein([[x, haut], ...demi], .0032), iris: m.yeux ? plein([[x, haut - .004], ...demi.map(([px, py]) => [x + (px - x) * .62, haut - .004 + (py - haut) * .62])], .0042) : vide(), eclat: vide() };
		}
		const releve = v.releve ?? 0, [rx, ry] = (v.regard ?? [0, 0]).map(t => t * rOeil);
		return {
			oeil: disque(x, yOeil, rOeil * v.r, rOeil * 1.08 * v.h, .0032 + releve, 8),
			iris: m.yeux ? disque(x + rx, yOeil - .003 * v.h + ry, rOeil * .66 * v.r, rOeil * .7 * v.h, .0042 + releve, 8) : vide(),
			eclat: disque(x - .012 * v.r + rx, yOeil + .015 * v.h + ry, .012 * Math.min(1, v.h * 1.2), .012 * Math.min(1, v.h * 1.2), .0052 + releve, 5),
		};
	};

	// Les yeux de base : un point d'encre, l'iris de la fiche à l'intérieur quand elle le donne, un éclat crème
	for(const sx of [1, -1]) {
		const cote = sx === 1 ? 'G' : 'D', o = oeil(sx, 'ouverts');
		add(o.oeil, ENCRE, 'oeil' + cote);
		if(m.yeux) add(o.iris, m.yeux.couleur, 'iris' + cote);
		add(o.eclat, CREME, 'eclat' + cote);
	}
	if(m['trait-yeux']) {
		for(const sx of [1, -1]) {
			const pts = [[sx * (xOeil - .034), yOeil + .036], [sx * xOeil, yOeil + .047], [sx * (xOeil + .036), yOeil + .04], [sx * (xOeil + .058), yOeil + .056]];
			add(trait(pts, .009, .0045), m['trait-yeux'].couleur);
		}
	}

	// Les sourcils : la couleur et l'épaisseur de la fiche, sinon un brun moyen d'épaisseur moyenne. Une variante lève le
	// bout du côté du nez (`interieur`) ou de la tempe (`exterieur`), et creuse ou aplatit l'arc : c'est l'inclinaison qui dit
	// l'émotion, l'épaisseur restant celle de la personne. 4 points, 6 triangles
	const sourcils = m.sourcils ?? { couleur: '#5b4636', epaisseur: .5 };
	const SOURCILS = {
		neutres: { interieur: 0, exterieur: 0, arc: .009 },
		hauts: { interieur: .03, exterieur: .022, arc: .016 },
		leves: { interieur: .012, exterieur: .01, arc: .01 },
		inquiets: { interieur: .026, exterieur: -.01, arc: .002 },
		fronces: { interieur: -.016, exterieur: .01, arc: .002 },
		tombants: { interieur: .008, exterieur: -.018, arc: .004 },
	};
	const sourcil = (sx, nom) => {
		const v = SOURCILS[nom];
		const pts = [0, .33, .66, 1].map(t => [sx * (xOeil - .04 + t * .08), yOeil + .085 + v.interieur * (1 - t) + v.exterieur * t + v.arc * Math.sin(t * Math.PI)]);
		return trait(pts, .009 + sourcils.epaisseur * .014, .0025);
	};
	for(const sx of [1, -1]) add(sourcil(sx, 'neutres'), sourcils.couleur, sx === 1 ? 'sourcilG' : 'sourcilD');

	// La bouche : le sourire d'encre, ou la couleur du rouge à lèvres, un peu plus large. Elle est une pièce à part, rôle
	// `bouche`, pour qu'une pose change d'expression en changeant sa géométrie : la moue, un arc retourné et plus court, sert
	// à la pose accablée, où un sourire contredisait tout le reste du corps. Les bouches ouvertes sont des surfaces pleines,
	// plus lisibles de loin qu'un trait d'un pixel. Toutes tiennent en 10 triangles au plus, ceux du sourire
	const levres = m['rouge-levres'], epaisseur = levres ? .02 : .013;
	// Chaque bouche dit aussi où tombe le bas de sa lèvre au milieu du visage, `bas` : un bijou de la lèvre suit ce bord d'une
	// bouche à l'autre (personnage.js), sans quoi il tombe dans une bouche ouverte ou pend sous un trait remonté
	const milieu = points => {
		const i = points.findIndex(([x], k) => k > 0 && x >= 0);
		const [[x0, y0], [x1, y1]] = [points[i - 1], points[i]];
		return y0 + (y1 - y0) * (0 - x0) / (x1 - x0);
	};
	const bouche = courbe => ({ geometry: trait(courbe, epaisseur, .0025), bas: milieu(courbe) - epaisseur / 2 });
	const ouverte = (geometry, bas) => ({ geometry, bas });
	const arcRire = Array.from({ length: 11 }, (_, i) => [Math.cos(i / 10 * Math.PI) * .052, yBouche + .022 - Math.sin(i / 10 * Math.PI) * .046]);
	const BOUCHES = {
		sourire: () => bouche([0, .2, .4, .6, .8, 1].map(t => [-.045 + t * .09, yBouche + .014 * ((t * 2 - 1) ** 2)])),
		moue: () => bouche([0, .2, .4, .6, .8, 1].map(t => [-.035 + t * .07, yBouche + .012 - .01 * ((t * 2 - 1) ** 2)])),
		// Le grand sourire, une moitié de disque ouverte sous une lèvre du haut creusée au milieu : 12 points, 10 triangles.
		// `arcRire` part du coin gauche de la personne et passe par le bas : l'éventail le parcourt à rebours, du coin droit au
		// coin gauche, pour tourner ses faces vers l'avant comme les autres traits, le bourg ne dessinant que les faces avant
		rire: () => {
			const arc = [...arcRire].reverse();
			return ouverte(plein([[0, yBouche + .014], ...arc], .0025), milieu(arc));
		},
		// Le sourire fermé, plus large et plus relevé aux coins que celui de base
		large: () => bouche([0, .2, .4, .6, .8, 1].map(t => [-.056 + t * .112, yBouche + .006 + .022 * ((t * 2 - 1) ** 2)])),
		// La bouche ronde de la surprise, un ovale plus haut que large : 10 triangles
		rond: () => ouverte(disque(0, yBouche - .006, .021, .03, .0025, 10), yBouche - .006 - .03),
		// Un trait droit et court, la bouche de qui ne sourit pas
		plate: () => bouche([0, .5, 1].map(t => [-.03 + t * .06, yBouche + .002])),
		// Un trait qui ondule, la bouche pincée de l'inquiétude
		pincee: () => bouche([0, .2, .4, .6, .8, 1].map(t => [-.032 + t * .064, yBouche + .004 - .007 * Math.sin(t * Math.PI * 2) - .006 * Math.sin(t * Math.PI)])),
	};
	const { geometry: sourire, bas: basRepos } = BOUCHES.sourire(), moue = BOUCHES.moue().geometry;
	parts.push({ name: 'visage', geometry: sourire, color: levres ? levres.couleur : ENCRE, pivot: a.pivots.tete, role: 'bouche' });

	// Les traits de chaque émotion (emotions.js), rôle par rôle, dans des géométries à elles : une scène les pose à la place
	// des traits de base le temps où elle montre l'émotion, puis remet ceux-ci
	const descente = {};
	const variantes = Object.fromEntries(Object.entries(EMOTIONS).map(([nom, { visage: v }]) => {
		const { geometry, bas } = BOUCHES[v.bouche]();
		const roles = { bouche: geometry };
		descente[nom] = bas - basRepos;
		for(const sx of [1, -1]) {
			const cote = sx === 1 ? 'G' : 'D', o = oeil(sx, v.yeux);
			Object.assign(roles, { ['oeil' + cote]: o.oeil, ['iris' + cote]: o.iris, ['eclat' + cote]: o.eclat, ['sourcil' + cote]: sourcil(sx, v.sourcils) });
		}
		return [nom, roles];
	}));

	// Les rides d'expression : trois traits en éventail au coin de chaque œil, un sillon de l'aile du nez au coin de la bouche,
	// une virgule au coin des lèvres pour les fossettes
	if(m['pattes-oie']) {
		const c = ride(m['pattes-oie'].marque);
		for(const sx of [1, -1]) for(const t of [-.45, 0, .45]) {
			const x0 = sx * (xOeil + rOeil + .016), y0 = yOeil + .004;
			add(trait([[x0, y0], [x0 + sx * .03 * Math.cos(t), y0 + .03 * Math.sin(t)]], .0055, .0022), c);
		}
	}
	if(m.sillons) {
		const c = ride(m.sillons.marque);
		for(const sx of [1, -1]) add(trait([[sx * .056, -.03], [sx * .074, -.052], [sx * .078, -.075], [sx * .07, -.098]], .006, .0022), c);
	}
	if(m.fossettes) {
		const c = ride(m.fossettes.marque);
		for(const sx of [1, -1]) add(trait([[sx * .06, yBouche + .016], [sx * .067, yBouche + .006], [sx * .063, yBouche - .006]], .006, .0022), c);
	}
	// `levre` : de combien le bas de la lèvre descend, au milieu, de la bouche au repos à celle de chaque émotion
	return { parts, expressions: { sourire, triste: moue }, variantes, levre: descente };
}

return { visage };
})();

// ---------- elements.js ----------
const module_elements = (() => {
// Les éléments créés depuis la photo, hors coiffure et vêtements : lunettes, barbes, bijoux,
// couvre-chefs et objets tenus en main. Du bois tourné et peint, comme le corps. Chaque fabrique reçoit les ancres du corps et
// les réglages de l'élément (couleur, tailles), et rend ses pièces dans le repère de son ancre :
//   - lunettes : `yeux.poser(x, y, lift)` pose un point du plan du visage sur la peau ;
//   - barbe : `menton.poser(th, phi, lift)` pose un point de la boule, donné en angles, sur la peau ;
//   - couvre-chef : origine au centre du cercle où il s'assoit, y le long de l'axe de la tête, z vers le visage. Il se taille
//     sur `crane` (rayon du crâne coiffé à cette hauteur) et `calotte` (ce que la tête coiffée dépasse au-dessus) ;
//   - main : origine au centre de la paume, axes du monde, `dehors` vaut 1 du côté +X et -1 de l'autre, `sol` est la hauteur
//     du sol dans ce repère. Un manche passe dans le poing, un panier pend d'aplomb sous lui ;
//   - bijou : coordonnées de la figure, sur les oreilles, la lèvre ou la poitrine ;
//   - accessoire : coordonnées de la figure, sur le corps habillé, chaque pièce nommant la pièce animée qui la porte (torse,
//     bassin) et la suivant dans toutes les poses.
// Aucune branche par prénom, aucun chargement de three : THREE arrive en argument.
const { merge, lathe, anse, ell, mix, nuance, lisse, geometrie, grille, rangsAutour, ENCRE, aPiece, depuisBlender } = module_outils;
const { disque, bande, ouverture, revers, boutons } = module_vetements;

const BOIS = '#c89a64', METAL = '#8e9aa0', VERT = '#4d8a3a';
const deg = d => d * Math.PI / 180;
const etendue = (de, a, n) => Array.from({ length: n + 1 }, (_, i) => de + (a - de) * i / n);

// Un manche tourné : un fût du bas jusqu'au-dessus du poing, fini par une boule, autour de l'axe vertical en (x, z)
const manche = (THREE, x, z, bas, haut, r = .014) =>
	lathe(THREE, [[r, bas], [r, haut - .05], [r * 1.6, haut - .02], [r * 1.1, haut + .01], [0, haut + .02]], 5).translate(x, 0, z);

const boite = (THREE, l, h, p, x, y, z) => new THREE.BoxGeometry(l, h, p).translate(x, y, z);

// Une feuille : un losange plat, une face de chaque côté pour se voir des deux
function feuille(THREE, base, pointe, largeur) {
	const A = new THREE.Vector3(...base), B = new THREE.Vector3(...pointe), d = B.clone().sub(A);
	const cote = new THREE.Vector3(-d.z, 0, d.x).normalize().multiplyScalar(largeur / 2);
	const m = A.clone().lerp(B, .45), g1 = m.clone().add(cote), g2 = m.clone().sub(cote);
	const pos = [A, g1, B, A, B, g2, A, B, g1, A, g2, B].flatMap(v => v.toArray());
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.computeVertexNormals();
	return g;
}

// Une monture : deux anneaux peints autour des yeux, un pont, deux branches qui filent vers les tempes et passent sous les
// cheveux, chaque point posé sur la peau. `carre` va du rond (0) au rectangle (1)
function monture(THREE, a, { carre, largeur, hauteur, epaisseur }) {
	const { x: xOeil, y: yOeil, r: rOeil, poser } = a.yeux;
	const geometrie = points => {
		const pos = [], nor = [];
		for(const o of points) { pos.push(o.p.x, o.p.y, o.p.z); nor.push(o.n.x, o.n.y, o.n.z); }
		const g = new THREE.BufferGeometry();
		g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
		g.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
		return g;
	};
	const trait = (points, l, lift) => {
		const bords = points.map(([x, y], i) => {
			const [xa, ya] = points[Math.max(0, i - 1)], [xb, yb] = points[Math.min(points.length - 1, i + 1)];
			const tx = xb - xa, ty = yb - ya, n = Math.hypot(tx, ty) || 1;
			const nx = ty / n * l / 2, ny = -tx / n * l / 2;
			return [poser(x + nx, y + ny, lift), poser(x - nx, y - ny, lift)];
		});
		const sommets = [];
		for(let i = 0; i < bords.length - 1; i++) {
			const [b0, h0] = bords[i], [b1, h1] = bords[i + 1];
			sommets.push(b0, b1, h1, b0, h1, h0);
		}
		return geometrie(sommets);
	};
	const anneau = (ext, int, lift) => {
		const sommets = [], n = ext.length;
		for(let i = 0; i < n; i++) {
			const j = (i + 1) % n;
			const e0 = poser(...ext[i], lift), e1 = poser(...ext[j], lift), i0 = poser(...int[i], lift), i1 = poser(...int[j], lift);
			sommets.push(i0, e0, e1, i0, e1, i1);
		}
		return geometrie(sommets);
	};
	const ep = epaisseur * 1.2 + .004, w = largeur * 1.25, h = Math.max(hauteur * 1.25, rOeil * 2.2 + ep * 2);
	const puissance = 2 + carre * 4;
	const contour = (cx, cy, ax, by) => Array.from({ length: 8 }, (_, i) => {
		const t = (i + .5) / 8 * Math.PI * 2, c = Math.cos(t), s = Math.sin(t);
		return [cx + ax * Math.sign(c) * Math.abs(c) ** (2 / puissance), cy + by * Math.sign(s) * Math.abs(s) ** (2 / puissance)];
	});
	const geos = [];
	for(const sx of [1, -1]) {
		geos.push(anneau(contour(sx * xOeil, yOeil, w / 2, h / 2), contour(sx * xOeil, yOeil, w / 2 - ep, h / 2 - ep), .007));
		const x0 = sx * (xOeil + w / 2 - ep * .3);
		// La branche suit la tempe en six pas : en trois, chaque corde passait sous les arêtes de la tête et la branche se lisait
		// en pointillés
		const branche = [[x0, yOeil + h * .2], [sx * .3, yOeil + h * .22], [sx * 1, yOeil + h * .2], [sx * 2.5, yOeil + h * .15]];
		geos.push(trait(branche.flatMap(([x, y], i) => i === 0 ? [[x, y]] : [1, 2].map(k => [branche[i - 1][0] + (x - branche[i - 1][0]) * k / 2, branche[i - 1][1] + (y - branche[i - 1][1]) * k / 2])), ep * .8, .006));
	}
	const xp = xOeil - w / 2 + ep * .3;
	geos.push(trait([[-xp, yOeil + h * .12], [0, yOeil + h * .2], [xp, yOeil + h * .12]], ep * .8, .003));
	// Des verres teintés : un éventail dans chaque cercle, au-dessus des yeux peints (le reflet de l'œil est à 5 mm) et sous
	// la monture. À la taille du jeu, une monture fine disparaît, des verres sombres restent une tache
	const verres = [];
	for(const sx of [1, -1]) {
		const int = contour(sx * xOeil, yOeil, w / 2 - ep * .5, h / 2 - ep * .5), c = poser(sx * xOeil, yOeil, .006), sommets = [];
		for(let i = 0; i < int.length; i++) sommets.push(c, poser(...int[i], .006), poser(...int[(i + 1) % int.length], .006));
		verres.push(geometrie(sommets));
	}
	return { monture: merge(THREE, geos), verres: merge(THREE, verres) };
}

// Une barbe : une coque posée sur le bas du visage, sur une grille d'angles. Le bord se pose près de la peau, l'intérieur se
// soulève de `relief` : la barbe a une épaisseur qui se voit de trois quarts
// `teinte(i, k)`, s'il est donné, rend la couleur de chaque sommet (colonne i, rang k) : la coque se peint alors de ses sommets
function coque(THREE, a, azimuts, rangs, relief, leveeBord = .012, teinte = null) {
	const pos = [], index = [], nA = azimuts.length, nR = rangs(0).length;
	azimuts.forEach((phi, i) => rangs(phi).forEach((th, k) => {
		const bord = i === 0 || i === nA - 1 || k === 0 || k === nR - 1;
		// Le bord reste au-dessus de la peau : entre deux sommets, la coque passe en corde sous les arêtes de la boule, qui à
		// 12 côtés saillent d'un centimètre au-dessus du milieu de leurs facettes
		pos.push(...a.menton.poser(th, phi, bord ? leveeBord : relief(phi, th) + .009).p.toArray());
	}));
	for(let i = 0; i < nA - 1; i++) for(let k = 0; k < nR - 1; k++) {
		const s = i * nR + k, t = (i + 1) * nR + k;
		index.push(s, t, t + 1, s, t + 1, s + 1);
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.setIndex(index);
	if(teinte) g.setAttribute('color', new THREE.Float32BufferAttribute(azimuts.flatMap((phi, i) => rangs(phi).flatMap((th, k) => teinte(i, k).toArray())), 3));
	g.computeVertexNormals();
	// L'ordre des sommets ne dit pas d'avance de quel côté regarde la coque : on le règle sur la normale de la peau
	const n = new THREE.Vector3().fromBufferAttribute(g.attributes.normal, nR + 1), dehors = a.menton.poser(rangs(azimuts[1])[1], azimuts[1], 0).n;
	if(n.dot(dehors) < 0) {
		g.setIndex(index.map((v, j) => j % 3 === 1 ? index[j + 1] : (j % 3 === 2 ? index[j - 1] : v)));
		g.computeVertexNormals();
	}
	return g.toNonIndexed();
}

// La moustache, au-dessus de la bouche, aux pointes tombantes. Plus large et plus tombante, elle rejoint la barbe au coin
// des lèvres
const moustache = (THREE, a, relief = .008, largeur = deg(26), chute = deg(4), epaisseur = deg(4.5), leveeBord = .012) => coque(THREE, a, etendue(-largeur, largeur, 4),
	phi => etendue(deg(99), deg(99) + epaisseur, 2).map(th => th + chute * (phi / largeur) ** 2), () => relief, leveeBord);

// Le collier de barbe : de l'avant de l'oreille au menton, sous la bouche, et jusque sous la mâchoire
const collier = (THREE, a, haut, bas, relief) => coque(THREE, a, etendue(deg(-84), deg(84), 8), phi => etendue(haut(phi), bas, 3), relief);

// Un tirage reproductible par cellule : la même barbe à chaque rendu, sans grain qui bouge d'une image à l'autre
const hasard = (i, k, f) => {
	const x = Math.sin(i * 12.9898 + k * 78.233 + f * 37.719) * 43758.5453;
	return x - Math.floor(x);
};

// Une barbe modelée dans Blender (pieces.js, pour l'avatar seul) : la pièce porte sa forme entière, modelée sur la tête du
// corps commun, et ne reçoit que sa teinte. Chaque sommet donne deux doses (`fondu` de perso_export.py), les mêmes que
// celles d'une chevelure : son fondu vers la peau, là où le poil s'éclaircit en lisière, et ce qu'il fonce, au creux d'une
// mèche. La bouche est une pièce du visage que chaque émotion redessine : une barbe n'en suit aucune, et se modèle autour
// de toutes
function barbeBlender(THREE, a, p, piece) {
	const base = new THREE.Color(p.couleur), peau = new THREE.Color(a.peau), sombre = new THREE.Color(nuance(THREE, p.couleur, -.09)), c = new THREE.Color();
	return depuisBlender(THREE, piece, {}, { couleur: p.couleur }).map(m => {
		const g = m.geometry, doses = g.attributes.fondu, couleurs = new Float32Array(doses.count * 3);
		for(let i = 0; i < doses.count; i++) c.copy(base).lerp(peau, doses.getY(i)).lerp(sombre, doses.getZ(i)).toArray(couleurs, i * 3);
		g.deleteAttribute('fondu');
		g.setAttribute('color', new THREE.BufferAttribute(couleurs, 3));
		return { geometry: g, color: p.couleur };
	});
}
// Là où sa pièce n'est pas livrée (les scènes), l'entrée garde la barbe en JS la plus proche (SKILL.md, « Pas de mélange »)
const barbePiece = (piece, js) => (THREE, a, p) => aPiece(piece) ? barbeBlender(THREE, a, p, piece) : BARBES[js](THREE, a, p);

const LUNETTES = {
	lunettes: (THREE, a, p) => {
		const { monture: m, verres } = monture(THREE, a, p);
		return [{ geometry: m, color: p.couleur }, ...(p.verres ? [{ geometry: verres, color: p.verres }] : [])];
	},
};

const BARBES = {
	// Une barbe de quelques jours laisse voir la peau entre les poils : sa couleur pleine, sur un centimètre d'épaisseur,
	// posait un masque sur le bas du visage
	'barbe-courte': (THREE, a, p) => [{ geometry: collier(THREE, a, phi => deg(110) + deg(4) * Math.cos(phi), deg(156), () => .004), color: mix(THREE, p.couleur, a.peau, .75) }],
	// Elle remonte sur les joues et descend sous le menton, plus épaisse au milieu : la pointe se voit de profil
	'barbe-pleine': (THREE, a, p) => [{
		geometry: merge(THREE, [
			collier(THREE, a, phi => deg(100) + deg(10) * Math.cos(phi), deg(160), (phi, th) => .012 + .016 * Math.cos(phi) * Math.sin((th - deg(100)) / deg(60) * Math.PI)),
			moustache(THREE, a),
		]),
		color: p.couleur,
	}],
	'moustache': (THREE, a, p) => [{ geometry: moustache(THREE, a), color: p.couleur }],
	// Taillée court, elle couvre le bas des joues et le menton, sans la pointe de la barbe pleine. Son bord descend en arc sous
	// la bouche, qu'elle laisse voir, et remonte devant les oreilles jusqu'aux pattes : un bord droit sous la moustache, arrêté
	// net avant l'oreille, cachait la bouche et se lisait comme un masque. La moustache garde le ton que la photo lui donne, souvent plus foncé que le menton chez qui
	// grisonne
	'barbe-taillee': (THREE, a, p) => {
		// Du poil ras et poivre et sel, pas une surface : le ton change d'un sommet à l'autre, du blanc au gris, et se fond
		// entre eux en marbrure, le relief bosselle par touffes, les deux bords s'effrangent. Un dégradé lisse d'un seul ton se
		// lisait comme un masque pâle posé sur le bas du visage ; une couleur franche par facette, comme du cristal ou du
		// plâtre brisé, et une frange de pointes sous la mâchoire, comme des dents. Le bord haut et les pattes passent à la peau,
		// comme une barbe qui pousse moins là où elle s'arrête, et les pattes s'arrêtent avant l'oreille, où elles y entraient
		const nA = 12, nR = 4, large = deg(76), pas = 2 * large / nA;
		const colonne = phi => Math.round((phi + large) / pas);
		const haut = phi => deg(104) + deg(13) * Math.cos(phi) ** 4 - deg(12) * (Math.abs(phi) / large) ** 3 + deg(4) * (colonne(phi) % 2 ? 1 : -1);
		const bas = phi => deg(157) + deg(4) * (colonne(phi) % 2 ? -1 : 1) * Math.cos(phi);
		const relief = (phi, th) => .004 + .005 * Math.cos(phi) * Math.sin((th - deg(104)) / deg(53) * Math.PI) + .004 * (hasard(colonne(phi), Math.round(th * 40), 1) - .5);
		const clair = new THREE.Color(mix(THREE, p.couleur, '#ffffff', .25)), sombre = new THREE.Color(mix(THREE, p.couleur, '#000000', .22)), peau = new THREE.Color(a.peau);
		const teinte = (i, k) => {
			const bord = Math.max(k === 0 ? .7 : 0, i === 0 || i === nA ? .6 : i === 1 || i === nA - 1 ? .25 : 0);
			return sombre.clone().lerp(clair, hasard(i, k, 2)).lerp(peau, bord);
		};
		return [
			{ geometry: coque(THREE, a, etendue(-large, large, nA), phi => etendue(haut(phi), bas(phi), nR), relief, .012, teinte), color: p.couleur },
			// La moustache affleure, bord compris, et ses pointes tombent un peu : levée d'un centimètre, elle se voyait par la
			// tranche de profil, comme une planchette plantée dans la joue, et plus tombante, comme une barre le long de la joue
			{ geometry: moustache(THREE, a, -.004, deg(28), deg(6), deg(6), .004), color: p.moustache ?? p.couleur },
		];
	},
	// Ces quatre barbes n'existent que par leur pièce, et prennent en scène la barbe en JS la plus proche
	'barbe-longue': barbePiece('barbe-longue', 'barbe-pleine'),
	'bouc': barbePiece('bouc', 'moustache'),
	'moustache-fournie': barbePiece('moustache-fournie', 'moustache'),
	'favoris': barbePiece('favoris', 'barbe-courte'),
};

// Le bord intérieur d'un chapeau à passe, sur l'avatar (a.contour, que donne l'assise sous `raccords`) : son premier rang,
// rayon c à la hauteur y, descend de `descente` sous le plan d'assise et suit la tête coiffée à 4 mm, azimut par azimut. Le
// cercle d'assise est le plus large de la tête coiffée, qui n'est pas centrée sur l'axe : le bord restait à 4 à 7 cm du
// front et des tempes, et l'on voyait le ciel entre lui et les cheveux. Abaissé, il rejoint la tête là où elle s'élargit, et
// ce qui reste au-dessus est sous la passe. Le contour se mesure sous chaque sommet et au milieu de ses deux arêtes, puis
// s'élargit de ce que la corde d'une arête rentre
// Entre ce bord et la peau, un bandeau ferme le dessous de la coiffe : la chevelure s'arrête au plan d'assise sur une face
// tournée vers le haut (`rabattre()` de personnage.js), que les scènes ne dessinent pas vue d'en dessous, et l'on voyait le
// fond par la fente entre le bord et les cheveux. Il remonte du bord à la peau nue sur le plan d'assise ; sa partie dans les
// cheveux ne se voit pas. Rend le bandeau
function bordSurLaTete(THREE, geometry, a, c, y, sides, descente) {
	const pos = geometry.attributes.position, corde = Math.cos(Math.PI / sides), pas = Math.PI / sides, bord = new Map();
	for(let i = 0; i < pos.count; i++) {
		if(Math.abs(pos.getY(i) - y) > 1e-4 || Math.abs(Math.hypot(pos.getX(i), pos.getZ(i)) - c) > 1e-4) continue;
		const phi = Math.atan2(pos.getX(i), pos.getZ(i));
		const r = Math.max(...[phi - pas, phi, phi + pas].map(t => a.contour(t, descente + .008)));
		const rayon = Math.min(c, (r + .004) / corde);
		pos.setXYZ(i, Math.sin(phi) * rayon, y - descente, Math.cos(phi) * rayon);
		bord.set(Math.round((phi + 2 * Math.PI) % (2 * Math.PI) / (2 * pas)) % sides, rayon);
	}
	geometry.computeVertexNormals();
	// Le bandeau, quadrilatère par quadrilatère, ses faces vers le bas et vers l'axe
	const point = (k, r, h) => { const phi = k * 2 * pas; return [Math.sin(phi) * r, h, Math.cos(phi) * r]; };
	const triangles = [];
	for(let k = 0; k < sides; k++) {
		const l = (k + 1) % sides;
		const b0 = point(k, bord.get(k), y - descente), b1 = point(l, bord.get(l), y - descente), p0 = point(k, a.peau, 0), p1 = point(l, a.peau, 0);
		triangles.push(...b0, ...p1, ...b1, ...b0, ...p0, ...p1);
	}
	const bandeau = new THREE.BufferGeometry();
	bandeau.setAttribute('position', new THREE.Float32BufferAttribute(triangles, 3));
	bandeau.computeVertexNormals();
	return bandeau;
}

// Le bonnet de l'avatar serré sur la tête coiffée, azimut par azimut. Un tour taillé sur le plus large de la tête coiffée au
// plan d'assise, mèches posées comprises alors qu'elles s'effacent sous le bonnet, flotte de 4 à 9 cm à la tempe et au front,
// où la tête est la plus étroite. Chaque sommet du revers vient à BONNET_JEU de ce qui reste de la tête coiffée à sa hauteur,
// sur une bande de BONNET_BANDE au-dessus de lui, mesuré pour que le milieu du pan (la corde) passe encore dehors, sans jamais
// s'écarter plus que le tour d'origine. Le pied de la coiffe suit, pour garder l'épaisseur du roulé, et s'efface vers le
// dessus, que la coiffe garde en dôme. Un bandeau un ton plus sombre ferme, sous le bord, ce que la corde laisse entre deux
// sommets
// Tout ce qui se lit sur le bonnet vient de ses deux profils, que l'entrée `bonnet` donne aux scènes comme à l'avatar : le
// rang du bas du revers (son bord, `bas` sous le plan d'assise), son rang du haut, son pied et celui de la coiffe, et les deux
// rangs de la coiffe entre lesquels elle retrouve son dôme. L'épaisseur gardée entre le bord serré et le pied est celle du
// roulé seul, sans l'élargissement `large` du revers : rapportée au pied, elle ferait du dessus du revers une couronne plate,
// un chapeau à bord, là où le bonnet doit resserrer
const BONNET_JEU = .006, BONNET_BANDE = .03, BONNET_BANDEAU = .012;
function bonnetSurLaTete(THREE, a, revers, profilRevers, large, coiffe, profilCoiffe, pans, couleur) {
	const corde = Math.cos(Math.PI / pans), pas = Math.PI / pans;
	const [haut, fond] = profilRevers[0], bas = -fond, dessus = -profilRevers.at(-2)[1], piedRevers = profilRevers.at(-1)[0];
	const [[piedCoiffe, fondCoiffe], [, mur], [, dome]] = profilCoiffe, epaisseur = haut - large - piedRevers;
	const tete = (phi, profondeur) => Math.max(...[phi - pas, phi, phi + pas].map(t => a.contour(t, profondeur + .004, Math.max(0, profondeur - BONNET_BANDE))));
	const bord = (phi, profondeur) => Math.min(haut, (tete(phi, profondeur) + BONNET_JEU) / corde);
	const pied = phi => Math.min(piedCoiffe, Math.max(bord(phi, dessus) - epaisseur, (tete(phi, -fondCoiffe) + .003) / corde));
	const serrer = (g, rayon) => {
		const pos = g.attributes.position;
		for(let i = 0; i < pos.count; i++) {
			const x = pos.getX(i), z = pos.getZ(i), r = Math.hypot(x, z);
			if(r < 1e-6) continue;
			const phi = Math.atan2(x, z), k = rayon(phi, r, pos.getY(i)) / r;
			pos.setXYZ(i, x * k, pos.getY(i), z * k);
		}
		g.computeVertexNormals();
	};
	// Le revers garde son roulé, le rang du milieu plus large que les autres, décalé du bord serré à sa hauteur
	serrer(revers, (phi, r, y) => r > (haut + piedRevers) / 2 ? r - haut + bord(phi, Math.max(dessus, -y)) : pied(phi));
	const fondu = y => Math.min(1, Math.max(0, (dome - y) / (dome - mur)));
	serrer(coiffe, (phi, r, y) => r * (1 + (pied(phi) / piedCoiffe - 1) * fondu(y)));
	// Le bandeau sous le bord, du rang du bas du revers vers la tête, faces vers le bas
	const triangles = [], point = (k, r, y) => { const phi = k * 2 * pas; return [Math.sin(phi) * r, y, Math.cos(phi) * r]; };
	for(let k = 0; k < pans; k++) {
		const rb = j => bord(j * 2 * pas, bas), rt = j => tete(j * 2 * pas, bas) - .004;
		const b0 = point(k, rb(k), -bas), b1 = point(k + 1, rb(k + 1), -bas), t0 = point(k, rt(k), -bas + BONNET_BANDEAU), t1 = point(k + 1, rt(k + 1), -bas + BONNET_BANDEAU);
		triangles.push(...b0, ...t1, ...b1, ...b0, ...t0, ...t1);
	}
	const bandeau = new THREE.BufferGeometry();
	bandeau.setAttribute('position', new THREE.Float32BufferAttribute(triangles, 3));
	bandeau.computeVertexNormals();
	return { geometry: bandeau, color: nuance(THREE, couleur, -.2) };
}

// La casquette en JS, que gardent les cibles dont pieces.js ne porte pas celle faite dans Blender
function casquetteJs(THREE, a, p) {
	// La calotte contient la chevelure rabattue sur le plan d'assise, et ce qui reste dessous jusqu'à son bord : mesurée au
	// milieu d'un pan, où sa face passe le plus près de l'axe. Taillée à 8 mm du seul cercle d'assise, elle laissait sortir
	// les cheveux d'une coupe rase à la tempe, contre la racine de la visière
	const pans = 10, h = a.calotte + .02;
	const c = Math.max(a.crane + .008, (Math.max(a.crane, a.sous(.01)) + .003) / Math.cos(Math.PI / pans));
	const calotte = lathe(THREE, [[c, -.01], [c * .98, h * .45], [c * .72, h * .88], [0, h]], pans);
	// La visière : un éventail devant le front, qui s'incline vers le bas
	const pos = [], n = 6, ouverture = 1.25;
	for(let i = 0; i < n; i++) {
		const t0 = -ouverture + 2 * ouverture * i / n, t1 = -ouverture + 2 * ouverture * (i + 1) / n;
		const pt = (t, r, y) => [Math.sin(t) * r, y, Math.cos(t) * r];
		const a0 = pt(t0, c - .004, -.004), a1 = pt(t1, c - .004, -.004);
		const b0 = pt(t0, c + .14 * Math.cos(t0) ** 2 + .01, -.03), b1 = pt(t1, c + .14 * Math.cos(t1) ** 2 + .01, -.03);
		pos.push(...a0, ...b0, ...b1, ...a0, ...b1, ...a1);
	}
	const visiere = new THREE.BufferGeometry();
	visiere.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	visiere.computeVertexNormals();
	return [{ geometry: calotte, color: p.couleur }, { geometry: visiere, color: p.visiere }];
}

// La fleur en JS, que gardent les cibles dont pieces.js ne porte pas celle faite dans Blender : glissée dans le ruban d'un
// chapeau, sur le côté gauche du devant, cinq pétales en étoile autour d'un cœur jaune, et une feuille dessous. Plate et
// tournée vers le dehors, elle ne se voit que d'un côté, celui qu'on regarde
function fleurJs(THREE, r, y, az, couleur) {
	const n = new THREE.Vector3(Math.sin(az), 0, Math.cos(az)), u = new THREE.Vector3(Math.cos(az), 0, -Math.sin(az)), v = new THREE.Vector3(0, 1, 0);
	const c = n.clone().multiplyScalar(r).add(new THREE.Vector3(0, y, 0));
	const etoile = (rayons, decalage, lift) => {
		const pos = [], m = rayons.length, o = c.clone().addScaledVector(n, lift);
		const pt = k => o.clone().addScaledVector(u, Math.cos(k / m * Math.PI * 2 + decalage) * rayons[k % m]).addScaledVector(v, Math.sin(k / m * Math.PI * 2 + decalage) * rayons[k % m]);
		for(let k = 0; k < m; k++) pos.push(...o.toArray(), ...pt(k).toArray(), ...pt(k + 1).toArray());
		return geometrie(THREE, pos, pos.map((_, i) => n.toArray()[i % 3]));
	};
	const petales = etoile(Array.from({ length: 10 }, (_, k) => k % 2 ? .034 : .07), 0, .004);
	const coeur = etoile([.024, .024, .024, .024, .024], .3, .007);
	const feuilleG = etoile([.02, .085, .02, .0], -.6, .002);
	return [{ geometry: petales, color: couleur }, { geometry: coeur, color: '#f2c84b' }, { geometry: feuilleG, color: VERT }];
}

// La fleur faite dans Blender (fleur.blend), en volume : son origine au pied de la fleur, z vers le dehors, y vers le haut. Elle
// se pose au milieu d'un pan du ruban, à son rayon, là où celle en JS, mesurée sur le cercle des sommets, flottait devant
function fleur(THREE, c, sides, couleur) {
	// Le milieu d'un pan est à un nombre impair de demi-pans de l'avant : celui qui tombe le plus près de l'azimut de la fleur en JS
	const pan = Math.PI / sides, az = pan * (2 * Math.round((.75 / pan - 1) / 2) + 1);
	if(aPiece('fleur') === false) return fleurJs(THREE, c + .016, .05, .75, couleur);
	const repere = new THREE.Matrix4().makeRotationY(az).setPosition(Math.sin(az) * (c + .0115) * Math.cos(pan), .05, Math.cos(az) * (c + .0115) * Math.cos(pan));
	return depuisBlender(THREE, 'fleur', {}, { fleur: couleur }).map(m => ({ geometry: m.geometry.applyMatrix4(repere), color: m.color }));
}

// Un couvre-chef modelé dans Blender (pieces.js, pour l'avatar seul), dans le repère de l'assise, sur la tête nue. Trois
// choses l'ajustent à la tête coiffée. Sa forme clé `rayon` écarte de l'axe ce qui entoure la coiffure, au cercle d'assise,
// et `hauteur` monte ce qui la coiffe. Un sommet qui porte la dose `colle` (premier canal de `fondu`, perso_export.py) suit
// la tête coiffée à son azimut : il s'écarte de l'axe de ce que les cheveux épaississent la tête entre le plan d'assise et
// lui, mesuré sur son azimut et ses deux voisins comme le fait `bordSurLaTete()`, ou sur sept azimuts et trois hauteurs
// quand la pièce se ceint (`ceint`, plus bas). C'est le bord qui touche la tête, ou un bandeau posé sur les cheveux.
// Au-delà de 8 cm ce n'est plus une épaisseur de cheveux mais une queue ou une masse qui tombe, que le bord laisse passer
// comme le revers du bonnet
const ASSISE_HAUT = .15;
// La peau nue à `d` sous le plan d'assise, qui est à ASSISE_HAUT du centre de la boule (`assise()` de corps.js)
const nue = (a, d) => Math.sqrt(a.peau ** 2 + 2 * ASSISE_HAUT * d - d * d);
// `ceint` : la pièce se ceint par-dessus la chevelure entière au lieu de border une coiffure rabattue. Ses rangs de douze
// sommets coupent alors droit entre deux mèches, d'où le contour lu plus serré et plus bas, et la corde rendue en marge
function epaisseurCoiffee(a, ceint) {
	const pas = Math.PI / 12, connues = new Map(), tours = ceint ? [-1.25, -.75, -.25, 0, .25, .75, 1.25] : [-1, 0, 1], creux = ceint ? [0, .02, .04] : [0];
	return (phi, d) => {
		const cle = Math.round(phi / pas * 8) + '|' + Math.round(d * 1000);
		if(connues.has(cle) === false) connues.set(cle, Math.min(ceint ? .18 : .08, Math.max(0, Math.max(...tours.flatMap(t => creux.map(c => a.contour(phi + t * pas, d + c)))) - nue(a, d))) * (ceint ? 1.04 : 1) + (ceint ? .016 : .006));
		return connues.get(cle);
	};
}
function chefBlender(THREE, a, p, piece, ceint = false) {
	const epaisseur = epaisseurCoiffee(a, ceint);
	// Le morceau teint `cheveux` (la frange que laisse voir un fichu) suppose une coiffure : sans elle, il n'a rien à montrer.
	// Il reçoit quand même une teinte, que depuisBlender() exige pour chaque morceau, avant d'être écarté
	const teintes = p.cheveux === undefined ? { ...p, cheveux: p.couleur } : p;
	return depuisBlender(THREE, piece, { rayon: a.crane, hauteur: a.calotte }, teintes).filter(m => p.cheveux !== undefined || m.teinte !== 'cheveux').map(m => {
		const pos = m.geometry.attributes.position, doses = m.geometry.attributes.fondu;
		for(let i = 0; i < pos.count; i++) {
			const dose = doses.getX(i);
			if(dose === 0) continue;
			const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i);
			const k = 1 + dose * epaisseur(Math.atan2(x, z), Math.max(.008, .008 - y)) / Math.hypot(x, z);
			pos.setXYZ(i, x * k, y, z * k);
		}
		m.geometry.deleteAttribute('fondu');
		return { geometry: m.geometry, color: m.color };
	});
}
// Là où sa pièce n'est pas livrée (les scènes), l'entrée garde le couvre-chef en JS le plus proche, ou rien quand aucun ne
// lui ressemble. La pièce se monte sur le contour de la tête coiffée, que l'assise ne mesure que pour l'avatar et pour un
// couvre-chef qui se ceint. `couvre` nomme les zones du corps que le couvre-chef cache, et qu'il retire s'il rend des pièces
const chefPiece = (piece, js = null, { ceint = false, couvre = [] } = {}) => (THREE, a, p) => {
	const parts = aPiece(piece) && a.contour ? chefBlender(THREE, a, p, piece, ceint) : js ? js(THREE, a, p) : [];
	if(parts.length > 0) parts.couvre = couvre;
	return parts;
};
// Ce que devient la coiffure sous un couvre-chef qui ne se pose pas sur elle comme un chapeau (`build()` de personnage.js) :
// `entiere`, il se ceint autour d'elle sans rien en retirer ; `masquee`, il la couvre toute, et reçoit sa teinte
// (`p.cheveux`) pour ce qu'il en laisse dépasser
const COIFFURE_SOUS = { 'bandeau-frontal': 'entiere', 'hijab-travail': 'masquee', 'fichu': 'masquee', 'charlotte': 'masquee', 'cagoule': 'masquee', 'turban': 'masquee' };
const TETE_COUVERTE = { couvre: ['crane', 'oreilles'] };

// Les coiffes en JS que gardent les scènes, chacune sur le profil de sa pièce relevé dans le repère de l'assise : la tête
// nue sous les cinq qui couvrent la coiffure, la tête coiffée sous le bandeau. Elles se lisent à la taille d'un personnage
// de scène par leur silhouette et leurs bandes de teinte, et le dessin fin de la pièce (plis, nœud modelé) reste à l'avatar

// Le rayon, à la hauteur y, de la sphère qui enveloppe la tête à `t` de la peau. Le centre de la boule est à ASSISE_HAUT
// sous le plan d'assise
const autour = (a, y, t) => Math.sqrt(Math.max(0, (Math.hypot(a.peau, ASSISE_HAUT) + t) ** 2 - (y + ASSISE_HAUT) ** 2));

// Une coiffe tournée autour de l'axe de la tête, de S colonnes, faces vers le dehors. Chaque rang rend, pour l'azimut phi
// (0 devant, x = sin phi), sa hauteur et son rayon, [y, r], un rayon nul faisant le sommet : un rang qui descend avec
// l'azimut fait un bord en biais, sans marche. `teinte(phi, k)` dit la teinte du quadrilatère de la colonne phi entre les
// rangs k et k + 1, ou null pour le laisser ouvert. Une colonne est centrée devant, où s'ouvre le visage
const point = (phi, y, r) => [Math.sin(phi) * r, y, Math.cos(phi) * r];
function voile(THREE, rangs, S, teinte) {
	// Ramené entre -π et π, pour que le profil et la teinte d'un azimut ne dépendent pas du tour où la colonne le prend
	const phi = c => {
		const t = Math.PI / S - c / S * Math.PI * 2;
		return Math.atan2(Math.sin(t), Math.cos(t));
	};
	const points = rangs.map(profil => {
		const [y, r] = profil(0);
		return r === 0 ? { pole: [0, y, 0] } : Array.from({ length: S }, (_, c) => point(phi(c), ...profil(phi(c))));
	});
	return grille(THREE, points, S, (k, c) => teinte(phi(c + .5), k));
}
// Un rang de même hauteur et de même rayon tout autour
const rang = (y, r) => () => [y, r];

// Une pièce mince vue des deux côtés : chaque triangle porte aussi sa face retournée
function mince(THREE, triangles) {
	const pos = [], nor = [], u = new THREE.Vector3(), v = new THREE.Vector3();
	for(const [A, B, C] of triangles) for(const [P, Q, R] of [[A, B, C], [A, C, B]]) {
		u.set(Q[0] - P[0], Q[1] - P[1], Q[2] - P[2]).cross(v.set(R[0] - P[0], R[1] - P[1], R[2] - P[2])).normalize();
		pos.push(...P, ...Q, ...R);
		for(let i = 0; i < 3; i++) nor.push(u.x, u.y, u.z);
	}
	return geometrie(THREE, pos, nor);
}

// Le bandeau ceint la tête coiffée sur une bande de 6 cm sous le plan d'assise, à l'écart que donne le contour, comme la
// pièce (`chefBlender()`) ; trois losanges du motif sur le front, deux bouts noués qui pendent derrière, à gauche
function bandeauJs(THREE, a, p) {
	const S = 16, epaisseur = epaisseurCoiffee(a, true);
	const rayon = (phi, y) => {
		const d = .008 - y;
		return nue(a, d) + .012 + epaisseur(phi, d);
	};
	const { couleur } = voile(THREE, [phi => [0, rayon(phi, 0)], phi => [-.06, rayon(phi, -.06)]], S, () => 'couleur');
	// Au milieu d'une colonne, la face passe à cos(π/S) du rayon de ses arêtes ; le losange se pose devant la plus écartée des
	// quatre, le contour changeant le rayon d'une arête à l'autre
	const losanges = [0, Math.PI / S * 2, -Math.PI / S * 2].map(phi => {
		const r = Math.max(...[-1, 1].flatMap(s => [0, -.06].map(y => rayon(phi + s * Math.PI / S, y))));
		const c = point(phi, -.03, r * Math.cos(Math.PI / S) + .003), u = [Math.cos(phi) * .012, 0, -Math.sin(phi) * .012];
		const haut = [c[0], c[1] + .017, c[2]], bas = [c[0], c[1] - .017, c[2]], gauche = c.map((x, i) => x + u[i]), droite = c.map((x, i) => x - u[i]);
		const sommets = [haut, bas, gauche, haut, droite, bas];
		return { p: sommets.flat(), n: sommets.flatMap(() => [Math.sin(phi), 0, Math.cos(phi)]) };
	});
	const bout = (phi, ecart, longueur) => {
		const r = rayon(phi, -.03) + .006, haut = [point(phi - .1, -.03, r), point(phi + .1, -.03, r)];
		const bas = [point(phi - .1 + ecart, -.03 - longueur, r + .02), point(phi + .1 + ecart, -.03 - longueur, r + .02)];
		return [[haut[0], bas[0], bas[1]], [haut[0], bas[1], haut[1]]];
	};
	return [
		{ geometry: couleur, color: p.couleur },
		{ geometry: geometrie(THREE, losanges.flatMap(l => l.p), losanges.flatMap(l => l.n)), color: p.motif },
		{ geometry: mince(THREE, [...bout(2.25, -.12, .15), ...bout(2.45, .1, .13)]), color: p.couleur },
	];
}

// Les cinq qui couvrent la coiffure s'ouvrent sur le visage entre ±45°, d'une tempe à l'autre
const VISAGE = Math.PI / 4;
// Le haut de la sphère de `autour()`
const sommet = (a, t) => Math.hypot(a.peau, ASSISE_HAUT) + t - ASSISE_HAUT;

// Le hijab couvre la tête, les oreilles et le cou à 2,5 cm de la peau, s'ouvre sur le visage du front au menton et tombe en
// pèlerine sur les épaules. Le bandeau de dessous, plus clair, passe sur le front au-dessus de l'ouverture
function hijabJs(THREE, a, p) {
	const rayon = y => autour(a, y, .025);
	const rangs = [rang(sommet(a, .025), 0), ...[.13, .07, -.01, -.09, -.17, -.25, -.33].map(y => rang(y, rayon(y))), rang(-.4, .21), rang(-.47, .215), rang(-.57, .25)];
	const g = voile(THREE, rangs, 12, (phi, k) => Math.abs(phi) > VISAGE ? 'couleur' : k >= 4 && k <= 6 ? null : k === 3 ? 'bandeau' : 'couleur');
	return [{ geometry: g.couleur, color: p.couleur }, { geometry: g.bandeau, color: nuance(THREE, p.couleur, .14) }];
}

// La cagoule prend la tête et le cou à 2 cm de la peau et s'ouvre sur le visage, des sourcils au menton, bordée d'un
// bourrelet plus sombre ; un col en tube la finit sur le cou
function cagouleJs(THREE, a, p) {
	const rayon = y => autour(a, y, .02), bord = [-.07, -.15, -.23, -.31];
	const rangs = [rang(sommet(a, .02), 0), ...[.13, .07, -.01, ...bord, -.38].map(y => rang(y, rayon(y))), rang(-.44, .155), rang(-.5, .165)];
	const g = voile(THREE, rangs, 12, (phi, k) => Math.abs(phi) < VISAGE && k >= 4 && k <= 6 ? null : 'couleur');
	const tour = [
		...bord.map(y => point(VISAGE, y, rayon(y))), ...[1, -1].map(s => point(s * VISAGE / 3, bord.at(-1), rayon(bord.at(-1)))),
		...[...bord].reverse().map(y => point(-VISAGE, y, rayon(y))), ...[-1, 1].map(s => point(s * VISAGE / 3, bord[0], rayon(bord[0]))),
	];
	return [{ geometry: g.couleur, color: p.couleur }, { geometry: anse(THREE, [...tour, tour[0]], .013), color: nuance(THREE, p.couleur, -.13) }];
}

// Le fichu couvre la tête et les oreilles à 1,5 cm de la peau, laisse le visage libre sous le front et finit en pointe dans
// le dos. Un liseré plus sombre le borde autour du visage, ses deux pans se nouent sous le menton, et sous son bord passe
// la frange de la coiffure qu'il cache, quand il y en a une
function fichuJs(THREE, a, p) {
	// Sous la tête, l'étoffe tombe sur la nuque sans la suivre, à 21 cm de l'axe au moins
	const bord = [-.33, -.26, -.18, -.1, -.06], rayon = y => y < bord[0] ? Math.max(autour(a, y, .015), .21) : autour(a, y, .015);
	// Son bord court sous la mâchoire et descend en pointe au milieu du dos
	const bas = phi => -.36 - .2 * lisse((Math.abs(phi) - deg(80)) / deg(100));
	const tombe = s => phi => {
		const y = bord[0] + s * (bas(phi) - bord[0]);
		return [y, rayon(y)];
	};
	const rangs = [rang(sommet(a, .015), 0), ...[.12, .06, -.01, ...[...bord].reverse()].map(y => rang(y, rayon(y))), tombe(.4), tombe(.75), tombe(1)];
	const g = voile(THREE, rangs, 12, (phi, k) => Math.abs(phi) < VISAGE && k >= 4 ? (k === 4 && p.cheveux ? 'cheveux' : null) : 'couleur');
	const lisere = [...bord.map(y => point(VISAGE, y, rayon(y))), ...[1, -1].map(s => point(s * VISAGE / 3, bord.at(-1), rayon(bord.at(-1)))), ...[...bord].reverse().map(y => point(-VISAGE, y, rayon(y)))];
	const noeud = [0, -.44, .12];
	const brides = [1, -1].map(s => anse(THREE, [point(s * VISAGE, bord[0], rayon(bord[0])), noeud], .022));
	const pans = mince(THREE, [1, -1].map(s => [[s * .012, -.45, .14], [s * .075, -.51, .215], [s * .03, -.525, .22]]));
	return [
		{ geometry: g.couleur, color: p.couleur },
		...(g.cheveux ? [{ geometry: g.cheveux, color: p.cheveux }] : []),
		{ geometry: anse(THREE, lisere, .01), color: nuance(THREE, p.couleur, -.14) },
		{ geometry: merge(THREE, [...brides, ell(THREE, ...noeud, .04, .03, .032, 6, 4)]), color: p.couleur },
		{ geometry: pans, color: p.couleur },
	];
}

// La charlotte bouffe autour de la tête, plus haute et plus large que le crâne, et se fronce sur un élastique qui passe sur
// le front, devant l'oreille et sous la nuque : son bord descend en biais du front vers la nuque
function charlotteJs(THREE, a, p) {
	const haut = sommet(a, .055), bord = phi => -.03 - .24 * lisse(Math.abs(phi) / deg(120));
	const bouffe = [.15, .32, .5, .68, .84].map(s => phi => {
		const y = haut + s * (bord(phi) + .02 - haut);
		return [y, autour(a, y, .055) * (1 - .08 * Math.max(0, Math.cos(phi)))];
	});
	const rangs = [rang(haut, 0), ...bouffe, phi => [bord(phi) + .02, autour(a, bord(phi) + .02, .03)], phi => [bord(phi), autour(a, bord(phi), .012)]];
	const g = voile(THREE, rangs, 12, (phi, k) => k === 6 ? 'elastique' : k === 5 ? 'fronce' : 'couleur');
	return [{ geometry: g.couleur, color: p.couleur }, { geometry: g.fronce, color: nuance(THREE, p.couleur, -.05) }, { geometry: g.elastique, color: p.elastique }];
}

// Le turban s'enroule en tours larges et hauts autour de la tête et des oreilles. Ses tours suivent son bord, en biais du
// front vers la nuque où il descend : chacun déborde d'un bourrelet sur le suivant, dont l'ombre les sépare à la taille d'un
// personnage de scène, et ils se croisent en pointe au milieu du front, sur l'étoffe de dessous
function turbanJs(THREE, a, p) {
	const haut = sommet(a, .09), bord = phi => -.03 - .24 * lisse((Math.abs(phi) - deg(20)) / deg(130));
	const largeur = (phi, y) => autour(a, y, .09) * (1 - .1 * Math.max(0, Math.cos(phi)));
	const tour = (s, ecart = 0, chute = 0) => phi => {
		const y = haut + s * (bord(phi) - haut) - chute;
		return [y, largeur(phi, y) + ecart];
	};
	const bourrelets = [.42, .64, .84].flatMap(s => [tour(s, .014), tour(s, 0, .012)]);
	const rangs = [rang(haut, 0), tour(.14), tour(.3), ...bourrelets, phi => [bord(phi), autour(a, bord(phi), .04)]];
	const bandes = ['tour0', 'tour0', 'tour0', 'ombre', 'tour1', 'ombre', 'tour2', 'ombre', 'tour1'];
	const g = voile(THREE, rangs, 12, (phi, k) => bandes[k]);
	// Au milieu de la colonne de devant, la face passe à cos(15°) du rayon de ses arêtes
	const y = bord(0), z = autour(a, y, .04) * Math.cos(Math.PI / 12) + .004;
	const pointe = geometrie(THREE, [.06, y + .03, z, -.06, y + .03, z, 0, y - .035, z], [0, 0, 1, 0, 0, 1, 0, 0, 1]);
	return [
		{ geometry: g.tour0, color: p.couleur }, { geometry: g.tour1, color: nuance(THREE, p.couleur, -.06) }, { geometry: g.tour2, color: nuance(THREE, p.couleur, -.12) },
		{ geometry: g.ombre, color: nuance(THREE, p.couleur, -.24) }, { geometry: pointe, color: nuance(THREE, p.couleur, -.24) },
	];
}

const COUVRE_CHEFS = {
	'chapeau-paille': (THREE, a, p) => {
		const c = a.crane + .008, h = a.calotte + .025;
		// Une large passe qui tombe un peu vers le bord, une coiffe ronde, un ruban au pied de la coiffe. Sur l'avatar, douze côtés
		// au lieu de neuf : la corde d'une arête de neuf rentrait de 19 mm, ce que le bord posé sur la tête aurait dû rattraper
		const sides = a.contour ? 12 : 9;
		const coiffe = lathe(THREE, [[c, 0], [c + .2, -.016], [c + .012, .02], [c + .006, h * .72], [c * .8, h], [0, h + .006]], sides);
		const ruban = lathe(THREE, [[c + .014, .022], [c + .009, .075]], sides);
		const fleurs = p.fleur ? fleur(THREE, c, sides, p.fleur) : [];
		if(a.contour) return [{ geometry: coiffe, color: p.couleur }, { geometry: ruban, color: p.ruban }, { geometry: bordSurLaTete(THREE, coiffe, a, c, 0, sides, .02), color: nuance(THREE, p.couleur, -.12) }, ...fleurs];
		return [{ geometry: coiffe, color: p.couleur }, { geometry: ruban, color: p.ruban }, ...fleurs];
	},
	// Faite dans Blender (casquette.blend), dans le repère de l'assise : plus haute devant, le dos en pente. Ses deux formes
	// clés l'ajustent à la tête coiffée : `rayon` est le rayon intérieur de son bord, au milieu d'un pan, celui que la version
	// en JS donne au sien ; `hauteur` met la calotte 2 cm au-dessus de la coiffure. La visière suit le bord sans changer de
	// longueur
	'casquette': (THREE, a, p) => {
		if(aPiece('casquette') === false) return casquetteJs(THREE, a, p);
		const rayon = Math.max((a.crane + .008) * Math.cos(Math.PI / 10), Math.max(a.crane, a.sous(.01)) + .003);
		return depuisBlender(THREE, 'casquette', { rayon, hauteur: a.calotte + .02 }, p).map(m => ({ geometry: m.geometry, color: m.color }));
	},
	'bob': (THREE, a, p) => {
		const c = a.crane + .008, h = a.calotte + .02;
		// Un bord étroit qui descend tout autour, une coiffe droite à peine bombée
		if(a.contour === undefined) return [{ geometry: lathe(THREE, [[c + .1, -.07], [c + .01, 0], [c + .006, h * .7], [c * .82, h], [0, h + .01]], 10), color: p.couleur }];
		// Sur l'avatar, vu de près, le bob prend le relief d'une toile cousue : le bord finit en bourrelet roulé et s'arrondit
		// jusqu'à la coiffe au lieu d'y monter en ligne droite, une bande cousue ceint le pied de la coiffe et une autre le
		// tour du dessus plat, chacune un ton plus sombre que la toile
		const sides = 12, sombre = nuance(THREE, p.couleur, -.2);
		const coiffe = lathe(THREE, [[c + .103, -.08], [c + .114, -.07], [c + .1, -.06], [c + .05, -.018], [c + .012, -.002], [c + .008, .012], [c + .006, h * .72], [c * .84, h], [0, h + .01]], sides);
		const bande = lathe(THREE, [[c + .016, -.001], [c + .012, .03]], sides);
		const dessus = lathe(THREE, [[c * .86, h - .004], [c * .78, h + .004]], sides);
		return [{ geometry: coiffe, color: p.couleur }, { geometry: bande, color: sombre }, { geometry: dessus, color: sombre }];
	},
	'chapeau-feutre': (THREE, a, p) => {
		const c = a.crane + .008, h = a.calotte + .012;
		// Un bord plat et net, plus long que celui d'un bob et plus court que la passe d'un chapeau de paille, une coiffe basse
		// aux flancs droits, creusée au milieu du dessus, un ruban au pied de la coiffe. La coiffe en dôme d'un bob, haute et
		// ronde sur un bord court qui descend, se lisait comme un casque
		const coiffe = lathe(THREE, [[c, -.004], [c + .12, -.01], [c + .126, -.004], [c + .118, 0], [c + .012, .008], [c, h * .7], [c * .88, h + .002], [c * .5, h - .002], [0, h - .006]], 12);
		const ruban = lathe(THREE, [[c + .015, .009], [c + .012, .04]], 12);
		if(a.contour) return [{ geometry: coiffe, color: p.couleur }, { geometry: ruban, color: p.ruban }, { geometry: bordSurLaTete(THREE, coiffe, a, c, -.004, 12, .016), color: nuance(THREE, p.couleur, -.12) }];
		return [{ geometry: coiffe, color: p.couleur }, { geometry: ruban, color: p.ruban }];
	},
	'bonnet': (THREE, a, p) => {
		const c = a.crane + .012, h = a.calotte + .06;
		// Un revers épais qui descend sur le front et les oreilles, une coiffe en dôme qui dépasse du crâne, un gros pompon. Une
		// coiffe qui épousait le crâne se lisait comme un béret plat, et le pompon en est le seul repère à la taille du jeu
		// Le revers descend sous le cercle d'assise, où la tête coiffée s'élargit : il s'écarte de ce qu'il couvre sur toute sa
		// hauteur (`sous()` de corps.js), mesuré au milieu d'un pan, où sa face passe le plus près de l'axe. Taillé sur le seul
		// cercle d'assise, il laissait sortir de 4 mm la nuque d'une coupe rase entre deux de ses arêtes. Il ne s'élargit que de
		// ce qui manque : sur une coiffure qu'il contient déjà, il garde sa forme
		const pans = 10, bas = .075;
		const contenu = Math.max(0, (a.sous(bas) + .003) / Math.cos(Math.PI / pans) - (c + .03));
		// Sur l'avatar (a.contour, que donne l'assise sous `raccords`), une queue de cheval modelée dans Blender sort sous le
		// plan d'assise : au-delà de 3 cm, ce n'est plus la tête coiffée qui s'élargit mais elle, et le revers la laisse passer.
		// Élargi de 9 cm pour l'enfermer, il se lisait comme un chapeau à bord. Les scènes gardent le revers qui contient tout
		const large = a.contour ? Math.min(.03, contenu) : contenu;
		const profilRevers = [[c + .03 + large, -bas], [c + .036 + large, -.045], [c + .03 + large, -.012], [c + .012, -.008]];
		const profilCoiffe = [[c + .012, -.01], [c + .012, h * .3], [c * .93, h * .62], [c * .66, h * .88], [c * .3, h * .99], [0, h]];
		const revers = lathe(THREE, profilRevers, pans);
		const pompon = new THREE.IcosahedronGeometry(.065, 1).translate(0, h + .045, 0);
		if(a.contour === undefined) return [{ geometry: lathe(THREE, profilCoiffe, pans), color: p.couleur }, { geometry: pompon, color: p.pompon ?? p.couleur }, { geometry: revers, color: p.revers ?? p.couleur }];
		// Sur l'avatar, le rang de la coiffe juste sous le pompon, qui le cache, rend ses 20 triangles au bandeau
		const coiffeAvatar = lathe(THREE, profilCoiffe.filter((rang, k) => k !== profilCoiffe.length - 2), pans);
		const bandeau = bonnetSurLaTete(THREE, a, revers, profilRevers, large, coiffeAvatar, profilCoiffe, pans, p.revers ?? p.couleur);
		return [{ geometry: coiffeAvatar, color: p.couleur }, { geometry: pompon, color: p.pompon ?? p.couleur }, { geometry: revers, color: p.revers ?? p.couleur }, bandeau];
	},
	// Les couvre-chefs qui suivent sont faits dans Blender, et les scènes en gardent une forme en JS. Le béret, le chapeau de
	// cow-boy, le chapeau conique, le chapeau de brousse et la casquette plate se posent sur la coiffure comme un chapeau et
	// empruntent celle d'un couvre-chef voisin ; le bandeau se ceint autour d'elle, et les cinq autres prennent toute la tête
	'beret': chefPiece('beret', (THREE, a, p) => COUVRE_CHEFS['bob'](THREE, a, p)),
	'cow-boy': chefPiece('cow-boy', (THREE, a, p) => COUVRE_CHEFS['chapeau-feutre'](THREE, a, p)),
	'chapeau-conique': chefPiece('chapeau-conique', (THREE, a, p) => COUVRE_CHEFS['chapeau-paille'](THREE, a, { couleur: p.couleur, ruban: p.jugulaire })),
	'bandeau-frontal': chefPiece('bandeau-frontal', bandeauJs, { ceint: true }),
	'hijab-travail': chefPiece('hijab-travail', hijabJs, TETE_COUVERTE),
	'fichu': chefPiece('fichu', fichuJs, TETE_COUVERTE),
	'chapeau-brousse': chefPiece('chapeau-brousse', (THREE, a, p) => COUVRE_CHEFS['chapeau-feutre'](THREE, a, p)),
	'casquette-plate': chefPiece('casquette-plate', (THREE, a, p) => COUVRE_CHEFS['casquette'](THREE, a, { couleur: p.couleur, visiere: p.couleur })),
	'charlotte': chefPiece('charlotte', charlotteJs, TETE_COUVERTE),
	'cagoule': chefPiece('cagoule', cagouleJs, TETE_COUVERTE),
	'turban': chefPiece('turban', turbanJs, TETE_COUVERTE),
};

const OBJETS = {
	'rateau': (THREE, a) => {
		// Tenu debout, la tête au sol, les dents vers l'avant, le manche un peu devant le poing pour passer devant le bras
		const x = a.dehors * .005, z = .045, s = a.sol;
		const geos = [boite(THREE, .03, .035, .36, x, s + .06, z + .04)];
		for(const dz of [-.12, -.04, .04, .12]) geos.push(boite(THREE, .012, .045, .012, x, s + .022, z + .04 + dz));
		return [{ geometry: manche(THREE, x, z, s + .07, .62), color: BOIS }, { geometry: merge(THREE, geos), color: ENCRE }];
	},
	'binette': (THREE, a) => {
		const x = a.dehors * .005, z = .045, s = a.sol;
		const lame = boite(THREE, .012, .09, .13, x, s + .045, z + .03);
		const virole = lathe(THREE, [[.019, s + .09], [.019, s + .13]], 5).translate(x, 0, z);
		return [{ geometry: manche(THREE, x, z, s + .09, .62), color: BOIS }, { geometry: lame, color: METAL }, { geometry: virole, color: ENCRE }];
	},
	'panier': (THREE, a) => {
		// Un panier oblong qui pend d'aplomb sous le poing, écarté du flanc, son anse passe dans la main
		const x = a.dehors * .025, z = .035;
		const corps = lathe(THREE, [[0, -.31], [.85, -.31], [1, -.13], [.9, -.13], [.78, -.28], [0, -.28]], 7).scale(.065, 1, .125).translate(x, 0, z);
		const points = Array.from({ length: 6 }, (_, i) => {
			const t = i / 5 * Math.PI;
			return [x, -.14 + .15 * Math.sin(t), z + .105 * Math.cos(t)];
		});
		return [{ geometry: merge(THREE, [corps, anse(THREE, points, .008)]), color: '#b5834e' }];
	},
	'arrosoir': (THREE, a) => {
		// Le corps pend sous le poing, l'anse passe au-dessus, le bec part vers l'avant et se finit en pomme
		const x = a.dehors * .035, z = 0;
		const corps = lathe(THREE, [[0, -.33], [.075, -.33], [.08, -.2], [.058, -.13], [0, -.12]], 7).translate(x, 0, z);
		const points = Array.from({ length: 5 }, (_, i) => {
			const t = i / 4 * Math.PI;
			return [x, -.13 + .135 * Math.sin(t), z + .05 * Math.cos(t)];
		});
		const debut = new THREE.Vector3(x, -.29, z + .06), fin = new THREE.Vector3(x, -.15, z + .2);
		const bec = anse(THREE, [debut.toArray(), fin.toArray()], .012);
		const pomme = lathe(THREE, [[.012, 0], [.03, .028], [0, .03]], 4);
		pomme.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), fin.clone().sub(debut).normalize()));
		pomme.translate(fin.x, fin.y, fin.z);
		return [{ geometry: merge(THREE, [corps, anse(THREE, points, .008), bec, pomme]), color: '#2fbb75' }];
	},
	'tomate': (THREE, a) => {
		// Une grosse tomate côtelée tenue devant le poing, côté dehors, la paume dessous, son collet vert tourné vers l'avant, là
		// où il se voit. Elle touche la boule de la main sans y entrer, et passe au large de la cuisse
		const x = a.dehors * .025, y = -.03, z = .09;
		const fruit = new THREE.SphereGeometry(1, 7, 4).toNonIndexed();
		fruit.deleteAttribute('uv');
		const p = fruit.attributes.position;
		for(let i = 0; i < p.count; i++) {
			const vx = p.getX(i), vy = p.getY(i), cote = 1 + .08 * Math.cos(4 * Math.atan2(vy, vx)) * Math.hypot(vx, vy);
			p.setXYZ(i, vx * cote, vy * cote, p.getZ(i));
		}
		fruit.computeVertexNormals();
		fruit.scale(.064, .064, .052).translate(x, y, z);
		const collet = Array.from({ length: 3 }, (_, i) => {
			const t = i / 3 * Math.PI * 2;
			return feuille(THREE, [x, y, z + .05], [x + Math.sin(t) * .04, y + Math.cos(t) * .037, z + .043], .016);
		});
		return [{ geometry: fruit, color: '#d8352a' }, { geometry: merge(THREE, collet), color: VERT }];
	},
	'navet': (THREE, a) => {
		// Tenu par les fanes : la racine pend sous le poing, rose au collet et blanche en bas, les feuilles montent au-dessus
		const x = a.dehors * .01, z = .03;
		const blanc = lathe(THREE, [[0, -.22], [.02, -.205], [.045, -.17], [.052, -.14]], 6).translate(x, 0, z);
		const rose = lathe(THREE, [[.052, -.14], [.042, -.11], [.015, -.085], [0, -.08]], 6).translate(x, 0, z);
		const tiges = anse(THREE, [[x, -.085, z], [x, .02, z]], .01);
		const fanes = [[-.05, .2, .02], [.03, .23, .04], [.06, .18, -.02]].map(([dx, dy, dz]) => feuille(THREE, [x, .01, z], [x + dx * a.dehors, dy, z + dz], .07));
		return [{ geometry: blanc, color: '#efe8da' }, { geometry: rose, color: '#c2577a' }, { geometry: merge(THREE, [tiges, ...fanes]), color: '#4d9a4a' }];
	},
	'lunettes-pliees': (THREE, a) => {
		// Une paire de lunettes repliée, pincée par les branches dans le poing et tendue vers l'avant : les deux cercles se
		// recouvrent à moitié devant la main, dans le plan du flanc, là où la vue de trois quarts les montre. Tendues vers l'avant
		// de la main, elles se relèvent quand le bras se lève, accroupi ou assis la main sur le genou, au lieu de pendre contre
		// le tibia ou dans la chaussure ; petites et face à l'avant, sur les jointures, elles se lisaient comme une montre.
		// Posées au sol, couchées vers le dehors, elles s'y étendent à plat. Les verres clairs sous la monture sombre font lire
		// des lunettes plutôt que deux anneaux
		const x = a.dehors * .03, y = .012, r = .028, cercles = [], verres = [];
		const tourne = g => g.rotateY(Math.PI / 2);
		// Six côtés et non huit : une scène peut mettre en plus un outil dans la même main, que le plafond de 2 000
		// triangles doit contenir, et sur 6 cm de large un verre hexagonal ne se distingue pas d'un octogonal. Les pointes
		// de l'hexagone vont vers l'avant et l'arrière, ses plats en haut et en bas : un verre plus large que haut
		const COTES = 6;
		// La boule de la main a 6,4 cm de rayon : le premier cercle part à 7 cm du milieu de la paume
		for(const [z, dx, dy] of [[.098, 0, 0], [.128, a.dehors * .006, -.006]]) {
			const c = tourne(new THREE.TorusGeometry(r, .005, 3, COTES).scale(1.1, .82, 1)).translate(x + dx, y + dy, z);
			const v = tourne(new THREE.CircleGeometry(r - .002, COTES).scale(1.1, .82, 1)).translate(x + dx - a.dehors * .001, y + dy, z);
			for(const g of [c, v]) g.deleteAttribute('uv');
			cercles.push(c);
			verres.push(v);
		}
		// Les branches repliées, de l'arrière des cercles jusque dans le poing
		const branches = [.012, -.012].map(dy => boite(THREE, .004, .004, .06, x, y + dy, .055));
		return [{ geometry: merge(THREE, [...cercles, ...branches]), color: a.couleur }, { geometry: merge(THREE, verres), color: '#c8d4d8' }];
	},
};

const BIJOUX = {
	// Une perle qui pend sous chaque oreille
	'boucles-oreilles': (THREE, a, p) => [{
		name: 'bijoux', parent: 'tete',
		geometry: merge(THREE, a.oreilles.map(o => {
			const axe = new THREE.Vector3(0, 1, 0).transformDirection(a.tete.M), dehors = o.clone().sub(a.tete.centre).normalize();
			const c = o.clone().addScaledVector(axe, -.07).addScaledVector(dehors, .012);
			return new THREE.OctahedronGeometry(.016, 0).translate(c.x, c.y, c.z);
		})),
		color: p.couleur,
	}],
	// Un anneau qui passe par le bord de la lèvre inférieure, au milieu, à moitié dans la peau : un clou de 9 mm se lisait à
	// cette taille comme une tache qu'on ne savait pas nommer. Il porte le rôle `levre` : une émotion le redemande avec la
	// descente du bas de sa bouche (`levre`), pour qu'il reste au bord de la lèvre ouverte ou remontée
	'piercing-levre': (THREE, a, p, levre = 0) => {
		const o = a.yeux.poser(0, a.bouche.y - .022 + levre, 0);
		const anneau = new THREE.TorusGeometry(.012, .0032, 3, 8).rotateY(Math.PI / 2).applyMatrix4(new THREE.Matrix4().extractRotation(a.tete.M)).translate(o.p.x, o.p.y, o.p.z);
		anneau.deleteAttribute('uv');
		return [{ name: 'bijoux', parent: 'tete', geometry: anneau, color: p.couleur, role: 'levre' }];
	},
	// Un médaillon sur la poitrine, au bout d'un cordon qui fait le tour du cou, posé sur le haut quand il y en a un
	'pendentif': (THREE, a, p) => {
		const { yEpaule } = a.corps, ecart = (a.ecartHaut ?? 0) + .006;
		const cordon = [1, -1].map(c => bande(THREE, a, c * .9, yEpaule - .01, c * .08, yEpaule - .065, .004, ecart));
		return [
			{ name: 'pendentif', parent: 'torse', geometry: merge(THREE, cordon), color: p.cordon },
			{ name: 'pendentif', parent: 'torse', geometry: disque(THREE, a, 0, yEpaule - .08, .022, ecart + .002), color: p.couleur },
		];
	},
};

// L'écharpe en bandoulière : un ruban de trois bandes, de la hanche gauche à l'épaule droite par le devant, puis par le dos
// jusqu'au flanc gauche, qu'il longe sous le bras pour revenir à la hanche, où une cocarde le ferme et d'où pend le gland.
// Les trois bandes partagent leurs bords : posées côte à côte chacune de son côté, elles se chevauchaient là où le ruban
// descend en biais, et chaque tronçon calculait sa largeur pour lui seul, d'où des raccords ouverts en créneaux et un bout
// déchiqueté là où le ruban tourne sur l'épaule
const ECHARPES = {
	'echarpe': (THREE, a, p) => {
		const { yEpaule, yTaille, surCorps } = a.corps, ecartHaut = a.ecartHaut ?? 0;
		const point = (az, y, e) => new THREE.Vector3(...surCorps(az, y, e));
		const yHanche = yTaille - .02, cocarde = [.95, yHanche];
		// Le tracé de l'axe en [azimut, hauteur]. Sur l'épaule, le ruban passe sur le plat entre le col et la manche : plus
		// bas sur le côté, la tête de manche le perçait. Le long du flanc gauche, il passe à l'horizontale à hauteur de la
		// cocarde : le bras qui pend le couvre, et ce qui en dépasse devant et derrière est à la même hauteur, donc se lit comme
		// un ruban qui passe dessous. En biais, il ressortait du bras à deux hauteurs et se lisait comme un bras qui le traverse
		const jalons = [
			cocarde,
			[-.35, yEpaule - .17],
			[-1.05, yEpaule - .03],
			[-Math.PI / 2, yEpaule - .001],
			[-2.1, yEpaule - .03],
			[-2.8, yEpaule - .17],
			[-3.55, yHanche + .06],
			[-4.15, yHanche + .004],
			[cocarde[0] - Math.PI * 2, yHanche],
		];
		// La courbe passe par les jalons (Catmull-Rom), mesurée sur le corps
		const fin = 400, dense = [];
		for(let k = 0; k <= fin; k++) {
			const t = k / fin * (jalons.length - 1), i = Math.min(jalons.length - 2, Math.floor(t)), u = t - i;
			const [p0, p1, p2, p3] = [jalons[Math.max(0, i - 1)], jalons[i], jalons[i + 1], jalons[Math.min(jalons.length - 1, i + 2)]];
			dense.push([0, 1].map(c => .5 * (2 * p1[c] + (p2[c] - p0[c]) * u + (2 * p0[c] - 5 * p1[c] + 4 * p2[c] - p3[c]) * u * u + (3 * p1[c] - p0[c] - 3 * p2[c] + p3[c]) * u * u * u)));
		}
		const longueurs = [0];
		for(let k = 1; k <= fin; k++) longueurs.push(longueurs[k - 1] + point(...dense[k], ecartHaut).distanceTo(point(...dense[k - 1], ecartHaut)));
		const L = longueurs[fin], aJalon = j => longueurs[Math.round(fin * j / (jalons.length - 1))];
		const sEpaule = aJalon(3);
		const cloche = (s, centre, largeur) => Math.exp(-(((s - centre) / largeur) ** 2));
		// Les pas se resserrent sur l'épaule, de 7 à 3 cm : le ruban y franchit en biais l'arête de l'épaule marquée, et une corde
		// de 7 cm passait sous elle d'un centimètre, qui perçait le ruban en points sombres
		const poids = s => 1 + 1.6 * cloche(s, sEpaule, .12), cumul = [0];
		for(let k = 1; k <= fin; k++) cumul.push(cumul[k - 1] + (longueurs[k] - longueurs[k - 1]) * poids((longueurs[k] + longueurs[k - 1]) / 2));
		const N = 26, axe = [];
		for(let i = 0, k = 0; i <= N; i++) {
			const c = cumul[fin] * i / N;
			while(k < fin - 1 && cumul[k + 1] < c) k++;
			const u = (c - cumul[k]) / (cumul[k + 1] - cumul[k]);
			axe.push({ az: dense[k][0] + (dense[k + 1][0] - dense[k][0]) * u, y: dense[k][1] + (dense[k + 1][1] - dense[k][1]) * u, s: longueurs[k] + (longueurs[k + 1] - longueurs[k]) * u });
		}
		// La demi-largeur : 3,9 cm, resserrée à 1,6 sur l'épaule, où le ruban se plisse entre le col et la tête de manche, et à
		// 2,8 vers la cocarde, qui en couvre ainsi les deux bouts
		const demi = s => .039 - .023 * cloche(s, sEpaule, .09) - .011 * (cloche(s, 0, .06) + cloche(s, L, .06));
		// La hauteur du ruban sur le corps suit le dessus du vêtement, rang par rang (`profil` du haut ou de la veste) : posé à
		// l'écart le plus haut de la veste, celui de l'épaule marquée, il flottait à 1,6 cm au-dessus de la poitrine et du dos,
		// se décollait de la silhouette sur le flanc et dépassait de l'épaule en pointe. Le jeu de 6 mm se compte le long de la
		// normale du vêtement : sur le plat de l'épaule, où le profil s'ouvre presque à l'horizontale, un jeu compté en rayon
		// ne relève presque pas le ruban, et l'arête de l'épaule marquée le perçait en points sombres
		const { rayon } = a.corps, rangs = new Map();
		for(const [r, y] of a.dessus?.profil ?? []) if(r > 0) rangs.set(y, Math.max(r, rangs.get(y) ?? 0));
		const profil = [...rangs].sort(([y0], [y1]) => y0 - y1);
		const rayonDessus = y => {
			if(profil.length < 2) return rayon(y) + ecartHaut;
			const k = Math.max(1, profil.findIndex(([yk]) => yk >= y));
			const [[y0, r0], [y1, r1]] = [profil[k - 1], profil[k]];
			return r0 + (r1 - r0) * (y - y0) / (y1 - y0);
		};
		// Au-delà du profil, le ruban garde l'écart du dernier rang : le bord du côté du cou monte au-dessus du col sur le plat
		// de l'épaule, et le rayon du col comparé à celui du cou qui rentre l'écartait de plusieurs centimètres, en pointes
		const [yBas, yHaut] = profil.length < 2 ? [-Infinity, Infinity] : [profil[0][0], profil.at(-1)[0]];
		const ecartDessus = y => { const c = Math.min(yHaut, Math.max(yBas, y)); return rayonDessus(c) - rayon(c); };
		// Entre deux pas, le ruban tend une corde : sur l'arête de l'épaule marquée, ses deux bouts posés de part et d'autre la
		// passaient dessous. Au-dessus de la poitrine, le ruban prend donc le plus haut du vêtement à 3 cm autour de sa hauteur ;
		// plus bas le profil ne fait plus d'arête, et le même maximum le décollait de la taille sur l'évasement de l'ourlet
		const { yPoitrine } = a.corps;
		const dessus = y => Math.max(...[-1, -.5, 0, .5, 1].map(k => y + .03 * k).filter(yk => yk >= yPoitrine || yk === y).map(ecartDessus));
		const jeu = y => {
			const c = Math.min(yHaut - .005, Math.max(yBas + .005, y));
			const pente = [-.005, .005].reduce((somme, dy) => somme + Math.abs(rayonDessus(c + dy) - rayonDessus(c)) / .005, 0) / 2;
			return .006 * Math.min(2, Math.sqrt(1 + pente * pente));
		};
		// Les revers, la cravate dans le V de l'ouverture et les boutons se posent au-dessus du drap de la veste, de 6, 19 et 4 mm
		// plus leur bombé : le ruban s'en relève, avec 1,5 cm de pente autour pour ne pas monter en marche, 3 cm autour d'un bouton,
		// qu'un bord du ruban posé à côté laissait percer par sa corde
		const carre = (az, y, rd) => { const da = rd / rayon(y); return [[az - da, y - rd], [az + da, y - rd], [az + da, y + rd], [az - da, y + rd]]; };
		const surelevations = a.dessus?.revers ? [
			[revers(a, 1), .006, .015], [revers(a, -1), .006, .015],
			[(v => [[0, v.bas], [v.demi, v.haut], [-v.demi, v.haut]])(ouverture(a)), .019, .015],
			...boutons(a).map(([az, y, rd]) => [carre(az, y, rd), .008, .03]),
		] : [];
		const distance = (az, y, contour) => {
			const m = rayon(y), P = [az * m, y];
			let dedans = false, d = Infinity;
			for(let i = 0; i < contour.length; i++) {
				const [A, B] = [contour[i], contour[(i + 1) % contour.length]].map(([x, yy]) => [x * m, yy]);
				if((A[1] > y) !== (B[1] > y) && P[0] < A[0] + (B[0] - A[0]) * (y - A[1]) / (B[1] - A[1])) dedans = !dedans;
				const AB = [B[0] - A[0], B[1] - A[1]], t = Math.max(0, Math.min(1, ((P[0] - A[0]) * AB[0] + (P[1] - A[1]) * AB[1]) / (AB[0] ** 2 + AB[1] ** 2)));
				d = Math.min(d, Math.hypot(P[0] - A[0] - t * AB[0], P[1] - A[1] - t * AB[1]));
			}
			return dedans ? 0 : d;
		};
		// Le départ du devant passe 4 mm au-dessus du retour du dos, qu'il recouvre sous la cocarde
		const ecart = (az, y, s) => dessus(y) + jeu(y) + .004 * cloche(s, 0, .08)
			+ Math.max(0, ...surelevations.map(([contour, h, pente]) => h * Math.max(0, 1 - distance(az, y, contour) / pente)));
		// Un point du ruban : décalé de d en travers de l'axe, mesuré sur le corps, puis reposé sur lui. Le décalage se fait
		// dans le plan tangent puis se reprojette par trois pas de Gauss-Newton, pour que la largeur reste vraie sur le plat de
		// l'épaule, où une hauteur de plus éloigne vite du cou. Le point reposé prend la hauteur du vêtement sous lui
		const h = 1e-4;
		const derivees = (az, y, e) => {
			const P = point(az, y, e);
			return { P, Ja: point(az + h, y, e).sub(P).divideScalar(h), Jy: point(az, y + h, e).sub(P).divideScalar(h) };
		};
		const surface = (az, y, e) => {
			const { P, Ja, Jy } = derivees(az, y, e);
			const n = new THREE.Vector3().crossVectors(Jy, Ja).normalize();
			return { P, n: n.dot(new THREE.Vector3(P.x, 0, P.z)) < 0 ? n.negate() : n };
		};
		const poser = (az, y, e, cible, s) => {
			for(let n = 0; n < 3; n++) {
				const { P, Ja, Jy } = derivees(az, y, e), r = cible.clone().sub(P);
				const aa = Ja.dot(Ja), ay = Ja.dot(Jy), yy = Jy.dot(Jy), det = aa * yy - ay * ay;
				const ba = Ja.dot(r), by = Jy.dot(r);
				az += (yy * ba - ay * by) / det;
				y += (aa * by - ay * ba) / det;
			}
			return surface(az, y, ecart(az, y, s));
		};
		// Les quatre bords, du côté du cou vers l'autre : le bleu au col, comme le porte un maire
		const rails = [[], [], [], []];
		let sens = 0;
		for(const [i, c] of axe.entries()) {
			const e = ecart(c.az, c.y, c.s), centre = surface(c.az, c.y, e);
			const avant = axe[Math.min(N, i + 1)], arriere = axe[Math.max(0, i - 1)];
			const T = point(avant.az, avant.y, e).sub(point(arriere.az, arriere.y, e));
			const B = new THREE.Vector3().crossVectors(centre.n, T).normalize();
			// Le côté du cou se lit au milieu du devant : celui qui monte
			if(sens === 0 && i >= N / 6) sens = B.y > 0 ? 1 : -1;
			for(const [j, f] of [1, 1 / 3, -1 / 3, -1].entries()) rails[j].push({ az: c.az, y: c.y, s: c.s, e, B, f, d: demi(c.s) });
		}
		const bords = rails.map(rail => rail.map(({ az, y, s, e, B, f, d }) => poser(az, y, e, point(az, y, e).addScaledVector(B, sens * f * d), s)));
		// Chaque bande tourne ses faces vers le dehors, triangle par triangle, d'après la normale du corps sous lui : sur le plat de
		// l'épaule, un quad se gauchit assez pour que ses deux triangles ne regardent pas du même côté
		const bandes = p.couleurs.map((couleur, k) => {
			const pos = [], nor = [];
			for(let i = 0; i < N; i++) {
				const q = [bords[k][i], bords[k + 1][i], bords[k + 1][i + 1], bords[k][i + 1]];
				for(const tri of [[0, 1, 2], [0, 2, 3]]) {
					const [A, B, C] = tri.map(o => q[o]), n = A.n.clone().add(B.n).add(C.n);
					const face = new THREE.Vector3().crossVectors(B.P.clone().sub(A.P), C.P.clone().sub(A.P));
					for(const o of face.dot(n) > 0 ? [A, B, C] : [A, C, B]) { pos.push(...o.P.toArray()); nor.push(...o.n.toArray()); }
				}
			}
			return { geometry: geometrie(THREE, pos, nor), color: couleur };
		});
		// La cocarde : trois disques empilés, bleu au centre, blanc, rouge au bord, posés à plat sur la hanche par-dessus les
		// deux bouts du ruban. Le gland pend dessous, collé au vêtement : posé à deux centimètres du corps, il flottait devant
		// les genoux dès que le buste penche
		const eCocarde = ecart(...cocarde, 0) + .004;
		const disqueA = (rd, dessus, couleur) => {
			const { P: c, n } = surface(...cocarde, eCocarde + dessus);
			const u = new THREE.Vector3(0, 1, 0).cross(n).normalize(), v = new THREE.Vector3().crossVectors(n, u);
			const pos = [], nor = [], seg = 8;
			for(let i = 0; i < seg; i++) {
				const [t0, t1] = [i, i + 1].map(j => j / seg * Math.PI * 2);
				const p0 = c.clone().addScaledVector(u, Math.cos(t0) * rd).addScaledVector(v, Math.sin(t0) * rd);
				const p1 = c.clone().addScaledVector(u, Math.cos(t1) * rd).addScaledVector(v, Math.sin(t1) * rd);
				const tri = [c, p0, p1], face = new THREE.Vector3().crossVectors(p0.clone().sub(c), p1.clone().sub(c));
				for(const q of face.dot(n) > 0 ? tri : [c, p1, p0]) { pos.push(...q.toArray()); nor.push(...n.toArray()); }
			}
			return { geometry: geometrie(THREE, pos, nor), color: couleur };
		};
		const [bleu, blanc, rouge] = p.couleurs;
		// Le gland se pose sur le vêtement, à son propre rayon de distance : sous la taille, le corps rentre vers l'entrejambe alors
		// que la veste garde la largeur des hanches, et un gland posé sur le corps y plongeait à moitié
		const yGland = cocarde[1] - .05;
		const [gx, gy, gz] = surCorps(cocarde[0], yGland, ecart(cocarde[0], yGland, 0) + .02);
		const gland = new THREE.CylinderGeometry(.008, .022, .055, 6).translate(gx, gy, gz);
		gland.deleteAttribute('uv');
		return [...bandes, disqueA(.034, 0, rouge), disqueA(.023, .002, blanc), disqueA(.012, .004, bleu), { geometry: gland, color: p.gland }];
	},
};

// Une toile sur une grille de points point(i, k), i en travers et k en long, chaque triangle tourné vers le dehors : loin de
// l'axe du corps, ou du côté de `dehors(P)` quand la toile n'entoure pas le corps. Les scènes ne dessinent que les faces avant,
// et l'ordre des points ne dit pas d'avance de quel côté regarde un triangle. Les normales sont lissées sur la grille
function toile(THREE, nI, nK, point, dehors = P => [P[0], 0, P[2]]) {
	const pos = [], index = [];
	for(let i = 0; i <= nI; i++) for(let k = 0; k <= nK; k++) pos.push(...point(i, k));
	const P = j => [pos[j * 3], pos[j * 3 + 1], pos[j * 3 + 2]];
	const tourne = (A, B, C) => {
		const [a, b, c] = [P(A), P(B), P(C)];
		const u = [b[0] - a[0], b[1] - a[1], b[2] - a[2]], v = [c[0] - a[0], c[1] - a[1], c[2] - a[2]];
		const n = [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
		const m = [(a[0] + b[0] + c[0]) / 3, (a[1] + b[1] + c[1]) / 3, (a[2] + b[2] + c[2]) / 3], d = dehors(m);
		return n[0] * d[0] + n[1] * d[1] + n[2] * d[2] >= 0 ? [A, B, C] : [A, C, B];
	};
	for(let i = 0; i < nI; i++) for(let k = 0; k < nK; k++) {
		const s = i * (nK + 1) + k, t = (i + 1) * (nK + 1) + k;
		index.push(...tourne(s, t, t + 1), ...tourne(s, t + 1, s + 1));
	}
	const g = new THREE.BufferGeometry();
	g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
	g.setIndex(index);
	g.computeVertexNormals();
	return g.toNonIndexed();
}

// Une sangle posée sur le corps le long de jalons [azimut, hauteur], de demi-largeur l, à l'écart e du corps
// Les jalons se resserrent à `pas` d'azimut ou de hauteur : entre deux jalons éloignés, la sangle tendait une corde qui passait
// sous les facettes du vêtement et ne se voyait plus que par tronçons
function sangle(THREE, a, lointains, l, e, pas = .045) {
	const { surCorps, rayon } = a.corps, jalons = [lointains[0]];
	for(let j = 1; j < lointains.length; j++) {
		const [az0, y0] = lointains[j - 1], [az1, y1] = lointains[j];
		const n = Math.max(1, Math.ceil(Math.max(Math.abs(az1 - az0) * rayon(y0) / pas, Math.abs(y1 - y0) / pas)));
		for(let k = 1; k <= n; k++) jalons.push([az0 + (az1 - az0) * k / n, y0 + (y1 - y0) * k / n]);
	}
	return toile(THREE, jalons.length - 1, 1, (i, k) => {
		const [az, y] = jalons[i], [az0, y0] = jalons[Math.max(0, i - 1)], [az1, y1] = jalons[Math.min(jalons.length - 1, i + 1)];
		const r = rayon(y), du = (az1 - az0) * r, dv = y1 - y0, n = Math.hypot(du, dv) || 1, s = k === 0 ? -1 : 1;
		return surCorps(az + s * (-dv / n) * l / r, y + s * (du / n) * l, e);
	});
}

// Un solide de révolution, sans coordonnées de texture
const tour = (THREE, profil, cotes) => {
	const g = new THREE.LatheGeometry(profil.map(([r, y]) => new THREE.Vector2(Math.max(r, 0), y)), cotes);
	g.deleteAttribute('uv');
	return g;
};
const pave = (THREE, l, h, p) => {
	const g = new THREE.BoxGeometry(l, h, p);
	g.deleteAttribute('uv');
	return g;
};

// Le repère d'un objet posé sur le corps à l'azimut az : x le long du flanc, y vers le haut, z vers le dehors
const repere = (THREE, a, az, y, e) => new THREE.Matrix4().makeBasis(
	new THREE.Vector3(Math.cos(az), 0, -Math.sin(az)), new THREE.Vector3(0, 1, 0), new THREE.Vector3(Math.sin(az), 0, Math.cos(az)),
).setPosition(...a.corps.surCorps(az, y, e));

// L'écart au corps de ce qui se pose sur le haut : celui du plus épais des hauts et vestes portés, plus un jeu
const surHaut = (a, jeu) => Math.max(a.ecartHaut ?? 0, .008) + jeu;

// Une ceinture tourne sur les seize azimuts des facettes du corps et du vêtement : sur un autre pas, ses cordes passaient
// sous leurs arêtes
const ceinturer = (THREE, a, y, h, e) => toile(THREE, 16, 1, (i, k) => a.corps.surCorps(i / 16 * Math.PI * 2, y - h / 2 + h * k, e));

// Les gants de travail : sur chaque main, un gant un peu plus gros qu'elle, tourné sur l'axe de l'avant-bras, et sa
// manchette évasée au poignet, d'un ton plus sombre. Ils retirent la main nue qu'ils couvrent (zone `main`). Faits dans
// Blender (gants.blend) pour les cibles dont pieces.js les porte, sur le corps commun et ses formes clés
function gants(THREE, a, p) {
	const parts = [];
	if(aPiece('gants')) {
		const teintes = { couleur: p.couleur, manchette: p.manchette ?? nuance(THREE, p.couleur, -.12) };
		for(const m of depuisBlender(THREE, 'gants', a.morphologie, teintes)) parts.push({ parent: m.os, pivot: a.bras[m.os === 'avantBrasG' ? 'brasG' : 'brasD'].E.toArray(), geometry: m.geometry, color: m.color });
	}
	else for(const cote of ['brasG', 'brasD']) {
		const b = a.bras[cote], ra = b.ra;
		// Le gant part de sous la manchette, qui cache son bord : ni l'un ni l'autre n'a besoin d'un rang qui rentre vers l'axe
		const gant = [[ra * .86 + .008, b.L - .04], [ra * 1.14 + .007, b.L + .035], [ra * .78 + .007, b.L + .09], [0, b.L + .114]];
		const manchette = [[ra * .98 + .012, b.L - .085], [ra * .98 + .016, b.L - .03], [ra * .9 + .008, b.L - .026]];
		for(const [profil, couleur] of [[gant, p.couleur], [manchette, p.manchette ?? nuance(THREE, p.couleur, -.12)]]) {
			const g = Object.values(grille(THREE, rangsAutour(THREE, profil, b.S, b.A, b.axe), b.S, k => k < profil.length - 1 ? 'g' : null));
			parts.push({ parent: b.avant, pivot: b.E.toArray(), geometry: merge(THREE, g), color: couleur });
		}
	}
	parts.couvre = ['main'];
	return parts;
}

// Les accessoires : un seul par personne, porté sur soi à son propre emplacement, jamais tenu en main. Ils se posent sur le
// corps habillé, à l'écart du plus épais des hauts et vestes (`ecartHaut`). Ce qui pend à la hanche va du côté droit de la
// personne (x négatif), parce que l'outil de la scène est toujours dans la main gauche
const ACCESSOIRES = {
	// Le tablier à bavette : la bavette sur la poitrine, retenue au cou par deux bretelles, la jupe du tablier jusqu'à mi-cuisse
	// avec sa poche, et le cordon qui fait le tour de la taille. La jupe pend du bassin, qui ne plie pas, et la bavette suit le
	// torse quand le buste penche
	'tablier': (THREE, a, p) => {
		const c = a.corps, e = surHaut(a, .008), yHaut = c.yPoitrine + .045, yBas = c.yHanche - .2;
		const bavette = toile(THREE, 4, 3, (i, k) => {
			const v = k / 3, y = c.yTaille + (yHaut - c.yTaille) * v, demi = .66 + (.46 - .66) * v;
			return c.surCorps((-1 + 2 * i / 4) * demi, y, e + .008);
		});
		// Sous la taille, le tronc se referme vers l'entrejambe : la jupe garde la largeur des hanches et s'évase un peu vers le
		// bas, pour passer devant les cuisses
		const rJupe = (y, jeu) => c.rayon(c.yTaille) + e + .012 + .045 * (c.yTaille - y) / (c.yTaille - yBas) + jeu;
		const pointJupe = (az, y, jeu = 0) => [Math.sin(az) * rJupe(y, jeu), y, Math.cos(az) * rJupe(y, jeu) * c.aplati(y)];
		const jupe = toile(THREE, 6, 2, (i, k) => pointJupe((-1 + 2 * i / 6) * .8, c.yTaille + .012 + (yBas - c.yTaille - .012) * k / 2));
		const poche = toile(THREE, 2, 1, (i, k) => pointJupe((-1 + 2 * i / 2) * .36, c.yTaille - .07 - .11 * k, .004));
		const cordon = ceinturer(THREE, a, c.yTaille, .024, e);
		const bretelles = [1, -1].map(s => sangle(THREE, a, [[s * .44, yHaut - .01], [s * .36, c.yEpaule - .03], [s * .26, c.yCou - .004]], .014, e + .003));
		return [
			{ parent: 'torse', geometry: merge(THREE, [bavette, cordon, ...bretelles]), color: p.couleur },
			{ parent: 'bassin', geometry: jupe, color: p.couleur },
			{ parent: 'bassin', geometry: poche, color: nuance(THREE, p.couleur, -.08) },
		];
	},

	// Le foulard noué au cou : un rouleau autour du cou, sous la tête, et la pointe du carré plié qui tombe sur la poitrine,
	// semée de pois clairs. Les pois font lire un tissu imprimé plutôt qu'une bavette
	'foulard': (THREE, a, p) => {
		const c = a.corps, e = surHaut(a, .008), yHaut = c.yEpaule - .002, yPointe = c.yPoitrine - .015;
		const rouleau = tour(THREE, [[c.rayon(c.yEpaule - .02) + e, c.yEpaule - .03], [c.rayon(c.yEpaule) + e + .022, c.yEpaule - .005], [.128 + e, c.yCou - .012], [.1 + e * .5, c.yCou + .004]], 8);
		// La pointe : un triangle qui suit la poitrine, ses bords tombant du rouleau jusqu'à la pointe
		const pointe = toile(THREE, 4, 2, (i, k) => {
			const v = k / 2, y = yHaut + (yPointe - yHaut) * v, demi = .62 * (1 - v) + .02 * v;
			return c.surCorps((-1 + 2 * i / 4) * demi, y, e + .006 + .008 * (1 - v));
		});
		const pois = [[-.22, c.yEpaule - .05], [.2, c.yEpaule - .06], [0, c.yEpaule - .1]].map(([az, y]) => {
			const g = new THREE.CircleGeometry(.014, 5);
			g.deleteAttribute('uv');
			return g.applyMatrix4(repere(THREE, a, az, y, e + .009 + .008 * (1 - (yHaut - y) / (yHaut - yPointe)) + .002));
		});
		return [
			{ parent: 'torse', geometry: merge(THREE, [rouleau, pointe]), color: p.couleur },
			{ parent: 'torse', geometry: merge(THREE, pois), color: p.pois },
		];
	},

	// L'écharpe de laine : un gros tour de cou, et deux pans rayés qui pendent devant, l'un plus long que l'autre, finis par
	// une frange
	'echarpe-laine': (THREE, a, p) => {
		const c = a.corps, e = surHaut(a, .01);
		const tourCou = tour(THREE, [[c.rayon(c.yEpaule - .05) + e, c.yEpaule - .06], [c.rayon(c.yEpaule - .03) + e + .045, c.yEpaule - .03], [.16 + e + .03, c.yCou - .01], [.11 + e, c.yCou + .012]], 8);
		const geos = { fond: [tourCou], bande: [] };
		// Chaque pan : une face devant et une tranche du côté du dehors, rayé d'une bande sur deux
		for(const [az, yBas, lift] of [[.3, c.yTaille + .03, .024], [-.1, c.yPoitrine - .1, .036]]) {
			const yHaut = c.yEpaule - .05, rangs = 4, l = .07;
			for(let r = 0; r < rangs; r++) {
				const y0 = yHaut + (yBas - yHaut) * r / rangs, y1 = yHaut + (yBas - yHaut) * (r + 1) / rangs;
				const face = toile(THREE, 1, 1, (i, k) => c.surCorps(az + (i ? 1 : -1) * l / c.rayon(k ? y1 : y0), k ? y1 : y0, e + lift));
				const s = Math.sign(az) || 1;
				const tranche = toile(THREE, 1, 1, (i, k) => c.surCorps(az + s * l / c.rayon(k ? y1 : y0), k ? y1 : y0, e + (i ? lift : .004)), P => [s * Math.cos(az), 0, -s * Math.sin(az)]);
				geos[r % 2 ? 'bande' : 'fond'].push(face, tranche);
			}
			// La frange : trois pointes sous le bout du pan
			for(const dx of [-.045, 0, .045]) {
				const az0 = az + dx / c.rayon(yBas);
				geos.bande.push(toile(THREE, 1, 1, (i, k) => c.surCorps(az0 + (k ? 0 : (i ? 1 : -1) * .016 / c.rayon(yBas)), yBas - (k ? .04 : 0) + (k && i ? .0001 : 0), e + lift)));
			}
		}
		return [
			{ parent: 'torse', geometry: merge(THREE, geos.fond), color: p.couleur },
			{ parent: 'torse', geometry: merge(THREE, geos.bande), color: p.bande },
		];
	},

	// La besace en bandoulière : la sangle part de l'épaule gauche, traverse la poitrine et le dos, et porte le sac sur la
	// hanche droite, à l'opposé de l'outil que la scène met dans la main gauche. Le sac a son rabat et sa boucle
	'besace': (THREE, a, p) => {
		const c = a.corps, e = surHaut(a, .006), azSac = -1.05, ySac = c.yTaille + .02;
		const devant = sangle(THREE, a, [[1.25, c.yEpaule - .005], [.75, c.yEpaule - .06], [.15, c.yPoitrine - .02], [-.5, c.yTaille + .12], [azSac + .12, ySac + .1]], .026, e + .006);
		const dos = sangle(THREE, a, [[1.25, c.yEpaule - .005], [1.9, c.yEpaule - .05], [2.6, c.yPoitrine - .04], [3.4, c.yTaille + .14], [4.2, ySac + .1], [2 * Math.PI + azSac - .12, ySac + .1]], .026, e + .006);
		const M = repere(THREE, a, azSac, ySac, e + .045);
		const sac = pave(THREE, .21, .17, .08).applyMatrix4(M);
		const rabat = toile(THREE, 1, 1, (i, k) => [(-1 + 2 * i) * .107, .087 - k * .1, .042], () => [0, 0, 1]).applyMatrix4(M);
		const boucle = pave(THREE, .03, .03, .012).translate(0, -.015, .046).applyMatrix4(M);
		return [
			{ parent: 'torse', geometry: merge(THREE, [devant, dos, sac]), color: p.couleur },
			{ parent: 'torse', geometry: merge(THREE, [rabat]), color: nuance(THREE, p.couleur, -.1) },
			{ parent: 'torse', geometry: boucle, color: p.boucle },
		];
	},

	// La hotte de légumes : une hotte d'osier sur le dos, penchée en arrière pour passer sous la tête, des fanes et des
	// poireaux qui dépassent, et ses deux bretelles sur les épaules. L'osier se lit par ses bandes alternées
	'hotte': (THREE, a, p) => {
		const c = a.corps, e = surHaut(a, .006), yPied = c.yTaille - .02, h = .46;
		// Le repère de la hotte : son axe part du bas du dos et penche vers l'arrière
		const M = new THREE.Matrix4().makeRotationX(-.32).setPosition(0, yPied, -(c.rayon(yPied) * c.aplati(yPied) + e + .13));
		const profil = [[0, 0], [.13, 0], [.15, h * .33], [.175, h * .66], [.2, h], [.205, h + .015], [.17, h + .015]];
		const bandes = { fond: [], bande: [] };
		for(let r = 0; r < profil.length - 1; r++) bandes[r === 2 || r === 4 ? 'bande' : 'fond'].push(tour(THREE, [profil[r], profil[r + 1]], 6).scale(1.15, 1, .78).applyMatrix4(M));
		// Ce que porte la hotte : la terre des légumes au ras du bord, d'où montent fanes et poireaux
		const dessus = tour(THREE, [[.17, h + .015], [0, h - .01]], 6).scale(1.15, 1, .78).applyMatrix4(M);
		const fanes = [[-.1, .2, -.02, .05], [-.03, .26, .03, .06], [.06, .22, -.03, .05], [.12, .17, .02, .045], [.02, .18, -.06, .05]].map(([x, hf, z, l]) => {
			const base = [x, h, z], bout = [x * 1.4, h + hf, z - .03];
			return toile(THREE, 1, 1, (i, k) => k ? [bout[0] + (i - .5) * .01, bout[1], bout[2]] : [base[0] + (i - .5) * l, base[1], base[2]], () => [0, 0, 1]).applyMatrix4(M);
		});
		const envers = fanes.map(g => {
			const r = g.clone(), pos = r.attributes.position.array;
			for(let t = 0; t < pos.length; t += 9) for(let k = 0; k < 3; k++) [pos[t + 3 + k], pos[t + 6 + k]] = [pos[t + 6 + k], pos[t + 3 + k]];
			r.computeVertexNormals();
			return r;
		});
		const bretelles = [1, -1].map(s => sangle(THREE, a, [[s * 2.6, c.yEpaule - .07], [s * 2.1, c.yEpaule - .015], [s * 1.05, c.yEpaule - .015], [s * .62, c.yEpaule - .07], [s * .56, c.yPoitrine], [s * .95, c.yPoitrine - .12]], .022, e + .006, .06));
		return [
			{ parent: 'torse', geometry: merge(THREE, [...bandes.fond, ...bretelles]), color: p.couleur },
			{ parent: 'torse', geometry: merge(THREE, bandes.bande), color: nuance(THREE, p.couleur, -.12) },
			{ parent: 'torse', geometry: dessus, color: '#5b4330' },
			{ parent: 'torse', geometry: merge(THREE, [...fanes, ...envers]), color: p.fanes },
		];
	},
};

return { LUNETTES, BARBES, COIFFURE_SOUS, COUVRE_CHEFS, OBJETS, BIJOUX, ECHARPES, gants, ACCESSOIRES };
})();

// ---------- personnage.js ----------
const module_personnage = (() => {
// Le personnage : le corps commun, habillé et coiffé de ce que dit sa fiche. Il se monte en
// couches, dans l'ordre où une photo se lit :
//   1. le corps commun nu (corps.js), dont seules la peau et la morphologie viennent de la fiche ;
//   2. « vetements » : le haut, le bas et les chaussures (vetements.js), qui retirent les zones du corps qu'ils couvrent ;
//   3. « visage » : les marques peintes sur la peau (visage.js), sans rien changer à la forme de la tête ;
//   4. « elements » : la coiffure (coiffure.js), puis les lunettes, la barbe, les bijoux, le couvre-chef, les objets en main
//      et l'accessoire (elements.js).
//   build(THREE, helpers, { fiche, couches })
//     fiche   : { nom, corps: { cle: valeur }, elements: [{ catalogue, reglages }] } (fiche.js)
//     couches : celles à monter, toutes par défaut ; ['vetements'] donne le corps habillé, [] le corps nu
//     raccords : les articulations fermées, pour l'avatar seul (avatar/contrat.js), { pli } : épaules, coudes et genoux en
//               demi-sphères centrées sur leurs pivots, la manche longue jusque sur la main, le col montant fermé jusque sous
//               la tête, le bord d'un chapeau à passe posé sur la tête coiffée, et la ceinture du bas coupée au pli des hanches
//               `pli` de l'avatar, en mètres à la taille de la fiche. Absent, le personnage est celui que les scènes ont cuit,
//               au triangle près
// Les pièces portent les noms que le bourg anime (bassin, torse, tete, brasG, brasD, jambeG, jambeD), plus des pièces filles
// accrochées à l'une d'elles par `parents`. Aucune branche par prénom : tout ce qui change d'une personne à l'autre se lit
// dans la fiche.
const { corps } = module_corps;
const { lisse } = module_outils;
const { VETEMENTS, MANCHE_COURTE } = module_vetements;
const { coiffure } = module_coiffure;
const { visage } = module_visage;
const { LUNETTES, BARBES, COUVRE_CHEFS, COIFFURE_SOUS, OBJETS, BIJOUX, ECHARPES, ACCESSOIRES, gants } = module_elements;
const { resoudre } = module_fiche;
const { FAMILLES } = module_catalogue;
const { EMOTIONS, poseEmotion } = module_emotions;

const COUCHES = ['vetements', 'visage', 'elements'];
const CHEVEUX_PAR_DEFAUT = '#483e30';
// Le buste, les bras et les avant-bras qui tiennent la caisse lancée du bourg devant la poitrine, par ses deux côtés : l'ancre
// `mainsLevees` se mesure sur eux (voir plus bas)
const CAISSE_LANCEE = { torse: [-.05, 0, 0], brasG: [-.75, 0, -.06], avantBrasG: [-.6, 0, 0], brasD: [-.75, 0, .06], avantBrasD: [-.6, 0, 0] };

// Ce que l'avatar laisse le bras s'enfoncer de plus que dans le haut par défaut, et l'écart le plus grand qu'il essaie. Là où
// la manche portée est plus épaisse que celle du haut par défaut, le tronc compte pour 5 mm de plus : la marche et les émotions
// qui plient le coude ramènent cette manche contre la taille, où celle d'un pull, écartée pour le seul repos, entrait encore
// d'un centimètre
const BRAS_TOLERANCE = .002, BRAS_MARGE = .005, BRAS_ECART_MAX = .4;

// Le dessus du tronc à la hauteur y, de côté : le rayon du corps, ou celui des rangs [rayon, hauteur] du profil d'une veste ou
// d'un haut là où ils le dépassent. Le bras pend sur le côté, où ce rayon se lit sans l'aplati de l'avant
const dessusTronc = (rayon, rangs) => y => {
	let r = rayon(y);
	for(let k = 1; k < rangs.length; k++) {
		const [r1, y1] = rangs[k - 1], [r2, y2] = rangs[k];
		if(y1 !== y2 && (y - y1) * (y - y2) <= 0) r = Math.max(r, r1 + (r2 - r1) * (y - y1) / (y2 - y1));
	}
	return r;
};

// L'épaisseur de la manche que porte le bras à `s` de l'épaule : celle de la veste à manches longues, sinon celle du haut
function manche(veste, haut, L) {
	return s => {
		if(veste?.params.manches === 'longues') return veste.params.epaisseur;
		if(haut?.params.manches === 'longues') return haut.params.epaisseur;
		if(haut?.params.manches === 'courtes' && s <= MANCHE_COURTE * L) return haut.params.epaisseur;
		return 0;
	};
}

// L'écart des bras de l'avatar, en radians autour de l'épaule : le plus petit qui, au repos, ne laisse pas le bras entrer dans
// ce que porte le tronc plus loin que dans le haut par défaut de l'éditeur, le t-shirt, sur lequel le corps commun a réglé ses
// bras : ils y pendent à moitié dans la poitrine, et ce recouvrement ne se voit pas. Une pièce plus épaisse que lui sur le
// tronc ou sur le bras mange l'écart entre les deux, et une veste longue garde la largeur des hanches jusqu'à son ourlet, là
// où pend la main : sans écart, la doudoune sans manches portée sur un t-shirt avalait le bras nu jusqu'au poignet.
// Le calcul suit l'axe du bras centimètre par centimètre, de la sortie de la boule d'épaule au bout de la main, et compare à
// chaque hauteur la face intérieure du bras, peau et manche, au dessus du tronc (dessusTronc()). `porte` et `defaut` donnent
// chacun le dessus du tronc et l'épaisseur de la manche le long du bras, `{ dessus, manche }`.
// Le bras gauche parle pour les deux : rien de ce que lit le calcul n'a de côté. Le dessus du tronc est un rayon par hauteur,
// l'épaisseur d'une manche un réglage de la pièce entière, et corps.js tire le bras droit du gauche par miroir (`sx`)
function ecartBras(a, porte, defaut) {
	const { A, axe, L, profil: profilBras } = a.bras.brasG;
	const rayonBras = s => {
		if(s <= profilBras[0][1]) return profilBras[0][0];
		for(let k = 1; k < profilBras.length; k++) {
			const [r1, s1] = profilBras[k - 1], [r2, s2] = profilBras[k];
			if(s <= s2) return r1 + (r2 - r1) * (s - s1) / (s2 - s1);
		}
		return profilBras[profilBras.length - 1][0];
	};
	const enfoui = (t, marge) => {
		let pire = -Infinity;
		for(let s = .09; s <= L + .09; s += .01) {
			const dx = axe.x * s, dy = axe.y * s, peau = rayonBras(s);
			const x = A.x + dx * Math.cos(t) - dy * Math.sin(t), y = A.y + dx * Math.sin(t) + dy * Math.cos(t);
			const reference = Math.max(0, defaut.dessus(A.y + dy) - (A.x + dx - peau - defaut.manche(s)));
			const jeu = porte.manche(s) > defaut.manche(s) ? marge : 0;
			pire = Math.max(pire, Math.max(0, porte.dessus(y) + jeu - (x - peau - porte.manche(s))) - reference);
		}
		return pire;
	};
	// La marge règle la taille d'un écart, elle n'en ouvre pas : elle prévoit ce que les gestes ajoutent à un bras que le repos
	// enfouit déjà. Un bras que le repos n'enfouit pas reste le long du corps, l'écarter éloignerait ses mains des hanches sans
	// rien sortir de la pièce
	if(enfoui(0, 0) <= BRAS_TOLERANCE) return 0;
	for(let t = 0; t < BRAS_ECART_MAX; t += .005) if(enfoui(t, BRAS_MARGE) <= BRAS_TOLERANCE) return t;
	return BRAS_ECART_MAX;
}

// Sous un couvre-chef, aucune mèche ne sort au-dessus de son bord. Le cercle d'assise est le plus large de la tête coiffée à sa
// hauteur, mais la coiffe se resserre au-dessus et les mèches posées bombent au-dessus du fond : leurs pointes perçaient la
// calotte d'une casquette ou la coiffe d'un chapeau, quel que soit le volume des cheveux. Rentrer sous la coiffe les seuls
// sommets d'au-dessus ne suffisait pas non plus : un triangle qui enjambe le bord, un sommet dessus et un dessous plus
// large que la tête, traversait le bas de la coiffe et y mordait en dents.
// La chevelure s'arrête donc au plan d'assise. Un triangle tout entier au-dessus est sous la coiffe, qui est fermée en haut :
// il ne se dessine plus, et ses triangles reviennent au budget sans rien changer à ce qu'on voit. Un triangle qui enjambe le
// bord garde ses sommets du dessous, et ceux du dessus descendent sur le plan, dans le cercle d'assise : il finit sous la
// coiffe sans la traverser. Ce qui passe sous le bord (la nuque, les tempes, une queue de cheval) ne bouge pas, et le chapeau
// reste assis où la coiffure le pose.
// Les mèches posées s'effacent toutes : elles partent du sommet, sous la coiffe, et ce qui en reste sous le bord n'est
// qu'un bout coupé, que son haut ramené sur le plan ne pose plus sur la tête. À la tempe, il pointait hors du contour du
// visage sous le revers d'un bonnet. Le fond de la calotte habille seul ce qui dépasse du bord.
// Un sommet ramené sur le plan reste hors de la boule nue (`rayonPeau`, son rayon à cette hauteur), à 6 mm près : descendu
// d'aplomb depuis le haut du crâne, il tombait près de l'axe, dans la tête, et la facette qui le joignait au bas de la
// chevelure passait sous la peau, qui se voyait sous le bord du chapeau
function rabattre(THREE, parts, repere, rayonAssise, rayonPeau) {
	const inverse = repere.clone().invert(), local = new THREE.Vector3();
	for(let i = parts.length - 1; i >= 0; i--) {
		if(parts[i].name !== 'cheveux') continue;
		if(parts[i].posee) {
			parts.splice(i, 1);
			continue;
		}
		const g = parts[i].geometry.index ? parts[i].geometry.toNonIndexed() : parts[i].geometry, pos = g.attributes.position;
		const dessus = Array.from({ length: pos.count }, (_, k) => local.fromBufferAttribute(pos, k).applyMatrix4(inverse).y > 0);
		const gardes = [];
		for(let t = 0; t < pos.count; t += 3) {
			if(dessus[t] && dessus[t + 1] && dessus[t + 2]) continue;
			gardes.push(t);
			for(let k = t; k < t + 3; k++) {
				if(dessus[k] === false) continue;
				local.fromBufferAttribute(pos, k).applyMatrix4(inverse);
				const rayon = Math.hypot(local.x, local.z), dans = Math.min(Math.max(rayon, rayonPeau + .006), rayonAssise) / rayon;
				pos.setXYZ(k, ...local.set(local.x * dans, 0, local.z * dans).applyMatrix4(repere).toArray());
			}
		}
		if(gardes.length === 0) {
			parts.splice(i, 1);
			continue;
		}
		const nouvelle = new THREE.BufferGeometry();
		for(const [nom, attribut] of Object.entries(g.attributes)) {
			const n = attribut.itemSize, tableau = new Float32Array(gardes.length * 3 * n);
			gardes.forEach((t, j) => tableau.set(attribut.array.subarray(t * n, (t + 3) * n), j * 3 * n));
			nouvelle.setAttribute(nom, new THREE.BufferAttribute(tableau, n));
		}
		parts[i] = { ...parts[i], geometry: nouvelle };
	}
}

// La forme du visage : un champ de déplacement posé sur tout ce que la tête porte, peau, traits, coiffure, barbe et couvre-chef
// ensemble, après qu'ils se sont posés sur la boule ronde. Chaque pièce garde ainsi son écart à la peau, et la forme ne
// coûte aucun triangle. Le champ ne touche que le bas du visage, sous les yeux, de face et sur les côtés jusque derrière les
// oreilles, où le contour se lit de face : le crâne, où s'assoient la coiffure et le couvre-chef, et la nuque, où se raccorde
// le cou, restent ceux de tous. `joues` resserre ou élargit, `etire` allonge le bas du visage jusque sous le menton, `menton`
// le descend ou le remonte au milieu. Un élargissement courbe la peau entre ses sommets, plus espacés que ceux de ce qui la
// couvre : plus large que la carrée, la peau perce la barbe courte, posée à 4 mm. Sa mâchoire droite vient donc surtout du
// menton remonté
const FORMES_VISAGE = {
	ronde: null,
	ovale: { joues: -.2, etire: .15, menton: -.02, avance: 0 },
	allongee: { joues: -.12, etire: .35, menton: -.02, avance: -.01 },
	carree: { joues: .12, etire: 0, menton: .05, avance: 0 },
	coeur: { joues: -.36, etire: .1, menton: -.05, avance: 0, pointe: true },
};
// Le déplacement d'un point du repère de la tête, centré sur la boule
function deplacerVisage(f, x, y, z) {
	const r = Math.hypot(x, y, z);
	if(r < 1e-6) return [x, y, z];
	const th = Math.acos(Math.min(1, Math.max(-1, y / r))), phi = Math.atan2(x, z), deg = Math.PI / 180;
	const bas = lisse((th - 88 * deg) / (34 * deg)), devant = lisse(Math.cos(phi) + .7);
	const menton = Math.max(0, Math.cos(phi)) ** 6;
	const w = bas * devant;
	if(w === 0) return [x, y, z];
	const joues = f.pointe ? f.joues * Math.pow(bas, 2.5) * devant : f.joues * w;
	// Le menton ne descend ou ne remonte que sous la bouche : la bouche est posée sur la peau, et un champ qui la déplace
	// autrement que les facettes voisines l'enfonce dedans
	const sousBouche = lisse((th - 112 * deg) / (25 * deg));
	return [x * (1 + joues), y * (1 + f.etire * w) + f.menton * sousBouche * menton, z + f.avance * sousBouche * menton];
}
// Les normales suivent le champ par l'inverse transposée de sa jacobienne, prise en différences finies : recalculées, elles
// perdraient le lissage et le redressement que chaque pièce a reçus
function formerVisage(THREE, a, forme, geometries) {
	const f = FORMES_VISAGE[forme];
	if(f === null) return;
	const { M, C } = a.tete, inverse = M.clone().invert(), rotation = new THREE.Matrix3().setFromMatrix4(M), retour = rotation.clone().invert();
	const v = new THREE.Vector3(), n = new THREE.Vector3(), J = new THREE.Matrix3(), e = 1e-4;
	for(const g of geometries) {
		const pos = g.attributes.position, nor = g.attributes.normal;
		for(let i = 0; i < pos.count; i++) {
			v.fromBufferAttribute(pos, i).applyMatrix4(inverse).sub(C);
			const p0 = deplacerVisage(f, v.x, v.y, v.z);
			if(p0[0] === v.x && p0[1] === v.y && p0[2] === v.z) continue;
			if(nor !== undefined) {
				const px = deplacerVisage(f, v.x + e, v.y, v.z), py = deplacerVisage(f, v.x, v.y + e, v.z), pz = deplacerVisage(f, v.x, v.y, v.z + e);
				J.set((px[0] - p0[0]) / e, (py[0] - p0[0]) / e, (pz[0] - p0[0]) / e, (px[1] - p0[1]) / e, (py[1] - p0[1]) / e, (pz[1] - p0[1]) / e, (px[2] - p0[2]) / e, (py[2] - p0[2]) / e, (pz[2] - p0[2]) / e);
				const normale = J.invert().transpose();
				n.fromBufferAttribute(nor, i).applyMatrix3(retour).applyMatrix3(normale).applyMatrix3(rotation).normalize();
				nor.setXYZ(i, n.x, n.y, n.z);
			}
			v.set(...p0).add(C).applyMatrix4(M);
			pos.setXYZ(i, v.x, v.y, v.z);
		}
		pos.needsUpdate = true;
		if(nor !== undefined) nor.needsUpdate = true;
	}
}

// La retombée des épaules, en mètres sur la figure de 1,8 m, au bout de l'épaule, sur l'avatar comme dans les scènes : le
// corps commun pose le dessus du bras en plateau à 6 mm sous la tête, et une personne se lit de la même ligne d'épaules
// dans l'éditeur et dans la scène (décidé le 2026-10-19). Un champ de déplacement, comme celui du visage : les bras, ce
// qu'ils portent et leurs pivots descendent d'un bloc ; le buste et ce qu'il porte descendent d'autant au pivot du bras, de
// rien au rang du cou et sous la poitrine. Aucun triangle de plus, et chaque pièce garde son écart à la peau
const EPAULES_RETOMBEE = .035;
function retomberEpaules(THREE, a, d, parts, parents) {
	const { yPoitrine, yEpaule, yCou, rayon } = a.corps;
	const y0 = yPoitrine - .02, x0 = rayon(yEpaule + .02), { x: x1, y: y1 } = a.bras.brasG.A;
	// Sous la tête, ce qu'elle porte et qui tombe sur les épaules (cheveux longs, voile) suit le buste, en fondu depuis le cou
	const champ = (x, y, sousTete) => -d * lisse((Math.abs(x) - x0) / (x1 - x0)) * lisse((y - y0) / (y1 - y0)) * (sousTete ? lisse((yCou - .01 - y) / .08) : 1);
	const bras = { brasG: 'brasG', avantBrasG: 'brasG', brasD: 'brasD', avantBrasD: 'brasD' };
	const chaine = nom => { while(nom !== undefined && bras[nom] === undefined && nom !== 'tete') nom = parents[nom]; return nom; };
	const n = new THREE.Vector3(), J = new THREE.Matrix3(), e = 1e-4, faites = new Set();
	for(const p of parts) {
		const c = chaine(p.name);
		if(bras[c]) {
			if(faites.has(p.geometry) === false) p.geometry.translate(0, -d, 0);
			faites.add(p.geometry);
			p.pivot = [p.pivot[0], p.pivot[1] - d, p.pivot[2]];
			continue;
		}
		if(faites.has(p.geometry)) continue;
		faites.add(p.geometry);
		const sousTete = c === 'tete', g = p.geometry, pos = g.attributes.position, nor = g.attributes.normal;
		let bouge = false;
		for(let i = 0; i < pos.count; i++) {
			const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i), dy = champ(x, y, sousTete);
			if(dy === 0) continue;
			bouge = true;
			if(nor !== undefined) {
				// Le champ ne déplace qu'en y : la jacobienne n'a de termes hors diagonale que sur sa ligne y
				const gx = (champ(x + e, y, sousTete) - dy) / e, gy = (champ(x, y + e, sousTete) - dy) / e;
				J.set(1, 0, 0, gx, 1 + gy, 0, 0, 0, 1);
				n.fromBufferAttribute(nor, i).applyMatrix3(J.invert().transpose()).normalize();
				nor.setXYZ(i, n.x, n.y, n.z);
			}
			pos.setY(i, y + dy);
		}
		if(bouge) { pos.needsUpdate = true; if(nor !== undefined) nor.needsUpdate = true; }
	}
	for(const cote of ['brasG', 'brasD']) for(const cle of ['A', 'E', 'paume']) a.bras[cote][cle].y -= d;
	a.mainG = [a.mainG[0], a.mainG[1] - d, a.mainG[2]];
	a.mainD = [a.mainD[0], a.mainD[1] - d, a.mainD[2]];
}

// Les mains des émotions qui touchent le visage, que la retombée des épaules en écarterait : la main droite de Pensif, au
// menton, passait devant le col et se lisait posée sur la poitrine. Sous raccords, l'adaptateur de l'avatar les remonte
// lui-même, avec l'écart des bras qu'il rattrape dans le même geste (avatarMainTenue(), avatar/contrat.js) ; sans, build() les
// remonte ici. Les mains jointes d'Inquiet descendent ensemble et restent jointes, et un poing sur la hanche reste sur la même
// hanche, plus bas
const MAINS_RELEVEES = { pensif: ['D'] };
// La pose où la main du côté `cote` remonte de `retombee` dans le repère du buste, celle d'avatarMainTenue() à écart nul : le
// coude se déplie de ce qu'il faut pour que la main atteigne sa place, puis le bras entier se tourne vers elle autour de
// l'épaule. Le pli se cherche par dichotomie jusqu'à un radian de dépli, qui couvre largement les 3,5 cm de la retombée
const DEPLI_MAX = 1;
function releverMain(THREE, pose, cote, retombee, pivots, anchors) {
	const bras = 'bras' + cote, avantBras = 'avantBras' + cote, epaule = new THREE.Vector3(...pivots[bras]);
	const rotation = (os, plus = 0) => { const [x, y, z] = pose[os] ?? [0, 0, 0]; return new THREE.Matrix4().makeRotationFromEuler(new THREE.Euler(x + plus, y, z)); };
	const angles = m => new THREE.Euler().setFromRotationMatrix(m).toArray().slice(0, 3);
	const haut = rotation(bras), coude = new THREE.Vector3(...pivots[avantBras]).sub(epaule);
	const paume = new THREE.Vector3(...anchors['main' + cote]).sub(epaule).sub(coude);
	const mainPosee = plus => paume.clone().applyMatrix4(rotation(avantBras, plus)).add(coude).applyMatrix4(haut);
	const cible = mainPosee(0).add(new THREE.Vector3(0, retombee, 0)), portee = cible.length();
	let ferme = 0, ouvert = DEPLI_MAX;
	for(let i = 0; i < 30; i++) {
		const milieu = (ferme + ouvert) / 2;
		if(mainPosee(milieu).length() < portee) ferme = milieu; else ouvert = milieu;
	}
	const vise = new THREE.Matrix4().makeRotationFromQuaternion(new THREE.Quaternion().setFromUnitVectors(mainPosee(ferme).normalize(), cible.clone().normalize()));
	return { ...pose, [bras]: angles(vise.multiply(haut)), [avantBras]: angles(rotation(avantBras, ferme)) };
}

function build(THREE, helpers, options = {}) {
	const { fiche = {}, couches = COUCHES, raccords = null } = options;
	const { corps: mesures, elements, ecarts, emotion } = resoudre(fiche);
	const portes = elements.filter(e => couches.includes(e.couche));
	const dans = famille => portes.filter(e => e.famille === famille);

	const base = corps(THREE, mesures, { raccords }), a = base.anchors;
	// Les vêtements lisent les raccords sur les ancres, le pli ramené à la taille du corps commun (1,80 m), où ils se taillent.
	// Sans raccords, la clé n'existe pas
	if(raccords) a.raccords = { pli: raccords.pli * 1.8 / mesures.hauteur };
	const parts = [...base.parts], couvre = new Set();
	// Chaque pièce suit son parent : le buste porte la tête et les bras, le bras son avant-bras, la cuisse son mollet
	const parents = { tete: 'torse', brasG: 'torse', brasD: 'torse', avantBrasG: 'brasG', avantBrasD: 'brasD', molletG: 'jambeG', molletD: 'jambeD', cheveux: 'tete', visage: 'tete', lunettes: 'tete', barbe: 'tete', bijoux: 'tete', chapeau: 'tete', pendentif: 'torse', outilG: 'avantBrasG', outilD: 'avantBrasD' };
	const poser = (rendu, nom = null) => {
		for(const p of rendu.parts) parts.push({ ...p, name: nom ?? p.name });
		for(const z of rendu.couvre ?? []) couvre.add(z);
	};

	// La veste se calcule d'abord, pour qu'une chemise dessous ne dessine que ce qui se voit ; les chaussures après le bas,
	// qui dit si les mollets restent nus et laissent voir une chaussette ; la cravate en dernier, sur le haut ou dans
	// l'ouverture de la veste
	const vetements = portes.filter(e => e.couche === 'vetements'), rendus = new Map();
	// La salopette lit l'épaisseur du haut pour poser sa bavette dessus : elle se calcule après lui
	// Sous une salopette, un haut fait dans Blender rentre son ourlet (hautBlender()) : l'avatar seul, les scènes n'en montent pas
	if(raccords) a.raccords.sousSalopette = vetements.some(e => e.params.coupe === 'salopette');
	const calcul = e => e.famille === 'vestes' ? 0 : e.famille === 'cravates' ? 3 : e.famille === 'chaussures' ? 2 : e.params.coupe === 'salopette' ? 1.5 : 1;
	for(const e of [...vetements].sort((x, y) => calcul(x) - calcul(y))) {
		if(e.famille === 'chaussures') a.jambesNues = couvre.has('mollets') === false;
		const rendu = VETEMENTS[e.forme](THREE, a, e.params);
		if(e.famille === 'vestes') a.veste = { ecart: rendu.ecart, encolure: rendu.encolure, manches: e.params.manches, longue: e.params.longue === true, fermee: e.params.fermeture !== 'aucune' };
		if(e.famille === 'hauts') a.ecartSousVeste = rendu.ecart;
		if(e.famille === 'hauts' || e.famille === 'vestes') a.ecartHaut = Math.max(a.ecartHaut ?? 0, rendu.ecart);
		// Le dessus du torse, que suit l'écharpe : celui de la veste, qui se calcule en premier et couvre le haut porté dessous
		if(rendu.profil && (e.famille === 'vestes' || a.veste === undefined)) a.dessus = { profil: rendu.profil, revers: rendu.revers };
		for(const z of rendu.couvre ?? []) couvre.add(z);
		rendus.set(e, rendu);
	}
	// Les pièces se rangent dans l'ordre de la fiche, chaussures comprises, veste en tête et cravate en dernier : l'ordre
	// des pièces entre dans l'empreinte des personnes déjà faites, et le calcul n'a pas à le déplacer
	const rang = e => e.famille === 'vestes' ? 0 : e.famille === 'cravates' ? 2 : 1;
	for(const e of [...vetements].sort((x, y) => rang(x) - rang(y))) poser(rendus.get(e));

	const [cheveux] = dans('coiffures'), [chef] = dans('couvre-chefs');
	// Un couvre-chef se pose sur la coiffure comme un chapeau, se ceint autour d'elle sans rien en retirer, ou la couvre toute
	// (`COIFFURE_SOUS` d'elements.js)
	const coiffureSous = COIFFURE_SOUS[chef?.forme];
	// Il s'assoit sur la tête coiffée : son cercle d'assise se mesure sur la peau et la coiffure ensemble. Celui qui se ceint
	// suit en plus le contour de la tête coiffée, azimut par azimut, comme sur l'avatar (`assise()` de corps.js)
	const coiffer = coiffe => {
		const assise = a.tete.assise(coiffe.volumes, coiffe.hauteur, coiffe.parts.filter(p => p.posee === undefined).map(p => p.geometry), coiffureSous === 'entiere' ? { contour: true } : {});
		const y = new THREE.Vector3(...assise.chapeauAxe), x = y.clone().cross(new THREE.Vector3(...assise.chapeauAvant)).normalize();
		const repere = new THREE.Matrix4().makeBasis(x, y, x.clone().cross(y).normalize()).setPosition(...assise.chapeau);
		const params = coiffureSous === 'masquee' && cheveux ? { ...chef.params, cheveux: cheveux.params.couleur } : chef.params;
		const rendu = COUVRE_CHEFS[chef.forme](THREE, assise, params);
		return { parts: rendu.map(o => ({ geometry: o.geometry.applyMatrix4(repere), color: o.color, pivot: a.pivots.tete })), couvre: rendu.couvre, repere, assise };
	};
	// Celui qui couvre toute la coiffure se taille sur la tête nue, avant elle, et elle ne se monte que s'il ne rend rien : une
	// cible qui n'en porte aucune forme garde la personne coiffée plutôt que chauve
	const masque = coiffureSous === 'masquee' ? coiffer({ parts: [], volumes: [], hauteur: 0 }) : null;
	let coiffe = { parts: [], volumes: [], hauteur: 0 };
	if(cheveux && (masque === null || masque.parts.length === 0)) {
		if(raccords) a.raccords.sousCouvreChef = chef !== undefined && coiffureSous === undefined;
		coiffe = coiffure(THREE, a, cheveux.params);
		poser(coiffe);
	}

	const marques = Object.fromEntries(dans('visage').map(e => [e.id, e.params]));
	const traits = visage(THREE, a, marques);
	poser(traits);

	const tete = a.pivots.tete;
	for(const e of dans('lunettes')) poser({ parts: LUNETTES[e.forme](THREE, a, e.params).map(o => ({ ...o, pivot: tete })) }, 'lunettes');
	for(const e of dans('barbes')) {
		const params = { ...e.params, couleur: e.params.couleur ?? cheveux?.params.couleur ?? CHEVEUX_PAR_DEFAUT };
		poser({ parts: BARBES[e.forme](THREE, a, params).map(o => ({ ...o, pivot: tete })) }, 'barbe');
	}
	// Un bijou qui porte un rôle suit un trait du visage : chaque émotion le redemande avec la descente du bas de sa bouche, et
	// sa géométrie se pose sur la pièce de même rôle comme celle des traits
	for(const e of dans('bijoux')) {
		const rendu = BIJOUX[e.forme](THREE, a, e.params);
		poser({ parts: rendu.map(o => ({ name: o.name, geometry: o.geometry, color: o.color, pivot: a.pivots[o.parent], ...(o.role ? { role: o.role } : {}) })) });
		if(rendu.some(o => o.role)) for(const nom in traits.variantes) {
			for(const o of BIJOUX[e.forme](THREE, a, e.params, traits.levre[nom])) if(o.role) traits.variantes[nom][o.role] = o.geometry;
		}
	}

	for(const e of dans('echarpes')) poser({ parts: ECHARPES[e.forme](THREE, a, e.params).map(o => ({ ...o, pivot: a.pivots.torse })) }, 'torse');

	// La chevelure ne se rabat sous le couvre-chef que s'il rend des pièces : sans elles, rien ne cacherait ce qu'elle perd
	if(chef) {
		const rendu = masque ?? coiffer(coiffe);
		poser(rendu, 'chapeau');
		if(coiffureSous === undefined && rendu.parts.length > 0) rabattre(THREE, parts, rendu.repere, rendu.assise.crane, rendu.assise.peau);
	}

	// Le premier objet va dans la main gauche du personnage, celle que montre la vue de trois quarts, le second dans la droite
	// Un objet qui se range au col (`rangement`) y passe quand la pose prend les deux mains : la scène lit `rangements` et
	// l'ancre `col`
	const rangements = {};
	dans('objets').forEach((e, i) => {
		const [main, nom, dehors] = i === 0 ? ['mainG', 'outilG', 1] : ['mainD', 'outilD', -1];
		const p = a[main];
		if(e.params.rangement) rangements[nom] = e.params.rangement;
		// Le sol se compte depuis la main où la laisse la retombée des épaules, qui descend l'objet avec le bras : compté
		// depuis la main d'avant, la tête d'un râteau posée au sol y entrait d'autant
		poser({ parts: OBJETS[e.forme](THREE, { ...e.params, dehors, sol: EPAULES_RETOMBEE - p[1] }).map(o => ({ geometry: o.geometry.translate(...p), color: o.color, pivot: p })) }, nom);
	});

	// L'accessoire se range après tout le reste : ses pièces se fondent dans la pièce animée qui les porte, et l'ordre des pièces
	// d'avant entre dans l'empreinte des personnes déjà faites
	for(const e of dans('accessoires')) poser({ parts: ACCESSOIRES[e.forme](THREE, a, e.params).map(o => ({ name: o.parent, geometry: o.geometry, color: o.color, pivot: a.pivots[o.parent] })) });
	// Les gants se rangent après l'accessoire, dans l'avant-bras qui les porte, et retirent la main nue
	for(const e of dans('gants')) {
		const rendu = gants(THREE, a, e.params);
		poser({ parts: rendu.map(o => ({ name: o.parent, geometry: o.geometry, color: o.color, pivot: o.pivot })), couvre: rendu.couvre });
	}

	// L'avatar écarte ses bras de ce que porte le tronc (ecartBras) : le bras, l'avant-bras et ce qu'ils portent tournent d'un bloc
	// autour de l'épaule, avant toute pose. L'écart est dans la géométrie parce que l'éditeur pose chaque os par rotation.set(),
	// qui écraserait un angle donné à l'os. Un écart déplace chaque geste écrit pour des bras le long du corps, qu'il faut alors
	// reprendre un à un en fonction de lui : l'avatar, regardé de près, paie cette reprise (avatar/contrat.js) ; les scènes, où
	// un bras qui entre de quelques millimètres dans un pull ne se voit pas, ne l'écartent pas
	let ecart = 0, surepaisseur = null;
	if(raccords) {
		const veste = vetements.find(e => e.famille === 'vestes'), haut = vetements.find(e => e.famille === 'hauts'), L = a.bras.brasG.L;
		// Le haut par défaut se taille sur ce corps comme s'il était porté seul, pour son seul profil
		const [defaut] = resoudre({ elements: [{ catalogue: FAMILLES.hauts.defaut }] }).elements;
		const dessusPorte = dessusTronc(a.corps.rayon, a.dessus?.profil ?? []);
		const dessusDefaut = dessusTronc(a.corps.rayon, VETEMENTS[defaut.forme](THREE, { ...a, veste: undefined }, defaut.params).profil);
		const porte = { dessus: dessusPorte, manche: manche(veste, haut, L) }, nu = { dessus: dessusDefaut, manche: manche(undefined, defaut, L) };
		ecart = ecartBras(a, porte, nu);
		surepaisseur = y => Math.max(0, porte.dessus(y) - nu.dessus(y)) + Math.max(0, porte.manche(L) - nu.manche(L));
		const membres = { brasG: 'brasG', avantBrasG: 'brasG', brasD: 'brasD', avantBrasD: 'brasD' };
		const chaine = nom => {
			while(nom !== undefined && membres[nom] === undefined) nom = parents[nom];
			return membres[nom];
		};
		const tourner = (cote, v) => v.sub(a.bras[cote].A).applyAxisAngle(new THREE.Vector3(0, 0, 1), a.bras[cote].sx * ecart).add(a.bras[cote].A);
		if(ecart > 0) {
			for(const p of parts) {
				const cote = chaine(p.name);
				if(cote === undefined) continue;
				const { x, y, z } = a.bras[cote].A;
				p.geometry.translate(-x, -y, -z).rotateZ(a.bras[cote].sx * ecart).translate(x, y, z);
				p.pivot = tourner(cote, new THREE.Vector3(...p.pivot)).toArray();
			}
			a.mainG = tourner('brasG', new THREE.Vector3(...a.mainG)).toArray();
			a.mainD = tourner('brasD', new THREE.Vector3(...a.mainD)).toArray();
		}
	}

	// Les épaules retombent après l'écart, sur tout ce que le buste et les bras portent
	retomberEpaules(THREE, a, EPAULES_RETOMBEE, parts, parents);

	// La forme du visage se pose en dernier sur la tête, ses traits d'émotion compris
	const porteTete = nom => nom === 'tete' || parents[nom] === 'tete';
	formerVisage(THREE, a, mesures.visage, new Set([...parts.filter(p => porteTete(p.name)).map(p => p.geometry), traits.expressions.triste, ...Object.values(traits.variantes).flatMap(roles => Object.values(roles))]));
	// Ce que vêtements et coiffure couvrent entièrement ne se dessine pas dessous
	const visibles = parts.filter(p => p.zone === undefined || p.zone === null || couvre.has(p.zone) === false);

	// Mise à la hauteur de la fiche : tout se met à l'échelle depuis les pieds, une seule fois
	const echelle = mesures.hauteur / 1.8, vers = v => v.map(x => x * echelle);
	for(const p of visibles) {
		p.geometry.scale(echelle, echelle, echelle);
		p.pivot = vers(p.pivot);
	}
	// La moue n'est pas dans les pièces : elle se met à l'échelle à part, le sourire l'étant déjà avec sa pièce
	traits.expressions.triste.scale(echelle, echelle, echelle);
	// Les traits des émotions de même, chacun dans sa géométrie
	for(const roles of Object.values(traits.variantes)) for(const g of Object.values(roles)) g.scale(echelle, echelle, echelle);
	const epaule = a.bras.brasG.A, longueur = a.bras.brasG.L + .035;
	// Les mains qui tiennent la caisse devant la poitrine au bourg, mesurées sur la chaîne torse, bras, avant-bras : le milieu des
	// paumes. Ce sont les angles de l'ancienne pose « lance », gardés ici depuis qu'elle montre la fin du geste sans caisse
	// (poses.js) : l'ancre décide où le bourg pose la caisse, et chaque personne déjà faite la porte dans son empreinte
	const torse = new THREE.Object3D();
	torse.position.fromArray(a.pivots.torse);
	torse.rotation.set(...CAISSE_LANCEE.torse);
	const paumes = ['brasG', 'brasD'].map(cote => {
		const b = a.bras[cote], bras = new THREE.Object3D(), avant = new THREE.Object3D(), paume = new THREE.Object3D();
		bras.position.copy(b.A).sub(new THREE.Vector3(...a.pivots.torse));
		bras.rotation.set(...CAISSE_LANCEE[cote]);
		avant.position.copy(b.E).sub(b.A);
		avant.rotation.set(...CAISSE_LANCEE[b.avant]);
		paume.position.copy(b.paume).sub(b.E);
		torse.add(bras); bras.add(avant); avant.add(paume);
		torse.updateMatrixWorld(true);
		return paume.getWorldPosition(new THREE.Vector3());
	});
	// Les paumes tiennent la caisse par ses deux côtés, à mi-hauteur : l'ancre est le milieu de son fond, 13 cm sous elles.
	// Elle porte le nom sous lequel le bourg la lit, `mainsLevees`, bien que la caisse parte de la poitrine
	const levees = paumes[0].add(paumes[1]).multiplyScalar(.5).add(new THREE.Vector3(0, -.13, 0));
	const anchors = {
		tete: vers([0, a.tete.sommet + coiffe.hauteur, 0]),
		mainG: vers(a.mainG),
		mainD: vers(a.mainD),
		// Les bras tendus à l'horizontale devant soi, à hauteur d'épaule ; et le fond de la caisse qu'on lance, posée sur les
		// paumes de CAISSE_LANCEE
		mains: vers([0, epaule.y, longueur]),
		mainsLevees: vers(levees.toArray()),
		dos: vers(a.dos),
		// Sous l'encolure, sur le haut, là où pend un objet rangé au col, une branche passée dans l'ouverture. Elle n'existe
		// que pour une personne qui range un objet : les ancres entrent dans l'empreinte des autres
		...(Object.keys(rangements).length > 0 ? { col: vers(a.corps.surCorps(0, a.corps.yEpaule - .06, (a.ecartHaut ?? 0) + .004)) } : {}),
		// De combien le corps entier descend pour s'asseoir, la cuisse à l'horizontale et le pied au sol
		descenteAssis: (a.corps.yHanche - a.corps.yGenou) * echelle,
	};
	const boite = new THREE.Box3(), pivotsOs = Object.fromEntries(visibles.map(p => [p.name, p.pivot]));
	for(const p of visibles) { p.geometry.computeBoundingBox(); boite.union(p.geometry.boundingBox); }
	return {
		d: Math.max(boite.max.x - boite.min.x, boite.max.z - boite.min.z), h: boite.max.y,
		// `cote` (l'avatar seul, sous raccords) dit de quel côté du pli des hanches une pièce reste entière
		parts: visibles.map(({ name, geometry, color, pivot, role, cote }) => ({ name, geometry, color, pivot, ...(role ? { role } : {}), ...(cote ? { cote } : {}) })),
		parents, anchors, ecarts, rangements,
		// L'écart des bras que l'avatar a pris dans sa géométrie, que ses gestes rattrapent (avatar/contrat.js), et ce que le
		// tronc, de côté à une hauteur donnée à la taille de la fiche, et la manche au poignet portent de plus que le haut par
		// défaut : une main posée sur la hanche s'en écarte d'autant, et n'y entre pas plus que sous ce haut. La retombée des
		// épaules, à la taille de la fiche, que les gestes qui posent une main au visage ou sur l'autre main rattrapent
		...(raccords ? { ecartBras: ecart, surepaisseur: y => surepaisseur(y / echelle) * echelle, retombeeEpaules: EPAULES_RETOMBEE * echelle } : {}),
		expressions: traits.expressions,
		// Chaque émotion : sa pose complète, et les géométries des traits du visage à poser, rôle par rôle, sur les pièces qui
		// portent ce rôle. `emotion` est celle que la fiche donne à la personne au repos, ou null
		emotions: Object.fromEntries(Object.keys(EMOTIONS).map(nom => [nom, { pose: raccords ? poseEmotion(nom) : (MAINS_RELEVEES[nom] ?? []).reduce((pose, cote) => releverMain(THREE, pose, cote, EPAULES_RETOMBEE * echelle, pivotsOs, anchors), poseEmotion(nom)), visage: traits.variantes[nom] }])),
		emotion,
		elements: portes.map(e => e.id),
	};
}

return { COUCHES, FORMES_VISAGE, build };
})();

// ---------- le contrat de paysan.js ----------
// Les sept pièces de paysan.js, plus le genou, en option du contrat : `tibiaG` et `tibiaD`, enfants de `jambeG` et `jambeD`,
// tournent au genou. La scène ne les lit que s'ils sont là ; sans eux, elle replie le tibia sur la cuisse
const PIECES = ['bassin', 'torse', 'tete', 'brasG', 'brasD', 'jambeG', 'jambeD', 'tibiaG', 'tibiaD'], HANCHE = .62;
const RENOMMEES = { molletG: 'tibiaG', molletD: 'tibiaD' };
// L'angle des bras de pose() pour la caisse portée, au repos (« -1.25 - 1.75 * lift »)
const BRAS_PORTE = -1.25;

function paysan17(THREE, helpers, { fiche = {}, outil = null } = {}) {
	const { P } = helpers;
	const modele = module_personnage.build(THREE, helpers, { fiche });
	// Chaque pièce fille est rangée dans la première pièce animée qui la porte : la scène n'anime pas les coudes
	const animee = nom => {
		let n = RENOMMEES[nom] ?? nom;
		while(PIECES.includes(n) === false && modele.parents[n]) n = RENOMMEES[modele.parents[n]] ?? modele.parents[n];
		return PIECES.includes(n) ? n : 'bassin';
	};
	const pivots = {};
	for(const p of modele.parts) {
		const nom = RENOMMEES[p.name] ?? p.name;
		if(PIECES.includes(nom)) pivots[nom] ??= p.pivot;
	}
	// Le buste et le bassin tournent autour du point de paysan.js, à 0,62 m : pose() de la scène replace le torse à cette
	// hauteur à chaque image, quel que soit son pivot. Plié là, le buste plie aux hanches, ce qu'il fait en vrai
	pivots.torse = pivots.bassin = [0, HANCHE, 0];
	// La scène fond les pièces en un maillage peint par sommet, et refuse des pièces qui n'ont pas les mêmes attributs. La
	// couleur par sommet d'une pièce qui en porte (le gris mêlé d'une chevelure) reste : la scène peint toutes les pièces.
	// La scène éclaire chaque sommet selon sa normale et ne dessine que la face tournée vers la caméra : toute normale qui
	// regarde à l'opposé de sa face y fait un éclat, d'où qu'elle vienne (un sommet que le couvre-chef a rabattu, une pièce
	// mince repliée). `redresser()` la ramène sur la face, pièce par pièce
	const nue = g => {
		const n = g.index ? g.toNonIndexed() : g;
		for(const cle of Object.keys(n.attributes)) if(cle !== 'position' && cle !== 'normal' && cle !== 'color') n.deleteAttribute(cle);
		if(n.attributes.normal === undefined) n.computeVertexNormals();
		return module_outils.redresser(THREE, n);
	};
	const parts = [];
	// Le haut du bassin, la ceinture et le haut du pantalon, monte sous le haut jusqu'à 0,84 m, au-dessus du pli : quand pose()
	// penche le buste, le dos du haut avance et le découvrait derrière, en coin noir. Les triangles tout entiers au-dessus du
	// pli suivent donc le buste ; debout, rien ne bouge. Ceux qui chevauchent le pli restent au bassin, coupés en deux ils
	// sortaient en dents sous l'ourlet : leurs sommets d'arrière au-dessus du pli rentrent de 40 %, de quoi passer
	// sous le dos du haut penché, sous le haut quand la personne est debout. Le bas d'un haut long, que le personnage range déjà
	// au bassin (`ourlet`), garde sa forme : rentré, il se creusait dans le dos.
	// À l'inverse, le bas d'un haut long, tout entier sous le pli, reste au bassin : penché avec le buste, le bas de la
	// doudoune partait en arrière et faisait un sac derrière les talons
	const PLI = HANCHE;
	for(const p of modele.parts) {
		const name = animee(p.name), geometry = nue(p.geometry);
		if(name !== 'bassin' && name !== 'torse') {
			parts.push({ name, geometry, color: p.color, pivot: pivots[name] });
			continue;
		}
		const pos = geometry.attributes.position.array, nor = geometry.attributes.normal.array, cotes = { bassin: [[], []], torse: [[], []] };
		for(let i = 0; i < pos.length; i += 9) {
			const ys = [pos[i + 1], pos[i + 4], pos[i + 7]];
			const cote = name === 'bassin' ? (Math.min(...ys) > PLI ? 'torse' : 'bassin') : (Math.max(...ys) < PLI ? 'bassin' : 'torse');
			const triangle = Array.from(pos.slice(i, i + 9));
			if(name === 'bassin' && cote === 'bassin' && !p.ourlet) for(let s = 0; s < 9; s += 3) if(triangle[s + 1] > PLI && triangle[s + 2] < 0) triangle[s + 2] *= .6;
			cotes[cote][0].push(...triangle);
			cotes[cote][1].push(...nor.slice(i, i + 9));
		}
		for(const [cote, [cp, cn]] of Object.entries(cotes)) {
			if(cp.length === 0) continue;
			const g = new THREE.BufferGeometry();
			g.setAttribute('position', new THREE.Float32BufferAttribute(cp, 3));
			g.setAttribute('normal', new THREE.Float32BufferAttribute(cn, 3));
			parts.push({ name: cote, geometry: g, color: p.color, pivot: pivots[cote] });
		}
	}

	// L'outil, dans la main du bras gauche, placé comme dans paysan.js par rapport au poing : le manche à 14 cm devant lui,
	// incliné, le fer en bas devant les pieds. Manche à trois pans sans fonds : à cette taille, un quatrième pan ne se lit pas.
	// Le manche se redresse et penche vers le dehors : penché en arrière dans le plan du bras, comme sur paysan.js, son haut
	// rentrait dans l'épaule du personnage, dont la tête et les épaules sont plus larges. Ainsi incliné, il passe à 9 mm
	// au moins du bras, du haut et de la tête de chaque personne livrée.
	// Le bras de la scène est d'un tenant et tourne à l'épaule : en marchant, il passe à l'aplomb de l'épaule, qui descend
	// alors de 7 à 8 cm, et accroupi, l'épaule tombe à 70 cm. Tout ce que le bras porte plus loin de l'épaule que sa hauteur
	// passe alors sous le sol. Le fer se place donc à PORTEE au plus de l'épaule, MARCHE sous elle, une marge qui couvre la
	// marche et l'accroupi de figure.js, et le manche s'arrête au fer : debout, l'outil ne touche pas le sol, et dans aucun
	// geste il ne passe dessous
	if(outil === 'houe' || outil === 'fourche') {
		const [mx, my, mz] = modele.anchors.mainG;
		const INCLINAISON = .15, PENCHE = .25, MARCHE = .1;
		const epaule = new THREE.Vector3(...pivots.brasG);
		const PORTEE = epaule.y - MARCHE;
		const tenu = (g, color) => parts.push({ name: 'brasG', geometry: nue(g.translate(mx, my, mz)), color: P[color], pivot: pivots.brasG });
		const incline = g => g.rotateX(-INCLINAISON).rotateZ(-PENCHE);
		const axe = new THREE.Vector3(0, 1, 0).applyAxisAngle(new THREE.Vector3(1, 0, 0), -INCLINAISON).applyAxisAngle(new THREE.Vector3(0, 0, 1), -PENCHE);
		const main = new THREE.Vector3(mx, my, mz + .14);
		// Le fer et les dents se décrivent depuis le bas du manche ; chacun de leurs bouts fixe jusqu'où ce bas peut descendre
		// le long de l'axe sans sortir de PORTEE, et le plus contraint l'emporte
		const bouts = outil === 'houe'
			? [-.13, .13].flatMap(x => [-.09, .09].flatMap(y => [-.025, .025].map(z => new THREE.Vector3(x, y, z).applyAxisAngle(new THREE.Vector3(1, 0, 0), .25).add(new THREE.Vector3(0, 0, .08)))))
			: [-.08, 0, .08].map(x => new THREE.Vector3(x, 0, .06).addScaledVector(axe, -.28).add(new THREE.Vector3(0, -.028, 0)));
		const descente = Math.min(...bouts.map(bout => {
			const a = main.clone().add(bout).sub(epaule);
			const d = a.dot(axe);
			return d + Math.sqrt(d * d - a.lengthSq() + PORTEE * PORTEE);
		}));
		const bas = axe.clone().multiplyScalar(-descente).add(new THREE.Vector3(0, 0, .14));
		const haut = axe.clone().multiplyScalar(.68).add(new THREE.Vector3(0, 0, .14));
		const longueur = haut.distanceTo(bas);
		const milieu = bas.clone().lerp(haut, .5);
		tenu(incline(new THREE.CylinderGeometry(.035, .035, longueur, 3, 1, true)).translate(milieu.x, milieu.y, milieu.z), 'boisSombre');
		if(outil === 'houe') tenu(new THREE.BoxGeometry(.26, .18, .05).rotateX(.25).translate(bas.x, bas.y, bas.z + .08), 'tole');
		else for(const x of [-.08, 0, .08]) {
			const dent = bas.clone().addScaledVector(axe, -.14);
			tenu(incline(new THREE.CylinderGeometry(.02, .028, .28, 3, 1, true)).translate(dent.x + x, dent.y, dent.z + .06), 'tole');
		}
	}

	const boite = new THREE.Box3();
	for(const p of parts) { p.geometry.computeBoundingBox(); boite.union(p.geometry.boundingBox); }
	const h = boite.max.y;
	// Les paumes au bout des bras tendus de la caisse portée : la caisse s'y pose par son dessus. Celles de paysan.js, à
	// 0,88 m, sont calées sur ses épaules à lui ; ici les épaules sont plus hautes, et les bras arrivaient au-dessus de la caisse
	const [px, py, pz] = pivots.brasG, [mx, my, mz] = modele.anchors.mainG;
	const paume = new THREE.Vector3(mx - px, my - py, mz - pz).applyAxisAngle(new THREE.Vector3(1, 0, 0), BRAS_PORTE).add(new THREE.Vector3(px, py, pz));
	return {
		d: Math.max(boite.max.x - boite.min.x, boite.max.z - boite.min.z), h, parts,
		parents: { tete: 'torse', brasG: 'torse', brasD: 'torse', tibiaG: 'jambeG', tibiaD: 'jambeD' },
		// La caisse portée au bout des bras, la caisse posée à côté des pieds comme dans paysan.js, le haut de la tête coiffure
		// comprise
		anchors: { mains: [0, paume.y, paume.z], pieds: [.5, .13, .35], tete: [0, h, 0] },
	};
}

// ---------- le contrat de base/scene ----------
// Le paysan de la structure 17 (contrat.js, ci-dessus) remis au contrat de base/scene : la cuisson refuse toute clé qu'elle
// ne saurait garder, dont le pivot porté par chaque pièce. Les pivots se rangent donc par os, une fois chacun ; deux pièces
// du même os portent le même pivot dans contrat.js, celui de l'os.
// La cuisson garde la couleur par sommet d'une pièce, et figure.js la rend : la chevelure garde sa lisière fondue vers la peau
// et l'ombre d'une mèche sur sa voisine, sans lesquelles elle se lit comme un bonnet (skill 3d-perso, « La coiffure »). Les
// pièces d'un os qui en portent se fondent en une seule : une coiffure, sa calotte et ses mèches teintées, fait une pièce et
// une couleur. La couleur de cette pièce, que la cuisson demande à toute pièce, est la teinte de base de son dégradé : la
// moyenne de ses sommets pondérée par l'aire de leurs triangles, calculée en linéaire, où se mêle la lumière.
// Un personnage ne bouge pas au vent : sway 0 sur chaque pièce, comme le collecteur de helpers.js.

function degrades(THREE, parts) {
	// La pièce fondue prend la place de la première qu'elle reprend : l'ordre des triangles d'un os ne change pas pour les autres
	const rendues = [], parOs = new Map();
	for(const { name, geometry, color } of parts) {
		if(geometry.attributes.color === undefined) {
			rendues.push({ name, geometry, color, sway: 0 });
			continue;
		}
		if(parOs.has(name) === false) {
			parOs.set(name, []);
			rendues.push(name);
		}
		parOs.get(name).push(geometry);
	}
	const fondue = (name, geometries) => {
		const tables = ['position', 'normal', 'color'].map(cle => geometries.flatMap(g => Array.from(g.attributes[cle].array)));
		const [position, normal, couleur] = tables;
		// La cuisson garde la couleur par sommet sur un octet sRGB par canal : livrée déjà posée sur cet octet, elle est celle que
		// la scène lira, et sa relecture par la cuisson rend la source au lieu d'un arrondi d'un demi-pas
		const octet = new THREE.Color(), srgb = {};
		for(let i = 0; i < couleur.length; i += 3) {
			octet.setRGB(couleur[i], couleur[i + 1], couleur[i + 2], THREE.LinearSRGBColorSpace).getRGB(srgb, THREE.SRGBColorSpace);
			octet.setRGB(...[srgb.r, srgb.g, srgb.b].map(v => Math.round(v * 255) / 255), THREE.SRGBColorSpace);
			[couleur[i], couleur[i + 1], couleur[i + 2]] = [octet.r, octet.g, octet.b];
		}
		const somme = [0, 0, 0], a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3();
		let aire = 0;
		for(let i = 0; i < position.length; i += 9) {
			a.fromArray(position, i);
			b.fromArray(position, i + 3);
			c.fromArray(position, i + 6);
			const s = b.sub(a).cross(c.sub(a)).length() / 2;
			aire += s;
			for(let k = 0; k < 3; k++) somme[k] += s * (couleur[i + k] + couleur[i + 3 + k] + couleur[i + 6 + k]) / 3;
		}
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(position, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normal, 3));
		geometry.setAttribute('color', new THREE.Float32BufferAttribute(couleur, 3));
		// La couleur par sommet est linéaire, celle d'une pièce s'écrit en sRGB
		const base = new THREE.Color().setRGB(...somme.map(v => v / aire), THREE.LinearSRGBColorSpace);
		return { name, geometry, color: '#' + base.getHexString(), sway: 0 };
	};
	return rendues.map(p => typeof p === 'string' ? fondue(p, parOs.get(p)) : p);
}

// La cheville, en option du contrat comme le genou : `piedG` et `piedD`, enfants de `tibiaG` et `tibiaD`. Une scène qui recale
// le corps sur le plus bas des semelles a besoin d'un pied qui tourne à part, pour le garder à plat quand la jambe s'incline :
// d'un bloc avec le tibia, le pied d'une jambe portée en arrière pique de la pointe, et c'est elle qui touche le sol pendant que
// l'autre pied flotte. Le personnage ne la porte pas, son pied est d'un tenant avec le mollet : le pied est donc la pièce du
// tibia qui touche le sol et dont le dessus est le plus bas (le galet de la chaussure, ou celui du pied nu), avec toute pièce
// du tibia qui ne monte pas au-dessus. Une pièce qui passe la cheville, tige de botte, laçage, bas du pantalon, reste au tibia
// en entier : partagée, elle se déchirerait à la cheville dès que le pied tourne. Le pivot est sur l'axe de la jambe, à la
// hauteur du dessus du pied. Aucun triangle ne s'ajoute
const PIEDS = { tibiaG: 'piedG', tibiaD: 'piedD' }, DESSUS = .001;

function chevilles(THREE, parts, pivots, parents) {
	const boites = parts.map(p => new THREE.Box3().setFromBufferAttribute(p.geometry.attributes.position));
	const rendues = parts.map(p => p);
	for(const [tibia, pied] of Object.entries(PIEDS)) {
		const rangs = parts.map((p, k) => k).filter(k => parts[k].name === tibia);
		const sol = Math.min(...rangs.map(k => boites[k].min.y));
		const dessus = Math.min(...rangs.filter(k => boites[k].min.y <= sol + DESSUS).map(k => boites[k].max.y));
		for(const k of rangs) if(boites[k].max.y <= dessus + DESSUS) rendues[k] = { ...parts[k], name: pied };
		pivots[pied] = [pivots[tibia][0], dessus, pivots[tibia][2]];
		parents[pied] = tibia;
	}
	return rendues;
}

function paysan(THREE, helpers, options = {}) {
	const { d, h, parts, parents, anchors } = paysan17(THREE, helpers, options);
	const pivots = {};
	for(const p of parts) pivots[p.name] ??= p.pivot;
	const rendues = degrades(THREE, parts);
	return { d, h, aligned: false, parts: options.chevilles === true ? chevilles(THREE, rendues, pivots, parents) : rendues, pivots, parents, anchors };
}

// ---------- le paysan ----------
export const FICHE = {
	"corps": {
		"peau": {
			"valeur": "#e6b496",
			"fonde": "personnage anonyme, sans photo : peau claire, rosée"
		},
		"morphologie.hauteur": {
			"valeur": 1.68,
			"fonde": "personnage anonyme, sans photo : homme âgé, un peu tassé"
		},
		"morphologie.carrure": {
			"valeur": 1,
			"fonde": "personnage anonyme, sans photo : épaules moyennes"
		},
		"morphologie.corpulence": {
			"valeur": 1.13,
			"fonde": "personnage anonyme, sans photo : du ventre, la silhouette la plus ronde de l'équipe"
		}
	},
	"elements": [
		{
			"catalogue": "chemise",
			"reglages": {
				"couleur": "#ece6d6"
			},
			"fonde": "personnage anonyme, sans photo : chemise blanc cassé, manches longues, claire au soleil"
		},
		{
			"catalogue": "pantalon",
			"reglages": {
				"couleur": "#6b4a32"
			},
			"fonde": "personnage anonyme, sans photo : pantalon de velours brun"
		},
		{
			"catalogue": "bottes",
			"reglages": {
				"couleur": "#3a2f28"
			},
			"fonde": "personnage anonyme, sans photo : bottes brun très foncé"
		},
		{
			"catalogue": "sourcils",
			"reglages": {
				"couleur": "#bdb7ae",
				"epaisseur": 0.65
			},
			"fonde": "personnage anonyme, sans photo : sourcils gris épais"
		},
		{
			"catalogue": "pattes-oie",
			"reglages": {
				"marque": 0.7
			},
			"fonde": "personnage anonyme, sans photo : rides aux tempes : l'âge"
		},
		{
			"catalogue": "sillons",
			"reglages": {
				"marque": 0.6
			},
			"fonde": "personnage anonyme, sans photo : plis du nez à la bouche : l'âge"
		},
		{
			"catalogue": "courte",
			"reglages": {
				"couleur": "#c3beb6",
				"volume": 0.2,
				"frange": 0,
				"golfes": 0.6,
				"raie": "aucune",
				"degrade": 0.7
			},
			"fonde": "personnage anonyme, sans photo : cheveux courts gris clair d'une seule teinte, sans gris mêlé : la scène peint chaque pièce d'une couleur"
		},
		{
			"catalogue": "moustache",
			"reglages": {
				"couleur": "#cfcac2"
			},
			"fonde": "personnage anonyme, sans photo : moustache grise"
		},
		{
			"catalogue": "chapeau-paille",
			"reglages": {
				"couleur": "#e2bf63",
				"ruban": "#7a2e2a"
			},
			"fonde": "personnage anonyme, sans photo : chapeau de paille à ruban bordeaux, la silhouette du paysan d'avant reprise sur le nouveau corps"
		}
	]
};

// La cuisson appelle build() sans option : l'outil tenu est celui du module, et un autre outil fait un autre module
export function build(THREE, helpers, options = {}) {
	return paysan(THREE, helpers, { fiche: FICHE, outil: options.outil ?? null });
}
