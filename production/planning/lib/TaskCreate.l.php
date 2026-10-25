<?php
namespace planning;

/**
 * Une intervention neuve du nouveau planning : « Nouvelle intervention » de la Semaine, du Jour, de l'Année
 * et du Temps de travail (`doCreate`, faite ou à faire, retour 244), dont l'état est fixé par l'entrée qui
 * l'ouvre (piste F de R-CreationRapide), Fait pour « J'ai aussi fait » (retour 293).
 *
 * Elle se construit comme une intervention créée depuis des séries (`series/page/task.p.php`,
 * `doCreateFromSeriesCollection`) : la série, la saison et l'espèce viennent de la culture choisie, lue
 * par son identifiant et bornée à la structure ; la catégorie est postée et bornée à la structure ; le
 * reste passe par `build()` avec les propriétés de création.
 */
class TaskCreateLib {

	/**
	 * L'action posée par le « + » d'un bloc, cherchée parmi celles que le panneau propose
	 * (`TaskDayLib::getCategoriesForCreate()`), et sa catégorie, la première qui la porte. Vides sans action.
	 *
	 * @return array [\work\Action, \work\Category]
	 */
	public static function getAction(\structure\Structure $eStructure, ?int $action): array {

		if($action === NULL) {
			return [new \work\Action(), new \work\Category()];
		}

		foreach(TaskDayLib::getCategoriesForCreate($eStructure) as $eCategory) {

			foreach($eCategory['cAction'] as $eAction) {

				if($eAction['id'] === $action) {
					return [$eAction, $eCategory];
				}

			}

		}

		throw new \NotExpectedAction('Action not offered');

	}

	/**
	 * L'intervention de « Nouvelle intervention », sur laquelle se posent les champs de l'ancien formulaire qui
	 * dépendent de l'action (`TaskCreateUi::getFields()`, `getWorkFields()`) : « Méthodes de travail » et
	 * « Matériel nécessaire » (retour 255), le produit ou les intrants, la fertilisation et le traitement (retour
	 * 256), ce que l'action et sa catégorie en proposent, rien sans action. Quand l'action change dans le panneau,
	 * `series/task:getFields` redessine les champs. « Espèce ou série » part vide, sauf ouvert depuis la fiche d'une
	 * planche, qui y pose les cultures de sa série (`cCultivationChosen`, `HarvestWeekLib::getForTaskSeries()`).
	 */
	public static function getNew(\structure\Structure $eStructure, \work\Action $eAction, \work\Category $eCategory = new \work\Category()): \series\Task {

		return new \series\Task([
			'structure' => $eStructure,
			'action' => $eAction,
			'category' => $eCategory,
			'description' => NULL,
			'hasMethods' => \work\MethodLib::getForWork($eStructure, $eAction),
			'cMethod?' => fn() => new \Collection(),
			'hasTools' => \work\ToolLib::getForWork($eStructure, $eAction),
			'cTool?' => fn() => new \Collection(),
			'hasProducts' => \work\ToolLib::getProductsForWork($eStructure, $eAction),
			'cProduct?' => fn() => new \Collection(),
			'cCultivationChosen' => new \Collection(),
		]);

	}

	/**
	 * Les champs de l'ancien formulaire qui dépendent des choix d'« Espèce ou série » (retour 256), que
	 * `taskDay:getCultivationFields` redessine à chaque choix :
	 * - les variétés des cultures choisies, celles qu'elles n'ont pas toutes désactivées, comme pour plusieurs séries
	 *   dans l'ancien formulaire (`series/task:getCreateCollectionFields`) ; aucune dès qu'une espèce hors série est
	 *   choisie, qu'aucune variété ne désigne (`Task::variety`) ; une culture dite « Partagé » n'en apporte aucune,
	 *   l'intervention de sa série n'ayant pas de culture (`prepare()`), donc aucune quand toutes le sont ;
	 * - les calibres, quand tous les choix sont d'une seule espèce, une espèce hors série comprise
	 *   (`createFromScratch()`) ;
	 * - les cultures dont la série en compte plusieurs, qui peuvent laisser la place à une intervention
	 *   « Partagé » de la série (`TaskCreateUi::getShared()`).
	 *
	 * Chaque culture et chaque espèce sont bornées à la structure.
	 *
	 * @param \work\Category $eCategory La catégorie sous laquelle l'action est choisie : hors culture, la culture choisie
	 * sera refusée (`prepare()`), et « Partagé » se cache (`TaskCreateUi::getSharedFields()`), comme la variété
	 * (`TaskCreateUi::getFields()`, `PlanningTaskAction.toggle()`).
	 * @param array $shared Le choix « Partagé » posté par culture (`shared[<culture>]`), comme le lit `prepare()`.
	 */
	public static function getNewForChoices(\structure\Structure $eStructure, \work\Action $eAction, \work\Category $eCategory, array $values, array $shared = []): \series\Task {

		$cultivations = [];
		$plants = [];

		foreach($values as $value) {

			['cultivation' => $cultivation, 'plant' => $plant] = self::readChoice($value);

			if($cultivation !== NULL) {
				$cultivations[] = $cultivation;
			} else if($plant !== NULL) {
				$plants[] = $plant;
			}

		}

		$cCultivation = \series\CultivationLib::getByIds(array_values(array_unique($cultivations)), [
			'id', 'structure',
			'series' => ['name', 'plants'],
			'plant' => ['name', 'fqn', 'vignette'],
		], index: 'id');

		foreach($cCultivation as $eCultivation) {

			$eCultivation
				->validate('canWrite')
				->validateProperty('structure', $eStructure);

		}

		$cPlant = \plant\PlantLib::getByIds(array_values(array_unique($plants)), index: 'id');

		foreach($cPlant as $ePlant) {
			$ePlant->validateProperty('structure', $eStructure);
		}

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'action' => $eAction,
			'category' => $eCategory,
			'cVariety' => new \Collection(),
			'varietiesIntersect' => [],
			'cSize' => new \Collection(),
			'cCultivationShared' => $cCultivation->find(fn(\series\Cultivation $eCultivation) => $eCultivation['series']['plants'] > 1),
		]);

		$cCultivationVariety = $cCultivation->find(fn(\series\Cultivation $eCultivation) => (
			$eTask['cCultivationShared']->offsetExists($eCultivation['id']) === FALSE or
			var_filter($shared[$eCultivation['id']] ?? NULL, 'bool') === FALSE
		));

		if($cPlant->empty() and $cCultivationVariety->notEmpty()) {
			$eTask->merge(\series\SliceLib::getVarietiesByCultivations($cCultivationVariety));
		}

		$cPlantChosen = new \Collection();

		foreach($cCultivation as $eCultivation) {
			$cPlantChosen[$eCultivation['plant']['id']] = $eCultivation['plant'];
		}

		$cPlantChosen->appendCollection($cPlant);

		if($cPlantChosen->count() === 1) {
			$eTask['cSize'] = \plant\SizeLib::getByStructureAndPlant($eStructure, $cPlantChosen->first());
		}

		return $eTask;

	}

	/**
	 * Les pastilles sous le champ « Intervention » (piste F de R-CreationRapide) : les actions les plus fréquentes
	 * (`TaskDayLib::getActionsFrequent()`), parmi celles que le champ propose, chacune avec la catégorie qui part
	 * avec elle. Aucune quand l'action est posée par le « + » d'un bloc : le panneau n'a alors pas de champ.
	 */
	public static function getShortcuts(\structure\Structure $eStructure, \work\Action $eAction): \Collection {

		if($eAction->notEmpty()) {
			return new \Collection();
		}

		$cCategory = TaskDayLib::getCategoriesForCreate($eStructure);

		return TaskDayLib::getActionsFrequent($eStructure, $cCategory, PlanningSetting::TASK_CREATE_SHORTCUTS);

	}

	/**
	 * Les semaines de « Quand » (piste F) autour de la semaine en cours, sur `\Clock` et non sur l'écran : la
	 * suivante pour À faire, la précédente pour Fait, celle qu'on vient de finir. Une semaine hors des saisons de
	 * la structure n'est pas proposée, `createCollection()` la refuserait.
	 *
	 * @return array [semaine => `current`, `next` ou `previous`]
	 */
	public static function getWeeks(\structure\Structure $eStructure, string $status): array {

		$weekCurrent = \Clock::week();

		$weeks = [
			$weekCurrent => 'current',
			toWeek(week_date_starts($weekCurrent).match($status) {
				\series\Task::TODO => ' + 7 days',
				\series\Task::DONE => ' - 7 days',
			}) => match($status) {
				\series\Task::TODO => 'next',
				\series\Task::DONE => 'previous',
			},
		];

		return array_filter($weeks, fn(string $week) => $eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($week)), ARRAY_FILTER_USE_KEY);

	}

	/**
	 * Les jours du panneau (`ReminderLib::getDays()`), chacun avec le statut qu'il propose (`status`, retour
	 * 244) : un jour passé est fait, aujourd'hui et un jour à venir sont à faire. Aujourd'hui à faire est une
	 * hypothèse de V-Planning, lecture littérale de « date dans le passé : fait ». Chacun dit aussi s'il peut
	 * porter une intervention faite (`done`, retour 258) : pas un jour à venir, que `createCollection()` refuse.
	 * Aujourd'hui est enfin le jour que Fait prend quand aucun jour qui peut l'être n'est choisi (`doneDefault`,
	 * arbitrage de V-Planning) : une semaine passée n'en a aucun, faute de savoir lequel de ses jours.
	 */
	public static function getDays(string $week): array {

		$today = \Clock::date();

		$days = ReminderLib::getDays($week);

		foreach($days as $date => $day) {
			$days[$date]['status'] = ($date < $today) ? \series\Task::DONE : \series\Task::TODO;
			$days[$date]['done'] = ($date <= $today);
			$days[$date]['doneDefault'] = ($date === $today);
		}

		return $days;

	}

	/**
	 * Le jour qu'une nouvelle intervention de la Semaine prend à l'ouverture du panneau : aujourd'hui quand la
	 * semaine le contient (`getDays()`, sur `\Clock`), aucun sur une semaine passée ou à venir. Ce qu'on ajoute
	 * à la semaine en cours depuis le planning, on vient en général de le faire : le panneau s'ouvre alors sur
	 * Fait, aujourd'hui (retour 392). Une semaine passée n'a pas de jour à proposer, une semaine à venir aucun
	 * jour qui puisse être fait.
	 */
	public static function getDateToday(array $days): ?string {

		foreach($days as $date => $day) {

			if($day['doneDefault']) {
				return $date;
			}

		}

		return NULL;

	}

	/**
	 * Une semaine peut porter une intervention faite tant qu'un de ses jours est passé ou est aujourd'hui (`getDays()`).
	 */
	public static function hasDone(string $week): bool {

		foreach(self::getDays($week) as $day) {

			if($day['done']) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Le jour posé à l'ouverture du panneau : aujourd'hui dans la semaine en cours (`getDateToday()`, retour 392).
	 * « + Fait », qui demande Fait (`$status`), ouvre une semaine passée sur son dernier jour, le plus proche de ce
	 * que l'on vient de faire. « + À faire » s'ouvre sur « Non défini » (piste F de R-CreationRapide) : ce qu'on
	 * prévoit a rarement déjà son jour.
	 */
	public static function getDateDefault(array $days, ?string $status): ?string {

		if($status === \series\Task::TODO) {
			return NULL;
		}

		$date = self::getDateToday($days);

		if($date === NULL and $status === \series\Task::DONE) {

			$date = array_key_last($days);

			if($days[$date]['done'] === FALSE) {
				return NULL;
			}

		}

		return $date;

	}

	/**
	 * L'état du panneau, qu'il ne change plus une fois ouvert (piste F de R-CreationRapide) : À faire quand il est
	 * demandé (`$status`, « + À faire »), sinon celui que propose le jour posé par l'adresse (`getDays()`), À faire
	 * sans jour. Fait peut être demandé par « + Fait », par « J'ai aussi fait » (retour 293) et par la Semaine en
	 * cours (`getDateToday()`, retour 392) ; il ne vaut que pour un jour qui peut le porter : un jour à venir reste
	 * à faire.
	 */
	public static function getStatus(array $days, ?string $date, ?string $status): string {

		if($status === \series\Task::TODO or $date === NULL) {
			return \series\Task::TODO;
		}

		if($status === \series\Task::DONE and $days[$date]['done']) {
			return \series\Task::DONE;
		}

		return $days[$date]['status'];

	}

	/**
	 * Les personnes du temps d'une intervention faite (retour 245), celles de la saisie d'une récolte dans la
	 * semaine de l'écran (`HarvestWeekLib::getTimeTeam()`, retour 254), dans la forme qu'en attend sa table
	 * (`HarvestEntryUi::getPeople()`) : la personne connectée montrée, les autres derrière « Ajouter une
	 * personne ». Vide quand la structure ne suit pas le temps : le panneau n'a pas de table, et
	 * `TimeTeamLib::prepare()` n'écrit aucun temps.
	 *
	 * Ouvert depuis la fiche de temps d'une personne (`$eUserFirst`, retour 293), le panneau la montre à la
	 * place de la personne connectée, en tête : on écrit le temps de la fiche que l'on regarde. La table
	 * épingle la personne marquée `isOnline` et propose toutes les autres (`HarvestEntryUi::getTimeAdd()`) :
	 * la marque va donc à la personne de la fiche, et la personne connectée passe derrière « Ajouter une
	 * personne ». Une personne sortie de la structure garde son onglet tant qu'elle a une présence dans la
	 * semaine, mais son temps ne s'écrit plus (`TimeTeamLib::getUsersForWeek()`) : la personne connectée
	 * reprend alors la tête.
	 */
	public static function getTimeUsers(\structure\Structure $eStructure, string $week, \user\User $eUserFirst = new \user\User()): \Collection {

		if($eStructure->hasTime() === FALSE) {
			return new \Collection();
		}

		$cUserTeam = HarvestWeekLib::getTimeTeam($eStructure, $week);

		if($eUserFirst->empty() or $cUserTeam->offsetExists($eUserFirst['id']) === FALSE) {
			$eUserFirst = \user\ConnectionLib::getOnline();
		}

		$cUser = new \Collection([
			$eUserFirst['id'] => $cUserTeam[$eUserFirst['id']]
		]);

		foreach($cUserTeam as $userId => $eUser) {

			if($cUser->offsetExists($userId) === FALSE) {
				$cUser[$userId] = $eUser;
			}

		}

		foreach($cUser as $eUser) {

			$eUser['isOnline'] = $eUser->is($eUserFirst);
			$eUser['worked'] = FALSE;
			$eUser['timeLine'] = $eUser['isOnline'] ? 'shown' : 'hidden';

		}

		return $cUser;

	}

	/**
	 * « Nouvelle intervention » dans la semaine choisie sous « Quand » (`$when`), faite ou à faire (`status`, retour 244).
	 *
	 * À faire, au jour choisi (`day`) ou sans jour, avec son temps prévu et sa répétition. Faite, au jour
	 * choisi, qui ne peut être ni absent ni à venir, avec le temps de chaque
	 * personne (`time[<user>]`, `TimeTeamLib::prepare()`). Une récolte peut se créer faite (retour 248), avec ou
	 * sans sa quantité (`prepareHarvest()`, retour 258).
	 *
	 * Une intervention par culture choisie (`cultivation[]`), toutes de la même action, du même jour et de
	 * la même description, chacune avec sa répétition ; aucune culture en donne une seule, hors série. Une
	 * culture dite « Partagé » donne l'intervention de sa série (`prepare()`, retour 256).
	 * Toutes se construisent avant la première écriture, et s'écrivent ensemble ou pas du tout : une culture
	 * refusée n'en laisse créer aucune.
	 */
	public static function createCollection(\structure\Structure $eStructure, string $when, array $input): \Collection {

		if($eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($when)) === FALSE) {
			throw new \NotExpectedAction('Invalid week');
		}

		$day = $input['day'] ?? '';

		if($day !== '' and in_array($day, week_dates($when), TRUE) === FALSE) {
			throw new \NotExpectedAction('Day out of the week');
		}

		$status = var_filter($input['status'] ?? NULL, [\series\Task::TODO, \series\Task::DONE], \series\Task::TODO);

		$fw = new \FailWatch();

		if($status === \series\Task::TODO) {

			$input['planned'] = ($day === '') ? ['week' => $when] : ['date' => $day];

		} else {

			if($day === '') {
				\series\Task::fail('done.day');
			} else if($day > \Clock::date()) {
				\series\Task::fail('done.future');
			}

			$input['done'] = ['date' => $day];

		}

		$fw->validate();

		$choices = [];

		foreach(var_filter($input['cultivation'] ?? NULL, 'array') as $value) {

			['cultivation' => $cultivation, 'plant' => $plant] = self::readChoice($value);

			if($cultivation === NULL and $plant === NULL) {
				continue;
			}

			$choices[] = (string)$value;

		}

		$choices = array_values(array_unique($choices));

		if(count($choices) > \series\SeriesSetting::TASK_CREATE_LIMIT) {
			\series\Task::fail('cultivation.tooMany');
		}

		$fw->validate();

		$cTask = new \Collection();

		// Deux cultures d'une même série dites « Partagé » désignent la même intervention de la série.
		$seriesShared = [];

		foreach(($choices === []) ? [NULL] : $choices as $choice) {

			$input['cultivation'] = $choice;

			$eTask = self::prepare($eStructure, $input, $status);

			$fw->validate();

			if($eTask['shared']) {

				if(in_array($eTask['series']['id'], $seriesShared, TRUE)) {
					continue;
				}

				$seriesShared[] = $eTask['series']['id'];

			}

			$cTask[] = $eTask;

		}

		$cTimesheet = match($status) {
			\series\Task::DONE => TimeTeamLib::prepare($eStructure, TimeTeamLib::getUsersForWeek($eStructure, $when), $day, $input),
			\series\Task::TODO => new \Collection()
		};

		$fw->validate();

		$harvest = self::prepareHarvest($eStructure, $cTask, $status, $day, $input);

		$fw->validate();

		\series\Task::model()->beginTransaction();

		\series\TaskLib::createCollection($cTask);

		TimeTeamLib::createForTasks($cTask, $cTimesheet, $harvest['distribution']);

		// Chaque part préparée porte l'intervention que la ligne du dessus vient de créer : l'écriture la trouve
		// avec son identifiant et ne la recrée pas.
		foreach($harvest['harvests'] as $prepared) {
			\series\TaskLib::writePreparedHarvestForWeek($prepared);
		}

		if($harvest['quantity'] > 0.0) {

			HarvestWeekLib::writeCrate($harvest['ePlantCrate'], $harvest['quantity']);
			HarvestStockLib::write($harvest['eTaskStock'], $harvest['eProductStock'], $harvest['stockRemember'], $harvest['quantity']);

		}

		\series\Task::model()->commit();

		return $cTask;

	}

	/**
	 * Le message d'une création hors de l'écran (M-Planning, 24/10) : la réponse ne redessine que ce que l'écran
	 * montre, et une intervention d'une autre semaine n'y paraît pas. Le message nomme sa semaine, et son année
	 * quand elle n'est pas celle de l'écran ; rien quand l'intervention entre dans l'écran.
	 *
	 * @param array $weeksShown Les semaines que l'écran montre, la sienne ou celles de l'Année.
	 * @return ?array Les options du message (`AlertUi::getSuccess()`), NULL sans message.
	 */
	public static function getSuccessOutside(\Collection $cTask, string $when, array $weeksShown): ?array {

		if(in_array($when, $weeksShown, TRUE)) {
			return NULL;
		}

		return [
			'week' => week_number($when),
			'year' => (week_year($when) === week_year($weeksShown[0])) ? NULL : week_year($when),
			'count' => $cTask->count(),
		];

	}

	/**
	 * La quantité d'une récolte faite (retour 258), saisie comme au tiroir de récolte (`harvestMore`, en caisses
	 * avec `crate`), dans l'unité de la saisie, celle de la première culture ou le kilo pour une espèce seule
	 * (BY-bis), ou dans une autre (`harvestUnit`, retour 319). Elle reste facultative : une récolte faite se crée
	 * sans quantité (retour 248).
	 *
	 * Sur plusieurs séries de la même espèce (DG-5, piste A de la planche planning-dg-plusieurs-series), elle se
	 * partage comme dans « Récolter » : convertie en kilos depuis des caisses, puis partagée à 0,1 près par la
	 * clé postée (`TaskBatchLib::getHarvestDistribution()`), qui partage aussi le temps de chaque personne. Des
	 * séries d'espèces différentes n'ont pas d'unité commune : la quantité s'y ignore, et le temps se partage à
	 * parts égales.
	 *
	 * Chaque part se prépare ici sur l'intervention que `createCollection()` va créer
	 * (`\series\TaskLib::prepareHarvestForWeek()`), avec tous ses refus, avant la transaction : un refus levé
	 * dans une transaction ouverte la laisserait ouverte. Le stock choisi reçoit la quantité entière, comme à
	 * toute saisie de récolte (`HarvestStockLib`, DG-4), lu dans l'unité de la saisie.
	 *
	 * @return array La clé du temps (`distribution`), les parts préparées (`harvests`), l'espèce du poids de caisse
	 * (`ePlantCrate`, `HarvestWeekLib::buildCrate()`), la quantité en kilos ou dans l'unité de la saisie
	 * (`quantity`), la récolte du stock, le stock et son étoile (`HarvestStockLib::build()`).
	 */
	private static function prepareHarvest(\structure\Structure $eStructure, \Collection $cTask, string $status, string $day, array $input): array {

		$harvest = [
			'distribution' => 'fair',
			'harvests' => [],
			'ePlantCrate' => new \plant\Plant(),
			'quantity' => 0.0,
			'eTaskStock' => new \series\Task(),
			'eProductStock' => new \product\Product(),
			'stockRemember' => NULL,
		];

		if($status !== \series\Task::DONE or self::isHarvestOfOnePlant($cTask) === FALSE) {
			return $harvest;
		}

		if($cTask->count() > 1) {

			TaskBatchLib::fillBases($cTask);

			$harvest['distribution'] = TaskBatchLib::getHarvestDistribution($cTask, $input);

		}

		// Sans quantité, la récolte se crée quand même : le champ vide s'écarte avant `build()`.
		if(($input['harvestMore'] ?? '') === '') {
			return $harvest;
		}

		$fw = new \FailWatch();

		$eTaskReference = $cTask->first();

		$eTaskEntry = new \series\Task([
			'structure' => $eStructure,
		]);

		$eTaskEntry->build(['harvestMore', 'harvestUnit'], $input, new \PropertyList('week'));

		$fw->validate();

		[$unitEntry] = TaskBatchLib::getHarvestUnit($cTask);

		if($eTaskEntry['harvestMore'] < 0.0) {
			\series\Task::fail('harvestMore.negative');
		}

		$ePlantCrate = HarvestWeekLib::buildCrate($eTaskReference['plant'], $input);

		$unit = $eTaskEntry['harvestUnit'] ?? $unitEntry;

		if($ePlantCrate->notEmpty() and $unit !== \series\Task::KG) {
			\series\Task::fail('harvestUnit.crate');
		}

		$fw->validate();

		// Une quantité nulle n'écrit rien : chaque intervention garde l'unité qu'elle prendra à sa première quantité.
		if($eTaskEntry['harvestMore'] === 0.0) {
			return $harvest;
		}

		$quantity = HarvestWeekLib::getCrateQuantity($ePlantCrate, $eTaskEntry['harvestMore']);

		TaskBatchLib::shareHarvest($cTask, $harvest['distribution'], $quantity);

		$fw->validate();

		foreach($cTask as $eTask) {

			// Une part nulle, que la clé laisse à une série sans base, n'écrit rien.
			if($eTask['harvestMore'] === 0.0) {
				continue;
			}

			$harvest['harvests'][] = \series\TaskLib::prepareHarvestForWeek($eTask, new \Collection(), $day, 'add', harvestUnit: $unit);

		}

		$eTaskStock = HarvestStockLib::getReference($eTaskReference, $unit);

		[$eProductStock, $stockRemember] = HarvestStockLib::build($eStructure, $eTaskStock, $input);

		$fw->validate();

		return [
			'ePlantCrate' => $ePlantCrate,
			'quantity' => $quantity,
			'eTaskStock' => $eTaskStock,
			'eProductStock' => $eProductStock,
			'stockRemember' => $stockRemember,
		] + $harvest;

	}

	/**
	 * Des récoltes, toutes de la même espèce : celles qu'une seule quantité peut couvrir. L'action, la variété et
	 * le calibre sont ceux du panneau, communs à toutes les interventions qu'il crée.
	 */
	private static function isHarvestOfOnePlant(\Collection $cTask): bool {

		$eTaskReference = $cTask->first();

		if($eTaskReference['action']['fqn'] !== ACTION_RECOLTE or $eTaskReference['plant']->empty()) {
			return FALSE;
		}

		return $cTask->find(fn(\series\Task $eTask) => $eTask['plant']->is($eTaskReference['plant']) === FALSE)->empty();

	}

	/**
	 * Ce que montre le bloc de récolte de « Nouvelle intervention » pour les choix d'« Espèce ou série », que
	 * `taskDay:harvest` rend à chaque choix (DG-5) : celui de « Récolter » (`TaskBatchLib::getHarvestPanel()`),
	 * sur les interventions que le panneau créerait, chacune mesurée par sa culture et la variété choisie. Rien
	 * quand les choix mêlent des espèces, qu'une quantité ne couvre pas.
	 *
	 * Chaque culture et chaque espèce sont bornées à la structure, et la variété aux tranches des cultures.
	 *
	 * @return array Les valeurs du bloc, par leur nom dans la vue `planning/taskDay:harvest`, vide sans espèce commune.
	 */
	public static function getHarvestPanel(\structure\Structure $eStructure, array $values, ?int $variety): array {

		if(count($values) > \series\SeriesSetting::TASK_CREATE_LIMIT) {
			throw new \NotExpectedAction('Too many choices');
		}

		if($values === []) {
			return [];
		}

		$fw = new \FailWatch();

		$eCategory = \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE);
		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);

		$cTask = new \Collection();

		// Un choix posté en tableau n'est pas un choix d'« Espèce ou série » : vide, il se refuse comme tel.
		$values = array_map(fn($value) => var_filter($value, 'string'), $values);

		foreach(array_values(array_unique($values)) as $value) {

			['cultivation' => $cultivation, 'plant' => $plant] = self::readChoice($value);

			$fw->validate();

			if($plant !== NULL) {

				$eCultivation = new \series\Cultivation();

				$ePlant = \plant\PlantLib::getById($plant)
					->validate('canRead')
					->validateProperty('structure', $eStructure);

			} else {

				$eCultivation = \series\CultivationLib::getById($cultivation, [
					'id', 'structure', 'mainUnit', 'density',
					'series' => \series\Series::getSelection(),
					'plant' => ['name', 'fqn', 'vignette'],
				])
					->validate('canWrite')
					->validateProperty('structure', $eStructure);

				$ePlant = $eCultivation['plant'];

			}

			$cTask[] = new \series\Task([
				'structure' => $eStructure,
				'category' => $eCategory,
				'action' => $eAction,
				'series' => $eCultivation->empty() ? new \series\Series() : $eCultivation['series'],
				'cultivation' => $eCultivation,
				'plant' => $ePlant,
				'harvestSize' => new \plant\Size(),
				'harvestUnit' => NULL,
			]);

		}

		if(self::isHarvestOfOnePlant($cTask) === FALSE) {
			return [];
		}

		$eTaskReference = $cTask->first();

		$eVariety = ($variety === NULL) ? new \plant\Variety() : new \plant\Variety(['id' => $variety]);

		foreach($cTask as $eTask) {
			$eTask['variety'] = $eVariety;
		}

		TaskBatchLib::fillBases($cTask);

		// Comme `Task::variety`, la variété se reconnaît à une tranche des cultures choisies, bornées à la
		// structure, et non à son espèce ni à son droit de lecture : « Variétés en mélange » et « Variété non
		// renseignée » n'ont ni espèce ni structure. Une série qui ne la cultive pas la mesure à 0, et l'envoi la
		// refusera sous le champ.
		if($eVariety->notEmpty() and $cTask->find(fn(\series\Task $eTask) => $eTask['cSlice']->offsetExists($eVariety['id']))->empty()) {
			throw new \NotExpectedAction('Variety not grown');
		}

		[$distributions, $distribution] = TaskBatchLib::getHarvestDistributions($cTask);
		[$unit, $unitFree] = TaskBatchLib::getHarvestUnit($cTask);

		$ePlant = $eTaskReference['plant'];
		HarvestWeekLib::prepareCrate($ePlant);

		return [
			'cTask' => $cTask,
			'distributions' => $distributions,
			'distribution' => $distribution,
			'unit' => $unit,
			'ePlant' => $ePlant,
			'tasksByUnit' => $unitFree ? HarvestWeekLib::getEntryTasksForCreate($unit) : [],
			'eTaskStock' => HarvestStockLib::fill(HarvestStockLib::getReference($eTaskReference, $unit)),
		];

	}

	/**
	 * Une intervention de « Nouvelle intervention », pour un seul choix d'« Espèce ou
	 * série » (`cultivation`). Une culture dite « Partagé » (`shared[<culture>]`, `TaskCreateUi::getShared()`,
	 * retour 256) donne, comme dans l'ancien formulaire, une intervention de sa série sans culture ni espèce
	 * (`shared`), qui suppose une série de plusieurs cultures et une autre action que la récolte.
	 */
	public static function prepare(\structure\Structure $eStructure, array $input, string $status): \series\Task {

		$fw = new \FailWatch();

		$eCategory = \work\CategoryLib::getByStructure($eStructure, id: var_filter($input['category'] ?? NULL, 'int'));

		if($eCategory->empty()) {
			\series\Task::fail('action.check');
		}

		$fw->validate();

		['cultivation' => $cultivation, 'plant' => $plant] = self::readChoice($input['cultivation'] ?? NULL);

		$fw->validate();

		if($plant !== NULL) {

			if($eCategory['fqn'] !== CATEGORIE_CULTURE) {
				\series\Task::fail('plant.category');
			}

			$fw->validate();

			$input['plant'] = $plant;

		}

		// Les unités et les poids : une récolte créée recalcule le récolté de sa culture (`TaskLib::recalculateHarvest()`).
		// La densité fonde le partage d'une récolte de plusieurs séries par le nombre de plants (`TaskBatchLib::fillBases()`).
		$eCultivation = \series\CultivationLib::getById($cultivation, [
			'id', 'structure', 'mainUnit', 'unitWeight', 'bunchWeight', 'density',
			'series' => \series\Series::getSelection(),
			'plant' => ['name'],
		]);

		if($cultivation !== NULL and $eCultivation->empty()) {
			\series\Task::fail('cultivation.unknown');
		}

		$fw->validate();

		if($eCultivation->notEmpty()) {

			$eCultivation
				->validate('canWrite')
				->validateProperty('structure', $eStructure);

			if($eCategory['fqn'] !== CATEGORIE_CULTURE) {
				\series\Task::fail('cultivation.category');
			}

			$fw->validate();

		}

		$shared = ($eCultivation->notEmpty() and var_filter($input['shared'][$eCultivation['id']] ?? NULL, 'bool'));

		if($shared) {

			// Comme `doCreateFromSeriesCollection` : une série d'une seule culture n'a pas d'intervention partagée.
			if($eCultivation['series']['plants'] === 1) {
				\series\Task::fail('cultivation.check');
			}

			$fw->validate();

			// Le choix des variétés vaut pour les cultures choisies, et une intervention de la série n'en a pas
			// (`Task::variety`) : celle du panneau ne la concerne pas.
			$input['variety'] = NULL;

		}

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'category' => $eCategory,
			'series' => $eCultivation->empty() ? new \series\Series() : $eCultivation['series'],
			'season' => $eCultivation->empty() ? NULL : $eCultivation['series']['season'],
			'cultivation' => $shared ? new \series\Cultivation() : $eCultivation,
			'plant' => ($eCultivation->empty() or $shared) ? new \plant\Plant() : $eCultivation['plant'],
			'status' => $status,
			'shared' => $shared,
			'harvest' => NULL,
			'harvestUnit' => NULL,
			// Une intervention neuve n'a rien récolté, ce que `\series\TaskLib::fillHarvestDates()` ne peut pas lire
			// en base sans identifiant.
			'harvestDates' => [],
		]);

		$eTask->validate('canCreate');

		// L'unité d'une récolte ne vaut que pour l'intervention qui reçoit la quantité, seule à la connaître
		// (`prepareHarvest()`), et non pour chaque intervention que le panneau crée (plusieurs séries, À faire,
		// une autre action) : elle se construit avec la quantité.
		$eTask->build(array_values(array_diff(\series\TaskLib::getPropertiesCreate()($eTask), ['harvestUnit'])), $input, new \PropertyList('create'));

		$fw->validate();

		// Une récolte se saisit sur une espèce, et `plant` ne se construit pas quand la série est posée
		// (`TaskLib::getPropertiesCreate()`) : le panneau ne propose pas « Partagé » pour une récolte, ce refus le garde.
		if($shared and $eTask['action']['fqn'] === ACTION_RECOLTE) {
			\series\Task::fail('harvestConsistency.plant');
		}

		return $eTask;

	}

	/**
	 * Un choix du champ « Espèce ou série », qui n'a qu'un nom pour deux sortes de valeurs : l'identifiant
	 * d'une culture, ou `plant:<id>` pour une espèce hors série (`TaskDayUi::getAutocompletePlant()`). Une
	 * valeur vide ne choisit rien.
	 *
	 * @return array ['cultivation' => ?int, 'plant' => ?int]
	 */
	public static function readChoice(mixed $value): array {

		$plant = self::readPlant($value);

		if($plant !== NULL) {
			return ['cultivation' => NULL, 'plant' => $plant];
		}

		$cultivation = var_filter($value, '?int');

		// var_filter() rend NULL pour une valeur vide comme pour une valeur illisible, et seule la vide ne choisit rien.
		if(
			($cultivation === NULL and $value !== NULL and $value !== '') or
			($cultivation !== NULL and $cultivation <= 0)
		) {
			\series\Task::fail('cultivation.unknown');
		}

		return ['cultivation' => $cultivation, 'plant' => NULL];

	}

	/**
	 * L'espèce d'un choix d'« Espèce ou série » écrit `plant:<id>`, ou rien.
	 */
	private static function readPlant(mixed $value): ?int {

		if(is_string($value) and preg_match('/^plant:([0-9]+)$/', $value, $match) === 1) {
			return (int)$match[1];
		}

		return NULL;

	}

	/**
	 * Le nom cherché dans ce qu'on a tapé dans « Espèce ou série ». La saison se choisit dans la rangée en tête de
	 * la liste (J-193) : une année de quatre chiffres tapée n'est pas un nom, et sort de la recherche.
	 */
	public static function readName(string $query): string {

		$name = preg_replace('/(?<=^|\s)[0-9]{4}(?=\s|$)/u', '', $query);

		return preg_replace('/\s+/u', ' ', trim($name));

	}

	/**
	 * Ce que « Espèce ou série » a déjà choisi (`exclude`, posté par `PlanningTaskAction.exclude()`), sous les deux
	 * formes de `readChoice()`. Une valeur qui ne désigne rien n'écarte rien.
	 *
	 * @return array ['cultivation' => int[], 'plant' => int[]]
	 */
	public static function readExcluded(array $values): array {

		$excluded = ['cultivation' => [], 'plant' => []];

		foreach($values as $value) {

			$plant = self::readPlant($value);

			if($plant !== NULL) {
				$excluded['plant'][] = $plant;
			} else if(is_string($value) and ctype_digit($value)) {
				$excluded['cultivation'][] = (int)$value;
			}

		}

		return $excluded;

	}

	/**
	 * Les sections de « Espèce ou série » dont « Voir les N autres » a levé la coupe (`more`, posté par
	 * `PlanningTaskAction.more()`, retour 382). Une valeur qui ne nomme pas une section ne lève rien.
	 *
	 * @return array ['cultivation' => bool, 'plant' => bool]
	 */
	public static function readMore(array $values): array {

		return [
			'cultivation' => in_array('cultivation', $values, TRUE),
			'plant' => in_array('plant', $values, TRUE),
		];

	}

	/**
	 * Les espèces de la recherche, par leur nom ou un de leurs alias (`PlantLib::getFromQuery()`), et par leur
	 * nom sans rien de tapé, dès l'ouverture de la liste (retour 243). Les espèces écartées le sont avant la
	 * coupe, qui garde ainsi sa pleine mesure, et que `$all` lève.
	 *
	 * @return array [les espèces retenues, combien d'autres la coupe laisse de côté]
	 */
	public static function getPlantsForQuery(\structure\Structure $eStructure, string $name, array $plantsExcluded = [], bool $all = FALSE): array {

		$cPlantFound = \plant\PlantLib::getFromQuery($name, $eStructure, properties: ['id', 'name', 'fqn', 'vignette'])
			->find(fn(\plant\Plant $ePlant) => in_array($ePlant['id'], $plantsExcluded, TRUE) === FALSE);

		if($all) {
			return [$cPlantFound, 0];
		}

		$cPlant = $cPlantFound->find(limit: PlanningSetting::TASK_QUERY_PLANTS);

		return [$cPlant, $cPlantFound->count() - $cPlant->count()];

	}

}
?>
