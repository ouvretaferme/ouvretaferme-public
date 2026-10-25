<?php
namespace util;

/**
 * Ce que devient le curseur après un choix dans une autocomplétion (`AutocompleteField.onSelect()`) : il reste dans
 * le champ choisi au clavier, d'où Tab mène au suivant, et la liste se rouvre au clic, à la flèche ou à la saisie,
 * sans qu'une recherche partie avant le choix la rouvre d'elle-même. Sur un écran tactile, il quitte le champ.
 */
class FormAutocompleteSelectTest extends \Test {

	public function testKeyboardKeepsFocus() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});
			emit({blurred, active: document.activeElement === input, range: input.range, selected: selected.length});
		');

		$this->assertEquals(0, $result['blurred'] ?? NULL, 'le choix ne retire pas le curseur');
		$this->assertTrue($result['active'] ?? NULL, 'le curseur reste dans le champ choisi');
		$this->assertEquals([0, 6], $result['range'] ?? NULL, 'le texte choisi est sélectionné, que la saisie suivante remplace');
		$this->assertEquals(1, $result['selected'] ?? NULL);

	}

	public function testFullscreenBlurs() {

		$result = $this->runBrowser('
			fullscreen = true;
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});
			emit({blurred});
		');

		$this->assertEquals(1, $result['blurred'] ?? NULL, 'sur un écran tactile, le choix replie le clavier virtuel');

	}

	/**
	 * Un champ à plusieurs choix qui garde le texte tapé (`data-autocomplete-keep-query`, retour 345 du planning) : le
	 * choix à la souris a pris le curseur au champ, qui le reprend sans rouvrir la liste, avec le texte sélectionné.
	 */
	public function testKeepQueryMouse() {

		$result = $this->runBrowser('
			const input = fakeInput(null, "field", {multiple: true, keepQuery: true});
			input.value = "tom";
			document.activeElement = null;
			field.apply(input, {value: "12", itemHtml: "Tomate"});
			emit({text: input.value, value: items.value(), active: document.activeElement === input, range: input.range ?? null, open});
		');

		$this->assertEquals(['text' => 'tom', 'value' => ['12'], 'active' => TRUE, 'range' => [0, 3], 'open' => FALSE], $result, 'texte gardé, choix ajouté, curseur repris sur le texte sélectionné, liste fermée');

	}

	public function testKeepQueryFullscreen() {

		$result = $this->runBrowser('
			fullscreen = true;
			const input = fakeInput(null, "field", {multiple: true, keepQuery: true});
			input.value = "tom";
			document.activeElement = null;
			field.apply(input, {value: "12", itemHtml: "Tomate"});
			emit({text: input.value, focused, range: input.range ?? null});
		');

		$this->assertEquals(['text' => 'tom', 'focused' => 0, 'range' => NULL], $result, 'sur un écran tactile, le texte reste sans reprendre le curseur, qui rouvrirait le clavier virtuel');

	}

	public function testMultipleClearsQuery() {

		$result = $this->runBrowser('
			const input = fakeInput(null, "field", {multiple: true});
			input.value = "tom";
			document.activeElement = null;
			field.apply(input, {value: "12", itemHtml: "Tomate"});
			emit({text: input.value, value: items.value(), focused});
		');

		$this->assertEquals(['text' => '', 'value' => ['12'], 'focused' => 0], $result, 'sans l\'option, le choix vide le texte tapé sans reprendre le curseur');

	}

	/**
	 * La croix d'un champ à plusieurs choix vide le texte tapé, qu'il soit gardé ou non, sans ajouter de choix vide.
	 */
	public function testKeepQueryCross() {

		$result = $this->runBrowser('
			const input = fakeInput(null, "field", {multiple: true, keepQuery: true});
			input.value = "tom";
			field.apply(input, {value: "12", itemHtml: "Tomate"});

			field.query(input);
			dropdown.hasAttribute = () => false;
			dropdown.previousElementSibling = input;
			const cross = {previousSibling: dropdown, previousElementSibling: dropdown};

			field.empty(cross);

			emit({text: input.value, value: items.value(), open});
		');

		$this->assertEquals(['text' => '', 'value' => ['12'], 'open' => FALSE], $result, 'texte vidé, aucun choix vide ajouté, liste fermée');

	}

	/**
	 * La recherche que la saisie a programmée, et celle qui attend sa réponse, ne rouvrent pas la liste après le choix.
	 */
	public function testQueryBeforeSelect() {

		$result = $this->runBrowser('
			const input = fakeInput();

			input.value = "Bin";
			input.input();
			field.apply(input, {value: "1", itemText: "Binage"});
			const timers = pendingTimers.length;

			field.query(input);
			const other = {};
			document.activeElement = other;
			field.apply(input, {value: "1", itemText: "Binage"});
			pendingAnswers.forEach(answer => answer());

			emit({timers, sent: bodies.length, html: dropdown.innerHTML, focused, ignore: field.ignoreNextFocus, active: document.activeElement === other});
		');

		$this->assertEquals(0, $result['timers'] ?? NULL, 'la recherche programmée par la saisie est annulée');
		$this->assertEquals(1, $result['sent'] ?? NULL, 'seule la recherche lancée avant le second choix part');
		$this->assertEquals('', $result['html'] ?? NULL, 'sa réponse ne remplit pas la liste');
		$this->assertEquals(0, $result['focused'] ?? NULL, 'ni ne reprend le curseur au champ voisin');
		$this->assertFalse($result['ignore'] ?? NULL, 'ni ne fait ignorer le focus suivant');
		$this->assertTrue($result['active'] ?? NULL);

	}

	/**
	 * Une recherche lancée après le choix remplit la liste comme avant.
	 */
	public function testQueryAfterSelect() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});
			field.query(input);
			pendingAnswers.forEach(answer => answer());
			emit({html: dropdown.innerHTML});
		');

		$this->assertEquals('<ul class="autocomplete-list"><li data-n="0" data-dropdown-keep class="">Carotte</li></ul>', $result['html'] ?? NULL);

	}

	/**
	 * Le champ qui a gardé le curseur ne reçoit plus de `focusin` : un clic ou la flèche vers le bas rouvrent la liste,
	 * et seulement quand elle est fermée.
	 */
	public function testReopen() {

		$result = $this->runBrowser('
			const input = fakeInput();

			input.click();
			const afterClick = bodies.length;
			input.click();
			const afterClickOpen = bodies.length;

			open = false;
			let prevented = 0;
			input.keydown({key: "ArrowDown", preventDefault: () => prevented++});
			const afterArrow = bodies.length;

			document.activeElement = {};
			open = false;
			input.click();

			emit({afterClick, afterClickOpen, afterArrow, prevented, afterBlur: bodies.length});
		');

		$this->assertEquals(1, $result['afterClick'] ?? NULL, 'un clic dans le champ qui a le curseur ouvre la liste');
		$this->assertEquals(1, $result['afterClickOpen'] ?? NULL, 'un clic dans la liste ouverte ne la relance pas');
		$this->assertEquals(2, $result['afterArrow'] ?? NULL, 'la flèche vers le bas ouvre la liste');
		$this->assertEquals(1, $result['prevented'] ?? NULL, 'la flèche ne déplace pas le curseur dans le texte');
		$this->assertEquals(2, $result['afterBlur'] ?? NULL, 'sans le curseur, le clic passe par le focusin');

	}

	/**
	 * L'écoute qui ferme la liste au clic extérieur naît avec la liste, y compris quand la saisie l'ouvre sans
	 * `focusin`.
	 */
	public function testWatchOnOpen() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.query(input);
			const watching = listeners.length;
			listeners.shift()({target: {parentNode: null}});
			emit({watching, closed: input.classList.removed});
		');

		$this->assertEquals(1, $result['watching'] ?? NULL, 'la liste ouverte écoute le clic extérieur');
		$this->assertEquals(1, $result['closed'] ?? NULL, 'qui la ferme');

	}

	/**
	 * La liste rouverte sans retoucher le texte garde la valeur choisie : Échap ou la sortie du champ retrouvent le choix,
	 * et un autre choix la remplace.
	 */
	public function testReopenKeepsChoice() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});

			document.activeElement = input;
			input.click();
			const reopened = bodies.length;
			const kept = items.value();

			field.remove(input);
			const escaped = {value: items.value(), text: input.value, open};

			input.click();
			field.apply(input, {value: "2", itemText: "Buttage"});

			emit({reopened, kept, escaped, replaced: items.value(), text: input.value});
		');

		$this->assertEquals(1, $result['reopened'] ?? NULL, 'le clic rouvre la liste');
		$this->assertEquals(['1'], $result['kept'] ?? NULL, 'la liste rouverte garde la valeur choisie');
		$this->assertEquals(['value' => ['1'], 'text' => 'Binage', 'open' => FALSE], $result['escaped'] ?? NULL, 'Échap retrouve le choix et son texte');
		$this->assertEquals(['2'], $result['replaced'] ?? NULL, 'un autre choix remplace la valeur, sans la doubler');
		$this->assertEquals('Buttage', $result['text'] ?? NULL);

	}

	/**
	 * Un texte retouché à la main n'accompagne plus la valeur choisie, qui s'efface à la recherche qu'il lance.
	 */
	public function testTypedClearsChoice() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});

			input.value = "Bina";
			input.input();
			pendingTimers.forEach(timer => timer());
			const typed = items.value();

			input.value = "Binage";
			field.query(input);

			emit({typed, retyped: items.value()});
		');

		$this->assertEquals([], $result['typed'] ?? NULL, 'le texte retouché efface la valeur');
		$this->assertEquals([], $result['retyped'] ?? NULL, 'retaper le même texte ne la ramène pas');

	}

	/**
	 * Un champ rendu avec sa valeur la garde comme un choix fait dans la liste.
	 */
	public function testPrefilledKeepsChoice() {

		$result = $this->runBrowser('
			const input = fakeInput({value: "7", text: "Binage"});
			field.query(input);
			field.remove(input);
			emit({value: items.value(), text: input.value});
		');

		$this->assertEquals(['value' => ['7'], 'text' => 'Binage'], $result);

	}

	/**
	 * La réponse qui rend le curseur au champ ignore le `focusin` de ce `focus()`, et celui-là seulement : un `focus()`
	 * qui n'en émet pas ne laisse pas la marque avaler le premier `focusin` du champ suivant.
	 */
	public function testFocusMarkDoesNotLinger() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.start(input);

			document.activeElement = {};
			field.query(input);
			pendingAnswers.forEach(answer => answer());
			const ignore = field.ignoreNextFocus;
			const sent = bodies.length;

			open = false;
			input.focusin();

			emit({focused, ignore, afterFocusin: bodies.length - sent});
		');

		$this->assertEquals(1, $result['focused'] ?? NULL, 'la réponse rend le curseur au champ');
		$this->assertFalse($result['ignore'] ?? NULL, 'la marque ne survit pas à l\'appel');
		$this->assertEquals(1, $result['afterFocusin'] ?? NULL, 'le focusin suivant ouvre la liste');

	}

	/**
	 * Une seule liste ouverte à la fois : celle du champ qu'on quitte se ferme quand la suivante s'ouvre, et la réponse
	 * qu'elle attendait encore ne la rouvre pas, ni ne remplit une autre liste, ni ne reprend le curseur.
	 */
	public function testOneListOpen() {

		$result = $this->runBrowser('
			const input = fakeInput();
			const next = fakeInput(null, "next");

			document.activeElement = input;
			field.query(input);

			document.activeElement = next;
			field.query(next);
			const both = [open, others.next.open];

			pendingAnswers.forEach(answer => answer());

			emit({both, open, html: dropdown.innerHTML, htmlNext: others.next.innerHTML, focused, active: document.activeElement === next});
		');

		$this->assertEquals([FALSE, TRUE], $result['both'] ?? NULL, 'la liste suivante ferme la précédente');
		$this->assertFalse($result['open'] ?? NULL, 'la réponse attendue par la liste fermée ne la rouvre pas');
		$this->assertEquals('', $result['html'] ?? NULL);
		$this->assertEquals('<ul class="autocomplete-list"><li data-n="0" data-dropdown-keep class="">next</li></ul>', $result['htmlNext'] ?? NULL, 'chaque liste ne montre que ses propres résultats');
		$this->assertEquals(0, $result['focused'] ?? NULL, 'le curseur reste dans le champ suivant');
		$this->assertTrue($result['active'] ?? NULL);

	}

	/**
	 * Une liste fermée par Échap ou un clic extérieur ne se rouvre pas à l'arrivée de sa réponse.
	 */
	public function testClosedListStaysClosed() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.query(input);
			field.remove(input);
			pendingAnswers.forEach(answer => answer());
			emit({open, html: dropdown.innerHTML});
		');

		$this->assertEquals(['open' => FALSE, 'html' => ''], $result, 'la réponse ne recrée pas la liste supprimée');

	}

	/**
	 * La croix vide son champ, y compris quand la liste ouverte s'intercale entre eux.
	 */
	public function testCrossWithListOpen() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});

			field.query(input);
			dropdown.hasAttribute = () => false;
			dropdown.previousElementSibling = input;
			const cross = {previousSibling: dropdown, previousElementSibling: dropdown};

			field.empty(cross);

			emit({text: input.value, value: items.value(), selected: selected.length, open});
		');

		$this->assertEquals(['text' => '', 'value' => [''], 'selected' => 2, 'open' => FALSE], $result, 'texte et valeur vidés, choix vide émis, liste fermée');

	}

	/**
	 * En plein écran, le champ est parti sous body avec sa liste : la croix ne trouve devant elle que son emplacement,
	 * et ne vide rien.
	 */
	public function testCrossWithFieldAway() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.apply(input, {value: "1", itemText: "Binage"});

			const placeholder = {hasAttribute: () => false, previousElementSibling: null};
			const cross = {previousSibling: placeholder, previousElementSibling: placeholder};

			field.empty(cross);

			emit({text: input.value, value: items.value()});
		');

		$this->assertEquals(['text' => 'Binage', 'value' => ['1']], $result);

	}

	/**
	 * Un champ retiré de la page avec sa liste ouverte n'a plus de liste à fermer, mais son écoute du clic se retire :
	 * le champ rendu de nouveau sous le même id en pose une neuve.
	 */
	public function testWatchDroppedWithField() {

		$result = $this->runBrowser('
			const input = fakeInput();
			field.query(input);

			field.getDropdown = () => null;
			field.internalRemove(input);

			emit({watched: field.clickListener[input.id] !== undefined});
		');

		$this->assertEquals(['watched' => FALSE], $result);

	}

	/**
	 * La recherche que la saisie a programmée dans un champ ne part plus quand un autre champ ouvre sa liste : elle
	 * rouvrirait la liste quittée et fermerait celle où l'on est.
	 */
	public function testTypedQueryAfterLeaving() {

		$result = $this->runBrowser('
			const input = fakeInput();
			const next = fakeInput(null, "next");

			input.value = "Bin";
			input.input();

			document.activeElement = next;
			field.query(next);

			emit({timers: pendingTimers.length, sent: bodies.length, open, openNext: others.next.open});
		');

		$this->assertEquals(['timers' => 0, 'sent' => 1, 'open' => FALSE, 'openNext' => TRUE], $result);

	}

	/**
	 * La recherche de la frappe part pendant que celle du focus attend encore sa réponse, et seule la sienne remplit la
	 * liste, même arrivée la première : la liste ne montre jamais les résultats d'un texte que le champ ne porte plus.
	 */
	public function testTypedQueryWhileWaiting() {

		$result = $this->runBrowser('
			const input = fakeInput();

			const rendered = [];
			field.source = (input, values) => rendered.push(values[0].query);

			field.query(input);

			input.value = "pneus";
			input.input();
			pendingTimers.forEach(timer => timer());

			pendingAnswers.reverse().forEach(answer => answer());

			emit({sent: bodies, rendered});
		');

		$this->assertEquals(['query=', 'query=pneus'], $result['sent'] ?? NULL, 'la recherche de la frappe part sans attendre la réponse de la précédente');
		$this->assertEquals(['pneus'], $result['rendered'] ?? NULL, 'seule sa réponse remplit la liste');

	}

	/**
	 * `form.js` tourne dans un contexte de node, avec le peu de document qu'`AutocompleteField` touche. Les réponses
	 * attendent dans `pendingAnswers` qu'on les rende, et les recherches programmées dans `pendingTimers`. `open`
	 * dit si la liste existe. Le script part par l'entrée standard, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`).
	 */
	private function runBrowser(string $scenario): array {

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/form.js').', "utf8");

			let fullscreen = false;
			let open = false;
			let blurred = 0;
			let focused = 0;
			const bodies = [];
			const listeners = [];
			const selected = [];
			const pendingAnswers = [];
			const locks = new Set();
			let pendingTimers = [];

			const dropdown = {
				innerHTML: "",
				style: {},
				qs: () => null,
				qsa: () => null,
				remove: () => { open = false; }
			};

			// Le conteneur des valeurs choisies : `innerHTML = ""` le vide, `value()` rend ce que le formulaire enverrait
			const items = {
				children: [],
				get firstElementChild() { return this.children[0] ?? null; },
				set innerHTML(html) { this.children = []; },
				appendChild(node) { this.children.push(node); },
				insertAdjacentHTML(position, html) { this.children.push({value: html.match(/data-value="([^"]*)"/)[1]}); },
				value() { return this.children.map(node => node.value); }
			};

			// Les champs suivants, chacun avec sa liste, ouverte quand `open` est vrai, et son conteneur de valeurs
			const others = {};
			const itemsOther = {};

			const document = {
				delegateEventListener: () => {},
				// Les écoutes en capture sont posées une fois au chargement de form.js (le clic sur un libellé) : seules
				// celles que la liste pose et retire se comptent
				addEventListener: (type, listener, options) => {
					if(options !== true) {
						listeners.push(listener);
					}
				},
				removeEventListener: () => {},
				body: {matches: () => fullscreen},
				activeElement: null
			};

			const context = vm.createContext({
				document,
				window: {scrollY: 0},
				URLSearchParams,
				CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
				setTimeout: (callback, delay) => {
					if(delay === 0) {
						callback();
						return null;
					}
					pendingTimers.push(callback);
					return callback;
				},
				clearTimeout: callback => { pendingTimers = pendingTimers.filter(timer => timer !== callback); },
				qs: (selector, callback) => {
					let found = null;
					if(selector === "#field-autocomplete") {
						found = open ? dropdown : null;
					} else if(selector.endsWith("-autocomplete")) {
						const other = others[selector.slice(1, -13)];
						found = (other !== undefined && other.open) ? other : null;
					} else if(selector === "#items") {
						found = items;
					} else if(selector.startsWith("#items-")) {
						found = itemsOther[selector.slice(7)];
					}
					return (callback && found !== null) ? callback(found) : found;
				},
				qsa: selector => selector.startsWith("#items > ") ? items.children.filter(node => selector.includes(\'"\'+ node.value +\'"\')) : [],
				Lime: {
					Asset: {icon: () => ""},
					getZIndex: () => 1,
					History: {popLayer: () => {}}
				},
				// Comme le vrai, le contexte reste verrouillé jusqu\'à la réponse, et une requête de même portée lève `LOCK_FAILED`
				Ajax: {
					Query: class {
						constructor(node) { this.node = node; this.scope = ""; }
						lockAs(scope) { this.scope = scope; return this; }
						url() { return this; }
						body(body) { this.query = body.get("query"); bodies.push(body.toString()); return this; }
						fetch() {
							const lock = this.node.id + this.scope;
							if(locks.has(lock)) {
								throw "LOCK_FAILED";
							}
							locks.add(lock);
							const itemHtml = (this.node.id === "field") ? "Carotte" : this.node.id;
							const query = this.query;
							return {then: callback => pendingAnswers.push(() => {
								locks.delete(lock);
								callback({results: [{value: 1, itemHtml, query}]});
							})};
						}
					}
				}
			});

			document.createElement = () => ({setAttribute(name, value) { this[name] = value; }});

			vm.runInContext(source + ";globalThis.AutocompleteField = AutocompleteField;", context);

			const field = context.AutocompleteField;

			// `getDropdown()` crée la liste quand elle manque : tout appel en ouvre une, par `move()` comme en dehors
			field.getDropdown = input => {
				if(input.id === "field") {
					open = true;
					return dropdown;
				}
				others[input.id].open = true;
				return others[input.id];
			};

			// `multiple` nomme le champ en tableau, `keepQuery` lui pose `data-autocomplete-keep-query`
			const fakeInput = (prefilled = null, id = "field", options = {}) => {
				if(id !== "field") {
					others[id] = {open: false, innerHTML: "", style: {}, qs: () => null, qsa: () => null, remove() { this.open = false; }};
					itemsOther[id] = {children: [], set innerHTML(html) { this.children = []; }, appendChild(node) { this.children.push(node); }, value() { return this.children.map(node => node.value); }};
				}
				if(prefilled !== null) {
					items.appendChild({value: prefilled.value});
				}
				const input = {
					id,
					value: prefilled?.text ?? "",
					style: {},
					dataset: {
						autocompleteUrl: "/query",
						autocompleteItems: (id === "field") ? "items" : "items-"+ id,
						autocompleteSelect: "event",
						...(options.keepQuery ? {autocompleteKeepQuery: "1"} : {})
					},
					getAttribute: name => (name === "data-autocomplete-field") ? (options.multiple ? "action[]" : "action") : null,
					hasAttribute: name => (name === "data-autocomplete-field"),
					setAttribute: () => {},
					setSelectionRange: (start, end) => { input.range = [start, end]; },
					classList: {
						open: false,
						removed: 0,
						contains: name => input.classList.open,
						add: name => { input.classList.open = true; },
						remove: name => { input.classList.open = false; input.classList.removed++; }
					},
					dispatchEvent: event => { if(event.type === "autocompleteSelect") { selected.push(event.detail); } },
					addEventListener: (type, listener) => { input[type] = listener; },
					focus: () => { focused++; document.activeElement = input; }
				};
				document.activeElement = input;
				document.activeElement.blur = () => { blurred++; document.activeElement = null; };
				field.init(input);
				return input;
			};

			const emit = value => process.stdout.write(JSON.stringify(value));

			'.$scenario.'
		';

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

		return $result ?? [];

	}

}
?>
