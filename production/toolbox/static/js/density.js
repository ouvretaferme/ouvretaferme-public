class ToolboxDensity {

	static timer = null;
	static running = false;
	static pending = false;

	/**
	 * Le résultat se relit un quart de seconde après la dernière frappe. `Ajax.Query` verrouille le
	 * formulaire pendant la requête et lève `LOCK_FAILED` sur la suivante : une saisie arrivée en vol
	 * est donc gardée, et repart dès le retour, avec les valeurs du moment.
	 */
	static refresh(form) {

		clearTimeout(this.timer);
		this.timer = setTimeout(() => this.send(form), 250);

	}

	static send(form) {

		if(this.running) {
			this.pending = true;
			return;
		}

		this.running = true;

		const done = () => {

			this.running = false;

			if(this.pending) {
				this.pending = false;
				this.send(form);
			}

		};

		new Ajax.Query(form)
			.url('/toolbox/density:calculate')
			.body(new FormData(form))
			.fetch()
			.then(done, done);

	}

	/**
	 * L'utilisation du sol, en plus des panneaux de la planche et de la surface
	 * (`ToolboxCalculator.changeUse`) : côté plantes, les champs sont communs, seuls les rangs, pour une
	 * planche, et l'écart entre les rangs, pour une surface libre, s'échangent, comme dans le panneau
	 * de série.
	 */
	static changeUse(target) {

		const bed = (ToolboxCalculator.changeUse(target) === 'bed');
		const form = target.firstParent('form');

		form.qsa('.crop-write-rows', node => node.classList.toggle('hide', bed === false));
		form.qsa('.crop-write-row-spacing', node => node.classList.toggle('hide', bed));

	}

	/**
	 * Le PNG est le dessin affiché, le Plan et, pour une planche, la Coupe dessous, dans le même
	 * ordre (retour du 28/09, sous mandat). Chaque dessin se copie avec ses styles
	 * calculés écrits sur chaque nœud : une image SVG ne voit pas les feuilles de la page. Les
	 * textes se mettent en page à la largeur du PNG et non à celle de l'écran, le temps de la
	 * copie (`SequenceDensityView.layout`).
	 */
	static download(button) {

		const svgs = Array.from(button.firstParent('#density-result').qsa('#density-view svg'));

		const width = 800;
		const left = 24;
		const top = 76;
		const gap = 24;
		const plotWidth = width - 2 * left;
		const properties = ['fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'stroke-linejoin', 'stroke-linecap', 'font-size', 'font-weight', 'font-family', 'paint-order', 'display'];

		const views = svgs.map(svg => {

			const box = svg.viewBox.baseVal;

			SequenceDensityView.layout(svg, box.width / plotWidth);

			const sources = [svg, ...svg.querySelectorAll('*')];
			const styles = sources.map(node => {
				const style = getComputedStyle(node);
				return properties.map(property => property +': '+ style.getPropertyValue(property)).join('; ');
			});

			const copy = svg.cloneNode(true);
			[copy, ...copy.querySelectorAll('*')].forEach((node, position) => node.setAttribute('style', styles[position]));

			SequenceDensityView.layout(svg, box.width / svg.clientWidth);

			// La hauteur est celle de la copie : la mise en page de l'écran a pu rallonger ou raccourcir
			// le cadre du dessin depuis (les passe-pieds de la coupe).
			const height = plotWidth * copy.viewBox.baseVal.height / copy.viewBox.baseVal.width;

			copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
			copy.setAttribute('width', plotWidth);
			copy.setAttribute('height', height);

			return {height: height, source: 'data:image/svg+xml;charset=utf-8,'+ encodeURIComponent(new XMLSerializer().serializeToString(copy))};

		});

		const images = views.map(view => new Promise(resolve => {
			const image = new Image();
			image.onload = () => resolve(image);
			image.src = view.source;
		}));

		Promise.all(images).then(loaded => {

			const scale = 2;
			const height = top + views.reduce((sum, view) => sum + view.height, 0) + gap * (views.length - 1) + 24;

			const canvas = document.createElement('canvas');
			canvas.width = width * scale;
			canvas.height = height * scale;

			const c = canvas.getContext('2d');
			c.scale(scale, scale);

			c.fillStyle = '#fff';
			c.fillRect(0, 0, width, height);

			this.drawTitle(c, button, left, width);

			let y = top;

			loaded.forEach((image, position) => {
				c.drawImage(image, left, y, plotWidth, views[position].height);
				y += views[position].height + gap;
			});

			this.save(canvas);

		});

	}

	static drawTitle(c, button, left, width) {

		const font = getComputedStyle(document.body).fontFamily;

		c.fillStyle = '#222';
		c.font = 'bold 17px '+ font;
		c.fillText(button.dataset.title, left, 28, width - left - 24);

		c.fillStyle = getComputedStyle(document.body).getPropertyValue('--muted').trim();
		c.font = '14px '+ font;
		c.fillText(button.dataset.subtitle, left, 52, width - left - 24);

	}

	static save(canvas) {

		canvas.toBlob(blob => {

			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = 'densite.png';

			document.body.appendChild(a);
			a.click();

			URL.revokeObjectURL(url);
			document.body.removeChild(a);

		});

	}

}

// Un champ du calculateur porte toujours une valeur, qu'on remplace plus souvent qu'on ne la corrige :
// la cliquer la sélectionne entière, et la frappe suivante la remplace.
document.delegateEventListener('focus', '#density-form input[type="number"]', function() {
	this.select();
});
