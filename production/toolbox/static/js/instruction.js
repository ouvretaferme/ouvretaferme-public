new Lime.Instruction('toolbox')
	/**
	 * Un relevé qui sort de l'ordre du compteur revient en question (`EquipmentReadingLib::getConflictFromFail()`) :
	 * le formulaire repart avec `meterConfirmed` si la personne confirme, par le `data-confirm` du cadriciel
	 * posé le temps d'un clic. Le clic envoie le formulaire sans attendre (`submitAjaxForm()` lit les champs
	 * dans l'événement `submit`), d'où la remise à zéro juste après : un envoi suivant, valeur changée, pose
	 * de nouveau la question.
	 */
	.register('confirmReading', function(message) {

		const form = this.context;
		const confirmed = form.qs('[name="meterConfirmed"]');
		const submit = form.qs('[type="submit"]');

		confirmed.value = '1';
		submit.setAttribute('data-confirm', message);

		submit.click();

		confirmed.value = '0';
		submit.removeAttribute('data-confirm');

	});
