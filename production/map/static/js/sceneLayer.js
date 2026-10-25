/**
 * La carte des calques du décor, posée sur la scène sous le bouton « Calques », en lecture comme en pose. Une ligne par calque :
 * son interrupteur le montre ou le masque, et la toucher en fait le calque actif, celui où l'on pose et sélectionne ; rendre actif
 * un calque masqué l'affiche. Le calque de base « Décor » n'a pas de ligne en base (`layer` nul) et ne se renomme ni ne se supprime.
 * Les lignes « Icônes des séries » et « Parcelles et jardins », sous la sienne, montrent ou masquent les pastilles des
 * cultures et les contours au sol, et ne sont jamais le calque actif.
 *
 * Ce qu'on masque est propre à chacun : il se garde dans le navigateur, sous une clé qui porte la structure et l'utilisateur, et
 * un calque neuf est affiché. Créer, renommer et supprimer partent aussitôt, hors de « Annuler » de la pose.
 *
 * Un nom de calque est saisi par l'utilisateur : il n'entre dans la page que par `textContent`.
 */
class MapSceneLayer {

	// Le calque de base, dans l'ensemble des calques masqués et comme calque actif
	static BASE = 0;

	/**
	 * Les calques du plan, tous affichés : c'est ce que voit une scène qui dessine le décor sans sa carte. `attach()` y ajoute la
	 * carte et ce que l'utilisateur a masqué.
	 */
	constructor({ plan }) {

		this.layers = plan.layers.map(({ id, name, type }) => ({ id, name, type }));
		this.active = MapSceneLayer.BASE;
		this.hidden = new Set();
		this.watch = null;
		this.resize = null;

	}

	/**
	 * La carte, dans le conteneur de la scène. `changed()` dit que le calque actif ou un affichage a changé, `removed(id)` retire de
	 * la scène les éléments d'un calque supprimé.
	 */
	attach({ container, changed, removed }) {

		this.card = container.qs('[data-scene-layers-card]');
		this.button = container.qs('[data-scene-layers]');
		this.changed = changed;
		this.removed = removed;

		// Sans décor, la carte n'a que les lignes des séries, des limites et du relief, que `MapScene` tient ; celle du relief n'est là
		// que si la scène en reçoit un
		this.template = this.card.qs('[data-scene-layers-row]');
		this.series = this.card.qs('[data-scene-series]');
		this.limits = this.card.qs('[data-scene-limits]');
		this.relief = this.card.qsa('[data-scene-relief]');

		this.texts = JSON.parse(this.card.dataset.texts);
		this.key = 'map-scene-layers-hidden-'+ this.card.dataset.structure +'-'+ this.card.dataset.user;
		this.hidden = new Set(this.readHidden().filter(id => id === MapSceneLayer.BASE || this.layers.some(layer => layer.id === id)));

		this.bind();
		this.render();

		// La carte et la fiche d'une planche se posent au même endroit : celle qui s'ouvre ferme l'autre
		this.sheet = container.qs('[data-scene-sheet]');
		this.watch = new MutationObserver(() => {
			if(this.sheet.classList.contains('hide') === false) {
				this.close();
			}
		});
		this.watch.observe(this.sheet, { attributes: true, attributeFilter: ['class'] });

		// Sous 768 px, la carte prend la place de la frise sous la scène, et la feuille de la pose se pose au-dessus d'elle
		this.resize = new ResizeObserver(([entry]) => container.style.setProperty('--map-scene-layers-height', entry.borderBoxSize[0].blockSize +'px'));
		this.resize.observe(this.card);

	}

	dispose() {
		this.watch?.disconnect();
		this.resize?.disconnect();
	}

	readHidden() {

		try {
			const stored = JSON.parse(localStorage.getItem(this.key) ?? '[]');
			return Array.isArray(stored) ? stored.filter(Number.isInteger) : [];
		} catch {
			return [];
		}

	}

	writeHidden() {

		try {
			localStorage.setItem(this.key, JSON.stringify([...this.hidden]));
		} catch {
			// Sans stockage (navigation privée, données bloquées), le masquage vaut pour la séance
		}

	}

	/**
	 * Le calque d'un élément du décor. Un élément dont le calque a disparu, posé pendant que son calque était supprimé ailleurs,
	 * revient au calque de base plutôt que de disparaitre.
	 */
	of(layer) {
		return (layer !== null && this.layers.some(item => item.id === layer)) ? layer : MapSceneLayer.BASE;
	}

	isShown(id) {
		return this.hidden.has(id) === false;
	}

	typeOf(id) {
		return this.layers.find(layer => layer.id === id)?.type ?? null;
	}

	nameOf(id) {
		return (id === MapSceneLayer.BASE) ? this.texts.base : (this.layers.find(layer => layer.id === id)?.name ?? '');
	}

	// Ce qu'on poste d'un élément posé : le calque de base est l'absence de calque
	value(id) {
		return (id === MapSceneLayer.BASE) ? '' : id;
	}

	bind() {

		this.button.addEventListener('click', () => this.toggle());

		// Sans le droit de gérer la structure, la carte n'a ni ajout, ni menu, ni formulaire
		this.card.qs('[data-scene-layers-new]')?.addEventListener('click', () => this.showForm('create'));

		this.card.qs('[data-scene-layers-rows]').addEventListener('click', event => {

			// La roue ouvre son menu sans rendre la ligne active
			if(event.target.closest('[data-dropdown]') !== null) {
				return;
			}

			const action = event.target.closest('[data-do]');

			if(action === null) {
				return;
			}

			const id = Number(action.closest('[data-layer]').dataset.layer);

			switch(action.dataset.do) {

				case 'pick' :
					this.pick(id);
					break;

				case 'switch' :
					this.switch(id);
					break;

			}

		});

		this.card.qsa('[data-scene-layers-form]', form => {

			form.qs('[data-do="cancel"]').addEventListener('click', () => this.showList());
			form.addEventListener('submit', event => {
				event.preventDefault();
				this.submit(form);
			});

		});

	}

	toggle() {

		const open = this.card.classList.toggle('hide') === false;

		this.button.classList.toggle('btn-selected', open);

		if(open) {
			if(this.sheet.classList.contains('hide') === false) {
				this.sheet.qs('[data-scene-sheet-close]').click();
			}

			this.showList();
		}

	}

	close() {

		this.card.classList.add('hide');
		this.button.classList.remove('btn-selected');

	}

	pick(id) {

		if(id === this.active && this.isShown(id)) {
			return;
		}

		this.active = id;

		// Le calque qu'on rend actif s'affiche : on ne pose pas dans ce qu'on ne voit pas
		if(this.hidden.delete(id)) {
			this.writeHidden();
		}

		this.render();
		this.changed();

	}

	switch(id) {

		if(this.hidden.delete(id) === false) {
			this.hidden.add(id);
		}

		this.writeHidden();
		this.render();
		this.changed();

	}

	render() {

		if(this.template === null) {
			return;
		}

		const [base, ...layers] = [{ id: MapSceneLayer.BASE, name: this.texts.base, type: null }, ...this.layers].map(layer => {

			const row = this.template.content.firstElementChild.cloneNode(true);
			const shown = this.isShown(layer.id);

			row.dataset.layer = layer.id;
			row.classList.toggle('selected', layer.id === this.active);
			row.qs('[data-scene-layers-name]').textContent = layer.name;

			const toggle = row.qs('[data-do="switch"]');
			toggle.classList.toggle('field-switch-on', shown);
			toggle.classList.toggle('field-switch-off', shown === false);

			// Le calque de base ne se renomme ni ne se supprime : sa roue ne garde que sa place, et son interrupteur s'aligne sur les autres
			if(layer.id === MapSceneLayer.BASE) {
				row.qs('[data-scene-layers-gear]', gear => {
					gear.nextElementSibling.remove();
					gear.removeAttribute('data-dropdown');
					gear.classList.add('map-scene-layers-gear-away');
				});
			}

			// Le menu ouvert quitte la ligne pour le corps de la page (Lime.Dropdown) : ses entrées portent leur propre écouteur
			row.qsa('.dropdown-list [data-do]', item => item.addEventListener('click', () => this.showForm(item.dataset.do, layer.id)));

			return row;

		});

		// Les lignes des séries, des limites et du relief gardent leur nœud, et l'écouteur que `MapScene` y a posé
		this.card.qs('[data-scene-layers-rows]').replaceChildren(base, this.series, this.limits, ...this.relief, ...layers);

	}

	showList() {

		this.card.qs('[data-scene-layers-list]').classList.remove('hide');
		this.card.qsa('[data-scene-layers-form]', form => form.classList.add('hide'));

	}

	showForm(kind, id = null) {

		const form = this.card.qs('[data-scene-layers-form="'+ kind +'"]');

		form.reset();
		Lime.Alert.hideErrors(form);

		switch(kind) {

			case 'rename' :
				form.qs('[name="id"]').value = id;
				form.qs('[name="name"]').value = this.nameOf(id);
				break;

			case 'delete' :
				form.qs('[name="id"]').value = id;
				form.qs('[data-scene-layers-delete-name]').textContent = this.nameOf(id);
				form.qs('[data-scene-layers-delete-count]').textContent = '';

				// Ce que la suppression emporte se compte en base, avec ce que la scène n'a pas pu monter
				new Ajax.Query(form)
					.url('/map/decorLayer:getDelete?id='+ id)
					.method('get')
					.fetch()
					.then(json => form.qs('[data-scene-layers-delete-count]').textContent = json.decor, () => null);
				break;

		}

		this.card.qs('[data-scene-layers-list]').classList.add('hide');
		this.card.qsa('[data-scene-layers-form]', node => node.classList.toggle('hide', node !== form));

		form.qs('input[name="name"]')?.focus();

	}

	submit(form) {

		const kind = form.dataset.sceneLayersForm;
		const values = new FormData(form);

		const route = {
			create: 'doCreate',
			rename: 'doUpdateName',
			delete: 'doDelete'
		}[kind];

		new Ajax.Query(form)
			.url('/map/decorLayer:'+ route)
			.body(values)
			.fetch()
			.then(json => {

				if(typeof json?.id !== 'number') {
					return;
				}

				switch(kind) {

					case 'create' :
						// On crée un calque pour y poser : il devient le calque actif
						this.layers.push({ id: json.id, name: json.name, type: values.get('type') });
						this.active = json.id;
						break;

					case 'rename' :
						this.layers.find(layer => layer.id === json.id).name = json.name;
						break;

					case 'delete' :
						this.removed(json.id);
						this.layers = this.layers.filter(layer => layer.id !== json.id);

						if(this.hidden.delete(json.id)) {
							this.writeHidden();
						}

						if(this.active === json.id) {
							this.active = MapSceneLayer.BASE;
						}
						break;

				}

				this.showList();
				this.render();
				this.changed();

			}, () => null);

	}

}
