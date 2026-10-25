/**
 * Des rangées d'une scène qui défilent de côté sans barre, qui les grandirait dès qu'elles débordent : chacune s'estompe du côté
 * où il en reste, la molette d'une souris la fait défiler de côté, et une flèche se pose au milieu de son bord, dans l'estompe,
 * sous un pointeur fin seulement (scene.css, `.scene-scroll`). Les flèches désignent leur rangée par son nom dans `rows`
 * (`data-scene-scroll`), disent leur sens (`data-direction`, -1 ou 1) et partagent son parent positionné.
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'scroll.js')`) : il ne lit rien de three.
 */
class SceneScroll {

	constructor(rows, arrows) {

		this.rows = rows;
		this.arrows = arrows;

		this.observer = new ResizeObserver(() => this.shade());

		// Les rangées et les flèches survivent à la scène qui se ferme sur une erreur : dispose() leur retire ce qui la retiendrait
		this.listeners = new AbortController();
		const signal = this.listeners.signal;

		for(const row of Object.values(this.rows)) {
			row.addEventListener('scroll', () => this.shade(), { passive: true, signal });
			row.addEventListener('wheel', event => SceneScroll.wheel(row, event), { passive: false, signal });
			this.observer.observe(row);
		}

		// Une flèche avance d'un peu moins que la largeur montrée : l'entrée coupée au bord reste en vue, de l'autre côté
		for(const arrow of this.arrows) {
			arrow.addEventListener('click', () => {
				const row = this.rows[arrow.dataset.sceneScroll];
				// Sans animation pour qui l'a coupée dans son système : la rangée saute à sa place
				const behavior = matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
				row.scrollBy({ left: Number(arrow.dataset.direction) * row.clientWidth * 0.8, behavior });
			}, { signal });
		}

	}

	/**
	 * Une rangée qui déborde s'estompe du côté où il en reste. Ses flèches se posent au milieu de ses bords, de ce même côté, tant
	 * qu'elle se voit. Toutes les rangées se mesurent, puis toutes s'écrivent.
	 */
	shade() {

		const rows = Object.fromEntries(Object.entries(this.rows).map(([name, row]) => [name, {
			row,
			left: row.scrollLeft > 1,
			right: row.scrollLeft + row.clientWidth < row.scrollWidth - 1,
			shown: (row.offsetParent !== null && getComputedStyle(row).visibility === 'visible'),
			top: row.offsetTop + row.offsetHeight / 2,
			start: row.offsetLeft,
			end: row.offsetLeft + row.offsetWidth
		}]));

		for(const { row, left, right } of Object.values(rows)) {
			row.classList.toggle('scene-more-left', left);
			row.classList.toggle('scene-more-right', right);
		}

		for(const arrow of this.arrows) {

			const { left, right, shown, top, start, end } = rows[arrow.dataset.sceneScroll];
			const forward = (arrow.dataset.direction === '1');

			arrow.classList.toggle('hide', shown === false || (forward ? right : left) === false);
			arrow.style.top = top +'px';
			arrow.style.left = (forward ? end : start) +'px';

		}

	}

	dispose() {
		this.observer.disconnect();
		this.listeners.abort();
	}

	/**
	 * La molette d'une souris ne tourne que de haut en bas : sur une rangée qui déborde, elle la fait défiler de côté. Au bout de la
	 * rangée, elle rend la main à la page. Un pavé tactile qui défile déjà de côté garde son geste.
	 */
	static wheel(row, event) {

		if(Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
			return;
		}

		// deltaY se compte en pixels, en lignes ou en pages selon la souris
		const delta = event.deltaY * [1, 16, row.clientWidth][event.deltaMode];
		const end = row.scrollWidth - row.clientWidth;

		if((delta < 0 && row.scrollLeft <= 0) || (delta > 0 && row.scrollLeft >= end - 1)) {
			return;
		}

		event.preventDefault();
		row.scrollLeft += delta;

	}

}
