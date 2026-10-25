document.delegateEventListener('autocompleteSelect', '#product-create, #product-update', function(e) {

	const fieldName = this.qs('[name="name"]');

	if(fieldName.value === '') {
		fieldName.value = e.detail.itemText;
	}

});

class Product {

	static changeUnit(target, change) {

		ref(change, (node) => node.innerHTML = target.value === '' ? '' : target.qs('option[value="'+ target.value +'"]').innerHTML);

	}

	static changePreparationDays(target) {

		const hour = target.parentElement.qs('[name="preparationTime"]');

		if(target.value === '') {
			hour.hide();
		} else {
			hour.removeHide();
		}

	}

	static accountDissociation() {

		if(qs('[data-field="proAccountNumber"]').classList.contains('hide')) {

			qs('[data-field="proAccountNumber"]').removeHide();
			qs('[data-field-label="privateAccountNumber"]').innerHTML = qs('[data-field-account-specific-label]').getAttribute('data-field-account-specific-label');


		} else {

			qs('[data-field="proAccountNumber"]').hide();
			qs('[data-field-label="privateAccountNumber"]').innerHTML = qs('[data-field-account-generic-label]').getAttribute('data-field-account-generic-label');

			const element = qs('[data-field="proAccountNumber"] a[class="autocomplete-empty"]');
			AutocompleteField.empty(element);


		}

	}

	static changeProfile(target) {

		const profile = target.dataset.profile;

		const wrapper = qs('.product-write [data-wrapper="profile"]');

		wrapper.qs('[data-dropdown] .product-profile-icon').outerHTML = target.qs('.product-profile-icon').outerHTML;
		wrapper.qs('[data-dropdown] .product-profile-name').innerHTML = target.qs('.product-profile-name').innerHTML;
		wrapper.qs('input').value = profile;

		Lime.Dropdown.purge();

	}

	static changeType(target, type) {

		const wrapper = target.firstParent('[data-wrapper="'+ type +'-block"]');

		// Un canal éteint ne se règle pas : ses champs se cachent plutôt que de rester grisés, où ils
		// donnaient à lire un formulaire dont rien ne servait.
		wrapper.classList.toggle('product-write-off', target.value === '');

		wrapper.qsa('[name^="'+ type +'Price"], [name^="'+ type +'Packaging"], [name^="'+ type +'Step"]', (node) => {

			if(target.value === '') {
				node.setAttribute('disabled', 'disabled');
			} else {
				node.removeAttribute('disabled');
			}

			node.value = '';

		});

	}

	static toggleSelection(target) {

		CheckboxField.all(target.firstParent('table'), target.checked, '[name^="batch[]"]');

		this.changeSelection(target);

	}

	static changeSelection() {

		return Batch.changeSelection('#batch-product', null);

	}

}
