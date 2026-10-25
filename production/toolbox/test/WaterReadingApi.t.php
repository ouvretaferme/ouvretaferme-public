<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\WaterReading
 * (production/toolbox/api/WaterReading_0_1beta.a.php).
 *
 * Chaque cas crée son compteur par l'API : ses relevés partent de rien, et rien ne dépend d'un
 * historique de la base. L'heure est figée, le jour d'un relevé étant jugé contre aujourd'hui
 * (`WaterReading::date.future`).
 *
 * Deux comportements surprennent un client et sont éprouvés ici : une création **remplace** le relevé
 * du jour, et un relevé plus bas que le précédent est accepté, parce qu'un compteur d'eau fait le
 * tour.
 *
 * Lancement : dev/test package=toolbox file=WaterReadingApi
 */
class WaterReadingApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	protected function getTransactionModel(): \ModuleModel {
		return new WaterReadingModel();
	}

	/** Crée un compteur et rend son id. */
	private function createMeter(string $name, int $digits = 5): int {

		return $this->callApi('POST', 'waterMeter', body: ['name' => $name, 'digits' => $digits])['body']['id'];

	}

	private function read(int $meter, string $date, int|float|string $value): array {

		return $this->callApi('POST', 'waterReading', body: ['meter' => $meter, 'date' => $date, 'value' => $value]);

	}

	private function deleteMeter(int $meter): void {

		$this->callApi('PUT', 'waterMeter/{id}', body: ['properties' => ['status'], 'status' => WaterMeter::INACTIVE], get: ['id' => $meter]);

	}

	/** Un relevé d'une autre structure, sur son compteur : la ressource ne doit jamais les rendre. */
	private function getWaterReadingForeign(): WaterReading {

		$eStructureForeign = \structure\StructureLib::getById(\structure\FixtureAiLib::F2);

		// Hors build() : il vérifierait le nom et le nombre de chiffres, posés ici à des valeurs que le
		// schéma accepte, et aucun appel de l'API n'écrit sur une autre structure que celle du jeton.
		$eWaterMeter = new WaterMeter([
			'structure' => $eStructureForeign,
			'name' => 'Compteur API hors structure',
			'digits' => 5
		]);

		WaterMeterLib::create($eWaterMeter);

		// Hors build() pour la même raison : le compteur vient d'être créé sur cette structure, le jour
		// est passé et la valeur tient dans cinq chiffres, ce que les étapes `owned`, `future` et
		// `digits` auraient vérifié.
		$eWaterReading = new WaterReading([
			'structure' => $eStructureForeign,
			'meter' => $eWaterMeter,
			'date' => '2026-06-01',
			'value' => 1200.0
		]);

		WaterReadingLib::create($eWaterReading);

		return $eWaterReading;

	}

	public function testList(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Forage API');

		$this->read($meter, '2026-06-01', 1200);
		$this->read($meter, '2026-06-08', 1230.5);
		$this->read($meter, '2026-06-15', 1260);

		$eWaterReadingForeign = $this->getWaterReadingForeign();

		$expected = WaterReading::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'waterReadingList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les relevés de la structure');
		$this->assertEquals(['id', 'meter', 'date', 'value', 'changed', 'changedValue'], array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');
		$this->assertFalse(in_array($eWaterReadingForeign['id'], array_column($response['body']['list'], 'id')), 'la liste ne rend pas le relevé d\'une autre structure');

		$response = $this->callApi('GET', 'waterReadingList', get: ['meter' => $meter]);

		$this->assertEquals(['2026-06-01', '2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'le filtre par compteur rend ses relevés');

		$response = $this->callApi('GET', 'waterReadingList', get: ['meter' => $meter, 'dateFrom' => '2026-06-08']);

		$this->assertEquals(['2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'dateFrom est une borne incluse');

		$response = $this->callApi('GET', 'waterReadingList', get: ['meter' => $meter, 'dateTo' => '2026-06-08']);

		$this->assertEquals(['2026-06-01', '2026-06-08'], array_column($response['body']['list'], 'date'), 'dateTo est une borne incluse');

		// Le compteur d'une autre structure ne rend rien, même nommé.
		$response = $this->callApi('GET', 'waterReadingList', get: ['meter' => $eWaterReadingForeign['meter']['id']]);

		$this->assertEquals([], $response['body']['list'] ?? NULL, 'le filtre sur le compteur d\'une autre structure ne rend aucun relevé');

	}

	public function testReadForeign(): void {

		$response = $this->callApi('GET', 'waterReading/{id}', get: ['id' => $this->getWaterReadingForeign()['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreate(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		$response = $this->read($meter, '2026-06-10', 1204.5);

		$this->assertEquals(200, $response['status'], 'un relevé pose ou remplace le jour : 200, jamais 201');
		$this->assertEquals($meter, $response['body']['meter']);
		$this->assertEquals('2026-06-10', $response['body']['date']);
		$this->assertEquals(1204.5, $response['body']['value'], 'le relevé se garde au dixième de m³');

		$read = $this->callApi('GET', 'waterReading/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

		$eWaterReading = WaterReading::model()
			->select(['structure'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eWaterReading['structure']['id'], 'le relevé est posé sur la structure du jeton');

	}

	public function testCreateReplacesTheDay(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		$first = $this->read($meter, '2026-06-10', 1200);
		$second = $this->read($meter, '2026-06-10', 1215);

		$this->assertEquals(200, $second['status']);
		$this->assertEquals($first['body']['id'], $second['body']['id'], 'le second relevé du jour est le même relevé');
		$this->assertEquals(1215.0, $second['body']['value'], 'il remplace la valeur au lieu de s\'y ajouter');

		$this->assertEquals(1, WaterReading::model()->whereMeter($meter)->count(), 'le jour ne porte qu\'une ligne');

	}

	/**
	 * Un compteur d'eau fait le tour : le relevé plus bas que le précédent s'enregistre sans
	 * confirmation, à l'inverse du compteur d'un matériel. Seul le nombre de chiffres le borne.
	 */
	public function testCreateLowerIsATurn(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Réseau API', 4);

		$this->read($meter, '2026-06-01', 9987);

		$response = $this->read($meter, '2026-06-10', 14);

		$this->assertEquals(200, $response['status'], 'un relevé plus bas que le précédent est accepté');
		$this->assertEquals(14.0, $response['body']['value']);

		$response = $this->read($meter, '2026-06-12', 10000);

		$this->assertEquals(400, $response['status'], 'un compteur à 4 chiffres n\'affiche pas 10 000');
		$this->assertEquals('WaterReading::value.digits', $response['body'][0]['code'] ?? NULL);

		$response = $this->read($meter, '2026-06-12', 9999.9);

		$this->assertEquals(200, $response['status'], 'et affiche encore 9 999,9');

	}

	public function testCreateFuture(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		$response = $this->read($meter, '2026-06-16', 1200);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::date.future', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateNotANumber(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		// Le cast du cadriciel rendrait 0 pour ce qui n'est pas un nombre, et 0 est un relevé.
		$response = $this->read($meter, '2026-06-10', 'abc');

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::value.number', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(0, WaterReading::model()->whereMeter($meter)->count(), 'aucun relevé à zéro n\'est écrit');

		$response = $this->read($meter, '2026-06-10', -5);

		$this->assertEquals(400, $response['status'], 'un compteur n\'affiche pas de valeur négative');
		$this->assertEquals('WaterReading::value.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateOnMeterForeignOrDeleted(): void {

		$this->setClock(self::CLOCK);

		$eWaterReadingForeign = $this->getWaterReadingForeign();

		$response = $this->read($eWaterReadingForeign['meter']['id'], '2026-06-10', 1300);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::meter.owned', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(1, WaterReading::model()->whereMeter($eWaterReadingForeign['meter'])->count(), 'le compteur d\'une autre structure n\'a reçu aucun relevé');

		// Un compteur supprimé garde ses relevés, mais n'en reçoit plus.
		$meter = $this->createMeter('Puits API');
		$this->deleteMeter($meter);

		$response = $this->read($meter, '2026-06-10', 1300);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::meter.owned', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateMissing(): void {

		$response = $this->callApi('POST', 'waterReading', body: ['date' => '2026-06-10', 'value' => 1200]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::meter.missing', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdate(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Réseau API', 4);

		$created = $this->read($meter, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['value' => 1240.5], get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1240.5, $response['body']['value']);
		$this->assertEquals('2026-06-08', $response['body']['date'], 'le relevé garde son jour');
		$this->assertEquals($meter, $response['body']['meter'], 'et son compteur');

		// Le nombre de chiffres du compteur borne aussi une correction.
		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['value' => 12400], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WaterReading::value.digits', $response['body'][0]['code'] ?? NULL);

		// Un relevé ne change ni de jour ni de compteur.
		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['properties' => ['date'], 'date' => '2026-06-09'], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Un changement de compteur se lit, et ne se déclare pas par l'API : `changed` et `changedValue`
	 * sont en lecture seule, la pose du nouveau compteur se corrige comme tout relevé, et supprimer le
	 * relevé retire le changement.
	 */
	public function testChangeIsReadOnly(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Réseau API', 4);

		$created = $this->read($meter, '2026-06-01', 9990);

		$this->assertEquals(FALSE, $created['body']['changed']);
		$this->assertEquals(NULL, $created['body']['changedValue']);

		// Sans `build()` ni appel d'API : le changement se déclare dans l'écran, dont `WaterReadingTest`
		// éprouve les refus, et le test veut exactement cette dépose, cette pose et ces cinq chiffres.
		$eWaterReadingChange = new WaterReading([
			'structure' => $this->eStructure,
			'meter' => new WaterMeter(['id' => $meter, 'digits' => 5]),
			'date' => '2026-06-08',
			'changedValue' => 9995.0,
			'value' => 0.0
		]);

		WaterReadingLib::createChange($eWaterReadingChange);

		$get = ['id' => $eWaterReadingChange['id']];

		$response = $this->callApi('GET', 'waterReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(TRUE, $response['body']['changed']);
		$this->assertEquals(9995.0, $response['body']['changedValue'], 'ce que l\'ancien compteur affichait à sa dépose');
		$this->assertEquals(0.0, $response['body']['value'], 'et le nouveau à sa pose');

		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['properties' => ['changedValue'], 'changedValue' => 9996], get: $get);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL, 'la dépose ne se corrige pas par l\'API');

		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['value' => 12000], get: $get);

		$this->assertEquals(200, $response['status'], 'la pose se borne par les cinq chiffres du nouveau compteur');
		$this->assertEquals(TRUE, $response['body']['changed']);
		$this->assertEquals(9995.0, $response['body']['changedValue']);

		$this->assertEquals(400, $this->read($meter, '2026-06-05', 12000)['status'], 'un relevé d\'avant le changement garde les quatre chiffres de l\'ancien');
		$this->assertEquals(200, $this->read($meter, '2026-06-10', 12040)['status']);

		$response = $this->callApi('DELETE', 'waterReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(5, $this->callApi('GET', 'waterMeter/{id}', get: ['id' => $meter])['body']['digits'], 'le changement retiré, le compteur garde le plus grand nombre de chiffres');

	}

	public function testUpdateAndDeleteOnDeletedMeter(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		$created = $this->read($meter, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$this->deleteMeter($meter);

		// Les relevés d'un compteur supprimé se lisent et ne s'écrivent plus.
		$response = $this->callApi('GET', 'waterReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);

		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['value' => 1240], get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_update', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('DELETE', 'waterReading/{id}', get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_delete', $response['body'][0]['code'] ?? NULL);

		$this->assertEquals(1230.0, WaterReading::model()->whereId($created['body']['id'])->getValue('value'), 'le relevé est resté tel quel');

	}

	public function testDelete(): void {

		$this->setClock(self::CLOCK);

		$meter = $this->createMeter('Puits API');

		$created = $this->read($meter, '2026-06-08', 1230);
		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('DELETE', 'waterReading/{id}', get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, WaterReading::model()->whereId($created['body']['id'])->count(), 'le relevé supprimé ne se relit plus');

		$again = $this->callApi('GET', 'waterReading/{id}', get: $get);

		$this->assertEquals(404, $again['status']);

	}

	public function testWriteForeign(): void {

		$eWaterReadingForeign = $this->getWaterReadingForeign();
		$get = ['id' => $eWaterReadingForeign['id']];

		$response = $this->callApi('PUT', 'waterReading/{id}', body: ['value' => 1300], get: $get);

		$this->assertEquals(404, $response['status']);

		$response = $this->callApi('DELETE', 'waterReading/{id}', get: $get);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals(1200.0, WaterReading::model()->whereId($eWaterReadingForeign['id'])->getValue('value'), 'le relevé d\'une autre structure est toujours là, à sa valeur');

	}

}
?>
