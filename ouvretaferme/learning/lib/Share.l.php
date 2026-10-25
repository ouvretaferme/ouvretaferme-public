<?php
namespace learning;

/**
 * À qui une formation de groupe est ouverte, et ce qu'un compte en tire : les promotions d'un
 * groupe de formation, qui y lisent, les membres d'un réseau, qui se la voient proposer.
 *
 * Une promotion supprimée laisse ses partages derrière elle : `base/structure` ne connaît pas
 * `learning` et n'a pas à le connaître. Chaque lecture d'un partage de promotion joint donc
 * `structureSection`, dans la même base commune, et un partage orphelin n'y survit pas. L'ouverture
 * d'un réseau ne nomme personne : elle vaut pour qui en est membre au moment où on la lit, et un
 * membre qui quitte le réseau ne se la voit plus proposer, sans qu'une ligne ait à être effacée.
 */
class ShareLib extends ShareCrud {

	/**
	 * Les fenêtres de lecture par compte, indexées par id de compte : elles dépendent du lecteur, et
	 * un même processus peut en lire plusieurs (les tests, une tâche qui boucle sur des comptes).
	 * Vidées à chaque écriture d'un partage.
	 */
	private static array $cShareByUser = [];

	/**
	 * Les ouvertures de réseau qui concernent chaque compte, même indexation et même vidage :
	 * « Mes formations » et la garde de l'onglet du réseau (`Lime.s.php`) les lisent toutes deux.
	 */
	private static array $cShareNetworkByUser = [];

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['opensAt', 'dueAt'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['opensAt', 'dueAt'];
	}

	/**
	 * Un partage à poser, ouvert du jour et sans échéance : ce que le panneau préremplit.
	 */
	public static function getNewElementForSection(Course $eCourse, \structure\Section $eSection): Share {

		return new Share([
			'course' => $eCourse,
			'audience' => Share::SECTION,
			'section' => $eSection,
			'opensAt' => \Clock::date(),
			'dueAt' => NULL,
		]);

	}

	/**
	 * L'ouverture d'une formation de réseau à ses membres. Elle porte l'ouverture du jour, que ni
	 * l'écran ni la lecture ne regardent, mais que le cron lit avec `announcedAt` par leur index
	 * commun (`getCoursesToAnnounce()`) : la colonne est celle des promotions, et une ouverture du
	 * jour s'annonce à l'enregistrement.
	 */
	public static function getNewElementForMembers(Course $eCourse): Share {

		return new Share([
			'course' => $eCourse,
			'audience' => Share::MEMBERS,
			'section' => NULL,
			'opensAt' => \Clock::date(),
			'dueAt' => NULL,
		]);

	}

	/**
	 * Les partages de promotion d'une formation, indexés par promotion.
	 */
	public static function getByCourse(Course $eCourse): \Collection {

		return Share::model()
			->select(Share::getSelection())
			->join(\structure\Section::model(), 'm1.section = m2.id')
			->whereCourse($eCourse)
			->getCollection(index: 'section');

	}

	/**
	 * L'ouverture d'une formation de réseau à ses membres, vide si elle ne leur est pas ouverte.
	 */
	public static function getMembersByCourse(Course $eCourse): Share {

		return Share::model()
			->select(Share::getSelection())
			->whereCourse($eCourse)
			->whereAudience(Share::MEMBERS)
			->get();

	}

	/**
	 * Le partage que le panneau d'un réseau reprend : l'ouverture aux membres si elle existe, sinon
	 * celle que l'enregistrement créera. Le panneau la coche d'avance dans les deux cas : on ne
	 * l'ouvre que pour ouvrir la formation, ou pour la garder ouverte.
	 */
	public static function getForNetworkPanel(Course $eCourse): Share {

		$eShare = self::getMembersByCourse($eCourse);

		if($eShare->empty()) {
			return self::getNewElementForMembers($eCourse);
		}

		// La ligne désigne la même formation : elle reprend celle que la page a préparée, dont le
		// panneau lit le compte des membres.
		$eShare['course'] = $eCourse;

		return $eShare;

	}

	/**
	 * Combien de membres l'ouverture d'une formation de réseau atteindrait (`applyMembers()`) : le
	 * panneau l'annonce, et l'éditeur ne propose d'ouvrir qu'à partir d'un
	 * (`Course::acceptShareAudience()`).
	 */
	public static function countMembers(Course $eCourse): int {

		return self::applyMembers(\structure\Member::model())
			->where('m1.structure', $eCourse['community'])
			->count();

	}

	/**
	 * Les membres d'un réseau, hors de son équipe (`Role::isStaff()`) : l'équipe écrit la formation
	 * et n'a rien à se voir proposer. Un membre est une adhésion entrée sur une structure active,
	 * équipe comprise avant ce filtre, et non le rattachement d'une de ses fermes (Vincent, 09/10,
	 * « une seule règle »). Un rôle sans fqn n'est pas de l'équipe. Le compte du panneau, les
	 * destinataires de l'e-mail et les formations proposées à un compte passent tous par ici ; les
	 * colonnes sont qualifiées en `m1.`, `m2` est le rôle, que l'appelant ne borne pas.
	 */
	private static function applyMembers(\structure\MemberModel $mMember): \structure\MemberModel {

		$fqns = implode(', ', array_map(\structure\Role::model()->format(...), \structure\StructureSetting::ROLE_FQNS));

		return $mMember
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->where('m2.fqn IS NULL OR m2.fqn NOT IN ('.$fqns.')');

	}

	/**
	 * Le nombre de partages qui ouvrent la formation à quelqu'un : ses promotions, orphelines
	 * exclues, et pour un réseau son ouverture aux membres. Le suivi d'un groupe distingue une
	 * formation que personne ne suit d'une formation ouverte hors de la portée de l'encadrant.
	 */
	public static function countByCourse(Course $eCourse): int {

		return Share::model()
			->join(\structure\Section::model(), 'm1.section = m2.id', 'LEFT')
			->whereCourse($eCourse)
			->where('m1.audience != '.Share::model()->format(Share::SECTION).' OR m2.id IS NOT NULL')
			->count();

	}

	/**
	 * Les promotions partagées que l'encadrant peut regarder, indexées par id : toutes celles du
	 * groupe qui portent un partage de la formation, bornées par sa portée (`$scope`, celle que
	 * rend `SectionLib::getScope()`, `NULL` pour tout le groupe). Closes comprises : clore une
	 * promotion ne retire rien à ses stagiaires, ni à ce qu'ils ont suivi.
	 */
	public static function getSectionsForTrack(Course $eCourse, ?array $scope): \Collection {

		$cShare = self::getByCourse($eCourse);

		return \structure\SectionLib::getByStructure($eCourse['community'])->find(fn(\structure\Section $eSection) => (
			$cShare->offsetExists($eSection['id']) and
			(
				$scope === NULL or
				in_array($eSection['id'], $scope, TRUE)
			)
		));

	}

	/**
	 * Les stagiaires que le suivi montre, indexés par id de compte : les membres présents du groupe
	 * rangés dans l'une des promotions de `$cSection` (celles de `getSectionsForTrack()`), ou dans
	 * `$eSection` seule quand l'écran en regarde une. Un seul appel sert l'écran, l'export et la
	 * légitimité de la case cochée.
	 *
	 * `Section.users` garde l'id d'un membre qui a quitté le groupe (`SectionLib::fillUsers()`) :
	 * c'est l'adhésion qui dit qui est encore là, la promotion seulement où il est rangé. Elle dit
	 * aussi qui encadre : un encadrant rangé dans sa promotion pour en borner sa portée n'en est pas
	 * un stagiaire.
	 * L'intersection se fait en PHP sur des clés, les ids vivant dans une colonne `json`.
	 */
	public static function getLearners(Course $eCourse, \Collection $cSection, \structure\Section $eSection = new \structure\Section()): \Collection {

		if($eSection->notEmpty()) {
			$cSection = $cSection->find(fn(\structure\Section $eSectionShared) => $eSectionShared['id'] === $eSection['id']);
		}

		if($cSection->empty()) {
			return new \Collection();
		}

		$users = [];

		foreach($cSection as $eSectionShared) {
			foreach($eSectionShared['users'] as $user) {
				$users[$user] = TRUE;
			}
		}

		return \structure\MemberLib::getByStructure($eCourse['community'], index: NULL)
			->find(fn(\structure\Member $eMember) => (
				$eMember->isStaff() === FALSE and
				isset($users[$eMember['user']['id']])
			))
			->getColumnCollection('user', 'user')
			->sort([
				'firstName' => SORT_ASC,
				'lastName' => SORT_ASC
			]);

	}

	/**
	 * Les promotions que le panneau propose : celles du groupe qui sont ouvertes, et celles qui sont
	 * closes mais déjà partagées, pour qu'on puisse encore retirer leur partage. Chacune porte le
	 * nombre de ses stagiaires (`learners`) et son partage (`eShare`) : celui qui existe, ou celui
	 * qu'une case cochée créera, ouvert du jour.
	 */
	public static function getSections(Course $eCourse): \Collection {

		$cShare = self::getByCourse($eCourse);

		$cSection = \structure\SectionLib::getByStructure($eCourse['community'])
			->find(fn(\structure\Section $eSection) => (
				$eSection->isClosed() === FALSE or
				$cShare->offsetExists($eSection['id'])
			));

		foreach($cSection as $eSection) {

			$eSection['learners'] = count($eSection['users']);
			$eSection['eShare'] = $cShare->offsetExists($eSection['id'])
				? $cShare[$eSection['id']]
				: self::getNewElementForSection($eCourse, $eSection);

		}

		return $cSection;

	}

	/**
	 * Les partages que le panneau a postés, construits et indexés par leur clé (`Share::getKey()`),
	 * prêts pour `synchronizeCourse()`. Un groupe de formation poste une case par promotion, avec
	 * ses dates ; un réseau poste son audience, les membres ou personne.
	 *
	 * Les cases cochées (`section[]`) arrivent en chaînes, et l'id est un entier comparé
	 * strictement : le cast est ce qui les apparie. On parcourt ce que le panneau propose, jamais
	 * les ids postés : une case forgée pour la promotion d'un autre groupe ne trouve rien à
	 * apparier. Les refus tombent dans le `FailWatch` de l'appelant, rattachés à leur champ
	 * (`dueAt[<promotion>]`, `audience`).
	 */
	public static function buildFromInput(Course $eCourse, array $input): \Collection {

		if($eCourse['community']->isNetwork()) {
			return self::buildFromInputNetwork($eCourse, $input);
		}

		$sections = array_map('intval', var_filter($input['section'] ?? NULL, 'array', []));

		$cShare = new \Collection();

		foreach(self::getSections($eCourse) as $eSection) {

			if(in_array($eSection['id'], $sections, TRUE) === FALSE) {
				continue;
			}

			$eShare = $eSection['eShare'];
			$eShare->buildIndex(['opensAt', 'dueAt'], $input, $eSection['id']);

			$cShare[$eShare->getKey()] = $eShare;

		}

		return $cShare;

	}

	/**
	 * « Personne pour l'instant » poste une chaîne vide, qu'on écarte avant `build()`, qui la
	 * refuserait : elle ne construit aucun partage, et `synchronizeCourse()` retire celui de la
	 * formation. Les membres en construisent un, sans période : elle ne se lit pas pour un réseau.
	 */
	private static function buildFromInputNetwork(Course $eCourse, array $input): \Collection {

		$cShare = new \Collection();

		if(($input['audience'] ?? NULL) === '') {
			return $cShare;
		}

		$eShareInput = new Share([
			'course' => $eCourse,
		]);

		$eShareInput->build(['audience'], $input);

		// `build()` laisse une propriété refusée non posée, sans lever : le refus attend l'appelant
		// dans son `FailWatch`, et il n'y a rien à construire d'ici là.
		if($eShareInput->offsetExists('audience') === FALSE) {
			return $cShare;
		}

		$eShare = self::getForNetworkPanel($eCourse);

		$cShare[$eShare->getKey()] = $eShare;

		return $cShare;

	}

	/**
	 * Pose sur chaque formation ses partages (`cShare`), période comprise, pour la colonne « Ouverte
	 * à » d'un groupe de formation et la ligne qui situe la formation sous le titre de son éditeur,
	 * et leur nombre (`shares`), qui ouvre l'accès au suivi (`Course::assertTrack()`). Deux
	 * lectures : les partages de promotion passent par `structureSection`, qui écarte les
	 * orphelins ; l'ouverture d'un réseau n'a rien à joindre.
	 */
	public static function fillShares(\Collection $cCourse): void {

		if($cCourse->empty()) {
			return;
		}

		$ccShareSection = Share::model()
			->select(['id', 'course', 'audience', 'opensAt', 'dueAt', 'section' => ['name']])
			->join(\structure\Section::model(), 'm1.section = m2.id')
			->whereCourse('IN', $cCourse)
			->sort(new \Sql('m2.name ASC'))
			->getCollection(index: ['course', 'id']);

		$ccShareNetwork = Share::model()
			->select(['id', 'course', 'audience', 'opensAt', 'dueAt'])
			->whereCourse('IN', $cCourse)
			->whereAudience(Share::MEMBERS)
			->getCollection(index: ['course', 'id']);

		foreach($cCourse as $eCourse) {

			$cShare = new \Collection();

			foreach([$ccShareSection, $ccShareNetwork] as $ccShare) {

				if($ccShare->offsetExists($eCourse['id'])) {
					$cShare->mergeCollection($ccShare[$eCourse['id']]);
				}

			}

			$eCourse['cShare'] = $cShare;
			$eCourse['shares'] = $cShare->count();

		}

	}

	/**
	 * Les ouvertures de réseau qui proposent des formations au compte : celles des réseaux dont il
	 * est membre hors de l'équipe (`applyMembers()`), formations de tout état, que l'appelant borne.
	 * Deux requêtes dans la base commune : les réseaux du compte, puis leurs ouvertures, par
	 * l'index de `Course.community`.
	 */
	private static function getNetworkSharesByUser(\user\User $eUser): \Collection {

		if($eUser->empty()) {
			return new \Collection();
		}

		return self::$cShareNetworkByUser[$eUser['id']] ??= self::readNetworkSharesByUser($eUser);

	}

	private static function readNetworkSharesByUser(\user\User $eUser): \Collection {

		$cMember = self::applyMembers(\structure\Member::model())
			->select(['structure'])
			->where('m1.user', $eUser)
			->getCollection();

		if($cMember->empty()) {
			return new \Collection();
		}

		return Share::model()
			->select(['id', 'audience', 'course' => Course::getSelection()])
			->join(Course::model(), 'm1.course = m2.id')
			->where('m1.audience', Share::MEMBERS)
			->where('m2.community', 'IN', $cMember->getColumnCollection('structure'))
			->getCollection(index: 'id');

	}

	/**
	 * La fenêtre de lecture de chaque formation ouverte au compte, indexée par formation. Une
	 * formation ouverte à plusieurs de ses promotions prend la fenêtre la plus large : elle s'ouvre
	 * au plus tôt, et ne se fige qu'à la plus tardive des échéances, jamais si l'une n'en a pas.
	 *
	 * Les membres d'une promotion sont une colonne `json` (`structureSection.users`), qu'aucun index
	 * ne couvre : la condition balaie `structureSection`, comme `SectionLib::getByUser()`. La table
	 * compte une ligne par promotion et non par stagiaire, et chaque promotion trouvée atteint ses
	 * partages par l'index `section`. `JSON_CONTAINS` et non un `LIKE`, qui trouverait 12 dans 120.
	 *
	 * `MAX()` ignore les `NULL` : l'échéance n'est gardée que si toutes les lignes en portent une.
	 *
	 * L'ouverture d'un réseau n'en donne aucune : la lecture d'une formation de réseau vient de
	 * l'inscription (`Course::canRead()`).
	 */
	public static function getWindowsByUser(\user\User $eUser): \Collection {

		if($eUser->empty()) {
			return new \Collection();
		}

		return self::$cShareByUser[$eUser['id']] ??= self::readWindowsByUser($eUser);

	}

	private static function readWindowsByUser(\user\User $eUser): \Collection {

		return Share::model()
			->select([
				'course',
				'opensAt' => new \Sql('MIN(m1.opensAt)'),
				'dueAt' => new \Sql('IF(COUNT(m1.dueAt) = COUNT(*), MAX(m1.dueAt), NULL)')
			])
			->join(\structure\Section::model(), 'm1.section = m2.id AND JSON_CONTAINS(m2.users, \''.(int)$eUser['id'].'\')')
			->group(new \Sql('m1.course'))
			->getCollection(index: 'course');

	}

	/**
	 * La fenêtre de lecture d'une formation pour ce compte, vide quand elle ne lui est ouverte par
	 * aucune de ses promotions.
	 */
	public static function getWindowByUserAndCourse(\user\User $eUser, Course $eCourse): Share {

		$cShare = self::getWindowsByUser($eUser);

		return $cShare->offsetExists($eCourse['id']) ? $cShare[$eCourse['id']] : new Share();

	}

	/**
	 * Parmi ces comptes, ceux à qui une promotion ouvre aujourd'hui la formation : la question de
	 * `getWindowByUserAndCourse()` suivie de `Share::isOpen()`, posée pour une liste de comptes en
	 * une requête, et non en une par compte. L'annonce d'une ouverture à une promotion la pose pour
	 * ses destinataires (`getAnnounceRecipients()`).
	 *
	 * La fenêtre d'un compte s'ouvre au plus tôt de ses partages (`MIN(opensAt)` de
	 * `readWindowsByUser()`) : elle est ouverte dès qu'un seul partage qui le concerne l'est. La
	 * lecture suit donc celle de `readWindowsByUser()`, bornée aux partages ouverts de la formation,
	 * les promotions par leur colonne `users`.
	 *
	 * @param int[] $users
	 * @return int[]
	 */
	public static function getUsersWithOpenWindow(Course $eCourse, array $users): array {

		if($users === []) {
			return [];
		}

		$opened = [];

		$cShareSection = Share::model()
			->select(['section' => ['users']])
			->join(\structure\Section::model(), 'm1.section = m2.id')
			->where('m1.course', $eCourse)
			->where('m1.opensAt', '<=', \Clock::date())
			->getCollection();

		foreach($cShareSection as $eShare) {
			foreach(array_intersect($eShare['section']['users'], $users) as $user) {
				$opened[$user] = $user;
			}
		}

		return array_values($opened);

	}

	/**
	 * Les partages qui concernent le compte, formations publiées seules, rangés par promotion puis
	 * par réseau, chacun sous sa clé (`key` : `section-<id>`, `community-<id>`), qui est celle des
	 * titres de « Mes formations ». Une formation ouverte à deux de ses promotions y paraît deux
	 * fois, et les deux partages portent le **même** élément de formation, pour que ce que la page
	 * y pose (compteurs, progression, fenêtre) vaille pour les deux cartes. Une formation de réseau
	 * n'a qu'une ouverture aux membres, donc une carte.
	 */
	public static function getByUser(\user\User $eUser): \Collection {

		$cShare = Share::model()
			->select([
				'id',
				'audience',
				'course' => Course::getSelection(),
				'section' => ['name', 'structure' => ['name']]
			])
			->join(\structure\Section::model(), 'm1.section = m2.id AND JSON_CONTAINS(m2.users, \''.(int)$eUser['id'].'\')')
			->sort(new \Sql('m2.name ASC, m2.id ASC, m1.id ASC'))
			->getCollection()
			->find(fn(Share $eShare) => $eShare['course']->acceptRead());

		foreach($cShare as $eShare) {
			$eShare['key'] = 'section-'.$eShare['section']['id'];
		}

		$cShareNetwork = self::getNetworkSharesByUser($eUser)
			->find(fn(Share $eShare) => $eShare['course']->acceptRead())
			->sort(fn(Share $a, Share $b) => (
				[$a['course']['community']['name'], $a['course']['community']['id'], $a['course']['name'], $a['id']] <=>
				[$b['course']['community']['name'], $b['course']['community']['id'], $b['course']['name'], $b['id']]
			));

		foreach($cShareNetwork as $eShare) {

			$eShare['key'] = 'community-'.$eShare['course']['community']['id'];
			$eShare['section'] = new \structure\Section();

			$cShare[] = $eShare;

		}

		$cCourse = new \Collection();

		foreach($cShare as $eShare) {

			$courseId = $eShare['course']['id'];

			if($cCourse->offsetExists($courseId)) {
				$eShare['course'] = $cCourse[$courseId];
			} else {
				$cCourse[$courseId] = $eShare['course'];
			}

		}

		return $cShare;

	}

	/**
	 * Les ouvertures d'un réseau qui concernent le compte (clé `community-<id>`), que l'onglet
	 * « Formations » du réseau montre à un membre.
	 */
	public static function getByUserAndCommunity(\user\User $eUser, \structure\Structure $eStructure): \Collection {

		return self::getByUser($eUser)->find(fn(Share $eShare) => $eShare['key'] === 'community-'.$eStructure['id'], clone: FALSE);

	}

	/**
	 * Pose sur chaque formation qu'un réseau ouvre au compte ce que sa carte dit de lui : proposée
	 * tant qu'il n'y est pas inscrit (`proposed`), sa demande quand elle attend
	 * (`eEnrollmentRequest` en ligne, `eDateEnrollmentRequest` à une date, vides sinon), et pour une
	 * formation datée son réseau (`eCommunity`), dont la page publique reçoit l'inscription à une
	 * date. Une demande en attente garde la formation proposée : elle ne se lit pas encore. Une
	 * place confirmée à une date l'inscrit, qu'il ait commencé les leçons ou non.
	 */
	public static function fillProposed(\user\User $eUser, \Collection $cCourse): void {

		$cEnrollment = EnrollmentLib::getOpenByUserAndCourses($eUser, $cCourse);
		$cDateEnrollment = DateEnrollmentLib::getOpenByUserAndCourses($eUser, $cCourse->find(fn(Course $eCourse) => $eCourse->isDated(), clone: FALSE));

		$cCommunity = new \Collection();

		foreach($cCourse as $eCourse) {

			$eEnrollment = $cEnrollment->offsetExists($eCourse['id']) ? $cEnrollment[$eCourse['id']] : new Enrollment();
			$eDateEnrollment = $cDateEnrollment->offsetExists($eCourse['id']) ? $cDateEnrollment[$eCourse['id']] : new DateEnrollment();

			$eCourse['proposed'] = (
				($eEnrollment->empty() or $eEnrollment['status'] === Enrollment::REQUESTED) and
				($eDateEnrollment->empty() or $eDateEnrollment['status'] !== DateEnrollment::CONFIRMED)
			);

			$eCourse['eEnrollmentRequest'] = ($eEnrollment->notEmpty() and $eEnrollment['status'] === Enrollment::REQUESTED) ? $eEnrollment : new Enrollment();
			$eCourse['eDateEnrollmentRequest'] = ($eDateEnrollment->notEmpty() and $eDateEnrollment['status'] !== DateEnrollment::CONFIRMED) ? $eDateEnrollment : new DateEnrollment();

			if($eCourse->isDated()) {

				$communityId = $eCourse['community']['id'];

				if($cCommunity->offsetExists($communityId) === FALSE) {
					$cCommunity[$communityId] = \structure\CommunityLib::getByStructure($eCourse['community']);
				}

				$eCourse['eCommunity'] = $cCommunity[$communityId];

			} else {
				$eCourse['eCommunity'] = new \structure\Community();
			}

		}

	}

	/**
	 * Le réseau ouvre-t-il une formation à ses membres, dont le compte ? C'est la garde de l'onglet
	 * « Formations » d'un membre qui n'est pas de l'équipe (`CommunityAction::render()`).
	 */
	public static function hasNetworkByUser(\user\User $eUser, \structure\Structure $eStructure): bool {

		return self::getByUserAndCommunity($eUser, $eStructure)->notEmpty();

	}

	/**
	 * Aligne les partages d'une formation sur ceux que le panneau a construits, indexés par leur
	 * clé (`Share::getKey()`) : ce qui n'y est plus se retire, ce qui y est se crée ou se met à
	 * jour. Les deux audiences se relisent : le panneau d'un réseau qui passe à « Personne pour
	 * l'instant » ne construit rien, et retire ainsi l'ouverture aux membres.
	 */
	public static function synchronizeCourse(Course $eCourse, \Collection $cShare): void {

		Share::model()->beginTransaction();

			$cShareExisting = self::getByCourse($eCourse);

			$eShareMembers = self::getMembersByCourse($eCourse);

			if($eShareMembers->notEmpty()) {
				$cShareExisting[] = $eShareMembers;
			}

			foreach($cShareExisting as $eShareExisting) {

				if($cShare->offsetExists($eShareExisting->getKey()) === FALSE) {
					self::delete($eShareExisting);
				}

			}

			foreach($cShare as $eShare) {

				if($eShare->exists()) {
					self::update($eShare, ['opensAt', 'dueAt']);
				} else {
					self::create($eShare);
				}

			}

		Share::model()->commit();

	}

	/**
	 * Sans la jointure des lectures : les partages d'une promotion supprimée partent aussi avec leur
	 * formation.
	 */
	public static function deleteByCourse(Course $eCourse): void {

		$cShare = Share::model()
			->select(['id'])
			->whereCourse($eCourse)
			->getCollection();

		foreach($cShare as $eShare) {
			self::delete($eShare);
		}

	}

	/**
	 * Les formations dont un partage ouvert attend son annonce, indexées par id, pour le cron
	 * (`page/cron/share.p.php`) : publiées, d'un groupe qui accepte les formations
	 * (`CourseLib::applyCommunityLearning()`) et, tant que les formations ne sont pas ouvertes à
	 * tous, d'une structure de `LearningSetting::STRUCTURE_BETA`, comme les crons des dates.
	 *
	 * Les partages se lisent par l'index `(announcedAt, opensAt)` : la quasi-totalité des lignes est
	 * annoncée, et la citation ne parcourt que celles qui attendent.
	 */
	public static function getCoursesToAnnounce(): \Collection {

		$mCourse = CourseLib::applyCommunityLearning(Course::model(), 'm', 's')
			->select(Course::getSelection())
			->whereStatus(Course::PUBLISHED)
			->whereId('IN', new ShareModel()
				->select('course')
				->whereAnnouncedAt(NULL)
				->whereOpensAt('<=', \Clock::date())
				->getSql());

		if(LearningSetting::$active === FALSE) {
			$mCourse->whereCommunity('IN', LearningSetting::STRUCTURE_BETA);
		}

		return $mCourse->getCollection(index: 'id');

	}

	/**
	 * Le cron : annonce chaque formation que `getCoursesToAnnounce()` rend, et rend le nombre
	 * d'e-mails partis. Ne se lance jamais depuis un test : il envoie.
	 */
	public static function announceOpened(): int {

		$sent = 0;

		foreach(self::getCoursesToAnnounce() as $eCourse) {
			$sent += self::announce($eCourse);
		}

		return $sent;

	}

	/**
	 * Annonce la formation à ceux que ses partages ouverts et pas encore annoncés atteignent, et
	 * rend le nombre d'e-mails partis. Appelé à l'enregistrement du panneau de partage pour ce qui
	 * s'ouvre le jour même, et par le cron pour ce qui s'ouvre ensuite.
	 */
	public static function announce(Course $eCourse): int {

		$mails = self::claimAnnounce($eCourse);

		self::send($mails);

		return count($mails);

	}

	/**
	 * Réclame les partages ouverts de la formation qui n'ont pas encore été annoncés, pose une
	 * annonce par compte qu'ils atteignent, et rend les e-mails à envoyer, un par annonce posée.
	 * Rien n'est envoyé ici : les tests y lisent ce qui partirait.
	 *
	 * Deux verrous, qui ne gardent pas la même chose :
	 * - un partage n'est réclamé qu'une fois, par l'écriture conditionnelle de `announcedAt` : le
	 *   panneau et le cron peuvent le lire au même instant, un seul des deux l'emporte ;
	 * - un compte n'est prévenu qu'une fois par formation, par l'unicité `(course, user)` de
	 *   `Announcement` : décocher puis recocher une promotion, ou repasser un réseau par « Personne
	 *   pour l'instant », crée une ligne de partage neuve, que rien d'autre ne distinguerait de la
	 *   première.
	 *
	 * Une formation qui n'est pas lisible (brouillon, groupe qui n'accepte plus les formations) ne
	 * réclame rien : ses partages attendent qu'elle le redevienne. Un partage dont l'échéance est
	 * passée à l'ouverture (un cron manqué, une saisie à rebours) est réclamé sans prévenir personne :
	 * la formation ne se coche plus, l'annoncer serait annoncer ce qu'on ne peut plus suivre. Les
	 * partages de promotion orphelins sont écartés comme dans `countByCourse()`.
	 *
	 * @return array<int, array{to: string, content: array}>
	 */
	public static function claimAnnounce(Course $eCourse): array {

		if($eCourse->acceptRead() === FALSE) {
			return [];
		}

		$cShare = Share::model()
			->select(Share::getSelection())
			->join(\structure\Section::model(), 'm1.section = m2.id', 'LEFT')
			->where('m1.course', $eCourse)
			->where('m1.announcedAt IS NULL')
			->where('m1.opensAt', '<=', \Clock::date())
			->where('m1.audience != '.Share::model()->format(Share::SECTION).' OR m2.id IS NOT NULL')
			->getCollection(index: 'id');

		if($cShare->empty()) {
			return [];
		}

		$mails = [];

		Share::model()->beginTransaction();

			$cShareClaimed = new \Collection();

			foreach($cShare as $eShare) {

				// Une réclamation et non une mise à jour : `ShareLib::update()` écrirait sans la
				// condition `announcedAt IS NULL`, qui est ce qui départage deux lecteurs simultanés.
				$eShare['announcedAt'] = new \Sql('NOW()');

				$affected = Share::model()
					->select(['announcedAt'])
					->whereAnnouncedAt(NULL)
					->update($eShare);

				if(
					$affected === 1 and
					$eShare->acceptProgress()
				) {
					$cShareClaimed[$eShare['id']] = $eShare;
				}

			}

			$cUser = self::getAnnounceRecipients($eCourse, $cShareClaimed);

			if($cUser->notEmpty()) {

				self::fillAnnounce($eCourse);

				foreach($cUser as $eUser) {

					if(AnnouncementLib::claim($eCourse, $eUser)) {
						$mails[] = [
							'to' => $eUser['email'],
							'content' => new ShareUi()->getAnnounceMail($eCourse, $eUser)
						];
					}

				}

			}

		Share::model()->commit();

		return $mails;

	}

	/**
	 * Ceux que ces partages atteignent, indexés par id de compte.
	 *
	 * Un réseau atteint ses membres hors de l'équipe (`applyMembers()`), qu'ils aient rattaché une
	 * structure ou non : l'ouverture ne nomme personne, l'e-mail non plus.
	 *
	 * Un groupe de formation atteint les stagiaires de ses promotions, chacun avec ses promotions
	 * (`cSection`) et l'échéance la plus large de ses partages (`dueAt`, `NULL` si l'un n'en a pas),
	 * comme la fenêtre de `readWindowsByUser()`. Le premier tri suit les promotions ; le second
	 * reprend la règle exacte de `Course::canRead()` par `getUsersWithOpenWindow()`.
	 */
	private static function getAnnounceRecipients(Course $eCourse, \Collection $cShare): \Collection {

		if($cShare->empty()) {
			return new \Collection();
		}

		if($eCourse['community']->isNetwork()) {

			$cMember = self::applyMembers(\structure\Member::model())
				->select(['user'])
				->where('m1.structure', $eCourse['community'])
				->getCollection();

			return self::getAnnounceUsers($eCourse, $cMember->getColumnCollection('user')->getIds());

		}

		// Par compte : les promotions qui l'atteignent et les échéances des partages qui les ouvrent.
		$places = [];
		$dues = [];

		$cSection = new \Collection();

		foreach($cShare as $eShare) {
			$cSection[$eShare['section']['id']] = $eShare['section'];
		}

		$cUserLearner = self::getLearners($eCourse, $cSection);

		foreach($cShare as $eShare) {

			foreach($eShare['section']['users'] as $userId) {

				if($cUserLearner->offsetExists($userId)) {
					$places[$userId][$eShare['section']['id']] = $eShare['section'];
					$dues[$userId][] = $eShare['dueAt'];
				}

			}

		}

		$cUser = self::getAnnounceUsers($eCourse, self::getUsersWithOpenWindow($eCourse, array_keys($places)));

		foreach($cUser as $eUser) {

			$eUser['cSection'] = new \Collection($places[$eUser['id']]);
			$eUser['dueAt'] = in_array(NULL, $dues[$eUser['id']], TRUE) ? NULL : max($dues[$eUser['id']]);

		}

		return $cUser;

	}

	/**
	 * Parmi ces comptes, ceux qu'un e-mail atteint, indexés par id : actifs (un compte fermé n'a plus
	 * d'adresse, un compte suspendu en garde une), et pas déjà inscrits à la formation, demande
	 * comprise, qui la connaissent.
	 *
	 * @param int[] $users
	 */
	private static function getAnnounceUsers(Course $eCourse, array $users): \Collection {

		if($users === []) {
			return new \Collection();
		}

		// `whereEmail('IS NOT NULL')` comparerait à la chaîne : la condition s'écrit en clair.
		$cUser = \user\User::model()
			->select(['id', 'email'])
			->whereId('IN', $users)
			->whereStatus(\user\User::ACTIVE)
			->where('email IS NOT NULL')
			->getCollection(index: 'id');

		$cEnrollment = EnrollmentLib::getOpenByCourseAndUsers($eCourse, $cUser);

		return $cUser->find(fn(\user\User $eUser) => $cEnrollment->offsetExists($eUser['id']) === FALSE);

	}

	/**
	 * Ce que l'e-mail d'annonce lit de la formation : ses leçons et leur durée, pour une formation
	 * datée sa prochaine date (`eDateNext`, vide sans date à venir), et pour une formation de réseau
	 * le réseau (`eCommunity`), dont la page publique reçoit l'inscription à une date, posés sur
	 * l'élément.
	 */
	public static function fillAnnounce(Course $eCourse): void {

		$cChapter = ChapterLib::getByCourse($eCourse);

		$eCourse['eCommunity'] = $eCourse->isNetwork()
			? \structure\CommunityLib::getByStructure($eCourse['community'])
			: new \structure\Community();

		// `fillUpcoming()` ne pose la prochaine date que sur une formation datée.
		$eCourse['eDateNext'] = new Date();

		$eCourse['lessons'] = ChapterLib::countLessons($cChapter);
		$eCourse['minutes'] = ChapterLib::sumMinutes($cChapter);

		DateLib::fillUpcoming(new \Collection([$eCourse]));

	}

	private static function send(array $mails): void {

		foreach($mails as ['to' => $to, 'content' => $content]) {

			\main\MailLib::getInstance()
				->setTo($to)
				->setContent(...$content)
				->send();

		}

	}

	public static function create(Share $e): void {

		parent::create($e);

		self::forget();

	}

	public static function update(Share $e, array $properties): void {

		parent::update($e, $properties);

		self::forget();

	}

	public static function delete(Share $e): void {

		parent::delete($e);

		self::forget();

	}

	private static function forget(): void {

		self::$cShareByUser = [];
		self::$cShareNetworkByUser = [];

	}

}
?>
