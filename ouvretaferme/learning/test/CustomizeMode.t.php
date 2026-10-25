<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * La barre de personnalisation sur la fiche et la leçon d'une formation de réseau (lot A2b) : réservée
 * au gérant du réseau, jamais au conseiller ni au lecteur ; l'essai d'un réglage sur le domaine de la
 * fiche ; rien d'écrit tant qu'on n'enregistre pas ; et le mode qui suit le gérant de la liste du
 * réseau à la fiche, puis à la leçon, réglages essayés compris.
 *
 * Le décor est celui de `LearningDateFixtureTest`, pour la date qui met la formation sur la liste.
 * L'utilisateur 1, conseiller du réseau G3, en devient administrateur le temps des tests qui
 * demandent le gérant.
 *
 * Lancement : dev/test package=learning file=CustomizeMode
 */
class CustomizeModeTest extends LearningDateFixtureTest {

	const GREEN = '#2f6b4f';
	const CREAM = '#f3ede4';
	const LOBSTER = "'Lobster', cursive";

	/**
	 * La barre et l'entrée « Personnaliser l'apparence » : au gérant seul, sur la fiche comme sur la
	 * leçon. Le conseiller écrit la formation sans gérer le réseau, le lecteur la lit.
	 */
	public function testBarIsReservedToTheManager(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedNetwork();

		$pages = [
			'la fiche' => ['formation/'.$eCourse['id'], 'learning/course'],
			'la leçon' => ['formation/lecon/'.$eLesson['id'], 'learning/lesson'],
		];

		foreach([
			'le lecteur' => $this->eUserReader,
			'le conseiller' => $this->eUserAdmin,
		] as $who => $eUser) {

			$this->actAs($eUser);

			foreach($pages as $page => [$path, $view]) {

				$data = $this->executeView($path, ['customize' => '1', 'color' => self::GREEN]);

				$this->assertTrue($data instanceof \stdClass, $page.' se lit par '.$who);
				$this->assertFalse($data->customize, $page.' sans mode pour '.$who);
				$this->assertEquals(self::CREAM, $data->settings['customBackground'], $page.' garde le fond enregistré pour '.$who);
				$this->assertEquals(NULL, $data->settings['color'], $page.' sans la couleur de l\'adresse pour '.$who);

				$html = $this->renderRoute($path, ['customize' => '1'], $view);

				$this->assertFalse(str_contains($html, 'learning-customize-bar'), $page.' sans barre pour '.$who);

			}

			$this->assertFalse(str_contains($this->renderRoute('formation/'.$eCourse['id'], view: 'learning/course'), 'customize=1'), 'pas d\'entrée « Personnaliser l\'apparence » pour '.$who);

		}

		$this->actAs(new \user\User());

		foreach($pages as $page => [$path]) {
			$this->assertTrue(is_string($this->executeView($path, ['customize' => '1'])), $page.' d\'une formation privée ne s\'ouvre pas sans compte, barre comprise');
		}

		$this->becomeManager();

		$html = $this->renderRoute('formation/'.$eCourse['id'], view: 'learning/course');

		$this->assertTrue(str_contains($html, 'href="/formation/'.$eCourse['id'].'?customize=1"'), 'l\'entrée « Personnaliser l\'apparence » pour le gérant');
		$this->assertFalse(str_contains($html, 'learning-customize-bar'), 'sans barre hors du mode');

		$html = $this->renderRoute('formation/'.$eCourse['id'], ['customize' => '1'], 'learning/course');

		$this->assertTrue(str_contains($html, 'data-url="/formation/'.$eCourse['id'].'/:doCustomize" data-preview="/formation/'.$eCourse['id'].'/:doPreview" data-exit="/formation/'.$eCourse['id'].'"'), 'la barre de la fiche poste sur la fiche et ramène à la fiche');
		$this->assertFalse(str_contains($html, 'href="/formation/'.$eCourse['id'].'?customize=1"'), 'l\'entrée s\'efface en mode');

		$html = $this->renderRoute('formation/lecon/'.$eLesson['id'], ['customize' => '1'], 'learning/lesson');

		$this->assertTrue(str_contains($html, 'data-url="/formation/'.$eCourse['id'].'/:doCustomize" data-preview="/formation/lecon/'.$eLesson['id'].'/:doPreview" data-exit="/formation/lecon/'.$eLesson['id'].'"'), 'la barre de la leçon essaie sur la leçon et enregistre sur la formation');

		foreach($pages as $page => [$path]) {

			$data = $this->executeView($path, ['customize' => '1', 'color' => self::GREEN]);

			$this->assertTrue($data->customize, $page.' en mode pour le gérant');
			$this->assertEquals(self::GREEN, $data->settings['color'], $page.' montre la couleur essayée');

		}

		foreach($pages as $page => [$path]) {
			$this->assertEquals(\RedirectAction::class, $this->executeView($path, ['customize' => '1', 'color' => '#7A7A7A']), $page.' renvoie à la barre sur une couleur illisible écrite à la main');
		}

	}

	/**
	 * Les trois routes de la barre jugent le droit sur le réseau de la formation : le conseiller, qui
	 * écrit la formation, et le lecteur sont refusés partout, enregistrement compris.
	 */
	public function testRoutesAreReservedToTheManager(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedNetwork();

		$input = ['customDesign' => '', 'color' => self::GREEN, 'customBackground' => '', 'customTitleFont' => '', 'customFont' => ''];

		$routes = [
			'formation/'.$eCourse['id'].'/:doPreview',
			'formation/lecon/'.$eLesson['id'].'/:doPreview',
			'formation/'.$eCourse['id'].'/:doCustomize',
		];

		foreach([
			'le lecteur' => $this->eUserReader,
			'le conseiller' => $this->eUserAdmin,
		] as $who => $eUser) {

			$this->actAs($eUser);

			foreach($routes as $route) {
				$this->assertEquals(\NotAllowedAction::class, $this->executePage($route, $input)[0], $route.' refusé pour '.$who);
			}

		}

		$this->assertEquals(self::CREAM, $this->getNetworkCommunity()['customBackground'], 'rien n\'est écrit');

		$this->becomeManager();

		$this->assertEquals(\RedirectAction::class, $this->executePage($routes[0], $input, redirect: $redirect)[0], 'l\'essai sur la fiche');
		$this->assertEquals('/formation/'.$eCourse['id'].'?'.http_build_query(['customize' => 1] + $input), $redirect, 'renvoie à la fiche avec tous les réglages');

		$this->assertEquals(\RedirectAction::class, $this->executePage($routes[1], $input, redirect: $redirect)[0], 'l\'essai sur la leçon');
		$this->assertEquals('/formation/lecon/'.$eLesson['id'].'?'.http_build_query(['customize' => 1] + $input), $redirect, 'renvoie à la leçon avec tous les réglages');

		$this->assertEquals([\FailAction::class, 'Community::color.contrast'], array_map(fn(?string $value) => $value === NULL ? NULL : explode(',', $value)[0], $this->executePage($routes[0], ['color' => '#7A7A7A'] + $input)), 'un essai refusé remonte par la chaîne d\'erreur');

		$this->forgetMemoization();

		$this->assertEquals(self::CREAM, $this->getNetworkCommunity()['customBackground'], 'l\'essai n\'écrit rien');

		$this->assertEquals(\ReloadAction::class, $this->executePage($routes[2], $input)[0], 'l\'enregistrement par le gérant');

		$this->forgetMemoization();

		$this->assertEquals([self::GREEN, NULL], array_values($this->getNetworkCommunity()->extracts(['color', 'customBackground'])), 'écrit sur le réseau de la formation');

	}

	/**
	 * Une formation de l'équipe n'a pas de réseau dont régler le thème : les trois routes n'existent
	 * pas pour elle.
	 */
	public function testRoutesRejectTeamCourse(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eLesson = $this->createLessonWritten($this->createChapter($eCourse, 'Module'), 'Leçon', 5);

		foreach([
			'formation/'.$eCourse['id'].'/:doPreview',
			'formation/lecon/'.$eLesson['id'].'/:doPreview',
			'formation/'.$eCourse['id'].'/:doCustomize',
		] as $route) {
			$this->assertEquals(\NotExistsAction::class, $this->executePage($route, ['color' => self::GREEN])[0], $route.' n\'existe pas pour une formation de l\'équipe');
		}

		$data = $this->executeView('formation/'.$eCourse['id'], ['customize' => '1']);

		$this->assertFalse($data->customize, 'ni le mode sur sa fiche');

	}

	/**
	 * Le gérant en mode sur la fiche et la leçon : le réseau garde ses réglages enregistrés, et la
	 * leçon ne tient pas le journal de lecture, qu'elle tient hors du mode pour la même inscription.
	 */
	public function testModeWritesNothing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedNetwork();

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserAdmin, 'course' => $eCourse, 'structure' => $this->eStructureFarm]));

		$this->becomeManager();

		$before = $this->getNetworkCommunity()->extracts(CommunityLib::getPropertiesCustomize());

		$query = ['customize' => '1', 'color' => self::GREEN, 'customBackground' => '', 'customTitleFont' => self::LOBSTER];

		$this->assertTrue($this->executeView('formation/'.$eCourse['id'], $query)->customize, 'la fiche en mode');
		$this->assertTrue($this->executeView('formation/lecon/'.$eLesson['id'], $query)->customize, 'la leçon en mode');

		$this->forgetMemoization();

		$this->assertEquals($before, $this->getNetworkCommunity()->extracts(CommunityLib::getPropertiesCustomize()), 'le réseau garde ses réglages');
		$this->assertEquals(0, $this->countReadings($eLesson), 'la leçon en mode ne tient pas le journal');

		$data = $this->executeView('formation/lecon/'.$eLesson['id'], []);

		$this->assertFalse($data->customize, 'la leçon hors du mode');
		$this->assertEquals(1, $this->countReadings($eLesson), 'qui tient le journal de la même inscription');

	}

	/**
	 * Le gérant en mode sur la liste du réseau suit une date, qui le renvoie à la fiche, puis une
	 * leçon du menu : le mode et les réglages essayés passent chaque lien et la redirection, une
	 * police à guillemets et virgule et une couleur à `#` compris.
	 */
	public function testModeFollowsListToCourseToLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedNetwork();
		$eDate = $this->createDate($eCourse, 10);

		$this->becomeManager();

		$fqn = $this->getNetworkCommunity()['fqn'];

		$html = $this->renderRoute('reseau/'.$fqn.'/formations', ['customize' => '1', 'color' => self::GREEN, 'customTitleFont' => self::LOBSTER]);

		$query = $this->getLinkQuery($html, '/formations/'.$eDate['id']);

		if($this->assertDecorPresent($query !== NULL, 'le lien de la date '.$eDate['id'].' sur la liste du réseau') === FALSE) {
			return;
		}

		$this->assertEquals('1', $query['customize'] ?? NULL, 'le lien de la date garde le mode');
		$this->assertEquals(self::GREEN, $query['color'] ?? NULL, 'et la couleur');
		$this->assertEquals(self::LOBSTER, $query['customTitleFont'] ?? NULL, 'et la police');

		[$action] = $this->executePage('reseau/'.$fqn.'/formations/'.$eDate['id'], $query, 'GET', $redirect);

		$this->assertEquals(\RedirectAction::class, $action, 'la date renvoie le gérant à la fiche');

		$this->assertEquals(\Lime::getUrl().'/formation/'.$eCourse['id'], strtok($redirect, '?'), 'la fiche de la formation');

		parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);

		$this->assertEquals(self::GREEN, $query['color'] ?? NULL, 'la redirection garde la couleur');
		$this->assertEquals(self::LOBSTER, $query['customTitleFont'] ?? NULL, 'et la police');

		$html = $this->renderRoute('formation/'.$eCourse['id'], $query, 'learning/course');

		$this->assertTrue(str_contains($html, 'learning-customize-bar'), 'la fiche porte la barre');

		$query = $this->getLinkQuery($html, '/formation/lecon/'.$eLesson['id']);

		if($this->assertDecorPresent($query !== NULL, 'le lien de la leçon '.$eLesson['id'].' sur la fiche') === FALSE) {
			return;
		}

		$data = $this->executeView('formation/lecon/'.$eLesson['id'], $query);

		$this->assertTrue($data->customize, 'la leçon en mode');
		$this->assertEquals(self::GREEN, $data->settings['color'], 'avec la couleur essayée sur la liste');
		$this->assertEquals(self::LOBSTER, $data->settings['customTitleFont'], 'et la police');

	}

	/**
	 * Le gérant en mode répond au quiz d'une leçon, par le formulaire que la leçon rend : la section
	 * et la colonne que `response.v.php` redessinent gardent le mode et la couleur essayée, après un
	 * échec (« Nouvelle tentative »), après la nouvelle tentative (le formulaire) et après la
	 * réussite (la colonne). Hors mode, rien n'en passe, et un réglage refusé
	 * écrit à la main est rejeté avant que la réponse s'écrive.
	 */
	public function testQuizKeepsTheMode(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$this->becomeManager();

		EnrollmentLib::create(new Enrollment(['user' => $this->eUserAdmin, 'course' => $eCourse, 'structure' => new \structure\Structure()]));

		$this->assertEquals(['lesson' => (string)$eLesson['id']], $this->getQuizHiddens($this->renderRoute('formation/lecon/'.$eLesson['id'], view: 'learning/lesson')), 'hors mode, le formulaire ne poste que la leçon');

		$posted = $this->getQuizHiddens($this->renderRoute('formation/lecon/'.$eLesson['id'], ['customize' => '1', 'color' => self::GREEN], 'learning/lesson'));

		$this->assertEquals('1', $posted['customize'] ?? NULL, 'en mode, le formulaire poste le mode');
		$this->assertEquals(self::GREEN, $posted['color'] ?? NULL, 'et la couleur essayée');

		$responses = $this->countResponses($eLesson);

		$refused = $this->executeView('learning/response:doAnswer', ['color' => 'rouge'] + $posted + ['answer' => $this->getAnswers($eLesson, 'Choix faux')], 'POST');

		$this->assertEquals(\NotExpectedAction::class, $refused, 'une couleur écrite à la main est refusée');
		$this->assertEquals($responses, $this->countResponses($eLesson), 'avant que la réponse s\'écrive');

		$quiz = $this->renderQuiz($this->executeView('learning/response:doAnswer', $posted + ['answer' => $this->getAnswers($eLesson, 'Choix faux')], 'POST'));

		$query = (preg_match('/data-ajax="\/learning\/response:retry\?([^"]*)"/', $quiz, $match) === 1)
			? $this->parseQuery($match[1])
			: [];

		$this->assertEquals((string)$eLesson['id'], $query['lesson'] ?? NULL, 'après un échec, « Nouvelle tentative » vise la leçon');
		$this->assertEquals('1', $query['customize'] ?? NULL, 'et garde le mode');
		$this->assertEquals(self::GREEN, $query['color'] ?? NULL, 'et la couleur');

		$this->assertFalse(str_contains($this->renderQuiz($this->executeView('learning/response:retry', ['lesson' => (string)$eLesson['id']])), 'customize'), 'hors mode, le quiz vierge n\'en porte rien');

		$posted = $this->getQuizHiddens($this->renderQuiz($this->executeView('learning/response:retry', $query)));

		$this->assertEquals(self::GREEN, $posted['color'] ?? NULL, 'le quiz vierge de la nouvelle tentative reposte la couleur');

		$data = $this->executeView('learning/response:doAnswer', $posted + ['answer' => $this->getAnswers($eLesson, 'Choix juste')], 'POST');

		$this->assertTrue($data->passed, 'la leçon réussie');

		$side = new LessonUi()->getSide($data->eLesson, $data->eCourse, $data->cChapter, $data->cProgress, $data->eEnrollment);
		$lesson = $this->getLinkQuery($side, '/formation/lecon/'.$eLesson['id']);

		$this->assertEquals('1', $lesson['customize'] ?? NULL, 'après la réussite, la colonne redessinée garde le mode');
		$this->assertEquals(self::GREEN, $lesson['color'] ?? NULL, 'et la couleur');

	}

	/**
	 * « Voir les dates », sur le quiz fermé d'une formation datée, garde le mode comme les autres
	 * liens de la leçon. Le gérant, membre de l'équipe du réseau, voit le quiz ouvert : l'état fermé
	 * se pose donc sur la leçon, et l'écran se rend seul.
	 */
	public function testClosedQuizKeepsTheMode(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse, $eLesson] = $this->seedCourse();

		$eLesson['quiz'] = ResponseLib::QUIZ_RESERVED;
		$eLesson['eDateConfirmed'] = new Date();
		$eLesson['course']['customizeQuery'] = CustomizeUi::getQuery(['color' => self::GREEN]);

		$html = new QuizUi()->get($eLesson, new Enrollment(), new \Collection(), new \Collection(), TRUE);

		$query = $this->getLinkQuery($html, CourseUi::url($eCourse));

		if($this->assertDecorPresent($query !== NULL, '« Voir les dates » sur le quiz fermé') === FALSE) {
			return;
		}

		$this->assertEquals('1', $query['customize'] ?? NULL, '« Voir les dates » garde le mode');
		$this->assertEquals(self::GREEN, $query['color'] ?? NULL, 'et la couleur');

		$eLesson['course']['customizeQuery'] = [];

		$html = new QuizUi()->get($eLesson, new Enrollment(), new \Collection(), new \Collection(), TRUE);

		$this->assertTrue(str_contains($html, 'href="'.CourseUi::url($eCourse).'"'), 'hors mode, la fiche nue');

	}

	/**
	 * Une formation du réseau ouverte à la ferme du lecteur, un module et une leçon, sur le thème
	 * enregistré du réseau : un fond, aucune couleur.
	 *
	 * @return array{0: Course, 1: Lesson}
	 */
	private function seedNetwork(): array {

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		// Le lecteur lit la formation datée par sa date confirmée : l'ouverture aux membres ne donne
		// pas la lecture.
		$this->enrollConfirmed($this->createDate($eCourse, 4), $this->eUserReader);

		$fw = new \FailWatch();

		CommunityLib::updateCustomize($this->eStructureNetwork, ['customDesign' => '', 'color' => '', 'customBackground' => self::CREAM, 'customTitleFont' => '', 'customFont' => '']);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglages refusés : '.json_encode($fw->get())));

		$this->forgetMemoization();

		$eLesson = LessonLib::getByCourse($eCourse)->first();

		return [$this->getCourse($eCourse['id']), $eLesson];

	}

	private function getNetworkCommunity(): \structure\Community {
		return \structure\CommunityLib::getByStructure($this->eStructureNetwork);
	}

	private function countReadings(Lesson $eLesson): int {
		return Reading::model()
			->whereLesson($eLesson)
			->whereUser($this->eUserAdmin)
			->count();
	}

	/**
	 * La requête du premier lien de `$html` qui mène à `$path`, lue comme le navigateur la lit, ou
	 * `NULL` sans lien.
	 */
	private function getLinkQuery(string $html, string $path): ?array {

		if(preg_match('/href="([^"]*'.preg_quote($path, '/').'(\?[^"#]*)?)"/', $html, $match) === 0) {
			return NULL;
		}

		parse_str(parse_url(html_entity_decode($match[1]), PHP_URL_QUERY) ?? '', $query);

		return $query;

	}

	/**
	 * Les champs cachés du formulaire du quiz, tels que le navigateur les posterait.
	 */
	private function getQuizHiddens(string $html): array {

		$form = strstr($html, 'response:doAnswer');

		if($form === FALSE) {
			return [];
		}

		preg_match_all('/<input[^>]*type="hidden"[^>]*>/', strstr($form, '</form>', TRUE), $inputs);

		$hiddens = [];

		foreach($inputs[0] as $input) {
			preg_match('/name="([^"]*)"/', $input, $name);
			preg_match('/value="([^"]*)"/', $input, $value);
			$hiddens[$name[1]] = html_entity_decode($value[1] ?? '', ENT_QUOTES);
		}

		return $hiddens;

	}

	/**
	 * La section du quiz telle que `response.v.php` la redessine, d'après ce que la route prépare.
	 */
	private function renderQuiz(\stdClass|string $data): string {

		if(is_string($data)) {
			throw new \Exception('le quiz lève '.$data);
		}

		return new QuizUi()->get($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, TRUE);

	}

	/**
	 * Une réponse par question du quiz, le choix au libellé `$label`.
	 */
	private function getAnswers(Lesson $eLesson, string $label): array {

		$answers = [];

		foreach(QuestionLib::getByLesson($eLesson) as $eQuestion) {
			$answers[$eQuestion['id']] = [(string)$eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['label'] === $label)->first()['id']];
		}

		return $answers;

	}

	private function countResponses(Lesson $eLesson): int {
		return Response::model()
			->whereUser($this->eUserAdmin)
			->whereQuestion('IN', QuestionLib::getByLesson($eLesson))
			->count();
	}

	private function parseQuery(string $query): array {

		parse_str(html_entity_decode($query), $values);

		return $values;

	}

	/**
	 * L'utilisateur 1, conseiller du réseau dans le décor, en devient administrateur dans la
	 * transaction du test.
	 */
	private function becomeManager(): void {

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNetwork);
		$eMember['role'] = \structure\RoleLib::getOne($this->eStructureNetwork, 'admin');

		\structure\MemberLib::update($eMember, ['role']);

		$this->forgetMemoization();
		$this->actAs($this->eUserAdmin);

	}

}
?>
