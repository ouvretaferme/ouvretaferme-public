<?php
namespace hr;

/**
 * Le récapitulatif du mois (`RecapLib`, `page/recap.p.php`) : qui en est, ce qu'il compte pour chacun, une absence
 * à cheval sur deux mois, les jours qui manquent, un à un et comptés pour l'équipe, le rappel du contrat, l'export,
 * le premier jour de la période, et les refus, d'un membre qui ne gère pas la structure et d'un mois hors des
 * bornes. La saisie d'un jour depuis le récapitulatif passe par la fiche de temps (`origin=recap`), qui refuse
 * aussi ce membre avant d'écrire.
 *
 * L'horloge est au mercredi 15 juillet 2026. Mai 2026 compte quatre jours fériés, les vendredis 1er et 8, le jeudi
 * 14 et le lundi 25.
 *
 * Lancement : dev/test package=hr file=RecapAi
 */
class RecapAiTest extends \dev\TransactionTest {

	const string CLOCK = '2026-07-15 10:00:00';

	protected \structure\Structure $eStructure;

	private static array $views = [];

	protected function getTransactionModel(): \ModuleModel {
		return new WorkingTimeModel();
	}

	/**
	 * La RH allumée dans la transaction du test. Le rollback ne défait pas la mémoïsation de `conf()`, qui garde
	 * ce qu'elle a lu dans la transaction : les fonctionnalités s'oublient à l'entrée et à la sortie, la
	 * configuration à la sortie, la période réglée par `doUpdatePeriod` restant sinon celle des tests suivants.
	 */
	public function setUp(): void {

		parent::setUp();

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

	}

	public function tearDown(): void {

		parent::tearDown();

		\structure\FeatureLib::forget($this->eStructure);
		\structure\ConfigurationLib::forget($this->eStructure);

	}

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

	}

	/**
	 * Les personnes du mois : une présence qui le touche, même d'un compte fermé depuis, et une journée sans
	 * présence. Une présence finie avant le mois n'en est pas, et le panneau refuse de l'ouvrir.
	 */
	public function testUsers(): void {

		$this->setClock(self::CLOCK);

		$eUserPresent = $this->createUser('Présente');
		$this->createPresence($eUserPresent, '2026-01-01', NULL, 35.0);

		$eUserClosed = $this->createUser('Fermée');
		$this->createPresence($eUserClosed, '2026-01-01', '2026-05-20', 35.0);

		$eUserClosed['status'] = \user\User::CLOSED;
		// Hors build() : la fermeture d'un compte passe par le compte lui-même, que le décor n'ouvre pas.
		\user\UserLib::update($eUserClosed, ['status']);

		$eUserAccess = $this->createUser('Sans présence');
		$this->createDays($eUserAccess, ['2026-05-12' => 6.0]);

		$eUserGone = $this->createUser('Partie');
		$this->createPresence($eUserGone, '2026-01-01', '2026-03-31', 35.0);

		$cUser = RecapLib::getUsers($this->eStructure, '2026-05');

		$this->assertTrue($cUser->offsetExists($eUserPresent['id']), 'une personne présente dans le mois manque');
		$this->assertTrue($cUser->offsetExists($eUserClosed['id']), 'un compte fermé depuis manque au mois où il a travaillé');
		$this->assertTrue($cUser->offsetExists($eUserAccess['id']), 'une journée sans présence manque');
		$this->assertFalse($cUser->offsetExists($eUserGone['id']), 'une présence finie avant le mois en est');

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUserGone, $eUserClosed) {

			$action = $this->executePage('hr/recap:user', ['id' => $this->eStructure['id'], 'month' => '2026-05', 'user' => $eUserGone['id']], 'GET');
			$this->assertTrue($action instanceof \NotExpectedAction, 'le panneau ouvre une personne hors du mois');

			$action = $this->executePage('hr/recap:user', ['id' => $this->eStructure['id'], 'month' => '2026-05', 'user' => $eUserClosed['id']], 'GET');
			$this->assertTrue($action instanceof \ViewAction, 'le panneau n\'ouvre pas un compte fermé du mois');

		});

	}

	/**
	 * Les journées saisies du mois, un férié travaillé, et les heures supplémentaires des semaines qui finissent dans
	 * le mois : la 18e, du 27 avril au 3 mai, en est avec sa journée d'avril.
	 */
	public function testFillByMonth(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Mois');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->createDays($eUser, ['2026-04-30' => 8.0, '2026-05-14' => 4.0, '2026-05-18' => 10.0, '2026-05-19' => 10.0, '2026-05-20' => 10.0, '2026-05-21' => 10.0]);

		$cUser = new \Collection([$eUser['id'] => $eUser]);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-05');

		$recap = $eUser['recap'];

		$this->assertEquals(5, $recap['days'], 'les journées saisies du mois');
		$this->assertEquals(44.0, $recap['time'], 'la durée des journées saisies du mois');
		$this->assertEquals(1, $recap['holidays'], 'le 14 mai travaillé, et lui seul');
		$this->assertEquals(5.0, $recap['extra'], 'les heures au-delà de 35 h de la 21e semaine');
		$this->assertEquals(0.0, $recap['absent']);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-05', withMissing: TRUE);

		// L'horloge est en juillet : tous les jours de mai que le contrat attend sont passés, et chacun est saisi,
		// férié ou non saisi.
		$this->assertEquals(13, $eUser['missing'], 'les jours non saisis de mai');

		$total = RecapLib::getTotal($cUser);

		$this->assertEquals(44.0, $total['time']);
		$this->assertEquals(5.0, $total['extra']);

	}

	/**
	 * Un contrat annualisé ouvert le lundi 4 mai : la synthèse de mai porte son solde arrêté à la fin du mois, qui ne
	 * compte que les quatre journées saisies, et la colonne des heures supplémentaires devient celle du solde tant
	 * qu'aucun contrat à la semaine n'en a.
	 */
	public function testYear(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Annualisé');

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
				'days' => new \Set(Contract::MONDAY | Contract::TUESDAY | Contract::WEDNESDAY | Contract::THURSDAY | Contract::FRIDAY),
				'hasVariableDays' => FALSE
			])
		]));

		$this->createDays($eUser, ['2026-05-18' => 10.0, '2026-05-19' => 10.0, '2026-05-20' => 10.0, '2026-05-21' => 10.0]);

		$cUser = new \Collection([$eUser['id'] => $eUser]);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-05');

		$year = $eUser['recap']['year'];

		$this->assertEquals(NULL, $eUser['recap']['extra'], 'un contrat annualisé compte des heures supplémentaires à la semaine');
		$this->assertEquals('2026-05-04', $year['start']);
		$this->assertEquals(40.0, $year['worked'], 'le solde lit des journées après la fin du mois');
		$this->assertEquals(round(40.0 - 4 * 1607.0 / 227, 4), round($year['balance'], 4), 'le solde attend des jours non saisis');
		$this->assertEquals(13, $year['missing'], 'les jours de mai non saisis, fériés des 8, 14 et 25 retirés');

		$columns = RecapLib::getColumns($cUser);

		$this->assertTrue($columns['balance'], 'la colonne ne devient pas celle du solde');

		$eUserWeek = $this->createUser('À la semaine');
		$this->createPresence($eUserWeek, '2026-01-01', NULL, 35.0);
		$this->createDays($eUserWeek, ['2026-05-18' => 10.0, '2026-05-19' => 10.0, '2026-05-20' => 10.0, '2026-05-21' => 10.0]);

		$cUser = new \Collection([$eUser['id'] => $eUser, $eUserWeek['id'] => $eUserWeek]);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-05');

		$columns = RecapLib::getColumns($cUser);

		$this->assertTrue($columns['extra']);
		$this->assertFalse($columns['balance'], 'la colonne devient celle du solde à côté d\'heures supplémentaires à la semaine');

	}

	/**
	 * Une semaine d'absence du lundi 29 juin au vendredi 3 juillet : deux jours en juin, trois en juillet.
	 */
	public function testAbsenceAcrossMonths(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Absente');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->createAbsence($eUser, '2026-06-29 00:00:00', '2026-07-03 23:59:59', 5.0);

		foreach(['2026-06' => 2.0, '2026-07' => 3.0] as $month => $days) {

			$cUser = new \Collection([$eUser['id'] => $eUser]);

			RecapLib::fillByMonth($this->eStructure, $cUser, $month);

			$this->assertEquals($days, $eUser['recap']['absences'][Absence::VACATION]['days'], 'les jours d\'absence en '.$month);
			$this->assertEquals($days, $eUser['recap']['absent'], 'la somme des jours d\'absence en '.$month);

		}

	}

	/**
	 * Une période qui part du 20 : octobre va du 20 septembre au 19 octobre, ses heures supplémentaires sont celles
	 * des semaines dont le dimanche y tombe, et le mois en cours passe au suivant le 20.
	 */
	public function testPeriod(): void {

		$this->setClock('2026-10-25 10:00:00');

		$eUser = $this->createUser('Période');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-09-19' => 7.0, '2026-09-21' => 7.0, '2026-10-19' => 7.0, '2026-10-20' => 7.0]);

		$this->withConfiguration('timePeriodDay', 20, function() use($eUser) {

			$this->assertEquals('2026-09-20', RecapLib::getStart($this->eStructure, '2026-10'));
			$this->assertEquals('2026-10-19', RecapLib::getStop($this->eStructure, '2026-10'));
			$this->assertEquals('2025-12-20', RecapLib::getStart($this->eStructure, '2026-01'), 'la période de janvier part de décembre');

			$this->assertEquals('2026-10', RecapLib::getMonth($this->eStructure, '2026-10-19'));
			$this->assertEquals('2026-11', RecapLib::getMonth($this->eStructure, '2026-10-20'), 'le 20 ouvre la période suivante');
			$this->assertTrue(RecapLib::isMonthValid($this->eStructure, '2026-11'), 'la période en cours ne s\'affiche pas');
			$this->assertFalse(RecapLib::isMonthValid($this->eStructure, '2026-12'), 'une période à venir s\'affiche');

			// Les dimanches du 20 septembre au 18 octobre ; celle du dimanche 25 octobre va à novembre.
			$this->assertEquals(['2026-W38', '2026-W39', '2026-W40', '2026-W41', '2026-W42'], RecapLib::getWeeksOvertime($this->eStructure, '2026-10'));
			$this->assertEquals('2026-W43', first(RecapLib::getWeeksOvertime($this->eStructure, '2026-11')));

			$this->assertEquals([], RecapLib::getHolidays($this->eStructure, '2026-10'));
			$this->assertEquals(['2026-11-01', '2026-11-11'], RecapLib::getHolidays($this->eStructure, '2026-11'));

			$cUser = new \Collection([$eUser['id'] => $eUser]);

			RecapLib::fillByMonth($this->eStructure, $cUser, '2026-10');

			$this->assertEquals(2, $eUser['recap']['days'], 'les journées saisies du 21 septembre et du 19 octobre');

			// La fiche de temps, qui dit si le jour s'ouvre, lit la personne connectée.
			$weeks = [];

			$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser, &$weeks) {
				$weeks = $this->getByUser($eUser, '2026-10');
			});

			$this->assertFalse($weeks['2026-W38']['days']['2026-09-19']['inPeriod'], 'le 19 septembre est dans octobre');
			$this->assertTrue($weeks['2026-W38']['days']['2026-09-20']['inPeriod'], 'le 20 septembre n\'est pas dans octobre');
			$this->assertTrue($weeks['2026-W43']['days']['2026-10-19']['inPeriod'], 'le 19 octobre n\'est pas dans octobre');
			$this->assertFalse($weeks['2026-W43']['days']['2026-10-20']['inPeriod'], 'le 20 octobre est dans octobre');

		});

	}

	/**
	 * Une journée saisie sur un jour du récapitulatif répond par sa semaine, où le jour de l'horloge n'est pas à
	 * venir et le lendemain l'est. Une semaine sans journée ni absence n'a pas de ligne de journées à écrire.
	 */
	public function testWorkingTimeFuture(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Saisie du jour');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$weeks = $this->getByUser($eUser, '2026-07');

			$this->assertFalse($weeks['2026-W29']['values'], 'une semaine sans journée ni absence a des valeurs');

			$action = $this->executePage('planning/timeDay:doWorkingTime', [
				'id' => $this->eStructure['id'],
				'user' => $eUser['id'],
				'week' => '2026-W29',
				'date' => '2026-07-13',
				'time' => '6',
				'origin' => RecapLib::ORIGIN
			], 'POST');

			$this->assertTrue($action instanceof \ViewAction, 'la journée saisie depuis le récapitulatif est refusée');

			[$data] = $this->runView('doWorkingTimeRecap', $action, 'timeDay', 'planning');

			$days = $data->weeks['2026-W29']['days'];

			$this->assertTrue($data->weeks['2026-W29']['values'], 'la semaine de la journée saisie n\'a pas de valeurs');
			$this->assertFalse($days['2026-07-15']['future'], 'le jour de l\'horloge est à venir');
			$this->assertTrue($days['2026-07-16']['future'], 'le lendemain n\'est pas à venir');
			$this->assertEquals(6.0, $days['2026-07-13']['eWorkingTime']['time'], 'la journée saisie manque à sa semaine');

		});

		$this->assertEquals(6.0, $this->getTime($eUser, '2026-07-13'));

	}

	/**
	 * La RH ouvre les heures de travail du jour sans le temps des interventions : temps de travail éteint, le
	 * récapitulatif compte toujours les journées en base, ses jours s'ouvrent, et ses colonnes d'heures restent.
	 */
	public function testUserWithoutTime(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Sans suivi');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-05-18' => 10.0, '2026-05-19' => 10.0, '2026-05-20' => 10.0, '2026-05-21' => 10.0]);
		$this->createAbsence($eUser, '2026-05-26 00:00:00', '2026-05-26 23:59:59', 1.0);

		$cUser = new \Collection([$eUser['id'] => $eUser]);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser, $cUser) {

			RecapLib::fillByMonth($this->eStructure, $cUser, '2026-05');

			$this->withConfiguration('time', FALSE, function() use($eUser, $cUser) {

				$this->assertFalse($this->eStructure->hasTime(), 'le temps de travail est allumé');
				$this->assertTrue($this->eStructure->hasWorkingTime(), 'la RH n\'ouvre pas les heures du jour');

				$weeks = $this->getByUser($eUser, '2026-05');

				$this->assertEquals(40.0, $weeks['2026-W21']['time'], 'la 21e semaine ne compte pas ses journées');
				$this->assertTrue($weeks['2026-W21']['days']['2026-05-18']['eWorkingTime']->notEmpty(), 'la journée du 18 mai n\'est pas lue');
				$this->assertTrue($weeks['2026-W21']['days']['2026-05-18']['canWorking'], 'la journée du 18 mai ne s\'ouvre pas');
				$this->assertTrue($weeks['2026-W22']['days']['2026-05-26']['absent'], 'l\'absence du 26 mai disparait');

				$columns = RecapLib::getColumns($cUser);

				$this->assertTrue($columns['extra'], 'les heures au-delà de 35 h de la 21e semaine disparaissent');
				$this->assertEquals([Absence::VACATION => FALSE], $columns['types'], 'les motifs d\'absence du mois');

			});

		});

	}

	/**
	 * Un jour passé que le contrat attend, la personne présente, sans journée saisie ni absence, manque : le
	 * vendredi 3 juillet. Ne manquent ni le mercredi 1er saisi, ni le jeudi 2 d'absence, ni le samedi 4 hors du
	 * contrat, ni le 14 juillet férié, ni le jour de l'horloge. Une période qui commence le 8 ne fait pas manquer le
	 * 7, et des jours variables n'en attendent aucun. Le tableau de l'équipe compte les mêmes jours : les 3, 6 à 10
	 * et 13 juillet, puis les 8 à 10 et 13 pour une arrivée le 8.
	 */
	public function testMissing(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Manquante');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-07-01' => 7.0]);
		$this->createAbsence($eUser, '2026-07-02 00:00:00', '2026-07-02 23:59:59', 1.0);

		$eUserLate = $this->createUser('Arrivée');
		$this->createPresence($eUserLate, '2026-07-08', NULL, 35.0);

		$eUserVariable = $this->createUser('Variable');
		$this->createPresence($eUserVariable, '2026-01-01', NULL, 35.0, variable: TRUE);

		$weeks = [];

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser, $eUserLate, $eUserVariable, &$weeks) {
			foreach([$eUser, $eUserLate, $eUserVariable] as $eUserRecap) {
				$weeks[$eUserRecap['id']] = $this->getByUser($eUserRecap, '2026-07');
			}
		});

		$missing = fn(\user\User $eUserRecap, string $week, string $date) => $weeks[$eUserRecap['id']][$week]['days'][$date]['missing'];

		$this->assertTrue($missing($eUser, '2026-W27', '2026-07-03'), 'le vendredi 3 juillet sans journée ne manque pas');

		$this->assertFalse($missing($eUser, '2026-W27', '2026-07-01'), 'le mercredi 1er saisi manque');
		$this->assertFalse($missing($eUser, '2026-W27', '2026-07-02'), 'le jeudi 2 d\'absence manque');
		$this->assertFalse($missing($eUser, '2026-W27', '2026-07-04'), 'le samedi 4 hors du contrat manque');
		$this->assertFalse($missing($eUser, '2026-W29', '2026-07-14'), 'le 14 juillet férié manque');
		$this->assertFalse($missing($eUser, '2026-W29', '2026-07-15'), 'le jour de l\'horloge manque');
		$this->assertFalse($missing($eUser, '2026-W29', '2026-07-16'), 'un jour à venir manque');

		$this->assertFalse($missing($eUserLate, '2026-W28', '2026-07-07'), 'un jour avant la période de présence manque');
		$this->assertTrue($missing($eUserLate, '2026-W28', '2026-07-08'), 'le premier jour de la période de présence ne manque pas');

		$this->assertFalse($missing($eUserVariable, '2026-W27', '2026-07-03'), 'un contrat à jours variables attend un jour');

		$cUser = new \Collection([$eUser['id'] => $eUser, $eUserLate['id'] => $eUserLate, $eUserVariable['id'] => $eUserVariable]);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-07', withMissing: TRUE);

		$this->assertEquals(7, $eUser['missing'], 'les jours non saisis de juillet');
		$this->assertEquals(4, $eUserLate['missing'], 'les jours non saisis depuis l\'arrivée');
		$this->assertEquals(0, $eUserVariable['missing'], 'des jours variables comptent des jours non saisis');
		$this->assertEquals(11, RecapLib::getTotal($cUser)['missing'], 'les jours non saisis de l\'équipe');

		$count = 0;

		foreach($weeks[$eUser['id']] as $week) {
			$count += count(array_filter($week['days'], fn(array $day) => $day['missing']));
		}

		$this->assertEquals($eUser['missing'], $count, 'le tableau et le détail ne comptent pas les mêmes jours');

	}

	/**
	 * Le rappel d'une personne : ses périodes de présence qui touchent le mois, la plus récente d'abord, chacune avec
	 * son contrat, sans celle qui finit avant le mois. Le panneau les rend.
	 */
	public function testPresences(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Rappel');
		$this->createPresence($eUser, '2026-01-01', '2026-03-31', 35.0);
		$this->createPresence($eUser, '2026-04-01', '2026-05-10', 35.0);
		$this->createPresence($eUser, '2026-05-11', NULL, 28.0);

		$cPresence = PresenceLib::getByUserBetween($this->eStructure, $eUser, '2026-05-01', '2026-05-31');

		$this->assertEquals(['2026-05-11', '2026-04-01'], array_values($cPresence->getColumn('from')), 'les périodes de mai, la plus récente d\'abord');
		$this->assertEquals(28.0, $cPresence->first()['eContract']['weeklyHours'], 'le contrat de la période');

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$action = $this->executePage('hr/recap:user', ['id' => $this->eStructure['id'], 'month' => '2026-05', 'user' => $eUser['id']], 'GET');

			[$data] = $this->runView('user', $action, 'recap', 'hr');

			$this->assertEquals(2, $data->cPresence->count(), 'le panneau ne reçoit pas les périodes du mois');
			$this->assertTrue($data->eMember->empty(), 'une personne sans accès à la structure a un rôle');

		});

	}

	/**
	 * Un membre au planning qui ne gère pas la structure n'ouvre ni l'écran, ni le panneau d'une personne, ni
	 * l'export.
	 */
	public function testManageRefused(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Gérée');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->asUser($this->getUser('decor.f1.planning@example.org'), function() use($eUser) {

			$input = ['id' => $this->eStructure['id'], 'month' => '2026-05', 'user' => $eUser['id']];

			foreach([$this->getPath(), 'hr/recap:user', 'hr/recap:export'] as $path) {

				$action = $this->executePage($path, $input, 'GET');

				$this->assertTrue($action instanceof \NotAllowedAction, $path.' est ouvert à un membre qui ne gère pas la structure');

			}

		});

	}

	/**
	 * La saisie d'un jour depuis le récapitulatif : le gérant la fait et reçoit le mois relu. Un membre au planning
	 * qui saisit sa propre journée depuis sa fiche est refusé dès qu'il se dit au récapitulatif, avant d'écrire.
	 */
	public function testWorkingTimeRecap(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Saisie');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$input = [
			'id' => $this->eStructure['id'],
			'week' => '2026-W29',
			'date' => '2026-07-13',
			'time' => '6',
			'origin' => RecapLib::ORIGIN
		];

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser, $input) {

			$action = $this->executePage('planning/timeDay:doWorkingTime', ['user' => $eUser['id']] + $input, 'POST');

			$this->assertTrue($action instanceof \ViewAction, 'la journée saisie depuis le récapitulatif est refusée au gérant');

			[$data, $instructions] = $this->runView('doWorkingTimeRecap', $action, 'timeDay', 'planning');

			$this->assertEquals(6.0, $data->eUserTime['recap']['time'], 'les chiffres rendus ne comptent pas la journée saisie');
			$this->assertTrue(str_contains(json_encode($instructions), json_encode('#'.RecapUi::getWeekId('2026-W29'))), 'la réponse ne remplace pas la semaine du jour');

		});

		$this->assertEquals(6.0, $this->getTime($eUser, '2026-07-13'));

		$eUserPlanning = $this->getPlanningMember();

		$this->asUser($eUserPlanning, function() use($eUserPlanning, $input) {

			$action = $this->executePage('planning/timeDay:doWorkingTime', ['user' => $eUserPlanning['id']] + $input, 'POST');

			$this->assertTrue($action instanceof \NotAllowedAction, 'un membre qui ne gère pas la structure saisit depuis le récapitulatif');
			$this->assertEquals(NULL, $this->getTime($eUserPlanning, '2026-07-13'), 'la journée refusée est écrite');

			unset($input['origin']);

			$action = $this->executePage('planning/timeDay:doWorkingTime', ['user' => $eUserPlanning['id']] + $input, 'POST');

			$this->assertTrue($action instanceof \ViewAction, 'le membre ne saisit plus sa journée depuis sa fiche');
			$this->assertEquals(6.0, $this->getTime($eUserPlanning, '2026-07-13'));

		});

	}

	/**
	 * Un salarié au planning complet lit la journée d'un collègue sans l'écrire : elle lui est refusée avant
	 * d'écrire, et sa fiche ne l'ouvre pas, quand le gérant écrit pour toute l'équipe.
	 */
	public function testWorkingTimeOfColleague(): void {

		$this->setClock(self::CLOCK);

		$eUserEmployee = $this->createEmployee('Salarié');
		$eUserColleague = $this->createEmployee('Collègue');

		PresenceLib::fillUsers($this->eStructure, new \Collection([$eUserColleague['id'] => $eUserColleague]));

		$input = [
			'id' => $this->eStructure['id'],
			'user' => $eUserColleague['id'],
			'week' => '2026-W29',
			'date' => '2026-07-13',
			'time' => '6'
		];

		$this->asUser($eUserEmployee, function() use($eUserColleague, $input) {

			$this->assertTrue(new WorkingTime(['structure' => $this->eStructure, 'user' => $eUserColleague])->canRead(), 'un salarié ne lit plus la journée d\'un collègue');
			$this->assertFalse(\planning\TimeDayLib::canWorking($this->eStructure, $eUserColleague, '2026-07-13'), 'la journée d\'un collègue s\'ouvre à un salarié');

			$action = $this->executePage('planning/timeDay:doWorkingTime', $input, 'POST');

			$this->assertTrue($action instanceof \NotAllowedAction, 'un salarié écrit la journée d\'un collègue');

		});

		$this->assertEquals(NULL, $this->getTime($eUserColleague, '2026-07-13'), 'la journée refusée est écrite');

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUserColleague, $input) {

			$this->assertTrue(\planning\TimeDayLib::canWorking($this->eStructure, $eUserColleague, '2026-07-13'), 'la journée d\'un salarié ne s\'ouvre pas au gérant');

			$action = $this->executePage('planning/timeDay:doWorkingTime', $input, 'POST');

			$this->assertTrue($action instanceof \ViewAction, 'le gérant n\'écrit pas la journée d\'un salarié');

		});

		$this->assertEquals(6.0, $this->getTime($eUserColleague, '2026-07-13'));

	}

	/**
	 * Hors de ses périodes de présence, une personne n'a pas de journée à saisir : la fiche ne l'ouvre pas et
	 * l'écriture est refusée. Ce qui est déjà en base hors présence reste, sans compter au mois, à son détail ni à
	 * l'export.
	 */
	public function testWorkingTimeOutOfPresence(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Sortie');
		$this->createPresence($eUser, '2026-01-01', '2026-07-14', 35.0);

		PresenceLib::fillUsers($this->eStructure, new \Collection([$eUser['id'] => $eUser]));

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$this->assertTrue(\planning\TimeDayLib::canWorking($this->eStructure, $eUser, '2026-07-14'), 'un jour de présence ne s\'ouvre pas');
			$this->assertFalse(\planning\TimeDayLib::canWorking($this->eStructure, $eUser, '2026-07-15'), 'un jour après la sortie s\'ouvre');

			$fw = new \FailWatch();

			$action = $this->executePage('planning/timeDay:doWorkingTime', [
				'id' => $this->eStructure['id'],
				'user' => $eUser['id'],
				'week' => '2026-W29',
				'date' => '2026-07-15',
				'time' => '6',
				'origin' => RecapLib::ORIGIN
			], 'POST');

			$this->assertTrue($action instanceof \FailAction, 'une journée après la sortie est acceptée');
			$this->assertTrue($fw->has('WorkingTime::date.outOfPresence'), 'le refus ne dit pas que le jour est hors présence');

		});

		$this->assertEquals(NULL, $this->getTime($eUser, '2026-07-15'), 'la journée refusée est écrite');

		$this->createDays($eUser, ['2026-07-13' => 7.0, '2026-07-15' => 6.0]);
		$this->createAbsence($eUser, '2026-07-16 00:00:00', '2026-07-16 23:59:59', 1.0);

		$cUser = new \Collection([$eUser['id'] => $eUser]);

		RecapLib::fillByMonth($this->eStructure, $cUser, '2026-07');

		$this->assertEquals(1, $eUser['recap']['days'], 'une journée hors présence compte au mois');
		$this->assertEquals(7.0, $eUser['recap']['time'], 'la durée d\'une journée hors présence compte au mois');
		$this->assertEquals(0.0, $eUser['recap']['absent'], 'une absence hors présence compte au mois');
		$this->assertEquals([], $eUser['recap']['absences'], 'le motif d\'une absence hors présence se montre au mois');

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$weeks = $this->getByUser($eUser, '2026-07');

			$this->assertFalse($weeks['2026-W29']['days']['2026-07-15']['inPresence'], 'le 15, après la sortie, est dans la présence');
			$this->assertTrue($weeks['2026-W29']['days']['2026-07-15']['eWorkingTime']->empty(), 'la journée hors présence se montre');
			$this->assertFalse($weeks['2026-W29']['days']['2026-07-15']['canWorking'], 'le 15, après la sortie, s\'ouvre');
			$this->assertFalse($weeks['2026-W29']['days']['2026-07-16']['absent'], 'l\'absence hors présence se montre');

			$action = $this->executePage('hr/recap:export', ['id' => $this->eStructure['id'], 'month' => '2026-07'], 'GET');

			$lines = new \ReflectionProperty(\CsvAction::class, 'lines')->getValue($action);

			$line = first(array_filter($lines, fn(array $line) => $line[2] === 'Récapitulatif Sortie'));

			$this->assertEquals([1, 7.0], [$line[3], $line[4]], 'l\'export compte une journée hors présence');

		});

	}

	/**
	 * RH éteinte, la saisie depuis le récapitulatif répond d'abord sur le droit, puis sur l'état : un membre qui ne
	 * gère pas la structure est refusé comme tel, le gérant parce que la RH est éteinte. Le temps de travail reste
	 * allumé, pour que la journée elle-même reste ouverte (`hasWorkingTime()`).
	 */
	public function testWorkingTimeRecapRightBeforeState(): void {

		$this->setClock(self::CLOCK);

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$eUser = $this->createUser('Droit');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$eUserPlanning = $this->getPlanningMember();

		$input = [
			'id' => $this->eStructure['id'],
			'week' => '2026-W29',
			'date' => '2026-07-13',
			'time' => '6',
			'origin' => RecapLib::ORIGIN
		];

		$this->withConfiguration('time', TRUE, function() use($eUser, $eUserPlanning, $input) {

			foreach([
				[$eUserPlanning, $eUserPlanning, \NotAllowedAction::class],
				[$this->getUser('decor.f1.admin@example.org'), $eUser, \NotExpectedAction::class]
			] as [$eUserOnline, $eUserTime, $expected]) {

				$this->asUser($eUserOnline, function() use($eUserOnline, $eUserTime, $input, $expected) {

					$action = $this->executePage('planning/timeDay:doWorkingTime', ['user' => $eUserTime['id']] + $input, 'POST');

					$this->assertEquals($expected, get_class($action), 'RH éteinte, la réponse à '.$eUserOnline['email']);
					$this->assertEquals(NULL, $this->getTime($eUserTime, '2026-07-13'), 'la journée refusée est écrite');

				});

			}

		});

	}

	/**
	 * Depuis le récapitulatif, la journée écrite est celle de la fiche : une date hors de la semaine postée est
	 * refusée par le constructeur avant d'écrire. Le jour à venir, refusé par le schéma, ne s'éprouve pas ici : sa
	 * borne se calcule à la construction du model, sur l'horloge d'avant `setClock()`.
	 */
	public function testWorkingTimeRecapDate(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Bornée');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$action = $this->executePage('planning/timeDay:doWorkingTime', [
				'id' => $this->eStructure['id'],
				'user' => $eUser['id'],
				'week' => '2026-W29',
				'date' => '2026-07-06',
				'time' => '6',
				'origin' => RecapLib::ORIGIN
			], 'POST');

			$this->assertTrue($action instanceof \NotExpectedAction, 'une date hors de la semaine de la fiche est acceptée');

		});

		$this->assertEquals(NULL, $this->getTime($eUser, '2026-07-06'), 'la journée hors de la semaine est écrite');

	}

	/**
	 * Un mois mal formé ou à venir est refusé par l'écran, le panneau d'une personne et l'export.
	 */
	public function testMonthRefused(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Mois refusé');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			foreach(['0000-01', '2026-08'] as $month) {

				$input = ['id' => $this->eStructure['id'], 'month' => $month, 'user' => $eUser['id']];

				foreach([$this->getPath(), 'hr/recap:user', 'hr/recap:export'] as $path) {

					$action = $this->executePage($path, $input, 'GET');

					$this->assertTrue($action instanceof \NotExpectedAction, $path.' accepte le mois '.$month);

				}

			}

			// Un rendu qui lève ferait tomber la page ou le panneau : la vue se joue, sans juger son balisage.
			foreach([
				[$this->getPath(), [], '/ferme/{id}/equipe/recapitulatif'],
				['hr/recap:user', ['id' => $this->eStructure['id'], 'user' => $eUser['id']], 'user']
			] as [$path, $input, $view]) {

				$action = $this->executePage($path, $input + ['month' => '2026-05'], 'GET');

				$this->assertTrue($action instanceof \ViewAction, $path.' ne s\'ouvre pas sur mai');

				$this->runView($view, $action, 'recap', 'hr');

			}

		});

	}

	/**
	 * Le premier jour de la période et le début de la période de référence des contrats annualisés se règlent depuis
	 * le récapitulatif, par le gérant seul, et seulement le suivi RH allumé. L'écriture passe par `build()`, qui
	 * refuse un jour que tous les mois n'ont pas.
	 */
	public function testUpdatePeriod(): void {

		$this->setClock(self::CLOCK);

		$input = ['id' => $this->eStructure->conf()['id']];

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($input) {

			$action = $this->executePage('structure/configuration:updatePeriod', $input, 'GET');

			$this->assertTrue($action instanceof \ViewAction, 'le réglage de la période ne s\'ouvre pas');

			$this->runView('updatePeriod', $action, 'configuration', 'structure');

			$action = $this->executePage('structure/configuration:doUpdatePeriod', $input + ['timePeriodDay' => '20', 'timeReferenceMonth' => '6'], 'POST');

			$this->assertTrue($action instanceof \ReloadAction, 'le 20 est refusé');
			$this->assertEquals(20, $this->getPeriodDay(), 'le premier jour n\'est pas écrit');
			$this->assertEquals(6, $this->getConfiguration('timeReferenceMonth'), 'le début de la période de référence n\'est pas écrit');

			$action = $this->executePage('structure/configuration:doUpdatePeriod', $input + ['timePeriodDay' => '29', 'timeReferenceMonth' => '6'], 'POST');

			$this->assertTrue($action instanceof \FailAction, 'le 29, qui manque à février, est accepté');
			$this->assertEquals(20, $this->getPeriodDay(), 'un jour refusé est écrit');

		});

		$this->asUser($this->getUser('decor.f1.planning@example.org'), function() use($input) {

			foreach([['updatePeriod', 'GET'], ['doUpdatePeriod', 'POST']] as [$page, $method]) {

				$action = $this->executePage('structure/configuration:'.$page, $input + ['timePeriodDay' => '10'], $method);

				$this->assertTrue($action instanceof \NotAllowedAction, $page.' est ouvert à un membre qui ne gère pas la structure');

			}

		});

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		\structure\FeatureLib::forget($this->eStructure);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($input) {

			foreach([['updatePeriod', 'GET'], ['doUpdatePeriod', 'POST']] as [$page, $method]) {

				$action = $this->executePage('structure/configuration:'.$page, $input + ['timePeriodDay' => '10'], $method);

				$this->assertTrue($action instanceof \NotExpectedAction, $page.' est ouvert sans le suivi RH');

			}

		});

		$this->assertEquals(20, $this->getPeriodDay(), 'le premier jour est écrit sans le suivi RH');

	}

	/**
	 * L'export, une ligne par personne, avec le nom tel quel, que le tableur neutralise s'il commence comme une
	 * formule (`\util\CsvLib::toCsv()`).
	 */
	public function testExport(): void {

		$this->setClock(self::CLOCK);

		$eUser = $this->createUser('Export', '=HYPERLINK("http://example.org")');
		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);
		$this->createDays($eUser, ['2026-05-04' => 7.5, '2026-05-05' => 7.25]);
		$this->createAbsence($eUser, '2026-05-18 00:00:00', '2026-05-19 23:59:59', 2.0);

		$this->asUser($this->getUser('decor.f1.admin@example.org'), function() use($eUser) {

			$action = $this->executePage('hr/recap:export', ['id' => $this->eStructure['id'], 'month' => '2026-05'], 'GET');

			$this->assertTrue($action instanceof \CsvAction, 'l\'export ne rend pas de fichier');

			$lines = new \ReflectionProperty(\CsvAction::class, 'lines')->getValue($action);

			$line = first(array_filter($lines, fn(array $line) => $line[2] === '=HYPERLINK("http://example.org") Export'));

			// Le fichier va à la paie : les heures au-delà de 43 h et le compteur d'un annualisé y ont leur colonne,
			// vide pour une personne qui n'en a pas, et chaque ligne a autant de valeurs que l'en-tête.
			$head = $lines[0];

			$this->assertTrue(in_array('Dont au-delà de 43 h', $head, TRUE), 'les heures au-delà de 43 h manquent à l\'export');
			$this->assertTrue(in_array('Compteur annualisé', $head, TRUE), 'le compteur d\'un annualisé manque à l\'export');

			$this->assertTrue($line !== NULL, 'la personne manque à l\'export, ou son nom y est encodé');

			// Les bornes de la période, nom, jours, heures, supplémentaires, puis les fériés travaillés, avant les
			// absences.
			$this->assertEquals(['Du', 'Au', 'Nom'], array_slice($head, 0, 3), 'la période n\'ouvre pas la ligne');
			$this->assertEquals(['01/05/2026', '31/05/2026'], array_slice($line, 0, 2), 'les bornes de mai');
			$this->assertEquals(2, $line[3]);
			$this->assertEquals(14.75, $line[4]);
			$this->assertEquals(2.0, last($line), 'les jours de congés');
			$this->assertEquals(count($head), count($line), 'la ligne ne suit pas l\'en-tête');
			$this->assertEquals('', $line[array_search('Compteur annualisé', $head, TRUE)], 'un contrat à la semaine porte un compteur');

			$csv = \util\CsvLib::toCsv([[$line[2]]], ',');

			$this->assertTrue(str_starts_with($csv, '"\'=HYPERLINK'), 'le nom qui commence comme une formule n\'est pas neutralisé');

		});

	}

	/**
	 * Le détail d'une personne comme le panneau le prépare, mois rempli puis périodes de présence du mois.
	 */
	protected function getByUser(\user\User $eUser, string $month): array {

		RecapLib::fillByMonth($this->eStructure, new \Collection([$eUser['id'] => $eUser]), $month);

		$cPresence = PresenceLib::getByUserBetween($this->eStructure, $eUser, RecapLib::getStart($this->eStructure, $month), RecapLib::getStop($this->eStructure, $month));

		return RecapLib::getByUser($this->eStructure, $eUser, $month, $cPresence);

	}

	protected function getPath(): string {
		return 'ferme/'.$this->eStructure['id'].'/equipe/recapitulatif';
	}

	protected function getUser(string $email): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

	}

	/**
	 * Bruno, au planning sans gérer la structure, avec une période qui couvre le mois depuis son premier jour, pour
	 * que sa fiche de temps l'accepte et qu'une journée du début du mois tombe dans sa présence.
	 */
	protected function getPlanningMember(): \user\User {

		$eUser = $this->getUser('decor.f1.planning@example.org');

		$start = substr(\Clock::date(), 0, 8).'01';

		if(PresenceLib::isPresentThroughout($this->eStructure, $eUser, $start, \Clock::date()) === FALSE) {
			$this->createPresence($eUser, $start, NULL, 35.0);
		}

		return $eUser;

	}

	/**
	 * Un salarié de la structure, au rôle permanent de `RoleLib` (planning complet sans gestion), présent depuis
	 * janvier, comme le pose `InviteLib::accept()` puis l'équipe.
	 */
	protected function createEmployee(string $lastName): \user\User {

		$eUser = $this->createUser($lastName);

		$eRole = \structure\Role::model()
			->select(['id', 'admin', 'planning', 'fqn'])
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_ASC])
			->getCollection()
			->find(fn(\structure\Role $eRole) => ($eRole['admin'] === FALSE and $eRole['fqn'] === NULL and $eRole['planning'] === \structure\Role::FULL), limit: 1, default: new \structure\Role());

		if($eRole->empty()) {
			throw new \Exception('La structure de référence n\'a pas de rôle de salarié');
		}

		// Hors build(), comme le pose InviteLib::accept().
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => $eRole,
			'status' => \structure\Member::IN
		]));

		$this->createPresence($eUser, '2026-01-01', NULL, 35.0);

		return $eUser;

	}

	protected function createUser(string $lastName, string $firstName = 'Récapitulatif'): \user\User {

		$eUser = new \user\User([
			'firstName' => $firstName,
			'lastName' => $lastName,
			'email' => 'hr-recap-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse, et le test
		// veut un prénom qui commence comme une formule, que le formulaire ne refuserait pas plus.
		\user\UserLib::create($eUser);

		return $eUser;

	}

	/**
	 * Une période et son contrat, un CDI du lundi au vendredi, ou à jours variables.
	 */
	protected function createPresence(\user\User $eUser, string $from, ?string $to, float $weeklyHours, bool $variable = FALSE): void {

		// Hors build() : les périodes du test ne se chevauchent pas, ce que l'étape `to.present` vérifierait.
		PresenceLib::create(new Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from,
			'to' => $to,
			'eContract' => new Contract([
				'type' => Contract::CDI,
				'weeklyHours' => $weeklyHours,
				'hoursPeriod' => Contract::WEEK,
				'days' => $variable ? NULL : new \Set(Contract::MONDAY | Contract::TUESDAY | Contract::WEDNESDAY | Contract::THURSDAY | Contract::FRIDAY),
				'hasVariableDays' => $variable
			])
		]));

	}

	protected function createDays(\user\User $eUser, array $days): void {

		foreach($days as $date => $time) {

			// Hors build() : la date est passée par rapport à l'horloge du test, ce que `date.check` vérifierait.
			WorkingTimeLib::create(new WorkingTime([
				'structure' => $this->eStructure,
				'user' => $eUser,
				'date' => $date,
				'time' => $time
			]));

		}

	}

	protected function createAbsence(\user\User $eUser, string $from, string $to, float $duration): void {

		// Hors build() : le décor veut une absence déjà validée, que le panneau ne pose qu'en demande, et pose sa
		// durée en jours travaillés, que le panneau calcule avant d'écrire.
		AbsenceLib::create(new Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => Absence::VACATION,
			'unit' => Absence::DAY,
			'status' => Absence::VALIDATED,
			'from' => $from,
			'to' => $to,
			'duration' => $duration
		]));

	}

	protected function getPeriodDay(): int {
		return $this->getConfiguration('timePeriodDay');
	}

	protected function getConfiguration(string $property): mixed {

		return \structure\Configuration::model()
			->whereId($this->eStructure->conf()['id'])
			->getValue($property);

	}

	protected function getTime(\user\User $eUser, string $date): ?float {

		return WorkingTime::model()
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->whereDate($date)
			->getValue('time');

	}

	/**
	 * La configuration est gardée en cache pour le processus (`Structure::conf()`) : le test la change sur place
	 * et la rend telle quelle, même quand le cas échoue.
	 */
	protected function withConfiguration(string $property, bool|int $value, \Closure $test): void {

		$eConfiguration = $this->eStructure->conf();
		$valueInitial = $eConfiguration[$property];

		$eConfiguration[$property] = $value;

		try {
			$test();
		} finally {
			$eConfiguration[$property] = $valueInitial;
		}

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
	 * Forme de `HarvestFixture::executePage()` : la page exécutée par le routeur, avec son constructeur commun, et
	 * l'action qu'elle lève.
	 */
	protected function executePage(string $path, array $input, string $method): ?\Action {

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		// Chaque route tourne dans une requête neuve : un état statique que la fiche de temps garnit pour sa vue ne
		// doit pas rester d'un appel à l'autre (`EntryFixture::executePageAction()`).
		\work\WorkSetting::$mainActions = NULL;

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];

		$_REQUEST = $input;

		if($method === 'GET') {
			[$_GET, $_POST] = [$input, []];
		} else {
			[$_GET, $_POST] = [[], $input];
		}

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Forme de `HarvestFixture::runView()` : la vue jouée sur les données de l'action, ses données et ses
	 * instructions. Chaque fichier de vue se charge une fois dans un registre vide, gardé ensuite pour lui, le
	 * registre du processus refusant deux vues du même nom.
	 */
	protected function runView(string $name, \ViewAction $action, string $file, string $package): array {

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$views = new \ReflectionProperty(\View::class, 'views');

		$uri = $package.'/'.$file;

		if(isset(self::$views[$uri]) === FALSE) {
			$views->setValue(NULL, []);
			require \Package::getFileFromUri($uri, 'view');
			self::$views[$uri] = $views->getValue();
		}

		$views->setValue(NULL, self::$views[$uri]);

		$view = \View::get($name, $action);
		$template = new \ReflectionMethod(\View::class, 'getTemplate')->invoke($view);
		$template->setData($data);

		ob_start();
		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);
		ob_get_clean();

		return [$data, $template->formatInstructions()];

	}

}
?>
