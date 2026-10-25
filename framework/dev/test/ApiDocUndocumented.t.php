<?php
namespace dev;

class ApiDocUndocumentedTest extends \Test {

	/**
	 * Un schéma se désigne par sa classe et non par son nom : ApiDocLib::buildUniqueNames() préfixe
	 * un nom court dès que deux modules de la liste le partagent, donc le même schéma change de nom
	 * selon la liste dans laquelle il est rendu.
	 */
	private function getSchemas(array $modules): array {

		$libModule = new ModuleLib();
		$libModule->load();

		$schemas = array_keys(ApiDocLib::buildOpenApiSpec($modules, $libModule)['components']['schemas']);

		$names = new \ReflectionMethod(ApiDocLib::class, 'buildUniqueNames')->invoke(NULL, $modules);
		uasort($names, fn(string $a, string $b) => strlen($b) <=> strlen($a));

		return array_map(function(string $schema) use($names) {

			foreach($names as $class => $name) {

				if(str_starts_with($schema, $name)) {
					return $class.' '.substr($schema, strlen($name));
				}

			}

			return $schema;

		}, $schemas);

	}

	/**
	 * Les deux classes éprouvées sont déclarées en bas de ce fichier, et aliasées sur le nom
	 * versionné que le filtre compose : l'alias suit ApiSetting::VERSIONS, là où un nom écrit en dur
	 * cesserait de désigner quoi que ce soit au prochain palier d'API, sans que le test échoue.
	 */
	public function testFilterReadsTheConstant(): void {

		$version = str_replace('.', '_', \api\ApiSetting::VERSIONS[count(\api\ApiSetting::VERSIONS) - 1]);

		class_alias(ZzApiDocDocumentedApi::class, 'dev\ZzApiDocDocumented_'.$version.'Api');
		class_alias(ZzApiDocUndocumentedApi::class, 'dev\ZzApiDocUndocumented_'.$version.'Api');

		$documented = ApiDocLib::filterDocumented(['dev\ZzApiDocDocumented', 'dev\ZzApiDocUndocumented']);

		$this->assertEquals(['dev\ZzApiDocDocumented'], $documented, 'le filtre garde DOCUMENTED = TRUE et écarte DOCUMENTED = FALSE, il a rendu : '.implode(', ', $documented));

	}

	/**
	 * Retirer des modules de la liste ne doit retirer de la spécification que leurs schémas, et tous
	 * les leurs : c'est ce qui distingue un module écarté par le filtre d'un module perdu en route.
	 * Le cas se joue sur les modules de learning, que le filtre ne retire plus : leurs ressources se
	 * citent entre elles (une leçon son module, un module sa formation), et un schéma cité par un
	 * module gardé ne doit pas partir avec eux. Qu'un module porte la constante, c'est dev\ApiUndocumentedSentinelTest qui le
	 * tient, et que le filtre la lise, le cas précédent.
	 */
	public function testRemovedModulesTakeOnlyTheirSchemas(): void {

		$exposed = ApiDocLib::getExposedModules();

		$removed = array_values(array_filter($exposed, fn(string $class) => str_starts_with($class, 'learning\\')));
		$kept = array_values(array_diff($exposed, $removed));

		if($this->assertDecorPresent($removed !== [], 'des modules de learning exposés, que le cas retire de la liste') === FALSE) {
			return;
		}

		$schemasRemoved = array_values(array_diff($this->getSchemas($exposed), $this->getSchemas($kept)));
		$schemasExpected = array_values(array_diff($this->getSchemas($removed), $this->getSchemas([])));

		sort($schemasRemoved);
		sort($schemasExpected);

		$this->assertEquals($schemasExpected, $schemasRemoved, 'schémas retirés : '.implode(', ', $schemasRemoved).' · attendus, ceux des modules retirés : '.implode(', ', $schemasExpected));

	}

}

class ZzApiDocDocumentedApi {
	const DOCUMENTED = TRUE;
}

class ZzApiDocUndocumentedApi {
	const DOCUMENTED = FALSE;
}
?>
