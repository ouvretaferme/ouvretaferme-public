<?php
namespace toolbox;

/**
 * Ce que le carnet d'entretien dit dû au planning (`PlanningObserverLib`, lu par `planning\DueLib`),
 * contrat « Alertes du planning » validé le 03/10/2026 :
 *
 * - un entretien daté est dû à sa date, et n'apparait sur une semaine à venir que la semaine venue ;
 * - un retard au compteur est dû maintenant, sans date, et ne se lit que sur la semaine en cours ;
 * - un geste qui résout fait disparaitre la ligne, sans que la source la ferme elle-même ;
 * - un recalcul qui ne change rien n'écrit rien.
 *
 * Lancement : dev/test package=toolbox file=PlanningDue
 */
class PlanningDueTest extends MaintenanceTaskFixtureTest {

	/**
	 * Une date à venir n'est lue que par sa semaine, et la semaine en cours quand elle arrive. Le temps
	 * qui passe ne recalcule rien : la ligne est la même, c'est la lecture qui la compare à l'horloge.
	 */
	public function testDatedComesWithItsWeek(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure, '2026-07-01', 'Courroie');

		$eDue = $this->getDue($eMaintenance);

		$this->assertEquals('2026-07-01', $eDue['dueDate'], 'l\'entretien est dû à sa date');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenance), 'pas sur la semaine en cours');
		$this->assertTrue($this->isListed($eStructure, '2026-W27', $eMaintenance), 'sur la semaine de sa date');
		$this->assertFalse($this->isListed($eStructure, '2026-W28', $eMaintenance), 'et pas sur la suivante');

		$this->setClock('2026-07-08 10:00:00');

		$this->assertTrue($this->isListed($eStructure, '2026-W28', $eMaintenance), 'une fois passée, la date est un retard de la semaine en cours');
		$this->assertEquals($eDue['id'], $this->getDue($eMaintenance)['id'], 'sans qu\'aucune ligne ait été réécrite');

	}

	/**
	 * Un retard au compteur ne se date pas : il est dû dès le relevé qui le dépasse, ne se lit que sur
	 * la semaine en cours, et disparait avec le relevé qui le portait.
	 */
	public function testMeterOverdueIsDueNow(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eEquipment = $this->createEquipmentWithMeter($eStructure);

		// Sans `build()` : l'entretien est le décor, une échéance au compteur seul.
		$eMaintenance = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 500,
		]);

		MaintenanceLib::create($eMaintenance);

		$this->assertTrue($this->getDue($eMaintenance)->empty(), 'sous l\'échéance, rien n\'est dû');

		$eEquipmentReading = new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => '2026-06-15',
			'value' => 520,
		]);

		EquipmentReadingLib::create($eEquipmentReading);

		$eDue = $this->getDue($eMaintenance);

		$this->assertTrue($eDue->notEmpty(), 'le relevé qui dépasse l\'échéance rend l\'entretien dû');
		$this->assertEquals(NULL, $eDue['dueDate'], 'dès maintenant, sans date');
		$this->assertTrue($this->isListed($eStructure, '2026-W25', $eMaintenance), 'la semaine en cours le liste');
		$this->assertFalse($this->isListed($eStructure, '2026-W26', $eMaintenance), 'pas une semaine à venir');

		$eEquipmentReading = EquipmentReading::model()
			->select(EquipmentReading::getSelection())
			->whereEquipment($eEquipment)
			->whereDate('2026-06-15')
			->get();

		EquipmentReadingLib::delete($eEquipmentReading);

		$this->assertTrue($this->getDue($eMaintenance)->empty(), 'le relevé retiré, le retard disparait');

	}

	/**
	 * L'entretien fait repart d'un an : sa ligne change de date et quitte la semaine. L'entretien
	 * supprimé n'a plus de ligne.
	 */
	public function testGestureResolves(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure, '2026-06-10', 'Graissage');

		$this->configure($eStructure);

		$this->assertTrue($this->isListed($eStructure, '2026-W25', $eMaintenance), 'le retard est listé');

		[$eMaintenanceDone, $fw] = $this->build($eMaintenance['equipment'], $eMaintenance, []);
		$this->assertTrue($fw->ok(), 'l\'entretien fait se construit : '.$fw);

		MaintenanceDoneLib::create($eMaintenanceDone);

		$this->assertEquals('2027-06-15', $this->getDue($eMaintenance)['dueDate'], 'fait, il repart d\'un an');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenance), 'et quitte la semaine');

		$eMaintenance['status'] = Maintenance::DELETED;

		MaintenanceLib::update($eMaintenance, ['status']);

		$this->assertTrue($this->getDue($eMaintenance)->empty(), 'supprimé, il n\'a plus de ligne');

	}

	/**
	 * La croix passe l'échéance d'un entretien en retard ou bientôt dû : il repart d'aujourd'hui, comme
	 * après « Ignorer ce retard », et quitte la semaine. Un entretien « Une seule fois » n'a pas de
	 * croix : sans fréquence, il ne reviendrait jamais.
	 */
	public function testSkip(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenanceOverdue = $this->createMaintenance($eStructure, '2026-06-10', 'Graissage');
		$eMaintenanceSoon = $this->createMaintenance($eStructure, '2026-06-18', 'Courroie');
		$eMaintenanceOnce = $this->createMaintenance($eStructure, '2026-06-17', 'Pneus');

		$eMaintenanceOnce['everyMonths'] = NULL;
		MaintenanceLib::update($eMaintenanceOnce, ['everyMonths']);

		$this->assertTrue($this->getFilled($eStructure, $eMaintenanceOverdue)->acceptSkip(), 'un retard se passe');
		$this->assertTrue($this->getFilled($eStructure, $eMaintenanceSoon)->acceptSkip(), 'un entretien bientôt dû aussi');
		$this->assertFalse($this->getFilled($eStructure, $eMaintenanceSoon)->acceptIgnore(), 'quand « Ignorer ce retard » du carnet reste aux retards');
		$this->assertFalse($this->getFilled($eStructure, $eMaintenanceOnce)->acceptSkip(), 'un entretien « Une seule fois » ne se passe pas');

		MaintenanceLib::ignore($this->getFilled($eStructure, $eMaintenanceSoon));

		$this->assertEquals('2027-06-15', $this->getDue($eMaintenanceSoon)['dueDate'], 'passé, il repart d\'aujourd\'hui et de sa fréquence');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenanceSoon), 'et quitte la semaine');
		$this->assertEquals(0, MaintenanceDone::model()->whereMaintenance($eMaintenanceSoon)->count(), 'sans rien dans l\'historique');

		$html = new \planning\DueUi()->getWeek($eStructure, '2026-W25', \planning\DueLib::getForWeek($eStructure, '2026-W25'));

		$this->assertTrue(str_contains($html, 'data-ajax="/toolbox/maintenance:doSkip" post-id="'.$eMaintenanceOverdue['id'].'"'), 'le retard porte sa croix au planning');
		$this->assertTrue(str_contains($html, '/toolbox/maintenanceDone:create?maintenance='.$eMaintenanceOnce['id']), 'l\'entretien « Une seule fois » est au planning');
		$this->assertFalse(str_contains($html, 'data-ajax="/toolbox/maintenance:doSkip" post-id="'.$eMaintenanceOnce['id'].'"'), 'sans croix');

	}

	/**
	 * Le décalage à la semaine suivante (retour 15 du 04/10) : l'entretien quitte la semaine
	 * affichée et reparaît le lundi d'après, son échéance au carnet inchangée, « Une seule fois »
	 * compris. Un retard au compteur, qui n'a pas de date, se décale aussi. Une échéance qui repart
	 * efface le décalage.
	 */
	public function testPostpone(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenanceOverdue = $this->createMaintenance($eStructure, '2026-06-10', 'Graissage');
		$eMaintenanceOnce = $this->createMaintenance($eStructure, '2026-06-17', 'Pneus');

		$eMaintenanceOnce['everyMonths'] = NULL;
		MaintenanceLib::update($eMaintenanceOnce, ['everyMonths']);

		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceOverdue));
		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceOnce));

		$this->assertEquals('2026-06-22', $this->getDue($eMaintenanceOverdue)['dueDate'], 'décalé, le retard est dû le lundi suivant');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenanceOverdue), 'il quitte la semaine');
		$this->assertTrue($this->isListed($eStructure, '2026-W26', $eMaintenanceOverdue), 'et reparaît la suivante');
		$this->assertEquals('2026-06-10', $this->getFilled($eStructure, $eMaintenanceOverdue)['plannedDate'], 'son échéance au carnet ne bouge pas');
		$this->assertTrue($this->isListed($eStructure, '2026-W26', $eMaintenanceOnce), 'un entretien « Une seule fois » se décale aussi');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenanceOnce), 'et quitte la semaine');

		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceOverdue));

		$this->assertEquals('2026-06-29', $this->getDue($eMaintenanceOverdue)['dueDate'], 'décalé de nouveau, il part de la semaine où le planning le montre, et non de la semaine en cours');

		$eMaintenanceLater = $this->createMaintenance($eStructure, '2026-07-15', 'Courroie');
		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceLater));

		$this->assertEquals('2026-07-20', $this->getDue($eMaintenanceLater)['dueDate'], 'à venir, il part de la semaine de son échéance');

		MaintenanceLib::ignore($this->getFilled($eStructure, $eMaintenanceOverdue));

		$this->assertEquals(NULL, Maintenance::model()->select('postponedDate')->whereId($eMaintenanceOverdue['id'])->get()['postponedDate'], 'l\'échéance qui repart efface le décalage');
		$this->assertEquals('2027-06-15', $this->getDue($eMaintenanceOverdue)['dueDate'], 'et l\'entretien est dû à sa nouvelle échéance');

		$eEquipment = $this->createEquipmentWithMeter($eStructure);

		// Sans `build()` : l'entretien est le décor, une échéance au compteur seul.
		$eMaintenanceMeter = new Maintenance([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 500,
		]);

		MaintenanceLib::create($eMaintenanceMeter);

		// Sans `build()` : le relevé est le décor qui met l'entretien en retard.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $eStructure,
			'equipment' => $eEquipment,
			'date' => '2026-06-15',
			'value' => 520,
		]));

		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceMeter));

		$this->assertEquals('2026-06-22', $this->getDue($eMaintenanceMeter)['dueDate'], 'un retard au compteur décalé prend la date du décalage');
		$this->assertFalse($this->isListed($eStructure, '2026-W25', $eMaintenanceMeter), 'et quitte la semaine en cours');

		$html = new \planning\DueUi()->getZones($eStructure, '2026-W26', \planning\DueLib::getForWeek($eStructure, '2026-W26'));

		$this->assertTrue(str_contains($html, 'post-id="'.$eMaintenanceMeter['id'].'"'), 'reparu, il porte ses gestes');
		$this->assertTrue(str_contains($html, '/toolbox/maintenance:doPostpone'), 'dont le décalage');
		$this->assertTrue(str_contains($html, 'post-origin="'.\planning\DueLib::ORIGIN_ZONE.'"'), 'qui emporte la forme de la zone');

	}

	/**
	 * Éditer l'échéance d'un entretien décalé efface le décalage (R-Retours-0410/15, relecteur point 5) :
	 * l'échéance au planning étant la plus tardive des deux, une échéance avancée à la main resterait sinon
	 * masquée par le décalage. Une édition qui ne change pas l'échéance, le seul nom, le garde.
	 */
	public function testPostponeEdited(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenanceEdited = $this->createMaintenance($eStructure, '2026-06-10', 'Graissage');
		$eMaintenanceRenamed = $this->createMaintenance($eStructure, '2026-06-10', 'Courroie');

		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceEdited));
		MaintenanceLib::postpone($this->getFilled($eStructure, $eMaintenanceRenamed));

		if($this->assertDecorPresent($this->getDue($eMaintenanceEdited)['dueDate'] === '2026-06-22', 'un entretien en retard décalé au lundi suivant') === FALSE) {
			return;
		}

		// La liste de `maintenance:doUpdate`, que le formulaire poste toujours en entier.
		$properties = ['name', 'everyValue', 'everyMonths', 'plannedValue', 'plannedDate'];

		$eMaintenance = $this->getFilled($eStructure, $eMaintenanceEdited);
		$eMaintenance->build($properties, ['name' => 'Graissage', 'everyMonths' => '12', 'plannedDate' => '2026-06-12']);
		MaintenanceLib::update($eMaintenance, $properties);

		$this->assertEquals(NULL, Maintenance::model()->select('postponedDate')->whereId($eMaintenanceEdited['id'])->get()['postponedDate'], 'l\'échéance éditée n\'efface pas le décalage');
		$this->assertEquals('2026-06-12', $this->getDue($eMaintenanceEdited)['dueDate'], 'l\'entretien n\'est pas dû à l\'échéance éditée');

		$eMaintenance = $this->getFilled($eStructure, $eMaintenanceRenamed);
		$eMaintenance->build($properties, ['name' => 'Courroie neuve', 'everyMonths' => '12', 'plannedDate' => '2026-06-10']);
		MaintenanceLib::update($eMaintenance, $properties);

		$this->assertEquals('2026-06-22', Maintenance::model()->select('postponedDate')->whereId($eMaintenanceRenamed['id'])->get()['postponedDate'], 'le seul nom édité efface le décalage');
		$this->assertEquals('2026-06-22', $this->getDue($eMaintenanceRenamed)['dueDate'], 'le seul nom édité ramène l\'entretien à son retard');

	}

	/** L'entretien tel que le carnet le montre, avec son échéance (`MaintenanceLib::fill()`). */
	protected function getFilled(\structure\Structure $eStructure, Maintenance $eMaintenance): Maintenance {
		return EquipmentLib::getByStructure($eStructure)[$eMaintenance['equipment']['id']]['cMaintenance'][$eMaintenance['id']];
	}

	/**
	 * Un recalcul qui trouve ce que la table porte déjà n'écrit rien : les gestes du carnet en
	 * enchainent plusieurs. La passe hebdomadaire reprend la structure.
	 */
	public function testRefreshWritesOnlyChanges(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure, '2026-07-01');

		$eDue = $this->getDue($eMaintenance);

		\planning\DueLib::refresh($eStructure, 'toolbox');

		$this->assertEquals($eDue['id'], $this->getDue($eMaintenance)['id'], 'la ligne est gardée telle quelle');

		// Sans `MaintenanceLib::update()` : la passe hebdomadaire est le filet d'une écriture qui n'aurait
		// pas recalculé, et c'est ce cas qu'il faut monter.
		Maintenance::model()->update($eMaintenance, ['plannedDate' => '2026-07-08']);

		$this->assertEquals('2026-07-01', $this->getDue($eMaintenance)['dueDate'], 'une écriture hors du carnet ne recalcule pas');

		$this->assertTrue(\planning\DueLib::refreshAll() > 0, 'la passe hebdomadaire recalcule des structures');
		$this->assertEquals('2026-07-08', $this->getDue($eMaintenance)['dueDate'], 'dont celle-ci');

	}

	/**
	 * Le bloc rendu au planning est celui du carnet, sous son titre : en tête du bloc à l'ancien planning,
	 * dans la tête de la zone au nouveau (retour 15 du 04/10), où chaque ligne est une carte
	 * dont les gestes sont les portes, sans cadre commun ni résumé (retours du 05/10).
	 */
	public function testBlockIsTheNotebook(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructure = $this->getStructure();
		$eMaintenance = $this->createMaintenance($eStructure, '2026-06-17');

		$dues = \planning\DueLib::getForWeek($eStructure, '2026-W25');

		$html = new \planning\DueUi()->getWeek($eStructure, '2026-W25', $dues);

		$this->assertTrue(str_contains($html, '/toolbox/maintenanceDone:create?maintenance='.$eMaintenance['id']), 'et le « Fait » de l\'entretien');

		$html = new \planning\DueUi()->getZones($eStructure, '2026-W25', $dues);

		$this->assertTrue(str_contains($html, '/toolbox/maintenanceDone:create?maintenance='.$eMaintenance['id'].'&origin='.\planning\DueLib::ORIGIN_ZONE), 'et le « Fait » emporte la forme de la zone');

	}

	protected function getDue(Maintenance $eMaintenance): \planning\Due {

		return \planning\Due::model()
			->select(\planning\Due::getSelection())
			->whereType(PlanningObserverLib::MAINTENANCE)
			->whereReference($eMaintenance['id'])
			->get();

	}

	protected function isListed(\structure\Structure $eStructure, string $week, Maintenance $eMaintenance): bool {

		$dues = \planning\DueLib::getForWeek($eStructure, $week);

		if(array_key_exists('toolbox', $dues) === FALSE) {
			return FALSE;
		}

		return $dues['toolbox']->find(fn(\planning\Due $eDue) => $eDue['reference'] === $eMaintenance['id'])->notEmpty();

	}

	protected function createEquipmentWithMeter(\structure\Structure $eStructure): Equipment {

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

		EquipmentLib::follow($eEquipment, new EquipmentReading());

		return $eEquipment;

	}

}
?>
