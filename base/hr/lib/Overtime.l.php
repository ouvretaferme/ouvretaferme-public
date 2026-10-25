<?php
namespace hr;

/**
 * Les heures d'une semaine comparées au contrat, que la fiche de l'équipe montre au gérant
 * (`planning/timeDay`), et que le récapitulatif reprend. Seule la durée de la journée (`WorkingTime.time`)
 * compte comme travail : une absence ou un jour férié n'en est pas.
 *
 * - Un contrat à la semaine : les heures supplémentaires au-delà de la durée légale, quel que soit l'horaire
 *   du contrat (décidé le 17/10, un contrat à 39 h en a donc 4 chaque semaine), dont celles au-delà
 *   de 43 h, et sous 35 h les heures complémentaires, entre l'horaire du contrat et la durée légale.
 * - Un contrat annualisé : un solde, le travail moins l'attendu, sur la période de référence de la structure
 *   (`Configuration.timeReferenceMonth`, `getReference()`), qui ne lit que ce qui est connu (`getYearBalances()`).
 *
 * Rien ne se calcule sans contrat ni sans horaire. Aucune majoration n'est calculée : le logiciel compte les
 * heures, il ne les valorise pas.
 */
class OvertimeLib {

	/**
	 * La durée légale du travail, en heures par semaine.
	 */
	const float LEGAL = 35.0;

	/**
	 * Le seuil au-delà duquel les heures supplémentaires changent de majoration.
	 */
	const float HIGH = 43.0;

	/**
	 * Le volume annuel d'un temps plein, qu'un contrat annualisé porte d'ordinaire (`Contract.annualHours`).
	 */
	const float ANNUAL = 1607.0;

	/**
	 * Les semaines de congés payés qu'un volume annuel porte déjà : elles sortent des jours sur lesquels il se répartit.
	 */
	const int PAID_LEAVE_WEEKS = 5;

	/**
	 * Pose sur chaque personne de `$cUser` son contrat de la semaine (`eContract`, vide sans contrat) et ses heures
	 * (`overtime`) :
	 * - `complementary`, `extra`, `extraHigh` : les heures de la semaine d'un contrat à la semaine, NULL sinon ;
	 * - `balance` : avec `$withBalance`, le solde d'un contrat annualisé à la fin de la semaine, NULL sinon, depuis
	 *   `balanceStart` (`getYearBalances()`). Le récapitulatif s'en passe : il lit le solde une fois, à la fin de sa
	 *   période, et non à chacune de ses semaines.
	 *
	 * Le contrat de la semaine est celui de la période qui la finit : un contrat qui change en cours de semaine
	 * compte la semaine entière au nouveau.
	 */
	public static function fillByWeek(\structure\Structure $eStructure, \Collection $cUser, string $week, bool $withBalance = TRUE): void {

		$weekStart = week_date_starts($week);
		$weekStop = week_date_ends($week);

		$cContract = ContractLib::getByUsers($eStructure, $cUser, $weekStart, $weekStop);

		$contracts = array_fill_keys($cUser->getIds(), []);

		foreach($cContract as $eContract) {
			$contracts[$eContract['presence']['user']['id']][] = $eContract;
		}

		foreach($cUser as $eUser) {
			$eUser['eContract'] = self::getContractOn($contracts[$eUser['id']], $weekStart, $weekStop);
		}

		$weeks = self::getWeekSums($eStructure, $cUser, $weekStart, $weekStop);

		$cUserYear = $cUser->find(fn(\user\User $eUser) => (
			$eUser['eContract']->notEmpty() and
			$eUser['eContract']['hoursPeriod'] === Contract::YEAR
		));

		if(
			$withBalance and
			$cUserYear->notEmpty()
		) {
			$balances = self::getYearBalances($eStructure, $cUserYear, min($weekStop, \Clock::date()));
		} else {
			$balances = [];
		}

		foreach($cUser as $eUser) {

			$eContract = $eUser['eContract'];
			$worked = $weeks[$eUser['id']];

			$overtime = [
				'complementary' => NULL,
				'extra' => NULL,
				'extraHigh' => NULL,
				'balance' => NULL,
				'balanceStart' => NULL
			];

			if($eContract->notEmpty()) {

				switch($eContract['hoursPeriod']) {

					case Contract::WEEK :

						if($eContract['weeklyHours'] === NULL) {
							break;
						}

						$overtime['extra'] = max(0.0, $worked - self::LEGAL);
						$overtime['extraHigh'] = max(0.0, $worked - self::HIGH);

						if($eContract['weeklyHours'] < self::LEGAL) {
							$overtime['complementary'] = max(0.0, min($worked, self::LEGAL) - $eContract['weeklyHours']);
						}

						break;

					case Contract::YEAR :

						// Pas de solde sans `$withBalance`, ni pour un contrat qui commence après la date lue.
						if(array_key_exists($eUser['id'], $balances)) {
							$overtime['balance'] = $balances[$eUser['id']]['balance'];
							$overtime['balanceStart'] = $balances[$eUser['id']]['start'];
						}

						break;

				}

			}

			$eUser['overtime'] = $overtime;

		}

	}

	/**
	 * Un contrat à la semaine de l'équipe est sous la durée légale : les heures complémentaires ont leur colonne.
	 * `$cUser` porte `overtime` (`fillByWeek()`).
	 */
	public static function hasComplementary(\Collection $cUser): bool {

		foreach($cUser as $eUser) {

			if($eUser['overtime']['complementary'] !== NULL) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * La période de référence qui contient `$date` : douze mois depuis le premier jour du mois choisi par la
	 * structure (`Configuration.timeReferenceMonth`).
	 *
	 * @return array{start: string, stop: string}
	 */
	public static function getReference(\structure\Structure $eStructure, string $date): array {

		$month = $eStructure->conf()['timeReferenceMonth'];
		$year = (int)substr($date, 0, 4);

		if((int)substr($date, 5, 2) < $month) {
			$year--;
		}

		$start = sprintf('%04d-%02d-01', $year, $month);

		return [
			'start' => $start,
			'stop' => date('Y-m-d', strtotime($start.' + 1 YEAR - 1 DAY'))
		];

	}

	/**
	 * La semaine de chaque personne, en une requête pour l'équipe : le temps de ses journées dans ses périodes de
	 * présence. Les heures ne se comptent qu'au contrat d'une période, et une journée restée en base avant
	 * l'arrivée ou après un départ n'est pas payée (`Presence::isDateWorkable()`).
	 *
	 * @return array<int, float> par personne
	 */
	protected static function getWeekSums(\structure\Structure $eStructure, \Collection $cUser, string $start, string $stop): array {

		$cWorkingTime = WorkingTime::model()
			->select([
				'user',
				'worked' => new \Sql('SUM(m1.time)', 'float')
			])
			->join(Presence::model(), 'm2.structure = m1.structure AND m2.user = m1.user AND m2.from <= m1.date AND (m2.to IS NULL OR m2.to >= m1.date)')
			->where('m1.structure', $eStructure)
			->where('m1.user', 'IN', $cUser)
			->where('m1.date', 'BETWEEN', new \Sql(WorkingTime::model()->format($start).' AND '.WorkingTime::model()->format($stop)))
			->group(new \Sql('m1.user'))
			->getCollection(NULL, NULL, 'user');

		$weeks = array_fill_keys($cUser->getIds(), 0.0);

		foreach($cWorkingTime as $userId => $eWorkingTime) {
			$weeks[$userId] = $eWorkingTime['worked'];
		}

		return $weeks;

	}

	/**
	 * Le solde de chaque personne de `$cUser` sous contrat annualisé, sur la période de référence qui contient
	 * `$date` (`getReference()`), arrêté à `$date`. Le volume du contrat (`annualHours`) se répartit sur ses jours
	 * de la période de référence, fériés retirés, moins ses congés payés (`PAID_LEAVE_WEEKS` semaines de ses jours) :
	 * 1 607 h sur les 252 jours de semaine de 2026 moins 25 font 7h05 par jour. Des jours variables se répartissent
	 * du lundi au vendredi, faute de mieux.
	 *
	 * Seul ce qui est connu compte (22/10) : un jour du contrat n'attend ses heures que s'il est saisi ou
	 * couvert par une absence validée, qui en retire ce qu'elle couvre. Un jour passé sans l'un ni l'autre sort du
	 * solde et se compte à part (`missing`), comme ceux de la synthèse (`Presence::isDateMissing()`).
	 *
	 * Par personne qui a un contrat annualisé dans la période, jusqu'à `$date` :
	 * - `start`, `stop` : du début de son premier contrat annualisé à la fin du dernier, bornés à la période ;
	 * - `end` : le dernier jour d'un contrat qui finit dans la période, NULL sinon ;
	 * - `target` : ce que ses contrats attendent sur la période entière, au prorata de leurs jours ;
	 * - `worked`, `expected`, `balance` : le temps de ses journées, l'attendu des jours connus, et leur différence ;
	 * - `remaining` : la cible moins le travail ;
	 * - `days`, `missing` : ses jours du contrat saisis, et ceux qui manquent.
	 *
	 * @return array<int, array> par personne
	 */
	public static function getYearBalances(\structure\Structure $eStructure, \Collection $cUser, string $date): array {

		$reference = self::getReference($eStructure, $date);

		$contracts = [];

		foreach(ContractLib::getByUsers($eStructure, $cUser, $reference['start'], $date) as $eContract) {

			if(
				$eContract['hoursPeriod'] === Contract::YEAR and
				$eContract['annualHours'] !== NULL
			) {
				$contracts[$eContract['presence']['user']['id']][] = $eContract;
			}

		}

		if($contracts === []) {
			return [];
		}

		$cWorkingTime = WorkingTime::model()
			->select(['user', 'date', 'time'])
			->whereStructure($eStructure)
			->whereUser('IN', array_keys($contracts))
			->whereDate('BETWEEN', new \Sql(WorkingTime::model()->format($reference['start']).' AND '.WorkingTime::model()->format($date)))
			->getCollection();

		$times = array_fill_keys(array_keys($contracts), []);

		foreach($cWorkingTime as $eWorkingTime) {
			$times[$eWorkingTime['user']['id']][$eWorkingTime['date']] = $eWorkingTime['time'];
		}

		$absences = array_fill_keys(array_keys($contracts), []);

		foreach(AbsenceLib::getValidatedBetween($eStructure, $reference['start'], $date, withType: FALSE) as $eAbsence) {

			if(array_key_exists($eAbsence['user']['id'], $absences)) {
				$absences[$eAbsence['user']['id']][] = $eAbsence;
			}

		}

		$holidays = array_merge(
			HolidayLib::getByYear((int)substr($reference['start'], 0, 4)),
			HolidayLib::getByYear((int)substr($reference['stop'], 0, 4))
		);

		$today = \Clock::date();

		$balances = [];

		foreach($contracts as $userId => $cContractUser) {

			$balance = [
				'start' => NULL,
				'stop' => NULL,
				'end' => NULL,
				'target' => 0.0,
				'worked' => 0.0,
				'expected' => 0.0,
				'balance' => 0.0,
				'remaining' => 0.0,
				'days' => 0,
				'missing' => 0
			];

			foreach($cContractUser as $eContract) {

				$eContractDays = self::getContractDays($eContract);

				$from = max($reference['start'], $eContract['presence']['from']);
				$to = ($eContract['presence']['to'] === NULL) ? $reference['stop'] : min($reference['stop'], $eContract['presence']['to']);

				$daysYear = self::countDays($eContractDays, $reference['start'], $reference['stop'], $holidays);
				$perDay = $eContract['annualHours'] / ($daysYear - self::PAID_LEAVE_WEEKS * count($eContractDays['days']->values()));

				$balance['start'] = min($balance['start'] ?? $from, $from);
				$balance['stop'] = max($balance['stop'] ?? $to, $to);
				$balance['target'] += $eContract['annualHours'] * self::countDays($eContractDays, $from, $to, $holidays) / $daysYear;

				for($day = $from; $day <= min($to, $date); $day = date('Y-m-d', strtotime($day.' + 1 DAY'))) {

					$time = array_key_exists($day, $times[$userId]) ? $times[$userId][$day] : NULL;

					if($time !== NULL) {
						$balance['worked'] += $time;
					}

					if(
						$eContractDays->isDayWorked($day) === FALSE or
						in_array($day, $holidays, TRUE)
					) {
						continue;
					}

					$absent = self::getAbsent($absences[$userId], $day, $perDay);

					if($time !== NULL) {
						$balance['days']++;
					}

					if($time !== NULL or $absent > 0.0) {
						$balance['expected'] += $perDay - $absent;
					// Un jour manque aux mêmes conditions que dans la synthèse (`Presence::isDateMissing()`) : un contrat
					// sans jours fixes cochés n'en attend aucun, même si son volume se répartit du lundi au vendredi.
					} else if(
						$day < $today and
						$eContract['hasVariableDays'] === FALSE and
						$eContract['days'] !== NULL and
						$eContract['days']->get() !== 0
					) {
						$balance['missing']++;
					}

				}

			}

			// Des contrats qui finissent tous avant la fin de la période, et avant la date lue : la personne en est sortie.
			if(
				$balance['stop'] < $reference['stop'] and
				$balance['stop'] <= $date
			) {
				$balance['end'] = $balance['stop'];
			}

			$balance['balance'] = $balance['worked'] - $balance['expected'];
			$balance['remaining'] = $balance['target'] - $balance['worked'];

			$balances[$userId] = $balance;

		}

		return $balances;

	}

	/**
	 * Le contrat dont les jours se lisent : le sien, ou le lundi au vendredi sans contrat, ou pour des jours variables
	 * ou absents.
	 */
	public static function getContractDays(Contract $eContract): Contract {

		if($eContract->empty() or $eContract['hasVariableDays'] or $eContract['days'] === NULL or $eContract['days']->get() === 0) {
			return ContractLib::getNew();
		}

		return $eContract;

	}

	/**
	 * Les jours du contrat de `$start` à `$stop`, fériés retirés.
	 *
	 * @param string[] $holidays
	 */
	protected static function countDays(Contract $eContractDays, string $start, string $stop, array $holidays): int {

		$days = 0;

		for($day = $start; $day <= $stop; $day = date('Y-m-d', strtotime($day.' + 1 DAY'))) {

			if(
				$eContractDays->isDayWorked($day) and
				in_array($day, $holidays, TRUE) === FALSE
			) {
				$days++;
			}

		}

		return $days;

	}

	/**
	 * Ce que les absences retirent d'un jour qui attend `$perDay` heures.
	 *
	 * @param Absence[] $absences
	 */
	protected static function getAbsent(array $absences, string $date, float $perDay): float {

		foreach($absences as $eAbsence) {

			if(
				$date < substr($eAbsence['from'], 0, 10) or
				$date > substr($eAbsence['to'], 0, 10)
			) {
				continue;
			}

			$part = $eAbsence->getPartOn($date);

			if($part === NULL) {
				return $perDay;
			}

			return match($eAbsence['unit']) {
				Absence::DAY => $perDay / 2,
				Absence::HOUR => min($perDay, WorkingTime::getRange($part['from'], $part['to']))
			};

		}

		return 0.0;

	}

	/**
	 * Le contrat de la semaine d'une personne, vide sans contrat : celui de la période qui la finit, comme pour ses
	 * heures (`fillByWeek()`).
	 */
	public static function getContractByWeek(\structure\Structure $eStructure, \user\User $eUser, string $week): Contract {

		$weekStart = week_date_starts($week);
		$weekStop = week_date_ends($week);

		$contracts = [];

		foreach(ContractLib::getByUsers($eStructure, new \Collection([$eUser['id'] => $eUser]), $weekStart, $weekStop) as $eContract) {
			$contracts[] = $eContract;
		}

		return self::getContractOn($contracts, $weekStart, $weekStop);

	}

	/**
	 * Le contrat de la dernière période qui touche la semaine, commencée au plus tard `$stop` et finie au plus tôt
	 * `$start` : une période qui finit en cours de semaine garde son contrat pour la semaine, une période close
	 * avant elle n'en donne aucun.
	 *
	 * @param Contract[] $contracts
	 */
	protected static function getContractOn(array $contracts, string $start, string $stop): Contract {

		$eContractOn = new Contract();

		foreach($contracts as $eContract) {

			if(
				$eContract['presence']['from'] <= $stop and
				($eContract['presence']['to'] === NULL or $eContract['presence']['to'] >= $start) and
				($eContractOn->empty() or $eContract['presence']['from'] > $eContractOn['presence']['from'])
			) {
				$eContractOn = $eContract;
			}

		}

		return $eContractOn;

	}

}
?>
