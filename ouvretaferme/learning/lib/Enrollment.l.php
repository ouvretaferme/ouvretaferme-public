<?php
namespace learning;

/**
 * L'inscription d'un compte à une formation, et la ferme au nom de laquelle il la suit.
 *
 * La ferme ne sert qu'à être notée. Elle se choisit une fois, à l'inscription, et seulement par un
 * compte qui a plusieurs fermes ; les autres n'ont rien à choisir.
 */
class EnrollmentLib extends EnrollmentCrud {

	/**
	 * La réponse à la prise en charge ne se construit que là où la question se pose : ailleurs, elle
	 * reste `NULL`, et une valeur postée à la main ne s'écrit pas.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): \Closure {
		return fn(Enrollment $e) => $e->hasFundingQuestion() ? ['structure', 'fundingRequested'] : ['structure'];
	}

	/**
	 * Le réglage d'inscription du réseau auteur d'une formation en ligne
	 * (`structure\Community::learningOnlineConfirm`), que les pages posent sur la formation
	 * (`onlineConfirm`) pour le bouton, le panneau et la question. Une formation de l'équipe, d'un
	 * groupe ou à dates n'en a pas : son inscription est toujours acquise. L'équipe du réseau qui
	 * l'écrit n'en a pas non plus, elle n'a rien à se demander à elle-même : la règle tient ici, et
	 * non dans chacun de ses lecteurs, pour que le bouton, le panneau et l'état écrit par `create()`
	 * ne puissent pas se contredire. Lu par
	 * `CommunityLib::getByStructure()` et non par `community()`, dont le cache statique tient tout le
	 * processus : un réglage changé vaut dès la demande suivante.
	 */
	public static function getOnlineConfirm(Course $eCourse): ?string {

		if($eCourse->isNetwork() === FALSE) {
			return NULL;
		}

		return self::getOnlineConfirmByCommunity($eCourse, \structure\CommunityLib::getByStructure($eCourse['community']));

	}

	/**
	 * Le même réglage, lu sur le réseau qu'une page a déjà chargé pour autre chose (son thème) :
	 * `$eCommunity` est celui de la formation, vide pour une formation qui n'est pas de réseau.
	 */
	public static function getOnlineConfirmByCommunity(Course $eCourse, \structure\Community $eCommunity): ?string {

		if(
			$eCourse->isNetwork() === FALSE or
			$eCourse->isDated() or
			$eCourse->canWrite()
		) {
			return NULL;
		}

		return $eCommunity['learningOnlineConfirm'];

	}

	/**
	 * L'état que prend une inscription envoyée maintenant, sur le modèle de
	 * `DateEnrollmentLib::getStatusForNew()` : à confirmer par le réseau toujours sous `ALWAYS`, sous
	 * `FUNDING` quand la personne demande une prise en charge, sinon acquise. Une réponse absente
	 * sous `FUNDING` est celle d'une inscription écrite sans la question, qui n'a rien demandé.
	 */
	public static function getStatusForNew(?string $onlineConfirm, ?bool $fundingRequested): string {

		return match($onlineConfirm) {
			NULL, \structure\Community::IMMEDIATE => Enrollment::CONFIRMED,
			\structure\Community::ALWAYS => Enrollment::REQUESTED,
			\structure\Community::FUNDING => ($fundingRequested === TRUE) ? Enrollment::REQUESTED : Enrollment::CONFIRMED,
		};

	}

	/**
	 * Les fermes du compte connecté, parmi lesquelles il choisit celle de l'inscription. Une ferme
	 * école ou une ferme projet n'en est pas une : c'est le filtre de
	 * `StructureLib::hasProducerByUser()`.
	 *
	 * Pour une formation de groupe, les fermes rattachées à ce groupe passent en tête : c'est parmi
	 * elles que se trouve celle que `getPreselected()` coche, et un compte qui a quarante fermes la
	 * verrait sinon au milieu de la liste, hors de l'écran. La condition sur le groupe est dans le
	 * `ON` : dans le `WHERE`, elle écarterait les fermes non rattachées au lieu de les ranger après.
	 * Une ferme rattachée au nom de deux comptes y figure deux fois, et l'index par id n'en garde
	 * qu'une.
	 */
	public static function getStructures(Course $eCourse = new Course()): \Collection {

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return new \Collection();
		}

		$mStructure = \structure\Structure::model()
			->join(\structure\Member::model(), 'm1.id = m2.structure')
			->select(['id', 'name'])
			->where('m1.type', \structure\Structure::PRODUCER)
			->where('m1.producerType', 'NOT IN', \structure\StructureSetting::PRODUCER_TYPES_COLLECTIVE)
			->where('m1.status', \structure\Structure::ACTIVE)
			->where('m2.user', $eUser)
			->where('m2.status', \structure\Member::IN);

		if(
			$eCourse->empty() or
			$eCourse->isTeam()
		) {
			$mStructure->sort(new \Sql('m1.id ASC'));
		} else {
			$mStructure
				->join(\structure\Affiliation::model(), 'm1.id = m3.structureProducer AND m3.structureCommunity = '.$eCourse['community']['id'], 'LEFT')
				->sort(new \Sql('m3.id IS NULL ASC, m1.id ASC'));
		}

		return $mStructure->getCollection(index: 'id');

	}

	/**
	 * La ferme proposée d'office : celle du compte qui est rattachée au groupe auteur de la
	 * formation. Une formation de l'équipe n'en propose aucune.
	 */
	public static function getPreselected(Course $eCourse, \Collection $cStructure): \structure\Structure {

		if(
			$eCourse->isTeam() or
			$cStructure->empty()
		) {
			return new \structure\Structure();
		}

		$eAffiliation = \structure\Affiliation::model()
			->select('structureProducer')
			->whereStructureCommunity($eCourse['community'])
			->whereStructureProducer('IN', $cStructure)
			->get();

		if($eAffiliation->empty()) {
			return new \structure\Structure();
		}

		return $cStructure[$eAffiliation['structureProducer']['id']];

	}

	/**
	 * L'inscription confirmée du compte, la seule qui ouvre la lecture : une demande se lit par
	 * `getRequestByUserAndCourse()`. Tous les accesseurs de ce Lib sont bornés aux confirmées, sauf
	 * ceux qui le disent par leur nom.
	 */
	public static function getByUserAndCourse(\user\User $eUser, Course $eCourse): Enrollment {

		if($eUser->empty()) {
			return new Enrollment();
		}

		$eEnrollment = Enrollment::model()
			->select(Enrollment::getSelectionWithoutCourse())
			->whereUser($eUser)
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->get();

		if($eEnrollment->notEmpty()) {
			$eEnrollment['course'] = $eCourse;
		}

		return $eEnrollment;

	}

	/**
	 * La demande du compte, encore à confirmer, que la fiche montre « Demande envoyée ».
	 */
	public static function getRequestByUserAndCourse(\user\User $eUser, Course $eCourse): Enrollment {

		if($eUser->empty()) {
			return new Enrollment();
		}

		$eEnrollment = Enrollment::model()
			->select(Enrollment::getSelectionWithoutCourse())
			->whereUser($eUser)
			->whereCourse($eCourse)
			->whereStatus(Enrollment::REQUESTED)
			->get();

		if($eEnrollment->notEmpty()) {
			$eEnrollment['course'] = $eCourse;
		}

		return $eEnrollment;

	}

	/**
	 * Le compte est-il inscrit à la formation ? La question de `Course::canRead()` pour la formation
	 * en ligne d'un réseau, posée sans charger l'inscription. Un visiteur n'est inscrit à rien.
	 */
	public static function isEnrolled(\user\User $eUser, Course $eCourse): bool {

		if($eUser->empty()) {
			return FALSE;
		}

		return Enrollment::model()
			->whereUser($eUser)
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->exists();

	}

	/**
	 * Les inscriptions du compte à des formations qu'il peut encore lire : une formation repassée en
	 * brouillon disparaît de sa liste sans que son inscription soit effacée.
	 */
	public static function getByUser(\user\User $eUser): \Collection {

		// La formation entière, et non celle des autres lectures d'une inscription : les cartes de
		// « Mes formations » lisent sa thématique, sa sous-thématique et son niveau.
		return Enrollment::model()
			->select(Enrollment::getSelectionWithoutCourse() + [
				'course' => Course::getSelection(),
			])
			->whereUser($eUser)
			->whereStatus(Enrollment::CONFIRMED)
			->sort(['createdAt' => SORT_DESC])
			->getCollection()
			->find(fn(Enrollment $eEnrollment) => $eEnrollment['course']->assertRead());

	}

	/**
	 * Les inscriptions des stagiaires à la formation, indexées par compte, avec la ferme au nom de
	 * laquelle chacun la suit et sa date, que l'export détaillé donne. Un stagiaire jamais inscrit
	 * n'y figure pas.
	 */
	public static function getByCourseAndUsers(Course $eCourse, \Collection $cUser): \Collection {

		if($cUser->empty()) {
			return new \Collection();
		}

		return Enrollment::model()
			->select(['id', 'user', 'createdAt', 'structure' => ['name']])
			->whereCourse($eCourse)
			->whereUser('IN', $cUser)
			->whereStatus(Enrollment::CONFIRMED)
			->getCollection(index: 'user');

	}

	/**
	 * Les lignes encore ouvertes des comptes, demandes comprises, indexées par compte : ceux que
	 * l'ouverture d'une formation ne prévient plus, une personne qui attend sa confirmation n'ayant
	 * rien à apprendre de l'e-mail qui l'invite à s'inscrire.
	 */
	public static function getOpenByCourseAndUsers(Course $eCourse, \Collection $cUser): \Collection {

		if($cUser->empty()) {
			return new \Collection();
		}

		return Enrollment::model()
			->select(['id', 'user'])
			->whereCourse($eCourse)
			->whereUser('IN', $cUser)
			->whereStatus('IN', [Enrollment::REQUESTED, Enrollment::CONFIRMED])
			->getCollection(index: 'user');

	}

	/**
	 * Les lignes encore ouvertes du compte sur ces formations, demandes comprises, indexées par
	 * formation : celles qu'un réseau ne lui propose plus, et celles où sa demande attend.
	 */
	public static function getOpenByUserAndCourses(\user\User $eUser, \Collection $cCourse): \Collection {

		if($cCourse->empty()) {
			return new \Collection();
		}

		return Enrollment::model()
			->select(['id', 'course', 'status'])
			->whereUser($eUser)
			->whereCourse('IN', $cCourse)
			->whereStatus('IN', [Enrollment::REQUESTED, Enrollment::CONFIRMED])
			->getCollection(index: 'course');

	}

	/**
	 * Toutes les lignes de la formation, quel que soit leur état : celles que sa suppression efface.
	 */
	public static function getByCourse(Course $eCourse): \Collection {

		return Enrollment::model()
			->select(['id', 'user', 'course'])
			->whereCourse($eCourse)
			->getCollection();

	}

	/**
	 * Les stagiaires que le suivi d'une formation de réseau montre, indexés par id de compte : les
	 * comptes inscrits, tous ou ceux inscrits au nom de `$eStructure`. Même forme que
	 * `ShareLib::getLearners()`, pour que la grille, l'export et la case cochée lisent l'un ou l'autre
	 * sans le savoir. Un inscrit dont la ferme a quitté le réseau, ou qui a quitté sa ferme, reste là
	 * avec ce qu'il a suivi : l'inscription est le fait, le rattachement n'entre pas dans le suivi.
	 */
	public static function getLearners(Course $eCourse, \structure\Structure $eStructure = new \structure\Structure()): \Collection {

		return Enrollment::model()
			->select(['user' => ['firstName', 'lastName']])
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->whereStructure($eStructure, if: $eStructure->notEmpty())
			->getCollection()
			->getColumnCollection('user', 'user')
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	/**
	 * Les fermes au nom desquelles des comptes sont inscrits à la formation, indexées par id et
	 * rangées par nom, chacune avec le nombre de ses inscrits (`learners`) : une ferme sans inscrit
	 * n'a rien à montrer au suivi. Les inscriptions sans ferme n'en font pas une.
	 */
	public static function getStructuresByCourse(Course $eCourse): \Collection {

		return Enrollment::model()
			->select([
				'structure' => ['name'],
				'learners' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->where('m1.structure IS NOT NULL')
			->group('m1.structure')
			->getCollection(index: 'structure')
			->sort(['structure' => ['name' => SORT_ASC]]);

	}

	/**
	 * Pose sur chaque formation le nombre de ses lecteurs inscrits (`readers`).
	 */
	public static function fillReaders(\Collection $cCourse): void {

		if($cCourse->empty()) {
			return;
		}

		$cEnrollmentCount = Enrollment::model()
			->select([
				'course',
				'readers' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse('IN', $cCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->group('course')
			->getCollection(index: 'course');

		foreach($cCourse as $eCourse) {
			$eCourse['readers'] = $cEnrollmentCount[$eCourse['id']]['readers'] ?? 0;
		}

	}

	/**
	 * Pose sur chaque formation en ligne d'une liste le nombre de ses demandes à confirmer
	 * (`requested`), en une requête groupée par formation : la colonne « À confirmer » de la liste du
	 * réseau, que `DateEnrollmentLib::fillRequested()` remplit pour les formations datées.
	 */
	public static function fillRequests(\Collection $cCourse): void {

		// Sans clone : les compteurs se posent sur les éléments de la liste, pas sur des copies.
		$cCourseOnline = $cCourse->find(fn(Course $eCourse) => $eCourse->isDated() === FALSE, clone: FALSE);

		if($cCourseOnline->empty()) {
			return;
		}

		$cEnrollmentCount = Enrollment::model()
			->select([
				'course',
				'requested' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse('IN', $cCourseOnline)
			->whereStatus(Enrollment::REQUESTED)
			->group('course')
			->getCollection(index: 'course');

		foreach($cCourseOnline as $eCourse) {
			$eCourse['requested'] = $cEnrollmentCount[$eCourse['id']]['requested'] ?? 0;
		}

	}

	/**
	 * Les demandes à confirmer d'une formation, les plus anciennes d'abord.
	 */
	public static function getRequestsByCourse(Course $eCourse): \Collection {

		$cEnrollment = Enrollment::model()
			->select([
				'id', 'course', 'status', 'fundingRequested', 'createdAt',
				'user' => ['firstName', 'lastName', 'email'],
				'structure' => ['name']
			])
			->whereCourse($eCourse)
			->whereStatus(Enrollment::REQUESTED)
			->sort(['createdAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

		return $cEnrollment;

	}

	/**
	 * Les inscriptions confirmées d'une formation entre deux jours compris, comptées au jour de leur
	 * confirmation et non de la demande, indexées par le premier jour de leur pas
	 * (`UsageLib::getStepSql()`). Un pas sans inscription n'y figure pas.
	 */
	public static function countBySteps(Course $eCourse, string $from, string $to, string $step): array {

		$cEnrollment = Enrollment::model()
			->select([
				'step' => UsageLib::getStepSql('confirmedAt', $step),
				'enrollments' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->whereConfirmedAt('>=', $from.' 00:00:00')
			->whereConfirmedAt('<=', $to.' 23:59:59')
			->group(UsageLib::getStepSql('confirmedAt', $step))
			->getCollection();

		$steps = [];

		foreach($cEnrollment as $eEnrollment) {
			$steps[$eEnrollment['step']] = $eEnrollment['enrollments'];
		}

		return $steps;

	}

	/**
	 * Le jour de la première inscription confirmée d'une formation, d'où part le Bilan sur toute sa
	 * durée (`UsageLib::getRange()`). NULL tant que personne n'est inscrit.
	 */
	public static function getFirstConfirmedAt(Course $eCourse): ?string {

		return Enrollment::model()
			->select([
				'first' => new \Sql('DATE(MIN(confirmedAt))')
			])
			->whereCourse($eCourse)
			->whereStatus(Enrollment::CONFIRMED)
			->get()['first'];

	}

	/**
	 * La formation relue avec ce que lisent la pause et l'état qu'une inscription prend en entrant.
	 * Elle se relit par son id parce que `create()` n'exige de l'appelant que son identifiant : une
	 * formation construite par son seul id s'inscrit, contrat que tient
	 * `NotificationTest::testEnrollment()`. La lecture verrouille la ligne et doit se faire dans la
	 * transaction de l'écriture : la page a jugé la pause sur la formation chargée avant l'envoi, et
	 * une bascule validée entre-temps doit refuser l'inscription, pas la laisser passer.
	 */
	private static function getCourseForRules(Course $eCourse): Course {

		return Course::model()
			->select(['id', 'community' => ['type', 'communityType'], 'type', 'enrollmentPaused'])
			->whereId($eCourse)
			->getForUpdate();

	}

	/**
	 * S'inscrire deux fois revient à s'être inscrit : le second clic, d'un autre onglet, ne lève rien.
	 * Une ligne déjà ouverte, demande ou inscription, reste telle qu'elle est ; une demande annulée se
	 * rouvre, et l'écriture la réclame par son état, qu'un autre onglet aurait déjà changé. Seule la
	 * ligne qui entre ou se rouvre est annoncée aux formateurs : `insert()` rend le nombre de lignes
	 * écrites, zéro pour un doublon ignoré, `update()` zéro pour une ligne qui n'est plus annulée.
	 *
	 * La ligne rouverte reprend la date du jour : c'est une demande neuve, et le suivi la date ainsi.
	 *
	 * L'état se juge ici sur le réglage relu (`getStatusForNew()`), et non sur celui que la page a lu
	 * pour poser la question. Une inscription écrite sans elle (compte créé sur place, formation à
	 * dates) n'a pas de réponse, d'où le défaut.
	 */
	public static function create(Enrollment $e): void {

		$e->expects(['user', 'course', 'structure']);
		$e->add(['fundingRequested' => NULL]);

		Enrollment::model()->beginTransaction();

			$eCourseLocked = self::getCourseForRules($e['course']);

			if(new Enrollment(['course' => $eCourseLocked])->acceptPause() === FALSE) {
				Enrollment::model()->commit();
				Enrollment::fail('paused');
				return;
			}

			$e['status'] = self::getStatusForNew(self::getOnlineConfirm($eCourseLocked), $e['fundingRequested']);
			$e['confirmedAt'] = ($e['status'] === Enrollment::CONFIRMED) ? new \Sql('NOW()') : NULL;

			$eEnrollmentCanceled = new EnrollmentModel()
				->select(['id'])
				->whereUser($e['user'])
				->whereCourse($e['course'])
				->whereStatus(Enrollment::CANCELED)
				->get();

			if($eEnrollmentCanceled->empty()) {

				$written = Enrollment::model()
					->option('add-ignore')
					->insert($e);

			} else {

				$e['id'] = $eEnrollmentCanceled['id'];
				$e['createdAt'] = new \Sql('NOW()');

				$written = Enrollment::model()
					->select(['structure', 'status', 'fundingRequested', 'confirmedAt', 'createdAt'])
					->whereStatus(Enrollment::CANCELED)
					->update($e);

			}

		Enrollment::model()->commit();

		if($written === 1) {
			NotificationLib::enrolled($e);
		}

	}

	/**
	 * Le réseau confirme une demande. L'état se lit dans la condition de l'écriture, qui ne touche
	 * rien si la ligne a changé entre-temps : deux onglets qui confirment ensemble n'écrivent qu'une
	 * fois.
	 */
	public static function confirm(Enrollment $e): void {

		$e->expects(['user', 'course']);

		$e['status'] = Enrollment::CONFIRMED;
		$e['confirmedAt'] = new \Sql('NOW()');

		Enrollment::model()->beginTransaction();

			$affected = Enrollment::model()
				->select(['status', 'confirmedAt'])
				->whereStatus(Enrollment::REQUESTED)
				->update($e);

			// Rien d'écrit : la ligne a changé d'état depuis que l'écran l'a montrée.
			if($affected === 0) {
				Enrollment::model()->commit();
				Enrollment::fail('changed');
				return;
			}

		Enrollment::model()->commit();

		NotificationLib::enrolled($e);

		self::sendMail($e, 'confirmed');

	}

	/**
	 * La personne retire sa demande. Rien ne part : le réseau n'a rien à faire d'une demande qui
	 * n'attend plus.
	 */
	public static function cancel(Enrollment $e): void {
		self::cancelRequest($e);
	}

	/**
	 * Le réseau annule une demande, et la personne le lit dans un e-mail. Elle peut s'inscrire de
	 * nouveau depuis la fiche, qui revient à « S'inscrire ».
	 */
	public static function cancelByCommunity(Enrollment $e): void {

		if(self::cancelRequest($e)) {
			self::sendMail($e, 'canceledByCommunity');
		}

	}

	/**
	 * Une demande passe à l'état annulé, sous la condition de son état : rend vrai si la ligne a été
	 * écrite, faux si elle avait changé entre-temps, confirmée ou déjà annulée par l'autre côté.
	 */
	private static function cancelRequest(Enrollment $e): bool {

		$e['status'] = Enrollment::CANCELED;

		$affected = Enrollment::model()
			->select(['status'])
			->whereStatus(Enrollment::REQUESTED)
			->update($e);

		return ($affected === 1);

	}

	/**
	 * L'e-mail à la personne, rendu sur la ligne relue : il lit le nom et le contact du réseau, que
	 * `Enrollment::getSelection()` ne porte pas, et `confirm()` comme `cancelByCommunity()` acceptent
	 * tout élément qui a son id. Un compte fermé n'a plus d'adresse, et rien ne part.
	 */
	private static function sendMail(Enrollment $e, string $event): void {

		$eEnrollment = new EnrollmentModel()
			->select([
				'id',
				'user' => ['email'],
				'course' => ['name', 'community' => ['name', 'legalEmail']]
			])
			->whereId($e['id'])
			->get();

		if($eEnrollment['user']['email'] === NULL) {
			return;
		}

		$ui = new EnrollmentUi();

		$content = match($event) {
			'confirmed' => $ui->getConfirmedMail($eEnrollment),
			'canceledByCommunity' => $ui->getCanceledByCommunityMail($eEnrollment, SupervisorLib::getContactByCourse($eEnrollment['course']))
		};

		\main\MailLib::getInstance()
			->setTo($eEnrollment['user']['email'])
			->setContent(...$content)
			->send();

	}

	public static function delete(Enrollment $e): void {

		Enrollment::model()->beginTransaction();

			foreach(ProgressLib::getByEnrollment($e) as $eProgress) {
				ProgressLib::delete($eProgress);
			}

			ReadingLib::deleteByEnrollment($e);

			parent::delete($e);

		Enrollment::model()->commit();

	}

}
?>
