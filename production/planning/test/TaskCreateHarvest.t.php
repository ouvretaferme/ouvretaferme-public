<?php
namespace planning;

/**
 * « Nouvelle intervention » faite sur plusieurs séries de la même espèce (DG-5) : la quantité se partage entre
 * les récoltes par la clé choisie, comme dans « Récolter », le temps de chaque personne suit la même clé, et le
 * stock choisi reçoit la quantité entière. Une clé que les séries n'offrent pas, ou une quantité refusée, ne
 * laisse rien d'écrit.
 *
 * Lancement : dev/test package=planning file=TaskCreateHarvest
 */
class TaskCreateHarvestTest extends HarvestFixtureTest {

	// Un refus ne laisse aucune transaction ouverte (`\dev\TransactionTest`) : `TaskCreateLib::createCollection()`
	// prépare la récolte avant d'ouvrir la sienne, et l'écart se lit en échec.
	const STRICT = TRUE;

	/**
	 * 8 kg sur deux séries de 10 et 30 m², par la surface : 2 kg et 6 kg, chacun sur sa culture, et 2 h de la
	 * personne partagées de même. Le stock choisi monte des 8 kg. Par parts égales, la même saisie donne 4 kg
	 * et 1 h à chacune.
	 */
	public function testShared(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$eUser = $this->getMember();

		$cases = [
			'surface' => ['area', [2.0, 6.0], [0.5, 1.5]],
			'parts égales' => ['fair', [4.0, 4.0], [1.0, 1.0]],
		];

		foreach($cases as $case => [$distribution, $quantities, $times]) {

			$ePlant = $this->createPlant();
			$eProduct = $this->createProduct('Stock '.$case, $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

			$cCultivation = $this->createCultivationsByArea($ePlant, [10, 30]);

			[$data, , $action] = $this->callCreate([
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'cultivation' => $cCultivation->getIds(),
				'distribution' => $distribution,
				'harvestMore' => '8',
				'stock' => $eProduct['id'],
				'time' => [$eUser['id'] => '2'],
			]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la récolte de deux séries est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$harvested = [];
			$written = [];

			foreach($cCultivation as $eCultivation) {

				$eTask = $data->cTaskCreated->find(fn(\series\Task $eTask) => $eTask['cultivation']->is($eCultivation))->first();

				$harvested[] = $this->getHarvested($eCultivation)[$tuesday]['kg'] ?? NULL;
				$written[] = $this->getTimeWritten($eTask, $eUser, $tuesday);

			}

			$this->assertEquals($quantities, $harvested, $case.' : les 8 kg ne se partagent pas par la clé');
			$this->assertEquals($times, $written, $case.' : les 2 h ne se partagent pas par la clé');
			$this->assertEquals(18.0, \product\ProductLib::getById($eProduct['id'])['stock'], $case.' : le stock ne reçoit pas les 8 kg');

		}

	}

	/**
	 * Par les plants, 12 kg sur 30 m² à 4 plants/m² et 60 m² à 1 plant/m², soit 120 et 60 plants : 8 kg et 4 kg.
	 * En caisses, 3 caisses de 6 kg se partagent en kilos : 12 kg et 6 kg.
	 */
	public function testPlantAndCrate(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		$cases = [
			'kilos' => [['harvestMore' => '12'], [8.0, 4.0]],
			'caisses' => [['harvestMore' => '3', 'crate' => '1', 'crateWeight' => '6'], [12.0, 6.0]],
		];

		foreach($cases as $case => [$input, $quantities]) {

			$cCultivation = $this->createCultivationsByArea($this->createPlant(), [30, 60], [4.0, 1.0]);

			[, , $action] = $this->callCreate($input + [
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'cultivation' => $cCultivation->getIds(),
				'distribution' => 'plant',
			]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la récolte par les plants est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$harvested = [];

			foreach($cCultivation as $eCultivation) {
				$harvested[] = $this->getHarvested($eCultivation)[$tuesday]['kg'] ?? NULL;
			}

			$this->assertEquals($quantities, $harvested, $case.' : la quantité ne se partage pas par les plants');

		}

	}

	/**
	 * Les plants sans densité, que les séries n'offrent donc pas, et des caisses en bottes sont refusés : aucune
	 * intervention, aucune récolte, aucun temps, aucun mouvement de stock.
	 */
	public function testRefused(): void {

		$tuesday = week_date_day(\Clock::week(), 2);
		$eUser = $this->getMember();

		$ePlant = $this->createPlant();
		$eProduct = $this->createProduct('Stock refusé', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);
		$cCultivation = $this->createCultivationsByArea($ePlant, [10, 30]);

		$timeBefore = \series\TimesheetLib::getTimeByUserDate($this->eStructure, $eUser, $tuesday);

		$cases = [
			'clé sans densité' => [['distribution' => 'plant', 'harvestMore' => '8'], []],
			'caisses en bottes' => [['distribution' => 'fair', 'harvestUnit' => \series\Task::BUNCH, 'harvestMore' => '3', 'crate' => '1', 'crateWeight' => '6'], ['Task::harvestUnit.crate']],
		];

		foreach($cases as $case => [$input, $fails]) {

			$before = $this->countWeekTasks();

			[, , $action] = $this->callCreate($input + [
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'cultivation' => $cCultivation->getIds(),
				'stock' => $eProduct['id'],
				'time' => [$eUser['id'] => '2'],
			]);

			$this->assertTrue(($action instanceof \ViewAction) === FALSE, $case.' : la récolte n\'est pas refusée');

			if($fails !== []) {
				$this->assertEquals($fails, $this->getFails($action), $case.' : le refus');
			}

			$this->assertEquals($before, $this->countWeekTasks(), $case.' : une intervention est créée');

			foreach($cCultivation as $eCultivation) {
				$this->assertEquals([], $this->getHarvested($eCultivation), $case.' : une récolte est écrite');
			}

			$this->assertEquals($timeBefore, \series\TimesheetLib::getTimeByUserDate($this->eStructure, $eUser, $tuesday), $case.' : du temps est écrit');
			$this->assertEquals(10.0, \product\ProductLib::getById($eProduct['id'])['stock'], $case.' : le stock a bougé');

		}

	}

	/**
	 * La route du bloc rend, pour deux séries de la même espèce, la surface et les parts égales, mesurées sur la
	 * variété choisie : une variété sur la moitié de la seconde série la ramène à 15 m². Des espèces différentes
	 * ne rendent rien. Une culture d'une autre structure, une variété d'une autre espèce et un choix posté en
	 * tableau sont refusés.
	 */
	public function testPanel(): void {

		$ePlant = $this->createPlant();
		$cCultivation = $this->createCultivationsByArea($ePlant, [10, 30]);

		$harvest = $this->callHarvest(array_map('strval', $cCultivation->getIds()));

		if($this->assertTrue($harvest !== NULL, 'le bloc de deux séries est refusé') === FALSE) {
			return;
		}

		$this->assertEquals([['area', 'fair'], 'area'], [$harvest['distributions'], $harvest['distribution']], 'deux séries qui ont une surface n\'offrent pas la surface d\'office');
		$this->assertEquals([10.0, 30.0], $this->readAreas($harvest['cTask']), 'les surfaces des deux séries');

		$eVariety = $this->createVariety($ePlant);
		$this->createSlice($cCultivation->last(), $eVariety, 50.0);

		$harvest = $this->callHarvest(array_map('strval', $cCultivation->getIds()), $eVariety['id']);

		if($this->assertTrue($harvest !== NULL, 'le bloc d\'une variété est refusé') === FALSE) {
			return;
		}

		$this->assertEquals([0.0, 15.0], $this->readAreas($harvest['cTask']), 'la variété ne mesure pas la part de chaque série');

		$cCultivationOther = $this->createCultivationsByArea($this->createPlant(), [10]);

		$this->assertEquals([], $this->callHarvest([(string)$cCultivation->first()['id'], (string)$cCultivationOther->first()['id']]), 'deux espèces rendent un bloc');

		$this->assertEquals(NULL, $this->callHarvest([(string)$cCultivation->first()['id'], (string)$this->createCultivationForeign()['id']]), 'une culture d\'une autre structure rend un bloc');
		$this->assertEquals(NULL, $this->callHarvest(array_map('strval', $cCultivation->getIds()), $this->createVariety($this->createPlant())['id']), 'une variété d\'une autre espèce rend un bloc');
		$this->assertEquals(NULL, $this->callHarvest([(string)$cCultivation->first()['id'], [(string)$cCultivation->last()['id']]]), 'un choix posté en tableau rend un bloc');

	}

	/**
	 * « Variétés en mélange » et « Variété non renseignée » n'ont pas d'espèce : cultivées dans les deux séries,
	 * elles rendent le bloc, et 8 kg s'y partagent par la surface. Sans clé postée, celle choisie d'office vaut.
	 */
	public function testVarietyWithoutPlant(): void {

		$tuesday = week_date_day(\Clock::week(), 2);

		foreach([\plant\PlantSetting::VARIETY_MIX, \plant\PlantSetting::VARIETY_UNKNOWN] as $variety) {

			$cCultivation = $this->createCultivationsByArea($this->createPlant(), [10, 30]);

			foreach($cCultivation as $eCultivation) {
				$this->createSlice($eCultivation, new \plant\Variety(['id' => $variety]));
			}

			$harvest = $this->callHarvest(array_map('strval', $cCultivation->getIds()), $variety);

			if($this->assertTrue($harvest !== NULL, 'la variété '.$variety.' : le bloc est refusé') === FALSE) {
				continue;
			}

			$this->assertEquals('area', $harvest['distribution'], 'la variété '.$variety.' : la surface n\'est pas la clé d\'office');

			[, , $action] = $this->callCreate([
				'status' => \series\Task::DONE,
				'day' => $tuesday,
				'cultivation' => $cCultivation->getIds(),
				'variety' => $variety,
				'harvestMore' => '8',
			]);

			if($this->assertTrue($action instanceof \ViewAction, 'la variété '.$variety.' : la récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$harvested = [];

			foreach($cCultivation as $eCultivation) {
				$harvested[] = $this->getHarvested($eCultivation)[$tuesday]['kg'] ?? NULL;
			}

			$this->assertEquals([2.0, 6.0], $harvested, 'la variété '.$variety.' : les 8 kg ne se partagent pas par la surface');

		}

	}

	/**
	 * Une culture par surface, chacune sur sa série et sur l'espèce donnée, avec sa densité quand elle est donnée.
	 */
	private function createCultivationsByArea(\plant\Plant $ePlant, array $areas, array $densities = []): \Collection {

		$cCultivation = new \Collection();

		foreach($areas as $position => $area) {

			$eCultivation = $this->createCultivation($this->createSeries('Série de '.$area.' m² '.uniqid()), $ePlant);

			// La surface d'une série se calcule de ses emplacements, que le décor ne pose pas : elle s'écrit
			// ici telle que le calcul l'aurait laissée, seule base que lit le partage.
			$eCultivation['series']['area'] = $area;
			\series\SeriesLib::update($eCultivation['series'], ['area']);

			if(isset($densities[$position])) {

				$eCultivation['series'] = \series\SeriesLib::getById($eCultivation['series']['id']);
				$eCultivation['distance'] = \series\Cultivation::DENSITY;
				$eCultivation['density'] = $densities[$position];
				\series\CultivationLib::update($eCultivation, ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing']);

			}

			$cCultivation[$eCultivation['id']] = $eCultivation;

		}

		return $cCultivation;

	}

	/**
	 * `taskWeek:doCreate` d'une récolte, rendu par sa vue.
	 */
	private function callCreate(array $input): array {

		// « Quand » s'ouvre sur la semaine de l'écran : sans `when`, l'intervention part pour elle, comme du panneau tel qu'il s'ouvre.
		$input += ['when' => $input['week'] ?? \Clock::week()];

		$action = $this->executePage('planning/taskWeek:doCreate', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_RECOLTE)['id'],
			'description' => '',
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView('doCreate', $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	/**
	 * Les valeurs du bloc que rend `taskDay:harvest` pour ces choix, `NULL` quand la route refuse.
	 */
	private function callHarvest(array $choices, ?int $variety = NULL): ?array {

		$action = $this->executePage('planning/taskDay:harvest', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'choices' => $choices,
			'variety' => $variety === NULL ? '' : (string)$variety,
		], 'POST');

		if(($action instanceof \ViewAction) === FALSE) {
			return NULL;
		}

		[$data] = $this->runView('harvest', $action, 'taskDay');

		return $data->harvest;

	}

	private function readAreas(\Collection $cTask): array {

		$areas = [];

		foreach($cTask as $eTask) {
			$areas[] = (float)$eTask['distributionArea'];
		}

		return $areas;

	}

	/**
	 * Une culture d'une autre structure, sur une espèce neuve, que la route doit refuser.
	 */
	private function createCultivationForeign(): \series\Cultivation {

		$eCultivation = new \series\Cultivation([
			'structure' => $this->getStructureForeign(),
			'season' => $this->getSeason(),
			'series' => $this->getSeries(),
			'sequence' => new \sequence\Sequence(),
			'crop' => new \sequence\Crop(),
			'plant' => $this->createPlant(),
			'distance' => \series\Cultivation::SPACING,
			'varietiesUnit' => \series\Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'mainUnit' => \series\Cultivation::KG,
			'harvestPeriodExpected' => \series\Cultivation::MONTH,
		]);

		// Sans `build()` ni `CultivationLib::create()` : le build borne la série et l'espèce à la structure, et
		// le Lib recalcule la série de la structure connectée. Le cas veut justement une ligne qu'aucun des deux
		// ne laisserait passer, une culture d'une autre structure.
		\series\Cultivation::model()->insert($eCultivation);

		return $eCultivation;

	}

	/**
	 * Le temps écrit pour une personne un jour sur une intervention, arrondi au dix-millième : la répartition
	 * écrit des parts à six décimales.
	 */
	private function getTimeWritten(\series\Task $eTask, \user\User $eUser, string $date): float {

		$cTimesheet = \series\Timesheet::model()
			->select('time')
			->whereTask($eTask)
			->whereUser($eUser)
			->whereDate($date)
			->getCollection();

		return round($cTimesheet->sum('time'), 4);

	}

	/** Le récolté de la culture, par jour puis par unité. */
	private function getHarvested(\series\Cultivation $eCultivation): array {

		$cHarvest = \series\Harvest::model()
			->select(['date', 'unit', 'quantity'])
			->whereCultivation($eCultivation)
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {
			$harvested[$eHarvest['date']][$eHarvest['unit']] = ($harvested[$eHarvest['date']][$eHarvest['unit']] ?? 0.0) + $eHarvest['quantity'];
		}

		return $harvested;

	}

	private function countWeekTasks(): int {

		return \series\Task::model()
			->whereStructure($this->eStructure)
			->wherePlannedWeek(\Clock::week())
			->count();

	}

}
?>
