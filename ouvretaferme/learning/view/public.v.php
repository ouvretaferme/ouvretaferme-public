<?php
/**
 * Les écrans de la page publique des formations d'un réseau, dans le gabarit public des relevés :
 * ni menu de structure, ni conteneur d'écran produit, le bandeau du réseau à la place de l'en-tête.
 * Intégrée au site du réseau (`?embed`), la page perd aussi la navigation, le bandeau et le pied.
 * Le thème du réseau vaut partout, intégration comprise ; la barre qui le règle, posée en bas
 * d'écran par le socle, n'est rendue qu'au gérant qui l'a demandée (`$customize`).
 */
function learningPublicTemplate(BaseTemplate $t, \structure\Structure $eStructureCommunity, \structure\Community $eCommunity, bool $embed, array $settings, bool $customize, array $customizeQuery): \learning\DatePublicUi {

	$t->mainContainer = FALSE;
	$t->header = '';
	$t->template .= ' presentation learning-public';

	if($embed) {
		$t->template .= ' learning-embed';
	}

	$t->title = s("Formations de {value}", $eStructureCommunity['name']);

	echo \learning\CustomizeUi::getStyles($settings, 'public');

	if($customize) {
		echo new \learning\CustomizeUi()->getNetwork($eStructureCommunity, $eCommunity, 'learning', $settings);
	}

	return new \learning\DatePublicUi($eStructureCommunity, $eCommunity, $embed, $customizeQuery);

}

new AdaptativeView('/reseau/{fqn}/formations', function($data, BaseTemplate $t) {

	$ui = learningPublicTemplate($t, $data->eStructureCommunity, $data->eCommunity, $data->embed, $data->settings, $data->customize, $data->customizeQuery);

	echo $ui->getPage($ui->getList($data->cDate, $data->cCourse).$ui->getFaqAccess($data->faqs), $data->settings['customDesign']);

});

/**
 * Sans la barre de personnalisation, qui n'a pas d'origine sur cette page : le gérant règle le thème
 * depuis la racine ou les formations, et la FAQ le reprend.
 */
new AdaptativeView('/reseau/{fqn}/faq', function($data, BaseTemplate $t) {

	$ui = learningPublicTemplate($t, $data->eStructureCommunity, $data->eCommunity, $data->embed, $data->settings, FALSE, []);

	$t->title = s("Questions fréquentes de {value}", $data->eStructureCommunity['name']);

	$faqUi = new \learning\FaqUi();

	$h = $faqUi->getNetworkHero($data->faqs, $ui->url(\learning\DatePublicUi::urlList($data->eCommunity)));
	$h .= $faqUi->getNetworkBody($data->cccFaq, $data->faqs, $data->withCourse);

	echo $ui->getPage($h, $data->settings['customDesign'], band: TRUE);

});

new AdaptativeView('/reseau/{fqn}/formations/{date}', function($data, BaseTemplate $t) {

	$ui = learningPublicTemplate($t, $data->eStructureCommunity, $data->eCommunity, $data->embed, $data->settings, $data->customize, $data->customizeQuery);

	$t->title = $data->e['date']['course']['name'];

	echo $ui->getPage($ui->getSteps($data->e, $data->step, $data->status, $data->commitments, $data->cChapter, $data->cStaff, $data->online), $data->settings['customDesign']);

});

new AdaptativeView('/reseau/{fqn}/inscription/{token}', function($data, BaseTemplate $t) {

	$ui = learningPublicTemplate($t, $data->eStructureCommunity, $data->eCommunity, $data->embed, $data->settings, $data->customize, $data->customizeQuery);

	$t->title = $data->eDateEnrollment['date']['course']['name'];

	echo $ui->getPage($ui->getEnrollment($data->eDateEnrollment), $data->settings['customDesign']);

});

new AdaptativeView('/reseau/{fqn}/inscription/{token}/avis', function($data, BaseTemplate $t) {

	$ui = learningPublicTemplate($t, $data->eStructureCommunity, $data->eCommunity, $data->embed, $data->settings, $data->customize, $data->customizeQuery);

	$t->title = $data->eDateEnrollment['date']['course']['name'];

	echo $ui->getPage($ui->getReview($data->eDateEnrollment, $data->eReview), $data->settings['customDesign']);

});

/**
 * L'avis envoyé ou retiré remplace le formulaire, sans changer d'adresse : le lien de l'e-mail reste
 * celui qui rouvre le formulaire.
 */
new JsonView([
	'/reseau/{fqn}/inscription/{token}/avis/:doReview',
	'/reseau/{fqn}/inscription/{token}/avis/:doDelete'
], function($data, AjaxTemplate $t) {

	$ui = new \learning\DatePublicUi($data->eStructureCommunity, $data->eCommunity, $data->embed);

	$t->qs('#learning-public-review')->outerHtml($ui->getReviewSent($data->eDateEnrollment, $data->eReview, $data->deleted));

});

/**
 * L'écran « envoyé » remplace les étapes, sans changer d'adresse : il n'a pas de page à lui, faute de
 * quoi le jeton ou le prénom saisi voyageraient dans l'adresse.
 */
new JsonView('/reseau/{fqn}/formations/{date}/:doCreate', function($data, AjaxTemplate $t) {

	$ui = new \learning\DatePublicUi($data->eStructureCommunity, $data->eCommunity, $data->embed);

	$t->qs('#learning-public-steps')->outerHtml($ui->getSent($data->e));

});

/**
 * La ligne du compte annulée depuis sa page : la même page, repeinte dans son nouvel état. Une
 * redirection vers la date rendrait les étapes, la ligne n'étant plus ouverte, sans dire que
 * l'annulation a eu lieu.
 */
new JsonView('/reseau/{fqn}/formations/{date}/:doCancel', function($data, AjaxTemplate $t) {

	$ui = new \learning\DatePublicUi($data->eStructureCommunity, $data->eCommunity, $data->embed);

	$t->qs('#learning-public-enrollment')->outerHtml($ui->getEnrollment($data->e));

});
?>
