// La dernière ligne d'une table de la page d'une aide ouvre à sa place la ligne de saisie qui la suit,
// et « Annuler » la referme (aid\AdminUi::getAddLink()).
document.delegateEventListener('click', '#aid-admin [data-aid-admin-add]', function() {

	const entry = this.nextElementSibling;

	this.classList.add('hide');
	entry.classList.remove('hide');

	entry.qs('input:not([type="hidden"]), select')?.focus();

});

document.delegateEventListener('click', '#aid-admin [data-aid-admin-cancel]', function() {

	const entry = this.closest('.util-table-add-entry');

	entry.classList.add('hide');
	entry.previousElementSibling.classList.remove('hide');

});
