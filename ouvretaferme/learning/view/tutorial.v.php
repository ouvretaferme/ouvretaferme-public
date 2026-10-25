<?php
new AdaptativeView('/tutoriels', function($data, BaseTemplate $t) {

	$ui = new \learning\TutorialUi();

	$t->template .= ' presentation';
	$t->title = s("Prendre en main Ouvretaferme, pas à pas");
	$t->header = '';
	$t->mainContainer = FALSE;

	echo $ui->getHero($data->cCourse, $data->chapters, $data->lessons, $data->minutes);

	if($data->cCourse->empty()) {
		echo $ui->getEmpty();
		return;
	}

	echo $ui->getSummary($data->cCourse, $data->faqsByChapter);
	echo $ui->getStart($data->eChapterStart);

});
?>
