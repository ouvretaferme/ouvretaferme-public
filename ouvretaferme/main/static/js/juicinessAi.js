/*
 * Le retour sur geste : ce que l'application fait des signaux que le cadriciel émet autour d'une requête.
 *
 * Le cadriciel signale et ne connait aucune de ces classes (avenant à la charte juiciness, point 4) :
 * - ajax.js émet `ajaxBeforeFetch` et `ajaxAfterFetch` sur l'élément cliqué, ou sur le document quand la
 *   requête n'en a pas ou que la réponse l'a remplacé, avec l'élément dans `detail.trigger` ;
 * - le PHP nomme le nœud qui s'éclaire (`outerHtml(…, highlight: TRUE)`, ou `highlight:` d'une
 *   ReloadAction ou d'une BackAction), et ajax.js émet `ajaxHighlight` : sur le nœud remplacé, ou sur le
 *   document avec la liste de sélecteurs ;
 * - lime.js émet `panelAfterClose` au retrait d'un panneau ;
 * - le PHP demande qu'un nœud retiré laisse voir son départ (`remove(vanish: TRUE)`), et ajax.js émet
 *   `ajaxVanish` sur chacun de ces nœuds avant de rendre la première instruction de la réponse.
 *
 * Cinq retours en découlent :
 * - `util-pending` sur le bouton d'envoi d'une écriture, le temps de la requête. Le sablier ne remplace
 *   le libellé qu'au-delà de --duration-sm, par un animation-delay de juicinessAi.css : une réponse plus
 *   rapide ne fait rien clignoter, et aucune minuterie n'est à annuler. Le libellé reste dans le bouton,
 *   caché, ce qui garde sa largeur sans la mesurer ;
 * - `util-waiting` sur le bloc qu'une requête recalcule, le temps de la requête. Le bloc porte
 *   `data-waiting-block` dans son markup, et le script qui le recalcule le nomme par `.trigger(bloc)`
 *   sur son Ajax.Query. La classe se retire à l'image qui suit ajaxAfterFetch, que la réponse réussisse
 *   ou échoue, et seulement si plus aucune requête n'est en vol sur ce bloc : celle qui prend le relais
 *   d'une autre sur le même bloc la garde, et le pulse continue au lieu de repartir de zéro ;
 * - `util-flash` sur le nœud nommé, une fois la page au repos : plus aucune requête en vol (le GET d'un
 *   rechargement en est une) et plus aucun panneau en train de se fermer. Une BackAction rend son
 *   instruction pendant que le panneau qu'elle ferme recharge la page dessous : le voile attend que la
 *   carte neuve soit posée et que le panneau soit parti. Une ReloadAction retire ses panneaux d'un coup,
 *   sans fermeture : l'état se regarde donc dès l'arrivée du signal, et pas seulement au signal suivant.
 *   Un script de package qui change un nœud sans requête pose le même voile par `MainJuiciness.flash(node)` ;
 * - l'état final tout de suite pour un contrôle `data-optimistic` (un interrupteur), qui revient à son état
 *   d'avant si la réponse échoue. Le message d'échec passe par la chaine existante (Lime.Alert) ;
 * - `util-vanish` sur une copie du nœud retiré, posée à sa place dans son parent mais hors du flux, qui
 *   s'efface pendant que les voisins remontent dessous. Le nœud, lui, est retiré dans l'instruction même :
 *   l'instruction suivante ne trouve que l'écran sans lui. La copie perd ses id, name, for et data-ajax*,
 *   mais garde ses classes et ses autres data-* : un sélecteur par classe ou par data-* la compte pendant
 *   0,15 s, sauf à écarter `[inert]`. Parce qu'elle reste entre ses voisins, le :nth-child de ceux qui la
 *   suivent ne bascule qu'à la fin de l'effacement.
 *
 * Les écouteurs sont posés sur le document une fois pour toutes : ils valent pour tout élément présent ou à
 * venir, après une navigation ajax comme après une réponse ciblée.
 */
class MainJuiciness {

	static inFlight = 0;

	// Nœuds et sélecteurs nommés par le PHP, en attente que la page soit au repos.
	static waiting = [];

	// L'interrupteur tel qu'il était avant le clic, rendu si la réponse échoue.
	static previous = new WeakMap();

	// Copies mesurées à ajaxVanish, posées toutes ensemble au prochain rendu.
	static vanishing = [];

	// Requêtes en vol par bloc en attente, deux requêtes sur le même bloc pouvant se croiser. Un bloc à zéro
	// garde sa classe jusqu'à l'image suivante (stopWaiting()).
	static waitingBlocks = new WeakMap();

	static beforeFetch(detail) {

		this.inFlight++;

		const trigger = detail.trigger;

		if(trigger instanceof Element === false) {
			return;
		}

		if(trigger.matches('[data-waiting-block]')) {

			this.wait(trigger);

		} else if(trigger.matches('.field-switch[data-optimistic]')) {

			this.previous.set(trigger, trigger.cloneNode(true));
			trigger.toggleSwitch();

		} else if(
			detail.method === 'POST' &&
			trigger.matches('[type="submit"], .btn[data-ajax]') &&
			trigger.matches('[data-waiter], [data-optimistic], .field-switch') === false
		) {
			this.pending(trigger);
		}

	}

	static afterFetch(detail) {

		this.inFlight--;

		const trigger = detail.trigger;

		if(trigger instanceof Element) {

			if(this.previous.has(trigger)) {

				// Une réponse réussie redit l'état elle-même : seul l'échec rend l'état d'avant.
				if(detail.failed && trigger.isConnected) {
					trigger.replaceWith(this.previous.get(trigger));
				}

				this.previous.delete(trigger);

			}

			if(trigger.classList.contains('util-pending')) {
				this.release(trigger);
			}

			if(this.waitingBlocks.has(trigger)) {
				this.stopWaiting(trigger);
			}

		}

		this.flush();

	}

	static pending(button) {

		const label = document.createElement('span');
		label.className = 'util-pending-label';
		label.append(...button.childNodes);

		button.append(label);
		button.insertAdjacentHTML('beforeend', '<span class="util-pending-icon"><span class="util-animate-turn">'+ Lime.Asset.icon('hourglass-top') +'</span></span>');
		button.classList.add('util-pending');

	}

	static release(button) {

		button.qs(':scope > .util-pending-icon')?.remove();

		const label = button.qs(':scope > .util-pending-label');

		if(label !== null) {
			label.replaceWith(...label.childNodes);
		}

		button.classList.remove('util-pending');

	}

	static wait(block) {

		this.waitingBlocks.set(block, (this.waitingBlocks.get(block) ?? 0) + 1);
		block.classList.add('util-waiting');

	}

	static stopWaiting(block) {

		const count = this.waitingBlocks.get(block) - 1;
		this.waitingBlocks.set(block, count);

		if(count > 0) {
			return;
		}

		// Une requête qui en relaie une autre sur ce bloc part depuis le .then() de la première, après
		// ajaxAfterFetch : retirer la classe ici puis la reposer ferait repartir le pulse de zéro dès qu'un
		// recalcul de style passe entre les deux. Le retrait attend donc l'image suivante, où il devient
		// visible, et n'a lieu que si aucune requête n'a repris le bloc entre-temps. Un onglet caché ne peint
		// rien et n'avance requestAnimationFrame qu'à son retour : le retrait y est immédiat.
		if(document.hidden) {
			this.unwait(block);
			return;
		}

		requestAnimationFrame(() => {

			if(this.waitingBlocks.get(block) === 0) {
				this.unwait(block);
			}

		});

	}

	static unwait(block) {

		this.waitingBlocks.delete(block);
		block.classList.remove('util-waiting');

	}

	static highlight(target, detail) {

		if(detail?.selectors) {
			this.waiting.push(...detail.selectors);
		} else {
			this.waiting.push(target);
		}

		this.flush();

	}

	// `leaving` est le panneau qui émet panelAfterClose : il porte encore .closing, lime.js ne le retirant
	// qu'après l'événement.
	static flush(leaving = null) {

		// Un panneau de couche qu'une réponse ferme (`closePanel`) quitte les couches tout de suite, mais ne prend
		// .closing qu'au retour arrière qui suit (`Lime.History.popLayer()`) : il part déjà, et le voile attend sa
		// sortie comme celle d'un panneau .closing.
		if(
			this.waiting.length === 0 ||
			this.inFlight > 0 ||
			Array.from(document.querySelectorAll('.panel.closing')).some(panel => panel !== leaving) ||
			Array.from(document.querySelectorAll('.panel.open[data-context="layer"]')).some(panel => panel !== leaving && Lime.History.getElementsOfLayers().includes(panel) === false)
		) {
			return;
		}

		const waiting = this.waiting;
		this.waiting = [];

		// Le PHP nomme chaque endroit où le nœud peut se trouver, sans savoir d'où le geste est parti : une
		// intervention modifiée revient à son panneau de lecture, à la frise de la série, au planning, aux
		// analyses ou à la boite à outils. Un panneau ouvert couvre la page : seul ce qu'il porte se voit,
		// et la carte restée dessous ne s'éclaire pas. lime.js donne à chaque panneau qu'il montre un z-index
		// plus haut que le précédent, y compris à un panneau rouvert : le plus haut est celui du dessus.
		const panel = Array.from(document.querySelectorAll('.panel.open:not(.closing)'))
			.reduce((top, current) => (top === null || Number(current.style.zIndex) > Number(top.style.zIndex)) ? current : top, null);

		// Tous les nœuds d'abord, puis toutes les écritures : aucune lecture ne suit une classe posée.
		const nodes = waiting
			.flatMap(item => (typeof item === 'string') ?
				Array.from(document.querySelectorAll(item)) :
				(item.isConnected ? [item] : []))
			.filter(node => panel === null || panel.contains(node));

		nodes.forEach(node => this.flash(node));

	}

	/**
	 * Le voile sur un nœud, une fois. C'est aussi la porte des packages : un script qui a déplacé ou
	 * changé un nœud sans réponse serveur (la série qui change de rang dans le plan de culture, series.js)
	 * l'éclaire par `MainJuiciness.flash(node)`, avec la teinte et la durée du socle, sans écrire sa propre
	 * animation. L'appel n'attend pas que la page soit au repos : c'est le script qui sait quand le nœud est
	 * à sa place. Le voile occupe le ::after du nœud, qui doit donc être libre : un ::after déjà animé par le
	 * package l'emporterait, sa feuille arrivant après celle-ci. `tint` pose une variante du voile de
	 * juicinessAi.css, `success` pour le nœud qu'une écriture vient de régler.
	 */
	static flash(node, tint = null) {

		// Un voile en cours va à son terme : le reposer demanderait de forcer un recalcul pour le relancer.
		if(node.classList.contains('util-flash')) {
			return;
		}

		const classes = (tint === null) ? ['util-flash'] : ['util-flash', 'util-flash-'+ tint];

		node.classList.add(...classes);

		const end = e => {

			// Les animations des enfants remontent jusqu'ici : seule celle du voile, sur le nœud, le retire.
			if(e.target !== node || e.animationName !== 'util-flash') {
				return;
			}

			node.classList.remove(...classes);
			node.removeEventListener('animationend', end);

		};

		node.addEventListener('animationend', end);

	}

	// L'écouteur ne fait que lire : ajax.js annonce tous les nœuds d'une réponse avant d'en retirer un
	// seul, et toutes les écritures attendent le même requestAnimationFrame. Aucune mesure ne suit donc
	// une écriture, et aucune n'est prise sur une page qui a déjà reflué.
	static vanish(node) {

		// Dans un onglet caché, requestAnimationFrame attend le retour sur l'onglet : la copie y paraitrait d'un
		// coup, sur des mesures périmées. Le nœud part sec.
		if(document.hidden) {
			return;
		}

		// Une ligne ou une cellule hors de sa table perd sa mise en forme : elle part sans copie.
		if(node.matches('tr, td, th, thead, tbody, tfoot')) {
			console.log('util-vanish : un nœud de tableau n\'a pas de copie hors de sa table, il est retiré sans effet.', node);
			return;
		}

		const rect = node.getBoundingClientRect();
		const container = this.getContainingBlock(node);

		// Une rangée en sous-grille (util-table-row) tient ses colonnes de la grille qui la porte : hors du
		// flux, la copie n'en a plus, et ses cellules s'empileraient. Elle reçoit les pistes que cette grille
		// avait résolues, lues ici avant tout retrait. Un ancêtre en `display: contents` ne génère aucune
		// boite : la grille est le premier ancêtre qui en génère une.
		let columns = null;

		if(getComputedStyle(node).gridTemplateColumns.startsWith('subgrid')) {

			let grid = node.parentElement;

			while(getComputedStyle(grid).display === 'contents') {
				grid = grid.parentElement;
			}

			columns = getComputedStyle(grid).gridTemplateColumns;

		}

		const copy = node.cloneNode(true);

		[copy, ...copy.querySelectorAll('*')].forEach(element => {
			Array.from(element.attributes)
				.filter(attribute => ['id', 'name', 'for'].includes(attribute.name) || attribute.name.startsWith('data-ajax'))
				.forEach(attribute => element.removeAttribute(attribute.name));
		});

		copy.setAttribute('inert', '');

		this.vanishing.push({node, parent: node.parentNode, next: node.nextSibling, container, copy, rect, columns});

		if(this.vanishing.length === 1) {
			requestAnimationFrame(() => this.appear());
		}

	}

	// Le bloc conteneur d'un élément en position absolue : l'ancêtre le plus proche qui est positionné, ou
	// qui porte une transformation, un filtre ou un confinement de mise en page.
	static getContainingBlock(node) {

		for(let ancestor = node.parentElement; ancestor !== null && ancestor !== document.body; ancestor = ancestor.parentElement) {

			const style = getComputedStyle(ancestor);

			if(
				style.position !== 'static' ||
				style.transform !== 'none' ||
				style.filter !== 'none' ||
				['layout', 'paint', 'strict', 'content'].some(value => style.contain.includes(value))
			) {
				return ancestor;
			}

		}

		return null;

	}

	static appear() {

		const vanishing = this.vanishing;
		this.vanishing = [];

		// Le conteneur se mesure ici et non à l'annonce : le retrait d'un voisin a pu le déplacer, lui aussi
		// (une frise dont chaque ligne vit dans sa propre bande positionnée). La copie reprend la place qu'avait
		// le nœud dans la fenêtre, quel que soit le chemin pris depuis par son conteneur. Toutes les mesures
		// avant la première écriture.
		vanishing.forEach(item => {

			const {container, rect} = item;

			// Sans ancêtre qui le contienne, un élément en position absolue se place sur le document.
			if(container === null) {
				item.top = rect.top + window.scrollY;
				item.left = rect.left + window.scrollX;
			} else {
				const box = container.getBoundingClientRect();
				item.top = rect.top - box.top - container.clientTop + container.scrollTop;
				item.left = rect.left - box.left - container.clientLeft + container.scrollLeft;
			}

		});

		vanishing.forEach(({copy, rect, top, left, columns}) => {

			// En ligne, parce que les règles des classes reprises du nœud peuvent peser plus que util-vanish.
			if(columns !== null) {
				copy.style.gridTemplateColumns = columns;
			}

			copy.style.position = 'absolute';
			copy.style.boxSizing = 'border-box';
			copy.style.margin = '0';
			copy.style.top = top +'px';
			copy.style.left = left +'px';
			copy.style.width = rect.width +'px';
			copy.style.height = rect.height +'px';
			copy.classList.add('util-vanish');

			copy.addEventListener('animationend', e => {
				if(e.target === copy && e.animationName === 'util-vanish') {
					copy.remove();
				}
			});

		});

		const items = new Map(vanishing.map(item => [item.node, item]));
		const placed = new Set();

		vanishing.forEach(item => this.place(item, items, placed));

		// Sans juicinessAi.css, aucune animation ne tourne et animationend ne viendrait jamais.
		vanishing
			.filter(({copy}) => getComputedStyle(copy).animationName === 'none')
			.forEach(({copy}) => copy.remove());

	}

	// La copie reprend la place du nœud entre ses voisins. Un voisin suivant parti dans la même réponse est posé
	// d'abord, et la copie se range devant la sienne : l'ordre des instructions ne dit rien de l'ordre de la page
	// (doSkip retire le tiroir avant sa ligne). Les coordonnées restent celles du bloc conteneur, où un élément
	// en position absolue se place quel que soit le parent statique qui le porte.
	static place(item, items, placed) {

		if(placed.has(item)) {
			return;
		}

		placed.add(item);

		const {parent, next, container, copy} = item;

		if(parent.isConnected === false) {
			(container ?? document.body).append(copy);
			return;
		}

		if(items.has(next)) {
			this.place(items.get(next), items, placed);
		}

		const anchor = items.has(next) ? items.get(next).copy : next;

		if(anchor !== null && anchor.parentNode === parent) {
			parent.insertBefore(copy, anchor);
		} else {
			parent.append(copy);
		}

	}

}

document.addEventListener('ajaxBeforeFetch', e => MainJuiciness.beforeFetch(e.detail));
document.addEventListener('ajaxAfterFetch', e => MainJuiciness.afterFetch(e.detail));
document.addEventListener('ajaxHighlight', e => MainJuiciness.highlight(e.target, e.detail));
document.addEventListener('ajaxVanish', e => MainJuiciness.vanish(e.target));

// panelAfterClose ne remonte pas, comme les autres événements de panneau : il s'écoute en capture.
document.addEventListener('panelAfterClose', e => MainJuiciness.flush(e.target), true);
