<?php
namespace main;

/**
 * La navigation ajax ne remplace pas l'en-tête d'une structure, elle y déplace la sélection
 * (`updateHeader`, `main/static/js/instruction.js`) : son menu, ses univers et son groupe Bureau
 * gardent ce qu'ils ont lu jusqu'au rechargement. Un lien qui mène à une autre structure (L52) doit au
 * contraire tout réécrire, sinon l'écran d'une structure montre le menu, les adresses et la météo
 * d'une autre, et une structure sans accueil garderait le groupe Bureau de la précédente.
 *
 * La structure que l'en-tête porte est sur le rail des univers (`StructureTemplate::getStructureSections()`),
 * que `buildAjaxHeader()` compare à celle de l'écran d'arrivée en la passant à l'instruction.
 *
 * instruction.js tourne dans node, sur un document factice qui ne porte que l'en-tête : c'est son code
 * qui s'exécute, et non une relecture du source.
 *
 * Lancement : dev/test package=main file=UpdateHeaderAi
 */
class UpdateHeaderAiTest extends \Test {

	public function testAnotherStructureRewritesTheHeader(): void {

		$result = $this->updateHeader(77);

		$this->assertEquals(['<sections 77/><nav 77/>'], $result['rewrites'], 'l\'en-tête d\'une autre structure se réécrit en entier, avec les univers et le menu de l\'arrivée');
		$this->assertEquals([], $result['selected'], 'aucune sélection ne se pose sur un menu qui va partir');

	}

	public function testTheSameStructureKeepsItsHeader(): void {

		$result = $this->updateHeader(7);

		$this->assertEquals([], $result['rewrites'], 'le menu de la même structure reste, et le groupe Bureau avec lui');
		$this->assertEquals(['[data-nav="selling"]'], $result['selected'], 'seule l\'entrée de l\'écran se sélectionne');

	}

	/**
	 * Un en-tête posé par la structure 7, puis l'instruction d'un écran de `$structure`.
	 */
	private function updateHeader(int $structure): array {

		$script = '
			const vm = require("vm");
			const fs = require("fs");

			const rewrites = [];
			const selected = [];

			const nav = {
				qsa: () => {},
				qs: (selector, callback) => {
					if(selector.startsWith("[data-nav=")) {
						selected.push(selector);
					}
				}
			};

			const methods = {};

			const context = vm.createContext({
				Lime: {
					Instruction: class {
						register(name, callback) {
							methods[name] = callback;
							return this;
						}
					}
				},
				qs: (selector, callback) => {

					let node = null;

					if(selector === "#structure-nav-sections[data-structure=\"7\"]") {
						node = {};
					} else if(selector === "#structure-nav") {
						node = nav;
					} else if(selector === "header") {
						node = {renderInner: html => rewrites.push(html)};
					}

					if(node !== null && callback !== undefined) {
						callback(node);
					}

					return node;

				}
			});

			vm.runInContext(fs.readFileSync('.json_encode(LIME_DIRECTORY.'/ouvretaferme/main/static/js/instruction.js').', "utf8"), context);

			methods.updateHeader("selling", null, null, "<sections '.$structure.'/>", "<nav '.$structure.'/>", "", '.$structure.');

			process.stdout.write(JSON.stringify({rewrites, selected}));
		';

		return $this->runNode($script, ['rewrites', 'selected']);

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

		// Sans résultat, l'échec est déjà dit : les assertions du test échouent ensuite sur des valeurs
		// nulles au lieu de lever sur une clé absente.
		return $result ?? array_fill_keys($keys, NULL);

	}

}
?>
