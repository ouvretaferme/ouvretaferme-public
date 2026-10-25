<?php
namespace hr;

/**
 * Les heures d'une semaine comparées au contrat (`OvertimeLib::fillByWeek()`) : complémentaires sous 35 h,
 * supplémentaires au-delà de 35 h quel que soit l'horaire, la part au-delà de 43 h, le solde d'un contrat
 * annualisé avec un férié, une demi-journée d'absence et un changement de contrat, ses jours non saisis, sa cible
 * et sa sortie, la période de référence, une personne sans contrat ou dont la période est close, et une personne
 * présente dans deux structures.
 *
 * La semaine est la 19e de 2026, du lundi 4 au dimanche 10 mai, le vendredi 8 mai étant férié.
 *
 * Lancement : dev/test package=hr file=OvertimeAi
 */
class OvertimeAiTest extends \dev\TransactionTest {

	const string WEEK = '2026-W19';

	/**
	 * L'attendu d'un jour d'un temps plein en 2026 : 1 607 h sur 252 jours de semaine hors fériés, moins 25 de congés.
	 */
	const float PER_DAY = 1607.0 / 227;

	protected \structure\Structure $eStructure;

	protected function getTransactionModel(): \ModuleModel {
		return new WorkingTimeModel();
	}

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

	}

	public function testWeekPartTime(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, 24.0);
		$this->createDays($eUser, ['2026-05-04' => 8.0, '2026-05-05' => 8.0, '2026-05-06' => 8.0, '2026-05-07' => 6.0]);

		$overtime = $this->fill($eUser);

		$this->assertEquals(6.0, $overtime['complementary']);
		$this->assertEquals(0.0, $overtime['extra']);
		$this->assertEquals(NULL, $overtime['balance']);

	}

	/**
	 * Un temps partiel au-delà de 35 h : ses complémentaires s'arrêtent à 35 h, le reste est supplémentaire.
	 */
	public function testWeekPartTimeBeyondLegal(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, 24.0);
		$this->createDays($eUser, ['2026-05-04' => 10.0, '2026-05-05' => 10.0, '2026-05-06' => 10.0, '2026-05-07' => 8.0]);

		$overtime = $this->fill($eUser);

		$this->assertEquals(11.0, $overtime['complementary']);
		$this->assertEquals(3.0, $overtime['extra']);

	}

	/**
	 * Un contrat à 39 h travaillé tel quel : 4 h supplémentaires (décision du 17/10), aucune
	 * complémentaire.
	 */
	public function testWeek39(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, 39.0);
		$this->createDays($eUser, ['2026-05-04' => 8.0, '2026-05-05' => 8.0, '2026-05-06' => 8.0, '2026-05-07' => 8.0, '2026-05-09' => 7.0]);

		$overtime = $this->fill($eUser);

		$this->assertEquals(NULL, $overtime['complementary']);
		$this->assertEquals(4.0, $overtime['extra']);
		$this->assertEquals(0.0, $overtime['extraHigh']);

	}

	public function testWeekHigh(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-05-04' => 10.0, '2026-05-05' => 10.0, '2026-05-06' => 10.0, '2026-05-07' => 10.0, '2026-05-09' => 6.0]);

		$overtime = $this->fill($eUser);

		$this->assertEquals(11.0, $overtime['extra']);
		$this->assertEquals(3.0, $overtime['extraHigh']);

	}

	public function testWithoutHours(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, NULL);
		$this->createDays($eUser, ['2026-05-04' => 12.0, '2026-05-05' => 12.0, '2026-05-06' => 12.0]);

		$overtime = $this->fill($eUser);

		$this->assertEquals(NULL, $overtime['extra']);
		$this->assertEquals(NULL, $overtime['complementary']);
		$this->assertEquals(NULL, $overtime['balance']);

	}

	public function testWithoutContract(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createDays($eUser, ['2026-05-04' => 12.0, '2026-05-05' => 12.0, '2026-05-06' => 12.0]);

		$overtime = $this->fill($eUser);

		$this->assertTrue($eUser['eContract']->empty());
		$this->assertEquals(NULL, $overtime['extra']);

	}

	/**
	 * Le solde ne lit que les jours d'un contrat annualisé : la semaine d'avant, sous un contrat à la semaine, n'y
	 * entre pas, et il part du début du contrat. Lundi 4 mai au jeudi 7, 1 607 h réparties sur les 227 jours de 2026
	 * (252 jours de semaine hors fériés, moins 25 de congés payés), moins la demi-journée d'absence du mardi ; le
	 * vendredi 8 est férié. Attendu trois jours et demi, travaillé 24 h.
	 */
	public function testYear(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-04-20', '2026-05-03', 35.0);
		$this->createPresence($eUser, '2026-05-04', NULL, OvertimeLib::ANNUAL, Contract::YEAR);

		$this->createDays($eUser, ['2026-04-30' => 10.0, '2026-05-04' => 8.0, '2026-05-06' => 8.0, '2026-05-07' => 8.0]);

		// Hors build() : la demi-journée se pose comme le formulaire la compose, une borne à midi.
		AbsenceLib::create(new Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => Absence::VACATION,
			'unit' => Absence::DAY,
			'status' => Absence::VALIDATED,
			'from' => '2026-05-05 '.AbsenceLib::NOON,
			'to' => '2026-05-05 '.AbsenceLib::DAY_END,
			'duration' => 0.5
		]));

		$overtime = $this->fill($eUser);

		$this->assertHours(24.0 - 3.5 * self::PER_DAY, $overtime['balance']);
		$this->assertEquals('2026-05-04', $overtime['balanceStart'], 'le solde ne part pas du début du contrat');
		$this->assertEquals(NULL, $overtime['extra']);

	}

	/**
	 * Un volume au-dessus des 1 607 h d'un temps plein attend plus chaque jour : 1 790 h font 7h53 par jour.
	 * La semaine en cours ne compte que jusqu'à aujourd'hui.
	 */
	public function testYearVolume(): void {

		$this->setClock('2026-05-07 18:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-05-04', NULL, 1790.0, Contract::YEAR);
		$this->createDays($eUser, ['2026-05-04' => 8.0, '2026-05-05' => 8.0, '2026-05-06' => 8.0, '2026-05-07' => 8.0]);

		$overtime = $this->fill($eUser);

		$this->assertHours(32.0 - 4 * 1790.0 / 227, $overtime['balance']);

	}

	/**
	 * Seul ce qui est connu compte (22/10). Le jeudi 7 mai à 9 h, deux personnes sous un contrat annualisé
	 * ouvert le lundi, comptées ensemble : chez l'une le mercredi passé sans journée sort du solde et manque, et le
	 * jeudi en cours, non saisi, n'attend rien ; chez l'autre le jeudi saisi compte, avec son attendu.
	 */
	public function testYearKnownDays(): void {

		$this->setClock('2026-05-07 09:00:00');

		$eUserWithout = $this->createUser();
		$eUserWith = $this->createUser();

		foreach([$eUserWithout, $eUserWith] as $eUser) {
			$this->createPresence($eUser, '2026-05-04', NULL, OvertimeLib::ANNUAL, Contract::YEAR);
			$this->createDays($eUser, ['2026-05-04' => 8.0, '2026-05-05' => 8.0]);
		}

		$this->createDays($eUserWith, ['2026-05-07' => 3.0]);

		$balances = OvertimeLib::getYearBalances($this->eStructure, new \Collection([$eUserWithout['id'] => $eUserWithout, $eUserWith['id'] => $eUserWith]), \Clock::date());

		$this->assertHours(16.0 - 2 * self::PER_DAY, $balances[$eUserWithout['id']]['balance'], 'un jour non saisi entre au solde');
		$this->assertEquals(1, $balances[$eUserWithout['id']]['missing'], 'le mercredi passé sans journée ne manque pas, ou le jeudi en cours manque');
		$this->assertEquals(2, $balances[$eUserWithout['id']]['days']);

		$this->assertHours(19.0 - 3 * self::PER_DAY, $balances[$eUserWith['id']]['balance'], 'le jeudi en cours, saisi, ne compte pas avec son attendu');
		$this->assertEquals(3, $balances[$eUserWith['id']]['days']);

	}

	/**
	 * Un contrat qui finit dans la période : sa cible est le volume au prorata de ses jours, ce qu'il reste à faire
	 * s'en déduit, et sa sortie se signale. Du mardi 1er septembre au mercredi 21 octobre 2026, 37 jours de semaine
	 * sans férié sur les 252 de l'année ; cinq journées de 10 h saisies, 32 jours qui manquent.
	 */
	public function testYearEnded(): void {

		$this->setClock('2026-10-22 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-09-01', '2026-10-21', OvertimeLib::ANNUAL, Contract::YEAR);
		$this->createDays($eUser, ['2026-09-07' => 10.0, '2026-09-08' => 10.0, '2026-09-09' => 10.0, '2026-09-10' => 10.0, '2026-09-11' => 10.0]);

		$balance = OvertimeLib::getYearBalances($this->eStructure, new \Collection([$eUser['id'] => $eUser]), \Clock::date())[$eUser['id']];

		$this->assertEquals('2026-09-01', $balance['start']);
		$this->assertEquals('2026-10-21', $balance['stop']);
		$this->assertEquals('2026-10-21', $balance['end'], 'la sortie ne se signale pas');
		$this->assertHours(OvertimeLib::ANNUAL * 37 / 252, $balance['target']);
		$this->assertHours(50.0, $balance['worked']);
		$this->assertHours(50.0 - 5 * self::PER_DAY, $balance['balance']);
		$this->assertHours(OvertimeLib::ANNUAL * 37 / 252 - 50.0, $balance['remaining']);
		$this->assertEquals(32, $balance['missing']);

		$balance = OvertimeLib::getYearBalances($this->eStructure, new \Collection([$eUser['id'] => $eUser]), '2026-09-30')[$eUser['id']];

		$this->assertEquals(NULL, $balance['end'], 'la sortie se signale avant d\'avoir eu lieu');

	}

	/**
	 * Un contrat annualisé sans jour coché ne fait manquer aucun jour, comme dans la synthèse
	 * (`Presence::isDateMissing()`) : son volume se répartit pourtant du lundi au vendredi.
	 */
	public function testYearWithoutDays(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();

		// Hors build() : la période ne chevauche aucune autre, ce que l'étape `to.present` vérifierait.
		PresenceLib::create(new Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => '2026-05-04',
			'to' => NULL,
			'eContract' => new Contract([
				'type' => Contract::CDI,
				'annualHours' => OvertimeLib::ANNUAL,
				'hoursPeriod' => Contract::YEAR,
				'days' => new \Set(0),
				'hasVariableDays' => FALSE
			])
		]));

		$this->createDays($eUser, ['2026-05-04' => 8.0]);

		$balance = OvertimeLib::getYearBalances($this->eStructure, new \Collection([$eUser['id'] => $eUser]), \Clock::date())[$eUser['id']];

		$this->assertEquals(0, $balance['missing'], 'un contrat sans jour coché fait manquer des jours');
		$this->assertHours(8.0 - self::PER_DAY, $balance['balance']);

	}

	/**
	 * La période de référence part du mois que règle la structure : en mai, avec un début en juin, elle court du
	 * 1er juin 2025 au 31 mai 2026.
	 */
	public function testReference(): void {

		// Le réglage se pose sur la configuration que `conf()` garde pour la durée du processus, et se remet ensuite :
		// une écriture en base ne serait pas relue par les tests qui l'ont déjà chargée.
		$eConfiguration = $this->eStructure->conf();
		$month = $eConfiguration['timeReferenceMonth'];
		$eConfiguration['timeReferenceMonth'] = 6;

		try {
			$this->assertEquals(['start' => '2025-06-01', 'stop' => '2026-05-31'], OvertimeLib::getReference($this->eStructure, '2026-05-07'));
			$this->assertEquals(['start' => '2026-06-01', 'stop' => '2027-05-31'], OvertimeLib::getReference($this->eStructure, '2026-06-01'));
		} finally {
			$eConfiguration['timeReferenceMonth'] = $month;
		}

	}

	/**
	 * Une période close avant la semaine ne donne pas son contrat : la personne n'a rien de comparé.
	 */
	public function testContractEnded(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', '2026-03-31', 24.0);
		$this->createDays($eUser, ['2026-05-04' => 10.0, '2026-05-05' => 10.0, '2026-05-06' => 10.0, '2026-05-07' => 10.0]);

		$overtime = $this->fill($eUser);

		$this->assertTrue($eUser['eContract']->empty());
		$this->assertEquals(NULL, $overtime['complementary']);
		$this->assertEquals(NULL, $overtime['extra']);

	}

	/**
	 * Une personne présente dans deux structures : ses journées de l'autre ne comptent pas.
	 */
	public function testOtherStructure(): void {

		$this->setClock('2026-05-11 10:00:00');

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-05-04' => 8.0, '2026-05-05' => 8.0]);

		$eStructureOther = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F2)
			->get();

		$this->createDays($eUser, ['2026-05-06' => 12.0, '2026-05-07' => 12.0, '2026-05-09' => 12.0], eStructure: $eStructureOther);

		$overtime = $this->fill($eUser);

		$this->assertEquals(0.0, $overtime['extra']);

	}

	protected function fill(\user\User $eUser): array {

		$cUser = new \Collection([$eUser['id'] => $eUser]);

		OvertimeLib::fillByWeek($this->eStructure, $cUser, self::WEEK);

		return $eUser['overtime'];

	}

	protected function createUser(): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Overtime',
			'lastName' => 'Test',
			'email' => 'hr-overtime-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse.
		\user\UserLib::create($eUser);

		return $eUser;

	}

	/**
	 * Le contrat d'une personne pour une semaine, que lit la semaine en champs de sa fiche : celui de sa période,
	 * vide avant qu'elle commence.
	 */
	public function testContractByWeek(): void {

		$eUser = $this->createUser();
		$this->createPresence($eUser, '2026-05-04', NULL, 35.0);

		$eContract = OvertimeLib::getContractByWeek($this->eStructure, $eUser, '2026-W19');

		$this->assertTrue($eContract->notEmpty(), 'la semaine de la période n\'a pas de contrat');
		$this->assertEquals(35.0, $eContract['weeklyHours'] ?? NULL, 'le contrat de la semaine n\'est pas celui de la période');
		$this->assertTrue(OvertimeLib::getContractByWeek($this->eStructure, $eUser, '2026-W18')->empty(), 'la semaine d\'avant la période a un contrat');

	}

	/**
	 * Une période et son contrat, un CDI du lundi au vendredi, à `$hours` heures par semaine ou par an selon
	 * `$hoursPeriod`.
	 */
	protected function createPresence(\user\User $eUser, string $from, ?string $to, ?float $hours, string $hoursPeriod = Contract::WEEK): void {

		// Hors build() : les périodes du test ne se chevauchent pas, ce que l'étape `to.present` vérifierait.
		PresenceLib::create(new Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from,
			'to' => $to,
			'eContract' => new Contract([
				'type' => Contract::CDI,
				'weeklyHours' => ($hoursPeriod === Contract::WEEK) ? $hours : NULL,
				'annualHours' => ($hoursPeriod === Contract::YEAR) ? $hours : NULL,
				'hoursPeriod' => $hoursPeriod,
				'days' => new \Set(Contract::MONDAY | Contract::TUESDAY | Contract::WEDNESDAY | Contract::THURSDAY | Contract::FRIDAY),
				'hasVariableDays' => FALSE
			])
		]));

	}

	/**
	 * Des heures calculées comparées à la minute près d'une heure, la répartition d'un volume ne tombant pas juste.
	 */
	protected function assertHours(float $expected, float $value, ?string $message = NULL): void {
		$this->assertEquals(round($expected, 4), round($value, 4), $message);
	}

	protected function createDays(\user\User $eUser, array $days, \structure\Structure $eStructure = new \structure\Structure()): void {

		if($eStructure->empty()) {
			$eStructure = $this->eStructure;
		}

		foreach($days as $date => $time) {

			// Hors build() : la date est passée par rapport à l'horloge du test, ce que `date.check` vérifierait.
			WorkingTimeLib::create(new WorkingTime([
				'structure' => $eStructure,
				'user' => $eUser,
				'date' => $date,
				'time' => $time
			]));

		}

	}

}
?>
