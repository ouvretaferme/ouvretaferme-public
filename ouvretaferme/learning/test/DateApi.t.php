<?php
namespace learning;

/**
 * L'API des dates et des inscrits (lot L10e), de bout en bout par `\ApiRoute::run()`, avec la clé
 * d'un réseau. Tout y est en lecture seule : le décor s'écrit par les Lib, dans la transaction que
 * `\dev\ApiTest` ouvre sur `Course::model()` et qui couvre la base commune, où vivent les
 * formations, les dates, les inscriptions, les comptes et les structures.
 *
 * Chaque refus est précédé du cas accepté qui lui correspond, comme dans `ApiTest` du package : un
 * 404 sur la date d'un autre réseau ne prouve la borne que si la même route rend les dates du réseau
 * de la clé, et une route inconnue ne prouve la lecture seule que si la lecture de la même adresse
 * répond.
 *
 * Les personnes sont des comptes fictifs en `example.org`. Les e-mails que les écritures du décor
 * mettent en file vont à ces adresses, que la dev ne délivre pas (`MailSetting::$devSendOnly`), et
 * la transaction les retire ensuite. Aucune annulation n'est faite par la personne : c'est la seule
 * qui écrive au réseau, dont l'adresse est réelle.
 *
 * Lancement : dev/test package=learning file=DateApi
 */
class DateApiTest extends \dev\ApiTest {

	/** Réseau du décor commun, porteur de la clé */
	const STRUCTURE = \structure\FixtureAiLib::G3;

	/** Groupe de formation du décor commun : il lit des formations, jamais datées */
	const COMMUNITY_EDUCATION = \structure\FixtureAiLib::G2;

	/** Ferme du décor commun, active et non collective : un membre peut s'y inscrire */
	const FARM = \structure\FixtureAiLib::F1;

	/** Groupe de producteurs du décor commun : un groupe, mais ni école ni réseau */
	const COMMUNITY_PRODUCER = \structure\FixtureAiLib::G1;

	/** Compte du décor commun sans structure, qui porte la clé des groupes du décor sans administrateur */
	const EMAIL_KEY = 'decor.sans.structure@example.org';

	/** Les champs d'une date, dans l'ordre de la déclaration */
	const DATE_FIELDS = ['id', 'course', 'startAt', 'endAt', 'sequences', 'minutes', 'link', 'seats', 'offer', 'price', 'externalUrl', 'enrollmentPaused', 'status'];

	/** Les champs d'une inscription, dans l'ordre de la déclaration */
	const ENROLLMENT_FIELDS = [
		'id', 'date',
		'firstName', 'lastName', 'email', 'structureName', 'trainee',
		'status', 'requestedAt', 'confirmedAt', 'canceledAt', 'canceledBy',
		'attendedAt', 'attendedMinutes'
	];

	private Theme $eTheme;
	private Level $eLevel;

	private int $people = 0;

	/**
	 * Un autre réseau, que le décor commun n'a pas : le premier réseau de la base qui a un
	 * administrateur, sans qui sa clé serait refusée. Relu par `assertFixtures()`.
	 */
	private int $networkOther = 0;

	public function init(): void {

		parent::init();

		$this->eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$this->eLevel = LevelLib::getAll()->first() ?? new Level();

	}

	protected function getTransactionModel(): \ModuleModel {
		return Course::model();
	}

	/**
	 * Une structure du décor absente ou d'un autre type rendrait les refus verts sans avoir refusé
	 * ce qu'ils nomment : chacune est relue et comparée au type attendu. Le réseau doit aussi faire
	 * confirmer les demandes, sinon la ligne sans compte serait confirmée d'emblée et le filtre
	 * `?status` compterait deux lignes au lieu d'une.
	 *
	 * Le réseau et le groupe de producteurs du décor n'ont aucun administrateur, et une clé ne
	 * s'obtient qu'au nom d'un administrateur (`ApiSetting::$devAccessTokenUser`) : le compte du décor
	 * sans structure (`EMAIL_KEY`) les administre le temps du test.
	 */
	private function assertFixtures(): bool {

		$this->networkOther = $this->getNetworkOther();

		if($this->networkOther === 0) {
			$this->assertFail('aucun autre réseau que '.self::STRUCTURE.' avec un administrateur dans cette base');
			return FALSE;
		}

		$expected = [
			self::STRUCTURE => [\structure\Structure::COMMUNITY, \structure\Structure::NETWORK],
			self::COMMUNITY_EDUCATION => [\structure\Structure::COMMUNITY, \structure\Structure::EDUCATION],
			self::FARM => [\structure\Structure::PRODUCER, NULL],
			self::COMMUNITY_PRODUCER => [\structure\Structure::COMMUNITY, \structure\Structure::PRODUCER],
		];

		$cStructure = \structure\Structure::model()
			->select(['id', 'type', 'communityType'])
			->whereId('IN', array_keys($expected))
			->getCollection(index: 'id');

		$wrong = [];

		foreach($expected as $structure => [$type, $communityType]) {

			$eStructure = $cStructure->offsetExists($structure) ? $cStructure[$structure] : new \structure\Structure();

			if(
				$eStructure->empty() or
				$eStructure['type'] !== $type or
				$eStructure['communityType'] !== $communityType
			) {
				$wrong[] = $structure.' ('.$type.($communityType === NULL ? '' : ', '.$communityType).' attendu)';
			}

		}

		if($wrong !== []) {
			$this->assertFail('structures du décor absentes ou d\'un autre type, structure/configure/fixtureAi write=1 à jouer : '.implode(', ', $wrong));
			return FALSE;
		}

		foreach([self::STRUCTURE, self::COMMUNITY_PRODUCER] as $structure) {
			$this->joinAdmin(\structure\StructureLib::getById($structure));
		}

		$this->setLearning($this->networkOther);

		if(
			$this->eTheme->empty() or
			$this->eLevel->empty()
		) {
			$this->assertFail('décor absent : thématiques et niveaux, learning/configure/theme et learning/configure/level à jouer');
			return FALSE;
		}

		if(\structure\CommunityLib::getByStructure($this->eStructure)['learningConfirm'] === FALSE) {
			$this->assertFail('le réseau '.self::STRUCTURE.' confirme les inscriptions d\'emblée : le décor attend des demandes à confirmer');
			return FALSE;
		}

		return TRUE;

	}

	private function getNetworkOther(): int {

		$eStructure = \structure\Structure::model()
			->select('id')
			->whereType(\structure\Structure::COMMUNITY)
			->whereCommunityType(\structure\Structure::NETWORK)
			->whereStatus(\structure\Structure::ACTIVE)
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereId('IN', \structure\Member::model()
				->select('structure')
				->whereStatus(\structure\Member::IN)
				->whereStructureStatus(\structure\Member::ACTIVE)
				->whereRole('IN', new \structure\RoleModel()->select('id')->whereAdmin(TRUE)->getSql('t'))
				->getSql())
			->sort(['id' => SORT_ASC])
			->get();

		return $eStructure->empty() ? 0 : $eStructure['id'];

	}

	/** Les formations de l'autre réseau ne se lisent par sa clé que s'il les a allumées. */
	private function setLearning(int $structure): void {

		$eFeature = \structure\FeatureLib::readByStructure(new \structure\Structure(['id' => $structure]));

		if($eFeature['hasCommunityLearning']) {
			return;
		}

		$eFeature['hasCommunityLearning'] = TRUE;

		// Hors build(), qui n'aurait jugé que le booléen posté par l'interrupteur du groupe : la valeur
		// est écrite ici en dur, et ce n'est pas l'écran qu'éprouve ce test.
		\structure\FeatureLib::update($eFeature, ['hasCommunityLearning']);

		\structure\FeatureLib::forgetAll();

	}

	private function joinAdmin(\structure\Structure $eStructure): void {

		$eUser = \user\UserLib::getByEmail(self::EMAIL_KEY);

		if(\structure\MemberLib::isAdmin($eUser, $eStructure)) {
			return;
		}

		// Les rôles d'une structure se lisent dans un cache redis, qu'un rechargement de la base
		// depuis la production ne vide pas : un rôle tiré d'un cache antérieur porte un id que la base
		// n'a plus, et le membre créé n'administre rien. Vidé, le cache se relit sur la base courante.
		\structure\RoleLib::deleteCache($eStructure);

		$fw = new \FailWatch();

		// Hors build(), comme le pose InviteLib::accept() : un membre entre par une invitation, aucun
		// formulaire ne le construit.
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eStructure,
			'role' => \structure\RoleLib::getOne($eStructure, 'admin'),
			'status' => \structure\Member::IN
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion de '.self::EMAIL_KEY.' à la structure '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

	}

	private function asStructure(int $structure, \Closure $callback): void {

		$tokenInitial = \api\ApiSetting::$devAccessToken;

		\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken($structure);

		try {
			$callback();
		} finally {
			\api\ApiSetting::$devAccessToken = $tokenInitial;
		}

	}

	/**
	 * Une formation sur place d'un réseau, écrite sans passer par l'API, au nom de l'administrateur
	 * qui porte la clé de ce réseau : `createdBy` est le compte connecté.
	 */
	private function createCourse(int $structure, string $name): Course {

		\user\ConnectionLib::setOnline((\api\ApiSetting::$devAccessTokenUser)($structure));

		$eCourse = CourseLib::getCreateElement(\structure\StructureLib::getById($structure), Course::ONSITE);
		$eCourse->merge([
			'name' => $name,
			'summary' => 'Une formation sur place.',
			'theme' => $this->eTheme,
			'level' => $this->eLevel,
		]);

		CourseLib::create($eCourse);

		return $eCourse;

	}

	/** Une journée dans `$days` jours, construite par le chemin de `doCreate`. */
	private function createDate(Course $eCourse, int $days): Date {

		$day = date('Y-m-d', strtotime('+'.$days.' days'));

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		// Une séance, sous les noms que le formulaire poste (`DateSequenceUi::getRow()`), au mode que
		// la formation propose : le lieu et le lien postés tous les deux, le mode décide lequel reste.
		$eDate->build(DateLib::getPropertiesCreate(), [
			'sequences' => ['0'],
			'startAt' => ['0' => ['date' => $day, 'time' => '09:00']],
			'endAt' => ['0' => ['time' => '17:00']],
			'mode' => ['0' => $eCourse->getDefaultSequence()['mode']],
			'place' => ['0' => 'Salle des fêtes, Crest'],
			'link' => 'https://visio.example.org/zztest',
			'seats' => '12',
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return DateLib::getById($eDate['id']);

	}

	private function createPerson(): \user\User {

		$this->people++;

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Stagiaire API '.$this->people,
			'email' => 'zztest.stagiaire-api-'.$this->people.'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte refusée : '.json_encode($fw->get())));

		return \user\UserLib::getById($eUser['id'], \user\User::getSelection());

	}

	/**
	 * La ligne d'un membre, par le chemin du panneau : l'identité reste sur le compte, les colonnes
	 * de la ligne restent vides. `$eStructureFarm` est la ferme au nom de laquelle il s'inscrit, que
	 * l'étape `owned` cherche parmi les siennes, celles du compte connecté.
	 */
	private function enrollMember(Date $eDate, \user\User $eUser, \structure\Structure $eStructureFarm = new \structure\Structure()): DateEnrollment {

		if($eStructureFarm->notEmpty()) {

			// Le cache des rôles peut précéder un rechargement de la base, comme dans joinAdmin().
			\structure\RoleLib::deleteCache($eStructureFarm);

			$fw = new \FailWatch();

			// Hors build(), comme le pose InviteLib::accept() : un membre entre par une invitation, aucun
			// formulaire ne le construit.
			\structure\MemberLib::create(new \structure\Member([
				'user' => $eUser,
				'structure' => $eStructureFarm,
				'role' => \structure\RoleLib::getOne($eStructureFarm, 'admin'),
				'status' => \structure\Member::IN
			]));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion refusée : '.json_encode($fw->get())));

		}

		\user\ConnectionLib::setOnline($eUser);

		try {

			$eDateEnrollment = DateEnrollmentLib::getCreateElement($eDate, $eUser);

			$fw = new \FailWatch();

			$eDateEnrollment->build(DateEnrollmentLib::getPropertiesCreate(), [
				'structure' => $eStructureFarm->empty() ? '' : (string)$eStructureFarm['id'],
				'trainee' => DateEnrollment::FARMER,
				'commitment' => ['presence', 'cancel', 'conditions'],
			], new \PropertyList('create'));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('inscription du membre refusée : '.json_encode($fw->get())));

			DateEnrollmentLib::create($eDateEnrollment);

		} finally {
			\user\ConnectionLib::setOnline(new \user\User());
		}

		return DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

	}

	/** La ligne d'une personne sans compte, par le chemin de la page publique du réseau. */
	private function enrollPublic(Date $eDate, string $email): DateEnrollment {

		$eStructureNetwork = \structure\StructureLib::getById(self::STRUCTURE);
		$eStructureNetwork['community'] = \structure\CommunityLib::getByStructure($eStructureNetwork);

		$eDateEnrollment = DateEnrollmentLib::getPublicCreateElement($eDate, $eStructureNetwork, new \user\User());

		$fw = new \FailWatch();

		$eDateEnrollment->build(DateEnrollmentLib::getPropertiesCreate('public'), [
			'firstName' => 'zzTest',
			'lastName' => 'Sans compte',
			'email' => $email,
			'structureName' => 'zzTest ferme saisie',
			'trainee' => DateEnrollment::PROJECT,
			'commitment' => ['presence', 'cancel', 'conditions'],
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('inscription publique refusée : '.json_encode($fw->get())));

		\user\ThrottleAiLib::clear('learning-enrollment-ip', getIp());

		DateEnrollmentLib::createPublic($eDateEnrollment);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('inscription publique refusée : '.json_encode($fw->get())));

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereEmail($email)
			->get();

	}

	/**
	 * Une date, et trois lignes qui couvrent les deux origines et les trois lectures de la
	 * structure : un membre confirmé au nom d'une ferme, une personne sans compte en demande avec la
	 * ferme qu'elle a saisie, un membre à titre personnel écarté par le réseau.
	 */
	private function seedEnrollments(): array {

		$eCourse = $this->createCourse(self::STRUCTURE, 'zzTest formation datée API');
		$eDate = $this->createDate($eCourse, 10);

		$eUserMember = $this->createPerson();
		$eDateEnrollmentMember = $this->enrollMember($eDate, $eUserMember, \structure\StructureLib::getById(self::FARM));

		if($eDateEnrollmentMember['status'] === DateEnrollment::REQUESTED) {
			DateEnrollmentLib::confirm($eDateEnrollmentMember);
		}

		$eDateEnrollmentPublic = $this->enrollPublic($eDate, 'zztest.sans-compte-api@example.org');

		$eUserCanceled = $this->createPerson();
		$eDateEnrollmentCanceled = $this->enrollMember($eDate, $eUserCanceled);

		DateEnrollmentLib::cancel($eDateEnrollmentCanceled, DateEnrollment::COMMUNITY);

		$cDateEnrollment = DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->getCollection(index: 'id');

		return [
			$eDate,
			$eUserMember,
			$cDateEnrollment[$eDateEnrollmentMember['id']],
			$cDateEnrollment[$eDateEnrollmentPublic['id']],
			$cDateEnrollment[$eDateEnrollmentCanceled['id']],
		];

	}

	private function assertStatus(int $status, array $response, string $message): void {
		$this->assertEquals($status, $response['status'], $message.' ('.json_encode($response['body']).')');
	}

	private function assertErrorCode(string $code, array $response, string $message): void {
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $message.' ('.json_encode($response['body']).')');
	}

	private function getIds(array $response): array {
		return array_column($response['body']['list'] ?? [], 'id');
	}

	/**
	 * La liste des dates du réseau, ses deux filtres et les champs rendus. La date d'un autre réseau
	 * existe pendant la lecture : elle ne doit jamais y paraître.
	 */
	public function testDateList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->createCourse(self::STRUCTURE, 'zzTest formation datée API');
		$eDatePlanned = $this->createDate($eCourse, 10);
		$eDateCanceled = $this->createDate($eCourse, 20);

		DateLib::cancel($eDateCanceled);

		$eCourseOther = $this->createCourse(self::STRUCTURE, 'zzTest autre formation datée API');
		$eDateOther = $this->createDate($eCourseOther, 30);

		$eDateForeign = $this->createDate($this->createCourse($this->networkOther, 'zzTest formation d\'ailleurs'), 10);

		$response = $this->callApi('GET', 'courseDateList', get: ['limit' => 500]);

		$this->assertStatus(200, $response, 'courseDateList');

		$ids = $this->getIds($response);

		$this->assertEquals([], array_values(array_diff([$eDatePlanned['id'], $eDateCanceled['id'], $eDateOther['id']], $ids)), 'les dates du réseau sont listées');
		$this->assertFalse(in_array($eDateForeign['id'], $ids, TRUE), 'la date d\'un autre réseau n\'est pas listée');

		$response = $this->callApi('GET', 'courseDateList', get: ['course' => $eCourse['id']]);

		$this->assertStatus(200, $response, 'courseDateList?course');
		$this->assertEquals(2, $response['body']['count'] ?? NULL, 'courseDateList?course compte les deux dates de la formation');
		$this->assertEquals([$eDatePlanned['id'], $eDateCanceled['id']], $this->getIds($response), 'courseDateList?course');

		$response = $this->callApi('GET', 'courseDateList', get: ['course' => $eCourse['id'], 'status' => Date::CANCELED]);

		$this->assertStatus(200, $response, 'courseDateList?course&status');
		$this->assertEquals([$eDateCanceled['id']], $this->getIds($response), 'courseDateList?status=canceled ne rend que la date annulée');

		// Une formation d'un autre réseau passée en filtre ne franchit pas la borne : liste vide, et non 404,
		// comme un filtre qui ne trouve rien.
		$response = $this->callApi('GET', 'courseDateList', get: ['course' => $eDateForeign['course']['id']]);

		$this->assertStatus(200, $response, 'courseDateList?course d\'un autre réseau');
		$this->assertEquals(0, $response['body']['count'] ?? NULL, 'courseDateList?course d\'un autre réseau');

		$response = $this->callApi('GET', 'courseDate/{id}', get: ['id' => $eDatePlanned['id']]);

		$this->assertStatus(200, $response, 'courseDate/{id}');
		$this->assertEquals(self::DATE_FIELDS, array_keys($response['body']), 'champs d\'une date');
		$this->assertEquals($eCourse['id'], $response['body']['course'], 'la formation se rend par son id');
		$this->assertEquals(Date::PLANNED, $response['body']['status'], 'état de la date');
		$this->assertEquals([
			['startAt' => $eDatePlanned['startAt'], 'endAt' => $eDatePlanned['endAt'], 'mode' => DateSequence::ONSITE, 'place' => 'Salle des fêtes, Crest'],
		], $response['body']['sequences'], 'les séances de la date, chacune avec son mode et son lieu');
		$this->assertEquals(12, $response['body']['seats'], 'places de la date');

	}

	/**
	 * Les inscrits d'une date, et le cas qui décide de la forme des champs : une ligne de membre n'a
	 * ni prénom, ni nom, ni adresse dans ses colonnes, qui ne se remplissent que sans compte. L'API
	 * les rend lus sur le compte.
	 */
	public function testEnrollmentList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eDate, $eUserMember, $eDateEnrollmentMember, $eDateEnrollmentPublic, $eDateEnrollmentCanceled] = $this->seedEnrollments();

		// Le décor d'abord : sans ces états, les comptes plus bas passeraient pour une autre raison.
		$this->assertEquals(
			[DateEnrollment::CONFIRMED, DateEnrollment::REQUESTED, DateEnrollment::CANCELED],
			[$eDateEnrollmentMember['status'], $eDateEnrollmentPublic['status'], $eDateEnrollmentCanceled['status']],
			'décor : une ligne confirmée, une demande, une annulée'
		);

		$this->assertEquals(
			[NULL, NULL, NULL],
			[$eDateEnrollmentMember['firstName'], $eDateEnrollmentMember['lastName'], $eDateEnrollmentMember['email']],
			'décor : la ligne de membre ne porte aucune identité dans ses colonnes'
		);

		$response = $this->callApi('GET', 'courseDate/{id}/enrollmentList', get: ['id' => $eDate['id']]);

		$this->assertStatus(200, $response, 'enrollmentList');
		$this->assertEquals(3, $response['body']['count'] ?? NULL, 'enrollmentList sans filtre compte les trois lignes');

		$list = array_column($response['body']['list'] ?? [], NULL, 'id');

		$this->assertEquals(
			[$eDateEnrollmentMember['id'], $eDateEnrollmentPublic['id'], $eDateEnrollmentCanceled['id']],
			array_keys($list),
			'enrollmentList rend les trois lignes, par id'
		);

		foreach($list as $id => $line) {
			$this->assertEquals(self::ENROLLMENT_FIELDS, array_keys($line), 'champs de l\'inscription '.$id.', sans token, user ni createdAt');
		}

		$member = $list[$eDateEnrollmentMember['id']] ?? [];

		$this->assertEquals(
			[
				'date' => $eDate['id'],
				'firstName' => $eUserMember['firstName'],
				'lastName' => $eUserMember['lastName'],
				'email' => $eUserMember['email'],
				'structureName' => \structure\StructureLib::getById(self::FARM)['name'],
				'status' => DateEnrollment::CONFIRMED,
			],
			array_intersect_key($member, array_flip(['firstName', 'lastName', 'email', 'structureName', 'date', 'status'])),
			'ligne de membre : l\'identité lue sur le compte, la structure choisie'
		);

		$this->assertTrue(($member['confirmedAt'] ?? NULL) !== NULL, 'ligne de membre : date de confirmation');

		$public = $list[$eDateEnrollmentPublic['id']] ?? [];

		$this->assertEquals(
			[
				'firstName' => 'zzTest',
				'lastName' => 'Sans compte',
				'email' => 'zztest.sans-compte-api@example.org',
				'structureName' => 'zzTest ferme saisie',
				'trainee' => DateEnrollment::PROJECT,
				'status' => DateEnrollment::REQUESTED,
			],
			array_intersect_key($public, array_flip(['firstName', 'lastName', 'email', 'structureName', 'trainee', 'status'])),
			'ligne sans compte : l\'identité et la ferme saisies'
		);

		$canceled = $list[$eDateEnrollmentCanceled['id']] ?? [];

		$this->assertTrue(array_key_exists('structureName', $canceled), 'ligne à titre personnel : structureName rendu');
		$this->assertEquals(NULL, $canceled['structureName'] ?? NULL, 'ligne à titre personnel : aucune structure');
		$this->assertEquals(DateEnrollment::COMMUNITY, $canceled['canceledBy'] ?? NULL, 'ligne écartée par le réseau');

		$response = $this->callApi('GET', 'courseDate/{id}/enrollmentList', get: ['id' => $eDate['id'], 'status' => DateEnrollment::CONFIRMED]);

		$this->assertStatus(200, $response, 'enrollmentList?status');
		$this->assertEquals(1, $response['body']['count'] ?? NULL, 'enrollmentList?status=confirmed compte les places prises');
		$this->assertEquals([$eDateEnrollmentMember['id']], $this->getIds($response), 'enrollmentList?status=confirmed');

		$response = $this->callApi('GET', 'dateEnrollment/{id}', get: ['id' => $eDateEnrollmentMember['id']]);

		$this->assertStatus(200, $response, 'dateEnrollment/{id}');
		$this->assertEquals($member, $response['body'], 'la ressource se lit comme dans la liste');

	}

	/**
	 * Une place dont le compte a été fermé (`DropLib::closeNow()` vide l'adresse) : la liste et la
	 * ressource la rendent, le nom lu sur le compte et l'adresse à `null`.
	 */
	public function testEnrollmentClosedAccount(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->createCourse(self::STRUCTURE, 'zzTest formation datée API compte fermé');
		$eDate = $this->createDate($eCourse, 10);

		$eUser = $this->createPerson();
		$eDateEnrollment = $this->enrollMember($eDate, $eUser);

		\user\DropLib::closeNow($eUser);

		$this->assertEquals(NULL, \user\UserLib::getById($eUser['id'], ['email'])['email'], 'décor : compte fermé, adresse vidée');

		$response = $this->callApi('GET', 'courseDate/{id}/enrollmentList', get: ['id' => $eDate['id']]);

		$this->assertStatus(200, $response, 'enrollmentList');
		$this->assertEquals([$eDateEnrollment['id']], $this->getIds($response), 'la place du compte fermé est rendue');

		$expected = [
			'firstName' => 'zzTest',
			'lastName' => $eUser['lastName'],
			'email' => NULL,
		];

		$this->assertEquals($expected, array_intersect_key($response['body']['list'][0] ?? [], $expected), 'le nom du compte, sans adresse');

		$response = $this->callApi('GET', 'dateEnrollment/{id}', get: ['id' => $eDateEnrollment['id']]);

		$this->assertStatus(200, $response, 'dateEnrollment/{id}');
		$this->assertEquals($expected, array_intersect_key($response['body'] ?? [], $expected), 'la ressource aussi');

	}

	/**
	 * La clé d'un autre réseau lit ses propres dates et ses propres inscrits, et reçoit 404 sur ceux
	 * du réseau de la clé, relus en 200 juste avant.
	 */
	public function testForeignNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eDate, , $eDateEnrollmentMember] = $this->seedEnrollments();

		$eDateForeign = $this->createDate($this->createCourse($this->networkOther, 'zzTest formation d\'ailleurs'), 10);

		$ownRoutes = [
			['courseDate/{id}', $eDate['id']],
			['courseDate/{id}/enrollmentList', $eDate['id']],
			['dateEnrollment/{id}', $eDateEnrollmentMember['id']],
		];

		foreach($ownRoutes as [$route, $id]) {
			$this->assertStatus(200, $this->callApi('GET', $route, get: ['id' => $id]), 'réseau de la clé : GET '.$route);
		}

		$this->assertStatus(404, $this->callApi('GET', 'courseDate/{id}', get: ['id' => $eDateForeign['id']]), 'réseau de la clé : date d\'un autre réseau');

		$this->asStructure($this->networkOther, function() use ($ownRoutes, $eDateForeign) {

			$this->assertStatus(200, $this->callApi('GET', 'courseDate/{id}', get: ['id' => $eDateForeign['id']]), 'autre réseau : sa propre date');
			$this->assertStatus(200, $this->callApi('GET', 'courseDate/{id}/enrollmentList', get: ['id' => $eDateForeign['id']]), 'autre réseau : ses propres inscrits');

			foreach($ownRoutes as [$route, $id]) {

				$response = $this->callApi('GET', $route, get: ['id' => $id]);

				$this->assertStatus(404, $response, 'autre réseau : GET '.$route);
				$this->assertErrorCode('not_found', $response, 'autre réseau : GET '.$route);

			}

		});

	}

	/**
	 * Un groupe de formation passe la garde des formations et ne lit aucune date : ses formations ne
	 * sont jamais datées. Une ferme et un groupe de producteurs, qui n'ont pas les formations, sont
	 * refusés au scope sur toutes les routes (`Structure::acceptApiScope()`).
	 */
	public function testStructureKey(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eDate, , $eDateEnrollmentMember] = $this->seedEnrollments();

		$routes = [
			['courseDateList', []],
			['courseDate/{id}', ['id' => $eDate['id']]],
			['courseDate/{id}/enrollmentList', ['id' => $eDate['id']]],
			['dateEnrollment/{id}', ['id' => $eDateEnrollmentMember['id']]],
		];

		foreach($routes as [$route, $get]) {
			$this->assertStatus(200, $this->callApi('GET', $route, get: $get), 'réseau : GET '.$route);
		}

		$this->asStructure(self::COMMUNITY_EDUCATION, function() {

			$response = $this->callApi('GET', 'courseDateList');

			$this->assertStatus(200, $response, 'groupe de formation : courseDateList');
			$this->assertEquals(0, $response['body']['count'] ?? NULL, 'groupe de formation : aucune date');

		});

		foreach([self::FARM => 'ferme', self::COMMUNITY_PRODUCER => 'groupe de producteurs'] as $structure => $label) {

			$this->asStructure($structure, function() use ($label, $routes) {

				foreach($routes as [$route, $get]) {

					$response = $this->callApi('GET', $route, get: $get);

					$this->assertStatus(403, $response, $label.' : GET '.$route);
					$this->assertErrorCode('scope_not_open', $response, $label.' : GET '.$route);

				}

			});

		}

	}

	/**
	 * Aucune route n'écrit une date ni une inscription : la même adresse répond en lecture, et rend
	 * une route inconnue pour tout autre verbe.
	 */
	public function testReadOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eDate, , $eDateEnrollmentMember] = $this->seedEnrollments();

		$this->assertStatus(200, $this->callApi('GET', 'courseDate/{id}', get: ['id' => $eDate['id']]), 'GET courseDate/{id}');
		$this->assertStatus(200, $this->callApi('GET', 'dateEnrollment/{id}', get: ['id' => $eDateEnrollmentMember['id']]), 'GET dateEnrollment/{id}');

		foreach([
			['POST', 'courseDate', [], 404],
			['PUT', 'courseDate/{id}', ['id' => $eDate['id']], 405],
			['DELETE', 'courseDate/{id}', ['id' => $eDate['id']], 405],
			['POST', 'dateEnrollment', [], 404],
			['PUT', 'dateEnrollment/{id}', ['id' => $eDateEnrollmentMember['id']], 405],
			['DELETE', 'dateEnrollment/{id}', ['id' => $eDateEnrollmentMember['id']], 405],
		] as [$method, $route, $get, $status]) {

			$response = $this->callApi($method, $route, body: ['status' => DateEnrollment::CANCELED], get: $get);

			$this->assertStatus($status, $response, $method.' '.$route);
			$this->assertErrorCode(match($status) { 404 => 'unknown_route', 405 => 'method_not_allowed' }, $response, $method.' '.$route);

		}

		$this->assertEquals(DateEnrollment::CONFIRMED, DateEnrollmentLib::getByDateAndUser($eDate, $eDateEnrollmentMember['user'])['status'], 'la ligne n\'a pas changé');

	}

}
?>
