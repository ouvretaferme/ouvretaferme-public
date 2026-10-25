<?php
namespace planning;

/**
 * Ce qui est dû hors cultures, pour le planning et la cloche (contrat « Alertes du planning »,
 * validé le 03/10/2026). Les packages l'alimentent par un observateur
 * `<package>/lib/Planning.o.php`, dont la classe `PlanningObserverLib` répond à trois événements :
 *
 * - `getDue(Structure)` rend les éléments `Due` de la structure, sans `structure` ni `source`, que
 *   `refresh()` pose ;
 * - `getDueStructures()` rend les structures que la passe hebdomadaire recalcule ;
 * - `describeDue(Structure, Collection)` accroche aux éléments ce que leur rendu demande, et les rend
 *   dans l'ordre de l'écran.
 *
 * Les mots, l'icône et les gestes sont dans `<package>/ui/Planning.o.php` (`PlanningObserverUi`).
 *
 * L'état est stocké : une ligne par chose due, datée de son début, que seul un geste qui change ce qui
 * est dû recalcule. Le temps qui passe ne demande rien, la lecture compare la date à l'horloge.
 */
class DueLib extends DueCrud {

	const ORIGIN_BLOCK = 'planning';
	const ORIGIN_ZONE = 'planning-zone';

	/**
	 * Les packages qui alimentent le planning, lus dans le registre des observateurs engendré par le
	 * cadriciel (`ouvretaferme/package.c.php`) : c'est l'inventaire que vérifie `SourceSentinel`.
	 */
	public static function getSources(): array {
		return \Package::getObservers()['lib']['planning']['getDue'] ?? [];
	}

	/**
	 * Aligne les lignes d'une source pour une structure sur ce que la source dit dû maintenant. Une
	 * source ne ferme jamais une ligne elle-même : ce qu'elle ne rend plus disparaît ici.
	 *
	 * Seules les lignes qui changent s'écrivent, une ligne valant par son type, sa référence et sa
	 * date. Un geste du carnet en enchaîne plusieurs qui recalculent chacune (un entretien fait écrit
	 * un relevé, puis fait repartir l'échéance) : les recalculs suivants le premier ne relisent que.
	 * Le cadriciel n'a pas de crochet après commit qui permettrait de n'en jouer qu'un.
	 */
	public static function refresh(\structure\Structure $eStructure, string $source): void {

		$cDue = self::getObserver($source)::getDue($eStructure);

		$cDueExisting = Due::model()
			->select(['id', 'type', 'reference', 'dueDate'])
			->whereStructure($eStructure)
			->whereSource($source)
			->getCollection();

		$dues = [];

		foreach($cDueExisting as $eDue) {
			$dues[self::getKey($eDue)] = $eDue;
		}

		Due::model()->beginTransaction();

			foreach($cDue as $eDue) {

				$key = self::getKey($eDue);

				if(array_key_exists($key, $dues)) {
					unset($dues[$key]);
					continue;
				}

				$eDue['structure'] = $eStructure;
				$eDue['source'] = $source;

				self::create($eDue);

			}

			foreach($dues as $eDue) {
				self::delete($eDue);
			}

		Due::model()->commit();

	}

	protected static function getKey(Due $eDue): string {
		return $eDue['type'].'|'.$eDue['reference'].'|'.$eDue['dueDate'];
	}

	/**
	 * La passe hebdomadaire, filet des gestes qui n'auraient pas recalculé : chaque source sur les
	 * structures qu'elle désigne, et sur celles qui portent encore une de ses lignes, pour qu'une
	 * structure qui n'a plus rien de dû soit vidée.
	 */
	public static function refreshAll(): int {

		$refreshed = 0;

		foreach(self::getSources() as $source) {

			$cStructure = self::getObserver($source)::getDueStructures();

			$cStructureExisting = Due::model()
				->whereSource($source)
				->group('structure')
				->getColumn('structure');

			$structures = [];

			foreach([$cStructure, $cStructureExisting] as $cStructureSource) {

				foreach($cStructureSource as $eStructure) {
					$structures[$eStructure['id']] = $eStructure;
				}

			}

			foreach($structures as $eStructure) {
				self::refresh($eStructure, $source);
				$refreshed++;
			}

		}

		return $refreshed;

	}

	/**
	 * Ce qui est dû dans la semaine, par source, chaque collection décrite par sa source et rangée
	 * dans l'ordre de l'écran. Semaine en cours : tout ce qui a commencé, et ce qui commence dans la
	 * semaine. Semaine à venir : ce qui commence dans la semaine. Semaine passée : rien, ce qui y était
	 * dû l'est encore aujourd'hui et se lit sur la semaine en cours.
	 */
	public static function getForWeek(\structure\Structure $eStructure, string $week): array {

		$weekCurrent = \Clock::week();

		if(strcmp($week, $weekCurrent) < 0) {
			return [];
		}

		$dates = week_dates($week);

		Due::model()
			->select(Due::getSelection())
			->whereStructure($eStructure)
			->sort(['dueDate' => SORT_ASC, 'id' => SORT_ASC]);

		if($week === $weekCurrent) {
			Due::model()->where('dueDate IS NULL OR dueDate <= '.Due::model()->format(last($dates)));
		} else {
			Due::model()->whereDueDate('BETWEEN', new \Sql(Due::model()->format(first($dates)).' AND '.Due::model()->format(last($dates))));
		}

		$cDue = Due::model()->getCollection();

		$dues = [];

		foreach(self::getSources() as $source) {

			$cDueSource = $cDue->find(fn(Due $eDue) => $eDue['source'] === $source);

			if($cDueSource->notEmpty()) {
				$dues[$source] = self::getObserver($source)::describeDue($eStructure, $cDueSource);
			}

		}

		return $dues;

	}

	/**
	 * Le lundi où reparaît ce qu'un geste décale à la semaine suivante (retour 15 du 04/10) :
	 * celui qui suit la semaine où le planning le montre. Ce qui est en retard ou n'a pas de date se
	 * montre dans la semaine en cours, le reste dans la semaine de son échéance. La semaine affichée
	 * n'entre pas dans le calcul : postée, elle décalerait jusqu'à n'importe quelle année.
	 */
	public static function getPostponedDate(\structure\Structure $eStructure, string $type, int $reference): string {

		$dueDate = Due::model()
			->whereStructure($eStructure)
			->whereType($type)
			->whereReference($reference)
			->getValue('dueDate');

		$week = \Clock::week();

		if($dueDate !== NULL) {
			$week = max($week, toWeek($dueDate));
		}

		return week_date_starts(toWeek(strtotime($week.' + 1 WEEK')));

	}

	/**
	 * L'origine d'un geste fait depuis ce qui est dû : `ORIGIN_BLOCK` pour le bloc en tête de « À faire »
	 * de l'ancien planning, `ORIGIN_ZONE` pour la zone du nouveau. La réponse rend la forme d'où le geste
	 * est parti (`planning/due`).
	 */
	public static function isOrigin(?string $origin): bool {
		return in_array($origin, [self::ORIGIN_BLOCK, self::ORIGIN_ZONE], TRUE);
	}

	/**
	 * La classe de l'observateur d'une source, refusée si la source n'est pas au registre : une faute
	 * de frappe dans un appel à `refresh()` lève au lieu de vider les lignes d'une source inconnue.
	 */
	protected static function getObserver(string $source): string {

		if(in_array($source, self::getSources(), TRUE) === FALSE) {
			throw new \Exception('Source '.$source.' does not observe planning\getDue');
		}

		return '\\'.$source.'\\PlanningObserverLib';

	}

}
?>
