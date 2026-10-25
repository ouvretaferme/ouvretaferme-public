<?php
namespace learning;

/**
 * La FAQ d'une structure par l'API (lot T5-faq-donnees), de bout en bout par `\ApiRoute::run()`,
 * avec la clé du réseau du décor en production simulée (`LearningSetting::$active` à faux), comme
 * `PhaseApiTest` : une question appartient à la structure de la clé, et renvoie au plus à un module
 * d'une de ses formations.
 *
 * Tout s'écrit dans la base commune : la transaction ouverte sur `Course::model()` par
 * `\dev\ApiTest` couvre les formations, les modules et les questions.
 *
 * Chaque refus est précédé du cas accepté qui lui correspond : un 404 sur la question d'une autre
 * structure ne prouve la borne que si la même route rend les questions de la clé.
 *
 * Lancement : dev/test package=learning file=FaqApi
 */
class FaqApiTest extends \dev\ApiTest {

	/** Réseau du décor commun, dans `LearningSetting::STRUCTURE_BETA` en dev */
	const STRUCTURE = \structure\FixtureAiLib::G3;

	/** Groupe de formation du décor commun, hors `STRUCTURE_BETA` : l'autre structure des ids croisés */
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
		\api\ApiSetting::$devScopes = NULL;

		parent::tearDown();

	}

	/**
	 * Une structure du décor absente ou d'un autre type rendrait les refus verts sans avoir refusé ce
	 * qu'ils nomment. Le réseau n'a pas d'administrateur, et une clé ne s'obtient qu'au nom d'un
	 * administrateur : le compte du décor sans structure l'administre le temps du test.
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
		// depuis la production ne vide pas : vidé, le cache se relit sur la base courante.
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

	private function createCourse(string $name = 'zzTest formation de la FAQ'): int {

		$response = $this->callApi('POST', 'course', body: [
			'name' => $name,
			'summary' => 'Une formation écrite par l\'API.',
			'theme' => $this->eTheme['id'],
			'level' => $this->eLevel['id'],
		]);

		if($response['status'] !== 201) {
			throw new \Exception('création de la formation refusée : '.json_encode($response['body']));
		}

		return $response['body']['id'];

	}

	private function createChapter(int $course, string $name = 'Module de la FAQ'): int {

		$response = $this->callApi('POST', 'chapter', body: [
			'course' => $course,
			'name' => $name,
			'summary' => NULL,
			'phase' => NULL,
		]);

		if($response['status'] !== 201) {
			throw new \Exception('création du module refusée : '.json_encode($response['body']));
		}

		return $response['body']['id'];

	}

	private function createFaq(array $body = []): array {

		return $this->callApi('POST', 'faq', body: $body + [
			'question' => 'Pourquoi la surface inclut-elle les passe-pieds ?',
			'answer' => '<p>La surface d\'une série vaut longueur × (largeur travaillée + passe-pied).</p>',
			'symptoms' => '<p>Ma planche de 20 m est comptée 24 m².</p>',
			'steps' => NULL,
			'notes' => NULL,
			'demoPage' => NULL,
			'demoPath' => NULL,
			'keywords' => 'passe-pied surface rendement',
			'screen' => Faq::SERIES,
			'verdict' => Faq::INTENDED,
			'status' => Faq::PUBLISHED,
			'chapter' => NULL,
			'discordUrl' => 'https://discord.com/channels/1/2/3',
		]);

	}

	private function assertErrorCode(string $code, array $response, string $message): void {
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $message.' ('.json_encode($response['body']).')');
	}

	private function getChapterColumn(int $faq): ?int {

		$eFaq = Faq::model()
			->select('chapter')
			->whereId($faq)
			->get();

		return $eFaq['chapter']->empty() ? NULL : $eFaq['chapter']['id'];

	}

	/**
	 * Une question se crée, se relit, se modifie, se liste avec ses filtres et se retire ; une
	 * question vide et un écran hors de la liste sont refusés.
	 */
	public function testFaq(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$created = $this->createFaq();

		$this->assertEquals(201, $created['status'], json_encode($created['body']));
		$this->assertEquals(self::STRUCTURE, $created['body']['community'] ?? NULL, 'la question appartient à la structure de la clé');
		$this->assertEquals([Faq::SERIES, Faq::INTENDED, Faq::PUBLISHED], [$created['body']['screen'] ?? NULL, $created['body']['verdict'] ?? NULL, $created['body']['status'] ?? NULL]);

		$id = $created['body']['id'];

		$draft = $this->createFaq(['question' => 'Comment exporter mes données ?', 'screen' => Faq::EXPORT, 'verdict' => Faq::POSSIBLE, 'status' => Faq::DRAFT]);

		$this->assertEquals(201, $draft['status'], json_encode($draft['body']));
		$this->assertEquals(Faq::DRAFT, Faq::model()->select('status')->whereId($draft['body']['id'])->get()['status'], 'brouillon écrit');

		$read = $this->callApi('GET', 'faq/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status'], json_encode($read['body']));
		$this->assertEquals("Pourquoi la surface inclut-elle les passe-pieds\u{00A0}?", $read['body']['question'] ?? NULL, 'l\'espace devant le signe est devenue insécable');

		$list = $this->callApi('GET', 'faqList');

		$this->assertEquals(200, $list['status'], json_encode($list['body']));
		$this->assertEquals([$id, $draft['body']['id']], array_column($list['body']['list'] ?? [], 'id'), 'les deux questions de la structure');

		$published = $this->callApi('GET', 'faqList', get: ['status' => Faq::PUBLISHED]);
		$this->assertEquals([$id], array_column($published['body']['list'] ?? [], 'id'), 'filtre sur la publication');

		$export = $this->callApi('GET', 'faqList', get: ['screen' => Faq::EXPORT]);
		$this->assertEquals([$draft['body']['id']], array_column($export['body']['list'] ?? [], 'id'), 'filtre sur l\'écran');

		$update = $this->callApi('PUT', 'faq/{id}', body: [
			'properties' => ['verdict', 'screen'],
			'verdict' => Faq::PARTIAL,
			'screen' => NULL,
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals([Faq::PARTIAL, NULL], [$update['body']['verdict'] ?? 'absent', $update['body']['screen'] ?? NULL], 'verdict changé, écran retiré');

		$typography = $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['question'], 'question' => 'Pourquoi le rendement baisse-t-il?'], get: ['id' => $id]);

		$this->assertEquals(200, $typography['status'], json_encode($typography['body']));
		$this->assertEquals("Pourquoi le rendement baisse-t-il\u{00A0}?", Faq::model()->select('question')->whereId($id)->get()['question'], 'une espace insécable posée devant le signe, par l\'API comme par l\'écran');

		$empty = $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['question'], 'question' => ''], get: ['id' => $id]);

		$this->assertEquals(400, $empty['status'], 'question vide refusée');
		$this->assertErrorCode('Faq::question.check', $empty, 'question vide');

		$screen = $this->createFaq(['screen' => 'accounting']);

		$this->assertEquals(400, $screen['status'], 'écran hors de la liste refusé');
		$this->assertErrorCode('Faq::screen.check', $screen, 'écran hors de la liste');

		$delete = $this->callApi('DELETE', 'faq/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals(404, $this->callApi('GET', 'faq/{id}', get: ['id' => $id])['status'], 'question retirée');

	}

	/**
	 * Un brouillon ne se lit pas par l'état, une question publiée si, et la lecture n'est jamais
	 * fermée par le droit : la FAQ est publique.
	 */
	public function testRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$published = $this->createFaq()['body']['id'];
		$draft = $this->createFaq(['status' => Faq::DRAFT])['body']['id'];

		// Les deux questions sont du même écran (`createFaq()`), sous lequel la FAQ publique les range.
		$cFaq = FaqLib::getPublishedByCommunity(\structure\StructureLib::getById(self::STRUCTURE))[Faq::SERIES];

		$this->assertTrue($cFaq->offsetExists($published), 'la question publiée est dans la FAQ publique');
		$this->assertFalse($cFaq->offsetExists($draft), 'le brouillon n\'y est pas');

		$eFaqPublished = Faq::model()->select(Faq::getSelection())->whereId($published)->get();
		$eFaqDraft = Faq::model()->select(Faq::getSelection())->whereId($draft)->get();

		$this->assertTrue($eFaqPublished->canRead() and $eFaqPublished->acceptRead(), 'publiée, lue par tous');
		$this->assertTrue($eFaqDraft->canRead(), 'le droit ne ferme pas la lecture');
		$this->assertFalse($eFaqDraft->acceptRead(), 'l\'état ferme le brouillon');

	}

	/**
	 * Une question renvoie à un module de la structure, en change, le perd quand il est retiré, puis
	 * quand sa formation entière est retirée. Un module d'une autre structure est refusé.
	 */
	public function testChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$course = $this->createCourse();
		$chapter = $this->createChapter($course);
		$chapterNext = $this->createChapter($course, 'Module suivant');

		$chapterOther = 0;

		$this->asStructure(self::COMMUNITY_EDUCATION, function() use(&$chapterOther) {
			$chapterOther = $this->createChapter($this->createCourse('zzTest formation du groupe'));
		});

		$created = $this->createFaq(['chapter' => $chapter]);

		$this->assertEquals(201, $created['status'], json_encode($created['body']));
		$this->assertEquals($chapter, $created['body']['chapter'] ?? NULL, 'la question renvoie au module');

		$id = $created['body']['id'];

		$foreignCreate = $this->createFaq(['chapter' => $chapterOther]);

		$this->assertEquals(400, $foreignCreate['status'], 'création vers le module d\'une autre structure');
		$this->assertErrorCode('Faq::chapter.owned', $foreignCreate, 'création, module d\'une autre structure');

		$foreignUpdate = $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['chapter'], 'chapter' => $chapterOther], get: ['id' => $id]);

		$this->assertEquals(400, $foreignUpdate['status'], 'modification vers le module d\'une autre structure');
		$this->assertErrorCode('Faq::chapter.owned', $foreignUpdate, 'modification, module d\'une autre structure');
		$this->assertEquals($chapter, $this->getChapterColumn($id), 'rien d\'écrit');

		$move = $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['chapter'], 'chapter' => $chapterNext], get: ['id' => $id]);

		$this->assertEquals(200, $move['status'], json_encode($move['body']));
		$this->assertEquals($chapterNext, $this->getChapterColumn($id), 'renvoi déplacé');

		$this->assertEquals(200, $this->callApi('DELETE', 'chapter/{id}', get: ['id' => $chapterNext])['status'], 'module retiré');
		$this->assertEquals(NULL, $this->getChapterColumn($id), 'la question perd le module retiré');
		$this->assertEquals(1, Faq::model()->whereId($id)->count(), 'la question reste');

		$this->callApi('PUT', 'faq/{id}', body: ['properties' => ['chapter'], 'chapter' => $chapter], get: ['id' => $id]);
		$this->assertEquals($chapter, $this->getChapterColumn($id), 'renvoi reposé');

		CourseLib::delete(CourseLib::getById($course));

		$this->assertEquals(NULL, $this->getChapterColumn($id), 'la question perd le module de la formation retirée');
		$this->assertEquals(1, Faq::model()->whereId($id)->count(), 'la question reste');

	}

	/**
	 * La réponse se découpe en champs, qu'un `PUT` partiel écrit sans renvoyer le reste. Le chemin de
	 * la démo ne mène qu'à la démo de l'environnement : un chemin qui changerait d'hôte est refusé, et
	 * l'emplacement dans le menu ne va jamais sans lui.
	 */
	public function testAnswerFields(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$created = $this->createFaq([
			'steps' => '<ol><li>Ouvrez la série.</li><li>Modifiez la largeur de passe-pieds.</li></ol>',
			'notes' => '<ul><li>Le réglage par défaut ne change pas les séries déjà créées.</li></ul>',
			'demoPage' => '/map/configuration:updateCrop?id=1',
			'demoPath' => 'Paramètres › Les cultures',
		]);

		$this->assertEquals(201, $created['status'], json_encode($created['body']));
		$this->assertEquals(['/map/configuration:updateCrop?id=1', 'Paramètres › Les cultures'], [$created['body']['demoPage'] ?? NULL, $created['body']['demoPath'] ?? NULL], 'chemin et emplacement relus');
		$this->assertTrue(str_contains($created['body']['steps'] ?? '', 'largeur de passe-pieds'), 'marche à suivre relue');
		$this->assertTrue(str_contains($created['body']['notes'] ?? '', 'séries déjà créées'), 'à savoir relu');

		$id = $created['body']['id'];

		$eFaq = Faq::model()->select('demoPage')->whereId($id)->get();
		$this->assertEquals(OTF_DEMO_URL.'/map/configuration:updateCrop?id=1', $eFaq->getDemoUrl(), 'l\'hôte de la démo se compose au rendu');

		$split = $this->callApi('PUT', 'faq/{id}', body: [
			'properties' => ['answer', 'steps', 'notes', 'demoPage', 'demoPath'],
			'answer' => '<p>La surface compte le passe-pied.</p>',
			'steps' => NULL,
			'notes' => NULL,
			'demoPage' => '/ferme/1/especes',
			'demoPath' => 'Paramètres › Les espèces',
		], get: ['id' => $id]);

		$this->assertEquals(200, $split['status'], json_encode($split['body']));
		$this->assertEquals([NULL, NULL, '/ferme/1/especes'], [$split['body']['steps'] ?? NULL, $split['body']['notes'] ?? NULL, $split['body']['demoPage'] ?? NULL], 'découpage écrit par un PUT partiel');
		$this->assertEquals("Pourquoi la surface inclut-elle les passe-pieds\u{00A0}?", $split['body']['question'] ?? NULL, 'le PUT partiel ne touche pas la question');

		foreach(['//autre.site/page', '/\\autre.site', 'javascript:alert(1)', 'https://demo.ouvretaferme.org/ferme/1', 'ferme/1', '/ferme/1 "x"', '/ferme/<b>', "/ferme/\0/series"] as $demoPage) {

			$forged = $this->callApi('PUT', 'faq/{id}', body: [
				'properties' => ['demoPage', 'demoPath'],
				'demoPage' => $demoPage,
				'demoPath' => NULL,
			], get: ['id' => $id]);

			$this->assertEquals(400, $forged['status'], 'chemin refusé : '.$demoPage);
			$this->assertErrorCode('Faq::demoPage.relative', $forged, 'chemin '.$demoPage);

		}

		$this->assertEquals('/ferme/1/especes', Faq::model()->select('demoPage')->whereId($id)->get()['demoPage'], 'aucun chemin refusé n\'est écrit');

		// Un saut de ligne ou un octet nul en bout de chaîne n'atteint pas l'étape `relative` : la
		// conversion des chaînes les retire (`trim()` du cast `string`, `framework/core/Module.php`), et le
		// chemin s'écrit nettoyé.
		foreach(["/ferme/1/series\n" => '/ferme/1/series', "/ferme/1/planning\0" => '/ferme/1/planning'] as $demoPage => $demoPageWritten) {

			$cleaned = $this->callApi('PUT', 'faq/{id}', body: [
				'properties' => ['demoPage', 'demoPath'],
				'demoPage' => $demoPage,
				'demoPath' => NULL,
			], get: ['id' => $id]);

			$this->assertEquals(200, $cleaned['status'], json_encode($cleaned['body']));
			$this->assertEquals($demoPageWritten, Faq::model()->select('demoPage')->whereId($id)->get()['demoPage'], 'chemin écrit nettoyé : '.json_encode($demoPage));

		}

		$alone = $this->callApi('PUT', 'faq/{id}', body: [
			'properties' => ['demoPath'],
			'demoPath' => 'Production › Plan de culture',
		], get: ['id' => $id]);

		$this->assertEquals(400, $alone['status'], 'emplacement seul refusé par le groupe');
		$this->assertErrorCode('property_together', $alone, 'emplacement sans chemin, groupe');

		$withoutPage = $this->callApi('PUT', 'faq/{id}', body: [
			'properties' => ['demoPage', 'demoPath'],
			'demoPage' => NULL,
			'demoPath' => 'Production › Plan de culture',
		], get: ['id' => $id]);

		$this->assertEquals(400, $withoutPage['status'], 'emplacement sans chemin refusé');
		$this->assertErrorCode('Faq::demoPath.withPage', $withoutPage, 'emplacement sans chemin, étape');

		$withoutPath = $this->callApi('PUT', 'faq/{id}', body: [
			'properties' => ['demoPage', 'demoPath'],
			'demoPage' => '/',
			'demoPath' => NULL,
		], get: ['id' => $id]);

		$this->assertEquals(200, $withoutPath['status'], 'un chemin sans emplacement est admis : '.json_encode($withoutPath['body']));

	}

	/**
	 * Deux structures aux ids croisés : chacune lit, liste, modifie et retire ses questions, aucune
	 * n'atteint celles de l'autre. Les formations sont ouvertes à tous le temps de la méthode, le
	 * groupe de formation n'étant pas de `STRUCTURE_BETA`.
	 */
	public function testStructureBound(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$faq = $this->createFaq()['body']['id'];

		$faqOther = 0;

		$this->asStructure(self::COMMUNITY_EDUCATION, function() use(&$faqOther) {
			$faqOther = $this->createFaq(['question' => 'Question du groupe ?'])['body']['id'];
		});

		$cases = [
			[self::STRUCTURE, $faq, $faqOther],
			[self::COMMUNITY_EDUCATION, $faqOther, $faq],
		];

		foreach($cases as [$structure, $faqOwn, $faqForeign]) {

			$this->asStructure($structure, function() use($structure, $faqOwn, $faqForeign) {

				$this->assertEquals(200, $this->callApi('GET', 'faq/{id}', get: ['id' => $faqOwn])['status'], $structure.' lit sa question');
				$this->assertEquals(404, $this->callApi('GET', 'faq/{id}', get: ['id' => $faqForeign])['status'], $structure.' ne lit pas celle d\'une autre');

				$list = $this->callApi('GET', 'faqList');

				$this->assertEquals([$faqOwn], array_column($list['body']['list'] ?? [], 'id'), $structure.' ne liste que ses questions');

				$this->assertEquals(200, $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['question'], 'question' => 'Reformulée ?'], get: ['id' => $faqOwn])['status'], $structure.' reformule sa question');
				$this->assertEquals(404, $this->callApi('PUT', 'faq/{id}', body: ['properties' => ['question'], 'question' => 'Détournée ?'], get: ['id' => $faqForeign])['status'], $structure.' ne reformule pas celle d\'une autre');
				$this->assertEquals(404, $this->callApi('DELETE', 'faq/{id}', get: ['id' => $faqForeign])['status'], $structure.' ne retire pas celle d\'une autre');

			});

		}

		$this->assertEquals(2, Faq::model()->whereId('IN', [$faq, $faqOther])->whereQuestion("Reformulée\u{00A0}?")->count(), 'chaque question n\'a été reformulée que par sa structure');

	}

	/**
	 * Une structure fermée aux formations n'atteint pas la FAQ : le groupe de formation du décor
	 * n'est pas de `STRUCTURE_BETA`, et les formations sont fermées en production simulée. Le refus
	 * tombe dès le scope `learning`, qui ne s'ouvre qu'aux structures ouvertes aux formations.
	 */
	public function testClosedStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals(200, $this->callApi('GET', 'faqList')['status'], 'le réseau bêta liste sa FAQ');

		$this->asStructure(self::COMMUNITY_EDUCATION, function() {

			$list = $this->callApi('GET', 'faqList');
			$create = $this->createFaq();

			$this->assertEquals(403, $list['status'], 'structure fermée, liste');
			$this->assertErrorCode('scope_not_open', $list, 'structure fermée, liste');
			$this->assertEquals(403, $create['status'], 'structure fermée, création');
			$this->assertErrorCode('scope_not_open', $create, 'structure fermée, création');

		});

	}

	/**
	 * Une clé qui ne porte que `learning:read` lit la FAQ et n'en écrit rien.
	 */
	public function testReadScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$faq = $this->createFaq()['body']['id'];

		\api\ApiSetting::$devScopes = ['learning:read'];

		$this->assertEquals(200, $this->callApi('GET', 'faq/{id}', get: ['id' => $faq])['status'], 'learning:read lit une question');
		$this->assertEquals(200, $this->callApi('GET', 'faqList')['status'], 'learning:read liste la FAQ');

		$count = Faq::model()->whereCommunity(self::STRUCTURE)->count();

		foreach([
			['POST', 'faq', [], ['question' => 'Refusée ?']],
			['PUT', 'faq/{id}', ['id' => $faq], ['properties' => ['question'], 'question' => 'Refusée ?']],
			['DELETE', 'faq/{id}', ['id' => $faq], []],
		] as [$method, $route, $get, $body]) {

			$response = $this->callApi($method, $route, body: $body, get: $get);

			$this->assertEquals(403, $response['status'], 'learning:read : '.$method.' '.$route);
			$this->assertEquals('scope_not_allowed', $response['body'][0]['code'] ?? NULL, 'learning:read : '.$method.' '.$route.' ('.json_encode($response['body']).')');

		}

		$this->assertEquals($count, Faq::model()->whereCommunity(self::STRUCTURE)->count(), 'aucune question écrite ni retirée');
		$this->assertEquals(0, Faq::model()->whereId($faq)->whereQuestion("Refusée\u{00A0}?")->count(), 'la question garde son texte');

	}

}
?>
