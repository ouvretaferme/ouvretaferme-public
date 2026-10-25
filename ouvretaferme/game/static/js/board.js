/**
 * La cour du jeu (lot 2 du chantier jeu, direction C de la planche) : les quatre plateaux d'un même monde, entourés
 * de l'herbe et du décor du plan (haies, arbres, verger, mare, puits, outils). Chaque case montre sa culture à son
 * stade, sa caisse quand elle est mûre ; un plateau fermé porte sa ganivelle. La vue se glisse, se pince et se zoome
 * à la molette dans les bornes de la cour. Le joueur s'y tient en personne, monté depuis la fiche de son avatar, et
 * marche jusqu'à la case ou au point du sol touché, en contournant les cases et le décor (`GameBoardPath`, path.js).
 * Un toucher ne déplace jamais la vue, qui appartient au joueur.
 *
 * La scène ne connaît que `#game-board` : elle lit la donnée de `data-game-board`, pose les marques que la page rend
 * dans ses `<template data-game-label>`, et dit ce qui se passe par des événements qui remontent de `#game-board`
 * (`gameboard:ready`, `error`, `tile`, `cellar`, `avatar`). L'habillage les écoute (`GameDesk`, desk.js) et lui répond
 * par `apply()`, `react()` et `clear()` ; `locate()` lui dit où une case se trouve à l'écran.
 *
 * Une seule cour vit à la fois, dans un seul contexte WebGL, libéré sur `navigation.sleep`.
 */
class GameBoard {

	static current = null;

	// Le côté d'une case et le pas d'une case à la suivante, en mètres, de la planche : quatre cases par rang
	static TILE = 2;
	static STEP = 2.35;
	static SIDE = GameBoard.STEP * 3 + GameBoard.TILE;

	// L'écart d'un plateau à son voisin dans la cour, allée comprise
	static SPREAD = GameBoard.SIDE + 7;

	// La ganivelle d'un plateau fermé court à FENCE mètres de son centre, posée à l'échelle FENCE_SCALE
	static FENCE = GameBoard.SIDE / 2 + .6;
	static FENCE_SCALE = 1.6;

	// Les petites espèces sont relevées pour se lire sur une case de 2 m, de la planche
	static BOOST = { betterave: 2.6, carotte: 2.6, luzerne: 1.6, pois: 1.3 };

	// L'herbe du plan, voulue par Vincent pour la direction C
	static COLORS = { ground: '#9cbf63', soil: '#8a5a3b', soilHover: '#a77250', soilSelected: '#c09068', soilWatered: '#6b4129' };

	// La lumière de la cour, celle de la planche que Vincent a demandée en recette : l'herbe et la terre gardent leur
	// couleur sous un ciel blanc et un soleil bas, et l'herbe s'assombrit vers les bords (`edge`, qu'elle atteint de
	// `inner` à `outer` mètres du centre de la cour), comme la vignette de la planche. `ambient` vaut à peu près π :
	// un matériau de Lambert divise la lumière reçue par π, et c'est à ce prix qu'il rend sa propre couleur
	static LIGHT = { sky: '#ffffff', earth: '#8a8a6a', ambient: 3.2, sun: '#fff1d6', sunIntensity: .3, edge: '#a6a9d1', inner: 14, outer: 28 };

	// La lanterne que le joueur porte, et l'anneau d'or à ses pieds qui le désigne dans la cour. Sa lumière est orangée
	// parce qu'elle s'ajoute au vert de l'herbe : c'est leur somme qui fait le halo jaune de la planche
	static LANTERN = { color: '#ff6a00', intensity: 12, distance: 7, decay: 1.6, ring: '#f2c14e' };

	// Où le joueur attend dans la cour : à gauche du puits, pour ne pas le cacher
	static HOME = { x: -2.2, z: 2.6 };

	// La vitesse de marche du joueur, en mètres par seconde
	static WALK = 2.2;

	// D'où la caméra regarde le point visé : de trois quarts, toujours du même côté, la rotation étant coupée
	static VIEW = { x: 20, y: 22, z: 20 };

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

	// La vue ne quitte pas la cour, et s'arrête à MARGIN mètres de son emprise
	static MARGIN = .5;

	// La grille du chemin, en cellules de 0,2 m. Les cases et le décor se contournent à REACH mètres, la demi-largeur
	// du joueur avec ses bras : à moins, son corps mord à l'écran sur le bord des cases qu'il longe. Le passe-pied de
	// 0,35 m entre deux cases se ferme ainsi, et le joueur fait le tour du plateau
	static CELL = .2;
	static REACH = .3;

	// Un modèle ne décrit que l'emprise de toute sa silhouette, couronne comprise : le tronc d'un arbre, seul à barrer
	// le chemin, en est pris comme cette part de la demi-emprise
	static TRUNK = .18;
	static TREES = ['arbre', 'arbre.sapin', 'bouleau', 'chene', 'fruitier'];

	// La marche reste à moins de YARD mètres du centre sur chaque axe. La limite passe derrière le décor, à plus d'une
	// largeur de joueur : une pièce posée contre elle fermerait une poche d'herbe avec un plateau fermé
	static YARD = 19;

	// Le décor, le même pour tous les joueurs : [slug, x, z, échelle, rotation]. Il occupe les quatre coins de l'image
	// laissés libres par les plateaux : au fond les haies, les arbres et le coin d'outils, à l'avant ce qui reste bas,
	// pour ne cacher aucune case. Les allées restent à la marche. Le cadrage de toute la cour le montre entier
	// (`getExtent()`, `zoomCourt`)
	static DECOR = [
		...[-1.05, .9, 3, 5.1].map(z => ['haie.charmille', -17.6, z, .7, 0]),
		...[-1.1, .9, 3.05, 5.2].map(x => ['haie.charmille', x, -17.6, .7, Math.PI / 2]),
		['arbre', -15.4, 2.4, .55, 0], ['bouleau', -16, 5.6, .6, 1.1], ['arbre.sapin', -14.6, 7.8, .6, 0],
		['chene', 2.2, -14.6, .4, .4], ['fruitier', 5.8, -15.4, .55, .7], ['bouleau', 0, -13.2, .6, 2.3],
		['compost', -14.4, -.6, .55, 1.6], ['cuve', -16.2, -1.6, .9, 0], ['brouette', -13.6, 1.9, .9, 2.2],
		['caisses', -16, .6, 1, .5], ['palette', -14, -2.4, 1, .3],
		['mare', -4.6, 15.4, .5, .3], ['roseaux', -7.8, 14.4, .6, 0], ['roseaux', -1.2, 17.2, .55, 1.4],
		['puits', 0, 0, .9, .6],
		['bande-fleurie', 16, .4, 1, 0], ['bande-fleurie', 3, 15.8, 1, Math.PI / 2],
		['rocher', 15.2, -3.2, .7, .8], ['souche', 14.4, 5, .9, 1.7],
		['buisson', 16.8, 4.2, .8, 0], ['buisson', 6.4, 16.2, .75, 1], ['buisson', 13.8, 2.6, .7, 2]
	];

	// `cover` est ce que l'habillage pose sur le haut de la scène (bandeaux, rangée de l'Avent), sous lequel la cour se
	// cadre ; l'accueil et la fin de partie n'en posent pas
	static start(node, cover = null) {

		GameBoard.stop();

		// L'attente de la cour : son herbe, sous la page déjà en place, jusqu'à la première image
		node.style.backgroundColor = GameBoard.COLORS.ground;

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

		this.models = {};
		this.materials = {};
		this.obstacles = [];
		this.grid = null;
		this.boxes = [];
		this.extent = null;
		this.controls = null;
		this.zoomBoard = 1;
		this.zoomCourt = 1;
		this.head = 0;
		this.down = null;
		this.pointers = new Set();

	}

	open() {

		const bake = JSON.parse(this.container.dataset.sceneBake);

		return Promise.all([
			SceneLoader.load(this.container),
			SceneLoader.loadPair({ directory: this.container.dataset.sceneDirectory, json: bake.json, bin: bake.bin })
		])
			.then(([three, baked]) => {

				if(this.closed) {
					return;
				}

				this.three = three;
				this.baked = baked;

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

	// Le sol, en carreaux de 5 m pour que la teinte de chaque sommet porte la vignette : pleine au centre de la cour,
	// `edge` au-delà de `outer`
	getGroundGeometry() {

		const { THREE } = this.three;

		const geometry = new THREE.PlaneGeometry(400, 400, 80, 80);
		const position = geometry.attributes.position;
		const colors = new Float32Array(position.count * 3);

		const center = new THREE.Color(1, 1, 1);
		const edge = new THREE.Color(GameBoard.LIGHT.edge);
		const color = new THREE.Color();

		for(let index = 0; index < position.count; index++) {

			const radius = Math.hypot(position.getX(index), position.getY(index));
			const ratio = THREE.MathUtils.smoothstep(radius, GameBoard.LIGHT.inner, GameBoard.LIGHT.outer);

			color.copy(center).lerp(edge, ratio).toArray(colors, index * 3);

		}

		geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

		return geometry;

	}

	emit(name, detail) {
		this.node.dispatchEvent(new CustomEvent('gameboard:'+ name, { bubbles: true, detail }));
	}

	mount() {

		const { THREE, addons } = this.three;

		this.origins = {};

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight(GameBoard.LIGHT.sky, GameBoard.LIGHT.earth, GameBoard.LIGHT.ambient));

		const sun = new THREE.DirectionalLight(GameBoard.LIGHT.sun, GameBoard.LIGHT.sunIntensity);
		sun.position.set(-12, 5, 6);
		this.scene.add(sun);

		// Le nombre de lumières entre dans chaque matériau compilé : la lanterne du joueur, qui arrive après la
		// première image, est donc posée ici, éteinte, et lui sera seulement rattachée
		this.lantern = new THREE.PointLight(GameBoard.LANTERN.color, 0, GameBoard.LANTERN.distance, GameBoard.LANTERN.decay);
		this.scene.add(this.lantern);

		const ground = new THREE.Mesh(this.getGroundGeometry(), new THREE.MeshLambertMaterial({ color: GameBoard.COLORS.ground, vertexColors: true }));
		ground.rotation.x = -Math.PI / 2;
		this.scene.add(ground);

		this.soilGeometry = new THREE.BoxGeometry(GameBoard.TILE, .16, GameBoard.TILE);

		Object.keys(this.data.boards).forEach(board => this.buildBoard(board));

		this.buildDecor();

		this.renderer = new THREE.WebGLRenderer({ antialias: true });
		this.renderer.setPixelRatio(Math.min(2, window.devicePixelRatio));
		this.renderer.outputColorSpace = THREE.SRGBColorSpace;
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

		Object.keys(this.data.boards).filter(board => this.data.boards[board].open === false).forEach(board => this.mark(this.roots[board], 0, 0, 'board-'+ board, 1.4, false, null));

		this.drawCellar();

		this.frame();

		// Un plateau demandé par l'adresse (`?board=`, depuis l'annonce de son ouverture) cadre la vue sur lui, sinon
		// elle s'ouvre sur toute la cour
		if(this.data.board === null) {
			this.look(new THREE.Vector3(), this.zoomCourt);
		} else {
			this.look(this.getAim(String(this.data.board)), this.zoomBoard);
		}

		this.drawMarks();

		this.request();

	}

	// Les quatre plateaux en carré, le premier en haut à gauche
	buildBoard(board) {

		const { THREE } = this.three;

		const index = Number(board) - 1;
		const origin = new THREE.Vector3((index % 2 === 0 ? -1 : 1) * GameBoard.SPREAD / 2, 0, (index < 2 ? -1 : 1) * GameBoard.SPREAD / 2);

		this.origins[board] = origin;

		const root = new THREE.Group();
		root.position.copy(origin);
		this.scene.add(root);

		// Fermé, le plateau compte sa ganivelle. Ouvert, ses cases et un mètre de cultures, pas la plus haute : la cour
		// se cadrerait autrement à chaque semis et chaque récolte, et la pointe d'un maïs mûr au fond du plateau peut
		// passer sous le bandeau de tête
		const [half, high] = (this.data.boards[board].open === false) ? [GameBoard.FENCE, this.getModel('cloture.ganivelle').box.max.y * GameBoard.FENCE_SCALE] : [GameBoard.SIDE / 2, 1];
		this.boxes.push(new THREE.Box3(new THREE.Vector3(origin.x - half, 0, origin.z - half), new THREE.Vector3(origin.x + half, high, origin.z + half)));
		this.roots[board] = root;

		this.tiles[board] = {};

		this.data.boards[board].tiles.forEach(data => {

			const row = Math.floor((data.tile - 1) / 4), column = (data.tile - 1) % 4;
			const x = (column - 1.5) * GameBoard.STEP, z = (row - 1.5) * GameBoard.STEP;

			const soil = new THREE.Mesh(this.soilGeometry, new THREE.MeshLambertMaterial());
			soil.position.set(x, .08, z);
			root.add(soil);

			const plants = new THREE.Group();
			plants.position.set(x, .16, z);
			root.add(plants);

			const entry = { board, soil, plants, x, z, data: null, mark: null };

			this.tiles[board][data.tile] = entry;
			this.plant(entry, data);

		});

		if(this.data.boards[board].open === false) {
			for(let side = 0; side < 4; side++) {
				const angle = side / 4 * Math.PI * 2;
				this.place('cloture.ganivelle', root, Math.cos(angle) * GameBoard.FENCE, Math.sin(angle) * GameBoard.FENCE, GameBoard.FENCE_SCALE, -angle + Math.PI / 2);
			}
		}

	}

	// La culture d'une case à son stade : plus petite au semis, adulte à maturité, la caisse posée au bord quand elle est mûre
	plant(entry, data) {

		entry.data = data;
		entry.plants.clear();
		entry.soil.material.color.set(this.getSoilColor(entry));

		if(data.growing === null) {
			return;
		}

		// La graine du placement d'une case ne change pas d'un affichage à l'autre : un geste ne fait pas bouger ses voisins
		const random = GameBoard.random(data.id);

		const model = this.getModel(data.growing);
		const boost = GameBoard.BOOST[data.growing] ?? 1;
		const side = Math.max(2, Math.min(3, Math.floor(1.75 / Math.max(model.d * boost, .45))));
		const pitch = GameBoard.TILE / side;
		const stage = data.companion ? 1 : Math.max(.15, data.stage);

		for(let i = 0; i < side; i++) {
			for(let j = 0; j < side; j++) {
				const x = -GameBoard.TILE / 2 + pitch * (i + .5) + (random() - .5) * pitch * .2;
				const z = -GameBoard.TILE / 2 + pitch * (j + .5) + (random() - .5) * pitch * .2;
				this.place(data.growing, entry.plants, x, z, boost * (.35 + .65 * stage) * (.9 + random() * .2), random() * Math.PI * 2);
			}
		}

		if(data.ripe) {
			this.place('caisse', entry.plants, GameBoard.TILE / 2 - .1, GameBoard.TILE / 2 + .35, .7, .3);
		}

	}

	// Le décor de la cour n'est porté par aucune donnée : le même pour tous les joueurs. Rien ne s'y touche, il est donc
	// fondu en un maillage par matériau, ce qui le dessine en quelques appels au lieu d'un par pièce de chaque objet
	buildDecor() {

		const { THREE, addons } = this.three;

		const pieces = {};
		const matrix = new THREE.Matrix4();
		const rotation = new THREE.Quaternion();
		const up = new THREE.Vector3(0, 1, 0);

		this.obstacles = [];

		GameBoard.DECOR.forEach(([slug, x, z, scale, angle]) => {

			const model = this.getModel(slug);

			matrix.compose(new THREE.Vector3(x, 0, z), rotation.setFromAxisAngle(up, angle), new THREE.Vector3(scale, scale, scale));

			// La géométrie d'un modèle est partagée avec les cultures et le cellier, qui la posent ailleurs : la fonte en
			// transforme une copie
			model.parts.forEach(part => {
				const key = part.vertex ? 'vertex' : part.color;
				(pieces[key] ??= { part, geometries: [] }).geometries.push(part.geometry.clone().applyMatrix4(matrix));
			});

			const box = model.box.clone().applyMatrix4(matrix);
			this.boxes.push(box);

			if(GameBoard.TREES.includes(slug)) {
				const radius = Math.min(box.max.x - box.min.x, box.max.z - box.min.z) / 2 * GameBoard.TRUNK;
				this.obstacles.push({ minX: x - radius, maxX: x + radius, minZ: z - radius, maxZ: z + radius });
			} else {
				this.obstacles.push(GameBoard.getFootprint(box, GameBoard.REACH));
			}

		});

		Object.values(pieces).forEach(({ part, geometries }) => {

			this.scene.add(new THREE.Mesh(addons.mergeGeometries(geometries), this.getMaterial(part)));

			geometries.forEach(geometry => geometry.dispose());

		});

		// Le cellier : les caisses empilées près du puits, que l'habillage ouvre sur les récoltes du joueur. Il se touche,
		// et reste donc hors de la fonte
		this.cellar = new THREE.Group();
		this.cellar.position.set(2.4, 0, -1.6);
		[[0, 0, 0], [.95, 0, .2], [.45, .55, .1]].forEach(([x, y, rotation]) => this.place('caisse', this.cellar, x, 0, .8, rotation).position.y = y);
		this.scene.add(this.cellar);

		const cellar = new THREE.Box3().setFromObject(this.cellar);
		this.boxes.push(cellar);
		this.obstacles.push(GameBoard.getFootprint(cellar, GameBoard.REACH));

	}

	// L'emprise au sol d'une boite, élargie de `margin`
	static getFootprint(box, margin) {
		return { minX: box.min.x - margin, maxX: box.max.x + margin, minZ: box.min.z - margin, maxZ: box.max.z + margin };
	}

	// Ce qui barre le chemin du joueur : chaque case, cultivée ou non, le bac de terre étant une planche, le carré d'un plateau fermé jusqu'à sa ganivelle,
	// et le décor posé par `buildDecor()`. La grille se calcule à la première marche, après la première image
	getGrid() {

		if(this.grid === null) {

			const rectangles = [...this.obstacles];

			Object.keys(this.tiles).forEach(board => {

				const origin = this.origins[board];

				if(this.data.boards[board].open === false) {
					const half = GameBoard.FENCE + GameBoard.REACH;
					rectangles.push({ minX: origin.x - half, maxX: origin.x + half, minZ: origin.z - half, maxZ: origin.z + half });
					return;
				}

				const half = GameBoard.TILE / 2 + GameBoard.REACH;

				Object.values(this.tiles[board]).forEach(entry => {
					const world = this.getWorld(entry);
					rectangles.push({ minX: world.x - half, maxX: world.x + half, minZ: world.z - half, maxZ: world.z + half });
				});

			});

			const extent = GameBoard.YARD;

			this.grid = new GameBoardPath({ minX: -extent, maxX: extent, minZ: -extent, maxZ: extent }, GameBoard.CELL, rectangles);

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

		this.materials[key] ??= part.vertex ? new THREE.MeshLambertMaterial({ vertexColors: true }) : new THREE.MeshLambertMaterial({ color: '#'+ part.color });

		return this.materials[key];

	}

	place(slug, parent, x, z, scale, rotation) {

		const { THREE } = this.three;

		const group = new THREE.Group();
		this.getModel(slug).parts.forEach(part => group.add(new THREE.Mesh(part.geometry, this.getMaterial(part))));
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

	// La marque du cellier, que la page remplace quand le stock change ; sans elle, le cellier ne se marque pas
	drawCellar() {

		this.cellarMark?.removeFromParent();
		this.cellarMark = this.mark(this.cellar, .45, .2, 'cellar', 1.9, true, null);

		this.cellarMark?.element.addEventListener('click', () => this.emit('cellar', {}));

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
	 * L'emprise de la cour dans le plan de l'image : le décor, les plateaux jusqu'à leur ganivelle et le cellier, en
	 * mètres le long des axes de la vue, `right` vers la droite de l'image et `up` vers son haut. La vue ne tourne
	 * jamais, ses axes se déduisent de VIEW et l'emprise se calcule une fois
	 */
	getExtent() {

		if(this.extent === null) {

			const { THREE } = this.three;

			const forward = new THREE.Vector3(-GameBoard.VIEW.x, -GameBoard.VIEW.y, -GameBoard.VIEW.z).normalize();
			const right = new THREE.Vector3().crossVectors(forward, new THREE.Vector3(0, 1, 0)).normalize();
			const up = new THREE.Vector3().crossVectors(right, forward);

			const extent = { right, up, minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity };
			const corner = new THREE.Vector3();

			this.boxes.forEach(box => {

				for(let index = 0; index < 8; index++) {

					corner.set((index & 1) ? box.max.x : box.min.x, (index & 2) ? box.max.y : box.min.y, (index & 4) ? box.max.z : box.min.z);

					extent.minX = Math.min(extent.minX, corner.dot(right));
					extent.maxX = Math.max(extent.maxX, corner.dot(right));
					extent.minY = Math.min(extent.minY, corner.dot(up));
					extent.maxY = Math.max(extent.maxY, corner.dot(up));

				}

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
		this.camera.position.set(target.x + GameBoard.VIEW.x, GameBoard.VIEW.y, target.z + GameBoard.VIEW.z);
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

		return this.origins[board].clone().addScaledVector(ground, head / 2 / ground.dot(up));

	}

	// Où le joueur attend devant un plateau : sur l'allée de son bord gauche, que le cadrage du plateau garde dans
	// l'image, derrière les cases pour n'en cacher aucune, et hors de ce qui barre le chemin, ganivelle comprise
	getWaiting(board) {

		const origin = this.origins[board];
		const half = (this.data.boards[board].open === false) ? GameBoard.FENCE : GameBoard.SIDE / 2;

		return { x: origin.x - half - GameBoard.REACH - GameBoard.CELL, z: origin.z + 1.5, board: origin };

	}

	getWorld(entry) {
		return { x: entry.x + this.origins[entry.board].x, z: entry.z + this.origins[entry.board].z };
	}

	// Le zoom 1 tient les plateaux et leurs allées dans la largeur ; celui de toute la cour, `zoomCourt`, tient son
	// emprise entière sous ce que l'habillage pose en haut ; celui d'un plateau, `zoomBoard`, tient sa diagonale. Le zoom
	// courant appartient au joueur, et se garde d'une taille de la vue à l'autre
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

		const fit = span => {

			let halfWidth = span / 2, halfHeight = halfWidth / (width / height);
			const needed = span * .62 / 2;

			if(halfHeight < needed) {
				halfHeight = needed;
				halfWidth = halfHeight * width / height;
			}

			return { halfWidth, halfHeight };

		};

		const { halfWidth, halfHeight } = fit((GameBoard.SPREAD + GameBoard.SIDE) * 1.45);

		// Les pixels d'un mètre au zoom 1
		this.pixels = width / (2 * halfWidth);

		this.zoomBoard = halfWidth / fit(GameBoard.SIDE * 1.42 * (width < 600 ? 1.02 : 1.18)).halfWidth;
		const { minX, maxX, minY, maxY } = this.getExtent();
		const visible = (height - this.head) / height;

		this.zoomCourt = Math.min(1, 2 * halfWidth / (maxX - minX + 2 * GameBoard.MARGIN), 2 * halfHeight * visible / (maxY - minY + 2 * GameBoard.MARGIN));
		this.controls.minZoom = this.zoomCourt * ((width < 600) ? 1 : GameBoard.ZOOM_MIN);
		this.controls.maxZoom = this.zoomBoard * GameBoard.ZOOM_MAX;

		this.camera.left = -halfWidth;
		this.camera.right = halfWidth;
		this.camera.top = halfHeight;
		this.camera.bottom = -halfHeight;
		this.camera.updateProjectionMatrix();

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

		let moving = false;

		moving = this.controls.update() || moving;

		// Un glisser ou un zoom s'arrêtent au bord de la cour
		const target = this.controls.target;
		const shift = this.getBounded(target, this.camera.zoom).sub(target);

		if(shift.lengthSq() > 1e-8) {
			target.add(shift);
			this.camera.position.add(shift);
		}

		const marked = (GameBoard.TILE * this.pixels * this.camera.zoom >= GameBoard.MARK_PX);

		if(marked !== this.marked) {
			this.marked = marked;
			this.marks.forEach(object => object.visible = marked);
		}

		if(this.actor !== null) {
			moving = this.actor.update(now / 1000) || moving;
		}

		this.renderer.render(this.scene, this.camera);
		this.labelRenderer.render(this.scene, this.camera);

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
		this.actor = new GameBoardActor(loaded, model, figure, holder, GameBoard.HOME);

		if(this.data.board !== null) {
			this.actor.wait(this.getWaiting(String(this.data.board)));
		}

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
