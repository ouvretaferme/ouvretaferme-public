<?php
namespace entry;

/**
 * Chaque intention du registre porte-t-elle toutes les méthodes que le champ lui demande ?
 *
 * Le moteur et l'écran du champ n'appellent une intention que par son nom de classe, lu dans le registre
 * (`EntryLib::getIntentions()`, `EntryUi::getIntentions()`) : `$intention::write()`, `$ui::getLines()`. Ni PHP
 * ni l'éditeur ne relient cet appel à une classe, et une méthode qui manque à l'une d'elles ne lève qu'au
 * premier passage par cette intention, en production, loin du lot qui a ajouté l'appel. c9dae3038 (18/10) a
 * ainsi fait appeler `getLines()` à l'écran de chaque intention en ne l'écrivant que pour la vente.
 *
 * Les méthodes demandées se déclarent donc ici, une fois, et deux contrôles les tiennent :
 * - la déclaration est l'inventaire des appels du code : elle se compare, dans les deux sens, aux appels
 *   relevés dans `lib/`, `ui/`, `page/` et `view/` (`VARIABLES`), le code faisant foi ;
 * - chaque classe du registre, intentions des packages fermés comprises, porte chaque méthode déclarée, par
 *   elle-même ou par `IntentionLib` et `IntentionUi`.
 *
 * Lancement : dev/test package=entry file=IntentionCallSentinel
 */
class IntentionCallSentinelTest extends \Test {

	/**
	 * Ce que le moteur demande à la classe `IntentionLib` d'une intention, et où.
	 */
	const array METHODS_LIB = [
		'canWrite' => "Entry.l.php, le droit d'écrire, qui filtre les intentions lues",
		'getWords' => "Entry.l.php, les mots du verbe",
		'isExplicit' => "Entry.l.php, le verbe dit en tête de phrase",
		'isImplicit' => "Entry.l.php, l'objet lu sans verbe",
		'getImplicitPrecedence' => "Entry.l.php, le départage de deux intentions qui lisent sans verbe le même objet",
		'getObjects' => "Entry.l.php, les objets que la phrase peut nommer",
		'getListed' => "Entry.l.php, ceux que la liste propose",
		'getTyped' => "Entry.l.php, ceux que la frappe propose",
		'readObject' => "Entry.l.php, l'objet lu dans les mots",
		'readQualifiers' => "Entry.l.php, les mots qui précisent l'objet",
		'isSearching' => "Entry.l.php, la frappe encore en recherche",
		'getComplements' => "Entry.l.php, les compléments lus en plus",
		'getDateRefusal' => "Entry.l.php, le jour que l'intention refuse",
		'hasRecap' => "Entry.l.php et DateComplement.l.php, le récapitulatif avant l'écriture",
		'readsRest' => "DateComplement.l.php, les mots que l'intention lit elle-même",
		'resolve' => "Entry.l.php, ce qui reste à décider",
		'isOffered' => "Entry.l.php, l'écriture proposée",
		'getModel' => "Entry.l.php, la table qui porte la transaction de l'écriture et de l'annulation",
		'prepare' => "Entry.l.php, le contrôle avant l'écriture",
		'write' => "Entry.l.php, l'écriture",
		'afterCommit' => "Entry.l.php, ce qui suit l'écriture validée",
		'getCancellation' => "Entry.l.php, ce que l'annulation défera",
		'getCancelFail' => "Entry.l.php, le refus d'une annulation",
		'cancel' => "Entry.l.php, l'annulation",
		'afterCancel' => "Entry.l.php, ce qui suit l'annulation validée",
		'getDashboardBlocks' => "Home.l.php, les pièces de l'accueil que l'écriture change",
		'getShown' => "entry.p.php, les objets que la liste montre",
		'writesPlanning' => "entry.p.php, le planning que l'écriture redessine",
		'getPlanningTasks' => "entry.p.php, les interventions qu'il redessine",
	];

	/**
	 * Ce que l'écran du champ demande à la classe `IntentionUi` d'une intention, et où.
	 */
	const array METHODS_UI = [
		'getChips' => "Entry.u.php, les gestes du champ vide",
		'getToken' => "Entry.u.php, le jeton du verbe",
		'getLabel' => "Entry.u.php, le nom de l'intention",
		'getResults' => "Entry.u.php, la liste sous le champ",
		'getRefusalResults' => "Entry.u.php, la liste sous un refus",
		'getRefusal' => "Entry.u.php, le texte d'un refus propre à l'intention",
		'getAwaited' => "Entry.u.php, ce que la phrase attend encore",
		'getAwaitedStep' => "Entry.u.php, l'étape qui l'attend",
		'getPlaceholder' => "Entry.u.php, le texte du champ vide à chaque étape",
		'getDays' => "Entry.u.php, les jours de la réponse",
		'getObjectIcon' => "Entry.u.php, l'icône de l'objet en jeton",
		'getLines' => "Entry.u.php, les lignes d'une intention qui en lit plusieurs",
		'getCancelUrl' => "Entry.u.php, l'adresse de l'annulation",
		'getWriteToast' => "entry.v.php, le message de l'écriture",
		'getWriteEvent' => "entry.v.php, l'événement que l'écriture lance dans la page",
		'getCancelToast' => "entry.v.php, le message de l'annulation",
		'getCancelEvent' => "entry.v.php, l'événement que l'annulation lance dans la page",
	];

	/**
	 * Ce que porte chaque variable sur laquelle le package appelle une méthode statique, par fichier : une
	 * intention du moteur (`lib`) ou de l'écran (`ui`), ou autre chose (`NULL`). `$entry[2]` est l'intention
	 * d'une lecture candidate (`EntryLib::readText()`). Une variable neuve se déclare ici avant d'être relevée :
	 * un appel qui passerait par elle sur une intention échapperait sinon à la déclaration.
	 */
	const array VARIABLES = [
		'lib/Entry.l.php' => ['intention' => 'lib', 'class' => 'lib', 'entry' => 'lib', 'complement' => NULL],
		'page/entry.p.php' => ['intention' => 'lib'],
		'ui/Entry.u.php' => ['ui' => 'ui'],
		'view/entry.v.php' => ['ui' => 'ui'],
	];

	/**
	 * Les appels relevés dans le code du package, par côté (`lib`, `ui`), et les variables que `VARIABLES` ne
	 * déclare pas (`unknown`). Un appel indexé sur le registre (`EntryLib::getIntentions()[$key]::readsRest()`)
	 * se relève par sa forme, écran dans `ui/` et `view/`, moteur ailleurs.
	 */
	private function getCalls(): array {

		$calls = ['lib' => [], 'ui' => [], 'unknown' => []];

		foreach(array_keys(\dev\SentinelLib::getFiles('.php')) as $relative) {

			if(preg_match('#^base/entry/(lib|ui|page|view)/[^/]+$#', $relative, $match) === 0) {
				continue;
			}

			$local = substr($relative, strlen('base/entry/'));
			$content = file_get_contents(\dev\SentinelLib::getRoot().'/'.$relative);

			preg_match_all('/\$(\w+)(?:\[[^\]]*\])*::(\w+)\(/', $content, $matches, PREG_SET_ORDER);

			foreach($matches as [, $variable, $method]) {

				if(array_key_exists($variable, self::VARIABLES[$local] ?? []) === FALSE) {
					$calls['unknown'][] = $local.' $'.$variable;
				} else if(self::VARIABLES[$local][$variable] !== NULL) {
					$calls[self::VARIABLES[$local][$variable]][] = $method;
				}

			}

			// Crochets imbriqués compris : `getIntentions()[$reading['intention']]::`.
			preg_match_all('/(?:getIntentions|getAllowed)\([^)]*\)\[(?:[^\[\]]|\[[^\]]*\])*\]::(\w+)\(/', $content, $matches);
			array_push($calls[in_array($match[1], ['ui', 'view'], TRUE) ? 'ui' : 'lib'], ...$matches[1]);

		}

		return array_map(fn(array $values) => array_values(array_unique($values)), $calls);

	}

	/**
	 * La déclaration est l'inventaire des appels : un appel neuf se déclare, une déclaration sans appel se retire.
	 */
	public function testDeclared(): void {

		$calls = $this->getCalls();

		$this->assertEquals([], $calls['unknown'], 'variables sur lesquelles base/entry appelle une méthode statique sans être déclarées dans IntentionCallSentinel::VARIABLES, à y déclarer avec ce qu\'elles portent : '.implode(', ', $calls['unknown']));

		foreach(['lib' => 'METHODS_LIB', 'ui' => 'METHODS_UI'] as $side => $constant) {

			$declared = array_keys(constant('self::'.$constant));

			$this->assertTrue($calls[$side] !== [], 'aucun appel '.$side.' relevé : les motifs ne trouvent plus rien');

			$undeclared = array_diff($calls[$side], $declared);
			$unused = array_diff($declared, $calls[$side]);

			$this->assertEquals([], array_values($undeclared), 'méthodes que le champ demande à une intention sans être déclarées dans IntentionCallSentinel::'.$constant.', à y déclarer : '.implode(', ', $undeclared));
			$this->assertEquals([], array_values($unused), 'méthodes déclarées dans IntentionCallSentinel::'.$constant.' que le champ ne demande plus à une intention, à retirer de la déclaration : '.implode(', ', $unused));

		}

	}

	/**
	 * Chaque classe du registre porte chaque méthode déclarée que le champ appelle. Une déclaration sans appel
	 * n'est demandée à personne : `testDeclared()` la fait retirer.
	 */
	public function testMethods(): void {

		$calls = $this->getCalls();
		$missing = [];

		foreach(['lib' => [EntryLib::getIntentions(), self::METHODS_LIB], 'ui' => [EntryUi::getIntentions(), self::METHODS_UI]] as $side => [$classes, $declared]) {

			$methods = array_intersect(array_keys($declared), $calls[$side]);

			foreach($classes as $class) {

				foreach($methods as $method) {

					if(method_exists($class, $method) === FALSE) {
						$missing[] = $class.'::'.$method.'()';
					}

				}

			}

		}

		$this->assertEquals([], $missing, 'méthodes que le champ appelle sur une intention du registre et que sa classe ne porte pas, à écrire dans la classe ou dans IntentionLib / IntentionUi : '.implode(', ', $missing));

	}

}
?>
