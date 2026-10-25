class User {

	static changeCustomerType(target) {

		const form = target.firstParent('form');
		const invite = form.qs('[name="invite"');

		new Ajax.Query()
			.url('/user/signUp:getCustomerFields?type='+ target.value + (invite ? '&invite='+ invite.value : ''))
			.method('get')
			.fetch();

	}

	static changeDeliveryCountry(target) {

		const form = target.firstParent('form');

		const deliveryCountry = form.qs('[name="deliveryCountry"]').value;

		const hasInvoiceAddress = (form.qs('[name="invoiceCity').value !== '');

		if(hasInvoiceAddress === false) {
			form.qs('[name="invoiceCountry"]').value = deliveryCountry;
		}

	}

	static changeAddress(target) {

		target.firstParent('.user-form-address').qsa('.form-control-address input', (node) => node.value = '');

	}
	
}