<?php
namespace dev;

/**
 * Ce que la référence OpenAPI publie au niveau de la ressource : son libellé, et le schéma
 * d'écriture qui l'accompagne.
 *
 * Deux défauts que la page Models rendait visibles au lecteur. Un module exposé sans entrée dans
 * l'`apiLabels()` de son package sortait titré « History », le
 * commentaire compris. Et chaque ressource publiait un schéma d'écriture, y compris celles qui se
 * lisent seulement : quatorze objets vides annonçaient une écriture qui n'existe pas.
 *
 * Lancement : dev/test package=dev file=ApiDocResourceAi
 */
class ApiDocResourceAiTest extends \Test {

	private array $spec;

	public function init(): void {

		$libModule = new ModuleLib();
		$libModule->load();

		$this->spec = ApiDocLib::buildOpenApiSpec(ApiDocLib::getExposedModules(), $libModule);

	}

	/** Toutes les références de la spécification, avec le chemin où chacune a été trouvée. */
	private function getReferences(): array {

		$references = [];

		array_walk_recursive($this->spec, function($value, $key) use (&$references) {

			if($key === '$ref' and is_string($value)) {
				$references[$value] = TRUE;
			}

		});

		return array_keys($references);

	}

	/** Un module exposé sans libellé sort avec le commentaire du gabarit en guise de titre. */
	public function testEveryResourceIsLabelled(): void {

		$manquants = [];

		foreach($this->spec['components']['schemas'] as $name => $schema) {

			foreach(['title', 'description'] as $key) {

				if(str_contains($schema[$key] ?? '', 'TODO')) {
					$manquants[] = $name.'.'.$key;
				}

			}

		}

		$this->assertEquals([], $manquants, 'libellés à écrire : '.implode(', ', $manquants));

	}

	/** Une ressource en lecture seule n'a pas de corps de requête à publier. */
	public function testNoOrphanWriteSchema(): void {

		$used = [];

		foreach($this->getReferences() as $reference) {
			$used[substr($reference, strrpos($reference, '/') + 1)] = TRUE;
		}

		$orphelins = [];

		foreach(array_keys($this->spec['components']['schemas']) as $name) {

			if(str_ends_with($name, 'Write') and isset($used[$name]) === FALSE) {
				$orphelins[] = $name;
			}

		}

		$this->assertEquals([], $orphelins, 'schémas d\'écriture que rien n\'utilise : '.implode(', ', $orphelins));

	}

	/**
	 * Un corps de requête qui n'accepte aucun champ se lit « rien n'est modifiable » : c'est faux dès
	 * lors qu'une route POST ou PUT le désigne. Cas vus en vrai : structure\Member et structure\System
	 * ne se créent pas par l'API, seulement s'y modifient, et le générateur ne lisait alors que
	 * getPropertiesCreate(). Un objet délibérément ouvert (additionalProperties) dit au contraire ce
	 * qu'on sait et reste acceptable.
	 */
	public function testWriteSchemasAcceptSomething(): void {

		$muets = [];

		foreach($this->spec['paths'] as $route => $methods) {

			foreach($methods as $method => $operation) {

				if(in_array($method, ['post', 'put'], TRUE) === FALSE) {
					continue;
				}

				$reference = $operation['requestBody']['content']['application/json']['schema']['$ref'] ?? NULL;

				if($reference === NULL) {
					continue;
				}

				$name = substr($reference, strrpos($reference, '/') + 1);
				$schema = $this->spec['components']['schemas'][$name] ?? [];

				if(($schema['properties'] ?? []) === [] and isset($schema['additionalProperties']) === FALSE) {
					$muets[] = $method.' '.$route.' -> '.$name;
				}

			}

		}

		$this->assertEquals([], $muets, 'corps de requête sans champ acceptable : '.implode(', ', $muets));

	}

	/**
	 * Contrôle du contrôle : un générateur qui cesserait de publier les schémas d'écriture passerait
	 * le test précédent sans que la référence documente encore le moindre corps de requête.
	 */
	public function testWriteSchemasAreStillPublished(): void {

		$write = array_filter(
			array_keys($this->spec['components']['schemas']),
			fn($name) => str_ends_with($name, 'Write'),
		);

		$this->assertTrue(count($write) > 30, 'les ressources modifiables gardent leur schéma d\'écriture');
		$this->assertTrue(in_array('InvoiceWrite', $write, TRUE), 'une ressource à routes d\'écriture publie le sien');

	}

	/**
	 * Le corps qu'une route d'action annonce doit être celui qu'elle lit.
	 *
	 * Le générateur ne sait pas le déduire, ce qu'une action lit vivant dans sa closure : faute de
	 * mieux il publiait le schéma d'écriture de la ressource pour toutes. PATCH invoice/{id}/confirm
	 * ne lit rien du tout et annonçait les sept champs modifiables d'une facture ; PUT
	 * product/{id}/vignette n'attend qu'un contenu encodé et annonçait les trente et un champs d'un
	 * produit. Chaque route le déclare désormais dans l'apiRoutes() de son package.
	 */
	public function testActionsDeclareTheirBody(): void {

		$menteuses = [];

		foreach($this->spec['paths'] as $route => $methods) {

			// Une action porte un segment de plus que sa ressource
			if(preg_match('#\{id\}/[a-zA-Z]#', $route) === 0) {
				continue;
			}

			foreach($methods as $method => $operation) {

				$reference = $operation['requestBody']['content']['application/json']['schema']['$ref'] ?? NULL;

				if($reference !== NULL) {
					$menteuses[] = strtoupper($method).' '.$route.' -> '.substr($reference, strrpos($reference, '/') + 1);
				}

			}

		}

		$this->assertEquals([], $menteuses, 'actions annonçant le corps de leur ressource : '.implode(', ', $menteuses));

	}

	/**
	 * Contrôle du contrôle : le test précédent passerait si le générateur cessait de publier le
	 * moindre corps de requête, ou si plus aucune route d'action n'était déclarée.
	 */
	public function testActionBodiesAreStillPublished(): void {

		$sansCorps = 0;
		$avecCorps = 0;

		foreach($this->spec['paths'] as $route => $methods) {

			if(preg_match('#\{id\}/[a-zA-Z]#', $route) === 0) {
				continue;
			}

			foreach($methods as $operation) {

				if(isset($operation['requestBody'])) {
					$avecCorps++;
				} else {
					$sansCorps++;
				}

			}

		}

		$this->assertTrue($avecCorps > 5, 'des actions publient un corps sur mesure');
		$this->assertTrue($sansCorps > 10, 'des actions déclarent ne rien attendre');

	}

	/**
	 * Une référence désigne une entrée de components, schéma ou réponse : retirer un schéma sans
	 * regarder qui le vise laisserait un lien mort dans la référence publiée.
	 */
	public function testEveryReferenceResolves(): void {

		$cassees = [];

		foreach($this->getReferences() as $reference) {

			$parts = explode('/', $reference);
			$name = array_pop($parts);
			$section = array_pop($parts);

			if(isset($this->spec['components'][$section][$name]) === FALSE) {
				$cassees[] = $reference;
			}

		}

		$this->assertEquals([], $cassees, 'références cassées : '.implode(', ', $cassees));

	}

	/**
	 * Un groupe qui porte le même titre que l'une de ses ressources déclare deux fois le tag, le
	 * second sous lui-même : GitBook n'affiche alors plus aucune catégorie de la référence. C'est
	 * ce qu'a fait le groupe catalog, titré « Catalogues » comme catalog\Catalog.
	 */
	public function testEveryTagIsDeclaredOnceUnderAnotherTag(): void {

		$names = array_column($this->spec['tags'], 'name');

		$doubles = array_keys(array_filter(array_count_values($names), fn($count) => $count > 1));

		$this->assertEquals([], $doubles, 'tags déclarés plusieurs fois, un titre de groupe à distinguer de celui de sa ressource : '.implode(', ', $doubles));

		$parents = array_column($this->spec['tags'], 'x-parent', 'name');

		$orphelins = array_keys(array_filter($parents, fn($parent, $name) => $parent === $name or in_array($parent, $names) === FALSE, ARRAY_FILTER_USE_BOTH));

		$this->assertEquals([], $orphelins, 'tags sans parent déclaré, ou parents d\'eux-mêmes : '.implode(', ', $orphelins));

	}

	/**
	 * Une intégration qui lit ses éléments un par un épuise ses appels du jour : la page de lecture
	 * d'un élément renvoie à la liste qui les rend par identifiants. Contrôle du contrôle, la page
	 * d'un produit, dont la liste porte ids, doit en être.
	 */
	public function testEveryReadPointsToItsListByIds(): void {

		$muettes = [];

		foreach($this->spec['paths'] as $route => $methods) {

			if(preg_match('#^/([a-zA-Z]+)/\{id\}$#', $route, $match) === 0 or isset($methods['get']) === FALSE) {
				continue;
			}

			$list = $this->spec['paths']['/'.$match[1].'List']['get'] ?? NULL;

			if($list === NULL or in_array('ids', array_column($list['parameters'] ?? [], 'name'), TRUE) === FALSE) {
				continue;
			}

			if(str_contains($methods['get']['description'], '/'.$match[1].'List?ids=') === FALSE) {
				$muettes[] = $route;
			}

		}

		$this->assertEquals([], $muettes, 'lectures sans renvoi à leur liste par ids : '.implode(', ', $muettes));
		$this->assertTrue(str_contains($this->spec['paths']['/product/{id}']['get']['description'], '/productList?ids='), 'la lecture d\'un produit renvoie à sa liste par ids');

	}

}
?>
