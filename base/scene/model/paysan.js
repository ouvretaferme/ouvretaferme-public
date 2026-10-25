// Le paysan anonyme de la scène : le personnage du bourg (salopette, bottes, tête cubique aux arêtes abattues, chapeau de paille
// à très large bord) ramené à notre échelle, 1,8 m chapeau compris. Tout est en boites franches, le budget va au chapeau, à la
// salopette et aux bottes. Debout, bras le long du corps, visage vers +Z.
// Un personnage articulé : name est l'os qui porte la pièce, pivots dit où chaque os tourne, parents lesquels suivent le torse.
// Les variantes (paysan.houe.js, paysan.fourche.js, paysan.casquette.js) appellent build() avec leurs options, parce que la
// cuisson n'en passe aucune. options : tenue, salopette, peau (noms de P), coiffe ('paille', 'casquette', 'bonnet', null),
// couleurCoiffe, outil ('houe', 'fourche', null).
export function build(THREE, helpers, options = {}) {
	const { P, facets, box, block, drum, collector, measure } = helpers;
	const { parts, pivots, add } = collector();
	const {
		tenue = 'bleu', salopette = 'encre', peau = 'peau', coiffe = 'paille',
		couleurCoiffe = coiffe === 'paille' ? 'paille' : 'rouge', outil = null,
	} = options;

	const HIP = .62, WAIST = .64, NECK = 1.11, SHOULDER = 1.03, HEAD_TOP = 1.64;
	const joints = {
		bassin: [0, HIP, 0], torse: [0, HIP, 0], tete: [0, NECK, 0],
		brasG: [.32, SHOULDER, 0], brasD: [-.32, SHOULDER, 0], jambeG: [.15, HIP, 0], jambeD: [-.15, HIP, 0],
	};
	const put = (geometry, color, name) => add(geometry, P[color], name, joints[name]);

	// Pavé aux quatre arêtes verticales abattues, posé sur y0 : la tête reste une boite sans être une brique
	const chamfered = (y0, sx, sy, sz, c) => {
		const a = sx / 2, b = sz / 2;
		const outline = [[-a + c, -b], [a - c, -b], [a, -b + c], [a, b - c], [a - c, b], [-a + c, b], [-a, b - c], [-a, -b + c]];
		const shape = new THREE.Shape(outline.map(([x, y]) => new THREE.Vector2(x, y)));
		return facets(new THREE.ExtrudeGeometry(shape, { depth: sy, bevelEnabled: false }).rotateX(-Math.PI / 2).translate(0, y0, 0));
	};
	// Tronc de pyramide sans fond, plus large en haut : la tige de la botte, dont le dessous est caché par le pied
	const flared = (x, y0, z, bottom, top, h) => {
		const corners = [[-1, 1], [1, 1], [1, -1], [-1, -1]];
		const low = corners.map(([sx, sz]) => [x + sx * bottom, y0, z + sz * bottom]);
		const high = corners.map(([sx, sz]) => [x + sx * top, y0 + h, z + sz * top]);
		const triangles = [[high[0], high[1], high[2]], [high[0], high[2], high[3]]];
		for(let i = 0; i < 4; i++) {
			const j = (i + 1) % 4;
			triangles.push([low[i], low[j], high[j]], [low[i], high[j], high[i]]);
		}
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(triangles.flat(2), 3));
		geometry.computeVertexNormals();
		return geometry;
	};

	// Le bassin déborde du torse de 3 cm de chaque côté : c'est le bas de la salopette, et aucune face ne partage un plan
	put(block(0, .5, 0, .48, .16, .33), salopette, 'bassin');

	put(block(0, WAIST, 0, .42, NECK - WAIST, .3), tenue, 'torse');
	// La bavette et les bretelles sont de la couleur du pantalon : c'est elles qui font lire une salopette et non un pull
	put(box(0, .8, .165, .3, .3, .09), salopette, 'torse');
	for(const side of [1, -1]) {
		put(box(side * .12, .89, 0, .08, .47, .38), salopette, 'torse');
	}

	put(chamfered(NECK, .56, HEAD_TOP - NECK, .52, .09), peau, 'tete');
	put(box(0, 1.32, .29, .11, .12, .09), peau, 'tete');

	if(coiffe === 'paille') {
		// Le bord fait plus d'une fois et demie les épaules : c'est lui qui signe la silhouette du producteur
		put(drum(0, 1.59, 0, .54, .06), couleurCoiffe, 'tete');
		put(drum(0, 1.65, 0, .28, .15, 8, .23), couleurCoiffe, 'tete');
		// Le ruban reprend la tenue, sans couleur de plus. Ouvert : ses deux fonds seraient dans la calotte
		put(facets(new THREE.CylinderGeometry(.27, .29, .075, 8, 1, true).translate(0, 1.69, 0)), tenue, 'tete');
	} else if(coiffe === 'casquette') {
		put(chamfered(1.52, .61, .22, .57, .1), couleurCoiffe, 'tete');
		put(box(0, 1.55, .4, .45, .06, .27), couleurCoiffe, 'tete');
	} else if(coiffe === 'bonnet') {
		put(chamfered(1.49, .61, .22, .57, .1), couleurCoiffe, 'tete');
		put(block(0, 1.71, 0, .17, .12, .17), couleurCoiffe, 'tete');
	}

	for(const [name, side] of [['brasG', 1], ['brasD', -1]]) {
		// Les bras s'écartent de quelques degrés depuis l'épaule : pendus droits, ils se fondraient dans le torse en silhouette
		const tilt = geometry => geometry.translate(-side * .32, -SHOULDER, 0).rotateZ(side * .24).translate(side * .32, SHOULDER, 0);
		put(tilt(box(side * .325, .89, 0, .13, .38, .15)), tenue, name);
		put(tilt(box(side * .325, .61, 0, .15, .2, .18)), peau, name);
	}

	// L'outil est une pièce du bras qui le tient, le gauche (+X), pour suivre son geste.
	// Manche à trois pans sans fonds : à cette taille, un quatrième pan ne se lit pas.
	// Le bras tourne d'un tenant autour de l'épaule, sans coude : tout point de l'outil reste à moins de la hauteur d'épaule moins
	// 10 cm de son pivot, sinon un geste le fait passer sous le sol. D'où le fer remonté le long du manche, et le manche arrêté au fer.
	if(outil === 'houe' || outil === 'fourche') {
		const [px, py] = joints.brasG, hold = g => facets(g.translate(px + .1, py, 0));
		const RISE = .24, up = [RISE * Math.cos(.35), -RISE * Math.sin(.35)];
		const top = new THREE.Vector3(0, -.42 + .68 * Math.cos(.35), .14 - .68 * Math.sin(.35));
		const end = outil === 'houe'
			? new THREE.Vector3(0, -.96 + up[0], .42 + up[1])
			: new THREE.Vector3(0, -.93 + up[0] + .15 * Math.cos(.35), .37 + up[1] - .15 * Math.sin(.35));
		const axis = end.clone().sub(top);
		const handle = new THREE.CylinderGeometry(.04, .04, axis.length(), 3, 1, true)
			.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), axis.clone().normalize()))
			.translate(...top.clone().add(end).multiplyScalar(.5).toArray());
		put(hold(handle), 'boisSombre', 'brasG');
		if(outil === 'houe') {
			put(hold(new THREE.BoxGeometry(.3, .2, .06).rotateX(.25).translate(0, -.96 + up[0], .42 + up[1])), 'tole', 'brasG');
		} else {
			for(const x of [-.09, 0, .09]) {
				put(hold(new THREE.CylinderGeometry(.022, .03, .3, 3, 1, true).rotateX(-.35).translate(x, -.93 + up[0], .37 + up[1])), 'tole', 'brasG');
			}
		}
	}

	for(const [name, side] of [['jambeG', 1], ['jambeD', -1]]) {
		const x = side * .18;
		put(block(x, .33, 0, .15, .29, .18), salopette, name);
		// La tige monte jusque sous le genou et avale le bas du pantalon
		put(flared(x, .09, 0, .09, .125, .32), 'boisSombre', name);
		put(block(x, 0, .06, .2, .11, .35), 'boisSombre', name);
	}

	const { d, h } = measure(parts);
	return {
		d, h, aligned: false, parts, pivots,
		// La tête et les bras tournent avec le torse, les jambes et le bassin restent à la racine
		parents: { tete: 'torse', brasG: 'torse', brasD: 'torse' },
		// Où la scène pose ce que le paysan porte : la caisse tenue aux mains, la caisse posée à ses pieds
		anchors: { mains: [0, .88, .42], pieds: [.5, .13, .35], tete: [0, h, 0] },
	};
}
