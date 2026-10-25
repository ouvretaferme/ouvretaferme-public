<?php
namespace learning;

/**
 * Le scope `learning` de l'API (lot T0c) : une clé qui ne porte que `learning:read` et
 * `learning:write` écrit les formations de sa structure, et rien d'autre.
 *
 * La clé de dev porte d'ordinaire tous les scopes personnels : `ApiSetting::$devScopes` la limite le
 * temps d'une méthode, et `tearDown()` la rend. Chaque refus est précédé du cas accepté qui lui
 * correspond, dans la même méthode : un 403 seul ne dirait pas si la route refuse le scope, ou tout.
 *
 * Tout s'écrit dans la base commune : la transaction ouverte sur `Course::model()` par
 * `\dev\ApiTest` couvre les formations comme les membres, rôles et invitations du réseau.
 *
 * Lancement : dev/test package=learning file=ScopeApi
 */
class ScopeApiTest extends \dev\ApiTest {

	/** Réseau du décor commun, dans `LearningSetting::STRUCTURE_BETA` en dev */
	const STRUCTURE = \structure\FixtureAiLib::G3;

	/** Groupe de formation du décor commun, qui porte la formation « d'ailleurs » */
	const COMMUNITY_EDUCATION = \structure\FixtureAiLib::G2;

	/** Ferme du décor commun, qui n'a pas les formations */
	const FARM = \structure\FixtureAiLib::F1;

	/** Compte du décor commun sans structure, qui administre le réseau le temps du test (cf `ApiTest`) */
	const EMAIL_KEY = 'decor.sans.structure@example.org';

	const SCOPES = ['learning:read', 'learning:write'];

	protected function getTransactionModel(): \ModuleModel {
		return Course::model();
	}

	public function tearDown(): void {

		\api\ApiSetting::$devScopes = NULL;

		parent::tearDown();

	}

	/**
	 * La clé limitée aux formations crée une formation, puis reçoit 403 `scope_not_allowed` sur
	 * chaque écriture du domaine `general`, sans que rien soit écrit.
	 */
	public function testLearningKeyWritesOnlyLearning(): void {

		$eUserKey = \user\UserLib::getByEmail(self::EMAIL_KEY);
		$eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$eLevel = LevelLib::getAll()->first() ?? new Level();

		if($this->assertDecorPresent(
			$this->eStructure->isNetwork() and $this->eStructure->acceptLearning() and $eUserKey->notEmpty() and $eTheme->notEmpty() and $eLevel->notEmpty(),
			'le réseau '.self::STRUCTURE.' avec les formations, le compte '.self::EMAIL_KEY.', une thématique et un niveau'
		) === FALSE) {
			return;
		}

		$this->joinAdmin($this->eStructure, $eUserKey);

		\api\ApiSetting::$devScopes = self::SCOPES;

		$response = $this->callApi('POST', 'course', body: [
			'name' => 'zzTest formation du scope',
			'summary' => 'Une formation écrite avec la clé des formations.',
			'theme' => $eTheme['id'],
			'level' => $eLevel['id'],
		]);

		$this->assertEquals(201, $response['status'], 'la clé des formations crée une formation : '.json_encode($response['body']));

		$eMember = \structure\MemberLib::getAdminsByStructure($this->eStructure)->first();
		$eRole = \structure\RoleLib::getOne($this->eStructure, 'admin');

		$members = \structure\Member::model()->whereStructure($this->eStructure)->count();
		$roles = \structure\Role::model()->whereStructure($this->eStructure)->count();
		$invites = \structure\Invite::model()->whereStructure($this->eStructure)->count();

		foreach([
			['PUT', 'member/{id}', ['id' => $eMember['id']], ['properties' => ['role'], 'role' => $eRole['id']]],
			['DELETE', 'member/{id}', ['id' => $eMember['id']], []],
			['POST', 'role', [], ['name' => 'zzTest rôle du scope']],
			['POST', 'invite', [], ['email' => 'scope.api@example.org']],
			['PUT', 'system', [], ['properties' => ['altitude'], 'altitude' => 100]],
		] as [$method, $route, $get, $body]) {

			$response = $this->callApi($method, $route, body: $body, get: $get);

			$this->assertEquals(403, $response['status'], 'clé des formations : '.$method.' '.$route);
			$this->assertEquals('scope_not_allowed', $response['body'][0]['code'] ?? NULL, 'clé des formations : '.$method.' '.$route.' ('.json_encode($response['body']).')');

		}

		$this->assertEquals(
			[$members, $roles, $invites],
			[
				\structure\Member::model()->whereStructure($this->eStructure)->count(),
				\structure\Role::model()->whereStructure($this->eStructure)->count(),
				\structure\Invite::model()->whereStructure($this->eStructure)->count(),
			],
			'ni membre retiré, ni rôle, ni invitation écrits'
		);

	}

	/**
	 * `structure\Structure_0_1betaApi` est du domaine `general` et ne déclare que la lecture : il n'y a
	 * aucune écriture à lui refuser. Relevé sur les routes déclarées, pour qu'une écriture ajoutée un
	 * jour rejoigne la méthode précédente au lieu de passer inaperçue.
	 */
	public function testStructureResourceHasNoWrite(): void {

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$routes = $property->getValue();

		$property->setValue(NULL, ['POST' => [], 'GET' => [], 'PUT' => [], 'DELETE' => [], 'PATCH' => []]);

		try {

			new \structure\Structure_0_1betaApi();

			$declared = $property->getValue();

		} finally {
			$property->setValue(NULL, $routes);
		}

		$this->assertEquals(['me'], array_keys($declared['GET']), 'une lecture, GET me');
		$this->assertEquals([[], [], [], []], [$declared['POST'], $declared['PUT'], $declared['DELETE'], $declared['PATCH']], 'aucune écriture');

	}

	/**
	 * La clé des formations ne sort pas de sa structure : la formation et le module d'un autre groupe
	 * répondent comme une ressource inconnue, et restent intacts. Le cas accepté est la relecture de
	 * la formation du réseau.
	 */
	public function testLearningKeyStaysInStructure(): void {

		$eUserKey = \user\UserLib::getByEmail(self::EMAIL_KEY);
		$eCommunityEducation = \structure\StructureLib::getById(self::COMMUNITY_EDUCATION);
		$eTheme = ThemeLib::getAll()->first() ?? new Theme();
		$eLevel = LevelLib::getAll()->first() ?? new Level();

		if($this->assertDecorPresent(
			$this->eStructure->acceptLearning() and $eCommunityEducation->notEmpty() and $eUserKey->notEmpty() and $eTheme->notEmpty() and $eLevel->notEmpty(),
			'le réseau '.self::STRUCTURE.' avec les formations, le groupe '.self::COMMUNITY_EDUCATION.', le compte '.self::EMAIL_KEY.', une thématique et un niveau'
		) === FALSE) {
			return;
		}

		$this->joinAdmin($this->eStructure, $eUserKey);

		$eCourseOwn = $this->createCourse($this->eStructure, 'zzTest formation du réseau', $eTheme, $eLevel);
		$eCourseForeign = $this->createCourse($eCommunityEducation, 'zzTest formation d\'ailleurs', $eTheme, $eLevel);

		$eChapterForeign = new Chapter(['course' => $eCourseForeign, 'name' => 'Module d\'ailleurs']);
		ChapterLib::create($eChapterForeign);

		\api\ApiSetting::$devScopes = self::SCOPES;

		$response = $this->callApi('GET', 'course/{id}', get: ['id' => $eCourseOwn['id']]);

		$this->assertEquals(200, $response['status'], 'la formation du réseau se relit : '.json_encode($response['body']));

		foreach([
			['PUT', 'course/{id}', $eCourseForeign['id'], ['properties' => ['name'], 'name' => 'Formation détournée']],
			['DELETE', 'chapter/{id}', $eChapterForeign['id'], []],
		] as [$method, $route, $id, $body]) {

			$response = $this->callApi($method, $route, body: $body, get: ['id' => $id]);

			$this->assertEquals(404, $response['status'], 'ressource d\'ailleurs : '.$method.' '.$route);
			$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL, 'ressource d\'ailleurs : '.$method.' '.$route.' ('.json_encode($response['body']).')');

		}

		// Lu en collection et non en élément : une ligne supprimée rend un tableau vide, là où un
		// élément vide ferait lever la lecture de `name` avant l'assertion.
		$this->assertEquals([['zzTest formation d\'ailleurs'], ['Module d\'ailleurs']], [
			Course::model()->select('name')->whereId($eCourseForeign)->getCollection()->getColumn('name'),
			Chapter::model()->select('name')->whereId($eChapterForeign)->getCollection()->getColumn('name'),
		], 'formation et module d\'ailleurs intacts');

	}

	/**
	 * La règle que lisent la page des clés et l'appel, éprouvée écriture fermée
	 * (`IS_API_WRITE_ACTIVE` vaut vrai en dev) : les formations s'ouvrent au réseau qui les a, en
	 * lecture comme en écriture, sans lui ouvrir l'écriture des autres domaines.
	 */
	public function testAcceptApiScope(): void {

		$eFarm = \structure\StructureLib::getById(self::FARM);

		if($this->assertDecorPresent(
			$this->eStructure->acceptLearning() and $eFarm->notEmpty() and $eFarm->acceptLearning() === FALSE,
			'le réseau '.self::STRUCTURE.' avec les formations et la ferme '.self::FARM.' sans'
		) === FALSE) {
			return;
		}

		$this->assertEquals(FALSE, in_array(self::STRUCTURE, \structure\StructureSetting::API_STRUCTURE_BETA), 'le réseau n\'est pas une structure de la bêta de l\'API');

		$this->assertEquals(TRUE, $this->eStructure->acceptApiScope('learning:write', FALSE), 'réseau, learning:write');
		$this->assertEquals(TRUE, $this->eStructure->acceptApiScope('learning:read', FALSE), 'réseau, learning:read');
		$this->assertEquals(FALSE, $this->eStructure->acceptApiScope('general:write', FALSE), 'réseau, general:write reste fermé');
		$this->assertEquals(TRUE, $this->eStructure->acceptApiScope('general:read', FALSE), 'réseau, general:read');

		$this->assertEquals(FALSE, $eFarm->acceptApiScope('learning:write', TRUE), 'ferme, learning:write');
		$this->assertEquals(FALSE, $eFarm->acceptApiScope('learning:read', TRUE), 'ferme, learning:read');

		$this->assertEquals(self::SCOPES, array_values(array_intersect($this->eStructure->getApiScopes(), self::SCOPES)), 'la page des clés propose les formations au réseau');
		$this->assertEquals([], array_values(array_intersect($eFarm->getApiScopes(), self::SCOPES)), 'la page des clés ne les propose pas à la ferme');

	}

	private function createCourse(\structure\Structure $eStructure, string $name, Theme $eTheme, Level $eLevel): Course {

		$eCourse = CourseLib::getCreateElement($eStructure);
		$eCourse->merge([
			'name' => $name,
			'summary' => 'Une formation écrite sans passer par l\'API.',
			'theme' => $eTheme,
			'level' => $eLevel,
		]);

		CourseLib::create($eCourse);

		return $eCourse;

	}

	/**
	 * Le réseau du décor n'a pas d'administrateur, et une clé ne s'obtient qu'au nom d'un
	 * administrateur. Hors build(), comme le pose `InviteLib::accept()` : un membre entre par une
	 * invitation, aucun formulaire ne le construit. La transaction du test l'efface.
	 */
	private function joinAdmin(\structure\Structure $eStructure, \user\User $eUser): void {

		if(\structure\MemberLib::isAdmin($eUser, $eStructure)) {
			return;
		}

		// Les rôles d'une structure se lisent dans un cache redis, qu'un rechargement de la base
		// depuis la production ne vide pas : un rôle tiré d'un cache antérieur porte un id que la base
		// n'a plus, et le membre créé n'administre rien. Vidé, le cache se relit sur la base courante.
		\structure\RoleLib::deleteCache($eStructure);

		$fw = new \FailWatch();

		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $eStructure,
			'role' => \structure\RoleLib::getOne($eStructure, 'admin'),
			'status' => \structure\Member::IN
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion de '.self::EMAIL_KEY.' au réseau '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

	}

}
?>
