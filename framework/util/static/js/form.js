/**
 * Form submission
 */
document.delegateEventListener('submit', 'form[data-ajax-form]', function(e) {

	e.preventDefault();
	e.stopImmediatePropagation();

	submitAjaxForm(this, e.submitter);

});

function submitAjaxForm(form, submitter) {

	const method = (form.getAttribute('method') ?? 'POST').toUpperCase();

	const className = form.hasAttribute('data-ajax-class') ?
		eval(form.getAttribute('data-ajax-class')) :
		Ajax.Navigation;

	const body = form.form();
	let url = form.getAttribute('data-ajax-form');

	const enable = startWaiter(submitter);

	// Un recalcul posé sur le formulaire ne doit pas bloquer son envoi.
	const object = new className(form).lockAs('submit');

	if(submitter) {
		object.trigger(submitter);
	}

	if(form.dataset.ajaxDownload !== undefined) {
		object.download(form.dataset.ajaxDownload);
	}

	switch(method) {

		case 'GET' :

			url += url.includes('?') ? '&' : '?';
			url += new URLSearchParams(body).toString();

			object
				.method(method)
				.url(url)
				.fetch()
				.then(() => enable(), () => enable());

			break;

		case 'POST' :

			object
				.method(method)
				.url(url)
				.skipHistory()
				.body(body)
				.fetch()
				.then(() => {
					// Uniquement sur succès : un POST refusé ou une coupure réseau
					// doivent laisser le brouillon en place
					EditorField.flushDraft(form);
					enable();
				}, () => enable());

			break;

		default :
			throw 'Method should be GET or POST';

	}

}

document.delegateEventListener('click', '[data-ajax-submit]', function(e) {

	e.preventDefault();
	e.stopImmediatePropagation();

	const url = this.getAttribute('data-ajax-submit');
	const form = this.hasAttribute('data-ajax-target') ? qs(this.getAttribute('data-ajax-target')) : this.firstParent('form');

	if(form.dataset.ajaxSubmitMethod === undefined) {
		form.dataset.ajaxSubmitMethod = form.getAttribute('method');
	}

	if(form === null) {
		throw "No form found for data-ajax-submit";
	}

	form.qsa('[data-form-zombie]', node => node.remove());

	if(this.dataset.ajaxBody !== undefined) {

		JSON.parse(this.dataset.ajaxBody).forEach(([key, value]) => {
			form.insertAdjacentHTML('afterbegin', '<input type="hidden" '+ 'name'.attr(key) +' '+ 'value'.attr(value) +' data-form-zombie="1"/>');
		});

	} else {

		this.post().forEach((value, key) => {
			form.insertAdjacentHTML('afterbegin', '<input type="hidden" '+ 'name'.attr(key) +' '+ 'value'.attr(value) +' data-form-zombie="1"/>');
		});

	}

	if(this.hasAttribute('data-ajax-method')) {
		form.setAttribute('method', this.getAttribute('data-ajax-method'));
	} else if(form.dataset.ajaxSubmitMethod !== undefined) {
		form.setAttribute('method', form.dataset.ajaxSubmitMethod);
	}

	if(this.dataset.ajaxNavigation === 'never') {
		form.setAttribute('action', url);
		form.submit();
	} else {
		form.setAttribute('action', 'javascript:;');
		form.setAttribute('data-ajax-form', url);
		form.dispatchEvent(new CustomEvent("submit"));
	}


});

document.delegateEventListener('click', '[data-clear]', function(e) {

	const field = this.previousElementSibling;

	field.value = '';
	field.dispatchEvent(new Event('input', {bubbles: true}));
	field.dispatchEvent(new Event('change', {bubbles: true}));
	field.focus();

});

document.delegateEventListener('input', 'input[data-type="fqn"]', function(e) {

	if(this.value.match(/^[a-z0-9\-]*$/) === null) {
		this.parentNode.classList.add('form-error-field');
	} else {
		this.parentNode.classList.remove('form-error-field');
	}

});

document.delegateEventListener('input', 'div.form-range input[type="range"]', function(e) {

	RangeField.updateLabel(e.target);

});

document.delegateEventListener('dropdownAfterShow', '.form-range-label', function(e) {

	const range = e.target.previousSibling;
	const list = e.detail.list;

	list.qs('[type="number"]').select();
	list.qs('[type="number"]').value = range.value;

});

/**
 * Manipulates editor fields
 */
class EditorField {

	/**
	 * Le formulaire a été accepté : les brouillons de ses éditeurs n'ont plus lieu d'être.
	 * On ne touche à EditorDraft que s'il y a un éditeur, donc que si editor.js est chargé
	 */
	static flushDraft(form) {

		if(form.qsa('.editor[data-draft]').length > 0) {
			EditorDraft.flushForm(form);
		}

	}

}

/**
 * Manipulates ranges
 */
class RangeField {

	static setValue(target) {

		const newValue = target.firstParent('.form-range-label').qs('[type="number"]').value;

		// On va mettre à jour le range
		target.firstParent('.form-range').qs('[type="range"]', rangeField => {

			rangeField.dataset.internalInput = 'true';
			rangeField.value = newValue;
			rangeField.dispatchEvent(new Event("input"));

		});

	}

	static updateLabel(target) {

		if(target.dataset.internalInput === 'true') {
			target.dataset.internalInput = 'false';
			return;
		}
		const label = target.firstParent('div.form-range').qs('[type="number"]');
		label.value = target.value;

	}

}

/**
 * Manipulates switch
 */
class SwitchField {

	static change(target, onchange) {

		const active = target.classList.contains('field-switch-off');

		target.toggleSwitch();

		const input = target.qs('input');

		input.value = active ? target.dataset.valueOn : target.dataset.valueOff;

		if(onchange) {
			onchange(input);
		}

	}

	static updateLabel(target) {

		const label = target.firstParent('div.form-range').qs('[type="number"]');
		label.value = target.value;

	}

}

/**
 * Manipulates calculations
 */
document.delegateEventListener('input', '[data-calculation]', function(e) {
	CalculationField.calculateValue(this);
});

class CalculationField {

	/**
	 * Calculates the final value according to the input content
	 */
	static calculateValue(target) {
		const currentOperation = target.value.replace(/,/g, '.').replace(/=/g, '');
		const hiddenElement = target.firstParent('div').qs('input[type="hidden"]');
		const resultElement = target.firstParent('div').qs('[data-calculated]');

		if(!currentOperation) {
			hiddenElement.setAttribute('value', ''); // reset value
			resultElement.hide();
		}

		try {
			const result = round(eval(currentOperation));

			if(isNaN(result) === false) {
				if(result.toString() === currentOperation.toString()) {
					resultElement.hide();
				} else {
					resultElement.removeHide();
				}
				resultElement.innerHTML = '= ' + result;
				hiddenElement.setAttribute('value', result);
			} else {
				hiddenElement.setAttribute('value', ''); // reset value
			}
		} catch(e) {
			hiddenElement.setAttribute('value', ''); // reset value
		}
	}

	/**
	 * Sets the field to the given value
	 */
	static setValue(hiddenTarget, value) {

		hiddenTarget.setAttribute('value', value);

		const resultElement = hiddenTarget.firstParent('div').qs('[data-calculated]');

		if(resultElement) {

			resultElement.hide(); // hides because values are the same (calculated + input)
			resultElement.innerHTML = '= ' + value;

			// Updates the input field
			const operationElement = resultElement.firstParent('div').qs('input[data-calculation="1"]');

			if(operationElement) {
				operationElement.value = value;
			}
		}

	}

	static getValue(hiddenTarget) {

		return parseFloat(hiddenTarget.getAttribute('value'));

	}
}

/**
 * Manipulates dates
 */

document.delegateEventListener('input', '[data-week-number]', function(e) {
	DateField.updateWeeks(this);
});

document.delegateEventListener('input', '[data-week-selector]', function(e) {
	DateField.updateWeeks(qs(this.dataset.weekSelector));
});

class DateField {

	static weeks = {};

	/* Handle fallback <input type="week"/> */
	static startFieldWeek(selector) {

		const wrapper = qs(selector);

		if(wrapper.length === 0) {
			return;
		}

		wrapper.qsa('div.field-week-fields input',function(node) {

			node.addEventListener('input', e => {

				const weekNumber = wrapper.qs('[data-fallback="weekNumber"]').value.padStart(2, '0');
				const year = wrapper.qs('[data-fallback="year"]').value;

				wrapper.qs('input.field-week-value').value = year +'-W'+ weekNumber;

			});

		});

	};

	static changeFieldWeek(selector, target) {

		const wrapper = qs(selector);
		const week = target.dataset.week;

		wrapper.qs('[data-fallback="year"]').value = week.substring(0, 4);
		wrapper.qs('[data-fallback="weekNumber"]').value = parseInt(week.substring(6, 8));

		wrapper.qs('input.field-week-value').value = week;

		this.updateWeeks(wrapper.qs('[data-fallback="weekNumber"]'));

	};

	static openWeekSelector(target) {

		const wrapper = target.firstParent('.field-week-wrapper');

		if(wrapper.dataset.withYear === 'true') {

			const year = parseInt(wrapper.qs('[data-fallback="year"]').value);

			if(
				isNaN(year) === false &&
				year >= 2000 &&
				year <= (new Date().getFullYear() + 10)
			) {

				const link = wrapper.qs('.field-week-selector-navigation-after');

				if(parseInt(link.getAttribute('post-year')) !== year) {

					link.setAttribute('post-year', year);
					link.click();

				}

			}

		}

		Lime.Dropdown.open(wrapper.qs('[data-fallback="weekNumber"]'), "center-center");


	}

	static onInputFieldWeek(selector) {

		const wrapper = qs(selector);

		const year = parseInt(wrapper.qs('[data-fallback="year"]').value);
		const week = parseInt(wrapper.qs('[data-fallback="weekNumber"]').value);

		wrapper.qs('input.field-week-value').value = year +'-W'+ (week < 10 ? '0' : '') + week;

		this.updateWeeks(qs('[data-fallback="weekNumber"]'));

	};

	static blurFieldWeek(target) {

		if(isTouch()) {
			target.blur();
		}

	};

	/* Handle fallback <input type="month"/> */
	static startFieldMonth(selector, display) {

		const wrapper = qs(selector);

		if(wrapper.length === 0) {
			return;
		}

		if(display === 'auto') {

			const test = document.createElement('input');
			test.type = 'month'; // Vérification de la disponibilité du type sur le navigateur du client

			display = (test.type === 'month') ? 'native' : 'fallback';

		}

		if(display === 'native') {
			wrapper.qs('div.field-month-native').classList.remove('hide');
		} else {
			wrapper.qs('div.field-month-fallback').classList.remove('hide');
		}

		wrapper.qsa('div.field-month-fallback input, select', node => {

			node.addEventListener('input', () => {

				let monthNumber = wrapper.querySelector('[data-fallback="monthNumber"]').value.padStart(2, '0');
				let year = wrapper.querySelector('[data-fallback="year"]').value;

				wrapper.querySelector('div.field-month-native input').value = year +'-'+ monthNumber;

			});

		});

	};

	static updateWeeks(selector) {

		const label = qs(selector.dataset.weekNumber);
		const weekNumber = parseInt(selector.value);

		if(weekNumber) {

			let year;

			if(selector.dataset.yearSelector) {
				qs(selector.dataset.yearSelector, node => year = node.value, () => year = new Date().getFullYear());
			} else {
				year = new Date().getFullYear();
			}

			const startDate = this.getDateOfISOWeek(1, weekNumber, year);
			const endDate = this.getDateOfISOWeek(7, weekNumber, year);

			label.innerHTML = this.formatDateElement(startDate.getDate()) +'/'+ this.formatDateElement(1 + startDate.getMonth()) +'&nbsp;'+ Lime.Asset.icon('arrow-right') +'&nbsp;'+ this.formatDateElement(endDate.getDate()) +'/'+ this.formatDateElement(1 + endDate.getMonth());

		} else {
			label.innerHTML = '';
		}

	};

	static formatDateElement(value) {
		return (value < 10 ? '0' : '') + value;
	}

	static getDateOfISOWeek(day, week, year) {
		const simple = new Date(year, 0, 1 + (week - 1) * 7);
		const dow = simple.getDay();
		if(dow <= 4) {
			simple.setDate(simple.getDate() - simple.getDay() + day);
		} else {
			simple.setDate(simple.getDate() + 7 + day - simple.getDay());
		}
		return simple;
	}

	static onclickWeek(url, target) {

		new Ajax.Navigation()
			.url(url.replace('__CURRENT__', target.dataset.week))
			.fetch();

	}

	static onclickMonth(url, target) {

		new Ajax.Navigation()
			.url(url.replace('__CURRENT__', target.dataset.month))
			.fetch();

	}

}

class CheckboxField {

	static all(target, checked, selector, callback = undefined) {

		target.qsa(selector, field => {

			if(field.disabled === true) {
				return;
			}

			field.checked = checked;

			if(typeof callback !== 'undefined') {
				callback(field);
			}

		});

	}

	static check(target, name, value) {

		const form = target.firstParent('form');
		form.qsa('[name^="'+ name +'"]', field => {

			if(field.disabled === true) {
				return;
			}

			field.checked = value;

		});

	}

}

class ColorField {

	static update(field) {

		const wrapper = field.firstParent('.field-color');

		wrapper.qs('input[type="checkbox"]', checkbox => checkbox.checked = false);

		const hiddenField = wrapper.qs('input[type="hidden"]');
		hiddenField.value = field.value;
		hiddenField.dispatchEvent(new CustomEvent("input"));

	}

	static setEmpty(field, emptyColor = '#000000') {

		const wrapper = field.firstParent('.field-color')

		const hiddenField = wrapper.qs('input[type="hidden"]');
		wrapper.qs('input[type="color"]').value = emptyColor;
		hiddenField.value = (field.checked ? '' : emptyColor);
		hiddenField.dispatchEvent(new CustomEvent("input"));

	}

}

class SelectDropdownField {

	static select(target) {

		const head = target.firstParent('.form-dropdown-list').previousSibling.qs('.form-dropdown-head');

		const selectedItem = target.firstParent('.dropdown-item');
		head.innerHTML = selectedItem.qs('.form-dropdown-content').innerHTML;

		const items = target.firstParent('.dropdown-list');
		items.qs('.dropdown-item.selected', item => item.classList.remove('selected'));
		selectedItem.classList.add('selected');

	}

}

/**
 * Manipulates field with a limit
 */
const selector = 'div.form-character-count-wrapper input[type=text], div.form-character-count-wrapper textarea';

document.delegateEventListener('focus', selector, function(e) {
	CounterField.refresh(this);
	CounterField.show(this);
});

document.delegateEventListener('blur', selector, function(e) {
	CounterField.hide(this);
});

document.delegateEventListener('input', selector, function(e) {
	CounterField.refresh(this);
});

class CounterField {

	static show(input, visible) {

		const counter = qs(".form-character-count[data-limit-for="+ input.id +"]");

		counter.style.display = 'flex';

		if(input.tagName == "TEXTAREA") {

			const originalHeight = input.offsetHeight;
			const newHeight = originalHeight - counter.offsetHeight;

			input.style.height = newHeight +'px';
			input.setAttribute('data-field-height', originalHeight);

		}
	};

	static hide(input, visible) {

		const counter = qs(".form-character-count[data-limit-for="+ input.id +"]");

		counter.style.display = '';

		if(input.tagName == "TEXTAREA") {

			const originalHeight = input.getAttribute('data-field-height');

			input.style.height = originalHeight +'px';
			input.removeAttribute('data-field-height');

		}
	};

	static refresh(input) {

		const counter = qs(".form-character-count[data-limit-for="+ input.id +"]");
		const wrapper = input.firstParent('.form-character-count-wrapper');

		const limit = parseInt(input.getAttribute("data-limit"));

		const count = input.value.length;
		const countLeft = limit - count;

		counter.classList.remove("error");
		counter.classList.add("focus");
		wrapper.classList.remove("form-error-field");

		if(countLeft < 0) {
			counter.classList.add("error");
			counter.classList.remove("focus");
			wrapper.classList.add("form-error-field");
		}

		if(input.tagName === 'TEXTAREA') {
			counter.innerHTML = count +'/'+ limit;
		} else {
			counter.innerHTML = countLeft;
		}
	}


};

/*
 * Le libellé d'un champ lui donne le curseur et lui renvoie son clic, une fois son propre clic propagé : la marque
 * posée ici en capture précède donc les deux, qui laissent la liste fermée. Le geste fini, la marque tombe.
 */
document.addEventListener('click', e => {

	const control = e.target.closest('label')?.control;

	if(control?.matches('[data-autocomplete-url]')) {
		AutocompleteField.labelClicked = control;
		setTimeout(() => AutocompleteField.labelClicked = null, 0);
	}

}, true);

/**
 *  Autocomplete features
 */
class AutocompleteField {

	static queryTimeout = null;
	static round = {};
	static sequence = 0;
	static opened = null;
	static chosen = {};
	static ignoreNextFocus = false;
	static focusQuiet = null;
	static labelClicked = null;
	static clickListener = {};

	static getDropdown(input) {

		const dropdownId = input.id +'-autocomplete';

		if(this.hasDropdown(input) === false) {

			const dropdown = document.createElement('div');
			dropdown.id = dropdownId;
			dropdown.classList.add('autocomplete-dropdown');

			if(input.dataset.autocompleteTextual) {
				dropdown.classList.add('autocomplete-dropdown-textual');
			}

			input.insertAdjacentElement('afterend', dropdown);
		}

		return qs('#'+ dropdownId);

	}

	static hasDropdown(input) {
		return (qs('#'+ input.id +'-autocomplete') !== null);
	}

	static start(input) {

		const focus = () => {

			if(AutocompleteField.ignoreNextFocus) {
				AutocompleteField.ignoreNextFocus = false;
				return;
			}

			if(input.id === null) {
				throw 'Missing ID for input';
			}

			this.init(input); // Init autocomplete field once

			if(this.focusQuiet !== input && this.labelClicked !== input) {
				this.query(input);
			}

			input.setSelectionRange(0, input.value.length);

			// Le libellé laisse la liste fermée : armée ici, l'écoute jetterait la recherche d'une mise au point suivante, et `move()` l'arme de toute façon quand la liste s'ouvre
			if(this.isFullscreen() === false && this.labelClicked !== input) {
				this.watchClick(input);
			}

		};

		input.addEventListener('focusin', focus);

		// Le `onrender` d'un parent passe avant celui du champ : un parcours qui y pose le curseur le fait avant l'écoute
		if(document.activeElement === input) {
			focus();
		}

	};

	/*
	 * Un clic hors du champ ferme la liste, un clic dans le champ la garde. L'écoute se pose au `focusin`, et aussi
	 * à l'ouverture de la liste, qui peut se passer de lui : la saisie dans le champ qui a gardé le curseur après un
	 * choix au clavier rouvre la liste, que le clic extérieur doit fermer comme les autres. Une seule écoute par
	 * champ, jusqu'à ce que `internalRemove()` la retire.
	 */
	static watchClick(input) {

		if(this.clickListener[input.id] !== undefined) {
			return;
		}

		this.clickListener[input.id] = (e) => {

			let target;
			for(target = e.target; target && target !== input; target = target.parentNode);

			if(target !== input) {
				this.internalRemove(input);
			} else {
				watch();
			}

		};

		const watch = () => setTimeout(() => document.addEventListener('click', this.clickListener[input.id], {once: true}), 0);

		watch();

	};

	/*
	 * Pose le curseur dans le champ sans ouvrir la liste, qui couvrirait les champs suivants.
	 */
	static focus(input) {

		this.focusQuiet = input;

		try {
			input.focus();
		} finally {
			this.focusQuiet = null;
		}

	}

	static init(input) {

		if(input.hasAttribute('data-autocomplete')) {
			return;
		}

		input.setAttribute('data-autocomplete', 'on');
		input.setAttribute('autocomplete', 'off');

		// Le champ est rendu avec le texte de la valeur qu'il porte, ou sans texte ni valeur : l'un accompagne l'autre
		// au même titre qu'un choix fait dans la liste
		this.chosen[input.id] = input.value;

		input.addEventListener('focusin', () => {
			input.setSelectionRange(0, input.value.length);
		});

		// Le champ qui a déjà le curseur ne reçoit pas le `focusin` qui ouvre la liste : un clic l'ouvre à sa place
		input.addEventListener('click', () => {

			if(document.activeElement === input && this.hasDropdown(input) === false && this.labelClicked !== input) {
				this.query(input);
			}

		});

		// Workaround pour téléphone qui lance deux events 'input' pour un seul changement dans le champ
		input.lastValue = null;

		input.addEventListener('input', () => {

			if(input.value !== input.lastValue) {
				input.lastValue = input.value;
				this.change(input);
			}

		});

		input.addEventListener('keydown', e => {

			switch(e.key) {

				case 'Enter' : // Enter

					e.preventDefault();

					if(this.hasDropdown(input)) {

						this.getDropdown(input).qs('li.selected', input => {
							input.dispatchEvent(new CustomEvent('autocompleteEnter'));
						});

					}

					break;

				case 'ArrowUp' :
					this.hover(input, 'up', e);
					break;

				case 'ArrowDown' :
					this.hover(input, 'down', e);
					break;
			}


		});

	};

	static change(input) {

		this.onUpdate(input);

		this.cancelQuery();

		this.queryTimeout = setTimeout(() => {
			this.queryTimeout = null;
			this.query(input);
		}, 200);

	};

	static query(input) {

		// Une recherche lancée rend caduque celle que la saisie avait programmée, qu'elle ait été tapée dans ce champ,
		// dont le texte part ici, ou dans un champ quitté depuis, dont la liste rouverte fermerait celle-ci
		this.cancelQuery();

		const url = input.dataset.autocompleteUrl;
		const body = new URLSearchParams();

		if(input.dataset.autocompleteBody) {
			Object.entries(JSON.parse(input.dataset.autocompleteBody)).forEach(([key, value]) => {
				if(value !== null && typeof value === 'object') {
					Object.entries(value).forEach(([subKey, subValue]) => {
						body.append(key +'['+ subKey +']', subValue);
					});
				} else {
					body.append(key, value);
				}
			});
		}

		if(input.dataset.autocompleteHead) {
			body.set(JSON.parse(input.dataset.autocompleteHead).name, input.dataset.autocompleteHeadSelected);
		}

		const items = input.dataset.autocompleteItems;

		const field = input.getAttribute('data-autocomplete-field');
		const multiple = (field.indexOf('[]') !== -1);

		// La valeur choisie ne s'efface qu'avec le texte qui l'accompagne : la liste rouverte sans le retoucher, par un clic,
		// la flèche ou le `focusin`, la garde, et Échap ou la sortie du champ retrouvent le choix tel qu'il était
		if(multiple === false && this.chosen[input.id] !== input.value) {
			qs('#'+ items).innerHTML = '';
			delete this.chosen[input.id];
		}

		const detail = {
			body: body,
			query: input.value
		};

		this.onBeforeQuery(input, detail);

		const value = detail.query;

		AutocompleteField.move(input);

		body.set('query', value);

		// Une recherche partie après celle-ci la rend caduque : sa réponse, si elle arrivait la dernière, rendrait la liste
		// d'un texte que le champ ne porte plus. Chaque recherche a son verrou, sinon celle de la frappe, partie avant la
		// réponse de celle du focus, serait refusée (`LOCK_FAILED`) et la liste resterait vide.
		const round = this.round[input.id] = {};

		new Ajax.Query(input)
			.lockAs('autocomplete-'+ (++this.sequence))
			.url(url)
			.body(body)
			.fetch()
			.then((json) => {

				if(this.round[input.id] !== round) {
					return;
				}

				// Le focus est perdu suite au déplacement précédent
				if(document.activeElement !== input) {

					// La marque ne vaut que pour le `focusin` de cet appel : un `focus()` qui n'en émet pas la laisserait
					// avaler le premier `focusin` du champ suivant
					AutocompleteField.ignoreNextFocus = true;

					try {
						input.focus();
					} finally {
						AutocompleteField.ignoreNextFocus = false;
					}

				}

				AutocompleteField.source(input, json.results);

				this.onSource(input, {
					query: value,
					results: json.results
				});

			});

	};

	static onUpdate(input) {
		this.dispatch(input, 'autocompleteUpdate', {});
	};

	static onSource(input, detail) {
		this.dispatch(input, 'autocompleteSource', detail);
	};

	static onBeforeQuery(input, detail) {
		this.dispatch(input, 'autocompleteBeforeQuery', detail);
	};

	static onSelect(input, detail) {

		this.renew(input);
		this.chosen[input.id] = input.value;

		// Le choix ne déplace pas le curseur : au clavier, il reste dans le champ, d'où Tab mène au suivant, avec le texte
		// choisi sélectionné comme au `focusin` pour que la saisie suivante le remplace. Sur un écran tactile, le choix
		// rend le curseur pour replier le clavier virtuel, qui couvrirait le formulaire où le champ retourne.
		if(this.isFullscreen()) {
			document.activeElement.blur();
		} else if(document.activeElement === input) {
			input.setSelectionRange(0, input.value.length);
		}

		// En plein écran, le champ attend sous body que l'historique referme la liste : remis à sa place
		// tout de suite, il atteint son formulaire, et ses écouteurs y retrouvent leurs parents.
		this.restore(input);

		switch(input.dataset.autocompleteSelect) {

			case 'submit' :
				this.internalRemove(input);
				input.dispatchEvent(new CustomEvent("submit"));
				break;

			case 'event' :
				this.dispatch(input, 'autocompleteSelect', detail);
				break;

		}

	};

	/*
	 * Une réponse ne vaut que pour la liste qui l'a demandée. Un choix, ou la liste fermée, en ouvrent une autre : la
	 * réponse d'une recherche partie avant eux rouvrirait la liste par-dessus, et reprendrait le curseur au champ où
	 * il est allé, elle est donc ignorée. La recherche que la saisie avait programmée ferait de même, et ne part pas.
	 */
	static renew(input) {
		this.round[input.id] = {};
		this.cancelQuery();
	}

	static cancelQuery() {

		if(this.queryTimeout !== null) {
			clearTimeout(this.queryTimeout);
			this.queryTimeout = null;
		}

	}

	static dispatch(input, eventName, detail) {

		detail.input = input;

		const event = new CustomEvent(eventName, {detail: detail});

		if(input.dataset.autocompleteDispatch) {
			qs(input.dataset.autocompleteDispatch, node => node.dispatchEvent(event));
		} else {
			input.dispatchEvent(event);
		}

	}

	static move(input) {

		if(input.classList.contains('autocomplete-open')) {
			return;
		}

		// Une seule liste ouverte à la fois : celle qu'on quitte sans choix, par Tab ou un clic dans un autre champ,
		// se ferme quand la suivante s'ouvre
		if(this.opened !== null && this.opened !== input) {
			this.remove(this.opened);
		}

		this.opened = input;

		input.dataset.scroll = window.scrollY;
		input.classList.add('autocomplete-open');

		const dropdown = this.getDropdown(input);

		if(this.isFullscreen()) {

			input.insertAdjacentHTML('beforebegin', '<div id="'+ input.id +'-placeholder" class="autocomplete-placeholder"></div>');

			document.body.insertAdjacentHTML('beforeend', '<div id="'+ input.id +'-wrapper" class="autocomplete-wrapper" style="z-index: '+ Lime.getZIndex() +'"></div>');

			const wrapper = qs('#'+ input.id +'-wrapper');
			wrapper.insertAdjacentElement('beforeend', input);
			wrapper.insertAdjacentElement('beforeend', dropdown);

			Lime.History.pushLayer(dropdown, () => this.internalRemove(input), document.location.href, null);

			document.body.classList.add('autocomplete-fullscreen-open');

		} else {
			input.style.zIndex = Lime.getZIndex();
			dropdown.style.zIndex = Lime.getZIndex();
			this.watchClick(input);
		}

	};

	static source(input, values) {

		const dropdown = this.getDropdown(input);

		let html = '<ul class="autocomplete-list">';

		// La réponse peut poser la rangée parmi ses résultats (`type: 'head'`), sous l'intitulé de la section qu'elle règle
		const headPlaced = values.some(value => value.type === 'head');

		if(input.dataset.autocompleteHead && headPlaced === false) {
			html += this.getHead(input, false);
		}

		if(values.length === 0) {

			if(input.value === '') {

				// Une liste qui porte une rangée de tête se refait avec la rangée seule, qui reste réglable
				if(input.dataset.autocompleteHead === undefined) {
					return '';
				}

			} else {

				const labelEmpty = input.dataset.autocompleteEmpty.replace('{value}', input.value);

				if(input.dataset.autocompleteTextual) {
					html += '<li class="autocomplete-list-empty">';
						html += '<a>'+ labelEmpty + '</a>';
					html += '</li>';
				} else {
					html += '<li class="autocomplete-not-selectable autocomplete-list-empty">' + labelEmpty + '</li>';
				}

			}

		} else {

			values.forEach((value, key) => {

				const type = value.type || 'value';

				if(type === 'title') {
					html += '<li class="autocomplete-not-selectable">';
						html += value['itemHtml'];
					html += '</li>';
				} else if(type === 'disabled') {
					html += '<li class="autocomplete-not-selectable disabled">';
						html += value['itemHtml'];
					html += '</li>';
				} else if(type === 'head') {
					if(input.dataset.autocompleteHead) {
						html += this.getHead(input, true);
					}
				} else if(type === 'link') {
					html += '<li class="autocomplete-link">';
						html += '<a href="'+ value['link'] +'" '+ (value['target'] !== undefined ? 'target="'+ value['target'] +'"' : '') +'>'+ value['itemHtml'] +'</a>';
					html += '</li>';
				} else {
					html += '<li data-n="'+ key +'" data-dropdown-keep class="'+ (value['class'] ?? '') +'">';
						html += value['itemHtml'];
					html += '</li>';
				}

			});

		}

		html += '</ul>';

		dropdown.innerHTML = html;

		dropdown.qs('ul.autocomplete-list', list => {

			// Avec data-autocomplete-width="group", la liste prend la largeur de l'input-group et s'aligne sur son bord
			// gauche ; sans groupe autour, elle garde celle du champ
			const frame = (input.dataset.autocompleteWidth === 'group') ? (input.closest('.input-group') ?? input) : input;

			const listBounds = list.getBoundingClientRect();
			const inputBounds = input.getBoundingClientRect();
			const frameBounds = frame.getBoundingClientRect();

			list.scroll(0, 0);
			list.style.width = frameBounds.width +'px';

			const translateX = frameBounds.left - listBounds.left;
			const translateY = inputBounds.bottom + 1 - listBounds.top;

			list.style.transform = 'translate('+ translateX +'px, '+ translateY +'px)';

		});

		dropdown.qsa('li', node => node.addEventListener('click', e => AutocompleteField.onClick(node, input, values, node)));
		dropdown.qsa('li', node => node.addEventListener('autocompleteEnter', e => AutocompleteField.onClick(node, input, values, node)));

		if(input.dataset.autocompleteHead) {

			dropdown.qs('li.autocomplete-head', head => {

				// Le focus reste dans le champ : la saisie et Échap continuent de le viser
				head.addEventListener('mousedown', e => e.preventDefault());

				// Un clic dans la rangée n'atteint pas l'écouteur du document qui ferme la liste. Ce dernier ne peut pas en
				// juger : la réponse qu'un bouton relance peut avoir refait la liste et détaché la cible avant lui.
				head.addEventListener('click', e => e.stopPropagation());

				head.qsa('button', button => button.addEventListener('click', () => this.selectHead(input, parseInt(button.dataset.autocompleteHeadN))));

			});

		}

	};

	/*
	 * La rangée de tête est rendue depuis le champ, qui garde la valeur choisie d'une réponse à l'autre.
	 * Des boutons et non des liens : un lien d'une ligne de liste en prend toute la largeur (form.css).
	 */
	static getHead(input, placed) {

		const head = JSON.parse(input.dataset.autocompleteHead);

		let html = '<li class="autocomplete-not-selectable autocomplete-head'+ (placed ? ' autocomplete-head-placed' : '') +'">';
			html += '<span>'+ head.label +'</span>';
			head.values.forEach(([value, label], n) => {
				const selected = (String(value) === input.dataset.autocompleteHeadSelected);
				html += '<button type="button" class="btn btn-sm '+ (selected ? 'btn-white' : 'btn-outline-white') +'" data-autocomplete-head-n="'+ n +'">'+ label +'</button>';
			});
		html += '</li>';

		return html;

	};

	static selectHead(input, n) {

		input.dataset.autocompleteHeadSelected = String(JSON.parse(input.dataset.autocompleteHead).values[n][0]);

		this.query(input);

	};

	static onClick(node, input, values, selected) {

		let value;

		if(
			input.dataset.autocompleteTextual &&
			node.classList.contains('autocomplete-list-empty')
		) {

			value = {
				value: input.value,
				itemText: input.value,
				itemHtml: input.value
			};

		} else {

			if(selected.dataset.n === undefined) {
				return;
			}

			const position = parseInt(selected.dataset.n);
			value = values[position];

		}

		// On applique et appelle les callbacks
		this.apply(input, value);

	};

	static empty(target) {

		// La liste ouverte s'intercale entre le champ et sa croix. En plein écran, le champ attend sous body avec sa liste
		// et ne laisse devant la croix que son emplacement : il n'y a rien à vider tant qu'elle est ouverte.
		let input = target.previousElementSibling;

		while(input !== null && input.hasAttribute('data-autocomplete-field') === false) {
			input = input.previousElementSibling;
		}

		if(input === null || input.value === '') {
			return;
		}

		// Un champ à plusieurs choix n'a que son texte à vider : `apply()` y ajouterait un choix vide
		if(input.getAttribute('data-autocomplete-field').includes('[]')) {

			input.value = '';
			this.remove(input);

		} else {

			this.apply(input, {
				value: '',
				itemText: ''
			});

		}

	};

	static apply(input, value) {

		if(input.dataset.autocompleteItems) {

				let itemSelector = '#'+ input.dataset.autocompleteItems;

				const field = input.getAttribute('data-autocomplete-field');
				const multiple = (field.indexOf('[]') !== -1);

				if(multiple === false) {

					if(input.dataset.autocompleteTextual) {

						input.value = value.itemText;

					} else {

						const newInput = document.createElement('input');
						newInput.setAttribute('type', 'hidden');
						newInput.setAttribute('name', field);
						newInput.setAttribute('value', value.value);

						// La valeur d'avant peut encore être là, la liste rouverte sans retoucher le texte la gardant
						qs(itemSelector, item => {
							item.innerHTML = '';
							item.appendChild(newInput);
						});

						input.value = value.itemText;

					}

					this.onSelect(input, value);

				} else {

					if(qsa(itemSelector +' > [data-value="'+ value.value +'"]').length === 0) {

						let item = '<div class="autocomplete-item" data-value="'+ value.value +'">';
							item += '<input type="hidden" name="'+ field +'" value="'+ value.value +'"/>';
							item += value.itemHtml;
							item += '<div class="autocomplete-item-actions">';
								item += '<a onclick="AutocompleteField.upItem(this)" class="btn btn-only-icon btn-sm btn-outline-primary autocomplete-reorder-up">'+ Lime.Asset.icon('arrow-up') +'</a>';
								item += '<a onclick="AutocompleteField.downItem(this)" class="btn btn-only-icon btn-sm btn-outline-primary autocomplete-reorder-down">'+ Lime.Asset.icon('arrow-down') +'</a>';
								item += '<a onclick="AutocompleteField.removeItem(this)" class="btn btn-only-icon btn-sm btn-outline-primary">'+ Lime.Asset.icon('trash-fill') +'</a>';
							item += '</div>';
						item += '</div>';

						qs(itemSelector).insertAdjacentHTML('beforeend', item);

					}

					// `data-autocomplete-keep-query` garde le texte tapé, pour revenir sur les autres résultats de la même recherche
					if(!input.dataset.autocompleteKeepQuery) {
						input.value = '';
					}

					this.onUpdate(input);

				}

		} else {

			input.value = value.value;
			this.onSelect(input, value);

		}

		// On remet l'élément à sa place
		this.remove(input);

		// Le texte gardé se sélectionne, pour que la saisie suivante le remplace. Un choix à la souris a pris le curseur
		// au champ, la ligne de la liste n'annulant pas `mousedown` : il le reprend sans rouvrir la liste. Sur un écran
		// tactile, le curseur reste rendu, comme après un choix unique.
		if(input.dataset.autocompleteKeepQuery && this.isFullscreen() === false) {
			this.focus(input);
			input.setSelectionRange(0, input.value.length);
		}

	};

	static hover(input, direction, e) {

		// Sans liste, la flèche l'ouvre, comme le clic dans le champ qui a déjà le curseur
		if(this.hasDropdown(input) === false) {
			e.preventDefault();
			this.query(input);
			return;
		}

		// Seules les lignes choisissables comptent : les en-têtes et les liens n'ont pas de data-n,
		// et les compter dans les rangs faisait chercher un li[data-n] inexistant.
		const list = this.getDropdown(input).qsa('li[data-n]');
		const length = list.length;

		if(length === 0) {
			return;
		}

		e.preventDefault();

		let currentPosition = null;

		list.forEach((entry, position) => {

			if(entry.classList.contains('selected')) {

				entry.classList.remove('selected');

				currentPosition = position;

			}

		});

		let newPosition;

		if(direction === 'up') {

			if(currentPosition === null || currentPosition === 0) {
				newPosition = length - 1;
			} else {
				newPosition = currentPosition - 1;
			}

		} else {

			if(currentPosition === null || currentPosition === length - 1) {
				newPosition = 0;
			} else {
				newPosition = currentPosition + 1;
			}

		}

		list[newPosition].classList.add('selected');

	};

	static removeItem(item) {

		const wrapper = item.firstParent('.autocomplete-items');
		item.firstParent('.autocomplete-item').remove();

		const input = qs('[data-autocomplete-items="'+ wrapper.id +'"]');

		if(input !== null) {
			this.onUpdate(input);
		}

	}

	static upItem(target) {

		const item = target.firstParent('.autocomplete-item');
		item.parentElement.insertBefore(item, item.previousSibling);

	}

	static downItem(target) {

		const item = target.firstParent('.autocomplete-item');
		item.parentElement.insertBefore(item.nextSibling, item);

	}

	static remove(input) {

		const dropdown = this.getDropdown(input);

		if(dropdown !== null) {

			if(this.isFullscreen()) {
				Lime.History.popLayer(dropdown);
			} else {
				this.internalRemove(input);
			}

		}

	};

	static restore(input) {

		if(this.isFullscreen()) {
			qs('#'+ input.id +'-placeholder', placeholder => placeholder.insertAdjacentElement('afterend', input));
		}

	}

	static internalRemove(input) {

		// L'écoute se retire même sans liste : un champ rendu de nouveau sous le même id n'en poserait pas d'autre
		if(this.isFullscreen() === false) {
			document.removeEventListener('click', this.clickListener[input.id], {once: true})
			delete this.clickListener[input.id];
		}

		const dropdown = this.getDropdown(input);

		if(dropdown === null) {
			return;
		}

		input.classList.remove('autocomplete-open');

		this.renew(input);

		if(this.opened === input) {
			this.opened = null;
		}

		if(this.isFullscreen()) {

			this.restore(input);

			qs('#'+ input.id +'-placeholder', placeholder => placeholder.remove());

			qs('#'+ input.id +'-wrapper', wrapper => wrapper.remove());

			document.body.classList.remove('autocomplete-fullscreen-open');

			setTimeout(() => {
				window.scrollTo(0, input.dataset.scroll);
				input.dataset.scroll = null;
			}, 0);

		}

		dropdown.remove();

		return true;

	}

	static isFullscreen() {
		return document.body.matches('[data-touch="yes"]');
	};

};

class PasswordField {

	static toggleVisibility(target) {

		const input = target.previousSibling;

		if(target.dataset.visible === '0') {

			input.type = 'text';
			target.dataset.visible = '1';

		} else {

			input.type = 'password';
			target.dataset.visible = '0';

		}

	}

}