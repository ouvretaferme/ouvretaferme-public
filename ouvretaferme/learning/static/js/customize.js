/**
 * La barre de personnalisation des pages publiques d'un réseau (accueil, formations, relevés de
 * prix), sur le modèle de celles de la boutique et du site : chaque couleur essayée recharge la page
 * avec les réglages dans l'adresse, les polices se montrent sans recharger (`Customize.setFont()`),
 * et l'enregistrement rejoue l'état complet que porte `data-settings`.
 */
class LearningCustomize {

	static get scrollKey() {
		return 'learning-customize-scroll';
	}

	static get panelKey() {
		return 'learning-customize-folded';
	}

	// Le réglage essayé part avec tous les autres, tels que `data-settings` les porte : le serveur
	// les contrôle comme à l'enregistrement, et répond par un refus affiché ou par l'adresse de
	// l'aperçu. Un réglage remis à zéro part vide : absent, la page rendrait la valeur enregistrée.
	static set(property, value) {

		const bar = qs('#learning-customize-bar');

		const settings = JSON.parse(bar.dataset.settings);
		settings[property] = value;

		sessionStorage.setItem(this.scrollKey, String(window.scrollY));

		// Refusé, l'essai laisse la page telle quelle : le sélecteur reprend la couleur qu'elle montre,
		// que son attribut `value` garde. Accepté, la page se recharge de toute façon.
		new Ajax.Query(bar)
			.method('post')
			.url(bar.dataset.preview)
			.body(this.getBody(settings))
			.fetch()
			.then(() => bar.querySelectorAll('input[type="color"]').forEach(input => input.value = input.defaultValue));

	}

	static getBody(settings) {

		const body = new FormData();

		for(const [key, value] of Object.entries(settings)) {
			body.append(key, value === null ? '' : String(value));
		}

		return body;

	}

	static toggle() {

		const bar = qs('#learning-customize-bar');

		bar.classList.toggle('customize-bar-folded');

		sessionStorage.setItem(this.panelKey, bar.classList.contains('customize-bar-folded') ? '1' : '0');

	}

	static cancel() {

		sessionStorage.removeItem(this.scrollKey);

		window.location.href = qs('#learning-customize-bar').dataset.exit;

	}

	// L'enregistrement passe par la couche ajax du framework : un réglage refusé revient en
	// instructions et s'affiche comme partout ailleurs, et le rechargement est celui de la
	// ReloadAction.
	static save() {

		const bar = qs('#learning-customize-bar');

		sessionStorage.setItem(this.scrollKey, String(window.scrollY));

		new Ajax.Query(bar)
			.method('post')
			.url(bar.dataset.url)
			.body(this.getBody(JSON.parse(bar.dataset.settings)))
			.fetch();

	}

	static restore() {

		const bar = qs('#learning-customize-bar');

		if(bar === null) {
			this.release();
			return;
		}

		if(sessionStorage.getItem(this.panelKey) === '1') {
			bar.classList.add('customize-bar-folded');
		}

		Customize.restore(bar);

		this.measure(bar);

		const scroll = sessionStorage.getItem(this.scrollKey);

		if(scroll !== null) {
			window.scrollTo(0, parseInt(scroll));
			sessionStorage.removeItem(this.scrollKey);
		}

	}

	// La page réserve sous son contenu la hauteur de la barre, pour que rien ne reste caché dessous.
	static measure(bar) {

		const apply = () => document.body.style.setProperty('--customize-height', bar.offsetHeight + 'px');

		apply();

		if(this.observer !== undefined) {
			this.observer.disconnect();
		}

		this.observer = new ResizeObserver(apply);
		this.observer.observe(bar);

	}

	// La navigation ajax garde `body` et son style en ligne : une page sans barre rend la hauteur
	// réservée, sans quoi le menu de la formation suivante finirait au-dessus d'une barre absente.
	static release() {

		document.body.style.removeProperty('--customize-height');

		if(this.observer !== undefined) {
			this.observer.disconnect();
			this.observer = undefined;
		}

	}

}

document.addEventListener('navigation.wakeup', () => LearningCustomize.restore());
