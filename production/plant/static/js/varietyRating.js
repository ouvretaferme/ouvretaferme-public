class VarietyRating {

	/**
	 * Les étoiles ne portent pas la valeur soumise : le champ caché reste vide
	 * tant qu'aucune étoile n'est cliquée, et le critère part à NULL.
	 */
	static select(star) {

		const criterion = star.firstParent('.variety-rating-criterion');
		const value = parseInt(star.dataset.star);

		criterion.dataset.value = value;
		criterion.qs('input[type="hidden"]').value = value;

		this.paint(criterion, value);

	}

	static clear(criterion) {

		criterion.dataset.value = '';
		criterion.qs('input[type="hidden"]').value = '';

		this.paint(criterion, 0);

	}

	static paint(criterion, value) {

		criterion.qsa('.variety-rating-star', node => {
			node.classList.toggle('variety-rating-star-on', parseInt(node.dataset.star) <= value);
		});

	}

	static preview(criterion, value) {

		// data-preview porte le niveau survolé : le CSS s'en sert pour teinter
		// l'aperçu de la couleur qu'aura la note si on clique.
		criterion.dataset.preview = value;

		criterion.qsa('.variety-rating-star', node => {
			node.classList.toggle('variety-rating-star-hover', parseInt(node.dataset.star) <= value);
		});

	}

	/**
	 * La note globale donnée depuis une ligne repliée : elle s'écrit sans bouton
	 * d'enregistrement et ouvre le tiroir, où elle est déjà posée.
	 *
	 * Le contrôle est peint ici plutôt qu'attendu de la réponse : la réponse ne
	 * rend que le tiroir, et la ligne n'a pas à repartir du serveur pour cinq
	 * étoiles dont on connaît déjà l'état.
	 */
	static rateOverall(criterion, value) {

		const line = criterion.firstParent('.variety-line');

		criterion.dataset.value = value;
		this.paint(criterion, value);

		const body = new FormData();

		body.set('id', line.dataset.variety);
		body.set('ratingOverall', value);

		this.closeDrawers();

		new Ajax.Query(line)
			.url('/plant/varietyRating:doRateOverall')
			.body(body)
			.fetch();

	}

	/**
	 * Le détail d'une variété. Un seul tiroir ouvert à la fois : deux formulaires
	 * ouverts sur la même liste laisseraient croire qu'on peut les enregistrer
	 * ensemble, alors que chacun écrit sa variété.
	 */
	static toggle(line) {

		const drawer = document.qs('#variety-drawer-' + line.dataset.variety);

		this.closeDrawers();

		if(drawer !== null) {
			return;
		}

		// Lecture, donc GET : Ajax.Query poste par défaut, et l'action est déclarée
		// en lecture. Les paramètres passent alors par l'URL, jamais par un body.
		new Ajax.Query(line)
			.method('GET')
			.url('/plant/varietyRating:drawer?id=' + line.dataset.variety)
			.fetch();

	}

	static closeDrawers() {

		document.qsa('.variety-drawer', node => node.remove());

	}

	/**
	 * Les variétés au-delà des premières sont déjà dans la page : les montrer ne
	 * demande rien au serveur.
	 */
	static more(link) {

		link.firstParent('.variety-plant').classList.add('is-expanded');

	}

	/**
	 * Changer d'espèce, c'est repartir sur une autre liste : l'URL ne garde que
	 * l'espèce, et le tiroir ouvert n'a plus de ligne où revenir.
	 */
	static changePlant(select) {

		new Ajax.Navigation()
			.url(select.dataset.url + (select.value ? '?plant=' + select.value : ''))
			.fetch();

	}

}

document.delegateEventListener('click', '.variety-rating-star', function(e) {

	e.preventDefault();

	const criterion = this.firstParent('.variety-rating-criterion');

	if(criterion.firstParent('.variety-line') !== null) {
		VarietyRating.rateOverall(criterion, parseInt(this.dataset.star));
	} else {
		VarietyRating.select(this);
	}

});

document.delegateEventListener('mouseenter', '.variety-rating-star', function() {

	VarietyRating.preview(this.firstParent('.variety-rating-criterion'), parseInt(this.dataset.star));

});

document.delegateEventListener('mouseleave', '.variety-rating-stars', function() {

	VarietyRating.preview(this.firstParent('.variety-rating-criterion'), 0);

});

document.delegateEventListener('click', '[data-action="variety-rating-clear"]', function(e) {

	e.preventDefault();

	VarietyRating.clear(this.firstParent('.variety-rating-criterion'));

});

/**
 * La ligne entière ouvre son tiroir : viser un chevron de dix-sept pixels n'est
 * pas un geste, surtout au doigt.
 *
 * Sauf sur ce qui agit déjà : les étoiles notent, la croix ferme. closest() et non
 * firstParent(), qui part du parent et laisserait passer un clic tombé pile sur le
 * contrôle lui-même.
 */
document.delegateEventListener('click', '.variety-line', function(e) {

	if(e.target.closest('[data-action], .variety-rating-criterion') !== null) {
		return;
	}

	VarietyRating.toggle(this);

});

document.delegateEventListener('click', '[data-action="variety-rating-close"]', function(e) {

	e.preventDefault();

	VarietyRating.closeDrawers();

});

document.delegateEventListener('click', '[data-action="variety-rating-more"]', function(e) {

	e.preventDefault();

	VarietyRating.more(this);

});
