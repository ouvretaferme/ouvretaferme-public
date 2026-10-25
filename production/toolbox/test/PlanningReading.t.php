<?php
namespace toolbox;

/**
 * Les compteurs à relever au planning (alertes lot 2, validé le 03/10/2026) :
 *
 * - un compteur entre au planning le jour où la cloche le rappellerait, le 5 d'un mois de relevé
 *   (Q2 B), et y reste jusqu'à son relevé suivant ;
 * - ce jour ne dépend que du dernier relevé et du rythme : un relevé, un rythme changé, un compteur
 *   posé ou retiré le recalculent ;
 * - les compteurs du carnet et ceux de l'eau se rangent sous les entretiens, dans un seul bloc
 *   « Rappels » (Q1 B), avec leur « Relever », qui emporte la semaine affichée ;
 * - la croix passe le relevé sans rien enregistrer : le compteur revient au rappel qui suit
 *   (demande du 03/10).
 *
 * Lancement : dev/test package=toolbox file=PlanningReading
 */
class PlanningReadingTest extends MaintenanceTaskFixtureTest {

	/**
	 * Le jour d'entrée, sur les trois cas qui le décident : le rythme, le seuil de deux mois ou d'un
	 * seul, et le compteur jamais relevé, qui entre au premier rappel après sa pose.
	 */
	public function testReadingDate(): void {

		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, '2026-06-20', '2025-01-01'), 'tous les 3 mois, relevé le 20 juin : deux mois plus tard, le rappel d\'octobre');
		$this->assertEquals('2026-07-05', NotificationLib::getReadingDate(3, '2026-05-04', '2025-01-01'), 'relevé le 4 mai : le 5 juillet tombe deux mois et un jour après');
		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, '2026-05-05', '2025-01-01'), 'relevé le 5 mai : le 5 juillet le voit encore relevé, il passe à octobre');
		$this->assertEquals('2026-08-05', NotificationLib::getReadingDate(1, '2026-06-20', '2025-01-01'), 'tous les mois : un seul mois de seuil');
		$this->assertEquals('2027-01-05', NotificationLib::getReadingDate(6, '2026-06-20', '2025-01-01'), 'tous les 6 mois : janvier et juillet');
		$this->assertEquals('2026-07-05', NotificationLib::getReadingDate(3, NULL, '2026-06-15 10:00:00'), 'jamais relevé : le premier rappel après sa pose');
		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, NULL, '2026-07-05 08:00:00'), 'posé le jour d\'un rappel : le suivant');

	}

	/**
	 * Un relevé passé repousse le jour au rappel qui suit la croix. Un relevé fait ensuite repart plus
	 * loin qu'elle, qui ne compte plus.
	 */
	public function testReadingDateSkipped(): void {

		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, '2026-03-10', '2025-01-01', '2026-07-06'), 'dû le 5 juillet, passé le 6 : le rappel d\'octobre');
		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, NULL, '2026-06-15 10:00:00', '2026-07-06'), 'jamais relevé, passé : octobre aussi');
		$this->assertEquals('2026-09-05', NotificationLib::getReadingDate(1, '2026-06-20', '2025-01-01', '2026-08-10'), 'tous les mois : le mois suivant');
		$this->assertEquals('2026-10-05', NotificationLib::getReadingDate(3, '2026-03-10', '2025-01-01', '2026-07-05'), 'passé le jour même où il entre : octobre');
		$this->assertEquals('2026-07-05', NotificationLib::getReadingDate(3, '2026-03-10', '2025-01-01', '2026-04-10'), 'une croix d\'un rappel d\'avant ne repousse pas celui de juillet');
		$this->assertEquals('2027-01-05', NotificationLib::getReadingDate(3, '2026-08-20', '2025-01-01', '2026-07-06'), 'relevé après la croix : elle ne compte plus');

	}

	/**
	 * La croix d'un compteur du carnet et celle d'un compteur d'eau : chacun quitte la semaine et
	 * revient au rappel suivant, sans relevé enregistré. Le compteur du carnet est passé avant le jour
	 * de son rappel, depuis une semaine à venir ; celui de l'eau après, depuis la semaine en cours.
	 */
	public function testSkipReading(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureReading($eStructure, 3);
		$this->configureWater($eStructure, 3);

		$eEquipment = $this->createEquipmentWithMeter($eStructure);

		// Sans `build()` : la pose d'un compteur est éprouvée par `WaterReadingTest::testMeterBuild()`.
		$eWaterMeter = new WaterMeter(['structure' => $eStructure, 'name' => 'Forage '.uniqid(), 'digits' => 5]);
		WaterMeterLib::create($eWaterMeter);

		$this->assertTrue($this->isListed($eStructure, '2026-W27', PlanningObserverLib::READING, $eEquipment['id']), 'le compteur du carnet entre le 5 juillet');

		// Relus comme l'action les reçoit (`page/equipment.p.php`) : l'élément créé porte encore `NOW()`
		// pour `createdAt`, d'où la croix compte le rappel.
		EquipmentLib::skipReading(EquipmentLib::getById($eEquipment['id']));

		$this->assertEquals('2026-07-05', Equipment::model()->select('readingSkippedDate')->whereId($eEquipment['id'])->get()['readingSkippedDate'], 'passé avant ce jour, la croix retient le rappel qu\'elle passe');

		$this->setClock('2026-07-08 10:00:00');

		$this->assertTrue($this->isListed($eStructure, '2026-W28', PlanningObserverLib::WATER_READING, $eWaterMeter['id']), 'le compteur d\'eau attend son relevé');

		WaterMeterLib::skipReading(WaterMeterLib::getById($eWaterMeter['id']));

		$this->assertEquals('2026-10-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'passé, il revient au rappel d\'octobre');
		$this->assertEquals('2026-10-05', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'celui de l\'eau aussi');
		$this->assertFalse($this->isListed($eStructure, '2026-W28', PlanningObserverLib::READING, $eEquipment['id']), 'et quitte la semaine');
		$this->assertFalse($this->isListed($eStructure, '2026-W28', PlanningObserverLib::WATER_READING, $eWaterMeter['id']), 'comme celui de l\'eau');
		$this->assertEquals(0, EquipmentReading::model()->whereEquipment($eEquipment)->count(), 'sans relevé enregistré');
		$this->assertEquals(0, WaterReading::model()->whereMeter($eWaterMeter)->count(), 'ni sur le compteur d\'eau');

	}

	/**
	 * Le décalage d'un compteur du carnet et d'un compteur d'eau à la semaine suivante (retour 15 de
	 * du 04/10) : chacun quitte la semaine affichée et reparaît le lundi d'après, sans relevé
	 * enregistré. Un relevé fait ensuite repart plus loin que le décalage, qui ne compte plus.
	 */
	public function testPostponeReading(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureReading($eStructure, 3);
		$this->configureWater($eStructure, 3);

		$eEquipment = $this->createEquipmentWithMeter($eStructure);

		// Sans `build()` : la pose d'un compteur est éprouvée par `WaterReadingTest::testMeterBuild()`.
		$eWaterMeter = new WaterMeter(['structure' => $eStructure, 'name' => 'Forage '.uniqid(), 'digits' => 5]);
		WaterMeterLib::create($eWaterMeter);

		$this->setClock('2026-07-08 10:00:00');

		$this->assertTrue($this->isListed($eStructure, '2026-W28', PlanningObserverLib::READING, $eEquipment['id']), 'le compteur du carnet attend son relevé');
		$this->assertTrue($this->isListed($eStructure, '2026-W28', PlanningObserverLib::WATER_READING, $eWaterMeter['id']), 'celui de l\'eau aussi');

		EquipmentLib::postponeReading(EquipmentLib::getById($eEquipment['id']));
		WaterMeterLib::postponeReading(WaterMeterLib::getById($eWaterMeter['id']));

		$this->assertEquals('2026-07-13', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'décalé, il est dû le lundi suivant');
		$this->assertEquals('2026-07-13', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'celui de l\'eau aussi');
		$this->assertFalse($this->isListed($eStructure, '2026-W28', PlanningObserverLib::READING, $eEquipment['id']), 'il quitte la semaine');
		$this->assertFalse($this->isListed($eStructure, '2026-W28', PlanningObserverLib::WATER_READING, $eWaterMeter['id']), 'comme celui de l\'eau');
		$this->assertTrue($this->isListed($eStructure, '2026-W29', PlanningObserverLib::READING, $eEquipment['id']), 'et reparaît la semaine suivante');
		$this->assertTrue($this->isListed($eStructure, '2026-W29', PlanningObserverLib::WATER_READING, $eWaterMeter['id']), 'comme celui de l\'eau');
		$this->assertEquals(0, EquipmentReading::model()->whereEquipment($eEquipment)->count(), 'sans relevé enregistré');

		// Sans `build()` : le relevé est le geste dont on lit l'effet sur le planning, sa saisie est
		// éprouvée par `EquipmentReadingTest`.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => '2026-07-08',
			'value' => 640,
		]));

		$this->assertEquals('2026-10-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'relevé, il repart au rappel d\'octobre, plus loin que le décalage');

	}

	/**
	 * Le planning et la cloche disent le même jour : le compteur que le planning fait entrer le 5
	 * est celui que le cron nomme ce matin-là, et pas le mois de rappel d'avant.
	 */
	public function testSameDayAsTheBell(): void {

		$this->setClock('2026-03-10 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureReading($eStructure, 3);

		$eEquipment = $this->createEquipmentWithMeter($eStructure, '2026-03-10', 500);

		$this->assertEquals('2026-07-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'relevé en mars, le compteur entre le 5 juillet');

		$this->setClock('2026-04-05 06:00:00');
		$this->assertFalse(in_array($eEquipment['id'], $this->getReadingIds()), 'le rappel d\'avril ne le nomme pas');

		$this->setClock('2026-07-05 06:00:00');
		$this->assertTrue(in_array($eEquipment['id'], $this->getReadingIds()), 'celui de juillet le nomme');

	}

	/**
	 * Un compteur du carnet n'est lu que par la semaine de son jour d'entrée, puis par la semaine en
	 * cours tant qu'il n'est pas relevé. Le relevé le fait repartir au rappel suivant.
	 */
	public function testEquipmentStaysUntilRead(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureReading($eStructure, 3);

		$eEquipment = $this->createEquipmentWithMeter($eStructure);

		$this->assertEquals('2026-07-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'jamais relevé, il entre au rappel de juillet');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', PlanningObserverLib::READING, $eEquipment['id']), 'pas sur la semaine en cours');
		$this->assertTrue($this->isListed($eStructure, '2026-W27', PlanningObserverLib::READING, $eEquipment['id']), 'sur la semaine du 5 juillet');

		$this->setClock('2026-08-20 10:00:00');

		$this->assertTrue($this->isListed($eStructure, '2026-W34', PlanningObserverLib::READING, $eEquipment['id']), 'six semaines plus tard, il attend toujours');

		// Sans `build()` : le relevé est le geste dont on lit l'effet sur le planning, sa saisie est
		// éprouvée par `EquipmentReadingTest`.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => '2026-08-20',
			'value' => 640,
		]));

		$this->assertEquals('2027-01-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'relevé, il repart au rappel de janvier');
		$this->assertFalse($this->isListed($eStructure, '2026-W34', PlanningObserverLib::READING, $eEquipment['id']), 'et quitte la semaine');

		$this->configureReading($eStructure, 1);

		$this->assertEquals('2026-10-05', $this->getDue(PlanningObserverLib::READING, $eEquipment['id'])['dueDate'], 'le rythme mensuel le rappelle dès octobre');

		$eEquipment['meter'] = NULL;
		EquipmentLib::update($eEquipment, ['meter']);

		$this->assertTrue($this->getDue(PlanningObserverLib::READING, $eEquipment['id'])->empty(), 'sans compteur, plus rien à relever');

	}

	/**
	 * Les compteurs d'eau suivent leur propre rythme, et chacun de leurs gestes recalcule : le relevé
	 * créé ou retiré, le rythme, la suppression du compteur.
	 */
	public function testWaterStaysUntilRead(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureWater($eStructure, 2);

		// Sans `build()` : la pose d'un compteur est éprouvée par `WaterReadingTest::testMeterBuild()`.
		$eWaterMeter = new WaterMeter(['structure' => $eStructure, 'name' => 'Forage '.uniqid(), 'digits' => 5]);
		WaterMeterLib::create($eWaterMeter);

		$this->assertEquals('2026-07-05', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'tous les 2 mois, jamais relevé : le rappel de juillet');

		// Sans `build()` non plus : la saisie d'un relevé est éprouvée par `WaterReadingTest`.
		$eWaterReading = new WaterReading(['structure' => $eStructure, 'meter' => $eWaterMeter, 'date' => '2026-06-15', 'value' => 1200]);
		WaterReadingLib::create($eWaterReading);

		$this->assertEquals('2026-09-05', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'relevé le 15 juin : septembre');

		$this->configureWater($eStructure, 6);

		$this->assertEquals('2027-01-05', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'tous les 6 mois : janvier');

		$eWaterReading = WaterReading::model()
			->select(['id', 'structure', 'meter', 'date', 'changed', 'changedDigits'])
			->whereMeter($eWaterMeter)
			->get();

		WaterReadingLib::delete($eWaterReading);

		$this->assertEquals('2026-07-05', $this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])['dueDate'], 'le relevé retiré, il redevient jamais relevé');

		$eWaterMeter['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeter, ['status']);

		$this->assertTrue($this->getDue(PlanningObserverLib::WATER_READING, $eWaterMeter['id'])->empty(), 'supprimé, il quitte le planning');

	}

	/**
	 * Le bloc « Rappels » : les entretiens d'abord, puis les compteurs du carnet, puis ceux de
	 * l'eau, chacun avec son badge, son dernier relevé et son « Relever », qui emporte la semaine.
	 */
	public function testBlock(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$this->configureReading($eStructure, 1);
		$this->configureWater($eStructure, 1);

		$this->createMaintenance($eStructure, '2026-06-10', 'Graissage');
		$eEquipment = $this->createEquipmentWithMeter($eStructure, '2026-04-02', 1250);

		// Sans `build()` : le compteur et son relevé sont le décor du bloc, éprouvés par `WaterReadingTest`.
		$eWaterMeter = new WaterMeter(['structure' => $eStructure, 'name' => 'Puits '.uniqid(), 'digits' => 5]);
		WaterMeterLib::create($eWaterMeter);
		WaterReadingLib::create(new WaterReading(['structure' => $eStructure, 'meter' => $eWaterMeter, 'date' => '2026-04-02', 'value' => 7259]));

		$week = '2026-W25';
		$dues = \planning\DueLib::getForWeek($eStructure, $week);

		$types = array_values(array_unique($dues['toolbox']->getColumn('type')));

		$this->assertEquals([PlanningObserverLib::MAINTENANCE, PlanningObserverLib::READING, PlanningObserverLib::WATER_READING], $types, 'les entretiens, puis les compteurs, puis l\'eau');

		$html = new \planning\DueUi()->getWeek($eStructure, $week, $dues);

		$this->assertTrue(str_contains($html, '/toolbox/equipmentReading:create?structure='.$eStructure['id'].'&equipment='.$eEquipment['id'].'&origin=planning&week='.$week), 'le « Relever » du compteur emporte la semaine');
		$this->assertTrue(str_contains($html, '/toolbox/waterReading:create?structure='.$eStructure['id'].'&meter='.$eWaterMeter['id'].'&origin=planning&week='.$week), 'celui de l\'eau aussi');
		$this->assertTrue(str_contains($html, 'id="planning-due"'), 'dans le nœud que la réponse d\'un geste remplace');

	}

	private function getDue(string $type, int $reference): \planning\Due {

		return \planning\Due::model()
			->select(\planning\Due::getSelection())
			->whereType($type)
			->whereReference($reference)
			->get();

	}

	private function isListed(\structure\Structure $eStructure, string $week, string $type, int $reference): bool {

		$dues = \planning\DueLib::getForWeek($eStructure, $week);

		if(array_key_exists('toolbox', $dues) === FALSE) {
			return FALSE;
		}

		return $dues['toolbox']->find(fn(\planning\Due $eDue) => $eDue['type'] === $type and $eDue['reference'] === $reference)->notEmpty();

	}

	/** Les matériels que le cron mettrait en file à l'heure figée. */
	private function getReadingIds(): array {
		return array_map(fn(Equipment $eEquipment) => $eEquipment['id'], NotificationLib::getReadings());
	}

	/**
	 * Un matériel à compteur au nom propre au test, relevé à `$date` quand elle est donnée.
	 */
	private function createEquipmentWithMeter(\structure\Structure $eStructure, ?string $date = NULL, ?int $value = NULL): Equipment {

		$eTool = \work\ToolLib::getNewTool($eStructure, NULL);

		// Sans `build()` : le nom est choisi par le test, et « Mon matériel » n'est pas ce qu'il éprouve.
		$eTool['name'] = 'Tracteur '.uniqid();

		\work\ToolLib::create($eTool);

		$eEquipment = new Equipment([
			'structure' => $eStructure,
			'tool' => $eTool,
			'meter' => Equipment::HOURS,
			'eToolNew' => new \work\Tool(),
		]);

		// Sans `build()` non plus : le relevé est le décor, pas la saisie.
		EquipmentLib::follow($eEquipment, ($date === NULL) ? new EquipmentReading() : new EquipmentReading([
			'structure' => $eStructure,
			'date' => $date,
			'value' => $value,
		]));

		return $eEquipment;

	}

	/**
	 * Le rythme du rappel des compteurs du carnet, réglé comme depuis son panneau.
	 */
	private function configureReading(\structure\Structure $eStructure, int $readingEveryMonths): void {

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

}
?>
