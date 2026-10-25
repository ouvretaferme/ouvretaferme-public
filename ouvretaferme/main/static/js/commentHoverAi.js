/*
 * util-comment-hover : dans une grille util-comment-editable, les gestes d'un message ne paraissent
 * qu'au survol de ce message, date et texte compris, et le message survolé ressort sur une bande de
 * fond.
 *
 * Les trois cellules d'un message ne forment pas un élément dans la grille, et une enveloppe en
 * display: contents casserait l'alignement à droite de la troisième : le survol se mémorise donc ici,
 * sur la cellule des gestes (is-hovered). Il ne se retire pas en quittant une cellule, sinon la
 * souris qui va du texte à la corbeille le perdrait dans l'écart de colonne : il passe au message
 * dans lequel elle entre, ou se retire quand elle quitte la grille.
 *
 * La bande se peint sous la grille (is-hovering et son ::before, component.css) et non sur les trois
 * cellules : alignées sur leur ligne de base, elles n'ont pas la même hauteur. Sa position se mesure
 * à chaque passage, un « Lire la suite » ou une réponse ajax pouvant avoir changé la hauteur du
 * message depuis le précédent.
 *
 * Un message sans geste (le lien « plus anciennes », une ligne en cours d'écriture) ne ressort pas :
 * la bande dit à quel message appartiennent les gestes qui paraissent.
 *
 * Au doigt, les gestes restent affichés et la bande n'est pas peinte (component.css).
 *
 * Délégation sur document : une réponse ajax remplace les grilles sans prévenir. mouseover et
 * mouseleave ne sont délégués qu'à leur cible exacte (lime.js, delegateEventListener).
 */
class MainCommentHover {

	static enter(target) {

		const grid = target.closest('.util-comment-hover');

		let cell = target;

		while(cell.parentElement !== grid) {
			cell = cell.parentElement;
		}

		const index = Array.from(grid.children).indexOf(cell);
		const first = index - (index % 3);
		const cells = [0, 1, 2].map(offset => grid.children[first + offset]).filter(child => child !== undefined);
		const actions = cells[2];

		if(actions === undefined || actions.childElementCount === 0) {
			this.leave(grid);
			return;
		}

		// Toutes les mesures, puis toutes les écritures : une écriture entre deux lectures forcerait
		// un recalcul de la mise en page à chaque mouvement de souris.
		const gridRect = grid.getBoundingClientRect();
		const rects = cells.map(child => child.getBoundingClientRect());
		const top = Math.min(...rects.map(rect => rect.top)) - gridRect.top;
		const bottom = Math.max(...rects.map(rect => rect.bottom)) - gridRect.top;

		if(actions.classList.contains('is-hovered') === false) {
			grid.qsa(':scope > .is-hovered', child => child.classList.remove('is-hovered'));
			actions.classList.add('is-hovered');
		}

		grid.style.setProperty('--comment-hover-top', top + 'px');
		grid.style.setProperty('--comment-hover-height', (bottom - top) + 'px');
		grid.classList.add('is-hovering');

	}

	static leave(grid) {

		grid.qsa(':scope > .is-hovered', child => child.classList.remove('is-hovered'));
		grid.classList.remove('is-hovering');

	}

}

document.delegateEventListener('mouseover', '.util-comment-hover *', function() {
	MainCommentHover.enter(this);
});

document.delegateEventListener('mouseleave', '.util-comment-hover', function() {
	MainCommentHover.leave(this);
});
