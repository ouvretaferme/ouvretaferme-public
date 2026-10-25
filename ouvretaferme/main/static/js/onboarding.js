class MainOnboarding {

	static current = null;
	static root = null;
	static index = 0;

	static start(root) {

		MainOnboarding.current = this;

		this.root = root;
		this.index = 0;

		this.init();

		// ⚠️ L'étape d'ouverture est celle que le serveur a écrite, pas 1. Un parcours qui
		// enregistre une étape redirige vers la suivante et la déclare en `data-step` ; forcer 1
		// ici ramenait sur celle qu'on venait de finir, et « Continuer » paraissait sans effet.
		// `parseInt` d'un attribut absent rend NaN, donc un appelant qui ne déclare rien ouvre
		// toujours sur 1.
		this.goTo(this.step || 1);

		this.watchErrors();

	}

	static init() {
	}

	// Les délégués écoutent sur le document, et `current` survit à la navigation ajax : sans ce
	// contrôle, un parcours `.onboarding` ouvert après un autre écran hérite de son `current`,
	// dont le nœud n'est plus dans la page, et Entrée y avale la soumission du formulaire.
	static of(node) {

		const current = MainOnboarding.current;

		if(current === null || current.root === null || current.root.isConnected === false) {
			MainOnboarding.current = null;
			return null;
		}

		return current.root.contains(node) ? current : null;

	}

	static onStep(step) {
	}

	static renderPreview() {
	}

	static get form() {
		return this.root.qs('form');
	}

	static get texts() {
		const node = this.root.qs('[data-texts]');
		return node ? JSON.parse(node.dataset.texts) : {};
	}

	static get step() {
		return parseInt(this.root.dataset.step);
	}

	static get lastStep() {
		return this.root.querySelectorAll('.onboarding-step').length;
	}

	static goTo(step) {

		const root = this.root;

		step = Math.max(1, Math.min(step, this.lastStep));

		root.dataset.step = step;

		root.qsa('.onboarding-step', node => node.classList.toggle('is-current', parseInt(node.dataset.step) === step));
		// Deux barres possibles, et c'est le balisage qui choisit : un segment qui porte data-step se
		// remplit par position, un segment qui porte data-onboarding-name ne reçoit que is-current et
		// garde le is-done que le serveur ou l'appelant a posé, une étape passée sans être enregistrée
		// restant éteinte. Un segment sans attribut n'est pas touché : parseInt(undefined) rendrait
		// NaN et effacerait la barre au premier affichage.
		root.qsa('.onboarding-stepper span[data-step]', node => {
			const position = parseInt(node.dataset.step);
			node.classList.toggle('is-done', position < step);
			node.classList.toggle('is-current', position === step);
		});
		root.qsa('.onboarding-stepper span[data-onboarding-name]', node => node.classList.toggle('is-current', parseInt(node.dataset.onboardingName) === step));
		// Un nom suit son segment : fait quand le segment l'est, et is-skipped, gris mais par lequel on
		// recule encore, quand l'étape a été dépassée sans être enregistrée (décidé le
		// 2026-10-01). Sous une barre remplie par position, toute étape dépassée est faite.
		root.qsa('.onboarding-progress-names [data-onboarding-name]', node => {
			const position = parseInt(node.dataset.onboardingName);
			const segment = root.qs('.onboarding-stepper span[data-onboarding-name="'+ position +'"]');
			const isDone = segment ? segment.classList.contains('is-done') : position < step;
			node.classList.toggle('is-done', isDone && position !== step);
			node.classList.toggle('is-skipped', isDone === false && position < step);
			node.classList.toggle('is-current', position === step);
		});
		root.qsa('[data-onboarding-tip]', node => node.classList.toggle('is-current', parseInt(node.dataset.onboardingTip) === step));

		root.qs('.onboarding-progress-label', label => {
			if(label.dataset.format !== undefined) {
				label.innerHTML = label.dataset.format
					.replace('__step__', step)
					.replace('__total__', this.lastStep);
			}
		});

		this.onStep(step);
		this.renderPreview();

		const focus = root.qs('.onboarding-step.is-current input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"])');

		// Le focus d'un autocomplete efface la valeur déjà choisie (AutocompleteField.query()).
		if(focus && (focus.dataset.autocompleteField === undefined || focus.value === '')) {
			focus.focus();
		}

	}

	static next() {
		this.goTo(this.step + 1);
	}

	static back() {
		this.goTo(this.step - 1);
	}

	static addRow(template, container) {

		const index = ++this.index;

		container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__i__', index));

		return container.qs('[data-index="'+ index +'"]');

	}

	static removeRow(row) {
		row.remove();
	}

	static watchErrors() {

		new MutationObserver(mutations => {

			for(const mutation of mutations) {

				if(mutation.target.classList.contains('form-error-wrapper')) {

					const section = mutation.target.closest('.onboarding-step');

					if(section && parseInt(section.dataset.step) < this.step) {
						this.goTo(parseInt(section.dataset.step));
					}

				}

			}

		}).observe(this.form, {attributes: true, attributeFilter: ['class'], subtree: true});

	}

}

document.delegateEventListener('click', '.onboarding [data-onboarding-next]', function(e) {
	MainOnboarding.of(this)?.next();
});

document.delegateEventListener('click', '.onboarding [data-onboarding-back]', function(e) {
	MainOnboarding.of(this)?.back();
});

// On recule par les noms d'étapes là où ils existent, par un bouton là où il n'y en a pas, et
// jamais par les deux : une barre qui nomme trois étapes sans en laisser atteindre aucune
// ressemble à une navigation sans en être une.
//
// ⚠️ Borné à `.onboarding-progress-names` : les segments de la barre portent le même attribut
// pour se colorer, mais ils disent ce qui est fait et ne sont pas des destinations.
//
// Seules les étapes faites se rejoignent par leur nom (décidé le 2026-09-26, variante
// A de la planche des étapes) : en avant, c'est le pied de l'étape qui avance, et « Passer cette
// étape » le fait déjà sans rien enregistrer. Une étape passée se rejoint aussi, par son nom resté
// gris (décidé le 2026-10-01, « Gris mais cliquable »).
document.delegateEventListener('click', '.onboarding .onboarding-progress-names [data-onboarding-name]:is(.is-done, .is-skipped)', function(e) {
	MainOnboarding.of(this)?.goTo(parseInt(this.dataset.onboardingName));
});

document.delegateEventListener('click', '.onboarding [data-onboarding-remove-row]', function(e) {
	MainOnboarding.of(this)?.removeRow(this.closest('.onboarding-row'));
});

document.delegateEventListener('input', '.onboarding form input', function(e) {
	MainOnboarding.of(this)?.renderPreview();
});

// Entrée choisit une proposition dans un champ d'autocomplétion, et le délégué écoute en capture,
// donc avant le champ : il ne peut pas voir le preventDefault() et doit l'écarter par le sélecteur
document.delegateEventListener('keydown', '.onboarding form input:not([data-autocomplete-field])', function(e) {

	const onboarding = MainOnboarding.of(this);

	if(onboarding !== null && e.key === 'Enter' && onboarding.step < onboarding.lastStep) {
		e.preventDefault();
		onboarding.next();
	}

});
