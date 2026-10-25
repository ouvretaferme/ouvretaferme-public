// « Un autre département », à côté du titre de l'outil : le choix mène à la liste dans ce département,
// dont l'adresse vient avec le résultat (AidUi::getDepartments()).
document.delegateEventListener('autocompleteSelect', '[data-autocomplete-field="aidDepartment"]', function(e) {

	if(e.detail.value === '') {
		return;
	}

	new Ajax.Navigation().url(e.detail.url).fetch();

});
