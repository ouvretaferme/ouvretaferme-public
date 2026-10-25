document.delegateEventListener('change', '[data-action="task-fertilizer-element"], [data-action="task-fertilizer-tool"]', function(e) {

	let request = document.location.href;

	request = request.setArgument('element', qs('[data-action="task-fertilizer-element"]').value);
	request = request.setArgument('tool', qs('[data-action="task-fertilizer-tool"]').value);

	new Ajax.Query(this)
		.method('get')
		.url(request)
		.fetch();

});

document.delegateEventListener('input', '[data-action="task-treatment"] [name^="treatment["]', function(e) {

	this.form.qs('[data-treatment="save"]', node => node.classList.remove('hide'));

});

document.delegateEventListener('change', '[data-action="task-treatment-sprayer"]', function(e) {

	new Ajax.Query(this)
		.method('get')
		.url(document.location.href.setArgument('sprayer', this.value))
		.fetch();

});

document.addEventListener('scroll', function() {

	if(
		window.scrollY === 0 &&
		document.body.dataset.template.includes(' structure-planning-scrolling')
	) {
		document.body.dataset.template = document.body.dataset.template.replace(' structure-planning-scrolling', '');
	}

});

document.delegateEventListener("scroll", '[data-planning-scroll]', (e) => {

	const currentScroll = parseInt(e.target.dataset.planningScroll);
	const newScroll = e.target.scrollTop;
	const deltaScroll = newScroll - currentScroll;

	// Un en-tête fixé au bas de l'écran, la barre du téléphone, ne s'en va pas quand le document défile.
	const header = qs('header');
	const headerScrollable = (getComputedStyle(header).position === 'fixed') ? 0 : header.getBoundingClientRect().height;

	const documentScrollable = qs('#root-nav').getBoundingClientRect().height + headerScrollable;

	if(
		deltaScroll > 0 &&
		window.scrollY < documentScrollable &&
		document.body.dataset.template.includes(' structure-planning-scrolling') === false
	) {

		document.body.dataset.template += ' structure-planning-scrolling';

		window.scrollTo({
			left: 0,
			top: documentScrollable,
			behavior: 'smooth'
		});

	}

	if(
		newScroll === 0 &&
		window.scrollY > 0 &&
		document.body.dataset.template.includes(' structure-planning-scrolling')
	) {

		setTimeout(
			() => document.body.dataset.template = document.body.dataset.template.replace(' structure-planning-scrolling', ''),
			500
		);

		window.scrollTo({
			left: 0,
			top: 0,
			behavior: 'smooth'
		});

	}

});

document.delegateEventListener("scroll", '#planning-daily-container', (e) => {

	if(Task.isScrollByDay()) {
		return;
	}

	const scroll = e.target.scrollLeft;
	const week = e.target.dataset.week;

	Task.setScrollStorage([week, scroll]);

});

document.delegateEventListener("click", '[data-action="task-checkbox"]', (e) => {

	e.preventDefault();
	e.stopImmediatePropagation();

	const checkbox = e.delegateTarget;
	const previous = checkbox.cloneNode(true);

	new Ajax.Query(checkbox)
		.url('/series/task:doCheck')
		.body(checkbox.post())
		.fetch();

	const check = (checkbox.getAttribute('post-check') === '1');

	checkbox.setAttribute('post-check', check ? '0' : '1');
	checkbox.innerHTML = Lime.Asset.icon(check ? 'check-circle' : 'circle');

	checkbox.addEventListener('ajaxAfterFetch', event => {

		if(event.detail.failed) {
			checkbox.replaceWith(previous);
		}

	}, {once: true});

});


document.delegateEventListener("touchend", '#planning-daily-container', (e) => {

	if(Task.isScrollByDay() === false) {
		return;
	}

	if(isTouchDown === false) {
		return;
	}

	const cache = Task.getScrollStorage();

	if(cache.length === 0) {
		return;
	}

	const position = qs('#planning-daily-container').scrollLeft / window.innerWidth + 1;
	let screen = null;

	if(position >= cache[1] - 0.05 && position <= cache[1] + 0.05) {
		screen = cache[1];
	} else if(position >= cache[1]) {
		screen = cache[1] + 1;
	} else {
		screen = cache[1] - 1;
	}

	if(screen !== null) {

		qs('#planning-daily-container').style.overflowX = 'hidden';

		const startPosition = qs('#planning-daily-container').scrollLeft;
		const newPosition = window.innerWidth * (screen - 1);

		const scrollFunction = function(value) {

			return () => {

				if(value === newPosition) {
					qs('#planning-daily-container').style.overflowX = '';
				}

				qs('#planning-daily-container').scrollTo({
					left: value,
					top: 0
				});
			};

		};

		if(newPosition > startPosition) {

			for(move = startPosition; ; move += 5) {

				if(move >= newPosition) {
					move = newPosition;
				}

				setTimeout(scrollFunction(move), (move - startPosition) / 2.5);

				if(move === newPosition) {
					break;
				}


			}

		} else {

			for(move = startPosition; ; move -= 5) {

				if(move <= newPosition) {
					move = newPosition;
				}

				setTimeout(scrollFunction(move), startPosition - move);

				if(move === newPosition) {
					break;
				}


			}

		}

		cache[1] = screen;
		Task.setScrollStorage(cache);

	}

});

document.delegateEventListener('click', 'input[data-action="task-write-action-change"]', function(e) {

	const form = this.firstParent('form');

	if(this.dataset.fqn === 'recolte') {
		form.qs('[data-wrapper="harvestSize"]', node => node.classList.remove('hide'))
	} else {
		form.qs('[data-wrapper="harvestSize"]', node => node.classList.add('hide'))
	}

	if(this.dataset.fqn === 'fertilisation') {
		form.qs('[data-wrapper="fertilizer"]', node => node.classList.remove('hide'))
	} else {
		form.qs('[data-wrapper="fertilizer"]', node => node.classList.add('hide'))
	}

	if(this.dataset.fqn === 'traitement') {
		form.qs('[data-wrapper="treatment"]', node => node.classList.remove('hide'))
	} else {
		form.qs('[data-wrapper="treatment"]', node => node.classList.add('hide'))
	}

	const body = {
		structure: e.target.dataset.structure,
		action: this.value
	};

	// « Modifier » poste son intervention : revenir à son action retrouve alors ce qui y est enregistré.
	// `form.id` y désignerait le champ nommé `id`, d'où `getAttribute()`.
	if(form.getAttribute('id') === 'task-update') {
		body.id = form.qs('[name="id"]').value;
	}

	new Ajax.Query(this)
		.url('/series/task:getFields')
		.body(body)
		.fetch()
		.then((json) => {

			form.ref('products', wrapper => wrapper.renderInner(json.products));

			form.ref('tools', wrapper => wrapper.renderInner(json.tools));

			form.ref('methods', wrapper => wrapper.renderInner(json.methods));

		});


});

document.addEventListener('scroll', function() {

	const months = qs('#tasks-calendar-months');

	if(months === null) {
		return;
	}

	if(months.getBoundingClientRect().top <= 9 * rem()) {
		qsa('.structure-subnav-planning-year', node => node.classList.remove('hide-touch'));
	} else {
		qsa('.structure-subnav-planning-year', node => node.classList.add('hide-touch'));
	}


});

document.delegateEventListener('dropdownAfterShow', '.tasks-time-day-full-link', function(e) {

	const list = e.detail.list;
	list.qs('[type="number"]').select();

});

document.delegateEventListener('scroll', '#planning-year-weeks', function() {

	const wrapper = qs('#planning-year-weeks');

	if(wrapper === null) {
		return;
	}

	const scrollTarget = wrapper.scrollLeft + window.innerWidth / 2;

	// On sélectionne le mois correspond au scroll
	Array.from(wrapper.qsa('.planning-year-week')).every((week) => {

		if(scrollTarget > week.offsetLeft && scrollTarget <= week.offsetLeft + week.offsetWidth +  2 * rem()) {

			// On va chercher les deux mois attachés à la semaine
			const scrollMonths = [
				week.dataset.months.split(/[- ]/)[1],
				week.dataset.months.split(/[- ]/)[3]
			];

			Task.highlightPlanningMonth(scrollMonths);

			return false;
		} else {
			return true;
		}

	});

});

// Le texte saisi sans produit choisi part avec le formulaire, que le serveur refuse (Task::products.unknown).
document.delegateEventListener('autocompleteUpdate', '[data-autocomplete-id="product"]', function() {

	this.firstParent('form').qs('[name="productQuery"]', node => node.value = this.value);

});

document.delegateEventListener('autocompleteSelect', '[data-autocomplete-id="product"]', function(e) {

	const form = this.firstParent('form');
	const creating = (e.detail.value === 'new');

	form.qs('[name="productQuery"]', node => node.value = '');

	// Un fieldset désactivé ne poste pas ses champs.
	form.qs('[data-ref="product-new"]', node => {

		node.classList.toggle('hide', creating === false);
		node.disabled = (creating === false);

		if(creating) {
			node.qs('[name="productNew[name]"]', field => field.value = e.detail.itemText);
		}

	});

	const treatment = e.detail.treatment;

	if(treatment === undefined) {
		return;
	}

	form.qs('[name="treatment[dose]"]', node => node.value = treatment.dose ?? '');
	form.qs('[name="treatment[water]"]', node => node.value = treatment.readyToUse ? '' : (treatment.water ?? ''));
	form.qs('[data-treatment="water"]', node => node.classList.toggle('hide', treatment.readyToUse));

	if(treatment.unit !== null) {
		form.qs('[name="treatment[unit]"]', node => node.value = treatment.unit);
	}

});

document.delegateEventListener('autocompleteSelect', '#task-create-plant', function(e) {

	let request = document.location.href;

	if(e.detail.value) {
		request = request.setArgument('plant', e.detail.value);
	} else {
		request = request.removeArgument('plant');
	}

	new Ajax.Query(this)
		.method('get')
		.url(request)
		.fetch();

});

// La fiche d'une série porte le diagramme du plan à la verticale : la pastille
// d'une intervention à faire se saisit d'un appui long et se dépose sur une
// autre semaine — une ligne occupée ou un trait de la graduation. Deux cibles à
// gauche et à droite décident si la suite vient avec (défaut) ou non.
document.delegateEventListener('pointerdown', '#series-task-wrapper .flow-timeline-pill-movable', function(e) {
	if(e.isPrimary === false || (e.button !== undefined && e.button !== 0)) {
		return;
	}
	e.preventDefault();
	Task.startVerticalShift(this, e);
});

document.delegateEventListener('pointerdown', '#series-task-wrapper .flow-timeline-harvest', function(e) {
	if(e.isPrimary === false || (e.button !== undefined && e.button !== 0) || e.target.closest('.flow-timeline-harvest-bound') !== null) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	Task.startHarvestMove(this, e);
});

document.delegateEventListener('pointerdown', '#series-task-wrapper .flow-timeline-harvest-bound', function(e) {
	if(e.isPrimary === false || (e.button !== undefined && e.button !== 0)) {
		return;
	}
	e.preventDefault();
	e.stopImmediatePropagation();
	Task.startHarvestBoundShift(this, e);
});

document.delegateEventListener('input', '#series-task-wrapper input[name="batch[]"], #series-task-wrapper .batch-all', function() {
	Task.syncPillSelection();
});

window.addEventListener('resize', function() {
	const wrapper = qs('#series-task-wrapper');
	if(wrapper !== null) {
		Task.layoutDelays(wrapper);
	}
});

document.addEventListener('contextmenu', function(e) {
	if(qs('#series-task-wrapper .flow-timeline-pill-holding, #series-task-wrapper .flow-timeline-harvest-bound.is-held') !== null) {
		e.preventDefault();
	}
});

class Task {

	// Une semaine ISO « 2026-W39 » → nombre de semaines depuis une origine fixe,
	// pour soustraire deux semaines sans se soucier du passage d'année.
	static weekIndex(week) {
		const [year, rank] = week.split('-W').map(value => parseInt(value));
		const monday = new Date(Date.UTC(year, 0, 4));
		monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7) + (rank - 1) * 7);
		return Math.round(monday.getTime() / 604800000);
	}

	// La case d'une intervention, celle de la barre de lot ; un clic sur la
	// pastille la coche ou la décoche, et la pastille en garde la trace.
	static selectionField(id) {
		return qs('#series-task-wrapper input[name="batch[]"][value="'+ id +'"]');
	}

	static syncPillSelection() {
		qsa('#series-task-wrapper .flow-timeline-pill[data-task]', pill => {
			const field = Task.selectionField(pill.dataset.task);
			const selected = (field !== null && field.checked);
			pill.classList.toggle('flow-timeline-pill-selected', selected);
			pill.firstParent('.flow-timeline-row')?.classList.toggle('flow-timeline-row-selected', selected);
		});
	}

	static togglePillSelection(pill) {

		const field = Task.selectionField(pill.dataset.task);

		if(field === null) {
			return;
		}

		field.checked = !field.checked;
		field.dispatchEvent(new Event('input', {bubbles: true}));
		Task.syncPillSelection();

	}

	static startVerticalShift(pill, e) {

		const wrapper = qs('#series-task-wrapper');
		const origin = pill.dataset.week;
		const rail = qs('#series-task-wrapper .flow-timeline-band .flow-timeline-rail');

		if(!wrapper || !rail || !origin) {
			return;
		}

		let armed = false;
		let moved = false;
		let target = null;
		let chosen = null;
		let ghost = null;
		let chips = null;
		let followers = [];

		// Une pastille cochée entraîne toute la sélection cochée ; les puces
		// « seule / avec la suite » ne s'affichent alors pas.
		const selected = [];
		if(pill.classList.contains('flow-timeline-pill-selected')) {
			qsa('#series-task-wrapper .flow-timeline-pill-selected.flow-timeline-pill-movable', node => {
				if(node !== pill) {
					selected.push(node);
				}
			});
		}
		const withSelection = pill.classList.contains('flow-timeline-pill-selected');

		const startX = e.clientX;
		const startY = e.clientY;
		const railX = rail.getBoundingClientRect().left + rail.getBoundingClientRect().width / 2;

		const clearTarget = () => {
			qsa('#series-task-wrapper .flow-timeline-drop', node => node.classList.remove('flow-timeline-drop'));
		};

		const bandOf = week => qs('#series-task-wrapper .flow-timeline-band[data-week="'+ week +'"]');

		const bandAtY = (y) => {
			let found = null;
			qsa('#series-task-wrapper .flow-timeline-band[data-week]', node => {
				const box = node.getBoundingClientRect();
				if(found === null && y >= box.top && y < box.bottom) {
					found = node;
				}
			});
			return found;
		};

		// « Ce qui suit » : les pastilles à faire de la même série dont la semaine
		// est égale ou postérieure — elles sont toutes dans cette liste.
		const following = [];
		qsa('#series-task-wrapper .flow-timeline-pill-movable', node => {
			if(node !== pill && node.dataset.week >= origin) {
				following.push(node);
			}
		});

		// Ce qui partira avec la pastille est signalé dès la prise, avant tout
		// mouvement : c'est ce qui rend « seule » et « avec la suite » lisibles.
		// La campagne attendue suit aussi, si elle commence à cette semaine ou après
		// (la règle de CultivationLib::getFollowingHarvest()).
		// Par culture : la campagne dont le début est à cette semaine ou après.
		const harvestFollowing = Array.from(qsa('#series-task-wrapper .flow-timeline-harvest-first'))
			.filter(node => node.dataset.first >= origin)
			.map(node => node.dataset.cultivation);
		const harvestFollows = (harvestFollowing.length > 0);
		const harvestSegments = () => Array.from(qsa('#series-task-wrapper .flow-timeline-harvest')).filter(node => harvestFollowing.includes(node.dataset.cultivation));

		// La zone du métro qui va bouger, entourée d'un pointillé : de la pastille
		// tenue à la dernière chose qui suit (pastille ou bout de la campagne).
		let zone = null;

		const drawZone = (show, delta = 0) => {

			zone?.remove();
			zone = null;

			if(show === false) {
				return;
			}

			// À l'arrêt, la zone entoure ce qui va partir ; en mouvement, elle suit
			// les fantômes sur les bandes d'arrivée.
			let boxes;

			if(delta === 0) {
				boxes = [pill.getBoundingClientRect(), ...following.map(node => node.getBoundingClientRect())];
				if(harvestFollows) {
					harvestSegments().forEach(node => boxes.push(node.getBoundingClientRect()));
				}
			} else {
				boxes = followers.map(node => node.getBoundingClientRect());
				const landing = bandOf(Task.shiftWeek(origin, delta));
				if(landing !== null) {
					const box = landing.getBoundingClientRect();
					boxes.push({top: box.top + 6, bottom: box.top + 6 + 24});
				}
				if(boxes.length === 0) {
					return;
				}
			}

			const top = Math.min(...boxes.map(box => box.top));
			const bottom = Math.max(...boxes.map(box => box.bottom));
			const railBox = rail.getBoundingClientRect();

			zone = document.createElement('div');
			zone.className = 'flow-timeline-following-zone';
			zone.style.left = (railBox.left - 2) + 'px';
			zone.style.width = (railBox.width + 4) + 'px';
			zone.style.top = (top - 6) + 'px';
			zone.style.height = (bottom - top + 12) + 'px';
			document.body.append(zone);

		};

		const highlight = () => {
			const withFollowing = (withSelection === false && (chosen || 'only') === 'following');
			const going = withSelection ? selected : (withFollowing ? following : []);
			[...following, ...selected].forEach(node => node.classList.toggle('flow-timeline-pill-will-move', going.includes(node)));
			harvestSegments().forEach(node => node.classList.toggle('flow-timeline-harvest-will-move', withFollowing));
		};

		const place = (x, y) => {
			ghost.style.left = railX + 'px';
			ghost.style.top = y + 'px';
			chips.style.left = railX + 'px';
			chips.style.top = y + 'px';
		};

		const arm = setTimeout(() => {

			armed = true;
			pill.classList.add('flow-timeline-pill-holding');

			ghost = document.createElement('div');
			ghost.className = 'flow-timeline-pill-ghost';
			ghost.textContent = pill.textContent;
			ghost.style.cssText = pill.getAttribute('style') || '';
			document.body.append(ghost);

			chips = document.createElement('div');
			chips.className = 'flow-timeline-chips';
			if(withSelection === false) {
				chips.innerHTML = '<span class="flow-timeline-chip flow-timeline-chip-left is-on">'+ Task.labels.alone +'</span><span class="flow-timeline-chip flow-timeline-chip-right">'+ Task.labels.withFollowing +'</span>';
			}
			document.body.append(chips);

			place(e.clientX, e.clientY);
			highlight();
			drawZone(withSelection === false && (chosen || 'only') === 'following');

		}, 350);

		// Les fantômes de la suite : posés sur la bande d'arrivée de chacun, ou
		// retirés quand la suite ne bouge pas.
		const drawFollowers = (delta) => {

			followers.forEach(node => node.remove());
			followers = [];

			const withFollowing = (withSelection === false && (chosen || 'only') === 'following');

			if(delta === 0 || (withSelection === false && withFollowing === false)) {
				drawZone(withFollowing, 0);
				return;
			}

			// La campagne attendue suit aussi : son fantôme se pose sur les bandes
			// d'arrivée, du début à la fin décalés (bornés au diagramme).
			if(withSelection === false && harvestFollows) {

				harvestFollowing.forEach(cultivation => {

					const segments = Array.from(qsa('#series-task-wrapper .flow-timeline-harvest[data-cultivation="'+ cultivation +'"]'));
					const shifted = segments.map(node => bandOf(Task.shiftWeek(node.firstParent('.flow-timeline-band').dataset.week, delta))).filter(node => node !== null);

					if(shifted.length === 0) {
						return;
					}

					// À la même place latérale que la barre d'origine.
					const box0 = segments[0].getBoundingClientRect();
					const top = shifted[0].getBoundingClientRect().top;
					const bottom = shifted[shifted.length - 1].getBoundingClientRect().bottom;

					const ghost = document.createElement('div');
					ghost.className = 'flow-timeline-harvest-ghost';
					ghost.style.left = (box0.left + box0.width / 2) + 'px';
					ghost.style.width = box0.width + 'px';
					ghost.style.top = (top + 3) + 'px';
					ghost.style.height = (bottom - top - 6) + 'px';
					ghost.textContent = 'R';
					document.body.append(ghost);
					followers.push(ghost);

				});

			}

			// Plusieurs pastilles sur la même bande d'arrivée s'empilent, comme
			// elles le feront une fois posées.
			const stacked = {};

			(withSelection ? selected : following).forEach(node => {

				const week = Task.shiftWeek(node.dataset.week, delta);
				const band = bandOf(week);

				if(band === null) {
					return;
				}

				stacked[week] = (stacked[week] || 0) + 1;

				const box = band.getBoundingClientRect();
				const clone = document.createElement('div');
				clone.className = 'flow-timeline-pill-follower';
				clone.textContent = node.textContent;
				clone.style.cssText = node.getAttribute('style') || '';
				clone.style.left = railX + 'px';
				clone.style.top = (box.top + 6 + 12 + (stacked[week] - 1) * 30) + 'px';
				document.body.append(clone);
				followers.push(clone);

			});

			drawZone(withFollowing, delta);

		};

		// En « seule », rester sur sa semaine et glisser le long de ses voisines
		// les réordonne : un trait marque où la ligne se posera.
		const ownRow = pill.firstParent('.flow-timeline-row');
		let insertIndex = null;

		const siblingRows = () => {
			const band = bandOf(origin);
			return band ? Array.from(band.qsa('.flow-timeline-row[data-task]')).filter(row => row !== ownRow) : [];
		};

		const clearInsert = () => {
			qsa('#series-task-wrapper .flow-timeline-row-insert-before, #series-task-wrapper .flow-timeline-row-insert-after', row => row.classList.remove('flow-timeline-row-insert-before', 'flow-timeline-row-insert-after'));
			insertIndex = null;
		};

		const markInsert = (y) => {

			const rows = siblingRows();

			if(rows.length === 0) {
				return;
			}

			insertIndex = rows.filter(row => {
				const box = row.getBoundingClientRect();
				return y > box.top + box.height / 2;
			}).length;

			if(insertIndex < rows.length) {
				rows[insertIndex].classList.add('flow-timeline-row-insert-before');
			} else {
				rows[rows.length - 1].classList.add('flow-timeline-row-insert-after');
			}

		};

		const move = (event) => {

			if(Math.abs(event.clientX - startX) > 4 || Math.abs(event.clientY - startY) > 4) {
				moved = true;
			}

			if(armed === false) {
				return;
			}

			event.preventDefault();
			place(event.clientX, event.clientY);

			if(withSelection === false) {
				const dx = event.clientX - startX;
				if(dx < -24) {
					chosen = 'only';
				} else if(dx > 24) {
					chosen = 'following';
				}
				chips.qs('.flow-timeline-chip-left').classList.toggle('is-on', (chosen || 'only') === 'only');
				chips.qs('.flow-timeline-chip-right').classList.toggle('is-on', (chosen || 'only') === 'following');
				highlight();
			}

			clearTarget();
			clearInsert();

			const band = bandAtY(event.clientY);
			target = band ? band.dataset.week : null;

			if(band) {
				band.classList.add('flow-timeline-drop');
			}

			// Sur sa propre semaine, un glissé en « seule » réordonne. « Avec les
			// suivantes » n'y fait rien : la suite bouge avec la pastille, donc rien
			// ne change de place.
			if(target === origin && withSelection === false && (chosen || 'only') === 'only') {
				markInsert(event.clientY);
			}

			drawFollowers(target ? Task.weekIndex(target) - Task.weekIndex(origin) : 0);

		};

		const finish = () => {

			clearTimeout(arm);

			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', cancel);

			pill.classList.remove('flow-timeline-pill-holding');
			zone?.remove();
			zone = null;
			qsa('#series-task-wrapper .flow-timeline-pill-will-move', node => node.classList.remove('flow-timeline-pill-will-move'));
			qsa('#series-task-wrapper .flow-timeline-harvest-will-move', node => node.classList.remove('flow-timeline-harvest-will-move'));
			ghost?.remove();
			chips?.remove();
			followers.forEach(node => node.remove());
			followers = [];
			clearTarget();
			clearInsert();

		};

		const up = () => {

			const chosenTarget = target;
			const mode = chosen || 'only';
			const chosenIndex = insertIndex;
			const rows = siblingRows();

			const wasArmed = armed;
			finish();

			// Un appui court sans mouvement : c'est un clic, il coche la pastille.
			if(wasArmed === false) {
				if(moved === false) {
					Task.togglePillSelection(pill);
				}
				return;
			}

			// Même semaine, en « seule » : c'est un réordonnancement.
			if(chosenTarget === origin && chosenIndex !== null && withSelection === false && mode === 'only') {

				const before = [...rows.slice(0, chosenIndex), ownRow, ...rows.slice(chosenIndex)].map(row => row.dataset.task);
				const current = Array.from(bandOf(origin).qsa('.flow-timeline-row[data-task]')).map(row => row.dataset.task);

				if(before.join(',') === current.join(',')) {
					return;
				}

				Task.rememberPositions([pill.dataset.task]);
				pill.classList.add('flow-timeline-pill-pending');

				const body = new URLSearchParams();
				body.append('id', wrapper.dataset.series);
				body.append('week', origin);
				before.forEach(id => body.append('ids[]', id));

				new Ajax.Query(wrapper)
					.url('/series/series:doUpdateTaskPosition')
					.body(body)
					.fetch()
					.then(json => (json && json.__redirect === undefined) && pill.classList.remove('flow-timeline-pill-pending'), () => pill.classList.remove('flow-timeline-pill-pending'));

				return;

			}

			if(chosenTarget === null || chosenTarget === origin) {
				return;
			}

			const increment = Task.weekIndex(chosenTarget) - Task.weekIndex(origin);
			const pills = withSelection ? [pill, ...selected] : [pill];

			Task.rememberPositions(pills.map(node => node.dataset.task));
			pills.forEach(node => node.classList.add('flow-timeline-pill-pending'));
			const settle = () => pills.forEach(node => node.classList.remove('flow-timeline-pill-pending'));

			const body = new URLSearchParams();
			body.append('increment', increment);

			if(withSelection) {
				pills.forEach(node => body.append('ids[]', node.dataset.task));
			} else {
				body.append('id', pill.dataset.task);
				body.append('mode', mode);
			}

			new Ajax.Query(wrapper)
				.url(withSelection ? '/series/task:doIncrementPlannedCollection' : '/series/task:doIncrementPlanned')
				.body(body)
				.fetch()
				.then(json => (json && json.__redirect === undefined) && settle(), settle);

		};

		// Un geste interrompu par le navigateur ne vaut pas un lâcher : rien n'est
		// enregistré.
		const cancel = () => finish();

		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', cancel);

		if(e.pointerId !== undefined && pill.setPointerCapture) {
			try {
				pill.setPointerCapture(e.pointerId);
			} catch(error) {
				// pointeur déjà relâché
			}
		}

	}

	// Le mouvement se voit : avant d'écrire, on note où sont les lignes ; quand le
	// bloc revient du serveur, chaque ligne qui a changé de place part de son
	// ancienne position et glisse vers la nouvelle (FLIP), et s'allume un instant.
	// Sans ça, cinq semaines de décalage sur des bandes vides d'un rem se voient
	// à peine.
	static positions = null;
	static weeks = null;
	static touched = [];

	// Les positions se mesurent depuis le haut du bloc, pas de la fenêtre : un
	// rechargement qui décale la page d'un bloc ne fait pas « bouger » toutes
	// les lignes.
	// La semaine de chacun est notée avec sa position : décaler une intervention
	// pousse tout ce qui la suit vers le bas sans rien changer à son planning,
	// et ces lignes-là glissent mais ne s'allument pas.
	static rememberPositions(touched = []) {

		const wrapper = qs('#series-task-wrapper');
		const origin = wrapper.getBoundingClientRect().top;
		const positions = {};
		const weeks = {};

		wrapper.qsa('.flow-timeline-row[data-task]', row => {
			const key = 'task-'+ row.dataset.task;
			positions[key] = row.getBoundingClientRect().top - origin;
			weeks[key] = row.firstParent('.flow-timeline-band')?.dataset.week ?? null;
		});

		wrapper.qsa('.flow-timeline-harvest-bound', bound => {
			const key = 'bound-'+ (bound.classList.contains('flow-timeline-harvest-bound-first') ? 'first' : 'last') +'-'+ bound.dataset.cultivation;
			positions[key] = bound.getBoundingClientRect().top - origin;
			weeks[key] = bound.firstParent('.flow-timeline-band')?.dataset.week ?? null;
		});

		Task.positions = positions;
		Task.weeks = weeks;
		Task.touched = touched.map(id => String(id));

	}

	static settleMove(wrapper) {

		const positions = Task.positions;
		const weeks = Task.weeks ?? {};
		const touched = Task.touched ?? [];
		Task.positions = null;
		Task.weeks = null;
		Task.touched = [];

		if(positions === null) {
			return;
		}

		const moving = [];
		const origin = wrapper.getBoundingClientRect().top;

		const weekOf = node => node.firstParent('.flow-timeline-band')?.dataset.week ?? null;

		wrapper.qsa('.flow-timeline-row[data-task]', row => {
			const key = 'task-'+ row.dataset.task;
			const before = positions[key];
			if(before !== undefined) {
				const delta = before - (row.getBoundingClientRect().top - origin);
				if(Math.abs(delta) > 2) {
					moving.push([row, delta, (weeks[key] !== weekOf(row) || touched.includes(row.dataset.task))]);
				}
			}
		});

		wrapper.qsa('.flow-timeline-harvest-bound', bound => {
			const key = 'bound-'+ (bound.classList.contains('flow-timeline-harvest-bound-first') ? 'first' : 'last') +'-'+ bound.dataset.cultivation;
			const before = positions[key];
			if(before !== undefined) {
				const delta = before - (bound.getBoundingClientRect().top - origin);
				if(Math.abs(delta) > 2) {
					moving.push([bound, delta, (weeks[key] !== weekOf(bound))]);
				}
			}
		});

		if(moving.length === 0) {
			return;
		}

		moving.forEach(([node, delta]) => {
			node.classList.add('flow-timeline-moving');
			node.style.transition = 'none';
			node.style.transform = (node.classList.contains('flow-timeline-harvest-bound') ? 'translateX(-50%) ' : '') + 'translateY('+ delta +'px)';
		});

		// Il faut que le navigateur ait PEINT la position de départ avant de la
		// relâcher : un getBoundingClientRect() ne force que la mise en page, et si
		// le rendu bloque le fil principal les deux états tombent dans la même
		// image, donc la ligne se téléporte. Deux images d'attente, et le trajet se
		// voit. Le filet couvre l'onglet en arrière-plan, où requestAnimationFrame
		// ne se déclenche pas du tout.
		let released = false;

		const release = () => {

			if(released) {
				return;
			}

			released = true;

			moving.forEach(([node, delta, changed]) => {
				node.style.transition = '';
				node.style.transform = node.classList.contains('flow-timeline-harvest-bound') ? 'translateX(-50%)' : '';
				// La carte porte le voile de MainJuiciness.flash() : le ::after de la ligne sert au repère d'insertion.
				if(changed && node.classList.contains('flow-timeline-row')) {
					MainJuiciness.flash(node.qs('.flow-timeline-card'));
				}
			});

			setTimeout(() => {
				moving.forEach(([node]) => {
					node.classList.remove('flow-timeline-moving');
					node.style.transform = '';
				});
			}, 450);

		};

		requestAnimationFrame(() => requestAnimationFrame(release));

		setTimeout(release, 150);

	}

	// L'arrivée d'un délai vise une pastille précise (semis, plantation…) qui
	// n'est pas forcément la première de sa semaine : sa hauteur se mesure.
	static layoutDelays(wrapper) {

		wrapper.qsa('.flow-timeline-delay-end-pill[data-task]', segment => {

			const pill = wrapper.qs('.flow-timeline-row[data-task="'+ segment.dataset.task +'"] .flow-timeline-pill');
			const band = segment.firstParent('.flow-timeline-band');

			if(pill === null || band === null) {
				return;
			}

			const box = pill.getBoundingClientRect();
			segment.style.height = (box.top + box.height / 2 - band.getBoundingClientRect().top) + 'px';

		});

		// Le départ, porté par la ligne du semis ou de la plantation, descend
		// jusqu'au bas de la bande — même quand d'autres lignes suivent la sienne.
		wrapper.qsa('.flow-timeline-delay-start-row', segment => {

			const band = segment.firstParent('.flow-timeline-band');

			if(band === null) {
				return;
			}

			segment.style.height = (band.getBoundingClientRect().bottom - segment.getBoundingClientRect().top) + 'px';

		});

		// Le nombre se centre sur le trait entier, d'une pastille à l'autre — pas
		// sur la seule bande vide qui le porte.
		const groups = {};

		wrapper.qsa('.flow-timeline-delay[data-delay]', segment => {
			const box = segment.getBoundingClientRect();
			const group = groups[segment.dataset.delay] ??= {top: Infinity, bottom: -Infinity, label: null};
			group.top = Math.min(group.top, box.top);
			group.bottom = Math.max(group.bottom, box.bottom);
			const label = segment.qs('.flow-timeline-delay-label');
			if(label !== null) {
				group.label = label;
			}
		});

		Object.values(groups).forEach(group => {
			if(group.label === null) {
				return;
			}
			const parent = group.label.parentElement.getBoundingClientRect();
			group.label.style.top = ((group.top + group.bottom) / 2 - parent.top) + 'px';
		});

	}

	// Lundi d'une semaine ISO, en secondes locales — ce que le serveur attend.
	static mondayTs(week) {
		const [year, rank] = week.split('-W').map(value => parseInt(value));
		const jan4 = new Date(year, 0, 4);
		const monday = new Date(year, 0, 4 - ((jan4.getDay() + 6) % 7) + (rank - 1) * 7);
		return Math.round(monday.getTime() / 1000);
	}

	// Étirer, rétracter ou déplacer la campagne attendue : une poignée bouge son
	// bout, la barre elle-même bouge la campagne entière ; la bande visée s'allume,
	// la campagne se redessine au fil du geste ; au relâchement l'écriture est
	// celle du plan (cultivation:doUpdateHarvestPeriod).
	static startHarvestBoundShift(bound, e) {
		Task.startHarvestShift(bound, e, bound.classList.contains('flow-timeline-harvest-bound-first') ? 'first' : 'last');
	}

	static startHarvestMove(segment, e) {

		const bound = qs('#series-task-wrapper .flow-timeline-harvest-bound[data-cultivation="'+ segment.dataset.cultivation +'"]');

		if(bound === null) {
			return;
		}

		Task.startHarvestShift(bound, e, 'move', segment);

	}

	static startHarvestShift(bound, e, mode, held = null) {

		const wrapper = qs('#series-task-wrapper');
		const first0 = bound.dataset.first;
		const last0 = bound.dataset.last;
		const length = Task.weekIndex(last0) - Task.weekIndex(first0);

		held ??= bound;

		let armed = false;
		let target = null;

		const bands = [];
		qsa('#series-task-wrapper .flow-timeline-band[data-week]', node => bands.push(node));
		const firstBand = bands[0].dataset.week;
		const lastBand = bands[bands.length - 1].dataset.week;

		const bandAt = (y) => {
			for(const node of bands) {
				const box = node.getBoundingClientRect();
				if(y >= box.top && y < box.bottom) {
					return node;
				}
			}
			return null;
		};

		// En déplacement, on retient la semaine saisie : le décalage se compte
		// depuis elle, pas depuis le début de la campagne.
		const grabbed = (mode === 'move') ? bandAt(e.clientY)?.dataset.week : null;

		// La campagne se redessine en direct : les bandes prennent ou perdent la
		// barre au fil du geste, la poignée suit le pointeur. On repart de l'état
		// d'origine si le geste s'annule.
		// Les segments de cette culture : un par bande couverte. Le modèle sert à
		// en créer sur les bandes qui entrent dans la campagne.
		const cultivation = bound.dataset.cultivation;
		const selector = '.flow-timeline-harvest[data-cultivation="'+ cultivation +'"]';
		const modelStyle = qs('#series-task-wrapper '+ selector).getAttribute('style') || '';
		// Le repère au milieu de la barre : la lettre, ou la vignette de la culture
		// quand plusieurs campagnes se partagent le rail.
		const label = qs('#series-task-wrapper '+ selector +' .flow-timeline-harvest-label') ?? qs('#series-task-wrapper '+ selector +' .flow-timeline-harvest-plant');
		const handles = {first: qs('#series-task-wrapper '+ selector +' .flow-timeline-harvest-bound-first'), last: qs('#series-task-wrapper '+ selector +' .flow-timeline-harvest-bound-last')};

		const draw = (first, last) => {

			const covered = [];

			bands.forEach(node => {

				const week = node.dataset.week;
				const inside = (week >= first && week <= last);
				let segment = node.qs(selector);

				if(inside && segment === null) {
					segment = document.createElement('span');
					segment.className = 'flow-timeline-harvest';
					segment.dataset.cultivation = cultivation;
					segment.style.cssText = modelStyle;
					if(mode === 'move' && armed) {
						segment.classList.add('is-held');
					}
					node.qs('.flow-timeline-rail').append(segment);
				} else if(inside === false && segment !== null) {
					segment.remove();
					segment = null;
				}

				if(segment !== null) {
					segment.classList.toggle('flow-timeline-harvest-first', week === first);
					segment.classList.toggle('flow-timeline-harvest-last', week === last);
					segment.dataset.first = first;
					segment.dataset.last = last;
					covered.push(segment);
				}

			});

			if(covered.length === 0) {
				return;
			}

			// La lettre au milieu, les poignées aux bouts : une poignée tenue ne
			// suit pas la main librement, elle reste collée au bout de la barre
			// et saute de bande en bande avec elle.
			if(label !== null) {
				covered[Math.floor(covered.length / 2)].append(label);
			}
			if(handles.first !== null) {
				covered[0].append(handles.first);
			}
			if(handles.last !== null) {
				covered[covered.length - 1].append(handles.last);
			}

		};

		// Ce que vise le geste, selon la prise.
		const aim = (week) => {
			if(mode === 'first') {
				return [week > last0 ? last0 : week, last0];
			}
			if(mode === 'last') {
				return [first0, week < first0 ? first0 : week];
			}
			let delta = Task.weekIndex(week) - Task.weekIndex(grabbed);
			// La campagne reste dans le diagramme.
			delta = Math.max(delta, Task.weekIndex(firstBand) - Task.weekIndex(first0));
			delta = Math.min(delta, Task.weekIndex(lastBand) - Task.weekIndex(last0));
			return [Task.shiftWeek(first0, delta), Task.shiftWeek(last0, delta)];
		};

		const title = bound.title;

		// À la souris, le geste s'arme tout de suite : rien d'autre ne se joue sur
		// la barre, et attendre un tiers de seconde donne une campagne qui a l'air
		// de ne pas se déplacer. Au doigt, l'attente distingue le glissé du
		// défilement de la page.
		const arm = setTimeout(() => {
			armed = true;
			held.classList.add('is-held');
			bound.title = '';
		}, e.pointerType === 'mouse' ? 0 : 350);

		const move = (event) => {

			if(armed === false) {
				return;
			}

			event.preventDefault();
			bands.forEach(node => node.classList.remove('flow-timeline-drop'));

			const band = bandAt(event.clientY);

			if(band === null || (mode === 'move' && grabbed === null)) {
				target = null;
				return;
			}

			target = aim(band.dataset.week);
			band.classList.add('flow-timeline-drop');
			draw(target[0], target[1]);

		};

		const up = () => {

			const chosen = target;
			const wasArmed = armed;

			clearTimeout(arm);
			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', up);
			held.classList.remove('is-held');
			bands.forEach(node => node.classList.remove('flow-timeline-drop'));
			bound.title = title;

			if(wasArmed === false || chosen === null || (chosen[0] === first0 && chosen[1] === last0)) {
				draw(first0, last0);
				return;
			}

			// L'état visé reste affiché pendant l'aller-retour ; un refus le remet.
			Task.rememberPositions();
			const body = new URLSearchParams();
			body.append('id', bound.dataset.cultivation);
			body.append('first', Task.mondayTs(chosen[0]));
			body.append('last', Task.mondayTs(chosen[1]));

			new Ajax.Query(wrapper)
				.url('/series/cultivation:doUpdateHarvestPeriod')
				.body(body)
				.fetch()
				.then(json => (json && json.__redirect === undefined) && draw(first0, last0), () => draw(first0, last0));

		};

		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', up);

		if(e.pointerId !== undefined && held.setPointerCapture) {
			try {
				held.setPointerCapture(e.pointerId);
			} catch(error) {
				// pointeur déjà relâché
			}
		}

	}

	static shiftWeek(week, delta) {
		const [year, rank] = week.split('-W').map(value => parseInt(value));
		const monday = new Date(Date.UTC(year, 0, 4));
		monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7) + (rank - 1) * 7 + delta * 7 + 3);
		const thursday = monday;
		const first = new Date(Date.UTC(thursday.getUTCFullYear(), 0, 4));
		first.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7) + 3);
		const number = 1 + Math.round((thursday - first) / 604800000);
		return thursday.getUTCFullYear() + '-W' + String(number).padStart(2, '0');
	}

	static changeBookmark(target) {

		if(target.checked) {
			delete target.dataset.confirm;
		} else {
			target.dataset.confirm = target.dataset.confirmText;
		}

	}

	static changeHarvestUnit(task, target) {

		new Ajax.Query(target)
			.body({
				id: task,
				harvestUnit: target.value
			})
			.url('/series/task:getStockField')
			.fetch();

	}

	static updateCultivationCheckbox(target) {

		const form = target.firstParent('form');

		const body = new URLSearchParams();

		let harvestDisable = false;
		let harvestString = null;

		const seriesChecked = form.qsa('input[name="series[]"]:checked', (seriesInput) => {

			const series = seriesInput.value;

			form.qs('input[type="radio"][name="cultivation['+ series +']"]:checked, input[type="hidden"][name="cultivation['+ series +']"]', cultivationInput => {

				if(cultivationInput.dataset.plant === undefined) {
					harvestDisable = true;
				} else {

					if(harvestString === null) {
						harvestString = cultivationInput.dataset.plant;
					} else if(harvestString !== cultivationInput.dataset.plant) {
						harvestDisable = true;
					}

					body.append('cultivations[]', cultivationInput.value);

				}

			});

		});


		form.qsa('input[type="radio"][name="action"]:not([data-fqn="recolte"])', action => seriesChecked.length === 0 ?
			action.disabled = true :
			action.disabled = false
		);

		const actionRadioRecolte = form.qs('input[type="radio"][name="action"][data-fqn="recolte"]');
		const actionHiddenRecolte = form.qs('input[type="hidden"][name="action"][data-fqn="recolte"]');

		if(Array.from(body).length > 0) {

			body.append('structure', form.dataset.structure);

			new Ajax.Query(target)
				.body(body)
				.url('/series/task:getCreateCollectionFields')
				.fetch()
				.then((json) => {

					if(actionRadioRecolte) {

						if(actionRadioRecolte.matches(':checked')) {
							form.qs('[data-wrapper="harvestSize"]', node => node.classList.remove('hide'))
						} else {
							form.qs('[data-wrapper="harvestSize"]', node => node.classList.add('hide'))
						}

					} else if(actionHiddenRecolte) {
						form.qs('[data-wrapper="harvestSize"]', node => node.classList.remove('hide'))
					}

				});

			if(actionRadioRecolte) {
				// Pas de récolte possible si intervention partagée sélectionnée
				actionRadioRecolte.disabled = harvestDisable;
			}

		} else {

			qs('#task-create-variety', node => node.renderInner(''));

			if(actionRadioRecolte) {

				qs('#task-create-size', node => node.renderInner(''));

				actionRadioRecolte.disabled = true;
				actionRadioRecolte.checked = false;

			}

			if(actionHiddenRecolte) {

				qs('#task-create-size', node => node.renderInner(''));

			}
		}

	}

	static selectSeriesCheckbox(target) {

		const tbody = target.firstParent('tbody');

		if(target.checked === false) {

			const cultivationsCheckbox = tbody.qsa('input[type="radio"][name="cultivation['+ target.value +']"]');

			if(cultivationsCheckbox.length > 0) {

				target.disabled = true;
				cultivationsCheckbox.forEach(input => input.checked = false);

			}

		}

		this.updateCultivationCheckbox(target);

	}

	static selectCultivationRadio(target) {

		const tbody = target.firstParent('tbody');

		const seriesCheckbox = tbody.qs('[name="series[]"]');
		seriesCheckbox.disabled = null;
		seriesCheckbox.checked = true;

		this.updateCultivationCheckbox(target);

	}

	static selectAll(target, plant) {

		const table = qs('#series-field-table');

		if(target.checked === false) {

			table.qsa('[name^="series"]', fieldSeries => {

				fieldSeries.checked = false;

				if(fieldSeries.dataset.filterCultivations > 1) {

					fieldSeries.disabled = true;

					table.qs('input[type="radio"][name="cultivation['+ fieldSeries.value +']"]:checked', fieldCultivation => fieldCultivation.checked = false);

				}

			});

		} else {

			const tdCheckbox = qs('#series-field-filter');
			tdCheckbox.qsa('input[type="checkbox"]', checkbox => {
				if(checkbox !== target) {
					checkbox.checked = false;
				}
			});

			table.qsa('[name^="series"]', fieldSeries => {

				if(fieldSeries.dataset.filterCultivations > 1) {

					table.qs(
						'input[type="radio"][name="cultivation['+ fieldSeries.value +']"]'+ (plant ? '[data-plant="'+ plant +'"]' : ':not([data-plant])'),
						fieldCultivation => {

							fieldCultivation.checked = true;

							fieldSeries.checked = true;
							fieldSeries.disabled = null;

						},
						() => {

							fieldSeries.checked = false;
							fieldSeries.disabled = true;

						}
					);

				} else {
					fieldSeries.checked = true;
				}

			});

		}

		this.updateCultivationCheckbox(table);

	}

	static createSelectCultivation(target) {

		let request = document.location.href;

		if(target.value) {
			request = request.setArgument('cultivation', target.value);
		} else {
			request = request.removeArgument('cultivation');
		}

		new Ajax.Query()
			.method('get')
			.url(request)
			.fetch();

	}

	static updateSelectCultivation(target) {

		const form = target.firstParent('form');

		if(target.value === '') {
			form.ref('varieties', wrapper => wrapper.renderInner(''));
		} else {

			form.ref('varieties', wrapper => {

				new Ajax.Query(target)
					.url('/series/task:getVarietiesField')
					.body({
						id: form.qs('[name="id"]').value,
						cultivation: target.value
					})
					.fetch()
					.then((json) => {

						wrapper.renderInner(json.field);

					});

			});

		}

	}

	static changePlanned(target, period) {

		const wrapper = target.firstParent('.task-write-planned');

		wrapper.dataset.period = period;
		wrapper.qsa('div.task-write-planned-'+ period +' input', field => field.removeAttribute('disabled'));
		wrapper.qsa('div.task-write-planned-field:not(.task-write-planned-'+ period +') input', field => field.setAttribute('disabled', 'disabled'));

		if(period === 'unplanned') {

			this.changeRepeat(target, false);

			wrapper.qsa('.task-write-norepeat, .task-write-repeat', node => node.classList.add('hide'));

		} else {
			wrapper.qs('.task-write-repeat', node => node.classList.remove('hide'));
		}

	}

	static changeRepeat(target, repeat) {

		const wrapper = target.firstParent('.task-write-planned');

		if(repeat) {

			wrapper.qs('.task-write-repeat', node => node.classList.add('hide'));
			wrapper.qsa('.task-write-norepeat, .task-write-repeat-field', node => node.classList.remove('hide'));

			wrapper.qsa('.task-write-repeat-field input', field => field.removeAttribute('disabled'));

		} else {

			wrapper.qs('.task-write-repeat', node => node.classList.remove('hide'));
			wrapper.qsa('.task-write-norepeat, .task-write-repeat-field', node => node.classList.add('hide'));

			wrapper.qsa('.task-write-repeat-field input', field => field.setAttribute('disabled', 'disabled'));

		}

	}

	static isScrollByDay() {

		return (
			isTouch() &&
			window.matchMedia('(max-width: 575px)').matches
		);

	}

	static getScrollStorage() {

		const value = JSON.parse(localStorage.getItem('task-scroll-'+ (this.isScrollByDay() ? 'screen' : 'scroll')) || '[]');

		if(Date.now() - value[2] > 3600 * 1000) { // Expiration à une heure
			localStorage.removeItem(value);
			return [];
		}

		return value;

	}

	static setScrollStorage(value) {

		value[2] = Date.now();

		localStorage.setItem('task-scroll-'+ (this.isScrollByDay() ? 'screen' : 'scroll'), JSON.stringify(value));

	}

	static scrollPlanningDaily(target, screen) {

		const cache = this.getScrollStorage();
		const useCache = (
			cache.length !== null &&
			target.dataset.week === cache[0]
		);

		let position;

		if(this.isScrollByDay()) {

			if(useCache) {
				screen = cache[1];
			} else {
				this.setScrollStorage([target.dataset.week, screen]);
			}

			position = document.documentElement.clientWidth * (screen - 1);

		} else {

			if(useCache) {
				position = cache[1];
			} else {

				const plannerWidth = (27 * 8) * rem();
				const screenWidth = document.documentElement.clientWidth;

				position = (screen - 1) * 27 * rem() + 13.5 * rem() - screenWidth / 2;
				position = Math.max(0, position);
				position = Math.min(plannerWidth, position);

			}

		}

		target.scroll(position, 0);

	}

	static selectPlanningMonth(year, month) {

		this.highlightPlanningMonth([month]);

		const weeks = qsa('#planning-year-weeks > [data-months~="'+ year +'-'+ month +'"]');

		// Current week ?
		const currentWeek = qs('#planning-year-weeks > [data-months~="'+ year +'-'+ month +'"].planning-year-week-current');

		let targetOffset;

		if(currentWeek !== null) {

			const startOffset = currentWeek.offsetLeft;
			targetOffset = startOffset - (window.innerWidth - currentWeek.offsetWidth) / 2;

		} else {

			const startOffset = weeks[0].offsetLeft;
			targetOffset = startOffset - rem();

		}

		const wrapper = qs('#planning-year-weeks');
		wrapper.scroll(targetOffset, 0);

		// Enregistrement de l'URL
		const base = qs('#tasks-calendar-months').dataset.url;
		Lime.History.replaceState(base +'/'+ year +'/'+ month);

	}

	static clickPlanningMonth(year, month) {

		this.selectPlanningMonth(year, month);

	}

	static highlightPlanningMonth(months) {

		const wrapper = qs('#tasks-calendar-months');

		if(months.filter(month => wrapper.qs('.tasks-calendar-month[data-month="'+ month +'"].selected')).length > 0) {
			return;
		} else {

			const month = months[0];

			wrapper.qsa('.tasks-calendar-month', node => node.classList.remove('selected'));
			wrapper.qs('.tasks-calendar-month:nth-child('+ month +')').classList.add('selected');

		}

	}

	static checkPlanningAction(selector) {

		const wrapper = selector.firstParent('.tasks-planning-items');

		wrapper.qsa('[name="batch[]"]', node => node.checked = selector.checked);

		this.changePlanningSelection();

	}

	static checkPlanningSeries(selector) {

		qsa('#series-task-wrapper [name="batch[]"]', node => node.checked = selector.checked);

		this.changePlanningSelection();

	}

	static checkPlanningWeekSeries(selector) {

		const wrapper = selector.parentElement;

		wrapper.dataset.checked = (wrapper.dataset.checked === '0') ? '1' : '0';

		qsa('#series-task-wrapper [name="batch[]"][data-week="'+ wrapper.dataset.week +'"]', node => node.checked = (wrapper.dataset.checked === '1'));

		this.changePlanningSelection();

	}

	static checkPlanningPlant(selector, plant) {

		const wrapper = selector.firstParent('.tasks-planning-items');

		wrapper.qsa('[data-filter-plant="'+ plant +'"] [name="batch[]"]', node => node.checked = selector.checked);

		this.changePlanningSelection();

	}

	static hidePlanningSelection() {

		qs('#batch-task').hide();

		qsa('[name="batch[]"]:checked', (field) => field.checked = false);
		qsa('[name="batchAction[]"]:checked', (field) => field.checked = false);

		Task.syncPillSelection();

	}

	static toggleDistributionHarvestTable() {

		const target = qs("#task-distribution-dates");

		if(target === null) {
			return;
		}

		const form = target.firstParent('form');

		const date = form.qs('[name="date"]').value;

		if(target.matches(':not([data-day~="'+ date +'"])')) {
			target.hide();
		} else {
			target.removeHide();
			target.qsa('tr', tr => tr.matches('[data-day~="'+ date +'"]') ? tr.removeHide() : tr.hide());
		}

	}

	static changePlanningSelection() {

		return Batch.changeSelection('#batch-task', '#batch-task-one',function(selection) {

			if(selection.filter(':not([data-batch~="harvest"])').length > 0) {
				qsa(".batch-harvest", button => button.hide());
			} else {

				// Récolte = même plante, même variété
				let harvestString = null;
				let harvestHide = false;

				selection.forEach((field) => {

					const task = field.firstParent('.batch-checkbox');

					if(harvestString === null) {
						harvestString = task.dataset.filterHarvest;
					} else if(harvestString !== task.dataset.filterHarvest) {
						harvestHide = true;
					}


				});

				if(harvestHide) {
					qsa(".batch-harvest", button => button.hide());
				} else {
					qsa(".batch-harvest", button => button.removeHide());
				}

			}

			// Temps de travail = même action
			let action = null;
			let sameAction = true;

			selection.forEach((field) => {

				const task = field.firstParent('.batch-checkbox');
				const taskAction = task.dataset.filterAction;

				if(action === null) {
					action = taskAction;
				} else if(action !== taskAction) {
					sameAction = false;
				}


			});

			if(sameAction === false) {
				qsa('.batch-timesheet', button => button.hide());
			} else {
				qsa('.batch-timesheet', button => button.removeHide());
			}

			if(selection.length > 1) {
				qs('.batch-update', button => button.hide());
			} else {
				qs('.batch-update', button => {
						 button.setAttribute('href', '/series/task:update?id='+ selection[0].value);
						 button.removeHide();
				});
			}

			if(selection.filter('[data-batch~="done"]').length > 0) {
				qsa('.batch-planned', button => button.hide());
				qsa('.batch-users', button => button.hide());
			} else {
				qsa('.batch-planned', button => button.removeHide());

				if(selection.filter('[data-batch~="not-postpone"]').length > 0) {
					qsa('.batch-planned .batch-postpone', link => link.hide());
				} else {
					qsa('.batch-planned .batch-postpone', link => link.removeHide());
				}

				qsa('.batch-users', button => button.removeHide());

				qsa('.batch-users .batch-planned-user', node => {

					if(selection.filter('[data-batch~="user-'+ node.getAttribute('post-user') +'"]').length !== selection.length) {
						node.setAttribute('post-action', 'add');
						node.classList.remove('selected');
					} else {
						node.setAttribute('post-action', 'delete');
						node.classList.add('selected');
					}

				});

			}

			if(selection.filter('[data-batch~="todo"]').length > 0) {
				qsa('.batch-todo', button => button.hide());
			} else {
				qsa('.batch-todo', button => button.removeHide());
			}

			if(selection.filter('[data-batch~="done"]').length > 0) {
				qsa('.batch-done', button => button.hide());
			} else {
				qsa('.batch-done', button => button.removeHide());
			}

		});

	}

}