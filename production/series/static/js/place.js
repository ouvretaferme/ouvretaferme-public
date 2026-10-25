document.delegateEventListener('input', '#place-update [name="beds[]"]', function() {
	SeriesPlace.selectBed(this);
});

document.delegateEventListener('input', '#place-update [name^="sizes"]', function() {
	SeriesPlace.updateSelected();
});

class SeriesPlace {

	static get series() {

		// Le plan porte la serie qu'il arme : la fiche du bac n'est pas toujours
		// affichee, et sans ce recours les boutons Poser restaient inertes.
		return qs('#place-context')?.dataset.series
			?? qs('#place-search [name="series"]')?.value
			?? qs('#zone-container')?.dataset.series
			?? null;

	}

	static get task() {
		return qs('#zone-container')?.dataset.task ?? null;
	}

	// D'où le panneau a été ouvert, posé par le serveur sur le panneau lui-même : les
	// écritures et le plan d'assolement empruntent les mêmes routes, et l'origine est
	// ce qui les distingue.
	static get origin() {
		return qs('#place-update')?.dataset.origin ?? null;
	}

	static armedKept = null;

	static rememberArmed() {
		this.armedKept = this.editing ? this.series : null;
	}

	static restoreArmed() {

		const series = this.armedKept;

		this.armedKept = null;

		if(
			series === null ||
			this.editing === false ||
			this.series !== null ||
			qs('#series-selector') === null
		) {
			return;
		}

		SeriesSelector.focusSeries(series);

	}

	static get armed() {

		const task = this.task;

		return (task === null) ?
			{series: this.series} :
			{task: task};

	}

	static get labels() {
		return window.seriesPlaceLabels ?? {};
	}

	static setFilter(node, name, value) {

		const select = qs('#place-search [name="'+ name +'"]');

		if(select === null) {
			return;
		}

		select.value = value;

		const filter = select.closest('.place-search-filter');
		const list = node.closest('.dropdown-list');

		list?.qsa('.dropdown-item', item => item.classList.remove('selected'));
		node.classList.add('selected');

		filter?.qs('.place-chip-value', label => label.innerHTML = node.innerHTML);
		filter?.qs('.place-chip', chip => {

			// is-solid porte le fond, place-chip-on la couleur du texte : ne basculer
			// que la seconde laissait un jeton peint comme actif sur une valeur qui
			// ne filtre rien.
			const on = (select.selectedIndex > 0);

			chip.classList.toggle('place-chip-on', on);
			chip.classList.toggle('is-solid', on);

			Lime.Dropdown.close(chip);

		});

		this.search();

	}

	// Les filtres tels que le navigateur les tient, pour les faire voyager avec
	// chaque requete : sans eux le serveur rend une fiche remise a zero pendant
	// que le plan, lui, reste filtre.
	static filterValues() {

		const wrapper = qs('#place-search');

		if(wrapper === null) {
			return {};
		}

		const values = {};

		['mode', 'width', 'free', 'rotation'].forEach(name => {

			const field = wrapper.qs('[name="'+ name +'"]');

			if(field === null) {
				return;
			}

			// La largeur voyage toujours, meme a zero : cote serveur son absence vaut
			// « la largeur de la serie », et « Toutes » doit pouvoir s'en distinguer.
			if(name === 'width' || (field.value !== '' && field.value !== '0')) {
				values[name] = field.value;
			}

		});

		return values;

	}

	static scope() {

		const params = new URLSearchParams(window.location.search);

		return ['zone', 'plot']
			.map(name => params.get(name))
			.map((value, index) => (value === null || value === '') ? '' : '&'+ ['zone', 'plot'][index] +'='+ encodeURIComponent(value))
			.join('');

	}

	static filters() {

		return Object.entries(this.filterValues())
			.map(([name, value]) => '&'+ name +'='+ value)
			.join('');

	}

	// Replier ou deplier les planches situees au-dessus de l'ecran deplace ce qu'on
	// regardait : quitter une serie redepliait tout et renvoyait en haut de page, sur
	// un navigateur qui n'ancre pas le defilement de lui-meme (overflow-anchor).
	// La planche au centre de l'ecran est relevee avant, et la page recalee sur elle.
	static holdView() {

		const anchor = document.elementFromPoint(window.innerWidth / 2, window.innerHeight / 2)?.closest('.bed-item-grid, .garden-col, .plot-wrapper') ?? null;

		return {
			anchor: anchor,
			top: (anchor === null) ? null : anchor.getBoundingClientRect().top
		};

	}

	static restoreView(view) {

		if(view.anchor !== null && view.anchor.isConnected) {
			window.scrollBy(0, view.anchor.getBoundingClientRect().top - view.top);
		}

	}

	// A petite largeur la frise commence en septembre de l'annee precedente, et la
	// serie tombait hors de l'ecran : chaque carte du panneau s'ouvre centree sur
	// elle. Une planche repliee ne se mesure pas, d'ou la premiere visible.
	static centerOnSeries() {

		const positions = [];

		qsa('#place-update .plot-card-scroll', scroll => {

			if(scroll.scrollWidth <= scroll.clientWidth) {
				return;
			}

			const block = [...scroll.qsa('.place-current, .place-grid-series-timeline-placeholder')].find(node => node.getClientRects().length > 0);

			if(block === undefined) {
				return;
			}

			const box = scroll.getBoundingClientRect();
			const rect = block.getBoundingClientRect();
			const label = scroll.qs('.plot-card-axis > div:first-child')?.offsetWidth ?? 0;
			const visible = scroll.clientWidth - label;
			const start = rect.left - box.left + scroll.scrollLeft - label;

			positions.push([scroll, (rect.width > visible) ? start : start - (visible - rect.width) / 2]);

		});

		positions.forEach(([scroll, left]) => scroll.scrollLeft = left);

	}

	/**
	 * Refermer la fiche rouvre le plan : les filtres vivent avec elle, et les laisser
	 * derriere laissait des planches repliees sans plus rien pour dire pourquoi.
	 */
	static clearSearch() {

		this.clearUnfolded();

		this.updateSearch({mode: '', width: false, free: 0, rotation: 0});

	}

	static search() {

		const wrapper = qs('#place-search');

		if(wrapper === null) {
			return;
		}

		this.clearUnfolded();

		this.updateSearch({
			mode: wrapper.qs('[name="mode"]').value,
			width: wrapper.qs('[name="width"]')?.value === '1' ? true : false,
			free: parseInt(wrapper.qs('[name="free"]')?.value ?? 0),
			rotation: parseInt(wrapper.qs('[name="rotation"]').value)
		});

	}

	// Apres un paquet d'etat le plan n'a pas ete redessine : sans filtre actif et sans
	// planche repliee, la passe n'aurait rien a changer et coutait 30 a 90 ms de mesures.
	static searchIfFiltered() {

		const values = this.filterValues();
		const active = Object.entries(values).some(([name, value]) => (name === 'width') ? (value === '1') : true);

		if(active === false && qs('#zone-container [data-hide="1"]') === null) {
			return;
		}

		this.search();

	}

	static toggleSelection(target) {

		CheckboxField.all(target.firstParent('.plot-wrapper, .zone-wrapper'), target.checked, '.bed-item-grid:not(.bed-item-fill) [name^="beds[]"]', (bed) => this.selectBed(bed));

	}

	static selectBed(target) {

		SeriesPlace.updateSelected();

		const zoneWrapper = target.firstParent('.zone-wrapper');
		const zoneCount = zoneWrapper ? qs('#zone-count-'+ zoneWrapper.dataset.zone) : null;

		if(zoneCount) {
			const beds = zoneWrapper.qsa('[name="beds[]"]:checked').length;
			zoneCount.innerHTML = (beds > 0) ? beds : '';
		}

		const plotWrapper = target.firstParent('.plot-wrapper');
		const plotCount = plotWrapper ? qs('#plot-count-'+ plotWrapper.dataset.plot) : null;

		if(plotCount) {
			const beds = plotWrapper.qsa('[name="beds[]"]:checked').length;
			plotCount.innerHTML = (beds > 0) ? beds : '';
		}

	}

	static updateSelected() {

		const nodeValue = qs('#place-update-value');

		if(nodeValue === null) {
			return;
		}

		const nodeTarget = nodeValue.firstParent('[data-value-target]');
		const form = qs('#place-update');

		if(form === null) {
			return;
		}

		let totalValue = 0;
		form.qsa('div.bed-item-grid:has([name^="beds"]:checked) [name^="sizes"], div.zone-wrapper:has(input.zone-title-fill:checked) .bed-item-fill-zone [name^="sizes"], div.plot-wrapper:has(input.plot-title-fill:checked) .bed-item-fill-plot [name^="sizes"]', (node) => totalValue += parseInt(node.value || 0));

		nodeValue.innerHTML = totalValue;

		if(
			nodeTarget !== null &&
			nodeTarget.dataset.valueTarget !== ''
		) {

			const totalTarget = parseInt(nodeTarget.dataset.valueTarget);

			if(totalValue === 0) {
				nodeTarget.dataset.color = 'zero';
			} else if(totalValue !== totalTarget) {
				nodeTarget.dataset.color = 'gap';
			} else {
				nodeTarget.dataset.color = '';
			}

		}

	}

	static updateSearch(query) {

		const searchMode = query.mode || '';
		const searchWidth = query.width || false;
		const searchFree = query.free || 0;
		const searchRotation = query.rotation || 0;
		const searchSeries = query.series || null;
		const posed = this.series ?? null;
		const kept = (posed === null) ? null : SeriesResize.getSeriesBeds(posed);

		const view = this.holdView();

		// Les libelles sont lus une fois pour toutes, et par textContent : innerText
		// force une mise en page, et appele dans la boucle juste apres avoir pose
		// data-hide sur la planche precedente, il en forcait une par planche cachee.
		// Mesure : 5,3 s pour 161 planches, 0,1 s ici.
		const labels = {
			free: this.getFilterLabel('free'),
			width: this.getFilterLabel('width'),
			rotation: this.getFilterReason('rotation'),
			mode: this.getFilterReason('mode'),
		};

		qsa('.zone-wrapper', zoneWrapper => {

			let zoneHide = 1;
			let zonePlots = 0;

			zoneWrapper.qsa('.plot-wrapper', plotWrapper => {

				let plotHide = 1;
				let plotBeds = 0;

				zonePlots++;

				plotWrapper.qsa('.bed-item-grid, .garden-col', bed => {

					// La surface libre ne se teste que sur le mode de son jardin, et ne decide
					// pas du repli de sa parcelle : elle le suit.
					if(bed.classList.contains('garden-col-fill') || bed.classList.contains('bed-item-fill')) {

						const fillHide = (
							(searchMode === 'open-field' && bed.dataset.greenhouse === '1') ||
							(searchMode === 'greenhouse' && bed.dataset.greenhouse === '0')
						) && (kept === null || kept.has(bed.dataset.bed) === false);

						bed.dataset.hide = fillHide ? 1 : 0;
						bed.dataset.mismatch = fillHide ? 1 : 0;
						bed.dataset.hiddenBy = fillHide ? labels.mode : '';

						return;

					}

					plotBeds++;

					let bedHide = 0;
					const hiddenBy = [];

					if(searchSeries !== null) {

						if(bed.qs('.place-grid-series-timeline[data-series="'+ searchSeries +'"], .place-garden-block[data-series="'+ searchSeries +'"]') === null) {
							bedHide = 1;
						}

					}

					switch(searchFree) {

						case 0 :
							break;

						case 100 :
							if(bed.dataset.free !== '0') {
								bedHide = 1;
								hiddenBy.push(labels.free);
							}
							break;

						case 1 :
							if(bed.dataset.free !== '0' && bed.dataset.free !== '1') {
								bedHide = 1;
								hiddenBy.push(labels.free);
							}
							break;

						case 2 :
							if(bed.dataset.free !== '0' && bed.dataset.free !== '1' && bed.dataset.free !== '2') {
								bedHide = 1;
								hiddenBy.push(labels.free);
							}
							break;

					}

					let bedMismatch = 0;

					switch(searchMode) {

						case '' :
							break;

						// Le mode replie comme les autres filtres : une planche que la
						// recherche exclut se referme et dit pourquoi, au lieu de rester
						// ouverte avec un fond grise.
						case 'open-field' :
							if(bed.dataset.greenhouse === '1') {
								bedMismatch = 1;
								bedHide = 1;
								hiddenBy.push(labels.mode);
							}
							break;

						case 'greenhouse' :
							if(bed.dataset.greenhouse === '0') {
								bedMismatch = 1;
								bedHide = 1;
								hiddenBy.push(labels.mode);
							}
							break;

					}

					if(
						searchWidth === true &&
						bed.dataset.sameWidth === '0'
					) {
						bedHide = 1;
						hiddenBy.push(labels.width);
					}

					if(
						searchRotation > 0 &&
						bed.dataset.rotation !== ''
					) {

						const rotation = parseInt(bed.dataset.rotation);

						if(searchRotation > rotation) {
							bedHide = 1;
							hiddenBy.push(labels.rotation);
						}

					}

					// Une planche qui porte deja la serie reste ouverte quels que soient
					// les filtres : c'est la qu'on vient la retirer ou la regler.
					if(kept !== null && kept.has(bed.dataset.bed)) {
						bedHide = 0;
						bedMismatch = 0;
						hiddenBy.length = 0;
					}

					bed.dataset.mismatch = bedMismatch;

					if(bedHide === 0) {
						zoneHide = 0;
						plotHide = 0;
					}

					bed.dataset.hide = bedHide;
					bed.dataset.hiddenBy = (bedHide === 1) ? hiddenBy.join(' · ') : '';

					if(bedHide === 1) {

						if(bed.dataset.hiddenTitle === undefined) {
							bed.dataset.hiddenTitle = bed.getAttribute('title') ?? '';
						}

						bed.title = bed.dataset.hiddenBy.replace(/<[^>]+>/g, '');

					} else if(bed.dataset.hiddenTitle !== undefined) {

						if(bed.dataset.hiddenTitle === '') {
							bed.removeAttribute('title');
						} else {
							bed.title = bed.dataset.hiddenTitle;
						}

						delete bed.dataset.hiddenTitle;

					}

				});

				// Une parcelle qui n'a que de la surface libre n'offre rien a tester : la
				// replier la ferait disparaitre alors qu'aucun filtre ne l'a exclue.
				if(plotBeds === 0) {
					plotHide = 0;
					zoneHide = 0;
				}

				plotWrapper.dataset.hide = plotHide;

			});

			if(zonePlots === 0) {
				zoneHide = 0;
			}

			zoneWrapper.dataset.hide = zoneHide;

		});

		qsa('.place-hidden-unfolded', col => col.classList.remove('place-hidden-unfolded'));
		this.restoreUnfolded();

		this.paintRails();

		this.resizeMerged();

		this.restoreView(view);

	}

	// Un bloc de plusieurs planches est large de N colonnes. Quand un filtre en
	// replie certaines, la somme reelle ne vaut plus N fois la largeur pleine : le
	// bloc debordait sur les colonnes repliees. On mesure d'abord, on ecrit ensuite.
	static resizeMerged() {

		const blocks = [...document.querySelectorAll('.place-garden-block-merged[data-span]')];

		const widths = blocks.map(block => {

			const cell = block.closest('.garden-col[data-bed]');

			if(cell === null) {
				return null;
			}

			const beds = SeriesResize.getBeds(cell);
			const start = beds.indexOf(cell);
			const span = Math.max(1, parseInt(block.dataset.span || 1));

			if(start === -1) {
				return null;
			}

			const total = beds
				.slice(start, start + span)
				.reduce((sum, node) => sum + node.getBoundingClientRect().width, 0);

			return total - (parseFloat(block.style.left) || 2) - 4;

		});

		blocks.forEach((block, index) => {

			if(widths[index] !== null && widths[index] > 0) {
				block.style.width = widths[index].toFixed(1) +'px';
			}

		});

	}

	static getFilterLabel(name) {

		const chip = qs('#place-search [name="'+ name +'"]')?.closest('.place-search-filter')?.qs('.place-chip-label');

		return (chip === null || chip === undefined) ? name : chip.textContent.trim();

	}

	static getFilterReason(name) {

		const label = this.getFilterLabel(name);
		const value = qs('#place-search [name="'+ name +'"]')?.closest('.place-search-filter')?.qs('.place-chip-value');

		return (value === null || value === undefined) ? label : label +' <b>'+ value.textContent.trim() +'</b>';

	}

	// Le gabarit n'est plus reecrit ici. Les pistes sont en « auto » et chaque
	// colonne porte sa largeur : --col-width au repos, 0,875 rem quand un filtre
	// l'ecarte. Reecrire les pistes rendait toutes les colonnes visibles a une
	// largeur de planche et effacait celles de la surface libre, qui en vaut
	// autant que de voies.
	static paintRails() {

		this.paintHiddenCount();

	}

	static paintHiddenCount() {

		qsa('.plot-wrapper', plot => {

			plot.qs('.place-hidden-count', node => node.remove());

			// La surface libre repliee n'est pas une planche masquee
			const hidden = [...plot.qsa('.garden-col[data-bed][data-hide="1"]:not(.garden-col-fill), .bed-item-grid[data-bed][data-hide="1"]:not(.bed-item-fill)')];

			if(hidden.length === 0) {
				return;
			}

			const reasons = [...new Set(hidden.map(col => col.dataset.hiddenBy).filter(r => r))];
			const labels = window.seriesPlaceLabels ?? {};

			const one = (hidden.length === 1);

			const template = (reasons.length === 1) ?
				(one ? labels.hiddenOneByOne : labels.hiddenManyByOne) :
				(one ? labels.hiddenOneByMany : labels.hiddenManyByMany);

			const node = document.createElement('div');

			node.className = 'place-hidden-count';
			node.innerHTML = (template ?? '')
				.replace('{count}', hidden.length)
				.replace('{value}', (reasons.length === 1) ? reasons[0] : reasons.length);

			plot.append(node);

		});

	}

	static editingFlag = false;

	static get editing() {
		return (this.editingFlag || qs('#place-update') !== null);
	}

	static set editing(editing) {
		this.editingFlag = editing;
	}

	// Le mode assoler ecrit les series : il n'existe que pour qui peut les ecrire.
	// series/ui/Header.u.php ne rend le bouton de bascule que sous canWrite(), et
	// c'est ce signe que lisent les autres entrees du mode, l'appui long et la
	// reprise en localStorage.
	static get writable() {
		return (qs('#series-selector-button') !== null);
	}

	// Vrai tant qu'une bascule de serie est en vol. Le plan met deux secondes a se
	// redessiner : sans ce verrou on reclique, et le second clic tombe dans un plan
	// qui n'existe deja plus.
	static switching = false;

	static storage = 'place-editing';

	static restoreEditing() {

		let stored = null;

		try {
			stored = localStorage.getItem(this.storage);
		} catch(e) {
		}

		return this.setEditing(stored === '1');

	}

	/**
	 * Le verrou de bascule, et son signe a l'ecran : deux secondes sans retour font
	 * recliquer, et le reclic defaisait le premier clic.
	 */
	static setSwitching(switching) {

		this.switching = switching;

		// Aucun signe a l'ecran : une classe d'attente, sur body comme sur le conteneur,
		// faisait recalculer le style de tout le plan a l'aller et au retour, 30 a 170 ms
		// chaque fois sur la ferme 7, pour une reponse qui revient en moins de 200 ms.
		// Filet : si la reponse ne revient jamais, le plan ne reste pas verrouille.
		clearTimeout(this.switchingTimer);

		if(switching) {
			this.switchingTimer = setTimeout(() => this.setSwitching(false), 8000);
		}

	}

	static toggleEditing() {

		if(this.editing) {
			this.stopEditing();
		} else {
			this.setEditing(true);
		}

	}

	/**
	 * Quitter l'assolement recharge la page. Les filtres du bac (largeur de planche,
	 * mode, rotation) masquent des planches et vivent cote client : sans rechargement
	 * on revenait en lecture sur un plan encore ampute, sans plus rien pour le dire.
	 * Le rechargement ne peut pas partir de setEditing(), que restoreEditing() appelle
	 * au chargement : il bouclerait.
	 */
	static stopEditing() {

		this.setEditing(false);

		document.location.reload();

	}

	static setEditing(editing) {

		if(editing && this.writable === false) {
			return;
		}

		this.editing = editing;

		// La fiche d'une série ne sert qu'en lecture : ses gestes ne se melent pas a ceux de l'assolement
		if(editing) {
			SeriesPlaceSheet.close();
		}

		// Poser la chaine vide laisserait l'attribut present : `[data-place-editing]`
		// matcherait encore, et la regle de masquage du bac ne s'appliquerait jamais.
		if(editing) {
			document.body.dataset.placeEditing = '1';
		} else {
			delete document.body.dataset.placeEditing;
		}

		// Le mode survit a la visite : il ne se referme que sur un geste explicite,
		// « Terminer l'assolement » ou la croix du bac.
		try {
			if(editing) {
				localStorage.setItem(this.storage, '1');
			} else {
				localStorage.removeItem(this.storage);
			}
		} catch(e) {
		}

		qs('#series-selector-button', node => {
			node.qs('span', label => label.innerHTML = editing ? node.dataset.labelStop : node.dataset.labelStart);
		});

		if(editing) {
			SeriesHelper.show();
		} else {
			SeriesHelper.hide();
		}

		// Le bac n'existe que pour poser des series : en lecture il n'a aucun objet,
		// et le laisser ouvert et inerte serait pire que son absence.
		if(editing) {

			const shown = SeriesSelector.show();

			const peindre = () => {
				this.paintTokens();
				this.paintPoseBar();
			};

			(shown instanceof Promise) ? shown.then(peindre) : peindre();

			return shown;

		} else {

			// Masquer, pas fermer : SeriesSelector.close() ecrit la preference
			// « bac replie » en localStorage, et l'appeler a chaque arrivee
			// l'ecraserait. La preference garde son sens, mais a l'interieur du
			// mode seulement.
			// Le masquage tient par la regle CSS sur data-place-editing, pas par
			// cet appel : la reponse ajax du bac repeint le noeud sans la classe,
			// et gagnait la course quand on quittait le mode avant qu'elle arrive.
			qs('#series-selector', node => node.hide());
			SeriesSelector.syncShown();
			SeriesSelector.deselect();
			SeriesResize.clearSelection();

		}

	}

	static send(action, body, gesture) {

		// Dernier filet : dix délégués se gardent en amont, celui-ci garde l'unique
		// point d'où part une écriture. Un délégué manqué reste ainsi un défaut
		// d'écran, jamais un défaut de donnée.
		if(this.editing === false) {
			return;
		}

		// Le geste est deja applique a l'ecran quand on arrive ici : la requete part
		// apres la premiere image, pour que le navigateur peigne le bloc a sa place
		// avant de relire la geometrie du plan et de payer l'envoi. Mesure : 44 ms de
		// lectures forcees au lacher, sur la ferme 7. Un onglet cache n'a pas d'image :
		// le second minuteur envoie quand meme, sans quoi l'ecriture attendrait le retour
		// de l'onglet.
		let sent = false;

		const go = () => {

			if(sent) {
				return;
			}

			sent = true;
			this.sendNow(action, body, gesture);

		};

		requestAnimationFrame(() => setTimeout(go, 0));
		setTimeout(go, 100);

	}

	static sendNow(action, body, gesture) {

		SeriesScroll.rememberAll();

		// La planche retenue le reste apres l'action : la reponse redessine le plan,
		// et sans ce repere la selection tombait a chaque reglage de longueur.
		const held = qs('.place-selected');

		SeriesResize.keep = SeriesResize.getKeep(held);

		const context = (this.task === null) ? this.series : null;

		const ungroup = SeriesUngroup.value();

		// La fiche série garde son bloc « Assolement » sous le panneau. Le panneau est
		// une couche d'historique : la fermer repose la page telle qu'elle était, sans
		// la redemander au serveur, donc rien n'y rafraîchit ce bloc. L'origine dit à
		// l'écriture de le renvoyer, et le plan d'assolement, qui ne l'affiche pas,
		// n'en paie pas le rendu.
		const origin = this.origin;

		new Ajax.Query(qs('#zone-container'))
			.url('/series/place:'+ action +'?view='+ (qs('.garden-plot') === null ? 'plan' : 'garden') + this.filters() + this.scope())
			.body({
				// INPUT() ne lit que $_POST sur un POST : les filtres posés dans
				// l'URL n'y parvenaient jamais, et chaque action les remettait à zéro.
				...this.filterValues(),
				...body,
				gesture: gesture ?? 'add',
				...(context === null ? {} : {context: context}),
				...(ungroup === '' ? {} : {ungroup: ungroup}),
				...(origin === null ? {} : {origin: origin}),
			})
			.fetch();

	}

	static addBeds(series, beds, gesture, size) {
		this.send('doAdd', {...this.armed, beds: [...new Set(beds)], ...(size === undefined ? {} : {size: size})}, gesture);
	}

	static removeCultivation(cultivation) {
		this.send('doUpdate', {cultivation: cultivation}, 'remove');
	}

	static removeBeds(series, beds, task = null) {

		SeriesUngroup.forget(series, beds);

		const cible = (task !== null) ? {task: task} : ((series ?? null) !== null ? {series: series} : this.armed);

		this.send('doRemove', {...cible, beds: [...new Set(beds)]}, 'remove');

	}

	static flashSeries(series, gesture, beds) {

		const touched = (beds ?? []).map(String);

		this.touched = touched;

		qsa('[data-series="'+ series +'"]', block => {

			const bed = block.closest('.garden-col, .bed-item-grid');

			if(bed === null) {
				return;
			}

			if(touched.length > 0) {

				const all = SeriesResize.getBeds(bed);
				const start = all.indexOf(bed);
				const span = Math.max(1, parseInt(block.dataset.span || 1));

				const covered = all.slice(start, start + span).map(node => node.dataset.bed);

				if(covered.some(id => touched.includes(id)) === false) {
					return;
				}

			}

			block.classList.add('place-arrive', 'place-arrive-'+ gesture);

			setTimeout(() => block.classList.remove('place-arrive', 'place-arrive-'+ gesture), 800);

		});

	}

	static toggleOutside(node) {

		const zone = node.closest('.zone-wrapper');

		if(zone === null) {
			return;
		}

		const shown = zone.classList.toggle('zone-wrapper-outside');
		const labels = window.seriesPlaceLabels ?? {};

		node.innerHTML = shown ?
			(labels.outsideHide ?? 'Masquer le hors jardin') :
			(labels.outsideShow ?? 'Afficher le hors jardin');

	}

	static vocabulary = new Map();

	static vocabularyGroups = ['plant', 'variety', 'tool'];

	static vocabularyKey(group) {
		return 'search'+ group[0].toUpperCase() + group.slice(1);
	}

	static rememberVocabulary() {

		document.querySelectorAll('#zone-container [data-search-plant]').forEach(block => {

			const series = block.dataset.series;

			if(series === undefined) {
				return;
			}

			const values = {};
			let known = false;

			this.vocabularyGroups.forEach(group => {
				const value = block.dataset[this.vocabularyKey(group)] ?? '';
				values[group] = value;
				(group === 'plant' || group === 'series' || value === '') || (known = true);
			});

			known && this.vocabulary.set(series, values);

		});

	}

	static restoreVocabulary() {

		document.querySelectorAll('#zone-container [data-search-plant]').forEach(block => {

			const values = this.vocabulary.get(block.dataset.series);

			if(values === undefined) {
				return;
			}

			this.vocabularyGroups.forEach(group => {
				const key = this.vocabularyKey(group);
				(block.dataset[key] ?? '') === '' && (block.dataset[key] = values[group]);
			});

		});

	}

	static repaintAfterRender() {

		this.restoreVocabulary();
		this.rememberVocabulary();
		this.restoreFolds();

		qs('#place-filter', box => this.filterApply(box));

	}

	static tokensMedia = '(max-width: 1099px), (max-height: 599px)';

	static tokensWanted() {
		return (this.editing && window.matchMedia(this.tokensMedia).matches && qs('#series-selector-list') !== null && qs('.garden-plot') !== null);
	}

	static searchBarWanted() {
		return (window.matchMedia(this.tokensMedia).matches && qs('#zone-container') !== null && qs('#place-filter') !== null);
	}

	static tokenPlant = null;

	static buildSearchToken() {

		const token = document.createElement('a');

		token.className = 'place-token place-token-search';
		token.dataset.tokenSearch = '1';
		token.innerHTML = this.labels.tokenSearchIcon ?? '';
		token.title = this.labels.tokenSearch ?? '';

		return token;

	}

	static toggleSearch() {

		const open = document.body.classList.toggle('place-search-open');

		this.paintSearchToken(open);

		if(open) {
			qs('#place-filter input', input => input.focus());
		} else {
			qs('#place-filter input', input => input.blur());
		}

	}

	static paintTokensArmed() {

		const armed = this.series;
		const plant = (armed === null) ?
			null :
			(qs('#series-selector-list .series-selector-cultivation[data-series="'+ armed +'"]')?.dataset.plant ?? null);

		qsa('#place-tokens .place-token', token => {

			const on = (armed !== null) && (
				token.dataset.series === armed ||
				(token.dataset.plant !== undefined && plant !== null && token.dataset.plant === plant)
			);

			token.classList.toggle('place-token-armed', on);

		});

	}

	static paintSearchToken(open) {

		qs('#place-tokens [data-token-search]', node => {
			node.innerHTML = (open ? this.labels.tokenSearchBack : this.labels.tokenSearchIcon) ?? '';
			node.title = (open ? this.labels.tokenBack : this.labels.tokenSearch) ?? '';
		});

	}

	static buildToken(label, paint, count, badge) {

		const token = document.createElement('a');

		token.className = 'badge badge-production place-token';

		paint === undefined || token.style.setProperty('--series-paint', paint);

		const span = document.createElement('span');

		span.className = 'place-token-label';
		span.textContent = label;

		token.append(span);

		if(count !== undefined) {

			const compte = document.createElement('span');

			compte.className = 'place-token-count';
			compte.textContent = count;

			token.append(compte);

		}

		if(badge !== undefined && badge.semaine !== '') {

			const marque = document.createElement('span');

			marque.className = 'badge badge-solid place-token-week';
			marque.textContent = badge.semaine;

			if(badge.couleur !== '') {
				marque.style.backgroundColor = badge.couleur;
			}

			token.append(marque);

		}

		return token;

	}

	// Deux classes sur body plutot que body:has(#place-tokens) et body:has(#place-update)
	// en CSS : meme mecanisme que SeriesSelector.syncShown(), Firefox rebalaye le
	// document a chaque insertion ou retrait du noeud vise par le :has.
	static syncTokens() {

		document.body.classList.toggle('place-tokens-shown', qs('#place-tokens') !== null);

	}

	// Sur panelClose le panneau est encore dans le document : il se retire lui-meme.
	static syncUpdate(closing = null) {

		const update = qs('#place-update');

		document.body.classList.toggle('place-update-open', update !== null && (closing === null || closing.contains(update) === false));

	}

	static paintTokens() {

		const scroll = qs('#place-tokens-scroll')?.scrollLeft ?? 0;

		qs('#place-tokens', node => node.remove());

		document.body.style.removeProperty('--place-tokens-height');

		this.syncTokens();

		if(this.searchBarWanted() === false) {
			return;
		}

		const avecJetons = this.tokensWanted();

		const cards = avecJetons ?
			[...document.querySelectorAll('#series-selector-list .series-selector-cultivation:not(.series-selector-closed)')] :
			[];

		const armed = this.series;
		const node = document.createElement('div');

		node.id = 'place-tokens';

		node.append(this.buildSearchToken());

		const rail = document.createElement('div');

		rail.id = 'place-tokens-scroll';

		node.append(rail);

		if(avecJetons === false) {

		} else if(this.tokenPlant === null) {

			const plants = new Map();

			cards.forEach(card => {

				const plant = card.dataset.plant;

				if(plant === undefined || plant === '') {
					return;
				}

				plants.has(plant) || plants.set(plant, {n: 0, paint: card.style.getPropertyValue('--series-paint-plant'), armed: false});

				const groupe = plants.get(plant);

				groupe.n++;
				(armed !== null && card.dataset.series === armed) && (groupe.armed = true);

			});

			plants.forEach((groupe, plant) => {

				const token = this.buildToken(plant, groupe.paint, groupe.n);

				token.dataset.plant = plant;

				rail.append(token);

			});

		} else {

			const retour = this.buildToken(this.labels.tokenBack ?? '← Espèces');

			retour.dataset.tokenBack = '1';

			rail.append(retour);

			cards.filter(card => card.dataset.plant === this.tokenPlant).forEach(card => {

				const name = card.qs('.series-selector-name')?.textContent.trim();

				if(name === undefined || name === '') {
					return;
				}

				const token = this.buildToken(name, card.style.getPropertyValue('--series-paint'), undefined, {
					semaine: card.dataset.startWeek ?? '',
					couleur: card.dataset.startColor ?? ''
				});

				token.dataset.cultivation = card.dataset.cultivation;
				token.dataset.series = card.dataset.series;

				rail.append(token);

			});

		}

		document.body.append(node);

		this.syncTokens();

		rail.scrollLeft = scroll;

		this.paintTokensArmed();
		this.paintSearchToken(document.body.classList.contains('place-search-open'));

		document.body.style.setProperty('--place-tokens-height', node.offsetHeight +'px');

	}

	static paintPoseBar() {

		qs('#place-pose-bar', node => node.remove());

		document.body.style.removeProperty('--place-pose-height');

		const series = this.series;

		if(this.tokensWanted() === false || series === null) {
			return;
		}

		const card = qs('#series-selector-list .series-selector-cultivation[data-series="'+ series +'"]');
		const name = card?.qs('.series-selector-name')?.textContent.trim();

		if(name === undefined || name === '') {
			return;
		}

		this.buildPoseBar(series, name);

	}

	// Au doigt, un bloc verrouille n'a pas de bulle pour dire pourquoi il se refuse :
	// la barre le nomme et l'ouvre, sans rien armer. Fermer rend la barre a la serie
	// retenue s'il y en a une.
	static paintLockedBar(block) {

		if(this.tokensWanted() === false) {
			return;
		}

		const name = block.qs('b, .place-grid-series-timeline-name')?.innerText.trim() ?? '';

		if(name === '' || block.dataset.series === undefined) {
			return;
		}

		// Rien ne s'assole sur un bloc verrouille : la serie retenue est relachee,
		// ses boutons Ajouter avec elle, avant que la barre ne nomme le bloc.
		SeriesSelector.deselect();

		const labels = this.labels;
		const status = (block.dataset.lockedStatus === 'closed') ? labels.lockedClosed : labels.lockedSeason;

		this.buildPoseBar(block.dataset.series, name, status).dataset.locked = '1';

	}

	static buildPoseBar(series, name, status = undefined) {

		const labels = this.labels;
		const node = document.createElement('div');

		node.id = 'place-pose-bar';

		const titre = document.createElement('b');
		titre.textContent = name;

		if(status !== undefined) {

			const detail = document.createElement('small');
			detail.textContent = status;

			titre.append(detail);

		}

		const ouvrir = document.createElement('a');
		ouvrir.className = 'place-pose-bar-action';
		ouvrir.dataset.poseOpen = series;
		ouvrir.href = '/serie/'+ series;
		ouvrir.target = '_blank';
		ouvrir.rel = 'noopener';
		ouvrir.textContent = labels.poseOpen ?? 'Ouvrir la série';

		const fermer = document.createElement('a');
		fermer.className = 'place-pose-bar-action';
		fermer.dataset.poseClose = '1';
		fermer.textContent = labels.poseClose ?? 'Fermer';

		node.append(titre, ouvrir, fermer);
		document.body.append(node);

		document.body.style.setProperty('--place-pose-height', node.offsetHeight +'px');

		return node;

	}

	static filterGroups = {plant: 'Espèces', variety: 'Variétés', tool: 'Matériel'};

	static filterZone() {

		const zones = [...qsa('#zone-container .zone-wrapper')];

		if(zones.length === 1) {
			return zones[0];
		}

		return qs('#zone-container');

	}

	static filterCountHere(matched) {

		const zone = this.filterZone();

		if(zone === null) {
			return 0;
		}

		const series = new Set();

		matched.forEach((ok, block) => {

			if(ok && block.dataset.series !== undefined && zone.contains(block)) {
				series.add(block.dataset.series);
			}

		});

		return series.size;

	}

	static filterScroll() {

		const zone = this.filterZone();

		if(zone === null) {
			return;
		}

		const results = [...zone.querySelectorAll('[data-search-plant]')]
			.filter(block => block.classList.contains('place-hidden') === false)
			.filter(block => block.classList.contains('place-filtered-out') === false);

		if(results.length === 0) {
			return;
		}

		const room = document.documentElement.clientHeight;

		const visible = results.some(block => {

			const rect = block.getBoundingClientRect();

			return (rect.height > 0 && rect.bottom > 0 && rect.top < room);

		});

		if(visible) {
			return;
		}

		results[0].scrollIntoView({block: 'center'});

	}

	static filterBlocks() {
		return [...qsa('#zone-container [data-search-plant]')].filter(block => block.classList.contains('place-hidden') === false);
	}

	static filterValuesOf(block, group) {

		return (block.dataset['search'+ group[0].toUpperCase() + group.slice(1)] ?? '')
			.split('|')
			.filter(value => value !== '')
			.map(value => UtilOmnibox.normalize(value));

	}

	static filterSuggestions() {

		const values = {};

		Object.keys(this.filterGroups).forEach(group => values[group] = new Set());

		this.filterBlocks().forEach(block => {
			Object.keys(values).forEach(group => {
				(block.dataset['search'+ group[0].toUpperCase() + group.slice(1)] ?? '').split('|').forEach(value => {
					value === '' || values[group].add(value);
				});
			});
		});

		const suggestions = [];

		Object.keys(values).forEach(group => {
			[...values[group]].sort((a, b) => a.localeCompare(b, 'fr')).forEach(value => {
				suggestions.push({group: this.filterGroups[group], label: value});
			});
		});

		return suggestions;

	}

	static filterCriteria(box) {

		const groups = {};
		const labels = this.filterGroups;

		Object.entries(UtilOmnibox.getTokenGroups(box)).forEach(([label, values]) => {

			const key = Object.keys(labels).find(one => labels[one] === label);

			if(key !== undefined) {
				groups[key] = values.map(value => UtilOmnibox.normalize(value));
			}

		});

		return groups;

	}

	static filterMatch(block, criteria) {
		return Object.keys(criteria).every(group => criteria[group].some(value => this.filterValuesOf(block, group).includes(value)));
	}

	static filterApply(box) {

		const criteria = this.filterCriteria(box);
		const filtering = (Object.keys(criteria).length > 0);
		const blocks = this.filterBlocks();

		const matched = new Map();
		const series = new Set();

		blocks.forEach(block => {

			const ok = (filtering === false) || this.filterMatch(block, criteria);

			matched.set(block, ok);

			if(ok && filtering && block.dataset.series !== undefined) {
				series.add(block.dataset.series);
			}

		});

		blocks.forEach(block => block.classList.toggle('place-filtered-out', filtering && matched.get(block) === false));

		qsa('.plot-wrapper.garden-plot', plot => {

			const inside = [...plot.querySelectorAll('[data-search-plant]')].filter(block => matched.get(block) === true);

			const accueille = (plot.querySelector('.place-pose') !== null);

			plot.classList.toggle('place-fold-search', filtering && inside.length === 0 && accueille === false);

		});

		const count = box.qs('.util-omnibox-count');

		if(count !== null) {

			const here = this.filterCountHere(matched);
			const total = series.size;

			const reste = (here === 0) ?
				(this.labels.filterNone ?? '') :
				(this.labels.filterHere ?? '').replace('{value}', here);

			count.textContent = filtering ?
				(total +' '+ count.dataset.label + ((here < total) ? ', '+ reste : '')) :
				'';

		}

		qs('.place-filter-empty', node => node.classList.toggle('hide', filtering === false || series.size > 0));

	}

	static folded = new Set();

	static unfolded = new Set();

	static foldPlot(target) {

		const plotWrapper = target.firstParent('.plot-wrapper');

		if(plotWrapper === null || plotWrapper.dataset.plot === undefined) {
			return;
		}

		this.folded.add(plotWrapper.dataset.plot);

		plotWrapper.classList.add('place-fold-user');

	}

	static unfoldPlot(target) {

		const plotWrapper = target.firstParent('.plot-wrapper');

		if(plotWrapper === null) {
			return;
		}

		plotWrapper.classList.remove('place-fold-user');
		plotWrapper.classList.remove('place-fold-search');

		if(plotWrapper.dataset.plot !== undefined) {
			this.folded.delete(plotWrapper.dataset.plot);
		}

	}

	static restoreFolds() {

		this.folded.forEach(plot => {
			qs('.plot-wrapper[data-plot="'+ plot +'"]', node => node.classList.add('place-fold-user'));
		});

	}

	static restoreUnfolded() {

		this.unfolded.forEach(bed => {
			qsa('.garden-col[data-bed="'+ bed +'"], .bed-item-grid[data-bed="'+ bed +'"]', node => node.classList.add('place-hidden-unfolded'));
		});

	}

	static clearUnfolded() {

		this.unfolded.clear();

	}

}
class SeriesDrag {

	static hasDates(card) {

		return (card?.dataset.start ?? '') !== '' && (card?.dataset.stop ?? '') !== '';

	}

	static current = null;
	static moved = false;
	static touchDelay = 200;
	static threshold = 4;

	static armed = 'move';

	static edge = 90;
	static speed = 40;
	static scroller = null;

	static pending = null;
	static hovered = null;

	static prepare(series, mode) {

		this.armed = mode;
		this.pending = {series: series, mode: mode};

		document.body.dataset.armed = mode;
		document.body.classList.add('place-arming');

	}

	static cancelPending() {

		this.pending = null;

		document.body.classList.remove('place-arming');

	}

	static applyPending(bed) {

		const pending = this.pending;

		if(pending === null || bed === undefined || bed === null) {
			return false;
		}

		const block = SeriesResize.getBlocks(pending.series)[0];

		if(block === undefined) {
			this.cancelPending();
			return false;
		}

		const drag = {
			source: 'plan',
			node: block,
			series: pending.series,
			from: block.closest('.garden-col, .bed-item-grid')?.dataset.bed,
			span: Math.max(1, parseInt(block.dataset.span || 1)),
			target: bed
		};

		if(drag.from === bed.dataset.bed) {
			return false;
		}

		this.cancelPending();

		if(pending.mode === 'add') {
			SeriesPlace.addBeds(pending.series, [bed.dataset.bed], 'add');
		} else if(pending.mode === 'copy') {
			this.copy(drag, bed.dataset.bed);
		} else {
			this.drop(drag, bed.dataset.bed);
		}

		return true;

	}

	static start(node, event, source) {

		if(event.button !== undefined && event.button !== 0) {
			return;
		}

		const touch = (event.pointerType !== undefined && event.pointerType !== 'mouse');

		this.current = {
			source: source,
			node: node,
			series: node.dataset.series || null,
			cultivation: node.dataset.cultivation || null,
			from: source === 'plan' ? node.firstParent('.garden-col, .bed-item-grid')?.dataset.bed : null,
			span: source === 'plan' ? Math.max(1, parseInt(node.dataset.span || 1)) : 1,
			grab: source === 'plan' ? this.getGrabOffset(node, event) : 0,
			label: source === 'plan' ? (node.qs('b, .place-grid-series-timeline-name')?.innerText ?? node.innerText.trim()) : node.qs('.series-selector-name')?.innerText,
			startX: event.clientX,
			startY: event.clientY,
			moved: false,
			ready: touch === false,
			ghost: null,
			target: null,
			mode: source === 'plan' ? 'move' : 'add',
			timer: null
		};

		if(touch) {
			this.current.timer = setTimeout(() => {
				if(this.current !== null) {
					this.current.ready = true;
				}
			}, this.touchDelay);
		}

	}

	static getGrabOffset(node, event) {

		const cell = node.closest('.garden-col, .bed-item-grid');

		if(cell === null) {
			return 0;
		}

		const beds = SeriesResize.getBeds(cell);
		const start = beds.indexOf(cell);

		if(start === -1) {
			return 0;
		}

		const rects = beds.map(bed => bed.getBoundingClientRect());
		const index = rects.findIndex(rect =>
			event.clientX >= rect.left && event.clientX < rect.right &&
			event.clientY >= rect.top && event.clientY < rect.bottom
		);

		if(index === -1) {
			return 0;
		}

		const span = Math.max(1, parseInt(node.dataset.span || 1));

		return Math.max(0, Math.min(index - start, span - 1));

	}

	static buildGhost(drag) {

		const ghost = document.createElement('div');

		ghost.className = 'bed-drag-ghost';

		if(drag.source === 'plan') {

			const rect = drag.node.getBoundingClientRect();
			const clone = drag.node.cloneNode(true);

			clone.qs('.place-handle', handle => handle.remove());
			clone.qs('.place-fill-pencil', node => node.remove());
			clone.qsa('.place-bed-part', part => part.remove());
			clone.classList.remove('place-selected');
			clone.qs('.place-selected-label', node => node.remove());
			clone.style.position = 'static';

			const width = rect.width;

			// Un bloc plus large que sa grille traine un fantome qui masque toutes les
			// cibles : il se plafonne a la grille, moins une marge pour voir dessous.
			const scroller = drag.node.closest('.garden-scroll');
			const frame = (scroller === null) ? width : Math.max(120, scroller.clientWidth - 48);

			const timeline = drag.node.classList.contains('place-grid-series-timeline');
			const shown = timeline ? Math.min(width, 240) : Math.min(width, frame);

			clone.style.width = Math.round(shown) +'px';
			clone.style.height = Math.round(rect.height) +'px';
			clone.style.maxHeight = Math.round(rect.height) +'px';
			clone.style.overflow = 'hidden';
			clone.style.left = 'auto';
			clone.style.right = 'auto';
			clone.style.top = 'auto';

			const summary = SeriesResize.getDragSummary(drag);

			if(summary !== '') {

				const label = document.createElement('span');

				label.className = 'bed-drag-ghost-label';
				label.innerHTML = summary;

				ghost.append(label);

			}

			ghost.append(clone);
			ghost.classList.add('bed-drag-ghost-card');

			if(timeline) {
				drag.offsetX = 16;
				drag.offsetY = Math.round(rect.height / 2);
			} else {
				drag.offsetX = Math.min(drag.startX - rect.left, shown - 24);
				drag.offsetY = drag.startY - rect.top;
			}

		} else {

			const rect = drag.node.getBoundingClientRect();
			const dot = drag.node.qs('.series-selector-dot');
			const name = drag.node.qs('.series-selector-name');

			const clone = document.createElement('div');

			clone.className = 'series-selector-header';

			if(dot !== null) {
				clone.append(dot.cloneNode(true));
			}

			clone.insertAdjacentHTML('beforeend', '<span class="series-selector-name">'+ (name?.textContent ?? '').trim() +'</span>');

			ghost.append(clone);
			ghost.classList.add('bed-drag-ghost-card', 'bed-drag-ghost-selector');

			const header = drag.node.qs('.series-selector-header');
			const top = (header === null) ? rect.top : header.getBoundingClientRect().top;

			drag.offsetX = 16;
			drag.offsetY = Math.max(0, Math.min(drag.startY - top, header === null ? rect.height : header.getBoundingClientRect().height));

		}

		return ghost;

	}

	// Les bords qui font defiler se mesurent a la taille du defileur : 90 px de
	// chaque cote d'une grille de 380 px, c'est la moitie de la grille qui defile
	// des qu'on y pose le doigt, a 40 px par image. Sur un telephone la zone se
	// reduit a un huitieme et la vitesse avec elle.
	static edgeFor(size) {
		return Math.min(this.edge, Math.round(size / 8));
	}

	static speedFor(size) {
		return Math.min(this.speed, Math.max(8, Math.round(size / 20)));
	}

	static startScroller() {

		if(this.scroller !== null) {
			return;
		}

		const tick = () => {

			const drag = this.current;

			if(drag === null || drag.moved === false) {
				this.scroller = null;
				return;
			}

			const y = drag.y ?? 0;
			const height = window.innerHeight;
			const edge = this.edgeFor(height);
			const speed = this.speedFor(height);

			let delta = 0;

			if(y < edge) {
				delta = -speed * (1 - y / edge);
			} else if(y > height - edge) {
				delta = speed * (1 - (height - y) / edge);
			}

			if(delta !== 0) {
				window.scrollBy(0, delta);
			}

			this.scrollAside(drag.x ?? 0, y);

			this.scroller = requestAnimationFrame(tick);

		};

		this.scroller = requestAnimationFrame(tick);

	}

	static scrollAside(x, y) {

		const node = document.elementFromPoint(x, y);

		if(node === null) {
			return;
		}

		const scroller = node.closest('.garden-scroll');

		if(scroller === null || scroller.scrollWidth <= scroller.clientWidth + 2) {
			return;
		}

		const box = scroller.getBoundingClientRect();
		const edge = this.edgeFor(box.width);
		const speed = this.speedFor(box.width);

		let delta = 0;

		if(x < box.left + edge) {
			delta = -speed * (1 - Math.max(0, x - box.left) / edge);
		} else if(x > box.right - edge) {
			delta = speed * (1 - Math.max(0, box.right - x) / edge);
		}

		if(delta !== 0) {
			scroller.scrollLeft += delta;
			SeriesScroll.remember(scroller);
		}

	}

	static stopScroller() {

		if(this.scroller !== null) {
			cancelAnimationFrame(this.scroller);
			this.scroller = null;
		}

	}

	static move(event) {

		const drag = this.current;

		if(drag === null || drag.ready === false) {
			return;
		}

		drag.x = event.clientX;
		drag.y = event.clientY;

		if(drag.moved === false) {

			if(
				Math.abs(event.clientX - drag.startX) < this.threshold &&
				Math.abs(event.clientY - drag.startY) < this.threshold
			) {
				return;
			}

			drag.moved = true;
			this.moved = true;
			document.body.classList.add('bed-dragging');

			if(drag.source === 'plan') {
				qsa('[data-series="'+ drag.series +'"]', block => block.classList.add('place-dragged'));
			}

			drag.ghost = this.buildGhost(drag);
			document.body.append(drag.ghost);

			this.startScroller();

			drag.badge = this.buildBadge(drag);

			if(drag.badge !== null) {
				document.body.append(drag.badge);
			}

		}

		drag.ghost.style.left = (event.clientX - (drag.offsetX ?? 0)) + 'px';
		drag.ghost.style.top = (event.clientY - (drag.offsetY ?? 0)) + 'px';

		if(drag.badge !== null) {
			drag.badge.style.left = event.clientX +'px';
			drag.badge.style.top = (event.clientY - (drag.offsetY ?? 0) - 12) +'px';
		}

		const element = document.elementFromPoint(event.clientX, event.clientY);
		const bed = element === null ? null : element.closest('.garden-col, .bed-item-grid');

		if(bed !== drag.target) {

			this.hidePreview();

			if(bed !== null && bed.dataset.bed !== drag.from && SeriesResize.isFillBed(bed) === false) {

				drag.target = bed;

				this.showPreview(drag, bed);

			} else {
				drag.target = null;
			}

		}

		event.preventDefault();

	}

	static buildBadge(drag) {

		if(drag.mode === 'move') {
			return null;
		}

		const labels = window.seriesPlaceLabels ?? {};

		const badge = document.createElement('div');

		badge.className = 'place-drag-badge place-drag-badge-'+ drag.mode;

		if(drag.mode === 'copy') {
			badge.innerHTML = (labels.armedCopy ?? 'Copier') +' <b>+ '+ this.getBedsFrom(drag).length +' '+ (labels.bedUnit ?? 'pl.') +'</b>';
		} else {
			badge.innerHTML = labels.place ?? 'Poser';
		}

		return badge;

	}

	static showPreview(drag, bed) {

		this.hidePreview();

		const beds = (drag.source === 'plan') ?
			this.getBedsTo(drag, bed.dataset.bed, this.getBedsFrom(drag).length) :
			[bed.dataset.bed];

		const targets = SeriesResize.getBeds(bed);

		for(const bedId of beds) {

			const target = targets.find(node => node.dataset.bed === bedId);

			if(target !== undefined) {
				this.putPreview(drag, target);
			}

		}

	}

	static putPreview(drag, bed) {

		const garden = bed.classList.contains('garden-col');
		const body = garden ? bed.qs('.garden-col-body') : bed.qs('.bed-item-places');

		if(body === null) {
			return;
		}

		const bounds = (garden === false && drag.source === 'plan') ?
			this.getPreviewBoundsTimeline(drag, body) :
			this.getPreviewBounds(drag, garden);

		if(bounds === null) {
			return;
		}

		const preview = document.createElement('div');

		preview.className = 'place-preview '+ (garden ? 'place-preview-garden' : 'place-preview-timeline');
		preview.style.cssText = bounds;

		body.append(preview);

	}

	static hidePreview() {
		qsa('.place-preview', node => node.remove());
	}

	// En chronologie un bloc tient sa position horizontale d'une regle CSS ecrite a
	// son identifiant, jamais de son style en ligne : le relire la rendait vide et
	// l'apercu se reduisait a quelques pixels. On mesure le bloc traine et on le
	// reporte sur la piste de la ligne visee.
	static getPreviewBoundsTimeline(drag, body) {

		const box = drag.node.getBoundingClientRect();
		const rail = body.getBoundingClientRect();

		if(box.width === 0 || rail.width === 0) {
			return null;
		}

		return 'left: '+ (box.left - rail.left).toFixed(1) +'px; width: '+ box.width.toFixed(1) +'px; right: auto;';

	}

	static getPreviewBounds(drag, garden) {

		if(drag.source === 'plan') {

			const style = drag.node.style;

			if(style.top === '' && style.left === '') {
				return garden ?
					'top: auto; bottom: 2px; height: 1.5rem;' :
					'left: auto; right: 2px; width: 6rem;';
			}

			return garden ?
				'top: '+ style.top +'; height: '+ style.height +';' :
				'left: '+ style.left +'; width: '+ style.width +';';

		}

		const container = qs('#zone-content-garden') ?? qs('#zone-content');

		if(container === null) {
			return null;
		}

		const start = parseInt(container.dataset.start);
		const stop = parseInt(container.dataset.stop);

		const from = parseInt(drag.node.dataset.start);
		const to = parseInt(drag.node.dataset.stop);

		if(isNaN(from) || isNaN(to) || stop <= start) {
			return null;
		}

		const top = Math.max(0, (from - start) / (stop - start) * 100);
		const bottom = Math.min(100, (to - start) / (stop - start) * 100);

		if(bottom <= top) {
			return null;
		}

		return garden ?
			'top: '+ top +'%; height: '+ (bottom - top) +'%;' :
			'left: '+ top +'%; width: '+ (bottom - top) +'%;';

	}

	static end() {

		const drag = this.current;

		this.current = null;

		if(drag === null) {
			return;
		}

		this.stopScroller();

		if(drag.timer !== null) {
			clearTimeout(drag.timer);
		}

		drag.ghost?.remove();
		drag.badge?.remove();
		this.hidePreview();
		document.body.classList.remove('bed-dragging');
		qsa('.place-dragged', block => block.classList.remove('place-dragged'));

		setTimeout(() => this.moved = false, 0);

		if(drag.moved === false || drag.target === null) {
			return;
		}

		const to = drag.target.dataset.bed;

		if(drag.source === 'plan') {

			if(SeriesResize.selection.length > 1 && SeriesResize.selection.includes(drag.series)) {
				this.dropMany(drag, to);
			} else if(drag.mode === 'copy') {
				this.copy(drag, to);
			} else {
				this.drop(drag, to);
			}

		} else {
			this.dropFromSelector(drag, drag.target);
		}

	}

	static dropMany(drag, to) {

		const labels = window.seriesPlaceLabels ?? {};

		const source = SeriesResize.getBeds(drag.node.closest('.garden-col, .bed-item-grid'));
		const targets = SeriesResize.getBeds(drag.target);

		const grabbed = source.findIndex(bed => bed.dataset.bed === drag.from);
		const dropped = targets.findIndex(bed => bed.dataset.bed === to);

		if(grabbed === -1 || dropped === -1) {
			return;
		}

		const moves = [];

		for(const series of SeriesResize.selection) {

			const from = [];
			const into = [];

			for(const bed of this.getSeriesBeds(series)) {

				const index = source.findIndex(node => node.dataset.bed === bed);

				if(index === -1) {
					continue;
				}

				const target = targets[dropped + index - grabbed];

				if(target === undefined) {
					Lime.Alert.showStaticError(labels.manyOutOfPlot);
					return;
				}

				from.push(bed);
				into.push(target.dataset.bed);

			}

			if(from.length > 0) {
				moves.push({series: series, from: from, to: into});
			}

		}

		if(moves.length === 0) {
			Lime.Alert.showStaticError(labels.manyOutOfPlot);
			return;
		}

		moves.forEach(move => SeriesUngroup.forget(move.series, [move.from, move.to]));

		SeriesPlace.send('doMoveMany', {series: SeriesPlace.series ?? drag.series, moves: JSON.stringify(moves)}, 'move');

	}

	static drop(drag, to) {

		const moved = this.getBedsFrom(drag);
		const targets = this.getBedsTo(drag, to, moved.length);

		if(targets.length === 0) {
			return;
		}

		const envoi = (size) => {

			SeriesUngroup.forget(drag.series, moved.concat(targets));

			this.commitMove(drag, to);

			SeriesPlace.send('doMove', {
				series: drag.series,
				from: moved,
				to: targets,
				...(size === undefined ? {} : {size: size})
			}, 'move');

		};

		const source = parseInt(drag.node.dataset.length || 0);

		if(SeriesSize.needed(drag.target, targets, source)) {
			SeriesSize.ask(drag.target, source, envoi);
			return;
		}

		envoi();

	}

	// Le bloc part tout de suite dans sa colonne d'arrivee ; la reponse du serveur
	// redessine le plan et corrige au besoin.
	static commitMove(drag, to) {

		const block = drag.node;
		const cell = drag.target?.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

		if(block === undefined || cell === null || cell === undefined) {
			return;
		}

		const beds = SeriesResize.getBeds(cell);
		const start = beds.indexOf(cell);
		const anchor = (start === -1) ? cell : (beds[start - (drag.grab ?? 0)] ?? cell);

		if(anchor.dataset.bed === drag.from) {
			return;
		}

		SeriesResize.host(anchor)?.append(block);

		block.dataset.bed = anchor.dataset.bed;

		qsa('.place-selected-label', node => node.remove());

	}

	static getBedsFrom(drag) {

		const beds = SeriesResize.getBeds(drag.node.closest('.garden-col, .bed-item-grid'));
		const start = beds.findIndex(bed => bed.dataset.bed === drag.from);

		if(start === -1) {
			return [drag.from];
		}

		return beds.slice(start, start + drag.span).map(bed => bed.dataset.bed);

	}

	static getBedsTo(drag, to, count) {

		// La surface libre est ecartee des destinations : sans cela un groupe cale
		// contre elle voyait sa tranche l'inclure, et le serveur en posait la
		// moitie dessus — le groupe se cassait en deux.
		const beds = SeriesResize.getBeds(drag.target).filter(bed => SeriesResize.isFillBed(bed) === false);
		const under = beds.findIndex(bed => bed.dataset.bed === to);

		if(under === -1) {
			return [to];
		}

		const start = Math.max(0, Math.min(under - (drag.grab ?? 0), beds.length - count));

		return beds.slice(start, start + count).map(bed => bed.dataset.bed);

	}

	static getSeriesBeds(series) {

		const beds = [];

		qsa('[data-series="'+ series +'"]', block => {

			const bed = block.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

			if(bed === null) {
				return;
			}

			const all = SeriesResize.getBeds(bed);
			const start = all.indexOf(bed);
			const span = Math.max(1, parseInt(block.dataset.span || 1));

			all.slice(start, start + span).forEach(node => {
				if(beds.includes(node.dataset.bed) === false) {
					beds.push(node.dataset.bed);
				}
			});

		});

		return beds;

	}

	static copy(drag, to) {

		const targets = this.getBedsTo(drag, to, this.getBedsFrom(drag).length);

		const envoi = (size) => SeriesPlace.addBeds(drag.series, targets, 'copy', size);

		const source = parseInt(drag.node.dataset.length || 0);

		if(SeriesSize.needed(drag.target, targets, source)) {
			SeriesSize.ask(drag.target, source, envoi);
			return;
		}

		envoi(source > 0 ? source : undefined);

	}

	static dropFromSelector(drag, target) {

		const envoi = (size) => SeriesPlace.addBeds(drag.series, [target.dataset.bed], 'add', size);

		if(target.dataset.fill === '1') {
			SeriesSize.ask(target, parseInt(target.dataset.area || 0), envoi);
			return;
		}

		envoi();

	}

}

class SeriesResize {

	static current = null;
	static moved = false;
	static series = null;
	static bed = null;

	// La ou un bloc vit dans une planche, selon la vue.
	static host(cell) {
		return cell.qs('.garden-col-body, .bed-item-places');
	}

	static selection = [];

	static toggle(node) {

		const series = node.dataset.series;
		const index = this.selection.indexOf(series);

		if(index === -1) {
			this.selection.push(series);
		} else {
			this.selection.splice(index, 1);
		}

		SeriesPanel.close();

		this.paintSelection();

	}

	static getBlockBelow(event, block) {

		const blocks = document.elementsFromPoint(event.clientX, event.clientY)
			.filter(node => node.matches('.place-garden-block-season, .place-grid-series-timeline-season'));

		const index = blocks.indexOf(block);

		return blocks[index + 1] ?? null;

	}

	static paintSelection() {

		qsa('.place-selected-many', block => block.classList.remove('place-selected-many'));

		if(this.selection.length < 2) {
			qs('#place-many', node => node.remove());
			return;
		}

		for(const series of this.selection) {
			this.getBlocks(series).forEach(block => block.classList.add('place-selected-many'));
		}

		const labels = window.seriesPlaceLabels ?? {};

		let node = qs('#place-many');

		if(node === null) {
			node = document.createElement('div');
			node.id = 'place-many';
			node.className = 'place-many';
			placeOverlay().append(node);
		}

		node.innerHTML = '<span>'+ this.selection.length +' '+ (labels.manySelected ?? 'séries retenues') +'</span>'
			+ '<a onclick="SeriesResize.clearSelection()">'+ (labels.deselect ?? 'Tout désélectionner') +'</a>';

	}

	static clearSelection() {

		this.selection = [];
		this.paintSelection();
		this.clear();

		document.body.classList.remove('place-selecting');
		qsa('.place-selected-label', node => node.remove());
		qsa('.place-nodate-line.selected', node => node.classList.remove('selected'));

		SeriesPanel.series = null;
		SeriesPanel.bed = null;

		SeriesDrag.cancelPending();

	}

	static select(node, event = null) {

		const series = node.dataset.series;
		const span = parseInt(node.dataset.span || 1);

		const blocks = this.getBlocks(series);

		this.clear();

		this.series = series;

		this.selection = [series];
		this.paintSelection();

		const locked = (node.dataset.locked === '1');

		const selected = blocks.includes(node) ? [node] : blocks;

		qsa('.place-selected-label', node => node.remove());
		qsa('.place-selected-series', block => block.classList.remove('place-selected-series'));

		blocks.forEach(block => block.classList.add('place-selected-series'));

		document.body.classList.add('place-selecting');

		const chips = [];

		selected.forEach(block => {

			block.classList.add('place-selected');

			if(locked) {
				return;
			}

			if(this.canResize(block, -1)) {
				block.insertAdjacentHTML('beforeend', '<i class="place-handle place-handle-start"></i>');
			}

			if(this.canResize(block, 1)) {
				block.insertAdjacentHTML('beforeend', '<i class="place-handle"></i>');
			}

			SeriesChip.unequip(block);

			if(block.dataset.bedLength > 0 && parseInt(block.dataset.span || 1) === 1) {
				chips.push(block);
			}

		});

		const boxes = chips.map(block => block.getBoundingClientRect());

		chips.forEach((block, index) => SeriesChip.equip(block, boxes[index]));

		if(locked === false) {

			placePillsPaint(node);

			const summary = node.classList.contains('place-grid-series-timeline') ?
				'' :
				this.getSeriesSummary(node.dataset.series, node);

			if(summary !== '') {

				const label = document.createElement('span');

				label.className = 'place-selected-label';
				label.innerHTML = summary;

				const pill = this.getGroupPill(node, span);

				if(pill !== null) {
					label.append(pill);
				}

				const drop = this.getDropPill(node);

				if(drop !== null) {
					label.append(drop);
				}

				const host = node.parentElement;

				if(host === null) {
					node.append(label);
				} else {

					host.append(label);

					label.style.left = node.offsetLeft +'px';
					label.style.top = Math.max(0, node.offsetTop - label.offsetHeight - 4) +'px';

					this.clampLabel(label, node);
					this.pushPills(label, node);

				}

			}

		}

		this.updateJumpers();

	}

	static keep = null;

	// Ce qu'il faut retenir d'une planche avant que le serveur redessine le plan :
	// laquelle, et ou elle se trouvait dans la fenetre. Le mode placement replie
	// des emplacements et raccourcit la page ; sans ce repere le navigateur rabat
	// le defilement sur la nouvelle hauteur et la vue saute.
	static getKeep(block) {

		if(block === null || block === undefined) {
			return null;
		}

		return {
			series: block.dataset.series,
			bed: block.dataset.bed,
			plot: block.closest('.plot-wrapper')?.id ?? '',
			top: block.getBoundingClientRect().top,
		};

	}

	static restoreSelection(series) {

		SeriesPanel.close();

		const keep = this.keep;

		this.keep = null;

		if(keep === null) {
			return;
		}

		// Un bloc qui s'etire vers la gauche change de planche d'ancrage : celle
		// qu'on a retenue ne l'heberge plus, il se retrouve par les planches qu'il
		// couvre, sinon par son premier bloc.
		// Et jamais un bloc d'une autre parcelle : la serie y est peut-etre aussi,
		// la retenir la-bas faisait sauter la page jusqu'a lui.
		const blocks = [...qsa('.garden-col [data-series="'+ keep.series +'"], .bed-item-grid [data-series="'+ keep.series +'"]')]
			.filter(node => node.matches('.place-garden-block-season, .place-grid-series-timeline-season'))
			.filter(node => (node.closest('.plot-wrapper')?.id ?? '') === keep.plot);

		const block = blocks.find(node => node.closest('.garden-col, .bed-item-grid')?.dataset.bed === keep.bed)
			?? blocks.find(node => {
				const cell = node.closest('.garden-col, .bed-item-grid');
				const beds = this.getBeds(cell);
				const start = beds.indexOf(cell);
				const span = Math.max(1, parseInt(node.dataset.span || 1));
				return beds.slice(start, start + span).some(one => one.dataset.bed === keep.bed);
			})
			?? blocks[0]
			?? null;

		if(block === null) {
			return;
		}

		this.select(block);

		const shift = block.getBoundingClientRect().top - keep.top;

		if(Math.abs(shift) > 1) {
			window.scrollBy({top: shift, behavior: 'instant'});
		}

		this.revealAside(block);

	}

	// Le defilement horizontal revient a sa valeur d'avant l'action, qui n'est plus
	// forcement celle du bloc : apres un geste, la planche retenue doit rester sous
	// les yeux. Un bloc plus large que la grille se cale sur son debut. Rejoue a
	// l'image suivante comme SeriesScroll.restore(), qui sinon reprend la main.
	static revealAside(block) {

		const scroller = block.closest('.garden-scroll');

		if(scroller === null) {
			return;
		}

		const align = () => {

			const box = block.getBoundingClientRect();
			const frame = scroller.getBoundingClientRect();
			const margin = 8;

			let delta = 0;

			if(box.left < frame.left + margin || box.width > frame.width - 2 * margin) {
				delta = box.left - frame.left - margin;
			} else if(box.right > frame.right - margin) {
				delta = box.right - frame.right + margin;
			}

			if(Math.abs(delta) > 1) {
				scroller.scrollLeft += delta;
				SeriesScroll.remember(scroller);
			}

		};

		align();

		requestAnimationFrame(align);

	}

	static canResize(block, direction) {

		// Pas de poignée en chronologie : la largeur d'un bloc y dit le temps, pas
		// le nombre de planches, et l'étirer n'a aucun sens visible.
		if(block.classList.contains('place-grid-series-timeline')) {
			return false;
		}

		const cell = block.closest('.garden-col, .bed-item-grid');

		if(cell !== null && this.isFillBed(cell)) {
			return false;
		}

		return this.canExtend(block, direction) || Math.max(1, parseInt(block.dataset.span || 1)) > 1;

	}

	static canExtend(block, direction) {

		const cell = block.closest('.garden-col, .bed-item-grid');

		if(cell === null) {
			return false;
		}

		const beds = this.getBeds(cell);
		const start = beds.indexOf(cell);

		if(start === -1) {
			return false;
		}

		const span = Math.max(1, parseInt(block.dataset.span || 1));
		const index = (direction < 0) ? (start - 1) : (start + span);
		const neighbour = beds[index];

		if(neighbour === undefined || this.isFillBed(neighbour) || this.isHiddenBed(neighbour)) {
			return false;
		}

		return neighbour.querySelector('[data-series="'+ block.dataset.series +'"]:not(.place-nodate-line)') === null;

	}

	// Une planche que le filtre a repliée n'est pas une destination : elle n'est
	// plus lisible, et l'y étendre poserait la série là où on ne la voit pas.
	static isHiddenBed(cell) {
		return cell.dataset.hide === '1';
	}

	static isFillBed(cell) {
		return cell.classList.contains('garden-col-fill') || cell.classList.contains('bed-item-fill');
	}

	// La corbeille vit dans l'etiquette, pas en pastille flottante : posee sur le
	// bloc, elle dependait du survol et disparaissait sans qu'on sache d'ou.
	static getDropPill(node) {

		if(
			node.dataset.locked === '1' ||
			node.dataset.bed === undefined ||
			node.closest('.garden-col') === null
		) {
			return null;
		}

		const labels = SeriesPanel.labels;
		const span = Math.max(1, parseInt(node.dataset.span || 1));

		const pill = document.createElement('a');

		pill.className = 'place-selected-pill place-selected-drop';
		pill.title = (span > 1) ?
			(labels.removeBedMany ?? '').replace('{value}', span) :
			(labels.removeBed ?? '');

		// Le garde du framework : delegue en capture, il arrete l'evenement avant
		// notre ecouteur quand l'utilisateur refuse.
		pill.dataset.confirm = (span > 1) ?
			(labels.removeBedConfirmMany ?? '').replace('{value}', span) :
			(labels.removeBedConfirm ?? '');

		pill.innerHTML = Lime.Asset.icon('trash');

		pill.addEventListener('pointerdown', event => {
			event.preventDefault();
			event.stopPropagation();
		});

		pill.addEventListener('click', event => {

			event.preventDefault();
			event.stopPropagation();

			SeriesPanel.series = node.dataset.series;
			SeriesPanel.removeBed(node.dataset.bed, node);

		});

		return pill;

	}

	static getGroupPill(node, span) {

		if(node.closest('.garden-col') === null) {
			return null;
		}

		const grouped = (span > 1);
		const ungrouped = SeriesUngroup.has(node.dataset.series, node.dataset.bed);

		if(grouped === false && ungrouped === false) {
			return null;
		}

		const labels = SeriesPanel.labels;
		const pill = document.createElement('a');

		pill.className = 'place-selected-pill';
		pill.textContent = grouped ? (labels.ungroup ?? 'Dissocier') : (labels.group ?? 'Regrouper');

		const stop = event => {
			event.preventDefault();
			event.stopPropagation();
		};

		pill.addEventListener('pointerdown', stop);

		pill.addEventListener('click', event => {

			stop(event);

			if(grouped) {
				SeriesUngroup.split(node);
			} else {
				SeriesUngroup.merge(node);
			}

		});

		return pill;

	}

	static pushPills(label, block) {

		const pills = [...document.querySelectorAll('.place-back, .place-drop')].filter(pill => pill.placeBlock === block);

		if(pills.length === 0) {
			return;
		}

		const lefts = pills.map(pill => pill.offsetLeft);
		const shift = label.offsetLeft + label.offsetWidth + 6 - Math.min(...lefts);

		if(shift > 0) {
			pills.forEach((pill, index) => pill.style.left = (lefts[index] + shift) +'px');
		}

		const overflow = this.getOverflow(block, label, Math.max(...pills.map(pill => pill.getBoundingClientRect().right)));

		if(overflow > 0) {
			label.style.left = (parseFloat(label.style.left) - overflow) +'px';
			pills.forEach(pill => pill.style.left = (pill.offsetLeft - overflow) +'px');
		}

	}

	static clampLabel(label, block) {

		const overflow = this.getOverflow(block, label, label.getBoundingClientRect().right);

		if(overflow > 0) {
			label.style.left = (parseFloat(label.style.left) - overflow) +'px';
		}

	}

	// Ce qui depasse a droite du bord visible, borne par la place laissee a gauche
	// de l'etiquette. Borne au bord visible, pas a scrollWidth : l'etiquette, deja
	// posee dans la zone de defilement, l'elargit elle-meme et ne deborderait jamais.
	static getOverflow(block, label, right) {

		const scroll = block.closest('.garden-scroll');

		if(scroll === null) {
			return 0;
		}

		const box = scroll.getBoundingClientRect();

		return Math.min(right - (box.left + scroll.clientWidth), label.getBoundingClientRect().left - box.left);

	}

	static markParts(node, span) {

		node.qsa('.place-bed-part', part => part.remove());

		const vertical = (node.classList.contains('place-grid-series-timeline') === true);
		const size = (100 / span);

		for(let index = 0; index < span; index++) {

			const style = vertical ?
				'top: '+ (index * size) +'%; height: '+ size +'%; left: 0; right: 0;' :
				'left: '+ (index * size) +'%; width: '+ size +'%; top: 0; bottom: 0;';

			node.insertAdjacentHTML('beforeend', '<i class="place-bed-part" style="'+ style +'"></i>');

		}

	}

	static getJumperSeries() {
		return this.series ?? SeriesPlace.series;
	}

	static getJumperPlant() {

		if(SeriesPlace.tokenPlant !== null) {
			return SeriesPlace.tokenPlant;
		}

		const box = qs('#place-filter');

		if(box === null) {
			return null;
		}

		const plants = SeriesPlace.filterCriteria(box).plant ?? [];

		return (plants.length === 1) ? plants[0] : null;

	}

	static getJumperBlocks() {

		const series = this.getJumperSeries();

		if(series !== null) {
			return this.getBlocks(series);
		}

		const plant = this.getJumperPlant();

		if(plant === null) {
			return [];
		}

		return [...document.querySelectorAll('#zone-container [data-search-plant]')]
			.filter(block =>
				UtilOmnibox.normalize(block.dataset.searchPlant ?? '') === plant &&
				block.closest('.garden-col, .bed-item-grid') !== null &&
				block.classList.contains('place-hidden') === false
			);

	}

	static getJumperLabel() {

		const series = this.getJumperSeries();

		if(series !== null) {
			return this.getJumperName(series);
		}

		return this.getJumperBlocks()[0]?.dataset.searchPlant ?? '';

	}

	static getJumperTint() {

		const series = this.getJumperSeries();

		if(series !== null) {
			return this.getJumperColor(series);
		}

		const block = this.getJumperBlocks()[0];

		return (block === undefined) ? '' : (block.style.backgroundColor || '');

	}

	static observer = null;

	static updateJumpers() {

		this.observer?.disconnect();
		this.observer = null;

		const series = this.getJumperSeries();
		const blocks = (series === null) ? [] : this.getBlocks(series);

		if(blocks.length > 0) {

			this.observer = new IntersectionObserver(() => this.paintJumpers(), {threshold: 0});

			blocks.forEach(block => this.observer.observe(block));

		}

		this.paintJumpers();

	}

	// Le premier calcul des sauts force la mise en page du plan qui vient de changer :
	// le faire apres la premiere image laisse le navigateur peindre d'abord.
	static updateJumpersAfterPaint() {
		requestAnimationFrame(() => setTimeout(() => this.updateJumpers(), 0));
	}

	static getJumperDirection(block) {

		const rect = block.getBoundingClientRect();

		if(rect.bottom < 0) {
			return 'up';
		}

		if(rect.top > window.innerHeight) {
			return 'down';
		}

		const scroll = block.closest('.garden-scroll');
		const box = (scroll === null) ?
			{left: 0, right: window.innerWidth} :
			scroll.getBoundingClientRect();

		if(rect.right < box.left) {
			return 'left';
		}

		if(rect.left > box.right) {
			return 'right';
		}

		return null;

	}

	static getJumperCounts() {

		const blocks = this.getJumperBlocks();

		if(blocks.length === 0) {
			return null;
		}

		const counts = {up: 0, down: 0, left: 0, right: 0};

		blocks.forEach(block => {

			const direction = this.getJumperDirection(block);

			if(direction !== null) {
				counts[direction]++;
			}

		});

		return counts;

	}

	static getJumperColor(series) {

		const block = qs('.place-garden-block-season[data-series="'+ series +'"], .place-grid-series-timeline-season[data-series="'+ series +'"]');
		const background = (block === null) ? '' : block.style.backgroundColor;

		if(background !== '') {
			return background;
		}

		const dot = qs('#series-selector-list [data-series="'+ series +'"] .series-selector-dot');

		return (dot === null) ? '#3d3d3d' : dot.style.backgroundColor;

	}

	static getJumperName(series) {

		const context = qs('#place-context');

		if(context !== null && context.dataset.series === series) {
			return context.dataset.name ?? '';
		}

		const name = qs('#series-selector-list [data-series="'+ series +'"] .series-selector-name');

		return (name === null) ? '' : name.innerText.trim();

	}

	static paintJumpers() {

		const counts = this.getJumperCounts();

		const directions = (counts === null) ? [] : [
			['up', '↑'],
			['down', '↓'],
			['left', '←'],
			['right', '→'],
		].filter(([key]) => counts[key] > 0);

		const etiquette = this.getJumperLabel();
		const signature = etiquette +'|'+ directions.map(([key]) => key +':'+ counts[key]).join(',');
		const existing = qs('#place-jumpers');

		if(existing !== null && existing.placeSignature === signature) {
			return;
		}

		if(existing !== null) {
			existing.remove();
		}

		if(directions.length === 0) {
			return;
		}

		const node = document.createElement('div');

		node.id = 'place-jumpers';
		node.className = 'place-jump';

		node.innerHTML =
			'<span class="place-jump-dot" style="background-color: '+ this.getJumperTint() +'"></span>'
			+ '<span class="place-jump-name">'+ etiquette +'</span>'
			+ directions.map(([key, arrow]) =>
				'<a class="place-jump-count" onclick="SeriesResize.jump(\''+ key +'\')">'+ arrow +' '+ counts[key] +'</a>'
			).join('');

		node.title = etiquette;
		node.placeSignature = signature;

		placeOverlay().append(node);

	}

	static jump(direction) {

		const blocks = this.getJumperBlocks();

		const outside = blocks.filter(block => this.getJumperDirection(block) === direction);

		if(outside.length === 0) {
			return;
		}

		outside.sort((a, b) => {
			const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
			return (direction === 'up' || direction === 'down') ? (ra.top - rb.top) : (ra.left - rb.left);
		});

		const target = (direction === 'up' || direction === 'left') ? outside[outside.length - 1] : outside[0];

		target.scrollIntoView({block: 'center', inline: 'center'});

		setTimeout(() => this.paintJumpers(), 600);

	}

	static paintTarget() {

		const container = qs('#zone-container');

		if(container === null) {
			return;
		}

		// Le plan de l'assolement arme depuis le bac, sans passer par select() : sans
		// ce marquage ses blocs n'etaient distingues par aucune classe, et l'effacement
		// des autres series s'appliquait aussi a celle qu'on assole.
		const armed = SeriesPlace.series;

		qsa('.place-selected-series', block => block.classList.remove('place-selected-series'));

		if(armed !== null) {
			qsa('[data-series="'+ armed +'"]', block => block.classList.add('place-selected-series'));
		}

		SeriesPlace.repaintAfterRender();
		SeriesPlace.paintTokens();
		SeriesPlace.paintPoseBar();

		const card = qs('#series-selector-list .series-selector-cultivation.selected');

		SeriesPlacement.setPaint(container, card?.style.getPropertyValue('--series-paint-shadow') ?? null);

	}

	static getDragSummary(drag) {

		const labels = window.seriesPlaceLabels ?? {};
		const ids = SeriesDrag.getBedsFrom(drag).map(String);

		const blocks = this.getBlocks(drag.series).filter(block => {

			const cell = block.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

			return (cell !== null && ids.includes(cell.dataset.bed));

		});

		let length = 0;

		blocks.forEach(block => {
			length += parseInt(block.dataset.length || 0);
		});

		const beds = Math.max(1, ids.length);
		const parts = [((beds > 1) ? (labels.bedMany ?? '{value} planches') : (labels.bedOne ?? '{value} planche')).replace('{value}', beds)];

		if(length > 0) {
			parts.push(length +' '+ (labels.lengthMl ?? 'mL'));
		}

		return parts.join(' · ');

	}

	static getBedSummary(block) {

		const labels = window.seriesPlaceLabels ?? {};
		const length = parseInt(block.dataset.length || 0);
		const parts = [(labels.bedOne ?? '{value} planche').replace('{value}', 1)];

		if(length > 0) {
			parts.push(length +' '+ (labels.lengthMl ?? 'mL'));
		}

		return parts.join(' · ');

	}

	static getSeriesSummary(series, block = null) {

		const labels = window.seriesPlaceLabels ?? {};
		const card = qs('#series-selector-list .series-selector-cultivation[data-series="'+ series +'"]');

		let beds = 0;
		let posed = 0;

		if(block === null) {

			this.getBlocks(series).forEach(node => {
				beds += parseInt(node.dataset.span || 1);
			});

			posed = parseInt(card?.qs('.series-selector-value')?.innerText || 0);

		} else {

			beds = Math.max(1, parseInt(block.dataset.span || 1));
			posed = parseInt(block.dataset.blockLength ?? block.dataset.length ?? 0) || 0;

		}

		const unit = card?.qs('.series-selector-place')?.innerText.trim().split(' ').pop() ?? (labels.lengthMl ?? 'mL');

		const parts = [];

		if(beds > 0) {
			parts.push(((beds > 1) ? (labels.bedMany ?? '{value} planches') : (labels.bedOne ?? '{value} planche')).replace('{value}', beds));
		}

		if(posed > 0) {
			parts.push(posed +' '+ unit);
		}

		return parts.join(' · ');

	}

	// Un bloc de plusieurs planches ne vit que dans la cellule de la premiere : se
	// fier a la presence du bloc laissait les suivantes pour vides.
	static getSeriesBeds(series) {

		const covered = new Set();

		this.getBlocks(series).forEach(block => {

			const cell = block.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

			if(cell === null) {
				return;
			}

			covered.add(cell.dataset.bed);

			const span = Math.max(1, parseInt(block.dataset.span || 1));

			if(span === 1) {
				return;
			}

			const beds = this.getBeds(cell);
			const start = beds.indexOf(cell);

			if(start === -1) {
				return;
			}

			beds.slice(start, start + span).forEach(node => covered.add(node.dataset.bed));

		});

		return covered;

	}

	static getBlocks(series) {

		return [...document.querySelectorAll('[data-series="'+ series +'"]')]
			.filter(block =>
				block.closest('.garden-col, .bed-item-grid') !== null &&
				block.classList.contains('place-nodate-line') === false
			);

	}

	static clear() {

		this.series = null;
		this.bed = null;

		this.observer?.disconnect();
		this.observer = null;

		qs('#place-jumpers', node => node.remove());

		// Le reglage de longueur parle de la planche retenue : il tombe avec elle.
		SeriesSize.close();

		qsa('.place-selected-series', block => block.classList.remove('place-selected-series'));

		qsa('.place-selected', block => {

			block.classList.remove('place-selected');
			block.qsa('.place-handle', handle => handle.remove());
			SeriesChip.unequip(block);

			if(block === placeHover) {
				placePillsPaint(block);
			} else {
				placePillsDrop(block);
			}

		});

	}

	static restoreBlock(resize) {

		resize.block.style.width = resize.width;
		resize.block.style.right = resize.right;
		resize.block.style.left = resize.styleLeft;

	}

	static restoreParts(resize) {

		if(resize.drawn === undefined) {
			return;
		}

		if(resize.span > 1) {
			this.markParts(resize.block, resize.span);
		} else {
			resize.block.qsa('.place-bed-part', part => part.remove());
		}

	}

	static getBeds(node) {

		const garden = node.closest('.garden-col');

		if(garden !== null) {
			return [...garden.parentElement.querySelectorAll('.garden-col[data-bed]')];
		}

		return [...node.closest('.plot-wrapper').querySelectorAll('.bed-item-grid[data-bed]')];

	}

	static start(node, event) {

		const block = node.closest('.place-garden-block, .place-grid-series-timeline');
		const bed = block.closest('.garden-col, .bed-item-grid');
		const beds = this.getBeds(bed);

		const series = block.dataset.series;
		const horizontal = block.classList.contains('place-garden-block');
		const span = Math.max(1, parseInt(block.dataset.span || 1));

		const start = beds.indexOf(bed);

		// Les planches de CE bloc, pas toutes celles que la serie occupe dans la
		// grille : apres une dissociation, deux blocs voisins portent la meme serie
		// et l'etirement de l'un partait de l'ancre de l'autre, avalant les deux.
		const held = (start === -1) ?
			[bed] :
			beds.slice(start, start + span);

		this.current = {
			series: series,
			block: block,
			horizontal: horizontal,
			side: node.classList.contains('place-handle-start') ? 'start' : 'end',
			width: block.style.width,
			right: block.style.right,
			styleLeft: block.style.left,
			left: parseFloat(block.style.left) || 2,
			span: span,
			beds: beds,
			held: held,
			anchor: beds.indexOf(held[0]),
			last: beds.indexOf(held[held.length - 1]),
			preview: null
		};

		document.body.classList.add('bed-resizing');

		event.preventDefault();
		event.stopPropagation();

	}

	static move(event) {

		const resize = this.current;

		if(resize === null) {
			return;
		}

		const element = document.elementFromPoint(event.clientX, event.clientY);
		const bed = element === null ? null : element.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

		if(bed === null) {
			return;
		}

		const index = resize.beds.indexOf(bed);

		if(index === -1 || this.isFillBed(bed) || this.isHiddenBed(bed)) {
			return;
		}

		let preview;

		if(resize.side === 'start') {

			if(index > resize.last) {
				return;
			}

			preview = resize.beds.slice(index, resize.last + 1);

		} else {

			if(index < resize.anchor) {
				return;
			}

			preview = resize.beds.slice(resize.anchor, index + 1);

		}

		if(preview.some(node => this.isHiddenBed(node) && resize.held.includes(node) === false)) {
			return;
		}

		resize.preview = preview;

		resize.beds.forEach(b => {
			b.classList.toggle('bed-item-resizing', resize.preview.includes(b));
			b.classList.toggle('bed-item-resizing-held', resize.preview.includes(b) && resize.held.includes(b));
		});

		if(resize.horizontal) {

			resize.block.style.right = 'auto';
			resize.block.style.width = 'calc('+ resize.preview.length +' * 100% - '+ (resize.left + 2) +'px)';

			if(resize.side === 'start') {

				const gained = resize.anchor - resize.beds.indexOf(resize.preview[0]);

				resize.block.style.left = 'calc('+ resize.left +'px - '+ gained +' * 100%)';

			}

		}

		if(resize.preview.length !== resize.drawn) {

			resize.drawn = resize.preview.length;

			if(resize.preview.length > 1) {
				this.markParts(resize.block, resize.preview.length);
			} else {
				resize.block.qsa('.place-bed-part', part => part.remove());
			}

		}

		event.preventDefault();

	}

	static end() {

		const resize = this.current;

		this.current = null;

		if(resize === null) {
			return;
		}

		document.body.classList.remove('bed-resizing');
		qsa('.bed-item-resizing', b => b.classList.remove('bed-item-resizing', 'bed-item-resizing-held'));

		if(resize.preview === null) {

			this.restoreBlock(resize);
			this.restoreParts(resize);

			return;

		}

		const beds = resize.preview.map(b => b.dataset.bed);
		const before = resize.held.map(b => b.dataset.bed);

		if(beds.join(',') === before.join(',')) {

			this.restoreBlock(resize);
			this.restoreParts(resize);

			return;

		}

		const added = beds.filter(bed => before.includes(bed) === false);
		const removed = before.filter(bed => beds.includes(bed) === false);

		SeriesUngroup.forget(resize.series, beds.concat(before));

		this.commit(resize);

		// Le clic qui conclut le geste tombe sur la colonne voisine, donc dans le
		// vide : sans ce repere il desarmait tout et le bloc reprenait sa taille
		// d'avant en attendant la reponse.
		this.moved = true;
		setTimeout(() => this.moved = false, 0);

		if(added.length > 0) {
			SeriesPlace.addBeds(resize.series, added, 'resize');
		} else if(removed.length > 0) {
			SeriesPlace.removeBeds(resize.series, removed);
		}

	}

	// L'etat final se garde sans attendre le serveur, dont la reponse redessine le
	// plan et corrige au besoin. Le bloc prend son nouveau nombre de planches et,
	// s'il a gagne a gauche, change de colonne d'ancrage.
	static commit(resize) {

		const block = resize.block;
		const span = resize.preview.length;
		const anchor = resize.preview[0];

		block.dataset.span = span;
		block.dataset.bed = anchor.dataset.bed;

		if(resize.horizontal) {

			this.host(anchor)?.append(block);

			block.style.left = resize.left +'px';
			block.style.right = 'auto';
			block.style.width = 'calc('+ span +' * 100% - '+ (resize.left + 2) +'px)';

		}

		if(span > 1) {
			this.markParts(block, span);
		} else {
			block.qsa('.place-bed-part', part => part.remove());
		}

		block.classList.toggle('place-garden-block-merged', span > 1);

	}

}

class SeriesUngroup {

	static beds = new Map();

	static has(series, bed) {
		return this.beds.get(String(series))?.has(String(bed)) === true;
	}

	static value() {

		const parts = [];

		this.beds.forEach((set, series) => {
			if(set.size > 0) {
				parts.push(series +':'+ [...set].join('.'));
			}
		});

		return parts.join('_');

	}

	static param() {

		const value = this.value();

		return (value === '') ? '' : '&ungroup='+ value;

	}

	// La marque « ne fusionne pas » ne vaut que tant que l'assolement de la planche
	// ne bouge pas. Un geste qui l'écrit — étirement, déplacement, retrait — la
	// périme : la garder faisait qu'une planche dissociée refusait ensuite de se
	// regrouper, et le geste semblait sans effet.
	static forget(series, beds) {

		const set = this.beds.get(String(series));

		if(set === undefined) {
			return;
		}

		beds.forEach(bed => set.delete(String(bed)));

		if(set.size === 0) {
			this.beds.delete(String(series));
		}

	}

	static split(block) {

		const series = block.dataset.series;
		const span = Math.max(1, parseInt(block.dataset.span || 1));
		const beds = SeriesPanel.getBlockBeds(block, span);

		if(beds.length < 2) {
			return;
		}

		let set = this.beds.get(series);

		if(set === undefined) {
			set = new Set();
			this.beds.set(series, set);
		}

		beds.forEach(bed => set.add(String(bed)));

		this.refresh(series, beds);

	}

	static merge(block) {

		const series = block.dataset.series;
		const set = this.beds.get(series);
		const cell = block.closest('.garden-col, .bed-item-grid');

		if(set === undefined || cell === null) {
			return;
		}

		const beds = SeriesResize.getBeds(cell).map(node => node.dataset.bed);
		const start = beds.indexOf(cell.dataset.bed);

		if(start === -1) {
			return;
		}

		const run = [beds[start]];

		for(let index = start - 1; index >= 0 && set.has(beds[index]); index--) {
			run.unshift(beds[index]);
		}

		for(let index = start + 1; index < beds.length && set.has(beds[index]); index++) {
			run.push(beds[index]);
		}

		run.forEach(bed => set.delete(bed));

		if(set.size === 0) {
			this.beds.delete(series);
		}

		this.refresh(series, run);

	}

	static refresh(series, beds) {

		const context = SeriesPlace.series;

		new Ajax.Query()
			.url('/series/place:ungroupAi?series='+ series + SeriesPlace.scope()
				+ beds.map(bed => '&beds[]='+ bed).join('')
				+ '&view='+ (qs('.garden-plot') === null ? 'plan' : 'garden')
				+ (context === null ? '' : '&context='+ context)
				+ this.param())
			.method('get')
			.fetch()
			.then(() => {

				const block = qs('.garden-col[data-bed="'+ beds[0] +'"] [data-series="'+ series +'"]');

				if(block !== null) {
					SeriesResize.select(block);
				}

			});

	}

}

class SeriesScroll {

	static positions = new Map();
	static page = null;

	static getKey(scroll) {

		const plot = scroll.closest('.garden-plot');

		return (plot === null || plot.dataset.plot === undefined) ? null : plot.dataset.plot;

	}

	static remember(scroll) {

		if(scroll === null || scroll === undefined) {
			return;
		}

		const key = this.getKey(scroll);

		if(key !== null) {
			this.positions.set(key, scroll.scrollLeft);
		}

	}

	// Releve de toutes les positions avant un redessin. Ne pas dependre du seul
	// evenement scroll : il ne remonte pas, et une carte vide rendait le plan a
	// zero a chaque reponse du serveur.
	static rememberAll() {
		qsa('.garden-scroll', scroll => this.remember(scroll));
	}

	static rememberPage() {

		const url = location.pathname + location.search;
		const y = window.scrollY;

		if(y < 200 && this.page !== null && this.page.url === url) {
			return;
		}

		this.page = {url: url, y: y};

	}

	static restorePage() {

		if(
			this.page === null ||
			this.page.y < 200 ||
			this.page.url !== location.pathname + location.search
		) {
			return;
		}

		const apply = () => {

			if(window.scrollY > 100) {
				return;
			}

			window.scrollTo(0, this.page.y);

		};

		apply();

		requestAnimationFrame(apply);

		setTimeout(apply, 250);

	}

	static restore() {

		const apply = () => qsa('.garden-scroll', scroll => {

			const key = this.getKey(scroll);
			const left = (key === null) ? undefined : this.positions.get(key);

			if(left === undefined || left === 0 || scroll.scrollLeft === left) {
				return;
			}

			scroll.scrollLeft = left;

		});

		apply();

		requestAnimationFrame(apply);

	}

}

// En capture, et non par delegation : l'evenement scroll ne remonte pas, si bien
// que le delegue ne voyait jamais rien et que la carte des positions restait
// vide. Le plan retombait donc a zero a chaque redessin.
document.addEventListener('scroll', function(e) {

	const scroll = (e.target instanceof Element) ? e.target.closest('.garden-scroll') : null;

	if(scroll !== null) {
		SeriesScroll.remember(scroll);
	}

}, {capture: true, passive: true});

document.delegateEventListener('click', '.garden-col[data-hide="1"], .bed-item-grid[data-hide="1"]', function(e) {

	e.preventDefault();
	e.stopPropagation();

	this.classList.add('place-hidden-unfolded');

	if(this.dataset.bed !== undefined) {
		SeriesPlace.unfolded.add(this.dataset.bed);
	}

	SeriesPlace.paintRails();

});

// La boite de reglage doit etre reellement atteignable : presente, dans l'ecran,
// et au premier plan a l'endroit ou on la voit.
const placeSizeVisible = () => {

	const box = qs('#place-size');

	if(box === null) {
		return false;
	}

	const rect = box.getBoundingClientRect();

	if(
		rect.width < 40 || rect.height < 40 ||
		rect.bottom < 0 || rect.right < 0 ||
		rect.top > window.innerHeight || rect.left > window.innerWidth
	) {
		return false;
	}

	const point = document.elementFromPoint(Math.round(rect.left + rect.width / 2), Math.round(rect.top + 10));

	return (point !== null && box.contains(point));

};

// La planche se remplit sans attendre le serveur, d'apres un bloc que la serie a
// deja dans la meme parcelle ; sans modele, on attend la reponse, qui redessine
// le plan dans tous les cas.
const placePoseCommit = function(series, cell) {

	const plot = cell.closest('.plot-wrapper');
	const host = SeriesResize.host(cell);
	const model = plot?.qs('.place-garden-block-season[data-series="'+ series +'"], .place-grid-series-timeline-season[data-series="'+ series +'"]');

	if(host === null || host === undefined || model === null || model === undefined) {
		return;
	}

	const block = model.cloneNode(true);

	block.classList.remove('place-selected', 'place-garden-block-merged', 'place-arrive');
	block.qsa('.place-handle, .place-bed-part, .place-selected-label', node => node.remove());
	block.dataset.span = 1;
	block.dataset.bed = cell.dataset.bed;

	// En chronologie la largeur est le temps : le fantome porte deja la tranche que
	// le serveur rendra, et un bloc pleine largeur couvrait la saison entiere avant
	// de se reduire. Au jardin le temps est la hauteur, que le modele porte deja, et
	// la largeur est celle de la colonne.
	const placeholder = cell.qs('.place-grid-series-timeline-placeholder');

	if(placeholder !== null) {
		block.style.left = placeholder.style.left;
		block.style.width = placeholder.style.width;
		block.style.right = 'auto';
		block.style.top = placeholder.style.top;
	} else {
		block.style.left = '2px';
		block.style.right = 'auto';
		block.style.width = 'calc(100% - 4px)';
	}

	cell.qsa('.place-garden-block-placeholder, .place-grid-series-timeline-placeholder, .place-pose', node => node.remove());
	SeriesPlacement.syncPosing();

	host.append(block);

};

const placePoseRun = function(bouton) {

	const series = SeriesPlace.series;

	if(series === null && SeriesPlace.task === null) {
		return;
	}

	const cell = bouton.closest('.garden-col[data-bed], .bed-item-grid[data-bed]') ?? bouton;
	const bed = bouton.dataset.bed;
	const context = qs('#place-context');

	const offered = (cell.dataset.rest === undefined || cell.dataset.rest === '') ?
		parseInt(cell.dataset.length || 0) :
		parseInt(cell.dataset.rest);

	const target = parseInt(context?.dataset.target || 0);
	const posed = parseInt(context?.dataset.value || 0);

	const missing = (target > 0) ? (target - posed) : null;
	const reached = (missing !== null && missing <= 0);
	const wanted = (missing === null || reached) ? offered : missing;

	const envoi = (size) => {
		placePoseCommit(series, cell);
		SeriesPlace.addBeds(series, [bed], 'add', size);
	};

	// Une intervention prend la planche entiere : buildFromBeds() lui pose length et
	// area a NULL. Lui demander une longueur promettrait un reglage sans effet.
	if(SeriesPlace.task !== null) {
		envoi(undefined);
		return;
	}

	if(reached || cell.dataset.fill === '1' || offered <= 0 || wanted > offered) {

		// Ancree sur le bouton et non sur la planche : en vue ligne la planche est
		// toute la rangee, et la boite s'ouvrait a son bord gauche, loin du geste.
		SeriesSize.ask(cell, wanted, envoi, null, bouton);

		// La boite ne s'ouvre pas partout, et le geste mourait avec elle. Tant
		// qu'elle n'a pas sa maquette pour ces cas, on pose la longueur proposee
		// plutot que de ne rien faire.
		if(wanted > 0 && placeSizeVisible() === false) {
			SeriesSize.close();
			envoi(wanted);
		}

		return;

	}

	envoi(wanted);

};

// Le doigt n'a pas droit au clic dans tous les cas : quand la pastille bouge ou
// disparait entre l'appui et le relachement, ou quand le navigateur prend le
// geste pour un defilement, aucun clic n'est emis. Trois portes donc, et un
// verrou de 700 ms pour ne poser qu'une fois.
let placePosePress = null;
let placePoseDone = 0;

const placePoseArm = (node, x, y) => {
	placePosePress = (node === null) ? null : {bed: node.dataset.bed, x: x, y: y};
};

const placePoseFire = (x, y) => {

	const press = placePosePress;
	placePosePress = null;

	if(press === null) {
		return;
	}

	// Un doigt qui glisse fait defiler, il ne pose pas.
	if(Math.abs(x - press.x) > 12 || Math.abs(y - press.y) > 12) {
		return;
	}

	// La pastille a pu etre refaite entre l'appui et le relachement : on la
	// retrouve par sa planche plutot que de garder le noeud.
	const node = qs('.place-pose[data-bed="'+ press.bed +'"]');

	if(node === null) {
		return;
	}

	placePoseDone = Date.now();

	placePoseRun(node);

};

document.addEventListener('touchstart', function(e) {
	placePoseArm(e.target.closest?.('.place-pose') ?? null, e.changedTouches[0].clientX, e.changedTouches[0].clientY);
}, {passive: true, capture: true});

document.addEventListener('touchend', function(e) {
	placePoseFire(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
}, true);

document.addEventListener('touchcancel', () => placePosePress = null, true);
document.addEventListener('pointercancel', () => placePosePress = null, true);

document.delegateEventListener('pointerdown', '.place-pose', function(e) {

	if(e.pointerType !== 'mouse') {
		placePoseArm(this, e.clientX, e.clientY);
	}

});

document.delegateEventListener('pointerup', '.place-pose', function(e) {

	if(e.pointerType !== 'mouse') {
		placePoseFire(e.clientX, e.clientY);
	}

});

document.delegateEventListener('click', '.place-pose', function(e) {

	e.preventDefault();
	e.stopPropagation();

	placePosePress = null;

	if(Date.now() - placePoseDone < 700) {
		return;
	}

	placePoseRun(this);

});

document.delegateEventListener('click', '.place-nodate-line', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	if(SeriesDrag.moved) {
		return;
	}

	qsa('.place-nodate-line', node => node.classList.remove('selected'));
	this.classList.add('selected');

	SeriesResize.select(this, e);
	SeriesPanel.openSeries(this, e);

});

class SeriesPanel {

	static anchor = null;
	static series = null;
	static bed = null;
	static season = null;
	static level = 'block';

	static context() {

		const series = this.series ?? SeriesPlace.series;

		if(series !== null) {
			return {series: series};
		}

		return {season: this.season ?? ''};

	}

	static get labels() {
		return window.seriesPlaceLabels ?? {};
	}

	static openSeries(block, event) {

		const span = Math.max(1, parseInt(block.dataset.span || 1));

		const beds = this.getBlockBeds(block, span);

		const bed = (span === 1) ? block.dataset.bed : beds[0];

		// Le panneau porte deja cette planche : le redemander rendrait la meme fiche.
		if(
			this.series === block.dataset.series &&
			this.bed === (bed ?? null) &&
			qs('#series-selector-list .series-selector-cultivation.selected') !== null
		) {
			return;
		}

		this.series = block.dataset.series;
		this.bed = bed ?? null;

		this.close();

		new Ajax.Query(qs('#series-selector'))
			.url('/series/place:panel?series='+ this.series + SeriesPlace.scope()
				+ SeriesPlace.filters()
				+ (bed ? '&bed='+ bed : '')
				+ (beds.length > 1 ? beds.map(b => '&beds[]='+ b).join('') : '')
				+ '&view='+ (qs('.garden-plot') === null ? 'plan' : 'garden'))
			.method('get')
			.fetch();

	}

	static getBlockBeds(block, span) {

		const cell = block.closest('.garden-col, .bed-item-grid');

		if(cell === null) {
			return [];
		}

		const beds = SeriesResize.getBeds(cell);
		const start = beds.indexOf(cell);

		if(start === -1) {
			return [];
		}

		if(span > 1) {
			return beds.slice(start, start + span).map(node => node.dataset.bed);
		}

		return [];

	}

	static openBed(trigger, bed) {

		this.series = SeriesPlace.series;
		this.bed = bed;
		this.season = trigger.dataset.season ?? null;

		this.build(trigger);

		const context = new URLSearchParams(this.context()).toString();

		new Ajax.Query(qs('#place-panel'))
			.url('/series/place:bedPanel?bed='+ bed + SeriesPlace.scope() +'&'+ context)
			.method('get')
			.fetch();

	}

	static build(anchor) {

		this.close();

		this.anchor = anchor;

		if(anchor.id === '') {
			anchor.id = 'place-panel-anchor-'+ (++Lime.counter);
		}

		const list = document.createElement('div');

		list.id = 'place-panel';
		list.className = 'dropdown-list bg-primary place-panel';
		list.setAttribute('data-dropdown-keep', '');
		list.innerHTML = '<div class="place-panel-loading">'+ Lime.Asset.icon('arrow-repeat') +'</div>';

		anchor.insertAdjacentElement('afterend', list);

		Lime.Dropdown.open(anchor, 'bottom-start');

	}

	static close() {

		if(this.anchor !== null && Lime.Dropdown.isOpen(this.anchor)) {
			Lime.Dropdown.close(this.anchor);
		}

		this.anchor = null;

		qs('#place-panel', node => node.remove());

	}

	static addOnBed(cultivation) {

		this.close();

		SeriesSelector.edit(cultivation);

	}

	static placeHere(bed) {

		this.close();

		SeriesPlace.addBeds(SeriesPlace.series, [bed], 'add');

	}

	static removeBed(bed, block, task = null) {

		this.close();

		const beds = (block === undefined || block === null) ?
			[] :
			this.getBlockBeds(block, Math.max(1, parseInt(block.dataset.span || 1)));

		// Le bloc disparait sans attendre : la reponse redessine le plan.
		if(block !== undefined && block !== null) {
			SeriesResize.clearSelection();
			block.remove();
		}

		SeriesPlace.removeBeds(this.series, (beds.length > 0) ? beds : [bed], task);

	}

	static hideSeries() {

		const series = this.series;

		this.close();
		SeriesResize.clearSelection();

		SeriesHidden.toggleSeries(series);

	}

	static removeSeries() {

		this.close();

		SeriesPlace.send('doRemoveSeries', {series: this.series}, 'remove');

	}

	static emptyBed(bed) {

		this.close();

		SeriesPlace.send('doEmptyBed', {...this.context(), bed: bed}, 'empty');

	}

	static toggleSwap(target) {

		const swap = target.firstParent('#place-panel').qs('.place-panel-swap');

		if(swap === null) {
			return;
		}

		swap.classList.toggle('place-panel-fold-open');

	}

	static swapBed(bed, swap) {

		this.close();

		SeriesPlace.send('doSwapBed', {...this.context(), bed: bed, swap: swap}, 'swap');

	}

	static previewLength(input) {

		const wrapper = input.firstParent('.place-panel-length');
		const total = parseInt(wrapper.dataset.total);
		const value = Math.max(1, Math.min(total, parseInt(input.value || 0)));

		wrapper.qs('.place-panel-length-bar i').style.width = Math.round(value / total * 100) +'%';

	}

	static setLength(target, value) {

		const wrapper = target.firstParent('.place-panel-length');
		const input = wrapper.qs('[name="length"]');

		input.value = value;

		this.previewLength(input);

	}

	static submitLength(target) {

		const wrapper = target.firstParent('.place-panel-length');
		const bed = wrapper.dataset.bed;
		const value = parseInt(wrapper.qs('[name="length"]').value || 0);

		this.close();

		SeriesPlace.send('doLength', {series: this.series, bed: bed, size: value}, 'length');

	}

}

class SeriesChip {

	static minHeight = 40;
	static minWidth = 60;

	static get labels() {
		return window.seriesPlaceLabels ?? {};
	}

	static equip(block, box = null) {

		if(block.classList.contains('place-selected') === false) {
			return;
		}

		if(parseInt(block.dataset.bedLength || 0) <= 0) {
			return;
		}

		const fill = block.qs('.place-fill');

		if(fill === null) {
			return;
		}

		box = box ?? block.getBoundingClientRect();

		const garden = block.classList.contains('place-garden-block');

		if(garden ? (box.height < this.minHeight) : (box.width < this.minWidth)) {
			return;
		}

		fill.classList.add('place-fill-live');
		fill.title = this.labels.chipEdit ?? '';

		if(fill.qs('.place-fill-pencil') === null) {
			fill.insertAdjacentHTML('afterbegin', '<div class="place-fill-pencil">'+ Lime.Asset.icon('pencil-fill') +'</div>');
		}

	}

	static unequip(block) {

		block.qsa('.place-fill-pencil', node => node.remove());
		block.qsa('.place-fill-live', fill => {
			fill.classList.remove('place-fill-live');
			fill.removeAttribute('title');
		});

	}

	static open(node) {

		const block = node.placeBlock ?? node.closest('.place-garden-block, .place-grid-series-timeline');

		if(block === null) {
			return;
		}

		const series = block.dataset.series;
		const bed = node.dataset.bed ?? block.dataset.bed;

		const grid = block.closest('.garden-grid, .plot-wrapper');
		const cell = (grid ?? document).querySelector('.garden-col[data-bed="'+ bed +'"], .bed-item-grid[data-bed="'+ bed +'"]')
			?? block.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

		if(cell === null) {
			return;
		}

		SeriesPanel.close();

		const placed = parseInt(node.dataset.length ?? block.dataset.length ?? 0);

		SeriesSize.ask(cell, placed, size => {
			SeriesPlace.send('doLength', {series: series, bed: bed, size: size}, 'length');
		}, placed, node);

	}

}

class SeriesSize {

	static pending = null;
	static dragging = false;

	static get labels() {
		return window.seriesPlaceLabels ?? {};
	}

	static needed(node, beds, source) {

		if(node.dataset.fill === '1') {
			return true;
		}

		if(beds.length !== 1) {
			return false;
		}

		const target = parseInt(node.dataset.length || 0);

		return (target > 0 && source > 0 && source > target);

	}

	static ask(node, current, envoi, alreadyPlaced = null, anchor = null) {

		this.close();

		// Le panneau d'assolement n'a pas de fiche du bac : c'est alors le plan qui dit
		// l'usage et le nom de la série qu'il arme, sans quoi une surface libre se
		// saisissait en mL.
		const context = qs('#place-context') ?? qs('#zone-container');
		const block = (context?.dataset.use === 'block');
		const fill = (node.dataset.fill === '1');

		const total = block ? parseInt(node.dataset.area || 0) : parseInt(node.dataset.length || 0);

		const free = (node.dataset.rest === undefined || node.dataset.rest === '') ?
			total :
			Math.min(total, parseInt(node.dataset.rest));

		const value = fill ?
			null :
			Math.max(1, total > 0 ? Math.min(total, alreadyPlaced || free || current || total) : (alreadyPlaced || current || 1));
		const unit = block ? (this.labels.areaUnit ?? 'm²') : (this.labels.lengthMl ?? 'mL');

		const name = context?.dataset.name ?? '';

		let bed;

		if(fill) {
			bed = this.labels.sizeOnFill ?? '{series} sur la surface libre';
		} else if(name === '') {
			bed = (this.labels.sizeBedOnly ?? 'Longueur à assoler · planche {bed}').replace('{bed}', this.getBedName(node));
		} else {
			bed = (this.labels.sizeOnBed ?? '{series} sur la planche {bed}').replace('{bed}', this.getBedName(node));
		}

		this.pending = envoi;

		const box = document.createElement('div');

		box.id = 'place-size';
		box.className = 'place-size';
		box.setAttribute('data-dropdown-keep', '');
		box.dataset.total = total || 0;
		box.dataset.unit = unit;

		const percent = (total > 0 && value !== null) ? Math.min(100, Math.round(value / total * 100)) : 0;

		box.innerHTML =
			'<div class="place-size-title">'+ bed.replace('{series}', name) +'</div>'
			+ (total > 0 ?
				'<div class="place-size-gauge"><i style="width: '+ percent +'%"></i><s style="left: '+ percent +'%"></s></div>'
				+ '<div class="place-size-scale">'
					+ '<span>0</span>'
					+ (free > 0 && free < total ? '<span>'+ (this.labels.sizeFree ?? '{value} libres').replace('{value}', free) +'</span>' : '<span></span>')
					+ '<span>'+ total +' '+ unit +'</span>'
				+ '</div>'
			: '')
			+ '<div class="place-size-row">'
				+ '<input type="number" name="size" value="'+ (value === null ? '' : value) +'" min="1"'+ (total > 0 ? ' max="'+ total +'"' : '') +' oninput="SeriesSize.preview(this)">'
				+ '<span class="place-size-unit">'+ unit +'</span>'
			+ '</div>'
			+ '<div class="place-size-buttons">'
				+ '<a onclick="SeriesSize.confirm(this)" class="btn btn-sm btn-secondary place-size-confirm '+ (value === null ? 'disabled' : '') +'">'+ this.getConfirmLabel(value, unit) +'</a>'
				+ '<a onclick="SeriesSize.close()" class="btn btn-sm btn-outline-primary">'+ (this.labels.sizeCancel ?? 'Annuler') +'</a>'
			+ '</div>';

		// Le rang se prend au compteur du cadriciel, comme les panneaux et les menus :
		// une valeur fixe passerait sous le premier panneau ouvert, dont le z-index
		// depend du nombre de couches deja empilees (Lime.getZIndex, lime.js).
		box.style.zIndex = Lime.getZIndex();

		document.body.append(box);

		const rect = (anchor ?? node).getBoundingClientRect();

		const above = rect.top - box.offsetHeight - 4;
		const below = Math.min(rect.bottom + 4, window.innerHeight - box.offsetHeight - 8);

		box.style.left = Math.round(Math.max(8, Math.min(rect.left, window.innerWidth - box.offsetWidth - 8))) +'px';
		box.style.top = Math.round(Math.max(8, (above >= 8) ? above : below)) +'px';

		box.qs('[name="size"]', input => input.select());

	}

	static getConfirmLabel(value, unit) {

		if(value === null) {
			return this.labels.sizeConfirmEmpty ?? 'Assoler';
		}

		return (this.labels.sizeConfirmValue ?? 'Assoler {value} {unit}')
			.replace('{value}', value)
			.replace('{unit}', unit);

	}

	static getBedName(node) {

		const cell = node.closest('.garden-col, .bed-item-grid') ?? node;
		const label = cell.qs('.garden-col-name, .bed-item-name b, .bed-item-name');

		return (label === null) ? (node.dataset.bed ?? '') : label.innerText.trim().split('\n')[0];

	}

	static preview(input) {

		const box = qs('#place-size');
		const total = parseInt(box?.dataset.total) || 0;
		if(input.value.trim() === '') {
			this.paint(null);
			return;
		}

		const value = Math.max(1, total > 0 ? Math.min(total, parseInt(input.value || 0)) : parseInt(input.value || 1));

		if(input.value !== String(value)) {
			input.value = value;
		}

		this.paint(value);

	}

	static paint(value) {

		const box = qs('#place-size');

		if(box === null) {
			return;
		}

		const total = parseInt(box.dataset.total) || 0;
		const percent = (total > 0 && value !== null) ? Math.min(100, Math.round(value / total * 100)) : 0;

		box.qs('.place-size-gauge i', bar => bar.style.width = percent +'%');
		box.qs('.place-size-gauge s', knob => knob.style.left = percent +'%');
		box.qs('.place-size-confirm', node => {
			node.innerHTML = this.getConfirmLabel(value, box.dataset.unit);
			node.classList.toggle('disabled', value === null);
		});

	}

	static drag(event) {

		const box = qs('#place-size');
		const gauge = box?.qs('.place-size-gauge');
		const total = parseInt(box?.dataset.total) || 0;

		if(gauge === null || gauge === undefined || total === 0) {
			return;
		}

		const rect = gauge.getBoundingClientRect();
		const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
		const value = Math.max(1, Math.round(total * ratio));

		box.qs('[name="size"]', input => input.value = value);

		this.paint(value);

		event.preventDefault();

	}

	static confirm() {

		const box = qs('#place-size');
		const value = parseInt(box?.qs('[name="size"]')?.value || 0);
		const envoi = this.pending;

		this.close();

		if(envoi !== null && value > 0) {
			envoi(value);
		}

	}

	static close() {

		this.pending = null;

		qs('#place-size', node => node.remove());

	}

}

class SeriesHidden {

	static blocks = new Set();

	static key(series, bed) {
		return series +'/'+ bed;
	}

	static toggle(series, bed) {

		const key = this.key(series, bed);

		if(this.blocks.has(key)) {
			this.blocks.delete(key);
		} else {
			this.blocks.add(key);
		}

		this.repaint();

	}

	static toggleSeries(series) {

		const blocks = SeriesResize.getBlocks(series);
		const keys = blocks.map(block => this.key(series, block.dataset.bed));
		const hidden = keys.every(key => this.blocks.has(key));

		keys.forEach(key => hidden ? this.blocks.delete(key) : this.blocks.add(key));

		this.repaint();

	}

	static clear() {

		this.blocks.clear();
		this.repaint();

	}

	static repaint() {

		qsa('.place-hidden', block => block.classList.remove('place-hidden'));

		for(const key of this.blocks) {

			const [series, bed] = key.split('/');

			qsa('[data-series="'+ series +'"][data-bed="'+ bed +'"]', block => {

				if(block.closest('.garden-col, .bed-item-grid') !== null) {
					block.classList.add('place-hidden');
				}

			});

		}

		this.paintBar();

	}

	static paintBar() {

		qs('#place-hidden-bar', node => node.remove());

		document.body.style.removeProperty('--place-hidden-height');

		if(this.blocks.size === 0) {
			return;
		}

		const labels = window.seriesPlaceLabels ?? {};

		const node = document.createElement('div');

		node.id = 'place-hidden-bar';
		node.className = 'place-hidden-bar';
		const count = (this.blocks.size > 1) ? (labels.hiddenMany ?? 'blocs à l\'arrière-plan') : (labels.hiddenOne ?? 'bloc à l\'arrière-plan');

		node.innerHTML = '<span>'+ this.blocks.size +' '+ count +'</span>'
			+ '<a onclick="SeriesHidden.clear()">'+ (labels.hiddenRestore ?? 'Tout ramener devant') +'</a>';

		placeOverlay().append(node);

		document.body.style.setProperty('--place-hidden-height', node.offsetHeight +'px');

	}

}

document.delegateEventListener('pointerdown', '.place-handle', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	SeriesResize.start(this, e);
});

document.delegateEventListener('pointerdown', '#place-size .place-size-gauge', function(e) {

	SeriesSize.dragging = true;
	SeriesSize.drag(e);

});

document.addEventListener('pointermove', e => {
	if(SeriesSize.dragging) {
		SeriesSize.drag(e);
	}
});

document.addEventListener('pointerup', () => SeriesSize.dragging = false);
document.addEventListener('pointercancel', () => SeriesSize.dragging = false);

document.delegateEventListener('keydown', '#place-size [name="size"]', function(e) {

	if(e.key === 'Enter') {
		e.preventDefault();
		SeriesSize.confirm();
	}

});

document.delegateEventListener('click', '.place-bed-panel', function(e) {

	e.preventDefault();
	e.stopPropagation();

	SeriesPanel.openBed(this, this.dataset.bed);

});

const PLACE_HOVER_DELAY = 200;
const PLACE_HOVER_BAR = 41;

let placeHoverTimer = null;
let placePointerX = -1;
let placePointerY = -1;

// Sur mousemove et pas sur pointermove : mesure faite, le navigateur dispatche
// mouseover AVANT pointermove, si bien qu'un garde pose dans mouseenter lisait
// toujours la position d'avant. Le garde prend donc ses coordonnees de son
// propre evenement, et ce suivi ne sert qu'au minuteur, qui court plus tard.
document.addEventListener('mousemove', e => {

	if(e.clientX !== placePointerX || e.clientY !== placePointerY) {
		placeScrolled = false;
	}

	placePointerX = e.clientX;
	placePointerY = e.clientY;

}, {passive: true});

let placeScrolled = false;

let placeHover = null;

let placeEnterTimer = null;
let placeEnterBlock = null;

const placeHoverStop = () => {

	if(placeHoverTimer !== null) {
		clearTimeout(placeHoverTimer);
		placeHoverTimer = null;
	}

};

const placeEnterStop = () => {

	if(placeEnterTimer !== null) {
		clearTimeout(placeEnterTimer);
		placeEnterTimer = null;
	}

	placeEnterBlock = null;

};

document.addEventListener('scroll', () => {

	placeScrolled = true;

	placeEnterStop();

}, {passive: true, capture: true});

// Le couloir qui mene aux pastilles, et rien d'autre : au-dessus du bloc, a
// l'aplomb des pastilles elles-memes. Prendre toute la largeur du bloc empechait
// de revenir sur celui du dessous, dont la partie visible est justement au-dessus
// du bloc pose par-dessus.
const placeHoverInBar = (block, x, y) => {

	if(block.placePills === undefined) {
		return false;
	}

	const top = block.getBoundingClientRect().top;

	return block.placePills.some(pill => {

		const rect = pill.getBoundingClientRect();

		return (
			x >= rect.left - 6 && x <= rect.right + 6 &&
			y >= rect.top - 6 && y <= top
		);

	});

};

const placeHoverInside = (block, x, y) => {

	const rect = block.getBoundingClientRect();

	return (
		x >= rect.left && x <= rect.right &&
		y >= rect.top - PLACE_HOVER_BAR && y <= rect.bottom
	);

};

// Les pastilles appartiennent au bloc, pas a la page : chacun a les siennes,
// portees par lui. Un exemplaire unique deplace d'un bloc a l'autre volait ses
// actions a la planche retenue des qu'on en survolait une autre.
const placePillsDrop = block => {

	if(block === null || block.placePills === undefined) {
		return;
	}

	block.placePills.forEach(pill => pill.remove());

	delete block.placePills;

};

// Les pastilles du bloc qui perd le survol sont rendues sans etre detruites :
// celui qui le prend les recycle. Recreer la paire a chaque survol reparse deux
// icones et salit la mise en page. Mesure : 42,5 ms sans, 19,9 ms avec.
const placePillsRelease = block => {

	if(block === null || block.placePills === undefined) {
		return [];
	}

	const pills = block.placePills;

	delete block.placePills;

	return pills;

};

const placePillTake = (recycle, className, icon) => {

	const found = recycle.findIndex(pill => pill.classList.contains(className));

	if(found !== -1) {
		return recycle.splice(found, 1)[0];
	}

	const pill = document.createElement('i');

	pill.className = className;
	pill.innerHTML = Lime.Asset.icon(icon);

	return pill;

};

// Un panneau recoit son z-index de Lime.getZIndex(), a partir de 1100 : accrochees
// a body, les barres flottantes passaient sous celui qui porte la grille.
const placeOverlay = () => qs('#zone-container')?.closest('.panel') ?? document.body;

const placePillsPaint = (block, recycle = []) => {

	const body = block.parentElement;

	if(body === null) {
		return;
	}

	const labels = window.seriesPlaceLabels ?? {};
	const coarse = window.matchMedia('(pointer: coarse)').matches;

	const narrow = window.matchMedia(SeriesPlace.tokensMedia).matches;

	// Toutes les mesures d'abord, toutes les ecritures ensuite : lire offsetLeft
	// apres avoir touche au DOM force une mise en page. Mesure sur une page de
	// 312 blocs : 0,1 ms en lisant avant, 98,5 ms en lisant apres.
	// Un bloc qui demarre en haut du corps n'a pas de place au-dessus de lui : la
	// pastille y etait plaquee a 0. Elle rentre alors dans le bloc, au meme bord
	// droit que d'habitude — la pastille de verrou est au coin gauche.
	const pillLeft = block.offsetLeft + block.offsetWidth - 35;
	const pillTop = block.offsetTop - 37;

	// Une serie qui deborde la frise est rognee a son bord par .bed-item-places
	// (map/static/css/bed.css) : posee au bout du bloc, la corbeille l'etait avec.
	const dropLeft = Math.min(block.offsetLeft + block.offsetWidth, body.offsetWidth) - 35;

	// L'oeil « passer en arriere-plan » se greffe aussi pendant qu'on assole : c'est
	// justement la ou l'on a besoin d'ecarter un bloc pour voir dessous. Seul le
	// placement de la corbeille tient compte des boutons Poser, pour ne pas se poser
	// dessus.
	const alone = block.classList.contains('place-grid-series-timeline');

	const posing = (qs('.place-pose') !== null);

	// Seule la chronologie garde la pastille : elle n'a pas d'etiquette ou loger
	// la corbeille.
	const withDrop = (
		SeriesPlace.editing &&
		block.dataset.locked !== '1' &&
		block.dataset.bed !== undefined &&
		block.classList.contains('place-selected') &&
		block.classList.contains('place-grid-series-timeline')
	);

	recycle = recycle.concat(placePillsRelease(block));

	const pills = [];

	if(alone === false) {

		const node = placePillTake(recycle, 'place-back', 'eye-slash');

		// Surtout pas data-series : getBlocks() ramasse tout ce qui le porte dans
		// une colonne et prenait les pastilles pour des blocs, jusqu'a leur greffer
		// des poignees de redimensionnement en plein milieu.
		node.dataset.pillSeries = block.dataset.series;
		node.title = labels.sendBack ?? "Envoyer à l'arrière-plan";
		node.style.left = pillLeft +'px';
		node.style.top = pillTop +'px';
		node.classList.toggle('place-pill-inert', coarse);
		node.placeBlock = block;

		pills.push(node);

	}

	if(withDrop) {

		const span = Math.max(1, parseInt(block.dataset.span || 1));
		const drop = placePillTake(recycle, 'place-drop', 'trash');

		drop.dataset.bed = block.dataset.bed;
		drop.dataset.pillSeries = block.dataset.series;
		drop.dataset.pillTask = block.dataset.task ?? '';
		drop.title = (span > 1) ?
			(labels.removeBedMany ?? "Retirer ces {value} planches").replace('{value}', span) :
			(labels.removeBed ?? "Retirer cette planche");
		drop.dataset.confirm = (span > 1) ?
			(labels.removeBedConfirmMany ?? "Ces {value} planches seront retirées de l'assolement de la série. Continuer ?").replace('{value}', span) :
			(labels.removeBedConfirm ?? "Cette planche sera retirée de l'assolement de la série. Continuer ?");
		drop.style.left = ((alone || posing) ? dropLeft : dropLeft - (narrow ? 48 : 33)) +'px';
		drop.style.top = pillTop +'px';
		// Jamais inerte, contrairement a l'oeil : la corbeille n'apparait que sur
		// une planche retenue, donc apres l'appui qui l'a retenue. L'appui suivant
		// est deja un geste vise, et l'inertie lui volait son clic.
		drop.classList.remove('place-pill-inert');
		drop.placeBlock = block;

		pills.push(drop);

		const fill = block.qs('.place-fill');

		if(fill !== null) {

			const length = placePillTake(recycle, 'place-length', 'pencil-fill');

			length.innerHTML = '<b>'+ fill.qs('b').innerHTML +'</b>'+ Lime.Asset.icon('pencil-fill');
			length.dataset.bed = fill.dataset.bed;
			length.dataset.length = fill.dataset.length ?? block.dataset.length ?? 0;
			length.title = labels.chipEdit ?? '';
			length.style.left = 'auto';
			length.style.right = (body.clientWidth - dropLeft + 6) +'px';
			length.style.top = pillTop +'px';
			length.placeBlock = block;

			pills.push(length);

		}

	}

	recycle.forEach(pill => pill.remove());

	if(pills.length === 0) {
		return;
	}

	pills.forEach(pill => body.append(pill));

	block.placePills = pills;

	if(coarse) {

		// Au doigt, mouseenter precede mousedown dans le meme appui : sans cela
		// l'appui qui revele les pastilles les declenche aussi. On les rend inertes
		// jusqu'a l'appui suivant.
		document.addEventListener('pointerdown', () => {
			qsa('.place-pill-inert', pill => pill.classList.remove('place-pill-inert'));
		}, {once: true, capture: true});

	}

};

// Le panneau d'assolement d'une serie : #place-update ne vit que la, et tout bloc
// qui n'est ni la serie assolee ni celle armee depuis le bac y est etranger.
const placeBlockForeign = block => (
	qs('#place-update') !== null &&
	(block.dataset.series !== undefined || block.dataset.task !== undefined) &&
	block.classList.contains('place-current') === false &&
	block.classList.contains('place-selected-series') === false
);

const placeCoveringPaint = block => {

	const scroll = block.closest('.garden-scroll');

	if(scroll === null) {
		qsa('.place-covering', node => node.classList.remove('place-covering'));
		return;
	}

	const box = block.getBoundingClientRect();
	const neighbours = [...scroll.querySelectorAll('.place-garden-block')].filter(other => other !== block && other.classList.contains('place-hidden') === false && other.classList.contains('place-filtered-out') === false);
	const boxes = neighbours.map(other => other.getBoundingClientRect());

	qsa('.place-covering', node => node.classList.remove('place-covering'));

	neighbours.forEach((other, index) => {

		const rect = boxes[index];

		if(rect.left < box.right && rect.right > box.left && rect.top < box.bottom && rect.bottom > box.top) {
			other.classList.add('place-covering');
		}

	});

};

// La planche retenue porte ses actions en tout temps. Plutot que de traquer le
// chemin qui les lui retire, on verifie la paire a chaque changement de survol et
// on la repose si la corbeille manque.
const placePillsEnsure = () => {

	qsa('.place-selected', selected => {

		if(
			selected.dataset.locked === '1' ||
			selected.dataset.bed === undefined
		) {
			return;
		}

		const pills = selected.placePills ?? [];

		const complete = pills.some(pill => pill.classList.contains('place-drop') && document.contains(pill));

		if(complete === false) {
			placePillsPaint(selected);
		}

	});

};

const placeHoverTake = block => {

	placeHoverStop();

	if(block.classList.contains('place-hidden') || placeBlockForeign(block)) {
		return;
	}

	if(placeHover === block) {
		return;
	}

	const previous = placeHover;

	placeHover = block;

	const recycle = (previous !== null && previous.classList.contains('place-selected') === false) ?
		placePillsRelease(previous) :
		[];

	qsa('.place-hover-front', node => node.classList.remove('place-hover-front'));
	qsa('.place-hovered', node => node.classList.remove('place-hovered'));

	block.classList.add('place-hover-front');

	placeCoveringPaint(block);
	placePillsPaint(block, recycle);

	placePillsEnsure();

};

const placeHoverRelease = () => {

	const block = placeHover;

	placeHover = null;

	if(block !== null && block.classList.contains('place-selected') === false) {
		placePillsDrop(block);
	}

	qsa('.place-hover-front', node => node.classList.remove('place-hover-front'));
	qsa('.place-hovered', node => node.classList.remove('place-hovered'));
	qsa('.place-covering', node => node.classList.remove('place-covering'));

	placePillsEnsure();

};

const placeHoverLeave = () => {

	placeHoverStop();

	const forget = () => {

		placeHoverTimer = null;

		if(placeEnterBlock !== null) {
			return;
		}

		if(placeHover !== null && placeHoverInside(placeHover, placePointerX, placePointerY)) {
			placeHoverTimer = setTimeout(forget, PLACE_HOVER_DELAY);
			return;
		}

		placeHoverRelease();

	};

	placeHoverTimer = setTimeout(forget, PLACE_HOVER_DELAY);

};

document.delegateEventListener('mouseenter', '.place-garden-block-season', function(e) {

	if(this.dataset.series === undefined || this.classList.contains('place-hidden')) {
		return;
	}

	if(placeScrolled) {
		return;
	}

	if(placeHover === this) {
		placeEnterStop();
		placeHoverStop();
		return;
	}

	if(
		placeHover !== null &&
		placeHoverInBar(placeHover, e.clientX, e.clientY)
	) {
		placeEnterStop();
		placeHoverStop();
		return;
	}

	if(placeEnterBlock === this) {
		return;
	}

	placeEnterStop();

	const block = this;

	placeEnterBlock = block;

	placeEnterTimer = setTimeout(() => {

		placeEnterTimer = null;
		placeEnterBlock = null;

		placeHoverTake(block);

	}, PLACE_HOVER_DELAY);

});

document.delegateEventListener('mouseleave', '.place-garden-block-season', function() {

	if(placeEnterBlock === this) {
		placeEnterStop();
	}

	if(placeHover === this) {
		placeHoverLeave();
	}

});

document.delegateEventListener('mouseenter', '.place-back, .place-drop', function() {
	placeEnterStop();
	placeHoverStop();
});

document.delegateEventListener('mouseleave', '.place-back, .place-drop', function() {
	placeHoverLeave();
});

document.delegateEventListener('click', '.place-drop', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	SeriesPanel.series = this.dataset.pillSeries;
	SeriesPanel.removeBed(this.dataset.bed, this.placeBlock, this.dataset.pillTask || null);

});

document.delegateEventListener('click', '.place-length', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	SeriesChip.open(this);

});

document.delegateEventListener('click', '.place-fill', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	qsa('.place-fill-live', node => node.classList.remove('place-fill-live'));
	this.classList.add('place-fill-live');

	SeriesChip.open(this);

});

document.addEventListener('pointerdown', function(e) {

	if(e.target.closest('.place-fill') === null) {
		qsa('.place-fill-live', node => node.classList.remove('place-fill-live'));
	}

});

document.delegateEventListener('mouseenter', '.place-back', function() {

	qsa('.place-garden-block[data-series="'+ this.dataset.pillSeries +'"]', block => block.classList.add('place-hovered'));

});

document.delegateEventListener('mouseleave', '.place-back', function() {

	qsa('.place-hovered', block => block.classList.remove('place-hovered'));

});

document.delegateEventListener('click', '.place-back', function(e) {

	e.preventDefault();
	e.stopPropagation();

	SeriesHidden.toggle(this.dataset.pillSeries, this.dataset.bed ?? this.placeBlock?.dataset.bed);

	this.remove();

});

document.addEventListener('pointermove', function(e) {

	if(SeriesDrag.pending === null) {
		return;
	}

	const element = (e.target.closest === undefined) ? document.elementFromPoint(e.clientX, e.clientY) : e.target;

	SeriesDrag.hovered = (element === null) ? null : element.closest('.garden-col[data-bed], .bed-item-grid[data-bed]');

});

document.delegateEventListener('click', '.garden-col[data-bed], .bed-item-grid[data-bed]', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	if(SeriesDrag.applyPending(this)) {
		e.preventDefault();
		e.stopPropagation();
	}

});

document.addEventListener('keydown', function(e) {

	// Un panneau ouvert depuis la fiche se ferme seul au keyup (`closeEscape`) : Échap ne ferme alors que lui
	if(
		e.key === 'Escape' &&
		SeriesPlace.editing === false &&
		qs('.panel.open') === null
	) {
		SeriesPlaceSheet.close();
	}

	if(SeriesPlace.editing === false) {
		return;
	}

	if(e.key === 'Escape') {

		const hadSize = (qs('#place-size') !== null);

		SeriesPanel.close();
		SeriesSize.close();
		SeriesDrag.cancelPending();

		if(hadSize) {
			return;
		}

		SeriesResize.clearSelection();

		return;

	}

	if(qs('#zone-container') === null) {
		return;
	}

	const field = (e.target.closest === undefined) ? null : e.target.closest('input, textarea, select');

	if(field !== null) {
		return;
	}

	if(e.key === 'ArrowDown' || e.key === 'ArrowUp') {

		const cards = [...qsa('#series-selector-list .series-selector-cultivation:not(.hide):not(.series-selector-closed)')];

		if(cards.length === 0) {
			return;
		}

		e.preventDefault();

		const current = cards.findIndex(card => card.classList.contains('selected'));
		const step = (e.key === 'ArrowDown') ? 1 : -1;

		const next = (current === -1) ?
			(step === 1 ? 0 : cards.length - 1) :
			Math.max(0, Math.min(cards.length - 1, current + step));

		cards[next].scrollIntoView({block: 'nearest'});
		SeriesSelector.select(cards[next].dataset.cultivation);

		return;

	}

	if(e.key === 'Enter') {

		qs('#place-panel .place-panel-open', node => {
			e.preventDefault();
			node.click();
		});

		return;

	}

	if((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'c') {

		if(SeriesResize.series === null) {
			return;
		}

		e.preventDefault();

		SeriesDrag.prepare(SeriesResize.series, 'copy');

		return;

	}

	if((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'v') {

		if(SeriesDrag.pending === null) {
			return;
		}

		e.preventDefault();

		SeriesDrag.applyPending(SeriesDrag.hovered);

		return;

	}

	if(e.key === 'Delete' || e.key === 'Backspace') {

		if(SeriesPanel.series === null || SeriesPanel.bed === null) {
			return;
		}

		e.preventDefault();

		SeriesPanel.removeBed(SeriesPanel.bed);

	}

});

const placeKeepArmed = [
	'#series-selector', '#place-panel', '#place-size', '#place-jumpers', '#place-filter',
	'.util-page-band', '.util-action', '.tabs-item', '.dropdown-list', '[data-dropdown]',
	'.place-pose', '.place-selected-label', '.place-back', '.place-drop', '.place-length',
	'.place-fill-live', '.place-bed-panel', '.place-many', '.place-hidden-count',
	'.place-zone-bar-outside',
	'.garden-col[data-hide="1"]', '.bed-item-grid[data-hide="1"]',
	'.plot-folded', '.garden-plot-title',
].join(', ');

const placePlanParts = [
	'.place-garden-block', '.place-grid-series-timeline',
	'.place-nodate-line', '.place-handle', '.place-fill-live',
].join(', ');

window.addEventListener('click', function(e) {

	// Un bloc d'une autre saison ne s'assole pas ici : seule la lecture l'ouvre, sur sa fiche
	const block = e.target.closest(SeriesPlace.editing ?
		'.place-garden-block-season, .place-grid-series-timeline-season, .place-garden-block-task, .place-grid-series-timeline-task' :
		'.place-garden-block-season, .place-grid-series-timeline-season, .place-garden-block-not-season, .place-grid-series-timeline-not-season, .place-garden-block-task, .place-grid-series-timeline-task');

	if(block === null) {

		qsa('.place-front', node => node.classList.remove('place-front'));

		// Cliquer dans le vide referme la fiche : sans cela on restait retenu sur une
		// culture sans plus rien a cliquer pour en sortir.
		// Le panneau assole un element precis : il est arme par nature, et rien n'y
		// designe ce qu'on assolerait ensuite. Y desarmer ferait disparaitre les
		// boutons Ajouter sans qu'aucun geste ne puisse les ramener.
		if(
			SeriesDrag.moved === false &&
			SeriesResize.moved === false &&
			SeriesPlace.editing &&
			SeriesPlace.series !== null &&
			qs('#place-update') === null &&
			e.target.closest(placeKeepArmed) === null
		) {
			SeriesSelector.deselect();
		}

		return;

	}

	// La jauge n'est exclue qu'en edition, ou son clic ouvre le reglage de longueur.
	// En lecture ce clic ne fait rien : l'exclure y laissait un trou au milieu d'un
	// bloc que le mode vient de rendre cliquable, avec un curseur qui promettait un
	// geste inexistant.
	const inerte = SeriesPlace.editing ?
		'.place-back, .place-drop, .place-fill, .place-selected-pill' :
		'.place-back, .place-drop, .place-selected-pill';

	if(e.target.closest(inerte) !== null) {
		return;
	}

	if(block.classList.contains('place-hidden')) {

		e.preventDefault();
		e.stopPropagation();

		SeriesHidden.toggle(block.dataset.series, block.dataset.bed);

		return;

	}

	// Un bloc verrouille ne s'arme pas : serie cloturee, perenne, ou saison voisine.
	// En lecture le clic mene quand meme a la serie, consulter n'est pas modifier.
	// Le clic le fait aussi passer devant ses voisins, seul moyen de lire un bloc
	// recouvert que rien d'autre ne peut retenir.
	if(SeriesPlace.editing && block.dataset.locked === '1') {
		e.preventDefault();
		e.stopPropagation();
		qsa('.place-front', node => node.classList.remove('place-front'));
		block.classList.add('place-front');
		SeriesPlace.paintLockedBar(block);
		return;
	}

	// Sur le panneau d'assolement, un bloc qui n'appartient pas a ce qu'on assole est
	// inerte : ni consulte, ni arme. Le clic s'arrete la plutot que d'emmener ailleurs
	// une page ouverte pour placer une seule serie.
	// SeriesDrag.moved d'abord : un deplacement se termine par un clic, et sans ce
	// test le bloc qu'on vient de poser comptait pour un clic sur un bloc etranger.
	if(SeriesDrag.moved === false && placeBlockForeign(block)) {
		e.preventDefault();
		e.stopPropagation();
		return;
	}

	// En lecture, le clic ouvre la fiche de la serie sur le plan d'assolement, ou
	// mene a la serie ailleurs. Ce handler avale le clic pour tout le
	// reste de la page : sans cette branche, un plan en lecture serait inerte.
	// Elle passe APRES le retour d'arriere-plan : un bloc mis a l'arriere-plan doit
	// pouvoir en revenir, c'est la seule chose que le mode lecture garde.
	if(SeriesPlace.editing === false) {

		e.preventDefault();
		e.stopPropagation();

		if(block.dataset.task !== undefined) {
			new Ajax.Navigation(block)
				.url('/tache/'+ block.dataset.task)
				.fetch();
		} else if(block.dataset.series !== undefined && qs('#place-sheet') !== null) {
			SeriesPlaceSheet.open(block);
		} else if(block.dataset.series !== undefined) {
			new Ajax.Navigation(block)
				.url('/serie/'+ block.dataset.series)
				.fetch();
		}

		return;

	}

	if(SeriesDrag.moved || SeriesResize.moved) {
		e.preventDefault();
		e.stopPropagation();
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	if(SeriesDrag.applyPending(block.closest('.garden-col[data-bed], .bed-item-grid[data-bed]'))) {
		return;
	}

	if(e.shiftKey) {
		SeriesResize.toggle(block);
		return;
	}

	// L'appui long vient de retenir cette planche : le clic qui le termine ne
	// doit pas la relacher aussitot. Un clic un peu appuye depasse les 400 ms.
	if(placePressDone) {
		placePressDone = false;
		return;
	}

	const target = e.altKey ? (SeriesResize.getBlockBelow(e, block) ?? block) : block;

	// Une bascule est en vol : le clic reflexe est ignore plutot que de defaire ou de
	// se perdre dans un plan qui va etre remplace.
	if(SeriesPlace.switching) {
		return;
	}

	if(target.classList.contains('place-selected')) {
		return;
	}

	SeriesResize.select(target, e);

	SeriesSelector.focusSeries(target.dataset.series, target);

}, true);

window.addEventListener('pointerdown', function(e) {

	// Tout ce qui fait partie du geste en cours. Les pastilles surtout : les
	// effacer au pointerdown retirait la corbeille du document avant que son clic
	// n'ait le temps de partir.
	if(e.target.closest(placeKeepArmed) !== null) {
		return;
	}

	// Le mode Poser tient tant que la serie est retenue dans le bac : seul Fermer
	// y met fin. Le fermer sur un clic ailleurs le faisait disparaitre sans qu'on
	// l'ait demande.

	if(
		e.target.closest(placePlanParts) === null &&
		(
			qs('.place-selected') !== null ||
			document.body.classList.contains('place-selecting') ||
			SeriesPlace.series !== null
		)
	) {

		SeriesPanel.close();

		// deselect() et non clearSelection() : le premier clic doit rendre le plan
		// entier, fiche du bac et filtre de planches compris. clearSelection() ne
		// retirait que les poignees, l'armement restait.
		// En mode Poser au doigt, la feuille de pose ne tient qu'a l'armement : elle
		// ne se ferme que par Fermer, arbitrage du lot tactile.
		if(qs('.place-pose') === null && qs('#place-update') === null) {
			SeriesSelector.deselect();
		} else {
			SeriesResize.clearSelection();
		}

	}

}, true);

// Le doigt fait defiler le jardin sur les deux axes ; c'est seulement une fois
// le bloc pris que le defilement est retenu. Le retenir en amont (touch-action:
// pan-y sur les blocs) interdisait tout defilement horizontal du plan tant
// qu'aucune serie n'etait retenue, les blocs couvrant presque toute la surface.
// Le bloc se prend apres 400 ms sans bouger : le defilement n'a donc pas
// commence quand ce garde s'applique, et preventDefault suffit a l'empecher.
// Et des le bloc pris, avant tout deplacement : le premier touchmove laissait le
// navigateur decider du defilement, qui annulait le pointeur et donc la glisse.
document.addEventListener('touchmove', function(e) {

	if(
		document.body.classList.contains('bed-dragging') ||
		document.body.classList.contains('bed-resizing') ||
		SeriesResize.current !== null ||
		(SeriesDrag.current !== null && SeriesDrag.current.ready)
	) {
		e.preventDefault();
	}

}, {passive: false});

document.addEventListener('pointermove', e => SeriesResize.move(e));
document.addEventListener('pointerup', () => SeriesResize.end());
document.addEventListener('pointercancel', () => SeriesResize.end());

// L'appui long au doigt, c'est le geste qui retient un bloc ; c'est aussi celui qui
// ouvre le menu contextuel du navigateur, lequel annule le pointeur et donc le
// geste. Mesure : le menu sort a 500 ms, le bloc se prend a 400 ms, le doigt ne se
// deplace pas encore. contextmenu ne dit pas d'ou il vient, on garde la trace du
// dernier pointeur pose.
let placeLastPointerType = 'mouse';

document.addEventListener('pointerdown', e => placeLastPointerType = e.pointerType, true);

document.addEventListener('contextmenu', function(e) {

	if(placeLastPointerType === 'mouse') {
		return;
	}

	if(e.target.closest('#zone-container, #series-selector-list, #place-tokens') !== null) {
		e.preventDefault();
	}

});

const PLACE_PRESS_DELAY = 400;
const PLACE_PRESS_MOVE = 8;

let placePressTimer = null;
let placePressDone = false;
let placePressX = 0;
let placePressY = 0;

const placePressCancel = () => {

	if(placePressTimer !== null) {
		clearTimeout(placePressTimer);
		placePressTimer = null;
	}

};

const placePressReset = () => {

	placePressCancel();
	placePressDone = false;

};

document.addEventListener('pointerup', placePressCancel);
document.addEventListener('pointercancel', placePressCancel);

document.addEventListener('pointermove', e => {

	if(
		placePressTimer !== null &&
		(Math.abs(e.clientX - placePressX) > PLACE_PRESS_MOVE || Math.abs(e.clientY - placePressY) > PLACE_PRESS_MOVE)
	) {
		placePressCancel();
	}

}, {passive: true});

document.delegateEventListener('pointerdown', '.place-garden-block-season, .place-grid-series-timeline-season', function(e) {

	if(SeriesPlace.editing === false) {

		if(
			SeriesPlace.writable === false ||
			e.target.closest('.place-handle, .place-fill-live, .place-back, .place-drop, .place-pose') !== null ||
			this.dataset.locked === '1' ||
			this.dataset.series === undefined
		) {
			return;
		}

		const block = this;
		const series = this.dataset.series;

		placePressReset();

		placePressX = e.clientX;
		placePressY = e.clientY;

		placePressTimer = setTimeout(() => {

			placePressTimer = null;
			placePressDone = true;

			const shown = SeriesPlace.setEditing(true);
			const arme = () => SeriesSelector.focusSeries(series, block);

			(shown instanceof Promise) ? shown.then(arme) : arme();

		}, PLACE_PRESS_DELAY);

		return;

	}

	if(e.target.closest('.place-handle, .place-fill-live, .place-back')) {
		return;
	}

	if(this.dataset.locked === '1') {
		return;
	}

	if(placeBlockForeign(this)) {
		return;
	}

	if(this.classList.contains('place-selected')) {
		SeriesDrag.start(this, e, 'plan');
		return;
	}

	const block = this;
	const point = {clientX: e.clientX, clientY: e.clientY, button: 0, pointerType: e.pointerType};

	placePressReset();

	placePressX = e.clientX;
	placePressY = e.clientY;

	placePressTimer = setTimeout(() => {

		placePressTimer = null;
		placePressDone = true;

		SeriesResize.select(block);
		SeriesDrag.start(block, point, 'plan');

		if(SeriesDrag.current !== null) {
			SeriesDrag.current.ready = true;
		}

	}, PLACE_PRESS_DELAY);

});

document.delegateEventListener('pointerdown', '.place-nodate-line', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	if(this.classList.contains('selected') === false) {
		return;
	}

	SeriesDrag.start(this, e, 'plan');

});

document.delegateEventListener('pointerdown', '.series-selector-cultivation:not(.series-selector-closed)', function(e) {

	if(SeriesPlace.editing === false) {
		return;
	}

	if(e.target.closest('a, button, input, select, label')) {
		return;
	}

	if(SeriesDrag.hasDates(this) === false) {
		return;
	}

	SeriesDrag.start(this, e, 'selector');

});

let placeJumperFrame = null;

window.addEventListener('scroll', function() {

	if(placeJumperFrame !== null) {
		return;
	}

	placeJumperFrame = requestAnimationFrame(() => {
		placeJumperFrame = null;
		SeriesResize.paintJumpers();
	});

}, true);

document.addEventListener('pointermove', e => SeriesDrag.move(e));
document.addEventListener('pointerup', () => SeriesDrag.end());
document.addEventListener('pointercancel', () => SeriesDrag.end());

class SeriesSoilMenu {

	static fallback = ['#8a7f6d', '#4e9a06', '#c4a000'];

	static paint(menu) {

		const cards = [...qsa('#series-selector-list .series-selector-cultivation')].slice(0, 3);

		qsa('.util-soil-mode', mode => {

			const preview = mode.qs('.util-soil-preview');

			if(preview === null) {
				return;
			}

			preview.innerHTML = '';

			for(let i = 0; i < 3; i++) {

				const card = cards[i];
				const source = (mode.dataset.mode === 'family') ? '--series-paint-family' : '--series-paint-plant';
				const color = card?.style.getPropertyValue(source)?.trim() || this.fallback[i];
				const chip = document.createElement('i');

				if(mode.dataset.mode === 'white') {
					chip.style.backgroundColor = 'white';
					chip.style.borderColor = color;
				} else {
					chip.style.backgroundColor = color;
				}

				preview.append(chip);

			}

		});

	}

}

document.addEventListener('dropdownAfterShow', function(e) {

	const list = e.detail?.list;

	if(list !== undefined && list !== null && list.classList.contains('util-soil-menu')) {
		SeriesSoilMenu.paint(list);
	}

}, true);

class SeriesPlacement {

	static applyFilters(beds) {

		Object.entries(beds).forEach(([bed, attributes]) => {

			const line = qs('#bed-line-'+ bed);

			if(line === null) {
				return;
			}

			this.setAttributes(line, attributes);

		});

	}

	static apply(state) {

		this.clear();

		// Le plan porte la serie qu'il arme, comme le rendu serveur le fait.
		qs('#zone-container', node => {

			if(state.series === null) {
				node.removeAttribute('data-series');
			} else {
				node.dataset.series = state.series;
			}

		});

		if(state.series === null) {
			document.body.classList.remove('bed-updating');
			return;
		}

		qsa('#zone-container .place-grid-series-timeline[data-series="'+ state.series +'"]', block => block.classList.add('place-current'));

		Object.entries(state.beds).forEach(([bed, data]) => this.paintBed(bed, data, state.ghost));

		this.syncPosing();

		document.body.classList.add('bed-updating');

	}

	static clear() {

		qsa('.place-pose', node => node.remove());
		qsa('#zone-container .place-current', node => node.classList.remove('place-current'));
		qsa('.place-grid-series-timeline-placeholder', node => node.remove());

		qsa('.bed-item-places[data-placement-added]', node => node.remove());

		qsa('.place-grid-series-timeline-lines[data-rest-height]', lines => {
			lines.style.height = lines.dataset.restHeight;
			lines.removeAttribute('data-rest-height');
		});

		qsa('.bed-item-grid[data-bed]', line => {

			line.classList.toggle('hide', line.dataset.emptyFill === '1');

			this.testAttributes.forEach(name => line.removeAttribute(name));

		});

		this.syncPosing();

	}

	static testAttributes = ['data-free', 'data-rest', 'data-reason', 'data-hide', 'data-mismatch', 'data-rotation', 'data-same-width', 'data-greenhouse'];

	// N'ecrire que ce qui change : entre deux series, la plupart des attributs de
	// test d'une planche sont identiques.
	static setAttributes(node, attributes) {

		Object.entries(attributes).forEach(([name, value]) => {

			if(node.getAttribute(name) !== value) {
				node.setAttribute(name, value);
			}

		});

	}

	// Le pendant de apply() pour la vue Plan : le jardin reste en place, seuls la serie
	// armee, les attributs de test et les fragments de pose changent.
	static applyGarden(state) {

		this.clearGarden();

		const container = qs('#zone-container');

		if(container === null) {
			return;
		}

		if(state.series === null && state.task === null) {
			document.body.classList.remove('bed-updating');
			return;
		}

		if(state.series !== null) {
			container.dataset.series = state.series;
		}

		if(state.task !== null) {
			container.dataset.task = state.task;
		}

		if(state.paint !== null) {
			this.setPaint(container, state.paint);
		}

		const armed = (state.task !== null) ? '[data-task="'+ state.task +'"]' : '[data-series="'+ state.series +'"]';

		container.qsa('.place-garden-block'+ armed, block => block.classList.add('place-current'));

		Object.entries(state.beds).forEach(([bed, data]) => this.paintGardenBed(bed, data, state));

		this.syncPosing();

		document.body.classList.add('bed-updating');

	}

	static clearGarden() {

		const container = qs('#zone-container');

		if(container === null) {
			return;
		}

		container.qsa('.place-pose, .place-garden-block-placeholder', node => node.remove());
		container.qsa('.place-current', node => node.classList.remove('place-current'));

		container.qsa('.garden-col-width-alert', width => {

			width.classList.remove('garden-col-width-alert');

			const icon = width.qs('.asset-icon-exclamation-circle');

			if(icon === null) {
				return;
			}

			if(icon.nextSibling !== null && icon.nextSibling.nodeType === Node.TEXT_NODE) {
				icon.nextSibling.data = icon.nextSibling.data.replace(/^ /, '');
			}

			icon.remove();

		});

		container.qsa('.garden-col[data-bed]', col => this.testAttributes.forEach(name => col.removeAttribute(name)));

		container.removeAttribute('data-series');
		container.removeAttribute('data-task');

		this.syncPosing();

	}

	// --target-paint est heritee par tout le plan : la reecrire a l'identique ou la
	// retirer pour la reposer faisait recalculer le style de 8 000 a 13 000 noeuds,
	// 40 a 90 ms par ecriture sur la ferme 7. On n'ecrit que ce qui change.
	static setPaint(container, paint) {

		const current = container.style.getPropertyValue('--target-paint').trim();

		if(paint === null || paint === '') {

			if(current !== '') {
				container.style.removeProperty('--target-paint');
			}

		} else if(current !== paint.trim()) {
			container.style.setProperty('--target-paint', paint.trim());
		}

	}

	static paintGardenBed(bed, data, state) {

		const col = qs('#bed-line-'+ bed);

		if(col === null) {
			return;
		}

		this.setAttributes(col, data.a);

		if(data.w) {

			col.qs('.garden-col-width', width => {
				width.classList.add('garden-col-width-alert');
				width.insertAdjacentHTML('afterbegin', state.alert +' ');
			});

		}

		if(data.p !== null && state.slice !== null) {

			col.qs('.garden-col-body', body => body.insertAdjacentHTML('beforeend',
				'<div class="place-garden-block place-garden-block-placeholder" style="top: '+ state.slice.top +'%; height: '+ state.slice.height +'%"></div>'
				+ '<a class="place-pose" data-bed="'+ bed +'"'+ data.p +' style="top: '+ state.slice.top +'%">'+ state.label +'</a>'
			));

		}

	}

	// Une classe tenue ici plutot que #zone-container:has(.place-pose) en CSS :
	// Firefox reevaluait ce :has a chaque insertion ou retrait d'un « + Ajouter »
	// en rebalayant tout le plan. Mesure le 04/09/2026 sur gaec du goupil (30 000
	// noeuds, 500 planches) : la selection d'une serie passait de 40 s a 1,4 s.
	static syncPosing() {

		qs('#zone-container', node => node.classList.toggle('place-posing', node.querySelector('.place-pose') !== null));

	}

	static paintBed(bed, data, ghost) {

		const line = qs('#bed-line-'+ bed);

		if(line === null) {
			return;
		}

		this.setAttributes(line, data.a);

		line.classList.toggle('hide', data.h);

		let places = line.qs('.bed-item-places');

		if(places === null && (data.p !== null || data.g !== null)) {
			line.insertAdjacentHTML('beforeend', '<div class="bed-item-places" data-placement-added="1"></div>');
			places = line.qs('.bed-item-places');
		}

		if(places !== null) {

			if(data.l > 0) {
				places.qs('.place-grid-series-timeline-lines', lines => {
					lines.dataset.restHeight = lines.style.height;
					lines.style.height = data.l +'rem';
				});
			}

			if(data.p !== null) {
				places.insertAdjacentHTML('beforeend', '<a class="place-pose place-pose-timeline'+ (data.p.faint ? ' place-pose-faint' : '') +'" data-bed="'+ bed +'"'+ (data.p.confirm ?? '') +' style="left: '+ data.p.left +'%">'+ data.p.label +'</a>');
			}

			if(data.g !== null && ghost !== null) {
				this.paintGhost(places, data.g, ghost);
			}

		}

	}

	static paintGhost(places, height, ghost) {

		let lines = places.qs('.place-grid-series-timeline-lines');

		if(lines === null) {
			places.insertAdjacentHTML('afterbegin', '<div class="place-grid-series-timeline-lines" style="height: '+ height +'rem"></div>');
			lines = places.qs('.place-grid-series-timeline-lines');
		}

		lines.insertAdjacentHTML('beforeend',
			'<div class="place-grid-series-timeline place-grid-series-timeline-placeholder bed-write" style="height: '+ height +'rem; top: 0rem;'+ (ghost.color ? '--block-color: '+ ghost.color +';' : '') +'left: '+ ghost.left +'%; width: '+ ghost.width +'%" data-ajax-navigation="notouch"></div>'
		);

	}

}

// Le plan retrouve a chaque arrivee le mode dans lequel on l'a laisse : il ne se
// referme que sur un geste explicite.
const placeReadingArm = () => {

	SeriesPlace.syncUpdate();

	// Tenue ici plutot qu'un html:has(#zone-container) en CSS, que Firefox reevaluait
	// a chaque insertion dans la page.
	document.body.classList.toggle('place-plan-shown', qs('#zone-container') !== null);

	if(qs('#zone-container') === null) {

		qs('#place-tokens', node => node.remove());
		SeriesPlace.syncTokens();
		qs('#place-pose-bar', node => node.remove());
		qs('#place-hidden-bar', node => node.remove());
		qs('#place-many', node => node.remove());

		document.body.style.removeProperty('--place-tokens-height');
		document.body.style.removeProperty('--place-pose-height');
		document.body.style.removeProperty('--place-hidden-height');

		return;

	}

	const shown = SeriesPlace.restoreEditing();

	SeriesPlacement.syncPosing();

	SeriesScroll.restore();
	SeriesScroll.restorePage();

	const arme = () => {
		SeriesPlace.rememberVocabulary();
		SeriesPlace.restoreArmed();
		SeriesPlace.paintTokens();
		SeriesPlace.paintPoseBar();
	};

	if(shown instanceof Promise) {
		shown.then(arme);
	} else {
		arme();
	}

};

document.delegateEventListener('click', '#place-pose-bar [data-pose-open]', function(e) {

	e.stopPropagation();

});

document.delegateEventListener('click', '#place-pose-bar [data-pose-close]', function(e) {

	e.preventDefault();
	e.stopPropagation();

	SeriesSelector.deselect();

});

document.delegateEventListener('click', '.garden-plot .garden-plot-title', function(e) {

	if(e.target.closest('a, button, [data-ajax], [data-dropdown]') !== null) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	SeriesPlace.foldPlot(this);

});

document.delegateEventListener('click', '#place-tokens [data-token-search]', function(e) {

	e.preventDefault();
	e.stopPropagation();

	SeriesPlace.toggleSearch();

});

document.delegateEventListener('click', '#place-tokens .place-token:not([data-token-search])', function(e) {

	e.preventDefault();
	e.stopPropagation();

	if(this.dataset.plant !== undefined) {
		SeriesPlace.tokenPlant = this.dataset.plant;
		SeriesPlace.paintTokens();
		return;
	}

	if(this.dataset.tokenBack !== undefined) {
		SeriesPlace.tokenPlant = null;
		SeriesPlace.paintTokens();
		return;
	}

	if(this.dataset.cultivation !== undefined) {
		SeriesSelector.select(this.dataset.cultivation);
	}

});

document.delegateEventListener('omniboxsuggest', '#place-filter', function() {
	UtilOmnibox.setSuggestions(this, SeriesPlace.filterSuggestions());
});

document.delegateEventListener('omnibox', '#place-filter', function() {

	SeriesPlace.filterApply(this);

	SeriesPlace.filterScroll();

});

window.matchMedia(SeriesPlace.tokensMedia).addEventListener('change', () => {
	SeriesPlace.paintTokens();
	SeriesPlace.paintPoseBar();
});

document.addEventListener('DOMContentLoaded', placeReadingArm);
document.addEventListener('navigation.wakeup', placeReadingArm);

// Lime.Panel emet ces evenements sur le panneau, sans bubbles : ecoute en capture.
document.addEventListener('panelAfterPaint', () => SeriesPlace.syncUpdate(), true);
document.addEventListener('panelAfterShow', () => SeriesPlace.syncUpdate(), true);
document.addEventListener('panelClose', e => SeriesPlace.syncUpdate(e.target), true);
document.delegateEventListener('click', '#series-selector-list .series-selector-cultivation:not(.selected)', function(e) {

	if(e.target.closest('a, button, [data-ajax], [data-dropdown], .series-selector-header') !== null) {
		return;
	}

	const cultivation = this.dataset.cultivation;

	if(cultivation !== undefined) {
		SeriesSelector.select(cultivation);
	}

});

document.addEventListener('navigation.sleep', () => {

	SeriesScroll.rememberAll();

	// Le panneau de longueur est pose sur le body, hors du contenu que la navigation
	// remplace : sans cela il survivait a la page qui l'avait ouvert et restait affiche
	// par-dessus la serie qu'on venait consulter. Ses quatre freres flottants sont deja
	// retires par placeReadingArm ; celui-la y avait ete oublie.
	SeriesSize.close();

});
document.addEventListener('navigationStart', () => {
	SeriesScroll.rememberPage();
	SeriesPlace.rememberArmed();
});

// La bulle d'un bloc : son contenu arrive par une requete, mise en cache par
// serie et par planche. Elle ne s'ouvre qu'en lecture, ou le bloc est justement
// devenu cliquable et ou aucun geste ne dispute le survol.
class SeriesTip {

	static node = null;
	static cache = new Map();
	static timer = null;
	static key = null;

	static getNode() {

		if(this.node === null || this.node.isConnected === false) {
			document.body.insertAdjacentHTML('beforeend', '<div id="place-tip" class="util-tip"></div><div id="place-tip-stage" class="hide"></div>');
			this.node = qs('#place-tip');
		}

		return this.node;

	}

	static hide() {

		clearTimeout(this.timer);
		this.key = null;

		if(this.node !== null) {
			this.node.classList.remove('util-tip-open', 'util-tip-above', 'util-tip-below');
		}

	}

	static show(block) {

		const series = block.dataset.series;

		if(series === undefined) {
			return;
		}

		const key = series;

		this.key = key;

		clearTimeout(this.timer);

		this.timer = setTimeout(() => {

			if(this.key !== key) {
				return;
			}

			const node = this.getNode();

			if(this.cache.has(key)) {
				node.innerHTML = this.cache.get(key);
				this.place(block);
				return;
			}

			new Ajax.Query()
				.url('/series/place:tipAi?series='+ series)
				.method('get')
				.fetch()
				.then(() => {

					const stage = qs('#place-tip-stage');
					const rendu = stage?.innerHTML ?? '';

					// La reponse a pu arriver apres qu'on a quitte le bloc, ou pour une
					// autre serie : sans ces deux gardes elle ecrivait le contenu d'une
					// serie sur la bulle d'une autre.
					if(rendu === '' || stage.qs('[data-series="'+ series +'"]') === null) {
						return;
					}

					// Une reponse vide ne se met pas en cache : le bloc resterait muet
					// pour de bon, sans jamais reessayer.
					this.cache.set(key, rendu);

					if(this.key === key) {
						node.innerHTML = rendu;
						this.place(block);
					}

				});

		}, 260);

	}

	static place(block, margin = 8) {

		const node = this.getNode();
		const rect = block.getBoundingClientRect();

		node.classList.add('util-tip-open');

		// Mesurer puis ecrire : la hauteur de la bulle n'est connue qu'une fois
		// affichee, et la lire apres avoir pose sa position forcerait une seconde
		// mise en page.
		const height = node.offsetHeight;
		const width = node.offsetWidth;

		// Le retournement se decide sur les DEUX bords, pas seulement le haut : un bloc
		// qui commence pres du sommet et finit sous la ligne de flottaison poussait la
		// bulle hors de l'ecran, et comme elle est en position fixe aucun defilement
		// ne la ramenait.
		const room = document.documentElement.clientHeight;
		const fitsAbove = (rect.top > height + 16);
		const fitsBelow = (rect.bottom + height + 16 < room);

		const above = fitsAbove && (fitsBelow === false || rect.top > room - rect.bottom);

		node.classList.toggle('util-tip-above', above);
		node.classList.toggle('util-tip-below', above === false);

		let left = rect.left;

		const span = document.documentElement.clientWidth;

		if(left + width > span - margin) {
			left = span - width - margin;
		}

		left = Math.max(margin, left);

		let top = above ? rect.top - height - 10 : rect.bottom + 10;

		// Dernier garde : si aucun des deux cotes ne tient, la bulle reste dans la
		// fenetre plutot que de sortir par le bas ou par le haut.
		top = Math.min(Math.max(8, top), Math.max(8, room - height - 8));

		node.style.left = left +'px';
		node.style.top = top +'px';
		node.style.setProperty('--tip-arrow', Math.min(Math.max(rect.left - left + 12, 12), width - 24) +'px');

	}

}

document.delegateEventListener('mouseenter', '.place-garden-block-season, .place-grid-series-timeline-season, .place-garden-block-not-season, .place-grid-series-timeline-not-season', function() {

	// En edition le survol appartient aux gestes : aucune bulle.
	if(SeriesPlace.editing) {
		return;
	}

	// Un bloc d'une autre saison n'a rien a dire en lecture : il n'est pas de la
	// saison qu'on regarde.
	if(this.classList.contains('place-garden-block-not-season') || this.classList.contains('place-grid-series-timeline-not-season')) {
		return;
	}

	SeriesTip.show(this);

});

document.delegateEventListener('mouseleave', '.place-garden-block-season, .place-grid-series-timeline-season, .place-garden-block-not-season, .place-grid-series-timeline-not-season', () => SeriesTip.hide());
document.addEventListener('navigation.wakeup', () => {
	SeriesTip.hide();
	SeriesTip.cache.clear();
});
// scroll ne remonte pas : l'ecoute se fait en capture pour attraper aussi le
// defilement horizontal des grilles, qui laissait la bulle derriere.
document.addEventListener('scroll', () => SeriesTip.hide(), {passive: true, capture: true});

class SeriesHelper {

	static node = null;
	static key = null;
	static placedAt = 0;

	static getSource() {
		return qs('#place-helper');
	}

	static getAnchor() {

		const container = qs('#zone-container');

		if(container === null) {
			return null;
		}

		const blocks = container.qsa('[data-series]:not(.place-hidden)');

		for(const block of blocks) {

			const rect = block.getBoundingClientRect();

			if(
				rect.width > 0 &&
				rect.height > 0 &&
				rect.bottom > 0 &&
				rect.top < window.innerHeight
			) {
				return block;
			}

		}

		return null;

	}

	static show() {

		const source = this.getSource();

		if(source === null) {
			return;
		}

		const anchor = this.getAnchor();

		if(anchor === null) {
			return;
		}

		this.key = source.dataset.helper;

		if(this.node === null) {
			document.body.insertAdjacentHTML('beforeend', '<div id="place-helper-tip" class="util-tip"></div>');
			this.node = qs('#place-helper-tip');
		}

		this.node.innerHTML = '<div class="place-tip-body">'+ source.innerHTML +'</div>';

		SeriesTip.place.call(this, anchor, this.getGutter());

	}

	static getGutter() {

		return parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

	}

	static hide() {

		if(this.node !== null) {
			this.node.classList.remove('util-tip-open', 'util-tip-above', 'util-tip-below');
		}

	}

	static getNode() {
		return this.node;
	}

	static replace() {

		if(this.node === null || this.node.classList.contains('util-tip-open') === false) {
			return;
		}

		const now = Date.now();

		if(now - this.placedAt < 16) {
			return;
		}

		this.placedAt = now;

		const anchor = this.getAnchor();

		if(anchor === null) {
			this.hide();
			return;
		}

		SeriesTip.place.call(this, anchor, this.getGutter());

	}

	static learn() {

		if(this.key === null) {
			return;
		}

		this.hide();
		this.key = null;

		const source = this.getSource();

		if(source !== null) {
			source.remove();
		}

	}

}

document.addEventListener('scroll', () => SeriesHelper.replace(), {passive: true, capture: true});
window.addEventListener('resize', () => SeriesHelper.replace(), {passive: true});

/**
 * La fiche d'une série sur le plan d'assolement en lecture (`PlaceSheetUi`) : un clic sur un bloc l'ouvre sur sa série,
 * et un clic sur un autre bloc la remplace. Elle se ferme par la croix, par Échap et au passage en édition. Son corps est
 * celui du plan 3D (`SeriesSheet`), rendu au jour que pose le serveur.
 */
class SeriesPlaceSheet {

	static open(block) {

		const sheet = qs('#place-sheet');

		qsa('.place-sheet-selected', node => node.classList.remove('place-sheet-selected'));
		block.classList.add('place-sheet-selected');

		new Ajax.Query()
			.method('get')
			.url('/series/place:sheet?'+ new URLSearchParams({ id: sheet.dataset.structure, series: block.dataset.series }))
			.fetch();

	}

	static render(body) {

		SceneLoader.loadStage(body).then(module => {

			// La fiche a pu être fermée ou remplacée pendant le chargement du calendrier
			if(body.isConnected === false) {
				return;
			}

			const plan = JSON.parse(body.dataset.sheet);

			new SeriesSheet({ texts: JSON.parse(body.dataset.texts), plan, stage: module.sceneCreateStage() })
				.render(body, plan.series[0].id, body.dataset.date);

			// La hauteur de la fiche n'est connue qu'une fois son corps rendu
			qs('.place-sheet-selected', block => SeriesPlaceSheet.reveal(block));

		});

	}

	// Au téléphone, la fiche est posée en bas de l'écran : le bloc touché remonte au-dessus d'elle. Les deux mesures
	// précèdent le défilement, qui seul écrit.
	static reveal(block) {

		const sheet = qs('#place-sheet');

		if(sheet === null || window.matchMedia('(max-width: 575px)').matches === false) {
			return;
		}

		const top = sheet.getBoundingClientRect().top;
		const bottom = block.getBoundingClientRect().bottom;

		if(bottom > top) {
			window.scrollBy({ top: bottom - top + parseFloat(getComputedStyle(document.documentElement).fontSize), behavior: 'smooth' });
		}

	}

	static close() {

		qs('#place-sheet:not(.hide)', sheet => {
			sheet.className = 'hide';
			sheet.replaceChildren();
		});

		qsa('.place-sheet-selected', node => node.classList.remove('place-sheet-selected'));

	}

}

document.delegateEventListener('click', '#place-sheet [data-place-sheet-close]', function() {
	SeriesPlaceSheet.close();
});

