class SequenceDensityView {

	/**
	 * Les textes d'un dessin gardent la même taille à l'écran quelle que soit sa largeur : la
	 * feuille de style multiplie leur taille par `--density-view-unit`, les unités de dessin par
	 * pixel, qui se reprend à chaque redimensionnement. Un dessin masqué (menu fermé) n'a pas de
	 * largeur, il attend d'être montré.
	 *
	 * Les cotes entre rangs se réduisent ensemble (`--density-view-edge-scale`) dès qu'une seule ne
	 * tient pas dans sa place (`data-space`, en unités de dessin) avec une demi-hauteur de texte
	 * d'écart : la colonne garde une taille de texte. Toutes restent écrites (« faut écrire tous
	 * les intervals », le 29/09) : sous 9 px à l'écran, elles passent en quinconce sur deux
	 * lignes, ce qui double à peu près leur place, avant de se réduire encore s'il le faut. « cm »,
	 * en tête de la colonne, recule ensuite le long de la planche s'il touche la première cote
	 * (`data-center`, la distance entre leurs milieux). Les mesures se prennent à taille pleine,
	 * l'écriture vient après.
	 */
	static fit(svg) {

		const layout = () => {

			if(svg.clientWidth > 0) {
				this.layout(svg, svg.viewBox.baseVal.width / svg.clientWidth);
			}

		};

		// Le premier rendu n'attend pas l'observateur, qui ne se déclenche qu'à la trame suivante,
		// et jamais dans un onglet masqué.
		layout();

		new ResizeObserver(layout).observe(svg);

	}

	/**
	 * La mise en page des textes pour `unit` unités de dessin par pixel : celle de l'écran, ou
	 * celle d'une image exportée (`ToolboxDensity.download`).
	 */
	static layout(svg, unit) {

		const texts = Array.from(svg.querySelectorAll('[data-space]'));

		svg.style.setProperty('--density-view-unit', unit);
		svg.style.setProperty('--density-view-edge-scale', 1);

		const labels = texts.map(text => ({
			length: text.getComputedTextLength(),
			space: parseFloat(text.dataset.space),
			center: parseFloat(text.dataset.center)
		}));

		const cm = svg.querySelector('.density-view-cote-unit');
		const cmLength = cm ? cm.getComputedTextLength() : 0;
		const size = parseFloat(getComputedStyle(texts[0] ?? svg).fontSize) / unit;

		let scale = this.getScale(labels, size * unit);

		// En quinconce, une cote n'a plus pour voisines sur sa ligne que celles de deux rangs plus
		// loin : elle gagne de chaque côté la moitié de l'écart voisin, donc le plus petit des deux
		// en tout, pour rester centrée. La première n'a que « cm » devant elle, qui recule s'il le faut.
		const staggered = (size * scale < 9 && labels.length > 2);

		if(staggered) {

			const spaces = labels.map((label, position) => label.space + Math.min(
				labels[position - 1]?.space ?? Infinity,
				labels[position + 1]?.space ?? Infinity
			));

			labels.forEach((label, position) => label.space = spaces[position]);

			scale = this.getScale(labels, size * unit);

		}

		// « cm » garde de la première cote la même demi-hauteur de texte que les cotes entre elles.
		const height = size * unit * scale;
		const shift = (labels.length > 0) ?
			Math.max(0, (labels[0].length + cmLength) * scale / 2 + height / 2 - labels[0].center) :
			0;

		svg.style.setProperty('--density-view-edge-scale', scale);

		// La seconde ligne s'écarte de la planche d'une hauteur de ligne, perpendiculairement à la
		// colonne, devant la rotation posée par le serveur, gardée à la première mise en page.
		texts.forEach((text, position) => {
			text.dataset.rotate ??= text.getAttribute('transform');
			const offset = (staggered && position % 2 === 1) ? height * 1.4 : 0;
			text.setAttribute('transform', 'translate('+ (-offset * Math.sin(Math.PI / 6)) +' '+ (-offset * Math.cos(Math.PI / 6)) +') '+ text.dataset.rotate);
		});

		// Une plantule d'au moins 12 px de large à l'écran, sans dépasser la place que lui laissent
		// ses voisines (`data-seedling-space`), et jamais plus petite que dessinée.
		if(svg.dataset.seedlingWidth !== undefined) {
			const width = parseFloat(svg.dataset.seedlingWidth);
			svg.style.setProperty('--density-view-seedling-scale', Math.max(1, Math.min(12 * unit, parseFloat(svg.dataset.seedlingSpace)) / width));
		}

		// Sous la coupe, les passe-pieds descendent d'une ligne quand l'un touche la planche, et le
		// cadre s'allonge d'autant : sur un téléphone, les trois libellés ne tiennent pas côte à côte.
		// Le cadre de départ est gardé à la première mise en page, et getBBox() ignore la translation
		// posée par une mise en page précédente.
		const plank = svg.querySelector('.density-view-cote-strong');
		const alleys = Array.from(svg.querySelectorAll('.density-view-cote-alley'));

		if(plank && alleys.length > 0) {

			const box = plank.getBBox();
			const lower = alleys.some(alley => {
				const other = alley.getBBox();
				return other.x < box.x + box.width && box.x < other.x + other.width;
			});
			const line = lower ? box.height * 1.2 : 0;

			svg.dataset.viewBox ??= svg.getAttribute('viewBox');
			const [x, y, width, height] = svg.dataset.viewBox.split(' ').map(parseFloat);

			svg.setAttribute('viewBox', [x, y, width, height + line].join(' '));
			alleys.forEach(alley => alley.setAttribute('transform', 'translate(0 '+ line +')'));

		}

		// Le recul s'écrit devant la rotation posée par le serveur, gardée à la première mise en page.
		if(cm) {
			cm.dataset.rotate ??= cm.getAttribute('transform');
			cm.setAttribute('transform', 'translate('+ (shift * Math.cos(Math.PI / 6)) +' '+ (-shift * Math.sin(Math.PI / 6)) +') '+ cm.dataset.rotate);
		}

	}

	// L'échelle où chaque cote garde une demi-hauteur de texte d'écart avec ses voisines, la hauteur
	// `height` se réduisant avec elle.
	static getScale(labels, height) {
		return Math.max(0, Math.min(1, ...labels.map(label => label.space / (label.length + height / 2))));
	}

}
