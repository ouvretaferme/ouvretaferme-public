/**
 * Les membres de la structure au travail dans les passe-pieds, autant que l'équipe du planning compte de présents le jour
 * montré (`plan.crew`). Un clic sur l'un d'eux ne fait rien.
 *
 * Quand la scène reçoit l'équipe avec ses avatars (`plan.team`, `map\SceneLib::getCrew()`), un présent qui a le sien paraît sous son
 * modèle téléversé ou sa composition, garde ce corps d'un jour à l'autre et passe avant les autres les jours où l'équipe dépasse MAX.
 * Les autres, et tous sans `plan.team`, sont des paysans anonymes, qui ne portent rien du membre qu'ils comptent.
 *
 * Ils vont d'un passe-pied à l'autre par un réseau de marche qui ne traverse ni une planche, ni un abri, ni le trait entre deux
 * îlots, et ne se croisent jamais : qui partirait sur la route d'un autre reste à sa tâche et tire une autre destination.
 *
 * Un script classique, posé après `plan.js` par la scène qui s'en sert (`Asset::js('scene', 'crew.js')`) : three, les
 * personnages et la paire cuite lui arrivent par `socle`, ce que rend `SceneLoader.loadCrop()`. Pas un module ES : elle lit
 * `ScenePlanGeometry` en classe globale, et `scene.js` lit ses statiques dans sa boucle d'images, sans import à attendre.
 */
class SceneCrew {

	// Un paysan coûte une dizaine d'appels de dessin : au-delà de neuf, l'équipe en demanderait autant que tout le reste de la scène
	static MAX = 9;

	// Une bouffée de poussière à chaque pas, prise dans une réserve tournante, un seul appel de dessin pour toute l'équipe. Elle
	// gonfle puis s'efface en DUST_LIFE secondes, en montant de DUST_RISE mètres
	static DUST = 120;
	static DUST_COLOR = '#e6d3a4';
	static DUST_SIZE = .14;
	static DUST_LIFE = .7;
	static DUST_RISE = .35;

	// En mètres par seconde de temps réel, et en radians par seconde : un demi-tour prend plus d'une seconde. Un paysan ne marche
	// qu'à moins de TURN_GATE de sa route, sinon il piétine en se tournant
	static WALK_SPEED = 1.15;
	static TURN_SPEED = 2.5;
	static TURN_GATE = 1;

	// Un paysan se lit de loin par un grossissement fixe, et non celui des cultures, qui lui ferait enjamber trois planches. Il reste
	// sous le faîte de l'abri le plus bas de la structure
	static HEIGHT = 1.8;
	static SCALE_MAX = 1.34;

	// Personne ne travaille sous un abri : la tête dépasserait la bâche. On le contourne à cette distance, brouette comprise
	static GREENHOUSE_MARGIN = 1;

	// La moitié du long côté d'une caisse, en mètres du corps, sa largeur suivant le modèle : elle grossit avec le paysan, comme ses
	// outils, sans quoi la prise dépendrait de la structure et une caisse fixe en mètres sortirait de la portée des bras sur un
	// paysan à taille réelle
	static CRATE_LONG = .22;

	// Tenue contre la poitrine (figure.hold), la caisse dépasse loin devant son porteur et balaierait le bord des planches s'il
	// tournait en marchant. Dans un virage, et avant un coude de sa route, il la serre donc plus haut (farmer.lift va de 0, portée
	// bas, à 1, serrée), et il ne marche qu'à moins de CRATE_TURN de sa route : au-delà, il s'arrête et pivote sur place
	// (crateManoeuvre). Son buste devance ses jambes d'au plus CRATE_TWIST, et la caisse avec lui : c'est le buste qui tourne le
	// premier quand on manœuvre une charge. Montée et descente en fraction du geste par seconde
	static CRATE_TURN = .12;
	static CRATE_TWIST = .5;
	static CRATE_RAISE = 12;
	static CRATE_LOWER = 3;

	// La caisse dépasse de près d'un mètre devant son porteur, et un passe-pied est plus étroit qu'elle n'est longue : au nœud même
	// d'un coude, elle entre souvent dans la planche d'en face avant qu'il y soit. Le porteur pivote donc là où elle tourne sans
	// toucher de planche, au nœud ou à moins de CRATE_MANOEUVRE mètres sur l'un des deux tronçons, et passe le nœud en pas chassés.
	// Un geste se juge par pas de CRATE_MANOEUVRE_STEP mètres et CRATE_MANOEUVRE_ANGLE radians, sur CRATE_MANOEUVRE_YAWS
	// orientations pour passer le nœud, et deux gestes à CRATE_MANOEUVRE_SLACK mètres près se départagent par le plus simple
	static CRATE_MANOEUVRE = 1.2;
	static CRATE_MANOEUVRE_STEP = .1;
	static CRATE_MANOEUVRE_ANGLE = .15;
	static CRATE_MANOEUVRE_YAWS = 11;
	static CRATE_MANOEUVRE_SLACK = .01;

	// Portée à hauteur de poitrine, la caisse peut frôler le bord d'une planche de CRATE_GRAZE mètres ; là où la route fait marcher son
	// porteur dans une planche, elle peut y entrer autant que lui. Au-delà, elle est de trop
	static CRATE_GRAZE = .1;

	// Une caisse se juge par ses coins et le milieu de ses côtés, en demi-longueurs et demi-largeurs depuis son milieu
	static CRATE_PROBES = [[1, 1], [1, -1], [-1, 1], [-1, -1], [1, 0], [-1, 0], [0, 1], [0, -1]];

	// La brouette (base/scene/model/brouette.js) a son origine à BARROW_AHEAD devant le paysan, en mètres du modèle. Ses poignées sont
	// à hauteur de hanche, plus haut que des poings bras tombants : pour qu'elles arrivent aux poings, les bras partent en avant et la
	// brouette s'éloigne d'autant. Poussée, elle bascule de BARROW_TILT sur l'axe de sa roue, ce qui lève ses pieds de 7 cm du modèle,
	// et le buste se penche de BARROW_BEND
	static BARROW_AHEAD = 1.14;
	static BARROW_TILT = .08;
	static BARROW_BEND = .1;

	// Le contenu d'une caisse : tomates, courges, salades
	static CRATE_CONTENTS = ['#d6452f', '#f2b632', '#9ccc65'];

	// Pendant la lecture, une pause de travail dure trois fois moins, et un paysan arrive ou part en 0,4 s, à la vitesse Normale :
	// les deux suivent la vitesse choisie, pour qu'une semaine laisse à l'équipe le même temps de jeu à toutes les vitesses
	static PLAY_REST = 3;
	static PLAY_FADE = .4;

	// Un coin du réseau se relie à ses huit voisins les plus proches à moins de 70 m : tout tester deux à deux prendrait une seconde
	// sur une grande structure
	static JOIN = 70;
	static NEIGHBOURS = 8;

	// La grille des obstacles, en mètres : un segment ne teste que ceux des cases qu'il survole
	static CELL = 16;

	// Le bout d'un passe-pied, à la première de ces distances de la planche que le décor laisse libre
	static END_STEPS = [.7, .5, .3, .1];

	// Les deux nœuds d'un portail, à la première de ces distances de part et d'autre de son ouverture qui ne touche aucun obstacle :
	// au-delà de la haie d'un enclos quand rien ne l'empêche, d'où l'on voit le reste de la structure
	static GATE_STEPS = [3.5, 3, 2.5, 2, 1.5, 1, .75, .5, .25, 0];

	// Où l'on franchit un portail, en part de la demi-largeur de son ouverture depuis son milieu : un élément ou une planche posés
	// sur le milieu laissent passer à côté, et seule une ouverture prise d'un bord à l'autre se ferme
	static GATE_SLIDES = [0, .3, -.3, .6, -.6, .9, -.9];

	// Le côté du tour où mène chaque nœud d'un portail se lit à GATE_PROBE mètre de l'ouverture : le nœud lui-même peut être sur la
	// clôture, quand une planche la serre
	static GATE_PROBE = .3;

	// Un lien jeté au travers du décor pour sortir d'un enclos se vérifie tous les BRIDGE_STEP mètres : il reste de son côté du tour
	static BRIDGE_STEP = .25;

	// Les six paysans anonymes dans l'ordre où l'équipe se remplit, chacun à son geste, l'outil dans le modèle. Quatre gestes pour six
	// corps : deux paysans voisins dans l'ordre ne font jamais le même, et la liste reprend au début au-delà de six
	static CAST = [
		{ role: 'hoe', body: 'paysan-a' },
		{ role: 'crate', body: 'paysan-c' },
		{ role: 'barrow', body: 'paysan-d' },
		{ role: 'fork', body: 'paysan-b' },
		{ role: 'crate', body: 'paysan-e' },
		{ role: 'barrow', body: 'paysan-f' }
	];

	// Le geste d'un avatar, par rang : la houe et la fourche sont cuites dans les corps anonymes, la caisse et la brouette se
	// montent à part sur n'importe quel corps
	static AVATAR_ROLES = ['crate', 'barrow'];

	/**
	 * Ce que `SceneCrew` reçoit en `avatars` pour `plan`, `{ loaded, team }` : le corps commun (`SceneLoader.loadAvatar()`), et
	 * pour chaque entrée de `plan.team` sa fiche et son modèle téléversé lu (`SceneLoader.readUpload()`), null pour un corps
	 * anonyme. Rend null sans rien importer quand aucune entrée n'a d'avatar, et quand le corps commun ne se charge pas :
	 * l'équipe reste alors anonyme plutôt que la scène tombe. `container` porte `data-scene-directory`.
	 */
	static prepare(container, plan) {

		if(plan.team.some(member => member !== null) === false) {
			return Promise.resolve(null);
		}

		return Promise.all([
			SceneLoader.loadAvatar(container),
			...plan.team.map(member => (member === null || member.upload === null) ? null : SceneLoader.readUpload(member.upload))
		])
			.then(([loaded, ...uploads]) => ({
				loaded,
				team: plan.team.map((member, rank) => (member === null) ? null : { fiche: member.fiche, upload: uploads[rank] })
			}))
			.catch(() => null);

	}

	// Le hasard vient de l'appelant, comme pour tout le socle : random(graine) rend une fonction, dont chaque appel tire de 0 à 1
	// `avatars` est ce que rend `prepare()`, null pour une équipe anonyme
	constructor(socle, plan, geometry, parent, gradientMap, solids, gates, random, avatars = null) {

		const { THREE } = socle;

		this.THREE = THREE;
		this.figure = socle.figure;
		this.baked = socle.baked;
		this.parent = parent;
		this.random = random;
		this.avatars = avatars;
		this.geometry = geometry;

		const days = plan.crew.map(row => Object.fromEntries(plan.columns.crew.map((column, index) => [column, row[index]])));

		this.days = new Map(days.map(day => [day.date, day.members]));
		// Les rangs dans `plan.team` des présents de chaque jour, que seule une équipe avec ses avatars a
		this.lineups = (avatars === null) ? null : new Map(days.map(day => [day.date, day.team]));

		const tunnels = [...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ plot, rectangle }) => ScenePlanGeometry.greenhouse(plot.decorGreenhouse, rectangle.width).height);
		this.scale = Math.max(1, Math.min(SceneCrew.SCALE_MAX, .97 * Math.min(Infinity, ...tunnels) / SceneCrew.HEIGHT));

		// D'une épaule à l'autre, bras compris : deux paysans ne passent jamais à moins d'une carrure et de la marge de leurs bras
		this.shoulder = .64 * this.scale;
		this.passRoom = this.shoulder * 1.4;
		// Une brouette se pousse à son origine, puis s'étend encore de .75 m du modèle devant elle
		this.reach = (SceneCrew.BARROW_AHEAD + .75) * this.scale;
		// Du centre du corps au centre de la caisse tenue, et la demi-largeur de la caisse, lus sur les corps qui la portent
		this.crateHeld = 0;
		this.crateWide = 0;
		this.crateAside = (.4 + SceneCrew.CRATE_LONG) * this.scale;

		// Les planches que la caisse d'un porteur ne doit pas balayer, et la manœuvre déjà jugée à chaque coude
		this.beds = geometry.beds;
		this.manoeuvres = new Map();

		// Ce que le réseau a déjà jugé, un passage entre deux points et la place d'une brouette autour d'un point : un geste sur le
		// décor n'en fait rejuger que ce qui est près de ce qui a bougé
		this.passes = new Map();
		this.rooms = new Map();
		this.joins = { layout: null, nodes: [] };

		// Les planches que cueillent les porteurs de caisse, que seul le bourg donne (`gather()`), les passe-pieds qui les longent, et
		// celui où ils attendent qu'il y en ait
		this.picked = null;
		this.edge = null;
		this.harvested = [];
		this.waiting = null;

		this.network(geometry, solids, gates);

		this.material = new THREE.MeshToonMaterial({ vertexColors: true, gradientMap });
		// L'ombre portée d'un paysan est un disque au sol : une ombre calculée la doublerait
		this.shadowMaterial = new THREE.MeshBasicMaterial({ color: '#000000', transparent: true, opacity: .2, depthWrite: false });
		this.shadowGeometry = new THREE.CircleGeometry(.5, 16).rotateX(-Math.PI / 2);

		// Les corps anonymes, puis les avatars, chacun ajouté au premier jour où il paraît (`actor()`)
		this.cast = (this.alleys.length > 0) ? Array.from({ length: SceneCrew.MAX }, (_, i) => this.build(socle.baked, i)) : [];
		this.farmers = [...this.cast];
		this.actors = new Map();

		this.dust = new THREE.InstancedMesh(new THREE.IcosahedronGeometry(SceneCrew.DUST_SIZE, 0), new THREE.MeshBasicMaterial({ color: SceneCrew.DUST_COLOR, transparent: true, opacity: .6, depthWrite: false }), SceneCrew.DUST);
		this.dust.frustumCulled = false;
		this.dust.count = 0;
		this.parent.add(this.dust);
		this.puffs = Array.from({ length: SceneCrew.DUST }, () => ({ start: -Infinity, at: new THREE.Vector3() }));
		this.puffNext = 0;
		this.puffMatrix = new THREE.Object3D();

		this.date = null;
		this.playing = false;
		this.speed = 1;
		this.last = null;

	}

	/**
	 * Le réseau de marche : un nœud à chaque bout de passe-pied et à sa tournière, assez loin de la planche pour qu'une brouette y
	 * pivote, un poste devant chaque abri d'où l'on y travaille du dehors faute de passe-pied (`alleysFirst()`), et des liens
	 * entre les coins voisins qui ne traversent aucun obstacle. Deux îlots rapprochés restent à des centaines de mètres l'un de
	 * l'autre : aucun lien ne les joint. Le décor posé, `solids`, se contourne au ras de ce qu'il dresse : personne n'y travaille.
	 * Un portail, `gates`, se franchit : un nœud de chaque côté de son ouverture, reliés à travers elle, et chacun joint à ses
	 * voisins de son côté, avec les coins du tour de son enclos par dedans.
	 */
	network(geometry, solids, gates) {

		const G = ScenePlanGeometry;
		const keep = SceneCrew.GREENHOUSE_MARGIN + this.reach;
		const turnRoom = this.reach + .2;

		const around = (r, margin) => ({ origin: G.sub(r.origin, G.add(G.mul(r.u, margin), G.mul(r.w, margin))), u: r.u, w: r.w, length: r.length + 2 * margin, width: r.width + 2 * margin });

		const greenhouses = [...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ rectangle }) => rectangle);

		// Une planche se contourne à 4 cm de son bord, un abri à distance de brouette ; `firm` est ce qu'une brouette qui pivote ne
		// doit pas toucher, l'abri compté à sa seule marge
		const obstacles = [
			...geometry.beds.map(bed => SceneCrew.obstacle(bed, .04, bed)),
			...greenhouses.map(r => SceneCrew.obstacle(around(r, keep), 0, around(r, SceneCrew.GREENHOUSE_MARGIN))),
			...solids.map(r => SceneCrew.obstacle(r, 0, r))
		];

		const walls = obstacles.slice(obstacles.length - solids.length);

		const CELL = SceneCrew.CELL;
		const grid = new Map();
		const cells = (minX, maxX, minN, maxN, callback) => {
			for(let i = Math.floor(minX / CELL); i <= Math.floor(maxX / CELL); i++) {
				for(let j = Math.floor(minN / CELL); j <= Math.floor(maxN / CELL); j++) {
					callback(i * 100003 + j);
				}
			}
		};

		for(const o of obstacles) {
			cells(o.minX, o.maxX, o.minN, o.maxN, key => {
				if(grid.has(key) === false) {
					grid.set(key, []);
				}
				grid.get(key).push(o);
			});
		}

		// Les obstacles des cases qu'une emprise survole, chacun une fois
		const near = (minX, maxX, minN, maxN) => {
			const found = new Set();
			cells(minX, maxX, minN, maxN, key => (grid.get(key) ?? []).forEach(o => found.add(o)));
			return found;
		};

		// Les jugements que cette passe a lus : les autres portent sur des nœuds qui n'existent plus, et se retirent à la fin
		const passesSeen = new Set();
		const roomsSeen = new Set();

		const clear = (a, b) => {

			const key = a.x +' '+ a.n +' '+ b.x +' '+ b.n;
			passesSeen.add(key);

			if(this.passes.has(key) === false) {
				const open = [...near(Math.min(a.x, b.x), Math.max(a.x, b.x), Math.min(a.n, b.n), Math.max(a.n, b.n))].every(o => SceneCrew.spanIn(a, b, o) === null);
				this.passes.set(key, { a, b, open });
			}

			return this.passes.get(key).open;

		};

		// Un îlot du foncier par zone : deux nœuds d'îlots différents ne se relient jamais
		const islandOfZone = new Map();
		geometry.land.islands.forEach((island, i) => island.zones.forEach(zone => islandOfZone.set(zone.id, i)));
		// Une zone hors des ilots est un ilot à elle seule, et ce qui n'a pas de zone n'est relié à rien : rien ne dit qu'on passe à pied
		// de l'un à l'autre
		const islandOf = zone => islandOfZone.get(zone) ?? (zone === null ? Symbol() : 'zone-'+ zone);

		this.nodes = [];

		const node = (p, island) => this.nodes.push({ p, island, links: [] }) - 1;
		const link = (i, j) => {
			const cost = G.length(G.sub(this.nodes[i].p, this.nodes[j].p));
			this.nodes[i].links.push([j, cost]);
			this.nodes[j].links.push([i, cost]);
		};

		const alleys = [];
		const corners = [];
		// Le sens dans lequel on sort d'un passe-pied par chacun de ses bouts
		const outward = new Map();

		for(const group of SceneCrew.groups(geometry.beds)) {

			const island = islandOf(group.zone);
			const at = (w, u) => G.add(G.add(group.origin, G.mul(group.w, w)), G.mul(group.u, u));

			const beds = group.beds;
			const lines = [{ w: beds[0].w0 - .3, from: beds[0].u0, to: beds[0].u1, beds: [beds[0].id] }];

			for(let i = 0; i < beds.length - 1; i++) {
				if(beds[i + 1].w0 - beds[i].w1 > .2) {
					lines.push({ w: (beds[i].w1 + beds[i + 1].w0) / 2, from: Math.min(beds[i].u0, beds[i + 1].u0), to: Math.max(beds[i].u1, beds[i + 1].u1), beds: [beds[i].id, beds[i + 1].id] });
				}
			}

			lines.push({ w: beds.at(-1).w1 + .3, from: beds.at(-1).u0, to: beds.at(-1).u1, beds: [beds.at(-1).id] });

			const rows = lines.map(line => {

				// Le décor qui barre le bout d'un passe-pied, la clôture d'un enclos serré sur son jardin par exemple, en rapproche le bout
				// de la planche plutôt que de le fermer ; une planche ou un abri le ferment comme avant
				const end = (u, out) => {

					const edge = at(line.w, u);
					const far = at(line.w, u + out * SceneCrew.END_STEPS[0]);

					if(walls.every(o => SceneCrew.spanIn(edge, far, o) === null)) {
						return far;
					}

					// Barré jusqu'au ras de la planche, le bout reste au plus loin : le passe-pied tombe alors au test de ses deux bouts
					return SceneCrew.END_STEPS.map(step => at(line.w, u + out * step)).find(p => clear(edge, p)) ?? far;

				};

				const ends = [end(line.from, -1), end(line.to, 1)].map(p => node(p, island));
				const turns = [at(line.w, line.from - turnRoom), at(line.w, line.to + turnRoom)].map(p => node(p, island));

				for(const n of [ends[0], turns[0]]) {
					outward.set(n, G.mul(group.u, -1));
				}

				for(const n of [ends[1], turns[1]]) {
					outward.set(n, group.u);
				}

				const reached = ends.map((end, i) => clear(this.nodes[end].p, this.nodes[turns[i]].p));

				reached.forEach((open, i) => {
					if(open) {
						link(ends[i], turns[i]);
					}
				});

				if(clear(this.nodes[ends[0]].p, this.nodes[ends[1]].p)) {
					link(ends[0], ends[1]);
					alleys.push({ top: ends[0], bottom: ends[1], group: group.key, zone: group.zone, a: group.w, turns: reached.every(open => open) ? turns : null, beds: line.beds });
				}

				return [...ends, ...turns];

			});

			rows.forEach((row, i) => {
				if(i > 0) {
					row.forEach((n, k) => {
						if(clear(this.nodes[rows[i - 1][k]].p, this.nodes[n].p)) {
							link(rows[i - 1][k], n);
						}
					});
				}
			});

			corners.push(...rows[0], ...rows.at(-1));

		}

		// Ce qu'une brouette tendue vers son abri ne doit pas toucher : les autres abris à leur marge, et les planches
		const standing = obstacles.filter(o => o.greenhouse === false);
		const glass = greenhouses.map(r => SceneCrew.obstacle(around(r, SceneCrew.GREENHOUSE_MARGIN), 0, null));

		[...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').forEach(({ plot, rectangle: r }, g) => {

			const island = islandOf(plot.zone);
			const margin = keep + .3;
			const edge = around(r, margin);

			corners.push(...SceneCrew.corners(edge).map(p => node(p, island)));

			const on = (along, across) => G.add(edge.origin, G.add(G.mul(edge.u, along), G.mul(edge.w, across)));

			// Du poste à l'abri, la brouette tient debout : rien d'autre que cet abri entre les deux
			const reaches = (p, face) => {
				const tip = G.add(p, G.mul(face, this.reach));
				return [...standing, ...glass.filter((o, other) => other !== g)].every(o => SceneCrew.spanIn(p, tip, o) === null);
			};

			const post = (p, q, a, face) => {

				if(clear(p, q) === false || reaches(p, face) === false || reaches(q, face) === false) {
					return false;
				}

				const top = node(p, island);
				const bottom = node(q, island);
				link(top, bottom);
				alleys.push({ top, bottom, group: 'abri '+ g, zone: plot.zone, a, face, beds: geometry.beds.filter(bed => bed.plot === plot.id).map(bed => bed.id) });
				corners.push(top, bottom);

				return true;

			};

			// Un poste devant chaque pignon, où l'on se tient face à l'abri
			const gables = [[0, r.u], [edge.length, G.mul(r.u, -1)]].filter(([along, face]) => post(on(along, margin + .25 * r.width), on(along, margin + .75 * r.width), r.w, face));

			if(gables.length > 0) {
				return;
			}

			// Les deux pignons pris dans un obstacle : le poste glisse le long d'un pied droit, au plus près d'un pignon
			for(let slide = 0; slide + 2 <= r.length; slide += 2) {
				if([[0, r.w], [edge.width, G.mul(r.w, -1)]].some(([across, face]) => [slide, r.length - slide - 2].some(start => post(on(margin + start, across), on(margin + start + 2, across), r.u, face)))) {
					break;
				}
			}

		});

		// L'îlot d'un portail est celui du nœud le plus proche : un enclos se pose sur une zone ou une parcelle, un portail seul près
		// de ce qu'il ouvre
		const gated = new Set();
		const passages = [];

		for(const gate of gates) {

			const closest = this.nodes.reduce((best, { p, island }) => {
				const distance = G.length(G.sub(p, gate.p));
				return (distance < best.distance) ? { distance, island } : best;
			}, { distance: SceneCrew.JOIN, island: null });

			if(closest.island === null) {
				continue;
			}

			// Chaque nœud s'écarte de l'ouverture autant qu'il le peut sans entrer dans une planche, qu'un enclos serre souvent de près
			const cross = at => [-1, 1].map(k => SceneCrew.GATE_STEPS.map(step => G.add(at, G.mul(gate.across, k * step))).find(p => clear(at, p)));
			const ends = SceneCrew.GATE_SLIDES.map(f => cross(G.add(gate.p, G.mul(gate.along, f * gate.half)))).find(pair => pair.includes(undefined) === false);

			// Une ouverture prise d'un bord à l'autre se passe quand même : l'enclos fermerait sinon son jardin, et ses nœuds se
			// relient plus bas au plus proche de chaque côté
			const sides = (ends ?? [-1, 1].map(k => G.add(gate.p, G.mul(gate.across, k * SceneCrew.GATE_STEPS.at(-2))))).map(p => node(p, closest.island));
			const round = gate.round.map(p => node(p, closest.island));

			link(sides[0], sides[1]);
			passages.push({ gate, sides, own: new Set([...sides, ...round]) });

			// Le tour d'un enclos par dedans mène du portail au bout des passe-pieds, qu'une clôture serrée sur le jardin a rapprochés
			corners.push(...sides, ...round);
			[...sides, ...round].forEach(n => gated.add(n));

		}

		// Un lien qui repart le long de la planche qu'on vient de quitter ferait rebrousser chemin dans l'herbe, au bout du passe-pied
		const back = (from, to) => outward.has(from) && G.dot(G.norm(G.sub(this.nodes[to].p, this.nodes[from].p)), outward.get(from)) < -.8;

		// Les plus proches d'un coin se rangent à mesure, dans l'ordre d'un tri stable : trier tous les coins de l'îlot pour chacun
		// d'eux prenait plusieurs centaines de millisecondes sur une grande structure
		const select = i => {

			const closest = [];

			for(const j of corners) {

				// Un nœud de portail ou de tour se joint lui-même à ce qu'il voit : derrière une clôture, il prendrait la place d'un voisin
				if(j === i || gated.has(j) || this.nodes[j].island !== this.nodes[i].island) {
					continue;
				}

				const distance = G.length(G.sub(this.nodes[j].p, this.nodes[i].p));

				if(distance >= SceneCrew.JOIN || (closest.length === SceneCrew.NEIGHBOURS && distance >= closest.at(-1)[1])) {
					continue;
				}

				let k = closest.length;

				while(k > 0 && closest[k - 1][1] > distance) {
					k--;
				}

				closest.splice(k, 0, [j, distance]);

				if(closest.length > SceneCrew.NEIGHBOURS) {
					closest.pop();
				}

			}

			return closest.map(([j]) => j);

		};

		// Un nœud de portail ou du tour d'un enclos a ses plus proches voisins de l'autre côté de la clôture : il se joint aux plus
		// proches qu'il voit, pris du plus près au plus loin
		const visible = i => {

			const seen = [];
			const candidates = corners
				.filter(j => j !== i && this.nodes[j].island === this.nodes[i].island)
				.map(j => [j, G.length(G.sub(this.nodes[j].p, this.nodes[i].p))])
				.filter(([, distance]) => distance < SceneCrew.JOIN)
				.sort((a, b) => a[1] - b[1]);

			for(const [j] of candidates) {

				if(seen.length === SceneCrew.NEIGHBOURS) {
					break;
				}

				if(clear(this.nodes[i].p, this.nodes[j].p)) {
					seen.push(j);
				}

			}

			return seen;

		};

		// Les voisins ne dépendent que de la place des coins et de leurs îlots : tant qu'un geste sur le décor n'ajoute ni ne retire
		// de coin, un portail ou le poste d'un abri, ils se reprennent tels quels. Deux coins sans zone ont chacun leur îlot, que la
		// clé dit par le rang du premier coin qui le porte
		const first = new Map();
		const layout = corners.map(i => {
			const { p, island } = this.nodes[i];
			if(first.has(island) === false) {
				first.set(island, first.size);
			}
			return p.x +' '+ p.n +' '+ first.get(island);
		}).join('|');

		if(this.joins.layout !== layout) {
			this.joins = { layout, nodes: corners.map(i => gated.has(i) ? [] : select(i)) };
		}

		corners.forEach((i, rank) => {
			for(const j of (gated.has(i) ? visible(i) : this.joins.nodes[rank])) {
				if(this.nodes[i].links.some(([k]) => k === j) === false && back(i, j) === false && back(j, i) === false && clear(this.nodes[i].p, this.nodes[j].p)) {
					link(i, j);
				}
			}
		});

		// Le réseau se découpe en morceaux reliés : un paysan reste dans le sien, et ne traverse jamais à pied vers une zone lointaine.
		// Un morceau porte le plus petit indice de ses nœuds, qui en est un : après un lien de plus, `piece[i]` relit le morceau du
		// nœud i, quel qu'il soit devenu
		const pieces = () => {

			const piece = new Int32Array(this.nodes.length).fill(-1);

			for(let start = 0; start < this.nodes.length; start++) {

				if(piece[start] !== -1) {
					continue;
				}

				const stack = [start];
				piece[start] = start;

				while(stack.length > 0) {
					for(const [j] of this.nodes[stack.pop()].links) {
						if(piece[j] === -1) {
							piece[j] = start;
							stack.push(j);
						}
					}
				}

			}

			return piece;

		};

		// Un enclos n'isole jamais son intérieur. Le nœud de son portail côté dehors rejoint le morceau le plus proche qui porte un
		// passe-pied au dehors, et côté dedans chaque morceau qui porte un passe-pied rejoint le portail. Un tel lien passe au besoin
		// à travers une planche ; il ne passe à travers le décor ou un abri que si aucun autre ne relie les deux morceaux, un portail
		// serré contre la haie d'un autre enclos par exemple, et ne franchit jamais le tour de son propre enclos
		const firm = [...walls, ...glass];
		const free = (a, b) => firm.every(o => SceneCrew.spanIn(a, b, o) === null);
		const alleyEnds = alleys.flatMap(alley => [alley.top, alley.bottom]);

		// Le lien le plus court d'un nœud de `from` à un nœud de `to` que rien ne barre, sinon qui reste de son côté du tour
		const bridge = (from, to, ring, within) => {

			const pairs = from.flatMap(i => to.map(j => [i, j, G.length(G.sub(this.nodes[i].p, this.nodes[j].p))])).sort((x, y) => x[2] - y[2]);
			const aside = ([i, j, length]) => {
				const steps = Math.ceil(length / SceneCrew.BRIDGE_STEP);
				return Array.from({ length: steps + 1 }, (_, k) => G.lerp(this.nodes[i].p, this.nodes[j].p, k / steps)).every(p => G.inside(p, ring) === within);
			};
			const found = pairs.find(([i, j]) => free(this.nodes[i].p, this.nodes[j].p)) ?? pairs.find(aside);

			if(found === undefined) {
				return false;
			}

			link(found[0], found[1]);
			return true;

		};

		for(const { gate, sides, own } of passages.filter(({ gate }) => gate.ring.length > 0)) {

			sides.forEach((side, k) => {

				const within = G.inside(G.add(gate.p, G.mul(gate.across, (2 * k - 1) * SceneCrew.GATE_PROBE)), gate.ring);
				const mine = j => own.has(j) === false && this.nodes[j].island === this.nodes[side].island && G.inside(this.nodes[j].p, gate.ring) === within;
				const nodes = this.nodes.map((n, j) => j).filter(mine);

				let piece = pieces();
				const anchored = [...new Set(alleyEnds.filter(mine).map(j => piece[j]))];

				if(within === false) {
					if(anchored.includes(piece[side]) === false) {
						bridge([side], nodes.filter(j => anchored.includes(piece[j])), gate.ring, within);
					}
					return;
				}

				for(const anchor of anchored) {
					if(piece[anchor] !== piece[side] && bridge(nodes.filter(j => piece[j] === piece[anchor]), [side, ...nodes].filter(j => piece[j] === piece[side]), gate.ring, within)) {
						piece = pieces();
					}
				}

			});

		}

		const component = pieces();

		// Là où une brouette pivote sans rien toucher : aucun bord d'obstacle à sa portée. La distance se prend aux quatre bords, une
		// planche en biais n'ayant pas ses coordonnées à angle droit
		const roomy = p => {

			const key = p.x +' '+ p.n;
			roomsSeen.add(key);

			if(this.rooms.has(key) === false) {
				const open = [...near(p.x - this.reach, p.x + this.reach, p.n - this.reach, p.n + this.reach)].every(o => {
					const c = SceneCrew.corners(o.firm);
					return c.every((corner, i) => G.distanceToSegment(p, corner, c[(i + 1) % 4]) >= this.reach);
				});
				this.rooms.set(key, { p, open });
			}

			return this.rooms.get(key).open;

		};

		this.roomy = this.nodes.map(({ p }) => roomy(p));

		// Une brouette n'entre que là où elle tourne à chaque bout ; à un poste d'abri, elle pivote sur place
		const wheeled = alley => alley.face
			? [0, .25, .5, .75, 1].every(k => roomy(G.lerp(this.nodes[alley.top].p, this.nodes[alley.bottom].p, k)))
			: (alley.turns !== null && alley.turns.every(n => this.roomy[n]));

		this.alleys = alleys.map(alley => ({ ...alley, component: component[alley.top], barrow: wheeled(alley) }));
		this.alleyOfLink = new Map(this.alleys.map(alley => [SceneCrew.linkKey(alley.top, alley.bottom), alley]));
		this.solids = solids;

		this.passes.forEach((pass, key) => {
			if(passesSeen.has(key) === false) {
				this.passes.delete(key);
			}
		});

		this.rooms.forEach((room, key) => {
			if(roomsSeen.has(key) === false) {
				this.rooms.delete(key);
			}
		});

	}

	/**
	 * Le réseau retracé d'après le décor du moment. Un paysan garde son passe-pied et sa route quand le passe-pied existe encore,
	 * au même endroit, qu'il y tient encore, et qu'aucun élément du décor ne barre la route ; sinon il reprend une place, comme au
	 * premier jour. Une brouette ne tient que dans un passe-pied qui la laisse tourner, et le même endroit retracé peut ne plus le
	 * faire. La route ne se juge que contre le décor : celle d'un porteur de caisse frôle les planches en pas chassés, et rien de ce
	 * qu'un geste change ne les déplace. Le réseau se retrace en entier, ses liens joignant des voisins à soixante-dix mètres et ses
	 * morceaux se comptant sur toute la structure : seuls les jugements de passage et de place loin de ce qui a bougé se reprennent.
	 */
	reroute(geometry, solids, gates) {

		// Ce qui a bougé : les rectangles du décor d'avant qui ne sont plus, et ceux d'après qui n'étaient pas
		const sign = r => [r.origin.x, r.origin.n, r.u.x, r.u.n, r.length, r.width].join(' ');
		const was = new Set(this.solids.map(sign));
		const is = new Set(solids.map(sign));
		const moved = [...this.solids.filter(r => is.has(sign(r)) === false), ...solids.filter(r => was.has(sign(r)) === false)].map(r => SceneCrew.obstacle(r, 0, r));

		// Un passage ne change que si son segment traverse ce qui a bougé, et la place d'une brouette que si ce qui a bougé est à sa
		// portée : la boite d'un lien de soixante-dix mètres en biais couvrirait la moitié de la structure
		this.passes.forEach(({ a, b }, key) => {
			if(moved.some(o => SceneCrew.spanIn(a, b, o) !== null)) {
				this.passes.delete(key);
			}
		});

		this.rooms.forEach(({ p }, key) => {
			if(moved.some(o => o.minX <= p.x + this.reach && o.maxX >= p.x - this.reach && o.minN <= p.n + this.reach && o.maxN >= p.n - this.reach)) {
				this.rooms.delete(key);
			}
		});

		const where = alley => alley.group +' '+ [alley.top, alley.bottom].map(n => this.nodes[n].p).map(p => p.x.toFixed(2) +' '+ p.n.toFixed(2)).join(' ');
		const before = new Map(this.alleys.map(alley => [alley, where(alley)]));

		this.network(geometry, solids, gates);
		this.manoeuvres.clear();
		this.pick();

		const after = new Map(this.alleys.map(alley => [where(alley), alley]));
		const same = alley => after.get(before.get(alley));
		const barriers = solids.map(r => SceneCrew.obstacle(r, 0, r));

		for(const farmer of this.farmers.filter(farmer => farmer.alley !== null)) {

			const alley = same(farmer.alley);
			const free = farmer.path.slice(1).every((q, i) => barriers.every(o => SceneCrew.spanIn(farmer.path[i], q, o) === null));

			if(alley !== undefined && free && this.fits(farmer, alley)) {

				farmer.alley = alley;
				farmer.through = farmer.through.map(same).filter(through => through !== undefined);

			} else if(farmer.on) {
				this.seat(farmer, farmer.r);
			} else {
				// Il s'effaçait d'une place qui n'existe plus ou qui ne lui laisse plus tourner sa brouette : rappelé, il en reprendra une
				farmer.shown = 0;
			}

		}

	}

	/**
	 * Les planches d'un même jardin rangées côte à côte, même sens et même départ : leurs passe-pieds courent entre elles. Chaque
	 * planche y porte sa place en travers (w0, w1) et en long (u0, u1), depuis l'origine du groupe.
	 */
	static groups(beds) {

		const G = ScenePlanGeometry;
		const groups = new Map();

		for(const bed of beds) {

			const turn = Math.round(Math.atan2(bed.u.n, bed.u.x) * 100);
			const hand = Math.sign(bed.u.x * bed.w.n - bed.u.n * bed.w.x);
			const start = Math.round(G.dot(bed.origin, bed.u));
			const key = bed.plot +' '+ turn +' '+ hand +' '+ start;

			if(groups.has(key) === false) {
				groups.set(key, { key, zone: bed.zone, origin: bed.origin, u: bed.u, w: bed.w, beds: [] });
			}

			const group = groups.get(key);
			const offset = G.sub(bed.origin, group.origin);
			const w0 = G.dot(offset, group.w);
			const u0 = G.dot(offset, group.u);

			group.beds.push({ id: bed.id, w0, w1: w0 + bed.width, u0, u1: u0 + bed.length });

		}

		for(const group of groups.values()) {
			group.beds.sort((a, b) => a.w0 - b.w0);
		}

		return groups.values();

	}

	static obstacle(r, margin, firm) {
		const c = SceneCrew.corners(r);
		return { r, margin, firm, greenhouse: firm !== r, minX: Math.min(...c.map(p => p.x)), maxX: Math.max(...c.map(p => p.x)), minN: Math.min(...c.map(p => p.n)), maxN: Math.max(...c.map(p => p.n)) };
	}

	static corners(r) {
		const G = ScenePlanGeometry;
		const far = G.add(r.origin, G.mul(r.u, r.length));
		return [r.origin, far, G.add(far, G.mul(r.w, r.width)), G.add(r.origin, G.mul(r.w, r.width))];
	}

	/**
	 * La part d'un segment prise dans l'emprise d'un obstacle, en fractions de sa longueur, ou null s'il passe à côté.
	 */
	static spanIn(a, b, o) {

		if(Math.max(a.x, b.x) < o.minX || Math.min(a.x, b.x) > o.maxX || Math.max(a.n, b.n) < o.minN || Math.min(a.n, b.n) > o.maxN) {
			return null;
		}

		const r = o.r;
		const m = o.margin;
		const ca = SceneCrew.coordinates(a, r);
		const cb = SceneCrew.coordinates(b, r);

		let k0 = 0;
		let k1 = 1;

		for(const [p0, p1, lo, hi] of [[ca[0], cb[0], m, r.length - m], [ca[1], cb[1], m, r.width - m]]) {

			const d = p1 - p0;

			if(Math.abs(d) < 1e-9) {
				if(p0 < lo || p0 > hi) {
					return null;
				}
				continue;
			}

			let ka = (lo - p0) / d;
			let kb = (hi - p0) / d;

			if(ka > kb) {
				[ka, kb] = [kb, ka];
			}

			k0 = Math.max(k0, ka);
			k1 = Math.min(k1, kb);

			if(k0 >= k1) {
				return null;
			}

		}

		return [k0, k1];

	}

	// Les coordonnées d'un point le long et en travers d'un rectangle, ses côtés pouvant ne pas être tout à fait d'équerre
	static coordinates(p, r) {
		const d = ScenePlanGeometry.sub(p, r.origin);
		const det = r.u.x * r.w.n - r.u.n * r.w.x;
		return [(d.x * r.w.n - d.n * r.w.x) / det, (r.u.x * d.n - r.u.n * d.x) / det];
	}

	// Le cap d'un tronçon de route. Un point peut imposer le sien (face) : le porteur d'une caisse y va en pas chassés, tourné comme
	// sa caisse et non comme ses pas
	static legYaw(from, to) {
		return to.face ?? SceneCrew.heading(ScenePlanGeometry.sub(to, from));
	}

	// Le buste qui porte la caisse devance les jambes vers la route, d'au plus CRATE_TWIST
	static crateTwist(farmer) {
		return Math.max(-SceneCrew.CRATE_TWIST, Math.min(SceneCrew.CRATE_TWIST, SceneCrew.turnSide(farmer.heading, farmer.facing)));
	}

	// Ce dont un point entre dans la planche qui le contient le plus, négatif hors de toute planche
	static bedDepth(p, beds) {

		let depth = -Infinity;

		for(const bed of beds) {
			const [along, across] = SceneCrew.coordinates(p, bed);
			depth = Math.max(depth, Math.min(along, bed.length - along, across, bed.width - across));
		}

		return depth;

	}

	/**
	 * Ce que la caisse tenue a de trop dans les planches, son porteur en `p` et tourné vers `yaw` : ce dont ses coins et le milieu de
	 * ses côtés y entrent, au-delà de ce qu'elle peut frôler et de ce dont le porteur y est lui-même.
	 */
	crateExcess(p, yaw, beds) {

		const G = ScenePlanGeometry;
		const ahead = { x: Math.sin(yaw), n: -Math.cos(yaw) };
		const right = { x: -Math.cos(yaw), n: -Math.sin(yaw) };
		const center = G.add(p, G.mul(ahead, this.crateHeld));
		const long = SceneCrew.CRATE_LONG * this.scale;

		let depth = -Infinity;

		for(const [a, b] of SceneCrew.CRATE_PROBES) {
			depth = Math.max(depth, SceneCrew.bedDepth(G.add(center, G.add(G.mul(ahead, a * long), G.mul(right, b * this.crateWide))), beds));
		}

		return depth - Math.max(SceneCrew.CRATE_GRAZE, SceneCrew.bedDepth(p, beds) + this.shoulder / 2);

	}

	// La route d'un porteur de caisse, ses coudes remplacés par leur manœuvre
	crateRoute(path) {

		const G = ScenePlanGeometry;
		const out = [path[0]];

		for(let i = 1; i < path.length; i++) {

			const from = out.at(-1);
			const corner = path[i];
			const next = path[i + 1];
			const bends = (next !== undefined && G.length(G.sub(corner, from)) > 1e-6 && G.length(G.sub(next, corner)) > 1e-6 && SceneCrew.turnGap(SceneCrew.legYaw(corner, next), SceneCrew.legYaw(from, corner)) > SceneCrew.CRATE_TURN);

			out.push(...(bends ? this.crateManoeuvre(from, corner, next) : [corner]));

		}

		return out;

	}

	/**
	 * Le geste d'un porteur de caisse à un coude de sa route, de `from` à `next` par `corner` : les points à suivre jusqu'à son second
	 * pivot compris. Il marche de face jusqu'au premier pivot, passe le nœud en pas chassés tourné comme sa caisse, pivote une seconde
	 * fois si cette orientation n'est pas celle de la suite, et repart de face. Le sens de chaque pivot se choisit, le plus court
	 * n'étant pas toujours le plus libre, et les points de pivot sont pris sur la route elle-même : le tracé que les autres évitent
	 * (leave) ne change pas. Chaque geste se juge sur tout son long par ce que la caisse a de trop (crateExcess), au pire de ses
	 * instants puis en tout. Le cas courant, un seul pivot au plus près du nœud, se prend dès qu'elle n'a rien de trop : la recherche
	 * complète, dix fois plus longue, ne sert qu'aux coudes serrés.
	 */
	crateManoeuvre(from, corner, next) {

		const G = ScenePlanGeometry;
		const C = SceneCrew;
		const inLength = G.length(G.sub(corner, from));
		const outLength = G.length(G.sub(next, corner));
		const inYaw = C.heading(G.sub(corner, from));
		const outYaw = C.heading(G.sub(next, corner));

		// Seul compte ce qui est à portée du nœud : le départ d'un tronçon plus long que la manœuvre n'y change rien
		const key = [corner.x, corner.n, inYaw, outYaw, Math.min(inLength, C.CRATE_MANOEUVRE + 1), Math.min(outLength, C.CRATE_MANOEUVRE + 1)].map(z => z.toFixed(2)).join();

		if(this.manoeuvres.has(key)) {
			return this.manoeuvres.get(key);
		}

		const inDirection = G.mul(G.sub(corner, from), 1 / inLength);
		const outDirection = G.mul(G.sub(next, corner), 1 / outLength);
		const reach = C.CRATE_MANOEUVRE + this.crateHeld + C.CRATE_LONG * this.scale + this.crateWide;

		const beds = this.beds.filter(bed => {
			const [along, across] = C.coordinates(corner, bed);
			return Math.hypot(Math.max(0, -along, along - bed.length), Math.max(0, -across, across - bed.width)) < reach;
		});

		const none = { worst: -Infinity, total: 0 };
		const cost = (...parts) => ({ worst: Math.max(...parts.map(part => part.worst)), total: parts.reduce((sum, part) => sum + part.total, 0) });

		// steps + 1 instants, l'instant k au point et au cap que rend at(k / steps)
		const gesture = (steps, at) => {

			let worst = -Infinity;
			let total = 0;

			for(let k = 0; k <= steps; k++) {
				const [p, yaw] = at(k / steps);
				const excess = this.crateExcess(p, yaw, beds);
				worst = Math.max(worst, excess);
				total += Math.max(0, excess);
			}

			return { worst, total };

		};

		const line = (a, b, yaw) => gesture(Math.max(1, Math.ceil(G.length(G.sub(b, a)) / C.CRATE_MANOEUVRE_STEP)), k => [G.lerp(a, b, k), yaw]);

		// À un centimètre près sur le pire, puis sur le total, le geste le plus simple
		const better = (a, b, simpler) => a.worst < b.worst - C.CRATE_MANOEUVRE_SLACK || (a.worst <= b.worst + C.CRATE_MANOEUVRE_SLACK
			&& (a.total < b.total - C.CRATE_MANOEUVRE_SLACK || (a.total <= b.total + C.CRATE_MANOEUVRE_SLACK && simpler(a, b))));

		// Le pivot sur place de a à b, dans le sens le plus libre : sa rotation, signée, et ce que la caisse a de trop en la faisant
		const pivot = (p, a, b) => {

			const short = C.turnSide(b, a);

			if(Math.abs(short) < 1e-6) {
				return { rotation: 0, ...none };
			}

			return [short, short - Math.sign(short) * 2 * Math.PI]
				.map(rotation => ({ rotation, ...gesture(Math.ceil(Math.abs(rotation) / C.CRATE_MANOEUVRE_ANGLE), k => [p, a + rotation * k]) }))
				.reduce((kept, option) => better(option, kept, () => false) ? option : kept);

		};

		const places = (length, direction, sign) => {

			const list = [];
			const last = Math.max(0, Math.min(C.CRATE_MANOEUVRE, length - C.CRATE_MANOEUVRE_STEP));

			for(let s = 0; s <= last + 1e-9; s += C.CRATE_MANOEUVRE_STEP) {
				list.push({ s, at: G.add(corner, G.mul(direction, sign * s)) });
			}

			return list;

		};

		const start = G.sub(corner, G.mul(inDirection, Math.min(inLength, C.CRATE_MANOEUVRE)));
		const end = G.add(corner, G.mul(outDirection, Math.min(outLength, C.CRATE_MANOEUVRE)));
		const before = places(inLength, inDirection, -1);
		const after = places(outLength, outDirection, 1);

		const firstAt = (place, yaw) => {
			place.approach ??= line(start, place.at, inYaw);
			const turn = pivot(place.at, inYaw, yaw);
			return { ...place, turn, ...cost(place.approach, turn, line(place.at, corner, yaw)) };
		};

		const secondAt = (place, yaw) => {
			place.leave ??= line(place.at, end, outYaw);
			const turn = pivot(place.at, yaw, outYaw);
			return { ...place, turn, ...cost(place.leave, turn, line(corner, place.at, yaw)) };
		};

		const option = (yaw, first, second, pivots) => ({ yaw, first, second, ...cost(first, second), pivots, s: first.s + second.s });

		let best = null;

		for(const place of before) {
			const candidate = option(outYaw, firstAt(place, outYaw), secondAt(after[0], outYaw), 1);
			if(candidate.worst <= 0) {
				best = candidate;
				break;
			}
		}

		if(best === null) {
			for(const place of after) {
				const candidate = option(inYaw, firstAt(before[0], inYaw), secondAt(place, inYaw), 1);
				if(candidate.worst <= 0) {
					best = candidate;
					break;
				}
			}
		}

		if(best === null) {

			// L'orientation qui passe le nœud : celle de la suite, puis celle d'où il vient, puis toutes les autres
			const yaws = [outYaw, inYaw, ...Array.from({ length: C.CRATE_MANOEUVRE_YAWS }, (_, i) => inYaw + (i + 1) * 2 * Math.PI / (C.CRATE_MANOEUVRE_YAWS + 1))];
			const nearest = options => options.reduce((kept, candidate) => better(candidate, kept, (a, b) => a.s < b.s) ? candidate : kept);

			yaws.forEach((yaw, index) => {
				const candidate = option(yaw, nearest(before.map(place => firstAt(place, yaw))), nearest(after.map(place => secondAt(place, yaw))), (index === 0 ? 0 : 1) + (index === 1 ? 0 : 1));
				if(best === null || better(candidate, best, (a, b) => a.pivots < b.pivots || (a.pivots === b.pivots && a.s < b.s))) {
					best = candidate;
				}
			});

		}

		// Un point sans face se rejoint de face ; un point qui en porte une se rejoint tourné ainsi, en pas chassés, ou sur place s'il
		// est là où le paysan se tient. Un pivot de plus d'un demi-tour passe par son milieu, pour se faire dans le sens choisi et non
		// dans le plus court
		const { yaw, first, second } = best;
		const points = [{ x: first.at.x, n: first.at.n }];

		const turnAt = (at, turn, origin) => {
			if(Math.abs(turn.rotation) > Math.PI) {
				points.push({ x: at.x, n: at.n, face: origin + turn.rotation / 2 });
			}
		};

		turnAt(first.at, first.turn, inYaw);
		points.push({ x: corner.x, n: corner.n, face: yaw });

		if(second.s > 0) {
			points.push({ x: second.at.x, n: second.at.n, face: yaw });
		}

		turnAt(second.at, second.turn, yaw);

		const kept = points.filter((point, i) => i === 0 || point.face !== points[i - 1].face || G.length(G.sub(point, points[i - 1])) > 1e-6);
		this.manoeuvres.set(key, kept);

		return kept;

	}

	static linkKey(i, j) {
		return Math.min(i, j) +' '+ Math.max(i, j);
	}

	// Le cap d'une direction du plan, l'inverse d'ahead() : la scène porte n en -z
	static heading(direction) {
		return Math.atan2(direction.x, -direction.n);
	}

	static turnSide(to, from) {
		return Math.atan2(Math.sin(to - from), Math.cos(to - from));
	}

	static turnGap(to, from) {
		return Math.abs(SceneCrew.turnSide(to, from));
	}

	static crosses(a, b, c, d) {
		const side = (p, q, r) => (q.x - p.x) * (r.n - p.n) - (q.n - p.n) * (r.x - p.x);
		return side(a, b, c) * side(a, b, d) < 0 && side(c, d, a) * side(c, d, b) < 0;
	}

	static segmentGap(a, b, c, d) {
		const G = ScenePlanGeometry;
		return SceneCrew.crosses(a, b, c, d) ? 0 : Math.min(G.distanceToSegment(a, c, d), G.distanceToSegment(b, c, d), G.distanceToSegment(c, a, b), G.distanceToSegment(d, a, b));
	}

	build(baked, i) {

		const { role, body: slug } = SceneCrew.CAST[i % SceneCrew.CAST.length];

		return this.mount(baked, i, role, this.figure.assemble(baked, slug, this.material, { tool: (role === 'hoe' || role === 'fork') }));

	}

	/**
	 * L'avatar du rang `rank` de `plan.team`, monté au premier jour où il paraît puis gardé : son modèle téléversé, ou sa
	 * composition si le modèle n'a pas été lu ou ne se monte pas.
	 */
	actor(rank) {

		if(this.actors.has(rank) === false) {

			const { loaded, team } = this.avatars;
			const { fiche, upload } = team[rank];
			const model = ((upload === null) ? null : SceneLoader.mountUpload(loaded, fiche, upload)) ?? loaded.avatar.build(structuredClone(fiche));

			const crate = [this.crateHeld, this.crateWide];
			const farmer = this.mount(this.baked, SceneCrew.MAX + rank, SceneCrew.AVATAR_ROLES[rank % SceneCrew.AVATAR_ROLES.length], loaded.figure.assemble(model, 'avatar', this.material));

			// Une caisse tenue plus loin que les précédentes change les manœuvres déjà jugées aux coudes
			if(this.crateHeld !== crate[0] || this.crateWide !== crate[1]) {
				this.manoeuvres.clear();
			}

			this.actors.set(rank, farmer);
			this.farmers.push(farmer);

		}

		return this.actors.get(rank);

	}

	// Monte le paysan `i` sur son corps `body`, avec ce que demande son geste
	mount(baked, i, role, body) {

		const THREE = this.THREE;
		const root = body.root;
		root.visible = false;

		const shadow = new THREE.Mesh(this.shadowGeometry, this.shadowMaterial);
		shadow.renderOrder = 1;
		root.add(shadow);

		let crate = null;

		if(role === 'crate') {
			crate = this.figure.prop(baked, 'caisse', this.material, { contenu: SceneCrew.CRATE_CONTENTS[i % SceneCrew.CRATE_CONTENTS.length] });
			crate.geometry.computeBoundingBox();
			const size = crate.geometry.boundingBox.getSize(new THREE.Vector3());
			crate.scale.setScalar(2 * SceneCrew.CRATE_LONG / size.x);
			this.crateWide = Math.max(this.crateWide, SceneCrew.CRATE_LONG * size.z / size.x * this.scale);
		}

		const { barrow, push } = (role === 'barrow') ? SceneCrew.wheel(THREE, this.figure, baked, body, this.material) : { barrow: null, push: null };

		const hold = (crate === null) ? null : this.figure.hold(body, crate);

		if(hold !== null) {
			this.crateHeld = Math.max(this.crateHeld, hold.depth * this.scale);
		}

		this.parent.add(root);

		return { role, body, root, shadow, crate, barrow, push, hold, on: false, shown: 0, r: this.random(i), at: { x: 0, n: 0 }, path: [], through: [], alley: null, pose: 'stand', work: 'stand', heading: 0, facing: 0, walkPhase: i, wait: 0, held: 0, crateSide: 1, drop: 0, lift: 0, carrying: false, pickup: false, setYaw: 0 };

	}

	/**
	 * La brouette que pousse `body`, montée sur son corps depuis la paire `baked`, et la prise de ses bras sur les poignées, que
	 * `figure.pose()` lit en `push` : celle d'un paysan de l'équipe, et celle du pousseur du bourg (`TownPeople`).
	 */
	static wheel(THREE, figure, baked, body, material) {

		const barrow = figure.prop(baked, 'brouette', material);
		body.root.add(barrow);

		// Le bout de chaque poignée, côté -X puis +X : le milieu de ce qui dépasse le plus en arrière, de part et d'autre de l'axe
		const positions = barrow.geometry.attributes.position;
		const points = Array.from({ length: positions.count }, (_, k) => new THREE.Vector3().fromBufferAttribute(positions, k));
		const back = Math.min(...points.map(p => p.z));

		barrow.userData.ends = [-1, 1].map(side => {
			const end = points.filter(p => p.z < back + .06 && Math.sign(p.x) === side);
			return end.reduce((sum, p) => sum.add(p), new THREE.Vector3()).divideScalar(end.length);
		});

		return { barrow, push: { grips: body.arms.map((arm, k) => SceneCrew.grip(THREE, body, arm, body.fists[k], barrow)), bend: SceneCrew.BARROW_BEND } };

	}

	/**
	 * La prise d'un bras sur sa poignée, brouette poussée et buste penché : l'angle du bras en avant et en dehors, et la prise le long
	 * du longeron, depuis le bout de la poignée vers l'axe de la roue. Trois inconnues pour que le poing tombe sur le longeron, trouvées
	 * par la méthode de Newton.
	 */
	static grip(THREE, body, arm, fist, barrow) {

		const side = Math.sign(arm.position.x);
		const end = barrow.userData.ends[side > 0 ? 1 : 0];
		const along = new THREE.Vector3(...barrow.userData.anchors.roue).sub(end).normalize();
		const tilt = new THREE.Euler(SceneCrew.BARROW_TILT, 0, 0);
		const bend = new THREE.Euler(SceneCrew.BARROW_BEND, 0, 0);
		const place = SceneCrew.place(THREE, barrow, SceneCrew.BARROW_TILT, 0);

		const gap = ([forward, outward, slide]) => fist.clone().applyEuler(new THREE.Euler(forward, 0, outward)).add(arm.position).applyEuler(bend).add(body.torso.position)
			.sub(end.clone().addScaledVector(along, slide).applyEuler(tilt).add(place)).toArray();

		let x = [-.8, side * .1, .05];

		for(let step = 0; step < 12; step++) {

			const r = gap(x);
			const columns = [0, 1, 2].map(j => gap(x.map((value, k) => (k === j) ? value + 1e-5 : value)).map((value, k) => (value - r[k]) / 1e-5));
			const jacobian = new THREE.Matrix3().set(...[0, 1, 2].flatMap(k => columns.map(column => column[k])));
			const dx = new THREE.Vector3(...r).applyMatrix3(jacobian.invert());

			x = x.map((value, k) => value - dx.getComponent(k));

		}

		return { forward: x[0], outward: x[1], slide: x[2] };

	}

	/**
	 * Où est la brouette quand elle bascule de `tilt` sur l'axe de sa roue, la roue au sol et le corps descendu de `drop`.
	 */
	static place(THREE, barrow, tilt, drop) {

		const [, y, z] = barrow.userData.anchors.roue;

		return new THREE.Vector3(0, y - (y * Math.cos(tilt) - z * Math.sin(tilt)) - drop, SceneCrew.BARROW_AHEAD + z - (y * Math.sin(tilt) + z * Math.cos(tilt)));

	}

	/**
	 * Pose la brouette de qui la pousse, en `pose`, son corps descendu de `drop`. En marchant, elle bascule sur l'axe de sa roue,
	 * qui reste au sol, et ses poignées suivent les poings quand le corps monte ou descend avec le pas : la hauteur se convertit en
	 * angle au premier ordre, sur la distance de l'axe aux poignées. À l'arrêt, elle repose sur ses pieds et sa roue.
	 */
	static tilt(THREE, barrow, pose, drop) {

		const tilt = (pose === 'walk') ? SceneCrew.BARROW_TILT + drop / (barrow.userData.anchors.roue[2] - barrow.userData.ends[0].z) : 0;

		barrow.rotation.x = tilt;
		barrow.position.copy(SceneCrew.place(THREE, barrow, tilt, drop));

	}

	/**
	 * Autant de paysans un jour donné que l'équipe du planning y compte de présents, neuf au plus.
	 */
	count(date) {
		return Math.min(SceneCrew.MAX, this.days.get(date) ?? 0);
	}

	/**
	 * Les paysans d'un jour, dans l'ordre où ils prennent place : l'avatar de chaque présent qui en a un, à son rang, puis des
	 * corps anonymes pour les autres.
	 */
	lineup(date) {

		const count = this.count(date);

		if(this.avatars === null || count === 0) {
			return this.cast.slice(0, count);
		}

		let anonymous = 0;

		return this.lineups.get(date).slice(0, count).map(rank => (this.avatars.team[rank] === null) ? this.cast[anonymous++] : this.actor(rank));

	}

	/**
	 * Place l'équipe du jour montré. Arrêtée, la scène montre le jour choisi, et chacun y reprend une place tirée de ce jour : la
	 * journée se joue de la même façon quel que soit le jour vu avant elle. Pendant la lecture, l'équipe est celle du jour le plus
	 * chargé de la semaine, faute de quoi elle s'effacerait chaque week-end : qui est déjà là garde sa place et son chemin.
	 *
	 * `week` porte les sept dates de la semaine montrée, du lundi au dimanche.
	 */
	staff(date, week, playing, speed) {

		if(this.cast.length === 0) {
			return;
		}

		if(playing) {

			const monday = week[0];
			const busiest = week.reduce((busiest, day) => (this.count(day) > this.count(busiest)) ? day : busiest);
			const lineup = this.lineup(busiest);
			const r = this.random(Number(monday.replaceAll('-', '')));

			this.farmers.forEach(farmer => {
				const k = lineup.indexOf(farmer);
				if(k >= 0 && farmer.on === false && farmer.shown > 0) {
					// Rappelé avant d'avoir fini de s'effacer, il revient là où il était
					farmer.on = true;
				} else if(k >= 0 && farmer.on === false) {
					farmer.r = this.random(Number(monday.replaceAll('-', '')) * 31 + k);
					farmer.on = true;
					this.seat(farmer, r);
				} else if(k < 0) {
					farmer.on = false;
				}
			});

		} else if(date !== this.date || this.playing) {

			const lineup = this.lineup(date);
			const day = Number(date.replaceAll('-', ''));
			const r = this.random(day);

			// Les places de la veille ne comptent plus : chacun reprend la sienne parmi celles que les précédents ont laissées
			this.farmers.forEach((farmer, i) => {
				farmer.r = this.random(day * 31 + i);
				farmer.on = false;
			});

			lineup.forEach((farmer, k) => {
				farmer.r = this.random(day * 31 + k);
				farmer.on = true;
				this.seat(farmer, r);
			});

		}

		this.date = date;
		this.playing = playing;
		this.speed = speed;

	}

	pointOf(alley, k) {
		return ScenePlanGeometry.lerp(this.nodes[alley.top].p, this.nodes[alley.bottom].p, k);
	}

	// Les passe-pieds pris par les autres : celui où chacun se tient ou se rend, et ceux que son trajet traverse. Un passe-pied ne
	// se partage pas, on ne s'y croise pas sans marcher sur la planche
	taken(farmer) {
		return new Set(this.farmers.filter(other => other.on && other !== farmer).flatMap(other => [other.alley, ...other.through]));
	}

	fits(farmer, alley) {

		if(farmer.role === 'crate' && this.picked !== null) {
			return (this.harvested.length > 0) ? this.harvested.includes(alley) : (alley === this.waiting);
		}

		return farmer.role !== 'barrow' || alley.barrow;

	}

	/**
	 * Fait des porteurs de caisse les cueilleurs du bourg, la semaine montrée : accroupis dans les passe-pieds qui longent une
	 * planche de `beds`, les planches en récolte, ou debout, leur caisse en main, dans le passe-pied le plus proche de `edge`,
	 * `{ x, n }`, le bord du champ où la route du bourg arrive, quand aucune n'est à cueillir. Le réseau ne sort pas du champ :
	 * ils y attendent. Sans cet appel, l'équipe ne change pas.
	 */
	gather(beds, edge) {

		this.picked = new Set(beds);
		this.edge = edge;

		this.pick();

	}

	pick() {

		if(this.picked === null) {
			return;
		}

		const G = ScenePlanGeometry;
		const distance = alley => Math.min(...[alley.top, alley.bottom].map(n => G.length(G.sub(this.nodes[n].p, this.edge))));

		this.harvested = this.alleys.filter(alley => alley.beds.some(bed => this.picked.has(bed)));
		this.waiting = this.alleys.reduce((best, alley) => (best === null || distance(alley) < distance(best)) ? alley : best, null);

	}

	// Une brouette ou une caisse dépasse du corps : la place à laisser autour de qui en a une se compte jusqu'au bout de ce qu'il tient
	reachOf(farmer) {
		if(farmer.role === 'barrow') {
			return this.reach;
		} else if(farmer.role === 'crate') {
			return this.crateHeld + SceneCrew.CRATE_LONG * this.scale;
		} else {
			return 0;
		}
	}

	ahead(farmer) {
		return { x: Math.sin(farmer.facing), n: -Math.cos(farmer.facing) };
	}

	/**
	 * Les passe-pieds de `alleys`, et ses postes d'abri seulement quand il n'a qu'eux. Au poste, on se tient dans l'herbe face à la
	 * bâche, loin de toute planche : on n'y travaille que faute de passe-pied qui convienne, sur une structure toute sous abri par
	 * exemple.
	 */
	static alleysFirst(alleys) {
		const open = alleys.filter(alley => alley.face === undefined);
		return (open.length > 0) ? open : alleys;
	}

	/**
	 * Un passe-pied et une place dans ce passe-pied, déjà au travail.
	 */
	seat(farmer, r) {

		const G = ScenePlanGeometry;
		const taken = this.taken(farmer);
		const free = this.alleys.filter(alley => taken.has(alley) === false && this.fits(farmer, alley));
		// Plus de paysans que de passe-pieds libres : il en partage un plutôt que de disparaitre
		const list = SceneCrew.alleysFirst((free.length > 0) ? free : this.alleys.filter(alley => this.fits(farmer, alley)));

		if(list.length === 0) {
			farmer.on = false;
			return;
		}

		// Deux passe-pieds peuvent se longer, un poste d'abri posé près d'un passe-pied par exemple : une place ne se prend que hors de
		// l'encombrement de ceux qui sont déjà placés. Après huit tirages il prend la dernière plutôt que de disparaitre
		const crowded = p => this.farmers.some(other => other.on && other !== farmer && other.alley !== null && G.length(G.sub(other.at, p)) < this.shoulder * 1.1 + Math.max(this.reachOf(farmer), this.reachOf(other)));

		for(let attempt = 0; attempt < 8; attempt++) {

			farmer.alley = list[Math.floor(r() * list.length)];
			farmer.at = this.pointOf(farmer.alley, .15 + r() * .7);

			if(crowded(farmer.at) === false) {
				break;
			}

		}

		farmer.path = [];
		farmer.through = [];
		farmer.held = 0;
		farmer.facing = r() * 2 * Math.PI;

		this.startWork(farmer);

		// Un placement n'est pas un trajet : il arrive déjà tourné
		farmer.facing = farmer.heading;
		farmer.wait *= r();

	}

	startWork(farmer) {

		const G = ScenePlanGeometry;
		const r = farmer.r();
		const alley = farmer.alley;

		farmer.through = [];

		// Un porteur d'outil travaille debout : accroupi, le fer de la houe ou de la fourche passe sous la terre
		// Le cueilleur qui attend au bord du champ le fait debout
		farmer.work = (farmer.role === 'crate' && this.picked !== null && this.harvested.includes(alley) === false) ? 'stand' : { hoe: 'hoe', fork: 'hoe', crate: 'crouch', barrow: 'stand' }[farmer.role];

		// La caisse posée prend sa place à côté du cap de travail : posée avant qu'il y soit tourné, elle serait devant ou derrière lui
		// tout le temps qu'il tourne, sa main tendue vers le sol vide. Il la garde en main jusque-là (update)
		farmer.pose = (farmer.crate !== null) ? 'stand' : farmer.work;

		farmer.wait = (farmer.work === 'stand') ? 3 + r * 4 : 6 + r * 8;

		let face = alley.face ?? (r < .5 ? alley.a : G.mul(alley.a, -1));

		// Une brouette est plus longue qu'un passe-pied n'est large : elle y reste dans l'axe, tournée comme elle est entrée
		if(farmer.role === 'barrow' && alley.face === undefined) {
			const axis = G.norm(G.sub(this.nodes[alley.bottom].p, this.nodes[alley.top].p));
			face = (G.dot(axis, this.ahead(farmer)) < 0) ? G.mul(axis, -1) : axis;
		}

		farmer.heading = SceneCrew.heading(face);

		// La caisse se pose du côté où le passe-pied est le plus long : vers son milieu, elle ne déborde pas de son bout
		const side = { x: Math.cos(farmer.heading), n: Math.sin(farmer.heading) };
		farmer.crateSide = (G.dot(G.sub(this.pointOf(alley, .5), farmer.at), side) < 0) ? -1 : 1;
		farmer.setYaw = farmer.heading;

	}

	nextStop(farmer, taken) {

		const near = SceneCrew.alleysFirst(this.alleys.filter(alley => alley.component === farmer.alley.component && taken.has(alley) === false && this.fits(farmer, alley)));
		const same = near.filter(alley => alley.group === farmer.alley.group && alley !== farmer.alley);

		// Retenu depuis trois secondes par les routes des autres, il cherche plus loin que son groupe de planches
		const list = (same.length > 0 && farmer.held < 3 && farmer.r() < .7) ? same : near;

		return { alley: list[Math.floor(farmer.r() * list.length)] ?? null, k: .15 + farmer.r() * .7 };

	}

	/**
	 * Sa tâche finie, un paysan part vers un autre passe-pied, par le plus court chemin, s'il ne croise personne en route.
	 */
	leave(farmer) {

		const G = ScenePlanGeometry;
		const taken = this.taken(farmer);
		const stop = this.nextStop(farmer, taken);

		if(stop.alley === null) {
			farmer.wait = 2;
			return;
		}

		const here = farmer.at;
		const target = stop.alley;
		const point = this.pointOf(target, stop.k);
		const distance = (n, p) => G.length(G.sub(this.nodes[n].p, p));

		// Dans un passe-pied, une brouette ne fait pas demi-tour : elle sort par la tournière qui lui fait face, et entre par une tournière
		const wheeled = alley => farmer.role === 'barrow' && alley.face === undefined;
		const ahead = wheeled(farmer.alley) ? farmer.alley.turns.filter(n => G.dot(G.sub(this.nodes[n].p, here), this.ahead(farmer)) > 0) : null;

		// Dans le passe-pied où il se tient, il y va droit : par un bout, il ferait l'aller-retour pour rien
		const straight = (target === farmer.alley && (ahead === null || G.dot(G.sub(point, here), this.ahead(farmer)) > 0));

		const from = (ahead ?? [farmer.alley.top, farmer.alley.bottom]).map(n => [n, distance(n, here)]);
		const to = (wheeled(target) ? target.turns : [target.top, target.bottom]).map(n => [n, distance(n, point)]);
		const nodes = straight ? [] : this.route(from, to, farmer.role === 'barrow' ? this.roomy : null, taken);

		if(nodes === null) {
			farmer.wait = 2;
			return;
		}

		const path = [here, ...nodes.map(n => this.nodes[n].p), point];
		const legs = path.slice(1).map((q, i) => [path[i], q]);

		// Il ne part que si sa route ne croise personne : ni la route qu'il reste à un autre, ni quelqu'un au travail. D'ici là il reste
		// à sa tâche et retire une destination ; personne n'attend debout dans un passage
		const meets = this.farmers.some(other => {

			if(other === farmer || other.on === false) {
				return false;
			}

			const reach = Math.max(this.reachOf(farmer), this.reachOf(other));

			if(other.path.length > 1) {
				return legs.some(([a, b]) => other.path.slice(1).some((q, i) => SceneCrew.segmentGap(a, b, i > 0 ? other.path[i] : other.at, q) < this.passRoom * 1.5 + reach));
			}

			// Le premier et le dernier tronçon suivent son propre passe-pied : un voisin au travail dans le passe-pied d'à côté n'y gêne
			// pas, sauf si l'un des deux a une caisse, qui balaie plus large que le passe-pied quand son porteur se tourne
			const swing = (farmer.role === 'crate' || other.role === 'crate') ? this.crateAside + SceneCrew.CRATE_LONG * this.scale : 0;

			return legs.some(([a, b], i) => G.distanceToSegment(other.at, a, b) < ((i === 0 || i === legs.length - 1) ? this.shoulder * 1.1 + swing : this.passRoom + reach));

		});

		if(meets) {
			farmer.wait = .5;
			farmer.held += .5;
			return;
		}

		farmer.held = 0;
		// Posée pendant le travail, la caisse n'est ramassée qu'une fois son porteur tourné vers sa route : tenue devant lui, elle
		// entrerait dans la planche qu'il regardait
		farmer.pickup = (farmer.role === 'crate' && SceneCrew.carries(farmer) === false);
		farmer.path = (farmer.role === 'crate') ? this.crateRoute(path) : path;

		// Le cap de la route vaut dès le départ : la caisse qu'il ramasse se règle sur lui, pas sur la planche qu'il regardait
		if(G.length(G.sub(farmer.path[1], here)) > 1e-6) {
			farmer.heading = SceneCrew.legYaw(here, farmer.path[1]);
		}

		farmer.through = nodes.slice(1).map((n, i) => this.alleyOfLink.get(SceneCrew.linkKey(nodes[i], n))).filter(alley => alley !== undefined && alley !== target);
		farmer.alley = target;
		farmer.pose = 'walk';

	}

	/**
	 * Le plus court chemin depuis plusieurs départs vers plusieurs arrivées, chacun avec son coût d'approche. `allowed` dit les
	 * nœuds où passer, tous s'il est nul ; `closed` les passe-pieds à ne pas traverser.
	 */
	route(sources, targets, allowed, closed) {

		const count = this.nodes.length;
		const distance = new Float64Array(count).fill(Infinity);
		const previous = new Int32Array(count).fill(-1);
		const heap = [];

		const push = (cost, node) => {
			heap.push([cost, node]);
			for(let i = heap.length - 1; i > 0;) {
				const parent = (i - 1) >> 1;
				if(heap[parent][0] <= heap[i][0]) {
					break;
				}
				[heap[parent], heap[i]] = [heap[i], heap[parent]];
				i = parent;
			}
		};

		const pop = () => {
			const top = heap[0];
			const last = heap.pop();
			if(heap.length > 0) {
				heap[0] = last;
				for(let i = 0; ;) {
					const l = 2 * i + 1;
					const r = l + 1;
					let m = i;
					if(l < heap.length && heap[l][0] < heap[m][0]) {
						m = l;
					}
					if(r < heap.length && heap[r][0] < heap[m][0]) {
						m = r;
					}
					if(m === i) {
						break;
					}
					[heap[m], heap[i]] = [heap[i], heap[m]];
					i = m;
				}
			}
			return top;
		};

		for(const [node, cost] of sources) {
			if(cost < distance[node]) {
				distance[node] = cost;
				push(cost, node);
			}
		}

		const wanted = new Set(targets.map(([node]) => node));

		while(heap.length > 0 && wanted.size > 0) {

			const [cost, node] = pop();

			if(cost > distance[node]) {
				continue;
			}

			wanted.delete(node);

			for(const [j, w] of this.nodes[node].links) {
				if(cost + w < distance[j] && (allowed === null || allowed[j]) && closed.has(this.alleyOfLink.get(SceneCrew.linkKey(node, j))) === false) {
					distance[j] = cost + w;
					previous[j] = node;
					push(cost + w, j);
				}
			}

		}

		const [end] = targets.reduce((best, target) => (distance[target[0]] + target[1] < distance[best[0]] + best[1]) ? target : best);

		if(Number.isFinite(distance[end]) === false) {
			return null;
		}

		const nodes = [];

		for(let n = end; n !== -1; n = previous[n]) {
			nodes.unshift(n);
		}

		return nodes;

	}

	/**
	 * Une image : chacun avance sur sa route ou travaille à sa place, se tourne vers son cap sans jamais en changer d'un coup, et
	 * pendant la lecture arrive ou part en fondu.
	 */
	update(time) {

		const G = ScenePlanGeometry;
		const seconds = time / 1000;
		// Un onglet revenu au premier plan reprend où il en était, sans rattraper en un pas le temps passé ailleurs
		const dt = (this.last === null) ? 0 : Math.min(.1, seconds - this.last);
		this.last = seconds;

		for(const farmer of this.farmers) {

			const target = farmer.on ? 1 : 0;
			farmer.shown = this.playing ? Math.max(0, Math.min(1, farmer.shown + (target === 1 ? 1 : -1) * dt * this.speed / SceneCrew.PLAY_FADE)) : target;
			farmer.root.visible = (farmer.shown > 0);
			farmer.root.scale.setScalar(this.scale * farmer.shown);

			if(farmer.root.visible === false || farmer.on === false) {
				continue;
			}

			if(farmer.path.length > 1) {

				const d = G.sub(farmer.path[1], farmer.at);
				const length = G.length(d);

				// Un point qui porte une face se rejoint tourné ainsi, et peut n'être qu'un pivot sur place
				if(length > 1e-6 || farmer.path[1].face !== undefined) {
					farmer.heading = SceneCrew.legYaw(farmer.at, farmer.path[1]);
				}

				// Il piétine tant qu'il n'est pas tourné vers sa route, et s'arrête à chaque nœud pour s'y tourner. Qui porte une caisse,
				// ou va la ramasser, ne marche qu'une fois tourné
				const gate = (farmer.carrying || farmer.pickup) ? SceneCrew.CRATE_TURN : SceneCrew.TURN_GATE;
				const step = (SceneCrew.turnGap(farmer.heading, farmer.facing) > gate) ? 0 : SceneCrew.WALK_SPEED * dt * (farmer.role === 'barrow' ? .8 : 1);

				if(step > 0) {
					farmer.pickup = false;
				}

				if(step > 0 && length <= step) {

					farmer.at = farmer.path[1];
					farmer.path.shift();

					// Au coude, le cap de la suite vaut tout de suite : la caisse levée pour tourner ne redescend pas sur l'ancien
					if(farmer.path.length > 1 && (G.length(G.sub(farmer.path[1], farmer.at)) > 1e-6 || farmer.path[1].face !== undefined)) {
						farmer.heading = SceneCrew.legYaw(farmer.at, farmer.path[1]);
					}

				} else if(step > 0) {
					farmer.at = G.add(farmer.at, G.mul(d, step / length));
				}

				const before = Math.sin(farmer.walkPhase);

				farmer.walkPhase += SceneCrew.WALK_SPEED * dt * 7 / this.scale;

				// Un pied touche le sol quand la phase passe par zéro
				if(Math.sign(before) !== Math.sign(Math.sin(farmer.walkPhase))) {
					this.puff(farmer.at, seconds);
				}
				farmer.pose = 'walk';

				if(farmer.path.length <= 1) {
					farmer.path = [];
					this.startWork(farmer);
				}

			} else {

				if(farmer.pose !== farmer.work && SceneCrew.turnGap(farmer.heading, farmer.facing) <= SceneCrew.CRATE_TURN) {
					farmer.pose = farmer.work;
				}

				farmer.wait -= dt * (this.playing ? SceneCrew.PLAY_REST * this.speed : 1);

				if(farmer.wait <= 0) {
					this.leave(farmer);
				}

			}

			if(farmer.crate !== null) {
				this.lift(farmer, dt);
			}

			this.pose(farmer, seconds);

			const ground = this.geometry.height(farmer.at.x, farmer.at.n);

			farmer.root.position.set(farmer.at.x, ground + farmer.drop * this.scale, -farmer.at.n);
			farmer.facing += Math.sign(SceneCrew.turnSide(farmer.heading, farmer.facing)) * Math.min(SceneCrew.turnGap(farmer.heading, farmer.facing), SceneCrew.TURN_SPEED * dt);
			farmer.root.rotation.y = farmer.facing;

			// Le disque reste au sol quand le corps descend pour s'accroupir ou au bout du pas. Sur une pente, il reste de niveau et se
			// lève de ce que le sol monte du centre à son bord, pour ne pas s'enfoncer du côté amont : lean() cisaille une matrice du
			// repère du monde, et celle du disque est relative à une racine qui tourne et change d'échelle
			const radius = this.shadowGeometry.parameters.radius * farmer.root.scale.x;
			const rise = Math.hypot(this.geometry.height(farmer.at.x + radius, farmer.at.n) - ground, this.geometry.height(farmer.at.x, farmer.at.n + radius) - ground);

			farmer.shadow.position.y = (ground + .03 + rise - farmer.root.position.y) / farmer.root.scale.y;

		}

		this.drawDust(seconds);

	}

	puff(at, seconds) {

		const puff = this.puffs[this.puffNext];
		this.puffNext = (this.puffNext + 1) % SceneCrew.DUST;

		puff.start = seconds;
		puff.at.set(at.x, this.geometry.height(at.x, at.n) + .1, -at.n);

	}

	/**
	 * Seules les bouffées encore en l'air s'écrivent, les plus récentes en tête : la réserve tourne, une bouffée éteinte n'est plus
	 * dessinée sans qu'il faille la remettre à zéro.
	 */
	drawDust(seconds) {

		const object = this.puffMatrix;
		let count = 0;

		for(let i = 0; i < SceneCrew.DUST; i++) {

			const index = (this.puffNext - 1 - i + SceneCrew.DUST) % SceneCrew.DUST;
			const puff = this.puffs[index];
			const k = (seconds - puff.start) / SceneCrew.DUST_LIFE;

			if(k < 0 || k >= 1) {
				break;
			}

			object.position.set(puff.at.x, puff.at.y + k * SceneCrew.DUST_RISE, puff.at.z);
			object.rotation.set(0, index, 0);
			object.scale.setScalar((1 - k) * (.6 + k * 1.6));
			object.updateMatrix();

			this.dust.setMatrixAt(count++, object.matrix);

		}

		if(count > 0 || this.dust.count > 0) {
			this.dust.count = count;
			this.dust.instanceMatrix.needsUpdate = true;
		}

	}

	// Une caisse se porte en marchant et debout, et se pose quand il bine ou s'accroupit ; posée, elle reste au sol jusqu'à ce qu'il
	// soit tourné vers sa route
	static carries(farmer) {
		return (farmer.crate !== null && farmer.pose !== 'crouch' && farmer.pose !== 'hoe' && farmer.pickup === false);
	}

	/**
	 * La caisse tenue monte dans un virage, et avant un coude de sa route, le geste de qui s'apprête à passer un coin chargé ; elle
	 * redescend en marche droite. Ramassée au sol, elle part d'emblée à sa place : il n'y a pas de geste à finir.
	 */
	lift(farmer, dt) {

		const held = SceneCrew.carries(farmer);
		const [, corner, after] = farmer.path;
		const bends = (after !== undefined && ScenePlanGeometry.length(ScenePlanGeometry.sub(corner, farmer.at)) < this.crateHeld + SceneCrew.CRATE_LONG * this.scale && SceneCrew.turnGap(SceneCrew.legYaw(corner, after), farmer.heading) > SceneCrew.CRATE_TURN);
		const up = (held && (bends || SceneCrew.turnGap(farmer.heading, farmer.facing) > SceneCrew.CRATE_TURN)) ? 1 : 0;

		if(held && farmer.carrying) {
			farmer.lift += Math.max(-SceneCrew.CRATE_LOWER * dt, Math.min(SceneCrew.CRATE_RAISE * dt, up - farmer.lift));
		} else {
			farmer.lift = up;
		}

		farmer.carrying = held;

	}

	pose(farmer, seconds) {

		const crate = farmer.crate;
		const held = SceneCrew.carries(farmer);

		// Portée, elle suit le buste, qui la place (figure.pose). Posée, elle tient au sol : le corps descend, elle remonte d'autant
		if(crate !== null) {

			const holder = held ? farmer.body.torso : farmer.root;

			if(crate.parent !== holder) {
				holder.add(crate);
			}

		}

		farmer.drop = this.figure.pose(farmer.body, farmer.pose, {
			phase: farmer.walkPhase,
			time: seconds,
			carry: held ? { crate, hold: farmer.hold, lift: farmer.lift } : null,
			push: farmer.push
		});

		farmer.body.torso.rotation.y = held ? SceneCrew.crateTwist(farmer) : 0;

		// Le corps tourne vers son cap : la caisse posée se replace de l'écart à l'axe de sa pose de travail pour ne pas le suivre, son
		// long côté le long du passe-pied, que le paysan accroupi a en travers, et ne suit pas non plus le pas sur place de qui se tourne
		// avant de la ramasser
		if(crate !== null && held === false) {
			const turned = farmer.setYaw - farmer.facing;
			const aside = farmer.crateSide * this.crateAside / this.scale;
			crate.rotation.y = turned;
			crate.position.set(aside * Math.cos(turned), -farmer.drop, -aside * Math.sin(turned));
		}

		if(farmer.barrow !== null) {
			SceneCrew.tilt(this.THREE, farmer.barrow, farmer.pose, farmer.drop);
		}

	}

	dispose() {
		this.material.dispose();
		this.shadowMaterial.dispose();
		this.shadowGeometry.dispose();
		this.dust.material.dispose();
	}

}
