<?php

new AdaptativeView('/design/', function($data, BaseTemplate $t) {

	if($data->forDev) {

		$t->title = s("Travailler avec les agents");
		$t->header = \design\NavigationUi::getHeader($t->title, \design\NavigationUi::DEV);

		echo new \design\DevAiUi()->all();

	} else if($data->forTests) {

		$t->title = s("Le décor des tests");
		$t->header = \design\NavigationUi::getHeader($t->title, \design\NavigationUi::TESTS, $data->forAgents);

		echo new \design\DecorAiUi()->all($data->decorStructures, $data->decorUsers, $data->forAgents);

	} else {

		$t->title = s("Design system de {siteName}");
		$t->header = \design\NavigationUi::getHeader($t->title, '/', $data->forAgents);

		echo new \design\DesignUi()->all($data->forAgents);

	}

});
