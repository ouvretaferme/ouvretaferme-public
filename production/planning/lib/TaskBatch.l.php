<?php
namespace planning;

/**
 * Les gestes de lot de la Semaine et du Jour (L5d) : la barre du produit (`\util\BatchUi`) sur les
 * interventions cochées, et ce que chaque geste change à l'écran.
 *
 * L'écriture est celle de l'ancien planning, par les mêmes méthodes de `\series\TaskLib`
 * (`updateDoneCollection()`, `updatePlannedDateCollection()`…) : seul ce qui suit l'écriture est propre au
 * nouveau planning, la réponse, qui ne redessine que ce que le lot a changé.
 */
class TaskBatchLib {

	/**
	 * Les interventions cochées, lues comme l'ancien planning les lit pour ses gestes de lot
	 * (`series/page/task.p.php`, page des collections) : toutes de la même structure
	 * (`Task::validateBatch()`), et cette structure est celle de l'écran.
	 */
	public static function getByIds(\structure\Structure $eStructure, array $ids): \Collection {

		$cTask = \series\TaskLib::getByIds($ids, properties: \series\Task::getSelection());

		\series\Task::validateBatch($cTask);

		$cTask->first()->validateProperty('structure', $eStructure);

		return $cTask;

	}

	/**
	 * La semaine ou le jour d'un geste « Planifier », construit comme l'ancien planning le construit
	 * (`doUpdatePlannedCollection`, `doUpdatePlannedDateCollection`).
	 */
	public static function getPlanned(string $property, array $input): ?string {

		$fw = new \FailWatch();

		$eTask = new \series\Task();
		$eTask->build([$property], $input);

		$fw->validate();

		return $eTask[$property];

	}

	/**
	 * Le membre et le sens d'« Affecter » (`doUpdateUserCollection` de l'ancien planning) : un membre de la
	 * structure, ajouté ou retiré.
	 *
	 * @return array [\user\User, 'add' ou 'delete']
	 */
	public static function getUser(\structure\Structure $eStructure, array $input): array {

		$eUser = \user\UserLib::getById(var_filter($input['user'] ?? NULL, '?int'));

		if($eUser->empty() or \structure\MemberLib::isMember($eUser, $eStructure) === FALSE) {
			throw new \NotExpectedAction('Invalid user');
		}

		$action = $input['action'] ?? NULL;

		if(in_array($action, ['add', 'delete'], TRUE) === FALSE) {
			throw new \NotExpectedAction('Invalid action');
		}

		return [$eUser, $action];

	}

	/**
	 * « Temps » sur un lot (lot BG, comme « Compléter pour » de l'ancien planning) : un temps total pour un jour,
	 * qui s'ajoute à celui de la personne connectée et se partage entre les interventions cochées selon une clé.
	 *
	 * Une intervention à faire que la personne n'a pas le droit de passer faite, au rôle planning limité, est
	 * verrouillée (`timeLocked`) : sa ligne se lit seulement, et elle ne reçoit aucune part. Les autres portent
	 * ce qui fonde leur part : leur surface et leur nombre de plants (`\series\TaskLib::fillDistribution()`), et,
	 * pour une récolte, sa quantité de chaque jour de la semaine (`harvestWeek`).
	 */
	public static function fillTimes(\Collection $cTask, string $week): void {

		self::fillBases($cTask);

		foreach($cTask as $eTask) {
			$eTask['timeLocked'] = ($eTask->isTodo() and $eTask->canWrite() === FALSE);
		}

		// Le même jugement que la clé, sur les seules lignes qui reçoivent une part (`getDistributions()`) : une
		// ligne verrouillée qui n'est pas une récolte ne prive pas les autres de leur quantité.
		$harvested = self::isHarvestOnly($cTask->find(fn($eTask) => $eTask['timeLocked'] === FALSE));

		// Une récolte pas encore créée (`HarvestWeekLib::getTasksForBatch()`) n'a rien récolté, et rien à lire en base.
		// Les éléments retenus restent ceux de `$cTask` (`clone: FALSE`), que la lecture complète.
		if($harvested) {
			$cTaskWritten = $cTask->find(fn($eTask) => $eTask->exists(), clone: FALSE);
			\series\TaskLib::fillHarvestDates($cTaskWritten);
		}

		foreach($cTask as $eTask) {

			$eTask['harvestWeek'] = [];

			foreach(week_dates($week) as $date) {
				$eTask['harvestWeek'][$date] = $harvested ? ($eTask['harvestDates'][$date] ?? 0.0) : 0.0;
			}

		}

	}

	/**
	 * Ce qui fonde la part d'une intervention selon la surface ou le nombre de plants : la surface de sa série ou
	 * de sa variété (`\series\TaskLib::fillDistribution()`), et les plants qu'y met la densité de sa culture,
	 * `NULL` quand l'une ou l'autre manque. Une intervention pas encore créée se mesure aussi, par sa culture.
	 */
	public static function fillBases(\Collection $cTask): void {

		\series\TaskLib::fillDistribution($cTask);

		foreach($cTask as $eTask) {

			$eTask['distributionPlant'] = ($eTask['cultivation']->notEmpty() and $eTask['cultivation']['density'] !== NULL and $eTask['distributionArea'] !== NULL) ?
				$eTask['cultivation']['density'] * $eTask['distributionArea'] :
				NULL;

		}

	}

	/**
	 * Les jours où poser le temps, ceux de la semaine affichée, et celui qui est choisi d'office : aujourd'hui
	 * quand la semaine le porte, son dernier jour quand elle est passée, aucun quand elle est à venir. Un jour
	 * à venir ne se choisit pas (`\series\Timesheet::properties()`, étape `future`).
	 *
	 * @return array [les jours, chacun avec `today` et `future`, et le jour choisi]
	 */
	public static function getTimeDays(string $week): array {

		$today = \Clock::date();

		$days = [];

		foreach(week_dates($week) as $date) {
			$days[$date] = [
				'today' => ($date === $today),
				'future' => ($date > $today),
			];
		}

		if(array_key_exists($today, $days)) {
			$date = $today;
		} else if(week_date_ends($week) < $today) {
			$date = week_date_ends($week);
		} else {
			$date = NULL;
		}

		return [$days, $date];

	}

	/**
	 * Les clés possibles pour partager le temps des interventions ouvertes, et celle choisie d'office.
	 *
	 * - Que des récoltes de la même unité : la quantité récoltée le jour choisi, sans autre choix (arbitrage de
	 *   du 12/10). Des unités différentes ne s'additionnent pas, et la sélection prend les autres clés.
	 * - Une intervention hors culture : le partage égalitaire seul, comme l'ancien formulaire
	 *   (`\series\TaskUi::getDistributionField()`).
	 * - Sinon la surface, quand chaque série a la sienne, le nombre de plants, quand chaque culture a aussi sa
	 *   densité, et le partage égalitaire. Plants d'office pour des semis en pépinière ou des plantations,
	 *   surface sinon, comme avant, chacune cédant à la suivante quand elle manque.
	 *
	 * @return array [les clés, la clé choisie]
	 */
	public static function getDistributions(\Collection $cTask): array {

		$cTaskOpen = $cTask->find(fn($eTask) => $eTask['timeLocked'] === FALSE);

		if($cTaskOpen->empty()) {
			return [[], NULL];
		}

		foreach($cTaskOpen as $eTask) {
			if($eTask['category']['fqn'] !== CATEGORIE_CULTURE) {
				return [['fair'], 'fair'];
			}
		}

		if(self::isHarvestOnly($cTaskOpen)) {
			return [['harvest'], 'harvest'];
		}

		return self::getDistributionsByBase($cTaskOpen);

	}

	/**
	 * La surface, quand chaque série a la sienne, le nombre de plants, quand chaque culture a aussi sa densité, et
	 * le partage égalitaire, avec celle choisie d'office : plants pour des semis en pépinière ou des plantations,
	 * surface sinon, chacune cédant à la suivante quand elle manque. « Temps » les propose hors des récoltes seules,
	 * « Récolter » toujours, pour la quantité comme pour le temps (`prepareHarvests()`).
	 *
	 * @return array [les clés, la clé choisie]
	 */
	protected static function getDistributionsByBase(\Collection $cTaskOpen): array {

		$distributions = [];

		if(self::sumBases($cTaskOpen, 'distributionArea') !== NULL) {
			$distributions[] = 'area';
		}

		if(self::sumBases($cTaskOpen, 'distributionPlant') !== NULL) {
			$distributions[] = 'plant';
		}

		$distributions[] = 'fair';

		$planted = ($cTaskOpen->find(fn($eTask) => in_array($eTask['action']['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION], TRUE) === FALSE)->empty());

		if($planted and in_array('plant', $distributions, TRUE)) {
			$default = 'plant';
		} else {
			$default = $distributions[0];
		}

		return [$distributions, $default];

	}

	/**
	 * Des récoltes seulement, toutes dans la même unité de récolte. Une récolte qui n'a encore rien reçu n'a pas
	 * d'unité (`harvestUnit` se pose à la première quantité) : elle ne départage pas les unités, et sa part du
	 * jour sera nulle, faute de quantité.
	 */
	protected static function isHarvestOnly(\Collection $cTask): bool {

		if($cTask->empty()) {
			return FALSE;
		}

		$units = [];

		foreach($cTask as $eTask) {

			if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
				return FALSE;
			}

			if($eTask['harvestUnit'] !== NULL) {
				$units[] = $eTask['harvestUnit'];
			}

		}

		return (count(array_unique($units)) <= 1);

	}

	/**
	 * La somme d'une base de partage, ou `NULL` quand une intervention n'en a pas, ou que toutes valent zéro :
	 * la clé ne départagerait rien.
	 */
	protected static function sumBases(\Collection $cTask, string $property): ?float {

		$sum = 0.0;

		foreach($cTask as $eTask) {

			if($eTask[$property] === NULL) {
				return NULL;
			}

			$sum += $eTask[$property];

		}

		return ($sum > 0.0) ? $sum : NULL;

	}

	/**
	 * Le temps total et son jour, construits par `build()` comme un temps de la fiche (`\series\Timesheet`), puis
	 * partagés selon la clé postée, qui doit être l'une des clés possibles. Le partage est celui de l'ancien
	 * formulaire (`\series\TaskLib::distribute()`), compté à la minute : la somme des parts vaut le total, et les
	 * minutes qui restent vont aux premières lignes. Une ligne dont la base vaut zéro, une récolte sans
	 * quantité ce jour, ne reçoit rien, ni ces minutes-là.
	 *
	 * Tout se refuse ici, avant la première écriture : un jour hors de la semaine affichée, un total nul, une
	 * journée qui passerait 24 heures avec le total, et des récoltes dont aucune n'a de quantité ce jour.
	 *
	 * @return \Collection Les temps à ajouter, par identifiant d'intervention, chacun avec son intervention.
	 */
	public static function prepareTimes(\structure\Structure $eStructure, \Collection $cTask, \user\User $eUser, string $week, array $input): \Collection {

		[$distributions] = self::getDistributions($cTask);

		$distribution = ($distributions === ['harvest'] or $distributions === ['fair']) ? $distributions[0] : ($input['distribution'] ?? NULL);

		if(in_array($distribution, $distributions, TRUE) === FALSE) {
			throw new \NotExpectedAction('Invalid distribution');
		}

		$eTimesheet = new \series\Timesheet([
			'structure' => $eStructure,
			'user' => $eUser,
		]);

		$l = new \PropertyList('create');

		$eTimesheet->build(['date', 'time'], $input, $l);

		if($l->getInvalid() !== []) {
			return new \Collection();
		}

		if(in_array($eTimesheet['date'], week_dates($week), TRUE) === FALSE) {
			\series\Timesheet::fail('date.week');
			return new \Collection();
		}

		$minutes = (int)round($eTimesheet['time'] * 60);

		if($minutes === 0) {
			\series\Timesheet::fail('time.missing');
			return new \Collection();
		}

		if(round(\series\TimesheetLib::getTimeByUserDate($eStructure, $eUser, $eTimesheet['date']) + $minutes / 60, 2) > 24.0) {
			\series\Timesheet::fail('time.tooLong');
			return new \Collection();
		}

		$cTaskShared = $cTask->find(fn($eTask) => $eTask['timeLocked'] === FALSE and self::getBase($eTask, $distribution, $eTimesheet['date']) > 0.0);

		if($cTaskShared->empty()) {
			\series\Timesheet::fail('time.harvestless');
			return new \Collection();
		}

		if($distribution === 'harvest') {
			\series\TaskLib::fillHarvestByDate($cTaskShared, $eTimesheet['date']);
		}

		$cTimesheet = new \Collection();

		\series\TaskLib::distribute($cTaskShared, $distribution, $minutes, 0, function(\series\Task $eTask) use($cTimesheet, $eTimesheet) {

			if($eTask['distributed'] > 0.0) {
				$cTimesheet[$eTask['id']] = (clone $eTimesheet)->merge([
					'task' => $eTask,
					'time' => $eTask['distributed'] / 60,
				]);
			}

		});

		return $cTimesheet;

	}

	/**
	 * Ce qui fonde la part d'une intervention selon la clé, que le panneau répète dans le navigateur
	 * (`PlanningTaskBatchTime`).
	 */
	public static function getBase(\series\Task $eTask, string $distribution, string $date): float {

		return match($distribution) {
			'harvest' => $eTask['harvestWeek'][$date],
			'area' => (float)$eTask['distributionArea'],
			'plant' => (float)$eTask['distributionPlant'],
			'fair' => 1.0,
		};

	}

	/**
	 * Les parts, dans une transaction : chacune s'ajoute au temps de la personne ce jour-là
	 * (`\series\TimesheetLib::writeByTasks()`, comme « Compléter pour »), puis une intervention à faire qui a reçu
	 * du temps passe faite, comme par sa coche (décision de V-Planning du 04/10). Le temps est écrit d'abord : la
	 * coche date le fait du dernier jour de temps (`\series\TaskLib::updateDoneCollection()`).
	 */
	public static function writeTimes(\Collection $cTimesheet, string $week): void {

		\series\Task::model()->beginTransaction();

		foreach($cTimesheet as $eTimesheet) {

			\series\TimesheetLib::writeByTasks(new \Collection([$eTimesheet['task']]), $eTimesheet, $eTimesheet['time'], 'fair');

			if($eTimesheet['task']->isTodo()) {
				\series\TaskLib::updateDoneCollection(new \Collection([$eTimesheet['task']]), $week);
			}

		}

		\series\Task::model()->commit();

	}

	/**
	 * « Récolter » sur un lot (DG-1, cadre r de la planche planning-dg-plusieurs-series) : des récoltes de la même
	 * espèce, de la même variété et du même calibre, dans la même unité. Une récolte qui n'a encore rien reçu n'a
	 * pas d'unité (`harvestUnit` se pose à la première quantité) et ne départage rien : elle prendra celle des
	 * autres. `\series\Task::validateSameHarvest()`, que garde l'ancien planning, la refuserait à côté d'une récolte
	 * en kilos.
	 */
	public static function validateHarvests(\Collection $cTask): void {

		$eTaskReference = $cTask->first();
		$units = [];

		foreach($cTask as $eTask) {

			if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
				throw new \FailAction('series\Task::actions.check');
			}

			if($eTask['plant']->empty()) {
				throw new \FailAction('series\Task::harvestConsistency.plant');
			}

			if(
				$eTask['plant']->is($eTaskReference['plant']) === FALSE or
				$eTask['variety']->is($eTaskReference['variety']) === FALSE or
				$eTask['harvestSize']->is($eTaskReference['harvestSize']) === FALSE
			) {
				throw new \FailAction('series\Task::harvestConsistency.check');
			}

			if($eTask['harvestUnit'] !== NULL) {
				$units[] = $eTask['harvestUnit'];
			}

		}

		if(count(array_unique($units)) > 1) {
			throw new \FailAction('series\Task::harvestConsistency.check');
		}

	}

	/**
	 * « Récolter » sur les lignes cochées des Récoltes (FD, retour 405) : des récoltes de la même espèce, dans la
	 * même unité, dont la variété et le calibre peuvent différer, la quantité se partageant par la surface de
	 * chacune (arbitrage du 16/10, « partage par surface », `HarvestWeekLib::fillBatchBases()`). La
	 * Semaine et le Jour gardent leur règle (`validateHarvests()`). Dans une culture, une ligne sans variété ne se
	 * coche pas avec une ligne de variété : elle se partagerait sur la surface de toute la série, qui compte déjà
	 * celle de la variété.
	 */
	public static function validateHarvestsCouples(\Collection $cTask): void {

		$eTaskReference = $cTask->first();
		$units = [];
		$varieties = [];

		foreach($cTask as $eTask) {

			if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
				throw new \FailAction('series\Task::actions.check');
			}

			if($eTask['plant']->empty()) {
				throw new \FailAction('series\Task::harvestConsistency.plant');
			}

			if($eTask['plant']->is($eTaskReference['plant']) === FALSE) {
				throw new \FailAction('series\Task::harvestConsistency.check');
			}

			if($eTask['harvestUnit'] !== NULL) {
				$units[] = $eTask['harvestUnit'];
			}

			$varieties[$eTask['cultivation']['id']][] = $eTask['variety']->notEmpty();

		}

		if(count(array_unique($units)) > 1) {
			throw new \FailAction('series\Task::harvestConsistency.check');
		}

		foreach($varieties as $specified) {
			if(count(array_unique($specified)) > 1) {
				throw new \FailAction('series\Task::harvestConsistency.unspecified');
			}
		}

	}

	/**
	 * La récolte sur laquelle se lit et s'écrit le stock d'un lot (`HarvestStockLib`) : celle de la première ligne
	 * quand toutes ont la même variété et le même calibre, sinon une récolte de l'espèce sans l'un ni l'autre,
	 * comme un lot de séries entières. Lue sur la première seulement, la compatibilité du produit de stock ne
	 * vaudrait que pour sa variété (`\product\StockLib::getCompatibleProducts()`).
	 */
	public static function getStockReference(\Collection $cTask, string $unit): \series\Task {

		$eTaskReference = $cTask->first();

		$mixed = $cTask->contains(fn($eTask) => (
			$eTask['variety']->is($eTaskReference['variety']) === FALSE or
			$eTask['harvestSize']->is($eTaskReference['harvestSize']) === FALSE
		));

		if($mixed) {
			return HarvestStockLib::getProbe($eTaskReference['structure'], $eTaskReference['plant'], $eTaskReference['cultivation'], new \plant\Variety(), new \plant\Size(), $unit);
		}

		return HarvestStockLib::getReference($eTaskReference, $unit);

	}

	/**
	 * L'unité de la saisie : celle des récoltes qui en ont une, sinon celle de la première culture, sinon le kilo,
	 * celui d'une espèce seule (BY-bis). Le second rendu dit si elle se choisit : seulement quand aucune récolte
	 * n'en a encore, comme l'unité de « Nouvelle intervention » (retour 319).
	 *
	 * Les Récoltes fixent l'unité (`$unitFixed`, `HarvestWeekLib::getBatchUnit()`).
	 *
	 * @return array [l'unité, TRUE quand elle se choisit]
	 */
	public static function getHarvestUnit(\Collection $cTask, ?string $unitFixed = NULL): array {

		if($unitFixed !== NULL) {
			return [$unitFixed, FALSE];
		}

		foreach($cTask as $eTask) {
			if($eTask['harvestUnit'] !== NULL) {
				return [$eTask['harvestUnit'], FALSE];
			}
		}

		$eCultivation = $cTask->first()['cultivation'];

		return [$eCultivation->empty() ? \series\Task::KG : $eCultivation['mainUnit'], TRUE];

	}

	/**
	 * Ce que montre le panneau « Récolter », pour la Semaine, le Jour et les Récoltes : le temps et la quantité
	 * de chaque récolte dans la semaine, les jours, les clés de partage, l'unité et, quand elle se choisit, les
	 * interventions déjà dans chaque unité, les caisses de l'espèce, puis les stocks que propose la récolte de
	 * référence.
	 *
	 * @return array Les valeurs du panneau, par leur nom dans la vue `planning/taskWeek:batchHarvest`.
	 */
	public static function getHarvestPanel(\Collection $cTask, string $week, ?string $unitFixed = NULL): array {

		self::fillTimes($cTask, $week);

		[$days, $date] = self::getTimeDays($week);
		[$distributions, $distribution] = self::getHarvestDistributions($cTask);
		[$unit, $unitFree] = self::getHarvestUnit($cTask, $unitFixed);

		$ePlant = $cTask->first()['plant'];
		HarvestWeekLib::prepareCrate($ePlant);

		return [
			'days' => $days,
			'date' => $date,
			'distributions' => $distributions,
			'distribution' => $distribution,
			'unit' => $unit,
			'ePlant' => $ePlant,
			'tasksByUnit' => $unitFree ? HarvestWeekLib::getEntryTasksForCreate($unit) : [],
			'eTaskStock' => HarvestStockLib::fill(self::getStockReference($cTask, $unit)),
		];

	}

	/**
	 * Les clés de « Récolter », pour la quantité comme pour le temps (contrat de DG : une seule clé) : la
	 * quantité à récolter ne peut pas fonder la part de la récolte elle-même.
	 *
	 * @return array [les clés, la clé choisie]
	 */
	public static function getHarvestDistributions(\Collection $cTask): array {

		return self::getDistributionsByBase($cTask);

	}

	/**
	 * La clé postée, qui doit être l'une de celles des récoltes (`getHarvestDistributions()`), sur des bases déjà
	 * lues (`fillBases()`) ; seule, elle ne se poste pas. Une autre clé n'est pas un choix du panneau, et
	 * `\series\TimesheetLib::writeByTasks()` la refuserait sans rien annuler de ce qui est déjà écrit. Sans clé
	 * postée, celle choisie d'office : seul le bloc de récolte la poste, et une récolte faite sans quantité n'a
	 * pas à l'attendre pour se créer.
	 */
	public static function getHarvestDistribution(\Collection $cTask, array $input): string {

		[$distributions, $default] = self::getHarvestDistributions($cTask);

		$distribution = (count($distributions) === 1) ? $distributions[0] : ($input['distribution'] ?? $default);

		if(in_array($distribution, $distributions, TRUE) === FALSE) {
			throw new \NotExpectedAction('Invalid distribution');
		}

		return $distribution;

	}

	/**
	 * La quantité partagée entre les récoltes à 0,1 près, par la clé (`\series\TaskLib::distribute()`), comme
	 * l'ancien planning (`doUpdateHarvestCollection`) : chaque récolte reçoit sa part dans `harvestMore`.
	 */
	public static function shareHarvest(\Collection $cTask, string $distribution, float $quantity): void {

		\series\TaskLib::distribute($cTask, $distribution, $quantity, 1, fn(\series\Task $eTask) => $eTask['harvestMore'] = $eTask['distributed']);

	}

	/**
	 * La récolte d'un lot et son temps, tout vérifié avant la première écriture : `writeHarvestForWeek()`
	 * valide avant sa transaction, et un refus après la première série laisserait les autres écrites. Le jour
	 * est un jour de la semaine affichée et pas à venir, la quantité est positive, en caisses seulement en
	 * kilos, et le stock choisi accepte cette récolte (`\product\StockLib::getCompatibleProducts()`).
	 *
	 * La quantité se partage à 0,1 près et le temps à la minute, par la même clé et par `\series\TaskLib::distribute()`,
	 * comme l'ancien planning (`doUpdateHarvestCollection`). Des caisses se convertissent en kilos avant le
	 * partage : partagées en caisses, chaque part s'arrondirait avant la conversion. Le temps est celui de chaque
	 * personne de l'équipe de la semaine (`time[<user>]`, `TimeTeamLib::prepare()`), partagé personne par
	 * personne : chacune a sa journée de 24 heures, et une part de série porte une ligne de temps par personne. Il
	 * est facultatif, et l'équipe est vide sur une structure qui ne suit pas le temps.
	 *
	 * @return array Les récoltes, chacune avec sa part (`harvestMore`) et ses temps (`cTimesheet`), puis le jour, l'unité, la quantité, le nombre saisi, l'espèce des caisses, la référence du stock, le stock choisi et son étoile (`HarvestStockLib::build()`).
	 */
	public static function prepareHarvests(\structure\Structure $eStructure, \Collection $cTask, string $week, array $input, ?string $unitFixed = NULL): array {

		$distribution = self::getHarvestDistribution($cTask, $input);

		$eTaskReference = $cTask->first();

		[$unit, $unitFree] = self::getHarvestUnit($cTask, $unitFixed);

		$fw = new \FailWatch();

		$eTimesheet = new \series\Timesheet([
			'structure' => $eStructure,
		]);

		$eTimesheet->build(['date'], $input, new \PropertyList('create'));

		$eTaskEntry = new \series\Task([
			'structure' => $eStructure,
		]);

		$eTaskEntry->build($unitFree ? ['harvestMore', 'harvestUnit'] : ['harvestMore'], $input, new \PropertyList('week'));

		$ePlantCrate = HarvestWeekLib::buildCrate($eTaskReference['plant'], $input);

		$fw->validate();

		if(in_array($eTimesheet['date'], week_dates($week), TRUE) === FALSE) {
			\series\Timesheet::fail('date.week');
		}

		if($unitFree and $eTaskEntry['harvestUnit'] !== NULL) {
			$unit = $eTaskEntry['harvestUnit'];
		}

		// « Récolter » ajoute toujours : une quantité négative retirerait d'un jour sur des séries que la clé désigne.
		if($eTaskEntry['harvestMore'] <= 0.0) {
			\series\Task::fail('harvestMore.missing');
		}

		if($ePlantCrate->notEmpty() and $unit !== \series\Task::KG) {
			\series\Task::fail('harvestUnit.crate');
		}

		// Chaque personne reconstruit le jour : il se juge d'abord, une fois, et non sous chacune.
		$cTimesheet = TimeTeamLib::prepare($eStructure, TimeTeamLib::getUsersForWeek($eStructure, $week), $eTimesheet['date'], $input);

		$eTaskStock = self::getStockReference($cTask, $unit);

		[$eProductStock, $stockRemember] = HarvestStockLib::build($eStructure, $eTaskStock, $input);

		$fw->validate();

		$quantity = HarvestWeekLib::getCrateQuantity($ePlantCrate, $eTaskEntry['harvestMore']);

		self::shareHarvest($cTask, $distribution, $quantity);

		$fw->validate();

		foreach($cTask as $eTask) {
			$eTask['cTimesheet'] = new \Collection();
		}

		foreach($cTimesheet as $eTimesheetUser) {

			\series\TaskLib::distribute($cTask, $distribution, (int)round($eTimesheetUser['time'] * 60), 0, function(\series\Task $eTask) use($eTimesheetUser) {

				if($eTask['distributed'] > 0.0) {
					$eTask['cTimesheet'][] = (clone $eTimesheetUser)->merge([
						'time' => $eTask['distributed'] / 60,
					]);
				}

			});

		}

		return [
			'cTask' => $cTask,
			'date' => $eTimesheet['date'],
			'unit' => $unit,
			'quantity' => $quantity,
			'crates' => $eTaskEntry['harvestMore'],
			'ePlantCrate' => $ePlantCrate,
			'eTaskStock' => $eTaskStock,
			'eProductStock' => $eProductStock,
			'stockRemember' => $stockRemember,
		];

	}

	/**
	 * Chaque part par `\series\TaskLib::writeHarvestForWeek()`, comme la saisie des Récoltes : la récolte, le temps de
	 * chaque personne, le report du jour prévu et le passage fait d'une récolte à faire qui reçoit une
	 * quantité. Une part nulle, sans temps, n'écrit rien. Puis le poids des caisses, le stock de la quantité
	 * entière et l'étoile (`HarvestStockLib::write()`), sur la première récolte, celle dont le champ a lu le stock
	 * mémorisé.
	 */
	public static function writeHarvests(array $harvest): void {

		\series\Task::model()->beginTransaction();

		foreach($harvest['cTask'] as $eTask) {

			if($eTask['harvestMore'] === 0.0 and $eTask['cTimesheet']->empty()) {
				continue;
			}

			\series\TaskLib::writeHarvestForWeek($eTask, $eTask['cTimesheet'], $harvest['date'], 'add', harvestUnit: $harvest['unit']);

		}

		HarvestWeekLib::writeCrate($harvest['ePlantCrate'], $harvest['crates']);

		HarvestStockLib::write($harvest['eTaskStock'], $harvest['eProductStock'], $harvest['stockRemember'], $harvest['quantity']);

		\series\Task::model()->commit();

	}

	/**
	 * Ce qu'un lot change dans la Semaine, lu dans les blocs d'après le geste : `board` dès qu'une seule
	 * intervention le demande (`TaskWeekLib::getChange()`, le dernier bloc vidé), sinon, par intervention,
	 * `card`, `remove`, ou `emptied` quand elle a vidé son bloc.
	 *
	 * Une intervention qui venait d'une zone sous la semaine (`$idsArriving`, L8) n'a pas encore de carte
	 * dans les blocs : elle rend les blocs entiers si elle y entre, et ne change rien sinon (`none`).
	 *
	 * @return string|array `board`, ou le changement de chaque intervention, par identifiant.
	 */
	public static function getWeekChange(\Collection $ccTask, \Collection $cTask, array $idsArriving = []): string|array {

		$changes = [];

		foreach($cTask as $eTask) {

			if(in_array($eTask['id'], $idsArriving)) {

				if(self::getCreateChange($ccTask, $eTask) !== NULL) {
					return 'board';
				}

				$changes[$eTask['id']] = 'none';
				continue;

			}

			$change = TaskWeekLib::getChange($ccTask, $eTask);

			if($change === 'board') {
				return 'board';
			}

			$changes[$eTask['id']] = $change;

		}

		return $changes;

	}

	/**
	 * Ce qu'une intervention neuve change dans la Semaine : rien quand elle n'y est pas (prévue une autre
	 * semaine), `board` quand elle ouvre un bloc, les blocs
	 * se répartissant en piles côté navigateur (`TaskWeekLib::getChange()`), et sinon `block`, son bloc
	 * rendu entier : la carte neuve n'a pas de nœud à remplacer, et sa place dans le bloc suit l'ordre de
	 * `TaskWeekLib::get()`.
	 *
	 * @param int $created Les interventions neuves de la même action et de la même semaine, créées d'un coup
	 *   avec celle-ci (`TaskCreateLib::createCollection()`) : elles ouvrent le bloc quand il ne porte qu'elles.
	 */
	public static function getCreateChange(\Collection $ccTask, \series\Task $eTask, int $created = 1): ?string {

		$action = $eTask['action']['id'];

		if(
			$ccTask->offsetExists($action) === FALSE or
			$ccTask[$action]->offsetExists($eTask['id']) === FALSE
		) {
			return NULL;
		}

		return ($ccTask[$action]->count() === $created) ? 'board' : 'block';

	}

	/**
	 * Les colonnes du Jour où se trouvent les interventions d'un lot avant le geste, lues pour chacune
	 * (`TaskDayLib::getKeysForTask()`), et réunies.
	 */
	public static function getDayKeysBefore(\structure\Structure $eStructure, string $week, \Collection $cTask): array {

		$keys = [];

		foreach($cTask as $eTask) {
			$keys = array_merge($keys, TaskDayLib::getKeysForTask($eStructure, $week, $eTask));
		}

		return array_values(array_unique($keys));

	}

	/**
	 * Les interventions d'un lot que « Sans jour » garde après la coche (`TaskDayLib::getColumns()`,
	 * R-Lot-Garde) : celles qui y étaient avant l'écriture, avec le temps qu'y avait la personne connectée
	 * dans la semaine, comme le geste d'une carte (`TaskDayLib::getTimeForTask()`).
	 */
	public static function getDayKept(\structure\Structure $eStructure, string $week, \Collection $cTask, \user\User $eUser): array {

		$cTaskNone = $cTask->find(fn($eTask) => TaskDayLib::isNone($eTask, $week));

		if($cTaskNone->empty()) {
			return [];
		}

		$tasks = array_fill_keys($cTaskNone->getIds(), 0.0);

		$cTimesheet = \series\Timesheet::model()
			->select([
				'task',
				'total' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->whereTask('IN', $cTaskNone)
			->whereUser($eUser)
			->whereDate('BETWEEN', new \Sql(\series\Timesheet::model()->format(week_date_starts($week)).' AND '.\series\Timesheet::model()->format(week_date_ends($week))))
			->group('task')
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {
			$tasks[$eTimesheet['task']['id']] = $eTimesheet['total'];
		}

		return $tasks;

	}

	/**
	 * Les colonnes du Jour qu'un lot redessine : celles de chaque intervention, avant et après le geste,
	 * dans l'ordre de l'écran (`TaskDayLib::getChange()`). Les colonnes d'après se lisent sur la semaine
	 * relue (`$cTaskWeek`) et non sur les cartes : sous « Masquer ce qui est fait », une intervention que le
	 * lot fait n'a pas de carte dans la colonne où elle va, dont la tête la compte pourtant.
	 */
	public static function getDayChange(array $columns, array $keysBefore, \Collection $cTask, \Collection $cTaskWeek, string $week, array $dates): array {

		$keys = $keysBefore;

		foreach($cTask as $eTask) {

			$keys = array_merge($keys, TaskDayLib::getChange($columns, [], $eTask));

			if($cTaskWeek->offsetExists($eTask['id'])) {
				$keys = array_merge($keys, TaskDayLib::getKeys($cTaskWeek[$eTask['id']], $week, $dates));
			}

		}

		return array_values(array_filter(array_keys($columns), fn($key) => in_array($key, $keys, TRUE)));

	}

}
?>
