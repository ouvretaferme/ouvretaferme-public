<?php
namespace learning;

/**
 * Le décor commun des tests du package.
 *
 * Deux comptes :
 * - l'utilisateur 1, administrateur de Ouvretaferme : c'est le seul compte réel dont le décor
 *   dépend, le décor commun (`\structure\FixtureAiLib`) n'ayant aucun administrateur de Ouvretaferme ;
 * - un lecteur fictif, sans ferme ni groupe, semé une fois en `example.org` (domaine réservé où
 *   aucun envoi n'est délivré) et retrouvé par son adresse aux lancements suivants.
 *
 * Les structures sont celles du décor commun : l'école G2 (`COMMUNITY_EDUCATION`) et la ferme F3
 * (`FARM`), qui n'a ni membre ni rattachement. L'utilisateur 1 n'y est pas membre : `setUp()` l'y
 * fait administrateur dans la transaction du test (`getMemberships()`), pour que rien ne dépende
 * des adhésions d'une copie de production.
 *
 * Tout le reste (formations, modules, leçons, inscriptions) est écrit dans la transaction de chaque
 * test et annulé à la fin : `learning` et `structure` vivent dans la base commune, un seul
 * `rollBack()` les couvre tous deux.
 *
 * Les fichiers de test se chargent par ordre alphabétique : chaque test inclut ce fichier lui-même
 * plutôt que de compter sur un nom qui le ferait passer en premier.
 */
abstract class LearningFixtureTest extends \Test {

	/** Administrateur de Ouvretaferme dans la copie de production, le seul qui écrive une formation d'équipe */
	const USER_ADMIN = 1;
	const EMAIL_READER = 'zztest.lecteur-formation@example.org';

	/** Groupe de formation du décor commun, que l'utilisateur 1 administre le temps du test */
	const COMMUNITY_EDUCATION = \structure\FixtureAiLib::G2;

	/** Ferme du décor commun sans membre ni rattachement, que l'utilisateur 1 administre le temps du test */
	const FARM = \structure\FixtureAiLib::F3;

	protected \user\User $eUserAdmin;
	protected \user\User $eUserReader;

	/** Celles que porte chaque formation du décor */
	protected Theme $eTheme;
	protected Level $eLevel;

	private \user\User $eUserInitial;

	/** La raison du refus, quand le semis n'a pas pu aller au bout */
	private ?string $seedFailure = NULL;

	/** La raison du refus, quand une adhésion de `getMemberships()` n'a pas pu se poser pour ce test */
	private ?string $membershipFailure = NULL;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// Une exception qui sort de init() interrompt le fichier sans bilan : le refus se garde et
		// chaque test le rend en échec nommé.
		try {
			$this->eUserAdmin = $this->getUser(self::USER_ADMIN);
			$this->eUserReader = $this->seedReader();
			$this->eTheme = $this->getFirst(ThemeLib::getAll(), 'aucune thématique');
			$this->eLevel = $this->getFirst(LevelLib::getAll(), 'aucun niveau');
		} catch(\Throwable $e) {
			$this->seedFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

	}

	/**
	 * Les adhésions de l'utilisateur 1 se posent ici plutôt que dans `assertFixtures()` : des tests
	 * lisent l'école ou la ferme sans passer par lui. Une exception levée dans `setUp()` couperait
	 * le fichier sans bilan, le refus se garde donc pour `assertFixtures()`.
	 */
	public function setUp(): void {

		Course::model()->beginTransaction();

		$this->membershipFailure = NULL;

		if($this->seedFailure !== NULL) {
			return;
		}

		try {

			foreach($this->getMemberships() as $structure => $role) {
				$this->join($this->eUserAdmin, $this->getCommunity($structure), $role);
			}

			$this->forgetMemoization();

		} catch(\Throwable $e) {
			$this->membershipFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

	}

	/**
	 * Les structures du décor commun où l'utilisateur 1 agit, avec le rôle qu'il y tient.
	 *
	 * @return array<int, string>
	 */
	protected function getMemberships(): array {
		return [
			self::COMMUNITY_EDUCATION => 'admin',
			self::FARM => 'admin',
		];
	}

	public function tearDown(): void {

		Course::model()->rollBack();

		$this->forgetMemoization();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	protected function assertFixtures(): bool {

		if($this->seedFailure !== NULL) {
			$this->assertFail('semis du décor refusé : '.$this->seedFailure);
			return FALSE;
		}

		if($this->membershipFailure !== NULL) {
			$this->assertFail('adhésion de l\'utilisateur '.self::USER_ADMIN.' au décor refusée, structure/configure/fixtureAi write=1 à jouer si le décor manque : '.$this->membershipFailure);
			return FALSE;
		}

		if($this->eUserAdmin->isAdmin() === FALSE) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est plus administrateur dans cette base');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Le compte au nom duquel le test agit. Un élément vide joue le visiteur sans compte.
	 */
	protected function actAs(\user\User $eUser): void {

		\user\ConnectionLib::setOnline($eUser);

		$this->forgetMemoization();

	}

	protected function getUser(int $id): \user\User {
		return \user\UserLib::getById($id, \user\User::getSelection());
	}

	protected function getCommunity(int $id): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($id)
			->get();

	}

	/**
	 * La ferme du décor que `USER_ADMIN` administre le temps du test.
	 */
	protected function getFarm(): \structure\Structure {
		return $this->getCommunity(self::FARM);
	}

	/**
	 * Le compte entre dans la structure avec ce rôle, ou y revient s'il en était sorti.
	 */
	protected function join(\user\User $eUser, \structure\Structure $eStructure, string $role = 'admin'): void {

		$eMember = \structure\MemberLib::getByUserAndStructure($eUser, $eStructure);

		if($eMember->notEmpty()) {

			if($eMember['status'] !== \structure\Member::IN) {
				$eMember['status'] = \structure\Member::IN;
				\structure\MemberLib::update($eMember, ['status']);
			}

			return;

		}

		// Les rôles d'une structure se lisent dans un cache redis, qu'un rechargement de la base
		// depuis la production ne vide pas : un rôle tiré d'un cache antérieur porte un id que la base
		// n'a plus, et le membre créé n'administre rien. Vidé, le cache se relit sur la base courante.
		\structure\RoleLib::deleteCache($eStructure);

		$fw = new \FailWatch();

		// Hors build(), comme le pose InviteLib::accept() : un membre entre par une invitation, aucun
		// formulaire ne le construit.
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eStructure,
			'role' => \structure\RoleLib::getOne($eStructure, $role),
			'status' => \structure\Member::IN
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion du compte '.$eUser['id'].' à la structure '.$eStructure['id'].' refusée : '.json_encode($this->getFails($fw))));

	}

	/**
	 * Une formation de réseau dite gratuite par son panneau du prix, ce que sa publication exige
	 * (`Course::status.priced`). Relue entière ensuite.
	 */
	protected function setFree(Course $eCourse): Course {

		$result = $this->executePage('learning/course:doUpdatePrice', ['id' => (string)$eCourse['id'], 'paid' => '0']);

		if($result !== [\ReloadAction::class, NULL]) {
			throw new \Exception('formation '.$eCourse['id'].' non dite gratuite : '.json_encode($result));
		}

		return $this->getCourse($eCourse['id']);

	}

	/**
	 * Une formation écrite par le chemin de l'écran : elle naît de son seul titre, puis se classe,
	 * puis reçoit son état. Relue entière ensuite. `$classified` à FALSE la rend telle qu'elle sort
	 * de l'écran de création : sans présentation ni classement.
	 *
	 * `$catalog` est lu comme avant L4a, où `accepted` sur une formation de l'équipe voulait dire
	 * « lue sans compte » : pour l'équipe, `open` vaut `accepted` et `catalog` reste `accepted`
	 * (`create()` l'y pose) ; pour un groupe, `catalog` se pose tel quel, `open` reste à faux.
	 * Le paramètre garde son nom et son type : le renommage en `bool $open`, avec ses appelants,
	 * est un geste de fin de chantier, pas de ce lot.
	 */
	protected function createCourse(\structure\Structure $eStructureCommunity, string $status, string $catalog, bool $classified = TRUE, string $type = Course::ONLINE): Course {

		$eCourse = CourseLib::getCreateElement($eStructureCommunity, $type);

		$fw = new \FailWatch();

		$eCourse->build(CourseLib::getPropertiesCreate(), [
			'name' => 'zzTest formation',
			'type' => $type
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de la formation refusée : '.json_encode($fw->get())));

		CourseLib::create($eCourse);

		$eCourse = $this->getCourse($eCourse['id']);

		if($classified) {

			$fw = new \FailWatch();

			$eCourse->build(['summary', 'theme', 'level'], [
				'summary' => 'Une formation du décor de test.',
				'theme' => $this->eTheme['id'],
				'level' => $this->eLevel['id'],
			]);

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('classement de la formation refusé : '.json_encode($fw->get())));

			CourseLib::update($eCourse, ['summary', 'theme', 'level']);

		}

		// Posé sans `build()`, donc sans l'étape `Course::status.classified` : c'est le seul moyen de
		// monter une formation publiée qui ne porte encore aucune leçon, état que le produit refuse
		// et dont la moitié de ces tests part. Un décor de test s'en autorise, un chemin du produit
		// jamais.
		$eCourse['status'] = $status;

		if($eCourse->isTeam()) {
			$eCourse['open'] = ($catalog === Course::ACCEPTED);
		} else {
			$eCourse['catalog'] = $catalog;
		}

		CourseLib::update($eCourse, ['status', 'catalog', 'open']);

		return $this->getCourse($eCourse['id']);

	}

	protected function getCourse(int $id): Course {

		return Course::model()
			->select(Course::getSelection())
			->whereId($id)
			->get();

	}

	protected function getChapter(int $id): Chapter {

		return Chapter::model()
			->select(Chapter::getSelection())
			->whereId($id)
			->get();

	}

	protected function getLesson(int $id): Lesson {

		return Lesson::model()
			->select(Lesson::getSelection())
			->whereId($id)
			->get();

	}

	protected function createChapter(Course $eCourse, string $name): Chapter {

		$eChapter = new Chapter(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eChapter->build(['name'], ['name' => $name], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('module '.$name.' refusé : '.json_encode($fw->get())));

		ChapterLib::create($eChapter);

		return $eChapter;

	}

	protected function createLesson(Chapter $eChapter, string $name, int $minutes): Lesson {

		$eLesson = new Lesson(['course' => $eChapter['course'], 'chapter' => $eChapter]);

		$fw = new \FailWatch();

		$eLesson->build(['name', 'minutes'], ['name' => $name, 'minutes' => $minutes], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('leçon '.$name.' refusée : '.json_encode($fw->get())));

		LessonLib::create($eLesson);

		return $eLesson;

	}

	/**
	 * Une leçon qui a de quoi se lire (`Lesson::isWritten()`) : le programme, la fiche et le sommaire
	 * des tutoriels ne mènent qu'à elle, une leçon sans bloc y paraissant « À venir ».
	 */
	protected function createLessonWritten(Chapter $eChapter, string $name, int $minutes): Lesson {

		$eLesson = $this->createLesson($eChapter, $name, $minutes);

		$this->createBlock($eLesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS, 'zzTest première étape');

		return $eLesson;

	}

	/**
	 * Un bloc de premier niveau d'une leçon. Un parent naît avec sa première étape, qui prend `$title`
	 * (`BlockLib::create()`) : sans titre, elle naît vide, et la leçon reste vide (`Lesson::isWritten()`).
	 */
	protected function createBlock(Lesson $eLesson, string $type, string $variant, ?string $title): \block\Block {

		$eBlock = new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $eLesson['id'],
			'type' => $type,
			'variant' => $variant,
			'title' => $title,
			'content' => NULL,
		]);

		// Hors de `build()`, qui refuserait la première étape sans titre : la leçon vide est un cas que
		// les tests éprouvent, et il ne s'écrit pas par le formulaire.
		\block\BlockLib::create($eBlock);

		return $eBlock;

	}

	/**
	 * Une action de page jouée comme le bouton la poste, sur le modèle de `executePage()` dans
	 * `amap/test/AmapFixture.t.php`. Rend la classe de l'action levée et, pour un `FailAction`, le
	 * nom du refus : c'est lui qui porte le message, un refus sans nom ne s'afficherait pas.
	 * `$method` à `GET` joue un lien, dont `GET()` lit les paramètres. Une redirection rend son
	 * adresse dans `$redirect`, qui dit par exemple à quelle étape un parcours reprend. `$data`
	 * rend ce que la page a posé avant de lever son action, comme l'élément qu'elle a créé.
	 *
	 * @return array{0: string, 1: ?string}
	 */
	protected function executePage(string $path, array $input, string $method = 'POST', ?string &$redirect = NULL, ?\stdClass &$data = NULL): array {

		$redirect = NULL;
		$data = new \stdClass();

		// HttpRoute pose LIME_URL, pas le lanceur de tests : `ConnectionLib::checkLogged()`, que
		// la page appelle, la lit en valeur par défaut d'argument.
		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = match($method) {
			'GET' => [$input, [], $input],
			'POST' => [[], $input, $input]
		};

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $data);
			return ['aucune action', NULL];
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return [\FailAction::class, is_string($fail) ? $fail : implode(', ', array_column($fail->format(), 0))];
		} catch(\RedirectAction $action) {
			$redirect = new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);
			return [\RedirectAction::class, NULL];
		} catch(\Action $action) {
			return [get_class($action), NULL];
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * La route jouée comme un lien, sur le modèle de `executePage()`, mais qui rend les données
	 * préparées pour la vue, ou le nom de l'action levée à la place. `$method` à `POST` joue un
	 * formulaire qui répond par une vue, comme le quiz.
	 */
	protected function executeView(string $path, array $input, string $method = 'GET'): \stdClass|string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = match($method) {
			'GET' => [$input, [], $input],
			'POST' => [[], $input, $input]
		};

		$data = new \stdClass();

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), $data);
			return 'aucune action';
		} catch(\ViewAction) {
			return $data;
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * L'adresse où mène une redirection de l'accueil d'un réseau (`communityAddressAi.p.php`), qui
	 * lève une `TemporaryRedirectAction` et non une `RedirectAction` : `executePage()` n'en garde pas
	 * l'adresse. Elle hérite de `PermanentRedirectAction`, que le `catch` attrape. `NULL` pour toute
	 * autre action.
	 */
	protected function getRedirect(string $path, array $get): ?string {

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];
		[$_GET, $_REQUEST] = [$get, $get];

		try {
			\Page::execute(\Route::getCompatiblePages($path), new \stdClass());
			return NULL;
		} catch(\PermanentRedirectAction $action) {
			return new \ReflectionProperty(\PermanentRedirectAction::class, 'url')->getValue($action);
		} catch(\Action) {
			return NULL;
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * La page rendue comme le cadriciel la servirait, avec ce que `main\PageLib::common()` charge
	 * pour la navigation du compte connecté. Rend la classe de l'action levée à la place d'une vue.
	 *
	 * `$view` rejoue le fichier de vue sur un registre vide : `course.v.php` et `lesson.v.php`
	 * déclarent chacun une vue `edition`, et `View::__construct()` lève sur un nom déjà enregistré.
	 * Le `require_once` de `ViewAction::run()` trouve alors le fichier posé, et le registre est rendu
	 * tel quel ensuite (même procédé que `ReviewTest::renderMore()`). `NULL` laisse `run()` charger
	 * la vue une seule fois : `public.v.php` déclare une fonction, qu'un second chargement
	 * redéclarerait.
	 */
	protected function renderRoute(string $path, array $get = [], ?string $view = NULL): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$get, [], $get];

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->isLogged = $data->eUserOnline->notEmpty();
		$data->userDeletedAt = NULL;
		$data->ccStructureUser = \structure\StructureLib::getOnline();
		$data->nStructureUser = $data->ccStructureUser->countByDepth();
		$data->hasLearning = CourseLib::isOpenByStructures($data->ccStructureUser);
		$data->notificationsUnseen = [];
		$data->tos = NULL;
		$data->logInExternal = NULL;
		$data->browserObsolete = FALSE;

		$action = NULL;

		try {
			\Page::execute(\Route::getCompatiblePages($path), $data);
		} catch(\ViewAction $viewAction) {
			$action = $viewAction;
		} catch(\Action $other) {
			return get_class($other);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

		if($action === NULL) {
			throw new \Exception($path.' ne lève pas de vue');
		}

		// `ViewAction::run()` ouvre son propre tampon et ne le referme pas.
		$level = ob_get_level();
		ob_start();

		if($view === NULL) {

			$action->run();

		} else {

			$views = new \ReflectionProperty(\View::class, 'views');
			$viewsInitial = $views->getValue();
			$views->setValue(NULL, []);

			try {
				require \Package::getFileFromUri($view, 'view');
				$action->run();
			} finally {
				$views->setValue(NULL, $viewsInitial);
			}

		}

		$rendered = '';

		while(ob_get_level() > $level) {
			$rendered = ob_get_clean().$rendered;
		}

		return $rendered;

	}

	/**
	 * Une promotion écrite par son Lib, qui tient le compteur du groupe ; le rangement des stagiaires
	 * passe par `associateUser()`, le seul chemin du produit qui écrit `users`.
	 */
	protected function createSection(\structure\Structure $eCommunity, string $name, array $users, string $status = \structure\Section::OPEN): \structure\Section {

		$eSection = new \structure\Section([
			'structure' => $eCommunity,
			'name' => $name,
			'users' => [],
			'status' => $status
		]);

		$fw = new \FailWatch();

		\structure\SectionLib::create($eSection);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de la promotion refusée : '.json_encode($fw->get())));

		foreach($users as $eUser) {
			\structure\SectionLib::associateUser($eSection, $eUser);
		}

		return \structure\Section::model()
			->select(\structure\Section::getSelection())
			->whereId($eSection)
			->get();

	}

	/**
	 * Le chemin de `doShare`, par le même `ShareLib::buildFromInput()` : `$posted` associe chaque
	 * promotion cochée à ses deux dates, et se traduit en ce que le panneau poste.
	 */
	protected function share(Course $eCourse, array $posted): void {

		// Ce que poste le navigateur : des chaînes, y compris pour les ids des cases cochées.
		$input = ['section' => [], 'opensAt' => [], 'dueAt' => []];

		foreach($posted as $section => [$opensAt, $dueAt]) {
			$input['section'][] = (string)$section;
			$input['opensAt'][$section] = $opensAt;
			$input['dueAt'][$section] = $dueAt ?? '';
		}

		$fw = new \FailWatch();

		$cShare = ShareLib::buildFromInput($eCourse, $input);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('partage refusé : '.json_encode($this->getFails($fw))));

		ShareLib::synchronizeCourse($eCourse, $cShare);

	}

	/** Le nom de chaque refus et le champ auquel il se rattache. */
	protected function getFails(\FailWatch $fw): array {
		return array_map(fn(array $fail) => [$fail[1], $fail[3]], $fw->get());
	}

	/**
	 * Le nombre d'e-mails partis depuis le début du processus : en dev, `SendLib` n'en envoie que pour
	 * la liste `$devSendOnly` de son package, où est le compte 1. Un test qui traverse un envoi le relève avant
	 * et après, et l'égalité dit qu'aucune adresse réelle n'a été écrite. Le journal de
	 * `\main\MailLib::getSent()` tient avec ou sans base/mail, dont la table n'est pas publiée.
	 */
	protected function getLastMail(): int {
		return count(\main\MailLib::getSent());
	}

	/**
	 * Les séances d'une date telles que son formulaire les poste (`DateSequenceUi::getRow()`, lot
	 * date-mixte), à fusionner avec les autres champs de la date. Chaque séance s'écrit
	 * `[jour, début, fin, mode, lieu]`, son index étant sa position ; le mode vaut
	 * `DateSequence::ONSITE` ou `REMOTE`, le lieu peut manquer en visio.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string, 3: string, 4?: ?string}> $sequences
	 */
	protected function postSequences(array $sequences): array {

		$input = ['sequences' => [], 'startAt' => [], 'endAt' => [], 'mode' => [], 'place' => []];

		foreach($sequences as $index => $sequence) {

			$input['sequences'][] = (string)$index;
			$input['startAt'][$index] = ['date' => $sequence[0], 'time' => $sequence[1]];
			$input['endAt'][$index] = ['time' => $sequence[2]];
			$input['mode'][$index] = $sequence[3];
			$input['place'][$index] = $sequence[4] ?? '';

		}

		return $input;

	}

	/**
	 * Remplace les séances de la date par celles-ci, `[début, fin]`, dans le mode et au lieu de sa
	 * première séance, par `DateLib::update()`. Sans `build()`, qui refuse une date du jour ou passée
	 * et une séance qui finit à 23:00 sur une date qui en compte d'autres : le décor les veut.
	 *
	 * @param array<int, array{0: string, 1: string}> $sequences
	 */
	protected function writeSequences(Date $eDate, array $sequences): Date {

		$eDateSequenceFirst = $eDate['cDateSequence']->first();

		$eDate['cDateSequence'] = new \Collection(array_map(fn(array $sequence) => new DateSequence([
			'startAt' => $sequence[0],
			'endAt' => $sequence[1],
			'mode' => $eDateSequenceFirst['mode'],
			'place' => $eDateSequenceFirst['place'],
		]), $sequences));

		$eDate['startAt'] = $sequences[0][0];
		$eDate['endAt'] = $sequences[count($sequences) - 1][1];

		DateLib::update($eDate, ['sequences']);

		return DateLib::getById($eDate['id']);

	}

	protected function day(int $offset): string {
		return date('Y-m-d', strtotime(\Clock::date().' '.($offset >= 0 ? '+' : '').$offset.' days'));
	}

	/**
	 * Thématiques et niveaux sont assurés par `learning/configure/theme` et `learning/configure/level` :
	 * une base qui ne les a pas joués le dit par son nom au lieu de lever sur un index absent.
	 */
	private function getFirst(\Collection $c, string $missing): \Element {

		if($c->empty()) {
			throw new \Exception($missing.', learning/configure/theme et learning/configure/level à jouer');
		}

		return $c->first();

	}

	/** Retrouvé par son adresse, que l'unicité de `user.email` rend sûre. */
	private function seedReader(): \user\User {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::EMAIL_READER)
			->get();

		if($eUser->notEmpty()) {
			return $eUser;
		}

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Lecteur',
			'email' => self::EMAIL_READER,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte '.self::EMAIL_READER.' refusée : '.json_encode($fw->get())));

		return $this->getUser($eUser['id']);

	}

	/**
	 * Les adhésions et le rôle dans un groupe sont gardés en statiques de processus : sans les
	 * vider, un test lirait les droits du compte précédent. Aucune de ces classes n'offre de purge,
	 * chaque statique reprend sa valeur de départ. Les fenêtres de lecture de `ShareLib` se vident à
	 * chaque écriture d'un partage, pas au `rollBack()` qui efface ces partages.
	 */
	protected function forgetMemoization(): void {

		foreach([\structure\Structure::class, \structure\ConfigurationLib::class, \structure\FeatureLib::class, \structure\MemberLib::class, ShareLib::class] as $class) {

			foreach(new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

				if($property->getDeclaringClass()->getName() === $class and $property->hasDefaultValue()) {
					$property->setValue(NULL, $property->getDefaultValue());
				}

			}

		}

	}

}
?>
