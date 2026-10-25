<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\MaintenanceDone
 * (production/toolbox/api/MaintenanceDone_0_1beta.a.php).
 *
 * Le décor est un matériel de la structure de référence que le carnet n'a jamais suivi, mis au
 * carnet par l'API au début de chaque cas : son compteur et son historique partent de rien. L'heure
 * est figée, le jour d'un entretien fait étant jugé contre aujourd'hui (`MaintenanceDone::date.future`).
 *
 * Trois comportements surprennent un client et sont éprouvés ici : une ligne qui nomme un entretien
 * le replanifie ou le clôt, un compteur donné devient le relevé du jour, et aucun des noms que le
 * formulaire de l'écran emploie (`meterUpdate`, `meterConfirmed`) ne passe depuis le corps.
 *
 * Lancement : dev/test package=toolbox file=MaintenanceDoneApi
 */
class MaintenanceDoneApiTest extends \dev\ApiTest {

	const CLOCK = '2026-06-15 10:00:00';

	const FIELDS = ['id', 'equipment', 'maintenance', 'name', 'date', 'value', 'cost', 'comment'];

	protected function getTransactionModel(): \ModuleModel {
		return new MaintenanceDoneModel();
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

	private function unfollow(int $equipment): void {

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['status'], 'status' => Equipment::INACTIVE], get: ['id' => $equipment]);

	}

	private function read(int $equipment, string $date, int $value): void {

		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => $date, 'value' => $value]);

	}

	/** Planifie un entretien et rend son id : « Une seule fois » tant que le cas ne donne pas de fréquence. */
	private function plan(int $equipment, array $values): int {

		$response = $this->callApi('POST', 'maintenance', body: $values + [
			'equipment' => $equipment,
			'name' => 'Vidange',
			'everyValue' => NULL,
			'everyMonths' => NULL,
			'plannedValue' => NULL,
			'plannedDate' => NULL
		]);

		return $response['body']['id'];

	}

	private function getMaintenance(int $maintenance): array {

		return $this->callApi('GET', 'maintenance/{id}', get: ['id' => $maintenance])['body'];

	}

	/** Le corps d'une ligne, que chaque cas complète : une création porte toujours ces sept propriétés. */
	private function done(int $equipment, array $values): array {

		return $this->callApi('POST', 'maintenanceDone', body: $values + [
			'equipment' => $equipment,
			'maintenance' => NULL,
			'name' => NULL,
			'date' => '2026-06-10',
			'value' => NULL,
			'cost' => NULL,
			'comment' => NULL
		]);

	}

	private function put(int $id, array $properties, array $values): array {

		return $this->callApi('PUT', 'maintenanceDone/{id}', body: ['properties' => $properties] + $values, get: ['id' => $id]);

	}

	/** Une ligne d'une autre structure : la ressource ne doit jamais la rendre. */
	private function getMaintenanceDoneForeign(): MaintenanceDone {

		return MaintenanceDone::model()
			->select(['id', 'structure', 'date'])
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

		$maintenance = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-09-01']);

		$this->done($equipment, ['name' => 'Courroie', 'date' => '2026-06-01']);
		$this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-08']);
		$this->done($equipment, ['name' => 'Pneu', 'date' => '2026-06-15']);

		$expected = MaintenanceDone::model()
			->whereStructure($this->eStructure)
			->count();

		$response = $this->callApi('GET', 'maintenanceDoneList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, $response['body']['count'], 'la liste compte les lignes de la structure');
		$this->assertEquals(self::FIELDS, array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs, sans la photo');

		$ids = MaintenanceDone::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucune ligne hors de la structure');

		$response = $this->callApi('GET', 'maintenanceDoneList', get: ['equipment' => $equipment]);

		$this->assertEquals(['2026-06-01', '2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'le filtre par matériel rend son historique');

		$response = $this->callApi('GET', 'maintenanceDoneList', get: ['maintenance' => $maintenance]);

		$this->assertEquals(['2026-06-08'], array_column($response['body']['list'], 'date'), 'le filtre par entretien rend les fois où il a été fait');

		$response = $this->callApi('GET', 'maintenanceDoneList', get: ['equipment' => $equipment, 'dateFrom' => '2026-06-08']);

		$this->assertEquals(['2026-06-08', '2026-06-15'], array_column($response['body']['list'], 'date'), 'dateFrom est une borne incluse');

		$response = $this->callApi('GET', 'maintenanceDoneList', get: ['equipment' => $equipment, 'dateTo' => '2026-06-08']);

		$this->assertEquals(['2026-06-01', '2026-06-08'], array_column($response['body']['list'], 'date'), 'dateTo est une borne incluse');

	}

	public function testReadForeign(): void {

		$eMaintenanceDoneForeign = $this->getMaintenanceDoneForeign();

		if($this->assertTrue($eMaintenanceDoneForeign->notEmpty(), 'la base porte une ligne d\'historique hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'maintenanceDone/{id}', get: ['id' => $eMaintenanceDoneForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateIntervention(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$response = $this->done($equipment, ['name' => 'Courroie', 'cost' => 42.5, 'comment' => 'Garage du bourg']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(self::FIELDS, array_keys($response['body']));
		$this->assertEquals($equipment, $response['body']['equipment']);
		$this->assertEquals(NULL, $response['body']['maintenance']);
		$this->assertEquals('Courroie', $response['body']['name']);
		$this->assertEquals('2026-06-10', $response['body']['date']);
		$this->assertEquals(NULL, $response['body']['value'], 'un matériel sans compteur n\'a pas de compteur à lire');
		$this->assertEquals(42.5, $response['body']['cost']);
		$this->assertEquals('Garage du bourg', $response['body']['comment']);

		$eMaintenanceDone = MaintenanceDone::model()
			->select(['structure', 'photo'])
			->whereId($response['body']['id'])
			->get();

		$this->assertEquals($this->eStructure['id'], $eMaintenanceDone['structure']['id'], 'la ligne est posée sur la structure du jeton');
		$this->assertEquals(NULL, $eMaintenanceDone['photo']);

		$read = $this->callApi('GET', 'maintenanceDone/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($response['body'], $read['body'], 'la lecture rend ce que la création a rendu');

		// Sans entretien, la ligne n'a que son libellé pour dire ce qui a été fait.
		$response = $this->done($equipment, []);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::name.required', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateRegular(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		$maintenance = $this->plan($equipment, ['everyValue' => 250, 'everyMonths' => 6, 'plannedValue' => 1250, 'plannedDate' => '2026-07-01']);

		// Le libellé est celui de l'entretien : ce que le corps porte dans `name` est oublié.
		$response = $this->done($equipment, ['maintenance' => $maintenance, 'name' => 'Autre chose']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals($maintenance, $response['body']['maintenance']);
		$this->assertEquals(NULL, $response['body']['name'], 'une ligne qui nomme un entretien n\'a pas de libellé propre');
		$this->assertEquals(1200, $response['body']['value'], 'le compteur est lu dans le dernier relevé d\'avant ce jour');

		$read = $this->getMaintenance($maintenance);

		$this->assertEquals('2026-12-10', $read['plannedDate'], 'l\'échéance à la date repart du jour de l\'entretien fait');
		$this->assertEquals(1450, $read['plannedValue'], 'l\'échéance au compteur repart du compteur de ce jour');
		$this->assertEquals(Maintenance::ACTIVE, $read['status'], 'un entretien régulier reste à faire');

		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'un compteur lu dans les relevés n\'en écrit pas un nouveau');

	}

	public function testCreateOnce(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$maintenance = $this->plan($equipment, ['name' => 'Contrôle technique', 'plannedDate' => '2026-10-05']);

		$response = $this->done($equipment, ['maintenance' => $maintenance]);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(Maintenance::DONE, $this->getMaintenance($maintenance)['status'], 'un entretien « Une seule fois » est clos par la ligne qui le nomme');

		// Clos, il ne se note plus : l'écran ne montre plus « Fait ».
		$again = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-12']);

		$this->assertEquals(400, $again['status']);
		$this->assertEquals('MaintenanceDone::maintenance.owned', $again['body'][0]['code'] ?? NULL);

		$deleted = $this->callApi('DELETE', 'maintenanceDone/{id}', get: ['id' => $response['body']['id']]);

		$this->assertEquals(200, $deleted['status']);
		$this->assertEquals(Maintenance::ACTIVE, $this->getMaintenance($maintenance)['status'], 'la ligne supprimée, l\'entretien est de nouveau à faire');

	}

	public function testCreateValue(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		// Un compteur donné devient le relevé du jour de l'entretien.
		$response = $this->done($equipment, ['name' => 'Courroie', 'value' => 1230]);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(1230, $response['body']['value']);
		$this->assertEquals(self::FIELDS, array_keys($response['body']), 'confirmed ne se relit pas');

		$this->assertEquals(1230, EquipmentReading::model()->whereEquipment($equipment)->whereDate('2026-06-10')->getValue('value'), 'le compteur donné est le relevé de ce jour');

		// Il suit la règle d'un relevé : un compteur ne recule pas.
		$response = $this->done($equipment, ['name' => 'Pneu', 'date' => '2026-06-12', 'value' => 1000]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::value.backwards', $response['body'][0]['code'] ?? NULL);

		// Le nom que pose le formulaire de l'écran n'ouvre rien : venu du corps, il est écarté.
		$response = $this->done($equipment, ['name' => 'Pneu', 'date' => '2026-06-12', 'value' => 1000, 'meterConfirmed' => '1']);

		$this->assertEquals(400, $response['status'], 'meterConfirmed dans le corps ne confirme pas');

		foreach(['true', '1', 1] as $confirmed) {

			$response = $this->done($equipment, ['name' => 'Pneu', 'date' => '2026-06-12', 'value' => 1000, 'confirmed' => $confirmed]);

			$this->assertEquals(400, $response['status'], 'confirmed à '.var_export($confirmed, TRUE).' ne confirme pas');

		}

		$this->assertEquals(1, MaintenanceDone::model()->whereEquipment($equipment)->count(), 'aucune ligne refusée n\'est écrite');
		$this->assertEquals(2, EquipmentReading::model()->whereEquipment($equipment)->count(), 'ni aucun relevé');

		$response = $this->done($equipment, ['name' => 'Pneu', 'date' => '2026-06-12', 'value' => 1000, 'confirmed' => TRUE]);

		$this->assertEquals(201, $response['status'], 'confirmed à true confirme');
		$this->assertEquals(1000, $response['body']['value']);

		// « Mettre à jour le compteur » est une question de l'écran : venue du corps, elle n'exige rien,
		// et le compteur se lit dans les relevés.
		$response = $this->done($equipment, ['name' => 'Graissage', 'date' => '2026-06-14', 'meterUpdate' => '1']);

		$this->assertEquals(201, $response['status'], 'meterUpdate dans le corps ne rend pas le compteur obligatoire');
		$this->assertEquals(1000, $response['body']['value']);

	}

	/**
	 * Un entretien dû au compteur seul repart du compteur du jour où il est fait. Sur un matériel sans
	 * aucun relevé, ce compteur ne se lit nulle part : la ligne doit le porter
	 * (`MaintenanceDoneLib::isValueRequired()`). Dû aussi à la date, l'entretien garde celle-ci et
	 * n'exige rien ; un seul relevé, d'avant ou d'après, suffit à lire le compteur.
	 */
	public function testCreateValueRequired(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$maintenance = $this->plan($equipment, ['everyValue' => 400, 'plannedValue' => 400]);
		$maintenanceDated = $this->plan($equipment, ['name' => 'Graissage', 'everyValue' => 400, 'everyMonths' => 6, 'plannedValue' => 400, 'plannedDate' => '2026-07-01']);
		$maintenanceOnce = $this->plan($equipment, ['name' => 'Rodage', 'plannedValue' => 50]);

		$response = $this->done($equipment, ['maintenance' => $maintenance]);

		$this->assertEquals(400, $response['status'], 'sans aucun relevé, un entretien dû au compteur seul ne se note pas sans compteur');
		$this->assertEquals('MaintenanceDone::value.required', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(0, MaintenanceDone::model()->whereEquipment($equipment)->count(), 'la ligne refusée n\'est pas écrite');
		$this->assertEquals(400, $this->getMaintenance($maintenance)['plannedValue'], 'l\'échéance de l\'entretien n\'a pas bougé');

		$response = $this->done($equipment, ['maintenance' => $maintenanceDated]);

		$this->assertEquals(201, $response['status'], 'dû aussi à la date, l\'entretien se note sans compteur');
		$this->assertEquals('2026-12-10', $this->getMaintenance($maintenanceDated)['plannedDate']);

		$response = $this->done($equipment, ['maintenance' => $maintenanceOnce]);

		$this->assertEquals(201, $response['status'], 'un entretien « Une seule fois » n\'a pas d\'échéance à faire repartir');

		$response = $this->done($equipment, ['name' => 'Courroie']);

		$this->assertEquals(201, $response['status'], 'une intervention n\'a pas d\'échéance');

		$response = $this->done($equipment, ['maintenance' => $maintenance, 'value' => 380]);

		$this->assertEquals(201, $response['status'], 'avec son compteur, la ligne passe');
		$this->assertEquals(780, $this->getMaintenance($maintenance)['plannedValue'], 'et l\'échéance repart de lui');

		// Le compteur donné est devenu un relevé : la règle ne joue plus sur ce matériel.
		$response = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-12']);

		$this->assertEquals(201, $response['status'], 'dès qu\'un relevé existe, le compteur se lit');
		$this->assertEquals(380, $response['body']['value']);

		// Un relevé d'après le jour de l'entretien suffit aussi : l'échéance part de lui (`follow()`).
		$equipmentAfter = $this->follow();
		$this->read($equipmentAfter, '2026-06-12', 900);
		$maintenanceAfter = $this->plan($equipmentAfter, ['everyValue' => 400, 'plannedValue' => 400]);

		$response = $this->done($equipmentAfter, ['maintenance' => $maintenanceAfter]);

		$this->assertEquals(201, $response['status'], 'un relevé d\'après ce jour suffit à faire repartir l\'échéance');
		$this->assertEquals(NULL, $response['body']['value']);
		$this->assertEquals(1300, $this->getMaintenance($maintenanceAfter)['plannedValue']);

		// Compteur retiré, l'échéance au compteur n'est plus due : rien n'est exigé.
		$equipmentOff = $this->follow();
		$maintenanceOff = $this->plan($equipmentOff, ['everyValue' => 400, 'plannedValue' => 400]);
		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => NULL], get: ['id' => $equipmentOff]);

		$response = $this->done($equipmentOff, ['maintenance' => $maintenanceOff]);

		$this->assertEquals(201, $response['status'], 'sur un matériel dont le compteur est retiré, rien n\'est exigé');

	}

	public function testCreateValueWithoutMeter(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$response = $this->done($equipment, ['name' => 'Courroie', 'value' => 1230]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::value.meter', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Ce qui n'est pas un nombre entier n'est pas un compteur : le cast du cadriciel en ferait 0, que
	 * `"confirmed": true` laisserait poser en relevé du jour sur un tracteur à 1 200 h.
	 */
	public function testCreateValueNotANumber(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		foreach(['abc', '12 h', 12.5, TRUE] as $value) {

			foreach([FALSE, TRUE] as $confirmed) {

				$response = $this->done($equipment, ['name' => 'Courroie', 'value' => $value, 'confirmed' => $confirmed]);

				$this->assertEquals(400, $response['status'], var_export($value, TRUE));
				$this->assertEquals(['MaintenanceDone::value.number'], array_column($response['body'], 'code'), var_export($value, TRUE));
				$this->assertTrue(($response['body'][0]['message'] ?? NULL) !== NULL, 'le refus dit pourquoi');

			}

		}

		$this->assertEquals(0, MaintenanceDone::model()->whereEquipment($equipment)->count(), 'aucune ligne');
		$this->assertEquals(1, EquipmentReading::model()->whereEquipment($equipment)->count(), 'aucun relevé posé');

		// Les chiffres d'un formulaire passent, comme un nombre.
		$response = $this->done($equipment, ['name' => 'Courroie', 'value' => '1230']);

		$this->assertEquals(201, $response['status']);
		$this->assertEquals(1230, $response['body']['value']);

	}

	/** Chaque refus du schéma porte un message : un code sans message ne s'affiche pas à l'écran. */
	public function testCreateOutOfRange(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$cases = [
			'MaintenanceDone::cost.check' => ['cost' => 1000000000],
			'MaintenanceDone::comment.check' => ['comment' => str_repeat('a', 70000)],
			'MaintenanceDone::name.check' => ['name' => str_repeat('a', 61)]
		];

		foreach($cases as $code => $values) {

			$response = $this->done($equipment, $values + ['name' => 'Courroie']);

			$this->assertEquals(400, $response['status'], $code);
			$this->assertEquals([$code], array_column($response['body'], 'code'));
			$this->assertTrue(($response['body'][0]['message'] ?? NULL) !== NULL, $code.' dit pourquoi');

		}

	}

	public function testCreateFuture(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$response = $this->done($equipment, ['name' => 'Courroie', 'date' => '2026-06-16']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::date.future', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateEquipmentRefused(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		// Un matériel qu'on ne suit plus ne reçoit plus rien. Le compteur donné n'ajoute pas d'erreur :
		// le matériel refusé la porte déjà.
		$this->unfollow($equipment);

		$response = $this->done($equipment, ['name' => 'Courroie', 'value' => 1230]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['MaintenanceDone::equipment.owned'], array_column($response['body'], 'code'));

		$eEquipmentForeign = Equipment::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(Equipment::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		if($this->assertTrue($eEquipmentForeign->notEmpty(), 'la base porte un matériel suivi hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->done($eEquipmentForeign['id'], ['name' => 'Courroie']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['MaintenanceDone::equipment.owned'], array_column($response['body'], 'code'));

	}

	public function testCreateMaintenanceRefused(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();
		$equipmentOther = $this->follow();

		if($equipment === NULL or $equipmentOther === NULL) {
			return;
		}

		// L'entretien d'un autre matériel de la structure.
		$maintenanceOther = $this->plan($equipmentOther, ['plannedDate' => '2026-09-01']);

		$response = $this->done($equipment, ['maintenance' => $maintenanceOther]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['MaintenanceDone::maintenance.owned'], array_column($response['body'], 'code'), 'un entretien refusé ne réclame pas en plus un libellé');

		// Un entretien retiré.
		$maintenance = $this->plan($equipment, ['plannedDate' => '2026-09-01']);

		$this->callApi('PUT', 'maintenance/{id}', body: ['properties' => ['status'], 'status' => Maintenance::DELETED], get: ['id' => $maintenance]);

		$response = $this->done($equipment, ['maintenance' => $maintenance]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::maintenance.owned', $response['body'][0]['code'] ?? NULL);

		// Un entretien qui n'existe pas est refusé par le contrôle du schéma.
		$response = $this->done($equipment, ['maintenance' => 0]);

		$this->assertEquals(400, $response['status']);

		$this->assertEquals(0, MaintenanceDone::model()->whereEquipment('IN', [$equipment, $equipmentOther])->count(), 'aucune ligne refusée n\'est écrite');

	}

	public function testCreateMissing(): void {

		$response = $this->callApi('POST', 'maintenanceDone', body: ['maintenance' => NULL, 'name' => 'Courroie', 'date' => '2026-06-10', 'value' => NULL, 'cost' => NULL, 'comment' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::equipment.missing', $response['body'][0]['code'] ?? NULL);

		// Le compteur absent du corps est refusé comme les autres : `.missing` n'interrompt pas la
		// conversion, qui lit donc la clé après l'avoir refusée (`convertProperties()`).
		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$response = $this->callApi('POST', 'maintenanceDone', body: ['equipment' => $equipment, 'maintenance' => NULL, 'name' => 'Courroie', 'date' => '2026-06-10', 'cost' => NULL, 'comment' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['MaintenanceDone::value.missing'], array_column($response['body'], 'code'));

	}

	public function testUpdate(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		$maintenance = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-07-01']);

		$created = $this->done($equipment, ['maintenance' => $maintenance]);
		$id = $created['body']['id'];

		$response = $this->put($id, ['cost', 'comment'], ['cost' => 80, 'comment' => 'Huile 15W40']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(80.0, $response['body']['cost']);
		$this->assertEquals('Huile 15W40', $response['body']['comment']);
		$this->assertEquals('2026-06-10', $response['body']['date'], 'ce qui n\'est pas nommé ne change pas');

		// La date d'une ligne fait repartir l'échéance de son entretien.
		$response = $this->put($id, ['date'], ['date' => '2026-06-05']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('2026-12-05', $this->getMaintenance($maintenance)['plannedDate'], 'l\'échéance suit la date corrigée');
		$this->assertEquals(1200, $response['body']['value'], 'le compteur connu le 5 est encore le relevé du 1er');

		$response = $this->put($id, ['date'], ['date' => '2026-06-16']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::date.future', $response['body'][0]['code'] ?? NULL);

		// Un corps sans `properties` porte les quatre propriétés d'une modification.
		$response = $this->callApi('PUT', 'maintenanceDone/{id}', body: ['name' => NULL, 'date' => '2026-06-06', 'cost' => NULL, 'comment' => NULL], get: ['id' => $id]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(NULL, $response['body']['cost']);
		$this->assertEquals('2026-06-06', $response['body']['date']);

		// Une ligne ne change ni de matériel, ni d'entretien, ni de compteur, et l'API ne porte pas la photo.
		foreach(['equipment' => $equipment, 'maintenance' => NULL, 'value' => 1300, 'photo' => NULL] as $property => $value) {

			$response = $this->put($id, [$property], [$property => $value]);

			$this->assertEquals(400, $response['status'], $property.' ne se modifie pas');
			$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

		}

	}

	/**
	 * Une ligne déplacée relit son compteur dans les relevés à sa nouvelle date, et l'échéance de son
	 * entretien repart de lui. Le relevé qu'elle avait posé à la création reste à son jour.
	 */
	public function testUpdateDateReadsValue(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1200);

		$maintenance = $this->plan($equipment, ['everyValue' => 100, 'plannedValue' => 1250]);

		$created = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-10', 'value' => 1230]);
		$id = $created['body']['id'];

		$this->assertEquals(1230, $created['body']['value']);
		$this->assertEquals(1330, $this->getMaintenance($maintenance)['plannedValue']);

		// La même date, renvoyée : rien n'est relu, même quand le relevé du jour a été corrigé depuis,
		// et une échéance corrigée sur l'entretien reste ce qu'elle est.
		$this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-10', 'value' => 1240]);

		$corrected = $this->callApi('PUT', 'maintenance/{id}', body: ['properties' => ['plannedValue', 'plannedDate'], 'plannedValue' => 1500, 'plannedDate' => NULL], get: ['id' => $maintenance]);

		$this->assertEquals(200, $corrected['status']);

		$response = $this->put($id, ['date', 'cost'], ['date' => '2026-06-10', 'cost' => 80]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1230, $response['body']['value'], 'une date inchangée ne relit pas le compteur');
		$this->assertEquals(1500, $this->getMaintenance($maintenance)['plannedValue'], 'ni ne fait repartir l\'échéance');

		// Reculée au 8, la ligne prend le relevé d'avant, celui du 1er.
		$response = $this->put($id, ['date'], ['date' => '2026-06-08']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(1200, $response['body']['value'], 'le compteur connu le 8 est le relevé du 1er');
		$this->assertEquals(1300, $this->getMaintenance($maintenance)['plannedValue'], 'l\'échéance repart du compteur relu');

		$eEquipmentReading = EquipmentReading::model()
			->select(['value'])
			->whereEquipment($equipment)
			->whereDate('2026-06-10')
			->get();

		$this->assertEquals(1240, $eEquipmentReading['value'] ?? NULL, 'le relevé de l\'ancien jour reste');

		// Un relevé posé le 8 après coup ne change pas la ligne, qui ne relit qu'en changeant de date.
		$this->read($equipment, '2026-06-08', 1225);

		$read = $this->callApi('GET', 'maintenanceDone/{id}', get: ['id' => $id]);

		$this->assertEquals(1200, $read['body']['value'], 'un relevé posé après coup ne rattrape pas la ligne');
		$this->assertEquals(1300, $this->getMaintenance($maintenance)['plannedValue']);

		// Le relevé posé d'abord, la ligne déplacée ensuite : elle le prend.
		$this->read($equipment, '2026-06-09', 1228);

		$response = $this->put($id, ['date'], ['date' => '2026-06-09']);

		$this->assertEquals(1228, $response['body']['value'], 'le relevé du jour est pris quand il existe');
		$this->assertEquals(1328, $this->getMaintenance($maintenance)['plannedValue']);

		// Avant tout relevé, la ligne n'a plus de compteur, et l'échéance part du premier relevé d'après.
		$response = $this->put($id, ['date'], ['date' => '2026-05-20']);

		$this->assertEquals(NULL, $response['body']['value'], 'aucun relevé ne précède le 20 mai');
		$this->assertEquals(1300, $this->getMaintenance($maintenance)['plannedValue'], 'le premier relevé d\'après est celui du 1er juin');

	}

	/** Un matériel sans compteur n'a rien à relire : la ligne déplacée garde `value` à `null`. */
	public function testUpdateDateWithoutMeter(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->done($equipment, ['name' => 'Courroie']);

		$response = $this->put($created['body']['id'], ['date'], ['date' => '2026-06-08']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('2026-06-08', $response['body']['date']);
		$this->assertEquals(NULL, $response['body']['value']);

	}

	/**
	 * Compteur coupé après coup : les relevés ne se lisent plus, la ligne déplacée garde le compteur
	 * qu'elle porte, et l'échéance au compteur de son entretien revient avec le compteur.
	 */
	public function testUpdateDateWithMeterOff(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1125);

		$maintenance = $this->plan($equipment, ['everyValue' => 400, 'plannedValue' => 1300]);

		$created = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-05']);

		$this->assertEquals(1125, $created['body']['value']);
		$this->assertEquals(1525, $this->getMaintenance($maintenance)['plannedValue']);

		$cut = $this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => NULL], get: ['id' => $equipment]);

		$this->assertEquals(200, $cut['status']);

		$response = $this->put($created['body']['id'], ['date'], ['date' => '2026-06-10']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('2026-06-10', $response['body']['date']);
		$this->assertEquals(1125, $response['body']['value'], 'compteur coupé, la ligne déplacée garde le sien');

		$this->callApi('PUT', 'equipment/{id}', body: ['properties' => ['meter'], 'meter' => Equipment::HOURS], get: ['id' => $equipment]);

		$this->assertEquals(1525, $this->getMaintenance($maintenance)['plannedValue'], 'l\'échéance au compteur revient avec le compteur');

	}

	/**
	 * Une ligne notée, déplacée ou retirée avant la dernière fois n'entre qu'à l'historique : l'échéance,
	 * ici corrigée sur l'entretien, ne bouge que lorsque la dernière fois change.
	 */
	public function testNotLastKeepsTheDue(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$this->read($equipment, '2026-06-01', 1000);
		$this->read($equipment, '2026-06-10', 1090);

		$maintenance = $this->plan($equipment, ['everyValue' => 100, 'plannedValue' => 1050]);

		$old = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-05'])['body']['id'];
		$this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-10']);

		$this->assertEquals(1190, $this->getMaintenance($maintenance)['plannedValue']);

		$this->callApi('PUT', 'maintenance/{id}', body: ['properties' => ['plannedValue', 'plannedDate'], 'plannedValue' => 2000, 'plannedDate' => NULL], get: ['id' => $maintenance]);

		$older = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-02']);

		$this->assertEquals(201, $older['status']);
		$this->assertEquals(2000, $this->getMaintenance($maintenance)['plannedValue'], 'une ligne notée avant la dernière fois ne fait pas repartir l\'échéance');

		$response = $this->put($old, ['date'], ['date' => '2026-06-07']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(2000, $this->getMaintenance($maintenance)['plannedValue'], 'ni une ligne déplacée qui reste avant la dernière fois');

		$response = $this->callApi('DELETE', 'maintenanceDone/{id}', get: ['id' => $older['body']['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(2000, $this->getMaintenance($maintenance)['plannedValue'], 'ni une ligne retirée avant la dernière fois');

		// Déplacée après la dernière fois, la ligne la devient : l'échéance repart d'elle.
		$this->read($equipment, '2026-06-12', 1095);

		$response = $this->put($old, ['date'], ['date' => '2026-06-12']);

		$this->assertEquals(1095, $response['body']['value']);
		$this->assertEquals(1195, $this->getMaintenance($maintenance)['plannedValue'], 'la ligne devenue la dernière fois fait repartir l\'échéance');

		// Retirée, la dernière fois redevient celle du 10.
		$this->callApi('DELETE', 'maintenanceDone/{id}', get: ['id' => $old]);

		$this->assertEquals(1190, $this->getMaintenance($maintenance)['plannedValue'], 'la dernière fois retirée, l\'échéance repart de la fois d\'avant');

	}

	public function testUpdateInterventionName(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->done($equipment, ['name' => 'Courroie']);

		$response = $this->put($created['body']['id'], ['name'], ['name' => 'Courroie d\'alternateur']);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('Courroie d\'alternateur', $response['body']['name']);

		$response = $this->put($created['body']['id'], ['name'], ['name' => NULL]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('MaintenanceDone::name.required', $response['body'][0]['code'] ?? NULL);

	}

	public function testUpdateAndDeleteOnUnfollowed(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow(NULL);

		if($equipment === NULL) {
			return;
		}

		$created = $this->done($equipment, ['name' => 'Courroie']);
		$get = ['id' => $created['body']['id']];

		$this->unfollow($equipment);

		// L'historique d'un matériel qu'on ne suit plus se lit et ne s'écrit plus.
		$response = $this->callApi('GET', 'maintenanceDone/{id}', get: $get);

		$this->assertEquals(200, $response['status']);

		$response = $this->callApi('PUT', 'maintenanceDone/{id}', body: ['properties' => ['cost'], 'cost' => 12], get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_update', $response['body'][0]['code'] ?? NULL);

		$response = $this->callApi('DELETE', 'maintenanceDone/{id}', get: $get);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_delete', $response['body'][0]['code'] ?? NULL);

		$this->assertEquals(1, MaintenanceDone::model()->whereId($created['body']['id'])->count(), 'la ligne est restée');

	}

	public function testDelete(): void {

		$this->setClock(self::CLOCK);

		$equipment = $this->follow();

		if($equipment === NULL) {
			return;
		}

		$maintenance = $this->plan($equipment, ['everyMonths' => 6, 'plannedDate' => '2026-07-01']);

		$this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-01']);
		$created = $this->done($equipment, ['maintenance' => $maintenance, 'date' => '2026-06-10']);

		$this->assertEquals('2026-12-10', $this->getMaintenance($maintenance)['plannedDate']);

		$get = ['id' => $created['body']['id']];

		$response = $this->callApi('DELETE', 'maintenanceDone/{id}', get: $get);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals('2026-12-01', $this->getMaintenance($maintenance)['plannedDate'], 'l\'échéance repart de la fois d\'avant');

		$again = $this->callApi('GET', 'maintenanceDone/{id}', get: $get);

		$this->assertEquals(404, $again['status']);

	}

	public function testWriteForeign(): void {

		$eMaintenanceDoneForeign = $this->getMaintenanceDoneForeign();

		if($this->assertTrue($eMaintenanceDoneForeign->notEmpty(), 'la base porte une ligne d\'historique hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$get = ['id' => $eMaintenanceDoneForeign['id']];

		$response = $this->callApi('PUT', 'maintenanceDone/{id}', body: ['properties' => ['date'], 'date' => $eMaintenanceDoneForeign['date']], get: $get);

		$this->assertEquals(404, $response['status']);

		$response = $this->callApi('DELETE', 'maintenanceDone/{id}', get: $get);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals(1, MaintenanceDone::model()->whereId($eMaintenanceDoneForeign['id'])->count(), 'la ligne d\'une autre structure est toujours là');

	}

}
?>
