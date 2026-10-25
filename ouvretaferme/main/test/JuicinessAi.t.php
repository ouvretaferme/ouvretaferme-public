<?php
namespace main;

/**
 * Le compteur des requêtes en vol de MainJuiciness (juicinessAi.js) monte à `ajaxBeforeFetch` et descend à
 * `ajaxAfterFetch`, deux événements émis par ajax.js. Un voile ne part que lorsqu'il revient à 0 : une
 * réponse dont le rendu lève sans émettre `ajaxAfterFetch` le laisserait à 1, et plus aucun voile ne
 * partirait de la page jusqu'au prochain chargement complet.
 *
 * ajax.js et juicinessAi.js tournent dans node, sur un document factice qui ne porte que ce que leur
 * chargement et une requête lisent : c'est leur code qui s'exécute, et non une relecture du source. Le
 * réseau est remplacé par une réponse fixe, qui remplace un nœud (`qs(…)->outerHtml(…)`). Son rendu lève
 * comme un onrender qui casse : `renderOuter()` remonte l'exception de `parseRender()`, qui n'a pas de
 * `try`. Une instruction de fenêtre (`js()`) ne convient pas : ajax.js la rend dans sa propre promesse,
 * dont l'échec n'atteint pas la requête.
 *
 * Lancement : dev/test package=main file=JuicinessAi
 */
class JuicinessAiTest extends \Test {

	public function testInstructionThatThrows(): void {

		foreach(['Query', 'Navigation'] as $request) {

			$result = $this->request($request, TRUE);

			$this->assertEquals(['ajaxBeforeFetch', 'ajaxAfterFetch'], $result['events'], $request.' : la réponse qui lève referme la requête qu\'elle a ouverte');
			$this->assertEquals(TRUE, $result['failed'], $request.' : la requête dont le rendu lève est comptée en échec');
			$this->assertEquals(0, $result['inFlight'], $request.' : le compteur revient à 0 après une instruction qui lève');
			$this->assertEquals('rendu cassé', $result['error'], $request.' : l\'erreur du rendu remonte, elle n\'est pas avalée');

		}

	}

	public function testInstructionThatSucceeds(): void {

		foreach(['Query', 'Navigation'] as $request) {

			$result = $this->request($request, FALSE);

			$this->assertEquals(['ajaxBeforeFetch', 'ajaxAfterFetch'], $result['events'], $request);
			$this->assertEquals(FALSE, $result['failed'], $request);
			$this->assertEquals(0, $result['inFlight'], $request);
			$this->assertEquals('', $result['error'], $request.' : une réponse rendue sans erreur n\'en fait remonter aucune');

		}

	}

	/**
	 * Trois cartes voisines retirées par une même réponse, puis une instruction qui vise l'une d'elles par
	 * son identifiant, comme une vue qui retire puis remplace. Chaque carte vit dans sa propre bande
	 * positionnée, comme une ligne de la frise d'une série, et la page factice reflue à chaque retrait : une
	 * bande vidée se replie et les suivantes remontent. Deux défauts poseraient les copies trop haut : une
	 * carte mesurée après le retrait d'une voisine (l'annonce avant toute instruction l'évite), et une copie
	 * placée d'après la position de sa bande au moment de l'annonce (la bande se mesure au moment d'écrire).
	 */
	public function testVanish(): void {

		$result = $this->vanish(FALSE);

		$this->assertEquals('', $result['error'], 'le rendu ne lève pas');
		$this->assertEquals(['card-4'], $result['remaining'], 'les trois cartes quittent la liste dans leur instruction');
		$this->assertEquals(0, $result['found'], 'aucune copie ne répond à l\'identifiant d\'une carte retirée');
		$this->assertEquals([100, 140, 180], $result['tops'], 'chaque copie est posée à la place qu\'avait sa carte dans la fenêtre, alors que sa bande est remontée');
		$this->assertEquals([[], [], []], $result['identifiers'], 'une copie ne porte ni id, ni name, ni for, ni data-ajax, elle ni ses enfants');
		$this->assertEquals([TRUE, TRUE, TRUE], $result['inert'], 'une copie est inerte');
		$this->assertEquals('table', $result['tableCopies'], 'une ligne de tableau part sans copie');
		$this->assertEquals([], $result['afterEnd'], 'une copie se retire à la fin de son animation');
		$this->assertEquals(0, $result['inFlight']);

		$this->assertEquals(['copie item-1', 'copie item-2', 'item-3', 'copie item-4', 'copie item-5'], $result['itemsOrder'], 'chaque copie est posée à la place de son nœud dans son parent, et deux voisins partis ensemble gardent leur ordre, qu\'ils soient retirés dans l\'ordre de la page ou à rebours');
		$this->assertEquals(['line-3', 'copie line-1'], $result['linesOrder'], 'une copie dont le voisin est parti sans copie va en dernier dans son parent');
		$this->assertEquals(['', '', 'copie solo-1'], $result['wrapOrder'], 'une copie dont le parent est parti dans la même réponse va dans le bloc conteneur');
		$this->assertEquals(['item-1' => 600, 'item-2' => 640, 'item-4' => 720, 'item-5' => 760, 'line-1' => 800, 'solo-1' => 920], $result['placeTops'], 'la copie posée dans son parent garde les coordonnées du nœud, mesurées sur le bloc conteneur');

	}

	/**
	 * Dans un onglet caché, requestAnimationFrame attend le retour sur l'onglet : une copie posée alors
	 * paraitrait d'un coup, sur des mesures périmées. Les cartes partent sèches.
	 */
	public function testVanishInHiddenTab(): void {

		$result = $this->vanish(TRUE);

		$this->assertEquals('', $result['error'], 'le rendu ne lève pas');
		$this->assertEquals(['card-4'], $result['remaining'], 'les trois cartes quittent la liste');
		$this->assertEquals([], $result['tops'], 'aucune copie n\'est posée dans un onglet caché');

	}

	/**
	 * La porte des packages : un script qui a changé un nœud sans requête l'éclaire tout de suite, même
	 * pendant qu'une autre requête est en vol, et le voile se retire à sa propre fin d'animation, pas à
	 * celle d'un enfant.
	 */
	public function testFlash(): void {

		$script = '
			const vm = require("vm");
			const fs = require("fs");

			const makeNode = () => {
				const node = {classes: new Set(), listeners: []};
				node.classList = {
					add: name => node.classes.add(name),
					remove: name => node.classes.delete(name),
					contains: name => node.classes.has(name)
				};
				node.addEventListener = (type, listener) => node.listeners.push(listener);
				node.removeEventListener = (type, listener) => node.listeners = node.listeners.filter(item => item !== listener);
				node.end = (target, animationName) => node.listeners.slice().forEach(listener => listener({target, animationName}));
				return node;
			};

			const context = vm.createContext({document: {addEventListener: () => {}}});
			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/ouvretaferme/main/static/js/juicinessAi.js').', "utf8") + ";globalThis.MainJuiciness = MainJuiciness;", context);

			const row = makeNode();
			const child = makeNode();

			context.MainJuiciness.inFlight = 1;
			context.MainJuiciness.flash(row);
			const during = Array.from(row.classes);

			context.MainJuiciness.flash(row);
			const listenersAfterSecondCall = row.listeners.length;

			row.end(child, "util-celebrate-spill");
			const afterChild = Array.from(row.classes);

			row.end(row, "util-flash");

			process.stdout.write(JSON.stringify({
				during,
				listenersAfterSecondCall,
				afterChild,
				afterEnd: Array.from(row.classes),
				listeners: row.listeners.length
			}));
		';

		$result = $this->runNode($script, ['during', 'listenersAfterSecondCall', 'afterChild', 'afterEnd', 'listeners']);

		$this->assertEquals(['util-flash'], $result['during'], 'le voile se pose tout de suite, même avec une requête en vol');
		$this->assertEquals(1, $result['listenersAfterSecondCall'], 'un second appel pendant le voile ne le relance pas');
		$this->assertEquals(['util-flash'], $result['afterChild'], 'la fin d\'animation d\'un enfant ne retire pas le voile');
		$this->assertEquals([], $result['afterEnd'], 'le voile se retire à sa propre fin d\'animation');
		$this->assertEquals(0, $result['listeners'], 'l\'écouteur part avec le voile');

	}

	private function vanish(bool $hidden): array {

		$response = json_encode(['__instructions' => [
			['qs', '#card-1', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#card-2', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#card-3', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#row-1', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#card-2', [['outerHtml', ['<div id="card-2"></div>']]]],
			['qs', '#item-2', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#item-1', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#item-4', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#item-5', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#line-1', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#line-2', [['remove', [[]]]]],
			['qs', '#solo-1', [['remove', [['vanish' => TRUE]]]]],
			['qs', '#band-solo', [['remove', [[]]]]],
		]]);

		$script = '
			const vm = require("vm");
			const fs = require("fs");

			class CustomEvent extends Event {
				constructor(name, options = {}) {
					super(name, options);
					this.detail = options.detail;
					this.bubbles_ = options.bubbles === true;
				}
			}

			class FakeElement {

				constructor(tag, attributes = {}, children = []) {
					this.tag = tag;
					this.attributeMap = new Map(Object.entries(attributes));
					this.children = [];
					this.parentElement = null;
					this.style = {};
					this.listeners = {};
					this.position = "static";
					this.classes = new Set();
					this.classList = {
						add: (...names) => names.forEach(name => this.classes.add(name)),
						remove: (...names) => names.forEach(name => this.classes.delete(name)),
						contains: name => this.classes.has(name)
					};
					this.clientTop = 0;
					this.clientLeft = 0;
					this.scrollTop = 0;
					this.scrollLeft = 0;
					children.forEach(child => this.append(child));
				}

				get id() { return this.attributeMap.get("id") ?? ""; }
				get attributes() { return Array.from(this.attributeMap.keys()).map(name => ({name})); }
				getAttribute(name) { return this.attributeMap.get(name) ?? null; }
				setAttribute(name, value) { this.attributeMap.set(name, value); }
				removeAttribute(name) { this.attributeMap.delete(name); }

				get parentNode() { return this.parentElement; }

				get nextSibling() {
					if(this.parentElement === null) {
						return null;
					}
					const siblings = this.parentElement.children;
					return siblings[siblings.indexOf(this) + 1] ?? null;
				}

				insertBefore(child, reference) {
					child.parentElement = this;
					this.children.splice(this.children.indexOf(reference), 0, child);
				}

				get isConnected() {
					let node = this;
					while(node.parentElement !== null) { node = node.parentElement; }
					return node === document;
				}

				append(child) {
					child.parentElement = this;
					this.children.push(child);
				}

				remove() {
					if(this.parentElement !== null) {
						this.parentElement.children = this.parentElement.children.filter(child => child !== this);
						this.parentElement = null;
					}
				}

				descendants() {
					return this.children.flatMap(child => [child, ...child.descendants()]);
				}

				querySelectorAll(selector) {
					return (selector === "*") ? this.descendants() : [];
				}

				matches(selector) {
					return selector.split(",").map(item => item.trim()).includes(this.tag);
				}

				cloneNode() {
					const copy = new FakeElement(this.tag, Object.fromEntries(this.attributeMap), this.children.map(child => child.cloneNode()));
					this.classes.forEach(name => copy.classes.add(name));
					return copy;
				}

				// Une mise en page en colonne : une carte mesure 40, une enveloppe la somme de ce qu elle porte dans le
				// flux, une copie en position absolue rien. Une enveloppe vidée se replie et celles d en dessous
				// remontent, comme les bandes de la frise d une série.
				flowHeight() {
					if(this.style.position === "absolute") {
						return 0;
					}
					return ["section", "ul"].includes(this.tag) ? this.children.reduce((sum, child) => sum + child.flowHeight(), 0) : 40;
				}

				getBoundingClientRect() {
					if(this.box) {
						return this.box;
					}
					const parent = this.parentElement.getBoundingClientRect();
					const siblings = this.parentElement.children;
					const above = siblings.slice(0, siblings.indexOf(this)).reduce((sum, sibling) => sum + sibling.flowHeight(), 0);
					return {top: parent.top + above, left: parent.left, width: 300, height: this.flowHeight()};
				}

				renderOuter(html) {
					const replacement = new FakeElement("div", {id: html.match(/id="([^"]+)"/)[1]});
					const index = this.parentElement.children.indexOf(this);
					replacement.parentElement = this.parentElement;
					this.parentElement.children[index] = replacement;
					this.parentElement = null;
					return replacement;
				}

				addEventListener(type, listener) {
					(this.listeners[type] ??= []).push(listener);
				}

				removeEventListener(type, listener) {
					this.listeners[type] = (this.listeners[type] ?? []).filter(item => item !== listener);
				}

				dispatchEvent(event) {
					Object.defineProperty(event, "target", {value: this, configurable: true});
					let node = this;
					do {
						(node.listeners[event.type] ?? []).slice().forEach(listener => listener(event));
						node = node.parentElement;
					} while(node !== null && event.bubbles_);
					return true;
				}

			}

			const document = new FakeElement("#document");
			document.delegateEventListener = () => {};
			document.hidden = '.($hidden ? 'true' : 'false').';
			document.body = new FakeElement("body");
			document.append(document.body);

			const cards = [1, 2, 3, 4].map(rank => new FakeElement("div", {id: "card-"+ rank, "data-ajax": "/carte"}, [
				new FakeElement("input", {name: "done", id: "done-"+ rank}),
				new FakeElement("label", {for: "done-"+ rank})
			]));
			const bands = cards.map(card => new FakeElement("section", {}, [card]));
			bands.forEach(band => band.position = "relative");
			const list = new FakeElement("div", {}, bands);
			list.box = {top: 100, left: 20, width: 300, height: 160};
			document.body.append(list);
			document.body.box = {top: 0, left: 0, width: 1000, height: 1000};

			// Une liste statique dans une enveloppe positionnée : le parent d une carte n est pas son bloc conteneur.
			const item = (prefix, rank) => new FakeElement("div", {id: prefix +"-"+ rank, "data-card": prefix +"-"+ rank});
			const items = new FakeElement("ul", {}, [1, 2, 3, 4, 5].map(rank => item("item", rank)));
			const lines = new FakeElement("ul", {}, [1, 2, 3].map(rank => item("line", rank)));
			const bandSolo = new FakeElement("section", {id: "band-solo"}, [item("solo", 1)]);
			const wrap = new FakeElement("div", {}, [items, lines, bandSolo]);
			wrap.position = "relative";
			wrap.box = {top: 600, left: 20, width: 300, height: 400};
			document.body.append(wrap);

			const table = new FakeElement("table", {}, [new FakeElement("tr", {id: "row-1"})]);
			table.box = {top: 400, left: 0, width: 300, height: 40};
			document.body.append(table);

			const frames = [];

			const context = vm.createContext({
				window: {scrollY: 0, scrollX: 0, addEventListener() {}},
				document,
				CustomEvent,
				Event,
				Element: FakeElement,
				HTMLFormElement: class {submit() {}},
				Headers,
				URLSearchParams,
				Promise,
				setTimeout,
				clearTimeout,
				console: {log: () => {}},
				Lime: {},
				requestAnimationFrame: callback => frames.push(callback),
				getComputedStyle: node => ({
					position: node.position,
					transform: "none",
					filter: "none",
					contain: "none",
					gridTemplateColumns: "none",
					animationName: node.classList.contains("util-vanish") ? "util-vanish" : "none"
				}),
				qs: (selector, callback) => {
					const node = document.descendants().find(item => "#"+ item.id === selector) ?? null;
					return (node !== null && callback) ? callback(node) : node;
				},
				fetch: () => Promise.resolve({ok: true, headers: new Headers(), text: () => Promise.resolve('.json_encode($response).')})
			});

			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/framework/util/static/js/ajax.js').', "utf8") + ";globalThis.Ajax = Ajax;", context);
			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/ouvretaferme/main/static/js/juicinessAi.js').', "utf8") + ";globalThis.MainJuiciness = MainJuiciness;", context);

			let error = "";

			process.on("unhandledRejection", reason => {
				error = reason instanceof Error ? reason.message : String(reason);
			});

			new context.Ajax.Query().method("get").url("/juiciness").fetch().catch(() => {});

			setTimeout(() => {

				frames.splice(0).forEach(callback => callback());

				const copies = bands.flatMap(band => band.children.filter(child => child.classList.contains("util-vanish")));
				const remaining = bands.flatMap(band => band.children.filter(child => child.classList.contains("util-vanish") === false).map(child => child.id));
				const identifiers = copies.map(copy => [copy, ...copy.descendants()].flatMap(node => node.attributes.map(attribute => attribute.name)).filter(name => ["id", "name", "for"].includes(name) || name.startsWith("data-ajax")));

				const name = node => node.classList.contains("util-vanish") ? "copie "+ node.getAttribute("data-card") : node.id;
				const wrapCopies = [items, lines, wrap].flatMap(parent => parent.children).filter(node => node.classList.contains("util-vanish"));

				const result = {
					itemsOrder: items.children.map(name),
					linesOrder: lines.children.map(name),
					wrapOrder: wrap.children.map(name),
					placeTops: wrapCopies.map(copy => ({[copy.getAttribute("data-card")]: 600 + parseFloat(copy.style.top)})).reduce((all, one) => Object.assign(all, one), {}),
					error,
					remaining,
					found: document.descendants().filter(node => node.id === "card-2").length,
					tops: copies.map(copy => copy.parentElement.getBoundingClientRect().top + parseFloat(copy.style.top)),
					identifiers,
					inert: copies.map(copy => copy.getAttribute("inert") !== null),
					tableCopies: table.children.length === 0 && document.body.children.every(child => child.classList.contains("util-vanish") === false) ? "table" : "copie",
					inFlight: context.MainJuiciness.inFlight
				};

				copies.forEach(copy => {
					const event = new CustomEvent("animationend");
					event.animationName = "util-vanish";
					copy.dispatchEvent(event);
				});

				result.afterEnd = bands.flatMap(band => band.children).filter(child => child.classList.contains("util-vanish")).map(child => child.tag);

				process.stdout.write(JSON.stringify(result));

			}, 100);
		';

		return $this->runNode($script, ['itemsOrder', 'linesOrder', 'wrapOrder', 'placeTops', 'error', 'remaining', 'found', 'tops', 'identifiers', 'inert', 'tableCopies', 'afterEnd', 'inFlight']);

	}

	private function request(string $request, bool $throws): array {

		$response = json_encode(['__instructions' => [['qs', '#juiciness', [['outerHtml', ['<div id="juiciness"></div>']]]]]]);

		$script = '
			const vm = require("vm");
			const fs = require("fs");

			class CustomEvent extends Event {
				constructor(name, options = {}) {
					super(name, options);
					this.detail = options.detail;
				}
			}

			const document = new EventTarget();
			document.delegateEventListener = () => {};
			document.querySelector = () => null;
			document.body = {};

			const context = vm.createContext({
				window: {scrollY: 0, location: {origin: "http://juiciness.test"}, addEventListener() {}},
				document,
				CustomEvent,
				Event,
				EventTarget,
				Element: class {},
				HTMLFormElement: class {submit() {}},
				Headers,
				URLSearchParams,
				Promise,
				setTimeout,
				clearTimeout,
				console,
				Lime: {
					Alert: {hideStaticErrors: () => {}},
					History: {push: () => {}}
				},
				qs: (selector, callback) => (selector !== "#juiciness") ? null : callback({
					renderOuter: () => {
						if('.($throws ? 'true' : 'false').') {
							throw new Error("rendu cassé");
						}
						return {};
					}
				}),
				fetch: () => Promise.resolve({ok: true, headers: new Headers(), text: () => Promise.resolve('.json_encode($response).')})
			});

			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/framework/util/static/js/ajax.js').', "utf8") + ";globalThis.Ajax = Ajax;", context);
			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/ouvretaferme/main/static/js/juicinessAi.js').', "utf8") + ";globalThis.MainJuiciness = MainJuiciness;", context);

			const events = [];
			let failed = null;

			document.addEventListener("ajaxBeforeFetch", () => events.push("ajaxBeforeFetch"));
			document.addEventListener("ajaxAfterFetch", event => {
				events.push("ajaxAfterFetch");
				failed = event.detail.failed;
			});

			let error = "";

			process.on("unhandledRejection", reason => {
				error = reason instanceof Error ? reason.message : String(reason);
			});

			new context.Ajax['.json_encode($request).']().method("get").url("/juiciness").fetch().catch(() => {});

			setTimeout(() => {
				process.stdout.write(JSON.stringify({events, failed, inFlight: context.MainJuiciness.inFlight, error}));
			}, 100);
		';

		return $this->runNode($script, ['events', 'failed', 'inFlight', 'error']);

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`).
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

		return $result ?? array_fill_keys($keys, NULL) + ['error' => $errors];

	}

}
?>
