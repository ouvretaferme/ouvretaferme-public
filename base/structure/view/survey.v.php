<?php

new AdaptativeView('surveyMain', function($data, BaseTemplate $t) {

	$t->title = s("Enquête des adhérents");

	if($data->cStructure->empty()) {
		echo '<p>'.s("Vous n'êtes exploitant d'aucune structure sur {siteName}.").'</p>';
	} else {

		echo \structure\SurveyUi::getIntroduction();

		echo '<table>';
			echo '<tbody>';
				foreach($data->cStructure as $eStructure) {
					echo '<tr>';
						echo '<td class="td-min-content">'.\structure\StructureUi::getLogo($eStructure, '3rem').'</td>';
						echo '<td>'.encode($eStructure['name']).'</td>';
						echo '<td>';
							if($eStructure->acceptSurvey()) {
								echo '<a href="/structure/survey?structure='.$eStructure['id'].'" class="btn btn-outline-primary">'.s("Répondre à l'enquête").'</a>';
							} else {
								echo Asset::icon('x-lg').' '.s("Adhérez à l'association pour répondre à cette enquête");
							}
						echo '</td>';
					echo '</tr>';
				}
			echo '</tbody>';
		echo '</table>';

	}

});

new AdaptativeView('surveyStructure', function($data, StructureTemplate $t) {

	$t->title = s("Enquête des adhérents");

	$t->nav = 'structure';
	$t->subNav = '';

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);

	if($data->eSurvey->notEmpty()) {

		echo '<div class="util-block-info">';
			echo \Asset::icon('check-circle-fill', ['class' => 'util-block-icon']);
			echo '<h3>'.s("Merci pour votre contribution !").'</h3>';
			echo '<p>'.s("Nous utiliserons vos réponses pour choisir les prochains développements. D'ici là, vous pouvez revenir compléter ou corriger ce que vous avez écrit.").'</p>';
		echo '</div>';

	} else {

		echo \structure\SurveyUi::getIntroduction();

	}

	echo new \structure\SurveyUi()->create($data->eSurvey, $data->eStructure);

});
