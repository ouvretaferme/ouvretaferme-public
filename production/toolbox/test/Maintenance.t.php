<?php
namespace toolbox;

/**
 * Le carnet du matériel (lot T4 de production-finir, planches pf-t4-v2 et pf-t4-v4).
 *
 * - L'échéance d'un entretien est « Prochaine fois » ; au compteur, elle se compare au dernier
 *   relevé, à la date, au jour ; « Bientôt » à 50 h, 1 000 km ou 60 jours ; un entretien dû aux deux
 *   l'est au premier des deux (`MaintenanceLib::fill()`).
 * - Un entretien fait fait repartir « Prochaine fois » d'un entretien régulier, et clôt un entretien
 *   « Une seule fois » (`MaintenanceDoneLib::follow()`).
 * - Un compteur ne recule pas : un relevé se place entre celui d'avant et celui d'après.
 * - Suivre de nouveau un matériel le réveille avec son historique, et son premier relevé ne passe
 *   pas sous le dernier. Un nom validé dans l'autocomplétion entre dans « Mon matériel » au même
 *   geste.
 *
 * Les échéances se calculent sur des éléments montés en mémoire. Les relevés et le suivi écrivent
 * sur F1, structure du décor des tests, dans une transaction annulée par méthode (`TransactionTest`).
 *
 * Lancement : dev/test package=toolbox file=Maintenance
 */
class MaintenanceTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	private \user\User $eUserInitial;

	/**
	 * `createdBy` lit le compte connecté : le propriétaire de la structure l'est le temps des tests.
	 */
	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		$eMember = \structure\Member::model()
			->select(['user'])
			->whereStructure(self::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->get();

		\user\ConnectionLib::setOnline($eMember['user']);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	protected function getTransactionModel(): \ModuleModel {
		return Equipment::model();
	}

	/**
	 * Un matériel à compteur dont le dernier relevé affiche `$value`, qui porte les entretiens et les
	 * entretiens faits donnés, comme `EquipmentLib::getByStructure()` le livre à `fill()`.
	 */
	private static function getEquipment(?int $value, array $maintenances, array $maintenancesDone = [], ?string $meter = Equipment::HOURS): Equipment {

		$cMaintenance = new \Collection();

		foreach($maintenances as $id => $values) {
			$cMaintenance[$id] = new Maintenance($values + [
				'id' => $id,
				'everyValue' => NULL,
				'everyMonths' => NULL,
				'plannedDate' => NULL,
				'plannedValue' => NULL,
				'ignoredDate' => NULL,
			]);
		}

		$cMaintenanceDone = new \Collection();

		foreach($maintenancesDone as $values) {
			$cMaintenanceDone[] = new MaintenanceDone($values + [
				'value' => NULL,
			]);
		}

		return new Equipment([
			'meter' => $meter,
			'eEquipmentReadingLast' => $value === NULL ? new EquipmentReading() : new EquipmentReading(['date' => '2026-06-10', 'value' => $value]),
			'cMaintenance' => $cMaintenance,
			'cMaintenanceDone' => $cMaintenanceDone,
		]);

	}

	public function testDueByHours(): void {

		$eEquipment = self::getEquipment(1250, [
			1 => ['everyValue' => 400, 'plannedValue' => 1100],
			2 => ['everyValue' => 400, 'plannedValue' => 1300],
			3 => ['everyValue' => 400, 'plannedValue' => 1400],
		]);

		MaintenanceLib::fill($eEquipment);

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals(1100, $cMaintenance[1]['nextValue']);
		$this->assertEquals(-150, $cMaintenance[1]['remainingValue']);
		$this->assertEquals(MaintenanceLib::OVERDUE, $cMaintenance[1]['due'], 'dépassée de 150 h');
		$this->assertEquals(MaintenanceLib::SOON, $cMaintenance[2]['due'], 'à 50 h de l\'échéance, c\'est bientôt');
		$this->assertEquals(NULL, $cMaintenance[3]['due'], 'à 150 h, rien n\'est dû');
		$this->assertEquals('value', $cMaintenance[1]['dueBy']);

	}

	/**
	 * Compteur coupé, un entretien qui n'est dû qu'au compteur n'a plus d'échéance du tout : ni due,
	 * ni borne qui décide. Celui qui est dû aussi à une date garde la date. L'écran levait sur le
	 * premier, faute de date à dire.
	 */
	public function testNoDueWithoutMeter(): void {

		$eEquipment = self::getEquipment(1250, [
			1 => ['everyValue' => 400, 'plannedValue' => 1100],
			2 => ['everyValue' => 400, 'everyMonths' => 12, 'plannedValue' => 1100, 'plannedDate' => '2027-01-10'],
		], meter: NULL);

		MaintenanceLib::fill($eEquipment);

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals(NULL, $cMaintenance[1]['dueBy'], 'au compteur seul, plus d\'échéance');
		$this->assertEquals(NULL, $cMaintenance[1]['due']);
		$this->assertEquals('date', $cMaintenance[2]['dueBy'], 'au compteur ou à une date, la date reste');

	}

	/**
	 * Le cas limite du point précédent : une échéance tombée pile sur le relevé reste à faire, elle
	 * n'est pas dépassée.
	 */
	public function testDueAtTheHour(): void {

		$eEquipment = self::getEquipment(1250, [
			1 => ['everyValue' => 400, 'plannedValue' => 1250],
		]);

		MaintenanceLib::fill($eEquipment);

		$this->assertEquals(0, $eEquipment['cMaintenance'][1]['remainingValue']);
		$this->assertEquals(MaintenanceLib::SOON, $eEquipment['cMaintenance'][1]['due']);

	}

	/**
	 * Un compteur kilométrique dit « bientôt » à 1 000 km de l'échéance, là où 800 heures ne
	 * seraient pas encore dues.
	 */
	public function testDueByKilometers(): void {

		$maintenances = [
			1 => ['everyValue' => 20000, 'plannedValue' => 49000],
			2 => ['everyValue' => 20000, 'plannedValue' => 50000],
		];

		$eEquipment = self::getEquipment(48200, $maintenances, meter: Equipment::KILOMETERS);
		MaintenanceLib::fill($eEquipment);

		$this->assertEquals(800, $eEquipment['cMaintenance'][1]['remainingValue']);
		$this->assertEquals(MaintenanceLib::SOON, $eEquipment['cMaintenance'][1]['due'], 'à 800 km de l\'échéance, c\'est bientôt');
		$this->assertEquals(NULL, $eEquipment['cMaintenance'][2]['due'], 'à 1 800 km, rien n\'est dû');

		$eEquipment = self::getEquipment(48200, $maintenances, meter: Equipment::HOURS);
		MaintenanceLib::fill($eEquipment);

		$this->assertEquals(NULL, $eEquipment['cMaintenance'][1]['due'], 'à 800 h, rien n\'est dû');

	}

	public function testDueByDate(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eEquipment = self::getEquipment(NULL, [
			1 => ['everyMonths' => 12, 'plannedDate' => '2026-07-01'],
			2 => ['everyMonths' => 12, 'plannedDate' => '2026-05-01'],
			3 => ['everyMonths' => 12, 'plannedDate' => '2027-01-10'],
			4 => ['plannedDate' => '2026-07-01'],
		], meter: NULL);

		MaintenanceLib::fill($eEquipment);

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals('2026-07-01', $cMaintenance[1]['nextDate']);
		$this->assertEquals(16, $cMaintenance[1]['remainingDays']);
		$this->assertEquals(MaintenanceLib::SOON, $cMaintenance[1]['due'], 'à 16 jours, c\'est bientôt');
		$this->assertEquals(MaintenanceLib::OVERDUE, $cMaintenance[2]['due'], 'passée depuis le 1er mai');
		$this->assertEquals(NULL, $cMaintenance[3]['due'], 'en janvier prochain, rien n\'est dû');
		$this->assertEquals('date', $cMaintenance[1]['dueBy']);
		$this->assertEquals(NULL, $cMaintenance[1]['nextValue'], 'sans compteur, pas d\'échéance au compteur');
		$this->assertEquals(MaintenanceLib::SOON, $cMaintenance[4]['due'], 'un entretien « Une seule fois » a son échéance comme les autres');

	}

	/**
	 * Au compteur et à la date, la pire des deux décide, et le texte suit celle qui décide.
	 */
	public function testDueByTheWorst(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eEquipment = self::getEquipment(1250, [
			1 => ['everyValue' => 400, 'plannedValue' => 1400, 'everyMonths' => 12, 'plannedDate' => '2026-05-01'],
			2 => ['everyValue' => 400, 'plannedValue' => 1100, 'everyMonths' => 12, 'plannedDate' => '2027-01-10'],
		]);

		MaintenanceLib::fill($eEquipment);

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals(MaintenanceLib::OVERDUE, $cMaintenance[1]['due']);
		$this->assertEquals('date', $cMaintenance[1]['dueBy'], 'la date est passée, les heures non');
		$this->assertEquals(MaintenanceLib::OVERDUE, $cMaintenance[2]['due']);
		$this->assertEquals('value', $cMaintenance[2]['dueBy'], 'les heures sont passées, la date non');

	}

	/**
	 * La dernière fois d'un entretien est son entretien fait le plus récent ; celui d'un autre entretien
	 * ne compte pas. L'historique arrive trié du plus récent au plus ancien.
	 */
	public function testDoneIsTheLastOfItsMaintenance(): void {

		$eEquipment = self::getEquipment(1250, [
			1 => ['everyValue' => 400, 'plannedValue' => 1600],
			2 => ['everyValue' => 400, 'plannedValue' => 1100],
		], [
			['maintenance' => new Maintenance(['id' => 1]), 'date' => '2026-06-01', 'value' => 1200],
			['maintenance' => new Maintenance(['id' => 1]), 'date' => '2026-03-01', 'value' => 1000],
		]);

		MaintenanceLib::fill($eEquipment);

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals(['2026-06-01', 1200], [$cMaintenance[1]['done']['date'], $cMaintenance[1]['done']['value']]);
		$this->assertTrue($cMaintenance[2]['done']->empty(), 'l\'entretien fait d\'une autre ligne ne compte pas');

	}

	public function testAddMonths(): void {

		$this->assertEquals('2026-02-28', MaintenanceLib::addMonths('2026-01-31', 1), 'le 31 janvier revient le dernier jour de février');
		$this->assertEquals('2028-02-29', MaintenanceLib::addMonths('2028-01-31', 1), 'une année bissextile garde son 29 février');
		$this->assertEquals('2027-02-15', MaintenanceLib::addMonths('2026-11-15', 3), 'le compte passe l\'année');
		$this->assertEquals('2027-12-15', MaintenanceLib::addMonths('2026-12-15', 12));

	}

	private function getStructure(): \structure\Structure {
		return \structure\StructureLib::getById(self::STRUCTURE);
	}

	/**
	 * L'intervention du planning réglée pour la ferme, comme après un premier « Fait » : un entretien
	 * fait crée alors sa tâche sans que le panneau la demande.
	 */
	private function configure(\structure\Structure $eStructure): \work\Action {

		$eAction = MaintenanceConfigurationLib::getDefaultAction(MaintenanceConfigurationLib::getActions($eStructure));

		// Sans `build()` : le réglage est le décor, l'intervention posée est lue dans celles que
		// `MaintenanceConfiguration::action.usable` accepterait, et le rythme du rappel est celui du schéma.
		MaintenanceConfigurationLib::create(new MaintenanceConfiguration([
			'structure' => $eStructure,
			'action' => $eAction,
			'readingEveryMonths' => MaintenanceConfigurationLib::getReadingEveryMonthsDefault()
		]));

		return $eAction;

	}

	/**
	 * Un matériel de « Mon matériel », au nom propre au test.
	 */
	private function createTool(\structure\Structure $eStructure, string $name): \work\Tool {

		$eTool = \work\ToolLib::getNewTool($eStructure, NULL);

		// Sans `build()` : le nom est choisi par le test, et « Mon matériel » n'est pas ce qu'il éprouve.
		$eTool['name'] = $name.' '.uniqid();

		\work\ToolLib::create($eTool);

		return $eTool;

	}

	private function follow(\structure\Structure $eStructure, \work\Tool $eTool, ?int $value, string $date, ?string $meter = Equipment::HOURS): Equipment {

		$eEquipment = new Equipment([
			'structure' => $eStructure,
			'tool' => $eTool,
			'meter' => $meter,
			'eToolNew' => new \work\Tool(),
		]);

		$eEquipmentReading = ($value === NULL) ? new EquipmentReading() : new EquipmentReading([
			'structure' => $eStructure,
			'date' => $date,
			'value' => $value,
		]);

		EquipmentLib::follow($eEquipment, $eEquipmentReading);

		return $eEquipment;

	}

	/**
	 * Le relevé se construit comme la ligne « Compteur » le poste.
	 */
	private function buildReading(\structure\Structure $eStructure, Equipment $eEquipment, string $date, string $value): \FailWatch {

		$fw = new \FailWatch();

		new EquipmentReading(['structure' => $eStructure])->build(EquipmentReadingLib::getPropertiesCreate(), [
			'equipment' => $eEquipment['id'],
			'date' => $date,
			'value' => $value,
		]);

		return $fw;

	}

	public function testReadingDoesNotGoBackwards(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, date('Y-m-d', \Clock::timestamp() - 86400 * 10));

		$this->assertTrue($this->buildReading($eStructure, $eEquipment, \Clock::date(), '1100')->ok(), 'un compteur qui avance');
		$this->assertTrue($this->buildReading($eStructure, $eEquipment, \Clock::date(), '1000')->ok(), 'un compteur qui n\'a pas tourné');
		$this->assertTrue($this->buildReading($eStructure, $eEquipment, \Clock::date(), '900')->has('EquipmentReading::value.backwards'), 'un compteur qui recule, sans confirmation');

		// Le relevé glissé entre deux autres ne dépasse pas celui d'après.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => \Clock::date(),
			'value' => 1200,
		]));

		$yesterday = date('Y-m-d', \Clock::timestamp() - 86400);

		$this->assertTrue($this->buildReading($eStructure, $eEquipment, $yesterday, '1300')->has('EquipmentReading::value.ahead'), 'un relevé d\'hier au-dessus de celui du jour, sans confirmation');
		$this->assertTrue($this->buildReading($eStructure, $eEquipment, $yesterday, '1150')->ok(), 'un relevé d\'hier entre les deux');

	}

	/**
	 * Un second relevé du même jour remplace le premier au lieu de lever sur l'unicité.
	 */
	public function testReadingOfTheSameDayReplaces(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, \Clock::date());

		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => \Clock::date(),
			'value' => 1010,
		]));

		$cEquipmentReading = EquipmentReading::model()
			->select(['value'])
			->whereEquipment($eEquipment)
			->getCollection();

		$this->assertEquals(1, $cEquipmentReading->count());
		$this->assertEquals(1010, $cEquipmentReading->first()['value']);

	}

	/**
	 * « Ne plus suivre » puis suivre de nouveau réveille le même matériel, qui retrouve ses relevés.
	 * Son premier relevé de retour ne passe pas sous le dernier sans confirmation, et le refus ne réveille
	 * rien (`testFollowAgainConfirmsAReadingBelow()`).
	 */
	public function testFollowAgainWakesTheEquipment(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Motoculteur');
		$eEquipment = $this->follow($eStructure, $eTool, 500, date('Y-m-d', \Clock::timestamp() - 86400 * 10));

		$eEquipment['status'] = Equipment::INACTIVE;
		EquipmentLib::update($eEquipment, ['status']);

		$fw = new \FailWatch();
		$eEquipmentBackwards = $this->follow($eStructure, $eTool, 400, \Clock::date());

		$this->assertTrue($fw->has('EquipmentReading::value.backwards'), 'le retour ne passe pas sous le dernier relevé sans confirmation');
		$this->assertEquals(Equipment::INACTIVE, Equipment::model()->whereId($eEquipment)->getValue('status'), 'le refus ne réveille pas le matériel');
		$this->assertFalse($eEquipmentBackwards->offsetExists('id'), 'le refus ne crée pas de second matériel');

		$fw = new \FailWatch();
		$eEquipmentAwake = $this->follow($eStructure, $eTool, 600, \Clock::date());

		$this->assertTrue($fw->ok());
		$this->assertEquals($eEquipment['id'], $eEquipmentAwake['id'], 'le même matériel se réveille');
		$this->assertEquals(Equipment::ACTIVE, Equipment::model()->whereId($eEquipment)->getValue('status'));
		$this->assertEquals(2, EquipmentReading::model()->whereEquipment($eEquipment)->count(), 'il retrouve ses relevés');

	}

	/**
	 * Un matériel qui a des relevés ne passe plus des heures aux kilomètres (planche pf-t4-v3, Q3).
	 * Couper le compteur reste ouvert, revenir à son unité aussi, et un matériel qu'on suit de nouveau
	 * garde l'unité de ses relevés.
	 */
	public function testMeterKeepsTheUnitOfItsReadings(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Camion');
		$eEquipment = $this->follow($eStructure, $eTool, 1200, \Clock::date());

		$this->assertEquals(EquipmentReading::HOURS, EquipmentReading::model()->whereEquipment($eEquipment)->getValue('meter'), 'le relevé garde l\'unité du matériel');

		$buildMeter = function(string $meter) use($eEquipment): \FailWatch {

			$fw = new \FailWatch();

			EquipmentLib::getById($eEquipment['id'])->build(['meter'], ['meter' => $meter], new \PropertyList('update'));

			return $fw;

		};

		$this->assertTrue($buildMeter(Equipment::KILOMETERS)->has('Equipment::meter.locked'), 'des heures aux kilomètres');
		$this->assertTrue($buildMeter('')->ok(), 'couper le compteur');
		$this->assertTrue($buildMeter(Equipment::HOURS)->ok(), 'garder son unité');

		$eEquipment['meter'] = NULL;
		EquipmentLib::update($eEquipment, ['meter']);

		$this->assertTrue($buildMeter(Equipment::KILOMETERS)->has('Equipment::meter.locked'), 'un compteur coupé ne revient qu\'à son unité');
		$this->assertTrue($buildMeter(Equipment::HOURS)->ok(), 'revenir à son unité');

		$eEquipment['status'] = Equipment::INACTIVE;
		EquipmentLib::update($eEquipment, ['status']);

		$fw = new \FailWatch();
		$this->follow($eStructure, $eTool, NULL, \Clock::date(), Equipment::KILOMETERS);

		$this->assertTrue($fw->has('Equipment::meter.locked'), 'suivi de nouveau, il garde l\'unité de ses relevés');
		$this->assertEquals(Equipment::INACTIVE, Equipment::model()->whereId($eEquipment)->getValue('status'), 'le refus ne réveille pas le matériel');

	}

	/**
	 * Sans aucun relevé, la « Prochaine fois » au compteur d'un entretien suffit à tenir l'unité (décidé sous mandat
	 * après la recette de la planche pf-t4-v3) : ses 700 km deviendraient 700 h. Le compteur coupé puis
	 * repris, l'unité est la dernière qu'il portait (`lastMeter`), y compris quand elle a changé depuis le
	 * suivi.
	 */
	public function testMeterKeepsTheUnitOfItsMaintenanceValues(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Tracteur');
		$eEquipment = $this->follow($eStructure, $eTool, NULL, \Clock::date());

		$buildMeter = function(string $meter) use($eEquipment): \FailWatch {

			$fw = new \FailWatch();

			EquipmentLib::getById($eEquipment['id'])->build(['meter'], ['meter' => $meter], new \PropertyList('update'));

			return $fw;

		};

		$this->assertTrue($buildMeter(Equipment::KILOMETERS)->ok(), 'sans aucune valeur, l\'unité change encore');

		$eEquipment['meter'] = Equipment::KILOMETERS;
		EquipmentLib::update($eEquipment, ['meter']);

		// Sans `build()` : l'entretien et sa valeur sont le décor, pas ce que le test éprouve.
		MaintenanceLib::create(new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 15000,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 15700,
		]));

		$this->assertTrue($buildMeter(Equipment::HOURS)->has('Equipment::meter.locked'), 'des kilomètres aux heures avec une « Prochaine fois » au compteur');
		$this->assertTrue($buildMeter('')->ok(), 'couper le compteur');

		$eEquipment['meter'] = NULL;
		EquipmentLib::update($eEquipment, ['meter']);

		$this->assertEquals(Equipment::KILOMETERS, Equipment::model()->whereId($eEquipment)->getValue('lastMeter'), 'le compteur coupé garde sa dernière unité');
		$this->assertTrue($buildMeter(Equipment::HOURS)->has('Equipment::meter.locked'), 'repris, il ne revient qu\'à son unité');
		$this->assertTrue($buildMeter(Equipment::KILOMETERS)->ok(), 'revenir aux kilomètres');

	}

	/**
	 * Le bloc d'un matériel ne montre que les entretiens qui ont une échéance (retour du
	 * 30/09) : celui qui ne revient qu'au compteur en sort quand le compteur est coupé, et y revient
	 * avec lui.
	 */
	public function testScheduledNeedsADue(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 100, \Clock::date());

		// Sans `build()` : les entretiens sont le décor, pas ce que le test éprouve.
		foreach([['Vidange moteur', 400, NULL], ['Contrôle technique', NULL, 12]] as [$name, $everyValue, $everyMonths]) {
			MaintenanceLib::create(new Maintenance([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'name' => $name,
				'everyValue' => $everyValue,
				'everyMonths' => $everyMonths,
				'plannedDate' => ($everyMonths === NULL) ? NULL : MaintenanceLib::addMonths(\Clock::date(), $everyMonths),
				'plannedValue' => ($everyValue === NULL) ? NULL : 500,
			]));
		}

		$getScheduled = fn() => array_values(EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenanceScheduled']->getColumn('name'));

		$this->assertEquals(['Vidange moteur', 'Contrôle technique'], $getScheduled(), 'avec le compteur, les deux ont une échéance');

		$eEquipment['meter'] = NULL;
		EquipmentLib::update($eEquipment, ['meter']);

		$this->assertEquals(['Contrôle technique'], $getScheduled(), 'compteur coupé, la vidange au compteur seul sort du bloc');

		$eEquipment['meter'] = Equipment::HOURS;
		EquipmentLib::update($eEquipment, ['meter']);

		$this->assertEquals(['Vidange moteur', 'Contrôle technique'], $getScheduled(), 'compteur repris, elle revient');

	}

	/**
	 * Un entretien planifié au compteur seul sur un matériel sans relevé a une échéance et pas encore
	 * de reste : le bloc du matériel le montre (« à 400 h ») et le PDF avec lui, mais rien ne le compte
	 * comme dû, ni le bandeau « Entretiens à faire », ni le compteur rouge de l'onglet, ni la
	 * notification. Le premier relevé lui donne son reste.
	 */
	public function testScheduledBeforeTheFirstReading(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Motoculteur'), NULL, \Clock::date());

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 400,
		]);
		MaintenanceLib::create($eMaintenance);

		$cEquipment = EquipmentLib::getByStructure($eStructure);
		$eEquipmentRead = $cEquipment[$eEquipment['id']];
		$eMaintenanceRead = $eEquipmentRead['cMaintenance'][$eMaintenance['id']];

		$this->assertEquals(['Vidange moteur'], array_values($eEquipmentRead['cMaintenanceScheduled']->getColumn('name')), 'sans relevé, l\'entretien au compteur seul est dans le bloc');
		$this->assertEquals(NULL, $eMaintenanceRead['dueBy'], 'aucune échéance ne décide encore');
		$this->assertEquals(NULL, $eMaintenanceRead['due'], 'il n\'est ni bientôt dû ni en retard');
		$this->assertEquals(NULL, $eMaintenanceRead['remainingValue'], 'son reste ne se calcule pas');
		$this->assertTrue($eEquipmentRead->acceptDownload(), 'le carnet a quelque chose à montrer');

		$this->assertEquals(0, EquipmentLib::getDue($cEquipment)->count(), 'le bandeau « Entretiens à faire » ne le compte pas');
		$this->assertEquals(0, $eEquipmentRead['overdue'], 'le compteur rouge de l\'onglet ne le compte pas');
		$this->assertTrue(NotificationLib::describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance['id'])->empty(), 'aucune notification à en dire');

		// Sans `build()` : le relevé est le décor.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => \Clock::date(),
			'value' => 120,
		]));

		$eMaintenanceRead = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenanceScheduled'][$eMaintenance['id']];

		$this->assertEquals('value', $eMaintenanceRead['dueBy'], 'au premier relevé, le compteur décide');
		$this->assertEquals('dans 280 h', strip_tags(MaintenanceUi::getDueText($eMaintenanceRead, near: FALSE)), 'et le reste se dit');

	}

	/**
	 * Le premier relevé d'un matériel fait partir de lui les entretiens réguliers au compteur qu'il
	 * mettrait en retard (retour du 02/10/2026 : une vidange toutes les 400 h, premier relevé
	 * à 500 h, due à 900 h « pour éviter du rouge direct »). Seule la part au compteur d'un entretien
	 * régulier repart, et seulement au premier relevé.
	 */
	public function testFirstReadingStartsOverdueMaintenances(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Motoculteur'), NULL, \Clock::date());

		// Sans `build()` : les entretiens sont le décor, et le test veut leurs valeurs exactes.
		$create = function(string $name, ?int $everyValue, ?int $everyMonths, ?int $plannedValue, ?string $plannedDate) use($eStructure, $eEquipment): Maintenance {

			$eMaintenance = new Maintenance([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'name' => $name,
				'everyValue' => $everyValue,
				'everyMonths' => $everyMonths,
				'plannedDate' => $plannedDate,
				'plannedValue' => $plannedValue,
			]);
			MaintenanceLib::create($eMaintenance);

			return $eMaintenance;

		};

		$eMaintenanceLate = $create('Vidange moteur', 400, NULL, 400, NULL);
		$eMaintenanceEqual = $create('Graissage', 400, NULL, 500, NULL);
		$eMaintenanceAhead = $create('Filtre à air', 400, NULL, 600, NULL);
		$eMaintenanceOnce = $create('Rodage', NULL, NULL, 300, NULL);
		$eMaintenanceBoth = $create('Courroie', 400, 12, 400, '2026-05-01');
		$eMaintenanceDeleted = $create('Bougie', 400, NULL, 400, NULL);

		$eMaintenanceDeleted['status'] = Maintenance::DELETED;
		MaintenanceLib::update($eMaintenanceDeleted, ['status']);

		$get = fn(Maintenance $eMaintenance) => Maintenance::model()
			->select(['plannedValue', 'plannedDate', 'ignoredDate'])
			->whereId($eMaintenance['id'])
			->get();

		$read = fn(string $date, int $value) => EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => $date,
			'value' => $value,
		]));

		$read('2026-06-10', 500);

		$this->assertEquals(900, $get($eMaintenanceLate)['plannedValue'], 'en retard au premier relevé, l\'entretien régulier repart du relevé');
		$this->assertEquals(900, $get($eMaintenanceEqual)['plannedValue'], 'à égalité aussi : dû au relevé même, il repart du relevé');
		$this->assertEquals(600, $get($eMaintenanceAhead)['plannedValue'], 'une « Prochaine fois » posée au-dessus du relevé reste');
		$this->assertEquals(300, $get($eMaintenanceOnce)['plannedValue'], '« Une seule fois » n\'a pas d\'intervalle d\'où repartir');
		$this->assertEquals(900, $get($eMaintenanceBoth)['plannedValue'], 'dû à la date et au compteur, la part au compteur repart');
		$this->assertEquals('2026-05-01', $get($eMaintenanceBoth)['plannedDate'], 'et la date ne bouge pas');
		$this->assertEquals(400, $get($eMaintenanceDeleted)['plannedValue'], 'un entretien retiré ne repart pas');
		$this->assertEquals(NULL, $get($eMaintenanceLate)['ignoredDate'], 'ce n\'est pas un retard ignoré');

		$eEquipmentRead = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']];

		$this->assertEquals(0, $eEquipmentRead['cMaintenanceDone']->count(), 'rien n\'entre à l\'historique');
		$this->assertEquals(NULL, $eEquipmentRead['cMaintenance'][$eMaintenanceLate['id']]['due'], 'aucun retard au premier relevé');
		$this->assertEquals(MaintenanceLib::OVERDUE, $eEquipmentRead['cMaintenance'][$eMaintenanceOnce['id']]['due'], '« Une seule fois » dépassée reste en retard');
		$this->assertEquals(MaintenanceLib::OVERDUE, $eEquipmentRead['cMaintenance'][$eMaintenanceBoth['id']]['due'], 'la date dépassée reste un retard');
		$this->assertEquals('date', $eEquipmentRead['cMaintenance'][$eMaintenanceBoth['id']]['dueBy'], 'dû par sa date');

		// Un entretien planifié alors qu'un relevé existe garde ce que la personne a tapé.
		$eMaintenanceAfter = $create('Chaîne', 400, NULL, 100, NULL);

		$read('2026-06-12', 950);

		$this->assertEquals(900, $get($eMaintenanceLate)['plannedValue'], 'un second relevé ne fait rien repartir');
		$this->assertEquals(100, $get($eMaintenanceAfter)['plannedValue'], 'ni un entretien planifié après le premier');
		$this->assertEquals(MaintenanceLib::OVERDUE, EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenanceLate['id']]['due'], 'le retard se dit alors');

		$read('2026-06-10', 480);

		$this->assertEquals(900, $get($eMaintenanceLate)['plannedValue'], 'le premier relevé corrigé ne fait rien repartir non plus');

	}

	/**
	 * Le premier relevé peut venir du compteur d'un entretien fait : les autres entretiens du matériel
	 * repartent de lui, et l'entretien fait repart comme après tout entretien fait.
	 */
	public function testFirstReadingFromADoneStartsTheOthers(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Motoculteur'), NULL, \Clock::date());

		// Sans `build()` : les deux entretiens sont le décor.
		$eMaintenanceDone = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 400,
		]);
		MaintenanceLib::create($eMaintenanceDone);

		$eMaintenanceOther = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Graissage',
			'everyValue' => 250,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 300,
		]);
		MaintenanceLib::create($eMaintenanceOther);

		// Sans `build()` : la ligne porte le compteur que « Mettre à jour le compteur : Oui » aurait
		// construit, et aucune tâche du planning, que ce test n'éprouve pas.
		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenanceDone['id']],
			'date' => \Clock::date(),
			'meterUpdate' => TRUE,
			'value' => 500,
			'cost' => NULL,
			'comment' => NULL,
		]));

		$cMaintenance = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'];

		$this->assertEquals(500, EquipmentReadingLib::getByEquipmentAndDate($eEquipment, \Clock::date())['value'], 'le compteur de l\'entretien fait est le premier relevé');
		$this->assertEquals(750, $cMaintenance[$eMaintenanceOther['id']]['nextValue'], 'l\'autre entretien, que ce relevé mettait en retard, repart de lui');
		$this->assertEquals(NULL, $cMaintenance[$eMaintenanceOther['id']]['due'], 'sans retard');
		$this->assertEquals(900, $cMaintenance[$eMaintenanceDone['id']]['nextValue'], 'l\'entretien fait repart de son compteur');

	}

	/**
	 * Le compteur d'un entretien fait ne se saisit pas : il est le dernier relevé au jour de l'entretien
	 * ou avant, et aucun relevé ne naît de l'entretien (retour du 30/09). Noté avant tout
	 * relevé, il n'a pas de compteur, et son échéance au compteur part du premier relevé d'après.
	 */
	public function testDoneTakesTheMeterFromReadings(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => \Clock::date(), 'value' => 1250]));

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 1300,
		]);
		MaintenanceLib::create($eMaintenance);

		$create = function(string $date) use($eStructure, $eEquipment, $eMaintenance): MaintenanceDone {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => $eMaintenance,
			]);

			$fw = new \FailWatch();
			$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['date' => $date, 'value' => '5000', 'cost' => '', 'comment' => '']);
			$this->assertTrue($fw->ok(), 'un entretien fait se construit sans compteur');

			MaintenanceDoneLib::create($eMaintenanceDone);

			return $eMaintenanceDone;

		};

		$getMaintenance = fn() => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenance['id']];

		$this->assertEquals(NULL, $create('2026-05-20')['value'], 'avant tout relevé, pas de compteur');
		$this->assertEquals([NULL, 1400], [$getMaintenance()['done']['value'], $getMaintenance()['nextValue']], 'la ligne ne dit pas de compteur, l\'échéance part du premier relevé d\'après');

		$this->assertEquals(1000, $create('2026-06-10')['value'], 'le relevé d\'avant le jour de l\'entretien, pas celui d\'après ni le compteur posté');
		$this->assertEquals(1250, $create(\Clock::date())['value'], 'le relevé du jour');
		$this->assertEquals(2, EquipmentReading::model()->whereEquipment($eEquipment)->count(), 'aucun relevé ne naît d\'un entretien fait sans mise à jour du compteur');

	}

	/**
	 * « Mettre à jour le compteur » (retour du 30/09) : Oui, le compteur saisi
	 * devient le relevé du jour de l'entretien, avec les règles d'un relevé ; Non, il est lu dans les
	 * relevés, quoi que le POST porte.
	 */
	public function testDoneUpdatesTheMeter(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-06-10', 'value' => 1250]));

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 1300,
		]);
		MaintenanceLib::create($eMaintenance);

		$build = function(string $date, string $meterUpdate, string $value) use($eStructure, $eEquipment, $eMaintenance): array {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => $eMaintenance,
			]);

			$fw = new \FailWatch();
			$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['date' => $date, 'meterUpdate' => $meterUpdate, 'value' => $value, 'cost' => '', 'comment' => '']);

			return [$eMaintenanceDone, $fw];

		};

		$countReadings = fn() => EquipmentReading::model()->whereEquipment($eEquipment)->count();

		[, $fw] = $build(\Clock::date(), '1', '');
		$this->assertTrue($fw->has('MaintenanceDone::value.required'), 'Oui demande un compteur');

		[, $fw] = $build('2026-06-12', '1', '1200');
		$this->assertTrue($fw->has('MaintenanceDone::value.backwards'), 'le compteur ne recule pas sous le relevé d\'avant sans confirmation');

		[, $fw] = $build('2026-06-05', '1', '1300');
		$this->assertTrue($fw->has('MaintenanceDone::value.ahead'), 'le compteur ne dépasse pas le relevé d\'après sans confirmation');

		[$eMaintenanceDone, $fw] = $build(\Clock::date(), '1', '1320');
		$this->assertTrue($fw->ok(), 'un compteur plus haut que le dernier relevé passe');

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(1320, $eMaintenanceDone['value'], 'l\'entretien fait prend le compteur saisi');
		$this->assertEquals(3, $countReadings(), 'le compteur saisi devient un relevé');
		$this->assertEquals(1320, EquipmentReadingLib::getByEquipmentAndDate($eEquipment, \Clock::date())['value'], 'au jour de l\'entretien');

		$eMaintenanceFilled = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenance['id']];
		$this->assertEquals(1720, $eMaintenanceFilled['nextValue'], 'l\'échéance repart du compteur saisi');

		[$eMaintenanceDone, $fw] = $build(\Clock::date(), '0', '9999');
		$this->assertTrue($fw->ok(), 'Non ne juge pas le compteur posté');

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(1320, $eMaintenanceDone['value'], 'Non lit le compteur dans les relevés');
		$this->assertEquals(3, $countReadings(), 'Non ne crée pas de relevé');

	}

	/**
	 * L'historique porte les relevés (retour du 30/09), sauf celui que dit déjà un entretien
	 * fait du même jour au même compteur. Un relevé s'y corrige entre ses voisins.
	 */
	public function testHistoryShowsTheReadings(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-06-10', 'value' => 1250]));

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 1300,
		]);
		MaintenanceLib::create($eMaintenance);

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
		]);

		$fw = new \FailWatch();
		$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['date' => \Clock::date(), 'meterUpdate' => '1', 'value' => '1320', 'cost' => '', 'comment' => '']);
		$this->assertTrue($fw->ok());

		MaintenanceDoneLib::create($eMaintenanceDone);

		$history = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['history'];

		$this->assertEquals(
			[['MaintenanceDone', '2026-06-15', 1320], ['EquipmentReading', '2026-06-10', 1250], ['EquipmentReading', '2026-06-01', 1000]],
			array_map(fn($eHistory) => [new \ReflectionClass($eHistory)->getShortName(), $eHistory['date'], $eHistory['value']], $history),
			'l\'entretien fait, puis les relevés, du plus récent au plus ancien, sans répéter celui qu\'il a posé'
		);

		$correct = function(string $value) use($history): \FailWatch {

			$eEquipmentReading = EquipmentReadingLib::getById($history[1]['id'], ['id', 'structure', 'date', 'value', 'equipment' => ['structure', 'meter', 'status']]);

			$fw = new \FailWatch();
			$eEquipmentReading->build(EquipmentReadingLib::getPropertiesUpdate(), ['value' => $value], new \PropertyList('update'));

			if($fw->ok()) {
				EquipmentReadingLib::update($eEquipmentReading, EquipmentReadingLib::getPropertiesUpdate());
			}

			return $fw;

		};

		$this->assertTrue($correct('900')->has('EquipmentReading::value.backwards'), 'corrigé, un relevé ne passe pas sous celui d\'avant sans confirmation');
		$this->assertTrue($correct('1400')->has('EquipmentReading::value.ahead'), 'ni au-dessus de celui d\'après');
		$this->assertTrue($correct('1200')->ok(), 'entre ses voisins, il se corrige');
		$this->assertEquals(1200, EquipmentReadingLib::getById($history[1]['id'])['value']);

	}

	/**
	 * Un relevé qui sort de l'ordre du compteur passe quand la personne le confirme, car il peut corriger
	 * une erreur (retour du 01/10). Sans le drapeau, il est refusé, et le refus porte de quoi
	 * poser la question : le relevé contredit et celui qu'on veut enregistrer.
	 */
	public function testReadingOutOfOrderIsConfirmed(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-06-10', 'value' => 1250]));

		$build = function(string $date, string $value, string $confirmed) use($eStructure, $eEquipment): array {

			$eEquipmentReading = new EquipmentReading(['structure' => $eStructure]);

			$fw = new \FailWatch();
			$eEquipmentReading->build(EquipmentReadingLib::getPropertiesCreate(), [
				'equipment' => $eEquipment['id'],
				'date' => $date,
				'value' => $value,
				'meterConfirmed' => $confirmed,
			]);

			return [$eEquipmentReading, $fw];

		};

		[, $fw] = $build(\Clock::date(), '1100', '0');
		$eEquipmentReadingConflict = EquipmentReadingLib::getConflictFromFail($fw);

		$this->assertEquals(['2026-06-10', 1250], [$eEquipmentReadingConflict['date'], $eEquipmentReadingConflict['value']], 'plus bas, la question rappelle le relevé d\'avant');
		$this->assertEquals([\Clock::date(), 1100], [$eEquipmentReadingConflict['eEquipmentReadingNew']['date'], $eEquipmentReadingConflict['eEquipmentReadingNew']['value']], 'et ce que la personne veut enregistrer');

		[, $fw] = $build('2026-06-05', '1300', '0');
		$eEquipmentReadingConflict = EquipmentReadingLib::getConflictFromFail($fw);

		$this->assertTrue($fw->has('EquipmentReading::value.ahead'));
		$this->assertEquals(['2026-06-10', 1250], [$eEquipmentReadingConflict['date'], $eEquipmentReadingConflict['value']], 'plus haut, elle rappelle le relevé d\'après');

		// Un autre refus se corrige d'abord sous son champ : pas de question tant qu'il reste.
		[, $fw] = $build(\Clock::date(), '1100', '0');
		EquipmentReading::fail('equipment.owned');
		$this->assertTrue(EquipmentReadingLib::getConflictFromFail($fw)->empty(), 'pas de question à côté d\'un autre refus');

		[$eEquipmentReading, $fw] = $build(\Clock::date(), '1100', '1');
		$this->assertTrue($fw->ok(), 'confirmé, le relevé plus bas passe');

		EquipmentReadingLib::create($eEquipmentReading);

		$eEquipmentFilled = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']];

		$this->assertEquals(1100, $eEquipmentFilled['cEquipmentReading']->last()['value'], 'il devient le compteur du matériel');
		$this->assertEquals(3, $eEquipmentFilled['cEquipmentReading']->count(), 'le relevé plus haut reste à l\'historique');

		[, $fw] = $build('2026-06-05', '1300', '1');
		$this->assertTrue($fw->ok(), 'confirmé, le relevé plus haut passe aussi');

	}

	/**
	 * « Fait » qui met le compteur à jour pose la même question qu'un relevé, et « Suivre un matériel »
	 * sur un matériel qui revient sous son dernier relevé aussi.
	 */
	public function testDoneAndFollowConfirmAReadingOutOfOrder(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-06-10', 'value' => 1250]));

		$build = function(string $confirmed) use($eStructure, $eEquipment): array {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => new Maintenance(),
			]);

			$fw = new \FailWatch();
			$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['name' => 'Graissage', 'date' => \Clock::date(), 'meterUpdate' => '1', 'value' => '1200', 'cost' => '', 'comment' => '', 'meterConfirmed' => $confirmed]);

			return [$eMaintenanceDone, $fw];

		};

		[, $fw] = $build('0');
		$eEquipmentReadingConflict = EquipmentReadingLib::getConflictFromFail($fw);

		$this->assertTrue($fw->has('MaintenanceDone::value.backwards'));
		$this->assertEquals(1250, $eEquipmentReadingConflict['value'], 'la question rappelle le relevé d\'avant');
		$this->assertEquals(1200, $eEquipmentReadingConflict['eEquipmentReadingNew']['value']);

		[$eMaintenanceDone, $fw] = $build('1');
		$this->assertTrue($fw->ok(), 'confirmé, l\'entretien fait passe');

		MaintenanceDoneLib::create($eMaintenanceDone);
		$this->assertEquals(1200, EquipmentReadingLib::getByEquipmentAndDate($eEquipment, \Clock::date())['value'], 'et son compteur devient le relevé du jour');

		$eTool = $this->createTool($eStructure, 'Motoculteur');
		$eEquipmentAsleep = $this->follow($eStructure, $eTool, 500, '2026-06-01');

		$eEquipmentAsleep['status'] = Equipment::INACTIVE;
		EquipmentLib::update($eEquipmentAsleep, ['status']);

		$follow = function(bool $confirmed) use($eStructure, $eTool): Equipment {

			$eEquipment = new Equipment([
				'structure' => $eStructure,
				'tool' => $eTool,
				'meter' => Equipment::HOURS,
				'eToolNew' => new \work\Tool(),
			]);

			EquipmentLib::follow($eEquipment, new EquipmentReading(['structure' => $eStructure, 'date' => \Clock::date(), 'value' => 400]), $confirmed);

			return $eEquipment;

		};

		$fw = new \FailWatch();
		$follow(FALSE);
		$eEquipmentReadingConflict = EquipmentReadingLib::getConflictFromFail($fw);

		$this->assertEquals(['2026-06-01', 500], [$eEquipmentReadingConflict['date'], $eEquipmentReadingConflict['value']], 'le retour rappelle le dernier relevé du matériel qui dort');
		$this->assertEquals(400, $eEquipmentReadingConflict['eEquipmentReadingNew']['value']);

		$fw = new \FailWatch();
		$eEquipmentAwake = $follow(TRUE);

		$this->assertTrue($fw->ok(), 'confirmé, le matériel revient');
		$this->assertEquals($eEquipmentAsleep['id'], $eEquipmentAwake['id']);
		$this->assertEquals(400, EquipmentReadingLib::getByEquipmentAndDate($eEquipmentAsleep, \Clock::date())['value']);

	}

	/**
	 * Une période que traverse un relevé confirmé plus bas ne dit pas ce que le matériel a tourné.
	 */
	public function testYearIgnoresACounterGoingBack(): void {

		$this->setClock('2026-06-15 10:00:00');

		$cEquipmentReading = new \Collection([
			new EquipmentReading(['date' => '2025-12-01', 'value' => 1000]),
			new EquipmentReading(['date' => '2026-02-01', 'value' => 1200]),
			new EquipmentReading(['date' => '2026-06-10', 'value' => 900]),
		]);

		[$valueYear] = EquipmentReadingLib::getYear($cEquipmentReading);
		$this->assertEquals(NULL, $valueYear, 'pas de compteur négatif depuis le 1er janvier');

		$cEquipmentReading[2]['value'] = 1300;

		[$valueYear] = EquipmentReadingLib::getYear($cEquipmentReading);
		$this->assertEquals(200, $valueYear, 'le même calcul rend un compteur quand il avance');

	}

	/**
	 * La courbe du compteur apparaît dès le deuxième relevé, avec un point par relevé du plus ancien au
	 * plus récent : un relevé seul ne trace rien, et un matériel sans compteur n'a pas de courbe.
	 */
	public function testChartNeedsTwoReadings(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-05-20');

		$render = function() use ($eStructure, $eEquipment): string {
			$cEquipment = EquipmentLib::getByStructure($eStructure)->find(fn($eEquipmentFound) => $eEquipmentFound['id'] === $eEquipment['id']);
			return new EquipmentUi()->getEquipments($eStructure, $cEquipment, new Equipment());
		};

		// Un décor n'a pas de `$_POST` à valider : `build()` vérifierait la date et le compteur, que le test choisit.
		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-06-10', 'value' => 1021]));
		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => '2026-04-02', 'value' => 950]));

		$html = html_entity_decode($render());

		$this->assertTrue(str_contains($html, 'UtilChart.createTimeLine(this, [950,1000,1021], ["2026-04-02","2026-05-20","2026-06-10"], "h")'), 'trois relevés, du plus ancien au plus récent, avec leur unité');

	}

	/**
	 * « Consigner un entretien » (retour du 30/09) : un libellé libre, sans entretien, qui
	 * n'entre qu'à l'historique et ne fait repartir aucune échéance. Un entretien fait garde le nom de
	 * son entretien, quoi que le POST porte.
	 */
	public function testInterventionEntersOnlyTheHistory(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configure($eStructure);
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-06-01');

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => NULL,
			'everyMonths' => 12,
			'plannedDate' => '2027-05-01',
			'plannedValue' => NULL,
		]);
		MaintenanceLib::create($eMaintenance);

		$build = function(Maintenance $eMaintenance, string $name) use($eStructure, $eEquipment): array {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => $eMaintenance,
			]);

			$fw = new \FailWatch();
			$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['name' => $name, 'date' => \Clock::date(), 'meterUpdate' => '0', 'value' => '', 'cost' => '80', 'comment' => '']);

			return [$eMaintenanceDone, $fw];

		};

		[, $fw] = $build(new Maintenance(), '');
		$this->assertTrue($fw->has('MaintenanceDone::name.required'), 'une intervention se décrit');

		[$eMaintenanceDone, $fw] = $build(new Maintenance(), 'Changement de courroie');
		$this->assertTrue($fw->ok());

		MaintenanceDoneLib::create($eMaintenanceDone);

		$eEquipmentFilled = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']];
		$eMaintenanceFilled = $eEquipmentFilled['cMaintenance'][$eMaintenance['id']];

		$this->assertEquals('Changement de courroie', $eEquipmentFilled['history'][0]->getName(), 'l\'intervention entre à l\'historique sous son libellé');
		$this->assertEquals(1000, $eEquipmentFilled['history'][0]['value'], 'avec le compteur du jour');
		$this->assertTrue($eMaintenanceFilled['done']->empty(), 'elle ne fait repartir aucun entretien');
		$this->assertEquals('2027-05-01', $eMaintenanceFilled['nextDate']);

		[$eMaintenanceDone, $fw] = $build($eMaintenance, 'Autre chose');
		$this->assertTrue($fw->ok());
		$this->assertEquals(NULL, $eMaintenanceDone['name'], 'un entretien fait n\'a pas de libellé propre');
		$this->assertEquals('Vidange moteur', $eMaintenanceDone->getName());

	}

	/**
	 * Sur un matériel sans compteur, Oui n'a rien à mettre à jour.
	 */
	public function testDoneWithoutMeterRefusesTheUpdate(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Débroussailleuse'), NULL, \Clock::date(), NULL);

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Affûtage',
			'everyValue' => NULL,
			'everyMonths' => 6,
			'plannedDate' => '2026-11-01',
			'plannedValue' => NULL,
		]);
		MaintenanceLib::create($eMaintenance);

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
		]);

		$fw = new \FailWatch();
		$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['date' => \Clock::date(), 'meterUpdate' => '1', 'value' => '10', 'cost' => '', 'comment' => '']);

		$this->assertTrue($fw->has('MaintenanceDone::value.meter'), 'pas de compteur à mettre à jour');

	}

	/**
	 * « Ignorer ce retard » enlève l'alerte pour ce tour : l'échéance repart du compteur et du jour,
	 * la dernière fois et l'historique ne bougent pas, et l'alerte revient quand la nouvelle échéance
	 * est dépassée. Le prochain entretien fait efface l'ignoré (retour du 30/09).
	 */
	public function testIgnoredOverdueComesBackAtTheNextDue(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1250, \Clock::date());

		// Sans `build()` : un graissage toutes les 150 h, prévu à 250 h, donc dépassé de 1 000 h.
		$eMaintenanceCreated = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Graissage',
			'everyValue' => 150,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 250,
		]);
		MaintenanceLib::create($eMaintenanceCreated);

		$get = fn() => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenanceCreated['id']];
		$countOverdue = fn() => EquipmentLib::getSummary($eStructure)['overdue'];

		$eMaintenance = $get();

		$this->assertEquals(MaintenanceLib::OVERDUE, $eMaintenance['due'], 'le décor part d\'un retard');
		$this->assertTrue($eMaintenance->acceptIgnore(), 'un retard s\'ignore');
		$this->assertEquals(1, $countOverdue());
		$this->assertEquals(NULL, EquipmentLib::getSummary($eStructure)['lastDone'], 'la carte n\'a pas de dernier entretien tant que rien n\'a été fait');

		MaintenanceLib::ignore($eMaintenance);

		$eMaintenance = $get();

		$this->assertEquals(NULL, $eMaintenance['due'], 'l\'alerte est enlevée');
		$this->assertEquals(1400, $eMaintenance['nextValue'], 'l\'échéance repart du compteur du jour');
		$this->assertEquals(\Clock::date(), $eMaintenance['ignoredDate'], 'la ligne dit le retard ignoré');
		$this->assertEquals(0, EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenanceDone']->count(), 'rien n\'entre dans l\'historique');
		$this->assertEquals(0, $countOverdue(), 'ni bandeau ni carte');
		$this->assertFalse($eMaintenance->acceptIgnore(), 'sans retard, rien à ignorer');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => \Clock::date(), 'value' => 1400]));

		$this->assertEquals(MaintenanceLib::SOON, $get()['due'], 'à l\'échéance, pas encore en retard');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => \Clock::date(), 'value' => 1401]));

		$this->assertEquals(MaintenanceLib::OVERDUE, $get()['due'], 'l\'échéance suivante dépassée, l\'alerte revient');
		$this->assertEquals(1, $countOverdue());

		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $get(),
			'date' => \Clock::date(),
			'value' => 1401,
			'cost' => NULL,
			'comment' => NULL,
		]));

		$eMaintenance = $get();

		$this->assertEquals(NULL, $eMaintenance['ignoredDate'], 'l\'entretien fait efface l\'ignoré');
		$this->assertEquals(1551, $eMaintenance['nextValue'], 'et porte l\'échéance');
		$this->assertEquals(\Clock::date(), EquipmentLib::getSummary($eStructure)['lastDone'], 'la carte porte le jour du dernier entretien fait');

	}

	/**
	 * À la date, le retard ignoré fait repartir l'échéance du jour.
	 */
	public function testIgnoredOverdueByDate(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Broyeur'), NULL, \Clock::date());

		$eEquipment['meter'] = NULL;
		EquipmentLib::update($eEquipment, ['meter']);

		// Sans `build()` : un affûtage annuel prévu il y a un mois et demi.
		$eMaintenanceCreated = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Affûtage',
			'everyValue' => NULL,
			'everyMonths' => 12,
			'plannedDate' => '2026-05-01',
			'plannedValue' => NULL,
		]);
		MaintenanceLib::create($eMaintenanceCreated);

		$get = fn() => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenanceCreated['id']];

		MaintenanceLib::ignore($get());

		$eMaintenance = $get();

		$this->assertEquals([NULL, '2027-06-15', '2026-06-15'], [$eMaintenance['due'], $eMaintenance['nextDate'], $eMaintenance['ignoredDate']], 'l\'échéance repart du jour');

		$this->setClock('2027-06-16 10:00:00');

		$this->assertEquals(MaintenanceLib::OVERDUE, $get()['due'], 'un an plus tard, l\'alerte revient');

	}

	/**
	 * L'historique d'un matériel se lit par pages de `ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE` lignes,
	 * les plus récentes d'abord. Une page au-delà de la dernière retombe sur elle, et la courbe garde tous
	 * les relevés quelle que soit la page.
	 */
	public function testHistoryIsPaged(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-05-01');

		foreach(range(2, 25) as $day) {

			// Un décor n'a pas de `$_POST` à valider : `build()` vérifierait la date et le compteur, que le test choisit.
			EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => sprintf('2026-05-%02d', $day), 'value' => 1000 + $day]));

		}

		$get = fn(array $pages) => EquipmentLib::getByStructure($eStructure, $pages)[$eEquipment['id']];

		$eEquipmentFirst = $get([]);

		$this->assertEquals(2, $eEquipmentFirst['historyPages'], '25 relevés tiennent sur deux pages');
		$this->assertEquals(ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE, count($eEquipmentFirst['history']), 'la première page est pleine');
		$this->assertEquals('2026-05-25', $eEquipmentFirst['history'][0]['date'], 'elle commence par le relevé le plus récent');

		$eEquipmentSecond = $get([$eEquipment['id'] => 1]);

		$this->assertEquals(5, count($eEquipmentSecond['history']), 'la seconde porte le reste');
		$this->assertEquals('2026-05-01', $eEquipmentSecond['history'][4]['date'], 'elle finit par le premier relevé');
		$this->assertEquals(25, $eEquipmentSecond['cEquipmentReading']->count(), 'la courbe garde tous les relevés');

		$this->assertEquals(1, $get([$eEquipment['id'] => 9])['historyPage'], 'une page au-delà de la dernière retombe sur elle');

		$html = new EquipmentUi()->getEquipments($eStructure, new \Collection([$eEquipmentSecond]), new Equipment());

		$this->assertTrue(str_contains($html, 'id="maintenance-history-'.$eEquipment['id'].'"'), 'l\'historique porte le nœud que la pagination remplace');
		$this->assertTrue(str_contains($html, '/toolbox/equipment:history?id='.$eEquipment['id'].'&page=1'), 'la pagination mène à la page du matériel');

	}

	/**
	 * L'export d'un matériel emporte tout l'historique, là où l'écran n'en lit qu'une page : le CSV en
	 * fait une ligne par ligne de l'historique, le PDF un tableau sans pagination. Une autre structure
	 * n'en obtient rien.
	 */
	public function testExportCarriesTheWholeHistory(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, '2026-05-01');

		foreach(range(2, 25) as $day) {

			// Un décor n'a pas de `$_POST` à valider : `build()` vérifierait la date et le compteur, que le test choisit.
			EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipment, 'date' => sprintf('2026-05-%02d', $day), 'value' => 1000 + $day]));

		}

		// Sans `build()` : l'entretien et les deux lignes de l'historique sont le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 1200,
		]);
		MaintenanceLib::create($eMaintenance);

		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
			'name' => NULL,
			'date' => '2026-06-10',
			'cost' => 80.5,
			'comment' => NULL,
		]));

		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => new Maintenance(),
			'name' => 'Changement de courroie',
			'date' => '2026-06-12',
			'cost' => NULL,
			'comment' => 'Courroie d\'alternateur',
		]));

		$eEquipmentExported = EquipmentLib::getWithHistory($eStructure, $eEquipment['id']);

		$this->assertEquals(27, count($eEquipmentExported['history']), 'l\'export porte les 25 relevés et les deux lignes, sans la coupe de l\'écran');
		$this->assertEquals(80.5, $eEquipmentExported['cost'], 'le coût est celui des entretiens faits, une ligne sans coût ne comptant pas');

		$export = EquipmentLib::getExport($eEquipmentExported);
		$header = new EquipmentUi()->getExportHeader();

		$this->assertEquals(27, count($export), 'une ligne de CSV par ligne de l\'historique');
		$this->assertEquals([count($header)], array_values(array_unique(array_map(count(...), $export))), 'chaque ligne a les colonnes de l\'en-tête');
		$this->assertEquals(['2026-06-12', 'intervention', 'Changement de courroie', 'Courroie d\'alternateur', 1025, Equipment::HOURS, NULL], $export[0], 'l\'intervention vient en tête, la plus récente, avec le compteur de son jour');
		$this->assertEquals(['2026-06-10', 'maintenance', 'Vidange moteur', '', 1025, Equipment::HOURS, '80,5'], $export[1], 'l\'entretien fait porte le nom de son entretien et son coût');
		$this->assertEquals(['2026-05-25', 'reading', '', '', 1025, Equipment::HOURS, NULL], $export[2], 'un relevé n\'a ni nom ni coût');
		$this->assertEquals('2026-05-01', $export[26][0], 'l\'export finit par le premier relevé');

		$html = new EquipmentPdfUi()->getHistory($eEquipmentExported);

		$this->assertEquals(28, substr_count($html, '<tr>'), 'le PDF porte l\'en-tête et les 27 lignes dans un seul tableau');
		$this->assertTrue(str_contains($html, '<div>Changement de courroie</div><div class="toolbox-pdf-muted">'.encode('Courroie d\'alternateur').'</div>'), 'la note d\'une ligne suit son nom');
		$this->assertFalse(str_contains($html, 'equipment:history'), 'sans pagination');

		$this->assertTrue(str_contains(new EquipmentPdfUi()->getMaintenances($eEquipmentExported), 'toutes les <span class="util-unit">400 h</span>'), 'les entretiens planifiés disent leur fréquence');

		// Retour du 01/10 sur le PDF, « ne rien afficher si y'en a pas » : une section sans ligne
		// ne laisse ni son titre ni une mention.
		$eEquipmentBare = new Equipment([
			'meter' => NULL,
			'cMaintenanceScheduled' => new \Collection(),
			'history' => []
		]);

		$this->assertEquals('', new EquipmentPdfUi()->getMaintenances($eEquipmentBare), 'sans entretien planifié, le PDF n\'en porte pas la section');
		$this->assertEquals('', new EquipmentPdfUi()->getHistory($eEquipmentBare), 'sans historique, le PDF n\'en porte pas la section');

		$eStructureOther = \structure\StructureLib::getById(\structure\FixtureAiLib::F2);

		$this->assertTrue(EquipmentLib::getWithHistory($eStructureOther, $eEquipment['id'])->empty(), 'une autre structure ne lit pas le carnet de ce matériel');

		$eEquipment['status'] = Equipment::INACTIVE;
		EquipmentLib::update($eEquipment, ['status']);

		$this->assertTrue(EquipmentLib::getWithHistory($eStructure, $eEquipment['id'])->empty(), 'un matériel sorti du carnet ne s\'exporte plus');

	}

	/**
	 * Le PDF s'archive : chaque date y porte son année, et un entretien qui revient au compteur et à
	 * la date y garde ses deux bornes, comme à l'écran (relecture de carnet-export du 01/10). L'écran,
	 * lui, tait l'année en cours.
	 */
	public function testPdfCarriesTheYearAndBothDues(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eEquipment = self::getEquipment(1000, [
			1 => ['name' => 'Révision', 'everyValue' => 400, 'everyMonths' => 12, 'plannedValue' => 1300, 'plannedDate' => '2026-12-01'],
			2 => ['name' => 'Graissage', 'everyMonths' => 3, 'plannedDate' => '2026-07-01'],
			3 => ['name' => 'Courroie', 'everyMonths' => 12, 'plannedDate' => '2026-06-01'],
		]);

		MaintenanceLib::fill($eEquipment);

		foreach($eEquipment['cMaintenance'] as $eMaintenance) {
			$eMaintenance['equipment'] = $eEquipment;
		}

		$eEquipment['cMaintenanceScheduled'] = $eEquipment['cMaintenance'];

		$cMaintenance = $eEquipment['cMaintenance'];

		$this->assertEquals('value', $cMaintenance[1]['dueBy'], 'le décor part d\'un entretien que le compteur décide');
		$this->assertFalse(str_contains(EquipmentUi::getDate('2026-07-01'), '2026'), 'l\'écran tait l\'année en cours');
		$this->assertTrue(str_contains(EquipmentUi::getDate('2026-07-01', year: TRUE), '2026'), 'le PDF la donne');

		$html = new EquipmentPdfUi()->getMaintenances($eEquipment);

		$this->assertTrue(str_contains($html, '<div>'.MaintenanceUi::getDueText($cMaintenance[1], year: TRUE).'</div><div class="toolbox-pdf-muted">au plus tard le '.EquipmentUi::getDate('2026-12-01', year: TRUE).'</div>'), 'les deux bornes de la révision, la seconde avec son année');
		$this->assertTrue(str_contains($html, '<div>le '.EquipmentUi::getDate('2026-07-01', year: TRUE).'</div></td>'), 'une échéance à la date seule, avec son année et sans seconde borne');
		$this->assertTrue(str_contains($html, 'dépassée depuis le '.EquipmentUi::getDate('2026-06-01', year: TRUE)), 'un retard dit aussi son année');
		$this->assertEquals(1, substr_count($html, 'au plus tard'), 'une seule seconde borne dans le tableau');

		$this->assertEquals(NULL, MaintenanceUi::getDueOther($cMaintenance[2]), 'une seule échéance, pas d\'autre borne');
		$this->assertEquals(NULL, MaintenanceUi::getDueOther($cMaintenance[3]));

	}

	/**
	 * Dans « Entretiens à faire », le nom du matériel mène à son onglet, sauf quand ce lien ramènerait là
	 * où l'on est : la page n'a qu'un matériel, ou l'adresse vient d'ouvrir le sien.
	 */
	public function testDueLinksOnlyToAnotherTab(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();

		$cEquipmentOwn = new \Collection();

		foreach(['Tracteur', 'Utilitaire'] as $name) {

			$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, $name), NULL, \Clock::date());

			// Sans `build()` : un entretien prévu il y a un mois et demi, pour qu'il soit en retard.
			MaintenanceLib::create(new Maintenance([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'name' => 'Contrôle technique',
				'everyValue' => NULL,
				'everyMonths' => 12,
				'plannedDate' => '2026-05-01',
				'plannedValue' => NULL,
			]));

			$cEquipmentOwn[] = $eEquipment;

		}

		[$eEquipmentTractor, $eEquipmentVan] = [$cEquipmentOwn[0], $cEquipmentOwn[1]];
		$ids = [$eEquipmentTractor['id'], $eEquipmentVan['id']];

		$cEquipment = EquipmentLib::getByStructure($eStructure)->find(fn($eEquipment) => in_array($eEquipment['id'], $ids));

		$render = fn(\Collection $cEquipment, Equipment $eEquipmentSelected) => new EquipmentUi()->getDue($eStructure, EquipmentLib::getDue($cEquipment), $cEquipment, $eEquipmentSelected);
		$link = fn(Equipment $eEquipment) => 'href="'.EquipmentUi::urlEquipment($eStructure, $eEquipment).'"';

		$html = $render($cEquipment, new Equipment());

		$this->assertTrue(str_contains($html, $link($eEquipmentTractor)) and str_contains($html, $link($eEquipmentVan)), 'deux matériels, aucun ouvert : chacun mène à son onglet');

		$html = $render($cEquipment, $eEquipmentTractor);

		$this->assertTrue(str_contains($html, $link($eEquipmentVan)), 'l\'autre matériel mène toujours à son onglet');

	}

	/**
	 * Le retard se compte deux fois (retours du 01/10) : le titre du bandeau dit combien
	 * d'entretiens sont à faire, en retard ou bientôt, et l'onglet d'un matériel porte en rouge le nombre
	 * de ses retards. Un matériel sans retard n'a pas de compteur.
	 */
	public function testOverdueIsCountedOnTheTab(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();

		$eEquipmentTractor = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), NULL, \Clock::date());
		$eEquipmentVan = $this->follow($eStructure, $this->createTool($eStructure, 'Utilitaire'), NULL, \Clock::date());

		// Sans `build()` : deux retards et un bientôt sur le tracteur, un bientôt sur l'utilitaire.
		foreach([[$eEquipmentTractor, '2026-05-01'], [$eEquipmentTractor, '2026-06-01'], [$eEquipmentTractor, '2026-07-01'], [$eEquipmentVan, '2026-07-01']] as $index => [$eEquipment, $plannedDate]) {
			MaintenanceLib::create(new Maintenance([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'name' => 'Entretien '.$index,
				'everyValue' => NULL,
				'everyMonths' => 12,
				'plannedDate' => $plannedDate,
				'plannedValue' => NULL,
			]));
		}

		$ids = [$eEquipmentTractor['id'], $eEquipmentVan['id']];
		$cEquipment = EquipmentLib::getByStructure($eStructure)->find(fn($eEquipment) => in_array($eEquipment['id'], $ids));

		$this->assertEquals([2, 0], [$cEquipment[$eEquipmentTractor['id']]['overdue'], $cEquipment[$eEquipmentVan['id']]['overdue']], 'deux retards sur le tracteur, aucun sur l\'utilitaire');

	}

	/**
	 * « Planifier un entretien » (planche pf-t4-v4, Q2) : « Prochaine fois » est demandée, à une date
	 * ou au compteur ; « Régulièrement » demande une fréquence, « Une seule fois » n'en a pas et oublie
	 * celle qu'un POST porterait.
	 */
	public function testPlanAsksWhenAndHowOften(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, \Clock::date());

		$build = function(array $input) use($eStructure, $eEquipment): array {

			$eMaintenance = new Maintenance([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
			]);

			$fw = new \FailWatch();
			$eMaintenance->build(MaintenanceLib::getPropertiesCreate(), $input + ['name' => 'Vidange moteur', 'everyValue' => '', 'everyMonths' => '', 'plannedValue' => '', 'plannedDate' => '']);

			return [$eMaintenance, $fw];

		};

		[, $fw] = $build(['frequency' => 'regular', 'plannedDate' => '2026-11-01']);
		$this->assertTrue($fw->has('Maintenance::everyMonths.frequency'), 'régulièrement, une fréquence est demandée');

		[, $fw] = $build(['frequency' => 'regular', 'everyMonths' => '12']);
		$this->assertTrue($fw->has('Maintenance::plannedDate.planned'), 'la prochaine fois est demandée');
		$this->assertFalse($fw->has('Maintenance::everyMonths.frequency'));

		[$eMaintenance, $fw] = $build(['frequency' => 'regular', 'everyValue' => '400', 'plannedValue' => '1400']);
		$this->assertTrue($fw->ok(), 'au compteur seul, la prochaine fois au compteur suffit');
		$this->assertTrue($eMaintenance->isRegular());

		[$eMaintenance, $fw] = $build(['frequency' => 'once', 'everyValue' => '400', 'everyMonths' => '12', 'plannedDate' => '2026-11-01']);
		$this->assertTrue($fw->ok(), 'une seule fois, aucune fréquence n\'est demandée');
		$this->assertEquals([NULL, NULL], [$eMaintenance['everyValue'], $eMaintenance['everyMonths']], 'et celle que le POST porte est oubliée');
		$this->assertFalse($eMaintenance->isRegular());

		[, $fw] = $build(['frequency' => 'once']);
		$this->assertTrue($fw->has('Maintenance::plannedDate.planned'), 'une seule fois, la prochaine fois reste demandée');

	}

	/**
	 * Un entretien « Une seule fois », fait, quitte le bloc du matériel et ne se replanifie pas ; retirer
	 * son entretien fait de l'historique l'y ramène.
	 */
	public function testOnceLeavesTheBlockWhenDone(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), 1000, \Clock::date());

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Changer le siège',
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedDate' => '2026-07-01',
			'plannedValue' => NULL,
		]);
		MaintenanceLib::create($eMaintenance);

		$getIds = fn() => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance']->getIds();

		$this->assertTrue(in_array($eMaintenance['id'], $getIds()), 'à faire, il est au bloc');

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
			'date' => \Clock::date(),
			'cost' => NULL,
			'comment' => NULL,
		]);
		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals(
			[Maintenance::DONE, '2026-07-01'],
			array_values(Maintenance::model()->select(['status', 'plannedDate'])->whereId($eMaintenance)->get()->extracts(['status', 'plannedDate'])),
			'fait, il est clos sans prochaine fois nouvelle'
		);
		$this->assertFalse(in_array($eMaintenance['id'], $getIds()), 'et quitte le bloc');
		$this->assertEquals(1, EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenanceDone']->count(), 'l\'entretien fait reste à l\'historique');

		MaintenanceDoneLib::delete($eMaintenanceDone);

		$this->assertEquals(Maintenance::ACTIVE, Maintenance::model()->whereId($eMaintenance)->getValue('status'), 'son entretien fait retiré, il revient');
		$this->assertTrue(in_array($eMaintenance['id'], $getIds()));

	}

	/**
	 * La dernière fois d'un entretien régulier décide de « Prochaine fois » : un entretien fait corrigé
	 * à une autre date la déplace, et retiré, celui d'avant reprend la main.
	 */
	public function testDoneCorrectedOrRemovedReplans(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Broyeur'), NULL, \Clock::date(), NULL);

		// Sans `build()` : l'entretien est le décor, pas ce que le test éprouve.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Affûtage',
			'everyValue' => NULL,
			'everyMonths' => 6,
			'plannedDate' => '2026-05-01',
			'plannedValue' => NULL,
		]);
		MaintenanceLib::create($eMaintenance);

		$getPlanned = fn() => Maintenance::model()->whereId($eMaintenance)->getValue('plannedDate');

		$cMaintenanceDone = new \Collection();

		foreach(['2026-03-01', '2026-06-01'] as $date) {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => $eMaintenance,
				'date' => $date,
				'cost' => NULL,
				'comment' => NULL,
			]);
			MaintenanceDoneLib::create($eMaintenanceDone);

			$cMaintenanceDone[] = $eMaintenanceDone;

		}

		$this->assertEquals('2026-12-01', $getPlanned(), 'six mois après la dernière fois');

		$eMaintenanceDoneLast = $cMaintenanceDone[1];
		$eMaintenanceDoneLast['date'] = '2026-06-10';
		MaintenanceDoneLib::update($eMaintenanceDoneLast, ['date']);

		$this->assertEquals('2026-12-10', $getPlanned(), 'corrigée, la dernière fois déplace la prochaine');

		MaintenanceDoneLib::delete($eMaintenanceDoneLast);

		$this->assertEquals('2026-09-01', $getPlanned(), 'retirée, celle d\'avant reprend la main');

	}

	/**
	 * Un matériel suivi au carnet est unique : « Mon matériel » ne construit plus son stock dans la
	 * modification, et l'édition rapide, qui construit le seul stock, le refuse.
	 */
	public function testFollowedToolHasNoStock(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Tracteur');

		$this->assertTrue(in_array('stock', \work\ToolLib::getPropertiesUpdate()($eTool)), 'un matériel qu\'on ne suit pas garde son stock');

		$this->follow($eStructure, $eTool, NULL, \Clock::date());

		$this->assertFalse(in_array('stock', \work\ToolLib::getPropertiesUpdate()($eTool)), 'un matériel suivi n\'a plus de stock');

		$fw = new \FailWatch();
		\work\ToolLib::getById($eTool['id'])->build(['stock'], ['stock' => '2'], new \PropertyList('update'));

		$this->assertTrue($fw->has('Tool::stock.followed'), 'l\'édition rapide du stock est refusée');

	}

	/**
	 * « Modifier le matériel » règle le compteur d'un matériel suivi, à la place du stock (planche
	 * pf-t4-v3, Q3 A). Le compteur se construit avec le matériel, donc son refus arrête tout
	 * l'enregistrement, et `ToolLib::update()` l'écrit avec le matériel. L'API, qui ne connaît pas le
	 * carnet, ne le reçoit pas : un corps sans compteur le couperait.
	 */
	public function testToolUpdateSetsTheMeter(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Tracteur');
		$eEquipment = $this->follow($eStructure, $eTool, 1200, \Clock::date());

		$this->assertTrue(in_array('meter', \work\ToolLib::getPropertiesUpdate()($eTool)), 'le formulaire porte le compteur');
		$this->assertFalse(in_array('meter', \work\ToolLib::getPropertiesUpdate('api')($eTool)), 'l\'API ne le porte pas');

		$fw = new \FailWatch();
		\work\ToolLib::getById($eTool['id'])->build(['name', 'meter'], ['name' => 'Refusé', 'meter' => Equipment::KILOMETERS], new \PropertyList('update'));

		$this->assertTrue($fw->has('Equipment::meter.locked'), 'des heures aux kilomètres, refusé avec le matériel');

		$eToolUpdated = \work\ToolLib::getById($eTool['id']);
		$eToolUpdated->build(['name', 'meter'], ['name' => $eTool['name'].' bis', 'meter' => ''], new \PropertyList('update'));
		\work\ToolLib::update($eToolUpdated, ['name', 'meter']);

		$this->assertEquals(NULL, Equipment::model()->whereId($eEquipment)->getValue('meter'), 'compteur coupé');
		$this->assertEquals($eTool['name'].' bis', \work\Tool::model()->whereId($eTool)->getValue('name'), 'le matériel est enregistré avec lui');

	}

	/**
	 * L'immatriculation d'un matériel à compteur (demande du 01/10) se règle dans « Modifier
	 * le matériel » avec le compteur, s'écrit en capitales, et reste en base quand le compteur est
	 * coupé : elle revient avec lui.
	 */
	public function testToolUpdateSetsTheRegistration(): void {

		$eStructure = $this->getStructure();
		$eTool = $this->createTool($eStructure, 'Utilitaire');
		$eEquipment = $this->follow($eStructure, $eTool, 48200, \Clock::date(), Equipment::KILOMETERS);

		$this->assertEquals(NULL, Equipment::model()->whereId($eEquipment)->getValue('registration'), 'un matériel entre au carnet sans immatriculation');

		$fw = new \FailWatch();
		\work\ToolLib::getById($eTool['id'])->build(['name', 'meter'], ['name' => 'Refusé', 'meter' => Equipment::KILOMETERS, 'registration' => str_repeat('A', 21)], new \PropertyList('update'));

		$this->assertTrue($fw->has('Equipment::registration.check'), 'une immatriculation trop longue est refusée avec le matériel');

		$eToolUpdated = \work\ToolLib::getById($eTool['id']);
		$eToolUpdated->build(['name', 'meter'], ['name' => $eTool['name'], 'meter' => Equipment::KILOMETERS, 'registration' => ' ab-123-cd '], new \PropertyList('update'));
		\work\ToolLib::update($eToolUpdated, ['name', 'meter']);

		$this->assertEquals('AB-123-CD', Equipment::model()->whereId($eEquipment)->getValue('registration'), 'écrite en capitales, sans ses espaces');
		$this->assertEquals('AB-123-CD', EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['registration'], 'le carnet la lit');
		$this->assertEquals('Carnet d\'entretien · '.$eTool['name'].' · AB-123-CD', EquipmentPdfUi::getTitle(EquipmentLib::getWithHistory($eStructure, $eEquipment['id'])), 'le titre du PDF la porte après le nom');

		$eToolUpdated = \work\ToolLib::getById($eTool['id']);
		$eToolUpdated->build(['name', 'meter'], ['name' => $eTool['name'], 'meter' => '', 'registration' => 'AB-123-CD'], new \PropertyList('update'));
		\work\ToolLib::update($eToolUpdated, ['name', 'meter']);

		$this->assertEquals(NULL, Equipment::model()->whereId($eEquipment)->getValue('meter'), 'compteur coupé');
		$this->assertEquals('AB-123-CD', Equipment::model()->whereId($eEquipment)->getValue('registration'), 'l\'immatriculation reste en base pour le retour du compteur');
		$this->assertEquals('Carnet d\'entretien · '.$eTool['name'], EquipmentPdfUi::getTitle(EquipmentLib::getWithHistory($eStructure, $eEquipment['id'])), 'sans compteur, le titre du PDF ne la montre pas');

		$eToolUpdated = \work\ToolLib::getById($eTool['id']);
		$eToolUpdated->build(['name', 'meter'], ['name' => $eTool['name'], 'meter' => Equipment::KILOMETERS, 'registration' => ''], new \PropertyList('update'));
		\work\ToolLib::update($eToolUpdated, ['name', 'meter']);

		$this->assertEquals(NULL, Equipment::model()->whereId($eEquipment)->getValue('registration'), 'un champ vidé efface l\'immatriculation');

	}

	/**
	 * Une photo déposée par l'appareil photo, telle que `/media/:put` la range : du type du carnet, au
	 * compte connecté.
	 */
	private function createPhoto(): string {

		$hash = substr(md5(uniqid()), 0, 19).'j';

		// Sans passer par l'envoi d'un fichier : `/media/:put` lit une image, et seul le rattachement de
		// l'empreinte à un compte est éprouvé ici.
		$eMedia = new \media\Media([
			'user' => \user\ConnectionLib::getOnline(),
			'type' => 'maintenance-done',
			'hash' => $hash,
		]);
		\media\Media::model()->insert($eMedia);

		return $hash;

	}

	/**
	 * La photo d'une intervention est une empreinte que le formulaire poste : elle n'est acceptée que
	 * déposée par le compte qui enregistre, et une ligne garde la sienne quand un autre membre la corrige.
	 */
	public function testPhotoBelongsToTheOneWhoSendsIt(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), NULL, \Clock::date(), NULL);

		$build = function(string $photo) use($eStructure, $eEquipment): array {

			$eMaintenanceDone = new MaintenanceDone([
				'structure' => $eStructure,
				'equipment' => $eEquipment,
				'maintenance' => new Maintenance(),
			]);

			$fw = new \FailWatch();
			$eMaintenanceDone->build(MaintenanceDoneLib::getPropertiesCreate(), ['name' => 'Changement du filtre', 'date' => \Clock::date(), 'meterUpdate' => '0', 'value' => '', 'cost' => '', 'comment' => '', 'photo' => $photo]);

			return [$eMaintenanceDone, $fw];

		};

		[$eMaintenanceDone, $fw] = $build('');
		$this->assertTrue($fw->ok());
		$this->assertEquals(NULL, $eMaintenanceDone['photo'], 'une intervention se consigne sans photo');

		[, $fw] = $build('trop-court');
		$this->assertTrue($fw->has('MaintenanceDone::photo.check'), 'une empreinte a vingt caractères');

		[, $fw] = $build('photodunautrecarne0j');
		$this->assertTrue($fw->has('MaintenanceDone::photo.owned'), 'une empreinte que personne n\'a déposée est refusée');

		$hash = $this->createPhoto();

		[$eMaintenanceDone, $fw] = $build($hash);
		$this->assertTrue($fw->ok(), 'la photo déposée par le compte connecté est acceptée');

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals($hash, EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['history'][0]['photo'], 'le carnet la lit');

		// Un autre membre corrige la ligne : la photo n'est pas la sienne, elle reste pourtant.
		$eUserAuthor = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline(new \user\User(['id' => $eUserAuthor['id'] + 1]));

		$update = function(string $photo) use($eMaintenanceDone): array {

			$eMaintenanceDoneUpdated = MaintenanceDoneLib::getById($eMaintenanceDone['id'], ['id', 'structure', 'name', 'date', 'value', 'cost', 'comment', 'photo', 'maintenance' => ['name'], 'equipment' => ['structure', 'meter', 'status']]);

			$fw = new \FailWatch();
			$eMaintenanceDoneUpdated->build(MaintenanceDoneLib::getPropertiesUpdate(), ['name' => 'Changement du filtre', 'date' => \Clock::date(), 'cost' => '40', 'comment' => '', 'photo' => $photo]);

			return [$eMaintenanceDoneUpdated, $fw];

		};

		[, $fw] = $update($hash);
		$this->assertTrue($fw->ok(), 'la photo déjà rangée sur la ligne reste, qui que soit celui qui corrige');

		[, $fw] = $build($hash);
		$this->assertTrue($fw->has('MaintenanceDone::photo.owned'), 'la même empreinte ne se pose pas sur une autre ligne par un autre compte');

		[, $fw] = $update('photodunautrecarne0j');
		$this->assertTrue($fw->has('MaintenanceDone::photo.owned'));

		[$eMaintenanceDoneUpdated, $fw] = $update('');
		$this->assertTrue($fw->ok());

		MaintenanceDoneLib::update($eMaintenanceDoneUpdated, MaintenanceDoneLib::getPropertiesUpdate());

		$this->assertEquals(NULL, MaintenanceDone::model()->whereId($eMaintenanceDone)->getValue('photo'), 'un cadre vidé retire la photo');

		\user\ConnectionLib::setOnline($eUserAuthor);

		$this->assertTrue(AlertUi::getError('MaintenanceDone::photo.owned') !== NULL, 'le refus a son message');

	}

	/**
	 * La photo se lit sous la note de sa ligne, et s'ouvre en grand par un lien : les lignes sans photo
	 * et les relevés n'en portent pas.
	 */
	public function testPhotoIsShownUnderTheNote(): void {

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, $this->createTool($eStructure, 'Tracteur'), NULL, \Clock::date(), NULL);

		$hash = $this->createPhoto();

		// Sans `build()` : les deux lignes sont le décor, dont l'empreinte vient d'être déposée.
		$eMaintenanceDonePhoto = new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => new Maintenance(),
			'name' => 'Changement du filtre',
			'date' => \Clock::date(),
			'cost' => NULL,
			'comment' => 'Filtre W 712/75',
			'photo' => $hash,
		]);
		MaintenanceDoneLib::create($eMaintenanceDonePhoto);

		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => new Maintenance(),
			'name' => 'Graissage',
			'date' => \Clock::date(),
			'cost' => NULL,
			'comment' => NULL,
			'photo' => NULL,
		]));

		$eEquipmentFilled = EquipmentLib::getByStructure($eStructure)[$eEquipment['id']];

		$html = new MaintenanceDoneUi()->getHistory($eEquipmentFilled, FALSE);

		$this->assertEquals(1, substr_count($html, '/toolbox/maintenanceDone:photo?id='.$eMaintenanceDonePhoto['id'].'"'), 'un clic l\'ouvre en grand');
		$this->assertTrue(str_contains($html, '/400/'.$hash), 'au format de la ligne');

		$panel = new MaintenanceDoneUi()->getPhoto(MaintenanceDoneLib::getById($eMaintenanceDonePhoto['id'], ['id', 'name', 'date', 'photo', 'maintenance' => ['name']]));

		$this->assertTrue(str_contains($panel->body, '/1600/'.$hash), 'et le panneau la sert au grand format');

	}

	/**
	 * Un nom validé dans l'autocomplétion entre dans « Mon matériel » avec le suivi, et le carnet
	 * devient un outil de la structure.
	 */
	public function testFollowCreatesTheTool(): void {

		$eStructure = $this->getStructure();

		$eToolNew = \work\ToolLib::getNewTool($eStructure, NULL);
		$eToolNew['name'] = 'Broyeur '.uniqid();

		$eEquipment = new Equipment([
			'structure' => $eStructure,
			'tool' => new \work\Tool(),
			'meter' => NULL,
			'eToolNew' => $eToolNew,
		]);

		EquipmentLib::follow($eEquipment, new EquipmentReading());

		$this->assertTrue($eEquipment['tool']->notEmpty());
		$this->assertEquals($eToolNew['name'], \work\Tool::model()->whereId($eEquipment['tool'])->getValue('name'), 'le matériel entre dans « Mon matériel »');
		$this->assertEquals(0, EquipmentReading::model()->whereEquipment($eEquipment)->count(), 'sans compteur, aucun relevé');
		$this->assertTrue(in_array(Favorite::MAINTENANCE, \structure\Feature::model()->whereStructure($eStructure)->getValue('hasToolbox')), 'le carnet devient un outil de la structure');

	}

}
?>
