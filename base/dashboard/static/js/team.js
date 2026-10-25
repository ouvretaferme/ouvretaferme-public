/**
 * L'équipe vivante de la tête de l'accueil (A8 de 3d-avatars, planche v3, direction C) : les membres en pied, chacun
 * monté depuis la fiche de son avatar par la fabrique du socle (`SceneLoader.loadAvatar()`), font ce que fait une
 * équipe entre deux tâches, sans faire semblant de travailler (arbitrage 33) : on se tient là, on se parle, on salue.
 * Le matin, le café, la tasse en main. Le jour, les mains vides, on se parle deux à deux. Le soir, la fatigue, puis on
 * salue. Chacun reste à sa place et garde son cap, la tête seule se tourne (arbitrage 34). Le rang n'est pas une file :
 * chacun a son écart, sa profondeur et son attitude de repos, à laquelle il revient après chaque geste (arbitrage 35).
 *
 * Le moment se lit à l'heure de l'appareil et non du serveur : c'est la journée de qui regarde. La personne connectée
 * prend la place la plus à droite, près de la roue ; un membre sans avatar prend le corps commun, peint en gris, et fait
 * les gestes du moment comme les autres. La tête garde autant de membres que la planche v3 à la même largeur, quatre au
 * plus sous 992 px, où l'équipe passe sous le champ de saisie et repousse le premier contenu utile. Faute de place pour
 * leurs gestes, les membres se serrent jusqu'à se toucher, puis rapetissent, mais ne perdent jamais un bout de corps.
 *
 * Un membre qui a téléversé son modèle le montre à la place de sa composition, et fait les mêmes gestes : ce sont des
 * angles d'os du corps commun, que le modèle partage. Il ne prend pas la tasse, que sa main n'est pas faite pour tenir,
 * ni les traits de la composition, qu'il ne porte pas.
 *
 * Qui est absent toute la journée se tient au second plan, derrière le rang, plus petit, pâli et immobile (planche v6) :
 * la liste des absences, à côté, le nomme, et la scène le montre. Il ne prend ni geste ni objet du moment.
 *
 * Une seule équipe vit à la fois, dans un seul contexte WebGL. Elle se libère sur `navigation.sleep` : en navigation
 * ajax, la page suivante ne recharge pas le document, et un contexte oublié tiendrait la carte graphique.
 */
class DashboardTeam {

	static current = null;

	// Le dégradé du matériau toon, la lumière et le gris du corps commun : ceux de l'aperçu de l'éditeur d'avatar
	// (`UserAvatarEditor`) et de la planche v1
	static RAMP = [120, 160, 200, 235, 255];
	static GREY = '#c8c9cc';

	// Un gris d'un seul ton efface le visage, et un corps sans visage se lit de dos (arbitrage 30) : le gris garde la
	// part GREY_RELIEF de la clarté des couleurs du corps commun, assez pour qu'yeux, bouche et cheveux se détachent de la peau
	static GREY_RELIEF = .4;

	// Le second plan garde les couleurs de la personne, là où le corps commun est peint en gris : pâlir sans griser,
	// c'est ce qui sépare un absent d'un membre sans avatar
	static AWAY_OPACITY = .35;

	// Le cadrage en pied, de la planche v3 : les pieds sur le filet, la caméra de 22° un peu plongeante. Un membre tient
	// STEP mètres de rang, la place des bras levés de Joyeux, et HEIGHT est la taille du corps commun
	static FOV = 22;
	static PITCH = .08;
	static STEP = .62;
	static HEIGHT = 1.75;

	// Le haut du cadre, en coordonnées du canevas : la marge que garde le chapeau du plus grand
	static TOP = .96;

	// Le rang se resserre jusqu'à STEP_TIGHT pour garder ses gestes dans le cadre sans rapetisser les corps : les membres
	// se touchent alors, épaule contre épaule. Plus serrés, ils se cacheraient l'un l'autre
	static STEP_TIGHT = .5;

	// Le rang n'est pas une file (arbitrage 35) : chaque place a son écart à son voisin de gauche, en multiple de l'écart
	// du rang, sa profondeur en mètres, son cap et son attitude de repos, les mêmes à chaque chargement. Aucun écart sous
	// 1,1 : à STEP_TIGHT deux corps se touchent déjà, et la perspective rapproche à l'écran celui qui se tient plus loin
	// derrière son voisin. Trois attitudes en cycle, pour que deux voisins n'aient jamais la même, et toutes du socle :
	// debout, pensif, et les poings sur les hanches de la fierté
	static RANK_GAP = [1.3, 1.2, 1.55, 1.1, 1.4];
	static RANK_DEPTH = [0, -.38, -.1, -.48, -.2];
	static RANK_YAW = [.25, .05, .3, -.1, .15];
	static RANK_ATTITUDES = ['emotion:pensif', 'stand', 'emotion:fier'];

	// Les mètres que prennent les bras du premier et du dernier membre au-delà de leurs épaules, comptés une fois pour tout le rang
	static ARMS = .6;

	// Aucun corps ne pivote : la scène orne la tête et ne doit pas attirer l'œil (arbitrage 34). Chacun garde le cap de sa
	// place (RANK_YAW, AWAY_YAW), de face ou de trois quarts, jamais au-delà de .65 radian, où il se lirait de profil.
	// Parler à son voisin ne tourne que la tête, de LOOK radians, et le regard rejoint sa cible en LOOK_EASE secondes
	// environ, sans à-coup
	static LOOK = .3;
	static LOOK_EASE = 1.5;

	// Le second plan, de la planche v6 : trois absents au plus, à BACK mètres derrière le rang et réduits de BACK_SCALE,
	// pour qu'on les lise derrière et non à côté. Chacun prend AWAY_ROOM d'une place au rang, qui garde RANK_MIN présents
	// au moins (arbitrage 28) : le nombre et la taille des corps sont ceux que la planche montre pour le même cas
	static AWAY_MAX = 3;
	static AWAY_ROOM = .6;
	static RANK_MIN = 2;
	static BACK = 1.8;
	static BACK_SCALE = .82;

	// La taille sous laquelle le second plan ne se resserre plus quand il ne tient pas à côté du rang : au-delà, c'est le
	// rang qui rapetisse (arbitrage 32). Plus petit, un absent ne ferait plus la moitié d'un présent à l'écran, quand la
	// planche v6 lui en donne les deux tiers, et le second plan est ce que la scène doit faire lire (arbitrage 28)
	static AWAY_SCALE_MIN = .7;

	// Chaque absent à sa façon (arbitrage 31), d'après son rang pour que deux chargements montrent la même scène : le
	// cap tourné vers le rang, vers nous ou vers son voisin, et un quinconce de profondeur, en mètres
	static AWAY_YAW = [.45, .05, .65];
	static AWAY_DEPTH = [0, -.3, -.12];

	// Les attitudes du second plan, tenues chacune de AWAY_HOLD secondes avant d'en changer, un absent à la fois. Deux
	// voisins n'ont jamais la même, et le premier rang de la liste est celui de l'image fixe. Pas la joie : ses deux bras
	// levés se lisent « haut les mains » au second plan (arbitrage 31 bis)
	static AWAY_ATTITUDES = ['emotion:pensif', 'stand', 'emotion:fatigue', 'emotion:fier', 'emotion:surpris'];
	static AWAY_HOLD = [6, 10];

	// L'écart qui sépare à l'écran un absent du second plan du présent le plus à gauche, en fraction de la demi-largeur du
	// canevas : aucun présent ne le cache
	static AWAY_GAP = .02;

	// Ce qui s'ajoute à AWAY_GAP entre un absent et son voisin de droite, en fraction de sa taille : un écart irrégulier,
	// qui ne fait qu'éloigner, et la place d'un poing sur la hanche ou d'une main portée au visage à côté d'un voisin
	static AWAY_SPACING = [0, .1, .05];
	static AWAY_PASSES = 5;

	// Sous 992 px, l'équipe passe sous le champ de saisie : quatre au plus, pour ne pas repousser ce qui suit
	static NARROW = '(max-width: 991px)';
	static NARROW_CAP = 4;

	// En secondes, repris de l'aperçu de l'éditeur : le passage d'un geste à l'autre, le clignement
	static BLEND = .6;
	static BLINK = .15;
	static BLINK_EVERY = [3, 6];

	// Le balancement d'un corps sur ses pieds au repos, en radians de part et d'autre de la verticale
	static SWAY = .02;

	// Le temps que le prénom reste affiché après l'appui du doigt, qui n'a pas de survol, en millisecondes
	static NAME_TAP = 2500;

	// Ce que chaque moment occupe autour de la place d'un membre, en fraction de la taille du corps, sur toute la durée
	// de ses gestes et de ses attitudes de repos comme dans l'image fixe, à tous les caps du rang et la tête tournée : la
	// tasse portée à la bouche le matin, le salut du soir tendu vers nous, la visière qui s'avance quand la fatigue plie
	// le buste. Le jour prend la boite du matin, dont il a les gestes sans la tasse. `low` est la profondeur de ce qui
	// reste près du sol, les pieds : la caméra plonge, et un sol cadré aussi profond que les bras tendus décollerait les
	// pieds du filet. `floor` est ce que les semelles descendent sous le sol quand le genou plie. Un corps qui sortirait
	// de ces boites serait tranché par le bord du canevas. Mesurées sur le corps commun et un avatar coiffé d'une
	// casquette, avec trois centièmes de marge pour une coiffure plus large
	static REACH = {
		matin: { x: [-.39, .41], z: [-.43, .4], low: [-.2, .2], floor: 0 },
		jour: { x: [-.39, .41], z: [-.43, .4], low: [-.2, .2], floor: 0 },
		soir: { x: [-.48, .42], z: [-.42, .56], low: [-.28, .28], floor: -.02 }
	};

	// Ce qu'occupe un corps les bras le long du corps, ce que getHidden() écarte l'un de l'autre
	static BODY = [-.2, .2];

	// La hauteur, en fraction de la taille du corps, sous laquelle on est près du sol
	static REACH_LOW = .25;

	// L'objet que le moment met en main gauche, une entrée du catalogue : la fiche n'en porte aucun
	// (`UserAvatarLib::getFiche()`), et deux objets dans la même main ne se tiendraient pas
	static OBJECTS = { matin: 'tasse' };

	// Les gestes animés du socle (`ANIMATIONS` d'avatar.js), par le nom que leur donne cette scène
	static ANIMATIONS = { sip: 'boire', talk: 'parler', wave: 'saluer' };

	// Le temps d'un cycle de l'animation `parler`, que tient un geste de parole
	static TALK = 4.8;

	// Boire porte la tasse à une bouche que seule la composition place : un modèle téléversé parle à la place
	static UPLOAD_GESTURES = { sip: ['talk', DashboardTeam.TALK] };

	// Les gestes de chaque moment, chacun avec le temps qu'il dure : un cycle entier de son animation. Le café le matin,
	// le salut le soir, et le jour entre les deux, sans l'un ni l'autre. Jamais la joie, dont les deux bras levés sont
	// le geste le plus ample et attirent l'œil (arbitrage 35 bis)
	static MOMENTS = {
		matin: [['sip', 4.2], ['talk', DashboardTeam.TALK], ['sip', 4.2]],
		jour: [['talk', DashboardTeam.TALK], ['emotion:fier', 2], ['emotion:pensif', 2]],
		soir: [['emotion:fatigue', 2.4], ['wave', 2.7], ['emotion:pensif', 2]]
	};

	/**
	 * Appelée par `onrender` : une exception levée ici ferait tomber les `onrender` suivants de la page, d'où une chaîne
	 * de promesse dès la première instruction.
	 */
	static start(node) {

		DashboardTeam.stop();

		const team = new DashboardTeam(node);
		DashboardTeam.current = team;

		Promise.resolve()
			.then(() => team.open())
			.catch(error => {

				// Une équipe fermée par une navigation pendant son chargement ne parle plus : la page a changé
				if(team.closed) {
					return;
				}

				// L'équipe orne la tête, rien n'y attend un geste : sans elle, la tête reste entière
				team.close();
				DashboardTeam.current = null;
				node.qs('#dashboard-head-team-scene')?.remove();

				console.error(error);

			});

	}

	static stop() {

		if(DashboardTeam.current !== null) {
			DashboardTeam.current.close();
			DashboardTeam.current = null;
		}

	}

	// Le moment de la journée à l'heure de l'appareil : le café avant 11 h, la journée jusqu'à 17 h, le soir ensuite
	static getMoment(hour) {

		if(hour < 11) {
			return 'matin';
		} else if(hour < 17) {
			return 'jour';
		} else {
			return 'soir';
		}

	}

	// Les pixels d'un mètre quand le corps prend la hauteur de la place, des pieds au chapeau
	static getPerMetre(height) {
		return height * DashboardTeam.TOP / (DashboardTeam.HEIGHT * 1.06);
	}

	// Combien de membres tiennent dans une place de cette taille, la formule de la planche v3 : le nombre qu'elle montre
	// à chaque largeur se retrouve ainsi tel quel. Le rang laisse ARMS aux bras de ses deux bouts, au repos, et les
	// gestes qui débordent davantage se logent en resserrant le rang (build())
	static getCapacity(width, height) {
		return Math.max(1, Math.floor((width / DashboardTeam.getPerMetre(height) - DashboardTeam.ARMS) / DashboardTeam.STEP) + 1);
	}

	constructor(node) {

		this.node = node;
		this.container = node.qs('#dashboard-head-team-scene');
		this.aside = node.qs('.dashboard-head-team-aside');
		this.name = node.qs('.dashboard-head-team-name');

		const { members, silhouette } = JSON.parse(node.dataset.team);

		this.members = members;
		this.present = members.filter(member => member.away === false);
		this.away = members.filter(member => member.away).slice(0, DashboardTeam.AWAY_MAX);
		this.silhouette = silhouette;

		this.moment = DashboardTeam.getMoment(new Date().getHours());
		this.motion = window.matchMedia('(prefers-reduced-motion: reduce)').matches === false;

		this.closed = false;
		this.loaded = null;
		this.renderer = null;
		this.observer = null;
		this.visibility = null;
		this.listeners = new AbortController();
		this.frameId = null;
		this.running = true;
		this.tapId = null;

		// Le pointeur posé sur le canevas, et le corps dont le prénom est affiché
		this.pointer = null;
		this.named = null;
		this.nameSize = [0, 0];

		// Les modèles de la fabrique, par membre et par objet : un plafond qui change remonte l'équipe sans refaire
		// l'avatar de ceux qui restent
		this.models = new Map();

		this.actors = [];
		this.resting = [];

		// Quand et à qui le tour de changer d'attitude au second plan
		this.awayNext = null;
		this.awayTurn = 0;

		// La part de AWAY_SPACING que frame() laisse au second plan, moins quand il se resserre
		this.awaySpacing = 1;

		// Aucun rang monté : un plafond de 0, quand tous les membres sont au second plan, monte encore la scène
		this.cap = null;
		this.size = [0, 0];

	}

	open() {

		// Personne à montrer : la personne connectée sans avatar est seule, et sa place porte « Faire mon avatar »
		if(this.container === null) {
			this.layout();
			return;
		}

		// La largeur décide d'abord qui la tête montre, et seuls ceux-là lisent leur modèle : un rang qui s'élargit ensuite
		// monte la composition des nouveaux venus, faute de remplacement à chaud
		const shown = [...this.present.slice(0, this.getCap()), ...this.away];

		this.members
			.filter(member => shown.includes(member) === false)
			.forEach(member => member.upload = null);

		return Promise.all([
			SceneLoader.loadAvatar(this.container),
			...shown.map(member => this.readUpload(member))
		])
			.then(([loaded]) => {

				if(this.closed) {
					return;
				}

				this.loaded = loaded;
				this.mount();

			});

	}

	// Le binaire du modèle téléversé, posé sur le membre ; une lecture refusée ou trop lente laisse la composition
	readUpload(member) {

		if(member.upload === null) {
			return Promise.resolve();
		}

		return SceneLoader.readUpload(member.upload).then(upload => {
			member.upload = upload;
		});

	}

	mount() {

		const { THREE } = this.loaded;

		const ramp = new THREE.DataTexture(new Uint8Array(DashboardTeam.RAMP), DashboardTeam.RAMP.length, 1, THREE.RedFormat);
		ramp.minFilter = ramp.magFilter = THREE.LinearFilter;
		ramp.needsUpdate = true;

		this.ramp = ramp;
		this.painted = new THREE.MeshToonMaterial({ color: '#fff', vertexColors: true, gradientMap: ramp, side: THREE.FrontSide });
		this.grey = new THREE.MeshToonMaterial({ color: DashboardTeam.GREY, vertexColors: true, gradientMap: ramp, side: THREE.FrontSide });

		this.paintedAway = this.painted.clone();
		this.greyAway = this.grey.clone();

		// Après les clones, que `Material.copy()` fait sans `onBeforeCompile`
		[this.grey, this.greyAway].forEach(material => material.onBeforeCompile = shader => {
			shader.fragmentShader = shader.fragmentShader.replace(
				'#include <color_fragment>',
				'diffuseColor.rgb *= '+ (1 - DashboardTeam.GREY_RELIEF).toFixed(2) +' + '+ DashboardTeam.GREY_RELIEF.toFixed(2) +' * dot(vColor, vec3(.299, .587, .114));'
			);
		});

		[this.paintedAway, this.greyAway].forEach(material => {
			material.transparent = true;
			material.opacity = DashboardTeam.AWAY_OPACITY;
		});

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight('#dcefff', '#93a06c', 1.4));

		const sun = new THREE.DirectionalLight('#fff1d6', 2);
		sun.position.set(-30, 50, 40);
		this.scene.add(sun);

		this.camera = new THREE.PerspectiveCamera(DashboardTeam.FOV, 1, .05, 80);

		this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
		this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

		this.canvas = this.renderer.domElement;
		this.container.append(this.canvas);

		this.raycaster = new THREE.Raycaster();

		const signal = this.listeners.signal;

		this.canvas.addEventListener('pointermove', event => this.point(event, false), { signal });
		this.canvas.addEventListener('pointerdown', event => this.point(event, event.pointerType !== 'mouse'), { signal });
		this.canvas.addEventListener('pointerleave', () => this.point(null, false), { signal });

		// La largeur décide du nombre de membres, la hauteur de leur taille : l'une et l'autre changent avec la fenêtre,
		// et la place change aussi quand la liste des absences change de forme sans que l'équipe change de largeur
		this.observer = new ResizeObserver(() => this.layout());
		this.observer.observe(this.node);
		this.observer.observe(this.container);

		// Hors de l'écran ou dans un onglet caché, le canevas ne dessine plus
		this.visibility = new IntersectionObserver(entries => {
			this.running = entries[0].isIntersecting;
			this.request();
		});
		this.visibility.observe(this.canvas);

		document.addEventListener('visibilitychange', () => this.request(), { signal });

		this.layout();

	}

	// Le plafond du rang, sur la largeur que laisse la liste des absences, mesurée
	layout() {

		if(this.closed || this.container === null) {
			return;
		}

		const shown = this.getCap();

		if(this.loaded === null) {
			return;
		}

		if(shown !== this.cap) {
			this.cap = shown;
			this.build();
		}

		this.resize();
		this.render(performance.now());
		this.request();

	}

	/**
	 * Combien de présents le rang garde : la formule de la planche v6 (arbitrage 28), la capacité de la largeur moins
	 * AWAY_ROOM par absent du second plan, RANK_MIN au moins. Ceux qui n'y tiennent pas ne se dessinent pas, comme
	 * au-delà de la capacité quand personne ne manque.
	 */
	getCap() {

		const gap = parseFloat(window.getComputedStyle(this.node).columnGap) || 0;
		const width = this.node.clientWidth - ((this.aside === null) ? 0 : this.aside.offsetWidth + gap);
		const height = this.container.clientHeight;

		if(width <= 0 || height <= 0) {
			return Math.min(this.present.length, 1);
		}

		// Comme sur la planche, le nombre ne dépend pas du moment : les gestes plus larges se logent en resserrant le rang
		const fit = DashboardTeam.getCapacity(width, height);
		const cap = window.matchMedia(DashboardTeam.NARROW).matches ? Math.min(fit, DashboardTeam.NARROW_CAP) : fit;

		return Math.min(this.present.length, Math.max(DashboardTeam.RANK_MIN, cap - Math.ceil(this.away.length * DashboardTeam.AWAY_ROOM)));

	}

	getModel(member, object) {

		if(member.upload !== null) {
			return this.getUploadModel(member, object);
		}

		const key = this.members.indexOf(member) +':'+ object;

		if(this.models.has(key) === false) {

			const fiche = structuredClone(member.fiche ?? this.silhouette);

			if(object !== null) {
				fiche.elements.push({ catalogue: object, reglages: {} });
			}

			this.models.set(key, this.loaded.avatar.build(fiche));

		}

		return this.models.get(key);

	}

	// Le modèle téléversé (`SceneLoader.mountUpload()`) ; un modèle que la fabrique ne sait pas monter laisse la composition
	getUploadModel(member, object) {

		const key = this.members.indexOf(member) +':upload';

		if(this.models.has(key) === false) {

			const model = SceneLoader.mountUpload(this.loaded, member.fiche, member.upload);

			if(model === null) {
				member.upload = null;
				return this.getModel(member, object);
			}

			this.models.set(key, model);

		}

		return this.models.get(key);

	}

	// Monte les membres que le plafond garde, à leur place, avec l'objet du moment
	build() {

		const { figure: figures } = this.loaded;

		this.dispose();

		const count = this.cap;

		const create = (member, slot, object, material) => {

			const model = this.getModel(member, object);
			const figure = figures.assemble(model, 'avatar', material);

			// Le visage de chaque trait tel que le modèle le monte, qu'un geste remplace le temps de son moment. Un modèle
			// téléversé n'a pas les traits de la composition, dont les visages des gestes sont faits
			figure.traits = model.uploaded ? {} : Object.fromEntries(
				model.parts
					.filter(({ name }) => model.parents[name] === 'tete' || model.parents[name]?.startsWith('paupiere'))
					.map(({ name }) => [name, figure.bones[name].children.find(child => child.isMesh)])
			);
			figure.neutral = Object.fromEntries(Object.entries(figure.traits).map(([trait, mesh]) => [trait, mesh.geometry]));
			figure.faces = new Map();

			this.scene.add(figure.root);

			return {
				member, model, figure, slot,
				look: 0, lookTarget: 0,
				gesture: 'stand', from: null, since: 0, face: null,
				phase: slot * 1.7, blinkAt: 1 + (slot * 1.3) % 4, next: 1 + slot * .9
			};

		};

		this.actors = this.present.slice(0, count).map((member, slot) => create(member, slot, DashboardTeam.OBJECTS[this.moment] ?? null, (member.fiche === null) ? this.grey : this.painted));
		this.resting = this.away.map((member, index) => create(member, count + index, null, (member.fiche === null) ? this.greyAway : this.paintedAway));

		const pick = (list, index) => list[index % list.length];

		// Les places, de droite à gauche, chacune à son écart, sa profondeur, son cap et son attitude
		const step = this.getStep();

		let x = 0;

		this.actors.forEach((actor, index) => {

			actor.home = { x, z: pick(DashboardTeam.RANK_DEPTH, index), yaw: pick(DashboardTeam.RANK_YAW, index) };
			actor.attitude = pick(DashboardTeam.RANK_ATTITUDES, index);
			actor.gesture = actor.attitude;

			x -= step * pick(DashboardTeam.RANK_GAP, index);

		});

		// Le second plan, derrière le rang et décalé vers la gauche, à la place de la planche v6 : tous partent du dernier
		// présent, et frame() les écarte un à un du rang puis l'un de l'autre, à l'écran
		const left = (this.actors.length > 0) ? this.actors[this.actors.length - 1].home.x : 0;

		this.resting.forEach((actor, index) => {
			actor.home = { x: left, z: -DashboardTeam.BACK + pick(DashboardTeam.AWAY_DEPTH, index), yaw: pick(DashboardTeam.AWAY_YAW, index) };
			actor.back = actor.home.x;
			actor.gesture = pick(DashboardTeam.AWAY_ATTITUDES, index);
			actor.figure.root.scale.setScalar(DashboardTeam.BACK_SCALE);
		});

		[...this.actors, ...this.resting].forEach(actor => actor.figure.root.position.set(actor.home.x, 0, actor.home.z));

		// Posé une fois dans son attitude : l'image fixe sans mouvement, et la première avant que rest() ne l'anime
		this.resting.forEach(actor => {

			actor.reach = this.getAwayReach(actor);

			this.pose(actor, 0);

			actor.figure.root.rotation.y = actor.home.yaw;

		});

		this.bound();

		this.size = [0, 0];

		if(this.motion === false) {
			this.settle();
		}

	}

	/**
	 * Ce que le cadrage loge, en deux boites par corps, ce qui reste près du sol et ce qui s'élève au-dessus : le rang au
	 * repos, gestes compris, dans `boxes`, et le second plan, toutes attitudes comprises, dans `awayBoxes`
	 */
	bound() {

		const { THREE } = this.loaded;

		const around = (actor, { x, floor, low, z: high }) => {

			const h = actor.figure.h * actor.figure.root.scale.x;
			const [left, right] = x.map(reach => actor.home.x + reach * h);
			const z = actor.home.z;

			return [
				new THREE.Box3(new THREE.Vector3(left, floor * h, z + low[0] * h), new THREE.Vector3(right, DashboardTeam.REACH_LOW * h, z + low[1] * h)),
				new THREE.Box3(new THREE.Vector3(left, DashboardTeam.REACH_LOW * h, z + high[0] * h), new THREE.Vector3(right, h * 1.06, z + high[1] * h))
			];

		};

		this.boxes = this.actors.flatMap(actor => around(actor, DashboardTeam.REACH[this.moment]));
		this.awayBoxes = this.resting.flatMap(actor => around(actor, actor.reach));

	}

	/**
	 * L'écart entre deux membres au repos, en mètres : STEP quand le rang tient, gestes compris, à la taille que donne la
	 * hauteur du canevas, moins sinon, jusqu'à STEP_TIGHT. Au-delà, frame() rapetisse les corps pour garder le reste
	 */
	getStep() {

		if(this.actors.length < 2) {
			return DashboardTeam.STEP;
		}

		const metres = this.container.clientWidth / DashboardTeam.getPerMetre(this.container.clientHeight);

		// Les bras du premier membre, à droite, et ceux du dernier, à gauche, débordent du rang
		const [left, right] = DashboardTeam.REACH[this.moment].x;
		const reach = right * this.actors[0].figure.h - left * this.actors[this.actors.length - 1].figure.h;

		const gaps = this.actors.slice(1).reduce((sum, actor, index) => sum + DashboardTeam.RANK_GAP[index % DashboardTeam.RANK_GAP.length], 0);
		const step = (metres - reach) / gaps;

		return Math.min(DashboardTeam.STEP, Math.max(DashboardTeam.STEP_TIGHT, step));

	}

	// Libère les membres montés, avant un nouveau plafond ou à la fermeture. Les matériaux restent, partagés par tous
	dispose() {

		[...this.actors, ...this.resting].forEach(({ figure }) => {

			this.scene.remove(figure.root);
			figure.root.traverse(object => object.geometry?.dispose());
			figure.faces.forEach(geometry => geometry.dispose());

		});

		this.actors = [];
		this.resting = [];

		// Le corps nommé n'est plus monté : le prochain rendu le cherche de nouveau sous le pointeur
		this.named = null;

	}

	resize() {

		const width = this.container.clientWidth;
		const height = this.container.clientHeight;

		if(width === 0 || height === 0 || (width === this.size[0] && height === this.size[1])) {
			return;
		}

		this.size = [width, height];

		// Le tampon seul : le canevas garde la taille que lui donne sa feuille, sans quoi une largeur posée en pixels
		// retient sa place quand la fenêtre rétrécit, et la scène ne se mesure plus jamais plus petite
		this.renderer.setSize(width, height, false);
		this.frame(width, height);

	}

	/**
	 * Le cadrage, dans l'ordre de l'arbitrage 32 : d'abord le rang, à la taille de la planche, puis le second plan, entier
	 * et jamais caché. Le rang se cadre seul ; les absents s'écartent ensuite du
	 * rang et l'un de l'autre à l'écran (arbitrage 28), et s'ils débordent du canevas, le second plan se resserre, son
	 * écart d'abord, sa taille ensuite jusqu'à AWAY_SCALE_MIN. Ce n'est qu'au-delà que le rang rapetisse pour le loger
	 */
	frame(width, height) {

		// Le second plan à une échelle et une part de son écart irrégulier, posé contre le rang puis écarté
		const place = (scale, spacing) => {

			this.resting.forEach(actor => {
				actor.figure.root.scale.setScalar(scale);
				actor.home.x = actor.back;
			});

			this.awaySpacing = spacing;

			// Sous un même cadrage, la projection d'une profondeur donnée est affine en x : les écarts sont justes du premier
			// coup, et une seconde passe ne sert qu'aux arrondis
			for(let pass = 0; pass < DashboardTeam.AWAY_PASSES; pass++) {

				const shifts = this.getHidden();

				if(shifts.every(shift => shift <= 0)) {
					break;
				}

				let moved = 0;

				this.resting.forEach((actor, index) => {
					moved += shifts[index];
					actor.home.x -= moved;
				});

			}

			this.resting.forEach(actor => {
				actor.figure.root.position.x = actor.home.x;
			});

			this.bound();

			return this.contains(this.awayBoxes);

		};

		// Sans personne au rang, le second plan se cadre seul
		this.bound();
		this.fit(width, height, (this.boxes.length > 0) ? this.boxes : this.awayBoxes);

		if(this.resting.length > 0 && place(DashboardTeam.BACK_SCALE, 1) === false) {

			// Le plus d'écart, puis la plus grande taille, que le canevas loge
			const search = (low, high, holds) => {

				for(let i = 0; i < 12; i++) {
					const middle = (low + high) / 2;
					[low, high] = holds(middle) ? [middle, high] : [low, middle];
				}

				return low;

			};

			if(place(DashboardTeam.BACK_SCALE, 0)) {
				place(DashboardTeam.BACK_SCALE, search(0, 1, spacing => place(DashboardTeam.BACK_SCALE, spacing)));
			} else if(place(DashboardTeam.AWAY_SCALE_MIN, 0)) {
				place(search(DashboardTeam.AWAY_SCALE_MIN, DashboardTeam.BACK_SCALE, scale => place(scale, 0)), 0);
			} else {

				// Le dernier recours : le rang rapetisse pour loger le second plan resserré. Chaque recadrage change la
				// perspective, d'où les passes
				for(let pass = 0; pass < DashboardTeam.AWAY_PASSES; pass++) {
					this.fit(width, height, [...this.boxes, ...this.awayBoxes]);
					place(DashboardTeam.AWAY_SCALE_MIN, 0);
				}

				// La dernière passe écarte le second plan sous un cadrage qui ne le logeait pas encore là : seul un recadrage
				// sur les boites posées le garde entier dans le canevas
				this.fit(width, height, [...this.boxes, ...this.awayBoxes]);

			}

		}

	}

	/**
	 * De combien de mètres pousser chaque absent à gauche, en plus de ceux qui sont à sa droite, pour qu'à l'écran son
	 * corps finisse AWAY_GAP et son AWAY_SPACING avant celui de son voisin de droite : le présent le plus à gauche pour le
	 * premier, l'absent précédent pour les suivants. Les corps et non les gestes, comme sur la planche : un salut ou un
	 * poing sur la hanche passent devant le voisin sans le cacher, et loger tout le geste écarterait le second plan au
	 * point de rapetisser le rang
	 */
	getHidden() {

		const { THREE } = this.loaded;

		const project = (x, y, z) => new THREE.Vector3(x, y, z).project(this.camera).x;

		// Le bord gauche ou droit d'un corps à l'écran, de ses pieds à sa tête
		const edge = (x, z, h, reach, side) => side(...[0, h].map(y => project(x + reach * h, y, z)));

		let front = (this.actors.length === 0) ?
			Infinity :
			Math.min(...this.actors.map(actor => edge(actor.home.x, actor.home.z, actor.figure.h, DashboardTeam.BODY[0], Math.min)));

		let moved = 0;

		return this.resting.map((actor, index) => {

			const h = actor.figure.h * actor.figure.root.scale.x;
			const [left, right] = DashboardTeam.BODY;
			const x = actor.home.x - moved;
			const z = actor.home.z;

			const overlap = edge(x, z, h, right, Math.max) - front + DashboardTeam.AWAY_GAP;
			const spacing = DashboardTeam.AWAY_SPACING[index % DashboardTeam.AWAY_SPACING.length] * this.awaySpacing * h;

			// Les unités du canevas par mètre, à la profondeur de cet absent
			const perMetre = project(x + 1, h / 2, z) - project(x, h / 2, z);
			const shift = Math.max(0, overlap / perMetre + spacing);

			moved += shift;
			front = edge(actor.home.x - moved, z, h, left, Math.min);

			return shift;

		});

	}

	/**
	 * Ce que le cadrage loge d'un absent du second plan : toutes ses attitudes, tournées de son cap, en fraction de sa
	 * taille, des poings aux hanches de la fierté au dos voûté de la fatigue. Mesuré sur son modèle, une fois, parce que ses
	 * attitudes changent sans recadrage et qu'une enveloppe écrite d'avance, une boite par axe, compterait ensemble des
	 * bras et un dos qui ne vont jamais du même côté une fois tournés
	 */
	getAwayReach(actor) {

		const { THREE } = this.loaded;
		const { figure } = actor;
		const h = figure.h * figure.root.scale.x;
		const box = new THREE.Box3();
		const gesture = actor.gesture;
		const position = figure.root.position.clone();

		// Mesuré au pied de sa place, la descente du corps comprise, puisque le cadrage loge le corps depuis sa place
		// (bound()) et que les genoux pliés font descendre les semelles sous le sol. Le balancement sur les pieds
		// (render()) penche le corps des deux côtés
		DashboardTeam.AWAY_ATTITUDES.forEach(attitude => {

			Object.assign(actor, { gesture: attitude, from: null });
			this.pose(actor, 0);

			figure.root.position.x = 0;
			figure.root.position.z = 0;

			[-DashboardTeam.SWAY, DashboardTeam.SWAY].forEach(sway => {

				figure.root.rotation.set(0, actor.home.yaw, sway);
				figure.root.updateMatrixWorld(true);

				box.union(new THREE.Box3().setFromObject(figure.root));

			});

		});

		actor.gesture = gesture;
		figure.root.position.copy(position);

		const x = [box.min.x / h, box.max.x / h];
		const z = [box.min.z / h, box.max.z / h];

		return { x, z, low: z, floor: Math.min(0, box.min.y / h) };

	}

	getCorners(boxes) {

		const { THREE } = this.loaded;

		return boxes.flatMap(({ min, max }) => [min.x, max.x].flatMap(x => [min.y, max.y].flatMap(y => [min.z, max.z].map(z => new THREE.Vector3(x, y, z)))));

	}

	// Si ces boites tiennent entières dans le canevas sous le cadrage posé
	contains(boxes) {

		return this.getCorners(boxes)
			.map(corner => corner.project(this.camera))
			.every(({ x, y }) => x >= -1 && x <= 1 && y >= -1 && y <= DashboardTeam.TOP);

	}

	/**
	 * Les boites données, visées de la direction de la caméra, à la distance où elles remplissent la hauteur du canevas
	 * sans déborder de sa largeur, puis calées en bas et à droite, la personne connectée sous la roue.
	 */
	fit(width, height, boxes) {

		const { THREE } = this.loaded;
		const camera = this.camera;

		camera.aspect = width / height;

		const center = boxes.reduce((box, next) => box.union(next), new THREE.Box3()).getCenter(new THREE.Vector3());
		const corners = this.getCorners(boxes);

		const toward = new THREE.Vector3(0, DashboardTeam.PITCH, 1).normalize();

		const aim = (distance, shift = new THREE.Vector3()) => {

			camera.position.copy(center).addScaledVector(toward, distance).add(shift);
			camera.lookAt(center.clone().add(shift));
			camera.updateProjectionMatrix();
			camera.updateMatrixWorld();

			const projected = corners.map(corner => corner.clone().project(camera));

			return {
				minX: Math.min(...projected.map(v => v.x)),
				maxX: Math.max(...projected.map(v => v.x)),
				minY: Math.min(...projected.map(v => v.y)),
				maxY: Math.max(...projected.map(v => v.y))
			};

		};

		let low = .5;
		let high = 60;

		for(let i = 0; i < 30; i++) {

			const middle = (low + high) / 2;
			const bounds = aim(middle);

			if(bounds.minY < -1 || bounds.maxY > DashboardTeam.TOP || bounds.maxX - bounds.minX > 2) {
				low = middle;
			} else {
				high = middle;
			}

		}

		const bounds = aim(high);
		const halfHeight = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * high;
		const halfWidth = halfHeight * camera.aspect;
		const right = new THREE.Vector3().setFromMatrixColumn(camera.matrixWorld, 0);
		const up = new THREE.Vector3().setFromMatrixColumn(camera.matrixWorld, 1);

		aim(high, right.multiplyScalar(-(1 - bounds.maxX) * halfWidth).add(up.multiplyScalar(-(-1 - bounds.minY) * halfHeight)));

	}

	// ---- Le corps : visage, repos, gestes et passage de l'un à l'autre, repris de `UserAvatarEditor` ----

	setFace(actor, visage) {

		if(visage === actor.face) {
			return;
		}

		actor.face = visage;

		const { THREE } = this.loaded;
		const { figure, model } = actor;

		Object.entries(figure.traits).forEach(([trait, mesh]) => {

			const geometry = visage?.[trait];

			if(geometry === undefined) {
				mesh.geometry = figure.neutral[trait];
				return;
			}

			if(figure.faces.has(geometry) === false) {

				const color = new THREE.Color(model.parts.find(({ name }) => name === trait).color);
				const colors = new Float32Array(geometry.attributes.position.count * 3);

				for(let i = 0; i < colors.length; i += 3) {
					color.toArray(colors, i);
				}

				const painted = geometry.clone();
				painted.setAttribute('color', new THREE.BufferAttribute(colors, 3));
				painted.translate(...model.pivots[trait].map(value => -value));

				figure.faces.set(geometry, painted);

			}

			mesh.geometry = figure.faces.get(geometry);

		});

	}

	// Le personnage tel que le modèle le monte, debout, les bras le long du corps, les yeux ouverts
	still({ root, bones, torso, rest }) {

		[root, ...Object.values(bones)].forEach(bone => bone.rotation.set(0, 0, 0));
		['paupiereG', 'paupiereD'].forEach(name => bones[name]?.scale.set(1, 1, 1));
		torso.position.y = rest;
		root.position.y = 0;

	}

	snapshot({ root, bones, torso }) {

		return [
			...[root, ...Object.values(bones)].flatMap(bone => [bone.rotation.x, bone.rotation.y, bone.rotation.z]),
			torso.position.y,
			root.position.y
		];

	}

	restore({ root, bones, torso }, values) {

		[root, ...Object.values(bones)].forEach((bone, index) => bone.rotation.set(...values.slice(index * 3, index * 3 + 3)));
		[torso.position.y, root.position.y] = values.slice(-2);

	}

	setGesture(actor, gesture, time) {

		if(actor.gesture === gesture) {
			return;
		}

		actor.from = this.snapshot(actor.figure);
		actor.since = time;
		actor.gesture = gesture;

	}

	// Les angles posés os par os, puis la descente du corps
	apply({ figure, model }, angles) {

		Object.entries(angles)
			.filter(([name]) => figure.bones[name] !== undefined)
			.forEach(([name, angle]) => figure.bones[name].rotation.set(...angle));

		figure.root.position.y = -(angles.descente ?? 0) * model.anchors.descenteAssis;

	}

	// Le geste à l'instant donné, son visage, et si l'objet tenu quitte la main le temps du geste
	getMoment(actor, time) {

		const { figure, model, gesture } = actor;

		if(gesture === 'stand') {

			figure.root.position.y = this.loaded.figure.pose(figure, gesture, { time: time + actor.phase });

			// Le poids passe d'une jambe à l'autre au repos : le corps se balance sur ses pieds
			if(this.motion) {
				figure.root.rotation.z = Math.sin(time * .9 + actor.phase) * DashboardTeam.SWAY;
			}

			return { visage: null, free: false };

		}

		if(DashboardTeam.ANIMATIONS[gesture] !== undefined) {

			const { pose, visage } = this.loaded.avatar.animer(model, DashboardTeam.ANIMATIONS[gesture], this.motion ? time - actor.since : null);
			this.apply(actor, pose);

			return { visage, free: pose.mainsLibres === true };

		}

		const { pose, visage } = model.emotions[gesture.split(':')[1]];
		this.apply(actor, pose);

		// Une émotion tenue respire comme le repos, chacun à son rythme
		figure.torso.rotation.x += Math.sin(time * 1.3 + actor.phase) * .02;

		return { visage, free: pose.mainsLibres === true };

	}

	pose(actor, time) {

		const { figure } = actor;

		this.still(figure);

		const { visage, free } = this.getMoment(actor, time);

		// Un visage ne s'interpole pas : il change à mi-passage, comme dans l'éditeur
		const progress = (actor.from === null) ? 1 : Math.min(1, (time - actor.since) / DashboardTeam.BLEND);

		if(progress >= .5) {
			this.setFace(actor, visage);
		}

		if(actor.from !== null) {

			if(progress < 1) {
				const weight = progress * progress * (3 - 2 * progress);
				const to = this.snapshot(figure);
				this.restore(figure, to.map((value, index) => actor.from[index] + (value - actor.from[index]) * weight));
			} else {
				actor.from = null;
			}

		}

		// L'objet tenu disparait le temps d'un geste qui prend les deux mains : l'éditeur le pose au sol à côté du pied, ce
		// que la tête, trop petite, ne montrerait pas
		if(figure.bones.objet !== undefined) {
			figure.bones.objet.visible = (free === false);
		}

		// La tête se tourne vers ce que la personne regarde, par-dessus le geste
		figure.bones.tete.rotation.y += actor.look;

		if(this.motion) {

			const blink = (time - actor.blinkAt) / DashboardTeam.BLINK;

			if(blink >= 1) {
				const [min, max] = DashboardTeam.BLINK_EVERY;
				actor.blinkAt = time + min + Math.random() * (max - min);
			} else if(blink > 0) {
				['paupiereG', 'paupiereD'].forEach(name => figure.bones[name]?.scale.setY(1 - .9 * Math.sin(Math.PI * blink)));
			}

		}

	}

	// ---- Le moment de la journée : à chaque image, le geste, la place et le regard de chacun ----

	direct(time) {

		const count = this.actors.length;

		this.actors.forEach(actor => {

			if(time >= actor.next) {

				// Après un geste, un temps dans son attitude de repos, puis un geste que ni lui ni ses voisins ne tiennent, ni
				// dans le moment ni au repos : l'attitude de chacun reste ainsi la sienne, et il la retrouve toujours libre
				const neighbours = this.getNeighbours(actor);
				const taken = [...neighbours.map(neighbour => neighbour.gesture), ...neighbours.map(neighbour => neighbour.attitude), actor.attitude];
				const gestures = DashboardTeam.MOMENTS[this.moment]
					.map(entry => (actor.model.uploaded === true) ? (DashboardTeam.UPLOAD_GESTURES[entry[0]] ?? entry) : entry)
					.filter(([gesture]) => taken.includes(gesture) === false);

				if(actor.gesture !== actor.attitude) {
					this.setGesture(actor, actor.attitude, time);
					actor.next = time + 2 + Math.random() * 2.5;
				} else if(gestures.length > 0) {
					const [gesture, hold] = gestures[Math.floor(Math.random() * gestures.length)];
					this.setGesture(actor, gesture, time);
					actor.next = time + hold;
				} else {
					actor.next = time + .5;
				}

			}

			// On parle deux à deux : la tête seule se tourne vers l'autre, le temps de parler
			const pair = (actor.slot % 2 === 0) ? actor.slot + 1 : actor.slot - 1;

			actor.lookTarget = (actor.gesture === 'talk' && pair < count) ? Math.sign(this.actors[pair].home.x - actor.home.x) * DashboardTeam.LOOK : 0;

		});

	}

	// Sans mouvement : chacun à sa place, dans l'image fixe du geste de son moment
	settle() {

		this.actors.forEach(actor => {

			Object.assign(actor, { look: 0, from: null });

			actor.gesture = this.getStill(actor);

		});

	}

	// Deux voisins n'ont jamais la même image fixe : chacun dans son attitude, ou un rang sur deux dans le geste du moment
	getStill(actor) {

		const even = (actor.slot % 2 === 0);

		if(this.moment === 'matin') {
			return even ? 'sip' : actor.attitude;
		} else if(this.moment === 'jour') {
			return actor.attitude;
		} else {
			return even ? actor.attitude : 'emotion:fatigue';
		}

	}

	// Les voisins au rang, de part et d'autre
	getNeighbours(actor) {

		const index = this.actors.indexOf(actor);

		return [this.actors[index - 1], this.actors[index + 1]].filter(neighbour => neighbour !== undefined);

	}

	// Le second plan vit lentement : chaque absent change d'attitude toutes les AWAY_HOLD secondes, un seul à la fois et
	// chacun son tour, pour la suivante de la liste qu'aucun voisin ne tient
	rest(time) {

		const count = this.resting.length;

		if(this.awayNext === null) {
			this.awayNext = time + this.getAwayHold() / count;
		}

		if(time < this.awayNext) {
			return;
		}

		const index = this.awayTurn % count;
		const actor = this.resting[index];
		const taken = [this.resting[index - 1], this.resting[index + 1]].filter(neighbour => neighbour !== undefined).map(neighbour => neighbour.gesture);
		const attitudes = DashboardTeam.AWAY_ATTITUDES;
		const from = attitudes.indexOf(actor.gesture);
		const following = [...attitudes.slice(from + 1), ...attitudes.slice(0, from)];

		this.setGesture(actor, following.find(attitude => taken.includes(attitude) === false), time);

		this.awayTurn++;
		this.awayNext = time + this.getAwayHold() / count;

	}

	getAwayHold() {

		const [min, max] = DashboardTeam.AWAY_HOLD;

		return min + Math.random() * (max - min);

	}

	// ---- L'image ----

	render(now) {

		if(this.closed || this.actors.length + this.resting.length === 0 || this.size[0] === 0) {
			return;
		}

		const time = now / 1000;
		const elapsed = (this.last === undefined) ? 0 : Math.min(.1, time - this.last);

		this.last = time;

		if(this.motion) {
			this.direct(time);
		}

		const ease = 1 - Math.exp(-elapsed * 3 / DashboardTeam.LOOK_EASE);

		if(this.motion && this.resting.length > 0) {
			this.rest(time);
		}

		this.actors.forEach(actor => {

			actor.look += (actor.lookTarget - actor.look) * ease;

			this.pose(actor, time);

			// Après la pose, qui remet le corps droit
			actor.figure.root.rotation.y = actor.home.yaw;

		});

		// Le second plan, à sa place fixe, se balance sur ses pieds quelle que soit son attitude
		if(this.motion) {

			this.resting.forEach(actor => {
				this.pose(actor, time);
				actor.figure.root.rotation.set(0, actor.home.yaw, Math.sin(time * .9 + actor.phase) * DashboardTeam.SWAY);
			});

		}

		this.renderer.render(this.scene, this.camera);

		if(this.pointer !== null) {
			this.named = this.find(this.pointer);
		}

		this.label();

	}

	// Une seule demande d'image à la fois, aucune hors de l'écran ni dans un onglet caché
	request() {

		if(
			this.motion === false ||
			this.closed ||
			this.frameId !== null ||
			this.running === false ||
			document.hidden
		) {
			return;
		}

		this.frameId = requestAnimationFrame(now => this.animate(now));

	}

	animate(now) {

		this.frameId = null;

		// La tête retirée sans navigation emporte le canevas : l'équipe s'arrête avec lui
		if(this.canvas.isConnected === false) {
			DashboardTeam.stop();
			return;
		}

		this.render(now);
		this.request();

	}

	// ---- Le prénom au survol, et à l'appui du doigt, qui n'a pas de survol ----

	/**
	 * Le pointeur se pose sur l'équipe, la quitte, ou le doigt appuie. Le corps nommé se cherche de nouveau à chaque image
	 * tant que le pointeur reste (render()) : le groupe bouge sous un pointeur immobile, et le prénom suit le corps qui
	 * passe dessous, pas celui qui y était.
	 */
	point(event, tap) {

		if(tap) {

			this.pointer = null;
			this.named = this.find(event);

			clearTimeout(this.tapId);
			this.tapId = (this.named === null) ? null : setTimeout(() => {
				this.tapId = null;
				this.named = null;
				this.label();
			}, DashboardTeam.NAME_TAP);

		} else if(this.tapId !== null) {
			// La pastille posée par le doigt reste son temps : elle ne suit pas le pointeur qui sort
			return;
		} else {
			this.pointer = (event === null) ? null : { clientX: event.clientX, clientY: event.clientY };
			this.named = (this.pointer === null) ? null : this.find(this.pointer);
		}

		this.label();

	}

	// Le prénom du corps nommé, au-dessus de sa tête telle que la dernière image la montre
	label() {

		if(this.named === null) {
			this.name.hidden = true;
			return;
		}

		const { THREE } = this.loaded;
		const actor = this.named;
		const head = new THREE.Vector3(0, actor.figure.h * 1.08, 0).applyMatrix4(actor.figure.root.matrixWorld).project(this.camera);

		// Mesurée au changement de prénom seulement : à chaque image, une mesure après l'écriture de la position
		// forcerait la mise en page
		if(this.name.hidden || this.name.textContent !== actor.member.name) {
			this.name.textContent = actor.member.name;
			this.name.hidden = false;
			this.nameSize = [this.name.offsetWidth, this.name.offsetHeight];
		}

		// Centrée sur la tête mais jamais hors de l'équipe : la personne connectée est au bord droit, sous la roue
		const half = this.nameSize[0] / 2;
		const x = this.canvas.offsetLeft + (head.x + 1) / 2 * this.canvas.clientWidth;
		const y = this.canvas.offsetTop + (1 - head.y) / 2 * this.canvas.clientHeight;
		const right = this.node.clientWidth - half;

		this.name.style.left = Math.min(Math.max(x, half), right) +'px';
		this.name.style.top = Math.max(y, this.nameSize[1]) +'px';

	}

	/**
	 * Le corps sous un point de l'écran. Le lancer de rayon de three traverse ce qui est masqué : l'objet qu'un geste
	 * retire de la main, un trait du visage remplacé. Seul compte le premier maillage visible jusqu'à sa
	 * racine, sinon un objet masqué d'un voisin, plus près de la caméra, donnerait son prénom.
	 */
	find({ clientX, clientY }) {

		const { THREE } = this.loaded;
		const rect = this.canvas.getBoundingClientRect();
		const pointer = new THREE.Vector2((clientX - rect.left) / rect.width * 2 - 1, -(clientY - rect.top) / rect.height * 2 + 1);

		this.raycaster.setFromCamera(pointer, this.camera);

		const actors = [...this.actors, ...this.resting];
		const roots = new Map(actors.map(actor => [actor.figure.root, actor]));

		for(const { object } of this.raycaster.intersectObjects(actors.map(actor => actor.figure.root), true)) {

			let node = object;

			while(node.visible && roots.has(node) === false) {
				node = node.parent;
			}

			if(node.visible) {
				return roots.get(node);
			}

		}

		return null;

	}

	close() {

		if(this.closed) {
			return;
		}

		this.closed = true;

		if(this.frameId !== null) {
			cancelAnimationFrame(this.frameId);
			this.frameId = null;
		}

		clearTimeout(this.tapId);

		this.listeners.abort();
		this.observer?.disconnect();
		this.visibility?.disconnect();

		if(this.renderer !== null) {

			this.dispose();

			this.painted.dispose();
			this.grey.dispose();
			this.paintedAway.dispose();
			this.greyAway.dispose();
			this.ramp.dispose();

			// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer.domElement.remove();
			this.renderer = null;

		}

		this.models.clear();

	}

}

document.addEventListener('navigation.sleep', () => DashboardTeam.stop());
