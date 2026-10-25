/**
 * La vue en 3D du plan d'une saison : le sol, les parcelles, les jardins, les planches et leurs
 * cultures au jour choisi, posés en mètres dans un repère local centré sur la structure, x vers l'est
 * et n vers le nord. Les îlots d'un foncier éclaté y sont rapprochés (`ScenePlanLand`).
 *
 * Une seule scène vit à la fois. Elle se libère sur `navigation.sleep` : en navigation ajax, la page
 * suivante ne recharge pas le document, et un contexte WebGL oublié tiendrait la carte graphique
 * jusqu'à ce que le navigateur en refuse un nouveau.
 */
class MapScene {

	static current = null;

	// Le conteneur de la scène courante tel que la page l'a rendu (restart())
	static pristine = null;

	// Un lit de terre de 2 mm, posé 5 cm au-dessus du pré : la terre se lit comme une surface, pas comme un volume
	static BED_BASE = .05;
	static BED_HEIGHT = .002;
	// L'écart entre la valeur claire et la valeur foncée des aplats de la terre, en part de sa couleur
	static BED_STEP = .15;

	// L'écart de la cible au sol qu'elle regarde en deçà duquel elle ne se recale pas, en mètres (seat())
	static SEAT_SLACK = .001;

	// Les légumes un peu plus vifs que dans les autres scènes, pour se détacher du pré pâle : la part de couleur gardée au-delà du
	// gris de même luminance
	static CROP_SATURATION = 1.18;

	// Ce que la caméra voit en hauteur au zoom 1, en mètres ; le zoom fait le reste
	static FRUSTUM = 100;

	// En dessous de ce nombre de pixels par mètre, les noms des jardins se chevauchent et cachent les planches. Les pastilles
	// paraissent au même seuil : une par culture, elles tiennent là où les noms de planche se chevaucheraient
	static LABEL_PIXELS = 10;

	// Les noms de planche paraissent en bout de planche à BED_LABELS_FROM pixels par mètre, en petit corps jusqu'à BED_LABELS_FULL.
	// En petit corps, une planche sur deux d'une même rangée peut reculer le sien de BED_LABEL_STEP mètres dans son axe : en
	// quinconce, deux noms voisins ne se touchent plus à l'échelle où une planche fait trente pixels de large. Aucun nom ne
	// s'efface pour laisser lire son voisin : montrés, ils le sont tous
	static BED_LABELS_FROM = 26;
	static BED_LABELS_FULL = 45;
	static BED_LABEL_STEP = 1.3;

	// L'écart en pixels qu'un nom de jardin garde avec un nom de planche, qui se lirait sinon comme son titre
	static LABEL_BED_MARGIN = 9;

	// La caméra d'ouverture regarde l'origine depuis ce côté, à OPENING_DISTANCE mètres sur chaque axe. L'écriteau d'un abri se pose
	// GREENHOUSE_SIGN_LIFT mètres au-dessus du sol, à GREENHOUSE_SIGN_OUT mètres d'un pignon : sur la bâche, il cachait les cultures dessous
	static OPENING_EYE = { x: 1, n: -1 };
	static OPENING_DISTANCE = 300;
	static GREENHOUSE_SIGN_OUT = 2;
	static GREENHOUSE_SIGN_LIFT = .4;

	// Le tri des noms qui se recouvrent passe au plus toutes les 150 ms : pendant un glissé les noms suivent la vue à chaque image,
	// seule la décision de les cacher retarde d'autant
	static DECLUTTER_EVERY = 150;

	// Le choix de cacher les pastilles vaut pour toutes les structures. Nommée comme celle des annotations de la banque
	static BADGES_HIDDEN_KEY = 'map-scene-badges-hidden';

	// Le zoom avant s'arrête à ce nombre de pixels par mètre : au-delà, les plants ne gagnent plus rien et la planche sort de l'écran
	static ZOOM_MAX = 65;

	// Part de la vue que remplit un cadrage, pour laisser un liseré de pré autour de ce qui est cadré
	static FRAME_FILL = .92;

	// La plus petite part de la scène qu'un cadrage garde, quoi que la frise ou la feuille de pose en couvrent
	static USABLE_LEAST = .35;

	// Un jour de la saison toutes les 150 millisecondes pendant la lecture : la saison passe en une minute, et une pousse de
	// quelques centimètres par jour se lit comme une croissance continue. C'est la vitesse Normale, que `SceneSpeed` divise
	static PLAY_DAY = 150;

	// Un clic sur la scène choisit ce qu'il touche s'il ne bouge pas de plus de 8 pixels en moins de 600 ms : au-delà, c'est un
	// glissé qui déplace la vue
	static CLICK_PX = 8;
	static CLICK_MS = 600;

	// Le losange doré au-dessus de ce qui est choisi, posé à 1,6 m de la terre et grandi avec le recul pour rester visible de loin
	static MARKER_COLOR = '#ffcf4a';
	static MARKER_GLOW = '#7a5200';
	static MARKER_HEIGHT = 1.6;
	static MARKER_SCREEN = 35;

	// Le vent des plants, calmé pour moins distraire l'œil, au réglage retenu sur le prototype : un balancement réduit, et un temps
	// du vent ralenti d'un cinquième, qui allonge d'autant chaque oscillation
	static WIND_SWAY = .107;
	static WIND_PACE = .8;

	// Le liseré des planches de l'espèce choisie et le trait entre deux îlots gardent leur épaisseur à l'écran, en pixels, pour se
	// lire de la vue d'ensemble comme de près
	static OUTLINE_PX = 3;
	static FOLD_PX = 3;

	// Le liseré passe 2 cm au-dessus de la terre qu'il longe : un plant posé devant lui le cache
	static OUTLINE_LIFT = .02;

	// Le trait entre deux îlots, d'un vert franchement plus sombre que l'herbe pour se lire sur le pré à tout zoom, sans tirer vers
	// le brun de la terre des planches
	static FOLD_SHADE = .72;

	// Les herbes et les sols du terrain, sous la valeur que `map\Configuration` enregistre. La première de chaque table est le défaut
	// du schéma, que `readTerrain()` donne aussi à une valeur inconnue, y compris à une valeur retirée du schéma. La verte pâle a
	// sa table de saison dans le plan (`MapScenePlanSeason.TABLES`), sa couleur est son 1er juillet. La presque blanche, le fond
	// de la première piste de « Plan clair », ne suit pas la saison, qui la verdirait au printemps, et ses aplats s'écartent près de
	// quatre fois moins que ceux de l'herbe : un fond, pas un pré
	static GRASSES = {
		tender: { color: '#8fbf72', step: .045, seasonless: false },
		pale: { color: '#cde2c0', step: .04, seasonless: false },
		clear: { color: '#eef2e4', step: .012, seasonless: true },
		dry: { color: '#bdb56e', step: .045, seasonless: false },
		lush: { color: '#5e9550', step: .045, seasonless: false },
		// Plus grise que la verte tendre, dont elle ne s'écartait pas assez une fois éclaircie comme elle dans le plan
		grazed: { color: '#9cb07c', step: .045, seasonless: false }
	};

	// Le sol par défaut est un sable clair, celui de « Plan clair », plus pâle que le sable qu'on choisit dans le décor pour que
	// les deux vignettes ne se confondent pas. Les sols foncés sont adoucis pour rester dans la famille du pré pâle
	static SOILS = {
		loam: '#e0b183',
		clay: '#a86a4e',
		sand: '#c4a77c',
		humus: '#6e5848',
		chalk: '#b3a58f',
		ochre: '#d49a5e',
		sandstone: '#c9978a',
		schist: '#8f8b85',
		brown: '#8f6e55'
	};

	// Le cadre vert foncé qui longe le bord grignoté d'une planche, en mètres, posé seulement sur une terre claire : sur le pré pâle,
	// une terre claire n'a pas assez d'écart de valeur pour détacher une planche de l'herbe, une terre foncée si. Il garde au moins
	// un pixel à l'écran, sans quoi il disparaitrait de la vue d'ensemble
	static BED_FRAME = '#3f6a37';
	static BED_FRAME_WIDTH = .035;

	// La luminance relative au-dessus de laquelle une terre est claire et prend le cadre (`framed()`)
	static BED_FRAME_LUMINANCE = .3;

	// Sous abri, la terre reste un peu plus sombre que dehors, quel que soit le sol
	static GREENHOUSE_SHADE = .95;

	// L'écart entre deux arceaux d'un abri, en mètres
	static ARCH_STEP = 3;

	/**
	 * Appelée par `onrender` : une exception levée ici ferait tomber les `onrender` suivants de la page,
	 * d'où une chaîne de promesse dès la première instruction, dont le rejet s'affiche par le bandeau
	 * d'erreur du cadriciel.
	 */
	static start(container) {

		MapScene.stop();

		// Le conteneur tel que la page l'a rendu, avant que la scène n'y pose ses écouteurs : restart() repart de lui
		MapScene.pristine = container.cloneNode(true);

		const scene = new MapScene(container);
		MapScene.current = scene;

		Promise.resolve()
			.then(() => scene.open())
			.catch(error => {

				// Une scène fermée par une navigation pendant son chargement ne parle plus : la page a changé
				if(scene.closed) {
					return;
				}

				scene.close();
				MapScene.current = null;

				console.error(error);
				Lime.Alert.showStaticError(container.dataset.error);

			});

	}

	static stop() {

		if(MapScene.current !== null) {
			MapScene.current.close();
			MapScene.current = null;
		}

	}

	/**
	 * La scène remontée sur `scene`, à la semaine `week`. close() libère ce que la scène a créé, pas les écouteurs qu'elle a posés
	 * sur les nœuds de la page : ses enfants repartent de ceux que la page a rendus. Le conteneur reste, parce qu'il est l'élément
	 * du plein écran, qu'un nœud remplacé ferait quitter.
	 */
	static restart(scene, week) {

		const container = MapScene.current.container;
		const fresh = MapScene.pristine.cloneNode(true);

		MapScene.stop();

		container.replaceChildren(...fresh.childNodes);
		container.dataset.scene = JSON.stringify(scene);
		container.qs('[data-scene-week]').value = week;
		container.parseRender();

	}

	static smooth(a, b, x) {
		const k = Math.max(0, Math.min(1, (x - a) / (b - a)));
		return k * k * (3 - 2 * k);
	}

	// Les dates du socle sont des textes 'AAAA-MM-JJ', lus en UTC : un passage à l'heure d'été ne décale aucun jour
	static addDays(date, days) {
		return new Date(Date.parse(date) + days * 86400000).toISOString().slice(0, 10);
	}

	static daysBetween(from, to) {
		return SeriesSheet.daysBetween(from, to);
	}

	/**
	 * Des boutons qui prennent la place d'autres. Un bouton caché pendant qu'il a le focus le rend au document, et la touche Tab
	 * repartirait du haut de la page : le focus passe au premier de ceux qui se montrent. Seulement au clavier : après un clic de
	 * souris, l'espace ou l'entrée cliquerait le bouton venu sous le focus sans qu'on le sache.
	 */
	static swap(hidden, shown) {

		const focused = hidden.some(button => button.contains(document.activeElement) && document.activeElement.matches(':focus-visible'));

		hidden.forEach(button => button.classList.add('hide'));
		shown.forEach(button => button.classList.remove('hide'));

		if(focused) {
			shown[0].focus();
		}

	}

	static push(map, key, value) {

		if(map.has(key) === false) {
			map.set(key, []);
		}

		map.get(key).push(value);

	}

	constructor(container) {

		this.container = container;
		this.closed = false;

		this.renderer = null;
		this.labels = null;
		this.controls = null;
		this.observer = null;
		this.scene = null;
		this.camera = null;
		this.crops = null;
		this.crew = null;
		this.decor = null;
		this.season = null;
		this.sun = null;
		this.hemisphere = null;
		this.materials = null;
		this.player = null;
		this.disposables = [];

		this.zone = null;
		this.species = null;
		this.week = null;
		this.day = 0;
		this.dayAt = 0;

		this.plotLabels = [];
		this.bedLabels = [];
		this.bedsSmall = false;
		this.declutterAt = 0;
		this.covers = [];
		// Les écriteaux d'abri se placent à l'échelle du cadrage d'ouverture : les images rendues avant lui n'ont pas encore sa taille
		this.openingFramed = false;
		this.badges = null;
		this.folds = [];
		this.outline = { mesh: null, edges: [], thickness: 0, side: null };
		this.legendKey = null;
		this.legendFitted = null;
		this.legendScroll = null;
		this.texts = null;

		this.films = null;
		this.geometry = null;
		this.selected = null;
		this.spans = new Map();
		this.sheet = null;
		this.marker = null;
		this.markerAt = null;
		this.sparks = null;
		this.standing = null;
		this.bubbles = null;
		this.harvestWeeks = null;
		this.centers = null;
		this.photo = false;
		this.record = null;
		this.months = null;
		this.onKey = null;
		this.onFullscreen = null;

		this.ambience = null;
		this.boards = null;
		this.sign = null;
		this.insects = null;
		this.antics = null;
		this.fauna = null;
		this.shown = null;

		this.ruins = null;
		this.hold = null;
		this.field = null;

	}

	open() {

		const plan = JSON.parse(this.container.dataset.scene);
		const target = this.container.qs('[data-scene-directory]');

		this.texts = JSON.parse(this.container.qs('[data-scene-sheet]').dataset.texts);

		// Les icônes des espèces sont celles du produit, un symbole par espèce. La scène n'en a pas besoin pour se dessiner : elle ne
		// les attend pas, et chaque pastille garde un point de la couleur de sa culture jusqu'à leur arrivée, ou pour de bon si elles
		// ne se lisent pas
		const symbols = SceneLoader.read(this.container.dataset.plants, 'text')
			.then(text => MapSceneBadges.symbols(text))
			.catch(() => new Map());

		return Promise.all([SceneLoader.loadCrop(target, [
			'species.get',
			'species.woody',
			'crop.models',
			'crop.layout',
			'crop.scatter',
			'crop.material',
			'crop.pool',
			'crop.tint',
			'crop.ridge',
			'crop.ridgeGeometry',
			'crop.placeRidges',
			'fruit.shapes',
			'fruit.material',
			'fruit.place',
			'fruit.harvestedWhole',
			'fruit.underground',
			'stage.timeline',
			'stage.at',
			'stage.monday',
			'stage.week',
			'figure.assemble',
			'figure.prop',
			'figure.pose',
			'figure.hold',
			'building.build',
			'building.uploaded'
		]), SceneCrew.prepare(target, plan)]).then(([socle, avatars]) => {

			// La page a pu changer pendant le chargement
			if(this.closed) {
				return;
			}

			this.draw(socle, plan, target, symbols, avatars);

		});

	}

	draw(socle, plan, target, symbols, avatars) {

		const { THREE, addons } = socle;
		const geometry = new ScenePlanGeometry(plan);

		// Le relief se pose une fois, avant tout ce qui se pose sur le sol, et seulement s'il est allumé : l'éteindre ou l'allumer
		// remonte la scène (MapScene.restart())
		if(plan.relief !== null && MapSceneRelief.shown(this.container)) {

			geometry.relief = MapSceneRelief.build(plan.relief, geometry);

			if(geometry.relief !== null) {
				this.disposables.push(geometry.relief);
			}

		}

		this.geometry = geometry;

		// Un téléphone dessine moins de pixels et une ombre plus grossière : le lissage des bords et une carte d'ombre de 4096 px y
		// coûtent plus qu'ils ne se voient
		const mobile = matchMedia('(max-width: 767px), (pointer: coarse)').matches;

		const canvas = document.createElement('canvas');
		target.append(canvas);

		this.renderer = new THREE.WebGLRenderer({ canvas, antialias: mobile === false });
		this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, mobile ? 1.5 : 2));
		// L'ombre se recalcule quand les cultures ou le cadre changent, pas à chaque image : la refaire au vent doublerait
		// les triangles dessinés pour un balancement qui ne se voit pas dans l'ombre. Le filtrage simple suffit à l'ombre d'une
		// structure entière vue de haut, où l'adoucissement des bords ne se distingue pas
		this.renderer.shadowMap.enabled = true;
		this.renderer.shadowMap.type = THREE.PCFShadowMap;
		this.renderer.shadowMap.autoUpdate = false;

		this.labels = new addons.CSS2DRenderer();
		this.labels.domElement.className = 'map-scene-labels';
		target.append(this.labels.domElement);

		this.season = new MapScenePlanSeason(THREE);

		this.scene = new THREE.Scene();
		this.ambience = new MapSceneAmbience(THREE, this.scene, plan);
		this.disposables.push(this.ambience);

		this.sun = new THREE.DirectionalLight();
		this.sun.castShadow = true;
		this.sun.shadow.mapSize.set(mobile ? 1024 : 4096, mobile ? 1024 : 4096);
		this.sun.shadow.bias = -.0004;
		this.sun.shadow.normalBias = .04;
		this.hemisphere = new THREE.HemisphereLight('#dcefff', '#93a06c');
		this.scene.add(this.hemisphere, this.sun, this.sun.target);

		// Les quatre paliers d'ombrage du toon : le relief se lit par eux, pas par une matière. Resserrés vers le clair, ils gardent
		// le relief sans assombrir la face à l'ombre d'un plan de jour très clair
		const ramp = new THREE.DataTexture(new Uint8Array([150, 192, 228, 255]), 4, 1, THREE.RedFormat);
		ramp.minFilter = THREE.NearestFilter;
		ramp.magFilter = THREE.NearestFilter;
		ramp.needsUpdate = true;

		const materials = {};
		materials.ground = new THREE.MeshToonMaterial({ gradientMap: ramp });
		materials.fold = new THREE.MeshToonMaterial({ gradientMap: ramp });
		materials.soil = new THREE.MeshToonMaterial({ gradientMap: ramp });
		materials.soilGreenhouse = new THREE.MeshToonMaterial({ gradientMap: ramp });

		this.grassStep = { value: 0 };
		this.bedFrame = { value: 0 };

		MapScene.grassTexture(materials.ground, this.grassStep);
		MapScene.bedTexture(THREE, materials.soil, this.bedFrame);
		MapScene.bedTexture(THREE, materials.soilGreenhouse, this.bedFrame);

		this.materials = materials;
		plan.terrain = MapScene.readTerrain(plan.terrain);
		this.setTerrain(plan.terrain);

		// Le bois clair des écriteaux, que la feuille de la scène tient : le liseré et les pastilles le peignent aussi, lu une fois
		const style = getComputedStyle(this.container);
		const wood = { cream: style.getPropertyValue('--map-scene-cream').trim(), ring: style.getPropertyValue('--map-scene-ring').trim() };

		materials.outline = new THREE.MeshBasicMaterial({ color: wood.cream, polygonOffset: true, polygonOffsetFactor: -2, polygonOffsetUnits: -2 });

		// Tenus à part des maillages : une structure sans serre ne pose aucun maillage sur la terre de serre
		this.disposables.push(ramp, ...Object.values(materials));

		// Un disque de rayon 1, que resize() pose sur la structure et taille sur le plus grand recul
		const ground = new THREE.Mesh(new THREE.CircleGeometry(1, 64), materials.ground);
		ground.rotation.x = -Math.PI / 2;
		ground.position.y = -.02;
		ground.receiveShadow = true;
		this.scene.add(ground);

		// Le sol drapé prend la place du disque, et sa matière : le disque n'a pas les altitudes que le nuanceur du relief lit
		if(geometry.relief !== null) {

			ground.visible = false;

			const draped = new THREE.Mesh(geometry.relief.ground(THREE, geometry, materials.ground), materials.ground);
			draped.position.y = -.02;
			draped.receiveShadow = true;
			this.scene.add(draped);

		}

		this.films = new MapSceneFilms(THREE, canvas, () => this.camera, event => this.target(this.ground(canvas, event), geometry) !== null);
		this.disposables.push(this.films);

		const wind = { value: 0 };

		// Le champ se dessine sans l'écran : la feuille de pose, la carte des calques et les gestes s'y ajoutent plus bas
		this.field = new MapSceneField({
			socle, plan, geometry,
			parent: this.scene,
			gradientMap: ramp,
			wind,
			soil: materials.soil,
			soilGreenhouse: materials.soilGreenhouse,
			shelter: () => this.films.add(this.ambience.film),
			arches: this.ambience.arches,
			spacing: 1,
			diameterLabel: this.container.dataset.diameterLabel,
			avatars,
			changed: () => {
				this.renderer.shadowMap.needsUpdate = true;
			}
		});
		this.disposables.push(this.field);

		this.crops = this.field.crops;
		this.decor = this.field.decor;
		this.crew = this.field.crew;

		// Un nom d'abri ne se pose pas sur une planche : devant le pignon vu de la caméra d'ouverture s'il y tient, sinon devant l'autre
		// pignon, où il se dresse au-dessus de son ancre pour s'écarter de la bâche. S'il couvre une planche aux deux places, il reste
		// devant le pignon vu
		const G = ScenePlanGeometry;
		const towardEye = G.norm(MapScene.OPENING_EYE);
		const acrossEye = { x: -towardEye.n, n: towardEye.x };
		const eyeSlope = 1 / G.length(MapScene.OPENING_EYE);
		const eyeSine = eyeSlope / Math.hypot(1, eyeSlope);

		const rectsOverlap = (a, b) => [a.u, a.w, b.u, b.w].every(axis => {
			const pa = geometry.corners(a).map(q => G.dot(q, axis));
			const pb = geometry.corners(b).map(q => G.dot(q, axis));
			return Math.max(...pa) > Math.min(...pb) && Math.max(...pb) > Math.min(...pa);
		});

		// Une vue orthographique rabat l'écriteau sur le sol le long du regard : sa hauteur à l'écran y devient une profondeur, et son
		// ancre levée tombe un peu plus loin de la caméra que le point qu'elle surplombe
		const greenhouseSignFootprint = (anchor, [width, height], pixelsPerMeter, hangs) => {
			const meters = width / pixelsPerMeter;
			const depth = height / (pixelsPerMeter * eyeSine);
			const ground = G.sub(anchor, G.mul(towardEye, MapScene.GREENHOUSE_SIGN_LIFT / eyeSlope));
			return { origin: G.sub(G.sub(ground, G.mul(acrossEye, meters / 2)), G.mul(towardEye, hangs ? 0 : depth)), u: towardEye, w: acrossEye, length: depth, width: meters };
		};

		for(const { plot, rectangle } of geometry.plots.values()) {

			// Un jardin qui couvre sa zone n'a pas de nom à lui, et un jardin de plein champ a sa pancarte (`MapSceneBoards`) : seul un
			// abri garde son nom en écriteau, posé au sol devant son pignon
			if(plot.zoneFill || plot.mode !== 'greenhouse') {
				continue;
			}

			const element = document.createElement('div');
			element.className = 'map-scene-label map-scene-label-plot';
			element.textContent = plot.name;

			// Le pignon se choisit d'après la caméra d'ouverture, une fois pour toutes : l'écriteau ne change pas de côté quand la vue tourne
			const end = (G.dot(rectangle.u, MapScene.OPENING_EYE) > 0) ? 1 : 0;
			const [seen, hidden] = [end, 1 - end].map(e => G.add(rectangle.origin, G.add(G.mul(rectangle.u, e * rectangle.length + (e ? 1 : -1) * MapScene.GREENHOUSE_SIGN_OUT), G.mul(rectangle.w, rectangle.width / 2))));

			const label = new addons.CSS2DObject(element);
			label.position.set(seen.x, geometry.height(seen.x, seen.n) + MapScene.GREENHOUSE_SIGN_LIFT, -seen.n);
			// L'écriteau pend sous son ancre : centré sur elle, sa moitié haute remontait sur les planches du bout de l'abri, qu'un pignon
			// tourné vers la caméra place toujours au-dessus de lui à l'écran
			label.center.set(.5, 0);
			label.userData = { size: null };
			// Sa taille ne se lit qu'une fois dans la page : le pignon se tranche au premier rendu qui suit le survol d'ouverture, à l'échelle de
			// ce cadrage. Rapprocher la vue rapetisse ensuite l'emprise, donc rien de ce qu'elle évitait ne se recouvre
			label.onAfterRender = () => {

				if(this.openingFramed === false || this.ambience.intro !== null) {
					return;
				}

				label.onAfterRender = () => {};

				const size = [element.offsetWidth, element.offsetHeight];
				const pixelsPerMeter = canvas.clientHeight * this.camera.zoom / (2 * MapScene.FRUSTUM);
				const covers = footprint => geometry.beds.some(bed => rectsOverlap(bed, footprint));

				if(covers(greenhouseSignFootprint(seen, size, pixelsPerMeter, true)) === false || covers(greenhouseSignFootprint(hidden, size, pixelsPerMeter, false))) {
					return;
				}

				label.position.set(hidden.x, geometry.height(hidden.x, hidden.n) + MapScene.GREENHOUSE_SIGN_LIFT, -hidden.n);
				label.center.set(.5, 1);
				this.declutterAt = 0;

			};
			this.scene.add(label);
			this.plotLabels.push(label);

		}

		this.drawBedLabels(THREE, addons, geometry);

		// Le trait au sol entre deux îlots, large d'un mètre et remis à FOLD_PX à chaque image. Sur le relief, il se coupe tous les
		// STEP mètres en long, et chaque sommet prend la hauteur du sol sous son axe : sa largeur change à chaque image, pas son axe
		for(const fold of geometry.land.folds()) {

			const plane = new THREE.PlaneGeometry(1, fold.length, 1, (geometry.relief === null) ? 1 : Math.ceil(fold.length / MapSceneRelief.STEP)).rotateX(-Math.PI / 2);

			if(geometry.relief !== null) {

				const position = plane.attributes.position;

				for(let i = 0; i < position.count; i++) {
					const along = ScenePlanGeometry.add(fold.center, ScenePlanGeometry.mul(fold.along, position.getZ(i)));
					position.setY(i, geometry.height(along.x, along.n));
				}

				plane.computeBoundingSphere();

			}

			const band = new THREE.Mesh(plane, materials.fold);
			band.position.set(fold.center.x, .03, -fold.center.n);
			band.rotation.y = Math.atan2(fold.along.x, -fold.along.n);
			band.receiveShadow = true;
			this.scene.add(band);
			this.folds.push(band);

		}

		this.badges = new MapSceneBadges(THREE, this.scene, wood);
		this.disposables.push(this.badges);

		symbols.then(found => {
			if(this.closed === false) {
				this.badges.learn(found, this.camera);
			}
		});

		this.camera = new THREE.OrthographicCamera(-1, 1, 1, -1, -5000, 5000);

		this.controls = new addons.MapControls(this.camera, canvas);
		this.controls.enableDamping = true;
		// Plus nerveux que les réglages par défaut : l'amortissement rend la main plus vite, et un geste couvre près du double de carte
		this.controls.dampingFactor = .14;
		this.controls.panSpeed = 1.8;
		this.controls.rotateSpeed = 1.6;
		this.controls.zoomSpeed = 1.9;
		this.controls.maxPolarAngle = Math.PI / 2.4;
		this.controls.zoomToCursor = true;
		this.controls.addEventListener('end', () => this.bubbles.loosen());
		this.controls.addEventListener('change', () => {

			this.seat();
			this.crops.face(this.camera);

			// La carte d'ombre ne couvre que les abords du point regardé : le soleil suit ce point, par pas de dix mètres, la carte
			// gardant trente mètres de marge autour de ce qui se cadre
			const moved = this.controls.target.clone().sub(this.sun.target.position);

			if(moved.lengthSq() >= 100) {
				this.sun.position.add(moved);
				this.sun.target.position.add(moved);
				this.renderer.shadowMap.needsUpdate = true;
			}

		});

		this.decor.attach({
			container: this.container,
			canvas,
			camera: this.camera,
			controls: this.controls,
			crew: this.field.crewGroup,
			switched: () => this.setPosing(THREE),
			terrain: terrain => {
				this.setTerrain(terrain);
				this.light(this.date(plan));
			}
		});

		this.camera.position.set(MapScene.OPENING_EYE.x * MapScene.OPENING_DISTANCE, MapScene.OPENING_DISTANCE, -MapScene.OPENING_EYE.n * MapScene.OPENING_DISTANCE);
		this.camera.lookAt(0, 0, 0);

		// La vue d'ensemble se serre sur les planches et les jardins tracés, sans le pré des zones ; une structure qui ne dessine ni
		// l'un ni l'autre se cadre sur ses zones
		const tight = geometry.beds.flatMap(bed => geometry.corners(bed)).concat([...geometry.plots.values()].filter(({ drawn }) => drawn).flatMap(({ points }) => points));
		const everything = (tight.length > 0) ? tight : [...geometry.zones.values()].flat();

		const span = this.span(THREE, geometry.beds.length > 0 ? geometry.beds.flatMap(bed => geometry.corners(bed)) : [...geometry.zones.values()].flat());
		// Le plancher du zoom est le plus large des cadrages que la scène propose : toute la structure, ou une zone sans planche,
		// qui se cadre sur son contour et peut déborder des planches de toutes les autres
		const bare = [...geometry.zones.entries()].filter(([id]) => geometry.beds.some(bed => bed.zone === id) === false).map(([, points]) => points);
		this.everything = everything;
		this.framings = [everything, ...bare].map(points => this.span(THREE, points));
		this.framingReach = this.span(THREE, geometry.land.islands.flatMap(island => island.reach));

		// La vue d'ouverture : le coin le plus vivant du jour quand il y en a un, sinon toute la structure
		let opening = null;

		// Ce que la vue couvre du pré autour de sa cible, à l'inclinaison la plus rasante
		const reach = zoom => MapScene.FRUSTUM / zoom * Math.hypot(canvas.clientWidth / canvas.clientHeight, 1 / Math.cos(this.controls.maxPolarAngle));

		const resize = () => {

			const width = canvas.clientWidth;
			const height = canvas.clientHeight;

			if(width === 0 || height === 0) {
				return;
			}

			// La part que la frise laisse se mesure avant que le rendu ne change de taille, faute de quoi la mesure recalcule toute la page
			const usable = this.usable();

			this.renderer.setSize(width, height, false);
			this.labels.setSize(width, height);
			this.badges.resize(width, height);
			this.insects.resize();
			this.fauna.resize();

			const aspect = width / height;
			Object.assign(this.camera, { left: -MapScene.FRUSTUM * aspect, right: MapScene.FRUSTUM * aspect, top: MapScene.FRUSTUM, bottom: -MapScene.FRUSTUM });

			this.controls.maxZoom = MapScene.ZOOM_MAX * MapScene.FRUSTUM * 2 / height;
			// Le zoom arrière s'arrête au plus large des cadrages : au-delà, la structure rapetisse au milieu d'un pré où il n'y a rien à voir
			this.controls.minZoom = this.zoomFloor(usable, this.decor.active);
			// Le canevas d'un téléphone change de taille quand la pose se ferme, après `setPosing()` : une vue que ce plancher laisse
			// trop loin revient elle aussi au cadrage choisi, au lieu de s'arrêter sur place au milieu du pré
			const beyond = (opening === null && this.camera.zoom < this.controls.minZoom);
			this.camera.zoom = Math.max(this.controls.minZoom, Math.min(this.camera.zoom, this.controls.maxZoom));
			this.camera.updateProjectionMatrix();

			// Le pré se centre sur la structure telle que les îlots rapprochés la posent, et non sur l'origine du plan, que le
			// rapprochement peut laisser loin d'elle ; au plus grand recul, la vue va d'un bout à l'autre sans en voir le bord
			ground.position.x = span.x;
			ground.position.z = -span.n;
			ground.scale.setScalar(reach(this.zoomFloor(MapScene.USABLE_LEAST, true)) + span.radius * 1.5 + 50);

			// Le premier cadrage seulement : ensuite, la vue appartient à l'utilisateur
			if(opening !== null) {

				this.frame(THREE, opening);

				// Les pancartes et l'enseigne se posent face à ce premier cadrage, puis le survol part de lui
				this.boards = new MapSceneBoards({ THREE, addons, plan, geometry, camera: this.camera, controls: this.controls, parent: this.scene, gradientMap: ramp });
				this.disposables.push(this.boards);

				this.sign = new MapSceneSign({ THREE, addons, plan, geometry, camera: this.camera, controls: this.controls, opening, boards: this.boards, decor: this.decor, parent: this.scene, gradientMap: ramp });
				this.disposables.push(this.sign);

				// Posée derrière les planches, l'enseigne sortirait par le haut de la vue d'ensemble, sous les boutons : elle entre dans
				// son cadrage et dans le plancher du zoom
				everything.push(...this.sign.footprint);
				this.framings[0] = this.span(THREE, everything);
				this.controls.minZoom = this.zoomFloor(usable, this.decor.active);

				this.renderer.shadowMap.needsUpdate = true;
				this.field.byDefault(plan, { boards: this.boards.boards, sign: this.sign.footprint });
				this.ambience.startIntro(this.camera, this.controls);
				this.openingFramed = true;

				opening = null;

			}

			if(beyond) {
				this.frameChosen(THREE);
			}

		};

		this.prepareHarvests(plan);

		this.sheet = new MapSceneSheet({
			container: this.container,
			texts: this.texts,
			plan,
			crops: this.crops,
			weekText: date => this.weekText(plan, date),
			chosen: target => this.choose(plan, target),
			closed: () => this.choose(plan, null)
		});
		this.disposables.push(this.sheet);

		this.marker = new THREE.Mesh(new THREE.OctahedronGeometry(.6, 0).scale(1, 1.6, 1), new THREE.MeshToonMaterial({ color: MapScene.MARKER_COLOR, emissive: MapScene.MARKER_GLOW, gradientMap: ramp }));
		this.marker.visible = false;
		this.scene.add(this.marker);
		this.disposables.push(this.marker.material);

		this.sparks = new MapSceneSparks(THREE, this.scene);
		this.disposables.push(this.sparks);

		this.bubbles = new MapSceneBubbles(addons, this.scene, canvas, () => this.covers, geometry);
		this.disposables.push(this.bubbles);

		this.ruins = new MapSceneRuins({
			THREE,
			parent: this.scene,
			gradientMap: ramp,
			shadow: () => {
				this.renderer.shadowMap.needsUpdate = true;
			},
			still: window.matchMedia('(prefers-reduced-motion: reduce)').matches
		});
		this.disposables.push(this.ruins);

		this.ruinSound = new MapSceneRuinsSound();
		this.disposables.push(this.ruinSound);

		// Ni pendant la pose du décor, qui a ses propres gestes, ni en mode photo, qui n'en a aucun
		this.hold = new MapSceneHold({
			container: this.container,
			canvas,
			tools: this.container.qs('[data-scene-ruins-tools]'),
			bar: this.container.qs('.map-scene-bar'),
			timeline: this.container.qs('.map-scene-timeline'),
			aim: event => this.aimRuin(canvas, event, plan, geometry),
			measure: target => MapSceneRuins.measure(THREE, target),
			mark: (target, glow, tremble) => this.ruins.mark(target, glow, tremble),
			prepare: target => this.ruins.prepare(target),
			forget: target => this.ruins.forget(target.key),
			choose: (kind, target) => {
				this.ruinSound.play(kind);
				this.ruins.begin(kind, target, performance.now());
			},
			enabled: () => (this.decor.active !== true && this.photo === false)
		});
		this.disposables.push(this.hold);

		// La clé des couleurs d'abord : sa hauteur est celle de la frise, que le premier cadrage évite
		opening = this.bind(THREE, plan, geometry, everything);

		this.ambience.gust(this.crops.material);
		this.ambience.gust(this.crops.leafMaterial);

		for(const material of [this.crops.material, this.crops.leafMaterial, this.crops.fruitMaterial]) {
			MapScene.saturate(material);
		}

		this.insects = new MapSceneInsects({ THREE, addons, crops: this.crops, geometry, scale: this.crew.scale, canvas, parent: this.scene, gradientMap: ramp });
		this.disposables.push(this.insects);

		this.antics = new MapSceneAntics({ THREE, addons, socle, crew: this.crew, decor: this.decor, geometry, parent: this.scene, gradientMap: ramp });
		this.disposables.push(this.antics);

		this.fauna = new MapSceneFauna({ THREE, socle, crew: this.crew, decor: this.decor, crops: this.crops, geometry, ambience: this.ambience, canvas, covers: () => this.covers, parent: this.scene });
		this.disposables.push(this.fauna);

		this.observer = new ResizeObserver(resize);
		this.observer.observe(canvas);
		resize();

		// Ce que la rangée des réglages et la frise couvrent du canevas se mesure quand l'une d'elles change de taille, la lecture étant
		// fraiche à ce moment-là : le tri des noms n'a alors aucune mise en page à relire
		const covers = new ResizeObserver(() => {
			this.covers = this.measureCovers();
			this.declutterAt = 0;
			this.bubbles.loosen();
			this.fitLegend();
		});
		[canvas, ...this.container.qsa('.map-scene-zone, .map-scene-screen, .map-scene-timeline')].forEach(element => covers.observe(element));
		this.disposables.push({ dispose: () => covers.disconnect() });

		this.pick(canvas, plan, geometry);

		this.renderer.setAnimationLoop(time => {

			wind.value = time / 1000 * MapScene.WIND_PACE;
			const moving = this.controls.update();

			// Le mode photo fige l'équipe : la vue se compose sans qu'un paysan ne sorte du cadre. Le film, tourné en mode photo, montre
			// l'année jouée, et l'équipe y travaille comme pendant la Lecture
			if(this.photo === false || this.record.active) {
				this.crew.update(time);
			}

			this.decor.animate(time);

			// Pendant la lecture, la chute des feuilles avance entre deux jours montrés au lieu d'attendre le suivant. L'image peut porter
			// un instant antérieur au jour qui vient d'être montré : la fraction ne descend pas sous zéro
			this.decor.glide((this.player === null) ? 0 : Math.min(1, Math.max(0, (time - this.dayAt) * this.speed.factor / MapScene.PLAY_DAY)));
			this.sparks.frame();
			this.bubbles.frame(moving);
			this.drawMarker(time);
			this.films.frame(time);

			// Un déplacement s'arrête avant que le bord du pré n'entre dans la vue
			const room = Math.max(0, ground.scale.x - reach(this.camera.zoom));
			const shift = new THREE.Vector3(this.controls.target.x - ground.position.x, 0, this.controls.target.z - ground.position.z);
			const away = shift.length();

			if(away > room) {
				const back = shift.multiplyScalar(room / away - 1);
				this.controls.target.add(back);
				this.camera.position.add(back);
			}

			const pixels = canvas.clientHeight * this.camera.zoom / (2 * MapScene.FRUSTUM);
			const labelled = (pixels >= MapScene.LABEL_PIXELS);

			// Pendant la lecture, les noms de planche et les pastilles se retirent : l'œil reste sur les cultures
			const named = (pixels >= MapScene.BED_LABELS_FROM && this.player === null);

			if(this.plotLabels.length > 0 && this.plotLabels[0].visible !== labelled) {
				this.plotLabels.forEach(label => {
					label.visible = labelled;
				});
				this.declutterAt = 0;
			}

			// Le corps change la taille de chaque nom de planche : elle se remesure, et le tri des noms repart
			if(this.bedsSmall !== (pixels < MapScene.BED_LABELS_FULL)) {
				this.bedsSmall = !this.bedsSmall;
				this.container.classList.toggle('map-scene-beds-small', this.bedsSmall);
				this.bedLabels.forEach(label => {
					label.userData.size = null;
				});
				this.declutterAt = 0;
			}

			if(this.bedLabels.length > 0 && this.bedLabels[0].visible !== named) {
				this.bedLabels.forEach(label => {
					label.visible = named;
				});
				this.declutterAt = 0;
			}

			this.badges.frame(pixels, labelled && this.player === null);

			for(const band of this.folds) {
				band.scale.x = MapScene.FOLD_PX / pixels;
			}

			this.drawOutline(MapScene.OUTLINE_PX / pixels);

			// Le survol se pose après MapControls, qui relit la caméra à l'image suivante sans rien garder d'elle
			this.ambience.fly(time, this.camera, this.controls);
			this.ambience.frame(time);
			this.boards?.frame(this.camera, this.controls);
			this.insects.frame(time, this.shown, this.week, this.camera, this.controls);
			this.antics.frame(time, this.shown, this.week, this.player !== null, this.camera, this.controls);
			this.fauna.frame(time, this.shown, this.week, this.camera, this.controls);
			this.ruins.frame(time);

			// La secousse d'une bombe ou d'un séisme ne vaut que pour cette image : MapControls ne la relit pas
			this.camera.position.add(this.ruins.shake);
			this.renderer.render(this.scene, this.camera);
			this.record.frame();
			this.labels.render(this.scene, this.camera);
			this.camera.position.sub(this.ruins.shake);

			this.declutter(time);

		});

	}

	/**
	 * La part de la hauteur du canevas que la frise laisse voir. Posée sur le canevas, elle en couvre le bas, et ce qui est cadré
	 * tient au-dessus d'elle ; sous 768 px, elle passe sous le canevas.
	 */
	/**
	 * Les rectangles que la rangée des réglages et la frise posent sur le canevas, en pixels du canevas. Sous la scène, la frise ne
	 * couvre rien : son rectangle tombe hors du canevas, et aucun nom ne le recoupe.
	 */
	measureCovers() {

		const origin = this.renderer.domElement.getBoundingClientRect();

		return [...this.container.qsa('.map-scene-zone, .map-scene-screen, .map-scene-timeline')].map(element => {
			const rect = element.getBoundingClientRect();
			return [rect.left - origin.left, rect.top - origin.top, rect.right - origin.left, rect.bottom - origin.top];
		});

	}

	usable() {

		// Le bas de la scène est couvert par la frise ou, pendant la pose, par la feuille du décor qui la remplace. Celle des deux
		// qui est masquée n'a pas de place à mesurer, et un lecteur sans droit de pose n'a pas de feuille
		const cover = [this.container.qs('.map-scene-timeline'), this.container.qs('[data-scene-decor-sheet]')].find(node => node !== null && node.classList.contains('hide') === false);
		const height = this.renderer.domElement.clientHeight;
		const covered = (cover !== undefined && getComputedStyle(cover).position === 'absolute') ? Math.min(height * (1 - MapScene.USABLE_LEAST), this.renderer.domElement.getBoundingClientRect().bottom - cover.getBoundingClientRect().top) : 0;

		return (height - covered) / height;

	}

	/**
	 * Le nom de chaque planche, en bout de planche. Une planche sur deux d'une même rangée garde une place de repli, plus loin dans
	 * son axe, que le tri des noms prend en petit corps quand deux noms voisins se touchent.
	 */
	drawBedLabels(THREE, addons, geometry) {

		const rows = new Map();

		for(const bed of geometry.beds) {

			if(bed.name === null) {
				continue;
			}

			const element = document.createElement('div');
			element.className = 'map-scene-label map-scene-label-bed';
			element.textContent = bed.name;

			const label = new addons.CSS2DObject(element);
			const end = ScenePlanGeometry.add(bed.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(bed.u, bed.length + .6), ScenePlanGeometry.mul(bed.w, bed.width / 2)));
			label.position.set(end.x, .4 + geometry.height(end.x, end.n), -end.n);
			label.visible = false;
			label.userData = { bed, size: null, near: label.position.clone(), far: null };
			this.scene.add(label);
			this.bedLabels.push(label);

			const key = Math.round(ScenePlanGeometry.dot(bed.origin, bed.u) / 2) +'|'+ bed.u.x.toFixed(2) +'|'+ bed.u.n.toFixed(2);

			if(rows.has(key) === false) {
				rows.set(key, []);
			}

			rows.get(key).push(label);

		}

		for(const row of rows.values()) {

			row.sort((a, b) => ScenePlanGeometry.dot(a.userData.bed.origin, a.userData.bed.w) - ScenePlanGeometry.dot(b.userData.bed.origin, b.userData.bed.w));

			row.forEach((label, index) => {

				if(index % 2 === 1) {
					const u = label.userData.bed.u;
					label.userData.far = label.position.clone().add(new THREE.Vector3(u.x, 0, -u.n).multiplyScalar(MapScene.BED_LABEL_STEP));
				}

			});

		}

	}

	/**
	 * Deux régimes. Un nom de jardin s'efface devant un autre nom de jardin, et devant les noms de planche qu'il recouvrirait. Les
	 * noms de planche paraissent tous, même quand ils se touchent, une suite de numéros qui se recouvrent se lisant mieux qu'une
	 * suite trouée : en petit corps, ils passent en quinconce dès que deux voisins se recouvrent, et un nom ne se cache que sous
	 * la rangée des réglages ou la frise.
	 *
	 * Les tailles se lisent en une passe quand le contenu ou le corps change, les positions se projettent : aucune lecture de mise
	 * en page à chaque passe, et toutes les écritures viennent après.
	 */
	declutter(now) {

		if(now - this.declutterAt < MapScene.DECLUTTER_EVERY) {
			return;
		}

		this.declutterAt = now;

		const plots = this.plotLabels.filter(label => label.visible);
		const beds = this.bedLabels.filter(label => label.visible);

		const unsized = [...plots, ...beds].filter(label => label.userData.size === null);
		const sizes = unsized.map(label => [label.element.offsetWidth, label.element.offsetHeight]);

		unsized.forEach((label, index) => {
			label.userData.size = sizes[index];
		});

		// La taille posée au dernier resize, et non celle du canevas : les étiquettes viennent d'être déplacées, la relire recalculerait
		// la mise en page
		const { width, height } = this.renderer.getSize(new this.crops.THREE.Vector2());
		const projected = new this.crops.THREE.Vector3();

		const box = (label, air) => {
			label.getWorldPosition(projected).project(this.camera);
			const [w, h] = label.userData.size;
			const x = (projected.x + 1) / 2 * width - w * label.center.x;
			const y = (1 - projected.y) / 2 * height - h * label.center.y;
			return [x - air, y - air, x + w + air, y + h + air];
		};

		const onScreen = b => (b[2] > 0 && b[0] < width && b[3] > 0 && b[1] < height);
		const inFrame = b => (b[0] >= 0 && b[2] <= width && b[1] >= 0 && b[3] <= height);
		const overlap = (a, b) => (a[0] < b[2] && a[2] > b[0] && a[1] < b[3] && a[3] > b[1]);

		// Un nom passé sous la rangée des réglages ou la frise se lirait à moitié, et un nom de jardin posé sur une pastille cache la
		// culture qu'elle montre
		const covered = b => this.covers.some(cover => overlap(b, cover));
		const badges = this.badges.boxes(this.camera, width, height);

		const spread = level => {

			const boxes = [];

			for(const label of beds) {

				label.position.copy((level > 0 && label.userData.far !== null) ? label.userData.far : label.userData.near);

				const b = box(label, 0);

				if(onScreen(b) && covered(b) === false) {
					boxes.push(b);
				}

			}

			return boxes.every((a, i) => boxes.every((b, j) => j <= i || overlap(a, b) === false));

		};

		// Le quinconce reste posé quand il se recouvre encore
		if(this.bedsSmall) {
			[0, 1].find(spread);
		} else {
			spread(0);
		}

		const hide = new Set();
		const keptPlots = [];
		const keptBeds = [];

		// Un nom de jardin garde deux pixels d'air, un nom de planche aucun
		for(const label of plots) {

			const b = box(label, 2);

			// Un écriteau d'abri entier ou aucun : coupé par le bord du canevas, il se lirait comme un autre nom
			if(inFrame(b) === false || covered(b) || badges.some(badge => overlap(b, badge)) || keptPlots.some(kept => overlap(b, kept))) {
				hide.add(label);
			} else {
				keptPlots.push(b);
			}

		}

		for(const label of beds) {

			const b = box(label, 0);

			if(covered(b)) {
				hide.add(label);
			} else if(onScreen(b)) {
				keptBeds.push(b);
			}

		}

		const margin = MapScene.LABEL_BED_MARGIN;

		for(const label of plots) {

			if(hide.has(label) === false && keptBeds.some(kept => overlap(box(label, margin), kept))) {
				hide.add(label);
			}

		}

		for(const label of [...plots, ...beds]) {

			const visibility = hide.has(label) ? 'hidden' : '';

			if(label.element.style.visibility !== visibility) {
				label.element.style.visibility = visibility;
			}

		}

		// Les pastilles se rangent de la plus lointaine à la plus proche au même rythme : la vue a pu tourner
		if(this.badges.mesh.visible) {
			this.badges.write(this.camera);
		}

	}

	/**
	 * Le zoom qui fait tenir une emprise vue à l'écran, en mètres, dans la part visible du canevas.
	 */
	fit(width, height, usable) {
		return Math.min((this.camera.right - this.camera.left) / Math.max(width, 10), (this.camera.top - this.camera.bottom) * usable / Math.max(height, 10)) * MapScene.FRAME_FILL;
	}

	/**
	 * L'emprise de la structure, son centre et son rayon en mètres du plan, et sa taille à l'écran, lue dans l'orientation de départ de la caméra : elle ne change pas quand la vue tourne,
	 * et le plancher du zoom qui s'en déduit ne saute donc pas.
	 */
	span(THREE, points) {

		const rest = new THREE.Object3D();
		rest.position.set(300, 300, 300);
		rest.lookAt(0, 0, 0);
		rest.updateMatrixWorld();

		const inverse = rest.matrixWorld.clone().invert();
		const seen = points.map(p => new THREE.Vector3(p.x, this.geometry.height(p.x, p.n), -p.n).applyMatrix4(inverse));

		const xs = points.map(p => p.x);
		const ns = points.map(p => p.n);
		const x = (Math.min(...xs) + Math.max(...xs)) / 2;
		const n = (Math.min(...ns) + Math.max(...ns)) / 2;

		return {
			x,
			n,
			radius: Math.max(10, ...points.map(p => Math.hypot(p.x - x, p.n - n))),
			width: Math.max(10, Math.max(...seen.map(p => p.x)) - Math.min(...seen.map(p => p.x))),
			height: Math.max(10, Math.max(...seen.map(p => p.y)) - Math.min(...seen.map(p => p.y)))
		};

	}

	/**
	 * Le choix de la zone, le curseur des semaines et la lecture de la saison. Rend les points de la vue d'ouverture.
	 */
	bind(THREE, plan, geometry, everything) {

		const zone = this.container.qs('[data-scene-zone]');
		const week = this.container.qs('[data-scene-week]');
		const play = this.container.qs('[data-scene-play]');
		const pause = this.container.qs('[data-scene-pause]');

		// Chaque dessin lit la vitesse, pour l'équipe et la lumière : elle se monte avant le premier. Changée pendant la lecture,
		// elle en reprend le battement
		this.speed = new SceneSpeed(this.container, () => {
			if(this.player !== null) {
				pace();
			}
		});

		this.week = Number(week.value);

		// Le coin le plus vivant du jour s'ouvre avec sa zone choisie : la clé ne nomme que ce qu'il montre
		const lively = this.crops.lively(this.date(plan));

		// Une zone tracée seulement : un jardin tracé peut appartenir à une zone qui ne l'est pas, et la liste ne la porte pas
		if(lively !== null && zone?.querySelector('option[value="'+ lively.zone +'"]')) {
			zone.value = String(lively.zone);
			this.zone = lively.zone;
		}

		zone?.addEventListener('change', () => {

			this.zone = (zone.value === '') ? null : Number(zone.value);
			this.fitLegend();
			this.show(plan);
			this.frameChosen(THREE);

		});

		week.addEventListener('input', () => {

			// La semaine se lit avant l'arrêt, qui repose le curseur sur la semaine jouée
			const chosen = Math.round(Number(week.value));

			this.stopPlaying(plan, play, pause, false);
			this.goWeek(plan, chosen);

		});

		this.bindTimeline(plan, play, pause);

		this.record = new MapSceneRecord(this.container, this.renderer.domElement, this.speed, this.months, this.texts.video.replace('{season}', plan.season), {
			rewind: () => {
				this.day = 0;
				this.goWeek(plan, Number(week.min));
			},
			play: () => play.click(),
			stop: () => this.stopPlaying(plan, play, pause),
			// Le mois se lit sur le jeudi de la semaine jouée : la saison commence et finit dans les années voisines
			at: () => ({
				position: (this.week + this.day / 7 - this.months.min) / this.months.span,
				month: Number(MapScene.addDays(this.crops.stage.monday(plan.season, this.week), 3).slice(5, 7)) - 1
			})
		});
		this.disposables.push(this.record);

		this.bindPhoto(plan, play, pause);

		// Un appui sur la clé arrête la lecture sans redessiner : une clé refaite sous le doigt perdrait le clic, qui redessine
		this.container.qs('[data-scene-legend]').addEventListener('pointerdown', () => this.stopPlaying(plan, play, pause, false));

		// Sur un téléphone, la clé tient sur une ligne qui défile de côté (scene.css)
		this.legendScroll = new MapSceneScroll({ legend: this.container.qs('[data-scene-legend]') }, this.container.qsa('[data-scene-scroll="legend"]'));
		this.disposables.push(this.legendScroll);

		const step = () => {

			if(this.day < 6) {
				this.day++;
			} else if(this.week < Number(week.max)) {
				this.week++;
				this.day = 0;
			} else {
				this.record.end();
				this.stopPlaying(plan, play, pause);
				return;
			}

			week.value = this.week + this.day / 7;
			this.dayAt = performance.now();
			this.show(plan);

		};

		// Un battement d'horloge ne change pas de cadence : une vitesse choisie pendant la lecture en lance un autre, à partir du
		// jour montré. `setInterval()` tronque son délai à la milliseconde : arrondi ici, un pas entier que la division par le
		// facteur rend à un rien près (51 ms en 50,999…) ne perd pas une milliseconde par jour
		const pace = () => {
			clearInterval(this.player);
			this.player = setInterval(step, Math.round(MapScene.PLAY_DAY / this.speed.factor));
		};

		play.addEventListener('click', () => {

			// Une lecture lancée sur le dernier jour de la saison repart du début
			if(this.week >= Number(week.max) && this.day >= 6) {
				this.week = Number(week.min);
				this.day = 0;
			}

			MapScene.swap([play], [pause]);

			// Le curseur glisse d'un jour à l'autre au lieu de sauter de semaine en semaine
			week.step = 'any';

			pace();

			this.dayAt = performance.now();
			this.show(plan);

		});

		pause.addEventListener('click', () => this.stopPlaying(plan, play, pause));

		// Le plein écran emporte toute la scène, sélecteur et frise compris. Échap en sort sans passer par le bouton : l'icône bascule
		// sur l'événement du navigateur, pas sur le clic. Un navigateur qui ne le permet pas, Safari sur iPhone, ne montre pas le bouton
		const enter = this.container.qs('[data-scene-fullscreen]');
		const leave = this.container.qs('[data-scene-fullscreen-exit]');

		if(document.fullscreenEnabled) {

			enter.classList.remove('hide');
			// Un refus du navigateur laisse la scène telle quelle, ce qui suffit à le dire : il n'y a rien à afficher de plus
			enter.addEventListener('click', () => this.container.requestFullscreen().catch(() => {}));
			leave.addEventListener('click', () => document.exitFullscreen().catch(() => {}));

			// Le conteneur survit à la scène quand le relief la remonte, qui peut l'être en plein écran : l'icône se pose aussi au
			// montage, et l'écouteur se retire à la fermeture
			this.onFullscreen = () => {
				const full = (document.fullscreenElement === this.container);
				MapScene.swap([full ? enter : leave], [full ? leave : enter]);
			};

			this.container.addEventListener('fullscreenchange', this.onFullscreen);
			this.onFullscreen();

		}

		// Les pastilles se cachent par la ligne « Icônes des séries » de la carte des calques, toute la ligne valant interrupteur. Une navigation
		// privée ou des données de site bloquées font lever l'accès au stockage : les pastilles restent alors affichées, ce qui est
		// l'état par défaut, sans que le choix soit retenu
		const series = this.container.qs('[data-scene-series]');
		const badges = series.qs('.field-switch');

		try {
			this.badges.on = (localStorage.getItem(MapScene.BADGES_HIDDEN_KEY) !== '1');
		} catch(e) {
		}

		badges.classList.toggle('field-switch-on', this.badges.on);
		badges.classList.toggle('field-switch-off', this.badges.on === false);

		series.addEventListener('click', () => {

			this.badges.on = (this.badges.on === false);
			badges.classList.toggle('field-switch-on', this.badges.on);
			badges.classList.toggle('field-switch-off', this.badges.on === false);

			try {
				if(this.badges.on) {
					localStorage.removeItem(MapScene.BADGES_HIDDEN_KEY);
				} else {
					localStorage.setItem(MapScene.BADGES_HIDDEN_KEY, '1');
				}
			} catch(e) {
			}

		});

		// Le relief se pose au montage de la scène : l'allumer ou l'éteindre la remonte (toggleRelief()). La mention de sa source
		// le suit
		this.container.qs('[data-scene-relief]', row => {

			const toggle = row.qs('.field-switch');
			const shown = MapSceneRelief.shown(this.container);

			toggle.classList.toggle('field-switch-on', shown);
			toggle.classList.toggle('field-switch-off', shown === false);

			row.addEventListener('click', () => this.toggleRelief(plan, shown === false));

		});

		this.container.qs('[data-scene-attribution]', attribution => attribution.classList.toggle('hide', this.geometry.relief === null));

		this.show(plan);

		return (lively !== null) ? lively.points : everything;

	}

	stopPlaying(plan, play, pause, redraw = true) {

		// Un film ne vaut que pour la saison entière : tout arrêt de la Lecture avant son dernier jour le jette
		this.record.cancel();

		if(this.player === null) {
			return;
		}

		clearInterval(this.player);
		this.player = null;

		MapScene.swap([pause], [play]);

		const week = this.container.qs('[data-scene-week]');
		week.step = 1;
		week.value = this.week;

		// Les séries en train d'entrer reprennent leur taille du jour
		if(redraw) {
			this.show(plan);
		}

	}

	date(plan) {
		return MapScene.addDays(this.crops.stage.monday(plan.season, this.week), this.day);
	}

	/**
	 * La bulle de la frise, à la semaine et au jour montrés. Pendant la lecture le curseur avance au jour, sans attendre le lundi.
	 */
	showWeek(plan, date, playing) {

		const week = this.container.qs('[data-scene-week]');
		const position = this.week + (playing ? this.day / 7 : 0);
		const span = Math.max(1, Number(week.max) - Number(week.min));

		week.parentElement.style.setProperty('--p', (position - Number(week.min)) / span);

		this.container.qs('[data-scene-date]').textContent = this.weekText(plan, date) +' · '+ MapScene.dayText(date, { day: 'numeric', month: 'short' });

	}

	// Les dates de la scène sont des jours UTC : la langue de la page les écrit, comme les graphiques du produit (chart.js)
	static dayText(date, options) {
		return new Date(Date.parse(date)).toLocaleDateString(document.documentElement.lang || 'fr', { timeZone: 'UTC', ...options });
	}

	/**
	 * « s41 » pour une date de la saison, « s2, 2027 » pour une date d'une autre année.
	 */
	weekText(plan, date) {
		return SeriesSheet.weekText(this.crops.stage, this.texts, plan.season, date);
	}

	/**
	 * Les cultures en place au jour choisi, la lumière de la saison, le liseré de l'espèce choisie et la clé des couleurs.
	 */
	show(plan) {

		const date = this.date(plan);
		const playing = (this.player !== null);
		const selected = (this.selected?.kind === 'series') ? this.selected.id : null;
		const { shown, chosen, badges, spans, segments, marker } = this.crops.render(date, this.zone, this.species, playing, selected);

		this.spans = spans;
		this.markerAt = (this.selected?.kind === 'bed') ? this.bedCenter(this.selected.id) : marker;
		this.burst(segments, playing);

		// Une espèce qui sort de la clé, récoltée ou absente de la zone choisie, est laissée : elle ne se rallume pas seule à son retour
		if(this.species !== null && shown.has(this.species) === false) {
			this.species = null;
			chosen.outline = [];
			chosen.framed = [];
		}

		this.crops.face(this.camera);
		this.badges.show(badges, this.camera);
		const monday = this.crops.stage.monday(plan.season, this.week);
		this.crew.staff(date, [0, 1, 2, 3, 4, 5, 6].map(day => MapScene.addDays(monday, day)), playing, this.speed.factor);
		this.light(date);
		this.setOutline(chosen);

		// Le soleil change de hauteur chaque jour : l'ombre se refait à chaque jour montré, lecture comprise
		this.renderer.shadowMap.needsUpdate = true;

		this.showWeek(plan, date, playing);
		this.showDays(plan);
		this.showBubbles(plan, playing);
		this.sheet.show(this.selected, date);

		this.legend(shown);

		return chosen;

	}

	/**
	 * La clé des couleurs, une espèce par bouton : choisie, l'espèce se cadre et ses planches se cernent. La lecture redessine chaque
	 * jour, la clé ne se refait que si ses espèces changent.
	 */
	legend(shown) {

		const entries = [...shown].sort(([a], [b]) => a.localeCompare(b, 'fr'));
		const key = entries.join('|');
		const legend = this.container.qs('[data-scene-legend]');

		// Choisir une espèce ne refait pas la clé : l'entrée cliquée garde le focus, et la clé se parcourt au clavier
		if(key === this.legendKey) {
			legend.qsa('[data-species]', item => item.classList.toggle('selected', item.dataset.species === this.species));
			return;
		}

		this.legendKey = key;

		legend.replaceChildren(...entries.map(([name, color]) => {

			const item = MapScene.legendItem(name, color);
			item.classList.toggle('selected', name === this.species);

			return item;

		}));

		this.legendScroll.shade();

	}

	static legendItem(name, color) {

		const item = document.createElement('button');
		item.type = 'button';
		item.className = 'util-legend-item map-scene-legend-item';
		item.dataset.species = name;

		const mark = document.createElement('i');
		mark.className = 'util-legend-mark';
		mark.style.backgroundColor = color;

		item.append(mark, name);

		return item;

	}

	/**
	 * La hauteur de la clé est celle que prendraient toutes les espèces de la saison dans la zone choisie, que scene.css borne à deux
	 * rangées : la lecture ne la fait pas changer sous le doigt, et une saison qui tient sur une rangée ne laisse pas de vide sous
	 * elle. Elle se reprend quand la largeur de la clé ou la zone change, puisque le nombre de rangées en dépend. Une copie de la clé
	 * hors du flux reçoit ces espèces : une seule mise en page se lit, entre sa pose et son retrait. Sous 768 px, la clé est une
	 * rangée qui défile et scene.css ne lit pas cette hauteur.
	 */
	fitLegend() {

		const legend = this.container.qs('[data-scene-legend]');
		const width = legend.clientWidth;
		const fitted = width +'|'+ this.zone;

		if(fitted === this.legendFitted) {
			return;
		}

		this.legendFitted = fitted;

		const probe = legend.cloneNode(false);
		probe.removeAttribute('data-scene-legend');
		probe.style.cssText = 'position: absolute; visibility: hidden; height: auto; width: '+ width +'px';
		probe.append(...[...this.crops.seasonSpecies(this.zone)].map(([name, color]) => MapScene.legendItem(name, color)));

		legend.after(probe);
		const height = probe.offsetHeight;
		probe.remove();

		legend.style.setProperty('--map-scene-legend-height', height +'px');

	}

	/**
	 * Un clic sur une espèce de la clé la choisit, un second clic sur elle ou un clic sur la scène la laisse. Un glissé qui déplace la
	 * vue n'est pas un clic.
	 */
	pick(canvas, plan, geometry) {

		const THREE = this.crops.THREE;

		this.container.qs('[data-scene-legend]').addEventListener('click', event => {

			const item = event.target.closest('[data-species]');

			if(item === null) {
				this.show(plan);
				return;
			}

			this.species = (this.species === item.dataset.species) ? null : item.dataset.species;

			const chosen = this.show(plan);

			// La vue recule jusqu'à montrer toutes les planches de l'espèce, sans jamais avancer : celles qui tiennent déjà à
			// l'écran se recentrent au zoom choisi, et le liseré, d'épaisseur fixe à l'écran, les désigne à toute distance
			if(this.species !== null) {
				this.frame(THREE, chosen.framed, false);
			}

		});

		let down = null;

		canvas.addEventListener('pointerdown', event => {
			down = { x: event.clientX, y: event.clientY, at: performance.now() };
		});

		// Le décor en pose a ses propres clics, et le mode photo n'en a aucun
		canvas.addEventListener('pointerup', event => {

			const click = (down !== null && this.decor.active !== true && this.photo === false && Math.hypot(event.clientX - down.x, event.clientY - down.y) <= MapScene.CLICK_PX && performance.now() - down.at < MapScene.CLICK_MS);

			down = null;

			if(click) {
				this.species = null;
				this.choose(plan, this.target(this.ground(canvas, event), geometry));
			}

		});

	}

	/**
	 * Le point du sol des planches sous le pointeur, en coordonnées du plan. Le sol suffit : un plant posé sur sa planche ne déborde
	 * pas assez pour qu'un clic sur lui tombe sur une autre.
	 */
	ground(canvas, event) {

		const THREE = this.crops.THREE;
		const box = canvas.getBoundingClientRect();
		const raycaster = new THREE.Raycaster();

		raycaster.setFromCamera(new THREE.Vector2((event.clientX - box.left) / box.width * 2 - 1, -(event.clientY - box.top) / box.height * 2 + 1), this.camera);

		if(this.geometry.relief !== null) {
			return this.geometry.relief.onRelief(raycaster.ray, MapScene.BED_BASE);
		}

		const point = raycaster.ray.intersectPlane(new THREE.Plane(new THREE.Vector3(0, 1, 0), -MapScene.BED_BASE), new THREE.Vector3());

		return (point === null) ? null : { x: point.x, n: -point.z };

	}

	/**
	 * Sur le relief, la cible de la vue se pose sur le sol qu'elle regarde. MapControls la glisse dans le plan de sa propre hauteur,
	 * et le zoom au curseur la recoupe sur ce plan : elle passerait au-dessus d'un creux ou sous une butte, et la vue tournerait
	 * autour d'un point en l'air. La caméra glisse avec elle le long du regard, ce qui ne change rien à l'image d'une vue
	 * orthographique.
	 */
	seat() {

		if(this.geometry.relief === null) {
			return;
		}

		const target = this.controls.target;
		const look = target.clone().sub(this.camera.position).normalize();
		const seated = this.geometry.relief.onRelief({ origin: target, direction: look }, 0);

		if(seated === null) {
			return;
		}

		const shift = look.set(seated.x - target.x, seated.y - target.y, -seated.n - target.z);

		// Une cible déjà posée ne bouge plus : sans ce seuil, chaque recalage relancerait `change` d'une fraction de millimètre
		if(shift.lengthSq() < MapScene.SEAT_SLACK ** 2) {
			return;
		}

		target.add(shift);
		this.camera.position.add(shift);

	}

	/**
	 * Ce que le point touche : la série qui tient cet endroit de la planche, sinon la planche, sinon rien. Un jardin sans planche
	 * dessinée se touche par son contour.
	 */
	target(p, geometry) {

		if(p === null) {
			return null;
		}

		const G = ScenePlanGeometry;

		for(const r of geometry.beds) {

			const along = G.dot(G.sub(p, r.origin), r.u);
			const across = G.dot(G.sub(p, r.origin), r.w);

			if(along >= 0 && along <= r.length && across >= 0 && across <= r.width) {

				const span = (this.spans.get(r.id) ?? []).find(span => along >= span.from && along <= span.to) ?? null;

				return (span !== null) ? { kind: 'series', id: span.series, bed: r.id } : { kind: 'bed', id: r.id, bed: r.id };

			}

		}

		for(const bed of this.crops.beds.values()) {

			if(bed.whole !== null && G.inside(p, bed.whole.points)) {

				const span = this.spans.get(bed.id)?.[0] ?? null;

				return (span !== null) ? { kind: 'series', id: span.series, bed: bed.id } : { kind: 'bed', id: bed.id, bed: bed.id };

			}

		}

		return null;

	}

	/**
	 * Ce qu'un appui long vise, en cible de MapSceneRuins : le plus proche des éléments du décor, des lettres de l'enseigne, des
	 * pancartes et des abris sous le pointeur, sinon la planche dessinée sous lui. Les paysans et les bêtes ne sont pas des cibles,
	 * le rayon les traverse. Un abri levé d'un toucher laisse voir ce qu'il couvre : il se traverse, et c'est la planche dessous qu'on
	 * vise. Celui que la souris lève reste une cible, puisque la souris le survole forcément avant d'appuyer dessus. La cible rendue
	 * n'est pas encore mesurée : MapSceneHold ne la mesure qu'à l'éclat de la cible, un simple clic n'en paie pas le prix.
	 */
	aimRuin(canvas, event, plan, geometry) {

		const THREE = this.crops.THREE;
		const box = canvas.getBoundingClientRect();
		const raycaster = new THREE.Raycaster();

		raycaster.setFromCamera(new THREE.Vector2((event.clientX - box.left) / box.width * 2 - 1, -(event.clientY - box.top) / box.height * 2 + 1), this.camera);

		const candidates = [];

		for(const e of this.decor.elements) {
			if(e.root?.visible === true) {
				candidates.push([e.root.children[0], () => this.decorRuin(e)]);
			}
		}

		if(this.sign?.group.visible === true) {
			candidates.push([this.sign.group, () => this.signRuin()]);
		}

		for(const { board } of this.boards?.boards ?? []) {
			if(board.visible) {
				candidates.push([board, () => this.boardRuin(board)]);
			}
		}

		for(const shelter of this.films.shelters) {
			if(shelter.meshes[0]?.visible === true && shelter !== this.films.touched) {
				candidates.push([shelter.meshes[0], () => this.shelterRuin(shelter)]);
			}
		}

		let nearest = null;

		for(const [object, make] of candidates) {
			const hit = raycaster.intersectObject(object, true)[0] ?? null;
			if(hit !== null && (nearest === null || hit.distance < nearest.distance)) {
				nearest = { distance: hit.distance, make };
			}
		}

		// Ce qui tombe d'une cible se pose sur le sol sous le point où il tombe, et sur la terre d'une planche
		const floor = bed => (x, z) => (bed ? MapScene.BED_BASE : 0) + geometry.height(x, -z);

		if(nearest !== null) {
			return { ...nearest.make(), floor: floor(false) };
		}

		// Une planche est fine et l'allée qui la borde ne vise rien : à défaut de planche sous le point, la plus proche à un doigt
		// près, en cercle autour de lui
		const reach = (event.pointerType === 'touch') ? 24 : 12;
		let touched = this.target(this.ground(canvas, event), geometry);

		for(let i = 0; i < 8 && touched === null; i++) {
			const angle = i / 8 * Math.PI * 2;
			touched = this.target(this.ground(canvas, { clientX: event.clientX + Math.cos(angle) * reach, clientY: event.clientY + Math.sin(angle) * reach }), geometry);
		}

		const r = (touched === null) ? null : (geometry.beds.find(bed => bed.id === touched.bed) ?? null);

		return (r === null || this.ruins.busy('bed:'+ r.id)) ? null : { ...this.bedRuin(plan, r), floor: floor(true) };

	}

	// Le sens d'un objet dans le monde autour de la verticale, comme `rotation.y`
	static yaw(object) {
		object.updateMatrixWorld(true);
		const m = object.matrixWorld.elements;
		return Math.atan2(-m[2], m[0]);
	}

	decorRuin(e) {

		const decor = this.decor;

		return {
			key: e,
			base: e.root.position.y,
			least: .6,
			angle: MapScene.yaw(e.root),
			// La copie garde la saison de l'original : un arbre d'automne brûle roux, une haie en fleurs reste fleurie
			look: { material: decor.material, depth: decor.depthMaterial, name: 'decor', attributes: { season: 3, anchor: 3 }, points: ['anchor'] },
			parts: () => [MapSceneRuins.part(e.root.children[0])],
			hide: () => decor.mask(e, true),
			show: () => decor.mask(e, false),
			shown: () => decor.layers.isShown(e.layer)
		};

	}

	signRuin() {

		const group = this.sign.group;
		const meshes = group.children.filter(child => child.isMesh);

		return {
			key: this.sign,
			base: group.position.y,
			least: .6,
			angle: MapScene.yaw(group),
			parts: () => meshes.map(mesh => MapSceneRuins.part(mesh)),
			hide: () => {
				group.visible = false;
			},
			show: () => {
				group.visible = true;
			}
		};

	}

	boardRuin(board) {

		const THREE = this.crops.THREE;

		return {
			key: board,
			base: board.position.y,
			least: .6,
			angle: MapScene.yaw(board),
			// Une pancarte tombe d'une pièce : en morceaux, son nom se lirait en miettes
			pieces: 1,
			parts: () => [{ ...MapSceneRuins.part(board), color: [this.boards.wood.color.clone(), new THREE.Color(MapSceneBoards.FACE)] }],
			hide: () => {
				board.visible = false;
			},
			show: () => {
				board.visible = true;
			}
		};

	}

	shelterRuin(shelter) {

		const r = shelter.rectangle;
		// Les arceaux sont des traits, sans triangle à copier : ils partent avec la bâche
		const meshes = shelter.pieces.filter(piece => piece.isMesh);

		return {
			key: shelter,
			base: shelter.base,
			least: .6,
			angle: Math.atan2(r.u.n, r.u.x),
			opacity: shelter.rest,
			parts: () => meshes.map(mesh => MapSceneRuins.part(mesh)),
			hide: () => shelter.pieces.forEach(piece => {
				piece.visible = false;
			}),
			show: () => shelter.pieces.forEach(piece => {
				piece.visible = true;
			})
		};

	}

	/**
	 * Une planche dessinée en cible : sa terre et tout ce qui pousse dessus. Masquée, sa terre s'efface de la terre fusionnée et ses
	 * cultures ne se posent plus, jusqu'à ce que show() les rende.
	 */
	bedRuin(plan, r) {

		const THREE = this.crops.THREE;
		const middle = ScenePlanGeometry.add(r.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(r.u, r.length / 2), ScenePlanGeometry.mul(r.w, r.width / 2)));

		return {
			key: 'bed:'+ r.id,
			base: MapScene.BED_BASE + this.geometry.height(middle.x, middle.n),
			least: 1.2,
			bed: true,
			angle: Math.atan2(r.u.n, r.u.x),
			// La terre copiée garde son bord grignoté et ses aplats : la copie prend la place de l'original sans qu'on la voie
			look: { material: this.materials.soil, depth: null, name: 'bed', attributes: { bedEdge: 3, bedSize: 2 }, points: [] },
			parts: () => {
				const slab = this.field.slabs.get(r.id);
				const soil = MapScene.bed(THREE, this.geometry, r);
				return [
					{ geometry: (this.geometry.relief === null) ? soil : MapSceneField.drape(THREE, this.geometry, soil), matrix: new THREE.Matrix4().makeTranslation(0, MapScene.BED_BASE, 0), color: slab.mesh.material.color.clone(), ground: true },
					...this.crops.parts(r)
				];
			},
			hide: () => {
				this.slab(r.id, false);
				this.crops.ruined.add(r.id);
				this.show(plan);
			},
			show: () => {
				this.slab(r.id, true);
				this.crops.ruined.delete(r.id);
				this.show(plan);
			}
		};

	}

	slab(id, shown) {

		const slab = this.field.slabs.get(id);
		const position = slab.mesh.geometry.attributes.position;
		const range = position.array.subarray(slab.start * 3, (slab.start + slab.count) * 3);

		if(shown) {
			range.set(slab.saved);
			slab.saved = null;
		} else {
			slab.saved = range.slice();
			range.fill(0);
		}

		position.needsUpdate = true;

	}

	choose(plan, target) {
		this.selected = target;
		this.show(plan);
	}

	bedCenter(id) {

		const G = ScenePlanGeometry;
		const r = this.geometry.beds.find(bed => bed.id === id) ?? this.crops.beds.get(id)?.rectangle ?? null;

		return (r === null) ? null : G.add(G.add(r.origin, G.mul(r.u, r.length / 2)), G.mul(r.w, r.width / 2));

	}

	drawMarker(time) {

		const at = this.markerAt;

		this.marker.visible = (at !== null && this.photo === false);

		if(this.marker.visible === false) {
			return;
		}

		const size = Math.max(1, (this.camera.top - this.camera.bottom) / this.camera.zoom / MapScene.MARKER_SCREEN);

		this.marker.scale.setScalar(size);
		this.marker.position.set(at.x, MapScene.BED_BASE + MapScene.MARKER_HEIGHT + (1 + Math.sin(time / 1000 * 3)) * .4 * size + this.geometry.height(at.x, at.n), -at.n);
		this.marker.rotation.y = time / 1000 * 1.8;

	}

	/**
	 * Les récoltes rangées par lundi pour les bulles, et le point d'où part la bulle de chaque série : le milieu de sa
	 * première planche dessinée.
	 */
	prepareHarvests(plan) {

		const G = ScenePlanGeometry;
		const stage = this.crops.stage;

		this.harvestWeeks = new Map();

		for(const harvests of SeriesSheet.group(plan.harvests, plan.columns.harvests).values()) {
			for(const harvest of harvests) {
				const { year, week } = stage.week(harvest.date);
				MapScene.push(this.harvestWeeks, stage.monday(year, week), harvest);
			}
		}

		this.centers = new Map();

		for(const bed of this.crops.beds.values()) {

			const center = (bed.whole !== null) ? G.centroid(bed.whole.points) : this.bedCenter(bed.id);

			for(const place of bed.places) {
				if(this.centers.has(place.series) === false) {
					this.centers.set(place.series, center);
				}
			}

		}

	}

	/**
	 * Les mois sous la frise et les jours de la semaine. Un mois se pose à la semaine de son premier jour ; le choisir arrête la
	 * lecture, comme le curseur.
	 */
	bindTimeline(plan, play, pause) {

		const week = this.container.qs('[data-scene-week]');
		const months = this.container.qs('[data-scene-months]');
		const days = this.container.qs('[data-scene-days]');
		const min = Number(week.min);
		const span = Math.max(1, Number(week.max) - min);

		// Le film de la saison redessine cette frise dans son image (MapSceneRecord) : il la lit ici
		this.months = { min, span, list: [] };

		for(let month = 1; month <= 12; month++) {

			const first = plan.season +'-'+ String(month).padStart(2, '0') +'-01';
			const found = this.crops.stage.week(first);
			// Le 1er janvier tombe souvent dans la dernière semaine de l'année d'avant
			const shown = (found.year === plan.season) ? found.week : min;
			const label = MapScene.dayText(first, { month: 'short' }).replace('.', '');

			const link = document.createElement('a');
			link.textContent = label;
			link.style.left = ((shown - min) / span * 100) +'%';
			link.dataset.week = shown;

			months.append(link);
			this.months.list.push({ week: shown, label });

		}

		months.addEventListener('click', event => {

			const link = event.target.closest('[data-week]');

			if(link !== null) {
				this.stopPlaying(plan, play, pause, false);
				this.goWeek(plan, Number(link.dataset.week));
			}

		});

		// La lecture refait les jours et la fiche chaque jour : arrêtée dès l'appui, elle laisse en place ce qui recevra le clic
		days.addEventListener('pointerdown', () => this.stopPlaying(plan, play, pause, false));
		this.container.qs('[data-scene-sheet]').addEventListener('pointerdown', () => this.stopPlaying(plan, play, pause, false));

		days.addEventListener('click', event => {

			const day = event.target.closest('[data-day]');

			if(day !== null) {
				this.stopPlaying(plan, play, pause, false);
				this.day = Number(day.dataset.day);
				this.show(plan);
			}

		});

	}

	/**
	 * Le relief allumé ou éteint, la scène remontée à la semaine montrée quand elle revient. Elle se relit au serveur derrière les écritures du décor
	 * encore en file : celle de la page dirait le décor de son ouverture, sans ce que la séance y a posé. L'équipe reste celle de la
	 * page, que l'écran met en forme et que le serveur ne renvoie pas. Un échec, que le bandeau du cadriciel dit, laisse la scène
	 * et le choix tels quels.
	 */
	toggleRelief(plan, shown) {

		return this.decor.queue
			.then(() => new Ajax.Query(this.container).method('get').url('/map/scene:get?id='+ plan.structure +'&season='+ plan.season).fetch())
			.then(json => {

				if(this.closed) {
					return;
				}

				MapSceneRelief.remember(this.container, shown);
				MapScene.restart({ ...json.scene, team: plan.team }, this.week);

			}, () => {});

	}

	/**
	 * Une semaine choisie à la main garde le jour montré. L'équipe de la scène est celle du planning, présente le week-end comme en
	 * semaine : le jour où elle compte le plus de membres ne dit pas où le travail s'est fait.
	 */
	goWeek(plan, chosen) {

		this.week = chosen;

		this.container.qs('[data-scene-week]').value = chosen;
		this.show(plan);

	}

	/**
	 * Le mode photo, par son bouton ou la touche P, retire tout ce qui n'est pas la scène ; Échap en sort. L'espace lance et arrête
	 * la lecture, hors d'un champ de saisie et d'un bouton atteint au clavier, qu'il active. Le bouton qu'un clic de souris a laissé
	 * sous le focus le perd : l'espace le cliquerait aussi.
	 */
	bindPhoto(plan, play, pause) {

		const bar = this.container.qs('[data-scene-photo-bar]');

		// Ce qui était choisi se retire de l'image et se rechoisit à la sortie
		let chosen = null;

		const photo = on => {

			// Au clavier, le focus suit ce qui reste à l'écran : la barre du mode photo, puis le bouton qui l'a ouverte (MapScene.swap())
			const focused = ((on ? this.container : bar).contains(document.activeElement) && document.activeElement.matches(':focus-visible'));

			this.photo = on;
			this.container.classList.toggle('map-scene-photo', on);
			bar.classList.toggle('hide', on === false);

			if(focused) {
				(on ? save : button).focus();
			}

			if(on) {
				this.hold.cancel();
				chosen = this.selected;
				this.stopPlaying(plan, play, pause, false);
				this.choose(plan, null);
			} else {

				this.record.cancel();

				if(chosen !== null) {
					this.choose(plan, chosen);
					chosen = null;
				}

			}

		};

		const button = this.container.qs('[data-scene-photo]');
		const save = this.container.qs('[data-scene-photo-save]');
		button.addEventListener('click', () => photo(true));
		this.container.qs('[data-scene-photo-close]').addEventListener('click', () => photo(false));

		// La pose du décor prend la scène et coupe le clic sur elle : la fiche s'y ferme et le mode photo n'y entre pas. Le bouton
		// Décor ne se montre que hors de la pose, quelle que soit la façon d'en sortir
		const decor = this.container.qs('[data-scene-decor]');

		if(decor !== null) {

			const watch = new MutationObserver(() => {

				const posing = decor.classList.contains('hide');
				button.classList.toggle('hide', posing);

				// Un élément masqué par un effet partirait masqué à l'enregistrement de la pose : tout revient avant
				if(posing) {
					this.hold.cancel();
					this.ruins.reset();
					this.choose(plan, null);
				}

			});

			watch.observe(decor, { attributes: true, attributeFilter: ['class'] });
			this.disposables.push({ dispose: () => watch.disconnect() });

		}

		// Le canevas ne garde pas son image après l'affichage : elle se lit dans la tâche même qui la dessine
		save.addEventListener('click', () => {

			this.renderer.render(this.scene, this.camera);

			this.renderer.domElement.toBlob(blob => {

				const link = document.createElement('a');
				link.href = URL.createObjectURL(blob);
				link.download = this.texts.photo.replace('{week}', this.week);
				link.click();

				setTimeout(() => URL.revokeObjectURL(link.href));

			});

		});

		this.onKey = event => {

			if(this.decor.active || event.ctrlKey || event.metaKey || event.altKey || event.target.closest?.('input:not([type="range"]), select, textarea, [contenteditable]')) {
				return;
			}

			if(event.key === 'Escape') {

				// Pendant un film, Échap l'arrête et laisse en mode photo : un second Échap en sort
				if(this.record.active) {
					this.record.cancel();
				} else if(this.photo) {
					photo(false);
				} else if(this.selected !== null) {
					this.choose(plan, null);
				}

			} else if(event.key === 'p' || event.key === 'P') {
				photo(this.photo === false);
			} else if(event.code === 'Space' && this.photo === false && event.target.matches?.('button:focus-visible') !== true) {

				event.preventDefault();
				document.activeElement?.blur();

				if(this.player === null) {
					play.click();
				} else {
					pause.click();
				}

			}

		};

		window.addEventListener('keydown', this.onKey);

	}

	/**
	 * Les sept jours de la semaine montrée, refaits quand la semaine change : un point sous ceux où l'équipe a travaillé.
	 */
	showDays(plan) {

		const days = this.container.qs('[data-scene-days]');
		const monday = this.crops.stage.monday(plan.season, this.week);

		if(days.dataset.monday !== monday) {

			days.dataset.monday = monday;
			days.replaceChildren();

			for(let day = 0; day < 7; day++) {

				const date = MapScene.addDays(monday, day);

				const number = document.createElement('span');
				number.className = 'util-calendar-day';
				number.textContent = Number(date.slice(8));

				const name = document.createElement('span');
				name.className = 'util-calendar-month';
				name.textContent = MapScene.dayText(date, { weekday: 'short' }).replace('.', '');

				const calendar = document.createElement('div');
				calendar.className = 'util-calendar';
				calendar.append(number, name);

				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'util-day';
				button.classList.toggle('map-scene-day-busy', this.crew.count(date) > 0);
				button.dataset.day = day;
				button.append(calendar);

				days.append(button);

			}

		}

		days.qsa('[data-day]', button => button.firstChild.classList.toggle('util-calendar-selected', Number(button.dataset.day) === this.day));

	}

	/**
	 * Les bulles des récoltes de la semaine, une par série, qui sortent le lundi pendant la lecture.
	 */
	showBubbles(plan, playing) {

		const monday = this.crops.stage.monday(plan.season, this.week);
		const harvests = this.harvestWeeks.get(monday) ?? [];

		this.bubbles.hold(this.week);

		if(playing && this.day === 0 && harvests.length > 0) {

			const bySeries = new Map();

			for(const harvest of harvests) {
				MapScene.push(bySeries, harvest.series, harvest);
			}

			this.bubbles.show(this.week, [...bySeries].filter(([series]) => this.centers.has(series)).map(([series, list]) => ({ at: this.centers.get(series), harvests: list })), this.texts);

		}

	}

	/**
	 * Pendant la lecture, une série debout la veille et partie le jour montré éclate au-dessus de sa planche.
	 */
	burst(segments, playing) {

		const standing = new Map(segments.map(segment => [segment.key, segment]));

		if(playing && this.standing !== null) {
			for(const [key, segment] of this.standing) {
				if(standing.has(key) === false) {
					this.sparks.burst(segment);
				}
			}
		}

		this.standing = playing ? standing : null;

	}

	/**
	 * Le terrain lu du serveur, chaque valeur que la scène ne connaît pas remplacée par la première de sa table. Une version du
	 * client plus ancienne que la base peut recevoir une herbe ou un sol qu'elle ne sait pas peindre.
	 */
	static readTerrain({ grass, soil }) {

		const known = (table, value) => Object.hasOwn(table, value) ? value : Object.keys(table)[0];

		return {
			grass: known(MapScene.GRASSES, grass),
			soil: known(MapScene.SOILS, soil)
		};

	}

	/**
	 * Peint l'herbe et le sol choisis. La saison part de cette herbe, ses aplats prennent son écart et la terre des planches
	 * prend le sol, avec son cadre si elle est claire.
	 */
	setTerrain({ grass, soil }) {

		this.season.useGrass(grass);
		this.grassStep.value = MapScene.GRASSES[grass].step;

		const color = MapScene.SOILS[soil];

		this.materials.soil.color.set(color);
		this.materials.soilGreenhouse.color.set(color).multiplyScalar(MapScene.GREENHOUSE_SHADE);
		this.bedFrame.value = MapScene.framed(soil) ? 1 : 0;

	}

	/**
	 * Une terre est claire quand la luminance relative de sa couleur dépasse BED_FRAME_LUMINANCE. Elle se lit sur le sol tel qu'on
	 * le choisit : sous abri, la terre plus sombre garde le cadre de sa planche voisine à l'air.
	 */
	static framed(soil) {

		const linear = channel => (channel <= .04045) ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
		const [red, green, blue] = [1, 3, 5].map(i => linear(parseInt(MapScene.SOILS[soil].slice(i, i + 2), 16) / 255));

		return .2126 * red + .7152 * green + .0722 * blue > MapScene.BED_FRAME_LUMINANCE;

	}

	/**
	 * La lumière du jour : la hauteur, la couleur et la force du soleil, l'appoint du ciel, la teinte du ciel et du pré.
	 */
	light(date) {

		const light = this.season.at(date);

		this.ambience.prepare(this.week, this.player !== null, this.speed.factor, { sun: this.sun, hemisphere: this.hemisphere, ground: this.materials.ground, fold: this.materials.fold });
		this.shown = date;

		this.sun.intensity = light.force;
		this.sun.color.setRGB(...light.sun);
		this.sun.position.copy(this.sun.target.position).add(this.ambience.sun(date, light.offset));
		this.hemisphere.intensity = light.fill;

		this.ambience.sky(light.sky);

		const materials = this.materials;
		materials.ground.color.setRGB(...light.grass);
		materials.fold.color.copy(materials.ground.color).multiplyScalar(MapScene.FOLD_SHADE);

		this.decor.season(date, light.path);

	}

	/**
	 * Le liseré au sol autour de chaque série de l'espèce choisie : un rectangle d'instance par côté, sa normale tournée vers
	 * l'extérieur, remis à l'épaisseur voulue quand le zoom change.
	 */
	setOutline(chosen) {

		const THREE = this.crops.THREE;
		const outline = this.outline;
		const y = MapScene.BED_BASE + MapScene.BED_HEIGHT + MapScene.OUTLINE_LIFT;

		outline.edges = chosen.outline.flatMap(points => {

			const middle = ScenePlanGeometry.centroid(points);

			return points.map((a, i) => {

				const b = points[(i + 1) % points.length];
				const along = ScenePlanGeometry.sub(b, a);
				const length = ScenePlanGeometry.length(along);
				const center = ScenePlanGeometry.lerp(a, b, .5);
				const out = { x: -along.n / length, n: along.x / length };
				const side = (ScenePlanGeometry.dot(out, ScenePlanGeometry.sub(center, middle)) < 0) ? -1 : 1;

				return { center, out: ScenePlanGeometry.mul(out, side), length, angle: Math.atan2(along.n, along.x), y };

			});

		});

		outline.thickness = 0;

		// Le maillage ne se refait que pour grandir
		if((outline.mesh === null && outline.edges.length === 0) || (outline.mesh !== null && outline.edges.length <= outline.mesh.instanceMatrix.count)) {
			return;
		}

		// InstancedMesh.dispose() ne libère que les attributs d'instance, la géométrie se libère à part
		if(outline.mesh !== null) {
			this.scene.remove(outline.mesh);
			outline.mesh.geometry.dispose();
			outline.mesh.dispose();
		}

		outline.mesh = new THREE.InstancedMesh(new THREE.PlaneGeometry(1, 1).rotateX(-Math.PI / 2), this.materials.outline, outline.edges.length * 2);
		outline.mesh.frustumCulled = false;
		outline.side ??= new THREE.Object3D();
		this.scene.add(outline.mesh);

	}

	drawOutline(thickness) {

		const outline = this.outline;

		if(outline.mesh === null || thickness === outline.thickness) {
			return;
		}

		outline.thickness = thickness;

		outline.edges.forEach((edge, i) => {

			const center = ScenePlanGeometry.add(edge.center, ScenePlanGeometry.mul(edge.out, thickness / 2));

			outline.side.position.set(center.x, edge.y, -center.n);
			outline.side.rotation.set(0, edge.angle, 0);
			outline.side.scale.set(edge.length + thickness * 2, 1, thickness);
			outline.side.updateMatrix();

			outline.mesh.setMatrixAt(i, this.geometry.lean(outline.side.matrix));

		});

		outline.mesh.count = outline.edges.length;
		outline.mesh.instanceMatrix.needsUpdate = true;

	}

	// Cadre ce que le sélecteur de zone a choisi : toute la structure, ou une zone
	frameChosen(THREE) {

		if(this.zone === null) {
			this.frame(THREE, this.everything);
			return;
		}

		const id = this.zone;
		const beds = this.geometry.beds.filter(bed => bed.zone === id);

		// Une zone se serre sur ses planches ; sans planche, sur son contour
		this.frame(THREE, beds.length > 0 ? beds.flatMap(bed => this.geometry.corners(bed)) : this.geometry.zones.get(id));

	}

	/**
	 * Le plancher du zoom, le plus large des cadrages que la scène propose. Pendant la pose du décor, la portée de pose en est un :
	 * un élément posé au plus loin des parcelles doit pouvoir se voir en entier.
	 */
	zoomFloor(usable, posing) {

		const framings = posing ? [...this.framings, this.framingReach] : this.framings;

		return Math.min(...framings.map(framing => this.fit(framing.width, framing.height, usable)), this.controls.maxZoom);

	}

	// La pose du décor s'ouvre ou se ferme : le plancher du zoom la suit, et une vue restée plus loin que lui revient au cadrage choisi
	setPosing(THREE) {

		this.controls.minZoom = this.zoomFloor(this.usable(), this.decor.active);

		if(this.camera.zoom < this.controls.minZoom) {
			this.frameChosen(THREE);
		}

		// Pendant la pose, la limite entre deux îlots est celle que le refus applique, et la grille du décor la trace pour un
		// tracé, seul à buter dessus. Le trait au sol est une droite du paysage, qui ne la suit pas : il montrerait une autre limite
		for(const band of this.folds) {
			band.visible = (this.decor.active === false);
		}

	}

	/**
	 * Cadre des points du sol à la place de la vue, sans changer l'angle de la caméra : la vue se
	 * centre sur ce qu'ils couvrent à l'écran, puis le zoom les fait tenir. L'ombre du soleil suit,
	 * bornée à ce qui est regardé : étirée sur toute une structure, elle serait floue. Sans `closer`, le
	 * zoom ne fait que reculer.
	 */
	frame(THREE, points, closer = true) {

		if(points.length === 0) {
			return;
		}

		// Le survol rejouerait à l'image suivante sa propre cible et son zoom par-dessus ce cadrage
		this.ambience.landIntro();

		const camera = this.camera;
		const offset = camera.position.clone().sub(this.controls.target);

		const xs = points.map(p => p.x);
		const ns = points.map(p => p.n);
		const heights = points.map(p => this.geometry.height(p.x, p.n));
		const center = new THREE.Vector3((Math.min(...xs) + Math.max(...xs)) / 2, heights.reduce((sum, height) => sum + height, 0) / heights.length, -(Math.min(...ns) + Math.max(...ns)) / 2);

		this.controls.target.copy(center);
		camera.position.copy(center).add(offset);
		camera.lookAt(center);
		camera.updateMatrixWorld();

		let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
		const point = new THREE.Vector3();

		for(const [i, p] of points.entries()) {
			for(const lift of [0, 2]) {

				point.set(p.x, heights[i] + lift, -p.n).applyMatrix4(camera.matrixWorldInverse);

				minX = Math.min(minX, point.x);
				maxX = Math.max(maxX, point.x);
				minY = Math.min(minY, point.y);
				maxY = Math.max(maxY, point.y);

			}
		}

		const pan = new THREE.Vector3((minX + maxX) / 2, (minY + maxY) / 2, 0).transformDirection(camera.matrixWorld).multiplyScalar(Math.hypot((minX + maxX) / 2, (minY + maxY) / 2));
		this.controls.target.add(pan);
		camera.position.add(pan);

		const usable = this.usable();

		const fitted = this.fit(maxX - minX, maxY - minY, usable);

		camera.zoom = Math.max(this.controls.minZoom, Math.min(this.controls.maxZoom, closer ? fitted : Math.min(camera.zoom, fitted)));

		const shift = (camera.top - camera.bottom) * (1 - usable) / 2 / camera.zoom;
		const up = new THREE.Vector3(0, 1, 0).applyQuaternion(camera.quaternion);
		this.controls.target.addScaledVector(up, -shift);
		camera.position.addScaledVector(up, -shift);

		camera.updateProjectionMatrix();
		this.controls.update();

		const extent = Math.min(400, Math.max(Math.max(...xs) - Math.min(...xs), Math.max(...ns) - Math.min(...ns)) * .75 + 30);
		Object.assign(this.sun.shadow.camera, { left: -extent, right: extent, top: extent, bottom: -extent, near: 1, far: 1400 });
		this.sun.shadow.camera.updateProjectionMatrix();

		// Le soleil garde sa hauteur de saison au-dessus de la nouvelle cible
		const sunOffset = this.sun.position.clone().sub(this.sun.target.position);
		this.sun.target.position.copy(this.controls.target);
		this.sun.position.copy(this.controls.target).add(sunOffset);

		this.renderer.shadowMap.needsUpdate = true;

	}

	/**
	 * Le lit de terre d'une planche, qui porte dans chaque sommet ses coordonnées dans le repère de la planche et sa graine : le bord
	 * grignoté et les aplats de la terre se calculent de là (`bedTexture()`). Les coordonnées se lisent dans le repère de la planche
	 * et non en projections sur ses axes : une planche tracée n'a pas toujours ses deux axes à angle droit, et les projections
	 * s'écarteraient alors du bord dessiné de plusieurs décimètres au bout d'une planche de 25 m.
	 */
	static bed(THREE, geometry, bed) {

		const shape = new THREE.Shape(geometry.corners(bed).map(p => new THREE.Vector2(p.x, p.n)));
		const slab = new THREE.ExtrudeGeometry(shape, { depth: MapScene.BED_HEIGHT, bevelEnabled: false }).rotateX(-Math.PI / 2);

		const position = slab.attributes.position;
		const det = bed.u.x * bed.w.n - bed.u.n * bed.w.x;
		// Deux planches voisines ne portent pas le même dessin : le repère de chacune part d'ailleurs le long de son axe
		const seed = bed.id * 61.7 % 997;

		const edge = new Float32Array(position.count * 3);
		const size = new Float32Array(position.count * 2);

		for(let i = 0; i < position.count; i++) {

			const d = ScenePlanGeometry.sub({ x: position.getX(i), n: -position.getZ(i) }, bed.origin);

			edge.set([(d.x * bed.w.n - d.n * bed.w.x) / det, (bed.u.x * d.n - bed.u.n * d.x) / det, seed], i * 3);
			size.set([bed.length, bed.width], i * 2);

		}

		slab.setAttribute('bedEdge', new THREE.BufferAttribute(edge, 3));
		slab.setAttribute('bedSize', new THREE.BufferAttribute(size, 2));

		return slab;

	}

	// Le bruit de valeur des aplats de l'herbe et de la terre, et du bord grignoté des planches
	static NOISE = `
float mapNoiseHash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
float mapNoise(vec2 p) {
	vec2 i = floor(p), f = fract(p);
	f = f * f * (3.0 - 2.0 * f);
	return mix(mix(mapNoiseHash(i), mapNoiseHash(i + vec2(1.0, 0.0)), f.x), mix(mapNoiseHash(i + vec2(0.0, 1.0)), mapNoiseHash(i + vec2(1.0, 1.0)), f.x), f.y);
}
`;

	/**
	 * L'herbe en aplats tachetés, d'une valeur à l'autre de `step` en part de sa couleur, sur deux octaves de bruit qui ne
	 * laissent voir aucune répétition. Le décalage de 2,089 est la moyenne mesurée des aplats : la teinte moyenne de l'herbe
	 * reste celle de la couleur unie, que la saison règle.
	 */
	static grassTexture(material, step) {

		material.onBeforeCompile = shader => {

			shader.uniforms.grassStep = step;

			shader.vertexShader = 'varying vec2 vGround;\n' + shader.vertexShader.replace('#include <project_vertex>', '#include <project_vertex>\nvGround = (modelMatrix * vec4(transformed, 1.0)).xz;');

			shader.fragmentShader = 'uniform float grassStep;\nvarying vec2 vGround;\n' + MapScene.NOISE + shader.fragmentShader.replace('#include <color_fragment>', `#include <color_fragment>
	float tone = (mapNoise(vGround * .07) + .35 * mapNoise(vGround * .31 + 5.)) * 3.;
	diffuseColor.rgb *= 1. + grassStep * (floor(tone) + smoothstep(.45, .55, fract(tone)) - 2.089);`);

		};

		material.customProgramCacheKey = () => 'map-scene-grass';

	}

	/**
	 * La terre d'une planche, en trois valeurs d'aplat plus longues que larges, écartées de BED_STEP autour de sa couleur, dont la
	 * moyenne ne bouge donc pas. Les aplats se fondent dans leur moyenne avant que leur dessin ne passe sous le pixel : la clarté
	 * de la terre ne saute pas au zoom arrière.
	 *
	 * Le bord n'est pas une règle : il se creuse vers l'intérieur, sans géométrie, et la terre ne sort jamais du rectangle. Il
	 * recule de six centimètres sur la moitié de sa longueur environ, par plages de l'ordre du mètre, et un grain plus serré de
	 * trois centimètres court tout du long : la terre perd neuf centimètres au plus. Le bruit ne dépend que de la planche, sa
	 * graine et ses mètres, donc la forme est la même à chaque chargement.
	 *
	 * Le chant de deux millimètres de la planche court tout droit sur le rectangle : il se retire, sans quoi il redessinerait la
	 * règle au fond des encoches.
	 *
	 * Le cadre (BED_FRAME) longe le bord grignoté quand `frame.value` vaut 1, que `setTerrain()` pose d'après le sol : un uniform,
	 * pour que changer de sol ne recompile pas le nuanceur.
	 */
	static bedTexture(THREE, material, frame) {

		// La teinte du cadre, dans l'espace de couleur linéaire où le nuanceur calcule, comme celle que prend la matière
		const edge = new THREE.Color(MapScene.BED_FRAME).toArray().map(x => x.toFixed(4));

		material.onBeforeCompile = shader => {

			shader.uniforms.bedFrame = frame;

			shader.vertexShader = 'attribute vec3 bedEdge;\nattribute vec2 bedSize;\nvarying vec3 vBedEdge;\nvarying vec2 vBedSize;\nvarying float vBedUp;\n' + shader.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\nvBedEdge = bedEdge;\nvBedSize = bedSize;\nvBedUp = normal.y;');

			shader.fragmentShader = `uniform float bedFrame;
varying vec3 vBedEdge;
varying vec2 vBedSize;
varying float vBedUp;
` + MapScene.NOISE + shader.fragmentShader.replace('#include <clipping_planes_fragment>', `#include <clipping_planes_fragment>
	// Une planche en ruines (MapSceneRuins) porte ses plants dans le maillage de sa terre, sans repère de planche : leur taille nulle
	// les tient hors du lit
	bool bBed = vBedSize.x > 0.;
	float bSigned = 0., bDepth = 0.;
	if(bBed) {
		if(vBedUp < .5) discard;
		float bu = vBedEdge.x, bv = vBedEdge.y, bShift = vBedEdge.z, bl = vBedSize.x, bw = vBedSize.y;
		bSigned = min(min(bu, bl - bu), min(bv, bw - bv));
		bool bLong = min(bv, bw - bv) < min(bu, bl - bu);
		float bAlong = bLong ? bu + bShift + (bv * 2. > bw ? 31.7 : 0.) : bv + bShift * 1.37 + (bu * 2. > bl ? 53.1 : 17.3);
		bDepth = .06 * smoothstep(.3, .7, mapNoise(vec2(bAlong * .9, 3.1))) + .03 * mapNoise(vec2(bAlong * 4.3, 8.7));
		if(bSigned < bDepth) discard;
	}`).replace('#include <color_fragment>', `#include <color_fragment>
	// Deux mesures du pixel, calculées hors du branchement où les dérivées ne sont garanties qu'en flot uniforme. bPixel, le plus grand
	// pas des deux coordonnées de la planche, vaut jusqu'au double en vue de biais : bShade le garde, son seuil a été réglé sur cette
	// échelle. Le cadre, qui doit tenir un pixel, se mesure en travers du bord, par la pente de la distance à celui-ci.
	float bPixel = max(fwidth(vBedEdge.x), fwidth(vBedEdge.y));
	float bInside = bSigned - bDepth;
	float bEdgePixel = length(vec2(dFdx(bInside), dFdy(bInside)));
	if(bBed) {
		vec2 bSpot = vec2(vBedEdge.x + vBedEdge.z, vBedEdge.y) * vec2(.4, .8);
		float bTone = clamp(((mapNoise(bSpot) + .35 * mapNoise(bSpot * 2.2 + 5.)) / 1.35 - .3) / .4, 0., .999) * 2.;
		float bShade = mix(.5, (floor(bTone) + smoothstep(.45, .55, fract(bTone))) / 2., 1. - smoothstep(.5, 1.2, bPixel));
		diffuseColor.rgb *= 1. + ${MapScene.BED_STEP} * (bShade - .5);
		float bEdge = max(${MapScene.BED_FRAME_WIDTH.toFixed(3)}, bEdgePixel);
		diffuseColor.rgb = mix(diffuseColor.rgb, vec3(${edge.join(', ')}), bedFrame * (1. - smoothstep(bEdge - .5 * bEdgePixel, bEdge + .5 * bEdgePixel, bInside)));
	}`);

		};

		material.customProgramCacheKey = () => 'map-scene-bed';

	}

	/**
	 * Avive les couleurs d'une matière des cultures du socle de CROP_SATURATION, après sa teinte d'instance. La matière est celle
	 * que le bourg prend aussi : l'avivage se pose ici, sur les matières que la scène du plan a montées, et la clé du programme
	 * change pour que three ne le partage pas avec une matière restée sans.
	 */
	static saturate(material) {

		const compile = material.onBeforeCompile;
		const key = material.customProgramCacheKey();

		material.onBeforeCompile = (shader, renderer) => {

			compile(shader, renderer);

			shader.fragmentShader = shader.fragmentShader.replace('#include <color_fragment>', `#include <color_fragment>
	diffuseColor.rgb = max(vec3(0.), mix(vec3(dot(diffuseColor.rgb, vec3(.2126, .7152, .0722))), diffuseColor.rgb, ${MapScene.CROP_SATURATION.toFixed(3)}));`);

		};

		material.customProgramCacheKey = () => key + '-saturate';
		material.needsUpdate = true;

	}

	/**
	 * La bâche d'un abri sur le rectangle de son jardin : sa couverture tirée sur toute la longueur, ses
	 * pieds droits, et ses deux pignons, ouverts sur une porte par nef quand le modèle en a.
	 */
	static tunnel(THREE, rectangle, greenhouse) {

		const { cover, doors } = greenhouse;
		const at = (across, along, y) => MapScene.onRectangle(rectangle, across, along, y);

		const positions = [];

		// La couverture fermée jusqu'au sol par les pieds droits, quand le modèle en a
		const profile = (cover[0][1] > 0) ? [[0, 0], ...cover, [rectangle.width, 0]] : cover;

		for(let i = 0; i < profile.length - 1; i++) {

			const [a, ya] = profile[i];
			const [b, yb] = profile[i + 1];

			positions.push(...at(a, 0, ya), ...at(b, 0, yb), ...at(b, rectangle.length, yb));
			positions.push(...at(a, 0, ya), ...at(b, rectangle.length, yb), ...at(a, rectangle.length, ya));

		}

		if(doors.length === 0) {

			// Un pignon en éventail depuis le milieu de sa base : le profil est convexe, aucun triangle n'en sort
			for(const along of [0, rectangle.length]) {
				for(let i = 0; i < profile.length - 1; i++) {
					positions.push(...at(rectangle.width / 2, along, 0), ...at(profile[i][0], along, profile[i][1]), ...at(profile[i + 1][0], along, profile[i + 1][1]));
				}
			}

		} else {

			// Le pignon se ferme par bandes verticales, de la couverture au sol, ou au linteau au droit d'une porte, qui reste ouverte
			const roofAt = across => ScenePlanGeometry.roofAt(cover, across);

			const edges = [...new Set([...cover.map(([across]) => across), ...doors.flatMap(door => [door.from, door.from + door.width])])].sort((a, b) => a - b);

			for(const along of [0, rectangle.length]) {
				for(let i = 0; i < edges.length - 1; i++) {

					const a0 = edges[i];
					const a1 = edges[i + 1];
					const bottom = doors.find(door => a0 >= door.from && a1 <= door.from + door.width)?.height ?? 0;

					positions.push(...at(a0, along, bottom), ...at(a1, along, bottom), ...at(a1, along, roofAt(a1)));
					positions.push(...at(a0, along, bottom), ...at(a1, along, roofAt(a1)), ...at(a0, along, roofAt(a0)));

				}
			}

		}

		const tunnel = new THREE.BufferGeometry();
		tunnel.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		tunnel.computeVertexNormals();

		return tunnel;

	}

	/**
	 * Un point d'un rectangle du plan dans le repère de la scène, par sa distance aux deux bords qui partent
	 * de son origine et sa hauteur.
	 */
	static onRectangle(rectangle, across, along, y) {

		const point = ScenePlanGeometry.add(rectangle.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rectangle.w, across), ScenePlanGeometry.mul(rectangle.u, along)));

		return [point.x, y, -point.n];

	}

	/**
	 * L'ossature d'un abri en segments : un arceau à chaque pignon et tous les ARCH_STEP mètres, les lisses au sol, en haut des pieds
	 * droits, au faîtage et au chéneau de chaque nef, les poteaux sous les chéneaux, et le cadre des portes quand le modèle en a.
	 */
	static arches(THREE, rectangle, greenhouse) {

		const { cover, doors } = greenhouse;
		const at = (across, along, y) => MapScene.onRectangle(rectangle, across, along, y);

		const walled = (cover[0][1] > 0);
		const outline = walled ? [[0, 0], ...cover, [rectangle.width, 0]] : cover;
		// Le faîtage de chaque nef, et entre deux nefs le chéneau, porté par un poteau à chaque arceau. Un faîtage en palier ne
		// compte qu'une fois : son dernier sommet, le seul plus haut que son voisin de droite
		const ridges = cover.filter((point, i) => i > 0 && i < cover.length - 1 && point[1] >= cover[i - 1][1] && point[1] > cover[i + 1][1]);
		const gutters = cover.filter((point, i) => i > 0 && i < cover.length - 1 && point[1] < cover[i - 1][1] && point[1] < cover[i + 1][1]);
		const count = Math.max(1, Math.round(rectangle.length / MapScene.ARCH_STEP));

		const positions = [];

		for(let j = 0; j <= count; j++) {

			const along = rectangle.length * j / count;

			for(let i = 0; i < outline.length - 1; i++) {
				positions.push(...at(outline[i][0], along, outline[i][1]), ...at(outline[i + 1][0], along, outline[i + 1][1]));
			}

			for(const [across, y] of gutters) {
				positions.push(...at(across, along, 0), ...at(across, along, y));
			}

		}

		const rails = [[0, 0], [rectangle.width, 0], ...ridges, ...gutters, ...(walled ? [cover[0], cover.at(-1)] : [])];

		for(const [across, y] of rails) {
			positions.push(...at(across, 0, y), ...at(across, rectangle.length, y));
		}

		for(const door of doors) {

			const from = door.from;
			const to = from + door.width;
			const middle = from + door.width / 2;

			for(const along of [0, rectangle.length]) {
				for(const [a, b] of [[[from, 0], [from, door.height]], [[to, 0], [to, door.height]], [[from, door.height], [to, door.height]], [[middle, 0], [middle, door.height]]]) {
					positions.push(...at(a[0], along, a[1]), ...at(b[0], along, b[1]));
				}
			}

		}

		const lines = new THREE.BufferGeometry();
		lines.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));

		return lines;

	}

	close() {

		this.closed = true;

		clearInterval(this.player);

		if(this.onKey !== null) {
			window.removeEventListener('keydown', this.onKey);
		}

		if(this.onFullscreen !== null) {
			this.container.removeEventListener('fullscreenchange', this.onFullscreen);
		}

		// draw() a pu s'interrompre à tout moment : seul ce qui a été créé se libère
		this.renderer?.setAnimationLoop(null);
		this.observer?.disconnect();
		this.controls?.dispose();

		this.scene?.traverse(object => {
			if(object.isMesh || object.isLineSegments) {
				object.geometry.dispose();
			}
		});

		this.outline.mesh?.dispose();

		this.disposables.forEach(disposable => disposable.dispose());
		this.disposables = [];

		this.labels?.domElement.remove();

		if(this.renderer !== null) {
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer.domElement.remove();
		}

		this.renderer = null;
		this.labels = null;
		this.observer = null;
		this.controls = null;
		this.scene = null;
		this.camera = null;
		this.field = null;
		this.crops = null;
		this.crew = null;
		this.decor = null;
		this.season = null;
		this.sun = null;
		this.hemisphere = null;
		this.materials = null;
		this.player = null;
		this.ambience = null;
		this.boards = null;
		this.sign = null;
		this.insects = null;
		this.antics = null;
		this.fauna = null;
		this.ruins = null;
		this.hold = null;
		this.films = null;
		this.sheet = null;
		this.marker = null;
		this.sparks = null;
		this.bubbles = null;
		this.record = null;
		this.months = null;
		this.onKey = null;

	}

}

/**
 * Le relief sous le plan, tel que l'IGN le donne en tuiles d'altitudes (`ReliefLib::getForScene()`). Il se pose une fois, au
 * montage de la scène : une grille au pas de STEP mètres sur toute la portée des îlots, à la place où chaque îlot s'affiche.
 *
 * Deux îlots rapprochés à l'affichage n'ont pas la même altitude réelle : entre eux, la hauteur passe de l'un à l'autre sur
 * l'écart qui les sépare, chaque point lisant les deux îlots les plus proches.
 */
class MapSceneRelief {

	// Le pas de la grille, en mètres : celui des points de l'IGN (MapSetting::RELIEF_DIVISIONS_LAT et RELIEF_DIVISIONS_LNG)
	static STEP = 4;

	// Un rayon vers le sol (onRelief()) s'avance par pas de RAY_STEP mètres au sol, puis se resserre de moitié jusqu'à RAY_PRECISION
	// mètres le long du rayon. Le pas est la moitié de la case la plus fine que lattice() puisse poser : une bosse du sol, large
	// d'une case au moins, ne passe pas entre deux pas
	static RAY_STEP = MapSceneRelief.STEP / 2;
	static RAY_PRECISION = .0001;

	// Le sol s'étend jusque-là autour de la portée des îlots, en mètres, par pas de plus en plus longs : au-delà du plus grand recul
	static OUTER = 5000;

	// Les pas du sol, en mètres : celui de la grille sous les îlots, plus longs jusqu'à la portée du décor, puis grandissant de
	// GROWTH en GROWTH
	static COARSE = 16;
	static GROWTH = 1.25;

	// Le plus de triangles que porte le sol. La cible de 1,21 M triangles de la scène laisse 60 000 au relief, une fois ôtés la
	// pire journée, les tracés, la haie et DECOR_TRIANGLES (`MapSetting`, production/map/Map.s.php) ; le sol en garde ce que les
	// soubassements, les tuyaux et les tracés drapés n'y prennent pas. L'emprise des îlots n'a pas de borne : au-delà, les pas
	// grandissent (`lattice()`)
	static GROUND_TRIANGLES = 50000;

	// Le plus long côté au sol d'un triangle de planche, en mètres, au-delà duquel il se coupe pour suivre la pente
	static CUT = 4;

	// De combien le pied d'un soubassement passe sous le sol, en mètres : entre deux points de son tour, le sol rendu peut descendre
	// sous la droite qui les joint, et un pied posé sur elle laisserait voir le jour
	static PLINTH_SINK = .3;

	// L'estompage, éclairé du nord-ouest comme une carte : une pente tournée vers la lumière s'éclaire, l'autre s'assombrit, de
	// SHADE fois l'écart de son éclairage à la moyenne de la structure, entre SHADE_MIN et SHADE_MAX. Le nuanceur en aplats du pré
	// rendrait une pente douce de la même teinte qu'un plat
	static SHADE = 1.5;
	static SHADE_MIN = .85;
	static SHADE_MAX = 1.1;
	static SHADE_SUN = [-.5, .7071, -.5];

	// Une courbe de niveau par mètre, une maîtresse tous les cinq, fines et sombres
	static CONTOUR_STEP = 1;
	static CONTOUR_MAJOR = 5;

	// Le masque des courbes autour des parcelles : sa marge en mètres, plus large autour d'une serre dont la bâche déborde, et sa
	// maille la plus fine
	static MASK_MARGIN = .5;
	static MASK_MARGIN_SHELTER = 1.5;
	static MASK_RESOLUTION = .5;
	static MASK_SIZE = 4096;

	static STORAGE = 'map-scene-relief-hidden-';

	/**
	 * Le relief est allumé sauf si l'utilisateur l'a éteint sur cette structure. Une navigation privée ou des données de site
	 * bloquées font lever l'accès au stockage : le relief reste alors allumé, ce qui est l'état par défaut.
	 */
	static shown(container) {

		try {
			return (localStorage.getItem(MapSceneRelief.key(container)) !== '1');
		} catch(e) {
			return true;
		}

	}

	static remember(container, shown) {

		try {
			if(shown) {
				localStorage.removeItem(MapSceneRelief.key(container));
			} else {
				localStorage.setItem(MapSceneRelief.key(container), '1');
			}
		} catch(e) {
		}

	}

	// Par structure et par utilisateur, comme les calques du décor (`MapSceneLayer`)
	static key(container) {
		const card = container.qs('[data-scene-layers-card]');
		return MapSceneRelief.STORAGE + card.dataset.structure +'-'+ card.dataset.user;
	}

	/**
	 * Le relief de la scène, ou null quand aucune de ses tuiles ne tombe sous les îlots.
	 */
	static build(relief, geometry) {

		const built = new MapSceneRelief(relief, geometry);
		return built.empty ? null : built;

	}

	constructor(relief, geometry) {

		this.disposables = [];

		const land = geometry.land;
		const read = MapSceneRelief.reader(relief);
		const step = MapSceneRelief.STEP;

		const reach = land.islands.flatMap(island => island.reach);
		this.x0 = Math.min(...reach.map(p => p.x));
		this.n0 = Math.min(...reach.map(p => p.n));
		this.nx = Math.ceil((Math.max(...reach.map(p => p.x)) - this.x0) / step) + 1;
		this.nn = Math.ceil((Math.max(...reach.map(p => p.n)) - this.n0) / step) + 1;
		this.x1 = this.x0 + (this.nx - 1) * step;
		this.n1 = this.n0 + (this.nn - 1) * step;

		// L'altitude sous un point affiché : il redevient réel par le décalage de son îlot
		const outlines = land.islands.map(island => land.moved(island));
		const real = (p, island) => read(...geometry.unproject(ScenePlanGeometry.sub(p, island.offset)));
		const blend = ScenePlanLand.GAP / 2 + ScenePlanLand.MARGIN;

		const away = (p, outline) => {

			if(ScenePlanGeometry.inside(p, outline)) {
				return 0;
			}

			let distance = Infinity;

			for(let i = 0; i < outline.length; i++) {
				distance = Math.min(distance, ScenePlanGeometry.distanceToSegment(p, outline[i], outline[(i + 1) % outline.length]));
			}

			return distance;

		};

		const altitudes = new Float64Array(this.nx * this.nn);

		for(let j = 0; j < this.nn; j++) {
			for(let i = 0; i < this.nx; i++) {

				const p = { x: this.x0 + i * step, n: this.n0 + j * step };
				let altitude;

				if(land.islands.length === 1) {
					altitude = real(p, land.islands[0]);
				} else {

					let first = { island: null, distance: Infinity };
					let second = { island: null, distance: Infinity };

					land.islands.forEach((island, k) => {

						const distance = away(p, outlines[k]);

						if(distance < first.distance) {
							second = first;
							first = { island, distance };
						} else if(distance < second.distance) {
							second = { island, distance };
						}

					});

					const mix = .5 * (1 - MapScene.smooth(0, blend, second.distance - first.distance));
					altitude = (mix > 0) ? real(p, first.island) * (1 - mix) + real(p, second.island) * mix : real(p, first.island);

				}

				altitudes[j * this.nx + i] = altitude;

			}
		}

		this.empty = (MapSceneRelief.fill(altitudes, this.nx, this.nn) === false);

		if(this.empty) {
			return;
		}

		this.altitudes = MapSceneRelief.smoothen(altitudes, this.nx, this.nn);

		// La hauteur se compte depuis l'altitude moyenne des sommets des zones : la structure reste autour du plan où se pose la caméra
		const corners = [...geometry.zones.values()].flat();
		this.base = corners.reduce((sum, p) => sum + this.altitude(p.x, p.n), 0) / corners.length;

		this.lattice(geometry);

	}

	/**
	 * Les sommets du sol rendu (ground()) : au pas de la grille sous les plateaux, plus longs ailleurs. height() lit ces mêmes
	 * triangles, pour que ce qui se pose sur le sol ne flotte ni ne s'enfonce entre deux sommets éloignés. Les pas grandissent
	 * d'un mètre à la fois, sous les plateaux comme ailleurs, jusqu'à ce que le sol tienne sous GROUND_TRIANGLES : le plus fin qui
	 * tient.
	 */
	lattice(geometry) {

		const plateaus = geometry.land.islands.map(island => island.plateau);
		const spans = axis => plateaus.map(points => [Math.min(...points.map(p => p[axis])), Math.max(...points.map(p => p[axis]))]);
		const lay = pitch => {
			this.pitch = pitch;
			this.xs = MapSceneRelief.axis(this.x0, this.x1, spans('x'), pitch);
			this.ns = MapSceneRelief.axis(this.n0, this.n1, spans('n'), pitch);
		};

		lay(MapSceneRelief.STEP);

		while(2 * (this.xs.length - 1) * (this.ns.length - 1) > MapSceneRelief.GROUND_TRIANGLES) {
			lay(this.pitch + 1);
		}

		this.heights = new Float32Array(this.xs.length * this.ns.length);

		this.ns.forEach((n, j) => this.xs.forEach((x, i) => {
			this.heights[j * this.xs.length + i] = this.altitude(x, n) - this.base;
		}));

		// Les hauteurs que le terrain porte, sous les creux que les étendues d'eau y font (`carve()`)
		this.raw = this.heights.slice();
		this.carves = new Map();
		this.surface = null;

	}

	/**
	 * L'altitude en mètres d'une longitude et d'une latitude, lue entre les quatre points de l'IGN qui l'entourent, NaN hors des
	 * tuiles reçues. Le point (i, j) de la maille est au centre de sa case, en (i + .5) / divisions.
	 */
	static reader(relief) {

		const size = relief.size;
		const tiles = new Map(relief.tiles.map(tile => {
			const bytes = Uint8Array.from(atob(tile.altitudes), character => character.charCodeAt(0));
			return [tile.x +'/'+ tile.y, new DataView(bytes.buffer)];
		}));

		const at = (i, j) => {

			const x = Math.floor(i / size);
			const y = Math.floor(j / size);
			const view = tiles.get(x +'/'+ y);

			return (view === undefined) ? NaN : view.getInt16(((j - y * size) * size + (i - x * size)) * 2, true) / 10;

		};

		return (lng, lat) => {

			const fi = lng * relief.divisions.lng - .5;
			const fj = lat * relief.divisions.lat - .5;
			const i = Math.floor(fi);
			const j = Math.floor(fj);
			const u = fi - i;
			const v = fj - j;

			return (at(i, j) * (1 - u) + at(i + 1, j) * u) * (1 - v) + (at(i, j + 1) * (1 - u) + at(i + 1, j + 1) * u) * v;

		};

	}

	/**
	 * Un point sans altitude prend la moyenne de ses voisins qui en ont, de proche en proche. Rend faux quand la grille n'a aucune
	 * altitude.
	 */
	static fill(values, nx, nn) {

		let missing = [];

		for(let k = 0; k < values.length; k++) {
			if(Number.isNaN(values[k])) {
				missing.push(k);
			}
		}

		if(missing.length === values.length) {
			return false;
		}

		while(missing.length > 0) {

			const found = [];
			const left = [];

			for(const k of missing) {

				const i = k % nx;
				const j = (k - i) / nx;
				let sum = 0;
				let count = 0;

				for(const [di, dj] of [[-1, 0], [1, 0], [0, -1], [0, 1]]) {

					const ii = i + di;
					const jj = j + dj;

					if(ii >= 0 && jj >= 0 && ii < nx && jj < nn && Number.isNaN(values[jj * nx + ii]) === false) {
						sum += values[jj * nx + ii];
						count++;
					}

				}

				if(count > 0) {
					found.push([k, sum / count]);
				} else {
					left.push(k);
				}

			}

			// Les points d'une même passe se remplissent ensemble : leur ordre de lecture ne décide pas de leur altitude
			found.forEach(([k, value]) => values[k] = value);
			missing = left;

		}

		return true;

	}

	/**
	 * Une passe de moyenne sur chaque point et ses huit voisins : le relief de l'IGN garde le grain d'un talus ou d'un fossé, que
	 * la toile du pré découperait en facettes.
	 */
	static smoothen(values, nx, nn) {

		const smooth = new Float64Array(values.length);

		for(let j = 0; j < nn; j++) {
			for(let i = 0; i < nx; i++) {

				let sum = 0;
				let count = 0;

				for(let jj = Math.max(0, j - 1); jj <= Math.min(nn - 1, j + 1); jj++) {
					for(let ii = Math.max(0, i - 1); ii <= Math.min(nx - 1, i + 1); ii++) {
						sum += values[jj * nx + ii];
						count++;
					}
				}

				smooth[j * nx + i] = sum / count;

			}
		}

		return smooth;

	}

	/**
	 * L'altitude réelle sous un point de la scène, lue sur la grille, et celle de son bord au-delà.
	 */
	altitude(x, n) {

		const fi = (Math.max(this.x0, Math.min(this.x1, x)) - this.x0) / MapSceneRelief.STEP;
		const fj = (Math.max(this.n0, Math.min(this.n1, n)) - this.n0) / MapSceneRelief.STEP;
		const i = Math.min(Math.floor(fi), this.nx - 2);
		const j = Math.min(Math.floor(fj), this.nn - 2);
		const u = fi - i;
		const v = fj - j;
		const at = (a, b) => this.altitudes[b * this.nx + a];

		return (at(i, j) * (1 - u) + at(i + 1, j) * u) * (1 - v) + (at(i, j + 1) * (1 - u) + at(i + 1, j + 1) * u) * v;

	}

	/**
	 * La hauteur du sol rendu sous un point de la scène : celle du triangle de ground() qui le porte, coupé de la même diagonale,
	 * et celle du bord au-delà.
	 */
	height(x, n, heights = this.heights) {

		const i = MapSceneRelief.cell(this.xs, x);
		const j = MapSceneRelief.cell(this.ns, n);
		const u = Math.max(0, Math.min(1, (x - this.xs[i]) / (this.xs[i + 1] - this.xs[i])));
		const v = Math.max(0, Math.min(1, (n - this.ns[j]) / (this.ns[j + 1] - this.ns[j])));
		const at = (a, b) => heights[(j + b) * this.xs.length + i + a];

		// ground() coupe chaque case de son sommet (i + 1, j) à son sommet (i, j + 1)
		if(u + v <= 1) {
			return at(0, 0) + u * (at(1, 0) - at(0, 0)) + v * (at(0, 1) - at(0, 0));
		} else {
			return at(1, 1) + (1 - u) * (at(0, 1) - at(1, 1)) + (1 - v) * (at(1, 0) - at(1, 1));
		}

	}

	/**
	 * Le point `{ x, y, n }` où un rayon `{ origin, direction }` de la scène touche le sol levé de `base` mètres, ou null pour un
	 * rayon qui ne descend pas. Le rayon ne croise le sol qu'entre le plus haut et le plus bas de ses sommets : il s'y avance par
	 * pas jusqu'au premier qui passe sous le sol, puis se resserre entre ce pas et le précédent. Reprendre la hauteur sous le point
	 * trouvé ne converge pas sous une vue rasante, où chaque tour envoie le point plus loin que la pente ne le rattrape. Le point
	 * peut tomber derrière l'origine, qui est parfois sous le sol : la cible de la vue, que MapControls glisse à sa propre hauteur
	 * (`MapScene.seat()`). La direction est unitaire, comme celle d'un rayon de THREE.Raycaster : la précision se compte en mètres.
	 */
	onRelief(ray, base) {

		const { origin, direction } = ray;

		if(direction.y >= 0) {
			return null;
		}

		let low = Infinity;
		let high = -Infinity;

		for(const height of this.heights) {
			low = Math.min(low, height);
			high = Math.max(high, height);
		}

		const at = t => ({ x: origin.x + t * direction.x, y: origin.y + t * direction.y, n: -(origin.z + t * direction.z) });
		const above = t => {
			const point = at(t);
			return point.y - base - this.height(point.x, point.n);
		};

		const last = (base + low - origin.y) / direction.y;
		const step = MapSceneRelief.RAY_STEP / Math.hypot(direction.x, direction.z);
		let from = (base + high - origin.y) / direction.y;
		let to = from;

		while(to < last && above(to) > 0) {
			from = to;
			to = Math.min(last, to + step);
		}

		while(to - from > MapSceneRelief.RAY_PRECISION) {

			const middle = (from + to) / 2;

			if(above(middle) > 0) {
				from = middle;
			} else {
				to = middle;
			}

		}

		return at(to);

	}

	/**
	 * La case d'un axe croissant qui contient une valeur, la première ou la dernière au-delà de ses bords.
	 */
	static cell(values, value) {

		let low = 0;
		let high = values.length - 2;

		while(low < high) {

			const middle = (low + high + 1) >> 1;

			if(values[middle] <= value) {
				low = middle;
			} else {
				high = middle - 1;
			}

		}

		return low;

	}

	/**
	 * Les parts `t`, de 0 à 1 et croissantes, où une droite du plan de `a` à `b` passe d'un triangle du sol rendu à un autre : ses
	 * axes `xs` et `ns`, puis la diagonale que ground() trace dans chaque case. Entre deux parts, le sol est plan sous la droite.
	 */
	section(a, b) {

		const dx = b.x - a.x;
		const dn = b.n - a.n;
		const parts = [0, 1];

		const across = (values, from, delta) => {

			if(delta === 0) {
				return;
			}

			const low = Math.min(from, from + delta);
			const high = Math.max(from, from + delta);

			for(let i = MapSceneRelief.cell(values, low) + 1; i < values.length && values[i] < high; i++) {
				parts.push((values[i] - from) / delta);
			}

		};

		across(this.xs, a.x, dx);
		across(this.ns, a.n, dn);
		parts.sort((p, q) => p - q);

		// Dans une case, la diagonale tient u + v = 1, qui varie le long de la droite d'une pente constante
		const diagonals = [];

		for(let k = 1; k < parts.length; k++) {

			const t = (parts[k - 1] + parts[k]) / 2;
			const i = MapSceneRelief.cell(this.xs, a.x + t * dx);
			const j = MapSceneRelief.cell(this.ns, a.n + t * dn);
			const wide = this.xs[i + 1] - this.xs[i];
			const deep = this.ns[j + 1] - this.ns[j];
			const rate = dx / wide + dn / deep;

			if(rate === 0) {
				continue;
			}

			const crossed = ((1 - (a.x - this.xs[i]) / wide - (a.n - this.ns[j]) / deep) / rate);

			if(crossed > parts[k - 1] && crossed < parts[k]) {
				diagonals.push(crossed);
			}

		}

		return [...parts, ...diagonals].sort((p, q) => p - q);

	}

	/**
	 * Une matrice de la scène, colonne par colonne, levée à la hauteur du sol sous sa translation et cisaillée sur la pente : un
	 * point à `dx` mètres à l'est de son centre monte de la pente fois `dx`, et une verticale reste verticale.
	 */
	lean(elements) {

		const x = elements[12];
		const n = -elements[14];
		const half = MapSceneRelief.STEP / 2;
		const east = (this.height(x + half, n) - this.height(x - half, n)) / (2 * half);
		const north = (this.height(x, n + half) - this.height(x, n - half)) / (2 * half);
		const lift = this.height(x, n) - east * x - north * n;

		// y' = y + east · X - north · Z + lift, la scène tenant le nord en Z négatif
		for(let column = 0; column < 4; column++) {
			const c = column * 4;
			elements[c + 1] += east * elements[c] - north * elements[c + 2] + lift * elements[c + 3];
		}

	}

	/**
	 * Une géométrie posée à plat dans la scène, dont ne restent que les faces tournées vers le ciel : chacune se coupe au milieu de
	 * son plus long côté jusqu'à ce qu'aucun ne dépasse CUT mètres au sol, puis chaque sommet monte à la hauteur du sol sous lui.
	 * Les attributs se coupent avec les sommets, à mi-chemin des deux qu'ils joignent.
	 */
	drape(THREE, source) {

		const flat = source.index !== null ? source.toNonIndexed() : source;
		const names = Object.keys(flat.attributes);
		const sizes = names.map(name => flat.attributes[name].itemSize);
		const normal = flat.attributes.normal;

		let triangles = [];

		for(let t = 0; t < flat.attributes.position.count; t += 3) {
			if(normal.getY(t) > .5) {
				triangles.push([t, t + 1, t + 2].map(vertex => names.map((name, a) => Array.from({ length: sizes[a] }, (_, c) => flat.attributes[name].array[vertex * sizes[a] + c]))));
			}
		}

		if(flat !== source) {
			flat.dispose();
		}

		const position = names.indexOf('position');
		const ground = (a, b) => Math.hypot(a[position][0] - b[position][0], a[position][2] - b[position][2]);
		const middle = (a, b) => a.map((values, k) => values.map((value, c) => (value + b[k][c]) / 2));

		const done = [];

		while(triangles.length > 0) {

			const next = [];

			for(const triangle of triangles) {

				const sides = [0, 1, 2].map(k => ground(triangle[k], triangle[(k + 1) % 3]));
				const longest = sides.indexOf(Math.max(...sides));

				if(sides[longest] <= MapSceneRelief.CUT) {
					done.push(triangle);
					continue;
				}

				const a = triangle[longest];
				const b = triangle[(longest + 1) % 3];
				const c = triangle[(longest + 2) % 3];
				const m = middle(a, b);

				next.push([a, m, c], [m, b, c]);

			}

			triangles = next;

		}

		const draped = new THREE.BufferGeometry();

		names.forEach((name, a) => {

			const array = new Float32Array(done.length * 3 * sizes[a]);

			done.forEach((triangle, t) => triangle.forEach((vertex, k) => array.set(vertex[a], (t * 3 + k) * sizes[a])));

			draped.setAttribute(name, new THREE.BufferAttribute(array, sizes[a]));

		});

		const lifted = draped.attributes.position;

		for(let i = 0; i < lifted.count; i++) {
			lifted.setY(i, lifted.getY(i) + this.height(lifted.getX(i), -lifted.getZ(i)));
		}

		return draped;

	}

	/**
	 * Une géométrie tracée dans les axes de la scène autour de `origin`, que la scène pose déjà à la hauteur du sol sous ce point
	 * (`MapSceneDecor.settle()`), et qui suit le relief sommet par sommet : chacun monte de ce que le sol sous lui dépasse le sol
	 * sous l'origine. Un sommet garde son x et son z, donc un piquet reste vertical et une bande garde sa hauteur au-dessus du sol.
	 * Chaque bloc `{ first, last, x, z }` monte d'un seul tenant, de ce que le sol dépasse sous son pied (x, z) : un arbre de haie
	 * reste droit au lieu de pencher son houppier avec la pente.
	 */
	conform(geometry, origin, blocks = []) {

		const position = geometry.attributes.position;
		const below = this.height(origin.x, origin.n);
		const fixed = new Float64Array(position.count).fill(NaN);

		for(const { first, last, x, z } of blocks) {
			fixed.fill(this.height(origin.x + x, origin.n - z) - below, first, last);
		}

		for(let i = 0; i < position.count; i++) {
			const lift = Number.isNaN(fixed[i]) ? this.height(origin.x + position.getX(i), origin.n - position.getZ(i)) - below : fixed[i];
			position.setY(i, position.getY(i) + lift);
		}

		position.needsUpdate = true;
		geometry.boundingBox = null;
		geometry.computeBoundingSphere();

	}

	// La plus grande hauteur du sol rendu sous un rectangle au sol (`ScenePlanGeometry`), où se pose un bâtiment
	highest(rectangle) {
		return this.extreme(rectangle, Math.max, -Infinity);
	}

	/**
	 * La plus petite du sol que le terrain porte, où se pose une étendue d'eau : lue sans les creux, elle ne dépend ni de l'ordre
	 * des poses ni d'une étendue voisine qui a creusé avant elle
	 */
	lowest(rectangle) {
		return this.extreme(rectangle, Math.min, Infinity, this.raw);
	}

	/**
	 * La hauteur extrême des hauteurs `heights` du sol sous un rectangle, lue à ses coins, le long de ses côtés et en travers au demi-pas de la
	 * grille, et à chaque sommet du sol qu'il couvre. Le sol est plan dans chacun de ses triangles : son extrême sous le rectangle
	 * est l'un de ces sommets, ou tombe sur un côté entre deux points lus, à moins d'un demi-pas.
	 */
	extreme(rectangle, pick, start, heights = this.heights) {

		const G = ScenePlanGeometry;
		const steps = [rectangle.length, rectangle.width].map(size => Math.max(1, Math.ceil(2 * size / MapSceneRelief.STEP)));
		let extreme = start;

		for(let i = 0; i <= steps[0]; i++) {
			for(let j = 0; j <= steps[1]; j++) {
				const p = G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length * i / steps[0]), G.mul(rectangle.w, rectangle.width * j / steps[1])));
				extreme = pick(extreme, this.height(p.x, p.n, heights));
			}
		}

		const corners = [0, 1].flatMap(i => [0, 1].map(j => G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length * i), G.mul(rectangle.w, rectangle.width * j)))));
		const [x0, x1] = [Math.min(...corners.map(p => p.x)), Math.max(...corners.map(p => p.x))];
		const [n0, n1] = [Math.min(...corners.map(p => p.n)), Math.max(...corners.map(p => p.n))];

		for(let j = MapSceneRelief.cell(this.ns, n0); j < this.ns.length && this.ns[j] <= n1; j++) {
			for(let i = MapSceneRelief.cell(this.xs, x0); i < this.xs.length && this.xs[i] <= x1; i++) {

				const p = { x: this.xs[i], n: this.ns[j] };
				const along = G.dot(G.sub(p, rectangle.origin), rectangle.u);
				const across = G.dot(G.sub(p, rectangle.origin), rectangle.w);

				if(along >= 0 && along <= rectangle.length && across >= 0 && across <= rectangle.width) {
					extreme = pick(extreme, heights[j * this.xs.length + i]);
				}

			}
		}

		return extreme;

	}

	/**
	 * Le sol sous une étendue d'eau `key`, posée au plus bas de son emprise `rectangle` (`lowest()`), ou au niveau de son groupe
	 * (`level()`) : un sommet sous le rectangle, ou à moins d'une case de son bord, descend au niveau de l'eau s'il est plus haut,
	 * sans quoi la pente passerait au-dessus de l'eau dans un triangle qui coupe le bord. Il ne descend pas de plus que le dénivelé
	 * entre l'eau et le plus haut du terrain sous l'emprise : là où une case longue touche le bord, une rive plus haute que ce
	 * dénivelé surplombe l'eau plutôt que de s'ouvrir sur toute sa hauteur. Un sommet ne monte jamais : le creux ne remblaie pas le
	 * sol en aval, et l'eau surplombe la rive aval d'au plus la pente sur une case. Un sommet que deux emprises touchent prend la
	 * plus basse, et `rectangle` null rend au sol ses hauteurs.
	 *
	 * Rend null quand rien n'a changé, sinon la boite du sol changé, en mètres de la scène (les sommets refaits et les cases qui les
	 * entourent, qui descendent ou remontent avec eux), et dans `moved` les autres étendues dont le niveau a suivi leur groupe.
	 */
	carve(key, rectangle) {

		const before = this.carves.get(key) ?? null;
		const after = (rectangle === null) ? null : this.reach(rectangle);

		if(after === null) {
			this.carves.delete(key);
		} else {
			this.carves.set(key, after);
		}

		const tops = new Map([...this.carves].map(([other, carve]) => [other, carve.top]));
		this.level();

		const moved = [...this.carves].filter(([other, carve]) => other !== key && carve.top !== tops.get(other));
		const boxes = [before, after, ...moved.map(([, carve]) => carve)].filter(box => box !== null);

		if(boxes.length === 0) {
			return null;
		}

		const nx = this.xs.length;

		for(const box of boxes) {
			for(let j = box.j0; j <= box.j1; j++) {
				for(let i = box.i0; i <= box.i1; i++) {

					const k = j * nx + i;
					let height = this.raw[k];

					for(const carve of this.carves.values()) {
						if(carve.vertices.has(k)) {
							height = Math.min(height, Math.max(carve.top, this.raw[k] - (carve.highest - carve.top)));
						}
					}

					this.heights[k] = height;

					if(this.surface !== null) {
						this.surface.attributes.position.setY(k, this.heights[k]);
						this.surface.attributes.reliefAltitude.setX(k, this.heights[k] + this.base);
					}

				}
			}
		}

		// La sphère englobante se recalcule au prochain rendu, sur les hauteurs neuves
		if(this.surface !== null) {
			this.surface.attributes.position.needsUpdate = true;
			this.surface.attributes.reliefAltitude.needsUpdate = true;
			this.surface.boundingSphere = null;
		}

		return {
			x0: this.xs[Math.max(0, Math.min(...boxes.map(box => box.i0)) - 1)],
			x1: this.xs[Math.min(this.xs.length - 1, Math.max(...boxes.map(box => box.i1)) + 1)],
			n0: this.ns[Math.max(0, Math.min(...boxes.map(box => box.j0)) - 1)],
			n1: this.ns[Math.min(this.ns.length - 1, Math.max(...boxes.map(box => box.j1)) + 1)],
			moved: moved.map(([other]) => other)
		};

	}

	/**
	 * Le creux d'une étendue d'eau sous `rectangle`, sans son niveau : la boite de ses cases (`cover()`), les sommets que le creux
	 * peut abaisser (`near()`), le plus bas et le plus haut du terrain sous l'emprise.
	 */
	reach(rectangle) {

		const box = this.cover(rectangle);
		const vertices = new Set();

		for(let j = box.j0; j <= box.j1; j++) {
			for(let i = box.i0; i <= box.i1; i++) {
				if(this.near(rectangle, i, j)) {
					vertices.add(j * this.xs.length + i);
				}
			}
		}

		return {
			...box,
			rectangle,
			vertices,
			lowest: this.lowest(rectangle),
			highest: this.extreme(rectangle, Math.max, -Infinity, this.raw)
		};

	}

	/**
	 * Le niveau `top` de chaque étendue d'eau : deux étendues dont un sommet creusé de l'une est à une case au plus d'un sommet
	 * creusé de l'autre se rejoignent, et chaque groupe ainsi formé de proche en proche prend le plus bas de leurs terrains. Il se
	 * refait sur toutes les étendues à chaque creux, pour ne dépendre ni de l'ordre des poses ni de celle qui vient de bouger.
	 * Les sommets se comparent un à un, et non les boites : une étendue tournée ne creuse pas les coins de la sienne.
	 */
	level() {

		const nx = this.xs.length;
		const carves = [...this.carves.values()];
		const touch = (a, b) => {

			if(a.i0 > b.i1 + 1 || b.i0 > a.i1 + 1 || a.j0 > b.j1 + 1 || b.j0 > a.j1 + 1) {
				return false;
			}

			for(const k of a.vertices) {

				const i = k % nx;
				const j = (k - i) / nx;

				for(let dj = -1; dj <= 1; dj++) {
					for(let di = -1; di <= 1; di++) {
						if(i + di >= 0 && i + di < nx && b.vertices.has((j + dj) * nx + i + di)) {
							return true;
						}
					}
				}

			}

			return false;

		};

		carves.forEach(carve => {
			carve.top = carve.lowest;
		});

		for(let lowered = true; lowered; ) {

			lowered = false;

			for(const a of carves) {
				for(const b of carves) {
					if(b.top < a.top && touch(a, b)) {
						a.top = b.top;
						lowered = true;
					}
				}
			}

		}

	}

	/**
	 * Le sommet (`i`, `j`) est sous le rectangle, ou à moins d'une case de son bord : sommet d'un triangle du sol qui peut couper
	 * le bord. La case se mesure en diagonale, le rectangle pouvant être tourné.
	 */
	near(rectangle, i, j) {

		const G = ScenePlanGeometry;
		const spacing = (values, k) => Math.max(...[k - 1, k].filter(l => l >= 0 && l + 1 < values.length).map(l => values[l + 1] - values[l]));
		const margin = Math.hypot(spacing(this.xs, i), spacing(this.ns, j));
		const p = { x: this.xs[i], n: this.ns[j] };
		const along = G.dot(G.sub(p, rectangle.origin), rectangle.u);
		const across = G.dot(G.sub(p, rectangle.origin), rectangle.w);

		return along >= -margin && along <= rectangle.length + margin && across >= -margin && across <= rectangle.width + margin;

	}

	// Les sommets des cases du sol que touche la boite d'un rectangle, en indices de la grille
	cover(rectangle) {

		const G = ScenePlanGeometry;
		const corners = [0, 1].flatMap(i => [0, 1].map(j => G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length * i), G.mul(rectangle.w, rectangle.width * j)))));

		return {
			i0: MapSceneRelief.cell(this.xs, Math.min(...corners.map(p => p.x))),
			i1: MapSceneRelief.cell(this.xs, Math.max(...corners.map(p => p.x))) + 1,
			j0: MapSceneRelief.cell(this.ns, Math.min(...corners.map(p => p.n))),
			j1: MapSceneRelief.cell(this.ns, Math.max(...corners.map(p => p.n))) + 1
		};

	}

	/**
	 * Le soubassement d'un objet posé au plus haut du sol sous lui (`highest()`) : une bande verticale sur le tour du rectangle, de
	 * `top` jusqu'à PLINTH_SINK sous le sol, coupée tous les STEP mètres pour descendre avec lui, en coordonnées de la scène. Un objet
	 * de niveau sur une pente ne laisse ainsi pas de jour entre lui et le sol.
	 */
	plinth(THREE, rectangle, top) {

		const G = ScenePlanGeometry;
		const corners = [[0, 0], [1, 0], [1, 1], [0, 1]].map(([i, j]) => G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length * i), G.mul(rectangle.w, rectangle.width * j))));
		const centre = G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length / 2), G.mul(rectangle.w, rectangle.width / 2)));
		const positions = [];
		const normals = [];

		corners.forEach((a, k) => {

			const b = corners[(k + 1) % corners.length];
			const cuts = Math.max(1, Math.ceil(G.length(G.sub(b, a)) / MapSceneRelief.STEP));
			const middle = G.lerp(a, b, .5);
			const out = { x: middle.x - centre.x, z: centre.n - middle.n };
			const length = Math.hypot(out.x, out.z) || 1;

			// La face (p haut, p bas, q bas) regarde du côté (-dz, dx) du sens de p vers q, en coordonnées de la scène : le tour se
			// prend dans le sens qui la tourne vers l'extérieur
			const inward = ((-(a.n - b.n) * out.x + (b.x - a.x) * out.z) < 0);
			const [from, to] = inward ? [b, a] : [a, b];

			for(let c = 0; c < cuts; c++) {

				const p = G.lerp(from, to, c / cuts);
				const q = G.lerp(from, to, (c + 1) / cuts);
				const [pTop, pBottom, qTop, qBottom] = [[p.x, top, -p.n], [p.x, this.height(p.x, p.n) - MapSceneRelief.PLINTH_SINK, -p.n], [q.x, top, -q.n], [q.x, this.height(q.x, q.n) - MapSceneRelief.PLINTH_SINK, -q.n]];

				positions.push(...pTop, ...pBottom, ...qBottom, ...pTop, ...qBottom, ...qTop);

				for(let v = 0; v < 6; v++) {
					normals.push(out.x / length, 0, out.z / length);
				}

			}

		});

		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3));

		return geometry;

	}

	/**
	 * Les pas d'un axe du sol : `pitch` sous les îlots, COARSE jusqu'au bord de la grille, puis de plus en plus longs jusqu'à OUTER.
	 * Un pas plus long que STEP allonge COARSE dans la même mesure.
	 */
	static axis(from, to, fine, pitch = MapSceneRelief.STEP) {

		const coarse = MapSceneRelief.COARSE * pitch / MapSceneRelief.STEP;
		const values = [];

		for(let at = from; at < to; ) {

			values.push(at);

			const inside = fine.some(([a, b]) => at >= a - coarse && at < b);
			at = Math.min(to, at + (inside ? pitch : coarse));

		}

		values.push(to);

		let step = coarse;

		for(let at = from; at > from - MapSceneRelief.OUTER; ) {
			step *= MapSceneRelief.GROWTH;
			at -= step;
			values.unshift(at);
		}

		step = coarse;

		for(let at = to; at < to + MapSceneRelief.OUTER; ) {
			step *= MapSceneRelief.GROWTH;
			at += step;
			values.push(at);
		}

		return values;

	}

	/**
	 * Le sol drapé, qui prend la place du disque du pré : la même matière, que la saison et le terrain continuent de peindre,
	 * estompée et tracée de ses courbes de niveau.
	 */
	ground(THREE, geometry, material) {

		const xs = this.xs;
		const ns = this.ns;

		const count = xs.length * ns.length;
		const positions = new Float32Array(count * 3);
		const altitudes = new Float32Array(count);

		ns.forEach((n, j) => xs.forEach((x, i) => {

			const k = j * xs.length + i;

			positions.set([x, this.heights[k], -n], k * 3);
			altitudes[k] = this.heights[k] + this.base;

		}));

		const index = [];

		for(let j = 0; j < ns.length - 1; j++) {
			for(let i = 0; i < xs.length - 1; i++) {
				const a = j * xs.length + i;
				const c = a + xs.length;
				index.push(a, a + 1, c, a + 1, c + 1, c);
			}
		}

		const mesh = new THREE.BufferGeometry();
		mesh.setAttribute('position', new THREE.BufferAttribute(positions, 3));
		mesh.setIndex(index);
		mesh.computeVertexNormals();

		// L'écart d'éclairage se lit à la moyenne de la grille et non au plat : une pente régulière ne fonce pas tout le pré
		const sun = new THREE.Vector3(...MapSceneRelief.SHADE_SUN).normalize();
		const normals = mesh.attributes.normal;
		const lit = Float32Array.from({ length: count }, (_, k) => normals.getX(k) * sun.x + normals.getY(k) * sun.y + normals.getZ(k) * sun.z);

		let sum = 0;
		let seen = 0;

		ns.forEach((n, j) => xs.forEach((x, i) => {
			if(x >= this.x0 && x <= this.x1 && n >= this.n0 && n <= this.n1) {
				sum += lit[j * xs.length + i];
				seen++;
			}
		}));

		const average = sum / seen;
		const shade = lit.map(value => Math.max(MapSceneRelief.SHADE_MIN, Math.min(MapSceneRelief.SHADE_MAX, 1 + MapSceneRelief.SHADE * (value - average))));

		mesh.setAttribute('reliefShade', new THREE.BufferAttribute(shade, 1));
		mesh.setAttribute('reliefAltitude', new THREE.BufferAttribute(altitudes, 1));

		// Le sol se creuse encore sous une étendue d'eau posée après lui (`carve()`)
		this.surface = mesh;

		// La lumière de la scène voit un sol plat : le nuanceur en aplats ferait basculer une pente dans l'aplat plus sombre, et seul
		// l'estompage dit la pente
		for(let k = 0; k < count; k++) {
			normals.setXYZ(k, 0, 1, 0);
		}

		this.contours(THREE, geometry, material);

		return mesh;

	}

	/**
	 * Les courbes de niveau, tracées par le nuanceur du pré sur l'altitude de chaque point : aucun triangle de plus. Elles ne
	 * passent ni sur l'emprise d'une parcelle, ni derrière une bâche vue de la caméra, que la paroi cache comme elle cache le sol.
	 */
	contours(THREE, geometry, material) {

		const box = { x: this.x0, z: -this.n1, width: this.x1 - this.x0, depth: this.n1 - this.n0 };
		const mask = this.mask(THREE, geometry, box);
		const shelters = this.shelters(THREE, geometry);

		this.disposables.push(mask, shelters.texture);

		const uniforms = {
			reliefContourStep: { value: MapSceneRelief.CONTOUR_STEP },
			reliefContourMajor: { value: MapSceneRelief.CONTOUR_MAJOR },
			reliefMask: { value: mask },
			reliefMaskBox: { value: new THREE.Vector4(box.x, box.z, box.width, box.depth) },
			reliefShelters: { value: shelters.texture },
			reliefShelterCount: { value: shelters.count }
		};

		const before = material.onBeforeCompile;
		const key = material.customProgramCacheKey();

		material.onBeforeCompile = (shader, renderer) => {

			before(shader, renderer);
			Object.assign(shader.uniforms, uniforms);

			shader.vertexShader = 'attribute float reliefShade;\nattribute float reliefAltitude;\nvarying float vReliefShade;\nvarying float vReliefAltitude;\nvarying vec3 vReliefWorld;\n' + shader.vertexShader.replace('#include <project_vertex>', '#include <project_vertex>\nvReliefShade = reliefShade;\nvReliefAltitude = reliefAltitude;\nvReliefWorld = (modelMatrix * vec4(transformed, 1.0)).xyz;');

			shader.fragmentShader = `uniform float reliefContourStep;
uniform float reliefContourMajor;
uniform sampler2D reliefMask;
uniform vec4 reliefMaskBox;
uniform highp sampler2D reliefShelters;
uniform int reliefShelterCount;
varying float vReliefShade;
varying float vReliefAltitude;
varying vec3 vReliefWorld;

// Le rayon qui part du sol vers la caméra traverse-t-il la boite d'une serre ? Une caméra orthographique regarde partout dans la
// même direction, la troisième ligne de la matrice de vue
bool reliefSheltered(vec3 point) {
	vec3 toward = vec3(viewMatrix[0][2], viewMatrix[1][2], viewMatrix[2][2]);
	for(int i = 0; i < reliefShelterCount; i++) {
		vec4 frame = texelFetch(reliefShelters, ivec2(0, i), 0);
		vec4 extent = texelFetch(reliefShelters, ivec2(1, i), 0);
		vec2 along = frame.zw;
		vec2 across = vec2(-along.y, along.x);
		vec2 offset = point.xz - frame.xy;
		vec3 origin = vec3(dot(offset, along), point.y - extent.w, dot(offset, across));
		vec3 direction = vec3(dot(toward.xz, along), toward.y, dot(toward.xz, across));
		vec3 inverse = 1. / direction;
		vec3 t1 = (-extent.xyz - origin) * inverse;
		vec3 t2 = (extent.xyz - origin) * inverse;
		vec3 near = min(t1, t2);
		vec3 far = max(t1, t2);
		float enter = max(max(near.x, near.y), near.z);
		float leave = min(min(far.x, far.y), far.z);
		if(leave >= max(enter, 0.)) {
			return true;
		}
	}
	return false;
}
` + shader.fragmentShader.replace('#include <color_fragment>', `#include <color_fragment>
	diffuseColor.rgb *= vReliefShade;
	{
		float minorAt = vReliefAltitude / reliefContourStep;
		float minor = 1. - clamp(abs(fract(minorAt - .5) - .5) / max(fwidth(minorAt), 1e-4) / .8, 0., 1.);
		float majorAt = vReliefAltitude / (reliefContourStep * reliefContourMajor);
		float major = 1. - clamp(abs(fract(majorAt - .5) - .5) / max(fwidth(majorAt), 1e-4) / 1.6, 0., 1.);
		float line = max(minor * .45, major * .8);
		if(line > 0.) {
			vec2 maskAt = (vReliefWorld.xz - reliefMaskBox.xy) / reliefMaskBox.zw;
			float masked = (maskAt.x < 0. || maskAt.y < 0. || maskAt.x > 1. || maskAt.y > 1.) ? 0. : texture2D(reliefMask, maskAt).r;
			if(masked < 1. && reliefSheltered(vReliefWorld) == false) {
				diffuseColor.rgb = mix(diffuseColor.rgb, vec3(.24, .27, .2), line * (1. - masked));
			}
		}
	}`);

		};

		material.customProgramCacheKey = () => key + '-relief';
		material.needsUpdate = true;

	}

	/**
	 * L'emprise de chaque parcelle cultivée vue du ciel, planches, allées et serres comprises : l'enveloppe de ses planches et,
	 * sous un abri, de tout le jardin.
	 */
	mask(THREE, geometry, box) {

		const resolution = Math.max(MapSceneRelief.MASK_RESOLUTION, Math.max(box.width, box.depth) / MapSceneRelief.MASK_SIZE);
		const canvas = document.createElement('canvas');
		canvas.width = Math.ceil(box.width / resolution);
		canvas.height = Math.ceil(box.depth / resolution);

		const context = canvas.getContext('2d');
		context.fillStyle = '#fff';
		context.strokeStyle = '#fff';
		context.lineJoin = 'round';

		const plots = new Map();
		const add = (key, points, margin) => {

			if(plots.has(key) === false) {
				plots.set(key, { points: [], margin });
			}

			const entry = plots.get(key);
			entry.points.push(...points);
			entry.margin = Math.max(entry.margin, margin);

		};

		for(const bed of geometry.beds) {
			add(bed.plot, geometry.corners(bed), bed.greenhouse ? MapSceneRelief.MASK_MARGIN_SHELTER : MapSceneRelief.MASK_MARGIN);
		}

		for(const { plot, points } of geometry.plots.values()) {
			if(plot.mode === 'greenhouse') {
				add(plot.id, points, MapSceneRelief.MASK_MARGIN_SHELTER);
			}
		}

		for(const { points, margin } of plots.values()) {

			context.beginPath();

			ScenePlanGeometry.hull(points).forEach((p, i) => {
				const at = [(p.x - box.x) / resolution, (-p.n - box.z) / resolution];
				if(i === 0) {
					context.moveTo(...at);
				} else {
					context.lineTo(...at);
				}
			});

			context.closePath();
			context.fill();
			context.lineWidth = margin * 2 / resolution;
			context.stroke();

		}

		const texture = new THREE.CanvasTexture(canvas);
		texture.flipY = false;

		return texture;

	}

	/**
	 * La boite de chaque serre, deux texels par serre : son centre et son axe au sol, puis ses demi-longueur, demi-hauteur,
	 * demi-largeur et la hauteur de son milieu. La boite tient la bâche penchée sur la pente.
	 */
	shelters(THREE, geometry) {

		const boxes = [];

		for(const { plot, rectangle } of geometry.plots.values()) {

			if(plot.mode !== 'greenhouse') {
				continue;
			}

			const greenhouse = ScenePlanGeometry.greenhouse(plot.decorGreenhouse, rectangle.width);
			const G = ScenePlanGeometry;
			const center = G.add(rectangle.origin, G.add(G.mul(rectangle.u, rectangle.length / 2), G.mul(rectangle.w, rectangle.width / 2)));
			const corners = [[0, 0], [1, 0], [0, 1], [1, 1]]
				.map(([a, b]) => G.add(rectangle.origin, G.add(G.mul(rectangle.u, a * rectangle.length), G.mul(rectangle.w, b * rectangle.width))))
				.map(p => this.height(p.x, p.n));
			const low = Math.min(...corners);
			const high = Math.max(...corners) + greenhouse.height;

			// L'axe se donne dans le plan de la scène, x à l'est et z au sud
			boxes.push(center.x, -center.n, rectangle.u.x, -rectangle.u.n, rectangle.length / 2, (high - low) / 2, rectangle.width / 2, (high + low) / 2);

		}

		// Une texture ne peut pas être vide : sans serre, elle garde une ligne que le nuanceur ne lit pas
		const count = boxes.length / 8;
		const data = new Float32Array(Math.max(count, 1) * 8);
		data.set(boxes);

		const texture = new THREE.DataTexture(data, 2, Math.max(count, 1), THREE.RGBAFormat, THREE.FloatType);
		texture.needsUpdate = true;

		return { texture, count };

	}

	dispose() {
		this.disposables.forEach(disposable => disposable.dispose());
	}

}

/**
 * Le champ d'une structure tel que le plan 3D le dessine, sans écran : la terre des planches, les abris, les cultures, le décor et
 * l'équipe, posés sous `parent`. Il ne connait ni rendu, ni caméra, ni conteneur : le plan y ajoute ses gestes et sa feuille de
 * pose (`MapSceneDecor.attach()`), une autre scène le pose tel quel.
 *
 * `soil` et `soilGreenhouse` sont les matériaux de la terre, à l'air et sous abri, dont l'appelant tient la couleur. `shelter()` rend
 * le matériau de la bâche d'un abri, `{ film, meshes, pieces, rectangle }` : le champ pose sa bâche dans
 * `meshes`, tout ce qui le dessine dans `pieces`, et son rectangle. `arches` est le matériau des arceaux. `spacing` écarte les pieds
 * sur le rang (`MapSceneCrops`), `diameterLabel` est le gabarit de la pastille d'un tuyau ou null (`MapSceneDecor`), `changed()`
 * dit que l'ombre est à refaire.
 */
class MapSceneField {

	// L'œil d'ouverture du plan, face auquel se tire le décor par défaut : le même décor où que le champ se montre
	static EYE = { x: MapScene.OPENING_EYE.x * MapScene.OPENING_DISTANCE, y: MapScene.OPENING_DISTANCE, z: -MapScene.OPENING_EYE.n * MapScene.OPENING_DISTANCE };

	// `avatars` est l'équipe avec ses avatars (`SceneCrew.prepare()`), que le bourg ne donne pas
	constructor({ socle, plan, geometry, parent, gradientMap, wind, soil, soilGreenhouse, shelter, arches, spacing, diameterLabel, changed, avatars = null }) {

		const { THREE, addons } = socle;

		this.pieces = [];

		// La place des sommets de chaque planche dans la terre fusionnée, que MapSceneRuins efface et remet
		this.slabs = new Map();

		// Une géométrie par terre et non par planche : chaque maillage coûte un appel de dessin, et une structure en compte des centaines
		for(const greenhouse of [false, true]) {

			const beds = geometry.beds.filter(bed => bed.greenhouse === greenhouse);
			const slabs = beds.map(bed => MapScene.bed(THREE, geometry, bed)).map(slab => (geometry.relief === null) ? slab : MapSceneField.drape(THREE, geometry, slab));

			if(slabs.length > 0) {

				const mesh = new THREE.Mesh(addons.mergeGeometries(slabs), greenhouse ? soilGreenhouse : soil);
				mesh.position.y = MapScene.BED_BASE;
				mesh.receiveShadow = true;
				parent.add(mesh);
				this.pieces.push(mesh);

				let start = 0;

				beds.forEach((bed, i) => {
					const count = slabs[i].attributes.position.count;
					this.slabs.set(bed.id, { mesh, start, count, saved: null });
					start += count;
				});

				slabs.forEach(slab => slab.dispose());

			}

		}

		for(const { plot, rectangle } of geometry.plots.values()) {

			if(plot.mode !== 'greenhouse') {
				continue;
			}

			const greenhouse = ScenePlanGeometry.greenhouse(plot.decorGreenhouse, rectangle.width);

			const held = shelter();

			// L'abri se penche sur la pente sous son milieu : ses arceaux restent droits, et sa bâche, qui ne se coupe pas, garde sa forme.
			// Un sol qui se creuse ou se bombe sous l'abri passerait au-dessus de son bord : l'abri monte de ce qui dépasse le plus
			const middle = ScenePlanGeometry.add(rectangle.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rectangle.u, rectangle.length / 2), ScenePlanGeometry.mul(rectangle.w, rectangle.width / 2)));
			const lean = geometry.lean(new THREE.Matrix4().makeTranslation(middle.x, 0, -middle.n));
			const above = MapSceneField.above(THREE, geometry, rectangle, lean);
			lean.premultiply(new THREE.Matrix4().makeTranslation(0, above, 0)).multiply(new THREE.Matrix4().makeTranslation(-middle.x, 0, middle.n));
			held.base = geometry.height(middle.x, middle.n) + above;

			const film = new THREE.Mesh(MapScene.tunnel(THREE, rectangle, greenhouse).applyMatrix4(lean), held.film);
			// Les surfaces transparentes se trient par la distance de leur centre : une bâche de trente mètres passerait tantôt devant,
			// tantôt derrière l'ombre des paysans qu'elle couvre. Dessinée la dernière, elle la recouvre sous tous les angles
			film.renderOrder = 10;

			const ribs = new THREE.LineSegments(MapScene.arches(THREE, rectangle, greenhouse).applyMatrix4(lean), arches);

			parent.add(film, ribs);
			this.pieces.push(film, ribs);
			held.meshes.push(film);
			held.pieces.push(film, ribs);
			held.rectangle = rectangle;

		}

		const cropGroup = new THREE.Group();
		parent.add(cropGroup);

		this.crops = new MapSceneCrops(socle, plan, geometry, cropGroup, gradientMap, wind, soil, spacing);

		this.crewGroup = new THREE.Group();
		parent.add(this.crewGroup);

		const decorGroup = new THREE.Group();
		parent.add(decorGroup);

		this.decor = new MapSceneDecor({
			socle, plan, geometry,
			parent: decorGroup,
			gradientMap,
			diameterLabel,
			changed,
			walked: () => {
				this.crew.reroute(geometry, this.decor.solids(), this.decor.gates());
			}
		});

		// Les paysans se montent après le décor, qu'ils contournent, et retracent leur réseau de marche à chaque geste sur le décor
		this.crew = new SceneCrew(socle, plan, geometry, this.crewGroup, gradientMap, this.decor.solids(), this.decor.gates(), MapSceneCrops.random, avatars);

	}

	/**
	 * De combien le sol passe au-dessus du plan où se penche un abri, au plus, sur son rectangle parcouru au pas de la grille du
	 * relief : zéro sur un sol plat ou sans relief.
	 */
	static above(THREE, geometry, rectangle, lean) {

		if(geometry.relief === null) {
			return 0;
		}

		const e = lean.elements;
		const point = new THREE.Vector3();
		const steps = [rectangle.length, rectangle.width].map(size => Math.max(1, Math.ceil(size / MapSceneRelief.STEP)));
		let above = 0;

		for(let i = 0; i <= steps[0]; i++) {
			for(let j = 0; j <= steps[1]; j++) {

				const p = ScenePlanGeometry.add(rectangle.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rectangle.u, rectangle.length * i / steps[0]), ScenePlanGeometry.mul(rectangle.w, rectangle.width * j / steps[1])));

				// La hauteur du plan penché sous ce point, la matrice étant posée au milieu de l'abri
				point.set(p.x - e[12], 0, -p.n - e[14]).applyMatrix4(lean);
				above = Math.max(above, geometry.height(p.x, p.n) - point.y);

			}
		}

		return above;

	}

	/**
	 * La terre d'une planche sur le relief : sa face du dessus seule, les autres que le nuanceur de la terre écarte ne se coupant pas.
	 */
	static drape(THREE, geometry, slab) {

		const draped = geometry.relief.drape(THREE, slab);
		slab.dispose();

		return draped;

	}

	/**
	 * Le décor par défaut, face à l'œil d'ouverture du plan, après les pancartes et l'enseigne qu'il évite : `boards` est la liste
	 * des pancartes (`MapSceneBoards.boards`), `sign` l'emprise de l'enseigne, vides pour une scène qui n'en pose pas.
	 */
	byDefault(plan, { boards, sign }) {
		this.decor.byDefault(plan, { boards, sign, eye: MapSceneField.EYE });
	}

	dispose() {

		this.crops.dispose();
		this.decor.dispose();
		this.crew.dispose();

		this.pieces.forEach(piece => piece.geometry.dispose());

	}

}

/**
 * Des rangées qui défilent de côté sans barre, qui les grandirait dès qu'elles débordent : chacune s'estompe du côté où il en reste, la
 * molette d'une souris la fait défiler de côté, et une flèche se pose au milieu de son bord, dans l'estompe, sous un pointeur fin
 * seulement (scene.css). Les flèches désignent leur rangée par son nom dans `rows`, et partagent son parent positionné. Ce sont les
 * rangées du catalogue du décor et la clé des cultures d'un téléphone.
 */
class MapSceneScroll {

	constructor(rows, arrows) {

		this.rows = rows;
		this.arrows = arrows;

		this.observer = new ResizeObserver(() => this.shade());

		for(const row of Object.values(this.rows)) {
			row.addEventListener('scroll', () => this.shade(), { passive: true });
			row.addEventListener('wheel', event => MapSceneScroll.wheel(row, event), { passive: false });
			this.observer.observe(row);
		}

		// Une flèche avance d'un peu moins que la largeur montrée : l'entrée coupée au bord reste en vue, de l'autre côté
		for(const arrow of this.arrows) {
			arrow.addEventListener('click', () => {
				const row = this.rows[arrow.dataset.sceneScroll];
				// Sans animation pour qui l'a coupée dans son système : la rangée saute à sa place
				const behavior = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
				row.scrollBy({ left: Number(arrow.dataset.direction) * row.clientWidth * 0.8, behavior });
			});
		}

	}

	/**
	 * Une rangée qui déborde s'estompe du côté où il en reste. Ses flèches se posent au milieu de ses bords, de ce même côté, tant
	 * qu'elle se voit : les familles du décor restent en place, invisibles, sous une emprise choisie. Toutes les rangées se mesurent,
	 * puis toutes s'écrivent.
	 */
	shade() {

		const rows = Object.fromEntries(Object.entries(this.rows).map(([name, row]) => [name, {
			row,
			left: row.scrollLeft > 1,
			right: row.scrollLeft + row.clientWidth < row.scrollWidth - 1,
			shown: (row.offsetParent !== null && getComputedStyle(row).visibility === 'visible'),
			top: row.offsetTop + row.offsetHeight / 2,
			start: row.offsetLeft,
			end: row.offsetLeft + row.offsetWidth
		}]));

		for(const { row, left, right } of Object.values(rows)) {
			row.classList.toggle('map-scene-more-left', left);
			row.classList.toggle('map-scene-more-right', right);
		}

		for(const arrow of this.arrows) {

			const { left, right, shown, top, start, end } = rows[arrow.dataset.sceneScroll];
			const forward = (arrow.dataset.direction === '1');

			arrow.classList.toggle('hide', shown === false || (forward ? right : left) === false);
			arrow.style.top = top +'px';
			arrow.style.left = (forward ? end : start) +'px';

		}

	}

	dispose() {
		this.observer.disconnect();
	}

	/**
	 * La molette d'une souris ne tourne que de haut en bas : sur une rangée qui déborde, elle la fait défiler de côté. Au bout de la
	 * rangée, elle rend la main à la page. Un pavé tactile qui défile déjà de côté garde son geste.
	 */
	static wheel(row, event) {

		if(Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
			return;
		}

		// deltaY se compte en pixels, en lignes ou en pages selon la souris
		const delta = event.deltaY * [1, 16, row.clientWidth][event.deltaMode];
		const end = row.scrollWidth - row.clientWidth;

		if((delta < 0 && row.scrollLeft <= 0) || (delta > 0 && row.scrollLeft >= end - 1)) {
			return;
		}

		event.preventDefault();
		row.scrollLeft += delta;

	}

}

/**
 * La lumière et les teintes du pré au fil de l'année, reprises de la scène de la comptabilité pour que les deux scènes vivent la
 * même saison. Une table se lit sur le mois de scène, de 0 au 1er janvier à 12
 * au 31 décembre, en [mois, valeur], valeur étant un nombre ou une couleur, adoucie d'une clé à l'autre, et ne se lit pas au-delà
 * de MONTH_MAX. La scène n'a pas d'heure : le soleil est celui de midi.
 */
class MapSceneSeason {

	// Un hiver ne garde qu'un tiers de l'écart qui sépare la teinte pâle de celle de mars : ternie en entier puis aux deux tiers, la
	// scène de la comptabilité paraissait éteinte
	static WINTER_LIVELINESS = 2 / 3;

	// La verte tendre, sur laquelle la table de saison est réglée : une autre herbe garde son rapport à celle-ci (`setGrass()`)
	static SUMMER_GRASS = MapScene.GRASSES.tender.color;

	// Le mois de scène du 1er novembre, au-delà duquel les tables ne se lisent plus : la lumière de fin octobre se garde jusqu'à
	// fin décembre, sans aller plus loin dans le changement de teinte (décidé par Vincent), pendant que les arbres, les haies et
	// les cultures finissent leur année
	static MONTH_MAX = 10;

	// Les jours que met l'hiver des tables à arriver après le 1er janvier, en partant du plafond : lu d'un coup, il faisait sauter le
	// pré du 31 décembre au 1er janvier. Neuf jours gardent le creux de l'hiver et tiennent chaque pas d'un jour à trois niveaux de
	// couleur au plus, le soleil compris, qui en fait quatre en une semaine. En mois de scène, janvier comptant 31 jours
	static WINTER_ARRIVAL = 9;
	static WINTER_ARRIVAL_MONTH = MapSceneSeason.WINTER_ARRIVAL / 31;

	constructor(THREE) {

		this.THREE = THREE;

		const winter = (pale, march) => new THREE.Color(pale).lerp(new THREE.Color(march), MapSceneSeason.WINTER_LIVELINESS);
		const rgb = keys => keys.map(([m, color]) => [m, (typeof color === 'string' ? new THREE.Color(color) : color).toArray()]);

		this.grass = rgb([[0, winter('#a8c498', '#a8cf82')], [2, '#a8cf82'], [3.6, '#97cf6d'], [6, '#8fbf72'], [8, '#93c474'], [9.6, '#9bca7a'], [11, '#a6c98e'], [12, winter('#a8c498', '#a8cf82')]]);
		this.sky = rgb([[0, '#dde6ee'], [2.5, '#cfe8f5'], [6, '#bfe2f6'], [8.6, '#cfe3ee'], [9.8, '#dfe6e6'], [10.8, '#dde3e8'], [12, '#dde6ee']]);
		this.sun = rgb([[0, winter('#eef2ff', '#fff1d6')], [3, '#fff1d6'], [7, '#fff0c8'], [9.6, '#ffecd0'], [11, '#f3ead8'], [12, winter('#eef2ff', '#fff1d6')]]);
		// Le chemin du décor posé, qui pâlit l'hiver
		this.path = rgb([[0, winter('#ddd4bc', '#dccfae')], [2.4, '#dccfae'], [10.6, '#dccfae'], [11.4, winter('#ddd4bc', '#dccfae')], [12, winter('#ddd4bc', '#dccfae')]]);
		this.force = [[0, 1.55], [3, 2], [6.5, 2.25], [9.5, 1.9], [12, 1.55]];

		// Le soleil se pose à 150 fois cette valeur de haut, à 118 m de sa cible sur le sol, soit une hauteur de 38° au 1er janvier
		// et de 56° au 1er juillet. Le minimum reste au-dessus des 30° où la rampe toon fait perdre un palier au sol plat
		this.height = [[0, .62], [6, 1.15], [12, .62]];

		this.summer = new THREE.Color(MapSceneSeason.SUMMER_GRASS);
		this.setGrass({ color: MapSceneSeason.SUMMER_GRASS, seasonless: false });

	}

	/**
	 * Une autre herbe que la verte tendre garde, saison par saison, son rapport à celle-ci ; une herbe hors saison garde sa couleur.
	 */
	setGrass({ color, seasonless }) {

		const grass = new this.THREE.Color(color);

		this.fixed = seasonless ? grass.toArray() : null;
		this.grassTint = [grass.r / this.summer.r, grass.g / this.summer.g, grass.b / this.summer.b];

	}

	key(keys, m) {

		let i = 0;

		while(i < keys.length - 2 && m >= keys[i + 1][0]) {
			i++;
		}

		const [m0, a] = keys[i];
		const [m1, b] = keys[i + 1];
		const k = MapScene.smooth(0, 1, (m - m0) / (m1 - m0));

		if(typeof a === 'number') {
			return a + (b - a) * k;
		}

		return a.map((x, j) => x + (b[j] - x) * k);

	}

	at(date) {

		const year = Number(date.slice(0, 4));
		const month = Number(date.slice(5, 7)) - 1;
		const m = Math.min(MapSceneSeason.MONTH_MAX, month + (Number(date.slice(8, 10)) - 1) / new Date(Date.UTC(year, month + 1, 0)).getUTCDate());

		let light = this.read(m);

		// Le 1er janvier reprend la lumière du 31 décembre, celle du plafond, et rejoint les tables en WINTER_ARRIVAL jours
		if(m < MapSceneSeason.WINTER_ARRIVAL_MONTH) {

			const plateau = this.read(MapSceneSeason.MONTH_MAX);
			const k = MapScene.smooth(0, MapSceneSeason.WINTER_ARRIVAL_MONTH, m);

			light = Object.fromEntries(Object.entries(plateau).map(([name, a]) => {
				const b = light[name];
				return [name, (typeof a === 'number') ? a + (b - a) * k : a.map((x, j) => x + (b[j] - x) * k)];
			}));

		}

		const { height, ...rest } = light;

		return { ...rest, offset: new this.THREE.Vector3(-95, 150 * height, 70) };

	}

	/**
	 * Les tables lues au mois de scène `m`, la hauteur du soleil en facteur.
	 */
	read(m) {

		// L'appoint du ciel gagne 0,35 l'hiver : entier jusqu'au 13 février, ce gain retombe jusqu'au 19 mars. Son retour, du 28
		// novembre au 22 décembre, est au-delà du plafond
		const winterFill = (m < 6) ? 1 - MapScene.smooth(1.4, 2.6, m) : MapScene.smooth(10.9, 11.7, m);

		return {
			grass: this.fixed ?? this.key(this.grass, m).map((x, j) => x * this.grassTint[j]),
			sky: this.key(this.sky, m),
			sun: this.key(this.sun, m),
			force: this.key(this.force, m),
			// 1,25 le reste de l'année, celui de la scène de la comptabilité en plein jour
			fill: 1.25 + .35 * winterFill,
			height: this.key(this.height, m),
			path: this.key(this.path, m)
		};

	}

}

/**
 * La saison de la scène du plan, « Plan clair » (choisi par Vincent) : un ciel blanc, un soleil plus haut, et un pré pâle. Le
 * bourg garde les tables de `MapSceneSeason`, qu'il lit telles quelles.
 *
 * Toutes les herbes du plan sont de la même famille claire : la verte tendre et la verte pâle ont leur table, une herbe hors
 * saison sa couleur, et les autres la table partagée éclaircie de LIGHTEN vers le blanc, le traitement qui donne la table de la
 * verte tendre.
 */
class MapScenePlanSeason extends MapSceneSeason {

	// La part de blanc mêlée à la table partagée, en sRGB : le cran qui suit le dosage 3 de 40 % dans la série de la planche (70,
	// 55, 40 %), pour un vert un peu plus soutenu (demandé par Vincent)
	static LIGHTEN = .25;

	// Le mois de scène du 1er juillet, l'été que montre la vignette d'une herbe dans le décor (`grassColor()`)
	static SUMMER = 6;

	// Les herbes qui ont leur table dans le plan, mois par mois, et la teinte pâle de leur hiver, comme `MapSceneSeason`. La verte
	// tendre est la table partagée éclaircie de LIGHTEN, écrite valeur par valeur. La verte pâle est le dosage 2 de la planche, la
	// table partagée éclaircie de 55 %. Les deux gardent la marche des saisons et un hiver vert
	static TABLES = {
		tender: { winter: '#bed3b2', keys: [[2, '#bedba1'], [3.6, '#b1db92'], [6, '#abcf95'], [8, '#aed397'], [9.6, '#b4d79b'], [11, '#bcd7aa']] },
		pale: { winter: '#d8e4d1', keys: [[2, '#d8e9c7'], [3.6, '#d0e9bd'], [6, '#cde2c0'], [8, '#cee4c0'], [9.6, '#d2e7c3'], [11, '#d7e7cc']] }
	};

	// Un ciel blanc, à peine plus bleu l'été, qui reste en retrait derrière le pré pâle (« Plan clair », choisi par Vincent)
	static SKY = [[0, '#f5f7f6'], [6, '#f0f6fa'], [12, '#f5f7f6']];

	constructor(THREE) {

		super(THREE);

		const color = value => new THREE.Color(value).toArray();

		this.tables = Object.fromEntries(Object.entries(MapScenePlanSeason.TABLES).map(([grass, { winter, keys }]) => {
			const pale = new THREE.Color(winter).lerp(new THREE.Color(keys[0][1]), MapSceneSeason.WINTER_LIVELINESS).toArray();
			return [grass, [[0, pale], ...keys.map(([m, value]) => [m, color(value)]), [12, pale]]];
		}));

		this.sky = MapScenePlanSeason.SKY.map(([m, value]) => [m, color(value)]);

		// Le soleil se pose à 49° au 1er janvier et à 64° au 1er juillet, au lieu de 38 et 56 : des ombres courtes, qui ne couvrent
		// plus la planche voisine
		this.height = [[0, 0.9], [6, 1.6], [12, 0.9]];

		this.useGrass('tender');

	}

	/**
	 * Une teinte `#rrggbb` éclaircie de LIGHTEN vers le blanc, canal par canal. Le pré et la vignette passent tous deux par ici,
	 * sans quoi la vignette d'une herbe montrerait une autre teinte que le plan.
	 */
	static lighten(color) {

		return '#'+ [1, 3, 5].map(i => {
			const channel = parseInt(color.slice(i, i + 2), 16);
			return Math.round(channel + (255 - channel) * MapScenePlanSeason.LIGHTEN).toString(16).padStart(2, '0');
		}).join('');

	}

	/**
	 * La teinte qu'une herbe montre dans le décor, en vignette et sous les clôtures : son 1er juillet dans la scène du plan.
	 */
	static grassColor(grass) {

		const table = MapScenePlanSeason.TABLES[grass];

		if(table !== undefined) {
			return table.keys.find(([m]) => m === MapScenePlanSeason.SUMMER)[1];
		}

		const { color, seasonless } = MapScene.GRASSES[grass];

		return seasonless ? color : MapScenePlanSeason.lighten(color);

	}

	/**
	 * L'herbe du plan, par son nom : `setGrass()` reçoit une couleur, celle que le constructeur de `MapSceneSeason` lui passe
	 * avant que le plan ait posé ses tables, et ne saurait donc pas laquelle des herbes il peint.
	 */
	useGrass(grass) {

		this.setGrass(MapScene.GRASSES[grass]);
		this.grassName = grass;

	}

	read(m) {

		const light = super.read(m);
		const table = this.tables[this.grassName];

		if(table !== undefined) {
			light.grass = this.key(table, m);
		} else if(this.fixed === null) {
			const shared = '#'+ new this.THREE.Color().setRGB(...light.grass).getHexString();
			light.grass = new this.THREE.Color(MapScenePlanSeason.lighten(shared)).toArray();
		}

		return light;

	}

}

/**
 * Les cultures en place à une date : les plants de chaque série qui a un modèle, en une instance par
 * espèce, et le nom couché sur la planche de celle qui n'en a pas. Une série sans calendrier n'est
 * jamais posée, ni plants ni nom (arbitrage du 10/10).
 */
class MapSceneCrops {

	// Grossissement des plants, pour qu'une planche se lise à la vue d'ensemble
	static SCALE = 2.5;

	// Écart sur le rang, en fraction de l'emprise
	static DENSITY = 1.25;

	// Écart minimal entre deux pieds, en mètres
	static MIN_PITCH = .4;

	// Ce qu'une série laisse libre au bout de sa longueur, en mètres
	static INSET = .2;

	// Plus grande dimension qu'un jeune plant garde pour rester reconnaissable, en mètres
	static YOUNG_LEGIBLE = .56;

	// Une série qui tient un jardin sans planche y prend la taille d'une planche de 1 m, en 150 pieds au plus
	static WHOLE = { bed: 1, plants: 150 };

	// Le nom d'une culture sans modèle couvre les sept dixièmes de la largeur de sa planche
	static NAME_FILL = .7;

	// Pendant la lecture, une série entre sur sa planche en quelques jours au lieu de paraitre d'un coup
	static PRESENCE_IN = 5;

	// La levée d'un semis, du quatrième au vingt-quatrième jour : de .15 à sa taille de jeune plant
	static EMERGENCE = [4, 24];

	// Le coin le plus vivant du jour réunit les planches cultivées d'une même zone à moins de 30 m l'une de l'autre
	static LIVELY_REACH = 30;

	// La patate douce ne se reconnait à rien d'autre que ses tubercules, sous des lianes semblables à toute autre courante. Ils
	// sortent à demi de terre le dernier mois avant la récolte, quand ils grossissent assez pour fendre la butte, puis entiers
	// pendant la récolte. Un fruit à sa taille vraie fait deux pixels en vue de structure : il est grossi comme au prototype.
	// Enfoncé de la hauteur de son centre une fois grossi, le tubercule couché laisse la moitié de son épaisseur hors de terre
	static TUBER = 'patate-douce';
	static TUBER_LEAD = 28;
	static TUBER_SINK = .1;
	static FRUIT_GROW = 1.7;

	// Le fruit se noue quand la série a fait FRUIT_SET de sa course vers la première récolte, au quart de sa taille, et grossit
	// jusqu'à sa taille pleine le jour de la première récolte. Par seizièmes : la pose des fruits ne se refait que lorsque la
	// taille change, et la lecture de l'année la referait chaque jour pour chaque série en croissance
	static FRUIT_SET = .6;
	static FRUIT_YOUNG = .25;
	static FRUIT_STEPS = 16;

	// Pendant la récolte d'une espèce récoltée entière, ses pieds sortent au fil des récoltes : il en reste un dixième à la fin
	static PULLED = .9;

	// Les variantes d'un modèle du socle, rangées sous le slug de l'espèce suivi de leur suffixe : le bois d'un ligneux sans ses
	// feuilles, son feuillage seul, le pied qui porte ses fruits, ces fruits seuls et le pied dont on a prélevé une part
	static BARE = '+nu';
	static FOLIAGE = '+feuilles';
	static DRESSED = '+fruits';
	static FRUITS_ONLY = '+fruits+seuls';
	static PICKED = '+cueilli';

	// Un ligneux est sans feuilles du lendemain de la chute du dernier arbre du décor à la mi-mars (jour 74) : un fruitier de série
	// et un arbre du décor voisins se dépouillent ensemble. Pendant la lecture, son feuillage pousse en LEAF_DAYS jours et tombe au
	// rythme du décor, et chaque pied avance ou retarde sa date de LEAF_STAGGER jours au plus. La pousse part du jour où le pied
	// feuillu parait à l'arrêt, la chute y finit
	static LEAF_ON = 74;
	static LEAF_DAYS = 12;
	static LEAF_STAGGER = 6;

	// Lus au décor à l'appel : sceneDecor.js se charge après cette feuille. Le décor compte son automne depuis le 1er octobre,
	// jour 273
	static get LEAF_OFF() {
		return 273 + MapSceneDecor.LEAF_FALL_START + MapSceneDecor.LEAF_FALL_DAYS + MapSceneDecor.LEAF_LATE_DAYS;
	}

	static get LEAF_FALL_DAYS() {
		return MapSceneDecor.LEAF_FALL_DAYS;
	}

	// Le feuillage qui tombe roussit, par huitièmes, jusqu'aux sept dixièmes de cette teinte
	static LEAF_RUST = '#c98a2b';

	// Avant ce jour de l'année, le feuillage d'un ligneux pousse ; après, il tombe
	static LEAF_SUMMER = 190;

	/**
	 * `spacing` écarte les pieds sur le rang (`crop.layout()`) : le plan les pose à 1, une scène qui montre une structure en petit
	 * les écarte pour tenir ses triangles.
	 */
	constructor(socle, plan, geometry, parent, gradientMap, wind, soil, spacing) {

		const { THREE } = socle;

		this.THREE = THREE;
		this.parent = parent;
		this.spacing = spacing;
		this.crop = socle.crop;
		this.stage = socle.stage;
		this.woody = socle.species.woody;
		this.geometry = geometry;

		// L'année lue n'est pas la vie entière d'une série : celle qui est déjà là la première semaine n'entre pas pendant la lecture
		this.first = MapScene.addDays(this.stage.monday(plan.season, 1), 7);

		this.material = this.crop.material({ gradientMap, wind, sway: MapScene.WIND_SWAY });
		this.leafMaterial = this.crop.material({ gradientMap, wind, sway: MapScene.WIND_SWAY, leaves: true });

		this.fruit = socle.fruit;
		this.fruitShapes = this.fruit.shapes();
		this.fruitMaterial = this.fruit.material({ gradientMap });
		this.fruitPools = new Map();
		this.fruitKey = null;

		// Une butte est la terre de sa planche relevée : son matériau tient la couleur même de la terre, que setTerrain() change en
		// place, sans quoi elle se lirait comme une bande rapportée dès que la terre change de ton
		this.ridgeGeometry = this.crop.ridgeGeometry();
		this.ridgeMaterial = new THREE.MeshToonMaterial({ gradientMap, vertexColors: true });
		this.ridgeMaterial.color = soil.color;
		this.ridgePool = null;

		// Une butte sous abri prend l'ombre de la terre sous abri
		this.ridgeOpen = new THREE.Color('#ffffff');
		this.ridgeShade = new THREE.Color('#ffffff').multiplyScalar(MapScene.GREENHOUSE_SHADE);

		this.leafRust = new THREE.Color(MapSceneCrops.LEAF_RUST);
		this.leafTints = new Map();

		const tasks = SeriesSheet.group(plan.tasks, plan.columns.tasks);
		const harvests = SeriesSheet.group(plan.harvests, plan.columns.harvests);
		const actions = new Map(plan.actions.map(action => [action.id, action.fqn]));

		const entries = new Map();
		this.series = new Map();

		for(const series of plan.series) {

			const cultivation = series.cultivations[0] ?? null;

			const timeline = SeriesSheet.timeline(this.stage, series, tasks.get(series.id) ?? [], harvests.get(series.id) ?? [], actions);

			if(timeline === null) {
				continue;
			}

			const entry = (cultivation === null) ? null : socle.species.get(cultivation.fqn, cultivation.plant);

			if(entry !== null) {
				entries.set(entry.slug, entry);
			}

			this.series.set(series.id, {
				id: series.id,
				status: series.status,
				plant: cultivation?.plant ?? series.name,
				fqn: cultivation?.fqn ?? null,
				color: cultivation?.color ?? '#888888',
				entry,
				timeline,
				harvests: harvests.get(series.id) ?? [],
				// Une espèce récoltée entière laisse sa planche nue après sa dernière récolte
				pulled: (entry !== null && socle.fruit.harvestedWhole(entry, series.cycle))
			});

		}

		this.models = this.crop.models(socle.baked, [...entries.values()]);

		this.variants = new Map();

		for(const [slug, model] of this.models) {

			const variants = [
				[slug, model],
				[slug + MapSceneCrops.BARE, model.bare],
				[slug + MapSceneCrops.FOLIAGE, model.leaves],
				[slug + MapSceneCrops.PICKED, model.picked],
				[slug + MapSceneCrops.DRESSED, model.dressed],
				[slug + MapSceneCrops.FRUITS_ONLY, (model.dressed === null) ? null : model.dressed.fruits]
			];

			for(const [key, variant] of variants) {
				if(variant !== null) {
					this.variants.set(key, variant);
				}
			}

		}

		// Une planche qui couvre son jardin ne porte ses cultures que si aucune autre planche de ce jardin n'est dessinée
		const beds = new Map(geometry.beds.map(bed => [bed.id, bed]));
		const plotsWithBeds = new Set(geometry.beds.map(bed => bed.plot));
		const plans = new Map(plan.beds.map(bed => [bed.id, bed]));

		this.beds = new Map();

		for(const place of plan.places) {

			if(this.series.has(place.series) === false) {
				continue;
			}

			if(this.beds.has(place.bed) === false) {

				const bed = plans.get(place.bed);
				const rectangle = beds.get(place.bed) ?? null;
				const whole = (rectangle === null && bed !== undefined && plotsWithBeds.has(bed.plot) === false) ? (geometry.plots.get(bed.plot) ?? null) : null;

				if(rectangle === null && whole === null) {
					continue;
				}

				this.beds.set(place.bed, { id: place.bed, zone: rectangle?.zone ?? whole.plot.zone, rectangle: rectangle ?? whole.rectangle, whole, places: [] });

			}

			this.beds.get(place.bed).places.push(place);

		}

		this.pools = new Map();
		this.names = [];
		this.textures = new Map();

		// Les planches réduites en ruines (MapSceneRuins) ne portent plus rien jusqu'au rechargement, lecture comprise
		this.ruined = new Set();
		this.blooms = new Map();
		this.nameGeometry = new THREE.PlaneGeometry(1, 1).rotateX(-Math.PI / 2);

		this.matrix = new THREE.Matrix4();
		this.position = new THREE.Vector3();
		this.turn = new THREE.Quaternion();
		this.scale = new THREE.Vector3();
		this.up = new THREE.Vector3(0, 1, 0);
		this.view = new THREE.Vector3();

	}

	static random(seed) {

		let a = seed >>> 0;

		return () => {
			a = (a + 0x6D2B79F5) >>> 0;
			let t = Math.imul(a ^ (a >>> 15), 1 | a);
			t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
			return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
		};

	}

	/**
	 * Pose les cultures en place à la date, et rend avec sa couleur chaque espèce montrée dans la zone choisie, ou dans
	 * toute la structure sans zone. Rend aussi l'emprise de chaque série de l'espèce choisie : son rectangle pour le liseré,
	 * qu'un jardin entier ne porte pas, et ce qui se cadre, dans toutes les zones, puisque le liseré les cerne toutes. La série
	 * `selected`, choisie d'un clic, se cerne de même, et `marker` est le centre de sa première portion. `spans` dit pour chaque
	 * planche quelle série tient quelle longueur, ce que lit le clic sur la scène, et `segments` chaque portion debout, d'où part
	 * l'éclat d'une série qui sort pendant la lecture.
	 */
	render(date, zone, species, playing, selected = null) {

		const instances = new Map();
		const fruits = [];
		const fruitKeys = [];
		const ridges = [];
		const shown = new Map();

		// Pendant la lecture, les fruits d'une série sont ceux de son lundi et tiennent la semaine : les retirer le jour de chaque
		// cueillette pour les reposer le lundi les ferait clignoter
		const { year, week } = this.stage.week(date);
		const picking = playing ? this.stage.monday(year, week) : date;
		const chosen = { outline: [], framed: [] };
		const badges = [];
		const spans = new Map();
		const segments = [];
		let marker = null;
		let names = 0;

		for(const bed of this.beds.values()) {

			if(this.ruined.has(bed.id)) {
				continue;
			}

			let active = [];

			for(const place of bed.places) {

				const series = this.series.get(place.series);
				const stage = this.stage.at(series.timeline, date, 'real');

				// Une série arrachée en entier garde sa longueur jusqu'à la fin de sa période : sans elle, les séries suivantes
				// de la planche glisseraient vers le début la semaine de la récolte
				if(stage.on) {
					active.push({ place, series, stage, drawn: (stage.key === 'over' && series.pulled) === false });
				}

			}

			if(active.length === 0) {
				continue;
			}

			const r = bed.rectangle;

			// Quand les séries de la planche ne tiennent pas bout à bout, une série arrachée cède sa place la première, puis
			// une série clôturée cède la sienne à une série en cours
			const fits = list => list.reduce((sum, { place }) => sum + Math.min(place.length ?? r.length / list.length, r.length), 0) <= r.length + .01;

			if(fits(active) === false) {
				active = active.filter(({ drawn }) => drawn);
			}

			const opened = active.filter(({ series }) => series.status !== 'closed');

			if(opened.length > 0 && opened.length < active.length && (bed.whole !== null || fits(active) === false)) {
				active = opened;
			}

			active.sort((a, b) => a.series.timeline.real.start.localeCompare(b.series.timeline.real.start));

			// Un jardin entier ne porte qu'une série : la première encore debout, puisqu'une série arrachée n'y laisse aucune
			// place à garder
			if(bed.whole !== null) {
				active = active.filter(({ drawn }) => drawn).slice(0, 1);
			}

			const angle = Math.atan2(r.u.n, r.u.x);
			let offset = 0;

			for(const { place, series, stage, drawn } of active) {

				const length = (bed.whole !== null) ? r.length : Math.min(place.length ?? r.length / active.length, r.length);

				if(offset + length > r.length + .01) {
					offset = 0;
				}

				if(drawn) {

					const middle = ScenePlanGeometry.add(ScenePlanGeometry.add(r.origin, ScenePlanGeometry.mul(r.u, offset + length / 2)), ScenePlanGeometry.mul(r.w, r.width / 2));

					MapScene.push(spans, bed.id, { from: offset, to: offset + length, series: series.id });
					segments.push({ key: bed.id +'-'+ series.id, series: series.id, center: middle, angle, length, width: r.width, top: MapScene.BED_BASE + MapScene.BED_HEIGHT });

					if(series.id === selected && marker === null) {
						marker = middle;
					}

					if(series.entry !== null) {

						this.plant({ instances, fruits, fruitKeys, ridges }, place, series, stage, bed, offset, length, angle, date, picking, playing);

						badges.push({
							position: [middle.x, MapScene.BED_BASE + MapScene.BED_HEIGHT + this.geometry.height(middle.x, middle.n), -middle.n],
							width: (bed.whole !== null) ? MapSceneCrops.WHOLE.bed : r.width,
							fqn: series.fqn,
							color: series.color,
							chosen: (series.plant === species)
						});

					} else if(bed.whole === null) {
						this.name(names++, series, r, offset, length, angle);
					}

					if((series.entry !== null || bed.whole === null) && (zone === null || bed.zone === zone)) {
						shown.set(series.plant, series.color);
					}

					if(series.plant === species || series.id === selected) {

						const span = (bed.whole !== null) ? bed.whole.points : this.span(r, offset, length);

						if(bed.whole === null) {
							chosen.outline.push(span);
						}

						chosen.framed.push(...span);

					}

				}

				offset += length;

			}

		}

		for(const [key, pool] of this.pools) {
			if(instances.has(key) === false) {
				pool.mesh.count = 0;
			}
		}

		for(const [key, { matrices, leaves, tints }] of instances) {

			const count = matrices.length / 16;
			const model = this.variants.get(key);
			const pool = this.crop.pool(this.parent, (model.geometry.attributes.anchor === undefined) ? this.material : this.leafMaterial, model, count, this.pools.get(key) ?? null);

			// Une culture annuelle reçoit l'ombre sans en porter : à l'échelle d'une structure, la sienne est une tache de
			// quelques pixels au pied du plant. Un ligneux garde la sienne, qui se lit de loin
			pool.mesh.castShadow = this.woody(model.species);

			pool.mesh.instanceMatrix.array.set(matrices);
			pool.mesh.instanceMatrix.needsUpdate = true;
			pool.mesh.count = count;

			if(pool.leaf !== null) {
				pool.leaf.array.set(leaves);
				pool.leaf.needsUpdate = true;
				pool.tints.array.set(tints);
				pool.tints.needsUpdate = true;
			}

			this.pools.set(key, pool);

		}

		this.ridgePool = this.crop.placeRidges(this.parent, this.ridgeMaterial, this.ridgeGeometry, ridges, this.ridgePool);

		// Le socle pose les buttes à plat, comme pour le bourg : sur le relief, chacune se penche ensuite sur la pente sous son milieu
		if(this.geometry.relief !== null) {

			for(let i = 0; i < ridges.length; i++) {
				this.ridgePool.mesh.getMatrixAt(i, this.matrix);
				this.ridgePool.mesh.setMatrixAt(i, this.geometry.lean(this.matrix));
			}

			this.ridgePool.mesh.instanceMatrix.needsUpdate = true;

		}

		// Tant que les mêmes places portent des fruits de la même taille, les fruits de la veille sont les bons, et la pose, qui
		// reprend chaque fruit de chaque pied, ne se refait pas
		const fruitKey = fruitKeys.join(' ');

		if(fruitKey !== this.fruitKey) {

			this.fruit.place(this.parent, this.fruitMaterial, this.fruitShapes, fruits, this.fruitPools, MapSceneCrops.random, { grow: MapSceneCrops.FRUIT_GROW });
			this.fruitKey = fruitKey;

			// Sans ombre, comme le plant qui les porte
			for(const pool of this.fruitPools.values()) {
				pool.mesh.castShadow = false;
			}

		}

		for(let i = names; i < this.names.length; i++) {
			this.names[i].visible = false;
		}

		return { shown, chosen, badges, spans, segments, marker };

	}

	/**
	 * Les pieds, buttes et fruits debout sur la planche `r`, chacun en morceau de copie (MapSceneRuins.shatter()) : sa géométrie,
	 * sa place dans le monde, sa couleur, la teinte de son feuillage et sa taille. Ils se reconnaissent à leur place : les instances
	 * d'une variante sont celles de toutes les planches, et deux planches ne se recouvrent pas.
	 */
	parts(r) {

		const THREE = this.THREE;
		const G = ScenePlanGeometry;
		const parts = [];
		const matrix = new THREE.Matrix4();
		const color = new THREE.Color();

		this.parent.updateMatrixWorld(true);

		for(const mesh of this.parent.children) {

			if(mesh.isInstancedMesh !== true || mesh.visible === false) {
				continue;
			}

			const tints = mesh.geometry.attributes.instanceTint ?? null;
			const leaves = mesh.geometry.attributes.instanceLeaf ?? null;

			for(let i = 0; i < mesh.count; i++) {

				mesh.getMatrixAt(i, matrix);

				const d = G.sub({ x: matrix.elements[12], n: -matrix.elements[14] }, r.origin);
				const along = G.dot(d, r.u), across = G.dot(d, r.w);

				if(along < -.05 || along > r.length + .05 || across < -.05 || across > r.width + .05) {
					continue;
				}

				const solid = mesh.material.color.clone();

				if(mesh.instanceColor !== null) {
					solid.multiply(color.fromArray(mesh.instanceColor.array, i * 3));
				}

				parts.push({
					geometry: mesh.geometry,
					matrix: matrix.clone().premultiply(mesh.matrixWorld),
					color: solid,
					tint: (tints === null) ? null : new THREE.Vector4().fromBufferAttribute(tints, i),
					leaf: (leaves === null) ? 1 : leaves.getX(i)
				});

			}

		}

		return parts;

	}

	/**
	 * Un pied de plus dans les instances d'une variante, posé sur la planche. `leaf` est la taille de son feuillage seul, `turned`
	 * la part de roux du feuillage qui tombe.
	 */
	instance(instances, key, x, top, n, d, h, rotation, leaf = 1, turned = 0) {

		if(instances.has(key) === false) {
			instances.set(key, { matrices: [], leaves: [], tints: [] });
		}

		const row = instances.get(key);

		this.matrix.compose(this.position.set(x, top + .02, -n), this.turn.setFromAxisAngle(this.up, rotation), this.scale.set(d, h, d));
		this.geometry.lean(this.matrix);

		row.matrices.push(...this.matrix.elements);
		row.leaves.push(leaf);
		row.tints.push(...this.leafTint(turned));

	}

	// Une teinte par huitième de roux : elle se compose une fois
	leafTint(turned) {

		if(this.leafTints.has(turned) === false) {
			this.leafTints.set(turned, this.crop.tint(new this.THREE.Vector4(), (turned === 0) ? [] : [[this.leafRust, .7 * turned]]).toArray());
		}

		return this.leafTints.get(turned);

	}

	/**
	 * La taille du feuillage d'un ligneux un jour de l'année, de 0 à 1 : il pousse au printemps et tombe à l'automne.
	 */
	static leafGrown(day) {

		if(day < MapSceneCrops.LEAF_SUMMER) {
			return MapScene.smooth(MapSceneCrops.LEAF_ON, MapSceneCrops.LEAF_ON + MapSceneCrops.LEAF_DAYS, day);
		}

		const end = MapSceneCrops.LEAF_OFF - MapSceneCrops.LEAF_STAGGER;

		return 1 - MapScene.smooth(end - MapSceneCrops.LEAF_FALL_DAYS, end, day);

	}

	/**
	 * L'avancement de la récolte d'une série à une date : la part des quantités saisies jusqu'à ce jour, ou celle de la période
	 * de récolte écoulée quand rien n'est saisi. Il ne fait que monter avec la date.
	 */
	harvestedShare(series, date) {

		const total = series.harvests.reduce((sum, harvest) => sum + harvest.quantity, 0);

		if(total > 0) {
			return series.harvests.reduce((sum, harvest) => (harvest.date <= date) ? sum + harvest.quantity : sum, 0) / total;
		}

		const real = series.timeline.real;

		return Math.max(0, Math.min(1, MapScene.daysBetween(real.harvestFirst, date) / Math.max(1, MapScene.daysBetween(real.harvestFirst, real.harvestLast ?? real.end))));

	}

	/**
	 * Un pied garde ses fruits tant qu'une récolte de sa série reste à venir, saisie plus tard ou attendue par sa période de
	 * récolte : il ne se vide qu'à la récolte de sa dernière semaine prévue, qui est aussi la seule d'une série récoltée en une
	 * fois. Une série close n'attend plus rien, et une série sans récolte saisie garde ses fruits toute sa période.
	 */
	fruitsPicked(series, date) {

		if(series.harvests.length === 0 || series.harvests.some(harvest => harvest.date > date)) {
			return false;
		}

		const plannedLast = series.timeline.planned.harvestLast;

		const last = series.harvests.reduce((latest, harvest) => (harvest.date > latest) ? harvest.date : latest, '');

		return (series.status === 'closed' || plannedLast === null || last >= MapScene.addDays(plannedLast, -6));

	}

	/**
	 * Les quatre coins de la portion de planche qu'occupe une série.
	 */
	span(r, offset, length) {

		const G = ScenePlanGeometry;
		const start = G.add(r.origin, G.mul(r.u, offset));
		const end = G.add(r.origin, G.mul(r.u, offset + length));

		return [start, end, G.add(end, G.mul(r.w, r.width)), G.add(start, G.mul(r.w, r.width))];

	}

	/**
	 * Toutes les espèces que la clé peut nommer dans la saison, dans une zone ou dans toute la structure : celles de chaque série
	 * posée sur une planche, à la condition de `render()` qui les met dans la clé.
	 */
	seasonSpecies(zone) {

		const species = new Map();

		for(const bed of this.beds.values()) {

			if(zone !== null && bed.zone !== zone) {
				continue;
			}

			for(const place of bed.places) {

				const series = this.series.get(place.series);

				if(series.entry !== null || bed.whole === null) {
					species.set(series.plant, series.color);
				}

			}

		}

		return species;

	}

	/**
	 * Le coin le plus vivant du jour : la planche dont le voisinage porte le plus de surface cultivée, cadrée avec ses voisines
	 * cultivées de la même zone. Toute la structure tient à l'écran à quelques pixels par mètre, où un plant ne se lit plus.
	 */
	lively(date) {

		const G = ScenePlanGeometry;
		const growing = [];

		for(const bed of this.beds.values()) {

			if(bed.whole !== null || bed.places.some(place => this.stage.at(this.series.get(place.series).timeline, date, 'real').on) === false) {
				continue;
			}

			const r = bed.rectangle;
			growing.push({ bed, center: G.add(r.origin, G.add(G.mul(r.u, r.length / 2), G.mul(r.w, r.width / 2))), area: r.length * r.width });

		}

		const near = (a, b) => a.bed.zone === b.bed.zone && G.length(G.sub(a.center, b.center)) < MapSceneCrops.LIVELY_REACH;

		let best = null;
		let bestArea = 0;

		for(const a of growing) {

			const area = growing.reduce((sum, b) => near(a, b) ? sum + b.area : sum, 0);

			if(area > bestArea) {
				best = a;
				bestArea = area;
			}

		}

		if(best === null) {
			return null;
		}

		// Se cadrent les coins de toutes les planches que la boite des voisines cultivées contient : une planche nue prise entre deux
		// planches cultivées reste dans le cadre au lieu d'en être coupée
		const box = growing.filter(b => near(best, b)).flatMap(b => this.geometry.corners(b.bed.rectangle));
		const [minX, maxX, minN, maxN] = [Math.min(...box.map(p => p.x)), Math.max(...box.map(p => p.x)), Math.min(...box.map(p => p.n)), Math.max(...box.map(p => p.n))];
		const points = this.geometry.beds.flatMap(bed => this.geometry.corners(bed)).filter(p => p.x >= minX - .01 && p.x <= maxX + .01 && p.n >= minN - .01 && p.n <= maxN + .01);

		return { zone: best.bed.zone, points };

	}

	/**
	 * Les pieds d'une série sur sa portion de planche, rangés par variante du modèle dans `instances`, et ce qui les accompagne :
	 * leurs fruits dans `fruits`, la clé qui dit quand les reposer dans `fruitKeys`, leurs buttes dans `ridges`. `picking` est le
	 * jour où se juge la cueillette des fruits.
	 */
	plant({ instances, fruits, fruitKeys, ridges }, place, series, stage, bed, offset, length, angle, date, picking, playing) {

		const slug = series.entry.slug;
		const model = this.models.get(slug);
		const r = bed.rectangle;
		const top = MapScene.BED_BASE + MapScene.BED_HEIGHT;

		const layout = this.crop.layout(model, (bed.whole === null) ?
			{ width: r.width, length, scale: MapSceneCrops.SCALE, density: MapSceneCrops.DENSITY, minPitch: MapSceneCrops.MIN_PITCH, inset: MapSceneCrops.INSET, spacing: this.spacing } :
			{ width: r.width, length: r.length, scale: MapSceneCrops.SCALE, density: MapSceneCrops.DENSITY, minPitch: MapSceneCrops.MIN_PITCH, whole: MapSceneCrops.WHOLE, spacing: this.spacing });

		const { d, h } = layout.size;

		// Un arbre ou un arbuste est déjà formé ; un jeune plant d'une petite espèce garde de quoi se reconnaitre, au plus les sept
		// dixièmes de sa taille adulte, et une grande espèce garde toute sa course
		const woody = this.woody(series.entry);
		const legible = Math.min(.7, Math.max(.15, MapSceneCrops.YOUNG_LEGIBLE / Math.max(d, h)));
		const grown = Math.max(legible, stage.growth);
		const start = series.timeline.real.start;
		const days = MapScene.daysBetween(start, date);

		let growth = 1;

		// Un semis lève en se fondant jusqu'à sa taille de jeune plant, au lieu d'y sauter le jour où il cesse d'être frais : la
		// lecture le montre jour par jour, et le curseur arrêté sur un jour le montre à la même taille
		if(woody === false) {
			growth = (series.timeline.kind === 'sowing' && (stage.key === 'sown' || stage.key === 'growing')) ? .15 + (grown - .15) * MapScene.smooth(...MapSceneCrops.EMERGENCE, days) : grown;
		}

		const presence = (playing && start > this.first) ? MapScene.smooth(-1, MapSceneCrops.PRESENCE_IN, days) : 1;

		const scatter = this.crop.scatter(model) * layout.pitch;
		const edge = (1 - layout.band) / 2;
		const seed = MapSceneCrops.random(place.id);
		const used = length - MapSceneCrops.INSET;

		// Un ligneux qui sépare son bois de son feuillage est nu l'hiver
		const sheds = (woody && model.bare !== null);
		const today = MapScene.daysBetween(date.slice(0, 4) + '-01-01', date);
		const bare = (sheds && (today >= MapSceneCrops.LEAF_OFF || today < MapSceneCrops.LEAF_ON));

		// Pendant la récolte d'une espèce récoltée entière, ses pieds sortent au fil des récoltes ; une espèce qui a sa variante
		// cueillie y passe pied par pied, et y reste en entier après sa dernière récolte
		const harvested = (stage.key === 'harvest') ? this.harvestedShare(series, date) : (stage.key === 'over') ? 1 : 0;
		const pulled = (stage.key === 'harvest' && series.pulled) ? MapSceneCrops.PULLED * harvested : 0;
		const picked = model.picked;
		const pickD = (picked === null) ? 1 : picked.d / model.d;
		const pickH = (picked === null) ? 1 : picked.h / model.h;

		// Les fruits mûrs tiennent tant qu'une récolte reste à venir. De la nouaison à la récolte, ils grossissent du quart de leur
		// taille à leur taille pleine ; un tubercule ne sort de terre qu'à l'arrachage
		const ready = (stage.key === 'harvest' && bare === false && presence === 1 && this.fruitsPicked(series, picking) === false);
		const setting = (stage.key === 'growing' && stage.progress > MapSceneCrops.FRUIT_SET && bare === false && presence === 1 && this.fruit.underground.has(slug.split('~')[0]) === false);

		let fruitSize = 0;

		if(ready) {
			fruitSize = 1;
		} else if(setting) {
			fruitSize = Math.round((MapSceneCrops.FRUIT_YOUNG + (1 - MapSceneCrops.FRUIT_YOUNG) * (stage.progress - MapSceneCrops.FRUIT_SET) / (1 - MapSceneCrops.FRUIT_SET)) * MapSceneCrops.FRUIT_STEPS) / MapSceneCrops.FRUIT_STEPS;
		}

		// Une espèce qui porte ses fruits dans son module ne reçoit jamais ceux du registre : prête à récolter, elle montre son pied
		// chargé, dont l'emprise et la hauteur comptent les fruits, et le rapport rend au pied sa taille de pied nu. De la nouaison à
		// la récolte, le pied nu porte les fruits seuls du même module, à cette échelle fois leur taille du jour
		const dressedModel = model.dressed;
		const dressed = (ready && dressedModel !== null);
		const dressD = (dressedModel === null) ? 1 : dressedModel.d / model.d;
		const dressH = (dressedModel === null) ? 1 : dressedModel.h / model.h;
		const young = (setting && dressedModel !== null && dressedModel.fruits !== null);

		let planted = slug;

		if(dressed) {
			planted = slug + MapSceneCrops.DRESSED;
		} else if(bare) {
			planted = slug + MapSceneCrops.BARE;
		}

		// Pendant la lecture, chaque ligneux suit sa propre date : nu, feuillu, ou son bois avec un feuillage à la taille du jour
		const staggered = (playing && sheds && dressed === false);

		// Des tubercules à demi enfoncés le mois d'avant la récolte, entiers pendant la récolte comme tout fruit mûr
		const sunk = (slug === MapSceneCrops.TUBER && stage.key !== 'harvest' && stage.key !== 'over' && series.timeline.real.harvestFirst !== null && MapScene.daysBetween(date, series.timeline.real.harvestFirst) <= MapSceneCrops.TUBER_LEAD);
		const fruited = (fruitSize > 0 && dressedModel === null);

		if(fruited || sunk) {
			fruitKeys.push(place.id + '@' + offset.toFixed(2) + '*' + (sunk ? 'sunk' + presence : fruitSize) + '-' + pulled.toFixed(2));
		}

		// Le pied et le rayon de cime de chaque arbre fruitier, où les oiseaux de MapSceneFauna se posent comme autour d'un arbre du
		// décor
		const perches = [];

		for(let i = 0; i < layout.count; i++) {
			for(let k = 0; k < layout.rows; k++) {

				let along = offset + MapSceneCrops.INSET / 2 + (i + .5) * used / layout.count;
				let across = r.width * (edge + layout.band * (k + .5) / layout.rows);

				// Le désordre d'un tapis de fleurs se tire de la graine de la place : les autres espèces gardent le même tirage
				if(scatter > 0) {
					along += (seed() - .5) * 2 * scatter;
					across = Math.min(r.width * (1 - edge), Math.max(r.width * edge, across + (seed() - .5) * 2 * scatter));
				}

				const x = r.origin.x + r.u.x * along + r.w.x * across;
				const n = r.origin.n + r.u.n * along + r.w.n * across;

				if(bed.whole !== null && ScenePlanGeometry.inside({ x, n }, bed.whole.points) === false) {
					continue;
				}

				const jitter = .88 + seed() * .24;
				const rotation = model.aligned ? angle : seed() * 6.28;
				const size = d * (.35 + .65 * growth) * jitter * presence;
				const tall = h * growth * jitter * presence;

				// Le tirage tient au pied, pas au jour : un pied sorti ou cueilli ne revient pas
				const drawn = (pulled > 0 || (picked !== null && harvested > 0)) ? MapSceneCrops.random(place.id * 977 + i * 7 + k)() : 1;

				if(drawn < pulled) {
					continue;
				}

				if(picked !== null && drawn < harvested) {
					this.instance(instances, slug + MapSceneCrops.PICKED, x, top, n, size * pickD, tall * pickH, rotation);
					continue;
				}

				if(series.entry.family === 'tree') {
					perches.push({ x, n, reach: size / 2 });
				}

				const leaf = staggered ? MapSceneCrops.leafGrown(today + (place.id * 31 + i * 7 + k * 3) % (2 * MapSceneCrops.LEAF_STAGGER + 1) - MapSceneCrops.LEAF_STAGGER) : 1;

				let key = planted;

				if(staggered) {
					key = (leaf < 1) ? slug + MapSceneCrops.BARE : slug;
				}

				this.instance(instances, key, x, top, n, dressed ? size * dressD : size, dressed ? tall * dressH : tall, rotation);

				if(young) {
					this.instance(instances, slug + MapSceneCrops.FRUITS_ONLY, x, top, n, size * dressD * fruitSize, tall * dressH * fruitSize, rotation);
				}

				// Le feuillage qui pousse garde le vert du plant, celui qui tombe roussit
				if(leaf > 0 && leaf < 1) {
					this.instance(instances, slug + MapSceneCrops.FOLIAGE, x, top, n, size, tall, rotation, leaf, (today < MapSceneCrops.LEAF_SUMMER) ? 0 : Math.round((1 - leaf) * 8) / 8);
				}

				if(fruited || sunk) {
					fruits.push({ slug, seed: place.id * 977 + i * 7 + k, x, y: top + .02 - (sunk ? MapSceneCrops.TUBER_SINK : 0) + this.geometry.height(x, n), z: -n, d: size, h: tall, rot: rotation, fruit: sunk ? presence : fruitSize });
				}

			}
		}

		// Le rang continu d'une espèce buttée : le plant porte sa propre butte, mais son pas laisse un creux entre deux pieds
		const ridge = this.crop.ridge(slug.split('~')[0]);

		if(ridge !== null && bed.whole === null) {

			for(let k = 0; k < layout.rows; k++) {

				const p = ScenePlanGeometry.add(ScenePlanGeometry.add(r.origin, ScenePlanGeometry.mul(r.u, offset + length / 2)), ScenePlanGeometry.mul(r.w, r.width * (edge + layout.band * (k + .5) / layout.rows)));

				ridges.push({
					x: p.x,
					y: top + .02,
					z: -p.n,
					rot: angle,
					length: Math.max(used, .1),
					width: d * (.35 + .65 * growth) * ridge.width * presence,
					height: h * growth * ridge.height * presence,
					color: r.greenhouse ? this.ridgeShade : this.ridgeOpen
				});

			}

		}

		// Les papillons de MapSceneInsects volent au-dessus de ces places, à la hauteur des plants
		const middle = ScenePlanGeometry.add(r.origin, ScenePlanGeometry.mul(r.w, r.width / 2));

		this.blooms.set(place.id, {
			date,
			a: ScenePlanGeometry.add(middle, ScenePlanGeometry.mul(r.u, offset)),
			b: ScenePlanGeometry.add(middle, ScenePlanGeometry.mul(r.u, offset + length)),
			top: top + h * growth * presence,
			height: h * growth * presence,
			greenhouse: (bed.whole === null) ? r.greenhouse : (bed.whole.plot.mode === 'greenhouse'),
			perches
		});

	}

	/**
	 * Le nom d'une culture sans modèle, couché sur sa planche dans le sens de sa longueur et à sa
	 * couleur : il tient dans l'emprise de la série, donc jamais sur la planche voisine.
	 */
	name(index, series, r, offset, length, angle) {

		const THREE = this.THREE;

		if(index === this.names.length) {

			const mesh = new THREE.Mesh(this.nameGeometry, new THREE.MeshBasicMaterial({ transparent: true, depthWrite: false }));
			mesh.matrixAutoUpdate = false;
			this.parent.add(mesh);
			this.names.push(mesh);

		}

		const mesh = this.names[index];
		const { texture, aspect } = this.texture(series.plant, series.color);
		const center = ScenePlanGeometry.add(ScenePlanGeometry.add(r.origin, ScenePlanGeometry.mul(r.u, offset + length / 2)), ScenePlanGeometry.mul(r.w, r.width / 2));
		const height = Math.min(r.width * MapSceneCrops.NAME_FILL, Math.max(length - .4, .1) / aspect);

		mesh.material.map = texture;
		mesh.material.needsUpdate = true;
		mesh.position.set(center.x, MapScene.BED_BASE + MapScene.BED_HEIGHT + .02, -center.n);
		mesh.scale.set(height * aspect, 1, height);
		mesh.userData.angle = angle;
		mesh.visible = true;

	}

	texture(name, color) {

		const key = name +'|'+ color;

		if(this.textures.has(key) === false) {

			const canvas = document.createElement('canvas');
			const context = canvas.getContext('2d');
			const font = '700 72px system-ui, sans-serif';

			context.font = font;
			canvas.width = Math.ceil(context.measureText(name).width) + 24;
			canvas.height = 96;

			// Changer la taille du canevas remet son contexte à zéro
			context.font = font;
			context.textBaseline = 'middle';
			context.lineJoin = 'round';
			context.lineWidth = 6;

			// Un liseré à l'opposé de la couleur de l'espèce : clair sous une teinte sombre, qui se perdrait sur la terre, brun sous
			// une teinte pâle, qui se perdrait dans un liseré clair
			const tint = new this.THREE.Color(color);
			context.strokeStyle = (.299 * tint.r + .587 * tint.g + .114 * tint.b > .6) ? '#3d2a1c' : '#fbf6ea';
			context.strokeText(name, 12, 50);
			context.fillStyle = color;
			context.fillText(name, 12, 50);

			const texture = new this.THREE.CanvasTexture(canvas);
			texture.colorSpace = this.THREE.SRGBColorSpace;

			this.textures.set(key, { texture, aspect: canvas.width / canvas.height });

		}

		return this.textures.get(key);

	}

	/**
	 * Un nom se lit à l'endroit d'où que la caméra regarde : il se retourne quand elle passe de
	 * l'autre côté de sa planche.
	 */
	face(camera) {

		camera.getWorldDirection(this.view);

		for(const mesh of this.names) {

			if(mesh.visible === false) {
				continue;
			}

			const angle = mesh.userData.angle;
			const behind = (Math.sin(angle) * this.view.x + Math.cos(angle) * this.view.z > 0);

			mesh.rotation.set(0, behind ? angle + Math.PI : angle, 0);
			mesh.updateMatrix();
			this.geometry.lean(mesh.matrix);

		}

	}

	dispose() {

		for(const pool of this.pools.values()) {
			pool.mesh.dispose();
		}

		// models() fusionne pour chaque espèce son pied, son pied cueilli et son pied chargé, chacun avec son bois, son feuillage et
		// ses fruits : tout ce qu'elle a fusionné se libère, même ce que la scène n'a pas posé
		for(const model of this.models.values()) {
			for(const variant of [model, model.picked, model.dressed]) {
				for(const part of [variant, variant?.bare, variant?.leaves, variant?.fruits]) {
					part?.geometry.dispose();
				}
			}
		}

		for(const mesh of this.names) {
			mesh.material.dispose();
		}

		for(const { texture } of this.textures.values()) {
			texture.dispose();
		}

		this.nameGeometry.dispose();
		this.material.dispose();
		this.leafMaterial.dispose();

		this.ridgePool?.mesh.dispose();
		this.ridgeGeometry.dispose();
		this.ridgeMaterial.dispose();

		for(const pool of this.fruitPools.values()) {
			pool.mesh.dispose();
		}

		for(const shape of this.fruitShapes.values()) {
			shape.userData.map?.dispose();
			shape.dispose();
		}

		this.fruitMaterial.dispose();

	}

}

/**
 * Une pastille par série plantée, au milieu de sa portion de planche : un disque crème cerclé d'un pixel, et dedans l'icône de
 * l'espèce ou, quand l'espèce n'a pas d'icône, un point de la couleur de la culture. Elle est ancrée au sol, ne prend pas le clic
 * et garde sa taille à l'écran, celle de la largeur de sa planche entre un plancher qui se lit à la vue d'ensemble et un plafond
 * qui laisse voir les plants.
 *
 * Toutes se dessinent dans le canevas, en un appel : en éléments posés sur la scène, les 150 pastilles d'une structure déplacées
 * à chaque image d'un glissé faisaient tomber le prototype à 30 images par seconde. Chacune est un carré tourné vers l'écran,
 * composé dans le nuanceur couche sur couche, comme le navigateur composait l'élément.
 */
class MapSceneBadges {

	// Taille à l'écran en pixels, la largeur de la planche qui en remplit BADGE_FILL, grossie de CHOSEN pour l'espèce choisie
	static MIN = 30;
	static MAX = 64;
	static FILL = .8;
	static CHOSEN = 1.3;

	// Le liseré en pixels, et dans le disque l'icône à 82 % ou le point à 55 %
	static BORDER = 1;
	static ICON = .82;
	static DOT = .55;
	static OPACITY = .94;

	// Une icône ne se dessine dans la texture qu'à sa première pastille, et la texture double de hauteur quand ses rangs sont pleins
	static CELL = 128;
	static MARGIN = 4;
	static COLUMNS = 16;
	static ROWS = 4;

	// Les tampons grandissent par paquets, une structure dépassant rarement 256 séries en terre le même jour
	static RESERVE = 256;

	/**
	 * Les symboles de `plants.svg`, par identifiant : le `fqn` de l'espèce en minuscules, comme `PlantUi::getVignetteHref()`.
	 */
	static symbols(text) {
		return new Map([...text.matchAll(/<symbol[^>]+id="([^"]+)"[^>]*>([\s\S]*?)<\/symbol>/g)].map(match => [match[1], match[2]]));
	}

	/**
	 * `wood` porte le crème du disque et son cerne, `{ cream, ring }`, lus dans la feuille de la scène.
	 */
	constructor(THREE, parent, wood) {

		this.THREE = THREE;
		this.symbols = new Map();
		this.parent = parent;

		this.on = true;
		this.list = [];
		this.capacity = 0;
		this.cells = new Map();
		this.rows = MapSceneBadges.ROWS;
		this.depth = new THREE.Vector3();

		this.board = document.createElement('canvas');
		this.board.width = MapSceneBadges.COLUMNS * MapSceneBadges.CELL;
		this.board.height = this.rows * MapSceneBadges.CELL;

		this.texture = new THREE.CanvasTexture(this.board);
		this.texture.colorSpace = THREE.SRGBColorSpace;

		const geometry = new THREE.InstancedBufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute([-1, -1, 0, 1, -1, 0, 1, 1, 0, -1, 1, 0], 3));
		geometry.setIndex([0, 1, 2, 0, 2, 3]);
		geometry.instanceCount = 0;

		const { MIN, MAX, FILL, BORDER, ICON, DOT, OPACITY, CELL, MARGIN, COLUMNS } = MapSceneBadges;
		const float = value => value.toFixed(2);

		// La sortie repasse en sRGB et se mélange à l'image dans cet espace, comme le faisait la page
		this.material = new THREE.ShaderMaterial({
			uniforms: {
				atlas: { value: this.texture },
				atlasRows: { value: this.rows },
				pixelsPerMeter: { value: 1 },
				viewport: { value: new THREE.Vector2(1, 1) },
				fill: { value: new THREE.Color(wood.cream) },
				ring: { value: new THREE.Color(wood.ring) }
			},
			vertexShader: `
				attribute vec3 badgeCenter;
				attribute vec2 badgeShape;
				attribute vec2 badgeCell;
				attribute vec3 badgeColor;
				uniform float pixelsPerMeter;
				uniform vec2 viewport;
				varying vec2 vPoint;
				varying float vRadius;
				varying vec2 vCell;
				varying vec3 vColor;
				void main() {
					float size = floor(clamp(badgeShape.x * pixelsPerMeter * ${float(FILL)}, ${float(MIN)}, ${float(MAX)}) * badgeShape.y + .5);
					vec4 clip = projectionMatrix * viewMatrix * vec4(badgeCenter, 1.);
					clip.xy += position.xy * size / viewport * clip.w;
					gl_Position = clip;
					vPoint = position.xy * size * .5;
					vRadius = size * .5;
					vCell = badgeCell;
					vColor = badgeColor;
				}`,
			fragmentShader: `
				uniform sampler2D atlas;
				uniform float atlasRows;
				uniform vec3 fill;
				uniform vec3 ring;
				varying vec2 vPoint;
				varying float vRadius;
				varying vec2 vCell;
				varying vec3 vColor;
				vec4 over(vec4 top, vec4 under) {
					float a = top.a + under.a * (1. - top.a);
					return a <= 0. ? vec4(0.) : vec4((top.rgb * top.a + under.rgb * under.a * (1. - top.a)) / a, a);
				}
				void main() {
					float r = length(vPoint), aa = max(fwidth(r), 1e-3);
					float disc = clamp((vRadius - r) / aa + .5, 0., 1.);
					float inner = clamp((vRadius - ${float(BORDER)} - r) / aa + .5, 0., 1.);
					vec4 color = over(vec4(ring, disc * (1. - inner)), vec4(fill, ${float(OPACITY)} * disc));
					float content = vRadius - ${float(BORDER)};
					vec2 t = vPoint / (content * ${float(ICON)}) * .5 + .5;
					float x = vCell.x * ${float(CELL)} + ${float(MARGIN)} + t.x * ${float(CELL - MARGIN * 2)};
					float y = vCell.y * ${float(CELL)} + ${float(MARGIN)} + (1. - t.y) * ${float(CELL - MARGIN * 2)};
					vec4 icon = texture2D(atlas, vec2(x / ${float(COLUMNS * CELL)}, 1. - y / (atlasRows * ${float(CELL)})));
					float framed = step(0., t.x) * step(t.x, 1.) * step(0., t.y) * step(t.y, 1.);
					float dot = clamp((content * ${float(DOT)} - r) / aa + .5, 0., 1.);
					color = vCell.x < 0. ? over(vec4(vColor, dot), color) : over(vec4(icon.rgb, icon.a * framed), color);
					if(color.a <= 0.) discard;
					gl_FragColor = color;
					#include <colorspace_fragment>
				}`,
			transparent: true,
			depthTest: false,
			depthWrite: false
		});

		this.mesh = new THREE.Mesh(geometry, this.material);
		this.mesh.frustumCulled = false;
		this.mesh.renderOrder = 100;
		this.mesh.visible = false;
		parent.add(this.mesh);

		this.reserve(MapSceneBadges.RESERVE);

	}

	reserve(count) {

		const geometry = this.mesh.geometry;
		this.capacity = Math.ceil(count / MapSceneBadges.RESERVE) * MapSceneBadges.RESERVE;

		for(const [name, size] of [['badgeCenter', 3], ['badgeShape', 2], ['badgeCell', 2], ['badgeColor', 3]]) {
			geometry.setAttribute(name, new this.THREE.InstancedBufferAttribute(new Float32Array(this.capacity * size), size));
		}

		// Des tampons plus grands remplacent les anciens : ceux de la carte graphique sont à refaire
		geometry.dispose();

	}

	/**
	 * La case de l'icône d'une espèce dans la texture, `[-1, -1]` quand l'espèce n'a pas d'icône, ou pas encore (`learn()`). L'icône se dessine quand son
	 * image est prête : la pastille montre son disque vide jusque-là.
	 */
	cell(fqn) {

		const symbol = (fqn === null) ? null : fqn.toLowerCase();

		if(symbol === null || this.symbols.has(symbol) === false) {
			return [-1, -1];
		}

		if(this.cells.has(symbol)) {
			return this.cells.get(symbol);
		}

		const { CELL, MARGIN, COLUMNS } = MapSceneBadges;
		const index = this.cells.size;
		const cell = [index % COLUMNS, Math.floor(index / COLUMNS)];

		if(cell[1] >= this.rows) {
			this.grow();
		}

		this.cells.set(symbol, cell);

		const image = new Image();
		image.src = 'data:image/svg+xml;charset=utf-8,'+ encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="'+ CELL +'" height="'+ CELL +'">'+ this.symbols.get(symbol) +'</svg>');

		// Une icône qui ne se décode pas laisse son disque vide, et la scène continue
		image.decode()
			.then(() => {
				this.board.getContext('2d').drawImage(image, cell[0] * CELL + MARGIN, cell[1] * CELL + MARGIN, CELL - MARGIN * 2, CELL - MARGIN * 2);
				this.texture.needsUpdate = true;
			})
			.catch(() => {});

		return cell;

	}

	/**
	 * Les icônes arrivent après les pastilles : chacune quitte son point de couleur pour l'icône de son espèce.
	 */
	learn(symbols, camera) {

		this.symbols = symbols;

		for(const badge of this.list) {
			badge.cell = this.cell(badge.fqn);
		}

		this.write(camera);

	}

	grow() {

		const kept = document.createElement('canvas');
		kept.width = this.board.width;
		kept.height = this.board.height;
		kept.getContext('2d').drawImage(this.board, 0, 0);

		this.rows *= 2;
		this.board.height = this.rows * MapSceneBadges.CELL;
		this.board.getContext('2d').drawImage(kept, 0, 0);
		this.material.uniforms.atlasRows.value = this.rows;

		// La texture change de taille : celle de la carte graphique est à refaire, pas à remplir
		this.texture.dispose();
		this.texture.needsUpdate = true;

	}

	/**
	 * Les pastilles du jour montré, telles que `MapSceneCrops.render()` les rend.
	 */
	show(badges, camera) {

		this.list = badges.map(badge => ({
			position: new this.THREE.Vector3(...badge.position),
			width: badge.width,
			chosen: badge.chosen,
			fqn: badge.fqn,
			cell: this.cell(badge.fqn),
			color: new this.THREE.Color(badge.color)
		}));

		this.write(camera);

	}

	/**
	 * Les pastilles s'écrivent de la plus lointaine à la plus proche, qui passe devant comme le faisait l'élément. Deux séries qui
	 * partagent une place de planche ont la même profondeur : la première de la liste reste devant.
	 */
	write(camera) {

		camera.getWorldDirection(this.depth);

		const sorted = [...this.list].reverse().map(badge => [badge, badge.position.dot(this.depth)]).sort((a, b) => b[1] - a[1]);
		const geometry = this.mesh.geometry;

		if(sorted.length > this.capacity) {
			this.reserve(sorted.length);
		}

		const center = geometry.getAttribute('badgeCenter');
		const shape = geometry.getAttribute('badgeShape');
		const cell = geometry.getAttribute('badgeCell');
		const color = geometry.getAttribute('badgeColor');

		sorted.forEach(([badge], index) => {
			center.setXYZ(index, badge.position.x, badge.position.y, badge.position.z);
			shape.setXY(index, badge.width, badge.chosen ? MapSceneBadges.CHOSEN : 1);
			cell.setXY(index, badge.cell[0], badge.cell[1]);
			color.setXYZ(index, badge.color.r, badge.color.g, badge.color.b);
		});

		for(const attribute of [center, shape, cell, color]) {
			attribute.needsUpdate = true;
		}

		geometry.instanceCount = sorted.length;

	}

	resize(width, height) {
		this.material.uniforms.viewport.value.set(width, height);
	}

	/**
	 * Le carré de chaque pastille à l'écran, en pixels du canevas, de la taille que le nuanceur lui donne ; aucun quand elles ne
	 * se dessinent pas.
	 */
	boxes(camera, width, height) {

		if(this.mesh.visible === false) {
			return [];
		}

		const { MIN, MAX, FILL, CHOSEN } = MapSceneBadges;
		const pixels = this.material.uniforms.pixelsPerMeter.value;
		const projected = new this.THREE.Vector3();

		return this.list.map(badge => {
			const size = Math.floor(Math.min(MAX, Math.max(MIN, badge.width * pixels * FILL)) * (badge.chosen ? CHOSEN : 1) + .5);
			projected.copy(badge.position).project(camera);
			const x = (projected.x + 1) / 2 * width;
			const y = (1 - projected.y) / 2 * height;
			return [x - size / 2, y - size / 2, x + size / 2, y + size / 2];
		});

	}

	/**
	 * La taille se calcule à chaque image dans le nuanceur, d'après le zoom.
	 */
	frame(pixels, shown) {

		this.mesh.visible = (this.on && shown && this.list.length > 0);
		this.material.uniforms.pixelsPerMeter.value = pixels;

	}

	dispose() {

		this.parent.remove(this.mesh);
		this.mesh.geometry.dispose();
		this.material.dispose();
		this.texture.dispose();

	}

}

/**
 * La fiche d'une planche ou d'une série choisie sur la scène, en lecture seule, écrite d'après le plan déjà descendu. Ses libellés
 * viennent de la vue (`SceneUi::getSheet()`), prêts à recevoir leurs valeurs ; un nom saisi par l'utilisateur ne s'écrit que par
 * `textContent`.
 */
class MapSceneSheet {

	constructor({ container, texts, plan, crops, weekText, chosen, closed }) {

		this.element = container.qs('[data-scene-sheet]');
		this.kind = this.element.qs('[data-scene-sheet-kind]');
		this.title = this.element.qs('[data-scene-sheet-title]');
		this.open = this.element.qs('[data-scene-sheet-open]');
		this.body = this.element.qs('[data-scene-sheet-body]');

		this.texts = texts;
		this.plan = plan;
		this.crops = crops;
		this.weekText = weekText;

		// La fiche d'une série est celle du plan d'assolement
		this.seriesSheet = new SeriesSheet({ texts, plan, stage: crops.stage });

		this.series = new Map(plan.series.map(series => [series.id, series]));
		this.beds = new Map(plan.beds.map(bed => [bed.id, bed]));
		this.plots = new Map(plan.plots.map(plot => [plot.id, plot]));

		this.placesByBed = new Map();

		for(const place of plan.places) {
			MapScene.push(this.placesByBed, place.bed, place);
		}

		this.target = null;

		this.onClick = event => {

			const link = event.target.closest('[data-sheet-series]');

			if(link !== null) {
				chosen({ kind: 'series', id: Number(link.dataset.sheetSeries), bed: this.target?.bed ?? null });
			}

		};

		this.onClose = () => closed();

		this.body.addEventListener('click', this.onClick);
		this.element.qs('[data-scene-sheet-close]').addEventListener('click', this.onClose);

	}

	/**
	 * Montre la cible `{ kind, id, bed }` au jour `date`, ou cache la fiche sans cible.
	 */
	show(target, date) {

		this.target = target;
		this.element.classList.toggle('hide', target === null);

		if(target === null) {
			return;
		}

		this.body.replaceChildren();

		if(target.kind === 'series') {
			this.showSeries(this.series.get(target.id), date);
		} else {
			this.showBed(this.beds.get(target.id), date);
		}

	}

	showBed(bed, date) {

		const plot = this.plots.get(bed.plot);
		const kind = bed.zoneFill ? 'zone' : (bed.plotFill ? 'plot' : 'bed');

		this.kind.textContent = this.texts.kind[kind];
		this.title.textContent = bed.name ?? plot.name;
		this.open.classList.add('hide');

		const rows = SeriesSheet.rows(this.body);

		SeriesSheet.stack(rows, this.texts.place, this.seriesSheet.placeName(bed), false);

		if(bed.length !== null && bed.width !== null) {
			SeriesSheet.row(rows, this.texts.size, this.texts.sizeValue.replace('{length}', MapSceneSheet.number(bed.length)).replace('{width}', MapSceneSheet.number(bed.width / 100)));
		}

		const series = [...new Set((this.placesByBed.get(bed.id) ?? []).map(place => place.series))]
			.map(id => this.series.get(id))
			.sort((a, b) => (a.start ?? '').localeCompare(b.start ?? ''));

		// Les séries suivent l'emplacement dans la même liste, sous le libellé de leur saison : une seconde liste poserait un
		// second filet contre le premier. La saison montrée a toujours le sien, les voisines seulement quand elles occupent la planche
		const seasons = [...new Set([this.plan.season, ...series.map(item => item.season)])].sort((a, b) => a - b);

		for(const season of seasons) {

			const items = series.filter(item => item.season === season);

			rows.append(SeriesSheet.label(this.texts.seasonSeries.replace('{season}', season)));

			if(items.length === 0) {
				SeriesSheet.row(rows, this.texts.noSeries);
			}

			for(const item of items) {

				const link = document.createElement('a');
				link.dataset.sheetSeries = item.id;
				link.append(SeriesSheet.mark(item.cultivations[0]?.color ?? null), item.name);

				SeriesSheet.row(rows, link, this.span(item));

			}

		}

	}

	showSeries(series, date) {

		this.kind.textContent = this.texts.kind.series.replace('{season}', series.season);
		this.title.replaceChildren(SeriesSheet.mark(series.cultivations[0]?.color ?? null), series.name);
		this.open.classList.remove('hide');
		this.open.qs('a').href = this.element.dataset.seriesUrl.replace('{id}', series.id);

		this.seriesSheet.render(this.body, series.id, date);

	}

	// Une date absente du plan se dit « non saisie », jamais « snull »
	span(series) {

		const timeline = this.crops.series.get(series.id)?.timeline ?? null;
		const start = timeline?.planned.start ?? series.start;
		const end = timeline?.planned.end ?? series.stop;

		if(start !== null && end !== null) {
			return this.texts.weekRange.replace('{from}', this.weekText(start)).replace('{to}', this.weekText(end));
		}

		if(end !== null) {
			return this.texts.until.replace('{week}', this.weekText(end));
		}

		return (start !== null) ? this.texts.since.replace('{week}', this.weekText(start)) : this.texts.undated;

	}

	static number(value) {
		return value.toLocaleString(document.documentElement.lang || 'fr', { maximumFractionDigits: 2 });
	}

	dispose() {
		this.body.removeEventListener('click', this.onClick);
		this.element.qs('[data-scene-sheet-close]').removeEventListener('click', this.onClose);
		this.element.classList.add('hide');
	}

}

/**
 * La bâche levée : l'abri sous la souris laisse voir ses cultures, sa bâche s'effaçant en FADE millisecondes. Chaque abri
 * a son matériau, clone du matériau commun, qui garde l'opacité de repos. Le rayon ne vise que les bâches, et seulement à
 * l'image qui suit un mouvement : une vue d'ensemble compte des milliers de plants à toucher. Un écran tactile n'a pas de survol : un
 * toucher sur une bâche hors d'une planche la lève jusqu'au toucher suivant ailleurs, un toucher sur une planche garde son rôle.
 */
class MapSceneFilms {

	static LIFTED = .12;
	static FADE = 200;

	constructor(THREE, canvas, camera, picks) {

		this.THREE = THREE;
		this.canvas = canvas;
		this.camera = camera;
		this.shelters = [];
		this.raycaster = new THREE.Raycaster();

		this.pointer = null;
		this.hovered = null;
		this.touched = null;
		this.touch = null;
		this.at = null;

		// Un glissé fait tourner la vue : l'abri levé le reste jusqu'au lâcher, au lieu de clignoter sous le pointeur
		this.onMove = event => {
			if(event.pointerType !== 'touch') {
				this.pointer = (event.buttons === 0) ? { x: event.clientX, y: event.clientY } : null;
			}
		};

		this.onLeave = event => {
			if(event.pointerType !== 'touch') {
				this.pointer = null;
				this.hovered = null;
			}
		};

		this.onDown = event => {
			if(event.pointerType === 'touch') {
				this.touch = { x: event.clientX, y: event.clientY };
			}
		};

		this.onUp = event => {

			if(event.pointerType !== 'touch' || this.touch === null || Math.hypot(event.clientX - this.touch.x, event.clientY - this.touch.y) > MapScene.CLICK_PX) {
				return;
			}

			this.touch = null;

			const shelter = this.under(event.clientX, event.clientY);

			if(shelter !== null && picks(event) === false) {
				this.touched = shelter;
			} else if(shelter !== this.touched) {
				this.touched = null;
			}

		};

		canvas.addEventListener('pointermove', this.onMove);
		canvas.addEventListener('pointerleave', this.onLeave);
		canvas.addEventListener('pointerdown', this.onDown);
		canvas.addEventListener('pointerup', this.onUp);

	}

	/**
	 * Un abri de plus, avec le matériau de sa bâche. L'appelant pose ses bâches dans `meshes`, et dans `pieces` tout ce qui le
	 * dessine, que MapSceneRuins masque d'un bloc.
	 */
	add(film) {

		const shelter = { film: film.clone(), rest: film.opacity, meshes: [], pieces: [], rectangle: null, base: 0, lift: 0 };
		this.shelters.push(shelter);

		return shelter;

	}

	under(x, y) {

		const box = this.canvas.getBoundingClientRect();

		this.raycaster.setFromCamera(new this.THREE.Vector2((x - box.left) / box.width * 2 - 1, 1 - (y - box.top) / box.height * 2), this.camera());

		// Le rayon traverse ce qui est masqué : un abri en ruines ne se lève plus
		const hit = this.raycaster.intersectObjects(this.shelters.flatMap(shelter => shelter.meshes).filter(mesh => mesh.visible), false)[0] ?? null;

		return (hit === null) ? null : this.shelters.find(shelter => shelter.meshes.includes(hit.object));

	}

	frame(time) {

		const dt = (this.at === null) ? 0 : time - this.at;
		this.at = time;

		if(this.pointer !== null) {
			this.hovered = this.under(this.pointer.x, this.pointer.y);
			this.pointer = null;
		}

		for(const shelter of this.shelters) {

			const lift = Math.min(1, Math.max(0, shelter.lift + ((shelter === this.hovered || shelter === this.touched) ? 1 : -1) * dt / MapSceneFilms.FADE));

			if(lift === shelter.lift) {
				continue;
			}

			shelter.lift = lift;
			shelter.film.opacity = shelter.rest + (MapSceneFilms.LIFTED - shelter.rest) * lift;

		}

	}

	dispose() {

		this.canvas.removeEventListener('pointermove', this.onMove);
		this.canvas.removeEventListener('pointerleave', this.onLeave);
		this.canvas.removeEventListener('pointerdown', this.onDown);
		this.canvas.removeEventListener('pointerup', this.onUp);

		for(const shelter of this.shelters) {
			shelter.film.dispose();
		}

	}

}

/**
 * L'éclat d'une série qui sort pendant la lecture de l'année : des étincelles au-dessus de sa planche. Des points, pas des
 * triangles : un seul appel de dessin, MAX étincelles au plus à la fois, écrites en anneau. Le mouvement est dans le nuanceur, une
 * image ne coûte que l'heure passée en uniforme, et hors d'un éclat l'objet n'est pas dessiné.
 */
class MapSceneSparks {

	static MAX = 600;
	static LIFE = .75;
	static PER_SEGMENT = 40;
	// La hauteur sur laquelle les étincelles partent, en mètres : celle d'un plant de légume adulte
	static SPREAD = .5;

	constructor(THREE, parent) {

		this.parent = parent;
		this.next = 0;
		this.until = 0;
		this.random = MapSceneCrops.random(77);

		this.geometry = new THREE.BufferGeometry();
		this.geometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(MapSceneSparks.MAX * 3), 3));
		// Par étincelle : l'instant de départ, la montée en mètres, la taille en mètres, une graine
		this.geometry.setAttribute('spark', new THREE.BufferAttribute(new Float32Array(MapSceneSparks.MAX * 4).fill(-10), 4));

		this.material = new THREE.ShaderMaterial({
			transparent: true,
			depthWrite: false,
			uniforms: { uTime: { value: 0 }, uLife: { value: MapSceneSparks.LIFE }, uPixels: { value: 1 } },
			vertexShader: `
				attribute vec4 spark;
				uniform float uTime, uLife, uPixels;
				varying float vFade;
				void main() {
					float t = (uTime - spark.x) / uLife, alive = step(0., t) * step(t, 1.);
					float rise = 1. - (1. - t) * (1. - t);
					vec3 p = position + vec3(sin(spark.w * 40. + t * 5.) * .12, spark.y * rise, cos(spark.w * 31. + t * 4.) * .12);
					vFade = alive * sin(3.1416 * min(1., t * 1.15));
					gl_Position = projectionMatrix * modelViewMatrix * vec4(p, 1.);
					gl_PointSize = alive * spark.z * uPixels * (.6 + .4 * sin(3.1416 * t));
				}`,
			fragmentShader: `
				varying float vFade;
				void main() {
					vec2 c = gl_PointCoord - .5;
					// Une étoile à quatre branches sur un halo rond
					float halo = smoothstep(.5, .05, length(c)), star = smoothstep(.06, 0., abs(c.x) * abs(c.y) * 8.) * smoothstep(.5, .1, length(c));
					gl_FragColor = vec4(mix(vec3(1., .93, .62), vec3(1.), star), vFade * max(halo * .55, star));
				}`
		});

		this.points = new THREE.Points(this.geometry, this.material);
		this.points.frustumCulled = false;
		this.points.visible = false;
		this.points.renderOrder = 30;

		// La taille d'un point se donne en pixels de la cible du rendu, qui n'est pas toujours l'écran
		this.points.onBeforeRender = (renderer, scene, camera) => {
			const height = renderer.getRenderTarget()?.height ?? renderer.domElement.height;
			this.material.uniforms.uPixels.value = height * camera.zoom / (camera.top - camera.bottom);
			this.material.uniforms.uTime.value = performance.now() / 1000;
		};

		parent.add(this.points);

	}

	/**
	 * Un éclat le long de la portion `{ center, angle, length, width, top }` d'une planche, en coordonnées du plan.
	 */
	burst(segment) {

		const now = performance.now() / 1000;
		const count = Math.max(8, Math.min(MapSceneSparks.PER_SEGMENT, Math.round(segment.length * 4)));
		const position = this.geometry.attributes.position;
		const spark = this.geometry.attributes.spark;
		const along = { x: Math.cos(segment.angle), z: -Math.sin(segment.angle) };
		const across = { x: -along.z, z: along.x };

		for(let i = 0; i < count; i++) {

			const a = (this.random() - .5) * segment.length;
			const b = (this.random() - .5) * segment.width;
			const k = this.next;

			this.next = (this.next + 1) % MapSceneSparks.MAX;

			position.setXYZ(k, segment.center.x + along.x * a + across.x * b, segment.top + .1 + this.random() * MapSceneSparks.SPREAD, -segment.center.n + along.z * a + across.z * b);
			spark.setXYZW(k, now + this.random() * .18, .6 + this.random() * 1.4, .35 + this.random() * .45, this.random());

		}

		position.needsUpdate = true;
		spark.needsUpdate = true;

		this.until = now + MapSceneSparks.LIFE + .2;
		this.points.visible = true;

	}

	frame() {
		if(this.points.visible && performance.now() / 1000 > this.until) {
			this.points.visible = false;
		}
	}

	dispose() {
		this.parent.remove(this.points);
		this.geometry.dispose();
		this.material.dispose();
	}

}

/**
 * Les bulles de récolte de la lecture de l'année : une par série et par semaine, qui porte son total de la semaine dans
 * chaque unité et sort le lundi. Elle tient tant que la scène montre sa semaine, pour se lire à la pause.
 */
class MapSceneBubbles {

	// Au plus quatre bulles à la fois, les séries qui remplissent le plus de caisses : davantage couvrirait la scène
	static MAX = 4;

	// Ce qu'une caisse contient de chaque unité, pour ranger des récoltes d'unités différentes
	static CRATE = { kg: 20, bunch: 30, unit: 40 };

	// L'écart gardé entre deux bulles, en pixels : collées, deux bulles voisines se liraient comme une seule
	static GAP = 8;

	/**
	 * Le décalage `{ x, y }` de chaque bulle d'une semaine, en pixels, pour qu'aucune n'en couvre une autre, ne sorte du canevas
	 * `room` ni ne passe sous un panneau de `covers` : `boxes` sont leurs rectangles, de la plus grosse récolte à la plus petite.
	 * Ramenée entre les bords, la bulle passe au-dessus de ce qu'elle couvre, ou dessous faute de place en haut. Sans place, ou
	 * l'ancre hors du canevas, elle rend `null` et ne repousse aucune voisine, comme au-delà de MAX. Un rectangle vide est
	 * celui d'une bulle que la page ne montre pas (mode photo) : elle ne bouge pas.
	 */
	static stack(boxes, gap, room, covers) {

		const placed = covers
			.filter(([left, , right]) => right > left)
			.map(([left, top, right, bottom]) => ({ left, top, right, bottom }));
		const fits = (top, bottom) => top >= room.top + gap && bottom <= room.bottom - gap;

		return boxes.map(({ left, right, top, bottom }) => {

			if(right <= left) {
				return { x: 0, y: 0 };
			}

			// Une ancre hors du canevas est celle d'une série que la vue ne montre pas : ramenée au bord, sa bulle se lirait sur une autre
			if((left + right) / 2 < room.left || (left + right) / 2 > room.right || bottom < room.top || bottom > room.bottom) {
				return null;
			}

			const x = Math.max(room.left + gap - left, Math.min(0, room.right - gap - right));
			const height = bottom - top;
			const covering = (rise, sink) => placed.find(box => left + x < box.right + gap && right + x > box.left - gap && rise < box.bottom + gap && sink > box.top - gap);

			// En montant comme en descendant, le bord qui avance prend exactement la valeur à laquelle la comparaison juge la bulle
			// dégagée : recalculé par différence, il peut rester sous la voisine d'une fraction de pixel, et la boucle ne finirait pas
			let sink = Math.min(bottom, room.bottom - gap);
			let rise = sink - height;
			let other;

			while((other = covering(rise, sink))) {
				sink = other.top - gap;
				rise = sink - height;
			}

			if(fits(rise, sink) === false) {

				rise = Math.max(top, room.top + gap);
				sink = rise + height;

				while((other = covering(rise, sink))) {
					rise = other.bottom + gap;
					sink = rise + height;
				}

				if(fits(rise, sink) === false) {
					return null;
				}

			}

			placed.push({ left: left + x, right: right + x, top: rise, bottom: sink });

			return { x, y: rise - top };

		});

	}

	constructor(addons, parent, canvas, covers, geometry) {
		this.addons = addons;
		this.parent = parent;
		this.canvas = canvas;
		this.covers = covers;
		this.geometry = geometry;
		this.shown = [];
		this.week = null;
		this.loose = false;
	}

	/**
	 * `weeks` : `[{ at: { x, n }, harvests }]`, une entrée par série récoltée dans la semaine `week`.
	 */
	show(week, weeks, texts) {

		this.hold(week);

		// Un geste un lundi de lecture redemande la semaine : reposée, une bulle couvrirait sa jumelle
		if(this.shown.length > 0) {
			return;
		}

		const crates = harvests => harvests.reduce((sum, harvest) => sum + harvest.quantity / MapSceneBubbles.CRATE[harvest.unit], 0);

		for(const { at, harvests } of weeks.sort((a, b) => crates(b.harvests) - crates(a.harvests)).slice(0, MapSceneBubbles.MAX)) {

			const bubble = document.createElement('span');
			bubble.className = 'map-scene-label map-scene-label-plot map-scene-gain';
			bubble.innerHTML = Lime.Asset.icon('basket2');
			bubble.append(' +'+ SeriesSheet.quantities(texts, harvests));
			// Cachée jusqu'à ce que `frame()` lui ait trouvé sa place, sinon elle couvrirait une voisine le temps d'une image
			bubble.style.visibility = 'hidden';

			const element = document.createElement('div');
			element.append(bubble);

			const label = new this.addons.CSS2DObject(element);
			label.position.set(at.x, this.geometry.height(at.x, at.n) + 1.5, -at.n);

			this.parent.add(label);
			this.shown.push({ label, bubble, shift: 0, lift: 0, placed: false });

		}

	}

	hold(week) {

		if(this.week === week) {
			return;
		}

		// CSS2DRenderer laisse en place l'élément d'un objet retiré de la scène : il se retire à la main
		for(const { label } of this.shown) {
			this.parent.remove(label);
			label.element.remove();
		}

		this.shown = [];
		this.week = week;

	}

	// Un geste de caméra terminé, ou le canevas ou un panneau qui change de taille : les décalages posés sur l'ancienne vue ne
	// séparent plus les bulles ni ne les gardent dans la zone visible
	loosen() {
		this.loose = true;
	}

	/**
	 * `moving` dit si la caméra a bougé à cette image : après un geste, l'amortissement et la molette la déplacent encore, et les
	 * bulles ne se replacent qu'une fois la caméra arrêtée.
	 */
	frame(moving) {

		if(this.loose && moving === false) {

			this.loose = false;

			for(const shown of this.shown) {
				shown.placed = false;
			}

		}

		// Une bulle se mesure à l'image qui suit sa pose par CSS2DRenderer, avec les autres de sa semaine : toutes les mesures, puis
		// toutes les écritures. Elle monte par `translate`, que l'animation de sa montée, sur `transform`, ne réécrit pas ; une bulle
		// replacée se mesure sans le décalage qu'elle porte déjà, que le rectangle compte. Le canevas ne se lit que s'il y a une bulle
		// à placer : sa lecture forcerait sinon une mise en page à chaque image
		const fresh = this.shown.filter(shown => shown.placed === false && shown.bubble.isConnected);

		if(fresh.length === 0) {
			return;
		}

		const origin = this.canvas.getBoundingClientRect();
		const boxes = fresh.map(({ bubble, shift, lift }) => {
			// Le rectangle compte la montée à l'instant de la mesure, souvent à son début : la bulle se place là où la montée l'amène
			const style = getComputedStyle(bubble);
			const rise = parseFloat(style.getPropertyValue('--map-scene-gain-rise')) - new DOMMatrixReadOnly(style.transform).m42;
			const { left, right, top, bottom } = bubble.getBoundingClientRect();
			return { left: left - shift - origin.left, right: right - shift - origin.left, top: top - lift + rise - origin.top, bottom: bottom - lift + rise - origin.top };
		});

		// Une bulle sans place reste cachée et compte pour placée : elle ne se remesure qu'au prochain geste de caméra ou changement
		// de taille, quand la vue a pu lui en faire une
		MapSceneBubbles.stack(boxes, MapSceneBubbles.GAP, { left: 0, top: 0, right: origin.width, bottom: origin.height }, this.covers()).forEach((position, index) => {

			if(position === null) {
				fresh[index].bubble.style.visibility = 'hidden';
			} else {
				fresh[index].bubble.style.translate = position.x +'px '+ position.y +'px';
				fresh[index].bubble.style.visibility = '';
				fresh[index].shift = position.x;
				fresh[index].lift = position.y;
			}

			fresh[index].placed = true;

		});

	}

	dispose() {
		this.hold(null);
	}

}

/**
 * Le ciel et la direction du soleil, le glissement de la lumière d'une semaine à l'autre pendant la lecture, le survol
 * d'ouverture et les rafales qui balaient les cultures, repris du prototype (`.claude/state/3d/poc/index.html`).
 */
class MapSceneAmbience {

	// Le haut du ciel garde le rapport qu'avaient entre eux les deux tons du ciel de jour du prototype ; l'horizon est le ciel de la
	// saison
	static SKY_TOP = '#bfe2f6';
	static SKY_HORIZON = '#cfe8f5';

	// La scène n'a pas d'heure : le soleil prend l'azimut de 10 h, heure solaire du lieu, à la hauteur que la saison lui donne.
	// L'heure légale ferait sauter l'azimut d'une heure aux deux changements d'heure, et l'ombre tournerait d'un jour à l'autre
	static SUN_HOUR = 10;
	static SUN_DISTANCE = 600;

	static GLIDE_MS = 600;

	static INTRO_MS = 3000;
	static INTRO_TURN = -.6;
	static INTRO_GESTURES = ['pointerdown', 'wheel', 'keydown', 'touchstart'];

	// La rafale du prototype, .29, calmée aux deux tiers comme le balancement (`MapScene.WIND_SWAY`)
	static GUST = .29 * .67;
	static GUST_DIRECTION = [.94, .34];

	constructor(THREE, scene, plan) {

		this.THREE = THREE;
		this.scene = scene;
		this.center = plan.center;

		this.canvas = document.createElement('canvas');
		this.canvas.width = 2;
		this.canvas.height = 256;

		this.texture = new THREE.CanvasTexture(this.canvas);
		this.texture.colorSpace = THREE.SRGBColorSpace;

		scene.background = this.texture;

		const top = new THREE.Color(MapSceneAmbience.SKY_TOP);
		const horizon = new THREE.Color(MapSceneAmbience.SKY_HORIZON);
		this.topRatio = new THREE.Color(top.r / horizon.r, top.g / horizon.g, top.b / horizon.b);

		this.top = new THREE.Color();
		this.horizon = new THREE.Color();
		this.painted = null;

		// La bâche d'abri du prototype, blanche et sans ombrage : elle se lit sur toutes les herbes, et son ossature la dessine
		this.film = new THREE.MeshBasicMaterial({ color: '#ffffff', transparent: true, opacity: .32, side: THREE.DoubleSide, depthWrite: false });
		this.arches = new THREE.LineBasicMaterial({ color: '#9aa7b4' });

		this.gustForce = { value: 0 };
		this.gustDirection = { value: new THREE.Vector2(...MapSceneAmbience.GUST_DIRECTION) };
		this.windiness = .5;

		this.glide = null;
		this.week = null;
		this.intro = null;
		this.stopIntro = () => this.landIntro();

	}

	/**
	 * Le dégradé du ciel, de son haut à l'horizon. La toile ne se repeint que si l'une des deux couleurs change.
	 */
	paint(top, horizon) {

		this.top.copy(top);
		this.horizon.copy(horizon);

		const key = top.getHexString() + horizon.getHexString();

		if(key === this.painted) {
			return;
		}

		this.painted = key;

		const context = this.canvas.getContext('2d');
		const gradient = context.createLinearGradient(0, 0, 0, 256);
		gradient.addColorStop(0, '#' + top.getHexString());
		gradient.addColorStop(.8, '#' + horizon.getHexString());
		gradient.addColorStop(1, '#' + horizon.getHexString());
		context.fillStyle = gradient;
		context.fillRect(0, 0, 2, 256);

		this.texture.needsUpdate = true;

	}

	/**
	 * Le ciel d'une saison, `sky` en composantes : l'horizon, et le haut qui en garde le rapport du ciel de jour.
	 */
	sky(sky) {

		const horizon = new this.THREE.Color().setRGB(...sky);
		const top = horizon.clone().multiply(this.topRatio);

		this.paint(top, horizon);

	}

	/**
	 * La direction du soleil à SUN_HOUR ce jour-là, à la hauteur que la saison donne à `offset`, en vecteur de SUN_DISTANCE mètres.
	 */
	sun(date, offset) {

		const [year, month, day] = date.split('-').map(Number);
		const [lng, lat] = this.center;
		// L'heure solaire moyenne avance sur l'heure universelle d'une heure tous les 15° de longitude vers l'est
		const { azimuth } = MapSceneAmbience.sunPosition(Date.UTC(year, month - 1, day) + (MapSceneAmbience.SUN_HOUR - lng / 15) * 3600000, lat, lng);
		const altitude = Math.atan2(offset.y, Math.hypot(offset.x, offset.z));

		const direction = new this.THREE.Vector3(-Math.sin(azimuth) * Math.cos(altitude), Math.sin(altitude), Math.cos(azimuth) * Math.cos(altitude));

		return direction.normalize().multiplyScalar(MapSceneAmbience.SUN_DISTANCE);

	}

	/**
	 * La position du soleil en radians, azimut compté depuis le sud vers l'ouest, d'après les formules de SunCalc.
	 */
	static sunPosition(time, lat, lng) {

		const rad = Math.PI / 180;
		const e = rad * 23.4397;
		const d = time / 86400000 - .5 + 2440588 - 2451545;
		const M = rad * (357.5291 + .98560028 * d);
		const L = M + rad * (1.9148 * Math.sin(M) + .02 * Math.sin(2 * M) + .0003 * Math.sin(3 * M)) + rad * 102.9372 + Math.PI;
		const declination = Math.asin(Math.sin(e) * Math.sin(L));
		const ascension = Math.atan2(Math.sin(L) * Math.cos(e), Math.cos(L));
		const H = rad * (280.16 + 360.9856235 * d) + rad * lng - ascension;
		const phi = rad * lat;

		return {
			azimuth: Math.atan2(Math.sin(H), Math.cos(H) * Math.sin(phi) - Math.tan(declination) * Math.cos(phi)),
			altitude: Math.asin(Math.sin(phi) * Math.sin(declination) + Math.cos(phi) * Math.cos(declination) * Math.cos(H))
		};

	}

	/**
	 * Appelé avant que la lumière d'un jour ne se pose. Pendant la lecture, un changement de semaine glisse de la lumière montrée à
	 * la nouvelle en GLIDE_MS à la vitesse Normale : l'état montré se prend ici, le nouveau à l'image suivante. Un autre jour de la même semaine pendant un
	 * glissement en change seulement l'arrivée, et toute autre lumière posée hors de la lecture l'annule.
	 */
	prepare(week, playing, speed, lights) {

		this.windiness = MapSceneAmbience.windiness(week);

		if(playing && this.week !== null && week !== this.week) {
			this.glide = { from: this.state(lights), to: null, start: null, duration: MapSceneAmbience.GLIDE_MS / speed, lights };
		} else if(playing && this.glide !== null) {
			this.glide.to = null;
		} else {
			this.glide = null;
		}

		this.week = week;

	}

	// Le vent de la semaine, tiré comme le temps qu'il fait au prototype (`weatherOf`) : le second tirage de sa graine
	static windiness(week) {

		const r = MapSceneCrops.random(week * 7919 + 13);
		r();

		return .25 + r() * .75;

	}

	state({ sun, hemisphere, ground, fold }) {
		return {
			sun: sun.intensity, sunColor: sun.color.clone(),
			hemisphere: hemisphere.intensity, hemisphereColor: hemisphere.color.clone(), hemisphereGround: hemisphere.groundColor.clone(),
			top: this.top.clone(), horizon: this.horizon.clone(),
			ground: ground.color.clone(), fold: fold.color.clone()
		};
	}

	blend(a, b, k, { sun, hemisphere, ground, fold }) {

		sun.intensity = a.sun + (b.sun - a.sun) * k;
		sun.color.copy(a.sunColor).lerp(b.sunColor, k);
		hemisphere.intensity = a.hemisphere + (b.hemisphere - a.hemisphere) * k;
		hemisphere.color.copy(a.hemisphereColor).lerp(b.hemisphereColor, k);
		hemisphere.groundColor.copy(a.hemisphereGround).lerp(b.hemisphereGround, k);
		ground.color.copy(a.ground).lerp(b.ground, k);
		fold.color.copy(a.fold).lerp(b.fold, k);

		this.paint(a.top.clone().lerp(b.top, k), a.horizon.clone().lerp(b.horizon, k));

	}

	/**
	 * Le survol d'ouverture : la vue part dézoomée et tournée, et rejoint en trois secondes le cadrage d'ouverture. Le premier geste
	 * pose ce cadrage et rend la main : arrêtée en vol, la vue garderait un angle et un zoom que personne n'a choisis. Le zoom ne
	 * descend pas sous le plancher que MapControls impose à chaque image.
	 */
	startIntro(camera, controls) {

		this.intro = { camera, controls, target: controls.target.clone(), offset: camera.position.clone().sub(controls.target), zoom: camera.zoom, start: null, turned: new this.THREE.Vector3() };

		MapSceneAmbience.INTRO_GESTURES.forEach(type => window.addEventListener(type, this.stopIntro, true));

	}

	/**
	 * Le survol cède la place au premier geste ou à un cadrage demandé par le code : la vue reprend l'orientation et le zoom où il
	 * l'aurait posée, et le geste ou le cadrage partent d'elle plutôt que d'une vue prise en plein vol.
	 */
	landIntro() {

		const intro = this.intro;

		if(intro === null) {
			return;
		}

		const { camera, controls } = intro;

		camera.position.copy(intro.target).add(intro.offset);
		camera.zoom = Math.max(controls.minZoom, intro.zoom);
		camera.lookAt(intro.target);
		camera.updateProjectionMatrix();

		this.endIntro();

	}

	endIntro() {

		this.intro = null;

		MapSceneAmbience.INTRO_GESTURES.forEach(type => window.removeEventListener(type, this.stopIntro, true));

	}

	/**
	 * Une image, avant que MapControls ne la reprenne : le survol tant qu'il dure.
	 */
	fly(time, camera, controls) {

		const intro = this.intro;

		if(intro === null) {
			return;
		}

		intro.start ??= time;

		const k = Math.min(1, (time - intro.start) / MapSceneAmbience.INTRO_MS);
		const e = 1 - Math.pow(1 - k, 3);

		intro.turned.copy(intro.offset).applyAxisAngle(this.THREE.Object3D.DEFAULT_UP, (1 - e) * MapSceneAmbience.INTRO_TURN);
		camera.position.copy(intro.target).add(intro.turned);
		camera.zoom = Math.max(controls.minZoom, intro.zoom * (.5 + .5 * e));
		camera.lookAt(intro.target);
		camera.updateProjectionMatrix();

		if(k >= 1) {
			this.endIntro();
		}

	}

	/**
	 * Une image, une fois la vue posée : la force de la rafale et le glissement de la lumière.
	 */
	frame(time) {

		const seconds = time / 1000;
		this.gustForce.value = this.windiness * (.35 + .65 * Math.pow(.5 + .5 * Math.sin(seconds * .21) * Math.sin(seconds * .13 + 1), 2));

		const glide = this.glide;

		if(glide !== null) {

			glide.to ??= this.state(glide.lights);
			glide.start ??= time;

			const k = Math.min(1, (time - glide.start) / glide.duration);
			this.blend(glide.from, glide.to, k, glide.lights);

			if(k >= 1) {
				this.glide = null;
			}

		}

	}

	/**
	 * Les rafales sur un matériau des cultures du socle, dont le balancement pose déjà `windRoot` et `windLift` : une bande qui
	 * balaie le site dans le sens du vent, sa direction ramenée dans le repère de chaque instance.
	 */
	gust(material) {

		const compile = material.onBeforeCompile;
		const key = material.customProgramCacheKey();

		material.onBeforeCompile = (shader, renderer) => {

			compile(shader, renderer);

			shader.uniforms.ambGust = this.gustForce;
			shader.uniforms.ambGustDir = this.gustDirection;
			shader.vertexShader = 'uniform float ambGust;\nuniform vec2 ambGustDir;\n' + shader.vertexShader.replace('#include <project_vertex>', `
				float ambWave = pow(max(0.0, sin(dot(windRoot.xz, ambGustDir) * .045 - windTime * 1.1)), 4.0) * ambGust;
				vec3 ambLocal = normalize(transpose(mat3(instanceMatrix)) * vec3(ambGustDir.x, 0.0, ambGustDir.y));
				transformed += ambLocal * ambWave * ${MapSceneAmbience.GUST.toFixed(3)} * windLift;
				#include <project_vertex>`);

		};

		material.customProgramCacheKey = () => key + '-gust';
		material.needsUpdate = true;

	}

	dispose() {

		this.endIntro();
		this.texture.dispose();
		this.film.dispose();
		this.arches.dispose();

	}

}

/**
 * La pancarte de bois de chaque parcelle de plein champ tracée et de chaque zone qui n'en porte aucune, plantée sur un côté tourné
 * vers la caméra d'ouverture, au plus près de sa parcelle et d'abord là où elle ne cache aucune planche. Elle fait face à la
 * caméra et grossit quand la vue recule, depuis son pied, qui ne bouge pas.
 *
 * Elles ont toutes la même taille, et aucune parcelle n'en est privée : une parcelle serrée entre des planches plante la
 * sienne plus loin, sur un autre côté, et à défaut là où elle en couvre. Une place se juge à la taille
 * que la pancarte a sur la vue d'une zone : plus loin, quand toute la structure tient à l'écran, elle peut couvrir un bout de
 * planche.
 */
class MapSceneBoards {

	// Le fond de la face peinte, que reprend la copie d'une pancarte en ruines
	static FACE = '#c99a5f';

	static BOARD = { width: 2.8, depth: .3, height: 2.3 };

	// L'écart de chaque poteau au milieu de la planche, en mètres, à la taille 1
	static POST = 1.05;
	static SCALE_MAX = 3;

	// La taille à laquelle une place se juge, celle d'une pancarte sur la vue d'une zone : jugée à sa plus grande taille, elle
	// se planterait trop loin de sa parcelle pour se lire comme la sienne
	static SCALE_POSE = 2.15;

	// La hauteur de vue, en mètres, à partir de laquelle une pancarte grossit
	static GROWS_FROM = 14;

	// Où elle se plante sur un côté, en part de sa longueur, puis à quelle distance du bord, du plus près au plus loin.
	// GAPS_FAR ne s'essaie qu'après tous les côtés à GAPS : à cette distance, une pancarte se lit moins bien comme celle de sa parcelle
	static ALONG = [.5, .25, .75];
	static GAPS = [1.5, 3, 5];
	static GAPS_FAR = [8, 12];

	// Le corps du nom, en part de la hauteur de la face : celui d'un nom qui tient sur une ligne, puis celui d'un nom plus long.
	// Puis la part de la largeur qu'une ligne peut occuper, et l'interligne en part du corps
	static LETTER = .52;
	static LETTER_SMALL = .42;
	static LETTER_WIDTH = .94;
	static LEADING = .95;

	// Le cadre peint de la face, en points de sa toile, et ce que le nom laisse libre avant lui comme entre ses deux lignes
	static FRAME = 10;
	static FRAME_ROOM = 4;

	constructor({ THREE, addons, plan, geometry, camera, controls, parent, gradientMap }) {

		const G = ScenePlanGeometry;
		const B = MapSceneBoards;

		this.THREE = THREE;
		this.parent = parent;
		// `this.geometry` porte déjà la géométrie de la pancarte : le sol prend un autre nom
		this.ground = geometry;

		this.wood = new THREE.MeshToonMaterial({ color: '#9b6f45', gradientMap });
		this.faces = [];

		// Poteaux d'abord, puis la planche : ses quatre chants suivent les poteaux dans l'index, et la pancarte tient en deux appels de
		// dessin, bois et face peinte. Les poteaux s'arrêtent sous la planche : rien ne passe devant le texte
		this.geometry = addons.mergeGeometries([
			new THREE.BoxGeometry(.14, 1.35, .14).translate(-B.POST, .675, 0),
			new THREE.BoxGeometry(.14, 1.35, .14).translate(B.POST, .675, 0),
			new THREE.BoxGeometry(2.8, 1, .1).translate(0, 1.85, 0)
		]);
		this.geometry.clearGroups();
		this.geometry.addGroup(0, 96, 0);
		this.geometry.addGroup(96, 12, 1);

		const eye = camera.position.clone().sub(controls.target);
		const away = G.norm({ x: -eye.x, n: eye.z });
		const toCamera = G.mul(away, -1);
		const slope = eye.y / Math.hypot(eye.x, eye.z);
		const turn = Math.atan2(eye.x, eye.z);

		const beds = geometry.beds;
		const bedPoints = beds.flatMap(bed => Array.from({ length: Math.ceil(bed.length / 3) + 1 }, (_, i) => G.add(G.add(bed.origin, G.mul(bed.w, bed.width / 2)), G.mul(bed.u, Math.min(bed.length, i * 3)))));
		const glass = [...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ points }) => points);

		// Un volume cache le sol derrière lui sur sa hauteur divisée par la pente du regard
		const hides = (rect, height, points) => {

			const long = (rect.length > rect.width);
			const radius = Math.min(rect.length, rect.width) / 2 + .5;
			const count = Math.ceil(Math.max(rect.length, rect.width) / (radius * 2));
			const middle = MapSceneBoards.center(rect);
			const axis = long ? rect.u : rect.w;
			const reach = height / slope + radius;

			const span = reach + Math.max(rect.length, rect.width);
			const centers = Array.from({ length: count }, (_, i) => G.add(middle, G.mul(axis, (i + .5 - count / 2) * radius * 2)));

			// Un seul passage sur les planches, et d'abord l'écart de celles qui sont trop loin pour être cachées : cette boucle
			// porte l'essentiel du coût de la pose
			for(const q of points) {

				if(Math.abs(q.x - middle.x) >= span || Math.abs(q.n - middle.n) >= span) {
					continue;
				}

				for(const c of centers) {

					const along = (q.x - c.x) * away.x + (q.n - c.n) * away.n;

					if(along > 0 && along < reach && Math.abs((q.x - c.x) * away.n - (q.n - c.n) * away.x) < radius) {
						return true;
					}

				}

			}

			return false;

		};

		// Ce qui fait parcelle : une parcelle de plein champ tracée, ou une zone qui n'en porte aucune ou dont des planches sont hors
		// de tout tracé
		const traced = new Set(plan.plots.filter(plot => plot.coordinates !== null && plot.mode === 'open-field').map(plot => plot.zone));

		for(const bed of beds) {

			const entry = geometry.plots.get(bed.plot);

			if(entry !== undefined && entry.drawn === false) {
				traced.delete(entry.plot.zone);
			}

		}

		const parcels = [
			...[...geometry.plots.values()].filter(({ plot, drawn }) => drawn && plot.mode === 'open-field').map(({ plot, points }) => ({ name: plot.name, points })),
			...plan.zones.filter(zone => geometry.zones.has(zone.id) && traced.has(zone.id) === false).map(zone => ({ name: zone.name, points: geometry.zones.get(zone.id) }))
		].filter(({ name, points }) => name !== null && points.length > 0);

		// Une planche n'est comparée à une place que si elle peut la toucher : le coût de la pose croît sinon comme le produit des
		// places essayées et des planches de la structure
		const reaches = beds.map(bed => ({ bed, middle: MapSceneBoards.center(bed), radius: Math.hypot(bed.length, bed.width) / 2 }));
		const clear = (p, rect, near) => near.every(({ bed, middle, radius }) => Math.hypot(middle.x - p.x, middle.n - p.n) > radius + grown.width || B.apart(bed, rect));

		// Deux pancartes se comparent à leur taille de pose, elles grossissent toutes ensemble au dézoom. En profondeur, celle de
		// devant couvre ce que sa hauteur cache au regard : deux pancartes l'une derrière l'autre se superposent
		const largest = { width: B.BOARD.width * B.SCALE_POSE, depth: B.BOARD.height * B.SCALE_POSE / slope };

		// La même emprise à leur plus grande taille, et de combien une place s'y écarte de la pancarte déjà plantée qu'elle touche
		// le plus : un et plus quand elle n'en touche aucune. Une place qui en touche une ne sert que faute d'une autre près de la
		// parcelle, et c'est alors celle qui la touche le moins
		const widest = { width: B.BOARD.width * B.SCALE_MAX + 1, depth: B.BOARD.height * B.SCALE_MAX / slope + 1 };
		const across = { x: Math.cos(turn), n: Math.sin(turn) };
		const spare = p => Math.min(...placed.map(other => Math.max(Math.abs(G.dot(G.sub(p, other.p), across)) / widest.width, Math.abs(G.dot(G.sub(p, other.p), toCamera)) / widest.depth)));

		// Le regard écrase la profondeur : à l'écran, une place plus près de sa parcelle sur le sol peut se lire plus près d'une
		// voisine. Un point du sol tel qu'il s'y montre, et la face d'une pancarte, qui s'y dresse depuis son pied
		const squash = eye.y / eye.length();
		const shown = q => ({ x: G.dot(q, across), n: G.dot(q, toCamera) * squash });
		const raised = p => [-1, 0, 1].flatMap(side => [0, 1].map(up => ({ x: shown(p).x + side * largest.width / 2, n: shown(p).n - up * largest.depth * squash })));

		// Deux pancartes plus éloignées que la diagonale de cette emprise ne se touchent pas
		const apart = Math.hypot(largest.width + 1, largest.depth + 1);

		// Ce qu'une pancarte écrase et ce qu'elle cache se jugent à la même taille, depuis son pied, qui ne bouge pas
		const grown = { width: B.BOARD.width * B.SCALE_POSE, depth: B.BOARD.depth * B.SCALE_POSE };

		// Jusqu'où une place essayée peut toucher ou cacher une planche, depuis le bord de sa parcelle
		const margin = Math.max(...B.GAPS_FAR) + B.SCALE_MAX * (B.BOARD.width + B.BOARD.height / slope);

		// Sur des côtés donnés, les places de l'écart le plus court au plus long, et à écart égal au plus près de la caméra
		const places = (sides, gaps) => sides
			.flatMap(side => B.ALONG.flatMap(k => gaps.map(gap => ({ p: G.add(G.lerp(side.a, side.b, k), G.mul(side.out, gap)), gap }))))
			.sort((a, b) => (a.gap - b.gap) || (G.dot(b.p, toCamera) - G.dot(a.p, toCamera)));

		this.boards = [];
		const placed = [];

		for(const parcel of parcels) {

			const sides = MapSceneBoards.sides(parcel.points);
			const facing = sides.filter(side => G.dot(side.out, toCamera) > .2);

			// Seul ce qui est à portée de cette parcelle se compare à ses places
			const xs = parcel.points.map(q => q.x);
			const ns = parcel.points.map(q => q.n);
			const box = { x0: Math.min(...xs) - margin, x1: Math.max(...xs) + margin, n0: Math.min(...ns) - margin, n1: Math.max(...ns) + margin };
			const around = (q, more) => q.x > box.x0 - more && q.x < box.x1 + more && q.n > box.n0 - more && q.n < box.n1 + more;
			const reached = points => points.some(q => q.x > box.x0) && points.some(q => q.x < box.x1) && points.some(q => q.n > box.n0) && points.some(q => q.n < box.n1);
			const near = {
				points: bedPoints.filter(q => around(q, 0)),
				beds: reaches.filter(({ middle, radius }) => around(middle, radius)),
				glass: glass.filter(reached)
			};

			// Les parcelles voisines : une zone qui fait parcelle autour de celle-ci n'en est pas une
			const middle = G.mul(parcel.points.reduce(G.add), 1 / parcel.points.length);
			const neighbours = parcels.filter(other => other !== parcel && reached(other.points) && G.inside(middle, other.points) === false).map(({ points }) => points);

			// De combien une place est plus près de sa parcelle que de toute voisine, nul quand elle est chez l'une d'elles
			const belongs = p => Math.min(...neighbours.map(points => G.inside(p, points) ? 0 : B.distance(p, points))) / B.distance(p, parcel.points);

			// Si la pancarte plantée là se lit comme la sienne : sa face, à l'écran, strictement plus près d'elle que de toute voisine
			const outlines = { own: parcel.points.map(shown), others: neighbours.map(points => points.map(shown)) };
			const reads = p => {

				const face = raised(p);
				const gap = points => Math.min(...face.map(q => G.inside(q, points) ? 0 : B.distance(q, points)));
				const own = gap(outlines.own);

				return outlines.others.every(points => gap(points) > own);

			};

			// Devant la parcelle, puis sur ses autres côtés, enfin plus loin d'elle : un rang ne s'essaie que si le précédent n'a
			// donné aucune place qui se lise comme la sienne, et la première qui ne cache rien et se lit ainsi arrête la recherche
			const ranks = [() => places(facing, B.GAPS), () => places(sides.filter(side => facing.includes(side) === false), B.GAPS), () => places(sides, B.GAPS_FAR)];

			let tried = null;
			let first = null;
			let lent = null;
			let tight = null;
			let blurred = null;
			let open = null;

			for(const rank of ranks) {

				// Les grands écarts ne s'essaient pas quand la parcelle s'en remet à une place proche qui en touche une autre de
				// loin : une place lointaine la lui prendrait. Ils s'essaient quand elle s'en remet à une place dégagée qui se lirait
				// comme le nom d'une voisine, une place lointaine qui se lit comme la sienne valant mieux
				if(open !== null || (tight !== null && blurred === null && rank === ranks[2])) {
					break;
				}

				for(const { p } of rank()) {

					const place = { p, outer: B.rectAt(p, turn, largest, .5) };

					tried ??= place;

					if(near.glass.some(points => G.inside(p, points)) || clear(p, B.rectAt(p, turn, grown, .5), near.beds) === false || placed.every(other => Math.hypot(other.p.x - p.x, other.p.n - p.n) > apart || B.apart(other.outer, place.outer)) === false) {
						continue;
					}

					// Entre deux places libres, celle qui n'est pas chez une voisine
					const lodged = neighbours.some(points => G.inside(p, points));

					if(first === null || (first.lodged && lodged === false)) {
						first = { ...place, lodged };
					}

					if(hides(B.rectAt(p, turn, grown), B.BOARD.height * B.SCALE_POSE, near.points)) {
						continue;
					}

					const share = belongs(p);

					if(share > 1) {

						const free = spare(p);

						if(free >= 1) {

							if(reads(p)) {
								open = place;
								break;
							}

							// Dégagée, mais qui se lirait comme le nom d'une voisine : la recherche continue, et la garde faute de mieux
							blurred ??= place;
							continue;

						}

						if(tight === null || free > tight.free) {
							tight = { ...place, free };
						}

						continue;

					}

					// Faute de mieux, la place la moins disputée à une voisine, hors de chez elle
					if(share > 0 && (lent === null || share > lent.share)) {
						lent = { ...place, share };
					}

				}

			}

			// Une parcelle a toujours sa pancarte : faute de place dégagée qui se lise comme la sienne, elle prend la première
			// dégagée, puis celle qui en touche une autre de loin, puis la moins disputée, puis cache des planches plutôt que de se
			// planter chez une voisine, et faute de place libre se plante à la première essayée. Un contour sans côté assez long pour
			// porter une place la reçoit en son milieu
			const spot = open ?? blurred ?? tight ?? lent ?? first ?? tried ?? { p: middle, outer: B.rectAt(middle, turn, largest, .5) };

			placed.push(spot);

			const face = new THREE.MeshToonMaterial({ map: MapSceneBoards.texture(THREE, parcel.name, 512, 184), emissive: '#2a1a0a', gradientMap });
			this.faces.push(face);

			const board = new THREE.Mesh(this.geometry, [this.wood, face]);
			board.position.set(spot.p.x, geometry.height(spot.p.x, spot.p.n), -spot.p.n);
			parent.add(board);

			this.boards.push({ board, base: spot.p });

		}

	}

	/**
	 * La planche peinte : le bois veiné, son cadre et le nom, d'un même corps pour tous les noms qui tiennent sur une ligne, et
	 * d'un corps plus petit, sur deux lignes au besoin, pour les autres.
	 */
	static texture(THREE, text, width, height) {

		const canvas = document.createElement('canvas');
		canvas.width = width;
		canvas.height = height;

		const context = canvas.getContext('2d');
		context.fillStyle = MapSceneBoards.FACE;
		context.fillRect(0, 0, width, height);

		const r = MapSceneCrops.random(text.length * 131 + width);
		context.strokeStyle = 'rgba(110, 72, 38, .28)';
		context.lineWidth = 3;

		for(let i = 0; i < 9; i++) {
			const y = r() * height;
			context.beginPath();
			context.moveTo(0, y);
			context.bezierCurveTo(width * .3, y + (r() - .5) * 18, width * .7, y + (r() - .5) * 18, width, y + (r() - .5) * 10);
			context.stroke();
		}

		context.strokeStyle = '#7a5130';
		context.lineWidth = MapSceneBoards.FRAME;
		context.strokeRect(MapSceneBoards.FRAME / 2, MapSceneBoards.FRAME / 2, width - MapSceneBoards.FRAME, height - MapSceneBoards.FRAME);

		const B = MapSceneBoards;

		const font = size => '800 '+ size +'px system-ui, sans-serif';
		const fits = name => context.measureText(name).width <= width * B.LETTER_WIDTH;

		context.fillStyle = '#4a2e17';
		context.textAlign = 'center';
		context.textBaseline = 'middle';

		let size = height * B.LETTER;
		let lines = [text];

		context.font = font(size);

		if(fits(text) === false) {

			size = height * B.LETTER_SMALL;
			context.font = font(size);

			lines = B.lines(text, fits);

		}

		// Le nom se centre sur son encre, du haut de sa première ligne au bas de sa dernière. Deux lignes s'écartent d'au moins ce
		// que le jambage de la première et l'accent de la seconde occupent, et le corps se réduit quand le cadre ne laisse pas
		// cette place : elles ne se touchent jamais
		const measure = () => {

			const inks = lines.map(line => context.measureText(line));
			const ascent = inks[0].actualBoundingBoxAscent;
			const descent = inks[inks.length - 1].actualBoundingBoxDescent;
			const leading = (inks.length === 2) ? Math.max(size * B.LEADING, inks[0].actualBoundingBoxDescent + inks[1].actualBoundingBoxAscent + B.FRAME_ROOM) : 0;

			return { ascent, leading, tall: ascent + leading + descent };

		};

		const inner = height - 2 * (B.FRAME + B.FRAME_ROOM);

		let ink = measure();

		if(ink.tall > inner) {

			size *= (inner - B.FRAME_ROOM) / (ink.tall - B.FRAME_ROOM);
			context.font = font(size);

			ink = measure();

		}

		const top = (height - ink.tall) / 2 + ink.ascent;

		lines.forEach((line, row) => context.fillText(line, width / 2, top + row * ink.leading));

		const texture = new THREE.CanvasTexture(canvas);
		texture.colorSpace = THREE.SRGBColorSpace;
		texture.anisotropy = 4;

		return texture;

	}

	/**
	 * Le nom tel qu'il s'écrit, sur une ligne s'il y tient, sinon sur deux. Il se partage entre deux mots, au plus égal quand les
	 * deux lignes tiennent, et sinon la première prend tout ce qui tient : seule la seconde se coupe. Un mot plus long que la face
	 * se partage là où elle s'arrête.
	 */
	static lines(text, fits) {

		if(fits(text)) {
			return [text];
		}

		const characters = MapSceneBoards.characters(text);
		const split = at => [characters.slice(0, at).join('').trimEnd(), characters.slice(at).join('').trimStart()];
		const length = line => MapSceneBoards.characters(line).length;

		const words = characters.map((character, at) => (character === ' ') ? at : 0).filter(at => at > 0).map(split).filter(([head]) => head !== '' && fits(head));
		const whole = words.filter(([, tail]) => fits(tail));

		let pair;

		if(whole.length > 0) {
			pair = whole.reduce((best, pair) => (Math.abs(length(pair[0]) - length(pair[1])) < Math.abs(length(best[0]) - length(best[1]))) ? pair : best);
		} else if(words.length > 0) {
			pair = words[words.length - 1];
		} else {

			let at = characters.length - 1;

			while(at > 1 && fits(characters.slice(0, at).join('')) === false) {
				at--;
			}

			pair = split(at);

		}

		return [pair[0], MapSceneBoards.shorten(pair[1], fits)];

	}

	/**
	 * Les caractères d'un nom, et non ses unités de code : un émoji coupé entre les deux siennes s'écrirait en signe de
	 * remplacement.
	 */
	static characters(text) {
		return Array.from(text);
	}

	/**
	 * Une ligne telle qu'elle s'écrit : entière si elle tient, sinon son plus long début qui tient une fois suivi de « … ».
	 * L'espace qui précèderait la coupe tombe, et une ligne dont rien ne tient se réduit au signe.
	 */
	static shorten(text, fits) {

		if(fits(text)) {
			return text;
		}

		const characters = MapSceneBoards.characters(text);

		for(let length = characters.length - 1; length > 0; length--) {

			const short = characters.slice(0, length).join('').trimEnd() +'…';

			if(fits(short)) {
				return short;
			}

		}

		return '…';

	}

	// La distance d'un point au contour
	static distance(p, points) {

		const G = ScenePlanGeometry;

		return Math.min(...points.map((a, i) => {
			const side = G.sub(points[(i + 1) % points.length], a);
			const k = Math.max(0, Math.min(1, G.dot(G.sub(p, a), side) / (G.dot(side, side) || 1)));
			return G.length(G.sub(p, G.add(a, G.mul(side, k))));
		}));

	}

	// Les côtés d'un contour, leur normale vers le dehors
	static sides(points) {

		const G = ScenePlanGeometry;
		const direct = (points.reduce((sum, q, i) => sum + q.x * points[(i + 1) % points.length].n - points[(i + 1) % points.length].x * q.n, 0) > 0) ? 1 : -1;

		return points.map((a, i) => {
			const b = points[(i + 1) % points.length];
			const along = G.norm(G.sub(b, a));
			return { a, b, out: { x: along.n * direct, n: -along.x * direct }, length: G.length(G.sub(b, a)) };
		}).filter(side => side.length > .5);

	}

	// L'emprise d'une pancarte tournée de `turn`, dans la forme d'une planche
	static rectAt(p, turn, size, grow = 0) {

		const G = ScenePlanGeometry;
		const w = { x: Math.cos(turn), n: Math.sin(turn) };
		const u = { x: Math.sin(turn), n: -Math.cos(turn) };
		const width = size.width + 2 * grow;
		const length = size.depth + 2 * grow;

		return { origin: G.sub(p, G.add(G.mul(w, width / 2), G.mul(u, length / 2))), w, u, width, length };

	}

	static corners(rect) {

		const G = ScenePlanGeometry;
		const across = G.mul(rect.w, rect.width);
		const along = G.mul(rect.u, rect.length);

		return [rect.origin, G.add(rect.origin, across), G.add(G.add(rect.origin, across), along), G.add(rect.origin, along)];

	}

	static center(rect) {
		return ScenePlanGeometry.add(rect.origin, ScenePlanGeometry.add(ScenePlanGeometry.mul(rect.u, rect.length / 2), ScenePlanGeometry.mul(rect.w, rect.width / 2)));
	}

	// Deux emprises sont séparées s'il existe un de leurs axes sur lequel leurs projections ne se recouvrent pas
	static apart(a, b) {

		const G = ScenePlanGeometry;

		return [a.u, a.w, b.u, b.w].some(axis => {
			const pa = MapSceneBoards.corners(a).map(q => G.dot(q, axis));
			const pb = MapSceneBoards.corners(b).map(q => G.dot(q, axis));
			return Math.max(...pa) <= Math.min(...pb) || Math.max(...pb) <= Math.min(...pa);
		});

	}

	frame(camera, controls) {

		const yaw = Math.atan2(camera.position.x - controls.target.x, camera.position.z - controls.target.z);
		const scale = Math.max(1, Math.min(MapSceneBoards.SCALE_MAX, MapScene.FRUSTUM / camera.zoom / MapSceneBoards.GROWS_FROM));

		// Tournée vers la caméra et grandie avec le recul, une pancarte se pose au plus bas du sol sous ses deux poteaux : posée sous son
		// milieu, sur une pente, l'un d'eux laisserait voir le jour. Ni l'angle ni la taille ne changent d'une image à l'autre au repos
		const moved = (this.ground.relief !== null && (yaw !== this.yaw || scale !== this.scale));

		this.yaw = yaw;
		this.scale = scale;

		for(const { board, base } of this.boards) {

			board.rotation.y = yaw;
			board.scale.setScalar(scale);

			if(moved) {
				const post = { x: Math.cos(yaw) * MapSceneBoards.POST * scale, n: Math.sin(yaw) * MapSceneBoards.POST * scale };
				board.position.y = Math.min(this.ground.height(base.x + post.x, base.n + post.n), this.ground.height(base.x - post.x, base.n - post.n));
			}

		}

	}

	dispose() {

		this.boards.forEach(({ board }) => this.parent.remove(board));
		this.geometry.dispose();
		this.wood.dispose();
		this.faces.forEach(face => {
			face.map.dispose();
			face.dispose();
		});

	}

}

/**
 * L'enseigne au nom de la structure, en lettres de SIZE mètres plantées chacune sur ses piquets, aucune tout à fait d'aplomb. Elle
 * se pose une fois, après le premier cadrage, dans la première bande libre derrière les planches qu'il montre, dans son axe et
 * tournée vers la caméra : là où elle ne cache ni planche, ni parcelle cultivée en aplat, ni chemin, ni bâtiment, ni pancarte, sur
 * la longueur de terrain que sa hauteur masque à cette caméra, et où rien de haut devant elle ne la cache.
 */
class MapSceneSign {

	static SIZE = 6;
	static LENGTH_MAX = 100;
	static CLEAR = 6;
	static PLATE = '#f6f3ea';
	static POST = '#6f7a80';

	// Le contour des lettres, sombre et d'épaisseur égale tout autour de la lettre : sur une terre claire, la plaque crème se
	// perdait et son ombre ressortait plus qu'elle. Il ne porte pas d'ombre, celle de la plaque reste la seule
	static OUTLINE = '#3d3a33';
	static OUTLINE_WIDTH = .14;

	// Les contours des barres se chevauchent, et le flanc de l'un dépasse au-dessus de la barre voisine dès que la vue tourne.
	// Le contour se dessine donc en premier sans écrire sa profondeur, puis les plaques le recouvrent partout où elles se voient,
	// et il ne reste que le tour de la lettre entière. La même boite écrit ensuite sa profondeur sans couleur : un objet dessiné
	// plus tard derrière l'enseigne, opaque ou transparent, ne recouvre pas le contour. Aucun opaque ne doit se dessiner entre le
	// contour et sa profondeur, où le sol l'effacerait : les trois passes se suivent donc avant l'ordre 0 que three donne par
	// défaut, celui du reste de la scène
	static OUTLINE_ORDER = -3;
	static PLATE_ORDER = -2;
	static OUTLINE_DEPTH_ORDER = -1;

	// Dessinée pour une lettre de 6 de haut : largeur et hauteur d'une lettre, épaisseur du trait, de la plaque, pas d'une lettre à
	// la suivante, garde au sol
	static W = 4;
	static H = 6;
	static S = .9;
	static DEPTH = .4;
	static STEP = 5.1;
	static FOOT = 1.6;

	constructor({ THREE, addons, plan, geometry, camera, controls, opening, boards, decor, parent, gradientMap }) {

		const G = ScenePlanGeometry;

		this.parent = parent;

		const characters = MapSceneSign.characters(plan.name);
		// Un nom long se lit en lettres plus petites, et non en une enseigne plus longue que la structure qu'elle nomme
		const size = Math.min(MapSceneSign.SIZE, MapSceneSign.LENGTH_MAX * MapSceneSign.H / (characters.length * MapSceneSign.STEP));
		const { plates, posts, outlines } = MapSceneSign.letters(THREE, characters, size);

		this.plate = new THREE.MeshToonMaterial({ color: MapSceneSign.PLATE, gradientMap });
		this.post = new THREE.MeshToonMaterial({ color: MapSceneSign.POST, gradientMap });
		this.outline = new THREE.MeshBasicMaterial({ color: MapSceneSign.OUTLINE, side: THREE.BackSide, depthWrite: false });
		this.outlineDepth = new THREE.MeshBasicMaterial({ side: THREE.BackSide, colorWrite: false });

		this.group = new THREE.Group();
		this.footprint = [];

		if(plates.length === 0) {
			return;
		}

		const plateGeometry = addons.mergeGeometries(plates);
		const postGeometry = addons.mergeGeometries(posts);
		const outlineGeometry = addons.mergeGeometries(outlines);
		plates.concat(posts, outlines).forEach(piece => piece.dispose());

		plateGeometry.computeBoundingBox();
		const box = plateGeometry.boundingBox;
		const half = Math.max(-box.min.x, box.max.x);
		const height = box.max.y;

		const offset = camera.position.clone().sub(controls.target);
		const back = offset.clone().setY(0).normalize();
		const away = { x: -back.x, n: back.z };
		const across = { x: back.z, n: back.x };
		const eye = { x: controls.target.x, n: -controls.target.z };

		// La longueur de terrain qu'un mètre de hauteur cache à cette caméra
		const shade = Math.hypot(offset.x, offset.z) / offset.y;
		const reach = height * shade;

		const sampled = (points, closed) => points.flatMap((p, i) => {

			if(closed === false && i === points.length - 1) {
				return [p];
			}

			const q = points[(i + 1) % points.length];
			const steps = Math.max(1, Math.ceil(G.length(G.sub(q, p)) / 3));

			return Array.from({ length: steps }, (_, k) => G.lerp(p, q, k / steps));

		});

		const bedPoints = geometry.beds.flatMap(bed => sampled(geometry.corners(bed), true));

		// Une parcelle cultivée en aplat, sans planche, compte pour son tour et un point tous les 3 m dedans, si une série y est placée
		const plotsWithBeds = new Set(geometry.beds.map(bed => bed.plot));
		const bedsPlaced = new Set(plan.places.map(place => place.bed));
		const planted = new Set(plan.beds.filter(bed => bedsPlaced.has(bed.id)).map(bed => bed.plot));

		const flatPoints = [...geometry.plots.values()].filter(({ plot, drawn }) => drawn && plotsWithBeds.has(plot.id) === false && planted.has(plot.id)).flatMap(({ points }) => {

			const xs = points.map(p => p.x);
			const ns = points.map(p => p.n);
			const grid = [];

			for(let x = Math.min(...xs); x <= Math.max(...xs); x += 3) {
				for(let n = Math.min(...ns); n <= Math.max(...ns); n += 3) {
					if(G.inside({ x, n }, points)) {
						grid.push({ x, n });
					}
				}
			}

			return [...sampled(points, true), ...grid];

		});

		// room : l'emprise autour du point. hide : la longueur de terrain qu'il cache derrière lui. shown : l'enseigne ne doit pas le
		// cacher. Un arbre ou une haie écarte d'une fois et demie sa taille, ce qui laisse son houppier mordre le pied des lettres
		const kept = [
			...bedPoints.map(p => ({ p, room: 0, hide: 0, shown: true })),
			...flatPoints.map(p => ({ p, room: 0, hide: 0, shown: true })),
			...boards.boards.map(({ base }) => ({ p: base, room: MapSceneBoards.BOARD.width * MapSceneBoards.SCALE_MAX / 2, hide: 0, shown: true })),
			...decor.elements.flatMap(e => MapSceneSign.obstacles(e, shade, sampled))
		];

		const along = p => G.dot(G.sub(p, eye), away);
		const aside = p => G.dot(G.sub(p, eye), across);

		// Les distances interdites, une plage par point du couloir
		const closed = kept.filter(({ p, room, shown }) => Math.abs(aside(p)) < half + room + (shown ? MapSceneSign.CLEAR : 0))
			.map(({ p, room, hide, shown }) => shown ? [along(p) - reach - room - MapSceneSign.CLEAR, along(p) + room + Math.max(hide, MapSceneSign.CLEAR)] : [along(p) - room, along(p) + room + hide])
			.sort((a, b) => a[0] - b[0]);

		const xs = opening.map(p => p.x);
		const ns = opening.map(p => p.n);
		const framed = bedPoints.filter(p => p.x >= Math.min(...xs) && p.x <= Math.max(...xs) && p.n >= Math.min(...ns) && p.n <= Math.max(...ns));

		let far = Math.max(0, ...framed.map(along));

		for(const [from, to] of closed) {

			if(from > far) {
				break;
			}

			far = Math.max(far, to);

		}

		const at = G.add(eye, G.mul(away, far));

		// Ce que l'enseigne couvre à l'écran, ramené au sol : son pied, et le sol que ses lettres cachent derrière elles
		this.footprint = [-half, half].flatMap(side => [0, reach].map(behind => G.add(at, G.add(G.mul(across, side), G.mul(away, behind)))));

		const plate = new THREE.Mesh(plateGeometry, this.plate);
		const post = new THREE.Mesh(postGeometry, this.post);
		const outline = new THREE.Mesh(outlineGeometry, this.outline);
		const outlineDepth = new THREE.Mesh(outlineGeometry, this.outlineDepth);
		plate.castShadow = true;
		post.castShadow = true;
		outline.renderOrder = MapSceneSign.OUTLINE_ORDER;
		plate.renderOrder = MapSceneSign.PLATE_ORDER;
		outlineDepth.renderOrder = MapSceneSign.OUTLINE_DEPTH_ORDER;

		this.group.add(plate, post, outline, outlineDepth);
		this.group.position.set(at.x, geometry.height(at.x, at.n), -at.n);
		this.group.rotation.y = Math.atan2(back.x, back.z);

		parent.add(this.group);

	}

	/**
	 * Ce qu'un élément du décor pose dans le couloir de l'enseigne : un bâtiment ou un point d'eau se voit et cache loin, un chemin
	 * se voit, un arbre ou une haie la laisse passer devant lui, le reste ne doit pas la cacher.
	 */
	static obstacles(e, shade, sampled) {

		const G = ScenePlanGeometry;
		const D = MapSceneDecor;

		// Ce qui passe sur les planches, un tuyau, une rampe ou une vanne, reste à plat sous l'enseigne, qui peut le couvrir
		if(e.def.overBeds === true) {
			return [];
		}

		// Une bande fleurie, à 30 cm du sol, se voit comme un chemin ou une route et ne cache rien
		if(D.WALKED.includes(e.kit) || e.kit === 'bande-fleurie') {
			return sampled(e.path.map(q => G.add(e.p, q)), false).map(p => ({ p, room: e.thick, hide: 0, shown: true }));
		}

		if(e.kit === 'haie') {
			return sampled(e.path.map(q => G.add(e.p, q)), false).map(p => ({ p, room: e.thick, hide: e.thick * 1.5, shown: false }));
		}

		if(e.def.family === 'tree') {
			return e.feet.map(foot => {
				const size = 2 * foot.half * e.size;
				return { p: MapSceneBoards.center(D.square(e, foot)), room: size * .6, hide: size * 1.5, shown: false };
			});
		}

		const rectangles = D.footprint(e).pieces.map(piece => piece.rectangle);

		if(e.def.family === 'building' || e.def.family === 'water') {
			return rectangles.flatMap(rect => sampled(D.corners(rect), true)).map(p => ({ p, room: 2, hide: 8 * shade, shown: true }));
		}

		return rectangles.flatMap(rect => D.corners(rect)).map(p => ({ p, room: 4, hide: 6 * shade, shown: false }));

	}

	/**
	 * Le nom en signes de l'enseigne : chaque capitale suivie des diacritiques qu'elle porte, une ligature en ses deux lettres, une
	 * apostrophe ou un guillemet typographique en sa forme droite.
	 */
	static characters(name) {

		const text = name.replace(/[\u2018\u2019]/g, "'").replace(/[\u201c\u201d]/g, '"').toUpperCase().replace(/\u0152/g, 'OE').normalize('NFD');
		const characters = [];

		for(const character of text) {

			if(/[\u0300-\u036f]/.test(character) === false) {
				characters.push(character);
			} else if(characters.length > 0) {
				characters[characters.length - 1] += character;
			}

		}

		return characters;

	}

	/**
	 * Les plaques, leurs contours et les piquets du nom, face vers +Z, sol à y = 0, centrés sur sa longueur. Un accent sans dessin
	 * tombe ; un signe sans dessin se lit comme une espace et garde son rang.
	 */
	static letters(THREE, characters, size) {

		const { W, H, S, DEPTH, STEP, FOOT } = MapSceneSign;
		const A = S / 2, B = W - S / 2, C = W / 2, LOW = S / 2, HIGH = H - S / 2, MID = H / 2;

		const LEFT = [A, 0, A, H], RIGHT = [B, 0, B, H], TOP = [S, HIGH, W - S, HIGH], BOTTOM = [S, LOW, W - S, LOW], BAR = [S, MID, W - S, MID];
		const O = [LEFT, RIGHT, BOTTOM, TOP];
		const FIVE = [[0, HIGH, W, HIGH], [A, MID, A, H - S], BAR, [B, S, B, MID], [0, LOW, W, LOW]];

		// Les barres d'une lettre, d'axe en axe : [x0, y0, x1, y1]
		const LETTERS = {
			A: [[A, 0, C, H], [B, 0, C, H], [1.2, 2, W - 1.2, 2]],
			B: [LEFT, [S, HIGH, W - 1.2, HIGH], [S, MID, W - S, MID], [S, LOW, W - S, LOW], [B - .3, MID + A, B - .3, H - .5], [B, .5, B, MID - A]],
			C: [LEFT, [S, HIGH, W, HIGH], [S, LOW, W, LOW]],
			// La panse est une pointe, deux pans qui se rejoignent à mi-hauteur : à angles droits le D se lit comme un O
			D: [LEFT, [S, HIGH, 2, HIGH], [S, LOW, 2, LOW], [1.9, HIGH, B, MID + .2], [1.9, LOW, B, MID - .2]],
			E: [LEFT, [S, HIGH, W, HIGH], [S, MID, W - 1, MID], [S, LOW, W, LOW]],
			F: [LEFT, [S, HIGH, W, HIGH], [S, MID, W - 1, MID]],
			G: [LEFT, [S, HIGH, W, HIGH], BOTTOM, [B, 0, B, MID], [2, MID, W - S, MID]],
			H: [LEFT, RIGHT, BAR],
			I: [[C, 0, C, H], [1, HIGH, 3, HIGH], [1, LOW, 3, LOW]],
			J: [RIGHT, BOTTOM, [A, 0, A, 2.2]],
			K: [LEFT, [S, MID, B, H], [1.5, MID, B, 0]],
			L: [LEFT, [S, LOW, W, LOW]],
			M: [LEFT, RIGHT, [A, H - .2, C, 2.4], [B, H - .2, C, 2.4]],
			N: [LEFT, RIGHT, [A, H - .3, B, .3]],
			O,
			P: [LEFT, TOP, BAR, [B, MID - A, B, H]],
			Q: [...O, [2.3, 1.7, W, -.2]],
			R: [LEFT, TOP, BAR, [B, MID - A, B, H], [1.7, MID, B, 0]],
			S: FIVE,
			T: [[0, HIGH, W, HIGH], [C, 0, C, H - S]],
			U: [LEFT, RIGHT, BOTTOM],
			V: [[A, H, C, .3], [B, H, C, .3]],
			W: [LEFT, RIGHT, [A, .2, C, 3.6], [B, .2, C, 3.6]],
			X: [[A, 0, B, H], [A, H, B, 0]],
			Y: [[A, H, C, MID], [B, H, C, MID], [C, 0, C, MID]],
			Z: [[0, HIGH, W, HIGH], [0, LOW, W, LOW], [B, H - S, A, S]],
			0: O,
			1: [[C, 0, C, H], [1, 4.5, C, H - .2]],
			2: [[0, HIGH, W, HIGH], [B, MID, B, H - S], BAR, [A, S, A, MID], [0, LOW, W, LOW]],
			3: [RIGHT, [0, HIGH, W - S, HIGH], [1, MID, W - S, MID], [0, LOW, W - S, LOW]],
			4: [RIGHT, [A, MID, A, H], BAR],
			5: FIVE,
			6: [LEFT, [S, HIGH, W, HIGH], BAR, BOTTOM, [B, 0, B, MID]],
			7: [[0, HIGH, W, HIGH], [B, H - S, 1.5, 0]],
			8: [...O, BAR],
			9: [RIGHT, TOP, BAR, [A, MID, A, H], [0, LOW, W - S, LOW]],
			'-': [[.8, MID, W - .8, MID]],
			"'": [[C, 4.3, C, H]],
			'"': [[1.3, 4.3, 1.3, H], [2.7, 4.3, 2.7, H]],
			'.': [[C, 0, C, S]],
			',': [[C, 0, C, S], [C + .3, .1, C - .5, -.8]],
			'\u00b7': [[C, MID - A, C, MID + A]],
			'!': [[C, 1.8, C, H], [C, 0, C, S]],
			'_': [[0, LOW, W, LOW]],
			'/': [[A, 0, B, H]],
			'(': [[2.9, H - .2, 1.3, 4.2], [1.3, 4.4, 1.3, 1.6], [1.3, 1.8, 2.9, .2]],
			')': [[1.1, H - .2, 2.7, 4.2], [2.7, 4.4, 2.7, 1.6], [2.7, 1.8, 1.1, .2]],
			'&': [[1.2, 3.7, 1.2, H], [1.2, HIGH, 2.9, HIGH], [2.9, H, 2.9, 4.5], [2.9, 4.5, A, 2], [A, 2.2, A, 0], [S, LOW, 2.3, LOW], [1.2, 3.7, B, 0], [2.3, LOW, B, 2.2]]
		};

		// Les accents, de petites barres au-dessus de la lettre, au large de son trait du haut, et la cédille sous elle, dans la garde
		// au sol que laisse le plus bas des panneaux
		const MARKS = {
			'\u0300': [[1.3, 8.1, 2.5, 6.9]],
			'\u0301': [[1.5, 6.9, 2.7, 8.1]],
			'\u0302': [[.9, 6.9, C, 8.1], [W - .9, 6.9, C, 8.1]],
			'\u0308': [[1.1, 7, 1.1, 7.9], [W - 1.1, 7, W - 1.1, 7.9]],
			'\u0327': [[2.7, .2, 2.7, -.35], [2.7, -.35, 1.5, -.6]]
		};

		// Où la lettre touche ses piquets : [x, hauteur du contact]. Sans mention, un piquet au milieu, sous la barre du bas
		const TWO = [[A, 0], [B, 0]], ONE = [[A, 0]];
		const FEET = {
			A: TWO, H: TWO, M: TWO, N: TWO, R: TWO, W: TWO, X: TWO, K: TWO, F: ONE, P: ONE, 4: [[B, 0]], 7: [[1.5, 0]],
			'-': [[C, MID - S / 2]], "'": [[C, 4.3]], '"': [[1.3, 4.3], [2.7, 4.3]], '\u00b7': [[C, MID - A]],
			'/': [[.8, .4]], '(': [[2.6, .4]], ')': [[1.4, .4]], '&': [[1.2, 0]]
		};

		// Un piquet : trois pans et un dessus, sans fond puisqu'il est planté
		const post = height => {

			const g = new THREE.CylinderGeometry(.24, .24, height, 3, 1, true).toNonIndexed();
			const position = Array.from(g.attributes.position.array);
			g.dispose();

			for(const k of [0, 2, 1]) {
				position.push(Math.sin(k * Math.PI * 2 / 3) * .24, height / 2, Math.cos(k * Math.PI * 2 / 3) * .24);
			}

			return new THREE.BufferGeometry().setAttribute('position', new THREE.Float32BufferAttribute(position, 3));

		};

		// Les normales par face : chaque pan d'une plaque reçoit une lumière uniforme, comme un panneau taillé
		const facets = g => {
			const out = (g.index !== null) ? g.toNonIndexed() : g;
			out.deleteAttribute('normal');
			out.deleteAttribute('uv');
			out.computeVertexNormals();
			return out;
		};

		const K = size / H;
		const T = MapSceneSign.OUTLINE_WIDTH / K;
		const plates = [];
		const posts = [];
		const outlines = [];

		characters.forEach((character, i) => {

			const [letter, ...marks] = [...character];

			if(LETTERS[letter] === undefined) {
				return;
			}

			const bars = [...LETTERS[letter], ...marks.flatMap(mark => MARKS[mark] ?? [])];

			// Chaque panneau a son écart : un peu plus haut, un peu tourné, un peu penché
			const lift = FOOT + Math.sin(i * 2.3) * .45;
			const yaw = Math.sin(i * 1.3 + 1) * .09;
			const lean = Math.sin(i * 3.1) * .035;
			const x = (i - (characters.length - 1) / 2) * STEP;
			const place = g => facets(g.translate(-C, 0, 0).rotateZ(lean).rotateY(yaw).translate(x, lift, 0).scale(K, K, K));

			// Le contour d'une barre est une boite plus grande d'un trait, vue par l'intérieur : seul son bord dépasse de la plaque. Elle
			// ne s'avance pas devant la face, sans quoi son bord se verrait aussi autour du flanc de la plaque et épaissirait le contour
			// vu de biais
			for(const [x0, y0, x1, y1] of bars) {
				const length = Math.hypot(x1 - x0, y1 - y0);
				const bar = g => place(g.rotateZ(Math.atan2(y1 - y0, x1 - x0)).translate((x0 + x1) / 2, (y0 + y1) / 2, 0));
				plates.push(bar(new THREE.BoxGeometry(length, S, DEPTH)));
				outlines.push(bar(new THREE.BoxGeometry(length + 2 * T, S + 2 * T, DEPTH + T).translate(0, 0, -T / 2)));
			}

			// Les piquets, derrière la plaque : du sol au premier contact avec la lettre, où le trait les cache, jamais plus haut
			for(const [px, py] of FEET[letter] ?? [[C, 0]]) {
				const top = py + S * .45;
				const bottom = -lift - .2;
				posts.push(place(post(top - bottom).translate(px, (top + bottom) / 2, -DEPTH / 2 - .22)));
			}

		});

		return { plates, posts, outlines };

	}

	dispose() {

		this.parent.remove(this.group);
		this.plate.dispose();
		this.post.dispose();
		this.outline.dispose();
		this.outlineDepth.dispose();

	}

}

/**
 * Les papillons, au-dessus des planches en culture que la vue regarde. Ils ne portent pas d'ombre : la carte des ombres ne se refait
 * qu'au changement de jour, l'ombre resterait sur place. Un insecte a une forme qui se lit comme la sienne, ou ne se dessine pas :
 * jamais une sphère ni un disque.
 */
class MapSceneInsects {

	static BUTTERFLY_WEEKS = [14, 44];

	// Un papillon a sa taille naturelle, trois fois grossie au grossissement des paysans, tant qu'elle fait BUTTERFLY_PIXELS pixels
	// d'envergure au cadrage du moment. Quand la vue recule il grossit pour tenir ces pixels, sans dépasser BUTTERFLY_CAP mètres dans
	// la scène, où il couvre déjà une planche : le plafond passe avant les pixels. Sous BUTTERFLY_FAINT pixels à ce plafond, il ne se
	// dessine plus, plutôt que de devenir une tache sur un petit écran
	static BUTTERFLY_SCALE = 3;
	static BUTTERFLY_SPAN = .1;
	static BUTTERFLY_PIXELS = 12;
	static BUTTERFLY_FAINT = 5;
	static BUTTERFLY_CAP = .6;

	// Les pixels par mètre du cadrage du moment, pour un canevas haut de `height` pixels
	static perMetre(height, camera) {
		return height * camera.zoom / (2 * MapScene.FRUSTUM);
	}

	/**
	 * Le grossissement d'un sujet de `size` mètres au cadrage du moment : `natural`, le sien, relevé quand il le laisserait sous
	 * `pixels` pixels, sans le porter au-delà de `cap` mètres dans la scène, où il dépasserait ce qui l'entoure.
	 */
	static sized(size, natural, pixels, perMetre, cap) {
		return Math.min(cap / size, Math.max(natural, pixels / (size * perMetre)));
	}

	// Un sujet qui, même grossi, ferait moins de `pixels` pixels : il ne se dessine pas plutôt que de se réduire à un point
	static faint(size, scale, perMetre, pixels) {
		return size * scale * perMetre < pixels;
	}

	// Vu de haut, un papillon qui vole en travers de la vue présente par la tranche son aile levée côté caméra. Il reste donc tourné à
	// l'opposé de la caméra, à 8 à 18° de côté pour ne jamais s'aligner sur l'axe de la vue
	static AXIS_MIN = 8 * Math.PI / 180;
	static AXIS_MAX = 18 * Math.PI / 180;

	// La piéride du prototype (`poc/modeles/papillon.js`), tête vers +X, ventre à y = 0
	static WING = '#f4f1e4';
	static TIP = '#1d1c21';
	static BODY = '#4a4850';

	constructor({ THREE, addons, crops, geometry, scale, canvas, parent, gradientMap }) {

		this.THREE = THREE;
		this.crops = crops;
		this.geometry = geometry;
		this.parent = parent;
		this.scale = scale;
		this.canvas = canvas;
		// Le grossissement se règle à chaque changement de zoom ou de taille du canevas. Nul, il dit que le papillon ne se dessine pas
		this.butterflyScale = 0;
		this.zoom = null;
		this.random = MapSceneCrops.random(41);

		this.material = new THREE.MeshToonMaterial({ vertexColors: true, side: THREE.DoubleSide, gradientMap });

		this.pieces = MapSceneInsects.butterfly(THREE).map(({ name, pivot, geometries }) => {

			const geometry = addons.mergeGeometries(geometries).translate(-pivot.x, -pivot.y, -pivot.z);
			geometries.forEach(g => g.dispose());

			return { name, pivot, geometry };

		});

		this.count = 4 + Math.floor(this.random() * 4);

		for(const piece of this.pieces) {
			piece.mesh = new THREE.InstancedMesh(piece.geometry, this.material, this.count);
			piece.mesh.count = 0;
			// Les instances bougent à chaque image : une sphère englobante calculée une fois serait fausse, le tri se fait papillon par papillon
			piece.mesh.frustumCulled = false;
			parent.add(piece.mesh);
		}

		this.butterflies = Array.from({ length: this.count }, () => ({ seed: this.random() * 100, anchor: null, goal: null, away: 0, wander: 0, at: { x: 0, n: 0 }, y: 0, heading: 0, flap: 0, seen: false }));

		this.frustum = new THREE.Frustum();
		this.projection = new THREE.Matrix4();
		this.probe = new THREE.Vector3();
		this.sphere = new THREE.Sphere();
		this.matrix = new THREE.Matrix4();
		this.part = new THREE.Matrix4();
		this.pitch = new THREE.Matrix4();
		this.quaternion = new THREE.Quaternion();
		this.partQuaternion = new THREE.Quaternion();
		this.euler = new THREE.Euler();
		this.position = new THREE.Vector3();
		this.size = new THREE.Vector3();
		this.one = new THREE.Vector3(1, 1, 1);
		this.up = new THREE.Vector3(0, 1, 0);

		this.last = null;

	}

	/**
	 * Le papillon en trois pièces qui battent chacune autour de son pivot : le corps et les deux ailes, l'apex noir de chaque aile
	 * fondu avec elle. Ses couleurs sont dans les sommets.
	 */
	static butterfly(THREE) {

		const I = MapSceneInsects;

		// Toutes les pièces ne sont pas indexées : un tour l'est, un polyèdre de three.js ou une pièce faite de triangles ne l'est pas
		const colored = (g, color) => {
			let out = g;
			if(g.index !== null) {
				out = g.toNonIndexed();
				g.dispose();
			}
			if(out.attributes.normal === undefined) {
				out.computeVertexNormals();
			}
			out.deleteAttribute('uv');
			const c = new THREE.Color(color);
			out.setAttribute('color', new THREE.Float32BufferAttribute(Array.from({ length: out.attributes.position.count }, () => [c.r, c.g, c.b]).flat(), 3));
			return out;
		};

		const mesh = tris => {
			const g = new THREE.BufferGeometry();
			g.setAttribute('position', new THREE.Float32BufferAttribute(tris.flat(2), 3));
			g.computeVertexNormals();
			return g;
		};

		// Éventail fermé autour d'un point intérieur : le contour reste convexe vu de ce point
		const fan = (center, outline) => outline.map((p, i) => [center, p, outline[(i + 1) % outline.length]]);

		// Corps : fuseau à cinq pans, le thorax le plus épais, la tête et les antennes à massue
		const bodyY = .0032;
		const profile = [[-.0215, 0], [-.019, .0016], [-.006, .0023], [.001, .0028], [.0065, .0032], [.0105, .0022], [.0115, 0]];
		const body = [
			new THREE.LatheGeometry(profile.map(([x, r]) => new THREE.Vector2(r, x)), 5).rotateZ(-Math.PI / 2).translate(0, bodyY, 0),
			new THREE.IcosahedronGeometry(.0026, 0).translate(.0128, bodyY + .0004, 0)
		];

		for(const side of [-1, 1]) {

			const root = new THREE.Vector3(.0138, bodyY + .0018, side * .0012);
			const tip = new THREE.Vector3(.0235, bodyY + .0105, side * .0058);
			const direction = tip.clone().sub(root);
			const length = direction.length();

			body.push(new THREE.CylinderGeometry(.00032, .00032, length, 3, 1, true).translate(0, length / 2, 0)
				.applyQuaternion(new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), direction.normalize()))
				.translate(root.x, root.y, root.z));
			body.push(new THREE.OctahedronGeometry(.0009, 0).translate(tip.x, tip.y, tip.z));

		}

		// Ailes : contours dans le plan de l'aile, en (abscisse le long du corps, écart à l'axe), relevés d'un dièdre faible
		const fore = [[.0085, .002], [.0125, .016], [.0152, .031], [.0148, .043], [.0105, .0505], [.0035, .0495], [-.003, .0455], [-.0085, .038], [-.0105, .031], [-.0065, .016], [-.002, .003]];
		const foreCenter = [.003, .024];
		const apex = [[.0152, .031], [.0148, .043], [.0105, .0505], [.0035, .0495], [-.0025, .0458], [.002, .0415], [.0045, .0435], [.007, .038], [.0105, .0395], [.0115, .0335]];
		const apexCenter = [.0085, .0435];
		// Les deux points noirs de la femelle : sans eux le blanc se lit comme un pétale
		const spots = [[-.0005, .0315, .0032], [-.0045, .0205, .0026]];
		const hind = [[.0005, .003], [-.003, .019], [-.006, .0335], [-.0125, .0375], [-.0195, .0335], [-.0245, .0235], [-.0225, .011], [-.0155, .0035]];
		const hindCenter = [-.012, .02];

		const place = (side, dihedral, lift) => ([x, w]) => [
			x,
			bodyY + .0006 + w * Math.sin(dihedral) + lift * Math.cos(dihedral),
			side * (.0008 + w * Math.cos(dihedral) - lift * Math.sin(dihedral))
		];

		const wing = (side, foreAngle, hindAngle) => {
			const f = place(side, foreAngle, 0);
			const h = place(side, hindAngle, -.0002);
			const a = place(side, foreAngle, .0003);
			return [
				colored(mesh([...fan(f(foreCenter), fore.map(f)), ...fan(h(hindCenter), hind.map(h))]), I.WING),
				colored(mesh([...fan(a(apexCenter), apex.map(a)), ...spots.flatMap(([cx, cw, r], k) => fan(a([cx, cw]), Array.from({ length: 6 }, (_, i) => {
					const angle = i * Math.PI / 3 + k;
					return a([cx + r * Math.cos(angle), cw + r * .8 * Math.sin(angle)]);
				})))]), I.TIP)
			];
		};

		const pivot = new THREE.Vector3(.004, bodyY, 0);

		// Le V n'est pas parfait : la gauche un peu plus relevée que la droite
		return [
			{ name: 'corps', pivot: new THREE.Vector3(0, bodyY, 0), geometries: body.map(g => colored(g, I.BODY)) },
			{ name: 'aile-g', pivot, geometries: wing(-1, .29, .17) },
			{ name: 'aile-d', pivot, geometries: wing(1, .24, .13) }
		];

	}

	/**
	 * Les places en culture du jour montré, relevées par `MapSceneCrops.plant()`.
	 */
	blooms(date) {
		return [...this.crops.blooms.values()].filter(bloom => bloom.date === date);
	}

	/**
	 * Un point d'une planche en culture hors abri dans le cadre, tiré parmi les plus proches du centre de la vue. Une planche longue
	 * déborde du cadre de près : elle se sonde en cinq points le long de son axe.
	 */
	spot(blooms, controls) {

		const near = [];

		for(const bloom of blooms) {

			if(bloom.greenhouse || bloom.height < .05) {
				continue;
			}

			for(let i = 0; i < 5; i++) {

				const p = ScenePlanGeometry.lerp(bloom.a, bloom.b, .1 + .8 * i / 4);
				const top = bloom.top + this.geometry.height(p.x, p.n);

				if(this.frustum.containsPoint(this.probe.set(p.x, top, -p.n)) === false) {
					continue;
				}

				near.push([{ x: p.x, n: p.n, y: top }, (p.x - controls.target.x) ** 2 + (p.n + controls.target.z) ** 2]);

			}

		}

		if(near.length === 0) {
			return null;
		}

		near.sort((a, b) => a[1] - b[1]);

		return near[Math.floor(this.random() * Math.min(near.length, 24))][0];

	}

	heading(heading, camera, controls) {

		const away = Math.atan2(controls.target.x - camera.position.x, controls.target.z - camera.position.z);
		const deviation = Math.atan2(Math.sin(heading - away), Math.cos(heading - away));
		const side = Math.sign(deviation) || 1;

		return away + side * Math.min(MapSceneInsects.AXIS_MAX, Math.max(MapSceneInsects.AXIS_MIN, Math.abs(deviation)));

	}

	frame(time, date, week, camera, controls) {

		const seconds = time / 1000;
		const dt = (this.last === null) ? 0 : Math.min(.1, seconds - this.last);
		this.last = seconds;

		camera.updateMatrixWorld();
		this.frustum.setFromProjectionMatrix(this.projection.multiplyMatrices(camera.projectionMatrix, camera.matrixWorldInverse));

		const I = MapSceneInsects;

		if(this.zoom !== camera.zoom) {

			this.zoom = camera.zoom;

			const perMetre = I.perMetre(this.canvas.clientHeight, camera);
			const butterfly = I.sized(I.BUTTERFLY_SPAN, this.scale * I.BUTTERFLY_SCALE, I.BUTTERFLY_PIXELS, perMetre, I.BUTTERFLY_CAP);

			this.butterflyScale = I.faint(I.BUTTERFLY_SPAN, butterfly, perMetre, I.BUTTERFLY_FAINT) ? 0 : butterfly;

		}

		const blooms = this.blooms(date);

		this.flyButterflies(week >= MapSceneInsects.BUTTERFLY_WEEKS[0] && week <= MapSceneInsects.BUTTERFLY_WEEKS[1], blooms, dt, seconds, camera, controls);
		this.drawButterflies();

	}

	flyButterflies(season, blooms, dt, seconds, camera, controls) {

		const S = this.scale;
		let relocated = false;

		for(const b of this.butterflies) {

			b.visible = season;

			if(season === false) {
				b.anchor = null;
				continue;
			}

			b.away = b.seen ? 0 : b.away + dt;
			b.wander -= dt;

			// Une seule recherche de planche par image : elle parcourt toutes les planches en culture
			if(relocated === false && (b.anchor === null || b.away > 1.2 || b.wander <= 0)) {

				const spot = this.spot(blooms, controls);
				relocated = true;

				if(spot !== null) {
					if(b.anchor === null || b.away > 1.2) {
						b.anchor = { ...spot };
					}
					b.goal = spot;
					b.away = 0;
					b.wander = 5 + this.random() * 8;
				}

			}

			if(b.anchor === null) {
				b.visible = false;
				continue;
			}

			// Vers la planche visée, sans se presser
			const k = Math.min(1, dt * .4);
			b.anchor.x += (b.goal.x - b.anchor.x) * k;
			b.anchor.n += (b.goal.n - b.anchor.n) * k;
			b.anchor.y += (b.goal.y - b.anchor.y) * k;

			const t = seconds + b.seed;
			const at = {
				x: b.anchor.x + (Math.cos(t * .7) * 1.1 + Math.sin(t * 1.9) * .35) * S,
				n: b.anchor.n + (Math.sin(t * .9) * .9 + Math.cos(t * 2.3) * .3) * S
			};

			if(Math.hypot(at.x - b.at.x, at.n - b.at.n) > 1e-4) {
				b.heading = this.heading(Math.atan2(at.x - b.at.x, -(at.n - b.at.n)), camera, controls);
			}

			b.at = at;
			b.y = b.anchor.y + (.35 + .2 * Math.sin(t * 1.3) + .06 * Math.sin(t * 8.5)) * S;

			// Battement rapide et ample, les ailes restant levées vers le ciel : au-delà, vue de dos, l'aile qui penche vers la caméra
			// se lirait par la tranche
			b.flap = .1 + .52 * (.5 + .5 * Math.sin(t * 32));

		}

	}

	drawButterflies() {

		const scale = this.butterflyScale;
		let drawn = 0;

		for(const b of this.butterflies) {

			b.seen = false;

			if(b.visible === false || scale === 0) {
				continue;
			}

			this.sphere.center.set(b.at.x, b.y, -b.at.n);
			this.sphere.radius = scale * MapSceneInsects.BUTTERFLY_SPAN / 2;

			if(this.frustum.intersectsSphere(this.sphere) === false) {
				continue;
			}

			b.seen = true;

			this.quaternion.setFromAxisAngle(this.up, b.heading - Math.PI / 2);
			this.matrix.compose(this.position.set(b.at.x, b.y, -b.at.n), this.quaternion, this.size.setScalar(scale));
			this.matrix.multiply(this.pitch.makeRotationZ(.15));

			for(const piece of this.pieces) {

				const flap = { corps: 0, 'aile-g': b.flap, 'aile-d': -b.flap }[piece.name];

				this.partQuaternion.setFromEuler(this.euler.set(flap, 0, 0));
				this.part.compose(piece.pivot, this.partQuaternion, this.one).premultiply(this.matrix);
				piece.mesh.setMatrixAt(drawn, this.part);

			}

			drawn++;

		}

		for(const piece of this.pieces) {
			piece.mesh.count = drawn;
			piece.mesh.instanceMatrix.needsUpdate = true;
		}

	}

	// Les pixels par mètre tiennent à la hauteur du canevas autant qu'au zoom : les tailles se relisent à l'image suivante
	resize() {
		this.zoom = null;
	}

	dispose() {

		for(const piece of this.pieces) {
			this.parent.remove(piece.mesh);
			piece.geometry.dispose();
			piece.mesh.dispose();
		}

		this.material.dispose();

	}

}

/**
 * Deux clins d'œil : la sieste sous un arbre entre midi et deux, la brouette qui déborde.
 * Aucun ne lit ni ne change une donnée, et aucun ne touche au tirage des paysans : la journée se joue de la même façon avec ou
 * sans eux. Ils ne se jouent pas pendant la lecture.
 */
class MapSceneAntics {

	// La scène n'a pas d'heure : la sieste suit l'horloge de qui regarde
	static NAP_FROM = 12;
	static NAP_UNTIL = 14;
	static NAP_WEEKS = [18, 40];
	static NAP_TRUNK = .9;
	static NAP_ROOM = .6;
	static NAP_LENGTH = 1.7;

	static SPILL_MAX = 24;
	static SPILL_REACH = .9;
	static SPILL_GRAVITY = 9;
	static SPILL_COLORS = ['#f08a1c', '#d8281e', '#f4d23a'];

	constructor({ THREE, addons, socle, crew, decor, geometry, parent, gradientMap }) {

		this.THREE = THREE;
		this.crew = crew;
		this.decor = decor;
		this.geometry = geometry;
		this.parent = parent;
		this.random = MapSceneCrops.random(20261010);

		this.object = new THREE.Object3D();
		this.color = new THREE.Color();

		// La sieste : une figure à part, quelqu'un de l'équipe qui ne travaille pas, sur le dos, les pieds vers le large
		this.nap = null;
		this.napping = false;
		this.spot = null;

		// Un corps de l'équipe sans outil dans son modèle, l'un des deux qui portent la caisse : couché, une houe se dresserait du sol
		if(crew.farmers.length > 0) {
			this.nap = this.crew.figure.assemble(socle.baked, 'paysan-e', crew.material).root;
			this.nap.rotation.order = 'YXZ';
			this.nap.scale.setScalar(crew.scale);
			this.nap.visible = false;
			parent.add(this.nap);
		}

		const bar = (length, x, y, turn) => new THREE.BoxGeometry(length, .07, .05).rotateZ(turn).translate(x, y, 0);
		this.letters = new THREE.InstancedMesh(addons.mergeGeometries([bar(.3, 0, .15, 0), bar(.42, 0, 0, Math.atan2(.3, .3)), bar(.3, 0, -.15, 0)]), new THREE.MeshBasicMaterial({ color: '#f6f3ea' }), 3);
		this.letters.frustumCulled = false;
		this.letters.visible = false;
		parent.add(this.letters);

		// La brouette qui déborde : à un cahot, deux ou trois légumes sautent et restent au sol. Leurs couleurs sont celles de
		// légumes, pas celles du plan : une couleur de série est souvent un vert, qui se perd sur l'herbe et les rangs
		this.spills = [];
		this.spillDate = null;
		this.spillMesh = new THREE.InstancedMesh(new THREE.IcosahedronGeometry(.12, 0), new THREE.MeshToonMaterial({ gradientMap }), MapSceneAntics.SPILL_MAX);
		this.spillMesh.frustumCulled = false;
		this.spillMesh.count = 0;
		parent.add(this.spillMesh);

		this.jolts = new Map();

		this.last = null;

	}

	/**
	 * Les arbres du décor posé, pied par pied, et le long de chaque haie un point tous les quelques mètres.
	 */
	trees() {

		const G = ScenePlanGeometry;

		// Une bande fleurie n'a ni pied ni ombre où dormir
		return this.decor.elements.filter(e => e.def.family === 'tree' && e.kit !== 'bande-fleurie').flatMap(e => {

			if(e.kit === 'haie') {
				return e.path.map(q => ({ p: G.add(e.p, q), radius: e.thick / 2, hedge: true }));
			}

			return e.feet.map(foot => ({ p: MapSceneBoards.center(MapSceneDecor.square(e, foot)), radius: foot.half * e.size, hedge: false }));

		});

	}

	/**
	 * La place de la sieste : au pied d'un arbre, du côté de la caméra d'abord, en travers de la vue ; un arbre d'abord, le plus
	 * proche de ce qu'on regarde, et sans arbre libre le pied d'une haie. Ni sur une planche, ni sous un abri, ni dans un élément
	 * du décor autre qu'un arbre.
	 */
	napSpot(camera, controls) {

		const G = ScenePlanGeometry;
		const length = MapSceneAntics.NAP_LENGTH * this.crew.scale;
		const eye = { x: controls.target.x, n: -controls.target.z };
		const toCamera = camera.position.clone().sub(controls.target).setY(0).normalize();
		const front = Math.atan2(-toCamera.z, toCamera.x);

		const inside = (p, r, margin) => {
			const d = G.sub(p, r.origin);
			const along = G.dot(d, r.u);
			const across = G.dot(d, r.w);
			return along > -margin && along < r.length + margin && across > -margin && across < r.width + margin;
		};

		const blocks = [
			...this.geometry.beds,
			...[...this.geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ rectangle }) => rectangle),
			...this.decor.elements.filter(e => e.def.family !== 'tree').flatMap(e => MapSceneDecor.footprint(e).pieces.map(piece => piece.rectangle))
		];

		const clear = p => blocks.some(r => inside(p, r, MapSceneAntics.NAP_ROOM)) === false;

		const trees = this.trees();
		const nearest = list => list.sort((a, b) => G.length(G.sub(a.p, eye)) - G.length(G.sub(b.p, eye)));

		for(const tree of [...nearest(trees.filter(tree => tree.hedge === false)), ...nearest(trees.filter(tree => tree.hedge))]) {
			for(const turn of [0, .6, -.6, 1.2, -1.2, 1.8, -1.8]) {

				const out = { x: Math.cos(front + turn), n: Math.sin(front + turn) };
				const dir = { x: -out.n, n: out.x };
				const feet = G.add(G.add(tree.p, G.mul(out, Math.max(MapSceneAntics.NAP_TRUNK, tree.radius * (tree.hedge ? 1.2 : .7)) + .4)), G.mul(dir, length / 2));

				if([0, .5, 1].every(k => clear(G.sub(feet, G.mul(dir, length * k))))) {
					return { feet, dir };
				}

			}
		}

		return null;

	}

	frame(time, date, week, playing, camera, controls) {

		const seconds = time / 1000;
		const dt = (this.last === null) ? 0 : Math.min(.1, seconds - this.last);
		this.last = seconds;

		this.sleep(seconds, week, playing, camera, controls);
		this.spill(seconds, dt, date, playing);

	}

	sleep(seconds, week, playing, camera, controls) {

		if(this.nap === null) {
			return;
		}

		const hour = new Date().getHours();
		const time = (hour >= MapSceneAntics.NAP_FROM && hour < MapSceneAntics.NAP_UNTIL && week >= MapSceneAntics.NAP_WEEKS[0] && week <= MapSceneAntics.NAP_WEEKS[1] && playing === false && this.crew.farmers.some(farmer => farmer.on));

		if(time && this.napping === false) {
			this.spot = this.napSpot(camera, controls);
		}

		this.napping = (time && this.spot !== null);
		this.nap.visible = this.napping;
		this.letters.visible = this.napping;

		if(this.napping === false) {
			return;
		}

		const G = ScenePlanGeometry;
		const scale = this.crew.scale;
		const { feet, dir } = this.spot;
		const head = G.sub(feet, G.mul(dir, MapSceneAntics.NAP_LENGTH * scale * .95));

		this.nap.rotation.set(-Math.PI / 2, Math.atan2(dir.x, -dir.n), 0);
		// Le ventre se soulève à peine : il respire
		this.nap.position.set(feet.x, this.geometry.height(feet.x, feet.n) + (.2 + Math.sin(seconds * 1.6) * .012) * scale, -feet.n);

		const pillow = this.geometry.height(head.x, head.n);

		for(let i = 0; i < 3; i++) {

			const k = (seconds * .3 + i / 3) % 1;

			this.object.position.set(head.x + Math.sin(k * 5 + i) * .25 + k * .5, pillow + (.7 + k * 2.2) * scale, -head.n);
			this.object.quaternion.copy(camera.quaternion);
			this.object.scale.setScalar((.6 + k * 1.2) * Math.sin(Math.PI * k) * scale);
			this.object.updateMatrix();
			this.letters.setMatrixAt(i, this.object.matrix);

		}

		this.letters.instanceMatrix.needsUpdate = true;

	}

	spill(seconds, dt, date, playing) {

		const G = ScenePlanGeometry;
		const A = MapSceneAntics;
		const scale = this.crew.scale;
		const farmers = this.crew.farmers;

		// Ce qui reste au sol part avec la journée
		if(date !== this.spillDate) {
			this.spills = [];
			this.spillDate = date;
		}

		for(const farmer of farmers) {

			// L'équipe ne repose que qui travaille et se voit : sur les autres, le tressaut s'ajouterait d'une image à l'autre
			if(farmer.barrow === null || farmer.on === false || farmer.root.visible === false) {
				continue;
			}

			const jolt = this.jolts.get(farmer) ?? { at: -10, next: 4 + this.random() * 8 };
			this.jolts.set(farmer, jolt);

			// La brouette tressaute au cahot, par-dessus la pose que l'équipe vient de lui donner
			const since = seconds - jolt.at;
			farmer.barrow.position.y += (since < .35) ? Math.sin(since / .35 * Math.PI) * .14 : 0;
			farmer.barrow.rotation.z = (since < .6) ? Math.sin(since * 22) * .12 * (1 - since / .6) : 0;

			if(farmer.pose !== 'walk' || playing || SceneCrew.turnGap(farmer.heading, farmer.facing) > .2) {
				continue;
			}

			jolt.next -= dt;

			if(jolt.next > 0) {
				continue;
			}

			jolt.next = 9 + this.random() * 14;
			jolt.at = seconds;

			const ahead = { x: Math.sin(farmer.facing), n: -Math.cos(farmer.facing) };
			const right = { x: -ahead.n, n: ahead.x };
			const from = G.add(farmer.at, G.mul(ahead, (SceneCrew.BARROW_AHEAD + .15) * scale));

			for(let i = 0, count = 2 + Math.floor(this.random() * 2); i < count; i++) {

				const side = (this.random() < .5) ? -1 : 1;

				if(this.spills.length === A.SPILL_MAX) {
					this.spills.shift();
				}

				this.spills.push({
					p: from, y: .55 * scale, rise: 1.8 + this.random(),
					drift: G.add(G.mul(right, side * (.8 + this.random() * .8)), G.mul(ahead, this.random() - .5)),
					color: A.SPILL_COLORS[Math.floor(this.random() * A.SPILL_COLORS.length)],
					size: (.9 + this.random() * .5) * scale, spin: this.random() * 6, bounced: false, picked: null
				});

			}

			this.crew.puff(from, seconds);

		}

		this.spills = this.spills.filter(item => item.picked === null || seconds - item.picked < .3);

		this.spills.forEach((item, i) => {

			const floor = .11 * item.size;

			if(item.y > floor || item.rise > 0) {

				item.p = G.add(item.p, G.mul(item.drift, dt));
				item.rise -= A.SPILL_GRAVITY * dt;
				item.y += item.rise * dt;
				item.spin += dt * 9;

				// Un seul rebond, puis il reste où il est tombé
				if(item.y <= floor) {
					item.y = floor;
					if(item.bounced) {
						item.rise = 0;
						item.drift = { x: 0, n: 0 };
					} else {
						item.bounced = true;
						item.rise = -item.rise * .35;
						item.drift = G.mul(item.drift, .4);
					}
				}

			} else if(item.picked === null && farmers.some(farmer => farmer.on && farmer.barrow === null && farmer.pose === 'walk' && G.length(G.sub(farmer.at, item.p)) < A.SPILL_REACH)) {
				// Le premier qui passe à pied les ramasse
				item.picked = seconds;
			}

			// Sa hauteur se compte au-dessus du sol qu'il survole
			this.object.position.set(item.p.x, this.geometry.height(item.p.x, item.p.n) + item.y, -item.p.n);
			this.object.rotation.set(item.spin, item.spin * .7, 0);
			this.object.scale.setScalar(item.size * ((item.picked === null) ? 1 : Math.max(0, 1 - (seconds - item.picked) / .3)));
			this.object.updateMatrix();

			this.spillMesh.setMatrixAt(i, this.object.matrix);
			this.spillMesh.setColorAt(i, this.color.set(item.color));

		});

		if(this.spills.length > 0 || this.spillMesh.count > 0) {

			this.spillMesh.count = this.spills.length;
			this.spillMesh.instanceMatrix.needsUpdate = true;

			if(this.spillMesh.instanceColor !== null) {
				this.spillMesh.instanceColor.needsUpdate = true;
			}

		}

	}

	dispose() {

		if(this.nap !== null) {
			this.parent.remove(this.nap);
		}

		for(const mesh of [this.letters, this.spillMesh]) {
			this.parent.remove(mesh);
			mesh.geometry.dispose();
			mesh.material.dispose();
			mesh.dispose();
		}

	}

}

/**
 * Les ruines : un appui long sur un élément du décor, un abri, une pancarte, l'enseigne ou une planche propose Feu, Bombe ou
 * Séisme (`MapSceneHold`), et l'effet choisi le réduit en ruines. Rien ne s'écrit : tout revient au rechargement, ou à l'entrée
 * dans la pose du décor.
 *
 * L'élément visé se copie une fois, triangles et couleurs compris, en morceaux de triangles voisins, et l'original se masque : ce
 * sont ses propres morceaux qui frémissent sous l'appui, puis brûlent, volent ou s'écroulent. Les morceaux tiennent dans un seul
 * maillage, qu'un nuanceur déplace morceau par morceau d'après une petite texture (une ligne par morceau) : un appel de dessin par
 * élément touché, quel que soit le nombre de morceaux. Flammes, fumée, cratère et fissures se partagent des maillages instanciés,
 * flash, onde et poussière des nuages de points (`MapSceneRuinsMotes`) : un effet de plus n'ajoute aucun appel de dessin, et aucune
 * lumière, qui recompilerait les matériaux toon.
 *
 * Une cible se décrit sans que le moteur connaisse la scène : `MapScene.aimRuin()` la monte, `measure()` lui donne son emprise
 * { x, z, width, depth, height } dans son repère tourné de `angle`. Elle donne ses maillages par `parts()` (la terre d'une planche
 * marquée `ground`), se masque par `hide()`, revient par `show()`, et `shown()` dit si son calque se montre. `look` porte le
 * nuanceur de l'original quand la copie doit le reprendre, `pieces` impose un nombre de morceaux, `opacity` garde une bâche
 * translucide. `base` est la hauteur où se pose l'original, `floor(x, z)` celle du sol sous un point du monde, où se posent ses
 * débris, son cratère, ses fissures et sa lueur.
 */
class MapSceneRuins {

	// Les phases de chaque effet, en secondes depuis le déclenchement
	static BURN = { GROW: .8, BLAZE: 2.4, OUT: 3.6, CHAR: [.3, 2.2], SLUMP: [1.2, 2.8], ASH: [2.6, 3.6], END: 6 };
	static BOMB = { FALL: .7, END: 5 };
	static QUAKE = { SHAKE: 1.8, CRACK: [.1, 1.2], SPLIT: [.4, 1.7], TILT: [.9, 1.9], COLLAPSE: [1.9, 2.8], END: 5 };

	// Le nombre de morceaux suit la taille de l'élément : une brouette en fait 20, un hangar 60
	static CHUNKS = [20, 60];
	static GRAVITY = 22;

	// Les plafonds bornent ce que les effets ajoutent à la scène, tous effets confondus
	static FLAMES = 240;
	static PUFFS = 160;
	static EMBERS = 120;
	static MOUNDS = 256;
	static CRACKS = 512;
	static BOMBS = 8;
	static SPARKS = 900;
	static GLINTS = 512;
	static DUST = 640;

	// Les flammes d'un feu, selon l'emprise de ce qui brûle
	static TONGUES = [6, 30];
	static TONGUE_HEIGHT = [.8, 8];
	static TONGUE_WIDTH = [.3, 2.4];

	// Les étincelles du feu, par seconde et par feu
	static SPARK_RATE = 30;
	static SPARK_PIXELS = 48;

	static PUFF_LIFE = 2.2;
	static PUFF_RISE = 3;
	static WIND = { x: .6, z: -.25 };

	// Le frémissement sous l'appui, en mètres au plus fort : une part de l'emprise, bornée pour qu'une planche de trente mètres ne
	// saute pas d'un mètre
	static TREMBLE = [.03, .25];

	// La lueur d'une ruine flotte de quelques centimètres au-dessus du sol (spread()), pour ne pas s'y mêler
	static SPREAD_LIFT = .03;

	static FLAME_COLORS = ['#e2461f', '#ff7a1a', '#ffc93c'];
	static CHARRED = '#2b2420';
	static GLOWING = '#ff9a55';
	static ASH = '#77716b';
	static DUSTY = '#d8cfc2';
	static SMOKE_DARK = '#4a4542';
	static SMOKE_LIGHT = '#b9b4ae';
	static RIM = '#6b5a48';
	static CRACK = '#2a2622';
	static BOMB_COLOR = '#3a3d42';
	static MARK = '#fff1cc';

	constructor({ THREE, parent, gradientMap, shadow, still }) {

		this.THREE = THREE;
		this.parent = parent;
		this.gradientMap = gradientMap;
		this.shadow = shadow;
		// Sous prefers-reduced-motion : ni secousse de la caméra ni frémissement de l'élément ; l'effet a lieu quand même
		this.still = still;
		this.random = MapSceneRuins.seeded(4517);

		this.marks = new Map();
		this.prepared = new Map();
		this.records = new Map();
		this.ruined = new Map();
		this.shake = new THREE.Vector3();

		const instanced = (geometry, material, count) => {
			const mesh = new THREE.InstancedMesh(geometry, material, count);
			mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
			// La couleur d'instance existe dès le départ : le premier effet ne recompile pas son programme
			mesh.setColorAt(0, new THREE.Color('#ffffff'));
			mesh.count = 0;
			mesh.frustumCulled = false;
			parent.add(mesh);
			return mesh;
		};

		// Une langue de flamme : un fuseau à cinq pans, renflé au tiers, dont la pointe part de côté pour lécher
		const tongue = new THREE.LatheGeometry([new THREE.Vector2(.32, 0), new THREE.Vector2(.5, .28), new THREE.Vector2(.3, .62), new THREE.Vector2(0, 1)], 5);
		const outline = tongue.attributes.position;
		for(let i = 0; i < outline.count; i++) {
			outline.setX(i, outline.getX(i) + .16 * outline.getY(i) ** 2);
		}

		// La lueur au pied d'un effet : pleine au centre, nulle au bord, en mélange additif
		const halo = new THREE.CircleGeometry(1, 12).rotateX(-Math.PI / 2);
		halo.setAttribute('color', new THREE.BufferAttribute(Float32Array.from({ length: halo.attributes.position.count * 3 }, (_, i) => (i < 3) ? 1 : 0), 3));

		// La bombe : un corps rond qui se termine en ailettes, la pointe en bas
		const bomb = new THREE.LatheGeometry([[0, 0], [.3, .08], [.42, .3], [.42, .55], [.3, .8], [.12, .95], [.12, 1.1], [.3, 1.25], [.3, 1.4], [0, 1.4]].map(([x, y]) => new THREE.Vector2(x, y)), 8);

		this.flames = instanced(tongue, new THREE.MeshBasicMaterial({ color: '#ffffff' }), MapSceneRuins.FLAMES);
		this.glows = instanced(halo, new THREE.MeshBasicMaterial({ color: '#ffffff', vertexColors: true, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false }), MapSceneRuins.FLAMES / 6);
		// La fumée du feu est translucide, sans quoi ses bouffées se lisent comme des pierres
		this.puffs = instanced(new THREE.IcosahedronGeometry(1, 0), new THREE.MeshToonMaterial({ color: '#ffffff', gradientMap, transparent: true, opacity: .72, depthWrite: false }), MapSceneRuins.PUFFS);
		this.embers = instanced(new THREE.TetrahedronGeometry(.08, 0), new THREE.MeshBasicMaterial({ color: '#ffffff' }), MapSceneRuins.EMBERS);
		this.mounds = instanced(new THREE.SphereGeometry(1, 7, 2, 0, Math.PI * 2, 0, Math.PI / 2), new THREE.MeshToonMaterial({ color: '#ffffff', gradientMap }), MapSceneRuins.MOUNDS);
		this.cracks = instanced(new THREE.PlaneGeometry(1, 1).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ color: MapSceneRuins.CRACK, transparent: true, opacity: .9, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -2 }), MapSceneRuins.CRACKS);
		this.bombs = instanced(bomb, new THREE.MeshToonMaterial({ color: MapSceneRuins.BOMB_COLOR, gradientMap }), MapSceneRuins.BOMBS);

		this.mounds.receiveShadow = true;
		this.cracks.renderOrder = 5;

		// Les étincelles du feu, sur le modèle de MapSceneSparks : tout leur mouvement se calcule dans le nuanceur
		this.sparkGeometry = new THREE.BufferGeometry();
		this.sparkGeometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(MapSceneRuins.SPARKS * 3), 3));
		this.sparkList = new Float32Array(MapSceneRuins.SPARKS * 4).fill(-10);
		this.sparkGeometry.setAttribute('spark', new THREE.BufferAttribute(this.sparkList, 4));
		this.sparkGeometry.setAttribute('hue', new THREE.BufferAttribute(new Float32Array(MapSceneRuins.SPARKS), 1));
		this.sparkNext = 0;
		this.sparkMaterial = new THREE.ShaderMaterial({
			transparent: true,
			depthWrite: false,
			blending: THREE.AdditiveBlending,
			uniforms: { uTime: { value: 0 }, uPixels: { value: 1 }, uMax: { value: MapSceneRuins.SPARK_PIXELS } },
			vertexShader: `
				attribute vec4 spark;
				attribute float hue;
				uniform float uTime, uPixels, uMax;
				varying float vFade;
				varying float vHue;
				void main() {
					float life = .55 + hue * .5;
					float t = (uTime - spark.x) / life, alive = step(0., t) * step(t, 1.);
					vec3 p = position + vec3(sin(spark.w * 40. + t * 6.) * .15 + t * .5, spark.y * t, cos(spark.w * 31. + t * 5.) * .15);
					vFade = alive * (1. - t);
					vHue = hue;
					gl_Position = projectionMatrix * modelViewMatrix * vec4(p, 1.);
					gl_PointSize = min(uMax, alive * spark.z * uPixels * (1. - .6 * t));
				}`,
			fragmentShader: `
				varying float vFade;
				varying float vHue;
				void main() {
					float d = length(gl_PointCoord - .5);
					vec3 hot = mix(vec3(1., .86, .35), vec3(1., .35, .1), vHue);
					gl_FragColor = vec4(hot, vFade * smoothstep(.5, .1, d));
				}`
		});
		this.sparkPoints = new THREE.Points(this.sparkGeometry, this.sparkMaterial);
		this.sparkPoints.frustumCulled = false;
		this.sparkPoints.renderOrder = 31;
		this.sparkPoints.onBeforeRender = (renderer, scene, camera) => {
			this.sparkMaterial.uniforms.uPixels.value = MapSceneRuins.pixels(renderer, camera);
		};
		parent.add(this.sparkPoints);

		// Flash, boule de feu et onde de la bombe en lumière additive ; poussière et fumée en mélange normal
		this.glints = new MapSceneRuinsMotes({ THREE, parent, count: MapSceneRuins.GLINTS, additive: true, max: 160 });
		this.dust = new MapSceneRuinsMotes({ THREE, parent, count: MapSceneRuins.DUST, additive: false, max: 90 });

		// Les marques au sol restent jusqu'au rechargement : elles s'écrivent une fois, à leur place
		this.moundCount = 0;
		this.crackCount = 0;
		this.puffNext = 0;
		this.puffList = Array.from({ length: MapSceneRuins.PUFFS }, () => ({ born: -1e9, x: 0, y: 0, z: 0, size: 0, rise: 0, dark: 0, seed: 0 }));

		this.matrix = new THREE.Matrix4();
		this.color = new THREE.Color();
		this.quaternion = new THREE.Quaternion();
		this.tilt = new THREE.Quaternion();
		this.up = new THREE.Vector3(0, 1, 0);
		this.across = new THREE.Vector3(0, 0, 1);
		this.identity = new THREE.Quaternion();
		this.white = new THREE.Color('#ffffff');

	}

	static seeded(seed) {
		let s = seed >>> 0;
		return () => {
			s = (s * 1664525 + 1013904223) >>> 0;
			return s / 4294967296;
		};
	}

	static clamp(value, [min, max]) {
		return Math.min(max, Math.max(min, value));
	}

	// La part écoulée de l'intervalle [from, to], adoucie aux deux bouts
	static ease(t, [from, to]) {
		const k = Math.min(1, Math.max(0, (t - from) / (to - from)));
		return k * k * (3 - 2 * k);
	}

	/**
	 * La découpe : une grille posée sur l'élément, dont chaque case occupée fait un morceau. Le pas se cherche par dichotomie pour
	 * que le nombre de cases occupées approche `wanted`, creux compris : un hangar n'occupe que les cases de ses murs et de son toit.
	 */
	static grid(points, wanted) {

		const min = [Infinity, Infinity, Infinity], max = [-Infinity, -Infinity, -Infinity];
		for(const p of points) {
			for(let axis = 0; axis < 3; axis++) {
				min[axis] = Math.min(min[axis], p[axis]);
				max[axis] = Math.max(max[axis], p[axis]);
			}
		}

		// Une case se numérote sur trois axes de 1024 cases au plus : un nombre se range plus vite qu'une chaine
		const cut = step => points.map(p => Math.floor((p[0] - min[0]) / step) * 1048576 + Math.floor((p[1] - min[1]) / step) * 1024 + Math.floor((p[2] - min[2]) / step));
		const count = step => new Set(cut(step)).size;

		let low = Math.max(max[0] - min[0], max[1] - min[1], max[2] - min[2], 1e-3) / 1000, high = low * 2000;

		for(let i = 0; i < 14; i++) {
			const step = Math.sqrt(low * high);
			if(count(step) > wanted) {
				low = step;
			} else {
				high = step;
			}
		}

		return cut(high);

	}

	/**
	 * L'emprise d'une cible d'après ses maillages, dans son repère tourné de `angle` : son centre au sol, sa largeur le long de
	 * l'angle, sa profondeur en travers et sa hauteur au-dessus de `base`. La cible la reçoit et se rend.
	 */
	static measure(THREE, target) {

		const cos = Math.cos(target.angle), sin = Math.sin(target.angle);
		const box = new THREE.Box3();
		const corner = new THREE.Vector3();
		const low = [Infinity, Infinity], high = [-Infinity, -Infinity];
		let top = -Infinity;

		for(const part of target.parts()) {

			if(part.geometry.boundingBox === null) {
				part.geometry.computeBoundingBox();
			}

			box.copy(part.geometry.boundingBox);

			for(let i = 0; i < 8; i++) {
				corner.set((i & 1) ? box.max.x : box.min.x, (i & 2) ? box.max.y : box.min.y, (i & 4) ? box.max.z : box.min.z).applyMatrix4(part.matrix);
				const a = corner.x * cos - corner.z * sin, b = corner.x * sin + corner.z * cos;
				low[0] = Math.min(low[0], a);
				low[1] = Math.min(low[1], b);
				high[0] = Math.max(high[0], a);
				high[1] = Math.max(high[1], b);
				top = Math.max(top, corner.y);
			}

		}

		const a = (low[0] + high[0]) / 2, b = (low[1] + high[1]) / 2;

		return Object.assign(target, {
			x: a * cos + b * sin,
			z: -a * sin + b * cos,
			width: Math.max(high[0] - low[0], .2),
			depth: Math.max(high[1] - low[1], .2),
			height: Math.max(top - target.base, target.least ?? .3)
		});

	}

	/**
	 * Un maillage de la scène en morceau de copie, dans le repère du monde. Un maillage sans couleurs de sommets prend la teinte unie
	 * de son matériau ; un maillage à plusieurs matériaux, une teinte par groupe.
	 */
	static part(mesh) {

		mesh.updateMatrixWorld(true);

		return { geometry: mesh.geometry, matrix: mesh.matrixWorld.clone(), color: Array.isArray(mesh.material) ? mesh.material.map(material => material.color.clone()) : mesh.material.color.clone() };

	}

	// Combien de pixels fait un mètre à l'écran, pour que la taille d'un point se donne en mètres
	static pixels(renderer, camera) {
		const height = renderer.getRenderTarget()?.height ?? renderer.domElement.height;
		return camera.isOrthographicCamera ? height * camera.zoom / (camera.top - camera.bottom) : height / 40;
	}

	/**
	 * Le nuanceur des morceaux : chaque sommet lit dans la texture la matrice de son morceau, et le fragment sa teinte. La teinte
	 * s'applique après tout ce que le nuanceur de l'original fait de la couleur : un arbre d'automne brûlé noircit. Le même texte
	 * sert au matériau de l'ombre, qui n'a ni normale ni couleur.
	 */
	static chunkShader(shader, depth) {

		const read = `
			int chunkRow = int(chunk);
			mat4 chunkMatrix = mat4(texelFetch(uChunks, ivec2(0, chunkRow), 0), texelFetch(uChunks, ivec2(1, chunkRow), 0), texelFetch(uChunks, ivec2(2, chunkRow), 0), texelFetch(uChunks, ivec2(3, chunkRow), 0));`;

		shader.vertexShader = shader.vertexShader.replace('#include <common>', '#include <common>\nattribute float chunk;\nuniform highp sampler2D uChunks;'+ (depth ? '' : '\nvarying vec3 vChunkTint;'));

		if(depth) {
			shader.vertexShader = shader.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>'+ read +'\ntransformed = (chunkMatrix * vec4(transformed, 1.)).xyz;');
			return;
		}

		// La normale se calcule avant la position dans le nuanceur toon : la matrice se lit avant elle, une fois pour les deux
		shader.vertexShader = shader.vertexShader
			.replace('#include <beginnormal_vertex>', '#include <beginnormal_vertex>'+ read +'\nvChunkTint = texelFetch(uChunks, ivec2(4, chunkRow), 0).rgb;\nobjectNormal = normalize(mat3(chunkMatrix) * objectNormal);')
			.replace('#include <begin_vertex>', '#include <begin_vertex>\ntransformed = (chunkMatrix * vec4(transformed, 1.)).xyz;');

		shader.fragmentShader = shader.fragmentShader
			.replace('#include <common>', '#include <common>\nvarying vec3 vChunkTint;')
			.replace('#include <color_fragment>', '#include <color_fragment>\ndiffuseColor.rgb *= vChunkTint;');

	}

	busy(key) {
		return this.records.has(key) || this.ruined.has(key);
	}

	/**
	 * Le retour pendant l'appui : une lueur grandit sous la cible (`glow`, de 0 à 1), et sa copie frémit de plus en plus fort
	 * (`tremble`). Une marque à 0 rend la cible immobile.
	 */
	mark(target, glow, tremble) {

		if(this.busy(target.key)) {
			return;
		}

		if(glow <= 0 && tremble <= 0) {
			this.marks.delete(target.key);
			const shatter = this.prepared.get(target.key);
			shatter?.mesh.position.set(0, 0, 0);
			return;
		}

		this.marks.set(target.key, { target, glow, tremble });

	}

	/**
	 * La copie se fait dès que la cible frémit, et prend sa place : la découpe d'un hangar coûte plusieurs dizaines de millisecondes,
	 * payées pendant l'appui et non au choix, et une planche, dont les plants sont des instances, ne frémit que par sa copie. Un
	 * appui lâché ou un choix abandonné rend l'original par forget().
	 */
	prepare(target) {

		if(this.busy(target.key) || this.prepared.has(target.key)) {
			return;
		}

		const shatter = this.shatter(target);
		this.prepared.set(target.key, shatter);
		target.hide();
		this.shadow();

	}

	forget(key) {

		const shatter = this.prepared.get(key);

		if(shatter === undefined) {
			return;
		}

		this.marks.delete(key);
		this.prepared.delete(key);
		this.dispose3d(shatter);
		shatter.target.show();
		this.shadow();

	}

	begin(kind, target, now) {

		if(this.busy(target.key)) {
			return false;
		}

		this.marks.delete(target.key);
		this.prepare(target);

		const record = { kind, target, started: now, step: -1, done: {} };
		record.shatter = this.prepared.get(target.key);
		record.shatter.mesh.position.set(0, 0, 0);
		this.prepared.delete(target.key);
		record.chunks = record.shatter.chunks;
		record.span = Math.max(target.width, target.depth);

		({ burn: () => this.prepareBurn(record), bomb: () => this.prepareBomb(record), quake: () => this.prepareQuake(record) })[kind]();

		this.records.set(target.key, record);
		this.shadow();

		return true;

	}

	/**
	 * La copie de l'élément en morceaux. Ses triangles, pris dans le repère du monde avec leurs normales, leurs couleurs et les
	 * attributs que lit le nuanceur de l'original (`look.attributes`), se rangent dans les cases d'une grille d'après leur centre :
	 * chaque morceau est un amas de triangles voisins. La terre d'une planche se découpe à part, pour rester au sol. Un plant garde
	 * la teinte de son instance et la taille de son feuillage, cuites dans la copie.
	 */
	shatter(target) {

		const THREE = this.THREE;
		const R = MapSceneRuins;

		const look = target.look ?? null;
		const sizes = { position: 3, normal: 3, color: 3, ...(look?.attributes ?? {}) };
		const extras = Object.keys(sizes).slice(3);
		const points = ['position', ...(look?.points ?? [])];
		const triangles = { channels: Object.fromEntries(Object.keys(sizes).map(name => [name, []])), sizes, ground: [] };
		const { channels } = triangles;

		const vertex = new THREE.Vector3();
		const anchor = new THREE.Vector3();
		const normal = new THREE.Vector3();
		const color = new THREE.Color();
		const normalMatrix = new THREE.Matrix3();

		for(const part of target.parts()) {

			const geometry = part.geometry;
			const { position, color: colors, anchor: anchors } = geometry.attributes;
			const normals = geometry.attributes.normal ?? null;
			const index = geometry.index;
			const count = index ? index.count : position.count;
			const leaf = part.leaf ?? 1;
			const shrinks = (leaf !== 1 && anchors !== undefined);
			const groups = Array.isArray(part.color) ? geometry.groups : [];

			normalMatrix.getNormalMatrix(part.matrix);

			for(let i = 0; i < count; i++) {

				const v = index ? index.getX(i) : i;

				vertex.fromBufferAttribute(position, v);

				// Un feuillage naissant ou tombant se rétrécit vers son point d'attache, comme le fait le nuanceur des cultures
				if(shrinks) {
					anchor.fromBufferAttribute(anchors, v);
					vertex.sub(anchor).multiplyScalar(leaf).add(anchor);
				}

				vertex.applyMatrix4(part.matrix);
				channels.position.push(vertex.x, vertex.y, vertex.z);

				if(normals === null) {
					channels.normal.push(0, 1, 0);
				} else {
					normal.fromBufferAttribute(normals, v).applyMatrix3(normalMatrix).normalize();
					channels.normal.push(normal.x, normal.y, normal.z);
				}

				const solid = (groups.length > 0) ? part.color[groups.find(group => i >= group.start && i < group.start + group.count)?.materialIndex ?? 0] : part.color;
				color.copy(solid);

				if(colors !== undefined) {
					color.multiply(this.color.setRGB(colors.getX(v), colors.getY(v), colors.getZ(v)));
				}

				if(part.tint) {
					color.lerp(this.color.setRGB(part.tint.x, part.tint.y, part.tint.z), part.tint.w);
				}

				channels.color.push(color.r, color.g, color.b);

				// Un morceau sans l'attribut le reçoit nul : les plants d'une planche n'ont pas le repère de sa terre
				for(const name of extras) {
					const attribute = geometry.attributes[name];
					if(attribute === undefined) {
						channels[name].push(...new Array(sizes[name]).fill(0));
					} else if(points.includes(name)) {
						vertex.fromBufferAttribute(attribute, v).applyMatrix4(part.matrix);
						channels[name].push(vertex.x, vertex.y, vertex.z);
					} else {
						for(let k = 0; k < sizes[name]; k++) {
							channels[name].push(attribute.getComponent(v, k));
						}
					}
				}

				if(i % 3 === 0) {
					triangles.ground.push(part.ground === true);
				}

			}

		}

		const size = Math.sqrt(target.width * target.depth + target.height * Math.max(target.width, target.depth));
		this.subdivide(triangles, R.clamp(size / 5, [.4, 3]));

		const total = triangles.ground.length;
		const wanted = Math.min(total, target.pieces ?? R.clamp(Math.round(size * 4), R.CHUNKS));
		const groundShare = triangles.ground.some(Boolean) ? R.clamp(Math.round(wanted * .25), [4, 16]) : 0;

		const positions = triangles.channels.position;
		const centroid = i => [0, 1, 2].map(axis => (positions[i * 9 + axis] + positions[i * 9 + 3 + axis] + positions[i * 9 + 6 + axis]) / 3);
		const owner = new Int32Array(total).fill(-1);
		let chunkCount = 0;

		for(const ground of [true, false]) {

			const members = [];
			for(let i = 0; i < total; i++) {
				if(triangles.ground[i] === ground) {
					members.push(i);
				}
			}

			if(members.length === 0) {
				continue;
			}

			const n = Math.max(1, Math.min(members.length, ground ? groundShare : wanted - groundShare));
			const cells = MapSceneRuins.grid(members.map(centroid), n);

			// Une case sans triangle ne fait pas de morceau
			const renumber = new Map();
			cells.forEach((cell, j) => {
				if(renumber.has(cell) === false) {
					renumber.set(cell, chunkCount++);
				}
				owner[members[j]] = renumber.get(cell);
			});

		}

		// Chaque morceau, sa boite et son centre ; ses sommets et ses points d'attache se rangent autour de son centre
		const boxes = Array.from({ length: chunkCount }, () => new THREE.Box3());
		const grounds = new Array(chunkCount).fill(false);

		for(let i = 0; i < total; i++) {
			for(let v = 0; v < 3; v++) {
				boxes[owner[i]].expandByPoint(vertex.fromArray(positions, i * 9 + v * 3));
			}
			grounds[owner[i]] = triangles.ground[i];
		}

		const centers = boxes.map(box => box.getCenter(new THREE.Vector3()));
		const chunk = new Float32Array(total * 3);

		for(const name of points) {
			const list = triangles.channels[name];
			for(let i = 0; i < total; i++) {
				const c = centers[owner[i]];
				for(let v = 0; v < 3; v++) {
					list[i * 9 + v * 3] -= c.x;
					list[i * 9 + v * 3 + 1] -= c.y;
					list[i * 9 + v * 3 + 2] -= c.z;
				}
			}
		}

		for(let i = 0; i < total; i++) {
			chunk.fill(owner[i], i * 3, i * 3 + 3);
		}

		const geometry = new THREE.BufferGeometry();
		for(const [name, list] of Object.entries(triangles.channels)) {
			geometry.setAttribute(name, new THREE.BufferAttribute(new Float32Array(list), sizes[name]));
		}
		geometry.setAttribute('chunk', new THREE.BufferAttribute(chunk, 1));

		// Cinq texels par morceau : les quatre colonnes de sa matrice, puis sa teinte
		const data = new Float32Array(5 * chunkCount * 4);
		const texture = new THREE.DataTexture(data, 5, chunkCount, THREE.RGBAFormat, THREE.FloatType);
		texture.needsUpdate = true;

		// Un matériau par élément touché, mais un seul programme par sorte d'original : même clé, même texte
		const opacity = target.opacity ?? 1;
		const material = new THREE.MeshToonMaterial({ vertexColors: true, gradientMap: this.gradientMap, transparent: opacity < 1, opacity, depthWrite: opacity === 1, side: (opacity < 1) ? THREE.DoubleSide : THREE.FrontSide });
		material.onBeforeCompile = shader => {
			shader.uniforms.uChunks = { value: texture };
			MapSceneRuins.chunkShader(shader, false);
			look?.material.onBeforeCompile(shader);
		};
		material.customProgramCacheKey = () => 'map-scene-ruins-chunks-'+ (look?.name ?? 'plain');

		const depth = new THREE.MeshDepthMaterial({ depthPacking: THREE.RGBADepthPacking });
		depth.onBeforeCompile = shader => {
			shader.uniforms.uChunks = { value: texture };
			MapSceneRuins.chunkShader(shader, true);
			look?.depth?.onBeforeCompile(shader);
		};
		depth.customProgramCacheKey = () => 'map-scene-ruins-depth-'+ (look?.name ?? 'plain');

		const mesh = new THREE.Mesh(geometry, material);
		mesh.customDepthMaterial = depth;
		mesh.castShadow = (opacity === 1);
		mesh.receiveShadow = true;
		mesh.frustumCulled = false;
		// Une bâche se dessine après les ombres qu'elle couvre, comme l'original
		mesh.renderOrder = (opacity < 1) ? 10 : 0;
		this.parent.add(mesh);

		const base = target.base;

		const chunks = centers.map((center, i) => ({
			center,
			half: boxes[i].getSize(new THREE.Vector3()).multiplyScalar(.5),
			bottom: boxes[i].min.y,
			ground: grounds[i],
			rise: R.clamp((center.y - base) / Math.max(target.height, .1), [0, 1]),
			seed: this.random(),
			position: center.clone(),
			quaternion: new THREE.Quaternion(),
			scale: new THREE.Vector3(1, 1, 1),
			tint: new THREE.Color('#ffffff')
		}));

		const shatter = { target, mesh, texture, data, chunks, triangles: total };
		this.write(shatter);

		return shatter;

	}

	/**
	 * Un mur de hangar tient en deux triangles de vingt mètres : rangés tels quels dans la grille, ils feraient des éclats aussi
	 * longs que lui. Tout triangle dont un côté dépasse `edge` se coupe au milieu de ce côté, jusqu'à ce qu'aucun ne dépasse. Chaque
	 * attribut du triangle se coupe avec lui.
	 */
	subdivide(triangles, edge) {

		// La position vient en premier : c'est elle qui mesure les côtés
		const names = Object.keys(triangles.channels);
		const sizes = names.map(name => triangles.sizes[name]);
		const limit = edge * edge;
		const out = Object.fromEntries(names.map(name => [name, []]));
		const ground = [];
		const stack = [];

		for(let i = 0; i < triangles.ground.length; i++) {
			stack.push([names.map((name, k) => triangles.channels[name].slice(i * 3 * sizes[k], (i + 1) * 3 * sizes[k])), triangles.ground[i]]);
		}

		while(stack.length > 0) {

			const [lists, g] = stack.pop();
			const p = lists[0];
			let longest = -1, length = limit;

			for(let e = 0; e < 3; e++) {
				const a = e * 3, b = ((e + 1) % 3) * 3;
				const d = (p[a] - p[b]) ** 2 + (p[a + 1] - p[b + 1]) ** 2 + (p[a + 2] - p[b + 2]) ** 2;
				if(d > length) {
					length = d;
					longest = e;
				}
			}

			if(longest === -1) {
				names.forEach((name, k) => out[name].push(...lists[k]));
				ground.push(g);
				continue;
			}

			// Le côté AB se coupe en M : ABC devient AMC et MBC, dans le même sens de rotation
			const a = longest, b = (longest + 1) % 3, o = (longest + 2) % 3;
			const pick = (k, at) => lists[k].slice(at * sizes[k], (at + 1) * sizes[k]);
			const middle = k => pick(k, a).map((value, j) => (value + lists[k][b * sizes[k] + j]) / 2);

			stack.push([lists.map((list, k) => [...pick(k, a), ...middle(k), ...pick(k, o)]), g]);
			stack.push([lists.map((list, k) => [...middle(k), ...pick(k, b), ...pick(k, o)]), g]);

		}

		triangles.channels = out;
		triangles.ground = ground;

	}

	write(shatter) {

		const { data, chunks } = shatter;

		chunks.forEach((chunk, i) => {
			this.matrix.compose(chunk.position, chunk.quaternion, chunk.scale);
			data.set(this.matrix.elements, i * 20);
			data[i * 20 + 16] = chunk.tint.r;
			data[i * 20 + 17] = chunk.tint.g;
			data[i * 20 + 18] = chunk.tint.b;
			data[i * 20 + 19] = 1;
		});

		shatter.texture.needsUpdate = true;

	}

	// Un point de l'emprise de la cible, donné dans son repère (a le long de la largeur, b de la profondeur)
	at(target, a, b) {
		const cos = Math.cos(target.angle), sin = Math.sin(target.angle);
		return { x: target.x + a * cos + b * sin, z: target.z - a * sin + b * cos };
	}

	/**
	 * Le feu : les flammes montent, les morceaux rougissent puis noircissent, s'affaissent l'un après l'autre en commençant par le
	 * haut, et se tassent en cendres là où ils sont tombés. La terre d'une planche noircit sans bouger.
	 */
	prepareBurn(record) {

		const R = MapSceneRuins;
		const { target } = record;
		const bed = (target.bed === true);
		const area = target.width * target.depth;
		const count = Math.round(R.clamp(area * (bed ? 1.2 : .35), R.TONGUES));
		// Sur une planche, les flammes dépassent les plants d'une moitié, pour se voir au-dessus des planches voisines
		const tall = R.clamp(target.height * (bed ? 1.5 : .75), R.TONGUE_HEIGHT);
		const wide = R.clamp(Math.min(target.width, target.depth) * (bed ? .7 : .3), R.TONGUE_WIDTH);

		record.tongues = Array.from({ length: count }, () => ({
			a: (this.random() - .5) * target.width * (bed ? 1 : .8),
			b: (this.random() - .5) * target.depth * (bed ? .9 : .8),
			// Sur un objet, la flamme part de son volume pour sortir de son toit ; sur une planche, du sol sous elle (`floor()`)
			lift: bed ? null : target.base + target.height * (.15 + this.random() * .55),
			height: tall * (.55 + this.random() * .45),
			width: wide * (.7 + this.random() * .5),
			seed: this.random() * 100,
			color: Math.floor(this.random() * 2)
		}));

		record.smoked = record.started;
		record.sparked = record.started;
		this.rest(record, bed ? 1 : .75, .12, Math.min(target.height * .12, .6));
		record.embers = record.chunks.filter(chunk => chunk.ground === false).slice(0, 24).map(chunk => ({ chunk, seed: this.random() * 10 }));

	}

	/**
	 * Où chaque morceau finit : ramené vers le centre de `pull`, posé au sol, écrasé à `flatten` de sa hauteur, sur un tas qui
	 * monte au plus de `heap` au centre. Les morceaux du haut tombent les premiers. Un morceau `lying` se couche sur sa face la plus
	 * large au lieu de s'écraser : un pan de mur tombé se lit à plat, pas debout.
	 */
	rest(record, pull, flatten, heap, lying = false) {

		const THREE = this.THREE;
		const { target } = record;
		const reach = Math.max(target.width, target.depth) * .6;

		for(const chunk of record.chunks) {
			const x = target.x + (chunk.center.x - target.x) * pull + (this.random() - .5) * chunk.half.x;
			const z = target.z + (chunk.center.z - target.z) * pull + (this.random() - .5) * chunk.half.z;
			const r = Math.hypot(x - target.x, z - target.z) / Math.max(reach, .1);
			const heaped = heap * Math.max(0, 1 - r * r) * (.3 + .7 * chunk.rise);
			if(lying) {
				const lie = this.lie(chunk);
				chunk.rest = new THREE.Vector3(x, target.floor(x, z) + lie.low + heaped, z);
				chunk.fall = lie.quaternion;
			} else {
				chunk.rest = new THREE.Vector3(x, target.floor(x, z) + chunk.half.y * flatten + heaped, z);
				chunk.fall = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(this.random() - .5, 0, this.random() - .5).normalize(), (this.random() - .5) * 1.2);
			}
			chunk.delay = (1 - chunk.rise) * .6 + this.random() * .25;
		}

	}

	// Un morceau couché sur sa face la plus large, tourné au hasard : son axe le plus mince passe à la verticale
	lie(chunk) {

		const THREE = this.THREE;
		const { x, y, z } = chunk.half;
		const quaternion = new THREE.Quaternion();
		let low = y;

		if(x < y && x < z) {
			quaternion.setFromAxisAngle(new THREE.Vector3(0, 0, 1), Math.PI / 2);
			low = x;
		} else if(z < y) {
			quaternion.setFromAxisAngle(new THREE.Vector3(1, 0, 0), Math.PI / 2);
			low = z;
		}

		const yaw = new THREE.Quaternion().setFromAxisAngle(this.up, this.random() * Math.PI * 2);
		return { quaternion: yaw.multiply(quaternion), low };

	}

	stepBurn(record, t, now, count) {

		const R = MapSceneRuins;
		const B = R.BURN;
		const { target } = record;
		const slump = R.ease(t, B.SLUMP);
		const size = R.ease(t, [0, B.GROW]) * (1 - R.ease(t, [B.BLAZE, B.OUT]));

		if(size > 0) {

			this.spark(record, now, size);

			for(const tongue of record.tongues) {

				if(count.flames >= R.FLAMES - 1) {
					break;
				}

				const flicker = 1 + .22 * Math.sin(t * 13 + tongue.seed * 7) + .12 * Math.sin(t * 23 + tongue.seed * 3);
				const { x, z } = this.at(target, tongue.a, tongue.b);
				const turn = tongue.seed + t * 1.7;
				const y = target.bed ? target.floor(x, z) : target.base + (tongue.lift - target.base) * (1 - .88 * slump);

				// La langue extérieure, rouge ou orangée, puis son cœur jaune, plus petit
				this.place(this.flames, count.flames++, x, y, z, turn, tongue.width * size, tongue.height * size * flicker, tongue.width * size, this.color.set(R.FLAME_COLORS[tongue.color]));
				this.place(this.flames, count.flames++, x, y, z, turn + 1, tongue.width * size * .55, tongue.height * size * flicker * .6, tongue.width * size * .55, this.color.set(R.FLAME_COLORS[2]));

			}

			// La lueur suit l'emprise : ronde sous un objet, allongée le long d'une planche
			const pulse = size * (1 + .08 * Math.sin(t * 17));
			this.spread(this.glows, count.glows++, target, (target.width * .5 + 1) * pulse, (target.depth * .5 + 1) * pulse, this.color.set(R.FLAME_COLORS[0]).multiplyScalar(.12));

		}

		const charred = R.ease(t, B.CHAR);
		const ashen = R.ease(t, B.ASH);
		const heat = Math.min(1, t / .4) * (1 - charred);

		for(const chunk of record.chunks) {

			if(chunk.ground) {
				chunk.tint.copy(this.white).lerp(this.color.set(R.CHARRED), charred * .75);
				continue;
			}

			const k = R.ease(t, [B.SLUMP[0] + chunk.delay, B.SLUMP[1] + chunk.delay]);
			chunk.position.lerpVectors(chunk.center, chunk.rest, k);
			chunk.quaternion.slerpQuaternions(this.identity, chunk.fall, k);
			chunk.scale.set(1 - .3 * k, 1 - .82 * k, 1 - .3 * k);
			chunk.tint.copy(this.white).lerp(this.color.set(R.GLOWING), heat).lerp(this.color.set(R.CHARRED), charred).lerp(this.color.set(R.ASH), ashen * (.4 + .5 * chunk.seed));

		}

		// L'ombre ne se recalcule que sur demande : par quarts d'affaissement, pas à chaque image
		this.shadowStep(record, Math.floor(slump * 4));

		// Des braises qui pâlissent sur les cendres
		const glow = R.ease(t, [B.SLUMP[1], B.SLUMP[1] + .4]) * (1 - R.ease(t, [B.ASH[1], B.END]));

		for(const ember of glow > 0 ? record.embers : []) {
			if(count.embers >= R.EMBERS) {
				break;
			}
			const blink = .5 + .5 * Math.sin(t * 6 + ember.seed * 9);
			const p = ember.chunk.position;
			this.place(this.embers, count.embers++, p.x, p.y + ember.chunk.half.y * .25, p.z, ember.seed, 1, 1, 1, this.color.set(R.FLAME_COLORS[1]).multiplyScalar(glow * (.4 + .6 * blink)));
		}

		// La fumée : sombre et nourrie pendant le feu, claire et de plus en plus rare sur les cendres
		const every = (t < B.OUT) ? 120 : 260 + 900 * R.ease(t, [B.OUT, B.END]);

		if(t > .4 && t < B.END && now - record.smoked > every) {
			record.smoked = now;
			const big = target.bed ? .55 : R.clamp(record.span * .1, [.3, 1.1]);
			this.puff(target, now, big, (t < B.OUT) ? 1 : .25, (t < B.OUT) ? 1 : .3);
		}

	}

	/**
	 * La bombe : elle tombe sur l'élément, éclate en un flash et une boule de feu, l'onde court au sol, et les morceaux de
	 * l'élément sont projetés puis retombent autour du cratère, un peu noircis près du centre.
	 */
	prepareBomb(record) {

		const THREE = this.THREE;
		const R = MapSceneRuins;
		const { target } = record;
		const g = R.GRAVITY;

		// Le cratère épouse l'emprise de l'élément, élargie d'une marge : allongé sous une planche, rond sous un arbre
		record.crater = R.clamp(Math.min(target.width, target.depth) * .5 + 1, [1.2, 4]);
		record.hole = { a: target.width / 2 * 1.05 + record.crater * .5, b: target.depth / 2 * 1.05 + record.crater * .5 };
		record.top = target.base + (target.bed ? .3 : target.height);
		record.smoked = record.started;

		for(const chunk of record.chunks) {

			const lie = this.lie(chunk);
			const floor = target.floor(chunk.center.x, chunk.center.z) + lie.low;
			const up = chunk.ground ? 3 + this.random() * 3 : 6 + this.random() * 6;
			const time = (up + Math.sqrt(up * up + 2 * g * Math.max(0, chunk.center.y - floor))) / g;
			const away = new THREE.Vector3(chunk.center.x - target.x + (this.random() - .5) * .6, 0, chunk.center.z - target.z + (this.random() - .5) * .6);
			const from = away.length();
			away.normalize();

			// Assez vite pour sortir du cratère, sans dépasser deux fois son rayon
			const land = from + record.crater * (.6 + this.random() * 1.2);
			const speed = Math.max(0, land - from) / time + this.random() * 1.5;

			chunk.velocity = new THREE.Vector3(away.x * speed, up, away.z * speed);
			chunk.time = time;
			chunk.floor = floor;
			chunk.spin = new THREE.Vector3(this.random() - .5, this.random() - .5, this.random() - .5).normalize();
			chunk.rate = 3 + this.random() * 6;
			chunk.fall = lie.quaternion;
			chunk.scorch = Math.max(0, 1 - from / Math.max(record.span * .5, .5)) * (.4 + .4 * this.random());

		}

		record.landed = Math.max(...record.chunks.map(chunk => chunk.time));

	}

	stepBomb(record, t, now, count) {

		const R = MapSceneRuins;
		const B = R.BOMB;
		const { target } = record;
		const g = R.GRAVITY;

		if(t < B.FALL) {
			const k = t / B.FALL;
			const size = R.clamp(record.span * .1, [.6, 1.4]);
			this.place(this.bombs, count.bombs++, target.x, record.top + 16 * (1 - k * k), target.z, t * 3, size, size, size, this.color.set('#ffffff'));
			return;
		}

		const hit = t - B.FALL;

		if(record.done.impact === undefined) {
			record.done.impact = true;
			this.impact(record, now);
		}

		for(const chunk of record.chunks) {

			const s = Math.min(hit, chunk.time);
			chunk.position.set(chunk.center.x + chunk.velocity.x * s, chunk.center.y + chunk.velocity.y * s - .5 * g * s * s, chunk.center.z + chunk.velocity.z * s);
			// Il tourne en vol et touche le sol couché : la rotation qui reste à faire s'annule à l'atterrissage
			chunk.quaternion.setFromAxisAngle(chunk.spin, chunk.rate * (s - chunk.time)).premultiply(chunk.fall);
			chunk.tint.copy(this.white).lerp(this.color.set(R.CHARRED), chunk.scorch * Math.min(1, hit / .3));

		}

		// L'ombre au moment de l'éclat, puis quand tout est retombé
		this.shadowStep(record, (hit > record.landed) ? 2 : 1);

		// Le cratère fume, de moins en moins
		if(hit > .2 && t < B.END - .5 && now - record.smoked > 110 + 400 * R.ease(t, [1.5, B.END])) {
			record.smoked = now;
			const p = this.at(target, (this.random() - .5) * record.hole.a, (this.random() - .5) * record.hole.b);
			this.dust.emit({ x: p.x, y: target.base + .3, z: p.z, vx: R.WIND.x, vy: 1.4 + this.random(), vz: R.WIND.z, gravity: -.2, born: now, life: 2.6, from: 1, to: 3, color: R.SMOKE_DARK, alpha: .5 });
		}

	}

	impact(record, now) {

		const R = MapSceneRuins;
		const { target } = record;
		const y = record.top;
		const c = record.crater;

		// Flash, boule de feu qui monte, onde au ras du sol, et la poussière qu'elle soulève
		this.glints.emit({ x: target.x, y, z: target.z, born: now, life: .35, from: c * 3, to: c * 4, color: '#fff6d8', alpha: 1 });

		for(let i = 0; i < 40; i++) {
			const a = this.random() * Math.PI * 2, up = this.random();
			const speed = 2 + this.random() * 4;
			this.glints.emit({ x: target.x, y, z: target.z, vx: Math.cos(a) * speed * (1 - up), vy: 1 + up * 5, vz: Math.sin(a) * speed * (1 - up), gravity: -1.5, born: now + this.random() * 80, life: .6 + this.random() * .5, from: 1.2 + this.random() * c * .4, to: 2.4 + c * .5, color: R.FLAME_COLORS[Math.floor(this.random() * 3)], alpha: .9 });
		}

		for(let i = 0; i < 64; i++) {
			const a = i / 64 * Math.PI * 2;
			this.glints.emit({ x: target.x, y: target.base + .25, z: target.z, vx: Math.cos(a) * 18, vz: Math.sin(a) * 18, born: now, life: .45, from: .7, to: .2, color: '#ffe2a8', alpha: .8 });
			this.dust.emit({ x: target.x + Math.cos(a) * c * .4, y: target.base + .3, z: target.z + Math.sin(a) * c * .4, vx: Math.cos(a) * 9, vy: .6, vz: Math.sin(a) * 9, gravity: 1, born: now, life: .9 + this.random() * .3, from: 1, to: 2.6, color: R.DUSTY, alpha: .55 });
		}

		// Le cratère : un bourrelet de terre autour
		const { a: ha, b: hb } = record.hole;

		const rim = [];
		const beads = Math.round(R.clamp(Math.PI * (ha + hb) / (c * .35), [12, 48]));
		for(let i = 0; i < beads; i++) {
			const a = (i + this.random() * .5) / beads * Math.PI * 2;
			rim.push({ ...this.at(target, Math.cos(a) * ha * 1.02, Math.sin(a) * hb * 1.02), radius: c * (.14 + this.random() * .08) });
		}
		this.mound(rim, target.floor, .4, R.RIM);

		record.shakeAt = now;

	}

	/**
	 * Le séisme : la scène tremble, des fissures courent au sol depuis l'élément, ses morceaux s'écartent le long d'elles, il
	 * penche, puis s'effondre en un tas de gravats dans la poussière. Une planche se craquelle, sa terre s'ouvre et ses
	 * cultures se couchent.
	 */
	prepareQuake(record) {

		const THREE = this.THREE;
		const R = MapSceneRuins;
		const { target } = record;

		record.dusted = record.started;
		record.cracks = this.crack(target);

		if(target.bed) {

			const cos = Math.cos(target.angle), sin = Math.sin(target.angle);
			const across = new THREE.Vector3(sin, 0, cos);

			for(const chunk of record.chunks) {
				// Chaque morceau de terre part de son côté de la fissure, chaque plant se couche dans un sens à lui
				chunk.side = Math.sign((chunk.center.x - target.x) * sin + (chunk.center.z - target.z) * cos) || 1;
				chunk.away = across.clone().multiplyScalar(chunk.side);
				chunk.lay = new THREE.Vector3(this.random() - .5, 0, this.random() - .5).normalize();
				chunk.delay = this.random() * .5;
			}

			return;

		}

		this.rest(record, 1.1, .6, R.clamp(target.height * .3, [.3, 3]), true);

		record.tilt = new THREE.Vector3(this.random() - .5, 0, this.random() - .5).normalize();
		record.lean = .22 + this.random() * .12;
		record.gap = R.clamp(record.span * .05, [.08, .6]);

		for(const chunk of record.chunks) {
			chunk.away = new THREE.Vector3(chunk.center.x - target.x, 0, chunk.center.z - target.z).normalize();
		}

	}

	stepQuake(record, t, now) {

		const THREE = this.THREE;
		const R = MapSceneRuins;
		const Q = R.QUAKE;
		const { target } = record;
		const shaking = (t < Q.SHAKE && this.still === false) ? Math.sin(Math.PI * t / Q.SHAKE) : 0;
		const split = R.ease(t, Q.SPLIT);

		this.reveal(record, t);

		if(target.bed) {

			for(const chunk of record.chunks) {

				if(chunk.ground) {
					const tilt = this.quaternion.setFromAxisAngle(new THREE.Vector3(Math.cos(target.angle), 0, -Math.sin(target.angle)), chunk.side * .1 * split);
					chunk.position.copy(chunk.center).addScaledVector(chunk.away, .06 * split);
					chunk.position.y -= .02 * split;
					chunk.quaternion.copy(tilt);
					continue;
				}

				// Le plant tourne autour de son pied jusqu'à se coucher sur la terre
				const k = R.ease(t, [.3 + chunk.delay, 1.6 + chunk.delay]);
				const lay = new THREE.Quaternion().setFromAxisAngle(chunk.lay, 1.25 * k);
				const pivot = new THREE.Vector3(chunk.center.x, chunk.bottom, chunk.center.z);
				chunk.position.copy(chunk.center).sub(pivot).applyQuaternion(lay).add(pivot).addScaledVector(chunk.away, .06 * split);
				chunk.quaternion.copy(lay);

			}

			if(t > .2 && t < Q.SHAKE && now - record.dusted > 120) {
				record.dusted = now;
				const p = this.at(target, (this.random() - .5) * target.width, 0);
				this.dust.emit({ x: p.x, y: target.base + .1, z: p.z, vx: (this.random() - .5), vy: .6, vz: (this.random() - .5), gravity: .4, born: now, life: 1.4, from: .5, to: 1.6, color: R.DUSTY, alpha: .5 });
			}

			this.shadowStep(record, Math.floor(R.ease(t, [.3, 2.1]) * 3));
			return;

		}

		// L'élément penche autour de son pied, du côté où il tombera
		const lean = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(record.tilt.z, 0, -record.tilt.x), record.lean * R.ease(t, Q.TILT));
		const pivot = new THREE.Vector3(target.x, target.base, target.z).addScaledVector(record.tilt, record.span * .3);
		const jitter = .04 * Math.max(target.height, .5) * shaking;
		const shake = new THREE.Vector3((this.random() - .5) * jitter, 0, (this.random() - .5) * jitter);

		for(const chunk of record.chunks) {

			const standing = chunk.center.clone().sub(pivot).applyQuaternion(lean).add(pivot).addScaledVector(chunk.away, record.gap * split).add(shake);
			const k = R.ease(t, [Q.COLLAPSE[0] + chunk.delay * .5, Q.COLLAPSE[1] + chunk.delay * .5]);

			// Il tombe comme on tombe : lentement d'abord, de plus en plus vite
			chunk.position.set(standing.x + (chunk.rest.x - standing.x) * k, standing.y + (chunk.rest.y - standing.y) * k * k, standing.z + (chunk.rest.z - standing.z) * k);
			chunk.quaternion.slerpQuaternions(lean, chunk.fall, k);
			chunk.tint.copy(this.white).lerp(this.color.set(R.DUSTY), .3 * k);

		}

		if(t >= Q.COLLAPSE[0] && record.done.collapse === undefined) {

			record.done.collapse = true;

			for(let i = 0; i < 56; i++) {
				const a = this.random() * Math.PI * 2, speed = 1.5 + this.random() * 3.5;
				const p = this.at(target, (this.random() - .5) * target.width, (this.random() - .5) * target.depth);
				this.dust.emit({ x: p.x, y: target.base + .3, z: p.z, vx: Math.cos(a) * speed, vy: .4 + this.random(), vz: Math.sin(a) * speed, gravity: .8, born: now + this.random() * 500, life: 1.6 + this.random() * .8, from: 1, to: 3.2, color: R.DUSTY, alpha: .6 });
			}

		}

		// Un filet de poussière au pied pendant les secousses, puis sur les gravats
		if(t < Q.END - .8 && now - record.dusted > (t < Q.COLLAPSE[0] ? 150 : 320)) {
			record.dusted = now;
			const p = this.at(target, (this.random() - .5) * target.width, (this.random() - .5) * target.depth);
			this.dust.emit({ x: p.x, y: target.base + .2, z: p.z, vx: (this.random() - .5), vy: .5, vz: (this.random() - .5), gravity: .3, born: now, life: 1.5, from: .6, to: 1.8, color: R.DUSTY, alpha: .45 });
		}

		this.shadowStep(record, Math.floor(R.ease(t, [Q.TILT[0], Q.COLLAPSE[1] + .4]) * 4));

	}

	// Les fissures : des rayons brisés partis du bord d'un élément, ou deux lignes brisées le long d'une planche
	crack(target) {

		const R = MapSceneRuins;
		const lines = [];

		if(target.bed) {
			for(const b of [-.12, .14]) {
				const n = Math.max(4, Math.round(target.width / 2.5));
				const points = Array.from({ length: n + 1 }, (_, i) => this.at(target, (i / n - .5) * target.width * .96, b * target.depth + (this.random() - .5) * target.depth * .2));
				// Une fissure doit se voir au cadrage d'ouverture, où un mètre fait une vingtaine de pixels
				lines.push({ points, width: .18, lift: .003 });
			}
		} else {
			const rays = 6;
			const reach = Math.max(target.width, target.depth) * .5 + 2.5;
			for(let r = 0; r < rays; r++) {
				let a = (r + this.random() * .6) / rays * Math.PI * 2;
				const start = Math.max(target.width, target.depth) * .35;
				const points = [{ x: target.x + Math.cos(a) * start, z: target.z + Math.sin(a) * start }];
				for(let s = 1; s <= 4; s++) {
					a += (this.random() - .5) * .9;
					const last = points[points.length - 1];
					points.push({ x: last.x + Math.cos(a) * (reach - start) / 4, z: last.z + Math.sin(a) * (reach - start) / 4 });
				}
				lines.push({ points, width: .24, lift: .02 });
			}
		}

		const segments = [];

		for(const line of lines) {
			const n = line.points.length - 1;
			for(let i = 0; i < n && this.crackCount < R.CRACKS; i++) {
				const from = line.points[i], to = line.points[i + 1];
				segments.push({
					index: this.crackCount++,
					x: from.x, z: from.z, y: target.floor(from.x, from.z) + line.lift,
					dx: to.x - from.x, dy: target.floor(to.x, to.z) - target.floor(from.x, from.z), dz: to.z - from.z,
					width: line.width * (1 - .5 * i / n),
					at: i / n
				});
			}
		}

		// Une fissure grandit depuis rien : ses segments se posent à longueur nulle, et reveal() les allonge
		this.matrix.makeScale(0, 0, 0);
		for(const segment of segments) {
			this.cracks.setMatrixAt(segment.index, this.matrix);
		}
		this.cracks.count = this.crackCount;
		this.cracks.instanceMatrix.needsUpdate = true;

		return segments;

	}

	reveal(record, t) {

		const Q = MapSceneRuins.QUAKE;

		if(record.done.cracked) {
			return;
		}

		const k = Math.min(1, Math.max(0, (t - Q.CRACK[0]) / (Q.CRACK[1] - Q.CRACK[0])));

		for(const s of record.cracks) {
			const grow = Math.min(1, Math.max(0, (k - s.at) * 4));
			// Un segment se penche avec le sol entre ses deux bouts : tourné vers son but, puis levé autour de sa largeur
			const length = Math.hypot(s.dx, s.dy, s.dz);
			this.quaternion.setFromAxisAngle(this.up, Math.atan2(-s.dz, s.dx)).multiply(this.tilt.setFromAxisAngle(this.across, Math.atan2(s.dy, Math.hypot(s.dx, s.dz))));
			this.matrix.compose(new this.THREE.Vector3(s.x + s.dx * grow / 2, s.y + s.dy * grow / 2, s.z + s.dz * grow / 2), this.quaternion, new this.THREE.Vector3(Math.max(length * grow, 1e-4), 1, s.width));
			this.cracks.setMatrixAt(s.index, this.matrix);
		}

		this.cracks.instanceMatrix.needsUpdate = true;

		if(k >= 1) {
			record.done.cracked = true;
		}

	}

	shadowStep(record, step) {
		if(step !== record.step) {
			record.step = step;
			this.shadow();
		}
	}

	// Une bouffée de fumée au-dessus de la cible, à la hauteur des flammes
	puff(target, now, size, rise, dark) {

		const p = this.puffList[this.puffNext];
		this.puffNext = (this.puffNext + 1) % MapSceneRuins.PUFFS;

		const { x, z } = this.at(target, (this.random() - .5) * target.width * (target.bed ? .9 : .5), (this.random() - .5) * target.depth * .5);

		p.born = now;
		p.x = x;
		p.z = z;
		// Au-dessus des flammes pendant le feu, au ras des cendres ensuite
		p.y = target.base + (target.bed ? 1.2 : target.height * 1.1) * rise;
		p.size = size * (.7 + this.random() * .6);
		p.rise = MapSceneRuins.PUFF_RISE * (.6 + rise * .6);
		p.dark = dark;
		p.seed = this.random() * 10;

	}

	mound(spots, floor, height, color) {
		for(const spot of spots) {
			if(this.moundCount >= MapSceneRuins.MOUNDS) {
				break;
			}
			this.matrix.compose(new this.THREE.Vector3(spot.x, floor(spot.x, spot.z), spot.z), this.quaternion.setFromAxisAngle(this.up, this.random() * Math.PI), new this.THREE.Vector3(spot.radius, spot.radius * height, spot.radius));
			this.mounds.setMatrixAt(this.moundCount, this.matrix);
			this.mounds.setColorAt(this.moundCount, this.color.set(color));
			this.moundCount++;
		}
		this.mounds.count = this.moundCount;
		this.mounds.instanceMatrix.needsUpdate = true;
		this.mounds.instanceColor.needsUpdate = true;
	}

	frame(now) {

		const R = MapSceneRuins;
		const count = { flames: 0, glows: 0, embers: 0, bombs: 0 };
		let shake = 0;

		for(const { target, glow, tremble } of this.marks.values()) {

			this.spread(this.glows, count.glows++, target, (target.width * .5 + 1) * glow, (target.depth * .5 + 1) * glow, this.color.set(R.MARK).multiplyScalar(.14 * glow));

			// La copie frémit de plus en plus fort à mesure que l'appui avance
			const shatter = this.prepared.get(target.key);

			if(shatter !== undefined) {
				const amount = this.still ? 0 : tremble * R.clamp(.025 * Math.max(target.width, target.depth), R.TREMBLE);
				shatter.mesh.position.set(Math.sin(now / 21) * amount, 0, Math.cos(now / 27) * amount);
			}

		}

		// Une copie suit l'affichage du calque de son original
		for(const shatter of [...this.prepared.values(), ...[...this.records.values(), ...this.ruined.values()].map(record => record.shatter)]) {
			shatter.mesh.visible = shatter.target.shown?.() ?? true;
		}

		for(const [key, record] of this.records) {

			const t = (now - record.started) / 1000;

			({ burn: () => this.stepBurn(record, t, now, count), bomb: () => this.stepBomb(record, t, now, count), quake: () => this.stepQuake(record, t, now) })[record.kind]();
			this.write(record.shatter);

			if(record.kind === 'bomb' && record.shakeAt !== undefined) {
				const since = (now - record.shakeAt) / 1000;
				if(since < .55) {
					shake = Math.max(shake, .6 * (1 - since / .55) ** 2);
				}
			}

			if(record.kind === 'quake' && t < R.QUAKE.SHAKE) {
				shake = Math.max(shake, .2 * Math.sin(Math.PI * t / R.QUAKE.SHAKE));
			}

			if(t >= R[record.kind.toUpperCase()].END) {
				this.records.delete(key);
				this.ruined.set(key, record);
				this.shadow();
			}

		}

		// La secousse se lit dans frame() et s'ajoute à la caméra juste le temps du rendu
		if(this.still || shake === 0) {
			this.shake.set(0, 0, 0);
		} else {
			this.shake.set(this.random() - .5, (this.random() - .5) * .4, this.random() - .5).multiplyScalar(2 * shake);
		}

		// L'horloge des points est celle de frame(), pas performance.now() : une image figée ou rejouée garde ses points
		this.sparkMaterial.uniforms.uTime.value = now / 1000;
		this.glints.frame(now);
		this.dust.frame(now);

		let puffs = 0;

		for(const p of this.puffList) {

			const age = (now - p.born) / 1000 / R.PUFF_LIFE;

			if(age < 0 || age >= 1) {
				continue;
			}

			// Elle grossit en montant, dérive avec le vent, puis fond sur place
			const grow = Math.sin(Math.PI * Math.min(1, age * 1.25)) * p.size * (1 + age);
			this.color.set(R.SMOKE_DARK).lerp(new this.THREE.Color(R.SMOKE_LIGHT), Math.min(1, age * 1.4 + (1 - p.dark)));
			this.place(this.puffs, puffs++, p.x + R.WIND.x * age * 2, p.y + p.rise * age, p.z + R.WIND.z * age * 2, p.seed + age, grow, grow * .8, grow, this.color);

		}

		this.flames.count = count.flames;
		this.glows.count = count.glows;
		this.embers.count = count.embers;
		this.bombs.count = count.bombs;
		this.puffs.count = puffs;

		for(const mesh of [this.flames, this.glows, this.puffs, this.embers, this.bombs]) {
			mesh.instanceMatrix.needsUpdate = true;
			mesh.instanceColor.needsUpdate = true;
		}

	}

	spark(record, now, size) {

		const R = MapSceneRuins;
		const { target } = record;
		const due = Math.floor((now - record.sparked) / 1000 * R.SPARK_RATE * Math.min(4, Math.max(1, target.width * target.depth / 20)));

		if(due <= 0) {
			return;
		}

		record.sparked = now;

		const position = this.sparkGeometry.attributes.position;
		const hue = this.sparkGeometry.attributes.hue;
		const tall = R.clamp(target.height * .75, R.TONGUE_HEIGHT);

		for(let i = 0; i < Math.min(due, 60); i++) {

			const k = this.sparkNext;
			this.sparkNext = (this.sparkNext + 1) % R.SPARKS;

			const { x, z } = this.at(target, (this.random() - .5) * target.width * .8, (this.random() - .5) * target.depth * .8);

			position.setXYZ(k, x, target.base + tall * .5 * this.random(), z);
			// Départ, montée, taille, graine
			this.sparkList.set([now / 1000, tall * (1 + this.random()) * size, .12 + this.random() * .12, this.random()], k * 4);
			hue.setX(k, this.random());

		}

		position.needsUpdate = true;
		hue.needsUpdate = true;
		this.sparkGeometry.attributes.spark.needsUpdate = true;

	}

	place(mesh, index, x, y, z, turn, sx, sy, sz, color) {
		this.matrix.compose(new this.THREE.Vector3(x, y, z), this.quaternion.setFromAxisAngle(this.up, turn), new this.THREE.Vector3(Math.max(sx, 1e-4), Math.max(sy, 1e-4), Math.max(sz, 1e-4)));
		mesh.setMatrixAt(index, this.matrix);
		mesh.setColorAt(index, color);
	}

	/**
	 * Une nappe de `sx` sur `sz` mètres couchée sur le sol d'une cible, penchée comme lui d'un bord à l'autre de son emprise : une
	 * lueur s'étend sur toute une planche, et posée de niveau au sol de son centre, elle sortirait du sol à un bout d'une pente
	 * et s'y enfoncerait à l'autre. Une nappe reste plane, puisque toutes les lueurs sont les instances d'un même disque : sur une
	 * planche bombée, elle passe sous la bosse ou au-dessus du creux de ce que le sol s'écarte de la droite entre ses deux bouts.
	 */
	spread(mesh, index, target, sx, sz, color) {

		const T = this.THREE;
		const floor = (a, b) => {
			const { x, z } = this.at(target, a, b);
			return target.floor(x, z);
		};

		const halfWidth = target.width / 2;
		const halfDepth = target.depth / 2;
		const across = new T.Vector3(Math.cos(target.angle), (floor(halfWidth, 0) - floor(-halfWidth, 0)) / target.width, -Math.sin(target.angle)).normalize();
		const along = new T.Vector3(Math.sin(target.angle), (floor(0, halfDepth) - floor(0, -halfDepth)) / target.depth, Math.cos(target.angle));
		const up = along.clone().cross(across).normalize();

		along.crossVectors(across, up);

		this.matrix.makeBasis(across, up, along).scale(new T.Vector3(Math.max(sx, 1e-4), 1, Math.max(sz, 1e-4))).setPosition(target.x, target.floor(target.x, target.z) + MapSceneRuins.SPREAD_LIFT, target.z);
		mesh.setMatrixAt(index, this.matrix);
		mesh.setColorAt(index, color);

	}

	/**
	 * Tout revient, d'un coup, comme au rechargement : avant d'entrer dans la pose du décor, où un élément masqué partirait à
	 * l'enregistrement.
	 */
	reset() {

		for(const record of [...this.records.values(), ...this.ruined.values()]) {
			this.dispose3d(record.shatter);
			record.target.show();
		}

		for(const key of [...this.prepared.keys()]) {
			this.forget(key);
		}

		this.marks.clear();
		this.records.clear();
		this.ruined.clear();
		this.moundCount = 0;
		this.crackCount = 0;
		this.mounds.count = 0;
		this.cracks.count = 0;
		this.puffList.forEach(p => {
			p.born = -1e9;
		});
		this.sparkList.fill(-10);
		this.sparkGeometry.attributes.spark.needsUpdate = true;
		this.glints.clear();
		this.dust.clear();
		this.shake.set(0, 0, 0);
		this.shadow();

	}

	dispose3d({ mesh, texture }) {
		this.parent.remove(mesh);
		mesh.geometry.dispose();
		mesh.material.dispose();
		mesh.customDepthMaterial.dispose();
		texture.dispose();
	}

	// La scène se ferme : rien ne se rend aux originaux, qu'elle libère avec elle
	dispose() {

		for(const shatter of [...this.prepared.values(), ...[...this.records.values(), ...this.ruined.values()].map(record => record.shatter)]) {
			this.dispose3d(shatter);
		}

		this.parent.remove(this.sparkPoints);
		this.sparkGeometry.dispose();
		this.sparkMaterial.dispose();
		this.glints.dispose();
		this.dust.dispose();

		for(const mesh of [this.flames, this.glows, this.puffs, this.embers, this.mounds, this.cracks, this.bombs]) {
			this.parent.remove(mesh);
			mesh.geometry.dispose();
			mesh.material.dispose();
			mesh.dispose();
		}

	}

}

/**
 * Un nuage de points dont chaque point porte son départ, sa vitesse, sa durée, sa taille et sa teinte : tout son mouvement se
 * calcule dans le nuanceur, comme MapSceneSparks, et le processeur n'écrit qu'à l'émission. Le flash, la boule de feu et l'onde
 * de la bombe s'ajoutent à la lumière ; la poussière et la fumée la masquent.
 */
class MapSceneRuinsMotes {

	constructor({ THREE, parent, count, additive, max }) {

		this.THREE = THREE;
		this.parent = parent;
		this.count = count;
		this.next = 0;
		this.color = new THREE.Color();

		this.geometry = new THREE.BufferGeometry();
		this.geometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(count * 3), 3));
		this.geometry.setAttribute('motion', new THREE.BufferAttribute(new Float32Array(count * 4), 4));
		this.span = new Float32Array(count * 4).fill(-1e6);
		this.geometry.setAttribute('span', new THREE.BufferAttribute(this.span, 4));
		this.geometry.setAttribute('tint', new THREE.BufferAttribute(new Float32Array(count * 4), 4));

		this.material = new THREE.ShaderMaterial({
			transparent: true,
			depthWrite: false,
			blending: additive ? THREE.AdditiveBlending : THREE.NormalBlending,
			uniforms: { uTime: { value: 0 }, uPixels: { value: 1 }, uMax: { value: max } },
			vertexShader: `
				attribute vec4 motion;
				attribute vec4 span;
				attribute vec4 tint;
				uniform float uTime, uPixels, uMax;
				varying vec4 vTint;
				varying float vFade;
				void main() {
					float t = (uTime - span.x) / span.y, alive = step(0., t) * step(t, 1.);
					float s = t * span.y;
					vec3 p = position + motion.xyz * s;
					p.y = max(p.y - .5 * motion.w * s * s, .05);
					vTint = tint;
					vFade = alive * (1. - t) * min(1., t * 10.);
					gl_Position = projectionMatrix * modelViewMatrix * vec4(p, 1.);
					gl_PointSize = min(uMax, alive * mix(span.z, span.w, t) * uPixels);
				}`,
			fragmentShader: `
				varying vec4 vTint;
				varying float vFade;
				void main() {
					float d = length(gl_PointCoord - .5);
					gl_FragColor = vec4(vTint.rgb, vTint.a * vFade * smoothstep(.5, ${additive ? '.05' : '.3'}, d));
				}`
		});

		this.points = new THREE.Points(this.geometry, this.material);
		this.points.frustumCulled = false;
		this.points.renderOrder = additive ? 32 : 30;
		this.points.onBeforeRender = (renderer, scene, camera) => {
			this.material.uniforms.uPixels.value = MapSceneRuins.pixels(renderer, camera);
		};
		parent.add(this.points);

	}

	// Un point, ses tailles en mètres au départ et à la fin, sa durée en secondes
	emit({ x, y, z, vx = 0, vy = 0, vz = 0, gravity = 0, born, life, from, to, color, alpha }) {

		const k = this.next;
		this.next = (this.next + 1) % this.count;

		this.color.set(color);
		this.geometry.attributes.position.setXYZ(k, x, y, z);
		this.geometry.attributes.motion.setXYZW(k, vx, vy, vz, gravity);
		this.geometry.attributes.span.setXYZW(k, born / 1000, life, from, to);
		this.geometry.attributes.tint.setXYZW(k, this.color.r, this.color.g, this.color.b, alpha);

		for(const name of ['position', 'motion', 'span', 'tint']) {
			this.geometry.attributes[name].needsUpdate = true;
		}

	}

	frame(now) {
		this.material.uniforms.uTime.value = now / 1000;
	}

	clear() {
		this.span.fill(-1e6);
		this.geometry.attributes.span.needsUpdate = true;
	}

	dispose() {
		this.parent.remove(this.points);
		this.geometry.dispose();
		this.material.dispose();
	}

}

/**
 * Le son d'un effet, synthétisé par Web Audio au moment du choix, sans fichier : le crépitement du feu, le sifflement puis la
 * détonation sourde de la bombe, le grondement du séisme. Chacun suit les phases de son effet (`MapSceneRuins`). Le contexte
 * s'ouvre au premier choix, qui est un geste : un navigateur refuse le son à une page qui ne l'a pas reçu.
 */
class MapSceneRuinsSound {

	// -18 dB : un son d'appoint, qui ne couvre pas ce que l'on écoute à côté
	static GAIN = Math.pow(10, -18 / 20);

	constructor() {
		this.context = null;
		this.buffers = new Map();
	}

	play(kind) {

		const Context = window.AudioContext ?? window.webkitAudioContext;

		if(Context === undefined) {
			return;
		}

		if(this.context === null) {
			this.context = new Context();
			this.master = this.context.createGain();
			this.master.gain.value = MapSceneRuinsSound.GAIN;
			this.master.connect(this.context.destination);
		}

		this.context.resume();

		const at = this.context.currentTime + .02;

		({ burn: () => this.burn(at), bomb: () => this.bomb(at), quake: () => this.quake(at) })[kind]();

	}

	/**
	 * Un bruit de `seconds` secondes : blanc, brun (le blanc intégré, sourd comme un grondement) ou crépitant (des claquements épars,
	 * chacun une courte rafale qui s'éteint). Il se calcule une fois par sorte.
	 */
	noise(kind, seconds) {

		const key = kind + seconds;

		if(this.buffers.has(key) === false) {

			const rate = this.context.sampleRate;
			const buffer = this.context.createBuffer(1, Math.round(seconds * rate), rate);
			const data = buffer.getChannelData(0);
			const random = MapSceneRuins.seeded(seconds * 1000);
			let last = 0;
			let crack = 0;

			for(let i = 0; i < data.length; i++) {

				const white = random() * 2 - 1;

				if(kind === 'brown') {
					last = (last + .02 * white) / 1.02;
					data[i] = last * 3.5;
				} else if(kind === 'crackle') {
					if(random() < 12 / rate * 60) {
						crack = .4 + random() * .6;
					}
					crack *= .9965;
					data[i] = white * crack;
				} else {
					data[i] = white;
				}

			}

			this.buffers.set(key, buffer);

		}

		return this.buffers.get(key);

	}

	// Une source de bruit filtrée, posée de `at` à `at + seconds`, et le gain qui en tient l'enveloppe
	source(kind, seconds, filter, frequency, at) {

		const source = this.context.createBufferSource();
		source.buffer = this.noise(kind, seconds);

		const shape = this.context.createBiquadFilter();
		shape.type = filter;
		shape.frequency.value = frequency;

		const gain = this.context.createGain();
		gain.gain.setValueAtTime(0, at);

		source.connect(shape).connect(gain).connect(this.master);
		source.start(at);
		source.stop(at + seconds);

		return gain.gain;

	}

	burn(at) {

		const B = MapSceneRuins.BURN;

		const crackle = this.source('crackle', B.END, 'bandpass', 2600, at);
		crackle.linearRampToValueAtTime(.9, at + B.GROW);
		crackle.setValueAtTime(.9, at + B.BLAZE);
		crackle.linearRampToValueAtTime(.25, at + B.OUT);
		crackle.linearRampToValueAtTime(0, at + B.END);

		const roar = this.source('brown', B.END, 'lowpass', 420, at);
		roar.linearRampToValueAtTime(.7, at + B.GROW);
		roar.setValueAtTime(.7, at + B.BLAZE);
		roar.linearRampToValueAtTime(0, at + B.OUT + .6);

	}

	bomb(at) {

		const B = MapSceneRuins.BOMB;
		const hit = at + B.FALL;

		const whistle = this.context.createOscillator();
		const whistleGain = this.context.createGain();
		whistle.frequency.setValueAtTime(1400, at);
		whistle.frequency.exponentialRampToValueAtTime(520, hit);
		whistleGain.gain.setValueAtTime(0, at);
		whistleGain.gain.linearRampToValueAtTime(.22, at + .15);
		whistleGain.gain.setValueAtTime(.22, hit - .03);
		whistleGain.gain.linearRampToValueAtTime(0, hit);
		whistle.connect(whistleGain).connect(this.master);
		whistle.start(at);
		whistle.stop(hit);

		const blast = this.source('brown', 2.6, 'lowpass', 700, hit);
		blast.linearRampToValueAtTime(1, hit + .01);
		blast.exponentialRampToValueAtTime(.001, hit + 2.5);

		const thump = this.context.createOscillator();
		const thumpGain = this.context.createGain();
		thump.frequency.setValueAtTime(75, hit);
		thump.frequency.exponentialRampToValueAtTime(32, hit + .5);
		thumpGain.gain.setValueAtTime(0, hit);
		thumpGain.gain.linearRampToValueAtTime(1, hit + .01);
		thumpGain.gain.exponentialRampToValueAtTime(.001, hit + .9);
		thump.connect(thumpGain).connect(this.master);
		thump.start(hit);
		thump.stop(hit + 1);

	}

	quake(at) {

		const Q = MapSceneRuins.QUAKE;

		const rumble = this.source('brown', Q.END, 'lowpass', 130, at);
		rumble.linearRampToValueAtTime(1, at + Q.SHAKE / 2);
		rumble.linearRampToValueAtTime(.4, at + Q.SHAKE);
		rumble.linearRampToValueAtTime(0, at + Q.END);

		const collapse = this.source('brown', 2, 'lowpass', 380, at + Q.COLLAPSE[0]);
		collapse.linearRampToValueAtTime(.8, at + Q.COLLAPSE[0] + .05);
		collapse.exponentialRampToValueAtTime(.001, at + Q.COLLAPSE[0] + 1.9);

	}

	dispose() {
		this.context?.close();
	}

}

/**
 * L'appui long. Un appui sur une cible doit se tenir HOLD millisecondes sans bouger de plus de MapScene.CLICK_PX pixels :
 * la cible s'éclaire au sol dès GLOW, frémit dès TREMBLE, de plus en plus fort, et les trois pastilles Feu, Bombe et Séisme
 * s'ouvrent au point d'appui. Relâcher, glisser, poser un second doigt, tourner la molette ou quitter la scène l'arrête et rend la
 * cible immobile. Un appui ailleurs, la molette ou Échap referment les pastilles sans rien faire. Au doigt, les pastilles se posent
 * au-dessus de l'appui pour que le doigt ne les cache pas.
 */
class MapSceneHold {

	static HOLD = 4000;
	static GLOW = 1500;
	static TREMBLE = 2000;
	static ABOVE_FINGER = 64;
	static GAP = 20;

	constructor({ container, canvas, tools, bar, timeline, aim, measure, mark, prepare, forget, choose, enabled }) {

		this.container = container;
		this.canvas = canvas;
		this.tools = tools;
		this.bar = bar;
		this.timeline = timeline;
		this.aim = aim;
		this.measure = measure;
		this.mark = mark;
		this.prepare = prepare;
		this.forget = forget;
		this.choose = choose;
		this.enabled = enabled;
		this.hold = null;
		this.choice = null;
		this.frame = null;
		this.pointers = new Set();

		this.listeners = [
			[canvas, 'pointerdown', event => this.down(event)],
			[canvas, 'pointermove', event => this.move(event)],
			[canvas, 'pointerup', event => this.up(event)],
			[canvas, 'pointercancel', event => this.up(event)],
			[canvas, 'pointerleave', event => this.up(event)],
			[canvas, 'wheel', () => this.cancel(), { passive: true }],
			// Un appui hors des pastilles les referme ; sur le canevas, il ne fait rien d'autre, ni clic de fiche ni début de glissé
			[container, 'pointerdown', event => this.outside(event), { capture: true }],
			[tools, 'click', event => this.pick(event)],
			[document, 'keydown', event => {
				if(event.key === 'Escape') {
					this.cancel();
				}
			}]
		];

		this.listeners.forEach(([node, name, listener, options]) => node.addEventListener(name, listener, options));

	}

	/**
	 * Où en est un appui tenu `elapsed` millisecondes : l'éclat de la cible et la force du frémissement, de 0 à 1, et s'il ouvre
	 * les pastilles.
	 */
	static progress(elapsed) {

		const H = MapSceneHold;

		return {
			glow: Math.min(1, Math.max(0, (elapsed - H.GLOW) / (H.HOLD - H.GLOW))),
			tremble: Math.min(1, Math.max(0, (elapsed - H.TREMBLE) / (H.HOLD - H.TREMBLE))),
			open: (elapsed >= H.HOLD)
		};

	}

	down(event) {

		this.pointers.add(event.pointerId);

		// Le clic droit et le clic molette font tourner ou glisser la vue
		if(this.enabled() === false || event.button !== 0 || this.choice !== null) {
			return;
		}

		// Un second doigt pince ou fait tourner la vue : ce n'est plus un appui
		if(this.pointers.size > 1) {
			this.stop();
			return;
		}

		const target = this.aim(event);

		if(target === null) {
			return;
		}

		this.hold = { target, x: event.clientX, y: event.clientY, touch: (event.pointerType === 'touch'), at: performance.now(), measured: false, prepared: false };
		this.tick();

	}

	move(event) {
		if(this.hold !== null && Math.hypot(event.clientX - this.hold.x, event.clientY - this.hold.y) > MapScene.CLICK_PX) {
			this.stop();
		}
	}

	up(event) {
		this.pointers.delete(event.pointerId);
		this.stop();
	}

	outside(event) {

		if(this.choice === null || this.tools.contains(event.target)) {
			return;
		}

		this.cancel();

		if(event.target === this.canvas) {
			event.stopPropagation();
		}

	}

	/**
	 * Les pastilles au point d'appui, relevé au-dessus du doigt, et écartées de lui de GAP pixels : centrées sur le point, entre la
	 * barre et la frise, comme MapSceneDecor borne ses outils ; sans place au-dessus, elles passent dessous.
	 */
	place() {

		const node = this.tools;
		const box = this.container.getBoundingClientRect();
		const lift = this.hold.touch ? MapSceneHold.ABOVE_FINGER : 0;
		const gap = MapSceneHold.GAP;
		const width = node.offsetWidth, height = node.offsetHeight;
		const margin = parseFloat(getComputedStyle(document.documentElement).fontSize);
		const canvas = this.canvas.getBoundingClientRect();
		const bar = this.bar.getBoundingClientRect(), timeline = this.timeline.getBoundingClientRect();
		const top = Math.max(canvas.top, bar.bottom) - box.top + margin;
		const bottom = Math.min(canvas.bottom, (timeline.top > canvas.top) ? timeline.top : Infinity) - box.top - height - margin;
		const x = this.hold.x - box.left;
		const y = this.hold.y - box.top - lift;

		node.style.left = Math.min(Math.max(margin, x - width / 2), box.width - width - margin) +'px';
		node.style.top = Math.min(Math.max(top, (y - height - gap < top) ? y + gap : y - height - gap), bottom) +'px';

	}

	tick() {

		if(this.hold === null) {
			return;
		}

		const elapsed = performance.now() - this.hold.at;
		const { glow, tremble, open } = MapSceneHold.progress(elapsed);

		if(glow > 0 && this.hold.measured === false) {
			this.hold.measured = true;
			this.measure(this.hold.target);
		}

		if(tremble > 0 && this.hold.prepared === false) {
			this.hold.prepared = true;
			this.prepare(this.hold.target);
		}

		this.mark(this.hold.target, glow, tremble);

		if(open) {
			this.open();
			return;
		}

		this.frame = requestAnimationFrame(() => this.tick());

	}

	open() {

		this.choice = this.hold.target;

		this.tools.classList.remove('hide');
		this.place();
		this.hold = null;

	}

	pick(event) {

		const tool = event.target.closest('[data-ruin]');

		if(tool === null || this.choice === null) {
			return;
		}

		const target = this.choice;
		this.choice = null;
		this.tools.classList.add('hide');
		this.choose(tool.dataset.ruin, target);

	}

	stop() {

		cancelAnimationFrame(this.frame);

		if(this.hold === null) {
			return;
		}

		this.mark(this.hold.target, 0, 0);

		if(this.hold.prepared) {
			this.forget(this.hold.target);
		}

		this.hold = null;

	}

	cancel() {

		this.stop();

		if(this.choice !== null) {
			this.mark(this.choice, 0, 0);
			this.forget(this.choice);
			this.choice = null;
			this.tools.classList.add('hide');
		}

	}

	// La scène se ferme : les écouteurs partent, rien ne se rend à une scène qui se libère
	dispose() {
		cancelAnimationFrame(this.frame);
		this.listeners.forEach(([node, name, listener, options]) => node.removeEventListener(name, listener, options));
	}

}

document.addEventListener('navigation.sleep', () => MapScene.stop());
