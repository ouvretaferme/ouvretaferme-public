document.addEventListener('keyup', function(e) {

	Structure.changeArrowSection(e)
	Structure.closeEscape(e);

});

document.delegateEventListener('click', '[data-action="structure-producer-type"]', function() {

	Structure.chooseProducerType(this);

});

document.addEventListener('navigation.wakeup', function() {

	Structure.scrollStrip();

});

document.delegateEventListener('mouseleave', 'header', function() {

	Structure.leaveSection();

});

// Une entrée qui mène à une page se rend en `a`, un entête sans cible en `div` : le sélecteur
// n'attrape donc que les entêtes. Dans la bande au-dessus de la barre, toucher un groupe ouvre la
// feuille, qui seule montre ses entrées.
document.delegateEventListener('click', '#structure-nav div.structure-tab', function() {

	if(
		this.classList.contains('disabled') === false &&
		document.body.dataset.panel !== 'open' &&
		Structure.hasBar()
	) {
		Structure.openPanel();
	}

});

// Le déclencheur est un lien sans adresse et sans `data-dropdown` : ce délégué charge la liste, puis
// l'ouvre, et `Lime.Dropdown` la referme.
document.delegateEventListener('click', '[data-structure-selector]', function(e) {

	e.preventDefault();

	Structure.openSelector(this);

});

document.delegateEventListener('input', '.structure-selector-search input', function() {

	Structure.filterSelector(this);

});

document.delegateEventListener('keydown', '.structure-selector-search input', function(e) {

	if(e.key === 'Enter') {
		e.preventDefault();
		this.closest('.structure-selector').qs('.dropdown-item-group-items > .dropdown-item:not(.hide)', node => node.click());
	}

});

document.delegateEventListener('dropdownBeforeShow', '[data-dropdown-id="structure-selector"]', function(e) {

	e.detail.list.style.minWidth = '';

});

// Au doigt, la liste passe en plein écran et le clavier en couvrirait la moitié avant qu'on ait vu
// ce qu'elle porte.
//
// La largeur est tenue à celle de l'ouverture : la liste n'a que celle de son contenu, et une recherche
// qui en masque une partie la ferait rétrécir sous le doigt, hors du centre où `Lime.Dropdown` l'a posée.
document.delegateEventListener('dropdownAfterShow', '[data-dropdown-id="structure-selector"]', function(e) {

	e.detail.list.style.minWidth = e.detail.list.getBoundingClientRect().width +'px';

	if(isTouch() === false) {
		e.detail.list.qs('.structure-selector-search input', node => node.focus());
	}

});

class Structure {

	// Les seuils de `template-structure.css`.
	static TABS_COLUMN = '(min-width: 1100px) and (min-height: 600px)';
	static BAR = '(max-width: 767px), (max-height: 599px)';
	static TABLET = '(min-width: 768px) and (max-width: 1099px) and (min-height: 600px)';

	static pendingSection = null;

	// L'instant où le survol a ouvert un univers, et le délai sous lequel un clic qui suit est encore le
	// geste d'ouverture.
	static hoveredAt = -Infinity;
	static HOVER_SETTLE = 400;

	static getId() {

		const a = document.createElement('a');
		a.href = document.location.href;

		let url = a.pathname.split('/')[1];

		return parseInt(url);

	}

	/**
	 * La barre du téléphone est rendue avec les onglets de tout gabarit de structure, et c'est la largeur
	 * qui la montre. Le gabarit se vérifie parce que ce script est aussi chargé hors de lui
	 * (`StructureUi`, `CashUi`), où aucune barre n'est rendue.
	 */
	static hasBar() {

		return (
			document.body.matches('[data-template~="structure"]') &&
			window.matchMedia(this.BAR).matches
		);

	}

	static changeArrowSection(e) {

		if(
			document.activeElement !== document.body ||
			document.body.classList.contains('panel-open')
		) {
			return;
		}

		if(e.key === 'ArrowLeft' || e.key === 'ArrowRight') {

			// La barre du téléphone et non les jetons : elle est rendue à toutes les largeurs, et elle seule
			// porte encore Paramètres parmi les univers.
			const sections = [];
			qsa('.structure-bar .structure-nav-section[data-section]', node => sections[sections.length] = node.dataset.section);

			let position = sections.findIndex(section => document.body.dataset.template.includes('structure-section-'+ section));

			if(position === -1) {
				position = sections.findIndex(section => document.body.dataset.template.includes('structure-'+ section));
			}

			let newPosition;

			switch(e.key) {

				case 'ArrowLeft' :
					newPosition = (position - 1 + sections.length) % sections.length;
					break;

				case 'ArrowRight' :
					newPosition = (position + 1) % sections.length;
					break;

			}

			this.setSection(sections[newPosition]);

		}

	}

	static changeSection(target, event, delay = 0) {

		this.clearSection();

		if(this.hasBar()) {

			// Toucher un univers de la barre ouvre la feuille sur lui, d'un seul geste. Le survol n'y
			// fait rien : il ouvrirait la feuille par-dessus l'écran sous un pointeur qui passe.
			if(event !== 'click') {
				return;
			}

			if(
				document.body.dataset.panel === 'open' &&
				document.body.dataset.section === target.dataset.section
			) {
				this.closePanel();
				return;
			}

			this.setSection(target.dataset.section);
			document.body.dataset.panel = 'open';

			return;

		}

		// À la tablette, toucher ou cliquer l'univers ouvert le referme, comme la croix. Au pointeur, le
		// survol l'a souvent ouvert juste avant : un clic qui suit l'ouverture de moins de
		// `HOVER_SETTLE` voulait l'ouvrir, et ne le referme pas. La colonne ne reste jamais vide : y
		// refermer un univers revient à celui de l'écran, et celui de l'écran ne se referme pas.
		if(
			event === 'click' &&
			document.body.dataset.section === target.dataset.section &&
			performance.now() - this.hoveredAt > this.HOVER_SETTLE
		) {

			if(window.matchMedia(this.TABS_COLUMN).matches) {

				const section = this.getScreenSection();

				if(
					section !== undefined &&
					section !== target.dataset.section
				) {
					this.setSection(section);
				}

			} else {
				this.closeSection();
			}

			return;

		}

		this.pendingSection = setTimeout(() => {

			if(
				event === 'mouseenter' &&
				document.body.dataset.section !== target.dataset.section
			) {
				this.hoveredAt = performance.now();
			}

			this.setSection(target.dataset.section);

			this.pendingSection = null;

		}, delay);

	}

	static leaveSection() {

		// La feuille du téléphone vit dans `<header>` : la quitter au pointeur la viderait de son univers
		// en la laissant ouverte. Elle se referme par sa croix, par son voile et par la barre.
		if(
			window.matchMedia(this.TABS_COLUMN).matches ||
			this.hasBar()
		) {
			return;
		}

		this.clearSection();
		this.closeSection();

	}

	/**
	 * Échap referme le déroulant de la tablette. Un panneau ouvert le prend avant lui
	 * (`Lime.Panel.closeEscape()`).
	 */
	static closeEscape(e) {

		if(
			e.key !== 'Escape' ||
			qs('.panel.open') !== null
		) {
			return;
		}

		this.leaveSection();

	}

	static closeSection() {

		if(document.body.dataset.section === undefined) {
			return false;
		}

		document.body.dataset.template = document.body.dataset.template.replace(' structure-section-' + document.body.dataset.section, '');

		delete document.body.dataset.section;

		this.closeDropdowns();

		return true;

	}

	/**
	 * `lime.js` déplace la liste d'un menu déroulant dans `<body>` : elle survivrait à la feuille qui
	 * l'a ouverte. Avec les onglets, quitter l'en-tête vers sa liste appelle `closeSection()`, qui
	 * fermerait la liste visée.
	 */
	static closeDropdowns() {

		if(this.hasBar()) {
			qsa('body > header [data-dropdown-display]', button => Lime.Dropdown.close(button));
		}

	}

	static setSection(section) {

		if(document.body.dataset.section !== section) {
			this.closeDropdowns();
		}

		document.body.dataset.template = document.body.dataset.template.replace(' structure-section-' + document.body.dataset.section, '');

		document.body.dataset.template += ' structure-section-'+ section;
		document.body.dataset.section = section;

	}

	static clearSection() {

		if(this.pendingSection !== null) {
			clearTimeout(this.pendingSection);
		}

	}

	static openPanel() {

		document.body.dataset.panel = 'open';

		// Sous la barre, un groupe d'onglets reste caché tant que le `<body>` ne porte pas son
		// `structure-section-<x>`. La feuille, elle, s'ouvre aussi par la bande : sans ce jeton, elle
		// ne montrerait que sa tête.
		if(document.body.dataset.section === undefined) {

			const section = this.getScreenSection();

			if(section !== undefined) {
				this.setSection(section);
			}

		}

	}

	/**
	 * Refermer la feuille, c'est quitter : `openPanel()` reposera l'univers de l'écran, donc rien sur
	 * l'accueil.
	 */
	static closePanel() {

		delete document.body.dataset.panel;

		this.closeSection();

	}

	/**
	 * L'univers de l'écran, lu sur le jeton du gabarit comme le fait la navigation au clavier. Il n'y
	 * en a pas toujours un, l'accueil n'étant dans aucun univers.
	 */
	static getScreenSection() {

		// Même liste que `changeArrowSection()`, Paramètres compris.
		const sections = [];
		qsa('.structure-bar .structure-nav-section[data-section]', node => sections[sections.length] = node.dataset.section);

		return sections.find(section => document.body.dataset.template.includes('structure-'+ section));

	}

	static scrollStrip() {

		if(this.hasBar() === false) {
			return;
		}

		const strips = Array.from(qsa('#structure-nav .structure-tabs')).filter(strip => strip.getClientRects().length > 0);

		strips.forEach(strip => strip.style.paddingRight = '');

		const layouts = strips
			.filter(strip => strip.scrollWidth > strip.clientWidth)
			.map(strip => {

				const stripBounding = strip.getBoundingClientRect();
				const style = window.getComputedStyle(strip);
				const anchor = parseFloat(style.scrollPaddingLeft);
				const max = strip.scrollWidth - strip.clientWidth;

				// Une bande en fin de course s'arrête où son contenu finit, pas sur une accroche : l'icône
				// collée y couperait l'entrée qui passe dessous. La marge de droite prolonge la course
				// jusqu'à l'accroche suivante, la plus proche.
				const anchors = Array.from(strip.qsa('.structure-tab, .structure-subnav-item, .dropdown-item'))
					.filter(node => node.getClientRects().length > 0)
					.map(node => [node, window.getComputedStyle(node)])
					.filter(([, nodeStyle]) => nodeStyle.scrollSnapAlign !== 'none')
					.map(([node, nodeStyle]) => node.getBoundingClientRect().left - parseFloat(nodeStyle.scrollMarginLeft) - stripBounding.left + strip.scrollLeft - anchor);

				const next = anchors.filter(position => position >= max - 1).sort((a, b) => a - b)[0] ?? max;

				// L'entrée d'abord : le groupe qui la contient porte aussi `selected`, et reste visible en
				// icône à gauche de la bande, où le centrer n'aurait aucun sens.
				const selected = ['.structure-subnav-item.selected, .dropdown-item.selected', '.selected, .structure-tab-wrapper:has(.structure-subnav-item.selected) > div.structure-tab']
					.map(selector => Array.from(strip.qsa(selector)).find(node => node.getClientRects().length > 0))
					.find(node => node !== undefined);

				const selectedBounding = selected?.getBoundingClientRect();

				return {
					strip: strip,
					paddingRight: parseFloat(style.paddingRight) + Math.max(0, next - max),
					scrollLeft: (selectedBounding === undefined) ?
						null :
						strip.scrollLeft + selectedBounding.left - stripBounding.left - (stripBounding.width - selectedBounding.width) / 2
				};

			});

		layouts.forEach(layout => {

			layout.strip.style.paddingRight = layout.paddingRight +'px';

			if(layout.scrollLeft !== null) {
				layout.strip.scrollLeft = layout.scrollLeft;
			}

		});

	}

	static scrollBreadCrumbs(target, selected) {

		const subNavOffset = qs('#structure-breadcrumbs-section').getBoundingClientRect().width;

		target.style.paddingLeft = subNavOffset +'px';

		const subNavSelected = target.qs('.selected');

		if(subNavSelected) {

			const subNavBounding = subNavSelected.getBoundingClientRect();
			const breadcrumbsBounding = target.getBoundingClientRect();

			const subNavLeft = subNavBounding.left + target.scrollLeft;

			const leftTarget = subNavOffset + (breadcrumbsBounding.width - subNavOffset) / 2 - subNavBounding.width / 2;

			const scroll = Math.max(0, subNavLeft - leftTarget);

			target.scroll(scroll, 0);

		}

	}

	/**
	 * La liste est chargée une fois par univers : l'adresse de chaque entrée suit celui de l'écran, qu'une
	 * navigation ajax change sans redessiner la barre. `Lime.Dropdown` place la liste d'après sa taille au
	 * moment où il l'ouvre, d'où l'ouverture après l'arrivée du contenu.
	 */
	static openSelector(button) {

		if(Lime.Dropdown.isOpen(button)) {
			return;
		}

		const list = qs('[data-dropdown-id="structure-selector-list"]');
		const url = this.withSelectorSection(button);

		if(list.dataset.structureSelectorLoaded === url) {
			Lime.Dropdown.open(button, 'bottom-start');
			return;
		}

		new Ajax.Query(button)
			.method('get')
			.url(url)
			.fetch()
			.then(() => {
				list.dataset.structureSelectorLoaded = url;
				Lime.Dropdown.open(button, 'bottom-start');
			});

	}

	/**
	 * L'univers de l'écran se lit dans body[data-template] (« structure-production »…), parmi ceux que le
	 * déclencheur porte, les seuls que la page accepte ; « structure-section-production », posé au survol
	 * d'un univers, n'y ressemble pas.
	 */
	static withSelectorSection(button) {

		const url = button.dataset.structureSelector;
		const templates = document.body.dataset.template.split(' ');
		const section = button.dataset.structureSections.split(' ').find(section => templates.includes('structure-'+ section));

		return (section === undefined) ? url : url +'&section='+ section;

	}

	/**
	 * Au-delà de dix structures, les entrées sont rangées dans deux replis. Chercher les ouvre et masque
	 * les récentes, qui montreraient deux fois une même structure ; un repli dont aucune structure ne
	 * répond disparaît, et quand plus aucun ne reste, la liste le dit.
	 */
	static filterSelector(field) {

		const list = field.closest('.structure-selector');
		const search = this.normalizeSearch(field.value);

		let found = false;

		list.qsa('.dropdown-item-group-items', items => {

			const group = items.previousElementSibling;

			let count = 0;

			items.qsa('.dropdown-item', node => {

				const match = this.normalizeSearch(node.textContent).includes(search);

				node.classList.toggle('hide', match === false);

				if(match) {
					count++;
				}

			});

			group.classList.toggle('dropdown-item-group-open', search !== '');
			group.classList.toggle('hide', count === 0);
			group.qs('.structure-selector-count', node => node.textContent = count);
			items.classList.toggle('hide', count === 0);

			if(count > 0) {
				found = true;
			}

		});

		list.qs('.structure-selector-recent', node => node.classList.toggle('hide', search !== ''));
		list.qs('.structure-selector-empty', node => node.classList.toggle('hide', found));

	}

	static normalizeSearch(value) {
		return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
	}

	static changeSearchFamily(target) {

		const seenField = target.firstParent('form').qs('.bed-rotation-search-seen');

		if(target.value) {
			seenField.classList.remove('hide');
		} else {
			seenField.classList.add('hide');
		}

	}

	static changeLegalCountry(target) {

		const legalCountry = parseInt(target.value);

		const form = target.firstParent('form');

		form.qsa('[data-wrapper="hasVat"]', wrapper => legalCountry === MainPlace.FR ?
			wrapper.classList.remove('hide') :
			wrapper.classList.add('hide'));

	}

	static chooseProducerType(target) {

		const form = target.firstParent('form');
		const producerType = target.dataset.producerType;

		form.qs('input[name="producerType"]').value = producerType;

		const wrapper = form.qs('[data-wrapper="producerType"]');
		let labelled = false;

		wrapper.qsa('[data-producer-type]', label => {
			const selected = (label.dataset.producerType === producerType);
			label.classList.toggle('hide', selected === false);
			labelled = labelled || selected;
		});

		wrapper.classList.toggle('hide', labelled === false);

		form.qsa('[data-wording]', label => label.classList.toggle('hide', label.dataset.wording !== target.dataset.wording));

		const fieldsHidden = target.dataset.fieldsHidden.split(' ').filter(name => name !== '');

		form.qsa('[data-step="fields"] [data-wrapper]', field => {

			if(field.dataset.wrapper !== 'producerType') {
				field.classList.toggle('hide', fieldsHidden.includes(field.dataset.wrapper));
			}

		});

		form.qs('[data-step="producer-type"]').classList.add('hide');
		form.qs('[data-step="fields"]').classList.remove('hide');

	}

}
