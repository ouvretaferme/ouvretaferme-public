<?php
namespace planning;

/**
 * La semaine d'une structure en suivi RH seul, la RH allumée sans la saisie par intervention (planche
 * rh-saisie-temps, A) : chaque jour passé se saisit sous sa pastille, un jour à venir ne s'ouvre pas, et la
 * saisie d'un jour ne répond que par ce jour et le total de la semaine.
 *
 * Le décor est celui de `HarvestFixtureTest` : le jeudi de la semaine 39, Alice seule présente, qui a le
 * planning complet.
 *
 * Lancement : dev/test package=planning file=TimeDayHrAi
 */
class TimeDayHrAiTest extends HarvestFixtureTest {

	private bool $timeBefore;

	/**
	 * Le suivi par intervention éteint et la RH allumée dans la transaction du test. Le rollback ne défait
	 * pas la mémoïsation de `conf()` : elle s'oublie à l'entrée et à la sortie, comme la configuration se remet en place.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->timeBefore = $this->getConfiguration()['time'];
		$this->getConfiguration()['time'] = FALSE;

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

	}

	public function tearDown(): void {

		$this->getConfiguration()['time'] = $this->timeBefore;

		parent::tearDown();

		\structure\FeatureLib::forget($this->eStructure);

	}

	/**
	 * La configuration que lit `Structure::hasTime()`, mémorisée par structure (`Structure::conf()`) : la page
	 * exécutée la relit par l'identifiant de la structure, et prend donc celle-ci.
	 */
	private function getConfiguration(): \structure\Configuration {

		return \structure\StructureLib::getById($this->eStructure['id'])->conf();

	}

	/**
	 * La page prépare pour chaque jour s'il se saisit, et la journée de chacun : un jour passé ou aujourd'hui a sa
	 * tuile dans la semaine, un jour à venir non, et chaque date de la semaine a sa journée, vide tant qu'elle n'est
	 * pas saisie.
	 */
	public function testDays(): void {

		[$document, $data] = $this->renderTimeDay();

		$this->assertTrue($document->getElementById('planning-time-working-day') === NULL, 'le suivi RH seul pose une journée hors de la semaine');

		foreach(week_dates(\Clock::week()) as $date) {

			$this->assertEquals($date <= \Clock::date(), $data->timeDay['days'][$date]['canWorking'], 'le '.$date.' ne dit pas s\'il se saisit');
			$this->assertTrue($data->timeDay['cWorkingTime']->offsetExists($date), 'le '.$date.' n\'a pas de journée');

			$tile = $document->querySelector('#planning-time-day-'.$date.' .planning-time-working[data-date]');
			$this->assertEquals($date <= \Clock::date(), $tile !== NULL, 'la tuile du '.$date.' ne suit pas sa saisie');

		}

	}

	/**
	 * Saisir un autre jour que celui de la fiche écrit ce jour, et la réponse ne remplace que sa pastille et le
	 * total de la semaine : il n'y a pas de fiche du jour à rafraîchir.
	 */
	public function testWorkingTime(): void {

		$monday = week_date_day(\Clock::week(), 1);

		[$data, $instructions, $action] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => '3']);

		if($this->assertTrue($data !== NULL, 'la saisie du lundi est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(3.0, $this->getWorkingTime($monday), 'la journée du lundi n\'est pas de 3 h');
		$this->assertEquals($monday, $data->date, 'la réponse ne porte pas sur le lundi');

		$selectors = array_column(array_filter($instructions, fn(array $instruction) => $instruction[0] === 'qs'), 1);
		$this->assertEquals(['#planning-time-day-'.$monday, '#planning-time-week-total'], $selectors, 'la saisie d\'un jour ne répond pas par sa seule pastille et le total de la semaine');

	}

	/**
	 * Sur la fiche d'une personne sous contrat à la semaine du lundi au vendredi, gérant et personne lisent les
	 * mêmes jours à saisir, bornés aux jours du contrat (19/10). L'horaire et la jauge ne se lisent que
	 * pour le gérant (`timeDay.p.php`) : la personne, sur sa propre fiche, n'en a aucun.
	 */
	public function testContractReader(): void {

		$eUserManager = $this->getMember();
		$eUserPlanning = $this->getMemberPlanning();

		if($this->assertDecorPresent(\structure\StructureLib::getById($this->eStructure['id'])->canManage(), 'un gérant sur la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		// Sans `build()`, qui n'aurait rien à vérifier : la fiche d'équipe ne porte que la structure et la personne,
		// toutes deux tirées du décor.
		if(\hr\TeammateLib::isTeammate($this->eStructure, $eUserPlanning) === FALSE) {
			\hr\TeammateLib::create(new \hr\Teammate([
				'structure' => $this->eStructure,
				'user' => $eUserPlanning
			]));
		}

		// La semaine passée, dont le samedi et le dimanche sont passés sans journée : à saisir sans contrat, hors des
		// jours du contrat avec lui.
		$week = toWeek(week_date_starts(\Clock::week()).' - 7 days');

		// Sans `build()`, qui vérifierait les dates d'un formulaire : la période s'ouvre la veille de la semaine, pour
		// être la dernière qui la touche quelles que soient celles du décor.
		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $eUserPlanning,
			'from' => date('Y-m-d', strtotime(week_date_starts($week).' - 1 DAY')),
			'to' => NULL,
			'eContract' => new \hr\Contract([
				'type' => \hr\Contract::CDI,
				'weeklyHours' => 35.0,
				'hoursPeriod' => \hr\Contract::WEEK,
				'days' => new \Set(\hr\Contract::MONDAY | \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY),
				'hasVariableDays' => FALSE
			])
		]));

		[, $data] = $this->renderTimeDay(['user' => $eUserPlanning['id']], $week);

		if($this->assertDecorPresent($data->timeDay['days'][week_date_day($week, 7)]['unfilled'], 'le dimanche de la semaine passée est sans journée') === FALSE) {
			return;
		}

		$this->assertEquals(35.0, $data->entry['hours'], 'le gérant ne lit pas l\'horaire du contrat');

		$weekdays = [];

		foreach($data->timeDay['days'] as $date => $day) {
			$weekdays[$date] = ($day['unfilled'] and (int)date('N', strtotime($date)) <= 5);
		}

		$this->assertEquals($weekdays, $data->entry['warnings'], 'les jours à saisir lus par le gérant ne se bornent pas aux jours du contrat');

		$entryManager = $data->entry;

		try {

			$this->connect($eUserPlanning);

			[, $data] = $this->renderTimeDay(['user' => $eUserPlanning['id']], $week);

			$this->assertEquals([NULL, NULL, NULL], [$data->entry['hours'], $data->entry['done'], $data->entry['todo']], 'la personne lit l\'horaire de son contrat');
			$this->assertEquals([$entryManager['warnings'], $entryManager['missing']], [$data->entry['warnings'], $data->entry['missing']], 'la personne et son gérant ne lisent pas les mêmes jours à saisir');

		} finally {
			$this->connect($eUserManager);
		}

	}

	/**
	 * Un collègue en planning complet, sans gérer la structure, qui ouvre la fiche d'une personne sous contrat n'en
	 * lit pas les jours : ils diraient un temps partiel. Les jours à saisir sont pour lui ceux de la semaine, samedi
	 * et dimanche passés compris. Le décor n'a pas ce rôle : il se pose en mémoire, sur le membre de Bruno, sans
	 * rien écrire en base (précédent : `MenuDataAi::connectAdmin()`).
	 */
	public function testContractColleague(): void {

		$eUserManager = $this->getMember();
		$eUserPlanning = $this->getMemberPlanning();

		$week = toWeek(week_date_starts(\Clock::week()).' - 7 days');

		// Sans `build()`, qui vérifierait les dates d'un formulaire : la période du gérant s'ouvre la veille de la
		// semaine, pour être la dernière qui la touche quelles que soient celles du décor.
		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $eUserManager,
			'from' => date('Y-m-d', strtotime(week_date_starts($week).' - 1 DAY')),
			'to' => NULL,
			'eContract' => new \hr\Contract([
				'type' => \hr\Contract::CDI,
				'weeklyHours' => 35.0,
				'hoursPeriod' => \hr\Contract::WEEK,
				'days' => new \Set(\hr\Contract::MONDAY | \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY),
				'hasVariableDays' => FALSE
			])
		]));

		$eMember = \structure\Member::model()
			->select(\structure\Member::getSelection())
			->whereStructure($this->eStructure)
			->whereUser($eUserPlanning)
			->get();

		$cache = new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
		$cacheInitial = $cache->getValue();

		try {

			$this->connect($eUserPlanning);

			$eMember['role'] = clone $eMember['role'];
			$eMember['role']['admin'] = FALSE;
			$eMember['role']['planning'] = \structure\Role::FULL;

			$cache->setValue(NULL, [
				$eUserPlanning['id'] => new \Collection([$this->eStructure['id'] => $eMember]),
			]);

			$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

			if($this->assertDecorPresent(($eStructure->canPlanning() and $eStructure->canManage() === FALSE), 'Bruno en planning complet sans gérer la structure') === FALSE) {
				return;
			}

			[, $data] = $this->renderTimeDay(['user' => $eUserManager['id']], $week);

			$unfilled = array_map(fn(array $day) => $day['unfilled'], $data->timeDay['days']);

			$this->assertTrue($unfilled[week_date_day($week, 7)], 'décor attendu : le dimanche de la semaine passée est sans journée');
			$this->assertEquals($unfilled, $data->entry['warnings'], 'le collègue lit les jours du contrat d\'une autre personne');
			$this->assertEquals([NULL, NULL, NULL], [$data->entry['hours'], $data->entry['done'], $data->entry['todo']], 'le collègue lit l\'horaire d\'un contrat');

		} finally {
			$cache->setValue(NULL, $cacheInitial);
			$this->connect($eUserManager);
		}

	}

	private function connect(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();
		$this->forgetAccountingYear();

	}

	private function getWorkingTime(string $date): ?float {

		$eWorkingTime = \hr\WorkingTime::model()
			->select(['time'])
			->whereStructure($this->eStructure)
			->whereUser($this->getMember())
			->whereDate($date)
			->get();

		return $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

	}

}
?>
