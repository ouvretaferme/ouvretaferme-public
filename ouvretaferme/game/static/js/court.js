/**
 * La cour d'un joueur, tirée de sa graine (planche jeu-4-decor-5, « Le ruisseau, cases dispersées ») : une ile posée
 * sur le fond, qu'un ruisseau traverse en ondulant, un pont près du puits, et les quatre plateaux dispersés le long des
 * rives, chacun de seize cases posées à la main, tournées et décalées. Une fois sur deux, un plateau est un ilot dans
 * une mare. Autour : l'abri et ses outils, les arbres des rives, la lisière, deux masses (bosquet, verger), une prairie.
 *
 * La graine est fixée par la page (`BoardLib::getScene()`) : la même graine rend la même cour, sur chaque appareil.
 * Rien ici ne dépend de three ni du DOM, pour que la cour se tire aussi bien dans la page, dans un Worker ou sous node
 * (`CourtAi`). `GameBoard` (board.js) la dessine, et marche dans les obstacles qu'elle rend (`getObstacles()`).
 *
 * Ce qui tient chaque tirage :
 * - tout ce qui est posé tient dans un cadre fixe de la vue, LIMIT ;
 * - le joueur atteint son attente, le cellier et l'entrée de chaque plateau, plateaux ouverts puis fermés, sans poche
 *   d'herbe enfermée : une pièce qui enferme est retirée (`settle()`) ;
 * - les modèles du décor restent sous BUDGET triangles, puits compris ; le sol, l'eau et l'ile sont des formes
 *   simples, que board.js dessine.
 */
class GameBoardCourt {

	// Le côté d'une case et le pas d'une case à la suivante, en mètres
	static TILE = 2;
	static STEP = 2.35;

	// La grille de la marche, en cellules de 0,2 m. Les cases et le décor se contournent à REACH mètres, la
	// demi-largeur du joueur avec ses bras : à moins, son corps mord à l'écran sur le bord des cases qu'il longe
	static CELL = .2;
	static REACH = .3;

	// Un modèle ne décrit que l'emprise de toute sa silhouette, couronne comprise : le tronc d'un arbre, seul à barrer
	// le chemin, en est pris comme cette part de la demi-emprise
	static TRUNK = .18;

	// La ganivelle d'un plateau fermé suit ses cases à FENCE_GAP mètres de leur bord
	static FENCE_GAP = .6;

	// Le plafond des modèles du décor, puits compris
	static BUDGET = 4900;

	// Le pont à l'échelle du décor : son tablier, l'axe z du modèle, est le seul passage d'une rive à l'autre
	static BRIDGE_SCALE = .62;

	// Le cours d'eau, tracé en carrés tous les STREAM_STEP mètres, n'en pose aucun à moins de BRIDGE_GAP mètres du
	// pont, pour que ses rives se raccordent au tablier
	static STREAM_STEP = .5;
	static BRIDGE_GAP = 2.1;

	// La marche se calcule sur un carré de BOUND mètres autour du centre, qui contient l'ile entière
	static BOUND = 30;

	// D'où la caméra regarde : de trois quarts, toujours du même côté. Le cadre de la vue, LIMIT, se compte dans son
	// plan, en mètres vers la droite et vers le haut de l'image
	static VIEW = { x: 20, y: 22, z: 20 };
	static LIMIT = { minX: -25, maxX: 25, minY: -16, maxY: 16 };

	// Un arbre dont la couronne mord l'eau, une case ou les caisses du cellier recule de TREE_STEP mètres à la fois, sur
	// TREE_RETREAT mètres au plus, et jamais à moins de TREE_WELL mètres du puits : s'éloigner de l'eau mène vers le
	// milieu de l'ile, où le puits rassemblerait les arbres reculés de toutes les rives. Il garde autour de lui
	// TREE_SPACE fois le rayon de sa couronne : reculés du même côté d'un plateau ou d'une rive, les arbres d'un bouquet
	// se serreraient l'un contre l'autre
	static TREE_STEP = .6;
	static TREE_RETREAT = 8.4;
	static TREE_WELL = 6;
	static TREE_SPACE = .8;

	static TREES = new Set(['arbre', 'arbre.sapin', 'bouleau', 'chene', 'fruitier', 'fruitier.or', 'fruitier.rouge', 'saule', 'arbre.peuplier', 'arbre-mort', 'sureau', 'noix']);

	// Les pièces que `settle()` retire en dernier : sans elles, la cour perd ce qui la fait reconnaitre
	static KEEP = new Set(['abri', 'mare', 'portail', 'chene', 'pont', 'noix']);

	static TERROIRS = {
		bocage: { grass: '#9cbf63', trees: ['chene', 'arbre.sapin', 'bouleau'], fruit: ['fruitier', 'fruitier.rouge'], water: ['bouleau', 'arbre.peuplier'], flowers: ['marguerite', 'bleuet', 'rudbeckia'], bush: 'buisson' },
		nord: { grass: '#95bd63', trees: ['bouleau', 'arbre.sapin', 'arbre.sapin'], fruit: ['fruitier', 'fruitier.or'], water: ['bouleau', 'arbre.peuplier'], flowers: ['campanule', 'rudbeckia', 'marguerite'], bush: 'buisson' },
		humide: { grass: '#8fbd5f', trees: ['arbre.peuplier', 'bouleau', 'arbre.sapin'], fruit: ['fruitier', 'fruitier.rouge'], water: ['bouleau', 'arbre.peuplier'], flowers: ['scabieuse', 'bleuet', 'marguerite'], bush: 'buisson' }
	};

	// Les fleurs du décor sont les moins chères du catalogue, autour de 60 triangles : elles se sèment en nombre, à
	// l'échelle qui les rend lisibles
	static FLOWER_SCALE = { zinnia: 2, rudbeckia: 2, marguerite: 1.9, bleuet: 1.35, campanule: 1.4, scabieuse: 1.3, cresson: 2.6, menthe: 1.7 };

	static COLORS = { highGrass: '#a9c467', beaten: '#d2bc93', wet: '#88b65a', water: '#86b4c8', bridge: '#a07a52' };

	// Les axes de la vue : `RIGHT` vers la droite de l'image, `UP` vers son haut
	static FORWARD = GameBoardCourt.normalize([-GameBoardCourt.VIEW.x, -GameBoardCourt.VIEW.y, -GameBoardCourt.VIEW.z]);
	static RIGHT = GameBoardCourt.normalize(GameBoardCourt.cross(GameBoardCourt.FORWARD, [0, 1, 0]));
	static UP = GameBoardCourt.cross(GameBoardCourt.RIGHT, GameBoardCourt.FORWARD);

	/**
	 * `catalogue` rend, pour un modèle, son emprise `box` ({ min, max }, en mètres à l'échelle 1) et ses `triangles`
	 * (`getCatalogue()`)
	 */
	constructor(seed, catalogue) {

		this.seed = seed;
		this.random = GameBoardCourt.makeRandom(seed * 7919 + 47);
		this.catalogue = catalogue;

		this.parcels = [];
		this.decor = [];
		this.spaces = [];
		this.reserved = [];
		this.layers = [];
		this.patches = [];
		this.rects = [];
		this.stones = [];
		this.boxes = [];
		this.triangles = 0;
		this.island = null;
		this.shed = null;

		this.draw();
		this.removed = this.settle();

	}

	/**
	 * Tire la cour hors du fil principal : sur un téléphone, les graines les plus lentes y gèleraient la page plusieurs
	 * secondes. Sans Worker, ou s'il échoue, la cour se tire sur place : plus lente, mais la même
	 */
	static build(seed, index) {

		const catalogue = GameBoardCourt.getCatalogue(index);
		const local = () => new GameBoardCourt(seed, catalogue);

		if(typeof Worker === 'undefined') {
			return Promise.resolve(local());
		}

		// Chaque échec du Worker retombe sur un tirage sur place. Pour le joueur, ce n'est pas une erreur : la cour
		// s'affiche. La console le garde en avertissement, un Worker qui échoue faisant perdre au fil principal ce qu'il
		// lui épargnait
		const fallback = reason => {
			console.warn('GameBoardCourt : la cour se tire sur place, le Worker a échoué', reason);
			return local();
		};

		const url = URL.createObjectURL(new Blob([GameBoardCourt.getWorkerSource()], { type: 'text/javascript' }));

		let worker;

		// Une page qui refuse les Worker de Blob lève dès la création
		try {
			worker = new Worker(url);
		} catch(error) {
			URL.revokeObjectURL(url);
			return Promise.resolve().then(() => fallback(error));
		}

		return new Promise((resolve, reject) => {

			const finish = make => {

				worker.terminate();
				URL.revokeObjectURL(url);

				try {
					resolve(make());
				} catch(error) {
					reject(error);
				}

			};

			worker.onmessage = ({ data }) => finish(() => GameBoardCourt.revive(data, catalogue));

			// Sans preventDefault(), la console montrerait l'erreur du Worker comme une erreur de la page
			worker.onerror = event => {
				event.preventDefault();
				finish(() => fallback(event.message ?? event));
			};

			worker.onmessageerror = event => finish(() => fallback(event));

			// Un message qui ne se copie pas lève ici, sans que le Worker n'en sache rien
			try {
				worker.postMessage({ seed, models: GameBoardCourt.getModels(index) });
			} catch(error) {
				finish(() => fallback(error));
			}

		});

	}

	/**
	 * Le texte du Worker : les classes déjà chargées, celles du chemin comprises, que la cour emploie pour vérifier
	 * qu'on y marche. Lu dans les classes et non dans un fichier, il reste juste quand les scripts de la page sont
	 * fondus en un seul
	 */
	static getWorkerSource() {
		return [GameBoardPathHeap, GameBoardPath, GameBoardCourt].join('\n')
			+'\nonmessage = ({ data }) => postMessage(GameBoardCourt.export(new GameBoardCourt(data.seed, GameBoardCourt.getCatalogue({ models: data.models }))));';
	}

	// Ce qui revient du Worker : la cour tirée, sans son tirage ni son catalogue, qui sont des fonctions
	static export(court) {

		const { random, catalogue, ...data } = court;

		return data;

	}

	static revive(data, catalogue) {
		return Object.assign(Object.create(GameBoardCourt.prototype), data, { catalogue });
	}

	/**
	 * Les modèles tels que la cour les lit, sa seule lecture de l'index des modèles cuits (`models.json`) : le Worker
	 * les reçoit ainsi, et tire la même cour que le fil principal
	 */
	static getModels(index) {

		const models = {};

		Object.entries(index.models).forEach(([slug, model]) => models[slug] = { box: model.box, triangles: model.triangles });

		return models;

	}

	/**
	 * Le catalogue de la cour, lu dans l'index des modèles cuits (`models.json`)
	 */
	static getCatalogue(index) {

		const models = GameBoardCourt.getModels(index);
		const cache = {};

		return slug => {

			if(cache[slug] === undefined) {
				const [min, max] = models[slug].box;
				cache[slug] = { box: { min: { x: min[0], y: min[1], z: min[2] }, max: { x: max[0], y: max[1], z: max[2] } }, triangles: models[slug].triangles };
			}

			return cache[slug];

		};

	}

	// ---------------------------------------------------------------------------------------------------------------
	// Le tirage

	draw() {

		const random = this.random;
		const terroir = random.weighted([[GameBoardCourt.TERROIRS.humide, 3], [GameBoardCourt.TERROIRS.bocage, 3], [GameBoardCourt.TERROIRS.nord, 2]]);

		// Le gravier du rond du puits
		const gravel = random.pick(['#d6c7a6', '#d9c49a']);

		let stream = null;

		for(let attempt = 0; attempt < 80; attempt++) {

			// Le cours, dans un repère de l'image tourné d'une cour à l'autre : il traverse l'ile en ondulant, plus ou
			// moins penché, plus ou moins haut, et le puits se pose sur l'une ou l'autre rive
			const theta = random.range(-1, 1), cos = Math.cos(theta), sin = Math.sin(theta);
			const toWorld = (p, q) => GameBoardCourt.world(p * cos - q * sin, p * sin + q * cos);
			const toStream = (x, z) => {
				const [p, q] = GameBoardCourt.screenOf(x, z);
				return [p * cos + q * sin, -p * sin + q * cos];
			};
			const wellSide = random.sign();

			// Une fois sur deux, le cours plie doucement
			const bow = random.chance(.5) ? random.sign() * random.range(.012, .028) : 0;
			const q0 = random.range(-6, 6), amp = random.range(1.5, 4.5), freq = random.range(.1, .2), phase = random.range(0, 6.28), tilt = random.range(-.22, .22);
			const qAt = p => q0 + tilt * p + bow * p * p + amp * Math.sin(p * freq + phase);
			const tangent = p => Math.atan(tilt + 2 * bow * p + amp * freq * Math.cos(p * freq + phase));

			const course = [];
			for(let p = -40; p <= 40; p += .5) {
				course.push(toWorld(p, qAt(p)));
			}

			// Le puits sur sa rive, près du milieu
			const wp = random.range(-5, 5), wq = qAt(wp) + wellSide * random.range(5.5, 7.5);
			const [wx, wz] = toWorld(wp, wq);
			const pond = random.chance(.5) ? random.pick([0, 1, 2, 3]) : -1;

			const parcels = [];
			const spots = random.shuffle([[-1, -1], [1, -1], [-1, 1], [1, 1]]).map(([side, bank], i) => ({ side, bank, pond: i === pond }));

			spots.forEach(spot => {

				// Le premier tirage d'un plateau ne sert plus, il reste pour que chaque graine garde sa cour
				random();
				const lean = random.range(-.2, .2), shape = Math.floor(random() * 1e9), extra = random.range(0, 2);

				// La forme d'un plateau ne dépend que de sa graine `shape` : elle se tire une fois, puis se tourne et se
				// porte à chaque place essayée
				const local = GameBoardCourt.getDispersed(GameBoardCourt.makeRandom(shape));
				const kind = spot.pond ? 'ilot' : 'rive';

				// Le plateau cherche sa place le long de la rive, de son côté d'abord, puis s'écarte de l'eau jusqu'à en trouver
				const along = [2, 4, 6, 8, 10, 12, 14].map(d => spot.side * (d + random.range(-.8, .8)));
				const candidates = [...random.shuffle(along.slice(0, 4)), ...along.slice(4), ...along.map(p => -p)];

				for(const p0 of candidates) {

					const t = tangent(p0);
					const angle = Math.PI / 4 - t - theta + lean;
					const [dx, dz] = toWorld(-Math.sin(t) * spot.bank, Math.cos(t) * spot.bank);
					const [x0, z0] = toWorld(p0, qAt(p0));

					const make = (x, z) => GameBoardCourt.makeParcel(local, kind, x, z, angle);
					const clear = parcel => GameBoardCourt.getParcelDistance(parcel, wx, wz) >= 5.2 && parcels.every(other => GameBoardCourt.isApart(other, parcel, 3.4)) && GameBoardCourt.fitsParcel(parcel, 1.2);
					const dry = parcel => course.every(([x, z]) => Math.abs(x - parcel.cx) > 14 || Math.abs(z - parcel.cz) > 14 || GameBoardCourt.getDistanceToRect(parcel.hull, x, z) > 2.2 || GameBoardCourt.getParcelDistance(parcel, x, z) > 2.2);

					let parcel;

					if(spot.pond) {
						parcel = clear(make(x0, z0)) ? make(x0, z0) : null;
					} else {
						parcel = GameBoardCourt.pushOut(make, x0, z0, dx, dz, candidate => clear(candidate) && dry(candidate), extra);
					}

					if(parcel !== null) {
						parcels.push(parcel);
						break;
					}

				}

			});

			if(parcels.length < 4 || GameBoardCourt.isLayoutOk(parcels, wx, wz, 3.4) === false) {
				continue;
			}

			this.parcels = parcels;
			this.wellAt = [wx, wz];
			stream = { qAt, tangent, toWorld, toStream, wellSide };
			break;

		}

		// Faute de place en quatre-vingts tirages, les quatre carrés d'avant, au bord d'un cours droit
		if(this.parcels.length === 0) {
			this.drawFallback();
			stream = { qAt: () => 0, tangent: () => 0, toWorld: GameBoardCourt.world, toStream: GameBoardCourt.screenOf, wellSide: 1 };
		}

		const [wx, wz] = this.wellAt;
		this.placeWell(wx, wz, random.range(0, 6.28), GameBoardCourt.shade(gravel, .03));
		this.parcels.forEach(parcel => parcel.entry = GameBoardCourt.getEntry(parcel, wx, wz));

		const { qAt, tangent, toWorld, toStream, wellSide } = stream;
		const sideOf = (x, z) => {
			const [p, q] = toStream(x, z);
			return Math.sign(q - qAt(p));
		};

		// Le pont, au plus près du puits, loin de la mare de l'ilot, qui couperait l'une de ses rives ; les plateaux de
		// l'autre rive y mènent
		const [wp] = toStream(wx, wz);
		const pondNear = this.parcels.find(parcel => parcel.kind === 'ilot');
		const shift = random.range(-2, 2);
		const bp = [0, 1.5, -1.5, 3, -3, 4.5, -4.5, 6, -6, 8, -8, 10, -10].map(k => wp + shift + k).find(p => {
			const [x, z] = toWorld(p, qAt(p));
			return pondNear === undefined || GameBoardCourt.getParcelDistance(pondNear, x, z) > pondNear.apron + 4.5;
		}) ?? wp + shift;

		const [bx, bz] = toWorld(bp, qAt(bp));
		const t = tangent(bp);

		// Le pont franchit le cours : l'axe x du modèle suit la perpendiculaire au cours
		const [ux, uz] = toWorld(-Math.sin(t), Math.cos(t));
		const bridgeAngle = Math.atan2(-uz, ux);
		const towardWell = Math.sign(ux * (wx - bx) + uz * (wz - bz)) || 1;
		const nearBank = [bx + ux * 3.2 * towardWell, bz + uz * 3.2 * towardWell];
		const farBank = [2 * bx - nearBank[0], 2 * bz - nearBank[1]];

		this.bridge = { x: bx, z: bz, ux, uz };

		this.parcels.forEach(parcel => {

			if(parcel.kind === 'ilot' || sideOf(parcel.cx, parcel.cz) === wellSide) {
				return;
			}

			parcel.entry = GameBoardCourt.getEntry(parcel, farBank[0], farBank[1]);
			parcel.viaBridge = true;

		});

		// Les chemins sont des pas japonais : un ruban de sable entre le puits et des plateaux proches ne ferait que des pointes
		const well = this.well;

		const drawPath = points => {

			points.forEach(([x, z], i) => {
				if((i % 2 === 0 || i === points.length - 1) && Math.hypot(x - well.x, z - well.z) > 3) {
					this.stones.push([x + random.range(-.1, .1), z + random.range(-.1, .1), random.range(.4, .46)]);
				}
			});

			points.forEach(([x, z]) => this.reserve(GameBoardCourt.around(x, z, .8)));

		};

		drawPath(GameBoardCourt.trail(well.x, well.z, nearBank[0], nearBank[1], random.range(-.8, .8)));

		this.parcels.forEach(parcel => {

			this.reserveParcel(parcel);

			// L'ilot se rejoint par la rive du puits ou par celle d'en face, selon son entrée
			if(parcel.kind === 'ilot') {
				const start = (sideOf(parcel.entry.x, parcel.entry.z) === wellSide) ? [well.x, well.z] : farBank;
				drawPath(GameBoardCourt.trail(start[0], start[1], parcel.entry.x, parcel.entry.z, random.range(-1, 1)));
				return;
			}

			const [sx, sz] = parcel.viaBridge ? farBank : [well.x, well.z];

			// Les pas vont jusqu'au bord des cases, au-delà de l'entrée que vise la marche
			const length = Math.hypot(parcel.cx - parcel.entry.x, parcel.cz - parcel.entry.z);

			drawPath([
				...GameBoardCourt.trail(sx, sz, parcel.entry.x, parcel.entry.z, random.range(-1.4, 1.4)),
				[parcel.entry.x + (parcel.cx - parcel.entry.x) / length * .9, parcel.entry.z + (parcel.cz - parcel.entry.z) / length * .9]
			]);

		});

		// Le cours d'eau : un ruban qui ferme la marche, sauf sur le tablier du pont (getBridgeWater())
		const points = [];
		for(let p = -40; p <= 40; p += GameBoardCourt.STREAM_STEP) {
			points.push(toWorld(p, qAt(p)));
		}

		const half = random.range(.85, 1.1);
		this.bridge.half = half;

		points.forEach(([x, z]) => {

			if(Math.hypot(x - bx, z - bz) < GameBoardCourt.BRIDGE_GAP) {
				return;
			}

			this.reserve(GameBoardCourt.around(x, z, half + .2));
			this.rects.push(GameBoardCourt.around(x, z, half - .05));

		});

		this.put('pont', bx, bz, GameBoardCourt.BRIDGE_SCALE, bridgeAngle, { walk: true, force: true, space: 0 });
		this.reserve(GameBoardCourt.around(bx, bz, 2.6));

		const pond = this.parcels.find(parcel => parcel.kind === 'ilot');

		if(pond !== undefined) {
			this.placePond(pond);
		}

		this.makeIsland(terroir, points.filter(([x, z]) => Math.hypot(x, z) < 14));

		// Le cours s'arrête au bord de l'ile
		const wet = points.filter(([x, z]) => this.isOnIsland(x, z, .05));
		this.layers.unshift(['ribbon', wet, half, GameBoardCourt.COLORS.water]);
		this.patches.push(['ribbon', wet, half + .45, GameBoardCourt.COLORS.wet]);

		// Un point de la rive `side`, à `d` mètres du milieu du cours
		const bank = (p, side, d) => {
			const tp = tangent(p), [nx, nz] = toWorld(-Math.sin(tp) * side, Math.cos(tp) * side);
			const [x, z] = toWorld(p, qAt(p));
			return [x + nx * d, z + nz * d];
		};

		// L'abri d'abord : il est le seul décor que la cour ne peut pas perdre. Des pas y mènent quand il est sur la rive
		// du puits ; sur l'autre, le pont et la pelouse suffisent
		if(this.placeShed(terroir) && sideOf(this.shed.x, this.shed.z) === wellSide) {
			drawPath(GameBoardCourt.trail(well.x, well.z, this.shed.x, this.shed.z, random.range(-.8, .8)));
		}

		// Les rives boisées : deux ou trois bouquets d'arbres d'eau, serrés au bord du cours
		const groves = random.range(2, 4) | 0;

		for(let g = 0; g < groves; g++) {
			const p0 = random.range(-18, 18), side = random.sign();
			for(let i = 0; i < 4; i++) {
				this.plantTree(i === 0 ? random.pick(terroir.water) : 'arbre.sapin', ...bank(p0 + random.range(-2.5, 2.5), side, half + random.range(1.5, 3.2)), random.range(.42, .52), { tight: true });
			}
		}

		for(let i = 0; i < 2; i++) {
			this.put('roseaux', ...bank(random.range(-20, 20), random.sign(), half + .55), .42, random.range(0, 6), { tight: true });
		}

		// Une touffe au ras de l'eau se piétine : elle ne gêne pas la marche
		for(let i = 0; i < 7; i++) {
			const slug = random.pick(['cresson', 'menthe']);
			this.put(slug, ...bank(random.range(-22, 22), random.sign(), half + .45), GameBoardCourt.FLOWER_SCALE[slug] * random.range(.8, 1.1), random.range(0, 6), { space: .2, walk: true });
		}

		this.placeLoneTrees(terroir);
		this.placeEdge(terroir, 30);
		this.placeMasses(terroir, ['bosquet', random.pick(['bosquet', 'verger'])]);
		this.placeMeadows(terroir, 1);

	}


	// Faute de place, les quatre carrés d'avant autour du puits, dont l'un ou l'autre légèrement déréglé
	drawFallback() {

		const kinds = this.random.shuffle(['bocage', 'mare', 'clos', 'deregle']);
		const offset = (GameBoardCourt.STEP * 3 + GameBoardCourt.TILE + 7) / 2 + 1;

		this.parcels = [[-1, -1], [1, -1], [-1, 1], [1, 1]].map(([sx, sz], i) => {
			const local = (kinds[i] === 'deregle') ? GameBoardCourt.getUneven(this.random) : GameBoardCourt.getGrid(4, 4, GameBoardCourt.STEP, GameBoardCourt.STEP);
			const parcel = GameBoardCourt.makeParcel(local, kinds[i], sx * offset, sz * offset, 0);
			parcel.kind = 'rive';
			return parcel;
		});

		this.wellAt = [0, 0];

	}

	// ---------------------------------------------------------------------------------------------------------------
	// La pose d'une pièce

	reserve(rectangle) {
		this.reserved.push(rectangle);
	}

	// La place d'un plateau, case par case, sa clôture et sa mare comprises, et son entrée
	reserveParcel(parcel) {
		parcel.tiles.forEach(tile => this.reserve(GameBoardCourt.tileRect(tile, GameBoardCourt.FENCE_GAP + GameBoardCourt.REACH + .4 + parcel.apron)));
		this.reserve(GameBoardCourt.around(parcel.entry.x, parcel.entry.z, 1.1));
	}

	isOnIsland(x, z, margin) {
		return this.island === null || Math.hypot(x, z) < this.getRadiusAt(Math.atan2(z, x)) - margin;
	}

	/**
	 * Pose une pièce du décor si elle tient : dans le budget, dans le cadre, sur l'ile, hors de ce qui est réservé et
	 * de la place d'une autre pièce. `options` : `y` sa hauteur, `walk` une pièce qui ne barre pas la marche, `force` une
	 * pièce posée sans regarder la place, `space` le rayon de sa place, `tight` une place resserrée
	 */
	put(slug, x, z, scale, angle, options = {}) {

		const model = this.catalogue(slug);

		if(this.triangles + model.triangles > GameBoardCourt.BUDGET) {
			return false;
		}

		const y = options.y ?? 0;
		const box = GameBoardCourt.transform(model.box, x, y, z, scale, angle);

		if(GameBoardCourt.fits(box) === false) {
			return false;
		}

		if(this.island !== null && options.force !== true) {

			const margin = GameBoardCourt.TREES.has(slug) ? -.4 : .25;

			if([[box.min.x, box.min.z], [box.min.x, box.max.z], [box.max.x, box.min.z], [box.max.x, box.max.z]].some(([cx, cz]) => this.isOnIsland(cx, cz, margin) === false)) {
				return false;
			}

		}

		if(GameBoardCourt.TREES.has(slug) && options.force !== true && this.getCrownHit(box, x, z, [...this.rects, ...this.getKept()]) !== null) {
			return false;
		}

		const obstacle = options.walk ? null : GameBoardCourt.getObstacleOf(slug, box, x, z);

		if(options.force !== true && obstacle !== null && this.reserved.some(rectangle => GameBoardCourt.overlaps(rectangle, obstacle))) {
			return false;
		}

		const width = box.max.x - box.min.x, depth = box.max.z - box.min.z;
		const radius = options.space ?? (GameBoardCourt.TREES.has(slug) ? Math.min(width, depth) / 2 * (options.tight ? .3 : .55) : Math.max(width, depth) / 2 * (options.tight ? .5 : .8));

		if(radius > 0 && options.force !== true && this.spaces.some(space => Math.hypot(space.x - x, space.z - z) < space.r + radius)) {
			return false;
		}

		if(radius > 0) {
			this.spaces.push({ x, z, r: radius });
		}

		this.decor.push([slug, x, z, scale, angle, y, options.walk === true]);
		this.triangles += model.triangles;

		return true;

	}

	putFirst(slug, places, scale, angle, options = {}) {
		return places.some(([x, z]) => this.put(slug, x, z, scale, angle, options));
	}

	// Un arbre, à l'échelle demandée ou un peu plus petit s'il ne tient pas. L'abri garde ses abords : aucun arbre
	// contre ses murs ni devant sa porte
	plantTree(slug, x, z, scale, options = {}) {

		const nearShed = (x, z) => this.shed !== null && (Math.hypot(x - this.shed.cx, z - this.shed.cz) < 3.4 || Math.hypot(x - this.shed.x, z - this.shed.z) < 2.6);

		if(nearShed(x, z)) {
			return false;
		}

		if([1, .88, .76].some(k => this.put(slug, x, z, scale * k, this.random.range(0, 6.28), options))) {
			return true;
		}

		// Un arbre dont la couronne mord, sous sa pose de face, l'eau, une case ou les caisses recule pas à pas, le long
		// de la direction qui l'en éloigne, jusqu'à trouver sa place. Un arbre que le budget refuse n'a nulle part où
		// reculer
		if(this.triangles + this.catalogue(slug).triangles > GameBoardCourt.BUDGET) {
			return false;
		}

		const box = this.catalogue(slug).box;
		const hit = this.getCrownHit(GameBoardCourt.transform(box, x, 0, z, scale, 0), x, z, [...this.rects, ...this.getKept()]);

		if(hit === null) {
			return false;
		}

		// Il se tourne vers son recul plutôt que de tirer un angle : le tirage décalerait toute la suite de la cour
		const angle = GameBoardCourt.toward(hit.dx, hit.dz);
		const retreat = { ...options, tight: false, space: Math.min(box.max.x - box.min.x, box.max.z - box.min.z) * scale / 2 * GameBoardCourt.TREE_SPACE };

		for(let step = 1; step <= GameBoardCourt.TREE_RETREAT / GameBoardCourt.TREE_STEP; step++) {

			const d = step * GameBoardCourt.TREE_STEP;

			// Droit devant l'eau d'abord, puis en biais de part et d'autre quand une pièce ou un plateau barre le recul
			for(const turn of [0, .6, -.6, 1.2, -1.2, 1.8, -1.8]) {

				const [dx, dz] = GameBoardCourt.rot(hit.dx, hit.dz, turn);
				const tx = x + dx * d, tz = z + dz * d;

				if(Math.hypot(tx - this.well.x, tz - this.well.z) < GameBoardCourt.TREE_WELL || nearShed(tx, tz)) {
					continue;
				}

				if([1, .88, .76].some(k => this.put(slug, tx, tz, scale * k, angle, retreat))) {
					return true;
				}

			}

		}

		return false;

	}

	/**
	 * Un arbre ne barre la marche que par son tronc, que l'eau et les réservations écartent : sa couronne doit l'être
	 * aussi, sinon elle se pose sur le cours, la mare, une case ou les caisses (`rectangles`). Vue de biais, la couronne
	 * couvre dans l'image le sol qui est derrière elle : elle se juge là où la vue la projette au sol depuis sa
	 * mi-hauteur et son sommet. Elle se juge aussi au pied, où l'image ne la montrerait pas toujours dessus : aucune
	 * couronne ne surplombe le cours, la mare ou un plateau, ce qui garde leur bord dégagé des deux côtés. Rend `null`
	 * quand la couronne ne couvre aucun rectangle, sinon la direction au sol qui l'éloigne de celui qu'elle mord
	 */
	getCrownHit(box, x, z, rectangles) {

		const crown = Math.min(box.max.x - box.min.x, box.max.z - box.min.z) / 2;
		const [fx, fy, fz] = GameBoardCourt.FORWARD;

		for(const height of [0, .5, 1]) {

			const along = box.max.y * height / -fy;
			const gx = x + fx * along, gz = z + fz * along;
			const rectangle = rectangles.find(rectangle => GameBoardCourt.getDistanceToRect(rectangle, gx, gz) < crown);

			if(rectangle !== undefined) {

				// Du point du rectangle le plus proche vers la couronne ; depuis son milieu quand la couronne y est
				const nx = Math.min(Math.max(gx, rectangle.minX), rectangle.maxX), nz = Math.min(Math.max(gz, rectangle.minZ), rectangle.maxZ);
				const [dx, dz] = (nx === gx && nz === gz) ? [gx - (rectangle.minX + rectangle.maxX) / 2, gz - (rectangle.minZ + rectangle.maxZ) / 2] : [gx - nx, gz - nz];
				const length = Math.hypot(dx, dz) || 1;

				return { dx: dx / length, dz: dz / length };

			}

		}

		return null;

	}

	// Ce qu'aucune couronne ne couvre, hors de l'eau : les cases des plateaux et les caisses du cellier
	getKept() {
		return [...this.parcels.flatMap(parcel => parcel.tiles.map(tile => GameBoardCourt.tileRect(tile, 0))), this.getCellarObstacle()];
	}

	// Ce qui reste libre autour d'un point : la distance au plus proche plateau, chemin, réservation, pièce ou bord
	getClearance(x, z) {

		let distance = Infinity;

		this.reserved.forEach(rectangle => distance = Math.min(distance, GameBoardCourt.getDistanceToRect(rectangle, x, z)));
		this.spaces.forEach(space => distance = Math.min(distance, Math.hypot(space.x - x, space.z - z) - space.r));

		if(this.island !== null) {
			distance = Math.min(distance, this.getRadiusAt(Math.atan2(z, x)) - Math.hypot(x, z) - .5);
		}

		return distance;

	}

	addBlob(x, z, rx, rz, color, n = 2.4) {
		this.patches.push(['blob', x, z, rx, rz, n, color]);
	}

	// Une tache d'herbe sous les pièces posées depuis `from`, fleurs exceptées
	addCover(from, color, margin) {

		const pieces = this.decor.slice(from).filter(([slug]) => GameBoardCourt.FLOWER_SCALE[slug] === undefined);

		if(pieces.length === 0) {
			return;
		}

		const extent = { minX: Infinity, maxX: -Infinity, minZ: Infinity, maxZ: -Infinity };

		pieces.forEach(([slug, x, z, scale, angle, y]) => {

			const box = GameBoardCourt.transform(this.catalogue(slug).box, x, y, z, scale, angle);
			const k = GameBoardCourt.TREES.has(slug) ? .25 : 0;
			const width = box.max.x - box.min.x, depth = box.max.z - box.min.z;

			extent.minX = Math.min(extent.minX, box.min.x + width * k);
			extent.maxX = Math.max(extent.maxX, box.max.x - width * k);
			extent.minZ = Math.min(extent.minZ, box.min.z + depth * k);
			extent.maxZ = Math.max(extent.maxZ, box.max.z - depth * k);

		});

		this.addBlob((extent.minX + extent.maxX) / 2, (extent.minZ + extent.maxZ) / 2, (extent.maxX - extent.minX) / 2 + margin, (extent.maxZ - extent.minZ) / 2 + margin, color);

	}

	// ---------------------------------------------------------------------------------------------------------------
	// Les lieux

	// Le puits, son rond de gravier, l'attente du joueur à sa gauche et le cellier à sa droite, tournés avec lui
	placeWell(x, z, facing, color) {

		const [hx, hz] = GameBoardCourt.rot(-2.2, 2.6, facing), [cx, cz] = GameBoardCourt.rot(2.4, -1.6, facing);

		this.well = { x, z };
		this.home = { x: x + hx, z: z + hz };
		this.cellar = { x: x + cx, z: z + cz, angle: facing };

		this.put('puits', x, z, .9, facing + .6, { force: true, space: 0 });
		this.layers.push(['disc', x, z, 3.1, color]);
		this.reserve(GameBoardCourt.around(x, z, 3.4));
		this.reserve(GameBoardCourt.around(this.cellar.x + .5, this.cellar.z, 1.6));

	}

	// L'abri : il cherche sa place près du puits, là où la pelouse est la plus libre, porte tournée vers le puits, et
	// range ses outils
	placeShed(terroir) {

		const random = this.random;
		const well = this.well;
		const candidates = [];

		for(let i = 0; i < 96; i++) {
			const a = i / 96 * Math.PI * 2, d = 6 + (i % 6) * 1.4;
			candidates.push([well.x + Math.cos(a) * d, well.z + Math.sin(a) * d]);
		}

		const sorted = candidates.map(([x, z]) => [x, z, this.getClearance(x, z) + random.range(0, .8)]).filter(candidate => candidate[2] > 2.2).sort((a, b) => b[2] - a[2]);

		for(const [x, z] of sorted) {

			const angle = GameBoardCourt.toward(well.x - x, well.z - z);

			if(this.put('abri', x, z, .66, angle) === false) {
				continue;
			}

			const [fx, fz] = GameBoardCourt.rot(0, 1.4, angle);
			this.shed = { x: x + fx * 2, z: z + fz * 2, cx: x, cz: z };
			this.addBlob(x + fx, z + fz, 2.4, 2.4, GameBoardCourt.COLORS.beaten);

			const side = random.sign();
			const at = (lx, lz) => {
				const [ox, oz] = GameBoardCourt.rot(lx, lz, angle);
				return [x + ox, z + oz];
			};

			this.putFirst('cuve', [at(side * 1.75, -.6), at(-side * 1.75, -.6)], .8, 0, { space: .3 });
			this.putFirst('compost', [at(-side * 2.6, .4), at(-side * 2.8, -1.2)], .5, angle + Math.PI / 2, { space: .8 });
			this.putFirst('brouette', [at(side * 1.7, 2.3), at(-side * 1.9, 2.4)], .85, random.range(0, 6.28), { space: .4 });
			this.plantTree(random.pick(terroir.trees), ...at(side * 2.4, -3), .5);

			return true;

		}

		return false;

	}

	// L'ilot : la mare l'entoure, s'étale d'un côté plus que de l'autre et se gonfle par endroits, sans jamais rentrer
	// sous les cases ; une passerelle mène de la rive au bord du plateau
	placePond(parcel) {

		const random = this.random;
		const frame = GameBoardCourt.getFrame(parcel, 0), cx = parcel.cx, cz = parcel.cz;
		const rx = frame.hx + 2.4, rz = frame.hz + 2.4;

		const k0 = random.range(0, 6.28), k1 = random.range(0, 6.28), k2 = random.range(0, 6.28);
		const b0 = random.range(.12, .26), b1 = random.range(.05, .1), b2 = random.range(.02, .05);
		const swell = a => 1 + b0 * (.5 + .5 * Math.sin(a + k0)) + b1 * (.5 + .5 * Math.sin(2 * a + k1)) + b2 * (.5 + .5 * Math.sin(3 * a + k2));

		const entry = parcel.entry, length = Math.hypot(entry.x - cx, entry.z - cz), dx = (entry.x - cx) / length, dz = (entry.z - cz) / length;

		const outline = (grow, n) => Array.from({ length: 64 }, (_, i) => {
			const a = i / 64 * Math.PI * 2, c = Math.cos(a), si = Math.sin(a);
			return frame.toWorld(((rx * swell(a)) + grow) * Math.sign(c) * Math.abs(c) ** (2 / n), ((rz * swell(a)) + grow) * Math.sign(si) * Math.abs(si) ** (2 / n));
		});

		this.patches.push(['poly', outline(.5, 2.5), GameBoardCourt.COLORS.wet]);
		this.layers.push(['poly', outline(0, 2.5), GameBoardCourt.COLORS.water]);

		// La terre de l'ilot, aux coins ronds, au ras de l'eau
		this.layers.push(['poly', Array.from({ length: 48 }, (_, i) => {
			const a = i / 48 * Math.PI * 2, c = Math.cos(a), si = Math.sin(a);
			return frame.toWorld((frame.hx + .7) * Math.sign(c) * Math.abs(c) ** (2 / 3.2), (frame.hz + .7) * Math.sign(si) * Math.abs(si) ** (2 / 3.2));
		}), GameBoardCourt.COLORS.wet]);

		const support = frame.support(dx, dz), walkway = 2.9, middle = support + .1 + walkway / 2;
		this.boxes.push({ x: cx + dx * middle, z: cz + dz * middle, y: 0, w: walkway, h: .1, d: 1.3, angle: Math.atan2(-dz, dx), color: GameBoardCourt.COLORS.bridge });

		// L'eau ferme la marche sur toute la mare, cases comprises, sauf la passerelle ; elle se découpe en bandes d'une cellule
		const cell = GameBoardCourt.CELL;
		const outer = GameBoardCourt.grow(parcel.hull, 2.6 + Math.max(rx, rz) * .7);

		for(let z = outer.minZ; z < outer.maxZ; z += cell) {

			let run = null;

			const flush = x => {
				if(run !== null) {
					this.rects.push(GameBoardCourt.rect(run, z, x, z + cell));
					run = null;
				}
			};

			for(let x = outer.minX; x < outer.maxX; x += cell) {

				const mx = x + cell / 2, mz = z + cell / 2;
				const [lx, lz] = frame.toLocal(mx, mz);
				const along = (mx - cx) * dx + (mz - cz) * dz, across = Math.abs(-(mx - cx) * dz + (mz - cz) * dx);

				// Le bord de la mare sous l'angle du point, gonflé comme la forme dessinée
				const a = Math.atan2(Math.sign(lz) * Math.abs(lz / rz) ** 1.25, Math.sign(lx) * Math.abs(lx / rx) ** 1.25), g = swell(a);
				const water = Math.abs(lx / (rx * g)) ** 2.5 + Math.abs(lz / (rz * g)) ** 2.5 <= 1.08 && (along > 0 && across < 1) === false;

				if(water) {
					run ??= x;
				} else {
					flush(x);
				}

			}

			flush(outer.maxX);

		}

		const side = random.sign();
		this.put('roseaux', ...frame.toWorld(side * (rx - .6), -(rz - .9)), .5, random.range(0, 6.28), { force: true, space: 0 });
		this.put('roseaux', ...frame.toWorld(-side * (rx - .5), rz * .2), .45, random.range(0, 6.28), { force: true, space: 0 });
		this.plantTree(random.pick(['bouleau', 'arbre.peuplier']), ...frame.toWorld(side * (rx + .9), -(rz + .9)), .5);

	}

	// Les masses, posées là où le vide est le plus grand, pour que le décor se groupe
	placeMasses(terroir, kinds) {

		const random = this.random;
		const points = [];

		for(let x = -26; x <= 26; x += 1.5) {
			for(let z = -26; z <= 26; z += 1.5) {
				if(this.isOnIsland(x, z, 1.5) && GameBoardCourt.fits({ min: { x: x - 1, y: 0, z: z - 1 }, max: { x: x + 1, y: 3, z: z + 1 } })) {
					points.push([x, z]);
				}
			}
		}

		kinds.forEach(kind => {

			const scored = points.map(([x, z]) => [x, z, this.getClearance(x, z)]).filter(point => point[2] > 2.2).sort((a, b) => b[2] - a[2]);

			if(scored.length === 0) {
				return;
			}

			const [x, z, room] = scored[Math.floor(random() * Math.min(4, scored.length))];

			if(kind === 'verger') {
				this.placeOrchard(x, z, Math.min(room, 5), terroir);
			} else {
				this.placeGrove(x, z, Math.min(room, 5), terroir);
			}

		});

	}

	// Le verger : quatre fruitiers en carré, sur une herbe haute
	placeOrchard(x, z, room, terroir) {

		const random = this.random;
		const from = this.decor.length, slug = random.pick(terroir.fruit), a = random.range(0, 6.28);

		[[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([i, k]) => {
			const [ox, oz] = GameBoardCourt.rot(i * room * .4, k * room * .4, a);
			this.plantTree(slug, x + ox, z + oz, .46, { tight: true });
		});

		this.addCover(from, GameBoardCourt.COLORS.highGrass, .9);

	}

	// Le bosquet : cinq arbres serrés et un buisson
	placeGrove(x, z, room, terroir) {

		const random = this.random;
		const from = this.decor.length, slug = random.pick(terroir.trees);

		for(let i = 0; i < 5; i++) {
			const a = i * 2.4 + random.range(0, 1), d = (i === 0) ? 0 : room * random.range(.3, .55);
			this.plantTree(slug, x + Math.cos(a) * d, z + Math.sin(a) * d, .5, { tight: true });
		}

		this.put(terroir.bush, x + room * .6, z - room * .3, .6, 0, { tight: true });
		this.addCover(from, GameBoardCourt.shade(terroir.grass, -.04), .6);

	}

	// Les prairies : de l'herbe haute semée de fleurs, là où le vide reste le plus grand une fois tout posé
	placeMeadows(terroir, count) {

		const random = this.random;
		const points = [];

		for(let x = -26; x <= 26; x += 1.5) {
			for(let z = -26; z <= 26; z += 1.5) {
				if(this.isOnIsland(x, z, 1.8)) {
					points.push([x, z]);
				}
			}
		}

		for(let i = 0; i < count; i++) {

			const scored = points.map(([x, z]) => [x, z, this.getClearance(x, z)]).filter(point => point[2] > 1.6).sort((a, b) => b[2] - a[2]);

			if(scored.length === 0) {
				return;
			}

			const [x, z, room] = scored[Math.floor(random() * Math.min(3, scored.length))];
			const r = Math.min(room, 3.2), flower = random.pick(terroir.flowers), a = random.range(0, 6.28);

			let sown = 0;

			for(let k = 0; k < 5; k++) {
				const b = a + k * 2.4, d = r * random.range(.15, .6);
				sown += this.put(flower, x + Math.cos(b) * d, z + Math.sin(b) * d, GameBoardCourt.FLOWER_SCALE[flower] * random.range(.8, 1.1), random.range(0, 6), { space: .25 }) ? 1 : 0;
			}

			// Une herbe haute sans fleurs se lirait comme une tache : elle ne reste qu'avec ses fleurs
			if(sown >= 3) {
				this.patches.push(['blob', x, z, r * random.range(.9, 1.2), r * random.range(.7, 1), 2.2, GameBoardCourt.COLORS.highGrass]);
			}

			this.spaces.push({ x, z, r: r * .8 });

		}

	}

	// La lisière fait le tour de l'ile, sauf devant où elle cacherait les plateaux, doublée au fond d'un second rang
	placeEdge(terroir, count) {

		const random = this.random;

		for(let i = 0; i < count; i++) {

			const a = i / count * Math.PI * 2 + random.range(-.04, .04);
			const edge = this.getRadiusAt(a);

			[1.2, 3.1].forEach((inset, rank) => {

				const r = edge - inset - random.range(0, .5);
				const x = Math.cos(a) * r, z = Math.sin(a) * r;
				const [, q] = GameBoardCourt.screenOf(x, z);

				if(q > (rank === 0 ? 3 : -6) || (rank === 1 && random.chance(.45))) {
					return;
				}

				const slug = random.chance(.7) ? 'arbre.sapin' : random.pick(terroir.trees);
				this.plantTree(slug, x, z, random.range(.42, .55), { tight: true });

			});

		}

	}

	// Cinq ou six arbres isolés au milieu de la cour, là où la pelouse est la plus vide, à 1,6 m au moins de ce que la
	// cour réserve et à 4 m l'un de l'autre : on va de l'un à l'autre. Posés avant la lisière, ils prennent sur elle
	// la part du budget, au fond où ses arbres se cachent l'un l'autre
	placeLoneTrees(terroir) {

		const random = this.random;
		const points = [];

		for(let x = -14; x <= 14; x += 1.5) {
			for(let z = -14; z <= 14; z += 1.5) {
				if(this.isOnIsland(x, z, 4)) {
					points.push([x, z, this.getClearance(x, z) + random.range(0, .6)]);
				}
			}
		}

		points.sort((a, b) => b[2] - a[2]);

		const placed = [];
		const wanted = random.range(5, 7) | 0;

		for(const [x, z, room] of points) {

			if(placed.length >= wanted || room < 1.6) {
				break;
			}

			if(placed.some(([px, pz]) => Math.hypot(px - x, pz - z) < 4)) {
				continue;
			}

			if(this.plantTree(random.pick(['chene', ...terroir.trees]), x, z, random.range(.5, .6))) {
				placed.push([x, z]);
			}

		}

	}

	/**
	 * L'ile : son contour enveloppe les plateaux, leurs entrées, le puits et le milieu du cours, avec une marge, et
	 * ondule. Il ne sort pas du cadre, tranche comprise. `getRadiusAt(angle)` rend son rayon dans chaque direction
	 */
	makeIsland(terroir, extra) {

		const random = this.random;
		const points = [];

		this.parcels.forEach(parcel => {
			const hull = GameBoardCourt.grow(parcel.hull, 2.6 + parcel.apron);
			points.push([hull.minX, hull.minZ], [hull.maxX, hull.minZ], [hull.minX, hull.maxZ], [hull.maxX, hull.maxZ]);
			points.push([parcel.entry.x, parcel.entry.z]);
		});

		points.push([this.well.x - 5, this.well.z], [this.well.x + 5, this.well.z], [this.well.x, this.well.z - 5], [this.well.x, this.well.z + 5]);
		points.push(...extra);

		const ripple = [random.range(0, 6.28), random.range(0, 6.28)];

		const raw = Array.from({ length: 144 }, (_, i) => {
			const a = i / 144 * Math.PI * 2, c = Math.cos(a), s = Math.sin(a);
			return Math.max(...points.map(([x, z]) => x * c + z * s)) + 1.2;
		});

		const smooth = raw.map((r, i) => {
			let sum = 0;
			for(let k = -6; k <= 6; k++) {
				sum += raw[(i + k + 144) % 144];
			}
			return Math.max(r, sum / 13);
		});

		const depth = random.range(1.4, 1.8);
		const limit = GameBoardCourt.LIMIT;

		const bounded = smooth.map((r, i) => {

			const a = i / 144 * Math.PI * 2;
			let ok = r;

			while(ok > 4) {

				const x = Math.cos(a) * ok, z = Math.sin(a) * ok;
				const [px, py] = GameBoardCourt.project(x, 0, z), [, pyLow] = GameBoardCourt.project(x, -depth, z);

				if(px > limit.minX + .2 && px < limit.maxX - .2 && py < limit.maxY - .2 && pyLow > limit.minY + .2) {
					break;
				}

				ok -= .1;

			}

			return ok;

		});

		const rock = random.weighted([[{ earth: '#9a6a44', deep: '#6e4a31' }, 3], [{ earth: '#c4ab84', deep: '#9c8463' }, 2], [{ earth: '#a8634a', deep: '#7a4535' }, 1]]);

		this.island = { radii: bounded, ripple, depth, top: terroir.grass, lip: GameBoardCourt.shade(terroir.grass, -.12), ...rock };

	}

	// Le rayon de l'ile dans la direction `angle`. L'ile garde ses rayons et non une fonction : la cour tirée dans un
	// Worker (`GameBoardCourt.build()`) revient par copie, qui ne transporte pas de fonction
	getRadiusAt(angle) {

		const { radii, ripple } = this.island;

		const t = ((angle % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2) / (Math.PI * 2) * 144, i = Math.floor(t) % 144, f = t - Math.floor(t);
		const base = radii[i] * (1 - f) + radii[(i + 1) % 144] * f;

		return base + .22 * Math.sin(angle * 5 + ripple[0]) + .14 * Math.sin(angle * 9 + ripple[1]) - .36;

	}

	// ---------------------------------------------------------------------------------------------------------------
	// La marche

	/**
	 * Ce qui barre la marche, en rectangles pour `GameBoardPath` : l'eau, le décor, le cellier, le bord de l'ile, et
	 * chaque plateau, fermé (`closed[board]`) jusqu'à sa ganivelle
	 */
	getObstacles(closed) {

		const rectangles = [...this.rects];

		this.decor.forEach(([slug, x, z, scale, angle, y, walk]) => {
			if(walk === false) {
				rectangles.push(GameBoardCourt.getObstacleOf(slug, GameBoardCourt.transform(this.catalogue(slug).box, x, y, z, scale, angle), x, z));
			}
		});

		rectangles.push(this.getCellarObstacle());
		rectangles.push(...this.getBridgeWater());
		rectangles.push(...this.getRim());

		this.parcels.forEach((parcel, index) => rectangles.push(...this.getFence(parcel, closed[index + 1] === true)));

		return rectangles;

	}

	/**
	 * Un plateau se contourne, il ne se traverse pas : l'obstacle est l'enveloppe convexe de ses cases,
	 * élargie de REACH quand il est ouvert, de la clôture et de REACH quand il est fermé, découpée en bandes d'une
	 * cellule. Le passage entre deux cases se regarde, il ne se parcourt pas
	 */
	getFence(parcel, closed) {

		const key = closed ? 'closed' : 'open';

		parcel.fences ??= {};

		if(parcel.fences[key] !== undefined) {
			return parcel.fences[key];
		}

		const hull = GameBoardCourt.getOutline(parcel);
		const d = (closed ? GameBoardCourt.FENCE_GAP : 0) + GameBoardCourt.REACH;
		const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);

		const inside = (x, z) => {

			let distance = Infinity, all = true;

			for(let i = 0; i < hull.length; i++) {

				const a = hull[i], b = hull[(i + 1) % hull.length];

				if(cross(a, b, [x, z]) < 0) {
					all = false;
				}

				const vx = b[0] - a[0], vz = b[1] - a[1], t = Math.max(0, Math.min(1, ((x - a[0]) * vx + (z - a[1]) * vz) / (vx * vx + vz * vz)));
				distance = Math.min(distance, Math.hypot(a[0] + vx * t - x, a[1] + vz * t - z));

			}

			return all || distance <= d;

		};

		const cell = GameBoardCourt.CELL;
		const area = GameBoardCourt.grow(parcel.hull, d + .2);
		const fence = [];

		for(let z = area.minZ; z < area.maxZ; z += cell) {

			let x0 = null, x1 = null;

			for(let x = area.minX; x <= area.maxX; x += .1) {
				if(inside(x, z + cell / 2)) {
					x0 ??= x;
					x1 = x;
				}
			}

			if(x0 !== null) {
				fence.push({ minX: x0, maxX: x1 + .1, minZ: z, maxZ: z + cell });
			}

		}

		parcel.fences[key] = fence;

		return fence;

	}

	/**
	 * L'eau de part et d'autre du tablier. Le cours n'a plus de carré à moins de BRIDGE_GAP mètres du pont, et cette
	 * ouverture est plus large que le tablier : sans ces carreaux, le chemin le plus court passe à côté du pont, dans
	 * l'eau. Ils couvrent l'ouverture jusqu'au premier carré du cours, un STREAM_STEP plus loin, et partent du
	 * garde-corps moins REACH : grandis de REACH comme tout obstacle, ils laissent au joueur le milieu du tablier. Ils ne
	 * servent qu'à la marche : le décor ne les voit pas, et la cour reste celle de sa graine
	 */
	getBridgeWater() {

		const { x, z, ux, uz, half } = this.bridge;
		const box = this.catalogue('pont').box;
		const deck = (box.max.z - box.min.z) / 2 * GameBoardCourt.BRIDGE_SCALE - GameBoardCourt.REACH;
		const step = GameBoardCourt.CELL;
		const water = [];

		for(let along = deck + step / 2; along < GameBoardCourt.BRIDGE_GAP + GameBoardCourt.STREAM_STEP; along += step) {
			for(let across = -half + .05; across <= half - .05; across += step) {
				for(const side of [1, -1]) {
					water.push(GameBoardCourt.around(x + ux * across - uz * along * side, z + uz * across + ux * along * side, step * .75));
				}
			}
		}

		return water;

	}

	// Le bord de l'ile, en bandes de 0,5 m, à 0,6 m du vide
	getRim() {

		if(this.rim !== undefined) {
			return this.rim;
		}

		const bound = GameBoardCourt.BOUND;
		const on = (x, z) => Math.hypot(x, z) < this.getRadiusAt(Math.atan2(z, x)) - .6;

		this.rim = [];

		for(let z = -bound; z < bound; z += .5) {

			let left = -Infinity, right = Infinity;

			for(let zz = z; zz <= z + .5001; zz += .1) {

				let x = 0;

				while(x < bound + 2 && on(x, zz)) {
					x += .05;
				}

				right = Math.min(right, x);
				x = 0;

				while(x > -bound - 2 && on(x, zz)) {
					x -= .05;
				}

				left = Math.max(left, x);

			}

			this.rim.push({ minX: -bound - 1, maxX: left + .01, minZ: z - .01, maxZ: z + .51 });
			this.rim.push({ minX: right - .01, maxX: bound + 1, minZ: z - .01, maxZ: z + .51 });

		}

		return this.rim;

	}

	getPath(closed) {
		const bound = GameBoardCourt.BOUND;
		return new GameBoardPath({ minX: -bound, maxX: bound, minZ: -bound, maxZ: bound }, GameBoardCourt.CELL, this.getObstacles(closed));
	}

	/**
	 * Ce que la marche laisse d'un seul tenant : la plus grande zone libre (`main`, en m²), les poches d'herbe d'au
	 * moins 0,5 m² qui n'y tiennent pas, et les lieux qu'on n'atteint pas à 1,2 m près
	 */
	analyse(closed) {

		const cell = GameBoardCourt.CELL;
		const grid = this.getPath(closed);
		const sizes = {};

		grid.zones.forEach(zone => {
			if(zone >= 0) {
				sizes[zone] = (sizes[zone] ?? 0) + 1;
			}
		});

		const main = Number(Object.keys(sizes).sort((a, b) => sizes[b] - sizes[a])[0]);

		const pockets = Object.keys(sizes).map(Number).filter(zone => zone !== main && sizes[zone] * cell * cell >= .5).map(zone => {

			let sx = 0, sz = 0;
			const cells = [];

			grid.zones.forEach((value, index) => {
				if(value === zone) {
					const center = grid.getCenter(index);
					sx += center.x;
					sz += center.z;
					cells.push(center);
				}
			});

			// Arrondis au décimètre comme la planche les lisait : `settle()` mesure depuis ce centre, et un centre plus
			// fin y retirerait parfois une autre pièce
			return { area: Number((sizes[zone] * cell * cell).toFixed(2)), x: Number((sx / sizes[zone]).toFixed(1)), z: Number((sz / sizes[zone]).toFixed(1)), cells };

		});

		const targets = this.getTargets();

		const unreached = Object.keys(targets).filter(name => {

			const point = targets[name];

			if(grid.zones[grid.getCell(point)] === main) {
				return false;
			}

			const near = grid.getNearest(point, point, main);
			return near === null || Math.hypot(near.x - point.x, near.z - point.z) > 1.2;

		});

		return { main: sizes[main] * cell * cell, pockets, unreached };

	}

	// Les lieux que le joueur doit atteindre : son attente, le cellier, l'entrée de chaque plateau
	getTargets() {

		const targets = {
			home: this.home,
			cellar: this.getCellarFront()
		};

		this.parcels.forEach((parcel, index) => targets['board-'+ (index + 1)] = parcel.entry);

		return targets;

	}

	// Les caisses du cellier s'empilent sur un mètre de long, tournées avec le puits (`placeWell()`) : leur obstacle
	// tourne avec elles
	getCellarObstacle() {
		const [x, z] = GameBoardCourt.rot(.5, 0, this.cellar.angle);
		return GameBoardCourt.around(this.cellar.x + x, this.cellar.z + z, 1.05);
	}

	// Le point devant le cellier que le joueur doit pouvoir atteindre
	getCellarFront() {
		const [x, z] = GameBoardCourt.rot(.5, 1.05 + GameBoardCourt.CELL, this.cellar.angle);
		return { x: this.cellar.x + x, z: this.cellar.z + z };
	}

	/**
	 * La cour réparée, plateaux tous ouverts puis tous fermés : une poche ou un lieu hors d'atteinte retire la pièce
	 * la plus proche, celles de KEEP en dernier, dix fois au plus. Une langue de terre que la mare de l'ilot et le
	 * ruisseau enferment passe sous l'eau : retirer une pièce n'y changerait rien
	 */
	settle() {

		const removed = [];
		const opened = {}, closed = { 1: true, 2: true, 3: true, 4: true };

		for(let round = 0; round < 10; round++) {

			const a = this.analyse(opened), b = this.analyse(closed);
			const targets = this.getTargets();

			const problems = [
				...a.pockets,
				...b.pockets,
				...[...a.unreached, ...b.unreached].map(name => ({ x: targets[name].x, z: targets[name].z }))
			];

			if(problems.length === 0) {
				break;
			}

			const problem = problems[0];
			const pond = this.parcels.find(parcel => parcel.kind === 'ilot');

			if(problem.cells !== undefined && problem.area < 12 && pond !== undefined && GameBoardCourt.getParcelDistance(pond, problem.x, problem.z) < 7) {
				this.flood(problem.cells);
				removed.push('langue de terre');
				continue;
			}

			let best = -1, distance = Infinity;

			this.decor.forEach(([slug, x, z, scale, angle, y, walk], index) => {

				if(slug === 'puits' || walk) {
					return;
				}

				const obstacle = GameBoardCourt.getObstacleOf(slug, GameBoardCourt.transform(this.catalogue(slug).box, x, y, z, scale, angle), x, z);
				const d = GameBoardCourt.getDistanceToRect(obstacle, problem.x, problem.z) + (GameBoardCourt.KEEP.has(slug) ? 3 : 0);

				if(d < distance) {
					distance = d;
					best = index;
				}

			});

			if(best < 0 || distance > 8) {
				break;
			}

			removed.push(this.decor[best][0]);
			this.triangles -= this.catalogue(this.decor[best][0]).triangles;
			this.decor.splice(best, 1);

		}

		return removed;

	}

	// Une poche passe sous l'eau, une bande par rang de cellules contiguës
	flood(cells) {

		const cell = GameBoardCourt.CELL;
		const rows = new Map();

		cells.forEach(center => rows.set(center.z, [...(rows.get(center.z) ?? []), center.x]));

		rows.forEach((xs, z) => {

			xs.sort((a, b) => a - b);
			let start = xs[0];

			xs.forEach((x, i) => {
				if(i === xs.length - 1 || xs[i + 1] - x > cell * 1.5) {
					const rectangle = GameBoardCourt.rect(start - cell / 2, z - cell / 2, x + cell / 2, z + cell / 2);
					this.rects.push(rectangle);
					this.layers.push(['rect', rectangle.minX, rectangle.minZ, rectangle.maxX, rectangle.maxZ, GameBoardCourt.COLORS.water]);
					start = xs[i + 1];
				}
			});

		});

	}

	// ---------------------------------------------------------------------------------------------------------------
	// Les plateaux

	// Les cases dispersées : des planches posées à la main, chacune tournée et décalée, les écarts inégaux d'une
	// colonne et d'un rang à l'autre. Une case qui en toucherait une autre se retire et se repose plus sagement
	static getDispersed(random) {

		const spread = n => {
			const gaps = Array.from({ length: n - 1 }, () => random.range(2.45, 3));
			const at = [0];
			gaps.forEach(gap => at.push(at[at.length - 1] + gap));
			return at.map(value => value - at[n - 1] / 2);
		};

		const xs = spread(4), zs = spread(4), shear = random.range(-.25, .25), tiles = [];

		for(let row = 0; row < 4; row++) {

			const lean = random.range(-.35, .35);

			for(let column = 0; column < 4; column++) {

				let tile = null;

				for(let attempt = 0; attempt < 24 && tile === null; attempt++) {

					const k = 1 - attempt / 24;
					const candidate = { x: xs[column] + zs[row] * shear * .2 + lean + random.range(-.3, .3) * k, z: zs[row] + random.range(-.3, .3) * k, a: random.range(-.4, .4) * k };

					if(tiles.every(other => GameBoardCourt.getSquareGap(other, candidate) > .14)) {
						tile = candidate;
					}

				}

				tiles.push(tile ?? { x: xs[column], z: zs[row], a: 0 });

			}

		}

		return tiles;

	}

	// Le carré d'avant, légèrement déréglé
	static getUneven(random) {
		return GameBoardCourt.getGrid(4, 4, 2.45, 2.45).map(tile => ({ x: tile.x + random.range(-.1, .1), z: tile.z + random.range(-.1, .1), a: random.range(-.13, .13) }));
	}

	static getGrid(columns, rows, dx, dz) {

		const tiles = [];

		for(let row = 0; row < rows; row++) {
			for(let column = 0; column < columns; column++) {
				tiles.push({ x: (column - (columns - 1) / 2) * dx, z: (row - (rows - 1) / 2) * dz, a: 0 });
			}
		}

		return tiles;

	}

	/**
	 * Un plateau posé : sa forme locale tournée de `angle` et portée en (cx, cz), avec son enveloppe dans les axes de
	 * la cour (`hull`). La mare d'un ilot l'élargit de `apron` : l'eau compte dans
	 * la place qu'il prend. Son enveloppe convexe se calcule à la demande (`getOutline()`) : la poussée essaie des
	 * dizaines de places pour en garder une
	 */
	static makeParcel(local, kind, cx, cz, angle) {

		const tiles = local.map(tile => {
			const [x, z] = GameBoardCourt.rot(tile.x, tile.z, angle);
			return { x: cx + x, z: cz + z, a: tile.a + angle };
		});

		const hull = tiles.reduce((extent, tile) => {
			const rectangle = GameBoardCourt.tileRect(tile, 0);
			return { minX: Math.min(extent.minX, rectangle.minX), maxX: Math.max(extent.maxX, rectangle.maxX), minZ: Math.min(extent.minZ, rectangle.minZ), maxZ: Math.max(extent.maxZ, rectangle.maxZ) };
		}, { minX: Infinity, maxX: -Infinity, minZ: Infinity, maxZ: -Infinity });

		const radius = Math.max(...tiles.map(tile => Math.hypot(tile.x - cx, tile.z - cz)));

		return { kind, tiles, cx, cz, angle, hull, radius, apron: (kind === 'mare' || kind === 'ilot') ? 2.1 : 0 };

	}

	// L'enveloppe convexe des coins des cases, que la ganivelle suit
	static getOutline(parcel) {

		if(parcel.outline !== undefined) {
			return parcel.outline;
		}

		const half = GameBoardCourt.TILE / 2;

		const points = parcel.tiles.flatMap(tile => [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([i, k]) => {
			const [x, z] = GameBoardCourt.rot(i * half, k * half, tile.a);
			return [tile.x + x, tile.z + z];
		})).sort((a, b) => a[0] - b[0] || a[1] - b[1]);

		const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);
		const lower = [], upper = [];

		points.forEach(point => {
			while(lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], point) <= 0) {
				lower.pop();
			}
			lower.push(point);
		});

		[...points].reverse().forEach(point => {
			while(upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], point) <= 0) {
				upper.pop();
			}
			upper.push(point);
		});

		parcel.outline = [...lower.slice(0, -1), ...upper.slice(0, -1)];

		return parcel.outline;

	}

	// L'entrée d'un plateau : le point d'attente du joueur, du côté de (tx, tz), au-delà de la clôture d'un plateau fermé
	static getEntry(parcel, tx, tz) {

		const length = Math.hypot(tx - parcel.cx, tz - parcel.cz) || 1, dx = (tx - parcel.cx) / length, dz = (tz - parcel.cz) / length;
		const support = Math.max(...parcel.tiles.map(tile => (tile.x - parcel.cx) * dx + (tile.z - parcel.cz) * dz + GameBoardCourt.tileHalf(tile.a)));
		const d = support + GameBoardCourt.FENCE_GAP + GameBoardCourt.REACH + GameBoardCourt.CELL + .45;

		return { x: parcel.cx + dx * d, z: parcel.cz + dz * d };

	}

	// La distance d'un point à un plateau, case par case : son enveloppe exagère un plateau tourné de 45°
	static getParcelDistance(parcel, x, z) {
		return Math.min(...parcel.tiles.map(tile => GameBoardCourt.getDistanceToRect(GameBoardCourt.tileRect(tile, 0), x, z)));
	}

	/**
	 * Entre deux cases, la distance se mesure dans le repère de la première : une case tournée de 45° n'est pas son
	 * enveloppe dans les axes de la cour, qui la grossirait de 40 %
	 */
	static getParcelGap(a, b) {

		let distance = Infinity;

		a.tiles.forEach(tile => b.tiles.forEach(other => distance = Math.min(distance, GameBoardCourt.getTileGap(tile, other))));

		return distance - a.apron - b.apron;

	}

	static getTileGap(tile, other) {
		const [lx, lz] = GameBoardCourt.rot(other.x - tile.x, other.z - tile.z, -tile.a), half = GameBoardCourt.TILE / 2 + GameBoardCourt.tileHalf(other.a - tile.a);
		return Math.hypot(Math.max(Math.abs(lx) - half, 0), Math.max(Math.abs(lz) - half, 0));
	}

	/**
	 * Deux plateaux sont-ils à `gap` mètres au moins l'un de l'autre, au sens de `getParcelGap()` ? La poussée des
	 * plateaux pose la question des dizaines de milliers de fois par cour, et la paire de cases aux centres les plus
	 * proches y répond presque toujours seule : sa distance est un majorant de l'écart, et celle de leurs centres,
	 * divisée par √2, moins la plus grande demi-emprise d'une case (1 + √2), un minorant. La réponse est la même que
	 * celle du calcul complet. Deux plateaux loin l'un de l'autre se départagent avant, par le cercle de leurs centres
	 */
	static isApart(a, b, gap) {

		const reach = 1 + Math.SQRT2 + 1e-6;
		const centers = Math.hypot(a.cx - b.cx, a.cz - b.cz) - a.radius - b.radius - 1e-6;

		if(centers > 0 && centers * Math.SQRT1_2 - reach - a.apron - b.apron >= gap) {
			return true;
		}

		let closest = null, nearest = Infinity;

		a.tiles.forEach(tile => b.tiles.forEach(other => {
			const d = (other.x - tile.x) ** 2 + (other.z - tile.z) ** 2;
			if(d < nearest) {
				nearest = d;
				closest = [tile, other];
			}
		}));

		if(GameBoardCourt.getTileGap(...closest) - a.apron - b.apron < gap) {
			return false;
		}

		if(Math.sqrt(nearest) * Math.SQRT1_2 - reach - a.apron - b.apron >= gap) {
			return true;
		}

		return GameBoardCourt.getParcelGap(a, b) >= gap;

	}

	// L'écart entre deux cases tournées : la plus grande séparation sur les quatre axes de leurs côtés, négative si
	// elles se recouvrent
	static getSquareGap(a, b) {

		const half = GameBoardCourt.TILE / 2;
		const corners = tile => [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([i, k]) => {
			const [x, z] = GameBoardCourt.rot(i * half, k * half, tile.a);
			return [tile.x + x, tile.z + z];
		});

		const ca = corners(a), cb = corners(b);
		let gap = -Infinity;

		[GameBoardCourt.rot(1, 0, a.a), GameBoardCourt.rot(0, 1, a.a), GameBoardCourt.rot(1, 0, b.a), GameBoardCourt.rot(0, 1, b.a)].forEach(([ux, uz]) => {
			const pa = ca.map(([x, z]) => x * ux + z * uz), pb = cb.map(([x, z]) => x * ux + z * uz);
			gap = Math.max(gap, Math.min(...pb) - Math.max(...pa), Math.min(...pa) - Math.max(...pb));
		});

		return gap;

	}

	// Un plateau poussé depuis (x0, z0) le long de (dx, dz) jusqu'à la première place qui convient, puis un peu plus
	// loin si le tirage le veut et que la place convient encore
	static pushOut(make, x0, z0, dx, dz, ok, extra) {

		for(let d = 0; d < 26; d += .3) {

			const parcel = make(x0 + dx * d, z0 + dz * d);

			if(ok(parcel)) {
				const further = make(x0 + dx * (d + extra), z0 + dz * (d + extra));
				return ok(further) ? further : parcel;
			}

		}

		return null;

	}

	// Un plateau tient dans le cadre avec une marge, cultures comprises, et l'ile peut l'entourer de 2 m sans que sa
	// tranche en sorte
	static fitsParcel(parcel, margin) {

		const extent = GameBoardCourt.getEmptyExtent();

		parcel.tiles.forEach(tile => {
			const half = GameBoardCourt.tileHalf(tile.a) + margin;
			GameBoardCourt.projectBox({ min: { x: tile.x - half, y: 0, z: tile.z - half }, max: { x: tile.x + half, y: 1.2, z: tile.z + half } }, extent);
		});

		parcel.tiles.forEach(tile => {
			const rectangle = GameBoardCourt.tileRect(tile, 2 + parcel.apron);
			GameBoardCourt.projectBox({ min: { x: rectangle.minX, y: -1.8, z: rectangle.minZ }, max: { x: rectangle.maxX, y: 0, z: rectangle.maxZ } }, extent);
		});

		return GameBoardCourt.isInside(extent);

	}

	// Les plateaux se tiennent loin du puits et les uns des autres, et le chemin droit du puits à chacun ne frôle pas les autres
	static isLayoutOk(parcels, wx, wz, gap) {

		if(parcels.some(parcel => GameBoardCourt.fitsParcel(parcel, 1.2) === false)) {
			return false;
		}

		for(let i = 0; i < parcels.length; i++) {

			if(GameBoardCourt.getParcelDistance(parcels[i], wx, wz) < 5) {
				return false;
			}

			for(let j = i + 1; j < parcels.length; j++) {
				if(GameBoardCourt.isApart(parcels[i], parcels[j], gap) === false) {
					return false;
				}
			}

		}

		return parcels.every((parcel, i) => {
			const entry = GameBoardCourt.getEntry(parcel, wx, wz);
			return GameBoardCourt.trail(wx, wz, entry.x, entry.z, 0, .5).every(([x, z]) => parcels.every((other, j) => j === i || GameBoardCourt.getParcelDistance(other, x, z) > 2.2 + other.apron));
		});

	}

	/**
	 * Le repère d'un plateau : ses demi-côtés dans son propre sens, élargis de `margin`, et le passage de ses
	 * coordonnées à celles de la cour
	 */
	static getFrame(parcel, margin) {

		const toLocal = (x, z) => GameBoardCourt.rot(x - parcel.cx, z - parcel.cz, -parcel.angle);
		const toWorld = (lx, lz) => {
			const [x, z] = GameBoardCourt.rot(lx, lz, parcel.angle);
			return [parcel.cx + x, parcel.cz + z];
		};

		let hx = 0, hz = 0;

		parcel.tiles.forEach(tile => {
			const [lx, lz] = toLocal(tile.x, tile.z), half = GameBoardCourt.tileHalf(tile.a - parcel.angle);
			hx = Math.max(hx, Math.abs(lx) + half);
			hz = Math.max(hz, Math.abs(lz) + half);
		});

		return {
			hx: hx + margin, hz: hz + margin, toLocal, toWorld,
			support: (dx, dz) => Math.max(...[[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([i, k]) => {
				const [x, z] = GameBoardCourt.rot(i * (hx + margin), k * (hz + margin), parcel.angle);
				return x * dx + z * dz;
			}))
		};

	}

	// ---------------------------------------------------------------------------------------------------------------
	// La géométrie

	// Les points d'un chemin de (x0, z0) à (x1, z1), qui ondule de `bend` au milieu
	static trail(x0, z0, x1, z1, bend, step = .45) {

		const length = Math.hypot(x1 - x0, z1 - z0), n = Math.max(2, Math.ceil(length / step));
		const nx = -(z1 - z0) / length, nz = (x1 - x0) / length;

		return Array.from({ length: n + 1 }, (_, i) => {
			const t = i / n, s = Math.sin(t * Math.PI) * bend;
			return [x0 + (x1 - x0) * t + nx * s, z0 + (z1 - z0) * t + nz * s];
		});

	}

	// L'emprise d'un modèle posé, comme `Box3.applyMatrix4()` de three la calcule pour une rotation autour de y : le
	// même calcul, dans le même ordre, pour que la cour soit la même ici et dans la page
	static transform(box, x, y, z, scale, angle) {

		const s = Math.sin(angle / 2), w = Math.cos(angle / 2);
		const y2 = s + s, yy = s * y2, wy = w * y2;
		const e0 = (1 - yy) * scale, e2 = -wy * scale, e5 = scale, e8 = wy * scale, e10 = (1 - yy) * scale;

		const result = { min: { x: Infinity, y: Infinity, z: Infinity }, max: { x: -Infinity, y: -Infinity, z: -Infinity } };

		for(let index = 0; index < 8; index++) {

			const px = (index & 4) ? box.max.x : box.min.x, py = (index & 2) ? box.max.y : box.min.y, pz = (index & 1) ? box.max.z : box.min.z;
			const cx = e0 * px + e8 * pz + x, cy = e5 * py + y, cz = e2 * px + e10 * pz + z;

			result.min.x = Math.min(result.min.x, cx);
			result.min.y = Math.min(result.min.y, cy);
			result.min.z = Math.min(result.min.z, cz);
			result.max.x = Math.max(result.max.x, cx);
			result.max.y = Math.max(result.max.y, cy);
			result.max.z = Math.max(result.max.z, cz);

		}

		return result;

	}

	// Ce qu'une pièce barre : le tronc d'un arbre, l'emprise d'une autre pièce élargie de REACH
	static getObstacleOf(slug, box, x, z) {

		if(GameBoardCourt.TREES.has(slug)) {
			const radius = Math.min(box.max.x - box.min.x, box.max.z - box.min.z) / 2 * GameBoardCourt.TRUNK;
			return GameBoardCourt.around(x, z, radius);
		}

		return GameBoardCourt.grow({ minX: box.min.x, maxX: box.max.x, minZ: box.min.z, maxZ: box.max.z }, GameBoardCourt.REACH);

	}

	static tileHalf(angle) {
		return GameBoardCourt.TILE / 2 * (Math.abs(Math.cos(angle)) + Math.abs(Math.sin(angle)));
	}

	static tileRect(tile, extra) {
		return GameBoardCourt.around(tile.x, tile.z, GameBoardCourt.tileHalf(tile.a) + extra);
	}

	// Un point dans le plan de l'image
	static project(x, y, z) {
		const right = GameBoardCourt.RIGHT, up = GameBoardCourt.UP;
		return [x * right[0] + y * right[1] + z * right[2], x * up[0] + y * up[1] + z * up[2]];
	}

	static getEmptyExtent() {
		return { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity };
	}

	static projectBox(box, extent) {

		for(let index = 0; index < 8; index++) {

			const [x, y] = GameBoardCourt.project((index & 1) ? box.max.x : box.min.x, (index & 2) ? box.max.y : box.min.y, (index & 4) ? box.max.z : box.min.z);

			extent.minX = Math.min(extent.minX, x);
			extent.maxX = Math.max(extent.maxX, x);
			extent.minY = Math.min(extent.minY, y);
			extent.maxY = Math.max(extent.maxY, y);

		}

		return extent;

	}

	static isInside(extent) {
		const limit = GameBoardCourt.LIMIT;
		return extent.minX >= limit.minX && extent.maxX <= limit.maxX && extent.minY >= limit.minY && extent.maxY <= limit.maxY;
	}

	static fits(box) {
		return GameBoardCourt.isInside(GameBoardCourt.projectBox(box, GameBoardCourt.getEmptyExtent()));
	}

	static normalize([x, y, z]) {
		const length = Math.sqrt(x * x + y * y + z * z) || 1;
		const inverse = 1 / length;
		return [x * inverse, y * inverse, z * inverse];
	}

	static cross([ax, ay, az], [bx, by, bz]) {
		return [ay * bz - az * by, az * bx - ax * bz, ax * by - ay * bx];
	}

	static overlaps(a, b) {
		return a.minX < b.maxX && b.minX < a.maxX && a.minZ < b.maxZ && b.minZ < a.maxZ;
	}

	static rect(x0, z0, x1, z1) {
		return { minX: Math.min(x0, x1), maxX: Math.max(x0, x1), minZ: Math.min(z0, z1), maxZ: Math.max(z0, z1) };
	}

	static grow(rectangle, d) {
		return { minX: rectangle.minX - d, maxX: rectangle.maxX + d, minZ: rectangle.minZ - d, maxZ: rectangle.maxZ + d };
	}

	static around(x, z, half) {
		return { minX: x - half, maxX: x + half, minZ: z - half, maxZ: z + half };
	}

	static getDistanceToRect(rectangle, x, z) {
		return Math.hypot(Math.max(rectangle.minX - x, 0, x - rectangle.maxX), Math.max(rectangle.minZ - z, 0, z - rectangle.maxZ));
	}

	// Le repère de l'image au sol : p vers la droite, q vers le bas de l'image, vers le joueur
	static world(p, q) {
		return [(p + q) * Math.SQRT1_2, (q - p) * Math.SQRT1_2];
	}

	static screenOf(x, z) {
		return [(x - z) * Math.SQRT1_2, (x + z) * Math.SQRT1_2];
	}

	// La rotation d'un point autour de y, celle de `rotation.y` dans three
	static rot(x, z, angle) {
		return [x * Math.cos(angle) + z * Math.sin(angle), -x * Math.sin(angle) + z * Math.cos(angle)];
	}

	// La rotation qui tourne la face +z d'un modèle vers (dx, dz)
	static toward(dx, dz) {
		return Math.atan2(dx, dz);
	}

	/**
	 * Une couleur plus claire ou plus sombre de `amount` en luminosité, comme `Color.offsetHSL()` de three la rend :
	 * en teinte, saturation et luminosité de l'espace linéaire, puis de retour en sRGB
	 */
	static shade(hex, amount) {

		const toLinear = c => (c < .04045) ? .0773993808 * c : Math.pow(.9478672986 * c + .0521327014, 2.4);
		const toSrgb = c => (c < .0031308) ? 12.92 * c : 1.055 * Math.pow(c, .41666) - .055;
		const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

		const value = parseInt(hex.slice(1), 16);
		const r = toLinear((value >> 16 & 255) / 255), g = toLinear((value >> 8 & 255) / 255), b = toLinear((value & 255) / 255);

		const max = Math.max(r, g, b), min = Math.min(r, g, b);
		const lightness = (min + max) / 2;
		let hue = 0, saturation = 0;

		if(min !== max) {

			const delta = max - min;
			saturation = (lightness <= .5) ? delta / (max + min) : delta / (2 - max - min);

			if(max === r) {
				hue = (g - b) / delta + (g < b ? 6 : 0);
			} else if(max === g) {
				hue = (b - r) / delta + 2;
			} else {
				hue = (r - g) / delta + 4;
			}

			hue /= 6;

		}

		const h = ((hue % 1) + 1) % 1, s = clamp(saturation, 0, 1), l = clamp(lightness + amount, 0, 1);
		const toRgb = (p, q, t) => {
			if(t < 0) {
				t += 1;
			}
			if(t > 1) {
				t -= 1;
			}
			if(t < 1 / 6) {
				return p + 6 * (q - p) * t;
			} else if(t < .5) {
				return q;
			} else if(t < 2 / 3) {
				return p + 6 * (q - p) * (2 / 3 - t);
			}
			return p;
		};

		let rgb;

		if(s === 0) {
			rgb = [l, l, l];
		} else {
			const q = (l <= .5) ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
			rgb = [toRgb(p, q, h + 1 / 3), toRgb(p, q, h), toRgb(p, q, h - 1 / 3)];
		}

		return '#'+ rgb.map(c => Math.round(clamp(255 * toSrgb(c), 0, 255)).toString(16).padStart(2, '0')).join('');

	}

	// Un tirage déterministe, de la graine de la cour
	static makeRandom(seed) {

		let state = (Math.imul(seed >>> 0, 2654435761) ^ 0x9e3779b9) >>> 0;

		const next = () => {
			state = (state + 0x6d2b79f5) >>> 0;
			let t = state;
			t = Math.imul(t ^ (t >>> 15), t | 1);
			t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
			return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
		};

		next.range = (a, b) => a + (b - a) * next();
		next.pick = list => list[Math.floor(next() * list.length)];
		next.chance = p => next() < p;
		next.sign = () => next() < .5 ? -1 : 1;
		next.weighted = pairs => {
			let r = next() * pairs.reduce((sum, [, weight]) => sum + weight, 0);
			for(const [value, weight] of pairs) {
				if((r -= weight) < 0) {
					return value;
				}
			}
			return pairs[pairs.length - 1][0];
		};
		next.shuffle = list => {
			const copy = [...list];
			for(let i = copy.length - 1; i > 0; i--) {
				const j = Math.floor(next() * (i + 1));
				[copy[i], copy[j]] = [copy[j], copy[i]];
			}
			return copy;
		};

		return next;

	}

}
