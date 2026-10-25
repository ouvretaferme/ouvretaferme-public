document.delegateEventListener('click', '[data-action="apiKey-log-expand"]', function(e) {

	const response = qs('#apiKey-log-response-' + this.dataset.log);

	// La réponse n'est demandée qu'à la première ouverture, les replis suivants basculent le contenu déjà chargé
	if(response.innerHTML === '') {

		new Ajax.Query(this)
			.url(this.dataset.url)
			.method('get')
			.fetch();

		return;

	}

	response.classList.toggle('hide');
	this.classList.toggle('apiKey-log-expanded');

});
