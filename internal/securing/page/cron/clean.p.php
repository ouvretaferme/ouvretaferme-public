<?php
/**
 * Purge l'historique de chaque structure.
 *
 * Le journal vit dans la base de la structure : là où PdpHistoryLib::clean() travaille sur la base
 * commune et se contente d'une requête, il faut ici boucler.
 *
 * Pour purger une seule structure à la main :
 * php framework/lime.php -a ouvretaferme -e prod securing/cron/clean:structure structure=7
 */
new Page()
	->cron('index', function($data) {

		if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
			return;
		}

		// Les bases qui portent le journal, lues sur le serveur, et non les structures déclarées : une
		// structure sans base arrêtait sinon la purge de toutes celles qui la suivent. Sans migration,
		// la purge ne lisant que doneAt et event.
		foreach(\structure\StructureLib::getDatabasesWithTable(\securing\Log::model()->getTable()) as $structureId => $base) {

			\structure\StructureLib::connectDatabase(new \structure\Structure(['id' => $structureId]), migrate: FALSE);
			\securing\LogLib::clean();

		}

	}, interval: '17 4 * * *')
	->cli('structure', function($data) {

		if(\structure\StructureSetting::hasAccountingApp() === FALSE) {
			return;
		}

		$eStructure = \structure\StructureLib::getById(GET('structure'))->validate();

		\structure\StructureLib::connectDatabase($eStructure);
		\securing\LogLib::clean();

	});
?>
