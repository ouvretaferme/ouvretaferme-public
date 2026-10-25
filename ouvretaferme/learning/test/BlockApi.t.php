<?php
namespace learning;

/**
 * Les blocs d'une leçon par l'API (lot T1-api-blocs), de bout en bout par `\ApiRoute::run()`, avec
 * la clé du réseau du décor en production simulée (`LearningSetting::$active` à faux) : c'est le
 * réseau des tutoriels qui écrit ses leçons ainsi, et `STRUCTURE_BETA` lui ouvre les formations en
 * dev comme en production.
 *
 * Tout s'écrit dans la base commune, où vivent les formations, les blocs et les médias : la
 * transaction ouverte sur `Course::model()` par `\dev\ApiTest` les couvre. Les fichiers d'image
 * déposés restent sur le disque de dev, comme ceux de `ProductVignetteApiAi`.
 *
 * Chaque refus est précédé du cas accepté qui lui correspond : un 404 sur le bloc d'une autre
 * structure ne prouve la borne que si la même route rend les blocs de la clé.
 *
 * Lancement : dev/test package=learning file=BlockApi
 */
class BlockApiTest extends \dev\ApiTest {

	/** Réseau du décor commun, dans `LearningSetting::STRUCTURE_BETA` en dev */
	const STRUCTURE = \structure\FixtureAiLib::G3;

	/** Groupe de formation du décor commun, qui a un administrateur : l'autre structure des ids croisés */
	const COMMUNITY_EDUCATION = \structure\FixtureAiLib::G2;

	/** Compte du décor commun sans structure, qui administre le réseau le temps du test (cf `ApiTest`) */
	const EMAIL_KEY = 'decor.sans.structure@example.org';

	private Theme $eTheme;
	private Level $eLevel;

	private bool $activeInitial;

	public function init(): void {

		parent::init();

		$this->eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$this->eLevel = LevelLib::getAll()->first() ?? new Level();

	}

	protected function getTransactionModel(): \ModuleModel {
		return Course::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->activeInitial = LearningSetting::$active;
		LearningSetting::$active = FALSE;

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;

		parent::tearDown();

	}

	/**
	 * Une structure du décor absente ou d'un autre type rendrait les refus verts sans avoir refusé ce
	 * qu'ils nomment : chacune est relue et comparée au type attendu. Le réseau n'a pas
	 * d'administrateur, et une clé ne s'obtient qu'au nom d'un administrateur : le compte du décor sans
	 * structure l'administre le temps du test.
	 */
	private function assertFixtures(): bool {

		$eStructureNetwork = \structure\StructureLib::getById(self::STRUCTURE);
		$eStructureEducation = \structure\StructureLib::getById(self::COMMUNITY_EDUCATION);
		$eUserKey = \user\UserLib::getByEmail(self::EMAIL_KEY);

		if($this->assertDecorPresent(
			$eStructureNetwork->notEmpty() and $eStructureNetwork->isNetwork() and
			$eStructureEducation->notEmpty() and $eStructureEducation->isEducation() and
			$eUserKey->notEmpty() and
			$this->eTheme->notEmpty() and $this->eLevel->notEmpty(),
			'le réseau '.self::STRUCTURE.' et le groupe de formation '.self::COMMUNITY_EDUCATION.' du décor, le compte '.self::EMAIL_KEY.', une thématique et un niveau'
		) === FALSE) {
			return FALSE;
		}

		$this->joinAdmin($eStructureNetwork, $eUserKey);

		return TRUE;

	}

	private function joinAdmin(\structure\Structure $eStructure, \user\User $eUser): void {

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

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion de '.self::EMAIL_KEY.' au réseau '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

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

	/** Une formation, un module et une leçon de la structure de la clé, écrits par l'API. */
	private function createLesson(string $name = 'Leçon API'): int {

		$course = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation des blocs',
			'summary' => 'Une formation écrite par l\'API.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
		]);

		if($course['status'] !== 201) {
			throw new \Exception('création de la formation refusée : '.json_encode($course['body']));
		}

		$chapter = $this->callApi('POST', 'chapter', body: [
			'course' => $course['body']['id'],
			'name' => 'Module API',
			'summary' => NULL,
			'phase' => NULL,
		]);

		if($chapter['status'] !== 201) {
			throw new \Exception('création du module refusée : '.json_encode($chapter['body']));
		}

		$lesson = $this->callApi('POST', 'lesson', body: [
			'chapter' => $chapter['body']['id'],
			'name' => $name,
			'minutes' => 12,
			'content' => '<p>Introduction de la leçon.</p>',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => NULL,
		]);

		if($lesson['status'] !== 201) {
			throw new \Exception('création de la leçon refusée : '.json_encode($lesson['body']));
		}

		return $lesson['body']['id'];

	}

	/** Un parent de premier niveau, qui naît avec son premier enfant. */
	private function createParent(int $lesson, string $type, ?string $variant = NULL, string $title = 'Première'): array {

		$body = [
			'lesson' => $lesson,
			'parent' => NULL,
			'type' => $type,
			'title' => $title,
			'content' => '<p>Texte de '.$title.'.</p>',
		];

		if($variant !== NULL) {
			$body['variant'] = $variant;
		}

		return $this->callApi('POST', 'block', body: $body);

	}

	private function createItem(int $lesson, int $parent, string $title, array $settings = []): array {

		return $this->callApi('POST', 'block', body: [
			'lesson' => $lesson,
			'parent' => $parent,
			'type' => \block\BlockTypeLib::ITEM,
			'title' => $title,
			'content' => '<p>Texte de '.$title.'.</p>',
			'settings' => $settings,
		]);

	}

	/** Les blocs de la leçon, relus par la route de liste, indexés par id. */
	private function getBlocks(int $lesson): array {

		$response = $this->callApi('GET', 'lesson/{id}/blocksList', get: ['id' => $lesson]);

		if($response['status'] !== 200) {
			throw new \Exception('liste des blocs refusée : '.json_encode($response['body']));
		}

		return array_column($response['body']['list'], NULL, 'id');

	}

	/** Une vraie image, produite par le même Imagick que celui du pipeline. */
	private function getImage(string $color = '#4a7c2f'): string {

		$image = new \Imagick();
		$image->newImage(600, 400, new \ImagickPixel($color));
		$image->setImageFormat('jpeg');

		$content = base64_encode($image->getImageBlob());

		$image->clear();

		return $content;

	}

	private function getMedia(int $block): ?string {
		return \block\Block::model()->select('media')->whereId($block)->get()['media'];
	}

	private function getMediaStatus(string $hash): ?string {

		$eMedia = \media\Media::model()
			->select('status')
			->whereHash(substr($hash, 0, 20))
			->whereType('block-item')
			->get();

		return $eMedia->empty() ? NULL : $eMedia['status'];

	}

	/**
	 * Aucune route ne date une leçon dans le passé : la seule façon de voir avancer `updatedAt` dans
	 * la seconde où le test tourne est de la réécrire en base, comme décor et non comme écriture du
	 * produit.
	 */
	private function backdateLesson(int $lesson): string {

		Lesson::model()
			->whereId($lesson)
			->update(['updatedAt' => new \Sql('NOW() - INTERVAL 1 DAY')]);

		return Lesson::model()->select('updatedAt')->whereId($lesson)->get()['updatedAt'];

	}

	private function getLessonUpdatedAt(int $lesson): string {
		return Lesson::model()->select('updatedAt')->whereId($lesson)->get()['updatedAt'];
	}

	private function assertErrorCode(string $code, array $response, string $message): void {
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $message.' ('.json_encode($response['body']).')');
	}

	/**
	 * Des étapes naissent avec leur première, en reçoivent une autre, qui se modifie, et le retrait de
	 * la dernière emporte le parent. Une vidéo s'écrit à côté, et une seule. Une étape porte son instant
	 * dans la vidéo, posté en secondes ou en `m:ss`, relu en secondes, borné à une journée. Un `media`
	 * posté avec le reste du corps n'est jamais écrit.
	 */
	public function testSteps(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();

		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS);

		$this->assertEquals(201, $steps['status'], 'des étapes naissent : '.json_encode($steps['body']));

		$stepsId = $steps['body']['id'];

		$blocks = $this->getBlocks($lesson);

		$this->assertEquals(3, count($blocks), 'le texte de la leçon, les étapes et leur première');
		$this->assertEquals(NULL, $blocks[$stepsId]['title'], 'le titre posté va à la première étape');

		$children = array_values(array_filter($blocks, fn(array $block) => $block['parent'] === $stepsId));

		$this->assertEquals(1, count($children), 'une étape sous le parent');
		$this->assertEquals('Première', $children[0]['title'] ?? NULL, 'la première étape porte le titre posté');
		$this->assertEquals($lesson, $children[0]['lesson'] ?? NULL, 'l\'étape nomme sa leçon');

		$second = $this->createItem($lesson, $stepsId, 'Deuxième');

		$this->assertEquals(201, $second['status'], 'une deuxième étape : '.json_encode($second['body']));
		$this->assertEquals(1, $second['body']['position'] ?? NULL, 'elle se range après la première');

		$secondId = $second['body']['id'];

		$update = $this->callApi('PUT', 'block/{id}', get: ['id' => $secondId], body: [
			'title' => 'Deuxième relue',
			'content' => '<p>Texte relu.</p>',
			'settings' => ['at' => '1:23'],
			'media' => 'aaaaaaaaaaaaaaaaaaajaaaaaaa001',
		]);

		$this->assertEquals(200, $update['status'], 'l\'étape se modifie : '.json_encode($update['body']));

		$read = $this->callApi('GET', 'block/{id}', get: ['id' => $secondId])['body'];

		$this->assertEquals('Deuxième relue', $read['title'] ?? NULL, 'le titre relu');
		$this->assertEquals(['at' => 83], $read['settings'] ?? NULL, 'l\'instant écrit 1:23 se relit en secondes');
		$this->assertEquals(NULL, $this->getMedia($secondId), 'un media posté avec le corps n\'est pas écrit');

		$video = $this->callApi('POST', 'block', body: [
			'lesson' => $lesson,
			'parent' => NULL,
			'type' => \block\BlockTypeLib::VIDEO,
			'variant' => \block\BlockTypeLib::FULL,
			'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			'title' => NULL,
			'content' => NULL,
		]);

		$this->assertEquals(201, $video['status'], 'une vidéo : '.json_encode($video['body']));

		$videoSecond = $this->callApi('POST', 'block', body: [
			'lesson' => $lesson,
			'parent' => NULL,
			'type' => \block\BlockTypeLib::VIDEO,
			'variant' => \block\BlockTypeLib::FULL,
			'url' => 'https://vimeo.com/76979871',
			'title' => NULL,
			'content' => NULL,
		]);

		$this->assertEquals(400, $videoSecond['status'], 'une seconde vidéo');
		$this->assertErrorCode('Block::url.video', $videoSecond, 'une leçon ne porte qu\'une vidéo');

		$third = $this->createItem($lesson, $stepsId, 'Troisième', ['at' => 125]);

		$this->assertEquals(201, $third['status'], 'une étape créée avec son instant : '.json_encode($third['body']));
		$this->assertEquals(['at' => 125], $third['body']['settings'] ?? NULL, 'l\'instant en secondes');

		$late = $this->createItem($lesson, $stepsId, 'Trop tard', ['at' => \block\BlockSetting::STEP_AT_MAX + 1]);

		$this->assertEquals(400, $late['status'], 'un instant au-delà d\'une journée');
		$this->assertErrorCode('Block::settings.at', $late, 'un instant au-delà d\'une journée');

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}', get: ['id' => $third['body']['id']])['status'], 'la troisième étape se retire');

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}', get: ['id' => $secondId])['status'], 'la deuxième étape se retire');
		$this->assertTrue(isset($this->getBlocks($lesson)[$stepsId]), 'le parent reste avec sa première');

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}', get: ['id' => $children[0]['id']])['status'], 'la dernière étape se retire');
		$this->assertFalse(isset($this->getBlocks($lesson)[$stepsId]), 'la dernière étape emporte le parent');

	}

	/**
	 * Le moteur juge comme à l'écran : un type hors de l'usage au premier niveau, un enfant d'un
	 * autre type que celui du parent, l'enfant de trop. La leçon et le parent se cherchent dans le
	 * bornage de la clé.
	 */
	public function testRefusals(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();
		$lessonOther = $this->createLesson('Autre leçon');

		$item = $this->callApi('POST', 'block', body: [
			'lesson' => $lesson,
			'parent' => NULL,
			'type' => \block\BlockTypeLib::ITEM,
			'title' => 'Étape sans parent',
			'content' => NULL,
			'settings' => [],
		]);

		$this->assertEquals(400, $item['status'], 'une étape au premier niveau');
		$this->assertErrorCode('Block::type.allowed', $item, 'une étape au premier niveau');

		$figure = $this->createParent($lesson, \block\BlockTypeLib::FIGURE, title: '1');

		$this->assertEquals(201, $figure['status'], 'des chiffres clés : '.json_encode($figure['body']));

		$text = $this->callApi('POST', 'block', body: [
			'lesson' => $lesson,
			'parent' => $figure['body']['id'],
			'type' => \block\BlockTypeLib::TEXT,
			'content' => '<p>Un texte parmi les chiffres.</p>',
		]);

		$this->assertEquals(400, $text['status'], 'un texte sous un parent');
		$this->assertErrorCode('Block::type.allowed', $text, 'un texte sous un parent');

		foreach(['2', '3', '4'] as $title) {
			$this->assertEquals(201, $this->createItem($lesson, $figure['body']['id'], $title)['status'], 'le chiffre '.$title);
		}

		$full = $this->createItem($lesson, $figure['body']['id'], '5');

		$this->assertEquals(400, $full['status'], 'le cinquième chiffre');
		$this->assertErrorCode('Block::type.full', $full, 'le cinquième chiffre');

		$unknown = $this->createParent(999999999, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS);

		$this->assertEquals(404, $unknown['status'], 'une leçon inconnue');
		$this->assertErrorCode('unknown_lesson', $unknown, 'une leçon inconnue');

		$parentOther = $this->createItem($lessonOther, $figure['body']['id'], 'Égaré');

		$this->assertEquals(404, $parentOther['status'], 'le parent d\'une autre leçon');
		$this->assertErrorCode('unknown_parent', $parentOther, 'le parent d\'une autre leçon');

		$parentUnknown = $this->createItem($lesson, 999999999, 'Égaré');

		$this->assertEquals(404, $parentUnknown['status'], 'un parent inconnu');
		$this->assertErrorCode('unknown_parent', $parentUnknown, 'un parent inconnu');

		$this->assertEquals(6, count($this->getBlocks($lesson)), 'aucun refus n\'a écrit : le texte, le parent et ses quatre chiffres');

		// Un bloc dont la leçon a disparu : aucun chemin du produit ne le laisse, il s'écrit donc hors
		// de `BlockLib::create()`, qui daterait une leçon absente, et hors de `build()`, qui n'a rien à
		// juger d'un état que seul un décor fabrique.
		$eBlockOrphan = new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'reference' => 999999999,
			'parent' => new \block\Block(),
			'type' => \block\BlockTypeLib::TEXT,
			'content' => '<p>Orphelin.</p>',
			'position' => 0,
		]);

		\block\Block::model()->insert($eBlockOrphan);

		$this->assertEquals(404, $this->callApi('GET', 'block/{id}', get: ['id' => $eBlockOrphan['id']])['status'], 'un bloc sans leçon est hors d\'atteinte');

		$media = $this->callApi('PUT', 'block/{id}/media', get: ['id' => $figure['body']['id']], body: ['content' => $this->getImage()]);

		$this->assertEquals(412, $media['status'], 'une image sur un parent');
		$this->assertErrorCode('accept_media', $media, 'une image sur un parent');

		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$itemSteps = $this->createItem($lesson, $steps, 'Sans image')['body']['id'];

		$missing = $this->callApi('PUT', 'block/{id}/media', get: ['id' => $itemSteps], body: ['autre' => 'chose']);

		$this->assertEquals(400, $missing['status'], 'une image sans contenu');
		$this->assertErrorCode('missing_content', $missing, 'une image sans contenu');

	}

	/**
	 * Toute leçon que la clé atteint s'écrit : `validateStructure()` refuse avant elle la structure
	 * fermée aux formations, seul état où `Lesson::acceptUpdate()` dirait non. Le refus de la route se
	 * prouve donc sur un bloc lu hors de la clé, celui d'une leçon de groupe quand les formations ne
	 * sont pas ouvertes à tous.
	 */
	public function testAcceptUpdate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eLesson = LessonLib::getById($this->createLesson());

		$eBlock = new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'type' => \block\BlockTypeLib::ITEM,
			'eOwner' => $eLesson,
		]);

		$validate = new \ReflectionMethod(Block_0_1betaApi::class, 'validateMedia');

		$accepted = TRUE;

		try {
			$validate->invoke(NULL, $eBlock);
		} catch(\ApiException) {
			$accepted = FALSE;
		}

		$this->assertTrue($accepted, 'une étape d\'une leçon du réseau ouvert accepte une image');

		$eLesson['course']['community'] = \structure\StructureLib::getById(self::COMMUNITY_EDUCATION);

		try {
			$validate->invoke(NULL, $eBlock);
			$this->assertFail('une leçon fermée aux formations accepte une image');
		} catch(\ApiException $e) {
			$this->assertEquals(412, $e->getStatusCode(), 'une leçon fermée aux formations');
			$this->assertEquals('accept_update', $e->getCode(), 'une leçon fermée aux formations');
		}

	}

	/**
	 * Deux structures, chacune avec ses blocs : chaque clé lit, écrit et retire les siens, et rien de
	 * l'autre. Les formations sont ouvertes à tous le temps de la méthode, le groupe de formation
	 * n'étant pas de `STRUCTURE_BETA`.
	 */
	public function testForeignStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$lesson = $this->createLesson();
		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$item = $this->createItem($lesson, $steps, 'Du réseau')['body']['id'];

		$lessonOther = 0;
		$stepsOther = 0;
		$itemOther = 0;

		$this->asStructure(self::COMMUNITY_EDUCATION, function() use(&$lessonOther, &$stepsOther, &$itemOther) {

			$lessonOther = $this->createLesson();
			$stepsOther = $this->createParent($lessonOther, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
			$itemOther = $this->createItem($lessonOther, $stepsOther, 'Du groupe')['body']['id'];

		});

		$cases = [
			[self::STRUCTURE, $lesson, $steps, $item, $lessonOther, $stepsOther, $itemOther],
			[self::COMMUNITY_EDUCATION, $lessonOther, $stepsOther, $itemOther, $lesson, $steps, $item],
		];

		foreach($cases as [$structure, $lessonOwn, $stepsOwn, $itemOwn, $lessonForeign, $stepsForeign, $itemForeign]) {

			$this->asStructure($structure, function() use($structure, $lessonOwn, $stepsOwn, $itemOwn, $lessonForeign, $stepsForeign, $itemForeign) {

				$this->assertEquals(200, $this->callApi('GET', 'lesson/{id}/blocksList', get: ['id' => $lessonOwn])['status'], $structure.' liste les blocs de sa leçon');
				$this->assertEquals(404, $this->callApi('GET', 'lesson/{id}/blocksList', get: ['id' => $lessonForeign])['status'], $structure.' ne liste pas ceux d\'une autre');

				$this->assertEquals(200, $this->callApi('GET', 'block/{id}', get: ['id' => $itemOwn])['status'], $structure.' lit son bloc');
				$this->assertEquals(404, $this->callApi('GET', 'block/{id}', get: ['id' => $itemForeign])['status'], $structure.' ne lit pas celui d\'une autre');

				$this->assertEquals(200, $this->callApi('PUT', 'block/{id}', get: ['id' => $itemOwn], body: ['title' => 'Relu', 'content' => NULL, 'settings' => []])['status'], $structure.' modifie son bloc');
				$this->assertEquals(404, $this->callApi('PUT', 'block/{id}', get: ['id' => $itemForeign], body: ['title' => 'Détourné', 'content' => NULL, 'settings' => []])['status'], $structure.' ne modifie pas celui d\'une autre');

				$this->assertEquals(200, $this->callApi('PUT', 'block/{id}/media', get: ['id' => $itemOwn], body: ['content' => $this->getImage()])['status'], $structure.' pose l\'image de son bloc');
				$this->assertEquals(404, $this->callApi('PUT', 'block/{id}/media', get: ['id' => $itemForeign], body: ['content' => $this->getImage()])['status'], $structure.' ne pose pas celle d\'une autre');
				$this->assertEquals(404, $this->callApi('DELETE', 'block/{id}/media', get: ['id' => $itemForeign])['status'], $structure.' ne retire pas celle d\'une autre');

				$this->assertEquals(201, $this->createItem($lessonOwn, $stepsOwn, 'Ajout')['status'], $structure.' ajoute à sa leçon');

				$intoForeign = $this->createItem($lessonForeign, $stepsForeign, 'Intrus');

				$this->assertEquals(404, $intoForeign['status'], $structure.' n\'ajoute pas à la leçon d\'une autre');
				$this->assertErrorCode('unknown_lesson', $intoForeign, $structure.' n\'ajoute pas à la leçon d\'une autre');

				$parentForeign = $this->createItem($lessonOwn, $stepsForeign, 'Intrus');

				$this->assertEquals(404, $parentForeign['status'], $structure.' n\'ajoute pas sous le parent d\'une autre');
				$this->assertErrorCode('unknown_parent', $parentForeign, $structure.' n\'ajoute pas sous le parent d\'une autre');

				$this->assertEquals(404, $this->callApi('DELETE', 'block/{id}', get: ['id' => $itemForeign])['status'], $structure.' ne retire pas le bloc d\'une autre');

			});

		}

		$this->assertTrue(\block\Block::model()->whereId('IN', [$item, $itemOther])->count() === 2, 'les deux blocs sont toujours là');
		$this->assertEquals(3, \block\Block::model()->whereParent($steps)->count(), 'les étapes du réseau : la première, la sienne, son ajout, et rien de l\'intrus');

	}

	/**
	 * L'image d'une étape se pose, se remplace et se retire par sa route, et date la leçon à chaque
	 * fois, comme toute écriture de bloc.
	 */
	public function testMedia(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();
		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$item = $this->createItem($lesson, $steps, 'Illustrée')['body']['id'];

		$before = $this->backdateLesson($lesson);

		$put = $this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage()]);

		$this->assertEquals(200, $put['status'], 'l\'image se pose : '.json_encode($put['body']));
		$this->assertTrue(str_starts_with((string)($put['body']['media'] ?? ''), 'http'), 'la réponse rend l\'adresse de l\'image');

		$hash = $this->getMedia($item);

		$this->assertTrue($hash !== NULL, 'le bloc porte l\'image');
		$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($hash), 'le média est actif, au type block-item');
		$this->assertTrue($this->getLessonUpdatedAt($lesson) > $before, 'la pose date la leçon');

		$this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage('#c03020')]);

		$this->assertEquals(substr($hash, 0, 20), substr($this->getMedia($item), 0, 20), 'le remplacement garde le hash');

		$before = $this->backdateLesson($lesson);

		$delete = $this->callApi('DELETE', 'block/{id}/media', get: ['id' => $item]);

		$this->assertEquals(200, $delete['status'], 'l\'image se retire');
		$this->assertTrue(array_key_exists('media', $delete['body']) and $delete['body']['media'] === NULL, 'la réponse ne rend plus d\'image');
		$this->assertEquals(NULL, $this->getMedia($item), 'le bloc ne porte plus l\'image');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'le média est retiré');
		$this->assertTrue($this->getLessonUpdatedAt($lesson) > $before, 'le retrait date la leçon');

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}/media', get: ['id' => $item])['status'], 'retirer une image absente ne change rien');

	}

	/**
	 * Les repères de la capture d'une étape s'écrivent par une modification du seul réglage, qui le
	 * remplace en entier : l'instant renvoyé reste, et les repères se relisent tels qu'ils sont
	 * reconstruits. Une forme fausse, une borne franchie et le repère de trop sont refusés sous leur
	 * nom. Une capture remplacée garde les repères, une capture retirée les emporte, l'instant restant.
	 */
	public function testMarks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();
		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$item = $this->createItem($lesson, $steps, 'Repérée', ['at' => 42])['body']['id'];

		$this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage()]);

		$marks = [
			['type' => 'box', 'x' => 10, 'y' => '20.5', 'w' => 30, 'h' => 15],
			['type' => 'ring', 'x' => 72.25, 'y' => 40],
		];

		$put = $this->callApi('PUT', 'block/{id}', get: ['id' => $item], body: ['properties' => ['settings'], 'settings' => ['at' => 42, 'marks' => $marks]]);

		$this->assertEquals(200, $put['status'], 'les repères se posent : '.json_encode($put['body']));

		$expected = [
			'at' => 42,
			'marks' => [
				['h' => 15.0, 'type' => 'box', 'w' => 30.0, 'x' => 10.0, 'y' => 20.5],
				['type' => 'ring', 'x' => 72.25, 'y' => 40.0],
			],
		];

		// La colonne json rend ses clés dans l'ordre que MySQL choisit, et non dans celui de l'écriture :
		// chaque repère se relit trié.
		$read = function() use($item): array {

			$body = $this->callApi('GET', 'block/{id}', get: ['id' => $item])['body'];

			foreach($body['settings']['marks'] ?? [] as $position => $mark) {
				ksort($mark);
				$body['settings']['marks'][$position] = $mark;
			}

			return $body;

		};

		$this->assertEquals($expected, $read()['settings'] ?? NULL, 'les repères se relisent à côté de l\'instant');
		$this->assertEquals('Repérée', $read()['title'] ?? NULL, 'le titre n\'est pas touché par une modification du réglage seul');

		$refusals = [
			'Block::settings.format' => [['type' => 'arrow', 'x' => 1, 'y' => 1]],
			'Block::settings.mark' => [['type' => 'box', 'x' => 90, 'y' => 1, 'w' => 20, 'h' => 5]],
			'Block::settings.marks' => array_fill(0, \block\BlockSetting::STEP_MARKS_MAX + 1, ['type' => 'ring', 'x' => 1, 'y' => 1]),
		];

		foreach($refusals as $code => $refused) {

			$response = $this->callApi('PUT', 'block/{id}', get: ['id' => $item], body: ['properties' => ['settings'], 'settings' => ['at' => 42, 'marks' => $refused]]);

			$this->assertEquals(400, $response['status'], $code);
			$this->assertErrorCode($code, $response, $code);

		}

		$this->assertEquals($expected, $read()['settings'] ?? NULL, 'un refus n\'écrit rien');

		$this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage('#c03020')]);

		$this->assertEquals($expected, $read()['settings'] ?? NULL, 'une capture remplacée garde les repères');

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}/media', get: ['id' => $item])['status'], 'la capture se retire');
		$this->assertEquals(['at' => 42], $read()['settings'] ?? NULL, 'une capture retirée emporte les repères, l\'instant reste');

	}

	/**
	 * Une copie prend son image sous un hash neuf : une image posée ensuite sur la copie, qui réutilise
	 * son hash, laisse l'original intact. Vaut pour la copie d'un bloc et pour celle d'une leçon.
	 */
	public function testDuplicateKeepsOriginal(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();
		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$item = $this->createItem($lesson, $steps, 'Original')['body']['id'];

		$this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage()]);

		$hash = $this->getMedia($item);

		if($this->assertDecorPresent($hash !== NULL, 'l\'image de l\'étape originale') === FALSE) {
			return;
		}

		\user\ConnectionLib::setOnline($this->getAdmin());

		$eBlockCopy = \block\BlockLib::duplicate(\block\BlockLib::getById($steps));

		$itemCopy = \block\Block::model()
			->select('id')
			->whereParent($eBlockCopy)
			->whereTitle('Original')
			->get()['id'];

		$lessonCopy = $this->createLesson('Leçon copiée');

		\block\BlockLib::duplicateByOwnerAndReference(BlockOwnerLib::LESSON, $lesson, $lessonCopy);

		$itemLessonCopy = \block\Block::model()
			->select('id')
			->whereReference($lessonCopy)
			->whereMedia('!=', NULL)
			->get()['id'];

		foreach(['du bloc' => $itemCopy, 'de la leçon' => $itemLessonCopy] as $case => $copy) {

			$hashCopy = $this->getMedia($copy);

			$this->assertTrue($hashCopy !== NULL and substr($hashCopy, 0, 20) !== substr($hash, 0, 20), 'la copie '.$case.' a son propre hash');
			$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($hashCopy), 'le média de la copie '.$case.' est actif');

			$this->assertEquals(200, $this->callApi('PUT', 'block/{id}/media', get: ['id' => $copy], body: ['content' => $this->getImage('#2040c0')])['status'], 'une image sur la copie '.$case);
			$this->assertEquals($hash, $this->getMedia($item), 'l\'original garde son image après la copie '.$case);
			$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($hash), 'le média de l\'original reste actif après la copie '.$case);

		}

	}

	/**
	 * Le retrait d'un parent emporte les images de ses étapes, et celui d'une leçon les images de tous
	 * ses blocs (`LessonLib::deleteWithCourse()`).
	 */
	public function testRemoveTakesMedia(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$lesson = $this->createLesson();
		$steps = $this->createParent($lesson, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];

		$hashes = [];

		foreach(['Une', 'Deux'] as $title) {

			$item = $this->createItem($lesson, $steps, $title)['body']['id'];

			$this->callApi('PUT', 'block/{id}/media', get: ['id' => $item], body: ['content' => $this->getImage()]);

			$hashes[] = $this->getMedia($item);

		}

		if($this->assertDecorPresent(count(array_filter($hashes)) === 2, 'les images des deux étapes') === FALSE) {
			return;
		}

		$this->assertEquals(200, $this->callApi('DELETE', 'block/{id}', get: ['id' => $steps])['status'], 'le parent se retire');

		foreach($hashes as $position => $hash) {
			$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'l\'image de l\'étape '.$position.' part avec le parent');
		}

		$lessonRemoved = $this->createLesson('Leçon retirée');
		$stepsRemoved = $this->createParent($lessonRemoved, \block\BlockTypeLib::STEPS, \block\BlockTypeLib::NUMBERS)['body']['id'];
		$itemRemoved = $this->createItem($lessonRemoved, $stepsRemoved, 'Dans la leçon')['body']['id'];

		$this->callApi('PUT', 'block/{id}/media', get: ['id' => $itemRemoved], body: ['content' => $this->getImage()]);

		$hash = $this->getMedia($itemRemoved);

		if($this->assertDecorPresent($hash !== NULL, 'l\'image de l\'étape de la leçon à retirer') === FALSE) {
			return;
		}

		$this->assertEquals(200, $this->callApi('DELETE', 'lesson/{id}', get: ['id' => $lessonRemoved])['status'], 'la leçon se retire');
		$this->assertEquals(0, \block\Block::model()->whereReference($lessonRemoved)->whereOwner(BlockOwnerLib::LESSON)->count(), 'ses blocs partent avec elle');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'l\'image de son étape part avec elle');

	}

}
?>
