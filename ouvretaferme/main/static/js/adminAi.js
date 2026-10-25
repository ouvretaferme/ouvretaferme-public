class MainAdminAi {

	/**
	 * Ce dont on se souvient d'une visite à l'autre, [paramètre d'URL => clé de stockage].
	 * L'entrée dit quoi regarder, la période à quelle maille : les deux se choisissent une fois et
	 * valent pour les visites suivantes.
	 */
	static MEMORY = {
		entry: 'otf-statistic-entry',
		period: 'otf-statistic-period',
	};

	/**
	 * Pointe le lien « Statistiques » de la navigation admin vers la dernière vue consultée — entrée
	 * et période — et la retient quand on se trouve justement sur cet écran.
	 *
	 * Les deux gestes tiennent ici parce que l'ordre compte : la navigation est rendue avant le
	 * contenu, et mémoriser depuis l'écran lui-même réécrivait le lien avec l'entrée d'avant.
	 *
	 * Le lien est réécrit plutôt que la page redirigée : une redirection au rendu ferait passer par
	 * l'entrée par défaut avant de basculer, et ce clignotement se voit. Ici la destination est bonne
	 * avant même le clic.
	 *
	 * L'entrée par défaut du serveur est le volume d'affaires : sans ce souvenir, quelqu'un qui suit
	 * la comptabilité repasse par la commercialisation à chaque visite.
	 */
	static restoreStatistics(target) {

		const link = target.querySelector('[data-admin-link="statistics"]');

		if(link === null) {
			return;
		}

		const base = link.getAttribute('href').split('?')[0];

		try {

			// Sur l'écran des statistiques, ce qui est ouvert devient ce dont on se souviendra.
			// Un paramètre absent ne remplace rien : arriver sans période ne doit pas effacer la sienne.
			if(window.location.pathname === base) {

				const current = new URLSearchParams(window.location.search);

				for(const [parameter, key] of Object.entries(MainAdminAi.MEMORY)) {

					const value = current.get(parameter);

					if(value !== null && value !== '') {
						window.localStorage.setItem(key, value);
					}

				}

			}

			const query = new URLSearchParams();

			for(const [parameter, key] of Object.entries(MainAdminAi.MEMORY)) {

				const value = window.localStorage.getItem(key);

				if(value !== null && value !== '') {
					query.set(parameter, value);
				}

			}

			// Le href de départ sert de base : l'écran peut déménager sans toucher à ce fichier
			if(query.size > 0) {
				link.setAttribute('href', base + '?' + query.toString());
			}

		} catch(e) {
			// Navigation privée, stockage refusé : le lien garde sa destination par défaut
		}

	}

}
