<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * Le compte créé depuis une formation (choix 2 B du lot glacons-parcours) : le visiteur d'une
 * formation qui demande un compte ouvre un panneau d'inscription, le compte créé n'a aucun rôle, et
 * la formation commence dans la foulée. Les refus de /inscription valent ici aussi, aucun compte
 * n'étant alors créé.
 *
 * Les comptes sont en `example.org`, que la dev n'écrit pas dans `mailEmail` : le relevé de
 * `getLastMail()` le vérifie.
 */
class AccountCreateTest extends LearningDateFixtureTest {

	public function tearDown(): void {

		\user\ThrottleAiLib::clear('sign-up-ip', getIp());

		parent::tearDown();

	}

	public function testVisitor(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedAccount();

		$this->actAs(new \user\User());

		$this->assertTrue(str_contains($this->renderFiche($eCourse), 'href="/learning/account:create?course='.$eCourse['id'].'"'), 'le visiteur ouvre le panneau d\'inscription');

		$data = $this->executeView('learning/account:create', ['course' => $eCourse['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le panneau se prépare : '.(is_string($data) ? $data : 'vue'));

		$panel = new AccountUi()->create($data->eUser, $data->eCourse, $data->enrollFirst);

		foreach(['name="firstName"', 'name="lastName"', 'name="email"', 'name="deliveryCountry"', 'name="password"', 'name="tos"', 'data-ajax-submit="/learning/account:logIn"'] as $expected) {
			$this->assertTrue(str_contains($panel->body, $expected), $expected.' dans le panneau');
		}

		// « Se connecter » bascule le même panneau sur la connexion, l'adresse saisie reprise et le
		// retour vers la formation gardé. `executeView()` rend la requête d'avant en sortant, et
		// `logInBasic()` lit le retour dans `$_REQUEST` : il y est reposé le temps du rendu.
		$data = $this->executeView('learning/account:logIn', ['course' => $eCourse['id'], 'email' => 'zztest.bascule@example.org'], 'POST');

		$_REQUEST['redirect'] = CourseUi::url($eCourse);

		try {
			$login = ($data instanceof \stdClass) ? new AccountUi()->logIn($data->email, $data->eCourse) : NULL;
		} finally {
			unset($_REQUEST['redirect']);
		}

		$this->assertEquals('panel-learning-account', $login?->id, 'le même panneau');
		$this->assertTrue(str_contains($login?->body ?? '', 'value="zztest.bascule@example.org"'), 'l\'adresse saisie reprise');
		$this->assertTrue(str_contains($login?->body ?? '', 'name="redirect" type="hidden" value="'.CourseUi::url($eCourse).'"'), 'le retour vers la formation');

		$this->assertFalse(str_contains($panel->body, 'name="passwordBis"'), 'le mot de passe une seule fois');

		$activeInitial = LearningSetting::$active;
		LearningSetting::$active = FALSE;

		try {
			$fiche = $this->renderFiche($eCourse);
		} finally {
			LearningSetting::$active = $activeInitial;
		}

		$this->assertFalse(str_contains($fiche, '/learning/account:create'), 'formations fermées, la page du compte est fermée : la leçon renvoie à la connexion');

		$eCourse['open'] = TRUE;
		$this->actAs($this->eUserAdmin);
		CourseLib::update($eCourse, ['open']);
		$this->actAs(new \user\User());

		$this->assertFalse(str_contains($this->renderFiche($eCourse), '/learning/account:create'), 'en lecture libre, la leçon s\'ouvre sans compte');

		$this->actAs($this->eUserReader);

		$this->assertEquals(\RedirectAction::class, $this->executeView('learning/account:create', ['course' => $eCourse['id']]), 'un compte connecté n\'en crée pas d\'autre');

		$this->actAs($this->eUserAdmin);

	}

	public function testCreate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$eCourse = $this->seedAccount();

		$this->actAs(new \user\User());

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/account:doCreate', $this->getInput($eCourse, 'zztest.compte-formation@example.org'), redirect: $redirect), 'le compte se crée');

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail('zztest.compte-formation@example.org')
			->get();

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($eUser->notEmpty(), 'le compte existe');
		$this->assertEquals([FALSE, FALSE, FALSE, FALSE], [$eUser['roleCustomer'], $eUser['roleProducer'], $eUser['roleConsultant'], $eUser['roleTeacher']], 'sans aucun rôle');

		$eEnrollment = EnrollmentLib::getByUserAndCourse($eUser, $eCourse);

		$this->assertTrue($eEnrollment->notEmpty(), 'la formation est commencée');
		$this->assertTrue($eEnrollment['structure']->empty(), 'sans structure');
		$this->assertTrue(str_starts_with($redirect, LessonUi::url(CourseLib::getFirst(ChapterLib::getByCourse($eCourse)))), 'sur la première leçon : '.$redirect);

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * Le formulaire du compte ne pose pas la question de la prise en charge et ne dit pas que le réseau
	 * confirme chaque inscription. Sous l'un et l'autre réglage, le compte créé et connecté revient à
	 * la fiche, le panneau d'inscription ouvert (`EnrollmentRequestTest::testPanelOnArrival()`), sans
	 * inscription ni demande écrite.
	 */
	public function testOnlineConfirm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedAccount();

		$this->setOnlineConfirm(\structure\Community::FUNDING);

		$this->actAs(new \user\User());

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/account:doCreate', $this->getInput($eCourse, 'zztest.compte-prise-en-charge@example.org'), redirect: $redirect), 'sous la question, le compte se crée');
		$this->assertTrue(str_starts_with($redirect, CourseUi::url($eCourse, ['enroll' => 1]).'&'), 'et revient à la fiche, le panneau « S\'inscrire » ouvert : '.$redirect);

		$eUser = \user\User::model()->select('id')->whereEmail('zztest.compte-prise-en-charge@example.org')->get();

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($eUser->notEmpty(), 'le compte existe');
		$this->assertTrue(Enrollment::model()->whereUser($eUser)->whereCourse($eCourse)->exists() === FALSE, 'sans inscription ni demande');

		$this->setOnlineConfirm(\structure\Community::ALWAYS);

		$this->actAs(new \user\User());

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/account:doCreate', $this->getInput($eCourse, 'zztest.compte-demande@example.org'), redirect: $redirect), 'sous la confirmation de chaque inscription, le compte se crée');
		$this->assertTrue(str_starts_with($redirect, CourseUi::url($eCourse, ['enroll' => 1]).'&'), 'et revient de même à la fiche, le panneau ouvert, qui dit que le réseau confirme : '.$redirect);

		$eUser = \user\User::model()->select('id')->whereEmail('zztest.compte-demande@example.org')->get();

		$this->actAs($this->eUserAdmin);

		$this->assertTrue(Enrollment::model()->whereUser($eUser)->whereCourse($eCourse)->exists() === FALSE, 'la demande n\'est pas envoyée avant le panneau');

	}

	public function testRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seedAccount();

		$this->actAs(new \user\User());

		$input = $this->getInput($eCourse, 'zztest.compte-refuse@example.org');
		unset($input['tos']);

		$this->assertEquals([\FailAction::class, 'User::tos.accepted'], $this->executePage('learning/account:doCreate', $input), 'les conditions d\'utilisation sont exigées');

		$input = $this->getInput($eCourse, 'zztest.compte-refuse@example.org');
		$input['password'] = 'a';

		$this->assertEquals(\FailAction::class, $this->executePage('learning/account:doCreate', $input)[0], 'le mot de passe trop court refusé');

		$this->assertEquals([\FailAction::class, 'User::email.duplicate'], $this->executePage('learning/account:doCreate', $this->getInput($eCourse, $this->eUserReader['email'])), 'une adresse prise invite à se connecter');

		$this->actAs($this->eUserAdmin);

		$this->assertFalse(\user\User::model()->whereEmail('zztest.compte-refuse@example.org')->exists(), 'aucun compte créé');

	}

	/**
	 * L'écran qui suit une inscription à une date sans compte invite à se connecter l'adresse qui a
	 * déjà un compte, à en créer un les autres.
	 */
	public function testSent(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eDate = $this->createDate($eCourse, 10);

		$this->createUser('zztest.envoi-compte@example.org');

		foreach([
			'zztest.envoi-compte@example.org' => ['/user/log:form', '/inscription'],
			'zztest.envoi-sans-compte@example.org' => ['/inscription', '/user/log:form'],
		] as $email => [$expected, $unexpected]) {

			$this->actAs(new \user\User());

			$data = $this->executeView('reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'].'/formations/'.$eDate['id'].'/:doCreate', [
				'firstName' => 'Zztest',
				'lastName' => 'Envoi',
				'email' => $email,
				'structureName' => 'zzTest structure',
				'trainee' => DateEnrollment::FARMER,
				'commitment' => ['presence', 'cancel', 'conditions'],
			], 'POST');

			$this->actAs($this->eUserAdmin);

			$this->assertTrue($data instanceof \stdClass, $email.' s\'inscrit : '.(is_string($data) ? $data : 'vue'));

			$sent = new DatePublicUi($data->eStructureCommunity, $data->eCommunity, $data->embed)->getSent($data->e);

			$this->assertEquals([TRUE, FALSE], [(preg_match('#href="[^"]*'.preg_quote($expected, '#').'#', $sent) === 1), (preg_match('#href="[^"]*'.preg_quote($unexpected, '#').'#', $sent) === 1)], $email.', '.$expected.' : '.$sent);

		}

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * Une formation en ligne de l'équipe du réseau, retenue au catalogue public et sans lecture
	 * libre : celle que le visiteur ne lit qu'avec un compte.
	 */
	private function seedAccount(): Course {

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::ONLINE);

		$eCourse['open'] = FALSE;
		CourseLib::update($eCourse, ['open']);

		$this->createLessonWritten($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		// L'inscription dans la foulée : le réseau inscrit d'office. Les deux autres réglages sont
		// ceux de `testOnlineConfirm()`.
		$this->setOnlineConfirm(\structure\Community::IMMEDIATE);

		return $this->getCourse($eCourse['id']);

	}

	private function getInput(Course $eCourse, string $email): array {

		return [
			'course' => $eCourse['id'],
			'type' => \user\User::PRIVATE,
			'firstName' => 'zzTest',
			'lastName' => 'Compte',
			'email' => $email,
			'deliveryCountry' => \user\CountryLib::getByCode('FR')['id'],
			'password' => 'zzTest-mot-de-passe',
			'tos' => '1'
		];

	}

	private function renderFiche(Course $eCourse): string {

		$data = $this->executeView('formation/'.$eCourse['id'], []);

		$this->assertTrue($data instanceof \stdClass, 'la fiche '.$eCourse['id'].' se prépare : '.(is_string($data) ? $data : 'vue'));

		if(($data instanceof \stdClass) === FALSE) {
			return '';
		}

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

}
?>
