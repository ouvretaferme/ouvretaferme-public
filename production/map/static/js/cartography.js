class MapCartography {

	static instances = {};

	static zoneRefreshHandler = null;

	/* Largeur d'un caractère en em, relevée sur capture : capitales grasses espacées de 0,12 em pour
	   les parcelles, bas de casse semi-grasse pour les blocs */
	static LABEL_ZONE = {em: 0.72, size: 13};
	static LABEL_PLOT = {em: 0.55, size: 12};
	static LABEL_MARGIN = 8;

	static SOURCES = ['zones', 'plots', 'beds', 'selected', 'zoneLabels', 'plotLabels', 'bedLabels', 'pac', 'drawing'];

	/* Les traits d'un jardin et de la forme désignée s'affinent de loin, où ils couvriraient la photo : chacun a sa largeur
	   de près, à partir du zoom NEAR, et la sienne de loin, au zoom FAR et en deçà, entre lesquelles la carte passe en ligne droite */
	static FAR = 15;
	static NEAR = 17;

	/* Le contour d'un jardin et le cerne qui le détache de la photo, en pixels */
	static PLOT = {width: [2.5, 4], casing: [4.5, 7]};

	/* Un jardin désigné se cadre avec ce qui l'entoure : ses bords sont repoussés de cette part de sa taille, de chaque côté */
	static PLOT_SURROUNDING = 0.4;

	/* Le trait de la forme désignée et le cerne qui le détache des contours voisins, en pixels, plus épais que ceux d'un
	   jardin pour rester au-dessus d'eux, et l'opacité du voile posé sur le reste de la carte */
	static SELECTED = {width: [4, 6], casing: [7, 11], veil: 0.4};

	/* Le trait du geste et le cerne d'encre qui le porte, en pixels : celui de la forme en cours de tracé, de son fantôme et
	   des planches cochées dans une liste. Aucun ton ne se lit à la fois sur la photo et sur le blanc du plan, d'où le cerne */
	static GESTURE = {line: {width: 3, casing: 5}, ghost: {width: 2, casing: 4}, bed: {width: 2.5, casing: 4.5}};

	/* Le monde entier, dont le voile retire la forme désignée */
	static WORLD = [[-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]];

	/* À combien de pixels d'une forme un clic compte encore pour elle, quand il s'agit de savoir s'il est tombé à côté */
	static EMPTY_REACH = 4;

	/* À combien de pixels d'une planche un clic la désigne encore : une planche de 80 cm n'en fait qu'une dizaine à l'écran,
	   et le clic qui la manque d'un rien la désigne au lieu de retirer la désignation */
	static BED_REACH = 8;

	container;
	mapbox = null;
	season;
	interactive = true;

	boundsStructure = undefined;
	boundsZones = {};
	boundsPlots = {};

	plotsZones = {};
	plotsModes = {};

	/* Le compte des planches à dessiner d'un jardin, posé en marqueur sous son nom : il parait avec lui */
	counters = new Map();

	/* Les formes et les étiquettes, par source et par clé : un ajout ou un retrait ne touche que la
	   collection, et la source n'est réécrite qu'une fois par image, cf. flush() */
	features = {};
	sourcesDirty = new Set();
	flushFrame = null;

	/* Retirée avec son panneau (dispose()), que la fermeture et la réouverture suivante peuvent demander chacune */
	disposed = false;

	/* Planches tracées par jardin, pour savoir quoi retirer avec lui */
	bedsDrawn = {};

	/* Bascule unique : le style ne se décharge jamais une fois chargé, cf. do() */
	styleReady = false;
	styleWatcher = null;
	stylePoller = null;
	pendingCallbacks = [];

	basemap = 'satellite';
	basemapSwitch = null;
	basemapNode = null;
	basemapSaved = null;
	basemapSaving = false;

	attribution = null;
	attributionControl = null;

	labelsZoom = null;

	fitPadding = null;

	loadZoneHandler = null;
	loadPlotHandler = null;
	loadStructureHandler = null;

	/* Le mode de tracé (MapDrawing) tient la carte le temps d'une forme : les clics ne choisissent plus rien,
	   et la forme qu'on retrace est retirée de ses sources jusqu'à la fin du geste */
	locked = false;
	masked = [];
	drawingHandler = null;
	bedHandler = null;
	bedsPlot = null;
	// Une planche de la forme désignée, ses planches encore fermées, se désigne à qui les ouvre (pickBed())
	pickBedHandler = null;
	emptyHandler = null;
	clearBedsHandler = null;
	emptyLayers = ['bed-fill'];

	selectedBeds = [];

	selectedZone = null;
	selectedPlot = null;
	zoom = 'structure';

	plotWidth = MapCartography.getWidth(MapCartography.PLOT.width);

	/* Sur papier, le nom d'une planche s'écrit dès que sa couche paraît : à l'écran, il attend qu'on s'approche pour se lire */
	print = false;

	constructor(container = null, season, interactive, map, options = {}) {

		this.container = container;
		MapCartography.instances[container] = this;

		MapCartography.SOURCES.forEach(source => this.features[source] = new Map());

		if(map) {

			if(options.basemap !== undefined) {
				this.basemap = options.basemap;
				this.basemapSaved = options.basemap;
				delete options.basemap;
			}

			if(options.plotWidth !== undefined) {
				this.plotWidth = options.plotWidth;
				delete options.plotWidth;
			}

			if(options.print !== undefined) {
				this.print = options.print;
				delete options.print;
			}

			if(options.basemapSwitch !== undefined) {
				this.basemapSwitch = options.basemapSwitch;
				delete options.basemapSwitch;
			}

			const scale = (options.scale === true);
			delete options.scale;

			if(options.attribution !== undefined) {
				this.attribution = options.attribution;
				delete options.attribution;
			}

			options.style = this.getStyle(options.zoom ?? 0);

			if(options.scrollZoom === undefined) {
				options.scrollZoom = false;
			}

			const controls = (options.controls !== false);
			delete options.controls;

			this.mapbox = new MapboxContainer(container).create(options);

			if(controls) {
				this.mapbox.addControl(new mapboxgl.NavigationControl());
				this.mapbox.addControl(new mapboxgl.FullscreenControl());
			}

			if(scale) {
				this.mapbox.addControl(new mapboxgl.ScaleControl({unit: 'metric', maxWidth: 120}), 'bottom-right');
			}

			this.refreshAttributionControl();

			if(this.basemapSwitch !== null) {
				this.addBasemapControl();
			}

			this.mapbox.on('zoom', () => this.refreshLabelsZoom());

			if(interactive) {
				this.bindClicks();
			}

			/* Une planche cliquée se désigne à qui le demande : la console de tracé, dont la carte ne choisit ni parcelle ni jardin.
			   Seulement celle du jardin déjà ouvert : le clic qui ouvre un jardin (`defaultPrevented`, posé par plot-fill) ne passe pas
			   ici, et Mapbox peut servir plot-fill avant cet écouteur, le jardin déjà rendu s'ouvrant alors sous le même clic.
			   Écouté sur toute la carte et non sur bed-fill, qui ne sert pas le clic tombé à côté d'une planche (getBedAt()) */
			this.mapbox.on('click', (e) => {

				if(this.locked || this.bedHandler === null || e.originalEvent.defaultPrevented || this.isMarkerClick(e)) {
					return;
				}

				const bed = this.getBedAt(e);

				if(bed !== null) {
					this.bedHandler(bed);
				}

			});

			/* Un clic qui ne tombe sur rien de ce qui se désigne retire la désignation. Les couches sont interrogées ici,
			   l'ordre dans lequel Mapbox sert un clic à ses écouteurs de couche et à celui-ci n'étant pas écrit */
			this.mapbox.on('click', (e) => {

				// Une planche qu'on manque d'un rien près du bord de sa parcelle se désigne, ce clic n'est pas tombé à côté
				if(this.locked || this.emptyHandler === null || this.styleReady === false || this.isMarkerClick(e) || this.isBedClick(e)) {
					return;
				}

				/* Un passe-pied entre deux planches n'est pas « à côté » : le clic compte pour une forme à quelques pixels près */
				const reach = MapCartography.EMPTY_REACH;
				const box = [[e.point.x - reach, e.point.y - reach], [e.point.x + reach, e.point.y + reach]];

				if(this.mapbox.queryRenderedFeatures(box, {layers: this.emptyLayers}).length === 0) {
					this.emptyHandler();
				}

			});

		}

		this.season = season;
		this.interactive = interactive;

	};

	/* Une carte retirée avec son conteneur, celle d'un panneau qui se ferme : MapboxContainer.create() la remplacerait sinon par
	   un `remove` déjà passé, qui ne revient pas, et une image en attente réécrirait les sources d'une carte qui n'en a plus */
	dispose() {

		if(this.disposed) {
			return;
		}

		this.disposed = true;

		if(this.flushFrame !== null) {
			cancelAnimationFrame(this.flushFrame);
			this.flushFrame = null;
		}

		this.mapbox.remove();

		/* Un panneau rouvert avant d'être retiré (Lime.Panel.render()) a déjà posé la carte suivante sous le même conteneur */
		if(MapboxContainer.maps[this.container] === this.mapbox) {
			delete MapboxContainer.maps[this.container];
		}

		if(MapCartography.instances[this.container] === this) {
			delete MapCartography.instances[this.container];
		}

	}

	static get(container, callback = undefined) {

		const instance = MapCartography.instances[container];

		if(instance !== undefined && callback !== undefined) {
			callback(instance);
		}

		return instance;

	}

	/* Le fond, les sources et les couches tiennent dans un seul style, posé à la création : changer
	   de fond n'en recharge aucun, seules la visibilité du satellite et les couleurs basculent */
	getStyle(zoom) {

		const paint = this.getPaint(this.basemap);

		const source = (data) => ({type: 'geojson', data: {type: 'FeatureCollection', features: []}, ...data});

		const drawing = this.getToken('--maptool-ring');

		const label = (id, layout, minzoom) => ({
			id: id,
			type: 'symbol',
			source: id.replace('-label', '') +'Labels',
			...(minzoom ? {minzoom: minzoom} : {}),
			layout: {
				'text-field': ['get', 'name'],
				'text-max-width': 40,
				'text-allow-overlap': false,
				'text-padding': 2,
				...layout
			},
			paint: paint[id]
		});

		const drawingLabel = (id, filter, always) => ({
			id: id,
			type: 'symbol',
			source: 'drawing',
			filter: filter,
			layout: {
				'text-field': ['get', 'name'],
				'text-font': ['Open Sans Bold'],
				'text-size': ['interpolate', ['linear'], ['zoom'], 18, 8, 20, 11],
				'text-rotate': ['get', 'rot'],
				'text-rotation-alignment': 'map',
				'text-padding': 0,
				'text-allow-overlap': always
			},
			paint: {'text-color': this.getToken('--text'), 'text-halo-color': this.getToken('--maptool-ring'), 'text-halo-width': 1.5}
		});

		return {
			version: 8,
			glyphs: 'mapbox://fonts/mapbox/{fontstack}/{range}.pbf',
			sources: {
				satellite: {type: 'raster', url: 'mapbox://mapbox.satellite', tileSize: 256},
				zones: source({promoteId: 'id'}),
				plots: source({promoteId: 'id'}),
				beds: source({}),
				selected: source({}),
				zoneLabels: source({}),
				plotLabels: source({}),
				bedLabels: source({}),
				pac: source({}),
				drawing: source({})
			},
			layers: [
				{id: 'background', type: 'background', paint: {'background-color': this.getToken('--background-surface')}},
				{id: 'satellite', type: 'raster', source: 'satellite', layout: {visibility: (this.basemap === 'satellite') ? 'visible' : 'none'}},
				{id: 'zone-fill', type: 'fill', source: 'zones', paint: paint['zone-fill']},
				{id: 'zone-line', type: 'line', source: 'zones', paint: paint['zone-line']},
				/* Un jardin n'a pas d'aplat, qui couvrirait ses planches et la photo : sa surface ne sert qu'à recevoir le clic */
				{id: 'plot-fill', type: 'fill', source: 'plots', paint: {'fill-opacity': 0}},
				{id: 'bed-fill', type: 'fill', source: 'beds', paint: paint['bed-fill']},
				/* Le filet sépare deux planches qui se touchent, que l'aplat seul fond en une tache */
				{id: 'bed-line', type: 'line', source: 'beds', paint: paint['bed-line']},
				/* La bordure d'un jardin passe sur ses planches : sous elles, celles qui touchent son bord la couvriraient et le jardin ne se lirait plus */
				{id: 'plot-casing', type: 'line', source: 'plots', layout: {'line-join': 'round'}, paint: paint['plot-casing']},
				{id: 'plot-line', type: 'line', source: 'plots', layout: {'line-join': 'round'}, paint: paint['plot-line']},
				/* La forme désignée passe sur les planches : sous elles, les jardins et les planches qui touchent son contour le mordraient */
				{id: 'selected-veil', type: 'fill', source: 'selected', filter: ['has', 'veil'], paint: paint['selected-veil']},
				{id: 'selected-casing', type: 'line', source: 'selected', filter: ['!', ['has', 'veil']], layout: {'line-join': 'round'}, paint: paint['selected-casing']},
				{id: 'selected-line', type: 'line', source: 'selected', filter: ['!', ['has', 'veil']], layout: {'line-join': 'round'}, paint: paint['selected-line']},
				/* Les planches cochées dans une liste gardent la couleur du geste qui les prendra, au-dessus de toute bordure qui les toucherait */
				{id: 'bed-selected-fill', type: 'fill', source: 'beds', filter: this.getBedsFilter(), paint: {'fill-color': this.getToken('--maptool-ring'), 'fill-opacity': 0.45}},
				{id: 'bed-selected-casing', type: 'line', source: 'beds', filter: this.getBedsFilter(), paint: {'line-color': this.getToken('--maptool-ink'), 'line-width': MapCartography.GESTURE.bed.casing}},
				{id: 'bed-selected-line', type: 'line', source: 'beds', filter: this.getBedsFilter(), paint: {'line-color': this.getToken('--maptool-ring'), 'line-width': MapCartography.GESTURE.bed.width}},
				/* Deux noms de planche qui se recouvriraient ne s'écrivent pas tous les deux : un trait serré en montre un sur
				   quelques-uns plutôt qu'une bande de halos. Le nom du jardin et celui de la parcelle, placés avant eux parce
				   que leurs couches sont au-dessus, gardent leur place */
				label('bed-label', {
					'text-font': ['Open Sans Semibold'],
					'text-size': ['interpolate', ['linear'], ['zoom'], 18, 8, 20, 11],
					'text-rotate': ['get', 'rot'],
					'text-rotation-alignment': 'map',
					'text-padding': 0
				}, 17.5),
				{...label('plot-label', {
					'text-font': ['Open Sans Semibold'],
					'text-size': MapCartography.LABEL_PLOT.size,
					'symbol-sort-key': 1
				}, 15), filter: this.getLabelsFilter(zoom)},
				{...label('zone-label', {
					'text-field': ['upcase', ['get', 'name']],
					'text-font': ['Open Sans Bold'],
					'text-size': MapCartography.LABEL_ZONE.size,
					'text-letter-spacing': 0.12,
					'symbol-sort-key': 0
				}), filter: this.getLabelsFilter(zoom)},
				/* Les parcelles PAC que l'outil « Parcelle PAC » peut encore prendre, en pointillé comme un contour à suivre, et celle
				   qu'un clic prendrait, de la couleur du geste (MapDrawing.refreshPac(), hoverPac()) */
				{id: 'pac-line', type: 'line', source: 'pac', filter: ['!', ['has', 'hover']], paint: {...paint['pac-line'], 'line-width': 1.5, 'line-dasharray': [3, 2]}},
				{id: 'pac-hover', type: 'fill', source: 'pac', filter: ['has', 'hover'], paint: {'fill-color': drawing, 'fill-opacity': 0.28}},
				/* La forme en cours de tracé garde sa couleur sur l'un et l'autre fond, c'est celle de ses poignées, et son cerne
				   porte aussi le fantôme */
				{id: 'drawing-fill', type: 'fill', source: 'drawing', filter: ['in', ['geometry-type'], ['literal', ['Polygon', 'MultiPolygon']]], paint: {'fill-color': drawing, 'fill-opacity': 0.18}},
				{id: 'drawing-casing', type: 'line', source: 'drawing', layout: {'line-join': 'round', 'line-cap': 'round'}, paint: {'line-color': this.getToken('--maptool-ink'), 'line-width': ['case', ['has', 'ghost'], MapCartography.GESTURE.ghost.casing, MapCartography.GESTURE.line.casing]}},
				{id: 'drawing-line', type: 'line', source: 'drawing', filter: ['!', ['has', 'ghost']], layout: {'line-join': 'round', 'line-cap': 'round'}, paint: {'line-color': drawing, 'line-width': MapCartography.GESTURE.line.width}},
				{id: 'drawing-ghost', type: 'line', source: 'drawing', filter: ['has', 'ghost'], paint: {'line-color': drawing, 'line-width': MapCartography.GESTURE.ghost.width, 'line-dasharray': [2, 2]}},
				/* Le nom de chaque planche d'un rang qu'on pose, lisible sur l'aplat du geste comme sur la photo. Deux noms qui
				   se recouvriraient ne s'écrivent pas tous les deux : de loin, un rang serré en montre un sur quelques-uns */
				drawingLabel('drawing-label', ['all', ['has', 'name'], ['!', ['has', 'end']]], false),
				/* Les deux bouts du rang s'écrivent toujours, et leur couche, au-dessus, se place avant : ce sont les autres noms qui leur cèdent */
				drawingLabel('drawing-label-end', ['has', 'end'], true)
			]
		};

	}

	static getWidth([far, near]) {
		return ['interpolate', ['linear'], ['zoom'], MapCartography.FAR, far, MapCartography.NEAR, near];
	}

	/* Les couleurs de chaque couche, sur l'un et l'autre fond */
	getPaint(basemap) {

		const token = (name) => this.getToken(name);
		const byMode = (openField, greenhouse) => ['match', ['get', 'mode'], 'greenhouse', greenhouse, openField];
		const bedLabelOpacity = this.print ? 1 : ['interpolate', ['linear'], ['zoom'], 18.3, 0, 18.8, 1];

		switch(basemap) {

			case 'drawing' :
				return {
					'zone-fill': {'fill-color': token('--background'), 'fill-opacity': 0.6},
					'zone-line': {'line-color': token('--text-intermediate'), 'line-width': 1.5, 'line-opacity': 1},
					'pac-line': {'line-color': token('--text-intermediate')},
					/* Sur le dessin, le contour se lit sans cerne : la couche reste, éteinte, pour que la bascule de fond la retrouve */
					'plot-casing': {'line-color': token('--background-surface'), 'line-width': MapCartography.getWidth(MapCartography.PLOT.casing), 'line-opacity': 0},
					'plot-line': {'line-color': byMode(token('--open-field'), token('--greenhouse')), 'line-width': this.plotWidth},
					'bed-fill': {'fill-color': token('--soil'), 'fill-opacity': ['interpolate', ['linear'], ['zoom'], 15.5, 0.15, 17.5, 0.72]},
					'bed-line': {'line-color': token('--map-bed-line-ink'), 'line-width': 0.75, 'line-opacity': ['interpolate', ['linear'], ['zoom'], 15.5, 0.15, 17.5, 0.8]},
					'selected-veil': {'fill-color': token('--background-surface'), 'fill-opacity': MapCartography.SELECTED.veil},
					'selected-casing': {'line-color': token('--background-surface'), 'line-width': MapCartography.getWidth(MapCartography.SELECTED.casing)},
					'selected-line': {'line-color': token('--map-selected-ink'), 'line-width': MapCartography.getWidth(MapCartography.SELECTED.width)},
					/* Sur papier, le halo couleur de terre se fond dans l'aplat qu'il borde : le nom prend le halo sombre du satellite */
					'bed-label': {'text-color': token('--background-surface'), 'text-halo-color': token(this.print ? '--text' : '--soil'), 'text-halo-width': 1.2, 'text-opacity': bedLabelOpacity},
					'plot-label': {'text-color': byMode(token('--open-field'), token('--greenhouse')), 'text-halo-color': token('--background-surface'), 'text-halo-width': 2, 'text-halo-blur': 0.5},
					/* 3 est le plafond de Mapbox, un quart de la taille du texte */
					'zone-label': {'text-color': token('--text'), 'text-halo-color': token('--background-surface'), 'text-halo-width': 3, 'text-halo-blur': 0.5}
				};

			case 'satellite' :
				return {
					'zone-fill': {'fill-color': token('--background-surface'), 'fill-opacity': 0},
					'zone-line': {'line-color': token('--background-surface'), 'line-width': 2, 'line-opacity': 0.95},
					'pac-line': {'line-color': token('--background-surface')},
					'plot-casing': {'line-color': token('--map-plot-casing'), 'line-width': MapCartography.getWidth(MapCartography.PLOT.casing), 'line-opacity': 0.95},
					'plot-line': {'line-color': byMode(token('--map-plot-line-open-field'), token('--map-plot-line-greenhouse')), 'line-width': this.plotWidth},
					/* L'aplat laisse voir le sol de la photo, le filet tient le bord de la planche à sa place */
					'bed-fill': {'fill-color': token('--open-field-background'), 'fill-opacity': 0.7},
					'bed-line': {'line-color': token('--map-bed-line'), 'line-width': 1.25, 'line-opacity': 0.95},
					'selected-veil': {'fill-color': token('--map-selected-casing'), 'fill-opacity': MapCartography.SELECTED.veil},
					'selected-casing': {'line-color': token('--map-selected-casing'), 'line-width': MapCartography.getWidth(MapCartography.SELECTED.casing)},
					'selected-line': {'line-color': token('--map-selected'), 'line-width': MapCartography.getWidth(MapCartography.SELECTED.width)},
					'bed-label': {'text-color': token('--background-surface'), 'text-halo-color': token('--text'), 'text-halo-width': 1.2, 'text-opacity': bedLabelOpacity},
					'plot-label': {'text-color': token('--background-surface'), 'text-halo-color': token('--text'), 'text-halo-width': 2, 'text-halo-blur': 0.5},
					'zone-label': {'text-color': token('--background-surface'), 'text-halo-color': token('--text'), 'text-halo-width': 2, 'text-halo-blur': 0.5}
				};

		}

	}

	getBedsFilter() {
		return ['in', ['get', 'id'], ['literal', this.selectedBeds]];
	}

	/* Les planches qu'une liste coche, par leur id : la carte les montre tant qu'aucun geste ne la tient */
	selectBeds(ids) {

		this.selectedBeds = ids;

		this.do(() => ['bed-selected-fill', 'bed-selected-casing', 'bed-selected-line'].forEach(layer => this.mapbox.setFilter(layer, this.getBedsFilter())));

		return this;

	}

	getToken(name) {
		return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
	}

	setBasemap(basemap) {

		this.basemap = basemap;

		this.do(() => {

			this.mapbox.setLayoutProperty('satellite', 'visibility', (basemap === 'satellite') ? 'visible' : 'none');

			Object.entries(this.getPaint(basemap)).forEach(([layer, paint]) => {
				Object.entries(paint).forEach(([property, value]) => this.mapbox.setPaintProperty(layer, property, value));
			});

		});

		this.refreshBasemapControl();
		this.refreshAttributionControl();

		return this;

	}

	/* Mapbox ne mentionne que les sources peintes : sur le dessin, le satellite est masqué et la mention
	   est posée en dur, avec Maxar quand la vignette montre sa photo ; sur le satellite, celle de la source
	   suffit, la doubler l'écrirait deux fois. Les options d'un AttributionControl ne se changent pas après
	   sa pose, d'où le remplacement */
	refreshAttributionControl() {

		if(this.attribution === null) {
			return;
		}

		if(this.attributionControl !== null) {
			this.mapbox.removeControl(this.attributionControl);
		}

		this.attributionControl = new mapboxgl.AttributionControl({
			...this.attribution,
			...(this.basemap === 'drawing' ? {customAttribution: '<a href="https://www.mapbox.com/about/maps/" target="_blank">© Mapbox</a> <a href="https://www.openstreetmap.org/copyright/" target="_blank">© OpenStreetMap</a>'+ (this.basemapSwitch !== null ? ' <a href="https://www.maxar.com/" target="_blank">© Maxar</a>' : '')} : {})
		});

		this.mapbox.addControl(this.attributionControl, 'bottom-left');

	}

	addBasemapControl() {

		this.basemapNode = document.createElement('button');
		this.basemapNode.type = 'button';
		this.basemapNode.className = 'mapboxgl-ctrl util-basemap';
		this.basemapNode.innerHTML = '<span class="util-basemap-image"></span><span class="util-basemap-name"></span>';

		this.basemapNode.addEventListener('click', () => {

			this.setBasemap((this.basemap === 'satellite') ? 'drawing' : 'satellite');
			this.saveBasemap();

		});

		this.mapbox.addControl({
			onAdd: () => this.basemapNode,
			onRemove: () => this.basemapNode.remove()
		}, 'bottom-right');

		this.refreshBasemapControl();

	}

	/* Un seul envoi à la fois, car Ajax.Query verrouille la vignette jusqu'à la réponse : des clics
	   rapprochés basculent la carte sans attendre, et le fond retenu part au retour de l'envoi en cours */
	saveBasemap() {

		if(
			this.basemapSwitch.member === null ||
			this.basemapSaving ||
			this.basemap === this.basemapSaved
		) {
			return;
		}

		const basemap = this.basemap;

		this.basemapSaving = true;

		new Ajax.Query(this.basemapNode)
			.url('/structure/member:doUpdateMapBasemap')
			.body({
				id: this.basemapSwitch.member,
				viewMapBasemap: basemap
			})
			.fetch()
			.then(() => {
				this.basemapSaved = basemap;
				this.basemapSaving = false;
				this.saveBasemap();
			}, () => {
				this.basemapSaving = false;
			});

	}

	/* La vignette montre l'autre fond : la photo de l'emprise, ou la structure dessinée */
	refreshBasemapControl() {

		if(this.basemapNode === null) {
			return;
		}

		const other = (this.basemap === 'satellite') ? 'drawing' : 'satellite';
		const image = this.basemapNode.qs('.util-basemap-image');

		this.basemapNode.qs('.util-basemap-name').textContent = this.basemapSwitch.labels[other];

		if(other === 'satellite') {
			image.innerHTML = '';
			image.style.backgroundImage = 'url('+ this.basemapSwitch.preview +')';
		} else {
			image.style.backgroundImage = 'none';
			image.innerHTML = this.getDrawingPreview(144, 112);
		}

	}

	/* Des formes seules, sans aucun nom : rien de ce qui est saisi n'entre dans ce SVG */
	getDrawingPreview(width, height) {

		const background = this.getToken('--background-surface');
		const svg = (content) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+ width +' '+ height +'" width="100%" height="100%" style="background: '+ background +'">'+ content +'</svg>';

		if(this.boundsStructure === undefined) {
			return svg('');
		}

		/* Les deux axes en Mercator, sinon la structure s'écrase vers les pôles */
		const projectX = (lng) => lng * Math.PI / 180;
		const projectY = (lat) => Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360));

		const [[west, north], [east, south]] = this.boundsStructure;
		const margin = 6;

		const scale = Math.min(
			(width - 2 * margin) / (projectX(east) - projectX(west)),
			(height - 2 * margin) / (projectY(north) - projectY(south))
		);

		const offsetX = (width - scale * (projectX(east) - projectX(west))) / 2;
		const offsetY = (height - scale * (projectY(north) - projectY(south))) / 2;

		const path = (feature) => feature.geometry.coordinates
			.map(ring => 'M'+ ring.map(([lng, lat]) => (offsetX + (projectX(lng) - projectX(west)) * scale).toFixed(1) +','+ (height - offsetY - (projectY(lat) - projectY(south)) * scale).toFixed(1)).join('L') +'Z')
			.join('');

		const shapes = (features, fill, stroke) => features
			.map(feature => '<path d="'+ path(feature) +'" fill="'+ fill(feature) +'" stroke="'+ stroke(feature) +'" stroke-width="0.6"/>')
			.join('');

		const byMode = (openField, greenhouse) => (feature) => this.getToken((feature.properties.mode === 'greenhouse') ? greenhouse : openField);

		return svg(
			shapes([...this.features.zones.values()], () => this.getToken('--background'), () => this.getToken('--text-intermediate')) +
			shapes([...this.features.plots.values()], () => 'none', byMode('--open-field', '--greenhouse')) +
			shapes([...this.features.beds.values()].flat(), () => this.getToken('--soil'), () => 'none')
		);

	}

	bindClicks() {

		/* Des planches posées à même la parcelle n'ont pas de bloc qui les entoure : c'est la planche cliquée qui ouvre leur jardin,
		   comme le bloc ouvre le sien. Cochée sous le même clic si leur jardin ou leur parcelle est déjà désigné */
		this.mapbox.on('click', 'bed-fill', (e) => {
			if(this.locked || e.originalEvent.defaultPrevented || this.isMarkerClick(e) || this.isBedClick(e)) {
				return;
			}
			const plotId = e.features[0].properties.plot;
			if(this.plotsZones[plotId] === undefined || this.features.plots.has(plotId)) {
				return;
			}
			e.originalEvent.preventDefault();
			// Leur jardin ou leur parcelle déjà désignés, la planche se coche. Le jardin reste ouvert : sans bloc pour retenir le clic,
			// sa parcelle le prendrait pour « à côté »
			const designated = (plotId === this.selectedPlot || (this.selectedPlot === null && this.plotsZones[plotId] === this.selectedZone));
			if(designated && (this.pickBed(e, plotId) || plotId === this.selectedPlot)) {
				return;
			}
			this.clickPlot(plotId, this.plotsZones[plotId], false);
		});

		/* Le bloc avant la parcelle : un clic sur un bloc atteint aussi la parcelle qui le contient. Dans le jardin désigné, un clic
		   sur une de ses planches la coche, et un clic qui n'en atteint aucune décoche celles qui l'étaient, sans le rouvrir
		   (unpickBeds()). Dans la parcelle désignée, qui liste les planches de tous ses jardins, un clic sur une planche la
		   coche aussi et désigne son jardin, dont la console ouvre les planches, et un clic à côté désigne le jardin. Un clic dans
		   la parcelle, hors du jardin désigné, décoche aussi ses planches (zone-fill) */
		this.mapbox.on('click', 'plot-fill', (e) => {
			if(this.locked || this.isMarkerClick(e) || this.isBedClick(e)) {
				return;
			}
			e.originalEvent.preventDefault();
			const plotId = e.features[0].properties.id;
			if(plotId === this.selectedPlot) {
				if(this.pickBed(e, plotId) === false) {
					this.unpickBeds(plotId);
				}
				return;
			}
			if(this.selectedPlot === null && this.plotsZones[plotId] === this.selectedZone && this.pickBed(e, plotId)) {
				this.select('plot', plotId);
				return;
			}
			this.clickPlot(plotId, this.plotsZones[plotId], false);
		});

		this.mapbox.on('click', 'zone-fill', (e) => {
			if(this.locked || e.originalEvent.defaultPrevented || this.isMarkerClick(e) || this.isBedClick(e)) {
				return;
			}
			// Dans la parcelle désignée, ou celle du jardin désigné, un clic hors de ses jardins ne défait pas la désignation : seul un
			// clic hors de toute parcelle le fait (emptyHandler). Des planches posées à même la parcelle, plus fines que leur trait
			// au zoom d'ouverture, se cochent à BED_REACH près comme celles d'un jardin, faute de bloc pour retenir le clic. Un clic
			// qui n'atteint aucune planche décoche celles qui l'étaient (unpickBeds()), qu'elles soient posées à même la parcelle ou
			// dans un jardin, abri compris, à côté duquel il tombe. La marge protège la planche visée, pas celles d'un autre jardin :
			// un clic près d'une planche posée à même la parcelle, quand c'est un autre jardin qui est désigné, décoche aussi
			if(e.features[0].properties.id === this.selectedZone) {
				const plotId = this.getFilledPlotAt(e);
				if(plotId !== null && (this.selectedPlot === null || plotId === this.selectedPlot)) {
					this.pickBed(e, plotId);
				} else if(plotId !== this.bedsPlot && this.plotsZones[this.bedsPlot] === this.selectedZone) {
					this.unpickBeds(this.bedsPlot);
				}
				return;
			}
			this.clickZone(e.features[0].properties.id, false);
		});

		/* Hors de toute parcelle, un clic défait la dernière chose désignée, sans jamais bouger la caméra : les planches cochées
		   d'un jardin, puis le jardin, pour sa parcelle, puis la parcelle, pour la structure. On ne perd pas l'endroit qu'on
		   regardait, seul le retour du bloc recadre */
		this.emptyLayers = ['zone-fill', 'plot-fill'];
		this.emptyHandler = () => {

			if(this.selectedZone === null) {
				return;
			}

			// Des planches posées à même la parcelle s'ouvrent et se cochent sans jardin désigné : leur jardin ouvert se lit sur bedsPlot
			if(this.selectedBeds.length > 0 && this.clearBedsHandler !== null) {
				this.clearBedsHandler();
				return;
			}

			if(this.selectedPlot !== null || this.bedsPlot !== null) {
				this.clickZone(this.selectedZone, false);
				return;
			}

			this.selectStructure();

			if(this.loadStructureHandler !== null) {
				this.loadStructureHandler();
			}

		};

		['zone-fill', 'plot-fill'].forEach(layer => {
			this.mapbox.on('mouseenter', layer, () => {
				if(this.locked === false) {
					this.mapbox.getCanvas().style.cursor = 'pointer';
				}
			});
			this.mapbox.on('mouseleave', layer, () => {
				if(this.locked === false) {
					this.mapbox.getCanvas().style.cursor = '';
				}
			});
		});

	}

	/* Un `['zoom']` dans le filtre d'une couche n'affiche rien en v3.31 : le zoom courant y entre comme
	   une valeur, reposée quand il change d'un dixième */
	getLabelsFilter(zoom) {
		return ['>=', zoom, ['get', 'fit']];
	}

	refreshLabelsZoom() {

		const zoom = Math.round(this.mapbox.getZoom() * 10) / 10;

		if(zoom === this.labelsZoom || this.styleReady === false) {
			return;
		}

		this.labelsZoom = zoom;

		['zone-label', 'plot-label'].forEach(layer => this.mapbox.setFilter(layer, this.getLabelsFilter(zoom)));

		this.counters.forEach(counter => counter.marker.getElement().classList.toggle('hide', zoom < counter.fit));

	}

	/* Un bouton posé sur la carte laisse passer son clic jusqu'aux écouteurs du document, et Mapbox le sert aussi
	   à ses couches, d'après le point de la carte qui est dessous : ce clic-là ne désigne rien. Le chemin de l'événement
	   est celui du début de sa propagation, quand les ancêtres de la cible sont ceux d'après son écouteur : « Planches »
	   refermé replace la fiche hors de son marqueur avant que Mapbox ne serve le clic (MapPlan.toggleBeds()) */
	isMarkerClick(e) {
		return e.originalEvent.composedPath().some(node => node.classList?.contains('mapboxgl-marker'));
	}

	/* Une planche du jardin dont on coche les planches se désigne elle-même : le clic qui l'atteint atteint aussi son jardin
	   ou sa parcelle, qui ne doivent pas se rouvrir */
	isBedClick(e) {
		return (this.bedHandler !== null && this.getBedAt(e) !== null);
	}

	/* Un clic qui n'atteint aucune planche du jardin ouvert, même à BED_REACH près, décoche celles qui l'étaient, qu'il tombe
	   dans ce jardin ou dans sa parcelle, à côté de lui. Cette marge garde la sélection au clic tombé dans un passe-pied */
	unpickBeds(plotId) {
		if(this.bedHandler !== null && plotId === this.bedsPlot && this.selectedBeds.length > 0 && this.clearBedsHandler !== null) {
			this.clearBedsHandler();
		}
	}

	/* La planche du jardin désigné que le clic touche, à BED_REACH pixels près, se donne à pickBedHandler. Rend faux sans
	   planche ni écouteur, et l'appelant traite alors le clic comme un clic sur la forme */
	pickBed(e, plotId) {

		if(this.pickBedHandler === null) {
			return false;
		}

		const bed = this.getBedAt(e, plotId);

		if(bed === null) {
			return false;
		}

		this.pickBedHandler(plotId, bed);

		return true;

	}

	/* La planche d'un jardin, par défaut celui ouvert, que le clic désigne, à BED_REACH pixels près : celle qu'il touche, sinon la plus proche */
	getBedAt(e, plotId = this.bedsPlot) {

		const features = this.getBedsNear(e).filter(feature => feature.properties.plot === plotId);

		if(features.length === 0) {
			return null;
		}

		const point = turf.point(e.lngLat.toArray());
		const distance = (feature) => turf.booleanPointInPolygon(point, feature) ? 0 : turf.pointToLineDistance(point, turf.polygonToLine(feature));

		const distances = features.map(feature => ({id: feature.properties.id, distance: distance(feature)}));
		distances.sort((a, b) => a.distance - b.distance);

		return distances[0].id;

	}

	// Le jardin des planches posées à même la parcelle désignée qu'un clic atteint à BED_REACH pixels près, sinon null
	getFilledPlotAt(e) {

		const feature = this.getBedsNear(e).find(feature => this.plotsZones[feature.properties.plot] === this.selectedZone && this.features.plots.has(feature.properties.plot) === false);

		return (feature === undefined) ? null : feature.properties.plot;

	}

	getBedsNear(e) {

		const reach = MapCartography.BED_REACH;
		const box = [[e.point.x - reach, e.point.y - reach], [e.point.x + reach, e.point.y + reach]];

		return this.mapbox.queryRenderedFeatures(box, {layers: ['bed-fill']});

	}

	/* Le compte d'un jardin suit ses planches tracées. Des planches posées à même la parcelle n'ont pas d'étiquette où le poser */
	refreshPlotCounter(plotId, undrawn) {
		this.setPlotCounter(plotId, this.features.plotLabels.get(plotId) ?? null, undrawn);
	}

	setPlotCounter(plotId, label, undrawn) {

		this.counters.get(plotId)?.marker.remove();
		this.counters.delete(plotId);

		if(this.mapbox === null || label === null || undrawn === 0) {
			return;
		}

		const node = document.createElement('span');
		node.className = 'util-counter util-counter-solid plan-mapview-counter';
		node.textContent = undrawn;

		const counter = {
			marker: new mapboxgl.Marker({element: node, anchor: 'top', offset: [0, 9]}).setLngLat(label.geometry.coordinates),
			fit: label.properties.fit
		};

		node.classList.toggle('hide', this.labelsZoom === null || this.labelsZoom < counter.fit);

		counter.marker.addTo(this.mapbox);

		this.counters.set(plotId, counter);

	}

	setFeature(source, key, feature) {

		if(feature === null) {
			this.features[source].delete(key);
		} else {
			this.features[source].set(key, feature);
		}

		this.sourcesDirty.add(source);

		if(this.flushFrame === null && this.mapbox !== null) {
			this.flushFrame = requestAnimationFrame(() => {
				this.flushFrame = null;
				this.do(() => {});
			});
		}

	}

	/* Retire de la carte ce qu'une clé y a posé dans ces sources, le temps d'un tracé */
	setMask(sources, key) {

		sources.forEach(source => {

			const feature = this.features[source].get(key);

			if(feature !== undefined) {
				this.masked.push([source, key, feature]);
				this.setFeature(source, key, null);
			}

		});

		/* Le compte d'un jardin est un marqueur posé sous son étiquette : il part avec elle */
		if(sources.includes('plotLabels')) {
			this.counters.get(key)?.marker.remove();
		}

		return this;

	}

	/* Retire de la carte des planches d'un jardin, et elles seules */
	setMaskBeds(plotId, ids) {

		(this.bedsDrawn[plotId] ?? []).forEach(key => {

			const polygons = this.features.beds.get(key);
			const labels = this.features.bedLabels.get(key);

			if(polygons === undefined) {
				return;
			}

			const kept = polygons.map((polygon, position) => position).filter(position => ids.includes(String(polygons[position].properties.id)) === false);

			if(kept.length === polygons.length) {
				return;
			}

			this.masked.push(['beds', key, polygons], ['bedLabels', key, labels]);

			this.setFeature('beds', key, kept.map(position => polygons[position]));
			this.setFeature('bedLabels', key, kept.map(position => labels[position]));

		});

		return this;

	}

	/* Déplace les planches d'un jardin et leurs noms d'un mouvement rigide (MapDrawingCopy.getMotion()), depuis ce qu'ils étaient
	   avant le geste, que clearMask(true) remet. Sans mouvement, ils reviennent à leur place */
	moveBeds(plotId, motion) {

		(this.bedsDrawn[plotId] ?? []).forEach(key => {
			this.moveFeature('beds', key, motion);
			this.moveFeature('bedLabels', key, motion);
		});

		return this;

	}

	/* Les jardins d'une parcelle tirée d'un bloc ou tournée la suivent, avec leurs planches, comme l'enregistrement les déplace */
	moveZone(zoneId, motion) {

		Object.entries(this.plotsZones)
			.filter(([, plotZoneId]) => String(plotZoneId) === String(zoneId))
			.forEach(([plotId]) => {

				this.moveFeature('plots', parseInt(plotId), motion);

				const label = this.moveFeature('plotLabels', parseInt(plotId), motion);
				if(label !== null) {
					this.counters.get(parseInt(plotId))?.marker.setLngLat(label.geometry.coordinates);
				}

				this.moveBeds(plotId, motion);

			});

		return this;

	}

	/* Déplace une forme de la carte depuis celle qu'elle avait avant le geste, gardée parmi les masquées pour revenir à l'abandon.
	   Sans mouvement, elle revient à sa place. Rend la forme posée, ou null */
	moveFeature(source, key, motion) {

		let original = this.masked.find(([sourceMasked, keyMasked]) => sourceMasked === source && keyMasked === key)?.[2];

		if(original === undefined) {

			original = this.features[source].get(key);

			if(original === undefined || motion === null) {
				return null;
			}

			this.masked.push([source, key, original]);

		}

		const moveOne = feature => {

			// Un clone qui garde les tableaux partagés déplacerait deux fois le point qui ferme un anneau
			const moved = JSON.parse(JSON.stringify(feature));
			turf.coordEach(moved, point => {
				[[point[0], point[1]]] = MapDrawingCopy.getTransformed([point], motion.origin, motion.shift, motion.angle);
			});

			// Le nom d'une planche tourne avec elle
			if(source === 'bedLabels') {
				moved.properties.rot = MapCartography.getRotation(moved.properties.rot + motion.angle);
			}

			return moved;

		};

		// Les planches d'un trait se rangent en liste sous sa clé, un jardin et son étiquette seuls sous la leur
		const feature = (motion === null) ? original : (Array.isArray(original) ? original.map(moveOne) : moveOne(original));

		this.setFeature(source, key, feature);

		return feature;

	}

	/* Un tracé abandonné remet ce qui était masqué ; un tracé enregistré le laisse à sa cible, qui le redessine */
	clearMask(restore) {

		if(restore) {
			this.masked.forEach(([source, key, feature]) => {
				this.setFeature(source, key, feature);
				if(source === 'plotLabels') {
					this.counters.get(key)?.marker.setLngLat(feature.geometry.coordinates).addTo(this.mapbox);
				}
			});
		}

		this.masked = [];

		return this;

	}

	/* Une réécriture par source modifiée : une carte de 956 planches ne réécrit pas sa source à chaque
	   tracé ajouté */
	flush() {

		this.sourcesDirty.forEach(source => {
			this.mapbox.getSource(source).setData({
				type: 'FeatureCollection',
				/* Rangées par clé : l'ordre décide de ce qui passe dessus quand deux formes se recouvrent, et celui d'arrivée
				   n'est pas le même après une réponse ciblée, qui repose les siennes en dernier, et après un rechargement */
				features: [...this.features[source].entries()]
					.sort(([keyA], [keyB]) => String(keyA).localeCompare(String(keyB), 'fr', {numeric: true}))
					.map(([, feature]) => feature)
					.flat()
			});
		});

		this.sourcesDirty.clear();

		/* L'aperçu du dessin se construit sur les formes */
		if(this.basemap === 'satellite') {
			this.refreshBasemapControl();
		}

	}

	/* Le nom se pose au milieu de la plus large corde horizontale de la forme qui tient encore à 12 %
	   de sa hauteur au-dessus et au-dessous : le centre de la forme tombe parfois sur un bord. Il paraît
	   au zoom où cette corde est assez large pour lui. */
	getLabel(id, name, coords, mode, size) {

		const anchor = MapCartography.getLabelAnchor(coords, 0.12) ?? MapCartography.getLabelAnchor(coords, 0);

		if(anchor === null) {
			return null;
		}

		const [lng, lat] = anchor.point;
		const latitude = lat * Math.PI / 180;

		const widthMeters = anchor.width * Math.cos(latitude) * 111320;
		const textPixels = [...name].length * size.em * size.size + MapCartography.LABEL_MARGIN;

		return turf.point(anchor.point, {
			id: id,
			name: name,
			mode: mode,
			fit: Math.log2(40075016.686 * Math.cos(latitude) * textPixels / (512 * widthMeters))
		});

	}

	static getLabelAnchor(ring, margin) {

		const latitudes = ring.map(coords => coords[1]);
		const low = Math.min(...latitudes);
		const high = Math.max(...latitudes);
		const delta = (high - low) * margin;

		let best = null;

		for(let i = 1; i < 42; i++) {

			const lat = low + (high - low) * i / 42;

			MapCartography.getChords(ring, lat).forEach(([start, end]) => {

				for(const other of [lat - delta, lat + delta]) {

					const middle = (start + end) / 2;
					const around = MapCartography.getChords(ring, other).find(([from, to]) => from <= middle && middle <= to);

					if(around === undefined) {
						start = middle;
						end = middle;
						break;
					}

					start = Math.max(start, around[0]);
					end = Math.min(end, around[1]);

				}

				if(end - start > (best?.width ?? 0)) {
					best = {width: end - start, point: [(start + end) / 2, lat]};
				}

			});

		}

		return best;

	}

	static getChords(ring, lat) {

		const crossings = [];

		for(let i = 0; i < ring.length; i++) {

			const [x1, y1] = ring[i];
			const [x2, y2] = ring[(i + 1) % ring.length];

			if((y1 <= lat && lat < y2) || (y2 <= lat && lat < y1)) {
				crossings.push(x1 + (lat - y1) * (x2 - x1) / (y2 - y1));
			}

		}

		crossings.sort((a, b) => a - b);

		const chords = [];

		for(let i = 0; i + 1 < crossings.length; i += 2) {
			chords.push([crossings[i], crossings[i + 1]]);
		}

		return chords;

	}

	addZone(zoneId, zoneName, coords, display = true) {

		if(coords === null) {
			return this;
		}

		this.boundsZones[zoneId] = this.extractBoundsFromCoords(coords);

		this.refreshStructureBounds();

		if(display) {
			this.setFeature('zones', zoneId, turf.polygon([coords], {id: zoneId}));
			this.setFeature('zoneLabels', zoneId, this.getLabel(zoneId, zoneName, coords, null, MapCartography.LABEL_ZONE));
		}

		if(zoneId === this.selectedZone) {
			this.refreshSelection();
		}

		return this;

	}

	/* Une forme désignée sur la carte est déjà sous les yeux : la caméra ne bouge pas (`move`). Désignée depuis la liste, elle peut
	   être hors de l'écran, et se cadre */
	clickZone(zoneId, move = true) {

		if(this.mapbox === null) {
			return this.loadZone(zoneId);
		}

		if(move) {
			this.fitZoneBounds(zoneId);
		} else {
			this.select('zone', zoneId);
		}

		this.loadZone(zoneId);

		return this;

	}

	loadZone(zoneId) {

		if(this.loadZoneHandler !== null) {
			this.loadZoneHandler(zoneId);
		} else {
			MapCartography.queryZone(zoneId);
		}

		return this;

	}

	static queryZone(zoneId) {

		if(MapCartography.zoneRefreshHandler === null || MapCartography.zoneRefreshHandler(zoneId) === false) {
			MapCartography.reloadPage();
		}

	}

	static reloadPage() {

		const query = new Ajax.Query();
		query.headers.set('x-requested-context', 'reuse');

		const url = document.body.dataset.context.removeArgument('create');

		if(url !== document.body.dataset.context) {
			document.body.dataset.context = url;
			Lime.History.replaceState(url);
		}

		query
			.method('get')
			.url(url)
			.fetch();

	}

	addPlot(plotId, plotName, zoneId, coords, display = true, mode = 'open-field', undrawn = 0) {

		if(coords === null) {
			return this;
		}

		this.boundsPlots[plotId] = this.extractBoundsFromCoords(coords);
		this.plotsZones[plotId] = zoneId;
		this.plotsModes[plotId] = mode;

		if(display) {

			const label = this.getLabel(plotId, plotName, coords, mode, MapCartography.LABEL_PLOT);

			this.setFeature('plots', plotId, turf.polygon([coords], {id: plotId, mode: mode}));
			this.setFeature('plotLabels', plotId, label);
			this.setPlotCounter(plotId, label, undrawn);

		}

		if(plotId === this.selectedPlot) {
			this.refreshSelection();
		}

		return this;

	}

	clickPlot(plotId, zoneId, move = true) {

		if(this.mapbox === null) {
			return this.loadPlot(plotId, zoneId);
		}

		if(move) {
			this.fitPlotBounds(plotId);
		} else {
			this.select('plot', plotId);
		}

		this.loadPlot(plotId, zoneId);

		return this;

	}

	loadPlot(plotId, zoneId) {

		if(this.loadPlotHandler !== null) {
			this.loadPlotHandler(plotId, zoneId);
		} else {
			MapCartography.queryZone(zoneId);
		}

		return this;

	}

	extractBoundsFromCoords(coords) {

		let lng1 = coords[0][0], lng2 = coords[0][0], lat1 = coords[0][1], lat2 = coords[0][1];

		for(let i = 1; i < coords.length; i++) {

			lng1 = Math.min(lng1, coords[i][0]);
			lat1 = Math.max(lat1, coords[i][1]);

			lng2 = Math.max(lng2, coords[i][0]);
			lat2 = Math.min(lat2, coords[i][1]);

		}

		return [[lng1, lat1], [lng2, lat2]];
	}

	clickStructure() {

		this.fitStructureBounds();

		if(this.loadStructureHandler !== null) {
			this.loadStructureHandler();
		}

	}

	unzoom() {

		if(this.countZones() <= 1) {
			return;
		}

		switch(this.zoom) {

			case 'structure' :
				break;

			case 'plot' :
				this.clickZone(this.selectedZone);
				break;

			case 'zone' :
				this.clickStructure();
				break;

		}

	}

	/* L'impression rend ses cartes l'une après l'autre dans cette seule carte, Chrome n'ouvrant que seize contextes WebGL : elle
	   passe sous chacune, en prend la taille, et la fige en image. Une carte de parcelle a la hauteur que demande son cadre, que sa
	   boite borne. Le nord, l'échelle, le logo, le crédit et la légende sont du HTML posé sur l'image, que le canvas ne porte pas :
	   l'échelle et la légende se lisent sur chaque carte, avant de passer à la suivante */
	printMaps(maps) {

		const container = this.mapbox.getContainer();
		const spacing = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--space-sm')) * rem();

		const next = () => {

			const map = maps.shift();

			if(map === undefined) {
				window.pdfReady = true;
				return;
			}

			const node = qs('#'+ map.map);

			/* Le cadre laisse libres le bas, où se posent l'échelle et le crédit, et le haut de la légende quand elle est sur l'image */
			const legend = node.querySelector('.plan-print-legend');

			const padding = {
				top: (legend === null) ? 36 : legend.offsetTop + legend.offsetHeight + spacing,
				right: 36,
				bottom: 72,
				left: 36
			};

			const capture = () => {

				node.querySelector('img').src = (this.basemap === 'satellite') ?
					this.mapbox.getCanvas().toDataURL('image/jpeg', 0.85) :
					this.mapbox.getCanvas().toDataURL('image/png');

				const seen = {
					zone: this.mapbox.queryRenderedFeatures({layers: ['zone-line']}).length > 0,
					bed: this.mapbox.queryRenderedFeatures({layers: ['bed-fill']}).length > 0
				};

				this.mapbox.queryRenderedFeatures({layers: ['plot-line']}).forEach(feature => seen[feature.properties.mode] = true);

				const legend = (map.zone === null) ? node.closest('.plan-print-page') : node;

				legend.querySelectorAll('[data-legend]').forEach(item => {
					if(seen[item.dataset.legend] !== true) {
						item.remove();
					}
				});

				const scale = this.getPrintScale(container.clientWidth);
				node.querySelector('.plan-print-scale span').textContent = scale.text;
				node.querySelector('.plan-print-scale-bar').style.width = scale.width +'px';

				node.querySelector('.plan-print-logo').innerHTML = container.querySelector('.mapboxgl-ctrl-logo').outerHTML;
				node.querySelector('.plan-print-credit').innerHTML = container.querySelector('.mapboxgl-ctrl-attrib-inner').innerHTML;

				/* Hors de l'écouteur d'idle : Mapbox n'a pas fini le rendu qui l'émet, et un cadrage posé dedans n'en déclenche aucun */
				setTimeout(next);

			};

			if(map.zone !== null) {
				node.style.height = this.getPrintHeight(map.zone, node.clientWidth, padding) +'px';
			}

			node.prepend(container);
			this.mapbox.resize();

			if(map.zone === null) {
				this.fitStructureBounds({duration: 0, padding: padding});
			} else {
				this.fitZoneBounds(map.zone, {duration: 0, padding: padding});
			}

			/* Un idle déjà en file appartient au cadre précédent : on attend le premier rendu du nouveau cadre, que deux cartes au
			   même cadre ne déclencheraient pas sans un rendu demandé */
			this.mapbox.once('render', () => this.mapbox.once('idle', capture));
			this.mapbox.triggerRepaint();

		};

		return this.do(next);

	}

	/* La hauteur qui montre le cadre de la parcelle sur toute la largeur, en projection de Mercator comme la carte */
	getPrintHeight(zoneId, width, padding) {

		const [[lng1, lat1], [lng2, lat2]] = this.getZoneBounds(zoneId);
		const mercator = lat => Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360));
		const ratio = Math.abs(mercator(lat1) - mercator(lat2)) / (Math.abs(lng2 - lng1) * Math.PI / 180);

		return (width - padding.left - padding.right) * ratio + padding.top + padding.bottom;

	}

	/* Un jardin peut déborder du contour de sa parcelle : le cadre de la parcelle couvre aussi ses jardins */
	getZoneBounds(zoneId) {

		return Object.keys(this.plotsZones)
			.filter(plotId => this.plotsZones[plotId] === zoneId && this.boundsPlots[plotId] !== undefined)
			.reduce(([[lng1, lat1], [lng2, lat2]], plotId) => {
				const [[plotLng1, plotLat1], [plotLng2, plotLat2]] = this.boundsPlots[plotId];
				return [[Math.min(lng1, plotLng1), Math.max(lat1, plotLat1)], [Math.max(lng2, plotLng2), Math.min(lat2, plotLat2)]];
			}, this.boundsZones[zoneId]);

	}

	/* Le cadre d'un jardin, avec ce qui l'entoure (PLOT_SURROUNDING) */
	getPlotBounds(plotId) {
		return MapCartography.widenBounds(this.boundsPlots[plotId], MapCartography.PLOT_SURROUNDING);
	}

	/* La plus longue longueur ronde qui tienne dans un cinquième de la largeur de la carte */
	getPrintScale(width) {

		const latitude = this.mapbox.getCenter().lat;
		const metersPerPixel = 40075016.686 * Math.cos(latitude * Math.PI / 180) / (512 * Math.pow(2, this.mapbox.getZoom()));
		const meters = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000].filter(length => length <= width / 5 * metersPerPixel).pop() ?? 1;

		return {
			width: Math.round(meters / metersPerPixel),
			text: (meters >= 1000) ? (meters / 1000) +' km' : meters +' m'
		};

	}

	fitStructureBounds(options) {
		return this.fitBounds(this.boundsStructure, 'structure', null, options);
	}

	fitZoneBounds(zoneId, options) {
		if(this.boundsZones[zoneId] === undefined) {
			return this.fitStructureBounds(options);
		} else {
			return this.fitBounds(this.getZoneBounds(zoneId), 'zone', zoneId, options);
		}
	}

	fitPlotBounds(plotId, options) {
		if(this.boundsPlots[plotId] === undefined) {
			return this.fitZoneBounds(this.plotsZones[plotId], options);
		} else {
			return this.fitBounds(this.getPlotBounds(plotId), 'plot', plotId, options);
		}
	}

	/* Un contour qui n'est pas sur la carte se cadre comme un jardin, avec ce qui l'entoure, sans rien désigner */
	fitCoordinatesBounds(coords, options) {

		this.moveToBounds(MapCartography.widenBounds(this.extractBoundsFromCoords(coords), MapCartography.PLOT_SURROUNDING), 'plot', options);

		return this;

	}

	static widenBounds([[lng1, lat1], [lng2, lat2]], ratio) {

		const lng = (lng2 - lng1) * ratio;
		const lat = (lat1 - lat2) * ratio;

		return [[lng1 - lng, lat1 + lat], [lng2 + lng, lat2 - lat]];

	}

	fitBounds(bounds, zoom, zoomId, options = {}) {

		if(bounds === undefined) {
			return this;
		}

		this.moveToBounds(bounds, zoom, options);

		return this.select(zoom, zoomId);

	}

	/* Le bouton Zoomer recadre la forme déjà désignée, comme à son ouverture : sans select(), qui la redésignerait */
	zoomZone(zoneId) {
		this.moveToBounds(this.getZoneBounds(zoneId), 'zone');
	}

	zoomPlot(plotId) {
		this.moveToBounds(this.getPlotBounds(plotId), 'plot');
	}

	moveToBounds(bounds, zoom, options = {}) {

		switch(zoom) {

			case 'structure' :
				options = {...{
					duration: 200,
					padding: 20
				}, ...options};
				break;

			case 'zone' :
			case 'plot' :
				options = {...{
					duration: 200,
					padding: 40
				}, ...options};
				break;

		}

		if(this.fitPadding !== null) {
			options.padding = this.fitPadding;
		}

		this.mapbox.fitBounds(bounds, options);

	}

	select(zoom, zoomId) {

		/* Une structure d'une seule parcelle ne la désigne pas : elle serait désignée en permanence */
		if(this.interactive) {

			if(zoom === 'plot') {
				this.selectPlot(zoomId);
			} else if(zoom === 'zone' && this.countZones() > 1) {
				this.selectZone(zoomId);
			} else {
				this.selectStructure();
			}

		}

		this.zoom = zoom;

		return this;

	}

	selectStructure() {

		this.selectedPlot = null;
		this.selectedZone = null;

		return this.refreshSelection();

	}

	selectZone(zoneId) {

		this.selectedPlot = null;
		this.selectedZone = zoneId;

		return this.refreshSelection();

	}

	selectPlot(plotId) {

		this.selectedPlot = plotId;
		this.selectedZone = this.plotsZones[plotId];

		return this.refreshSelection();

	}

	/* La forme désignée, la parcelle ou le bloc s'il y en a un, est une copie posée dans sa propre source : elle se
	   repose donc chaque fois que l'original est réécrit ou retiré, et s'efface le temps d'un tracé, où l'ancien
	   contour resterait sinon sous la forme qu'on retrace */
	refreshSelection() {

		let feature = undefined;

		if(this.locked === false) {

			if(this.selectedPlot !== null) {
				feature = this.features.plots.get(this.selectedPlot);
			} else if(this.selectedZone !== null) {
				feature = this.features.zones.get(this.selectedZone);
			}

		}

		this.setFeature('selected', 'shape', feature ?? null);
		this.setFeature('selected', 'veil', (feature === undefined) ? null : turf.polygon([MapCartography.WORLD, feature.geometry.coordinates[0]], {veil: true}));

		return this;

	}

	countZones() {
		return Object.entries(this.boundsZones).length;
	}

	do(callback) {

		/* Poser une source ou une couche ne demande que le style, pas la carte entière : le bon
		   test est isStyleLoaded(). loaded() était trop strict — mesuré durablement faux sur une
		   carte pourtant utilisable — et se rabattre sur le seul événement load ne marchait pas
		   davantage, celui-ci ne se redéclenchant jamais : tout appel postérieur au chargement,
		   comme reposer une parcelle après une action ajax, restait en file sans erreur ni effet.
		   Aucun signal n'est fiable seul, on a mesuré des navigateurs où le style est prêt sans
		   qu'aucun événement ne survienne, et d'autres où styledata précède load. D'où le test
		   immédiat doublé de trois écoutes, la première qui voit le style prêt l'emportant, et
		   d'une scrutation : un style qui devient prêt sans plus aucun événement laissait sinon
		   la file en attente pour toujours — carte peinte, ni polygone ni étiquette dessus.
		   La bascule est unique et non par appel : poser une couche resalit le style, un test
		   refait à chaque appel renverrait les suivants en attente d'un événement déjà passé.
		   Les sources sont réécrites avant tout rappel, qui peut attendre que la carte les ait peintes
		   (`window.pdfReady` de l'impression). */
		if(this.styleReady) {
			this.flush();
			callback(this);
			return this;
		}

		this.pendingCallbacks.push(callback);

		if(this.styleWatcher === null) {

			const events = ['load', 'styledata', 'idle'];

			this.styleWatcher = () => {

				if(this.mapbox.isStyleLoaded() === false) {
					return;
				}

				events.forEach(event => this.mapbox.off(event, this.styleWatcher));
				clearInterval(this.stylePoller);
				this.styleReady = true;

				this.flush();
				this.refreshLabelsZoom();

				const callbacks = this.pendingCallbacks;
				this.pendingCallbacks = [];

				callbacks.forEach(pending => pending(this));

			};

			events.forEach(event => this.mapbox.on(event, this.styleWatcher));
			this.stylePoller = setInterval(this.styleWatcher, 50);
			this.styleWatcher();

		}

		return this;

	}

	/* Une planche tracée, sous la clé de sa ligne (`drawId`) */
	addBed(plotId, drawId, bed, corner, angle) {

		const key = plotId +'-'+ drawId;
		const mode = this.plotsModes[plotId];

		const {polygon, label} = MapCartography.getBed(bed, corner, angle);

		polygon.properties = {mode: mode, plot: plotId, draw: drawId, id: bed.id};
		label.properties.mode = mode;

		this.bedsDrawn[plotId] ??= [];
		this.bedsDrawn[plotId].push(key);

		this.setFeature('beds', key, [polygon]);
		this.setFeature('bedLabels', key, [label]);

		return this;

	}

	/* Un jardin qui remplit sa parcelle n'a pas de polygone propre, donc pas d'addPlot ni d'entrée
	   dans plotsZones — alors que ses planches, elles, sont bien dessinées. Sans ce rattachement
	   explicite removeZone() les laisserait derrière lui, et ses planches prendraient la couleur
	   d'un jardin de plein champ. */
	attachPlot(plotId, zoneId, mode) {

		this.plotsZones[plotId] = zoneId;
		this.plotsModes[plotId] = mode;

		return this;

	}

	removeBeds(plotId) {

		(this.bedsDrawn[plotId] ?? []).forEach(key => {
			this.setFeature('beds', key, null);
			this.setFeature('bedLabels', key, null);
		});

		delete this.bedsDrawn[plotId];

		return this;

	}

	/* Retire les tracés de planches d'un jardin, et eux seuls. Une ligne que le geste a masquée n'a plus rien à montrer : elle part aussi */
	removeBedsById(plotId, ids) {

		const removed = (this.bedsDrawn[plotId] ?? []).filter(key => this.features.beds.get(key).every(polygon => ids.includes(polygon.properties.id)));

		removed.forEach(key => {
			this.setFeature('beds', key, null);
			this.setFeature('bedLabels', key, null);
		});

		this.bedsDrawn[plotId] = (this.bedsDrawn[plotId] ?? []).filter(key => removed.includes(key) === false);

		return this;

	}

	removePlot(plotId) {

		this.removeBeds(plotId);

		delete this.boundsPlots[plotId];
		delete this.plotsZones[plotId];
		delete this.plotsModes[plotId];

		this.setFeature('plots', plotId, null);
		this.setFeature('plotLabels', plotId, null);
		this.setPlotCounter(plotId, null, 0);

		if(plotId === this.selectedPlot) {
			this.refreshSelection();
		}

		return this;

	}

	removeZone(zoneId) {

		Object.entries(this.plotsZones)
			.filter(([, id]) => id === zoneId)
			.forEach(([plotId]) => this.removePlot(parseInt(plotId)));

		delete this.boundsZones[zoneId];

		this.refreshStructureBounds();

		this.setFeature('zones', zoneId, null);
		this.setFeature('zoneLabels', zoneId, null);

		if(zoneId === this.selectedZone) {
			this.refreshSelection();
		}

		return this;

	}

	/* Les bornes de la structure cumulent celles des parcelles : elles ne se décrémentent pas,
	   il faut les refaire depuis ce qui reste */
	refreshStructureBounds() {

		this.boundsStructure = undefined;

		Object.values(this.boundsZones).forEach(bounds => {

			if(this.boundsStructure === undefined) {
				this.boundsStructure = [[bounds[0][0], bounds[0][1]], [bounds[1][0], bounds[1][1]]];
			} else {
				this.boundsStructure[0][0] = Math.min(this.boundsStructure[0][0], bounds[0][0]);
				this.boundsStructure[0][1] = Math.max(this.boundsStructure[0][1], bounds[0][1]);
				this.boundsStructure[1][0] = Math.max(this.boundsStructure[1][0], bounds[1][0]);
				this.boundsStructure[1][1] = Math.min(this.boundsStructure[1][1], bounds[1][1]);
			}

		});

		return this;

	}

	/* L'angle du numéro d'une planche dont la largeur part au cap `bearing`, en degrés, retourné quand il se lirait la tête en bas.
	   L'aperçu d'une copie ou d'un contour tourné ajoute sa rotation au cap : il peut faire plus d'un demi-tour, et le numéro ne se
	   retourne qu'une fois ramené dans [-180, 180[ */
	static getRotation(bearing) {

		const heading = ((bearing + 180) % 360 + 360) % 360 - 180;

		return (heading > 75) ? heading - 180 : ((heading < -105) ? heading + 180 : heading);

	}

	/* Le rectangle d'une planche depuis son coin : la largeur part au cap `angle`, en radians depuis le nord dans le sens des
	   aiguilles, et la longueur à angle + 90°, comme DrawLib::getLine(). Son numéro suit la largeur, retourné quand il se lirait
	   la tête en bas, comme dans getBeds(). */
	static getBed(bed, corner, angle) {

		const bearing = angle * 180 / Math.PI;
		const rotation = MapCartography.getRotation(bearing);

		const across = turf.destination(corner, bed.width / 100 / 1000, bearing).geometry.coordinates;

		const polygon = turf.polygon([[
			corner,
			across,
			turf.destination(across, bed.length / 1000, bearing + 90).geometry.coordinates,
			turf.destination(corner, bed.length / 1000, bearing + 90).geometry.coordinates,
			corner
		]]);

		const label = turf.centroid(polygon, {
			properties: {
				name: bed.name,
				rot: rotation
			}
		});

		return {polygon, label};

	}

	/* Les planches d'un tracé, côte à côte le long de la première ligne, séparées par des allées
	   égales, et longues dans le sens de la seconde. Leur numéro suit la première ligne, retourné
	   quand il se lirait la tête en bas. */
	static getBeds(bedsList, coords) {

		const bedsLine = coords.slice(0, 2);
		const bedsLineWidth = turf.distance(...bedsLine);
		const bedsLineBearing = turf.bearing(...bedsLine);

		const bedsDirection = coords.slice(1, 3);
		const bedsDirectionBearing = turf.bearing(...bedsDirection);

		const bedsRealWidth = bedsList.reduce((total, bed) => total + bed.width / 100 / 1000, 0);

		const bedsAlleyWidth = (bedsList.length > 1) ? (bedsLineWidth - bedsRealWidth) / (bedsList.length - 1) : null;

		const rotation = (bedsLineBearing < -90) ? bedsLineBearing + 180 : ((bedsLineBearing > 75) ? bedsLineBearing - 180 : bedsLineBearing);

		const polygons = [];
		const labels = [];

		let currentLinePosition = 0;

		bedsList.forEach(bed => {

			const bedLength = bed.length / 1000;
			const bedWidth = bed.width / 100 / 1000;

			const p1 = turf.destination(coords[0], currentLinePosition, bedsLineBearing).geometry.coordinates;

			currentLinePosition += bedWidth;

			const p2 = turf.destination(coords[0], currentLinePosition, bedsLineBearing).geometry.coordinates;

			currentLinePosition += bedsAlleyWidth;

			const p3 = turf.destination(p2, bedLength, bedsDirectionBearing).geometry.coordinates;
			const p4 = turf.destination(p1, bedLength, bedsDirectionBearing).geometry.coordinates;

			const polygon = turf.polygon([[p1, p2, p3, p4, p1]]);
			polygons.push(polygon);

			labels.push(turf.centroid(polygon, {
				properties: {
					name: bed.name,
					rot: rotation
				}
			}));

		});

		return {polygons: polygons, labels: labels};

	}

}
