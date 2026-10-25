class StructureCommunityMap {

	// La carte passe par MapboxContainer, qui la retrouve par l'id de son nœud : une navigation ajax
	// qui revient sur la liste des fermes recycle la carte au lieu d'empiler des instances mapbox-gl.
	static start(node) {

		// Dans un panneau d'onglet masqué (« Fermes projet » rouvert par `?tab=` ou par la mémoire
		// de Lime.Tab), le nœud n'a pas de largeur : `fitBounds` cadrerait sur une boîte nulle, et un
		// `resize()` après coup garderait ce cadrage faux. La carte attend sa première largeur.
		if(node.offsetWidth === 0) {

			const observer = new ResizeObserver(() => {

				if(node.offsetWidth > 0) {
					observer.disconnect();
					this.start(node);
				}

			});

			observer.observe(node);
			return;

		}

		// Le lieu d'une ferme n'est pas borné à la saisie : un point hors du globe ferait lever
		// LngLatBounds.extend(), et avec lui tous les onrender suivants de la page.
		const points = JSON.parse(node.dataset.points).filter(point => (
			Math.abs(point.lngLat[0]) <= 180 &&
			Math.abs(point.lngLat[1]) <= 90
		));

		if(points.length === 0) {
			return;
		}

		const bounds = new mapboxgl.LngLatBounds();
		points.forEach(point => bounds.extend(point.lngLat));

		const mapbox = new MapboxContainer(node.id).create({
			bounds: bounds,
			// Un groupe dont toutes les fermes sont dans le même village cadrerait sur quelques rues :
			// le plafond garde la commune et ses voisines à l'écran.
			fitBoundsOptions: {padding: 40, maxZoom: 11},
			cooperativeGestures: true,
			// Les textes de mapbox-gl (messages de cooperativeGestures, infobulles du zoom) sont en anglais
			// par défaut : la bibliothèque les lit dans `locale`, dont les clés sont traduites côté PHP.
			locale: JSON.parse(node.dataset.locale),
			dragRotate: false,
			pitchWithRotate: false
		});

		// Une carte de situation se lit nord en haut : la rotation coupée, la boussole n'aurait rien à
		// remettre d'aplomb.
		mapbox.touchZoomRotate.disableRotation();
		mapbox.addControl(new mapboxgl.NavigationControl({showCompass: false}));

		points.forEach(point => {

			// Le décalage place la bulle au-dessus de la tête du repère, dont la pointe est le point.
			const popup = new mapboxgl.Popup({offset: 28, maxWidth: '280px'}).setHTML(point.popup);

			new mapboxgl.Marker()
				.setLngLat(point.lngLat)
				.setPopup(popup)
				.addTo(mapbox);

		});

	}

}
