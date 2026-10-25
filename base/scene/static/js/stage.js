// Calendrier d'une série et stade à une date, pour toute scène qui montre des cultures dans le temps.
// Module ES sans import ni horloge : chaque date est un argument, au format 'AAAA-MM-JJ', et se compte en jours entiers.
// Compter en millisecondes d'heure locale ferait dépendre un stade du fuseau du navigateur : la quinzaine qui suit un semis
// compte une heure de moins quand elle traverse le passage à l'heure d'été.

export function sceneCreateStage() {

	const DAY = 86400000;

	// La mise en place d'une série : ce qui la fait entrer sur sa planche
	const START_ACTIONS = ['semis-direct', 'plantation'];

	// Durée pendant laquelle une série vient d'être semée ou plantée, en jours
	const FRESH = 14;

	const toDay = text => Date.UTC(Number(text.slice(0, 4)), Number(text.slice(5, 7)) - 1, Number(text.slice(8, 10))) / DAY;
	const toText = day => day === null ? null : new Date(day * DAY).toISOString().slice(0, 10);
	const min = days => days.length ? Math.min(...days) : null;
	const max = days => days.length ? Math.max(...days) : null;

	// Le lundi d'une semaine ISO, en jours : la semaine 1 est celle du 4 janvier
	const mondayDay = (year, week) => {
		const january4 = Date.UTC(year, 0, 4) / DAY;
		return january4 - (new Date(january4 * DAY).getUTCDay() + 6) % 7 + (week - 1) * 7;
	};

	// '2026-W14' : le format des semaines de la base
	const weekTextDay = text => mondayDay(Number(text.slice(0, 4)), Number(text.slice(6)));

	const week = date => {
		const day = toDay(date);
		// Le jeudi de la semaine dit son année : une semaine ISO appartient à l'année qui en porte quatre jours
		const thursday = day + 4 - (new Date(day * DAY).getUTCDay() || 7);
		const year = new Date(thursday * DAY).getUTCFullYear();
		return { year, week: Math.ceil((thursday - Date.UTC(year, 0, 1) / DAY + 1) / 7) };
	};

	return {

		week,

		monday: (year, week) => toText(mondayDay(year, week)),

		// Le prévu d'après les semaines planifiées, le réalisé d'après ce qui est fait.
		// series : { start, stop, cycle, status, cultivations: [{ harvestWeeksExpected }] }, start le premier jour sur la planche
		// et stop le dernier. tasks : [{ fqn, planned, done }] de la série, harvests : [{ date }], dates en 'AAAA-MM-JJ' ou null.
		// Une série qui n'a pas ses deux bouts n'a pas de calendrier : null, que l'appelant teste avant at() et harvestWeeks()
		timeline(series, tasks, harvests, season) {

			if(series.start === null || series.stop === null) {
				return null;
			}

			const seriesStart = toDay(series.start), seriesStop = toDay(series.stop);

			const starts = tasks.filter(task => START_ACTIONS.includes(task.fqn)).map(task => ({
				fqn: task.fqn,
				planned: task.planned === null ? null : toDay(task.planned),
				done: task.done === null ? null : toDay(task.done)
			}));
			const harvestTasks = tasks.filter(task => task.fqn === 'recolte');
			const nursery = tasks.filter(task => task.fqn === 'semis-pepiniere' && task.done !== null).map(task => toDay(task.done));

			// Une vivace déjà en place tient sa planche dès la première semaine de la saison : ses tâches de mise en place datent
			// d'une autre année et ne disent rien de celle-ci
			const perennialRunning = series.cycle === 'perennial' && series.start >= `${season}-01-01` && series.start < `${season}-01-08`;

			const plannedStart = perennialRunning ? seriesStart : (min(starts.map(task => task.planned ?? task.done).filter(day => day !== null)) ?? seriesStart);
			const realStart = perennialRunning || starts.length === 0 ? seriesStart : min(starts.filter(task => task.done !== null).map(task => task.done));
			// Une mise en place sans date ne dit pas laquelle vient d'abord : comptée au jour 0, elle passerait devant toutes les autres
			const firstStart = starts.filter(task => (task.planned ?? task.done) !== null).sort((a, b) => (a.planned ?? a.done) - (b.planned ?? b.done))[0];

			const plannedHarvestWeeks = [
				...harvestTasks.filter(task => task.planned !== null).map(task => toDay(task.planned)),
				...series.cultivations.flatMap(cultivation => cultivation.harvestWeeksExpected ?? []).map(weekTextDay)
			];
			const realHarvestDays = [
				...harvests.map(harvest => toDay(harvest.date)),
				...harvestTasks.filter(task => task.done !== null && task.planned === null).map(task => toDay(task.done))
			];

			// Une semaine prévue de récolte court jusqu'à son dimanche, une récolte saisie tient la semaine qui la suit
			const plannedHarvestFirst = min(plannedHarvestWeeks);
			const plannedHarvestLast = plannedHarvestWeeks.length ? max(plannedHarvestWeeks) + 6 : null;
			const realHarvestFirst = min(realHarvestDays);
			const realHarvestLast = max(realHarvestDays);

			const closed = series.status === 'closed';
			const plannedEnd = Math.max(seriesStop, plannedHarvestLast ?? 0);
			const realEnd = closed ? Math.max(seriesStop, realHarvestLast ?? 0) : Math.max(plannedEnd, realHarvestLast ?? 0);

			let realLast;
			if(realHarvestLast !== null && closed) {
				realLast = realHarvestLast + 6;
			} else if(plannedHarvestLast !== null && realHarvestLast !== null) {
				realLast = Math.max(plannedHarvestLast, realHarvestLast + 6);
			} else if(plannedHarvestLast !== null) {
				realLast = plannedHarvestLast;
			} else {
				realLast = realHarvestLast === null ? null : realHarvestLast + 6;
			}

			return {
				kind: firstStart?.fqn === 'semis-direct' ? 'sowing' : 'planting',
				nurseryDone: toText(min(nursery)),
				planned: {
					start: toText(plannedStart),
					harvestFirst: toText(plannedHarvestFirst),
					harvestLast: toText(plannedHarvestLast),
					end: toText(plannedEnd)
				},
				real: {
					start: toText(realStart),
					harvestFirst: toText(realHarvestFirst ?? plannedHarvestFirst),
					harvestLast: toText(realLast),
					end: toText(realEnd)
				}
			};

		},

		// Le stade d'une série à une date, selon le prévu ('planned') ou le réalisé ('real'). on dit si elle occupe sa planche,
		// progress son avancée de la mise en place à la première récolte, growth sa taille en part de la taille adulte.
		// Les dates 'AAAA-MM-JJ' se comparent telles quelles, l'ordre des textes est celui des jours
		at(timeline, date, mode) {

			const span = timeline[mode];
			const off = key => ({ key, on: false, progress: 0, growth: 0 });

			if(span.start === null || date < span.start) {
				if(mode === 'real' && timeline.nurseryDone !== null && timeline.nurseryDone <= date) {
					return off('nursery');
				}
				if(mode === 'real' && span.start === null && date >= timeline.planned.start) {
					return off('late');
				}
				return off('todo');
			}

			if(date > span.end) {
				return off('done');
			}

			if(span.harvestLast !== null && date > span.harvestLast) {
				return { key: 'over', on: true, progress: 1, growth: 1 };
			}

			if(span.harvestFirst !== null && date >= span.harvestFirst) {
				return { key: 'harvest', on: true, progress: 1, growth: 1 };
			}

			const day = toDay(date), start = toDay(span.start), harvestFirst = toDay(span.harvestFirst ?? span.end);
			const progress = Math.max(0, Math.min(1, (day - start) / Math.max(1, harvestFirst - start)));
			const fresh = day - start < FRESH;

			// Un semis lève de presque rien et s'étoffe tard, un plant arrive déjà formé
			if(timeline.kind === 'sowing') {
				return { key: fresh ? 'sown' : 'growing', on: true, progress, growth: .04 + .96 * progress ** 1.4 };
			}

			return { key: fresh ? 'planted' : 'growing', on: true, progress, growth: .22 + .78 * progress };

		},

		// La première et la dernière semaine de récolte, [{ year, week }, { year, week }], ou null pour une série sans récolte
		// prévue ni saisie
		harvestWeeks(timeline, mode) {

			const span = timeline[mode];

			if(span.harvestFirst === null) {
				return null;
			}

			return [week(span.harvestFirst), week(span.harvestLast)];

		}

	};

}
