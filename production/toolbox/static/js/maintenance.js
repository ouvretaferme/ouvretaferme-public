// « Suivre un matériel » : un nom absent de « Mon matériel » arrive avec la valeur `new`, et part dans
// `toolName` pour que le matériel soit créé au même geste (arbitrage Q5 A de la planche pf-t4-v2).
document.delegateEventListener('autocompleteSelect', '[data-autocomplete-id="equipment-tool"]', function(e) {

	const creating = (e.detail.value === 'new');

	this.firstParent('form').qs('[name="toolName"]', node => node.value = creating ? e.detail.itemText : '');

});

class ToolboxMaintenance {

	static toggle(node) {

		node.firstParent('.util-deck').classList.toggle('util-deck-open');

	}

	/**
	 * Un matériel sans compteur n'a ni immatriculation ni premier relevé à donner, et le relevé d'un
	 * matériel à compteur se dit dans l'unité choisie.
	 */
	static changeMeter(node) {

		const form = node.firstParent('form');
		const meter = form.qs('[name="meter"]:checked')?.value ?? '';

		form.qsa('[data-meter]', group => {

			group.classList.toggle('hide', meter === '');

			if(meter !== '') {
				group.qs('.input-group-addon[data-unit-' + meter + ']', unit => unit.textContent = unit.getAttribute('data-unit-' + meter));
			}

		});

	}

	/**
	 * « Régulièrement » ouvre la fréquence, « Une seule fois » la ferme : elle est alors oubliée à
	 * l'envoi (`Maintenance::properties()`).
	 */
	static changeFrequency(node) {

		const form = node.firstParent('form');
		const regular = (form.qs('[name="frequency"]:checked')?.value === 'regular');

		form.qs('[data-frequency="regular"]', group => group.classList.toggle('hide', regular === false));

	}

	/**
	 * « Mettre à jour le compteur » : Oui montre le compteur, Non le cache et l'échéance
	 * repart du compteur lu dans les relevés.
	 */
	static changeMeterUpdate(node) {

		const form = node.firstParent('form');
		const update = (form.qs('[name="meterUpdate"]:checked')?.value === '1');

		form.qs('[data-meter-update="value"]', group => group.classList.toggle('hide', update === false));

		// Une intervention ou un entretien « Une seule fois » n'ont pas de « Prochaine fois ».
		if(form.qs('#maintenance-done-next')) {
			this.refreshNext(node);
		}

	}

	static refreshNextTimeout = null;
	static refreshNextRunning = false;
	static refreshNextAgain = false;

	/**
	 * « Prochaine fois » suit la date de l'entretien fait, et le compteur quand il est mis à jour. Le
	 * compteur la relit à la frappe : la requête attend que la saisie se pose, pour ne pas partir à
	 * chaque chiffre.
	 */
	static refreshNext(field) {

		const form = field.firstParent('form');

		clearTimeout(this.refreshNextTimeout);
		this.refreshNextTimeout = setTimeout(() => this.fetchNext(form), 300);

	}

	/**
	 * Une requête à la fois, et la dernière saisie toujours relue : une saisie arrivée pendant une
	 * requête en relance une à son retour, si bien qu'une réponse ancienne ne reste jamais affichée.
	 * La requête n'a pas le formulaire pour contexte : son verrou refuserait la relance au lieu de
	 * l'attendre, et une relecture n'a ni bouton à bloquer ni erreur à effacer.
	 */
	static fetchNext(form) {

		if(this.refreshNextRunning) {
			this.refreshNextAgain = true;
			return;
		}

		this.refreshNextRunning = true;

		const done = () => {

			this.refreshNextRunning = false;

			if(this.refreshNextAgain) {
				this.refreshNextAgain = false;
				this.fetchNext(form);
			}

		};

		new Ajax.Query()
			.url('/toolbox/maintenanceDone:next')
			.body(new FormData(form))
			.fetch()
			.then(done, done);

	}

}
