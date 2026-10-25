/**
 * Gestion des remises de prix par article
 */
class PriceInitial {

	static switch(target, onHide = null) {

		const reference = target.dataset.priceDiscountReference;
		const container = this.getContainer(target, reference);

		if(target.dataset.visible === '1') {
			this.hide(reference, container);
			if(onHide) {
				onHide(target);
			}
		} else {
			this.show(reference, container);
		}

	}

	/**
	 * Le plus petit ancêtre du bouton qui porte aussi son champ.
	 *
	 * Deux blocs portent la même référence dans une même page dès que le panneau de modification
	 * d'un article s'ouvre au-dessus de la ligne de vente qui porte déjà la sienne. Une recherche
	 * depuis `document` prend alors le premier des deux, qui n'est pas celui qu'on a cliqué.
	 */
	static getContainer(target, reference) {

		let node = target.parentElement;

		while(node !== null) {

			if(node.qs('[data-price-discount="field"][data-price-discount-reference="'+ reference +'"]') !== null) {
				return node;
			}

			node = node.parentElement;

		}

		return document;

	}

	static show(reference, container = null) {

		container ??= document;
		container.qsa('[data-price-discount="field"][data-price-discount-reference="'+ reference +'"]', node => node.removeHide());

		const target = container.qs('[data-price-discount="link"][data-price-discount-reference="'+ reference +'"]');

		this.switchDiscount(target);
		this.markOrigin(target, false, container);

	}

	static hide(reference, container = null) {

		container ??= document;
		container.qsa('[data-price-discount="field"][data-price-discount-reference="'+ reference +'"]', node => node.hide());
		container.qs('[data-price-discount-reference="'+ reference +'"] input').value = '';

		const target = container.qs('[data-price-discount="link"][data-price-discount-reference="'+ reference +'"]');

		this.switchDiscount(target);
		this.markOrigin(target, false, container);

	}

	/**
	 * Le bouton nomme l'origine de la baisse : un palier de prix dégressif, ou une remise saisie.
	 *
	 * Les deux étiquettes de la paire sont posées ensemble. `switchDiscount()` les échange à chaque
	 * bascule : en écrire une seule empoisonnerait l'autre, et le bouton dirait « Remise sur volume »
	 * une fois replié.
	 */
	static markOrigin(link, fromSteps, container = null) {

		if(link === null) {
			return;
		}

		const shown = fromSteps ? link.dataset.labelSteps : link.dataset.labelDiscount;

		if(link.dataset.visible === '1') {
			link.innerHTML = shown;
			link.dataset.placeholder = link.dataset.labelFolded;
		} else {
			link.innerHTML = link.dataset.labelFolded;
			link.dataset.placeholder = shown;
		}

		const reference = link.dataset.priceDiscountReference;

		(container ?? document).qsa('[data-price-discount-label][data-price-discount-reference="'+ reference +'"]', node => {
			node.innerHTML = fromSteps ? node.dataset.labelSteps : node.dataset.labelDiscount;
		});

	}

	static switchDiscount(target) {

		const placeholder = target.dataset.placeholder;
		target.dataset.placeholder = target.innerHTML;
		target.dataset.visible = (target.dataset.visible === '1') ? '0' : '1';

		target.innerHTML = placeholder;

	}

}
