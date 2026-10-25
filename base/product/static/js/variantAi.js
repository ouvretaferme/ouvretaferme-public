class ProductVariant {

	/**
	 * Le glissement des variantes, motif repris de WebsiteManage.initMenuEditor()
	 * (ouvretaferme/website/static/js/manage.js). Ce qui diffère de lui : la liste est
	 * plate, donc ni group ni onMove ni parent posté ; le conteneur de la grille est une
	 * table dont chaque variante est un <tbody> ; et la même liste est rendue deux fois,
	 * en grille au-dessus de 767 px et en cartes en dessous, donc les deux conteneurs
	 * s'arment et le jumeau se remet dans l'ordre au dépôt.
	 */
	static initSort(node) {

		if(node.dataset.sortReady === '1') {
			return;
		}

		node.dataset.sortReady = '1';

		node.qsa('[data-variant-sort]', list => {

			new Sortable(list, {
				handle: '.util-drag-handle',
				draggable: '[data-variant-id]',
				ghostClass: 'util-drag-ghost',
				chosenClass: 'util-drag-chosen',
				forceFallback: true,
				fallbackOnBody: true,
				onChoose: e => ProductVariant.freezeColumns(e.item),
				onUnchoose: e => ProductVariant.freezeColumns(e.item, false),
				onEnd: e => {
					// e.to et non le premier conteneur venu : la grille et les cartes rendent
					// la même liste, et c'est celle où le doigt a glissé qui fait foi
					ProductVariant.saveSort(node, e.to);
				}
			});

		});

	}

	/**
	 * Le fantôme d'un <tbody>, réparé avant qu'il ne soit fabriqué.
	 *
	 * Sortable._appendGhost() clone l'élément saisi hors du tableau et lui impose sa boîte
	 * extérieure ; les cellules, elles, perdent l'algorithme de mise en page des tableaux,
	 * qui calcule leur largeur sur la colonne entière et non sur leur contenu. Mesuré le
	 * 2026-09-02 sur un glissement réel : huit colonnes sur neuf décalées, jusqu'à 181 px,
	 * CARACTÉRISTIQUES tombant à 5 px. Figer les largeurs en ligne avant le clone les lui
	 * transmet.
	 *
	 * ⚠️ Le crochet est onChoose et NON onStart : _dragStarted() (Sortable-1.11.0.js:1688)
	 * appelle _appendGhost() TROIS LIGNES AVANT de dispatcher « start ». onStart n'est pas
	 * avant le premier déplacement, il est dedans, après le clone. onChoose part au
	 * pointerdown (:1548), lui.
	 *
	 * Le dégel est sur onUnchoose, qui part à chaque relâchement (:2464, inconditionnel
	 * dans _onDrop) : onEnd ne partirait pas sur un clic de poignée sans glissement, et
	 * les largeurs resteraient figées.
	 *
	 * Sans effet sur une carte, qui n'a pas de cellules et dont le fantôme est déjà juste.
	 */
	static freezeColumns(item, freeze = true) {

		const cells = [];
		const widths = [];

		// Toutes les mesures, puis toutes les écritures : lire une géométrie et écrire un
		// style dans la même boucle force un recalcul complet à chaque tour
		item.qsa('td', cell => cells.push(cell));
		cells.forEach(cell => widths.push(freeze ? cell.getBoundingClientRect().width + 'px' : ''));
		cells.forEach((cell, index) => cell.style.width = widths[index]);

	}

	static saveSort(node, list) {

		const ids = [];

		list.qsa('[data-variant-id]', item => ids.push(parseInt(item.dataset.variantId)));

		node.qsa('[data-variant-sort]', twin => {

			// insertBefore et non appendChild : dans la grille, la ligne « Ajouter une
			// variante » ferme la table et doit le rester
			const end = twin.qs('[data-variant-add]');

			ids.forEach(id => {

				const item = twin.qs('[data-variant-id="'+ id +'"]');

				if(item !== null) {
					twin.insertBefore(item, end);
				}

			});

		});

		new Ajax.Query()
			.url('/product/product:doUpdatePositions')
			.body({
				id: node.dataset.product,
				positions: JSON.stringify(ids)
			})
			.fetch();

	}

	/**
	 * Replier une famille sur la liste des produits. Aucun aller-retour : les sous-lignes
	 * viennent d'être écrites par la réponse de dépliage, les retirer suffit et un nouveau
	 * dépliage les redemandera fraîches.
	 *
	 * Les deux chevrons sont rendus par le serveur, l'un dans l'autre état : c'est la classe
	 * du <tbody> qui décide lequel se voit, jamais du HTML fabriqué ici.
	 */
	static collapseFamily(node) {

		const family = node.firstParent('tbody');

		if(family === null) {
			return;
		}

		document.getElementById(family.id + '-variants').innerHTML = '';
		family.classList.remove('product-family-open');

	}


	/**
	 * Les deux onglets de la variante 2 d'une première variante : créer un produit ou en rattacher
	 * un. `tabs-exclusive` laisse Lime.Tab désactiver le fieldset de l'onglet écarté ; il ne reste
	 * ici que le libellé du bouton, propre à cet écran.
	 */
	static selectSource(target) {

		Lime.Tab.select(target);

		target.firstParent('form').qs('[type="submit"][data-label-'+ target.dataset.tab +']', button => {
			button.innerHTML = button.getAttribute('data-label-'+ target.dataset.tab);
		});

	}

}
