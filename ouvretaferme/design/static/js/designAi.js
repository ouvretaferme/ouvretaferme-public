/**
 * Le sommaire de la page du design system marque la famille qu'on lit : celle qui croise une ligne de
 * lecture posée sous la barre collée en haut, et non le haut de l'écran, où la fin de la famille
 * précédente reste cachée sous la barre après un saut d'ancre.
 */
class DesignSummary {

	static observer = null;

	// La ligne de lecture, en part de la hauteur de l'écran depuis son haut.
	static LINE = 0.2;

	static start() {

		if(DesignSummary.observer !== null) {
			DesignSummary.observer.disconnect();
			DesignSummary.observer = null;
		}

		const summary = qs('#design-summary');

		if(summary === null) {
			return;
		}

		const families = Array.from(summary.qsa(':scope > .tab-panel > .design-family'));
		const read = new Set();

		DesignSummary.observer = new IntersectionObserver(entries => {

			entries.forEach(entry => entry.isIntersecting ? read.add(entry.target) : read.delete(entry.target));

			const current = families.find(family => read.has(family)) ?? DesignSummary.getAbove(families);

			summary.qsa(':scope > .tabs-item > .tab-item').forEach(item => item.classList.toggle('selected', item.dataset.family === current?.id));

		}, {rootMargin: `-${DesignSummary.LINE * 100}% 0px -${99 - DesignSummary.LINE * 100}% 0px`});

		families.forEach(family => DesignSummary.observer.observe(family));

	}

	/**
	 * Un changement d'ancre dans l'adresse lève un `popstate`, auquel `Lime.History` répond en rechargeant
	 * la page : le sommaire et les liens vers une fiche font donc défiler sans toucher à l'adresse.
	 */
	static go(item) {
		qs('#'+ item.dataset.target).scrollIntoView({block: 'start'});
	}

	/**
	 * Aucune famille ne croise la ligne : un saut l'a posée dans le blanc d'un filet, au-dessus de la
	 * première famille ou sous la dernière. La famille lue est alors la dernière commencée au-dessus
	 * de la ligne, la première en haut de page, aucune une fois la dernière finie.
	 */
	static getAbove(families) {

		const line = window.innerHeight * DesignSummary.LINE;
		const rects = families.map(family => family.getBoundingClientRect());
		const index = rects.findLastIndex(rect => rect.top <= line);

		if(index === -1) {
			return families[0];
		}

		if(index === families.length - 1 && rects[index].bottom < line) {
			return null;
		}

		return families[index];

	}

}

document.addEventListener('navigation.wakeup', () => DesignSummary.start());

// Les liens des démonstrations n'ont pas de destination, et leur ancre partirait ailleurs : le gabarit
// pose `<base href>` sur l'hôte, où « # » mène à l'accueil, et un changement d'ancre recharge la page.
document.delegateEventListener('click', '#design-summary a[href^="#"]', e => e.preventDefault());
