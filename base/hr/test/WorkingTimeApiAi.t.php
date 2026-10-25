<?php
namespace hr;

/**
 * Test bout-en-bout de l'API publique hr\WorkingTime (base/hr/api/WorkingTime_0_1beta.a.php).
 *
 * Lancement : dev/test package=hr file=WorkingTimeApiAi
 */
class WorkingTimeApiAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new WorkingTimeModel();
	}

	/** Le schéma plafonne date à aujourd'hui : on ne pointe pas d'heures dans le futur. */
	protected const DATE = '2020-06-17';

	/**
	 * Une journée ne s'écrit que dans une période de présence de la personne (`WorkingTime::date.outOfPresence`) : le
	 * membre de la structure de référence y est mis le mois de `DATE`, dans la transaction, quand aucune de ses
	 * périodes ne le couvre. Il peut n'en avoir aucune, et la journée s'écrit alors sans elle.
	 */
	public function setUp(): void {

		parent::setUp();

		$eUser = $this->getUserMember();

		if(PresenceLib::isWorkable($this->eStructure, $eUser, self::DATE) === FALSE) {

			// Hors build() : le décor pose la période sans passer par le formulaire de l'équipe, dont l'étape `teammate`
			// demande une fiche d'équipier que le test n'a pas à monter pour éprouver l'API des journées.
			PresenceLib::create(new Presence([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'from' => '2020-06-01',
				'to' => '2020-06-30'
			]));

		}

	}

	protected function getUserMember(): \user\User {

		$eUser = \structure\MemberLib::getUsersByStructure($this->eStructure)->first();

		if($eUser === NULL) {
			throw new \Exception('La structure de référence n\'a aucun membre');
		}

		return $eUser;

	}

	/**
	 * Ni membre, ni ancien membre : user.check accepte aussi quiconque garde une présence dans la
	 * structure, il faut donc écarter les deux populations.
	 */
	protected function getUserOutsider(): \user\User {

		// Les collections sont calculées avant la chaîne : getUsersByStructure() interroge lui-même le
		// singleton user\User, et l'appeler dans le whereId() effacerait le select() et le where().
		$cUserMember = \structure\MemberLib::getUsersByStructure($this->eStructure);

		$userPresence = Presence::model()
			->select(['user'])
			->whereStructure($this->eStructure)
			->getCollection()
			->getColumnCollection('user', 'user')
			->getIds();

		$eUser = \user\User::model()
			->select(['id'])
			->whereId('NOT IN', array_merge($cUserMember->getIds(), $userPresence))
			->whereStatus(\user\User::ACTIVE)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Aucun utilisateur étranger à la structure de référence');
		}

		return $eUser;

	}

	protected function createWorkingTime(array $body = []): array {

		return $this->callApi('POST', 'workingTime', body: $body + [
			'user' => $this->getUserMember()['id'],
			'date' => self::DATE,
			'time' => 7.0
		]);

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'workingTimeList', get: ['start' => 1, 'limit' => 100]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));

	}

	public function testCreateAndRead(): void {

		$create = $this->createWorkingTime();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(self::DATE, $create['body']['date']);
		$this->assertEquals(7.0, $create['body']['time']);

		$read = $this->callApi('GET', 'workingTime/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(7.0, $read['body']['time']);

	}

	public function testCreateExposesNothingElse(): void {

		$create = $this->createWorkingTime();

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(['id', 'user', 'date', 'time', 'start', 'end', 'pause'], array_keys($create['body']));
		$this->assertEquals(NULL, $create['body']['start']);

	}

	/** UNIQUE (structure, user, date) : renvoyer la même journée écrase au lieu de dupliquer. */
	public function testSameDayIsUpserted(): void {

		$first = $this->createWorkingTime(['time' => 4.0]);

		$this->assertEquals(201, $first['status']);

		$second = $this->createWorkingTime(['time' => 6.5]);

		$this->assertEquals(201, $second['status']);
		$this->assertEquals(6.5, $second['body']['time']);

		$count = WorkingTime::model()
			->whereStructure($this->eStructure)
			->whereUser($this->getUserMember())
			->whereDate(self::DATE)
			->count();

		$this->assertEquals(1, $count);

	}

	/**
	 * WorkingTimeLib::create() supprime la journée quand le temps est nul. Côté API c'est un DELETE :
	 * un POST répondrait 201 en désignant une ressource qui vient de disparaître. Un temps négatif,
	 * lui, est déjà écarté par build() (cf. testNegativeTimeIsRejectedByBuild).
	 */
	public function testZeroTimeIsRejected(): void {

		$create = $this->createWorkingTime(['time' => 4.0]);

		$this->assertEquals(201, $create['status']);

		$response = $this->createWorkingTime(['time' => 0.0]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('time_zero', $response['body'][0]['code'] ?? NULL);

		// La journée est intacte : le refus intervient avant l'écriture.
		$read = $this->callApi('GET', 'workingTime/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(4.0, $read['body']['time']);

	}

	/** La borne basse du schéma s'en charge : rien à réécrire côté API. */
	public function testNegativeTimeIsRejectedByBuild(): void {

		$response = $this->createWorkingTime(['time' => -1.0]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WorkingTime::time.check', $response['body'][0]['code'] ?? NULL);

	}

	/** On ne pointe des heures que pour quelqu'un de l'équipe. */
	public function testOutsiderIsRejected(): void {

		$response = $this->createWorkingTime(['user' => $this->getUserOutsider()['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('WorkingTime::user.member', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Le module n'a qu'une écriture, l'upsert de WorkingTimeLib::create() : ni le web ni le Lib n'ont
	 * de mise à jour ni de suppression propres, l'API n'en invente pas.
	 */
	public function testOnlyCreateIsExposed(): void {

		$create = $this->createWorkingTime();

		$this->assertEquals(201, $create['status']);

		foreach(['PUT', 'DELETE'] as $method) {

			$response = $this->callApi($method, 'workingTime/{id}', body: [
				'time' => 1.0
			], get: ['id' => $create['body']['id']]);

			$this->assertEquals(405, $response['status']);
			$this->assertEquals('method_not_allowed', $response['body'][0]['code'] ?? NULL);

		}

	}

	/**
	 * Sous horaires, la journée se poste par l'arrivée, le départ et la pause, et sa durée s'en calcule : la
	 * durée postée n'est pas lue.
	 */
	public function testRangeCreate(): void {

		$this->withTimeRange(function() {

			$create = $this->createRange(['start' => '08:00', 'end' => '16:30', 'pause' => 1.0, 'time' => 2.0]);

			$this->assertEquals(201, $create['status']);
			$this->assertEquals(7.5, $create['body']['time']);
			$this->assertEquals('08:00', substr($create['body']['start'], 0, 5));
			$this->assertEquals('16:30', substr($create['body']['end'], 0, 5));
			$this->assertEquals(1.0, $create['body']['pause']);

			// Une pause nulle compte la plage entière. Elle se poste quand même : l'API exige toute propriété de
			// la liste (`Api::convertProperties()`).
			$create = $this->createRange(['start' => '08:00', 'end' => '12:15', 'pause' => NULL]);

			$this->assertEquals(201, $create['status']);
			$this->assertEquals(4.25, $create['body']['time']);
			$this->assertEquals(0.0, $create['body']['pause']);

		});

	}

	public function testRangeStartMissing(): void {

		$this->withTimeRange(function() {

			$response = $this->createRange(['end' => '16:30', 'pause' => NULL]);

			$this->assertEquals(400, $response['status']);
			$this->assertEquals('WorkingTime::start.missing', $response['body'][0]['code'] ?? NULL);

		});

	}

	/** Une journée ne passe pas minuit : un départ avant l'arrivée est refusé, pas compté sur deux jours. */
	public function testRangeEndBeforeStart(): void {

		$this->withTimeRange(function() {

			$response = $this->createRange(['start' => '22:00', 'end' => '06:00', 'pause' => NULL]);

			$this->assertEquals(400, $response['status']);
			$this->assertEquals('WorkingTime::end.afterStart', $response['body'][0]['code'] ?? NULL);

		});

	}

	/** Une pause aussi longue que la plage laisserait une journée nulle, que l'API ne sait pas écrire. */
	public function testRangePauseTooLong(): void {

		$this->withTimeRange(function() {

			$response = $this->createRange(['start' => '08:00', 'end' => '09:00', 'pause' => 1.0]);

			$this->assertEquals(400, $response['status']);
			$this->assertEquals('WorkingTime::pause.tooLong', $response['body'][0]['code'] ?? NULL);

		});

	}

	protected function createRange(array $body): array {

		return $this->callApi('POST', 'workingTime', body: $body + [
			'user' => $this->getUserMember()['id'],
			'date' => self::DATE
		]);

	}

	/**
	 * La structure à horaires se pose sur sa configuration chargée, que chaque appel relit (`Structure::conf()`) :
	 * rien ne s'écrit en base, et la configuration reprend sa valeur même quand une assertion échoue.
	 */
	protected function withTimeRange(\Closure $test): void {

		$eConfiguration = $this->eStructure->conf();
		$timeRange = $eConfiguration['timeRange'];

		$eConfiguration['timeRange'] = TRUE;

		try {
			$test();
		} finally {
			$eConfiguration['timeRange'] = $timeRange;
		}

	}

	/**
	 * Sans temps de travail ni suivi RH, la journée ne s'écrit pas, et la RH seule suffit à l'ouvrir. La RH se pose
	 * dans la transaction, et le rollback ne défait pas la mémoïsation de `conf()`, qui s'oublie donc à
	 * chaque changement et à la sortie.
	 */
	public function testUntrackedIsRejected(): void {

		$eConfiguration = $this->eStructure->conf();
		$time = $eConfiguration['time'];

		$eConfiguration['time'] = FALSE;

		try {

			\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
			\structure\FeatureLib::forget($this->eStructure);

			$response = $this->createWorkingTime();

			$this->assertEquals(400, $response['status'], 'la journée s\'écrit sans temps de travail ni RH');
			$this->assertEquals('WorkingTime::date.untracked', $response['body'][0]['code'] ?? NULL);

			\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
			\structure\FeatureLib::forget($this->eStructure);

			$this->assertEquals(201, $this->createWorkingTime()['status'], 'la journée ne s\'écrit pas sous la RH seule');

		} finally {
			$eConfiguration['time'] = $time;
			\structure\FeatureLib::forget($this->eStructure);
		}

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'workingTime/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);

	}

}
?>
