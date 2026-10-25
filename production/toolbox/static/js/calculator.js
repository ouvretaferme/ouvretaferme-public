/**
 * Ce que les calculateurs de la Boite à outils partagent (`calculator.css` pour la forme).
 */
class ToolboxCalculator {

	/**
	 * Le choix de la planche ou de la surface libre (`DensityUi::getSurface()`) montre le panneau de ses
	 * champs et désactive le `fieldset` de l'autre, qui ne poste plus rien. Le radio est une saisie :
	 * l'événement `input` du formulaire relit déjà le résultat. `FormUi::radios()` pose `onchange`
	 * sur le groupe et non sur chaque radio, d'où la lecture de la valeur cochée, comme
	 * `Series.updateArea()`. Rend cette valeur.
	 */
	static changeUse(target) {

		const form = target.firstParent('form');
		const use = form.qs('input[name="use"]:checked').value;

		form.qsa('.calculator-surface .tab-panel', panel => {

			const selected = (panel.dataset.tab === use);

			panel.classList.toggle('selected', selected);
			panel.qs('fieldset').disabled = (selected === false);

		});

		return use;

	}

}
