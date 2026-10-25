<?php
namespace planning;

/**
 * Le geste de la réglette de la journée sur une journée au-delà de l'échelle (R-Retours-0410-soir, T, tranché
 * par V-Planning) : « un geste qui ne quitte pas le bout hachuré garde la journée ». La réglette ne rend aucune
 * valeur au-delà de 12h, et un clic dans le bout hachuré d'une journée de 15h45 l'aurait ramenée à 12h00 sans
 * rien dire.
 *
 * `PlanningTimeRule` tourne dans node, extraite telle quelle de `planning.js`, sur une réglette factice qui ne
 * porte que ce que le geste lit : sa géométrie (480 px pour 12h, soit 40 px par heure), son bout hachuré
 * (`planning-time-rule-end`, 2,5 rem soit 40 px, de 440 à 480) et ses `data-*`. L'envoi (`PlanningTimeWorking.post()`) est
 * relevé au lieu de partir : c'est lui qui écrit la journée.
 *
 * Lancement : dev/test package=planning file=TimeRuleAi
 */
class TimeRuleAiTest extends \Test {

	/**
	 * Un clic dans le bout hachuré, puis un glissé qui y reste, n'envoient rien : la journée reste à 15h45 et
	 * la réglette garde son bout hachuré.
	 */
	public function testClickInBeyondKeepsDay(): void {

		$result = $this->gesture('15.75', [470]);

		$this->assertEquals([], $result['posted'], 'un clic dans le bout hachuré écrit la journée');
		$this->assertTrue($result['beyond'], 'la réglette perd son bout hachuré après un clic dedans');
		$this->assertEquals('100%', $result['knob'], 'la poignée quitte le bout de la réglette');

		$result = $this->gesture('15.75', [470, 445, 478]);

		$this->assertEquals([], $result['posted'], 'un glissé qui reste dans le bout hachuré écrit la journée');

	}

	/**
	 * Un glissé qui sort du bout hachuré pose la valeur visée, comme sur toute réglette : 300 px valent 7h30. Et
	 * une fois sorti, revenir dans le bout ne rend pas la journée : le geste pose alors 12h00.
	 */
	public function testDragOutOfBeyondChangesDay(): void {

		$result = $this->gesture('15.75', [470, 300]);

		$this->assertEquals([7.5], $result['posted'], 'le glissé sorti du bout hachuré n\'écrit pas la valeur visée');
		$this->assertFalse($result['beyond'], 'la réglette garde son bout hachuré pour une journée de 7h30');

		$result = $this->gesture('15.75', [470, 300, 478]);

		$this->assertEquals([12], $result['posted'], 'le glissé revenu dans le bout hachuré rend la journée au lieu de la valeur visée');

	}

	/**
	 * Le même clic sur une journée dans l'échelle pose la valeur visée, 11h45 : seul le bout hachuré garde la
	 * journée.
	 */
	public function testClickWithinScale(): void {

		$result = $this->gesture('4', [470]);

		$this->assertEquals([11.75], $result['posted'], 'un clic sur une journée dans l\'échelle n\'écrit pas la valeur visée');

	}

	/**
	 * Le survol du bout hachuré annonce la journée, et non 12h00 : « Journée », le libellé que la réglette donne
	 * à la bulle, puis sa durée (R-Retours-0810, 179).
	 */
	public function testHoverInBeyond(): void {

		$result = $this->gesture('15.75', [], hover: 470);

		$this->assertEquals(['Journée', '15h45'], $result['ghost'], 'le survol du bout hachuré n\'annonce pas la journée');

	}

	/**
	 * Joue un geste sur la réglette d'une journée de `$time` heures : pointeur posé à la première abscisse,
	 * déplacé aux suivantes, puis lâché. Sans abscisse, seul le survol est joué, à `$hover`.
	 */
	private function gesture(string $time, array $positions, ?int $hover = NULL): array {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningTimeRule {");
		$end = strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningTimeRule') === FALSE) {
			return ['posted' => NULL, 'beyond' => NULL, 'knob' => NULL, 'ghost' => NULL];
		}

		$class = substr($source, $start, $end - $start + 2);

		$script = '
			const vm = require("vm");

			const makeNode = className => {
				const node = {
					className: className,
					style: {},
					textContent: "",
					children: [],
					classes: new Set(className.split(" ")),
					append: (...children) => node.children.push(...children),
					remove: () => {}
				};
				node.qs = selector => {
					for(const child of node.children) {
						if(child.className.split(" ").includes(selector.slice(1))) {
							return child;
						}
						const found = child.qs?.(selector) ?? null;
						if(found !== null) {
							return found;
						}
					}
					return null;
				};
				node.classList = {
					contains: name => node.classes.has(name),
					remove: name => node.classes.delete(name),
					toggle: (name, force) => force ? node.classes.add(name) : node.classes.delete(name)
				};
				return node;
			};

			const time = '.json_encode($time).';
			const rule = makeNode("planning-time-rule" + (parseFloat(time) > 12 ? " planning-time-rule-beyond" : ""));
			const end = makeNode("planning-time-rule-end");
			end.getBoundingClientRect = () => ({left: 440, right: 480, width: 40});
			const knob = makeNode("planning-time-rule-knob");

			rule.dataset = {time: time, scale: "12", working: "working-test", label: "Journée"};
			rule.getBoundingClientRect = () => ({left: 0, right: 480, width: 480});
			rule.qs = (selector, callback) => {
				let found = null;
				if(selector === ".planning-time-rule-end") {
					found = end;
				} else if(selector === ".planning-time-rule-knob") {
					found = knob;
				} else if(selector === ".planning-time-rule-ghost") {
					found = rule.children.find(child => child.className === "planning-time-rule-ghost") ?? null;
				}
				if(callback !== undefined) {
					return (found === null) ? null : callback(found);
				}
				return found;
			};

			const posted = [];

			const context = vm.createContext({
				document: {
					createElement: () => makeNode(""),
					qs: selector => selector
				},
				PlanningDuration: {close: () => {}},
				PlanningTimeWorking: {post: (tile, value) => posted.push(value)},
				PlanningTask: {format: value => Math.floor(value) + "h" + String(Math.round((value - Math.floor(value)) * 60)).padStart(2, "0")}
			});

			vm.runInContext('.json_encode($class).' + ";globalThis.PlanningTimeRule = PlanningTimeRule;", context);

			const PlanningTimeRule = context.PlanningTimeRule;
			const positions = '.json_encode($positions).';
			let ghost = null;

			if(positions.length === 0) {
				PlanningTimeRule.enter(rule, {pointerType: "mouse"});
				PlanningTimeRule.hover({clientX: '.json_encode($hover).'});
				const bubble = rule.qs(".planning-time-rule-ghost");
				ghost = (bubble === null) ? null : bubble.children.map(child => child.textContent);
			} else {
				PlanningTimeRule.start(rule, {clientX: positions[0]});
				positions.slice(1).forEach(x => PlanningTimeRule.place(x));
				PlanningTimeRule.end();
			}

			process.stdout.write(JSON.stringify({
				posted: posted,
				beyond: rule.classList.contains("planning-time-rule-beyond"),
				knob: knob.style.left ?? null,
				ghost: ghost
			}));
		';

		return $this->runNode($script, ['posted', 'beyond', 'knob', 'ghost']);

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
