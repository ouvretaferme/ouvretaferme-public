<?php
namespace learning;

/**
 * L'API des formations de bout en bout, par `\ApiRoute::run()` (cf `\dev\ApiTest` : jeton de dev,
 * corps simulé), avec la clé d'un groupe de formation.
 *
 * Chaque refus est précédé du cas accepté qui lui correspond : un refus seul ne dit pas si la route
 * refuse ce qu'elle doit, ou tout. Pour une lecture, le cas accepté est la relecture en 200 de ce
 * que le test vient de créer (`assertRead()`) : un 404 sur la ressource d'une autre structure ne
 * prouve la borne que si la même route rend ses propres ressources.
 *
 * Tout s'écrit dans la base commune (`learning` n'est pas un package de structure) : la transaction
 * ouverte sur `Course::model()` par `\dev\ApiTest` couvre les formations, les inscriptions et la
 * progression.
 *
 * Lancement : dev/test package=learning file=Api
 */
class ApiTest extends \dev\ApiTest {

	/** Groupe de formation du décor commun, celui de `LearningFixtureTest` */
	const STRUCTURE = \structure\FixtureAiLib::G2;

	/** Réseau du décor commun : une clé acceptée, et l'auteur d'une formation « d'ailleurs » */
	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	/** Ferme du décor commun */
	const FARM = \structure\FixtureAiLib::F1;

	/** Groupe de producteurs du décor commun : un groupe, mais ni école ni réseau */
	const COMMUNITY_PRODUCER = \structure\FixtureAiLib::G1;

	/** Compte du décor commun sans structure, qui porte la clé des groupes du décor sans administrateur */
	const EMAIL_KEY = 'decor.sans.structure@example.org';

	private Theme $eTheme;
	private Level $eLevel;

	public function init(): void {

		parent::init();

		$this->eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$this->eLevel = LevelLib::getAll()->first() ?? new Level();

	}

	protected function getTransactionModel(): \ModuleModel {
		return Course::model();
	}

	/**
	 * Une structure du décor absente ou d'un autre type rendrait les refus de `testStructureKey()`
	 * verts sans avoir refusé ce qu'ils nomment : chacune est relue et comparée au type attendu.
	 *
	 * Le réseau et le groupe de producteurs du décor n'ont aucun administrateur, et une clé ne
	 * s'obtient qu'au nom d'un administrateur (`ApiSetting::$devAccessTokenUser`) : le compte du décor
	 * sans structure (`EMAIL_KEY`) les administre le temps du test.
	 */
	private function assertFixtures(): bool {

		$expected = [
			self::STRUCTURE => [\structure\Structure::COMMUNITY, \structure\Structure::EDUCATION],
			self::COMMUNITY_NETWORK => [\structure\Structure::COMMUNITY, \structure\Structure::NETWORK],
			self::FARM => [\structure\Structure::PRODUCER, NULL],
			self::COMMUNITY_PRODUCER => [\structure\Structure::COMMUNITY, \structure\Structure::PRODUCER],
		];

		$cStructure = \structure\Structure::model()
			->select(['id', 'type', 'communityType'])
			->whereId('IN', array_keys($expected))
			->getCollection(index: 'id');

		$wrong = [];

		foreach($expected as $structure => [$type, $communityType]) {

			$eStructure = $cStructure->offsetExists($structure) ? $cStructure[$structure] : new \structure\Structure();

			if(
				$eStructure->empty() or
				$eStructure['type'] !== $type or
				$eStructure['communityType'] !== $communityType
			) {
				$wrong[] = $structure.' ('.$type.($communityType === NULL ? '' : ', '.$communityType).' attendu)';
			}

		}

		if($wrong !== []) {
			$this->assertFail('structures du décor absentes ou d\'un autre type, structure/configure/fixtureAi write=1 à jouer : '.implode(', ', $wrong));
			return FALSE;
		}

		foreach([self::COMMUNITY_NETWORK, self::COMMUNITY_PRODUCER] as $structure) {
			$this->joinAdmin(\structure\StructureLib::getById($structure));
		}

		if(
			$this->eTheme->empty() or
			$this->eLevel->empty()
		) {
			$this->assertFail('décor absent : thématiques et niveaux, learning/configure/theme et learning/configure/level à jouer');
			return FALSE;
		}

		// `testUsage` inscrit trois lecteurs distincts. La copie de production en porte des milliers
		// et une base neuve peut n'en avoir aucun : sans ce compte, l'indice absent lèverait au lieu
		// de nommer le décor manquant.
		$users = \user\User::model()
			->whereId('!=', \user\UserSetting::$apiUserId)
			->count();

		if($users < 3) {
			$this->assertFail('décor absent : trois comptes au moins hors compte d\'API, '.$users.' dans cette base');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Le cas accepté d'une lecture : la ressource que le test vient d'écrire se relit en 200.
	 */
	private function assertRead(string $route, int $id, string $name): void {

		$response = $this->callApi('GET', $route, get: ['id' => $id]);

		$this->assertEquals(200, $response['status'], 'GET '.$route.' : '.json_encode($response['body']));
		$this->assertEquals($name, $response['body']['name'] ?? NULL, 'GET '.$route.' relit le nom');

	}

	/**
	 * Aucune route ne date une progression : la seule façon de la placer hors des trente derniers
	 * jours est de réécrire `doneAt` en base, comme décor et non comme écriture du produit.
	 */
	private function backdateProgress(\user\User $eUser, int $course, int $days): void {

		Progress::model()
			->whereUser($eUser)
			->whereCourse($course)
			->update(['doneAt' => new \Sql('NOW() - INTERVAL '.$days.' DAY')]);

	}

	private function asStructure(int $structure, \Closure $callback): void {

		$tokenInitial = \api\ApiSetting::$devAccessToken;

		\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken($structure);

		try {
			$callback();
		} finally {
			\api\ApiSetting::$devAccessToken = $tokenInitial;
		}

	}

	private function createCourse(string $name = 'zzTest formation API'): int {

		$response = $this->callApi('POST', 'course', body: [
			'name' => $name,
			'summary' => 'Une formation écrite par l\'API.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
		]);

		if($response['status'] !== 201) {
			throw new \Exception('création de la formation refusée : '.json_encode($response['body']));
		}

		$this->assertRead('course/{id}', $response['body']['id'], $name);

		return $response['body']['id'];

	}

	private function createChapter(int $course, string $name = 'Module API'): int {

		$response = $this->callApi('POST', 'chapter', body: [
			'course' => $course,
			'name' => $name,
			'summary' => NULL,
			'phase' => NULL,
		]);

		if($response['status'] !== 201) {
			throw new \Exception('création du module refusée : '.json_encode($response['body']));
		}

		$this->assertRead('chapter/{id}', $response['body']['id'], $name);

		return $response['body']['id'];

	}

	private function lessonBody(?int $chapter): array {

		return [
			'chapter' => $chapter,
			'name' => 'Leçon API',
			'minutes' => 12,
			'content' => '<p>Contenu de la leçon.</p>',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => NULL,
		];

	}

	private function createLesson(int $chapter): int {

		$response = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapter));

		if($response['status'] !== 201) {
			throw new \Exception('création de la leçon refusée : '.json_encode($response['body']));
		}

		$this->assertRead('lesson/{id}', $response['body']['id'], 'Leçon API');

		return $response['body']['id'];

	}

	private function joinAdmin(\structure\Structure $eStructure): void {

		$eUser = $this->getUserKey();

		if(\structure\MemberLib::isAdmin($eUser, $eStructure)) {
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
			'role' => \structure\RoleLib::getOne($eStructure, 'admin'),
			'status' => \structure\Member::IN
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion de '.self::EMAIL_KEY.' à la structure '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

	}

	private function getUserKey(): \user\User {
		return \user\UserLib::getByEmail(self::EMAIL_KEY);
	}

	/** Une formation d'une autre structure, écrite sans passer par l'API. */
	private function createCourseForeign(): Course {

		$eCourse = CourseLib::getCreateElement(\structure\StructureLib::getById(self::COMMUNITY_NETWORK));
		$eCourse->merge([
			'name' => 'zzTest formation d\'ailleurs',
			'summary' => 'Une formation du réseau.',
			'theme' => $this->eTheme,
			'level' => $this->eLevel,
		]);

		CourseLib::create($eCourse);

		return $eCourse;

	}

	private function assertErrorCode(string $code, array $response, string $message): void {
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $message.' ('.json_encode($response['body']).')');
	}

	public function testCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$create = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation API',
			'summary' => 'Une formation écrite par l\'API.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals(Course::DRAFT, $create['body']['status'] ?? NULL, 'une formation naît en brouillon');
		$this->assertEquals(Course::ONLINE, $create['body']['type'] ?? NULL, 'sans type, une formation naît en ligne');

		foreach(['createdAt', 'createdBy', 'catalog', 'open', 'community'] as $property) {
			$this->assertFalse(array_key_exists($property, $create['body']), $property.' n\'est pas rendu');
		}

		$id = $create['body']['id'];

		$this->assertRead('course/{id}', $id, 'zzTest formation API');

		$this->assertEquals(self::STRUCTURE, Course::model()->select('community')->whereId($id)->get()['community']['id'], 'la formation appartient au groupe de la clé');

		$list = $this->callApi('GET', 'courseList', get: ['ids' => [$id]]);

		$this->assertEquals([$id], array_column($list['body']['list'] ?? [], 'id'), 'listée');

		// Une formation sans aucune leçon ne se publie pas : le programme précède la publication.
		$this->createLesson($this->createChapter($id));

		$update = $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['name', 'status'],
			'name' => 'zzTest formation API renommée',
			'status' => Course::PUBLISHED,
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals('zzTest formation API renommée', $update['body']['name'] ?? NULL);
		$this->assertTrue(($update['body']['publishedAt'] ?? NULL) !== NULL, 'publiée, datée');

		// Les deux sens : un filtre qui rendrait 400 écarterait la formation lui aussi, et la seule
		// liste vide ne dirait pas laquelle des deux raisons l'a vidée.
		$draft = $this->callApi('GET', 'courseList', get: ['ids' => [$id], 'status' => Course::DRAFT]);

		$this->assertEquals(200, $draft['status'], json_encode($draft['body']));
		$this->assertEquals([], array_column($draft['body']['list'] ?? [], 'id'), 'le filtre d\'état l\'écarte');

		$published = $this->callApi('GET', 'courseList', get: ['ids' => [$id], 'status' => Course::PUBLISHED]);

		$this->assertEquals(200, $published['status'], json_encode($published['body']));
		$this->assertEquals([$id], array_column($published['body']['list'] ?? [], 'id'), 'et la retient sur son état');

		$this->assertEquals(200, $this->callApi('GET', 'themeList')['status']);
		$this->assertEquals(ThemeLib::getAll()->getIds(), array_column($this->callApi('GET', 'themeList')['body']['list'], 'id'), 'les thématiques');
		$this->assertEquals(LevelLib::getAll()->getIds(), array_column($this->callApi('GET', 'levelList')['body']['list'], 'id'), 'les niveaux');

		$delete = $this->callApi('DELETE', 'course/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals(404, $this->callApi('GET', 'course/{id}', get: ['id' => $id])['status'], 'supprimée');

	}

	/**
	 * La sous-thématique (lot T1) se lit et ne s'écrit pas : `themeList` rend celles de chaque
	 * thématique dans leur ordre, la lecture d'une formation rend la sienne, et un PUT qui change la
	 * thématique la vide, puisqu'une formation ne porte qu'une sous-thématique de sa thématique.
	 */
	public function testSubtheme(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cSubtheme = SubthemeLib::getByTheme($this->eTheme);
		$eThemeOther = ThemeLib::getAll()->find(fn(Theme $eTheme) => $eTheme['id'] !== $this->eTheme['id'])->first();

		if($cSubtheme->empty() or $eThemeOther === NULL) {
			$this->assertFail('décor absent : une thématique avec ses sous-thématiques et une autre, learning/configure/theme à jouer');
			return;
		}

		$themes = array_column($this->callApi('GET', 'themeList')['body']['list'], NULL, 'id');

		$this->assertEquals($cSubtheme->getIds(), array_column($themes[$this->eTheme['id']]['subthemes'], 'id'), 'les sous-thématiques de la thématique, dans leur ordre');
		$this->assertEquals(['id', 'name', 'position'], array_keys($themes[$this->eTheme['id']]['subthemes'][0]), 'une sous-thématique rend son nom et sa place');

		$id = $this->createCourse();

		$eCourse = Course::model()->select(Course::getSelection())->whereId($id)->get();
		$eCourse['subtheme'] = $cSubtheme->first();
		CourseLib::update($eCourse, ['subtheme']);

		$read = $this->callApi('GET', 'course/{id}', get: ['id' => $id]);

		$this->assertEquals($cSubtheme->first()['id'], $read['body']['subtheme'] ?? NULL, 'la lecture rend la sous-thématique');

		$update = $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['theme'],
			'theme' => $eThemeOther['id'],
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertTrue(array_key_exists('subtheme', $update['body']), 'la réponse rend la sous-thématique');
		$this->assertEquals(NULL, $update['body']['subtheme'], 'la thématique changée la vide');
		$this->assertTrue(Course::model()->whereId($id)->whereSubtheme(NULL)->exists(), 'et la base le confirme');

	}

	/**
	 * La pause des inscriptions (lot T13) se lit et ne s'écrit qu'à l'écran, sous
	 * `Course::acceptUpdateEnrollmentPaused()` : un PUT qui la porte ne change rien en base.
	 */
	public function testEnrollmentPaused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$id = $this->createCourse();

		// La pause posée sans `build()` ni l'écran, qui ne la règlent que sur une formation publiée : la
		// lecture seule est ce que l'on éprouve, pas la règle de l'interrupteur.
		$eCourse = Course::model()->select(Course::getSelection())->whereId($id)->get();
		$eCourse['enrollmentPaused'] = TRUE;
		CourseLib::update($eCourse, ['enrollmentPaused']);

		$read = $this->callApi('GET', 'course/{id}', get: ['id' => $id]);

		$this->assertEquals(TRUE, $read['body']['enrollmentPaused'] ?? NULL, 'la lecture rend la pause');

		$update = $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['enrollmentPaused'],
			'enrollmentPaused' => FALSE,
		], get: ['id' => $id]);

		$this->assertEquals(400, $update['status'], 'un PUT ne rouvre pas');
		$this->assertErrorCode('property_unknown', $update, 'la pause ne se règle pas par l\'API');
		$this->assertTrue(Course::model()->whereId($id)->whereEnrollmentPaused(TRUE)->exists(), 'et la base garde la pause');

	}

	/**
	 * Le type se choisit à la création. Seul un réseau crée en présentiel ou en visio : une école
	 * qui le demande est refusée sous le nom de l'étape, et rien n'est écrit.
	 */
	public function testCourseType(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$courses = Course::model()->whereCommunity(self::STRUCTURE)->count();

		$school = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation API sur place',
			'summary' => NULL,
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
			'type' => Course::ONSITE,
		]);

		$this->assertEquals(400, $school['status'], json_encode($school['body']));
		$this->assertErrorCode('Course::type.network', $school, 'une école ne crée pas sur place');
		$this->assertEquals($courses, Course::model()->whereCommunity(self::STRUCTURE)->count(), 'rien d\'écrit');

		$this->asStructure(self::COMMUNITY_NETWORK, function() {

			$network = $this->callApi('POST', 'course', body: [
				'name' => 'zzTest formation API en visio',
				'summary' => NULL,
				'theme' => $this->eTheme['id'],
				'level' => $this->eLevel['id'],
				'type' => Course::REMOTE,
			]);

			$this->assertEquals(201, $network['status'], json_encode($network['body']));
			$this->assertEquals(Course::REMOTE, $network['body']['type'] ?? NULL, 'un réseau crée en visio');

			$unknown = $this->callApi('POST', 'course', body: [
				'name' => 'zzTest formation API hybride',
				'summary' => NULL,
				'theme' => $this->eTheme['id'],
				'level' => $this->eLevel['id'],
				'type' => 'hybrid',
			]);

			$this->assertEquals(400, $unknown['status'], json_encode($unknown['body']));
			$this->assertErrorCode('Course::type.check', $unknown, 'un type inconnu');

		});

	}

	/**
	 * Le prix d'une formation de réseau s'écrit par l'API, par la même `build()` que son panneau à
	 * l'écran : sans prix la publication se refuse (`status.priced`), « Formation payante » sans
	 * montant aussi (`price.amount`), non ramène les deux montants à 0 et vide financeurs et
	 * conditions, un PUT complet passe sans `paid`, que la lecture ne rend pas, et non se refuse sous
	 * une offre payée en ligne par le stagiaire (`paid.trainee`).
	 */
	public function testNetworkPrice(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->asStructure(self::COMMUNITY_NETWORK, function() {

			$create = $this->callApi('POST', 'course', body: [
				'name' => 'zzTest formation API de réseau',
				'summary' => 'Une formation de réseau publiée par l\'API.',
				'theme' => $this->eTheme['id'],
				'level' => $this->eLevel['id'],
				'type' => Course::REMOTE,
			]);

			$this->assertEquals(201, $create['status'], json_encode($create['body']));

			$id = $create['body']['id'];

			$unpriced = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['status'],
				'status' => Course::PUBLISHED,
			], get: ['id' => $id]);

			$this->assertEquals(400, $unpriced['status'], json_encode($unpriced['body']));
			$this->assertErrorCode('Course::status.priced', $unpriced, 'sans prix, pas de publication');

			$noAmount = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['paid', 'priceFunded', 'price', 'status'],
				'paid' => TRUE,
				'priceFunded' => NULL,
				'price' => NULL,
				'status' => Course::PUBLISHED,
			], get: ['id' => $id]);

			$this->assertEquals(400, $noAmount['status'], json_encode($noAmount['body']));
			$this->assertErrorCode('Course::price.amount', $noAmount, 'payante sans montant');
			$this->assertTrue(Course::model()->whereId($id)->whereStatus(Course::DRAFT)->exists(), 'toujours en brouillon');

			$published = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['paid', 'priceFunded', 'price', 'status'],
				'paid' => TRUE,
				'priceFunded' => 120,
				'price' => 350,
				'status' => Course::PUBLISHED,
			], get: ['id' => $id]);

			$this->assertEquals(200, $published['status'], json_encode($published['body']));
			$this->assertEquals([120.0, 350.0, Course::PUBLISHED], [$published['body']['priceFunded'] ?? NULL, $published['body']['price'] ?? NULL, $published['body']['status'] ?? NULL], 'publiée avec son prix, rendu par la réponse');

			$free = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['paid', 'priceFunded', 'price'],
				'paid' => FALSE,
				'priceFunded' => 120,
				'price' => 350,
			], get: ['id' => $id]);

			$this->assertEquals(200, $free['status'], json_encode($free['body']));
			$this->assertEquals([0.0, 0.0], [$free['body']['priceFunded'] ?? NULL, $free['body']['price'] ?? NULL], 'gratuite, les montants postés ramenés à 0');

			// Les financeurs et les conditions s'écrivent à l'écran seulement : posés par la même
			// `build()` que le panneau du prix, puis la formation repasse payante.
			$eCourse = CourseLib::getById($id);
			$eCourse->build(['paid', 'priceFunded', 'price', 'funding', 'fundingNote'], ['paid' => '1', 'priceFunded' => '120', 'price' => '350', 'funding' => [Course::VIVEA], 'fundingNote' => 'zzTest conditions']);
			CourseLib::update($eCourse, ['paid', 'priceFunded', 'price', 'funding', 'fundingNote']);

			$this->assertTrue(Course::model()->whereId($id)->whereFundingNote('zzTest conditions')->exists(), 'payante, avec financeurs et conditions');

			$freeAlone = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['paid'],
				'paid' => FALSE,
			], get: ['id' => $id]);

			$this->assertEquals(200, $freeAlone['status'], json_encode($freeAlone['body']));

			$eCourseFree = Course::model()->select(['priceFunded', 'price', 'funding', 'fundingNote'])->whereId($id)->get();

			$this->assertEquals([0.0, 0.0, NULL, NULL], [$eCourseFree['priceFunded'], $eCourseFree['price'], $eCourseFree['funding'], $eCourseFree['fundingNote']], 'non seul ramène les montants à 0 et vide financeurs et conditions, comme le panneau');

			// Un client renvoie ce qu'il a lu, où `paid` n'est pas : le PUT complet passe sans lui.
			$read = $this->callApi('GET', 'course/{id}', get: ['id' => $id])['body'];

			$complete = $this->callApi('PUT', 'course/{id}', body: [
				'name' => $read['name'],
				'summary' => $read['summary'],
				'theme' => $read['theme'],
				'level' => $read['level'],
				'priceFunded' => 80,
				'price' => 200,
				'status' => $read['status'],
				'description' => $read['description'],
				'objectives' => $read['objectives'],
				'audience' => $read['audience'],
				'prerequisites' => $read['prerequisites'],
			], get: ['id' => $id]);

			$this->assertEquals(200, $complete['status'], json_encode($complete['body']));
			$this->assertEquals([80.0, 200.0], [$complete['body']['priceFunded'] ?? NULL, $complete['body']['price'] ?? NULL], 'un PUT complet sans paid écrit le prix');

			// Décor : l'offre ne s'écrit qu'à l'écran, sous un compte de reversement et le paiement en
			// ligne ouvert, que ce test ne monte pas. `build()` n'y vérifierait que ces deux verrous,
			// étrangers au refus éprouvé ici.
			Course::model()->whereId($id)->update(['offer' => Course::TRAINEE, 'price' => 45.0]);

			$trainee = $this->callApi('PUT', 'course/{id}', body: [
				'properties' => ['paid', 'priceFunded', 'price'],
				'paid' => FALSE,
				'priceFunded' => 0,
				'price' => 0,
			], get: ['id' => $id]);

			$this->assertEquals(400, $trainee['status'], json_encode($trainee['body']));
			$this->assertErrorCode('Course::paid.trainee', $trainee, 'gratuite, refusée sous le paiement en ligne par le stagiaire');
			$this->assertTrue(Course::model()->whereId($id)->wherePrice(45.0)->exists(), 'et rien ne s\'écrit');

		});

	}

	public function testChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();

		$create = $this->callApi('POST', 'chapter', body: [
			'course' => $course,
			'name' => 'Module API',
			'summary' => 'Le premier module.',
			'phase' => NULL,
		]);

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals($course, $create['body']['course'] ?? NULL);
		$this->assertEquals(0, $create['body']['position'] ?? NULL, 'premier du programme');

		$id = $create['body']['id'];

		$this->assertRead('chapter/{id}', $id, 'Module API');

		$list = $this->callApi('GET', 'course/{id}/chapterList', get: ['id' => $course]);

		$this->assertEquals(200, $list['status'], json_encode($list['body']));
		$this->assertEquals([$id], array_column($list['body']['list'], 'id'));

		$update = $this->callApi('PUT', 'chapter/{id}', body: [
			'properties' => ['name'],
			'name' => 'Module API renommé',
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals('Module API renommé', $update['body']['name'] ?? NULL);

		$this->createLesson($id);

		$delete = $this->callApi('DELETE', 'chapter/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals([0, 0], [
			Chapter::model()->whereId($id)->count(),
			Lesson::model()->whereCourse($course)->count(),
		], 'le module part avec ses leçons');

	}

	public function testLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);
		$chapterSecond = $this->createChapter($course, 'Second module');

		$create = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapter));

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals($course, $create['body']['course'] ?? NULL, 'la formation suit le module');
		$this->assertEquals($chapter, $create['body']['chapter'] ?? NULL);

		$id = $create['body']['id'];

		$this->assertRead('lesson/{id}', $id, 'Leçon API');

		$list = $this->callApi('GET', 'chapter/{id}/lessonList', get: ['id' => $chapter]);

		$this->assertEquals([$id], array_column($list['body']['list'] ?? [], 'id'));

		// Le point 1 du plan : sans `cChapter` posé par getById(), chaque modification levait.
		$update = $this->callApi('PUT', 'lesson/{id}', body: $this->lessonBody($chapterSecond) + ['name' => 'Leçon API déplacée'], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals($chapterSecond, $update['body']['chapter'] ?? NULL, 'changée de module dans sa formation');

		// Une modification partielle ne porte pas `chapter` : l'étape `owned` n'a rien à juger.
		$rename = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['name'],
			'name' => 'Leçon API renommée',
		], get: ['id' => $id]);

		$this->assertEquals(200, $rename['status'], json_encode($rename['body']));
		$this->assertEquals(['Leçon API renommée', $chapterSecond], [$rename['body']['name'] ?? NULL, $rename['body']['chapter'] ?? NULL], 'renommée sans changer de module');

		$delete = $this->callApi('DELETE', 'lesson/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals(0, Lesson::model()->whereId($id)->count(), 'supprimée');

	}

	/**
	 * Une leçon ne change pas de formation : un module d'une autre formation du même groupe est
	 * refusé comme celui d'un autre groupe, et la progression n'a donc jamais à suivre.
	 */
	public function testLessonStaysInCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);
		$lesson = $this->createLesson($chapter);

		$chapterOther = $this->createChapter($this->createCourse('zzTest autre formation'));

		$lessonOther = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapterOther));

		$this->assertEquals(201, $lessonOther['status'], 'le module de l\'autre formation reçoit une leçon neuve');
		$this->assertRead('lesson/{id}', $lessonOther['body']['id'], 'Leçon API');

		$update = $this->callApi('PUT', 'lesson/{id}', body: $this->lessonBody($chapterOther), get: ['id' => $lesson]);

		$this->assertEquals(400, $update['status']);
		$this->assertErrorCode('Lesson::chapter.owned', $update, 'module d\'une autre formation refusé');
		$this->assertEquals($chapter, Lesson::model()->select('chapter')->whereId($lesson)->get()['chapter']['id'], 'la leçon reste dans son module');

	}

	/**
	 * Le contenu d'une leçon vit dans ses blocs depuis B0 : l'API l'écrit dans le bloc texte unique
	 * de la leçon, le relit bloc à bout, et refuse de l'écrire d'un seul tenant sur une leçon
	 * composée. La colonne `content` n'est jamais écrite. Chaque refus suit son cas accepté.
	 */
	public function testLessonContentInBlocks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);

		$create = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapter));
		$this->assertEquals(201, $create['status'], json_encode($create['body']));

		$id = $create['body']['id'];
		$cBlock = \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id);

		$this->assertEquals([\block\BlockTypeLib::TEXT], array_values($cBlock->getColumn('type')), 'la création pose un bloc texte');
		$this->assertTrue(in_array('content', Lesson::model()->getProperties(), TRUE) === FALSE, 'la leçon n\'a plus de colonne de contenu, qui vit dans ses blocs (lot B1)');

		$content = $cBlock->first()['content'];

		$this->assertTrue(str_contains($content, 'Contenu de la leçon.'), 'le bloc porte le texte posté');

		$read = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);
		$this->assertEquals($content, $read['body']['content'] ?? NULL, 'la lecture rend le texte du bloc');

		$list = $this->callApi('GET', 'chapter/{id}/lessonList', get: ['id' => $chapter]);
		$this->assertEquals([$content], array_column($list['body']['list'] ?? [], 'content'), 'la liste des leçons rend le texte du bloc');

		// Écriture sur un bloc : il est réécrit, pas doublé.
		$update = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['content'],
			'content' => '<p>Contenu réécrit.</p>',
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));

		$cBlock = \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id);

		$this->assertEquals(1, $cBlock->count(), 'toujours un seul bloc');
		$this->assertTrue(str_contains($cBlock->first()['content'], 'Contenu réécrit.'), 'le bloc porte le nouveau texte');
		$this->assertEquals($cBlock->first()['content'], $update['body']['content'] ?? NULL, 'la réponse rend le nouveau texte');

		// Un contenu vidé retire le bloc ; réécrit sur zéro bloc, il en pose un.
		$empty = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => NULL], get: ['id' => $id]);

		$this->assertEquals(200, $empty['status'], json_encode($empty['body']));
		$this->assertEquals(0, \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id)->count(), 'contenu vidé, bloc retiré');
		$this->assertEquals(NULL, $empty['body']['content'] ?? NULL, 'une leçon sans bloc n\'a pas de contenu');

		$refill = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => '<p>Contenu reposé.</p>'], get: ['id' => $id]);

		$this->assertEquals(200, $refill['status'], json_encode($refill['body']));
		$this->assertEquals(1, \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id)->count(), 'écrit sur zéro bloc, un bloc posé');

		// Le contenu s'écrit dans un bloc texte, qui accepte une photo entre deux paragraphes, dans le
		// balisage que l'éditeur poste, avec sa légende, et rien d'autre d'une figure : la vidéo est retirée.
		$image = '<div data-type="image" data-w="100" data-h="80" data-url="https://media.exemple.org/photo.jpg" data-color="#ffffff"></div>';
		$video = '<div data-type="video" data-w="100" data-h="80" data-url="https://media.exemple.org/video" data-source="youtube"></div>';

		$figure = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['content'],
			'content' => '<p>Avant l\'image.</p><figure data-interactive="true">'.$image.$video.'<figcaption>Une légende</figcaption></figure><p>Après.</p>',
		], get: ['id' => $id]);

		$this->assertEquals(200, $figure['status'], json_encode($figure['body']));
		$this->assertEquals(
			'<main><p>Avant l\'image.</p><figure interactive="true"><image url="https://media.exemple.org/photo.jpg" color="#ffffff" width="100" height="80"/><figcaption>Une légende</figcaption></figure><p>Après.</p></main>',
			$figure['body']['content'] ?? NULL,
			'la réponse rend la photo à sa place, avec sa légende, sans la vidéo'
		);
		$this->assertEquals($figure['body']['content'], \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id)->first()['content'], 'le bloc texte la porte');

		$read = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);

		$this->assertEquals($figure['body']['content'], $read['body']['content'] ?? NULL, 'la lecture la rend');

		// Une figure qui n'est pas celle de l'éditeur ne porte pas de photo lisible : elle est retirée.
		$figure = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['content'],
			'content' => '<p>Avant l\'image.</p><figure><img src="https://example.org/a.png"/></figure>',
		], get: ['id' => $id]);

		$this->assertEquals(200, $figure['status'], json_encode($figure['body']));
		$this->assertTrue(str_contains($figure['body']['content'] ?? '', 'Avant l\'image.'), 'le texte autour de la figure est gardé');
		$this->assertTrue(str_contains($figure['body']['content'] ?? '', '<figure') === FALSE, 'la figure sans photo est retirée : '.($figure['body']['content'] ?? ''));

		// Une leçon composée de deux blocs se lit bout à bout et refuse un contenu d'un seul tenant.
		\block\BlockLib::create(new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $id,
			'type' => \block\BlockTypeLib::TEXT,
			'content' => '<main><p>Second bloc.</p></main>',
		]));

		$read = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);

		$this->assertTrue(str_contains($read['body']['content'] ?? '', 'Avant l\'image.'), 'le premier bloc est lu');
		$this->assertTrue(str_contains($read['body']['content'] ?? '', 'Second bloc.'), 'le second bloc est lu à sa suite');
		$this->assertEquals(1, substr_count($read['body']['content'] ?? '', '<main>'), 'une seule enveloppe pour les deux blocs');

		$refused = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => '<p>Écrasement.</p>'], get: ['id' => $id]);

		$this->assertEquals(400, $refused['status'], json_encode($refused['body']));
		$this->assertErrorCode('Lesson::content.blocks', $refused, 'leçon composée refusée');
		$this->assertEquals(2, \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id)->count(), 'la composition est gardée');

		$rename = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['name'], 'name' => 'Leçon composée'], get: ['id' => $id]);
		$this->assertEquals(200, $rename['status'], 'le refus ne touche que le contenu : '.json_encode($rename['body']));

		$delete = $this->callApi('DELETE', 'lesson/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals(0, \block\Block::model()->whereOwner(BlockOwnerLib::LESSON)->whereReference($id)->count(), 'les blocs partent avec la leçon');

	}

	/**
	 * Une leçon dont le premier bloc est une vidéo, seule puis suivie d'un texte : l'écriture de
	 * `content` vise le premier bloc de la leçon (`LessonLib::writeContent()`), et seule l'étape
	 * `Lesson::content.blocks` l'empêche d'atteindre un bloc qui n'est pas un texte. Le cas vide
	 * (`content` à `NULL`) y passe aussi, lui qui supprimerait le premier bloc.
	 */
	public function testLessonContentKeepsVideoBlock(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);

		$create = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapter));
		$this->assertEquals(201, $create['status'], json_encode($create['body']));

		$id = $create['body']['id'];

		$empty = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => NULL], get: ['id' => $id]);
		$this->assertEquals(200, $empty['status'], json_encode($empty['body']));

		$url = 'https://vimeo.com/76979871';

		\block\BlockLib::create(new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $id,
			'type' => \block\BlockTypeLib::VIDEO,
			'variant' => \block\BlockTypeLib::FULL,
			'url' => $url,
			'title' => 'Le paillage',
		]));

		$getBlocks = fn(): array => array_values(\block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $id)->makeArray(fn(\block\Block $eBlock) => [$eBlock['type'], $eBlock['url'], $eBlock['title'], $eBlock['content']]));

		$video = [\block\BlockTypeLib::VIDEO, $url, 'Le paillage', NULL];

		$this->assertEquals([$video], $getBlocks(), 'la leçon ne porte qu\'une vidéo');

		$read = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);
		$this->assertEquals(NULL, $read['body']['content'] ?? NULL, 'une leçon sans bloc texte n\'a pas de contenu à lire : '.json_encode($read['body']['content'] ?? NULL));

		$write = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => '<p>Écrit par l\'API.</p>'], get: ['id' => $id]);

		$this->assertEquals(400, $write['status'], 'vidéo seule, contenu posté : '.json_encode($write['body']));
		$this->assertErrorCode('Lesson::content.blocks', $write, 'vidéo seule refusée');
		$this->assertEquals([$video], $getBlocks(), 'la vidéo garde son type, son adresse et sa légende, et ne reçoit pas le texte');

		$erase = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => NULL], get: ['id' => $id]);

		$this->assertEquals(400, $erase['status'], 'vidéo seule, contenu vidé : '.json_encode($erase['body']));
		$this->assertErrorCode('Lesson::content.blocks', $erase, 'vider le contenu ne supprime pas la vidéo');
		$this->assertEquals([$video], $getBlocks(), 'la vidéo est toujours là');

		\block\BlockLib::create(new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => $id,
			'type' => \block\BlockTypeLib::TEXT,
			'content' => '<main><p>Texte sous la vidéo.</p></main>',
		]));

		$text = [\block\BlockTypeLib::TEXT, NULL, NULL, '<main><p>Texte sous la vidéo.</p></main>'];

		$this->assertEquals([$video, $text], $getBlocks(), 'la vidéo reste le premier bloc, le texte la suit');

		$write = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => '<p>Écrit par l\'API.</p>'], get: ['id' => $id]);

		$this->assertEquals(400, $write['status'], 'vidéo puis texte, contenu posté : '.json_encode($write['body']));
		$this->assertErrorCode('Lesson::content.blocks', $write, 'vidéo puis texte refusée');
		$this->assertEquals([$video, $text], $getBlocks(), 'ni la vidéo ni le texte ne changent');

		$erase = $this->callApi('PUT', 'lesson/{id}', body: ['properties' => ['content'], 'content' => NULL], get: ['id' => $id]);

		$this->assertEquals(400, $erase['status'], 'vidéo puis texte, contenu vidé : '.json_encode($erase['body']));
		$this->assertEquals([$video, $text], $getBlocks(), 'rien n\'est supprimé');

		$read = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);

		$this->assertEquals('<main><p>Texte sous la vidéo.</p></main>', $read['body']['content'] ?? NULL, 'la lecture rend le texte seul, sans rien de la vidéo');

	}

	public function testForeignStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);

		$eCourseForeign = $this->createCourseForeign();

		$eChapterForeign = new Chapter(['course' => $eCourseForeign, 'name' => 'Module d\'ailleurs']);
		ChapterLib::create($eChapterForeign);

		$eLessonForeign = new Lesson(['course' => $eCourseForeign, 'chapter' => $eChapterForeign, 'name' => 'Leçon d\'ailleurs', 'minutes' => 5]);
		LessonLib::create($eLessonForeign);

		// Toutes les routes qui prennent un `{id}`, et non les seules lectures : une borne posée sur
		// la lecture et oubliée sur l'écriture laisserait renommer et supprimer les ressources d'un
		// autre groupe. Les deux listes filles en sont, leur `{id}` désignant lui aussi un parent
		// d'ailleurs.
		foreach([
			['GET', 'course/{id}', $eCourseForeign['id'], []],
			['GET', 'course/{id}/usage', $eCourseForeign['id'], []],
			['GET', 'course/{id}/chapterList', $eCourseForeign['id'], []],
			['PUT', 'course/{id}', $eCourseForeign['id'], ['properties' => ['name'], 'name' => 'Formation détournée']],
			['DELETE', 'course/{id}', $eCourseForeign['id'], []],
			['GET', 'chapter/{id}', $eChapterForeign['id'], []],
			['GET', 'chapter/{id}/lessonList', $eChapterForeign['id'], []],
			['PUT', 'chapter/{id}', $eChapterForeign['id'], ['properties' => ['name'], 'name' => 'Module détourné']],
			['DELETE', 'chapter/{id}', $eChapterForeign['id'], []],
			['GET', 'lesson/{id}', $eLessonForeign['id'], []],
			['PUT', 'lesson/{id}', $eLessonForeign['id'], ['properties' => ['name'], 'name' => 'Leçon détournée']],
			['DELETE', 'lesson/{id}', $eLessonForeign['id'], []],
		] as [$method, $route, $id, $body]) {
			$this->assertEquals(404, $this->callApi($method, $route, body: $body, get: ['id' => $id])['status'], 'ressource d\'ailleurs : '.$method.' '.$route);
		}

		// Lu en collection et non en élément : une ligne que la suppression aurait emportée rend un
		// tableau vide, là où un élément vide ferait lever la lecture de `name` avant l'assertion.
		$this->assertEquals([['zzTest formation d\'ailleurs'], ['Module d\'ailleurs'], ['Leçon d\'ailleurs']], [
			Course::model()->select('name')->whereId($eCourseForeign)->getCollection()->getColumn('name'),
			Chapter::model()->select('name')->whereId($eChapterForeign)->getCollection()->getColumn('name'),
			Lesson::model()->select('name')->whereId($eLessonForeign)->getCollection()->getColumn('name'),
		], 'formation, module et leçon d\'ailleurs intacts : ni renommés, ni supprimés');

		$chapterForeign = $this->callApi('POST', 'chapter', body: ['course' => $eCourseForeign['id'], 'name' => 'Module', 'summary' => NULL, 'phase' => NULL]);

		$this->assertEquals(400, $chapterForeign['status']);
		$this->assertErrorCode('Chapter::course.owned', $chapterForeign, 'formation d\'ailleurs refusée');

		$lessonOwn = $this->callApi('POST', 'lesson', body: $this->lessonBody($chapter));

		$this->assertEquals(201, $lessonOwn['status'], 'leçon acceptée dans un module du groupe');
		$this->assertRead('lesson/{id}', $lessonOwn['body']['id'], 'Leçon API');

		$lessonForeign = $this->callApi('POST', 'lesson', body: $this->lessonBody($eChapterForeign['id']));

		$this->assertEquals(400, $lessonForeign['status']);
		$this->assertErrorCode('Lesson::chapter.owned', $lessonForeign, 'module d\'ailleurs refusé');
		$this->assertEquals(1, Lesson::model()->whereChapter($eChapterForeign)->count(), 'rien d\'écrit');

	}

	/**
	 * Le point 3 du plan : un parent absent ou nul est refusé avant l'étape `owned`, un parent
	 * inexistant par elle, chaque fois en 400 nommé, sans notice ni `expects` levé.
	 */
	public function testBodyWithoutParent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);

		$lessonsBefore = Lesson::model()->whereCourse($course)->count();

		$bodies = [
			'absent' => [array_diff_key($this->lessonBody($chapter), ['chapter' => TRUE]), 'Lesson::chapter.missing'],
			'nul' => [$this->lessonBody(NULL), 'Lesson::chapter.check'],
			// Le schéma ne vérifie pas qu'un id existe : c'est l'étape `owned` qui ne le trouve pas.
			'inexistant' => [$this->lessonBody(999999999), 'Lesson::chapter.owned'],
		];

		foreach($bodies as $case => [$body, $code]) {

			$response = $this->callApi('POST', 'lesson', body: $body);

			$this->assertEquals(400, $response['status'], 'leçon, module '.$case);
			$this->assertErrorCode($code, $response, 'leçon, module '.$case);

		}

		$this->assertEquals($lessonsBefore, Lesson::model()->whereCourse($course)->count(), 'aucune leçon écrite');

		$chapters = [
			'absent' => [['name' => 'Module', 'summary' => NULL, 'phase' => NULL], 'Chapter::course.missing'],
			'nul' => [['course' => NULL, 'name' => 'Module', 'summary' => NULL, 'phase' => NULL], 'Chapter::course.check'],
			'inexistant' => [['course' => 999999999, 'name' => 'Module', 'summary' => NULL, 'phase' => NULL], 'Chapter::course.owned'],
		];

		foreach($chapters as $case => [$body, $code]) {

			$response = $this->callApi('POST', 'chapter', body: $body);

			$this->assertEquals(400, $response['status'], 'module, formation '.$case);
			$this->assertErrorCode($code, $response, 'module, formation '.$case);

		}

	}

	/**
	 * Un module inconnu et une durée nulle dans le même corps : `chapter.owned` refuse sans poser la
	 * formation, et la règle de durée qui suit n'a plus rien à lire. Le 400 est le même dans les deux
	 * cas, c'est le silence du moteur qui les sépare : en dev, une notice suffit à faire tomber le
	 * JSON que lit un formulaire ajax.
	 *
	 * Seul le `POST` peut lever, et c'est lui que ce cas fige : la garde ne tire que là où rien n'a
	 * posé la formation avant le build, c'est-à-dire sur la seule création d'API, la modification
	 * apportant sa formation avec l'élément relu. Le `PUT` est ici pour que ce
	 * chemin-là soit tenu silencieux lui aussi, non pour éprouver la garde : il resterait vert si
	 * `chapter` repassait en queue d'une liste.
	 */
	public function testLessonWithoutMinutesOnUnknownChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$lesson = $this->createLesson($this->createChapter($course));

		$body = $this->lessonBody(999999999);
		$body['minutes'] = NULL;

		$calls = [
			'création' => fn(): array => $this->callApi('POST', 'lesson', body: $body),
			'modification' => fn(): array => $this->callApi('PUT', 'lesson/{id}', get: ['id' => $lesson], body: $body)
		];

		foreach($calls as $case => $call) {

			$errors = [];

			set_error_handler(function(int $level, string $message) use(&$errors): bool {
				$errors[] = $message;
				return TRUE;
			});

			try {
				$response = $call();
			} finally {
				restore_error_handler();
			}

			$this->assertEquals(400, $response['status'], 'module inconnu, durée nulle, '.$case);
			$this->assertErrorCode('Lesson::chapter.owned', $response, 'module inconnu, durée nulle, '.$case);
			$this->assertEquals('', implode(' · ', $errors), 'aucune erreur PHP, '.$case);

		}

		$this->callApi('DELETE', 'course/{id}', get: ['id' => $course]);

	}

	/**
	 * Chaque propriété que l'API reçoit, retirée du corps, est refusée en 400 sous
	 * `<Module>::<propriété>.missing` avec un message. Sans entrée dans `AlertUi::getError()`, le
	 * refus partait avec `message: null` : le code suffisait au test, pas à celui qui lit la réponse.
	 * Les listes viennent des Libs, pour qu'une propriété ajoutée à l'API soit couverte sans y penser.
	 */
	public function testMissingPropertyHasMessage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);
		$lesson = $this->createLesson($chapter);

		$eCourse = Course::model()
			->select(Course::getSelection())
			->whereId($course)
			->get();

		$bodies = [
			'Course' => [
				'course', 'course/{id}', $course,
				[
					'name' => 'Formation',
					'summary' => 'Une formation.',
					'theme' => $this->eTheme['id'],
					'level' => $this->eLevel['id'],
				],
				// `type` omis vaut en ligne (`Course_0_1betaApi::convertProperties()`), `testCourse` le vérifie.
				array_diff(CourseLib::getPropertiesCreate('api'), ['type']),
				CourseLib::getPropertiesUpdate('api')($eCourse)
			],
			'Chapter' => [
				'chapter', 'chapter/{id}', $chapter,
				['course' => $course, 'name' => 'Module', 'summary' => NULL, 'phase' => NULL],
				ChapterLib::getPropertiesCreate('api'),
				ChapterLib::getPropertiesUpdate('api')
			],
			'Lesson' => [
				'lesson', 'lesson/{id}', $lesson,
				$this->lessonBody($chapter),
				LessonLib::getPropertiesCreate('api'),
				LessonLib::getPropertiesUpdate('api')
			],
		];

		foreach($bodies as $module => [$routeCreate, $routeUpdate, $id, $body, $propertiesCreate, $propertiesUpdate]) {

			foreach($propertiesCreate as $property) {

				$response = $this->callApi('POST', $routeCreate, body: array_diff_key($body, [$property => TRUE]));

				$this->assertMissing($module.'::'.$property.'.missing', $response, 'création');

			}

			foreach($propertiesUpdate as $property) {

				$response = $this->callApi('PUT', $routeUpdate, body: [
					'properties' => [$property]
				], get: ['id' => $id]);

				$this->assertMissing($module.'::'.$property.'.missing', $response, 'modification');

			}

		}

	}

	private function assertMissing(string $code, array $response, string $case): void {

		$this->assertEquals(400, $response['status'], $code.' en '.$case);
		$this->assertErrorCode($code, $response, $case);

		$message = $response['body'][0]['message'] ?? NULL;

		$this->assertTrue(is_string($message) and $message !== '', $code.' en '.$case.' : message vide ('.json_encode($response['body']).')');

	}

	/**
	 * Le classement nul vu de l'API, dans les trois cas que le lot L1w ouvre ou ferme.
	 *
	 * Ce que ce contrôle prouve est exactement « le classement peut valoir `null` », et non « le
	 * classement peut manquer » : une clé **absente** du corps reste refusée par `.missing`
	 * (`framework/core/Api.php`), et `testMissingPropertyHasMessage()` le fige pour les quatre
	 * propriétés en dérivant sa liste de `getPropertiesCreate('api')`. Ce qui change avec L1w, c'est
	 * que `null` devient une valeur acceptée, là où la colonne la refusait. D'où le nom : un test
	 * appelé « optional » resterait vert le jour où quelqu'un rendrait les quatre clés obligatoires
	 * en valeur, et se lirait comme la preuve du contraire.
	 */
	public function testClassificationNullable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$unclassified = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation au classement nul',
			'summary' => NULL,
			'theme' => NULL,
			'level' => NULL,
		]);

		$this->assertEquals(201, $unclassified['status'], 'créer une formation dont le classement est nul : '.json_encode($unclassified['body']));

		// L'autre moitié du contrat, sans quoi le contrôle ne distinguerait pas « nullable » de
		// « optionnel » : omettre les clés reste refusé, et les trois refus sont nommés.
		$absent = $this->callApi('POST', 'course', body: ['name' => 'zzTest formation sans les clés']);

		$this->assertEquals(400, $absent['status'], 'créer une formation sans les clés du classement : '.json_encode($absent['body']));
		$this->assertEquals(
			['Course::summary.missing', 'Course::theme.missing', 'Course::level.missing'],
			array_column($absent['body'], 'code'),
			'les trois clés omises sont nommées ('.json_encode($absent['body']).')'
		);

		$eCourseUnclassified = $this->getCourse($unclassified['body']['id']);

		$this->assertEquals(NULL, $eCourseUnclassified['summary'], 'le résumé reste vide');
		$this->assertTrue($eCourseUnclassified['theme']->empty(), 'la thématique reste vide');
		$this->assertTrue($eCourseUnclassified['level']->empty(), 'le niveau reste vide');
		$this->assertEquals(Course::DRAFT, $eCourseUnclassified['status'], 'une formation naît en brouillon');

		// Le cas accepté qui donne son sens au précédent : les trois propriétés restent écrites quand
		// le client les porte, « plus exigé » n'étant pas « plus accepté ».
		$eCourseClassified = $this->getCourse($this->createCourse('zzTest formation classée par l\'API'));

		$this->assertEquals($this->eTheme['id'], $eCourseClassified['theme']['id'], 'la thématique postée est écrite');
		$this->assertEquals($this->eLevel['id'], $eCourseClassified['level']['id'], 'le niveau posté est écrit');
		$this->assertTrue($eCourseClassified['summary'] !== NULL, 'le résumé posté est écrit');

		/**
		 * Point 31 du plan : le corps ne commande pas l'ordre de construction. `properties` est
		 * réordonné sur `getPropertiesUpdate()` (`framework/core/Api.php`, `array_intersect`, qui garde
		 * l'ordre de son premier argument), donc `summary` se construit avant `status` et une formation
		 * se publie et se complète d'une seule requête. Si ce contrôle tombe, la lecture est fausse et
		 * le groupe des quatre propriétés devient obligatoire.
		 */
		$response = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation à publier',
			'summary' => NULL,
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
		]);

		$this->assertEquals(201, $response['status'], 'créer une formation dont le résumé est nul : '.json_encode($response['body']));

		$course = $response['body']['id'];

		$this->createLesson($this->createChapter($course));

		$publish = $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['status', 'summary'],
			'status' => Course::PUBLISHED,
			'summary' => 'Le résumé posé dans la requête qui publie.',
		], get: ['id' => $course]);

		$this->assertEquals(200, $publish['status'], 'publier et compléter le résumé d\'une seule requête : '.json_encode($publish['body']));
		$this->assertEquals(Course::PUBLISHED, $this->getCourse($course)['status'], 'la formation est publiée');

		/**
		 * Écart assumé 3, point 30 du plan : dépublier **et** vider le résumé dans la même requête est
		 * refusé, `summary` se construisant avant `status` et lisant l'état encore publié. Le sens de
		 * l'erreur est bon, c'est un refus de trop et jamais un trou : aucun ordre ne laisse publier
		 * sans classement. L'écran ne peut pas produire ce cas, publier et régler y étant deux gestes.
		 */
		$refused = $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['status', 'summary'],
			'status' => Course::DRAFT,
			'summary' => '',
		], get: ['id' => $course]);

		$this->assertEquals(400, $refused['status'], 'dépublier en vidant le résumé : '.json_encode($refused['body']));
		$this->assertErrorCode('Course::summary.classified', $refused, 'dépublier en vidant le résumé');
		$this->assertEquals(Course::PUBLISHED, $this->getCourse($course)['status'], 'la formation reste publiée');

	}

	private function getCourse(int $id): Course {

		return Course::model()
			->select(Course::getSelection())
			->whereId($id)
			->get();

	}

	/**
	 * La forme d'une leçon se lit et ne s'écrit pas : elle suit le contenu, sur la route de la leçon
	 * comme sur la liste du module, qui la calcule sur les blocs chargés pour toute la page. Un PUT
	 * qui la nomme seule est refusé, un corps entier qui la porte la voit ignorée.
	 */
	public function testFormat(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$chapter = $this->createChapter($this->createCourse());

		$read = function(int $id) use($chapter): array {

			$lesson = $this->callApi('GET', 'lesson/{id}', get: ['id' => $id]);
			$list = $this->callApi('GET', 'chapter/{id}/lessonList', get: ['id' => $chapter]);

			return [$lesson['body']['format'] ?? NULL, array_column($list['body']['list'] ?? [], 'format', 'id')[$id] ?? NULL];

		};

		$create = $this->callApi('POST', 'lesson', body: ['content' => NULL] + $this->lessonBody($chapter));

		$this->assertEquals(201, $create['status'], json_encode($create['body']));
		$this->assertEquals(Lesson::EMPTY, $create['body']['format'] ?? NULL, 'la création rend la forme d\'une leçon sans contenu');

		$id = $create['body']['id'];

		$this->assertEquals([Lesson::EMPTY, Lesson::EMPTY], $read($id), 'vide, sur la leçon et dans la liste du module');

		$content = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['content'],
			'content' => '<p>Un texte.</p>',
		], get: ['id' => $id]);

		$this->assertEquals(Lesson::TEXT, $content['body']['format'] ?? NULL, 'la modification rend la forme de son contenu');
		$this->assertEquals([Lesson::TEXT, Lesson::TEXT], $read($id), 'un texte, sur la leçon et dans la liste du module');

		$full = $this->callApi('PUT', 'lesson/{id}', body: $this->lessonBody($chapter) + ['format' => Lesson::VIDEO], get: ['id' => $id]);

		$this->assertEquals(200, $full['status'], json_encode($full['body']));
		$this->assertEquals(Lesson::TEXT, $full['body']['format'] ?? NULL, 'un corps entier qui porte la forme la voit ignorée');

		$steps = $this->callApi('POST', 'block', body: [
			'lesson' => $id,
			'parent' => NULL,
			'type' => \block\BlockTypeLib::STEPS,
			'variant' => \block\BlockTypeLib::NUMBERS,
			'title' => 'Première étape',
			'content' => '<p>Texte de la première étape.</p>',
		]);

		$this->assertEquals(201, $steps['status'], json_encode($steps['body']));
		$this->assertEquals([Lesson::STEPS, Lesson::STEPS], $read($id), 'des étapes, sur la leçon et dans la liste du module');

		$update = $this->callApi('PUT', 'lesson/{id}', body: [
			'properties' => ['format'],
			'format' => Lesson::VIDEO,
		], get: ['id' => $id]);

		$this->assertEquals(400, $update['status']);
		$this->assertErrorCode('property_unknown', $update, 'la forme ne s\'écrit pas seule');

		$this->assertEquals([Lesson::STEPS, Lesson::STEPS], $read($id), 'la forme reste celle du contenu');

	}

	/**
	 * Supprimer une formation par l'API emporte ses inscriptions, comme à l'écran : rien ne s'y
	 * oppose (arbitrage du 2026-09-20, « Supprimable, comme aujourd'hui »).
	 */
	public function testDeleteEnrolled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals(200, $this->callApi('DELETE', 'course/{id}', get: ['id' => $this->createCourse()])['status'], 'sans inscrit, supprimée');

		$course = $this->createCourse();

		EnrollmentLib::create(new Enrollment([
			'user' => $this->getUserKey(),
			'course' => new Course(['id' => $course]),
			'structure' => new \structure\Structure()
		]));

		$delete = $this->callApi('DELETE', 'course/{id}', get: ['id' => $course]);

		$this->assertEquals(200, $delete['status'], 'avec un inscrit, supprimée aussi');
		$this->assertEquals([0, 0], [
			Course::model()->whereId($course)->count(),
			Enrollment::model()->whereCourse(new Course(['id' => $course]))->count()
		], 'formation et inscription emportées');

	}

	/**
	 * Une formation en ligne se publie par l'API comme à l'écran, avec au moins une leçon : vide,
	 * elle est refusée en 400 `Course::status.program`. Publiée, sa dernière leçon et le module qui
	 * la porte se refusent en 412 `accept_delete`, l'API ne lisant que les `accept*`. Le présentiel,
	 * qui se publie vide et se vide, est éprouvé par `PublishProgram.t.php`.
	 */
	public function testDeleteLastLessonPublished(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse('zzTest formation API publiée');

		$publish = fn(string $status) => $this->callApi('PUT', 'course/{id}', body: [
			'properties' => ['status'],
			'status' => $status,
		], get: ['id' => $course]);

		$refused = $publish(Course::PUBLISHED);

		$this->assertEquals(400, $refused['status'], json_encode($refused['body']));
		$this->assertErrorCode('Course::status.program', $refused, 'publiée sans leçon');

		$chapter = $this->createChapter($course);
		$lesson = $this->createLesson($chapter);

		$this->assertEquals(200, $publish(Course::PUBLISHED)['status'], 'publiée avec sa première leçon');

		$this->assertEquals(412, $this->callApi('DELETE', 'lesson/{id}', get: ['id' => $lesson])['status'], 'la dernière leçon d\'une formation en ligne publiée');
		$this->assertEquals(412, $this->callApi('DELETE', 'chapter/{id}', get: ['id' => $chapter])['status'], 'le module qui la porte');

		$this->assertEquals([1, 1], [
			Lesson::model()->whereId($lesson)->count(),
			Chapter::model()->whereId($chapter)->count()
		], 'rien n\'est parti');

		$this->assertEquals(200, $publish(Course::DRAFT)['status'], 'dépubliée');
		$this->assertEquals(200, $this->callApi('DELETE', 'lesson/{id}', get: ['id' => $lesson])['status'], 'en brouillon, la dernière leçon se supprime');

		$this->callApi('DELETE', 'course/{id}', get: ['id' => $course]);

	}

	public function testUsage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		// Un vendredi dont aucune période ne commence un lundi : 30 jours partent d'un jeudi, 90 jours
		// d'un dimanche et 12 mois d'un samedi 1er, donc chaque borne attendue diffère du jour qu'elle
		// prendrait sans le calage sur un lundi.
		$this->setClock('2026-10-23 12:00:00');

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);
		$lessons = [$this->createLesson($chapter), $this->createLesson($chapter), $this->createLesson($this->createChapter($course, 'Second module'))];

		$empty = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course]);

		$this->assertEquals(200, $empty['status'], json_encode($empty['body']));
		$this->assertEquals(['90d', 'week', '2026-07-20', '2026-10-23'], [$empty['body']['period'], $empty['body']['step'], $empty['body']['from'], $empty['body']['to']], '90 jours par semaine par défaut, reculés au lundi qui précède le dimanche 26 juillet');
		$this->assertEquals([14, '2026-07-20'], [count($empty['body']['series']), $empty['body']['series'][0]['date']], 'quatorze semaines entières, vides comprises, la première partant du lundi de la période');
		$this->assertEquals([0, 0, NULL, 0, 0], [$empty['body']['readers'], $empty['body']['readersCompleted'], $empty['body']['completionRate'], $empty['body']['enrolled'], $empty['body']['readersActive']], 'sans lecteur');

		$invalid = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '7d']);

		$this->assertEquals(400, $invalid['status'], json_encode($invalid['body']));
		$this->assertErrorCode('period.check', $invalid, 'une période inconnue');

		$invalidLimit = $this->callApi('GET', 'courseList', get: ['limit' => 501]);

		$this->assertEquals(400, $invalidLimit['status'], json_encode($invalidLimit['body']));
		$this->assertErrorCode('limit.check', $invalidLimit, 'une limite hors bornes');
		$this->assertEquals(
			[$invalidLimit['body'][0]['type'], str_replace('limit', 'period', $invalidLimit['body'][0]['message'])],
			[$invalid['body'][0]['type'], $invalid['body'][0]['message']],
			'une période inconnue est refusée comme un autre argument refusé par son masque'
		);

		$nested = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => ['30d']]);

		$this->assertEquals(400, $nested['status'], json_encode($nested['body']));
		$this->assertErrorCode('period.check', $nested, 'une liste qui contient une période n\'en est pas une');

		$monthly = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '90d', 'step' => 'month']);

		$this->assertEquals(200, $monthly['status'], json_encode($monthly['body']));
		$this->assertEquals(['90d', 'month', '2026-07-20'], [$monthly['body']['period'], $monthly['body']['step'], $monthly['body']['from']], '90 jours par mois, partis du même lundi que par semaine');
		$this->assertEquals(['2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'], array_column($monthly['body']['series'], 'date'), '90 jours par mois, juillet entamé compris');

		$year = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '12m', 'step' => 'month']);

		$this->assertEquals(200, $year['status'], json_encode($year['body']));
		$this->assertEquals(['12m', 'month', '2025-11-03'], [$year['body']['period'], $year['body']['step'], $year['body']['from']], '12 mois avancés au lundi qui suit le samedi 1er novembre');
		$this->assertEquals([
			'2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01',
			'2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'
		], array_column($year['body']['series'], 'date'), 'douze barres au mois, sans treizième barre d\'octobre 2025');

		$stepUnknown = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'step' => 'day']);

		$this->assertEquals(400, $stepUnknown['status'], json_encode($stepUnknown['body']));
		$this->assertErrorCode('step.check', $stepUnknown, 'le pas par jour n\'existe plus');

		$stepRefused = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '30d', 'step' => 'month']);

		$this->assertEquals(400, $stepRefused['status'], json_encode($stepRefused['body']));
		$this->assertErrorCode('step.check', $stepRefused, 'le mois sur 30 jours');
		$this->assertEquals($stepUnknown['body'], $stepRefused['body'], 'un pas que la période n\'admet pas est refusé comme un pas inconnu');

		$cUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereId('!=', \user\UserSetting::$apiUserId)
			->sort(['id' => SORT_ASC])
			->getCollection(0, 3);

		$eCourse = new Course(['id' => $course]);

		// Le premier suit tout, le deuxième une leçon, le troisième une leçon il y a quarante jours.
		$done = [$lessons, [$lessons[0]], [$lessons[1]]];

		$users = iterator_to_array($cUser, FALSE);

		foreach($users as $position => $eUser) {

			$eEnrollment = new Enrollment(['user' => $eUser, 'course' => $eCourse, 'structure' => new \structure\Structure()]);
			EnrollmentLib::create($eEnrollment);

			foreach($done[$position] as $lesson) {
				ProgressLib::done($eEnrollment, new Lesson(['id' => $lesson]));
			}

		}

		$this->backdateProgress($users[2], $course, 40);

		$usage = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '30d']);

		$this->assertEquals(200, $usage['status'], json_encode($usage['body']));
		$this->assertEquals($course, $usage['body']['course']);
		$this->assertEquals(['2026-09-21', '2026-10-23'], [$usage['body']['from'], $usage['body']['to']], '30 jours reculés au lundi qui précède le jeudi 24 septembre');
		$this->assertEquals(['2026-09-21', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'], array_column($usage['body']['series'], 'date'), 'cinq semaines entières');
		$this->assertEquals(3, $usage['body']['readers'], 'inscrits');
		$this->assertEquals([1, 33], [$usage['body']['readersCompleted'], $usage['body']['completionRate']], 'un seul a tout suivi');
		$this->assertEquals(3, $usage['body']['enrolled'], 'inscrits dans les 30 jours');
		$this->assertEquals(3, array_sum(array_column($usage['body']['series'], 'enrolled')), 'la série redit les inscriptions');
		$this->assertEquals(2, $usage['body']['readersActive'], 'le lecteur d\'il y a quarante jours n\'est pas actif');
		$this->assertEquals([
			['lesson' => $lessons[0], 'readersDone' => 2, 'medianSeconds' => NULL, 'comments' => 0, 'quizFirstAttemptRate' => NULL],
			['lesson' => $lessons[1], 'readersDone' => 1, 'medianSeconds' => NULL, 'comments' => 0, 'quizFirstAttemptRate' => NULL],
			['lesson' => $lessons[2], 'readersDone' => 1, 'medianSeconds' => NULL, 'comments' => 0, 'quizFirstAttemptRate' => NULL],
		], $usage['body']['lessons'], 'chaque leçon dans l\'ordre du programme, sur la période');

		$quarter = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $course, 'period' => '90d']);

		$this->assertEquals(200, $quarter['status'], json_encode($quarter['body']));
		$this->assertEquals(['90d', 'week'], [$quarter['body']['period'], $quarter['body']['step']], '90 jours, par semaine');
		$this->assertEquals(3, $quarter['body']['readersActive'], 'sur 90 jours, le lecteur d\'il y a quarante jours est actif');
		$this->assertEquals([2, 2, 1], array_column($quarter['body']['lessons'], 'readersDone'), 'sa leçon compte sur 90 jours');

		$keys = [];

		array_walk_recursive($usage['body'], function($value, $key) use (&$keys) {
			$keys[$key] = TRUE;
		});

		$this->assertFalse(array_key_exists('user', $keys), 'aucun lecteur nommé');

		// Une formation ouverte en mars, dont la première inscription tombe un jeudi.
		$this->setClock('2026-03-10 12:00:00');

		$opened = $this->createCourse();

		$this->setClock('2026-03-12 10:00:00');

		EnrollmentLib::create(new Enrollment(['user' => $users[0], 'course' => new Course(['id' => $opened]), 'structure' => new \structure\Structure()]));

		$this->setClock('2026-10-23 12:00:00');

		$all = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $opened, 'period' => 'all']);

		$this->assertEquals(200, $all['status'], json_encode($all['body']));
		$this->assertEquals(['all', 'week', '2026-03-09'], [$all['body']['period'], $all['body']['step'], $all['body']['from']], 'depuis l\'ouverture, reculé au lundi qui précède la première inscription du jeudi 12 mars');

		// Une formation à dates n'a pas de bilan : ses lecteurs se comptent à chaque date.
		$this->asStructure(self::COMMUNITY_NETWORK, function() {

			$dated = $this->callApi('POST', 'course', body: [
				'name' => 'zzTest formation API en visio',
				'summary' => NULL,
				'theme' => $this->eTheme['id'],
				'level' => $this->eLevel['id'],
				'type' => Course::REMOTE,
			]);

			$this->assertEquals(201, $dated['status'], json_encode($dated['body']));

			$refused = $this->callApi('GET', 'course/{id}/usage', get: ['id' => $dated['body']['id']]);

			$this->assertEquals(412, $refused['status'], json_encode($refused['body']));
			$this->assertErrorCode('accept_usage', $refused, 'une formation à dates');

			$this->callApi('DELETE', 'course/{id}', get: ['id' => $dated['body']['id']]);

		});

	}

	/**
	 * Q-API-1 : la clé d'un groupe de formation ou d'un réseau, et aucune autre. Une structure sans
	 * les formations est refusée au scope, avant toute ressource (`Structure::acceptApiScope()`) :
	 * 403 `scope_not_open`, lecture comme écriture.
	 */
	public function testStructureKey(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals(200, $this->callApi('GET', 'courseList')['status'], 'groupe de formation accepté');

		// La formation existe et appartient au groupe de la clé, et se relit ici en 200 : le refus que
		// reçoivent les clés plus bas porte alors sur la clé, pas sur la ressource.
		$course = $this->createCourse();

		$this->assertRead('course/{id}', $course, 'zzTest formation API');

		$this->asStructure(self::COMMUNITY_NETWORK, function() {
			$this->assertEquals(200, $this->callApi('GET', 'courseList')['status'], 'réseau accepté');
		});

		foreach([self::FARM => 'ferme', self::COMMUNITY_PRODUCER => 'groupe de producteurs'] as $structure => $label) {

			$this->asStructure($structure, function() use ($label, $course) {

				foreach([
					['GET', 'courseList', []],
					['GET', 'themeList', []],
					['GET', 'course/{id}', ['id' => $course]],
					['POST', 'chapter', []],
				] as [$method, $route, $get]) {

					$response = $this->callApi($method, $route, body: ['name' => 'x'], get: $get);

					$this->assertEquals(403, $response['status'], $label.' : '.$method.' '.$route);
					$this->assertErrorCode('scope_not_open', $response, $label.' : '.$method.' '.$route);

				}

			});

		}

	}

	/**
	 * Un groupe qui a éteint ses formations n'en écrit ni n'en lit plus par l'API : sa clé est refusée
	 * au scope, comme celle d'une ferme. Rallumé, tout revient, sans clé neuve : le scope se relit à
	 * chaque appel.
	 */
	public function testLearningOff(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->asStructure(self::COMMUNITY_NETWORK, function() {

			$this->assertEquals(200, $this->callApi('GET', 'courseList')['status'], 'allumé, le réseau liste ses formations');

			$this->setLearning(self::COMMUNITY_NETWORK, FALSE);

			foreach([
				['GET', 'courseList'],
				['POST', 'course'],
			] as [$method, $route]) {

				$response = $this->callApi($method, $route, body: ['name' => 'zzTest formation API']);

				$this->assertEquals(403, $response['status'], 'éteint : '.$method.' '.$route);
				$this->assertErrorCode('scope_not_open', $response, 'éteint : '.$method.' '.$route);

			}

			$this->setLearning(self::COMMUNITY_NETWORK, TRUE);

			$this->assertEquals(200, $this->callApi('GET', 'courseList')['status'], 'rallumé, la liste revient');

		});

	}

	/**
	 * Le décor bascule la colonne par le Lib, sans `build()` : la valeur est celle que le test veut,
	 * et le refus d'éteindre se juge dans `CommunityFeature.t.php`. Les fonctionnalités d'une structure
	 * sont gardées en statique de processus, vidée ici pour que l'appel suivant relise la colonne.
	 */
	private function setLearning(int $structure, bool $value): void {

		$eFeature = \structure\FeatureLib::readByStructure(new \structure\Structure(['id' => $structure]));
		$eFeature['hasCommunityLearning'] = $value;

		\structure\FeatureLib::update($eFeature, ['hasCommunityLearning']);

		\structure\FeatureLib::forgetAll();

	}

}
?>
