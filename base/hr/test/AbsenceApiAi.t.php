<?php
namespace hr;

/**
 * Test bout-en-bout de l'API publique hr\Absence (base/hr/api/Absence_0_1beta.a.php).
 *
 * Lancement : dev/test package=hr file=AbsenceApiAi
 */
class AbsenceApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new AbsenceModel();
	}

	/**
	 * La RH allumée dans la transaction du test. Le rollback ne défait pas la mémoïsation de `conf()`, qui
	 * garde ce qu'elle a lu dans la transaction : elle s'oublie à l'entrée et à la sortie.
	 */
	public function setUp(): void {

		parent::setUp();

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

	}

	public function tearDown(): void {

		parent::tearDown();

		\structure\FeatureLib::forget($this->eStructure);

	}

	/**
	 * Un membre de la structure de référence, présent dans l'équipe : une période qui court aujourd'hui lui est
	 * posée quand aucune ne court depuis `Absence::PRESENCE_MONTHS` mois.
	 */
	protected function getUserMember(): \user\User {

		$eUser = \structure\MemberLib::getUsersByStructure($this->eStructure)->first();

		if($eUser === NULL) {
			throw new \Exception('La structure de référence n\'a aucun membre');
		}

		if(PresenceLib::isPresentBetween($this->eStructure, $eUser, Absence::getPresenceSince(), NULL) === FALSE) {

			// Hors build() : aucune période ne court depuis trois mois, celle-ci ne chevauche donc rien.
			PresenceLib::create(new Presence([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'from' => \Clock::date(),
				'to' => NULL
			]));

		}

		return $eUser;

	}

	protected function getUserOutsider(): \user\User {

		// La collection est calculée avant la chaîne : getUsersByStructure() interroge lui-même le
		// singleton user\User, et l'appeler dans le whereId() effacerait le select() et le where().
		$cUserMember = \structure\MemberLib::getUsersByStructure($this->eStructure);

		$eUser = \user\User::model()
			->select(['id'])
			->whereId('NOT IN', $cUserMember)
			->whereStatus(\user\User::ACTIVE)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Aucun utilisateur hors de l\'équipe de la structure de référence');
		}

		return $eUser;

	}

	protected function createAbsence(array $body = []): array {

		return $this->callApi('POST', 'absence', body: $body + [
			'user' => $this->getUserMember()['id'],
			'type' => Absence::VACATION,
			'from' => '2030-07-01 09:00:00',
			'to' => '2030-07-05 18:00:00',
			'duration' => 4.5
		]);

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'absenceList', get: ['start' => 1, 'limit' => 100]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));

	}

	public function testListFilteredByUser(): void {

		$create = $this->createAbsence();

		$this->assertEquals(201, $create['status']);

		$response = $this->callApi('GET', 'absenceList', get: ['user' => $create['body']['user'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(TRUE, in_array($create['body']['id'], array_column($response['body']['list'], 'id')));
		$this->assertEquals([$create['body']['user']], array_unique(array_column($response['body']['list'], 'user')));

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->createAbsence();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(Absence::VACATION, $create['body']['type']);
		$this->assertEquals(Absence::VALIDATED, $create['body']['status'], 'l\'API saisit comme le gérant');
		$this->assertEquals(Absence::DAY, $create['body']['unit']);
		$this->assertEquals(4.5, $create['body']['duration'], 'la durée postée');

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'absence/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);

		$update = $this->callApi('PUT', 'absence/{id}', body: [
			'properties' => ['type', 'to'],
			'type' => Absence::RTT,
			'to' => '2030-07-02 18:00:00'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(Absence::RTT, $update['body']['type']);
		$this->assertEquals(4.5, $update['body']['duration'], 'la durée suit la fin changée');

		$delete = $this->callApi('DELETE', 'absence/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);
		$this->assertEquals(404, $this->callApi('GET', 'absence/{id}', get: ['id' => $id])['status']);

	}

	/**
	 * RH éteinte, chaque route répond le même refus, y compris sur un identifiant qui n'existe pas : un 404
	 * dirait lesquels existent. L'absence posée avant l'extinction reste en base.
	 */
	public function testHrOffIsRefused(): void {

		$id = $this->createAbsence()['body']['id'];

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$responses = [
			$this->callApi('GET', 'absenceList'),
			$this->callApi('GET', 'absence/{id}', get: ['id' => $id]),
			$this->callApi('GET', 'absence/{id}', get: ['id' => 999999999]),
			$this->createAbsence(),
			$this->callApi('PUT', 'absence/{id}', body: ['properties' => ['type'], 'type' => Absence::RTT], get: ['id' => $id]),
			$this->callApi('DELETE', 'absence/{id}', get: ['id' => $id])
		];

		foreach($responses as $response) {
			$this->assertEquals(412, $response['status']);
			$this->assertEquals('accept_hr', $response['body'][0]['code'] ?? NULL);
		}

		$this->assertEquals(Absence::VACATION, AbsenceLib::getById($id)['type']);

	}

	public function testCreateExposesNothingElse(): void {

		$create = $this->createAbsence();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(['id', 'user', 'type', 'unit', 'status', 'from', 'to', 'duration', 'createdAt'], array_keys($create['body']));

	}

	/** La durée se saisit, elle ne se calcule pas (décidé le 19/10). */
	public function testDurationIsRequired(): void {

		$response = $this->callApi('POST', 'absence', body: [
			'user' => $this->getUserMember()['id'],
			'type' => Absence::VACATION,
			'from' => '2030-07-01 09:00:00',
			'to' => '2030-07-05 18:00:00'
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Absence::duration.missing', $response['body'][0]['code'] ?? NULL);

	}

	/** Une absence se saisit pour une personne de l'équipe ces trois derniers mois. */
	public function testOutsiderIsRejected(): void {

		$response = $this->createAbsence(['user' => $this->getUserOutsider()['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Absence::user.present', $response['body'][0]['code'] ?? NULL);

	}

	public function testEndBeforeStartIsRejected(): void {

		$response = $this->createAbsence([
			'from' => '2030-07-05 09:00:00',
			'to' => '2030-07-01 18:00:00'
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Absence::to.consistency', $response['body'][0]['code'] ?? NULL);

	}

	public function testUnknownTypeIsRejected(): void {

		$response = $this->createAbsence(['type' => 'sabbatical']);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Absence::type.check', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Une absence d'un autre parc, que le jeton de celui-ci ne doit ni lire, ni écrire, ni supprimer.
	 *
	 * `ModuleApi` n'interroge que les `accept*` (`framework/core/Api.php`) : aucun `can*` n'est consulté,
	 * et `Absence::canRead()` comme `canWrite()` sont donc muets ici. Ce qui sépare deux parcs est le
	 * `whereStructure()` de `getNewModel()`, par lequel passe aussi le `getById()` des trois routes à
	 * identifiant. Ce cas le fige, la table `hrAbsence` vivant dans la base commune.
	 *
	 * L'absence est déplacée par le modèle, comme dans `bank\ReconciliationCrossTenantAi` : aucun Lib
	 * n'accepte de poser une structure qui n'est pas la sienne, et c'est l'état qu'un jeton d'un autre
	 * parc rencontrerait.
	 */
	public function testAnAbsenceOfAnotherStructureIsNotReachable(): void {

		$create = $this->createAbsence();

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$this->assertEquals(200, $this->callApi('GET', 'absence/{id}', get: ['id' => $id])['status'], 'l\'absence de la structure se lit');

		$eStructureOther = new \structure\StructureModel()
			->select(['id'])
			->whereId('!=', $this->eStructure['id'])
			->sort(['id' => SORT_ASC])
			->get();

		Absence::model()
			->whereId($id)
			->update(['structure' => $eStructureOther]);

		$this->assertEquals(404, $this->callApi('GET', 'absence/{id}', get: ['id' => $id])['status'], 'celle d\'une autre structure ne se lit pas');

		$update = $this->callApi('PUT', 'absence/{id}', body: [
			'properties' => ['type'],
			'type' => Absence::RTT
		], get: ['id' => $id]);

		$this->assertEquals(404, $update['status'], 'ni ne s\'écrit');
		$this->assertEquals(404, $this->callApi('DELETE', 'absence/{id}', get: ['id' => $id])['status'], 'ni ne se supprime');

		$this->assertTrue(Absence::model()->whereId($id)->exists(), 'alors qu\'elle est bien en base');

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'absence/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);

	}

}
?>
