// Rien ne se complète tout seul : envoyer un jardin sur une destination déjà prise fait passer
// celui qui la visait à « À définir », qu'il faudra choisir à la main. Rien n'est posté avant le bouton.
document.delegateEventListener('change', '.garden-chairs select', function() {

	const form = this.firstParent('form');

	if(this.value !== '') {

		form.qsa('.garden-chairs select').forEach(select => {

			if(select !== this && select.value === this.value) {
				select.value = '';
			}

		});

	}

	SeriesGardenChairs.refresh(form);

});

document.delegateEventListener('click', '[data-action="garden-chairs-cancel"]', function() {

	const form = this.firstParent('form');

	form.qsa('.garden-chairs select').forEach(select => select.value = select.dataset.plot);

	SeriesGardenChairs.refresh(form);

});

// Chaque jardin de la parcelle vers le suivant dans l'ordre affiché, le dernier vers le premier.
document.delegateEventListener('click', '[data-action="garden-chairs-shift"]', function() {

	const form = this.firstParent('form');
	const zone = this.firstParent('.garden-chairs-zone');

	// Sans intertitre, le groupe n'a qu'une parcelle et le bouton est dans le deck du groupe.
	const list = zone ? zone.nextElementSibling : form.qs('.garden-chairs-list');
	const decks = list.qsa('.util-deck[data-plot]');

	decks.forEach((deck, position) => {
		deck.qs('select').value = decks[(position + 1) % decks.length].dataset.plot;
	});

	SeriesGardenChairs.refresh(form);

});

class SeriesGardenChairs {

	static init(form) {
		this.refresh(form);
	}

	static refresh(form) {

		const list = form.qs('.garden-chairs');
		const states = list.qs('.garden-chairs-states');

		const seriesMoving = new Set();
		const seriesPerennial = new Set();
		const seriesPerennialNames = [];
		let moved = 0;
		let missing = 0;
		let still = 0;
		let watch = 0;

		list.qsa('.util-deck[data-plot]').forEach(deck => {

			const to = deck.qs('select').value;

			let state = null;
			let watchAfter = 0;

			// Un jardin à « À définir » fait partie du brouillon, mais ne compte dans la pesée qu'une fois complété.
			if(to === '') {
				state = 'missing';
				missing++;
			} else if(to !== deck.dataset.plot) {

				moved++;

				watchAfter = JSON.parse(deck.dataset.watch)[to];
				watch += watchAfter;

				// Un jardin dont aucune série ne part n'a rien à dire de la rotation : son menu compte
				// dans le déplacement, mais il ne reçoit ni bande ni accent.
				if(deck.dataset.seriesMoving !== '') {
					state = (watchAfter > 0) ? 'watch' : 'none';
				}

				this.split(deck.dataset.seriesMoving).forEach(id => seriesMoving.add(id));
				Object.entries(JSON.parse(deck.dataset.seriesPerennial)).forEach(([id, name]) => {
					seriesPerennial.add(id);
					seriesPerennialNames.push(name);
				});

			} else {
				still++;
			}

			deck.classList.toggle('util-deck-accent-warning', state === 'watch');
			deck.classList.toggle('util-deck-accent-success', state === 'none');
			deck.classList.toggle('util-deck-accent-todo', state === 'missing');

			deck.qs('.util-deck-band-state', band => band.remove());

			this.ring(deck, (state === 'watch') ? JSON.parse(deck.dataset.returns)[to].plants : {});

			if(state !== null) {

				const band = states.content.querySelector('[data-state="'+ state +'"]').cloneNode(true);

				if(state === 'watch') {
					band.qs('span').textContent = JSON.parse(deck.dataset.returns)[to].text;
				}

				deck.append(band);

			}

		});

		const counters = {
			missing: missing,
			series: seriesMoving.size,
			perennial: seriesPerennial.size,
			watch: watch,
			still: still
		};

		form.qsa('[data-counter]').forEach(counter => {

			const value = counters[counter.dataset.counter];

			counter.textContent = this.plural(counter.dataset.singular, counter.dataset.plural, value).replace('{list}', seriesPerennialNames.join(', '));

			// Une ligne à zéro ne dit rien et disparaît, sauf celle des séries : à zéro, elle dit que
			// le déplacement ne changera rien.
			if(counter.dataset.counter !== 'series') {
				counter.firstParent('li').classList.toggle('hide', value === 0);
			}

		});

		// Ouverte, la pesée ne se replie plus avant le rechargement : la replier sous Annuler
		// raccourcirait la page et déplacerait le défilement.
		if(moved + missing > 0) {
			form.qs('.garden-chairs-draft').classList.remove('hide');
		}

		const ready = (missing === 0 && moved > 0);

		const submit = form.qs('[type="submit"]');
		submit.textContent = ready ? submit.dataset.label.replace('{value}', moved) : submit.dataset.labelBlocked;
		submit.disabled = (ready === false);
		// L'attribut bloque le bouton, la classe le montre : .btn ne peint pas :disabled (component.css).
		submit.classList.toggle('disabled', ready === false);

	}

	// L'anneau désigne les espèces dont la famille revient trop tôt sur la planche d'arrivée de même rang.
	static ring(deck, plants) {

		deck.qsa('.garden-chairs-bed').forEach((bed, rank) => {

			bed.qsa('.garden-chairs-plant').forEach(plant => {

				plant.dataset.title ??= plant.title;

				const gap = (plants[rank] ?? {})[plant.dataset.plant];

				plant.classList.toggle('is-returning', gap !== undefined);
				plant.title = (gap !== undefined) ? plant.dataset.title +' '+ gap : plant.dataset.title;

			});

		});

	}

	static plural(singular, plural, value) {
		return (value > 1 ? plural : singular).replace('{value}', value);
	}

	static split(value) {
		return value === '' ? [] : value.split(',');
	}

}
