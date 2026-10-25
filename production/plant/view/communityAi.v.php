<?php
new AdaptativeView('/ferme/{id}/bibliotheque', function($data, StructureTemplate $t) {

	$t->nav = 'intelligence-vegetable';
	$t->subNav = 'intelligence-crop-plan';

	$t->title = s("Bibliothèques de cultures");

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);

	$start = '<p>'.s("Combien de temps entre la plantation et la première récolte ? Jusqu'à quand récolte-t-on une série implantée en juillet ? Quelles variétés les autres producteurs retiennent-ils pour chaque créneau ?").'</p>';
	$start .= '<p>'.s("Nous avons rassemblé ici <b>les plans de culture de centaines de fermes</b> qui utilisent {siteName} pour y répondre d'un coup d'œil.", ['b' => '<b class="util-mark">']).'</p>';

	$start .= '<div class="util-block-info mt-2">';
		$start .= '<div class="util-block-icon">';
			$start .= \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => 'carotte']), '2.5rem');
		$start .= '</div>';
		$start .= '<p>';
			$start .= ($data->cPlant->notEmpty() ?
				s("Cette page est en préparation et elle est basée sur les fermes comme la votre qui ont accepté de partager leurs données. Elle s'enrichira des notes attribuées aux variétés par les producteurs et productrices.") :
				s("Cette page est proposée en avant-première sur la carotte.<br/>Elle couvrira ensuite toutes les espèces et s'enrichira des notes attribuées aux variétés par les producteurs et productrices.")
			);
		$start .= '</p>';
		$start .= '<a href="'.\structure\StructureUi::urlIntelligenceVariety($data->eStructure).'" class="">'.s("Évaluer mes variétés").'</a>';
	$start .= '</div>';


	echo new \main\DesignUi()->getStart(
		\Asset::icon('calendar3'),
		s("Le plan de culture de la communauté").' '.\Asset::image('main', 'favicon.svg'),
		$start,
		upcoming: '<b>'.s("À venir").'</b><small>'.s("5 novembre").'</small>'
	);

	if($data->eStructure['membership'] !== TRUE) {

		echo new \plant\CommunityAiUi()->getMembershipRequired($data->eStructure);

		return;

	}

	if($data->eStructure['consentData'] !== TRUE) {

		echo new \structure\StructureUi()->getConsentDataForm($data->eStructure);

		return;

	}

	echo new \plant\CommunityAiUi()->getCropPlan(
		$data->eStructure,
		$data->ePlant,
		$data->cropPlan,
		$data->filters,
		$data->cPlant
	);

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \plant\CommunityAiUi::getSuggestions(
		$data->eStructure,
		$data->cPlant,
		$data->ePlant,
		$data->filters,
		$data->cCultivationVariety,
		$data->query
	));

});
