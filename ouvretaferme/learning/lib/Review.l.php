<?php
namespace learning;

/**
 * Les avis sur les formations du catalogue public (lot L15c) : qui peut noter, l'écriture et ses
 * gestes (répondre, signaler, garder, masquer, réafficher), les lectures de la fiche, de la carte
 * et de l'onglet d'administration.
 *
 * Qui peut noter est un état posé sur l'élément avant toute validation : `experiencedAt`, le jour où
 * la personne a suivi la formation, ou `NULL` si elle ne l'a pas suivie (`getCreateElement()`).
 * `Review::acceptCreate()` le juge sans requête.
 *
 * Tout vit dans la base commune (avis, formations, inscriptions, comptes) : aucune lecture ne
 * touche une base `structure_<id>`.
 */
class ReviewLib extends ReviewCrud {

	/** Les avis que le fil de la fiche montre à la fois, du plus récent au plus ancien */
	const PER_PAGE = 10;
	const READ_PER_PAGE = 20;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['rating', 'text'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['rating', 'text'];
	}

	/**
	 * L'avis à écrire par un compte : `experiencedAt` dit s'il a suivi la formation, et sur une
	 * formation datée `dateEnrollment` porte la place qui l'ouvre. `eReviewExisting` porte l'avis que
	 * le compte a déjà donné, que `create()` réécrirait, pour que `acceptCreate()` le juge. Un visiteur
	 * reçoit un élément dont `canCreate()` refuse l'écriture.
	 */
	public static function getCreateElement(Course $eCourse, \user\User $eUser): Review {

		$eReview = new Review([
			'course' => $eCourse,
			'user' => $eUser,
			'dateEnrollment' => new DateEnrollment(),
			'experiencedAt' => NULL,
			'rating' => NULL,
			'eReviewExisting' => new Review()
		]);

		if($eUser->empty()) {
			return $eReview;
		}

		$eReview['eReviewExisting'] = self::getByCourseAndUser($eCourse, $eUser);

		if($eCourse->isDated()) {

			$eDateEnrollment = self::getExperiencedDateEnrollment($eCourse, $eUser);

			if($eDateEnrollment->notEmpty()) {
				$eReview['dateEnrollment'] = $eDateEnrollment;
				$eReview['experiencedAt'] = mb_substr($eDateEnrollment['date']['startAt'], 0, 10);
			}

		} else {
			$eReview['experiencedAt'] = self::getOnlineExperiencedAt($eCourse, $eUser);
		}

		return $eReview;

	}

	/**
	 * L'avis à écrire par le lien d'une inscription, sans compte connecté : seule la ligne du lien
	 * l'ouvre, confirmée à une date planifiée terminée. Une ligne déjà rattachée à un compte écrit
	 * l'avis au nom de ce compte, comme il l'écrirait depuis la fiche. `writesCourse` dit si ce compte
	 * écrit la formation, ce que `Review::canCreateByLink()` pèse à la place du compte connecté.
	 */
	public static function getCreateElementByDateEnrollment(DateEnrollment $eDateEnrollment): Review {

		$eDateEnrollment->expects(['user', 'email', 'status', 'date' => ['status', 'startAt', 'endAt', 'course']]);

		$qualifies = (
			$eDateEnrollment['status'] === DateEnrollment::CONFIRMED and
			$eDateEnrollment['date']['status'] === Date::PLANNED and
			$eDateEnrollment['date']['endAt'] < \Clock::datetime()
		);

		return new Review([
			'course' => $eDateEnrollment['date']['course'],
			'user' => $eDateEnrollment['user'],
			'dateEnrollment' => $eDateEnrollment,
			'experiencedAt' => $qualifies ? mb_substr($eDateEnrollment['date']['startAt'], 0, 10) : NULL,
			'rating' => NULL,
			'eReviewExisting' => self::getByPerson($eDateEnrollment),
			'writesCourse' => self::writesCourse($eDateEnrollment['date']['course'], $eDateEnrollment['user'])
		]);

	}

	/**
	 * `Course::canWrite()` posé pour le compte d'une place, que le lien d'une inscription ne connecte
	 * pas : un administrateur pour une formation de l'équipe, l'équipe du réseau sinon. Une place sans
	 * compte n'écrit rien : rien ne la rattache au réseau.
	 */
	private static function writesCourse(Course $eCourse, \user\User $eUser): bool {

		$eCourse->expects(['community']);

		if($eUser->empty()) {
			return FALSE;
		}

		if($eCourse->isTeam()) {
			return $eUser->isAdmin();
		}

		return (DateEnrollmentLib::getStaffUsers($eCourse['community'], [$eUser['id']]) !== []);

	}

	/**
	 * La place la plus récente que le compte a tenue : confirmée, à une date planifiée terminée. Une
	 * date passée ne s'annule plus, ni ses lignes (`Date::acceptCancel()`) : ce qui qualifie ne se
	 * défait pas après coup.
	 */
	private static function getExperiencedDateEnrollment(Course $eCourse, \user\User $eUser): DateEnrollment {

		$cDateEnrollment = DateEnrollment::model()
			->select(['id', 'date' => ['startAt']])
			->whereUser($eUser)
			->whereStatus(DateEnrollment::CONFIRMED)
			->whereDate('IN', new DateModel()
				->select('id')
				->whereCourse($eCourse)
				->whereStatus(Date::PLANNED)
				->whereEndAt('<', new \Sql('NOW()'))
				->getSql())
			->getCollection();

		$eDateEnrollmentLast = new DateEnrollment();

		foreach($cDateEnrollment as $eDateEnrollment) {
			if(
				$eDateEnrollmentLast->empty() or
				$eDateEnrollment['date']['startAt'] > $eDateEnrollmentLast['date']['startAt']
			) {
				$eDateEnrollmentLast = $eDateEnrollment;
			}
		}

		return $eDateEnrollmentLast;

	}

	/**
	 * En ligne, le compte inscrit qui a fait toutes les leçons, comme la fiche le compte pour
	 * l'attestation (`Course::isDone()`) ; le jour est celui de la dernière leçon faite. `NULL` sinon.
	 */
	private static function getOnlineExperiencedAt(Course $eCourse, \user\User $eUser): ?string {

		if(EnrollmentLib::getByUserAndCourse($eUser, $eCourse)->empty()) {
			return NULL;
		}

		$eProgress = Progress::model()
			->select([
				'done' => new \Sql('COUNT(*)', 'int'),
				'lastDoneAt' => new \Sql('MAX(doneAt)')
			])
			->whereUser($eUser)
			->whereCourse($eCourse)
			->whereStatus(Progress::DONE)
			->get();

		$eCourseDone = new Course([
			'lessons' => Lesson::model()->whereCourse($eCourse)->count(),
			'done' => $eProgress['done']
		]);

		if($eCourseDone->isDone() === FALSE) {
			return NULL;
		}

		return mb_substr($eProgress['lastDoneAt'], 0, 10);

	}

	public static function getByCourseAndUser(Course $eCourse, \user\User $eUser): Review {

		if($eUser->empty()) {
			return new Review();
		}

		$eReview = Review::model()
			->select(Review::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->whereUser($eUser)
			->get();

		if($eReview->notEmpty()) {
			$eReview['course'] = $eCourse;
		}

		return $eReview;

	}

	public static function getByDateEnrollment(DateEnrollment $eDateEnrollment): Review {

		return Review::model()
			->select(Review::getSelection())
			->whereDateEnrollment($eDateEnrollment)
			->get();

	}

	/**
	 * L'avis sans compte d'une adresse sur une formation, quelle que soit la place qui l'a ouvert :
	 * une personne inscrite à deux dates n'a qu'un avis, et le lien de la seconde ouvre celui de la
	 * première. Deux lectures, les lignes de l'adresse sur les dates de la formation par l'`UNIQUE
	 * (email, date)`, puis l'avis par l'`UNIQUE (dateEnrollment)`.
	 */
	public static function getByCourseAndEmail(Course $eCourse, string $email): Review {

		$cDateEnrollment = DateEnrollment::model()
			->select('id')
			->whereEmail($email)
			->whereUser(NULL)
			->whereDate('IN', new DateModel()
				->select('id')
				->whereCourse($eCourse)
				->getSql())
			->getCollection();

		if($cDateEnrollment->empty()) {
			return new Review();
		}

		return Review::model()
			->select(Review::getSelection())
			->whereDateEnrollment('IN', $cDateEnrollment)
			->sort(['createdAt' => SORT_DESC])
			->get();

	}

	/**
	 * L'avis déjà donné par la personne d'une ligne : celui du compte quand la ligne en a un, celui
	 * de l'adresse sinon.
	 */
	public static function getByPerson(DateEnrollment $eDateEnrollment): Review {

		$eDateEnrollment->expects(['user', 'email', 'date' => ['course']]);

		if($eDateEnrollment['user']->notEmpty()) {
			return self::getByCourseAndUser($eDateEnrollment['date']['course'], $eDateEnrollment['user']);
		}

		return self::getByCourseAndEmail($eDateEnrollment['date']['course'], $eDateEnrollment['email']);

	}

	/**
	 * Un avis par personne et par formation : un second envoi (deux onglets, un double clic) modifie
	 * le premier au lieu de heurter l'unicité. L'équipe du réseau est prévenue d'un avis neuf
	 * seulement.
	 */
	public static function create(Review $e): void {

		$e->expects(['course', 'user', 'dateEnrollment']);

		Review::model()->beginTransaction();

			$eReviewExisting = $e['user']->notEmpty()
				? self::getByCourseAndUser($e['course'], $e['user'])
				: self::getByDateEnrollment($e['dateEnrollment']);

			if($eReviewExisting->notEmpty()) {

				$eReviewExisting['rating'] = $e['rating'];
				$eReviewExisting['text'] = $e['text'];

				self::update($eReviewExisting, ['rating', 'text']);

				$e['id'] = $eReviewExisting['id'];

				Review::model()->commit();

				return;

			}

			parent::create($e);

		Review::model()->commit();

		// Tant que le catalogue public est fermé, l'avis n'est lu que par le réseau, dans sa boite :
		// ni notification ni e-mail, seul son compteur le dit (décision, 07/10).
		if(LearningSetting::$active) {
			NotificationLib::reviewed($e);
		}

	}

	/**
	 * L'auteur modifie : un avis gardé après signalement redevient publié et peut être signalé de
	 * nouveau, son texte n'étant plus celui que l'équipe a jugé. Un avis signalé le reste, l'équipe
	 * juge le texte du jour.
	 *
	 * Une note ou un texte changés repassent l'avis en non lu (décision de V-Learning du 07/10) : le
	 * réseau a lu l'avis d'avant, pas celui-ci. `build()` a déjà posé les nouvelles valeurs sur
	 * l'élément, les anciennes se relisent donc en base avant l'écriture.
	 */
	public static function update(Review $e, array $properties = []): void {

		$e->expects(['status']);

		$e['updatedAt'] = \Clock::datetime();
		$properties[] = 'updatedAt';

		$propertiesRead = array_values(array_intersect(['rating', 'text'], $properties));

		if($propertiesRead !== []) {

			$eReviewBefore = Review::model()
				->select($propertiesRead)
				->whereId($e)
				->get();

			if($eReviewBefore->extracts($propertiesRead) !== $e->extracts($propertiesRead)) {
				$e['readAt'] = NULL;
				$e['readBy'] = new \user\User();
				$properties[] = 'readAt';
				$properties[] = 'readBy';
			}

		}

		if($e['status'] === Review::KEPT) {
			$e['status'] = Review::PUBLISHED;
			$properties[] = 'status';
		}

		parent::update($e, $properties);

	}

	public static function answer(Review $e): void {

		$e['answeredAt'] = \Clock::datetime();
		$e['answeredBy'] = \user\ConnectionLib::getOnline();

		Review::model()
			->select(['answer', 'answeredAt', 'answeredBy'])
			->update($e);

	}

	public static function deleteAnswer(Review $e): void {

		$e['answer'] = NULL;
		$e['answeredAt'] = NULL;
		$e['answeredBy'] = new \user\User();

		Review::model()
			->select(['answer', 'answeredAt', 'answeredBy'])
			->update($e);

	}

	/**
	 * La condition sur l'état dans l'écriture : deux signalements simultanés n'en font qu'un.
	 */
	public static function report(Review $e): void {

		$e['status'] = Review::REPORTED;
		$e['reportedAt'] = \Clock::datetime();
		$e['reportedBy'] = \user\ConnectionLib::getOnline();

		Review::model()
			->select(['status', 'reportReason', 'reportedAt', 'reportedBy'])
			->whereStatus(Review::PUBLISHED)
			->update($e);

	}

	public static function keep(Review $e): void {

		$e['status'] = Review::KEPT;

		self::moderate($e, ['status'], [Review::REPORTED]);

	}

	/**
	 * L'e-mail part dans la transaction, et seulement si la ligne a changé d'état : un second geste
	 * sur un avis déjà masqué n'écrit pas deux fois à son auteur.
	 */
	public static function hide(Review $e): void {

		$e['status'] = Review::HIDDEN;

		Review::model()->beginTransaction();

			$affected = self::moderate($e, ['status', 'hideReason'], [Review::PUBLISHED, Review::REPORTED, Review::KEPT]);

			if($affected > 0) {
				self::send(self::getMails($e, 'hidden'));
			}

		Review::model()->commit();

	}

	/**
	 * Réafficher rend l'avis gardé : l'équipe l'a jugé, le réseau ne le signale plus. Le motif du
	 * masquage reste sur la ligne, que plus rien n'affiche.
	 */
	public static function show(Review $e): void {

		$e['status'] = Review::KEPT;

		self::moderate($e, ['status'], [Review::HIDDEN]);

	}

	/**
	 * La condition sur l'état dans l'écriture, comme pour `report()` : l'état que le geste suppose
	 * (`acceptKeep()`, `acceptHide()`, `acceptShow()`) a pu changer entre l'affichage et le clic,
	 * et « Garder » ne doit pas réafficher un avis masqué entre-temps.
	 */
	private static function moderate(Review $e, array $properties, array $statusesFrom): int {

		$e['moderatedAt'] = \Clock::datetime();
		$e['moderatedBy'] = \user\ConnectionLib::getOnline();

		return Review::model()
			->select(array_merge($properties, ['moderatedAt', 'moderatedBy']))
			->whereStatus('IN', $statusesFrom)
			->update($e);

	}

	/**
	 * Les e-mails d'un geste de l'équipe, destinataire et contenu, sans rien envoyer : ce que les
	 * tests lisent est exactement ce qui part.
	 *
	 * - `hidden` : à l'auteur, avec le motif. Rien quand il n'a plus d'adresse (compte fermé,
	 *   `DropLib::closeNow()` vidant `email`).
	 *
	 * @return array<array{to: string, content: array}>
	 */
	public static function getMails(Review $e, string $event): array {

		$e->expects(['user', 'dateEnrollment', 'course', 'createdAt', 'hideReason']);

		$email = $e->getAuthorEmail();

		if($email === NULL) {
			return [];
		}

		return match($event) {
			'hidden' => [['to' => $email, 'content' => new ReviewUi()->getHiddenMail($e)]]
		};

	}

	private static function send(array $mails): void {

		foreach($mails as ['to' => $to, 'content' => $content]) {

			\main\MailLib::getInstance()
				->setTo($to)
				->setContent(...$content)
				->send();

		}

	}

	/**
	 * Pose `reviews` (le nombre d'avis affichés) et `rating` (leur moyenne, `NULL` sous le seuil) sur
	 * chaque formation. Une formation hors du catalogue public n'affiche pas ses avis : 0 et `NULL`,
	 * sans lecture. Une requête groupée pour toute la collection, par l'index `(course, createdAt)`.
	 */
	public static function fillSummary(\Collection $cCourse): void {

		$cCourseCatalog = $cCourse->find(fn(Course $eCourse) => $eCourse->isInCatalog());

		$cReview = $cCourseCatalog->empty()
			? new \Collection()
			: Review::model()
				->select([
					'course',
					'count' => new \Sql('COUNT(*)', 'int'),
					'average' => new \Sql('AVG(rating)', 'float')
				])
				->whereCourse('IN', $cCourseCatalog)
				->whereStatus('!=', Review::HIDDEN)
				->group('course')
				->getCollection(index: 'course');

		foreach($cCourse as $eCourse) {

			$eCourse['reviews'] = $cReview->offsetExists($eCourse['id']) ? $cReview[$eCourse['id']]['count'] : 0;
			$eCourse['rating'] = ($eCourse['reviews'] >= LearningSetting::REVIEWS_THRESHOLD) ? $cReview[$eCourse['id']]['average'] : NULL;

		}

	}

	/**
	 * Tout ce que la fiche montre des avis : le résumé, la répartition, la première page du fil et
	 * l'avis du compte connecté (`eReview`), existant ou à écrire. Hors du catalogue public, la fiche
	 * n'a pas de section et ne garde que cet avis, que le réseau seul lit.
	 *
	 * Les avis masqués restent dans le fil de qui écrit la formation et de l'équipe, qui y lisent le
	 * motif ; les autres ne les voient ni dans le fil ni dans les nombres.
	 *
	 * `hasReviewSection` dit si la fiche porte la section. Inscription fermée (lot annuaire), une
	 * formation sans avis n'en a pas : plus personne ne s'y confirme sur Ouvretaferme, et la mention
	 * promettrait des avis qui ne viendront pas. Ceux qui existent viennent de places confirmées
	 * avant la fermeture : la section et sa mention restent vraies pour eux.
	 */
	public static function fillCourse(Course $eCourse, \user\User $eUser): void {

		self::fillSummary(new \Collection([$eCourse['id'] => $eCourse]));

		$eReview = self::getByCourseAndUser($eCourse, $eUser);

		if($eReview->empty()) {
			$eReview = self::getCreateElement($eCourse, $eUser);
		}

		$eCourse['eReview'] = $eReview;

		if($eCourse->isInCatalog() === FALSE) {
			$eCourse['cReview'] = new \Collection();
			$eCourse['reviewsListed'] = 0;
			$eCourse['reviewDistribution'] = [];
			$eCourse['hasReviewSection'] = FALSE;
			return;
		}

		$withHidden = ($eCourse->canWrite() or $eCourse->canDecide());

		$eCourse['cReview'] = self::getForCourse($eCourse, $withHidden);
		$eCourse['reviewsListed'] = self::countForCourse($eCourse, $withHidden);
		$eCourse['reviewDistribution'] = ($eCourse['rating'] === NULL) ? [] : self::getDistribution($eCourse);
		$eCourse['hasReviewSection'] = (
			$eCourse->isExternal() === FALSE or
			$eCourse['cReview']->notEmpty()
		);

	}

	/**
	 * La boite des avis d'un groupe : les avis non lus de ses formations, au catalogue public ou non,
	 * le plus récent d'abord.
	 */
	public static function getNewByStructure(\structure\Structure $eStructure): \Collection {

		return self::whereStructure($eStructure)
			->select(Review::getSelection())
			->whereReadAt(NULL)
			->sort(['id' => SORT_DESC])
			->getCollection();

	}

	/**
	 * Les avis lus, à partir de celui qui précède `$before` : la page suivante reprend sous le dernier
	 * montré, et un avis lu entre-temps ne décale pas la suite (forme de `CommentLib`).
	 */
	public static function getReadByStructure(\structure\Structure $eStructure, ?int $before, int $number): \Collection {

		return self::whereStructure($eStructure)
			->select(Review::getSelection())
			->whereReadAt('!=', NULL)
			->whereId('<', $before, if: $before !== NULL)
			->sort(['id' => SORT_DESC])
			->getCollection(0, $number);

	}

	/**
	 * Lu à chaque affichage des formations du groupe, par l'index `(course, readAt)`.
	 */
	public static function countNewByStructure(\structure\Structure $eStructure): int {

		return self::whereStructure($eStructure)
			->whereReadAt(NULL)
			->count();

	}

	/**
	 * Les avis des formations du groupe, formations et avis dans la base commune.
	 */
	private static function whereStructure(\structure\Structure $eStructure): ReviewModel {

		return Review::model()->whereCourse('IN', Course::model()
			->select('id')
			->whereCommunity($eStructure)
			->getSql());

	}

	/**
	 * Le premier membre de l'équipe qui le marque lu le marque pour tous : un avis déjà lu garde son
	 * premier lecteur.
	 */
	public static function read(Review $e): void {

		$e['readAt'] = new \Sql('NOW()');
		$e['readBy'] = \user\ConnectionLib::getOnline();

		Review::model()
			->select(['readAt', 'readBy'])
			->whereReadAt(NULL)
			->update($e);

	}

	public static function readAllByStructure(\structure\Structure $eStructure): void {

		// Écriture de masse en tableau, comme `CommentLib::readAllByStructure()` : marquer lu n'a
		// aucune règle par avis à rejouer, et un élément porteur d'un id réduirait l'écriture à sa
		// seule ligne (`Module::getUpdateCondition()`).
		self::whereStructure($eStructure)
			->whereReadAt(NULL)
			->update([
				'readAt' => new \Sql('NOW()'),
				'readBy' => \user\ConnectionLib::getOnline()
			]);

	}

	/**
	 * Le fil de la fiche, du plus récent au plus ancien par la date de publication et jamais par la
	 * note (L111-7-2) : `updatedAt` laisserait un auteur remonter son avis en le retouchant. Les avis
	 * masqués n'y sont que pour qui écrit la formation, que la page désigne.
	 */
	public static function getForCourse(Course $eCourse, bool $withHidden, int $offset = 0, int $number = self::PER_PAGE): \Collection {

		return Review::model()
			->select(Review::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->whereStatus('!=', Review::HIDDEN, if: $withHidden === FALSE)
			->sort(['createdAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection($offset, $number)
			->setColumn('course', $eCourse);

	}

	public static function countForCourse(Course $eCourse, bool $withHidden): int {

		return Review::model()
			->whereCourse($eCourse)
			->whereStatus('!=', Review::HIDDEN, if: $withHidden === FALSE)
			->count();

	}

	/**
	 * Le nombre d'avis affichés par note, de 5 à 1, chaque note présente même sans avis. Bornée à des
	 * jours compris, c'est la note du Bilan sur sa période (`UsageLib::get()`) : les mêmes avis que la
	 * page publique, un avis signalé y restant visible tant que l'équipe ne l'a pas masqué.
	 *
	 * @return array<int, int>
	 */
	public static function getDistribution(Course $eCourse, string $from = '', string $to = ''): array {

		$distribution = array_fill_keys([5, 4, 3, 2, 1], 0);

		$cReview = Review::model()
			->select([
				'rating',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus('!=', Review::HIDDEN)
			->whereCreatedAt('>=', $from.' 00:00:00', if: $from !== '')
			->whereCreatedAt('<=', $to.' 23:59:59', if: $to !== '')
			->group('rating')
			->getCollection();

		foreach($cReview as $eReview) {
			$distribution[$eReview['rating']] = $eReview['count'];
		}

		return $distribution;

	}

	/**
	 * Les avis affichés d'une formation entre deux jours compris, indexés par le premier jour de leur
	 * pas (`UsageLib::getStepSql()`). Un pas sans avis n'y figure pas.
	 */
	public static function countBySteps(Course $eCourse, string $from, string $to, string $step): array {

		$cReview = Review::model()
			->select([
				'step' => UsageLib::getStepSql('createdAt', $step),
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus('!=', Review::HIDDEN)
			->whereCreatedAt('>=', $from.' 00:00:00')
			->whereCreatedAt('<=', $to.' 23:59:59')
			->group(UsageLib::getStepSql('createdAt', $step))
			->getCollection();

		$steps = [];

		foreach($cReview as $eReview) {
			$steps[$eReview['step']] = $eReview['count'];
		}

		return $steps;

	}

	/**
	 * Les avis que les réseaux ont signalés, le plus ancien signalement d'abord : l'équipe les traite
	 * dans l'ordre où ils sont arrivés.
	 */
	public static function getReported(): \Collection {

		return Review::model()
			->select(Review::getSelection())
			->whereStatus(Review::REPORTED)
			->sort(['reportedAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	public static function getHidden(): \Collection {

		return Review::model()
			->select(Review::getSelection())
			->whereStatus(Review::HIDDEN)
			->sort(['moderatedAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection();

	}

	/**
	 * Les avis donnés sans compte par les lignes que `DateEnrollmentLib::attachByUser()` rattache au
	 * compte, dans sa transaction et avant qu'elle ne supprime une ligne en double : ils prennent le
	 * compte. Si le compte a déjà son avis sur la même formation, le plus récent reste et l'autre
	 * est supprimé, sans e-mail : c'est la même personne.
	 */
	public static function attachByUser(\user\User $eUser, \Collection $cDateEnrollment): void {

		$cReview = Review::model()
			->select(['id', 'course', 'createdAt', 'updatedAt'])
			->whereDateEnrollment('IN', $cDateEnrollment)
			->whereUser(NULL)
			->getCollection();

		foreach($cReview as $eReview) {

			$eReviewAccount = Review::model()
				->select(['id', 'createdAt', 'updatedAt'])
				->whereCourse($eReview['course'])
				->whereUser($eUser)
				->get();

			if($eReviewAccount->notEmpty()) {

				if(self::getWrittenAt($eReviewAccount) >= self::getWrittenAt($eReview)) {
					self::delete($eReview);
					continue;
				}

				self::delete($eReviewAccount);

			}

			$eReview['user'] = $eUser;

			Review::model()
				->select(['user'])
				->update($eReview);

		}

	}

	private static function getWrittenAt(Review $eReview): string {
		return $eReview['updatedAt'] ?? $eReview['createdAt'];
	}

	/**
	 * La ligne qui portait l'avis est supprimée par le rattachement : l'avis passe sur la ligne gardée
	 * de la même personne, sur la même date.
	 */
	public static function moveDateEnrollment(DateEnrollment $eDateEnrollmentFrom, DateEnrollment $eDateEnrollmentTo): void {

		$eReview = Review::model()
			->select(['id'])
			->whereDateEnrollment($eDateEnrollmentFrom)
			->get();

		if($eReview->empty()) {
			return;
		}

		$eReview['dateEnrollment'] = $eDateEnrollmentTo;

		Review::model()
			->select(['dateEnrollment'])
			->update($eReview);

	}

	/**
	 * Avec la formation, dans la transaction de `CourseLib::delete()`, avant ses dates et leurs lignes.
	 */
	public static function deleteByCourse(Course $eCourse): void {

		$cReview = Review::model()
			->select('id')
			->whereCourse($eCourse)
			->getCollection();

		foreach($cReview as $eReview) {
			self::delete($eReview);
		}

	}

}
?>
