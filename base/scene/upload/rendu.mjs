// Le rendu d'un personnage ou d'un objet du décor comme le site le dessine, sans carte graphique : le même code tourne dans node et dans un
// navigateur, et rend les mêmes pixels, que l'empreinte de l'image permet de comparer.
// L'ombrage est celui de MeshToonMaterial de three 0.170, faces avant seules : le soleil passe par la rampe (gradientMap,
// lue au point dot(N, L) / 2 + 1/2), le ciel par l'hémisphère (mélange du sol et du ciel selon N.y), le tout fois l'albédo
// sur π, en couleurs linéaires, puis encodé en sRGB sans tone mapping. Les normales s'interpolent par pixel et se
// normalisent. La vue est orthographique, sauf pour un profil qui porte une caméra : elle est alors en perspective, comme
// celle de l'éditeur d'avatar ou de l'éditeur de bâtiment. La lumière ne dépend pas de la caméra, et l'ombrage reste le
// même d'une vue à l'autre.
// Rien ici ne dépend de la page ni de node : THREE est passé en argument, et la compression d'un PNG aussi.

// Les scènes qui montrent une personne. Sans lumière propre, un profil prend celle de juillet (mois 6 des tables de saison
// des scènes) : soleil #fff1d6 vers #fff0c8 aux trois quarts, force 2,214, posé en (-95, 172,5, 70) de sa cible ; ciel
// #dcefff sur sol #93a06c, appoint 1,25 ; fond #bfe2f6
export const PROFILS = {
	// Le village : cinq paliers, lus en filtre linéaire
	bourg: { rampe: [120, 160, 200, 235, 255], filtre: 'lineaire' },
	// L'onglet 3D de la production : quatre paliers nets
	onglet: { rampe: [105, 165, 215, 255], filtre: 'net' },
	// L'éditeur d'avatar : la rampe du village, sa propre lumière sur un fond transparent, et sa caméra en pied, champ
	// vertical de 30°, à 4,1 m, l'œil 0,35 m au-dessus du point visé à mi-hauteur de la figure
	editeur: {
		rampe: [120, 160, 200, 235, 255],
		filtre: 'lineaire',
		lumiere: { soleil: ['#fff1d6', '#fff1d6', 0], force: 2, position: [-30, 50, 40], ciel: '#dcefff', sol: '#93a06c', appoint: 1.4, fond: null },
		camera: { champ: 30, recul: 4.1, hausse: .35, visee: .5 },
	},
	// L'éditeur des bâtiments du décor : MeshToonMaterial sans rampe, dont three tient le
	// soleil à 0,7 sous le seuil de 0,7 et à 1 au-dessus, sa lumière sur un fond transparent, et sa caméra, champ vertical de
	// 30°, qui recule jusqu'à voir toute la sphère du modèle avec 5 % de marge et monte de 0,55 fois ce recul
	batiment: {
		rampe: [178.5, 255],
		filtre: 'seuil',
		lumiere: { soleil: ['#fff1d6', '#fff1d6', 0], force: 2.2, position: [30, 50, 40], ciel: '#dcefff', sol: '#93a06c', appoint: 1.6, fond: null },
		camera: { champ: 30, marge: 1.05, montee: .55 },
	},
};
export const LUMIERE = { soleil: ['#fff1d6', '#fff0c8', .75], force: 2 + 3 / 3.5 * .25, position: [-95, 172.5, 70], ciel: '#dcefff', sol: '#93a06c', appoint: 1.25, fond: '#bfe2f6' };

// Les vues des captures : azimut et hauteur de l'œil en degrés (0 de face, la personne regarde vers les z positifs), et la
// part de la figure cadrée (1 : la figure entière ; `tete` : le haut de la tête seul)
export const VUES = {
	'trois-quarts': { az: 35, el: 12 },
	face: { az: 0, el: 5 },
	dos: { az: 180, el: 5 },
	profil: { az: 90, el: 5 },
	dessus: { az: 20, el: 62 },
	'portrait-trois-quarts': { az: 35, el: 10, tete: true },
	'portrait-dos': { az: 180, el: 15, tete: true },
};

const lineaire = (THREE, hex) => { const c = new THREE.Color(hex); return [c.r, c.g, c.b]; };
const srgb = c => c <= .0031308 ? c * 12.92 : 1.055 * Math.pow(c, 1 / 2.4) - .055;

// objets : [{ geometry, matrixWorld, color }], déjà posés. Rend { largeur, hauteur, pixels } en RGBA 8 bits
export function rendre(THREE, objets, { profil = 'bourg', vue = 'trois-quarts', largeur = 400, hauteur = 600, sur = 2, cadre = null } = {}) {
	const P = PROFILS[profil], V = typeof vue === 'string' ? VUES[vue] : vue, W = largeur * sur, H = hauteur * sur;
	const E = P.lumiere ?? LUMIERE, C = P.camera ?? null;
	const soleilA = lineaire(THREE, E.soleil[0]), soleilB = lineaire(THREE, E.soleil[1]);
	const soleil = soleilA.map((x, i) => (x + (soleilB[i] - x) * E.soleil[2]) * E.force);
	const L = new THREE.Vector3(...E.position).normalize();
	const ciel = lineaire(THREE, E.ciel).map(x => x * E.appoint), sol = lineaire(THREE, E.sol).map(x => x * E.appoint);
	// Un fond nul est transparent : un pixel prend la couleur moyenne de ses échantillons couverts, et leur part en opacité
	const fond = E.fond === null ? null : lineaire(THREE, E.fond);
	const rampe = P.rampe.map(v => v / 255), nr = rampe.length;
	const lireRampe = u => {
		if(P.filtre === 'net') return rampe[Math.min(nr - 1, Math.max(0, Math.floor(u * nr)))];
		if(P.filtre === 'seuil') return u < .7 ? rampe[0] : rampe[1];
		const x = u * nr - .5, i = Math.floor(x), t = x - i;
		const a = rampe[Math.min(nr - 1, Math.max(0, i))], b = rampe[Math.min(nr - 1, Math.max(0, i + 1))];
		return a + (b - a) * t;
	};

	// Les triangles dans le monde : position, normale et couleur linéaire par sommet
	const tris = [];
	const v = new THREE.Vector3(), n = new THREE.Vector3(), nm = new THREE.Matrix3();
	for(const o of objets) {
		const g = o.geometry.index ? o.geometry.toNonIndexed() : o.geometry;
		const pos = g.attributes.position.array, nor = g.attributes.normal.array, col = g.attributes.color?.array;
		const base = col ? null : lineaire(THREE, o.color);
		nm.getNormalMatrix(o.matrixWorld);
		for(let i = 0; i < pos.length; i += 3) {
			v.set(pos[i], pos[i + 1], pos[i + 2]).applyMatrix4(o.matrixWorld);
			n.set(nor[i], nor[i + 1], nor[i + 2]).applyMatrix3(nm).normalize();
			tris.push(v.x, v.y, v.z, n.x, n.y, n.z, col ? col[i] : base[0], col ? col[i + 1] : base[1], col ? col[i + 2] : base[2]);
		}
	}
	const S = 9, ns = tris.length / S;

	// La caméra, cadrée sur la figure entière ou sur le haut de la tête. Une caméra de profil vise une part de la hauteur de
	// cette boite : passer celle de la figure au repos en `cadre` garde le point visé d'une pose à l'autre
	const boite = cadre ?? (() => {
		const b = [Infinity, Infinity, Infinity, -Infinity, -Infinity, -Infinity];
		for(let k = 0; k < ns; k++) for(let a = 0; a < 3; a++) { b[a] = Math.min(b[a], tris[k * S + a]); b[a + 3] = Math.max(b[a + 3], tris[k * S + a]); }
		if(V.tete) b[1] = b[4] - (b[4] - b[1]) * .3;
		return b;
	})();
	const az = V.az * Math.PI / 180, el = V.el * Math.PI / 180;
	const croix = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
	const unite = a => { const l = Math.hypot(...a); return a.map(x => x / l); };
	const hautMonde = [0, 1, 0];

	// La position à l'écran et la profondeur de chaque sommet
	const ecran = new Float32Array(ns * 3);
	let camera;
	if(C === null) {
		// L'œil sur la sphère de la vue, à l'infini ; le cadre serre les huit coins de la boite, vus de l'œil, à 6 % du bord
		const d = [Math.sin(az) * Math.cos(el), Math.sin(el), Math.cos(az) * Math.cos(el)];
		const f = d.map(x => -x);
		const droite = unite(croix(f, hautMonde)), haut = croix(droite, f);
		let umin = Infinity, umax = -Infinity, vmin = Infinity, vmax = -Infinity;
		for(const x of [boite[0], boite[3]]) for(const y of [boite[1], boite[4]]) for(const z of [boite[2], boite[5]]) {
			const u = x * droite[0] + y * droite[1] + z * droite[2], w = x * haut[0] + y * haut[1] + z * haut[2];
			umin = Math.min(umin, u); umax = Math.max(umax, u); vmin = Math.min(vmin, w); vmax = Math.max(vmax, w);
		}
		const echelle = .88 * Math.min(W / (umax - umin), H / (vmax - vmin));
		const cu = (umin + umax) / 2, cv = (vmin + vmax) / 2;
		const centre = [cu * droite[0] + cv * haut[0], cu * droite[1] + cv * haut[1], cu * droite[2] + cv * haut[2]];
		for(let k = 0; k < ns; k++) {
			const q = [tris[k * S] - centre[0], tris[k * S + 1] - centre[1], tris[k * S + 2] - centre[2]];
			ecran[k * 3] = W / 2 + (q[0] * droite[0] + q[1] * droite[1] + q[2] * droite[2]) * echelle;
			ecran[k * 3 + 1] = H / 2 - (q[0] * haut[0] + q[1] * haut[1] + q[2] * haut[2]) * echelle;
			ecran[k * 3 + 2] = q[0] * f[0] + q[1] * f[1] + q[2] * f[2];
		}
		// Pour qu'un rendu WebGL de comparaison se place au même endroit : centre, axes, et demi-largeur et demi-hauteur de la
		// vue en mètres
		camera = { centre, droite, haut, devant: f, demiLargeur: W / 2 / echelle, demiHauteur: H / 2 / echelle };
	}
	else {
		let cible, oeil;
		if(C.marge === undefined) {
			// L'œil tourne autour de la figure à hauteur fixe, la hauteur de la vue n'y joue pas
			cible = [0, boite[4] * C.visee, 0];
			oeil = [Math.sin(az) * C.recul, cible[1] + C.hausse, Math.cos(az) * C.recul];
		}
		else {
			// L'œil vise le centre de la boite et recule jusqu'à voir sa sphère ; une vue plus haute que la montée du profil
			// (`dessus`) monte d'autant
			cible = [0, 1, 2].map(a => (boite[a] + boite[a + 3]) / 2);
			const rayon = Math.hypot(boite[3] - boite[0], boite[4] - boite[1], boite[5] - boite[2]) / 2;
			const recul = rayon / Math.sin(C.champ / 2 * Math.PI / 180) * C.marge, montee = Math.max(C.montee, Math.tan(el));
			oeil = [cible[0] + Math.sin(az) * recul, cible[1] + montee * recul, cible[2] + Math.cos(az) * recul];
		}
		const f = unite(cible.map((x, i) => x - oeil[i]));
		const droite = unite(croix(f, hautMonde)), haut = croix(droite, f);
		const focale = H / 2 / Math.tan(C.champ / 2 * Math.PI / 180);
		for(let k = 0; k < ns; k++) {
			const q = [tris[k * S] - oeil[0], tris[k * S + 1] - oeil[1], tris[k * S + 2] - oeil[2]];
			const z = q[0] * f[0] + q[1] * f[1] + q[2] * f[2];
			ecran[k * 3] = W / 2 + (q[0] * droite[0] + q[1] * droite[1] + q[2] * droite[2]) / z * focale;
			ecran[k * 3 + 1] = H / 2 - (q[0] * haut[0] + q[1] * haut[1] + q[2] * haut[2]) / z * focale;
			ecran[k * 3 + 2] = z;
		}
		// Pour qu'un rendu WebGL de comparaison pose une PerspectiveCamera au même endroit
		camera = { oeil, cible, champ: C.champ };
	}

	const couleur = new Float32Array(W * H * 3), profondeur = new Float32Array(W * H).fill(Infinity);
	if(fond !== null) for(let i = 0; i < W * H; i++) couleur.set(fond, i * 3);
	const nn = [0, 0, 0];
	for(let t = 0; t < ns; t += 3) {
		const x0 = ecran[t * 3], y0 = ecran[t * 3 + 1], x1 = ecran[t * 3 + 3], y1 = ecran[t * 3 + 4], x2 = ecran[t * 3 + 6], y2 = ecran[t * 3 + 7];
		const z0 = ecran[t * 3 + 2], z1 = ecran[t * 3 + 5], z2 = ecran[t * 3 + 8];
		// Un triangle qui passe derrière l'œil ne se projette pas ; la figure reste devant la caméra de l'éditeur
		if(C !== null && (z0 <= 0 || z1 <= 0 || z2 <= 0)) continue;
		// Faces avant seules : dans l'écran, y vers le bas, une face avant tourne dans le sens des aiguilles d'une montre
		const aire = (x1 - x0) * (y2 - y0) - (x2 - x0) * (y1 - y0);
		if(aire >= 0) continue;
		const xmin = Math.max(0, Math.floor(Math.min(x0, x1, x2))), xmax = Math.min(W - 1, Math.ceil(Math.max(x0, x1, x2)));
		const ymin = Math.max(0, Math.floor(Math.min(y0, y1, y2))), ymax = Math.min(H - 1, Math.ceil(Math.max(y0, y1, y2)));
		for(let py = ymin; py <= ymax; py++) for(let px = xmin; px <= xmax; px++) {
			const cx = px + .5, cy = py + .5;
			let w0 = ((x1 - cx) * (y2 - cy) - (x2 - cx) * (y1 - cy)) / aire;
			let w1 = ((x2 - cx) * (y0 - cy) - (x0 - cx) * (y2 - cy)) / aire;
			let w2 = 1 - w0 - w1;
			if(w0 < 0 || w1 < 0 || w2 < 0) continue;
			let z;
			if(C === null) z = w0 * z0 + w1 * z1 + w2 * z2;
			else {
				// En perspective, ce qui varie linéairement à l'écran est l'attribut sur la profondeur : les poids se corrigent
				const s = w0 / z0 + w1 / z1 + w2 / z2;
				z = 1 / s;
				w0 = w0 / z0 * z; w1 = w1 / z1 * z; w2 = w2 / z2 * z;
			}
			const i = py * W + px;
			if(z >= profondeur[i]) continue;
			profondeur[i] = z;
			const a = t * S, b = a + S, c = b + S;
			for(let k = 0; k < 3; k++) nn[k] = w0 * tris[a + 3 + k] + w1 * tris[b + 3 + k] + w2 * tris[c + 3 + k];
			const l = Math.hypot(nn[0], nn[1], nn[2]) || 1;
			const nx = nn[0] / l, ny = nn[1] / l, nz = nn[2] / l;
			const direct = lireRampe((nx * L.x + ny * L.y + nz * L.z) * .5 + .5), poids = .5 * ny + .5;
			for(let k = 0; k < 3; k++) {
				const albedo = w0 * tris[a + 6 + k] + w1 * tris[b + 6 + k] + w2 * tris[c + 6 + k];
				couleur[i * 3 + k] = albedo * (direct * soleil[k] + sol[k] + (ciel[k] - sol[k]) * poids) / Math.PI;
			}
		}
	}

	// Le suréchantillonnage se moyenne en linéaire, puis s'encode en sRGB
	const pixels = new Uint8ClampedArray(largeur * hauteur * 4);
	for(let y = 0; y < hauteur; y++) for(let x = 0; x < largeur; x++) {
		const o = (y * largeur + x) * 4;
		let couverts = sur * sur;
		if(fond === null) {
			couverts = 0;
			for(let sy = 0; sy < sur; sy++) for(let sx = 0; sx < sur; sx++) if(profondeur[(y * sur + sy) * W + x * sur + sx] !== Infinity) couverts++;
		}
		for(let k = 0; k < 3; k++) {
			let somme = 0;
			for(let sy = 0; sy < sur; sy++) for(let sx = 0; sx < sur; sx++) somme += couleur[((y * sur + sy) * W + x * sur + sx) * 3 + k];
			pixels[o + k] = couverts === 0 ? 0 : Math.round(Math.min(1, Math.max(0, srgb(somme / couverts))) * 255);
		}
		pixels[o + 3] = fond === null ? Math.round(couverts / (sur * sur) * 255) : 255;
	}
	return { largeur, hauteur, pixels, camera };
}

// La figure posée comme les scènes la montent : chaque pièce dans le groupe de son pivot, rangé sous celui de son
// parent, puis tournée de la pose. Rend les objets à passer à rendre()
export function poser(THREE, model, pose = {}) {
	const racine = new THREE.Group(), groupes = {}, pivots = {}, maillages = [];
	for(const p of model.parts) pivots[p.name] ??= p.pivot ?? [0, 0, 0];
	for(const nom in pivots) groupes[nom] = new THREE.Group();
	for(const nom in pivots) {
		const parent = model.parents?.[nom] && groupes[model.parents[nom]] ? model.parents[nom] : null, origine = parent ? pivots[parent] : [0, 0, 0];
		groupes[nom].position.set(pivots[nom][0] - origine[0], pivots[nom][1] - origine[1], pivots[nom][2] - origine[2]);
		(parent ? groupes[parent] : racine).add(groupes[nom]);
	}
	for(const p of model.parts) {
		const m = new THREE.Mesh(p.geometry.clone().translate(...pivots[p.name].map(x => -x)));
		m.userData.part = p;
		groupes[p.name].add(m);
		maillages.push(m);
	}
	for(const nom in pose) if(groupes[nom] && Array.isArray(pose[nom])) groupes[nom].rotation.set(...pose[nom]);
	racine.updateMatrixWorld(true);
	const objets = maillages.map(m => ({ geometry: m.geometry, matrixWorld: m.matrixWorld, color: m.userData.part.color }));
	return { racine, groupes, maillages, objets, monde: m => m.geometry.clone().applyMatrix4(m.matrixWorld) };
}

// L'empreinte d'une image, la même dans node et dans Chrome : FNV-1a sur 32 bits, deux fois avec deux graines, pour ne pas
// dépendre d'une bibliothèque de hachage d'un côté seulement
export function empreinte(pixels) {
	let a = 0x811c9dc5, b = 0x01000193 ^ 0x5bd1e995;
	for(let i = 0; i < pixels.length; i++) {
		a = Math.imul(a ^ pixels[i], 0x01000193) >>> 0;
		b = Math.imul(b ^ pixels[i], 0x01000193) >>> 0;
		b = (b ^ (b >>> 13)) >>> 0;
	}
	return a.toString(16).padStart(8, '0') + b.toString(16).padStart(8, '0');
}

// La liste des captures d'une personne : les vues de la figure au repos, puis au bourg chaque pose et chaque émotion de trois
// quarts, l'émotion aussi à 20 pixels par mètre, et la figure aux tailles des scènes (20 et 65 pixels par mètre, la figure
// mesurant environ 1,7 m). Le profil onglet ne rend que la figure au repos : ses gestes sont ceux de figure.js
export function captures(THREE, model, { profil = 'bourg', POSES = {}, EMOTIONS = {} } = {}) {
	const liste = [], repos = poser(THREE, model).objets, grand = { largeur: 400, hauteur: 600 };
	for(const vue of Object.keys(VUES)) liste.push({ quoi: vue, objets: repos, options: { vue, ...(VUES[vue].tete ? { largeur: 400, hauteur: 400 } : grand) } });
	if(profil === 'bourg') {
		for(const [nom, pose] of Object.entries(POSES)) liste.push({ quoi: `pose ${nom}`, objets: poser(THREE, model, pose).objets, options: { vue: 'trois-quarts', ...grand } });
		for(const nom of Object.keys(EMOTIONS)) {
			const e = model.emotions[nom];
			const parts = model.parts.map(p => p.role && e.visage[p.role] ? { ...p, geometry: e.visage[p.role] } : p);
			const objets = poser(THREE, { ...model, parts }, e.pose).objets;
			liste.push({ quoi: `émotion ${nom}, de trois quarts`, objets, options: { vue: 'trois-quarts', ...grand } });
			liste.push({ quoi: `émotion ${nom}, à 20 px/m`, objets, options: { vue: 'dessus', largeur: 40, hauteur: 40, sur: 4 } });
		}
	}
	liste.push({ quoi: 'à 20 px/m, vue de la scène', objets: repos, options: { vue: 'dessus', largeur: 40, hauteur: 40, sur: 4 } });
	liste.push({ quoi: 'à 65 px/m, vue de la scène', objets: repos, options: { vue: 'dessus', largeur: 120, hauteur: 120, sur: 4 } });
	return liste.map(c => ({ ...c, options: { profil, ...c.options } }));
}

// Une image de rendre() en PNG RGBA : signature, IHDR, IDAT compressé, IEND, chaque bloc avec son CRC. `deflate` compresse
// un Uint8Array au format zlib, `zlib.deflateSync` dans node
const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for(let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
const crc = octets => { let c = 0xffffffff; for(const x of octets) c = crcTable[(c ^ x) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
const concatener = morceaux => {
	const tout = new Uint8Array(morceaux.reduce((somme, m) => somme + m.length, 0));
	morceaux.reduce((position, m) => { tout.set(m, position); return position + m.length; }, 0);
	return tout;
};
const bloc = (type, donnees) => {
	const entete = new Uint8Array(8), fin = new Uint8Array(4), nom = new TextEncoder().encode(type);
	new DataView(entete.buffer).setUint32(0, donnees.length);
	entete.set(nom, 4);
	new DataView(fin.buffer).setUint32(0, crc(concatener([nom, donnees])));
	return concatener([entete, donnees, fin]);
};
export function png({ largeur, hauteur, pixels }, deflate) {
	const ihdr = new Uint8Array(13), vue = new DataView(ihdr.buffer);
	vue.setUint32(0, largeur);
	vue.setUint32(4, hauteur);
	ihdr[8] = 8;
	ihdr[9] = 6;
	// Chaque ligne commence par l'octet de filtre, 0 : aucun
	const brut = new Uint8Array((largeur * 4 + 1) * hauteur);
	for(let y = 0; y < hauteur; y++) brut.set(new Uint8Array(pixels.buffer, pixels.byteOffset + y * largeur * 4, largeur * 4), y * (largeur * 4 + 1) + 1);
	return concatener([new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), bloc('IHDR', ihdr), bloc('IDAT', new Uint8Array(deflate(brut))), bloc('IEND', new Uint8Array(0))]);
}
