<?php
namespace learning;

/**
 * Les dates d'une formation de réseau : des sessions en visio ou sur place, à venir, tenues ou
 * annulées. Une date passée ou annulée reste écrite, l'éditeur la relit ; seules les planifiées à
 * venir comptent pour les cartes et pour la publication.
 */
class DateLib extends DateCrud {

	const MORNING = 'morning';
	const AFTERNOON = 'afternoon';

	/**
	 * `sequences` avant `minutes`, dont l'étape `attended` compare la durée à celle des séances
	 * postées. `startAt` et `endAt` ne se postent plus : ils bornent les séances (`Date::properties()`).
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['sequences', 'minutes', 'link', 'seats', 'externalUrl', 'priceFunded', 'price', 'funding', 'fundingNote'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['sequences', 'minutes', 'link', 'seats', 'externalUrl', 'priceFunded', 'price', 'funding', 'fundingNote'];
	}

	public static function getByCourse(Course $eCourse): \Collection {

		return Date::model()
			->select(Date::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

	}

	/**
	 * Les demi-journées que la feuille d'émargement fait signer, par jour, sur les seules séances en
	 * présentiel : une séance en visio ne s'émarge pas. Une séance signe le matin quand elle commence
	 * avant midi, l'après-midi quand elle finit après 13:00 ; deux séances du même jour réunissent
	 * leurs demi-journées.
	 *
	 * Une séance posée entre midi et 13:00 n'est ni l'un ni l'autre : elle garde une colonne, celle
	 * de l'après-midi, pour qu'aucune feuille ne se rende sans case à signer.
	 *
	 * @return array<string, string[]> les demi-journées (`MORNING`, `AFTERNOON`) de chaque jour
	 */
	public static function getHalfDays(Date $e): array {

		$e->expects(['cDateSequence']);

		$days = [];

		foreach($e['cDateSequence'] as $eDateSequence) {

			if($eDateSequence['mode'] !== DateSequence::ONSITE) {
				continue;
			}

			$halves = $days[$eDateSequence->getDay()] ?? [];

			if(substr($eDateSequence['startAt'], 11, 5) < '12:00') {
				$halves[] = self::MORNING;
			}

			if(
				substr($eDateSequence['endAt'], 11, 5) > '13:00' or
				substr($eDateSequence['startAt'], 11, 5) >= '12:00'
			) {
				$halves[] = self::AFTERNOON;
			}

			$days[$eDateSequence->getDay()] = array_values(array_intersect([self::MORNING, self::AFTERNOON], $halves));

		}

		return $days;

	}

	/**
	 * Ce que la fiche montre : les dates qui ne sont pas encore passées, annulées comprises, pour
	 * qu'un lecteur qui comptait sur l'une d'elles voie qu'elle ne se tient plus.
	 */
	public static function getUpcomingByCourse(Course $eCourse): \Collection {

		return Date::model()
			->select(Date::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->where('endAt >= NOW()')
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

	}

	/**
	 * Les dates dont l'offre se choisit encore, dans l'onglet Diffusion (lot L15a) : celles
	 * que `applyUpcoming()` retient, les mêmes qui ouvrent le catalogue à la formation
	 * (`CourseLib::isOffered()`). Une date annulée ou passée ne se vend plus.
	 */
	public static function getPlannedByCourse(Course $eCourse): \Collection {

		return self::applyUpcoming(Date::model())
			->select(Date::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

	}

	/**
	 * La date est-elle la seule, parmi les dates à venir de sa formation, à porter une offre, alors
	 * que la formation est proposée ou retenue au catalogue public (lot L15a, porte Q4) ? Passée sans
	 * offre, elle laisserait au catalogue une formation que personne ne peut acheter : c'est ce que
	 * `CourseLib::isOffered()` refuse à la demande, et `Course::offer.catalog` à une formation en ligne.
	 */
	public static function holdsCatalog(Date $e): bool {

		$e->expects(['id', 'course' => ['catalog']]);

		if(in_array($e['course']['catalog'], [Course::PROPOSED, Course::ACCEPTED], TRUE) === FALSE) {
			return FALSE;
		}

		return self::applyUpcoming(new DateModel())
			->whereCourse($e['course'])
			->whereId('!=', $e['id'])
			->whereOffer('!=', Date::NONE)
			->exists() === FALSE;

	}

	/**
	 * La date de la formation où la personne est confirmée, celle que l'écran du quiz lui annonce :
	 * la prochaine qui n'est pas finie, sinon la dernière tenue. Vide sans inscription confirmée.
	 * Les lignes se citent par `getSql()`, même base.
	 */
	public static function getConfirmedByUser(\user\User $eUser, Course $eCourse): Date {

		if($eUser->empty()) {
			return new Date();
		}

		$cDate = Date::model()
			->select(Date::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->whereId('IN', new DateEnrollmentModel()
				->select('date')
				->whereUser($eUser)
				->whereStatus(DateEnrollment::CONFIRMED)
				->getSql())
			->sort(['startAt' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

		$eDateLast = new Date();

		foreach($cDate as $eDate) {

			if($eDate->isPast() === FALSE) {
				return $eDate;
			}

			$eDateLast = $eDate;

		}

		return $eDateLast;

	}

	/**
	 * La seule condition « à venir » des compteurs et de la bande des cartes, écrite une fois : une
	 * date planifiée qui n'est pas encore finie. Une date annulée n'y entre pas.
	 */
	public static function applyUpcoming(DateModel $m): DateModel {

		return $m
			->whereStatus(Date::PLANNED)
			->where('endAt >= NOW()');

	}

	/**
	 * Les dates que la page publique d'un réseau propose, dans l'ordre du calendrier : à venir,
	 * planifiées, de ses formations publiées et datées. Lue sans `Course::canRead()`, qui suppose un
	 * membre ; `Date::acceptPublic()` dit la même règle sur un élément chargé.
	 */
	public static function getPublicByCommunity(\structure\Structure $eStructureCommunity): \Collection {

		return self::applyPublicByCommunity(Date::model(), $eStructureCommunity)
			->select(Date::getSelection())
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * La page publique a-t-elle une date à proposer : la racine du réseau n'est une page qu'à cette
	 * condition, et le panneau des réglages ne donne l'adresse et le code qu'à elle.
	 */
	public static function existsPublicByCommunity(\structure\Structure $eStructureCommunity): bool {

		return self::applyPublicByCommunity(Date::model(), $eStructureCommunity)->exists();

	}

	/**
	 * La règle de `getPublicByCommunity()` sur un modèle que l'appelant termine : la liste la lit, et
	 * `DateEnrollmentLib::createPublic()` la cite pour borner le plafond par adresse aux dates du
	 * réseau. Les formations se citent par `getSql()`, même base : un `IN` sur l'index
	 * `learningCourse.community`.
	 */
	public static function applyPublicByCommunity(DateModel $m, \structure\Structure $eStructureCommunity): DateModel {

		return self::applyUpcoming($m)
			->whereCourse('IN', CourseLib::applyPublicByCommunity(new CourseModel(), $eStructureCommunity)
				->select('id')
				->getSql());

	}

	/**
	 * Les dates à venir d'un groupe qui portent une inscription non annulée, confirmée, demandée ou
	 * en liste d'attente : tant qu'il en reste une, le groupe ne peut pas éteindre ses formations
	 * (`Feature::hasCommunityLearning.enrolled`). Formations et inscriptions sont citées dans la même
	 * base, sur les index `learningCourse.community` et `learningDateEnrollment.date`.
	 */
	public static function getEnrolledByCommunity(\structure\Structure $eStructureCommunity): \Collection {

		return self::applyUpcoming(Date::model())
			->select([
				'id', 'startAt',
				'course' => ['name']
			])
			->whereCourse('IN', new CourseModel()
				->select('id')
				->whereCommunity($eStructureCommunity)
				->getSql())
			->where(new DateEnrollmentModel()
				->whereDate(new \Sql('m1.id'))
				->whereStatus('!=', DateEnrollment::CANCELED)
				->existsSql())
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	public static function countUpcomingByCourse(Course $eCourse): int {

		return self::applyUpcoming(Date::model())
			->whereCourse($eCourse)
			->count();

	}

	/**
	 * Le nombre de dates à venir et la prochaine, sur les formations de réseau d'une liste, par une
	 * requête groupée comme les compteurs de `CourseLib::fillCounts()`. La prochaine date se pose
	 * aussi en élément (`eDateNext`, vide sans date à venir) : la carte y lit les montants, les
	 * financeurs et le mode de diffusion, et `course` y est reposé sur la formation de la liste,
	 * dont la date reprend les montants qu'elle n'a pas. Les autres formations n'ont pas de date,
	 * et n'en portent pas non plus le compte : leurs écrans ne le lisent pas.
	 *
	 * Les dates à venir se posent aussi en entier (`cDateUpcoming`, dans l'ordre du calendrier),
	 * chacune avec sa formation reposée, pour l'onglet Prochaines dates du catalogue
	 * (`getAgenda()`) : elles sont déjà lues pour trouver la prochaine.
	 *
	 * Le compte dit aussi si la formation a une date à venir (`hasDateUpcoming`), comme
	 * `CourseLib::fillHasDateUpcoming()` le dit d'une seule : la pause des inscriptions ne s'affiche
	 * qu'à cette condition (`Course::isEnrollmentPauseShown()`).
	 */
	public static function fillUpcoming(\Collection $cCourse): void {

		// Sans clone : les compteurs se posent sur les éléments de la liste, pas sur des copies.
		$cCourseNetwork = $cCourse->find(fn(Course $eCourse) => $eCourse->isDated(), clone: FALSE);

		if($cCourseNetwork->empty()) {
			return;
		}

		$cDateCount = self::applyUpcoming(Date::model())
			->select([
				'course',
				'dates' => new \Sql('COUNT(*)', 'int'),
				'dateNext' => new \Sql('MIN(startAt)')
			])
			->whereCourse('IN', $cCourseNetwork)
			->group('course')
			->getCollection(index: 'course');

		$cDateUpcoming = self::applyUpcoming(Date::model())
			->select(['id', 'course', 'startAt', 'endAt', 'minutes', 'offer', 'priceFunded', 'price', 'funding', 'fundingNote', 'cDateSequence' => Date::getSelection()['cDateSequence']])
			->whereCourse('IN', $cCourseNetwork)
			->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

		$eDateNextByCourse = [];
		$datesByCourse = [];

		foreach($cDateUpcoming as $eDate) {
			$eDateNextByCourse[$eDate['course']['id']] ??= $eDate;
			$datesByCourse[$eDate['course']['id']][] = $eDate;
		}

		foreach($cCourseNetwork as $eCourse) {

			$eCourse['cDateUpcoming'] = new \Collection($datesByCourse[$eCourse['id']] ?? []);

			foreach($eCourse['cDateUpcoming'] as $eDate) {
				$eDate['course'] = $eCourse;
			}

			if($cDateCount->offsetExists($eCourse['id'])) {
				$eCourse['dates'] = $cDateCount[$eCourse['id']]['dates'];
				$eCourse['dateNext'] = $cDateCount[$eCourse['id']]['dateNext'];
			} else {
				$eCourse['dates'] = 0;
				$eCourse['dateNext'] = NULL;
			}

			$eCourse['hasDateUpcoming'] = ($eCourse['dates'] > 0);

			$eCourse['eDateNext'] = $eDateNextByCourse[$eCourse['id']] ?? new Date();

		}

	}

	/**
	 * L'onglet Prochaines dates du catalogue : les dates à venir des formations d'une liste passée par
	 * `fillUpcoming()`, une ligne par date, dans l'ordre du calendrier. Une formation en ligne n'a pas
	 * de date et n'y paraît pas.
	 */
	public static function getAgenda(\Collection $cCourse): \Collection {

		$cDate = new \Collection();

		foreach($cCourse as $eCourse) {

			if($eCourse->isDated() === FALSE) {
				continue;
			}

			$cDate->mergeCollection($eCourse['cDateUpcoming']);

		}

		return $cDate->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC]);

	}

	/**
	 * Les dates de l'agenda rangées par mois de leur début, une collection par mois, sous sa clé
	 * `AAAA-MM`. Les dates arrivent triées, les mois aussi.
	 */
	public static function groupByMonth(\Collection $cDate): \Collection {

		$ccDate = new \Collection();

		foreach($cDate as $eDate) {

			$month = substr($eDate['startAt'], 0, 7);

			if($ccDate->offsetExists($month) === FALSE) {
				$ccDate[$month] = new \Collection();
			}

			$ccDate[$month][] = $eDate;

		}

		return $ccDate;

	}

	/**
	 * Une date à venir de la formation porte-t-elle une offre ? Une date annulée ou passée ne compte
	 * pas : le visiteur du catalogue ne pourrait pas s'y inscrire. `new DateModel()` et non
	 * `Date::model()` : les étapes `Course::catalog.offer` et `Course::externalOnly.catalog` la citent
	 * depuis une closure du modèle.
	 */
	public static function existsOfferedByCourse(Course $eCourse): bool {

		return self::applyUpcoming(new DateModel())
			->whereCourse($eCourse)
			->whereOffer('!=', Date::NONE)
			->exists();

	}

	/**
	 * Chaque date à venir de la formation a-t-elle sa page sur le site de l'organisme, et y en a-t-il
	 * au moins une ? C'est alors que la page de la formation devient facultative
	 * (`Course::externalUrl.uncovered`). `new DateModel()` et non `Date::model()` : l'étape la cite
	 * depuis `build()`.
	 */
	public static function hasExternalUrlOnEachUpcoming(Course $eCourse): bool {

		return (
			self::applyUpcoming(new DateModel())
				->whereCourse($eCourse)
				->exists() and
			self::applyUpcoming(new DateModel())
				->whereCourse($eCourse)
				->where('externalUrl IS NULL')
				->exists() === FALSE
		);

	}

	/**
	 * Une date planifiée à venir de la formation est-elle payée par le stagiaire sur le montant
	 * « Autre public » de la formation, faute du sien ? C'est alors que la formation ne peut plus
	 * vider ce montant ni le passer à 0 (`Course::price.trainee`). `new DateModel()` et non
	 * `Date::model()` : l'étape la cite depuis `build()`.
	 */
	public static function existsTraineeInheriting(Course $eCourse): bool {

		if($eCourse->isDated() === FALSE) {
			return FALSE;
		}

		return self::applyUpcoming(new DateModel())
			->whereCourse($eCourse)
			->whereOffer(Date::TRAINEE)
			->where('price IS NULL')
			->exists();

	}

	/**
	 * La formation a-t-elle une date, quel que soit son état ? Une date annulée compte : elle reste
	 * lisible telle qu'elle a été retirée, sur place ou en visio, et le type de sa formation ne peut
	 * plus basculer sous elle (`Course::type.dated`). `new DateModel()` et non `Date::model()` :
	 * l'étape la cite depuis `build()`.
	 */
	public static function existsByCourse(Course $eCourse): bool {

		return new DateModel()
			->whereCourse($eCourse)
			->exists();

	}

	/**
	 * La date de la formation qui commence le plus tard, bornée à un statut quand il est donné.
	 */
	private static function getLastByCourse(Course $eCourse, ?string $status = NULL): Date {

		return new DateModel()
			->select(['offer'])
			->whereCourse($eCourse)
			->whereStatus($status, if: $status !== NULL)
			->sort(['startAt' => SORT_DESC, 'id' => SORT_DESC])
			->get();

	}

	public static function create(Date $e): void {

		$e->expects(['course', 'cDateSequence']);

		self::clearOtherFormat($e);

		Date::model()->beginTransaction();

			// Une date neuve reprend l'offre de la dernière date de sa formation : un réseau qui ajoute
			// une session la vend comme les précédentes, et la change ensuite sur la page Offre et
			// catalogue. Une date annulée ne se vend plus et peut garder une offre dépassée : elle ne
			// sert de modèle qu'à défaut d'une date maintenue. La première date d'une formation naît
			// sans offre. Ses montants, eux, sont ceux qu'elle a reçus, sinon ceux de la formation :
			// sans montant « Autre public » non nul à encaisser, le paiement en ligne ne se reprend pas,
			// comme `Date::offer.priced` le refuserait à l'écran, et la date naît sans offre.
			$eDateLast = self::getLastByCourse($e['course'], Date::PLANNED);

			if($eDateLast->empty()) {
				$eDateLast = self::getLastByCourse($e['course']);
			}

			$e['offer'] = $eDateLast->empty() ? Date::NONE : $eDateLast['offer'];

			if(
				$e['offer'] === Date::TRAINEE and
				$e->acceptTraineeOffer() === FALSE
			) {
				$e['offer'] = Date::NONE;
			}

			parent::create($e);

			DateSequenceLib::replaceForDate($e, $e['cDateSequence']);

			CourseLib::synchronizeType($e['course']);
			CourseLib::touch($e['course']);

		Date::model()->commit();

	}

	/**
	 * Quand les séances sont écrites, les bornes de la date le sont avec elles (`Date::properties()`
	 * les a posées), et la marque de la formation se recalcule.
	 */
	public static function update(Date $e, array $properties): void {

		$e->expects(['course']);

		$withSequences = in_array('sequences', $properties);

		if($withSequences) {
			$properties = array_values(array_unique([...array_diff($properties, ['sequences']), 'startAt', 'endAt']));
		}

		if(
			$withSequences or
			in_array('link', $properties)
		) {
			$e->expects(['cDateSequence']);
			self::clearOtherFormat($e);
			$properties = array_values(array_unique([...$properties, 'link']));
		}

		Date::model()->beginTransaction();

			// L'élément posté porte déjà le nouveau jour et le nouveau lien : les anciens se lisent en
			// base, sous la transaction, avant l'écriture.
			$eDateBefore = (in_array('startAt', $properties) or in_array('link', $properties))
				? Date::model()
					->select(['startAt', 'link'])
					->whereId($e['id'])
					->get()
				: new Date();

			// Un lien changé ou effacé n'est plus celui que les personnes confirmées ont reçu : la
			// trace de l'envoi repart à vide dans la même écriture, et rien ne part de lui-même. La
			// carte de la date redemande l'envoi, l'inscrit relit que le lien lui sera envoyé.
			if(
				in_array('link', $properties) and
				$eDateBefore['link'] !== $e['link']
			) {
				$e['linkMailedAt'] = NULL;
				$properties[] = 'linkMailedAt';
			}

			parent::update($e, $properties);

			if($withSequences) {
				DateSequenceLib::replaceForDate($e, $e['cDateSequence']);
				CourseLib::synchronizeType($e['course']);
			}

			// Le rappel est parti pour l'ancien jour : il repart pour le nouveau. Une autre heure le
			// même jour ne relance rien, le rappel en était juste.
			if(
				in_array('startAt', $properties) and
				substr($eDateBefore['startAt'], 0, 10) !== substr($e['startAt'], 0, 10)
			) {
				DateEnrollmentLib::resetRemindedByDate($e);
			}

			CourseLib::touch($e['course']);

		Date::model()->commit();

	}

	/**
	 * Ouvre le quiz de la formation aux personnes confirmées de la date, une fois : la date se pose
	 * dans la condition de l'écriture, et un second geste, même simultané, ne touche rien. Les
	 * e-mails partent dans la même transaction, mis en file par `SendLib` : une chute annule
	 * l'ouverture et les e-mails ensemble, et le geste se refait. Rien ne remet l'ouverture à vide.
	 * Rend le nombre d'e-mails partis, aucun quand le quiz était déjà ouvert.
	 */
	public static function openQuiz(Date $e): int {

		$e->expects(['eLessonQuiz' => ['quizThreshold']]);

		// Ce que l'e-mail annonce : le nombre de questions et de bonnes réponses pour valider.
		$e['eLessonQuiz']['questions'] = QuestionLib::countByLesson($e['eLessonQuiz']);
		$e['eLessonQuiz']['needed'] = ResponseLib::getNeeded($e['eLessonQuiz']['questions'], $e['eLessonQuiz']['quizThreshold']);

		$e['quizOpenedAt'] = new \Sql('NOW()');

		Date::model()->beginTransaction();

			$affected = Date::model()
				->select(['quizOpenedAt'])
				->whereQuizOpenedAt(NULL)
				->update($e);

			if($affected === 0) {
				Date::model()->commit();
				Date::fail('quizAlreadyOpened');
				return 0;
			}

			$mails = DateEnrollmentLib::sendQuizOpened($e);

		Date::model()->commit();

		return $mails;

	}

	/**
	 * Envoie le lien de la visio aux personnes confirmées de la date, une fois par lien : la trace
	 * se pose dans la condition de l'écriture, et un second geste, même simultané, ne touche rien.
	 * Les e-mails partent dans la même transaction, mis en file par `SendLib` : une chute annule la
	 * trace et les e-mails ensemble, et le geste se refait. Seul `update()` remet la trace à vide,
	 * quand le lien change. Rend le nombre d'e-mails partis, aucun quand le lien était déjà envoyé
	 * ou n'est plus celui de l'élément.
	 */
	public static function sendLink(Date $e): int {

		$e->expects(['link']);

		$e['linkMailedAt'] = \Clock::datetime();

		Date::model()->beginTransaction();

			// La trace atteste l'envoi d'un lien précis, celui que les e-mails portent : il entre dans
			// la condition avec elle, et une date dont le lien a été changé ou effacé depuis la lecture
			// de l'élément ne correspond plus à aucune ligne.
			$affected = Date::model()
				->select(['linkMailedAt'])
				->whereLinkMailedAt(NULL)
				->whereLink($e['link'])
				->update($e);

			if($affected === 0) {
				Date::model()->commit();
				Date::fail('linkAlreadyMailed');
				return 0;
			}

			$mails = DateEnrollmentLib::sendLinked($e);

		Date::model()->commit();

		return $mails;

	}

	/**
	 * Le lien d'une date sans séance en visio ne veut rien dire : il ne s'écrit pas, quoi qu'on lui
	 * poste, et la trace de son envoi repart à vide avec lui (`update()`). Le lieu d'une séance en
	 * visio s'efface de même, séance par séance (`DateSequenceLib::create()`).
	 */
	private static function clearOtherFormat(Date $e): void {

		if($e->hasMode(DateSequence::REMOTE) === FALSE) {
			$e['link'] = NULL;
		}

	}

	/**
	 * Pose le verrou exclusif d'InnoDB sur la ligne de la date, tenu jusqu'au COMMIT de la
	 * transaction ouverte : une seconde inscription sur la même date attend là. Un `UPDATE` qui ne
	 * change rien verrouille quand même la ligne qu'il trouve, et l'ORM n'a pas de `FOR UPDATE`.
	 * L'écriture de `status` sur lui-même ne dépend pas de ce que l'élément a chargé, qu'une
	 * annulation concurrente a pu périmer. Le nombre de lignes touchées vaut toujours 0, la
	 * connexion ne comptant que les lignes changées : rien ne le lit.
	 *
	 * Toujours la seule prise de verrou d'une transaction, et toujours en premier : le cadriciel
	 * rejoue la seule instruction sur un interblocage (1213) alors qu'InnoDB a annulé toute la
	 * transaction.
	 */
	public static function lock(Date $e): void {

		Date::model()->update($e, ['status' => new \Sql('status')]);

	}

	/**
	 * L'écriture de la date vient en premier et tient son verrou, comme `lock()` à l'inscription :
	 * une inscription concurrente attend, puis trouve la date annulée.
	 */
	public static function cancel(Date $e): void {

		$e['status'] = Date::CANCELED;

		Date::model()->beginTransaction();

			self::update($e, ['status']);

			DateEnrollmentLib::cancelByDate($e);

			CourseLib::synchronizeType($e['course']);

		Date::model()->commit();

	}

	public static function delete(Date $e): void {

		$e->expects(['course']);

		Date::model()->beginTransaction();

			// Le verrou avant de compter les inscrits, comme à l'inscription : une inscription
			// validée pendant l'attente est vue, et aucune ne se pose après le compte.
			self::lock($e);

			// Une date qui a eu des inscrits, même tous annulés, garde leur liste : c'est la trace
			// de qui s'est inscrit, de qui a annulé et quand. Elle s'annule, elle ne s'efface pas.
			if(DateEnrollmentLib::existsByDate($e)) {
				Date::model()->commit();
				Date::fail('deleteEnrolled');
				return;
			}

			// Une date passée sans inscrit peut porter une feuille signée, déposée sans personne
			// confirmée : elle partirait orpheline avec la date.
			if(DateSheet::model()->whereDate($e)->exists()) {
				Date::model()->commit();
				Date::fail('deleteSheets');
				return;
			}

			DateSequenceLib::deleteByDate($e);

			parent::delete($e);

			CourseLib::synchronizeType($e['course']);
			CourseLib::touch($e['course']);

		Date::model()->commit();

	}

	/**
	 * Avec la formation, sans la dater : c'est elle qui disparaît, avec les inscrits de ses dates.
	 * Appelée sous la transaction de `CourseLib::delete()`.
	 */
	public static function deleteByCourse(Course $eCourse): void {

		$cDate = Date::model()
			->select(['id', 'course'])
			->whereCourse($eCourse)
			->getCollection();

		foreach($cDate as $eDate) {
			DateEnrollmentLib::deleteByDate($eDate);
			DateSequenceLib::deleteByDate($eDate);
			parent::delete($eDate);
		}

	}

}
?>
