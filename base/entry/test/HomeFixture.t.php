<?php
namespace entry;

/**
 * Le décor du garde-fou de la réponse ciblée sur l'accueil : l'accueil rendu, les instructions d'une écriture
 * ou d'une annulation faites depuis son champ appliquées dessus, doit être exactement l'accueil rendu de
 * nouveau après elle (`check()`).
 *
 * Le rendu est celui de la vraie page (`dashboard/page/dashboard.p.php`, vue `/ferme/{id}/accueil`), sur
 * la structure du décor, dans la transaction du test. Un bloc lourd y est un emplacement, que le
 * navigateur remplit par sa route : les deux rendus le remplissent de même avant d'être comparés, sans
 * quoi l'accueil rechargé montrerait un emplacement là où la réponse a posé le bloc.
 *
 * Les instructions s'appliquent comme `ajax.js` les applique (`PlanningFingerprintTest`, dont la méthode
 * est reprise). Une intention d'un package fermé s'y éprouve depuis son package, la phrase qu'elle écrit
 * n'étant pas à nommer ici.
 *
 * Abstraite, donc ignorée par `TestLib::run()`.
 */
abstract class HomeFixtureTest extends EntryFixtureTest {

	/** Ce que la dernière route du champ a préparé. */
	protected ?\stdClass $lastData = NULL;

	public function setUp(): void {

		parent::setUp();

		$this->assertTrue(\dashboard\DashboardSetting::isEnabled($this->eStructure), 'l\'accueil est ouvert à la structure du décor');

	}

	/**
	 * L'accueil tel que la page le rend, ses emplacements remplis.
	 */
	protected function render(): \Dom\HTMLDocument {

		$path = 'ferme/'.$this->eStructure['id'].'/accueil';

		[$data, $view, $template] = $this->prepareView($path, [], 'GET', 'dashboard/dashboard', '/ferme/{id}/accueil');

		ob_start();
		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);
		$html = ob_get_clean();

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$this->fill($document);

		return $document;

	}

	/**
	 * Remplit chaque emplacement comme son `onrender` le fait : par la route du bloc.
	 */
	protected function fill(\Dom\HTMLDocument $document): void {

		$prefix = \dashboard\DashboardUi::getBlockId('');

		foreach(iterator_to_array($document->querySelectorAll('.dashboard-block-pending')) as $node) {

			$block = substr($node->getAttribute('id'), strlen($prefix));

			[$data, $view, $template] = $this->prepareView('ferme/'.$this->eStructure['id'].'/accueil/bloc/'.$block, [], 'GET', 'dashboard/dashboard', '/ferme/{id}/accueil/bloc/{name}');

			new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);

			$this->apply($document, $template->formatInstructions());

		}

	}

	/**
	 * La page exécutée, puis sa vue prête à rendre.
	 */
	protected function prepareView(string $path, array $input, string $method, string $file, string $name): array {

		$action = $this->executePageAction($path, $input, $method);

		$this->assertTrue($action instanceof \ViewAction, $path.' se rend');

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		require_once \Package::getFileFromUri($file, 'view');

		$view = \View::get($name, $action);
		$template = new \ReflectionMethod(\View::class, 'getTemplate')->invoke($view);
		$template->setData($data);

		return [$data, $view, $template];

	}

	/**
	 * Les blocs que l'écran montre, comme `EntryField.setContext()` les lit.
	 */
	protected function getShown(\Dom\HTMLDocument $document): string {

		$prefix = \dashboard\DashboardUi::getBlockId('');

		$blocks = array_map(fn(\Dom\Element $node) => substr($node->getAttribute('id'), strlen($prefix)), iterator_to_array($document->querySelectorAll('[id^="'.$prefix.'"]')));

		return implode(',', $blocks);

	}

	protected function getInstructions(string $route, array $input): array {

		[$data, $view, $template] = $this->prepareView('entry/entry:'.$route, $input + ['structure' => $this->eStructure['id']], 'POST', 'entry/entry', $route);

		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);

		$this->lastData = $data;

		return $template->formatInstructions();

	}

	protected function cancel(\Dom\HTMLDocument $document, array $undo): array {

		return $this->getInstructions('doCancel', $undo + [
			'origin' => 'dashboard',
			'blocks' => $this->getShown($document),
		]);

	}

	/**
	 * Applique les instructions comme `Ajax.Instruction.render()` (`framework/util/static/js/ajax.js`) : un
	 * sélecteur qui ne trouve rien ne fait rien, une instruction `js` (la pastille) n'a pas de sélecteur.
	 */
	protected function apply(\Dom\HTMLDocument $document, array $instructions): void {

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js') {
				continue;
			}

			[$mode, $selector, $calls] = $instruction;

			$this->assertEquals('qs', $mode, 'instruction '.$mode.' prise en charge');

			$node = $document->querySelector($selector);

			if($node === NULL) {
				continue;
			}

			foreach($calls as $call) {

				[$method, $arguments] = $call + [1 => []];

				switch($method) {

					case 'outerHtml' :
						$node->replaceWith(...$this->parse($document, $arguments[0]));
						break;

					case 'insertAdjacentHtml' :
						$this->assertEquals('beforeend', $arguments[0], 'les colonnes se posent en fin d\'accueil');
						$node->append(...$this->parse($document, $arguments[1]));
						break;

					case 'remove' :
						$node->remove();
						break;

					default :
						$this->assertFail('instruction '.$method.' prise en charge');
						break;

				}

			}

		}

	}

	/**
	 * Le client refuse un HTML à plusieurs racines (`renderOuter()` et `renderAdjacentHTML()`,
	 * `framework/util/static/js/lime.js`), que le DOM du test accepterait sans rien dire.
	 */
	protected function parse(\Dom\HTMLDocument $document, string $html): array {

		$container = $document->createElement('div');
		$container->innerHTML = ltrim($html);

		$this->assertTrue($container->childNodes->length <= 1, 'une seule racine par instruction, comme le client l\'exige');

		return iterator_to_array($container->childNodes);

	}

	/**
	 * Les chiffres de la semaine et les colonnes de l'accueil : le reste de la tête porte le champ et le jour,
	 * qu'aucune écriture ne redessine. Les ids `field-<aléa>` que le cadriciel tire pour chaque champ sont
	 * neutralisés.
	 */
	protected function getFingerprint(\Dom\HTMLDocument $document): string {

		$columns = $document->querySelector('#dashboard > .dashboard-columns');

		$this->assertTrue($columns !== NULL, 'les colonnes de l\'accueil sont rendues');

		$html = '';

		foreach($document->querySelectorAll('#dashboard .dashboard-head-type-sentence') as $node) {
			$html .= $document->saveHtml($node);
		}

		$html .= $document->saveHtml($columns);

		return preg_replace('/field-[a-z0-9]+/', 'field-X', $html);

	}

	protected function check(\Dom\HTMLDocument $before, array $instructions, string $case): void {

		$this->apply($before, $instructions);

		// Un emplacement que la réponse pose se remplit à son tour, par son `onrender`.
		$this->fill($before);

		$expected = $this->getFingerprint($this->render());
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	protected function getGap(string $expected, string $actual): string {

		if($expected === $actual) {
			return '';
		}

		$position = strspn($expected ^ $actual, "\0");
		$start = max(0, $position - 200);

		return "\n rechargé : ".substr($expected, $start, 500)."\n ciblé    : ".substr($actual, $start, 500);

	}

}
?>
