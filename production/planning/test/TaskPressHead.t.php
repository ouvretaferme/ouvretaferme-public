<?php
namespace planning;

/**
 * L'appui long sur la tête d'un bloc (retour 278, lot BU) : à la Semaine et au Jour, il ouvre le mode
 * Sélectionner et coche les cartes que vise la case de tête (`PlanningTaskSelection.getTargets()`), comme
 * l'appui long sur une carte coche la carte. Mêmes règles que sur une carte : le délai, le défilement qui
 * l'emporte, le clic qui termine l'appui avalé. Le « + » de la tête reste un lien, et une tête sans case (le
 * pense-bête) n'est pas visée. Le mode ouvert, l'appui long bascule la case de tête, et le mode se ferme
 * quand elle décoche tout (13/10).
 *
 * La case de tête suit aussi la recherche à chaque passage (arbitrage V-Planning du 13/10) : un jeton qui
 * masque les cartes à faire d'un bloc lui fait viser ses cartes faites, et un bloc que la recherche vide perd
 * sa case.
 *
 * `PlanningTaskPress`, `PlanningTaskSelection` et `PlanningTaskSearch` tournent dans node, extraites telles
 * quelles de `planning.js`, sur un écran simulé : un petit arbre d'éléments qui répond aux sélecteurs que ces
 * classes posent (`qs()`, `qsa()`, `closest()`, `matches()`), avec les cartes, les têtes, le « + », le
 * pense-bête et l'omnibox. Les minuteries sont tenues par le test, qui fait aboutir l'appui long sans attendre.
 *
 * Lancement : dev/test package=planning file=TaskPressHead
 */
class TaskPressHeadTest extends \Test {

	/**
	 * Un geste sur l'écran, puis la sélection, le mode et le clic qui termine le geste (ou le menu du
	 * navigateur), avalé ou non.
	 */
	public function testPress(): void {

		// [écran, cible, geste, pointeur, déplacement en px, téléphone, cartes cochées avant] => [sélection, mode ouvert, clic ou menu avalé]
		$cases = [
			'à la Semaine, un appui long au doigt sur la tête d\'un bloc mixte' => ['week', 'head-1', 'long', 'touch', 0, FALSE, [], [['11'], TRUE, TRUE]],
			'à la Semaine, un appui long au doigt sur la tête d\'un bloc tout fait' => ['week', 'head-2', 'long', 'touch', 0, FALSE, [], [['21', '22'], TRUE, TRUE]],
			'à la Semaine, un appui long au doigt sur la tête d\'un bloc à faire' => ['week', 'head-5', 'long', 'touch', 0, FALSE, [], [['51', '52'], TRUE, TRUE]],
			'à la Semaine, un appui long à la souris sur la tête d\'un bloc à faire' => ['week', 'head-5', 'long', 'mouse', 0, FALSE, [], [['51', '52'], TRUE, TRUE]],
			'à la Semaine, un appui long qui tremble sous le seuil' => ['week', 'head-5', 'long', 'touch', 4, FALSE, [], [['51', '52'], TRUE, TRUE]],
			'au Jour, un appui long sur la tête d\'un groupe tout fait' => ['day', 'head-4', 'long', 'touch', 0, FALSE, [], [['41', '42'], TRUE, TRUE]],
			'au Jour, un appui long sur la tête d\'un groupe de « Sans jour »' => ['day', 'head-3', 'long', 'touch', 0, FALSE, [], [['31', '32'], TRUE, TRUE]],
			'au Jour, un appui long sur la tête d\'un groupe de « Sans jour » replié au téléphone' => ['day', 'head-3', 'long', 'touch', 0, TRUE, [], [[], FALSE, FALSE]],
			'à la Semaine, un appui court sur la tête' => ['week', 'head-5', 'short', 'touch', 0, FALSE, [], [[], FALSE, FALSE]],
			'à la Semaine, un défilement commencé sur la tête' => ['week', 'head-5', 'long', 'touch', 20, FALSE, [], [[], FALSE, FALSE]],
			'à la Semaine, un appui long sur le « + » de la tête' => ['week', 'plus-5', 'long', 'touch', 0, FALSE, [], [[], FALSE, FALSE]],
			'au Jour, un appui long sur le « + » de la tête' => ['day', 'plus-4', 'long', 'touch', 0, FALSE, [], [[], FALSE, FALSE]],
			'à la Semaine, un appui long sur le pense-bête' => ['week', 'reminder', 'long', 'touch', 0, FALSE, [], [[], FALSE, FALSE]],
			'à la Semaine, un appui long du bouton droit sur la tête' => ['week', 'head-5', 'right', 'mouse', 0, FALSE, [], [[], FALSE, FALSE]],
			'dans le mode, un appui long sur la tête d\'un bloc non coché' => ['week', 'head-2', 'long', 'touch', 0, FALSE, ['11'], [['11', '21', '22'], TRUE, TRUE]],
			'dans le mode, un appui long sur la tête d\'un bloc coché' => ['week', 'head-2', 'long', 'touch', 0, FALSE, ['11', '21', '22'], [['11'], TRUE, TRUE]],
			'dans le mode, un appui long sur la tête du seul bloc coché' => ['week', 'head-2', 'long', 'touch', 0, FALSE, ['21', '22'], [[], FALSE, TRUE]],
			'dans le mode, un appui long sur la tête d\'un groupe à demi coché' => ['day', 'head-3', 'long', 'touch', 0, FALSE, ['31'], [['31', '32'], TRUE, TRUE]],
			'dans le mode, un appui court sur la tête' => ['week', 'head-2', 'short', 'touch', 0, FALSE, ['11'], [['11'], TRUE, FALSE]],
			'à la Semaine, un appui long sur une carte' => ['week', 'card-12', 'long', 'touch', 0, FALSE, [], [['12'], TRUE, TRUE]],
			'dans le mode, un appui long sur une carte' => ['week', 'card-51', 'long', 'touch', 0, FALSE, ['11'], [['11', '51'], TRUE, TRUE]],
			'à la Semaine, le menu du navigateur au doigt sur la tête' => ['week', 'head-5', 'contextmenu', 'touch', 0, FALSE, [], [[], FALSE, TRUE]],
			'à la Semaine, le menu du navigateur à la souris sur la tête' => ['week', 'head-5', 'contextmenu', 'mouse', 0, FALSE, [], [[], FALSE, FALSE]],
		];

		$results = $this->play('press', array_values(array_map(fn($case) => array_slice($case, 0, 7), $cases)));

		if($results === NULL) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$selection, $selecting, $prevented] = $results[$position];
			[$selectionExpected, $selectingExpected, $preventedExpected] = $cases[$case][7];

			$this->assertEquals($selectionExpected, $selection, $case.', la sélection n\'est pas la bonne');
			$this->assertEquals($selectingExpected, $selecting, $case.', le mode Sélectionner '.($selectingExpected ? 'reste fermé' : 'est ouvert'));
			$this->assertEquals($preventedExpected, $prevented, $case.', le geste qui termine l\'appui '.($preventedExpected ? 'passe au navigateur' : 'est avalé'));

		}

	}

	/**
	 * La case de tête du groupe Désherbage (31 et 32 à faire sur la carotte, 33 fait sur l'ail), en mode
	 * Sélectionner, après un jeton puis après l'avoir retiré.
	 */
	public function testSearch(): void {

		// [cartes cochées, jeton d'espèce] => [[tête cochée, à demi cochée, cachée] sous le jeton, puis sans]
		$cases = [
			'un jeton qui masque les cartes à faire du bloc, la carte faite cochée' => [['33'], 'Ail', [[TRUE, FALSE, FALSE], [FALSE, FALSE, FALSE]]],
			'un jeton qui masque les cartes à faire du bloc, rien de coché dans le bloc' => [['41'], 'Ail', [[FALSE, FALSE, FALSE], [FALSE, FALSE, FALSE]]],
			'un jeton qui masque tout le bloc' => [['41'], 'Poireau', [[FALSE, FALSE, TRUE], [FALSE, FALSE, FALSE]]],
			'un jeton qui garde les cartes à faire du bloc, l\'une cochée' => [['31'], 'Carotte', [[FALSE, TRUE, FALSE], [FALSE, TRUE, FALSE]]],
		];

		$results = $this->play('search', array_values(array_map(fn($case) => array_slice($case, 0, 2), $cases)));

		if($results === NULL) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			foreach(['sous le jeton', 'le jeton retiré'] as $step => $moment) {

				[$checked, $indeterminate, $empty] = $results[$position][$step];
				[$checkedExpected, $indeterminateExpected, $emptyExpected] = $cases[$case][2][$step];

				$this->assertEquals($checkedExpected, $checked, $case.', '.$moment.', la case de tête '.($checkedExpected ? 'reste décochée' : 'est cochée'));
				$this->assertEquals($indeterminateExpected, $indeterminate, $case.', '.$moment.', la case de tête '.($indeterminateExpected ? 'n\'est pas' : 'est').' à demi cochée');
				$this->assertEquals($emptyExpected, $empty, $case.', '.$moment.', la case de tête '.($emptyExpected ? 'reste visible' : 'disparaît'));

			}

		}

	}

	private function play(string $gesture, array $cases): ?array {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');
		$classes = '';

		foreach(['PlanningTaskSelection', 'PlanningTaskPress', 'PlanningTaskSearch'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return NULL;
			}

			$classes .= substr($source, $start, $end - $start + 2).'globalThis.'.$class.' = '.$class.";\n";

		}

		$script = strtr(self::SCRIPT, [
			'__CLASSES__' => json_encode($classes),
			'__GESTURE__' => json_encode($gesture),
			'__CASES__' => json_encode($cases),
		]);

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results) and count($results) === count($cases), 'node rend son résultat : '.$trace) === FALSE) {
			return NULL;
		}

		return $results;

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`), comme dans `TaskSelectGroup.t.php`.
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

	/*
	 * L'écran simulé. Un sélecteur se découpe au premier niveau, hors des parenthèses et des crochets : la
	 * virgule sépare les choix, l'espace et `>` les ancêtres. Un composé lit l'élément, l'identifiant, les
	 * classes, les attributs (`=`, `~=`), `:checked`, `:is()` et `:not()`, ce que les trois classes posent.
	 */
	private const SCRIPT = <<<'JS'
const vm = require('vm');

const split = (text, separator) => {

	const parts = [];
	let depth = 0;
	let current = '';

	for(const character of text) {

		if(character === '(' || character === '[') {
			depth++;
		} else if(character === ')' || character === ']') {
			depth--;
		}

		if(depth === 0 && character === separator) {
			parts.push(current);
			current = '';
		} else {
			current += character;
		}

	}

	parts.push(current);

	return parts.map(part => part.trim()).filter(part => part !== '');

};

const closing = (text, start, open, close) => {

	let depth = 0;

	for(let position = start; position < text.length; position++) {
		if(text[position] === open) {
			depth++;
		} else if(text[position] === close && --depth === 0) {
			return position;
		}
	}

	throw new Error('Sélecteur non fermé : '+ text);

};

const matchCompound = (node, text) => {

	let position = 0;

	while(position < text.length) {

		const character = text[position];

		if(character === '[') {

			const end = closing(text, position, '[', ']');
			const [, name, operator, value] = text.slice(position + 1, end).match(/^([\w-]+)(?:(~?=)"(.*)")?$/);
			const actual = node.getAttribute(name);

			if(actual === null || (operator === '=' && actual !== value) || (operator === '~=' && actual.split(' ').includes(value) === false)) {
				return false;
			}

			position = end + 1;

		} else if(character === ':') {

			const name = text.slice(position + 1).match(/^[\w-]+/)[0];
			position += 1 + name.length;

			if(name === 'checked') {

				if(node.checked !== true) {
					return false;
				}

			} else if(name === 'is' || name === 'not') {

				const end = closing(text, position, '(', ')');
				const found = node.matches(text.slice(position + 1, end));

				if(found === (name === 'not')) {
					return false;
				}

				position = end + 1;

			} else {
				throw new Error('Pseudo-classe inconnue : '+ name);
			}

		} else if(character === '#' || character === '.') {

			const name = text.slice(position + 1).match(/^[\w-]+/)[0];

			if((character === '#') ? (node.id !== name) : (node.classList.contains(name) === false)) {
				return false;
			}

			position += 1 + name.length;

		} else {

			const name = text.slice(position).match(/^[\w-]+/)[0];

			if(node.tagName !== name) {
				return false;
			}

			position += name.length;

		}

	}

	return true;

};

const matchComplex = (node, text) => {

	const tokens = split(text.replace(/\s*>\s*/g, ' > '), ' ');

	let index = tokens.length - 1;
	let current = node;

	if(matchCompound(current, tokens[index]) === false) {
		return false;
	}

	index--;

	while(index >= 0) {

		if(tokens[index] === '>') {

			current = current.parentNode;

			if(current === null || matchCompound(current, tokens[index - 1]) === false) {
				return false;
			}

			index -= 2;

		} else {

			do {
				current = current.parentNode;
			} while(current !== null && matchCompound(current, tokens[index]) === false);

			if(current === null) {
				return false;
			}

			index--;

		}

	}

	return true;

};

class List extends Array {
	filter(selector) {
		return (typeof selector === 'string') ? List.from(Array.from(this).filter(node => node.matches(selector))) : super.filter(selector);
	}
}

class Node {
}

class Element extends Node {

	constructor(tagName, attributes = {}, children = []) {

		super();

		this.tagName = tagName;
		this.parentNode = null;
		this.children = [];
		this.attributes = {};
		this.dataset = {};
		this.classes = new Set();
		this.style = {};
		this.checked = false;
		this.indeterminate = false;
		this.textContent = '';

		const classes = this.classes;

		this.classList = {
			contains: name => classes.has(name),
			add: name => classes.add(name),
			remove: name => classes.delete(name),
			toggle: (name, force) => {
				if(force ?? (classes.has(name) === false)) {
					classes.add(name);
				} else {
					classes.delete(name);
				}
			}
		};

		Object.entries(attributes).forEach(([name, value]) => {
			if(name === 'class') {
				value.split(' ').forEach(item => classes.add(item));
			} else if(name.startsWith('data-')) {
				this.dataset[name.slice(5).replace(/-([a-z])/g, (all, letter) => letter.toUpperCase())] = String(value);
			} else {
				this.attributes[name] = String(value);
			}
		});

		children.forEach(child => {
			child.parentNode = this;
			this.children.push(child);
		});

	}

	get id() {
		return this.attributes.id ?? '';
	}

	get value() {
		return this.attributes.value;
	}

	getAttribute(name) {

		if(name === 'class') {
			return Array.from(this.classes).join(' ');
		} else if(name.startsWith('data-')) {
			return this.dataset[name.slice(5).replace(/-([a-z])/g, (all, letter) => letter.toUpperCase())] ?? null;
		} else {
			return this.attributes[name] ?? null;
		}

	}

	matches(selector) {
		return split(selector, ',').some(complex => matchComplex(this, complex));
	}

	closest(selector) {

		let node = this;

		while(node !== null && node.matches(selector) === false) {
			node = node.parentNode;
		}

		return node;

	}

	getDescendants() {
		return this.children.flatMap(child => [child, ...child.getDescendants()]);
	}

	qsa(selector, each) {

		const nodes = List.from(this.getDescendants().filter(node => node.matches(selector)));

		if(each) {
			nodes.forEach(each);
		}

		return nodes;

	}

	qs(selector, found) {

		const node = this.getDescendants().find(node => node.matches(selector)) ?? null;

		if(node !== null && found) {
			found(node);
		}

		return node;

	}

	contains(other) {

		while(other !== null && other !== this) {
			other = other.parentNode;
		}

		return (other === this);

	}

	getBoundingClientRect() {
		return {top: 0, left: 0, width: 0, height: 0};
	}

}

const E = (tagName, attributes, children) => new Element(tagName, attributes, children);

const card = (id, batch, plant) => E('div', {class: (batch === 'done') ? 'planning-task planning-task-done' : 'planning-task', 'data-task': id}, [
	E('label', {class: 'planning-select'}, [E('input', {type: 'checkbox', name: 'batch[]', value: id, 'data-batch': batch})]),
	E('a', {class: 'planning-task-main', href: '/series/task:'+ id, 'data-probe': 'card-'+ id, 'data-search': JSON.stringify({plant: [plant]})}, [E('span', {class: 'planning-task-name'})])
]);

const head = (week, action) => E('div', {class: week ? 'util-title planning-block-head' : 'planning-block-head planning-day-action-head'}, [
	E('div', {class: 'planning-block-name'}, [
		E('label', {class: 'planning-select planning-select-group'}, [E('input', {type: 'checkbox', 'data-planning-select-group': ''})]),
		week ?
			E('h4', {'data-probe': 'head-'+ action}, [E('span', {class: 'util-counter'})]) :
			E('div', {class: 'planning-day-action-name', 'data-probe': 'head-'+ action})
	]),
	E('div', {class: 'planning-block-end'}, [
		E('a', {class: 'btn btn-sm btn-outline-primary', href: '/series/task:create?action='+ action, 'data-probe': 'plus-'+ action}, [E('i', {class: 'bi-plus-lg'})])
	])
]);

const box = () => E('div', {class: 'planning-search'}, [
	E('div', {id: 'planning-search', 'data-groups': JSON.stringify({plant: 'Espèce'})})
]);

const screens = {
	week: () => E('div', {id: 'planning-week'}, [
		box(),
		E('div', {id: 'planning-week-board'}, [
			E('section', {class: 'util-card planning-week-block', id: 'planning-week-block-1', 'data-action': 1}, [head(true, 1), card('11', 'todo', 'Carotte'), card('12', 'done', 'Carotte')]),
			E('section', {class: 'util-card planning-week-block', id: 'planning-week-block-2', 'data-action': 2}, [head(true, 2), card('21', 'done', 'Carotte'), card('22', 'done', 'Carotte')]),
			E('section', {class: 'util-card planning-week-block', id: 'planning-week-block-5', 'data-action': 5}, [head(true, 5), card('51', 'todo', 'Carotte'), card('52', 'todo', 'Carotte')])
		]),
		E('section', {class: 'util-note planning-reminder-block'}, [
			E('div', {class: 'util-title planning-block-head', id: 'planning-reminder-head'}, [E('h4', {'data-probe': 'reminder'})])
		])
	]),
	day: () => E('div', {id: 'planning-day'}, [
		box(),
		E('div', {class: 'planning-day-column', 'data-column': '2026-10-14'}, [
			E('div', {class: 'planning-day-action', 'data-action': 4}, [head(false, 4), card('41', 'done', 'Poireau'), card('42', 'done', 'Poireau')])
		]),
		E('div', {class: 'planning-day-column', 'data-column': 'none'}, [
			E('div', {class: 'planning-day-action', 'data-action': 3}, [head(false, 3), card('31', 'todo', 'Carotte'), card('32', 'todo', 'Carotte'), card('33', 'done', 'Ail')])
		])
	])
};

const state = {root: null, phone: false, tokens: {}, timers: new Map(), timer: 0};

const context = vm.createContext({
	Element,
	Node,
	console,
	queueMicrotask: callback => callback(),
	setTimeout: (callback) => {
		state.timer++;
		state.timers.set(state.timer, callback);
		return state.timer;
	},
	clearTimeout: id => state.timers.delete(id),
	window: {
		innerHeight: 800,
		matchMedia: () => ({matches: state.phone})
	},
	qs: (selector, found) => state.root.qs(selector, found),
	qsa: (selector, each) => state.root.qsa(selector, each),
	Lime: {Dropdown: {purge: () => {}}},
	MainPiles: {stack: () => {}},
	PlanningPointer: {THRESHOLD: 8},
	UtilOmnibox: {
		getTokenGroups: () => state.tokens,
		normalize: value => value.toLowerCase()
	}
});

vm.runInContext(__CLASSES__, context);

const selection = context.PlanningTaskSelection;
const press = context.PlanningTaskPress;
const search = context.PlanningTaskSearch;

search.count = () => {};
search.link = () => {};

const open = (screen, phone, checked) => {

	state.root = E('html', {}, [E('body', {}, [screens[screen]()])]);
	state.phone = phone;
	state.tokens = {};
	state.timers.clear();

	selection.selection = new Set();
	selection.selecting = false;
	press.press = null;
	press.swallow = null;
	search.folded = '';

	if(checked.length > 0) {

		checked.forEach(id => {
			state.root.qs('.planning-task[data-task="'+ id +'"] [name="batch[]"]').checked = true;
			selection.selection.add(id);
		});

		selection.enter();

	}

};

const gestures = {

	press: ([screen, probe, gesture, pointerType, move, phone, checked]) => {

		open(screen, phone, checked);

		const target = state.root.qs('[data-probe="'+ probe +'"]');
		const event = extra => ({
			button: 0, pointerType, clientX: 0, clientY: 0, detail: 1, target, prevented: false,
			preventDefault() {
				this.prevented = true;
			},
			stopPropagation() {},
			...extra
		});

		let prevented;

		press.down(event((gesture === 'right') ? {button: 2} : {}));

		if(gesture === 'contextmenu') {

			const menu = event({});
			press.contextmenu(menu);
			prevented = menu.prevented;

			press.cancel();

		} else {

			if(move > 0) {
				press.move(event({clientY: move}));
			}

			if(gesture !== 'short') {
				Array.from(state.timers.values()).forEach(callback => callback());
				state.timers.clear();
			}

			press.cancel();

			const click = event({});
			press.click(click);
			prevented = click.prevented;

		}

		return [Array.from(selection.selection).sort(), selection.selecting, prevented];

	},

	search: ([checked, plant]) => {

		open('day', false, checked);

		const group = state.root.qs('.planning-day-action[data-action="3"] [data-planning-select-group]');
		const read = () => [group.checked, group.indeterminate, group.closest('.planning-select').classList.contains('planning-select-empty')];

		state.tokens = {'Espèce': [plant]};
		search.filter();
		const filtered = read();

		state.tokens = {};
		search.filter();

		return [filtered, read()];

	}

};

process.stdout.write(JSON.stringify(__CASES__.map(gestures[__GESTURE__])));
JS;

}
?>
