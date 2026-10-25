class MapPlan {

	static lastBed = null;
	static drag = null;
	static suppressClick = false;
	static touchDelay = 200;
	// Le jardin dont « Dessiner les planches » demande le dessin : son niveau, une fois rendu, coche ce qui reste à tracer
	static drawUndrawn = null;
	// La planche cliquée sur la carte dans sa forme désignée : son jardin s'ouvre et l'y coche (pickBed())
	static pickedBed = null;

	/* Les planches cochées à l'ouverture d'un geste, qu'« Annuler » rend */
	static checkedBefore = [];

	// La position de la liste des planches avant qu'une réponse la rende, que showDrawing() repose (keepConsoleScroll())
	static consoleScroll = null;

	// Le tracé d'une parcelle sans contour, ouvert depuis la liste des parcelles, qu'« Annuler » rend (openZoneDrawing())
	static zoneDrawing = false;

	static refreshZone(zoneId, keepCreate = false) {

		const zone = qs('#plan-zone-'+ zoneId);
		const plan = qs('#plan-container');

		if(zone === null || plan === null) {
			return false;
		}

		if(keepCreate === false) {
			MapPlan.setContext(document.body.dataset.context.removeArgument('create'));
		}

		const args = new URL(document.body.dataset.context, document.location.href).searchParams;

		let url = '/map/map:getZone?zone='+ zoneId +'&season='+ plan.dataset.season;

		['plot', 'table', 'create'].forEach(name => {
			if(args.has(name)) {
				url += '&'+ name +'='+ args.get(name);
			}
		});

		MapPlan.clearSelection();

		new Ajax.Query()
			.method('get')
			.url(url)
			.fetch();

		return true;

	}

	static toggleSheet(zoneId, plotId, sheet, open) {

		let context = document.body.dataset.context
			.removeArgument('zone').removeArgument('plot').removeArgument('table')
			.setArgument('zone', zoneId)
			.setArgument('plot', plotId);

		if(open) {
			context = context.setArgument(sheet, 1);
		}

		MapPlan.setContext(context);
		MapPlan.refreshZone(zoneId, true);

	}

	static setContext(context) {

		if(context !== document.body.dataset.context) {
			document.body.dataset.context = context;
			Lime.History.replaceState(context);
		}

	}

	static clickBed(node, event) {

		if(MapPlan.suppressClick) {
			MapPlan.suppressClick = false;
			return;
		}

		const plot = node.firstParent('.plan-schema-plot');
		const current = qs('.plan-schema-plot.has-selection');

		if(current !== null && current !== plot) {
			MapPlan.clearSelection();
		}

		if(event.shiftKey && MapPlan.lastBed !== null && MapPlan.lastBed.firstParent('.plan-schema-plot') === plot) {

			const beds = [...plot.qsa('.plan-schema-bed:not(.plan-schema-bed-add)')];
			const from = beds.indexOf(MapPlan.lastBed);
			const to = beds.indexOf(node);

			beds.slice(Math.min(from, to), Math.max(from, to) + 1).forEach(bed => bed.classList.add('selected'));

		} else {
			node.classList.toggle('selected');
		}

		MapPlan.lastBed = node;

		MapPlan.refreshSelection(plot);

	}

	static startDrag(node, event) {

		MapPlan.suppressClick = false;

		if(event.button !== undefined && event.button !== 0) {
			return;
		}

		const plot = node.firstParent('.plan-schema-plot');

		const touch = (event.pointerType !== undefined && event.pointerType !== 'mouse');

		MapPlan.drag = {
			plot: plot,
			start: node,
			current: node,
			touched: [],
			before: [...plot.qsa('.plan-schema-bed.selected:not(.plan-schema-bed-add)')],
			select: node.classList.contains('selected') === false,
			moved: false,
			armed: touch === false,
			timer: null
		};

		if(touch) {
			MapPlan.drag.timer = setTimeout(() => {
				if(MapPlan.drag !== null) {
					MapPlan.drag.armed = true;
					MapPlan.drag.timer = null;
				}
			}, MapPlan.touchDelay);
		}

	}

	static moveDrag(event) {

		const drag = MapPlan.drag;

		if(drag === null || drag.armed === false) {
			return;
		}

		const target = document.elementFromPoint(event.clientX, event.clientY);
		const bed = target === null ? null : target.closest('.plan-schema-bed:not(.plan-schema-bed-add)');

		if(bed === null || bed === drag.current || bed.firstParent('.plan-schema-plot') !== drag.plot) {
			return;
		}

		if(drag.moved === false) {

			drag.moved = true;

			const current = qs('.plan-schema-plot.has-selection');

			if(current !== null && current !== drag.plot) {
				MapPlan.clearSelection();
				drag.before = [];
			}

		}

		drag.current = bed;

		const beds = [...drag.plot.qsa('.plan-schema-bed:not(.plan-schema-bed-add)')];
		const from = beds.indexOf(drag.start);
		const to = beds.indexOf(bed);
		const range = beds.slice(Math.min(from, to), Math.max(from, to) + 1);

		drag.touched.forEach(touched => {
			if(range.includes(touched) === false) {
				touched.classList.toggle('selected', drag.before.includes(touched));
			}
		});

		range.forEach(touched => touched.classList.toggle('selected', drag.select));
		drag.touched = range;

		MapPlan.refreshSelection(drag.plot);

		event.preventDefault();

	}

	static endDrag() {

		const drag = MapPlan.drag;

		if(drag === null) {
			return;
		}

		MapPlan.drag = null;

		if(drag.timer !== null) {
			clearTimeout(drag.timer);
		}

		if(drag.moved) {
			MapPlan.lastBed = drag.current;
			MapPlan.suppressClick = true;
		}

	}

	static clearSelection() {

		qsa('.plan-schema-bed.selected', bed => bed.classList.remove('selected'));
		qsa('.plan-schema-plot.has-selection', plot => plot.classList.remove('has-selection'));

		MapPlan.lastBed = null;

		if(qs('#plan-batch') !== null) {
			Batch.hideSelection('#plan-batch');
		}

	}

	static refreshSelection(plot) {

		const beds = [...plot.qsa('.plan-schema-bed:not(.plan-schema-bed-add)')];
		const selected = beds.filter(bed => bed.classList.contains('selected'));

		if(selected.length === 0 || qs('#plan-batch') === null) {
			MapPlan.clearSelection();
			return;
		}

		plot.classList.add('has-selection');

		beds.forEach(bed => bed.qs('[name="batch[]"]', input => input.checked = bed.classList.contains('selected')));

		Batch.changeSelection('#plan-batch', null, selection => {

			const form = qs('#plan-batch-form');

			['plot', 'season', 'origin'].forEach(name => {
				if(form.qs('[name="'+ name +'"]') === null) {
					form.insertAdjacentHTML('afterbegin', '<input type="hidden" name="'+ name +'"/>');
				}
			});

			form.qs('[name="plot"]').value = plot.dataset.plot;
			form.qs('[name="season"]').value = qs('#plan-container').dataset.season;
			form.qs('[name="origin"]').value = qs('#plan-container').dataset.origin;

			qs('#plan-batch-update', link => {
				link.classList.toggle('hide', selection.length !== 1);
				link.setAttribute('href', '/map/bed:update?id='+ selection[0].value +'&season='+ qs('#plan-container').dataset.season);
			});

			qs('#plan-batch-draw', link => {
				link.classList.toggle('hide', plot.dataset.draw !== '1');
				link.setAttribute('href', plot.dataset.drawUrl + [...selection].map(input => '&ids[]='+ input.value).join(''));
			});

		});

	}

	static toggleSelection(target) {

		CheckboxField.all(target.firstParent('form'), target.checked, '[name^="ids[]"]');

		this.changeSelection(target);

	}

	static changeSelection(target) {

		const form = target.firstParent('form');

		let drawings = 0;

		form.qsa('[name="ids[]"]:checked', input => {
			drawings += (input.dataset.drawn === '1') ? 1 : 0;
		});

		form.qs('[data-batch="draw-delete"]', node => node.classList.toggle('hide', drawings === 0));

		/* La console du jardin coche les planches que son adresse désigne */
		form.qs('[data-batch="draw"]', link => link.setAttribute('href', link.dataset.url + [...form.qsa('[name="ids[]"]:checked')].map(input => '&ids[]='+ input.value).join('')));

	}

	/**
	 * Chaque planche se coche seule, tracée ou non. Pendant un rang neuf, cocher ou décocher une planche refait le rang.
	 */
	static changeConsoleBeds(form) {

		this.refreshConsoleCard(form);

		const drawing = MapDrawing.current;

		if(drawing === null || (drawing.shape instanceof MapDrawingLine) === false) {
			return;
		}

		drawing.changeBeds();

	}

	// Les cases d'une section de la liste qui se cochent : une planche sans dimensions n'a pas de rectangle à poser
	static getConsoleSection(form, section) {
		return [...form.qsa('[name="ids[]"][data-section="'+ section +'"]:not(:disabled)')];
	}

	/* La case du titre coche ou décoche d'un coup les planches de sa section, comme si on les cochait une à une */
	static changeConsoleAll(input) {

		MapPlan.getConsoleSection(input.form, input.dataset.consoleAll).forEach(bed => bed.checked = input.checked);

		MapPlan.changeConsoleBeds(input.form);

	}

	/* La rangée nomme ce qu'elle désigne : le jardin sans case cochée, sinon les planches cochées et ce qui se fait sur elles */
	static refreshConsoleCard(form) {

		const card = qs('#plan-console-card');
		const checked = [...form.qsa('[name="ids[]"]:checked')];

		form.qsa('[data-console-all]', all => {
			const beds = MapPlan.getConsoleSection(form, all.dataset.consoleAll);
			const count = beds.filter(bed => bed.checked).length;
			all.checked = (beds.length > 0 && count === beds.length);
			all.indeterminate = (count > 0 && count < beds.length);
		});

		card.qsa('[data-console-face]', face => face.classList.toggle('hide', face.dataset.consoleFace !== ((checked.length === 0) ? 'plot' : 'beds')));
		const drawn = checked.filter(input => input.dataset.section === 'drawn').length;
		card.qsa('[data-console-drawn]', item => item.classList.toggle('hide', drawn < parseInt(item.dataset.consoleDrawn)));

		const link = card.qs('[data-drawing="line"]');

		MapCartography.get(link.dataset.drawingMap, carto => carto.selectBeds(checked.map(input => parseInt(input.value))));

		if(checked.length === 0) {
			return;
		}

		const labels = JSON.parse(card.dataset.labels);

		card.qs('[data-console-names]').textContent = MapPlan.getConsoleNames(checked.map(input => input.dataset.name));
		card.qsa('[data-console-url]', item => item.href = item.dataset.consoleUrl + checked.map(input => '&ids[]='+ input.value).join(''));
		link.qs('[data-console-label]').textContent = (checked.length === 1) ? labels.drawOne : labels.drawMany.replace('{count}', checked.length);

	}

	// Trois noms au plus, comme DrawUi::getCheckedNames() : le bouton voisin dit déjà combien de planches sont cochées
	static getConsoleNames(names) {
		return names.slice(0, 3).join(', ') + (names.length > 3 ? '…' : '');
	}

	/* Une planche cliquée sur la carte de la console se coche, ou se décoche si elle l'était. Celle d'un autre jardin n'est pas dans la liste */
	static clickConsoleBed(bed) {

		const input = qs('#plan-console-beds [name="ids[]"][value="'+ bed +'"]');

		if(input === null || input.disabled) {
			return;
		}

		input.checked = (input.checked === false);

		MapPlan.changeConsoleBeds(input.form);

		if(input.checked) {
			MapPlan.showConsoleBed(input);
		}

	}

	/* Amène une case là où la liste se lit : dans son propre défilement quand elle en a un, sinon sous la carte, qui
	   reste collée en haut de la page. Sa place collée se déduit de son décalage et de sa hauteur : tant que la page
	   n'a pas défilé, elle est encore plus bas. En bas, la page s'arrête à la barre fixe, dont plan.css donne la hauteur
	   à la case en marge de défilement. Une case déjà en vue ne bouge pas. Tout se lit avant le défilement. */
	static showConsoleBed(input) {

		const row = input.closest('.plan-console-bed');
		const list = row.closest('.plan-console-beds');
		const view = qs('.plan-console .plan-mapview');
		const scrolls = (list.scrollHeight > list.clientHeight);

		const rowBounds = row.getBoundingClientRect();
		const listBounds = list.getBoundingClientRect();
		const top = scrolls ? listBounds.top : parseFloat(getComputedStyle(view).top) + view.offsetHeight;
		const bottom = scrolls ? listBounds.bottom : window.innerHeight - parseFloat(getComputedStyle(row).scrollMarginBottom);

		if(rowBounds.top >= top && rowBounds.bottom <= bottom) {
			return;
		}

		(scrolls ? list : window).scrollBy(0, rowBounds.top - top);

	}

	/* La liste se décoche sans toucher à l'adresse : un changement de niveau pose lui-même la sienne, et un clic sur la carte
	   passe par unpickConsoleBeds() */
	static clearConsoleBeds() {

		const form = qs('#plan-console-beds');

		form.qsa('[name="ids[]"]:checked', input => input.checked = false);

		MapPlan.refreshConsoleCard(form);

	}

	/* Un clic sur la carte loin de toute planche, dans le jardin ou hors de toute parcelle, décoche la liste, et l'adresse ne
	   désigne plus de planche : un rechargement rend le jardin sans case cochée */
	static unpickConsoleBeds() {

		MapPlan.clearConsoleBeds();
		MapPlan.setContext(qs('#plan-console-drawing').dataset.url);

	}

	/* Une réponse qui pose ou retire des planches sans passer par un geste les pose elle-même sur la carte : seul un geste qui se ferme
	   le fait d'ordinaire */
	static refreshConsoleDraws(json) {
		MapDrawing.targets.beds(MapCartography.get(json.map), json);
	}

	/* Les planches se posent avant que le geste s'ouvre, qui prend sur la carte l'instantané que rend « Annuler ». Le geste s'ouvre
	   par son lien, sa seule entrée */
	static openConsoleDrawing(json) {

		MapPlan.refreshConsoleDraws(json);

		qs('#plan-console-card [data-drawing="line"]')?.click();

	}

	static refreshMapPadding(carto) {
		// Le bas réserve la fiche la plus haute, celle d'un pas à pas dont les noms de planches passent sur un second rang : le cadrage se fait avant qu'elle s'ouvre.
		// La gauche réserve le bloc que plan.css pose sur la carte, mesuré parce que sa largeur suit celle de la carte, sans lequel la carte se cadre sur toute sa largeur.
		// Le haut réserve les actions du jardin désigné, posées au-dessus de son contour, et leur écart : sous 768 px leurs boutons ont la taille du doigt,
		// et la droite y laisse la colonne des boutons de la carte, que les actions croiseraient. Un geste les cache, et mesure lui-même sa fiche
		const side = qs('.plan-console-side');
		const container = qs('#plan-mapview-map').getBoundingClientRect();
		const controls = qs('#plan-mapview-map .mapboxgl-ctrl-top-right')?.getBoundingClientRect() ?? null;
		const sideRight = (side === null) ? null : Math.round(side.getBoundingClientRect().right - container.left);
		const left = (sideRight === null) ? 40 : sideRight + 42;
		const wide = (window.innerWidth > 767);

		carto.fitPadding = wide ? {top: 70, right: 40, bottom: 130, left: left} : {top: 84, right: 50, bottom: 30, left: 30};

		// Ce qui se voit de la carte, où une rangée posée au-dessus d'une forme se cale quand la forme en sort : à droite du bloc
		// au-dessus de 768 px, sous lesquels il passe au-dessus de la carte, et à gauche de la colonne des boutons
		const margin = MapDrawing.VIEW_MARGIN;

		// La colonne des boutons garde l'écart --space-md, d'un rem, avec ce qui se pose à sa gauche
		const controlsMargin = parseFloat(getComputedStyle(document.documentElement).fontSize);

		carto.inset = {
			left: (wide && sideRight !== null) ? sideRight + margin : margin,
			top: margin,
			right: container.width - margin,
			bottom: container.height - margin,
			controls: (controls === null) ? null : {left: controls.left - container.left - controlsMargin, bottom: controls.bottom - container.top + margin}
		};

		const right = (carto.inset.controls === null) ? carto.inset.right : carto.inset.controls.left;

		qs('#plan-mapview-map').parentElement.style.setProperty('--plan-mapview-actions-width', Math.max(0, right - carto.inset.left) +'px');
		qs('#plan-mapview-map').parentElement.style.setProperty('--plan-mapview-actions-left', carto.inset.left +'px');

		// La colonne des boutons avec la marge que Mapbox lui donne, à côté de laquelle plan.css pose la rangée du tracé : sa largeur
		// change avec la version de Mapbox
		qs('#plan-mapview-map').parentElement.style.setProperty('--plan-mapview-controls-width', ((controls === null) ? 0 : container.right - controls.left) +'px');

		MapPlan.placeActions(carto);
	}

	// Le bloc de la vue Carte montre un niveau à la fois : la structure, une parcelle et ses jardins, ou les planches d'un jardin.
	// La carte ne coche des planches et ne les rend au clic qu'au troisième
	static showLevel(level, zoneId = null) {

		// Un menu de la carte s'ouvre sur ce qui est désigné : changer de niveau le rend caduc, et data-ajax arrête le clic qui le fermerait
		qsa('.plan-mapview [data-dropdown-display]', button => Lime.Dropdown.close(button));

		// Le niveau des planches n'est rendu qu'à qui peut dessiner (map.v.php)
		const leavingBeds = (level !== 'plot' && qs('#plan-console-drawing:not(.hide)') !== null);

		qsa('.plan-console-level', node => node.classList.toggle('hide', node.dataset.level !== level || (zoneId !== null && parseInt(node.dataset.zone) !== zoneId)));

		// Sous 768 px la liste suit la carte dans la page : quitter les planches, plus longues, laisserait la page sous la liste des jardins
		if(leavingBeds && window.innerWidth <= 767) {
			window.scrollTo(0, 0);
		}

		// Quitter le jardin pour sa parcelle ou la structure vide sa sélection : on y revient à zéro case
		if(level === 'zone' || level === 'structure') {
			qs('#plan-console-beds', form => {
				if(form.qs('[name="ids[]"]:checked') !== null) {
					MapPlan.clearConsoleBeds();
				}
			});
		}

		if(level !== 'plot') {
			MapCartography.get('plan-mapview-map', carto => {
				carto.bedHandler = null;
				carto.bedsPlot = null;
				carto.selectBeds([]);
			});
		}

	}

	static bindMap(carto) {

		carto.loadZoneHandler = zoneId => MapPlan.showZone(zoneId);
		carto.loadPlotHandler = (plotId, zoneId) => MapPlan.showPlot(plotId, zoneId);
		carto.loadStructureHandler = () => MapPlan.showStructure();
		carto.clearBedsHandler = () => MapPlan.unpickConsoleBeds();
		// Une planche ne se coche que dans le niveau des planches, rendu seulement à qui peut dessiner (map.v.php) : ailleurs, son clic reste celui de la forme
		if(qs('#plan-console-drawing') !== null) {
			carto.pickBedHandler = (plotId, bedId) => MapPlan.pickBed(plotId, bedId);
		}

		// La fin d'un tracé rend le bouton plein à « Nouvelle parcelle » s'il ne reste aucun autre geste à l'écran
		carto.drawingHandler = (ended, saved) => {

			const form = qs('#plan-console-beds');

			// Un geste enregistré rend le jardin coché comme sa réponse l'a rendu ; annulé, il rend les cases qu'il avait trouvées en s'ouvrant
			if(form !== null) {

				if(ended === false) {
					MapPlan.checkedBefore = [...form.qsa('[name="ids[]"]:checked')].map(input => input.value);
				} else if(saved) {
					MapPlan.refreshConsoleCard(form);
				} else {
					form.qsa('[name="ids[]"]', input => input.checked = MapPlan.checkedBefore.includes(input.value));
					MapPlan.refreshConsoleCard(form);
				}

			}

			if(ended && MapPlan.zoneDrawing) {

				MapPlan.zoneDrawing = false;

				if(saved === false) {
					carto.select('structure');
					MapPlan.showStructure();
				}

			}

			MapPlan.refreshZoneCreate();

		};

		// Un seul marqueur porte les actions de la parcelle ou du jardin désigné : MapPlan.refreshActions() y range celles de la page, le serveur n'en remplace que le contenu
		const actions = document.createElement('div');
		actions.className = 'plan-mapview-marker';

		MapDrawing.CARD_EVENTS.forEach(type => actions.addEventListener(type, e => e.stopPropagation()));

		carto.actions = new mapboxgl.Marker({element: actions, anchor: 'bottom', offset: [0, -MapDrawing.CARD_GAP]});
		carto.actionsRing = null;
		carto.actionsSize = null;

		// Les actions restent dans la carte quand leur forme en sort : chaque mouvement n'écrit que leur position, d'après une taille
		// que l'observateur tient à jour sans faire relire le document
		new ResizeObserver(entries => {
			const box = entries[0].borderBoxSize[0];
			carto.actionsSize = {width: box.inlineSize, height: box.blockSize};
			MapPlan.placeActions(carto);
		}).observe(actions);

		carto.mapbox.on('move', () => MapPlan.placeActions(carto));

		MapPlan.refreshMapPadding(carto);

	}

	static placeActions(carto) {

		if(carto.actionsRing === null || carto.actionsSize === null) {
			return;
		}

		const at = MapDrawing.placeInView(carto.mapbox, carto.actionsRing, carto.actionsSize, carto.inset, MapDrawing.CARD_GAP);

		carto.actions.setLngLat(carto.mapbox.unproject([at.x, at.y + carto.actionsSize.height + MapDrawing.CARD_GAP]));

	}

	/* Les actions du jardin désigné se posent au-dessus de son contour : celles de son troisième niveau quand il est ouvert, sinon
	   celles que la page garde pour lui, ou pour sa parcelle quand aucun jardin n'est désigné. Celles d'avant retournent cachées
	   dans la vue, où le serveur les retrouve */
	static refreshActions() {

		MapCartography.get('plan-mapview-map', carto => {

			const view = carto.mapbox.getContainer().parentElement;
			const holder = carto.actions.getElement();
			const level = qs('.plan-console-level:not(.hide)');

			let content = null;

			if(level !== null && level.dataset.level === 'plot') {
				content = qs('#plan-console-card');
			} else if(level !== null && level.dataset.level === 'zone') {
				const item = level.qs('.plan-item.selected[data-plot]');
				content = (item === null) ?
					view.qs('[data-zone-actions="'+ level.dataset.zone +'"]') :
					view.qs('[data-plot-actions="'+ item.dataset.plot +'"]');
			}

			[...holder.children]
				.filter(node => node !== content)
				.forEach(node => {
					node.classList.add('hide');
					view.append(node);
				});

			if(content === null || content.dataset.anchor === undefined) {
				carto.actionsRing = null;
				carto.actions.remove();
			} else {

				holder.append(content);
				content.classList.remove('hide');

				carto.actionsRing = JSON.parse(content.dataset.anchor);
				carto.actions.setLngLat(MapDrawing.getTop(carto.actionsRing)).addTo(carto.mapbox);

				MapPlan.placeActions(carto);

			}

			// Le marqueur se lit une fois rempli : le geste du pas à pas peut y être entré ou en être sorti
			MapPlan.refreshZoneCreate();

		});

	}

	// bindMap() rejoue à chaque rendu de la vue : l'écouteur de resize est posé une fois, au bas du fichier, et retrouve ici la carte du rendu en cours
	static refreshMapView() {

		if(qs('#plan-mapview-map') === null) {
			return;
		}

		MapCartography.get('plan-mapview-map', carto => MapPlan.refreshMapPadding(carto));

	}

	static showZone(zoneId) {

		qsa('.plan-console-level .plan-item[data-plot]', item => item.classList.remove('selected'));

		MapPlan.showLevel('zone', zoneId);

		MapPlan.refreshActions();

		// `draw` n'a servi qu'à lancer un tracé au chargement (map.p.php) : l'adresse gardée revient à la parcelle sans le relancer,
		// ni rouvrir les planches du jardin qu'on quitte (`beds`, `ids[]`)
		MapPlan.setContext(document.body.dataset.context.removeArgument('plot').removeArgument('draw').removeArgument('beds').removeArgument('ids[]').setArgument('zone', zoneId));

	}

	/* Une parcelle sans contour n'a qu'un geste, son tracé, que son clic lance : son bloc tient la gauche de la carte le temps du
	   geste, sans caméra puisqu'elle n'a pas où se poser, et l'annuler rend la liste d'où il est parti */
	static openZoneDrawing(zoneId) {

		MapCartography.get('plan-mapview-map', carto => carto.clickZone(zoneId, false));

		MapPlan.zoneDrawing = true;

	}

	// Un jardin se désigne sans ses planches. Celles qu'il a ouvertes le restent à un clic sur lui hors d'elles, qui décoche la liste
	// comme un clic à côté : seul « Planches » les referme (toggleBeds())
	static showPlot(plotId, zoneId) {

		if(MapPlan.isBedsShown(plotId)) {
			MapPlan.clearConsoleBeds();
			return;
		}

		MapPlan.selectPlot(plotId, zoneId);

	}

	// L'adresse perd `beds`, pour qu'un rechargement rende le jardin tel qu'on le laisse, sans ses planches
	static selectPlot(plotId, zoneId) {

		qsa('.plan-console-level .plan-item[data-plot]', item => item.classList.toggle('selected', parseInt(item.dataset.plot) === plotId));

		MapPlan.showLevel('zone', zoneId);

		MapPlan.refreshActions();

		MapPlan.setContext(document.body.dataset.context.removeArgument('beds').removeArgument('ids[]').setArgument('zone', zoneId).setArgument('plot', plotId));

	}

	static isBedsShown(plotId) {

		// Le niveau des planches n'est rendu qu'à qui peut dessiner (map.v.php)
		const level = qs('#plan-console-drawing:not(.hide)');

		return (level !== null && level.dataset.plot === String(plotId));

	}

	// « Planches » ouvre dans le bloc les planches du jardin désigné, et les referme. Celles posées à même la parcelle (`zoneFill`)
	// rendent la parcelle, où elles n'ont pas de jardin à désigner
	static toggleBeds(plotId, zoneId, zoneFill = false) {

		if(MapPlan.isBedsShown(plotId)) {
			if(zoneFill) {
				MapPlan.showZone(zoneId);
			} else {
				MapPlan.selectPlot(plotId, zoneId);
			}
		} else {
			MapPlan.openDrawing(plotId);
		}

	}

	static zoomZone(zoneId) {
		MapCartography.get('plan-mapview-map', carto => carto.zoomZone(zoneId));
	}

	static zoomPlot(plotId) {
		MapCartography.get('plan-mapview-map', carto => carto.zoomPlot(plotId));
	}

	// Une demande de dessin ouvre le jardin sur le geste de ses planches à dessiner (checkConsoleUndrawn())
	static drawPlot(plotId, zoneId, zoneFill) {
		MapPlan.drawUndrawn = plotId;
		MapPlan.openPlot(plotId, zoneId, zoneFill);
	}

	// Un jardin ouvert depuis la liste l'est comme d'un clic sur la carte, sans case cochée ni geste. Des planches posées à même la
	// parcelle n'ont pas d'autre forme qu'elle : c'est elle que la carte désigne
	static openPlot(plotId, zoneId, zoneFill) {

		MapCartography.get('plan-mapview-map', carto => {

			if(zoneFill) {
				carto.clickZone(zoneId);
				MapPlan.openDrawing(plotId);
			} else {
				carto.clickPlot(plotId, zoneId);
			}

		});

	}

	/* Le niveau d'un jardin vient du serveur, les planches de tous les jardins ne tenant pas dans la page. Déjà rendu, il
	   ne se recharge pas, et un clic sur le jardin hors de ses planches décoche la liste, comme un clic à côté */
	static openDrawing(plotId) {

		const level = qs('#plan-console-drawing');

		if(level.dataset.plot !== String(plotId)) {

			return new Ajax.Query()
				.method('get')
				.url('/map/plot:drawing?id='+ plotId +'&season='+ MapCartography.get('plan-mapview-map').season)
				.fetch();

		} else if(level.classList.contains('hide')) {
			MapPlan.showDrawing(true);
		} else {
			MapPlan.clearConsoleBeds();
			MapPlan.checkConsoleUndrawn();
		}

	}

	/* Une réponse qui rend la liste des planches la lit d'abord : la liste neuve repartirait du haut */
	static keepConsoleScroll() {
		qs('#plan-console-beds', form => MapPlan.consoleScroll = form.scrollTop);
	}

	/* Le troisième niveau rendu : la fiche de son geste prend la place des autres, et les planches cochées se montrent sur la carte.
	   Au chargement, l'adresse est déjà la sienne et peut désigner des planches (`ids[]`), qu'elle garde */
	static showDrawing(withContext) {

		const level = qs('#plan-console-drawing');

		// Une position gardée ne vaut que pour la réponse qui l'a lue : ouvrir un jardin part du haut de sa liste
		if(MapPlan.consoleScroll !== null) {
			qs('#plan-console-beds', form => form.scrollTop = MapPlan.consoleScroll);
			MapPlan.consoleScroll = null;
		}

		qsa('.plan-console-level .plan-item[data-plot]', item => item.classList.toggle('selected', item.dataset.plot === level.dataset.plot));

		MapPlan.showLevel('plot');

		MapCartography.get('plan-mapview-map', carto => {
			carto.bedHandler = MapPlan.clickConsoleBed;
			carto.bedsPlot = parseInt(level.dataset.plot);
		});

		MapPlan.refreshConsoleCard(qs('#'+ level.dataset.form));
		MapPlan.refreshActions();

		if(withContext) {
			MapPlan.setContext(level.dataset.url);
		}

		MapPlan.checkConsoleUndrawn();

		if(MapPlan.pickedBed !== null) {
			const bed = MapPlan.pickedBed;
			MapPlan.pickedBed = null;
			MapPlan.clickConsoleBed(bed);
		}

	}

	/* Une planche cliquée dans sa forme désignée ouvre les planches de son jardin, où elle se coche une fois le niveau rendu
	   (showDrawing()), le jardin pouvant venir du serveur. Les clics suivants la cochent ou la décochent comme la liste (clickConsoleBed()) */
	static pickBed(plotId, bedId) {

		MapPlan.pickedBed = bedId;

		// Un niveau qui n'arrive pas laisserait la planche en attente du prochain showDrawing(), qui la cocherait sans clic
		MapPlan.openDrawing(plotId)?.catch(() => MapPlan.pickedBed = null);

	}

	/* Seule une demande de dessin coche des planches sans que l'utilisateur les désigne : celles qui restent à tracer */
	static checkConsoleUndrawn() {

		const level = qs('#plan-console-drawing');

		if(MapPlan.drawUndrawn === null || level.dataset.plot !== String(MapPlan.drawUndrawn)) {
			return;
		}

		MapPlan.drawUndrawn = null;

		const all = qs('#'+ level.dataset.form +' [data-console-all="undrawn"]');

		if(all === null) {
			return;
		}

		all.checked = true;
		MapPlan.changeConsoleAll(all);

		/* Une demande de dessin ouvre le geste sur les planches qu'elle coche, et « Annuler » rend le jardin sans case cochée,
		   comme on l'a trouvé */
		qs('#plan-console-card [data-drawing="line"]')?.click();
		MapPlan.checkedBefore = [];

	}

	// « Nouvelle parcelle » passe en contour tant que l'écran porte un autre bouton plein, celui d'un tracé ou d'une création, pour
	// qu'un seul reste à l'écran
	static refreshZoneCreate() {

		// La vue 3D porte aussi plan-mapview : la vue Carte se reconnait au conteneur de sa carte
		const map = qs('#plan-mapview-map');
		const create = qs('#plan-zone-create');

		if(map === null || create === null) {
			return;
		}

		const view = map.parentElement;

		const hasStep = (
			view.classList.contains('is-drawing') ||
			qs('.plan-console-level[data-level="create"]:not(.hide)') !== null
		);

		create.classList.toggle('btn-secondary', hasStep === false);
		create.classList.toggle('btn-outline-secondary', hasStep);

	}

	static showStructure() {

		qsa('.plan-console-level .plan-item[data-plot]', item => item.classList.remove('selected'));

		MapPlan.showLevel('structure');

		MapPlan.refreshActions();

		// Comme dans showZone(), l'adresse gardée perd `draw` et les planches ouvertes
		MapPlan.setContext(document.body.dataset.context.removeArgument('zone').removeArgument('plot').removeArgument('draw').removeArgument('beds').removeArgument('ids[]'));

	}

}

class MapPlanSort {

	static card = null;
	static order = null;

	static start(handle, e) {

		MapPlanSort.card = handle.firstParent('.plan-schema-plot');
		MapPlanSort.order = MapPlanSort.getOrder(MapPlanSort.card.parentElement);

		MapPlanSort.card.classList.add('is-dragging');

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', MapPlanSort.card.dataset.plot);

	}

	static over(target, e) {

		const card = MapPlanSort.card;

		if(card === null || target === card || target.parentElement !== card.parentElement || target.qs('.plan-schema-plot-handle') === null) {
			return;
		}

		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';

		const rect = target.getBoundingClientRect();
		const before = (e.clientX < rect.left + rect.width / 2);

		target.parentElement.insertBefore(card, before ? target : target.nextSibling);

	}

	static end() {

		const card = MapPlanSort.card;

		if(card === null) {
			return;
		}

		card.classList.remove('is-dragging');

		const plots = card.parentElement;
		const order = MapPlanSort.getOrder(plots);

		MapPlanSort.card = null;

		if(order.join(',') === MapPlanSort.order.join(',')) {
			return;
		}

		new Ajax.Query()
			.url('/map/plot:doUpdatePositions')
			.method('post')
			.body({zone: plots.dataset.zone, ids: order})
			.fetch();

	}

	static getOrder(plots) {
		return [...plots.qsa('.plan-schema-plot')].filter(node => node.qs('.plan-schema-plot-handle') !== null).map(node => node.dataset.plot);
	}

}

document.addEventListener('navigation.wakeup', () => {
	MapCartography.zoneRefreshHandler = zoneId => MapPlan.refreshZone(zoneId);
});

document.delegateEventListener('dragstart', '.plan-schema-plot-handle', function(e) {
	MapPlanSort.start(this, e);
});

document.delegateEventListener('dragover', '.plan-schema-plot', function(e) {
	MapPlanSort.over(this, e);
});

document.delegateEventListener('dragover', '.plan-schema-plots', function(e) {
	if(MapPlanSort.card !== null && MapPlanSort.card.parentElement === this) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
	}
});

document.delegateEventListener('drop', '.plan-schema-plots', function(e) {
	e.preventDefault();
});

document.addEventListener('dragend', () => MapPlanSort.end());

document.addEventListener('pointermove', e => MapPlan.moveDrag(e));
document.addEventListener('pointerup', () => MapPlan.endDrag());
document.addEventListener('pointercancel', () => MapPlan.endDrag());

document.addEventListener('touchmove', e => {
	if(MapPlan.drag !== null && MapPlan.drag.armed) {
		e.preventDefault();
	}
}, {passive: false});

document.delegateEventListener('contextmenu', '.plan-schema-bed[onclick]', function(e) {
	e.preventDefault();
});

document.delegateEventListener('change', '.plan-console-bed input', function() {
	MapPlan.changeConsoleBeds(this.form);
});

document.delegateEventListener('change', '[data-console-all]', function() {
	MapPlan.changeConsoleAll(this);
});

window.addEventListener('resize', () => MapPlan.refreshMapView());

document.addEventListener('keydown', function(e) {

	if(e.key === 'Escape' && qs('#plan-batch:not(.hide)') !== null) {
		MapPlan.clearSelection();
	}

});

