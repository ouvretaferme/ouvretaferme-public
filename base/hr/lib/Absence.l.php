<?php
namespace hr;

class AbsenceLib extends AbsenceCrud {

	/**
	 * La borne d'une demi-journée, fin du matin et début de l'après-midi (`hr.yml`, commentaire de `unit`).
	 */
	const string NOON = '12:00:00';

	const string DAY_START = '00:00:00';
	const string DAY_END = '23:59:59';

	/**
	 * La liste de l'API et des décors : une absence à la journée, dont les bornes arrivent écrites en entier.
	 * La page ajoute l'unité, et la personne pour le seul gérant (`absence.p.php`). La durée se saisit : elle
	 * dépend du temps partiel et d'autres règles que les bornes ne disent pas (décidé le 19/10).
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		if($origin === 'api') {
			return array_merge(['user'], self::getPropertiesUpdate());
		}

		return self::getPropertiesUpdate();

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['type', 'from', 'to', 'duration'];
	}

	/**
	 * Une borne d'absence en date et heure, depuis les morceaux du formulaire. Une absence à la journée commence
	 * le matin ou à midi et finit à midi ou le soir ; une absence à l'heure prend l'heure saisie. Rend NULL
	 * quand un morceau manque, que le contrôle de la borne refuse.
	 */
	public static function compose(?string $unit, ?string $date, ?string $half, ?string $time, bool $start): ?string {

		if($date === NULL or $date === '') {
			return NULL;
		}

		return match($unit) {
			Absence::DAY => $date.' '.match($half) {
				'afternoon' => $start ? self::NOON : self::DAY_END,
				'morning' => $start ? self::DAY_START : self::NOON,
				default => $start ? self::DAY_START : self::DAY_END
			},
			Absence::HOUR => ($time === NULL or $time === '') ? NULL : $date.' '.$time.':00',
			default => NULL
		};

	}

	public static function create(Absence $e): void {

		$e->expects(['structure', 'user', 'duration']);

		// La page et l'API posent le statut, seules à savoir qui saisit. Une absence montée à la main, décor
		// ou test, est une absence saisie par le gérant, comme toutes celles d'avant les demandes.
		$e->add([
			'status' => Absence::VALIDATED,
			'unit' => Absence::DAY
		]);

		Absence::model()->beginTransaction();

			parent::create($e);

			if($e['status'] === Absence::PENDING) {
				NotificationLib::absenceRequested($e);
			}

		Absence::model()->commit();

	}

	/**
	 * Les jours travaillés que l'absence `$e`, à la journée, prend de `$start` à `$stop`. Un jour compte s'il est
	 * dans une période de présence, un jour du contrat de cette période (du lundi au vendredi sans contrat ou avec
	 * des jours variables, `ContractLib::getNew()`) et n'est pas férié ; une demi-journée compte pour moitié. Bornée
	 * à un mois, elle rend la part d'une absence qui en déborde.
	 */
	public static function getDaysBetween(Absence $e, string $start, string $stop): float {

		$e->expects(['structure', 'user', 'from', 'to']);

		$cPresence = Presence::model()
			->select([
				'id', 'from', 'to',
				'eContract' => Contract::model()
					->select(['days', 'hasVariableDays'])
					->delegateElement('presence')
			])
			->whereStructure($e['structure'])
			->whereUser($e['user'])
			->whereFrom('<=', $stop)
			->or(
				fn() => $this->whereTo('>=', $start),
				fn() => $this->whereTo(NULL)
			)
			->getCollection();

		$eContractDefault = ContractLib::getNew();

		$duration = 0.0;

		// Un compte de jours, et non une comparaison de dates écrites : au-delà de l'an 9999, '10000-01-01' se
		// range avant '9999-12-31' et la boucle ne finirait pas.
		$span = (int)round((strtotime($stop) - strtotime($start)) / 86400);

		for($index = 0; $index <= $span; $index++) {

			$date = date('Y-m-d', strtotime($start.' + '.$index.' DAY'));

			if(HolidayLib::isHoliday($date)) {
				continue;
			}

			$ePresence = $cPresence->find(fn(Presence $ePresence) => $ePresence['from'] <= $date and ($ePresence['to'] === NULL or $ePresence['to'] >= $date), limit: 1, default: new Presence());

			// Hors présence, la personne n'est pas payée par la structure : il n'y a pas de jour dont s'absenter.
			if($ePresence->empty()) {
				continue;
			}

			$eContract = (
				$ePresence['eContract']->notEmpty() and
				$ePresence['eContract']['hasVariableDays'] === FALSE and
				$ePresence['eContract']['days'] !== NULL
			) ? $ePresence['eContract'] : $eContractDefault;

			if($eContract->isDayWorked($date) === FALSE) {
				continue;
			}

			$part = $e->getPartOn($date);

			$duration += ($part === NULL) ? 1.0 : 0.5;

		}

		return $duration;

	}

	public static function validate(Absence $e): void {
		self::decide($e, Absence::VALIDATED);
	}

	public static function refuse(Absence $e): void {
		self::decide($e, Absence::REFUSED);
	}

	/**
	 * Valider et refuser n'écrivent que sur une demande qui attend, et l'état se relit dans l'écriture même : une
	 * demande tranchée depuis le chargement de l'écran, par un autre gérant ou un autre onglet, ne doit pas voir
	 * sa réponse écrasée. C'est, avec une validation sortie de la présence (`outOfPresence`), le seul refus d'état
	 * de ces deux gestes (`absence.p.php`).
	 */
	protected static function decide(Absence $e, string $status): void {

		// Une demande posée dans la présence peut en sortir quand la période est raccourcie ensuite : elle se refuse,
		// elle ne se valide plus (`Absence::to.outOfPresence`). Sans verrou : une période raccourcie juste après la
		// validation donne le même état qu'une période raccourcie plus tard, que le récapitulatif ignore déjà.
		if($status === Absence::VALIDATED) {

			$e->expects(['structure', 'user', 'from', 'to']);

			if(PresenceLib::isPresentThroughout($e['structure'], $e['user'], substr($e['from'], 0, 10), substr($e['to'], 0, 10)) === FALSE) {
				Absence::fail('outOfPresence');
				return;
			}

		}

		$e['status'] = $status;

		$affected = Absence::model()
			->select(['status'])
			->whereStatus(Absence::PENDING)
			->update($e);

		if($affected === 0) {
			Absence::fail('status.decided');
		}

	}

	/**
	 * Les absences validées de chaque personne de `$cUser`, bornées à la période quand l'appelant en a une.
	 * Ni le motif ni la durée ne sont lus : ce que l'équipe voit d'une absence, au planning, à la carte ou sur la
	 * frise, c'est qu'une personne est absente, jamais pourquoi.
	 */
	public static function fillUsers(\structure\Structure $eStructure, \Collection $cUser, ?string $start = NULL, ?string $stop = NULL): void {

		// RH éteinte, personne n'est absent nulle part. Les drapeaux seuls suffisent, sans `acceptHr()` qui lit aussi
		// le type : `hasHr` ne s'allume que chez un producteur (étape `hasHr.production`).
		if(
			$eStructure->conf(\structure\Feature::class)['production'] === FALSE or
			$eStructure->conf(\structure\Feature::class)['hasHr'] === FALSE
		) {

			foreach($cUser as $eUser) {
				$eUser['cAbsence'] = new \Collection();
			}

			return;

		}

		\user\User::model()
			->select([
				'cAbsence' => Absence::model()
					->select(['id', 'from', 'to', 'unit'])
					->whereStructure($eStructure)
					->whereStatus(Absence::VALIDATED)
					->whereTo('>=', $start.' '.self::DAY_START, if: $start !== NULL)
					->whereFrom('<=', $stop.' '.self::DAY_END, if: $stop !== NULL)
					->sort([
						'from' => SORT_DESC
					])
					->delegateCollection('user')
			])
			->get($cUser);

	}

	/**
	 * Les absences d'une personne, toutes et avec leur motif, pour le gérant sur sa fiche.
	 */
	public static function getByUser(\structure\Structure $eStructure, \user\User $eUser): \Collection {

		return Absence::model()
			->select(Absence::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection();

	}

	/**
	 * Les absences qu'une personne lit sur son planning : celles qui finissent cette année ou plus tard, et ses
	 * demandes qui attendent encore une réponse, quelle que soit leur date.
	 */
	public static function getForRequester(\structure\Structure $eStructure, \user\User $eUser): \Collection {

		// Calculé hors de la closure : or() la lie au modèle, où `self` ne désigne plus AbsenceLib.
		$yearStart = \Clock::year().'-01-01 '.self::DAY_START;

		return Absence::model()
			->select(Absence::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->or(
				fn() => $this->whereTo('>=', $yearStart),
				fn() => $this->whereStatus(Absence::PENDING)
			)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection();

	}

	/**
	 * Les absences validées qui touchent `$start` à `$stop`, de la plus proche à la plus lointaine. Le motif peut
	 * dire la santé d'une personne : il ne se lit qu'avec `$withType`, que la page réserve au gérant.
	 */
	public static function getValidatedBetween(\structure\Structure $eStructure, string $start, string $stop, bool $withType): \Collection {

		return Absence::model()
			->select(self::getSelectionForType($withType))
			->whereStructure($eStructure)
			->whereStatus(Absence::VALIDATED)
			->whereFrom('<=', $stop.' '.self::DAY_END)
			->whereTo('>=', $start.' '.self::DAY_START)
			->sort([
				'from' => SORT_ASC
			])
			->getCollection();

	}

	/**
	 * La frise du mois à venir, à l'onglet Absences du planning (décidé le 19/10) : une ligne par
	 * personne présente dans le mois, avec ses absences validées (`cAbsence`), sans motif. Une absence n'a sa
	 * place que dans une période : celle d'une personne sans période dans le mois, ou dont le compte est fermé
	 * (`PresenceLib::getBetween()`), n'a pas de ligne où se poser.
	 * Les jours se comptent depuis aujourd'hui, comme `TeammateLib::group()` les compte depuis le 1er janvier :
	 * `ranges` porte ses périodes bornées au mois, et `segments`, dans l'ordre de `ranges`, les absences bornées à
	 * chaque période, que `segmentsAbsence` reprend avec leur id pour qui peut les modifier. Une absence d'une
	 * demi-journée ou de quelques heures prend le jour entier, seule largeur qui se voit encore à 360 px ; les
	 * dates écrites sous la frise disent la part du jour.
	 *
	 * `graduation` donne la date de chaque lundi, indexée par son jour, et dit s'il ouvre la frise ou son mois,
	 * ceux que la graduation met en gras. Un mois sans absence de l'équipe rend une équipe vide : il n'y a rien
	 * à montrer.
	 */
	public static function getUpcoming(\structure\Structure $eStructure): array {

		$start = \Clock::date();
		$stop = date('Y-m-d', \Clock::shift('+1 month'));
		$days = self::getDayIndex($start, $stop) + 1;

		$graduation = [];

		for($index = 0; $index < $days; $index++) {

			$date = date('Y-m-d', strtotime($start.' +'.$index.' days'));

			if(date('N', strtotime($date)) === '1') {
				$graduation[$index] = [
					'date' => $date,
					'month' => ($index < 7 or (int)substr($date, 8, 2) <= 7)
				];
			}

		}

		$upcoming = [
			'cUser' => new \Collection(),
			'days' => $days,
			'graduation' => $graduation
		];

		$cAbsence = self::getValidatedBetween($eStructure, $start, $stop, withType: FALSE);

		if($cAbsence->empty()) {
			return $upcoming;
		}

		$cUser = new \Collection();

		foreach(PresenceLib::getBetween($eStructure, $start, $stop) as $cPresence) {

			foreach($cPresence as $ePresence) {

				$eUser = self::getUpcomingUser($cUser, $ePresence['user']);

				$eUser['ranges'][] = [
					self::getDayIndex($start, max($ePresence['from'], $start)),
					self::getDayIndex($start, min($ePresence['to'] ?? $stop, $stop)) + 1
				];

			}

		}

		$absent = FALSE;

		foreach($cAbsence as $eAbsence) {
			if($cUser->offsetExists($eAbsence['user']['id'])) {
				// Les absences sont lues sur `$eStructure` : c'est leur structure, qui porte déjà le type que les
				// droits interrogent.
				$eAbsence['structure'] = $eStructure;
				$eUser = $cUser[$eAbsence['user']['id']];
				$eUser['cAbsence'][$eAbsence['id']] = $eAbsence;
				$absent = TRUE;
			}
		}

		if($absent === FALSE) {
			return $upcoming;
		}

		// Sur l'année, `TeammateLib::fillSegments()` trie les absences et fond celles que sépare moins d'un pour
		// cent, où un jour ne fait pas un pixel. Sur un mois, un jour en fait encore 6 à 360 px : chaque absence
		// garde sa bande, et deux bandes séparées d'un jour se lisent séparées. Chaque bande étant une absence, son
		// lien (`segmentsAbsence`, `TeammateUi::getRail()`) a la même étendue, et n'existe que pour qui peut la
		// modifier : la frise se montre à toute l'équipe.
		foreach($cUser as $eUser) {

			$segments = [];
			$segmentsAbsence = [];

			foreach($eUser['ranges'] as [$rangeStart, $rangeStop]) {

				$bands = [];
				$links = [];

				foreach($eUser['cAbsence'] as $eAbsence) {

					$absenceStart = max(self::getDayIndex($start, max(substr($eAbsence['from'], 0, 10), $start)), $rangeStart);
					$absenceStop = min(self::getDayIndex($start, min(substr($eAbsence['to'], 0, 10), $stop)) + 1, $rangeStop);

					if($absenceStop > $absenceStart) {
						$bands[] = [$absenceStart, $absenceStop];
						if($eAbsence->assertUpdate()) {
							$links[] = [$eAbsence['id'], $absenceStart, $absenceStop];
						}
					}

				}

				$segments[] = $bands;
				$segmentsAbsence[] = $links;

			}

			$eUser['segments'] = $segments;
			$eUser['segmentsAbsence'] = $segmentsAbsence;

		}

		$upcoming['cUser'] = $cUser->sort([
			'firstName' => SORT_ASC,
			'lastName' => SORT_ASC
		]);

		return $upcoming;

	}

	/**
	 * La ligne de `$eUser` dans la frise du mois, posée à sa première période.
	 */
	private static function getUpcomingUser(\Collection $cUser, \user\User $eUser): \user\User {

		if($cUser->offsetExists($eUser['id']) === FALSE) {
			$eUser['ranges'] = [];
			$eUser['cAbsence'] = new \Collection();
			$cUser[$eUser['id']] = $eUser;
		}

		return $cUser[$eUser['id']];

	}

	/**
	 * Le rang de `$date` compté en jours depuis `$start`. Un changement d'heure ne décale rien : l'écart se compte
	 * en jours du calendrier, pas en secondes.
	 */
	private static function getDayIndex(string $start, string $date): int {
		return new \DateTimeImmutable($start)->diff(new \DateTimeImmutable($date))->days;
	}

	/**
	 * La sélection d'une absence, privée du motif sans `$withType` : il ne descend alors pas jusqu'au rendu.
	 */
	private static function getSelectionForType(bool $withType): array {

		$properties = Absence::getSelection();

		if($withType === FALSE) {
			$properties = array_filter($properties, fn($property) => $property !== 'type');
		}

		return $properties;

	}

	public static function getPendingByStructure(\structure\Structure $eStructure): \Collection {

		return Absence::model()
			->select(Absence::getSelection())
			->whereStructure($eStructure)
			->whereStatus(Absence::PENDING)
			->sort([
				'from' => SORT_ASC
			])
			->getCollection();

	}

	/**
	 * Les absences validées qui touchent l'année `$year`, rangées par mois de leur début, du plus récent au plus
	 * ancien. Une absence à cheval sur deux années paraît dans chacune. Le motif ne se lit qu'avec `$withType`,
	 * comme pour `getValidatedBetween()`.
	 *
	 * @return array<int, \Collection> par mois, de 12 à 1, les mois sans absence omis
	 */
	public static function getValidatedByStructureForYear(\structure\Structure $eStructure, int $year, bool $withType): array {

		$cAbsence = Absence::model()
			->select(self::getSelectionForType($withType))
			->whereStructure($eStructure)
			->whereStatus(Absence::VALIDATED)
			->whereFrom('<=', $year.'-12-31 '.self::DAY_END)
			->whereTo('>=', $year.'-01-01 '.self::DAY_START)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection();

		$months = [];

		foreach($cAbsence as $eAbsence) {

			$month = (substr($eAbsence['from'], 0, 4) < $year) ? 1 : (int)substr($eAbsence['from'], 5, 2);

			$months[$month] ??= new \Collection();
			$months[$month][$eAbsence['id']] = $eAbsence;

		}

		return $months;

	}

	public static function countPending(\structure\Structure $eStructure): int {

		return Absence::model()
			->whereStructure($eStructure)
			->whereStatus(Absence::PENDING)
			->count();

	}

	/**
	 * Les absences d'une personne sans compte passent au compte qui la remplace (`structure\MemberLib::transferGhost()`).
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écriture de masse : toutes les absences de la personne changent de titulaire, leur statut reste celui qu'elles ont.
		Absence::model()
			->whereStructure($eStructure)
			->whereUser($eUserFrom)
			->update([
				'user' => $eUserTo
			]);

	}

}
?>
