<?php
namespace dev;

/**
 * La documentation API se génère à l'identique sans base de données.
 *
 * Le générateur appelle du code métier réel : les closures de `getPropertiesCreate()`/
 * `getPropertiesUpdate()` reçoivent une sonde et disent ce qu'elles accepteraient. Ces closures
 * lisent des droits et des fonctionnalités (`$e['structure']->conf(\structure\Feature::class)['hasAccounting']`), et une
 * sonde n'existant pas en base, chacune de ces lectures partait en requête pour ne jamais rien
 * ramener : 2 120 tentatives mesurées le 2026-09-10.
 *
 * Le workflow api-doc-ai.yml tourne sans base. Chaque tentative y échoue, la closure entière est
 * alors passée, et cinq ressources publiaient un corps libre au lieu de leur schéma : la
 * documentation en ligne n'était pas celle qu'on génère en développement. Selon la façon dont la
 * connexion échoue, refus immédiat ou attente TCP, la commande passait ou restait bloquée sans fin.
 *
 * Le test coupe les serveurs et compare les deux sorties. Une lecture qui repart en base fait
 * diverger la seconde, et l'écart nomme la ressource qui l'a perdue.
 *
 * Lancement : dev/test package=dev file=ApiDocNoDatabaseAi
 */
class ApiDocNoDatabaseAiTest extends \Test {

	/** Ce que la commande écrit, des deux côtés : la référence OpenAPI et la collection Postman. */
	private function build(): array {

		$libModule = new ModuleLib();
		$libModule->load();

		$modules = ApiDocLib::getExposedModules();

		return [
			'openapi' => ApiDocLib::buildOpenApiSpec($modules, $libModule),
			'postman' => ApiDocLib::buildPostmanCollection($modules, $libModule),
		];

	}

	public function testGenerationIsTheSameWithoutDatabase(): void {

		// Sans base d'abord : une lecture qui aboutit mémoïse son résultat dans une propriété statique
		// (ConfLib::$cachedByStructure, MemberLib::$cMemberOnline), et la passe suivante n'a plus rien à
		// demander. Mesurée en second, la génération sans base rendrait le travail de la première.
		$withoutDatabase = $this->withoutDatabase(fn() => $this->build());
		$withDatabase = $this->build();

		foreach($withDatabase as $document => $expected) {

			$divergences = $this->getDivergences(
				$this->normalize($expected),
				$this->normalize($withoutDatabase[$document]),
				$document
			);

			$this->assertEquals([], $divergences, $document.' : '.count($divergences).' divergence(s) sans base, '.join(' | ', array_slice($divergences, 0, 3)));

		}

	}

	/**
	 * Le témoin : deux générations vides seraient identiques, et le test passerait sur un générateur
	 * qui ne produit plus rien.
	 */
	public function testTheGenerationIsNotEmpty(): void {

		$documents = $this->build();

		$this->assertEquals(TRUE, count($documents['openapi']['paths']) > 100, 'la référence OpenAPI porte ses routes');
		$this->assertEquals(TRUE, count($documents['postman']['item']) > 0, 'la collection Postman porte ses dossiers');

	}

	/**
	 * L'appel joué comme le workflow le joue : aucun serveur déclaré, donc toute lecture lève.
	 *
	 * Les serveurs sont remis en place ensuite, les tests suivants du même processus en ayant besoin.
	 * Database ne les rend pas : ses deux registres se lisent par réflexion.
	 */
	private function withoutDatabase(\Closure $call): array {

		$properties = [];

		foreach(['servers', 'bases'] as $name) {
			$properties[$name] = new \ReflectionProperty(\Database::class, $name);
		}

		$saved = array_map(fn(\ReflectionProperty $property) => $property->getValue(), $properties);

		\Database::resetServers();

		try {
			return $call();
		} finally {
			foreach($properties as $name => $property) {
				$property->setValue(NULL, $saved[$name]);
			}
		}

	}

	/**
	 * Les exemples de champs date et datetime portent l'instant de génération : les deux passes ne
	 * tombent pas à la même seconde. Le workflow neutralise ces dates de la même façon avant de
	 * décider s'il y a un vrai changement à publier.
	 */
	private function normalize(array $document): array {

		return json_decode(
			preg_replace('/\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2})?/', '@DATE@', json_encode($document)),
			TRUE
		);

	}

	/** Les chemins où les deux documents diffèrent, du plus général au plus précis. */
	private function getDivergences(array $expected, array $value, string $path): array {

		if($expected === $value) {
			return [];
		}

		$divergences = [];

		foreach($expected as $key => $expectedValue) {

			if(array_key_exists($key, $value) === FALSE) {
				$divergences[] = $path.'.'.$key.' (absent sans base)';
				continue;
			}

			if($expectedValue === $value[$key]) {
				continue;
			}

			if(is_array($expectedValue) and is_array($value[$key])) {
				$divergences = array_merge($divergences, $this->getDivergences($expectedValue, $value[$key], $path.'.'.$key));
				continue;
			}

			$divergences[] = $path.'.'.$key;

		}

		foreach($value as $key => $ignored) {

			if(array_key_exists($key, $expected) === FALSE) {
				$divergences[] = $path.'.'.$key.' (apparu sans base)';
			}

		}

		return $divergences;

	}

}
?>
