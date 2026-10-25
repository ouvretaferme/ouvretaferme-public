<?php
namespace entry;

/**
 * Ce qu'une écriture ou une annulation faite depuis le champ de l'accueil change sur l'accueil : les
 * pièces que l'intention déclare (`IntentionLib::getDashboardBlocks()`), relues après elle. Une pièce est un
 * bloc ou une ligne de la file de ce qui attend un geste.
 *
 * Ce que l'écran montrait se lit sur la page et voyage avec la requête (`EntryField.setContext()`) : c'est
 * l'écran qu'on redessine, et le relire en base avant l'écriture paierait chaque pièce deux fois. Un nom posté
 * qui n'est pas parmi ceux de l'intention ne fait rien lire, et une pièce relue passe par le filtre du moteur
 * (`DashboardLib::loadBlock()`, `DashboardLib::loadContributions()`), qui la refuse à qui ne la voit pas.
 *
 * Un bloc lourd se relit ici aussi : son emplacement le ferait relire aussitôt par sa route, et le cache
 * qu'on vient d'effacer ne lui épargnerait rien.
 */
class HomeLib {

	/**
	 * `replace` porte les blocs à redessiner sur place et `remove` ceux qui n'ont plus rien à dire. La file
	 * se redessine entière (`queue`), l'ordre de ses lignes suivant leur échéance.
	 *
	 * `columns` porte les colonnes entières, rendues seules quand un bloc paraît, ou quand la file paraît ou
	 * se vide : leur place dépend de l'ordre que le membre a réglé, que l'écran ne connaît pas.
	 *
	 * @param string[] $shown Les pièces que l'écran montre
	 * @return array{replace: array, remove: string[], queue: ?array, columns: ?array}
	 */
	public static function getChange(\structure\Structure $eStructure, string $intention, array $shown): array {

		$change = [
			'replace' => [],
			'remove' => [],
			'queue' => NULL,
			'columns' => NULL
		];

		$blocks = \dashboard\DashboardLib::getBlocks();
		$contributionsQueue = array_filter(\dashboard\DashboardLib::getContributions(), fn(array $declaration) => $declaration['slot'] === \dashboard\DashboardSetting::QUEUE);

		$declared = EntryLib::getIntentions()[$intention]::getDashboardBlocks();
		$declaredBlocks = array_values(array_intersect($declared, array_keys($blocks)));
		$declaredQueue = array_values(array_intersect($declared, array_keys($contributionsQueue)));

		// La donnée gardée au cache est celle d'avant l'écriture, et c'est celle du membre qui écrit que
		// l'écran montre. Les autres membres la voient jusqu'à l'expiration de leur clé, comme après toute
		// écriture faite ailleurs que sur l'accueil. Toutes s'effacent avant la première lecture : les
		// colonnes rendues entières relisent aussi celles des pièces qui viennent après.
		$eUser = \user\ConnectionLib::getOnline();

		$declarations = array_intersect_key($blocks + $contributionsQueue, array_flip($declared));

		foreach($declarations as $name => $declaration) {
			if($declaration['cache'] > 0) {
				\Cache::redis()->delete(\dashboard\DashboardLib::getCacheKey($name, $eStructure, $eUser));
			}
		}

		$columns = FALSE;

		foreach($declaredBlocks as $block) {

			$entry = \dashboard\DashboardLib::loadBlock($eStructure, $block);

			if(in_array($block, $shown, TRUE)) {

				if($entry === NULL) {
					$change['remove'][] = $block;
				} else {
					$change['replace'][$block] = $entry;
				}

			} else if($entry !== NULL) {
				$columns = TRUE;
			}

		}

		// La file se montre dès qu'une de ses lignes se montre, déclarée ou non par l'intention.
		if($declaredQueue !== []) {

			$queue = \dashboard\DashboardLib::loadContributions($eStructure, slots: [\dashboard\DashboardSetting::QUEUE])[\dashboard\DashboardSetting::QUEUE] ?? [];
			$queueShown = (array_intersect(array_keys($contributionsQueue), $shown) !== []);

			if($queueShown !== ($queue !== [])) {
				$columns = TRUE;
			} else if($queue !== []) {
				$change['queue'] = $queue;
			}

		}

		if($columns) {

			$change = [
				'replace' => [],
				'remove' => [],
				'queue' => NULL,
				'columns' => [
					'blocks' => \dashboard\DashboardLib::load($eStructure),
					'contributions' => \dashboard\DashboardLib::loadContributions($eStructure, slots: [\dashboard\DashboardSetting::QUEUE, \dashboard\DashboardSetting::BILAN])
				]
			];

		}

		return $change;

	}

}
?>
