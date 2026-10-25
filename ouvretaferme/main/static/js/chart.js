class UtilChart {

	static get BAR_COLOR() {
		return '#1a6277';
	}

	static get BAR_COLOR_PALE() {
		return '#ccdde2';
	}

	static defaultsApplied = false;

	// La police des graphiques, posée au premier graphique et non au chargement de ce fichier : la
	// bibliothèque n'arrive qu'avec un graphique rendu, et un écran sans graphique charge ce fichier sans
	// elle. Les graphiques qui ne passent pas par `create*` l'appellent eux aussi, sinon leur police
	// dépendrait de l'écran ouvert avant eux.
	static applyDefaults() {

		if(UtilChart.defaultsApplied) {
			return;
		}

		Chart.defaults.font.family = '"DM Sans", sans-serif';
		Chart.defaults.font.size = 0.9 * rem();
		Chart.defaults.plugins.tooltip.titleFont = {weight: '500'};

		UtilChart.defaultsApplied = true;

	}

	/**
	 * Une série en barres. `legend: false` retire la légende d'un graphique dont le titre nomme déjà la
	 * seule série ; `integer: true` gradue en entiers un axe qui compte des personnes ou des avis, et
	 * n'écrit plus 0,5 sous trois inscriptions.
	 */
	static createBar(target, labelBar, valuesBar, labels, labelSuffix = '€', {legend = true, integer = false} = {}) {

		this.applyDefaults();

		new Chart(target, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						label: labelBar,
						data: valuesBar,
						backgroundColor: this.BAR_COLOR,
						borderRadius: 3,
						barPercentage: 0.7,
						categoryPercentage: 1
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						grid: {
							display: false
						},
						// Un libellé incliné prend un tiers du graphique au téléphone : Chart.js en saute plutôt.
						ticks: {
							maxRotation: 0
						}
					},
					y: {
						ticks: {
							precision: integer ? 0 : undefined,
							callback: value => value + ' ' + labelSuffix
						}
					}
				},
				plugins: {
					legend: {
						display: legend,
						position: 'bottom'
					},
					tooltip: {
						callbacks: {
							label: context => context.dataset.label + ' : ' + context.formattedValue + ' ' + labelSuffix
						}
					}
				}
			}
		});

	}

	/**
	 * Des modalités comparées à une date : une barre chacune, d'une seule couleur, la couleur ne portant pas
	 * la modalité. Le graphique se couche quand la place de chaque barre est plus étroite que le plus long mot
	 * des libellés : les modalités se lisent alors de haut en bas dans l'ordre de l'écran, toutes nommées, et
	 * le cadre grandit d'une rangée par modalité. La forme se décide en mesurant d'abord, puis s'écrit, et se
	 * rejoue quand le cadre change de largeur.
	 */
	static createComparison(target, values, labels, labelSuffix = '€') {

		this.applyDefaults();

		const frame = target.parentElement;

		// Chart.js redimensionne le canvas, ce qui rend au contexte sa police par défaut : elle se pose à chaque
		// mesure.
		const context = target.getContext('2d');
		const measure = text => {
			context.font = Chart.defaults.font.size + 'px ' + Chart.defaults.font.family;
			return context.measureText(text).width;
		};

		const words = labels.map(label => String(label).split(' '));
		const longestWord = Math.max(...words.flat().map(word => measure(word)));

		const getSlot = width => (width / labels.length - rem());

		const isLying = width => (getSlot(width) < longestWord);

		const getScales = lying => ({
			[lying ? 'y' : 'x']: {
				grid: {
					display: false
				},
				ticks: {
					autoSkip: false,
					maxRotation: 0
				}
			},
			[lying ? 'x' : 'y']: {
				beginAtZero: true,
				ticks: {
					callback: value => value + ' ' + labelSuffix
				}
			}
		});

		// Debout, le libellé passe à la ligne quand il dépasse la place de sa barre ; couché, il tient sur la
		// sienne.
		const getLabels = width => isLying(width) ? labels : words.map(line => line.reduce((lines, word) => {

			const last = lines.length - 1;

			if(last >= 0 && measure(lines[last] + ' ' + word) <= getSlot(width)) {
				lines[last] += ' ' + word;
			} else {
				lines.push(word);
			}

			return lines;

		}, []));

		const getHeight = lying => lying ? 'calc(' + labels.length + ' * 2rem + 3rem)' : '';

		const width = frame.clientWidth;
		const lying = isLying(width);

		frame.style.height = getHeight(lying);

		new Chart(target, {
			type: 'bar',
			data: {
				labels: getLabels(width),
				datasets: [
					{
						data: values,
						backgroundColor: this.BAR_COLOR,
						borderRadius: 3,
						barPercentage: 0.7,
						categoryPercentage: 1
					}
				]
			},
			options: {
				indexAxis: lying ? 'y' : 'x',
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: getScales(lying),
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							title: items => labels[items[0].dataIndex],
							label: item => item.formattedValue + ' ' + labelSuffix
						}
					}
				},
				// Les libellés se recoupent à chaque largeur ; l'axe et la hauteur ne changent qu'avec la forme.
				onResize: (chart, size) => {

					const lyingNow = isLying(size.width);

					if(lyingNow !== (chart.options.indexAxis === 'y')) {
						frame.style.height = getHeight(lyingNow);
						chart.options.indexAxis = lyingNow ? 'y' : 'x';
						chart.options.scales = getScales(lyingNow);
					}

					chart.data.labels = getLabels(size.width);
					chart.update();

				}
			}
		});

	}

	static createDoubleBar(target, labelBar, valuesBar, label2Bar, values2Bar, labels, labelSuffix = '€') {

		this.applyDefaults();

		new Chart(target, {
			type: 'bar',
			plugins: [{
				afterUpdate: chart => {
					chart.getDatasetMeta(0).data.forEach(bar => bar.x += bar.width / 4);
					chart.getDatasetMeta(1).data.forEach(bar => bar.x -= bar.width / 4);
				}
			}],
			data: {
				labels: labels,
				datasets: [
					{
						label: labelBar,
						data: valuesBar,
						backgroundColor: this.BAR_COLOR,
						borderRadius: 3,
						barPercentage: 1.2,
						categoryPercentage: 1,
						order: 1
					},
					{
						label: label2Bar,
						data: values2Bar,
						backgroundColor: this.BAR_COLOR_PALE,
						borderRadius: 3,
						barPercentage: 1.2,
						categoryPercentage: 1,
						order: 2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						grid: {
							display: false
						}
					},
					y: {
						ticks: {
							callback: value => value + ' ' + labelSuffix
						}
					}
				},
				plugins: {
					legend: {
						position: 'bottom'
					},
					tooltip: {
						callbacks: {
							label: context => context.dataset.label + ' : ' + context.formattedValue + ' ' + labelSuffix
						}
					}
				}
			}
		});

	}

	static createLines(target, series, labels, labelSuffix = '€') {

		this.applyDefaults();

		const colors = this.getDefaultColors();

		// Une courbe par couleur de la palette, dans l'ordre que l'écran donne à ses séries : les suivantes
		// sont barrées dans la légende, et un clic les trace en tirets, sur les couleurs reprises du début.
		const isBeyond = index => (index >= colors.length);

		new Chart(target, {
			type: 'line',
			data: {
				labels: labels,
				datasets: series.map((serie, index) => ({
					label: serie.label,
					data: serie.values,
					borderColor: colors[index % colors.length],
					backgroundColor: isBeyond(index) ? 'white' : colors[index % colors.length],
					borderDash: isBeyond(index) ? [6, 4] : [],
					hidden: isBeyond(index),
					spanGaps: false,
					tension: 0.2,
					pointRadius: 2
				}))
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						grid: {
							display: false
						}
					},
					y: {
						beginAtZero: true,
						ticks: {
							callback: value => value + ' ' + labelSuffix
						}
					}
				},
				plugins: {
					legend: {
						position: 'bottom'
					},
					tooltip: {
						callbacks: {
							label: context => context.dataset.label + ' : ' + context.formattedValue + ' ' + labelSuffix
						}
					}
				}
			}
		});

	}

	// `period` ([début, fin]) fixe l'axe au lieu de le borner aux points : la courbe s'arrête à son
	// dernier point et l'axe continue jusqu'à la fin de la période.
	static createTimeLine(target, values, dates, labelSuffix = '€', period = null) {

		this.applyDefaults();

		const day = 86400000;
		const times = dates.map(date => Date.parse(date));
		const first = (period === null) ? times[0] : Date.parse(period[0]);
		const days = Math.round((((period === null) ? times[times.length - 1] : Date.parse(period[1])) - first) / day);

		const format = (time, options) => new Date(time).toLocaleDateString(document.documentElement.lang || 'fr', {timeZone: 'UTC', ...options});

		// Chart.js cale le canvas sur son parent : borner le parent en rem laisse le navigateur suivre la
		// fenêtre et la taille de la racine, sans mesure. 5rem par point pour le tracé, 4rem pour l'axe des
		// valeurs et les marges. Une période fixée compte ses jours et non ses points : elle prend la largeur
		// de la carte.
		if(period === null) {
			target.parentElement.style.maxWidth = 'calc(' + times.length + ' * 5rem + 4rem)';
		}

		new Chart(target, {
			type: 'line',
			data: {
				datasets: [
					{
						data: values.map((value, index) => ({x: times[index], y: value})),
						borderColor: this.BAR_COLOR,
						backgroundColor: this.BAR_COLOR,
						tension: 0,
						pointRadius: 3
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						type: 'linear',
						min: first,
						max: first + days * day,
						grid: {
							display: false
						},
						// Une échelle linéaire gradue sur des millisecondes rondes, qui tombent au milieu d'un jour et
						// pas sur les extrémités : des jours entiers, du premier au dernier, un tous les 80 px, largeur
						// d'une date dans la police du graphique.
						afterBuildTicks: axis => {

							const steps = Math.max(1, Math.min(days, Math.floor(axis.maxWidth / 80) - 1));

							axis.ticks = Array.from({length: steps + 1}, (value, step) => ({value: first + Math.round(step * days / steps) * day}));

						},
						ticks: {
							autoSkip: false,
							maxRotation: 0,
							callback: value => format(value, {day: 'numeric', month: 'short', year: '2-digit'})
						}
					},
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							callback: value => value + ' ' + labelSuffix
						}
					}
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							title: items => format(items[0].parsed.x, {day: 'numeric', month: 'long', year: 'numeric'}),
							label: context => context.formattedValue + ' ' + labelSuffix
						}
					}
				}
			}
		});

	}

	static createTimeLines(target, series, labelSuffix = '€', yearly = false, period = null) {

		this.applyDefaults();

		const day = 86400000;

		// `yearly` superpose les années sur un seul axe, du 1er janvier au 31 décembre : chaque date y est
		// ramenée à une même année, bissextile pour qu'un 29 février y ait sa place. `period` fixe l'axe
		// sur de vraies dates, comme `createTimeLine()`.
		if(yearly) {
			period = ['2024-01-01', '2024-12-31'];
		}

		const times = series.map(serie => serie.dates.map(date => Date.parse(yearly ? '2024' + date.substring(4) : date)));
		const first = (period === null) ? Math.min(...times.flat()) : Date.parse(period[0]);
		const days = Math.round((((period === null) ? Math.max(...times.flat()) : Date.parse(period[1])) - first) / day);
		const colors = this.getTimeLineColors();

		const format = (time, options) => new Date(time).toLocaleDateString(document.documentElement.lang || 'fr', {timeZone: 'UTC', ...options});

		const radius = 3;
		const gaps = times.map(serie => (Math.max(...serie) - Math.min(...serie)) / (serie.length - 1));

		// `chartArea` n'existe pas à la première lecture des options, avant la mise en page.
		const isDense = context => (
			context.chart.chartArea !== undefined &&
			gaps[context.datasetIndex] * context.chart.chartArea.width / (days * day) < radius * 2 + 2
		);

		if(period === null) {
			target.parentElement.style.maxWidth = 'calc(' + new Set(times.flat()).size + ' * 5rem + 4rem)';
		}

		new Chart(target, {
			type: 'line',
			data: {
				datasets: series.map((serie, index) => ({
					label: serie.label,
					data: serie.values.map((value, position) => ({x: times[index][position], y: value, date: serie.dates[position]})),
					borderColor: colors[index % colors.length],
					backgroundColor: colors[index % colors.length],
					borderWidth: (index === 0) ? 3 : 2,
					tension: 0,
					pointRadius: context => isDense(context) ? 0 : radius,
					// Chart.js ne survole que la surface d'un point : sans rayon, il lui faut une zone.
					pointHitRadius: context => isDense(context) ? radius * 2 : 1
				}))
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						type: 'linear',
						min: first,
						max: first + days * day,
						grid: {
							display: false
						},
						afterBuildTicks: axis => {

							const steps = Math.max(1, Math.min(days, Math.floor(axis.maxWidth / 80) - 1));

							axis.ticks = Array.from({length: steps + 1}, (value, step) => ({value: first + Math.round(step * days / steps) * day}));

						},
						ticks: {
							autoSkip: false,
							maxRotation: 0,
							callback: value => format(value, yearly ? {day: 'numeric', month: 'short'} : {day: 'numeric', month: 'short', year: '2-digit'})
						}
					},
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							callback: value => value + ' ' + labelSuffix
						}
					}
				},
				plugins: {
					legend: {
						position: 'bottom'
					},
					tooltip: {
						callbacks: {
							title: items => (yearly ? [] : [items[0].dataset.label]).concat(format(Date.parse(items[0].raw.date), {day: 'numeric', month: 'long', year: 'numeric'})),
							label: context => context.formattedValue + ' ' + labelSuffix
						}
					}
				}
			}
		});

	}

	// Ni orange ni rouge, qui disent une alerte dans l'interface.
	static getTimeLineColors() {

		return [
			this.BAR_COLOR,
			'#2FBB75', // --color-vert-emeraude
			'#8290FD', // --color-lavande
			'#676767', // --color-gris-charbon-clair
			'#22829F', // --accounting-light
			'#A8B9AB'  // --color-vert-sauvage
		];

	}

	static createMonthly(target, values, labels, legends, colors, labelSuffix = '€') {

		this.applyDefaults();

		if(colors === undefined) {
			colors = this.getDefaultColors();
		}

		let datasets = [];

		values.forEach((elementValues, key) => {

			let data = [];

			for(let month = 0; month < 12; month++) {
				data[month] = elementValues[month];
			}

			datasets[datasets.length] = {
				label: legends[key],
				data: data,
				borderColor: colors[key],
				backgroundColor: colors[key],
				fill: (key === 0) ? 'origin' : '-1'
			}
		});

		new Chart(target, {
			type: 'line',
			data: {
				labels: labels,
				datasets: datasets
			},
			options: {
				responsive: true,
				animation: {
					duration: 0
				},
				scales: {
					x: {
						stacked: true
					},
					y: {
						stacked: true,
						position: 'left',
						ticks: {
							callback: function(value) {
								return value + ' '+ labelSuffix;
							}
						}
					}
				},
				plugins: {
					filler: {
					  propagate: false
					},
					legend: {
						position: 'bottom'
					},
				},
				maintainAspectRatio: false
			}
		});

	}

	static createBarLine(target, labelBar, valuesBar, labelLine, valuesLine, labels) {

		this.applyDefaults();

		new Chart(target, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: labelLine,
						data: valuesLine,
						backgroundColor: '#212529',
						borderColor: '#212529',
						yAxisID: 'line'
					},
					{
						label: labelBar,
						data: valuesBar,
						backgroundColor: this.BAR_COLOR,
						borderRadius: 3,
						type: 'bar',
						yAxisID: 'bar'
					}
				]
			},
			options: {
				responsive: true,
				animation: {
					duration: 0
				},
				scales: {
					line: {
						position: 'right',
						beginAtZero: true,
						ticks: {
							color: '#212529'
						}
					},
					bar: {
						position: 'left',
						ticks: {
							callback: function(value) {
								return value + ' €';
							}
						}
					}
				},
				plugins: {
					legend: {
						position: 'bottom'
					}
				},
				maintainAspectRatio: false
			}
		});

	}

	static createPie(target, values, labels, colors) {

		this.applyDefaults();

		if(colors === undefined) {
			colors = this.getDefaultColors();
		}

		new Chart(target, {
			type: 'doughnut',
			plugins: [
				{
					id: 'pieLabels',
					// Les valeurs sont déjà des pourcentages, ChartUi::buildPie les calcule
					// côté serveur : elles font office de pourcentages telles quelles.
					beforeLayout: chart => this.reservePieLabelSpace(chart, values),
					afterUpdate: chart => this.fitPieHeight(chart, values),
					afterDatasetsDraw: chart => this.drawPieLabels(chart, values)
				}
			],
			data: {
				labels: labels,
				datasets: [
					{
						data: values,
						backgroundColor: colors,
					}
				]
			},
			options: {
				responsive: true,
				animation: {
					duration: 0
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(data) {
								return ' '+ data.label + ' → ' + data.formattedValue +' %';
							}
						}
					}
				},
				maintainAspectRatio: false
			}
		});

	}

	static getPieLabelFont() {
		return '0.9rem "DM Sans", sans-serif';
	}

	static get PIE_LABEL_LINE_HEIGHT() {
		return Math.round(1.2 * 0.9 * rem());
	}

	/**
	 * Réserve de place autour de l'anneau pour les libellés et leurs filets.
	 *
	 * Un seul côté est réservé, celui de la colonne ; l'autre n'a que douze pixels et
	 * l'anneau s'y étend d'autant. La réserve suit le libellé le plus long — « Tomate » et
	 * « Dotations aux amortissements » n'appellent pas la même marge — dans la limite du
	 * tiers de la largeur. Seules comptent les parts assez grosses pour être légendées.
	 */
	static reservePieLabelSpace(chart, percents) {

		const ctx = chart.ctx;

		ctx.save();
		ctx.font = this.getPieLabelFont();

		const longest = (chart.data.labels ?? []).reduce(
			(widest, label, index) => (percents[index] >= 3) ? Math.max(widest, ctx.measureText(String(label)).width) : widest,
			0
		);

		ctx.restore();

		const column = Math.min(Math.round(chart.width * 0.33), Math.max(40, Math.round(longest) + 26));
		const side = this.getPieColumnSide(percents);

		chart.options.layout.padding = {
			left: (side === 'left') ? column : 12,
			right: (side === 'left') ? 12 : column,
			top: 32,
			bottom: 32
		};

	}

	/**
	 * Le pourcentage dans la part, le libellé juste à côté d'elle.
	 *
	 * `percents` porte un pourcentage par part, dans l'ordre des données, et c'est l'appelant
	 * qui l'établit : ici les valeurs sont déjà des pourcentages, ailleurs ce sont des montants
	 * dont il faut prendre la part d'un total.
	 */
	static drawPieLabels(chart, percents) {

		this.drawPieCallouts(chart, percents);
		this.drawPiePercents(chart, percents);

	}

	/**
	 * Le libellé de chaque part, posé à côté d'elle et relié par un filet de sa couleur.
	 *
	 * Trois zones : une colonne d'un seul côté de l'anneau, et deux bandes au-dessus et en
	 * dessous. Le côté de la colonne est celui où se trouve le gros des parts à légender —
	 * les libellés rejoignent ainsi leur part par le chemin le plus court, et le côté
	 * opposé, laissé libre, rend sa place à l'anneau. Les filets ne traversent jamais le
	 * disque.
	 *
	 * En dessous de 3 %, la part est laissée à l'infobulle : les fines tranches pointent presque
	 * au même endroit du cercle et leurs libellés empilés occuperaient plus de place que
	 * l'anneau. Les parts nulles n'ont, elles, aucune position sur le cercle.
	 */
	static drawPieCallouts(chart, percents) {

		const ctx = chart.ctx;
		const colors = chart.data.datasets[0].backgroundColor;

		const side = this.getPieColumnSide(percents);
		const items = this.getPieItems(chart, percents);

		ctx.save();
		ctx.font = this.getPieLabelFont();
		ctx.textBaseline = 'middle';

		this.drawPieColumn(chart, items.filter(item => item.zone === side), colors, side);
		this.drawPieBand(chart, items.filter(item => item.zone === 'top'), colors, 'top');
		this.drawPieBand(chart, items.filter(item => item.zone === 'bottom'), colors, 'bottom');

		ctx.restore();

	}

	/**
	 * Le côté où placer la colonne de libellés : celui qui porte le plus de parts à légender.
	 *
	 * Déduit des seules valeurs, sans passer par les arcs : la réserve de marge est calculée
	 * avant que Chart.js ne les ait placés, et les deux doivent tomber d'accord sous peine de
	 * réserver la place d'un côté pour écrire de l'autre.
	 *
	 * À égalité, la gauche : c'est le côté où l'œil commence sa lecture.
	 */
	static getPieColumnSide(percents) {

		const total = percents.reduce((sum, percent) => sum + percent, 0);

		if(total <= 0) {
			return 'left';
		}

		let angle = -Math.PI / 2;
		let left = 0;
		let right = 0;

		percents.forEach(percent => {

			const sweep = percent / total * 2 * Math.PI;

			if(percent >= 3) {
				(Math.cos(angle + sweep / 2) < 0) ? left++ : right++;
			}

			angle += sweep;

		});

		return (right > left) ? 'right' : 'left';

	}

	/**
	 * Les parts qui méritent un libellé, avec leur point d'attache et la zone où il ira.
	 *
	 * Le tracé et la mesure de hauteur partent tous deux d'ici : si les deux répartissaient
	 * les parts à leur façon, la place réservée ne serait pas celle qu'on occupe.
	 */
	static getPieItems(chart, percents) {

		const side = this.getPieColumnSide(percents);
		const items = [];

		chart.getDatasetMeta(0).data.forEach((arc, index) => {

			if(arc.circumference === 0 || percents[index] < 3) {
				return;
			}

			// Le point d'attache d'abord, la zone ensuite : c'est l'endroit le plus avancé
			// vers la colonne que la part permette qui décide où va son libellé.
			const anchor = this.getPieAnchorAngle(arc, side);
			const towards = (side === 'left') ? (Math.cos(anchor) < 0) : (Math.cos(anchor) > 0);

			items.push({
				index: index,
				arc: arc,
				anchor: anchor,
				zone: towards ? side : ((Math.sin(anchor) < 0) ? 'top' : 'bottom'),
				label: String(chart.data.labels[index] ?? '')
			});

		});

		return items;

	}

	static get PIE_MIN_HEIGHT() {
		return 220;
	}

	static get PIE_MAX_HEIGHT() {
		return 480;
	}

	/**
	 * Ajuste la hauteur du conteneur à ce que le graphique a réellement à dessiner.
	 *
	 * Une hauteur fixe convient à un seul cas de figure : trois parts s'y perdent dans le
	 * vide, douze libellés s'y chevauchent. Celle-ci se déduit du contenu — l'anneau prend
	 * la largeur qu'on lui laisse, et la colonne de gauche impose la sienne quand elle est
	 * chargée.
	 *
	 * Le calcul ne dépend que de la largeur et des angles, pas de la hauteur courante :
	 * il converge donc au premier passage. Le compteur et le seuil de huit pixels ne sont
	 * là que pour qu'un cas non prévu s'arrête au lieu de boucler.
	 *
	 * L'ajustement a lieu pendant la mise à jour, où les parts sont placées mais où rien
	 * n'est encore peint, et non après le dessin : différer ne serait-ce que d'une trame
	 * ferait apparaître la hauteur de départ, puis la bonne — un sursaut qui fait surgir
	 * puis disparaître la barre de défilement de la page. Le redimensionnement est donc
	 * synchrone, et c'est le garde-fou ci-dessus qui borne la ré-entrance.
	 */
	static fitPieHeight(chart, percents) {

		const container = chart.canvas.parentNode;

		// Un graphique dans un panneau replié n'a pas de largeur : il n'y a rien à mesurer,
		// et le faire lui ferait dépenser ses tentatives avant même d'être visible.
		if(container === null || chart.width <= 0) {
			return;
		}

		const height = this.getPieNeededHeight(chart, percents);

		if(Math.abs(height - chart.height) <= 8 || (chart.$pieFits ?? 0) >= 3) {
			return;
		}

		chart.$pieFits = (chart.$pieFits ?? 0) + 1;

		container.style.height = height + 'px';
		chart.resize();

	}

	/**
	 * La hauteur que réclame le contenu : l'anneau à pleine largeur, ou la colonne de
	 * libellés si elle est plus haute.
	 */
	static getPieNeededHeight(chart, percents) {

		const ctx = chart.ctx;
		const items = this.getPieItems(chart, percents);
		const padding = chart.options.layout.padding;

		const side = this.getPieColumnSide(percents);

		ctx.save();
		ctx.font = this.getPieLabelFont();

		const available = (side === 'left')
			? (chart.chartArea.left - 24)
			: (chart.width - chart.chartArea.right - 24);

		let column = 0;

		items.filter(item => item.zone === side).forEach((item, index) => {
			column += this.wrapToWidth(ctx, item.label, available).length * this.PIE_LABEL_LINE_HEIGHT + (index > 0 ? 4 : 0);
		});

		ctx.restore();

		// Les bandes ne coûtent leur hauteur que si elles portent quelque chose.
		const bands =
			(items.some(item => item.zone === 'top') ? padding.top : 0) +
			(items.some(item => item.zone === 'bottom') ? padding.bottom : 0);

		// Un anneau rond et aussi grand que la largeur le permet : la hauteur qui lui rend
		// un disque plein plutôt qu'un disque bridé par le haut et le bas.
		const ring = chart.width - padding.left - padding.right;

		// La colonne garde douze pixels de marge en haut et en bas, comme au tracé.
		return Math.round(Math.min(this.PIE_MAX_HEIGHT, Math.max(this.PIE_MIN_HEIGHT, ring + bands, column + 24)));

	}

	/**
	 * Les libellés de la colonne, empilés verticalement et alignés sur le bord du graphique.
	 */
	static drawPieColumn(chart, items, colors, side) {

		if(items.length === 0) {
			return;
		}

		const ctx = chart.ctx;
		const left = (side === 'left');

		const available = left ? (chart.chartArea.left - 24) : (chart.width - chart.chartArea.right - 24);
		const textX = left ? (chart.chartArea.left - 20) : (chart.chartArea.right + 20);
		const direction = left ? 1 : -1;

		items.forEach(item => {
			item.lines = this.wrapToWidth(ctx, item.label, available);
			item.y = item.arc.y + Math.sin(item.anchor) * (item.arc.outerRadius + 10);
		});

		items.sort((first, second) => first.y - second.y);

		this.spreadPieLabels(items, 12, chart.height - 12);

		items.forEach(item => {

			// Filet en trois points : bord de la part, coude à la hauteur du libellé, puis
			// segment horizontal jusqu'à lui.
			this.strokePieLine(ctx, colors[item.index], [
				[item.arc.x + Math.cos(item.anchor) * (item.arc.outerRadius + 2), item.arc.y + Math.sin(item.anchor) * (item.arc.outerRadius + 2)],
				[item.arc.x + Math.cos(item.anchor) * (item.arc.outerRadius + 12), item.y],
				[textX + direction * 5, item.y]
			]);

			ctx.textAlign = left ? 'right' : 'left';
			this.fillPieLabel(ctx, item, textX, item.y);

		});

	}

	/**
	 * Les libellés du haut ou du bas, alignés sur une bande et écartés horizontalement.
	 *
	 * Le filet ne part pas du milieu angulaire de la part mais du point de celle-ci le plus
	 * proche du sommet — ou du bas — de l'anneau, donc le plus à gauche qu'elle permette.
	 * Une part à droite se raccorde ainsi par un trait court et presque vertical, au lieu du
	 * long trait oblique qui partait de son flanc droit.
	 */
	static drawPieBand(chart, items, colors, side) {

		if(items.length === 0) {
			return;
		}

		const ctx = chart.ctx;
		const direction = (side === 'top') ? -1 : 1;

		// Chaque libellé se partage équitablement la largeur de la bande.
		const available = Math.max(60, chart.width / items.length - 12);
		const y = (side === 'top') ? 14 : chart.height - 14;

		items.forEach(item => {
			item.lines = this.wrapToWidth(ctx, item.label, available, 1);
			item.width = Math.max(...item.lines.map(line => ctx.measureText(line).width));
			item.x = item.arc.x + Math.cos(item.anchor) * (item.arc.outerRadius + 14);
		});

		items.sort((first, second) => first.x - second.x);

		this.spreadPieBand(items, chart.width);

		items.forEach(item => {

			const startX = item.arc.x + Math.cos(item.anchor) * (item.arc.outerRadius + 2);
			const startY = item.arc.y + Math.sin(item.anchor) * (item.arc.outerRadius + 2);
			const elbowX = item.arc.x + Math.cos(item.anchor) * (item.arc.outerRadius + 10);
			const elbowY = y - direction * 8;

			// Tracé en équerre plutôt qu'en diagonale : une part proche de 3 heures reste loin
			// de sa bande, et un long trait oblique se lit alors comme une rayure en travers du
			// graphique là où deux segments francs se suivent des yeux.
			this.strokePieLine(ctx, colors[item.index], [
				[startX, startY],
				[elbowX, elbowY],
				[item.x, elbowY]
			]);

			ctx.textAlign = 'center';
			this.fillPieLabel(ctx, item, item.x, y);

		});

	}

	/**
	 * L'angle du point d'une part le plus avancé vers la colonne, où accrocher son filet.
	 *
	 * Une part qui couvre l'heure visée — neuf heures pour une colonne à gauche, trois heures
	 * pour une colonne à droite — s'y accroche. C'est le cas des grosses parts, dont le milieu
	 * angulaire peut pointer d'un côté alors qu'elles occupent tout le flanc opposé. Sinon
	 * c'est celle de ses deux extrémités qui penche le plus vers la colonne, ce qui, pour une
	 * part entièrement du mauvais côté, revient à son extrémité la plus proche du sommet ou du
	 * bas de l'anneau.
	 *
	 * Un léger retrait évite d'accrocher pile sur la frontière avec la part voisine, où le
	 * filet semblerait appartenir à l'une comme à l'autre.
	 */
	static getPieAnchorAngle(arc, side) {

		const inset = Math.min(0.15, (arc.endAngle - arc.startAngle) / 4);
		const from = arc.startAngle + inset;
		const to = arc.endAngle - inset;

		const candidates = [from, to];

		// Chart.js compte les angles depuis le haut et peut dépasser un tour : l'heure visée
		// est donc cherchée à un tour près, dans un sens comme dans l'autre.
		const targets = (side === 'left')
			? [-Math.PI, Math.PI, 3 * Math.PI]
			: [-2 * Math.PI, 0, 2 * Math.PI];

		targets.forEach(target => {
			if(target >= from && target <= to) {
				candidates.push(target);
			}
		});

		return candidates.reduce(
			(best, angle) => ((side === 'left') === (Math.cos(angle) < Math.cos(best))) ? angle : best
		);

	}

	static strokePieLine(ctx, color, points) {

		ctx.strokeStyle = color;
		ctx.lineWidth = 1.5;

		ctx.beginPath();
		ctx.moveTo(points[0][0], points[0][1]);
		points.slice(1).forEach(point => ctx.lineTo(point[0], point[1]));
		ctx.stroke();

	}

	static fillPieLabel(ctx, item, x, y) {

		// Les lignes s'empilent de part et d'autre de la hauteur retenue, de sorte que le filet
		// arrive au milieu du bloc de texte et non sur sa première ligne.
		const offset = (item.lines.length - 1) / 2;

		ctx.fillStyle = '#212529';

		item.lines.forEach((line, index) => {
			ctx.fillText(line, x, y + (index - offset) * this.PIE_LABEL_LINE_HEIGHT);
		});

	}

	/**
	 * Écarte verticalement les libellés d'une colonne jusqu'à ce qu'ils cessent de se
	 * chevaucher : deux parts fines voisines pointent presque au même endroit du cercle.
	 */
	static spreadPieLabels(column, minY, maxY) {

		const height = item => item.lines.length * this.PIE_LABEL_LINE_HEIGHT;
		const spacing = (first, second) => (height(first) + height(second)) / 2 + 4;

		for(let index = 1; index < column.length; index++) {
			column[index].y = Math.max(column[index].y, column[index - 1].y + spacing(column[index - 1], column[index]));
		}

		const last = column[column.length - 1];
		const overflow = (last !== undefined) ? (last.y + height(last) / 2) - maxY : 0;

		if(overflow > 0) {
			column.forEach(item => item.y -= overflow);
		}

		for(let index = 0; index < column.length; index++) {

			const floor = (index === 0)
				? minY + height(column[index]) / 2
				: column[index - 1].y + spacing(column[index - 1], column[index]);

			column[index].y = Math.max(column[index].y, floor);

		}

	}

	/**
	 * Même principe que spreadPieLabels(), mais sur l'axe horizontal pour les bandes.
	 */
	static spreadPieBand(band, width) {

		const spacing = (first, second) => (first.width + second.width) / 2 + 10;

		for(let index = 1; index < band.length; index++) {
			band[index].x = Math.max(band[index].x, band[index - 1].x + spacing(band[index - 1], band[index]));
		}

		const last = band[band.length - 1];
		const overflow = (last !== undefined) ? (last.x + last.width / 2 + 4) - width : 0;

		if(overflow > 0) {
			band.forEach(item => item.x -= overflow);
		}

		for(let index = 0; index < band.length; index++) {

			const floor = (index === 0)
				? band[index].width / 2 + 4
				: band[index - 1].x + spacing(band[index - 1], band[index]);

			band[index].x = Math.max(band[index].x, floor);

		}

	}

	/**
	 * Le libellé découpé pour tenir dans la largeur donnée : deux lignes au plus par défaut,
	 * coupées entre les mots, et la dernière abrégée si le compte n'y est toujours pas.
	 */
	static wrapToWidth(ctx, text, maxWidth, maxLines = 2) {

		if(ctx.measureText(text).width <= maxWidth) {
			return [text];
		}

		const lines = [];
		let current = '';

		text.split(' ').forEach(word => {

			const candidate = (current === '') ? word : current + ' ' + word;

			if(ctx.measureText(candidate).width <= maxWidth || current === '') {
				current = candidate;
			} else {
				lines.push(current);
				current = word;
			}

		});

		lines.push(current);

		if(lines.length > maxLines) {
			lines.length = maxLines;
			lines[maxLines - 1] = lines[maxLines - 1] + '…';
		}

		return lines.map(line => this.truncateToWidth(ctx, line, maxWidth));

	}

	static drawPiePercents(chart, percents) {

		const ctx = chart.ctx;
		const colors = chart.data.datasets[0].backgroundColor;

		ctx.save();
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		ctx.font = '500 0.9rem "DM Sans", sans-serif';

		chart.getDatasetMeta(0).data.forEach((arc, index) => {

			const percent = percents[index];

			// En dessous, la part est plus étroite que son texte : le pourcentage reste
			// dans l'infobulle plutôt que de déborder sur ses voisines. Le libellé, lui,
			// est dans la couronne et reste donc lisible.
			if(percent < 5) {
				return;
			}

			const angle = (arc.startAngle + arc.endAngle) / 2;
			const radius = (arc.innerRadius + arc.outerRadius) / 2;

			ctx.fillStyle = this.getReadableColor(colors[index]);
			ctx.fillText(percent + ' %', arc.x + Math.cos(angle) * radius, arc.y + Math.sin(angle) * radius);

		});

		ctx.restore();

	}
	static truncateToWidth(ctx, text, maxWidth) {

		if(ctx.measureText(text).width <= maxWidth) {
			return text;
		}

		const characters = Array.from(text);

		while(characters.length > 1) {

			characters.pop();

			const shortened = characters.join('').trimEnd() + '…';

			if(ctx.measureText(shortened).width <= maxWidth) {
				return shortened;
			}

		}

		return '…';

	}

	/**
	 * Blanc ou noir selon la luminance du fond : les couleurs des parts viennent souvent de
	 * l'appelant — couleur d'une action, gris des « Autres » — et un blanc uniforme y serait
	 * illisible. Les deux notations hexadécimales sont acceptées, '#AAA' étant employé tel quel
	 * par ChartUi. Sur une couleur non reconnue, on garde le blanc de la palette par défaut,
	 * qui est sombre.
	 */
	static getReadableColor(background) {

		const hex = String(background ?? '').replace('#', '');
		const full = (hex.length === 3) ? hex.replace(/./g, character => character + character) : hex;

		if(/^[0-9a-f]{6}$/i.test(full) === false) {
			return 'white';
		}

		const [red, green, blue] = [0, 2, 4].map(index => parseInt(full.slice(index, index + 2), 16));
		const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

		return (luminance > 0.5) ? '#212529' : 'white';

	}

	static getDefaultColors() {

		return [
			'#1D4B53', // --color-brand, bleu canard foncé
			'#2FBB75', // --color-vert-emeraude
			'#FF6A58', // --color-terracota
			'#22829F', // --accounting-light
			'#8290FD', // --color-lavande
			'#B6231A', // --color-terracota
			'#676767', // --color-gris-charbon-clair
			'#A8B9AB'  // --color-vert-sauvage
		];

	}

}