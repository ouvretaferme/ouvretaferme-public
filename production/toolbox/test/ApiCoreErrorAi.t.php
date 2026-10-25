<?php
namespace toolbox;

/**
 * Les refus du socle d'API (`ModuleApi`, `ApiRoute::run()`, `Module::cast()`), éprouvés sur cinq
 * ressources de deux packages : work\Tool et les quatre ressources du carnet d'entretien. Chaque refus
 * doit rendre son statut et un message lisible en français, sans avertissement PHP ni exception
 * traversante. Relevés le 2026-10-02 sur le carnet : un nombre non numérique enregistré à 0, un PUT
 * à `properties: []` qui levait une ModuleException, des refus `.missing` et des arguments de liste
 * au message null, des refus du cadriciel en anglais, une date en tableau qui émettait « Array to
 * string conversion ».
 *
 * Le test vit ici et non dans framework/ : son décor est une structure de fixture, et le cadriciel
 * ne dépend pas du package structure.
 *
 * Lancement : dev/test package=toolbox file=ApiCoreErrorAi
 */
class ApiCoreErrorAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new MaintenanceDoneModel();
	}

	/** @return array{tool: int, equipment: int, maintenance: int, reading: int} */
	private function createDecor(): array {

		$this->setClock('2026-06-15 10:00:00');

		$name = 'Socle API '.uniqid();

		$this->callApi('POST', 'tool', body: ['name' => $name, 'action' => NULL, 'stock' => NULL, 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL]);

		$tool = \work\Tool::model()
			->select(['id'])
			->whereStructure($this->eStructure)
			->whereName($name)
			->get()['id'];

		$equipment = $this->callApi('POST', 'equipment', body: ['tool' => $tool, 'meter' => Equipment::HOURS, 'registration' => NULL])['body']['id'];
		$maintenance = $this->callApi('POST', 'maintenance', body: ['equipment' => $equipment, 'name' => 'Vidange', 'everyValue' => NULL, 'everyMonths' => 6, 'plannedValue' => NULL, 'plannedDate' => '2026-09-01'])['body']['id'];
		$reading = $this->callApi('POST', 'equipmentReading', body: ['equipment' => $equipment, 'date' => '2026-06-10', 'value' => 120])['body']['id'];

		return ['tool' => $tool, 'equipment' => $equipment, 'maintenance' => $maintenance, 'reading' => $reading];

	}

	/**
	 * Appelle la route en relevant les avertissements PHP, qu'une réponse 400 juste ne montre pas.
	 *
	 * @return array{status: int, body: mixed, warnings: string[]}
	 */
	private function call(string $method, string $route, array $body = [], array $get = []): array {

		$warnings = [];

		set_error_handler(function(int $number, string $message, string $file, int $line) use (&$warnings) {
			$warnings[] = $message.' ('.$file.':'.$line.')';
			return TRUE;
		});

		try {
			$response = $this->callApi($method, $route, $body, $get);
		} finally {
			restore_error_handler();
		}

		return $response + ['warnings' => $warnings];

	}

	private function assertRefused(array $response, int $status, string $type, string $code, string $label): void {

		$this->assertEquals([], $response['warnings'], $label.', sans avertissement PHP');
		$this->assertEquals($status, $response['status'], $label.', statut');
		$this->assertEquals($type, $response['body'][0]['type'] ?? NULL, $label.', type');
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $label.', code');
		$this->assertTrue(is_string($response['body'][0]['message'] ?? NULL) and $response['body'][0]['message'] !== '', $label.', le message est rendu');

	}

	public function testNonNumericValueIsRefused(): void {

		$decor = $this->createDecor();

		$cases = [
			'tool.stock' => ['POST', 'tool', ['name' => 'Socle API stock', 'action' => NULL, 'stock' => 'abc', 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL], [], 'Tool::stock.check'],
			'tool.stock booléen' => ['POST', 'tool', ['name' => 'Socle API stock', 'action' => NULL, 'stock' => TRUE, 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL], [], 'Tool::stock.check'],
			'maintenance.everyMonths' => ['PUT', 'maintenance/{id}', ['properties' => ['everyValue', 'everyMonths'], 'everyValue' => NULL, 'everyMonths' => 'abc'], ['id' => $decor['maintenance']], 'Maintenance::everyMonths.check'],
			// Une valeur décimale pour un entier se refuse, la conversion la tronquerait en un entier que le contrôle du schéma laisserait passer
			'maintenance.everyMonths décimal' => ['PUT', 'maintenance/{id}', ['properties' => ['everyValue', 'everyMonths'], 'everyValue' => NULL, 'everyMonths' => '2.5'], ['id' => $decor['maintenance']], 'Maintenance::everyMonths.check'],
			'maintenance.everyMonths décimal en nombre' => ['PUT', 'maintenance/{id}', ['properties' => ['everyValue', 'everyMonths'], 'everyValue' => NULL, 'everyMonths' => 2.5], ['id' => $decor['maintenance']], 'Maintenance::everyMonths.check'],
			'tool.stock écrit en décimal' => ['POST', 'tool', ['name' => 'Socle API stock', 'action' => NULL, 'stock' => '3.0', 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL], [], 'Tool::stock.check'],
			'maintenance.plannedValue' => ['PUT', 'maintenance/{id}', ['properties' => ['plannedValue', 'plannedDate'], 'plannedValue' => [500], 'plannedDate' => NULL], ['id' => $decor['maintenance']], 'Maintenance::plannedValue.check'],
			'maintenanceDone.cost' => ['POST', 'maintenanceDone', ['equipment' => $decor['equipment'], 'maintenance' => NULL, 'name' => 'Pneu', 'date' => '2026-06-10', 'value' => NULL, 'cost' => 'abc', 'comment' => NULL], [], 'MaintenanceDone::cost.check'],
		];

		foreach($cases as $label => [$method, $route, $body, $get, $code]) {
			$this->assertRefused($this->call($method, $route, $body, $get), 400, 'resource_error', $code, $label);
		}

		$this->assertEquals(0, \work\Tool::model()->whereStructure($this->eStructure)->whereName('Socle API stock')->count(), 'aucun matériel n\'est créé avec un stock à 0');
		$this->assertEquals(6, Maintenance::model()->select(['everyMonths'])->whereId($decor['maintenance'])->get()['everyMonths'], 'la périodicité refusée n\'a pas été tronquée en base');
		$this->assertEquals(0, MaintenanceDone::model()->whereEquipment($decor['equipment'])->count(), 'aucun entretien n\'est enregistré à 0 €');

		$response = $this->call('POST', 'tool', ['name' => 'Socle API stock', 'action' => NULL, 'stock' => '12', 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL]);
		$this->assertEquals(201, $response['status'], 'un nombre envoyé en chaîne reste accepté');
		$this->assertEquals(12, \work\Tool::model()->select(['stock'])->whereStructure($this->eStructure)->whereName('Socle API stock')->get()['stock'] ?? NULL, 'et il est enregistré');

		$response = $this->call('POST', 'tool', ['name' => 'Socle API stock décimal', 'action' => NULL, 'stock' => 4.0, 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL]);
		$this->assertEquals(201, $response['status'], 'un nombre entier écrit en décimal dans le JSON reste accepté');
		$this->assertEquals(4, \work\Tool::model()->select(['stock'])->whereStructure($this->eStructure)->whereName('Socle API stock décimal')->get()['stock'] ?? NULL, 'et il est enregistré');

	}

	/**
	 * Un identifiant qui ne peut désigner aucune ligne est un refus, pas une absence de valeur. La
	 * branche `element` de Module::cast() rendait un élément vide pour « abc », pour un booléen et
	 * pour un objet dont l'id n'est pas un nombre : une colonne nullable l'enregistrait alors en
	 * NULL, et une colonne non nullable refusait par sa nullabilité.
	 */
	public function testNonNumericElementIsRefused(): void {

		$decor = $this->createDecor();

		$cases = [
			'chaîne non numérique' => 'abc',
			'booléen' => TRUE,
			'objet sans id numérique' => ['id' => 'abc'],
		];

		foreach($cases as $label => $value) {

			$this->assertRefused(
				$this->call('POST', 'maintenanceDone', ['equipment' => $decor['equipment'], 'maintenance' => $value, 'name' => 'Pneu', 'date' => '2026-06-10', 'value' => NULL, 'cost' => NULL, 'comment' => NULL]),
				400, 'resource_error', 'MaintenanceDone::maintenance.check', 'maintenanceDone.maintenance nullable, '.$label
			);

			$this->assertRefused(
				$this->call('POST', 'maintenanceDone', ['equipment' => $value, 'maintenance' => NULL, 'name' => 'Pneu', 'date' => '2026-06-10', 'value' => NULL, 'cost' => NULL, 'comment' => NULL]),
				400, 'resource_error', 'MaintenanceDone::equipment.check', 'maintenanceDone.equipment non nullable, '.$label
			);

		}

		$this->assertEquals(0, MaintenanceDone::model()->whereEquipment($decor['equipment'])->count(), 'aucun entretien n\'est enregistré sans son entretien prévu');

		$response = $this->call('POST', 'maintenanceDone', ['equipment' => (string)$decor['equipment'], 'maintenance' => (string)$decor['maintenance'], 'name' => 'Pneu', 'date' => '2026-06-10', 'value' => NULL, 'cost' => NULL, 'comment' => NULL]);

		$this->assertEquals(201, $response['status'], 'un identifiant envoyé en chaîne reste accepté');
		$this->assertEquals($decor['maintenance'], MaintenanceDone::model()->select(['maintenance'])->whereId($response['body']['id'] ?? 0)->get()['maintenance']['id'] ?? NULL, 'et il désigne bien cette ligne');

	}

	public function testArrayDateIsRefusedWithoutWarning(): void {

		$decor = $this->createDecor();

		$this->assertRefused(
			$this->call('POST', 'maintenanceDone', ['equipment' => $decor['equipment'], 'maintenance' => NULL, 'name' => 'Pneu', 'date' => ['2026-06-10'], 'value' => NULL, 'cost' => NULL, 'comment' => NULL]),
			400, 'resource_error', 'MaintenanceDone::date.check', 'maintenanceDone.date'
		);

		$this->assertRefused(
			$this->call('PUT', 'maintenance/{id}', ['properties' => ['plannedValue', 'plannedDate'], 'plannedValue' => NULL, 'plannedDate' => ['2026-09-01']], ['id' => $decor['maintenance']]),
			400, 'resource_error', 'Maintenance::plannedDate.check', 'maintenance.plannedDate'
		);

	}

	/**
	 * Un tableau ou un objet envoyé sur un enum ou sur un booléen est un refus, pas une valeur. La
	 * branche `enum` de Module::cast() le passait à `(string)`, ce qui émettait « Array to string
	 * conversion » au milieu de la réponse, et `isCastable('bool', …)` rendait TRUE pour tout, donc
	 * un tableau non vide devenait TRUE et un tableau vide FALSE.
	 */
	public function testInvalidEnumAndBooleanAreRefused(): void {

		$decor = $this->createDecor();

		$singular = 'u'.substr(uniqid(), -8);

		$unit = fn(mixed $type, mixed $approximate): array => ['singular' => $singular, 'plural' => $singular.'s', 'short' => 'bte', 'type' => $type, 'approximate' => $approximate];

		$cases = [
			'unit.type tableau' => ['POST', 'unit', $unit(['integer'], FALSE), [], 'Unit::type.check'],
			'unit.type tableau vide' => ['POST', 'unit', $unit([], FALSE), [], 'Unit::type.check'],
			'unit.approximate tableau' => ['POST', 'unit', $unit(\product\Unit::INTEGER, ['oui']), [], 'Unit::approximate.check'],
			'unit.approximate tableau vide' => ['POST', 'unit', $unit(\product\Unit::INTEGER, []), [], 'Unit::approximate.check'],
			'equipment.meter tableau' => ['PUT', 'equipment/{id}', ['properties' => ['meter'], 'meter' => [Equipment::HOURS]], ['id' => $decor['equipment']], 'Equipment::meter.check'],
		];

		foreach($cases as $label => [$method, $route, $body, $get, $code]) {
			$this->assertRefused($this->call($method, $route, $body, $get), 400, 'resource_error', $code, $label);
		}

		$this->assertEquals(0, \product\Unit::model()->whereStructure($this->eStructure)->whereSingular($singular)->count(), 'aucune unité n\'est créée avec un type ou une approximation inventés');
		$this->assertEquals(Equipment::HOURS, Equipment::model()->select(['meter'])->whereId($decor['equipment'])->get()['meter'], 'le compteur de l\'équipement n\'a pas bougé');

		$response = $this->call('POST', 'unit', $unit(\product\Unit::INTEGER, '1'));
		$this->assertEquals(201, $response['status'], 'un booléen envoyé en chaîne reste accepté');
		$this->assertEquals(TRUE, \product\Unit::model()->select(['approximate'])->whereStructure($this->eStructure)->whereSingular($singular)->get()['approximate'] ?? NULL, 'et il est enregistré à vrai');

	}

	/**
	 * Aucune route n'expose de propriété `set` en écriture au 2026-10-02 (mesuré sur les
	 * getPropertiesCreate('api') des 96 ressources), donc le refus s'éprouve sur le cast lui-même :
	 * `new Set('abc')` rendait un set vide, que le filtre accepte puisque ne rien cocher est légal.
	 */
	public function testInvalidSetIsRefusedByCast(): void {

		$mCommunity = \structure\Community::model();

		foreach(['chaîne non numérique' => 'abc', 'booléen' => TRUE, 'élément de tableau non numérique' => ['abc']] as $label => $value) {

			$casted = $value;
			$this->assertEquals(FALSE, $mCommunity->cast('shareProduction', $casted), 'Community::shareProduction, '.$label.' est refusé par le cast');

		}

		$casted = [\structure\Community::STAFF];
		$this->assertTrue($mCommunity->cast('shareProduction', $casted) !== FALSE, 'un tableau de valeurs du set reste accepté');
		$this->assertTrue($mCommunity->check('shareProduction', $casted), 'et le filtre le valide');

		$casted = '0';
		$this->assertTrue($mCommunity->cast('shareProduction', $casted) !== FALSE, 'la valeur brute que rend la base reste acceptée');

	}

	public function testEmptyPropertiesListIsRefused(): void {

		$decor = $this->createDecor();

		$cases = [
			'tool' => ['tool/{id}', $decor['tool']],
			'equipment' => ['equipment/{id}', $decor['equipment']],
			'maintenance' => ['maintenance/{id}', $decor['maintenance']],
			'equipmentReading' => ['equipmentReading/{id}', $decor['reading']],
		];

		foreach($cases as $label => [$route, $id]) {
			$this->assertRefused($this->call('PUT', $route, ['properties' => []], ['id' => $id]), 400, 'api_error', 'properties_empty', $label);
		}

	}

	public function testMissingPropertyHasMessage(): void {

		$decor = $this->createDecor();

		$cases = [
			'tool' => ['tool', ['name' => 'Socle API sans stock', 'action' => NULL, 'comment' => NULL, 'routineName' => NULL, 'routineValue' => NULL], 'Tool::stock.missing'],
			'maintenance' => ['maintenance', ['equipment' => $decor['equipment'], 'name' => 'Courroie', 'everyMonths' => 6, 'plannedValue' => NULL, 'plannedDate' => '2026-09-01'], 'Maintenance::everyValue.missing'],
			'maintenanceDone' => ['maintenanceDone', ['equipment' => $decor['equipment'], 'maintenance' => NULL, 'name' => 'Pneu', 'value' => NULL, 'cost' => NULL, 'comment' => NULL], 'MaintenanceDone::date.missing'],
			'equipmentReading' => ['equipmentReading', ['equipment' => $decor['equipment'], 'date' => '2026-06-11'], 'EquipmentReading::value.missing'],
		];

		foreach($cases as $label => [$route, $body, $code]) {

			$response = $this->call('POST', $route, $body);

			$this->assertRefused($response, 400, 'resource_error', $code, $label);
			$this->assertTrue(str_contains($response['body'][0]['message'] ?? '', explode('.', explode('::', $code)[1])[0]), $label.', le message nomme la propriété');

		}

	}

	public function testInvalidListArgumentHasMessage(): void {

		$cases = [
			'maintenanceDoneList.dateFrom' => ['maintenanceDoneList', ['dateFrom' => 'pas une date'], 'dateFrom.check'],
			'equipmentReadingList.dateTo' => ['equipmentReadingList', ['dateTo' => 'pas une date'], 'dateTo.check'],
			'equipmentList.limit' => ['equipmentList', ['limit' => 'abc'], 'limit.check'],
			'toolList.limit' => ['toolList', ['limit' => 100000], 'limit.check'],
		];

		foreach($cases as $label => [$route, $get, $code]) {
			$this->assertRefused($this->call('GET', $route, get: $get), 400, 'resource_error', $code, $label);
		}

	}

	public function testCoreRefusalIsTranslated(): void {

		$decor = $this->createDecor();

		$cases = [
			'property_unknown' => [$this->call('PUT', 'maintenance/{id}', ['properties' => ['inconnue']], ['id' => $decor['maintenance']]), 400, \api\AlertUi::getError('Api::property_unknown', ['property' => 'inconnue'])],
			'not_found' => [$this->call('GET', 'tool/{id}', get: ['id' => 999999999]), 404, \api\AlertUi::getError('Api::not_found', [])],
			'unknown_route' => [$this->call('GET', 'routeInconnue'), 404, \api\AlertUi::getError('Api::unknown_route', ['route' => 'routeInconnue'])],
		];

		foreach($cases as $code => [$response, $status, $message]) {

			$this->assertRefused($response, $status, 'api_error', $code, $code);
			$this->assertEquals($message, $response['body'][0]['message'], $code.', le message est celui du socle');

		}

	}

	/** Un code du socle réemployé par une ressource garde le message que la ressource a écrit. */
	public function testResourceMessageIsKept(): void {

		$exception = new \ApiException('Bed not found', 'not_found', 404);

		$this->assertEquals('Bed not found', $exception->getOutputMessage());
		$this->assertEquals(\api\AlertUi::getError('Api::not_found', []), new \ApiException('Not found', 'not_found', 404, [])->getOutputMessage());

	}

}
?>
