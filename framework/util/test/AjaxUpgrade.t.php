<?php
namespace util;

/**
 * Une réponse qui annonce une version des fichiers plus récente que celle de l'onglet (`Ajax.Query.loadAssets()`) fait
 * recharger la page, et n'est pas rendue. Rendue, une navigation inscrivait son adresse dans l'historique juste avant le
 * rechargement à la même adresse : le panneau ouvert en page entière avait alors derrière lui sa propre adresse, et sa
 * croix, qui revient en arrière, rechargeait le même panneau (signalé le 23/10/2026 sur une intervention).
 */
class AjaxUpgradeTest extends \Test {

	public function testNavigationNotRenderedOnUpgrade() {

		$result = $this->runBrowser('
			const query = answer(context.Ajax.Navigation, {__assets: {version: "nouvelle"}});
			await flush();
			emit({upgrades: query.upgrades, rendered: query.rendered, resolved});
		');

		$this->assertEquals(['upgrades' => 1, 'rendered' => 0, 'resolved' => 0], $result, 'la page se recharge, la navigation n\'est ni rendue ni inscrite dans l\'historique');

	}

	public function testQueryNotRenderedOnUpgrade() {

		$result = $this->runBrowser('
			const query = answer(context.Ajax.Query, {__assets: {version: "nouvelle"}});
			await flush();
			emit({upgrades: query.upgrades, rendered: query.rendered, resolved});
		');

		$this->assertEquals(['upgrades' => 1, 'rendered' => 0, 'resolved' => 0], $result, 'la page se recharge, ses instructions ne sont pas jouées');

	}

	/**
	 * Le témoin : la même réponse, sans fichiers annoncés, est rendue comme avant.
	 */
	public function testRenderedWithoutAssets() {

		$result = $this->runBrowser('
			const query = answer(context.Ajax.Navigation, {});
			await flush();
			emit({upgrades: query.upgrades, rendered: query.rendered, resolved});
		');

		$this->assertEquals(['upgrades' => 0, 'rendered' => 1, 'resolved' => 1], $result);

	}

	/**
	 * Charge `ajax.js` sous node et joue le scénario. `answer(classe, json)` fait recevoir `json` à une requête GET de
	 * cette classe, l'onglet portant la version `ancienne` ; la requête compte ses rechargements (`upgrades`) et ses
	 * rendus (`rendered`), et `resolved` compte les requêtes que la réponse a terminées. Le script part par l'entrée
	 * standard, la commande ne se compose que de littéraux (`ShellCommandSentinel`).
	 */
	private function runBrowser(string $scenario): array {

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/ajax.js').', "utf8");

			let resolved = 0;

			const context = vm.createContext({
				window: {addEventListener: () => {}},
				document: {delegateEventListener: () => {}, addEventListener: () => {}, dispatchEvent: () => {}},
				CustomEvent: class {},
				HTMLFormElement: class {}
			});

			vm.runInContext(source + ";globalThis.Ajax = Ajax;", context);

			context.Ajax.Asset.version = "ancienne";

			const answer = (Type, json) => {
				const query = Object.create(Type.prototype);
				query.fetchMethod = "GET";
				query.fetchUrl = "/tache/1";
				query.context = null;
				query.upgrades = 0;
				query.rendered = 0;
				query.upgrade = () => { query.upgrades++; };
				query.renderOk = () => { query.rendered++; return Promise.resolve(false); };
				query.done = () => {};
				query.dispatch = () => {};
				query.ok(JSON.stringify(json), () => { resolved++; }, () => {});
				return query;
			};

			const flush = () => new Promise(resolve => setTimeout(resolve, 10));

			const emit = value => process.stdout.write(JSON.stringify(value));

			(async () => {
				'.$scenario.'
			})();
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
