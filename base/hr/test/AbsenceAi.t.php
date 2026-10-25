<?php
namespace hr;

/**
 * Les absences d'une structure de production : qui peut demander, lire et retoucher une absence, la réponse
 * du gérant, la durée calculée sur les jours du contrat, et ce que le planning et la frise en montrent.
 *
 * Lancement : dev/test package=hr file=AbsenceAi
 */
class AbsenceAiTest extends \dev\TransactionTest {

	protected \structure\Structure $eStructure;

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

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

	}

	/**
	 * Bruno, du décor, au planning limité : le salarié qui demande. Une période qui court aujourd'hui lui est
	 * posée quand le décor n'en a pas, sans toucher à celles qu'il a.
	 */
	protected function getRequester(): \user\User {

		$eUser = $this->getUser('decor.f1.planning@example.org');

		if(TeammateLib::isTeammate($this->eStructure, $eUser) === FALSE) {

			// Hors build() : le test veut une période ouverte aujourd'hui, ce que le panneau accepterait aussi.
			TeammateLib::create(new Teammate([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'ePresence' => new Presence(['from' => \Clock::date(), 'to' => NULL])
			]));

		} else if(PresenceLib::isPresentBetween($this->eStructure, $eUser, \Clock::date(), NULL) === FALSE) {

			// Hors build() : aucune période ne court aujourd'hui ni après, celle-ci ne chevauche donc rien.
			PresenceLib::create(new Presence([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'from' => \Clock::date(),
				'to' => NULL
			]));

		}

		return $eUser;

	}

	/**
	 * La personne qui demande, au planning depuis `$from` sans fin, pour éprouver la construction d'une absence sur
	 * des jours passés : une absence ne se pose que dans une période de présence (`Absence::to.outOfPresence`). La
	 * période est posée quand aucune ne court déjà de `$from` à toujours.
	 */
	protected function getRequesterSince(string $from): \user\User {

		$eUser = $this->getUser('decor.f1.planning@example.org');

		if(TeammateLib::isTeammate($this->eStructure, $eUser) === FALSE) {

			// Hors build() : le test veut une période ouverte depuis `$from`, ce que le panneau accepterait aussi.
			TeammateLib::create(new Teammate([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'ePresence' => new Presence(['from' => $from, 'to' => NULL])
			]));

		} else if(PresenceLib::isPresentThroughout($this->eStructure, $eUser, $from, '9999-12-31') === FALSE) {

			// Hors build() : le décor de F1 ne donne aucune période à cette personne, celle-ci ne chevauche donc rien.
			PresenceLib::create(new Presence([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'from' => $from,
				'to' => NULL
			]));

		}

		return $eUser;

	}

	protected function getUser(string $email): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

	}

	/**
	 * Une personne qui a un accès avec le rôle de Bruno, sans être dans l'équipe, comme le pose
	 * `InviteLib::accept()`.
	 */
	protected function createAccess(string $name): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Absence',
			'lastName' => $name,
			'email' => 'hr-absence-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse.
		\user\UserLib::create($eUser);

		$eMemberRequester = \structure\Member::model()
			->select(['role'])
			->whereStructure($this->eStructure)
			->whereUser($this->getUser('decor.f1.planning@example.org'))
			->get();

		// Hors build(), comme le pose InviteLib::accept().
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => $eMemberRequester['role'],
			'status' => \structure\Member::IN
		]));

		return $eUser;

	}

	/**
	 * Une absence comme la pose un décor, saisie par le gérant sauf statut contraire.
	 */
	protected function createAbsence(\user\User $eUser, string $from, string $to, string $unit = Absence::DAY, string $status = Absence::VALIDATED, float $duration = 1.0): Absence {

		$eAbsence = new Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => Absence::VACATION,
			'unit' => $unit,
			'status' => $status,
			'from' => $from,
			'to' => $to,
			'duration' => $duration
		]);

		// Hors build() : le décor veut ces bornes exactes, y compris une borne à 08:00 que le panneau ne poste plus.
		AbsenceLib::create($eAbsence);

		return $eAbsence;

	}

	protected function asUser(\user\User $eUser, \Closure $test): void {

		$eUserOnline = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eUser);

		try {
			$test();
		} finally {
			\user\ConnectionLib::setOnline($eUserOnline);
		}

	}

	/**
	 * Une personne au planning demande pour elle-même tant qu'elle est dans l'équipe. Hors de l'équipe, ou partie,
	 * elle ne demande plus.
	 */
	public function testCanCreate(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserRequester = $this->getRequester();
		$eUserOutside = $this->createAccess('Hors équipe');
		$eUserLeft = $this->createAccess('Partie');

		// Hors build() : une période finie avant aujourd'hui, que le panneau de départ poserait aussi.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUserLeft,
			'ePresence' => new Presence(['from' => '2026-01-05', 'to' => '2026-05-31'])
		]));

		$this->asUser($eUserRequester, function() use($eUserRequester, $eUserOutside) {
			$this->assertTrue(new Absence(['structure' => $this->eStructure, 'user' => $eUserRequester])->canCreate(), 'une personne de l\'équipe ne demande pas pour elle-même');
			$this->assertFalse(new Absence(['structure' => $this->eStructure, 'user' => $eUserOutside])->canCreate(), 'une personne de l\'équipe demande pour une autre');
		});

		$this->asUser($eUserOutside, function() use($eUserOutside) {
			$this->assertFalse(new Absence(['structure' => $this->eStructure, 'user' => $eUserOutside])->canCreate(), 'une personne hors de l\'équipe demande');
		});

		$this->asUser($eUserLeft, function() use($eUserLeft) {
			$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eUserLeft), 'la personne partie reste dans l\'équipe');
			$this->assertFalse(new Absence(['structure' => $this->eStructure, 'user' => $eUserLeft])->canCreate(), 'une personne partie demande');
		});

	}

	/**
	 * Le panneau du gérant ne propose que les personnes dont une période court encore ou a fini depuis trois mois
	 * au plus, et l'étape `present` refuse les autres, qu'un POST nommerait quand même.
	 */
	public function testUsersForAbsence(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserRequester = $this->getRequester();
		$eUserOutside = $this->createAccess('Hors équipe');
		$eUserLeft = $this->createAccess('Partie');
		$eUserLeftLong = $this->createAccess('Partie depuis longtemps');

		// Hors build() : des périodes finies avant aujourd'hui, que le panneau de départ poserait aussi.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUserLeft,
			'ePresence' => new Presence(['from' => '2026-01-05', 'to' => '2026-03-15'])
		]));

		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUserLeftLong,
			'ePresence' => new Presence(['from' => '2026-01-05', 'to' => '2026-03-14'])
		]));

		$cUser = TeammateLib::getUsersForAbsence($this->eStructure);

		$users = [
			[$eUserRequester, TRUE],
			[$eUserLeft, TRUE],
			[$eUserLeftLong, FALSE],
			[$eUserOutside, FALSE]
		];

		foreach($users as [$eUser, $accepted]) {

			$fw = new \FailWatch();

			new Absence(['structure' => $this->eStructure])->build(['user'], ['user' => $eUser['id']]);

			$this->assertEquals($accepted, $cUser->offsetExists($eUser['id']), $eUser['lastName'].' au panneau du gérant');
			$this->assertEquals($accepted === FALSE, $fw->has('Absence::user.present'), $eUser['lastName'].' devant l\'étape present');

		}

	}

	/**
	 * Le motif d'une absence peut dire la santé d'une personne : la liste de la semaine ne le lit que pour le gérant.
	 */
	public function testValidatedBetweenWithoutType(): void {

		$this->setClock('2026-06-15 10:00:00');

		$this->createAbsence($this->getRequester(), '2026-06-16 00:00:00', '2026-06-16 23:59:59');

		$cAbsence = AbsenceLib::getValidatedBetween($this->eStructure, '2026-06-15', '2026-06-21', withType: FALSE);
		$cAbsenceManager = AbsenceLib::getValidatedBetween($this->eStructure, '2026-06-15', '2026-06-21', withType: TRUE);

		$this->assertEquals(1, $cAbsence->count(), 'l\'absence de la semaine manque');
		$this->assertFalse($cAbsence->first()->offsetExists('type'), 'le motif est lu hors gérant');
		$this->assertEquals(Absence::VACATION, $cAbsenceManager->first()['type'], 'le motif manque au gérant');

	}

	/**
	 * Le salarié ne poste pas `user` : la page le pose sur la personne connectée, et la demande attend la réponse
	 * du gérant.
	 */
	public function testPostedUserIgnored(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserRequester = $this->getRequester();
		$eUserOther = $this->createAccess('Autre');

		$this->asUser($eUserRequester, function() use($eUserRequester, $eUserOther) {

			$response = $this->executePage('hr/absence:doCreate', [
				'structure' => (string)$this->eStructure['id'],
				'user' => (string)$eUserOther['id'],
				'type' => Absence::RTT,
				'unit' => Absence::DAY,
				'from' => ['date' => '2026-07-15', 'half' => ''],
				'to' => ['date' => '2026-07-16', 'half' => ''],
				'duration' => '2'
			]);

			$this->assertEquals([\ReloadAction::class, NULL], $response, 'la demande ne passe pas');

		});

		$cAbsence = Absence::model()
			->select(['user', 'status', 'from', 'to', 'duration'])
			->whereStructure($this->eStructure)
			->whereType(Absence::RTT)
			->whereFrom('2026-07-15 00:00:00')
			->getCollection();

		$this->assertEquals(1, $cAbsence->count());
		$this->assertEquals($eUserRequester['id'], $cAbsence->first()['user']['id'], 'la personne postée remplace la personne connectée');
		$this->assertEquals(Absence::PENDING, $cAbsence->first()['status'], 'la demande d\'un salarié n\'attend pas de réponse');
		$this->assertEquals('2026-07-16 23:59:59', $cAbsence->first()['to']);

	}

	/**
	 * Depuis « Mes absences » du planning, le gérant saisit pour lui-même : la personne postée ne remplace pas la
	 * personne connectée, l'absence est validée d'emblée, et un gérant qui n'est pas de l'équipe n'y saisit rien.
	 */
	public function testManagerOwnFromPlanning(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserRequester = $this->getRequester();
		$eUserManager = $this->createAccess('Gérante');

		$eMemberAdmin = \structure\Member::model()
			->select(['role'])
			->whereStructure($this->eStructure)
			->whereUser($this->getUser('decor.f1.admin@example.org'))
			->get();

		$eMemberManager = \structure\MemberLib::getByUserAndStructure($eUserManager, $this->eStructure);
		$eMemberManager['role'] = $eMemberAdmin['role'];

		// Hors build() : le décor change le rôle d'un accès qu'il vient de poser, comme le ferait le panneau des membres.
		\structure\MemberLib::update($eMemberManager, ['role']);

		$input = [
			'structure' => (string)$this->eStructure['id'],
			'origin' => 'planning',
			'user' => (string)$eUserRequester['id'],
			'type' => Absence::RTT,
			'unit' => Absence::DAY,
			'from' => ['date' => '2026-07-20', 'half' => ''],
			'to' => ['date' => '2026-07-20', 'half' => ''],
			'duration' => '1'
		];

		$this->asUser($eUserManager, function() use($input) {
			$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('hr/absence:doCreate', $input), 'un gérant hors de l\'équipe saisit pour lui-même');
		});

		// Hors build() : une période ouverte aujourd'hui, ce que le panneau accepterait aussi.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUserManager,
			'ePresence' => new Presence(['from' => \Clock::date(), 'to' => NULL])
		]));

		$this->asUser($eUserManager, function() use($input) {
			$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('hr/absence:doCreate', $input), 'l\'absence du gérant ne passe pas');
		});

		$cAbsence = Absence::model()
			->select(['user', 'status'])
			->whereStructure($this->eStructure)
			->whereType(Absence::RTT)
			->whereFrom('2026-07-20 00:00:00')
			->getCollection();

		$this->assertEquals(1, $cAbsence->count());
		$this->assertEquals($eUserManager['id'], $cAbsence->first()['user']['id'], 'la personne postée remplace le gérant connecté');
		$this->assertEquals(Absence::VALIDATED, $cAbsence->first()['status'], 'l\'absence du gérant attend une réponse');

	}

	/**
	 * Le salarié lit et retouche sa demande tant qu'elle attend ; il ne lit ni ne retouche celle d'un autre.
	 */
	public function testOwnRequestOnly(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserRequester = $this->getRequester();
		$eUserOther = $this->createAccess('Autre');

		$eAbsenceOwn = $this->createAbsence($eUserRequester, '2026-07-15 00:00:00', '2026-07-15 23:59:59', status: Absence::PENDING);
		$eAbsenceOther = $this->createAbsence($eUserOther, '2026-07-15 00:00:00', '2026-07-15 23:59:59', status: Absence::PENDING);
		$eAbsenceValidated = $this->createAbsence($eUserRequester, '2026-08-03 00:00:00', '2026-08-07 23:59:59');

		$this->asUser($eUserRequester, function() use($eAbsenceOwn, $eAbsenceOther, $eAbsenceValidated) {

			$this->assertTrue($eAbsenceOwn->canRead(), 'sa demande ne se lit pas');
			$this->assertTrue($eAbsenceOwn->canWrite(), 'sa demande ne se retouche pas');
			$this->assertFalse($eAbsenceOther->canRead(), 'la demande d\'un autre se lit');
			$this->assertFalse($eAbsenceOther->canWrite(), 'la demande d\'un autre se retouche');
			$this->assertTrue($eAbsenceValidated->canRead(), 'son absence validée ne se lit pas');
			$this->assertFalse($eAbsenceValidated->canWrite(), 'son absence validée se retouche');
			$this->assertFalse($eAbsenceOwn->canValidate(), 'le salarié valide');

			$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('hr/absence:doDelete', ['id' => (string)$eAbsenceOther['id']]), 'la demande d\'un autre se retire');

		});

		$this->assertTrue(Absence::model()->whereId($eAbsenceOther['id'])->exists(), 'la demande d\'un autre a disparu');

	}

	/**
	 * Deux réponses à la même demande : la seconde est refusée, et la première reste.
	 */
	public function testDecideOnce(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eAbsence = $this->createAbsence($this->getRequester(), '2026-07-15 00:00:00', '2026-07-15 23:59:59', status: Absence::PENDING);

		$fw = new \FailWatch();

		AbsenceLib::validate($eAbsence);

		$this->assertTrue($fw->ok(), 'la première réponse est refusée');

		AbsenceLib::refuse(new Absence(['id' => $eAbsence['id']]));

		$this->assertTrue($fw->has('Absence::status.decided'), 'la seconde réponse passe');
		$this->assertEquals(Absence::VALIDATED, Absence::model()->select(['status'])->whereId($eAbsence['id'])->get()['status'], 'la seconde réponse écrase la première');

	}

	/**
	 * Une absence ne se pose que dans les périodes de présence de la personne : demandée au-delà, elle est refusée,
	 * et une demande dont la période a été raccourcie depuis ne se valide plus. Une absence déjà en base qui
	 * déborde reste telle quelle, et seuls ses jours de présence se comptent.
	 */
	public function testOutOfPresence(): void {

		$this->setClock('2026-07-10 10:00:00');

		$eUser = $this->createAccess('Présence');

		$ePresence = new Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => '2026-07-01',
			'to' => '2026-07-31'
		]);

		// Hors build() : le décor pose la période sans fiche d'équipier, que l'étape `teammate` du formulaire demande
		// et dont le cas n'a pas besoin.
		PresenceLib::create($ePresence);

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-28', 'half' => 'all'],
			'to' => ['date' => '2026-08-03', 'half' => 'all']
		]);

		$this->assertTrue($fw->has('Absence::to.outOfPresence'), 'une absence qui sort de la présence est acceptée');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-20', 'half' => 'all'],
			'to' => ['date' => '2026-07-22', 'half' => 'all']
		]);

		$this->assertTrue($fw->ok(), 'une absence dans la présence est refusée');

		$eAbsencePending = $this->createAbsence($eUser, '2026-07-20 00:00:00', '2026-07-22 23:59:59', status: Absence::PENDING);

		$ePresence['to'] = '2026-07-21';
		PresenceLib::update($ePresence, ['to']);

		AbsenceLib::validate($eAbsencePending);

		$this->assertTrue($fw->has('Absence::outOfPresence'), 'une demande sortie de la présence se valide');
		$this->assertEquals(Absence::PENDING, Absence::model()->select(['status'])->whereId($eAbsencePending['id'])->get()['status'], 'la demande refusée change d\'état');

		$eAbsenceBeyond = $this->createAbsence($eUser, '2026-07-20 00:00:00', '2026-07-24 23:59:59', duration: 5.0);

		$this->assertEquals(2.0, AbsenceLib::getDaysBetween($eAbsenceBeyond, '2026-07-20', '2026-07-24'), 'les jours d\'après la sortie sont comptés');

	}

	/**
	 * Un second onglet resté ouvert refuse une demande déjà validée : la page répond par `status.decided`, et non
	 * par une erreur interne.
	 */
	public function testDecideTwiceThroughPage(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eAbsence = $this->createAbsence($this->getRequester(), '2026-07-15 00:00:00', '2026-07-15 23:59:59', status: Absence::PENDING);

		$this->asUser($this->getUser(\structure\FixtureAiLib::AUTHOR), function() use($eAbsence) {

			$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('hr/absence:doValidate', ['id' => (string)$eAbsence['id']]), 'la demande ne se valide pas');
			$this->assertEquals([\FailAction::class, 'Absence::status.decided'], $this->executePage('hr/absence:doRefuse', ['id' => (string)$eAbsence['id']]), 'le second clic ne dit pas que la demande a déjà reçu une réponse');

		});

		$this->assertEquals(Absence::VALIDATED, Absence::model()->select(['status'])->whereId($eAbsence['id'])->get()['status'], 'le second clic écrase la première réponse');

	}

	/**
	 * La durée se saisit et s'enregistre telle quelle, quelles que soient les bornes (décidé le 19/10) ;
	 * sans durée, l'absence est refusée.
	 */
	public function testDuration(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUser = $this->getRequesterSince('2026-03-01');

		$input = [
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-13', 'half' => 'all'],
			'to' => ['date' => '2026-07-17', 'half' => 'all']
		];

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser, 'unit' => Absence::DAY]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], $input + ['duration' => '']);

		$this->assertTrue($fw->has('Absence::duration.check'), 'une absence sans durée est acceptée');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser, 'unit' => Absence::DAY]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], $input + ['duration' => '5.5']);

		$this->assertTrue($fw->has('Absence::duration.tooLong'), 'cinq jours et demi sur cinq jours du calendrier');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser, 'unit' => Absence::DAY]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], $input + ['duration' => '0']);

		$this->assertTrue($fw->has('Absence::duration.positive'), 'une durée nulle est acceptée');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser, 'unit' => Absence::DAY]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], [
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-03-28', 'half' => 'all'],
			'to' => ['date' => '2026-03-30', 'half' => 'all'],
			'duration' => '3'
		]);

		$this->assertTrue($fw->ok(), 'trois jours sur le passage à l\'heure d\'été, qui fait un jour de 23 heures');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser, 'unit' => Absence::HOUR]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], [
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-15', 'time' => '14:00'],
			'to' => ['time' => '17:00'],
			'duration' => '3.5'
		]);

		$this->assertTrue($fw->has('Absence::duration.tooLong'), 'trois heures et demie de 14 h à 17 h');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $this->getRequester(), 'unit' => Absence::DAY, 'status' => Absence::VALIDATED]);
		$eAbsence->build(['type', 'from', 'to', 'duration'], $input + ['duration' => '2.5']);

		$this->assertTrue($fw->ok(), 'une durée saisie est refusée');

		AbsenceLib::create($eAbsence);

		$this->assertEquals(2.5, Absence::model()->select(['duration'])->whereId($eAbsence['id'])->get()['duration'], 'la durée saisie n\'est pas celle enregistrée');

		$eAbsence['to'] = '2026-07-20 23:59:59';

		AbsenceLib::update($eAbsence, ['to']);

		$this->assertEquals(2.5, Absence::model()->select(['duration'])->whereId($eAbsence['id'])->get()['duration'], 'la durée suit la fin changée');

	}

	/**
	 * Le panneau compose les bornes depuis la date et la demi-journée, ou la date et l'heure, et une absence à
	 * l'heure tient sur un seul jour.
	 */
	public function testBuild(): void {

		$eUser = $this->getRequesterSince('2026-07-01');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-15', 'half' => 'afternoon'],
			'to' => ['date' => '2026-07-17', 'half' => 'morning']
		]);

		$this->assertTrue($fw->ok());
		$this->assertEquals(['2026-07-15 12:00:00', '2026-07-17 12:00:00'], [$eAbsence['from'], $eAbsence['to']]);

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::HOUR,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-15', 'time' => '14:00'],
			'to' => ['time' => '17:30']
		]);

		$this->assertTrue($fw->ok());
		$this->assertEquals(['2026-07-15 14:00:00', '2026-07-15 17:30:00'], [$eAbsence['from'], $eAbsence['to']], 'la fin d\'une absence à l\'heure reprend le jour du début');

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::HOUR,
			'type' => Absence::VACATION,
			'from' => '2026-07-15 14:00:00',
			'to' => '2026-07-16 10:00:00'
		]);

		$this->assertTrue($fw->has('Absence::to.oneDay'), 'une absence à l\'heure déborde sur deux jours');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-01-01', 'half' => 'all'],
			'to' => ['date' => '2028-12-31', 'half' => 'all']
		]);

		$this->assertFalse($fw->has('Absence::to.tooLong'), 'trois ans moins une seconde');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-01-01', 'half' => 'all'],
			'to' => ['date' => '2029-01-01', 'half' => 'all']
		]);

		$this->assertTrue($fw->has('Absence::to.tooLong'), 'plus de trois ans');

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $eUser]);
		$eAbsence->build(['unit', 'type', 'from', 'to'], [
			'unit' => Absence::DAY,
			'type' => Absence::VACATION,
			'from' => '2026-01-01 00:00:00',
			'to' => '9999-12-31 23:59:59'
		]);

		$this->assertTrue($fw->has('Absence::to.tooLong'), 'jusqu\'à l\'an 9999');

	}

	/**
	 * Le planning dit d'une demi-journée qu'elle laisse le jour travaillé, et d'une journée entière qu'elle
	 * est absente.
	 */
	public function testPlanningDay(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eAbsence = new Absence(['from' => '2026-07-15 12:00:00', 'to' => '2026-07-17 12:00:00', 'unit' => Absence::DAY]);

		$day = \planning\TimeWeekLib::getDay('2026-07-15', NULL, 0.0, $eAbsence);
		$this->assertEquals([FALSE, ['from' => '12:00', 'to' => NULL]], [$day['absent'], $day['absentPart']], 'le premier jour, l\'après-midi');

		$day = \planning\TimeWeekLib::getDay('2026-07-16', NULL, 0.0, $eAbsence);
		$this->assertEquals([TRUE, NULL], [$day['absent'], $day['absentPart']], 'le jour du milieu, entier');

		$day = \planning\TimeWeekLib::getDay('2026-07-17', NULL, 0.0, $eAbsence);
		$this->assertEquals([FALSE, ['from' => NULL, 'to' => '12:00']], [$day['absent'], $day['absentPart']], 'le dernier jour, le matin');

		$day = \planning\TimeWeekLib::getDay('2026-07-18', NULL, 0.0);
		$this->assertEquals([FALSE, NULL], [$day['absent'], $day['absentPart']], 'sans absence');

	}

	/**
	 * Deux absences à l'heure le même jour : le jour montre celle qui commence le plus tard, la première que
	 * rend `fillUsers()`, rangée de la plus récente à la plus ancienne. L'autre ne paraît que dans la liste.
	 */
	public function testDateAbsentTwiceSameDay(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUser = $this->getRequester();

		$this->createAbsence($eUser, '2026-07-15 09:00:00', '2026-07-15 12:00:00', Absence::HOUR);
		$this->createAbsence($eUser, '2026-07-15 14:00:00', '2026-07-15 17:00:00', Absence::HOUR);

		$cUser = new \Collection([$eUser['id'] => new \user\User(['id' => $eUser['id']])]);
		AbsenceLib::fillUsers($this->eStructure, $cUser, '2026-07-15', '2026-07-15');

		$eAbsence = Absence::isDateAbsent($cUser[$eUser['id']]['cAbsence'], '2026-07-15');
		$this->assertEquals('2026-07-15 14:00:00', $eAbsence['from'], 'le jour ne montre pas l\'absence de l\'après-midi');

		$day = \planning\TimeWeekLib::getDay('2026-07-15', NULL, 0.0, $eAbsence);
		$this->assertEquals([FALSE, ['from' => '14:00', 'to' => '17:00']], [$day['absent'], $day['absentPart']]);

	}

	/**
	 * Une borne postée en morceaux se compose à l'unité de l'élément, celle que l'API pose et enregistre, et non
	 * à une unité postée qu'elle ne construit pas.
	 */
	public function testComposeOnElementUnit(): void {

		$fw = new \FailWatch();

		$eAbsence = new Absence(['structure' => $this->eStructure, 'user' => $this->getRequesterSince('2026-07-01'), 'unit' => Absence::DAY]);
		$eAbsence->build(['type', 'from', 'to'], [
			'unit' => Absence::HOUR,
			'type' => Absence::VACATION,
			'from' => ['date' => '2026-07-15', 'time' => '14:00'],
			'to' => ['date' => '2026-07-15', 'time' => '17:00']
		]);

		$this->assertTrue($fw->ok());
		$this->assertEquals(['2026-07-15 00:00:00', '2026-07-15 23:59:59'], [$eAbsence['from'], $eAbsence['to']]);

	}

	/**
	 * Les bandes d'absence de la frise de l'onglet Personnes, période par période : bornées à la période, et deux
	 * absences proches ne font qu'une bande. Chaque absence garde son étendue pour son lien, bande réunie ou non,
	 * élargie à un pour cent comme sa bande : la suivante chevauche alors sa fin, et son lien, posé après, la recouvre.
	 */
	public function testSegments(): void {

		$eUser = new \user\User([
			'cAbsence' => new \Collection([
				new Absence(['id' => 11, 'from' => '2026-02-02 00:00:00', 'to' => '2026-02-03 23:59:59']),
				new Absence(['id' => 12, 'from' => '2026-02-05 00:00:00', 'to' => '2026-02-05 23:59:59']),
				new Absence(['id' => 13, 'from' => '2026-06-29 00:00:00', 'to' => '2026-07-10 23:59:59'])
			])
		]);

		$eUserNone = new \user\User([
			'cAbsence' => new \Collection()
		]);

		// La présence du 1er janvier au 30 juin 2026, en jours : [0, 181].
		$eTeammate = new Teammate(['user' => $eUser, 'ranges' => [[0, 181]]]);
		$eTeammateNone = new Teammate(['user' => $eUserNone, 'ranges' => [[0, 181]]]);

		TeammateLib::fillSegments([
			'groups' => ['now' => new \Collection([1 => $eTeammate, 2 => $eTeammateNone])],
			'days' => 365
		], 2026);

		$this->assertEquals([
			[
				[32, 38.65],
				[179, 181]
			]
		], $eTeammate['segments'], 'deux absences à un jour d\'écart ne font qu\'une bande, la seconde d\'un jour large d\'un pour cent de l\'année, et la dernière s\'arrête à la fin de la période');

		$this->assertEquals([[]], $eTeammateNone['segments'], 'sans absence, la période n\'a pas de bande');

		$this->assertEquals([
			[
				[11, 32, 35.65],
				[12, 35, 38.65],
				[13, 179, 181]
			]
		], $eTeammate['segmentsAbsence'], 'dans une bande réunie, chaque absence ne garde pas sa propre étendue');

		$this->assertEquals([[]], $eTeammateNone['segmentsAbsence'], 'sans absence, la période a un lien d\'absence');

	}

	/**
	 * RH éteinte, le droit se demande avant l'état : une personne d'une autre structure est refusée comme partout
	 * ailleurs et n'apprend pas si la RH est allumée, que seul le gérant se voit refuser.
	 */
	public function testRightBeforeState(): void {

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$structure = (string)$this->eStructure['id'];

		$pages = [
			['ferme/'.$structure.'/equipe/absences', ['id' => $structure]],
			['ferme/'.$structure.'/equipe/recapitulatif', ['id' => $structure]],
			['hr/absence:create', ['structure' => $structure]]
		];

		foreach([
			'decor.f2.admin@example.org' => \NotAllowedAction::class,
			'decor.f1.admin@example.org' => \NotExpectedAction::class
		] as $email => $expected) {

			$this->asUser($this->getUser($email), function() use($pages, $email, $expected) {

				foreach($pages as [$path, $input]) {
					$this->assertEquals([$expected, NULL], $this->executePage($path, $input, 'GET'), $path.' pour '.$email);
				}

			});

		}

	}

	/**
	 * L'onglet Absences du planning est à toute personne au planning, gérant compris, et s'éteint avec la RH. Des
	 * absences de l'équipe, chacun ne reçoit que les validées du mois à venir, sans motif (décidé le
	 * 19/10).
	 */
	public function testPlanningTab(): void {

		$this->setClock('2026-06-15 10:00:00');

		$structure = (string)$this->eStructure['id'];
		$path = 'ferme/'.$structure.'/planning-v2/absences';

		$eUserRequester = $this->getRequester();
		$eUserColleague = $this->createAccess('Collègue');

		$eAbsenceOwn = $this->createAbsence($eUserRequester, '2026-07-15 00:00:00', '2026-07-15 23:59:59', status: Absence::PENDING);
		$eAbsenceColleaguePending = $this->createAbsence($eUserColleague, '2026-06-22 00:00:00', '2026-06-22 23:59:59', status: Absence::PENDING);
		$eAbsenceColleague = $this->createAbsence($eUserColleague, '2026-07-13 00:00:00', '2026-07-17 23:59:59');
		$eAbsenceColleagueLater = $this->createAbsence($eUserColleague, '2026-07-16 00:00:00', '2026-07-16 23:59:59');
		$eAbsenceColleaguePast = $this->createAbsence($eUserColleague, '2026-06-12 00:00:00', '2026-06-14 23:59:59');

		// Hors build() : le collègue arrive dans le mois, ce que le panneau accepterait aussi.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUserColleague,
			'ePresence' => new Presence(['from' => '2026-07-01', 'to' => NULL])
		]));

		$eUserOutside = $this->createAccess('Hors équipe');
		$eAbsenceOutside = $this->createAbsence($eUserOutside, '2026-06-24 00:00:00', '2026-06-24 23:59:59');

		$excluded = [
			$eAbsenceOutside['id'] => 'l\'absence d\'une personne sans période dans le mois',
			$eAbsenceColleaguePending['id'] => 'la demande d\'un collègue',
			$eAbsenceColleagueLater['id'] => 'une absence au-delà du mois',
			$eAbsenceColleaguePast['id'] => 'une absence finie'
		];

		$this->asUser($eUserRequester, function() use($path, $structure, $eUserRequester, $eUserColleague, $eAbsenceOwn, $eAbsenceColleague, $excluded) {

			$this->assertTrue(new Absence(['structure' => $this->eStructure])->assertPlanning(), 'la personne au planning n\'a pas l\'onglet');

			$data = NULL;
			$this->assertEquals([\ViewAction::class, NULL], $this->executePage($path, ['id' => $structure], 'GET', $data), 'la personne au planning n\'ouvre pas l\'onglet');
			$this->assertTrue(in_array($eAbsenceOwn['id'], $data->cAbsence->getIds(), TRUE), 'sa demande manque à l\'onglet');
			$this->assertTrue($data->canCreate, 'elle ne peut pas demander depuis l\'onglet');

			$absenceIds = $this->getUpcomingIds($data->upcoming);

			$this->assertTrue(in_array($eAbsenceColleague['id'], $absenceIds, TRUE), 'l\'absence validée d\'un collègue manque');

			foreach($excluded as $absenceId => $message) {
				$this->assertFalse(in_array($absenceId, $absenceIds, TRUE), $message.' descend au salarié');
			}

			foreach($data->upcoming['cUser'] as $eUser) {
				foreach($eUser['cAbsence'] as $eAbsence) {
					$this->assertFalse($eAbsence->offsetExists('type'), 'le motif d\'une absence descend au salarié');
				}
			}

			// Du 15/06 au 15/07, 31 jours comptés depuis aujourd'hui.
			$this->assertEquals(31, $data->upcoming['days'], 'la frise ne couvre pas le mois');
			$this->assertEquals([
				0 => ['date' => '2026-06-15', 'month' => TRUE],
				7 => ['date' => '2026-06-22', 'month' => FALSE],
				14 => ['date' => '2026-06-29', 'month' => FALSE],
				21 => ['date' => '2026-07-06', 'month' => TRUE],
				28 => ['date' => '2026-07-13', 'month' => FALSE]
			], $data->upcoming['graduation'], 'les lundis de la frise sont faux');

			$eUserColleagueUpcoming = $data->upcoming['cUser'][$eUserColleague['id']];
			$this->assertEquals([[16, 31]], $eUserColleagueUpcoming['ranges'], 'la période du collègue n\'est pas bornée au mois');
			$this->assertEquals([[[28, 31]]], $eUserColleagueUpcoming['segments'], 'l\'absence du collègue n\'est pas bornée au mois');
			$this->assertEquals([[]], $eUserColleagueUpcoming['segmentsAbsence'], 'l\'absence validée d\'un collègue s\'ouvre au salarié, qui ne peut pas la modifier');

			$eUserRequesterUpcoming = $data->upcoming['cUser'][$eUserRequester['id']];
			$this->assertEquals([[0, 31]], $eUserRequesterUpcoming['ranges'], 'la personne présente n\'a pas sa frise');
			$this->assertEquals([[]], $eUserRequesterUpcoming['segments'], 'une demande en attente se dessine sur la frise');

		});

		$this->asUser($this->getUser(\structure\FixtureAiLib::AUTHOR), function() use($path, $structure, $eUserColleague, $eAbsenceColleague, $excluded) {

			$this->assertTrue(new Absence(['structure' => $this->eStructure])->assertPlanning(), 'le gérant n\'a pas l\'onglet');

			$data = NULL;
			$this->assertEquals([\ViewAction::class, NULL], $this->executePage($path, ['id' => $structure], 'GET', $data), 'le gérant n\'ouvre pas l\'onglet');
			$absenceIds = $this->getUpcomingIds($data->upcoming);

			$this->assertTrue(in_array($eAbsenceColleague['id'], $absenceIds, TRUE), 'l\'absence validée d\'un collègue manque au gérant');

			foreach($excluded as $absenceId => $message) {
				$this->assertFalse(in_array($absenceId, $absenceIds, TRUE), $message.' descend au gérant');
			}

			$this->assertEquals([[[$eAbsenceColleague['id'], 28, 31]]], $data->upcoming['cUser'][$eUserColleague['id']]['segmentsAbsence'], 'le gérant n\'ouvre pas l\'absence du collègue depuis la frise');

		});

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$this->asUser($eUserRequester, function() use($path, $structure) {

			$this->assertFalse(new Absence(['structure' => $this->eStructure])->assertPlanning(), 'l\'onglet reste RH éteinte');
			$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage($path, ['id' => $structure], 'GET'), 'l\'onglet s\'ouvre RH éteinte');

		});

	}

	/**
	 * Les absences de la frise du mois, toutes personnes confondues.
	 */
	protected function getUpcomingIds(array $upcoming): array {

		$absenceIds = [];

		foreach($upcoming['cUser'] as $eUser) {
			$absenceIds = array_merge($absenceIds, $eUser['cAbsence']->getIds());
		}

		return $absenceIds;

	}

	/**
	 * La page réelle, sur une requête reconstruite : l'action levée dit la réponse, et un refus son nom. Même
	 * mécanique que `TeammateAi`, `executePage()`.
	 *
	 * @param \stdClass|null $data les données d'une vue, quand la page en rend une
	 * @return array{0: class-string, 1: ?string}
	 */
	private function executePage(string $path, array $input, string $method = 'POST', ?\stdClass &$data = NULL): array {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		$_REQUEST = $input;

		if($method === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), new \stdClass());
			return ['aucune action', NULL];
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return [\FailAction::class, is_string($fail) ? $fail : implode(', ', array_column($fail->format(), 0))];
		} catch(\ViewAction $action) {
			$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
			return [\ViewAction::class, NULL];
		} catch(\Action $action) {
			return [get_class($action), NULL];
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
