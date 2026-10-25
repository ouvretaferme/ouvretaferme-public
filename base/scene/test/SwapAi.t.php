<?php
namespace scene;

/**
 * Des boutons qui prennent la place les uns des autres (`static/js/swap.js`), éprouvés sous node sur une page réduite à
 * ce que `SceneSwap.swap()` lit : le focus, son anneau et la classe `hide`. Le focus tenu au clavier suit le bouton montré,
 * celui pris à la souris passe à la scène sans faire défiler la page, et un focus posé ailleurs ne bouge pas.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=scene file=SwapAi
 */
class SwapAiTest extends \Test {

	public function testKeyboardFocusFollowsTheShownButton(): void {

		$result = $this->swap('play', TRUE);

		$this->assertEquals('pause', $result['active'], "Entrée sur Lecture laisse le focus clavier sur Pause, qui prend sa place");
		$this->assertEquals(['play'], $result['hidden']);

	}

	public function testMouseFocusFallsBackToTheSceneWithoutScrolling(): void {

		$result = $this->swap('play', FALSE);

		$this->assertEquals('scene', $result['active'], "un clic sur Lecture rend le focus à la scène, qui reçoit ensuite Espace");
		$this->assertEquals(TRUE, $result['preventScroll'], "la scène reprend le focus sans faire défiler la page");

	}

	public function testFocusElsewhereStays(): void {

		$result = $this->swap('other', TRUE);

		$this->assertEquals('other', $result['active'], "un focus posé hors des boutons échangés ne bouge pas");
		$this->assertEquals(['play'], $result['hidden']);

	}

	/**
	 * Échange Lecture contre Pause, le focus posé sur `$focused`, tenu au clavier si `$visible`.
	 */
	private function swap(string $focused, bool $visible): array {

		return $this->node('
			const { readFileSync } = require("node:fs");

			const document = { activeElement: null };
			let preventScroll = null;

			const element = name => {
				const classes = new Set();
				return {
					name,
					classList: { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) },
					contains(other) { return other === this; },
					matches: selector => selector === ":focus-visible" && '.json_encode($visible).',
					focus(options = {}) {
						document.activeElement = this;
						preventScroll = options.preventScroll ?? false;
					}
				};
			};

			const [play, pause, scene, other] = ["play", "pause", "scene", "other"].map(element);
			pause.classList.add("hide");
			document.activeElement = { play, other }['.json_encode($focused).'];

			const source = readFileSync('.json_encode(LIME_DIRECTORY.'/base/scene/static/js/swap.js').', "utf8");
			const SceneSwap = new Function("document", source + "; return SceneSwap;")(document);

			SceneSwap.swap([play], [pause], scene);

			process.stdout.write(JSON.stringify({
				active: document.activeElement.name,
				hidden: [play, pause].filter(button => button.classList.contains("hide")).map(button => button.name),
				preventScroll
			}));
		');

	}

	private function node(string $script): array {

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve swap.js sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return [];
		}

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
