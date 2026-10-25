/*
 * La saisie des récoltes (écran des récoltes du nouveau planning) : à l'ordinateur, le tiroir et la
 * grille ; au tactile, la feuille à pavé (`PlanningHarvestSheet`).
 *
 * Tout passe par des délégués posés une fois sur le document : la feuille reste chargée en navigation
 * ajax, et les lignes sont remplacées à chaque saisie, donc rien ne s'accroche à un nœud.
 *
 * Une ligne ou une part porte aussi l'attribut, pour le clavier seul : un clic n'ouvre la saisie que sur un
 * bouton ou une quantité (retour 170).
 */
document.delegateEventListener('click', '#planning-harvest-list [data-planning-harvest-open]:not(.util-table-row, .planning-harvest-row-sub)', function(e) {

	e.preventDefault();

	PlanningHarvest.open(this);

});

document.delegateEventListener('click', '#planning-harvest-list [data-planning-harvest-close]', function(e) {

	e.preventDefault();

	PlanningHarvest.close(this.closest('.util-table-row'));

});

/*
 * Un clic hors du tiroir ouvert le referme (retour 194), et dans la grille il ne fait rien d'autre : le jour
 * ouvert reste, aucune autre saisie ne s'ouvre, c'est le clic suivant qui agit. Hors de la grille, un onglet
 * ou le menu agissent aussi. Écouté en capture sur `window`, avant les délégués du document et `MainBellows`.
 * L'appui retenu écarte une sélection commencée dans le tiroir et lâchée dehors, dont le clic tombe sur
 * l'ancêtre commun.
 */
window.addEventListener('pointerdown', e => PlanningHarvest.down(e), true);
window.addEventListener('click', e => PlanningHarvest.outside(e), true);

document.delegateEventListener('keydown', '#planning-harvest-list .planning-harvest-drawer', function(e) {

	if(e.key === 'Escape' && e.target.closest('.planning-duration') === null) {
		e.preventDefault();
		PlanningHarvest.escape(this.closest('.util-table-row'));
	}

});

// Une frappe arrêtée rend le focus au document, et Tab le pose sur un lien de la ligne, le nom de la série :
// l'Échap suivant n'atteint alors ni le tiroir ni la ligne qui a le curseur, qui ont chacun le leur, plus
// haut et plus bas. Écouté en capture, avant `PlanningDuration`, pour laisser la saisie encore ouverte se
// refermer seule. Un panneau ouvert par-dessus (`panel-open`, `lime.js`) prend cet Échap à son `keyup` : le
// tiroir dessous reste ouvert.
document.addEventListener('keydown', e => {

	if(
		e.key !== 'Escape' ||
		document.body.classList.contains('panel-open') ||
		qs('.planning-duration-tiled > .planning-time-panel.is-open') !== null
	) {
		return;
	}

	const drawer = qs('#planning-harvest-list .planning-harvest-drawer');

	if(drawer === null) {
		return;
	}

	const row = drawer.closest('.util-table-row');

	if(
		e.target === document.body ||
		(row.contains(e.target) && drawer.contains(e.target) === false && e.target.matches('[data-planning-harvest-open]') === false)
	) {
		PlanningHarvest.escape(row);
	}

}, true);

// Sur une ligne ou une part qui a le curseur : Entrée ouvre la saisie du jour, les flèches changent de ligne.
document.delegateEventListener('keydown', '#planning-harvest-list :is(.util-table-row, .planning-harvest-row-sub)[data-planning-harvest-open]', function(e) {

	if(e.target !== this) {
		return;
	}

	switch(e.key) {

		case 'Enter' :
			e.preventDefault();
			PlanningHarvest.open(this);
			break;

		case 'ArrowDown' :
		case 'ArrowUp' :
			e.preventDefault();
			PlanningHarvest.move(this, e.key === 'ArrowDown' ? 1 : -1);
			break;

		case 'Escape' :
			e.preventDefault();
			PlanningHarvest.escape(this.closest('.util-table-row'));
			break;

	}

});

// Une part est un rang dans le rang de sa culture : `MainBellows` ne voit que les rangs du tableau. Ses plis
// suivent donc ceux de la ligne, et une bande de part ouvre le pli de sa colonne dans la ligne. La grande
// pastille du jour suit aussi le pli ouvert (retour 181).
document.delegateEventListener('bellowsOpen', '#planning-harvest-list .util-table-head > [data-date]', function() {

	PlanningHarvest.followBellows(this);

});

// Les chevrons du titre d'un pli ouvrent le jour que le serveur y a désigné, comme un clic sur sa bande. Le
// chevron est dans le pli qu'on referme : le délégué de `MainBellows`, qui lit le même clic ensuite, le
// rouvrirait aussitôt, d'où l'arrêt du clic une fois servi.
document.delegateEventListener('click', '#planning-harvest-list [data-planning-harvest-step]', function(e) {

	const item = this.closest('.util-table-head').qs(':scope > [data-date="'+ this.dataset.planningHarvestStep +'"]');

	if(item !== null) {
		e.stopImmediatePropagation();
		MainBellows.open(item);
	}

});

document.delegateEventListener('click', '#planning-harvest-list .planning-harvest-row-sub > .util-bellows-item:not(.util-bellows-open, .util-bellows-inactive)', function() {

	const part = this.parentNode;

	MainBellows.open(part.parentNode.children[Array.from(part.children).indexOf(this)]);

});

// Le couple choisi règle l'unité de la saisie, celle de son intervention de la semaine.
document.delegateEventListener('change', '[data-planning-harvest-units] :is(select, input[type="radio"])', function() {

	PlanningHarvestChoice.change(this.closest('[data-planning-harvest-units]'));

});

document.delegateEventListener('change', 'select[data-planning-harvest-crate]', function() {

	if(PlanningHarvestCrate.reopen(this)) {
		return;
	}

	const sheet = this.closest('#planning-harvest-sheet');

	PlanningHarvestCrate.task(this);

	if(sheet !== null) {
		PlanningHarvestSheet.choose(sheet);
	} else {
		PlanningHarvestCrate.prefill(this.form);
		PlanningHarvestCrate.update(this.form);
		PlanningHarvestDrawer.check(this.form);
	}

});

// À la feuille, le bouton dit ce qui sera écrit, caisses converties en kilos : le poids le récrit aussi.
document.delegateEventListener('input', 'form :is(input[name="harvestMore"], input[name="harvestDay"], input[name^="harvestOther["], input[name="crateWeight"])', function() {

	const sheet = this.closest('#planning-harvest-sheet');

	if(sheet !== null) {
		PlanningHarvestSheet.update(sheet);
	} else {
		PlanningHarvestCrate.update(this.form);
		PlanningHarvestDrawer.check(this.form);
	}

});

document.delegateEventListener('click', '[data-planning-crate-edit]', function(e) {

	e.preventDefault();

	const crate = this.closest('[data-planning-crate]');
	const field = crate.qs('.planning-harvest-crate-weight');

	this.classList.add('hide');
	field.classList.remove('hide');

	// On ouvre le champ pour taper un autre poids : la frappe remplace l'ancien au lieu de s'y ajouter.
	const input = field.qs('input');
	input.focus();
	input.select();

});

document.delegateEventListener('input', 'input[name="crateWeight"]', function() {

	PlanningHarvestCrate.label(this.closest('[data-planning-crate]'));

});

// Le jour ouvert à l'écran suit `MainBellows` sans aller-retour : « Nouvelle récolte » le reprend pour que
// sa réponse redessine les lignes dans la même colonne.
document.delegateEventListener('bellowsOpen', '#planning-harvest-list .util-table-head > [data-date]', function() {

	const date = this.dataset.date;

	qsa('#planning-harvest-list [data-planning-harvest-create]', link => link.setAttribute('href', link.getAttribute('href').setArgument('open', date)));

});

/*
 * Le champ « Intervention » de « Nouvelle intervention » (`TaskCreateUi::getActionField()`) :
 * l'action choisie, ou la croix qui le vide, que l'autocomplétion émet sur le champ lui-même.
 */
document.delegateEventListener('autocompleteSelect', 'input[data-planning-task-action]', function(e) {

	PlanningTaskAction.select(this.form, e.detail);

});

/*
 * Les champs d'autocomplétion des panneaux qui portent `data-planning-task-escape`. `Lime.Panel` se ferme sur le
 * `keyup` d'Échap, à la remontée sur le document (`lime.js`) : ce délégué, en capture, passe avant lui et peut l'arrêter.
 */
document.delegateEventListener('keyup', 'input[data-planning-task-escape]', function(e) {

	PlanningTaskAction.escape(this, e);

});

/*
 * « Espèce ou série » de « Nouvelle intervention », qui garde plusieurs choix.
 */
document.delegateEventListener('autocompleteBeforeQuery', 'input[data-planning-task-exclude]', function(e) {

	PlanningTaskAction.exclude(this, e.detail.body);
	PlanningTaskAction.more(this, e.detail.body);

});

document.delegateEventListener('autocompleteSource', 'input[data-planning-task-exclude]', function() {

	PlanningTaskAction.listenMore(this);

});

/*
 * Une section ouverte par « Voir les N autres » le reste pour le texte tapé : la frappe suivante est une autre
 * recherche, qui retrouve ses coupes.
 */
document.delegateEventListener('input', 'input[data-planning-task-exclude]', function() {

	delete this.dataset.planningTaskMoreOpen;
	delete this.dataset.planningTaskMoreScroll;

});

class PlanningTaskAction {

	/*
	 * Une action se choisit sous une catégorie, qui part avec elle.
	 */
	static select(form, value) {

		form.qs('input[name="category"]').value = value.category ?? '';

		form.dataset.planningTaskActionFqn = value.fqn ?? '';

		if(value.value === '') {
			form.dataset.planningTaskActionCulture = '';
		} else {
			form.dataset.planningTaskActionCulture = (value.culture === true) ? '1' : '0';
		}

		PlanningTaskAction.toggle(form);
		PlanningTaskAction.fields(form, value);

		PlanningTaskAction.accent(form, value);

		// Une récolte cache « Partagé », et la variété des cultures qu'il couvrait revient.
		form.qs('input[data-planning-task-choice]', input => PlanningTaskChoice.update(input));

		// Une récolte exige une espèce (`Task::plant.action`) : le refus d'« Espèce ou série » dépend de l'action.
		PlanningTaskCreateAlert.hide(form.qs('input[data-planning-task-action]'));
		form.qs('input[data-planning-task-exclude]', input => PlanningTaskCreateAlert.hide(input));

		form.qs('[data-planning-task-create-harvest]', fieldset => {
			fieldset.dataset.planningTaskCreateHarvest = (value.harvest === true) ? '1' : '0';
			PlanningTaskCreateHarvest.update(form);
		});

	}

	/*
	 * L'action choisie se reconnaît à sa pastille, qui se remplit, et à sa couleur, que prend le point du champ.
	 */
	static accent(form, value) {

		form.qsa('[data-planning-task-create-shortcut]', node => {
			node.classList.toggle('is-active', JSON.parse(node.dataset.planningTaskCreateShortcut).value === value.value);
		});

		form.qs('input[data-planning-task-action]').closest('.input-group').qs('.input-group-addon', addon => {

			addon.classList.toggle('planning-accent', (value.color ?? null) !== null);

			if(value.color) {
				addon.style.setProperty('--accent', value.color);
			} else {
				addon.style.removeProperty('--accent');
			}

		});

	}

	/*
	 * Ce que l'action montre ou cache sans retour au serveur, comme dans l'ancien formulaire (`series/task.js`,
	 * `task-write-action-change`) : le calibre pour une récolte, la fertilisation et le traitement pour leur action ;
	 * le produit, la fertilisation et le traitement pour une action de culture seulement (`TaskCreateUi::getFields()`) ;
	 * « Partagé » jamais pour une récolte (`TaskCreateUi::getSharedFields()`). Une action hors culture refuse la
	 * culture choisie (`TaskCreateLib::prepare()`), et cache avec elle la variété et « Partagé », qui la précisent ;
	 * sans action, rien n'est encore refusé. Un `fieldset` caché est aussi désactivé, et ne poste rien.
	 */
	static toggle(form) {

		const fqn = form.dataset.planningTaskActionFqn;

		form.qs('[data-wrapper="harvestSize"]', node => node.classList.toggle('hide', fqn !== 'recolte'));
		form.qs('[data-wrapper="fertilizer"]', node => node.classList.toggle('hide', fqn !== 'fertilisation'));
		form.qs('[data-wrapper="treatment"]', node => node.classList.toggle('hide', fqn !== 'traitement'));

		const culture = form.dataset.planningTaskActionCulture;

		const shown = {
			culture: (culture === '1'),
			variety: (culture !== '0'),
			shared: (fqn !== 'recolte' && culture !== '0')
		};

		Object.entries(shown).forEach(([name, visible]) => {

			form.qs('[data-planning-task-create-'+ name +']', fieldset => {
				fieldset.classList.toggle('hide', visible === false);
				fieldset.disabled = (visible === false);
			});

		});

	}

	/*
	 * Le produit ou les intrants (retour 256), « Méthodes de travail » et « Matériel nécessaire » (retour 255)
	 * suivent l'action, redessinés par la route de l'ancien formulaire (`series/task:getFields`) ; la croix les vide.
	 */
	static fields(form, value) {

		const methods = form.qs('[data-ref="methods"]');

		// Un choix d'action peut suivre le précédent avant sa réponse, et les réponses n'arrivent pas forcément
		// dans l'ordre : seule celle du dernier choix (ou la croix) remplit les groupes. Chaque requête a son verrou,
		// sinon la seconde serait refusée (`LOCK_FAILED`) et l'action choisie montrerait les champs de la précédente.
		const sequence = String(Number(methods.dataset.sequence ?? 0) + 1);
		methods.dataset.sequence = sequence;

		if(value.value === '') {

			form.ref('products', wrapper => wrapper.renderInner(''));
			form.ref('methods', wrapper => wrapper.renderInner(''));
			form.ref('tools', wrapper => wrapper.renderInner(''));
			return;

		}

		new Ajax.Query(form)
			.lockAs('fields-'+ sequence)
			.url('/series/task:getFields')
			.body({
				structure: methods.dataset.structure,
				action: value.value
			})
			.fetch()
			.then((json) => {

				if(methods.dataset.sequence !== sequence) {
					return;
				}

				form.ref('products', wrapper => wrapper.renderInner(json.products));
				form.ref('methods', wrapper => wrapper.renderInner(json.methods));
				form.ref('tools', wrapper => wrapper.renderInner(json.tools));

			});

	}

	/*
	 * Échap, liste ouverte, ne ferme que la liste : la saisie reste, et c'est le suivant qui ferme le panneau.
	 * Elle sert tout champ qui porte `data-planning-task-escape`.
	 */
	static escape(input, e) {

		if(e.key !== 'Escape' || AutocompleteField.hasDropdown(input) === false) {
			return;
		}

		AutocompleteField.remove(input);
		e.stopPropagation();

	}

	/*
	 * Ce qui est déjà choisi part avec la recherche, qui ne le propose plus (`taskDay:query`) : une pastille
	 * retirée revient à la recherche suivante.
	 */
	static exclude(input, body) {

		qsa('#'+ input.dataset.autocompleteItems +' input[type="hidden"]', hidden => body.append('exclude[]', hidden.value));

	}

	/*
	 * Les sections que « Voir les N autres » a ouvertes partent sans coupe (`TaskCreateLib::readMore()`).
	 */
	static more(input, body) {

		PlanningTaskAction.getMoreOpen(input).forEach(section => body.append('more[]', section));

	}

	static getMoreOpen(input) {

		return (input.dataset.planningTaskMoreOpen ?? '').split(' ').filter(section => section !== '');

	}

	/*
	 * La liste vient d'être refaite. Le clic sur « Voir les N autres » relance la recherche sans fermer la liste :
	 * comme la rangée des saisons (`AutocompleteField.source()`), `mousedown` annulé garde le curseur dans le
	 * champ, et `click` arrêté n'atteint pas l'écoute du document qui fermerait la liste.
	 *
	 * Le socle refuse une recherche tant que la précédente est en vol, son champ étant verrouillé
	 * (`Ajax.Query.lock()`) : un clic pendant ce temps n'aurait rien relancé. Une réponse qui coupe encore une
	 * section ouverte relance donc la recherche, le verrou étant levé avant elle, et celle qui les rend entières
	 * reprend le défilement d'avant le clic, que le socle remet en haut.
	 */
	static listenMore(input) {

		const list = AutocompleteField.getDropdown(input).qs('ul.autocomplete-list');

		if(PlanningTaskAction.getMoreOpen(input).some(section => list.qs('[data-planning-task-more="'+ section +'"]') !== null)) {
			AutocompleteField.query(input);
			return;
		}

		if(input.dataset.planningTaskMoreScroll !== undefined) {
			list.scrollTop = parseInt(input.dataset.planningTaskMoreScroll);
			delete input.dataset.planningTaskMoreScroll;
		}

		list.qsa('[data-planning-task-more]', button => {

			button.addEventListener('mousedown', e => e.preventDefault());

			button.addEventListener('click', e => {

				e.stopPropagation();

				input.dataset.planningTaskMoreOpen = [...PlanningTaskAction.getMoreOpen(input), button.dataset.planningTaskMore].join(' ');
				input.dataset.planningTaskMoreScroll = list.scrollTop;

				AutocompleteField.query(input);

			});

		});

	}

}

/*
 * « Quand » de « Nouvelle intervention » (piste A de R-CreationQuand, `TaskCreateUi::getWhen()`) : un onglet par
 * semaine, et sous l'onglet choisi « Non défini » pour une intervention à faire et les sept jours. Un clic sur un jour le choisit
 * et écrit sa date dans le champ caché `day`, vide pour « Non défini ». Le jour choisi ne se retire pas d'un second
 * clic : « Non défini » est une pastille, qui se touche comme les autres. Faite, l'intervention ne prend pas un jour
 * à venir, que le serveur grise.
 */
document.delegateEventListener('click', '#panel-planning-task-create [data-planning-task-create-day]', function(e) {

	e.preventDefault();

	PlanningTaskCreateDay.select(this);

});

class PlanningTaskCreateDay {

	static select(day) {

		if(day.classList.contains('is-disabled')) {
			return;
		}

		const days = day.closest('.util-week-picker-days');
		const date = day.dataset.planningTaskCreateDay;

		days.qsa('[data-planning-task-create-day]', node => {

			const selected = (node.dataset.planningTaskCreateDay === date);

			node.classList.toggle('is-selected', selected);
			node.qs('.util-calendar', calendar => calendar.classList.toggle('util-calendar-selected', selected));

		});

		days.closest('form').qs('input[name="day"]').value = date;

		PlanningTaskCreateAlert.hide(days);

	}

}

/*
 * Une autre semaine sous « Quand », par sa pastille ou par le calendrier d'« Autre » (`FormUi::weekSelector()`), ou
 * un autre état. `taskDay:getWhen` rend la rangée de la semaine retenue, qui n'est plus une semaine à venir pour
 * Fait, la saison où « Espèce ou série » cherche et, pour une intervention faite, le temps des personnes de cette
 * semaine. La liste du calendrier peut avoir été déplacée hors du formulaire à l'ouverture : le formulaire se
 * cherche par le panneau.
 */
document.delegateEventListener('click', '#panel-planning-task-create [data-planning-task-create-week]', function(e) {

	e.preventDefault();

	PlanningTaskCreateWhen.select(this.dataset.planningTaskCreateWeek);

});

class PlanningTaskCreateWhen {

	static select(week) {

		Lime.Dropdown.purge();

		const form = qs('#panel-planning-task-create form');
		const when = form.qs('[data-planning-task-create-when]');

		const body = new FormData();
		body.append('id', form.elements['id'].value);
		body.append('week', week);
		body.append('status', form.elements['status'].value);

		const user = form.elements.namedItem('user');

		if(user !== null) {
			body.append('user', user.value);
		}

		new Ajax.Query(when)
			.url('/planning/taskDay:getWhen')
			.body(body)
			.fetch()
			.then((json) => {

				when.renderInner(json.when);

				PlanningTaskCreateAlert.hide(when);

				if(json.time !== undefined) {
					form.qs('[data-planning-task-create-time]', node => node.renderInner(json.time));
				}

				form.qsa('input[data-autocomplete-body]', input => {

					const autocompleteBody = JSON.parse(input.dataset.autocompleteBody);
					autocompleteBody.week = json.week;

					input.dataset.autocompleteBody = JSON.stringify(autocompleteBody);

				});

				form.qs('input[data-planning-task-exclude][data-autocomplete-head]', input => {
					input.dataset.autocompleteHead = json.season.head;
					input.dataset.autocompleteHeadSelected = json.season.selected;
				});

			});

	}

}

/*
 * « Fait | À faire » en tête du panneau, coché sur le bouton qui l'a ouvert, se change encore (retours sur
 * la piste F) : les champs de l'état coché se montrent et ceux de l'autre se cachent sans poster
 * (`TaskCreateUi::getFor()`), et « Quand » se redessine, ses semaines, ses jours et le jour pris d'office
 * dépendant de l'état.
 */
document.delegateEventListener('change', '#panel-planning-task-create input[name="status"]', function() {

	PlanningTaskCreateStatus.show(this.form, this.value);

});

class PlanningTaskCreateStatus {

	static show(form, status) {

		form.qsa('[data-planning-task-create-for]', fieldset => {

			const shown = (fieldset.dataset.planningTaskCreateFor === status);

			fieldset.classList.toggle('hide', shown === false);
			fieldset.disabled = (shown === false);

		});

		form.qs('[data-planning-task-create-asterisk]').classList.toggle('hide', status !== 'done');

		PlanningTaskCreateHarvest.update(form);

		PlanningTaskCreateWhen.select(form.elements['when'].value);

	}

}

/*
 * Les pastilles des actions fréquentes, sous « Intervention » (`TaskCreateUi::getShortcuts()`) : une pastille remplit
 * le champ comme un choix dans la liste, et c'est `PlanningTaskAction.select()` qui la marque choisie.
 */
document.delegateEventListener('click', '#panel-planning-task-create [data-planning-task-create-shortcut]', function(e) {

	e.preventDefault();

	AutocompleteField.apply(
		this.closest('form').qs('input[data-planning-task-action]'),
		JSON.parse(this.dataset.planningTaskCreateShortcut)
	);

});

/*
 * Un refus du serveur reste affiché jusqu'à l'envoi suivant (`Lime.Alert.showErrors()`) : dans « Nouvelle
 * intervention », il disparaît dès que l'on change le champ qu'il désigne (retour 258), sans quoi il contredit
 * l'écran. `hideErrors()` ne
 * nettoie que les descendants du nœud qu'on lui donne : le groupe garde sa marque, et le formulaire la sienne
 * tant qu'un autre refus reste affiché.
 */
class PlanningTaskCreateAlert {

	static hide(node) {

		const form = node.closest('form');
		const group = node.closest('.form-group');

		Lime.Alert.hideErrors(group);
		group.classList.remove('form-error-wrapper');

		this.mark(form);

	}

	// Le formulaire garde sa marque, et le bouton son halo, tant qu'un refus reste à l'écran : celui d'un bloc
	// caché et désactivé (une quantité, les champs de l'autre statut) se garde pour son retour, sans marquer.
	static mark(form) {

		const shown = Array.from(form.querySelectorAll('.form-error-wrapper')).some(node => node.closest('fieldset[disabled]') === null);

		form.classList.toggle('form-error', shown);

	}

}

/*
 * La récolte d'une récolte faite (retour 258), demandée à `taskDay:harvest` à chaque choix d'« Espèce ou série » :
 * la quantité et le stock d'une série ou d'une espèce seule, et pour plusieurs séries de la même espèce le bloc de
 * « Récolter » (DG-5), avec la clé et les parts. Des espèces différentes n'ont pas d'unité commune : la réponse est
 * vide. Caché, son `fieldset` est désactivé et ne poste rien.
 */
document.delegateEventListener('autocompleteUpdate', '#panel-planning-task-create input[data-planning-task-exclude]', function() {

	PlanningTaskCreateAlert.hide(this);
	PlanningTaskCreateHarvest.update(this.form);

});

// Le refus de la quantité (`harvestMore.negative`, `harvestUnit.crate`, poids de la caisse) tient à sa saisie.
document.delegateEventListener('input', '#panel-planning-task-create [data-planning-task-create-quantity] .form-group input', function() {
	PlanningTaskCreateAlert.hide(this);
});

document.delegateEventListener('change', '#panel-planning-task-create [data-planning-task-create-quantity] .form-group select', function() {
	PlanningTaskCreateAlert.hide(this);
});

class PlanningTaskCreateHarvest {

	static update(form) {

		const fieldset = form.qs('[data-planning-task-create-harvest]');

		if(fieldset === null) {
			return;
		}

		const input = form.qs('input[data-planning-task-exclude]');
		const values = Array.from(qsa('#'+ input.dataset.autocompleteItems +' input[type="hidden"]'), hidden => hidden.value).filter(value => value !== '');

		// La variété mesure la surface de chaque série, qui fonde la part : elle ne compte qu'à plusieurs séries.
		const variety = (values.length > 1) ? (form.elements.namedItem('variety')?.value ?? '') : '';

		const shown = (
			fieldset.dataset.planningTaskCreateHarvest === '1' &&
			form.elements['status'].value === 'done' &&
			values.length > 0
		);

		const quantity = fieldset.qs('[data-planning-task-create-quantity]');
		const choices = values.join(',') +'|'+ variety;
		const known = (quantity.dataset.choices === choices);

		// Des choix déjà répondus par un bloc vide, des espèces différentes, gardent le bloc caché.
		this.toggle(form, fieldset, shown && (known === false || quantity.dataset.empty === undefined));

		if(shown === false || known) {
			return;
		}

		// La quantité déjà tapée survit à une nouvelle réponse : un choix de plus ne la rend pas fausse.
		const typed = form.elements.namedItem('harvestMore')?.value ?? '';

		// Seule la réponse des derniers choix demandés remplit le bloc, comme pour les méthodes et le matériel.
		// Le refus d'une quantité part avec le bloc vidé : il ne vaut plus pour les nouveaux choix.
		quantity.dataset.choices = choices;
		quantity.renderInner('');

		PlanningTaskCreateAlert.mark(form);

		// `lock()` ne désactive que les boutons de son contexte, et le cadriciel n'a pas d'autre verrou :
		// l'envoi se verrouille ici le temps de la requête, sans quoi la récolte partirait sans sa quantité.
		const submits = form.qsa('[type="submit"]');
		submits.forEach(node => node.setAttribute('disabled', 'disabled'));

		const body = new FormData();
		body.append('id', form.elements['id'].value);
		body.append('week', form.elements['week'].value);
		body.append('variety', variety);
		values.forEach(value => body.append('choices[]', value));

		let answered = false;

		new Ajax.Query(fieldset)
			.lockAs('harvest-'+ choices)
			.url('/planning/taskDay:harvest')
			.body(body)
			.fetch()
			.then((json) => {

				if(quantity.dataset.choices !== choices) {
					return;
				}

				answered = true;

				quantity.renderInner(json.harvest);
				quantity.toggleAttribute('data-empty', json.harvest === '');

				this.toggle(form, fieldset, json.harvest !== '');

				form.qs('[data-planning-task-create-quantity] input[name="harvestMore"]', field => field.value = typed);

				quantity.qs('[data-planning-batch-harvest]', block => PlanningTaskBatchHarvest.update(block));

			})
			.finally(() => {

				// Un choix fait entre-temps a relancé la requête : c'est la sienne qui rendra l'envoi.
				if(quantity.dataset.choices !== choices) {
					return;
				}

				submits.forEach(node => node.removeAttribute('disabled'));

				// Une requête refusée laisse le bloc vide : caché, il ne poste rien, et le prochain choix la relance.
				if(answered === false) {
					delete quantity.dataset.choices;
					this.toggle(form, fieldset, false);
				}

			});

	}

	static toggle(form, fieldset, shown) {

		fieldset.classList.toggle('hide', shown === false);
		fieldset.disabled = (shown === false);

		PlanningTaskCreateAlert.mark(form);

	}

}

document.delegateEventListener('change', '#panel-planning-task-create :is([name="variety"], [name="harvestSize"])', function() {
	PlanningHarvestStock.reload(this.form);
	PlanningTaskCreateHarvest.update(this.form);
});

/*
 * Variété, calibre et « Partagé » de l'ancien formulaire (retour 256) suivent les choix d'« Espèce ou série »,
 * dont le champ à plusieurs choix émet `autocompleteUpdate`.
 */
document.delegateEventListener('autocompleteUpdate', 'input[data-planning-task-choice]', function() {

	PlanningTaskChoice.update(this);

});

// Une culture dite « Partagé » n'apporte pas de variété (`TaskCreateLib::getNewForChoices()`).
document.delegateEventListener('change', '[data-planning-task-create-shared] input[type="radio"]', function() {

	this.form.qs('input[data-planning-task-choice]', input => PlanningTaskChoice.update(input));

});

class PlanningTaskChoice {

	static update(input) {

		const form = input.form;
		const variety = form.qs('[data-ref="variety"]');

		const values = Array.from(qsa('#'+ input.dataset.autocompleteItems +' input[type="hidden"]'), hidden => hidden.value).filter(value => value !== '');

		// « Partagé » tel qu'il part à l'envoi : caché pour une récolte, son `fieldset` désactivé ne poste rien.
		const shared = Array.from(new FormData(form)).filter(([name]) => name.startsWith('shared['));

		const choices = values.join(',') +'|'+ shared.map(([name, value]) => name +'='+ value).join(',');

		// La saisie émet aussi `autocompleteUpdate` : seuls des choix qui changent redessinent les champs, et seule la
		// réponse des derniers choix les remplit, comme pour la quantité.
		if(variety.dataset.choices === choices) {
			return;
		}

		variety.dataset.choices = choices;

		if(values.length === 0) {

			['variety', 'size', 'shared'].forEach(ref => form.ref(ref, wrapper => wrapper.renderInner('')));
			return;

		}

		// « Partagé » se garde d'une réponse à l'autre pour les cultures qui restent choisies, même caché, et la variété
		// quand elle est de nouveau proposée : une réponse sans variété (tout « Partagé », une espèce hors série) n'efface
		// pas celle qui était choisie avant elle.
		const checked = Array.from(form.qsa('[data-ref="shared"] input[type="radio"]:checked'), radio => [radio.name, radio.value]);

		// Lue sur le champ et non par `FormData`, qui ignore la variété cachée par une action hors culture.
		if(variety.qs('[name="variety"]:not([type="hidden"])') !== null) {
			variety.dataset.chosen = form.elements.namedItem('variety')?.value ?? '';
		}

		const varietyChosen = variety.dataset.chosen ?? '';

		const body = new FormData();
		body.append('id', form.elements['id'].value);
		body.append('week', form.elements['week'].value);
		body.append('action', new FormData(form).get('action') ?? '');
		body.append('category', new FormData(form).get('category') ?? '');
		values.forEach(value => body.append('cultivation[]', value));
		shared.forEach(([name, value]) => body.append(name, value));

		new Ajax.Query(variety)
			.lockAs('choice-'+ choices)
			.url('/planning/taskDay:getCultivationFields')
			.body(body)
			.fetch()
			.then((json) => {

				if(variety.dataset.choices !== choices) {
					return;
				}

				form.ref('variety', wrapper => wrapper.renderInner(json.variety));
				form.ref('size', wrapper => wrapper.renderInner(json.size));
				form.ref('shared', wrapper => wrapper.renderInner(json.shared));

				checked.forEach(([name, value]) => form.qs('[data-ref="shared"] input[name="'+ name +'"][value="'+ value +'"]', radio => radio.checked = true));

				if(varietyChosen !== '') {
					form.qs('[data-ref="variety"] input[name="variety"][value="'+ varietyChosen +'"]:not([disabled])', radio => radio.checked = true);
					form.qs('[data-ref="variety"] select[name="variety"] option[value="'+ varietyChosen +'"]:not([disabled])', option => option.selected = true);
				}

				// Une action choisie pendant la requête a pu changer ce qui se montre : la réponse suit l'action d'avant.
				if(form.dataset.planningTaskActionFqn !== undefined) {
					PlanningTaskAction.toggle(form);
				}

			});

	}

}

/*
 * Les champs à liste des panneaux du planning : « Intervention » de « Nouvelle intervention », « Série » de
 * « Nouvelle récolte ».
 */
class PlanningAutocomplete {

	/*
	 * Un clic dans le champ ouvre la liste entière, comme le menu qu'elle remplace. Le socle n'ouvre la liste
	 * qu'au `focusin`, qui arme aussi sa fermeture au clic extérieur : le champ qui a déjà le curseur, sans
	 * liste ouverte, le perd donc au `mousedown`, et le même clic le lui rend par le chemin du socle.
	 *
	 * Une réponse arrivée après la frappe est celle d'un texte qui n'est plus dans le champ, et la recherche
	 * du texte tapé n'est pas partie : le socle la refuse tant que la précédente est en vol, son contexte, le
	 * champ, étant verrouillé (`Ajax.Query.lock()`). Elle part donc à l'arrivée de la réponse périmée, sans
	 * quoi la liste resterait sur les Fréquentes et Entrée choisirait l'une d'elles.
	 */
	static start(input) {

		AutocompleteField.start(input);

		input.addEventListener('mousedown', () => {

			if(document.activeElement === input && AutocompleteField.hasDropdown(input) === false) {
				input.blur();
			}

		});

		input.addEventListener('autocompleteSource', e => {

			if(e.detail.query !== input.value && document.activeElement === input) {
				AutocompleteField.query(input);
			}

		});

	}

}

document.delegateEventListener('autocompleteSelect', '#planning-harvest-create-cultivation', function(e) {

	let request = document.location.href;

	if(e.detail.value) {
		request = request.setArgument('cultivation', e.detail.value);
	} else {
		request = request.removeArgument('cultivation');
	}

	new Ajax.Query(this)
		.method('get')
		.url(request)
		.fetch();

});

/*
 * Le formulaire du tiroir se centre sous le bouton qui l'a ouvert, et la pointe le désigne (`planning.css`).
 * Ouvert au clavier depuis la ligne, ou une fois son pli refermé, le bouton ne désigne plus rien : la pointe
 * reste au milieu de la colonne du jour. Tout se remesure quand la largeur de l'écran change, et quand un
 * autre pli s'ouvre, une fois son glissement fini.
 */
class PlanningHarvestDrawer {

	static opener = null;

	/*
	 * À l'ouverture, le tiroir de la dernière culture tombe sous les barres fixes du bas, sous 768 de large
	 * ou 600 de haut (constat de T, point 3) : la page défile jusqu'à montrer son bas, sans faire passer la
	 * ligne qui l'a ouvert sous ce qui borne le haut de l'écran (`PlanningDurationStep.getScreen()`). Cette
	 * ligne est la part que le tiroir suit, ou sinon la culture qui le porte : le haut d'une culture à parts
	 * peut être loin au-dessus de l'écran, et la page remonterait (relecteur de T, point 1). C'est le
	 * bas du formulaire qui compte : à partir de 768, il se pose par-dessus les lignes suivantes et le tiroir
	 * n'a plus de hauteur (`planning.css`). La pointe et le défilement se mesurent d'abord, ensemble, puis
	 * s'écrivent. Remesuré ensuite (`replace()`), le tiroir ne fait plus défiler : l'écran qui change de
	 * taille ne déplace pas la page sous les doigts.
	 */
	static start(drawer) {

		// La liste des unités (`HarvestEntryUi::getDrawer()`) laisse ce que le geste permet, comme à la feuille, et un
		// tiroir rouvert en caisses retire les kilos du jour du champ.
		const form = drawer.qs(':scope > .planning-harvest-drawer-form');
		form.qs('select[data-planning-harvest-crate]', select => {
			PlanningHarvestCrate.allow(select, form.elements['mode'].value);
			PlanningHarvestCrate.prefill(form);
		});

		this.check(form);

		const placement = this.measure(drawer);

		if(placement === null) {
			return;
		}

		this.observer.observe(drawer.qs(':scope > .planning-harvest-drawer-form'));

		const part = drawer.previousElementSibling;
		const line = (part !== null && part.matches('.planning-harvest-open')) ? part : drawer.closest('.util-table-row');

		const end = drawer.qs(':scope > .planning-harvest-drawer-form').getBoundingClientRect().bottom;
		const lineBounds = line.getBoundingClientRect();
		const {top, bottom} = PlanningDurationStep.getScreen(drawer, lineBounds);

		this.write(drawer, placement);

		if(end > bottom) {
			window.scrollBy(0, Math.min(end - bottom, lineBounds.top - top));
		}

	}

	/*
	 * Le bouton s'éteint sur une saisie sans quantité ni temps, que le serveur refuse (`.empty`,
	 * `HarvestWeekLib::writeEntry()`), comme à la feuille (`PlanningHarvestSheet.update()`) : un ajout vaut dès
	 * qu'il porte une quantité ou un temps, un remplacement dès qu'une quantité est tapée, zéro compris, dans
	 * chacune de ses unités (`isOthersTyped()`).
	 */
	static check(form) {

		form.qs('.planning-harvest-entry-go button[type="submit"]', submit => {

			// Des fonctions et non des valeurs : un tiroir ne porte que le champ de son geste, `harvestMore` ou `harvestDay`.
			const valid = {
				add: () => {
					const number = PlanningHarvestCrate.read(form.elements['harvestMore'].value);
					let time = 0;
					form.qsa('.planning-harvest-time', wrapper => time += PlanningHarvestTime.get(wrapper));
					return (number > 0 || time > 0);
				},
				replace: () => (form.elements['harvestDay'].value !== '' && this.isOthersTyped(form))
			}[form.elements['mode'].value];

			submit.disabled = (valid() === false);

		});

	}

	/*
	 * Chaque rangée d'une autre unité du remplacement (`HarvestEntryUi::getOthers()`) porte une quantité, zéro
	 * compris : vide, elle se refuserait au serveur. Celles de la feuille hors du remplacement sont éteintes.
	 */
	static isOthersTyped(form) {

		let typed = true;

		form.qsa('input[name^="harvestOther["]:enabled', field => typed = (typed && field.value !== ''));

		return typed;

	}

	static place(drawer) {

		const placement = this.measure(drawer);

		if(placement !== null) {
			this.write(drawer, placement);
		}

	}

	/*
	 * La boîte du tiroir, la colonne de son jour et le décalage de la pointe vers le bouton qui l'a ouvert,
	 * `null` quand le tiroir n'est plus dans la page.
	 */
	static measure(drawer) {

		const day = drawer.qs(':scope > .planning-harvest-drawer-day');

		if(drawer.isConnected === false || day === null) {
			return null;
		}

		// Le bouton tient la pointe quand il est dans la colonne du jour que le tiroir saisit, le pli ouvert.
		const opener = this.opener;
		const anchored = (
			opener !== null &&
			opener.isConnected &&
			drawer.parentNode.contains(opener) &&
			opener.closest('.util-bellows-item')?.matches('.util-bellows-open') === true
		);

		const box = drawer.getBoundingClientRect();
		const column = day.getBoundingClientRect().left;
		const shift = anchored ? (opener.getBoundingClientRect().left + opener.getBoundingClientRect().width / 2 - column) : 0;

		return {box, column, shift};

	}

	static write(drawer, {box, column, shift}) {

		drawer.style.setProperty('--planning-harvest-drawer-center', (column - box.left + shift) +'px');
		drawer.style.setProperty('--planning-harvest-drawer-shift', shift +'px');
		drawer.style.setProperty('--planning-harvest-drawer-width', box.width +'px');

	}

	static replace() {
		qsa('#planning-harvest-list .planning-harvest-drawer', drawer => this.place(drawer));
	}

	/*
	 * À partir de 768, le formulaire se pose par-dessus les lignes suivantes : sous les dernières, il dépasse
	 * du bas de la liste, et ce qui la porte le couperait, « Ajouter » compris, d'autant plus vite que chaque
	 * personne y a ses pas (lot BW). La liste s'allonge alors de ce qui dépasse, plus un écart
	 * (`--planning-harvest-drawer-room`, `planning.css`). Le formulaire grandit après coup (« Ajouter une
	 * personne », un refus) et disparaît sans prévenir, remplacé par la réponse ou par un autre tiroir : la
	 * longueur se remesure à chaque changement de sa taille, qu'un formulaire retiré de la page signale aussi.
	 */
	static observer = new ResizeObserver(() => this.room());

	static room() {

		const list = qs('#planning-harvest-list');

		if(list === null) {
			return;
		}

		// Le bas de la liste sans la longueur déjà ajoutée, seule à régler son rembourrage du bas.
		const bottom = list.getBoundingClientRect().bottom - parseFloat(getComputedStyle(list).paddingBottom);
		const end = Math.max(bottom, ...[...list.qsa('.planning-harvest-drawer > .planning-harvest-drawer-form')].map(form => form.getBoundingClientRect().bottom));

		if(end > bottom) {
			list.style.setProperty('--planning-harvest-drawer-room', 'calc('+ (end - bottom) +'px + var(--space-md))');
		} else {
			list.style.removeProperty('--planning-harvest-drawer-room');
		}

	}

}

window.addEventListener('resize', () => requestAnimationFrame(() => PlanningHarvestDrawer.replace()));

document.delegateEventListener('bellowsOpen', '#planning-harvest-list .util-table-head > [data-date]', function() {

	setTimeout(() => PlanningHarvestDrawer.replace(), MainBellows.DURATION);

});

/*
 * La sélection des Récoltes (DG-2) : la case de chaque série sans part et de chaque part d'une série (FD), la
 * case d'une série à parts qui coche ou décoche ses variétés et calibres, et la vignette de l'espèce qui coche ou
 * décoche toutes ses lignes, « Non spécifiée » toujours à part (`getFieldsGrouped()`), nourrissent la barre
 * « Récolter » (`HarvestWeekUi::getBatch()`). Comme dans la Semaine
 * (`PlanningTaskSelection`), le serveur ne rend jamais une case cochée : la sélection vit ici, en valeurs de
 * couples (`HarvestWeekLib::getBatchValue()`), et une ligne redessinée par une saisie la relit en arrivant
 * (`restore()`, par son `onrender`).
 */
document.delegateEventListener('input', '#planning-harvest-list .planning-harvest-select [name="batch[]"]', function() {

	PlanningHarvestSelection.toggle(this);

});

document.delegateEventListener('input', '#planning-harvest-list .planning-harvest-select [data-planning-harvest-series]', function() {

	PlanningHarvestSelection.toggleSeries(this);

});

document.delegateEventListener('click', '#planning-harvest-list [data-planning-harvest-select-plant]', function(e) {

	e.preventDefault();

	PlanningHarvestSelection.togglePlant(this.dataset.planningHarvestSelectPlant);

});

document.delegateEventListener('bellowsOpen', '#planning-harvest-list .util-table-head > [data-date]', function() {

	PlanningHarvestSelection.updateOpen();

});

document.addEventListener('navigation.wakeup', () => PlanningHarvestSelection.reset());

document.addEventListener('entryWrite', e => {

	const cell = (e.target instanceof Element && e.target.closest('#planning-harvest-list') !== null);

	if(cell) {
		PlanningHarvestEntry.prepareFocus(e.target);
	}

	PlanningHarvestEntry.refresh(e.detail, cell);

});
document.addEventListener('entryCancel', e => PlanningHarvestEntry.refresh(e.detail, false));

/*
 * Une récolte saisie ou annulée depuis le champ du menu (`entry.js`) redessine la liste à l'écran, sur les
 * seules espèces que le serveur juge touchées (`harvestWeek:entry`). Saisie dans la case d'une culture (`cell`,
 * HN-B), elle ne redessine que sa ligne. Une saisie d'une autre structure, ou sans culture, ne change rien à
 * la liste.
 */
class PlanningHarvestEntry {

	static running = false;
	static pending = new Map();
	static focus = null;

	/*
	 * Une case écrite au clavier passe la main au champ de la case suivante du même jour, pour enchaîner les
	 * lignes sans la souris ; la dernière rend la main, sa case devenue une quantité sans champ. La réponse
	 * redessine la ligne, et parfois la suivante avec l'espèce : la case se retrouve donc par sa ligne et son
	 * jour (`getCellKey()`), comme
	 * `HarvestWeekUi::getWrittenSelectors()` la désigne, pas par son nœud.
	 * Au doigt (`UtilOmnibox.isFullscreen()`), la main ne passe pas : un champ qui prend le focus sans geste
	 * fait monter le clavier, qui cache la grille qu'on vient de lire.
	 */
	static prepareFocus(box) {

		const cell = box.closest('.planning-harvest-cell[data-date]');

		if(UtilOmnibox.isFullscreen() || cell === null || box.contains(document.activeElement) === false) {
			return;
		}

		const cells = this.getCells().filter(node => node.dataset.date === cell.dataset.date);
		const next = cells.slice(cells.indexOf(cell) + 1).find(node => node.checkVisibility());

		this.focus = (next === undefined) ? null : this.getCellKey(next);

	}

	static getCells() {
		return Array.from(qsa('#planning-harvest-list .util-bellows-open > .util-bellows-panel > .planning-harvest-cell[data-date]'));
	}

	static getCellKey(cell) {
		return cell.closest(':is(.util-table-row, .planning-harvest-row-sub)').id +' '+ cell.dataset.date;
	}

	static restoreFocus() {

		const key = this.focus;
		this.focus = null;

		// Le champ écrit a quitté la page avec sa ligne : un focus ailleurs est un geste de l'utilisateur pendant
		// l'aller-retour, qu'on ne lui reprend pas.
		if(document.activeElement !== null && document.activeElement !== document.body) {
			return;
		}

		this.getCells()
			.find(cell => this.getCellKey(cell) === key)
			?.qs('.util-omnibox-input')
			.focus();

	}

	/*
	 * Une saisie puis son annulation peuvent se suivre dans l'aller-retour. La requête verrouille la liste
	 * (`LOCK_FAILED` sur la seconde), et deux réponses parallèles pourraient arriver dans le désordre : la
	 * suivante attend donc la fin de la précédente. Une réponse ne relit que les espèces voisines de sa
	 * culture : chaque culture en attente garde sa requête, une seule fois même saisie deux fois, et la plus
	 * large des deux quand l'une vient du menu.
	 */
	static refresh(detail, cell) {

		const list = qs('#planning-harvest-list');

		if(
			list === null ||
			list.dataset.structure !== String(detail.structure) ||
			detail.cultivation === null
		) {
			return;
		}

		if(this.running) {
			this.pending.set(detail.cultivation, cell && (this.pending.get(detail.cultivation) ?? true));
			return;
		}

		const parameters = new URLSearchParams({
			id: list.dataset.structure,
			week: list.dataset.week,
			cultivation: detail.cultivation
		});

		if(cell) {
			parameters.set('cell', 1);
		}

		const open = PlanningHarvest.getOpen();

		if(open !== null) {
			parameters.set('open', open);
		}

		this.running = true;

		new Ajax.Query(list)
			.method('get')
			.url('/planning/harvestWeek:entry?'+ parameters.toString())
			.fetch()
			.finally(() => {

				this.running = false;

				// Une culture en attente n'a plus rien à redessiner quand la liste a quitté l'écran ou la structure.
				if(qs('#planning-harvest-list')?.dataset.structure !== String(detail.structure)) {
					this.pending.clear();
					this.focus = null;
					return;
				}

				const [next] = this.pending;

				if(next !== undefined) {
					const [cultivation, cellNext] = next;
					this.pending.delete(cultivation);
					this.refresh({structure: detail.structure, cultivation: cultivation}, cellNext);
				} else if(this.focus !== null) {
					this.restoreFocus();
				}

			});

	}

}

class PlanningHarvestSelection {

	static selection = new Set();

	static queued = false;

	static getFields() {
		return Array.from(qsa('#planning-harvest-list .planning-harvest-select [name="batch[]"]'));
	}

	/*
	 * Les cases que cochent celle d'une série et la vignette d'une espèce : « Non spécifiée » se coche à part, car
	 * « Récolter » la refuse avec une variété de sa série, et le geste groupé doit mener à un envoi possible.
	 */
	static getFieldsGrouped() {
		return this.getFields().filter(field => field.dataset.unspecified === undefined);
	}

	static toggle(field) {

		if(field.checked) {
			this.selection.add(field.value);
		} else {
			this.selection.delete(field.value);
		}

		this.change();

	}

	/*
	 * La case d'une série à parts coche ou décoche ses variétés et ses calibres. Indéterminée, elle n'est pas
	 * cochée (`syncSeries()`) : le clic la coche, et eux avec elle.
	 */
	static toggleSeries(master) {

		const fields = this.getFieldsGrouped().filter(field => field.dataset.cultivation === master.dataset.planningHarvestSeries);

		fields.forEach(field => {

			field.checked = master.checked;

			if(master.checked) {
				this.selection.add(field.value);
			} else {
				this.selection.delete(field.value);
			}

		});

		this.change();

	}

	/*
	 * Les séries de l'espèce toutes cochées se décochent, sinon toutes se cochent, celles qui l'étaient déjà
	 * comprises. Toutes les lectures d'abord, puis toutes les écritures.
	 */
	static togglePlant(plant) {

		const fields = this.getFieldsGrouped().filter(field => field.dataset.plant === plant);
		const checked = (fields.every(field => field.checked) === false);

		fields.forEach(field => {

			field.checked = checked;

			if(checked) {
				this.selection.add(field.value);
			} else {
				this.selection.delete(field.value);
			}

		});

		this.change();

	}

	/*
	 * Les cases d'une même réponse se rendent ensemble, et la barre ne se recalcule qu'une fois après elles.
	 */
	static restore(field) {

		field.checked = this.selection.has(field.value);

		this.apply();

	}

	static apply() {

		if(this.queued) {
			return;
		}

		this.queued = true;

		queueMicrotask(() => {
			this.queued = false;
			this.change();
		});

	}

	/*
	 * L'état seul, à chaque navigation : les cases d'un écran neuf arrivent décochées.
	 */
	static reset() {

		this.selection.clear();

	}

	/*
	 * Le × de la barre et la réponse de « Récolter ».
	 */
	static clear() {

		this.selection.clear();

		this.getFields().forEach(field => field.checked = false);

		this.change();

	}

	static change() {

		// Une ligne qui a quitté l'écran sort de la sélection.
		const present = new Set(this.getFields().map(field => field.value));

		this.selection.forEach(value => {
			if(present.has(value) === false) {
				this.selection.delete(value);
			}
		});

		this.syncSeries();

		if(qs('#planning-harvest-batch') === null) {
			return;
		}

		Batch.changeSelection('#planning-harvest-batch', null, () => this.updateOpen());

		// `Batch` ne réécrit les identifiants et le compte que de la barre qu'il montre : masquée, elle garderait
		// ceux d'une sélection passée.
		qsa('#planning-harvest-batch.hide :is(.batch-ids, .batch-group-count)', node => node.innerHTML = '');

	}

	/*
	 * La case de chaque série à parts dit l'état de ses variétés et calibres, « Non spécifiée » à part : cochée quand
	 * tous le sont, indéterminée quand une partie seulement l'est, vide sinon. Toutes les lectures d'abord, puis
	 * toutes les écritures.
	 */
	static syncSeries() {

		const counts = new Map();

		this.getFieldsGrouped().forEach(field => {

			const count = counts.get(field.dataset.cultivation) ?? {total: 0, checked: 0};

			count.total++;
			count.checked += field.checked ? 1 : 0;

			counts.set(field.dataset.cultivation, count);

		});

		qsa('#planning-harvest-list [data-planning-harvest-series]', master => {

			const count = counts.get(master.dataset.planningHarvestSeries);

			master.checked = (count.checked === count.total);
			master.indeterminate = (count.checked > 0 && count.checked < count.total);

		});

	}

	/*
	 * « Récolter » propose le jour ouvert à l'écran (`PlanningHarvest.getOpen()`), que `MainBellows` a pu changer
	 * depuis le rendu, et la réponse redessine ses lignes dans la même colonne. Sans jour ouvert, l'attribut reste
	 * vide et le panneau prend le jour proposé (`harvestWeek:batchHarvest`).
	 */
	static updateOpen() {

		qs('#planning-harvest-batch [post-open]', node => node.setAttribute('post-open', PlanningHarvest.getOpen() ?? ''));

	}

}

class PlanningHarvest {

	/*
	 * Le nœud où a commencé le clic en cours (`outside()`).
	 */
	static pressed = null;

	/*
	 * Le jour ouvert à l'écran, le pli ouvert, que `MainBellows` a pu changer depuis le rendu. Chaque requête de
	 * la semaine le renvoie (`open`), et la réponse redessine ses lignes dans la même colonne. `null` quand aucun
	 * jour n'est ouvert : une semaine à venir, ou une semaine passée avant qu'on en ouvre un.
	 */
	static getOpen() {
		return qs('#planning-harvest-list .util-table-head > .util-bellows-open[data-date]')?.dataset.date ?? null;
	}

	/*
	 * La liste de `week` rendue dans une autre forme (saisie rapide, ventes et stock) se recharge entière : la réponse ne vise que
	 * la forme de la préférence (`planningHarvestWeekChange()`). Le pli ouvert et la hauteur de lecture ont pu
	 * changer depuis le rendu sans que l'adresse les suive : ils voyagent avec le rechargement.
	 */
	static align(week, quick, sales) {

		const list = qs('#planning-harvest-list[data-week="'+ week +'"]');

		if(list === null || (list.dataset.quick === String(quick) && list.dataset.sales === String(sales))) {
			return;
		}

		const open = this.getOpen();
		const scroll = window.scrollY;

		new Ajax.Navigation()
			.url((open === null) ? location.href : location.href.setArgument('open', open))
			.fetch()
			.then(() => window.scrollTo(0, scroll));

	}

	/*
	 * Au clavier, la ligne ouvre la saisie du jour proposé au rendu (`HarvestWeekUi::getRow()`) : si un pli
	 * s'est ouvert depuis, c'est lui qu'elle vise.
	 *
	 * Au tactile, la saisie s'ouvre dans la feuille à pavé : même route, avec `sheet=1`. Le critère est
	 * celui de `body[data-touch]`, que le CSS lit aussi pour donner au bouton la cible tactile du socle.
	 */
	static open(opener, url = opener.dataset.planningHarvestOpen) {

		PlanningHarvestDrawer.opener = opener;

		const open = this.getOpen();

		if(open !== null) {

			url = url.setArgument('open', open);

			if(opener.matches('.util-table-row, .planning-harvest-row-sub')) {
				url = url.setArgument('date', open);
			}

		}

		if(isTouch()) {
			url = url.setArgument('sheet', 1);
		}

		new Ajax.Query(opener)
			.method('get')
			.url(url)
			.fetch();

	}

	/*
	 * Fermer ne demande rien au serveur : sans son tiroir ni sa marque, la ligne est exactement celle
	 * que `HarvestWeekUi::getRow()` rend fermée.
	 */
	static close(row) {

		const opener = row.classList.contains('planning-harvest-open') ? row : row.qs('.planning-harvest-row-sub.planning-harvest-open');

		row.qsa('.planning-harvest-drawer', node => node.remove());
		row.qsa('.planning-harvest-open', node => node.classList.remove('planning-harvest-open'));
		row.classList.remove('planning-harvest-open');

		// Refermé par un clic ailleurs (`outside()`), la page ne doit pas défiler jusqu'à la ligne quittée.
		(opener ?? row).focus({preventScroll: true});

	}

	static down(e) {
		this.pressed = e.target;
	}

	/*
	 * Un clic au clavier (`detail` à 0) n'est pas un clic ailleurs : Entrée sur un bouton hors du tiroir agit.
	 * Un Ctrl+clic ou Cmd+clic sur un lien l'ouvre dans un autre onglet et laisse l'écran tel quel, tiroir
	 * compris, comme la navigation ajax le laisse passer (`ajax.js`). Ailleurs, sur un jour ou un « + » de récolte
	 * qui n'ont pas d'adresse, la touche ne change rien au geste, qui reste un clic ailleurs.
	 */
	static outside(e) {

		const pressed = this.pressed;

		this.pressed = null;

		const drawer = qs('#planning-harvest-list .planning-harvest-drawer');
		const opensTab = ((e.ctrlKey || e.metaKey) && e.target.closest('a[href]') !== null);

		if(drawer === null || e.detail === 0 || opensTab || this.contains(drawer, e.target) || this.contains(drawer, pressed)) {
			return;
		}

		this.close(drawer.closest('.util-table-row'));

		if(e.target.closest('#planning-harvest-list .planning-harvest-grid') !== null) {
			e.preventDefault();
			e.stopPropagation();
		}

	}

	/*
	 * Ouvert, le menu du jour du temps est posé sous `body` (`Lime.Dropdown.open()`) : il appartient au tiroir
	 * par son bouton (`list.button`), et le choix d'un jour n'est pas un clic ailleurs.
	 */
	static contains(drawer, node) {

		if(node === null) {
			return false;
		}

		const list = node.closest('.dropdown-list');

		return (drawer.contains(node) || (list !== null && list.button !== undefined && drawer.contains(list.button)));

	}

	/*
	 * Un appui sur la tuile du temps, qui ne prend pas le focus, le rend à la ligne qui porte `tabindex` :
	 * Échap y arrive donc aussi, et referme d'abord la saisie, puis le tiroir.
	 */
	static escape(row) {

		if(row.qs('.planning-duration-tiled > .planning-time-panel.is-open') !== null) {
			PlanningDuration.escapeAll();
		} else if(row.qs('.planning-harvest-drawer') !== null) {
			this.close(row);
		}

	}

	/*
	 * Les lignes et les parts se suivent dans l'ordre du document, une part après sa culture.
	 */
	static move(opener, step) {

		const openers = Array.from(qsa('#planning-harvest-list :is(.util-table-row, .planning-harvest-row-sub)[data-planning-harvest-open]'));
		const next = openers[openers.indexOf(opener) + step];

		if(next !== undefined) {
			next.focus();
		}

	}

	static followBellows(head) {

		const column = Array.from(head.parentNode.children).indexOf(head);

		qsa('#planning-harvest-list .planning-harvest-row-sub', part => {
			Array.from(part.children).forEach((cell, index) => cell.classList.toggle('util-bellows-open', index === column));
		});

		head.parentNode.qsa(':scope > [data-date] .util-calendar', calendar => {
			calendar.classList.toggle('util-calendar-lg', head.contains(calendar));
			calendar.classList.toggle('util-calendar-selected', head.contains(calendar));
		});

	}

}

/*
 * Le choix du couple d'une saisie (`HarvestEntryUi::getChoice()`) : chaque couple écrit dans l'unité de son
 * intervention de la semaine, que le conteneur porte par couple (`variété-calibre`, `0` pour aucun), et
 * `*` pour un couple qui n'en a pas encore. Le tiroir, le panneau et la feuille changent l'unité de leur
 * champ, et la feuille celle que dit son bouton.
 */
class PlanningHarvestChoice {

	static change(choice) {

		const form = choice.closest('form');
		const units = JSON.parse(choice.dataset.planningHarvestUnits);
		const key = this.getValue(form, 'variety') +'-'+ this.getValue(form, 'harvestSize');
		const unit = units[key] ?? units['*'];

		form.qsa('[data-wrapper~="harvestMore"] [data-planning-harvest-unit]', addon => addon.textContent = unit.label);
		const sheet = form.closest('#planning-harvest-sheet');

		form.qs('select[data-planning-harvest-crate]', select => PlanningHarvestCrate.unit(select, unit, key === '0-0', sheet?.dataset.mode ?? 'add'));

		if(sheet !== null) {
			PlanningHarvestSheet.unit(sheet, unit);
		} else {
			PlanningHarvestCrate.update(form);
		}

		PlanningHarvestStock.reload(form);

	}

	/*
	 * Un champ absent, ou laissé sur « Toutes » ou « Non spécifié », vaut `0` dans la clé.
	 */
	static getValue(form, name) {

		const field = form.elements[name];

		return (field === undefined || field.value === '') ? '0' : field.value;

	}

}

/*
 * « Ajouter à un stock » d'une saisie de récolte (`HarvestEntryUi::getStock()`, DG-4) : les stocks proposés
 * dépendent de la variété et de l'unité de la saisie, et `harvestWeek:stock` redessine le champ quand l'une
 * change. Le cadre porte celles qu'il a lues (`data-variety`, `data-unit`), et la demande en vol
 * (`data-pending`). Une seule part à la fois, la requête verrouillant son cadre (`Ajax.Query.lock()`) : un
 * changement fait pendant qu'elle vole se relit à sa réponse, contre le cadre neuf, et la dernière saisie
 * gagne. Une réponse qui ne redessine pas le cadre ne relance rien, une erreur qui dure ne bouclant pas.
 */
class PlanningHarvestStock {

	static reload(form) {

		form.qsa('[data-planning-harvest-stock]', field => {

			const variety = this.getVariety(form, field);
			const unit = this.getUnit(form, field);

			if(
				field.dataset.pending !== undefined ||
				variety +'|'+ unit === field.dataset.variety +'|'+ field.dataset.unit
			) {
				return;
			}

			field.dataset.pending = '1';

			new Ajax.Query(field)
				.url('/planning/harvestWeek:stock')
				.body({
					id: form.elements['id'].value,
					stockId: field.dataset.planningHarvestStock,
					cultivation: field.dataset.cultivation,
					plant: field.dataset.plant,
					variety: variety,
					harvestUnit: unit,
					titled: field.dataset.titled
				})
				.fetch()
				.then(() => field.isConnected === false, () => false)
				.then(replaced => {

					delete field.dataset.pending;

					if(replaced && form.isConnected) {
						this.reload(form);
					}

				});

		});

	}

	/*
	 * La variété telle qu'elle part à l'envoi : un champ éteint ne poste rien et laisse « Toutes variétés ». Une
	 * saisie sans champ de variété, la fiche ou « Récolter », garde celle de sa récolte.
	 */
	static getVariety(form, field) {

		const data = new FormData(form);

		if(data.has('variety')) {
			return data.get('variety');
		}

		return (form.elements['variety'] === undefined) ? field.dataset.variety : '';

	}

	/*
	 * L'unité de la saisie : celle d'une autre unité choisie, qui désigne son intervention ou se poste
	 * (`PlanningHarvestCrate.task()`), celle du champ « Unité » de la fiche, celle du couple choisi
	 * (`PlanningHarvestChoice`), celle que la liste des unités porte depuis son rendu, sinon celle de la
	 * récolte. Le cadre vient en dernier, pour une saisie dont l'unité ne change jamais : il porte l'unité du
	 * dernier stock lu, qui n'est plus celle de la saisie après un aller et retour vers une autre unité.
	 */
	static getUnit(form, field) {

		const select = form.qs('select[data-planning-harvest-crate]');
		const chosen = select?.selectedOptions[0];

		if(chosen?.dataset.planningHarvestStockUnit !== undefined) {
			return chosen.dataset.planningHarvestStockUnit;
		}

		const unit = new FormData(form).get('harvestUnit');

		if(unit !== null && unit !== '') {
			return unit;
		}

		const choice = form.qs('[data-planning-harvest-units]');

		if(choice !== null) {

			const units = JSON.parse(choice.dataset.planningHarvestUnits);

			return (units[PlanningHarvestChoice.getValue(form, 'variety') +'-'+ PlanningHarvestChoice.getValue(form, 'harvestSize')] ?? units['*']).value;

		}

		return select?.dataset.planningHarvestStockUnit ?? field.dataset.unit;

	}

	/*
	 * « Supprimer », un lien `data-ajax` qui ne poste pas son formulaire, emporte le stock choisi dans ses
	 * `post-*`. La copie se fait avant tout autre écouteur : celui de `data-ajax` (`ajax.js`) poste et arrête
	 * la propagation.
	 */
	static copy(link) {

		const data = new FormData(link.closest('form'));

		if(data.has('stock')) {
			link.setAttribute('post-stock', data.get('stock'));
			link.setAttribute('post-stock-remember', data.has('stockRemember') ? '1' : '0');
		} else {
			link.removeAttribute('post-stock');
			link.removeAttribute('post-stock-remember');
		}

	}

}

window.addEventListener('click', e => {

	const link = (e.target instanceof Element) ? e.target.closest('[data-planning-harvest-stock-copy]') : null;

	if(link !== null) {
		PlanningHarvestStock.copy(link);
	}

}, {capture: true});

// L'unité d'une intervention qui n'a encore rien récolté se choisit dans la récolte de la fiche.
document.delegateEventListener('change', '#planning-task-detail-intervention select[name="harvestUnit"]', function() {
	PlanningHarvestStock.reload(this.form);
});

/*
 * Au téléphone, la semaine des Récoltes passe d'un jour à l'autre d'un geste horizontal sur la grille (GU) :
 * vers la gauche le jour suivant, vers la droite le précédent, en sautant les jours à venir, et rien au bout de
 * la semaine. Le jour s'ouvre comme d'un toucher sur sa tête (`MainBellows.open()`), qui déplace aussi le
 * tiroir et la sélection.
 *
 * La grille laisse le défilement vertical à la page (`touch-action: pan-y pinch-zoom`) : l'axe se décide au
 * seuil de `PlanningPointer`, et un geste vertical rend la main au navigateur, qui le dit par `pointercancel`.
 * Le pointeur n'est pas capturé : le toucher d'une bande garde son clic. Au doigt, un geste qui a bougé
 * n'émet pas de clic, donc le lâcher n'ouvre rien d'autre.
 */
class PlanningHarvestSwipe {

	// Le déplacement horizontal, en `rem`, au-delà duquel le lâcher change de jour.
	static DISTANCE = 3;

	static active = null;

	static down(e) {

		this.active = null;

		if(
			e.pointerType !== 'touch' ||
			window.matchMedia('(max-width: 575px)').matches === false ||
			(e.target instanceof Element) === false ||
			qs('#planning-harvest-list .planning-harvest-drawer') !== null
		) {
			return;
		}

		const grid = e.target.closest('#planning-harvest-list .planning-harvest-grid-week');

		if(grid === null) {
			return;
		}

		this.active = {
			grid,
			pointer: e.pointerId,
			x: e.clientX,
			y: e.clientY,
			axis: null
		};

	}

	static move(e) {

		const swipe = this.active;

		if(swipe === null || e.pointerId !== swipe.pointer || swipe.axis !== null) {
			return;
		}

		const dx = e.clientX - swipe.x;
		const dy = e.clientY - swipe.y;

		if(Math.abs(dy) > PlanningPointer.THRESHOLD && Math.abs(dy) >= Math.abs(dx)) {
			this.active = null;
		} else if(Math.abs(dx) > PlanningPointer.THRESHOLD) {
			swipe.axis = 'x';
		}

	}

	static end(e) {

		const swipe = this.active;
		this.active = null;

		if(swipe === null || e.pointerId !== swipe.pointer || swipe.axis !== 'x') {
			return;
		}

		const dx = e.clientX - swipe.x;
		if(Math.abs(dx) < this.DISTANCE * rem()) {
			return;
		}

		const item = this.getNext(swipe.grid, (dx < 0) ? 1 : -1);

		if(item !== null) {
			MainBellows.open(item);
		}

	}

	static cancel() {
		this.active = null;
	}

	// La tête du jour à ouvrir dans le sens `step`, en sautant les jours à venir ; `null` au bout de la semaine, ou
	// quand aucun jour n'est ouvert, d'où l'on ne sait vers où glisser.
	static getNext(grid, step) {

		const items = Array.from(grid.qsa(':scope > .util-table-head > .util-bellows-item'));
		const open = items.findIndex(item => item.classList.contains('util-bellows-open'));

		if(open === -1) {
			return null;
		}

		for(let index = open + step; index >= 0 && index < items.length; index += step) {
			if(items[index].classList.contains('util-bellows-inactive') === false) {
				return items[index];
			}
		}

		return null;

	}

}

document.addEventListener('pointerdown', e => PlanningHarvestSwipe.down(e));
document.addEventListener('pointermove', e => PlanningHarvestSwipe.move(e));
document.addEventListener('pointerup', e => PlanningHarvestSwipe.end(e));
document.addEventListener('pointercancel', () => PlanningHarvestSwipe.cancel());

/*
 * Le geste d'une réglette au pointeur, celle de la journée (`PlanningTimeRule`). Chaque réglette se déclare
 * (`PlanningPointer.register()`) :
 * - `selector` : la réglette, cherchée depuis la cible de l'appui (`closest()`) ;
 * - `prevent` : annuler l'appui de la souris (`pointerdown`) ;
 * - `start(node, e)` : la saisie commence, à l'appui de la souris, ou quand le doigt a choisi ;
 * - `move(e)` : le pointeur se déplace pendant la saisie ;
 * - `end(e)` : le pointeur se lève ;
 * - `cancel()` : le navigateur reprend le geste (`pointercancel`).
 *
 * Au doigt, la réglette laisse le défilement vertical à la page (`touch-action: pan-y`) : la saisie ne
 * commence qu'à un geste horizontal ou à un toucher sans déplacement, et un geste vertical n'écrit rien. La
 * souris saisit dès l'appui.
 */
document.addEventListener('pointerdown', e => PlanningPointer.down(e));
document.addEventListener('pointermove', e => PlanningPointer.move(e));
document.addEventListener('pointerup', e => PlanningPointer.up(e));
document.addEventListener('pointercancel', () => PlanningPointer.cancel());

class PlanningPointer {

	/*
	 * - `pending` : un doigt posé, dont on ne sait pas encore s'il défile ou s'il saisit ;
	 * - `active` : la réglette en cours de saisie et sa déclaration.
	 */
	static handlers = [];
	static pending = null;
	static active = null;

	// Le déplacement, en pixels, au-delà duquel un doigt a choisi entre défiler et saisir.
	static THRESHOLD = 8;

	static register(handler) {
		this.handlers.push(handler);
	}

	static down(e) {

		if(e.button !== 0) {
			return;
		}

		for(const handler of this.handlers) {

			const node = e.target.closest(handler.selector);

			if(node === null) {
				continue;
			}

			if(e.pointerType === 'mouse') {

				if(handler.prevent) {
					e.preventDefault();
				}

				this.start(handler, node, e);

			} else {
				this.pending = {handler, node, x: e.clientX, y: e.clientY};
			}

			return;

		}

	}

	static start(handler, node, e) {

		// Un pointeur que le navigateur ne suit pas, simulé ou déjà levé, ne se capture pas : le geste suit
		// alors par les écouteurs du document, qui reçoivent les mêmes événements (comme `PlanningTaskSwipe`).
		try {
			node.setPointerCapture(e.pointerId);
		} catch(error) {
			if(error.name !== 'NotFoundError') {
				throw error;
			}
		}

		this.active = {handler, node};

		handler.start(node, e);

	}

	static move(e) {

		if(this.pending !== null) {

			const dx = Math.abs(e.clientX - this.pending.x);
			const dy = Math.abs(e.clientY - this.pending.y);

			if(dy > this.THRESHOLD && dy > dx) {
				this.pending = null;
			} else if(dx > this.THRESHOLD) {
				const {handler, node} = this.pending;
				this.pending = null;
				this.start(handler, node, e);
			}

			return;

		}

		if(this.active !== null) {
			this.active.handler.move(e);
		}

	}

	static up(e) {

		// Un doigt levé sans avoir bougé est un toucher : il pose la valeur là où il était. Le pointeur n'a plus
		// rien à capturer, et `setPointerCapture()` lève sur un pointeur que `pointerup` a déjà relâché.
		if(this.pending !== null) {
			const {handler, node} = this.pending;
			this.pending = null;
			handler.start(node, e);
			handler.end(e);
			return;
		}

		if(this.active === null) {
			return;
		}

		const {handler} = this.active;

		this.active = null;

		handler.end(e);

	}

	static cancel() {

		this.pending = null;

		if(this.active !== null) {
			const {handler} = this.active;
			this.active = null;
			handler.cancel();
		}

	}

}

/*
 * La saisie d'une durée derrière une tuile (`DurationUi::getTiled()`) : les ronds des heures et des minutes,
 * que seule la journée de travail garde (M2j). Toute saisie du temps d'une intervention a le champ par pas
 * (`PlanningDurationStep`, R-Retours-0810, 212).
 * Ses délégués sont posés une fois, ici ; l'écran déclare ce que devient la durée
 * (`PlanningDuration.register()`) :
 * - `selector` : le conteneur de la saisie (`.planning-duration`), auquel le geste remonte ;
 * - `bounded` : une durée ne dépasse pas une journée (`PlanningTask.MAX_TIME`) ;
 * - `change(duration, time)` : sur « Enregistrer » ou Entrée, quand la durée a changé ; rend la promesse de
 *   son envoi, et un refus du serveur rouvre alors la saisie sur la durée refusée, avec son message ;
 * - `discard(duration)`, facultatif : rend ce que la saisie montre sans l'avoir écrit, quand elle se ferme sans
 *   enregistrer ; une journée en horaires (`PlanningTimeRange`) n'a pas de durée à reprendre, mais ses champs.
 *
 * Une heure écrit l'heure ronde en gardant les minutes, des minutes complètent l'heure, la durée tapée
 * s'écrit, et la saisie reste ouverte : rien ne part avant « Enregistrer » ou Entrée (`save()`, planche
 * R-Retours-0810, retour 198). La fermer sans enregistrer la rend à la dernière durée écrite (`data-sent`,
 * `hide()`). La fiche redessine « Journée », qui se rouvre par sa clé (`data-duration`) sur la durée qu'elle
 * affichait (`draft`).
 */
document.delegateEventListener('click', '.planning-duration [data-planning-duration-open]', function(e) {

	e.preventDefault();

	PlanningDuration.toggle(this.closest('.planning-duration'));

});

document.delegateEventListener('click', '.planning-duration [data-planning-duration-close]', function(e) {

	e.preventDefault();

	PlanningDuration.dismiss();

});

document.delegateEventListener('click', '.planning-duration [data-planning-time-hour]', function(e) {

	e.preventDefault();

	PlanningDuration.chooseHour(this.closest('.planning-duration'), parseInt(this.dataset.planningTimeHour));

});

document.delegateEventListener('click', '.planning-duration [data-planning-time-minute]', function(e) {

	e.preventDefault();

	PlanningDuration.chooseMinute(this.closest('.planning-duration'), parseInt(this.dataset.planningTimeMinute));

});

document.delegateEventListener('click', '.planning-duration [data-planning-time-reset]', function(e) {

	e.preventDefault();

	PlanningDuration.choose(this.closest('.planning-duration'), 0);

});

document.delegateEventListener('click', '.planning-duration [data-planning-duration-title]', function(e) {

	e.preventDefault();

	PlanningDuration.type(this.closest('.planning-duration'));

});

document.delegateEventListener('click', '.planning-duration [data-planning-duration-save]', function(e) {

	e.preventDefault();

	PlanningDuration.save(this.closest('.planning-duration'));

});

/*
 * La durée tapée reprend le champ d'une durée du planning actuel (`timesheet.js`) : deux chiffres tapés
 * dans les heures passent aux minutes, et chaque case se borne à ce qu'une horloge accepte, les heures
 * à 99 quand la durée n'a pas la borne d'une journée.
 */
document.delegateEventListener('input', '.planning-duration .planning-time-panel-type input[name="timeAddHour"]', function() {

	const duration = this.closest('.planning-duration');

	PlanningDuration.bound(this, PlanningDuration.find(duration).bounded ? 23 : 99);

	if(this.value.length >= 2) {
		duration.qs('input[name="timeAddMinute"]').focus();
	}

});

document.delegateEventListener('input', '.planning-duration .planning-time-panel-type input[name="timeAddMinute"]', function() {
	PlanningDuration.bound(this, 59);
});

// Le focus qui quitte les deux cases écrit la durée tapée. Un rond ou des minutes, qui ne prennent pas le
// focus, le font quitter aussi, sans `relatedTarget`.
document.delegateEventListener('focusout', '.planning-duration .planning-time-panel-type', function(e) {

	if(e.relatedTarget === null || this.contains(e.relatedTarget) === false) {
		PlanningDuration.submitTyped(this.closest('.planning-duration'));
	}

});

// Entrée enregistre la durée tapée sans envoyer le formulaire qui loge la saisie. Échap la rend.
document.delegateEventListener('keydown', '.planning-duration', function(e) {

	switch(e.key) {

		case 'Enter' :
			if(e.target.closest('.planning-time-panel-type') !== null) {
				e.preventDefault();
				PlanningDuration.save(this);
			}
			break;

		case 'Escape' :
			e.preventDefault();
			PlanningDuration.escaped = true;
			PlanningDuration.escape(this);
			break;

	}

});

// Les ronds, les minutes et les pas ne prennent pas le focus : Échap arrive alors au document. Il retire aussi
// l'écran d'une fenêtre sans tuile, la bulle ou la carte du Jour, qu'aucun panneau ne porte.
document.addEventListener('keydown', e => {

	if(e.key === 'Escape' && e.target === document.body) {
		PlanningDuration.escaped = (qs('.planning-duration-tiled > .planning-time-panel.is-open, .planning-duration-step-window.is-open:not(.planning-duration-step-bare)') !== null);
		PlanningDuration.escapeAll();
	}

});

// `Lime.Panel` se ferme sur le `keyup` d'Échap (`lime.js`), que le `keydown` de la saisie ne peut pas annuler :
// l'Échap qui a refermé une saisie s'arrête avant lui, en capture, et la page qui la porte reste ouverte.
window.addEventListener('keydown', e => {

	if(e.key === 'Escape') {
		PlanningDuration.escaped = false;
	}

}, true);

window.addEventListener('keyup', e => {

	if(e.key === 'Escape' && PlanningDuration.escaped) {
		PlanningDuration.escaped = false;
		e.stopImmediatePropagation();
	}

}, true);

/*
 * Un appui hors de la saisie la referme avant le clic, pour qu'un appui sur une autre tuile ouvre la
 * sienne ; ce qui n'a pas été enregistré est abandonné (`hide()`). Une saisie dans le flux reste ouverte, la
 * refermer ferait remonter ce qu'on est en train de toucher. Le flux se lit sur le panneau.
 */
document.addEventListener('pointerdown', e => {

	if(e.target.closest('.planning-duration') === null) {
		PlanningDuration.close('.planning-duration-tiled', panel => getComputedStyle(panel).position !== 'static');
	}

});

document.delegateEventListener('animationend', '.planning-time-panel-title', function() {
	PlanningDuration.stopRolling(this);
});

class PlanningDuration {

	static consumers = [];

	/*
	 * La clé de la saisie restée ouverte, que la réponse de son écran redessine : elle s'y rouvre.
	 */
	static kept = null;

	/*
	 * La durée affichée et pas encore enregistrée de la saisie gardée, quand elle porte « Enregistrer » : la
	 * réponse d'une autre saisie de l'écran (une ligne de la fiche) la redessine sur la durée enregistrée, et
	 * elle s'y réécrit pour que rien ne se perde sans qu'on l'ait refermée.
	 */
	static draft = null;

	/*
	 * Un Échap vient de refermer une saisie : son `keyup` ne doit pas fermer le panneau qui la porte.
	 */
	static escaped = false;

	static register(consumer) {
		this.consumers.push(consumer);
	}

	static find(duration) {
		return this.consumers.find(consumer => duration.matches(consumer.selector));
	}

	/*
	 * La durée de la saisie, zéro quand rien n'est saisi.
	 */
	static get(duration) {

		const time = parseFloat(duration.dataset.time);

		return isNaN(time) ? 0 : time;

	}

	static split(time) {

		const minutes = Math.round(time * 60);

		return [Math.floor(minutes / 60), minutes % 60];

	}

	static getPanel(duration) {
		return duration.qs(':scope > .planning-time-panel');
	}

	/*
	 * Une seule saisie derrière une tuile est ouverte à la fois : l'Équipe de la fiche en porte une par ligne.
	 */
	static toggle(duration) {

		const open = this.getPanel(duration).classList.contains('is-open');

		this.close();

		if(open === false) {
			this.open(duration);
		}

	}

	/*
	 * Une journée vide s'ouvre sur la durée que son écran propose (`data-suggested`, `TimeDayUi::getWorking()`),
	 * qui n'est qu'affichée : la fermer sans enregistrer la rend vide (`discard()`), et la rouvrir la repropose.
	 */
	static open(duration) {

		const panel = this.getPanel(duration);

		panel.classList.add('is-open');

		this.kept = duration.dataset.duration;
		this.draft = null;

		if(duration.dataset.time === '' && duration.dataset.suggested !== undefined) {
			this.choose(duration, parseFloat(duration.dataset.suggested));
		}

		this.place(panel);

	}

	static close(selector = '.planning-duration-tiled', accept = () => true) {

		let held = false;

		qsa(selector +' > .planning-time-panel.is-open', panel => {

			if(accept(panel) === false) {
				held = held || (panel.parentElement.dataset.duration === this.kept);
				return;
			}

			this.hide(panel.parentElement);

		});

		if(held === false) {
			this.kept = null;
			this.draft = null;
		}

	}

	/*
	 * Une durée en cours de frappe est abandonnée avec le reste de ce qui n'a pas été enregistré. Masquée par la
	 * fermeture, une animation s'arrête sans `animationend` : la classe se retire donc ici.
	 */
	static hide(duration) {

		this.discard(duration);

		if(this.kept === duration.dataset.duration) {
			this.kept = null;
			this.draft = null;
		}

		this.getPanel(duration).classList.remove('is-open', 'is-up', 'is-sheet');
		duration.qs('.planning-time-panel-title', title => this.stopRolling(title));

	}

	/*
	 * La saisie reprend la dernière durée écrite, `data-time` vide compris pour une journée à saisir.
	 */
	static discard(duration) {

		this.stopTyping(duration);

		const consumer = this.find(duration);

		if(consumer.discard !== undefined) {
			consumer.discard(duration);
			return;
		}

		if(duration.dataset.time !== duration.dataset.sent) {
			this.write(duration, duration.dataset.sent === '' ? 0 : parseFloat(duration.dataset.sent));
			duration.dataset.time = duration.dataset.sent;
		}

	}

	/*
	 * « Enregistrer » et Entrée envoient la durée affichée puis referment la saisie. Une durée inchangée ne
	 * repart pas, et une journée à saisir part dès qu'un geste l'a écrite, même à zéro. Un refus ne redessine rien :
	 * la dernière durée écrite redevient celle d'avant, et la saisie se rouvre sur la durée refusée, où son
	 * message se lit, sauf quand une autre fenêtre s'est ouverte depuis.
	 */
	static save(duration) {

		this.submitTyped(duration);

		const time = this.get(duration);
		const before = duration.dataset.sent;

		if(duration.dataset.time !== '' && (before === '' || parseFloat(before) !== time)) {

			duration.dataset.sent = time;

			const sent = this.find(duration).change(duration, time);

			if(sent instanceof Promise) {
				sent.then(json => {
					if(this.refused(json) && duration.isConnected && parseFloat(duration.dataset.sent) === time) {
						duration.dataset.sent = before;
						if(qs('.planning-duration-tiled > .planning-time-panel.is-open, .planning-duration-step-window.is-open') === null) {
							this.open(duration);
						}
					}
				});
			}

		}

		this.hide(duration);

	}

	// La croix et Échap referment la saisie.
	static dismiss() {
		this.close();
	}

	static escape(duration) {

		if(duration.classList.contains('is-typing')) {
			this.stopTyping(duration);
		} else {
			this.dismiss();
		}

	}

	static escapeAll() {

		this.close();

		PlanningDurationStep.closeAll();
		PlanningDurationStep.dismissAll();

	}

	/*
	 * Sous la tuile, ou au-dessus quand la place manque dessous : ouverte vers le haut, la saisie se pose
	 * sur son bloc conteneur. Au téléphone, ou sans place ni dessus ni dessous, elle devient une feuille en bas
	 * de l'écran. Une saisie sans `offsetParent`, parce que dans un pli fermé, ne se place pas.
	 */
	static place(panel) {

		if(panel.offsetParent === null) {
			return;
		}

		const {top, bottom} = this.getScreen(panel);

		const gap = parseFloat(getComputedStyle(panel).getPropertyValue('--planning-time-panel-gap')) * rem();
		const phone = window.matchMedia('(max-width: 575px)').matches;

		const panelBounds = panel.getBoundingClientRect();
		const anchorBounds = panel.offsetParent.getBoundingClientRect();

		const below = (panelBounds.bottom <= bottom);
		const above = (anchorBounds.top - gap - panelBounds.height >= top && anchorBounds.top - gap <= bottom);
		const sheet = (phone || (below === false && above === false));
		const shift = sheet ? 0 : this.getShift(panel, panelBounds);

		if(sheet) {
			panel.classList.add('is-sheet');
		} else if(below === false) {
			panel.classList.add('is-up');
		}

		this.shift(panel, shift);

	}

	/*
	 * Le décalage horizontal qui garde une saisie ou une fenêtre entière dans l'écran que la page lui laisse
	 * (`getSides()`) : posée sur le bord de ce qui l'ouvre, elle en dépasse d'un côté ou de l'autre selon la
	 * place de la tuile dans la page et la largeur de l'écran (R-Retours-0810, N3). Le bord gauche l'emporte
	 * sur un écran plus étroit qu'elle. Les bornes mesurées se comptent sans le décalage déjà posé, qui
	 * reste d'une ouverture à l'autre.
	 */
	static getShift(node, bounds) {

		const {left, right} = this.getSides(node);
		const current = parseFloat(node.style.getPropertyValue('--planning-duration-shift')) || 0;

		const nodeLeft = bounds.left - current;
		const nodeRight = bounds.right - current;

		let shift = 0;

		if(nodeRight > right) {
			shift = right - nodeRight;
		}

		if(nodeLeft + shift < left) {
			shift = left - nodeLeft;
		}

		return shift;

	}

	static shift(node, shift) {

		if(shift === 0) {
			node.style.removeProperty('--planning-duration-shift');
		} else {
			node.style.setProperty('--planning-duration-shift', shift +'px');
		}

	}

	/*
	 * Le gauche et le droit de l'écran que la page laisse libres au nœud : le panneau qui le porte, sinon `main`,
	 * qui commence après le menu. Bornés à la fenêtre, `main` débordant de l'écran quand la page défile de
	 * côté, et écartés des bords de l'espacement d'une cellule. Le droit se lit à `clientWidth` : le panneau
	 * défile, et la barre de défilement que dessine un ordinateur prend sur sa largeur.
	 */
	static getSides(node) {

		const container = node.closest('.panel') ?? qs('body > main') ?? document.documentElement;
		const box = container.getBoundingClientRect();
		const margin = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--space-xs')) * rem();

		return {
			left: Math.max(box.left, 0) + margin,
			right: Math.min(box.left + container.clientLeft + container.clientWidth, document.documentElement.clientWidth) - margin
		};

	}

	/*
	 * Le haut et le bas de l'écran que la page laisse libres au nœud. Un panneau couvre la page et ses barres :
	 * son écran va du bas de son en-tête au haut de son pied quand il porte quelque chose, sinon à son propre
	 * bas. En-tête et pied collent par-dessus le corps, dont la pile (`.panel-body`, `lime.css`) enferme ce
	 * qui s'y ouvre sous eux (retour 196).
	 * Hors d'un panneau, seule la barre du pouce (`Structure.hasBar()`) occupe le bas de l'écran, avec la bande
	 * de l'univers fixée sur elle (`body > header`) : sans elle, rien ne borne le bas de l'écran.
	 */
	static getScreen(node) {

		const panel = node.closest('.panel');

		if(panel !== null) {

			const footer = panel.qs('.panel-footer:not(:empty)');

			return {
				top: panel.qs('.panel-header').getBoundingClientRect().bottom,
				bottom: (footer === null) ? panel.getBoundingClientRect().bottom : footer.getBoundingClientRect().top
			};

		}

		const root = getComputedStyle(document.documentElement);

		if(Structure.hasBar()) {

			// La barre du haut colle aussi et mesure `--nav-main`. Le bas se lit sur ce qui est fixé, car
			// `body` y pose `--nav-structure` en `calc()`, que `getPropertyValue()` rend sans le résoudre.
			let bottom = window.innerHeight;

			qsa('body > header, .structure-bar', bar => {

				const bounds = bar.getBoundingClientRect();

				if(bounds.height > 0) {
					bottom = Math.min(bottom, bounds.top);
				}

			});

			return {
				top: parseFloat(root.getPropertyValue('--nav-main')) * rem(),
				bottom
			};

		}

		return {
			top: (parseFloat(root.getPropertyValue('--mainSticky')) + parseFloat(root.getPropertyValue('--nav-main-sticked'))) * rem(),
			bottom: window.innerHeight
		};

	}

	static chooseHour(duration, hour) {

		const [, minute] = this.split(this.get(duration));

		this.choose(duration, hour + minute / 60);

	}

	static chooseMinute(duration, minute) {

		const [hour] = this.split(this.get(duration));

		this.choose(duration, hour + minute / 60);

	}

	// Le geste ne fait qu'écrire la durée affichée : seul « Enregistrer » l'envoie (`save()`).
	static choose(duration, time) {

		time = PlanningTask.round(this.find(duration).bounded ? Math.min(time, PlanningTask.MAX_TIME) : time);

		this.write(duration, time);
		this.draft = time;

	}

	/*
	 * Un refus répond par la seule instruction `errors` de la `FailAction` (`framework/core/Action.php`), et
	 * la promesse d'`Ajax.Query` se résout quand même : seule la réponse le dit. Une réponse absente est un
	 * envoi que la file a sauté parce que son nœud était déjà remplacé.
	 */
	static refused(json) {

		if(json === undefined) {
			return false;
		}

		return (json.__instructions ?? []).some(([type, about]) => type === 'js' && about.some(([name]) => name === 'errors'));

	}

	/*
	 * La saisie redessinée par la réponse de son écran se rouvre, à sa place, sur la durée qu'elle affichait
	 * (`draft`).
	 */
	static reopen() {

		if(this.kept === null) {
			return;
		}

		qs('.planning-duration-tiled[data-duration="'+ this.kept +'"]', duration => {

			const panel = this.getPanel(duration);

			if(panel.classList.contains('is-open') === false) {

				if(this.draft !== null) {
					this.write(duration, this.draft);
				}

				panel.classList.add('is-open');
				this.place(panel);

			}

		});

	}

	/*
	 * Écrit la durée comme `DurationUi` la rend : la durée lue, la valeur de ce qui ouvre la saisie, la tuile
	 * ou le rond de la semaine en champs (`TimeDayUi::getWorkingRound()`), et le rond et les minutes allumés,
	 * aucun à zéro.
	 * Seule la durée lue glisse d'un cran, vers le haut quand elle grandit (retour 133) ; la classe se repose
	 * à l'image suivante pour relancer l'animation sur une durée qui la porte encore, sans relire la mise en
	 * page pour la rejouer, et seulement sur une durée encore visible : posée sur une durée masquée, elle
	 * rejouerait à la réouverture.
	 */
	static write(duration, time) {

		const before = this.get(duration);
		const [hour, minute] = this.split(time);
		const empty = (time === 0);

		duration.dataset.time = time;

		duration.qs('[data-planning-time-reset]', reset => reset.toggleAttribute('data-empty', empty));

		duration.qs('.planning-time-panel-readout', readout => {

			readout.classList.toggle('planning-time-panel-readout-empty', empty);

			readout.qs('.planning-time-panel-title', title => {

				title.qs('b').textContent = hour;
				title.qs('span:last-child').textContent = String(minute).padStart(2, '0');

				if(time !== before) {
					this.stopRolling(title);
					requestAnimationFrame(() => {
						if(this.isShown(duration)) {
							title.classList.add(time > before ? 'planning-duration-roll-up' : 'planning-duration-roll-down');
						}
					});
				}

			});

		});

		duration.qs(':scope > [data-planning-duration-open] .planning-duration-tile-value', value => {
			value.classList.toggle('planning-duration-missing', empty);
			value.textContent = empty ? value.dataset.missing : PlanningTask.format(time);
		});

		duration.qsa('[data-planning-time-hour]', button => button.classList.toggle('btn-chosen', empty === false && parseInt(button.dataset.planningTimeHour) === hour));
		duration.qsa('[data-planning-time-minute]', button => button.classList.toggle('btn-chosen', empty === false && parseInt(button.dataset.planningTimeMinute) === minute));

	}

	/*
	 * La durée lue s'ouvre en deux cases, heures et minutes, sur la durée saisie (image 163). Leur
	 * `fieldset` n'est actif que le temps de la frappe : le formulaire qui loge la saisie n'en poste rien.
	 */
	static type(duration) {

		const fieldset = duration.qs('.planning-time-panel-type');
		const [hour, minute] = this.split(this.get(duration));
		const hourInput = fieldset.qs('input[name="timeAddHour"]');

		fieldset.disabled = false;
		duration.classList.add('is-typing');

		hourInput.value = hour;
		fieldset.qs('input[name="timeAddMinute"]').value = String(minute).padStart(2, '0');

		hourInput.focus();

	}

	static submitTyped(duration) {

		if(duration.classList.contains('is-typing') === false) {
			return;
		}

		const hour = this.readTyped(duration.qs('input[name="timeAddHour"]'));
		const minute = this.readTyped(duration.qs('input[name="timeAddMinute"]'));

		this.stopTyping(duration);

		if(hour !== null || minute !== null) {
			this.choose(duration, (hour ?? 0) + (minute ?? 0) / 60);
		}

	}

	// Une case que le focus a vidée garde sa valeur en indication (`Timesheet.updateFocusInTime()`).
	static readTyped(input) {

		const value = parseInt((input.value !== '') ? input.value : input.getAttribute('placeholder'));

		return isNaN(value) ? null : value;

	}

	// Une journée en horaires n'a pas de durée tapée (`DurationUi::getTiledRange()`).
	static stopTyping(duration) {

		duration.classList.remove('is-typing');
		duration.qs('.planning-time-panel-type', fieldset => fieldset.disabled = true);

	}

	static stopRolling(title) {
		title.classList.remove('planning-duration-roll-up', 'planning-duration-roll-down');
	}

	// Le panneau dans le flux est toujours visible, celui d'une tuile seulement ouvert.
	static isShown(duration) {
		return duration.classList.contains('planning-duration-tiled') === false || this.getPanel(duration).classList.contains('is-open');
	}

	static bound(input, max) {

		const value = parseInt(input.value);

		if(isNaN(value) === false && value > max) {
			input.value = max;
		}

	}

}

/*
 * Le champ par pas (`DurationUi::getStepped()`, R-Retours-0410, point 22) : -1h, -15, -5, la durée tapée en
 * deux cases, heures et minutes (retour 188), +5, +15, +1h, repris du `PlanningStep` d'avant `f8b4a4e61` sans
 * sa réglette. Il vit dans une fenêtre sous laquelle « Enregistrer » est le seul geste qui écrit (planche
 * R-Retours-0810, Enregistrer) : celle que la durée de sa ligne ouvre (`DurationUi::getStepWindow()`), ou une
 * fenêtre sans tuile, toujours ouverte dans l'écran qui la porte (`DurationUi::getStepBare()`, la bulle de la
 * Semaine et la carte du Jour, R-Retours-0810, 212). Le temps d'une saisie de récolte a ses pas en ligne,
 * sans « Enregistrer », dans le formulaire dont l'envoi écrit (lot BW). Ses délégués sont posés une fois, ici ;
 * chaque écran déclare ce que devient son champ (`PlanningDurationStep.register()`) :
 * - `selector` : le conteneur du champ (`.planning-duration-step`), auquel le geste remonte ;
 * - `bounded` : un pas ne dépasse pas une journée (`PlanningTask.MAX_TIME`) ; le total d'une personne sur
 *   une intervention entière et le temps prévu d'une ligne de la grille n'ont pas cette borne ;
 * - `change(field, time)` : sur « Enregistrer » ou Entrée, et à chaque pas en ligne, quand la durée a changé ;
 *   rend la promesse de son envoi. La fenêtre à tuile se ferme à l'envoi (FJ, retour 411), et un refus la
 *   rouvre sur la durée refusée, avec son message ;
 * - `dismiss(field)` : retire l'écran d'une fenêtre sans tuile, après un « Enregistrer » que le serveur n'a
 *   pas refusé, ou sur la croix et Échap. La fenêtre ne se ferme jamais d'elle-même : un refus garde l'écran,
 *   où son message se lit sous le champ.
 *
 * Le client ne refuse aucune durée : il ne sait pas ce que la journée porte déjà, et une marque qu'il pose
 * sans message laisse « Enregistrer » sans effet visible (R-Retours-0810, 210). Le serveur juge seul, et son
 * refus revient par la chaîne d'erreur.
 *
 * Les gestes des deux cases sont ceux du champ de la fiche de temps (`timesheet.js`) : une case prise se vide
 * et garde sa valeur en indication, une heure tapée pose « 00 » dans les minutes vides.
 *
 * Un pas et la frappe n'écrivent que la durée affichée, sauf en ligne, où ils la reportent aussitôt. `data-sent`
 * porte la dernière durée écrite : une durée identique ne repart pas, et la fenêtre fermée sans enregistrer la
 * reprend.
 */
// Un pas ne prend pas le focus : le champ le garde, et Entrée enregistre encore après un pas. Seul le
// `mousedown` porte le déplacement du focus, l'annuler sur `pointerdown` ne le retient pas.
document.delegateEventListener('mousedown', '.planning-duration-step [data-planning-duration-step]', function(e) {
	e.preventDefault();
});

document.delegateEventListener('click', '.planning-duration-step [data-planning-duration-step]', function(e) {

	e.preventDefault();

	PlanningDurationStep.add(this.closest('.planning-duration-step'), parseInt(this.dataset.planningDurationStep));

});

document.delegateEventListener('keydown', '.planning-duration-step input', function(e) {

	const field = this.closest('.planning-duration-step');

	switch(e.key) {

		case 'Enter' :
			e.preventDefault();
			PlanningDurationStep.save(field);
			break;

		case 'Escape' : {

			const stepWindow = PlanningDurationStep.getWindow(field);

			// Les pas d'une saisie de récolte n'ont rien à refermer (`DurationUi::getStepInline()`) : Échap revient
			// au panneau ou au tiroir qui les porte, comme dans la quantité.
			if(PlanningDurationStep.isBare(stepWindow) && PlanningDurationStep.isInline(stepWindow)) {
				break;
			}

			PlanningDuration.escaped = PlanningDurationStep.isOpen(stepWindow);
			PlanningDurationStep.close(field);
			break;

		}

	}

});

// Les pas en ligne reportent aussi la durée tapée (`PlanningDurationStep.report()`).
document.delegateEventListener('input', '.planning-duration-step-inline .planning-duration-step input', function() {
	PlanningDurationStep.report(this.closest('.planning-duration-step'));
});

document.delegateEventListener('animationend', '.planning-duration-step .timesheet-time-field-group', function() {
	PlanningDurationStep.stopRolling(this);
});

document.delegateEventListener('click', '.planning-duration-step-window [data-planning-duration-step-save]', function(e) {

	e.preventDefault();

	PlanningDurationStep.save(this.closest('.planning-duration-step-window').qs('.planning-duration-step'));

});

// La poubelle d'une case de la fiche (`DurationUi::getStepPanel()`). Celle d'une case du menu d'un jour, sans
// pied, a son propre délégué (`PlanningTaskDetail.erase()`).
document.delegateEventListener('click', '.planning-duration-step-window:not(.planning-duration-step-inline) [data-planning-duration-step-erase]', function(e) {

	e.preventDefault();

	PlanningDurationStep.erase(this.closest('.planning-duration-step-window').qs('.planning-duration-step'));

});

// La croix ferme comme Échap : la durée affichée et pas enregistrée redevient la dernière écrite.
document.delegateEventListener('click', '.planning-duration-step-window [data-planning-duration-step-close]', function(e) {

	e.preventDefault();

	PlanningDurationStep.close(this.closest('.planning-duration-step-window').qs('.planning-duration-step'));

});

/*
 * La durée de la ligne ouvre sa fenêtre et la referme. Un appui hors d'elle ou une tabulation qui en sort la
 * referme aussi, sans rien écrire. Le focus ne va pas au champ, pour qu'au téléphone le clavier ne recouvre
 * pas la fenêtre.
 */
document.delegateEventListener('click', '[data-planning-duration-step-open]', function(e) {

	e.preventDefault();

	const field = this.nextElementSibling.qs('.planning-duration-step');

	if(PlanningDurationStep.isOpen(PlanningDurationStep.getWindow(field))) {
		PlanningDurationStep.close(field);
	} else {
		PlanningDurationStep.open(field);
	}

});

// Pendant un appui, le focus quitte la fenêtre dès le `mousedown`, avant le clic : c'est `outside()` qui la
// referme alors, en avalant ce clic. Le `focusout` ne referme donc que sur une tabulation qui sort.
document.delegateEventListener('focusout', '.planning-duration-step-window', function(e) {

	if(PlanningDurationStep.pressed === null && e.relatedTarget !== null && this.contains(e.relatedTarget) === false && PlanningDurationStep.isFloating(this)) {
		PlanningDurationStep.close(this.qs('.planning-duration-step'));
	}

});

/*
 * Un clic hors de la fenêtre flottante ouverte la referme sans rien écrire, et nulle part dans la page il ne
 * fait autre chose : ni le lien, ni la ligne, ni le « + » dessous n'agissent, c'est le clic suivant qui agit
 * (retour 399, la règle du tiroir des récoltes). Écouté en capture sur `window`, avant les délégués du
 * document. Une fenêtre rendue au flux (`planning.css`) ne se ferme que par ses gestes : elle tient
 * sa place dans la page au lieu de flotter sur ce qu'un toucher ailleurs viendrait atteindre.
 */
window.addEventListener('pointerdown', e => PlanningDurationStep.down(e), true);
window.addEventListener('pointercancel', () => PlanningDurationStep.cancel(), true);
window.addEventListener('click', e => PlanningDurationStep.outside(e), true);

/*
 * Une fenêtre sortie de la boîte de sa table (`is-fixed`, `PlanningDurationStep.place()`) ne défile plus avec
 * elle : elle suit sa case au défilement de la page comme à celui de la grille, écouté en capture puisque le
 * `scroll` d'une boîte ne remonte pas. Posé une fois ici et non à l'ouverture : une réponse redessine une
 * fenêtre fermée sans passer par `close()`, et un écouteur retiré là ne le serait jamais.
 */
document.addEventListener('scroll', () => PlanningDurationStep.follow(), {capture: true, passive: true});
window.addEventListener('resize', () => requestAnimationFrame(() => PlanningDurationStep.follow()));

class PlanningDurationStep {

	static consumers = [];

	/*
	 * Une réponse redessine le champ avec la durée que le serveur vient d'écrire, alors qu'un envoi plus
	 * récent peut attendre dans la file : sans lui, le champ afficherait une durée dépassée. Ce qui attend se
	 * range par clé (`data-duration`), la seule chose qui survive au redessin, et `resume()` le rend au champ
	 * redessiné :
	 * - `pending` : par clé, le nombre d'envois sans réponse et la dernière durée envoyée ;
	 * - `opened` : la clé de la fenêtre ouverte, sur une autre ligne ou sur la même, pendant l'envoi : une réponse
	 *   la redessine fermée, et `resume()` la rouvre.
	 */
	static pending = new Map();
	static opened = null;

	/*
	 * Le clic en cours (`outside()`) : le nœud où il a commencé, et la fenêtre flottante ouverte à cet instant,
	 * relevée avant que le focus ne bouge.
	 */
	static pressed = null;
	static held = null;

	static register(consumer) {
		this.consumers.push(consumer);
	}

	static find(field) {
		return this.consumers.find(consumer => field.matches(consumer.selector));
	}

	static getInput(field) {
		return field.qs('input[name="timeAddHour"]');
	}

	static getInputs(field) {
		return [this.getInput(field), field.qs('input[name="timeAddMinute"]')];
	}

	static getWindow(field) {
		return field.closest('.planning-duration-step-window');
	}

	static isOpen(stepWindow) {
		return stepWindow.classList.contains('is-open');
	}

	static stopRolling(group) {
		group.classList.remove('planning-duration-step-roll-up', 'planning-duration-step-roll-down');
	}

	static isBare(stepWindow) {
		return stepWindow.classList.contains('planning-duration-step-bare');
	}

	// Les pas en ligne (`DurationUi::getStepInline()`) n'ont pas d'« Enregistrer » : la durée se reporte à chaque
	// pas (`report()`).
	static isInline(stepWindow) {
		return stepWindow.classList.contains('planning-duration-step-inline');
	}

	static isFloating(stepWindow) {
		return this.isBare(stepWindow) === false && getComputedStyle(stepWindow).position !== 'static';
	}

	// Une seule fenêtre à tuile est ouverte à la fois (`open()`). Un autre bouton que le principal n'aura pas de
	// clic qui le relâche : il ne retient rien, sans quoi le `focusout` resterait muet jusqu'au clic suivant.
	static down(e) {

		if(e.button !== 0) {
			this.cancel();
			return;
		}

		this.pressed = e.target;
		this.held = qs('.planning-duration-step-window.is-open:not(.planning-duration-step-bare)');

		if(this.held !== null && this.isFloating(this.held) === false) {
			this.held = null;
		}

	}

	// Un appui que le navigateur reprend, pour faire défiler au doigt, n'aura pas de clic.
	static cancel() {
		this.pressed = null;
		this.held = null;
	}

	/*
	 * Comme `PlanningHarvest.outside()`, un clic au clavier (`detail` à 0) agit, et un Ctrl+clic ou Cmd+clic sur
	 * un lien l'ouvre dans un autre onglet en laissant la fenêtre. L'appui retenu écarte une sélection commencée
	 * dans la fenêtre et lâchée dehors. La tuile de la fenêtre n'est pas un ailleurs : son délégué la referme.
	 */
	static outside(e) {

		const pressed = this.pressed;
		const stepWindow = this.held;

		this.cancel();

		const opensTab = ((e.ctrlKey || e.metaKey) && e.target.closest('a[href]') !== null);

		if(stepWindow === null || this.isOpen(stepWindow) === false || e.detail === 0 || opensTab || this.contains(stepWindow, e.target) || this.contains(stepWindow, pressed)) {
			return;
		}

		this.close(stepWindow.qs('.planning-duration-step'));

		e.preventDefault();
		e.stopPropagation();

	}

	static contains(stepWindow, node) {
		return (node !== null && (stepWindow.contains(node) || node.closest('[data-planning-duration-step-open]')?.nextElementSibling === stepWindow));
	}

	/*
	 * La durée des deux cases ; une case que le focus a vidée vaut sa valeur en indication
	 * (`PlanningDuration.readTyped()`), une case vide vaut zéro. Les minutes s'ajoutent aux heures comme
	 * `timesheet.js` les lit : 3 h 75 vaut 4 h 15.
	 */
	static get(field) {

		const [hour, minute] = this.getInputs(field).map(input => PlanningDuration.readTyped(input) ?? 0);

		return PlanningTask.round(hour + minute / 60);

	}

	// La dernière durée écrite, arrondie comme `get()` : la colonne `float32` la rend en 0.079999998211861 pour 0 h 05.
	static getSent(field) {
		return PlanningTask.round(parseFloat(field.dataset.sent));
	}

	/*
	 * Le pas se compte en minutes, sur la durée ramenée à la minute : une durée s'arrondit au centième d'heure,
	 * et cinq minutes, qui n'en tombent pas juste, dériveraient d'un pas à l'autre.
	 */
	static add(field, step) {

		this.show(field, this.shift(field, this.get(field), step));
		this.report(field);

	}

	/*
	 * Les pas en ligne reportent la durée affichée dès qu'elle change, sans attendre « Enregistrer », qu'ils
	 * n'ont pas : le formulaire qui les porte l'envoie seul (lot BW, retour 280). `send()` ne fait rien partir
	 * d'une durée inchangée.
	 */
	static report(field) {

		if(this.isInline(this.getWindow(field))) {
			this.send(field, this.get(field));
		}

	}

	/*
	 * La durée `current` déplacée de `minutes`, jamais sous zéro. Une durée déjà au-delà d'une journée (écrite
	 * avant la borne) n'est pas ramenée à 24 h par un pas positif : le serveur accepte toute baisse, et le
	 * bouton « plus » effacerait des heures.
	 */
	static shift(field, current, minutes) {

		let time = Math.max(0, (Math.round(current * 60) + minutes) / 60);

		if(this.find(field).bounded && current <= PlanningTask.MAX_TIME) {
			time = Math.min(time, PlanningTask.MAX_TIME);
		}

		return PlanningTask.round(time);

	}

	/*
	 * Le glisser sur la tuile d'une ligne de la fiche (R-Retours-0810, 201), dont `PlanningPointer` dit le
	 * départ : la souris dès l'appui, le doigt au-delà de son seuil horizontal. Un pas de `DRAG_STEP` rem vaut
	 * un quart d'heure, et la tuile le dit aussitôt. Le temps ne part qu'au lâcher, une fois, par l'envoi
	 * d'« Enregistrer » (`send()`), et sans avoir besoin de lui : le geste est fini quand le pointeur se lève,
	 * comme celui de la réglette de la journée.
	 * Sur une ligne dont la fenêtre est ouverte, le geste reste à la fenêtre et la tuile ne fait que la
	 * refermer. Un appui sans pas laisse passer son clic, qui ouvre la fenêtre ; un glisser d'au moins un pas
	 * avale le sien (`swallow`).
	 *
	 * - `dragged` : le geste en cours, sa tuile, son champ, son départ et la durée visée ;
	 * - `swallow` : la tuile qu'un glisser vient de lâcher.
	 */
	// Réglé sur 1 rem et 1,25 rem (livraison R-Retours-0810, N3) : les deux posent 1h00 dans la ligne au doigt à
	// 360 px, et le plus grand laisse au doigt le pas le plus large à viser.
	static DRAG_STEP = 1.25;

	static dragged = null;
	static swallow = null;

	static getTile(field) {
		return this.getWindow(field).previousElementSibling;
	}

	// Le pas se mesure une fois, au départ : les déplacements ne font qu'écrire.
	static grab(tile, e) {

		this.swallow = null;
		this.dragged = null;

		const stepWindow = tile.nextElementSibling;

		if(this.isOpen(stepWindow)) {
			return;
		}

		const field = stepWindow.qs('.planning-duration-step');
		const from = parseFloat(field.dataset.sent);

		this.dragged = {
			tile,
			field,
			x: e.clientX,
			step: this.DRAG_STEP * parseFloat(getComputedStyle(document.documentElement).fontSize),
			from,
			time: from,
			moved: false
		};

	}

	static drag(x) {

		const dragged = this.dragged;

		if(dragged === null) {
			return;
		}

		const time = this.shift(dragged.field, dragged.from, Math.trunc((x - dragged.x) / dragged.step) * 15);

		if(time === dragged.time) {
			return;
		}

		dragged.time = time;
		dragged.moved = true;

		this.tile(dragged.tile, time);

	}

	static drop() {

		const dragged = this.dragged;

		if(dragged === null) {
			return;
		}

		this.dragged = null;

		if(dragged.moved === false) {
			return;
		}

		this.swallow = dragged.tile;

		this.write(dragged.field, dragged.time);
		this.send(dragged.field, dragged.time);

	}

	// Le navigateur reprend le geste : la tuile revient à son départ, rien ne part.
	static release() {

		const dragged = this.dragged;

		if(dragged === null) {
			return;
		}

		this.dragged = null;

		this.tile(dragged.tile, dragged.from);

	}

	// Seul un clic de pointeur (`detail` à 1 au moins) suit le glisser : celui d'Entrée au clavier passe.
	static click(e) {

		if(this.swallow !== null && e.detail > 0 && (e.target instanceof Node) && this.swallow.contains(e.target)) {
			this.swallow = null;
			e.preventDefault();
			e.stopPropagation();
		}

	}

	// Écrit la durée de la tuile comme `DurationUi::getStepWindow()` la rend, sans l'envoyer. Un temps prévu ne
	// prend pas la marque d'un temps passé.
	static tile(tile, time) {

		tile.qs('.planning-duration-tile-value', value => {
			value.textContent = PlanningTask.format(time);
			if(value.classList.contains('planning-duration-tile-expected')) {
				value.classList.toggle('planning-duration-tile-set', time > 0);
			} else {
				['badge', 'badge-time-spent'].forEach(name => value.classList.toggle(name, time > 0));
			}
		});

	}

	/*
	 * Une seule fenêtre est ouverte à la fois, saisie derrière une tuile comprise.
	 */
	static open(field) {

		const stepWindow = this.getWindow(field);

		if(this.isOpen(stepWindow)) {
			return;
		}

		PlanningDuration.close();
		this.closeAll();

		stepWindow.classList.remove('is-up', 'is-over', 'is-fixed');
		stepWindow.classList.add('is-open');

		this.mute(field, true);

		this.opened = field.dataset.duration;

		this.place(stepWindow);

	}

	/*
	 * Ce qui n'a pas été enregistré est abandonné : le champ reprend la dernière durée écrite. Une fenêtre sans
	 * tuile retire l'écran qui la porte. Masquée par la fermeture, une animation s'arrête sans
	 * `animationend` : la classe se retire donc ici.
	 */
	static close(field) {

		const stepWindow = this.getWindow(field);

		if(this.isOpen(stepWindow) === false) {
			return;
		}

		this.write(field, parseFloat(field.dataset.sent));

		if(this.isBare(stepWindow)) {
			this.find(field).dismiss(field);
			return;
		}

		if(stepWindow.classList.contains('is-fixed')) {
			this.unanchor(stepWindow);
		}

		stepWindow.classList.remove('is-open', 'is-up', 'is-over', 'is-fixed');
		this.stopRolling(field.qs('.timesheet-time-field-group'));
		this.mute(field, false);

		if(this.opened === field.dataset.duration) {
			this.opened = null;
		}

	}

	/*
	 * Une zone n'a qu'un bouton plein visible (passe ui de R). Dans un formulaire, celui de la fenêtre ouverte
	 * ne fait que reporter la durée dans le champ caché, et l'envoi du formulaire passe en contour jusqu'à sa
	 * fermeture : seul celui qu'elle a éteint reprend le plein.
	 */
	static mute(field, muted) {

		const selector = muted ? 'button[type="submit"].btn-primary' : 'button[type="submit"][data-planning-duration-muted]';

		field.closest('form')?.qsa(selector, button => {
			button.classList.toggle('btn-primary', muted === false);
			button.classList.toggle('btn-outline-primary', muted);
			button.toggleAttribute('data-planning-duration-muted', muted);
		});

	}

	// Une fenêtre sans tuile n'est pas de celles qu'une autre referme : seul son écran la retire.
	static closeAll(accept = () => true) {

		qsa('.planning-duration-step-window.is-open:not(.planning-duration-step-bare)', stepWindow => {
			if(accept(stepWindow)) {
				this.close(stepWindow.qs('.planning-duration-step'));
			}
		});

	}

	static dismissAll() {
		qsa('.planning-duration-step-bare', stepWindow => this.close(stepWindow.qs('.planning-duration-step')));
	}

	/*
	 * La fenêtre se pose sous la ligne, ou au-dessus d'elle quand elle dépasse le bas de l'écran et que la place
	 * y suffit sans monter au-dessus de la table : celle de la première ligne ne couvre pas ce qui précède la
	 * table, la rangée des jours de la page d'une intervention (constat de P, 3c). Le bas de la table ne compte
	 * pas : posée sous elle, la fenêtre tient dans l'écran, donc ne fait pas grandir la page, et monter la ferait
	 * passer sur la ligne (R-Retours-1210, FQ). Quand la table ne la loge d'aucun côté, l'écran seul décide,
	 * dessous d'abord.
	 * Une table qui découpe ce qui la dépasse couperait la fenêtre : la grille de la Semaine plus étroite que ses
	 * colonnes défile dans sa boîte (`overflow-x: auto`, qui vaut aussi en hauteur). La fenêtre en sort alors en
	 * `position: fixed` (`is-fixed`), sans changer de parent, sans quoi le consommateur, la ligne qui envoie et
	 * la tuile voisine ne la retrouveraient plus (R-Retours-1210, FT), et suit sa case (`follow()`). Elle est
	 * mesurée avant, en `absolute` (`open()` et `close()` retirent `is-fixed`) : sous la case et à son droit, c'est
	 * la place que le `fixed` lui rend.
	 * Le tiroir de Récoltes fait exception : la fenêtre y descend toujours sous le bas du
	 * formulaire (`--planning-duration-step-drop`), sur la culture suivante de la liste, et la page défile pour
	 * la montrer quand l'écran manque. Sous la ligne, elle couvrirait « Annuler », et au-dessus la quantité et le
	 * temps de la saisie en cours (R-Retours-0810, AB, point 5). Jamais en feuille au bas de l'écran, où le
	 * clavier du champ la couvrirait.
	 * Sans place dans l'écran (`getScreen()`), elle monte entre les seules barres fixes et passe devant ce qui
	 * colle (`is-over`), sans couvrir sa tuile, qui est sous elle (constat de P, 3a, à 844×390). Sans
	 * place même là, elle reste dessous, et ce qui la porte, le panneau ou la page, défile jusqu'à montrer son
	 * bas, sans faire passer son haut sous l'en-tête (contre-recette M1, panneau de 300 de haut). De côté, elle
	 * se décale pour tenir dans l'écran (`PlanningDuration.getShift()`). Toutes les mesures d'abord, puis les
	 * écritures.
	 * Une fenêtre rendue au flux a déjà sa place, et `reveal()` montre son bas.
	 * Dans un formulaire, la récolte, le lot ou l'entretien, la ligne est le temps et la table le formulaire ;
	 * sur la grille de la Semaine, la ligne est celle de l'intervention ; dans le tableau de l'équipe d'une intervention, la
	 * ligne est la case, celle d'une personne étant sans boîte.
	 */
	static place(stepWindow) {

		if(this.isBare(stepWindow)) {
			return;
		}

		if(this.isFloating(stepWindow) === false) {
			this.reveal(stepWindow);
			return;
		}

		const windowBounds = stepWindow.getBoundingClientRect();
		const rowBounds = stepWindow.closest('.planning-time-row, .planning-detail-time-field, .planning-harvest-time, .planning-time-crew-duration, .planning-grid-row').getBoundingClientRect();
		const table = this.getTable(stepWindow);
		const tableBounds = table.getBoundingClientRect();
		const tableClips = (getComputedStyle(table).overflowY !== 'visible');
		const cellBounds = tableClips ? stepWindow.parentElement.getBoundingClientRect() : null;
		const inDrawer = (stepWindow.closest('.planning-harvest-drawer') !== null);

		// La fenêtre est mesurée là où l'ouverture précédente l'a laissée : sa place sous la ligne s'en déduit.
		const dropCurrent = parseFloat(stepWindow.style.getPropertyValue('--planning-duration-step-drop')) || 0;
		const drop = inDrawer ? Math.max(0, tableBounds.bottom - rowBounds.bottom) : 0;
		const droppedTop = windowBounds.top - dropCurrent + drop;
		const droppedBottom = windowBounds.bottom - dropCurrent + drop;

		const {top, bottom, bars} = this.getScreen(stepWindow, windowBounds);
		const raised = rowBounds.top - windowBounds.height;

		const below = (droppedBottom <= bottom);
		const above = (raised >= top);
		const over = (inDrawer === false && below === false && above === false && raised >= bars.top);
		const shift = PlanningDuration.getShift(stepWindow, windowBounds);

		let up;

		if(inDrawer || below) {
			up = false;
		} else if(above && raised >= tableBounds.top) {
			up = true;
		} else if(below || above) {
			up = (below === false);
		} else {
			up = over;
		}

		PlanningDuration.shift(stepWindow, shift);

		stepWindow.classList.toggle('is-up', up);
		stepWindow.classList.toggle('is-over', over);
		stepWindow.classList.toggle('is-fixed', tableClips);

		if(tableClips) {
			this.anchor(stepWindow, cellBounds);
		}

		if(up || drop === 0) {
			stepWindow.style.removeProperty('--planning-duration-step-drop');
		} else {
			stepWindow.style.setProperty('--planning-duration-step-drop', drop +'px');
		}

		if(up === false && below === false) {
			(stepWindow.closest('.panel') ?? window).scrollBy(0, Math.min(droppedBottom - bottom, droppedTop - top));
		}

	}

	// Dans un formulaire, la récolte, le lot ou l'entretien, la table est le formulaire (`place()`).
	static getTable(stepWindow) {
		return stepWindow.closest('.planning-time-table, .planning-detail-time-table, #planning-grid, #planning-grid-late, form');
	}

	/*
	 * La fenêtre sortie de sa table (`is-fixed`) suit sa case, celle qui porte la tuile, tant qu'une partie en
	 * reste visible dans la table : un léger glissé du doigt ne fait pas perdre les pas tapés. Elle se décale de
	 * côté pour rester dans l'écran, à la règle de `place()` (`PlanningDuration.getShift()`), sans quoi une case à
	 * moitié sortie emporterait « Enregistrer » hors de l'écran. La case sortie entière par le côté, la fenêtre
	 * flotterait loin de sa ligne : elle se ferme comme sur Échap, sans rien enregistrer (R-Retours-1210, FT,
	 * tranché par M-Planning). Une seule fenêtre est ouverte à la fois (`open()`).
	 */
	static follow() {

		const stepWindow = qs('.planning-duration-step-window.is-open.is-fixed');

		if(stepWindow === null) {
			return;
		}

		const cellBounds = stepWindow.parentElement.getBoundingClientRect();
		const tableBounds = this.getTable(stepWindow).getBoundingClientRect();

		if(cellBounds.right <= tableBounds.left || cellBounds.left >= tableBounds.right) {
			this.close(stepWindow.qs('.planning-duration-step'));
			return;
		}

		// La fenêtre se mesure là où la case d'avant la tenait, et se déplace d'autant que la case.
		const windowBounds = stepWindow.getBoundingClientRect();
		const moved = cellBounds.right - parseFloat(stepWindow.style.getPropertyValue('--planning-duration-step-cell-right'));
		const shift = PlanningDuration.getShift(stepWindow, {left: windowBounds.left + moved, right: windowBounds.right + moved});

		PlanningDuration.shift(stepWindow, shift);
		this.anchor(stepWindow, cellBounds);

	}

	// La géométrie de la case, d'où `planning.css` place la fenêtre `is-fixed` avec ses propres écarts.
	static anchor(stepWindow, cellBounds) {
		stepWindow.style.setProperty('--planning-duration-step-cell-top', cellBounds.top +'px');
		stepWindow.style.setProperty('--planning-duration-step-cell-bottom', cellBounds.bottom +'px');
		stepWindow.style.setProperty('--planning-duration-step-cell-right', cellBounds.right +'px');
	}

	/*
	 * La case survit à la fermeture de sa fenêtre : fermée, la fenêtre sortie de sa table redevient celle qu'un
	 * rendu de la case donne, décalage de côté compris.
	 */
	static unanchor(stepWindow) {

		['top', 'bottom', 'right'].forEach(side => stepWindow.style.removeProperty('--planning-duration-step-cell-'+ side));
		PlanningDuration.shift(stepWindow, 0);

		// `removeAttribute()` ne suffit pas : Chrome réécrit l'attribut d'après les retraits du style qui précèdent,
		// vide, à la lecture suivante. `toggleAttribute()` relit l'attribut avant de le retirer.
		if(stepWindow.style.length === 0) {
			stepWindow.toggleAttribute('style', false);
		}

	}

	/*
	 * Le haut et le bas de l'écran que la page laisse à la fenêtre ; `bars` les mêmes entre les seules barres
	 * fixes. Un panneau couvre la page et ses barres : son écran est celui de `PlanningDuration.getScreen()`.
	 * Hors d'un panneau, l'écran se mesure sur ce qui le borne. Les variables du gabarit ne le disent pas :
	 * `body` les redéfinit sous 768 et sous 600 de haut (`template-structure.css`), là où la barre du pouce et
	 * la bande de l'univers (`body > header`) occupent ensemble le bas de l'écran (constat de P, 3a).
	 * - Une barre fixe est plus large que haute : le menu et sa colonne, à gauche, n'en sont pas.
	 * - Le titre de la page et l'en-tête de la table bornent son haut là où ils collent (constat de P, 3a et 3b).
	 *   Encore dans le flux, l'en-tête est plus bas que cette borne, et la fenêtre ouverte vers le haut passe
	 *   devant lui (`is-up`, FQ) ; le titre (`z-index` 100) reste devant elle.
	 */
	static getScreen(stepWindow, windowBounds) {

		if(stepWindow.closest('.panel') !== null) {
			const screen = PlanningDuration.getScreen(stepWindow);
			return {...screen, bars: screen};
		}

		const bars = {top: 0, bottom: window.innerHeight};

		qsa('body > header, body > nav, .structure-bar', bar => {

			const bounds = bar.getBoundingClientRect();

			if(getComputedStyle(bar).position !== 'fixed' || bounds.width <= bounds.height) {
				return;
			}

			if(bounds.top < window.innerHeight / 2) {
				bars.top = Math.max(bars.top, bounds.bottom);
			} else {
				bars.bottom = Math.min(bars.bottom, bounds.top);
			}

		});

		let top = bars.top;

		// Un en-tête collant qui n'a pas encore atteint son `top` est plus bas qu'il ne le sera une fois la page
		// défilée : c'est là qu'il colle qui borne l'écran, sans quoi la page défile trop peu (T, 767×640).
		[qs('.structure-template-main-title'), stepWindow.closest('.util-table')?.qs(':scope > .util-table-head')].forEach(head => {

			if(head === null || head === undefined) {
				return;
			}

			const style = getComputedStyle(head);

			if(['sticky', 'fixed'].includes(style.position) === false) {
				return;
			}

			const bounds = head.getBoundingClientRect();
			const stuck = parseFloat(style.top);

			if(bounds.bottom <= windowBounds.top) {
				top = Math.max(top, Number.isNaN(stuck) ? bounds.bottom : Math.min(bounds.bottom, stuck + bounds.height));
			}

		});

		return {top, bottom: bars.bottom, bars};

	}

	/*
	 * Une fenêtre qui ne flotte pas s'ouvre là où elle est, et peut tomber hors de l'écran : rendue au flux dans
	 * le tiroir sous 768, sur sa propre ligne (constat de P, 3d), sous les barres du bas. Ce qui la porte, le
	 * panneau ou la page, défile jusqu'à montrer son bas, sans faire passer sous ce qui borne le haut la tuile,
	 * dont la durée suit chaque pas.
	 */
	static reveal(stepWindow) {

		const tileBounds = stepWindow.previousElementSibling.getBoundingClientRect();
		const scroller = stepWindow.closest('.panel') ?? window;
		const {top, bottom} = this.getScreen(stepWindow, tileBounds);
		const end = stepWindow.getBoundingClientRect().bottom;

		if(end > bottom) {
			scroller.scrollBy(0, Math.min(end - bottom, tileBounds.top - top));
		}

	}

	/*
	 * « Enregistrer » et Entrée : la durée part si elle a changé, puis la fenêtre à tuile se ferme (FJ, retour
	 * 411, qui défait le retour 396). Fermée, elle garde la durée envoyée, écrite comme la ligne la lira : 3 h 75
	 * devient 4 h 15, même quand la réponse ne redessine pas le champ (temps prévu de la grille). Un refus la
	 * rouvre sur la durée refusée (`refuse()`). Une fenêtre sans tuile reste ouverte jusqu'à la réponse : son
	 * écran se retire si le serveur n'a pas refusé, et la garde sinon. Son consommateur se lit avant l'envoi : la
	 * réponse du Jour redessine la colonne, et le champ qu'elle a remplacé ne répond plus à aucun sélecteur.
	 */
	static save(field) {

		const consumer = this.find(field);
		const sent = this.send(field, this.get(field));

		if(this.isBare(this.getWindow(field)) === false) {
			this.close(field);
		} else {
			Promise.resolve(sent).then(refused => {
				if(refused !== true) {
					consumer.dismiss(field);
				}
			});
		}

	}

	// La poubelle (`DurationUi::getStepPanel()`) : une durée nulle part comme d'« Enregistrer », et le serveur
	// supprime le temps du jour.
	static erase(field) {

		this.write(field, 0);
		this.save(field);

	}

	/*
	 * Rend la promesse de la réponse, qui dit si le serveur a refusé, ou rien quand rien n'est parti.
	 */
	static send(field, time) {

		const before = field.dataset.sent;
		const key = field.dataset.duration;

		if(this.getSent(field) === time) {
			return undefined;
		}

		field.dataset.sent = time;

		// La case qui a le focus à l'envoi, Entrée tapée dedans : le redessin la retire, et `resume()` le rend à
		// celle du même nom.
		const focused = this.getInputs(field).find(input => input === document.activeElement)?.name ?? null;
		const sent = this.find(field).change(field, time);

		if(sent instanceof Promise === false) {
			return undefined;
		}

		this.pending.set(key, {count: (this.pending.get(key)?.count ?? 0) + 1, time: time});

		return sent.then(json => {

			const waiting = this.settle(key);
			const refused = PlanningDuration.refused(json);

			if(refused) {
				this.refuse(field, time, before);
			} else {
				this.resume(key, waiting, field, focused);
			}

			return refused;

		}, () => {
			this.settle(key);
			return false;
		});

	}

	/*
	 * Une réponse est arrivée : rend la durée encore en route, ou `null` quand plus rien n'attend.
	 */
	static settle(key) {

		const pending = this.pending.get(key);

		pending.count--;

		if(pending.count === 0) {
			this.pending.delete(key);
			return null;
		}

		return pending.time;

	}

	/*
	 * Après le redessin d'une réponse, le champ reprend la durée encore en route, sans glissé : pour celui qui
	 * regarde, rien n'a bougé. À défaut, il reprend celle que l'ancien champ (`former`, retiré par le redessin)
	 * montrait sans l'avoir encore envoyée, un pas ou une frappe faits pendant l'envoi. La fenêtre ouverte
	 * entre-temps se rouvre dans le nœud redessiné, comme « Journée » que la réponse redessine avec les chiffres
	 * du jour (`PlanningDuration.reopen()`), et la case qui avait le focus le retrouve quand sa fenêtre est
	 * celle-là, sauf s'il est passé ailleurs entre-temps : une fenêtre fermée n'a pas de case à montrer.
	 */
	static resume(key, waiting, former, focused) {

		const redrawn = (former.isConnected === false);

		if(waiting !== null) {

			qs('.planning-duration-step[data-duration="'+ key +'"]', field => {
				field.dataset.sent = waiting;
				this.write(field, waiting);
			});

		} else if(redrawn) {
			this.carry(key, former);
		}

		if(this.opened !== null) {

			qs('.planning-duration-step[data-duration="'+ this.opened +'"]', field => {

				const stepWindow = this.getWindow(field);

				if(this.isOpen(stepWindow) === false) {
					stepWindow.classList.add('is-open');
					this.place(stepWindow);
				}

			});

		}

		if(redrawn && focused !== null && this.opened === key && (document.activeElement === null || document.activeElement === document.body)) {
			this.focus(key, focused);
		}

		PlanningDuration.reopen();

	}

	/*
	 * Le champ redessiné de clé `key` reprend la durée que `former`, l'ancien nœud, montrait au-delà de sa dernière
	 * durée écrite, sans l'envoyer : elle attend son « Enregistrer », comme avant le redessin. ET le rappelle
	 * depuis la réponse du menu d'un jour.
	 */
	static carry(key, former) {

		const shown = this.get(former);

		if(shown === this.getSent(former)) {
			return;
		}

		qs('.planning-duration-step[data-duration="'+ key +'"]', field => this.write(field, shown));

	}

	// Rend le focus à la case `name` (heures ou minutes) du champ redessiné de clé `key`, sans faire défiler.
	static focus(key, name) {
		qs('.planning-duration-step[data-duration="'+ key +'"] input[name="'+ name +'"]', input => input.focus({preventScroll: true}));
	}

	/*
	 * Un refus ne redessine rien. La dernière durée écrite redevient celle d'avant, sauf si un envoi plus récent
	 * est parti depuis, et la fenêtre se rouvre sur la durée refusée, où son message se lit, sauf quand une
	 * autre fenêtre s'est ouverte depuis : un refus ne referme jamais une fenêtre qu'il n'a pas envoyée. Une
	 * fenêtre sans tuile n'a rien à rouvrir : elle montre encore la durée refusée.
	 */
	static refuse(field, time, before) {

		if(field.isConnected === false || parseFloat(field.dataset.sent) !== time) {
			return;
		}

		field.dataset.sent = before;

		if(this.isBare(this.getWindow(field))) {
			return;
		}

		// Un glisser a déjà écrit la durée refusée sur la tuile, que la réponse ne redessine pas.
		this.tile(this.getTile(field), parseFloat(before));

		if(qs('.planning-duration-step-window.is-open:not(.planning-duration-step-bare), .planning-duration-tiled > .planning-time-panel.is-open') === null) {
			this.show(field, time);
			this.open(field);
		}

	}

	/*
	 * La valeur cachée du champ de la fiche de temps (`TimesheetUi`, `timeAdd`) suit les cases, comme
	 * `DurationUi::getStepped()` la rend : sur la grille de la Semaine, dont la réponse ne redessine pas le
	 * champ, l'écran reste celui d'un rechargement.
	 */
	static write(field, time) {

		const minutes = Math.round(time * 60);
		const [hour, minute] = this.getInputs(field);

		hour.value = Math.floor(minutes / 60);
		minute.value = String(minutes % 60).padStart(2, '0');

		field.qs('input[name="timeAdd"]', input => {
			input.value = time;
		});

		field.classList.toggle('planning-duration-step-spent', time > 0 && field.classList.contains('planning-duration-step-expected') === false);
		field.classList.toggle('planning-duration-step-zero', time <= 0);

	}

	/*
	 * Écrit la durée comme `DurationUi::getStepped()` la rend, sans l'envoyer. Seule la valeur glisse d'un
	 * cran, vers le haut quand elle grandit (retour 133) ; la classe se repose à l'image suivante pour
	 * relancer l'animation sur un champ qui la porte encore, sans relire la mise en page, et seulement dans
	 * une fenêtre encore ouverte : posée dans une fenêtre fermée, elle rejouerait à la réouverture.
	 */
	static show(field, time) {

		const group = field.qs('.timesheet-time-field-group');
		const before = this.get(field);

		this.write(field, time);

		if(time !== before) {
			this.stopRolling(group);
			requestAnimationFrame(() => {
				if(this.isOpen(this.getWindow(field))) {
					group.classList.add(time > before ? 'planning-duration-step-roll-up' : 'planning-duration-step-roll-down');
				}
			});
		}

	}

}

/*
 * Le temps passé d'une saisie de récolte (`HarvestEntryUi::getTimeWindow()`), au tiroir, aux panneaux et sur
 * la feuille du tactile, et le temps prévu de « Nouvelle intervention »
 * (`HarvestEntryUi::getTimeExpectedField()`), dans le champ par pas (R-Retours-0810, 208). Rien ne part au serveur
 * avant l'envoi du formulaire : chaque pas de la saisie de récolte, en ligne (lot BW), ou « Enregistrer » de la
 * fenêtre des deux autres, reporte la durée dans le champ caché, qui la
 * poste en heures, désactivé tant qu'elle vaut zéro, pour qu'une saisie sans temps ne poste pas de durée, que
 * `doHarvest` ne construit alors pas.
 */
PlanningDurationStep.register({
	selector: '.planning-harvest-time .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningHarvestTime.write(field.closest('.planning-harvest-time'), time),
	// Toujours ouverts (`DurationUi::getStepInline()`), les pas n'ont pas d'écran à retirer : Entrée les laisse en
	// place, la durée déjà reportée.
	dismiss: () => {}
});

document.delegateEventListener('change', '#panel-planning-harvest-create input[name="date"]', function() {

	this.form.qs('[data-planning-harvest-time-labels]', group => PlanningHarvestTime.day(group, this.value));

});

// Ouvert, le menu est sous `body` (`Lime.Dropdown.open()`) : le délégué part de l'entrée, et le jour se
// rapporte au tiroir par le bouton du menu (`list.button`).
document.delegateEventListener('click', '.dropdown-list [data-planning-harvest-time-date]', function(e) {

	e.preventDefault();

	PlanningHarvestTime.choose(this);

});

// Même chose pour « Ajouter une personne » (`HarvestEntryUi::getTimeAdd()`).
document.delegateEventListener('click', '.planning-harvest-time-add-list [data-planning-harvest-add]', function(e) {

	e.preventDefault();

	PlanningHarvestTime.add(this);

});

class PlanningHarvestTime {

	/*
	 * Les libellés des jours qu'on peut choisir sont rendus par `HarvestEntryUi::create()` ; un jour hors de la
	 * semaine, que le serveur refusera, laisse le libellé du dernier jour lu.
	 */
	static day(group, date) {

		const labels = JSON.parse(group.dataset.planningHarvestTimeLabels);

		if(labels[date] !== undefined) {
			group.qs('[data-planning-harvest-time-day]').textContent = labels[date];
		}

	}

	/*
	 * Le jour du temps au tiroir (`HarvestEntryUi::getDrawerTime()`) : le titre prend celui du jour choisi, et
	 * `timeDate` le poste. La récolte reste sur le jour du tiroir.
	 */
	static choose(item) {

		const list = item.closest('.dropdown-list');
		const title = list.button;

		title.parentElement.qs('input[name="timeDate"]').value = item.dataset.planningHarvestTimeDate;
		title.textContent = item.dataset.planningHarvestTimeLabel;

		list.qsa('[data-planning-harvest-time-date]', node => node.classList.toggle('selected', node === item));

		title.parentElement.qsa('.planning-harvest-time[data-planning-harvest-replace]', wrapper => this.reset(wrapper));

	}

	/*
	 * Une personne montrée par « Ajouter une personne » le reste, quel que soit le geste, et arrive avec ses pas
	 * ouverts. Sur la feuille, le corps défile jusqu'à elle (`PlanningHarvestSheet.reveal()`).
	 */
	static add(item) {

		const form = item.closest('.dropdown-list').button.closest('form');
		const person = form.qs('[data-planning-harvest-person="'+ item.dataset.planningHarvestAdd +'"]');

		person.toggleAttribute('data-planning-harvest-added', true);

		this.team(form);

		const sheet = form.closest('#planning-harvest-sheet');

		// La personne est en `display: contents` et ne se mesure pas : c'est sa ligne qui se montre.
		if(sheet !== null) {
			PlanningHarvestSheet.reveal(sheet, person.qs('.util-table-row'));
		}

	}

	/*
	 * Les personnes montrées (R-Retours-0810, 224) : la personne connectée, celles qu'« Ajouter une personne » a
	 * montrées, et en remplacement celles qui ont du temps sur l'intervention, dont on corrige alors le temps.
	 * La durée d'une personne cachée ne part pas, et le menu ne propose que les personnes cachées. Le geste se
	 * lit sur le temps (`data-planning-harvest-replace`), que la feuille bascule quand il change.
	 */
	static team(form) {

		const replace = (form.qs('.planning-harvest-time[data-planning-harvest-replace]') !== null);
		const shown = new Set();

		form.qsa('[data-planning-harvest-person]', person => {

			const visible = (
				person.dataset.planningHarvestOnline !== undefined ||
				person.dataset.planningHarvestAdded !== undefined ||
				(replace && person.dataset.planningHarvestWorked !== undefined)
			);

			person.classList.toggle('hide', visible === false);

			if(visible) {
				shown.add(person.dataset.planningHarvestPerson);
			}

			const wrapper = this.getPersonTime(form, person);

			this.write(wrapper, this.getSent(wrapper));

		});

		// Le menu ouvert est déplacé hors du formulaire et laisse à sa place un `dropdown-placeholder` : la liste se
		// retrouve par son bouton, jamais par son voisin, sans quoi un ajout cache « Ajouter une personne ».
		form.qsa('.planning-harvest-time-add', button => {

			const list = Lime.Dropdown.getListFromButton(button);

			list.qsa('[data-planning-harvest-add]', item => item.classList.toggle('hide', shown.has(item.dataset.planningHarvestAdd)));

			button.classList.toggle('hide', list.qs('[data-planning-harvest-add]:not(.hide)') === null);

		});

	}

	static getPersonTime(form, person) {
		return form.qs('.planning-harvest-time[data-planning-harvest-user="'+ person.dataset.planningHarvestPerson +'"]');
	}

	// La durée reportée en dernier (`PlanningDurationStep.send()`).
	static getSent(wrapper) {
		return parseFloat(wrapper.qs('.planning-duration-step').dataset.sent);
	}

	/*
	 * La durée d'où repart la tuile quand le jour du temps ou le geste changent (R-Retours-0810, 219) : en
	 * remplacement, le temps que la personne a passé ce jour-là sur l'intervention
	 * (`HarvestEntryUi::getTimeWindow()`), sinon zéro. Une durée choisie et non envoyée est abandonnée, elle
	 * valait pour un autre jour ou un autre geste.
	 */
	static reset(wrapper) {

		const form = wrapper.closest('form');
		const date = (form.qs('input[name="timeDate"]') ?? form.qs('input[name="date"]')).value;
		const time = (wrapper.dataset.planningHarvestReplace === undefined) ? 0 : JSON.parse(wrapper.dataset.planningHarvestSpent)[date];

		const field = wrapper.qs('.planning-duration-step');

		PlanningDurationStep.close(field);

		field.dataset.sent = time;

		PlanningDurationStep.write(field, time);

		this.write(wrapper, time);

	}

	static getHidden(wrapper) {
		return wrapper.qs(':scope > input[type="hidden"]');
	}

	// La durée qui partira avec le formulaire.
	static get(wrapper) {

		const hidden = this.getHidden(wrapper);

		return hidden.disabled ? 0 : parseFloat(hidden.value);

	}

	/*
	 * Reporte la durée dans le champ caché et la feuille. En remplacement, elle part même nulle : elle retire
	 * alors le temps du jour. Celle d'une personne cachée ne part pas.
	 */
	static write(wrapper, time) {

		const hidden = this.getHidden(wrapper);
		const replace = (wrapper.dataset.planningHarvestReplace !== undefined);
		const away = (wrapper.closest('[data-planning-harvest-person]')?.classList.contains('hide') ?? false);

		hidden.value = (time > 0 || replace) ? String(time) : '';
		hidden.disabled = ((time === 0 && replace === false) || away);

		const sheet = wrapper.closest('#planning-harvest-sheet');

		if(sheet !== null) {
			PlanningHarvestSheet.update(sheet);
		} else {
			PlanningHarvestDrawer.check(wrapper.closest('form'));
		}

		// « Nouvelle intervention » sur plusieurs séries partage le temps de chaque personne (DG-5).
		wrapper.closest('form').qs('[data-planning-batch-harvest]', block => PlanningTaskBatchHarvest.update(block));

	}

}

/*
 * Le temps d'une équipe dans le formulaire d'un autre écran (`TimeTeamUi::getField()`), « Entretien fait »
 * du carnet le premier. Comme la récolte, rien ne part avant l'envoi : chaque durée part en heures par son
 * champ caché `time[<user>]`, actif quand la durée n'est pas nulle et que la personne est choisie. Seule
 * dans le bloc, la personne n'a pas de vignette et compte pour choisie.
 */
PlanningDurationStep.register({
	selector: '.planning-time-crew-duration .planning-duration-step',
	bounded: true,
	change: (field, time) => {
		PlanningDurationStep.tile(PlanningDurationStep.getTile(field), time);
		PlanningTimeTeam.update(field.closest('.planning-time-crew-duration'));
	}
});

document.delegateEventListener('click', '.planning-time-crew [data-planning-time-crew-user]', function(e) {

	e.preventDefault();

	this.classList.toggle('is-selected');

	PlanningTimeTeam.update(this.nextElementSibling);

});

class PlanningTimeTeam {

	// La durée est la dernière qu'« Enregistrer » a écrite (`data-sent`).
	static update(wrapper) {

		const time = parseFloat(wrapper.qs('.planning-duration-step').dataset.sent);
		const hidden = wrapper.qs(':scope > input[type="hidden"]');
		const user = wrapper.previousElementSibling;

		hidden.value = String(time);
		hidden.disabled = (time === 0 || (user !== null && user.classList.contains('is-selected') === false));

	}

}

/*
 * « Temps » sur un lot (`TaskBatchUi::getTime()`, lot BG) : un temps total pour un jour, partagé entre les lignes
 * selon une clé. Le jour, le total et la clé se reportent dans leurs champs, et chaque ligne montre sa part et
 * ce qui la fonde. Le serveur recalcule les parts à l'envoi (`TaskBatchLib::prepareTimes()`) : celles-ci n'en
 * sont que la lecture, par le même calcul (`share()`).
 *
 * Le total n'a pas d'« Enregistrer » : un pas, une frappe ou Entrée le reportent aussitôt, et il devient la
 * dernière durée écrite (`data-sent`), qu'Échap n'a donc pas à rendre.
 */
PlanningDurationStep.register({
	selector: '.planning-batch-time-total .planning-duration-step',
	bounded: true,
	change: field => PlanningTaskBatchTime.update(field.closest('form')),
	dismiss: field => PlanningTaskBatchTime.update(field.closest('form'))
});

document.delegateEventListener('click', '.planning-batch-time-total [data-planning-duration-step]', function() {
	PlanningTaskBatchTime.update(this.closest('form'));
});

document.delegateEventListener('input', '.planning-batch-time-total input', function() {
	PlanningTaskBatchTime.update(this.closest('form'));
});

document.delegateEventListener('change', '[data-planning-batch-time] input[name="distribution"]', function() {
	PlanningTaskBatchTime.update(this.form);
});

document.delegateEventListener('click', '[data-planning-batch-time-day]', function(e) {

	e.preventDefault();

	PlanningTaskBatchTime.day(this);

});

class PlanningTaskBatchTime {

	static day(day) {

		const days = day.closest('.util-days');
		const date = day.dataset.planningBatchTimeDay;

		days.qsa('[data-planning-batch-time-day]', node => {

			const selected = (node === day);

			node.classList.toggle('is-selected', selected);
			node.qs('.util-calendar', calendar => calendar.classList.toggle('util-calendar-selected', selected));

		});

		const form = days.closest('form');

		form.qs('input[name="date"]').value = date;

		this.update(form);

	}

	/*
	 * Le total en minutes, la clé, le jour, puis la part de chaque ligne ouverte.
	 */
	static update(form) {

		const field = form.qs('.planning-batch-time-total .planning-duration-step');
		const time = PlanningDurationStep.get(field);
		const minutes = Math.round(time * 60);
		const distribution = form.qs('input[name="distribution"]:checked, input[type="hidden"][name="distribution"]').value;
		const date = form.qs('input[name="date"]').value;

		field.dataset.sent = time;
		form.qs('input[name="time"]').value = time;

		const shares = [...form.qsa('[data-planning-batch-time-task]')].map(share => ({
			share: share,
			base: this.getBase(JSON.parse(share.dataset.bases), distribution, date)
		}));

		this.share(shares.filter(line => line.base > 0), distribution, minutes);

		shares.forEach(({share, base, part}) => {

			const value = (base > 0) ? part : 0;
			const node = share.qs('.planning-batch-time-part');

			node.innerHTML = (value > 0) ?
				'<span class="badge badge-time-spent">'+ PlanningTask.format(value / 60) +'</span>' :
				PlanningTask.format(0);

			share.qsa('[data-basis]', basis => basis.classList.toggle('hide', basis.dataset.basis !== distribution && basis.dataset.basis !== 'harvest-'+ date));

		});

	}

	// Comme `TaskBatchLib::getBase()`.
	static getBase(bases, distribution, date) {

		switch(distribution) {
			case 'harvest' :
				return bases.harvest[date] ?? 0;
			case 'fair' :
				return 1;
			default :
				return bases[distribution] ?? 0;
		}

	}

	/*
	 * Le partage de `\series\TaskLib::distribute()` à la minute, dans le même ordre d'opérations pour arriver aux
	 * mêmes arrondis : chaque ligne reçoit la partie entière de sa part, et les minutes qui restent vont une à une
	 * aux premières lignes.
	 */
	static share(lines, distribution, minutes) {

		if(lines.length === 0) {
			return;
		}

		if(lines.length === 1) {
			lines[0].part = minutes;
			return;
		}

		let remain = minutes;

		if(distribution === 'fair') {

			const part = Math.floor(minutes / lines.length);

			lines.forEach(line => line.part = part);
			remain = minutes - part * lines.length;

		} else {

			const total = lines.reduce((sum, line) => sum + line.base, 0);

			lines.forEach(line => {
				line.part = Math.floor(line.base / total * minutes);
				remain -= line.part;
			});

		}

		lines.forEach(line => {
			if(remain-- > 0) {
				line.part++;
			}
		});

	}

}

/*
 * « Récolter » sur un lot (`TaskBatchUi::getHarvest()`, DG-1) : une quantité pour un jour, et le temps de chaque
 * personne quand la structure le suit (HM), partagés entre les séries par la même clé. Chaque ligne montre sa récolte, son temps et ce
 * qui les fonde. Le serveur recalcule les parts à l'envoi (`TaskBatchLib::prepareHarvests()`) : celles-ci n'en
 * sont que la lecture, par le partage de « Temps » (`PlanningTaskBatchTime.share()`), la quantité comptée en
 * dixièmes comme `\series\TaskLib::distribute()` la compte.
 */
document.delegateEventListener('input', '[data-planning-batch-harvest] :is(input[name="harvestMore"], input[name="crateWeight"])', function() {
	PlanningTaskBatchHarvest.update(this.closest('[data-planning-batch-harvest]'));
});

document.delegateEventListener('change', '[data-planning-batch-harvest] :is(input[name="distribution"], select[data-planning-harvest-crate])', function() {
	PlanningTaskBatchHarvest.update(this.closest('[data-planning-batch-harvest]'));
});

document.delegateEventListener('click', '[data-planning-batch-harvest-day]', function(e) {

	e.preventDefault();

	PlanningTaskBatchHarvest.day(this);

});

// Le couple choisi aux Récoltes (HM2) redessine ce qui en dépend, la saisie restant en place. Les parts se
// relisent ensuite, rendues à zéro. Un refus ramène le champ au couple que le panneau montre. Les champs se
// figent le temps de la réponse : un second choix serait refusé par le verrou du formulaire sans être envoyé,
// et l'aperçu ne dirait plus le couple posté.
document.delegateEventListener('change', '[data-planning-batch-harvest-choice] select', function() {
	PlanningTaskBatchHarvest.choose(this);
});

document.addEventListener('ajaxAfterFetch', e => {

	const select = e.detail.trigger;

	if(select?.matches('[data-planning-batch-harvest-choice] select') !== true) {
		return;
	}

	select.closest('[data-planning-batch-harvest-choice]').qsa('select', field => field.disabled = false);

	if(e.detail.failed) {
		Array.from(select.options).forEach(option => option.selected = option.defaultSelected);
		return;
	}

	Array.from(select.options).forEach(option => option.defaultSelected = option.selected);
	select.form.qs('[data-planning-batch-harvest]', block => PlanningTaskBatchHarvest.update(block));

});

class PlanningTaskBatchHarvest {

	static day(day) {

		const days = day.closest('.util-days');

		days.qsa('[data-planning-batch-harvest-day]', node => {

			const selected = (node === day);

			node.classList.toggle('is-selected', selected);
			node.qs('.util-calendar', calendar => calendar.classList.toggle('util-calendar-selected', selected));

		});

		days.closest('form').qs('input[name="date"]').value = day.dataset.planningBatchHarvestDay;

	}

	static choose(select) {

		const choice = select.closest('[data-planning-batch-harvest-choice]');
		const url = choice.dataset.planningBatchHarvestChoice +'?'+ new URLSearchParams(new FormData(select.form)).toString();

		// Après la lecture du formulaire : un champ éteint ne part pas.
		choice.qsa('select', field => field.disabled = true);

		new Ajax.Query(select.form)
			.trigger(select)
			.method('get')
			.url(url)
			.fetch();

	}

	// L'unité de la saisie : une autre unité choisie part dans `harvestUnit` (`PlanningHarvestCrate.task()`).
	static unit(block) {

		const field = block.closest('form').elements['harvestUnit'];

		return (field !== undefined && field.disabled === false && field.value !== '') ? field.value : block.dataset.unit;

	}

	/*
	 * Les minutes à partager, comme le serveur les partage (`TaskBatchUi::getHarvestBlock()`) : celles de chaque
	 * personne une à une dans « Récolter » (`data-time-split`), leur total dans « Nouvelle intervention » (DG-5).
	 * Une durée part par son champ caché `time[<user>]`, éteint quand elle ne part pas (`PlanningHarvestTime.write()`).
	 */
	static minutes(block) {

		const times = Array.from(block.closest('form').qsa('input[type="hidden"][name^="time["]:not(:disabled)'))
			.map(hidden => parseFloat(hidden.value) || 0);

		return (block.dataset.timeSplit !== undefined) ?
			times.map(time => Math.round(time * 60)) :
			[Math.round(times.reduce((sum, time) => sum + time, 0) * 60)];

	}

	/*
	 * La quantité en dixièmes et le temps en minutes, la clé, puis les parts de chaque ligne. Des caisses se lisent
	 * en kilos (`PlanningHarvestCrate.update()`), comme le serveur les partage.
	 */
	static update(block) {

		const form = block.closest('form');

		const kilos = PlanningHarvestCrate.update(form);
		const typed = PlanningHarvestCrate.read(form.elements['harvestMore'].value);
		const quantity = PlanningHarvestCrate.isOn(form) ? (kilos ?? 0) : (isNaN(typed) ? 0 : typed);

		const distribution = block.qs('input[name="distribution"]:checked, input[type="hidden"][name="distribution"]').value;

		const units = JSON.parse(block.dataset.units);
		const unit = PlanningHarvestCrate.isOn(form) ? 'kg' : this.unit(block);

		const shares = [...block.qsa('[data-planning-batch-harvest-share]')].map(share => ({
			share: share,
			base: PlanningTaskBatchTime.getBase(JSON.parse(share.dataset.bases), distribution, null)
		}));

		const quantities = shares.map(line => ({base: line.base}));
		const times = shares.map(() => 0);

		PlanningTaskBatchTime.share(quantities.filter(line => line.base > 0), distribution, Math.round(quantity * 10));

		this.minutes(block).forEach(minutes => {

			const lines = shares.map(line => ({base: line.base}));

			PlanningTaskBatchTime.share(lines.filter(line => line.base > 0), distribution, minutes);
			lines.forEach((line, index) => times[index] += (line.base > 0) ? line.part : 0);

		});

		shares.forEach(({share}, index) => {

			const part = (quantities[index].base > 0) ? quantities[index].part / 10 : 0;

			share.qs('.planning-batch-harvest-part').textContent = PlanningHarvestCrate.format(part) +' '+ units[unit];

			share.qs('.planning-batch-time-part', node => {

				const value = times[index];

				node.innerHTML = (value > 0) ?
					'<span class="badge badge-time-spent">'+ PlanningTask.format(value / 60) +'</span>' :
					PlanningTask.format(0);

			});

			share.qsa('[data-basis]', basis => basis.classList.toggle('hide', basis.dataset.basis !== distribution));

		});

	}

}

/*
 * Une récolte en caisses (`HarvestEntryUi::getCrate()`) : sa conversion en kilos sous la quantité, et le
 * champ du poids d'une caisse, montré tant que l'espèce n'en a pas. Le serveur fait seul la conversion
 * qu'il écrit (`TaskLib::writeHarvestForWeek()`) : celle-ci n'est qu'une lecture.
 */
class PlanningHarvestCrate {

	static isOn(form) {

		const field = form.elements['crate'];

		return (field !== undefined && field.value === '1');

	}

	/*
	 * Le select du champ garde « caisses » pour un couple en kilos seulement, et les autres unités
	 * (`HarvestEntryUi::getUnitChoices()`), qui suivent l'unité du couple et ses caisses. Sur un couple (HN-B2c),
	 * chacune annonce l'intervention du couple dans son unité (`task()`), celle de la culture comprise, et celle
	 * du couple, déjà en tête, se cache (`isHidden()`). En remplacement, une autre unité ne se garde que là où son
	 * choix rouvre la saisie sur elle (`reopen()`, en Semaine) : ailleurs, le champ montre la quantité de l'unité de la case, et
	 * une autre unité remplacerait une quantité que l'écran ne montre pas. Une option éteinte rend le choix à
	 * l'unité du couple.
	 */
	static unit(select, unit, whole, mode = 'add') {

		this.own(select).textContent = unit.label;
		select.dataset.crate = unit.crate ? '1' : '0';
		select.dataset.whole = whole ? '1' : '0';
		select.dataset.unit = unit.value;

		this.allow(select, mode);

	}

	/*
	 * L'unité de la saisie (`HarvestEntryUi::getUnitChoices()`), en tête de la liste sauf rouverte sur une
	 * autre unité, où la liste garde l'ordre de l'ouverture.
	 */
	static own(select) {
		return select.qs('option[data-planning-harvest-own]') ?? select.options[0];
	}

	/*
	 * Les options que le couple (`unit()`) et le geste laissent : toutes tant qu'aucun couple n'est choisi. Les
	 * caisses d'une autre unité ne se saisissent qu'en la rouvrant (`reopen()`), donc en remplacement seulement :
	 * ailleurs, elles posteraient leur intervention sans le poids d'une caisse. En remplacement, une unité qui a
	 * sa rangée (`HarvestEntryUi::getOthers()`) s'y remplace et ne se rouvre pas. En remplacement toujours, une
	 * autre unité qui ne se choisit pas se cache (M-Planning, 21/10, recette de HN-B2c) : la liste ne garde que
	 * ce qui remplace, alors que l'ajout montre ce que le couple éteint.
	 */
	static allow(select, mode = 'add') {

		const reopen = (mode === 'replace' && select.dataset.planningHarvestReopen !== undefined);

		for(const option of select.options) {

			const row = (mode === 'replace' && this.hasRow(select, option));
			const other = (option.dataset.planningHarvestOwn === undefined);

			if(option.value === '1') {
				option.disabled = (
					select.dataset.crate === '0' ||
					(other && (reopen === false || row))
				);
				option.hidden = (mode === 'replace' && other && option.disabled);
			} else if(other) {
				option.disabled = (
					this.isHidden(select, option) ||
					row ||
					(mode === 'replace' && reopen === false)
				);
				option.hidden = (this.isHidden(select, option) || (mode === 'replace' && option.disabled));
			}

		}

		if(select.selectedOptions[0].disabled) {
			this.own(select).selected = true;
		}

		this.task(select);

	}

	/*
	 * En remplacement, une autre unité choisie rouvre la saisie sur elle (lot GO), avec l'adresse que la liste
	 * porte (`HarvestEntryUi::getReopenUrl()`), l'intervention ou l'unité de l'option en plus : le tiroir et la
	 * feuille montrent alors la quantité et les durées de l'intervention qui les recevra. L'unité de la saisie et
	 * ses caisses, qui ne changent pas d'intervention, se choisissent sans revenir au serveur ; les caisses d'une
	 * autre unité la rouvrent caisses choisies (`crate`). Une liste sans adresse ne rouvre jamais, et son
	 * formulaire n'a pas de geste à lire (« Récolter » sur des séries cochées n'a pas de champ `mode`).
	 */
	static reopen(select) {

		const url = select.dataset.planningHarvestReopen;

		if(url === undefined) {
			return false;
		}

		const option = select.selectedOptions[0];
		const sheet = select.closest('#planning-harvest-sheet');
		const mode = (sheet !== null) ? sheet.dataset.mode : select.form.elements['mode'].value;

		if(mode !== 'replace' || option.dataset.planningHarvestOwn !== undefined) {
			return false;
		}

		const chosen = option.dataset;
		const urlChosen = (chosen.planningHarvestTask !== undefined) ? url.setArgument('task', chosen.planningHarvestTask) : url.setArgument('harvestUnit', chosen.planningHarvestOther);

		new Ajax.Query(select)
			.method('get')
			.url((option.value === '1') ? urlChosen.setArgument('crate', 1) : urlChosen)
			.fetch();

		return true;

	}

	static hasRow(select, option) {
		return (
			option.dataset.planningHarvestStockUnit !== undefined &&
			select.form.qs('[data-planning-harvest-row="'+ option.dataset.planningHarvestStockUnit +'"]') !== null
		);
	}

	/*
	 * Sans couple, la seconde option de l'unité de la saisie n'a pas lieu d'être, la première la portant déjà ;
	 * sur un couple, c'est l'autre unité égale à celle du couple, que la première porte alors.
	 */
	static isHidden(select, option) {

		if(select.dataset.whole === '0') {
			return (option.dataset.planningHarvestStockUnit === select.dataset.unit);
		} else {
			return (option.dataset.planningHarvestCouple !== undefined);
		}

	}

	/*
	 * Une autre unité de la semaine désigne son intervention, que le champ caché `task` poste, et une unité
	 * que la semaine ne porte pas se poste elle-même (`harvestUnit`) ; l'unité de la règle ne désigne rien, et
	 * les deux champs s'éteignent. À la feuille ouverte depuis une case qui désigne son intervention, l'unité
	 * de la case et ses caisses la désignent encore, à la feuille comme au tiroir. Depuis une carte de la Semaine,
	 * ou pour une part, `task` est le sien, qu'aucun choix ne change : le formulaire n'a pas de champ
	 * `harvestUnit`. « Nouvelle intervention », qui crée son intervention, n'a pas de champ `task`. Sur un couple,
	 * une autre unité ne désigne rien et se nomme : l'intervention de la culture entière qu'elle porte n'est pas
	 * celle du couple (`HarvestWeekLib::getTaskForChoice()`).
	 */
	static task(select) {

		const fields = select.form.elements;

		if(fields['harvestUnit'] === undefined) {
			return;
		}

		const chosen = select.selectedOptions[0].dataset;
		const couple = (select.dataset.whole === '0' && chosen.planningHarvestOwn === undefined);

		if(fields['task'] !== undefined) {
			fields['task'].value = couple ? '' : (chosen.planningHarvestTask ?? '');
			fields['task'].disabled = (fields['task'].value === '');
		}

		fields['harvestUnit'].value = couple ? chosen.planningHarvestStockUnit : (chosen.planningHarvestOther ?? '');
		fields['harvestUnit'].disabled = (fields['harvestUnit'].value === '');

		PlanningHarvestStock.reload(select.form);

	}

	/*
	 * Rend les kilos de la saisie en caisses, NULL hors caisses ou tant que la quantité et le poids ne sont pas lus.
	 */
	static update(form) {

		const crate = form.qs('[data-planning-crate]');

		if(crate === null) {
			return null;
		}

		const on = this.isOn(form);

		crate.classList.toggle('hide', on === false);

		// Le champ du geste, que la feuille renomme. Le clavier du téléphone perd sa virgule en caisses, qui se
		// comptent.
		const quantityField = form.qs(':is(input[name="harvestMore"], input[name="harvestDay"]):enabled');

		if(quantityField !== null) {
			quantityField.inputMode = on ? 'numeric' : 'decimal';
		}
		const weightField = crate.qs('input[name="crateWeight"]');

		const quantity = (quantityField === null) ? NaN : this.read(quantityField.value);
		const weightTyped = (weightField !== null && weightField.closest('.hide') === null) ? this.read(weightField.value) : NaN;
		const weight = isNaN(weightTyped) ? this.read(crate.dataset.crateWeight) : weightTyped;

		const conversion = crate.qs('[data-planning-crate-conversion]');

		if(on === false || isNaN(quantity) || quantity <= 0 || isNaN(weight) || weight <= 0) {
			conversion.textContent = '';
			return null;
		}

		const kilos = Math.round(quantity * weight * 10) / 10;
		const label = (quantity < 2) ? crate.dataset.crateSingular : crate.dataset.cratePlural;

		conversion.textContent = this.format(quantity) +' '+ label +' · '+ this.format(kilos) +' kg';

		return kilos;

	}

	/*
	 * Un remplacement s'ouvre sur les kilos du jour : ce nombre ne vaut pas des caisses, le passage en caisses
	 * le retire, et le retour en kilos le remet tant que rien n'a été tapé. Une saisie rouverte en caisses
	 * (`HarvestEntryUi::getUnitChoices()`) le retire dès l'ouverture.
	 */
	static prefill(form) {

		const field = form.elements['harvestDay'];

		if(field === undefined || field.defaultValue === '') {
			return;
		}

		if(this.isOn(form)) {
			if(field.value === field.defaultValue) {
				field.value = '';
			}
		} else if(field.value === '') {
			field.value = field.defaultValue;
		}

	}

	static read(value) {
		return (value === '') ? NaN : parseFloat(value.replace(',', '.'));
	}

	static format(value) {
		return String(value).replace('.', ',');
	}

	/*
	 * Le choix de l'unité dit le poids d'une caisse (`HarvestEntryUi::getCrateUnit()`) : le poids tapé le
	 * réécrit, et un champ vidé rend celui de l'espèce, comme la conversion.
	 */
	static label(crate) {

		const typed = this.read(crate.qs('input[name="crateWeight"]').value);
		const weight = isNaN(typed) ? this.read(crate.dataset.crateWeight) : typed;
		const label = (isNaN(weight) || weight <= 0) ? crate.dataset.cratePlural : crate.dataset.crateUnit.replace('{value}', this.format(weight));

		const form = crate.closest('form');

		form.qs('select[data-planning-harvest-crate] option[value="1"]', option => option.textContent = label);

	}

}

/*
 * La feuille du tactile (`HarvestEntryUi::getSheet()`), le formulaire Récolter saisi au clavier du téléphone.
 *
 * Elle se pose sur le `body`, hors de la liste, et le défilement de la page se bloque tant qu'elle est
 * dans le document (`planning.css`, `:has(#planning-harvest-sheet)`) : la fermer, c'est la retirer, et
 * la réponse d'une saisie la retire aussi. Rien d'autre ne garde d'état que la feuille elle-même.
 */
document.delegateEventListener('change', '#planning-harvest-sheet [data-planning-sheet-mode] input', function() {

	PlanningHarvestSheet.mode(this.closest('#planning-harvest-sheet'), this.value);

});

document.delegateEventListener('change', '#planning-harvest-sheet select[data-planning-sheet-species]', function() {

	PlanningHarvestSheet.species(this);

});

// Le refus d'une personne s'écrit sous sa ligne, dans le volet : le volet défile pour qu'un écran bas ne garde
// pas le message sous le pied de la feuille.
document.addEventListener('ajaxAfterFetch', e => {

	const sheet = e.detail.trigger?.closest('#planning-harvest-sheet');

	if(
		sheet?.isConnected !== true ||
		sheet.qs('[data-planning-sheet-pane="time"] .form-error-message') === null
	) {
		return;
	}

	PlanningHarvestSheet.reveal(sheet);

});

document.delegateEventListener('click', '#planning-harvest-sheet [data-planning-sheet-close]', function(e) {

	e.preventDefault();

	PlanningHarvestSheet.close(this.closest('#planning-harvest-sheet'));

});

// Entrée envoie le formulaire d'elle-même, tant que son bouton n'est pas éteint.
document.delegateEventListener('keydown', '#planning-harvest-sheet', function(e) {

	if(e.key === 'Escape') {
		e.preventDefault();
		PlanningHarvestSheet.close(this);
	}

});

// La feuille vit sur le `body`, hors de l'écran qu'une navigation ajax remplace : sans ce retrait, elle
// survivrait à l'écran qui l'a ouverte, le défilement bloqué.
document.addEventListener('navigation.wakeup', () => qs('#planning-harvest-sheet', sheet => sheet.remove()));

class PlanningHarvestSheet {

	static start(sheet) {

		// Ouverte en remplacement, la quantité du jour est déjà dans le champ (`HarvestEntryUi::getSheet()`).
		sheet.dataset.prefilled = (sheet.dataset.mode === 'replace') ? this.getField(sheet).value : '';

		// Le focus dans la feuille est ce qui lui fait recevoir Échap, sans ouvrir le clavier du téléphone sur
		// ce qu'elle montre.
		sheet.qs('.util-sheet-panel').focus();

		this.dock(sheet);

		sheet.qs('select[data-planning-harvest-crate]', select => {
			PlanningHarvestCrate.allow(select, sheet.dataset.mode);
			PlanningHarvestCrate.prefill(select.form);
		});

		this.update(sheet);

	}

	/*
	 * Le clavier du téléphone réduit la partie visible de la fenêtre sans changer `innerHeight` (Chrome Android
	 * depuis la version 108, Safari) : la feuille, posée au bas de la fenêtre, remonte d'autant pour que son pied
	 * reste au-dessus du clavier. Les écouteurs se retirent avec la feuille.
	 */
	static dock(sheet) {

		const viewport = window.visualViewport;

		if(viewport === undefined || viewport === null) {
			return;
		}

		const place = () => {

			if(sheet.isConnected === false) {
				viewport.removeEventListener('resize', place);
				viewport.removeEventListener('scroll', place);
				return;
			}

			sheet.style.bottom = Math.max(0, Math.round(window.innerHeight - viewport.height - viewport.offsetTop)) +'px';

		};

		viewport.addEventListener('resize', place);
		viewport.addEventListener('scroll', place);

		place();

	}

	static close(sheet) {
		sheet.remove();
	}

	/*
	 * Une autre espèce de la série rouvre la feuille sur sa culture, en ajout, au même jour
	 * (`HarvestEntryUi::getSpecies()`) : c'est là que la récolte s'écrira.
	 */
	static species(select) {

		PlanningHarvest.open(select, select.selectedOptions[0].dataset.url);

	}

	/*
	 * Une personne ajoutée, ou le volet qui porte un refus, peut descendre sous le pied d'un écran bas (844×390), ou
	 * se trouver au-dessus de ce que le corps montre quand il a défilé : le corps de la feuille défile juste assez
	 * pour montrer `target` entier, ou jusqu'à son haut quand il est plus grand que le corps, pour que sa première
	 * ligne reste lisible. Rien ne défile à l'ouverture, où le volet masquerait la quantité, qui se tape en premier.
	 */
	static reveal(sheet, target = sheet.qs('[data-planning-sheet-pane="time"]')) {

		const body = sheet.qs('.util-sheet-body');

		const bodyBounds = body.getBoundingClientRect();
		const targetBounds = target.getBoundingClientRect();

		if(targetBounds.top < bodyBounds.top) {
			body.scrollBy(0, targetBounds.top - bodyBounds.top);
		} else if(targetBounds.bottom > bodyBounds.bottom) {
			body.scrollBy(0, Math.min(targetBounds.bottom - bodyBounds.bottom, targetBounds.top - bodyBounds.top));
		}

	}

	/*
	 * Le geste choisi en tête : le champ de la quantité prend son nom (`harvestMore` ou `harvestDay`), et seuls
	 * ses morceaux se montrent (`HarvestEntryUi::getSheetFor()`), un `fieldset` qui ne vaut pas pour lui se
	 * désactivant. Le couple ne vaut qu'en ajout, les autres unités en remplacement seulement là où leur choix
	 * rouvre la saisie (`allow()`) : en quittant l'ajout, la saisie revient à l'unité de ce qu'elle vise, celle
	 * où se lit la quantité du jour que le remplacement pré-remplit. Les caisses, ou une autre unité, se
	 * rechoisissent ensuite en remplacement.
	 */
	static mode(sheet, mode) {

		sheet.dataset.mode = mode;

		// Le temps remplace en remplacement, et s'ajoute sinon : il repart de ce que chaque geste lui donne, et
		// le remplacement montre en plus qui a du temps sur l'intervention.
		sheet.qsa('.planning-harvest-time[data-planning-harvest-spent]', wrapper => {

			const replace = (mode === 'replace');

			if(replace !== (wrapper.dataset.planningHarvestReplace !== undefined)) {
				wrapper.toggleAttribute('data-planning-harvest-replace', replace);
				PlanningHarvestTime.reset(wrapper);
			}

		});

		PlanningHarvestTime.team(sheet.qs('form'));

		sheet.qsa('[data-planning-sheet-for]', node => {

			const off = (node.dataset.planningSheetFor.split(' ').includes(mode) === false);

			node.classList.toggle('hide', off);

			if(node.matches('fieldset')) {
				node.disabled = off;
			}

		});

		const field = this.getField(sheet);

		field.name = {add: 'harvestMore', replace: 'harvestDay'}[mode];

		// Le champ garde les attributs du geste qui l'a rendu : le plancher de `harvestDay` bloquerait l'envoi d'un
		// ajout négatif, qui retire du jour.
		if(mode === 'replace') {
			field.min = '0';
		} else {
			field.removeAttribute('min');
		}

		const select = sheet.qs('select[data-planning-harvest-crate]');
		const chosen = (select === null) ? null : select.selectedOptions[0];

		if(mode !== 'add') {

			sheet.qs('[data-planning-harvest-units]', choice => {

				choice.qsa('select', select => select.value = '');
				choice.qsa('input[type="radio"][value=""]', radio => radio.checked = true);

				PlanningHarvestChoice.change(choice);

			});

		}

		if(select !== null) {

			if(mode !== 'add') {
				PlanningHarvestCrate.own(select).selected = true;
			}

			PlanningHarvestCrate.allow(select, mode);

			// Une quantité tapée en caisses ou dans une autre unité les compte : revenue à l'unité de la saisie,
			// elle s'y lirait.
			if(select.selectedOptions[0] !== chosen) {
				field.value = '';
			}

		}

		this.choose(sheet);

	}

	/*
	 * L'unité du couple choisi (`PlanningHarvestChoice`), celle que dit le bouton.
	 */
	static unit(sheet, unit) {

		sheet.dataset.unit = unit.value;

		this.choose(sheet);

	}

	/*
	 * Le remplacement part de la quantité du jour, comme le tiroir, sauf si une quantité est déjà tapée. Les
	 * caisses ou un autre geste retirent cette quantité tant qu'elle n'a pas été touchée : elle compterait des
	 * caisses, ou s'ajouterait à elle-même.
	 */
	static choose(sheet) {

		const field = this.getField(sheet);
		const prefill = (sheet.dataset.mode === 'replace' && PlanningHarvestCrate.isOn(sheet.qs('form')) === false);

		if(prefill) {

			if(field.value === '') {
				field.value = String(Math.round(parseFloat(sheet.dataset.currentDay) * 1000) / 1000);
				sheet.dataset.prefilled = field.value;
			}

		} else {

			if(sheet.dataset.prefilled !== '' && field.value === sheet.dataset.prefilled) {
				field.value = '';
			}

			sheet.dataset.prefilled = '';

		}

		this.update(sheet);

	}

	static getField(sheet) {
		return sheet.qs(':is(input[name="harvestMore"], input[name="harvestDay"])');
	}

	/*
	 * L'unité courte que dit le bouton : les caisses, au singulier ou au pluriel, l'autre unité choisie, sinon
	 * celle du couple.
	 */
	static getUnit(sheet, quantity) {

		if(PlanningHarvestCrate.isOn(sheet.qs('form'))) {

			const crate = sheet.qs('[data-planning-crate]').dataset;

			return (quantity < 2) ? crate.crateSingular : crate.cratePlural;

		}

		const option = sheet.qs('select[data-planning-harvest-crate]')?.selectedOptions[0];

		return JSON.parse(sheet.dataset.unitsShort)[option?.dataset.planningHarvestStockUnit ?? sheet.dataset.unit];

	}

	/*
	 * Ce que la frappe change, réécrit sur place : le bouton, qui dit ce qui sera écrit, et son temps. Le bouton
	 * et le temps sont rendus une fois par geste, d'où les `qsa()`. Un ajout vaut dès qu'il porte une quantité
	 * ou un temps, comme au tiroir ; un remplacement dès qu'une quantité est tapée, zéro compris.
	 */
	static update(sheet) {

		const mode = sheet.dataset.mode;

		const form = sheet.qs('form');
		const typed = this.getField(sheet).value;
		const read = PlanningHarvestCrate.read(typed);
		const number = isNaN(read) ? 0 : read;

		const crate = PlanningHarvestCrate.isOn(form);
		const kilos = PlanningHarvestCrate.update(form);

		// Un ajout négatif se lit « Retirer 3 kg » : le verbe porte le signe, la quantité ne le répète pas.
		const minus = (number < 0);
		const quantity = Math.abs(number);

		const amount = (typed === '') ?
			'' :
			PlanningHarvestCrate.format(Math.round(quantity * 1000) / 1000) +' '+ this.getUnit(sheet, quantity) + ((crate && kilos !== null) ? ' · '+ PlanningHarvestCrate.format(Math.abs(kilos)) +' kg' : '');

		sheet.qsa('[data-planning-sheet-amount]', node => node.textContent = amount);
		sheet.qsa('[data-planning-sheet-sign]', node => node.classList.toggle('hide', (node.dataset.planningSheetSign === 'minus') !== minus));

		let time = 0;

		sheet.qsa('.planning-harvest-time', wrapper => time += PlanningHarvestTime.get(wrapper));

		time = PlanningTask.round(time);

		sheet.qsa('[data-planning-sheet-time]', node => node.classList.toggle('hide', time === 0));
		sheet.qsa('[data-planning-sheet-duration]', duration => duration.textContent = (time === 0) ? '' : PlanningTask.format(time));

		sheet.qsa('[data-planning-sheet-time-after]', after => after.classList.toggle('hide', typed === ''));
		sheet.qsa('[data-planning-sheet-time-alone]', alone => alone.classList.toggle('hide', typed !== ''));

		sheet.qsa('[data-planning-sheet-typed]', sentence => sentence.classList.toggle('hide', typed === ''));
		sheet.qsa('[data-planning-sheet-untyped]', verb => verb.classList.toggle('hide', typed !== ''));

		// Un retrait corrige une quantité mal saisie, il ne porte pas de temps : le serveur le refuse
		// (`Task::harvestMore.time`), comme la case. Le bouton s'éteint et le refus se lit dès la frappe, et se retire
		// avec ce qui l'a fait venir.
		const refused = (mode === 'add' && number < 0 && time > 0);

		const valid = {
			add: () => (number !== 0 || time > 0) && refused === false,
			replace: () => (typed !== '' && PlanningHarvestDrawer.isOthersTyped(form))
		}[mode]();

		sheet.qs('[data-planning-sheet-submit]').disabled = (valid === false);

		sheet.qs('[data-planning-sheet-remove-time]', wrapper => {

			const message = wrapper.qs('[data-planning-sheet-remove-time-message]');

			if(refused && message === null) {
				const node = document.createElement('p');
				node.className = 'form-error-message';
				node.dataset.planningSheetRemoveTimeMessage = '';
				node.textContent = wrapper.dataset.planningSheetRemoveTime;
				wrapper.append(node);
			} else if(refused === false && message !== null) {
				message.remove();
			}

		});

	}

}

/*
 * L'écran « Interventions » d'une semaine (L5a) : la marque des cartes, qui les coche et les décoche, la bulle du
 * temps passé qui s'ouvre sous la carte cochée. La répartition des blocs en piles est
 * celle du socle (`MainPiles`).
 *
 * Mêmes délégués posés une fois sur le document que pour les récoltes : chaque geste remplace la carte, et
 * la bulle est rendue par le serveur, donc rien ne s'accroche à un nœud.
 */
document.delegateEventListener('click', '#planning-week [data-planning-task-done]', function(e) {

	e.preventDefault();

	PlanningTask.done(this.closest('.planning-task'));

});

document.delegateEventListener('click', '#planning-week [data-planning-task-todo]', function(e) {

	e.preventDefault();

	PlanningTask.todo(this.closest('.planning-task'));

});

// Le cachet « Fait » d'une ligne de la grille (CW) : les mêmes gestes que la marque d'une carte, et la ligne
// s'envoie à leur place. La case garde l'état cliqué jusqu'à la réponse, qui rend la ligne de la base.
document.delegateEventListener('change', ':is(#planning-grid, #planning-grid-late) [data-planning-grid-done]', function() {

	if(this.checked) {
		PlanningTaskGrid.pressStamp(this.nextElementSibling);
		PlanningTask.done(this.closest('.planning-grid-row'));
	} else {
		PlanningTask.todo(this.closest('.planning-grid-row'));
	}

});

// Le cachet « Fait » d'un rappel de la grille (DC-bis, retour 339) : la coche et la décoche de la note, dont la
// réponse redessine la ligne.
document.delegateEventListener('change', '#planning-grid [data-planning-grid-reminder]', function() {

	const week = qs('#planning-week');

	if(this.checked) {
		PlanningTaskGrid.pressStamp(this.nextElementSibling);
	}

	new Ajax.Query(this.closest('.planning-grid-row'))
		.url('/planning/taskWeek:'+ (this.checked ? 'doReminderCheck' : 'doReminderUncheck'))
		.body({
			id: week.dataset.structure,
			week: week.dataset.week,
			reminder: this.dataset.planningGridReminder,
			view: week.dataset.view
		})
		.fetch();

});

// Un jour à venir de la grille prévoit l'intervention ce jour-là.
document.delegateEventListener('click', ':is(#planning-grid, #planning-grid-late) [data-planning-grid-plan]', function(e) {

	e.preventDefault();

	PlanningTask.send(this.closest('.planning-grid-row'), 'doPlanned', {'planned[date]': this.dataset.planningGridPlan});

});

// Le point du jour prévu retire ce jour : l'intervention reste prévue dans la semaine, sans jour (DN).
document.delegateEventListener('click', ':is(#planning-grid, #planning-grid-late) [data-planning-grid-unplan]', function(e) {

	e.preventDefault();

	PlanningTask.send(this.closest('.planning-grid-row'), 'doUnplanned');

});

// Le temps prévu d'une ligne (DF) : pas de borne d'une journée, comme le total d'une personne sur la page d'une
// intervention. La réponse ne redessine que le travail estimé de la semaine, la tuile s'écrit elle-même.
PlanningDurationStep.register({
	selector: ':is(#planning-grid, #planning-grid-late) .planning-grid-expected .planning-duration-step',
	bounded: false,
	change: (field, time) => {
		PlanningDurationStep.tile(PlanningDurationStep.getTile(field), time);
		return PlanningTaskGrid.saveTimeExpected(field.closest('.planning-grid-row'), time);
	}
});

// « Récolté » ouvre la saisie de l'écran des récoltes sur la culture de la ligne, comme la carte qu'on tire.
document.delegateEventListener('click', ':is(#planning-grid, #planning-grid-late) [data-planning-grid-harvest]', function(e) {

	e.preventDefault();

	PlanningTaskSwipe.harvest(this.closest('.planning-grid-row'));

});

// La saisie du temps d'un jour et la liste « Affecter à... » se rendent à l'ouverture de leur menu.
// `Lime.Dropdown` émet ses événements sur le bouton, sans remontée : seule une écoute en capture les voit.
window.addEventListener('dropdownAfterShow', e => {

	if(e.target.dataset?.planningGridUsers !== undefined) {
		PlanningTaskGrid.load(e.target, e.detail.list, 'users');
	} else if(e.target.dataset?.planningGridTime !== undefined) {
		PlanningTaskGrid.load(e.target, e.detail.list, 'time');
	}

}, true);

// Ouverte, la liste est sous `body` (`Lime.Dropdown.open()`) : le délégué part de sa classe, et non de
// `#planning-grid`.
document.delegateEventListener('click', '.planning-grid-users-list [data-planning-grid-user]', function(e) {

	e.preventDefault();

	PlanningTaskGrid.user(this);

});

// Le menu d'aujourd'hui prévoit l'intervention ce jour-là, comme la case d'un jour à venir (EH).
// Sous `body` comme la liste des personnes, il retrouve sa ligne par la tâche qu'il porte.
document.delegateEventListener('click', '.planning-grid-time-list [data-planning-grid-plan]', function(e) {

	e.preventDefault();

	PlanningTask.send(PlanningTaskGrid.getTimeRow(this), 'doPlanned', {'planned[date]': this.dataset.planningGridPlan});

});

/*
 * Les menus d'une ligne de la grille (CW), dont les listes vivent sous `body` une fois ouvertes : chaque
 * envoi désigne donc sa ligne par l'intervention que porte la liste ou l'entrée, et passe par la file commune
 * (`PlanningTask.enqueue()`). Le menu d'un jour porte le tableau de l'équipe de la fiche, réduit à ce jour, et
 * ses gestes sont ceux de la fiche (`PlanningTaskDetail`), sauf le temps : chaque personne y a ses pas déjà
 * ouverts, et un seul « Enregistrer » écrit les durées de toutes (ET, retour 398). Sa réponse, comme celle
 * d'une affectation, ne redessine que le déclencheur, le tableau et ce que le temps change sur la ligne, menu
 * ouvert ; une ligne qui change de place, ou qui entre dans les blocs depuis une zone, revient entière, ce qui
 * ferme le menu.
 */
class PlanningTaskGrid {

	// Une seule lecture par liste : une ligne redessinée revient avec une liste vide, relue à sa prochaine
	// ouverture. Une liste rouverte avant sa réponse ne relance pas la lecture.
	static loading = new WeakSet();

	static load(button, list, action) {

		if(this.loading.has(list)) {
			return;
		}

		if(action === 'time') {
			PlanningTaskDetail.open(list);
		}

		if(list.qs('.dropdown-item, .planning-detail-team-grid') !== null) {
			list.qs('.planning-detail-team-grid-inline', grid => PlanningTaskDetail.rendered(grid));
			this.fitUsers(button, list);
			return;
		}

		this.loading.add(list);

		const row = button.closest('.planning-grid-row');
		const body = PlanningTask.getBody(row.dataset.task);

		if(action === 'time') {
			body.date = button.dataset.planningGridTime;
		}

		new Ajax.Query(button)
			.url('/planning/taskWeek:'+ action +'?'+ new URLSearchParams(body).toString())
			.method('get')
			.fetch()
			.then(() => this.placeUsers(button, list))
			.finally(() => this.loading.delete(list));

	}

	static getTimeRow(item) {
		return document.getElementById('planning-grid-task-'+ item.closest('.planning-grid-time-list').dataset.task);
	}

	// Ouverte, la liste vit sous `body` : elle retrouve son bouton par la case du jour, qui le garde.
	static getTimeButton(list) {
		return document.getElementById('planning-grid-day-'+ list.dataset.task +'-'+ list.dataset.date)?.qs('[data-planning-grid-time]') ?? null;
	}

	static close(list) {

		const button = this.getTimeButton(list);

		if(button !== null && Lime.Dropdown.isOpen(button)) {
			Lime.Dropdown.close(button);
		}

	}

	static user(item) {

		const id = item.id;
		const task = item.dataset.task;

		return PlanningTask.enqueue(() => {

			const itemNow = document.getElementById(id);

			if(itemNow === null) {
				return;
			}

			return new Ajax.Query(itemNow)
				.url('/planning/taskWeek:doUser')
				.body({
					...PlanningTask.getBody(task),
					user: itemNow.dataset.planningGridUser,
					action: itemNow.getAttribute('post-action')
				})
				.fetch();

		});

	}

	static saveTimeExpected(row, time) {

		return PlanningTask.enqueue(() => new Ajax.Query(row)
			.url('/planning/taskWeek:doTimeExpected')
			.body({
				...PlanningTask.getBody(row.dataset.task),
				timeExpected: time
			})
			.fetch()
		);

	}

	// Le coup de tampon, à la coche : la réponse remplace la ligne, et le cachet neuf arrive déjà coché.
	static pressStamp(stamp) {

		stamp.classList.remove('is-pressed');

		// La lecture de la géométrie relance l'animation sur un cachet qui vient de la perdre.
		void stamp.offsetWidth;
		stamp.classList.add('is-pressed');

	}

	// `Lime.Dropdown` place la liste à son ouverture, vide, et ne la replace plus : une fois remplie, elle
	// déborderait de l'écran. Accrochée au bouton, son `stick` la replace ; en plein écran (téléphone), elle n'en a
	// pas, et se recentre ici comme `openFullscreen()` la centre. Refermée avant la réponse, il n'y a rien à
	// placer, et son `stick` est déjà détruit.
	static placeUsers(button, list) {

		if(Lime.Dropdown.isOpen(button) === false) {
			return;
		}

		if(button.dataset.dropdownDisplay !== 'fullscreen') {
			button.stick.move();
			this.fitUsers(button, list);
			return;
		}

		// `openFullscreen()` centre la liste 5 ms après l'ouverture, d'après sa position du moment : une réponse
		// arrivée avant verrait son centrage écrasé. Un délai égal, posé après le sien, passe toujours derrière.
		setTimeout(() => this.centerUsers(button, list), 5);

	}

	// Accrochée sous son bouton, la liste de toute la structure descend plus bas que l'écran à partir de 1200 de
	// large, où `Lime.Dropdown` ne la passe jamais en plein écran : le socle la borne à la hauteur de l'écran
	// (`lime.css`), sans compter la place déjà prise au-dessus d'elle (R-Retours-0810, AB, point 13). Elle se pose
	// donc du côté du bouton où elle tient entière dans l'écran que la page laisse (`PlanningDuration.getScreen()`,
	// barres fixes déduites), dessous d'abord, et s'écarte de son bord. Sans place d'aucun côté, elle prend le
	// plus grand et défile en elle-même. La page, elle, ne défile pas : au bas du document, c'est la liste qui le
	// prolonge, et la page qui défile jusqu'à elle l'amène contre le bord de l'écran, sans marge. En plein écran,
	// `openFullscreen()` la loge déjà, et une borne posée par une ouverture précédente, avant que l'écran ne
	// rétrécisse, n'a plus lieu d'être.
	static fitUsers(button, list) {

		// La place se compare à la liste entière, pas à la borne d'une ouverture précédente.
		list.style.maxHeight = '';

		if(button.dataset.dropdownDisplay === 'fullscreen') {
			return;
		}

		const bounds = list.getBoundingClientRect();
		const buttonBounds = button.getBoundingClientRect();
		const screen = PlanningDuration.getScreen(list);
		const margin = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--space-xs')) * rem();

		const gap = bounds.top - buttonBounds.bottom;
		const roomBelow = screen.bottom - margin - bounds.top;
		const roomAbove = buttonBounds.top - gap - margin - screen.top;

		if(bounds.height <= roomBelow) {
			return;
		}

		const up = (bounds.height <= roomAbove || roomAbove > roomBelow);

		// `Lime.Dropdown` ne retourne jamais une liste accrochée, et son placement `auto` compte sur toute la hauteur
		// de la fenêtre, barres fixes comprises : le côté se décide ici et se pose sur le `Lime.Stick`, que
		// `openAround()` recrée à chaque ouverture. La classe `dropdown-list-bottom` reste, aucune feuille ne la lit.
		if(up) {
			button.stick.placement = button.stick.placement.replace('bottom', 'top');
			button.stick.offset.y = -button.stick.offset.y;
		}

		const room = up ? roomAbove : roomBelow;

		if(bounds.height > room) {
			list.style.maxHeight = room +'px';
		}

		if(up) {
			button.stick.move();
		}

	}

	static centerUsers(button, list) {

		if(Lime.Dropdown.isOpen(button) === false) {
			return;
		}

		const bounding = list.getBoundingClientRect();
		const matrix = new DOMMatrix(getComputedStyle(list).transform);

		const translateX = matrix.e + (window.innerWidth - bounding.width) / 2 - bounding.left;
		const translateY = matrix.f + (window.innerHeight - bounding.height) / 2 - bounding.top;

		list.style.transform = 'translate('+ translateX +'px, '+ translateY +'px)';

	}

}

// La bulle n'a pas de tuile : « Enregistrer » la retire une fois le temps accepté, la croix et Échap sans
// rien envoyer.
PlanningDurationStep.register({
	selector: '#planning-week .planning-task-bubble .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTask.saveTime(field.closest('.planning-task-bubble'), time),
	dismiss: field => PlanningTask.dismiss(field.closest('.planning-task-bubble'))
});

/*
 * Un clic hors de la bulle ouverte la referme, et sur les blocs et les zones « En retard » il ne fait rien
 * d'autre : aucune carte ne se coche ni ne s'ouvre, c'est le clic suivant qui agit (retour 194, la règle du
 * tiroir des récoltes), sauf le rond de la carte que la bulle suit, qui la décoche. Hors des blocs, un
 * onglet, la recherche ou le menu agissent aussi. Écouté en capture sur `window`, avant les délégués du
 * document. L'appui retenu écarte une sélection commencée dans la bulle et lâchée dehors, dont le clic tombe
 * sur l'ancêtre commun.
 */
window.addEventListener('pointerdown', e => PlanningTask.down(e), true);
window.addEventListener('click', e => PlanningTask.outside(e), true);



class PlanningTask {

	static MAX_TIME = 24;

	/*
	 * Le nœud où a commencé le clic en cours (`outside()`).
	 */
	static pressed = null;

	/*
	 * Les requêtes de l'écran partent l'une après l'autre : la coche d'une carte ferme la bulle d'avant,
	 * dont le dernier total doit être écrit avant, et deux totaux tapés vite ne doivent pas arriver dans le
	 * désordre. Une requête refusée ne bloque pas les suivantes.
	 */
	static queue = Promise.resolve();

	static enqueue(callback) {

		this.queue = this.queue.then(callback, callback);

		return this.queue;

	}

	static getBody(task) {

		const week = qs('#planning-week');

		return {
			id: week.dataset.structure,
			week: week.dataset.week,
			unplanned: week.dataset.unplanned,
			folded: week.dataset.folded,
			view: week.dataset.view,
			task: task
		};

	}

	/*
	 * Les cartes faites que « À faire » garde en Colonnes (`kept`, R-Retours-1210 V40) voyagent avec chaque geste,
	 * comme celles de « Sans jour » au Jour (`PlanningTaskDay.getKept()`) : « Fait », que la réponse peut rendre
	 * entière, garderait sinon la carte d'une première coche sous l'identifiant de sa carte gardée. La coche
	 * d'une carte de « À faire » y ajoute la sienne. Un geste qui ouvre un panneau par son adresse ne les porte
	 * pas : seuls les gestes qui écrivent les relisent.
	 */
	static getKept(card = null, action = null) {

		const kept = Object.fromEntries(Array.from(qsa('#planning-week-todo .planning-task-done'), cardKept => [cardKept.dataset.task, 1]));

		if(action === 'doDone' && card.closest('#planning-week-todo') !== null) {
			kept[card.dataset.task] = 1;
		}

		return kept;

	}

	// Les gestes qui postent leur formulaire, que `PlanningTaskKept` garnit des cartes gardées.
	static KEPT = {
		batch: '/planning/taskWeek:doBatch',
		forms: ['/planning/taskWeek:doCreate', '/planning/taskWeek:doBatchTime', '/planning/taskWeek:doHarvest'],
		attribute: 'data-planning-week-kept',
		get: () => PlanningTask.getKept()
	};

	// La largeur décide si le temps s'ouvre en bulle ou en feuille (`taskWeek:doDone`), à la borne du CSS.
	static done(card) {

		this.send(card, 'doDone', {narrow: window.matchMedia('(max-width: 767px)').matches ? 1 : 0});

	}

	static todo(card) {

		this.send(card, 'doTodo');

	}

	/*
	 * La carte touchée garde sa place dans la fenêtre : quand la réponse rend l'écran des blocs entier, les
	 * piles se recomposent et la carte peut changer de hauteur dans la page. Le défilement est corrigé de
	 * cet écart, pour qu'un geste ne déplace jamais ce que l'on regarde. La ligne de la grille se retrouve
	 * de même, par son identifiant.
	 */
	static send(card, action, body = {}) {

		const task = card.dataset.task;
		const id = card.id;

		return this.enqueue(() => {

			// Un second clic mis en file pendant le premier vise une carte que la réponse a déjà remplacée :
			// le geste est fait, rien ne repart.
			if(card.isConnected === false) {
				return;
			}

			const top = card.getBoundingClientRect().top;

			return new Ajax.Query(card)
				.url('/planning/taskWeek:'+ action)
				.body({...this.getBody(task), kept: this.getKept(card, action), ...body})
				.fetch()
				.then(() => {

					const cardNew = document.getElementById(id);

					// Au téléphone, une carte qui change de colonne part sous l'onglet caché, sans place à garder.
					if(cardNew !== null && cardNew.getClientRects().length > 0) {
						window.scrollBy(0, cardNew.getBoundingClientRect().top - top);
					}

				});

		});

	}

	/*
	 * La bulle se pose sous sa carte et y reste pendant toute la saisie : la carte que chaque pas remplace peut
	 * gagner ou perdre une ligne, la bulle ne la suit pas, pour que le pas reste sous le pointeur d'un clic à
	 * l'autre (retour 95). Les réponses d'une saisie ne remplacent que la carte (`taskWeek.v.php`), donc la bulle
	 * n'est ancrée qu'à son ouverture, et de nouveau quand la largeur de l'écran change (`PlanningTask.reanchor()`).
	 *
	 * Elle se centre sous le rond de la carte (retour 271). Près du bord de l'écran ou du menu, elle se décale
	 * juste assez pour tenir dans la Semaine, et sa pointe reste sous le rond (`--planning-task-bubble-tip`, mesuré
	 * depuis le bord intérieur de sa bordure gauche). Sa largeur est celle de la rangée des pas
	 * (`min-width: max-content`), qui ne dépend pas de sa position : tout se mesure d'abord, puis tout s'écrit,
	 * sans second calcul de la mise en page.
	 */
	static anchor(bubble) {

		const card = bubble.previousElementSibling;

		if(bubble.isConnected === false || card === null) {
			return;
		}

		const mark = card.querySelector('.planning-task-mark-ring');

		const top = card.offsetTop + card.offsetHeight;
		const cardLeft = card.offsetLeft;
		const cardRect = card.getBoundingClientRect();
		const markRect = mark.getBoundingClientRect();
		const weekRect = bubble.closest('#planning-week').getBoundingClientRect();
		const screenRight = document.documentElement.clientWidth;
		const width = bubble.offsetWidth;
		const border = bubble.clientLeft;

		const center = markRect.left + markRect.width / 2;
		const min = Math.max(weekRect.left, 0);
		const max = Math.min(weekRect.right, screenRight) - width;
		const left = Math.max(Math.min(center - width / 2, max), min);

		bubble.style.top = top +'px';
		bubble.style.left = (cardLeft + left - cardRect.left) +'px';

		// La pointe se pose depuis le bord intérieur de la bordure, la position de la bulle depuis son bord extérieur.
		bubble.style.setProperty('--planning-task-bubble-tip', (center - left - border) +'px');

	}

	/*
	 * Au redimensionnement, la largeur d'une carte dépend du nombre de piles que `MainPiles.restack()`
	 * recompose sur le même événement : la bulle se réancre à l'image suivante, une fois toutes les
	 * écoutes de `resize` passées, quel que soit l'ordre de chargement des deux feuilles.
	 */
	static reanchor() {
		requestAnimationFrame(() => qsa('#planning-week .planning-task-bubble', bubble => this.anchor(bubble)));
	}

	/*
	 * La durée enregistrée est le temps de la personne au jour du fait, le serveur n'en écrit que l'écart. La
	 * carte suit, la bulle reste en place. Un refus du serveur s'affiche sous le champ sans rejeter la promesse
	 * (`Ajax.Query.ok()`), et la bulle reste ouverte sur la durée refusée (`PlanningDurationStep.refuse()`).
	 */
	static saveTime(bubble, time) {

		const body = this.getBody(bubble.dataset.task);
		body.kept = this.getKept();
		body.time = time;

		return this.enqueue(() => new Ajax.Query(bubble)
			.url('/planning/taskWeek:doTime')
			.body(body)
			.fetch()
		);

	}

	static close(bubble) {

		// La bulle se retire une fois son dernier total écrit : un refus doit encore pouvoir s'y afficher.
		this.enqueue(() => {

			if(bubble.qs('.form-error-message') === null) {
				bubble.remove();
			}

		});

	}

	// La croix et Échap renoncent à ce que la bulle affiche, refus déjà lu compris. Un total encore en route
	// peut toujours être refusé, et la garder.
	static dismiss(bubble) {

		bubble.qsa('.form-error-message', message => message.remove());

		this.close(bubble);

	}

	static down(e) {
		this.pressed = e.target;
	}

	/*
	 * Comme `PlanningHarvest.outside()`, un clic au clavier (`detail` à 0) agit. Celui que le glisser vers la
	 * gauche adresse au dessous de la carte (`PlanningTaskSwipe.remove()`) aussi : arrêté, il vaudrait un refus
	 * et la carte reviendrait. Un Ctrl+clic ou Cmd+clic sur un lien l'ouvre dans un autre onglet et laisse la
	 * bulle. Sélection en cours, le clic sur une carte la coche par `PlanningTaskPress.click()`, qui écoute aussi
	 * `window` et qu'un arrêt de la propagation ne retient donc pas : la bulle se ferme et le clic agit.
	 */
	static outside(e) {

		const pressed = this.pressed;

		this.pressed = null;

		const bubble = qs('#planning-week .planning-task-bubble');
		const opensTab = ((e.ctrlKey || e.metaKey) && e.target.closest('a[href]') !== null);

		if(bubble === null || e.detail === 0 || opensTab || bubble.contains(e.target) || bubble.contains(pressed)) {
			return;
		}

		// Le rond de la carte de la bulle la décoche du premier clic : on revient sur le geste que la bulle
		// suit, pas sur une autre carte, et la réponse de `doTodo` retire cette bulle (`taskWeek.v.php`).
		if(e.target.closest('[data-planning-task-todo="'+ bubble.dataset.task +'"]') !== null) {
			return;
		}

		// Une bulle qui affiche un refus de `doTime` reste ouverte pour qu'on le lise (`close()`) : un clic qui ne
		// la ferme pas n'a rien à arrêter, sans quoi la grille ne répondrait plus tant que le refus est affiché.
		if(bubble.qs('.form-error-message') !== null) {
			return;
		}

		this.close(bubble);

		if(PlanningTaskSelection.selecting === false && e.target.closest('#planning-week-before, #planning-week-board') !== null) {
			e.preventDefault();
			e.stopPropagation();
		}

	}

	static round(time) {
		return Math.round(time * 100) / 100;
	}

	// Comme `DurationUi::formatDuration()`, pour que la saisie écrive ce que le serveur rend.
	static format(time) {

		const minutes = Math.round(time * 60);

		return Math.floor(minutes / 60) +'h'+ String(minutes % 60).padStart(2, '0');

	}

}

window.addEventListener('resize', () => PlanningTask.reanchor());

// En Colonnes, passer la borne pendant une saisie la ferme sans rien écrire, comme la croix : la bulle et la
// feuille ne valent chacune que d'un côté. Les Cartes gardent leur bulle, que `reanchor()` replace.
window.matchMedia('(max-width: 767px)').addEventListener('change', () => qsa('#planning-week[data-view="columns"] :is(.planning-task-bubble, #planning-task-sheet)', node => PlanningTask.dismiss(node)));

// « Enregistrer » écrit puis ferme la feuille, comme la bulle (`PlanningTask.saveTime()`, `close()`).
PlanningDurationStep.register({
	selector: '#planning-task-sheet .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTask.saveTime(field.closest('#planning-task-sheet'), time),
	dismiss: field => PlanningTask.dismiss(field.closest('#planning-task-sheet'))
});

document.delegateEventListener('click', '#planning-task-sheet [data-planning-task-sheet-close]', function(e) {

	e.preventDefault();

	PlanningTask.dismiss(this.closest('#planning-task-sheet'));

});

// Échap dans le champ est déjà celui de `PlanningDurationStep`.
document.delegateEventListener('keydown', '#planning-task-sheet', function(e) {

	if(e.key === 'Escape' && e.target.closest('.planning-duration-step input') === null) {
		e.preventDefault();
		PlanningTask.dismiss(this);
	}

});

class PlanningTaskSheet {

	static start(sheet) {

		sheet.qs('.util-sheet-panel').focus();

		PlanningHarvestSheet.dock(sheet);

	}

}

/*
 * Le tableau de l'équipe (`TaskDetailUi::getGrid()`), dans le « Temps de travail » de la page d'une intervention
 * et dans le menu d'un jour de la grille de la Semaine : une ligne par personne et une case par jour, chacune
 * avec son champ par pas (`DurationUi::getStepped()`). Chaque saisie écrit le temps d'une personne un jour
 * donné : la borne de 24 h vaut donc partout (`bounded`), comme le serveur la vérifie (`time.tooLong`). Les
 * envois passent par la file de la bulle (`PlanningTask.enqueue()`).
 *
 * Le tableau porte ses routes, ce que chaque geste réémet et les lignes ajoutées par « Ajouter une
 * personne », que rien n'a encore écrites (`PlanningTaskDetail.getShown()`) : le serveur le redessine avec
 * les mêmes lignes.
 */
PlanningDurationStep.register({
	selector: '.planning-detail-team-grid:not(.planning-detail-team-grid-inline) .planning-detail-time-field .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTaskDetail.save(field, time)
});

/*
 * Dans le menu d'un jour de la grille, les pas de chaque personne sont déjà ouverts (`DurationUi::getStepInline()`)
 * et ne font que retenir la durée tapée (`PlanningTaskDetail.type()`) : l'« Enregistrer » du tableau, ou Entrée
 * dans une case, les écrit toutes (`PlanningTaskDetail.saveTeam()`). Ils n'ont pas d'écran à retirer : Échap
 * ferme le menu, qui abandonne ce qui n'a pas été enregistré.
 */
PlanningDurationStep.register({
	selector: '.planning-detail-team-grid-inline .planning-detail-time-field .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTaskDetail.type(field, time),
	dismiss: () => {}
});

// Après le délégué de tous les pas, qui a déjà reporté la durée de la case.
document.delegateEventListener('keydown', '.planning-detail-team-grid-inline .planning-duration-step input', function(e) {

	if(e.key === 'Enter') {
		PlanningTaskDetail.saveTeam(this.closest('.planning-detail-team-grid'));
	}

});

document.delegateEventListener('click', '.planning-detail-team-grid-inline [data-planning-detail-save]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.saveTeam(this.closest('.planning-detail-team-grid'));

});

document.delegateEventListener('click', '.planning-detail-team-grid-inline [data-planning-duration-step-erase]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.erase(this.closest('.planning-duration-step-window').qs('.planning-duration-step'));

});

document.delegateEventListener('focusin', '.planning-detail-team-grid-inline .planning-duration-step input', function() {
	PlanningTaskDetail.focused.set(this.closest('.planning-detail-team-grid').id, {
		key: this.closest('.planning-duration-step').dataset.duration,
		name: this.name
	});
});

// `Lime.Dropdown` ne se ferme pas sur Échap. Le menu d'un jour, qui porte des saisies, se ferme comme elles.
document.addEventListener('keydown', e => {

	if(e.key !== 'Escape') {
		return;
	}

	qs('.planning-grid-time-list.dropdown-list-open', list => {
		PlanningDuration.escaped = true;
		PlanningTaskGrid.close(list);
	});

});

// La liste d'« Ajouter une personne » s'ouvre dans le `body` sur la fiche (`Lime.Dropdown`), et le menu d'un jour
// de la grille aussi : aucun délégué ne peut passer par l'écran.
document.delegateEventListener('click', '.planning-detail-time-add-list [data-planning-detail-add]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.add(this);

});

// Le menu de la vignette, au téléphone, s'ouvre aussi dans le `body` (`TaskDetailUi::getGridUserMenu()`).
document.delegateEventListener('click', ':is(.planning-detail-team-grid, .planning-detail-team-menu) [data-planning-detail-assign]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.assign(this);

});

document.delegateEventListener('click', '#planning-task-detail-intervention [data-planning-detail-harvest]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.harvest(this, true);

});

document.delegateEventListener('click', '#planning-task-detail-intervention [data-planning-detail-harvest-cancel]', function(e) {

	e.preventDefault();

	PlanningTaskDetail.harvest(this, false);

});

document.delegateEventListener('click', '#planning-week [data-planning-zone]', function(e) {

	e.preventDefault();

	PlanningTaskZone.toggle(e.delegateTarget.closest('.util-fold'));

});

// Le Jour porte la zone sans les autres zones de la semaine : il n'y a pas de pli à retenir.
document.delegateEventListener('click', '#planning-due .util-fold-head', function(e) {

	e.preventDefault();

	e.delegateTarget.closest('.util-fold').classList.toggle('util-fold-open');

	if(qs('#planning-week') !== null) {
		PlanningTaskZone.fold();
	}

});

/*
 * Les deux zones sous la semaine (L8), « En retard » et « Non planifié ». Une zone repliée a une largeur
 * nulle : ses piles se recalculent donc à l'ouverture. « Non planifié » n'est chargé qu'à sa première
 * ouverture, et la réponse pose alors sur l'écran ce que les gestes réémettront (`data-unplanned`).
 */
class PlanningTaskZone {

	static toggle(zone) {

		const open = zone.classList.toggle('util-fold-open');

		this.fold();

		if(open === false) {
			return;
		}

		const board = zone.qs('.util-fold-content > div');

		if(board.classList.contains('util-piles')) {
			MainPiles.stack(board);
			return;
		}

		// « En retard » en grille (DZ) arrive rendu avec la page : seules ses colonnes sont à remesurer.
		if(board.classList.contains('planning-grid-board')) {
			PlanningTaskGridTracks.align();
			return;
		}

		const week = qs('#planning-week');

		const query = new URLSearchParams({
			id: week.dataset.structure,
			week: week.dataset.week
		});

		new Ajax.Query(zone)
			.url('/planning/taskWeek:unplanned?'+ query.toString())
			.method('get')
			.fetch();

	}

	/*
	 * Les zones montrées que la personne a repliées, réémises par les gestes des cartes et par chaque entrée
	 * de la barre du lot, pour qu'une réponse qui redessine les zones ne les rouvre pas.
	 */
	static fold() {

		const week = qs('#planning-week');

		const folded = Array.from(week.qsa('#planning-week-before > .util-fold:not(.util-fold-open)'))
			.filter(zone => zone.qs('.util-fold-content > :is(.util-piles, .planning-grid-board)') !== null)
			.map(zone => zone.id.replace('planning-week-', ''))
			.concat(Array.from(week.qsa('#planning-due > .util-fold:not(.util-fold-open)'), zone => zone.id.replace('planning-due-', '')));

		week.dataset.folded = folded.join(',');

		qsa('#planning-week [post-week]', node => node.setAttribute('post-folded', week.dataset.folded));

	}

	/*
	 * L'écran entier, quand la page d'une intervention y a fait passer l'intervention d'une zone à la
	 * semaine, ou l'inverse : la carte n'est plus là où la page l'aurait remplacée.
	 */
	static screen() {

		const week = qs('#planning-week');

		if(week === null) {
			return;
		}

		const query = new URLSearchParams({
			id: week.dataset.structure,
			week: week.dataset.week,
			unplanned: week.dataset.unplanned,
			folded: week.dataset.folded,
			view: week.dataset.view
		});

		new Ajax.Query()
			.url('/planning/taskWeek:screen?'+ query.toString())
			.method('get')
			.fetch();

	}

}

/*
 * « En retard » en grille (DZ) est un second tableau sous celui de la semaine, et ses jours tombent sous ceux
 * de la semaine : chaque colonne qui suit ce qu'elle porte prend dans les deux tableaux la plus large des deux
 * (`--planning-grid-track-*`, `planning.css`). Les jours ont une largeur fixe et l'intervention le reste d'une
 * boîte commune : elles suivent. Une colonne se lit sur la case d'en-tête qui la nomme ; une case masquée, dans
 * la forme étroite ou dans « En retard » replié, n'a pas de largeur et n'en impose aucune.
 *
 * Une largeur posée fige sa colonne : les valeurs s'effacent avant de relire ce que portent les lignes, puis
 * toutes les cases se lisent, puis tout s'écrit. Les colonnes se remesurent quand une réponse remplace un nœud
 * de la Semaine, quand sa boîte change de largeur, et quand « En retard » s'ouvre (`PlanningTaskZone.toggle()`).
 * La Semaine est remplacée entière par certaines réponses : le tableau du retard relance l'observation à
 * chaque rendu.
 */
class PlanningTaskGridTracks {

	static observers = [];
	static scheduled = false;

	static start(table) {

		this.observers.forEach(observer => observer.disconnect());

		const week = table.closest('#planning-week');

		// La hauteur change à chaque ligne remplacée : seule la largeur de la boîte change les colonnes. La boîte
		// se signale entre la mise en page et l'affichage, où les colonnes se remesurent avant d'être vues ; les
		// remplacements d'une réponse attendent l'image suivante, pour n'être mesurés qu'une fois.
		let width = null;

		const resize = new ResizeObserver(entries => {

			const current = entries[0].contentRect.width;

			if(current !== width) {
				width = current;
				this.align();
			}

		});

		const mutation = new MutationObserver(() => this.schedule());

		resize.observe(week);
		mutation.observe(week, {childList: true, subtree: true});

		this.observers = [resize, mutation];

	}

	static schedule() {

		if(this.scheduled) {
			return;
		}

		this.scheduled = true;

		requestAnimationFrame(() => {
			this.scheduled = false;
			this.align();
		});

	}

	static align() {

		const tables = Array.from(qsa('#planning-grid, #planning-grid-late'));
		const cells = tables.flatMap(table => Array.from(table.qsa(':scope > .util-table-head > [data-planning-grid-track]')));
		const names = new Set(cells.map(cell => cell.dataset.planningGridTrack));

		tables.forEach(table => names.forEach(name => table.style.removeProperty('--planning-grid-track-'+ name)));

		if(tables.length < 2) {
			return;
		}

		const widths = new Map();

		cells
			.map(cell => [cell.dataset.planningGridTrack, cell.getBoundingClientRect().width])
			.forEach(([name, width]) => {
				if(width > 0) {
					widths.set(name, Math.max(widths.get(name) ?? 0, width));
				}
			});

		tables.forEach(table => widths.forEach((width, name) => table.style.setProperty('--planning-grid-track-'+ name, width +'px')));

	}

}

/*
 * Où la grille de la Semaine s'ouvre quand elle déborde de côté (FL, piste C). Sur une page qu'on ouvre, aujourd'hui
 * est la dernière colonne visible, pour que les jours passés de la semaine restent à l'écran ; un lundi, ou une
 * autre semaine, elle part du lundi. Une réponse qui la remplace sur place reprend la position d'avant, sans revenir
 * sur aujourd'hui : le défilement se retient par grille, au fil de l'utilisateur, jusqu'à la page suivante. La grille
 * le tient seule (`data-own-scroll-left`) : le socle reporterait sinon la position d'une page sur l'autre.
 */
class PlanningTaskGridScroll {

	static positions = new Map();

	static start(table) {

		const position = this.positions.has(table.id) ? this.positions.get(table.id) : this.getToday(table);

		if(position > 0) {
			table.scrollLeft = position;
		}

	}

	static getToday(table) {

		const days = Array.from(table.qsa(':scope > .util-table-head > .planning-grid-head-day'));
		const today = days.findIndex(day => day.classList.contains('planning-grid-today'));

		if(today <= 0) {
			return 0;
		}

		const box = table.getBoundingClientRect();

		return days[today].getBoundingClientRect().right - box.left + table.scrollLeft - table.clientWidth;

	}

}

document.addEventListener('navigation.sleep', () => PlanningTaskGridScroll.positions.clear());

// Le défilement d'une grille ne remonte pas : il s'écoute en capture.
document.addEventListener('scroll', e => {

	if(e.target instanceof Element && e.target.matches('#planning-grid, #planning-grid-late')) {
		PlanningTaskGridScroll.positions.set(e.target.id, e.target.scrollLeft);
	}

}, {capture: true, passive: true});

class PlanningOrder {

	static dragging = false;
	static started = null;

	/*
	 * Les lâchers en file, avec l'ordre que le serveur tient (`saved`) : celui d'avant le premier lâcher,
	 * puis chaque déplacement accepté rejoué dessus comme le serveur le rejoue (`TaskWeekLib::applyMove()`),
	 * et non la liste à l'écran, qui porte encore un déplacement refusé avant lui. Comme pour une durée
	 * (`PlanningDurationStep.pending`), la liste n'est remise qu'à la dernière réponse, et jamais sous le doigt :
	 * Sortable a déjà déplacé la ligne tenue, la remise attend donc la fin du glisser.
	 */
	static sending = null;

	/*
	 * Un seul Sortable, sur le pli de la liste et non sur la liste : Sortable ne range que les enfants
	 * directs de son conteneur.
	 */
	static open(list) {

		const page = list.closest('#planning-customize');
		const fold = list.qs(':scope > .util-deck-fold');

		if(Sortable.get(fold)) {
			return;
		}

		new Sortable(fold, {
			handle: '.util-drag-handle',
			draggable: '.planning-customize-row',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true,
			onStart: () => {
				this.dragging = true;
				this.started = this.getOrder(fold);
			},
			onEnd: e => {

				this.dragging = false;

				if(e.oldIndex !== e.newIndex) {
					this.drop(page, fold, e.item);
				} else if(this.sending !== null && this.sending.pending === 0) {
					this.finish(fold);
				}

			}
		});

	}

	/*
	 * Le serveur ne reçoit que l'intention, la ligne et celle qui la suit désormais (`before`, vide en fin de
	 * liste), et la rejoue sur la même liste que celle qu'il a rendue (`TaskWeekLib::getActions()`). La
	 * réponse n'a rien à redessiner.
	 */
	static drop(page, fold, row) {

		this.renumber(fold);

		const action = row.dataset.action;
		const before = row.nextElementSibling === null ? '' : row.nextElementSibling.dataset.action;

		this.sending ??= {saved: this.started, pending: 0};
		this.sending.pending++;

		PlanningTask.enqueue(() => new Ajax.Query(fold)
			.url('/planning/taskWeek:doOrder')
			.body({
				id: page.dataset.structure,
				week: page.dataset.week,
				action: action,
				before: before
			})
			.fetch())
			.then(json => this.settle(fold, PlanningDuration.refused(json) ? null : [action, before]), () => this.settle(fold, null));

	}

	/*
	 * `move` vaut NULL sur un refus. Une erreur du serveur compte comme un refus, à l'inverse d'une durée :
	 * `doOrder` n'écrit qu'en dernier, juste avant une réponse vide, donc un échec laisse l'ordre enregistré
	 * tel quel.
	 */
	static settle(fold, move) {

		const state = this.sending;

		if(move !== null) {
			state.saved = this.applyMove(state.saved, ...move);
		}

		state.pending--;

		if(state.pending > 0 || this.dragging) {
			return;
		}

		this.finish(fold);

	}

	static finish(fold) {

		const saved = this.sending.saved;

		this.sending = null;

		if(fold.isConnected === false || this.getOrder(fold).join() === saved.join()) {
			return;
		}

		saved.forEach(action => fold.append(fold.qs('.planning-customize-row[data-action="'+ action +'"]')));

		this.renumber(fold);

	}

	/*
	 * La flèche de retour ouvre l'écran d'origine, et la Semaine y lit l'ordre enregistré : la navigation attend
	 * donc le dernier lâcher en file (R-Retours-0410-soir, A2). La flèche est le lien nu de `DesignUi::getPageHeader()`, et le
	 * délégué des liens de `ajax.js` écoute en capture sur `document` : il naviguerait sans attendre, d'où un
	 * écouteur en capture sur `window`, qui passe avant lui.
	 */
	static click(e) {

		const link = (e.target instanceof Element) ? e.target.closest('.util-page-header-back[href]') : null;

		if(link === null || document.getElementById('planning-customize') === null || e.ctrlKey || e.metaKey) {
			return;
		}

		e.stopPropagation();
		e.preventDefault();

		const navigate = () => new Ajax.Navigation(link)
			.url(link.getAttribute('href'))
			.fetch();

		PlanningTask.queue.then(navigate, navigate);

	}

	static applyMove(order, action, before) {

		const orderMoved = order.filter(actionOrdered => actionOrdered !== action);
		const place = orderMoved.indexOf(before);

		orderMoved.splice(place === -1 ? orderMoved.length : place, 0, action);

		return orderMoved;

	}

	static getOrder(fold) {
		return Array.from(fold.qsa('.planning-customize-row'), row => row.dataset.action);
	}

	static renumber(fold) {
		Array.from(fold.qsa('.planning-customize-rank')).forEach((rank, index) => rank.textContent = index + 1);
	}

}

window.addEventListener('click', e => PlanningOrder.click(e), true);

class PlanningTaskDetail {

	/*
	 * Ce que le tableau porte et que chaque geste réémet (`TaskDetailUi::getGridDetail()`, `getGridWeek()`) :
	 * l'intervention, la semaine montrée ou le jour, et les lignes ajoutées, plus l'état de l'écran de la
	 * semaine quand le tableau vit dans un de ses menus.
	 */
	static getShown(grid) {

		const body = JSON.parse(grid.dataset.body);

		return {
			...(grid.dataset.screen === 'week' ? PlanningTask.getBody(body.task) : {}),
			...body,
			added: grid.dataset.added
		};

	}

	/*
	 * La ligne d'une personne choisie dans « Ajouter une personne » : le serveur rend le tableau avec elle. La
	 * liste nomme son tableau, sous lequel elle ne vit pas quand elle s'ouvre en menu.
	 */
	static add(item) {

		const grid = document.getElementById(item.closest('.planning-detail-time-add-list').dataset.grid);

		const query = new URLSearchParams({
			...this.getShown(grid),
			user: item.dataset.planningDetailAdd
		});

		new Ajax.Query(grid)
			.url(grid.dataset.urlAdd +'?'+ query.toString())
			.method('get')
			.fetch();

	}

	/*
	 * Dans le menu d'un jour de la grille, ce que chaque saisie montre sans l'avoir enregistré (`typed`, par clé
	 * de saisie), ce que le serveur a rendu (`served`), et la case qui a eu le focus en dernier (`focused`, par
	 * tableau). Un redessin du tableau, « Ajouter une personne », « Affecter » ou « Enregistrer », les retrouve
	 * (`rendered()`). Tout vit le temps d'une ouverture du menu : le fermer abandonne ce qui n'a pas été
	 * enregistré, comme Échap dans toute saisie.
	 */
	static typed = new Map();
	static served = new Map();
	static focused = new Map();

	/*
	 * À l'ouverture du menu d'un jour, l'ouverture précédente est oubliée. Le menu fermé se vide : rouvert, il
	 * se relit et montre les durées écrites. `Lime.Dropdown` le ferme sans rien émettre, d'où l'observation de
	 * sa classe, posée une fois par liste.
	 */
	static open(list) {

		this.forget(list);

		if(list.dataset.planningObserved !== undefined) {
			return;
		}

		list.dataset.planningObserved = '';

		new MutationObserver(() => {

			if(list.classList.contains('dropdown-list-open') === false && list.firstChild !== null) {
				this.forget(list);
				list.innerHTML = '';
			}

		}).observe(list, {attributes: true, attributeFilter: ['class']});

	}

	static forget(list) {

		const prefix = 'planning-grid-team-'+ list.dataset.task +'-'+ list.dataset.date;

		[this.typed, this.served].forEach(map => Array.from(map.keys())
			.filter(key => key.startsWith(prefix +'-'))
			.forEach(key => map.delete(key))
		);

		this.focused.delete(prefix +'-grid');

	}

	// Retenue tant qu'elle diffère de ce que le serveur a rendu : un « Enregistrer » sans rien de changé ferme le menu.
	static type(field, time) {

		const key = field.dataset.duration;

		if(time === this.served.get(key)) {
			this.typed.delete(key);
		} else {
			this.typed.set(key, time);
		}

	}

	/*
	 * Chaque rendu du tableau d'un menu (`TaskDetailUi::getGrid()`, `onrender`) : les saisies reprennent ce qui
	 * était tapé et pas encore enregistré, puis le focus revient comme après la réponse d'une durée
	 * (`PlanningDurationStep.resume()`), à la case qui l'avait, sauf s'il est passé ailleurs entre-temps. Le
	 * focus ne se pose qu'à la demande de la personne : une case qu'elle n'a pas prise ne le reçoit jamais, à
	 * l'ouverture du menu comme après un redessin (retour 403). Une réponse arrivée après la fermeture du menu
	 * ne prend rien.
	 */
	static rendered(grid) {

		grid.qsa('.planning-duration-step', field => {

			const key = field.dataset.duration;
			const served = PlanningDurationStep.getSent(field);

			this.served.set(key, served);

			if(this.typed.has(key) === false) {
				return;
			}

			const typed = this.typed.get(key);

			if(typed === served) {
				this.typed.delete(key);
				return;
			}

			// La durée tapée devient celle que les pas comparent, comme si elle venait d'être tapée.
			field.dataset.sent = typed;
			PlanningDurationStep.write(field, typed);

		});

		const list = grid.closest('.dropdown-list');

		if(
			list === null ||
			list.classList.contains('dropdown-list-open') === false ||
			(document.activeElement !== null && document.activeElement !== document.body)
		) {
			return;
		}

		const focused = this.focused.get(grid.id);

		if(focused !== undefined) {
			PlanningDurationStep.focus(focused.key, focused.name);
		}

	}

	/*
	 * L'« Enregistrer » du menu d'un jour : une requête pour les durées changées de toutes les personnes,
	 * relues au départ dans la file, sur le tableau qu'une réponse précédente a pu redessiner. La réponse
	 * acceptée ferme le menu (FJ, retour 411), comme « Enregistrer » sans rien de changé, et le menu fermé
	 * oublie ce qui a été tapé pendant l'envoi (`open()`). Un refus garde tout, menu ouvert, et son message se
	 * lit sous les pas de la personne. `key` borne l'envoi à la durée d'une seule personne (`erase()`).
	 */
	static saveTeam(grid, key = null) {

		const id = grid.id;
		const list = grid.closest('.planning-grid-time-list');

		if(Array.from(grid.qsa('.planning-duration-step')).some(field => this.typed.has(field.dataset.duration)) === false) {
			PlanningTaskGrid.close(list);
			return;
		}

		return PlanningTask.enqueue(() => {

			const gridNow = document.getElementById(id);

			if(gridNow === null) {
				return;
			}

			const sent = new Map();
			const time = {};

			gridNow.qsa('.planning-duration-step', field => {

				const fieldKey = field.dataset.duration;

				if(
					this.typed.has(fieldKey) &&
					(key === null || key === fieldKey)
				) {
					sent.set(fieldKey, this.typed.get(fieldKey));
					time[field.closest('[data-user]').dataset.user] = this.typed.get(fieldKey);
				}

			});

			if(sent.size === 0) {
				return;
			}

			return new Ajax.Query(gridNow)
				.url(gridNow.dataset.urlTime)
				.body({
					...this.getShown(gridNow),
					time: time
				})
				.fetch()
				.then(json => {

					if(PlanningDuration.refused(json) === false) {
						sent.forEach((typed, key) => {
							if(this.typed.get(key) === typed) {
								this.typed.delete(key);
							}
						});
						PlanningTaskGrid.close(list);
					}

					return json;

				});

		});

	}

	/*
	 * La poubelle d'une personne dans le menu d'un jour (`DurationUi::getStepPanel()`) : seule sa durée nulle
	 * part, et le serveur supprime son temps du jour. « Enregistrer » reste le seul geste qui écrive les durées
	 * tapées pour les autres (point 14 du relecteur, tranché le 16/10) : le menu se ferme à la réponse, et ce
	 * qui n'a pas été enregistré se perd, comme avec Échap.
	 */
	static erase(field) {

		PlanningDurationStep.write(field, 0);
		this.type(field, 0);
		this.saveTeam(field.closest('.planning-detail-team-grid'), field.dataset.duration);

	}

	/*
	 * « Affecter » et « Affecté » d'une ligne, dans la file des saisies : une affectation suivie d'un temps
	 * partent dans l'ordre, et la seconde lit le tableau que la première a rendu, retrouvé par son identifiant.
	 * Le geste se lit sur la ligne ou dans le menu de sa vignette, qui nomment tous deux leur tableau.
	 */
	static assign(link) {

		const id = link.closest('[data-grid]').dataset.grid;
		const user = link.closest('[data-user]').dataset.user;
		const action = link.dataset.planningDetailAssign;

		return PlanningTask.enqueue(() => {

			const grid = document.getElementById(id);

			if(grid === null) {
				return;
			}

			return new Ajax.Query(grid)
				.url(grid.dataset.urlUser)
				.body({
					...this.getShown(grid),
					user: user,
					action: action
				})
				.fetch();

		});

	}

	/*
	 * Une étape de la frise des récoltes de la fiche, celle d'un jour ou « Ajouter une récolte », remplace sa ligne
	 * par sa saisie (R-Retours-0810, 207). Une seule saisie est ouverte à la fois. Refermée sans envoi, elle revient
	 * à ce que le serveur a rendu, la quantité du jour que sa ligne affiche. La réponse de `doHarvest` redessine la
	 * récolte, saisie refermée.
	 */
	static harvest(trigger, open) {

		const step = trigger.closest('.util-procedure-step');

		if(open) {
			step.parentElement.qsa(':scope > .util-procedure-step > .planning-detail-harvest-form:not(.hide)', entry => this.harvest(entry, false));
		}

		const entry = step.qs(':scope > .planning-detail-harvest-form');

		step.qs(':scope > .planning-detail-harvest-line').classList.toggle('hide', open);
		entry.classList.toggle('hide', open === false);

		if(open) {
			entry.qs('input[name="harvestMore"], input[name="harvestDay"]').focus();
		} else {
			entry.qs('form').reset();
		}

	}

	/*
	 * La personne et le jour se lisent sur l'écran au moment où l'envoi part, et non au geste : un envoi en
	 * file part après la réponse du précédent, qui a pu redessiner le champ. Le nœud se retrouve par sa clé
	 * (`data-duration`), unique dans la page (`TaskDetailUi::getKey()`).
	 */
	static save(node, time) {

		const key = node.dataset.duration;

		return PlanningTask.enqueue(() => {

			const target = qs('[data-duration="'+ key +'"]');

			if(target === null) {
				return;
			}

			const grid = target.closest('.planning-detail-team-grid');

			const body = {
				...this.getShown(grid),
				user: target.closest('[data-user]').dataset.user,
				date: target.closest('[data-date]').dataset.date,
				time: time
			};

			// Le refus s'affiche dans le `data-wrapper` que `Lime.Alert` cherche sous le contexte, et non sur lui :
			// le champ par pas le porte lui-même, le contexte est donc sa cellule.
			return new Ajax.Query(target.closest('.planning-detail-time-field'))
				.url(grid.dataset.urlTime)
				.body(body)
				.fetch();

		});

	}

	/*
	 * Le calendrier (`FormUi::weekSelector()`) n'offre qu'un lien ou un `onclick` : un lien passerait par
	 * `Ajax.Navigation`, qui changerait l'adresse de la page.
	 */
	static week(weekShown) {

		const detail = qs('#planning-task-detail');

		const query = new URLSearchParams({
			id: detail.dataset.structure,
			task: detail.dataset.task,
			weekShown: weekShown
		});

		if(detail.dataset.week !== '') {
			query.set('week', detail.dataset.week);
		}

		new Ajax.Query(detail)
			.url('/planning/taskDetail:week?'+ query.toString())
			.method('get')
			.fetch();

	}

	/*
	 * Après la suppression de l'intervention (`series/task:doDelete`, `doDeleteRepeat`). Ouverte par son adresse
	 * (contexte `new`), la page n'a aucun écran sous elle et le rechargement habituel la redemanderait, elle
	 * qui n'existe plus : elle laisse la place à la semaine de l'intervention. Au-dessus d'un écran, cet écran
	 * se recharge comme après toute suppression, par les appels de `ajaxReload` (`ajax.js:785`). Le message
	 * vient après, comme les instructions qui suivent une redirection : retirer les couches l'emporterait.
	 */
	static deleted(url, success) {

		const panel = qs('#panel-planning-task');

		let promise;

		if(panel !== null && panel.dataset.context === 'new') {

			promise = new Ajax.Navigation()
				.url(url)
				.fetch()
				.then(() => {
					// La page seule attend d'être quittée par le retour arrière, qui recharge alors (`Lime.Panel.layer()`) :
					// quittée par navigation, ce rechargement viendrait au prochain retour arrière, sur un autre écran.
					Lime.History.forceRefreshOnBack = false;

					// La page seule masque la barre verticale (`panel-open`) et `--screen-width` n'est recalculé qu'au
					// chargement et au redimensionnement : sans ce calcul, l'écran d'arrivée déborde de la largeur de la barre.
					calculateScreenWidth();
				});

		} else {

			const query = new Ajax.Query();

			Lime.History.saveScroll(Lime.History.currentTimestamp);
			query.headers.set('x-requested-history', Lime.History.currentTimestamp);

			Lime.Dropdown.purge();
			Lime.Panel.purge();

			promise = Lime.History.purgeLayers().then(() => query
				.method('get')
				.url(document.body.dataset.context)
				.fetch());

		}

		promise.then(() => Lime.Alert.showStaticSuccess(success));

	}

}

/*
 * Le Jour de l'écran « Interventions » (L5c) : « Sans jour », puis une colonne par jour, qui défilent de
 * côté (`TaskDayUi`). Les gestes de la carte sont ceux de la Semaine, cocher et décocher, plus le menu
 * du jour et le glisser, et le champ du temps sous une carte faite, que le clic sur son temps ouvre, à la
 * place de la bulle. Chacun répond par les colonnes qu'il change (`taskDay.v.php`).
 *
 * Mêmes délégués posés une fois sur le document que la Semaine, et la même file d'envoi
 * (`PlanningTask.enqueue()`) : un temps tapé puis une coche partent dans l'ordre.
 */
// Cocher et décocher réémettent la colonne de la carte, où une carte faite reste jusqu'au rechargement
// (`TaskDayLib::getColumns()`). La carte cochée dans un jour arrive avec son champ du temps ouvert : cocher
// puis saisir reste deux gestes, comme avec la bulle de la Semaine (`PlanningTaskDay.openDone()`).
document.delegateEventListener('click', '#planning-day [data-planning-task-done]', function(e) {

	e.preventDefault();

	const card = this.closest('.planning-task');
	const task = card.dataset.task;
	const column = card.dataset.column;

	PlanningTaskDay.send(card, 'doDone', {column: column})
		.then(() => PlanningTaskDay.openDone(task, column));

});

document.delegateEventListener('click', '#planning-day [data-planning-task-todo]', function(e) {

	e.preventDefault();

	const card = this.closest('.planning-task');

	PlanningTaskDay.send(card, 'doTodo', {column: card.dataset.column});

});

// Le temps d'une carte faite est dans le lien de la carte : le socle l'écarte de sa navigation
// (`data-ajax-navigation="never"`, `TaskCardUi::getTop()`), et c'est ici que celle du navigateur s'annule.
document.delegateEventListener('click', '#planning-day [data-planning-task-time-open]', function(e) {

	e.preventDefault();

	const card = this.closest('.planning-task');

	if(card.classList.contains('is-editing')) {
		PlanningTaskDay.close();
	} else {
		PlanningTaskDay.open(card);
	}

});

// Le menu du jour s'ouvre dans le `body` (`Lime.Dropdown`) : il porte lui-même sa carte.
document.delegateEventListener('click', '.planning-task-day-menu [data-planning-task-plan]', function(e) {

	e.preventDefault();

	const menu = this.closest('.planning-task-day-menu');

	PlanningTaskDay.plan(menu.dataset.task, menu.dataset.column, this.dataset.planningTaskPlan);

});

/*
 * Le glisser d'une colonne à l'autre, à la souris : un raccourci du menu du jour, jamais le seul moyen,
 * puisqu'il ne vaut rien au doigt (maquette navigable). Seule une carte à faire que l'on peut écrire se
 * glisse (`draggable`, `TaskCardUi::get()`). La colonne survolée montre la place où la carte se rangera
 * (`PlanningTaskDay.placeGhost()`, R-Retours-0810, 231).
 */
document.addEventListener('dragstart', function(e) {

	const card = (e.target instanceof Element) ? e.target.closest('#planning-day .planning-task[draggable="true"]') : null;

	if(card === null) {
		return;
	}

	PlanningTaskDay.startDrag(card, e);

});

document.addEventListener('dragover', function(e) {

	const column = PlanningTaskDay.getDropColumn(e);

	if(column === null) {
		return;
	}

	e.preventDefault();

	// `dragover` se répète tant que le pointeur bouge : la place ne se calcule qu'à l'entrée dans une colonne.
	if(column.classList.contains('planning-day-drop')) {
		return;
	}

	qsa('#planning-day .planning-day-drop', node => node.classList.remove('planning-day-drop'));
	column.classList.add('planning-day-drop');

	PlanningTaskDay.placeGhost(column);

});

document.addEventListener('drop', function(e) {

	const column = PlanningTaskDay.getDropColumn(e);

	if(column === null) {
		return;
	}

	e.preventDefault();

	const card = PlanningTaskDay.dragged;

	PlanningTaskDay.endDrag();

	if(column.dataset.column !== card.dataset.column) {
		PlanningTaskDay.plan(card.dataset.task, card.dataset.column, column.dataset.column);
	}

});

document.addEventListener('dragend', () => PlanningTaskDay.endDrag());

// Au téléphone, « Sans jour » est un rail le long des jours : le toucher l'ouvre par-dessus eux, la flèche
// le referme. Au-delà, la colonne est une colonne comme les autres.
document.delegateEventListener('click', '#planning-day .planning-day-column[data-column="none"]', function(e) {

	if(window.matchMedia('(max-width: 575px)').matches === false) {
		return;
	}

	if(this.classList.contains('is-open') === false) {
		e.preventDefault();
		PlanningTaskDay.openNone(this, true);
	} else if(e.target.closest('[data-planning-day-close]') !== null) {
		e.preventDefault();
		PlanningTaskDay.openNone(this, false);
	}

});

// Le champ du temps d'une carte faite, la fenêtre sans tuile de la bulle de la Semaine : « Enregistrer » le
// referme une fois le temps accepté, la croix et Échap sans rien envoyer.
PlanningDurationStep.register({
	selector: '#planning-day .planning-task-time-field .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTaskDay.saveTime(field, time),
	dismiss: () => PlanningTaskDay.close()
});

window.addEventListener('resize', () => qs('#planning-day .planning-day-scroll', zone => PlanningTaskDay.resize(zone)));

class PlanningTaskDay {

	static dragged = null;

	/*
	 * Le défilement du corps de chaque colonne, par colonne, comme `PlanningTaskYear.scrolls` : un geste
	 * redessine la colonne entière, et son corps neuf repartirait en haut, loin de la carte sur laquelle on
	 * vient d'agir. Relevé au défilement, rendu au rendu du corps neuf (`restoreScroll()`), oublié par une page
	 * neuve (`center()`).
	 */
	static scrolls = new Map();

	/*
	 * Le rail ouvert de « Sans jour » survit à la réponse d'un geste, qui redessine la colonne : un jour
	 * choisi depuis le rail ne le referme pas sous le doigt. Une page neuve le rend fermé (`center()`).
	 */
	static noneOpen = false;

	/*
	 * La colonne sur le point d'accroche à la fin du dernier défilement de côté, et si le cadre butait sur sa
	 * fin, que le redimensionnement rend (`resize()`). Une page neuve le relit (`center()`).
	 */
	static anchor = null;

	/*
	 * L'identifiant de la carte dont le champ du temps est ouvert : chaque heure choisie répond par la
	 * colonne redessinée, où le champ se rouvre (`restore()`), pour que des minutes suivent sans rouvrir le
	 * champ. Un seul champ est ouvert à la fois, et une page neuve les rend fermés.
	 */
	static editing = null;

	/*
	 * Les cartes faites que « Sans jour » garde encore (`kept`) voyagent avec chaque geste, avec le temps
	 * qu'y avait la personne avant la coche, d'où se déduit leur pied d'avant la coche : la colonne que la réponse redessine les garderait sinon jusqu'à
	 * la coche suivante seulement (R-Jour-Fait).
	 */
	static getBody(task) {

		const day = qs('#planning-day');

		return {
			id: day.dataset.structure,
			week: day.dataset.week,
			task: task,
			kept: this.getKept()
		};

	}

	static getKept() {

		return Object.fromEntries(Array.from(qsa('#planning-day-none .planning-task[data-kept]'), card => [card.dataset.task, card.dataset.kept]));

	}

	// Les gestes qui postent leur formulaire, que `PlanningTaskKept` garnit des cartes gardées.
	static KEPT = {
		batch: '/planning/taskDay:doBatch',
		forms: ['/planning/taskDay:doCreate', '/planning/taskDay:doBatchTime'],
		attribute: 'data-planning-kept',
		get: () => PlanningTaskDay.getKept()
	};

	static send(card, action, body = {}) {

		return PlanningTask.enqueue(() => {

			// Un second clic mis en file pendant le premier vise une carte que la réponse a déjà remplacée.
			if(card.isConnected === false) {
				return;
			}

			return new Ajax.Query(card)
				.url('/planning/taskDay:'+ action)
				.body(Object.assign(this.getBody(card.dataset.task), body))
				.fetch()
				.then(json => {
					this.restore();
					return json;
				});

		});

	}

	static open(card) {

		this.close();

		this.editing = card.id;

		card.classList.add('is-editing');

	}

	static close() {

		if(this.editing === null) {
			return;
		}

		qs('#'+ this.editing, card => {

			card.classList.remove('is-editing');

			// Un champ masqué garderait le focus, et le clavier y écrirait encore.
			if(card.contains(document.activeElement)) {
				document.activeElement.blur();
			}

		});

		this.editing = null;

	}

	/*
	 * Le champ se rouvre sur la carte redessinée tant qu'elle en porte un : décochée, elle redevient à faire, et
	 * le champ s'oublie avec elle.
	 */
	static restore() {

		if(this.editing === null) {
			return;
		}

		const card = qs('#'+ this.editing);

		if(card !== null && card.qs('.planning-task-time-field') !== null) {
			card.classList.add('is-editing');
		} else {
			this.editing = null;
		}

	}

	/*
	 * La carte qu'on vient de cocher, restée dans la colonne où on l'a cochée : le temps tapé ira à ce
	 * jour-là. Une carte gardée dans « Sans jour » n'a pas de champ, faute de date, et la copie que la coche
	 * pose dans la colonne d'aujourd'hui y paraît sans rien ouvrir (R-Jour-Fait) : son temps s'ouvre par un
	 * clic sur son temps. Une carte cochée sur un jour à venir n'en a aucun.
	 */
	static openDone(task, column) {

		const field = qs('#planning-task-'+ task +'-'+ column +' .planning-task-time-field');

		if(field !== null) {
			this.open(field.closest('.planning-task'));
		}

	}

	/*
	 * Le jour choisi au menu ou par le glisser : une date, « Sans jour » (`none`), qui garde l'intervention
	 * dans la semaine affichée, ou la semaine suivante (`next`), que l'écran porte (`data-week-next`).
	 */
	static plan(task, column, value) {

		const card = qs('#planning-task-'+ task +'-'+ column);

		if(card === null) {
			return;
		}

		const day = qs('#planning-day');

		let body;

		switch(value) {

			case 'none' :
				body = {'planned[week]': day.dataset.week};
				break;

			case 'next' :
				body = {'planned[week]': day.dataset.weekNext};
				break;

			default :
				body = {'planned[date]': value};
				break;

		}

		this.send(card, 'doPlanned', body);

	}

	static getDropColumn(e) {

		if(this.dragged === null || e.target instanceof Element === false) {
			return null;
		}

		return e.target.closest('#planning-day .planning-day-column');

	}

	/*
	 * L'image du glisser est la carte telle qu'elle est, détachée de sa colonne (`sortable-fallback`, comme les
	 * listes que Sortable réordonne) ; la carte d'origine reste à sa place en pointillés (`util-drag-ghost`). Le
	 * navigateur prend l'image à la fin de `dragstart` : la copie hors de l'écran ne sert qu'à ce moment-là, et
	 * la carte d'origine ne change qu'ensuite, sans quoi l'image serait celle des pointillés. La copie se pose
	 * à côté de la carte, dont elle garde ainsi les règles, toutes bornées par l'écran.
	 */
	static startDrag(card, e) {

		this.dragged = card;

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', card.dataset.task);

		const bounds = card.getBoundingClientRect();

		const image = this.getCopy(card);
		image.classList.add('sortable-fallback', 'planning-day-drag-image');
		image.style.width = bounds.width +'px';

		card.after(image);

		e.dataTransfer.setDragImage(image, e.clientX - bounds.left, e.clientY - bounds.top);

		setTimeout(() => {
			image.remove();
			card.classList.add('util-drag-ghost');
		});

	}

	/*
	 * La place de la carte dans la colonne survolée, en pointillés : la réponse range une carte dans le groupe
	 * de son action, et les cartes comme les groupes dans l'ordre de leurs clés de rang (`data-sort`,
	 * `TaskWeekLib::getSort()`). Sans groupe de son action dans la colonne, la carte arrive avec la tête de son
	 * groupe, à son rang. Sa propre colonne, et une colonne qui la porte déjà, ne montrent rien.
	 *
	 * Un groupe se range par sa première carte : il n'existe que par elles, `TaskDayUi::getTasks()` l'ouvrant sur
	 * une carte et toute réponse redessinant la colonne entière. Sous une recherche, la place est une carte que
	 * la recherche trouve, puisqu'elle repasse à chaque changement de l'écran (`PlanningTaskSearch`) : son
	 * groupe se montre même quand toutes ses cartes sont masquées, et le compte de la colonne la compte déjà,
	 * comme il comptera la carte déposée.
	 */
	static placeGhost(column) {

		this.removeGhost();

		const card = this.dragged;

		if(
			column.dataset.column === card.dataset.column ||
			column.qs('.planning-task[data-task="'+ card.dataset.task +'"]') !== null
		) {
			return;
		}

		const group = card.closest('.planning-day-action');
		const sort = card.dataset.sort;

		const ghost = this.getCopy(card);
		ghost.classList.add('util-drag-ghost', 'planning-day-ghost');

		const groupTarget = column.qs('.planning-day-action[data-action="'+ group.dataset.action +'"]');

		if(groupTarget !== null) {

			const next = Array.from(groupTarget.qsa('.planning-task')).find(node => node.dataset.sort > sort) ?? null;
			groupTarget.insertBefore(ghost, next);

		} else {

			const groupGhost = document.createElement('div');
			groupGhost.className = 'planning-day-action planning-day-ghost';
			groupGhost.append(group.qs('.planning-day-action-head').cloneNode(true), ghost);

			const next = Array.from(column.qsa('.planning-day-action')).find(node => node.qs('.planning-task').dataset.sort > sort) ?? null;
			const body = column.qs('.planning-day-body');

			body.qs('.util-block-empty', empty => empty.classList.add('hide'));
			body.insertBefore(groupGhost, next);

		}

	}

	/*
	 * Une copie de la carte, sans ses identifiants, que la page ne doit trouver qu'une fois, et qui ne se glisse
	 * pas.
	 */
	static getCopy(card) {

		const copy = card.cloneNode(true);

		copy.removeAttribute('id');
		copy.removeAttribute('draggable');
		copy.classList.remove('util-drag-ghost', 'batch-checkbox');
		copy.qsa('[id]', node => node.removeAttribute('id'));

		return copy;

	}

	static removeGhost() {

		qsa('#planning-day .planning-day-ghost', node => node.remove());
		qsa('#planning-day .planning-day-body > .util-block-empty.hide', empty => empty.classList.remove('hide'));

	}

	static endDrag() {

		this.dragged?.classList.remove('util-drag-ghost');
		this.dragged = null;

		this.removeGhost();

		qsa('#planning-day .planning-day-drop', node => node.classList.remove('planning-day-drop'));

	}

	static openNone(column, open) {

		this.noneOpen = open;

		column.classList.toggle('is-open', open);

	}

	static renderNone(column) {
		column.classList.toggle('is-open', this.noneOpen);
	}

	/*
	 * À l'ouverture, aujourd'hui se cale sur le point d'accroche (contre le rail « Sans jour » sous 576, au
	 * bord du cadre à partir de 576), comme dans le planning actuel (`series/static/js/task.js`) et la maquette
	 * (`centrerJour()`). Les jours qui suivent restent à sa droite, pour qu'on lise la semaine dans l'ordre à
	 * partir d'aujourd'hui.
	 */
	static center(zone) {

		this.noneOpen = false;
		this.editing = null;
		this.scrolls.clear();

		// Le défilement d'un élément ne remonte pas : le cadre le voit en capture.
		zone.addEventListener('scroll', e => this.remember(e.target), {capture: true, passive: true});

		// `scrollend` ne remonte pas non plus : sans capture, seul le défilement de côté du cadre l'émet ici.
		// Safari ne l'émet pas : le jour se relit alors quand le défilement s'est tu 150 ms, sans quoi un
		// redimensionnement ramènerait toujours au jour d'ouverture. Le raccrochage du navigateur au
		// redimensionnement émet aussi un `scroll`, mais `resize()` passe avant le délai et replace le jour.
		if('onscrollend' in window) {
			zone.addEventListener('scrollend', () => this.anchor = this.getAnchor(zone), {passive: true});
		} else {

			let timeout = null;

			zone.addEventListener('scroll', e => {

				if(e.target === zone) {
					clearTimeout(timeout);
					// Un cadre redessiné entre-temps (navigation, geste qui rend l'écran) ne doit pas écraser
					// le jour de la page neuve, posé par son propre `center()`.
					timeout = setTimeout(() => {
						if(zone.isConnected) {
							this.anchor = this.getAnchor(zone);
						}
					}, 150);
				}

			}, {passive: true});

		}

		this.size(zone);

		this.place(zone, zone.qs('.planning-day-today'));

		this.anchor = this.getAnchor(zone);

	}

	static remember(target) {

		if(target instanceof Element && target.matches('.planning-day-body')) {
			this.scrolls.set(target.parentElement.dataset.column, target.scrollTop);
		}

	}

	static restoreScroll(body) {

		const top = this.scrolls.get(body.parentElement.dataset.column);

		if(top !== undefined) {
			body.scrollTop = top;
		}

	}

	/*
	 * Une colonne contre le bord gauche du cadre, sur son point d'accroche : contre le rail « Sans jour » au
	 * Jour sous 576 px, qui pose son retrait dans `scroll-padding-left` (`size()`), au bord même sinon.
	 * Le cadre bute sur sa fin : une colonne trop à droite s'arrête où il s'arrête.
	 */
	static place(zone, column) {

		if(column === null) {
			return;
		}

		const left = zone.scrollLeft + column.getBoundingClientRect().left - zone.getBoundingClientRect().left - zone.clientLeft;
		const padding = parseFloat(zone.style.scrollPaddingLeft) || 0;

		zone.scrollLeft = left - padding;

	}

	/*
	 * Au redimensionnement, `size()` repartage les colonnes mais le défilement reste en pixels : le jour qui
	 * était sur le point d'accroche glisse, et en rétrécissant, le dimanche se retrouve coupé à droite (C5,
	 * R-Retours-0410-soir). Le jour accroché ne se lit pas au `resize` : le cadre a déjà changé de largeur, et
	 * le navigateur a déjà raccroché le défilement à un point de `scroll-snap`, souvent un autre jour quand le
	 * cadre butait sur sa fin, où aucun point d'accroche ne tombe. Il se lit donc à la fin de chaque défilement
	 * (`anchor`, posé par `center()`), et se rend après le partage : la fin du cadre si le cadre y butait, la
	 * même colonne sur le point d'accroche sinon.
	 */
	static resize(zone) {

		const anchor = this.anchor;

		this.size(zone);

		if(anchor === null) {
			return;
		}

		if(anchor.end) {
			zone.scrollLeft = zone.scrollWidth;
		} else {
			this.place(zone, zone.qs('.planning-day-column[data-column="'+ anchor.column +'"]'));
		}

	}

	/*
	 * La première colonne dont le bord droit dépasse le point d'accroche, et si le cadre bute sur sa fin.
	 * « Sans jour » en est une, sauf en rail collé au téléphone, où il couvre toujours le début du cadre.
	 * Ne fait que mesurer.
	 */
	static getAnchor(zone) {

		const from = zone.getBoundingClientRect().left + zone.clientLeft + (parseFloat(zone.style.scrollPaddingLeft) || 0);

		const column = Array.from(zone.qsa('.planning-day-column'))
			.filter(column => column.dataset.column !== 'none' || window.getComputedStyle(column).position !== 'sticky')
			.find(column => column.getBoundingClientRect().right > from + 1) ?? null;

		return {
			column: (column === null) ? null : column.dataset.column,
			end: (zone.scrollLeft >= zone.scrollWidth - zone.clientWidth - 1)
		};

	}

	/*
	 * Au téléphone, « Sans jour » est un rail qui colle à gauche, et les jours s'accrochent contre lui
	 * (`scroll-padding`), à l'écart que font le pas des colonnes et sa marge propre (`separation`). À partir de
	 * la tablette, il défile avec les jours (R-Retours-1210 V60) : les jours s'accrochent au bord du cadre et
	 * s'en partagent toute la largeur en un nombre entier de colonnes d'au moins 20rem, comme à l'Année, et un
	 * jour seul prend toute la largeur sinon. « Sans jour » et sa marge prennent la place d'un jour (CSS),
	 * donc aucun jour n'est entamé à droite, ni au début du cadre ni calé sur un jour. Tout est lu avant
	 * d'écrire.
	 *
	 * La rangée d'outils colle sous la barre du haut à partir de 576 px, et le cadre se retranche de sa hauteur
	 * (`--planning-day-tools`) : sinon, le bas d'une colonne qui défile seule ne se lirait qu'en passant sa tête
	 * sous la rangée (R-Retours-0410, point 16).
	 */
	static size(zone) {

		const none = zone.qs('.planning-day-column[data-column="none"]');
		const columns = zone.qs('.planning-day-columns');
		const tools = zone.closest('#planning-day').qs(':scope > .planning-tools');

		const gap = parseFloat(window.getComputedStyle(columns).columnGap) || 0;
		const separation = gap + (parseFloat(window.getComputedStyle(none).marginRight) || 0);
		const width = none.offsetWidth;
		const rem = parseFloat(window.getComputedStyle(document.documentElement).fontSize);
		const free = zone.clientWidth;
		const desktop = window.matchMedia('(min-width: 576px)').matches;
		const toolsHeight = tools.offsetHeight;

		zone.style.setProperty('--planning-day-gap', separation +'px');
		zone.style.setProperty('--planning-day-tools', toolsHeight +'px');

		if(desktop) {
			zone.style.scrollPaddingLeft = '';
			this.share(zone, Math.min(20 * rem, free), free, gap);
		} else {
			zone.style.scrollPaddingLeft = (width + separation) +'px';
		}

	}

	/*
	 * La largeur libre se partage en un nombre entier de colonnes d'au moins `unit`, écartées de `gap`, sur tout
	 * le cadre au Jour comme à l'Année (`PlanningTaskYear.size()`). N'écrit que.
	 */
	static share(zone, unit, free, gap) {

		const count = Math.max(1, Math.floor((free + gap) / (unit + gap)));

		zone.style.setProperty('--planning-day-width', ((free - (count - 1) * gap) / count) +'px');

	}

	// Le jour est celui de la colonne de la carte.
	static saveTime(field, time) {

		const card = field.closest('.planning-task');

		return this.send(card, 'doTime', {time: time, date: card.dataset.column});

	}

}

/*
 * Les cartes gardées du Jour (« Sans jour », `PlanningTaskDay.getKept()`) et de la semaine en Colonnes (« À faire »,
 * `PlanningTask.getKept()`, R-Retours-1210 V40) dans les gestes qui postent leur formulaire (`form.js`) et non
 * `getBody()` : la barre du lot, « Nouvelle intervention », la saisie du temps en lot, celle d'une récolte. Elles y
 * entrent à l'envoi, relues à ce moment-là, au clic pour le lot (R-Lot-Garde), à l'événement `submit` pour un
 * panneau, qui part aussi par la touche Entrée (R-Garde-Gestes). Pas en `data-form-zombie`, que `form.js` efface
 * avant de poster. Chaque écran décrit ses gestes dans sa constante `KEPT` ; ses champs portent son propre
 * attribut, pour que l'émission d'un écran ne retire pas ceux de l'autre.
 */
class PlanningTaskKept {

	static emit(e, kept) {

		const link = (e.target instanceof Element) ? e.target.closest('[data-ajax-submit]') : null;

		if(link === null) {
			return;
		}

		const form = link.hasAttribute('data-ajax-target') ? qs(link.getAttribute('data-ajax-target')) : link.firstParent('form');

		if(form === null) {
			return;
		}

		this.set(form, kept, link.getAttribute('data-ajax-submit').startsWith(kept.batch));

	}

	static emitForm(e, kept) {

		const form = e.target;

		if(form instanceof HTMLFormElement === false || form.hasAttribute('data-ajax-form') === false) {
			return;
		}

		const url = form.getAttribute('data-ajax-form');

		// Le geste du lot repasse ici : `form.js` poste son formulaire par un `submit`, et retirer les champs posés au clic le priverait des cartes gardées.
		if(kept.forms.some(prefix => url.startsWith(prefix)) === false) {
			return;
		}

		this.set(form, kept, true);

	}

	static set(form, kept, emit) {

		// Les autres envois du même formulaire, en GET compris, ne portent pas les champs d'un geste précédent.
		form.qsa('['+ kept.attribute +']', node => node.remove());

		if(emit === false) {
			return;
		}

		Object.entries(kept.get()).forEach(([task, value]) => {
			form.insertAdjacentHTML('afterbegin', '<input type="hidden" '+ 'name'.attr('kept['+ task +']') +' '+ 'value'.attr(value) +' '+ kept.attribute +'="1"/>');
		});

	}

}

// En capture sur `window` : les délégués de `document`, celui de `form.js` compris, tournent dans l'ordre de leur pose, et lui arrête la propagation.
window.addEventListener('click', e => PlanningTaskKept.emit(e, PlanningTaskDay.KEPT), true);
window.addEventListener('submit', e => PlanningTaskKept.emitForm(e, PlanningTaskDay.KEPT), true);
window.addEventListener('click', e => PlanningTaskKept.emit(e, PlanningTask.KEPT), true);
window.addEventListener('submit', e => PlanningTaskKept.emitForm(e, PlanningTask.KEPT), true);

/*
 * La fiche de temps (L6b) : la journée de travail d'une personne, et son temps sur chaque intervention
 * le jour choisi. Tout ce qui s'écrit part au nom de la personne de l'onglet, que l'écran porte avec le
 * jour (`#planning-time`, `data-*`), et passe par la file de l'écran
 * (`PlanningTask.enqueue()`) : une coche suivie d'un temps tapé vite arrivent dans l'ordre.
 */
document.delegateEventListener('click', '#planning-time [data-planning-task-done]', function(e) {

	e.preventDefault();

	const row = this.closest('.planning-time-row');

	PlanningTimeDay.send(row, 'doDone');

});

// Le pli de la fiche de l'équipe, le détail du jour d'une personne : rien n'est retenu, la fiche le rend
// replié.
document.delegateEventListener('click', '#planning-time .planning-time-fold-head', function(e) {

	e.preventDefault();

	this.closest('.util-fold').classList.toggle('util-fold-open');

});

document.delegateEventListener('click', '#planning-time [data-planning-task-todo]', function(e) {

	e.preventDefault();

	PlanningTimeDay.send(this.closest('.planning-time-row'), 'doTodo');

});

// Le temps d'une ligne : la tuile, et par-dessus la page la fenêtre du champ par pas.
PlanningDurationStep.register({
	selector: '#planning-time .planning-time-time .planning-duration-step',
	bounded: true,
	change: (field, time) => PlanningTimeDay.saveTime(field, time)
});

// Le glisser sur la tuile d'une ligne, sur la fiche seulement (R-Retours-0810, 201, tranché par V-Planning).
PlanningPointer.register({
	selector: '#planning-time .planning-time-time > .planning-duration-step-tile',
	prevent: true,
	start: (tile, e) => PlanningDurationStep.grab(tile, e),
	move: e => PlanningDurationStep.drag(e.clientX),
	end: () => PlanningDurationStep.drop(),
	cancel: () => PlanningDurationStep.release()
});

window.addEventListener('click', e => PlanningDurationStep.click(e), true);

/*
 * La journée de travail d'une personne un jour (`TimeDayUi::getWorking()`), où qu'elle soit : sur la fiche
 * de temps, sous la pastille du jour, ou sur une ligne de l'Équipe de la fiche. Son conteneur porte ce
 * qu'elle poste, la structure, la semaine, le jour, la personne et l'écran d'où elle part (`origin`, vide
 * sur la fiche, `team` sur l'Équipe), que `timeDay:doWorkingTime` lit pour
 * savoir par quoi répondre. Rien ne part avant « Enregistrer » ou Entrée, après quoi la saisie se referme
 * (planche R-Retours-0810, retour 198).
 */
PlanningDuration.register({
	selector: '.planning-duration.planning-time-working:not(.planning-duration-range)',
	bounded: true,
	change: (duration, time) => PlanningTimeWorking.post(duration, time)
});

/*
 * La même journée sur une structure à horaires (`DurationUi::getTiledRange()`) : l'arrivée, le départ et la
 * pause, que « Enregistrer » ou Entrée envoient. La durée lue suit les champs à chaque frappe ; c'est le serveur
 * qui l'écrit (`hr\WorkingTimeLib::prepareTime()`). « Vider » efface la journée. La fenêtre reste ouverte
 * pendant l'envoi : la réponse la remplace par la journée écrite, un refus la laisse avec son message sous le
 * champ refusé. Fermée sans enregistrer, elle reprend les horaires de la journée écrite.
 */
PlanningDuration.register({
	selector: '.planning-duration-range',
	bounded: true,
	change: null,
	discard: duration => PlanningTimeRange.reset(duration)
});

document.delegateEventListener('click', '.planning-duration-range [data-planning-range-save]', function(e) {

	e.preventDefault();

	PlanningTimeRange.save(this.closest('.planning-duration-range'));

});

document.delegateEventListener('click', '.planning-duration-range [data-planning-range-clear]', function(e) {

	e.preventDefault();

	PlanningTimeRange.clear(this.closest('.planning-duration-range'));

});

document.delegateEventListener('input', '.planning-duration-range .planning-time-range input', function() {
	PlanningTimeRange.read(this.closest('.planning-duration-range'));
});

// Entrée enregistre sans envoyer le formulaire qui loge la saisie.
document.delegateEventListener('keydown', '.planning-duration-range .planning-time-range input', function(e) {

	if(e.key === 'Enter') {
		e.preventDefault();
		PlanningTimeRange.save(this.closest('.planning-duration-range'));
	}

});

class PlanningTimeDay {

	/*
	 * La variante est celle de la table de la ligne : la semaine et chaque zone ont la leur (CF). Les zones
	 * disent si « Non planifié » est lu, lesquelles sont repliées, quels blocs sont dépliés, lesquelles ont la
	 * colonne des commentaires et quelles lignes un geste y a gardées à leur place (`retained`), pour qu'une
	 * ligne qui y entre ou en sort s'y place comme l'écran les montre.
	 */
	static getBody(row) {

		const sheet = qs('#planning-time');
		const zones = qs('#planning-time-zones');

		const body = {
			id: sheet.dataset.structure,
			week: sheet.dataset.week,
			date: sheet.dataset.date,
			user: sheet.dataset.user,
			columns: row.closest('.planning-time-table').dataset.columns
		};

		if(zones !== null) {
			body.unplanned = zones.dataset.unplanned;
			body.folded = zones.dataset.folded;
			body.expanded = zones.dataset.expanded;
			body.retained = zones.dataset.retained;
			body.commented = Array.from(zones.qsa('.planning-time-table-comments'), table => table.closest('[data-planning-time-zone]').dataset.planningTimeZone).join(',');
		}

		return body;

	}

	/*
	 * La ligne réémet le jour où l'écran la range (`dayWeek`) : un geste ne la déplace pas
	 * sous le pointeur, elle ne se range qu'au prochain affichage de la fiche. Une ligne de zone réémet
	 * sa zone et si elle ouvre son action, pour y rester jusqu'au rechargement.
	 */
	static send(row, action, body = {}) {

		return PlanningTask.enqueue(() => {

			// Un second clic mis en file pendant le premier vise une ligne que la réponse a déjà remplacée.
			if(row.isConnected === false) {
				return;
			}

			return this.post(row, action, body);

		});

	}

	static post(row, action, body) {

		const zone = row.closest('[data-planning-time-zone]');

		const position = (zone === null) ? {} : {
			zone: zone.dataset.planningTimeZone,
			actionFirst: row.dataset.actionFirst
		};

		return new Ajax.Query(row)
			.url('/planning/timeDay:'+ action)
			.body(Object.assign(this.getBody(row), {
				task: row.dataset.task,
				dayWeek: row.dataset.dayWeek
			}, position, body))
			.fetch();

	}

	/*
	 * Le temps part pour la ligne telle qu'elle est quand son tour vient : deux enregistrements rapprochés
	 * partent du même nœud, que la réponse du premier remplace. Le second part donc pour la ligne redessinée
	 * au lieu d'être perdu.
	 */
	static saveTime(field, time) {

		const id = field.closest('.planning-time-row').id;
		const body = {time: time};

		return PlanningTask.enqueue(() => {

			const row = document.getElementById(id);

			if(row === null) {
				return;
			}

			return this.post(row, 'doTime', body);

		});

	}

}

/*
 * Les zones sous la fiche (CF) : leur titre les ouvre et les ferme. « Non planifié » n'est lu qu'à sa
 * première ouverture.
 */
document.delegateEventListener('click', '#planning-time-zones .util-fold-head', function(e) {

	e.preventDefault();

	PlanningTimeZone.toggle(this.closest('[data-planning-time-zone]'));

});

document.delegateEventListener('click', '#planning-time-zones [data-planning-time-more]', function(e) {

	e.preventDefault();

	PlanningTimeZone.more(this);

});

class PlanningTimeZone {

	static toggle(zone) {

		const open = zone.classList.toggle('util-fold-open');

		this.fold();

		if(open === false || zone.qs('.planning-time-table') !== null) {
			return;
		}

		const sheet = qs('#planning-time');

		const query = new URLSearchParams({
			id: sheet.dataset.structure,
			week: sheet.dataset.week,
			date: sheet.dataset.date,
			user: sheet.dataset.user
		});

		new Ajax.Query(zone)
			.url('/planning/timeDay:unplanned?'+ query.toString())
			.method('get')
			.fetch();

	}

	/*
	 * « Voir les N autres » d'une action : l'écran réémet les blocs déjà dépliés, auxquels la réponse ajoute
	 * celui-ci.
	 */
	static more(link) {

		const sheet = qs('#planning-time');

		const query = new URLSearchParams({
			id: sheet.dataset.structure,
			week: sheet.dataset.week,
			date: sheet.dataset.date,
			user: sheet.dataset.user,
			zone: link.closest('[data-planning-time-zone]').dataset.planningTimeZone,
			action: link.dataset.planningTimeMore,
			columns: link.closest('.planning-time-table').dataset.columns,
			expanded: qs('#planning-time-zones').dataset.expanded,
			retained: qs('#planning-time-zones').dataset.retained
		});

		new Ajax.Query(link)
			.url('/planning/timeDay:more?'+ query.toString())
			.method('get')
			.fetch();

	}

	// Les zones montrées que la personne a repliées, que les gestes réémettent.
	static fold() {

		const zones = qs('#planning-time-zones');

		zones.dataset.folded = Array.from(zones.qsa('[data-planning-time-zone]:not(.util-fold-open)'))
			.filter(zone => zone.qs('.planning-time-table') !== null)
			.map(zone => zone.dataset.planningTimeZone)
			.join(',');

	}

}

class PlanningTimeRange {

	static save(duration) {

		const pause = this.getPause(duration);

		return this.send(duration, {
			start: duration.qs('input[name="start"]').value,
			end: duration.qs('input[name="end"]').value,
			pause: (pause === null) ? '' : pause
		});

	}

	static clear(duration) {
		return this.send(duration, {clear: 1});
	}

	/*
	 * La saisie n'est plus à rouvrir quand une autre réponse redessine l'écran (`PlanningDuration.reopen()`) :
	 * la sienne la remplace.
	 */
	static send(duration, values) {

		PlanningDuration.kept = null;
		PlanningDuration.draft = null;

		return PlanningTask.enqueue(() => new Ajax.Query(duration)
			.url('/planning/timeDay:doWorkingTime')
			.body(Object.assign(PlanningTimeWorking.getBody(duration), values))
			.fetch()
		).then(json => {

			if(PlanningDuration.refused(json) === false && duration.isConnected) {
				PlanningDuration.hide(duration);
			}

			return json;

		});

	}

	/*
	 * La pause en heures, `null` sans rien de saisi. Une case que le focus a vidée garde sa valeur en
	 * indication (`Timesheet.updateFocusInTime()`).
	 */
	static getPause(duration) {

		const hour = PlanningDuration.readTyped(duration.qs('.planning-time-range input[name="timeAddHour"]'));
		const minute = PlanningDuration.readTyped(duration.qs('.planning-time-range input[name="timeAddMinute"]'));

		if(hour === null && minute === null) {
			return null;
		}

		return (hour ?? 0) + (minute ?? 0) / 60;

	}

	static getMinutes(input) {

		if(input.value === '') {
			return null;
		}

		const [hour, minute] = input.value.split(':').map(value => parseInt(value));

		return hour * 60 + minute;

	}

	// La durée lue, celle que le serveur calculera : zéro tant que les horaires n'en donnent pas une.
	static read(duration) {

		const start = this.getMinutes(duration.qs('input[name="start"]'));
		const end = this.getMinutes(duration.qs('input[name="end"]'));

		let minutes = 0;

		if(start !== null && end !== null) {
			minutes = Math.max(0, end - start - Math.round((this.getPause(duration) ?? 0) * 60));
		}

		duration.qs('.planning-time-panel-readout', readout => {

			readout.classList.toggle('planning-time-panel-readout-empty', minutes === 0);

			readout.qs('.planning-time-panel-title b').textContent = Math.floor(minutes / 60);
			readout.qs('.planning-time-panel-title > span:last-child').textContent = String(minutes % 60).padStart(2, '0');

		});

	}

	static reset(duration) {

		duration.qsa('.planning-time-range input', input => input.value = input.defaultValue);
		Lime.Alert.hideErrors(duration);

		this.read(duration);

	}

}

class PlanningTimeWorking {

	static getBody(container) {

		const body = {
			id: container.dataset.structure,
			week: container.dataset.week,
			date: container.dataset.date,
			user: container.dataset.user
		};

		if(container.dataset.origin !== '') {
			body.origin = container.dataset.origin;
		}

		// La semaine saisie jour par jour garde le jour choisi à l'écran (`#planning-time`, `data-selected`), vide
		// quand c'est la semaine qui l'est, et la réponse redessine le jour avec. Ailleurs, le serveur prend le jour saisi.
		if(container.closest('.util-days-entry') !== null) {
			body.selected = qs('#planning-time').dataset.selected;
		}

		return body;

	}

	// L'envoi de la saisie et de la réglette du même jour (`PlanningTimeRule`), dans la file commune.
	static post(container, time) {

		const body = this.getBody(container);

		return PlanningTask.enqueue(() => new Ajax.Query(container)
			.url('/planning/timeDay:doWorkingTime')
			.body(Object.assign(body, {time: PlanningTask.round(time)}))
			.fetch()
		);

	}

}

/*
 * La réglette de la journée (`TimeWeekUi::getRule()`), sur la fiche de temps et dans chaque ligne de son
 * Équipe : un clic ou un glissé pose la journée au quart d'heure, sur
 * l'échelle de la barre (`data-scale`), et elle ne part qu'au lâcher, par l'envoi de la grille du même jour
 * (`PlanningTimeWorking.post()`). Lâcher sur la journée déjà saisie n'écrit rien ; lâcher à zéro l'efface, comme
 * « Remettre à zéro » de sa saisie. Le geste, à la souris comme au doigt, est celui de `PlanningPointer`.
 */
PlanningPointer.register({
	selector: '[data-planning-time-rule]',
	prevent: true,
	start: (rule, e) => PlanningTimeRule.start(rule, e),
	move: e => PlanningTimeRule.place(e.clientX),
	end: () => PlanningTimeRule.end(),
	cancel: () => PlanningTimeRule.cancel()
});

document.addEventListener('pointermove', e => PlanningTimeRule.hover(e));

document.delegateEventListener('pointerover', '[data-planning-time-rule]', function(e) {
	PlanningTimeRule.enter(this, e);
});

document.delegateEventListener('pointerout', '[data-planning-time-rule]', function(e) {
	PlanningTimeRule.leave(this, e);
});

class PlanningTimeRule {

	/*
	 * La piste de chaque geste garde sa géométrie, lue une seule fois, au début de la saisie ou à l'entrée de
	 * la souris : les déplacements ne font qu'écrire, une lecture après une écriture forçant un recalcul de
	 * la page à chaque mouvement.
	 *
	 * - `drag` : la saisie en cours, sa piste, sa géométrie et la valeur visée ;
	 * - `hovered` : la piste sous la souris, pour la valeur qu'un clic poserait.
	 *
	 * Une journée au-delà de l'échelle se dit par le bout hachuré de sa réglette (`planning-time-rule-end`),
	 * dont l'abscisse de début est lue avec la géométrie (`beyond`). Un geste qui ne quitte pas ce bout garde la
	 * journée, puisqu'aucune valeur de la réglette ne dépasse l'échelle : un clic n'efface pas sans rien dire
	 * ce qui va au-delà de 12h (R-Retours-0410-soir, T, tranché par V-Planning). Un glissé qui en sort pose la
	 * valeur visée, comme sur toute réglette, et le reste jusqu'au lâcher, même revenu dans le bout.
	 */
	static drag = null;
	static hovered = null;

	static start(rule, e) {

		PlanningDuration.close();

		this.drag = {rule: rule, rect: rule.getBoundingClientRect(), beyond: this.getBeyond(rule), value: null};
		this.place(e.clientX);

	}

	static hover(e) {

		if(this.drag === null && this.hovered !== null) {
			this.showGhost(this.hovered.rule, this.getValue(this.hovered, e.clientX));
		}

	}

	static end() {

		const rule = this.drag.rule;
		const value = this.drag.value;

		this.drag = null;

		if(value === this.getTime(rule)) {
			this.reset(rule);
			return;
		}

		this.hideGhost(rule);

		// La tuile se retrouve par sa clé, que la réglette porte (`TimeWeekUi::getRule()`) : sur la fiche, elle est
		// au-dessus de la réglette, dans l'Équipe au bout de sa ligne.
		PlanningTimeWorking.post(document.qs('.planning-duration[data-duration="'+ rule.dataset.working +'"]'), value);

	}

	static cancel() {

		if(this.drag !== null) {
			this.reset(this.drag.rule);
			this.drag = null;
		}

	}

	static enter(rule, e) {

		if(e.pointerType !== 'mouse' || this.drag !== null || (this.hovered !== null && this.hovered.rule === rule)) {
			return;
		}

		this.hovered = {rule: rule, rect: rule.getBoundingClientRect(), beyond: this.getBeyond(rule)};

	}

	static leave(rule, e) {

		if(e.relatedTarget !== null && rule.contains(e.relatedTarget)) {
			return;
		}

		this.hovered = null;

		if(this.drag === null) {
			this.hideGhost(rule);
		}

	}

	static place(x) {

		const rule = this.drag.rule;

		if(this.drag.beyond !== null && x < this.drag.beyond) {
			this.drag.beyond = null;
		}

		const value = this.getValue(this.drag, x);

		this.drag.value = value;
		this.showGhost(rule, value);
		rule.classList.toggle('planning-time-rule-beyond', value > parseFloat(rule.dataset.scale));
		rule.qs('.planning-time-rule-knob').style.left = this.getPercent(rule, value);

	}

	// L'abscisse où commence le bout hachuré, NULL sur une journée dans l'échelle.
	static getBeyond(rule) {

		if(rule.classList.contains('planning-time-rule-beyond') === false) {
			return null;
		}

		return rule.qs('.planning-time-rule-end').getBoundingClientRect().left;

	}

	static getTime(rule) {
		return (rule.dataset.time === '') ? 0 : parseFloat(rule.dataset.time);
	}

	static getValue(state, x) {

		if(state.beyond !== null && x >= state.beyond) {
			return this.getTime(state.rule);
		}

		const scale = parseFloat(state.rule.dataset.scale);
		const value = (x - state.rect.left) / state.rect.width * scale;

		return Math.min(scale, Math.max(0, Math.round(value * 4) / 4));

	}

	static getPercent(rule, value) {
		return (100 * Math.min(value, parseFloat(rule.dataset.scale)) / parseFloat(rule.dataset.scale)) +'%';
	}

	// La bulle porte deux lignes, le libellé que la réglette lui donne (`TimeWeekUi::getRule()`), puis la durée.
	static showGhost(rule, value) {

		let ghost = rule.qs('.planning-time-rule-ghost');

		if(ghost === null) {

			ghost = document.createElement('span');
			ghost.className = 'planning-time-rule-ghost';

			const label = document.createElement('span');
			label.textContent = rule.dataset.label;

			const time = document.createElement('span');
			time.className = 'planning-time-rule-ghost-time';

			ghost.append(label, time);
			rule.append(ghost);

		}

		const percent = this.getPercent(rule, value);

		// La bulle glisse sur son point autant que le point sur la réglette : centrée au milieu, elle s'aligne
		// sur le bord aux extrémités, où « Journée », centré, sortirait de l'écran du téléphone.
		ghost.qs('.planning-time-rule-ghost-time').textContent = PlanningTask.format(value);
		ghost.style.left = percent;
		ghost.style.transform = 'translateX(-'+ percent +')';

	}

	static hideGhost(rule) {
		rule.qs('.planning-time-rule-ghost', ghost => ghost.remove());
	}

	// La poignée revient sur la journée saisie, à zéro quand il n'y en a pas.
	static reset(rule) {

		const time = this.getTime(rule);

		this.hideGhost(rule);
		rule.classList.toggle('planning-time-rule-beyond', time > parseFloat(rule.dataset.scale));
		rule.qs('.planning-time-rule-knob').style.left = this.getPercent(rule, time);

	}

}

/*
 * La sélection de la Semaine et du Jour (L5d, `TaskBatchUi`) : la case de chaque carte, la case de tête
 * d'un bloc ou d'un groupe d'action, et la barre de lot du produit (`batch.js`), en bas d'écran dès la
 * première carte cochée. La grille de la Semaine (DG-3) coche ses lignes de la même façon, sans mode
 * « Sélectionner » : seules ses lignes d'intervention portent `data-task`, celles du pense-bête n'ont rien à cocher.
 *
 * Le serveur ne rend jamais une case cochée, pour qu'une réponse ciblée reste octet pour octet le
 * rechargement (`FingerprintTest`) : la sélection vit ici, dans un ensemble d'identifiants, que chaque
 * case relit en arrivant à l'écran (`restore()`, par son `onrender`). Une carte remplacée par la réponse
 * d'un geste revient donc cochée si elle l'était. L'ensemble se vide à chaque navigation et à chaque rendu
 * entier de l'écran, rechargement compris (`reset()`).
 */
document.delegateEventListener('input', ':is(#planning-week, #planning-day) :is(.planning-task, .planning-grid-row[data-task]) [name="batch[]"]', function() {

	PlanningTaskSelection.toggle(this);

});

document.delegateEventListener('input', ':is(#planning-week, #planning-day) [data-planning-select-group]', function() {

	PlanningTaskSelection.toggleGroup(this);

});

// Seuls la Semaine et le Jour posent `reset()` sur `#planning-board` : une navigation vers un autre écran
// vide l'état ici. `navigation.wakeup` part après le rendu, d'où `clear()` sur la Semaine ou le Jour arrivés.
document.addEventListener('navigation.wakeup', () => {
	PlanningTaskSelection.reset();
	qs('#planning-week, #planning-day', () => PlanningTaskSelection.clear());
});

// Le succès d'un panneau de la barre de lot ne fait que lancer le rechargement de l'écran : la sélection se
// vide dès l'écriture faite, pour que les cases et la barre n'attendent pas la page neuve (retour 253). Un
// refus laisse le panneau ouvert et une annulation n'envoie rien, la sélection reste alors entière.
// Seul l'envoi du formulaire d'écriture compte, que `submitAjaxForm()` (form.js) retient par le formulaire ou
// par son bouton : un champ du panneau Modifier interroge aussi le serveur sans rien écrire (series/task.js).
document.addEventListener('ajaxAfterFetch', e => {

	const trigger = e.detail.trigger;
	const form = trigger?.matches('form') ? trigger : trigger?.closest('[type="submit"]')?.form;

	if(
		e.detail.failed === false &&
		form?.matches('[data-ajax-form="/series/task:doUpdate"], [data-ajax-form="/series/task:doUpdatePlannedCollection"], [data-ajax-form="/series/task:doIncrementPlannedCollection"], [data-ajax-form="/series/comment:doCreateCollection"]')
	) {
		qs('#planning-week, #planning-day', () => PlanningTaskSelection.clear());
	}

});

document.delegateEventListener('click', ':is(#planning-week, #planning-day) [data-planning-select-mode]', function(e) {

	e.preventDefault();

	if(PlanningTaskSelection.selecting) {
		PlanningTaskSelection.clear();
	} else {
		PlanningTaskSelection.enter();
	}

});

// Sur `window`, en capture : la navigation ajax du produit écoute déjà le clic en capture sur `document`
// (`delegateEventListener`), et le lien de la carte partirait avant.
window.addEventListener('click', e => PlanningTaskPress.click(e), true);
document.addEventListener('pointerdown', e => PlanningTaskPress.down(e));
document.addEventListener('pointermove', e => PlanningTaskPress.move(e));
document.addEventListener('pointerup', () => PlanningTaskPress.cancel());
document.addEventListener('pointercancel', () => PlanningTaskPress.cancel());
document.addEventListener('scroll', () => PlanningTaskPress.cancel(), true);
document.addEventListener('contextmenu', e => PlanningTaskPress.contextmenu(e));
// En capture, pour passer avant le glisser du Jour.
document.addEventListener('dragstart', e => PlanningTaskPress.drag(e), true);

document.delegateEventListener('click', '#planning-week .planning-week-piles [data-planning-pile]', function(e) {
	e.preventDefault();
	PlanningTaskColumns.select(this.dataset.planningPile);
});

/*
 * La colonne montrée au téléphone en Colonnes (GQ), où une seule tient : « À faire » à l'ouverture, puis celle
 * que la personne a choisie, reposée sur chaque écran rendu ensuite, la semaine suivante comme une réponse
 * qui rend l'écran entier. Le choix ne sert qu'à l'affichage et n'a rien à faire au serveur.
 */
class PlanningTaskColumns {

	static pile = null;

	static select(pile) {

		this.pile = pile;
		this.apply(qs('#planning-week .planning-week-piles'));

	}

	static restore(tabs) {

		if(this.pile !== null) {
			this.apply(tabs);
		}

	}

	static apply(tabs) {

		tabs.closest('#planning-week').dataset.pile = this.pile;
		tabs.qsa('[data-planning-pile]', tab => tab.classList.toggle('selected', tab.dataset.planningPile === this.pile));

	}

}

class PlanningTaskSelection {

	static selection = new Set();

	static selecting = false;

	static queued = false;

	static toggle(field) {

		if(field.checked) {
			this.selection.add(field.value);
		} else {
			this.selection.delete(field.value);
		}

		this.open(field);
		this.change();
		this.leaveEmpty();

	}

	static enter() {

		this.selecting = true;

		this.change();

	}

	/*
	 * La semaine en Colonnes montre ses cases sans « Sélectionner » (R-Retours-1210 V42) : la première case cochée y
	 * ouvre le mode. Ailleurs une case de carte ne se coche qu'en mode ouvert, et la Grille coche sans mode.
	 */
	static open(field) {

		if(this.selection.size > 0 && field.closest('#planning-week[data-view="columns"]') !== null) {
			this.selecting = true;
		}

	}

	/*
	 * Le geste qui décoche la dernière carte ferme le mode, et les cases repartent avec lui (13/10), sauf
	 * en Colonnes, où elles restent. Une carte qui quitte l'écran par la réponse d'un geste passe par `change()` et
	 * le laisse ouvert.
	 */
	static leaveEmpty() {

		if(this.selection.size === 0) {
			this.clear();
		}

	}

	static apply() {

		if(this.queued) {
			return;
		}

		this.queued = true;

		queueMicrotask(() => {
			this.queued = false;
			this.change();
		});

	}

	static getField(card) {

		return card.qs('.planning-select [name="batch[]"]') ?? card.closest('#planning-week, #planning-day').qs('.planning-task[data-task="'+ card.dataset.task +'"] .planning-select [name="batch[]"]');

	}

	static check(card, checked) {

		const field = this.getField(card);

		if(field === null) {
			return;
		}

		field.checked = checked ?? (field.checked === false);

		this.toggle(field);

	}

	/*
	 * La case de tête prend les cartes qu'elle vise dans son bloc (`getTargets()`), toutes cochées ou toutes
	 * décochées.
	 */
	static toggleGroup(field) {

		this.set(this.getTargets(this.getGroup(field)), field.checked, field);

	}

	/*
	 * La vignette d'une carte prend les cartes de la même espèce dans son bloc (R-Retours-1210 V44), comme la
	 * case de tête prend tout le bloc : toutes cochées, elles se décochent, sinon elles se cochent. Seules celles
	 * de l'état de la carte cliquée, à faire ou faites : elle en fait toujours partie, et la sélection ne mêle pas
	 * les deux (retour 56).
	 */
	static togglePlant(card) {

		const todo = this.getField(card).matches('[data-batch~="todo"]');
		const fields = this.getFields(this.getGroup(card), card.dataset.plant).filter(field => field.matches('[data-batch~="todo"]') === todo);

		this.set(fields, fields.some(field => field.checked === false), card);

	}

	static set(fields, checked, origin) {

		fields.forEach(field => {

			field.checked = checked;

			if(checked) {
				this.selection.add(field.value);
			} else {
				this.selection.delete(field.value);
			}

		});

		this.open(origin);
		this.change();
		this.leaveEmpty();

	}

	/*
	 * Une case arrive à l'écran : elle reprend son état de la sélection. Les cases d'une même réponse se
	 * rendent ensemble, et la barre ne se recalcule qu'une fois après elles.
	 */
	static restore(field) {

		field.checked = this.selection.has(field.value);

		this.apply();

	}

	/*
	 * L'état seul, sans toucher l'écran : posé avant que les cases ne se rendent, il les laisse arriver
	 * décochées, et la barre se règle sur elles (`restore()`).
	 */
	static reset() {

		this.selection.clear();
		this.selecting = false;

	}

	/*
	 * « Terminé », le × de la barre, la dernière carte décochée, la réponse d'une action de la barre et une
	 * navigation vident la sélection et ferment le mode. Le menu ouvert depuis la barre (Planifier, Affecter)
	 * se ferme aussi : sa liste vit sous `body` et survivrait à la barre qui se masque, prête à poster un lot
	 * sans intervention.
	 */
	static clear() {

		Lime.Dropdown.purge();

		this.selection.clear();
		this.selecting = false;

		qsa(':is(#planning-week, #planning-day) :is(.planning-task, .planning-grid-row[data-task]) [name="batch[]"]:checked', field => field.checked = false);

		this.change();

	}

	static getGroup(node) {
		return node.closest('.planning-week-block, .planning-day-action, .planning-grid-group');
	}

	/*
	 * Les cases des cartes à faire du bloc, dont une sélection mêlée de cartes faites retirerait « Fait ! » de
	 * la barre (retour 56), ou celles de toutes ses cartes quand aucune n'est plus à faire (retours 275 et 276).
	 * Une copie du Jour y compte par la case de sa carte d'origine (`getField()`), comme son clic. Une carte que
	 * la recherche masque n'est jamais prise (`PlanningTaskSearch`).
	 */
	static getTargets(group) {

		const fields = this.getFields(group);
		const todo = fields.filter(field => field.matches('[data-batch~="todo"]'));

		return (todo.length > 0) ? todo : fields;

	}

	static getFields(group, plant = null) {

		const cards = ':is(.planning-task, .planning-grid-row[data-task]):not(.planning-search-out)'+ (plant === null ? '' : '[data-plant="'+ plant +'"]');

		return Array.from(group.qsa(cards), card => this.getField(card)).filter(field => field !== null);

	}

	static change() {

		const fields = qsa(':is(#planning-week, #planning-day) :is(.planning-task, .planning-grid-row[data-task]) [name="batch[]"]');

		// Une intervention dont la case a quitté l'écran (supprimée, reportée hors de la semaine) sort de la
		// sélection.
		const present = new Set(Array.from(fields, field => field.value));
		this.selection.forEach(id => {
			if(present.has(id) === false) {
				this.selection.delete(id);
			}
		});

		this.updateGroups();
		this.paint();

		if(qs('#planning-batch') === null) {
			return;
		}

		// Sans menu « un » (R-Retours-0310, point 18) : la barre du bas dès la première carte cochée.
		Batch.changeSelection('#planning-batch', null, selection => this.updateMenu(selection));

		// `Batch` ne réécrit les identifiants et le compte que de la barre qu'il montre : masquée, elle garderait
		// ceux d'une sélection passée, et un geste qui poste son formulaire enverrait des cartes décochées.
		qsa('#planning-batch.hide :is(.batch-ids, .batch-group-count)', node => node.innerHTML = '');

	}

	/*
	 * L'état de chaque case de tête : cochée quand toutes les cartes qu'elle vise le sont, à demi cochée quand
	 * certaines le sont (`indeterminate` ne s'écrit pas en HTML), absente quand la recherche masque tout le
	 * bloc. Toutes les lectures d'abord, puis toutes les écritures.
	 */
	static updateGroups() {

		const states = [];

		qsa(':is(#planning-week, #planning-day) [data-planning-select-group]', field => {

			const targets = this.getTargets(this.getGroup(field));
			const checked = targets.filter(target => target.checked).length;

			states.push({field, total: targets.length, checked});

		});

		states.forEach(({field, total, checked}) => {

			field.closest('.planning-select').classList.toggle('planning-select-empty', total === 0);
			field.checked = (total > 0 && checked === total);
			field.indeterminate = (checked > 0 && checked < total);

		});

	}

	/*
	 * Le mode sur l'écran, et le fond « coché » sur toutes les cartes d'une intervention choisie, copies du
	 * Jour comprises, comme sur sa ligne de la grille. Toutes les lectures d'abord, puis toutes les écritures.
	 */
	static paint() {

		const screens = qsa('#planning-week, #planning-day');
		const cards = qsa(':is(#planning-week, #planning-day) .planning-task');
		const rows = qsa(':is(#planning-grid, #planning-grid-late) .planning-grid-row[data-task]');
		const selected = Array.from(cards, card => this.selection.has(card.dataset.task));
		const selectedRows = Array.from(rows, row => this.selection.has(row.dataset.task));

		screens.forEach(screen => screen.classList.toggle('planning-selecting', this.selecting));
		cards.forEach((card, index) => card.classList.toggle('planning-task-selected', selected[index]));
		rows.forEach((row, index) => row.classList.toggle('planning-grid-row-selected', selectedRows[index]));

	}

	/*
	 * « Récolter » ne s'offre qu'à des récoltes de la même espèce, variété et calibre, dans au plus une unité : une
	 * récolte qui n'a encore rien reçu n'a pas d'unité (`TaskBatchUi::getCheckbox()`, `TaskBatchLib::validateHarvests()`).
	 */
	static isHarvestable(selection) {

		const harvests = new Set();
		const units = new Set();

		selection.forEach(checkbox => {

			const tokens = checkbox.dataset.batch.split(' ');

			harvests.add(tokens.find(token => token.startsWith('harvest-') && token.startsWith('harvest-unit-') === false) ?? null);
			tokens.filter(token => token.startsWith('harvest-unit-')).forEach(token => units.add(token));

		});

		return (harvests.size === 1 && harvests.has(null) === false && units.size <= 1);

	}

	/*
	 * Les entrées de la barre, selon les interventions choisies, comme l'ancien planning les règle
	 * (`Task.changePlanningSelection()`). À une carte, la barre porte les gestes décidés pour une seule
	 * intervention (R-Retours-0310, point 18) : Modifier en plus, et pas de Fait !, que la carte porte déjà
	 * par sa marque. Temps s'y montre comme à plusieurs (retour 295), une carte ne portant pas toujours son
	 * champ de temps. Qui ne peut pas passer une intervention faite ne lui donne pas de temps
	 * (`TaskBatchLib::fillTimes()`) : sa barre le dit (`data-planning-batch-time-done`), et Temps s'y cache
	 * tant qu'aucune carte faite n'est cochée. Une récolte seule sans quantité dans la semaine n'a aucun jour
	 * où recevoir du temps (`not-time`, posé par `TaskBatchUi::getCheckbox()`) : Temps s'y cache aussi, plutôt
	 * que de s'ouvrir pour refuser à l'envoi. `lime.js` lit `data-confirm` au clic : la question y est
	 * recopiée au singulier ou au pluriel, les deux formes venant du serveur.
	 */
	static updateMenu(selection) {

		const one = (selection.length === 1);
		const done = selection.filter('[data-batch~="done"]').length;
		const todo = selection.filter('[data-batch~="todo"]').length;
		const timeless = (one && selection.filter('[data-batch~="not-time"]').length > 0);

		qsa('.planning-batch-time', node => node.classList.toggle('hide', timeless || (node.hasAttribute('data-planning-batch-time-done') && done === 0)));
		qsa('.planning-batch-harvest', node => node.classList.toggle('hide', this.isHarvestable(selection) === false));
		qsa('.planning-batch-done', node => node.classList.toggle('hide', one || done > 0));
		qsa('.planning-batch-todo', node => node.classList.toggle('hide', todo > 0));
		qsa('.planning-batch-planned, .planning-batch-users', node => node.classList.toggle('hide', done > 0));
		qsa('.planning-batch-postpone', node => node.classList.toggle('hide', selection.filter('[data-batch~="not-postpone"]').length > 0));

		qsa('.planning-batch-update', node => {
			node.classList.toggle('hide', one === false);
			node.setAttribute('href', '/series/task:update?id='+ selection[0].value);
		});

		qsa('#planning-batch [data-confirm-one]', node => {
			node.dataset.confirm = one ? node.dataset.confirmOne : node.dataset.confirmMany;
		});

		// Ouverte, la liste est sous `body` (`Lime.Dropdown.open()`) : elle se cherche par sa classe, et non sous `.planning-batch-users`.
		qsa('.planning-batch-users-list .batch-planned-user', node => {

			const all = (selection.filter('[data-batch~="user-'+ node.getAttribute('post-user') +'"]').length === selection.length);

			node.setAttribute('post-action', all ? 'delete' : 'add');
			node.classList.toggle('selected', all);

		});

	}

}

// Le navigateur émet `pointercancel` dès qu'il prend le doigt pour un défilement : le défilement gagne
// toujours sur l'appui long. L'appui long sur la tête d'un bloc vaut un clic sur sa case de tête (retour 278).
class PlanningTaskPress {

	static DELAY = 500;

	static press = null;

	// La carte ou la tête dont l'appui long vient d'aboutir : le clic qui le termine, s'il en vient un, est le sien.
	static swallow = null;

	// `contextmenu` ne dit pas quel pointeur l'a ouvert.
	static pointerType = 'mouse';

	static IGNORE = '.planning-select, .planning-task-validate, .planning-task-day-menu, .planning-task-time-field, .planning-duration';

	static getCard(target) {

		if((target instanceof Element) === false) {
			return null;
		}

		const card = target.closest(':is(#planning-week, #planning-day) .planning-task');

		if(card === null || target.closest(this.IGNORE) !== null || this.isFolded(card)) {
			return null;
		}

		return card;

	}

	// La vignette d'une carte qui a une espèce, là où les cases se voient : le mode ouvert, la semaine en Colonnes
	// qui les montre toujours (R-Retours-1210 V42), et la Grille, dont chaque ligne porte sa case. Ailleurs elle
	// reste une partie du lien de la carte.
	static getVignette(target) {

		if((target instanceof Element) === false) {
			return null;
		}

		const row = target.closest('#planning-week .planning-grid-row[data-plant]');

		if(row !== null) {
			return (target.closest('.planning-grid-vignette') !== null) ? row : null;
		}

		const card = this.getCard(target);

		if(
			card === null ||
			card.dataset.plant === undefined ||
			target.closest('.planning-task-vignette') === null ||
			(PlanningTaskSelection.selecting === false && card.closest('#planning-week[data-view="columns"]') === null)
		) {
			return null;
		}

		return card;

	}

	// Le « + » de la tête reste un lien, et le pense-bête n'a pas de case de tête.
	static getHead(target) {

		if((target instanceof Element) === false) {
			return null;
		}

		const head = target.closest(':is(#planning-week, #planning-day) .planning-block-head');

		if(
			head === null ||
			head.qs('[data-planning-select-group]') === null ||
			target.closest(this.IGNORE +', a') !== null ||
			this.isFolded(head)
		) {
			return null;
		}

		return head;

	}

	// Au téléphone, « Sans jour » replié s'ouvre d'abord au toucher.
	static isFolded(node) {
		return (node.closest('#planning-day .planning-day-column[data-column="none"]:not(.is-open)') !== null && window.matchMedia('(max-width: 575px)').matches);
	}

	static down(e) {

		this.pointerType = e.pointerType;
		this.swallow = null;

		// La grille n'a pas de mode « Sélectionner » : ses cases sont toujours là, et les cartes des zones sous
		// elle ne se cochent qu'en Cartes (DG-3). L'appui long y ouvrirait le mode et ferait paraître leurs cases.
		if(e.button !== 0 || e.target.closest('#planning-week[data-view="grid"]') !== null) {
			return;
		}

		// Le mode ouvert, une carte se coche déjà au clic (`click()`).
		const node = PlanningTaskSelection.selecting ? this.getHead(e.target) : (this.getCard(e.target) ?? this.getHead(e.target));

		if(node === null) {
			return;
		}

		this.cancel();

		this.press = {
			node,
			x: e.clientX,
			y: e.clientY,
			timer: setTimeout(() => this.fire(), this.DELAY)
		};

	}

	static move(e) {

		if(this.press === null) {
			return;
		}

		if(Math.abs(e.clientX - this.press.x) > PlanningPointer.THRESHOLD || Math.abs(e.clientY - this.press.y) > PlanningPointer.THRESHOLD) {
			this.cancel();
		}

	}

	static cancel() {

		if(this.press !== null) {
			clearTimeout(this.press.timer);
			this.press = null;
		}

	}

	static fire() {

		const node = this.press.node;

		this.press = null;
		this.swallow = node;

		PlanningTaskSelection.enter();

		if(node.matches('.planning-block-head')) {

			const field = node.qs('[data-planning-select-group]');

			field.checked = (field.checked === false);

			PlanningTaskSelection.toggleGroup(field);

		} else {
			PlanningTaskSelection.check(node, true);
		}

	}

	// Seul un clic de pointeur (`detail` à 1 au moins) peut terminer l'appui long : celui d'Entrée au clavier
	// (`detail` à 0) vaut un clic sur la carte, même quand le doigt s'est levé hors d'elle sans y laisser de clic.
	static click(e) {

		if(e.detail > 0) {

			const swallow = this.swallow;

			this.swallow = null;

			if(swallow !== null && (e.target instanceof Node) && swallow.contains(e.target)) {
				e.preventDefault();
				e.stopPropagation();
				return;
			}

		}

		const vignette = this.getVignette(e.target);

		if(vignette !== null) {

			e.preventDefault();
			e.stopPropagation();

			PlanningTaskSelection.togglePlant(vignette);

			return;

		}

		if(PlanningTaskSelection.selecting === false) {
			return;
		}

		const card = this.getCard(e.target);

		if(card === null) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		PlanningTaskSelection.check(card);

	}

	static contextmenu(e) {

		if(this.pointerType !== 'mouse' && (this.getCard(e.target) ?? this.getHead(e.target)) !== null) {
			e.preventDefault();
		}

	}

	// Le glisser du Jour ne vaut qu'à la souris, hors du mode.
	static drag(e) {

		this.cancel();

		if(
			(e.target instanceof Element) &&
			e.target.closest('#planning-day .planning-task') !== null &&
			(PlanningTaskSelection.selecting || this.pointerType !== 'mouse')
		) {
			e.preventDefault();
			e.stopPropagation();
		}

	}

}

/*
 * Le glisser d'une carte des blocs de la semaine (retour 176) : à droite, elle part à la semaine suivante,
 * à gauche, elle est supprimée après la confirmation du socle (`data-confirm`). Les dessous viennent du
 * gabarit `#planning-week-swipe` (`TaskWeekUi::getSwipe()`), absent sans le droit d'écrire. Le dessous se pose
 * à la fin du bloc, aux mesures de la carte, et la carte reste où elle est : posé à côté d'elle, il couperait
 * le filet de la carte suivante, et la carte déplacée dans la page perdrait la capture du pointeur. De côté, la
 * carte est coupée à sa place d'origine, si bien qu'elle découvre le dessous sans déborder du bloc.
 *
 * Une récolte de série (`data-cultivation`) ouvre aussi la saisie de l'écran des récoltes (retour 203), quand
 * le gabarit porte « Récolter » : au doigt, à droite, avant la semaine suivante, qui ne vient qu'au-delà des
 * deux tiers ; à la souris, tirée vers le haut, par-dessus les cartes qui la précèdent, les gestes de côté
 * restant ceux de 176.
 *
 * Le départ suit `PlanningPointer` : au-delà du seuil, un geste plus large que haut est le glisser, l'autre
 * reste au défilement (`touch-action`), sauf la récolte tirée vers le haut à la souris, et l'appui long tombe
 * au même seuil (`PlanningTaskPress.move()`). Au doigt, le départ attend en outre `DELAY` (retour 259). Une
 * carte que le serveur refuserait de reporter (`not-postpone`, `Task::acceptPostpone()`) ne glisse que vers la
 * gauche, ou vers la récolte.
 */
class PlanningTaskSwipe {

	// La part de la carte au-delà de laquelle le lâcher vaut le geste : sa largeur de côté, sa hauteur vers le haut,
	// où le libellé du dessous doit en outre être découvert en entier (`reach`). Vers le haut, ce seuil est aussi la
	// butée de la carte (retour 215).
	static RATIO = 0.4;

	// Au doigt, la part de la largeur au-delà de laquelle une récolte qui se reporte découvre la semaine suivante.
	static POSTPONE_RATIO = 2 / 3;

	// Au doigt, le temps en millisecondes avant lequel le geste ne choisit pas son axe (retour 259). Notre seuil
	// tombe avant celui où le navigateur prend un défilement : sans attente, un pouce qui défile en partant de
	// biais ferait partir la carte de côté, jusqu'au `pointercancel` qui la ramène. Pendant l'attente, ce
	// `pointercancel` vide le départ sans que rien ait bougé. L'axe se décide ensuite sur tout le déplacement
	// depuis l'appui, et la carte rattrape le doigt.
	static DELAY = 100;

	static pending = null;
	static active = null;

	// La carte qu'un geste au-delà du seuil vient de lâcher : le clic que le pointeur émet en la lâchant ne l'ouvre pas.
	static swallow = null;

	static down(e) {

		this.swallow = null;

		if(
			e.button !== 0 ||
			this.active !== null ||
			PlanningTaskSelection.selecting ||
			(e.target instanceof Element) === false ||
			e.target.closest(PlanningTaskPress.IGNORE) !== null
		) {
			return;
		}

		const card = e.target.closest('#planning-week-board .planning-task');
		const template = qs('#planning-week-swipe');

		// Une carte qui revient ou s'en va d'un geste fini attend la fin de son retour : un second geste
		// partirait de sa place glissée et poserait un dessous que le premier ne retirerait pas.
		if(card === null || template === null || card.classList.contains('planning-task-swiping')) {
			return;
		}

		this.pending = {
			card,
			pointer: e.pointerId,
			x: e.clientX,
			y: e.clientY,
			at: e.timeStamp,
			mouse: (e.pointerType === 'mouse'),
			harvest: (card.dataset.cultivation !== undefined && template.content.qs('.planning-task-swipe-harvest') !== null)
		};

	}

	// L'axe que prend un départ de `dx`, `dy` après `elapsed` millisecondes : `null` tant qu'il reste sous le seuil
	// ou, au doigt, sous `DELAY`, `none` quand il revient au défilement.
	static getAxis(swipe, dx, dy, elapsed) {

		if(swipe.mouse === false && elapsed < this.DELAY) {
			return null;
		}

		if(Math.abs(dy) > PlanningPointer.THRESHOLD && Math.abs(dy) >= Math.abs(dx)) {
			return (swipe.mouse && swipe.harvest && dy < 0) ? 'y' : 'none';
		} else if(Math.abs(dx) > PlanningPointer.THRESHOLD) {
			return 'x';
		} else {
			return null;
		}

	}

	// Le dessous qu'un déplacement de `offset` sur l'axe du geste découvre, `null` quand la carte ne va pas de ce côté.
	static getSide(swipe, offset) {

		if(swipe.axis === 'y') {
			return (offset < 0) ? 'harvest' : null;
		}

		if(offset < 0) {
			return 'delete';
		} else if(offset === 0) {
			return null;
		}

		if(swipe.harvest && swipe.mouse === false) {
			return (swipe.postpone && offset >= swipe.width * this.POSTPONE_RATIO) ? 'next' : 'harvest';
		}

		return swipe.postpone ? 'next' : null;

	}

	// Le geste que vaut le lâcher, au-delà de `RATIO` de la carte sur son axe, `null` en deçà. Vers le haut, une
	// carte d'une ligne levée de 40 % cache encore « Récolter » : le lâcher attend aussi que le libellé soit
	// découvert en entier (seuil vertical, arbitrage V-Planning du 2026-10-09).
	static getGesture(swipe, offset) {

		const side = this.getSide(swipe, offset);

		return (side !== null && Math.abs(offset) >= this.getThreshold(swipe)) ? side : null;

	}

	static getThreshold(swipe) {
		return (swipe.axis === 'y') ?
			Math.max(swipe.height * this.RATIO, swipe.reach) :
			swipe.width * this.RATIO;
	}

	// Tirée vers le haut, la carte s'arrête où « Récolter » se lit en entier, quoi que fasse la souris : au-delà,
	// elle couvrirait les cartes du dessus sans rien découvrir de plus (retour 215). De côté, rien ne la retient.
	static bound(swipe, offset) {
		return (swipe.axis === 'y') ? Math.max(offset, -this.getThreshold(swipe)) : offset;
	}

	// Ce que la carte doit découvrir du dessous pour que son libellé se lise : du haut du libellé au bas du
	// dessous, rembourrage compris.
	static getReach(under) {

		const range = document.createRange();
		range.selectNodeContents(under);

		return under.getBoundingClientRect().bottom - range.getBoundingClientRect().top;

	}

	static move(e) {

		if(this.pending !== null && this.pending.pointer === e.pointerId) {

			// L'appui long est parti en sélection sous le même doigt : ce qu'il glisse ensuite n'est plus un geste.
			if(PlanningTaskSelection.selecting) {
				this.pending = null;
				return;
			}

			const axis = this.getAxis(this.pending, e.clientX - this.pending.x, e.clientY - this.pending.y, e.timeStamp - this.pending.at);

			if(axis === 'none') {
				this.pending = null;
			} else if(axis !== null) {
				this.start(e, axis);
			}

			return;

		}

		if(this.active !== null && this.active.pointer === e.pointerId) {
			this.follow(this.getOffset(this.active, e));
		}

	}

	static getOffset(swipe, e) {
		return (swipe.axis === 'y') ? e.clientY - swipe.y : e.clientX - swipe.x;
	}

	static start(e, axis) {

		const {card, pointer, x, y, mouse, harvest} = this.pending;

		this.pending = null;

		this.active = {
			card,
			pointer,
			x,
			y,
			axis,
			mouse,
			harvest,
			offset: 0,
			under: null,
			reach: 0,
			postpone: (card.qs('[data-batch~="not-postpone"]') === null),
			...this.measure(card)
		};

		card.classList.add('planning-task-swiping');

		window.getSelection().removeAllRanges();

		this.follow(this.getOffset(this.active, e));

		// La capture garde le geste à la carte quand le doigt sort de sa colonne. Un pointeur que le navigateur
		// ne suit pas, simulé ou déjà levé, ne se capture pas ; le geste suit alors par les écouteurs du
		// document, qui reçoivent les mêmes événements.
		try {
			card.setPointerCapture(pointer);
		} catch(error) {
			if(error.name !== 'NotFoundError') {
				throw error;
			}
		}

	}

	// Le dessous se pose exactement sous la carte, et une carte peut tomber sur un demi-pixel : les `offset*`
	// arrondis à l'entier laisseraient un filet du dessous dépasser au bord de la carte.
	static measure(card) {

		const box = card.getBoundingClientRect();
		const frame = card.parentElement.getBoundingClientRect();

		return {
			top: box.top - frame.top - card.parentElement.clientTop,
			left: box.left - frame.left - card.parentElement.clientLeft,
			width: box.width,
			height: box.height
		};

	}

	// Un déplacement vers un côté que la carte n'a pas la laisse à sa place.
	static follow(offset) {

		const swipe = this.active;
		const side = this.getSide(swipe, offset);

		if(side === null) {
			offset = 0;
		}

		if(swipe.under !== null && swipe.under.dataset.side !== side) {
			swipe.under.remove();
			swipe.under = null;
		}

		if(swipe.under === null && side !== null) {

			const under = qs('#planning-week-swipe').content.qs('.planning-task-swipe-'+ side).cloneNode(true);

			under.dataset.side = side;
			under.classList.toggle('planning-task-swipe-up', swipe.axis === 'y');
			under.style.top = swipe.top +'px';
			under.style.left = swipe.left +'px';
			under.style.width = swipe.width +'px';
			under.style.height = swipe.height +'px';

			swipe.card.parentElement.append(under);
			swipe.under = under;

			// Le libellé se mesure à la pose du dessous, juste après cette seule écriture et avant celle de la
			// carte : la butée en a besoin dès le premier déplacement, et un dessous posé ne change plus de taille.
			if(swipe.axis === 'y') {
				swipe.reach = this.getReach(under);
			}

		}

		offset = this.bound(swipe, offset);

		swipe.offset = offset;

		this.place(swipe, offset);

	}

	// Tirée vers le haut, la carte n'est pas coupée : elle passe par-dessus celles qui la précèdent.
	static place(swipe, offset) {

		if(swipe.axis === 'y') {
			swipe.card.style.transform = 'translateY('+ offset +'px)';
		} else {
			swipe.card.style.transform = 'translateX('+ offset +'px)';
			swipe.card.style.clipPath = 'inset(0 '+ offset +'px 0 '+ (-offset) +'px)';
		}

	}

	static end(e) {

		if(this.pending !== null && this.pending.pointer === e.pointerId) {
			this.pending = null;
		}

		if(this.active === null || this.active.pointer !== e.pointerId) {
			return;
		}

		const swipe = this.active;
		const gesture = this.getGesture(swipe, swipe.offset);

		this.active = null;

		// Seul un geste qui agit retient le clic qui le suit. Un doigt qui dérive de quelques pixels en touchant
		// la carte part en geste, revient, et le navigateur en tire tout de même un clic : c'est un toucher.
		if(gesture !== null) {
			this.swallow = swipe.card;
		}

		switch(gesture) {

			case 'harvest' :
				this.release(swipe, 0).then(() => {
					this.clean(swipe);
					this.harvest(swipe.card);
				});
				break;

			case 'next' :
				this.release(swipe, swipe.width)
					.then(() => this.send(swipe, 'doBatchIncrement', {increment: swipe.under.dataset.increment}));
				break;

			case 'delete' :
				this.remove(swipe);
				break;

			default :
				this.release(swipe, 0).then(() => this.clean(swipe));
				break;

		}

	}

	static cancel() {

		this.pending = null;

		if(this.active !== null) {
			const swipe = this.active;
			this.active = null;
			this.release(swipe, 0).then(() => this.clean(swipe));
		}

	}

	// Le dessous reçoit le clic que `data-confirm` attend : un refus l'annule, et la carte revient.
	static remove(swipe) {

		const confirmed = swipe.under.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));

		if(confirmed) {
			this.release(swipe, -swipe.width)
				.then(() => this.send(swipe, 'doBatchDelete', {}));
		} else {
			this.release(swipe, 0).then(() => this.clean(swipe));
		}

	}

	// La carte glisse jusqu'à `offset` (`planning-task-swiping-release`), puis la promesse se tient. Si la carte
	// est déjà à `offset`, aucune transition ne part et la promesse se tient tout de suite. Une page cachée ne fait
	// pas avancer ses transitions : la durée de la transition tient alors la promesse, pour qu'aucun dessous
	// ne survive au geste et que la carte se laisse de nouveau glisser.
	static release(swipe, offset) {

		swipe.card.classList.add('planning-task-swiping-release');

		this.place(swipe, offset);
		swipe.offset = offset;

		const animations = swipe.card.getAnimations().filter(animation => animation.transitionProperty === 'transform');

		return Promise.all(animations.map(animation => Promise.race([
			animation.finished.catch(() => null),
			new Promise(resolve => setTimeout(resolve, animation.effect.getComputedTiming().endTime))
		])));

	}

	static send(swipe, action, body) {

		const task = swipe.card.dataset.task;

		PlanningTask.enqueue(() => new Ajax.Query(swipe.card)
			.url('/planning/taskWeek:'+ action)
			.body({...PlanningTask.getBody(task), kept: PlanningTask.getKept(), ...body, ids: [task]})
			.fetch()
			.finally(() => this.clean(swipe)));

	}

	// La saisie de l'écran des récoltes, sur la culture de la carte : la feuille au tactile, comme
	// `PlanningHarvest.open()`, le panneau « Nouvelle récolte » sinon. Elle réémet l'écran derrière elle
	// (`PlanningTask.getBody()`), pour que `taskWeek:doHarvest` réponde par ce que l'on regarde.
	static harvest(card) {

		const query = new URLSearchParams({
			...PlanningTask.getBody(card.dataset.task),
			cultivation: card.dataset.cultivation,
			origin: 'semaine'
		});

		if(isTouch()) {

			query.set('sheet', 1);

			new Ajax.Query(card)
				.method('get')
				.url('/planning/harvestWeek:open?'+ query.toString())
				.fetch();

		} else {

			new Ajax.Navigation(card)
				.url('/planning/harvestWeek:create?'+ query.toString())
				.fetch();

		}

	}

	static clean(swipe) {

		swipe.under?.remove();

		swipe.card.classList.remove('planning-task-swiping', 'planning-task-swiping-release');
		swipe.card.style.removeProperty('transform');
		swipe.card.style.removeProperty('clip-path');

	}

	// Le clic que `remove()` donne au dessous n'est pas celui de la carte, qui arrive après lui. Seul un clic de
	// pointeur (`detail` à 1 au moins) peut suivre le geste : celui d'Entrée au clavier (`detail` à 0) passe, même
	// quand le doigt du geste s'est levé hors de la carte sans y laisser de clic.
	static click(e) {

		if(this.swallow !== null && e.detail > 0 && (e.target instanceof Node) && this.swallow.contains(e.target)) {
			this.swallow = null;
			e.preventDefault();
			e.stopPropagation();
		}

	}

	// Le lien de la carte, tiré à la souris, partirait en glisser du navigateur.
	static drag(e) {

		if((e.target instanceof Element) && e.target.closest('#planning-week-board .planning-task') !== null && qs('#planning-week-swipe') !== null) {
			e.preventDefault();
		}

	}

}

document.addEventListener('pointerdown', e => PlanningTaskSwipe.down(e));
document.addEventListener('pointermove', e => PlanningTaskSwipe.move(e));
document.addEventListener('pointerup', e => PlanningTaskSwipe.end(e));
document.addEventListener('pointercancel', () => PlanningTaskSwipe.cancel());
window.addEventListener('click', e => PlanningTaskSwipe.click(e), true);
document.addEventListener('dragstart', e => PlanningTaskSwipe.drag(e), true);

/*
 * L'Année de l'écran « Interventions » (R-Annee) : une colonne par semaine, qui défilent de côté comme les
 * jours du Jour (`TaskYearUi`). La page ne rend le contenu que des semaines autour de celle qu'elle ouvre ;
 * les autres se chargent quand elles approchent du cadre. Les gestes de la carte sont cocher et décocher,
 * qui répondent par les semaines qu'ils changent (`taskYear.v.php`), par la même file d'envoi que la
 * Semaine et le Jour (`PlanningTask.enqueue()`).
 */
document.delegateEventListener('click', '#planning-year [data-planning-task-done]', function(e) {

	e.preventDefault();

	const card = this.closest('.planning-task');
	PlanningTaskYear.checked.add(card.dataset.task);

	PlanningTaskYear.send(card, 'doDone');

});

document.delegateEventListener('click', '#planning-year [data-planning-task-todo]', function(e) {

	e.preventDefault();

	PlanningTaskYear.send(this.closest('.planning-task'), 'doTodo');

});

// Un mois mène le cadre à sa première semaine, sans la navigation du lien.
document.delegateEventListener('click', '#planning-year [data-planning-year-month]', function(e) {

	e.preventDefault();

	PlanningTaskYear.scrollToMonth(this.dataset.planningYearMonth);

});

window.addEventListener('resize', () => qs('#planning-year .planning-day-scroll', zone => PlanningTaskYear.resize(zone)));

class PlanningTaskYear {

	/*
	 * Deux observateurs sur le cadre, et aucune lecture de géométrie au défilement : `loader` voit les semaines
	 * qui entrent à une largeur de cadre de chaque côté, `spy` celles qui coupent une bande étroite près du bord
	 * gauche du cadre, d'où se lit le mois en gras. Ils naissent avec le cadre (`start()`) et chaque colonne
	 * s'y inscrit à son rendu (`watch()`), celle qu'une réponse vient de remplacer comprise.
	 *
	 * ⚠️ Pas de `threshold: 0.5` pour `spy` : le ratio se calcule sur toute la surface de la colonne, et sous
	 * 576 px une semaine chargée est bien plus haute que l'écran, la page défilant en hauteur. Une colonne
	 * entièrement en vue n'atteignait jamais la moitié, et le mois restait celui de l'ouverture.
	 */
	static loader = null;
	static spy = null;

	// Les semaines à charger au prochain envoi, et celles déjà demandées, qu'un second passage ne redemande pas.
	static pending = new Set();
	static requested = new Set();

	// Les colonnes qui coupent la bande de `spy` : des nœuds, pas des semaines, une réponse remplaçant le nœud d'une semaine.
	static visible = new Set();

	/*
	 * Les interventions cochées pendant la visite, que chaque geste et chaque chargement réémettent (`shown`) :
	 * sous « Masquer ce qui est fait », la colonne qu'un geste redessine ou qu'un chargement apporte les garde,
	 * comme la Semaine garde les cartes cochées jusqu'au rechargement (`TaskYearLib::getColumns()`), et la
	 * marque d'une de ces cartes trouve sa jumelle dans chaque semaine où elle paraît.
	 */
	static checked = new Set();

	static LOAD_MAX = 8;

	// Le jeton du dernier chargement parti (`load()`), et l'attente de la fin d'un redimensionnement (`resize()`).
	static loads = 0;
	static resizing = null;

	/*
	 * Le défilement de chaque semaine, par semaine, que `watch()` rend à la colonne qu'une réponse remplace :
	 * remplacée, une colonne revient en haut, et la carte qu'on vient de cocher en bas d'une semaine de
	 * printemps sortirait de la vue. Relevé au défilement, seul moment où il change, et
	 * non au départ d'un geste : « Nouvelle intervention » part de son panneau, sans passer par `send()`.
	 */
	static scrolls = new Map();

	static start(zone) {

		this.stop();

		this.size(zone);

		this.arm(zone);

		this.spy = new IntersectionObserver(entries => this.see(entries), {
			root: zone,
			rootMargin: '0px -80% 0px -10%'
		});

		// Le défilement d'un élément ne remonte pas : le cadre le voit en capture.
		zone.addEventListener('scroll', e => this.remember(e.target), {capture: true, passive: true});

		PlanningTaskDay.place(zone, qs('#planning-year-'+ qs('#planning-year').dataset.week));

	}

	/*
	 * Le chargeur voit à une largeur de cadre de chaque côté : il naît avec le cadre, et renaît à la fin d'un
	 * redimensionnement (`resize()`), qui change cette largeur. Seules les colonnes encore à charger s'y
	 * inscrivent, et une semaine déjà demandée n'est pas redemandée (`flush()`).
	 */
	static arm(zone) {

		this.loader?.disconnect();

		this.loader = new IntersectionObserver(entries => this.approach(entries), {
			root: zone,
			rootMargin: '0px '+ zone.clientWidth +'px'
		});

		zone.qsa('.planning-day-column:not([data-loaded])', column => this.loader.observe(column));

	}

	/*
	 * Les colonnes se repartagent à chaque événement (`size()`, qui mesure tout avant d'écrire), le chargeur
	 * ne renaît qu'une fois le redimensionnement fini : un observateur recréé à chaque événement relancerait
	 * pour rien le premier passage de toutes les colonnes non chargées.
	 */
	static resize(zone) {

		this.size(zone);

		clearTimeout(this.resizing);

		this.resizing = setTimeout(() => {

			if(zone.isConnected && this.loader !== null) {
				this.arm(zone);
			}

		}, 250);

	}

	/*
	 * La recherche change les semaines à charger : le chargeur renaît pour revoir toutes les colonnes encore à
	 * charger, une colonne écartée restant sinon sans nouvelle tant qu'elle ne quitte pas le cadre.
	 */
	static rearm(zone) {

		if(this.loader !== null) {
			this.arm(zone);
		}

	}

	static stop() {

		clearTimeout(this.resizing);

		this.loader?.disconnect();
		this.spy?.disconnect();

		this.loader = null;
		this.spy = null;

		this.pending.clear();
		this.requested.clear();
		this.visible.clear();
		this.checked.clear();
		this.scrolls.clear();

	}

	static watch(column) {

		if(this.spy === null) {
			return;
		}

		this.spy.observe(column);

		if(column.dataset.loaded === undefined) {
			this.loader.observe(column);
		}

		const top = this.scrolls.get(column.dataset.column);

		if(top !== undefined) {
			column.scrollTop = top;
		}

	}

	static remember(target) {

		if(target instanceof Element && target.matches('.planning-day-column')) {
			this.scrolls.set(target.dataset.column, target.scrollTop);
		}

	}

	/*
	 * Les semaines se partagent la largeur du cadre en un nombre entier de colonnes d'au moins 20rem, comme les
	 * jours du Jour (`PlanningTaskDay.share()`), sans rail : aucune n'est entamée à droite. Une semaine chargée
	 * après coup garde la largeur de sa tête, et ne déplace donc pas celle qu'on regarde. Tout est lu avant
	 * d'écrire.
	 */
	static size(zone) {

		const columns = zone.qs('.planning-day-columns');

		const gap = parseFloat(window.getComputedStyle(columns).columnGap) || 0;
		const rem = parseFloat(window.getComputedStyle(document.documentElement).fontSize);
		const free = zone.clientWidth;
		const desktop = window.matchMedia('(min-width: 576px)').matches;

		if(desktop) {
			PlanningTaskDay.share(zone, Math.min(20 * rem, free), free, gap);
		}

	}

	static scrollToMonth(month) {

		qs('#planning-year .planning-day-scroll', zone => PlanningTaskDay.place(zone, zone.qs('.planning-day-column[data-month="'+ month +'"]')));

	}

	static approach(entries) {

		entries.forEach(entry => {

			const column = entry.target;

			// Sous un jeton, une semaine sans résultat ne se charge pas : elle resterait vide (`PlanningTaskSearch.wants()`).
			if(entry.isIntersecting && column.isConnected && column.dataset.loaded === undefined && PlanningTaskSearch.wants(column.dataset.column)) {
				this.pending.add(column.dataset.column);
				this.loader.unobserve(column);
			}

		});

		// Les semaines entrées ensemble partent ensemble.
		setTimeout(() => this.flush(), 0);

	}

	static flush() {

		const weeks = Array.from(this.pending).filter(week => this.requested.has(week) === false).sort();

		this.pending.clear();

		for(let start = 0; start < weeks.length; start += this.LOAD_MAX) {
			this.load(weeks.slice(start, start + this.LOAD_MAX));
		}

	}

	/*
	 * Un GET sans contexte : il ne verrouille pas l'écran, où une coche peut partir pendant qu'une semaine se
	 * charge.
	 *
	 * ⚠️ Le serveur lit les semaines au départ, et la réponse peut revenir après un geste qui les a changées :
	 * appliquée, elle remettrait l'état d'avant le geste. Chaque chargement pose donc son jeton sur ses
	 * colonnes (`data-load`), la réponse ne vise que celles qui le portent encore, et un geste le retire des
	 * semaines qu'il change (vues `weeks` et `doTask`). Une semaine qu'une réponse périmée laisse non chargée se
	 * redemande à son prochain passage devant le chargeur.
	 */
	static load(weeks) {

		const year = qs('#planning-year');
		const load = String(++this.loads);

		weeks.forEach(week => {

			this.requested.add(week);

			qs('#planning-year-'+ week, column => column.dataset.load = load);

		});

		const parameters = new URLSearchParams({
			id: year.dataset.structure,
			week: year.dataset.week,
			load: load
		});

		weeks.forEach(week => parameters.append('weeks[]', week));
		this.checked.forEach(task => parameters.append('shown[]', task));

		// Seule une colonne sans jeton (un geste le lui a retiré) ou portant encore le sien se libère : une réponse
		// qui revient après une navigation trouve les colonnes de la page nouvelle sous le jeton de leur propre
		// chargement, et les libérer relancerait ce chargement en boucle.
		//
		// Une réponse périmée est un succès, et sa colonne retourne aussitôt sous le chargeur. Une requête en
		// échec (500, 414, serveur absent) libère sa colonne sans la réobserver : une colonne visible relancerait
		// la même requête à l'image suivante, sans fin. Elle se redemande au prochain `arm()` (fin d'un
		// redimensionnement) ou à la prochaine navigation.
		const settle = success => weeks.forEach(week => qs('#planning-year-'+ week, column => {

			if(
				column.dataset.loaded === undefined &&
				(column.dataset.load === undefined || column.dataset.load === load)
			) {

				this.requested.delete(week);

				if(success) {
					this.loader?.observe(column);
				}

			}

		}));

		new Ajax.Query()
			.method('get')
			.url('/planning/taskYear:weeks?'+ parameters.toString())
			.fetch()
			.then(() => settle(true), () => settle(false));

	}

	static see(entries) {

		entries.forEach(entry => {

			if(entry.isIntersecting && entry.target.isConnected) {
				this.visible.add(entry.target);
			} else {
				this.visible.delete(entry.target);
			}

			if(entry.target.isConnected === false) {
				this.spy.unobserve(entry.target);
			}

		});

		const first = Array.from(this.visible)
			.filter(column => column.isConnected)
			.sort((column1, column2) => column1.dataset.column.localeCompare(column2.dataset.column))[0];

		if(first === undefined) {
			return;
		}

		qsa('#planning-year [data-planning-year-month]', link => link.classList.toggle('is-selected', link.dataset.planningYearMonth === first.dataset.month));

	}

	static send(card, action) {

		return PlanningTask.enqueue(() => {

			// Un second clic mis en file pendant le premier vise une carte que la réponse a déjà remplacée.
			if(card.isConnected === false) {
				return;
			}

			const year = qs('#planning-year');

			return new Ajax.Query(card)
				.url('/planning/taskYear:'+ action)
				.body({
					id: year.dataset.structure,
					week: card.dataset.column,
					current: year.dataset.week,
							task: card.dataset.task,
					shown: Array.from(this.checked)
				})
				.fetch();

		});

	}

}

/*
 * La recherche des Interventions (R-Recherche) : l'omnibox de la rangée (`TabUi::getSearch()`) masque, à la
 * Semaine, au Jour et à l'Année, les cartes que ses jetons écartent. Chaque carte dit ce qu'on peut y chercher
 * dans `data-search` (`TaskSearchLib::getValues()`). Un jeton par groupe, et les groupes se croisent. Le Jour
 * porte toutes ses cartes : rien n'est demandé au serveur. La Semaine porte les siennes, sauf dans ses zones,
 * « Non planifié » avant sa première ouverture et les blocs plafonnés : ce que les jetons y retiennent vient du
 * serveur. L'Année ne charge que les semaines qui approchent du cadre : ses suggestions et ce que les jetons
 * retiennent dans les semaines qu'elle n'a pas chargées viennent du serveur (R-Recherche-2, `search()`).
 *
 * Les jetons vivent dans l'adresse, sous `filtre`, comme ceux du plan de culture (`Series.writePlanFilter()`) :
 * ils sont reportés sur les liens de la période et du calendrier, et l'écran qui revient, par
 * un de ces liens, un rechargement ou la réponse d'un geste qui le rend entier, les reprend
 * (`start()`, par l'`onrender` de l'enveloppe).
 *
 * Les réponses des gestes remplacent ou retirent des cartes sans prévenir : un `MutationObserver` sur l'écran
 * repasse le filtre après chacune. Les écritures du filtre lui-même sont retirées de sa file
 * (`takeRecords()`), sans quoi il se relancerait sur ses propres piles.
 */
document.delegateEventListener('omniboxsuggest', '#planning-search', function() {
	UtilOmnibox.setSuggestions(this, PlanningTaskSearch.suggest(this));
});

document.delegateEventListener('omnibox', '#planning-search', function() {
	PlanningTaskSearch.write(this);
	PlanningTaskSearch.apply(true);
});

class PlanningTaskSearch {

	static observer = null;

	static queued = false;

	// Le passage vient d'un jeton posé ou retiré, et non de la réponse d'un geste (`reveal()`).
	static revealing = false;

	// Les blocs masqués au dernier passage : les piles ne se recomposent que s'ils changent.
	static folded = '';

	/*
	 * L'Année : ce que le serveur retient dans toute l'année pour une clé de recherche (`getKey()`), la clé de la
	 * demande en route, les semaines que le chargeur prend encore (`wants()`), et la recherche du cadre qui
	 * attend la réponse d'un jeton posé (`seek()`).
	 */
	static matches = null;
	static requested = null;
	static weeks = null;
	static seeking = false;

	// Les changements de l'écran, que la réponse de la Semaine retient : une réponse plus ancienne se redemande.
	static version = 0;

	// Le rail « Sans jour » du Jour, sous 576 px, quand c'est la recherche qui l'a ouvert : elle le referme seule.
	static railOpened = false;

	static start(wrapper) {

		const screen = wrapper.closest('#planning-week, #planning-day, #planning-year');
		const box = wrapper.qs('#planning-search');

		// Au doigt, l'omnibox passe en plein écran sous `body` (`UtilOmnibox.openFullscreen()`), hors de son
		// enveloppe et de son écran : ce que la recherche lit sur l'enveloppe voyage avec la boîte, et la boîte
		// comme l'écran se cherchent dans le document.
		box.dataset.groups = wrapper.dataset.groups;
		box.dataset.vignettes = wrapper.dataset.vignettes;
		box.dataset.countOne = wrapper.dataset.countOne;
		box.dataset.countMany = wrapper.dataset.countMany;

		if(this.observer !== null) {
			this.observer.disconnect();
		}

		// La liste de l'omnibox se redessine à chaque frappe : seules les mutations de l'écran comptent.
		this.observer = new MutationObserver(records => {
			if(records.some(record => record.target.closest('.planning-search') === null)) {
				this.version++;
				this.apply();
			}
		});
		// `data-search` aussi : la réponse d'une affectation le repose seul sur la ligne (`taskWeek:doUser`).
		this.observer.observe(screen, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-search']});

		this.folded = '';
		this.matches = null;
		this.requested = null;
		this.weeks = null;
		this.seeking = false;
		this.railOpened = false;

		this.read(box);
		this.apply();

	}

	static getGroups(box) {
		return JSON.parse(box.dataset.groups);
	}

	static getValues(card) {
		return JSON.parse(card.qs('[data-search]').dataset.search);
	}

	// Les lignes de la grille (CW) portent ce que lit la recherche comme les cartes.
	static getCards(screen) {
		return Array.from(screen.qsa('.planning-task, .planning-grid-row')).filter(card => card.qs('[data-search]') !== null);
	}

	/*
	 * Les suggestions, relevées sur les cartes de l'écran à chaque ouverture de la liste : la zone « Non
	 * planifié » charge les siennes à sa première ouverture, et un geste peut en ajouter.
	 */
	static suggest(box) {

		const groups = this.getGroups(box);
		const values = {};

		Object.keys(groups).forEach(group => values[group] = new Set());

		// Les avatars des membres sont rendus sur l'enveloppe et recopiés sur la boîte par `start()`, la vignette d'une espèce de sa carte (`TaskCardUi::getSearchPlant()`).
		const vignettes = {...JSON.parse(box.dataset.vignettes), plant: {}};

		this.getCards(qs('#planning-week, #planning-day')).forEach(card => {

			const search = this.getValues(card);
			const plant = card.qs('[data-search]').dataset.searchPlant;

			Object.keys(groups).forEach(group => (search[group] ?? []).forEach(value => values[group].add(value)));

			if(plant !== undefined) {
				search.plant.forEach(value => vignettes.plant[value] = JSON.parse(plant));
			}

		});

		const suggestions = [];

		// Les nombres dans l'ordre des nombres, « n8 » avant « n10 », comme les suggestions de l'Année
		// (`TaskSearchLib::getSuggestions()`).
		Object.keys(groups).forEach(group => {
			[...values[group]].sort((a, b) => a.localeCompare(b, 'fr', {numeric: true})).forEach(value => {
				suggestions.push({group: groups[group], label: value, ...(vignettes[group]?.[value] ?? {})});
			});
		});

		return suggestions;

	}

	// `{groupe: [valeurs normalisées]}`, à partir des jetons, rangés par libellé de groupe.
	static getCriteria(box) {

		const groups = this.getGroups(box);
		const criteria = {};

		Object.entries(UtilOmnibox.getTokenGroups(box)).forEach(([label, values]) => {

			const group = Object.keys(groups).find(key => groups[key] === label);

			if(group !== undefined) {
				criteria[group] = values.map(value => UtilOmnibox.normalize(value));
			}

		});

		return criteria;

	}

	static match(card, criteria) {

		const search = this.getValues(card);

		return Object.keys(criteria).every(group => (search[group] ?? []).some(value => criteria[group].includes(UtilOmnibox.normalize(value))));

	}

	static apply(revealing = false) {

		this.revealing = (this.revealing || revealing);

		if(this.queued) {
			return;
		}

		this.queued = true;

		queueMicrotask(() => {
			this.queued = false;
			this.filter();
		});

	}

	/*
	 * Toutes les lectures, puis toutes les écritures. Un bloc (Semaine, zones) ou un groupe d'action (Jour) se
	 * masque quand il ne garde aucune carte, sauf un bloc de zone dont le serveur retient des cartes au-delà de
	 * son plafond. Une zone de la Semaine se masque quand le serveur n'y retient rien, chargée ou non, et son
	 * compte dit ce qu'il y retient (contre-recette de R-Recherche, 2026-10-03).
	 *
	 * À l'Année, une semaine sans résultat reste en place, vide (arbitrage B2 de V-Planning) : les mois et le
	 * défilement par semaine lisent la géométrie des colonnes. Une semaine chargée se juge sur ses cartes, une
	 * semaine qui ne l'est pas sur la réponse du serveur, et le décompte unit les deux : une réponse de geste
	 * redessine les semaines qu'il change en les chargeant, donc ce que le serveur a dit d'elles ne compte plus.
	 */
	static filter() {

		const screen = qs('#planning-week, #planning-day, #planning-year');
		const box = (screen === null) ? null : qs('#planning-search');

		if(box === null) {
			return;
		}

		const criteria = this.getCriteria(box);
		const filtering = (Object.keys(criteria).length > 0);

		const cards = this.getCards(screen);
		const shown = new Map(cards.map(card => [card, (filtering === false) || this.match(card, criteria)]));

		const year = (screen.id === 'planning-year');
		const zones = Array.from(screen.qsa('#planning-week-before > .util-fold'));

		// Ce que l'écran ne porte pas se demande au serveur : à l'Année, les semaines qu'elle n'a pas chargées ; à la
		// Semaine, les cartes des zones au-delà du plafond de leurs blocs, et « Non planifié » avant sa première
		// ouverture. À la Semaine, la réponse d'avant un changement de l'écran reste lue jusqu'à la suivante
		// (`follow()`) : un geste dans une zone ne la fait pas clignoter.
		const remote = (year || zones.length > 0);
		const key = (remote && filtering) ? this.getKey(screen, criteria) : null;
		const matches = (key !== null && this.matches?.key === key) ? this.matches : null;
		const zoneMatches = (year === false && matches !== null) ? matches.zones : null;

		const groups = Array.from(screen.qsa('.planning-week-block, .planning-grid-group, .planning-day-action'));
		const groupsShown = groups.map(group => {

			// Le pense-bête de la grille n'a pas de carte (`getCards()`) : sans jeton il reste, même vide, avec son
			// « + », et sous un jeton il n'a rien qui réponde.
			if(group.classList.contains('planning-grid-reminders')) {
				return (filtering === false);
			}

			if(Array.from(group.qsa('.planning-task, .planning-grid-row')).some(card => shown.get(card) === true)) {
				return true;
			}

			// Un bloc de zone dont les résultats sont au-delà de son plafond reste, et « Voir les N autres » s'ouvre.
			const block = this.getZoneBlock(group);

			return (block !== null && this.getZoneIds(zoneMatches, block.zone, block.action).length > 0);

		});

		// Sous un jeton, une zone suit la réponse du serveur, qui la voit entière ; en l'attendant, elle reste comme
		// elle était.
		const zonesShown = zones.map(zone => {

			if(filtering === false) {
				return true;
			} else if(zoneMatches === null) {
				return (zone.classList.contains('planning-search-out') === false);
			} else {
				return (this.getZoneIds(zoneMatches, this.getZoneName(zone)).length > 0);
			}

		});

		const tasks = new Set(cards.filter(card => shown.get(card)).map(card => card.dataset.task));

		if(zoneMatches !== null) {
			zones.forEach(zone => this.getZoneIds(zoneMatches, this.getZoneName(zone)).forEach(id => tasks.add(String(id))));
		}

		const columns = year ? Array.from(screen.qsa('.planning-day-column')) : [];
		const columnsShown = columns.map(column => {

			if(column.dataset.loaded !== undefined) {
				return Array.from(column.qsa('.planning-task')).some(card => shown.get(card) === true);
			} else {
				return (matches !== null && matches.weeks[column.dataset.column] !== undefined);
			}

		});

		if(year && matches !== null) {

			Object.entries(matches.weeks).forEach(([week, ids]) => {
				if(screen.qs('#planning-year-'+ week +'[data-loaded]') === null) {
					ids.forEach(id => tasks.add(String(id)));
				}
			});

		}

		// Rien de neuf ne se dit avant la réponse du serveur : les décomptes et l'écran vide gardent ce qu'ils
		// disaient, pour qu'un geste aussi courant qu'une coche ne fasse pas clignoter l'écran.
		const known = (remote === false || filtering === false || matches !== null);
		const empty = remote ?
			(known ? (filtering && tasks.size === 0) : screen.classList.contains('planning-search-none')) :
			(filtering && cards.length > 0 && tasks.size === 0);

		// Sous un jeton, le compte d'une zone ou d'un bloc de zone dit ce que le serveur y retient, et garde
		// son nombre en attendant la réponse. Celui d'une colonne du Jour ou de l'Année, ou d'un
		// bloc du tableau de la Semaine, dit les cartes retenues à faire qu'elle montre, comme le décompte de la
		// recherche : sous « Masquer ce qui est fait », une intervention commencée n'a pas de carte, et ne se compte
		// donc pas sous un jeton, alors que le nombre rendu par la page la compte. Sans jeton, chacun retrouve le
		// nombre que la page a rendu, gardé sur le nœud.
		const todo = node => Array.from(node.qsa('.planning-task:not(.planning-task-done), .planning-grid-row:not(.planning-grid-row-done)')).filter(card => shown.get(card) === true).length;

		// En Colonnes, « Fait » n'a que des cartes faites : ses blocs et son onglet comptent les cartes retenues
		// qu'ils montrent, comme « À faire ».
		const all = node => Array.from(node.qsa('.planning-task')).filter(card => shown.get(card) === true).length;

		const counters = [
			...zones.map(zone => [screen.qs('#'+ zone.id +'-counter'), () => this.getZoneIds(zoneMatches, this.getZoneName(zone)).length, true]),
			...Array.from(screen.qsa('#planning-week-todo, #planning-week-done')).map(column => [screen.qs('#'+ column.id +'-counter'), () => all(column), false]),
			...groups
				.filter(group => group.classList.contains('planning-week-block') || group.classList.contains('planning-grid-group'))
				.map(group => {

					const block = this.getZoneBlock(group);

					if(block === null) {
						return [group.qs('h4 > .util-counter'), () => (group.closest('#planning-week-done') === null ? todo(group) : all(group)), false];
					} else {
						return [group.qs('h4 > .util-counter'), () => this.getZoneIds(zoneMatches, block.zone, block.action).length, true];
					}

				}),
			...Array.from(screen.qsa('.planning-day-column')).map(column => [column.qs('.planning-day-title .util-counter'), () => todo(column), false])
		]
			.filter(([counter]) => counter !== null)
			.map(([counter, count, server]) => {

				const initial = counter.dataset.count ?? counter.textContent;

				if(filtering === false) {
					return [counter, initial, initial];
				} else if(server && matches === null) {
					return [counter, initial, null];
				} else {
					return [counter, initial, String(count())];
				}

			});

		// Un compte ne dit jamais plus de cartes que l'écran n'en montre : « Voir les N autres » s'ouvre de lui-même
		// quand il cache un résultat.
		const more = [];

		if(year === false && filtering && matches !== null) {

			const expected = groups.map(group => [group, this.getZoneBlock(group)]).filter(([, block]) => block !== null).map(([group, block]) => [group, this.getZoneIds(zoneMatches, block.zone, block.action)]);

			expected.forEach(([node, ids]) => {

				const link = node.qs(':is(.planning-week-more > a, a.util-table-add)[data-ajax]');

				if(link === null || link.dataset.searchMore !== undefined) {
					return;
				}

				const present = new Set(Array.from(node.qsa('.planning-task, .planning-grid-row')).map(card => card.dataset.task));

				if(ids.some(id => present.has(String(id)) === false)) {
					more.push(link);
				}

			});

		}

		// Les semaines que le chargeur prend encore : sans jeton toutes, sous un jeton celles qui portent un
		// résultat, et en attendant la réponse, celles d'avant.
		const weeks = (year === false || filtering === false) ? null : ((matches === null) ? this.weeks : new Set(Object.keys(matches.weeks)));

		const rail = (screen.id === 'planning-day') ? screen.qs('.planning-day-column[data-column="none"]') : null;
		const railAlone = (
			rail !== null &&
			cards.some(card => shown.get(card) && rail.contains(card)) &&
			cards.some(card => shown.get(card) && rail.contains(card) === false) === false
		);

		const unchecked = cards
			.filter(card => shown.get(card) === false)
			.map(card => card.qs('.planning-select [name="batch[]"]:checked'))
			.filter(field => field !== null);

		// Une liste ouverte depuis une carte que la recherche retire flotterait sur une ligne absente (Affecter à...).
		const dropdowns = cards
			.filter(card => shown.get(card) === false)
			.flatMap(card => Array.from(card.qsa('[data-dropdown-display]')));

		const folded = groups.filter((group, index) => groupsShown[index] === false).map(group => group.id).join(',');

		// Le défilement ne bouge pas sous un jeton posé : l'écran garde au moins la hauteur qui atteint le bas de
		// la fenêtre, sans quoi le navigateur remonterait la page raccourcie. Sans jeton, il retrouve la sienne.
		const floor = filtering ? Math.max(0, Math.ceil(window.innerHeight - screen.getBoundingClientRect().top)) : null;

		cards.forEach(card => card.classList.toggle('planning-search-out', shown.get(card) === false));
		groups.forEach((group, index) => group.classList.toggle('planning-search-out', groupsShown[index] === false));
		zones.forEach((zone, index) => zone.classList.toggle('planning-search-out', zonesShown[index] === false));

		screen.classList.toggle('planning-search-none', empty);
		screen.style.minHeight = (floor === null) ? '' : floor +'px';

		qs('.planning-search-empty', node => node.classList.toggle('hide', empty === false));

		counters.forEach(([counter, initial, value]) => {

			if(value !== null) {
				counter.dataset.count = initial;
				counter.textContent = value;
			}

			// À l'Année, la tête d'une semaine ne rend pas de compteur quand rien ne reste à faire (`TaskYearUi::getTitle()`).
			if(year && counter.closest('.planning-day-title') !== null) {
				counter.classList.toggle('hide', value === '0');
			}

		});

		// Sans carte restante, la phrase de l'écran vide le dit déjà : le décompte se tait.
		if(known) {
			this.count(box, (filtering && empty === false) ? tasks.size : null);
		}
		this.link(box);

		if(remote) {
			this.follow(screen, key, matches, criteria, weeks);
		}

		more.forEach(link => {
			link.dataset.searchMore = '';
			link.click();
		});

		dropdowns.forEach(button => Lime.Dropdown.close(button));

		if(unchecked.length > 0) {

			unchecked.forEach(field => {
				field.checked = false;
				PlanningTaskSelection.selection.delete(field.value);
			});

			PlanningTaskSelection.change();

		} else {
			// La case de tête vise les cartes que la recherche laisse (`getTargets()`), et `change()` la relit dans
			// l'autre branche : elle suit chaque passage, même sans carte cochée masquée.
			PlanningTaskSelection.updateGroups();
		}

		if(folded !== this.folded) {

			this.folded = folded;

			qs('#planning-week-board.util-piles', board => MainPiles.stack(board, true));
			screen.qsa('#planning-week-before .util-piles', board => MainPiles.stack(board, true));

		}

		if(this.revealing) {

			this.revealing = false;

			// À l'Année, la semaine qui porte un résultat ne se cherche qu'à la réponse du serveur (`seek()`).
			this.seeking = (year && filtering);

			if(rail !== null) {
				this.unfoldRail(rail, filtering, railAlone);
			}

			if(empty) {
				qs('.planning-search-empty', node => this.reveal(screen, [node]));
			} else if(filtering) {

				const results = cards.filter(card => shown.get(card));

				if(screen.id === 'planning-day') {
					this.align(screen, results);
				}

				this.reveal(screen, results);

			}

		}

		if(this.seeking && matches !== null) {
			this.seeking = false;
			this.seek(screen, columns, columnsShown);
		}

		this.observer?.takeRecords();

	}

	/*
	 * La clé d'une recherche de l'Année : les jetons, et ce que l'écran réémet au serveur. Une intervention
	 * cochée pendant la visite reste à l'écran sous le masque (`PlanningTaskYear.checked`) : elle en fait partie.
	 */
	static getKey(screen, criteria) {

		if(screen.id === 'planning-year') {
			return JSON.stringify([criteria, screen.dataset.week, Array.from(PlanningTaskYear.checked).sort()]);
		} else {
			return JSON.stringify([criteria, screen.dataset.week]);
		}

	}

	/*
	 * Une demande en route se reconnaît à sa clé, et à la Semaine à l'état de l'écran qu'elle a vu : l'Année
	 * charge ses semaines sans cesse au défilement, et sa réponse ne dépend pas d'elles.
	 */
	static getRequest(screen, key) {
		return (screen.id === 'planning-year') ? key : key +'#'+ this.version;
	}

	static getZoneName(zone) {
		return zone.id.replace('planning-week-', '');
	}

	// La zone et l'action d'un bloc de zone de la Semaine (`TaskWeekUi::getZoneBlock()`), `null` pour un autre bloc.
	static getZoneBlock(group) {

		const parts = group.id.match(/^planning-week-(late|unplanned)-block-([0-9]+)$/);

		return (parts === null) ? null : {zone: parts[1], action: parts[2]};

	}

	// Ce que le serveur retient dans une zone, ou dans un de ses blocs.
	static getZoneIds(zoneMatches, zone, action = null) {

		if(zoneMatches === null) {
			return [];
		}

		const blocks = zoneMatches[zone] ?? {};

		return (action === null) ? Object.values(blocks).flat() : (blocks[action] ?? []);

	}

	/*
	 * Demande au serveur ce que la clé retient, si ce n'est déjà fait, ou à la Semaine si l'écran a changé depuis
	 * la réponse. À l'Année, réarme le chargeur quand les semaines à charger changent
	 * (`PlanningTaskYear.approach()`, qui écarte les autres).
	 */
	static follow(screen, key, matches, criteria, weeks) {

		const stale = (matches === null) || (screen.id === 'planning-week' && matches.version !== this.version);

		if(key !== null && stale && this.requested !== this.getRequest(screen, key)) {
			this.search(screen, key, criteria);
		}

		const before = (this.weeks === null) ? null : Array.from(this.weeks).join(',');
		const after = (weeks === null) ? null : Array.from(weeks).join(',');

		this.weeks = weeks;

		if(before !== after) {
			qs('#planning-year .planning-day-scroll', zone => PlanningTaskYear.rearm(zone));
		}

	}

	static wants(week) {
		return (this.weeks === null) || this.weeks.has(week);
	}

	/*
	 * Un GET sans contexte, comme le chargement des semaines : il ne verrouille pas l'écran. Seule la réponse
	 * de la dernière demande compte, une frappe plus récente ayant pu en changer la clé.
	 */
	static search(screen, key, criteria) {

		const year = (screen.id === 'planning-year');
		const request = this.getRequest(screen, key);
		const version = this.version;

		this.requested = request;

		const parameters = new URLSearchParams({
			id: screen.dataset.structure,
			week: screen.dataset.week
		});

		if(year) {
			PlanningTaskYear.checked.forEach(task => parameters.append('shown[]', task));
		}

		Object.entries(criteria).forEach(([group, values]) => values.forEach(value => parameters.append('criteria['+ group +'][]', value)));

		new Ajax.Query()
			.method('get')
			.url((year ? '/planning/taskYear:search?' : '/planning/taskWeek:search?')+ parameters.toString())
			.fetch()
			.then(json => {

				if(this.requested !== request) {
					return;
				}

				this.requested = null;

				if(screen.isConnected === false) {
					return;
				}

				this.matches = year ?
					{key: key, version: version, weeks: json.weeks} :
					{key: key, version: version, zones: json.zones};

				this.apply();

			}, () => {

				if(this.requested === request) {
					this.requested = null;
				}

			});

	}

	/*
	 * L'Année, après un jeton posé (arbitrage B3 de V-Planning, sous l'exception de `reveal()`) : quand aucune
	 * semaine en vue ne porte de résultat, le cadre va à la première qui en porte à partir de celle du bord
	 * gauche, ou à la dernière avant elle. Une recherche n'agit sur aucune carte : elle sert à voir ce qu'on
	 * cherche.
	 */
	static seek(screen, columns, columnsShown) {

		const zone = screen.qs('.planning-day-scroll');
		const results = columns.filter((column, index) => columnsShown[index]);

		if(results.length === 0 || zone === null) {
			return;
		}

		const frame = zone.getBoundingClientRect();
		const visible = columns.filter(column => {
			const rect = column.getBoundingClientRect();
			return (rect.right > frame.left + 1 && rect.left < frame.right - 1);
		});

		if(visible.length === 0 || visible.some(column => results.includes(column))) {
			return;
		}

		const from = visible[0].dataset.column;
		const target = results.find(column => column.dataset.column >= from) ?? results[results.length - 1];

		PlanningTaskDay.place(zone, target);

	}

	/*
	 * Au Jour sous 576 px, « Sans jour » est un rail replié le long des jours : quand un jeton posé n'y laisse
	 * que des cartes, le rail s'ouvre, sans rien faire défiler, et se referme au retrait du dernier jeton s'il
	 * a été ouvert ainsi.
	 */
	static unfoldRail(rail, filtering, alone) {

		if(window.matchMedia('(max-width: 575px)').matches === false) {
			return;
		}

		if(filtering) {

			if(alone && rail.classList.contains('is-open') === false) {
				PlanningTaskDay.openNone(rail, true);
				this.railOpened = true;
			}

		} else if(this.railOpened) {

			this.railOpened = false;

			if(rail.classList.contains('is-open')) {
				PlanningTaskDay.openNone(rail, false);
			}

		}

	}

	/*
	 * Le Jour, à toutes les largeurs, sous la même exception que `reveal()` (n°7 de R-Dettes-Ecran, élargi par
	 * V-Planning le 2026-10-03) : quand aucune carte retenue n'est à l'écran dans le cadre des jours, la colonne
	 * de la première vient sur le point d'accroche. Sous 576, une carte du rail « Sans jour » qu'on voit suffit
	 * à ne rien bouger, et la colonne visée est un jour, le rail collant au bord sans défiler ; replié, il ne
	 * montre aucune carte et ne compte pas. À partir de 576, « Sans jour » défile avec les jours (R-Retours-1210
	 * V60) : ses cartes se jugent comme les autres, et elle peut être la colonne visée.
	 * Chaque colonne défile aussi seule en hauteur (R-Retours-0410, point 16) : celle dont la première carte
	 * retenue est passée au-dessus de sa vue remonte jusqu'à elle (`getBodyTops()`).
	 * Toutes les mesures, puis les écritures, avant les mesures de `reveal()`.
	 */
	static align(screen, cards) {

		const zone = screen.qs('.planning-day-scroll');

		if(zone === null || cards.length === 0) {
			return;
		}

		const tops = this.getBodyTops(cards);

		const rail = zone.qs('.planning-day-column[data-column="none"]');
		const railSticky = (rail !== null && getComputedStyle(rail).position === 'sticky');

		const frame = zone.getBoundingClientRect();
		const from = frame.left + zone.clientLeft + (parseFloat(zone.style.scrollPaddingLeft) || 0);
		const to = frame.left + zone.clientLeft + zone.clientWidth;

		const seen = cards.some(card => {

			if(card.getClientRects().length === 0) {
				return false;
			}

			const rect = card.getBoundingClientRect();

			if(railSticky && card.closest('.planning-day-column[data-column="none"]') !== null) {
				return true;
			}

			return (rect.right > from + 1 && rect.left < to - 1);

		});

		if(seen === false) {

			const column = cards
				.map(card => card.closest('.planning-day-column'))
				.find(column => column !== null && (railSticky === false || column !== rail)) ?? null;

			// `place()` mesure la colonne avant d'écrire : il passe avant les corps, dont le défilement vertical
			// ne change rien à la position de côté qu'il lit.
			PlanningTaskDay.place(zone, column);

		}

		tops.forEach(([body, top]) => body.scrollTop = top);

	}

	/*
	 * Le défilement à donner au corps de chaque colonne dont la première carte retenue est passée au-dessus de
	 * sa vue, comme `reveal()` le fait pour l'écran : le haut de son bloc d'action, où se lit le nom de l'action.
	 * Un corps qui ne défile pas (sous 576 de large ou 600 de haut) a toujours sa première carte en vue. Ne fait que mesurer.
	 */
	static getBodyTops(cards) {

		const firsts = new Map();

		cards.forEach(card => {

			const body = card.closest('.planning-day-body');

			if(body !== null && firsts.has(body) === false && card.getClientRects().length > 0) {
				firsts.set(body, card);
			}

		});

		const tops = [];

		firsts.forEach((card, body) => {

			const top = body.getBoundingClientRect().top + body.clientTop;

			if(card.getBoundingClientRect().top >= top) {
				return;
			}

			const block = card.closest('.planning-day-action') ?? card;

			tops.push([body, body.scrollTop + block.getBoundingClientRect().top - top]);

		});

		return tops;

	}

	/*
	 * Exception à « un geste ne déplace jamais le défilement » (V-Planning, 2026-10-03, point 3 de la passe ui) :
	 * la convention protège la carte sur laquelle on agit, et une recherche n'agit sur aucune carte, elle sert à
	 * voir ce qu'on cherche. Dès que le haut de la première carte restante, où se lit son titre, passe sous la
	 * rangée collante, l'écran remonte en haut du tableau qui la porte. Quand aucune carte ne reste, il remonte
	 * jusqu'à la phrase de l'écran vide, que l'ancrage du défilement du navigateur peut aussi avoir fait passer
	 * sous la rangée. Sinon rien ne bouge, et après un geste jamais.
	 * Toutes les mesures, prises après les écritures du filtre qui déplacent les cartes, puis une seule écriture.
	 */
	static reveal(screen, nodes) {

		// Sans rangée collante (sous 576 px), l'omnibox défile avec la page : on ne peut y poser un jeton qu'en la
		// voyant, donc au-dessus de toute carte, et l'écran ne bouge pas.
		const tools = screen.qs(':scope > .planning-tools');

		if(tools === null || getComputedStyle(tools).position !== 'sticky') {
			return;
		}

		const ceiling = tools.getBoundingClientRect().bottom;

		// Une carte sans boîte (dans un parent replié) n'est pas à l'écran, ni au-dessus ni ailleurs.
		const visible = nodes.filter(node => node.getClientRects().length > 0);
		const rects = visible.map(card => card.getBoundingClientRect());
		const first = rects.reduce((best, rect, index) => (best === null || rect.top < rects[best].top) ? index : best, null);

		if(first === null || rects[first].top >= ceiling) {
			return;
		}

		const table = visible[first].closest('#planning-week-before, #planning-week-board, .planning-day-scroll') ?? visible[first];
		const top = Math.max(0, window.scrollY + table.getBoundingClientRect().top - ceiling);
		const screenTop = screen.getBoundingClientRect().top + window.scrollY;

		// La hauteur plancher de `filter()` tenait la position quittée : elle se recalcule pour la nouvelle, sans
		// quoi la page garderait du blanc sous les cartes.
		screen.style.minHeight = Math.max(0, Math.ceil(window.innerHeight - (screenTop - top))) +'px';
		window.scrollTo({top});

	}

	static count(box, value) {

		box.qs('.util-omnibox-count').textContent = (value === null) ?
			'' :
			((value <= 1) ? box.dataset.countOne : box.dataset.countMany).replace('{value}', value);

	}

	static getFilter(box) {

		const tokens = UtilOmnibox.getTokens(box).map(token => [token.group, token.value, token.label]);

		return (tokens.length === 0) ? null : JSON.stringify(tokens);

	}

	/*
	 * Pas `String.setArgument()` / `removeArgument()` de `lime.js`, que prend `Series.writePlanFilter()` : quand
	 * le dernier jeton part, l'expression de `removeArgument()` s'arrête à la première parenthèse, apostrophe
	 * ou soulignement, que `encodeURIComponent()` laisse en clair et qu'un nom d'intervention porte, et laisse
	 * la fin de la valeur dans l'adresse.
	 */
	static setFilter(href, value) {

		const url = new URL(href, location.origin);

		if(value === null) {
			url.searchParams.delete('filtre');
		} else {
			url.searchParams.set('filtre', value);
		}

		// Le contexte du framework est une adresse complète, les liens du produit des chemins : chacun garde sa forme.
		return /^https?:/.test(href) ? url.href : url.pathname + url.search + url.hash;

	}

	/*
	 * L'adresse et le contexte du framework, que le rechargement relit (`Series.readPlanFilter()` dit pourquoi
	 * les deux).
	 */
	static write(box) {

		const value = this.getFilter(box);
		const href = this.setFilter(location.href, value);

		if(document.body.dataset.context) {
			document.body.dataset.context = this.setFilter(document.body.dataset.context, value);
		}

		Lime.History.replaceState(href);

	}

	static read(box) {

		const raw = new URL(location.href).searchParams.get('filtre')
			?? new URL(document.body.dataset.context ?? location.href, location.origin).searchParams.get('filtre');

		if(raw === null || UtilOmnibox.getTokens(box).length > 0) {
			return;
		}

		let tokens;

		try {
			tokens = JSON.parse(raw);
		} catch(e) {
			return;
		}

		const groups = Object.values(this.getGroups(box));

		tokens
			.filter(token => Array.isArray(token) && groups.includes(token[0]))
			.forEach(([group, value, label]) => UtilOmnibox.addToken(box, group, value, label));

		box.classList.toggle('is-filled', box.qs('.util-omnibox-token') !== null);

	}

	/*
	 * Les liens qui mènent d'un écran des Interventions à l'autre emportent les jetons : la période et le
	 * calendrier des semaines, dont le sélecteur reçoit l'adresse de ses semaines en
	 * `post-link-weeks`. Les autres liens les abandonnent.
	 */
	static link(box) {

		const value = this.getFilter(box);

		qsa('.planning-period a[href], .planning-period-list a[href], #tasks-calendar-top a[href]', link => {

			const href = this.setFilter(link.getAttribute('href'), value);

			if(link.getAttribute('href') !== href) {
				link.setAttribute('href', href);
			}

		});

		qsa('#tasks-calendar-top [post-link-weeks]', node => {

			const href = this.setFilter(node.getAttribute('post-link-weeks'), value);

			if(node.getAttribute('post-link-weeks') !== href) {
				node.setAttribute('post-link-weeks', href);
			}

		});

	}

}

/*
 * Le jour d'un rappel du pense-bête, en pastilles (`ReminderUi::getDays()`) : un clic choisit le jour, et
 * « Non défini », dont le jour est vide, veut dire « dans la semaine, sans jour ». Un clic sur le jour choisi
 * le retire aussi, et rend la main à « Non défini ».
 * La valeur part dans le champ caché `date` du formulaire : celui du panneau « Nouveau rappel », ou celui de
 * la boîte du texte sur place. Dans les deux cas, la pastille ne fait que choisir : le jour part avec le
 * reste du formulaire, sur place par ✓, Entrée ou la sortie de la boîte (`MainQuickInline.blur()`).
 * Sur place, les pastilles s'ouvrent avec la boîte (`QuickInlineAiUi::linked()`) et vivent hors de son
 * formulaire.
 */
document.delegateEventListener('click', '[data-planning-reminder-day]', function(e) {

	e.preventDefault();

	PlanningReminderDay.select(this);

});

document.addEventListener('quickInlineReset', e => PlanningReminderDay.reset(e.target));

class PlanningReminderDay {

	static select(day) {

		const days = day.closest('.planning-reminder-days');
		const linked = days.closest('[data-quick-inline-for]');
		const form = (linked === null) ?
			days.closest('form') :
			document.getElementById(linked.dataset.quickInlineFor).qs('.util-quick-inline-field');

		// Sur place, pendant un envoi, le jour ne change pas : à la réponse, la boîte prend ses champs pour
		// enregistrés (`MainQuickInline.sent()`), et un jour choisi entre-temps passerait pour reçu par la base
		if(linked !== null && Ajax.Query.elementsLocked[form.getAttribute('data-lock')] !== undefined) {
			return;
		}

		const date = day.classList.contains('is-selected') ? '' : day.dataset.planningReminderDay;

		this.mark(days, date);
		form.qs('input[name="date"]').value = date;

	}

	// Échap a remis le jour de la boîte sur celui de la base : les pastilles le suivent
	static reset(box) {

		box.qs('input[name="date"]', input => {
			document.qsa('[data-quick-inline-for="'+ box.id +'"] .planning-reminder-days', days => this.mark(days, input.value));
		});

	}

	static mark(days, date) {

		days.qsa('[data-planning-reminder-day]', node => {

			const selected = (node.dataset.planningReminderDay === date);

			node.classList.toggle('is-selected', selected);
			node.qs('.util-calendar', calendar => calendar.classList.toggle('util-calendar-selected', selected));

		});

	}

}
