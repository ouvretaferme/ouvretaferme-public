/*
 * Le geste de `util-bellows` : un clic sur un pli fermé l'ouvre et referme celui qui l'était.
 *
 * Posé seul (`.util-bellows`), la feuille fait tout le mouvement par `flex-grow` dès que la classe change.
 * Dans une `util-table` (`.util-bellows-columns`), le pli est une colonne : la classe se pose sur chaque
 * cellule de la colonne, et la variable `--util-bellows-<n>` du pli ouvert passe à 1 sur la grille, les autres
 * étant retirées ; les pistes des plis, écrites par la feuille de l'écran, la lisent. Aucune largeur ici :
 * elles suivent les bascules de `component.css`, donc un redimensionnement n'a rien à refaire.
 *
 * Le délégué est posé sur `document` : il vaut pour tout pli présent ou à venir, y compris après une
 * navigation ajax ou une ligne remplacée par une réponse ciblée, et il n'y a rien à initialiser au
 * réveil. L'état d'arrivée est celui que le serveur rend (`util-bellows-open`, `util-bellows-titled`,
 * variable posée sur la grille).
 */
class MainBellows {

	static DURATION = 400;

	static open(item) {

		const line = item.parentNode;

		if(line.classList.contains('util-bellows')) {

			const left = line.qs(':scope > .util-bellows-open');

			left?.classList.remove('util-bellows-open');
			item.classList.add('util-bellows-open');

			this.title([left, item]);

			return;

		}

		const grid = line.parentNode;

		if(grid.classList.contains('util-bellows-columns') === false) {
			return;
		}

		const items = Array.from(line.children).filter(child => child.classList.contains('util-bellows-item'));
		const column = Array.from(line.children).indexOf(item);
		const position = items.indexOf(item) + 1;

		grid.qsa(':scope > * > .util-bellows-open', node => node.classList.remove('util-bellows-open'));

		grid.qsa(':scope > .util-table-head, :scope > .util-table-row', row => {
			row.children[column]?.classList.add('util-bellows-open');
		});

		for(let index = 1; index <= items.length; index++) {
			grid.style.removeProperty('--util-bellows-'+ index);
		}

		grid.style.setProperty('--util-bellows-'+ position, '1');

		const head = grid.qs(':scope > .util-table-head');

		if(head !== null) {
			this.title(Array.from(head.children).filter(child => child.matches('.util-bellows-item:not(.util-bellows-inactive)')));
		}

		// L'écran qui porte la grille peut avoir à suivre le pli ouvert (un lien, un champ qui le réémet) :
		// l'événement remonte depuis la cellule d'en-tête, qui porte ce que l'écran y a posé.
		head?.children[column]?.dispatchEvent(new CustomEvent('bellowsOpen', {bubbles: true}));

	}

	/*
	 * Chaque pli montre son nom vertical tant que sa bande le contient, son titre sinon. La course de la bande
	 * dépend de la largeur de l'écran : aucun délai CSS ne tombe juste, on mesure donc à chaque image du
	 * glissement. Toutes les mesures d'abord, toutes les écritures ensuite : une lecture après une écriture
	 * forcerait un recalcul complet de la mise en page à chaque pli. En mouvement réduit, la première image
	 * mesure déjà l'état final.
	 *
	 * La mesure court au moins `DURATION`, puis tant qu'une transition des bandes ou de la grille tourne : sur
	 * un écran lourd, le glissement démarre avec du retard et finirait après la dernière image, qui laisserait
	 * au pli ouvert l'état mesuré en route, bande repliée sans son titre (retour 247). Seules les transitions
	 * comptent : elles ont toujours une fin, ce qu'une animation n'a pas forcément.
	 */
	static title(items) {

		items = items.filter(item => item !== null && item !== undefined);

		if(items.length === 0) {
			return;
		}

		const end = performance.now() + this.DURATION;
		const animated = [items[0].closest('.util-bellows-columns'), ...items, ...items.map(item => item.qs('.util-bellows-band'))].filter(node => node !== null);

		const frame = () => {

			const titled = items.map(item => {

				const name = item.qs('.util-bellows-name');

				if(name === null) {
					return true;
				}

				return item.qs('.util-bellows-band').clientWidth + 0.5 < name.getBoundingClientRect().width;

			});

			const again = (performance.now() < end || animated.some(node => node.getAnimations().some(animation => animation instanceof CSSTransition && animation.playState === 'running')));

			items.forEach((item, index) => item.classList.toggle('util-bellows-titled', titled[index]));

			if(again) {
				requestAnimationFrame(frame);
			}

		};

		frame();

	}

}

document.delegateEventListener('click', '.util-bellows-item:not(.util-bellows-open, .util-bellows-inactive)', function() {
	MainBellows.open(this);
});

// Au clavier, la bande qui porte le nom du pli (tabindex posé par l'écran) s'ouvre par Entrée ou Espace.
// La bande ouverte se replie à zéro puis se masque : le focus passe au panneau qui la remplace, sinon
// il resterait sur un point invisible avant de retomber sur le document.
document.delegateEventListener('keydown', '.util-bellows-item:not(.util-bellows-open, .util-bellows-inactive) > .util-bellows-band[tabindex]', function(e) {

	if(e.key !== 'Enter' && e.key !== ' ') {
		return;
	}

	e.preventDefault();

	const item = this.parentNode;
	const panel = item.qs(':scope > .util-bellows-panel');

	MainBellows.open(item);

	if(panel !== null) {
		panel.setAttribute('tabindex', '-1');
		panel.focus({preventScroll: true});
	}

});
