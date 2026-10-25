class PlantCommunity {

	/**
	 * Les deux onglets n'ont pas la même hauteur : quitter le bas des créneaux pour la
	 * conduite laisse le navigateur ramener le défilement à la fin d'une page plus courte,
	 * et la barre d'onglets finit au-dessus de l'écran, avec le début du nouvel onglet.
	 * Elle revient alors sous l'en-tête collant, dont la hauteur est sa marge de défilement
	 * (`--mainSticky`, nulle sur grand écran) ; tant qu'elle y est visible, rien ne bouge.
	 */
	static selectTab(item) {

		Lime.Tab.select(item);

		const tabs = item.closest('.tabs');

		if(tabs.getBoundingClientRect().top < parseFloat(getComputedStyle(tabs).scrollMarginTop)) {
			tabs.scrollIntoView({block: 'start', behavior: 'instant'});
		}

	}

	/**
	 * Changer la valeur d'un menu déroulant, c'est recharger la page sur la même vue.
	 *
	 * L'URL de base porte déjà les autres filtres : le script n'a qu'à y accrocher le
	 * sien, qu'il lit dans le nom du champ, sans rien savoir de la route ni du reste.
	 * Le séparateur dépend de ce que porte déjà cette URL — sans filtre elle n'a
	 * aucun paramètre.
	 *
	 * Une valeur vide n'accroche rien : c'est ainsi que « toutes » se dit dans une
	 * URL, par l'absence du paramètre.
	 */
	static change(select) {

		const url = select.dataset.url;
		const value = select.value;

		new Ajax.Navigation()
			.url(value === '' ? url : url + (url.includes('?') ? '&' : '?') + select.name + '=' + encodeURIComponent(value))
			.fetch();

	}

	/**
	 * L'axe des densités porte jusqu'à dix graduations, plus qu'une bande étroite n'en
	 * laisse lire. Il n'en garde alors qu'une sur deux, sur cinq, sur dix…, la plus
	 * petite cadence dont les libellés ne se touchent pas, et toujours les multiples
	 * ronds (`data-rank` est le rang de la graduation depuis zéro). Un libellé qui
	 * sortirait du bloc à droite se tait aussi.
	 *
	 * Toutes les bandes partagent l'axe et la largeur : la première décide pour toutes.
	 * Les libellés tus gardent leur place (`visibility`), les mesures ne dépendent donc
	 * pas du résultat : toutes les mesures d'abord, toutes les écritures ensuite.
	 */
	static fitAxis(bands) {

		new ResizeObserver(() => {

			const axes = Array.from(bands.querySelectorAll('.conduct-axis'));
			const limit = axes[0].getBoundingClientRect().right;
			const labels = Array.from(axes[0].children).map(label => ({
				rank: parseInt(label.dataset.rank),
				box: label.getBoundingClientRect()
			}));

			const fits = cadence => {
				const kept = labels.filter(label => label.rank % cadence === 0);
				return kept.every((label, index) => index === 0 || kept[index - 1].box.right + 4 <= label.box.left);
			};

			// 1, 2, 5, 10, 20, 50… jusqu'à la première cadence qui tient.
			let cadence = 1;

			while(fits(cadence) === false) {
				cadence *= (String(cadence)[0] === '2') ? 2.5 : 2;
			}

			const silent = labels.map(label => label.rank % cadence !== 0 || label.box.right > limit);

			axes.forEach(axis => Array.from(axis.children).forEach((label, index) => label.classList.toggle('is-silent', silent[index])));

		}).observe(bands);

	}

	/**
	 * Le survol d'une semaine d'implantation montre sa cohorte dans la bande des
	 * récoltes. Les cohortes sont déjà dans la page, cachées : il n'y a qu'à montrer
	 * la bonne et sa phrase, et à griser la courbe de l'espèce.
	 *
	 * Le mode ne s'allume qu'après une seconde continue sur la bande d'implantation,
	 * pour qu'un passage de la souris vers le bas de la page ne fasse pas clignoter la
	 * bande des récoltes. Une fois allumé, passer d'une semaine à l'autre est immédiat.
	 * Il reste allumé tant que la souris va de la bande d'implantation à celle des
	 * récoltes, puisque c'est là qu'on le lit, et s'éteint dès qu'elle sort des deux.
	 *
	 * Au toucher, il n'y a pas de survol à attendre : la semaine touchée s'allume tout
	 * de suite. `pointerleave` part dès que le doigt se lève, c'est donc un toucher hors
	 * de la bande d'implantation qui éteint, écouté sur le document.
	 */
	static listenCohorts(chart) {

		const implantation = chart.querySelector('.community-chart-row.is-implantation');
		const harvest = chart.querySelector('.community-chart-row.is-harvest');

		if(implantation === null || harvest === null) {
			return;
		}

		let current = null;
		let active = false;
		let timer = null;

		const stop = () => {
			clearTimeout(timer);
			timer = null;
			active = false;
			PlantCommunity.hideCohort(chart);
		};

		implantation.addEventListener('pointerenter', e => {

			if(e.pointerType === 'touch' || active || timer !== null) {
				return;
			}

			timer = setTimeout(() => {

				timer = null;
				active = true;

				if(current !== null) {
					PlantCommunity.showCohort(chart, current);
				}

			}, 1000);

		});

		implantation.querySelectorAll('.community-chart-week[data-week]').forEach(week => {

			week.addEventListener('pointerenter', e => {

				current = week;

				if(e.pointerType === 'touch') {
					active = true;
				}

				if(active) {
					PlantCommunity.showCohort(chart, week);
				}

			});

		});

		const leave = e => {

			if(e.pointerType === 'touch') {
				return;
			}

			const inside = (e.relatedTarget instanceof Node && (implantation.contains(e.relatedTarget) || harvest.contains(e.relatedTarget)));

			// La seconde se compte sur la bande d'implantation seule : descendre sur les
			// récoltes avant qu'elle soit écoulée la remet à zéro.
			if(inside && active) {
				return;
			}

			if(inside) {
				clearTimeout(timer);
				timer = null;
				return;
			}

			current = null;
			stop();

		};

		implantation.addEventListener('pointerleave', leave);
		harvest.addEventListener('pointerleave', leave);

		const touch = e => {

			if(chart.isConnected === false) {
				document.removeEventListener('pointerdown', touch);
				return;
			}

			if(e.pointerType === 'touch' && implantation.contains(e.target) === false) {
				stop();
			}

		};

		document.addEventListener('pointerdown', touch);

	}

	/**
	 * Toute semaine grise la courbe de l'espèce et dit ce qu'elle a : une semaine vide
	 * ou sous le seuil n'a que sa phrase, ni cohorte ni lien.
	 */
	static showCohort(chart, week) {

		PlantCommunity.hideCohort(chart);

		chart.querySelectorAll('[data-cohort="'+ week.dataset.week +'"]').forEach(node => node.hidden = false);
		chart.querySelector('.community-chart-row.is-harvest .community-chart-scale:not([data-cohort])').hidden = true;

		week.classList.add('util-column-hover');
		chart.classList.add('has-cohort');

	}

	static hideCohort(chart) {

		if(chart.classList.contains('has-cohort') === false) {
			return;
		}

		chart.querySelectorAll('[data-cohort]').forEach(node => node.hidden = true);
		chart.querySelector('.community-chart-row.is-harvest .community-chart-scale:not([data-cohort])').hidden = false;
		chart.querySelectorAll('.community-chart-week.util-column-hover').forEach(week => week.classList.remove('util-column-hover'));

		chart.classList.remove('has-cohort');

	}

}
