document.delegateEventListener('autocompleteUpdate', 'input[data-autocomplete-map]', function(e) {

	const lngLat = qs('#'+ this.getAttribute('data-autocomplete-lnglat'));
	lngLat.value = '';

	const container = this.getAttribute('data-autocomplete-map');

	MainPlace.updateMap(container, null);


});

document.delegateEventListener('autocompleteSelect', 'input[data-autocomplete-map]', function(e) {

	const lngLat = qs('#'+ this.getAttribute('data-autocomplete-lnglat'));
	const container = this.getAttribute('data-autocomplete-map');

	if(e.detail.lngLat !== undefined) {
		lngLat.value = JSON.stringify(e.detail.lngLat);
		MainPlace.updateMap(container, e.detail.lngLat);
		MainPlace.updateAltitude(this, e.detail.lngLat);
	} else {
		lngLat.value = null;
		MainPlace.updateMap(container, null);
	}


});


document.delegateEventListener('autocompleteSelect', 'input[data-place-address]', function(e) {
	MainPlace.fillAddress(this, e.detail);
});

document.delegateEventListener('autocompleteSelect', 'input[data-place-city]', function(e) {
	MainPlace.fillCity(this, e.detail);
});


class MainPlace {

	static FR = 75;

	static querySiret(target) {

		const wrapper = target.firstParent('[data-wrapper]');

		const siret = target.value.replace(/\s/g, '');

		if(siret.match(/^[0-9]{14}$/) === null) {
			return false;
		}

		new Ajax.Query()
			.url('/main/place:siret?siret='+ siret)
			.method('get')
			.fetch()
			.then((json) => {

				const result = json.result;

				if(result === null) {
					this.queryNotFound('siret', wrapper);
				} else {
					this.queryFound('siret', wrapper, result);
				}

			});

	}

	static querySiren(target) {

		const wrapper = target.firstParent('[data-wrapper]');

		const siren = target.value.replace(/\s/g, '');

		if(siren.match(/^[0-9]{9}$/) === null) {
			return false;
		}


		new Ajax.Query()
			.url('/main/place:siren?siren='+ siren)
			.method('get')
			.fetch()
			.then((json) => {

				const result = json.result;

				if(result === null) {
					this.queryNotFound('siren', wrapper);
				} else {
					this.queryFound('siren', wrapper, result);
				}

			});

	}

	static field(prefix, name) {
		return prefix === '' ? name : prefix + name.charAt(0).toUpperCase() + name.slice(1);
	}

	// Une saisie libre ou la croix du champ passent aussi par ici, sans code postal ni pays
	static fillAddress(input, detail) {

		const form = input.firstParent('form');
		const prefix = input.dataset.placeAddress;

		if(detail.postcode === undefined) {
			return;
		}

		// Une adresse sans code postal ou sans commune dans la réponse de Mapbox laisse la saisie en place
		if(detail.postcode !== null) {
			form.qs('[name="'+ this.field(prefix, 'postcode') +'"]', node => node.value = detail.postcode);
		}

		if(detail.city !== null) {
			form.qs('[name="'+ this.field(prefix, 'city') +'"]', node => node.value = detail.city);
		}

		if(detail.country !== null) {
			this.selectCountry(form.qs('select[name="'+ this.field(prefix, 'country') +'"]'), detail.country);
		}

	}

	static fillCity(input, detail) {

		const form = input.firstParent('form');
		const prefix = input.dataset.placeCity;

		// La croix vide la commune, et le département qui n'appartenait qu'à elle
		if(detail.value === '') {
			form.qs('[name="'+ this.field(prefix, 'department') +'"]', node => node.value = '');
			return;
		}

		if(detail.country === undefined) {
			return;
		}

		// citiesByName propose l'adresse complète de la commune, le champ ne garde que son nom
		input.value = detail.value;

		form.qs('[name="'+ this.field(prefix, 'department') +'"]', node => node.value = detail.department ?? '');

		if(detail.country !== null) {
			this.selectCountry(form.qs('select[name="'+ this.field(prefix, 'country') +'"]'), detail.country);
		}

	}

	// Une valeur posée par script ne déclenche pas le onchange que le formulaire accroche au pays
	static selectCountry(select, country) {

		if(select === null) {
			return;
		}

		select.value = country;
		select.dispatchEvent(new Event('change'));

	}

	static fillCompany(type, target, prefix) {

		const form = target.firstParent('form');
		const querySelector = '.'+ type +'-found .'+ type;

		if(form.qs('input[name="legalName"]')) {
			form.qs('input[name="legalName"]').value = form.qs(querySelector +'-name').innerHTML;
		}

		if(form.qs('input[name="name"]') && form.qs('input[name="name"]').value.length === 0) {
			form.qs('input[name="name"]').value = form.qs(querySelector +'-name').innerHTML;
		}

		if(prefix.length === 0 && form.qs('input[name="street1"]')) {
			form.qs('input[name="street1"]').value = form.qs(querySelector +'-street1').innerHTML;
		}
		if(prefix.length > 0 && form.qs('input[name="'+ prefix +'Street1"]')) {
			form.qs('input[name="'+ prefix +'Street1"]').value = form.qs(querySelector +'-street1').innerHTML;
		}

		if(prefix.length === 0 && form.qs('input[name="street2"]')) {
			form.qs('input[name="street2"]').value = form.qs(querySelector +'-street2').innerHTML;
		}
		if(prefix.length > 0 && form.qs('input[name="'+ prefix +'Street2"]')) {
			form.qs('input[name="'+ prefix +'Street2"]').value = form.qs(querySelector +'-street2').innerHTML;
		}

		if(prefix.length === 0 && form.qs('input[name="postcode"]')) {
			form.qs('input[name="postcode"]').value = form.qs(querySelector +'-postcode').innerHTML;
		}
		if(prefix.length > 0 && form.qs('input[name="'+ prefix +'Postcode"]')) {
			form.qs('input[name="'+ prefix +'Postcode"]').value = form.qs(querySelector +'-postcode').innerHTML;
		}

		if(prefix.length === 0 && form.qs('input[name="city"]')) {
			form.qs('input[name="city"]').value = form.qs(querySelector +'-city').innerHTML;
		}
		if(prefix.length > 0 && form.qs('input[name="'+ prefix +'City"]')) {
			form.qs('input[name="'+ prefix +'City"]').value = form.qs(querySelector +'-city').innerHTML;
		}

		if(prefix.length === 0 && form.qs('select[name="country"]')) {
			form.qs('select[name="country"]').value = MainPlace.FR;
		}
		if(prefix.length > 0 && form.qs('select[name="'+ prefix +'Country"]')) {
			form.qs('select[name="'+ prefix +'Country"]').value = MainPlace.FR;
		}

		if(form.qs('input[name="siren"]') && form.qs(querySelector +'-siren')) {
			form.qs('input[name="siren"]').value = form.qs(querySelector +'-siren').innerHTML;
		}
		if(form.qs('input[name="siret"]') && form.qs(querySelector +'-siret')) {
			form.qs('input[name="siret"]').value = form.qs(querySelector +'-siret').innerHTML;
		}

		form.qs('.'+ type +'-found').hide();

	}

	static queryFound(type, wrapper, result) {

		wrapper.qs('.'+ type +'-name').innerHTML = result.legalName;
		wrapper.qs('.'+ type +'-street1').innerHTML = result.legalStreet1;
		wrapper.qs('.'+ type +'-street2').innerHTML = result.legalStreet2;
		wrapper.qs('.'+ type +'-postcode').innerHTML = result.legalPostcode;
		wrapper.qs('.'+ type +'-city').innerHTML = result.legalCity;

		if(wrapper.qs('.'+ type +'-siren')) {
			wrapper.qs('.'+ type +'-siren').innerHTML = result.siren;
		}
		if(wrapper.qs('.'+ type +'-siret')) {
			wrapper.qs('.'+ type +'-siret').innerHTML = result.siret;
		}

		wrapper.qs('.'+ type +'-found').removeHide();
		wrapper.qs('.'+ type +'-unknown').hide();

		return false;

	}

	static queryNotFound(type, wrapper) {

		wrapper.qs('.'+ type +'-found').hide();
		wrapper.qs('.'+ type +'-unknown').removeHide();

		return false;

	}

	static updateMap(container, lngLat, options) {

		let wrapper = qs('#'+ container);

		// Champs qui n'ont de sens qu'une fois le lieu positionné
		wrapper.firstParent('form').qsa('.place-located', node => lngLat === null ? node.hide() : node.removeHide());

		if(lngLat !== null) {

			wrapper.style.display = 'block';

			let drawer = new MapboxContainer(container);

			if(drawer.exists()) {

				const mapbox = drawer.get();
				const marker = mapbox.marker.getLngLat();

				// Le repère vient d'être déplacé à la main, inutile de recentrer la carte sous le curseur
				if(marker.lng !== lngLat[0] || marker.lat !== lngLat[1]) {
					mapbox.setCenter(lngLat);
					mapbox.marker.setLngLat(lngLat);
				}

			} else {

				if(options === undefined) {
					options = {};
				}

				options.center = lngLat;

				if(options.zoom === undefined) {
					options.zoom = 11;
				}

				const mapbox = drawer.create(options);
				const draggable = wrapper.hasAttribute('data-place-draggable');

				mapbox.marker = new mapboxgl.Marker({draggable: draggable})
					.setLngLat(lngLat)
					.addTo(mapbox);

				if(draggable) {
					mapbox.marker.on('dragend', () => this.moveMarker(container, mapbox.marker.getLngLat()));
				}

			}

		} else {
			wrapper.style.display = 'none';
		}

	}

	static moveMarker(container, position) {

		const input = qs('input[data-autocomplete-map="'+ container +'"]');
		const lngLat = [position.lng, position.lat];

		new Ajax.Query()
			.url('/main/place:citiesByLngLat')
			.body({
				lng: lngLat[0],
				lat: lngLat[1]
			})
			.fetch()
			.then((json) => {

				const city = json.city ?? input.value;

				// AutocompleteField ne vide le champ caché que lors d'une frappe au clavier
				qs('#'+ input.dataset.autocompleteItems, node => node.innerHTML = '');

				// Déclenche autocompleteSelect, qui repositionne la carte et l'altitude
				AutocompleteField.apply(input, {
					value: city,
					itemText: city,
					lngLat: lngLat
				});

			});

	}

	static updateAltitude(input, lngLat) {

		const altitude = input.firstParent('form').qs('input[name="altitude"]');

		if(altitude === null) {
			return;
		}

		new Ajax.Query()
			.url('/main/place:altitudeByLngLat')
			.body({
				lng: lngLat[0],
				lat: lngLat[1]
			})
			.fetch()
			.then((json) => {

				if(json.altitude !== null) {
					altitude.value = json.altitude;
				}

			});

	}

}
