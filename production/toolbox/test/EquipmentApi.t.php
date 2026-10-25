<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\Equipment (production/toolbox/api/Equipment_0_1beta.a.php).
 *
 * Le décor se lit dans la base : un matériel de « Mon matériel » de la structure de référence que le
 * carnet n'a jamais suivi, que chaque cas met lui-même au carnet par l'API. Les cas ne dépendent
 * donc ni des matériels que la structure suit déjà, ni de leur historique.
 *
 * Lancement : dev/test package=toolbox file=EquipmentApi
 */
class EquipmentApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	protected function getTransactionModel(): \ModuleModel {
		return new EquipmentModel();
	}

	/**
	 * Deux matériels de « Mon matériel » que le carnet n'a jamais suivis, ni actifs ni endormis : un
	 * matériel endormi se réveillerait avec un historique qui ne vient pas du cas. Créés au premier
	 * appel du cas, relus ensuite.
	 */
	private function getToolsFree(): \Collection {

		if(\work\Tool::model()->whereStructure($this->eStructure)->whereName('LIKE', 'Carnet API %')->exists() === FALSE) {
			$this->createTool('Carnet API 1');
			$this->createTool('Carnet API 2');
		}

		$followed = Equipment::model()
			->whereStructure($this->eStructure)
			->getColumn('tool')
			->getIds();

		return \work\Tool::model()
			->select(['id', 'name'])
			->whereStructure($this->eStructure)
			->whereStatus(\work\Tool::ACTIVE)
			->whereName('LIKE', 'Carnet API %')
			->whereId('NOT IN', $followed, if: $followed !== [])
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

	}

	private function getToolFree(): \work\Tool {

		$cTool = $this->getToolsFree();

		return $cTool->empty() ? new \work\Tool() : $cTool->first();

	}

	private function requireTool(): bool {

		return $this->assertTrue($this->getToolFree()->notEmpty(), 'la structure '.static::STRUCTURE.' porte un matériel que le carnet n\'a jamais suivi');

	}

	/** Un matériel suivi d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getEquipmentForeign(): Equipment {

		return Equipment::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	/**
	 * Un matériel créé pour le cas par l'API, comme le ferait un intégrateur : le décor ne porte pas
	 * de matériel, et le cas ne dépend ainsi d'aucune donnée de la base. Il n'est cité par aucune
	 * intervention ni aucun itinéraire : sans le carnet, `work\ToolLib::delete()` effacerait sa ligne.
	 */
	private function createTool(string $name = 'Matériel du test du carnet'): int {

		$response = $this->callApi('POST', 'tool', body: [
			'name' => $name,
			'action' => NULL,
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		]);

		return $response['body']['id'];

	}

	/** Met le matériel libre au carnet par l'API, et rend la réponse. */
	private function follow(?string $meter): array {

		return $this->callApi('POST', 'equipment', body: ['tool' => $this->getToolFree()['id'], 'meter' => $meter, 'registration' => NULL]);

	}

	public function testList(): void {

		// La liste d'une structure sans matériel suivi ne prouverait rien de ses champs.
		$this->follow(Equipment::HOURS);

		$expected = Equipment::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'equipmentList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les matériels suivis de la structure');

		$ids = Equipment::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucun matériel hors de la structure');

		$this->assertEquals(['id', 'tool', 'meter', 'registration', 'status'], array_keys($response['body']['list'][0] ?? []), 'la ressource rend exactement ces champs');

	}

	public function testListFilters(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$eTool = $this->getToolFree();
		$created = $this->follow(Equipment::HOURS);

		$response = $this->callApi('GET', 'equipmentList', get: ['tool' => $eTool['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$created['body']['id']], array_column($response['body']['list'], 'id'), 'le filtre par matériel ne rend que son suivi');

		$response = $this->callApi('GET', 'equipmentList', get: ['status' => Equipment::ACTIVE, 'limit' => 500]);

		$statuses = array_unique(array_column($response['body']['list'], 'status'));

		$this->assertEquals([Equipment::ACTIVE], array_values($statuses), 'le filtre ne laisse passer que le statut demandé');

	}

	public function testReadForeign(): void {

		$eEquipmentForeign = $this->getEquipmentForeign();

		if($this->assertTrue($eEquipmentForeign->notEmpty(), 'la base porte un matériel suivi hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'equipment/{id}', get: ['id' => $eEquipmentForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreate(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$eTool = $this->getToolFree();

		$response = $this->follow(Equipment::HOURS);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($eTool['id'], $response['body']['tool']);
		$this->assertEquals(Equipment::HOURS, $response['body']['meter']);
		$this->assertEquals(Equipment::ACTIVE, $response['body']['status']);

		$eEquipment = Equipment::model()
			->select(['structure', 'lastMeter'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eEquipment['structure']['id'], 'le matériel est suivi par la structure du jeton');
		$this->assertEquals(Equipment::HOURS, $eEquipment['lastMeter'], 'l\'unité du compteur est retenue, comme par l\'écran');

		$read = $this->callApi('GET', 'equipment/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

		// Un matériel ne se suit qu'une fois.
		$again = $this->callApi('POST', 'equipment', body: ['tool' => $eTool['id'], 'meter' => Equipment::HOURS, 'registration' => NULL]);

		$this->assertEquals(400, $again['status']);
		$this->assertEquals('Equipment::tool.followed', $again['body'][0]['code'] ?? NULL);

	}

	public function testCreateWithoutMeter(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		// `null` dit « sans compteur » : il sort ainsi, il doit pouvoir entrer ainsi.
		$response = $this->follow(NULL);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(NULL, $response['body']['meter']);

		$this->assertEquals(NULL, Equipment::model()->whereId($response['body']['id'])->getValue('meter'), 'le matériel est sans compteur en base');

	}

	public function testCreateMeterUnknown(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$response = $this->follow('miles');

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::meter.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateAwakens(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$eTool = $this->getToolFree();

		$created = $this->follow(Equipment::HOURS);

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $created['body']['id']]);

		// Suivre de nouveau un matériel réveille sa ligne : 200 et non 201, aucune ressource n'est née.
		$response = $this->callApi('POST', 'equipment', body: ['tool' => $eTool['id'], 'meter' => Equipment::HOURS, 'registration' => NULL]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($created['body']['id'], $response['body']['id'], 'le matériel réveillé garde son id');
		$this->assertEquals(Equipment::ACTIVE, $response['body']['status']);

		$this->assertEquals(1, Equipment::model()->whereTool($eTool)->count(), 'aucune seconde ligne n\'est écrite');

	}

	public function testCreateAwakensLocked(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$this->setClock(self::CLOCK);

		$eTool = $this->getToolFree();

		$created = $this->follow(Equipment::HOURS);

		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $created['body']['id'], 'date' => '2026-06-01', 'value' => 1200]);
		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $created['body']['id']]);

		// Le matériel porte un relevé en heures : il ne revient pas en kilomètres.
		$response = $this->callApi('POST', 'equipment', body: ['tool' => $eTool['id'], 'meter' => Equipment::KILOMETERS, 'registration' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::meter.locked', $response['body'][0]['code'] ?? NULL);

		$this->assertEquals(Equipment::INACTIVE, Equipment::model()->whereId($created['body']['id'])->getValue('status'), 'le matériel refusé reste endormi');

	}

	public function testCreateToolNew(): void {

		// « new » est le mot de l'autocomplétion de l'écran pour créer le matériel au même geste. L'API
		// le crée par sa propre ressource, `POST tool`, et le refus le dit (étape `newApi`).
		$before = \work\Tool::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('POST', 'equipment', body: ['tool' => 'new', 'toolName' => 'Matériel venu de l\'API', 'meter' => NULL, 'registration' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::tool.newApi', $response['body'][0]['code'] ?? NULL);
		$this->assertTrue(str_contains($response['body'][0]['message'] ?? '', 'POST tool'), 'le refus nomme la route qui crée un matériel');

		$this->assertEquals($before, \work\Tool::model()->whereStructure($this->eStructure)->count(), 'aucun matériel n\'est créé');

	}

	public function testCreateToolForeign(): void {

		$eToolForeign = \work\Tool::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(\work\Tool::ACTIVE)
			->whereRoutineName(NULL)
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertTrue($eToolForeign->notEmpty(), 'la base porte un matériel hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'equipment', body: ['tool' => $eToolForeign['id'], 'meter' => NULL, 'registration' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::tool.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateToolProduct(): void {

		// Un produit de traitement ou de fertilisation vit dans « Mon matériel » avec une routine : ce
		// n'est pas un matériel à entretenir.
		// L'API ne crée pas de matériel de routine, le décor se monte donc par le Lib.
		$eToolProduct = \work\ToolLib::getNewTool($this->eStructure, 'tray');
		// Sans build() : il vérifierait un nom et une contenance postés, et le cas ne poste rien, il
		// lui faut seulement une plaque de semis en base.
		$eToolProduct->merge(['name' => 'Plaque du test du carnet', 'routineValue' => ['value' => 150]]);
		\work\ToolLib::create($eToolProduct);

		$response = $this->callApi('POST', 'equipment', body: ['tool' => $eToolProduct['id'], 'meter' => NULL, 'registration' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::tool.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateWithoutTool(): void {

		$response = $this->callApi('POST', 'equipment', body: ['meter' => Equipment::HOURS, 'registration' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::tool.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateMeter(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$created = $this->follow(NULL);

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::KILOMETERS], get: ['id' => $created['body']['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Equipment::KILOMETERS, $response['body']['meter']);
		$this->assertEquals(Equipment::ACTIVE, $response['body']['status'], 'changer le compteur ne touche pas au suivi');

		$this->assertEquals(Equipment::KILOMETERS, Equipment::model()->whereId($created['body']['id'])->getValue('lastMeter'), 'l\'unité du compteur est retenue');

	}

	public function testRegistration(): void {

		$eTool = $this->getToolFree();

		if($eTool->empty()) {
			return;
		}

		$created = $this->callApi('POST', 'equipment', body: ['tool' => $eTool['id'], 'meter' => Equipment::KILOMETERS, 'registration' => 'ab-123-cd']);

		$this->assertEquals(201, $created['status']);
		$this->assertEquals('AB-123-CD', $created['body']['registration'], 'l\'immatriculation se donne à la création, rendue en capitales');

		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['registration'], 'registration' => 'EF-456-GH'], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('EF-456-GH', $response['body']['registration']);
		$this->assertEquals(Equipment::KILOMETERS, $response['body']['meter'], 'l\'immatriculation se modifie sans toucher au compteur');

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['registration'], 'registration' => str_repeat('A', 21)], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::registration.check', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['registration'], 'registration' => NULL], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(NULL, $response['body']['registration'], 'null l\'efface');

	}

	public function testUpdateMeterLocked(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$this->setClock(self::CLOCK);

		$created = $this->follow(Equipment::HOURS);
		$get = ['id' => $created['body']['id']];

		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $created['body']['id'], 'date' => '2026-06-01', 'value' => 1200]);

		// Un relevé en heures tient le compteur à cette unité.
		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::KILOMETERS], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::meter.locked', $response['body'][0]['code'] ?? NULL);

		// Couper le compteur reste ouvert, et il ne revient qu'à son unité.
		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => NULL], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(NULL, $response['body']['meter']);

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::KILOMETERS], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::meter.locked', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::HOURS], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Equipment::HOURS, $response['body']['meter']);

	}

	public function testUpdateUnfollow(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$created = $this->follow(Equipment::HOURS);
		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(Equipment::INACTIVE, $response['body']['status']);
		$this->assertEquals(Equipment::HOURS, $response['body']['meter'], 'le matériel garde son compteur pour son retour');

		// Un matériel qu'on ne suit plus ne se modifie plus : c'est `acceptUpdate()`, la seule garde
		// que l'API interroge. Il revient par `POST equipment`.
		$again = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => NULL], get: $get);

		$this->assertEquals(412, $again['status']);
		$this->assertEquals('accept_update', $again['body'][0]['code'] ?? NULL);

	}

	public function testUpdateActiveRefused(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$created = $this->follow(Equipment::HOURS);

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::ACTIVE], get: ['id' => $created['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Equipment::status.deleted', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateToolRefused(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$cTool = $this->getToolsFree();

		if($this->assertEquals(2, $cTool->count(), 'la structure '.static::STRUCTURE.' porte deux matériels que le carnet n\'a jamais suivis') === FALSE) {
			return;
		}

		$created = $this->follow(Equipment::HOURS);

		// Un suivi ne change pas de matériel : il porte ses relevés et son historique.
		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['tool'], 'tool' => $cTool->last()['id']], get: ['id' => $created['body']['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateForeign(): void {

		$eEquipmentForeign = $this->getEquipmentForeign();

		if($this->assertTrue($eEquipmentForeign->notEmpty(), 'la base porte un matériel suivi hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $eEquipmentForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testToolStockIgnored(): void {

		$tool = $this->createTool();

		$this->callApi('PUT', 'tool/{id}', body: ['properties' => ['stock'], 'stock' => 3], get: ['id' => $tool]);
		$this->callApi('POST', 'equipment', body: ['tool' => $tool, 'meter' => NULL, 'registration' => NULL]);

		// Un matériel suivi est un matériel unique : « Mon matériel » ne lui prend plus de stock.
		$response = $this->callApi('PUT', 'tool/{id}', body: ['properties' => ['stock'], 'stock' => 5], get: ['id' => $tool]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('PUT', 'tool/{id}', body: [
			'name' => 'Matériel du test du carnet',
			'action' => NULL,
			'stock' => 5,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL
		], get: ['id' => $tool]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(3, $response['body']['stock'], 'le stock d\'un corps complet est ignoré');

	}

	public function testToolDeleted(): void {

		$this->setClock(self::CLOCK);

		$tool = $this->createTool();

		$created = $this->callApi('POST', 'equipment', body: ['tool' => $tool, 'meter' => Equipment::HOURS, 'registration' => NULL]);
		$equipment = $created['body']['id'];

		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-01', 'value' => 1200]);
		$maintenance = $this->callApi('POST', 'maintenance', body: ['equipment' => $equipment, 'name' => 'Vidange', 'everyValue' => NULL, 'everyMonths' => 6, 'plannedValue' => NULL, 'plannedDate' => '2026-06-01']);

		$this->assertEquals(MaintenanceLib::OVERDUE, $maintenance['body']['due'], 'le décor porte un entretien en retard');

		// Supprimer de « Mon matériel » un matériel entré au carnet garde sa ligne, que les relevés et
		// l'historique nomment, comme pour un matériel cité par une intervention. Seul le carnet la
		// retient ici, le matériel venant d'être créé.
		$response = $this->callApi('DELETE', 'tool/{id}', get: ['id' => $tool]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(\work\Tool::DELETED, \work\Tool::model()->whereId($tool)->getValue('status'), 'le matériel est retiré, sa ligne reste');

		// Le carnet ne le suit plus : ni onglet, ni échéance, ni notification, toutes ses lectures ne
		// retenant que les matériels suivis.
		$read = $this->callApi('GET', 'equipment/{id}', get: ['id' => $equipment]);

		$this->assertEquals(Equipment::INACTIVE, $read['body']['status']);
		$this->assertFalse(EquipmentLib::getByStructure($this->eStructure)->offsetExists($equipment), 'le carnet ne montre plus le matériel');
		$this->assertEquals([], MaintenanceLib::getDueUntil('2026-12-31')->find(fn($eMaintenance) => $eMaintenance['id'] === $maintenance['body']['id'])->getIds(), 'le point du lundi ne lit plus son entretien');

		$read = $this->callApi('GET', 'maintenance/{id}', get: ['id' => $maintenance['body']['id']]);

		$this->assertEquals(NULL, $read['body']['due'], 'son entretien en retard n\'a plus d\'échéance');

		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'ses relevés sont gardés');

		// Et il ne se réveille pas : un matériel retiré n'est plus un matériel de « Mon matériel ».
		$again = $this->callApi('POST', 'equipment', body: ['tool' => $tool, 'meter' => Equipment::HOURS, 'registration' => NULL]);

		$this->assertEquals(400, $again['status']);
		$this->assertEquals('Equipment::tool.check', $again['body'][0]['code'] ?? NULL);
		$this->assertEquals(Equipment::INACTIVE, Equipment::model()->whereId($equipment)->getValue('status'), 'le matériel refusé reste endormi');

	}

	public function testToolDeletedOnceUnfollowed(): void {

		$tool = $this->createTool();

		$created = $this->callApi('POST', 'equipment', body: ['tool' => $tool, 'meter' => NULL, 'registration' => NULL]);

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $created['body']['id']]);

		// Un matériel qu'on ne suit plus garde son historique au carnet : sa ligne reste aussi.
		$response = $this->callApi('DELETE', 'tool/{id}', get: ['id' => $tool]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(\work\Tool::DELETED, \work\Tool::model()->whereId($tool)->getValue('status'));
		$this->assertEquals(1, Equipment::model()->whereId($created['body']['id'])->count(), 'la ligne du carnet est gardée');

	}

	public function testDeleteRouteAbsent(): void {

		if($this->requireTool() === FALSE) {
			return;
		}

		$created = $this->follow(Equipment::HOURS);

		// « Ne plus suivre » est un changement de statut : aucune route DELETE n'existe.
		$response = $this->callApi('DELETE', 'equipment/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(405, $response['status']);
		$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

	}

}
?>
