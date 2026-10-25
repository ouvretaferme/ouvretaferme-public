<?php
namespace series;

/**
 * Ce que la production apporte à l'accueil d'une structure : le bloc des interventions de la semaine,
 * et rien au poste du haut.
 *
 * Le bloc ne compte que les actions que le membre suit, et non toute la semaine : la carte du planning
 * qu'il remplace redisait le planning en plus petit, ses quatre nombres, ses sept jours et sa liste
 * cochable (L15, planche A, arbitrée le 2026-10-08). Les récoltes ont leur propre cellule,
 * dans la paire de `planning`, d'où l'action de récolte écartée d'ici.
 */
class DashboardLib {

	/**
	 * Les espèces nommées sous une action : au-delà, la ligne dit combien elle en tait. Une action de sol
	 * peut toucher toute la structure dans la semaine, et la sous-ligne ne doit pas devenir une liste.
	 */
	const int PLANTS_MAX = 4;

	/**
	 * `light` : trois requêtes sur la base commune, les actions de la structure et les interventions de la
	 * semaine sur les index `(structure, status, plannedWeek)` et `(structure, status, doneWeek)`. Pas de
	 * cache, la liste changeant à chaque intervention cochée, au planning comme ici.
	 */
	public static function getBlocks(): array {

		return [
			'series-week' => [
				'section' => 'production',
				'column' => \dashboard\DashboardSetting::TODO,
				'shape' => \dashboard\DashboardSetting::WIDE,
				'feature' => 'production',
				'right' => 'canPlanningLimited',
				'accept' => ['acceptSeasonPlanned'],
				'position' => 5,
				'choose' => TRUE
			]
		];

	}

	public static function getContributions(): array {
		return [];
	}

	/** Aucune pièce ne se centre sur quoi que ce soit : `$focus` est dans la signature commune, sans lecteur. */
	public static function load(string $name, \structure\Structure $eStructure, ?string $focus = NULL): ?array {

		return match($name) {
			'series-week' => self::getWeek($eStructure)
		};

	}

	public static function getUrl(string $name, \structure\Structure $eStructure, ?array $data): string {

		return match($name) {
			'series-week' => \structure\StructureUi::urlPlanning($eStructure)
		};

	}

	/**
	 * Ce que le « Choisir » du bloc règle : toutes les actions que le bloc peut suivre, celles que le
	 * membre suit d'abord et dans son ordre, les autres ensuite par nom. `NULL` pour qui n'a pas de ligne
	 * de membre où l'écrire, un membre de l'équipe en visite par exemple.
	 */
	public static function getChoice(string $name, \structure\Structure $eStructure): ?array {

		$eMember = $eStructure->getMember();

		if($eMember->empty()) {
			return NULL;
		}

		$cAction = self::getActions($eStructure);
		$followed = self::getFollowed($eStructure, $cAction);

		$actions = [];

		foreach(array_merge($followed, array_diff($cAction->getIds(), $followed)) as $action) {
			$actions[] = [
				'id' => $action,
				'name' => $cAction[$action]['name'],
				'color' => $cAction[$action]['color'],
				'followed' => in_array($action, $followed, TRUE)
			];
		}

		return [
			'member' => $eMember['id'],
			'actions' => $actions
		];

	}

	/**
	 * Les actions que le bloc peut suivre : celles de la structure, actives, sauf la récolte, que la
	 * cellule des récoltes porte avec ses quantités. Publique parce que l'écriture du choix vérifie contre
	 * la même liste (`page/dashboard.p.php`).
	 */
	public static function getActions(\structure\Structure $eStructure): \Collection {

		return \work\ActionLib::getByStructure($eStructure, index: 'id')
			->find(fn(\work\Action $eAction) => $eAction['fqn'] !== ACTION_RECOLTE);

	}

	/**
	 * Les actions suivies, dans l'ordre du membre. `NULL` en base vaut les favorites de la structure,
	 * pour qu'un membre qui n'a rien choisi voie un bloc rempli dès la première fois. Un identifiant qui
	 * n'est plus dans la liste, une action désactivée depuis, est ignoré à la lecture : le choix se
	 * réécrit au prochain geste, et rien ne sert de le réparer d'ici.
	 *
	 * @return int[]
	 */
	private static function getFollowed(\structure\Structure $eStructure, \Collection $cAction): array {

		$chosen = $eStructure->getView('viewDashboardActions');

		if($chosen === NULL) {
			return $cAction->find(fn(\work\Action $eAction) => $eAction['favorite'])->getIds();
		}

		return array_values(array_filter($chosen, fn(int $action) => $cAction->offsetExists($action)));

	}

	/**
	 * Chaque action suivie avec ce qui en est fait et ce qui en reste à faire cette semaine, les espèces
	 * qu'elle touche et ses interventions, que la ligne déplie pour les cocher, puis ce qui reste à faire
	 * sur toutes, le chiffre du bloc. Une action sans rien cette semaine reste dans la liste, à zéro :
	 * c'est le membre qui l'a choisie, et la voir disparaître le lundi lui ferait croire que son choix est
	 * perdu.
	 *
	 * Les interventions se rangent par leur nom et jamais par leur état : cochée, une intervention garde sa
	 * place, et la réponse du geste la redessine là où elle est (`getWeekPlace()`). Le nom est celui de la
	 * carte du planning, la série, à défaut l'espèce, à défaut la première ligne de la note.
	 *
	 * Le bloc n'est jamais `NULL` : sans action suivie, il garde son « Choisir », seul moyen d'en suivre.
	 */
	private static function getWeek(\structure\Structure $eStructure): array {

		$cAction = self::getActions($eStructure);
		$followed = self::getFollowed($eStructure, $cAction);

		$actions = [];
		$plants = [];

		foreach($followed as $action) {

			$plants[$action] = [];

			$actions[$action] = [
				'id' => $action,
				'name' => $cAction[$action]['name'],
				'color' => $cAction[$action]['color'],
				'done' => 0,
				'todo' => 0,
				'late' => 0,
				'plants' => [],
				'plantsHidden' => 0,
				'tasks' => []
			];

		}

		if($followed !== []) {

			$week = \Clock::week();

			// Les deux moitiés sont celles de l'écran de la semaine du planning (`TaskLib::getForWeekTodo()`
			// et `getForWeekDone()`) : une intervention à faire se range à sa semaine prévue, une faite à
			// sa semaine faite, qu'elle ait été prévue ou non. Deux requêtes, chacune sur son index
			// `(structure, status, plannedWeek)` ou `(structure, status, doneWeek)`.
			$select = [
				'id', 'action', 'status', 'description',
				'plant' => ['name'],
				'series' => ['name', 'mode']
			];

			// `applyOpenSeries()` pose sa condition sur le singleton `Task::model()` : elle précède la
			// construction de la requête, comme dans `TaskLib::getForPlanning()`.
			TaskLib::applyOpenSeries();

			$cTask = Task::model()
				->select($select)
				->whereStructure($eStructure)
				->whereStatus(Task::TODO)
				->wherePlannedWeek($week)
				->whereAction('IN', $followed)
				->getCollection();

			$cTask->mergeCollection(Task::model()
				->select($select)
				->whereStructure($eStructure)
				->whereStatus(Task::DONE)
				->whereDoneWeek($week)
				->whereAction('IN', $followed)
				->getCollection());

			foreach($cTask as $eTask) {

				$action = $eTask['action']['id'];
				$done = ($eTask['status'] === Task::DONE);

				if($done) {
					$actions[$action]['done']++;
				} else {
					$actions[$action]['todo']++;
				}

				if($eTask['plant']->notEmpty()) {
					$plants[$action][] = $eTask['plant']['name'];
				}

				$actions[$action]['tasks'][] = [
					'id' => $eTask['id'],
					'status' => $eTask['status'],
					'series' => $eTask['series']->empty() ? NULL : [
						'id' => $eTask['series']['id'],
						'name' => $eTask['series']['name'],
						'mode' => $eTask['series']['mode']
					],
					'plant' => $eTask['plant']->empty() ? NULL : $eTask['plant']['name'],
					'description' => ($eTask['description'] === NULL) ? NULL : explode("\n", $eTask['description'])[0]
				];

			}

			foreach(self::countLate($eStructure, $week, $followed) as $action => $late) {
				$actions[$action]['late'] = $late;
			}

		}

		$collator = \L::getCollator();

		$getName = fn(array $task): string => $task['series']['name'] ?? $task['plant'] ?? $task['description'] ?? '';

		foreach($actions as $action => $values) {

			$names = array_unique($plants[$action]);

			usort($names, fn(string $first, string $second): int => $collator->compare($first, $second));

			$actions[$action]['plants'] = array_slice($names, 0, self::PLANTS_MAX);
			$actions[$action]['plantsHidden'] = max(0, count($names) - self::PLANTS_MAX);

			usort($actions[$action]['tasks'], fn(array $first, array $second): int => ($collator->compare($getName($first), $getName($second)) ?: $first['id'] <=> $second['id']));

		}

		return [
			'week' => \Clock::week(),
			'actions' => array_values($actions),
			'todo' => array_sum(array_column($actions, 'todo')),
			'late' => array_sum(array_column($actions, 'late')),
			'lateUrl' => \structure\StructureUi::urlPlanningWeekly($eStructure)
		];

	}

	/**
	 * Les interventions en retard de chaque action suivie, celles que « Retardé » montre en tête de la
	 * semaine du planning (L67) : sa règle, `TaskLib::applyDelayed()` sous la profondeur
	 * `planningDelayedMax` et `applyOpenSeries()`, appelée et non réécrite (`TaskLib::getForWeekDelayed()`).
	 * Le compte se groupe par action : une requête, quel que soit le nombre de retards, là où l'écran les
	 * hydrate une à une (2 271 sur 525).
	 *
	 * @return int[] indexé par action, sans les actions à zéro
	 */
	private static function countLate(\structure\Structure $eStructure, string $week, array $followed): array {

		$mTask = Task::model();

		TaskLib::applyDelayed($mTask, $eStructure, $week);

		// `applyDelayed()` trie pour l'écran sur `plannedWeek`, une colonne ni groupée ni agrégée, que MySQL refuse sous `ONLY_FULL_GROUP_BY`.
		$mTask->resetSort();

		// `applyOpenSeries()` pose sa condition sur le singleton, avant la requête (même ordre que plus haut).
		TaskLib::applyOpenSeries();

		$cTask = $mTask
			->select([
				'action',
				'late' => new \Sql('COUNT(*)', 'int')
			])
			->whereStructure($eStructure)
			->whereAction('IN', $followed)
			->group('action')
			->getCollection();

		$late = [];

		foreach($cTask as $eTask) {
			$late[$eTask['action']['id']] = $eTask['late'];
		}

		return $late;

	}

	/**
	 * Où en est le bloc après un geste sur une intervention : le bloc relu, l'action de l'intervention et
	 * l'intervention elle-même, telles que le bloc les montre. `action` vaut `NULL` quand l'action n'a plus
	 * rien cette semaine, ou que le membre ne la suit plus, et `task` quand l'intervention a quitté la
	 * semaine : cochée, elle compte à sa semaine faite, qu'un temps pointé plus tard peut repousser, et
	 * décochée, à sa semaine prévue (`TaskLib::updateDoneCollection()`).
	 *
	 * @return array{week: array, action: ?array, task: ?array}
	 */
	public static function getWeekPlace(\structure\Structure $eStructure, Task $eTask): array {

		$eTask->expects(['id', 'action']);

		$week = self::getWeek($eStructure);

		foreach($week['actions'] as $action) {

			if($action['id'] !== $eTask['action']['id'] or $action['done'] + $action['todo'] === 0) {
				continue;
			}

			foreach($action['tasks'] as $task) {

				if($task['id'] === $eTask['id']) {
					return ['week' => $week, 'action' => $action, 'task' => $task];
				}

			}

			return ['week' => $week, 'action' => $action, 'task' => NULL];

		}

		return ['week' => $week, 'action' => NULL, 'task' => NULL];

	}

}
?>
