/*
 * Un `.util-carousel` : une diapositive à la fois, que le doigt ou le pavé fait glisser par le
 * défilement natif de la piste, à accroche (`component.css`). Le script ne fait que tenir les points
 * sous la piste et faire défiler jusqu'à celui qu'on touche : le geste est celui du navigateur.
 *
 * Une diapositive qui porte `data-url` n'est qu'une place, que le script demande à son premier
 * affichage : la réponse la remplace par la diapositive pleine, qui ne porte plus d'adresse, et rien ne
 * la redemande. La place porte `data-waiting-block`, que la requête pâlit le temps de la réponse
 * (`juicinessAi.js`).
 *
 * La position se lit au défilement, jamais au geste : points, doigt, pavé et clavier y passent tous.
 */
class MainCarousel {

	static start(carousel) {

		const track = carousel.qs(':scope > .util-carousel-track');

		if(track === null) {
			return;
		}

		let frame = null;

		track.addEventListener('scroll', () => {

			if(frame !== null) {
				return;
			}

			frame = requestAnimationFrame(() => {
				frame = null;
				this.update(carousel);
			});

		}, {passive: true});

		carousel.qsa(':scope > .util-carousel-dots > button', dot => dot.addEventListener('click', () => {

			const position = Array.from(dot.parentElement.children).indexOf(dot);

			// Le navigateur borne le défilement en fin de piste : le point d'une des dernières cartes d'un
			// carrousel qui en montre plusieurs les amène toutes.
			track.scrollTo({left: track.children[position].offsetLeft - track.children[0].offsetLeft});

		}));

		// Une piste qui change de largeur sans défiler : un pli qui s'ouvre, un palier franchi par la
		// fenêtre. Sans quoi un carrousel apparu dans un pli ouvert après coup n'aurait aucun point.
		new ResizeObserver(() => this.update(carousel)).observe(track);

		this.update(carousel);

	}

	/*
	 * Les points des diapositives affichées, puis la demande de celles qui ne sont qu'une place. Une
	 * piste sans largeur est hors de vue, dans un panneau replié : aucune diapositive n'y est affichée.
	 *
	 * Le pas se mesure d'une diapositive à la suivante, écart compris, et non à la largeur de la piste :
	 * `util-carousel-cards` en montre plusieurs à la fois. Tout se mesure avant d'écrire.
	 */
	static update(carousel) {

		const track = carousel.qs(':scope > .util-carousel-track');
		const width = track.clientWidth;

		if(width === 0) {
			return;
		}

		const slides = Array.from(track.children);
		const step = (slides.length > 1) ? slides[1].offsetLeft - slides[0].offsetLeft : width;
		const first = Math.round(track.scrollLeft / step);
		const visible = Math.max(1, Math.round(width / step));
		const fits = (track.scrollWidth <= width);

		const dots = carousel.qs(':scope > .util-carousel-dots');

		if(dots !== null) {
			dots.hidden = fits;
			Array.from(dots.children).forEach((dot, index) => dot.classList.toggle('selected', index >= first && index < first + visible));
		}

		slides.slice(first, first + visible).forEach(slide => this.load(slide));

	}

	static load(slide) {

		if(slide.dataset.url === undefined || slide.dataset.carouselLoading !== undefined) {
			return;
		}

		slide.dataset.carouselLoading = '';

		// Une réponse en échec laisse la place : elle se redemande au prochain passage, au lieu de rester
		// blanche jusqu'au rechargement.
		new Ajax.Query(slide)
			.method('GET')
			.url(slide.dataset.url)
			.trigger(slide)
			.fetch()
			.catch(() => delete slide.dataset.carouselLoading);

	}

	/*
	 * Une diapositive que sa route a retirée, avec le point qui la suit au même rang. Les points
	 * partent avec l'avant-dernière, un point seul ne menant nulle part. La diapositive qui prend la
	 * place est affichée sans défilement, et se demande donc ici.
	 */
	static remove(slide) {

		// Le carrousel a pu être réécrit pendant la requête de la diapositive (un chiffre réglé, une autre
		// structure) : la réponse vise alors un nœud parti.
		if(slide === null) {
			return;
		}

		const carousel = slide.closest('.util-carousel');
		const track = slide.parentElement;
		const dots = carousel.qs(':scope > .util-carousel-dots');
		const position = Array.from(track.children).indexOf(slide);

		slide.remove();

		if(dots !== null) {

			dots.children[position].remove();

			if(track.children.length < 2) {
				dots.remove();
			}

		}

		this.update(carousel);

	}

}
