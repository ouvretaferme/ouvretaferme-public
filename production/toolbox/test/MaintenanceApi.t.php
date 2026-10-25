<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\Maintenance (production/toolbox/api/Maintenance_0_1beta.a.php).
 *
 * Le décor est un matériel de la structure de référence que le carnet n'a jamais suivi, mis au
 * carnet par l'API au début de chaque cas, avec ou sans compteur. L'heure est figée : l'échéance
 * d'un entretien se compte en jours depuis aujourd'hui.
 *
 * Lancement : dev/test package=toolbox file=MaintenanceApi
 */
class MaintenanceApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	const FIELDS = ['id', 'equipment', 'name', 'everyValue', 'everyMonths', 'plannedDate', 'plannedValue', 'ignoredDate', 'status', 'due', 'remainingDays', 'remainingValue'];

	protected function getTransactionModel(): \ModuleModel {
		return new MaintenanceModel();
	}

	/**
	 * Un matériel de « Mon matériel », créé par l'API comme le ferait un intégrateur : le décor ne
	 * porte pas de matériel, et le cas ne dépend ainsi d'aucune donnée de la base.
	 */
	private function createTool(string $name): void {

		$this->callApi('POST', 'tool', body: [
			'name' => $name,
			'action' => NULL,
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

	}

	/** Crée un matériel, le met au carnet, et rend l'id de son suivi. */
	private function follow(?string $meter = Equipment::HOURS): ?int {

		$followed = Equipment::model()
			->whereStructure($this->eStructure)
			->getColumn('tool')
			->getIds();

		$this->createTool('Carnet API '.(count($followed) + 1));

		$eTool = \work\Tool::model()
			->select(['id'])
			->whereStructure($this->eStructure)
			->whereName('LIKE', 'Carnet API %')
			->whereId('NOT IN', $followed, if: $followed !== [])
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertTrue($eTool->notEmpty(), 'la structure '.static::STRUCTURE.' porte un matériel que le carnet n\'a jamais suivi') === FALSE) {
			return NULL;
		}

		$response = $this->callApi('POST', 'equipment', body: ['tool' => $eTool['id'], 'meter' => $meter, 'registration' => NULL]);

		return $response['body']['id'];

	}

	/** Le corps d'un entretien, que chaque cas complète : une création porte toujours ces six propriétés. */
	private function plan(int $equipment, array $values): array {

		return $this->callApi('POST', 'maintenance', body: $values + [
			'equipment' => $equipment,
			'name' => 'Vidange',
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedValue' => NULL,
			'plannedDate' => NULL
		]);

	}

	private function put(int $id, array $properties, array $values): array {

		return $this->callApi('PUT', 'maintenance/{id}', body: ['properties' => $properties] + $values, get: ['id' => $id]);

	}

	/** Un entretien d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getMaintenanceForeign(): Maintenance {

		return Maintenance::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(Maintenance::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function testList(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$first = $this->plan($equipment, ['plannedDate' => '2026-09-01']);
		$second = $this->plan($equipment, ['name' => 'Graissage', 'plannedDate' => '2026-10-01']);

		$this->put($second['body']['id'], ['status'], ['status' => Maintenance::DELETED]);

		// La liste écarte les entretiens retirés, comme `toolList` les matériels supprimés.
		$expected = Maintenance::model()
			->whereStructure($this->eStructure)
			->whereStatus('!=', Maintenance::DELETED)
			->count();

		$response = $this->callApi('GET', 'maintenanceList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les entretiens de la structure');
		$this->assertEquals(self::FIELDS, array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');

		$ids = Maintenance::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucun entretien hors de la structure');

		$response = $this->callApi('GET', 'maintenanceList', get: ['equipment' => $equipment]);

		$this->assertEquals([$first['body']['id']], array_column($response['body']['list'], 'id'), 'le filtre par matériel rend ses entretiens, sans celui qui est retiré');

		$response = $this->callApi('GET', 'maintenanceList', get: ['equipment' => $equipment, 'status' => Maintenance::DELETED]);

		$this->assertEquals([$second['body']['id']], array_column($response['body']['list'], 'id'), '?status=deleted retrouve l\'entretien retiré');

		$response = $this->callApi('GET', 'maintenanceList', get: ['status' => 'unknown']);

		$this->assertEquals(400, $response['status'], 'un statut qui n\'existe pas est refusé');

		$response = $this->callApi('GET', 'maintenanceList', get: ['equipment' => $equipment, 'status' => Maintenance::ACTIVE]);

		$this->assertEquals([$first['body']['id']], array_column($response['body']['list'], 'id'), 'le filtre par statut écarte l\'entretien retiré');
		$this->assertEquals($first['body'], $response['body']['list'][0], 'la liste rend l\'entretien comme la création, échéance comprise');

	}

	public function testReadForeign(): void {

		$eMaintenanceForeign = $this->getMaintenanceForeign();

		if($this->assertTrue($eMaintenanceForeign->notEmpty(), 'la base porte un entretien hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'maintenance/{id}', get: ['id' => $eMaintenanceForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateOnce(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		// Les deux fréquences à `null` : un entretien à faire une seule fois.
		$response = $this->plan($equipment, ['name' => 'Contrôle technique', 'plannedDate' => '2026-10-05']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(self::FIELDS, array_keys($response['body']));
		$this->assertEquals($equipment, $response['body']['equipment']);
		$this->assertEquals('Contrôle technique', $response['body']['name']);
		$this->assertEquals(NULL, $response['body']['everyValue']);
		$this->assertEquals(NULL, $response['body']['everyMonths']);
		$this->assertEquals('2026-10-05', $response['body']['plannedDate']);
		$this->assertEquals(Maintenance::ACTIVE, $response['body']['status']);

		$this->assertEquals(NULL, $response['body']['due'], 'à 112 jours, l\'entretien n\'est pas encore annoncé');
		$this->assertEquals(112, $response['body']['remainingDays']);
		$this->assertEquals(NULL, $response['body']['remainingValue']);

		$eMaintenance = Maintenance::model()
			->select(['structure'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eMaintenance['structure']['id'], 'l\'entretien est posé sur la structure du jeton');

		$read = $this->callApi('GET', 'maintenance/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

	}

	public function testCreateRegular(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-10', 'value' => 1210]);

		$response = $this->plan($equipment, ['everyValue' => 250, 'everyMonths' => 12, 'plannedValue' => 1250, 'plannedDate' => '2027-01-15']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(250, $response['body']['everyValue']);
		$this->assertEquals(12, $response['body']['everyMonths']);
		$this->assertEquals(1250, $response['body']['plannedValue']);

		// Dû au premier des deux : le compteur, à 40 heures, l'annonce avant la date.
		$this->assertEquals(MaintenanceLib::SOON, $response['body']['due']);
		$this->assertEquals(40, $response['body']['remainingValue']);
		$this->assertEquals(214, $response['body']['remainingDays']);

		// Le compteur avance, et l'échéance se relit sans que l'entretien ait changé.
		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-15', 'value' => 1262]);

		$read = $this->callApi('GET', 'maintenance/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(MaintenanceLib::OVERDUE, $read['body']['due']);
		$this->assertEquals(-12, $read['body']['remainingValue'], 'ce qui reste devient négatif une fois l\'échéance dépassée');

	}

	public function testCreateOverdueByDate(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		// Une date passée est admise : l'entretien arrive en retard, ce que la personne sait déjà.
		$response = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-06-01']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(MaintenanceLib::OVERDUE, $response['body']['due']);
		$this->assertEquals(-14, $response['body']['remainingDays']);

	}

	public function testCreateFrequencyFromBodyIgnored(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		// `frequency` est le choix du formulaire de l'écran, qui efface la fréquence quand il vaut
		// « once » : venu du corps, il n'efface rien.
		$response = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-09-01', 'frequency' => 'once']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(6, $response['body']['everyMonths'], 'la fréquence donnée est gardée');

	}

	public function testCreateWithoutSchedule(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$response = $this->plan($equipment, ['everyMonths' => 6]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::plannedDate.planned', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateMeterValuesWithoutMeter(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$response = $this->plan($equipment, ['everyValue' => 250, 'plannedDate' => '2026-09-01']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::everyValue.meter', $response['body'][0]['code'] ?? NULL);

		$response = $this->plan($equipment, ['plannedValue' => 1500]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::plannedValue.meter', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateEquipmentRefused(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $equipment]);

		// Un matériel qu'on ne suit plus ne reçoit plus d'entretien.
		$response = $this->plan($equipment, ['plannedDate' => '2026-09-01']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::equipment.owned', $response['body'][0]['code'] ?? NULL);

		$eEquipmentForeign = Equipment::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(Equipment::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertTrue($eEquipmentForeign->notEmpty(), 'la base porte un matériel suivi hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$before = Maintenance::model()->whereEquipment($eEquipmentForeign)->count();

		$response = $this->plan($eEquipmentForeign['id'], ['plannedDate' => '2026-09-01']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::equipment.owned', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals($before, Maintenance::model()->whereEquipment($eEquipmentForeign)->count(), 'rien n\'est planifié sur le matériel d\'une autre structure');

	}

	public function testCreateMissing(): void {

		$response = $this->callApi('POST', 'maintenance', body: ['name' => 'Vidange', 'everyValue' => NULL, 'everyMonths' => NULL, 'plannedValue' => NULL, 'plannedDate' => '2026-09-01']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::equipment.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdate(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$created = $this->plan($equipment, ['everyValue' => 250, 'plannedValue' => 1500]);
		$id = $created['body']['id'];

		$response = $this->put($id, ['name'], ['name' => 'Vidange moteur']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('Vidange moteur', $response['body']['name']);
		$this->assertEquals(250, $response['body']['everyValue'], 'le reste de l\'entretien n\'a pas bougé');
		$this->assertEquals(1500, $response['body']['plannedValue']);

		// La date nommée avant le compteur : l'étape `planned` lit quand même le compteur du corps.
		$response = $this->put($id, ['plannedDate', 'plannedValue'], ['plannedDate' => NULL, 'plannedValue' => 1600]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1600, $response['body']['plannedValue']);
		$this->assertEquals(NULL, $response['body']['plannedDate']);

		$response = $this->put($id, ['plannedValue', 'plannedDate'], ['plannedValue' => NULL, 'plannedDate' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::plannedDate.planned', $response['body'][0]['code'] ?? NULL);

		// Les deux fréquences retirées : l'entretien régulier devient un entretien à faire une seule fois.
		$response = $this->put($id, ['everyValue', 'everyMonths'], ['everyValue' => NULL, 'everyMonths' => NULL]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(NULL, $response['body']['everyValue']);
		$this->assertEquals(NULL, $response['body']['everyMonths']);

		$response = $this->put($id, ['everyValue', 'everyMonths'], ['everyValue' => NULL, 'everyMonths' => 6]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(6, $response['body']['everyMonths']);

	}

	public function testUpdateTogether(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$created = $this->plan($equipment, ['everyValue' => 250, 'plannedValue' => 1500]);
		$id = $created['body']['id'];

		// Une fréquence seule laisserait l'autre hors du jugement, une échéance seule aussi.
		$response = $this->put($id, ['everyMonths'], ['everyMonths' => 6]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

		$response = $this->put($id, ['plannedDate'], ['plannedDate' => '2026-09-01']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

		// Un entretien ne change pas de matériel.
		$response = $this->put($id, ['equipment'], ['equipment' => $equipment]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

		// « Ignorer ce retard » n'est pas un geste de l'API.
		$response = $this->put($id, ['ignoredDate'], ['ignoredDate' => '2026-06-15']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateDelete(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-06-01']);
		$id = $created['body']['id'];

		// Le seul statut qui se poste est la suppression : DONE ne vient qu'avec l'entretien fait.
		$response = $this->put($id, ['status'], ['status' => Maintenance::DONE]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Maintenance::status.deleted', $response['body'][0]['code'] ?? NULL);

		$response = $this->put($id, ['status'], ['status' => Maintenance::DELETED]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Maintenance::DELETED, $response['body']['status']);
		$this->assertEquals(NULL, $response['body']['due'], 'un entretien retiré n\'a plus d\'échéance, même en retard');
		$this->assertEquals(NULL, $response['body']['remainingDays']);

		// Un entretien retiré ne se modifie plus : c'est `acceptUpdate()`.
		$again = $this->put($id, ['name'], ['name' => 'Vidange moteur']);

		$this->assertEquals(412, $again['status']);
		$this->assertEquals('accept_update', $again['body'][0]['code'] ?? NULL);

	}

	public function testDueOnUnfollowed(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-06-01']);

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $equipment]);

		// Le carnet ne montre plus le matériel : l'entretien se lit toujours, sans échéance.
		$response = $this->callApi('GET', 'maintenance/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Maintenance::ACTIVE, $response['body']['status']);
		$this->assertEquals(NULL, $response['body']['due']);
		$this->assertEquals(NULL, $response['body']['remainingDays']);

	}

	public function testUpdateForeign(): void {

		$eMaintenanceForeign = $this->getMaintenanceForeign();

		if($this->assertTrue($eMaintenanceForeign->notEmpty(), 'la base porte un entretien hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->put($eMaintenanceForeign['id'], ['status'], ['status' => Maintenance::DELETED]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals(Maintenance::ACTIVE, Maintenance::model()->whereId($eMaintenanceForeign['id'])->getValue('status'), 'l\'entretien d\'une autre structure est toujours actif');

	}

	public function testDeleteRouteAbsent(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->plan($equipment, ['plannedDate' => '2026-09-01']);

		// Retirer un entretien est un changement de statut : aucune route DELETE n'existe.
		$response = $this->callApi('DELETE', 'maintenance/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

}
?>
