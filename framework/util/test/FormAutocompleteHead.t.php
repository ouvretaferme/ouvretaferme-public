<?php
namespace util;

/**
 * La rangée de boutons en tête de la liste d'une autocomplétion (`$d->autocompleteHead`) : son attribut côté PHP,
 * son rendu et le corps de la recherche côté `AutocompleteField`, et les autocomplétions sans rangée, inchangées.
 */
class FormAutocompleteHeadTest extends \Test {

	private const HEAD = [
		'name' => 'season',
		'label' => 'Saison',
		'values' => [2025 => '2025', 2026 => '2026', 2027 => '2027'],
		'selected' => 2026
	];

	public function testAttributes() {

		$html = $this->getField(FALSE, self::HEAD);

		$this->assertTrue(str_contains($html, ' data-autocomplete-head="'.encode('{"name":"season","label":"Saison","values":[[2025,"2025"],[2026,"2026"],[2027,"2027"]]}').'"'), 'le JSON de la rangée, valeurs en paires pour garder leur ordre');
		$this->assertTrue(str_contains($html, ' data-autocomplete-head-selected="2026"'), 'la valeur choisie d\'office');

	}

	public function testAttributesClosure() {

		// La closure s'appelle liée au FormUi (`Closure::call()`), où `self` n'est plus ce test
		$head = self::HEAD;

		$html = $this->getField(FALSE, fn(FormUi $form, \plant\Plant $ePlant) => ['selected' => 2027] + $head);

		$this->assertTrue(str_contains($html, ' data-autocomplete-head-selected="2027"'), 'la rangée se calcule d\'après l\'élément');

	}

	/**
	 * Le libellé et les noms des valeurs se concatènent dans la liste comme un `itemHtml` : ils partent encodés.
	 */
	public function testEncoded() {

		$attributes = new FormUi()->getAutocompleteHead(['label' => 'Saison <b>', 'values' => ['a' => '<i>A</i>'], 'selected' => 'a'] + self::HEAD);

		$this->assertEquals(['name' => 'season', 'label' => 'Saison &lt;b&gt;', 'values' => [['a', '&lt;i&gt;A&lt;/i&gt;']]], json_decode($attributes['data-autocomplete-head'], TRUE));

	}

	public function testSelectedOutside() {

		$this->assertRefused(['selected' => 2030] + self::HEAD, 'une valeur choisie hors de la liste');

	}

	public function testIncomplete() {

		$head = self::HEAD;
		unset($head['name']);

		$this->assertRefused($head, 'une rangée sans nom de paramètre');
		$this->assertRefused(['values' => []] + self::HEAD, 'une rangée sans valeur');

	}

	/**
	 * Sans rangée, le champ ne porte aucun attribut de rangée, qu'il soit à un ou à plusieurs choix.
	 */
	public function testWithoutHead() {

		$single = $this->getField(FALSE, NULL);
		$multiple = $this->getField(TRUE, NULL);

		$this->assertFalse(str_contains($single, 'data-autocomplete-head'), 'un choix unique sans rangée');
		$this->assertTrue(str_contains($single, ' data-autocomplete-field="name"'), 'le champ posté d\'un choix unique');
		$this->assertFalse(str_contains($multiple, 'data-autocomplete-head'), 'plusieurs choix sans rangée');
		$this->assertTrue(str_contains($multiple, ' data-autocomplete-field="name[]"'), 'le champ posté de plusieurs choix');

	}

	/**
	 * La rangée vient en tête, la valeur choisie se distingue des autres, et revient à chaque réponse : `source()`
	 * refait toute la liste.
	 */
	public function testSource() {

		$result = $this->runBrowser('
			const input = fakeInput(true);
			field.query(input);
			const first = dropdown.innerHTML;
			answer = [{value: 2, itemHtml: "Chou"}];
			field.query(input);
			emit({first, second: dropdown.innerHTML});
		');

		$this->assertHeadFirst($result['first'] ?? '', 'la rangée en tête de la première réponse');
		$this->assertSelected($result['first'] ?? '', 1, 'la valeur choisie d\'office');
		$this->assertHeadFirst($result['second'] ?? '', 'la rangée revient avec la seconde réponse');
		$this->assertSelected($result['second'] ?? '', 1, 'et sa valeur choisie');

	}

	/**
	 * Une réponse qui place la rangée parmi ses résultats (`type: 'head'`) la reçoit à cet endroit, sous l'intitulé
	 * de la section qu'elle règle, et plus en tête.
	 */
	public function testSourcePlaced() {

		$result = $this->runBrowser('
			answer = [{type: "title", itemHtml: "Séries"}, {type: "head"}, {value: 2, itemHtml: "Chou"}];
			const input = fakeInput(true);
			field.query(input);
			emit({html: dropdown.innerHTML});
		');

		$html = $result['html'] ?? '';
		$head = strpos($html, 'autocomplete-head');

		$this->assertTrue($head !== FALSE and strpos($html, 'Séries') < $head and $head < strpos($html, '<li data-n="2"'), 'la rangée entre l\'intitulé et le résultat : '.$html);

		$result = $this->runBrowser('
			answer = [{type: "title", itemHtml: "Séries"}, {type: "head"}, {value: 2, itemHtml: "Chou"}];
			const input = fakeInput(false);
			field.query(input);
			emit({html: dropdown.innerHTML});
		');

		$this->assertFalse(str_contains($result['html'] ?? '', 'autocomplete-head'), 'une place de rangée sur un champ sans rangée ne rend pas de rangée');
		$this->assertTrue(str_contains($result['html'] ?? '', '<li data-n="2"'), 'une place de rangée sur un champ sans rangée n\'est pas ignorée');

	}

	/**
	 * Sans résultat et sans saisie, la liste d'avant reste en place ; avec la rangée, elle se refait avec la rangée
	 * seule, pour qu'on puisse encore changer de valeur.
	 */
	public function testSourceEmpty() {

		$result = $this->runBrowser('
			answer = [];
			const input = fakeInput(true);
			input.value = "";
			field.query(input);
			const withHead = dropdown.innerHTML;
			dropdown.innerHTML = "avant";
			const plain = fakeInput(false);
			plain.value = "";
			field.query(plain);
			emit({withHead, plain: dropdown.innerHTML});
		');

		$this->assertTrue(str_contains($result['withHead'] ?? '', 'autocomplete-head') and str_contains($result['withHead'] ?? '', 'data-n=') === FALSE, 'la rangée seule');
		$this->assertEquals('avant', $result['plain'] ?? NULL, 'sans rangée, la liste n\'est pas touchée');

	}

	/**
	 * La valeur choisie part dans le corps sous le nom de la rangée ; un autre bouton la change et relance la
	 * recherche avec le texte tapé.
	 */
	public function testQuery() {

		$result = $this->runBrowser('
			const input = fakeInput(true);
			field.query(input);
			field.selectHead(input, 2);
			emit({bodies, selected: input.dataset.autocompleteHeadSelected, html: dropdown.innerHTML});
		');

		$this->assertEquals(['week=2026-36&season=2026&query=chou', 'week=2026-36&season=2027&query=chou'], $result['bodies'] ?? NULL);
		$this->assertEquals('2027', $result['selected'] ?? NULL);
		$this->assertSelected($result['html'] ?? '', 2, 'le bouton choisi se distingue des autres');

	}

	/**
	 * Sans rangée, le corps et la liste sont ceux d'avant l'option.
	 */
	public function testWithoutHeadBrowser() {

		$result = $this->runBrowser('
			const input = fakeInput(false);
			field.query(input);
			emit({bodies, html: dropdown.innerHTML});
		');

		$this->assertEquals(['week=2026-36&query=chou'], $result['bodies'] ?? NULL);
		$this->assertFalse(str_contains($result['html'] ?? '', 'autocomplete-head'), 'aucune rangée dans la liste');
		$this->assertTrue(str_contains($result['html'] ?? '', '<li data-n="0"'), 'le résultat est choisissable');

	}

	/**
	 * Un clic dans la rangée s'arrête à elle et n'atteint pas l'écouteur du document qui ferme la liste, un bouton
	 * relance la recherche, et la rangée garde le focus dans le champ. Sans rangée, un clic hors du champ ferme
	 * la liste comme avant l'option.
	 */
	public function testClick() {

		$result = $this->runBrowser('
			const input = fakeInput(true);
			field.query(input);
			let stopped = 0;
			let prevented = 0;
			head.listeners.click({stopPropagation: () => stopped++});
			head.listeners.mousedown({preventDefault: () => prevented++});
			head.buttons[0].listeners.click({});

			const plain = fakeInput(false);
			field.start(plain);
			plain.focusin();
			listeners.shift()({target: {parentNode: null}});

			emit({stopped, prevented, season: bodies[bodies.length - 2], removed, watching: listeners.length});
		');

		$this->assertEquals(1, $result['stopped'] ?? NULL, 'un clic dans la rangée remonte jusqu\'au document');
		$this->assertEquals(1, $result['prevented'] ?? NULL, 'la rangée prend le focus au champ');
		$this->assertEquals('week=2026-36&season=2025&query=chou', $result['season'] ?? NULL, 'le premier bouton relance la recherche sur 2025');
		$this->assertEquals(1, $result['removed'] ?? NULL, 'sans rangée, un clic hors du champ ferme');
		$this->assertEquals(0, $result['watching'] ?? NULL);

	}

	private function getField(bool $multiple, array|\Closure|null $head): string {

		return new FormUi()->dynamicField(new \plant\Plant(), 'name', function(\PropertyDescriber $d) use ($multiple, $head) {
			$d->field = 'autocomplete';
			$d->autocompleteUrl = '/plant/plant:query';
			$d->autocompleteBody = ['structure' => 7];
			$d->autocompleteResults = fn($e) => ['value' => 1, 'itemText' => 'Carotte', 'itemHtml' => 'Carotte'];
			$d->autocompleteHead = $head;
			$d->multiple = $multiple;
			$d->attributes = ['id' => 'plant-name'];
		});

	}

	/**
	 * La liste que lit `AutocompleteField` (`ul.autocomplete-list`) s'ouvre sur la rangée, avant le premier résultat
	 * choisissable, et ses boutons portent les rangs des valeurs.
	 */
	private function assertHeadFirst(string $html, string $case): void {

		$head = strpos($html, 'autocomplete-head');

		$this->assertTrue(str_starts_with($html, '<ul class="autocomplete-list">'), $case.', dans la liste : '.$html);
		$this->assertTrue($head !== FALSE and $head < strpos($html, '<li data-n='), $case.' : '.$html);
		$this->assertEquals([0, 1, 2], array_keys($this->getHeadButtons($html)), $case.', un bouton par valeur');

	}

	/**
	 * Le bouton de la valeur choisie, repéré par son rang, porte une classe que n'a aucun autre.
	 */
	private function assertSelected(string $html, int $n, string $case): void {

		$buttons = $this->getHeadButtons($html);
		$selected = $buttons[$n] ?? NULL;

		unset($buttons[$n]);

		$this->assertTrue($selected !== NULL and $buttons !== [] and in_array($selected, $buttons, TRUE) === FALSE, $case.' : '.$html);

	}

	/**
	 * @return array<int, string> La classe de chaque bouton de la rangée, par son rang
	 */
	private function getHeadButtons(string $html): array {

		preg_match_all('#<button [^>]*class="([^"]*)"[^>]*data-autocomplete-head-n="(\d+)"#', $html, $matches, PREG_SET_ORDER);

		return array_column($matches, 1, 2);

	}

	private function assertRefused(array $head, string $case): void {

		try {
			new FormUi()->getAutocompleteHead($head);
			$this->assertTrue(FALSE, $case.' passe');
		} catch(\Exception) {
			$this->assertTrue(TRUE);
		}

	}

	/**
	 * `form.js` tourne dans un contexte de node, avec le peu de document qu'`AutocompleteField` touche : la requête
	 * répond tout de suite par `answer`, la liste est une chaîne. Le script part par l'entrée standard, la commande ne
	 * se compose que de littéraux (`ShellCommandSentinel`).
	 */
	private function runBrowser(string $scenario): array {

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/form.js').', "utf8");

			let answer = [{value: 1, itemHtml: "Carotte"}];
			let removed = 0;
			const bodies = [];
			const listeners = [];
			const node = () => {
				const element = {listeners: {}, dataset: {}, addEventListener: (type, listener) => { element.listeners[type] = listener; }};
				return element;
			};
			const head = node();
			head.buttons = [0, 1, 2].map(n => { const button = node(); button.dataset.autocompleteHeadN = String(n); return button; });
			head.qsa = (selector, callback) => (selector === "button") ? head.buttons.forEach(callback) : null;
			const dropdown = {innerHTML: "", qs: (selector, callback) => (selector === "li.autocomplete-head") ? callback(head) : null, qsa: () => null, remove: () => {}};

			const document = {
				delegateEventListener: () => {},
				addEventListener: (type, listener) => listeners.push(listener),
				removeEventListener: () => {},
				body: {matches: () => false},
				activeElement: null
			};

			const context = vm.createContext({
				document,
				URLSearchParams,
				CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
				setTimeout: callback => callback(),
				qs: (selector, callback) => (selector === "#field-autocomplete") ? dropdown : null,
				Ajax: {
					Query: class {
						url() { return this; }
						body(body) { bodies.push(body.toString()); return this; }
						fetch() { return {then: callback => callback({results: answer})}; }
					}
				}
			});

			vm.runInContext(source + ";globalThis.AutocompleteField = AutocompleteField;", context);

			const field = context.AutocompleteField;
			field.internalRemove = (...args) => { removed++; };

			const fakeInput = head => {
				const input = {
					id: "field",
					value: "chou",
					dataset: {
						autocompleteUrl: "/query",
						autocompleteBody: JSON.stringify({week: "2026-36"}),
						autocompleteItems: "items"
					},
					getAttribute: name => (name === "data-autocomplete-field") ? "cultivation[]" : null,
					hasAttribute: () => true,
					setSelectionRange: () => {},
					classList: {contains: () => true, add: () => {}, remove: () => {}},
					dispatchEvent: () => {},
					addEventListener: (type, listener) => { input[type] = listener; }
				};
				if(head) {
					input.dataset.autocompleteHead = JSON.stringify({name: "season", label: "Saison", values: [[2025, "2025"], [2026, "2026"], [2027, "2027"]]});
					input.dataset.autocompleteHeadSelected = "2026";
				}
				document.activeElement = input;
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
