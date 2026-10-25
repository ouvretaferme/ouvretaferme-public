document.delegateEventListener('change', '#series-create-tasks select', function(e) {

	const wrapper = qs('#series-create-tasks');

	new Ajax.Query()
		.url('/series/series:getTasksFromSequence')
		.body({
			structure: wrapper.dataset.structure,
			season: wrapper.dataset.season,
			sequence: wrapper.dataset.sequence,
			startYear: wrapper.qs('[name="startYear"]').value,
			startWeek: wrapper.qs('[name="startWeek"]').value
		})
		.fetch();

});


document.delegateEventListener('change', '[data-action="series-cycle-change"] input', function(e) {

	const lifetime = qs('#series-write-perennial-lifetime');

	if(this.value === 'perennial') {
		lifetime.style.display = '';
		lifetime.qs('input').value = '';
	} else {
		lifetime.style.display = 'none';
	}

});

document.delegateEventListener('autocompleteSelect', '#series-create-add-plant', function(e) {

	if(e.detail.value === '') {
		return;
	}

	new Ajax.Query(this)
		.url('/series/series:addPlant')
		.body({
			structure: qs('#series-create-plant input[name="structure"]').value,
			use: qs('#series-create-plant input[name="use"]:checked').value,
			season: qs('#series-create-plant input[name="season"]').value,
			cycle: qs('#series-create-plant').getAttribute('data-cycle'),
			index: qs('#series-create-plant input[name="index"]').value,
			plant: e.detail.value,
		})
		.fetch()
		.then(() => {

			Series.updateName();

		});

});

document.delegateEventListener('change', '#series-create-plant input[name="use"], #series-create-sequence input[name="use"]', function(e) {

	const form = this.firstParent('form');

	const fieldRows = form.qsa('[class~="crop-write-rows"]');
	const fieldRowSpacing = form.qsa('[class~="crop-write-row-spacing"]');

	const fieldAreaTarget = form.qsa('[data-wrapper="areaTarget"]');
	const fieldLengthTarget = form.qsa('[data-wrapper="lengthTarget"]');
	const fieldBedTarget = form.qsa('[data-wrapper="bedWidth"], [data-wrapper="alleyWidth"]');

	if(this.value === 'bed') {

		fieldRows.forEach(node => node.classList.remove('hide'));
		fieldRowSpacing.forEach(node => {
			node.qs('input').value = '';
			node.classList.add('hide');
		});

		fieldLengthTarget.forEach(node => node.classList.remove('hide'));
		fieldBedTarget.forEach(node => node.classList.remove('hide'));
		fieldAreaTarget.forEach(node => {
			node.qs('input').value = '';
			node.classList.add('hide');
		});

	} else {

		fieldRows.forEach(node => {
			node.qs('input').value = '';
			node.classList.add('hide');
		});
		fieldRowSpacing.forEach(node => node.classList.remove('hide'));

		fieldLengthTarget.forEach(node => {
			node.qs('input').value = '';
			node.classList.add('hide');
		});
		fieldBedTarget.forEach(node => node.classList.add('hide'));
		fieldAreaTarget.forEach(node => node.classList.remove('hide'));

	}

});

// Au doigt, l'appui long ouvre le menu contextuel du navigateur au moment
// précis où le geste s'arme : tant qu'un élément est tenu, on le refuse.
document.addEventListener('contextmenu', function(e) {
	if(qs('#series-wrapper .series-item-timeline-holding') !== null) {
		e.preventDefault();
	}
});

// Les suggestions du plan ne viennent pas du serveur : le composant les demande à la
// page au premier besoin, et elle les relève sur les lignes qu'elle affiche déjà.
document.delegateEventListener('omniboxsuggest', '#series-filter', function() {
	UtilOmnibox.setSuggestions(this, Series.getPlanSuggestions());
});

document.delegateEventListener('omnibox', '#series-filter', function() {
	Series.filterPlan(this);
});

// Le conteneur et non la cellule : le repère de la semaine en cours recouvre la
// graduation, et sous 1200 px la plupart des cellules sont en visibility: hidden, donc
// hors d'atteinte du pointeur. Leur géométrie, elle, reste juste.
document.delegateEventListener('click', '.series-season-filterable .series-season-weeks', function(e) {

	const cell = Series.weekAt(this, e.clientX);

	cell === null || Series.openWeek(cell, e.shiftKey);

});

document.delegateEventListener('click', '.series-season-filterable .series-season-month[data-month]', function() {
	Series.openMonth(this);
});

document.delegateEventListener('change', '.series-week-option input', function() {
	Series.applyWeekMenu(this);
});

document.delegateEventListener('pointerover', '#series-wrapper .series-item-planning', function(e) {
	const track = this.qs('.series-item-planning-timeline');
	if(track && track.qs('.series-item-timeline-marks') === null) {
		Series.renderMarks(track);
	}
});

document.delegateEventListener('pointerdown', '#series-wrapper .series-item-timeline-event-shiftable', function(e) {
	if(Series.isPrimaryPointer(e) === false) {
		return;
	}
	e.preventDefault();
	Series.startShift(this, e);
});

document.delegateEventListener('pointerdown', '#series-wrapper .series-item-timeline-bound', function(e) {

	const period = this.firstParent('.series-item-timeline-event');

	if(Series.isPrimaryPointer(e) === false || period === null || (!period.dataset.cultivation && !period.dataset.flow)) {
		return;
	}

	e.preventDefault();
	e.stopImmediatePropagation();
	Series.startHarvestShift(period, e, this.classList.contains('series-item-timeline-bound-first') ? 'first' : 'last');

});

// Une campagne de culture comme une période d'itinéraire technique se déplacent
// en bloc par leur milieu, et s'étirent par leurs deux bornes.
document.delegateEventListener('pointerdown', '#series-wrapper .series-item-timeline-event-period', function(e) {
	if(Series.isPrimaryPointer(e) === false || (!this.dataset.cultivation && !this.dataset.flow)) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	Series.startHarvestShift(this, e, 'move');
});

document.delegateEventListener('pointerdown', '#series-wrapper .sequence-plan-event-span[data-flow]', function(e) {
	if(Series.isPrimaryPointer(e) === false) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	Series.startHarvestShift(this, e, 'move');
});

document.delegateEventListener('pointerdown', '#series-wrapper .series-item-timeline-event-movable', function(e) {
	if(Series.isPrimaryPointer(e) === false) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	Series.startTaskShift(this, e);
});



class Series {

	// Une semaine mesure 15 px à l'écran et son numéro en mesure 20 : aucun
	// étiquetage permanent ne tient. La règle ne s'affiche donc qu'au survol,
	// calée sur la semaine, jamais sur le pixel. Montée par l'onrender du
	// conteneur : navigation.wakeup ne suit pas les rechargements ajax.
	// Les séries qui viennent d'être créées s'allument quand le plan revient. Les
	// instructions d'une réponse avec redirection sont jouées APRÈS le
	// rechargement (ajax.js, renderRedirect) : au moment où le serveur nomme les
	// copies, les lignes sont déjà là, on allume tout de suite. Dans l'autre sens
	// (ids nommés avant le rendu), on garde la liste jusqu'à ce qu'une ligne
	// existe.
	static highlighted = [];
	static highlightedLabel = null;
	static highlightedScrolled = false;
	static highlightedTimeout = null;

	static highlightNext(ids, label = null) {

		Series.highlighted = ids.map(id => String(id));
		Series.highlightedLabel = label;
		Series.highlightedScrolled = false;
		Series.highlight();

		clearTimeout(Series.highlightedTimeout);
		Series.highlightedTimeout = setTimeout(() => Series.unhighlight(), 5000);

	}

	// Pendant les cinq secondes de l'allumage, les ids restent en mémoire et se
	// reposent à chaque rendu du plan : un rechargement ajax n'emporte pas le repère.
	static highlight() {

		if(Series.highlighted.length === 0) {
			return;
		}

		const lines = [];

		Series.highlighted.forEach(id => {
			qsa('#series-wrapper [name="batch[]"][value="'+ id +'"]', field => {
				const line = field.firstParent('.series-item-planning');
				if(line !== null) {
					lines.push(line);
				}
			});
		});

		if(lines.length === 0) {
			return;
		}

		lines.forEach(line => {

			line.classList.add('series-item-planning-new');

			if(Series.highlightedLabel !== null && line.qs('.series-item-planning-new-label') === null) {
				const badge = document.createElement('span');
				badge.className = 'series-item-planning-new-label';
				badge.textContent = Series.highlightedLabel;
				line.prepend(badge);
			}

		});

		// On ne ramène à l'écran qu'à la première pose : un rechargement plus tard
		// n'a pas à faire sauter la page sous les yeux.
		if(Series.highlightedScrolled === false) {
			Series.highlightedScrolled = true;
			lines[0].scrollIntoView({block: 'center', behavior: 'smooth'});
		}

	}

	static unhighlight() {

		Series.highlighted = [];
		Series.highlightedTimeout = null;

		qsa('#series-wrapper .series-item-planning-new', line => {

			line.classList.add('series-item-planning-new-out');

			setTimeout(() => {
				line.classList.remove('series-item-planning-new', 'series-item-planning-new-out');
				line.qs('.series-item-planning-new-label', badge => badge.remove());
			}, 600);

		});

	}

	/*
	 * Le filtre de la page : on masque, on ne recharge pas. Le plan porte déjà ses
	 * lignes, un aller-retour serveur par critère posé n'apprendrait rien de plus.
	 *
	 * Le composant util-omnibox porte le champ, les jetons et le clavier. Ne restent
	 * ici que les deux choses qu'il ne peut pas savoir : ce que le plan a à proposer,
	 * et ce qu'un critère posé masque.
	 */
	static normalizeSearch(value) {
		return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	// Les groupes du déroulant, et l'attribut de ligne où chacun se cherche.
	static planGroups = {plant: 'Espèces', variety: 'Variétés', tool: 'Matériel', idle: 'Interventions'};

	static planGroupKey(label) {
		return Object.keys(Series.planGroups).find(key => Series.planGroups[key] === label);
	}

	static planRows(wrapper) {

		const rows = [];

		// L'attribut plutôt que la classe : les trois onglets nomment leurs lignes
		// autrement — planning, harvesting, working-time — mais portent le même
		// vocabulaire de recherche.
		wrapper.qsa('[data-search-plant]', row => {

			if(row.planSearch === undefined) {

				row.planSearch = {};

				Object.keys(Series.planGroups).forEach(group => {
					row.planSearch[group] = (row.dataset['search'+ group[0].toUpperCase() + group.slice(1)] ?? '')
						.split('|')
						.filter(value => value !== '')
						.map(value => Series.normalizeSearch(value));
				});

			}

			rows.push(row);

		});

		return rows;

	}

	/*
	 * Rejouer le filtre sur un plan re-rendu.
	 *
	 * Toute écriture — glisser une série, un semis, valider un panneau, une action de
	 * lot — répond par une ReloadAction qui remplace #series-wrapper. Les jetons
	 * survivent, eux, puisque l'omnibox est hors de ce bloc : sans ce rappel le filtre
	 * se lit comme actif, jetons posés et décompte affiché, sur un plan qui montre tout.
	 *
	 * Rien n'est mémorisé et rien n'est énuméré : on relit l'état courant des jetons et
	 * on le réapplique. Un appelant nouveau n'a donc rien à déclarer pour en profiter,
	 * et aucun ne peut être oublié.
	 */
	static replayPlanFilter() {

		const box = qs('#series-filter');

		if(box === null) {
			return;
		}

		// Les suggestions avaient été relevées sur les lignes d'avant et ne décrivent
		// plus le plan — une série créée apporte ses variétés, une série supprimée
		// emporte les siennes.
		delete box.omniboxSuggestions;

		Series.readPlanFilter(box);
		Series.filterPlan(box);

	}

	/*
	 * Le filtre vit dans l'URL, sous `filtre`, et doit être posé aux deux endroits.
	 *
	 * Le détour vaut d'être noté, parce que la première tentative a échoué pour une
	 * raison invisible à la lecture : un rechargement ajax ne va pas chercher la barre
	 * d'adresse mais `body[data-context]` (`ajax.js`, `case 'ajaxReload'`), une URL
	 * posée par le serveur. Un paramètre ajouté par un `replaceState` nu n'y arrivait
	 * donc jamais, et disparaissait au premier rendu.
	 *
	 * `history.updateArgument()` réécrit bien les deux, mais seulement quand l'adresse
	 * et le contexte sont déjà identiques (`lime.js`) — et sur le plan ils ne le sont
	 * pas dès qu'on y arrive par un lien interne : le serveur canonise la saison dans
	 * le chemin, l'adresse devient `/ferme/7/series/2026?view=area` là où le contexte
	 * reste `/ferme/7/series?view=area`. Le report est donc refait ici sans condition,
	 * sur la seule clé `filtre`.
	 *
	 * Le libellé voyage avec le groupe et la valeur plutôt que d'être reconstruit :
	 * « juin » ne se déduit pas de la liste de semaines qu'il désigne.
	 */
	static writePlanFilter(box) {

		const tokens = UtilOmnibox.getTokens(box).map(token => [token.group, token.value, token.label]);

		const value = (tokens.length === 0) ? null : JSON.stringify(tokens);

		if(value === null) {
			history.removeArgument('filtre', true);
		} else {
			history.updateArgument('filtre', value, 'replace');
		}

		const context = document.body.dataset.context;

		if(context) {
			document.body.dataset.context = (value === null) ? context.removeArgument('filtre') : context.setArgument('filtre', value);
		}

		/*
		 * Les onglets emportent le filtre avec eux.
		 *
		 * Leurs liens sont rendus par le serveur, qui ne connaît pas un état posé dans le
		 * navigateur : sans ce report, passer aux récoltes repartirait d'une adresse sans
		 * `filtre` et le perdrait. Le report se refait à chaque changement de jetons et
		 * après chaque rendu, donc des liens neufs sont toujours à jour.
		 *
		 * Ce sont les seuls liens concernés, et c'est voulu : quitter le plan par le menu
		 * ou changer de saison abandonne le filtre, ce qui est le sens d'un état porté par
		 * l'adresse.
		 */
		qsa('.series-views .tab-item', tab => {
			tab.href = (value === null) ? tab.href.removeArgument('filtre') : tab.href.setArgument('filtre', value);
		});

	}

	static readPlanFilter(box) {

		/*
		 * L'adresse d'abord, le contexte ensuite — et le second n'est pas un repli de
		 * confort.
		 *
		 * Après un rendu ajax, le client réécrit la barre d'adresse avec l'URL canonique
		 * que le serveur lui rend, laquelle ignore les paramètres qu'il ne connaît pas :
		 * `filtre` en disparaît. `body[data-context]`, lui, le garde — c'est l'URL que le
		 * framework tient pour courante et qu'il ira chercher au rechargement suivant.
		 *
		 * Lire les deux, dans cet ordre, couvre donc le lien ouvert à la main comme le
		 * rendu qui vient de passer. `writePlanFilter()`, appelé juste après par
		 * `filterPlan()`, remet aussitôt le paramètre dans l'adresse.
		 */
		const raw = new URL(location.href).searchParams.get('filtre')
			?? new URL(document.body.dataset.context ?? location.href, location.origin).searchParams.get('filtre');

		if(raw === null) {
			return;
		}

		let tokens;

		try {
			tokens = JSON.parse(raw);
		} catch(e) {
			return;
		}

		// setToken() retire d'abord le jeton de mêmes groupe et valeur : reposer sur une
		// boîte qui les porte déjà ne duplique rien, ce qui rend l'appel sans risque quel
		// que soit l'état de la boîte au moment du rendu.
		tokens.forEach(([group, value, label]) => UtilOmnibox.setToken(box, group, value, label));

	}

	// Ce que le plan a à proposer, relevé sur ses propres lignes : rien n'est demandé
	// au serveur, la page porte déjà les trois listes.
	static getPlanSuggestions() {

		const wrapper = qs('#series-wrapper');

		if(wrapper === null) {
			return [];
		}

		const values = {};

		Object.keys(Series.planGroups).forEach(group => values[group] = new Set());

		Series.planRows(wrapper).forEach(row => {
			Object.keys(values).forEach(group => {
				(row.dataset['search'+ group[0].toUpperCase() + group.slice(1)] ?? '').split('|').forEach(value => {
					value === '' || values[group].add(value);
				});
			});
		});

		// La vignette de l'espèce est déjà à l'écran, sur l'en-tête de son groupe : on la
		// relève là plutôt que de la redemander au serveur. Toutes les espèces n'en ont
		// pas — une espèce propre à la structure n'a pas de symbole dans le sprite.
		const icons = {};

		wrapper.qsa('.series-item-title[data-plant]', title => {

			const name = title.qs('.series-item-title-plant-name')?.textContent.trim();
			const use = title.qs('use');

			if(name !== undefined && use !== null) {
				icons[name] = use.getAttribute('xlink:href') ?? use.getAttribute('href');
			}

		});

		const suggestions = [];

		Object.keys(values).forEach(group => {
			[...values[group]].sort((a, b) => a.localeCompare(b, 'fr')).forEach(value => {

				const suggestion = {group: Series.planGroups[group], label: value};

				icons[value] === undefined || (suggestion.icon = icons[value]);

				suggestions.push(suggestion);

			});
		});

		return suggestions;

	}

	/*
	 * Une ligne reste visible si elle répond à chaque groupe de critères posés.
	 *
	 * Dans un groupe, les critères s'ajoutent : demander « Ail » et « Artichaut », c'est
	 * demander les deux espèces, pas une ligne qui serait les deux à la fois — celle-là
	 * n'existe pas, et le filtre ne rendrait jamais rien. Entre groupes ils se croisent :
	 * « Ail » et « Germidour », c'est l'ail de cette variété-là.
	 *
	 * Les groupes portent aussi ce qu'un même mot peut désigner deux fois : une variété
	 * « Carotte » ne répond pas à l'espèce du même nom.
	 */
	static filterPlan(box) {

		const wrapper = qs('#series-wrapper');

		if(wrapper === null) {
			return;
		}

		const rows = Series.planRows(wrapper);

		// Un écran dont les lignes ne portent pas les attributs de recherche ne se filtre
		// pas : on ne masque rien et on n'annonce rien. Sans ce retour, un critère hérité
		// d'un autre onglet ne trouverait aucune ligne et cacherait tout le tableau, ce
		// qui se lit comme une panne plutôt que comme un filtre sans objet.
		if(rows.length === 0) {
			return;
		}

		const criteria = Series.planCriteria(box);

		Series.planSupportsWeeks() || (criteria.weeks = []);

		const filtered = (Object.keys(criteria.groups).length + criteria.weeks.length) > 0;
		const counts = {};

		rows.forEach(row => {

			const visible = Series.planVisible(row, criteria);

			row.classList.toggle('hide', visible === false);
			counts[row.dataset.plant] = (counts[row.dataset.plant] ?? 0) + (visible ? 1 : 0);

		});

		let total = 0;

		wrapper.qsa('.series-item-title[data-plant]', title => {
			const count = counts[title.dataset.plant] ?? 0;
			title.classList.toggle('hide', count === 0);
			total += count;
		});

		const count = box.qs('.util-omnibox-count');

		count.textContent = (filtered === false) ? '' : total +' '+ count.dataset.label;

		qs('.series-filter-empty')?.classList.toggle('hide', filtered === false || total > 0);

		Series.writePlanFilter(box);

	}
	/*
	 * Le filtre par semaine : un clic sur la graduation ouvre le choix des actions, et
	 * chacune porte le nombre de séries concernées.
	 *
	 * Le compte se lit **avant** de filtrer, et c'est tout l'intérêt : sur une même
	 * semaine, « récolte » et « plantation » ne pèsent pas le même poids, et la
	 * graduation ne le dit pas. Filtrer d'abord pour voir ensuite fait tâtonner.
	 *
	 * Le critère retenu part dans l'omnibox comme les autres : un filtre posé à un
	 * endroit et visible à un autre se perd, et celui-là s'effacerait du geste d'un
	 * écran qu'on ne regarde plus.
	 */

	// La semaine ISO écrite « 2026-W26 » : isoWeek() ne rend que le rang, et le plan
	// couvre trois années civiles qui portent les mêmes.
	static isoWeekKey(ts) {

		const date = new Date((ts + 43200) * 1000);
		const thursday = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));

		thursday.setUTCDate(thursday.getUTCDate() - ((thursday.getUTCDay() + 6) % 7) + 3);

		return thursday.getUTCFullYear() +'-W'+ String(Series.isoWeek(ts)).padStart(2, '0');

	}

	// Ce qui se passe sur une ligne, semaine par semaine. Les interventions portent leur
	// semaine, la campagne de récolte ses deux bornes : elle compte pour chacune des
	// semaines qu'elle couvre, sinon récolter treize semaines ne se verrait qu'une fois.
	static planActions(row) {

		if(row.planWeeks === undefined) {

			row.planWeeks = {};

			const add = (week, action) => {
				const actions = (row.planWeeks[week] ??= []);
				actions.includes(action) || actions.push(action);
			};

			row.qsa('.series-item-timeline-event[data-week][data-action]', pill => add(pill.dataset.week, pill.dataset.action));

			row.qsa('.series-item-timeline-event[data-first][data-action]', bar => {

				const last = parseInt(bar.dataset.last);

				for(let ts = parseInt(bar.dataset.first); ts <= last; ts += 604800) {
					add(Series.isoWeekKey(ts), bar.dataset.action);
				}

			});

		}

		return row.planWeeks;

	}

	static weekNumber(week) {
		return parseInt(week.split('-W')[1]);
	}

	// Un critère porte une liste de semaines : une seule, une plage prise à Maj + clic,
	// ou celles d'un mois. Rien ne les distingue une fois posées — c'est le libellé du
	// jeton, non sa valeur, qui dit d'où il vient.
	// Les actions sont encodées : leur nom vient du référentiel de la structure, donc
	// d'une saisie libre, et un « | » dedans couperait la valeur en deux critères.
	static weekTokenValue(weeks, actions) {
		return [weeks.join(',')].concat(actions.map(encodeURIComponent)).join('|');
	}

	static parseWeekToken(value) {

		const parts = value.split('|');

		return {weeks: parts[0].split(','), actions: parts.slice(1).map(decodeURIComponent)};

	}

	// Le jeton doit dire ce qu'il retranche, pas seulement où il regarde : « s26 » quand
	// toutes les actions comptent, « s26 · semis, plantation » sinon.
	static weekTokenLabel(label, actions) {
		return (actions.length === 0) ? label : label +' · '+ actions.map(action => action.toLowerCase()).join(', ');
	}

	static planWeekMatch(row, criterion) {

		const weeks = Series.planActions(row);

		return criterion.weeks.some(week => {

			const actions = weeks[week] ?? [];

			return (criterion.actions.length === 0) ? actions.length > 0 : criterion.actions.some(action => actions.includes(action));

		});

	}

	// Les cellules de la graduation dans l'ordre du calendrier : elles servent à la fois
	// à retrouver une semaine sous le pointeur, à découper une plage et à rassembler un
	// mois.
	static weekCells() {
		return [...qsa('.series-season-filterable .series-season-week[data-week]')];
	}

	// Une semaine appartient au mois dès qu'un de ses sept jours y tombe. À cheval, elle
	// compte pour les deux : on cherche ce qui se passe en juin, pas ce que la norme ISO
	// range en juin.
	static monthWeeks(month) {

		const [year, rank] = month.split('-').map(value => parseInt(value));
		const first = Date.UTC(year, rank - 1, 1) / 1000;
		const last = Date.UTC(year, rank, 1) / 1000 - 1;

		return Series.weekCells()
			.map(cell => cell.dataset.week)
			.filter(week => {
				const monday = Series.isoMonday(week);
				return monday <= last && monday + 7 * 86400 - 1 >= first;
			});

	}

	// Les jetons posés, rangés en deux : ceux qui se cherchent dans le texte de la
	// ligne, et ceux qui désignent une semaine. Les seconds ne se comparent pas comme
	// les premiers, mais ils suivent la même règle d'assemblage.
	static planCriteria(box) {

		const groups = {};
		const weeks = [];

		Object.entries(UtilOmnibox.getTokenGroups(box)).forEach(([label, values]) => {

			if(label === Series.weekLabels.family) {
				values.forEach(value => weeks.push(Series.parseWeekToken(value)));
			} else {
				groups[Series.planGroupKey(label)] = values.map(value => Series.normalizeSearch(value));
			}

		});

		return {groups: groups, weeks: weeks};

	}

	static planVisible(row, criteria) {

		const text = Object.keys(criteria.groups).every(group => criteria.groups[group].some(value => (row.planSearch[group] ?? []).includes(value)));

		return text && (criteria.weeks.length === 0 || criteria.weeks.some(criterion => Series.planWeekMatch(row, criterion)));

	}

	/*
	 * Un critère de semaine ne s'applique que là où les lignes portent des semaines.
	 *
	 * Le filtre se conserve d'un onglet à l'autre, mais les onglets ne montrent pas la
	 * même chose : les récoltes et le temps de travail n'ont ni graduation hebdomadaire
	 * ni pastilles d'intervention. Appliqué là, un « s26 » ne trouverait rien et
	 * masquerait tout le tableau — un filtre sans objet doit se taire, pas tout cacher.
	 */
	static planSupportsWeeks() {
		return qs('.series-season-filterable .series-season-week[data-week]') !== null;
	}

	/*
	 * Le clic ne vise pas la cellule du numéro, et ce n'est pas un détail : le repère de
	 * la semaine en cours passe par-dessus la graduation sur six cents pixels, et sous
	 * 1200 px une semaine sur cinq seulement reste visible — or un élément en
	 * `visibility: hidden` n'est pas testable au pointeur. On écoute donc le conteneur
	 * et on retrouve la semaine par l'abscisse, la géométrie restant juste dans les deux
	 * cas.
	 */
	static weekAt(weeks, x) {

		let found = null;

		weeks.qsa('.series-season-week[data-week]', cell => {

			const box = cell.getBoundingClientRect();

			if(x >= box.left && x < box.right) {
				found = cell;
			}

		});

		return found;

	}

	static openWeekMenu(anchor, weeks, title, label) {

		const box = qs('#series-filter');
		const list = qs('.series-week-list');

		if(box === null || list === null || weeks.length === 0) {
			return;
		}

		const wrapper = qs('#series-wrapper');

		// Le compte ignore les jetons de semaine déjà posés, mais pas les autres : sur un
		// plan déjà réduit aux carottes, annoncer les récoltes de tout le plan tromperait.
		const criteria = Series.planCriteria(box);
		const counts = {};
		let total = 0;

		Series.planRows(wrapper).forEach(row => {

			if(Series.planVisible(row, {groups: criteria.groups, weeks: []}) === false) {
				return;
			}

			// Une série qui sème en s22 et récolte en s26 ne compte qu'une fois par
			// action sur la plage : c'est un nombre de séries, pas d'interventions.
			const actions = [];

			weeks.forEach(week => (Series.planActions(row)[week] ?? []).forEach(action => actions.includes(action) || actions.push(action)));

			actions.forEach(action => counts[action] = (counts[action] ?? 0) + 1);

			actions.length === 0 || total++;

		});

		const value = weeks.join(',');
		const posed = criteria.weeks.find(criterion => criterion.weeks.join(',') === value);

		list.replaceChildren();
		list.dataset.weeks = value;
		list.dataset.label = label;

		const heading = document.createElement('div');

		heading.className = 'dropdown-title';
		heading.textContent = title;

		list.append(heading);

		const option = (action, count, checked, all) => {

			const item = document.createElement('label');

			item.className = 'dropdown-item series-week-option';

			const input = document.createElement('input');

			input.type = 'checkbox';
			input.checked = checked;
			all ? (input.dataset.all = '1') : (input.value = action);

			const number = document.createElement('span');

			number.className = 'series-week-option-count';
			number.textContent = count;

			item.append(input, document.createTextNode(action), number);
			list.append(item);

		};

		Object.keys(counts).forEach(action => {
			option(action, counts[action], posed !== undefined && (posed.actions.length === 0 || posed.actions.includes(action)), false);
		});

		if(Object.keys(counts).length === 0) {

			const empty = document.createElement('div');

			empty.className = 'dropdown-item series-week-empty';
			empty.textContent = Series.weekLabels.empty;

			list.append(empty);

		} else {

			const divider = document.createElement('div');

			divider.className = 'dropdown-divider';

			list.append(divider);

			// « Toutes » suit ce que les cases disent, pas ce que le jeton porte : un
			// jeton qui nomme la seule action de la semaine la couvre entièrement, et le
			// contraire se lirait comme une case oubliée.
			const covered = posed !== undefined && (posed.actions.length === 0 || Object.keys(counts).every(action => posed.actions.includes(action)));

			option(Series.weekLabels.all, total, covered, true);

		}

		anchor.dataset.dropdownId = 'series-week';

		Lime.Dropdown.open(anchor, 'bottom-start');

	}

	// Ce qu'un clic sur la graduation désigne, selon le geste : une semaine, la plage
	// depuis la dernière semaine ouverte si Maj est tenue, ou un mois entier.
	static openWeek(cell, extend) {

		const cells = Series.weekCells();
		const anchor = (extend && Series.weekAnchor !== null) ? cells.findIndex(one => one.dataset.week === Series.weekAnchor) : -1;
		const index = cells.indexOf(cell);

		const weeks = (anchor === -1)
			? [cell.dataset.week]
			: cells.slice(Math.min(anchor, index), Math.max(anchor, index) + 1).map(one => one.dataset.week);

		// L'ancre ne bouge pas quand on étend : Maj + clic sur une troisième semaine
		// corrige la plage au lieu d'en ouvrir une autre.
		anchor === -1 && (Series.weekAnchor = cell.dataset.week);

		const label = (weeks.length === 1)
			? Series.weekLabel(Series.weekNumber(weeks[0]))
			: Series.weekLabel(Series.weekNumber(weeks[0])) +' → '+ Series.weekLabel(Series.weekNumber(weeks[weeks.length - 1]));

		const title = (weeks.length === 1)
			? Series.weekLabels.title +' '+ Series.weekNumber(weeks[0]) +' · '+ Series.weekDates(weeks[0])
			: Series.weekLabels.titlePlural +' '+ Series.weekNumber(weeks[0]) +' '+ Series.weekLabels.to +' '+ Series.weekNumber(weeks[weeks.length - 1]) +' · '+ Series.weekSpan(weeks);

		Series.openWeekMenu(cell, weeks, title, label);

	}

	static openMonth(cell) {

		const weeks = Series.monthWeeks(cell.dataset.month);
		const [year, rank] = cell.dataset.month.split('-').map(value => parseInt(value));
		const name = Series.weekLabels.months[rank - 1];

		Series.weekAnchor = null;

		Series.openWeekMenu(cell, weeks, name.charAt(0).toUpperCase() + name.slice(1) +' '+ year, name);

	}

	static weekSpan(weeks) {

		const first = new Date(Series.isoMonday(weeks[0]) * 1000);
		const last = new Date((Series.isoMonday(weeks[weeks.length - 1]) + 6 * 86400) * 1000);
		const month = (date) => Series.weekLabels.months[date.getUTCMonth()];

		return first.getUTCDate() +' '+ month(first) +' – '+ last.getUTCDate() +' '+ month(last);

	}

	// Du lundi au dimanche, en clair : le numéro de semaine seul ne dit pas à quel
	// moment de l'année on est.
	static weekDates(week) {

		const monday = new Date(Series.isoMonday(week) * 1000);
		const sunday = new Date((Series.isoMonday(week) + 6 * 86400) * 1000);
		const month = (date) => Series.weekLabels.months[date.getUTCMonth()];

		if(monday.getUTCMonth() === sunday.getUTCMonth()) {
			return monday.getUTCDate() +' – '+ sunday.getUTCDate() +' '+ month(sunday);
		}

		return monday.getUTCDate() +' '+ month(monday) +' – '+ sunday.getUTCDate() +' '+ month(sunday);

	}

	static applyWeekMenu(input) {

		const list = input.firstParent('.series-week-list');
		const box = qs('#series-filter');
		const weeks = list.dataset.weeks.split(',');

		if(input.dataset.all !== undefined) {
			list.qsa('.series-week-option input:not([data-all])', one => one.checked = input.checked);
		}

		const actions = [...list.qsa('.series-week-option input:not([data-all])')];
		const checked = actions.filter(one => one.checked);
		const chosen = (checked.length === actions.length) ? [] : checked.map(one => one.value);

		list.qs('.series-week-option input[data-all]').checked = (checked.length === actions.length);

		const previous = Series.planCriteria(box).weeks.find(criterion => criterion.weeks.join(',') === list.dataset.weeks);

		UtilOmnibox.setToken(
			box,
			Series.weekLabels.family,
			(checked.length === 0) ? null : Series.weekTokenValue(weeks, chosen),
			(checked.length === 0) ? null : Series.weekTokenLabel(list.dataset.label, chosen),
			(previous === undefined) ? null : Series.weekTokenValue(weeks, previous.actions)
		);

	}

	static weekAnchor = null;

	static plugRuler() {

		Series.highlight();


		const wrapper = qs('#series-wrapper');

		if(wrapper === null || wrapper.dataset.start === undefined || wrapper.qs('.series-item-ruler') !== null) {
			return;
		}

		const rule = document.createElement('div');
		rule.className = 'series-item-ruler hide';
		wrapper.append(rule);


		const start = parseInt(wrapper.dataset.start);
		const offset = parseInt(wrapper.dataset.offset);
		const span = parseInt(wrapper.dataset.days) * 86400;

		wrapper.addEventListener('pointermove', (event) => {

			const track = wrapper.qs('.series-item-planning-timeline');

			// Pendant un glissé la règle n'a rien à dire : les repères sous la ligne
			// portent déjà les semaines visées.
			if(track === null || wrapper.qs('.series-item-timeline-holding') !== null) {
				rule.classList.add('hide');
				return;
			}

			const zone = track.getBoundingClientRect();
			const box = wrapper.getBoundingClientRect();
			const step = zone.width * 604800 / span;

			if(event.clientX < zone.left || event.clientX > zone.right) {
				rule.classList.add('hide');
				return;
			}

			// L'origine est le lundi de la première semaine, qui tombe en général
			// avant le bord du diagramme : le décalage est donc négatif.
			const origin = zone.left + offset * zone.width / span;
			const rank = Math.floor((event.clientX - origin) / step);

			// Les traits de la grille sont posés à « X% - 1px » : la règle reprend ce
			// décalage pour se superposer au trait au lieu de le doubler.
			rule.style.left = (origin - box.left + rank * step - 1) + 'px';
			rule.style.width = step + 'px';
			// La règle traverse l'en-tête : c'est là que se lit le numéro, sur la
			// graduation, comme sur la bibliothèque de cultures.
			rule.classList.remove('hide');


		});

		wrapper.addEventListener('pointerleave', () => {
			rule.classList.add('hide');
		});

	}

	// Le jeudi de la semaine porte son année : c'est la définition ISO, et c'est
	// ce qui fait qu'une semaine de janvier peut appartenir à l'année précédente.
	static isoWeek(ts) {

		// Les instants qu'on reçoit sont des lundis minuit, parfois décalés d'une
		// heure par un changement d'heure (dimanche 23 h) : midi les recale sans
		// jamais changer de semaine un vrai lundi.
		const date = new Date((ts + 43200) * 1000);
		const thursday = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));

		thursday.setUTCDate(thursday.getUTCDate() - ((thursday.getUTCDay() + 6) % 7) + 3);

		const first = new Date(Date.UTC(thursday.getUTCFullYear(), 0, 4));

		first.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7) + 3);

		return 1 + Math.round((thursday - first) / 604800000);

	}

	// Décaler une semaine ISO écrite « 2026-W39 » : le 4 janvier appartient
	// toujours à la semaine 1, il sert donc d'ancre pour retrouver le lundi.
	static shiftIsoWeek(week, weeks) {

		const [year, rank] = week.split('-W').map(value => parseInt(value));
		const monday = new Date(Date.UTC(year, 0, 4));

		monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7) + (rank - 1) * 7 + weeks * 7);

		return Series.isoWeek(monday.getTime() / 1000);

	}

	static weekLabel(week) {
		return Series.labels.week + String(week).padStart(2, '0');
	}

	// Lundi d'une semaine ISO « 2026-W39 », en secondes.
	static isoMonday(week) {

		const [year, rank] = week.split('-W').map(value => parseInt(value));
		const monday = new Date(Date.UTC(year, 0, 4));

		monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7) + (rank - 1) * 7);

		return monday.getTime() / 1000;

	}

	// Sous la ligne survolée, la lecture de la bibliothèque de cultures posée sur
	// la série réelle : un repère sous chaque moment fixe — semis, plantation,
	// début et fin de récolte — avec son numéro de semaine, et entre deux repères
	// un pointillé qui porte l'écart. Un seul vocabulaire : un point se numérote,
	// une distance se compte, la campagne n'est qu'une distance de plus. Les
	// positions viennent du DOM, donc d'un glissé en cours, et les semaines des
	// décalages posés par le geste.
	static renderMarks(track) {

		const timeline = track.qs('.series-item-timeline');

		if(timeline === null) {
			return;
		}

		let marks = timeline.qs('.series-item-timeline-marks');

		if(marks === null) {
			marks = document.createElement('div');
			marks.className = 'series-item-timeline-marks';
			timeline.append(marks);
		}

		const zone = timeline.getBoundingClientRect();
		const steps = [];

		timeline.qsa('.series-item-timeline-event[data-week]', pill => {
			const box = pill.getBoundingClientRect();
			const shift = parseInt(pill.dataset.shift || 0);
			steps.push({ts: Series.isoMonday(pill.dataset.week) + shift * 604800, x: box.left + box.width / 2 - zone.left, week: Series.shiftIsoWeek(pill.dataset.week, shift)});
		});

		// La campagne attendue est celle qu'on manipule ; à défaut, la récolte faite.
		let periods = [];
		timeline.qsa('.series-item-timeline-event-period', node => periods.push(node));
		if(periods.length === 0) {
			timeline.qsa('.series-item-timeline-event-harvest', node => periods.push(node));
		}

		periods.forEach(period => {
			const box = period.getBoundingClientRect();
			const first = parseInt(period.dataset.first) + parseInt(period.dataset.shiftFirst || 0) * 604800;
			const last = parseInt(period.dataset.last) + parseInt(period.dataset.shiftLast || 0) * 604800;
			steps.push({ts: first, x: box.left - zone.left, week: Series.isoWeek(first)});
			steps.push({ts: last + 7 * 86400, x: box.right - zone.left, week: Series.isoWeek(last)});
		});

		steps.sort((a, b) => a.ts - b.ts);

		// Deux repères sur la même semaine n'en font qu'un.
		const unique = steps.filter((step, i) => i === 0 || Math.abs(step.ts - steps[i - 1].ts) > 86400);

		let h = '';

		unique.forEach((step, i) => {

			if(i > 0) {
				const previous = unique[i - 1];
				const weeks = Math.round((step.ts - previous.ts) / 604800);
				h += '<div class="series-item-timeline-delay" style="left: '+ previous.x +'px; width: '+ (step.x - previous.x) +'px">'
					+ (weeks >= 6 ? '<span>'+ weeks +' '+ Series.labels.weeksShort +'</span>' : '')
					+ '</div>';
			}

		});

		// Deux numéros à moins de vingt pixels se recouvrent : une semaine en fait
		// quinze et son numéro vingt. Le second s'efface, le trait reste.
		let lastX = -Infinity;

		unique.forEach(step => {
			if(step.x - lastX < 22) {
				return;
			}
			lastX = step.x;
			h += '<span class="series-item-timeline-mark" style="left: '+ step.x +'px">'+ Series.weekLabel(step.week) +'</span>';
		});

		marks.innerHTML = h;

	}

	// Un clic droit ou un second doigt ne démarre pas de geste.
	static isPrimaryPointer(e) {
		return e.isPrimary !== false && (e.button === undefined || e.button === 0);
	}

	// Suivre le pointeur jusqu'au bout : la souris peut sortir de la fenêtre,
	// le doigt déclencher un défilement, un onglet passer en arrière-plan.
	// Chaque cas se traduit par un pointercancel, traité comme un relâchement.
	static followPointer(node, e, move, up) {

		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', up);

		if(e.pointerId !== undefined && node.setPointerCapture) {
			try {
				node.setPointerCapture(e.pointerId);
			} catch(error) {
				// pointeur déjà relâché : le geste s'annule au pointerup qui suit
			}
		}

	}

	static unfollowPointer(move, up) {
		document.removeEventListener('pointermove', move);
		document.removeEventListener('pointerup', up);
		document.removeEventListener('pointercancel', up);
	}

	// Un refus du serveur (FailWatch) ne rejette pas la promesse d'Ajax.Query :
	// elle se résout avec les instructions d'erreur. Le succès, lui, recharge la
	// page. Tout ce qui se résout sans recharger est donc un refus à défaire.
	static refused(json) {
		return json && json.__redirect === undefined;
	}

	// Glisser une série la décale dans le temps. Le geste n'est actif que sur une
	// ligne déjà sélectionnée : au doigt, un glissé sur une barre non sélectionnée
	// est indiscernable d'un glissé pour faire défiler la page, et le navigateur
	// tranche toujours en faveur du défilement.
	static startShift(bar, e) {

		const row = bar.firstParent('.series-item-planning');
		const box = row?.qs('[name="batch[]"]');
		const track = row?.qs('.series-item-planning-timeline');
		const wrapper = qs('#series-wrapper');

		// Une ligne de plan porte une case à cocher, une ligne d'itinéraire porte
		// l'identifiant de sa séquence : l'une des deux suffit.
		if((!box && !bar.dataset.sequence) || !track || !wrapper.dataset.duration) {
			return;
		}

		const duration = parseInt(wrapper.dataset.duration);
		const width = track.getBoundingClientRect().width;
		const startX = e.clientX;

		let armed = false;
		const arm = setTimeout(() => {
			armed = true;
			bar.classList.add('series-item-timeline-holding');
			ghost.classList.remove('hide');
		}, 350);

		bar.dataset.width = bar.getBoundingClientRect().width;

		// Le fantôme garde la position de départ sous les yeux : sans lui, on ne
		// sait plus de combien on a bougé une fois la barre déplacée.
		const ghost = bar.cloneNode(false);
		ghost.removeAttribute('id');
		ghost.classList.add('series-item-timeline-ghost', 'hide');
		bar.parentNode.insertBefore(ghost, bar);

		// Le ruban ne se déplace que si rien n'est fait : toutes les pastilles de la
		// ligne et la campagne attendue suivent donc le geste, et les repères en
		// dessous se recalculent.
		const pills = [...track.querySelectorAll('.series-item-timeline-event-movable')];
		const periods = [...track.querySelectorAll('.series-item-timeline-event-period')];

		let weeks = 0;

		const move = (event) => {

			if(armed === false) {
				return;
			}

			event.preventDefault();

			const dx = event.clientX - startX;

			weeks = Math.round(dx / width * duration / 604800);

			const px = Math.round(weeks * width * 604800 / duration);

			// translateY(-50%) centre l'élément : le remplacer par un translateX
			// seul le ferait remonter d'une demi-hauteur.
			bar.style.transform = 'translate('+ px +'px, -50%)';

			pills.forEach(pill => {
				pill.style.transform = 'translate('+ px +'px, -50%)';
				pill.dataset.shift = weeks;
			});

			periods.forEach(period => {
				period.style.transform = 'translate('+ px +'px, -50%)';
				period.dataset.shiftFirst = weeks;
				period.dataset.shiftLast = weeks;
			});

			Series.renderMarks(track);

		};

		const restore = () => {
			bar.style.transform = '';
			pills.forEach(pill => {
				pill.style.transform = '';
				delete pill.dataset.shift;
			});
			periods.forEach(period => {
				period.style.transform = '';
				delete period.dataset.shiftFirst;
				delete period.dataset.shiftLast;
			});
			Series.renderMarks(track);
		};

		const up = () => {

			clearTimeout(arm);

			Series.unfollowPointer(move, up);

			bar.classList.remove('series-item-timeline-holding');
			ghost.remove();

			if(weeks === 0) {
				restore();
				return;
			}

			bar.classList.add('series-item-timeline-pending');

			const done = () => {
				bar.classList.remove('series-item-timeline-pending');
				restore();
			};

			if(bar.dataset.sequence) {
				this.shiftSequence(bar.dataset.sequence, weeks, track, done);
			} else {
				this.shiftSelection(row, weeks, done);
			}

		};

		Series.followPointer(bar, e, move, up);

	}

	// Étirer ou rétracter la récolte attendue. Une série n'a pas de durée propre :
	// ses dates vivent sur ses interventions. La campagne de récolte, elle, est
	// une période stockée telle quelle — c'est la seule chose du plan qui se borne.
	static startHarvestShift(period, e, mode) {

		const track = period?.firstParent('.series-item-planning-timeline');
		const wrapper = qs('#series-wrapper');

		if(!period || track === null || !wrapper.dataset.duration) {
			return;
		}

		const isFirst = (mode === 'first');
		const duration = parseInt(wrapper.dataset.duration);
		const width = track.getBoundingClientRect().width;
		const startX = e.clientX;

		const box = period.getBoundingClientRect();
		const zone = track.getBoundingClientRect();
		const left0 = box.left - zone.left;
		const width0 = box.width;

		let armed = false;
		let weeks = 0;

		const arm = setTimeout(() => {
			armed = true;
			period.classList.add('series-item-timeline-holding');
		}, e.pointerType === 'mouse' ? 0 : 350);

		const move = (event) => {

			if(armed === false) {
				return;
			}

			event.preventDefault();

			weeks = Math.round((event.clientX - startX) / width * duration / 604800);

			// Une borne ne franchit pas l'autre. Une campagne de récolte peut se
			// réduire à une semaine ; une période d'itinéraire, non — elle en dure au
			// moins deux (Flow::buildPeriodInterval()), et l'amener à zéro faisait
			// refuser l'écriture par le serveur au lieu de s'arrêter au bord.
			const span = Math.round((parseInt(period.dataset.last) - parseInt(period.dataset.first)) / 604800);
			const shortest = period.dataset.flow ? 1 : 0;

			if(mode === 'first') {
				weeks = Math.min(weeks, span - shortest);
			} else if(mode === 'last') {
				weeks = Math.max(weeks, shortest - span);
			}

			const px = Math.round(weeks * width * 604800 / duration);

			// Le bord opposé ne bouge pas : on pilote left et width ensemble, en
			// pixels, sinon la largeur posée par le style en ligne est perdue.
			if(mode === 'move') {
				period.style.left = (left0 + px) + 'px';
			} else if(isFirst) {
				period.style.left = (left0 + px) + 'px';
				period.style.width = (width0 - px) + 'px';
			} else {
				period.style.width = (width0 + px) + 'px';
			}

			// Les repères en dessous lisent ces décalages : mêmes bornes, même
			// arithmétique que le POST plus bas.
			period.dataset.shiftFirst = (mode === 'move' || isFirst) ? weeks : 0;
			period.dataset.shiftLast = (mode === 'move' || isFirst === false) ? weeks : 0;

			Series.renderMarks(track);

		};

		const up = () => {

			clearTimeout(arm);

			Series.unfollowPointer(move, up);

			period.classList.remove('series-item-timeline-holding');

			const restore = () => {
				period.classList.remove('series-item-timeline-pending');
				period.style.left = '';
				period.style.width = '';
				delete period.dataset.shiftFirst;
				delete period.dataset.shiftLast;
				Series.renderMarks(track);
			};

			if(weeks === 0) {
				restore();
				return;
			}

			period.classList.add('series-item-timeline-pending');

			const decalage = weeks * 604800;
			const body = new URLSearchParams();

			if(period.dataset.flow) {
				body.append('id', period.dataset.flow);
				body.append('increment', weeks);
				if(mode !== 'move') {
					body.append('bound', isFirst ? 'start' : 'stop');
				}
			} else {
				body.append('id', period.dataset.cultivation);
				body.append('first', parseInt(period.dataset.first) + ((mode === 'move' || isFirst) ? decalage : 0));
				body.append('last', parseInt(period.dataset.last) + ((mode === 'move' || isFirst === false) ? decalage : 0));
			}

			new Ajax.Query(track)
				.url(period.dataset.flow ? (mode === 'move' ? '/sequence/flow:doIncrementWeekPlan' : '/sequence/flow:doIncrementWeekBound') : '/series/cultivation:doUpdateHarvestPeriod')
				.body(body)
				.fetch()
				.then(json => Series.refused(json) && restore(), restore);

		};

		Series.followPointer(period, e, move, up);

	}

	// Déplacer une intervention : même contrat que la série, appui long puis
	// glissé, mais l'écriture porte sur une seule tâche.
	// Déplacer une intervention : appui long puis glissé. À l'appui long, deux
	// cibles apparaissent — monter le geste jusqu'à « avec la suite » (le défaut),
	// descendre jusqu'à « seule » — et une bulle chargée à la demande dit ce que
	// la suite contient : les interventions à faire égales ou postérieures, la
	// récolte attendue. Ce qui suivrait est dessiné en fantôme pendant le glissé.
	static startTaskShift(pill, e) {

		const track = pill.firstParent('.series-item-planning-timeline');
		const wrapper = qs('#series-wrapper');

		if(track === null || !wrapper.dataset.duration) {
			return;
		}

		const duration = parseInt(wrapper.dataset.duration);
		const width = track.getBoundingClientRect().width;
		const startX = e.clientX;
		const startY = e.clientY;
		const isTask = (pill.dataset.task !== undefined);

		let armed = false;
		let weeks = 0;
		let ghosts = [];

		// Deux cibles autour de la pastille tenue : monter le geste jusqu'à l'une
		// emmène la suite, descendre jusqu'à l'autre ne bouge que l'intervention.
		// Toucher une cible la retient — la pastille, elle, ne quitte jamais sa
		// ligne. Par défaut : avec la suite.
		let targets = null;
		let chosen = null;

		const arm = setTimeout(() => {

			armed = true;
			pill.classList.add('series-item-timeline-holding');

			targets = Series.openShiftTargets(pill, track);
			targets.classList.toggle('is-up', mode() === 'following');
			targets.classList.toggle('is-down', mode() === 'only');

			// La bulle qui détaille ce que « la suite » contient n'existe que pour
			// une intervention de série : elle se lit sur les tâches à faire, qu'un
			// itinéraire n'a pas.
			if(isTask) {
				Series.openShiftChoice(pill, track);
			}

		}, 350);

		const mode = () => chosen || 'only';

		// Les fantômes de ce qui suit : clonés une fois, déplacés à chaque pas.
		// Ce qui partira avec la pastille. Pour une intervention de série, le serveur
		// le dit dans la bulle : il faut connaître les tâches faites, que la page
		// n'affiche pas. Pour une intervention d'itinéraire, la règle est entièrement
		// lisible sur la ligne — tout ce qui démarre à la même semaine ou après,
		// exactement ce que fait FlowLib::incrementWeekFollowing().
		const followers = () => {

			if(mode() !== 'following') {
				return [];
			}

			if(pill.dataset.flow) {

				const from = parseInt(pill.dataset.first);
				const nodes = [];

				track.qsa('.series-item-timeline-event[data-flow]', node => {
					if(node !== pill && parseInt(node.dataset.first) >= from) {
						nodes.push(node);
					}
				});

				return nodes;

			}

			const choice = qs('#series-choice');

			if(choice === null) {
				return [];
			}

			const nodes = [];
			(choice.dataset.tasks || '').split(',').filter(id => id).forEach(id => qsa('#series-wrapper [data-task="'+ id +'"]', node => nodes.push(node)));
			(choice.dataset.cultivations || '').split(',').filter(id => id).forEach(id => qsa('#series-wrapper .series-item-timeline-event-period[data-cultivation="'+ id +'"]', node => nodes.push(node)));
			return nodes;

		};

		const move = (event) => {

			if(armed === false) {
				return;
			}

			event.preventDefault();

			weeks = Math.round((event.clientX - startX) / width * duration / 604800);

			const px = Math.round(weeks * width * 604800 / duration);

			// Au-delà de 14 px vers le haut ou le bas, la cible est touchée et
			// retenue ; revenir sur la ligne ne la relâche pas. La pastille ne bouge
			// qu'horizontalement : elle reste sur sa ligne quoi qu'il arrive.
			const dy = event.clientY - startY;
			const previous = mode();

			if(dy < -14) {
				chosen = 'following';
			} else if(dy > 14) {
				chosen = 'only';
			}

			if(targets !== null) {
				targets.classList.toggle('is-up', mode() === 'following');
				targets.classList.toggle('is-down', mode() === 'only');
			}

			pill.style.transform = 'translate('+ px +'px, -50%)';
			pill.dataset.shift = weeks;

			if(mode() !== previous) {
				ghosts.forEach(ghost => ghost.remove());
				ghosts = [];
			}

			if(ghosts.length === 0) {
				ghosts = followers().map(node => {
					const ghost = node.cloneNode(true);
					// La position vient d'une règle <style> sur l'id : sans id, le clone
					// tomberait au bord gauche. On lui recopie la géométrie calculée.
					const computed = getComputedStyle(node);
					ghost.style.left = computed.left;
					ghost.style.right = computed.right;
					ghost.style.width = computed.width;
					ghost.removeAttribute('id');
					// Un fantôme ne porte ni semaine ni identifiant : les repères et
					// les prochains glissés ne doivent pas le compter.
					['week', 'task', 'flow', 'sequence', 'first', 'last', 'cultivation', 'shift'].forEach(key => delete ghost.dataset[key]);
					ghost.classList.add('series-item-timeline-follower');
					ghost.classList.remove('series-item-timeline-event-movable', 'series-item-timeline-event-period', 'series-item-timeline-event-shiftable');
					node.parentNode.insertBefore(ghost, node.nextSibling);
					return ghost;
				});
			}

			ghosts.forEach(ghost => {
				ghost.style.transform = 'translate('+ px +'px, -50%)';
			});

			Series.renderMarks(track);

		};

		const up = () => {

			clearTimeout(arm);

			Series.unfollowPointer(move, up);

			pill.classList.remove('series-item-timeline-holding');
			targets?.remove();

			const restore = () => {
				pill.classList.remove('series-item-timeline-pending');
				pill.style.transform = '';
				delete pill.dataset.shift;
				ghosts.forEach(ghost => ghost.remove());
				ghosts = [];
				Series.renderMarks(track);
			};

			Series.closeShiftChoice();

			if(weeks === 0) {
				restore();
				return;
			}

			pill.classList.add('series-item-timeline-pending');
			ghosts.forEach(ghost => ghost.classList.add('series-item-timeline-pending'));

			const body = new URLSearchParams();

			// Le choix « seule / avec les suivantes » vaut des deux côtés : une
			// intervention d'itinéraire entraîne la suite comme celle d'une série.
			body.append('id', pill.dataset.flow ?? pill.dataset.task);
			body.append('mode', mode());
			body.append('increment', weeks);

			new Ajax.Query(track)
				.url(pill.dataset.flow ? '/sequence/flow:doIncrementWeekPlan' : '/series/task:doIncrementPlanned')
				.body(body)
				.fetch()
				.then(json => Series.refused(json) && restore(), restore);

		};

		Series.followPointer(pill, e, move, up);

	}

	static openShiftTargets(pill, track) {

		qsa('#series-wrapper .series-item-timeline-targets', node => node.remove());

		const zone = track.getBoundingClientRect();
		const box = pill.getBoundingClientRect();

		const targets = document.createElement('div');
		targets.className = 'series-item-timeline-targets';
		targets.style.left = (box.left + box.width / 2 - zone.left) + 'px';
		targets.innerHTML = '<div class="series-item-timeline-target series-item-timeline-target-up">'+ Series.labels.withFollowing +'</div>'
			+ '<div class="series-item-timeline-target series-item-timeline-target-down">'+ Series.labels.alone +'</div>';
		track.append(targets);

		return targets;

	}

	// La bulle est chargée à l'appui long : le placeholder est posé tout de suite
	// sous la pastille, le serveur le remplace par la liste. Un seul à la fois.
	static openShiftChoice(pill, track) {

		Series.closeShiftChoice();

		const placeholder = document.createElement('div');
		placeholder.id = 'series-choice';
		placeholder.className = 'series-item-timeline-choice series-item-timeline-choice-loading';
		track.append(placeholder);

		Series.placeShiftChoice(pill);

		new Ajax.Query()
			.method('get')
			.url('/series/task:following?id='+ pill.dataset.task)
			.fetch()
			.then(() => {
				if(qs('#series-choice') !== null) {
					Series.placeShiftChoice(pill);
				}
			});

	}

	static placeShiftChoice(pill) {

		const choice = qs('#series-choice');
		const track = pill.firstParent('.series-item-planning-timeline');

		if(choice === null || track === null) {
			return;
		}

		const zone = track.getBoundingClientRect();
		const box = pill.getBoundingClientRect();

		// Sous la pastille, sans sortir de la piste par la droite.
		let left = box.left - zone.left - 0.75 * 16;
		left = Math.max(0, Math.min(left, zone.width - choice.getBoundingClientRect().width - 4));

		// Sous la cible basse, pour ne pas la recouvrir.
		choice.style.left = left + 'px';
		choice.style.top = (box.bottom - zone.top + 34) + 'px';

	}

	static closeShiftChoice() {
		qsa('#series-choice', node => node.remove());
	}

	static shiftSelection(row, weeks, onFail) {

		// Le geste porte sur la ligne saisie. Si elle fait partie d'une sélection
		// cochée, le lot entier suit ; sinon elle est seule — sans quoi un glissé
		// sans case cochée ne poste rien et le serveur refuse le lot vide.
		const propre = row.qs('[name="batch[]"]');
		const ids = [];

		qsa('#series-wrapper [name="batch[]"]:checked', box => ids.push(box.value));

		if(propre && ids.includes(propre.value) === false) {
			ids.length = 0;
			ids.push(propre.value);
		}

		// Le lot se poste sous ids[], comme le fait le formulaire de la barre
		// d'actions de masse : la sérialisation d'objet ne produit pas cette forme.
		const body = new URLSearchParams();

		ids.forEach(id => body.append('ids[]', id));
		body.append('increment', weeks);

		const fail = () => onFail && onFail();

		new Ajax.Query(row)
			.url('/series/series:doIncrementPlannedCollection')
			.body(body)
			.fetch()
			.then(json => Series.refused(json) && fail(), fail);

	}

	// Une série peut changer de rang dans son espèce après n'importe quelle
	// écriture : le plan trie sur la semaine de départ, que le glissé d'une série,
	// celui d'un semis, le panneau d'une culture ou la barre de lot peuvent tous
	// déplacer. Plutôt que d'énumérer ces chemins — et d'en oublier —, le plan
	// garde la photo de sa disposition au rendu précédent et anime l'écart au
	// suivant. Toute cause est couverte par construction.
	static settlePositions(wrapper) {

		const origin = wrapper.getBoundingClientRect().top;
		const current = {};

		wrapper.qsa('.series-item-planning[id]', row => {
			current[row.id] = row.getBoundingClientRect().top - origin;
		});

		const before = Series.positions ?? null;

		Series.positions = current;

		if(before === null) {
			return;
		}

		// Mêmes lignes, ou rien : un changement de saison, de vue, de filtre ou une
		// série créée n'est pas un déplacement, et animer y produirait du bruit.
		const avant = Object.keys(before);
		const apres = Object.keys(current);

		if(avant.length !== apres.length || avant.some(id => current[id] === undefined)) {
			return;
		}

		const moving = [];

		apres.forEach(id => {

			const delta = before[id] - current[id];

			if(Math.abs(delta) > 2) {
				moving.push([wrapper.qs('[id="'+ id +'"]'), delta]);
			}

		});

		if(moving.length === 0) {
			return;
		}

		moving.forEach(([row, delta]) => {
			row.classList.add('series-item-moving');
			row.style.transition = 'none';
			row.style.transform = 'translateY('+ delta +'px)';
		});

		// Il faut que le navigateur ait PEINT la position de départ avant de la
		// relâcher, sinon la transition n'a rien à jouer. Un getBoundingClientRect()
		// ne force que le calcul de mise en page ; sur un plan de 250 lignes, le
		// rendu bloque le fil principal presque une seconde et les deux états sont
		// posés dans la même image. Deux images d'attente, et le trajet se voit.
		let released = false;

		const release = () => {

			if(released) {
				return;
			}

			released = true;

			moving.forEach(([row]) => {
				row.style.transition = '';
				row.style.transform = '';
				// Le voile de MainJuiciness.flash() et le repère d'une série neuve occupent tous deux le ::after de la ligne.
				if(row.classList.contains('series-item-planning-new') === false) {
					MainJuiciness.flash(row);
				}
			});

			setTimeout(() => {
				moving.forEach(([row]) => {
					row.classList.remove('series-item-moving');
					row.style.transform = '';
				});
			}, 450);

		};

		requestAnimationFrame(() => requestAnimationFrame(release));

		// Filet : dans un onglet en arrière-plan, requestAnimationFrame ne se
		// déclenche pas du tout. Sans lui les lignes resteraient décalées.
		setTimeout(release, 150);

	}

	// Décaler un itinéraire entier : toutes ses interventions bougent ensemble.
	static shiftSequence(sequence, weeks, context, onFail) {

		const body = new URLSearchParams();

		body.append('id', sequence);
		body.append('increment', weeks);

		new Ajax.Query(context)
			.url('/sequence/sequence:doIncrementWeek')
			.body(body)
			.fetch()
			.then(json => Series.refused(json) && onFail(), onFail);

	}

	static changeAllSelection(target) {

		// Une ligne masquée par le filtre n'est pas sélectionnée : le lot est ce que l'écran montre.
		qsa('#series-wrapper .series-item-planning-checkbox:not(:disabled)', node => node.checked = (target.checked && node.firstParent('.hide') === null));

		this.changeSelection();

	}

	static changePlantSelection(target, plantId) {

		qsa('#series-wrapper [data-plant="'+ plantId +'"][name="batch[]"]:not(:disabled)', node => node.checked = (target.checked && node.firstParent('.hide') === null));

		this.changeSelection();

	}

	static changeSelection() {

		return Batch.changeSelection('#batch-series', '#batch-series-one', function(selection) {

			// Une seule culture cochée : ses actions à elle pointent sur elle.
			if(selection.length === 1) {

				const single = selection[0];
				const link = (node, query) => {
					if(node !== null) {
						node.setAttribute('href', node.dataset.url + query);
					}
				};

				link(qs('#batch-series-one .batch-culture-update'), single.dataset.cultivation);
				link(qs('#batch-series-one .batch-culture-task'), '&series='+ single.dataset.series +'&cultivation='+ single.dataset.cultivation);
				link(qs('#batch-series-one .batch-culture-harvest'), '&series='+ single.dataset.series +'&cultivation='+ single.dataset.cultivation);

			}

			// Le framework ne teste les actions que sur la barre de groupe : le menu
			// d'une seule culture applique lui-même ses conditions (une pérenne ne se
			// duplique pas, une série ouverte ne se réouvre pas).
			if(selection.length === 1) {

				const accepted = (selection[0].dataset.batch || '').split(' ');

				qsa('#batch-series-one [data-batch-test]', node => {
					node.classList.toggle('hide', accepted.includes(node.dataset.batchTest) === false);
				});

			}
		});

	}

	static hideSelection() {

		qs('#batch-series').hide();
		qs('#batch-series-one')?.hide();

		qsa('#series-wrapper .series-item-planning-checkbox:checked', (field) => field.checked = false);

	}

	static selectCreateSeason(target) {
		qsa('#series-create-from input[name="season"]', node => node.value = target.value);
	}

	static refreshUpdateUse(target) {

		const form = target.firstParent('form');

		switch(target.value) {

			case 'block' :
				form.qs('.series-update-block', node => node.classList.remove('hide'));
				form.qs('.series-update-bed', node => node.classList.add('hide'));
				break;

			case 'bed' :
				form.qs('.series-update-block', node => node.classList.add('hide'));
				form.qs('.series-update-bed', node => node.classList.remove('hide'));
				break;

		}

	}

	static changeNameAuto(target) {

		target.dataset.auto = 'false';

	}

	static updateName() {

		const name = qs('#series-create-plant [name="name"]');

		if(name.dataset.auto === 'false') {
			return;
		}

		let defaultName = '';
		qsa('#series-create-plant [data-plant-name]', (plant) => defaultName += (defaultName === '' ? '' : ' + ') + plant.dataset.plantName);

		name.value = defaultName;

	}

	static deletePlant(target) {

		target.firstParent('.series-create-plant').remove();

		this.showOrHideDeletePlant();
		this.updateName();

	}

	static showOrHideDeletePlant() {

		const plants = qsa('#series-create-plant-list .series-create-plant').length;

		qsa('#series-create-plant-list .series-create-plant-delete', node => (plants > 1) ? node.classList.remove('hide') : node.classList.add('hide'));

	}

	static changeDuplicateCopies(target, increment) {

		const form = target.firstParent('form');
		const wrapper = target.firstParent('.series-duplicate-copies');
		const input = wrapper.qs('input');

		const value = parseInt(input.value) + increment;
		const limit = parseInt(wrapper.dataset.limit);

		if(value > 0 && value <= limit) {
			input.value = value;
			wrapper.qs('.series-duplicate-copies-value').innerHTML = value;
		} else {
			return;
		}

		wrapper.qsa('.series-duplicate-copies-disabled', node => node.classList.remove('series-duplicate-copies-disabled'));

		if(value === 1) {
			wrapper.qs('.series-duplicate-copies-minus').classList.add('series-duplicate-copies-disabled');
		}

		if(value >= limit) {
			wrapper.qs('.series-duplicate-copies-plus').classList.add('series-duplicate-copies-disabled');
		}

		const list = form.qs('.series-duplicate-list');

		list.dataset.copies = value;

		if(increment === 1) {

			list.qsa('.series-duplicate-one', node => {

				const lastCopy = node.qs('.series-duplicate-copy:last-child');
				const newContent = lastCopy.outerHTML;

				lastCopy.insertAdjacentHTML('afterend', newContent);

				const newLastCopy = node.qs('.series-duplicate-copy:last-child');

				newLastCopy.dataset.wrapper = 'series-'+ node.dataset.series +'-'+ (value - 1);

				newLastCopy.qs('input[name^="name"]').dataset.copy = value;
				newLastCopy.qs('input[name^="name"]').name = 'name['+ node.dataset.series +']['+ (value - 1) +']';
				newLastCopy.qs('input[name^="name"]').value = node.dataset.name.replace('@copy', value);

				newLastCopy.qs('input[name^="taskInterval"]').name = 'taskInterval['+ node.dataset.series +']['+ (value - 1) +']';
				newLastCopy.qs('input[name^="taskInterval"]').value = '';

				newLastCopy.qs('.series-duplicate-copy-number').innerHTML = value;


			});

		} else if(increment === -1) {

			list.qsa('.series-duplicate-one', node => node.qs('.series-duplicate-copy:last-child').remove());

		}

	}

	static toggleDuplicateInterval(target) {

		const list = target.firstParent('.series-duplicate-list');

		if(list.dataset.interval === '0') {

			list.qsa('input[name^="interval"]', node => node.value = '');
			list.dataset.interval = '1';

			target.qs('svg').renderOuter(Lime.Asset.icon('chevron-contract'));

		} else {

			list.dataset.interval = '0';

			target.qs('svg').renderOuter(Lime.Asset.icon('chevron-expand'));

		}

	}

	static changeDuplicateSeason(target) {

		const toSeason = parseInt(target.value);

		const form = target.firstParent('form');
		const fromSeason = parseInt(form.dataset.season);

		const timesheetField = form.qs('[data-field="copyTimesheet"]');
		const timesheetWrapper = form.qs('[data-wrapper="copyTimesheet"]');
		const harvestField = form.qs('input[data-fqn="recolte"]');
		const harvestWrapper = harvestField?.firstParent('label');
		const seasonInfo = form.qs('.series-duplicate-season');

		if(toSeason !== fromSeason) {

			seasonInfo.classList.remove('hide');

			timesheetWrapper?.classList.add('hide');
			if(timesheetField) {
				timesheetField.qs('input[value="0"]').checked = true;
			}

			harvestWrapper?.classList.add('hide');
			if(harvestField) {
				harvestField.checked = false;
			}

		} else {

			seasonInfo.classList.add('hide');

			timesheetWrapper?.classList.remove('hide');
			harvestWrapper?.classList.remove('hide');

		}

	}

	static updateArea(target) {

		const form = target.firstParent('form');

		const use = form.qs('input[name="use"]:checked').value;
		const bedWidth = parseInt(form.qs('input[name="bedWidth"]').value) || null;
		const alleyWidth = parseInt(form.qs('input[name="alleyWidth"]').value) || 0;

		let area, length;

		switch(use) {

			case 'block' :
				area = parseInt(form.qs('input[name="areaTarget"]').value || null);
				length = null;
				break;

			case 'bed' :

				length = parseInt(form.qs('input[name="lengthTarget"]').value) || null;

				if(length === null || bedWidth === null) {
					area = null;
				} else {
					area = length * (bedWidth + alleyWidth) / 100;
				}

				break;

		}

		qsa('.cultivation-write', node => {

			node.dataset.area = area;
			node.dataset.length = length;

			Cultivation.updateDensity(node);

		})

	}

	static initNumber(table) {

		if(table.dataset.sortReady === '1') {
			return;
		}

		table.dataset.sortReady = '1';

		table.qsa('[data-number-group]', group => {

			new Sortable(group, {
				handle: '.util-drag-handle',
				draggable: 'tr',
				ghostClass: 'util-drag-ghost',
				chosenClass: 'util-drag-chosen',
				forceFallback: true,
				fallbackOnBody: true,
				// Le fantôme est un clone sorti du tableau, dont les cellules perdent la largeur de leur
				// colonne : elle se fige au choix de la ligne, que Sortable dispatche avant de cloner.
				onChoose: e => Series.freezeNumberCells(e.item, true),
				onUnchoose: e => Series.freezeNumberCells(e.item, false),
				onEnd: () => Series.renumber(table)
			});

		});

	}

	static freezeNumberCells(row, freeze) {

		const cells = [];
		const widths = [];

		row.qsa('td', cell => cells.push(cell));
		cells.forEach(cell => widths.push(freeze ? cell.getBoundingClientRect().width + 'px' : ''));
		cells.forEach((cell, index) => cell.style.width = widths[index]);

	}

	static renumber(table) {

		const numbered = (table.dataset.numbered !== '0');

		table.qsa('[data-number-group]', group => {

			let rank = 0;

			group.qsa('input[data-bare]', input => {

				rank++;

				if(input.dataset.edited !== '1') {
					input.value = numbered ? input.dataset.bare +' '+ rank : input.dataset.bare;
				}

			});

		});

		Series.labelNumber(table);

	}

	static labelNumber(table) {

		const submit = table.firstParent('form').qs('button[type="submit"]');
		const rename = (table.dataset.numbered === '0' || table.qs('input[data-edited="1"]') !== null);

		submit.textContent = rename ? submit.dataset.labelRename : submit.dataset.labelNumber;

	}

}

document.delegateEventListener('click', '[data-action="series-number-toggle"]', function(e) {

	const table = this.firstParent('form').qs('.series-number');
	const numbered = (table.dataset.numbered === '0');

	table.dataset.numbered = numbered ? '1' : '0';
	table.qsa('input[data-edited]', input => delete input.dataset.edited);

	this.textContent = numbered ? this.dataset.labelRemove : this.dataset.labelRestore;

	Series.renumber(table);

});

document.delegateEventListener('input', '.series-number input[data-bare]', function(e) {

	this.dataset.edited = '1';

	Series.labelNumber(this.firstParent('.series-number'));

});

document.delegateEventListener('click', '[data-action="series-close-note"]', function(e) {

	const row = this.firstParent('.util-table-row');

	const comment = row.qs('.series-close-lot-comment');

	comment.removeHide();
	comment.qs('[contenteditable]').focus();
	this.parentElement.hide();

});
