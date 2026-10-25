document.delegateEventListener('click', 'a[data-action="flow-period-interval"]', function(e) {

	e.preventDefault();

	qs('#flow-period-only').style.display = 'none';
	qs('#flow-period-interval').style.display = 'block';

	qs('#flow-period').value = 'interval';

	qs('#flow-period-interval [name="weekStart"]').value = qs('#flow-period-only [name="weekOnly"]').value;
	qs('#flow-period-interval [name="yearStart"]').value = qs('#flow-period-only [name="yearOnly"]').value;

	qs('#flow-period-interval [name="weekStop"]').value = '';
	qs('#flow-period-interval [name="yearStop"]').value = qs('#flow-period-only [name="yearOnly"]').value;

	qs('#flow-period-interval [name="frequency"]').value = 'w1';

	qs('#flow-period-only [name="weekOnly"]').value = '';

	DateField.updateWeeks(qs('#flow-period-interval [name="weekStart"]'));

});

document.delegateEventListener('click', 'a[data-action="flow-period-only"]', function(e) {

	e.preventDefault();

	qs('#flow-period-only').style.display = 'block';
	qs('#flow-period-interval').style.display = 'none';

	qs('#flow-period').value = 'only';

	qs('#flow-period-only [name="weekOnly"]').value = qs('#flow-period-interval [name="weekStart"]').value;
	qs('#flow-period-only [name="yearOnly"]').value = qs('#flow-period-interval [name="yearStart"]').value;

	qs('#flow-period-interval [name="weekStart"], #flow-period-interval [name="weekStop"]').value = '';

	DateField.updateWeeks(qs('#flow-period-only [name="weekOnly"]'));

});

document.delegateEventListener('click', 'a[data-action="flow-season-interval"]', function(e) {

	e.preventDefault();

	qs('#flow-season-only').style.display = 'none';
	qs('#flow-season-interval').style.display = 'block';

	qs('#flow-season').value = 'interval';

	qs('#flow-season-interval [name="seasonStart"]').value = qs('#flow-season-only [name="seasonOnly"]').value;
	qs('#flow-season-interval [name="seasonStop"]').value = '';

	qs('#flow-season-only [name="seasonOnly"]').value = '';

});

document.delegateEventListener('click', 'a[data-action="flow-season-only"]', function(e) {

	e.preventDefault();

	qs('#flow-season-only').style.display = 'block';
	qs('#flow-season-interval').style.display = 'none';

	qs('#flow-season').value = 'only';

	qs('#flow-season-only [name="seasonOnly"]').value = qs('#flow-season-interval [name="seasonStart"]').value;

	qs('#flow-season-interval [name="seasonStart"], #flow-season-interval [name="seasonStop"]').value = '';

});

document.delegateEventListener('click', 'input[data-action="flow-write-action-change"]', function(e) {

	const form = this.firstParent('form');

	if(this.dataset.fqn === 'fertilisation') {
		form.qs('[data-wrapper="fertilizer"]', node => node.classList.remove('hide'))
	} else {
		form.qs('[data-wrapper="fertilizer"]', node => node.classList.add('hide'))
	}

	const body = {
		structure: e.target.dataset.structure,
		action: this.value
	};

	// « Modifier » poste son itinéraire : revenir à son action retrouve alors ce qui y est enregistré.
	// `form.id` y désignerait le champ nommé `id`, d'où `getAttribute()`.
	if(form.getAttribute('id') === 'flow-update') {
		body.id = form.qs('[name="id"]').value;
	}

	new Ajax.Query(this)
		.url('/sequence/flow:getFields')
		.body(body)
		.fetch()
		.then((json) => {

			form.ref('products', wrapper => wrapper.renderInner(json.products));
			form.ref('tools', wrapper => wrapper.renderInner(json.tools));
			form.ref('methods', wrapper => wrapper.renderInner(json.methods));

		});

});

// Le diagramme de l'itinéraire se manipule exactement comme celui d'une série :
// la pastille d'une intervention se saisit d'un appui long et se dépose sur une
// autre semaine. Deux cibles à gauche et à droite décident si la suite vient
// avec (défaut) ou non. La poignée de la fiche mène au même geste.
document.delegateEventListener('pointerdown', '#flow-wrapper .flow-timeline-pill-movable', function(e) {

	if(e.isPrimary === false || (e.button !== undefined && e.button !== 0)) {
		return;
	}

	e.preventDefault();
	Flow.startShift(this, e);

});

// Les poignées d'une période : le geste ne déplace qu'une borne, l'autre reste
// où elle est.
document.delegateEventListener('pointerdown', '#flow-wrapper .flow-timeline-lane-handle', function(e) {

	if(e.isPrimary === false || (e.button !== undefined && e.button !== 0)) {
		return;
	}

	e.preventDefault();
	e.stopPropagation();

	Flow.startResize(this, e);

});

document.delegateEventListener('input', '#flow-wrapper input[name="batch[]"], #flow-wrapper .batch-all', function() {
	Flow.syncPillSelection();
});

document.addEventListener('contextmenu', function(e) {
	if(qs('#flow-wrapper .flow-timeline-pill-holding') !== null) {
		e.preventDefault();
	}
});

class Flow {

	static createSelectCultivation(target) {

		const form = target.firstParent('form');
		const harvest = form.qs('[name="action"][data-fqn="recolte"]')

		if(target.value) {
			harvest.disabled = false;
		} else {
			harvest.disabled = true;
			harvest.checked = false;
		}

	}

	static changeAllSelection(selector) {

		qsa('#flow-wrapper [name="batch[]"]', node => node.checked = selector.checked);

		this.changeSelection();

	}

	static changeWeekSelection(selector) {

		const wrapper = selector.parentElement;

		wrapper.dataset.checked = (wrapper.dataset.checked === '0') ? '1' : '0';

		qsa('#flow-wrapper [name="batch[]"][data-week="'+ wrapper.dataset.week +'"]', node => node.checked = (wrapper.dataset.checked === '1'));

		this.changeSelection();

	}

	// La case d'une intervention, celle de la barre de lot ; un clic sur la
	// pastille la coche ou la décoche, et la pastille en garde la trace.
	static selectionField(id) {
		return qs('#flow-wrapper input[name="batch[]"][value="'+ id +'"]');
	}

	static syncPillSelection() {

		qsa('#flow-wrapper .flow-timeline-pill[data-flow]', pill => {
			const field = Flow.selectionField(pill.dataset.flow);
			const selected = (field !== null && field.checked);
			pill.classList.toggle('flow-timeline-pill-selected', selected);
		});

	}

	static togglePillSelection(pill) {

		const field = Flow.selectionField(pill.dataset.flow);

		if(field === null) {
			return;
		}

		field.checked = !field.checked;
		field.dispatchEvent(new Event('input', {bubbles: true}));
		Flow.syncPillSelection();
		Flow.changeSelection();

	}

	// La semaine d'une bande, en rang continu : l'année est relative à la série
	// (N - 1, N, N + 1) et compte 52 semaines pleines dans ce diagramme.
	static weekIndex(band) {
		return parseInt(band.dataset.year) * 52 + parseInt(band.dataset.week);
	}

	// L'ordre du diagramme, saison comprise : c'est celui que le serveur emploie
	// pour décider de « ce qui suit ».
	static rank(band) {
		return parseInt(band.dataset.season) * 10000 + Flow.weekIndex(band);
	}

	static bandOf(node) {
		return node.firstParent('.flow-timeline-band');
	}

	// Une intervention peut changer de place après n'importe quelle écriture : le
	// diagramme est trié par semaine puis par rang. Plutôt que d'énumérer les
	// gestes qui déplacent — et d'en oublier —, le diagramme garde la photo de sa
	// disposition au rendu précédent et anime l'écart au suivant.
	static settlePositions(wrapper) {

		const origin = wrapper.getBoundingClientRect().top;
		const current = {};

		// La semaine en plus de la hauteur : les interventions qui suivent glissent
		// avec celles qu'on a décalées, sans avoir changé de semaine.
		wrapper.qsa('.flow-timeline-row[data-flow]', row => {
			const band = Flow.bandOf(row);
			current[row.dataset.flow] = {
				top: row.getBoundingClientRect().top - origin,
				week: band === null ? row.dataset.week : Flow.rank(band)
			};
		});

		const before = Flow.positions ?? null;

		Flow.positions = current;

		if(before === null) {
			return;
		}

		// Mêmes interventions, ou rien : une création, une suppression ou un
		// changement d'itinéraire n'est pas un déplacement.
		const avant = Object.keys(before);
		const apres = Object.keys(current);

		if(avant.length !== apres.length || avant.some(flow => current[flow] === undefined)) {
			return;
		}

		const moving = [];
		const changed = [];

		apres.forEach(flow => {

			const row = wrapper.qs('.flow-timeline-row[data-flow="'+ flow +'"]');
			const delta = before[flow].top - current[flow].top;

			if(Math.abs(delta) > 2) {
				moving.push([row, delta]);
			}

			if(before[flow].week !== current[flow].week) {
				changed.push(row);
			}

		});

		if(moving.length === 0 && changed.length === 0) {
			return;
		}

		moving.forEach(([row, delta]) => {
			row.classList.add('flow-timeline-moving');
			row.style.transition = 'none';
			row.style.transform = 'translateY('+ delta +'px)';
		});

		// Il faut que le navigateur ait PEINT la position de départ avant de la
		// relâcher : un getBoundingClientRect() ne force que la mise en page. Le
		// filet couvre l'onglet en arrière-plan, où requestAnimationFrame ne se
		// déclenche pas du tout.
		let released = false;

		const release = () => {

			if(released) {
				return;
			}

			released = true;

			moving.forEach(([row]) => {
				row.style.transition = '';
				row.style.transform = '';
			});

			// La carte porte le voile de MainJuiciness.flash() : le ::after de la ligne sert au repère d'insertion.
			changed.forEach(row => MainJuiciness.flash(row.qs('.flow-timeline-card')));

			setTimeout(() => {
				moving.forEach(([row]) => {
					row.classList.remove('flow-timeline-moving');
					row.style.transform = '';
				});
			}, 450);

		};

		requestAnimationFrame(() => requestAnimationFrame(release));

		setTimeout(release, 150);

	}

	// L'arrivée d'un délai s'arrête à la pastille de son étape, le départ descend
	// jusqu'au bas de sa bande, et le nombre se centre sur le trait entier — trois
	// mesures que le CSS ne peut pas faire, la pastille visée n'étant ni la
	// première ni la dernière ligne de sa semaine.
	static layoutDelays(wrapper) {

		wrapper.qsa('.flow-timeline-delay-end-pill[data-flow]', segment => {

			const pill = wrapper.qs('.flow-timeline-row[data-flow="'+ segment.dataset.flow +'"] .flow-timeline-pill');
			const band = segment.firstParent('.flow-timeline-band');

			if(pill === null || band === null) {
				return;
			}

			const box = pill.getBoundingClientRect();

			segment.style.height = (box.top + box.height / 2 - band.getBoundingClientRect().top) + 'px';

		});

		wrapper.qsa('.flow-timeline-delay-start-row', segment => {

			const band = segment.firstParent('.flow-timeline-band');

			if(band === null) {
				return;
			}

			segment.style.height = (band.getBoundingClientRect().bottom - segment.getBoundingClientRect().top) + 'px';

		});

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

	static startShift(handle, e) {

		const wrapper = qs('#flow-wrapper');
		const row = handle.firstParent('.flow-timeline-row');
		const pill = (row === null) ? null : row.qs('.flow-timeline-pill-movable');
		const band = (row === null) ? null : Flow.bandOf(row);
		const rail = (wrapper === null) ? null : wrapper.qs('.flow-timeline-band .flow-timeline-rail');

		if(wrapper === null || row === null || pill === null || band === null || rail === null) {
			return;
		}

		const season = band.dataset.season;
		const origin = Flow.weekIndex(band);
		const originRank = Flow.rank(band);

		let armed = false;
		let moved = false;
		let target = null;
		let chosen = null;
		let ghost = null;
		let chips = null;
		let followers = [];
		let zone = null;

		const startX = e.clientX;
		const startY = e.clientY;
		const railBox = rail.getBoundingClientRect();
		const railX = railBox.left + railBox.width / 2;

		// Toutes les bandes, repérées par saison et par semaine : c'est ainsi qu'on
		// retrouve la bande d'arrivée de chaque chose qui suit.
		const bands = [];
		wrapper.qsa('.flow-timeline-band[data-week]', node => bands.push(node));

		const landing = (from, delta) => bands.find(node => node.dataset.season === from.dataset.season && Flow.weekIndex(node) === Flow.weekIndex(from) + delta) ?? null;

		const bandAtY = (y) => {

			for(const node of bands) {
				const box = node.getBoundingClientRect();
				if(y >= box.top && y < box.bottom) {
					return node;
				}
			}

			return null;

		};

		const clearTarget = () => wrapper.qsa('.flow-timeline-drop', node => node.classList.remove('flow-timeline-drop'));

		// Une pastille cochée entraîne toute la sélection cochée ; les puces
		// « seule / avec la suite » ne s'affichent alors pas.
		const withSelection = pill.classList.contains('flow-timeline-pill-selected');
		const selected = [];

		if(withSelection) {
			wrapper.qsa('.flow-timeline-pill-selected.flow-timeline-pill-movable', node => {
				if(node !== pill) {
					selected.push(node);
				}
			});
		}

		// « Ce qui suit » : les interventions de rang égal ou postérieur, saisons
		// suivantes comprises — la règle de FlowLib::incrementWeekFollowing().
		const following = [];

		wrapper.qsa('.flow-timeline-pill-movable', node => {
			const nodeBand = Flow.bandOf(node);
			if(node !== pill && nodeBand !== null && Flow.rank(nodeBand) >= originRank) {
				following.push(node);
			}
		});

		// Les périodes qui suivent : chaque trait dessiné, avec ses deux bouts, pour
		// pouvoir en poser le fantôme sur les bandes d'arrivée.
		const periods = [];

		wrapper.qsa('.flow-timeline-lane-start[data-flow]', node => {

			const bandStart = Flow.bandOf(node);

			if(bandStart === null || Flow.rank(bandStart) < originRank) {
				return;
			}

			let bandStop = null;

			wrapper.qsa('.flow-timeline-lane-stop[data-flow="'+ node.dataset.flow +'"]', stop => {
				const stopBand = Flow.bandOf(stop);
				if(bandStop === null && stopBand !== null && stopBand.dataset.season === bandStart.dataset.season) {
					bandStop = stopBand;
				}
			});

			if(bandStop === null) {
				return;
			}

			const lanes = [];
			wrapper.qsa('.flow-timeline-lane[data-flow="'+ node.dataset.flow +'"]', lane => {
				const laneBand = Flow.bandOf(lane);
				if(laneBand !== null && laneBand.dataset.season === bandStart.dataset.season) {
					lanes.push(lane);
				}
			});

			// Les bandes que la période traverse, de son départ à sa fin : c'est sur
			// leurs homologues décalées que se pose le fantôme.
			const covered = bands.filter(node => (
				node.dataset.season === bandStart.dataset.season &&
				Flow.weekIndex(node) >= Flow.weekIndex(bandStart) &&
				Flow.weekIndex(node) <= Flow.weekIndex(bandStop)
			));

			periods.push({start: bandStart, stop: bandStop, covered: covered, lanes: lanes, node: node});

		});

		const withFollowing = () => (withSelection === false && (chosen || 'only') === 'following');

		// Ce qui partira avec la pastille est signalé dès la prise, avant tout
		// mouvement : c'est ce qui rend « seule » et « avec la suite » lisibles.
		const highlight = () => {

			const going = withSelection ? selected : (withFollowing() ? following : []);

			[...following, ...selected].forEach(node => node.classList.toggle('flow-timeline-pill-will-move', going.includes(node)));

			periods.forEach(period => period.lanes.forEach(lane => lane.classList.toggle('flow-timeline-lane-will-move', withFollowing())));

		};

		// La zone du diagramme qui va bouger, entourée d'un pointillé : de la
		// pastille tenue à la dernière chose qui suit.
		const drawZone = (show, delta = 0) => {

			zone?.remove();
			zone = null;

			if(show === false) {
				return;
			}

			let boxes;

			if(delta === 0) {
				boxes = [pill.getBoundingClientRect(), ...following.map(node => node.getBoundingClientRect())];
				periods.forEach(period => period.lanes.forEach(lane => boxes.push(lane.getBoundingClientRect())));
			} else {
				boxes = followers.map(node => node.getBoundingClientRect());
				const arriving = landing(band, delta);
				if(arriving !== null) {
					const box = arriving.getBoundingClientRect();
					boxes.push({top: box.top + 6, bottom: box.top + 6 + 24});
				}
				if(boxes.length === 0) {
					return;
				}
			}

			const top = Math.min(...boxes.map(box => box.top));
			const bottom = Math.max(...boxes.map(box => box.bottom));

			zone = document.createElement('div');
			zone.className = 'flow-timeline-following-zone';
			zone.style.left = (railBox.left - 2) + 'px';
			zone.style.width = (railBox.width + 4) + 'px';
			zone.style.top = (top - 6) + 'px';
			zone.style.height = (bottom - top + 12) + 'px';
			document.body.append(zone);

		};

		// Les fantômes de la suite : posés sur la bande d'arrivée de chacun, ou
		// retirés quand la suite ne bouge pas.
		const drawFollowers = (delta) => {

			followers.forEach(node => node.remove());
			followers = [];

			if(delta === 0 || (withSelection === false && withFollowing() === false)) {
				drawZone(withFollowing(), 0);
				return;
			}

			if(withSelection === false) {

				periods.forEach(period => {

					// Le trait est borné au diagramme : une période qui déborderait de la
					// dernière semaine affichée s'arrête à elle, comme la campagne de
					// récolte sur une série.
					const arriving = period.covered.map(node => landing(node, delta)).filter(node => node !== null);

					if(arriving.length === 0) {
						return;
					}

					const box = period.node.getBoundingClientRect();
					const top = arriving[0].getBoundingClientRect().top;
					const bottom = arriving[arriving.length - 1].getBoundingClientRect().bottom;

					const bar = document.createElement('div');
					bar.className = 'flow-timeline-lane-ghost';
					bar.style.color = period.node.style.color;
					bar.style.left = (box.left + box.width / 2) + 'px';
					bar.style.top = (top + 3) + 'px';
					bar.style.height = Math.max(bottom - top - 6, 6) + 'px';
					document.body.append(bar);
					followers.push(bar);

				});

			}

			// Plusieurs pastilles sur la même bande d'arrivée s'empilent, comme elles
			// le feront une fois posées.
			const stacked = {};

			(withSelection ? selected : following).forEach(node => {

				const arriving = landing(Flow.bandOf(node), delta);

				if(arriving === null) {
					return;
				}

				const key = arriving.dataset.season +'|'+ Flow.weekIndex(arriving);
				stacked[key] = (stacked[key] || 0) + 1;

				const box = arriving.getBoundingClientRect();
				const clone = document.createElement('div');
				clone.className = 'flow-timeline-pill-follower';
				clone.textContent = node.textContent;
				clone.style.cssText = node.getAttribute('style') || '';
				clone.style.left = railX + 'px';
				clone.style.top = (box.top + 6 + 12 + (stacked[key] - 1) * 30) + 'px';
				document.body.append(clone);
				followers.push(clone);

			});

			drawZone(withFollowing(), delta);

		};

		// En « seule », rester sur sa semaine et glisser le long de ses voisines les
		// réordonne : un trait marque où la ligne se posera.
		let insertIndex = null;

		const siblingRows = () => Array.from(band.qsa('.flow-timeline-row[data-flow]')).filter(node => node !== row);

		const clearInsert = () => {
			wrapper.qsa('.flow-timeline-row-insert-before, .flow-timeline-row-insert-after', node => node.classList.remove('flow-timeline-row-insert-before', 'flow-timeline-row-insert-after'));
			insertIndex = null;
		};

		const markInsert = (y) => {

			const rows = siblingRows();

			if(rows.length === 0) {
				return;
			}

			insertIndex = rows.filter(node => {
				const box = node.getBoundingClientRect();
				return y > box.top + box.height / 2;
			}).length;

			if(insertIndex < rows.length) {
				rows[insertIndex].classList.add('flow-timeline-row-insert-before');
			} else {
				rows[rows.length - 1].classList.add('flow-timeline-row-insert-after');
			}

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
				chips.innerHTML = '<span class="flow-timeline-chip flow-timeline-chip-left is-on">'+ Flow.labels.alone +'</span><span class="flow-timeline-chip flow-timeline-chip-right">'+ Flow.labels.withFollowing +'</span>';
			}
			document.body.append(chips);

			place(e.clientX, e.clientY);
			highlight();
			drawZone(withFollowing());

		}, 350);

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

			const over = bandAtY(event.clientY);

			// Une intervention ne change pas de saison au glisser : l'écart de
			// semaines n'aurait aucun sens d'une saison à l'autre.
			target = (over !== null && over.dataset.season === season) ? over : null;

			if(target !== null) {
				target.classList.add('flow-timeline-drop');
			}

			// Sur sa propre semaine, un glissé réordonne — quel que soit le mode :
			// « avec la suite » n'a pas de sens sans changement de semaine.
			if(target === band && withSelection === false) {
				markInsert(event.clientY);
			}

			drawFollowers(target === null ? 0 : Flow.weekIndex(target) - origin);

		};

		const finish = () => {

			clearTimeout(arm);

			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', cancel);

			pill.classList.remove('flow-timeline-pill-holding');
			zone?.remove();
			zone = null;
			wrapper.qsa('.flow-timeline-pill-will-move', node => node.classList.remove('flow-timeline-pill-will-move'));
			wrapper.qsa('.flow-timeline-lane-will-move', node => node.classList.remove('flow-timeline-lane-will-move'));
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

			// Un appui court sans mouvement : c'est un clic, il coche la pastille —
			// mais seulement s'il a commencé sur elle, pas sur la poignée de la fiche.
			if(wasArmed === false) {
				if(moved === false && handle === pill) {
					Flow.togglePillSelection(pill);
				}
				return;
			}

			// Même semaine, en « seule » : c'est un réordonnancement.
			if(chosenTarget === band && chosenIndex !== null && withSelection === false) {
				Flow.movePosition(wrapper, pill, row, rows, chosenIndex);
				return;
			}

			if(chosenTarget === null || chosenTarget === band) {
				return;
			}

			const increment = Flow.weekIndex(chosenTarget) - origin;
			const pills = withSelection ? [pill, ...selected] : [pill];

			pills.forEach(node => node.classList.add('flow-timeline-pill-pending'));

			const settle = () => pills.forEach(node => node.classList.remove('flow-timeline-pill-pending'));

			const body = new URLSearchParams();

			body.append('increment', increment);

			if(withSelection) {
				pills.forEach(node => body.append('ids[]', node.dataset.flow));
			} else {
				body.append('id', pill.dataset.flow);
				body.append('mode', mode);
			}

			new Ajax.Query(wrapper)
				.url(withSelection ? '/sequence/flow:doIncrementWeekCollection' : '/sequence/flow:doIncrementWeekPlan')
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
			} catch(exception) {
			}
		}

	}

	// Le rang dans la semaine : l'ordre des lignes de la bande tel que le trait
	// d'insertion l'a montré, renvoyé tel quel.
	static movePosition(wrapper, pill, row, rows, index) {

		const ordered = [...rows.slice(0, index), row, ...rows.slice(index)];
		const current = Array.from(Flow.bandOf(row).qsa('.flow-timeline-row[data-flow]'));

		if(ordered.map(node => node.dataset.flow).join(',') === current.map(node => node.dataset.flow).join(',')) {
			return;
		}

		const body = new URLSearchParams();

		body.append('id', row.dataset.flow);
		body.append('positions', JSON.stringify(ordered.map(node => [parseInt(node.dataset.flow), node.dataset.field])));

		pill.classList.add('flow-timeline-pill-pending');

		const settle = () => pill.classList.remove('flow-timeline-pill-pending');

		new Ajax.Query(wrapper)
			.url('/sequence/flow:doPosition')
			.body(body)
			.fetch()
			.then(json => (json && json.__redirect === undefined) && settle(), settle);

	}

	static startResize(handle, e) {

		const wrapper = qs('#flow-wrapper');
		const lane = handle.parentElement;
		const band = handle.firstParent('.flow-timeline-band');

		if(wrapper === null || band === null) {
			return;
		}

		const bound = handle.dataset.bound;
		const flow = lane.dataset.flow;

		// L'autre bout du même trait : c'est lui qui ne bouge pas, et qui borne le
		// déplacement de celui qu'on tient. Une période qui court sur plusieurs
		// saisons se dessine dans chacune : on prend le bout de la sienne.
		const opposites = [];
		wrapper.qsa('.flow-timeline-lane-'+ (bound === 'start' ? 'stop' : 'start') +'[data-flow="'+ flow +'"]', node => opposites.push(node));

		const oppositeBand = opposites
			.map(node => node.firstParent('.flow-timeline-band'))
			.find(node => node !== null && node.dataset.season === band.dataset.season) ?? null;

		if(oppositeBand === null) {
			return;
		}

		const bands = [];
		wrapper.qsa('.flow-timeline-band[data-week]', node => bands.push(node));

		const fixed = bands.indexOf(oppositeBand);

		// Le trait d'origine s'estompe pendant le geste : c'est le fantôme qui porte
		// la nouvelle étendue, comme la pastille tenue laisse la sienne au fantôme.
		const lanes = [];
		wrapper.qsa('.flow-timeline-lane[data-flow="'+ flow +'"]', node => {
			const laneBand = node.firstParent('.flow-timeline-band');
			if(laneBand !== null && laneBand.dataset.season === band.dataset.season) {
				lanes.push(node);
			}
		});

		let target = null;
		let ghost = null;

		handle.classList.add('flow-timeline-lane-handle-holding');
		lanes.forEach(node => node.classList.add('flow-timeline-lane-holding'));

		const clear = () => {
			bands.forEach(node => node.classList.remove('flow-timeline-band-extend', 'flow-timeline-drop'));
			ghost?.remove();
			ghost = null;
		};

		// Le trait tel qu'il sera une fois la poignée lâchée, du haut de sa première
		// semaine au bas de sa dernière.
		const drawGhost = (first, last) => {

			const box = lane.getBoundingClientRect();
			const top = bands[first].getBoundingClientRect().top;
			const bottom = bands[last].getBoundingClientRect().bottom;

			ghost = document.createElement('div');
			ghost.className = 'flow-timeline-lane-ghost';
			ghost.style.color = lane.style.color;
			ghost.style.left = (box.left + box.width / 2) + 'px';
			ghost.style.top = (top + 3) + 'px';
			ghost.style.height = Math.max(bottom - top - 6, 6) + 'px';

			// La poignée se dessine au bout que l'on tire : sans elle, le rond reste
			// à sa place de départ et c'est le trait seul qui semble bouger.
			ghost.innerHTML = '<div class="flow-timeline-lane-ghost-handle flow-timeline-lane-ghost-handle-'+ bound +'"></div>';

			document.body.append(ghost);

		};

		const bandAt = (y) => {

			for(const node of bands) {
				const box = node.getBoundingClientRect();
				if(y >= box.top && y < box.bottom) {
					return node;
				}
			}

			return null;

		};

		const move = (event) => {

			event.preventDefault();

			const next = bandAt(event.clientY);

			if(next === target) {
				return;
			}

			clear();
			target = next;

			if(target === null) {
				return;
			}

			const to = bands.indexOf(target);

			// Une période dure au moins deux semaines et ne traverse pas une saison :
			// hors de ces bornes, la cible n'est pas retenue.
			if(
				target.dataset.season !== band.dataset.season ||
				(bound === 'start' ? to >= fixed : to <= fixed)
			) {
				target = null;
				return;
			}

			const first = (bound === 'start') ? to : fixed;
			const last = (bound === 'start') ? fixed : to;

			bands.slice(first, last + 1).forEach(node => node.classList.add('flow-timeline-band-extend'));

			target.classList.add('flow-timeline-drop');

			drawGhost(first, last);

		};

		const stop = () => {

			document.removeEventListener('pointermove', move);
			document.removeEventListener('pointerup', up);
			document.removeEventListener('pointercancel', cancel);

			handle.classList.remove('flow-timeline-lane-handle-holding');
			lanes.forEach(node => node.classList.remove('flow-timeline-lane-holding'));
			clear();

		};

		const up = () => {

			stop();

			if(target === null || target === band) {
				return;
			}

			Flow.moveBound(wrapper, band, target, flow, bound);

		};

		// Un geste interrompu par le navigateur ne vaut pas un lâcher : rien n'est
		// enregistré.
		const cancel = () => stop();

		document.addEventListener('pointermove', move);
		document.addEventListener('pointerup', up);
		document.addEventListener('pointercancel', cancel);

	}

	static moveBound(wrapper, band, target, flow, bound) {

		const increment = (parseInt(target.dataset.year) - parseInt(band.dataset.year)) * 52 + parseInt(target.dataset.week) - parseInt(band.dataset.week);

		if(increment === 0) {
			return;
		}

		const body = new URLSearchParams();

		body.append('id', flow);
		body.append('bound', bound);
		body.append('increment', increment);

		const pill = wrapper.qs('.flow-timeline-pill[data-flow="'+ flow +'"]');

		pill?.classList.add('flow-timeline-pill-pending');

		const settle = () => pill?.classList.remove('flow-timeline-pill-pending');

		new Ajax.Query(wrapper)
			.url('/sequence/flow:doIncrementWeekBound')
			.body(body)
			.fetch()
			.then(json => (json && json.__redirect === undefined) && settle(), settle);

	}

	static changeSelection() {

		Flow.syncPillSelection();

		return Batch.changeSelection('#batch-flow', '#batch-flow-one',function(selection) {

		if(selection.length > 1) {
			qsa('.batch-update', node => node.hide());
		} else {
			qsa('.batch-update', (node) => {
				node.setAttribute('href', '/sequence/flow:update?id='+ selection[0].value);
				node.removeHide();
			});
		}

		});

	}


}