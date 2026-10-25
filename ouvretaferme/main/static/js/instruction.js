new Lime.Instruction('main')
	.register('updateHeader', function(nav, subNav, subNavTarget, sectionHtml, navHtml, breadcrumbs, structure) {

		// Un en-tête posé par une autre structure, atteinte par un lien en navigation ajax, porte ses
		// univers, ses adresses et son groupe Bureau : il se réécrit en entier, comme en l'absence de menu.
		const structureNav = (qs('#structure-nav-sections[data-structure="'+ structure +'"]') === null) ? null : qs('#structure-nav');

		if(structureNav) {

			structureNav.qsa('[data-nav].selected', node => node.classList.remove('selected'));
			structureNav.qs('[data-nav="'+ nav +'"]', node => node.classList.add('selected'));

			const subNavSelector = '[data-sub-nav-menu="'+ nav +'"][data-sub-nav="'+ subNav +'"]';

			structureNav.qsa('[data-sub-nav].selected', node => node.classList.remove('selected'));
			structureNav.qsa(subNavSelector, node => node.classList.add('selected'));

			if(subNavTarget !== null) {
				structureNav.qsa(subNavSelector, node => node.setAttribute('href', subNavTarget));
			}

			structureNav.qs('#structure-breadcrumbs', node => node.renderInner(breadcrumbs));

			// Les liens du sélecteur d'exercice pointent vers la page courante : #structure-nav n'étant pas remplacé ici, il faut les reprendre du nav fraîchement rendu.
			structureNav.qs('#structure-financial-years', node => {

				const financialYears = new DOMParser().parseFromString(navHtml, 'text/html').querySelector('#structure-financial-years');

				if(financialYears) {
					node.renderInner(financialYears.innerHTML);
				}

			});

			// Le compte au pied de la colonne, hors de #structure-nav, porte la photo de profil, qu'un écran peut avoir
			// changée (un avatar enregistré, une photo envoyée) : il se reprend lui aussi du nav fraîchement rendu. Celui
			// de la barre du haut vit dans #root-nav, que chaque écran réécrit
			qs('header > .structure-nav-foot .structure-nav-account', node => {

				const account = new DOMParser().parseFromString(navHtml, 'text/html').querySelector('.structure-nav-account');

				if(account) {
					node.renderOuter(account.outerHTML);
				}

			});

		} else {
			// sectionHtml vaut null quand les univers sont rendus ailleurs : concaténé tel quel, il
			// écrirait la chaîne « null » dans l'entête.
			qs('header').renderInner(sectionHtml === null ? navHtml : sectionHtml + navHtml);
		}

	})
	.register('updateNavPlanning', function(url, period) {

		qs('#structure-nav [data-nav="planning"]', node => node.setAttribute('href', url));

		if(qs('#structure-tab-planning-period')) {
			qs('#structure-tab-planning-period').innerHTML = qs('#structure-tab-planning-'+ period).innerHTML;
			qsa('[data-dropdown-id="structure-tab-planning-list"] .dropdown-item', item => item.classList.remove('selected'))
			qs('#structure-tab-planning-'+ period).classList.add('selected');
		}

	})
	.register('updateNavTeam', function(url) {

		qs('#structure-nav [data-nav="team"]', node => node.setAttribute('href', url));

	})
	.register('keepScroll', function() {

		if(
			document.body.getAttribute('data-touch') === 'yes' ||
			window.innerHeight < 600 /* Hauteur minimale pour la navigation de gauche */
		) {

			const node = qs('body > nav');

			const bounds = node.getBoundingClientRect();
			const position = bounds.top + bounds.height + window.scrollY;

			window.scrollTo(0, Math.min(position, this.initialScrollY));

		} else {
			window.scrollTo(0, 0);
		}

	})
	.register('resetScroll', function() {

		window.scrollTo(0, 0);

	});
