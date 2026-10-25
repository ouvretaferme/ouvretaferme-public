<?php
new AdaptativeView('sequence', function($data, StructureTemplate $t) {

	$t->canonical = \structure\StructureUi::urlCultivationSequences($data->eStructure);
	$t->title = s("Itinéraires techniques de {value}", $data->eStructure['name']);

	$t->nav = 'cultivation';
	$t->subNav = 'sequence';

	$tools = '';
	$create = '';

	// ⚠️ La loupe se cache quand il n'y a **rien à chercher**, et non quand on n'a pas le
	// droit d'écrire : chercher n'est pas écrire, et un lecteur a le droit de chercher dans ce
	// qu'il peut lire. La condition est donc celle du contenu, la même que le bloc principal
	// plus bas : sur une ferme sans aucun itinéraire, elle ouvrait une recherche sur rien.
	if($data->ccCrop->notEmpty() or $data->emptySearch === FALSE) {
		$tools = '<a '.attr('onclick', 'Lime.Search.toggle("#sequence-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>';
	}

	if($data->eStructure->canManage()) {
		$create = '<a href="/sequence/sequence:create?structure='.$data->eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvel itinéraire").'</span></a>';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Itinéraires techniques"),
		tools: $tools,
		create: $create
	);

	if($data->ccCrop->notEmpty() or $data->emptySearch === FALSE) {

		echo new \sequence\SequenceUi()->getTabs($data->eStructure, $data->search, $data->sequences);
		echo new \sequence\SequenceUi()->getSearch($data->eStructure, $data->search, $data->emptySearch);
		echo new \sequence\SequenceUi()->getListByPlants($data->eStructure, $data->ccCrop, $data->cActionMain, $data->search);

	} else {

		// ⚠️ Le titre et le texte n'étaient pas gardés, seul le bouton l'était : un lecteur sans
		// droit lisait « Créez les itinéraires techniques de votre ferme » sans aucun moyen de les
		// créer, sur une ferme qui n'est pas la sienne. Garder le bouton et laisser la phrase est
		// le pire des deux états — l'écran demande un geste et le retire.
		if($data->eStructure->canManage()) {

			$start = '<p>'.s("Un itinéraire technique contient la liste des interventions à réaliser pour une culture, de la préparation à la récolte.").'</p>';
			$start .= '<p><b>'.s("Si vous débutez avec {siteName}, il est recommandé de ne pas utiliser cette fonctionnalité tout de suite.").'</b></p>';

			echo new \main\DesignUi()->getStart(
				\Asset::icon('journals'),
				s("Créez les itinéraires techniques de votre ferme"),
				$start
			);

			echo '<a href="/sequence/sequence:create?structure='.$data->eStructure['id'].'" class="btn btn-primary">'.s("Créer un itinéraire technique").'</a>';

		} else {

			echo \main\DesignUi::getEmptyRead(
				$data->eStructure,
				s("Aucun itinéraire technique n'a encore été créé, et seul un responsable de la structure peut en créer."),
				s("Cette ferme n'a pas encore créé d'itinéraire technique.")
			);

		}

	}

});
?>
