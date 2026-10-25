<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * Les formations en production, jouées en dev (lot ouverture-6659) : `LearningSetting::$active` à
 * faux, seules les structures de `LearningSetting::STRUCTURE_BETA` ouvertes. En dev, la liste porte le
 * réseau du décor (`COMMUNITY_NETWORK`) et celui des tutoriels ; le groupe de formation du décor
 * (`COMMUNITY_EDUCATION`) et tout réseau de la base hors du décor en sont absents.
 *
 * Le décor s'écrit avant la bascule, par les Lib, comme les autres tests du package : ce fichier
 * éprouve ce que voit et fait une structure ouverte ou fermée, pas la façon dont ses formations sont
 * nées. La bascule se fait par `simulateProduction()`, et `tearDown()` rend la valeur de départ.
 *
 * Lancement : dev/test package=learning file=ProductionAccess
 */
class ProductionAccessTest extends LearningReviewFixtureTest {

	private bool $activeInitial;

	public function setUp(): void {

		parent::setUp();

		$this->activeInitial = LearningSetting::$active;

	}

	public function tearDown(): void {

		LearningSetting::$active = $this->activeInitial;

		parent::tearDown();

	}

	/**
	 * La question posée à l'élément : ouverte pour une formation du réseau listé, fermée pour celle
	 * d'un groupe absent de la liste et pour une formation de l'équipe, qui suit l'ouverture à tous.
	 */
	public function testElement(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseNetwork = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);
		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->assertTrue($eCourseEducation->acceptRead(), 'en dev, tout est ouvert');

		$this->simulateProduction();

		$this->assertTrue($this->eStructureNetwork->acceptLearning(), 'le réseau listé est ouvert');
		$this->assertFalse($this->getCommunity(self::COMMUNITY_EDUCATION)->acceptLearning(), 'le groupe absent de la liste est fermé');

		$this->assertTrue($eCourseNetwork->acceptRead(), 'une formation du réseau listé se lit');
		$this->assertTrue($eCourseNetwork->acceptWrite(), 'et s\'écrit');

		$this->assertFalse($eCourseEducation->acceptRead(), 'une formation du groupe fermé ne se lit pas');
		$this->assertFalse($eCourseEducation->acceptWrite(), 'ni ne s\'écrit');

		$this->assertFalse($eCourseTeam->acceptRead(), 'une formation de l\'équipe ne se lit pas');
		$this->assertFalse($eCourseTeam->acceptWrite(), 'ni ne s\'écrit');

		$this->assertFalse($eCourseNetwork->acceptCatalog(), 'le catalogue public reste fermé, même au réseau listé');

	}

	/**
	 * L'équipe du réseau listé lit sa fiche, ouvre son éditeur et ses pages de groupe, écrit un
	 * module, et sa page publique répond.
	 */
	public function testListedReadsAndWrites(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$eLesson = $this->getLesson(LessonLib::getByCourse($eCourse)->first()['id']);

		$this->simulateProduction();

		$this->actAs($this->eUserAdmin);

		$this->assertTrue($this->executeView('formation/'.$eCourse['id'], []) instanceof \stdClass, 'la fiche se lit');
		$this->assertTrue($this->executeView('formation/lecon/'.$eLesson['id'], []) instanceof \stdClass, 'la leçon se lit');
		$this->assertTrue($this->executeView('formation/'.$eCourse['id'].'/edition', []) instanceof \stdClass, 'l\'éditeur s\'ouvre');
		$this->assertTrue($this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []) instanceof \stdClass, 'la liste du groupe s\'ouvre');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/chapter:doCreate', ['course' => (string)$eCourse['id'], 'name' => 'zzTest module ouvert']), 'un module s\'écrit');

		$this->assertTrue($this->executeView($this->getPublicPath($this->eStructureNetwork).'/formations', []) instanceof \stdClass, 'la page publique du réseau répond');

		$this->assertTrue(BlockOwnerLib::canRead($eLesson), 'les blocs de la leçon se lisent');

	}

	/**
	 * Une formation d'une structure absente de la liste n'existe pas : sa fiche et sa leçon, pour un
	 * membre comme pour un visiteur, et les pages du groupe. Les adresses d'action forgées sont
	 * refusées par l'état de l'élément, que la page valide.
	 */
	public function testAbsentStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$eCourse = $this->createCourse($eStructureEducation, Course::PUBLISHED, Course::PRIVATE);
		$eChapter = $this->createChapter($eCourse, 'zzTest module');
		$eLesson = $this->getLesson($this->createLesson($eChapter, 'zzTest leçon', 10)['id']);

		$eComment = new Comment(['lesson' => $eLesson]);
		$eComment['text'] = 'zzTest commentaire';
		CommentLib::create($eComment);

		$this->simulateProduction();

		$this->assertEquals(\NotExistsAction::class, $this->executeView('formation/'.$eCourse['id'], []), 'la fiche n\'existe pas pour l\'équipe du groupe');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('formation/lecon/'.$eLesson['id'], []), 'ni la leçon');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('groupe/'.$eStructureEducation['id'].'/formations', []), 'ni la liste du groupe');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('groupe/'.$eStructureEducation['id'].'/formations/commentaires', []), 'ni ses commentaires');

		$this->assertEquals(\NotExpectedAction::class, $this->executeView('formation/'.$eCourse['id'].'/edition', []), 'l\'éditeur forgé est refusé');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/chapter:doCreate', ['course' => (string)$eCourse['id'], 'name' => 'zzTest module fermé']), 'un module forgé est refusé');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/comment:doRead', ['id' => (string)$eComment['id']]), 'un commentaire ne se marque pas lu');
		$this->assertEquals(\NotExpectedAction::class, $this->executeView('learning/progress:mine', ['id' => (string)$eCourse['id']]), 'le journal du stagiaire ne s\'ouvre pas');

		$this->assertFalse(BlockOwnerLib::canRead($eLesson), 'les blocs de la leçon ne se lisent pas');
		$this->assertFalse(BlockOwnerLib::canWrite($eLesson), 'ni ne s\'écrivent');

		$this->actAs(new \user\User());

		$this->assertEquals(\NotExistsAction::class, $this->executeView('formation/'.$eCourse['id'], []), 'un visiteur n\'est pas renvoyé à la connexion');

	}

	/**
	 * Une formation de l'équipe n'existe pas en production, lien direct compris.
	 */
	public function testTeam(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->simulateProduction();

		$this->assertEquals(\NotExistsAction::class, $this->executeView('formation/'.$eCourse['id'], []), 'la fiche n\'existe pas');

	}

	/**
	 * Un autre réseau que celui de la liste : sa page publique, son intégration et sa racine ne
	 * parlent pas de formations, et les inscrits d'une de ses dates ne se gèrent pas.
	 */
	public function testAbsentNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eStructureOther = $this->createNetworkOther();
		$this->join($this->eUserAdmin, $eStructureOther);

		$eCourse = $this->createCourse($eStructureOther, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eDate = $this->createDate($eCourse, 10);
		$eDateEnrollment = $this->enrollRequested($eDate, $this->eUserReader);

		$this->simulateProduction();

		$this->assertEquals(\NotExistsAction::class, $this->executeView($this->getPublicPath($eStructureOther).'/formations', []), 'la page publique n\'existe pas');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('reseau/embed-learning.js', ['id' => (string)$eStructureOther['id'], 'target' => 'zztest']), 'ni son intégration');
		$this->assertEquals(\NotExpectedAction::class, $this->executeView('formation/date/'.$eDate['id'].'/inscrits', []), 'la liste des inscrits d\'une date forgée est refusée');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$eDateEnrollment['id']]), 'une demande forgée ne se confirme pas');

		$data = $this->executeView($this->getPublicPath($eStructureOther), []);
		$this->assertFalse(($data instanceof \stdClass) and isset($data->cDate), 'la racine du réseau ne lit pas ses dates');

	}

	/**
	 * Les deux crons balaient toute la base : en production, ils ne retiennent que les dates des
	 * structures ouvertes. Une date de demain et une date finie hier, sur le réseau listé et sur un
	 * autre réseau, toutes deux au catalogue pour que l'invitation à l'avis ne les écarte pas pour une
	 * autre raison.
	 */
	public function testCrons(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$this->actAs($this->eUserAdmin);

		[$eCourseListed] = $this->seedCatalog();

		$eStructureOther = $this->createNetworkOther();
		$this->join($this->eUserAdmin, $eStructureOther);

		$eCourseOther = $this->createCourse($eStructureOther, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eCourseOther['catalog'] = Course::ACCEPTED;
		CourseLib::update($eCourseOther, ['catalog']);

		$remind = [
			'réseau listé' => $this->age($this->enrollConfirmed($this->createDate($eCourseListed, 1), $this->createPerson())),
			'autre réseau' => $this->age($this->enrollConfirmed($this->createDate($eCourseOther, 1), $this->createPerson())),
		];

		$review = [
			'réseau listé' => $this->followDate($eCourseListed, $this->createPerson(), -1),
			'autre réseau' => $this->followDate($eCourseOther, $this->createPerson(), -1),
		];

		$this->assertEquals(2, count(array_intersect(DateEnrollmentLib::getRemindable()->getIds(), [$remind['réseau listé']['id'], $remind['autre réseau']['id']])), 'en dev, le rappel retient les deux dates');

		$this->simulateProduction();

		$this->assertTrue(LearningSetting::isActiveAnywhere(), 'les crons tournent dès qu\'une structure est ouverte');

		$remindable = DateEnrollmentLib::getRemindable()->getIds();
		$this->assertTrue(in_array($remind['réseau listé']['id'], $remindable), 'le rappel retient la date du réseau listé');
		$this->assertFalse(in_array($remind['autre réseau']['id'], $remindable), 'et pas celle de l\'autre réseau');

		$cDateEnrollment = new \Collection();

		foreach($review as $eDateEnrollment) {
			$cDateEnrollment[$eDateEnrollment['id']] = $eDateEnrollment;
		}

		$this->assertEquals([$review['réseau listé']['id']], DateEnrollmentLib::getReviewableAmong($cDateEnrollment)->getIds(), 'l\'invitation à l\'avis ne retient que la date du réseau listé');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail enregistré');

	}

	/**
	 * L'API des formations déclare ses routes même quand les formations ne sont pas ouvertes à tous :
	 * c'est la structure de la clé qui décide (`Course_0_1betaApi::validateStructure()`, éprouvée
	 * de bout en bout par `TutorialApi.t.php`). Les routes sont lues à vide, puis rendues telles
	 * qu'elles étaient.
	 */
	public function testApi(): void {

		$property = new \ReflectionProperty(\Api::class, 'routes');
		$routes = $property->getValue();

		$this->simulateProduction();

		$property->setValue(NULL, []);

		try {

			foreach(['Chapter', 'Course', 'Date', 'DateEnrollment', 'Lesson'] as $module) {
				$class = __NAMESPACE__.'\\'.$module.'_0_1betaApi';
				new $class();
			}

			$declared = array_merge(...array_values(array_map(fn(array $routes) => array_column($routes, 'class'), $property->getValue())));

			foreach(['Chapter', 'Course', 'Date', 'DateEnrollment', 'Lesson'] as $module) {
				$this->assertTrue(in_array(__NAMESPACE__.'\\'.$module.'_0_1betaApi', $declared, TRUE), $module.' déclare ses routes');
			}

		} finally {
			$property->setValue(NULL, $routes);
		}

	}

	/**
	 * « Mes formations » s'ouvre dès qu'une structure est ouverte, et ne liste que ce qui se lit.
	 */
	public function testMine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->simulateProduction();

		$this->actAs($this->eUserAdmin);

		$data = $this->executeView('mes-formations', []);

		$this->assertTrue($data instanceof \stdClass, 'la page s\'ouvre');
		$closed = fn(Course $eCourse) => ($eCourse->acceptLearning() === FALSE);

		$this->assertEquals([], $data->cCourseRunning->find($closed)->getIds(), 'aucune formation à suivre d\'une structure fermée');
		$this->assertEquals([], $data->cCourseDone->find($closed)->getIds(), 'ni suivie');

	}

	/**
	 * « Mes formations » paraît au menu du compte d'un membre du réseau listé et d'un membre d'une
	 * ferme qui lui est rattachée, pas à celui d'un compte sans structure ni d'un membre du seul
	 * groupe fermé.
	 */
	public function testMenu(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eUserAlone = $this->createUser('zztest.menu-seul@example.org');

		$eUserEducation = $this->createUser('zztest.menu-groupe@example.org');
		$this->join($eUserEducation, $this->getCommunity(self::COMMUNITY_EDUCATION));

		$this->simulateProduction();

		$isOpen = fn(\user\User $eUser) => CourseLib::isOpenByStructures(\structure\StructureLib::getByUser($eUser, index: ['type', 'id']));

		$this->assertTrue($isOpen($this->eUserAdmin), 'membre du réseau listé');
		$this->assertTrue($isOpen($this->eUserReader), 'membre d\'une ferme rattachée au réseau listé');
		$this->assertFalse($isOpen($eUserAlone), 'compte sans structure');
		$this->assertFalse($isOpen($eUserEducation), 'membre du seul groupe fermé');

		LearningSetting::$active = TRUE;

		$this->assertTrue($isOpen($eUserAlone), 'ouvert à tous, le menu paraît partout');

	}

	/**
	 * L'observateur de connexion cherche par adresse à chaque connexion du parc
	 * (`DateEnrollmentLib::attachByUser()`) : la requête lit l'index unique `(email, date)`, dont
	 * l'adresse est la première colonne, jamais la table entière.
	 */
	public function testEmailIndex(): void {

		$sql = new DateEnrollmentModel()
			->select(['id', 'date', 'status'])
			->whereEmail('zztest.index@example.org')
			->whereUser(NULL)
			->getSql();

		$rows = DateEnrollment::model()->pdo()->query('EXPLAIN '.$sql)->fetchAll(\PDO::FETCH_ASSOC);

		// Le générateur nomme les index par leur rang : celui qui commence par `email` se lit dans la base.
		$index = DateEnrollment::model()->pdo()->query('SHOW INDEX FROM '.DateEnrollment::model()->getDatabase().'.'.DateEnrollment::model()->getTable().' WHERE Column_name = \'email\' AND Seq_in_index = 1')->fetch(\PDO::FETCH_ASSOC)['Key_name'];

		$this->assertEquals('ref', $rows[0]['type'], 'accès par index : '.json_encode($rows));
		$this->assertEquals($index, $rows[0]['key'], 'l\'index (email, date) : '.json_encode($rows));

	}

	/**
	 * Les deux offres payantes restent fermées tant que les formations ne sont pas ouvertes à tous,
	 * sur une formation en ligne comme sur une date. « Hors catalogue public » passe.
	 */
	public function testOffer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		$this->actAs($this->eUserAdmin);

		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONLINE);
		$eCourseOnsite = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);
		$eDate = $this->createDate($eCourseOnsite, 10);

		$this->simulateProduction();

		// Le panneau de l'offre d'une formation en ligne est fermé avec le catalogue public
		// (`Course::acceptUpdateOffer()`) : l'étape se juge sur le `build()` que ferait son POST.
		$this->assertEquals(\NotExpectedAction::class, $this->executeView('learning/course:updateOffer', ['id' => (string)$eCourseOnline['id']]), 'le panneau de l\'offre d\'une formation en ligne est fermé');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/course:doUpdateOffer', ['id' => (string)$eCourseOnline['id'], 'offer' => Course::NETWORK]), 'et son POST refusé');

		$eCourseOnline = $this->getCourse($eCourseOnline['id']);

		foreach([Course::NETWORK => ['Course::offer.paid'], Course::TRAINEE => ['Course::offer.paid'], Course::NONE => []] as $offer => $expected) {

			$fw = new \FailWatch();
			$eCourseOnline->build(['offer'], ['offer' => $offer]);

			$this->assertEquals($expected, array_column($this->getFails($fw), 0), 'formation en ligne, offre '.$offer);

		}

		$this->assertEquals([\FailAction::class, 'Date::offer.paid'], $this->executePage('learning/date:doUpdateOffer', ['id' => (string)$eDate['id'], 'offer' => Date::NETWORK]), 'date payée par le réseau, refusée');
		$this->assertEquals([\FailAction::class, 'Date::offer.paid'], $this->executePage('learning/date:doUpdateOffer', ['id' => (string)$eDate['id'], 'offer' => Date::TRAINEE]), 'payée par le stagiaire, refusée');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doUpdateOffer', ['id' => (string)$eDate['id'], 'offer' => Date::NONE]), 'sans offre, enregistrée');

		$eCourseOnline['hasPayoutAccount'] = TRUE;

		$html = CourseUi::getOfferField(new \util\FormUi(), Course::NONE, $eCourseOnline, FALSE, $eCourseOnline);

		$this->assertTrue($this->isLocked($html, Course::NETWORK), 'à l\'écran, l\'offre payée par le réseau est fermée');
		$this->assertTrue($this->isLocked($html, Course::TRAINEE), 'et celle payée par le stagiaire');
		$this->assertFalse($this->isLocked($html, Course::NONE), 'pas « Hors catalogue public »');

		$data = $this->executeView('learning/date:updateOffer', ['id' => (string)$eDate['id']]);
		$html = new DateUi()->updateOffer($data->e)->body;

		$this->assertTrue($this->isLocked($html, Date::NETWORK), 'sur une date aussi');
		$this->assertTrue($this->isLocked($html, Date::TRAINEE), 'pour les deux offres payantes');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail enregistré');

	}

	private function isLocked(string $html, string $offer): bool {

		$this->assertEquals(1, preg_match('/<input[^>]*value="'.$offer.'"[^>]*>/', $html, $match), 'le panneau porte l\'offre '.$offer);

		return str_contains($match[0] ?? '', 'disabled');

	}

	/**
	 * La liste de production ne se lit pas en dev, où la constante prend sa branche de dev : elle se
	 * lit dans le source. Chaque structure listée doit être un réseau ou un groupe de formation de la
	 * copie de production, seuls à écrire des formations (`Course::acceptWrite()`) : une ferme listée
	 * n'ouvrirait rien. Une structure créée après la dernière copie n'y est pas et ne se juge pas :
	 * 6659 est dans ce cas le 2026-09-29, et c'est Vincent qui a dit que c'est un réseau.
	 */
	public function testProductionList(): void {

		$source = file_get_contents(__DIR__.'/../Learning.s.php');

		$this->assertEquals(1, preg_match('/const STRUCTURE_BETA = \(LIME_ENV === \'dev\'\) \? \[[^\]]*\] : \[([0-9, ]*)\];/', $source, $match), 'la déclaration de la liste se lit');

		$structures = array_map('intval', array_filter(array_map('trim', explode(',', $match[1] ?? ''))));

		$this->assertTrue($structures !== [], 'la liste de production porte une structure');

		// La constante prend sa branche de dev ici : c'est la liste lue dans le source que le réseau des
		// tutoriels doit rejoindre, sans quoi ses formations resteraient fermées en production.
		$this->assertTrue(in_array(LearningSetting::$structureTutorial, $structures, TRUE), 'le réseau des tutoriels est dans la liste de production');

		foreach($structures as $structure) {

			$eStructure = \structure\StructureLib::getById($structure);

			if($eStructure->empty()) {
				continue;
			}

			$this->assertTrue($eStructure->isCommunity(), 'la structure '.$structure.' est un réseau ou un groupe de formation dans la copie de production');

		}

	}

	private function simulateProduction(): void {
		LearningSetting::$active = FALSE;
	}

	/**
	 * La confirmation reculée de deux jours, comme dans `DateEnrollmentRemind.t.php` : le rappel ne
	 * suit pas une confirmation de moins de 24 heures. Écrite hors du Lib, qui n'a pas de raison de
	 * dater une confirmation dans le passé.
	 */
	private function age(DateEnrollment $eDateEnrollment): DateEnrollment {

		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['confirmedAt' => new \Sql('NOW() - INTERVAL 2 DAY')]);

		return $this->getLine($eDateEnrollment['id']);

	}

	private function getPublicPath(\structure\Structure $eStructure): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($eStructure)['fqn'];
	}

	/**
	 * Un réseau avec une adresse, autre que celui de la liste, monté dans la transaction du test : le
	 * premier réseau venu de la base ne garantissait ni d'exister, ni de rester hors de la liste.
	 */
	private function createNetworkOther(): \structure\Structure {

		$eStructure = new \structure\Structure([
			'name' => 'zzTest autre réseau',
			'type' => \structure\Structure::COMMUNITY,
			'communityType' => \structure\Structure::NETWORK,
			'legalEmail' => 'zztest.autre-reseau@example.org',
		]);

		// Hors de StructureLib::create(), dont le CREATE DATABASE validerait la transaction du test et
		// laisserait le réseau en base : build() n'aurait vérifié que le nom et l'adresse, posés ici en dur.
		\structure\Structure::model()->insert($eStructure);

		// Ses étapes que lisent les écrans du réseau : réglages, rôles et adresse.
		\structure\FeatureLib::createForStructure($eStructure);
		\structure\RoleLib::createForStructure($eStructure);
		\structure\CommunityLib::createForStructure($eStructure);

		return $this->getCommunity($eStructure['id']);

	}

}
?>
