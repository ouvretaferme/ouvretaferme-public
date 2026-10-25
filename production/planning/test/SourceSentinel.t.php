<?php
namespace planning;

/**
 * Les packages qui disent au planning ce qui est dû (`DueLib`), et la forme de leur observateur.
 *
 * Un package devient une source en posant `<package>/lib/Planning.o.php` : le cadriciel l'inscrit
 * au registre (`ouvretaferme/package.c.php`) sans que personne ne le décide. Ce qu'il rend s'affiche
 * alors sur le planning de toutes les structures, et ses types se mêlent à ceux des autres sources
 * dans la même table. D'où quatre inventaires, lus dans le code :
 * - les sources sont celles que déclare `SOURCES`, dans les deux sens ;
 * - chaque source pose ses deux observateurs, dont les méthodes publiques sont exactement les
 *   événements que `DueLib` et `DueUi` appellent, une méthode publique de plus devenant un événement ;
 * - le registre suit les fichiers : il est suivi par git et ne se régénère pas en production, qui
 *   ne lit que lui (`DueLib::getSources()`) ;
 * - chaque constante d'un `PlanningObserverLib` est un type préfixé par `<source>-`, pour que deux
 *   sources ne puissent pas écrire le même type.
 *
 * C'est le code qui fait foi : une source apparue se déclare ici avec sa raison, une source disparue
 * sort de la déclaration, un registre en retard se régénère en ouvrant une page de la dev, un écart
 * de forme se corrige dans l'observateur.
 *
 * Inventaire pur, sans écriture en base. Le registre se lit tel qu'il est sur le disque : les
 * requêtes `dev/` ne le régénèrent pas (`dev\PackageLib::__construct()`).
 *
 * Lancement : dev/test package=planning file=SourceSentinel
 */
class SourceSentinelTest extends \Test {

	/**
	 * Les sources, avec ce qu'elles disent dû.
	 * - `toolbox` : les entretiens du carnet, en retard au compteur ou datés (contrat « Alertes du
	 *   planning », 03/10/2026).
	 */
	const SOURCES = [
		'toolbox' => 'entretiens du carnet',
	];

	/**
	 * Les événements que `DueLib` appelle sur `PlanningObserverLib`, et `DueUi` sur `PlanningObserverUi`.
	 */
	const EVENTS = [
		'lib' => ['describeDue', 'getDue', 'getDueStructures'],
		'ui' => ['getDue', 'getDueTitle'],
	];

	public function testSourcesDeclared(): void {

		$sources = $this->getSources('lib');
		$declared = array_keys(self::SOURCES);

		$undeclared = array_diff($sources, $declared);
		$missing = array_diff($declared, $sources);

		$this->assertEquals([], array_values($undeclared), 'un package pose lib/Planning.o.php sans être déclaré dans SourceSentinelTest::SOURCES, c\'est la déclaration qui s\'ajoute, avec ce que la source dit dû : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($missing), 'une source déclarée dans SourceSentinelTest::SOURCES n\'a plus de lib/Planning.o.php, c\'est la déclaration qui se retire : '.implode(', ', $missing));

	}

	public function testObserversComplete(): void {

		$sources = $this->getSources('lib');

		$withoutUi = array_diff($sources, $this->getSources('ui'));
		$withoutLib = array_diff($this->getSources('ui'), $sources);

		$this->assertEquals([], array_values($withoutUi), 'une source n\'a pas de ui/Planning.o.php pour dire ses mots, c\'est l\'observateur qui s\'écrit : '.implode(', ', $withoutUi));
		$this->assertEquals([], array_values($withoutLib), 'un package a un ui/Planning.o.php sans lib/Planning.o.php, c\'est l\'un qui s\'écrit ou l\'autre qui se retire : '.implode(', ', $withoutLib));

		foreach(self::EVENTS as $layer => $events) {

			foreach($this->getSources($layer) as $source) {

				$methods = $this->getEvents($source, $layer);

				$absent = array_diff($events, $methods);
				$extra = array_diff($methods, $events);

				$this->assertEquals([], array_values($absent), $source.'\\PlanningObserver'.ucfirst($layer).' ne répond pas à un événement du planning, c\'est la méthode qui s\'écrit : '.implode(', ', $absent));
				$this->assertEquals([], array_values($extra), $source.'\\PlanningObserver'.ucfirst($layer).' porte une méthode publique que le planning n\'appelle pas, et que le registre inscrit comme un événement, c\'est elle qui passe en protected : '.implode(', ', $extra));

			}

		}

	}

	public function testRegistryFollowsFiles(): void {

		$observers = \Package::getObservers();

		foreach(self::EVENTS as $layer => $events) {

			foreach($events as $event) {

				$registered = $observers[$layer]['planning'][$event] ?? [];
				$sources = $this->getSources($layer);

				$absent = array_diff($sources, $registered);
				$extra = array_diff($registered, $sources);

				$this->assertEquals([], array_values($absent), 'ouvretaferme/package.c.php n\'inscrit pas '.$layer.'.planning.'.$event.' pour une source qui l\'observe, c\'est le registre qui se régénère en ouvrant une page de la dev, puis part avec le commit : '.implode(', ', $absent));
				$this->assertEquals([], array_values($extra), 'ouvretaferme/package.c.php inscrit '.$layer.'.planning.'.$event.' pour un package qui ne l\'observe plus, c\'est le registre qui se régénère en ouvrant une page de la dev, puis part avec le commit : '.implode(', ', $extra));

			}

		}

	}

	public function testTypesPrefixed(): void {

		$wrong = [];

		foreach($this->getSources('lib') as $source) {

			$constants = new \ReflectionClass('\\'.$source.'\\PlanningObserverLib')->getConstants();

			foreach($constants as $name => $value) {

				if(is_string($value) === FALSE or str_starts_with($value, $source.'-') === FALSE) {
					$wrong[] = $source.'\\PlanningObserverLib::'.$name;
				}

			}

		}

		$this->assertEquals([], $wrong, 'une constante d\'observateur du planning n\'est pas un type préfixé par sa source, c\'est la constante qui se renomme : '.implode(', ', $wrong));

	}

	/**
	 * Les packages qui posent `<layer>/Planning.o.php`, lus sur le disque comme le fait le cadriciel
	 * pour construire le registre (`dev\PackageLib::getObservers()`).
	 */
	private function getSources(string $layer): array {

		$sources = [];

		foreach(array_keys(\Package::getList()) as $package) {

			if(is_file(\Package::getPath($package).'/'.$layer.'/Planning.o.php')) {
				$sources[] = $package;
			}

		}

		return $sources;

	}

	private function getEvents(string $source, string $layer): array {

		$methods = new \ReflectionClass('\\'.$source.'\\PlanningObserver'.ucfirst($layer))->getMethods(\ReflectionMethod::IS_PUBLIC);

		$events = array_map(fn(\ReflectionMethod $method) => $method->getName(), $methods);
		sort($events);

		return $events;

	}

}
?>
