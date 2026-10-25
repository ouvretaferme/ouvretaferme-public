/**
 * Le calque « Parcelles et jardins » de la scène : le contour de chaque parcelle en trait continu, celui de chaque jardin
 * tracé en tirets plus pâles, posés sur le sol et drapés sur le relief. Un jardin sans tracé occupe sa parcelle et n'a pas de trait
 * à lui. Ce sont des lignes, que la scène dessine sans aucun triangle : le calque ne pèse rien sur le plafond du décor.
 *
 * Le calque est éteint tant que l'utilisateur ne l'a pas allumé sur cette structure. Le montrer ou le masquer ne remonte pas la
 * scène, à la différence du relief : la semaine montrée reste.
 */
class MapSceneLimits {

	static STORAGE = 'map-scene-limits-shown-';

	// Au-dessus du dessus des planches (`MapScene.BED_BASE` + `MapScene.BED_HEIGHT`), qui couvriraient le trait d'un jardin à leur bord
	static LIFT = .06;

	static DASH = 1;
	static GAP = .5;

	/**
	 * `ground(positions, a, b, lift)` pose une droite du plan sur le sol, coupée aux triangles du relief (`MapSceneDecor.groundLine()`).
	 */
	constructor({ THREE, geometry, ground, parent }) {

		this.THREE = THREE;
		this.geometry = geometry;
		this.ground = ground;
		this.toggle = null;

		this.outlines = {
			zone: [...geometry.zones.values()],
			plot: [...geometry.plots.values()].filter(({ drawn }) => drawn).map(({ points }) => points)
		};

		this.materials = {
			zone: new THREE.LineBasicMaterial({ color: '#ffffff', transparent: true, opacity: .9, depthWrite: false }),
			plot: new THREE.LineDashedMaterial({ color: '#ffffff', transparent: true, opacity: .8, depthWrite: false, dashSize: MapSceneLimits.DASH, gapSize: MapSceneLimits.GAP })
		};

		this.lines = {
			zone: new THREE.LineSegments(new THREE.BufferGeometry(), this.materials.zone),
			plot: new THREE.LineSegments(new THREE.BufferGeometry(), this.materials.plot)
		};

		this.group = new THREE.Group();
		this.group.visible = false;

		Object.values(this.lines).forEach(line => {
			line.frustumCulled = false;
			this.group.add(line);
		});

		parent.add(this.group);

		this.draw();

	}

	/**
	 * Le calque est éteint sauf si l'utilisateur l'a allumé sur cette structure. Une navigation privée ou des données de site bloquées
	 * font lever l'accès au stockage : il reste alors éteint, ce qui est l'état par défaut.
	 */
	static shown(container) {

		try {
			return (localStorage.getItem(MapSceneLimits.key(container)) === '1');
		} catch(e) {
			return false;
		}

	}

	static remember(container, shown) {

		try {
			if(shown) {
				localStorage.setItem(MapSceneLimits.key(container), '1');
			} else {
				localStorage.removeItem(MapSceneLimits.key(container));
			}
		} catch(e) {
		}

	}

	// Par structure et par utilisateur, comme les calques du décor (`MapSceneLayer`) et le relief (`MapSceneRelief`)
	static key(container) {
		const card = container.qs('[data-scene-layers-card]');
		return MapSceneLimits.STORAGE + card.dataset.structure +'-'+ card.dataset.user;
	}

	/**
	 * La ligne de la carte des calques, toute la ligne valant interrupteur, comme celle des séries.
	 */
	attach(container) {

		const row = container.qs('[data-scene-limits]');

		this.toggle = row.qs('.field-switch');
		this.show(MapSceneLimits.shown(container));

		row.addEventListener('click', () => {
			const shown = (this.group.visible === false);
			MapSceneLimits.remember(container, shown);
			this.show(shown);
		});

	}

	show(shown) {

		this.group.visible = shown;
		this.toggle.classList.toggle('field-switch-on', shown);
		this.toggle.classList.toggle('field-switch-off', shown === false);

	}

	/**
	 * Le tracé, refait en entier quand un creux de mare change le sol (`MapSceneRelief.carve()`) : quelques centaines de segments,
	 * moins que ce que coûterait de trouver ceux que le creux touche.
	 */
	draw() {

		for(const [kind, outlines] of Object.entries(this.outlines)) {

			const positions = [];

			for(const points of outlines) {
				points.forEach((a, i) => this.ground(positions, a, points[(i + 1) % points.length], MapSceneLimits.LIFT));
			}

			const line = this.lines[kind];

			line.geometry.dispose();
			line.geometry = MapSceneDecor.lineGeometry(this.THREE, positions);

			// Les tirets se comptent le long de chaque segment, d'où un pas qui repart à chaque coupe du relief : sans coupe, le
			// côté d'un jardin garde un tiret régulier
			if(kind === 'plot') {
				line.computeLineDistances();
			}

		}

	}

	dispose() {

		Object.values(this.lines).forEach(line => line.geometry.dispose());
		Object.values(this.materials).forEach(material => material.dispose());

	}

}
