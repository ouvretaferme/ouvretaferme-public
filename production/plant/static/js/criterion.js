class PlantCriterion {

	/**
	 * Au plafond, on désactive les cases non cochées plutôt que de refuser le clic :
	 * la limite se voit avant d'être atteinte, pas après.
	 *
	 * Le conteneur est repéré par data-criteria-max et non par un identifiant : les
	 * cases s'affichent dans le panneau de modification d'une espèce, où la matrice
	 * n'est pas servie.
	 */
	static refresh(container) {

		const max = parseInt(container.dataset.criteriaMax);
		const boxes = [...container.qsa('input[type="checkbox"]')];
		const checked = boxes.filter(node => node.checked).length;

		boxes.forEach(node => node.disabled = (checked >= max && node.checked === false));

	}

	/**
	 * Poser ou retirer un critère écrit aussitôt, sans bouton d'enregistrement.
	 *
	 * La liste complète est postée et non le seul critère touché : c'est build() qui
	 * valide la colonne, y compris son plafond, et il attend la valeur entière. Une
	 * liste vidée ne poste aucun champ, ce que build() lit comme un tableau vide.
	 */
	static toggle(cell) {

		const species = cell.closest('tr').qs('.plant-criterion-species');
		const criterion = parseInt(cell.dataset.criterion);
		const criteria = JSON.parse(species.dataset.criteria);

		const position = criteria.indexOf(criterion);

		if(position === -1) {
			criteria.push(criterion);
		} else {
			criteria.splice(position, 1);
		}

		const body = new FormData();
		body.set('id', species.dataset.plant);

		criteria.forEach(id => body.append('criteria[]', id));

		new Ajax.Query(cell)
			.url('/plant/criterion:doToggle')
			.body(body)
			.fetch();

	}

	/**
	 * Le survol éclaire toute la colonne et détache son libellé : sur dix-sept
	 * colonnes, le nom du critère est trop loin de la case pour qu'on sache lequel
	 * on s'apprête à poser.
	 *
	 * La bande des familles est écartée du parcours : elle fusionne ses colonnes, et
	 * son n-ième enfant ne désigne pas la n-ième colonne.
	 */
	static light(table, index) {

		table.qsa('.plant-criterion-column', node => node.classList.remove('plant-criterion-column'));

		if(index === null) {
			return;
		}

		table.qsa('thead tr:last-child, tbody tr', row => {

			const cell = row.children[index];

			if(cell !== undefined) {
				cell.classList.add('plant-criterion-column');
			}

		});

	}

}

document.delegateEventListener('mouseover', '[data-action="plant-criterion-toggle"]', (e) => {
	const cell = e.delegateTarget.closest('td');
	PlantCriterion.light(cell.closest('table'), cell.cellIndex);
});

document.delegateEventListener('mouseleave', '.plant-criterion-matrix', (e) => {
	PlantCriterion.light(e.delegateTarget, null);
});

document.delegateEventListener('change', '[data-criteria-max] input[type="checkbox"]', (e) => {
	PlantCriterion.refresh(e.target.closest('[data-criteria-max]'));
});

document.delegateEventListener('click', '[data-action="plant-criterion-toggle"]', (e) => {

	e.preventDefault();

	if(e.delegateTarget.classList.contains('is-full')) {
		return;
	}

	PlantCriterion.toggle(e.delegateTarget);

});

document.addEventListener('navigation.wakeup', () => {
	qsa('[data-criteria-max]', node => PlantCriterion.refresh(node));
});
