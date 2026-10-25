<?php
namespace entry;

/**
 * « désherbage carotte » puis Entrée : l'intervention de culture prévue passe faite (lot S4a). La phrase est
 * celle du temps (`TimeIntentionLib`) : la coche en délègue la lecture de l'objet et de l'espèce, et ne s'en
 * distingue, sans verbe, que par ce qu'elle trouve à cocher (`getImplicitPrecedence()`). Une durée tapée sans
 * verbe garde la phrase au temps, qui propose alors d'écrire aussi la coche (`isOffered()`) ; avec le verbe,
 * la coche écrit la durée sur l'intervention qu'elle coche (lot S4c).
 *
 * Les interventions cochables sont celles de l'action et de l'espèce, de culture, à faire, d'une série
 * ouverte ou hors série, prévues de `WEEKS_BEFORE` semaines avant à `WEEKS_AFTER` semaines après le jour dit
 * (19/10). Une seule se coche sans question ; plusieurs, le champ demande laquelle, la plus proche
 * du jour dit en tête, et « Toutes les séries » quand elles sont sur plusieurs séries : la plus proche de
 * chacune se coche (lot S4d). La coche date l'intervention du jour dit.
 *
 * Rien de prévu dans la fenêtre : l'intervention se crée faite au jour dit, sur la série que le champ demande
 * comme pour un temps qui ajoute la sienne au planning (lot S4b, 19/10), ou sur chacune des séries en
 * phase (lot S4d).
 *
 * La lecture et l'écriture portent donc une collection d'interventions (`cTask`), d'une seule dans le cas
 * courant. Une durée dite se répartit à parts égales entre elles, comme Consigner le fait (`fair`).
 *
 * Annuler remet à faire celles qui étaient prévues, si elles sont restées dans l'état où la coche les a
 * laissées (`\series\TaskLib::updateTodoIfLeft()`), puis retire le temps écrit avec chacune, et supprime celles
 * que la coche a créées, avec leur temps, si rien ne s'y est ajouté depuis (`CancelLib::isCreatedBy()`).
 */
class DoneIntentionLib extends IntentionLib {

	const int WEEKS_BEFORE = 8;
	const int WEEKS_AFTER = 3;

	/**
	 * Les interventions que la question propose au plus, les plus proches du jour dit : une structure en garde
	 * plus de cent d'une même action et d'une même espèce dans la fenêtre (hypothèse, recette de S4a).
	 */
	const int QUESTION_LIMIT = 10;

	public static function getWords(): array {
		return ['fait', 'faite', 'faits', 'faites', 'fini', 'finie', 'termine', 'terminee'];
	}

	// Le droit du planning, qui coche une intervention (`task.p.php`, `doUpdateDoneCollection`).
	public static function canWrite(\structure\Structure $eStructure): bool {
		return $eStructure->canPlanning();
	}

	public static function writesPlanning(): bool {
		return TRUE;
	}

	// Les interventions de la semaine, et les récoltes quand la coche en porte une quantité.
	public static function getDashboardBlocks(): array {
		return ['series-week', 'planning-harvest'];
	}

	/**
	 * Les objets du temps, plus ceux que la même phrase a lus avant que le jour dit ne change la semaine : ce
	 * que la coche cherche ne dépend pas de la semaine du jour dit, et le moteur refuserait sinon l'objet
	 * nommé (`dated`).
	 */
	public static function getObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead = new \Collection()): \Collection {

		$cTaskObject = TimeIntentionLib::getObjects($eStructure, $reading, $cElementRead);

		foreach($cElementRead as $id => $eTaskRead) {

			if($cTaskObject->offsetExists($id) === FALSE) {
				$cTaskObject[$id] = $eTaskRead;
			}

		}

		return $cTaskObject;

	}

	public static function getListed(\Collection $cElement): \Collection {
		return TimeIntentionLib::getListed($cElement);
	}

	public static function getTyped(\Collection $cElement): \Collection {
		return TimeIntentionLib::getTyped($cElement);
	}

	public static function isSearching(array $reading): bool {
		return TimeIntentionLib::isSearching($reading);
	}

	public static function getShown(\structure\Structure $eStructure, array $reading, \Collection $cElement): \Collection {
		return TimeIntentionLib::getShown($eStructure, $reading, $cElement);
	}

	public static function readObject(\Collection $cElement, array &$words): \Element {
		return TimeIntentionLib::readObject($cElement, $words);
	}

	public static function getDateRefusal(string $date): ?array {
		return self::getPastRefusal($date);
	}

	/**
	 * Sans verbe, la coche ne prend la phrase au temps que lorsqu'elle a une intervention à cocher, à choisir,
	 * à créer faite (`isOffered()`) ou déjà faite : sinon, la frappe de l'objet et les refus restent ceux du
	 * temps. Une durée dite garde toujours la phrase au temps, la grammaire « Entrée sans durée » réservant
	 * Entrée à la coche (19/10) : le temps propose alors la coche en seconde ligne.
	 */
	public static function getImplicitPrecedence(array $reading): int {

		if($reading['complements']['duration'] !== NULL) {
			return -1;
		}

		return (self::isOffered($reading) or ($reading['refusal'] === NULL and $reading['task']->notEmpty())) ? 1 : -1;

	}

	/**
	 * La coche a quelque chose à écrire, tout de suite ou après sa question : une intervention à cocher, à
	 * choisir, ou une série où la créer. Une déjà faite n'attend plus rien.
	 */
	public static function isOffered(array $reading): bool {

		return (
			$reading['refusal'] === NULL and (
				$reading['outcome'] === EntryLib::WRITE or
				($reading['question'] !== NULL and in_array($reading['question']['name'], ['task', 'series'], TRUE))
			)
		);

	}

	/**
	 * L'espèce, puis l'intervention à cocher. Sans candidate, l'intervention de l'action et de l'espèce faite le
	 * jour dit est retenue sans rien écrire : il n'y a plus rien à cocher, et un second Entrée n'en crée pas une
	 * autre. Sinon, une action de culture se crée faite (`resolveAdd()`).
	 */
	public static function resolve(\structure\Structure $eStructure, array &$reading, array $answers): void {

		// Refusé dès la lecture, comme `Timesheet::time.untracked` le refuserait à l'écriture. Une durée en
		// frappe ou hors bornes attend, ou se refuse, comme partout (`DurationComplementLib::apply()`).
		if(is_int($reading['complements']['duration']) and $eStructure->hasTime() === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'untracked']);
			return;
		}

		$eAction = $reading['object']['action'];
		$ePlant = $reading['object']['plant'];

		// Une action nommée seule, suivie de l'espèce, comme pour le temps.
		if($ePlant->empty()) {

			$cPlant = ($reading['pending'] === NULL) ? new \Collection() : TimeIntentionLib::readPlants($eStructure, $reading['pending']);

			// Le début de plusieurs noms sous une action de culture (`addable`) garde la question de l'espèce, et
			// reste en attente : l'espèce choisie le remplace.
			if($cPlant->count() > 1) {

				if($reading['object']['addable'] and TimeIntentionLib::askPlant($eStructure, $reading, $cPlant)) {
					return;
				}

				$reading['pending'] = NULL;
				$reading['outcome'] = EntryLib::ASK;
				return;

			}

			if($cPlant->empty()) {

				// L'action seule que la frappe propose (`TimeIntentionLib::getTyped()`) demande l'espèce, comme pour
				// le temps.
				if($reading['pending'] === NULL and $reading['object']['typed'] and TimeIntentionLib::askPlant($eStructure, $reading)) {
					return;
				}

				self::refuseUnplanned($reading, $reading['object']['name']);
				return;

			}

			// Nommée par ses mots, comme pour le temps : le choix d'une série les retire du champ (`setSaid()`).
			$ePlant = $cPlant->first();
			$reading['plant'] = $ePlant;
			$reading['plantNamed'] = EntryLib::split($reading['pending']);
			$reading['pending'] = NULL;

		}

		$name = $eAction['name'].' '.mb_strtolower($ePlant['name']);

		$eCategory = \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE);

		if($eCategory->empty()) {
			self::refuseUnplanned($reading, $name);
			return;
		}

		$cTask = self::getCandidates($eStructure, $eCategory, $eAction, $ePlant, $reading['date']);

		if($cTask->empty()) {

			$eTaskDone = self::getDone($eStructure, $eCategory, $eAction, $ePlant, $reading['date']);

			if($eTaskDone->notEmpty()) {
				$eTaskDone['timed'] = $eStructure->hasTime(); // Faite le jour dit, donc dans sa semaine.
				$reading['task'] = $eTaskDone;
				$reading['outcome'] = EntryLib::ASK;
			} else if(
				// Relue pour ses catégories, que seule la création demande : la lecture de chaque phrase ne les paie pas.
				TimeIntentionLib::isAddable($eCategory, \work\ActionLib::getByStructure($eStructure, id: $eAction['id']))
			) {
				self::resolveAdd($eStructure, $reading, $answers, $eAction, $ePlant);
			} else {
				self::refuseUnplanned($reading, $name);
			}

			return;

		}

		$cTaskBySeries = self::getNearestBySeries($cTask);

		if($cTask->count() === 1) {
			$eTask = $cTask->first();
		} else if($answers['task'] !== NULL and $cTask->offsetExists($answers['task'])) {
			$eTask = $cTask[$answers['task']];
		} else if($answers['all'] and $cTaskBySeries->count() > 1) {

			self::confirm($reading, $cTaskBySeries, FALSE);
			return;

		} else {

			// La liste montrée est bornée, « Toutes les séries » porte sur toute la fenêtre.
			EntryLib::ask($reading, [
				'name' => 'task',
				'cTask' => $cTask->slice(0, min(self::QUESTION_LIMIT, $cTask->count()), preserveKeys: TRUE),
				'cTaskAll' => ($cTaskBySeries->count() > 1) ? $cTaskBySeries : new \Collection(),
			]);

			return;

		}

		// Une durée ajoutée sans verbe fait de la phrase un temps, qui ne se pose que sur une intervention que le
		// temps propose au jour dit (`TimeIntentionLib::isProposed()`) : ailleurs, la durée chercherait une autre
		// intervention. Une durée déjà dite n'a plus à se proposer.
		$eTask['timed'] = (
			$reading['complements']['duration'] === NULL and
			$eStructure->hasTime() and
			TimeIntentionLib::isProposed($eTask, $reading['date'])
		);

		$reading['task'] = $eTask;

		self::confirm($reading, new \Collection([$eTask['id'] => $eTask]), FALSE);

	}

	/**
	 * La plus proche du jour dit de chaque série, les candidates arrivant rangées ainsi (`getCandidates()`).
	 * Les interventions hors série en font une : ce sont des passages sur l'espèce, pas des séries.
	 */
	private static function getNearestBySeries(\Collection $cTask): \Collection {

		$cTaskNearest = new \Collection();
		$seen = [];

		foreach($cTask as $eTask) {

			$seriesId = $eTask['series']->empty() ? 0 : $eTask['series']['id'];

			if(in_array($seriesId, $seen, TRUE) === FALSE) {
				$seen[] = $seriesId;
				$cTaskNearest[$eTask['id']] = $eTask;
			}

		}

		return $cTaskNearest;

	}

	/**
	 * La question des séries où créer l'intervention faite, celle du temps qui ajoute la sienne au planning
	 * (`TimeIntentionLib::resolveAdd()`), mêmes réponses, posée même pour une seule culture : les cultures de
	 * l'espèce rangées pour l'action (`CultivationResolveLib::sortByPhase()`), puis « Hors série ».
	 *
	 * L'intervention se construit dès la lecture : un refus de `build()` vaut pour la lecture comme pour
	 * l'écriture.
	 */
	private static function resolveAdd(\structure\Structure $eStructure, array &$reading, array $answers, \work\Action $eAction, \plant\Plant $ePlant): void {

		$cCultivation = CultivationResolveLib::sortByPhase(CultivationResolveLib::getByPlant($eStructure, $ePlant, $reading['date']), $eAction, toWeek($reading['date']));

		$cCultivationPhase = $cCultivation->find(fn(\series\Cultivation $eCultivation) => $eCultivation['inPhase']);

		if($answers['addCultivation'] !== NULL and $cCultivation->offsetExists($answers['addCultivation'])) {
			$cCultivationAdded = new \Collection([$cCultivation[$answers['addCultivation']]]);
		} else if($answers['addNoSeries'] === $ePlant['id']) {
			$cCultivationAdded = new \Collection([new \series\Cultivation()]);
		} else if($answers['all'] and $cCultivationPhase->count() > 1) {
			$cCultivationAdded = $cCultivationPhase;
		} else {

			HarvestIntentionLib::fillPlaces($cCultivation);

			EntryLib::ask($reading, [
				'name' => 'series',
				'ePlant' => $ePlant,
				'cCultivation' => $cCultivation,
				'cCultivationAll' => ($cCultivationPhase->count() > 1) ? $cCultivationPhase : new \Collection(),
			]);

			return;

		}

		$fw = new \FailWatch();

		$cTask = new \Collection();

		foreach($cCultivationAdded as $eCultivation) {
			$cTask[] = TimeIntentionLib::buildTask($eStructure, $eAction, $ePlant, $eCultivation, $reading['date'], \series\Task::DONE);
		}

		$fw->validate();

		foreach($cTask as $eTask) {

			// L'espèce lue, la même que celle que `build()` a posée hors série, porte ce que l'écran en montre.
			$eTask['plant'] = $ePlant;
			$eTask['timed'] = FALSE;

			TimeIntentionLib::fillNameWithSeason($eTask);

		}

		$reading['task'] = ($cTask->count() === 1) ? $cTask->first() : new \series\Task();

		self::confirm($reading, $cTask, TRUE);

	}

	/**
	 * Les interventions à cocher, par id, ou à créer, sans id encore, et la durée dite avec elles (`minutes`,
	 * sinon `NULL`). Une durée encore en frappe (`DurationComplementLib::TYPING`) attend la suite, que
	 * `EntryLib::readText()` arrête.
	 */
	private static function confirm(array &$reading, \Collection $cTask, bool $created): void {

		$minutes = $reading['complements']['duration'];

		$reading['confirmation'] = [
			'cTask' => $cTask,
			'date' => $reading['date'],
			'minutes' => is_int($minutes) ? $minutes : NULL,
			'created' => $created,
		];

		$reading['outcome'] = EntryLib::WRITE;

	}

	private static function refuseUnplanned(array &$reading, string $name): void {

		EntryLib::refuse($reading, [
			'reason' => 'unplanned',
			'name' => $name,
			'from' => self::getWeekFrom($reading['date']),
			'to' => self::getWeekTo($reading['date']),
		]);

	}

	public static function getWeekFrom(string $date): string {
		return toWeek($date.' - '.self::WEEKS_BEFORE.' WEEKS');
	}

	public static function getWeekTo(string $date): string {
		return toWeek($date.' + '.self::WEEKS_AFTER.' WEEKS');
	}

	/**
	 * Les interventions à cocher, la plus proche de la semaine du jour dit en tête, puis la plus ancienne.
	 */
	private static function getCandidates(\structure\Structure $eStructure, \work\Category $eCategory, \work\Action $eAction, \plant\Plant $ePlant, string $date): \Collection {

		\series\TaskLib::applyOpenSeries();

		$cTask = \series\Task::model()
			->select(self::getSelection())
			->whereStructure($eStructure)
			->whereStatus(\series\Task::TODO)
			->wherePlannedWeek('BETWEEN', new \Sql(\series\Task::model()->format(self::getWeekFrom($date)).' AND '.\series\Task::model()->format(self::getWeekTo($date))))
			->whereAction($eAction)
			->wherePlant($ePlant)
			->whereCategory($eCategory)
			->getCollection(index: 'id');

		$week = strtotime(toWeek($date));

		$cTask->sort(function(\series\Task $e1, \series\Task $e2) use($week) {

			$distance = abs(strtotime($e1['plannedWeek']) - $week) <=> abs(strtotime($e2['plannedWeek']) - $week);

			return ($distance !== 0) ? $distance : strcmp($e1['plannedWeek'], $e2['plannedWeek']);

		});

		return $cTask;

	}

	/**
	 * L'intervention de l'action et de l'espèce faite le jour dit, la dernière cochée s'il y en a plusieurs.
	 */
	private static function getDone(\structure\Structure $eStructure, \work\Category $eCategory, \work\Action $eAction, \plant\Plant $ePlant, string $date): \series\Task {

		return \series\Task::model()
			->select(self::getSelection())
			->whereStructure($eStructure)
			->whereStatus(\series\Task::DONE)
			->whereDoneWeek(toWeek($date))
			->whereDoneDate($date)
			->whereAction($eAction)
			->wherePlant($ePlant)
			->whereCategory($eCategory)
			->sort(['updatedAt' => SORT_DESC, 'id' => SORT_DESC])
			->get();

	}

	private static function getSelection(): array {

		return [
			'id',
			'action' => ['name', 'fqn'],
			'plant' => ['name', 'fqn'],
			'series' => ['name'],
			'cultivation', 'description', 'status', 'plannedWeek', 'doneWeek', 'doneDate',
		];

	}

	/**
	 * Relues entières, en une requête, contrôlées comme le planning contrôle sa coche (`task.p.php`,
	 * `doUpdateDoneCollection`). Celles qui se créent se contrôlent comme des interventions neuves. Le temps
	 * dit avec elles se contrôle ensuite comme un temps seul (`TimeIntentionLib::prepareTime()`), sur la
	 * première : `writeByTasks()` le pose sur chacune, et ce qu'il contrôle, la personne et sa journée, ne
	 * dépend pas de l'intervention.
	 */
	public static function prepare(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		if($confirmation['created']) {

			$cTask = $confirmation['cTask'];
			$cTask->validate('canCreate', 'acceptCreate');

		} else {

			$cTask = self::getTasks($eStructure, $confirmation['cTask']->getIds());

			if($cTask->count() !== $confirmation['cTask']->count()) {
				throw new \NotExistsAction();
			}

			$cTask->validate('isTodo', 'canWrite');

			$reading['confirmation']['cTask'] = $cTask;

		}

		$reading['eTimesheet'] = ($confirmation['minutes'] === NULL) ?
			new \series\Timesheet() :
			TimeIntentionLib::prepareTime($eStructure, $cTask->first(), $reading['date'], $confirmation['minutes']);

	}

	private static function getTasks(\structure\Structure $eStructure, array $ids): \Collection {

		return \series\Task::model()
			->select(\series\Task::getSelection())
			->whereId('IN', $ids)
			->whereStructure($eStructure)
			->getCollection(index: 'id');

	}

	/**
	 * Faites au jour dit et dans leur semaine, avec le temps dit. Pour chacune, ce que l'annulation exige encore
	 * (`$reading['tasks']`) : `taskLeft`, l'état où la coche la laisse, relu dans la même transaction, pour la
	 * remettre à faire ; `taskTouched`, l'empreinte de celle qu'elle crée, qui ne part que si rien ne l'a
	 * changée depuis ; `time`, le temps qu'elle y a écrit, que l'annulation retire. Relues entières, pour la
	 * réponse et la pastille.
	 */
	public static function write(\structure\Structure $eStructure, array &$reading): void {

		$cTask = $reading['confirmation']['cTask'];
		$created = $reading['confirmation']['created'];

		if($created) {

			foreach($cTask as $eTask) {
				\series\TaskLib::create($eTask);
			}

		} else {
			\series\TaskLib::updateDoneCollection($cTask, NULL, $reading['date']);
		}

		$cTask = self::getTasks($eStructure, $cTask->getIds());

		$times = array_fill_keys($cTask->getIds(), 0.0);

		// Comme le temps seul (`TimeIntentionLib::write()`), sur les interventions relues, qui portent leur série.
		// Le temps écrit sur chacune est l'écart à celui qu'elle avait déjà ce jour-là : la répartition le décide.
		if($reading['eTimesheet']->notEmpty()) {

			$eTimesheet = $reading['eTimesheet'];
			$eUser = \user\ConnectionLib::getOnline();

			foreach($cTask as $eTask) {
				$times[$eTask['id']] = -\series\TimesheetLib::getTimeByDate($eTask, $eUser, $reading['date']);
			}

			\series\TimesheetLib::writeByTasks($cTask, $eTimesheet, $eTimesheet['time'], 'fair');

			$cTask = self::getTasks($eStructure, $cTask->getIds());

			foreach($cTask as $eTask) {
				$times[$eTask['id']] += \series\TimesheetLib::getTimeByDate($eTask, $eUser, $reading['date']);
			}

		}

		$reading['tasks'] = [];

		foreach($cTask as $eTask) {

			if($created) {
				TimeIntentionLib::fillNameWithSeason($eTask);
			}

			$reading['tasks'][$eTask['id']] = [
				'taskLeft' => $eTask->extracts(['status', 'plannedDate', 'doneWeek', 'harvest']),
				'taskTouched' => $created ? CancelLib::getTouched($eTask) : NULL,
				'time' => $times[$eTask['id']],
			];

		}

		$reading['confirmation']['cTask'] = $cTask;

	}

	public static function afterCommit(\structure\Structure $eStructure, array &$reading): void {

		$ticket = bin2hex(random_bytes(16));

		\Cache::redis()->set(self::getCancelKey($eStructure, $ticket), [
			'user' => \user\ConnectionLib::getOnline()['id'],
			'tasks' => $reading['tasks'],
			'date' => $reading['date'],
			'created' => $reading['confirmation']['created'],
		], EntrySetting::CANCEL_DELAY);

		$reading['undo'] = ['intention' => 'done', 'ticket' => $ticket];

	}

	/**
	 * Les interventions d'une écriture de la coche, comme le planning les redessine.
	 */
	public static function getPlanningTasks(array $values): \Collection {
		return $values['cTask'];
	}

	public static function getModel(): \ModuleModel {
		return \series\Task::model();
	}

	public static function getCancelKey(\structure\Structure $eStructure, string $ticket): string {
		return 'entry-cancel-done-'.$eStructure['id'].'-'.$ticket;
	}

	public static function getCancelFail(): string {
		return 'entry\Entry::done.cancellable';
	}

	/**
	 * Le ticket vivant d'une coche de la personne connectée, sur des interventions de la structure :
	 * - `cTask` : les interventions du ticket encore là, dans leur état avant l'annulation, qui dit à la réponse
	 *   si elles étaient dans À faire ;
	 * - `cTaskReverted` : celles que l'annulation défait, jugé ici, hors transaction, comme pour le temps
	 *   (`TimeIntentionLib::prepareCancel()`) : une prévue restée dans l'état où la coche l'a laissée
	 *   (`taskLeft`, la condition de `updateTodoIfLeft()`), une créée où rien ne s'est ajouté depuis
	 *   (`CancelLib::isCreatedBy()`). Chacune porte `taskLeft` et `timeRemoved`, le temps que l'annulation
	 *   retire, jamais plus que ce que la personne a encore ce jour-là sur l'intervention ;
	 * - `eUser` et `date` : l'auteur et le jour de la coche ;
	 * - `time` : le temps retiré en tout, en heures ;
	 * - `created` : la coche les a créées, le ticket le disant. Elles ne se remettent pas à faire, n'ayant
	 *   jamais été prévues ;
	 * - `key` : la clé du ticket.
	 */
	public static function getCancellation(\structure\Structure $eStructure, string $ticket): ?array {

		if(preg_match('/^[0-9a-f]{32}$/', $ticket) !== 1) {
			return NULL;
		}

		$key = self::getCancelKey($eStructure, $ticket);
		$value = \Cache::redis()->get($key);

		if($value === FALSE or $value['user'] !== \user\ConnectionLib::getOnline()['id']) {
			return NULL;
		}

		$cTask = self::getTasks($eStructure, array_keys($value['tasks']));

		if($cTask->empty()) {
			return NULL;
		}

		$cTask->validate('canWrite');

		$eUser = new \user\User(['id' => $value['user']]);

		$cTaskReverted = new \Collection();
		$time = 0.0;

		foreach($cTask as $eTask) {

			$values = $value['tasks'][$eTask['id']];

			$timeRemoved = min($values['time'], \series\TimesheetLib::getTimeByDate($eTask, $eUser, $value['date']));

			$reverted = $value['created'] ?
				CancelLib::isCreatedBy($eTask, $eUser, [$value['date'] => $timeRemoved], $values['taskTouched']) :
				self::isLeft($eTask, $values['taskLeft']);

			if($reverted) {

				$eTaskReverted = clone $eTask;
				$eTaskReverted['taskLeft'] = $values['taskLeft'];
				$eTaskReverted['timeRemoved'] = $timeRemoved;

				$cTaskReverted[$eTask['id']] = $eTaskReverted;
				$time += $timeRemoved;

			}

		}

		return [
			'cTask' => $cTask,
			'cTaskReverted' => $cTaskReverted,
			'eUser' => $eUser,
			'date' => $value['date'],
			'time' => $time,
			'created' => $value['created'],
			'key' => $key,
		];

	}

	/**
	 * La condition de `\series\TaskLib::updateTodoIfLeft()`, lue sur l'intervention relue, pour que la pastille
	 * compte ce que l'annulation défera. La remise à faire la repose en base, au moment d'écrire.
	 */
	private static function isLeft(\series\Task $eTask, array $left): bool {

		return (
			$eTask['status'] === $left['status'] and
			$eTask['plannedDate'] === $left['plannedDate'] and
			$eTask['doneWeek'] === $left['doneWeek'] and
			(($left['harvest'] === NULL) ? ($eTask['harvest'] === NULL) : ($eTask['harvest'] !== NULL and round($eTask['harvest'], 2) === round($left['harvest'], 2)))
		);

	}

	/**
	 * Le ticket se retire d'abord, et c'est lui qui départage deux annulations simultanées, comme pour le
	 * temps (`TimeIntentionLib::cancel()`). Rien à défaire rend `FALSE`. Une remise à faire échoue quand
	 * l'intervention a bougé entre le jugement et l'écriture : elle reste alors faite, avec son temps,
	 * `EntryLib::cancel()` validant la transaction même sur ce refus, et le temps ne part donc qu'après elle.
	 * Aucune remise réussie rend `FALSE`. Celles que la coche a créées partent avec leur temps.
	 */
	public static function cancel(array $cancellation): bool {

		if(\Cache::redis()->delete($cancellation['key']) === FALSE) {
			return FALSE;
		}

		$reverted = 0;

		foreach($cancellation['cTaskReverted'] as $eTask) {

			if($cancellation['created']) {

				\series\TaskLib::delete($eTask);
				$reverted++;

			} else if(\series\TaskLib::updateTodoIfLeft($eTask, $eTask['taskLeft'])) {

				$reverted++;

				if($eTask['timeRemoved'] > 0.0) {
					CancelLib::removeTime($eTask, $cancellation['eUser'], $cancellation['date'], $eTask['timeRemoved']);
				}

			}

		}

		return ($reverted > 0);

	}

}
?>
