/**
 * Les animaux de la scène : un merle et un rouge-gorge autour des arbres du décor et des fruitiers cultivés, un hérisson le long
 * des haies et des bandes fleuries. Sans arbre montré, aucun oiseau ; sans haie ni bande fleurie montrée, aucun hérisson. Ils ne portent pas d'ombre,
 * comme les insectes : la carte des ombres ne se refait qu'au changement de jour, l'ombre resterait sur place.
 *
 * Ce qu'ils font suit l'horloge des images et non la frise : revenir à une semaine ne les retrouve pas où ils étaient, et seule
 * la saison du hérisson lit la semaine montrée. Ils vivent aussi pendant la lecture et en mode photo.
 *
 * Rien ne se cherche à chaque image. Un animal décide à ses transitions (un envol, la fin d'un arrêt), et c'est là seulement
 * qu'il relit le décor, tire au hasard et revalide la place qu'il tient : un arbre masqué entre deux décisions garde son oiseau
 * jusqu'à la suivante.
 *
 * Le hérisson se retire par `hedgehog: false` : une scène qui l'a écarté ne le monte pas, plutôt que de compter sur l'absence de
 * haie pour qu'il ne sorte jamais, et n'a pas à charger `sceneHedgehog.js`. La caméra peut être orthographique ou en perspective
 * (`perMetre()`).
 */
class MapSceneFauna {

	// Un animal a sa taille naturelle, trois fois grossie au grossissement des paysans, tant qu'elle fait PIXELS pixels au cadrage
	// du moment. Quand la vue recule il grossit pour les tenir, sans dépasser CAP mètres dans la scène, la taille d'un paysan : le
	// plafond passe avant les pixels. Sous FAINT pixels à ce plafond, il ne se dessine plus
	static SCALE = 3;
	static PIXELS = 12;
	static CAP = 1.7;
	static FAINT = 6;

	// Les kits qui dressent un arbre, dont un oiseau fait le tour : une souche, un roncier ou un rocher n'en sont pas. Et ceux que
	// le hérisson longe, avec la haie d'un enclos
	static TREES = ['arbre', 'fruitier', 'bosquet', 'massif', 'verger', 'alignement', 'chene', 'saule', 'bouleau', 'arbre-mort'];
	static LINES = ['haie', 'bande-fleurie'];

	// Une place se cherche dans le cadre, parmi les plus proches du point regardé, et un animal sans place réessaie après RETRY
	// secondes
	static NEAR = 12;
	static RETRY = 2;

	// L'écart qu'un animal garde avec une planche, un abri ou un élément du décor, en mètres de l'animal à sa taille réelle, et la
	// distance sous laquelle une place relue est encore celle qu'il tient
	static ROOM = .1;
	static SAME = .05;

	// Un envol par oiseau toutes les FLIGHT_EVERY secondes. Le vol va à FLIGHT_SPEED mètres par seconde tant que sa durée tient dans
	// FLIGHT_SECONDS : une place lointaine se rejoint plus vite, pour qu'aucun vol ne traine. Il monte de FLIGHT_PEAK mètres et
	// s'écarte de sa ligne d'au moins FLIGHT_LOOP mètres : d'un côté d'un arbre à l'autre, il en fait le tour au lieu de le traverser
	static FLIGHT_EVERY = [30, 60];
	static FLIGHT_SECONDS = [2.5, 6];
	static FLIGHT_SPEED = 8;
	static FLIGHT_PEAK = [3, 10];
	static FLIGHT_LOOP = 2;
	static FLIGHT_BANK = .35;

	// Un oiseau se pose au bord de la cime, de PERCH mètres de l'oiseau au-delà, à moins de PERCH_TURN radians du côté de la caméra :
	// derrière l'arbre ou sous sa cime, il ne se verrait pas
	static PERCH = [.3, .9];
	static PERCH_TURN = 1.2;
	static PERCH_SHIFTS = [0, .8, -.8, 1.6, -1.6];
	// Deux oiseaux posés au même endroit se lisent comme un seul : leurs places se tiennent à APART fois la taille dessinée du plus
	// grand des deux
	static APART = 1.5;
	// Ce qu'une cime cache du sol derrière elle, en rayons de cime : la caméra plonge, donc un arbre haut de quatre à cinq rayons
	// couvre plus loin que son pied
	static CROWN_SHADE = 8;

	constructor({ THREE, socle, crew, decor, crops, geometry, ambience, canvas, covers, parent, hedgehog = true, perch = MapSceneFauna.PERCH }) {

		this.decor = decor;
		this.crops = crops;
		// Les rectangles que la rangée des réglages et la frise posent sur le canevas, relus à chaque décision : la scène les
		// remesure quand ils changent de taille
		this.covers = covers;
		this.perchAway = perch;
		this.geometry = geometry;
		this.ambience = ambience;
		this.canvas = canvas;
		this.parent = parent;
		this.natural = crew.scale * MapSceneFauna.SCALE;

		// Un tirage propre aux animaux : celui de l'équipe ne bouge pas, la journée se joue de la même façon avec ou sans eux
		this.random = MapSceneCrops.random(20261012);

		this.roots = [];

		const mount = slug => {

			const figure = MapSceneFauna.assemble(THREE, socle, slug, crew.material);

			// Le cap d'abord, puis l'assiette, puis l'inclinaison dans le virage
			figure.root.rotation.order = 'YZX';
			figure.root.visible = false;
			parent.add(figure.root);
			this.roots.push(figure.root);

			return figure;

		};

		// Posé et en vol sont deux modèles, dont les pivots diffèrent : un seul des deux se montre. L'oiseau garde en vol l'échelle de
		// sa pose, sans quoi il changerait de taille à l'envol
		this.birds = ['merle', 'rouge-gorge'].map(slug => ({
			size: socle.baked.index.models[slug].d,
			scale: 0,
			faint: false,
			perched: mount(slug),
			flying: mount(slug +'.vol'),
			place: null,
			flight: null,
			heading: 0,
			wait: 0,
			seed: this.random() * 100
		}));

		this.hedgehog = hedgehog ? new MapSceneHedgehog(this, mount('herisson'), socle.baked.index.models.herisson.d) : null;

		this.projection = new THREE.Matrix4();
		this.probe = new THREE.Vector3();
		this.band = [-1, 1];
		this.date = null;

		// La hauteur du canevas, lue à la première image puis après chaque resize() seulement, et les pixels par mètre d'où vient la
		// taille des animaux
		this.height = null;
		this.perMetreShown = null;
		this.last = null;

	}

	/**
	 * Monte un animal cuit en os : un groupe par pivot du modèle, qui porte les pièces de son nom. `figure.assemble()` du socle ne
	 * monte que des corps debout, dont il lit le buste ; un animal se découpe donc dans la géométrie fondue de `figure.prop()`, qui
	 * garde l'ordre et le nombre de sommets des pièces cuites, comme `MapSceneDecor.base()` y retrouve les fruits. Tous les os
	 * pendent à la racine.
	 */
	static assemble(THREE, socle, slug, material) {

		const model = socle.baked.index.models[slug];
		const whole = socle.figure.prop(socle.baked, slug, material).geometry;

		const root = new THREE.Group();
		const bones = {};

		for(const [name, [x, y, z]] of Object.entries(model.pivots)) {

			const geometry = new THREE.BufferGeometry();

			for(const [key, source] of Object.entries(whole.attributes)) {

				const size = source.itemSize;
				const ranges = [];
				let from = 0;

				for(const part of model.parts) {

					if(part.name === name) {
						ranges.push(source.array.subarray(from * size, (from + part.count) * size));
					}

					from += part.count;

				}

				const values = new source.array.constructor(ranges.reduce((total, range) => total + range.length, 0));
				let at = 0;

				for(const range of ranges) {
					values.set(range, at);
					at += range.length;
				}

				geometry.setAttribute(key, new THREE.BufferAttribute(values, size, source.normalized));

			}

			// L'os tourne autour de son pivot : ses pièces s'y ramènent
			geometry.translate(-x, -y, -z);

			bones[name] = new THREE.Group();
			bones[name].position.set(x, y, z);
			bones[name].add(new THREE.Mesh(geometry, material));
			root.add(bones[name]);

		}

		whole.dispose();

		return { root, bones };

	}

	// Un point du plan à plus de `margin` mètres de chaque rectangle
	static clear(p, blocks, margin) {

		const G = ScenePlanGeometry;

		return blocks.some(r => {

			const d = G.sub(p, r.origin);
			const along = G.dot(d, r.u);
			const across = G.dot(d, r.w);

			return along > -margin && along < r.length + margin && across > -margin && across < r.width + margin;

		}) === false;

	}

	// Un point du plan qu'une cime cache à la caméra, `toward` étant la direction du plan qui mène vers elle
	static shaded(p, trees, toward) {

		const G = ScenePlanGeometry;

		return trees.some(tree => {

			const d = G.sub(p, tree.p);
			const ahead = G.dot(d, toward);
			const aside = Math.abs(d.x * toward.n - d.n * toward.x);

			return aside < tree.reach && ahead < tree.reach && ahead > -tree.reach * MapSceneFauna.CROWN_SHADE;

		});

	}

	/**
	 * Ce que la scène montre aux animaux, en une lecture : le pied de chaque arbre du décor et de chaque fruitier cultivé avec le
	 * rayon de sa cime, et la ligne de chaque haie, haie d'enclos et bande fleurie avec sa demi-largeur. Un élément que son calque
	 * masque, en ruine ou pas encore monté n'y est pas.
	 */
	habitat() {

		const G = ScenePlanGeometry;
		const D = MapSceneDecor;
		const trees = [];
		const lines = [];

		for(const e of this.decor.elements) {

			if(e.root === null || this.decor.shows(e) === false) {
				continue;
			}

			if(MapSceneFauna.TREES.includes(e.kit)) {

				const { right, front } = D.axes(e);

				for(const foot of e.feet) {
					trees.push({
						e,
						p: G.add(e.p, G.add(G.mul(right, foot.x * e.size), G.mul(front, foot.z * e.size))),
						// La cime se déduit du tronc, que le décor tient pour une part fixe d'elle
						reach: Math.max(foot.half, foot.trunk / D.TRUNK_SHARE) * e.size
					});
				}

			} else if(MapSceneFauna.LINES.includes(e.kit)) {
				lines.push({ e, line: e.path.map(q => G.add(e.p, q)), half: e.thick / 2 });
			} else if(e.def.enclosure === true) {

				const hedge = D.enclosureLines(e).hedge;

				if(hedge.length >= 2) {
					lines.push({ e, line: hedge.map(q => G.add(e.p, q)), half: D.HEDGE_WIDTH / 2 });
				}

			}

		}

		// Un fruitier cultivé porte un oiseau comme un arbre du décor, sauf sous abri : sa cime est sous le film, et l'oiseau posé
		// autour le serait derrière l'abri. Le hérisson, lui, reste aux haies et aux bandes fleuries
		for(const [id, bloom] of this.crops.blooms) {

			if(bloom.date !== this.date || bloom.greenhouse) {
				continue;
			}

			for(const perch of bloom.perches) {
				trees.push({ e: id, p: perch, reach: perch.reach });
			}

		}

		return { trees, lines };

	}

	// Où un animal ne se pose pas : les planches, les abris et ce que les paysans contournent du décor
	blocks() {

		return [
			...this.geometry.beds,
			...[...this.geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ rectangle }) => rectangle),
			...this.decor.solids()
		];

	}

	/**
	 * Relève le cadre utile : ce que la caméra montre, moins la bande de la rangée des réglages en haut et celle de la frise en
	 * bas, sous lesquelles un animal ne se verrait pas. Sous la scène, la frise ne couvre rien et la bande du bas est vide.
	 */
	look(camera) {

		camera.updateMatrixWorld();
		this.projection.multiplyMatrices(camera.projectionMatrix, camera.matrixWorldInverse);

		const height = this.height;
		let top = 0;
		let bottom = height;

		for(const [, coverTop, , coverBottom] of this.covers()) {

			if(coverTop + coverBottom < height) {
				top = Math.max(top, coverBottom);
			} else {
				bottom = Math.min(bottom, coverTop);
			}

		}

		// En coordonnées de la caméra, où le haut du canevas vaut 1 et le bas -1
		this.band[0] = 1 - 2 * bottom / height;
		this.band[1] = 1 - 2 * top / height;

	}

	// Un point du plan, au sol, dans le cadre utile relevé par look()
	seen(p) {

		const at = this.probe.set(p.x, 0, -p.n).applyMatrix4(this.projection);

		return Math.abs(at.x) <= 1 && Math.abs(at.z) <= 1 && at.y >= this.band[0] && at.y <= this.band[1];

	}

	// Les plus proches du point regardé, `at` rendant le point du plan de chacun
	nearest(list, at, controls) {

		const far = item => (at(item).x - controls.target.x) ** 2 + (at(item).n + controls.target.z) ** 2;

		return list.map(item => [item, far(item)]).sort((a, b) => a[1] - b[1]).slice(0, MapSceneFauna.NEAR).map(([item]) => item);

	}

	delay([from, until]) {
		return from + this.random() * (until - from);
	}

	frame(time, date, week, camera, controls) {

		const F = MapSceneFauna;

		this.date = date;
		const seconds = time / 1000;
		// Un onglet qui revient ne rattrape pas son absence : aucun envol en rafale
		const dt = (this.last === null) ? 0 : Math.min(.1, seconds - this.last);
		this.last = seconds;

		// Le survol d'ouverture montre un cadre deux fois plus large que celui où il se pose : une place choisie pendant lui tomberait
		// souvent hors du cadrage d'ouverture
		if(this.ambience.intro !== null) {
			return;
		}

		// La hauteur retenue borne aussi le cadre utile de look(). Elle ne se relit qu'après resize() : relue pendant un geste de la
		// vue, elle tomberait après les écritures de la page dans la même image et forcerait une mise en page à chacune
		if(this.height === null) {
			this.height = this.canvas.clientHeight;
		}

		// Le grossissement tient au cadrage du moment, jamais à celui de l'ouverture : une scène ouverte étroite puis agrandie montre
		// les mêmes animaux qu'une scène ouverte grande. Il se recalcule quand les pixels par mètre changent plutôt qu'à chaque image,
		// et après le survol, dont le cadrage bouge à chaque image pour des animaux qui n'y sont pas encore
		const perMetre = MapSceneFauna.perMetre(this.height, camera, controls);

		if(this.perMetreShown !== perMetre) {

			this.perMetreShown = perMetre;

			for(const subject of [...this.birds, this.hedgehog].filter(subject => subject !== null)) {
				subject.scale = MapSceneInsects.sized(subject.size, this.natural, F.PIXELS, perMetre, F.CAP);
				subject.faint = MapSceneInsects.faint(subject.size, subject.scale, perMetre, F.FAINT);
			}

		}

		for(const bird of this.birds) {

			if(bird.flight !== null) {
				this.fly(bird, dt);
			} else {

				bird.wait -= dt;

				// Les deux oiseaux ne volent jamais ensemble : celui dont le délai tombe pendant le vol de l'autre attend sa pose, et
				// c'est ici seulement que cela se décide
				if(bird.wait <= 0 && this.birds.every(other => other.flight === null)) {
					this.decideBird(bird, camera, controls);
				}

			}

			this.drawBird(bird, seconds);

		}

		if(this.hedgehog !== null) {
			this.hedgehog.trot(dt, seconds, week, camera, controls);
		}

	}

	/**
	 * Les pixels par mètre au point regardé. Une caméra orthographique les tient de son zoom (`MapSceneInsects.perMetre()`) ; une
	 * caméra en perspective, de son recul sur `controls.target`. Le terme de la matrice de projection porte à la fois l'angle, le
	 * zoom et un décalage de vue (`setViewOffset()`), que la recopie de la caméra oublierait.
	 */
	static perMetre(height, camera, controls) {

		if(camera.isPerspectiveCamera !== true) {
			return MapSceneInsects.perMetre(height, camera);
		}

		return height * camera.projectionMatrix.elements[5] / (2 * camera.position.distanceTo(controls.target));

	}

	/**
	 * Une place au sol au bord de la cime d'un arbre, du côté de la caméra : un arbre du cadre, tiré parmi les plus proches du point
	 * regardé, et quand le cadre n'en montre aucun le plus proche de tout le plan, pour qu'une vue qui glisse vers lui les trouve là.
	 * `taken` porte les places des autres oiseaux, dont celle-ci se tient à l'écart.
	 * Les tirages se font en tête, avant toute recherche : une décision en consomme le même nombre, qu'elle trouve une place ou non.
	 */
	perch(trees, taken, apart, scale, camera, controls) {

		const G = ScenePlanGeometry;
		const F = MapSceneFauna;

		const first = this.random();
		const turn = (this.random() * 2 - 1) * F.PERCH_TURN;
		const away = this.delay(this.perchAway) * scale;

		const framed = trees.filter(tree => this.seen(tree.p));
		const near = this.nearest((framed.length > 0) ? framed : trees, tree => tree.p, controls);
		// Hors du cadre rien ne se voit, donc rien n'est à varier : le plus proche d'abord, pour que la vue les croise au plus tôt
		const start = (framed.length > 0) ? Math.floor(first * near.length) : 0;

		if(near.length === 0) {
			return null;
		}

		const blocks = this.blocks();
		const front = Math.atan2(controls.target.z - camera.position.z, camera.position.x - controls.target.x);
		const toward = { x: Math.cos(front), n: Math.sin(front) };

		for(let i = 0; i < near.length; i++) {

			const tree = near[(start + i) % near.length];

			for(const shift of F.PERCH_SHIFTS) {

				const angle = front + turn + shift;
				const p = G.add(tree.p, G.mul({ x: Math.cos(angle), n: Math.sin(angle) }, tree.reach + away));

				// Un arbre du cadre utile peut jeter sa place sous la frise
				if((framed.length === 0 || this.seen(p)) && F.clear(p, blocks, F.ROOM * scale) && F.shaded(p, trees, toward) === false && taken.every(q => G.length(G.sub(p, q)) >= apart)) {
					// Une place garde la hauteur de son sol, lue une fois quand l'oiseau la choisit
					return { p, tree, height: this.geometry.height(p.x, p.n) };
				}

			}

		}

		return null;

	}

	/**
	 * La décision d'un oiseau posé, ou qui n'est nulle part : il s'envole vers une autre place. Sans place libre, il reste où il est
	 * tant que son arbre se montre, et disparait sinon.
	 */
	decideBird(bird, camera, controls) {

		const G = ScenePlanGeometry;
		const F = MapSceneFauna;

		this.look(camera);

		const { trees } = this.habitat();
		const taken = this.birds.filter(other => other !== bird && other.place !== null).map(other => other.place.p);
		const apart = F.APART * Math.max(...this.birds.map(other => other.size * other.scale));
		const next = this.perch(trees, taken, apart, bird.scale, camera, controls);
		const wait = this.delay(F.FLIGHT_EVERY);
		const heading = this.random() * Math.PI * 2;
		const side = (this.random() < .5) ? -1 : 1;

		if(next === null) {

			const held = (bird.place !== null && trees.some(tree => tree.e === bird.place.tree.e && G.length(G.sub(tree.p, bird.place.tree.p)) < F.SAME));

			if(held === false) {
				bird.place = null;
			}

			bird.wait = held ? wait : F.RETRY;

			return;

		}

		bird.wait = wait;

		// Il n'était nulle part : il est déjà posé quand la vue arrive sur lui
		if(bird.place === null) {
			bird.place = next;
			bird.heading = heading;
			return;
		}

		const span = G.sub(next.p, bird.place.p);
		const distance = G.length(span);
		const forward = (distance > 1e-3) ? G.mul(span, 1 / distance) : { x: 1, n: 0 };

		bird.flight = {
			from: bird.place.p,
			base: bird.place.height,
			to: next,
			span,
			// L'écart à la ligne droite, du côté tiré, et l'inclinaison vers l'intérieur du virage qu'il impose
			aside: G.mul({ x: forward.n, n: -forward.x }, side * Math.max(F.FLIGHT_LOOP, distance * .25)),
			bank: -side * F.FLIGHT_BANK,
			peak: Math.min(F.FLIGHT_PEAK[1], F.FLIGHT_PEAK[0] + distance * .2),
			duration: Math.max(F.FLIGHT_SECONDS[0], Math.min(F.FLIGHT_SECONDS[1], distance / F.FLIGHT_SPEED)),
			elapsed: 0
		};

	}

	fly(bird, dt) {

		const flight = bird.flight;

		flight.elapsed += dt;

		if(flight.elapsed < flight.duration) {
			return;
		}

		bird.place = flight.to;
		bird.heading = Math.atan2(flight.span.n - flight.aside.n * Math.PI, flight.span.x - flight.aside.x * Math.PI);
		bird.flight = null;

	}

	drawBird(bird, seconds) {

		const { perched, flying, flight, scale } = bird;
		const t = seconds + bird.seed;

		perched.root.visible = (flight === null && bird.place !== null && bird.faint === false);
		flying.root.visible = (flight !== null && bird.faint === false);

		if(flight !== null) {

			const k = flight.elapsed / flight.duration;
			// Départ et arrivée en douceur, le long d'un arc qui s'écarte de la ligne droite et monte en son milieu
			const s = k * k * (3 - 2 * k);
			const arc = Math.sin(Math.PI * s);
			const swing = Math.cos(Math.PI * s) * Math.PI;

			const vx = flight.span.x + flight.aside.x * swing;
			const vn = flight.span.n + flight.aside.n * swing;
			const flap = Math.sin(t * 22) * .6;

			flying.root.position.set(flight.from.x + flight.span.x * s + flight.aside.x * arc, flight.base + (flight.to.height - flight.base) * s + flight.peak * arc + .05 * scale, -(flight.from.n + flight.span.n * s + flight.aside.n * arc));
			flying.root.rotation.set(flight.bank * arc, Math.atan2(vn, vx), Math.atan2(flight.peak * swing, Math.hypot(vx, vn)) * .7);
			flying.root.scale.setScalar(scale);

			flying.bones['aile-g'].rotation.x = flap;
			flying.bones['aile-d'].rotation.x = -flap;
			flying.bones.queue.rotation.z = -.15 * swing / Math.PI;

			return;

		}

		if(bird.place === null) {
			return;
		}

		// Posé, il sautille par moments en se tournant, et picore le reste du temps
		const hopping = (Math.sin(t * .9) > .6);
		const peck = hopping ? 0 : Math.max(0, Math.sin(t * 2.3)) ** 4;

		perched.root.position.set(bird.place.p.x, bird.place.height + (hopping ? Math.abs(Math.sin(t * 9)) * .04 * scale : 0), -bird.place.p.n);
		perched.root.rotation.set(0, bird.heading + .5 * Math.sin(t * .4) + .3 * Math.sin(t * 1.1), -.25 * peck);
		perched.root.scale.setScalar(scale);

		perched.bones.tete.rotation.z = -.9 * peck;
		perched.bones.queue.rotation.z = .15 * Math.sin(t * 5);

	}

	// Les pixels par mètre tiennent à la hauteur du canevas autant qu'au cadrage : la hauteur se relit à l'image suivante, et la
	// taille et le cadre utile avec elle
	resize() {
		this.height = null;
	}

	dispose() {

		for(const root of this.roots) {

			this.parent.remove(root);

			// Les géométries sont celles de ce montage, la matière est celle de l'équipe, qui la libère elle-même
			root.traverse(object => {
				if(object.isMesh) {
					object.geometry.dispose();
				}
			});

		}

	}

}
