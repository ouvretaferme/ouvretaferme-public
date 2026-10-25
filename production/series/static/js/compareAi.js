/**
 * La frise des espèces d'une ferme face au réseau (`CompareAiUi::getSpeciesTimeline()`).
 *
 * Les autres fermes sont servies repliées sous chaque espèce : le lien les déplie pour cette espèce
 * seule et disparaît avec, sans aller-retour au serveur. Même geste que les variétés repliées de
 * `plant/static/js/varietyRating.js`.
 */
class SeriesCompareAi {

	static more(link) {

		link.firstParent('.compare-timeline-species').classList.add('is-expanded');

	}

}

document.delegateEventListener('click', '[data-action="series-compare-more"]', function(e) {

	e.preventDefault();

	SeriesCompareAi.more(this);

});
