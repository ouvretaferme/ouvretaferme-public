<?php
namespace entry;

/**
 * « désherbage carotte 2 h » : le temps de l'utilisateur connecté sur une intervention de la semaine, sans
 * récolte (lot 2b). L'objet est l'action, suivie de l'espèce quand l'intervention en a une (« vente directe
 * 1 h » n'en a pas) : la phrase se lit sans verbe, comme la récolte, et `temps` reste admis en tête.
 *
 * Les interventions proposées sont celles que la fiche de temps montre sur la semaine du jour dit
 * (`TaskLib::getForWeekExport()`) : à faire cette semaine, faites cette semaine, ou portant déjà du temps
 * cette semaine, et celles restées à faire des semaines passées, en retard (HN-B7). Une seule se choisit sans
 * question ; plusieurs, une ligne par intervention (lot 2, Q2, piste A). La récolte n'en fait pas partie :
 * « récolte » est le verbe de `HarvestIntentionLib`, qui lit déjà le temps.
 *
 * Le temps s'ajoute (`TimesheetLib::writeByTasks()`), comme dans Consigner, construit et borné à la journée
 * par les mêmes méthodes (`HarvestWeekLib::buildTime()`, `checkTimeDay()`), et laisse l'intervention dans son
 * état : une intervention à faire le reste (lot 2, Q3). Il n'écrit que le temps de qui tape (Q4), jusqu'à
 * `EntrySetting::PAST_DAYS` jours en arrière (Q6).
 *
 * Le même temps s'écrit sur plusieurs jours de la semaine du jour dit, choisis en puces (`getDays()`,
 * 24/10) : un seul geste, une seule pastille, une seule annulation.
 *
 * Le temps seul n'a pas de récolte qui porte le ticket d'annulation : chaque écriture a le sien
 * (`setCancel()`), que la récolte emprunte aussi pour son temps seul.
 */
class TimeIntentionLib extends IntentionLib {

	/**
	 * Les objets de la lecture en cours (`EntryLib::getRead()`), par structure et par jour dit.
	 */
	private static array $objects = [];

	public static function getWords(): array {
		return ['temps'];
	}

	// Le même droit que la récolte (`HarvestIntentionLib::canWrite()`) : la lecture montre les interventions
	// de la semaine.
	public static function canWrite(\structure\Structure $eStructure): bool {
		return $eStructure->canPlanning();
	}

	public static function writesPlanning(): bool {
		return TRUE;
	}

	// Le temps seul crée l'intervention qu'il ne trouve pas, que le bloc de la semaine compte.
	public static function getDashboardBlocks(): array {
		return ['series-week'];
	}

	/**
	 * Une intervention par action et par espèce, nommée par les deux (« Désherbage carotte »), qui porte
	 * dans `cTask` les interventions de la semaine qu'elle désigne. Elle n'existe pas en base : son
	 * identifiant est celui de sa première intervention, ou celui que la même phrase lui a donné avant
	 * que le jour dit ne change la semaine (`$cElementRead`), pour que le moteur la retrouve.
	 *
	 * Chaque action de la structure qui n'a pas d'intervention de la semaine sans espèce est aussi nommée seule, et
	 * porte toutes ses interventions de la semaine, éventuellement aucune. Son identifiant est négatif, pour ne
	 * croiser aucune intervention. « fertilisation 1 h » demande alors laquelle, et « désherbage laitue 2 h » se
	 * lit en temps, que `resolve()` refuse quand la semaine n'en a pas, au lieu que la récolte de laitue l'emporte.
	 * La liste ne la propose pas d'emblée (`listed`, `getListed()`) : la frappe la trouve par le début de son nom
	 * (`typed`, `getTyped()`, HN-B11) quand la semaine n'en a aucune intervention, celles qu'elle a se proposant
	 * déjà, et qu'elle est de culture : choisie, elle demande l'espèce où l'ajouter au planning. `addable` dit
	 * qu'elle est de culture, interventions de la semaine ou non : un début d'espèce tapé après elle garde la
	 * question de l'espèce, une autre espèce que celles de la semaine pouvant toujours s'ajouter au planning.
	 *
	 * Une action nommée seule, proposée ou non, porte aussi dans `cTaskAction` toutes les interventions de
	 * l'action, où `resolve()` cherche l'espèce qui la suit.
	 */
	public static function getObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead = new \Collection()): \Collection {

		// Sans verbe, la coche relit la même phrase que le temps (`DoneIntentionLib`) : la même lecture, au même
		// jour, a les mêmes objets. Ceux qu'une phrase a déjà lus à un autre jour en reprennent les identifiants,
		// et se relisent.
		if($cElementRead->empty()) {

			$key = EntryLib::getRead().'-'.$eStructure['id'].'-'.$reading['date'];

			if(array_key_exists($key, self::$objects) === FALSE) {
				self::$objects = [$key => self::readObjects($eStructure, $reading, $cElementRead)];
			}

			return self::$objects[$key];

		}

		return self::readObjects($eStructure, $reading, $cElementRead);

	}

	private static function readObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead): \Collection {

		$ids = [];

		foreach($cElementRead as $eTaskRead) {
			$ids[self::getKey($eTaskRead)] = $eTaskRead['id'];
		}

		$cAction = \work\ActionLib::getByStructure($eStructure);
		$eCategory = \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE);
		$eActionHarvest = $cAction->find(fn(\work\Action $eAction) => $eAction['fqn'] === ACTION_RECOLTE, limit: 1);

		$week = toWeek($reading['date']);

		$objects = [];

		foreach(self::getTasks($eStructure, $week, $eActionHarvest) as $eTask) {

			$eTask['late'] = self::isLate($eTask, $reading['date']);

			$key = self::getKey($eTask);

			if(array_key_exists($key, $objects) === FALSE) {

				$objects[$key] = new \series\Task([
					'id' => $ids[$key] ?? $eTask['id'],
					'action' => $eTask['action'],
					'plant' => $eTask['plant'],
					'name' => $eTask['plant']->empty() ?
						$eTask['action']['name'] :
						$eTask['action']['name'].' '.mb_strtolower($eTask['plant']['name']),
					'cTask' => new \Collection(),
					'listed' => TRUE,
					'typed' => FALSE,
					'addable' => FALSE,
				]);

			}

			$objects[$key]['cTask'][$eTask['id']] = $eTask;

		}

		// En retard quand toutes ses interventions le sont : la liste la range après celles de la semaine, et dit
		// la semaine de la plus ancienne.
		foreach($objects as $eTaskObject) {

			$cTaskLate = $eTaskObject['cTask']->find(fn(\series\Task $eTask) => $eTask['late']);

			$eTaskObject['plannedWeekLate'] = ($cTaskLate->count() === $eTaskObject['cTask']->count()) ?
				min($cTaskLate->getColumn('plannedWeek')) :
				NULL;

		}

		foreach($cAction as $eAction) {

			if($eAction['fqn'] === ACTION_RECOLTE) {
				continue;
			}

			$key = $eAction['id'].'-';

			$cTaskAction = new \Collection();

			foreach($objects as $eTaskObject) {

				if($eTaskObject['action']['id'] === $eAction['id']) {
					$cTaskAction->appendCollection($eTaskObject['cTask']);
				}

			}

			if(array_key_exists($key, $objects) === FALSE) {

				$objects[$key] = new \series\Task([
					'id' => -$eAction['id'],
					'action' => $eAction,
					'plant' => new \plant\Plant(),
					'name' => $eAction['name'],
					'cTask' => $cTaskAction,
					'plannedWeekLate' => NULL,
					'listed' => FALSE,
					'typed' => ($cTaskAction->empty() and self::isAddable($eCategory, $eAction)),
					'addable' => self::isAddable($eCategory, $eAction),
				]);

			}

			$objects[$key]['cTaskAction'] = $cTaskAction;

			// Entière : ses catégories disent si l'intervention qu'aucune ne porte peut s'ajouter au planning
			// (`resolveAdd()`).
			$objects[$key]['action'] = $eAction;

		}

		usort($objects, fn(\series\Task $e1, \series\Task $e2) => (self::getRank($e1) <=> self::getRank($e2)) ?: \L::getCollator()->compare($e1['name'], $e2['name']));

		$cTaskObject = new \Collection();

		foreach($objects as $eTaskObject) {
			$cTaskObject[$eTaskObject['id']] = $eTaskObject;
		}

		return $cTaskObject;

	}

	/**
	 * Le rang d'un objet dans la liste : les interventions de la semaine, puis celles en retard, puis les actions
	 * seules, qui mènent à l'ajout au planning.
	 */
	private static function getRank(\series\Task $eTaskObject): int {

		if($eTaskObject['listed'] === FALSE) {
			return 2;
		}

		return ($eTaskObject['plannedWeekLate'] === NULL) ? 0 : 1;

	}

	public static function getListed(\Collection $cElement): \Collection {
		return $cElement->find(fn(\series\Task $eTaskObject) => $eTaskObject['listed']);
	}

	public static function getTyped(\Collection $cElement): \Collection {
		return $cElement->find(fn(\series\Task $eTaskObject) => $eTaskObject['typed']);
	}

	/**
	 * Le début d'un nom tapé sous l'action seule cherche l'espèce que la question propose (`askPlant()`).
	 */
	public static function isSearching(array $reading): bool {
		return ($reading['question'] !== NULL and $reading['question']['name'] === 'plant');
	}

	// Les interventions de la semaine et celles en retard, et les actions seules dont la frappe commence le nom.
	public static function getShown(\structure\Structure $eStructure, array $reading, \Collection $cElement): \Collection {

		$cTaskTyped = ($reading['pending'] === NULL) ? new \Collection() : EntryLib::findObjects(self::getTyped($cElement), $reading['pending'], start: TRUE);

		return $cElement->find(fn(\series\Task $eTaskObject) => ($eTaskObject['listed'] or $cTaskTyped->offsetExists($eTaskObject['id'])));

	}

	/**
	 * Un nom ne se retient pas quand, avec tous les mots qui le suivent, il commence le nom d'une
	 * intervention proposée : « fertilisation toma » est le début de « Fertilisation tomate », et
	 * « irrigation ch » celui de « Irrigation chou » même quand « Irrigation » seule en est une. La frappe
	 * doit le trouver comme tout objet encore incomplet (`EntryLib::findObjects()`). Un mot qui s'en écarte
	 * (« irrigation chou blanc 1 h ») rend le nom, et l'espèce se lit alors après l'action.
	 *
	 * Les liaisons se lisent comme dans la récolte, où « pomme de terre » nomme l'espèce : elles ne
	 * séparent ni l'action de l'espèce (« fertilisation de laitue »), ni les mots d'un nom.
	 */
	public static function readObject(\Collection $cTask, array &$words): \series\Task {

		$wordsRead = $words;
		$wordsUnlinked = EntryLib::withoutLinks($words);
		$wordsUnlinkedRead = $wordsUnlinked;

		$key = self::readName(self::getUnlinked($cTask), $wordsUnlinked);

		if($key === NULL) {
			return new \series\Task();
		}

		$positions = array_keys(array_diff_key($wordsUnlinkedRead, $wordsUnlinked));

		$named = array_filter($wordsRead, fn(int $position) => ($position >= min($positions) and $position <= max($positions)), ARRAY_FILTER_USE_KEY);
		$words = array_diff_key($wordsRead, $named);

		$following = array_filter($words, fn(int $position) => $position > array_key_last($named), ARRAY_FILTER_USE_KEY);

		if(
			EntryLib::withoutLinks($following) !== [] and
			self::findStarting(self::getListed($cTask), $named + $following)->notEmpty()
		) {
			$words = $wordsRead;
			return new \series\Task();
		}

		return $cTask[$key];

	}

	public static function getDateRefusal(string $date): ?array {
		return self::getPastRefusal($date);
	}

	/**
	 * L'intervention, puis la durée. Plusieurs interventions possibles posent la question, une ligne par
	 * intervention, chacune avec le temps que la personne y a déjà ce jour-là (`timeDay`, en heures).
	 */
	public static function resolve(\structure\Structure $eStructure, array &$reading, array $answers): void {

		// Refusé dès la lecture, comme `Timesheet::time.untracked` le refuserait à l'écriture.
		if($eStructure->hasTime() === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'untracked']);
			return;
		}

		$cTask = $reading['object']['cTask'];

		$pending = $reading['pending'];
		$cPlant = ($reading['object']['plant']->empty() and $pending !== NULL) ? self::readPlants($eStructure, $pending) : new \Collection();

		// Une action nommée seule, suivie d'une ou plusieurs espèces (« chou », début de plusieurs noms) :
		// toutes ses interventions sur elles, que la suite départage comme les autres.
		if($cPlant->notEmpty()) {

			$reading['pending'] = NULL;

			if($cPlant->count() === 1) {
				$reading['plant'] = $cPlant->first();
				$reading['plantNamed'] = EntryLib::split($pending);
			}

			$cTask = $reading['object']['cTaskAction']->find(fn(\series\Task $eTask) => ($eTask['plant']->notEmpty() and $cPlant->offsetExists($eTask['plant']['id'])));

			if($cTask->empty()) {

				$words = EntryLib::split($pending);

				// Une seule espèce, sans intervention de l'action cette semaine : l'intervention s'ajoute au
				// planning (lot 2c). En frappe, le début de plusieurs noms attend la suite, qui dira laquelle.
				// Le début de plusieurs noms sous une action de culture (`addable`) garde la question de l'espèce,
				// bornée à ceux qu'il lit. Il reste en attente : l'espèce choisie le remplace.
				if($cPlant->count() === 1 and self::isAddable(\work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE), $reading['object']['action'])) {
					self::resolveAdd($eStructure, $reading, $answers, $cPlant->first());
				} else if($cPlant->count() > 1 and $reading['object']['addable'] and self::askPlant($eStructure, $reading, $cPlant)) {
					$reading['pending'] = $pending;
				} else if($cPlant->count() > 1 and end($words) === $reading['typing']) {
					$reading['outcome'] = EntryLib::ASK;
				} else {
					EntryLib::refuse($reading, ['reason' => 'none', 'name' => $reading['object']['name'].' '.(($cPlant->count() === 1) ? mb_strtolower($cPlant->first()['name']) : $pending)]);
				}

				return;

			}

		}

		if($cTask->empty()) {

			// L'action seule de culture, sans espèce ni intervention de la semaine, celle que la frappe propose
			// (`typed`) : l'espèce se demande, et mène aux séries où l'ajouter au planning (HN-B11).
			if($reading['object']['typed'] and $reading['pending'] === NULL and self::askPlant($eStructure, $reading)) {
				return;
			}

			EntryLib::refuse($reading, ['reason' => 'none', 'name' => $reading['object']['name']]);
			return;

		}

		if($cTask->count() === 1) {
			$eTask = $cTask->first();
		} else if($answers['task'] !== NULL and $cTask->offsetExists($answers['task'])) {
			$eTask = $cTask[$answers['task']];
		} else {

			$times = \series\Timesheet::model()
				->select(['task', 'total' => new \Sql('SUM(time)', 'float')])
				->whereTask('IN', $cTask)
				->whereUser(\user\ConnectionLib::getOnline())
				->whereDate($reading['date'])
				->group('task')
				->getCollection()
				->makeArray(function(\series\Timesheet $eTimesheet, &$key) {
					$key = $eTimesheet['task']['id'];
					return $eTimesheet['total'];
				});

			foreach($cTask as $eTaskCandidate) {
				$eTaskCandidate['timeDay'] = $times[$eTaskCandidate['id']] ?? 0.0;
			}

			// Celles de la semaine d'abord, puis celles en retard, comme la liste (`getRank()`).
			$cTask->sort(['late' => SORT_ASC, 'id' => SORT_ASC]);

			EntryLib::ask($reading, [
				'name' => 'task',
				'cTask' => $cTask,
			]);

			return;

		}

		self::resolveDuration($reading, $eTask, $answers);

	}

	/**
	 * L'intervention retenue, puis la durée. `created` dit que l'intervention n'existe pas encore : l'écriture
	 * la crée (`resolveAdd()`).
	 *
	 * `dates` dit les jours qui reçoivent la durée, chacun la durée entière, parmi les jours de la semaine du jour
	 * dit (`getDays()`) : ceux que les puces choisissent (`$answers['days']`), et le jour dit quand la phrase le
	 * tape (« hier », « jeudi »). Sans jour tapé, aujourd'hui n'est qu'un choix par défaut, que la première puce
	 * choisie remplace (HN-B17). Un jour posté hors de ces jours ne s'écrit pas, la semaine ayant changé avec le
	 * jour dit. Les puces vont sur la lecture (`days`), vides quand la semaine n'offre que le jour dit, dès que
	 * l'intervention est retenue : les jours se choisissent avant la durée comme après elle.
	 */
	private static function resolveDuration(array &$reading, \series\Task $eTask, array $answers): void {

		$reading['task'] = $eTask;

		$days = self::getDays($reading['date']);
		$dates = self::getDates($reading, $answers);

		$reading['days'] = (count($days) > 1) ? array_map(fn(string $day) => ['date' => $day, 'selected' => in_array($day, $dates, TRUE)], $days) : [];

		$minutes = $reading['complements']['duration'];

		// Sans durée, ou une durée encore en frappe (`DurationComplementLib::TYPING`), que `isComplete()` arrête.
		if(is_int($minutes) === FALSE) {
			$reading['outcome'] = EntryLib::ASK;
			return;
		}

		if($eTask->exists() === FALSE) {
			self::fillNameWithSeason($eTask);
		}

		$reading['confirmation'] = [
			'minutes' => $minutes,
			'date' => $reading['date'],
			'dates' => $dates,
			'eTask' => $eTask,
			'created' => ($eTask->exists() === FALSE),
		];

		$reading['outcome'] = EntryLib::WRITE;

	}

	/**
	 * Une intervention de culture se crée sur une action de la catégorie Culture (`$eCategory`), seule à porter
	 * une espèce (`\series\Task::action.usable`). Les autres gardent le refus : la semaine n'en a pas. La coche
	 * en crée aussi (`DoneIntentionLib`).
	 */
	public static function isAddable(\work\Category $eCategory, \work\Action $eAction): bool {

		return ($eCategory->notEmpty() and in_array($eCategory['id'], $eAction['categories']));

	}

	/**
	 * Les jours qui reçoivent la durée, dans l'ordre de la semaine : le jour dit quand la phrase le tape ou
	 * qu'aucune puce n'est choisie, et les puces choisies (`resolveDuration()`).
	 */
	private static function getDates(array $reading, array $answers): array {

		$days = self::getDays($reading['date']);
		$daysChosen = array_values(array_filter($days, fn(string $day) => in_array($day, $answers['days'], TRUE)));

		$dated = ($reading['complementsNamed']['date'] !== [] or $daysChosen === []);

		return array_values(array_filter($days, fn(string $day) => (($dated and $day === $reading['date']) or in_array($day, $daysChosen, TRUE))));

	}

	/**
	 * « fertilisation tomate 1 h » sans fertilisation de tomate cette semaine : l'intervention s'ajoute au
	 * planning, comme la récolte ajoute la sienne (lot 2c). La question propose les cultures de
	 * l'espèce (`CultivationResolveLib::getByPlant()`), puis « Hors série », seule ligne d'une espèce sans
	 * culture. Elle se pose même pour une seule culture : le temps ne choisit pas la série à la place de
	 * la personne, la récolte ayant au contraire une intervention à compléter.
	 *
	 * « Hors série » répond par l'id de l'espèce : une autre espèce tapée ensuite repose la question.
	 *
	 * L'intervention se construit dès la lecture, comme celle d'une récolte neuve
	 * (`\planning\HarvestWeekLib::buildTask()`) : un refus de `build()` vaut pour la lecture comme pour
	 * l'écriture. Elle est à faire, planifiée au jour dit, et le temps la laisse à faire.
	 */
	private static function resolveAdd(\structure\Structure $eStructure, array &$reading, array $answers, \plant\Plant $ePlant): void {

		$eCultivation = self::resolveSeries($eStructure, $reading, $answers, $ePlant);

		if($reading['question'] !== NULL) {
			return;
		}

		$fw = new \FailWatch();

		// Prévue au premier jour où le temps s'écrit, que ce soit le jour dit ou une puce qui l'a remplacé (
		// 24/10).
		$eTask = self::buildTask($eStructure, $reading['object']['action'], $ePlant, $eCultivation, self::getDates($reading, $answers)[0]);

		$fw->validate();

		self::resolveDuration($reading, $eTask, $answers);

	}

	/**
	 * La culture où ajouter l'intervention de l'espèce, que la réponse à la question `series` choisit parmi les
	 * cultures de l'espèce (`CultivationResolveLib::getByPlant()`), vide pour « Hors série ». Sans réponse qui
	 * vaille, la question se pose, et la lecture la porte (`question`). L'intervention de la saisie
	 * (`InterventionIntentionLib`) la pose comme le temps.
	 */
	public static function resolveSeries(\structure\Structure $eStructure, array &$reading, array $answers, \plant\Plant $ePlant): \series\Cultivation {

		$cCultivation = CultivationResolveLib::getByPlant($eStructure, $ePlant, $reading['date']);

		if($answers['addCultivation'] !== NULL and $cCultivation->offsetExists($answers['addCultivation'])) {
			return $cCultivation[$answers['addCultivation']];
		}

		if($answers['addNoSeries'] === $ePlant['id']) {
			return new \series\Cultivation();
		}

		HarvestIntentionLib::fillPlaces($cCultivation);

		EntryLib::ask($reading, [
			'name' => 'series',
			'ePlant' => $ePlant,
			'cCultivation' => $cCultivation,
		]);

		return new \series\Cultivation();

	}

	/**
	 * La question de l'espèce sous l'action seule (`getSeasonPlants()`), posée par le temps et par la coche.
	 * Un début de nom tapé après l'action (« fra ») la borne aux espèces qu'il lit (`$cPlantRead`, de
	 * `readPlants()`). Sans espèce à proposer, elle n'est pas posée, et l'appelant garde son refus.
	 */
	public static function askPlant(\structure\Structure $eStructure, array &$reading, \Collection $cPlantRead = new \Collection()): bool {

		$cPlant = self::getSeasonPlants($eStructure, $reading['object']['action'], $reading['date']);

		if($cPlantRead->notEmpty()) {
			$cPlant = $cPlant->find(fn(\plant\Plant $ePlant) => $cPlantRead->offsetExists($ePlant['id']));
		}

		if($cPlant->empty()) {
			return FALSE;
		}

		EntryLib::ask($reading, ['name' => 'plant', 'cPlant' => $cPlant]);

		return TRUE;

	}

	/**
	 * Les espèces que la question de l'action seule propose, rangées par nom : celles des cultures que la série
	 * où ajouter l'intervention proposerait (`CultivationResolveLib::getByPlant()`), sur une série ouverte des
	 * saisons qui entourent le jour dit. Chacune porte `acted`, vrai quand une intervention de l'action la vise
	 * dans ces saisons : la liste les montre en tête (« Semis direct cette saison »).
	 */
	public static function getSeasonPlants(\structure\Structure $eStructure, \work\Action $eAction, string $date): \Collection {

		$seasons = CultivationResolveLib::getSeasons($date);

		$cCultivation = \series\Cultivation::model()
			->select([
				'plant' => ['name', 'fqn'],
				'series' => ['status'],
			])
			->whereStructure($eStructure)
			->whereSeason('IN', $seasons)
			->getCollection()
			->find(fn(\series\Cultivation $eCultivation) => $eCultivation['series']['status'] === \series\Series::OPEN);

		$acted = \series\Task::model()
			->select(['plant'])
			->whereStructure($eStructure)
			->whereAction($eAction)
			->whereSeason('IN', $seasons)
			->wherePlant('!=', NULL)
			->getColumn('plant')
			->getIds();

		$cPlant = new \Collection();

		foreach($cCultivation as $eCultivation) {

			$ePlant = $eCultivation['plant'];

			if($cPlant->offsetExists($ePlant['id']) === FALSE) {
				$ePlant['acted'] = in_array($ePlant['id'], $acted, TRUE);
				$cPlant[$ePlant['id']] = $ePlant;
			}

		}

		return $cPlant->sort(fn(\plant\Plant $e1, \plant\Plant $e2) => \L::getCollator()->compare($e1['name'], $e2['name']));

	}

	/**
	 * Les jours où le temps peut s'écrire dans la semaine du jour dit, du lundi au dimanche : ceux que
	 * `getDateRefusal()` admet, donc ni à venir ni au-delà de `EntrySetting::PAST_DAYS`.
	 */
	public static function getDays(string $date): array {
		return array_values(array_filter(week_dates(toWeek($date)), fn(string $day) => self::getDateRefusal($day) === NULL));
	}

	/**
	 * Construite comme `\planning\TaskCreateLib::prepare()` construit une intervention : l'espèce ne se
	 * construit que hors série (`\series\TaskLib::getPropertiesCreate()`), une série la donnant par sa
	 * culture, et l'unité de récolte ne concerne pas une autre action. À faire, elle est prévue au jour dit ;
	 * faite (la coche, `DoneIntentionLib`), elle est faite ce jour-là : le statut choisit la propriété que
	 * `getPropertiesWrite()` construit, donc la clé postée.
	 *
	 * `$input` remplace ou complète ce qui est posté, comme un formulaire le ferait : une intervention à faire
	 * prévue à la semaine seule (`['planned' => ['week' => …]]`), son temps prévu (`timeExpected`, en heures).
	 */
	public static function buildTask(\structure\Structure $eStructure, \work\Action $eAction, \plant\Plant $ePlant, \series\Cultivation $eCultivation, string $date, string $status = \series\Task::TODO, array $input = []): \series\Task {

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'category' => \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE),
			'series' => $eCultivation->empty() ? new \series\Series() : $eCultivation['series'],
			'season' => $eCultivation->empty() ? NULL : $eCultivation['series']['season'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation->empty() ? new \plant\Plant() : $ePlant,
			'status' => $status,
			'harvest' => NULL,
			'harvestUnit' => NULL,
			'harvestDates' => [],
		]);

		$property = match($status) {
			\series\Task::TODO => 'planned',
			\series\Task::DONE => 'done',
		};

		$eTask->build(array_values(array_diff(\series\TaskLib::getPropertiesCreate()($eTask), ['harvestUnit'])), array_replace([
			'action' => $eAction['id'],
			'plant' => $ePlant['id'],
			$property => ['date' => $date],
		], $input), new \PropertyList('create'));

		return $eTask;

	}

	/**
	 * `nameWithSeason` d'une intervention ajoutée au planning, comme la ligne de sa série dans la question
	 * (`CultivationResolveLib::getByPlant()`) : vrai quand la série n'est pas de la saison de la semaine, prévue
	 * ou faite. Une intervention faite construite n'a pas encore de semaine prévue, que seul
	 * `\series\TaskLib::create()` recopie.
	 */
	public static function fillNameWithSeason(\series\Task $eTask): void {

		$week = match($eTask['status']) {
			\series\Task::TODO => $eTask['plannedWeek'],
			\series\Task::DONE => $eTask['doneWeek'],
		};

		$eTask['nameWithSeason'] = (
			$eTask['series']->notEmpty() and
			$eTask['series']['season'] !== week_year($week)
		);

	}

	/**
	 * L'intervention existante est relue entière : `writeByTasks()` lit sa série pour la répartition. Celle
	 * qui s'ajoute au planning se contrôle comme une intervention neuve. Le temps se contrôle ensuite
	 * (`prepareTime()`), jour par jour : chaque journée a sa borne de 24 heures.
	 */
	public static function prepare(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		if($confirmation['created']) {

			$eTask = $confirmation['eTask'];
			$eTask->validate('canCreate', 'acceptCreate');

		} else {

			$eTask = \series\Task::model()
				->select(\series\Task::getSelection())
				->whereId($confirmation['eTask']['id'])
				->whereStructure($eStructure)
				->get();

			$eTask->validate('canRead');

			// Le retard se dit au jour dit, qu'aucune colonne ne porte : la pastille le relit (`getTaskSub()`).
			$eTask['late'] = self::isLate($eTask, $reading['date']);

		}

		$reading['cTimesheet'] = new \Collection();

		foreach($confirmation['dates'] as $date) {
			$reading['cTimesheet'][] = self::prepareTime($eStructure, $eTask, $date, $confirmation['minutes']);
		}

		$reading['confirmation']['eTask'] = $eTask;

	}

	/**
	 * Le temps de la personne connectée sur l'intervention, construit et contrôlé dans l'ordre de
	 * `HarvestWeekLib::writeEntry()` : la personne, puis la journée de 24 heures, puis le droit d'écrire ce
	 * temps. Celui du temps seul, et celui que la coche écrit avec elle (`DoneIntentionLib`, lot S4c).
	 */
	public static function prepareTime(\structure\Structure $eStructure, \series\Task $eTask, string $date, int $minutes): \series\Timesheet {

		$fw = new \FailWatch();

		$cTimesheet = \planning\HarvestWeekLib::buildTime($eStructure, $eTask, $date, 'add', [
			\user\ConnectionLib::getOnline()['id'] => DurationComplementLib::getHours($minutes)
		]);

		$fw->validate();

		\planning\HarvestWeekLib::checkTimeDay($eStructure, $eTask, $cTimesheet, 'add');

		$fw->validate();

		$cTimesheet->validate('canWrite', 'acceptWrite');

		return $cTimesheet->first();

	}

	/**
	 * Sur une seule intervention en répartition « fair », `writeByTasks()` ne refuse rien : ses deux refus
	 * portent sur la surface de la série et sur une répartition au récolté.
	 */
	public static function write(\structure\Structure $eStructure, array &$reading): void {

		$eTask = $reading['confirmation']['eTask'];

		// Relue entière une fois créée, comme l'existante l'est par `prepare()`.
		if($reading['confirmation']['created']) {

			\series\TaskLib::create($eTask);

			$eTask = \series\Task::model()
				->select(\series\Task::getSelection())
				->whereId($eTask['id'])
				->get();

			self::fillNameWithSeason($eTask);

		}

		foreach($reading['cTimesheet'] as $eTimesheet) {
			\series\TimesheetLib::writeByTasks(new \Collection([$eTask['id'] => $eTask]), $eTimesheet, $eTimesheet['time'], 'fair');
		}

		$reading['taskTouched'] = $reading['confirmation']['created'] ? CancelLib::getTouched($eTask) : NULL;
		$reading['confirmation']['eTask'] = $eTask;
		$reading['eHarvest'] = new \series\Harvest();

	}

	public static function afterCommit(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		$reading['undo'] = ['intention' => 'time', 'ticket' => self::setCancel($eStructure, $confirmation['eTask'], $confirmation['dates'], $confirmation['minutes'], $confirmation['created'], $reading['taskTouched'])];

	}

	public static function getModel(): \ModuleModel {
		return \series\Timesheet::model();
	}

	/**
	 * Le ticket d'un temps écrit par la saisie, posé une fois l'écriture validée, et son jeton, que le lien
	 * « Annuler » de la pastille poste. `$dates` dit les jours qui ont reçu chacun `$minutes`. `created` dit
	 * que la saisie a créé l'intervention (un temps seul sur une récolte qui n'avait pas encore la sienne, ou
	 * sur une intervention ajoutée au planning), `taskTouched` son empreinte une fois écrite.
	 */
	public static function setCancel(\structure\Structure $eStructure, \series\Task $eTask, array $dates, int $minutes, bool $created, ?string $taskTouched): string {

		$ticket = bin2hex(random_bytes(16));

		\Cache::redis()->set(self::getCancelKey($eStructure, $ticket), [
			'user' => \user\ConnectionLib::getOnline()['id'],
			'task' => $eTask['id'],
			'dates' => $dates,
			'minutes' => $minutes,
			'created' => $created,
			'taskTouched' => $taskTouched,
		], EntrySetting::CANCEL_DELAY);

		return $ticket;

	}

	/**
	 * Les espèces que les mots de reste nomment : celle qu'ils nomment tout entiers (« laitue »), sinon
	 * celles dont le nom commence par eux (« toma », en frappe). Aucune quand d'autres mots les suivent,
	 * que le moteur refuse comme incompris.
	 *
	 * Le reste a perdu ses liaisons, que le moteur retire après l'objet (`EntryLib::read()`) : les noms
	 * d'espèce se comparent sans les leurs, sinon « Pomme de terre » ne serait jamais lue.
	 *
	 * La coche (`DoneIntentionLib`) lit l'espèce de la même façon, la phrase étant celle du temps.
	 */
	public static function readPlants(\structure\Structure $eStructure, string $pending): \Collection {

		$cPlant = \plant\PlantLib::getByStructure($eStructure, properties: ['id', 'name', 'fqn']);
		$cPlantUnlinked = self::getUnlinked($cPlant);

		$words = EntryLib::split($pending);

		$key = self::readName($cPlantUnlinked, $words);

		if($key !== NULL and $words === []) {
			return new \Collection([$key => $cPlant[$key]]);
		}

		$cPlantFound = self::findStarting($cPlantUnlinked, EntryLib::split($pending));

		return $cPlant->find(fn(\plant\Plant $ePlant) => $cPlantFound->offsetExists($ePlant['id']));

	}

	/**
	 * Une copie des éléments, nommés sans leurs liaisons, pour `readName()`.
	 */
	private static function getUnlinked(\Collection $cElement): \Collection {

		$cElementUnlinked = new \Collection();

		foreach($cElement as $key => $eElement) {

			$eElementUnlinked = clone $eElement;
			$eElementUnlinked['name'] = implode(' ', EntryLib::withoutLinks(EntryLib::split($eElement['name'])));

			$cElementUnlinked[$key] = $eElementUnlinked;

		}

		return $cElementUnlinked;

	}

	/**
	 * Les éléments dont le nom commence par ces mots, liaisons ignorées de part et d'autre : « désherbage de
	 * chou » commence « Désherbage chou fleur ».
	 */
	private static function findStarting(\Collection $cElement, array $words): \Collection {

		$search = implode(' ', EntryLib::withoutLinks($words));

		return $cElement->find(fn(\Element $eElement) => str_starts_with(implode(' ', EntryLib::withoutLinks(EntryLib::split($eElement['name']))), $search));

	}

	public static function getCancelKey(\structure\Structure $eStructure, string $ticket): string {
		return 'entry-cancel-time-'.$eStructure['id'].'-'.$ticket;
	}

	public static function getCancellation(\structure\Structure $eStructure, string $ticket): ?array {

		$eTask = self::getCancellable($eStructure, $ticket);

		return $eTask->empty() ? NULL : self::prepareCancel($eStructure, $eTask);

	}

	public static function getCancelFail(): string {
		return 'entry\Entry::time.cancellable';
	}

	/**
	 * L'intervention dont son auteur peut encore annuler le temps : le jeton désigne un ticket vivant, qui
	 * porte l'utilisateur connecté et une intervention de la structure. Le ticket est porté par l'intervention
	 * rendue (`ticket`), avec sa clé (`key`).
	 */
	public static function getCancellable(\structure\Structure $eStructure, string $ticket): \series\Task {

		if(preg_match('/^[0-9a-f]{32}$/', $ticket) !== 1) {
			return new \series\Task();
		}

		$key = self::getCancelKey($eStructure, $ticket);
		$value = \Cache::redis()->get($key);

		if($value === FALSE or $value['user'] !== \user\ConnectionLib::getOnline()['id']) {
			return new \series\Task();
		}

		$eTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->whereId($value['task'])
			->whereStructure($eStructure)
			->get();

		if($eTask->notEmpty()) {
			$eTask['ticket'] = $value + ['key' => $key];
		}

		return $eTask;

	}

	/**
	 * Tout ce que l'annulation défera, lu et contrôlé hors transaction :
	 * - `eTask` et `status` : l'intervention et son statut avant l'annulation, qui dit à la réponse si elle
	 *   était dans À faire ;
	 * - `eHarvest` : vide, l'annulation d'une récolte portant la sienne au même endroit ;
	 * - `eUser`, `dates` et `minutes` : l'auteur, les jours et la durée de chacun que la pastille a annoncés,
	 *   et `date`, le dernier de ces jours, dont la semaine est celle de tous ;
	 * - `times` : le temps en heures que l'annulation retire, par jour, jamais plus que ce que la personne a
	 *   encore ce jour-là sur l'intervention (`CancelLib::removeTime()`), et `time`, leur somme ;
	 * - `created` : la saisie a créé l'intervention et rien ne s'y est ajouté depuis, elle part avec son temps.
	 */
	public static function prepareCancel(\structure\Structure $eStructure, \series\Task $eTask): array {

		$eTask->validate('canWrite');

		$ticket = $eTask['ticket'];
		$eUser = new \user\User(['id' => $ticket['user']]);

		$times = [];

		foreach($ticket['dates'] as $date) {
			$times[$date] = min(DurationComplementLib::getHours($ticket['minutes']), \series\TimesheetLib::getTimeByDate($eTask, $eUser, $date));
		}

		return [
			'eTask' => $eTask,
			'eHarvest' => new \series\Harvest(),
			'status' => $eTask['status'],
			'eUser' => $eUser,
			'date' => end($ticket['dates']),
			'dates' => $ticket['dates'],
			'minutes' => $ticket['minutes'],
			'times' => $times,
			'time' => array_sum($times),
			'created' => ($ticket['created'] and CancelLib::isCreatedBy($eTask, $eUser, $times, $ticket['taskTouched'])),
			'key' => $ticket['key'],
		];

	}

	/**
	 * Annule dans la transaction ouverte par `EntryLib::cancel()`. Le ticket se retire d'abord, et
	 * c'est lui qui départage deux annulations simultanées : sans récolte à supprimer, rien d'autre en base
	 * ne dit qu'une autre est passée. Une seule le retire, l'autre n'écrit rien. Une écriture qui lèverait
	 * ensuite laisse le temps en place et le ticket retiré : l'annulation ne se rejoue pas, le temps se
	 * corrige alors par la fiche de temps.
	 */
	public static function cancel(array $cancellation): bool {

		if(\Cache::redis()->delete($cancellation['key']) === FALSE) {
			return FALSE;
		}

		$eTask = $cancellation['eTask'];

		if($cancellation['created']) {
			\series\TaskLib::delete($eTask);
		} else {
			foreach($cancellation['times'] as $date => $time) {
				CancelLib::removeTime($eTask, $cancellation['eUser'], $date, $time);
			}
		}

		return TRUE;

	}

	/**
	 * Les interventions sur lesquelles poser du temps : celles de la semaine que `TaskLib::getForWeekExport()`
	 * rassemble, hors récolte et hors série close tant qu'elles sont à faire (`TaskLib::applyOpenSeries()`).
	 *
	 * S'y ajoutent celles restées à faire des semaines passées (HN-B7), depuis la semaine du jour le plus ancien
	 * que la saisie admet (`getPastLimit()`) : un temps ne s'écrit pas plus loin, une intervention plus ancienne se
	 * retrouve par le planning.
	 */
	private static function getTasks(\structure\Structure $eStructure, string $week, \work\Action $eActionHarvest): \Collection {

		$cTaskTimesheet = \series\TimesheetLib::getTasksByWeek($eStructure, $week);
		$weekOldest = toWeek(self::getPastLimit());

		\series\TaskLib::applyOpenSeries();

		return \series\Task::model()
			->select([
				'id',
				'action' => ['name', 'fqn'],
				'plant' => ['name', 'fqn'],
				'series' => ['name'],
				'cultivation', 'description', 'status', 'plannedWeek', 'doneWeek',
			])
			->whereStructure($eStructure)
			->whereAction('!=', $eActionHarvest, if: $eActionHarvest->notEmpty())
			->or(
				fn() => $this->whereId('IN', $cTaskTimesheet),
				fn() => $this
					->wherePlannedWeek('>=', $weekOldest)
					->wherePlannedWeek('<=', $week)
					->whereStatus(\series\Task::TODO),
				fn() => $this
					->whereStatus(\series\Task::DONE)
					->whereDoneWeek($week),
			)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Une intervention à faire que le temps propose au jour dit (`getTasks()`) : de la semaine du jour dit, ou en
	 * retard depuis la semaine du jour le plus ancien qu'un temps admet.
	 */
	public static function isProposed(\series\Task $eTask, string $date): bool {
		return ($eTask['plannedWeek'] === toWeek($date) or (self::isLate($eTask, $date) and $eTask['plannedWeek'] >= toWeek(self::getPastLimit())));
	}

	/**
	 * Restée à faire d'une semaine passée, au jour dit. Une intervention sans semaine prévue n'est pas en retard,
	 * elle n'a jamais été prévue.
	 */
	public static function isLate(\series\Task $eTask, string $date): bool {
		return ($eTask['status'] === \series\Task::TODO and $eTask['plannedWeek'] !== NULL and $eTask['plannedWeek'] < toWeek($date));
	}

	private static function getKey(\series\Task $eTask): string {
		return $eTask['action']['id'].'-'.($eTask['plant']->empty() ? '' : $eTask['plant']['id']);
	}

}
?>
