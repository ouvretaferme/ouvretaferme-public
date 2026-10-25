// Les plants d'une culture : la fusion d'un modèle cuit en une géométrie par espèce, la taille d'un plant sur sa planche,
// son pas, ses rangs, son matériau et son pool d'instances.
// Module ES sans import : three, ses compléments, les pièces de géométrie (geometry.js), le lecteur des modèles cuits
// (model.js) et la règle des ligneux (species.js) sont passés en argument. Rien ici ne crée de scène ni ne lit l'heure :
// ce qui se pose l'est sur un parent fourni, dans son repère, et le vent est un uniform de l'appelant.
// Les tailles et les pas parlent en mètres réels. Une scène qui réduit son plan convertit ce qu'elle passe (largeur,
// longueur, pas minimal, retrait) : le plancher de lisibilité et le plafond de hauteur sont des mètres, pas des fractions.

export function sceneCreateCrop(THREE, addons, geometry, sceneBuildModel, woody) {

	// Plus grande dimension qu'un plant adulte atteint au moins, soit 14 px à la vue de travail (20 px/m)
	const LEGIBLE = .7;

	// Écart au rang d'un pied de parterre, en pas, dans chaque sens
	const PITCH_SCATTER = .15;

	// Hauteur d'un plant grossi, en mètres, pour passer sous les tunnels : un plant plus grand en vrai garde 1,3 fois sa taille
	const HEIGHT_MAX = 2.4;

	// Un chou d'une espèce qui plafonne ses rangs s'élargit au plus jusqu'au double de la taille que size() lui donne
	const CABBAGE_GROWTH = 2;

	// Au-delà de 1,6 fois le chou que size() donne, un rang de chou s'ouvre en un rang de plus : un chou géant seul sur sa
	// planche ne se lit plus comme un chou
	const CABBAGE_SPLIT = 1.6;

	// Part de la largeur de la planche où se posent les rangs, centrée, que layout() rend à la scène avec eux
	const ROW_BAND = .86;

	// Framboisier et mure n'ont de rigide que les fils de leur palissage : sans feuilles il ne resterait que les fils, sans les cannes
	const BARE_NEVER = new Set(['framboise', 'mure']);

	// width et height en fraction de l'emprise et de la hauteur du plant, calées sur la butte du modèle pour que les deux se confondent
	const RIDGES = {
		'pomme-de-terre': { width: .68, height: .31, color: '#a87549' }
	};

	const LEAF_VERTEX = 'transformed = anchor + (transformed - anchor) * instanceLeaf;';

	// Un seul matériau de profondeur pour tous les feuillages de l'instance : il ne dépend d'aucune espèce
	let depth = null;
	const leafDepth = () => {
		if(depth === null) {
			depth = new THREE.MeshDepthMaterial({ depthPacking: THREE.RGBADepthPacking });
			depth.onBeforeCompile = shader => {
				shader.vertexShader = 'attribute vec3 anchor;\nattribute float instanceLeaf;\n' + shader.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\n\t' + LEAF_VERTEX);
			};
			depth.customProgramCacheKey = () => 'leafDepth';
		}
		return depth;
	};

	// L'index cuit écrit une couleur en six chiffres, sans croisillon, et le lecteur la rend telle quelle
	const colorOf = part => new THREE.Color('#' + part.color);

	// Le point d'attache de chaque sommet d'une pièce : le centre du morceau d'un seul tenant auquel il appartient (une feuille,
	// une touffe). Le nuanceur du feuillage rétrécit chaque morceau vers ce point, donc sur place, là où il tient à la branche
	function anchorsOf(piece) {
		const position = piece.attributes.position, count = position.count, parent = Array.from({ length: count }, (_, i) => i);
		const find = i => {
			while(parent[i] !== i) {
				i = parent[i] = parent[parent[i]];
			}
			return i;
		};
		const first = new Map();
		for(let i = 0; i < count; i++) {
			const key = `${Math.round(position.getX(i) * 1e4)},${Math.round(position.getY(i) * 1e4)},${Math.round(position.getZ(i) * 1e4)}`;
			if(first.has(key)) {
				parent[find(i)] = find(first.get(key));
			} else {
				first.set(key, i);
			}
		}
		for(let i = 0; i < count; i += 3) {
			parent[find(i + 1)] = find(i);
			parent[find(i + 2)] = find(i);
		}
		const sums = new Map();
		for(let i = 0; i < count; i++) {
			const root = find(i);
			if(!sums.has(root)) {
				sums.set(root, [0, 0, 0, 0]);
			}
			const sum = sums.get(root);
			sum[0] += position.getX(i);
			sum[1] += position.getY(i);
			sum[2] += position.getZ(i);
			sum[3]++;
		}
		const anchors = new Float32Array(count * 3);
		for(let i = 0; i < count; i++) {
			const sum = sums.get(find(i));
			anchors.set([sum[0] / sum[3], sum[1] / sum[3], sum[2] / sum[3]], i * 3);
		}
		return new THREE.BufferAttribute(anchors, 3);
	}

	// Un plant = UNE géométrie, couleurs et balancement portés par les sommets, donc un seul appel de dessin par espèce.
	// sink enfonce le plant d'une fraction de la hauteur de sa racine : posée sur la planche, une racine entière se lit comme
	// un légume déjà arraché, avec une ombre détachée du pied. La racine est l'ensemble des pièces rigides (sway 0), que le
	// vent ne fait pas bouger parce qu'elles sont en terre ; un modèle sans pièce rigide prend sa pièce la plus basse
	function fuse(model, { sink = 0, keep = null, anchors = false } = {}) {

		const build = (parts, anchored) => parts.map(part => {
			const piece = geometry.paint(geometry.flat(part.geometry), colorOf(part));
			const count = piece.attributes.position.count;
			piece.setAttribute('sway', new THREE.BufferAttribute(new Float32Array(count).fill(part.sway), 1));
			if(anchored) {
				piece.setAttribute('anchor', anchorsOf(piece));
			}
			return piece;
		});

		// Les sommets identiques (position, normale, couleur, balancement) sont fusionnés : la géométrie indexée passe chaque
		// sommet une seule fois dans le nuanceur de sommets au lieu d'une fois par triangle, et les cultures sont limitées par
		// ce nuanceur
		const whole = addons.mergeVertices(addons.mergeGeometries(build(model.parts, false)));

		// Emprise ramenée à 1 m, hauteur à 1 m, pied à y = 0 : l'instance donne la taille réelle
		whole.computeBoundingBox();
		const box = whole.boundingBox, size = box.getSize(new THREE.Vector3());
		const k = 1 / Math.max(size.x, size.z);

		// keep ne garde qu'une partie des pièces (le bois d'un arbre sans ses feuilles), mise à l'échelle par la boite du modèle
		// entier : les pièces gardées restent exactement où elles sont dans le modèle complet, à la même taille
		const fused = keep === null ? whole : addons.mergeVertices(addons.mergeGeometries(build(model.parts.filter(keep), anchors)));

		fused.translate(0, -box.min.y, 0);
		fused.scale(k, 1 / size.y, k);

		let sunk = 0;
		if(sink > 0) {
			const boxes = model.parts.map(part => ({ sway: part.sway, box: new THREE.Box3().setFromBufferAttribute(part.geometry.attributes.position) }));
			const rigid = boxes.filter(part => part.sway === 0);
			const root = rigid.length ? rigid.reduce((union, part) => union.union(part.box), new THREE.Box3()) : boxes.reduce((low, part) => part.box.min.y < low.box.min.y ? part : low).box;
			sunk = sink * (root.max.y - root.min.y) / size.y;
			fused.translate(0, -sunk, 0);
		}

		// translate et scale ne déplacent que les positions : les points d'attache suivent le même chemin, à la main
		const anchor = fused.attributes.anchor;
		if(anchor) {
			for(let i = 0; i < anchor.count; i++) {
				anchor.setXYZ(i, anchor.getX(i) * k, (anchor.getY(i) - box.min.y) / size.y - sunk, anchor.getZ(i) * k);
			}
		}

		fused.computeBoundingSphere();

		return { geometry: fused, d: model.d, h: model.h, aligned: model.aligned, triangles: geometry.triangles(fused) };

	}

	// Le modèle d'un module du catalogue pour une entrée : le pied entier, et ce que la scène en montre à part.
	// Plusieurs entrées peuvent partager un module (couvert, pour les cultures semées dru) : d fixe alors leur emprise
	function read(baked, entry, module) {

		const model = sceneBuildModel(THREE, baked, module);

		if(entry.base) {
			for(const key of ['color', 'leaf', 'accent']) {
				if(entry[key] === entry.base[key]) {
					continue;
				}
				const from = new THREE.Color(entry.base[key]);
				for(const part of model.parts) {
					if(colorOf(part).equals(from)) {
						part.color = entry[key].slice(1);
					}
				}
			}
		}

		const sink = entry.sink ?? 0;
		const own = fused => ({ ...fused, d: entry.d ?? fused.d, species: entry });
		const some = keep => model.parts.some(keep);

		// Un arbre ou un arbuste sans ses feuilles : les pièces rigides du même modèle (tronc, charpente, palissage), rien d'autre.
		// Un module qui ne sépare pas son bois (aucune pièce à sway 0, ou seulement ses fils) garde ses feuilles toute l'année
		const wood = part => part.sway === 0, foliage = part => part.sway !== 0, fruit = part => part.name === 'fruit';
		const sheds = woody(entry) && !BARE_NEVER.has(module) && some(wood) && some(foliage);

		return {
			...own(fuse(model, { sink })),
			bare: sheds ? own(fuse(model, { sink, keep: wood })) : null,
			// Le feuillage seul, avec ses points d'attache : posé sur le bois, il pousse et tombe sans que le tronc bouge
			leaves: sheds ? own(fuse(model, { sink, keep: foliage, anchors: true })) : null,
			// Les fruits seuls d'un module qui porte les siens, dans la boite du modèle entier : posés sur le pied nu à la même
			// échelle, ils tombent exactement où le module complet les montre
			fruits: some(fruit) ? own(fuse(model, { sink, keep: fruit })) : null
		};

	}

	// La hauteur d'un plant grossi, jamais au-delà de HEIGHT_MAX sauf pour garder 1,3 fois sa taille vraie
	const ceiling = model => Math.max(model.h * 1.3, HEIGHT_MAX);

	const size = (model, width, scale) => {

		// Un arbre ou une liane garde sa taille vraie : grossi comme un légume, un noyer couvrirait la parcelle, et sa taille
		// vraie le fait déjà lire de loin
		if(model.species.family === 'tree') {
			return { d: model.d, h: model.h };
		}

		// Un seul facteur grossit l'emprise et la hauteur : avec deux, l'emprise bornée par la planche et la hauteur libre,
		// un buisson large devient un vase plus haut que large. L'emprise vise 1,3 fois la largeur de la planche, avec un
		// grossissement minimal de 1,2. L'espèce qui déclare une taille réduite (size sous 1) n'a pas ce minimum : elle demande
		// à tenir dans sa planche, et il ferait déborder son module dès qu'il est plus large qu'elle
		const least = model.species.size < 1 ? 0 : 1.2;
		const k = Math.min(scale, Math.max(least, width * 1.3 / model.d));
		// La hauteur reçoit au plus un quart de plus que l'emprise, pour rester lisible de loin, et ne dépasse pas ceiling()
		// pour passer sous les tunnels
		const d = model.d * k, h = Math.min(model.h * Math.min(scale, k * 1.25), ceiling(model));

		// L'emprise suit le plancher de lisibilité dans la limite de deux rangs par planche, la hauteur n'a pas de voisin à gêner
		const floor = LEGIBLE / Math.max(d, h);
		const fitted = floor > 1 ? { d: Math.max(d, Math.min(d * floor, width * .43)), h: h * floor } : { d, h };

		// Une espèce peut déclarer sa taille (size), en fraction de celle que la planche lui donnerait : l'emprise du module ne
		// suffit pas à la régler, le grossissement se cale sur la largeur de la planche et la rattrape. Au-dessus de 1, elle
		// s'arrête à ce que la planche peut porter : un rang ne déborde pas dans l'allée
		const declared = model.species.size ?? 1;
		const factor = declared > 1 ? Math.max(1, Math.min(declared, width * .9 / fitted.d)) : declared;

		return factor === 1 ? fitted : { d: fitted.d * factor, h: fitted.h * factor };

	};

	// Une culture semée dru (module couvert) se plante à la moitié de son emprise, ses gerbes se chevauchent en nappe sans
	// terre visible, et une espèce en parterre à 0,7 fois son emprise pour former un tapis. Une espèce au feuillage fin laisse
	// voir la terre entre deux pieds au pas commun : elle déclare son pas (pitch), en fraction de son emprise
	const pitch = (model, d, density) => d * (model.species.module === 'couvert' ? .5 : model.species.parterre ? .7 : model.species.pitch ?? density);

	// Deux rangs par défaut : au-delà, les plants rapetissent jusqu'à ne plus se reconnaitre de loin. Une espèce semée très
	// dense déclare les siens (rows), un parterre en garde trois pour ne pas montrer la terre, et un couvert quatre : sa
	// touffe est une gerbe étroite au pied, trois rangs laissent une bande de terre entre eux
	const rows = model => model.species.rows ?? (model.species.module === 'couvert' ? 4 : model.species.parterre ? 3 : 2);

	return {

		// baked : { index, buffer }, les deux fichiers cuits. list : les entrées du catalogue à fusionner.
		// Rend une Map slug → { geometry, d, h, aligned, triangles, species, bare, leaves, fruits, picked, dressed }.
		// bare, leaves et fruits portent { geometry, d, h, aligned, triangles, species }, ou null quand le module ne les sépare pas.
		// picked est le pied allégé de ce qui se cueille (pickedModule), dressed le pied qui porte ses fruits (fruitModule) :
		// chacun avec ses propres bare, leaves et fruits, ou null quand l'entrée ne le déclare pas
		models(baked, list) {

			const out = new Map();

			for(const entry of list) {
				out.set(entry.slug, {
					...read(baked, entry, entry.module ?? entry.slug),
					picked: entry.pickedModule ? read(baked, entry, entry.pickedModule) : null,
					dressed: entry.fruitModule ? read(baked, entry, entry.fruitModule) : null
				});
			}

			return out;

		},

		size,
		pitch,
		rows,

		// Les rangs et les plants d'une série sur sa longueur de planche. inset est ce que la série laisse libre à ses bouts.
		// Un arbre tient un rang, à un pas qui suit son emprise.
		// whole : { bed, plants } pour une série qui tient une parcelle sans planche. Ses plants ont la taille qu'ils auraient
		// sur une planche de bed mètres, la parcelle n'en porte pas plus que plants (le pas s'élargit pour le tenir) et ses
		// rangs n'ont pas de plafond.
		// spacing écarte les pieds sur le rang, toutes espèces comprises, sans changer ni leur taille ni le nombre de rangs : une
		// scène qui montre plus de planches qu'elle ne peut en dessiner garde chaque planche plantée d'un bout à l'autre, avec moins
		// de pieds. À 1, le pas est celui que l'espèce et la planche donnent
		layout(model, { width, length, scale, density, minPitch = 0, inset = 0, whole = null, spacing = 1 }) {

			const plant = size(model, whole === null ? width : whole.bed, scale);
			const step = Math.max(pitch(model, plant.d, density), minPitch, whole === null ? 0 : Math.sqrt(length * width / whole.plants));

			if(whole === null && model.species.family === 'tree') {
				return { size: plant, pitch: step * spacing, rows: 1, count: Math.max(1, Math.round(length / (plant.d * 1.15 * spacing))), band: ROW_BAND };
			}

			// Un chou adulte couvre sa part de planche : il s'élargit jusqu'à la largeur de son rang, et son pas sur le rang est
			// son emprise, deux pieds voisins se touchant presque. Il passe ainsi le grossissement (scale) que l'appelant donne aux
			// autres espèces, qui laissait un tiers de planche au chou. La planche ouvre un rang de plus dès qu'un rang
			// dépasserait CABBAGE_SPLIT fois le chou, ce qui borne le grossissement ; une espèce qui plafonne ses rangs (rows)
			// est bornée par CABBAGE_GROWTH. La hauteur suit le même facteur, pour garder la silhouette du modèle, sous
			// ceiling(). Le chou ne rapetisse jamais : sur une planche plus étroite que lui, ou juste assez large pour ouvrir un
			// rang de plus, il garde la taille qui le fait lire de loin, quitte à mordre sur le rang voisin
			if(whole === null && model.species.family === 'cabbage') {

				const lines = Math.max(1, Math.min(model.species.rows ?? Infinity, Math.ceil(ROW_BAND * width / (CABBAGE_SPLIT * plant.d))));
				const k = Math.max(1, Math.min(ROW_BAND * width / lines / plant.d, CABBAGE_GROWTH, ceiling(model) / plant.h));
				const cabbage = { d: plant.d * k, h: plant.h * k };
				const along = Math.max(cabbage.d * (model.species.pitch ?? 1), minPitch) * spacing;

				return { size: cabbage, pitch: along, rows: lines, count: Math.max(1, Math.floor((length - inset) / along)), band: ROW_BAND };

			}

			return {
				size: plant,
				pitch: step * spacing,
				rows: Math.max(1, Math.min(whole === null ? rows(model) : Infinity, Math.floor(width / step + .2))),
				count: Math.max(1, Math.floor((length - inset) / (step * spacing))),
				band: ROW_BAND
			};

		},

		// Part du pas dont un pied s'écarte de son rang : le parterre se déclare par espèce, pas par famille
		scatter: model => model.species.parterre ? PITCH_SCATTER : 0,

		// Matériau commun : toon, couleurs de sommet, vent pondéré par l'attribut sway, et une teinte d'instance (rgb + force)
		// qui ramène le plant vers la paille, le gris ou la neige. wind est l'uniform { value } que l'appelant fait avancer.
		// leaves : le matériau du feuillage seul d'un ligneux, dont chaque morceau se rétrécit vers son point d'attache selon
		// instanceLeaf (0 à 1)
		material({ gradientMap, wind, sway = .22, leaves = false }) {

			const material = new THREE.MeshToonMaterial({ gradientMap, side: THREE.DoubleSide, vertexColors: true });

			material.onBeforeCompile = shader => {
				shader.uniforms.windTime = wind;
				shader.vertexShader = 'uniform float windTime;\nattribute float sway;\nattribute vec4 instanceTint;\n' + (leaves ? 'attribute vec3 anchor;\nattribute float instanceLeaf;\n' : '') + shader.vertexShader
					.replace('#include <color_vertex>', '#include <color_vertex>\n\tvColor.rgb = mix(vColor.rgb, instanceTint.rgb, instanceTint.a);')
					.replace('#include <begin_vertex>', `#include <begin_vertex>
					${leaves ? LEAF_VERTEX : ''}
					vec3 windRoot = instanceMatrix[3].xyz;
					float windPhase = windTime * 1.6 + windRoot.x * .31 + windRoot.z * .23;
					float windLift = max(position.y, 0.0) * sway;
					transformed.x += sin(windPhase) * ${sway.toFixed(3)} * windLift;
					transformed.z += cos(windPhase * .8) * ${(sway * .6).toFixed(3)} * windLift;`);
			};

			// three partage un programme entre deux matériaux de même clé : sans elle, deux balancements liraient le même nuanceur
			material.customProgramCacheKey = () => 'crop' + sway + (leaves ? 'leaves' : '');

			return material;

		},

		// Compose des passages successifs vers des couleurs cibles en une seule teinte (des mix itérés font un seul mix).
		// target est un Vector4 que la fonction remplit, steps une liste de [couleur, force]
		tint(target, steps) {

			let a = 0;
			const x = new THREE.Color(0, 0, 0);

			for(const [color, amount] of steps) {
				const next = 1 - (1 - a) * (1 - amount);
				if(next === 0) {
					continue;
				}
				x.multiplyScalar(a * (1 - amount)).add(color.clone().multiplyScalar(amount)).multiplyScalar(1 / next);
				a = next;
			}

			return target.set(x.r, x.g, x.b, a);

		},

		// Pool d'instances d'une espèce : un InstancedMesh qui grandit au besoin, posé sur parent
		pool(parent, material, model, needed, pool = null) {

			if(pool !== null && needed <= pool.capacity) {
				return pool;
			}

			// La géométrie de l'ancien pool se libère sans les attributs du modèle, que le nouveau reprend : three libère avec une
			// géométrie son index et tous ses attributs, et les rechargerait aussitôt sur la carte graphique
			if(pool !== null) {
				parent.remove(pool.mesh);
				pool.mesh.dispose();
				const old = pool.mesh.geometry;
				old.setIndex(null);
				for(const name of Object.keys(model.geometry.attributes)) {
					old.deleteAttribute(name);
				}
				old.dispose();
			}

			const capacity = Math.max(32, Math.ceil(needed * 1.3));

			// Le modèle est partagé entre les pools successifs d'une espèce : chaque pool a sa géométrie, pour ses attributs
			// d'instance, mais elle reprend les attributs du modèle au lieu de les copier. Copiés, un pool qui grandit pendant
			// la lecture de l'année recharge tout le modèle sur la carte graphique et laisse l'ancienne copie au ramasse-miettes
			const shared = new THREE.BufferGeometry();
			shared.setIndex(model.geometry.index);
			for(const [name, attribute] of Object.entries(model.geometry.attributes)) {
				shared.setAttribute(name, attribute);
			}
			shared.boundingSphere = model.geometry.boundingSphere;
			shared.boundingBox = model.geometry.boundingBox;

			const mesh = new THREE.InstancedMesh(shared, material, capacity);
			const tints = new THREE.InstancedBufferAttribute(new Float32Array(capacity * 4), 4);
			shared.setAttribute('instanceTint', tints);

			// Un feuillage seul porte en plus sa taille par instance, et son ombre la suit : sans ce matériau de profondeur,
			// un arbre aux feuilles naissantes porterait l'ombre d'une couronne pleine
			const leaf = model.geometry.attributes.anchor ? new THREE.InstancedBufferAttribute(new Float32Array(capacity).fill(1), 1) : null;
			if(leaf !== null) {
				shared.setAttribute('instanceLeaf', leaf);
				mesh.customDepthMaterial = leafDepth();
			}

			mesh.castShadow = mesh.receiveShadow = true;
			// Les instances débordent la sphère du modèle, qui ne borne qu'un plant à l'origine
			mesh.frustumCulled = false;
			parent.add(mesh);

			return { mesh, tints, leaf, capacity };

		},

		// La butte d'une espèce buttée, { width, height, color }, ou null. Le plant porte sa propre butte, mais son pas laisse
		// un creux entre deux pieds : le rang continu se pose par placeRidges()
		ridge: slug => RIDGES[slug] ?? null,

		// Prisme de section triangulaire, longueur et largeur 1 centrées, arête haute à y = 1 : 6 triangles, normales plates
		ridgeGeometry() {

			const prism = new THREE.BufferGeometry();
			const quad = (a, b, c, d) => [...a, ...b, ...c, ...a, ...c, ...d];
			const A = [-.5, 0, .5], B = [.5, 0, .5], C = [.5, 1, 0], D = [-.5, 1, 0], E = [.5, 0, -.5], F = [-.5, 0, -.5];

			prism.setAttribute('position', new THREE.Float32BufferAttribute([...quad(A, B, C, D), ...quad(E, F, D, C), ...B, ...E, ...C, ...F, ...A, ...D], 3));
			prism.computeVertexNormals();
			prism.setAttribute('color', new THREE.Float32BufferAttribute(new Array(prism.attributes.position.count * 3).fill(1), 3));

			return prism;

		},

		// rows : [{ x, y, z, rot, length, width, height, color }] dans le repère de parent, rot autour de y comme les plants
		placeRidges(parent, material, ridgeGeometry, rows, pool = null) {

			if(pool === null || pool.capacity < rows.length) {
				if(pool !== null) {
					parent.remove(pool.mesh);
					pool.mesh.dispose();
				}
				const capacity = Math.max(32, Math.ceil(rows.length * 1.3));
				const mesh = new THREE.InstancedMesh(ridgeGeometry, material, capacity);
				// La couleur d'instance n'existe qu'après une première écriture
				mesh.setColorAt(0, new THREE.Color('#ffffff'));
				mesh.receiveShadow = mesh.castShadow = true;
				mesh.frustumCulled = false;
				parent.add(mesh);
				pool = { mesh, capacity };
			}

			const matrix = new THREE.Matrix4(), position = new THREE.Vector3(), turn = new THREE.Quaternion(), scale = new THREE.Vector3();
			const up = new THREE.Vector3(0, 1, 0), color = new THREE.Color();

			rows.forEach((row, i) => {
				matrix.compose(position.set(row.x, row.y, row.z), turn.setFromAxisAngle(up, row.rot), scale.set(row.length, row.height, row.width));
				pool.mesh.setMatrixAt(i, matrix);
				pool.mesh.setColorAt(i, color.set(row.color));
			});

			pool.mesh.count = rows.length;
			pool.mesh.visible = rows.length > 0;
			pool.mesh.instanceMatrix.needsUpdate = true;
			pool.mesh.instanceColor.needsUpdate = true;

			return pool;

		}

	};

}
