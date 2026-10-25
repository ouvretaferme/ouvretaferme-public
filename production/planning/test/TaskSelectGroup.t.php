<?php
namespace planning;

/**
 * La case de tête d'un bloc en mode Sélectionner (retours 275 et 276, lot BT) : elle reste sur un bloc où
 * tout est fait. Sur un bloc qui a encore des cartes à faire, elle ne prend que celles-là, pour garder
 * « Fait ! » dans la barre (retour 56) ; sur un bloc entièrement fait, elle prend toutes ses cartes. Son état,
 * cochée ou à demi cochée, suit les cartes qu'elle vise. Une copie du Jour, qui ne porte pas de case, compte
 * par la case de sa carte d'origine.
 *
 * Décocher la dernière carte, par sa case ou par la case de tête, ferme le mode Sélectionner (13/10).
 *
 * `PlanningTaskSelection` tourne dans node, extraite telle quelle de `planning.js`, sur un bloc simulé qui ne
 * porte que ce que la case lit : ses cartes, la case de chacune ou, pour une copie, celle de sa carte
 * d'origine ailleurs sur l'écran, leurs jetons `data-batch`, la marque de la recherche
 * (`planning-search-out`) et la case de tête. `change()` est ramené à `updateGroups()`, la barre de lot
 * n'étant pas sur l'arbre simulé.
 *
 * Lancement : dev/test package=planning file=TaskSelectGroup
 */
class TaskSelectGroupTest extends \Test {

	/**
	 * Un clic sur la case de tête : les cartes qu'il coche ou décoche, puis l'état de la case.
	 */
	public function testToggle(): void {

		// [cartes du bloc : [jeton, cochée, masquée par la recherche, copie du Jour], case de tête cochée par le clic] => [cartes choisies, tête cochée, tête à demi cochée, tête cachée]
		$cases = [
			'un bloc à faire' => [[['todo', FALSE, FALSE, FALSE], ['todo', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], TRUE, [['1', '2'], TRUE, FALSE, FALSE]],
			'un bloc à faire, décoché' => [[['todo', TRUE, FALSE, FALSE], ['todo', TRUE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], FALSE, [[], FALSE, FALSE, FALSE]],
			'un bloc tout fait' => [[['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], TRUE, [['1', '2', '3'], TRUE, FALSE, FALSE]],
			'un bloc tout fait, décoché' => [[['done', TRUE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE]], FALSE, [[], FALSE, FALSE, FALSE]],
			'un bloc tout fait dont la recherche masque une carte' => [[['done', FALSE, FALSE, FALSE], ['done', FALSE, TRUE, FALSE], ['done', FALSE, FALSE, FALSE]], TRUE, [['1', '3'], TRUE, FALSE, FALSE]],
			'un bloc à faire dont la recherche masque la carte à faire' => [[['todo', FALSE, TRUE, FALSE], ['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], TRUE, [['2', '3'], TRUE, FALSE, FALSE]],
			'un bloc du Jour fait de copies faites' => [[['done', FALSE, FALSE, TRUE], ['done', FALSE, FALSE, TRUE]], TRUE, [['1', '2'], TRUE, FALSE, FALSE]],
			'un bloc du Jour dont la seule carte à faire est une copie' => [[['todo', FALSE, FALSE, TRUE], ['done', FALSE, FALSE, FALSE]], TRUE, [['1'], TRUE, FALSE, FALSE]],
		];

		$results = $this->play('toggle', $cases);

		if($results === NULL) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$selection, $checked, $indeterminate, $empty] = $results[$position]['state'];
			[$selectionExpected, $checkedExpected, $indeterminateExpected, $emptyExpected] = $cases[$case][2];

			$this->assertEquals($selectionExpected, $selection, $case.', la case de tête ne prend pas les bonnes cartes');
			$this->assertEquals($checkedExpected, $checked, $case.', la case de tête '.($checkedExpected ? 'reste décochée' : 'reste cochée'));
			$this->assertEquals($indeterminateExpected, $indeterminate, $case.', la case de tête '.($indeterminateExpected ? 'n\'est pas' : 'est').' à demi cochée');
			$this->assertEquals($emptyExpected, $empty, $case.', la case de tête '.($emptyExpected ? 'reste visible' : 'disparaît'));

		}

	}

	/**
	 * L'état de la case de tête, relu des cartes cochées une à une, et les cases lues avant toute écriture
	 * (CLAUDE.md, « Mesurer puis écrire »).
	 */
	public function testState(): void {

		// [cartes du bloc : [jeton, cochée, masquée par la recherche, copie du Jour]] => [tête cochée, tête à demi cochée, tête cachée]
		$cases = [
			'un bloc à faire, rien de coché' => [[['todo', FALSE, FALSE, FALSE], ['todo', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], NULL, [FALSE, FALSE, FALSE]],
			'un bloc à faire, une carte à faire cochée' => [[['todo', TRUE, FALSE, FALSE], ['todo', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], NULL, [FALSE, TRUE, FALSE]],
			'un bloc à faire, toutes les cartes à faire cochées' => [[['todo', TRUE, FALSE, FALSE], ['todo', TRUE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], NULL, [TRUE, FALSE, FALSE]],
			'un bloc à faire, seule la carte faite cochée' => [[['todo', FALSE, FALSE, FALSE], ['todo', FALSE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE]], NULL, [FALSE, FALSE, FALSE]],
			'un bloc tout fait, rien de coché' => [[['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], NULL, [FALSE, FALSE, FALSE]],
			'un bloc tout fait, une carte cochée' => [[['done', TRUE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], NULL, [FALSE, TRUE, FALSE]],
			'un bloc tout fait, toutes ses cartes cochées' => [[['done', TRUE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE]], NULL, [TRUE, FALSE, FALSE]],
			'un bloc que la recherche vide' => [[['done', FALSE, TRUE, FALSE], ['todo', FALSE, TRUE, FALSE]], NULL, [FALSE, FALSE, TRUE]],
			'un bloc du Jour fait de copies, rien de coché' => [[['done', FALSE, FALSE, TRUE], ['done', FALSE, FALSE, TRUE]], NULL, [FALSE, FALSE, FALSE]],
			'un bloc du Jour fait de copies, une carte d\'origine cochée' => [[['done', TRUE, FALSE, TRUE], ['done', FALSE, FALSE, TRUE]], NULL, [FALSE, TRUE, FALSE]],
		];

		$results = $this->play('state', $cases);

		if($results === NULL) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$selection, $checked, $indeterminate, $empty] = $results[$position]['state'];
			[$checkedExpected, $indeterminateExpected, $emptyExpected] = $cases[$case][2];

			$this->assertEquals($checkedExpected, $checked, $case.', la case de tête '.($checkedExpected ? 'reste décochée' : 'est cochée'));
			$this->assertEquals($indeterminateExpected, $indeterminate, $case.', la case de tête '.($indeterminateExpected ? 'n\'est pas' : 'est').' à demi cochée');
			$this->assertEquals($emptyExpected, $empty, $case.', la case de tête '.($emptyExpected ? 'reste visible' : 'disparaît'));
			$this->assertFalse($results[$position]['readAfterWrite'], $case.', une case est lue après une écriture');

		}

	}

	/**
	 * Décocher la dernière carte, par sa case ou par la case de tête, ferme le mode Sélectionner (13/10,
	 * qui défait le retour 288), et le menu ouvert par une entrée de la barre se ferme avec lui
	 * (`Lime.Dropdown.purge()`). Tant qu'une carte reste cochée, le mode reste ouvert.
	 */
	public function testLeavesEmpty(): void {

		// [cartes du bloc : [jeton, cochée, masquée par la recherche, copie du Jour], case cochée par le clic] => [cartes choisies, menus fermés]
		$cases = [
			'la dernière carte décochée par sa case' => [[['todo', TRUE, FALSE, FALSE], ['todo', FALSE, FALSE, FALSE]], FALSE, [[], 1]],
			'la dernière carte faite décochée par sa case' => [[['done', TRUE, FALSE, FALSE]], FALSE, [[], 1]],
			'une carte décochée sur deux' => [[['todo', TRUE, FALSE, FALSE], ['todo', TRUE, FALSE, FALSE]], FALSE, [['2'], 0]],
		];

		$casesGroup = [
			'les dernières cartes décochées par la case de tête' => [[['todo', TRUE, FALSE, FALSE], ['todo', TRUE, FALSE, FALSE], ['done', FALSE, FALSE, FALSE]], FALSE, [[], 1]],
			'un bloc tout fait décoché par la case de tête' => [[['done', TRUE, FALSE, FALSE], ['done', TRUE, FALSE, FALSE]], FALSE, [[], 1]],
		];

		$results = $this->play('card', $cases);
		$resultsGroup = $this->play('toggle', $casesGroup);

		if($results === NULL or $resultsGroup === NULL) {
			return;
		}

		foreach([[$cases, $results], [$casesGroup, $resultsGroup]] as [$casesPlayed, $resultsPlayed]) {

			foreach(array_keys($casesPlayed) as $position => $case) {

				[$selectionExpected, $purgedExpected] = $casesPlayed[$case][2];

				$this->assertEquals($selectionExpected, $resultsPlayed[$position]['state'][0], $case.', la sélection ne garde pas les bonnes cartes');
				$this->assertEquals($selectionExpected !== [], $resultsPlayed[$position]['selecting'], $case.', le mode Sélectionner '.($selectionExpected === [] ? 'reste ouvert' : 'se ferme'));
				$this->assertEquals($purgedExpected, $resultsPlayed[$position]['purged'], $case.', '.($purgedExpected > 0 ? 'le menu de la barre reste ouvert' : 'le menu de la barre se ferme'));

			}

		}

	}

	/**
	 * Joue chaque cas sur son bloc : `card` coche ou décoche la première carte par `toggle()`, `toggle` coche
	 * ou décoche la case de tête puis passe par `toggleGroup()`, `state` relit l'état par `updateGroups()`.
	 */
	private function play(string $gesture, array $cases): ?array {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTaskSelection {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTaskSelection') === FALSE) {
			return NULL;
		}

		$script = '
			const vm = require("vm");

			// `qsa()` rend une `NodeList`, à qui lime.js donne un `filter()` à sélecteur.
			class List extends Array {
				filter(selector) {
					return (typeof selector === "string") ? Array.from(this).filter(node => node.matches(selector)) : super.filter(selector);
				}
			}

			// `Lime.Dropdown.purge()` compte les menus fermés.
			let heads = [];
			let purged = 0;
			const context = vm.createContext({
				Lime: {Dropdown: {purge: () => purged++}},
				qsa: (selector, each) => {
					const nodes = selector.includes("[data-planning-select-group]") ? heads : [];
					nodes.forEach(each);
					return List.from(nodes);
				}
			});

			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningTaskSelection = PlanningTaskSelection;", context);

			const selection = context.PlanningTaskSelection;
			selection.change = () => selection.updateGroups();

			const results = '.json_encode(array_values(array_map(fn($case) => [$case[0], $case[1]], $cases))).'.map(([cards, click]) => {

				let written = false;
				let readAfterWrite = false;

				const read = value => {
					if(written) {
						readAfterWrite = true;
					}
					return value;
				};

				selection.selection = new Set();
				selection.selecting = true;
				purged = 0;

				// La case d\'une copie vit sur la carte d\'origine, dans une autre colonne de l\'écran.
				const elsewhere = new Map();
				const screen = {
					qs: selector => read(elsewhere.get(selector.match(/data-task="([0-9]+)"/)[1]) ?? null)
				};

				const nodes = cards.map(([batch, checked, out, copy], index) => {

					const value = String(index + 1);
					let state = checked;

					const field = {
						value: value,
						get checked() { return read(state); },
						set checked(change) { state = change; },
						matches: selector => read((selector === ":checked") ? state : (selector.includes("[data-batch~=\"todo\"]") === false || batch === "todo")),
						// Le bloc n\'est pas dans la semaine en Colonnes, où la première case cochée ouvre le mode (V42).
						closest: selector => read(null)
					};

					if(checked) {
						selection.selection.add(value);
					}

					if(copy) {
						elsewhere.set(value, field);
					}

					return {
						dataset: {task: value},
						out: out,
						field: copy ? null : field,
						qs: selector => read(copy ? null : field),
						closest: selector => screen
					};

				});

				// Les cartes du bloc, ou les cases qu\'elles portent quand le sélecteur les vise.
				const group = {
					qsa: selector => {
						const visible = nodes.filter(card => card.out === false || selector.includes(":not(.planning-search-out)") === false);
						return read(List.from(selector.includes("[name=\"batch[]\"]") ? visible.filter(card => card.field !== null && card.field.matches(selector)).map(card => card.field) : visible));
					}
				};

				const label = {
					classes: new Set(),
					classList: {
						toggle(name, force) {
							written = true;
							if(force) {
								label.classes.add(name);
							} else {
								label.classes.delete(name);
							}
						}
					}
				};

				// Deux cases de tête sur l\'écran, pour qu\'une écriture sur la première précède les lectures de la seconde.
				const createHead = () => ({
					state: {checked: false, indeterminate: false},
					get checked() { return this.state.checked; },
					set checked(value) { written = true; this.state.checked = value; },
					get indeterminate() { return this.state.indeterminate; },
					set indeterminate(value) { written = true; this.state.indeterminate = value; },
					closest: selector => (selector === ".planning-select") ? label : group
				});

				const head = createHead();

				heads = [head, createHead()];

				if('.json_encode($gesture).' === "toggle") {
					head.state.checked = click;
					selection.toggleGroup(head);
				} else if('.json_encode($gesture).' === "card") {
					nodes[0].field.checked = click;
					selection.toggle(nodes[0].field);
				} else {
					selection.updateGroups();
				}

				return {
					state: [Array.from(selection.selection).sort(), head.state.checked, head.state.indeterminate, label.classes.has("planning-select-empty")],
					selecting: selection.selecting,
					purged: purged,
					readAfterWrite: readAfterWrite
				};

			});

			process.stdout.write(JSON.stringify(results));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results) and count($results) === count($cases), 'node rend son résultat : '.$trace) === FALSE) {
			return NULL;
		}

		return $results;

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`), comme dans `TimeRuleAi.t.php`.
	 */
	private function runNode(string $script): array {

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return [json_decode($output, TRUE), $output.$errors];

	}

}
?>
