if(isTouch()) {

	let zoneEvent = null;

	document.addEventListener('scroll', function() {

		if(window.oldScrollY > window.scrollY) {
			qs('#zone-header', node => node.style.top = 0);
		}

		if(zoneEvent !== null) {

			clearTimeout(zoneEvent);

		}

		zoneEvent = setTimeout(() => {

			const zone = qs('#zone-content');
			const header = qs('#zone-header');

			if(header === null) {
				return;
			}

			const zoneTop = zone.getBoundingClientRect().top;
			const sticky = parseFloat(window.getComputedStyle(document.body).getPropertyValue('--mainSticky')) * rem();

			if(zoneTop < sticky) {
				header.style.top = (sticky - zoneTop) +'px';
			} else {
				header.style.top = 0;
			}

			zoneEvent = null;

		}, 250);

	});

}

class MapZone {

	/* L'interrupteur du panneau de création : remise à plus tard, la parcelle a sa surface saisie, et dessinée, celle de son contour */
	static changeLater(input) {

		const later = (input.value === '0');

		MapDrawingEmbedded.changeLater(input, 'zone-create-drawing');

		qs('#zone-create').qsa('[data-zone-area]', node => {

			const typed = (node.dataset.zoneArea === 'typed');

			node.classList.toggle('hide', typed !== later);

			if(typed) {
				node.qsa('input', field => field.disabled = (later === false));
			}

		});

	}

	/* Une parcelle à dessiner maintenant et encore sans contour n'a rien à envoyer : le refus du serveur tomberait sur la surface, que
	   l'écran lit sur le dessin, alors c'est le dessin qu'on désigne */
	static checkDrawing(form) {

		const drawing = form.qs('#zone-create-drawing');

		if(drawing === null) {
			return true;
		}

		const errors = [];

		if(drawing.classList.contains('hide') === false && form.qs('input[name="coordinates"]').disabled) {
			errors.push([drawing.dataset.errorName, drawing.dataset.error, 'coordinates']);
		}

		Lime.Alert.showErrors(form, errors);

		return (errors.length === 0);

	}

	static anchor() {

		const plot = new URLSearchParams(window.location.search).get('plot');

		if(plot === null || plot === '') {
			return;
		}

		const cible = qs('#plot-item-'+ plot) ?? qs('#garden-plot-'+ plot);

		if(cible === null) {
			return;
		}

		cible.scrollIntoView({block: 'center', inline: 'center'});

	}

	// La hauteur de la rangee collante, publiee pour que le bandeau de parcelle
	// vienne se poser dessous au lieu de glisser derriere. Dans un panneau, la rangee
	// colle sous l'en-tete, dont la hauteur varie avec le titre.
	static measureSoilViews() {

		const row = qs('main .soil-views');

		document.documentElement.style.setProperty('--soil-views-height', (row === null) ? '0px' : row.offsetHeight +'px');

		qsa('.panel .soil-views', row => {

			const panel = row.firstParent('.panel');

			panel.style.setProperty('--soil-views-height', row.offsetHeight +'px');
			panel.style.setProperty('--soil-views-top', (panel.qs('.panel-header')?.offsetHeight ?? 0) +'px');

			// Le panneau defile seul : le document ne recoit pas son evenement scroll.
			if(panel.dataset.soilPath === undefined) {
				panel.dataset.soilPath = '';
				panel.addEventListener('scroll', () => MapZonePath.refresh(), {passive: true});
			}

		});

	}

	static select(zoneId, plotId = undefined) {

		const zoneTarget = qs('#zone-selector [data-zone="'+ zoneId +'"]');

		let location;

		// Le bouton vit dans la rangée des onglets, hors du plan : le chercher sous
		// #zone-container ne le trouvait plus et son libellé restait figé.
		const zoneDropdown = qs('.zone-dropdown');

		if(zoneId === undefined) {

			qsa('[data-wrapper="#zone-container"] .dropdown-item', item => item.classList.toggle('selected', item.dataset.tab === ''));

			location = document.location.href.removeArgument('zone').removeArgument('plot');

			if(zoneDropdown !== null) {
				zoneDropdown.textContent = zoneDropdown.dataset.placeholder;
			}

		} else {

			qsa('[data-wrapper="#zone-container"] .dropdown-item', tab => tab.dataset.zone === zoneTarget.dataset.zone ? tab.classList.add('selected') : tab.classList.remove('selected'));

			location = document.location.href.setArgument('zone', zoneId);
			location = (plotId === undefined) ? location.removeArgument('plot') : location.setArgument('plot', plotId);

			if(zoneDropdown !== null) {

				// Le libellé dit ce qu'on vient de choisir : le jardin s'il est visé,
				// la parcelle sinon.
				const target = (plotId === undefined) ?
					zoneTarget :
					(qs('#zone-selector [data-plot="'+ plotId +'"]') ?? zoneTarget);

				zoneDropdown.textContent = target.dataset.placeholder;

			}

		}

		const panels = [...qsa('#zone-container > .bed-item-wrapper > .tab-panel')];

		if(panels.length > 0) {

			const wanted = (zoneId === undefined) ?
				panels[0].dataset.tab :
				String(zoneId);

			panels.forEach(panel => panel.classList.toggle('selected', panel.dataset.tab === wanted));

		}

		// Au téléphone, le menu est une couche d'historique : le fermer recule d'une entrée puis repose
		// le défilement d'avant l'ouverture. L'adresse et le saut vers la parcelle attendent ce retour,
		// sans quoi l'adresse s'écrit sur l'entrée qu'on quitte et le saut est défait.
		const button = qs('.zone-button[data-dropdown-display="fullscreen"]');

		const closed = (button !== null) ?
			Lime.History.popLayer(button) :
			Lime.Dropdown.purge();

		Promise.resolve(closed).then(() => {

			Lime.History.replaceState(location);

			if(plotId !== undefined || zoneId !== undefined) {

				const target = (plotId === undefined) ?
					qs('#zone-container .zone-wrapper[data-zone="'+ zoneId +'"]') :
					(qs('#plot-item-'+ plotId) ?? qs('#garden-plot-'+ plotId));

				if(target !== null) {

					target.scrollIntoView({
						block: (plotId === undefined) ? 'start' : 'center',
						inline: 'center'
					});

				}

			} else {

				const panel = qs('#zone-container')?.firstParent('.panel');

				(panel ?? window).scrollTo(0, 0);

			}

		});

	}

}

// Au défilement, le bouton des parcelles dit celle qu'on est en train de lire et
// le menu la met en évidence. Il ne filtre rien : il reflète.
class MapZonePath {

	static refresh() {

		const label = qs('.zone-dropdown');

		if(label === null) {
			return;
		}

		const limit = label.getBoundingClientRect().bottom;

		let closest = null;
		let distance = null;

		qsa('.plot-wrapper[data-plot]', plot => {

			const box = plot.getBoundingClientRect();

			if(box.height === 0 || box.bottom < limit) {
				return;
			}

			const gap = Math.abs(box.top - limit);

			if(distance === null || gap < distance) {
				distance = gap;
				closest = plot;
			}

		});

		qsa('#zone-selector .dropdown-item', item => item.classList.remove('selected'));

		if(closest === null) {

			label.textContent = label.dataset.placeholder;
			qs('#zone-selector .dropdown-item[data-tab=""]', item => item.classList.add('selected'));

			return;

		}

		const item = qs('#zone-selector .dropdown-item[data-plot="'+ closest.dataset.plot +'"]')
			?? qs('#zone-selector .dropdown-item[data-zone="'+ closest.firstParent('.zone-wrapper')?.dataset.zone +'"]');

		if(item === null) {
			label.textContent = label.dataset.placeholder;
			return;
		}

		item.classList.add('selected');

		label.textContent = item.dataset.placeholder ?? label.dataset.placeholder;

	}

}

window.addEventListener('resize', () => MapZone.measureSoilViews());
document.addEventListener('navigation.wakeup', () => MapZone.measureSoilViews());
MapZone.measureSoilViews();

/* L'envoi du formulaire part d'un écouteur du cadriciel qui arrête la propagation (form.js) : le contrôle se pose sur le clic du bouton,
   que l'Entrée d'un champ déclenche aussi */
document.delegateEventListener('click', '#zone-create [type="submit"]', function(e) {

	if(MapZone.checkDrawing(this.form) === false) {
		e.preventDefault();
	}

});

document.addEventListener('DOMContentLoaded', () => MapZone.anchor());
document.addEventListener('navigation.wakeup', () => MapZone.anchor());

document.delegateEventListener('scroll', 'body', () => MapZonePath.refresh());
document.addEventListener('scroll', () => MapZonePath.refresh(), {passive: true});
document.addEventListener('navigation.wakeup', () => MapZonePath.refresh());
