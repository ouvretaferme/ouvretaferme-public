<?php
/**
 * Compute migrations
 * php framework/lime.php -a ouvretaferme -e prod data/migrate
 * php framework/lime.php -a ouvretaferme -e dev data/migrate replay=<N> [structure=<id>]
 *
 */
new Page()
	->cli('index', function($data) {

		if(get_exists('replay')) {

			$eStructure = get_exists('structure') ? \structure\StructureLib::getById(GET('structure'))->validate() : new \structure\Structure();
			\data\MigrateLib::replay(GET('replay', 'int'), $eStructure);

		} else if(get_exists('structure')) {

			$eStructure = \structure\StructureLib::getById(GET('structure'))->validate();
			\data\MigrateLib::one($eStructure);

		} else {
			\data\MigrateLib::all();
		}


	});
?>
