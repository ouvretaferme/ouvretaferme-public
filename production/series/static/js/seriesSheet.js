/**
 * La fiche d'une série au jour montré : son stade, ses dates prévues et réalisées, ses cultures, ce qu'elle a donné, ses
 * planches et ses interventions. Le plan 3D (`MapSceneSheet`) et le plan d'assolement (`SeriesPlaceSheet`) la rendent tous
 * deux, sur la donnée au format de la scène (`\series\SeriesLib::getForScene()`, `getSheet()`), avec le calendrier du socle
 * (`base/scene/static/js/stage.js`). Tout ce qu'elle écrit vient d'ici : les deux écrans ne divergent pas.
 */
class SeriesSheet {

	// Prévu et réalisé ne s'écrivent tous deux qu'à plus de six jours d'écart : en deçà, c'est la même semaine à un jour près
	static GAP_DAYS = 6;

	constructor({ texts, plan, stage }) {

		this.texts = texts;
		this.plan = plan;
		this.stage = stage;

		this.series = new Map(plan.series.map(series => [series.id, series]));
		this.beds = new Map(plan.beds.map(bed => [bed.id, bed]));
		this.plots = new Map(plan.plots.map(plot => [plot.id, plot]));
		this.zones = new Map(plan.zones.map(zone => [zone.id, zone]));
		this.actions = new Map(plan.actions.map(action => [action.id, action]));
		this.tasks = SeriesSheet.group(plan.tasks, plan.columns.tasks);
		this.harvests = SeriesSheet.group(plan.harvests, plan.columns.harvests);

		this.bedsBySeries = new Map();

		for(const place of plan.places) {

			if(this.bedsBySeries.has(place.series) === false) {
				this.bedsBySeries.set(place.series, new Set());
			}

			this.bedsBySeries.get(place.series).add(place.bed);

		}

	}

	/**
	 * Le calendrier d'une série (`stage.timeline()`), ou null quand rien ne la date. `actions` porte le fqn de chaque action par
	 * son id : la mise en place d'une série se lit à l'action de ses interventions. Une série se date sur sa propre saison, que
	 * la scène qui la pose montre la sienne ou une voisine.
	 */
	static timeline(stage, series, tasks, harvests, actions) {

		return stage.timeline(
			series,
			tasks.map(task => ({
				fqn: actions.get(task.action) ?? null,
				planned: SeriesSheet.monday(stage, task.plannedWeek),
				done: task.done ? (task.doneDate ?? SeriesSheet.monday(stage, task.doneWeek)) : null
			})),
			harvests,
			series.season
		);

	}

	/**
	 * Remplit `container` de la fiche de la série `id` au jour `date`, au format 'AAAA-MM-JJ'. Un rang qui n'aurait rien à
	 * dire, une date non saisie ou une récolte encore nulle, ne s'écrit pas.
	 */
	render(container, id, date) {

		const series = this.series.get(id);
		const tasksAll = this.tasks.get(id) ?? [];
		const harvestsAll = this.harvests.get(id) ?? [];
		const fqns = new Map([...this.actions.values()].map(action => [action.id, action.fqn]));

		const timeline = SeriesSheet.timeline(this.stage, series, tasksAll, harvestsAll, fqns);
		const stage = this.stage;

		const rows = SeriesSheet.rows(container);

		if(timeline !== null) {

			const state = document.createElement('span');
			state.className = 'badge';
			state.textContent = this.stageText(timeline, stage.at(timeline, date, 'real'));

			SeriesSheet.row(rows, this.texts.today, state);

			const planned = this.stageText(timeline, stage.at(timeline, date, 'planned'));

			if(planned !== state.textContent) {
				SeriesSheet.row(rows, this.texts.plannedToday, planned);
			}

			// Sans semis ni plantation faits, le réalisé de la timeline est la date de la série : seul le prévu se dit
			const started = this.action(tasksAll, (timeline.kind === 'sowing') ? 'semis-direct' : 'plantation', true);
			const start = this.compare(timeline.planned.start, (started !== null) ? timeline.real.start : null, started);

			if(start !== null) {
				SeriesSheet.row(rows, (timeline.kind === 'sowing') ? this.texts.sowing : this.texts.planting, start);
			}

			// Le réalisé de la timeline prend le prévu tant que rien n'est récolté : seule une récolte saisie le dit
			const picked = harvestsAll.length > 0;
			const harvest = this.compare(timeline.planned.harvestFirst, picked ? timeline.real.harvestFirst : null, this.action(tasksAll, 'recolte', false));

			if(harvest !== null) {
				SeriesSheet.row(rows, this.texts.harvest, harvest);
			}

		}

		for(const cultivation of series.cultivations) {
			SeriesSheet.stack(rows, cultivation.plant, cultivation.varieties.length > 0 ? cultivation.varieties.join(', ') : this.texts.noVariety, true);
		}

		const harvested = harvestsAll.filter(harvest => harvest.date <= date);
		const quantities = SeriesSheet.quantities(this.texts, harvested);

		if(quantities !== null) {
			SeriesSheet.row(rows, this.texts.harvested, quantities);
		}

		this.renderYield(rows, series, harvested);

		const beds = [...(this.bedsBySeries.get(id) ?? [])].map(bed => this.beds.get(bed));
		SeriesSheet.stack(rows, this.texts.beds, beds.map(bed => bed.name ?? this.placeName(bed)).join(', '), false);

		const tasks = tasksAll
			.map(task => ({ task, action: this.actions.get(task.action) ?? null, planned: SeriesSheet.monday(stage, task.plannedWeek), done: task.done ? (task.doneDate ?? SeriesSheet.monday(stage, task.doneWeek)) : null }))
			.sort((a, b) => (a.planned ?? a.done ?? '').localeCompare(b.planned ?? b.done ?? ''));

		if(tasks.length === 0) {
			SeriesSheet.row(rows, this.texts.noTask);
		} else {
			SeriesSheet.header(rows, [this.texts.tasks, this.texts.weeks]);
		}

		// Une intervention répétée, un désherbage chaque semaine, se lit en une ligne par état : les faites avec la dernière, les
		// prévues avec la première. Une intervention faite après le jour montré est encore à venir ce jour-là
		const groups = new Map();

		for(const { task, action, planned, done } of tasks) {

			const finished = (done !== null && done <= date);
			const key = task.action +'-'+ finished;
			const when = finished ? done : (planned ?? done);

			if(groups.has(key) === false) {
				groups.set(key, { action, finished, count: 0, first: when, last: when });
			}

			const group = groups.get(key);
			group.count++;

			if(when !== null) {
				group.first = (group.first === null || when < group.first) ? when : group.first;
				group.last = (group.last === null || when > group.last) ? when : group.last;
			}

		}

		for(const { action, finished, count, first, last } of groups.values()) {

			const label = document.createElement('span');
			label.className = 'series-sheet-task-label';
			label.append(action?.name ?? '');

			if(count > 1) {
				const counter = document.createElement('span');
				counter.className = 'util-counter';
				counter.textContent = count;
				label.append(counter);
			}

			const name = document.createElement('span');
			name.className = 'series-sheet-task';
			name.append(SeriesSheet.mark(action?.color ?? null), label);

			let weeks;

			if(first === null) {
				weeks = this.texts.taskTodo;
			} else if(this.weekText(first) === this.weekText(last)) {
				weeks = this.weekText(first);
			} else {
				weeks = this.texts.taskWeeks.replace('{from}', this.weekText(first)).replace('{to}', this.weekText(last));
			}

			SeriesSheet.row(rows, name, finished ? SeriesSheet.tint(weeks, action) : SeriesSheet.muted(weeks));

		}

	}

	stageText(timeline, stage) {

		if(stage.key === 'late') {
			return (timeline.kind === 'sowing') ? this.texts.stage.lateSowing : this.texts.stage.latePlanting;
		}

		return this.texts.stage[stage.key];

	}

	placeName(bed) {

		const plot = this.plots.get(bed.plot);
		const zone = this.zones.get(plot.zone) ?? null;

		return [zone?.name, plot.name].filter(name => name).join(' › ');

	}

	weekText(date) {
		return SeriesSheet.weekText(this.stage, this.texts, this.plan.season, date);
	}

	/**
	 * Le rendement de chaque culture au jour montré, ses récoltes jusqu'à ce jour sur l'aire de la série, par unité : une
	 * botte ou une pièce compte en kg quand le serveur en donne le poids (`weights`). Une récolte sans culture ne revient
	 * qu'à une série qui n'en a qu'une. Le rang ne s'écrit pas sans aire ni récolte, et une série associée en porte une
	 * ligne par culture, son nom en tête : les kg de deux espèces sur la même aire ne s'additionnent pas.
	 */
	renderYield(rows, series, harvests) {

		if(series.area === null || series.area <= 0) {
			return;
		}

		const lines = [];

		for(const cultivation of series.cultivations) {

			const sums = new Map();

			for(const { cultivation: id, quantity, unit } of harvests) {

				if(id !== cultivation.id && (id !== null || series.cultivations.length > 1)) {
					continue;
				}

				const weight = cultivation.weights[unit] ?? null;
				const counted = (weight === null) ? unit : 'kg';

				sums.set(counted, (sums.get(counted) ?? 0) + quantity * (weight ?? 1));

			}

			const values = Object.keys(this.texts.unit)
				.filter(unit => sums.has(unit))
				.map(unit => [unit, Math.round(sums.get(unit) / series.area * 10) / 10])
				.filter(([, value]) => value > 0)
				.map(([unit, value]) => this.texts.yieldValue.replace('{value}', value.toLocaleString(document.documentElement.lang || 'fr') +' '+ this.texts.unit[unit]));

			if(values.length > 0) {
				lines.push({ cultivation, text: values.join(', ') });
			}

		}

		if(lines.length === 0) {
			return;
		}

		if(series.cultivations.length === 1) {
			SeriesSheet.row(rows, this.texts.yield, lines[0].text);
			return;
		}

		const list = document.createElement('div');

		for(const { cultivation, text } of lines) {
			const line = document.createElement('div');
			line.append(cultivation.plant +' '+ text);
			list.append(line);
		}

		SeriesSheet.stack(rows, this.texts.yield, list, false);

	}

	/**
	 * L'action `fqn` d'une intervention de la série, faite si `done` le demande, ou null.
	 */
	action(tasks, fqn, done) {

		const task = tasks.find(task => (done === false || task.done) && this.actions.get(task.action)?.fqn === fqn);

		return (task === undefined) ? null : this.actions.get(task.action);

	}

	/**
	 * La semaine réalisée, teintée par l'`action` qui la date, la prévue barrée devant elle quand elles s'écartent, la
	 * prévue seule et grise tant que rien n'est fait, ou null quand aucune n'est saisie.
	 */
	compare(planned, real, action) {

		if(real === null) {
			return (planned === null) ? null : SeriesSheet.muted(this.weekText(planned));
		}

		const done = SeriesSheet.tint(this.weekText(real), action);

		if(planned !== null && Math.abs(SeriesSheet.daysBetween(planned, real)) > SeriesSheet.GAP_DAYS) {

			const before = document.createElement('span');
			before.className = 'series-sheet-planned';
			before.textContent = this.weekText(planned);

			const both = document.createElement('span');
			both.append(before, done);

			return both;

		}

		return done;

	}

	/**
	 * Une semaine faite, dans la teinte de son action comme sa pastille : la marque garde le contraste de son encre sur les
	 * teintes claires, que le texte coloré perdait. Sans action, rien ne dit qu'elle est faite : elle reste grise, comme une
	 * semaine prévue.
	 */
	static tint(text, action) {

		if(action === null) {
			return SeriesSheet.muted(text);
		}

		const tint = document.createElement('span');
		tint.className = 'badge';
		tint.style.setProperty('--badge-tint', action.color);
		tint.textContent = text;

		return tint;

	}

	// Une semaine seulement prévue reste grise : la teinte dit ce qui est fait
	static muted(text) {

		const muted = document.createElement('span');
		muted.className = 'color-muted';
		muted.textContent = text;

		return muted;

	}

	/**
	 * « s41 » pour une date de la saison, « s2, 2027 » pour une date d'une autre année.
	 */
	static weekText(stage, texts, season, date) {

		const { year, week } = stage.week(date);
		const template = (year === season) ? texts.week : texts.weekYear;

		return template.replace('{week}', week).replace('{year}', year);

	}

	/**
	 * Des récoltes écrites par unité, « 12 kg, 3 bottes », ou null sans récolte.
	 */
	static quantities(texts, harvests) {

		const sums = new Map();

		for(const { quantity, unit } of harvests) {
			sums.set(unit, (sums.get(unit) ?? 0) + quantity);
		}

		if(sums.size === 0) {
			return null;
		}

		return Object.keys(texts.unit).filter(unit => sums.has(unit)).map(unit => [unit, sums.get(unit)]).map(([unit, sum]) => (Math.round(sum * 10) / 10).toLocaleString(document.documentElement.lang || 'fr') +' '+ texts.unit[unit]).join(', ');

	}

	/**
	 * Les tuples d'une liste de la scène (`tasks`, `harvests`), nommés par `columns` et rangés par série.
	 */
	static group(rows, columns) {

		const grouped = new Map();

		for(const row of rows) {

			const named = Object.fromEntries(columns.map((column, index) => [column, row[index]]));

			if(grouped.has(named.series) === false) {
				grouped.set(named.series, []);
			}

			grouped.get(named.series).push(named);

		}

		return grouped;

	}

	// Les dates du socle sont des textes 'AAAA-MM-JJ', lus en UTC : un passage à l'heure d'été ne décale aucun jour
	static daysBetween(from, to) {
		return (Date.parse(to) - Date.parse(from)) / 86400000;
	}

	static monday(stage, week) {
		return (week === null) ? null : stage.monday(Number(week.slice(0, 4)), Number(week.slice(6)));
	}

	static rows(parent) {

		const rows = document.createElement('div');
		rows.className = 'util-rows';

		parent.append(rows);

		return rows;

	}

	static label(text) {

		const label = document.createElement('div');
		label.className = 'util-label';
		label.textContent = text;

		return label;

	}

	// Le rang qui nomme les colonnes des suivants : chaque cellule est l'intitulé d'une colonne hors d'un tableau.
	static header(rows, texts) {

		const row = document.createElement('div');
		row.className = 'util-row';

		texts.forEach(text => {
			const cell = document.createElement('div');
			cell.className = 'util-table-header';
			cell.textContent = text;
			row.append(cell);
		});

		rows.append(row);

	}

	static row(rows, label, value = null) {

		const row = document.createElement('div');
		row.className = 'util-row';

		const name = document.createElement('div');
		name.append(label);
		row.append(name);

		if(value !== null) {
			const cell = document.createElement('div');
			cell.append(value);
			row.append(cell);
		}

		rows.append(row);

	}

	// Une valeur sans borne de longueur passe sous son libellé : à droite, la valeur d'un util-row tient sur une ligne (`flex: none`,
	// component.css) et recouvre le libellé dès qu'elle prend la largeur. Grise sous une donnée, une culture ou une intervention, pour
	// ne pas lire deux noms à égalité ; noire sous un intitulé, comme la valeur d'un rang voisin
	static stack(rows, label, value, muted) {

		const row = document.createElement('div');
		row.className = 'util-row';

		const cell = document.createElement('div');

		const name = document.createElement('div');
		name.append(label);

		const below = document.createElement('div');
		below.classList.toggle('color-muted', muted);
		below.append(value);

		cell.append(name, below);
		row.append(cell);
		rows.append(row);

	}

	static mark(color) {

		const mark = document.createElement('span');
		mark.className = 'util-dot';
		mark.style.setProperty('--util-dot', color ?? 'var(--muted)');

		return mark;

	}

}
