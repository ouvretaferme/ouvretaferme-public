<?php
namespace planning;

/**
 * Le glisser sur la durée d'une ligne de la fiche de temps (R-Retours-0810, 201) : la tuile suit le pointeur
 * d'un quart d'heure par `PlanningDurationStep.DRAG_STEP` rem, de 0 à une journée, et le temps ne part qu'au
 * lâcher, par l'envoi de « Enregistrer » (`PlanningDurationStep.send()`). Un appui sans pas ouvre la fenêtre
 * comme avant, un glisser ne l'ouvre pas, et une ligne dont la fenêtre est ouverte ne se glisse pas.
 *
 * `PlanningDurationStep` tourne dans node, extraite telle quelle de `planning.js`, sur une tuile factice qui
 * ne porte que ce que le geste lit : sa valeur, la fenêtre qui la suit et le champ de cette fenêtre. Le rem
 * vaut 16 px. L'écran déclare son champ comme la fiche (`PlanningDurationStep.register()`), et ce qu'il
 * envoie est relevé au lieu de partir. Le départ du geste, la souris dès l'appui et le doigt au-delà du seuil,
 * est celui de `PlanningPointer`, que la réglette de la journée éprouve déjà (`TimeRuleAiTest`).
 *
 * Lancement : dev/test package=planning file=TimeDayDrag
 */
class TimeDayDragTest extends \Test {

	/**
	 * Trois pas vers la droite ajoutent trois quarts d'heure, qui partent au lâcher, et la tuile les dit dans
	 * la teinte du temps passé, sans crayon.
	 */
	public function testThreeStepsRight(): void {

		$result = $this->gesture(1.0, 100, [100 + 3 * $this->step() + 2]);

		$this->assertEquals([1.75], $result['posted'], 'trois pas à droite n\'écrivent pas 1h45');
		$this->assertEquals('1h45', $result['tile'], 'la tuile ne suit pas le glisser');
		$this->assertTrue($result['spent'], 'la tuile d\'un temps posé perd la teinte du temps passé');
		$this->assertEquals('1h45', $result['field'], 'le champ de la fenêtre ne reprend pas la durée glissée');

	}

	/**
	 * Un glisser vers la gauche au-delà de zéro s'arrête à zéro, et la tuile s'éteint. Vers la droite, il
	 * s'arrête à une journée.
	 */
	public function testBounds(): void {

		$result = $this->gesture(1.0, 300, [300 - 10 * $this->step()]);

		$this->assertEquals([0], $result['posted'], 'un glisser à gauche ne ramène pas à 0h00');
		$this->assertEquals('0h00', $result['tile'], 'la tuile ne dit pas 0h00 après un retour à zéro');
		$this->assertFalse($result['spent'], 'une tuile à zéro garde la teinte du temps passé');

		$result = $this->gesture(23.5, 0, [5 * $this->step()]);

		$this->assertEquals([24], $result['posted'], 'un glisser à droite passe une journée');

	}

	/**
	 * Le glisser n'envoie rien tant que le pointeur ne se lève pas, puis une seule fois, la dernière durée
	 * visée. Un pas fait puis défait n'envoie rien.
	 */
	public function testOneRequestOnDrop(): void {

		$step = $this->step();

		$result = $this->gesture(0.5, 0, [$step, 2 * $step, 3 * $step, 2 * $step + 1], drop: FALSE);

		$this->assertEquals([], $result['posted'], 'le glisser écrit avant le lâcher');
		$this->assertEquals('1h00', $result['tile'], 'la tuile ne suit pas le dernier pas');

		$result = $this->gesture(0.5, 0, [$step, 2 * $step, 3 * $step, 2 * $step + 1]);

		$this->assertEquals([1], $result['posted'], 'le lâcher n\'écrit pas une seule fois la dernière durée');

		$result = $this->gesture(0.5, 0, [$step, 0]);

		$this->assertEquals([], $result['posted'], 'un pas défait avant le lâcher écrit encore');

	}

	/**
	 * Un appui sans pas laisse passer son clic, qui ouvre la fenêtre. Un glisser d'au moins un pas avale le
	 * sien, même revenu à son départ, mais pas un clic d'Entrée au clavier.
	 */
	public function testClick(): void {

		$step = $this->step();

		$result = $this->gesture(1.0, 0, [$step - 1]);

		$this->assertEquals([], $result['posted'], 'un appui sans pas écrit');
		$this->assertFalse($result['swallowed'], 'un appui sans pas n\'ouvre plus la fenêtre');

		$result = $this->gesture(1.0, 0, [$step]);

		$this->assertTrue($result['swallowed'], 'un glisser ouvre la fenêtre en lâchant');

		$result = $this->gesture(1.0, 0, [$step, 0]);

		$this->assertTrue($result['swallowed'], 'un glisser revenu à son départ ouvre la fenêtre en lâchant');

		$result = $this->gesture(1.0, 0, [$step], keyboard: TRUE);

		$this->assertFalse($result['swallowed'], 'le clic d\'Entrée qui suit un glisser est avalé');

	}

	/**
	 * Sur une ligne dont la fenêtre est ouverte, le pointeur ne prend pas la tuile : le geste reste à la
	 * fenêtre, et la tuile la referme d'un appui.
	 */
	public function testOpenWindow(): void {

		$result = $this->gesture(1.0, 0, [3 * $this->step()], open: TRUE);

		$this->assertEquals([], $result['posted'], 'une ligne à la fenêtre ouverte se glisse');
		$this->assertEquals('1h00', $result['tile'], 'la tuile d\'une fenêtre ouverte suit le pointeur');
		$this->assertFalse($result['swallowed'], 'la tuile d\'une fenêtre ouverte ne la referme plus');

	}

	/**
	 * Un geste que le navigateur reprend (`pointercancel`) rend à la tuile sa durée de départ, sans rien écrire.
	 */
	public function testCancel(): void {

		$result = $this->gesture(1.0, 0, [2 * $this->step()], drop: FALSE, cancel: TRUE);

		$this->assertEquals([], $result['posted'], 'un geste repris écrit');
		$this->assertEquals('1h00', $result['tile'], 'un geste repris laisse la tuile sur la durée glissée');

	}

	/**
	 * Un glisser que le serveur refuse rend à la tuile la dernière durée écrite : la réponse d'un refus ne
	 * redessine pas la ligne, et la fenêtre se rouvre sur la durée refusée.
	 */
	public function testRefused(): void {

		$result = $this->gesture(1.0, 0, [3 * $this->step()], refused: TRUE);

		$this->assertEquals([1.75], $result['posted'], 'le glisser n\'envoie pas sa durée');
		$this->assertEquals('1h00', $result['tile'], 'la tuile garde la durée refusée');
		$this->assertEquals('1h45', $result['field'], 'la fenêtre ne se rouvre pas sur la durée refusée');

	}

	/**
	 * Un pas en pixels, au rem de 16 px.
	 */
	private function step(): int {

		$source = $this->getSource();

		if(preg_match('/static DRAG_STEP = ([0-9.]+);/', $source, $match) !== 1) {
			return 0;
		}

		return (int)round((float)$match[1] * 16);

	}

	private function getSource(): string {
		return file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');
	}

	/**
	 * Joue un glisser sur la tuile d'une durée de `$time` heures : pointeur posé à l'abscisse `$x`, déplacé aux
	 * suivantes, puis lâché, et le clic qui suit. `$open` ouvre d'abord la fenêtre de la ligne, `$keyboard` donne
	 * au clic le `detail` d'Entrée, `$cancel` fait reprendre le geste par le navigateur, `$refused` fait refuser
	 * l'envoi par le serveur.
	 */
	private function gesture(float $time, int $x, array $positions, bool $drop = TRUE, bool $open = FALSE, bool $keyboard = FALSE, bool $cancel = FALSE, bool $refused = FALSE): array {

		$source = $this->getSource();

		$start = strpos($source, "\nclass PlanningDurationStep {");
		$end = strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE and $this->step() > 0, 'planning.js déclare PlanningDurationStep et son pas DRAG_STEP') === FALSE) {
			return ['posted' => NULL, 'tile' => NULL, 'spent' => NULL, 'field' => NULL, 'swallowed' => NULL];
		}

		$class = substr($source, $start, $end - $start + 2);

		$script = '
			const vm = require("vm");

			const makeClassList = names => {
				const set = new Set(names);
				return {
					contains: name => set.has(name),
					add: (...list) => list.forEach(name => set.add(name)),
					remove: (...list) => list.forEach(name => set.delete(name)),
					toggle: (name, force) => (force ?? set.has(name) === false) ? set.add(name) : set.delete(name)
				};
			};

			const makeNode = (names, children = {}) => {
				const node = {classList: makeClassList(names), dataset: {}, value: "", textContent: ""};
				node.qs = (selector, callback) => {
					const found = children[selector] ?? null;
					return (found !== null && callback !== undefined) ? callback(found) : found;
				};
				node.contains = other => other === node || Object.values(children).some(child => child.contains?.(other));
				return node;
			};

			const time = '.json_encode($time).';

			const hour = makeNode(["timesheet-time-field-hour"]);
			const minute = makeNode(["timesheet-time-field-minute"]);
			const group = makeNode(["timesheet-time-field-group"]);

			const field = makeNode(["planning-duration-step"], {
				"input[name=\"timeAddHour\"]": hour,
				"input[name=\"timeAddMinute\"]": minute,
				".timesheet-time-field-group": group
			});
			field.dataset = {duration: "task-1-2026-10-09", sent: String(time)};
			field.matches = selector => selector === "#planning-time .planning-time-time .planning-duration-step";

			const stepWindow = makeNode(["planning-duration-step-window"].concat('.json_encode($open).' ? ["is-open"] : []), {".planning-duration-step": field});
			field.closest = selector => (selector === ".planning-duration-step-window") ? stepWindow : null;

			const value = makeNode(["planning-duration-tile-value"].concat(time > 0 ? ["badge", "badge-time-spent"] : []));
			const tile = makeNode(["planning-duration-step-tile"], {".planning-duration-tile-value": value});
			tile.nextElementSibling = stepWindow;
			stepWindow.previousElementSibling = tile;

			const posted = [];

			const context = vm.createContext({
				document: {documentElement: {}},
				getComputedStyle: () => ({fontSize: "16px"}),
				requestAnimationFrame: () => {},
				Node: class {
					static [Symbol.hasInstance](target) {
						return typeof target?.contains === "function";
					}
				},
				qs: () => null,
				qsa: () => {},
				PlanningDuration: {readTyped: input => input.value === "" ? null : parseInt(input.value), close: () => {}, refused: json => json.refused, reopen: () => {}},
				PlanningTask: {
					MAX_TIME: 24,
					round: value => Math.round(value * 100) / 100,
					format: value => {
						const minutes = Math.round(value * 60);
						return Math.floor(minutes / 60) + "h" + String(minutes % 60).padStart(2, "0");
					}
				}
			});

			vm.runInContext('.json_encode($class).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;

			// La place de la fenêtre rouverte lit la géométrie de la page, que ces cas n\'éprouvent pas.
			PlanningDurationStep.place = () => {};

			value.textContent = context.PlanningTask.format(time);

			PlanningDurationStep.register({
				selector: "#planning-time .planning-time-time .planning-duration-step",
				bounded: true,
				change: (field, value) => {
					posted.push(value);
					// La promesse naît dans le contexte, où `send()` la reconnaît (`instanceof Promise`).
					return '.json_encode($refused).' ? vm.runInContext("Promise.resolve({refused: true})", context) : undefined;
				}
			});

			PlanningDurationStep.write(field, time);

			const positions = '.json_encode($positions).';

			PlanningDurationStep.grab(tile, {clientX: '.json_encode($x).'});
			positions.forEach(x => PlanningDurationStep.drag(x));

			if('.json_encode($cancel).') {
				PlanningDurationStep.release();
			}

			let swallowed = false;

			if('.json_encode($drop).') {
				PlanningDurationStep.drop();
				PlanningDurationStep.click({
					target: value,
					detail: '.json_encode($keyboard ? 0 : 1).',
					preventDefault: () => swallowed = true,
					stopPropagation: () => {}
				});
			}

			// La réponse du serveur arrive après le lâcher.
			setTimeout(() => process.stdout.write(JSON.stringify({
				posted: posted,
				tile: value.textContent,
				spent: value.classList.contains("badge-time-spent"),
				field: hour.value + "h" + minute.value,
				swallowed: swallowed
			})), 0);
		';

		return $this->runNode($script, ['posted', 'tile', 'spent', 'field', 'swallowed']);

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`), comme dans `main/test/JuicinessAi.t.php`.
	 */
	private function runNode(string $script, array $keys): array {

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? array_fill_keys($keys, NULL);

	}

}
?>
