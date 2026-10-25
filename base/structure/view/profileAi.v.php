<?php
new AdaptativeView('/ferme/{id}', function($data, BaseTemplate $t) {

	// Le titre nomme l'onglet, il ne se peint pas : le bandeau d'identité porte déjà le nom, avec
	// le logo et la description, et BaseTemplate en ferait un second h1 juste au-dessus.
	$t->title = $data->eStructure['name'];
	$t->header = '';

	// L'en-tête va d'un bord à l'autre : sans cela, BaseTemplate l'enferme dans son .container et
	// la bande s'arrête au contenu. Le corps reprend son container juste après.
	$t->mainContainer = FALSE;

	$ui = new \structure\ProfileAiUi();

	echo $ui->getIdentity($data->eStructure, $data->cUser);

	$scene = '';
	$blocks = '';

	if($data->profileShare !== \structure\Structure::PROFILE_HIDDEN) {

		$scene = $ui->getScene($data->eSystem, $data->cProduction);

		$blocks .= $scene;
		$blocks .= $ui->getProductions($data->cProduction);
		$blocks .= $ui->getTerritory($data->cStructureNeighbour);

	}

	echo '<div class="container">';

	// Rien n'a été chargé : ce que le lecteur ne voit pas ne dit rien de ce que la ferme a rempli,
	// et l'inviter à en conclure un profil vide serait faux.
	if($data->profileShare === \structure\Structure::PROFILE_HIDDEN) {

		echo $ui->getHiddenNotice();

	} else {

		// La scène absente est le seul signe du système vide, une fois qu'un bloc vide ne se rend
		// plus : sans elle, la page d'une ferme qui n'a rempli que ses ateliers ne dirait plus
		// nulle part ce qui manque ni où le remplir.
		if($scene === '') {
			echo $ui->getEmptyNotice($data->eStructure, $data->eStructure->isMember());
		}

		echo $blocks;

	}

	echo '</div>';

});
