<?php
namespace block;

/**
 * Une ligne du plan amène le bloc sous les yeux et l'ouvre. Un parent (étapes, accordéon, onglets,
 * chiffres clés) n'a pas de formulaire à ouvrir : la ligne le choisit, comme le clic sur le bloc.
 * L'ouvrir masquait son rendu avant de chercher un `<template>` qu'il n'a pas, et le bloc disparaissait
 * de l'éditeur, deux « + » empilés à sa place, jusqu'au rechargement (défaut du 01/10/2026).
 *
 * Le script de l'éditeur tourne dans node, sur des nœuds factices qui ne portent que ce que
 * `reach()` et ce qu'il appelle lisent : c'est son code qui s'exécute, et non une relecture du source.
 *
 * Lancement : dev/test package=block file=BlockReach
 */
class BlockReachTest extends \Test {

	public function testCompositeIsSelected(): void {

		$result = $this->reach(TRUE);

		$this->assertEquals('', $result['error'], 'la ligne du plan d\'un parent ne lève pas');
		$this->assertEquals(FALSE, $result['hidden'], 'le rendu du parent reste affiché');
		$this->assertEquals(TRUE, $result['selected'], 'le parent est choisi, sa barre et le « + » de ses enfants montrés');
		$this->assertEquals(0, $result['opened'], 'aucun formulaire ouvert pour un parent');

	}

	public function testBlockIsOpened(): void {

		$result = $this->reach(FALSE);

		$this->assertEquals('', $result['error']);
		$this->assertEquals(1, $result['opened'], 'un bloc qui a un formulaire s\'ouvre à l\'écriture');

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`).
	 */
	private function reach(bool $composite): array {

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/block.js').', "utf8");
			const target = {addEventListener() {}};
			const context = vm.createContext({window: target, document: target, qs: () => null, qsa: () => {}});
			vm.runInContext(source + ";globalThis.BlockEditor = BlockEditor;", context);

			const classes = new Set();
			const read = {hidden: false, qs: () => ({getBoundingClientRect: () => ({top: 0})}), classList: {add: (...names) => names.forEach(name => classes.add(name)), remove: () => {}}};
			const block = {
				dataset: '.($composite ? '{blockPlan: "1", blockComposite: ""}' : '{blockPlan: "1"}').',
				parentElement: {closest: () => null},
				scrollIntoView() {},
				qs(selector) {
					switch(selector) {
						case ":scope > .block-item-selected": return classes.has("block-item-selected") ? read : null;
						case ":scope > .block-item": return read;
						default: return null;
					}
				}
			};

			context.qs = selector => (selector === "#block-1") ? block : null;

			const editor = context.BlockEditor;
			const open = editor.open;
			let opened = 0;

			editor.open = (...args) => {
				opened++;
				if(args[0].dataset.blockComposite !== undefined) {
					return open.apply(editor, args);
				}
			};

			let error = "";

			try {
				editor.reach({dataset: {blockPlan: "1"}});
			} catch(e) {
				error = String(e);
			}

			process.stdout.write(JSON.stringify({error, hidden: read.hidden, selected: classes.has("block-item-selected"), opened}));
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

		return $result ?? ['error' => $errors, 'hidden' => NULL, 'selected' => NULL, 'opened' => NULL];

	}

}
?>
