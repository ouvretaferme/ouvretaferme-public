/**
 * La cour du jeu : une ile qu'un ruisseau traverse, ses quatre plateaux de cases dispersées le long des rives, et son
 * décor, tirés de la graine du joueur (`GameBoardCourt`, court.js). Chaque case montre sa culture à son stade, sa
 * caisse quand elle est mûre ; un plateau fermé porte sa ganivelle. La vue se glisse, se pince et se zoome à la
 * molette dans les bornes de la cour. Le joueur s'y tient en personne, monté depuis la fiche de son avatar, et marche
 * jusqu'à la case ou au point du sol touché, en contournant les cases, l'eau et le décor (`GameBoardPath`, path.js).
 * Un toucher ne déplace jamais la vue, qui appartient au joueur.
 *
 * La scène ne connaît que `#game-board` : elle lit la donnée de `data-game-board`, pose les marques que la page rend
 * dans ses `<template data-game-label>`, et dit ce qui se passe par des événements qui remontent de `#game-board`
 * (`gameboard:ready`, `error`, `tile`, `cellar`, `placed`, `avatar`). L'habillage les écoute (`GameDesk`, desk.js) et lui répond
 * par `apply()`, `react()` et `clear()` ; `locate()` lui dit où une case se trouve à l'écran.
 *
 * Une seule cour vit à la fois, dans un seul contexte WebGL, libéré sur `navigation.sleep`.
 */
class GameBoard {

	static current = null;

	// La cour de l'accueil et de la fin de partie, qui n'ont pas de joueur : `BoardLib::getScene()` donne la graine des
	// autres, le plus petit id de leurs cases
	static SEED = 129;

	// La ganivelle d'un plateau fermé suit ses cases à `GameBoardCourt.FENCE_GAP` mètres, à l'échelle FENCE_SCALE
	static FENCE_SCALE = 1.6;

	// Les petites espèces sont relevées pour se lire sur une case de 2 m, de la planche
	static BOOST = { betterave: 2.6, carotte: 2.6, luzerne: 1.6, pois: 1.3 };

	// `background` est le fond sur lequel l'ile se pose, `stones` les pas japonais
	static COLORS = { background: '#f7ecda', stones: '#a8987a', soil: '#8a5a3b', soilHover: '#a77250', soilSelected: '#c09068', soilWatered: '#6b4129' };

	// La lumière de la planche « Soleil et ombres portées » : un ciel chaud, un soleil haut venu du fond à gauche dont
	// l'ombre pose chaque arbre et chaque plant sur l'herbe. L'ombre se calcule dans un carré de `shadow` mètres autour
	// du centre, qui contient l'ile
	static LIGHT = { sky: '#fff8ec', earth: '#6f7f4f', ambient: 1.55, sun: '#fff0d4', sunIntensity: 2.6, sunAt: [-14, 26, 9], shadow: 32, shadowSize: 2048 };

	// La lanterne que le joueur porte, et l'anneau d'or à ses pieds qui le désigne dans la cour. Sa lumière est orangée
	// parce qu'elle s'ajoute au vert de l'herbe : c'est leur somme qui fait le halo jaune de la planche
	static LANTERN = { color: '#ff6a00', intensity: 12, distance: 7, decay: 1.6, ring: '#f2c14e' };

	// La vitesse de marche du joueur, en mètres par seconde
	static WALK = 2.2;

	// Un toucher reste un toucher tant qu'il bouge de moins de CLICK_PX pixels en moins de CLICK_MS millisecondes ;
	// au-delà, c'est un glisser de la vue (même règle que le plan de ferme)
	static CLICK_PX = 8;
	static CLICK_MS = 600;

	// Les marques des cases se montrent quand une case mesure au moins MARK_PX pixels de côté à l'écran : en deçà,
	// leurs pastilles se chevauchent d'une case à l'autre et couvrent la cour
	static MARK_PX = 36;

	// Le zoom va de ZOOM_MIN fois le cadrage de toute la cour, `zoomCourt`, à ZOOM_MAX fois celui d'un plateau, où une
	// case et ses plantes se lisent de près. Sous 600 px, la cour cadrée entière remplit déjà la largeur : le zoom ne
	// descend pas plus bas, il ne ferait que rapetisser les cases
	static ZOOM_MIN = .8;
	static ZOOM_MAX = 2.5;

	// La marque d'une case déborde de sa case jusqu'à CELLAR_CLEAR pixels de chaque côté quand elle porte une étiquette
	// (« 30 12 j ») : la marque du cellier s'en écarte d'autant (`placeCellar()`)
	static CELLAR_CLEAR = 20;
	static CELLAR_REACH = 90;
	static CELLAR_STEP = 6;

	// La vue ne quitte pas la cour, et s'arrête à MARGIN mètres de son emprise
	static MARGIN = .5;

	// La largeur de la vue au zoom 1, celle de la cour d'avant le ruisseau : deux plateaux carrés de 9,05 m, leur allée
	// de 7 m, et une marge. Les zooms se comptent par rapport à elle
	static SPAN = (9.05 * 2 + 7) * 1.45;

	// `cover` est ce que l'habillage pose sur le haut de la scène (bandeaux, rangée de l'Avent), sous lequel la cour se
	// cadre ; l'accueil et la fin de partie n'en posent pas
	static start(node, cover = null) {

		GameBoard.stop();

		const board = new GameBoard(node, cover);
		GameBoard.current = board;

		// Appelée depuis un `onrender` : une exception levée ici ferait tomber les `onrender` suivants de la page
		Promise.resolve()
			.then(() => board.open())
			.catch(error => {

				if(board.closed) {
					return;
				}

				board.close();
				GameBoard.current = null;

				console.error(error);
				board.emit('error', {});

			});

		return board;

	}

	static stop() {

		if(GameBoard.current !== null) {
			GameBoard.current.close();
			GameBoard.current = null;
		}

	}

	// Les cases qu'un geste a changées (`BoardLib::export()`), leurs marques rendues par la page, par id de case, et les
	// gestes que le joueur y enchaîne ensuite, nommés par la page (`view/action.v.php`)
	static apply(tiles, labels, gestures) {
		GameBoard.current?.update(tiles, labels, gestures);
	}

	static clear() {
		GameBoard.current?.unselect();
	}

	// Des gestes que le joueur fait là où il se tient, sans case touchée : nommés par la page, comme ceux d'`apply()`
	static react(gestures) {
		GameBoard.current?.perform(gestures);
	}

	// Où se trouve une case à l'écran, en coordonnées de la fenêtre, null si la cour ne la porte pas
	static locate(id) {
		return (GameBoard.current === null) ? null : GameBoard.current.locate(id);
	}

	constructor(node, cover) {

		this.node = node;
		this.cover = cover;
		this.container = node.qs('#game-board-container');
		this.data = JSON.parse(node.dataset.gameBoard);

		this.labels = {};
		node.qsa('template[data-game-label]', template => this.labels[template.dataset.gameLabel] = template.innerHTML.trim());

		this.closed = false;
		this.renderer = null;
		this.listeners = new AbortController();
		this.observer = null;
		this.visibility = null;
		this.frameId = null;
		this.running = true;

		this.tiles = {};
		this.roots = {};
		this.marks = [];
		this.marked = false;
		this.pixels = 1;
		this.hovered = null;
		this.selected = null;
		this.pending = null;
		this.actor = null;
		this.cellarMark = null;
		this.cellarSize = null;
		this.cellarZoom = null;
		this.wellBox = null;

		this.court = null;
		this.acting = false;
		this.models = {};
		this.materials = {};
		this.grid = null;
		this.boxes = [];
		this.extent = null;
		this.controls = null;
		this.zoomBoard = 1;
		this.zoomCourt = 1;
		this.zoomOpening = 1;
		this.opening = false;
		this.head = 0;
		this.down = null;
		this.pointers = new Set();

	}

	open() {

		const bake = JSON.parse(this.container.dataset.sceneBake);
		const loading = SceneLoader.loadPair({ directory: this.container.dataset.sceneDirectory, json: bake.json, bin: bake.bin });

		// La cour se tire dès que les modèles sont lus, pendant que three se charge
		return Promise.all([
			SceneLoader.load(this.container),
			loading,
			loading.then(baked => GameBoardCourt.build(this.data.seed ?? GameBoard.SEED, baked.index))
		])
			.then(([three, baked, court]) => {

				if(this.closed) {
					return;
				}

				this.three = three;
				this.baked = baked;
				this.court = court;

				this.mount();
				this.emit('ready', {});

				// La cour sans personne de l'accueil et de la fin de partie
				if(this.data.avatar === null) {
					return;
				}

				// Le joueur vient après la première image : la cour ne l'attend pas, et reste entière sans lui
				return SceneLoader.loadAvatar(this.container)
					.then(loaded => {
						if(this.closed === false) {
							this.mountAvatar(loaded);
						}
					})
					.catch(error => console.error(error));

			});

	}

	/**
	 * Le sol de la cour : le dessus de l'ile, sa tranche en trois bandes (le rebord d'herbe, la terre, la roche), puis
	 * ce que la cour pose à plat dessus, l'herbe haute, le sable, l'eau et les pas japonais. Ces formes ne se touchent
	 * pas : elles se fondent en un maillage coloré par sommet, chacune un peu au-dessus de la précédente pour que
	 * l'ordre de la cour décide de ce qui recouvre quoi
	 */
	buildGround() {

		const { THREE, addons } = this.three;
		const { top, lip, earth, deep, depth } = this.court.island;

		const outline = new THREE.Shape();

		for(let index = 0; index < 160; index++) {

			const angle = index / 160 * Math.PI * 2, radius = this.court.getRadiusAt(angle);

			if(index === 0) {
				outline.moveTo(Math.cos(angle) * radius, -Math.sin(angle) * radius);
			} else {
				outline.lineTo(Math.cos(angle) * radius, -Math.sin(angle) * radius);
			}

		}

		const disc = new THREE.ShapeGeometry(outline);
		disc.rotateX(-Math.PI / 2);

		this.addGround(disc, new THREE.MeshLambertMaterial({ color: top }));

		const side = color => new THREE.MeshLambertMaterial({ color, flatShading: true, side: THREE.DoubleSide });

		this.addGround(this.getIslandBand(0, .05, 0, -.28), side(lip));
		this.addGround(this.getIslandBand(.05, .45, -.28, -.28 - depth * .55), side(earth));
		this.addGround(this.getIslandBand(.45, 1.3, -.28 - depth * .55, -.28 - depth), side(deep));

		const flats = [...this.court.patches, ...this.court.layers];
		const color = new THREE.Color();

		const geometries = flats.map((layer, index) => {
			const geometry = this.getFlatGeometry(layer);
			geometry.translate(0, .012 + index * .002, 0);
			return this.paintGeometry(geometry, color.set(layer[layer.length - 1]));
		});

		const stones = this.court.stones.map(([x, z, radius]) => {
			const geometry = new THREE.CircleGeometry(radius, 7);
			geometry.rotateX(-Math.PI / 2);
			geometry.translate(x, .012 + (flats.length + 1) * .002, z);
			return this.paintGeometry(geometry, color.set(GameBoard.COLORS.stones));
		});

		const merged = addons.mergeGeometries([...geometries, ...stones]);
		[...geometries, ...stones].forEach(geometry => geometry.dispose());

		this.addGround(merged, new THREE.MeshLambertMaterial({ vertexColors: true }));

		// Les passerelles : des boites, fondues par couleur
		const boxes = {};

		this.court.boxes.forEach(box => {
			const geometry = new THREE.BoxGeometry(box.w, box.h, box.d);
			geometry.rotateY(box.angle);
			geometry.translate(box.x, box.y + box.h / 2, box.z);
			(boxes[box.color] ??= []).push(geometry);
		});

		Object.entries(boxes).forEach(([color, geometries]) => {
			this.scene.add(this.cast(new THREE.Mesh(addons.mergeGeometries(geometries), new THREE.MeshLambertMaterial({ color }))));
			geometries.forEach(geometry => geometry.dispose());
		});

	}

	addGround(geometry, material) {

		const { THREE } = this.three;

		const mesh = new THREE.Mesh(geometry, material);
		mesh.receiveShadow = true;
		this.scene.add(mesh);

		return mesh;

	}

	paintGeometry(geometry, color) {

		const { THREE } = this.three;

		const count = geometry.attributes.position.count;
		const colors = new Float32Array(count * 3);

		for(let index = 0; index < count; index++) {
			color.toArray(colors, index * 3);
		}

		geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

		return geometry;

	}

	// Une bande de la tranche de l'ile, de `inset0` à `inset1` mètres en retrait du bord et de `y0` à `y1` en hauteur
	getIslandBand(inset0, inset1, y0, y1) {

		const { THREE } = this.three;

		const count = 120, positions = [];
		const at = (angle, inset, y) => {
			const radius = this.court.getRadiusAt(angle) - inset;
			return [Math.cos(angle) * radius, y, Math.sin(angle) * radius];
		};

		for(let index = 0; index < count; index++) {

			const a0 = index / count * Math.PI * 2, a1 = (index + 1) / count * Math.PI * 2;
			const t0 = at(a0, inset0, y0), t1 = at(a1, inset0, y0), b0 = at(a0, inset1, y1), b1 = at(a1, inset1, y1);

			positions.push(...t0, ...b0, ...t1, ...t1, ...b0, ...b1);

		}

		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geometry.computeVertexNormals();

		return geometry;

	}

	// Une forme posée à plat par la cour (`GameBoardCourt`, `layers` et `patches`) : un rectangle, un rond, une tache, un
	// polygone ou un ruban le long d'une suite de points
	getFlatGeometry(layer) {

		const { THREE } = this.three;
		const [kind, ...rest] = layer;
		const shape = new THREE.Shape();

		const trace = points => {
			points.forEach(([x, z], index) => {
				if(index === 0) {
					shape.moveTo(x, -z);
				} else {
					shape.lineTo(x, -z);
				}
			});
			shape.closePath();
		};

		if(kind === 'rect') {

			const [x0, z0, x1, z1] = rest;
			trace([[x0, z0], [x1, z0], [x1, z1], [x0, z1]]);

		} else if(kind === 'disc') {

			const [x, z, radius] = rest;
			shape.absarc(x, -z, radius, 0, Math.PI * 2, false);

		} else if(kind === 'blob') {

			const [x, z, rx, rz, n] = rest;

			trace(Array.from({ length: 64 }, (_, index) => {
				const angle = index / 64 * Math.PI * 2, cos = Math.cos(angle), sin = Math.sin(angle);
				return [x + rx * Math.sign(cos) * Math.pow(Math.abs(cos), 2 / n), z + rz * Math.sign(sin) * Math.pow(Math.abs(sin), 2 / n)];
			}));

		} else if(kind === 'poly') {

			trace(rest[0]);

		} else if(kind === 'ribbon') {

			const [points, half] = rest;
			const left = [], right = [];

			points.forEach((point, index) => {
				const a = points[Math.max(0, index - 1)], b = points[Math.min(points.length - 1, index + 1)];
				const dx = b[0] - a[0], dz = b[1] - a[1], length = Math.hypot(dx, dz) || 1;
				left.push([point[0] - dz / length * half, point[1] + dx / length * half]);
				right.push([point[0] + dz / length * half, point[1] - dx / length * half]);
			});

			trace([...left, ...right.reverse()]);

		}

		const geometry = new THREE.ShapeGeometry(shape, 24);
		geometry.rotateX(-Math.PI / 2);

		return geometry;

	}

	// Tout ce qui se tient debout dans la cour porte son ombre et reçoit celle des autres
	cast(mesh) {
		mesh.castShadow = true;
		mesh.receiveShadow = true;
		return mesh;
	}

	emit(name, detail) {
		this.node.dispatchEvent(new CustomEvent('gameboard:'+ name, { bubbles: true, detail }));
	}

	mount() {

		const { THREE, addons } = this.three;

		this.scene = new THREE.Scene();
		this.scene.background = new THREE.Color(GameBoard.COLORS.background);
		this.scene.add(new THREE.HemisphereLight(GameBoard.LIGHT.sky, GameBoard.LIGHT.earth, GameBoard.LIGHT.ambient));

		// Le soleil vient de `sunAt`, à 30 m du centre : sa caméra d'ombre, centrée sur lui, couvre toute l'ile, sa
		// tranche comprise, entre `near` et `far`
		const sun = new THREE.DirectionalLight(GameBoard.LIGHT.sun, GameBoard.LIGHT.sunIntensity);
		const shadow = GameBoard.LIGHT.shadow;
		sun.position.set(...GameBoard.LIGHT.sunAt).normalize().multiplyScalar(30);
		sun.castShadow = true;
		sun.shadow.mapSize.set(GameBoard.LIGHT.shadowSize, GameBoard.LIGHT.shadowSize);
		Object.assign(sun.shadow.camera, { left: -shadow, right: shadow, top: shadow, bottom: -shadow, near: -60, far: 80 });
		sun.shadow.bias = -.0006;
		sun.shadow.normalBias = .03;
		this.scene.add(sun);
		this.scene.add(sun.target);

		// Le nombre de lumières entre dans chaque matériau compilé : la lanterne du joueur, qui arrive après la
		// première image, est donc posée ici, éteinte, et lui sera seulement rattachée
		this.lantern = new THREE.PointLight(GameBoard.LANTERN.color, 0, GameBoard.LANTERN.distance, GameBoard.LANTERN.decay);
		this.scene.add(this.lantern);

		this.buildGround();

		this.soilGeometry = new THREE.BoxGeometry(GameBoardCourt.TILE, .16, GameBoardCourt.TILE);

		Object.keys(this.data.boards).forEach(board => this.buildBoard(board));

		this.buildDecor();

		this.renderer = new THREE.WebGLRenderer({ antialias: true });
		this.renderer.setPixelRatio(Math.min(2, window.devicePixelRatio));
		this.renderer.outputColorSpace = THREE.SRGBColorSpace;
		this.renderer.shadowMap.enabled = true;
		this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

		// Le soleil ne bouge pas avec la vue : l'ombre ne se recalcule que lorsque ce qui la porte change, une culture
		// ou le joueur qui marche (`draw()`), et un glisser ne paie qu'une passe sur deux
		this.renderer.shadowMap.autoUpdate = false;
		this.renderer.shadowMap.needsUpdate = true;
		this.renderer.domElement.style.display = 'block';
		this.container.append(this.renderer.domElement);

		this.labelRenderer = new addons.CSS2DRenderer();
		this.labelRenderer.domElement.style.position = 'absolute';
		this.labelRenderer.domElement.style.inset = '0';
		this.labelRenderer.domElement.style.pointerEvents = 'none';
		this.container.append(this.labelRenderer.domElement);

		this.camera = new THREE.OrthographicCamera(-1, 1, 1, -1, -200, 400);
		this.raycaster = new THREE.Raycaster();
		this.pointer = new THREE.Vector2();

		const canvas = this.renderer.domElement;
		const signal = this.listeners.signal;

		// Les réglages du plan de ferme (production/map, `MapScene`), sans la rotation : la cour se lit toujours du même
		// côté, et les marques, la place d'attente du joueur et le haut de l'image en dépendent
		this.controls = new addons.MapControls(this.camera, canvas);
		this.controls.enableRotate = false;
		this.controls.enableDamping = GameBoardActor.MOTION;
		this.controls.dampingFactor = .14;
		this.controls.panSpeed = 1.8;
		this.controls.zoomSpeed = 1.9;
		this.controls.zoomToCursor = true;
		this.controls.touches.TWO = THREE.TOUCH.DOLLY_PAN;
		this.controls.addEventListener('change', () => this.request());
		this.controls.addEventListener('start', () => this.opening = false);

		canvas.addEventListener('pointermove', event => this.hover(event), { signal });
		canvas.addEventListener('pointerdown', event => this.press(event), { signal });
		canvas.addEventListener('pointerup', event => this.release(event), { signal });
		canvas.addEventListener('pointercancel', event => this.cancel(event), { signal });

		this.observer = new ResizeObserver(() => this.frame());
		this.observer.observe(this.container);
		if(this.cover !== null) {
			this.observer.observe(this.cover);
		}

		// Hors de l'écran ou dans un onglet caché, la cour ne dessine plus
		this.visibility = new IntersectionObserver(entries => {
			this.running = entries[0].isIntersecting;
			this.request();
		});
		this.visibility.observe(canvas);

		document.addEventListener('visibilitychange', () => this.request(), { signal });

		Object.keys(this.data.boards).filter(board => this.data.boards[board].open === false).forEach(board => {
			const parcel = this.getParcel(board);
			this.mark(this.roots[board], parcel.cx, parcel.cz, 'board-'+ board, 1.4, false, null);
		});

		this.drawCellar();

		this.frame();

		// Un plateau demandé par l'adresse (`?board=`, depuis l'annonce de son ouverture) cadre la vue sur lui, sinon
		// elle s'ouvre sur ses plateaux
		if(this.data.board === null) {
			this.look(this.getOpening(), this.zoomOpening);
			this.opening = true;
		} else {
			this.look(this.getAim(String(this.data.board)), this.zoomBoard);
		}

		this.drawMarks();

		this.request();

	}

	getParcel(board) {
		return this.court.parcels[Number(board) - 1];
	}

	/**
	 * Un plateau à sa place dans la cour, chaque case tournée comme la cour l'a posée. Ses cases et leurs cultures
	 * sont les enfants directs de son groupe, que `pick()` reconnait
	 */
	buildBoard(board) {

		const { THREE } = this.three;

		const parcel = this.getParcel(board);
		const closed = (this.data.boards[board].open === false);

		const root = new THREE.Group();
		this.scene.add(root);
		this.roots[board] = root;

		// Fermé, le plateau compte sa ganivelle. Ouvert, ses cases et un mètre de cultures, pas la plus haute : la cour
		// se cadrerait autrement à chaque semis et chaque récolte
		const high = closed ? this.getModel('cloture.ganivelle').box.max.y * GameBoard.FENCE_SCALE : 1;
		const reach = closed ? GameBoardCourt.FENCE_GAP : 0;

		parcel.tiles.forEach(tile => {
			const half = GameBoardCourt.tileHalf(tile.a) + reach;
			this.boxes.push(new THREE.Box3(new THREE.Vector3(tile.x - half, 0, tile.z - half), new THREE.Vector3(tile.x + half, high, tile.z + half)));
		});

		this.tiles[board] = {};

		this.data.boards[board].tiles.forEach(data => {

			const tile = parcel.tiles[data.tile - 1];

			const soil = this.cast(new THREE.Mesh(this.soilGeometry, new THREE.MeshLambertMaterial()));
			soil.position.set(tile.x, .08, tile.z);
			soil.rotation.y = tile.a;
			root.add(soil);

			const plants = new THREE.Group();
			plants.position.set(tile.x, .16, tile.z);
			plants.rotation.y = tile.a;
			root.add(plants);

			const entry = { board, soil, plants, x: tile.x, z: tile.z, data: null, mark: null };

			this.tiles[board][data.tile] = entry;
			this.plant(entry, data);

		});

		if(closed) {
			this.buildFence(parcel, root);
		}

	}

	/**
	 * La ganivelle d'un plateau fermé : elle suit l'enveloppe de ses cases à `FENCE_GAP` mètres, la marche s'arrêtant
	 * au même tracé (`GameBoardCourt.getFence()`). Chaque côté de l'enveloppe reçoit des segments d'égale longueur :
	 * une ganivelle à FENCE_SCALE s'y étire d'au plus 15 % plutôt que d'ajouter un segment trop court. Elle n'est pas
	 * tirée par la cour et ne compte pas dans son budget : la cour reste la même quand un plateau ouvre. Ses segments se
	 * fondent par matériau, comme le décor : un plateau en porte une quinzaine, que l'ombre ferait dessiner deux fois
	 * chacun
	 */
	buildFence(parcel, root) {

		const { THREE, addons } = this.three;

		const model = this.getModel('cloture.ganivelle');
		const depth = model.box.max.z - model.box.min.z;
		const scale = GameBoard.FENCE_SCALE;

		const matrix = new THREE.Matrix4();
		const rotation = new THREE.Quaternion();
		const up = new THREE.Vector3(0, 1, 0);
		const geometries = model.parts.map(() => []);

		this.getFenceLine(parcel).forEach(([from, to]) => {

			const dx = to[0] - from[0], dz = to[1] - from[1], side = Math.hypot(dx, dz);
			const count = Math.max(1, Math.ceil(side / (depth * scale) - .15));

			rotation.setFromAxisAngle(up, Math.atan2(dx, dz));

			for(let index = 0; index < count; index++) {

				// Un segment plus court qu'une ganivelle se resserre dans sa longueur, pas dans sa hauteur
				matrix.compose(new THREE.Vector3(from[0] + dx * index / count, 0, from[1] + dz * index / count), rotation, new THREE.Vector3(scale, scale, side / count / depth));

				model.parts.forEach((part, position) => geometries[position].push(part.geometry.clone().applyMatrix4(matrix)));

			}

		});

		model.parts.forEach((part, position) => {
			root.add(this.cast(new THREE.Mesh(addons.mergeGeometries(geometries[position]), this.getMaterial(part))));
			geometries[position].forEach(geometry => geometry.dispose());
		});

	}

	/**
	 * Les côtés de l'enveloppe convexe des cases, chacun écarté de `FENCE_GAP` vers l'extérieur et prolongé jusqu'à
	 * croiser ses voisins. Un côté de moins de 0,3 m, au coin arrondi de deux cases, se fond dans le suivant
	 */
	getFenceLine(parcel) {

		const hull = GameBoardCourt.getOutline(parcel);
		const gap = GameBoardCourt.FENCE_GAP;

		// L'enveloppe tourne dans le sens direct : l'extérieur d'un côté est à sa droite
		const lines = hull.map((point, index) => {
			const next = hull[(index + 1) % hull.length], dx = next[0] - point[0], dz = next[1] - point[1], length = Math.hypot(dx, dz);
			return { point, next, dx: dx / length, dz: dz / length, length };
		}).filter(line => line.length >= .3).map(line => ({ ...line, nx: line.dz, nz: -line.dx }));

		const corners = lines.map((line, index) => {

			const previous = lines[(index + lines.length - 1) % lines.length];

			const ax = previous.point[0] + previous.nx * gap, az = previous.point[1] + previous.nz * gap;
			const bx = line.point[0] + line.nx * gap, bz = line.point[1] + line.nz * gap;
			const cross = previous.dx * line.dz - previous.dz * line.dx;

			// Deux côtés presque alignés se rejoignent au départ du second
			if(Math.abs(cross) < 1e-6) {
				return [bx, bz];
			}

			const t = ((bx - ax) * line.dz - (bz - az) * line.dx) / cross;

			return [ax + previous.dx * t, az + previous.dz * t];

		});

		return corners.map((corner, index) => [corner, corners[(index + 1) % corners.length]]);

	}

	// La culture d'une case à son stade : plus petite au semis, adulte à maturité, la caisse posée au bord quand elle est mûre
	plant(entry, data) {

		entry.data = data;
		entry.plants.clear();

		if(this.renderer !== null) {
			this.renderer.shadowMap.needsUpdate = true;
		}
		entry.soil.material.color.set(this.getSoilColor(entry));

		if(data.growing === null) {
			return;
		}

		// La graine du placement d'une case ne change pas d'un affichage à l'autre : un geste ne fait pas bouger ses voisins
		const random = GameBoard.random(data.id);

		const model = this.getModel(data.growing);
		const boost = GameBoard.BOOST[data.growing] ?? 1;
		const side = Math.max(2, Math.min(3, Math.floor(1.75 / Math.max(model.d * boost, .45))));
		const pitch = GameBoardCourt.TILE / side;
		const stage = data.companion ? 1 : Math.max(.15, data.stage);

		for(let i = 0; i < side; i++) {
			for(let j = 0; j < side; j++) {
				const x = -GameBoardCourt.TILE / 2 + pitch * (i + .5) + (random() - .5) * pitch * .2;
				const z = -GameBoardCourt.TILE / 2 + pitch * (j + .5) + (random() - .5) * pitch * .2;
				this.place(data.growing, entry.plants, x, z, boost * (.35 + .65 * stage) * (.9 + random() * .2), random() * Math.PI * 2);
			}
		}

		if(data.ripe) {
			this.place('caisse', entry.plants, GameBoardCourt.TILE / 2 - .1, GameBoardCourt.TILE / 2 + .35, .7, .3);
		}

	}

	// Le décor de la cour ne porte aucune donnée et rien ne s'y touche : il est fondu en un maillage par matériau, ce
	// qui le dessine en quelques appels au lieu d'un par pièce de chaque objet
	buildDecor() {

		const { THREE, addons } = this.three;

		const pieces = {};
		const matrix = new THREE.Matrix4();
		const rotation = new THREE.Quaternion();
		const up = new THREE.Vector3(0, 1, 0);

		this.court.decor.forEach(([slug, x, z, scale, angle, y]) => {

			const model = this.getModel(slug);

			matrix.compose(new THREE.Vector3(x, y, z), rotation.setFromAxisAngle(up, angle), new THREE.Vector3(scale, scale, scale));

			// La géométrie d'un modèle est partagée avec les cultures et le cellier, qui la posent ailleurs : la fonte en
			// transforme une copie
			model.parts.forEach(part => {
				const key = part.vertex ? 'vertex' : part.color;
				(pieces[key] ??= { part, geometries: [] }).geometries.push(part.geometry.clone().applyMatrix4(matrix));
			});

			this.boxes.push(model.box.clone().applyMatrix4(matrix));

			if(slug === 'puits') {
				this.wellBox = this.boxes.at(-1);
			}

		});

		Object.values(pieces).forEach(({ part, geometries }) => {

			this.scene.add(this.cast(new THREE.Mesh(addons.mergeGeometries(geometries), this.getMaterial(part))));

			geometries.forEach(geometry => geometry.dispose());

		});

		// Le cellier : les caisses empilées près du puits, tournées avec lui, que l'habillage ouvre sur les récoltes du
		// joueur. Il se touche, et reste donc hors de la fonte
		this.cellar = new THREE.Group();
		this.cellar.position.set(this.court.cellar.x, 0, this.court.cellar.z);
		this.cellar.rotation.y = this.court.cellar.angle;
		[[0, 0, 0], [.95, 0, .2], [.45, .55, .1]].forEach(([x, y, rotation]) => this.place('caisse', this.cellar, x, 0, .8, rotation).position.y = y);
		this.scene.add(this.cellar);

		this.boxes.push(new THREE.Box3().setFromObject(this.cellar));

		// L'ile et sa tranche
		const depth = this.court.island.depth;

		for(let index = 0; index < 72; index++) {
			const angle = index / 72 * Math.PI * 2, radius = this.court.getRadiusAt(angle), x = Math.cos(angle) * radius, z = Math.sin(angle) * radius;
			this.boxes.push(new THREE.Box3(new THREE.Vector3(x, -depth, z), new THREE.Vector3(x, 0, z)));
		}

	}

	/**
	 * Ce qui barre le chemin du joueur (`GameBoardCourt.getObstacles()`) : l'eau, le décor, le cellier, le bord de
	 * l'ile, et chaque plateau, ouvert jusqu'à ses cases, fermé jusqu'à sa ganivelle. La grille se calcule à la première
	 * marche, après la première image
	 */
	getGrid() {

		if(this.grid === null) {

			const closed = {};

			Object.keys(this.data.boards).forEach(board => closed[board] = (this.data.boards[board].open === false));

			this.grid = this.court.getPath(closed);

		}

		return this.grid;

	}

	getModel(slug) {

		if(this.models[slug] === undefined) {

			const { THREE, sceneBuildModel } = this.three;
			const built = sceneBuildModel(THREE, this.baked, slug);

			// La boite de toutes les pièces, dont le décor tire son emprise au sol
			const box = new THREE.Box3();

			built.parts.forEach(part => {
				part.geometry.computeBoundingBox();
				box.union(part.geometry.boundingBox);
			});

			this.models[slug] = {
				d: built.d,
				box,
				parts: built.parts.map(part => ({ geometry: part.geometry, color: part.color, vertex: part.geometry.getAttribute('color') !== undefined }))
			};

		}

		return this.models[slug];

	}

	getMaterial(part) {

		const { THREE } = this.three;
		const key = part.vertex ? 'vertex' : part.color;

		// Une pièce mince (feuille, planche) porte son ombre des deux côtés : vue par sa face arrière, elle n'en porterait aucune
		this.materials[key] ??= part.vertex ? new THREE.MeshLambertMaterial({ vertexColors: true, shadowSide: THREE.DoubleSide }) : new THREE.MeshLambertMaterial({ color: '#'+ part.color, shadowSide: THREE.DoubleSide });

		return this.materials[key];

	}

	place(slug, parent, x, z, scale, rotation) {

		const { THREE } = this.three;

		const group = new THREE.Group();
		this.getModel(slug).parts.forEach(part => group.add(this.cast(new THREE.Mesh(part.geometry, this.getMaterial(part)))));
		group.position.set(x, 0, z);
		group.scale.setScalar(scale);
		group.rotation.y = rotation;
		parent.add(group);

		return group;

	}

	getSoilColor(entry) {

		if(this.selected === entry) {
			return GameBoard.COLORS.soilSelected;
		} else if(this.hovered === entry) {
			return GameBoard.COLORS.soilHover;
		} else if(entry.data.watering > 0) {
			return GameBoard.COLORS.soilWatered;
		} else {
			return GameBoard.COLORS.soil;
		}

	}

	paint() {

		Object.values(this.tiles).forEach(tiles => Object.values(tiles).forEach(entry => entry.soil.material.color.set(this.getSoilColor(entry))));
		this.request();

	}

	// Une marque de la page, posée au-dessus d'un point d'un plateau. Une marque vide n'est pas posée
	mark(parent, x, z, key, height, clickable, entry) {

		const html = this.labels[key] ?? '';

		if(html === '') {
			return null;
		}

		const { addons } = this.three;

		const node = document.createElement('div');
		node.innerHTML = html;
		node.style.pointerEvents = clickable ? 'auto' : 'none';

		if(clickable) {
			node.addEventListener('click', event => {
				event.stopPropagation();
				this.touch(entry);
			});
		}

		const object = new addons.CSS2DObject(node);
		object.position.set(x, height, z);
		parent.add(object);

		return object;

	}

	// Les marques des cases de chaque plateau ouvert
	drawMarks() {

		this.marks.splice(0).forEach(object => object.removeFromParent());

		Object.keys(this.tiles).filter(board => this.data.boards[board].open).forEach(board => Object.values(this.tiles[board]).forEach(entry => {

			const object = this.mark(this.roots[board], entry.x, entry.z, String(entry.data.id), .9, entry.data.actionable && entry.data.growing === null && this.data.readonly === false, entry);

			if(object !== null) {
				object.visible = this.marked;
				this.marks.push(object);
			}

		}));

	}

	update(tiles, labels, gestures) {

		Object.assign(this.labels, labels);

		tiles.forEach(data => {

			const entry = this.tiles[String(data.board)]?.[data.tile];

			if(entry !== undefined) {
				this.plant(entry, data);
			}

		});

		this.selected = null;
		this.pending = null;

		this.drawMarks();
		this.drawCellar();
		this.paint();

		this.actor?.play(gestures);

	}

	// La marque du cellier, que la page remplace quand le stock change ; sans elle, le cellier ne se marque pas.
	// `placeCellar()` ne la place qu'une fois sa taille lue, à l'image qui suit son entrée dans la page : d'ici là, elle
	// garde la place de celle qu'elle remplace, et la première se cache. La récolte attend cette place
	// (`gameboard:placed`, `GameDesk.harvested()`)
	drawCellar() {

		const previous = this.cellarMark;

		previous?.removeFromParent();
		this.cellarMark = this.mark(this.scene, 0, 0, 'cellar', 0, true, null);
		this.cellarSize = null;

		if(this.cellarMark === null) {
			return;
		}

		if(previous === null) {
			this.cellarMark.element.style.visibility = 'hidden';
		} else {
			this.cellarMark.position.copy(previous.position);
			this.cellarMark.center.copy(previous.center);
		}

		this.cellarMark.element.addEventListener('click', () => this.emit('cellar', {}));

		this.request();

	}

	/**
	 * La marque du cellier se pose au plus près des caisses dans l'image, à la première place où elle ne couvre ni le
	 * puits, ni une case, ni le bord de la vue : les places se cherchent sur une grille de CELLAR_STEP pixels, jusqu'à
	 * CELLAR_REACH pixels des caisses. Entre places qui couvrent autant, celle qui est plus proche du puits que des
	 * caisses passe après les autres, la marque s'y lirait comme celle du puits. À distance égale, elle prend la place qui empiète le moins sur les
	 * étiquettes des cases ; quand aucune n'est libre, celle qui couvre le moins. Ce qu'elle couvre dépend du zoom, sa taille
	 * étant en pixels : elle se replace quand il change. Toutes les mesures se font dans le plan de l'image, en
	 * pixels, sans lire la page
	 */
	placeCellar() {

		const { THREE } = this.three;

		const scale = this.pixels * this.camera.zoom;
		const step = GameBoard.CELLAR_STEP, reach = GameBoard.CELLAR_REACH;
		const { width, height } = this.cellarSize;

		const extentOf = (box, grow = 0) => {
			const extent = GameBoardCourt.getEmptyExtent();
			GameBoardCourt.projectBox(box, extent);
			return { minX: extent.minX * scale - grow, maxX: extent.maxX * scale + grow, minY: extent.minY * scale - grow, maxY: extent.maxY * scale + grow };
		};

		const tiles = this.court.parcels.flatMap(parcel => parcel.tiles.map(tile => {
			const half = GameBoardCourt.tileHalf(tile.a);
			return { min: { x: tile.x - half, y: 0, z: tile.z - half }, max: { x: tile.x + half, y: 1, z: tile.z + half } };
		}));

		const obstacles = tiles.map(tile => extentOf(tile));
		const labels = tiles.map(tile => extentOf(tile, GameBoard.CELLAR_CLEAR));
		const well = (this.wellBox === null) ? null : extentOf(this.wellBox);

		if(well !== null) {
			obstacles.push(well);
		}

		// La vue, sous ce que l'habillage pose en haut
		const [targetX, targetY] = GameBoardCourt.project(this.controls.target.x, this.controls.target.y, this.controls.target.z);
		const view = {
			minX: targetX * scale - this.camera.right * this.pixels,
			maxX: targetX * scale + this.camera.right * this.pixels,
			minY: targetY * scale - this.camera.top * this.pixels,
			maxY: targetY * scale + this.camera.top * this.pixels - this.head
		};

		// Les caisses, et leur abord, que la marque ne couvre pas
		const body = extentOf(new THREE.Box3().setFromObject(this.cellar));
		const crates = { minX: body.minX - .2 * scale, maxX: body.maxX + .2 * scale, minY: body.minY - .2 * scale, maxY: body.maxY + .2 * scale };

		// Seuls comptent les obstacles qu'une place de la grille peut toucher : le reste du plateau ne change rien au
		// choix, et la recherche se refait à chaque image d'un zoom
		const zone = { minX: crates.minX - width - reach, maxX: crates.maxX + width + reach, minY: crates.minY - height - reach, maxY: crates.maxY + height + reach };
		const touches = extent => extent.maxX > zone.minX && extent.minX < zone.maxX && extent.maxY > zone.minY && extent.minY < zone.maxY;
		const near = obstacles.filter(touches), crowded = labels.filter(touches);

		const overlap = (a, b) => Math.max(0, Math.min(a.maxX, b.maxX) - Math.max(a.minX, b.minX)) * Math.max(0, Math.min(a.maxY, b.maxY) - Math.max(a.minY, b.minY));
		const apart = (a, b) => Math.hypot(Math.max(0, b.minX - a.maxX, a.minX - b.maxX), Math.max(0, b.minY - a.maxY, a.minY - b.maxY));

		let best = null;

		// Le coin bas gauche de la marque parcourt la grille ; `gap` compte en pas de grille sa distance aux caisses
		for(let minX = crates.minX - width - reach; minX <= crates.maxX + reach; minX += step) {

			for(let minY = crates.minY - height - reach; minY <= crates.maxY + reach; minY += step) {

				const rectangle = { minX, maxX: minX + width, minY, maxY: minY + height };

				if(overlap(rectangle, crates) > 0) {
					continue;
				}

				const distance = apart(rectangle, crates);
				const gap = Math.ceil(distance / step);

				if(gap * step > reach) {
					continue;
				}

				// Arrondi au pixel : l'aire hors de la vue est une différence de flottants, dont le bruit ferait passer une
				// place libre lointaine avant une place libre contre les caisses
				const cost = Math.round(near.reduce((sum, obstacle) => sum + overlap(rectangle, obstacle), 0) + width * height - overlap(rectangle, view));
				const crowding = crowded.reduce((sum, label) => sum + overlap(rectangle, label), 0);
				const astray = (well !== null && apart(rectangle, well) < apart(rectangle, body)) ? 1 : 0;

				if(
					best === null ||
					cost < best.cost ||
					(cost === best.cost && (astray < best.astray || (astray === best.astray && (gap < best.gap || (gap === best.gap && crowding < best.crowding)))))
				) {
					best = { x: minX, y: minY, cost, astray, gap, crowding };
				}

			}

		}

		const center = new THREE.Box3().setFromObject(this.cellar).getCenter(new THREE.Vector3());
		const [centerX, centerY] = GameBoardCourt.project(center.x, center.y, center.z);

		this.cellarMark.position.copy(center)
			.addScaledVector(new THREE.Vector3(...GameBoardCourt.RIGHT), best.x / scale - centerX)
			.addScaledVector(new THREE.Vector3(...GameBoardCourt.UP), best.y / scale - centerY);
		this.cellarMark.center.set(0, 1);

	}

	perform(gestures) {

		this.actor?.play(gestures);
		this.request();

	}

	locate(id) {

		const entry = Object.values(this.tiles).flatMap(tiles => Object.values(tiles)).find(entry => entry.data.id === id);

		if(entry === undefined) {
			return null;
		}

		const { THREE } = this.three;

		const world = this.getWorld(entry);
		const point = new THREE.Vector3(world.x, .5, world.z).project(this.camera);
		const rect = this.container.getBoundingClientRect();

		return { x: rect.left + (point.x + 1) / 2 * rect.width, y: rect.top + (1 - point.y) / 2 * rect.height };

	}

	// Le rayon de la caméra sous le pointeur
	aim(event) {

		const rect = this.renderer.domElement.getBoundingClientRect();

		this.pointer.set((event.clientX - rect.left) / rect.width * 2 - 1, -(event.clientY - rect.top) / rect.height * 2 + 1);
		this.raycaster.setFromCamera(this.pointer, this.camera);

		return this.raycaster.ray;

	}

	pick(event) {

		this.aim(event);

		const roots = Object.values(this.roots);
		const entries = Object.values(this.tiles).flatMap(tiles => Object.values(tiles));

		for(const hit of this.raycaster.intersectObjects([...roots, this.cellar], true)) {

			let object = hit.object;

			while(object.parent !== null && object !== this.cellar && roots.includes(object.parent) === false) {
				object = object.parent;
			}

			if(object === this.cellar) {
				return { cellar: true };
			}

			// La ganivelle d'un plateau fermé ne se touche pas : le toucher passe au sol, où le joueur marche
			const entry = entries.find(entry => entry.soil === object || entry.plants === object);

			if(entry !== undefined) {
				return { entry };
			}

		}

		return null;

	}

	hover(event) {

		if(event.pointerType !== 'mouse') {
			return;
		}

		const picked = this.pick(event);
		const entry = this.isTouchable(picked?.entry) ? picked.entry : null;

		this.renderer.domElement.style.cursor = (picked === null || (picked.entry !== undefined && entry === null)) ? '' : 'pointer';

		if(entry !== this.hovered) {
			this.hovered = entry;
			this.paint();
		}

	}

	// Un appui commence peut-être un toucher. Au doigt, rien ne survole : la case se teinte tant qu'il appuie, comme
	// sous la souris avant le clic
	press(event) {

		this.pointers.add(event.pointerId);

		// Un second doigt fait d'un toucher un pincement de la vue ; seul le bouton principal touche, les autres
		// appartiennent à la vue
		this.down = (this.pointers.size === 1 && event.button === 0) ? { x: event.clientX, y: event.clientY, at: performance.now() } : null;

		if(event.pointerType === 'mouse' || this.down === null) {
			return;
		}

		const picked = this.pick(event);
		const entry = this.isTouchable(picked?.entry) ? picked.entry : null;

		if(entry !== this.hovered) {
			this.hovered = entry;
			this.paint();
		}

	}

	release(event) {

		const down = this.down;

		this.pointers.delete(event.pointerId);
		this.down = null;

		if(event.pointerType !== 'mouse' && this.hovered !== null) {
			this.hovered = null;
			this.paint();
		}

		if(down !== null && Math.hypot(event.clientX - down.x, event.clientY - down.y) <= GameBoard.CLICK_PX && performance.now() - down.at < GameBoard.CLICK_MS) {
			this.click(event);
		}

	}

	cancel(event) {

		this.pointers.delete(event.pointerId);
		this.down = null;

		if(this.hovered !== null) {
			this.hovered = null;
			this.paint();
		}

	}

	// Un toucher : le cellier, une case, et à défaut le sol, où le joueur marche
	click(event) {

		const picked = this.pick(event);

		if(picked === null) {
			this.walk(event);
		} else if(picked.cellar) {
			this.emit('cellar', {});
		} else {
			this.touch(picked.entry);
		}

	}

	// Chez un ami, la vue se promène mais le joueur montré n'est pas celui qui regarde : il ne marche pas
	walk(event) {

		if(this.actor === null || this.data.readonly) {
			return;
		}

		const { THREE } = this.three;

		const point = this.aim(event).intersectPlane(new THREE.Plane(new THREE.Vector3(0, 1, 0), 0), new THREE.Vector3());

		if(point === null) {
			return;
		}

		// Une case touchée avant, que le joueur n'a pas atteinte, ne s'ouvrira plus
		if(this.pending !== null) {
			this.unselect();
		}

		this.actor.follow(this.getGrid().find(this.actor, point), null);
		this.request();

	}

	isTouchable(entry) {
		return entry !== undefined && entry !== null && this.data.readonly === false && entry.data.actionable;
	}

	// La case se teinte tout de suite, sa feuille s'ouvre quand le joueur arrive devant elle ; un autre toucher en route
	// la remplace
	touch(entry) {

		if(this.isTouchable(entry) === false) {
			return;
		}

		this.selected = entry;
		this.pending = entry;
		this.paint();

		const open = () => {

			if(this.pending === entry) {
				this.pending = null;
				this.emit('tile', { id: entry.data.id, board: Number(entry.board), tile: entry.data.tile });
			}

		};

		if(this.actor === null) {
			open();
		} else {
			this.actor.walkTo(this.getGrid(), this.getWorld(entry), open);
			this.request();
		}

	}

	// La case n'est plus touchée : elle perd sa teinte, et sa feuille ne s'ouvrira plus si le joueur était en route
	unselect() {

		this.selected = null;
		this.pending = null;
		this.paint();

	}

	/**
	 * L'emprise de la cour dans le plan de l'image : l'ile et sa tranche, le décor, les plateaux jusqu'à leur ganivelle
	 * et le cellier, en mètres le long des axes de la vue, `right` vers la droite de l'image et `up` vers son haut.
	 * `plateaus` est celle des seules cases, que le cadrage d'ouverture serre, et `board` la plus grande
	 * largeur d'un plateau. La vue ne tourne jamais, ses axes se déduisent de VIEW et l'emprise se calcule une fois
	 */
	getExtent() {

		if(this.extent === null) {

			const { THREE } = this.three;

			const extent = {
				right: new THREE.Vector3(...GameBoardCourt.RIGHT),
				up: new THREE.Vector3(...GameBoardCourt.UP),
				...GameBoardCourt.getEmptyExtent(),
				plateaus: GameBoardCourt.getEmptyExtent(),
				board: 0
			};

			this.boxes.forEach(box => GameBoardCourt.projectBox(box, extent));

			this.court.parcels.forEach(parcel => {

				const own = GameBoardCourt.getEmptyExtent();

				parcel.tiles.forEach(tile => {
					const rectangle = GameBoardCourt.tileRect(tile, 0);
					const box = { min: { x: rectangle.minX, y: 0, z: rectangle.minZ }, max: { x: rectangle.maxX, y: 0, z: rectangle.maxZ } };
					GameBoardCourt.projectBox(box, own);
					GameBoardCourt.projectBox(box, extent.plateaus);
				});

				extent.board = Math.max(extent.board, own.maxX - own.minX);

			});

			this.extent = extent;

		}

		return this.extent;

	}

	/**
	 * Le point visé au sol, ramené pour que la vue au zoom `zoom` reste sur la cour : centrée sur elle quand elle tient
	 * entière dans la vue, sous le bandeau de tête, et sinon arrêtée à MARGIN mètres de son bord, de chaque côté selon
	 * ce que le décor y occupe
	 */
	getBounded(target, zoom) {

		const { THREE } = this.three;
		const { right, up, minX, maxX, minY, maxY } = this.getExtent();

		const halfWidth = this.camera.right / zoom, halfHeight = this.camera.top / zoom;
		const head = this.head / this.container.clientHeight * 2 * halfHeight;

		// `reserve` est la part de la vue couverte de son côté haut
		const bound = (value, min, max, half, reserve) => {

			const low = min - GameBoard.MARGIN, high = max + GameBoard.MARGIN;

			if(high - low <= 2 * half - reserve) {
				return (low + high + reserve) / 2;
			}

			return Math.min(high - half + reserve, Math.max(low + half, value));

		};

		const x = target.dot(right), y = target.dot(up);

		// Au sol, un pas vers le haut de l'image ne monte dans la vue que de son produit avec `up`
		const ground = new THREE.Vector3(up.x, 0, up.z).normalize();

		return target.clone()
			.addScaledVector(right, bound(x, minX, maxX, halfWidth, 0) - x)
			.addScaledVector(ground, (bound(y, minY, maxY, halfHeight, head) - y) / ground.dot(up));

	}

	// Les pixels du haut de la scène que couvre l'habillage
	getHead() {

		if(this.cover === null) {
			return 0;
		}

		return Math.max(0, this.cover.getBoundingClientRect().bottom - this.container.getBoundingClientRect().top);

	}

	look(target, zoom) {

		this.controls.target.copy(target);
		this.camera.position.set(target.x + GameBoardCourt.VIEW.x, GameBoardCourt.VIEW.y, target.z + GameBoardCourt.VIEW.z);
		this.camera.lookAt(target);
		this.camera.zoom = zoom;
		this.camera.updateProjectionMatrix();
		this.request();

	}

	// Le point visé par la vue d'un plateau : son centre, remonté d'une demi-tête pour que le plateau se centre sous ce
	// que l'habillage pose en haut, et que la case du fond reste touchable, au plus sa pointe dessous
	getAim(board) {

		const { THREE } = this.three;
		const { up } = this.getExtent();

		const head = this.head / this.container.clientHeight * 2 * this.camera.top / this.zoomBoard;
		const ground = new THREE.Vector3(up.x, 0, up.z).normalize();

		const parcel = this.getParcel(board);

		return new THREE.Vector3(parcel.cx, 0, parcel.cz).addScaledVector(ground, head / 2 / ground.dot(up));

	}

	// Où le joueur attend devant un plateau : à son entrée, tournée vers le puits ou vers le pont, hors de ce qui barre le
	// chemin, ganivelle comprise (`GameBoardCourt.getEntry()`)
	getWaiting(board) {

		const parcel = this.getParcel(board);

		return { x: parcel.entry.x, z: parcel.entry.z, board: { x: parcel.cx, z: parcel.cz } };

	}

	getWorld(entry) {
		return { x: entry.x, z: entry.z };
	}

	/**
	 * Le point visé à l'ouverture sur toute la cour : le milieu de ses plateaux, remonté d'une demi-tête pour qu'ils se
	 * centrent sous ce que l'habillage pose en haut. `getBounded()` le ramène au milieu de la cour quand elle tient
	 * entière dans la vue
	 */
	getOpening() {

		const { THREE } = this.three;
		const { right, up, plateaus } = this.getExtent();

		const head = this.head / this.container.clientHeight * 2 * this.camera.top / this.zoomOpening;
		const ground = new THREE.Vector3(up.x, 0, up.z).normalize();

		return new THREE.Vector3()
			.addScaledVector(right, (plateaus.minX + plateaus.maxX) / 2)
			.addScaledVector(ground, ((plateaus.minY + plateaus.maxY) / 2 + head / 2) / ground.dot(up));

	}

	// Le zoom 1 tient SPAN dans la largeur ; celui de toute la cour, `zoomCourt`, tient son emprise entière sous ce que
	// l'habillage pose en haut ; celui d'un plateau, `zoomBoard`, tient le plus large ; celui de l'ouverture,
	// `zoomOpening`, tient ses plateaux. Le zoom courant appartient au joueur dès qu'il a touché la cour, et se garde
	// alors d'une taille de la vue à l'autre
	frame() {

		if(this.renderer === null) {
			return;
		}

		const width = this.container.clientWidth, height = this.container.clientHeight;

		if(width === 0 || height === 0) {
			return;
		}

		// La tête se mesure avant que `setSize()` n'écrive le style du canevas, sans quoi la mesure force la mise en
		// page ; `getBounded()` la relit à chaque image, et l'observateur de `cover` refait ce cadrage quand elle change
		this.head = this.getHead();

		this.renderer.setSize(width, height);
		this.labelRenderer.setSize(width, height);

		// La largeur change ce que la pastille montre sous 576 px (game.css) : sa taille se relit
		this.cellarSize = null;
		this.cellarZoom = null;

		const fit = span => {

			let halfWidth = span / 2, halfHeight = halfWidth / (width / height);
			const needed = span * .62 / 2;

			if(halfHeight < needed) {
				halfHeight = needed;
				halfWidth = halfHeight * width / height;
			}

			return { halfWidth, halfHeight };

		};

		const { halfWidth, halfHeight } = fit(GameBoard.SPAN);

		// Les pixels d'un mètre au zoom 1
		this.pixels = width / (2 * halfWidth);

		const { minX, maxX, minY, maxY, plateaus, board } = this.getExtent();
		const visible = (height - this.head) / height;
		const zoomOf = extent => Math.min(2 * halfWidth / (extent.maxX - extent.minX + 2 * GameBoard.MARGIN), 2 * halfHeight * visible / (extent.maxY - extent.minY + 2 * GameBoard.MARGIN));

		this.zoomBoard = halfWidth / fit(board * (width < 600 ? 1.02 : 1.18)).halfWidth;
		this.zoomCourt = Math.min(1, zoomOf({ minX, maxX, minY, maxY }));

		// L'ouverture serre les plateaux, et laisse déborder la lisière, que le glisser et le pincement rendent : la cour
		// entière laisserait le fond autour de l'ile, et en portrait ses cases tomberaient sous le doigt
		this.zoomOpening = Math.max(this.zoomCourt, Math.min(this.zoomBoard, zoomOf(plateaus)));
		this.controls.minZoom = this.zoomCourt * ((width < 600) ? 1 : GameBoard.ZOOM_MIN);
		this.controls.maxZoom = this.zoomBoard * GameBoard.ZOOM_MAX;

		this.camera.left = -halfWidth;
		this.camera.right = halfWidth;
		this.camera.top = halfHeight;
		this.camera.bottom = -halfHeight;
		this.camera.updateProjectionMatrix();

		// Tant que le joueur n'a pas touché la cour, la vue reste celle de l'ouverture, qui change avec la forme de la
		// vue : un téléphone qu'on tourne recadre ses plateaux
		if(this.opening) {
			this.look(this.getOpening(), this.zoomOpening);
		}

		this.request();

	}

	request() {

		if(this.frameId !== null || this.renderer === null || this.running === false || document.hidden) {
			return;
		}

		this.frameId = requestAnimationFrame(now => {

			this.frameId = null;
			this.draw(now);

		});

	}

	draw(now) {

		let moving = false, placed = false;

		moving = this.controls.update() || moving;

		// Un glisser ou un zoom s'arrêtent au bord de la cour
		const target = this.controls.target;
		const shift = this.getBounded(target, this.camera.zoom).sub(target);

		if(shift.lengthSq() > 1e-8) {
			target.add(shift);
			this.camera.position.add(shift);
		}

		const marked = (GameBoardCourt.TILE * this.pixels * this.camera.zoom >= GameBoard.MARK_PX);

		// La taille de la marque du cellier se lit dans la page avant que l'image n'y écrive, une fois par marque et par
		// largeur ; posée par l'image précédente, elle se lit à la suivante
		if(this.cellarMark !== null && this.cellarMark.element.isConnected && (this.cellarSize === null || this.cellarZoom !== this.camera.zoom)) {

			this.cellarSize ??= { width: this.cellarMark.element.offsetWidth, height: this.cellarMark.element.offsetHeight };
			this.cellarZoom = this.camera.zoom;
			this.placeCellar();
			this.cellarMark.element.style.visibility = '';
			placed = true;

		} else if(this.cellarMark !== null && this.cellarSize === null) {
			moving = true;
		}

		if(marked !== this.marked) {
			this.marked = marked;
			this.marks.forEach(object => object.visible = marked);
		}

		if(this.actor !== null) {

			const acting = this.actor.update(now / 1000);

			// L'image où le joueur s'arrête le pose encore : son ombre suit jusqu'à elle
			if(acting || this.acting) {
				this.renderer.shadowMap.needsUpdate = true;
			}

			this.acting = acting;
			moving = acting || moving;

		}

		this.renderer.render(this.scene, this.camera);
		this.labelRenderer.render(this.scene, this.camera);

		// Une fois la marque du cellier écrite à sa place : ce qui vise la marque se mesure à partir de là
		if(placed) {
			this.emit('placed', {});
		}

		if(moving) {
			this.request();
		}

	}

	/**
	 * Le joueur, monté depuis sa fiche par la fabrique du socle (`SceneLoader.loadAvatar()`), comme l'équipe de l'accueil.
	 * Il attend près du puits ou au bord du plateau que l'adresse demande, marche jusqu'à la case touchée et y fait le
	 * geste que la page nomme. Il porte la lanterne de la veillée.
	 */
	mountAvatar(loaded) {

		const { THREE } = loaded;

		const ramp = new THREE.DataTexture(new Uint8Array([120, 160, 200, 235, 255]), 5, 1, THREE.RedFormat);
		ramp.minFilter = ramp.magFilter = THREE.LinearFilter;
		ramp.needsUpdate = true;

		const model = loaded.avatar.build(this.data.avatar);
		const figure = loaded.figure.assemble(model, 'avatar', new THREE.MeshToonMaterial({ color: '#fff', vertexColors: true, gradientMap: ramp }));

		figure.root.traverse(object => {
			if(object.isMesh) {
				this.cast(object);
			}
		});

		const holder = new THREE.Group();
		holder.scale.setScalar(1.25);
		holder.add(figure.root);
		this.scene.add(holder);

		// L'anneau désigne le joueur : sans lumière à recevoir, il garde son or quelle que soit la cour
		const ring = new THREE.Mesh(new THREE.RingGeometry(.36, .46, 40), new THREE.MeshBasicMaterial({ color: GameBoard.LANTERN.ring, side: THREE.DoubleSide }));
		ring.rotation.x = -Math.PI / 2;
		ring.position.y = .03;
		holder.add(ring);

		this.lantern.position.set(.3, 1.6, .3);
		this.lantern.intensity = GameBoard.LANTERN.intensity;
		holder.add(this.lantern);

		const label = this.mark(holder, 0, 0, 'avatar', 2.15, true, null);

		if(label !== null) {
			label.element.addEventListener('click', () => this.emit('avatar', {}));
		}

		this.ramp = ramp;
		this.actor = new GameBoardActor(loaded, model, figure, holder, this.court.home);

		if(this.data.board !== null) {
			this.actor.wait(this.getWaiting(String(this.data.board)));
		}

		this.renderer.shadowMap.needsUpdate = true;
		this.request();

	}

	close() {

		this.closed = true;

		if(this.frameId !== null) {
			cancelAnimationFrame(this.frameId);
			this.frameId = null;
		}

		this.listeners.abort();
		this.controls?.dispose();
		this.observer?.disconnect();
		this.visibility?.disconnect();

		if(this.renderer !== null) {

			// Un matériau se partage entre maillages : chacun n'est libéré qu'une fois
			const materials = new Set(Object.values(this.materials));

			this.scene.traverse(object => {
				if(object.isMesh) {

					if(object.geometry !== this.soilGeometry) {
						object.geometry.dispose();
					}

					[object.material].flat().forEach(material => materials.add(material));

				}
			});

			this.soilGeometry.dispose();
			materials.forEach(material => material.dispose());
			this.ramp?.dispose();

			// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer.domElement.remove();
			this.labelRenderer.domElement.remove();
			this.renderer = null;

		}

	}

	// Un tirage déterministe, de la graine d'une case
	static random(seed) {

		let state = (seed % 2147483646) + 1;

		return () => (state = (state * 16807) % 2147483647) / 2147483647;

	}

}

/**
 * Le joueur dans la cour : sa marche jusqu'à une case, puis les gestes que la page lui nomme, l'un après l'autre.
 * Les gestes viennent tous du socle ; seuls la marche et l'enchaînement sont d'ici.
 */
class GameBoardActor {

	// Les gestes que la page peut nommer, d'où chacun vient dans le socle, et combien de secondes il se tient :
	// une figure de `figure.pose()`, un geste animé de `avatar.ANIMATIONS`, une posture des `poses` du modèle,
	// une émotion des `emotions` du modèle, ces deux dernières mesurées sur la personne par `avatar.build()`
	static GESTURES = {
		crouch: { source: 'figure', seconds: 2 },
		hoe: { source: 'figure', seconds: 2.4 },
		arroser: { source: 'animation', seconds: 2 },
		recolte: { source: 'pose', seconds: 1 },
		joyeux: { source: 'emotion', seconds: 1.8 },
		boire: { source: 'animation', seconds: 2 },
		saluer: { source: 'animation', seconds: 2.6 },
		fier: { source: 'emotion', seconds: 2.5 }
	};

	static MOTION = (window.matchMedia('(prefers-reduced-motion: reduce)').matches === false);

	constructor(loaded, model, figure, holder, home) {

		this.loaded = loaded;
		this.model = model;
		this.figure = figure;
		this.holder = holder;
		this.home = home;

		this.x = home.x;
		this.z = home.z;
		this.yaw = Math.PI / 4;
		this.yawTarget = this.yaw;
		this.path = null;
		this.phase = 0;
		this.queue = [];
		this.gesture = null;
		this.since = 0;
		this.last = null;

	}

	// La case est prise : le chemin s'arrête au bord du plateau le plus proche d'elle, et le joueur se tourne vers elle
	walkTo(grid, point, arrive) {
		this.follow(grid.find(this, point), point, arrive);
	}

	// Passe par chaque point de `points`, puis se tourne vers `face` s'il y en a un et appelle `arrive`. Un nouveau
	// chemin remplace le précédent, qui n'arrive jamais
	follow(points, face, arrive = null) {

		if(points.length === 0) {
			this.path = null;
			arrive?.();
			return;
		}

		this.path = { points, face, arrive };

		if(GameBoardActor.MOTION === false) {

			const last = points[points.length - 1];

			this.x = last.x;
			this.z = last.z;
			this.path = null;

			if(face !== null) {
				this.yaw = this.yawTarget = Math.atan2(face.x - this.x, face.z - this.z);
			}

			arrive?.();

		}

	}

	// Le joueur est posé devant le plateau que l'adresse cadre, sans marcher : la caméra y est déjà, et il serait hors
	// de l'image le temps de sa marche
	wait(point) {

		this.x = point.x;
		this.z = point.z;
		this.yaw = this.yawTarget = Math.atan2(point.board.x - point.x, point.board.z - point.z);

	}

	// Les gestes attendent que le joueur soit arrivé à la case, et chassent ceux qu'un geste précédent n'a pas finis
	play(gestures) {

		this.queue = [...gestures];
		this.gesture = null;

	}

	// Rend vrai tant que le joueur bouge, pour que la cour continue de dessiner
	update(time) {

		const delta = (this.last === null) ? 0 : Math.min(.1, time - this.last);
		this.last = time;

		let moving = false;

		if(this.path !== null) {

			const next = this.path.points[0];
			const dx = next.x - this.x, dz = next.z - this.z, distance = Math.hypot(dx, dz), step = GameBoard.WALK * delta;

			if(distance <= step) {

				this.x = next.x;
				this.z = next.z;
				this.path.points.shift();

				if(this.path.points.length === 0) {

					if(this.path.face !== null) {
						this.yawTarget = Math.atan2(this.path.face.x - this.x, this.path.face.z - this.z);
					}

					const arrive = this.path.arrive;
					this.path = null;
					arrive?.();

				}

			} else {
				this.x += dx / distance * step;
				this.z += dz / distance * step;
				this.yawTarget = Math.atan2(dx, dz);
				this.phase += step * 4.2;
			}

			moving = true;

		}

		let turn = this.yawTarget - this.yaw;
		turn = Math.atan2(Math.sin(turn), Math.cos(turn));
		this.yaw += turn * Math.min(1, delta * 8);

		if(Math.abs(turn) > .01) {
			moving = true;
		}

		this.holder.position.set(this.x, 0, this.z);
		this.holder.rotation.y = this.yaw;

		this.rest();

		if(this.path === null) {

			if(this.gesture !== null && time >= this.since + GameBoardActor.GESTURES[this.gesture].seconds) {
				this.gesture = null;
			}

			if(this.gesture === null && this.queue.length > 0) {
				this.gesture = this.queue.shift();
				this.since = time;
			}

		}

		let free = false;

		if(this.path !== null) {
			this.figure.root.position.y = this.loaded.figure.pose(this.figure, 'walk', { phase: this.phase, time });
		} else if(this.gesture !== null) {
			free = this.pose(this.gesture, time - this.since, time);
			moving = true;
		} else {
			this.figure.root.position.y = this.loaded.figure.pose(this.figure, 'stand', { time });
		}

		// L'objet tenu disparait le temps d'un geste qui prend les deux mains : le socle le poserait au sol à côté du pied,
		// ce qu'un joueur vu de si loin ne lirait pas (même choix que l'équipe de l'accueil, `team.js`)
		if(this.figure.bones.objet !== undefined) {
			this.figure.bones.objet.visible = (free === false);
		}

		return moving;

	}

	// Pose le geste à l'instant donné, et rend si l'objet tenu quitte la main pendant ce temps (`mainsLibres`)
	pose(gesture, elapsed, time) {

		const source = GameBoardActor.GESTURES[gesture].source;

		if(source === 'figure') {
			// Le binage avance sur son propre départ, l'accroupi respire sur l'horloge de la cour
			this.figure.root.position.y = this.loaded.figure.pose(this.figure, gesture, { phase: this.phase, time: (gesture === 'hoe') ? elapsed * 1.6 : time });
			return false;
		}

		let angles;

		if(source === 'animation') {
			angles = this.loaded.avatar.animer(this.model, gesture, GameBoardActor.MOTION ? elapsed : null).pose;
		} else if(source === 'pose') {
			angles = this.model.poses[gesture];
		} else {
			angles = this.model.emotions[gesture].pose;
		}

		this.apply(angles);

		return (angles.mainsLibres === true);

	}

	rest() {

		[this.figure.root, ...Object.values(this.figure.bones)].forEach(bone => bone.rotation.set(0, 0, 0));
		this.figure.torso.position.y = this.figure.rest;
		this.figure.root.position.y = 0;

	}

	apply(angles) {

		Object.entries(angles)
			.filter(([name]) => this.figure.bones[name] !== undefined)
			.forEach(([name, angle]) => this.figure.bones[name].rotation.set(...angle));

		this.figure.root.position.y = -(angles.descente ?? 0) * this.model.anchors.descenteAssis;

	}

}

document.addEventListener('navigation.sleep', () => GameBoard.stop());
