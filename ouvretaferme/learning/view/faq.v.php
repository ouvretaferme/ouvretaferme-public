<?php
new AdaptativeView('/faq', function($data, BaseTemplate $t) {

	$ui = new \learning\FaqUi();

	$t->template .= ' presentation';
	$t->title = s("Que se passe-t-il à l'écran ?");
	$t->metaDescription = s("Les réponses aux questions qui reviennent sur l'utilisation de Ouvretaferme, du plan de culture au planning, des semences aux exports.");
	$t->header = '';
	$t->mainContainer = FALSE;

	echo $ui->getHero($data->faqs, $data->screens);
	echo $ui->getBody($data->ccFaq, $data->faqs);

});
?>
