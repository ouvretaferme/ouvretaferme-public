<?php
/**
 * La page d'un réseau sur son adresse publique, quand il propose des formations : le bandeau, les
 * dates à venir, puis l'accès aux relevés de prix s'il a une collecte ouverte au public et à ses
 * questions fréquentes s'il en a publié. Le gabarit public des relevés, dans la portée du thème du
 * réseau, comme ses autres pages publiques (`learning\CustomizeUi`), et la barre qui le règle pour le
 * gérant qui l'a demandée.
 */
new AdaptativeView('/reseau/{fqn}', function($data, BaseTemplate $t) {

	$t->mainContainer = FALSE;
	$t->header = '';
	$t->template .= ' presentation learning-public';

	$t->title = $data->eStructure['name'];

	echo \learning\CustomizeUi::getStyles($data->settings, 'public');

	if($data->customize) {
		echo new \learning\CustomizeUi()->getNetwork($data->eStructure, $data->eCommunity, 'home', $data->settings);
	}

	$ui = new \learning\DatePublicUi($data->eStructure, $data->eCommunity, embed: FALSE, customizeQuery: $data->customizeQuery);

	$h = $ui->getBanner(\learning\DatePublicUi::getBannerSubtitle($data->withQuotation));

	$h .= '<div class="container mt-2">';

		$h .= $ui->getList($data->cDate);

		if($data->withQuotation) {
			$h .= implode('', \structure\StructureUi::getObserverEntries('getCommunityPublic', $data->eCommunity, $data->eStructure, $data->customizeQuery));
		}

		$h .= $ui->getFaqAccess($data->faqs);

	$h .= '</div>';

	echo \learning\CustomizeUi::getPublic($data->settings['customDesign'], $h);

});
?>
