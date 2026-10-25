// Le choix d'une espèce dans son champ (`FertilizationUi::getCrop()`), qui ne connait pas d'`onchange`.
document.delegateEventListener('autocompleteSelect', '#fertilization-crop-query', function() {
	ToolboxFertilization.changeCrop(this);
});

class ToolboxFertilization {

	static timer = null;
	static running = false;
	static pending = false;

	/**
	 * Une frappe relit le résultat, comme dans le calculateur de densités. Un sélecteur a son propre
	 * geste (`changeCrop`, `changeMaterial`), qui relit d'abord ce qu'il porte : son événement `input`
	 * n'est donc pas une saisie. La frappe dans le champ de l'espèce non plus : elle cherche, et le
	 * choix d'une espèce a son geste.
	 */
	static input(form, event) {

		if(event.target.matches('select, [data-autocomplete-field]')) {
			return;
		}

		this.refresh(form);

	}

	/**
	 * Le résultat se relit un quart de seconde après la dernière frappe. `Ajax.Query` verrouille le
	 * formulaire pendant la requête et lève `LOCK_FAILED` sur la suivante : une saisie arrivée en vol
	 * est donc gardée, et repart dès le retour, avec les valeurs du moment.
	 */
	static refresh(form) {

		clearTimeout(this.timer);
		this.timer = setTimeout(() => this.send(form), 250);

	}

	static send(form) {

		if(this.running) {
			this.pending = true;
			return;
		}

		this.running = true;

		const done = () => {

			this.running = false;

			if(this.pending) {
				this.pending = false;
				this.send(form);
			}

		};

		new Ajax.Query(form)
			.url('/toolbox/fertilization:calculate')
			.body(new FormData(form))
			.fetch()
			.then(done, done);

	}

	/**
	 * Une autre espèce, ou un autre rendement : le serveur rend le bloc de la culture, puis le
	 * résultat se relit.
	 */
	static changeCrop(target) {

		const form = target.firstParent('form');

		new Ajax.Query()
			.url('/toolbox/fertilization:crop')
			.body({
				id: form.qs('[name="id"]').value,
				crop: form.qs('[name="crop"]').value,
				yield: form.qs('[name="yield"]')?.value ?? ''
			})
			.fetch()
			.then(() => this.refresh(form));

	}

	/**
	 * L'espèce ou la saisie manuelle : `Lime.Tab.select()` désactive les champs de l'autre onglet,
	 * qui ne se postent plus, puis le résultat se relit sur ceux de l'onglet choisi.
	 */
	static changeMode(tab) {

		Lime.Tab.select(tab);

		this.refresh(tab.firstParent('form'));

	}

	static changeMaterial(select) {

		const form = select.firstParent('form');
		const index = select.firstParent('[data-index]').dataset.index;

		this.material(form, index, select.value, false);

	}

	/**
	 * Un complément s'ajoute à la suite des autres, à un index que l'écran n'a pas encore, sans matière
	 * choisie.
	 */
	static add(button) {

		const form = button.firstParent('form');
		const indexes = Array.from(form.qsa('[data-index]')).map(node => parseInt(node.dataset.index));

		this.material(form, Math.max(...indexes) + 1, '', true);

	}

	static remove(button) {

		const form = button.firstParent('form');

		button.firstParent('[data-index]').remove();

		this.refresh(form);

	}

	static material(form, index, material, add) {

		new Ajax.Query()
			.url('/toolbox/fertilization:material')
			.body({
				id: form.qs('[name="id"]').value,
				index: index,
				material: material,
				add: add ? 1 : 0
			})
			.fetch()
			.then(() => this.refresh(form));

	}

}

// Un champ du calculateur porte souvent une valeur, qu'on remplace plus souvent qu'on ne la corrige :
// la cliquer la sélectionne entière, et la frappe suivante la remplace.
document.delegateEventListener('focus', '#fertilization-form input[type="number"]', function() {
	this.select();
});
