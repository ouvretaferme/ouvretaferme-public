<?php
namespace entry;

/**
 * Ce qu'une écriture du champ change dans la vue semaine du planning, pour que la réponse ne remplace
 * que les nœuds touchés au lieu de recharger la page.
 *
 * Le contexte est lu comme `series/page/index.p.php` le lit : la vue et les filtres mémorisés du
 * membre, la semaine et la parcelle que l'écran poste. Les interventions de la semaine sont relues
 * par les fonctions de la page, `TaskLib::getForWeekDone()` avant l'écriture et
 * `TaskLib::getForWeek()` après, donc avec le même tri et les mêmes données que le rendu complet : la
 * place d'une ligne se lit dans la comparaison des deux, elle n'est pas recalculée ici.
 *
 * Le rendu suit `PlanningUi::getByWeek()` : dans Fait, un bloc par action (`getTasks()`), où chaque
 * espèce ouvre sa tête (`getPlantTask()`) devant ses lignes. Les filtres d'action et d'espèce ne
 * retirent rien de la lecture, ils masquent au rendu ; ceux d'utilisateur et de parcelle écartent
 * l'intervention dès la requête.
 */
class PlanningLib {

	const string LINE = 'line';
	const string INSERT = 'insert';
	const string INSERT_PLANT = 'insertPlant';
	const string INSERT_ACTION = 'insertAction';
	const string ACTION = 'action';
	const string REMOVE = 'remove';
	const string REMOVE_PLANT = 'removePlant';
	const string REMOVE_ACTION = 'removeAction';
	const string NONE = 'none';

	const string AFTER = 'afterend';
	const string BEFORE = 'beforebegin';

	/**
	 * La vue semaine que l'écran montre, si l'écriture peut y changer quelque chose : `NULL` en vue
	 * quotidienne ou annuelle, sur une semaine antérieure à celle de la date écrite, et sur une
	 * semaine future, qui n'a pas de « Retardé » (`TaskLib::applyDelayed()`) et ne montre rien
	 * d'une date qui n'est jamais future.
	 *
	 * Une semaine postérieure à la date, jusqu'à la semaine courante, garde son contexte : elle montre
	 * dans « Retardé » les interventions à faire des semaines passées, et une récolte datée de l'une
	 * d'elles change leur ligne. Quand l'intervention n'y paraît pas, `getChange()` le dit.
	 */
	public static function getContext(\structure\Structure $eStructure, ?string $week, \map\Plot $ePlot, string $date): ?array {

		if(
			$eStructure->getView('viewPlanning') !== \structure\Member::WEEKLY or
			$week === NULL or
			\Filter::check('week', $week) === FALSE or
			strcmp(toWeek($date), $week) > 0 or
			strcmp($week, \Clock::week()) > 0
		) {
			return NULL;
		}

		$cAction = \work\ActionLib::getByStructure($eStructure, index: 'id');

		$cUserStructure = \structure\MemberLib::getUsersByStructureForPeriod($eStructure, week_date_starts($week), week_date_ends($week), withPresenceAbsence: TRUE);

		$search = new \Search([
			'plot' => $ePlot,
		]);

		$eUserSelected = $eStructure->getView('viewPlanningUser');

		if($eUserSelected->notEmpty()) {
			$eUserSelected = $cUserStructure->offsetExists($eUserSelected['id']) ? $cUserStructure[$eUserSelected['id']] : new \user\User();
		}

		$search->set('user', $eUserSelected);

		$eActionSelected = $eStructure->getView('viewPlanningAction');

		if($eActionSelected->notEmpty()) {
			$eActionSelected = $cAction->offsetExists($eActionSelected['id']) ? $cAction[$eActionSelected['id']] : new \work\Action();
		}

		$ePlantSelected = $eStructure->getView('viewPlanningPlant');

		if($ePlantSelected->notEmpty()) {
			$ePlantSelected = \plant\PlantLib::getById($ePlantSelected);
		}

		return [
			'week' => $week,
			'search' => $search,
			'cAction' => $cAction,
			'eUserSelected' => $eUserSelected,
			'eActionSelected' => $eActionSelected,
			'ePlantSelected' => $ePlantSelected,
			'cUserStructure' => $cUserStructure,
			'dues' => \planning\DueLib::getForWeek($eStructure, $week),
		];

	}

	/**
	 * L'onglet du temps de travail, que la page rend pour l'utilisateur connecté
	 * (`series/page/index.p.php`) : une écriture ou une annulation qui touche son temps le rend de nouveau,
	 * ses totaux du jour et de la semaine changeant avec elle. Vide quand le geste ne touche aucun temps, ou
	 * quand l'onglet montre un autre membre ou toute l'équipe (`$timeUser`, posé par le navigateur) : la
	 * saisie n'écrit que le temps de qui tape.
	 */
	public static function getUserTime(\structure\Structure $eStructure, array $context, ?int $timeUser, float $time): \user\User {

		$eUser = clone \user\ConnectionLib::getOnline();

		if($time <= 0.0 or $timeUser !== $eUser['id']) {
			return new \user\User();
		}

		// Comme la page : un membre absent de la période n'a ni présence ni absence.
		$eUser['weekTimesheet'] = \series\TimesheetLib::getTimesByWeek($eStructure, $eUser, $context['week']);
		$eUser['cWorkingTimeWeek'] = \hr\WorkingTimeLib::getByWeek($eStructure, $eUser, $context['week']);
		$eUser['cPresence'] = $context['cUserStructure'][$eUser['id']]['cPresence'] ?? new \Collection();
		$eUser['cAbsence'] = $context['cUserStructure'][$eUser['id']]['cAbsence'] ?? new \Collection();

		return $eUser;

	}

	public static function getDone(\structure\Structure $eStructure, array $context): \Collection {
		return \series\TaskLib::getForWeekDone($eStructure, $context['week'], $context['search']);
	}

	public static function getWeek(\structure\Structure $eStructure, array $context): \Collection {
		return \series\TaskLib::getForWeek($eStructure, $context['week'], $context['cAction'], $context['search']);
	}

	/**
	 * Les interventions d'une écriture qui étaient à faire, par id, lues avant elle. Une intervention qui va se
	 * créer n'en est pas.
	 */
	public static function getTodo(\Collection $cTask): array {

		$todo = [];

		foreach($cTask as $eTask) {

			if($eTask->exists() and $eTask['status'] === \series\Task::TODO) {
				$todo[] = $eTask['id'];
			}

		}

		return $todo;

	}

	/**
	 * Ce qui change pour les interventions d'une écriture : une seule, comme `getChange()` le dit. Plusieurs
	 * sont d'une même action (la coche de « Toutes les séries », `DoneIntentionLib`) : le bloc de l'action se
	 * redessine entier (`ACTION`), entre dans Fait (`INSERT_ACTION`) ou en sort (`REMOVE_ACTION`), ou rien
	 * quand les filtres le masquent avant comme après (`NONE`). `$todo` : celles qui étaient à faire avant
	 * le geste (`getTodo()`).
	 */
	public static function getChangeCollection(array $context, \Collection $ccTaskBefore, \Collection $cccTaskAfter, \Collection $cTask, array $todo): array {

		if($cTask->count() === 1) {
			$eTask = $cTask->first();
			return self::getChange($context, $ccTaskBefore, $cccTaskAfter, $eTask, in_array($eTask['id'], $todo, TRUE));
		}

		$eAction = $context['cAction'][$cTask->first()['action']['id']];

		$ccTaskAfter = $cccTaskAfter['done'];

		$visibleBefore = self::getVisible($ccTaskBefore, $eAction, $context);
		$visibleAfter = self::getVisible($ccTaskAfter, $eAction, $context);

		$visibleAfterIds = array_map(fn(\series\Task $eTask) => $eTask['id'], $visibleAfter);

		$todoAfter = FALSE;
		$doneChanged = FALSE;

		foreach($cTask as $eTask) {
			$todoAfter = ($todoAfter or self::findTodo($cccTaskAfter, $eTask)->notEmpty());
			$doneChanged = ($doneChanged or self::find($ccTaskBefore, $eTask)->notEmpty() !== self::find($ccTaskAfter, $eTask)->notEmpty());
		}

		$change = [
			'id' => $cTask->first()['id'],
			'action' => $eAction,
			'level' => self::NONE,
			'anchor' => [],
			'eTask' => new \series\Task(),
			'cTask' => $ccTaskAfter->offsetExists($eAction['id']) ? $ccTaskAfter[$eAction['id']] : new \Collection(),
			// Celles que Fait montre après, que la réponse signale dans le bloc redessiné.
			'cTaskChanged' => $cTask->find(fn(\series\Task $eTask) => in_array($eTask['id'], $visibleAfterIds, TRUE)),
		];

		$change['todo'] = ($todo !== [] or $todoAfter or self::isEmpty($cccTaskAfter, $ccTaskBefore, $context) !== self::isEmpty($cccTaskAfter, $ccTaskAfter, $context));
		$change['filters'] = ($change['todo'] or $doneChanged);

		if($visibleBefore !== [] and $visibleAfter !== []) {
			$change['level'] = self::ACTION;
		} else if($visibleAfter !== []) {
			$change['level'] = self::INSERT_ACTION;
			$change['anchor'] = self::getActionAnchor($ccTaskAfter, $eAction, $context);
		} else if($visibleBefore !== []) {
			$change['level'] = self::REMOVE_ACTION;
		}

		return $change;

	}

	/**
	 * La semaine que montrerait le planning est-elle vide, Fait valant `$ccTaskDone` (`getChange()`).
	 */
	private static function isEmpty(\Collection $cccTaskAfter, \Collection $ccTaskDone, array $context): bool {

		return (
			$context['dues'] === [] and
			$cccTaskAfter['todo']->empty() and
			$cccTaskAfter['delayed']->empty() and
			$cccTaskAfter['unplanned']->empty() and
			$ccTaskDone->empty()
		);

	}

	/**
	 * Ce qui change pour une intervention, entre les interventions faites de la semaine avant
	 * l'écriture et toutes celles de la semaine après.
	 *
	 * - `id` : l'intervention, qui désigne sa ligne même quand elle n'est plus dans Fait ;
	 * - `action` : son action, dont le bloc la porte dans Fait, la récolte comme toute autre ;
	 * - `level` : la ligne réécrite (`LINE`), insérée seule, avec la tête de son espèce ou avec tout
	 *   le bloc de son action (`INSERT*`), retirée de même (`REMOVE*`), ou rien (`NONE`) ;
	 * - `anchor` : où l'insérer, `['task' => id, 'position' => AFTER|BEFORE]` à côté d'une voisine,
	 *   `['action' => id]` après la tête du bloc ou après le bloc d'une autre action, `['header' => TRUE]`
	 *   en tête de Fait ;
	 * - `eTask` : l'intervention telle que Fait la montre après, vide si elle n'y est plus ;
	 * - `cTask` : le bloc de son action après, pour `INSERT_ACTION` ;
	 * - `todo` : À faire se redessine entier, parce que l'intervention y était avant le geste (`$todoBefore`)
	 *   ou y est après, Retardé compris : l'écriture la termine, Annuler la remet à faire. Une ligne qui
	 *   part ou revient y change aussi la tête de Retardé et le « Le planning est encore vide. », que seul
	 *   le rendu sait poser. Il se redessine aussi quand la semaine devient vide ou cesse de l'être ;
	 * - `filters` : la barre de filtres se redessine, parce que ses compteurs portent sur les interventions
	 *   lues et non sur celles affichées : quand l'intervention entre dans Fait ou en sort, et quand À faire
	 *   se redessine, l'intervention ayant pu quitter la semaine affichée ou y revenir.
	 */
	public static function getChange(array $context, \Collection $ccTaskBefore, \Collection $cccTaskAfter, \series\Task $eTask, bool $todoBefore): array {

		$eAction = $context['cAction'][$eTask['action']['id']];

		$ccTaskAfter = $cccTaskAfter['done'];

		$visibleBefore = self::getVisible($ccTaskBefore, $eAction, $context);
		$visibleAfter = self::getVisible($ccTaskAfter, $eAction, $context);

		$change = [
			'id' => $eTask['id'],
			'action' => $eAction,
			'level' => self::NONE,
			'anchor' => [],
			'eTask' => self::find($ccTaskAfter, $eTask),
			'cTask' => $ccTaskAfter->offsetExists($eAction['id']) ? $ccTaskAfter[$eAction['id']] : new \Collection(),
		];

		$todoAfter = self::findTodo($cccTaskAfter, $eTask)->notEmpty();

		// Hors d'À faire avant comme après, l'intervention n'y change rien : ses listes sont celles d'avant,
		// et seul Fait décide si la semaine passe de vide à remplie, ou l'inverse. Sous une échéance, À faire
		// n'affiche jamais « Le planning est encore vide. » (`PlanningUi::getTodoPlanning()`).
		$change['todo'] = ($todoBefore or $todoAfter or self::isEmpty($cccTaskAfter, $ccTaskBefore, $context) !== self::isEmpty($cccTaskAfter, $ccTaskAfter, $context));
		$change['filters'] = (
			$change['todo'] or
			self::find($ccTaskBefore, $eTask)->notEmpty() !== self::find($ccTaskAfter, $eTask)->notEmpty()
		);

		$positionBefore = self::getPosition($visibleBefore, $eTask);
		$positionAfter = self::getPosition($visibleAfter, $eTask);

		if($positionBefore !== NULL and $positionAfter !== NULL) {
			$change['level'] = self::LINE;
		} else if($positionAfter !== NULL) {
			self::fillInsert($change, $visibleAfter, $positionAfter, $ccTaskAfter, $eAction, $context);
		} else if($positionBefore !== NULL) {
			self::fillRemove($change, $visibleBefore, $positionBefore, $visibleAfter);
		}

		return $change;

	}

	/**
	 * Une ligne entre dans Fait : seule si une voisine de la même espèce l'encadre, avec la tête de
	 * son espèce sinon, avec tout le bloc de son action si elle en est la seule ligne.
	 */
	private static function fillInsert(array &$change, array $visible, int $position, \Collection $ccTaskAfter, \work\Action $eAction, array $context): void {

		$eTask = $visible[$position];
		$eTaskPrevious = $visible[$position - 1] ?? new \series\Task();
		$eTaskNext = $visible[$position + 1] ?? new \series\Task();

		if(count($visible) === 1) {

			$change['level'] = self::INSERT_ACTION;
			$change['anchor'] = self::getActionAnchor($ccTaskAfter, $eAction, $context);

		} else if(self::isSamePlant($eTask, $eTaskPrevious)) {

			$change['level'] = self::INSERT;
			$change['anchor'] = ['task' => $eTaskPrevious['id'], 'position' => self::AFTER];

		} else if(self::isSamePlant($eTask, $eTaskNext)) {

			$change['level'] = self::INSERT;
			$change['anchor'] = ['task' => $eTaskNext['id'], 'position' => self::BEFORE];

		} else {

			$change['level'] = self::INSERT_PLANT;
			$change['anchor'] = $eTaskPrevious->notEmpty() ?
				['task' => $eTaskPrevious['id'], 'position' => self::AFTER] :
				['action' => $eAction['id']];

		}

	}

	/**
	 * Une ligne sort de Fait : la tête de son espèce part avec elle si aucune voisine ne la partage,
	 * le bloc de son action s'il n'en reste rien.
	 */
	private static function fillRemove(array &$change, array $visible, int $position, array $visibleAfter): void {

		$eTask = $visible[$position];

		if($visibleAfter === []) {
			$change['level'] = self::REMOVE_ACTION;
		} else if(
			self::isSamePlant($eTask, $visible[$position - 1] ?? new \series\Task()) or
			self::isSamePlant($eTask, $visible[$position + 1] ?? new \series\Task())
		) {
			$change['level'] = self::REMOVE;
		} else {
			$change['level'] = self::REMOVE_PLANT;
		}

	}

	/**
	 * Le bloc rendu avant celui de l'action, les blocs suivant l'ordre de la lecture (par nom
	 * d'action) : un bloc dont le filtre masque toutes les lignes n'est pas rendu.
	 */
	private static function getActionAnchor(\Collection $ccTaskAfter, \work\Action $eAction, array $context): array {

		$anchor = ['header' => TRUE];

		foreach($ccTaskAfter as $action => $cTask) {

			if($action === $eAction['id']) {
				break;
			}

			if(self::getVisible($ccTaskAfter, $cTask->first()['action'], $context) !== []) {
				$anchor = ['action' => $action];
			}

		}

		return $anchor;

	}

	/**
	 * Les lignes que Fait montre dans le bloc d'une action, dans l'ordre du rendu.
	 */
	private static function getVisible(\Collection $ccTask, \work\Action $eAction, array $context): array {

		if(
			$eAction->empty() or
			$ccTask->offsetExists($eAction['id']) === FALSE or
			($context['eActionSelected']->notEmpty() and $context['eActionSelected']->is($eAction) === FALSE)
		) {
			return [];
		}

		$visible = [];

		foreach($ccTask[$eAction['id']] as $eTask) {

			if(
				$context['ePlantSelected']->empty() or
				$context['ePlantSelected']->is($eTask['plant'])
			) {
				$visible[] = $eTask;
			}

		}

		return $visible;

	}

	private static function getPosition(array $visible, \series\Task $eTask): ?int {

		foreach($visible as $position => $eTaskVisible) {

			if($eTaskVisible['id'] === $eTask['id']) {
				return $position;
			}

		}

		return NULL;

	}

	private static function isSamePlant(\series\Task $eTask, \series\Task $eTaskOther): bool {

		return (
			$eTaskOther->notEmpty() and
			$eTask['plant']->notEmpty() and
			$eTask['plant']->is($eTaskOther['plant'])
		);

	}

	private static function find(\Collection $ccTask, \series\Task $eTask): \series\Task {

		foreach($ccTask as $cTask) {

			foreach($cTask as $eTaskFound) {

				if($eTaskFound['id'] === $eTask['id']) {
					return $eTaskFound;
				}

			}

		}

		return new \series\Task();

	}

	private static function findTodo(\Collection $cccTask, \series\Task $eTask): \series\Task {

		foreach(['todo', 'delayed', 'unplanned'] as $list) {

			$eTaskFound = self::find($cccTask[$list], $eTask);

			if($eTaskFound->notEmpty()) {
				return $eTaskFound;
			}

		}

		return new \series\Task();

	}

}
?>
