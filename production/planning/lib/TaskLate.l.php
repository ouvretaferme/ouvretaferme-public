<?php
namespace planning;

/**
 * Les deux zones sous les blocs de la Semaine (L8, piste 2 de la planche arbitrée) : « En
 * retard », dépliée, puis « Non planifié », repliée et chargée à sa première ouverture.
 *
 * La règle est celle de l'ancien planning, appelée et non réécrite : `\series\TaskLib::applyDelayed()`
 * pour le retard (les interventions à faire prévues avant la semaine affichée, dans la profondeur
 * `planningDelayedMax` de la structure, aucune pour une semaine à venir) et `getByUnplanned()` pour les
 * interventions sans semaine. Les séries clôturées en sont écartées comme dans l'ancien planning
 * (`applyOpenSeries()`).
 *
 * Une intervention à faire qui porte du temps ou une récolte dans la semaine affichée est déjà dans la
 * Semaine, comme commencée (`\series\TaskLib::getForWeekDone()`) : les deux zones l'écartent, sans quoi
 * sa carte serait deux fois à l'écran sous le même identifiant.
 *
 * Une zone se lit en deux temps. Ses clés d'abord (`getKeys()`), l'identifiant et l'action, sans rien
 * hydrater : elles suffisent aux comptes et à la décision de ce qu'un geste change. Les cartes
 * ensuite (`getTasks()`), seulement celles que l'écran montre ou que la réponse redessine : 2 413
 * interventions en retard à la 525 coûtaient 740 ms et 3,7 Mo rendues en entier, leurs clés 35 ms (mesuré
 * le 01/10, plan de L8).
 *
 * Au-delà de `$threshold` interventions dans la zone, chaque bloc ne montre que ses `LIMIT` plus
 * récentes et finit par « Voir les N autres » (Q1 du plan, tranchée par V-Planning le 01/10). L'ordre est
 * lu en SQL et ne dépend que de l'intervention, pour qu'une carte remplacée sur place soit à la même
 * place qu'au rechargement : la semaine prévue puis la création pour le retard, la création pour le
 * non planifié, les plus récentes d'abord.
 */
class TaskLateLib {

	const LATE = 'late';
	const UNPLANNED = 'unplanned';

	const LIMIT = 20;

	/**
	 * Le nombre d'interventions d'une zone au-delà duquel ses blocs se plafonnent : environ 500 Ko et 300 ms
	 * ajoutés à la page, le seuil fixé au plan de L8. Une propriété et non une constante : le test du
	 * plafond la baisse, plutôt que de semer trois cents interventions.
	 */
	public static int $threshold = 300;

	/**
	 * Les clés d'une zone dans l'ordre de l'écran, indexées par identifiant. `$eTask` borne la lecture à une
	 * seule intervention, pour savoir dans quelle zone elle se trouve (page d'une intervention).
	 */
	public static function getKeys(\structure\Structure $eStructure, string $zone, string $week, \series\Task $eTask = new \series\Task()): \Collection {

		$cTaskTimesheet = \series\TimesheetLib::getTasksByWeek($eStructure, $week);
		$cTaskHarvest = \series\HarvestLib::getTasksByWeek($eStructure, $week);

		$mTask = \series\Task::model();

		switch($zone) {

			case self::LATE :

				if(\series\TaskLib::applyDelayed($mTask, $eStructure, $week) === FALSE) {
					return new \Collection();
				}

				$mTask->sort([
					'plannedWeek' => SORT_DESC,
					'createdAt' => SORT_DESC,
					'id' => SORT_DESC
				]);

				break;

			case self::UNPLANNED :

				$mTask
					->wherePlannedWeek(NULL)
					->whereStatus(\series\Task::TODO)
					->sort([
						'createdAt' => SORT_DESC,
						'id' => SORT_DESC
					]);

				break;

			// Sans condition de zone, la lecture rendrait toutes les interventions de la structure.
			default :
				throw new \Exception('Unknown zone '.$zone);

		}

		\series\TaskLib::applyOpenSeries();

		return $mTask
			->select([
				'id',
				'action' => ['name', 'color'],
				'plannedUsers',
				'time',
				'harvest'
			])
			->whereStructure($eStructure)
			->whereId($eTask, if: $eTask->notEmpty())
			->whereId('NOT IN', $cTaskTimesheet, if: $cTaskTimesheet->notEmpty())
			->whereId('NOT IN', $cTaskHarvest, if: $cTaskHarvest->notEmpty())
			->getCollection(index: 'id');

	}

	/**
	 * Ce que montre une zone, sans rien hydrater :
	 * - `count` : les interventions de la zone ;
	 * - `capped` : si ses blocs se plafonnent ;
	 * - `loaded` : si l'écran la montre, toujours pour le retard, après sa première ouverture pour le non
	 *   planifié, que le client réémet ;
	 * - `blocks` : un bloc par action, dans l'ordre des noms comme la Semaine, avec toutes ses
	 *   interventions (`ids`), celles qui se montrent (`shown`), leur compte et le rang du bloc, que
	 *   `MainPiles.stack()` relit pour un bloc rendu seul (`rank`) ;
	 * - `started` : si une de ses interventions est commencée (`\series\Task::isStarted()`), donc n'a pas de geste
	 *   sur ses jours en grille, ce que dit la phrase sous la grille (`TaskWeekLib::hasLocked()`) ;
	 * - `cTask` : les cartes hydratées, vide tant que `fill()` n'est pas passé.
	 *
	 * `$eUserAssigned` ne garde que les interventions prévues pour cette personne, « Uniquement assigné à moi »
	 * de la fiche de temps (`TimeDayLib::isAssigned()`). Une intervention de zone n'a pas de temps dans la
	 * semaine (`getKeys()`) : la personne prévue est la seule chose qui la lui donne.
	 */
	public static function getZone(\structure\Structure $eStructure, string $zone, string $week, bool $loaded, \user\User $eUserAssigned = new \user\User()): array {

		$cTaskKey = self::getKeys($eStructure, $zone, $week);

		if($eUserAssigned->notEmpty()) {
			$cTaskKey = $cTaskKey->find(fn($eTaskKey) => in_array($eUserAssigned['id'], $eTaskKey['plannedUsers']));
		}

		$count = $cTaskKey->count();
		$capped = ($count > self::$threshold);
		$started = $cTaskKey->find(fn($eTaskKey) => $eTaskKey->isStarted())->notEmpty();

		$blocks = [];

		foreach($cTaskKey as $eTaskKey) {

			$action = $eTaskKey['action']['id'];

			$blocks[$action] ??= [
				'eAction' => $eTaskKey['action'],
				'ids' => [],
			];

			$blocks[$action]['ids'][] = $eTaskKey['id'];

		}

		$collator = \L::getCollator();

		uasort($blocks, function(array $block1, array $block2) use($collator): int {

			$compare = $collator->compare($block1['eAction']['name'], $block2['eAction']['name']);

			// Deux actions de même nom restent deux blocs : l'identifiant les sépare, comme dans la Semaine.
			return ($compare !== 0) ? $compare : ($block1['eAction']['id'] <=> $block2['eAction']['id']);

		});

		$rank = 0;

		foreach($blocks as $action => $block) {
			$blocks[$action]['count'] = count($block['ids']);
			$blocks[$action]['shown'] = $capped ? array_slice($block['ids'], 0, self::LIMIT) : $block['ids'];
			$blocks[$action]['rank'] = $rank++;
		}

		return [
			'zone' => $zone,
			'count' => $count,
			'capped' => $capped,
			'loaded' => ($zone === self::LATE or $loaded),
			'blocks' => $blocks,
			'started' => $started,
			'cTask' => new \Collection(),
		];

	}

	/**
	 * Hydrate les cartes que l'écran montre, toutes ou celles des seules actions données, et les ajoute à
	 * celles déjà hydratées. Une zone que l'écran ne montre pas n'hydrate rien.
	 */
	public static function fill(\structure\Structure $eStructure, string $week, array &$zone, ?array $actions = NULL): void {

		if($zone['loaded'] === FALSE) {
			return;
		}

		$ids = [];

		foreach($zone['blocks'] as $action => $block) {
			if($actions === NULL or in_array($action, $actions)) {
				$ids = array_merge($ids, $block['shown']);
			}
		}

		$zone['cTask']->mergeCollection(self::getTasks($eStructure, $zone['zone'], $week, $ids), reindex: FALSE);

	}

	/**
	 * Les cartes d'un bloc entier, au-delà du plafond, que « Voir les N autres » charge.
	 */
	public static function fillBlock(\structure\Structure $eStructure, string $week, array &$zone, int $action): void {

		$zone['blocks'][$action]['shown'] = $zone['blocks'][$action]['ids'];

		self::fill($eStructure, $week, $zone, [$action]);

	}

	/**
	 * Les cartes de la zone par la lecture de l'ancien planning, bornée aux identifiants donnés : elle hydrate
	 * comme la Semaine (lieux, commentaires, culture, temps de la semaine, semis par variété), et ses conditions
	 * restent celles de la zone. Indexées par identifiant, avec ce que la carte attend (`TaskCardUi::get()`).
	 */
	public static function getTasks(\structure\Structure $eStructure, string $zone, string $week, array $ids): \Collection {

		$cTask = new \Collection();

		// Les identifiants viennent des clés de la zone : sans eux, la condition posée sur le modèle partagé
		// survivrait à la lecture que `getForWeekDelayed()` ne fait pas pour une semaine à venir.
		if($ids === []) {
			return $cTask;
		}

		\series\Task::model()->whereId('IN', $ids);

		$ccTask = match($zone) {
			self::LATE => \series\TaskLib::getForWeekDelayed($eStructure, $week),
			self::UNPLANNED => \series\TaskLib::getByUnplanned($eStructure, $week),
		};

		foreach($ccTask as $cTaskAction) {

			foreach($cTaskAction as $eTask) {
				$eTask['timePeriod'] = array_sum($eTask['times']);
				$eTask['harvestWeekTotal'] = TaskWeekLib::getHarvestWeekTotal($eTask);
				// La zone qui range la carte, que sa marque de semaine lit (`TaskCardUi::hasOtherWeek()`).
				$eTask['zone'] = $zone;
				$eTask['keptCopy'] = FALSE;
				TaskWeekLib::fillSeedling($eTask);
				$cTask[$eTask['id']] = $eTask;
			}

		}

		TaskSearchLib::fill($eStructure, $cTask);

		return $cTask;

	}

	/**
	 * La carte d'une intervention qu'un geste a touchée dans une zone et qui n'est pas dans la semaine
	 * affichée : revenue dans sa zone, ou cochée, donc faite dans sa semaine prévue
	 * (`\series\TaskLib::updateDoneCollection()`). La fiche de temps la garde à sa place jusqu'au
	 * rechargement (R-Place), quelle que soit la zone qui la porterait ensuite. Elle n'a ni temps ni récolte
	 * dans la semaine, sans quoi elle y serait (`\series\TaskLib::getForWeekDone()`). Vide pour une série
	 * close, que les zones écartent.
	 */
	public static function getTaskKept(\structure\Structure $eStructure, \series\Task $eTask): \series\Task {

		\series\TaskLib::applyOpenSeries();

		$cTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->select(\series\TaskLib::getPlanningSelection())
			->whereStructure($eStructure)
			->whereId($eTask)
			->getCollection(index: 'id');

		foreach($cTask as $eTaskKept) {
			$eTaskKept['times'] = [];
			$eTaskKept['timePeriod'] = 0.0;
			$eTaskKept['harvestPeriod'] = NULL;
			$eTaskKept['harvestWeekTotal'] = NULL;
			$eTaskKept['keptCopy'] = FALSE;
		}

		TaskSearchLib::fill($eStructure, $cTask);

		return $cTask->empty() ? new \series\Task() : $cTask->first();

	}

	/**
	 * Les identifiants de toutes les interventions de la zone, montrées ou non.
	 */
	public static function getIds(array $zone): array {

		$ids = [];

		foreach($zone['blocks'] as $block) {
			$ids = array_merge($ids, $block['ids']);
		}

		return $ids;

	}

	/**
	 * Ce qu'un geste change dans une zone, lu entre ses clés d'avant et d'après le geste :
	 * - `zones` : la zone apparaît ou disparaît, les deux zones se rendent entières ;
	 * - `board` : un bloc touché apparaît, ou la zone franchit le seuil du plafond, ses blocs se rendent
	 *   entiers, les blocs se répartissant en piles côté navigateur ;
	 * - sinon, par bloc touché : le bloc qui disparaît sort seul (`emptied`), sans que les autres se
	 *   répartissent de nouveau entre les piles, comme dans la semaine (`TaskWeekLib::getChange()`) ; ses
	 *   cartes remplacées sur place (`card`) quand le bloc montre les mêmes interventions, retirées
	 *   (`remove`) quand il montre les mêmes moins celles qui le quittent, le bloc rendu entier (`block`) dans
	 *   tous les autres cas, une carte qui arrive n'ayant pas de nœud à remplacer. Les comptes des blocs
	 *   touchés qui restent suivent (`counter`).
	 *
	 * Une zone que l'écran ne montre pas ne change que par ses comptes.
	 *
	 * @return string|array `zones`, `board`, ou `['card' => ids, 'remove' => ids, 'block' => actions, 'emptied' => actions, 'counter' => actions]`.
	 */
	public static function getChange(array $before, array $after, array $ids): string|array {

		if(($before['count'] > 0) !== ($after['count'] > 0)) {
			return 'zones';
		}

		$change = [
			'card' => [],
			'remove' => [],
			'block' => [],
			'emptied' => [],
			'counter' => [],
		];

		if($after['loaded'] === FALSE) {
			return $change;
		}

		if($before['capped'] !== $after['capped']) {
			return 'board';
		}

		$actions = [];

		foreach([$before, $after] as $zone) {
			foreach($zone['blocks'] as $action => $block) {
				if(array_intersect($ids, $block['ids']) !== []) {
					$actions[$action] = TRUE;
				}
			}
		}

		foreach(array_keys($actions) as $action) {

			if(array_key_exists($action, $after['blocks']) === FALSE) {
				$change['emptied'][] = $action;
				continue;
			}

			if(array_key_exists($action, $before['blocks']) === FALSE) {
				return 'board';
			}

			$shownBefore = $before['blocks'][$action]['shown'];
			$shownAfter = $after['blocks'][$action]['shown'];

			$change['counter'][] = $action;

			// Une carte au-delà du plafond n'est à l'écran que par « Voir les N autres », que le serveur ne
			// connaît pas : son bloc se redessine, plafonné comme au rechargement.
			$hidden = array_diff(array_intersect($ids, $before['blocks'][$action]['ids']), $shownBefore);

			if($hidden !== []) {
				$change['block'][] = $action;
			} else if($shownBefore === $shownAfter) {
				$change['card'] = array_merge($change['card'], array_values(array_intersect($ids, $shownAfter)));
			} else if(array_values(array_diff($shownBefore, $ids)) === $shownAfter) {
				$change['remove'] = array_merge($change['remove'], array_values(array_intersect($ids, $shownBefore)));
			} else {
				$change['block'][] = $action;
			}

		}

		return $change;

	}

	/**
	 * Les actions dont le bloc se redessine, pour n'hydrater que leurs cartes, et les cartes remplacées.
	 */
	public static function fillChange(\structure\Structure $eStructure, string $week, array &$zone, string|array $change): void {

		if($change === 'zones' or $change === 'board') {
			self::fill($eStructure, $week, $zone);
			return;
		}

		if($change['block'] !== []) {
			self::fill($eStructure, $week, $zone, $change['block']);
		}

		if($change['card'] !== []) {
			$zone['cTask']->mergeCollection(self::getTasks($eStructure, $zone['zone'], $week, $change['card']), reindex: FALSE);
		}

	}

	/**
	 * La zone où se trouve une intervention dans la semaine donnée, `NULL` hors des deux zones.
	 */
	public static function getZoneOf(\structure\Structure $eStructure, string $week, \series\Task $eTask): ?string {

		foreach([self::LATE, self::UNPLANNED] as $zone) {
			if(self::getKeys($eStructure, $zone, $week, $eTask)->notEmpty()) {
				return $zone;
			}
		}

		return NULL;

	}

}
?>
