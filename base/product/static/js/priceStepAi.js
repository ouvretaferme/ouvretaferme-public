class ProductPriceStepAi {

	static container(node) {
		return node.firstParent('.product-price-steps');
	}

	static switch(reference) {

		const link = qs('[data-price-steps="link"][data-price-steps-reference="'+ reference +'"]');

		(link.dataset.visible === '1') ? this.hide(reference) : this.show(reference);

	}

	static show(reference) {

		qsa('[data-price-steps="field"][data-price-steps-reference="'+ reference +'"]', node => node.removeHide());

		this.switchLink(reference);

	}

	/**
	 * Replier vide la table : un bloc caché dont les champs partiraient quand même appliquerait un
	 * barème que le producteur croit avoir retiré.
	 */
	static hide(reference) {

		qsa('[data-price-steps="field"][data-price-steps-reference="'+ reference +'"]', (node) => {

			node.hide();
			node.qsa('[data-ref="rows"] tr', row => row.remove());

			const container = node.qs('.product-price-steps');

			if(container) {
				this.refreshContainer(container);
			}

		});

		this.switchLink(reference);

	}

	static switchLink(reference) {

		const link = qs('[data-price-steps="link"][data-price-steps-reference="'+ reference +'"]');

		link.dataset.visible = (link.dataset.visible === '1') ? '0' : '1';

	}

	static add(button) {

		const container = this.container(button);
		const rows = container.qs('[data-ref="rows"]');

		if(rows.children.length >= parseInt(container.dataset.max)) {
			return;
		}

		// L'index sert à nommer les champs postés, pas à ordonner : `ProductLib::checkPriceSteps()`
		// retrie la table et resserre les trous. Un compteur qui ne redescend jamais suffit donc, et
		// évite de renommer les lignes qui restent quand on en supprime une du milieu.
		const index = parseInt(container.dataset.next ?? rows.children.length) + 1;
		container.dataset.next = index;

		rows.insertAdjacentHTML(
			'beforeend',
			container.qs('[data-ref="template"]').innerHTML.replaceAll('{index}', index)
		);

		this.refreshContainer(container);

	}

	static remove(link) {

		const container = this.container(link);

		link.firstParent('tr').remove();

		this.refreshContainer(container);

	}

	static refresh(input) {
		this.refreshContainer(this.container(input));
	}

	/**
	 * Le prix sur lequel se mesure l'écart : le prix remisé quand il est renseigné, le prix de base
	 * sinon.
	 *
	 * C'est la règle du serveur : `priceDiscount.setValue` déplace le prix de base dans
	 * `priceInitial` et met le prix remisé dans `price`, et `checkPriceStepsDecrease()` compare les
	 * paliers à `price`. Un écart mesuré sur le prix barré annoncerait une remise que le producteur
	 * n'obtiendrait pas à l'enregistrement.
	 */
	static base(container) {

		const form = container.firstParent('form');

		if(form === null) {
			return NaN;
		}

		this.watch(container, form);

		const discount = form.qs('[name="'+ container.dataset.priceFieldDiscount +'"]');
		const remise = discount ? parseFloat(discount.value) : NaN;

		if(isNaN(remise) === false) {
			return remise;
		}

		const price = form.qs('[name="'+ container.dataset.priceField +'"]');

		return price ? parseFloat(price.value) : NaN;

	}

	/** Les deux champs de prix redessinent les écarts quand ils changent, une seule fois posé. */
	static watch(container, form) {

		if(container.dataset.watched === '1') {
			return;
		}

		container.dataset.watched = '1';

		[container.dataset.priceField, container.dataset.priceFieldDiscount].forEach((name) => {
			form.qs('[name="'+ name +'"]', node => node.addEventListener('input', () => this.refreshContainer(container)));
		});

	}

	/**
	 * Recalcule l'écart de chaque palier et masque le bouton d'ajout au plafond.
	 *
	 * L'écart se mesure sur le prix de base en cours de saisie : rendu au serveur, il ne serait juste
	 * qu'après enregistrement, et c'est lui qui répond à « est-ce que je brade » pendant qu'on écrit.
	 */
	static refreshContainer(container) {

		const base = this.base(container);
		const rows = container.qs('[data-ref="rows"]');

		rows.qsa('tr', (row) => {

			const price = parseFloat(row.qs('[name$="[price]"]').value);
			const gap = row.qs('[data-ref="gap"]');

			// Un tiret cadratin plutôt que rien : une cellule vide se lit comme un écart nul, alors
			// qu'il n'y a simplement pas encore de quoi le calculer.
			if(isNaN(base) || isNaN(price) || base <= 0) {
				gap.innerHTML = '\u2014';
				gap.classList.remove('product-price-steps-gap-up');
				return;
			}

			const percent = Math.round((price - base) / base * 100);

			gap.innerHTML = (percent < 0 ? '\u2212' : '+') + Math.abs(percent) + ' %';
			gap.classList.toggle('product-price-steps-gap-up', percent >= 0);

		});

		// La table et l'état vide sont tous deux dans le DOM : c'est ici qu'on choisit lequel se
		// montre, plutôt que d'ouvrir un `<thead>` au-dessus de zéro ligne.
		const empty = rows.children.length === 0;

		container.qs('[data-ref="table"]').classList.toggle('hide', empty);
		container.qs('[data-ref="empty"]').classList.toggle('hide', empty === false);

		container.qs('[data-ref="add"]').classList.toggle('hide', rows.children.length >= parseInt(container.dataset.max));

	}

}
