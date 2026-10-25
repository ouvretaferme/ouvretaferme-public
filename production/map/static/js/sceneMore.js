/**
 * Le menu ⋮ de la rangée des réglages de la scène. Un bouton qui ne tient plus dans la rangée y entre, et en ressort dès que la
 * place revient : rien ne se règle à une largeur d'écran, la rangée se mesure, parce que ce qu'elle porte change avec
 * la pose et les droits de celui qui regarde.
 *
 * Un bouton rangé reste dans la page, hors du flux et invisible (`map-scene-folded`) : sa boite se mesure encore, et son entrée
 * du menu ne fait que le cliquer. Aucun geste n'est donc écrit deux fois, et l'état allumé d'un bouton se lit sur lui.
 */
class MapSceneMore {

	// L'ordre d'entrée dans le menu, le premier part le premier. Le plein écran n'y est pas : il ne quitte jamais la rangée
	static ORDER = ['avatar', 'photo', 'decor', 'layers'];

	static start(more) {
		new MapSceneMore(more);
	}

	constructor(more) {

		this.more = more;
		this.list = more.nextElementSibling;
		this.screen = more.parentNode;
		this.bar = this.screen.parentNode;
		this.container = this.bar.parentNode;
		this.zone = this.bar.qs('.map-scene-zone');

		// Sans le droit de poser, la rangée n'a pas de bouton Décor
		this.entries = MapSceneMore.ORDER
			.map(key => ({ button: this.screen.qs('[data-scene-'+ key +']'), item: this.list.qs('[data-scene-more-item="'+ key +'"]') }))
			.filter(entry => entry.button !== null);

		const light = new MutationObserver(() => this.light());

		this.entries.forEach(entry => {
			entry.item.addEventListener('click', () => entry.button.click());
			light.observe(entry.button, { attributes: true, attributeFilter: ['class'] });
		});

		// Un menu en plein écran se ferme par l'historique, donc après coup : aucune lecture faite au moment de le fermer ne le voit
		// fermé, seul l'attribut que le cadriciel garde sur le bouton tant que la liste est ouverte le dit
		light.observe(this.more, { attributes: true, attributeFilter: ['data-dropdown-display'] });

		this.more.addEventListener('dropdownAfterShow', event => SceneDropdown.follow(this.container, this.more, event.detail.list));

		// Un bouton qui paraît, disparait ou perd son libellé change de taille : la taille de chaque occupant de la rangée
		// dit donc tout ce qui peut la faire déborder. Un bouton rangé garde la sienne, ce qui épargne à la mesure de se
		// relancer elle-même. Ranger un bouton change la taille de la rangée, observée elle aussi : écrire dans la passe de
		// l'observateur lui ferait signaler une boucle, d'où l'image suivante
		this.frame = null;

		const fold = new ResizeObserver(() => {
			this.frame ??= requestAnimationFrame(() => {
				this.frame = null;
				this.fold();
			});
		});

		[this.bar, ...this.bar.children, ...this.screen.children].forEach(element => fold.observe(element));

	}

	/**
	 * Toutes les lectures, puis toutes les écritures : la rangée n'est mise en page qu'une fois.
	 */
	fold() {

		const bar = getComputedStyle(this.bar);

		// La rangée entière est retirée pendant la photo
		if(bar.display === 'none') {
			return;
		}

		const shown = element => (getComputedStyle(element).display !== 'none');
		const width = element => element.getBoundingClientRect().width;

		// Sous 768 px, la rangée passe à la ligne : la liste du lieu prend la première, et les boutons toute la largeur de la suivante
		const single = (bar.flexWrap === 'nowrap');
		const beside = (single && this.zone !== null && shown(this.zone));
		const room = this.bar.clientWidth - parseFloat(bar.paddingLeft) - parseFloat(bar.paddingRight) - (beside ? width(this.zone) + parseFloat(bar.columnGap) : 0);

		const gap = parseFloat(getComputedStyle(this.screen).columnGap);
		const candidates = this.entries.filter(entry => shown(entry.button));
		const kept = [...this.screen.children].filter(element => element !== this.more && shown(element) && candidates.some(entry => entry.button === element) === false);

		const fixed = kept.reduce((sum, element) => sum + width(element), 0);

		const widths = candidates.map(entry => width(entry.button));
		const more = width(this.more);

		const need = folded => fixed
			+ widths.slice(folded).reduce((sum, value) => sum + value, 0)
			+ (folded > 0 ? more : 0)
			+ gap * (kept.length + widths.length - folded + (folded > 0 ? 1 : 0) - 1);

		let folded = 0;

		while(folded < candidates.length && need(folded) > room + .5) {
			folded++;
		}

		const open = Lime.Dropdown.isOpen(this.more);

		candidates.forEach((entry, index) => {
			entry.button.classList.toggle('map-scene-folded', index < folded);
		});

		this.entries.forEach(entry => {
			entry.item.classList.toggle('hide', candidates.indexOf(entry) === -1 || candidates.indexOf(entry) >= folded);
		});

		this.more.classList.toggle('map-scene-folded', folded === 0);

		if(folded === 0 && open) {
			Lime.Dropdown.close(this.more);
		}

		this.light();

	}

	/**
	 * Un bouton allumé le reste dans le menu, et ⋮ s'allume pour lui tant qu'il y est rangé. Menu ouvert, ⋮ est allumé comme le
	 * bouton Calques quand sa carte est ouverte.
	 */
	light() {

		const lit = this.entries.map(entry => entry.button.classList.contains('btn-selected'));
		const folded = this.entries.map(entry => entry.button.classList.contains('map-scene-folded'));

		this.entries.forEach((entry, index) => {
			entry.item.classList.toggle('selected', lit[index]);
		});

		this.more.classList.toggle('btn-selected', Lime.Dropdown.isOpen(this.more) || lit.some((on, index) => on && folded[index]));

	}

}
