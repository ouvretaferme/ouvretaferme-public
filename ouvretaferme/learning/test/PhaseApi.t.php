<?php
namespace learning;

/**
 * Les phases d'une formation par l'API (lot T2-phase), de bout en bout par `\ApiRoute::run()`, avec
 * la clé du réseau du décor en production simulée (`LearningSetting::$active` à faux), comme
 * `BlockApiTest` : une phase range les modules d'une formation, et un module en désigne au plus une,
 * toujours de sa formation.
 *
 * La copie et le retrait d'une formation, qui emportent ses phases, se jouent par `CourseLib`, le
 * chemin des pages : aucune route de l'API ne copie une formation.
 *
 * Tout s'écrit dans la base commune : la transaction ouverte sur `Course::model()` par
 * `\dev\ApiTest` couvre les formations, les phases et les modules.
 *
 * Chaque refus est précédé du cas accepté qui lui correspond : un 404 sur la phase d'une autre
 * structure ne prouve la borne que si la même route rend les phases de la clé.
 *
 * Lancement : dev/test package=learning file=PhaseApi
 */
class PhaseApiTest extends \dev\ApiTest {

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

	private function createCourse(string $name = 'zzTest formation des phases'): int {

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

	private function createPhase(int $course, string $name): array {

		return $this->callApi('POST', 'phase', body: [
			'course' => $course,
			'name' => $name,
			'summary' => NULL,
		]);

	}

	private function createChapter(int $course, ?int $phase, string $name = 'Module API'): array {

		return $this->callApi('POST', 'chapter', body: [
			'course' => $course,
			'name' => $name,
			'summary' => NULL,
			'phase' => $phase,
		]);

	}

	private function assertErrorCode(string $code, array $response, string $message): void {
		$this->assertEquals($code, $response['body'][0]['code'] ?? NULL, $message.' ('.json_encode($response['body']).')');
	}

	private function getPhaseColumn(int $chapter): ?int {

		$eChapter = Chapter::model()
			->select('phase')
			->whereId($chapter)
			->get();

		return $eChapter['phase']->empty() ? NULL : $eChapter['phase']['id'];

	}

	/**
	 * Les phases se rangent dans l'ordre de leur création, se relisent, se renomment, et le retrait
	 * de la première fait remonter la suivante sans laisser de trou.
	 */
	public function testPhase(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();

		$first = $this->createPhase($course, 'Préparer la saison');
		$second = $this->createPhase($course, 'Suivre la saison');

		$this->assertEquals(201, $first['status'], json_encode($first['body']));
		$this->assertEquals(201, $second['status'], json_encode($second['body']));
		$this->assertEquals([0, 1], [$first['body']['position'] ?? NULL, $second['body']['position'] ?? NULL], 'rangées dans l\'ordre de création');
		$this->assertEquals($course, $first['body']['course'] ?? NULL);

		$read = $this->callApi('GET', 'phase/{id}', get: ['id' => $first['body']['id']]);

		$this->assertEquals(200, $read['status'], json_encode($read['body']));
		$this->assertEquals('Préparer la saison', $read['body']['name'] ?? NULL);

		$list = $this->callApi('GET', 'course/{id}/phasesList', get: ['id' => $course]);

		$this->assertEquals(200, $list['status'], json_encode($list['body']));
		$this->assertEquals([$first['body']['id'], $second['body']['id']], array_column($list['body']['list'] ?? [], 'id'));

		$update = $this->callApi('PUT', 'phase/{id}', body: [
			'name' => 'Préparer la saison suivante',
			'summary' => 'Avant les semis.',
		], get: ['id' => $first['body']['id']]);

		$this->assertEquals(200, $update['status'], json_encode($update['body']));
		$this->assertEquals(['Préparer la saison suivante', 'Avant les semis.'], [$update['body']['name'] ?? NULL, $update['body']['summary'] ?? NULL]);

		$refused = $this->callApi('PUT', 'phase/{id}', body: [
			'name' => '',
			'summary' => NULL,
		], get: ['id' => $first['body']['id']]);

		$this->assertEquals(400, $refused['status'], 'nom vide refusé');
		$this->assertErrorCode('Phase::name.check', $refused, 'nom vide');

		$delete = $this->callApi('DELETE', 'phase/{id}', get: ['id' => $first['body']['id']]);

		$this->assertEquals(200, $delete['status'], json_encode($delete['body']));
		$this->assertEquals(404, $this->callApi('GET', 'phase/{id}', get: ['id' => $first['body']['id']])['status'], 'phase retirée');
		$this->assertEquals(0, Phase::model()->select('position')->whereId($second['body']['id'])->get()['position'], 'la suivante remonte au premier rang');

	}

	/**
	 * Un module se range sous une phase de sa formation, en change, la quitte, et la retrouve vide
	 * quand la phase est retirée. Une phase d'une autre formation de la même structure est refusée,
	 * en création comme en modification.
	 */
	public function testChapterPhase(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$courseOther = $this->createCourse('zzTest autre formation des phases');

		$phase = $this->createPhase($course, 'Préparer')['body']['id'];
		$phaseNext = $this->createPhase($course, 'Suivre')['body']['id'];
		$phaseOther = $this->createPhase($courseOther, 'Ailleurs')['body']['id'];

		$chapter = $this->createChapter($course, $phase);

		$this->assertEquals(201, $chapter['status'], json_encode($chapter['body']));
		$this->assertEquals($phase, $chapter['body']['phase'] ?? NULL, 'module rangé sous sa phase');

		$id = $chapter['body']['id'];

		$move = $this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => $phaseNext], get: ['id' => $id]);

		$this->assertEquals(200, $move['status'], json_encode($move['body']));
		$this->assertEquals($phaseNext, $this->getPhaseColumn($id), 'module déplacé sous la phase suivante');

		$empty = $this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => NULL], get: ['id' => $id]);

		$this->assertEquals(200, $empty['status'], json_encode($empty['body']));
		$this->assertEquals(NULL, $this->getPhaseColumn($id), 'module sans phase');

		$this->assertEquals(201, $this->createChapter($course, NULL, 'Module sans phase')['status'], 'un module naît sans phase');

		$refusedCreate = $this->createChapter($course, $phaseOther, 'Module mal rangé');

		$this->assertEquals(400, $refusedCreate['status'], 'création sous la phase d\'une autre formation');
		$this->assertErrorCode('Chapter::phase.consistent', $refusedCreate, 'création, phase d\'une autre formation');

		$refusedUpdate = $this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => $phaseOther], get: ['id' => $id]);

		$this->assertEquals(400, $refusedUpdate['status'], 'modification sous la phase d\'une autre formation');
		$this->assertErrorCode('Chapter::phase.consistent', $refusedUpdate, 'modification, phase d\'une autre formation');
		$this->assertEquals(NULL, $this->getPhaseColumn($id), 'rien d\'écrit');

		$this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => $phase], get: ['id' => $id]);
		$this->assertEquals($phase, $this->getPhaseColumn($id), 'module rangé de nouveau');

		$this->assertEquals(200, $this->callApi('DELETE', 'phase/{id}', get: ['id' => $phase])['status'], 'phase retirée');
		$this->assertEquals(NULL, $this->getPhaseColumn($id), 'le module de la phase retirée reste, sans phase');
		$this->assertEquals(1, Chapter::model()->whereId($id)->count(), 'le module n\'est pas retiré');

	}

	/**
	 * Deux structures aux ids croisés : chacune lit et range ses phases, aucune n'atteint celles de
	 * l'autre, ni par leur id, ni par la liste d'une formation, ni en y rangeant un de ses modules, ni
	 * en créant une phase dans une formation de l'autre. Les formations sont ouvertes à tous le temps
	 * de la méthode, le groupe de formation n'étant pas de `STRUCTURE_BETA`.
	 */
	public function testStructureBound(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		LearningSetting::$active = TRUE;

		$course = $this->createCourse();
		$phase = $this->createPhase($course, 'Du réseau')['body']['id'];
		$chapter = $this->createChapter($course, NULL)['body']['id'];

		$courseOther = 0;
		$phaseOther = 0;
		$chapterOther = 0;

		$this->asStructure(self::COMMUNITY_EDUCATION, function() use(&$courseOther, &$phaseOther, &$chapterOther) {

			$courseOther = $this->createCourse('zzTest formation du groupe');
			$phaseOther = $this->createPhase($courseOther, 'Du groupe')['body']['id'];
			$chapterOther = $this->createChapter($courseOther, NULL)['body']['id'];

		});

		$cases = [
			[self::STRUCTURE, $course, $phase, $chapter, $courseOther, $phaseOther, $chapterOther],
			[self::COMMUNITY_EDUCATION, $courseOther, $phaseOther, $chapterOther, $course, $phase, $chapter],
		];

		foreach($cases as [$structure, $courseOwn, $phaseOwn, $chapterOwn, $courseForeign, $phaseForeign, $chapterForeign]) {

			$this->asStructure($structure, function() use($structure, $courseOwn, $phaseOwn, $chapterOwn, $courseForeign, $phaseForeign, $chapterForeign) {

				$this->assertEquals(200, $this->callApi('GET', 'phase/{id}', get: ['id' => $phaseOwn])['status'], $structure.' lit sa phase');
				$this->assertEquals(404, $this->callApi('GET', 'phase/{id}', get: ['id' => $phaseForeign])['status'], $structure.' ne lit pas celle d\'une autre');

				$this->assertEquals(200, $this->callApi('GET', 'course/{id}/phasesList', get: ['id' => $courseOwn])['status'], $structure.' liste les phases de sa formation');
				$this->assertEquals(404, $this->callApi('GET', 'course/{id}/phasesList', get: ['id' => $courseForeign])['status'], $structure.' ne liste pas celles d\'une autre');

				$this->assertEquals(200, $this->callApi('PUT', 'phase/{id}', body: ['properties' => ['name'], 'name' => 'Renommée'], get: ['id' => $phaseOwn])['status'], $structure.' renomme sa phase');
				$this->assertEquals(404, $this->callApi('PUT', 'phase/{id}', body: ['properties' => ['name'], 'name' => 'Détournée'], get: ['id' => $phaseForeign])['status'], $structure.' ne renomme pas celle d\'une autre');
				$this->assertEquals(404, $this->callApi('DELETE', 'phase/{id}', get: ['id' => $phaseForeign])['status'], $structure.' ne retire pas celle d\'une autre');

				$this->assertEquals(201, $this->createPhase($courseOwn, 'Neuve')['status'], $structure.' crée une phase dans sa formation');

				$foreignCourse = $this->createPhase($courseForeign, 'Intruse');

				$this->assertEquals(400, $foreignCourse['status'], $structure.' ne crée pas de phase dans la formation d\'une autre');
				$this->assertErrorCode('Phase::course.owned', $foreignCourse, $structure.', formation d\'une autre');

				$this->assertEquals(200, $this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => $phaseOwn], get: ['id' => $chapterOwn])['status'], $structure.' range son module sous sa phase');

				$foreignPhase = $this->callApi('PUT', 'chapter/{id}', body: ['properties' => ['phase'], 'phase' => $phaseForeign], get: ['id' => $chapterOwn]);

				$this->assertEquals(400, $foreignPhase['status'], $structure.' ne range pas son module sous la phase d\'une autre');
				$this->assertErrorCode('Chapter::phase.consistent', $foreignPhase, $structure.', phase d\'une autre');

			});

		}

		$this->assertEquals(1, Phase::model()->whereId($phaseOther)->whereName('Renommée')->count(), 'la phase du groupe n\'a été renommée que par lui');
		$this->assertEquals($phaseOther, $this->getPhaseColumn($chapterOther), 'le module du groupe reste sous sa phase');

	}

	/**
	 * Une clé qui ne porte que `learning:read` lit les phases et n'en écrit aucune.
	 */
	public function testReadScope(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();
		$phase = $this->createPhase($course, 'Lue')['body']['id'];

		\api\ApiSetting::$devScopes = ['learning:read'];

		$this->assertEquals(200, $this->callApi('GET', 'phase/{id}', get: ['id' => $phase])['status'], 'learning:read lit une phase');
		$this->assertEquals(200, $this->callApi('GET', 'course/{id}/phasesList', get: ['id' => $course])['status'], 'learning:read liste les phases');

		$phases = Phase::model()->whereCourse($course)->count();

		foreach([
			['POST', 'phase', [], ['course' => $course, 'name' => 'Refusée', 'summary' => NULL]],
			['PUT', 'phase/{id}', ['id' => $phase], ['properties' => ['name'], 'name' => 'Refusée']],
			['DELETE', 'phase/{id}', ['id' => $phase], []],
		] as [$method, $route, $get, $body]) {

			$response = $this->callApi($method, $route, body: $body, get: $get);

			$this->assertEquals(403, $response['status'], 'learning:read : '.$method.' '.$route);
			$this->assertEquals('scope_not_allowed', $response['body'][0]['code'] ?? NULL, 'learning:read : '.$method.' '.$route.' ('.json_encode($response['body']).')');

		}

		$this->assertEquals($phases, Phase::model()->whereCourse($course)->count(), 'aucune phase écrite ni retirée');
		$this->assertEquals(1, Phase::model()->whereId($phase)->whereName('Lue')->count(), 'la phase garde son nom');

	}

	/**
	 * La copie d'une formation copie ses phases dans l'ordre, et range chaque module copié sous la
	 * phase copiée qui correspond, jamais sous une phase de l'original. Le retrait de la formation
	 * emporte ses phases.
	 */
	public function testCopyAndDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$course = $this->createCourse();

		$phaseFirst = $this->createPhase($course, 'Préparer')['body']['id'];
		$phaseSecond = $this->createPhase($course, 'Suivre')['body']['id'];

		$this->createChapter($course, $phaseSecond, 'Module de la seconde');
		$this->createChapter($course, NULL, 'Module sans phase');
		$this->createChapter($course, $phaseFirst, 'Module de la première');

		$eCourse = CourseLib::getById($course);

		$eCourseNew = CourseLib::getCreateElement($eCourse['community']);

		$fw = new \FailWatch();

		$eCourseNew->build(['name'], ['name' => 'zzTest copie des phases'], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('titre de la copie refusé : '.json_encode($fw->get())));

		$eCourseCopy = CourseLib::duplicate($eCourse, $eCourseNew);

		$cPhaseCopy = PhaseLib::getByCourse($eCourseCopy);

		$this->assertEquals([['Préparer', 0], ['Suivre', 1]], $cPhaseCopy->makeArray(fn($ePhase) => [$ePhase['name'], $ePhase['position']]), 'phases copiées dans l\'ordre');

		$phaseNames = [];

		foreach($cPhaseCopy as $ePhaseCopy) {
			$phaseNames[$ePhaseCopy['id']] = $ePhaseCopy['name'];
		}

		$cChapterCopy = Chapter::model()
			->select(['name', 'phase'])
			->whereCourse($eCourseCopy)
			->sort(['position' => SORT_ASC])
			->getCollection();

		$placed = [];

		foreach($cChapterCopy as $eChapterCopy) {
			$placed[$eChapterCopy['name']] = $eChapterCopy['phase']->empty() ? NULL : ($phaseNames[$eChapterCopy['phase']['id']] ?? 'phase étrangère '.$eChapterCopy['phase']['id']);
		}

		$this->assertEquals([
			'Module de la seconde' => 'Suivre',
			'Module sans phase' => NULL,
			'Module de la première' => 'Préparer',
		], $placed, 'chaque module copié sous la phase copiée qui correspond');

		$this->assertEquals(0, Chapter::model()->whereCourse($eCourseCopy)->wherePhase('IN', [$phaseFirst, $phaseSecond])->count(), 'aucun module copié sous une phase de l\'original');

		CourseLib::delete(CourseLib::getById($course));

		$this->assertEquals(0, Phase::model()->whereCourse($course)->count(), 'les phases partent avec leur formation');
		$this->assertEquals(2, Phase::model()->whereCourse($eCourseCopy)->count(), 'celles de la copie restent');

	}

}
?>
