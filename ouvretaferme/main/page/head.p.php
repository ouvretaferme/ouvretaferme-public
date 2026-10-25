<?php
/**
 * Le commit en production, pour que chaque poste sache qu'un déploiement a eu lieu. La route est
 * signée : le commit déployé dit quels correctifs poussés ne le sont pas encore.
 */
new Page()
	->remote('index', 'head', function($data) {

		$head = \main\HeadLib::get();

		throw new DataAction('head '.$head['head']."\n".'upstream '.$head['upstream']."\n", 'text/plain');

	});
?>
