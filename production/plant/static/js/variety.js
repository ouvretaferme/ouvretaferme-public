class PlantVariety {

	/**
	 * Le stock se range toujours en graines : changer d'unité ne change que la façon
	 * de le lire, donc le nombre affiché doit suivre, faute de quoi 333 graines
	 * deviendraient 333 grammes au premier changement de menu.
	 *
	 * L'unité de départ est portée par le menu lui-même : un <select> ne dit pas
	 * quelle valeur il avait avant le changement.
	 */
	static convertStock(select) {

		const unitFrom = select.dataset.unit;
		const unitTo = select.value;

		select.dataset.unit = unitTo;

		const input = select.closest('.input-group').qs('input[type="number"]');
		const weight = parseFloat(select.dataset.weight);
		const value = parseFloat(input.value);

		if(unitFrom === unitTo || isNaN(weight) || isNaN(value)) {
			return;
		}

		if(unitTo === 'gram') {
			input.value = Math.round(value * weight / 1000 * 100) / 100;
		} else {
			input.value = Math.round(value * 1000 / weight);
		}

	}

}
