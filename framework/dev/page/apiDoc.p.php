<?php

/**
 * Generate the OpenAPI 3.0 specification (openapi.json), api-doc repository, and a Postman
 * Collection v2.1 (postman-collection.json), ouvretaferme/main/static/document, for every module
 * exposed through ApiRoute::expose(), except those whose API class carries `const DOCUMENTED =
 * FALSE`. openapi.json is rendered by GitBook's native OpenAPI reference once registered (Space >
 * OpenAPI > Add specification) ; postman-collection.json est servi tel quel par la route
 * /developer/postman-collection.json (ouvretaferme/main/page/developer.p.php), déployé avec
 * l'app comme n'importe quel autre static, donc toujours à jour sans ré-upload manuel. Le repo
 * api-doc étant privé, un lien GitHub brut (Import > Link) n'aurait de toute façon pas été
 * accessible aux devs externes.
 *
 * Usage: php framework/lime.php -a ouvretaferme -e dev dev/apiDoc [output=/path/to/api-doc]
 *
 * V2 (voir MEMORY project_api_developer_doc) : remplace la génération markdown par page
 * (endpoints/*.md) par un unique openapi.json, introspecté via dev\ApiDocLib. Régénère aussi
 * intégralement la table 'Ressources disponibles' de endpoints/README.md et le menu Endpoints
 * de SUMMARY.md (juste la Vue d'ensemble désormais, le détail par route vit dans la référence
 * OpenAPI).
 */
new Page()
	->cli('index', function($data) {

		$outputDir = GET('output', 'string', dirname(__DIR__, 4).'/api-doc');

		// Les refus de cette page sortent en code 2, comme ceux de lime.php : un code 0 les ferait passer
		// pour réussis auprès de qui ne lit que le code de sortie.
		if(is_dir($outputDir) === FALSE) {
			echo "Error: Output directory '".$outputDir."' does not exist\n";
			exit(2);
		}

		$modules = \dev\ApiDocLib::getDocumentedModules();

		if($modules === []) {
			echo "Error: No documented module found\n";
			exit(2);
		}

		$libModule = new \dev\ModuleLib();
		$libModule->load();

		$spec = \dev\ApiDocLib::buildOpenApiSpec($modules, $libModule);

		$path = $outputDir.'/openapi.json';
		file_put_contents($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

		echo 'openapi.json written to '.$path.' ('.count($modules).' module(s), '.count($spec['paths']).' path(s)).'."\n";

		$collection = \dev\ApiDocLib::buildPostmanCollection($modules, $libModule);

		$postmanPath = \Package::getPath('main').'/static/document/postman-collection.json';
		file_put_contents($postmanPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

		echo 'postman-collection.json written to '.$postmanPath.'.'."\n";

		\dev\ApiDocLib::updateReadme($outputDir.'/endpoints/README.md', $spec);
		\dev\ApiDocLib::updateSummary($outputDir.'/SUMMARY.md');

		echo "Done.\n";

	})
	->cli('help', function($data) {

		echo "Usage: dev/apiDoc [output=path]\n";
		echo "\n";
		echo "DESCRIPTION\n";
		echo "	Generate the OpenAPI 3.0 specification (openapi.json) and a Postman Collection\n";
		echo "	(postman-collection.json) for every module exposed via ApiRoute::expose() whose API\n";
		echo "	class does not carry const DOCUMENTED = FALSE, from introspection of routes,\n";
		echo "	schema and writable properties. Also refreshes endpoints/README.md's resource table\n";
		echo "	and SUMMARY.md's Endpoints menu.\n";
		echo "\n";
		echo "OPTIONS\n";
		echo "	output=[path]\n";
		echo "		Target directory of the api-doc repository (default: ../api-doc next to src).\n";

	});
?>
