<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\EquipmentReading
 * (production/toolbox/api/EquipmentReading_0_1beta.a.php).
 *
 * Le décor est un matériel de la structure de référence que le carnet n'a jamais suivi, mis au
 * carnet par l'API au début de chaque cas : son compteur part de rien, et l'ordre des relevés ne
 * dépend d'aucun historique de la base. L'heure est figée, les jours des relevés étant jugés contre
 * aujourd'hui (`EquipmentReading::date.future`).
 *
 * Deux comportements surprennent un client et sont éprouvés ici : une création **remplace** le relevé
 * du jour, et un relevé hors de l'ordre du compteur ne passe qu'avec `confirmed` à `true`, jamais
 * avec le nom que le formulaire de l'écran emploie.
 *
 * Lancement : dev/test package=toolbox file=EquipmentReadingApi
 */
class EquipmentReadingApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	protected function getTransactionModel(): \ModuleModel {
		return new EquipmentReadingModel();
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

	private function read(int $equipment, string $date, int $value, array $more = []): array {

		return $this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => $date, 'value' => $value] + $more);

	}

	private function unfollow(int $equipment): void {

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $equipment]);

	}

	/** Un relevé d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getEquipmentReadingForeign(): EquipmentReading {

		return EquipmentReading::model()
			->select(['id', 'structure', 'value'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function testList(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);
		$this->read($equipment, '2026-06-08', 1230);
		$this->read($equipment, '2026-06-15', 1260);

		$expected = EquipmentReading::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'equipmentReadingList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les relevés de la structure');
		$this->assertEquals(['id', 'equipment', 'date', 'value', 'meter'], array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');

		$ids = EquipmentReading::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucun relevé hors de la structure');

		$response = $this->callApi('GET', 'equipmentReadingList', get: ['equipment' => $equipment]);

		$this->assertEquals(['2026-06-01', '2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'le filtre par matériel rend ses relevés');

		$response = $this->callApi('GET', 'equipmentReadingList', get: ['equipment' => $equipment, 'dateFrom' => '2026-06-08']);

		$this->assertEquals(['2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'dateFrom est une borne incluse');

		$response = $this->callApi('GET', 'equipmentReadingList', get: ['equipment' => $equipment, 'dateTo' => '2026-06-08']);

		$this->assertEquals(['2026-06-01', '2026-06-08'], array_column($response['body']['list'], 'date'), 'dateTo est une borne incluse');

	}

	public function testReadForeign(): void {

		$eEquipmentReadingForeign = $this->getEquipmentReadingForeign();

		if($this->assertTrue($eEquipmentReadingForeign->notEmpty(), 'la base porte un relevé hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'equipmentReading/{id}', get: ['id' => $eEquipmentReadingForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreate(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(Equipment::KILOMETERS);

		if($equipment === NULL) {
			return;
		}

		$response = $this->read($equipment, '2026-06-10', 45200);

		$this->assertEquals(200, $response['status'], 'un relevé pose ou remplace le jour : 200, jamais 201');
		$this->assertEquals($equipment, $response['body']['equipment']);
		$this->assertEquals('2026-06-10', $response['body']['date']);
		$this->assertEquals(45200, $response['body']['value']);
		$this->assertEquals(Equipment::KILOMETERS, $response['body']['meter'], 'le relevé prend l\'unité du matériel');

		$read = $this->callApi('GET', 'equipmentReading/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

		$eEquipmentReading = EquipmentReading::model()
			->select(['structure'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eEquipmentReading['structure']['id'], 'le relevé est posé sur la structure du jeton');

	}

	public function testCreateReplacesTheDay(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$first = $this->read($equipment, '2026-06-10', 1200);
		$second = $this->read($equipment, '2026-06-10', 1215);

		$this->assertEquals(200, $second['status']);
		$this->assertEquals($first['body']['id'], $second['body']['id'], 'le second relevé du jour est le même relevé');
		$this->assertEquals(1215, $second['body']['value'], 'il remplace la valeur au lieu de s\'y ajouter');

		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'le jour ne porte qu\'une ligne');

		// Le relevé du jour ne se compare pas à lui-même : le corriger à la baisse n'est pas un recul.
		$third = $this->read($equipment, '2026-06-10', 1190);

		$this->assertEquals(200, $third['status']);
		$this->assertEquals(1190, $third['body']['value']);

	}

	/**
	 * Par l'API comme à l'écran, le premier relevé fait partir de lui l'entretien régulier qu'il mettrait
	 * en retard (`MaintenanceLib::planFromFirstReading()`), et le relevé suivant ne fait rien repartir.
	 */
	public function testFirstReadingStartsOverdueMaintenances(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$maintenance = $this->callApi('POST', 'maintenance', body: ['equipment' => $equipment, 'name' => 'Vidange', 'everyValue' => 400, 'everyMonths' => NULL, 'plannedValue' => 400, 'plannedDate' => NULL]);

		$this->assertEquals(201, $maintenance['status']);

		$getPlannedValue = fn() => $this->callApi('GET', 'maintenance/{id}', get: ['id' => $maintenance['body']['id']])['body']['plannedValue'];

		$this->read($equipment, '2026-06-10', 500);

		$this->assertEquals(900, $getPlannedValue(), 'en retard au premier relevé, l\'échéance repart du relevé');

		$this->read($equipment, '2026-06-12', 950);

		$this->assertEquals(900, $getPlannedValue(), 'le relevé suivant ne la fait pas repartir');

	}

	public function testCreateFuture(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$response = $this->read($equipment, '2026-06-16', 1200);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::date.future', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateBackwards(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		$response = $this->read($equipment, '2026-06-10', 1000);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::value.backwards', $response['body'][0]['code'] ?? NULL);

		// Le nom que pose le formulaire de l'écran n'ouvre rien : venu du corps, il est écarté.
		$response = $this->read($equipment, '2026-06-10', 1000, ['meterConfirmed' => '1']);

		$this->assertEquals(400, $response['status'], 'meterConfirmed dans le corps ne confirme pas');
		$this->assertEquals('EquipmentReading::value.backwards', $response['body'][0]['code'] ?? NULL);

		// Ni ce qui ressemble à un oui sans en être un.
		foreach(['true', '1', 1] as $confirmed) {

			$response = $this->read($equipment, '2026-06-10', 1000, ['confirmed' => $confirmed]);

			$this->assertEquals(400, $response['status'], 'confirmed à '.var_export($confirmed, TRUE).' ne confirme pas');

		}

		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'aucun relevé refusé n\'est écrit');

		$response = $this->read($equipment, '2026-06-10', 1000, ['confirmed' => TRUE]);

		$this->assertEquals(200, $response['status'], 'confirmed à true confirme');
		$this->assertEquals(1000, $response['body']['value']);
		$this->assertEquals(['id', 'equipment', 'date', 'value', 'meter'], array_keys($response['body']), 'confirmed ne se relit pas');

	}

	public function testCreateAhead(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-10', 1500);

		$response = $this->read($equipment, '2026-06-01', 1600);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::value.ahead', $response['body'][0]['code'] ?? NULL);

		$response = $this->read($equipment, '2026-06-01', 1600, ['meterConfirmed' => '1']);

		$this->assertEquals(400, $response['status'], 'meterConfirmed dans le corps ne confirme pas');

		$response = $this->read($equipment, '2026-06-01', 1600, ['confirmed' => TRUE]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1600, $response['body']['value']);

	}

	/**
	 * Ce qui n'est pas un nombre entier n'est pas un compteur : le cast du cadriciel en ferait 0, que
	 * `"confirmed": true` laisserait poser sous un relevé à 1 200 h.
	 */
	public function testCreateNotANumber(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		foreach(['abc', '12 h', 12.5, TRUE] as $value) {

			foreach([FALSE, TRUE] as $confirmed) {

				$response = $this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-10', 'value' => $value, 'confirmed' => $confirmed]);

				$this->assertEquals(400, $response['status'], var_export($value, TRUE));
				$this->assertEquals(['EquipmentReading::value.number'], array_column($response['body'], 'code'), var_export($value, TRUE));
				$this->assertTrue(($response['body'][0]['message'] ?? NULL) !== NULL, 'le refus dit pourquoi');

			}

		}

		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'aucun relevé posé');

	}

	public function testCreateEquipmentRefused(): void {

		$this->setClock(self::CLOCK);

		// Sans compteur, un matériel n'a rien à relever.
		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$response = $this->read($equipment, '2026-06-10', 1200);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::equipment.owned', $response['body'][0]['code'] ?? NULL);

		// Un matériel qu'on ne suit plus ne reçoit plus de relevé, même avec un compteur.
		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::HOURS], get: ['id' => $equipment]);
		$this->unfollow($equipment);

		$response = $this->read($equipment, '2026-06-10', 1200);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::equipment.owned', $response['body'][0]['code'] ?? NULL);

		$eEquipmentForeign = Equipment::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(Equipment::ACTIVE)
			->where('meter IS NOT NULL')
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertTrue($eEquipmentForeign->notEmpty(), 'la base porte un matériel à compteur suivi hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->read($eEquipmentForeign['id'], '2026-06-10', 1200);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::equipment.owned', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateMissing(): void {

		$response = $this->callApi('POST', 'equipmentReading', body: ['date' => '2026-06-10', 'value' => 1200]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::equipment.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdate(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);
		$created = $this->read($equipment, '2026-06-08', 1230);
		$this->read($equipment, '2026-06-15', 1260);

		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1240], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1240, $response['body']['value']);
		$this->assertEquals('2026-06-08', $response['body']['date'], 'le relevé garde son jour');

		// La règle du compteur vaut aussi pour une correction, des deux côtés.
		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1100], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('EquipmentReading::value.backwards', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1300, 'meterConfirmed' => '1'], get: $get);

		$this->assertEquals(400, $response['status'], 'meterConfirmed dans le corps ne confirme pas une correction');
		$this->assertEquals('EquipmentReading::value.ahead', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1300, 'confirmed' => TRUE], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1300, $response['body']['value']);

		// Un relevé ne change ni de jour ni de matériel.
		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['properties' => ['date'], 'date' => '2026-06-09'], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateAndDeleteOnUnfollowed(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$created = $this->read($equipment, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$this->unfollow($equipment);

		// L'historique d'un matériel qu'on ne suit plus se lit et ne s'écrit plus.
		$response = $this->callApi('GET', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1240], get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_update', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('DELETE', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_delete', $response['body'][0]['code'] ?? NULL);

		$this->assertEquals(1230, EquipmentReading::model()->whereId($created['body']['id'])->getValue('value'), 'le relevé est resté tel quel');

	}

	public function testUpdateOnMeterCut(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$created = $this->read($equipment, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => NULL], get: ['id' => $equipment]);

		// Compteur coupé : le relevé garde son unité, ne se corrige plus, et peut encore se supprimer.
		$response = $this->callApi('GET', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(Equipment::HOURS, $response['body']['meter'], 'le relevé garde l\'unité du jour où il a été posé');

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => 1240], get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_update', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('DELETE', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);

	}

	public function testDelete(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$created = $this->read($equipment, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('DELETE', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, EquipmentReading::model()->whereId($created['body']['id'])->count(), 'le relevé supprimé ne se relit plus');

		$again = $this->callApi('GET', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(404, $again['status']);

	}

	public function testWriteForeign(): void {

		$eEquipmentReadingForeign = $this->getEquipmentReadingForeign();

		if($this->assertTrue($eEquipmentReadingForeign->notEmpty(), 'la base porte un relevé hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$get = ['id' => $eEquipmentReadingForeign['id']];

		$response = $this->callApi('PUT', 'equipmentReading/{id}', body: ['value' => $eEquipmentReadingForeign['value']], get: $get);

		$this->assertEquals(404, $response['status']);

		$response = $this->callApi('DELETE', 'equipmentReading/{id}', get: $get);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals(1, EquipmentReading::model()->whereId($eEquipmentReadingForeign['id'])->count(), 'le relevé d\'une autre structure est toujours là');

	}

}
?>
