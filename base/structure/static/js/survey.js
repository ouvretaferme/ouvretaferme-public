/**
 * La liste de ce qui manque au logiciel, dans l'enquête des adhérents : une ligne par besoin, la
 * première étant la priorité. L'ordre des lignes est celui qui part avec le formulaire, il n'y a
 * donc rien à enregistrer quand une ligne monte.
 */
class StructureSurvey {

	static addMissing(button) {

		const table = qs('#structure-survey-missing');

		table.insertAdjacentHTML('beforeend', qs('#structure-survey-missing-template').innerHTML);

		StructureSurvey.refresh(table);

		table.lastElementChild.qs('input').focus();

	}

	static upMissing(button) {

		const row = button.firstParent('[data-missing]');
		const previous = row.previousElementSibling;

		if(previous !== null) {
			previous.before(row);
		}

	}

	/**
	 * La dernière ligne ne se retire pas, elle se vide : la liste garde toujours un champ où écrire.
	 */
	static removeMissing(button) {

		const table = qs('#structure-survey-missing');
		const row = button.firstParent('[data-missing]');

		if(table.qsa('[data-missing]').length === 1) {
			row.qs('input').value = '';
		} else {
			row.remove();
		}

		StructureSurvey.refresh(table);

	}

	static refresh(table) {

		const max = parseInt(table.dataset.max);

		qs('#structure-survey-missing-add').classList.toggle('hide', table.qsa('[data-missing]').length >= max);

	}

}
