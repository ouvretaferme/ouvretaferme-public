<?php
namespace learning;

/**
 * Les inscriptions aux dates d'une formation de réseau.
 *
 * Quatre règles, tranchées au plan du lot L10b et que tout geste respecte :
 * - une demande à confirmer tient une place : places prises = à confirmer + confirmées ;
 * - une place libérée reste au réseau tant que la liste d'attente n'est pas vide : il ne reste alors
 *   aucune place, et une nouvelle inscription part en attente derrière celles qui attendent ;
 * - le réglage du réseau (`structure\Community::learningConfirm`) ne vaut que pour les inscriptions
 *   suivantes, rien n'est réécrit quand il change ;
 * - se réinscrire rouvre la ligne annulée, une ligne par personne et par date.
 *
 * La page publique du réseau inscrit aussi sans compte (`createPublic()`) : la ligne porte alors
 * l'identité saisie et un jeton, et se rattache au compte qui se connecte avec la même adresse
 * (`attachByUser()`). Les quatre règles valent pour elle telles quelles.
 *
 * Chaque changement d'état prévient l'autre côté par e-mail (`getMails()`), mis en file par
 * `SendLib`, donc dans la transaction de l'écriture.
 */
class DateEnrollmentLib extends DateEnrollmentCrud {

	const OPEN = [DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED, DateEnrollment::WAITING];

	/**
	 * Les lignes ouvertes sans compte qu'une même adresse peut tenir sur les dates à venir d'un
	 * réseau. Au-dessus des 4 dates à venir du plus grand réseau et des 2 lignes ouvertes par personne
	 * et par réseau mesurées en base le 2026-09-27 ; chaque inscription envoie un accusé de réception
	 * à l'adresse saisie, la borne dit donc aussi combien d'e-mails une boîte qui n'a rien demandé
	 * peut recevoir. Une ligne avec compte n'y est pas soumise.
	 */
	const PUBLIC_OPEN_MAX = 5;

	/**
	 * Les envois de la page publique par poste et par heure. Une adresse neuve à chaque envoi
	 * échappe à `PUBLIC_OPEN_MAX`, et remplirait les places (une demande à confirmer en tient une)
	 * en faisant partir un e-mail par envoi ; 20 laisse un réseau inscrire depuis son bureau un groupe
	 * de 12, le plus grand nombre de places mesuré sur une date. Compté par `user\ThrottleAiLib`, qui
	 * ne compte rien sans Redis.
	 */
	const PUBLIC_POST_MAX = 20;
	const PUBLIC_POST_WINDOW = 3600;

	/**
	 * Les lignes que le tableau de bord d'un réseau montre sous « Dernières inscriptions ». Il
	 * annonce ce qui vient d'arriver et mène à la liste de la date, qui les porte toutes.
	 */
	const LAST_MAX = 5;

	/**
	 * `public` : la page publique sans compte, qui demande l'identité et la ferme en texte libre ;
	 * `publicOnline` : la même page pour un compte connecté qui ne lit pas la formation, dont
	 * l'identité est celle du compte. Sinon le panneau du membre, sur la fiche.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		return match($origin) {
			'public' => ['firstName', 'lastName', 'email', 'structureName', 'trainee', 'committedAt', 'conditions'],
			'publicOnline' => ['structureName', 'trainee', 'committedAt', 'conditions'],
			NULL => ['structure', 'trainee', 'committedAt', 'conditions'],
		};

	}

	/**
	 * L'édition rapide des heures d'une personne pointée (lot L8e), seule écriture de la ligne par
	 * un formulaire après l'inscription.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['attendedMinutes'];
	}

	/**
	 * La ligne de la personne sur la date si elle existe, annulée ou non, sinon une ligne neuve sans
	 * état : c'est ce que lit `DateEnrollment::acceptCreate()`. Elle porte le réseau et son groupe
	 * (`eStructureCommunity`), dont le réglage et les conditions servent aux trois étapes du panneau
	 * et au refus des cases dans `build()`.
	 */
	public static function getCreateElement(Date $eDate, \user\User $eUser): DateEnrollment {

		$eDateEnrollment = self::getByDateAndUser($eDate, $eUser);

		if($eDateEnrollment->empty()) {
			$eDateEnrollment = new DateEnrollment([
				'date' => $eDate,
				'user' => $eUser,
				'status' => NULL,
			]);
		}

		$eStructureCommunity = \structure\StructureLib::getById($eDate['course']['community']);
		// Lu ici plutôt que par `community()`, qui garde le groupe en cache statique pour tout le
		// processus : les conditions changées par le réglage doivent se lire à la demande suivante.
		$eStructureCommunity['community'] = \structure\CommunityLib::getByStructure($eStructureCommunity);

		$eDateEnrollment['eStructureCommunity'] = $eStructureCommunity;

		return $eDateEnrollment;

	}

	/**
	 * La ligne que lit `DateEnrollment::acceptPublicCreate()` sur la page publique : celle du compte
	 * connecté sur la date s'il y en a un, sinon une ligne neuve sans état. Sans compte, l'adresse
	 * n'est connue qu'au `build()` : la ligne de la même adresse se relit sous le verrou, dans
	 * `createPublic()`. `$eStructureCommunity` est le réseau de l'adresse, que la date doit porter.
	 */
	public static function getPublicCreateElement(Date $eDate, \structure\Structure $eStructureCommunity, \user\User $eUser): DateEnrollment {

		$eDateEnrollment = self::getByDateAndUser($eDate, $eUser);

		if($eDateEnrollment->empty()) {
			$eDateEnrollment = new DateEnrollment([
				'date' => $eDate,
				'user' => $eUser,
				'status' => NULL,
				'token' => NULL,
				'firstName' => NULL,
				'lastName' => NULL,
				'email' => NULL,
				'structureName' => NULL,
				'trainee' => NULL,
			]);
		}

		$eDateEnrollment['eStructureCommunity'] = $eStructureCommunity;

		return $eDateEnrollment;

	}

	public static function getByToken(string $token): DateEnrollment {

		if(strlen($token) !== 30 or ctype_xdigit($token) === FALSE) {
			return new DateEnrollment();
		}

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereToken($token)
			->get();

	}

	public static function getByDateAndUser(Date $eDate, \user\User $eUser): DateEnrollment {

		if($eUser->empty()) {
			return new DateEnrollment();
		}

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereUser($eUser)
			->whereDate($eDate)
			->get();

	}

	/**
	 * Le statut de stagiaire de la dernière inscription de la personne, proposé d'office à la
	 * suivante : il change rarement d'une date à l'autre.
	 */
	public static function getLastTrainee(\user\User $eUser): ?string {

		$eDateEnrollment = DateEnrollment::model()
			->select('trainee')
			->whereUser($eUser)
			->sort(['requestedAt' => SORT_DESC])
			->get();

		return $eDateEnrollment->empty() ? NULL : $eDateEnrollment['trainee'];

	}

	/**
	 * Toutes les lignes d'une date, pour sa liste et son export, dans l'ordre des demandes.
	 */
	public static function getByDate(Date $eDate): \Collection {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->sort(['requestedAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les personnes confirmées d'une date, pour sa feuille d'émargement, par nom puis par prénom.
	 * Rangées après lecture : le nom d'une ligne est celui du compte, sinon sa saisie de la page
	 * publique (`DateEnrollment::getLastName()`), ce qu'aucune colonne ne porte seule.
	 */
	public static function getConfirmedByDate(Date $eDate): \Collection {

		$collator = \L::getCollator();

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereStatus(DateEnrollment::CONFIRMED)
			->getCollection()
			->sort(fn(DateEnrollment $e1, DateEnrollment $e2) => (
				$collator->compare($e1->getLastName(), $e2->getLastName()) ?:
				$collator->compare($e1->getFirstName() ?? '', $e2->getFirstName() ?? '')
			));

	}

	/**
	 * Les demandes à confirmer sur les dates à venir de chaque formation de réseau d'une liste
	 * (`requested`), en une requête groupée par formation. Les formations en ligne n'ont pas de date
	 * et ne portent pas le compte.
	 *
	 * La jointure sur `Date` rattache la demande à sa formation, la citation garde la règle « à
	 * venir » de `DateLib::applyUpcoming()`. Les deux tables ont un `status` : sans `m1.`, MySQL
	 * refuse la condition comme ambiguë.
	 */
	public static function fillRequested(\Collection $cCourse): void {

		// Sans clone : les compteurs se posent sur les éléments de la liste, pas sur des copies.
		$cCourseDated = $cCourse->find(fn(Course $eCourse) => $eCourse->isDated(), clone: FALSE);

		if($cCourseDated->empty()) {
			return;
		}

		$cDateEnrollmentCount = DateEnrollment::model()
			->select([
				'course' => new \Sql('m2.course', 'int'),
				'requested' => new \Sql('COUNT(*)', 'int')
			])
			->join(Date::model(), 'm1.date = m2.id')
			->where('m1.status', DateEnrollment::REQUESTED)
			->where('m1.date', 'IN', DateLib::applyUpcoming(new DateModel())
				->select('id')
				->whereCourse('IN', $cCourseDated)
				->getSql())
			->group(new \Sql('m2.course'))
			->getCollection(index: 'course');

		foreach($cCourseDated as $eCourse) {
			$eCourse['requested'] = $cDateEnrollmentCount[$eCourse['id']]['requested'] ?? 0;
		}

	}

	/**
	 * Le réseau a-t-il des demandes à confirmer, présentes ou à venir : oui tant qu'il confirme ses
	 * inscriptions, à une date (`structure\Community::learningConfirm`) ou en ligne, ne serait-ce que
	 * celles qui demandent une prise en charge (`learningOnlineConfirm`), et sinon tant qu'une de ses
	 * formations porte encore une demande. Les réglages ne valent que pour les inscriptions suivantes :
	 * un réseau qui vient de passer sans confirmation garde celles déjà envoyées.
	 *
	 * `$cCourse` est toute la liste du réseau, jamais celle d'un type filtré : la réponse décide d'une
	 * colonne du tableau, qui ne change pas d'un onglet du filtre à l'autre. Lit `requested`, posé par
	 * `fillRequested()` sur les formations datées et par `EnrollmentLib::fillRequests()` sur les
	 * formations en ligne.
	 */
	public static function hasRequested(\structure\Structure $eStructure, \Collection $cCourse): bool {

		if(
			$eStructure->getCommunity('learningConfirm') or
			$eStructure->getCommunity('learningOnlineConfirm') !== \structure\Community::IMMEDIATE
		) {
			return TRUE;
		}

		return $cCourse
			->find(fn(Course $eCourse) => $eCourse['requested'] > 0)
			->notEmpty();

	}

	/**
	 * Les dernières inscriptions reçues par un réseau sur ses dates à venir, la plus récente en tête,
	 * avec les places prises de leur date (`taken`). Une ligne en liste d'attente n'y entre pas : elle
	 * ne tient pas de place, et c'est le remplissage que le tableau de bord rapporte.
	 *
	 * Formations et dates sont citées et non jointes : les trois tables vivent dans la base commune.
	 */
	public static function getLastByCommunity(\structure\Structure $eStructureCommunity): \Collection {

		$mCourse = new CourseModel()
			->select('id')
			->whereCommunity($eStructureCommunity);

		$mDate = DateLib::applyUpcoming(new DateModel())
			->select('id')
			->whereCourse('IN', $mCourse->getSql('t'));

		$cDateEnrollment = DateEnrollment::model()
			->select([
				'id', 'status', 'requestedAt',
				'user' => ['firstName', 'lastName'],
				'firstName', 'lastName',
				'structure' => ['name'],
				'structureName',
				'date' => [
					'startAt', 'seats',
					'course' => ['name']
				]
			])
			->whereDate('IN', $mDate->getSql())
			->whereStatus('IN', [DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED])
			->sort(['requestedAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection(0, self::LAST_MAX);

		self::fillCounts($cDateEnrollment->getColumnCollection('date'));

		return $cDateEnrollment;

	}

	/**
	 * Les lignes par état sur chaque date d'une liste, en une requête groupée, et les places qui
	 * restent (`remaining`, `NULL` quand la date ne limite pas ses places).
	 */
	public static function fillCounts(\Collection $cDate): void {

		if($cDate->empty()) {
			return;
		}

		$cDateEnrollmentCount = DateEnrollment::model()
			->select([
				'date',
				'status',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereDate('IN', $cDate)
			->group(['date', 'status'])
			->getCollection();

		foreach($cDate as $eDate) {

			$counts = [
				DateEnrollment::REQUESTED => 0,
				DateEnrollment::CONFIRMED => 0,
				DateEnrollment::WAITING => 0,
				DateEnrollment::CANCELED => 0,
			];

			foreach($cDateEnrollmentCount as $eDateEnrollmentCount) {

				if($eDateEnrollmentCount['date']['id'] === $eDate['id']) {
					$counts[$eDateEnrollmentCount['status']] = $eDateEnrollmentCount['count'];
				}

			}

			self::setCounts($eDate, $counts);

		}

	}

	/**
	 * Les personnes confirmées pointées présentes sur une date (`attended`), que la ligne de durée
	 * rapporte au nombre de confirmées. Lu par l'index de la date et de l'état.
	 */
	public static function fillAttendedByDate(Date $eDate): void {

		$eDate['attended'] = DateEnrollment::model()
			->whereDate($eDate)
			->whereStatus(DateEnrollment::CONFIRMED)
			->where('attendedAt IS NOT NULL')
			->count();

	}

	/**
	 * Les places confirmées et pointées de ces comptes aux dates de la formation, dans l'ordre des
	 * jours : ce que le relevé et l'attestation de suivi additionnent (`fillAttended()`), et ce dont
	 * la fiche d'une formation sans leçon tire une attestation de présence par place (lot L8f).
	 */
	public static function getAttendedByCourse(Course $eCourse, \Collection $cUser): \Collection {

		if($cUser->empty()) {
			return new \Collection();
		}

		return DateEnrollment::model()
			->select([
				'id', 'user', 'status', 'attendedAt', 'attendedMinutes',
				'date' => Date::getSelection()
			])
			->whereUser('IN', $cUser)
			->whereDate('IN', new DateModel()
				->select('id')
				->whereCourse($eCourse)
				->getSql())
			->whereStatus(DateEnrollment::CONFIRMED)
			->where('attendedAt IS NOT NULL')
			->getCollection()
			->sort(fn(DateEnrollment $e1, DateEnrollment $e2) => strcmp($e1['date']['startAt'], $e2['date']['startAt']));

	}

	/**
	 * Pose sur chaque stagiaire, après `ProgressLib::fillSummary()`, ses présences pointées aux dates
	 * de la formation (lot L8e-3), que le relevé, l'attestation et le journal lisent :
	 * - `cDateEnrollmentAttended` : ses places confirmées et pointées, dans l'ordre des jours ;
	 * - `minutesAttended` et `attendedModes` : leurs heures additionnées et leurs modes réunis ;
	 * - `minutesRetained` : la durée retenue, les leçons validées plus ces heures ;
	 * - `followedFrom` et `followedTo` : la période suivie, de la première leçon ou date pointée à la
	 *   dernière, que l'attestation écrit.
	 *
	 * Les places se comptent d'abord une à une (`fillAttendance()`), puis s'additionnent par
	 * compte. Les dates se citent par `getSql()`, même base ; les lignes se lisent par la clé unique
	 * `[user, date]`. `Date::getSelection()` charge les séances, que la durée d'une date additionne.
	 */
	public static function fillAttended(Course $eCourse, \Collection $cUser): void {

		$cDateEnrollment = self::getAttendedByCourse($eCourse, $cUser);

		self::fillAttendance($cDateEnrollment);

		foreach($cUser as $eUser) {

			$eUser->expects(['minutesValidated', 'firstDoneAt', 'lastDoneAt']);

			$cDateEnrollmentUser = $cDateEnrollment->find(fn(DateEnrollment $e) => $e['user']['id'] === $eUser['id']);

			$eUser['cDateEnrollmentAttended'] = $cDateEnrollmentUser;
			$eUser['minutesAttended'] = (int)$cDateEnrollmentUser->sum('minutesAttended');
			$eUser['attendedModes'] = self::mergeModes($cDateEnrollmentUser->makeArray(fn(DateEnrollment $e) => $e['attendedModes']));
			$eUser['minutesRetained'] = $eUser['minutesValidated'] + $eUser['minutesAttended'];

			$followedAts = array_merge(
				array_filter([$eUser['firstDoneAt'], $eUser['lastDoneAt']]),
				...$cDateEnrollmentUser->makeArray(fn(DateEnrollment $e) => [$e['date']['startAt'], $e['date']['endAt']])
			);

			$eUser['followedFrom'] = $followedAts ? min($followedAts) : NULL;
			$eUser['followedTo'] = $followedAts ? max($followedAts) : NULL;

		}

	}

	/**
	 * Pose sur chaque place ses heures pointées (`minutesAttended`) et les modes de sa date
	 * (`attendedModes`) : rien sur une place qui n'est pas pointée. Seule une place confirmée se
	 * pointe (`DateEnrollment::acceptAttend()`), et elle ne s'annule plus une fois la date passée
	 * (`acceptCancel()`) : être pointée suffit. Une date mixte compte en entier, le pointage se
	 * faisant par date et non par séance (porte du 03/10). Une place sans compte n'a ni relevé ni
	 * attestation de suivi : elle se lit ici seule.
	 *
	 * Le nom dit ce qui est posé, comme `fillLosesLessons()` : un `fillXxxByY()` de
	 * ce Lib filtre par `Y` (`fillAttendedByDate()`).
	 */
	public static function fillAttendance(\Collection $cDateEnrollment): void {

		foreach($cDateEnrollment as $eDateEnrollment) {

			$attended = $eDateEnrollment->isAttended();

			$eDateEnrollment['minutesAttended'] = $attended ? $eDateEnrollment->getAttendedMinutes() : 0;
			$eDateEnrollment['attendedModes'] = $attended ? $eDateEnrollment['date']->getModes() : [];

		}

	}

	/**
	 * Les modes de plusieurs places réunis, sans doublon, dans l'ordre de `DateSequence::mode` comme
	 * `Date::getModes()`.
	 *
	 * @param string[][] $modes
	 * @return string[]
	 */
	private static function mergeModes(array $modes): array {

		$merged = array_merge(...$modes);

		return array_values(array_filter(
			[DateSequence::ONSITE, DateSequence::REMOTE],
			fn(string $mode) => in_array($mode, $merged)
		));

	}

	/**
	 * La présence aux heures saisies les plus longues d'une date, que la durée de la date ne peut pas
	 * passer en dessous. Une présence sans heures saisies suit la durée de la date et n'est pas lue.
	 */
	public static function getLongestAttendanceByDate(Date $eDate): DateEnrollment {

		return DateEnrollment::model()
			->select([
				'id', 'attendedMinutes', 'firstName', 'lastName',
				'user' => ['firstName', 'lastName']
			])
			->whereDate($eDate)
			->whereAttendedMinutes('!=', NULL)
			->sort(['attendedMinutes' => SORT_DESC])
			->get();

	}

	/**
	 * Le compte d'une seule date, lu sous son verrou par `create()` et `giveSeat()`. La même
	 * requête que `fillCounts()`, par la même raison d'index.
	 */
	private static function countByDate(Date $eDate): void {

		self::fillCounts(new \Collection([$eDate]));

	}

	/**
	 * Les places prises comptent les demandes à confirmer (règle 1) ; des places baissées sous ce
	 * nombre sont acceptées à l'écriture de la date, d'où le plancher à zéro. Dès qu'une personne
	 * attend, la place libérée reste au réseau (règle 2).
	 */
	private static function setCounts(Date $eDate, array $counts): void {

		$eDate['requested'] = $counts[DateEnrollment::REQUESTED];
		$eDate['confirmed'] = $counts[DateEnrollment::CONFIRMED];
		$eDate['waiting'] = $counts[DateEnrollment::WAITING];
		$eDate['canceled'] = $counts[DateEnrollment::CANCELED];
		$eDate['taken'] = $eDate['requested'] + $eDate['confirmed'];

		if($eDate['seats'] === NULL) {
			$eDate['remaining'] = NULL;
		} else if($eDate['waiting'] > 0) {
			$eDate['remaining'] = 0;
		} else {
			$eDate['remaining'] = max(0, $eDate['seats'] - $eDate['taken']);
		}

	}

	/**
	 * Sur la fiche et sur la liste publique d'un réseau : les comptes de chaque date, et la ligne de
	 * la personne connectée sur chacune (`eDateEnrollment`, sans état si elle ne s'y est jamais
	 * inscrite ou si personne n'est connecté).
	 */
	public static function fillForCourse(\Collection $cDate, \user\User $eUser): void {

		self::fillCounts($cDate);

		if($cDate->empty()) {
			return;
		}

		$cDateEnrollment = $eUser->empty() ? new \Collection() : DateEnrollment::model()
			->select(DateEnrollment::model()->getProperties())
			->whereUser($eUser)
			->whereDate('IN', $cDate)
			->getCollection(index: 'date');

		foreach($cDate as $eDate) {

			if($cDateEnrollment->offsetExists($eDate['id'])) {

				$eDateEnrollment = $cDateEnrollment[$eDate['id']];
				$eDateEnrollment['date'] = $eDate;

				$eDate['eDateEnrollment'] = $eDateEnrollment;

			} else {
				$eDate['eDateEnrollment'] = new DateEnrollment([
					'date' => $eDate,
					'user' => $eUser,
					'status' => NULL,
				]);
			}

		}

	}

	/**
	 * L'état que prend une inscription envoyée maintenant : en attente s'il ne reste aucune place
	 * (règle 2, y compris quand l'inscription serait immédiate), sinon à confirmer ou confirmée selon
	 * le réglage du réseau. À l'écriture, les comptes de la date sont ceux lus sous son verrou ; le
	 * panneau d'inscription pose la même question pour choisir son titre et son bouton.
	 */
	public static function getStatusForNew(Date $eDate, bool $confirm): string {

		if($eDate['remaining'] === 0) {
			return DateEnrollment::WAITING;
		}

		return $confirm ? DateEnrollment::REQUESTED : DateEnrollment::CONFIRMED;

	}

	/**
	 * Pourquoi la date ne prend plus d'inscription depuis que la page l'a chargée : `closed`, la date
	 * annulée ou supprimée, ou la formation inscrite sur le site de l'organisme, `paused`, la date ou
	 * sa formation en pause, NULL, elle en prend. Le refus définitif passe avant la pause, comme sur
	 * la page (`DateEnrollment::acceptOpen()`). Appelé sous le verrou de la date (`DateLib::lock()`),
	 * qui ne couvre pas la formation : celle-ci se relit par `getForUpdate()`, une lecture
	 * verrouillante qui voit la dernière version validée et fait attendre une bascule de la pause
	 * jusqu'à l'écriture. Une date ou une formation supprimée entre la page et l'envoi ne se relit
	 * plus : elle ferme.
	 */
	private static function getClosedReason(Date $eDate): ?string {

		$eDateLocked = new DateModel()
			->select(['status', 'enrollmentPaused'])
			->whereId($eDate)
			->get();

		$eCourseLocked = new CourseModel()
			->select(['externalOnly', 'enrollmentPaused'])
			->whereId($eDate['course'])
			->getForUpdate();

		if(
			$eDateLocked->empty() or
			$eCourseLocked->empty() or
			$eDateLocked['status'] !== Date::PLANNED or
			$eCourseLocked['externalOnly']
		) {
			return 'closed';
		}

		if(
			$eCourseLocked['enrollmentPaused'] or
			$eDateLocked['enrollmentPaused']
		) {
			return 'paused';
		}

		return NULL;

	}

	/**
	 * Inscrit la personne, ou rouvre sa ligne annulée (`doCreate` vide l'id de l'élément chargé :
	 * la ligne se retrouve ici, sous le verrou).
	 */
	public static function create(DateEnrollment $e): void {

		$e->expects(['date' => ['course'], 'user', 'structure', 'trainee', 'committedAt']);

		$eDate = $e['date'];

		DateEnrollment::model()->beginTransaction();

			// Le verrou d'abord, toute lecture ensuite : en REPEATABLE READ, la première lecture de
			// la transaction fige la vue, et une lecture posée avant le verrou cacherait une
			// inscription concurrente validée pendant l'attente.
			DateLib::lock($eDate);

			$eDateEnrollmentExisting = new DateEnrollmentModel()
				->select(['id', 'status'])
				->whereUser($e['user'])
				->whereDate($eDate)
				->get();

			$closed = self::getClosedReason($eDate);

			if(
				$closed !== NULL or
				($eDateEnrollmentExisting->notEmpty() and $eDateEnrollmentExisting->isOpen())
			) {
				DateEnrollment::model()->commit();
				DateEnrollment::fail($closed ?? 'closed');
				return;
			}

			self::countByDate($eDate);

			$confirm = \structure\CommunityLib::getByStructure($eDate['course']['community'])['learningConfirm'];

			$e['status'] = self::getStatusForNew($eDate, $confirm);
			$e['requestedAt'] = new \Sql('NOW()');
			$e['confirmedAt'] = ($e['status'] === DateEnrollment::CONFIRMED) ? new \Sql('NOW()') : NULL;
			$e['canceledBy'] = NULL;
			$e['canceledAt'] = NULL;
			$e['remindedAt'] = NULL;

			if($eDateEnrollmentExisting->empty()) {
				parent::create($e);
			} else {
				$e['id'] = $eDateEnrollmentExisting['id'];
				parent::update($e, ['structure', 'trainee', 'status', 'requestedAt', 'committedAt', 'confirmedAt', 'canceledBy', 'canceledAt', 'remindedAt']);
			}

			// L'e-mail se rend sur la ligne relue et non sur `$e` : sorti de `build()`, « Au nom de »
			// n'y est qu'un identifiant, sans le nom que l'e-mail écrit.
			$eDateEnrollmentWritten = new DateEnrollmentModel()
				->select(DateEnrollment::getSelection())
				->whereId($e['id'])
				->get();

			self::send(self::getMails($eDateEnrollmentWritten, 'created'));

		DateEnrollment::model()->commit();

	}

	/**
	 * L'inscription par la page publique, avec ou sans compte, dans la transaction et sous le verrou
	 * de `create()`. Seule une date fermée ou la limite par poste se disent au visiteur : pour tout
	 * le reste, l'écran répond « envoyé », et c'est l'e-mail parti à l'adresse saisie qui porte le vrai
	 * cas. Sans cela, taper une adresse suffirait à apprendre si elle est inscrite, combien de fois,
	 * et à quel nom.
	 *
	 * Avec un compte, la ligne est celle du compte. Sans compte, la ligne de l'adresse se relit parmi
	 * les seules lignes sans compte : une ligne rattachée garde son adresse (`attachByUser()`), et un
	 * inconnu qui la taperait écrirait sur l'inscription d'un compte et lirait son prénom. Selon ce
	 * qu'on trouve :
	 * - une ligne ouverte : rien ne s'écrit, l'accusé de réception repart avec son lien ;
	 * - une ligne d'un compte à cette adresse sur la date : rien ne s'écrit ni ne part ;
	 * - le plafond `PUBLIC_OPEN_MAX` atteint par l'adresse : rien ne s'écrit ni ne part ;
	 * - sinon la ligne s'écrit, ou se rouvre en gardant son jeton, et l'accusé part.
	 *
	 * Aucun accusé ne part à l'adresse d'un compte existant pour une ligne sans compte : la ligne
	 * l'attend à la connexion (`sendReceived()`).
	 *
	 * Tout accusé est borné à un par heure pour une adresse et une date (`mailedAt`), quel que soit le
	 * nombre d'envois : la borne vit sur la ligne, donc tient sans Redis, que la limite par poste
	 * n'a pas.
	 *
	 * Le jeton est tiré ici et jamais lu du formulaire : il n'est pas dans `getPropertiesCreate()`. Il
	 * ne revient jamais à celui qui envoie : seul l'e-mail le porte. `$e['status']` est toujours posé,
	 * celui de la ligne écrite, ou celui qu'aurait pris une ligne neuve : c'est ce que l'écran dit.
	 */
	public static function createPublic(DateEnrollment $e): void {

		$e->expects(['date' => ['course'], 'user', 'trainee', 'committedAt', 'eStructureCommunity']);

		$ip = getIp();

		if(\user\ThrottleAiLib::isExceeded('learning-enrollment-ip', $ip, self::PUBLIC_POST_MAX)) {
			DateEnrollment::fail('throttled');
			return;
		}

		\user\ThrottleAiLib::hit('learning-enrollment-ip', $ip, self::PUBLIC_POST_WINDOW);

		$eDate = $e['date'];
		$withUser = $e['user']->notEmpty();

		DateEnrollment::model()->beginTransaction();

			// Même raison que dans `create()` : le verrou avant toute lecture.
			DateLib::lock($eDate);

			$closed = self::getClosedReason($eDate);

			if($closed !== NULL) {
				DateEnrollment::model()->commit();
				DateEnrollment::fail($closed);
				return;
			}

			$mDateEnrollmentExisting = new DateEnrollmentModel()
				->select(DateEnrollment::getSelection())
				->whereDate($eDate);

			if($withUser) {
				$mDateEnrollmentExisting->whereUser($e['user']);
			} else {
				$mDateEnrollmentExisting
					->whereEmail($e['email'])
					->whereUser(NULL);
			}

			$eDateEnrollmentExisting = $mDateEnrollmentExisting->get();

			self::countByDate($eDate);

			$e['status'] = self::getStatusForNew($eDate, $e['eStructureCommunity']->getCommunity('learningConfirm'));

			if(
				$eDateEnrollmentExisting->notEmpty() and
				$eDateEnrollmentExisting->isOpen()
			) {
				self::sendReceived($eDateEnrollmentExisting);
				DateEnrollment::model()->commit();
				return;
			}

			if(
				$withUser === FALSE and
				(
					self::existsWithUserByEmail($eDate, $e['email']) or
					self::countOpenByEmail($e['email'], $e['eStructureCommunity']) >= self::PUBLIC_OPEN_MAX
				)
			) {
				DateEnrollment::model()->commit();
				return;
			}

			$e['requestedAt'] = new \Sql('NOW()');
			$e['confirmedAt'] = ($e['status'] === DateEnrollment::CONFIRMED) ? new \Sql('NOW()') : NULL;
			$e['canceledBy'] = NULL;
			$e['canceledAt'] = NULL;
			$e['remindedAt'] = NULL;

			$properties = ['structureName', 'trainee', 'status', 'requestedAt', 'committedAt', 'confirmedAt', 'canceledBy', 'canceledAt', 'remindedAt', 'token'];

			if($withUser === FALSE) {
				$properties = array_merge($properties, ['firstName', 'lastName', 'email']);
			}

			if($eDateEnrollmentExisting->empty()) {
				$e['token'] = $e->getNewToken();
				$e['mailedAt'] = NULL;
				parent::create($e);
			} else {
				$e['id'] = $eDateEnrollmentExisting['id'];
				$e['token'] = $eDateEnrollmentExisting['token'] ?? $e->getNewToken();
				parent::update($e, $properties);
			}

			$eDateEnrollmentWritten = new DateEnrollmentModel()
				->select(DateEnrollment::getSelection())
				->whereId($e['id'])
				->get();

			self::sendReceived($eDateEnrollmentWritten);

		DateEnrollment::model()->commit();

	}

	/**
	 * L'accusé de réception d'une ligne de la page publique, s'il n'en est pas parti un depuis une
	 * heure pour cette ligne, c'est-à-dire pour cette adresse et cette date. La date se pose dans la
	 * condition de l'écriture : deux envois simultanés sur la même ligne n'en font partir qu'un.
	 *
	 * Rien ne part à l'adresse d'un compte existant pour une ligne sans compte : la ligne se rattache
	 * à sa connexion, et son titulaire n'a rien demandé à une page où il ne s'est pas connecté.
	 */
	private static function sendReceived(DateEnrollment $e): void {

		$e->expects(['user', 'email']);

		if(
			$e['user']->empty() and
			\user\UserLib::getByEmail($e['email'], ['id'])->notEmpty()
		) {
			return;
		}

		$e['mailedAt'] = new \Sql('NOW()');

		// Lue hors des closures de `or()`, que le cadriciel lie au modèle : `self` y désignerait
		// `DateEnrollmentModel`.
		$mailedBefore = new \Sql('NOW() - INTERVAL '.self::PUBLIC_POST_WINDOW.' SECOND');

		$affected = DateEnrollment::model()
			->select(['mailedAt'])
			->or(
				fn() => $this->whereMailedAt(NULL),
				fn() => $this->whereMailedAt('<', $mailedBefore)
			)
			->update($e);

		if($affected > 0) {
			self::send(self::getMails($e, 'created'));
		}

	}

	/**
	 * Une ligne d'un compte à cette adresse sur la date : celle qu'un compte a prise en se connectant
	 * (`attachByUser()`) garde l'adresse saisie, et la clé `[email, date]` refuserait une seconde
	 * ligne.
	 */
	private static function existsWithUserByEmail(Date $eDate, string $email): bool {

		return new DateEnrollmentModel()
			->whereEmail($email)
			->whereDate($eDate)
			->whereUser('!=', NULL)
			->exists();

	}

	/**
	 * Les lignes ouvertes sans compte d'une adresse sur les dates à venir d'un réseau, que borne
	 * `PUBLIC_OPEN_MAX`. Lues par l'index `[email, date]`, la citation bornant aux dates du réseau.
	 */
	private static function countOpenByEmail(string $email, \structure\Structure $eStructureCommunity): int {

		return new DateEnrollmentModel()
			->whereEmail($email)
			->whereUser(NULL)
			->whereStatus('IN', self::OPEN)
			->whereDate('IN', DateLib::applyPublicByCommunity(new DateModel(), $eStructureCommunity)
				->select('id')
				->getSql())
			->count();

	}

	/**
	 * Le réseau confirme une demande. La demande tenait déjà sa place (règle 1) : aucun compte à
	 * refaire, donc pas de verrou. L'état se lit dans la condition de l'écriture, qui ne touche rien
	 * si la ligne a changé entre-temps.
	 */
	public static function confirm(DateEnrollment $e): void {

		$e['status'] = DateEnrollment::CONFIRMED;
		$e['confirmedAt'] = new \Sql('NOW()');

		DateEnrollment::model()->beginTransaction();

			$affected = DateEnrollment::model()
				->select(['status', 'confirmedAt'])
				->whereStatus(DateEnrollment::REQUESTED)
				->update($e);

			// Rien d'écrit : la ligne a changé d'état depuis que l'écran l'a montrée.
			if($affected === 0) {
				DateEnrollment::model()->commit();
				DateEnrollment::fail('changed');
				return;
			}

			self::send(self::getMails($e, 'confirmed'));

		DateEnrollment::model()->commit();

	}

	/**
	 * Le réseau donne une place à une personne en attente, s'il en reste une : les places prises
	 * se comptent sous le verrou de la date, comme à l'inscription. Les personnes qui attendent
	 * n'empêchent pas ce geste, c'est lui qui les sert.
	 */
	public static function giveSeat(DateEnrollment $e): void {

		$e->expects(['date']);

		$eDate = $e['date'];

		DateEnrollment::model()->beginTransaction();

			// Même raison que dans `create()` : le verrou avant toute lecture.
			DateLib::lock($eDate);

			self::countByDate($eDate);

			if(
				$eDate['seats'] !== NULL and
				$eDate['taken'] >= $eDate['seats']
			) {
				DateEnrollment::model()->commit();
				DateEnrollment::fail('noSeat');
				return;
			}

			$e['status'] = DateEnrollment::CONFIRMED;
			$e['confirmedAt'] = new \Sql('NOW()');

			$affected = DateEnrollment::model()
				->select(['status', 'confirmedAt'])
				->whereStatus(DateEnrollment::WAITING)
				->update($e);

			// Rien d'écrit : la ligne a changé d'état depuis que l'écran l'a montrée.
			if($affected === 0) {
				DateEnrollment::model()->commit();
				DateEnrollment::fail('changed');
				return;
			}

			self::send(self::getMails($e, 'seatGiven'));

		DateEnrollment::model()->commit();

	}

	/**
	 * Annule une ligne ouverte, par la personne (`USER`), par le réseau (`COMMUNITY`) ou avec sa date
	 * (`DATE`), et prévient l'autre côté. La place libérée ne se donne à personne d'office (règle 2).
	 */
	public static function cancel(DateEnrollment $e, string $canceledBy): void {

		$e['status'] = DateEnrollment::CANCELED;
		$e['canceledBy'] = $canceledBy;
		$e['canceledAt'] = new \Sql('NOW()');

		DateEnrollment::model()->beginTransaction();

			$affected = DateEnrollment::model()
				->select(['status', 'canceledBy', 'canceledAt'])
				->whereStatus('IN', self::OPEN)
				->update($e);

			if($affected > 0) {
				self::send(self::getMails($e, 'canceled'));
			}

		DateEnrollment::model()->commit();

	}

	/**
	 * La date est annulée : toutes ses lignes encore ouvertes passent à « Annulée » avec leur date
	 * (`DATE`), et chacun reçoit l'e-mail qui l'annonce. Une ligne déjà annulée, par la personne ou
	 * à l'unité par le réseau, garde qui l'a annulée et quand. Appelée sous la transaction de
	 * `DateLib::cancel()`, après l'écriture de la date, qui en tient le verrou. Rend le nombre de
	 * lignes annulées.
	 */
	public static function cancelByDate(Date $eDate): int {

		$cDateEnrollment = self::getOpenByDate($eDate);

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eDateEnrollment['date'] = $eDate;

			self::cancel($eDateEnrollment, DateEnrollment::DATE);

		}

		return $cDateEnrollment->count();

	}

	/**
	 * Les lignes encore ouvertes d'une date : celles que son annulation annule, et dont le panneau
	 * d'annulation dit si l'une perd l'accès aux leçons.
	 */
	public static function getOpenByDate(Date $eDate): \Collection {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereStatus('IN', self::OPEN)
			->getCollection();

	}

	/**
	 * Annuler cette ligne retire-t-il au compte de la ligne l'accès aux leçons de la formation
	 * (`losesLessons`) ? C'est `Course::canReadLessons()` posé pour ce compte et non pour le compte
	 * connecté : l'e-mail et la confirmation partent pendant le geste d'un autre, l'équipe du réseau
	 * quand elle annule une ligne ou une date. Lot L10e, option A tranchée par V-Learning : l'accès
	 * suit la confirmation, et la règle est dite là où elle s'applique.
	 *
	 * La réponse se dérive de `readsCourse` (`fillReadsCourse()`, lot L10e-lien), posé sur les seules
	 * lignes confirmées. Vrai seulement si toutes ces conditions tiennent :
	 * - la ligne a un compte, et elle est confirmée au moment de l'annulation (`wasConfirmed()`) : une
	 *   demande ou une attente n'a jamais donné l'accès, même quand le compte a commencé la formation
	 *   par un autre droit (reprise R3) ;
	 * - le compte ne lira plus la fiche une fois la ligne annulée (`readsCourse` faux) ;
	 * - la formation a au moins une leçon : sans leçon, il n'y a rien à perdre, alors que le droit de
	 *   lire la fiche se perd comme ailleurs.
	 * Le test `DateEnrollmentLessons` compare cette réponse au droit réel, compte de la ligne connecté,
	 * avant et après l'annulation.
	 *
	 * Toutes les lignes sont de la formation `$eCourse` : la liste des inscrits la pose sur chacune
	 * de ses lignes.
	 */
	public static function fillLosesLessons(Course $eCourse, \Collection $cDateEnrollment): void {

		// Sans clone : la réponse se pose sur les lignes de l'appelant, pas sur des copies.
		self::fillReadsCourse($eCourse, $cDateEnrollment->find(fn(DateEnrollment $eDateEnrollment) => self::wasConfirmed($eDateEnrollment), clone: FALSE));

		self::fillLosesLessonsFromReading($eCourse, $cDateEnrollment);

	}

	/**
	 * Le compte de la ligne lira-t-il encore la fiche de la formation une fois cette ligne annulée
	 * (`readsCourse`), quel que soit l'état de la ligne ? C'est `Course::canRead()` posé pour ce
	 * compte, la date de la ligne retirée de ses dates confirmées. Le lien « Voir les autres dates »
	 * des e-mails d'annulation le suit : la fiche pour qui la lit encore, la page publique du réseau
	 * sinon, que la ligne annulée ait été confirmée ou seulement demandée (lot L10e-lien).
	 *
	 * Vrai si l'une de ces conditions tient :
	 * - il est de l'équipe du réseau (`Course::canWrite()`, lue sur ses adhésions comme
	 *   `MemberLib::getOnline()` les lit) ;
	 * - il a une autre ligne confirmée sur une autre date de la formation, passée comprise
	 *   (`hasConfirmed()`, la date de la ligne exclue : la réponse est la même avant et après
	 *   l'écriture), et la formation est datée.
	 * - la formation est retenue au catalogue public, qui l'ouvre à tous (lot L14) ;
	 * - il est de l'équipe de Ouvretaferme et la formation lui a été proposée (`Course::isDecider()`,
	 *   que `canDecide()` pose pour le compte connecté).
	 * `Course::isTeam()` est toujours faux sur une formation datée, et l'inscription aux leçons n'y
	 * ouvre rien : elle ne vaut droit de lecture que pour une formation en ligne, qui n'a pas de
	 * date, donc aucune ligne ici. Aucun partage non plus : une formation datée est d'un réseau, dont
	 * l'ouverture aux membres ne donne pas la lecture. Une ligne sans compte ne lit rien.
	 *
	 * Une requête par condition pour toute la liste, jamais une par ligne, et aucune quand aucune
	 * ligne n'a de compte.
	 */
	public static function fillReadsCourse(Course $eCourse, \Collection $cDateEnrollment): void {

		$eCourse->expects(['type', 'community', 'catalog']);

		$users = [];
		$usersDeciding = [];

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eDateEnrollment['readsCourse'] = FALSE;

			if($eDateEnrollment['user']->notEmpty()) {

				$users[] = $eDateEnrollment['user']['id'];

				if(Course::isDecider($eDateEnrollment['user'])) {
					$usersDeciding[] = $eDateEnrollment['user']['id'];
				}

			}

		}

		if($users === []) {
			return;
		}

		$cDateEnrollmentOther = $eCourse->isDated()
			? DateEnrollment::model()
				->select(['user', 'date'])
				->whereUser('IN', $users)
				->whereStatus(DateEnrollment::CONFIRMED)
				->whereDate('IN', new DateModel()
					->select('id')
					->whereCourse($eCourse)
					->getSql())
				->getCollection()
			: new \Collection();

		$usersKeeping = self::getStaffUsers($eCourse['community'], $users);

		if(
			$eCourse['catalog'] === Course::ACCEPTED or
			$eCourse->acceptTutorial()
		) {
			$usersKeeping = $users;
		} else if($eCourse['catalog'] !== Course::PRIVATE) {
			$usersKeeping = array_merge($usersKeeping, $usersDeciding);
		}

		foreach($cDateEnrollment as $eDateEnrollment) {

			if($eDateEnrollment['user']->empty()) {
				continue;
			}

			$user = $eDateEnrollment['user']['id'];
			$date = $eDateEnrollment['date']['id'];

			$hasOther = $cDateEnrollmentOther->find(fn(DateEnrollment $eDateEnrollmentOther) => (
				$eDateEnrollmentOther['user']['id'] === $user and
				$eDateEnrollmentOther['date']['id'] !== $date
			))->notEmpty();

			$eDateEnrollment['readsCourse'] = (
				in_array($user, $usersKeeping, TRUE) or
				$hasOther
			);

		}

	}

	/**
	 * Une ligne à compte qui donnait l'accès : confirmée avant l'écriture ; après, l'e-mail lit une
	 * ligne annulée, dont `confirmedAt` dit ce qu'elle était (`DateEnrollment::isRequest()` :
	 * l'annulation le garde, la réouverture le repose selon le nouvel état, et aucun geste ne ramène
	 * une ligne confirmée en attente).
	 */
	private static function wasConfirmed(DateEnrollment $eDateEnrollment): bool {

		return (
			$eDateEnrollment['user']->notEmpty() and
			(
				$eDateEnrollment['status'] === DateEnrollment::CONFIRMED or
				(
					$eDateEnrollment['status'] === DateEnrollment::CANCELED and
					$eDateEnrollment->isRequest() === FALSE
				)
			)
		);

	}

	/**
	 * `losesLessons` posé d'après `readsCourse`, que l'appelant a posé au moins sur les lignes
	 * confirmées : la seule écriture de la règle, pour la liste des inscrits comme pour l'e-mail
	 * d'annulation. La lecture des leçons ne se paie que si une ligne perd la fiche.
	 */
	private static function fillLosesLessonsFromReading(Course $eCourse, \Collection $cDateEnrollment): void {

		$cDateEnrollmentLosing = $cDateEnrollment->find(fn(DateEnrollment $eDateEnrollment) => (
			self::wasConfirmed($eDateEnrollment) and
			$eDateEnrollment['readsCourse'] === FALSE
		), clone: FALSE);

		// Une formation en ligne n'a pas de date : une ligne confirmée n'y a jamais donné l'accès.
		$losesLessons = (
			$cDateEnrollmentLosing->notEmpty() and
			$eCourse->isDated() and
			ChapterLib::hasLessons($eCourse)
		);

		foreach($cDateEnrollment as $eDateEnrollment) {
			$eDateEnrollment['losesLessons'] = FALSE;
		}

		foreach($cDateEnrollmentLosing as $eDateEnrollment) {
			$eDateEnrollment['losesLessons'] = $losesLessons;
		}

	}

	/**
	 * La même question pour les lignes ouvertes d'une date, que son annulation annulerait :
	 * `losesLessons` est vrai sur la date si l'une d'elles perd l'accès. Le panneau d'annulation de
	 * la date le dit, sans nommer personne.
	 */
	public static function fillLosesLessonsByDate(Date $eDate): void {

		$eDate->expects(['course']);

		$cDateEnrollment = self::getOpenByDate($eDate);
		self::fillLosesLessons($eDate['course'], $cDateEnrollment);

		$eDate['losesLessons'] = $cDateEnrollment->find(fn(DateEnrollment $eDateEnrollment) => $eDateEnrollment['losesLessons'])->notEmpty();

	}

	/**
	 * La même question pour une seule ligne, que le panneau de la personne pose avant qu'elle
	 * n'annule. L'e-mail d'annulation pose en plus `readsCourse` sur sa ligne (`fillCanceled()`).
	 */
	public static function losesLessons(DateEnrollment $e): bool {

		$e->expects(['user', 'status', 'date' => ['course']]);

		self::fillLosesLessons($e['date']['course'], new \Collection([$e]));

		return $e['losesLessons'];

	}

	/**
	 * Parmi ces comptes, ceux de l'équipe du réseau : les adhésions présentes d'une structure active,
	 * lues comme `MemberLib::getOnline()` les lit pour `Course::canWrite()`.
	 *
	 * @param int[] $users
	 * @return int[]
	 */
	public static function getStaffUsers(\structure\Structure $eStructureCommunity, array $users): array {

		$cMember = \structure\Member::model()
			->select(\structure\Member::getSelection())
			->whereUser('IN', $users)
			->whereStructure($eStructureCommunity)
			->whereStructureStatus(\structure\Member::ACTIVE)
			->whereStatus(\structure\Member::IN)
			->getCollection();

		$staff = [];

		foreach($cMember as $eMember) {

			if($eMember->isStaff()) {
				$staff[] = $eMember['user']['id'];
			}

		}

		return $staff;

	}

	/**
	 * Pointe la personne présente : l'heure et qui l'a pointée. Les heures ne s'écrivent pas, elles
	 * suivent la durée de la date tant que l'équipe ne les corrige pas (`getAttendedMinutes()`).
	 */
	public static function attend(DateEnrollment $e): void {

		$e['attendedAt'] = \Clock::datetime();
		$e['attendedBy'] = \user\ConnectionLib::getOnline();

		self::update($e, ['attendedAt', 'attendedBy']);

	}

	/**
	 * Retire le pointage, et avec lui les heures corrigées : pointée de nouveau, la personne repart
	 * de la durée de la date.
	 */
	public static function unattend(DateEnrollment $e): void {

		$e['attendedAt'] = NULL;
		$e['attendedBy'] = new \user\User();
		$e['attendedMinutes'] = NULL;

		self::update($e, ['attendedAt', 'attendedBy', 'attendedMinutes']);

	}

	/**
	 * Avec la formation, sans prévenir personne : elle disparaît. Appelée par
	 * `DateLib::deleteByCourse()`, sous la transaction de `CourseLib::delete()`.
	 */
	public static function deleteByDate(Date $eDate): void {

		$cDateEnrollment = DateEnrollment::model()
			->select(['id'])
			->whereDate($eDate)
			->getCollection();

		foreach($cDateEnrollment as $eDateEnrollment) {
			parent::delete($eDateEnrollment);
		}

	}

	/**
	 * La ligne du compte sur l'une des dates de chacune de ces formations, passées comprises,
	 * indexée par formation : sa place confirmée s'il en a une, sinon sa demande ou sa place en
	 * liste d'attente. C'est ce que la carte d'une formation datée proposée par un réseau dit de lui
	 * (`ShareLib::fillProposed()`).
	 */
	public static function getOpenByUserAndCourses(\user\User $eUser, \Collection $cCourse): \Collection {

		if($cCourse->empty()) {
			return new \Collection();
		}

		$cDateEnrollment = DateEnrollment::model()
			->select([
				'id', 'status',
				'course' => new \Sql('m2.course', 'int')
			])
			->join(Date::model(), 'm1.date = m2.id')
			->where('m1.user', $eUser)
			->where('m1.status', 'IN', [DateEnrollment::REQUESTED, DateEnrollment::WAITING, DateEnrollment::CONFIRMED])
			->where('m2.course', 'IN', $cCourse)
			->getCollection();

		$cDateEnrollmentByCourse = new \Collection();

		foreach($cDateEnrollment as $eDateEnrollment) {

			$courseId = $eDateEnrollment['course'];

			if(
				$cDateEnrollmentByCourse->offsetExists($courseId) === FALSE or
				$eDateEnrollment['status'] === DateEnrollment::CONFIRMED
			) {
				$cDateEnrollmentByCourse[$courseId] = $eDateEnrollment;
			}

		}

		return $cDateEnrollmentByCourse;

	}

	/**
	 * La personne a-t-elle une inscription confirmée à l'une des dates de la formation, passées
	 * comprises ? C'est ce qui ouvre « Commencer la formation » sur une formation datée
	 * (`Enrollment::acceptCreate()`) : la fiche ne charge que les dates à venir, et la journée
	 * passée n'enlève pas l'accès aux leçons. Une date annulée a annulé ses lignes avec elle.
	 */
	public static function hasConfirmed(\user\User $eUser, Course $eCourse): bool {

		if($eUser->empty()) {
			return FALSE;
		}

		return DateEnrollment::model()
			->join(Date::model(), 'm1.date = m2.id')
			->where('m1.user', $eUser)
			->where('m1.status', DateEnrollment::CONFIRMED)
			->where('m2.course', $eCourse)
			->exists();

	}

	/**
	 * La personne est-elle confirmée à une date de la formation dont le réseau a ouvert le quiz ? Une
	 * seule suffit : inscrite à deux dates, elle passe dès que l'une l'ouvre. Une ligne annulée ne
	 * compte plus, même après un quiz réussi. C'est la question de `Lesson::canAnswer()`.
	 */
	public static function hasQuizOpened(\user\User $eUser, Course $eCourse): bool {

		if($eUser->empty()) {
			return FALSE;
		}

		return DateEnrollment::model()
			->join(Date::model(), 'm1.date = m2.id')
			->where('m1.user', $eUser)
			->where('m1.status', DateEnrollment::CONFIRMED)
			->where('m2.course', $eCourse)
			->where('m2.quizOpenedAt IS NOT NULL')
			->exists();

	}

	/**
	 * La personne a-t-elle, à une date de la formation, une demande qui attend la confirmation du
	 * réseau ou une place en liste d'attente : inscrite, elle n'est pas encore confirmée. Les dates
	 * se citent par `getSql()`, même base ; les lignes se lisent par la clé unique `[user, date]`.
	 */
	public static function hasUnconfirmed(\user\User $eUser, Course $eCourse): bool {

		if($eUser->empty()) {
			return FALSE;
		}

		return DateEnrollment::model()
			->whereUser($eUser)
			->whereDate('IN', new DateModel()
				->select('id')
				->whereCourse($eCourse)
				->getSql())
			->whereStatus('IN', [DateEnrollment::REQUESTED, DateEnrollment::WAITING])
			->exists();

	}

	/**
	 * Les inscriptions à qui le rappel part : confirmées, pas encore rappelées, confirmées depuis plus
	 * de 24 heures (la confirmation vient de leur écrire), à une date planifiée qui commence demain
	 * ou après-demain. Deux jours et non un : le passage d'une nuit manquée est rattrapé par celui
	 * de la nuit suivante. Jamais le jour même, où le rappel arriverait trop tard pour servir.
	 *
	 * Les dates se citent par l'index `startAt`, les lignes se lisent par `[date, status, requestedAt]`.
	 */
	public static function getRemindable(): \Collection {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate('IN', self::applyLearning(new DateModel())
				->select('id')
				->whereStatus(Date::PLANNED)
				->whereStartAt('>=', new \Sql('CURDATE() + INTERVAL 1 DAY'))
				->whereStartAt('<', new \Sql('CURDATE() + INTERVAL 3 DAY'))
				->getSql())
			->whereStatus(DateEnrollment::CONFIRMED)
			->whereRemindedAt(NULL)
			->whereConfirmedAt('<', new \Sql('NOW() - INTERVAL 1 DAY'))
			->getCollection();

	}

	/**
	 * Le rappel d'avant la date, une fois par ligne : la date d'envoi se pose dans la condition de
	 * l'écriture, et l'e-mail ne part que si la ligne a été écrite. Une ligne par transaction : une
	 * chute n'emporte que la sienne, que le passage suivant reprend. Rend le nombre d'e-mails remis à
	 * `SendLib`, rappels et alertes au réseau, comme `\amap\DutyLib::remind()` : une place dont le
	 * compte est fermé est marquée rappelée sans e-mail, et le passage suivant ne la relit pas.
	 *
	 * Le rappel d'une visio dont le réseau n'a pas envoyé le lien, qu'il reste à poser ou à envoyer
	 * (`Date::isLinkAwaited()`), part quand même, en disant que le lien suit
	 * (`DateEnrollmentUi::getFacts()`) : il ne révèle pas un lien que le réseau n'a pas envoyé. Le
	 * réseau en est alerté après le passage, une fois par date (`getLinkMissingMails()`).
	 */
	public static function remind(): int {

		$sent = 0;

		// Les visios dont le rappel est parti sans lien, par identifiant : une alerte par date.
		$datesLinkAwaited = [];

		foreach(self::getRemindable() as $eDateEnrollment) {

			$eDateEnrollment['remindedAt'] = new \Sql('NOW()');

			DateEnrollment::model()->beginTransaction();

				$affected = DateEnrollment::model()
					->select(['remindedAt'])
					->whereRemindedAt(NULL)
					->update($eDateEnrollment);

				if($affected === 0) {
					DateEnrollment::model()->commit();
					continue;
				}

				$mails = self::getMails($eDateEnrollment, 'reminded');

				self::send($mails);

			DateEnrollment::model()->commit();

			$sent += count($mails);

			if(
				$mails !== [] and
				$eDateEnrollment['date']->isLinkAwaited()
			) {
				$datesLinkAwaited[$eDateEnrollment['date']['id']] = $eDateEnrollment['date'];
			}

		}

		foreach($datesLinkAwaited as $eDate) {

			$mails = self::getLinkMissingMails($eDate);

			self::send($mails);

			$sent += count($mails);

		}

		return $sent;

	}

	/**
	 * L'alerte au réseau quand le rappel d'une visio est parti sans son lien, à son adresse de
	 * contact, celle qui reçoit déjà les annulations : la séance est dans un ou deux jours, et les
	 * personnes confirmées ont lu que le lien suivrait. Une par date et par passage du rappel qui
	 * rappelle quelqu'un sans lien, donc au plus deux par date. Elle dit ce qui reste à faire, poser
	 * le lien puis l'envoyer, ou seulement l'envoyer. Le nombre dit les personnes à qui le lien
	 * partira (`getLinkRecipients()`, la liste que `sendLinked()` parcourt), et pas seulement celles
	 * que ce passage vient de rappeler : une place confirmée dont le compte est fermé n'y entre pas.
	 *
	 * @return array<array{to: string, content: array}>
	 */
	public static function getLinkMissingMails(Date $eDate): array {

		$eDate->expects(['course' => ['community']]);

		$eStructureCommunity = \structure\StructureLib::getById($eDate['course']['community'], ['legalEmail']);

		$waiting = self::getLinkRecipients(new \Collection([$eDate]))->count();

		return [['to' => $eStructureCommunity['legalEmail'], 'content' => new DateEnrollmentUi()->getLinkMissingMail($eDate, $waiting)]];

	}

	/**
	 * Le lien de la visio part à chaque personne confirmée de la date, quand le réseau l'envoie
	 * (`DateLib::sendLink()`) : c'est à partir de là qu'elle le lit sur la fiche. Une personne
	 * confirmée ensuite le reçoit avec sa confirmation. Appelée sous la transaction de
	 * `DateLib::sendLink()`, qui a écrit la trace : les lignes relisent la date avec elle, et
	 * l'e-mail porte le lien. Rend le nombre d'e-mails partis.
	 */
	public static function sendLinked(Date $eDate): int {

		$sent = 0;

		foreach(self::getLinkRecipients(new \Collection([$eDate])) as $eDateEnrollment) {

			$mails = self::getMails($eDateEnrollment, 'linkMailed');

			self::send($mails);

			$sent += count($mails);

		}

		return $sent;

	}

	/**
	 * Les lignes à qui le lien de la visio de ces dates part : les confirmées qui ont une adresse.
	 * Un compte fermé garde sa place confirmée et n'a plus d'adresse (`DateEnrollment::getEmail()`),
	 * rien ne lui part. L'envoi (`sendLinked()`) et le nombre que l'écran annonce
	 * (`fillLinkRecipients()`) lisent cette seule liste.
	 */
	public static function getLinkRecipients(\Collection $cDate): \Collection {

		return DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate('IN', $cDate)
			->whereStatus(DateEnrollment::CONFIRMED)
			->getCollection()
			->find(fn(DateEnrollment $eDateEnrollment) => $eDateEnrollment->getEmail() !== NULL);

	}

	/**
	 * Le nombre de personnes à qui le lien de la visio partirait, sur chaque date (`linkRecipients`) :
	 * `Date::acceptSendLink()` le juge, le panneau de l'envoi le chiffre.
	 */
	public static function fillLinkRecipients(\Collection $cDate): void {

		if($cDate->empty()) {
			return;
		}

		$cDateEnrollment = self::getLinkRecipients($cDate);

		foreach($cDate as $eDate) {
			$eDate['linkRecipients'] = $cDateEnrollment->find(fn(DateEnrollment $eDateEnrollment) => $eDateEnrollment['date']['id'] === $eDate['id'])->count();
		}

	}

	/**
	 * Les places d'un compte qui délivrent une attestation de présence, dans l'ordre des jours : une
	 * par date pointée d'une formation sans leçon, pour la fiche (lot L8f).
	 */
	public static function getPresenceByCourse(Course $eCourse, \user\User $eUser): \Collection {

		$cDateEnrollment = self::getAttendedByCourse($eCourse, new \Collection([$eUser]));

		self::fillLessons($cDateEnrollment);

		return $cDateEnrollment->find(fn(DateEnrollment $eDateEnrollment) => $eDateEnrollment->acceptPresenceAttestation());

	}

	/**
	 * Pose `lessons` sur la formation de la date de chaque place, ce qu'attend
	 * `DateEnrollment::acceptPresenceAttestation()` : une requête groupée pour toutes les formations
	 * de la collection. La fiche, la page à jeton, la route de l'attestation et son rendu PDF
	 * l'appellent sur leurs places avant de juger.
	 */
	public static function fillLessons(\Collection $cDateEnrollment): void {

		$lessons = self::countLessons(array_unique($cDateEnrollment->makeArray(fn(DateEnrollment $e) => $e['date']['course']['id'])));

		foreach($cDateEnrollment as $eDateEnrollment) {
			$eDateEnrollment['date']['course']['lessons'] = $lessons[$eDateEnrollment['date']['course']['id']] ?? 0;
		}

	}

	/**
	 * Le nombre de leçons par formation, une formation sans leçon absente du tableau.
	 *
	 * @param int[] $courses
	 * @return array<int, int>
	 */
	private static function countLessons(array $courses): array {

		if($courses === []) {
			return [];
		}

		return Lesson::model()
			->select([
				'course',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse('IN', $courses)
			->group('course')
			->getCollection(index: 'course')
			->makeArray(fn(Lesson $eLesson) => $eLesson['count'], preserveKeys: TRUE);

	}

	/**
	 * Ce que la liste des inscrits d'une date dit des attestations de présence (lot L8f), lu sur ses
	 * places déjà chargées (`getByDate()`) :
	 * - `course.lessons`, sur la date et sur la date de chaque place, une seule formation pour toutes ;
	 * - `attestationRecipients` : les places qui attendent la leur
	 *   (`DateEnrollment::isAttestationAwaited()`), que `Date::acceptSendAttestations()` juge et que
	 *   le panneau de l'envoi chiffre ;
	 * - `attestationMailed` et `attestationMailedAt` : les places pointées qui l'ont reçue, et le
	 *   dernier envoi, que la ligne de présence écrit.
	 */
	public static function fillAttestationRecipients(Date $eDate, \Collection $cDateEnrollment): void {

		$lessons = self::countLessons([$eDate['course']['id']])[$eDate['course']['id']] ?? 0;

		$eDate['course']['lessons'] = $lessons;

		foreach($cDateEnrollment as $eDateEnrollment) {
			$eDateEnrollment['date']['course']['lessons'] = $lessons;
		}

		$cDateEnrollmentMailed = $cDateEnrollment->find(fn(DateEnrollment $e) => $e->hasPresenceAttestation() and $e['attestationMailedAt'] !== NULL);

		$eDate['attestationRecipients'] = $cDateEnrollment->find(fn(DateEnrollment $e) => $e->isAttestationAwaited())->count();
		$eDate['attestationMailed'] = $cDateEnrollmentMailed->count();
		$eDate['attestationMailedAt'] = $cDateEnrollmentMailed->empty() ? NULL : max($cDateEnrollmentMailed->getColumn('attestationMailedAt'));

	}

	/**
	 * Les attestations de présence d'une date partent par e-mail (lot L8f), une fois par place, dans
	 * la forme d'`invite()` : la date d'envoi se pose dans la condition de l'écriture, l'e-mail ne part
	 * que si la ligne a été écrite, une transaction par place. Deux gestes simultanés n'envoient
	 * donc qu'une fois. Une place qui lève défait la sienne et arrête la boucle : les places d'avant
	 * restent envoyées, celles d'après partent au geste suivant. La condition reprend aussi la
	 * confirmation et le pointage, qu'un dépointage entre la lecture et l'écriture aurait retirés.
	 * Rend le nombre d'attestations parties.
	 */
	public static function sendAttestations(Date $eDate): int {

		$cDateEnrollment = self::getByDate($eDate);

		self::fillLessons($cDateEnrollment);

		$sent = 0;

		foreach($cDateEnrollment as $eDateEnrollment) {

			if($eDateEnrollment->isAttestationAwaited() === FALSE) {
				continue;
			}

			$eDateEnrollment['attestationMailedAt'] = new \Sql('NOW()');

			DateEnrollment::model()->beginTransaction();

				$affected = DateEnrollment::model()
					->select(['attestationMailedAt'])
					->whereAttestationMailedAt(NULL)
					->whereStatus(DateEnrollment::CONFIRMED)
					->where('attendedAt IS NOT NULL')
					->update($eDateEnrollment);

				if($affected === 0) {
					DateEnrollment::model()->commit();
					continue;
				}

				self::send(self::getMails($eDateEnrollment, 'attestationMailed'));

			DateEnrollment::model()->commit();

			$sent++;

		}

		return $sent;

	}

	/**
	 * Le jour de début de la date a changé : le rappel parti annonçait l'ancien, il repart pour le
	 * nouveau. Appelée sous la transaction de `DateLib::update()`.
	 */
	public static function resetRemindedByDate(Date $eDate): void {

		// Un tableau de valeurs et non un élément : un élément porteur d'un id réduirait l'écriture à
		// sa seule ligne (`Module::getUpdateCondition()`).
		DateEnrollment::model()
			->whereDate($eDate)
			->update(['remindedAt' => NULL]);

	}

	/**
	 * Les places à qui l'invitation à donner son avis part (lot L15c, Q2) : confirmées, pas encore
	 * invitées, d'une date planifiée terminée hier ou avant-hier. Deux jours et non un, comme le
	 * rappel : le passage d'une nuit manquée est rattrapé par celui de la nuit suivante.
	 *
	 * Balaie toute la base : seul le cron l'appelle, jamais un test ni une recette en dev, où une ligne
	 * à l'adresse d'un compte de `MailSetting::$devSendOnly` recevrait l'e-mail pour de vrai.
	 */
	public static function getReviewable(): \Collection {

		return self::filterReviewable(
			self::applyReviewable(DateEnrollment::model())->getCollection()
		);

	}

	/**
	 * La même condition, bornée aux lignes données : la forme que les tests appellent.
	 */
	public static function getReviewableAmong(\Collection $cDateEnrollment): \Collection {

		if($cDateEnrollment->empty()) {
			return new \Collection();
		}

		return self::filterReviewable(
			self::applyReviewable(DateEnrollment::model())
				->whereId('IN', $cDateEnrollment)
				->getCollection()
		);

	}

	/**
	 * Les dates se citent par l'index `startAt`, borné à trente jours, une date durant au plus
	 * quelques jours ; `endAt`, sans index, ne trie que ce reste. Les lignes se lisent par
	 * `[date, status, requestedAt]`.
	 */
	private static function applyReviewable(DateEnrollmentModel $mDateEnrollment): DateEnrollmentModel {

		return $mDateEnrollment
			->select(DateEnrollment::getSelection())
			->whereDate('IN', self::applyLearning(new DateModel())
				->select('id')
				->whereStatus(Date::PLANNED)
				->whereStartAt('>=', new \Sql('CURDATE() - INTERVAL 30 DAY'))
				->whereStartAt('<', new \Sql('CURDATE()'))
				->whereEndAt('>=', new \Sql('CURDATE() - INTERVAL 2 DAY'))
				->whereEndAt('<', new \Sql('CURDATE()'))
				->getSql())
			->whereStatus(DateEnrollment::CONFIRMED)
			->whereReviewMailedAt(NULL);

	}

	/**
	 * Les dates des formations ouvertes, pour les crons qui balaient toute la base : celles des groupes
	 * qui acceptent les formations par leur type et leur interrupteur
	 * (`CourseLib::applyCommunityLearning()`), et parmi elles, tant que les formations ne sont pas
	 * ouvertes à tous, celles des structures de `LearningSetting::STRUCTURE_BETA`. Les formations de
	 * l'équipe, sans structure, ne restent que dans le premier cas. La requête des dates étant
	 * elle-même citée sous `s`, les formations prennent le préfixe `t` et l'interrupteur `u`.
	 */
	private static function applyLearning(DateModel $mDate): DateModel {

		$mCourse = CourseLib::applyCommunityLearning(new CourseModel(), 't', 'u')
			->select('id');

		if(LearningSetting::$active === FALSE) {
			$mCourse->whereCommunity('IN', LearningSetting::STRUCTURE_BETA);
		}

		return $mDate->whereCourse('IN', $mCourse->getSql('t'));

	}

	/**
	 * Ce que la requête ne dit pas sans citer une troisième table : la formation au catalogue public,
	 * une adresse où écrire (un compte fermé n'en a plus), aucun avis déjà donné par la personne,
	 * depuis la fiche ou par une autre place, et un compte qui n'écrit pas la formation. Les deux
	 * derniers se lisent sur l'avis à écrire par le lien, que l'invitation ouvre : elle ne mène jamais
	 * à une page qui refuse.
	 */
	private static function filterReviewable(\Collection $cDateEnrollment): \Collection {

		return $cDateEnrollment->find(function(DateEnrollment $eDateEnrollment) {

			if(
				$eDateEnrollment['date']['course']->isInCatalog() === FALSE or
				$eDateEnrollment->getEmail() === NULL
			) {
				return FALSE;
			}

			$eReview = ReviewLib::getCreateElementByDateEnrollment($eDateEnrollment);

			return (
				$eReview['eReviewExisting']->empty() and
				$eReview->canCreateByLink()
			);

		});

	}

	/**
	 * L'invitation, une fois par ligne, dans la forme de `remind()` : la date d'envoi se pose dans la
	 * condition de l'écriture, l'e-mail ne part que si la ligne a été écrite, une ligne par
	 * transaction. Rend le nombre d'invitations parties.
	 */
	public static function invite(\Collection $cDateEnrollment): int {

		$invited = 0;

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eDateEnrollment['reviewMailedAt'] = new \Sql('NOW()');

			DateEnrollment::model()->beginTransaction();

				$affected = DateEnrollment::model()
					->select(['reviewMailedAt'])
					->whereReviewMailedAt(NULL)
					->update($eDateEnrollment);

				if($affected === 0) {
					DateEnrollment::model()->commit();
					continue;
				}

				self::send(self::getMails($eDateEnrollment, 'reviewInvited'));

			DateEnrollment::model()->commit();

			$invited++;

		}

		return $invited;

	}

	/**
	 * L'e-mail d'ouverture du quiz, à chaque personne confirmée de la date au moment de l'ouverture.
	 * Une personne confirmée ensuite n'en reçoit pas : l'e-mail de sa confirmation la mène à la
	 * formation, où le quiz est ouvert. Appelée sous la transaction de `DateLib::openQuiz()`. Rend
	 * le nombre d'e-mails partis.
	 */
	public static function sendQuizOpened(Date $eDate): int {

		$eDate->expects(['eLessonQuiz' => ['questions', 'needed']]);

		$cDateEnrollment = DateEnrollment::model()
			->select(DateEnrollment::getSelection())
			->whereDate($eDate)
			->whereStatus(DateEnrollment::CONFIRMED)
			->getCollection();

		$sent = 0;

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eDateEnrollment['date'] = $eDate;

			$mails = self::getMails($eDateEnrollment, 'quizOpened');

			self::send($mails);

			$sent += count($mails);

		}

		return $sent;

	}

	/**
	 * Un compte existe-t-il à l'adresse de la ligne (`accountExists`) : celui de la ligne, ou pour une
	 * ligne sans compte celui qui la reprendra à sa connexion. L'e-mail et l'écran qui suit l'envoi
	 * ne proposent alors pas d'en créer un.
	 */
	public static function fillAccount(DateEnrollment $e): void {

		$e['accountExists'] = (
			$e['user']->notEmpty() or
			\user\UserLib::getByEmail($e['email'], ['id'])->notEmpty()
		);

	}

	/**
	 * Rattache au compte qui se connecte les lignes sans compte faites à son adresse sur la page
	 * publique (lot L10c, arbitrage 1 a : toute connexion, adresse vérifiée ou non, comme
	 * `selling\UserObserverLib::signUpCreate()` rattache les clients ; le retrait tient à l'appel dans
	 * `UserObserverLib`). La saisie reste sur la ligne, le compte se lit
	 * d'abord (`DateEnrollment::getName()`).
	 *
	 * Quand le compte a déjà sa ligne sur la date, une seule reste (arbitrage 4) : l'ouverte ; si les
	 * deux le sont, celle dont l'état vaut le plus (`CONFIRMED` > `REQUESTED` > `WAITING`), à
	 * égalité celle du compte, qui garde son rang d'attente ; si les deux sont annulées, celle du
	 * compte. L'autre est supprimée, sans e-mail : c'est la même personne.
	 *
	 * Les avis donnés par ces lignes prennent le compte, et suivent la ligne gardée (lot L15c,
	 * `ReviewLib::attachByUser()`).
	 */
	public static function attachByUser(\user\User $eUser): void {

		$eUser->expects(['id', 'email']);

		$cDateEnrollment = DateEnrollment::model()
			->select(['id', 'date', 'status'])
			->whereEmail($eUser['email'])
			->whereUser(NULL)
			->getCollection();

		if($cDateEnrollment->empty()) {
			return;
		}

		$cDateEnrollmentAccount = DateEnrollment::model()
			->select(['id', 'date', 'status'])
			->whereUser($eUser)
			->whereDate('IN', $cDateEnrollment->getColumnCollection('date'))
			->getCollection(index: 'date');

		DateEnrollment::model()->beginTransaction();

			ReviewLib::attachByUser($eUser, $cDateEnrollment);

			foreach($cDateEnrollment as $eDateEnrollment) {

				$date = $eDateEnrollment['date']['id'];

				if($cDateEnrollmentAccount->offsetExists($date)) {

					$eDateEnrollmentAccount = $cDateEnrollmentAccount[$date];

					// L'avis porté par la ligne écartée passe sur la ligne gardée avant qu'elle ne parte.
					if(self::getRank($eDateEnrollment) <= self::getRank($eDateEnrollmentAccount)) {
						ReviewLib::moveDateEnrollment($eDateEnrollment, $eDateEnrollmentAccount);
						parent::delete($eDateEnrollment);
						continue;
					}

					// La clé `[user, date]` n'admet pas deux lignes du compte : celle qu'on écarte part
					// avant que l'autre ne prenne le compte.
					ReviewLib::moveDateEnrollment($eDateEnrollmentAccount, $eDateEnrollment);
					parent::delete($eDateEnrollmentAccount);

				}

				$eDateEnrollment['user'] = $eUser;

				DateEnrollment::model()
					->select(['user'])
					->update($eDateEnrollment);

			}

		DateEnrollment::model()->commit();

	}

	/**
	 * Ce que vaut une ligne au rattachement : une ligne annulée ne vaut rien, une place confirmée
	 * le plus.
	 */
	private static function getRank(DateEnrollment $eDateEnrollment): int {

		return match($eDateEnrollment['status']) {
			DateEnrollment::CANCELED => 0,
			DateEnrollment::WAITING => 1,
			DateEnrollment::REQUESTED => 2,
			DateEnrollment::CONFIRMED => 3,
		};

	}

	public static function existsByDate(Date $eDate): bool {

		return DateEnrollment::model()
			->whereDate($eDate)
			->exists();

	}

	/**
	 * Les e-mails qu'un changement d'état envoie, destinataire et contenu : ce qui est envoyé est
	 * exactement ce que rend cette méthode, que les tests comptent.
	 *
	 * - `created` : l'inscription immédiate confirmée est annoncée ; une demande ou une attente ne
	 *   l'est pas, l'écran vient de le dire à la personne et le réseau la lit dans sa liste. Une
	 *   ligne de la page publique reçoit un accusé de réception, qui porte son jeton : c'est par lui
	 *   seul qu'elle se relit et s'annule. `createPublic()` décide s'il part (`sendReceived()`). Il
	 *   n'invite à créer un compte que sur une formation qui a des leçons (`fillLessons()`),
	 *   l'invitation les promettant ;
	 * - `confirmed`, `seatGiven` : à la personne ;
	 * - `canceled` : à l'autre côté, au réseau quand la personne annule, à la personne sinon ; une
	 *   ligne annulée avec sa date le dit, plutôt qu'une inscription annulée seule. Quand le compte
	 *   de la ligne perd l'accès aux leçons (`losesLessons`), l'e-mail le dit aussi ; quand il ne lit
	 *   plus la fiche (`readsCourse`), « Voir les autres dates » mène à la page publique du réseau ;
	 * - `linkMailed` : à la personne confirmée, quand le réseau envoie le lien de la visio
	 *   (`sendLinked()`) ;
	 * - `reminded` : à la personne confirmée, deux jours avant la date (`remind()`), avec la même
	 *   condition de leçons que l'accusé de réception pour l'invitation au compte ;
	 * - `quizOpened` : à la personne confirmée, quand le réseau ouvre le quiz (`sendQuizOpened()`).
	 *   Les deux ne proposent de créer un compte qu'à une adresse qui n'en a pas (`fillAccount()`) ;
	 * - `reviewInvited` : à la personne confirmée, le lendemain de la date (`invite()`) ;
	 * - `attestationMailed` : à la personne pointée d'une formation sans leçon, quand le réseau envoie
	 *   les attestations de présence (`sendAttestations()`), avec le lien de la sienne.
	 *
	 * L'accusé de réception, le lien de la visio, le rappel, l'ouverture du quiz et l'annulation par
	 * le réseau disent à qui écrire : l'encadrant contact de la formation
	 * (`SupervisorLib::getContactByCourse()`), ou à défaut le réseau. Les e-mails qui partent au réseau
	 * vont toujours à son adresse légale.
	 *
	 * @return array<array{to: string, content: array}>
	 */
	public static function getMails(DateEnrollment $e, string $event): array {

		$e->expects(['user', 'email', 'token', 'date' => ['course' => ['type', 'community']]]);

		$ui = new DateEnrollmentUi();

		if($event === 'canceled') {
			self::fillCanceled($e);
		} else if($e->isPublic()) {
			// Le lien à jeton se construit sur l'adresse du réseau, que la ligne ne porte pas.
			$e['eCommunity'] = \structure\CommunityLib::getByStructure($e['date']['course']['community']);
		}

		// Un compte fermé n'a plus d'adresse (`DateEnrollment::getEmail()`) : rien ne part à la personne,
		// mais l'annulation par elle va au réseau, donc la sortie se fait ici et non en tête.
		$toUser = fn(array $content): array => ($e->getEmail() === NULL) ? [] : [['to' => $e->getEmail(), 'content' => $content]];

		if($event === 'created') {

			if($e->isPublic()) {

				self::fillLessons(new \Collection([$e]));

				return $toUser($ui->getReceivedMail($e, \structure\StructureLib::getById($e['date']['course']['community'], ['name', 'legalEmail']), SupervisorLib::getContactByCourse($e['date']['course'])));
			}

			return ($e['status'] === DateEnrollment::CONFIRMED) ? $toUser($ui->getConfirmedMail($e)) : [];

		}

		if($event === 'reviewInvited') {
			return $toUser($ui->getReviewInvitedMail($e));
		}

		if($event === 'attestationMailed') {
			return $toUser($ui->getAttestationMail($e));
		}

		if($event === 'linkMailed') {
			return $toUser($ui->getLinkedMail($e, \structure\StructureLib::getById($e['date']['course']['community'], ['name', 'legalEmail']), SupervisorLib::getContactByCourse($e['date']['course'])));
		}

		if(in_array($event, ['reminded', 'quizOpened'], TRUE)) {

			self::fillAccount($e);

			if($event === 'reminded') {
				self::fillLessons(new \Collection([$e]));
			}

			$eStructureCommunity = \structure\StructureLib::getById($e['date']['course']['community'], ['name', 'legalEmail']);
			$eSupervisorContact = SupervisorLib::getContactByCourse($e['date']['course']);

			return match($event) {
				'reminded' => $toUser($ui->getRemindMail($e, $eStructureCommunity, $eSupervisorContact)),
				'quizOpened' => $toUser($ui->getQuizOpenedMail($e, $eStructureCommunity, $eSupervisorContact)),
			};

		}

		return match($event) {

			'confirmed' => $toUser($ui->getConfirmedMail($e)),
			'seatGiven' => $toUser($ui->getSeatGivenMail($e)),
			// L'adresse de contact du réseau, celle que lui écrivent déjà les départs de fermes
			// (`AffiliationAiLib`), reçoit l'annulation d'une personne. L'annulation par le réseau
			// donne à la personne l'adresse de l'encadrant contact, ou à défaut celle du réseau,
			// l'adresse d'envoi ne recevant pas de réponse.
			'canceled' => match($e['canceledBy']) {
				DateEnrollment::USER => [['to' => \structure\StructureLib::getById($e['date']['course']['community'], ['legalEmail'])['legalEmail'], 'content' => $ui->getCanceledByUserMail($e)]],
				DateEnrollment::COMMUNITY => $toUser($ui->getCanceledByCommunityMail($e, \structure\StructureLib::getById($e['date']['course']['community'], ['name', 'legalEmail']), SupervisorLib::getContactByCourse($e['date']['course']))),
				DateEnrollment::DATE => $toUser($ui->getDateCanceledMail($e))
			}

		};

	}

	/**
	 * Ce que lit l'e-mail d'une ligne annulée, selon qui l'annule :
	 * - par la personne, l'e-mail part au réseau et ne dit que la perte des leçons (`losesLessons`) ;
	 * - par le réseau ou avec la date, il part à la personne, et « Voir les autres dates » mène là où
	 *   elle les lit encore (`readsCourse`) : la fiche, ou la page publique du réseau, dont l'adresse
	 *   (`eCommunity`) n'est lue que dans ce cas et pour une ligne de la page publique.
	 * Publique pour qu'un test rende un e-mail d'annulation par l'UI sur la même ligne que `getMails()`.
	 */
	public static function fillCanceled(DateEnrollment $e): void {

		$e->expects(['user', 'status', 'confirmedAt', 'canceledBy', 'token', 'date' => ['course' => ['type', 'community']]]);

		$eCourse = $e['date']['course'];

		if($e['canceledBy'] === DateEnrollment::USER) {
			self::fillLosesLessons($eCourse, new \Collection([$e]));
			return;
		}

		$cDateEnrollment = new \Collection([$e]);

		self::fillReadsCourse($eCourse, $cDateEnrollment);
		self::fillLosesLessonsFromReading($eCourse, $cDateEnrollment);

		if(
			$e->isPublic() or
			$e['readsCourse'] === FALSE
		) {
			$e['eCommunity'] = \structure\CommunityLib::getByStructure($eCourse['community']);
		}

	}

	private static function send(array $mails): void {

		foreach($mails as ['to' => $to, 'content' => $content]) {

			\main\MailLib::getInstance()
				->setTo($to)
				->setContent(...$content)
				->send();

		}

	}

}
?>
