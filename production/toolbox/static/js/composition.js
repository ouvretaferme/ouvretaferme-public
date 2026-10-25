/**
 * Le choix des semaines livrées : la case de chaque mois suit les semaines qu'il range, et « Toute
 * l'année » suit toutes celles de la saison.
 *
 * `indeterminate` n'existe qu'en propriété, aucun attribut HTML ne le pose : l'état d'un mois ou
 * d'une année à moitié livrés se calcule donc ici, au rendu puis à chaque geste. Aucune de ces cases
 * ne garde d'état à elle : il se relit sur les semaines, les seules qui partent au serveur.
 *
 * Une case de tête coche ses semaines par `CheckboxField.all()`, qui n'émet pas `change` :
 * `checkWeeks()` recalcule donc lui-même les autres cases de tête, la case d'un mois changeant celle
 * de l'année et l'inverse.
 */
class ToolboxComposition {

	static updateMonths(target) {

		const fields = [
			[target.qs('.toolbox-composition-weeks-all input[type="checkbox"]'), target],
			...Array.from(target.qsa('.util-dates-month')).map(month => [month.qs('.util-label input[type="checkbox"]'), month])
		];

		this.updateFields(fields);

	}

	static updateMonth(month) {

		const target = month.firstParent('.toolbox-composition-weeks');

		this.updateFields([
			[target.qs('.toolbox-composition-weeks-all input[type="checkbox"]'), target],
			[month.qs('.util-label input[type="checkbox"]'), month]
		]);

	}

	// Chaque case de tête se lit sur les semaines de sa portée, toutes les lectures avant la première
	// écriture.
	static updateFields(fields) {

		const states = fields.map(([field, scope]) => {

			const weeks = Array.from(scope.qsa('[name="weeks[]"]'));
			const checked = weeks.filter(week => week.checked).length;

			return [field, weeks.length > 0 && checked === weeks.length, checked > 0 && checked < weeks.length];

		});

		states.forEach(([field, checked, indeterminate]) => {
			field.checked = checked;
			field.indeterminate = indeterminate;
		});

	}

	static checkWeeks(field, scope) {

		CheckboxField.all(field.firstParent(scope), field.checked, '[name="weeks[]"]');

		this.updateMonths(field.firstParent('.toolbox-composition-weeks'));

	}

}

document.delegateEventListener('change', '.toolbox-composition-weeks [name="weeks[]"]', function() {
	ToolboxComposition.updateMonth(this.firstParent('.util-dates-month'));
});

/**
 * L'envoi du panneau « Semaine par semaine » se confirme quand il décoche une semaine corrigée
 * (lot N) : chaque jeton corrigé porte la phrase de ce qu'il ferait perdre (`data-loss`,
 * `CompositionUi::getWeeksDates()`), et le bouton reçoit, au moment du clic, le `data-confirm` qui
 * les enchaîne, ou le perd si toutes sont cochées.
 *
 * Le message se compose au clic et non à chaque case changée : la case d'un mois coche ses
 * semaines par `CheckboxField.all()`, qui n'émet pas `change`, et le glisser bascule des périodes
 * entières. Écouté en capture, sur `window` : le délégué de `data-confirm`
 * (`framework/util/static/js/lime.js`) écoute le même clic en capture sur le document, où il est
 * inscrit avant ce fichier, et lirait l'attribut avant qu'il ne soit posé ; la capture passe par
 * `window` avant le document. La validation au clavier passe aussi par là, le navigateur la rendant
 * en clic sur le bouton d'envoi.
 */
window.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('#toolbox-composition-weeks-panel [type="submit"][data-question]') : null;

	if(button === null) {
		return;
	}

	const losses = Array.from(button.form.qsa('[name="weeks[]"][data-loss]'))
		.filter(input => input.checked === false)
		.map(input => input.dataset.loss);

	if(losses.length > 0) {
		button.dataset.confirm = losses.join(' ') +' '+ button.dataset.question;
	} else {
		delete button.dataset.confirm;
	}

}, true);

/**
 * L'envoi du panneau « Modifier les paniers » (r31, #96) se confirme quand il recompose une taille qui
 * porte des semaines corrigées à la main : le nom en tête de sa colonne porte la phrase de ce qu'elle
 * perdrait (`data-loss`, `CompositionFormulaUi::getRecomposeLoss()`) et l'identifiant de la taille
 * (`data-formula`), qui retrouve ses champs dans les autres rangs (`price[<id>]`, r32). La phrase ne
 * compte que si l'un de ses champs qui recomposent a changé
 * (`CompositionFormulaLib::getPropertiesRecompose()`). Le nom et le nombre de paniers ne recomposent
 * rien. Même écoute que le panneau des semaines, et pour la même raison.
 *
 * Un champ a changé quand il s'écarte de son rendu : `defaultValue`, `defaultChecked` et
 * `defaultSelected` en gardent l'état, que la saisie ne touche pas. Revenir à la valeur d'ouverture
 * n'est donc pas un changement, comme pour le serveur, qui n'écrit que ce qui diffère.
 */
class ToolboxCompositionSettings {

	static isChanged(field) {

		if(field.type === 'radio' || field.type === 'checkbox') {
			return field.checked !== field.defaultChecked;
		}

		if(field.tagName === 'SELECT') {
			return Array.from(field.options).some(option => option.selected !== option.defaultSelected);
		}

		return field.value !== field.defaultValue;

	}

	static hasChanges(form) {

		return Array.from(form.elements).some(field => field.type !== 'hidden' && field.type !== 'submit' && this.isChanged(field));

	}

	static getLosses(form) {

		const properties = ['price', 'gap', 'linesMin', 'linesMax', 'base'];

		return Array.from(form.qsa('[data-loss]'))
			.filter(column => properties.some(property => Array.from(form.qsa('[name="'+ property +'['+ column.dataset.formula +']"]'))
				.some(field => this.isChanged(field))))
			.map(column => column.dataset.loss);

	}

}

window.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('#toolbox-composition-settings-panel [type="submit"][data-question]') : null;

	if(button === null) {
		return;
	}

	const losses = ToolboxCompositionSettings.getLosses(button.form);

	if(losses.length > 0) {
		button.dataset.confirm = losses.join(' ') +' '+ button.dataset.question;
	} else {
		delete button.dataset.confirm;
	}

}, true);

/**
 * « Ajouter un panier », en tête de « Modifier les paniers » (r51b) : le panneau se rend de nouveau
 * après l'ajout, et un réglage tapé sans être enregistré n'y survit pas. Le lien ne se confirme que
 * si un champ a changé, par la même écoute que l'envoi et pour la même raison.
 */
window.addEventListener('click', e => {

	const link = (e.target instanceof Element) ? e.target.closest('#toolbox-composition-settings-add') : null;

	if(link === null) {
		return;
	}

	if(ToolboxCompositionSettings.hasChanges(qs('#toolbox-composition-settings-panel form'))) {
		link.dataset.confirm = link.dataset.question;
	} else {
		delete link.dataset.confirm;
	}

}, true);

/**
 * Le deck « Plan de culture » de l'onglet « Espèces » (r51b) : la case de tête et le bouton suivent
 * les cases cochées, une par espèce. La case de tête est cochée quand toutes le sont, partielle
 * quand certaines le sont, et le bouton dit combien d'espèces il enverra, dans celle de ses trois
 * formes (`data-none`, `data-one`, `data-many`) que le compte demande.
 *
 * `CheckboxField.all()` coche les cases sans émettre `change` : la case de tête appelle `update()`
 * par son propre rappel, les cases cochées une à une passent par l'écouteur ci-dessous.
 *
 * Sans case cochée le bouton prend `disabled`, qui seule bloque le clic sur un `.btn`. Entrée dans
 * le formulaire part quand même, et le serveur répond par un refus.
 */
class ToolboxCompositionSynchronize {

	static update() {

		const form = qs('#toolbox-composition-synchronize');
		const boxes = Array.from(form.qsa('[name="species[]"]'));
		const checked = boxes.filter(box => box.checked).length;

		const all = form.qs('.util-table-head input[type="checkbox"]');

		all.checked = (checked === boxes.length);
		all.indeterminate = (checked > 0 && checked < boxes.length);

		const button = form.qs('.toolbox-composition-synchronize-submit .btn');

		button.classList.toggle('disabled', checked === 0);

		if(checked === 0) {
			button.textContent = button.dataset.none;
		} else if(checked === 1) {
			button.textContent = button.dataset.one;
		} else {
			button.textContent = button.dataset.many.replace('{value}', checked);
		}

	}

}

document.delegateEventListener('change', '#toolbox-composition-synchronize [name="species[]"]', () => ToolboxCompositionSynchronize.update());

/**
 * Le formulaire de l'unité d'une espèce (lot r14, `CompositionSpeciesUi::p('unit')`) : l'unité cochée
 * montre le poids d'une pièce ou d'une botte qui la convertit, les autres se masquent et se
 * désactivent, pour que seul le poids de l'unité choisie parte avec l'envoi.
 */
document.delegateEventListener('change', '.toolbox-composition-unit [name="unit"]', function() {

	this.firstParent('.toolbox-composition-unit').qsa('[data-unit]').forEach(group => {

		const hidden = (group.dataset.unit !== this.value);

		group.classList.toggle('hide', hidden);
		group.qsa('input').forEach(input => input.disabled = hidden);

	});

});

/**
 * Au panneau « Ajouter une espèce » du départ (r48c, `CompositionSpeciesUi::addStart()`), la quantité
 * au panier et le prix d'une espèce que la communauté ne compte pas se disent dans l'unité cochée
 * (`data-follow-text`). Rien n'est encore tapé qu'une autre unité rendrait faux : les champs gardent
 * leur valeur, seuls leurs mots changent. Une fois les paniers composés, le panneau pose les réglages
 * de « Régler une espèce », que suit l'écouteur suivant.
 */
document.delegateEventListener('change', '#toolbox-composition-add-panel form:not(.toolbox-composition-species-form) [name="unit"]', function() {

	const panel = this.firstParent('#toolbox-composition-add-panel');

	panel.qsa('[data-follow-text="singular"]').forEach(node => node.textContent = this.dataset.singular);
	panel.qsa('[data-follow-text="plural"]').forEach(node => node.textContent = this.dataset.plural);
	panel.qsa('[data-follow-text="by"]').forEach(node => node.innerHTML = this.dataset.by);

});

/**
 * Au panneau « Régler une espèce » (lot r22f), le prix, le pas, le minimum et la quantité par défaut
 * (r30, #92) se disent dans l'unité cochée (`CompositionSpeciesUi::update()`, `data-follow`) : une
 * autre unité vide leurs champs, que
 * le serveur efface s'ils restent vides et garde s'ils sont retapés
 * (`CompositionSpeciesLib::getPropertiesPanel()`), et nomme sa propre unité dans leurs libellés et
 * leurs suffixes. Le pas se masque et ne part pas quand la nouvelle unité ne se coupe pas, et son
 * champ vide propose le pas de la nouvelle unité, comme celui du minimum son minimum (lot r25). Le prix de la cascade et d'où il vient se disent
 * dans l'unité d'avant : ils se taisent tant qu'une autre est cochée.
 *
 * Revenir à l'unité d'ouverture rend tout ce que le panneau montrait : `defaultChecked` et
 * `defaultValue` sont l'état du rendu, que la saisie ne change pas. Le placeholder d'ouverture se
 * garde au premier changement, le navigateur n'en tenant pas de copie.
 *
 * Le panneau « Ajouter une espèce » pose les mêmes réglages (r60, `CompositionSpeciesUi::getSettings()`)
 * et se suit de la même façon : les deux formulaires portent `toolbox-composition-species-form`. Une
 * espèce que la communauté ne compte pas y part sans unité ni produit vendu, et toute unité cochée y
 * est donc une autre unité.
 *
 * Une variante (r76 lot 2) y part aussi sans unité cochée, avec les valeurs de son espèce, dites dans
 * l'unité de l'espèce (`data-copied`) : cocher cette unité-là les rend, en cocher une autre les vide.
 * Chaque unité porte le prix de la mercuriale de l'espèce dans cette unité (`data-price`), que le
 * champ du prix montre en attente.
 */
document.delegateEventListener('change', '.toolbox-composition-species-form [name="unit"]', function() {

	const panel = this.firstParent('.toolbox-composition-species-form');
	const initial = this.defaultChecked;
	const kept = (initial || this.dataset.copied !== undefined);

	panel.qsa('[data-follow]:not([data-follow="product"]) input').forEach(input => {

		if(input.dataset.placeholder === undefined) {
			input.dataset.placeholder = input.placeholder;
		}

		input.value = kept ? input.defaultValue : '';

	});

	const price = panel.qs('[data-follow="price"]');

	price.qs('input').placeholder = initial ? price.qs('input').dataset.placeholder : (this.dataset.price ?? '');
	price.qsa('.form-info').forEach(info => info.classList.toggle('hide', initial === false));

	// Le produit vendu se vend dans l'unité d'ouverture (r39b) : une autre unité le délie, ce qui
	// rend le champ du prix, et sa recherche, qui ne connaît que les produits de cette unité, se
	// ferme (r53). Revenir à l'unité d'ouverture rend le produit d'ouverture, que son groupe garde
	// (`data-value`, `data-price`, `data-from`) : le champ caché de l'autocomplétion ne survit pas à
	// une recherche.
	const product = panel.qs('[data-follow="product"]');

	if(product !== null) {

		const query = product.qs('[data-autocomplete-field="product"]');

		// À l'ajout (r60), rien n'est lié qu'une autre unité délierait : la recherche reste ouverte et
		// cherche dans l'unité cochée (`autocompleteBeforeQuery` ci-dessous), et le groupe, qui
		// attendait une unité sur une espèce typée, se montre.
		const followUnit = (product.dataset.followUnit !== undefined);

		product.classList.remove('hide');
		query.disabled = (initial === false && followUnit === false);

		if(initial) {
			ToolboxCompositionSpecies.linkProduct(query, product.dataset.value ?? '', query.defaultValue);
			ToolboxCompositionSpecies.followProduct(panel, product.dataset);
		} else {
			ToolboxCompositionSpecies.linkProduct(query, '', '');
			ToolboxCompositionSpecies.followProduct(panel, {});
		}

	}

	// Le pas est toujours dans le formulaire, masqué et désactivé quand l'unité cochée ne se coupe pas.
	const step = panel.qs('[data-follow="step"]');
	const hidden = (this.dataset.divisible === '0');

	step.classList.toggle('hide', hidden);
	step.qs('input').disabled = hidden;
	step.qs('input').placeholder = initial ? step.qs('input').dataset.placeholder : this.dataset.step;

	const minimum = panel.qs('[data-follow="minimum"] input');

	minimum.placeholder = initial ? minimum.dataset.placeholder : this.dataset.minimum;

	ToolboxCompositionSpecies.followMinimum(panel);

	// Un libellé qui attendait une unité prend sa forme avec unité, que la ligne suivante remplit.
	panel.qsa('[data-follow-label]').forEach(node => node.outerHTML = node.dataset.followLabel);

	panel.qsa('[data-follow-text="singular"]').forEach(node => node.textContent = this.dataset.singular);
	panel.qsa('[data-follow-text="plural"]').forEach(node => node.textContent = this.dataset.plural);
	panel.qsa('[data-follow-text="by"]').forEach(node => node.innerHTML = this.dataset.by);

});

/**
 * Vide, la quantité par défaut vaut le minimum qui s'applique (r30, #92) : son attente suit ce que
 * le champ du minimum tape, ou son attente quand il est vide, y compris celle d'une unité cochée.
 */
class ToolboxCompositionSpecies {

	/**
	 * Le prix d'une espèce liée suit son produit (r39b) : le choix d'un produit qui porte un prix
	 * (`price`, celui que le panier retiendra, et `from`, d'où il vient, dans le résultat de
	 * l'autocomplétion, r53, `CompositionSpeciesUi::getProductAutocomplete()`) masque le champ du
	 * prix au profit de ce prix figé. Un champ vidé, ou un produit sans prix sur son canal, rend le
	 * champ tel qu'il était : masqué, il a gardé sa valeur, et c'est elle qui part à l'envoi.
	 */
	static followProduct(panel, result) {

		const linked = (result.price !== undefined);

		const fixed = panel.qs('[data-price-product]');

		if(linked) {
			fixed.qs('[data-price-value]').innerHTML = result.price;
			fixed.qs('.form-info').innerHTML = result.from;
		}

		fixed.classList.toggle('hide', linked === false);
		panel.qs('[data-follow="price"]').classList.toggle('hide', linked);

	}

	/**
	 * Le produit que l'envoi postera, et le texte du champ, comme `AutocompleteField.apply()` les
	 * pose, sans l'événement de choix : l'appelant suit le prix lui-même.
	 */
	static linkProduct(query, value, text) {

		const hidden = document.createElement('input');
		hidden.setAttribute('type', 'hidden');
		hidden.setAttribute('name', 'product');
		hidden.setAttribute('value', value);

		qs('#'+ query.dataset.autocompleteItems).replaceChildren(hidden);

		query.value = text;

	}

	static followMinimum(panel) {

		const minimum = panel.qs('[data-follow="minimum"] input');

		panel.qs('[data-follow="quantity"] input').placeholder = (minimum.value !== '') ? minimum.value : minimum.placeholder;

	}

}

document.delegateEventListener('autocompleteSelect', '.toolbox-composition-species-form [data-autocomplete-field="product"]', function(e) {
	ToolboxCompositionSpecies.followProduct(this.firstParent('.toolbox-composition-species-form'), e.detail);
});

/**
 * Effacer le texte au clavier délie, comme la croix (r53) : sans choix, l'autocomplétion ne poste
 * aucun produit, et le serveur, qui ne construit pas un champ absent, garderait le lien qu'on croit
 * défait. Le champ caché vide se pose donc au geste, et le prix se rend dans le même temps. La
 * recherche qui part 200 ms plus tard vide les champs cachés avant d'interroger
 * (`AutocompleteField.query()`) : il se repose aussitôt, sur `autocompleteBeforeQuery`, qu'elle
 * émet juste après ce vidage, pour qu'un envoi fait pendant qu'elle attend sa réponse poste
 * toujours un produit vide.
 */
document.delegateEventListener('autocompleteUpdate', '.toolbox-composition-species-form [data-autocomplete-field="product"]', function() {

	if(this.value === '') {
		ToolboxCompositionSpecies.linkProduct(this, '', '');
		ToolboxCompositionSpecies.followProduct(this.firstParent('.toolbox-composition-species-form'), {});
	}

});

document.delegateEventListener('autocompleteBeforeQuery', '.toolbox-composition-species-form [data-autocomplete-field="product"]', function(e) {

	if(this.value === '') {
		ToolboxCompositionSpecies.linkProduct(this, '', '');
	}

	// À l'ajout (r60), la recherche cherche dans l'unité cochée, que le serveur contrôle par `build()`.
	if(this.firstParent('[data-follow="product"]').dataset.followUnit !== undefined) {

		const unit = this.firstParent('.toolbox-composition-species-form').qs('[name="unit"]:checked');

		e.detail.body.set('unit', unit === null ? '' : unit.value);

	}

});

document.delegateEventListener('input', '.toolbox-composition-species-form [data-follow="minimum"] input', function() {
	ToolboxCompositionSpecies.followMinimum(this.firstParent('.toolbox-composition-species-form'));
});

/**
 * L'envoi du formulaire de l'unité se confirme quand l'unité cochée efface un réglage tapé : chaque
 * choix porte la phrase de ce qu'il ferait perdre (`data-loss`, `CompositionSpeciesUi::getUnitLoss()`),
 * que le bouton reçoit au moment du clic. En capture sur `window`, pour la même raison que l'envoi
 * du panneau « Semaine par semaine » ci-dessus.
 */
window.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('[type="submit"]') : null;

	if(button === null || button.form === null || button.form.qs('.toolbox-composition-unit') === null) {
		return;
	}

	const radio = button.form.qs('.toolbox-composition-unit [name="unit"]:checked');

	if(radio !== null && radio.dataset.loss !== undefined) {
		button.dataset.confirm = radio.dataset.loss;
	} else {
		delete button.dataset.confirm;
	}

}, true);

/**
 * Glisser sur les semaines (`CompositionUi::getWeeksDates()`), au panneau « Semaine par semaine » et à
 * l'étape « Vos semaines » du départ : c'est le geste des planches du plan de la ferme
 * (`MapPlan.startDrag()` dans `map/static/js/plan.js`), porté sur des cases à cocher. La semaine où
 * le geste commence dit s'il coche ou décoche, et toute la période parcourue, d'un mois à l'autre,
 * prend cet état. Revenir en arrière rend aux semaines quittées l'état d'avant le geste.
 *
 * Un clic sans mouvement reste le clic natif du jeton : il bascule une semaine seule, ce qui ouvre
 * un trou dans une période. Après un mouvement, le clic que le navigateur émet au relâché est
 * écarté, sinon il rebasculerait la semaine sous le doigt.
 *
 * Chaque case changée émet `input` et `change`, qui remontent : la case du mois se recalcule
 * (écouteur ci-dessus) et l'aperçu du départ se redessine, comme après un clic.
 *
 * Au toucher, le geste ne s'arme qu'après un appui tenu (`touchDelay`) : un doigt qui glisse tout de
 * suite fait défiler la page, comme sur le plan de la ferme.
 */
class ToolboxCompositionWeeks {

	static touchDelay = 200;
	static drag = null;
	static suppressClick = false;

	static start(node, event) {

		this.suppressClick = false;

		if(event.button !== undefined && event.button !== 0) {
			return;
		}

		const touch = (event.pointerType !== undefined && event.pointerType !== 'mouse');
		const weeks = [...node.firstParent('.toolbox-composition-weeks').qsa('.util-date')];

		this.drag = {
			weeks: weeks,
			start: node,
			current: node,
			touched: [],
			before: new Map(weeks.map(week => [week, this.input(week).checked])),
			select: this.input(node).checked === false,
			moved: false,
			armed: touch === false,
			timer: null
		};

		if(touch) {
			this.drag.timer = setTimeout(() => {
				if(this.drag !== null) {
					this.drag.armed = true;
					this.drag.timer = null;
				}
			}, this.touchDelay);
		}

	}

	static input(week) {
		return week.qs('input[name="weeks[]"]');
	}

	static move(event) {

		const drag = this.drag;

		if(drag === null || drag.armed === false) {
			return;
		}

		const target = document.elementFromPoint(event.clientX, event.clientY);
		const week = (target === null) ? null : target.closest('.util-date');

		if(week === null || week === drag.current || drag.weeks.includes(week) === false) {
			return;
		}

		drag.moved = true;
		drag.current = week;

		const from = drag.weeks.indexOf(drag.start);
		const to = drag.weeks.indexOf(week);
		const range = drag.weeks.slice(Math.min(from, to), Math.max(from, to) + 1);

		drag.touched
			.filter(touched => range.includes(touched) === false)
			.forEach(touched => this.set(touched, drag.before.get(touched)));

		range.forEach(touched => this.set(touched, drag.select));
		drag.touched = range;

		event.preventDefault();

	}

	static set(week, checked) {

		const input = this.input(week);

		if(input.checked === checked) {
			return;
		}

		input.checked = checked;
		input.dispatchEvent(new Event('input', {bubbles: true}));
		input.dispatchEvent(new Event('change', {bubbles: true}));

	}

	static end() {

		const drag = this.drag;

		if(drag === null) {
			return;
		}

		this.drag = null;

		if(drag.timer !== null) {
			clearTimeout(drag.timer);
		}

		this.suppressClick = drag.moved;

	}

}

document.delegateEventListener('pointerdown', '.toolbox-composition-weeks .util-date', function(e) {
	ToolboxCompositionWeeks.start(this, e);
});

document.addEventListener('pointermove', e => ToolboxCompositionWeeks.move(e));
document.addEventListener('pointerup', () => ToolboxCompositionWeeks.end());
document.addEventListener('pointercancel', () => ToolboxCompositionWeeks.end());

// ⚠️ `passive: false` : un écouteur `touchmove` est passif par défaut, et son `preventDefault()` ne
// retiendrait pas le défilement une fois le geste armé.
document.addEventListener('touchmove', e => {
	if(ToolboxCompositionWeeks.drag !== null && ToolboxCompositionWeeks.drag.armed) {
		e.preventDefault();
	}
}, {passive: false});

// En capture, avant le clic natif du jeton qui basculerait sa case.
document.addEventListener('click', e => {

	if(ToolboxCompositionWeeks.suppressClick === false) {
		return;
	}

	ToolboxCompositionWeeks.suppressClick = false;

	if(e.target instanceof Element && e.target.closest('.toolbox-composition-weeks') !== null) {
		e.preventDefault();
		e.stopPropagation();
	}

}, true);


/**
 * La semaine survolée de la grille (É10) : ses cases prennent `util-column-hover`, et une bulle dit
 * au-dessus de sa colonne la semaine, ses jours et sa valeur (planche r1). Tout est rendu d'avance par
 * `CompositionUi::getGrid()`, masqué : le survol ne fait que montrer et cacher, sans requête.
 *
 * Les écouteurs sont posés une fois sur le document et non sur la grille : la grille se rend de
 * nouveau à chaque réponse des panneaux (semaines, espèces), sans navigation, et des écouteurs posés
 * sur elle partiraient avec l'ancienne.
 *
 * Les semaines livrées prennent une pastille sur l'axe (`decorate()`), que `series\CultivationUi` rend
 * sans elle. La colonne ouverte des lots r8 à r8d, que la pastille ou une case ouvrait, est partie au
 * lot r24 (retour du 27/09, « il n'y a plus de modification en colonne »).
 *
 * Au toucher, il n'y a pas de survol : le toucher sur une colonne l'allume, et elle le reste jusqu'au
 * toucher suivant ailleurs, sur une autre colonne ou hors de la grille, comme la bande d'implantation
 * de la bibliothèque de cultures (`plant/static/js/communityAi.js`).
 *
 * La ligne survolée (retour du 27/09) : à la souris, le rang de l'espèce prend la même
 * teinte que la colonne, du nom à la dernière semaine. La classe se pose sur le `<tr>` et non sur ses
 * cases : une case est à la fois dans la ligne et dans la colonne, et éteindre l'une ne doit pas
 * éteindre l'autre ; une réponse qui ne remplace que le contenu d'un rang (`getGridChanges()`) garde
 * aussi la classe. Au toucher, rien ne s'allume sur la ligne : un doigt ne sort jamais de la grille
 * par un `pointerout` de souris, et la ligne resterait allumée jusqu'au toucher suivant.
 *
 * La case (É11, revue au lot r24) : un clic sur une case servie ou de saison ouvre son menu
 * (`menu()`), où se règle ce que la case sert ; il n'allume ni n'éteint rien, un clic de passage
 * ajoutant une espèce qu'on n'avait pas choisie.
 */
class ToolboxCompositionGrid {

	/**
	 * La grille vient d'être rendue, au chargement ou par une réponse qui la remplace entière : les
	 * semaines livrées prennent leur pastille, et la grille reprend le réglage de la saisonnalité s'il était
	 * allumé (`ToolboxCompositionSeason`).
	 */
	static renderGrid(grid) {

		this.decorate(grid);

		ToolboxCompositionSeason.renderGrid(grid);

	}

	/**
	 * La pastille des semaines livrées, lues sur la grille (`data-weeks`).
	 */
	static decorate(grid) {

		this.getWeeks(grid)
			.map(week => this.getMark(grid, week))
			.forEach(mark => mark.classList.add('toolbox-composition-grid-mark-week'));

	}

	static getWeeks(grid) {
		return grid.dataset.weeks.split(' ');
	}

	/**
	 * Le menu de la case `cell` (lot r24) : le serveur le rend (`compositionLine:getMenu`), dans la
	 * liste unique de la grille (`CompositionUi::getGrid()`), puis il s'ouvre sous la case. Un menu
	 * vide ne s'ouvre pas : la case n'a rien à proposer. Un second clic sur la case referme son menu,
	 * le délégué de ce fichier passant avant l'écouteur de fermeture de `Lime.Dropdown`, inscrit sur
	 * le document à chaque ouverture. Un clic sur une autre case, le menu ouvert, ne fait que le
	 * refermer, comme un clic n'importe où ailleurs (retour r28, #71) : la case cliquée
	 * s'ouvre au clic suivant.
	 *
	 * Toutes les cases désignent la même liste par `data-dropdown-id` : `Lime.Dropdown` la cherche
	 * par lui (`getListFromButton()`), et referme le menu ouvert sur une autre case avant de l'ouvrir
	 * sur celle-ci. La case poste ce que postent ses gestes.
	 */
	static menu(cell) {

		const grid = cell.firstParent('.toolbox-composition-grid');
		const opened = grid.qs('td[data-dropdown-display]');

		if(opened !== null) {
			Lime.Dropdown.close(opened);
			return;
		}

		const body = new URLSearchParams({
			structure: grid.dataset.structure,
			formula: grid.dataset.formula,
			week: cell.dataset.week,
			species: cell.firstParent('tr').dataset.species
		});

		return new Ajax.Query(cell)
			.url('/toolbox/compositionLine:getMenu')
			.method('post')
			.body(body)
			.fetch()
			.then(json => {

				if(json.menu === '' || document.body.contains(cell) === false) {
					return;
				}

				const list = document.getElementById('toolbox-composition-cell-menu');

				list.renderInner(json.menu);

				cell.dataset.dropdownId = 'toolbox-composition-cell-menu';
				Lime.Dropdown.open(cell, 'bottom-center');

			});

	}

	/**
	 * L'axe (`series\CultivationUi::getListSeason()`) gradue les semaines dans l'ordre, à partir de la
	 * première semaine de la saison (`data-season-first`) : la graduation d'une semaine est la n-ième
	 * après elle.
	 */
	static getMark(grid, week) {
		return grid.qsa('.toolbox-composition-grid-axis .util-graduation-mark')[parseInt(week) - parseInt(grid.dataset.seasonFirst)];
	}

	/**
	 * La bulle de la semaine (`CompositionUi::getGridTip()`) ne se rouvre que si elle est fermée : une
	 * réponse peut l'avoir remplacée, fermée, sous une souris qui n'a pas quitté la colonne, et le
	 * survol suivant d'une case de la même semaine la rouvre.
	 */
	static show(grid, week) {

		if(grid.dataset.week !== week) {

			this.hide(grid);

			grid.qsa('td[data-week="'+ week +'"]', cell => cell.classList.add('util-column-hover'));

			grid.dataset.week = week;

		}

		const tip = grid.qs(':scope > .util-tip[data-week="'+ week +'"]');

		if(tip === null) {
			return;
		}

		if(tip.classList.contains('util-tip-open') === false) {
			this.place(grid, tip);
		}

	}

	static hide(grid) {

		const week = grid.dataset.week;

		if(week === undefined) {
			return;
		}

		grid.qsa('td[data-week="'+ week +'"]', cell => cell.classList.remove('util-column-hover'));
		grid.qsa(':scope > .util-tip-open', tip => tip.classList.remove('util-tip-open', 'util-tip-above', 'util-tip-below'));

		delete grid.dataset.week;

	}

	// `species` vaut `undefined` sur un rang qui n'est pas celui d'une espèce (la tête d'un groupe) : la
	// ligne allumée s'éteint.
	static showRow(grid, species) {

		this.hideRow(grid);

		if(species === undefined) {
			return;
		}

		grid.qs('tr[data-species="'+ species +'"]', row => row.classList.add('toolbox-composition-grid-row-hover'));

		grid.dataset.species = species;

	}

	static hideRow(grid) {

		grid.qsa('tr.toolbox-composition-grid-row-hover', row => row.classList.remove('toolbox-composition-grid-row-hover'));

		delete grid.dataset.species;

	}

	/**
	 * Pose la bulle au-dessus de la graduation de sa semaine, la flèche sur elle, comme `SeriesTip.place()`
	 * (`series/static/js/place.js`) : la bulle s'ouvre, se mesure avec la graduation, puis s'écrit, en
	 * deux passes.
	 *
	 * Quand l'en-tête est sorti de l'écran par le haut, la bulle reste en haut de la fenêtre, au-dessus
	 * de la colonne ; elle ne passe pas sous la graduation, où elle cacherait les cases qu'on survole.
	 *
	 * La bulle reste à sa place quand la grille défile dans son `util-overflow` ou que la page défile :
	 * l'écouteur de défilement en bas de ce fichier la replace.
	 */
	static place(grid, tip, margin = 8) {

		const mark = this.getMark(grid, tip.dataset.week);

		tip.classList.add('util-tip-open', 'util-tip-above');

		const rect = mark.getBoundingClientRect();
		const width = tip.offsetWidth;
		const height = tip.offsetHeight;
		const span = document.documentElement.clientWidth;

		const center = rect.left + rect.width / 2;
		const left = Math.max(margin, Math.min(center - width / 2, span - width - margin));
		const top = Math.max(margin, rect.top - height - 10);

		tip.style.left = left +'px';
		tip.style.top = top +'px';
		tip.style.setProperty('--tip-arrow', Math.min(Math.max(center - left - 5, 12), width - 24) +'px');

	}

}

// Le défilement ne remonte pas : écouté en capture, il attrape aussi celui de la grille dans son
// `util-overflow`, qui laisserait la bulle loin de sa colonne.
document.addEventListener('scroll', () => {

	document.querySelectorAll('.toolbox-composition-grid > .util-tip-open').forEach(tip => {
		ToolboxCompositionGrid.place(tip.firstParent('.toolbox-composition-grid'), tip);
	});

}, {capture: true, passive: true});

document.delegateEventListener('pointerover', '.toolbox-composition-grid td[data-week]', function(e) {

	if(e.pointerType === 'touch') {
		return;
	}

	ToolboxCompositionGrid.show(this.firstParent('.toolbox-composition-grid'), this.dataset.week);

});

// La souris qui passe sur la colonne des noms garde la semaine allumée. Seule la sortie de la grille
// l'éteint.
document.delegateEventListener('pointerout', '.toolbox-composition-grid', function(e) {

	if(e.pointerType === 'touch') {
		return;
	}

	if(e.relatedTarget instanceof Node && this.contains(e.relatedTarget)) {
		return;
	}

	ToolboxCompositionGrid.hide(this);
	ToolboxCompositionGrid.hideRow(this);

});

// Le délégué remonte les parents de la cible : il part pour chaque case où entre la souris, et ne
// réécrit la ligne que quand elle change.
document.delegateEventListener('pointerover', '.toolbox-composition-grid tr', function(e) {

	if(e.pointerType === 'touch') {
		return;
	}

	const grid = this.firstParent('.toolbox-composition-grid');

	if(grid.dataset.species !== this.dataset.species) {
		ToolboxCompositionGrid.showRow(grid, this.dataset.species);
	}

});

document.addEventListener('pointerdown', e => {

	if(e.pointerType !== 'touch') {
		return;
	}

	const cell = (e.target instanceof Element) ? e.target.closest('.toolbox-composition-grid td[data-week]') : null;

	document.querySelectorAll('.toolbox-composition-grid[data-week]').forEach(grid => {
		if(cell === null || grid.contains(cell) === false) {
			ToolboxCompositionGrid.hide(grid);
		}
	});

	// Une ligne allumée par une souris avant que le doigt ne prenne le relais, sur un écran qui a les deux.
	document.querySelectorAll('.toolbox-composition-grid[data-species]').forEach(grid => ToolboxCompositionGrid.hideRow(grid));

	if(cell !== null) {
		ToolboxCompositionGrid.show(cell.firstParent('.toolbox-composition-grid'), cell.dataset.week);
	}

});

// Les cases qui ouvrent un menu sont celles que le serveur marque (`CompositionUi::getGridCellAttributes()`),
// et seulement pour qui peut écrire le plan (`data-editable`). Pendant le réglage de la saisonnalité, une case
// se tire et n'ouvre rien (lot r27).
document.delegateEventListener('click', '.toolbox-composition-grid[data-editable]:not(.toolbox-composition-season-mode) td.toolbox-composition-grid-cell', function() {
	ToolboxCompositionGrid.menu(this);
});

/**
 * La quantité d'une ligne des cartes des semaines, qui se tape en place (lot P10) : la ligne ne porte
 * pas son formulaire, qui coûterait 1 Ko par ligne pour un champ qu'on ouvre rarement. Il est rendu
 * une fois (`CompositionUi::getWeekQuantityTemplate()`), et recopié dans la ligne au premier clic,
 * sans requête, avec la semaine, l'espèce, la valeur et l'unité que la ligne porte en `data-*`.
 * L'ouverture, la fermeture et l'envoi sont ensuite ceux de `util-quick-inline` (`MainQuickInline`).
 *
 * Les valeurs se posent en attributs, et non en propriétés, pour que le formulaire recopié soit
 * celui que le serveur rendrait. Les identifiants de la copie sont retirés : copiée dans plusieurs
 * lignes, elle les porterait plusieurs fois dans le document, et rien ne les nomme.
 */
class ToolboxCompositionQuantity {

	static open(box) {

		if(box.qs('.util-quick-inline-field') === null) {
			box.append(this.create(box));
		}

		MainQuickInline.open(box);

	}

	static create(box) {

		const form = document.getElementById('toolbox-composition-quantity-template').content.querySelector('.util-quick-inline-field').cloneNode(true);

		form.qs('[name="week"]').setAttribute('value', box.dataset.week);
		form.qs('[name="species"]').setAttribute('value', box.dataset.species);
		form.qs('[name="quantity"]').setAttribute('value', box.dataset.quantity);
		form.qs('.input-group-addon').textContent = box.dataset.unit;

		form.qsa('[id]', node => node.removeAttribute('id'));

		return form;

	}

}

document.delegateEventListener('click', '[data-composition-quantity]', function(e) {
	e.preventDefault();
	ToolboxCompositionQuantity.open(this.closest('.util-quick-inline'));
});

/**
 * Le réglage des saisons de vente dans la grille (lot r27, planche r27, question 1, option B) : allumé
 * par l'interrupteur « Régler la saisonnalité » (`CompositionUi::getSeasonSwitch()`), il dessine la saison
 * de chaque espèce en barre sur son rang (`draw()`, retirée hors du réglage).
 * Cinq gestes, tous au glisser :
 * - la poignée de début étire ou raccourcit l'intervalle par son début ;
 * - la poignée de fin, par sa fin ;
 * - une poignée tirée au-delà de l'autre bout retire l'intervalle (r30, #93), y compris celui d'une
 *   seule semaine, dont chaque poignée étire vers l'extérieur et retire vers l'intérieur. Au bord de
 *   la grille, l'autre bout est hors de la piste : une poignée tirée au-delà de la première ou de la
 *   dernière semaine, sur les noms ou les sommes, y passe aussi (r72, `getWeek()`) ;
 * - le corps de la barre la déplace, sans sortir de la saison (`data-season-first`, `data-season-last`) ;
 * - un glisser sur des cases hors de la saison en ouvre un nouvel intervalle (question 2, option A).
 * Le geste se trace en pointillé sur le rang, puis part au lâcher : la saison entière, en intervalles,
 * que le serveur fusionne quand ils se touchent (`CompositionSpecies::windowWeeks`, `merge`). Un
 * intervalle retiré disparaît du tracé tant que la poignée reste au-delà, et revient si elle repasse
 * en deçà avant le lâcher : le tracé se recalcule à chaque case depuis la saison d'avant le geste.
 * Le dernier intervalle retiré laisse une saison vide, qui part au serveur comme telle : l'espèce
 * reste cultivée et n'entre plus dans aucun panier. Un clic sans glisser ne change rien, ni un tracé
 * revenu sur sa case de départ avant le lâcher (r72) : il poserait une semaine isolée, que le serveur
 * refuse (`CompositionSpecies::windowWeeks`, `short`).
 *
 * L'état est celui du navigateur, et ne part pas au serveur : une réponse qui rend de nouveau
 * l'interrupteur (`renderSwitch()`) ou la grille (`renderGrid()`) le reprend d'ici.
 *
 * Le même rang, seul et réglage allumé d'office, sert à « Ajouter une espèce » (r59,
 * `CompositionUi::getSeasonField()`) : sa saison y part avec le formulaire (`write()`).
 *
 * Au toucher, le geste ne s'arme qu'après un appui tenu, comme le tracé des semaines
 * (`ToolboxCompositionWeeks`) : un doigt qui glisse tout de suite fait défiler la grille.
 */
class ToolboxCompositionSeason {

	static active = false;
	static touchDelay = 200;
	static drag = null;
	static suppressClick = false;
	static observer = null;

	static renderSwitch(node) {

		if(this.active) {
			node.switchOn();
		}

	}

	// Seule une grille que la structure peut écrire (`data-editable`) se règle : l'interrupteur n'est
	// rendu que pour elle.
	//
	// La barre n'est pas dans la page que le serveur rend (chantier Performances, P13) : elle se dessine
	// ici, rang par rang, quand le réglage s'allume, et se retire quand il s'éteint. Pendant le réglage,
	// une réponse qui rend de nouveau un rang, son contenu ou sa saison (`data-season`) le fait
	// redessiner par l'observateur. Il est appelé après toutes les instructions de la réponse, alors
	// qu'un `onrender` dans le rang tournerait à l'insertion du contenu, avant que la même réponse ne
	// pose la nouvelle saison (`CompositionUi::getSeasoned()`, contenu puis attributs).
	static renderGrid(grid) {

		const mode = (this.active && grid.dataset.editable !== undefined);

		grid.classList.toggle('toolbox-composition-season-mode', mode);

		if(this.observer !== null) {
			this.observer.disconnect();
			this.observer = null;
		}

		if(mode === false) {
			grid.qsa('.toolbox-composition-season-solid', span => span.remove());
			return;
		}

		grid.qsa('tr[data-season]', row => this.draw(row));

		this.observer = new MutationObserver(mutations => this.redraw(mutations));
		this.observer.observe(grid, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-season']});

	}

	// Les rangs que les mutations `mutations` ont rendus de nouveau. Celles qui touchent une case sont
	// les siennes, barre ou tracé, et ne redessinent rien.
	static redraw(mutations) {

		const rows = new Set();

		mutations.forEach(mutation => {

			if(mutation.target.matches('tr[data-season]')) {
				rows.add(mutation.target);
			}

			if(mutation.type === 'childList' && mutation.target.matches('td') === false) {
				mutation.addedNodes.forEach(node => {
					if(node instanceof Element) {
						if(node.matches('tr[data-season]')) {
							rows.add(node);
						}
						node.qsa('tr[data-season]', row => rows.add(row));
					}
				});
			}

		});

		rows.forEach(row => {
			if(row.isConnected) {
				this.draw(row);
			}
		});

	}

	// La barre enregistrée du rang `row`, un morceau par case de sa saison, et une poignée à chaque bout
	// d'intervalle. Un intervalle écrit après un autre qu'il chevauche lui reprend ses cases.
	static draw(row) {

		row.qsa('.toolbox-composition-season-solid', span => span.remove());

		const pieces = {};
		const intervals = this.read(row);
		const wraps = this.wraps(row, intervals);
		const last = this.getLastWeek(row);

		intervals.forEach(([start, stop]) => {
			for(let week = start; week <= stop; week++) {
				pieces[week] = {start: (week === start && !(wraps && week === 1)), end: (week === stop && !(wraps && week === last))};
			}
		});

		row.qsa('td[data-week]', cell => {

			const piece = pieces[cell.dataset.week];

			if(piece === undefined) {
				return;
			}

			const span = document.createElement('span');
			span.className = 'toolbox-composition-season toolbox-composition-season-solid'+ (piece.start ? ' toolbox-composition-season-start' : '') + (piece.end ? ' toolbox-composition-season-end' : '');

			if(piece.start) {
				span.append(this.createHandle('toolbox-composition-season-handle toolbox-composition-season-handle-start'));
			}

			if(piece.end) {
				span.append(this.createHandle('toolbox-composition-season-handle'));
			}

			cell.append(span);

		});

	}

	// Une saison qui passe le nouvel an est stockée en deux intervalles, l'un ouvert en semaine 1, l'autre
	// fermé sur la dernière semaine de la grille (`[[1,15],[38,52]]`), le schéma ne connaissant pas
	// d'intervalle qui traverse l'année. Elle se lit pourtant comme une seule saison : ses bords en
	// semaine 1 et en fin d'année ne sont pas des bouts, ils ne portent ni arrondi ni poignée, et seuls
	// ses deux bouts réels (15 et 38) se règlent. Les gestes sur ces bouts écrivent toujours la même
	// paire d'intervalles. Une saison sur toute l'année (`[[1,52]]`) n'est qu'un intervalle et garde
	// ses deux poignées (r54).
	static wraps(row, intervals) {

		if(intervals.length < 2) {
			return false;
		}

		const starts = intervals.map(([start]) => start);
		const stops = intervals.map(([, stop]) => stop);

		return (Math.min(...starts) === 1 && Math.max(...stops) === this.getLastWeek(row));

	}

	static getLastWeek(row) {

		const cells = row.querySelectorAll('td[data-week]');

		return parseInt(cells[cells.length - 1].dataset.week);

	}

	static createHandle(className) {

		const handle = document.createElement('span');
		handle.className = className;

		return handle;

	}

	static toggle(node) {

		node.toggleSwitch();

		this.active = node.classList.contains('field-switch-on');

		const grid = document.getElementById('toolbox-composition-grid');

		if(grid !== null) {
			this.renderGrid(grid);
		}

	}

	// « 14-28,38-44 » en `[[14, 28], [38, 44]]` ; une saison vide rend une liste vide.
	static read(row) {

		if(row.dataset.season === '') {
			return [];
		}

		return row.dataset.season.split(',').map(interval => interval.split('-').map(week => parseInt(week)));

	}

	static merge(intervals) {

		const merged = [];

		[...intervals]
			.sort((interval1, interval2) => interval1[0] - interval2[0])
			.forEach(([start, stop]) => {

				const last = merged[merged.length - 1];

				if(last !== undefined && start <= last[1] + 1) {
					last[1] = Math.max(last[1], stop);
				} else {
					merged.push([start, stop]);
				}

			});

		return merged;

	}

	static start(cell, event) {

		this.suppressClick = false;

		if(event.button !== undefined && event.button !== 0) {
			return;
		}

		const row = cell.firstParent('tr');
		const grid = row.firstParent('.toolbox-composition-grid');
		const week = parseInt(cell.dataset.week);
		const intervals = this.read(row);
		const index = intervals.findIndex(([start, stop]) => week >= start && week <= stop);

		let mode;

		if(event.target.closest('.toolbox-composition-season-handle-start') !== null) {
			mode = 'start';
		} else if(event.target.closest('.toolbox-composition-season-handle') !== null) {
			mode = 'end';
		} else if(index !== -1) {
			mode = 'move';
		} else {
			mode = 'create';
		}

		const touch = (event.pointerType !== undefined && event.pointerType !== 'mouse');
		const cells = row.querySelectorAll('td[data-week]');

		this.drag = {
			grid: grid,
			row: row,
			cells: [...cells].map(node => {
				const rect = node.getBoundingClientRect();
				return [rect.left, rect.right, parseInt(node.dataset.week)];
			}),
			intervals: intervals,
			index: index,
			mode: mode,
			origin: week,
			current: week,
			preview: intervals,
			moved: false,
			armed: touch === false,
			timer: null
		};

		if(touch) {
			this.drag.timer = setTimeout(() => {
				if(this.drag !== null) {
					this.drag.armed = true;
					this.drag.timer = null;
				}
			}, this.touchDelay);
		}

	}

	static move(event) {

		const drag = this.drag;

		if(drag === null || drag.armed === false) {
			return;
		}

		event.preventDefault();

		const week = this.getWeek(drag, event);

		if(week === null || week === drag.current) {
			return;
		}

		drag.current = week;
		drag.moved = true;
		drag.preview = this.compute(drag, week);

		this.preview(drag.row, drag.preview);

	}

	// La semaine sous le pointeur, lue sur l'abscisse seule, contre les cases du rang mesurées au début du
	// geste (`start()`, qui n'a encore rien écrit). Pas par `elementFromPoint()` : la poignée tenue et sa
	// zone de prise débordent sur les cases voisines, et la semaine lue restait celle de la poignée tant
	// que le pointeur ne les avait pas quittées, ce qui à 360 px, cases de 11 px sous une zone de 23 px,
	// voulait dire jamais quand la colonne des noms était à côté. Un doigt qui dérive sur le rang voisin
	// reste aussi sur le sien.
	//
	// Une poignée qui sort de la piste par un bord vaut la semaine d'à côté, 0 ou la dernière + 1, que
	// `compute()` lit comme « au-delà de l'autre bout » (r72, le Haricot de la 7 en `[[1,1],[21,52]]`) :
	// collé au bord, un intervalle d'une semaine n'avait aucune case de l'autre côté, et dans une saison
	// qui passe le nouvel an la poignée de fin y est la seule. Hors de la piste, un tracé ou un
	// déplacement ne change rien.
	static getWeek(drag, event) {

		const x = event.clientX;
		const cell = drag.cells.find(([left, right]) => x >= left && x < right);

		if(cell !== undefined) {
			return cell[2];
		}

		if(drag.mode !== 'start' && drag.mode !== 'end') {
			return null;
		}

		if(x < drag.cells[0][0]) {
			return parseInt(drag.grid.dataset.seasonFirst) - 1;
		}

		if(x >= drag.cells[drag.cells.length - 1][1]) {
			return parseInt(drag.grid.dataset.seasonLast) + 1;
		}

		return null;

	}

	// La saison que le geste dessine quand le pointeur est sur `week`. Une poignée tirée au-delà de
	// l'autre bout retire l'intervalle ; posée sur l'autre bout, elle lui laisse cette seule semaine,
	// que le serveur refuse comme tout morceau de moins de trois semaines (r72, étape `short` de
	// `CompositionSpecies::properties()`) : le message s'affiche et la barre revient à la saison
	// enregistrée.
	// Le calcul part toujours de `drag.intervals`, la saison d'avant le geste : l'intervalle retiré
	// revient dès que la poignée repasse en deçà.
	static compute(drag, week) {

		const intervals = drag.intervals.map(interval => [...interval]);

		switch(drag.mode) {

			case 'create' :
				if(week !== drag.origin) {
					intervals.push([Math.min(drag.origin, week), Math.max(drag.origin, week)]);
				}
				break;

			// Hors de la piste du côté qui étire, la poignée s'arrête au bord (`getWeek()`).
			case 'start' :
				if(week > intervals[drag.index][1]) {
					intervals.splice(drag.index, 1);
				} else {
					intervals[drag.index][0] = Math.max(week, parseInt(drag.grid.dataset.seasonFirst));
				}
				break;

			case 'end' :
				if(week < intervals[drag.index][0]) {
					intervals.splice(drag.index, 1);
				} else {
					intervals[drag.index][1] = Math.min(week, parseInt(drag.grid.dataset.seasonLast));
				}
				break;

			case 'move' : {

				const [start, stop] = intervals[drag.index];
				const first = parseInt(drag.grid.dataset.seasonFirst);
				const last = parseInt(drag.grid.dataset.seasonLast);
				const delta = Math.min(Math.max(week - drag.origin, first - start), last - stop);

				intervals[drag.index] = [start + delta, stop + delta];
				break;

			}

		}

		return this.merge(intervals);

	}

	// Le tracé en cours, case par case comme la barre enregistrée, dont le rang cache les morceaux le
	// temps du geste (`toolbox-composition-season-dragging`).
	static preview(row, intervals) {

		row.classList.add('toolbox-composition-season-dragging');
		row.qsa('.toolbox-composition-season-preview', span => span.remove());

		const wraps = this.wraps(row, intervals);
		const last = this.getLastWeek(row);

		intervals.forEach(([start, stop]) => {

			row.qsa('td[data-week]', cell => {

				const week = parseInt(cell.dataset.week);

				if(week < start || week > stop) {
					return;
				}

				const span = document.createElement('span');
				span.classList.add('toolbox-composition-season', 'toolbox-composition-season-preview');

				if(week === start && !(wraps && week === 1)) {
					span.classList.add('toolbox-composition-season-start');
				}

				if(week === stop && !(wraps && week === last)) {
					span.classList.add('toolbox-composition-season-end');
				}

				cell.append(span);

			});

		});

	}

	static end() {

		const drag = this.drag;

		if(drag === null) {
			return;
		}

		this.drag = null;

		if(drag.timer !== null) {
			clearTimeout(drag.timer);
		}

		this.suppressClick = drag.moved;

		if(drag.moved === false) {
			return;
		}

		if(JSON.stringify(drag.preview) === JSON.stringify(drag.intervals)) {
			this.cancel(drag.row);
			return;
		}

		if(drag.grid.dataset.seasonField !== undefined) {
			this.write(drag.grid, drag.row, drag.preview);
		} else {
			this.save(drag.grid, drag.row, drag.preview);
		}

	}

	static cancel(row) {

		row.classList.remove('toolbox-composition-season-dragging');
		row.qsa('.toolbox-composition-season-preview', span => span.remove());

	}

	// Le rang de « Ajouter une espèce » (r59, `CompositionUi::getSeasonField()`) n'a pas d'espèce
	// écrite à qui envoyer sa saison, et celui de « Régler une espèce » (r61) l'enregistre avec les
	// autres réglages : elle s'écrit dans les champs de son formulaire, sous les noms que `save()`
	// poste, et part avec lui. Le rang garde la saison tracée, que le geste suivant relit.
	static write(grid, row, intervals) {

		row.dataset.season = intervals.map(([start, stop]) => start +'-'+ stop).join(',');

		const inputs = grid.qs('.toolbox-composition-season-inputs');

		inputs.innerHTML = '';

		intervals.forEach(([start, stop], position) => {
			[start, stop].forEach((week, bound) => {

				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'windowWeeks['+ position +']['+ bound +']';
				input.value = week;

				inputs.append(input);

			});
		});

		this.cancel(row);
		this.draw(row);

	}

	// La réponse remplace le contenu du rang (`CompositionUi::getSeasoned()`), tracé compris, et garde
	// le `<tr>` : il quitte le geste après elle, qu'elle réussisse ou qu'un refus le rende à sa saison
	// enregistrée. Une saison vide ne poste aucun `windowWeeks[…]`, que la page lit comme une liste
	// vide (`POST('windowWeeks', 'array')`).
	static save(grid, row, intervals) {

		const body = new URLSearchParams({
			id: row.dataset.species,
			structure: grid.dataset.structure,
			formula: grid.dataset.formula
		});

		intervals.forEach(([start, stop], position) => {
			body.append('windowWeeks['+ position +'][0]', start);
			body.append('windowWeeks['+ position +'][1]', stop);
		});

		return new Ajax.Query(row)
			.url('/toolbox/compositionSpecies:doUpdateSeason')
			.method('post')
			.body(body)
			.fetch()
			.then(() => this.cancel(row), () => this.cancel(row));

	}

}

// Le libellé actionne l'interrupteur comme un `<label>` le ferait d'un champ : l'interrupteur est un
// lien (`util\TextUi::switch()`), qu'aucun `<label for>` ne vise.
document.delegateEventListener('click', '.toolbox-composition-season-control', function(e) {

	e.preventDefault();

	ToolboxCompositionSeason.toggle(this.qs('#toolbox-composition-season-switch'));

});

document.delegateEventListener('pointerdown', '.toolbox-composition-season-mode tr[data-season] > td[data-week]', function(e) {
	ToolboxCompositionSeason.start(this, e);
});

document.addEventListener('pointermove', e => ToolboxCompositionSeason.move(e));
document.addEventListener('pointerup', () => ToolboxCompositionSeason.end());
document.addEventListener('pointercancel', () => {

	const drag = ToolboxCompositionSeason.drag;

	ToolboxCompositionSeason.drag = null;

	if(drag !== null) {

		if(drag.timer !== null) {
			clearTimeout(drag.timer);
		}

		ToolboxCompositionSeason.cancel(drag.row);

	}

});

// ⚠️ `passive: false`, comme pour le tracé des semaines : un écouteur `touchmove` passif ne retiendrait
// pas le défilement de la grille une fois le geste armé.
document.addEventListener('touchmove', e => {
	if(ToolboxCompositionSeason.drag !== null && ToolboxCompositionSeason.drag.armed) {
		e.preventDefault();
	}
}, {passive: false});

// En capture : le clic qui suit un glisser ne doit rien ouvrir sous le pointeur lâché.
document.addEventListener('click', e => {

	if(ToolboxCompositionSeason.suppressClick === false) {
		return;
	}

	ToolboxCompositionSeason.suppressClick = false;

	if(e.target instanceof Element && e.target.closest('.toolbox-composition-grid') !== null) {
		e.preventDefault();
		e.stopPropagation();
	}

}, true);

/**
 * Le produit vendu d'un légume, sur la page « Composition de <produit> » (r39c, piste C) : le texte
 * du produit proposé ouvre le menu des produits du légume (`CompositionSaleUi::getProduct()`), et
 * une entrée choisie remplace ce texte et la valeur du champ caché `product[<espèce>]`, sans requête.
 * « Préparer la composition » envoie le tout.
 *
 * Le menu ouvert a quitté le formulaire (`Lime.Dropdown.open()` le déplace sous `<body>`) : l'entrée
 * retrouve sa ligne par l'espèce qu'elle porte (`data-composition-pick`), jamais par ses parents. Le clic qui
 * choisit referme le menu, comme tout clic hors d'un `data-dropdown-keep`.
 */
class ToolboxCompositionPick {

	static choose(item) {

		const toggle = qs('.toolbox-composition-pick .dropdown-toggle[data-species="'+ item.dataset.compositionPick +'"]');

		if(toggle === null) {
			return;
		}

		toggle.firstParent('.toolbox-composition-pick-product').qs('input[type="hidden"]').value = item.dataset.product;
		toggle.textContent = item.textContent;

		item.parentElement.qsa('.dropdown-item', node => node.classList.toggle('selected', node === item));

	}

}

document.delegateEventListener('click', '[data-composition-pick]', function() {
	ToolboxCompositionPick.choose(this);
});
