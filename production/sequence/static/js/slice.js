document.delegateEventListener('click', 'a[data-action="slice-add"]', function(e) {

	const id = this.getAttribute('data-id');
	const wrapper = qs(id);

	const clone = qs(id +' .slice-spare').firstChild.cloneNode(true);
	clone.qsa('[name*="spare"]', node => node.setAttribute('name', node.getAttribute('name').replace('spare', 'variety')));

	clone.qsa('[data-autocomplete-url]', node => {

		node.id = node.id +'-'+ Date.now() + Math.floor(Math.random() * 1000);

		node.removeAttribute('data-autocomplete');

	});

	wrapper.qs('.slice-items > *:last-child').insertAdjacentElement('beforebegin', clone);

	clone.parseRender();

});

document.delegateEventListener('click', 'a[data-action="slice-unit"]', function(e) {

	Slice.show(e.target);

});

document.delegateEventListener('click', 'a[data-action="slice-fair"]', function(e) {

	const wrapper = qs(this.getAttribute('data-id'));

	Slice.divide(wrapper);

});

document.delegateEventListener('click', 'a[data-action="slice-remove"]', function(e) {

	const wrapper = this.firstParent('.slice-wrapper');

	this.parentElement.remove();

	Slice.updateSum(wrapper);

});

document.delegateEventListener('input', '.slice-items input[name*=varietyPart]', function(e) {

	const wrapper = this.firstParent('.slice-wrapper');

	Slice.updateSum(wrapper);

});

class Slice {

	static startVariety(input) {

		// Capturés ici et non au moment de l'événement : en plein écran (mobile), le widget déplace
		// le champ hors de sa ligne le temps de la saisie, et remonter le DOM depuis lui ne trouve
		// plus rien.
		const fieldSelect = input.firstParent('.slice-item-select');
		const fieldId = () => fieldSelect.qs('[data-ref="slice-variety-id"]');
		const fieldCreate = () => fieldSelect.qs('[data-ref="slice-variety-create"]');

		// Le nom en place ne s'efface pas au clic : il reste affiché et sélectionné, pour être
		// remplacé d'un coup en tapant ou retouché à la main.
		input.addEventListener('focusin', () => {

			delete input.dataset.typed;

			input.dataset.previous = input.value;
			input.select();

		});

		input.addEventListener('input', () => input.dataset.typed = input.value);

		// Tant que rien n'est tapé, le nom en place n'est pas une recherche : sans cela, la liste
		// ouverte au clic se réduit à la variété déjà choisie.
		input.addEventListener('autocompleteBeforeQuery', e => {

			if(input.dataset.typed === undefined) {
				e.detail.query = '';
			}

		});

		// Un nom tapé qui ne correspond à rien de la liste n'est pas perdu : il reste affiché et la
		// variété se crée à l'enregistrement. Si un élément de la liste est cliqué, le focusout passe
		// avant l'événement de sélection, qui reprend la main ci-dessous.
		input.addEventListener('focusout', () => {

			const typed = (input.dataset.typed ?? '').trim();

			delete input.dataset.typed;

			if(typed === '') {
				input.value = input.dataset.previous ?? '';
				return;
			}

			input.value = typed;
			input.dataset.previous = typed;

			fieldId().value = 'new';
			fieldCreate().value = typed;

		});

		// Le choix l'emporte sur ce qui était tapé, que le focusout passe avant lui (clic dans la
		// liste, écran tactile) ou après (choix au clavier, le curseur reste dans le champ) : la
		// recherche tapée s'oublie ici, sans quoi le focusout suivant la réécrirait par-dessus.
		input.addEventListener('autocompleteSelect', e => {

			delete input.dataset.typed;

			input.value = e.detail.itemText;
			input.dataset.previous = e.detail.itemText;

			fieldId().value = e.detail.id;
			fieldCreate().value = (e.detail.id === 'new') ? e.detail.itemText : '';

		});

	}

	static updateCultivation(wrapper, use, density) {

		const area = parseInt(wrapper.dataset.area) || null;
		const length = parseInt(wrapper.dataset.length) || null;
		const varietiesUnit = wrapper.qs('input[name^="varietiesUnit"]').value;

		// La surface porte déjà le passe-pied, en blocs comme en planches : le nombre de plants
		// ne dépend donc pas de l'implantation, seules les unités proposées en dépendent.
		const plants = (area !== null && density !== null) ? Math.round(area * density) : null;

		switch(use) {

			case 'block' :

				wrapper.qs('[data-action="slice-unit"][data-unit="length"]').classList.add('hide');

				wrapper.qs('[data-action="slice-unit"][data-unit="area"]', node => {

					node.classList.remove('hide');

					if(varietiesUnit === 'length') {
						Slice.show(node);
					}

				});

				break;

			case 'bed' :

				wrapper.qs('[data-action="slice-unit"][data-unit="length"]', node => {

					node.classList.remove('hide');

					if(varietiesUnit === 'area') {
						Slice.show(node);
					}

				});

				wrapper.qs('[data-action="slice-unit"][data-unit="area"]').classList.add('hide');

				break;

		}

		wrapper.qs('.slice-item-limit[data-unit="length"] .slice-item-max').innerHTML = length ?? '';
		wrapper.qs('.slice-item-limit[data-unit="area"] .slice-item-max').innerHTML = area ?? '';


		wrapper.qs('.slice-item-limit[data-unit="plant"] .slice-item-max').innerHTML = plants ?? '';

		wrapper.qsa('.slice-item-limit[data-unit="tray"]', node => {

			const trays = (plants !== null) ? Math.ceil(plants / parseInt(node.dataset.value)) : null;

			node.qs('.slice-item-max').innerHTML = trays;

		});

		Slice.updateSum(wrapper.qs('.slice-wrapper'));

	}

	static show(target) {
	
		const id = target.getAttribute('data-id');

		const wrapper = qs(id);

		const currentLimit = this.getLimit(wrapper);
		const currentVarieties = this.getVarietiesParts(wrapper);

		const newUnit = target.dataset.unit;
		const newTool = (target.dataset.tool === undefined) ? '' : target.dataset.tool;

		wrapper.qs('[name*="varietiesUnit"]').value = newUnit;
		wrapper.qs('[name*="varietiesTool"]').value = newTool;

		const newSelector = '[data-unit="'+ newUnit +'"]'+ (newTool !== '' ? '[data-tool="'+ newTool +'"]' : '');

		wrapper.qsa('.slice-item-part', node => node.classList.add('hide'));
		wrapper.qsa('.slice-item-part'+ newSelector, node => node.classList.remove('hide'));

		wrapper.qsa('.slice-item-limit', node => node.classList.add('hide'));
		wrapper.qsa('.slice-item-limit'+ newSelector, node => node.classList.remove('hide'));

		wrapper.qsa('[data-action="slice-unit"]', node => node.classList.remove('selected'));
		wrapper.qsa('[data-action="slice-unit"]'+ newSelector, node => node.classList.add('selected'));

		wrapper.qs('.slice-unit-dropdown').innerHTML = target.dataset.label;

		this.convertSum(
			wrapper,
			currentLimit,
			currentVarieties,
			this.getLimit(wrapper),
			this.getVarietiesParts(wrapper)
		);

		this.updateSum(wrapper);

		target.parentElement.qs('.dropdown-item.selected', node => node.classList.remove('selected'));
		target.classList.add('selected');

	}

	static divide(wrapper) {

		const cake = this.getMax(wrapper);

		const slices = this.getVarietiesParts(wrapper);
		const part = Math.floor(cake / slices.length);
		let rest = cake - part * slices.length;

		slices.forEach(slice => {

			slice.value = part + (rest > 0 ? 1 : 0);
			slice.dispatchEvent(new Event("input"));
			rest--;

		});

		this.updateSum(wrapper);

	}

	static updateSum(wrapper) {

		const unit = this.getUnit(wrapper);
		const limit = this.getLimit(wrapper);

		const sum = this.calculateSum(wrapper);

		limit.qs('.slice-item-sum').innerHTML = sum;

		// Excès de répartition
		let newColor = null;

		if(unit === 'percent') {

			if(sum > 100) {
				newColor = 'color-danger';
			} else if(sum < 100) {
				newColor = 'color-warning';
			} else {
				newColor = 'color-success';
			}

		} else if(limit.qs('.slice-item-max').innerHTML !== '') {

			const max = parseInt(limit.qs('.slice-item-max').innerHTML);

			if(sum > max) {
				newColor = 'color-danger';
			} else if(sum < max) {
				newColor = 'color-warning';
			} else {
				newColor = 'color-success';
			}

		}

		limit.classList.remove('color-danger');
		limit.classList.remove('color-warning');
		limit.classList.remove('color-success');

		if(newColor !== null) {
			limit.classList.add(newColor);
		}

	}

	static getUnit(wrapper) {
		return wrapper.qs('[name*="varietiesUnit"]').value;
	}

	static getTool(wrapper) {
		return wrapper.qs('[name*="varietiesTool"]').value;
	}

	static getSum(wrapper) {
		return this.getLimit(wrapper).qs('.slice-item-sum');
	}

	static getMax(wrapper) {

		const unit = this.getUnit(wrapper);

		if(unit === 'percent') {
			return 100;
		} else {

			const max = this.getLimit(wrapper).qs('.slice-item-max');

			return max.innerHTML !== '' ? parseInt(max.innerHTML) : 0;

		}

	}

	static getLimit(wrapper) {
		const unit = this.getUnit(wrapper);
		const tool = this.getTool(wrapper);
		return wrapper.qs('.slice-item-limit[data-unit="'+ unit +'"]'+ (tool !== '' ? '[data-tool="'+ tool +'"]' : ''));
	}

	static getVarietiesParts(wrapper) {
		const unit = this.getUnit(wrapper);
		const tool = this.getTool(wrapper);
		return wrapper.qsa('.slice-items .slice-item-part[data-unit="'+ unit +'"]'+ (tool !== '' ? '[data-tool="'+ tool +'"]' : '') +' input[name*=varietyPart]');
	}

	static calculateSum(wrapper) {

		let sum = 0;

		this.getVarietiesParts(wrapper).forEach(node => {
			const value = parseFloat(node.value);
			if(isNaN(value) === false) {
				sum += value;
			}
		});

		return sum;

	}

	// Mise à jour des valeurs par défaut quand on passe d'une unité à une autre
	static convertSum(wrapper, currentLimit, currentVarieties, newLimit, newVarieties) {

		const newSum = parseFloat(newLimit.qs('.slice-item-sum').innerHTML);

		if(newSum > 0) {
			return;
		}

		const currentSum = parseFloat(currentLimit.qs('.slice-item-sum').innerHTML);

		if(currentSum === 0) {
			return;
		}

		const newMax = this.getMax(wrapper);

		let newCalculatedSum = 0;

		currentVarieties.forEach((currentRange, key) => {

			const newRange = newVarieties.item(key);

			const newValue = Math.floor((currentRange.value / currentSum) * newMax);

			newCalculatedSum += newValue;
			newRange.value = newValue;

		});

		// Distribution des arrondis manquants
		for(let key = 0; key < (newMax - newCalculatedSum); key++) {
			newVarieties.item(key).value = parseInt(newVarieties.item(key).value) + 1;
		}

		newVarieties.forEach(newRange => {
			newRange.dispatchEvent(new Event("input"));
		});


	}

}
