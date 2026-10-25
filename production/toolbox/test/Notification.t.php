<?php
namespace toolbox;

/**
 * Les notifications du carnet du matériel (planche production-finir-carnet-notifications, arbitrée
 * par le 01/10/2026).
 *
 * - Le point du lundi annonce un entretien en retard, à la date ou au compteur, et un entretien dû à
 *   une date des six jours qui suivent. Un entretien au compteur que le dernier relevé n'a pas dépassé
 *   n'entre pas : « pour "à faire" on ne met que ceux sur périodicité temporelle ».
 * - Le rappel des compteurs nomme les matériels à compteur qui n'ont pas été relevés depuis deux
 *   mois, jamais relevés compris. Il part le 5 des mois que dit le rythme réglé par la ferme (tous
 *   les mois, 2 mois, 3 mois ou 6 mois, comptés depuis janvier), tous les 3 mois sans réglage, et le
 *   seuil tombe à un mois quand le rappel est mensuel.
 * - Une relecture est bornée par la structure : l'objet d'une autre ne s'annonce pas.
 *
 * Le cron émet large (`MaintenanceLib::getDueUntil()`), la relecture tranche : les deux sont éprouvés
 * sur le même décor, écrit sur F1 dans une transaction annulée par méthode (`TransactionTest`).
 *
 * Lancement : dev/test package=toolbox file=Notification
 */
class NotificationTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	/** Un lundi, le jour du cron : le point regarde jusqu'au dimanche 21. */
	const CLOCK = '2026-06-15 06:00:00';

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

	private function getStructure(): \structure\Structure {
		return \structure\StructureLib::getById(self::STRUCTURE);
	}

	/**
	 * Un matériel suivi, avec ou sans compteur, avec ou sans premier relevé.
	 */
	private function follow(\structure\Structure $eStructure, string $name, ?string $meter, ?int $value = NULL, ?string $date = NULL): Equipment {

		$eTool = \work\ToolLib::getNewTool($eStructure, NULL);

		// Sans `build()` : le nom est choisi par le test, et « Mon matériel » n'est pas ce qu'il éprouve.
		$eTool['name'] = $name.' '.uniqid();

		\work\ToolLib::create($eTool);

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

	private function plan(\structure\Structure $eStructure, Equipment $eEquipment, string $name, ?string $plannedDate, ?int $plannedValue): Maintenance {

		// Sans `build()` : le décor veut une échéance déjà passée, que le formulaire ne refuse pas mais
		// dont il n'éprouve rien ici ; ce qui se juge est la relecture, pas la saisie.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => $name,
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedDate' => $plannedDate,
			'plannedValue' => $plannedValue,
		]);

		MaintenanceLib::create($eMaintenance);

		return $eMaintenance;

	}

	private function describe(string $type, \structure\Structure $eStructure, \Element $e): \notification\Notification {
		return NotificationLib::describe($type, $eStructure, $e['id']);
	}

	/**
	 * Le rythme du rappel des compteurs, réglé comme depuis le panneau du carnet.
	 */
	private function configure(\structure\Structure $eStructure, int $readingEveryMonths): void {

		$eAction = MaintenanceConfigurationLib::getDefaultAction(MaintenanceConfigurationLib::getActions($eStructure));

		$eMaintenanceConfiguration = new MaintenanceConfiguration(['structure' => $eStructure]);

		$fw = new \FailWatch();

		$eMaintenanceConfiguration->build(MaintenanceConfigurationLib::getPropertiesCreate(), [
			'action' => $eAction->empty() ? '' : (string)$eAction['id'],
			'readingEveryMonths' => (string)$readingEveryMonths
		]);

		if($this->assertTrue($fw->ok(), 'le réglage « tous les '.$readingEveryMonths.' mois » se construit : '.$fw)) {
			MaintenanceConfigurationLib::create($eMaintenanceConfiguration);
		}

	}

	/** Les matériels que le cron mettrait en file à l'heure figée. */
	private function getReadingIds(): array {
		return array_map(fn(Equipment $eEquipment) => $eEquipment['id'], NotificationLib::getReadings());
	}

	public function testWindows(): void {

		$this->setClock(self::CLOCK);

		$this->assertEquals('2026-06-21', NotificationLib::getUntil(), 'le point du lundi regarde jusqu\'au dimanche');
		$this->assertEquals('2026-04-15', NotificationLib::getSince(2), 'un compteur se rappelle après deux mois sans relevé');
		$this->assertEquals('2026-05-15', NotificationLib::getSince(1), 'après un mois quand le rappel est mensuel');
		$this->assertEquals([1, 2, 2, 2], array_map(NotificationLib::getReadingMonths(...), MaintenanceConfiguration::READING_EVERY_MONTHS), 'le seuil de chaque rythme');

	}

	public function testMaintenanceLateByDate(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, 'Herse', NULL);
		$eMaintenance = $this->plan($eStructure, $eEquipment, 'Contrôle des dents', '2026-06-01', NULL);

		$eNotification = $this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance);

		$this->assertTrue($eNotification->notEmpty(), 'un entretien dont la date est passée s\'annonce');

		if($eNotification->empty()) {
			return;
		}

		$this->assertEquals(0, $eNotification['group'], 'il se réunit aux autres du jour');
		$this->assertEquals('Contrôle des dents', $eNotification['content']['maintenance']);
		$this->assertEquals($eEquipment['id'], $eNotification['content']['equipmentId']);
		$this->assertTrue($eNotification['content']['late']);
		$this->assertEquals('date', $eNotification['content']['by']);
		$this->assertEquals('2026-06-01', $eNotification['content']['plannedDate']);
		$this->assertEquals(NULL, $eNotification['content']['readingValue'], 'un matériel sans compteur n\'a pas de relevé à citer');
		$this->assertEquals('2026-06-21', $eNotification['content']['until']);

		$this->assertTrue(in_array($eMaintenance['id'], MaintenanceLib::getDueUntil(NotificationLib::getUntil())->getIds()), 'le cron le met en file');

	}

	public function testMaintenanceLateByValue(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, 'Tracteur', Equipment::HOURS, 520, '2026-06-10');
		$eMaintenance = $this->plan($eStructure, $eEquipment, 'Vidange', NULL, 500);

		$eNotification = $this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance);

		$this->assertTrue($eNotification->notEmpty(), 'un entretien dont le compteur est dépassé s\'annonce');

		if($eNotification->empty()) {
			return;
		}

		$this->assertTrue($eNotification['content']['late']);
		$this->assertEquals('value', $eNotification['content']['by']);
		$this->assertEquals(500, $eNotification['content']['plannedValue']);
		$this->assertEquals(520, $eNotification['content']['readingValue']);
		$this->assertEquals(Equipment::HOURS, $eNotification['content']['meter']);

		$this->assertTrue(in_array($eMaintenance['id'], MaintenanceLib::getDueUntil(NotificationLib::getUntil())->getIds()), 'le cron le met en file');

	}

	public function testMaintenanceOfTheWeek(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, 'Utilitaire', Equipment::KILOMETERS, 48200, '2026-06-10');

		$eMaintenanceSunday = $this->plan($eStructure, $eEquipment, 'Contrôle technique', '2026-06-21', NULL);
		$eMaintenanceMonday = $this->plan($eStructure, $eEquipment, 'Pneus', '2026-06-22', NULL);
		$eMaintenanceValue = $this->plan($eStructure, $eEquipment, 'Vidange', NULL, 48300);

		$eNotification = $this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenanceSunday);

		$this->assertTrue($eNotification->notEmpty(), 'un entretien dû dimanche s\'annonce le lundi');

		if($eNotification->notEmpty()) {
			$this->assertFalse($eNotification['content']['late']);
			$this->assertEquals('date', $eNotification['content']['by']);
			$this->assertEquals('2026-06-21', $eNotification['content']['plannedDate']);
		}

		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenanceMonday)->empty(), 'un entretien dû le lundi suivant attend le point suivant');
		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenanceValue)->empty(), 'un entretien au compteur à 100 km de son échéance ne s\'annonce pas, il n\'entre que par son retard');

		$ids = MaintenanceLib::getDueUntil(NotificationLib::getUntil())->getIds();

		$this->assertTrue(in_array($eMaintenanceSunday['id'], $ids), 'le cron met en file l\'entretien de la semaine');
		$this->assertFalse(in_array($eMaintenanceMonday['id'], $ids), 'pas celui de la semaine suivante');

	}

	/**
	 * Ce qui s'est passé entre le cron et le traitement retire la raison de l'annonce.
	 */
	public function testMaintenanceDoneSinceIsSilent(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, 'Broyeur', NULL);
		$eMaintenance = $this->plan($eStructure, $eEquipment, 'Affutage', '2026-06-01', NULL);

		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance)->notEmpty(), 'le décor part d\'un retard');

		MaintenanceDoneLib::create(new MaintenanceDone([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'maintenance' => EquipmentLib::getByStructure($eStructure)[$eEquipment['id']]['cMaintenance'][$eMaintenance['id']],
			'date' => \Clock::date(),
			'value' => NULL,
			'cost' => NULL,
			'comment' => NULL,
		]));

		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance)->empty(), 'un entretien fait depuis ne s\'annonce plus');

	}

	public function testReading(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();

		$eEquipmentAsleep = $this->follow($eStructure, 'Tracteur', Equipment::HOURS, 1250, '2026-04-10');
		$eEquipmentAwake = $this->follow($eStructure, 'Utilitaire', Equipment::KILOMETERS, 48200, '2026-04-15');
		$eEquipmentNever = $this->follow($eStructure, 'Pulvérisateur', Equipment::HOURS);
		$eEquipmentBare = $this->follow($eStructure, 'Herse', NULL);

		$eNotification = $this->describe(NotificationLib::READING, $eStructure, $eEquipmentAsleep);

		$this->assertTrue($eNotification->notEmpty(), 'un compteur relevé il y a plus de deux mois se rappelle');

		if($eNotification->notEmpty()) {
			$this->assertEquals(0, $eNotification['group']);
			$this->assertEquals('2026-04-10', $eNotification['content']['lastDate']);
			$this->assertEquals($eEquipmentAsleep['tool']['name'], $eNotification['content']['equipment']);
		}

		$this->assertTrue($this->describe(NotificationLib::READING, $eStructure, $eEquipmentAwake)->empty(), 'un compteur relevé il y a deux mois jour pour jour ne se rappelle pas');

		$eNotification = $this->describe(NotificationLib::READING, $eStructure, $eEquipmentNever);

		$this->assertTrue($eNotification->notEmpty(), 'un compteur jamais relevé se rappelle');

		if($eNotification->notEmpty()) {
			$this->assertEquals(NULL, $eNotification['content']['lastDate']);
		}

		$this->assertTrue($this->describe(NotificationLib::READING, $eStructure, $eEquipmentBare)->empty(), 'un matériel sans compteur n\'a rien à relever');

		$ids = EquipmentLib::getUnreadSince(NotificationLib::getSince(2))->getIds();

		$this->assertTrue(in_array($eEquipmentAsleep['id'], $ids), 'le cron met en file le compteur qui dort');
		$this->assertTrue(in_array($eEquipmentNever['id'], $ids), 'et celui qu\'on n\'a jamais relevé');
		$this->assertFalse(in_array($eEquipmentAwake['id'], $ids), 'pas celui qu\'on relève');
		$this->assertFalse(in_array($eEquipmentBare['id'], $ids), 'ni le matériel sans compteur');

		EquipmentReadingLib::create(new EquipmentReading(['structure' => $eStructure, 'equipment' => $eEquipmentAsleep, 'date' => \Clock::date(), 'value' => 1300]));

		$this->assertTrue($this->describe(NotificationLib::READING, $eStructure, $eEquipmentAsleep)->empty(), 'un relevé saisi depuis le cron retire le rappel');

	}

	/**
	 * Le rythme réglé dit les mois où le rappel d'une ferme part, comptés depuis janvier. Le cron
	 * passe le 5 de chaque mois : un compteur jamais relevé y entre les mois de sa ferme, et eux seuls.
	 */
	public function testReadingMonths(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eEquipment = $this->follow($eStructure, 'Pulvérisateur', Equipment::HOURS);

		$getMonths = function() use($eEquipment): array {

			$months = [];

			foreach(range(1, 12) as $month) {

				$this->setClock('2027-'.sprintf('%02d', $month).'-05 06:00:00');

				if(in_array($eEquipment['id'], $this->getReadingIds())) {
					$months[] = $month;
				}

			}

			return $months;

		};

		$this->assertEquals([1, 4, 7, 10], $getMonths(), 'sans réglage, le rappel part en janvier, avril, juillet et octobre');

		$expected = [
			1 => range(1, 12),
			2 => [1, 3, 5, 7, 9, 11],
			3 => [1, 4, 7, 10],
			6 => [1, 7]
		];

		$this->assertEquals(MaintenanceConfiguration::READING_EVERY_MONTHS, array_keys($expected), 'chaque rythme qu\'on peut choisir est éprouvé');

		foreach($expected as $readingEveryMonths => $months) {

			$this->configure($eStructure, $readingEveryMonths);

			$this->assertEquals($months, $getMonths(), 'tous les '.$readingEveryMonths.' mois');

		}

	}

	/**
	 * Le seuil suit le rythme : un compteur relevé il y a six semaines ne se rappelle que chez une
	 * ferme au rappel mensuel, au cron comme à la relecture.
	 */
	public function testReadingThreshold(): void {

		// Juillet : le mois, avec janvier, où les quatre rythmes font partir le rappel.
		$this->setClock('2026-07-05 06:00:00');

		$eStructure = $this->getStructure();

		$eEquipmentAsleep = $this->follow($eStructure, 'Tracteur', Equipment::HOURS, 1250, '2026-04-10');
		$eEquipmentRecent = $this->follow($eStructure, 'Quad', Equipment::HOURS, 310, '2026-05-24');
		$eEquipmentAwake = $this->follow($eStructure, 'Utilitaire', Equipment::KILOMETERS, 48200, '2026-06-20');

		$read = function() use($eStructure, $eEquipmentAsleep, $eEquipmentRecent, $eEquipmentAwake): array {

			$ids = $this->getReadingIds();

			return array_map(fn(Equipment $eEquipment) => [
				in_array($eEquipment['id'], $ids),
				$this->describe(NotificationLib::READING, $eStructure, $eEquipment)->notEmpty()
			], [$eEquipmentAsleep, $eEquipmentRecent, $eEquipmentAwake]);

		};

		$this->assertEquals([[TRUE, TRUE], [FALSE, FALSE], [FALSE, FALSE]], $read(), 'sans réglage, le seuil est de deux mois');

		foreach(MaintenanceConfiguration::READING_EVERY_MONTHS as $readingEveryMonths) {

			$this->configure($eStructure, $readingEveryMonths);

			$recent = ($readingEveryMonths === 1);

			$this->assertEquals(
				[[TRUE, TRUE], [$recent, $recent], [FALSE, FALSE]],
				$read(),
				'tous les '.$readingEveryMonths.' mois : le compteur relevé il y a six semaines '.($recent ? 'se rappelle' : 'ne se rappelle pas').', celui du mois jamais'
			);

		}

	}

	/**
	 * Le carnet vit dans la base commune : un identifiant seul désigne l'objet de n'importe quelle
	 * structure. La structure 0 ne possède rien.
	 */
	public function testAnotherStructureReadsNothing(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eStructureOther = new \structure\Structure(['id' => 0]);

		$eEquipment = $this->follow($eStructure, 'Tracteur', Equipment::HOURS, 1250, '2026-04-10');
		$eMaintenance = $this->plan($eStructure, $eEquipment, 'Vidange', '2026-06-01', NULL);

		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructure, $eMaintenance)->notEmpty(), 'le décor s\'annonce chez lui');
		$this->assertTrue($this->describe(NotificationLib::READING, $eStructure, $eEquipment)->notEmpty());

		$this->assertTrue($this->describe(NotificationLib::MAINTENANCE, $eStructureOther, $eMaintenance)->empty(), 'l\'entretien ne se lit pas par une autre structure');
		$this->assertTrue($this->describe(NotificationLib::READING, $eStructureOther, $eEquipment)->empty(), 'le compteur non plus');

	}

	/**
	 * Le point du lundi en une phrase, selon ce que le groupe réunit.
	 */
	public function testGroupText(): void {

		$getHead = function(string $type, array $lates, bool $earlier = FALSE): \notification\Notification {

			$cNotification = new \Collection();

			foreach($lates as $late) {
				$cNotification[] = new \notification\Notification([
					'type' => $type,
					'content' => ['late' => $late, 'until' => '2026-06-21']
				]);
			}

			return new \notification\Notification([
				'type' => $type,
				'content' => ['late' => $lates[0], 'until' => '2026-06-21'],
				'cNotificationGroup' => $cNotification,
				'earlier' => $earlier
			]);

		};

		$ui = new NotificationUi();
		$until = EquipmentUi::getDate('2026-06-21');

		$this->assertEquals('2 entretiens en retard', $ui->getGroupText($getHead(NotificationLib::MAINTENANCE, [TRUE, TRUE])));
		$this->assertEquals('1 entretien à faire d\'ici le '.$until, $ui->getGroupText($getHead(NotificationLib::MAINTENANCE, [FALSE])));
		$this->assertEquals('2 entretiens en retard et 3 à faire d\'ici le '.$until, $ui->getGroupText($getHead(NotificationLib::MAINTENANCE, [TRUE, FALSE, TRUE, FALSE, FALSE])));
		$this->assertEquals('3 entretiens signalés plus tôt', $ui->getGroupText($getHead(NotificationLib::MAINTENANCE, [TRUE, FALSE, TRUE], earlier: TRUE)));
		$this->assertEquals('4 compteurs à relever', $ui->getGroupText($getHead(NotificationLib::READING, [FALSE, FALSE, FALSE, FALSE])));
		$this->assertEquals('1 compteur signalé plus tôt', $ui->getGroupText($getHead(NotificationLib::READING, [FALSE], earlier: TRUE)));

	}

	/**
	 * Un compteur d'eau posé, avec ou sans premier relevé.
	 */
	private function place(\structure\Structure $eStructure, string $name, ?float $value = NULL, ?string $date = NULL): WaterMeter {

		// Sans `build()` : le nom est choisi par le test, et la pose d'un compteur n'est pas ce qu'il
		// éprouve (`WaterReadingTest::testMeterBuild()`).
		$eWaterMeter = new WaterMeter([
			'structure' => $eStructure,
			'name' => $name,
			'digits' => 5
		]);

		WaterMeterLib::create($eWaterMeter);

		if($value !== NULL) {

			// Sans `build()` non plus : un relevé daté du passé, que `WaterReadingTest` éprouve par ailleurs.
			WaterReadingLib::create(new WaterReading([
				'structure' => $eStructure,
				'meter' => $eWaterMeter,
				'date' => $date,
				'value' => $value
			]));

		}

		return $eWaterMeter;

	}

	/**
	 * Le rythme du rappel des compteurs d'eau, réglé comme depuis le panneau de la consommation d'eau.
	 */
	private function configureWater(\structure\Structure $eStructure, int $readingEveryMonths): void {

		$eWaterConfiguration = new WaterConfiguration(['structure' => $eStructure]);

		$fw = new \FailWatch();

		$eWaterConfiguration->build(WaterConfigurationLib::getPropertiesCreate(), [
			'readingEveryMonths' => (string)$readingEveryMonths
		]);

		if($this->assertTrue($fw->ok(), 'le réglage de l\'eau « tous les '.$readingEveryMonths.' mois » se construit : '.$fw)) {
			WaterConfigurationLib::create($eWaterConfiguration);
		}

	}

	private function getWaterReadingIds(): array {
		return array_map(fn(WaterMeter $eWaterMeter) => $eWaterMeter['id'], NotificationLib::getWaterReadings());
	}

	/**
	 * Le rappel des compteurs d'eau suit celui du carnet : un compteur relevé il y a plus de deux mois,
	 * ou jamais, se rappelle ; un compteur supprimé, ou relevé depuis le cron, ne se rappelle plus.
	 */
	public function testWaterReading(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();

		$eWaterMeterAsleep = $this->place($eStructure, 'Puits', 1250, '2026-04-10');
		$eWaterMeterAwake = $this->place($eStructure, 'Forage', 48200, '2026-04-15');
		$eWaterMeterNever = $this->place($eStructure, 'Réseau');
		$eWaterMeterDeleted = $this->place($eStructure, 'Ancien puits', 300, '2026-01-10');

		$eWaterMeterDeleted['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeterDeleted, ['status']);

		$eNotification = $this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterAsleep);

		$this->assertTrue($eNotification->notEmpty(), 'un compteur d\'eau relevé il y a plus de deux mois se rappelle');

		if($eNotification->notEmpty()) {
			$this->assertEquals(0, $eNotification['group']);
			$this->assertEquals(['meter' => 'Puits', 'lastDate' => '2026-04-10'], $eNotification['content']);
		}

		$this->assertTrue($this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterAwake)->empty(), 'un compteur relevé il y a deux mois jour pour jour ne se rappelle pas');

		$eNotification = $this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterNever);

		$this->assertTrue($eNotification->notEmpty(), 'un compteur jamais relevé se rappelle');

		if($eNotification->notEmpty()) {
			$this->assertEquals(NULL, $eNotification['content']['lastDate']);
		}

		$this->assertTrue($this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterDeleted)->empty(), 'un compteur supprimé n\'a plus rien à relever');

		$this->setClock('2026-07-05 06:00:00');

		$ids = $this->getWaterReadingIds();

		$this->assertTrue(in_array($eWaterMeterAsleep['id'], $ids), 'le cron met en file le compteur qui dort');
		$this->assertTrue(in_array($eWaterMeterNever['id'], $ids), 'et celui qu\'on n\'a jamais relevé');
		$this->assertFalse(in_array($eWaterMeterDeleted['id'], $ids), 'pas le compteur supprimé');

		$this->setClock(self::CLOCK);

		WaterReadingLib::create(new WaterReading(['structure' => $eStructure, 'meter' => $eWaterMeterAsleep, 'date' => \Clock::date(), 'value' => 1300]));

		$this->assertTrue($this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterAsleep)->empty(), 'un relevé saisi depuis le cron retire le rappel');

		$this->assertTrue($this->describe(NotificationLib::WATER_READING, new \structure\Structure(['id' => 0]), $eWaterMeterNever)->empty(), 'le compteur ne se lit pas par une autre structure');

	}

	/**
	 * Le rythme de l'eau se règle à part de celui du carnet : chacun dit les mois de son rappel, et le
	 * seuil suit le sien.
	 */
	public function testWaterReadingMonths(): void {

		$this->setClock(self::CLOCK);

		$eStructure = $this->getStructure();
		$eWaterMeter = $this->place($eStructure, 'Puits');
		$eEquipment = $this->follow($eStructure, 'Pulvérisateur', Equipment::HOURS);

		$getMonths = function(\Closure $getIds, \Element $e): array {

			$months = [];

			foreach(range(1, 12) as $month) {

				$this->setClock('2027-'.sprintf('%02d', $month).'-05 06:00:00');

				if(in_array($e['id'], $getIds())) {
					$months[] = $month;
				}

			}

			return $months;

		};

		$this->assertEquals([1, 4, 7, 10], $getMonths($this->getWaterReadingIds(...), $eWaterMeter), 'sans réglage, le rappel de l\'eau part en janvier, avril, juillet et octobre');

		$this->configureWater($eStructure, 6);
		$this->configure($eStructure, 1);

		$this->assertEquals([1, 7], $getMonths($this->getWaterReadingIds(...), $eWaterMeter), 'l\'eau tous les 6 mois');
		$this->assertEquals(range(1, 12), $getMonths($this->getReadingIds(...), $eEquipment), 'le carnet garde son rythme mensuel');

		$this->configureWater($eStructure, 2);

		$this->assertEquals([1, 3, 5, 7, 9, 11], $getMonths($this->getWaterReadingIds(...), $eWaterMeter), 'un second réglage remplace le premier');
		$this->assertEquals(1, WaterConfiguration::model()->whereStructure($eStructure)->count(), 'une seule ligne par structure');

		// Relevé il y a six semaines : il ne se rappelle qu'au rythme mensuel, dont le seuil est d'un mois.
		$this->setClock('2026-07-05 06:00:00');

		$eWaterMeterRecent = $this->place($eStructure, 'Forage', 310, '2026-05-24');

		$this->assertFalse(in_array($eWaterMeterRecent['id'], $this->getWaterReadingIds()), 'tous les 2 mois, six semaines ne suffisent pas');
		$this->assertTrue($this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterRecent)->empty());

		$this->configureWater($eStructure, 1);

		$this->assertTrue(in_array($eWaterMeterRecent['id'], $this->getWaterReadingIds()), 'tous les mois, six semaines suffisent');
		$this->assertTrue($this->describe(NotificationLib::WATER_READING, $eStructure, $eWaterMeterRecent)->notEmpty());

	}

	/**
	 * Le réglage n'accepte que les rythmes du carnet.
	 */
	public function testWaterConfigurationBuild(): void {

		$eStructure = $this->getStructure();

		$builds = function(string $readingEveryMonths) use($eStructure): array {

			$fw = new \FailWatch();
			new WaterConfiguration(['structure' => $eStructure])->build(WaterConfigurationLib::getPropertiesCreate(), ['readingEveryMonths' => $readingEveryMonths], new \PropertyList('create'));

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		foreach(MaintenanceConfiguration::READING_EVERY_MONTHS as $readingEveryMonths) {
			$this->assertEquals([], $builds((string)$readingEveryMonths), 'tous les '.$readingEveryMonths.' mois');
		}

		$this->assertEquals(['WaterConfiguration::readingEveryMonths.check'], $builds('4'), 'pas tous les 4 mois, dans les bornes du schéma mais hors de la liste');
		$this->assertEquals(['WaterConfiguration::readingEveryMonths.check'], $builds(''), 'ni rien');

	}

}
?>
