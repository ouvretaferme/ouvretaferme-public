// Les personnages d'une scène et ce qu'ils portent, montés depuis les modèles cuits.
// Module ES sans import ni horloge : l'instant d'un geste est un argument, en secondes, et la phase du pas vient de l'appelant.
// Un personnage cuit porte `pivots` (os vers [x, y, z]) et `parents` (os vers l'os qui le porte) : chaque os devient un groupe
// posé à son pivot, qui tourne avec son parent, et ses pièces y sont fondues. Un os sans parent pend à la racine.

export function sceneCreateFigure(THREE, addons, geometry, sceneBuildModel) {

	// Les pièces fondues en une géométrie, chaque sommet à la couleur de sa pièce : un os se dessine en un appel, quel que soit
	// son nombre de couleurs. `colors` remplace la couleur d'une pièce par son nom, le contenu d'une caisse par exemple. Une pièce
	// qui porte déjà ses couleurs par sommet les garde : un modèle monté sans cuisson peut fondre la lisière d'une chevelure
	const painted = (parts, colors) => addons.mergeGeometries(parts.map(part => {

		if(part.geometry.hasAttribute('color') && colors[part.name] === undefined) {
			return part.geometry;
		}

		return geometry.paint(part.geometry, new THREE.Color(colors[part.name] ?? '#'+ part.color));

	}));

	const pair = (bones, right, left) => (bones[right] && bones[left]) ? [bones[right], bones[left]] : null;

	const pointsOf = geometry => Array.from({ length: geometry.attributes.position.count }, (_, i) => new THREE.Vector3().fromBufferAttribute(geometry.attributes.position, i));
	const centroid = points => points.reduce((sum, point) => sum.add(point), new THREE.Vector3()).divideScalar(points.length);

	// Porté, l'outil a le poing serré sur le manche, à TOOL_GRIP de sa longueur depuis le fer, son point d'équilibre. Le fer va devant,
	// plus bas que le poing de TOOL_PITCH ; le haut du manche s'écarte du corps de TOOL_OUT pour passer au large de la cuisse qui balance
	const TOOL_GRIP = .35;
	const TOOL_PITCH = .35;
	const TOOL_OUT = .15;

	/**
	 * Monte un personnage cuit. Avec `tool`, l'outil qu'il tient se monte à part, dans son bras : un module de personnage range ses
	 * pièces dans le bras gauche après tout le corps, le manche en tête (model/paysan-a.js), et pose() le porte autrement en marchant.
	 */
	function assemble(baked, slug, material, { tool = false } = {}) {

		const model = sceneBuildModel(THREE, baked, slug);

		if(model.pivots === undefined) {
			throw new Error('sceneCreateFigure : '+ slug +' ne porte pas de pivots');
		}

		const root = new THREE.Group();
		const bones = {};
		const parents = model.parents ?? {};

		let first = model.parts.length;

		while(tool && first > 0 && model.parts[first - 1].name === 'brasG') {
			first--;
		}

		const held = model.parts.slice(first);

		const make = name => {

			if(bones[name] !== undefined) {
				return bones[name];
			}

			const [x, y, z] = model.pivots[name];
			const parent = parents[name] ?? null;
			const bone = new THREE.Group();

			// Un os se place depuis le pivot de celui qui le porte : les pivots du modèle sont tous donnés depuis le pied
			bone.position.set(x, y, z);
			if(parent !== null) {
				bone.position.sub(new THREE.Vector3(...model.pivots[parent]));
			}

			(parent === null ? root : make(parent)).add(bone);
			bones[name] = bone;

			const parts = model.parts.filter(part => part.name === name && held.includes(part) === false);

			if(parts.length > 0) {
				bone.add(new THREE.Mesh(painted(parts, {}).translate(-x, -y, -z), material));
			}

			return bone;

		};

		Object.keys(model.pivots).forEach(make);

		const legs = pair(bones, 'jambeD', 'jambeG');
		const arms = pair(bones, 'brasD', 'brasG');
		// Lus avant que l'outil ne pende au bras
		const fists = (arms === null) ? null : arms.map(fist);

		let carried = null;

		if(held.length > 0) {

			const shoulder = new THREE.Vector3(...model.pivots.brasG);
			const mesh = new THREE.Mesh(painted(held, {}).translate(-shoulder.x, -shoulder.y, -shoulder.z), material);

			bones.brasG.add(mesh);
			carried = { mesh, ...carry(fists[1], held[0].geometry, shoulder) };

		}

		return {
			root,
			bones,
			pivots: model.pivots,
			anchors: model.anchors ?? {},
			h: model.h,
			torso: bones.torse,
			// La hauteur du buste au repos, d'où pose() le fait descendre
			rest: bones.torse.position.y,
			// Le côté droit vient en premier : le bras qui tient l'outil, brasG, côté +X, est le second, le rang par lequel pose() le désigne
			legs,
			arms,
			fists,
			// L'outil monté à part, { mesh, quaternion, position } : sa place portée en marche, dans le repère du bras
			tool: carried,
			// Le genou est une option du contrat : un corps sans tibias garde ses jambes d'un bloc
			knees: pair(bones, 'tibiaD', 'tibiaG'),
			// Un corps sans jambes ne marche pas : pose() ne le fait pas descendre
			soles: (legs === null) ? null : soles(bones)
		};

	}

	// Le poing : le milieu de ce que le bras porte dans ses huit derniers centimètres du modèle, bras tombant, dans le repère de son épaule
	function fist(arm) {

		const points = arm.children.filter(child => child.isMesh).flatMap(mesh => pointsOf(mesh.geometry));
		const bottom = Math.min(...points.map(point => point.y));

		return centroid(points.filter(point => point.y < bottom + .08));

	}

	/**
	 * La place de l'outil porté, dans le repère du bras où le module l'a posé : le poing sur le manche, le fer devant et plus bas, les
	 * dents ou la lame à plat. Le manche se lit en deux moitiés, du côté du fer et du côté du haut.
	 */
	function carry(fist, handle, shoulder) {

		const points = pointsOf(handle).map(point => point.sub(shoulder));
		const high = points.reduce((a, b) => (b.y > a.y) ? b : a);
		const low = points.reduce((a, b) => (b.y < a.y) ? b : a);
		const middle = high.clone().lerp(low, .5);

		const iron = centroid(points.filter(point => point.y < middle.y));
		const top = centroid(points.filter(point => point.y >= middle.y));
		const grip = iron.clone().lerp(top, TOOL_GRIP);
		const along = top.sub(iron).normalize();

		const flat = new THREE.Vector3(1, 0, 0);
		const basis = u => {
			const w = flat.clone().addScaledVector(u, -flat.dot(u)).normalize();
			return new THREE.Matrix4().makeBasis(u, w, u.clone().cross(w));
		};

		const carried = new THREE.Vector3(TOOL_OUT, Math.sin(TOOL_PITCH), -Math.cos(TOOL_PITCH)).normalize();
		const quaternion = new THREE.Quaternion().setFromRotationMatrix(basis(carried).multiply(basis(along).transpose()));

		return { quaternion, position: fist.clone().sub(grip.applyQuaternion(quaternion)) };

	}

	// Le talon et la pointe de chaque pied, dans le repère de l'os qui le porte, le tibia quand il y en a un : la marche les garde
	// au sol (pose()). Jambes et genoux ne tournent qu'autour de x, donc le point le plus bas d'une semelle plate est toujours l'un
	// de ses deux bouts en profondeur. La semelle est la tranche de 2 cm sous le plus bas de la botte : plus fine, elle ne garderait
	// qu'un bout d'une semelle modelée en biais
	function soles(bones) {

		return [['jambeD', 'tibiaD'], ['jambeG', 'tibiaG']].map(([leg, knee]) => {

			const bone = bones[knee] ?? bones[leg];
			const points = [];

			bone.children.filter(child => child.isMesh).forEach(mesh => {
				const positions = mesh.geometry.attributes.position;
				for(let i = 0; i < positions.count; i++) {
					points.push(new THREE.Vector3().fromBufferAttribute(positions, i));
				}
			});

			const bottom = Math.min(...points.map(point => point.y));
			const sole = points.filter(point => point.y < bottom + .02);
			const heel = sole.reduce((a, b) => (b.z < a.z) ? b : a);
			const toe = sole.reduce((a, b) => (b.z > a.z) ? b : a);

			return { bone, points: [heel, toe] };

		});

	}

	// La hauteur du point le plus bas des pieds au-dessus du pied de la racine, une fois les jambes posées
	function lowest(figure) {

		let low = Infinity;

		figure.soles.forEach(({ bone, points }) => {

			const matrix = new THREE.Matrix4();

			for(let b = bone; b !== figure.root; b = b.parent) {
				b.updateMatrix();
				matrix.premultiply(b.matrix);
			}

			points.forEach(point => {
				low = Math.min(low, point.clone().applyMatrix4(matrix).y);
			});

		});

		return low;

	}

	// Un objet sans os, ce qu'un personnage pousse ou porte, en un maillage qui garde ses ancres
	function prop(baked, slug, material, colors = {}) {

		const model = sceneBuildModel(THREE, baked, slug);
		const mesh = new THREE.Mesh(painted(model.parts, colors), material);
		mesh.userData.anchors = model.anchors ?? {};

		return mesh;

	}

	/**
	 * Pose un personnage monté par assemble() pour un geste, `walk`, `hoe`, `crouch` ou `stand`. `phase` avance avec le pas et
	 * décale les personnages entre eux. `carry` tient une caisse serrée contre la poitrine en `walk` et en `stand` : `{ crate, hold,
	 * lift }`, la prise rendue par hold() et la levée, de 0 (portée bas, bras tombants) à 1 (dessus à hauteur d'épaule). La caisse,
	 * que l'appelant a mise dans le buste, y est placée, et chaque bras pose sa main contre un de ses grands côtés. `push` tient les
	 * poignées d'une brouette en `walk` : `{ grips, bend }`, l'angle de chaque bras en avant et en dehors, dans l'ordre de `arms`,
	 * et la pente du buste. `hoe` et `crouch` ignorent l'un et l'autre. `depth` plie l'accroupi en part de la pose entière, de 0
	 * (debout) à 1, cuisses, genoux et buste ensemble, pour qui ne descend qu'à demi. L'outil d'un personnage monté avec le sien
	 * se porte en `walk` et en `stand`, et revient à la place que le module lui donne pour travailler. Rend de combien le corps
	 * descend sous ses pieds, en mètres du modèle, que l'appelant ajoute à la racine. En marchant et en binant, c'est de combien
	 * le plus bas des talons et des pointes est monté : le pied d'appui reste au sol, et en marchant le corps est au plus haut
	 * quand les jambes se croisent, au plus bas au bout du pas.
	 */
	function pose(figure, name, { phase = 0, time = 0, carry = null, push = null, depth = 1 } = {}) {

		const { torso, legs, arms, knees } = figure;
		const step = Math.sin(phase);

		let legR = 0;
		let legL = 0;
		let armR = 0;
		let armL = 0;
		let outR = 0;
		let outL = 0;
		let kneeR = 0;
		let kneeL = 0;
		let bend = 0;
		let drop = 0;
		let sink = 0;
		let held = false;

		if(name === 'walk') {

			legR = step * .6;
			legL = -step * .6;
			armR = -step * .5;
			armL = step * .5;
			bend = .06;
			held = (carry !== null);

			// Le bras qui porte l'outil balance peu : l'outil suit le poing, et son fer descendrait vers le sol
			if(figure.tool !== null) {
				armL = step * .15;
			}

			if(push !== null) {
				[[armR, outR], [armL, outL]] = push.grips.map(({ forward, outward }) => [forward, outward]);
				bend = push.bend;
			}

			// La jambe qui revient en arrière plie le genou, celle qui passe devant se tend
			kneeR = Math.max(0, legR) * 1.1;
			kneeL = Math.max(0, legL) * 1.1;

		} else if(name === 'hoe') {

			const swing = Math.sin(time * 3.2 + phase);

			bend = .3 + swing * .08;
			armR = -.9 + swing * .45;
			armL = -1 + swing * .55;
			legR = .2;
			legL = -.15;
			kneeR = .2;
			kneeL = .15;

		} else if(name === 'crouch') {

			const pick = Math.sin(time * 4 + phase) * .25;

			bend = .55 * depth;

			if(knees !== null) {

				// Cuisse presque à l'horizontale et tibia d'aplomb : les deux angles s'annulent et le pied reste à plat. Le corps descend
				// jusqu'à ce que la botte touche le sol, par le recalage des semelles en fin de geste. Les mains vont au rang, à 30 cm du
				// sol devant les pointes
				legR = legL = -1.5 * depth;
				kneeR = kneeL = 1.5 * depth;
				armR = -1.2 + pick;
				armL = -1.2 - pick;

			} else {

				drop = -.22 * depth;
				legR = legL = -1.05 * depth;
				armR = -.7 + pick;
				armL = -.7 - pick;

			}

		} else if(name === 'stand') {

			bend = Math.sin(time * 1.3 + phase) * .03;
			armR = armL = .05;
			held = (carry !== null);

		} else {
			throw new Error('sceneCreateFigure : geste inconnu '+ name);
		}

		legs[0].rotation.x = legR;
		legs[1].rotation.x = legL;

		if(knees !== null) {
			knees[0].rotation.x = kneeR;
			knees[1].rotation.x = kneeL;
		}

		if(held) {

			// La mi-hauteur de ses grands côtés est à la hauteur des mains, son long côté dans l'axe du buste
			const { hold, lift, crate } = carry;
			const level = hold.low + (hold.high - hold.low) * lift;

			hold.hands.forEach(({ arm, hand, across }) => {
				const { forward, inward } = reach(hand, across, level - arm.position.y);
				arm.rotation.set(forward, 0, inward);
			});

			crate.rotation.y = Math.PI / 2;
			crate.position.set(0, level - hold.height / 2, hold.depth);

		} else {
			arms[0].rotation.set(armR, 0, outR);
			arms[1].rotation.set(armL, 0, outL);
		}

		if(figure.tool !== null) {

			const { mesh, quaternion, position } = figure.tool;

			if(name === 'walk' || name === 'stand') {
				mesh.position.copy(position);
				mesh.quaternion.copy(quaternion);
			} else {
				mesh.position.set(0, 0, 0);
				mesh.quaternion.identity();
			}

		}

		// Sans genou, le buste descend plus que la racine pour rapprocher les mains du sol ; avec, le bassin descend déjà assez,
		// et le buste s'enfoncerait dans le bassin
		torso.rotation.x = bend;
		torso.position.y = figure.rest + (knees === null ? drop * .3 : 0);

		// Une jambe portée en arrière incline sa botte, sans cheville pour la remettre à plat, et la pointe descend sous la plante : en
		// marchant comme en binant, le corps se recale sur le plus bas des talons et des pointes, et les pieds ne passent jamais sous
		// le sol. Accroupi sur des genoux, le même recalage pose la botte au sol, quelles que soient la cuisse et le tibia du corps
		if(name === 'walk' || name === 'hoe' || (name === 'crouch' && knees !== null)) {
			sink = -lowest(figure);
		}

		return drop + sink;

	}

	/**
	 * Les deux angles d'épaule, en avant (x) et vers le corps (z), qui posent une main au repos en `hand` depuis l'épaule à `across`
	 * de côté et `height` de hauteur : les bras des personnages sont d'un tenant, sans coude, et la profondeur atteinte découle de la
	 * longueur du bras.
	 */
	function reach(hand, across, height) {

		const clamp = k => Math.max(-1, Math.min(1, k));
		const front = Math.hypot(hand.x, hand.y);
		const frontAngle = Math.atan2(hand.y, hand.x);
		const inward = -Math.acos(clamp(across / front)) - frontAngle;
		const down = front * Math.sin(inward + frontAngle);
		const side = Math.hypot(down, hand.z);
		const sideAngle = Math.atan2(hand.z, down);
		const forward = Math.acos(clamp(height / side)) - sideAngle;

		return { forward: Math.atan2(Math.sin(forward), Math.cos(forward)), inward };

	}

	/**
	 * La prise d'une caisse serrée contre la poitrine, en unités du buste. La caisse arrive à sa taille, son long côté selon x,
	 * que l'appelant tourne vers l'avant : son arrière se colle au devant du buste, vêtement compris, à `depth` de profondeur pour
	 * son milieu. Chaque main (`hands`, lue sur le bas de son bras) se plaque contre un grand côté, à mi-hauteur. Portée bas, à
	 * `low`, les mains en tiennent le quart arrière, bras tombants ; serrée, à `high`, son dessus monte à hauteur d'épaule et les
	 * mains glissent vers l'avant de ses côtés, où le bras tendu les porte. `low` et `high` sont la hauteur des mains.
	 */
	function hold(figure, crate) {

		const box = object => {
			const mesh = object.isMesh ? object : object.children.find(child => child.isMesh);
			mesh.geometry.computeBoundingBox();
			return mesh.geometry.boundingBox;
		};

		const size = box(crate).getSize(new THREE.Vector3()).multiply(crate.scale);
		const height = box(crate).max.y * crate.scale.y;

		// La main est le bas du bras, sur huit centimètres du modèle : son milieu, et sa demi-largeur pour que son bord intérieur
		// touche le côté de la caisse. Le bras se lit en entier, dans le repère de l'épaule : un bras peut porter plusieurs maillages,
		// et un avant-bras qui y pend
		const hands = figure.arms.map(arm => {

			const points = [];

			arm.updateMatrixWorld(true);
			const shoulder = arm.matrixWorld.clone().invert();

			arm.traverse(child => {

				// three ne pose isMesh que sur un maillage : un groupe le laisse indéfini
				if(child.isMesh !== true) {
					return;
				}

				const toArm = shoulder.clone().multiply(child.matrixWorld);
				const positions = child.geometry.attributes.position;

				for(let i = 0; i < positions.count; i++) {
					points.push(new THREE.Vector3().fromBufferAttribute(positions, i).applyMatrix4(toArm));
				}

			});

			const bottom = Math.min(...points.map(point => point.y));
			const low = points.filter(point => point.y < bottom + .08);

			const xs = low.map(point => point.x);
			const hand = low.reduce((sum, point) => sum.add(point), new THREE.Vector3()).divideScalar(low.length);
			const across = Math.sign(arm.position.x) * (size.z / 2 + (Math.max(...xs) - Math.min(...xs)) / 2) - arm.position.x;

			return { arm, hand, across };

		});

		const depth = box(figure.torso).max.z + .01 + size.x / 2;
		const grip = depth - size.x / 4;
		const shoulder = Math.min(...figure.arms.map(arm => arm.position.y));

		// Une main ne s'écarte pas de l'épaule plus que la longueur du bras : à hauteur d'épaule, le milieu des côtés doit être à sa
		// portée, sans quoi la caisse se tiendrait dans le vide
		if(hands.some(({ hand, across }) => across * across + depth * depth > hand.lengthSq())) {
			throw new Error('sceneCreateFigure : une caisse de '+ size.x.toFixed(2) +' m tenue contre la poitrine est hors de portée des bras');
		}

		// Portée bas, la hauteur où le bras le plus court atteint le quart arrière de la caisse
		const low = Math.max(...hands.map(({ arm, hand, across }) => arm.position.y - Math.sqrt(Math.max(0, hand.lengthSq() - across * across - grip * grip))));
		const high = Math.max(low, shoulder - height / 2);

		return { hands, low, high, height, depth };

	}

	/**
	 * De combien le corps descend pour que le plus bas de ses talons et de ses pointes touche le sol, ses os tels qu'ils sont,
	 * en mètres du modèle : pour un appelant qui compose une posture de plusieurs poses et ne peut reprendre la descente
	 * d'aucune d'elles
	 */
	function ground(figure) {
		return -lowest(figure);
	}

	return {
		assemble,
		prop,
		pose,
		hold,
		ground
	};

}
