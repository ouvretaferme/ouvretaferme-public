/**
 * Le départ des paniers de l'année (`CompositionStartUi`) : le composant d'accueil
 * (`main/onboarding.js`) porte les étapes, ce fichier l'aperçu et ce qui est propre aux paniers.
 *
 * L'aperçu relit les champs à chaque saisie et ne garde aucun état : ce qu'il écrit est toujours ce
 * que le formulaire enverrait. Les phrases arrivent traduites dans `data-texts`, avec `__value__` à
 * remplacer, au singulier puis au pluriel.
 *
 * Il naît vide et ne montre que ce qui a été saisi (skill ui, composant onboarding) : une taille
 * n'y entre qu'avec son nom, son prix ou son nombre de paniers, une carte sans rien à montrer reste
 * masquée, et aucune phrase ne dit « zéro » ou « aucune » à la place d'une réponse.
 */
class ToolboxCompositionStart extends MainOnboarding {

	static init() {
		this.index = this.root.qsa('.toolbox-composition-start-formula').length;
	}

	static plural(forms, value) {
		return forms[value > 1 ? 1 : 0].replace('__value__', this.number(value));
	}

	static number(value) {
		return new Intl.NumberFormat('fr-FR').format(value);
	}

	static money(value) {
		return new Intl.NumberFormat('fr-FR', {style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2}).format(value);
	}

	// Une saisie à la française, virgule décimale comprise ; ce qui ne se lit pas compte pour rien.
	static read(input) {

		const value = parseFloat(input.value.replace(',', '.').replace(/\s/g, ''));

		return Number.isFinite(value) ? value : null;

	}

	static getWeeks() {
		return [...this.form.qsa('[name="weeks[]"]:checked')].map(input => parseInt(input.value));
	}

	static renderPreview() {

		const texts = this.texts;
		const weeks = this.getWeeks();

		let baskets = 0;
		let formulas = '';

		this.root.qsa('.toolbox-composition-start-formula', row => {

			const name = row.qs('[name^="name["]').value.trim();
			const price = this.read(row.qs('[name^="price["]'));
			const count = this.read(row.qs('[name^="baskets["]'));

			baskets += (count === null) ? 0 : count;

			if(name === '' && price === null && count === null) {
				return;
			}

			formulas += '<div class="toolbox-composition-start-size">';
				formulas += '<b>'+ name.encode() +'</b>';
				formulas += '<span>'+ (price === null ? '' : this.money(price)) +'</span>';
				formulas += '<span class="util-annotation">'+ (count === null ? '' : this.plural(texts.basketsWeek, count)) +'</span>';
			formulas += '</div>';

		});

		qs('#toolbox-composition-start-preview-formulas').innerHTML = formulas;

		const period = this.getPeriod(weeks);

		qs('#toolbox-composition-start-weeks').innerHTML = (weeks.length === 0) ? texts.noWeek : period;
		qs('#toolbox-composition-start-preview-weeks').innerHTML = period;

		this.renderYear(weeks);

		const pending = this.renderSpecies();

		let summary = '';

		if(baskets > 0) {
			if(this.step === 1) {
				summary = this.plural(texts.basketsWeek, baskets);
			} else if(weeks.length > 0) {
				summary = this.plural(texts.basketsYear, baskets * weeks.length);
			}
		}

		qs('#toolbox-composition-start-summary').innerHTML = summary;

		this.root.qsa('[data-preview-step]', node => node.hidden = (parseInt(node.dataset.previewStep) > this.step));

		qs('#toolbox-composition-start-preview-formulas-card').hidden = (formulas === '');

		if(weeks.length === 0) {
			qs('#toolbox-composition-start-preview-weeks-card').hidden = true;
		}

		// Sans climat choisi, aucune espèce n'est encore montrée : la carte n'a rien à chiffrer.
		if(pending) {
			qs('#toolbox-composition-start-preview-grown-card').hidden = true;
		}

		// Le titre ne paraît qu'avec la première carte, jamais seul au-dessus d'une colonne vide
		// (skill ui, composant onboarding). `hide` et non `hidden` : `.onboarding-preview-header` pose
		// `display: flex`, qui l'emporte sur l'attribut.
		qs('#toolbox-composition-start-preview-header').classList.toggle('hide', [...this.root.qs('.onboarding-preview-body').children].every(node => node.hidden));

	}

	static getPeriod(weeks) {

		const texts = this.texts;

		if(weeks.length === 0) {
			return '';
		}

		return texts.period
			.replace('__weeks__', this.plural(texts.weeks, weeks.length))
			.replace('__from__', texts.days[Math.min(...weeks)][0])
			.replace('__to__', texts.days[Math.max(...weeks)][1]);

	}

	// Les plages du rail, une par suite de semaines livrées, dans le repère que pose
	// `CompositionUi::getYear()` : la graduation compte une case par semaine de la saison.
	static renderYear(weeks) {

		const track = this.root.qs('.toolbox-composition-rail > .util-rail-track');
		const days = Object.keys(this.texts.days).map(week => parseInt(week));
		const minimum = Math.min(...days);
		const span = Math.max(...days) - minimum + 1;

		track.qsa('.util-rail-range', range => range.remove());

		const sorted = [...weeks].sort((a, b) => a - b);

		for(let index = 0; index < sorted.length; index++) {

			const start = sorted[index];

			while(index + 1 < sorted.length && sorted[index + 1] === sorted[index] + 1) {
				index++;
			}

			const stop = sorted[index];

			const range = document.createElement('i');
			range.className = 'util-rail-range';
			range.style.left = ((start - minimum) / span * 100) +'%';
			range.style.width = ((stop - start + 1) / span * 100) +'%';

			track.append(range);

		}

	}

	// Un autre plan de culture choisit les espèces livrées comme le départ les choisit côté serveur
	// (`CompositionPlanLib::getForStart()`) : ses espèces, ou quand il est vide celles des ventes,
	// que `data-sold` ne porte qu'au-delà du seuil, ou toutes.
	static checkCultivated(select) {

		const option = select.options[select.selectedIndex];
		const cultivated = (option.dataset.plants === '') ? [] : option.dataset.plants.split(' ');
		const sold = (select.dataset.sold === '') ? [] : select.dataset.sold.split(' ');
		const plants = (cultivated.length > 0) ? cultivated : sold;

		const list = this.root.qs('.toolbox-composition-calendar-list');

		this.root.qsa('.toolbox-composition-species-start', row => {
			row.qs('[name^="status["]').value = (plants.length === 0 || plants.includes(row.dataset.plant)) ? list.dataset.statusOn : list.dataset.statusOff;
		});

		this.renderPreview();

	}

	static remove(row) {

		row.qs('[name^="status["]').value = this.root.qs('.toolbox-composition-calendar-list').dataset.statusOff;

		this.renderPreview();

	}

	// Le rang qu'ajoute le panneau « Ajouter une espèce » (r48e) arrive hors du formulaire
	// (`toolbox-composition-start-added`) : il prend sa place dans l'ordre alphabétique de la liste, à
	// la place du rang de la même espèce quand la corbeille l'avait retirée. Rien n'est écrit, il part
	// avec le formulaire.
	static insert() {

		const row = qs('#toolbox-composition-start-added').firstElementChild;
		const list = this.root.qs('.toolbox-composition-calendar-list');

		list.qsa('.toolbox-composition-species-start[data-plant="'+ row.dataset.plant +'"]', node => node.remove());

		const next = [...list.querySelectorAll('.toolbox-composition-species-start')]
			.find(node => node.dataset.name.localeCompare(row.dataset.name, 'fr', {sensitivity: 'base'}) > 0);

		if(next === undefined) {
			list.append(row);
		} else {
			list.insertBefore(row, next);
		}

		this.renderPreview();

	}

	// L'ordre dans lequel une espèce prend sa saison sous le choix en cours (`data-sources`,
	// `CompositionPlanLib::getStartSources()`), `null` tenant la place du climat choisi. La source se
	// lit sur le formulaire : sans plan de culture, elle n'a pas de tuile et part d'un champ caché.
	static getSources() {

		const section = this.root.qs('.toolbox-composition-start-cultures');
		const reference = new FormData(this.form).get('reference');
		const climate = this.form.qs('[name="climate"]').value;

		return JSON.parse(section.dataset.sources)[reference].map(source => (source === null) ? climate : source);

	}

	// La liste suit ce que les champs portent : un rang se montre tant que son champ ne dit pas
	// « retirée », et dessine la saison de la première source qui date son espèce, ou sans elle le
	// dessin qui n'a pas de source : douze barres vides, ou la saison qu'une espèce ajoutée que rien ne
	// date a reçue des poignées de son panneau (r67). La liste ne règle aucune saison (r57, r58).
	// Sous le calendrier sans climat choisi, il n'y a aucune saison à montrer, donc ni liste ni envoi
	// (`is-pending`).
	//
	// « Ajouter une espèce » ouvre son panneau sur ce que le formulaire montre : la saison, la source,
	// le climat et les espèces livrées voyagent dans son adresse, récrite ici à chaque changement.
	static renderSpecies() {

		const texts = this.texts;
		const sources = this.getSources();
		const off = this.root.qs('.toolbox-composition-calendar-list').dataset.statusOff;

		const pending = sources.includes('');

		this.root.qs('.toolbox-composition-start-cultures').classList.toggle('is-pending', pending);

		const delivered = [];

		this.root.qsa('.toolbox-composition-species-start', row => {

			const isOff = (row.qs('[name^="status["]').value === off);

			if(isOff === false) {
				delivered.push(row.dataset.plant);
			}

			row.classList.toggle('is-off', isOff);

			const drawings = [...row.querySelectorAll('.toolbox-composition-start-season > .toolbox-composition-calendar-source')];
			const shown = sources
				.map(source => drawings.find(drawing => drawing.dataset.source.split(' ').includes(source)))
				.find(drawing => drawing !== undefined) ?? drawings.find(drawing => drawing.dataset.source === '');

			drawings.forEach(drawing => drawing.classList.toggle('hide', drawing !== shown));

		});

		qs('#toolbox-composition-start-count').innerHTML = this.number(delivered.length);

		const add = qs('#toolbox-composition-start-species');
		const data = new FormData(this.form);

		add.href = add.dataset.url +'&'+ new URLSearchParams({
			season: data.get('season'),
			reference: data.get('reference'),
			climate: data.get('climate'),
			delivered: delivered.join(',')
		});

		qs('#toolbox-composition-start-preview-grown').innerHTML = texts.grown
			.replace('__value__', this.number(delivered.length))
			.replace('__total__', this.number(this.root.qsa('.toolbox-composition-species-start').length));

		return pending;

	}

}

document.delegateEventListener('click', '#toolbox-composition-start [data-action="toolbox-composition-start-add"]', function() {

	const row = ToolboxCompositionStart.addRow(qs('#toolbox-composition-start-template'), qs('#toolbox-composition-start-formulas'));

	row.qs('[name^="name["]').focus();

	ToolboxCompositionStart.renderPreview();

});

document.delegateEventListener('click', '#toolbox-composition-start [data-onboarding-remove-row]', function() {
	ToolboxCompositionStart.renderPreview();
});

document.delegateEventListener('change', '#toolbox-composition-start [data-action="toolbox-composition-start-cultivation"]', function() {

	const url = this.options[this.selectedIndex].dataset.url;

	if(url === undefined) {
		ToolboxCompositionStart.checkCultivated(this);
	} else {
		new Ajax.Navigation()
			.url(url)
			.fetch();
	}

});

document.delegateEventListener('click', '#toolbox-composition-start [data-action="toolbox-composition-start-remove"]', function() {
	ToolboxCompositionStart.remove(this.closest('.toolbox-composition-species-start'));
});

// Le choix d'une tuile passe par le délégué `input` du composant d'accueil, qui n'écoute que les
// champs `input` : un menu a le sien.
document.delegateEventListener('change', '#toolbox-composition-start [data-action="toolbox-composition-start-climate"]', function() {
	ToolboxCompositionStart.renderPreview();
});

// La case d'un mois et « Toute l'année » cochent leurs semaines par `CheckboxField.all()`, qui
// n'émet aucun événement, et par l'`onclick` du champ, qui passe après ce délégué : l'aperçu se
// redessine donc à l'image suivante.
document.delegateEventListener('click', '#toolbox-composition-start .toolbox-composition-weeks .util-label input', function() {
	requestAnimationFrame(() => ToolboxCompositionStart.renderPreview());
});
