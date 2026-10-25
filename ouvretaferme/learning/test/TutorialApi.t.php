<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * L'écriture des tutoriels par l'API en production (lot T0-portes) : les routes se déclarent même
 * quand les formations ne sont pas ouvertes à tous (`LearningSetting::$active` à faux), et c'est la
 * structure de la clé qui décide (`Course_0_1betaApi::validateStructure()`). Le réseau des tutoriels
 * du décor tient le rôle de celui de production, comme dans `Tutorial.t.php`, et la clé est au nom de
 * son administrateur (`ApiSetting::$devAccessTokenUser`).
 *
 * L'appel passe par `\ApiRoute::run()` (cf `\dev\ApiTest`), donc par `ApiData::authorized()` et
 * `acceptFeature(FEATURE_API)`. L'écriture y est ouverte par `Structure::acceptApiScope()`, qui
 * ouvre `learning:write` au réseau qui a les formations : en production, celui des tutoriels l'a
 * par `LearningSetting::STRUCTURE_BETA`, au second push du lot.
 *
 * Lancement : dev/test package=learning file=TutorialApi
 */
class TutorialApiTest extends \dev\ApiTest {

	/** Réseau des tutoriels du décor commun, dans `LearningSetting::STRUCTURE_BETA` en dev */
	const STRUCTURE = LearningTutorialFixtureTest::COMMUNITY_TUTORIAL;

	/** Groupe de formation du décor commun, absent de `LearningSetting::STRUCTURE_BETA` */
	const COMMUNITY_EDUCATION = \structure\FixtureAiLib::G2;

	private bool $activeInitial;
	private ?int $structureTutorialInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Course::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->activeInitial = LearningSetting::$active;
		$this->structureTutorialInitial = LearningSetting::$structureTutorial;

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;
		LearningSetting::$structureTutorial = $this->structureTutorialInitial;

		parent::tearDown();

	}

	/**
	 * En production simulée, les routes déclarées à vide puis rendues : le réseau des tutoriels crée
	 * une formation, le groupe absent de la liste est refusé au scope (`Structure::acceptApiScope()`).
	 */
	public function testWriteInProduction(): void {

		$eStructure = \structure\StructureLib::getById(self::STRUCTURE);
		$eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$eLevel = LevelLib::getAll()->first() ?? new Level();

		if($this->assertDecorPresent(
			$eStructure->notEmpty() and $eStructure->isNetwork() and \structure\MemberLib::getAdminsByStructure($eStructure)->notEmpty() and $eTheme->notEmpty() and $eLevel->notEmpty(),
			'le réseau des tutoriels '.self::STRUCTURE.' du décor avec son administrateur, une thématique et un niveau'
		) === FALSE) {
			return;
		}

		LearningSetting::$active = FALSE;
		LearningSetting::$structureTutorial = self::STRUCTURE;

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$routes = $property->getValue();

		$property->setValue(NULL, []);

		try {

			foreach(['Chapter', 'Course', 'Date', 'DateEnrollment', 'Lesson'] as $module) {
				$class = __NAMESPACE__.'\\'.$module.'_0_1betaApi';
				new $class();
			}

			$response = $this->callApi('POST', 'course', body: [
				'name' => 'zzTest tutoriel API',
				'summary' => 'Un tutoriel écrit par l\'API.',
				'theme' => $eTheme['id'],
				'level' => $eLevel['id'],
			]);

			$this->assertEquals(201, $response['status'], 'le réseau des tutoriels crée une formation : '.json_encode($response['body']));

			$eCourse = CourseLib::getById($response['body']['id'] ?? NULL);

			$this->assertTrue($eCourse->notEmpty() and $eCourse->acceptTutorial(), 'la formation créée est un tutoriel');

			$tokenInitial = \api\ApiSetting::$devAccessToken;
			\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken(self::COMMUNITY_EDUCATION);

			try {

				foreach([['GET', 'courseList'], ['POST', 'course']] as [$method, $route]) {

					$response = $this->callApi($method, $route, body: ['name' => 'zzTest formation refusée']);

					$this->assertEquals(403, $response['status'], 'groupe absent de la liste : '.$method.' '.$route);
					$this->assertEquals('scope_not_open', $response['body'][0]['code'] ?? NULL, 'groupe absent de la liste : '.$method.' '.$route.' ('.json_encode($response['body']).')');

				}

			} finally {
				\api\ApiSetting::$devAccessToken = $tokenInitial;
			}

		} finally {
			$property->setValue(NULL, $routes);
		}

	}

}
?>
