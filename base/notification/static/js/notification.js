/*
 * La cloche ouvre son menu une fois son contenu arrivé, comme Lime.Quick : Lime.Dropdown place la
 * liste d'après sa taille au moment où il l'ouvre (Lime.Stick autour du bouton, un centrage calculé
 * une fois en plein écran tactile), et un contenu chargé après l'ouverture resterait mal placé.
 * La cloche ne porte donc pas data-dropdown : c'est ce délégué qui ouvre, et Lime.Dropdown qui ferme.
 */
class NotificationBell {

	static toggle(button) {

		if(Lime.Dropdown.isOpen(button)) {
			return;
		}

		/*
		 * Le menu se charge une fois par page (décidé le 22/09) : le charger marque vues les
		 * notifications, et un second chargement réunirait sous les yeux de l'utilisateur les nouvelles
		 * qu'il vient de voir à part. Il se recharge après un changement de page (reset()).
		 */
		if(button.dataset.notificationLoaded !== undefined) {
			this.open(button);
			return;
		}

		new Ajax.Query(button)
			.method('get')
			.url(this.withSection(button.dataset.notificationBell))
			.fetch()
			.then(() => {
				button.dataset.notificationLoaded = '';
				this.open(button);
			});

	}

	static open(button) {

		button.addEventListener('dropdownBeforeShow', e => this.fit(button, e.detail.list), {once: true});
		Lime.Dropdown.open(button, 'bottom-end');

	}

	/*
	 * La barre n'est pas redessinée par une navigation ajax : sans cette remise à zéro, la cloche
	 * rouvrirait sur une autre page le menu chargé sur la précédente.
	 */
	static reset() {

		qsa('[data-notification-bell]', button => delete button.dataset.notificationLoaded);

	}

	/*
	 * Lime.Stick aligne le bord droit du menu sur celui de la cloche, décalé de 5 px vers la droite par
	 * défaut (lime.js, openAround()), et ne le ramène jamais dans l'écran. La cloche est le dernier
	 * élément de la barre : sur un écran étroit, le menu sortirait de ces 5 px à droite, et un menu plus
	 * large que la place à gauche de la cloche sortirait à gauche. Dans les deux cas, le décalage cale
	 * son bord droit à 0,5 rem du bord de l'écran ; sinon Lime.Dropdown garde son décalage par défaut.
	 * dropdownBeforeShow tombe après que la liste a reçu dropdown-list-open, donc sa largeur finale, et
	 * avant qu'openAround() ne lise data-dropdown-offset-x. Le choix entre « end » et « start » reprend
	 * celui de Lime.Stick.move() : en « start », quand la barre est latérale, le menu part de la gauche
	 * de la cloche vers le contenu et ne déborde pas.
	 */
	static fit(button, list) {

		const margin = rem() / 2;
		const width = list.getBoundingClientRect().width;
		const bounds = button.getBoundingClientRect();
		const right = document.documentElement.clientWidth - margin;

		const alignedEnd = (bounds.left - width > 0 || bounds.left >= window.innerWidth / 2);

		if(alignedEnd && (bounds.right + 5 > right || bounds.right + 5 - width < margin)) {
			button.dataset.dropdownOffsetX = (right - bounds.right) / rem();
		} else {
			delete button.dataset.dropdownOffsetX;
		}

	}

	/*
	 * Le centre s'affiche sous la section d'où l'on vient : le lien la porte en paramètre, lue au
	 * moment du geste. Pas plus tôt, parce que la cloche est rendue une fois avec la barre et que la
	 * navigation ajax change de section sans la redessiner. Le gabarit tient la section courante dans
	 * body[data-template] (« structure-production »…), comme le lit Structure dans structure.js ;
	 * « structure-section-production », posé au survol de la rangée de sections, n'y ressemble pas.
	 */
	static withSection(url) {

		const templates = document.body.dataset.template.split(' ');
		const node = Array.from(qsa('#structure-nav-sections [data-section]')).find(node => templates.includes('structure-'+ node.dataset.section));

		if(node === undefined) {
			return url;
		}

		const target = new URL(url, location.origin);
		target.searchParams.set('section', node.dataset.section);

		return target.pathname + target.search;

	}

}

/*
 * La cloche est un lien vers le centre, pour qu'un clic avec ctrl ou cmd l'ouvre dans un onglet. Elle
 * porte data-ajax-navigation="never" : sans lui, le délégué de navigation de ajax.js, déclaré avant
 * celui-ci sur tout a[href], chargerait le centre avant que ce délégué ne tourne.
 */
document.delegateEventListener('click', '[data-notification-bell]', function(e) {

	if(e.ctrlKey || e.metaKey) {
		this.href = NotificationBell.withSection(this.href);
		return;
	}

	e.preventDefault();

	NotificationBell.toggle(this);

});

document.addEventListener('navigation.sleep', () => NotificationBell.reset());

/* Le clic du milieu ne passe pas par click : il ouvre le centre dans un onglet, avec la même section. */
document.delegateEventListener('auxclick', '[data-notification-bell]', function() {
	this.href = NotificationBell.withSection(this.href);
});
