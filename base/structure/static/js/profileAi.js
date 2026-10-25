/*
 * L'infobulle des fiches n'existe pas au doigt, et ce n'est pas un repli : elle ne se pose pas.
 *
 * Le délégué de clic de Lime.Dropdown (lime.js) annule le clic dès isTouch(), quoi que dise
 * data-dropdown-hover. Laisser l'attribut sur tactile remplacerait donc la navigation vers la
 * fiche par un aperçu qui en montre moins, sur le seul appareil où le survol n'existe pas.
 */
document.addEventListener('navigation.wakeup', function() {

	if(isTouch() === false) {
		return;
	}

	document.querySelectorAll('.profile-name [data-dropdown]').forEach(function(node) {
		delete node.dataset.dropdown;
		delete node.dataset.dropdownHover;
	});

});
