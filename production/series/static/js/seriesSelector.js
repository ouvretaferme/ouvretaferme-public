class SeriesSelector {

	static mode = 'all';

	static restoreFilter() {

		qs('#series-selector-filter').value = this.mode;
		this.filter();

	}

	static filter() {

		this.mode = qs('#series-selector-filter').value;

		qsa('#series-selector-list .series-selector-cultivation', cultivation => {

			const value = parseInt(cultivation.qs('.series-selector-value').innerHTML);
			const target = cultivation.qs('.series-selector-place').dataset.valueTarget;
			const closed = (cultivation.dataset.status === 'closed');

			let show;

			switch(this.mode) {

				case 'zero' :
					show = (closed === false && value === 0);
					break;

				case 'gap' :
					show = (closed === false && target !== '' && value < parseInt(target));
					break;

				default :
					show = true;

			}

			if(show) {
				cultivation.removeHide();
			} else {
				cultivation.hide();
			}

		});

	}

	static edit(cultivation) {

		SeriesScroll.rememberAll();
		SeriesHelper.learn();
		SeriesPlace.setSwitching(true);

		return new Ajax.Query()
			.url('/series/place:update?cultivation='+ cultivation +'&view='+ (qs('.garden-plot') === null ? 'plan' : 'garden') + SeriesPlace.filters() + SeriesUngroup.param() + SeriesPlace.scope())
			.method('get')
			.fetch()
			.then(() => {

				document.body.classList.add('bed-updating');

			})
			.finally(() => SeriesPlace.setSwitching(false));

	}

	static select(cultivation) {

		const target = qs('#series-selector-'+ cultivation);

		if(
			target === null ||
			target.classList.contains('selected')
		) {
			return;
		}

		// Retenir une série, c'est vouloir l'assoler : le mode s'ouvre avec la fiche,
		// il n'y a plus de bouton pour le demander.
		this.edit(cultivation);

	}

	static markSeries(series) {

		qsa('#series-selector-list .series-selector-cultivation.selected', node => node.classList.remove('selected'));
		qsa('#series-selector-list .series-selector-cultivation[data-series="'+ series +'"]', node => node.classList.add('selected'));

		qsa('#series-selector-list .series-selector-cultivation', node => {

			if(node.dataset.series !== String(series)) {
				node.qs('.place-context', fiche => fiche.remove());
			}

		});

	}

	static reveal(series) {

		this.markSeries(series);

		const card = qs('#series-selector-list .series-selector-cultivation[data-series="'+ series +'"]');

		if(card === null) {
			return;
		}

		const list = card.closest('#series-selector');

		if(list === null || list.scrollHeight <= list.clientHeight) {
			return;
		}

		const box = list.getBoundingClientRect();
		const rect = card.getBoundingClientRect();

		if(rect.top >= box.top && rect.bottom <= box.bottom) {
			return;
		}

		list.scrollTop += (rect.top - box.top) - (box.height - rect.height) / 2;

	}

	static deselect() {

		const view = SeriesPlace.holdView();

		SeriesResize.clearSelection();

		qsa('#zone-content .bed-item-grid.selected, #zone-content-garden .garden-col.selected', node => node.classList.remove('selected'));
		qs("#series-selector-list .series-selector-cultivation.selected", node => node.classList.remove('selected'));
		qsa('#zone-selector .zone-count, #zone-selector .plot-count', node => node.innerHTML = '');
		qsa('#series-selector-list .series-selector-dates', node => node.remove());
		SeriesPanel.series = null;
		SeriesPanel.bed = null;
		qsa('.place-garden-block-placeholder, .place-grid-series-timeline-placeholder, .place-pose', node => node.remove());

		if(typeof SeriesPlacement !== 'undefined') {
			SeriesPlacement.clear();
		}

		qs('#zone-container', node => node.removeAttribute('data-series'));

		qsa('.place-context', node => node.remove());
		qsa('#place-search [name="series"]', node => node.remove());

		document.body.classList.remove('bed-updating');

		qs('#place-pose-bar', node => node.remove());
		document.body.style.removeProperty('--place-pose-height');

		SeriesPlace.paintTokensArmed();

		SeriesPlace.clearSearch();

		SeriesPlace.restoreView(view);

	}

	static refreshCount() {

		let toPlace = 0;

		qsa('#series-selector-list .series-selector-cultivation:not(.series-selector-closed)', node => {

			const target = node.qs('.series-selector-place')?.dataset.valueTarget;

			if(target === '' || target === undefined) {
				return;
			}

			if(parseInt(node.qs('.series-selector-value').innerHTML || 0) < parseInt(target)) {
				toPlace++;
			}

		});

		qs('.series-selector-count', node => node.innerHTML = toPlace);

	}

	static storage = 'series-selector-closed';

	// Une classe sur body plutot que body:has(#series-selector:not(.hide)) en CSS :
	// Firefox reevaluait ce :has a chaque changement de classe hide n'importe ou
	// dans la page, en rebalayant tout le document. Mesure le 04/09/2026 sur gaec
	// du goupil : 6 ms par planche basculee, 3,1 s pour les 502 d'une selection.
	static syncShown() {

		// Au chargement complet, le script s'evalue dans head : pas encore de body.
		if(document.body === null) {
			return;
		}

		// Deux classes tenues ici plutot qu'un body:has(#series-selector) en CSS : un
		// :has ancre sur body se reevalue a chaque insertion dans la page.
		document.body.classList.toggle('series-selector-present', qs('#series-selector') !== null);
		document.body.classList.toggle('series-selector-shown', qs('#series-selector:not(.hide)') !== null);

	}

	static restore() {

		try {

			if(localStorage.getItem(this.storage) === '1') {

				qs('#series-selector', node => node.hide());

			}

		} catch(e) {
		}

		this.syncShown();

	}

	static show() {

		const node = qs('#series-selector');

		if(node === null) {
			return;
		}

		// Le contenu du bac n'est pas dans la page : il arrive au premier clic.
		if(node.dataset.structure !== undefined) {

			const url = '/series/selectorAi:index?id='+ node.dataset.structure +'&season='+ node.dataset.season;

			node.removeAttribute('data-structure');

			return new Ajax.Query()
				.url(url)
				.method('get')
				.fetch()
				.then(() => this.show());

		}

		try {
			localStorage.removeItem(this.storage);
		} catch(e) {
		}

		qs('#series-selector').removeHide();

		this.syncShown();

	}

	// Le clic sur une planche ouvre la fiche ET le mode Assoler, en une seule
	// requete : `place:update` redessine la carte et le plan. Rend FALSE quand il
	// n'y a pas de bac, l'appelant se rabat alors sur le panneau seul.
	static focusSeries(series, block = null) {

		const node = qs('#series-selector');
		const button = qs('#series-selector-button');

		// Le bac est utilisable s'il est deja ouvert, ou si son bouton est offert.
		// Tester le seul bouton echouait des l'ouverture : le CSS le masque alors.
		const usable = (
			node !== null &&
			(
				node.classList.contains('hide') === false ||
				(button !== null && button.offsetParent !== null)
			)
		);

		// Sans bac — sur petit écran — le mode s'ouvre quand même, depuis la série.
		// Le panneau d'assolement d'une série, lui, est déjà ce mode.
		if(usable === false) {

			if(qs('#place-update') === null) {
				this.placeInline(series, block ?? qs('.place-selected'));
			}

			return true;

		}

		const held = block ?? qs('.place-selected');

		const place = () => {

			const card = qs('#series-selector-list .series-selector-cultivation[data-series="'+ series +'"]');

			if(card === null) {
				return;
			}

			// Le plan est redessine : sans ce repere la planche qu'on vient de
			// designer perdrait sa selection au retour.
			SeriesResize.keep = SeriesResize.getKeep(held);

			const done = this.edit(card.id.replace('series-selector-', ''));

			if(done instanceof Promise) {

				done.then(() => {

					SeriesResize.restoreSelection(series);

					// La liste est longue : la carte de la serie designee vient sous
					// les yeux, sinon on ne sait pas ce qu'on vient d'ouvrir.
					this.reveal(series);

				});

			}

		};

		const shown = this.show();

		if(shown instanceof Promise) {
			shown.then(place);
		} else {
			place();
		}

		return true;

	}

	static placeInline(series, held) {

		SeriesScroll.rememberAll();
		SeriesHelper.learn();
		SeriesPlace.setSwitching(true);

		SeriesResize.keep = SeriesResize.getKeep(held);

		new Ajax.Query()
			.url('/series/place:update?series='+ series
				+ '&inline=1'
				+ '&view='+ (qs('.garden-plot') === null ? 'plan' : 'garden')
				+ SeriesPlace.filters()
				+ SeriesUngroup.param()
				+ SeriesPlace.scope())
			.method('get')
			.fetch()
			.then(() => {

				document.body.classList.add('bed-updating');

				SeriesResize.restoreSelection(series);

			})
			.finally(() => SeriesPlace.setSwitching(false));

	}

	static close() {

		try {
			localStorage.setItem(this.storage, '1');
		} catch(e) {
		}

		this.deselect();

		qs('#series-selector').hide();

		this.syncShown();

	}

	static update(target) {

		new Ajax.Query()
			.url(qs('#contact-export-link').getAttribute('data-ajax'))
			.method('get')
			.fetch();

	}

}
document.addEventListener('navigation.wakeup', () => SeriesSelector.restore());
SeriesSelector.restore();
