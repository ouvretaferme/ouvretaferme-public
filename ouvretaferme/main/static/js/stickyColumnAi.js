/*
 * L'ombre au bord de la première colonne d'une `util-table-sticky-column`, ou de son pendant posé sur
 * la balise, `table.td-sticky-first`, dit que des colonnes passent dessous. Quand l'enveloppe n'a rien
 * à faire défiler, rien ne passe, et le filet se lit alors comme un filet vertical de tableau, que
 * l'interface n'emploie nulle part ailleurs.
 *
 * Le CSS ne peut pas poser la question, aucun sélecteur ne disant si un conteneur déborde, et l'appelant
 * ne peut pas y répondre à sa place, le débord dépendant de la donnée autant que de la largeur : un
 * contrat de 47 distributions défile, un contrat de 3 ne défile pas, même balisage. D'où ce script, écrit
 * dans le sens qui protège : la feuille pose l'ombre, il ne fait que l'éteindre en posant `is-fitted`. Un
 * écran qui ne charge pas ce fichier garde exactement le rendu d'avant, jamais une colonne sans repère.
 *
 * Trois choses font bouger la réponse, et chacune a son accroche. La largeur de l'enveloppe et celle de
 * la grille : un `ResizeObserver` sur les deux. L'arrivée d'un écran : `navigation.wakeup`, qu'`ajax.js`
 * déclenche sur l'événement de chargement du navigateur autant qu'en navigation ajax, donc une seule
 * accroche couvre les deux.
 * Le remplacement d'une grille par une réponse ajax ciblée, que rien n'annonce : un `MutationObserver`
 * sur le corps, qui ne réveille la mesure que pour une grille réellement neuve. Les scripts étant chargés
 * dans le `<head>`, aucune accroche ne peut se poser avant `navigation.wakeup`, où `document.body` existe.
 */
class MainStickyColumn {

	static SELECTOR = '.util-table-sticky-column, table.td-sticky-first';

	static resizeObserver = null;
	static observed = new WeakSet();
	static frame = null;

	/*
	 * L'enveloppe qui défile n'est pas toujours le parent direct ni toujours une `util-overflow` : on
	 * remonte jusqu'au premier ancêtre que le navigateur dit défilant. Le style calculé répond pour la
	 * largeur courante, ce qui règle `util-overflow-md`, défilante d'un seul côté de sa bascule.
	 *
	 * La recherche part de la table elle-même, qui peut être son propre cadre (`overflow-x: auto` posé
	 * sur la grille). L'observateur ne suit alors qu'une boîte, dont la largeur ne bouge pas quand le
	 * contenu s'élargit : seule une table dont les colonnes ont des minimums fixes peut défiler seule.
	 */
	static getWrapper(table) {

		for(let node = table; node !== null; node = node.parentElement) {

			const overflowX = getComputedStyle(node).overflowX;

			if(overflowX === 'auto' || overflowX === 'scroll') {
				return node;
			}

		}

		return null;

	}

	static watch(node) {

		if(this.observed.has(node) === true) {
			return;
		}

		this.observed.add(node);
		this.resizeObserver.observe(node);

	}

	/*
	 * Toutes les mesures, puis toutes les écritures : une lecture de `scrollWidth` entre deux poses de
	 * classe forcerait un recalcul de mise en page par tableau.
	 *
	 * Le pixel de tolérance n'est pas une marge de confort : `scrollWidth` et `clientWidth` sont arrondis
	 * à l'entier, donc une grille de largeur fractionnaire rend un écart de 1 sans que rien ne défile.
	 */
	static apply() {

		const tables = Array.from(document.querySelectorAll(this.SELECTOR));

		const measures = tables.map(table => {

			const wrapper = this.getWrapper(table);

			return {
				wrapper: wrapper,
				fitted: wrapper === null || wrapper.scrollWidth <= wrapper.clientWidth + 1,
			};

		});

		tables.forEach((table, index) => table.classList.toggle('is-fitted', measures[index].fitted));

		tables.forEach((table, index) => {

			this.watch(table);

			if(measures[index].wrapper !== null) {
				this.watch(measures[index].wrapper);
			}

		});

	}

	/*
	 * Le report est un `setTimeout` et non un `requestAnimationFrame`, parce qu'une image n'est jamais
	 * peinte dans un onglet caché : mesuré sur la page design system chargée en arrière-plan,
	 * `visibilityState` à `hidden`, le rappel ne partait pas, le jeton restait posé, et toutes les passes
	 * suivantes sortaient dessus, donc l'onglet restait sans mesure même redevenu visible. Le délai nul
	 * regroupe tout aussi bien une rafale de mutations, la tâche courante se terminant d'abord.
	 */
	static refresh() {

		if(this.frame !== null) {
			return;
		}

		this.frame = setTimeout(() => {
			this.frame = null;
			this.apply();
		}, 0);

	}

	/*
	 * Ce qui justifie la mesure est une grille que le `ResizeObserver` ne suit pas encore, donc une grille
	 * neuve. Le test marche sur le seul sous-arbre inséré et ne lit aucune géométrie : une insertion sans
	 * grille collante ne coûte rien, là où rappeler `apply()` à chaque mutation lirait `scrollWidth` à
	 * chaque image, sur des écrans qui écrivent beaucoup dans le DOM.
	 */
	static hasTable(records) {

		for(const record of records) {

			for(const node of record.addedNodes) {

				if(node.nodeType !== Node.ELEMENT_NODE) {
					continue;
				}

				if(
					node.matches(this.SELECTOR) === true ||
					node.querySelector(this.SELECTOR) !== null
				) {
					return true;
				}

			}

		}

		return false;

	}

	static start() {

		if(this.resizeObserver !== null) {
			this.refresh();
			return;
		}

		this.resizeObserver = new ResizeObserver(() => this.refresh());

		/* Le corps n'est pas observé en `attributes` : `is-fitted` est lui-même un changement d'attribut,
		   et l'observateur reviendrait sans fin sur sa propre écriture. */
		new MutationObserver(records => {

			if(this.hasTable(records) === true) {
				this.refresh();
			}

		}).observe(document.body, {childList: true, subtree: true});

		this.refresh();

	}

}

document.addEventListener('navigation.wakeup', () => MainStickyColumn.start());
