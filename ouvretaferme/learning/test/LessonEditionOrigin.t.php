<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * L'onglet de retour du panneau « Modifier la leçon », et de celui d'une question. L'écran d'une leçon s'ouvre toujours sur son
 * contenu : enregistré depuis les fichiers ou le quiz, le panneau ramène donc sur l'onglet d'où il a
 * été ouvert, par l'adresse (`?tab=`). L'origine voyage dans le lien (`origin`, que la rangée des
 * onglets y écrit, cf. `LessonEditionTabs`), puis dans le formulaire, puis dans l'action.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test : le groupe de
 * formation G2 (`COMMUNITY_EDUCATION`), que l'utilisateur 1 (`USER_ADMIN`) administre. La
 * formation et sa leçon s'écrivent dans la transaction.
 *
 * Lancement : dev/test package=learning file=LessonEditionOrigin
 */
class LessonEditionOriginTest extends LearningFixtureTest {

	/**
	 * Le formulaire reposte l'origine que le lien lui a donnée, et rien quand le lien n'en porte pas :
	 * la roue d'une leçon dans le programme d'une formation ouvre le même panneau.
	 */
	public function testForm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonInCourse();

		$data = $this->executeView('learning/lesson:updateSettings', ['id' => $eLesson['id'], 'origin' => 'quiz']);
		$this->assertTrue($data instanceof \stdClass, 'le panneau se prépare : '.(is_string($data) ? $data : ''));

		// `executeView()` rend `$_GET` à son état : le panneau se rend ici comme la vue le rend, dans la requête qui porte l'origine.
		$getInitial = $_GET;

		$_GET = ['origin' => 'quiz'];
		$body = new LessonUi()->updateSettings($data->e)->body;
		$this->assertEquals(1, preg_match('/<input [^>]*name="origin" type="hidden" value="quiz"\/>/', $body), 'l\'origine du lien repostée');

		$_GET = [];
		$body = new LessonUi()->updateSettings($data->e)->body;
		$this->assertEquals(1, preg_match('/<input [^>]*name="origin" type="hidden" value=""\/>/', $body), 'sans origine dans le lien, le champ part vide');

		$_GET = $getInitial;

	}

	/**
	 * Depuis les fichiers ou le quiz, l'enregistrement mène à l'écran sur cet onglet, avec son message.
	 * Depuis le contenu, ou sans origine, il recharge l'écran où le panneau a été ouvert. Une origine
	 * inconnue ne choisit rien et ne refuse rien : la leçon est écrite quand elle se lit.
	 */
	public function testReturn(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonInCourse();

		$settings = [
			'id' => (string)$eLesson['id'],
			'chapter' => (string)$eLesson['chapter']['id'],
			'name' => 'zzTest leçon du retour',
			'minutes' => '10',
			'validation' => Lesson::DECLARED,
			'quizThreshold' => ''
		];

		$edition = LessonUi::urlEdition($eLesson);

		foreach(['attachments', 'quiz'] as $origin) {

			$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['origin' => $origin] + $settings, redirect: $url), 'depuis '.$origin);
			$this->assertEquals($edition.'?tab='.$origin.'&success=learning\\Lesson::updated', $url, 'retour sur '.$origin.', avec le message');

		}

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['origin' => 'content'] + $settings), 'depuis le contenu, l\'écran se recharge');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['origin' => ''] + $settings), 'sans origine, l\'écran se recharge');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', $settings), 'sans le champ non plus');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/lesson:doUpdateSettings', ['origin' => 'zzInconnue', 'name' => 'zzTest leçon renommée'] + $settings), 'une origine inconnue ne refuse rien');
		$this->assertEquals('zzTest leçon renommée', $this->getLesson($eLesson['id'])['name'], 'et la leçon est écrite');

	}

	/**
	 * Le panneau d'une question, ouvert par-dessus l'écran de la leçon, n'y touche qu'à la section du
	 * quiz. Ouvert comme une page, il n'a rien derrière lui, et le fermer reviendrait à l'adresse que
	 * `Lime.Tab.restore()` a dépouillée de son onglet, donc au contenu de la leçon (retour de
	 * V-Learning du 05/10) : le formulaire poste alors l'onglet du quiz, et l'action y ramène.
	 */
	public function testQuestion(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eLesson = $this->createLessonInCourse();
		$edition = LessonUi::urlEdition($eLesson);

		$panel = function(string $page, array $input): string {

			$data = $this->executeView('learning/question:'.$page, $input);
			$this->assertTrue($data instanceof \stdClass, $page.' : le panneau se prépare ('.(is_string($data) ? $data : 'vue').')');

			return ($page === 'create') ? new QuestionUi()->create($data->e)->body : new QuestionUi()->update($data->e)->body;

		};

		// Le lanceur de tests ne joue pas une requête ajax, et une requête ajax jouée ici ouvrirait la
		// session du site (`PageLib::common()`) : le panneau se prépare donc comme une page, et le cas
		// ajax se lit dans la règle de la page.
		$this->assertTrue(\Route::getRequestedWith() !== 'ajax', 'le lanceur ne se dit pas ajax');
		$this->assertEquals(1, preg_match('/<input [^>]*name="origin" type="hidden" value="quiz"\/>/', $panel('create', ['lesson' => $eLesson['id']])), 'comme une page, l\'ajout poste l\'onglet du quiz');

		$source = file_get_contents(\Package::getFileFromUri('learning/question', 'page'));
		$this->assertTrue(str_contains($source, '$getOrigin = fn(): string => (Route::getRequestedWith() === \'ajax\') ? \'\' : \'quiz\';'), 'par-dessus l\'écran, en ajax, le panneau ne poste pas d\'onglet');
		$this->assertEquals(2, substr_count($source, '$data->e[\'origin\'] = $getOrigin();'), 'la même règle pour l\'ajout et la modification');

		$question = fn(string $statement, string $origin) => [
			'lesson' => (string)$eLesson['id'],
			'origin' => $origin,
			'statement' => $statement,
			'image' => '',
			'type' => Question::SINGLE,
			'explanation' => '',
			'choices' => ['label' => ['Oui', 'Non'], 'correct' => ['1', '0'], 'image' => ['', ''], 'id' => ['', '']],
		];

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/question:doCreate', $question('zzTest question par-dessus', '')), 'sans onglet posté, la section du quiz seule');

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/question:doCreate', $question('zzTest question en page', 'quiz'), redirect: $url), 'avec l\'onglet du quiz, l\'écran');
		$this->assertEquals($edition.'?tab=quiz&success=learning\\Question::created', $url, 'rouvert sur le quiz, avec le message');

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('learning/question:doCreate', $question('zzTest question forgée', 'zzInconnue')), 'une origine inconnue ne choisit rien');

		$eQuestion = QuestionLib::getByLesson($eLesson)->first();

		$this->assertEquals(1, preg_match('/<input [^>]*name="origin" type="hidden" value="quiz"\/>/', $panel('update', ['id' => $eQuestion['id']])), 'la modification, comme une page, poste aussi l\'onglet');

		$update = ['id' => (string)$eQuestion['id']] + $question('zzTest question modifiée', 'quiz');
		unset($update['lesson']);

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/question:doUpdate', $update, redirect: $url), 'modifiée comme une page');
		$this->assertEquals($edition.'?tab=quiz&success=learning\\Question::updated', $url, 'rouverte sur le quiz');

		$this->assertEquals(3, QuestionLib::countByLesson($eLesson), 'les trois ajouts écrits, quel que soit le retour');

	}

	private function createLessonInCourse(): Lesson {

		$eCourse = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::DRAFT, Course::PRIVATE);

		return $this->createLesson($this->createChapter($eCourse, 'zzTest module du retour'), 'zzTest leçon du retour', 10);

	}

}
