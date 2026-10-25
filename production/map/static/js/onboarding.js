class MapOnboarding extends MainOnboarding {

	static STEP_ZONE = 3;
	static STEP_PLOTS = 4;
	static STEP_BEDS = 5;

	/* La carte de la parcelle s'ouvre sur le lieu à une échelle où une parcelle de plusieurs hectares tient entière */
	static ZOOM_ZONE = 16;

	/* La séance de tracé ouverte, celle de la parcelle à l'étape 3, d'un jardin à l'étape 4 ou d'un rang de planches à l'étape 5 :
	   une seule carte vit à la fois */
	static drawing = null;

	/* Le jardin qu'on édite, dont la carte s'ouvre quand l'étape le montre */
	static plotOpen = null;

	/* Les parcelles PAC que la séance a lues autour de sa vue, reprises quand elle se rouvre (MapDrawingEmbedded.resumePac()) */
	static zonePac = null;

	/* La forme que chaque mode du jardin a laissée, rendue quand on y revient : un clic de trop sur le mode ne perd pas un dessin.
	   Seul le tracé du mode affiché part, et pas s'il est remis à plus tard. Les dimensions d'un abri partent dans les deux modes,
	   le serveur les ignorant en plein champ (Plot::properties()) */
	static plotShapes = {};

	static init() {
		// Une navigation ajax rouvre l'écran sans recharger la classe : le jardin qu'on éditait, les formes et les parcelles PAC
		// lues sont ceux de l'écran quitté
		this.plotOpen = null;
		this.zonePac = null;
		this.plotShapes = {};
		this.resetPlotShape(this.plotRow);
		this.renderPlots();
	}

	static onStep(step) {

		this.root.dataset.mode = this.mode;

		// Le jardin mis de côté par « Passer cette étape » revient tel qu'il était quand on revient à son étape
		if(step === this.STEP_PLOTS) {
			this.plotRow.disabled = false;
		}

		this.refreshZone();
		this.refreshPlots();

		if(step === this.STEP_BEDS) {
			this.renderBeds();
		}

		this.refreshBeds();

	}

	/* Le repère posé à l'étape du lieu, que la carte de la parcelle prend pour centre */
	static get place() {
		const value = this.root.qs('input[name="placeLngLat"]').value;
		return (value === '') ? null : JSON.parse(value);
	}

	static get zoneSection() {
		return this.root.qs('.onboarding-step[data-step="'+ this.STEP_ZONE +'"]');
	}

	static get zoneTrigger() {
		return qs('#onboarding-zone-map [data-drawing]');
	}

	/* Un jardin se trace dans une parcelle tracée, dont le contour part : remise à plus tard ou pas encore refermée, elle n'en a pas */
	static get zoneDrawn() {
		const field = this.form.qs('[name="coordinates"]');
		return (field.disabled === false && field.value !== '');
	}

	/* Le contour de la parcelle se dessine quand le lieu est connu, tant qu'on ne le remet pas à plus tard : la surface se lit alors
	   sur le dessin au lieu de se saisir. Une carte cachée n'a pas de taille, donc la séance s'ouvre à l'arrivée sur l'étape et se
	   libère au départ, la forme et sa surface restant dans les champs qui partent avec le formulaire */
	static refreshZone() {

		const section = this.zoneSection;
		const field = this.form.qs('[name="coordinates"]');
		const later = (section.qs('.map-drawing-switch').classList.contains('field-switch-off'));
		const drawable = (this.place !== null);
		const drawing = (drawable && later === false);

		section.qs('[data-onboarding-drawing]').classList.toggle('hide', drawable === false);
		section.qsa('[data-onboarding-help]', node => node.classList.toggle('hide', node.dataset.onboardingHelp !== (drawing ? 'now' : 'later')));
		section.qsa('[data-onboarding-area="later"]', node => node.classList.toggle('hide', drawing));
		section.qs('input[name="area"]:not([type="hidden"])').disabled = drawing;

		qs('#onboarding-zone-map').classList.toggle('hide', drawing === false);

		// Un repère retiré depuis le dessin laisse une forme qui ne doit plus partir
		if(drawable === false) {
			field.disabled = true;
			section.qs('input[type="hidden"][name="area"]').disabled = true;
		}

		this.refreshDrawing();

		MapDrawingEmbedded.refreshSurface(this.form);

	}

	/* Le tracé qui a la carte : celui de la parcelle sur l'étape 3, celui du jardin qu'on édite sur l'étape 4, celui du rang de la
	   première ligne de planches dont l'emplacement est tracé sur l'étape 5, la seule tant que le démarrage ne crée qu'un emplacement. Une
	   carte cachée n'a pas de taille, donc la séance s'ouvre à l'arrivée sur l'étape et se libère au départ, la forme et sa surface
	   restant dans les champs qui partent avec le formulaire */
	static refreshDrawing() {

		// Une navigation qui quitte l'écran libère la séance sans repasser par ici (`navigation.sleep`)
		if(this.drawing !== MapDrawing.current) {
			this.drawing = null;
		}

		let trigger = null;

		if(this.step === this.STEP_ZONE) {

			if(this.place !== null && this.isLater(this.zoneSection) === false) {
				trigger = this.zoneTrigger;
			}

		} else if(this.step === this.STEP_PLOTS) {

			if(this.plotOpen !== null && this.zoneDrawn && this.isLater(this.plotOpen) === false) {
				trigger = this.plotOpen.qs('[data-drawing]');
			}

		} else if(this.step === this.STEP_BEDS) {

			const row = Array.from(qs('#onboarding-beds').qsa('.onboarding-bed')).find(row => this.getBedSupport(row) !== null && this.isLater(row) === false);

			if(row !== undefined) {
				trigger = row.qs('[data-drawing]');
			}

		}

		if(this.drawing?.trigger === trigger) {
			return;
		}

		if(this.drawing !== null) {
			this.stopDrawing();
		}

		if(trigger === this.zoneTrigger) {
			this.startZoneDrawing();
		} else if(trigger?.dataset.drawing === 'line') {
			this.startBedDrawing(trigger);
		} else if(trigger !== null) {
			this.startPlotDrawing(trigger);
		}

	}

	static isLater(scope) {
		return scope.qs('.map-drawing-switch').classList.contains('field-switch-off');
	}

	static startZoneDrawing() {

		const trigger = this.zoneTrigger;

		this.drawing = MapDrawingEmbedded.start(trigger, () => new MapCartography(trigger.dataset.drawingMap, this.season, false, true, {center: this.place, zoom: this.ZOOM_ZONE}));

		// Un contour repris se montre, même resté loin d'un lieu changé depuis : il partirait sinon sans qu'on le voie
		if('drawingPacYear' in trigger.dataset) {
			this.drawing.resumePac(parseInt(trigger.dataset.drawingPacYear), this.zonePac);
		} else {
			this.drawing.frameShape();
		}

	}

	/* Le jardin se trace dans le contour de la parcelle, qui l'aimante (`drawingContour`) et se montre autour, la parcelle pouvant
	   avoir été retracée depuis la dernière ouverture. La carte se cadre sur la parcelle, jamais sur le jardin : il y est tenu, et
	   un abri de quelques mètres cadré seul ne montre plus rien autour de lui */
	static startPlotDrawing(trigger) {

		const coordinates = this.form.qs('[name="coordinates"]').value;
		const points = JSON.parse(coordinates);
		const ring = [...points, points[0]];

		trigger.dataset.drawingContour = coordinates;

		this.drawing = MapDrawingEmbedded.start(trigger, () => new MapCartography(trigger.dataset.drawingMap, this.season, false, true, {center: this.place, zoom: 17})
			.addZone(0, this.getZone().name, ring)
			.fitCoordinatesBounds(ring, {duration: 0}));

	}

	/* Le rang se pose dans le contour de son emplacement, que la carte cadre et montre dans sa parcelle. Un rang qui n'est plus
	   dans le contour qu'il a reçu, l'emplacement retracé depuis, repart d'une pose d'office */
	static startBedDrawing(trigger) {

		const row = trigger.closest('.onboarding-bed');
		const support = this.getBedSupport(row);
		const zone = JSON.parse(this.form.qs('[name="coordinates"]').value);
		const ring = [...support, support[0]];

		// Le contour d'un rang est un anneau refermé, comme celui que la Carte donne au lien (Bed.u.php)
		if(trigger.dataset.drawingContour !== JSON.stringify(ring)) {
			trigger.dataset.drawingContour = JSON.stringify(ring);
			trigger.dataset.drawingCoordinates = 'null';
		}

		this.drawing = MapDrawingEmbedded.start(trigger, () => {

			const carto = new MapCartography(trigger.dataset.drawingMap, this.season, false, true, {center: this.place, zoom: 17})
				.addZone(0, this.getZone().name, [...zone, zone[0]]);

			if(row.dataset.index !== '0') {
				const plot = this.getPlots().find(plot => plot.index === parseInt(row.dataset.index));
				carto.addPlot(0, plot.name, 0, ring, true, plot.mode);
			}

			return carto.fitCoordinatesBounds(ring, {duration: 0});

		});

	}

	/* Le contour où se posent les planches d'une ligne : celui de son jardin ou de son abri quand il part tracé, celui de la
	   parcelle quand elles vont directement dessus, aucun sinon */
	static getBedSupport(row) {

		const index = row.dataset.index;
		const field = (index === '0') ?
			this.form.qs('[name="coordinates"]') :
			this.plotRow.qs('input[type="hidden"][name="plotCoordinates['+ index +']"]');

		if(field === null || field.disabled || field.value === '') {
			return null;
		}

		return JSON.parse(field.value);

	}

	/* Rouverte, la séance reprend la forme laissée : rectangle ou contour libre, et un contour déclaré à la PAC le reste ; un rang
	   reprend son cadre, ses planches suivant le formulaire */
	static stopDrawing() {

		const session = this.drawing;
		const trigger = session.trigger;

		if(session.shape instanceof MapDrawingLine) {
			trigger.dataset.drawingCoordinates = session.shape.closed ? JSON.stringify(session.shape.points) : 'null';
		} else {
			trigger.dataset.drawing = (session.shape instanceof MapDrawingRectangle) ? 'rectangle' : 'polygon';
			trigger.dataset.drawingCoordinates = (session.field.value === '') ? 'null' : session.field.value;
		}

		if(session.pacYear === null) {
			delete trigger.dataset.drawingPacYear;
		} else {
			trigger.dataset.drawingPacYear = session.pacYear;
		}

		if(trigger === this.zoneTrigger) {
			this.zonePac = session.pac;
		}

		session.dispose();

		this.drawing = null;

	}

	static get season() {
		return this.form.qs('[name="season"]').value;
	}

	/* L'interrupteur de l'étape : la carte et le contour suivent la règle commune, l'aide et la surface celle du parcours */
	static changeDrawing(input) {

		MapDrawingEmbedded.changeLater(input);

		this.refreshZone();

	}

	static next() {

		if(this.step === this.STEP_ZONE && this.checkZone() === false) {
			return;
		}

		if(this.step === this.STEP_PLOTS && this.checkPlot() === false) {
			return;
		}

		super.next();

	}

	static checkZone() {

		const section = this.root.qs('.onboarding-step[data-step="'+ this.STEP_ZONE +'"]');
		const area = section.qs('input[name="area"]:not([disabled])');
		const errors = [];

		// Une surface lue sur le dessin manque quand le contour n'est pas refermé : c'est lui qu'il faut reprendre
		if(area === null) {
			const map = qs('#onboarding-zone-map');
			errors.push(['Zone::coordinates.check', map.dataset.error, 'coordinates']);
		} else if(area.value === '' || area.checkValidity() === false) {
			errors.push(['Zone::area.check', area.dataset.error, 'area']);
		}

		Lime.Alert.showErrors(section, errors);

		return (errors.length === 0);

	}

	/* Le jardin refuse ce que son mode refuse au serveur : sous abri, les deux dimensions, que le tracé écrit quand il y en a un ;
	   en plein champ, une surface saisie hors des bornes du champ, une surface vide se déduisant des planches de l'étape suivante
	   (OnboardingLib::guessArea()). Le tracé refuse le reste */
	static checkPlot() {

		const section = this.root.qs('.onboarding-step[data-step="'+ this.STEP_PLOTS +'"]');
		const errors = MapDrawingEmbedded.getDrawingErrors(this.form);

		const fields = (this.plotRow.dataset.plotMode === 'greenhouse') ?
			[['plotLength', 'Plot::length.required', true], ['plotWidth', 'Plot::width.required', true]] :
			[['plotArea', 'Plot::area.check', false]];

		fields.forEach(([property, errorName, required]) => {

			const name = property +'['+ this.plotRow.dataset.index +']';
			const input = this.plotRow.qs('input[name="'+ name +'"]:not([type="hidden"]):not([disabled])');

			if(input === null) {
				return;
			}

			if((required && input.value === '') || input.checkValidity() === false) {
				errors.push([errorName, input.dataset.error, name]);
			}

		});

		Lime.Alert.showErrors(section, errors);

		return (errors.length === 0);

	}

	static get mode() {
		const input = this.root.qs('input[name="mode"]:checked');
		return input ? input.value : 'permanent';
	}

	static get withBeds() {
		return (this.mode === 'permanent');
	}

	static get lastStep() {
		return this.withBeds ? this.STEP_BEDS : this.STEP_PLOTS;
	}

	static get plotRow() {
		return qs('#onboarding-plot');
	}

	/* Le jardin se met de côté sans se perdre : son fieldset désactivé ne poste rien, et les planches vont sur la parcelle. À la
	   dernière étape, celle des cultures temporaires, passer crée le plan : l'envoi lit le formulaire pendant le clic
	   (submitAjaxForm()), et le jardin se rend aussitôt, pour qu'un refus laisse l'étape telle qu'on l'a quittée */
	static skip() {

		this.plotRow.disabled = true;

		if(this.step === this.lastStep) {
			this.root.qs('.onboarding-step[data-step="'+ this.step +'"] [data-onboarding-submit]').click();
			this.plotRow.disabled = false;
		} else {
			super.next();
		}

	}

	/* Un jardin se trace en contour, libre ou rectangle, et donne sa surface ; un abri se trace en rectangle et donne ses dimensions.
	   Le mode qui change reprend la forme qu'il avait laissée, vide la première fois. La séance s'arrête d'abord, pour que la forme
	   du mode qu'on quitte soit écrite sur le lien */
	static setPlotMode(row, mode) {

		if(this.drawing?.trigger === row.qs('[data-drawing]')) {
			this.stopDrawing();
		}

		this.plotShapes[row.dataset.plotMode] = this.getPlotShape(row);

		row.dataset.plotMode = mode;

		this.resetPlotShape(row, this.plotShapes[mode] ?? null);

		this.renderPlots();
		this.refreshPlots();

	}

	/* La forme d'un jardin tient au lien, qui la rouvre, et aux champs cachés de son contour et de sa surface, qui la font partir.
	   Les dimensions d'un abri sont dans ses propres champs, que l'autre mode cache sans les vider */
	static getPlotShape(row) {

		const index = row.dataset.index;
		const trigger = row.qs('[data-drawing]');
		const field = row.qs('input[type="hidden"][name="plotCoordinates['+ index +']"]');
		const area = row.qs('input[type="hidden"][name="plotArea['+ index +']"]');

		return {
			drawing: trigger.dataset.drawing,
			coordinates: trigger.dataset.drawingCoordinates,
			value: field.value,
			disabled: field.disabled,
			area: area.value,
			areaDisabled: area.disabled
		};

	}

	static resetPlotShape(row, shape = null) {

		const index = row.dataset.index;
		const trigger = row.qs('[data-drawing]');
		const field = row.qs('input[type="hidden"][name="plotCoordinates['+ index +']"]');
		const area = row.qs('input[type="hidden"][name="plotArea['+ index +']"]');

		['drawingRectangle', 'drawingFree', 'drawingAreaField', 'drawingDimensions'].forEach(key => delete trigger.dataset[key]);

		trigger.dataset.drawingCoordinates = 'null';

		if(row.dataset.plotMode === 'greenhouse') {
			trigger.dataset.drawing = 'rectangle';
			trigger.dataset.drawingDimensions = '';
		} else {
			trigger.dataset.drawing = 'polygon';
			trigger.dataset.drawingRectangle = '';
			trigger.dataset.drawingFree = '';
			trigger.dataset.drawingAreaField = 'plotArea['+ index +']';
		}

		if(shape === null) {
			field.value = '';
			field.disabled = true;
			area.disabled = true;
		} else {
			// Un jardin a pu se tracer en rectangle, que le lien doit rouvrir comme tel
			trigger.dataset.drawing = shape.drawing;
			trigger.dataset.drawingCoordinates = shape.coordinates;
			field.value = shape.value;
			area.value = shape.area;
			// L'interrupteur est commun aux deux modes : remis à plus tard dans l'autre, le tracé rendu ne part pas
			field.disabled = (shape.disabled || this.isLater(row));
			area.disabled = (shape.areaDisabled || this.isLater(row));
		}

	}

	/* L'interrupteur du jardin : la carte et le contour suivent la règle commune. Remis à plus tard, le jardin se replie ; rendu au
	   dessin, il devient celui qu'on édite */
	static changePlotDrawing(input) {

		const row = input.closest('.onboarding-plot');

		MapDrawingEmbedded.changeLater(input);

		if(this.isLater(row) === false) {
			this.plotOpen = row;
		}

		this.refreshPlots();

	}

	static editPlot(row) {

		this.plotOpen = row;

		this.refreshPlots();

	}

	/* L'interrupteur d'une ligne de planches : le rang suit la règle commune, et seule la ligne dessinée maintenant a la carte */
	static changeBedDrawing(input) {

		MapDrawingEmbedded.changeLater(input);

		this.refreshBeds();

	}

	/* Une ligne de planches offre le rang quand son emplacement est tracé. Sans lui, ou remis à plus tard, le rang ne part pas et les
	   planches se créent à dessiner */
	static refreshBeds() {

		qs('#onboarding-beds').qsa('.onboarding-bed', row => {

			const drawable = (this.getBedSupport(row) !== null);

			row.qs('[data-onboarding-bed-drawing]').classList.toggle('hide', drawable === false);
			row.qs('[data-drawing-block]').classList.toggle('hide', drawable === false || this.isLater(row));

			if(drawable === false) {
				row.qs('input[type="hidden"][name^="bedDraw["]').disabled = true;
			}

		});

		this.refreshDrawing();

	}

	/* Seul un jardin tracé se replie : à dessiner maintenant et sans tracé, sa carte s'ouvre. C'est le cas d'un jardin saisi avant que
	   la parcelle soit tracée, qui sinon perdrait sa surface sans pouvoir en recevoir une */
	static refreshPlots() {

		const drawable = this.zoneDrawn;
		const row = this.plotRow;

		if(drawable && this.plotOpen === null && this.isLater(row) === false && row.qs('input[type="hidden"][name^="plotCoordinates["]').value === '') {
			this.plotOpen = row;
		}

		this.refreshPlot(row, drawable);

		this.refreshDrawing();

	}

	/* Le jardin offre le tracé quand la parcelle est tracée. Dessiné maintenant, il prend sa surface ou ses dimensions du tracé : la
	   surface saisie se désactive en montrant celle du dessin, et un jardin replié garde ses dimensions en lecture seule jusqu'à
	   « Modifier le tracé » */
	static refreshPlot(row, drawable) {

		const index = row.dataset.index;
		const later = this.isLater(row);
		const drawing = (drawable && later === false);
		const open = (drawing && row === this.plotOpen);
		const field = row.qs('input[type="hidden"][name="plotCoordinates['+ index +']"]');
		const block = row.qs('[data-drawing-block]');

		// Une parcelle remise à plus tard depuis laisse une forme qui ne doit plus partir
		if(drawable === false) {
			field.disabled = true;
			row.qs('input[type="hidden"][name="plotArea['+ index +']"]').disabled = true;
		}

		const drawn = (drawing && (open || field.disabled === false));

		row.qs('[data-onboarding-plot-drawing]').classList.toggle('hide', drawable === false);
		block.classList.toggle('hide', open === false);
		row.qs('[data-onboarding-plot-edit]').classList.toggle('hide', drawing === false || open);

		const area = row.qs('input:not([type="hidden"])[name="plotArea['+ index +']"]');
		area.disabled = drawing;

		if(drawing) {
			area.value = row.qs('input[type="hidden"][name="plotArea['+ index +']"]').value;
		}

		row.qsa('[data-drawing-dimension]', node => node.readOnly = (drawn && open === false));

	}

	static getDefaultPlotName(row, mode, rank) {

		const name = row.qs('input[name^="plotName["]');
		const model = (mode === 'greenhouse') ? name.dataset.defaultGreenhouse : name.dataset.defaultOpenField;

		return model.replace('__rank__', rank);

	}

	/* Le nom proposé suit le mode, « Jardin 1 » ou « Tunnel 1 » */
	static renderPlots() {

		const row = this.plotRow;

		row.qs('input[name^="plotName["]').placeholder = this.texts.example.replace('__value__', this.getDefaultPlotName(row, row.dataset.plotMode, 1));

	}

	static getZone() {

		return {
			name: this.root.qs('input[name="name"]').value.trim(),
			area: parseInt(this.root.qs('input[name="area"]:not([disabled])')?.value)
		};

	}

	/* Mis de côté par « Passer cette étape », le jardin ne part pas : les planches vont sur la parcelle */
	static getPlots() {

		const row = this.plotRow;

		if(row.disabled) {
			return [];
		}

		const index = parseInt(row.dataset.index);

		return [{
			index: index,
			mode: row.dataset.plotMode,
			name: row.qs('input[name="plotName['+ index +']"]').value.trim(),
			area: parseInt(row.qs('input[name="plotArea['+ index +']"]').value),
			length: parseFloat(row.qs('input[name="plotLength['+ index +']"]').value),
			width: parseFloat(row.qs('input[name="plotWidth['+ index +']"]').value)
		}];

	}

	static getHolders() {

		const plots = this.getPlots();

		if(plots.length > 0) {
			return plots;
		}

		const zone = this.getZone();

		return [{
			index: 0,
			direct: true,
			mode: 'open-field',
			name: zone.name,
			area: zone.area
		}];

	}

	static renderBeds() {

		const container = qs('#onboarding-beds');
		const holders = this.getHolders();
		const indexes = holders.map(holder => holder.index);

		container.qsa('.onboarding-bed', row => {
			if(indexes.includes(parseInt(row.dataset.index)) === false) {
				row.remove();
			}
		});

		let reference = null;

		holders.forEach(holder => {

			let row = container.qs('.onboarding-bed[data-index="'+ holder.index +'"]');

			if(row === null) {

				container.insertAdjacentHTML('beforeend', qs('#onboarding-bed-template').innerHTML.replaceAll('__i__', holder.index));
				row = container.qs('.onboarding-bed[data-index="'+ holder.index +'"]');

				// Le rang ne part que posé : la séance active son champ quand il l'est (MapDrawingEmbedded.refresh())
				row.qs('input[type="hidden"][name="bedDraw['+ holder.index +']"]').disabled = true;

				if(reference !== null) {
					this.copyFormat(reference, row);
				}

			}

			if(reference === null) {
				reference = row;
			}

			row.dataset.plotMode = holder.mode;

			let label;

			if(holder.direct) {
				label = container.dataset.labelDirect + (holder.name ? ' « '+ holder.name.encode() +' »' : '');
			} else {
				const kind = (holder.mode === 'greenhouse') ? container.dataset.labelGreenhouse : container.dataset.labelOpenField;
				label = holder.name ? holder.name.encode() +'<small>'+ kind +'</small>' : kind;
			}

			row.qs('.onboarding-bed-label').innerHTML = label;

		});

	}

	static copyFormat(from, to) {

		['bedLength', 'bedWidth', 'bedAlleyWidth'].forEach(name => {
			to.qs('input[name^="'+ name +'["]').value = from.qs('input[name^="'+ name +'["]').value;
		});

	}

	static spreadFormat(row) {

		row.dataset.touched = '1';

		qs('#onboarding-beds').qsa('.onboarding-bed', other => {
			if(other !== row && other.dataset.touched !== '1') {
				this.copyFormat(row, other);
			}
		});

	}

}

document.delegateEventListener('click', '#onboarding [data-onboarding-skip]', function(e) {
	MapOnboarding.skip();
});

document.delegateEventListener('change', '#onboarding input[name="mode"]', function(e) {
	MapOnboarding.root.dataset.mode = this.value;
});

document.delegateEventListener('change', '#onboarding input[name^="plotMode["]', function(e) {
	MapOnboarding.setPlotMode(this.closest('.onboarding-plot'), this.value);
});

document.delegateEventListener('click', '#onboarding [data-onboarding-plot-edit]', function(e) {
	MapOnboarding.editPlot(this.closest('.onboarding-plot'));
});

/* La surface d'un jardin tracé se lit dans sa saisie, désactivée tant qu'il se dessine. Remis à plus tard, le tracé vide sa
   surface : la saisie, rendue à la main, part alors de la dernière surface dessinée */
document.delegateEventListener('input', '#onboarding .onboarding-plot input[type="hidden"][name^="plotArea["]', function(e) {

	const row = this.closest('.onboarding-plot');

	if(MapOnboarding.isLater(row) === false) {
		row.qs('input:not([type="hidden"])[name="'+ this.name +'"]').value = this.value;
	}

});

// Le refus d'un contour non refermé tombe dès que le tracé l'est : la surface qu'il donne arrive alors
document.delegateEventListener('input', '#onboarding input[type="hidden"][name="area"]', function(e) {

	if(this.value === '') {
		return;
	}

	const map = qs('#onboarding-zone-map');

	Lime.Alert.hideErrors(map);
	map.classList.remove('form-error-wrapper');

});

/* Le rang posé suit le nombre et le format saisis (MapDrawingLine.getCreated()) */
document.delegateEventListener('input', '#onboarding-beds .onboarding-bed input[name^="bed"]', function(e) {
	MapOnboarding.spreadFormat(this.closest('.onboarding-bed'));
	this.form.dispatchEvent(new CustomEvent('bedsChange', {bubbles: true}));
});

document.delegateEventListener('focus', '#onboarding-beds .onboarding-bed input', function(e) {
	this.select();
});
