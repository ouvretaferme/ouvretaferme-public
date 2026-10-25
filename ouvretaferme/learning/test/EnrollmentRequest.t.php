<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La demande d'inscription à une formation en ligne de réseau (sous-chantier personnes, lot C) : une
 * ligne `Enrollment::REQUESTED` que le réseau confirme ou annule depuis le suivi, et que la personne
 * retire depuis la fiche.
 *
 * Une demande ne donne ni la lecture ni un compte dans les chiffres : les accesseurs de
 * `EnrollmentLib` ne rendent que les confirmées, comptées au mois de `confirmedAt`. Les lecteurs
 * hors du Lib sont inventoriés par `EnrollmentReaderSentinel.t.php`.
 *
 * Le réglage du réseau (`Community.learningOnlineConfirm`) fait d'une inscription une demande :
 * toujours, jamais, ou quand la personne dit demander une prise en charge, la question n'étant posée
 * qu'alors (`testOnlineConfirm()` et suivants). Les autres tests écrivent leurs demandes en direct
 * (`request()`), pour les dater.
 *
 * Le décor est celui de `LearningNetworkFixture.t.php` : le réseau G3, dont l'utilisateur 1 est
 * conseiller et donc membre (lui seul reçoit un e-mail en dev), et le lecteur fictif, hors du
 * réseau.
 *
 * Lancement : dev/test package=learning file=EnrollmentRequest
 */
class EnrollmentRequestTest extends LearningNetworkFixtureTest {

	/**
	 * Une demande ne lit rien et ne compte nulle part : ni la fiche, ni les leçons, ni la liste des
	 * formations du compte, ni les lecteurs, ni le compte par mois de l'API, ni le suivi. Elle se lit
	 * par ses méthodes nommées.
	 */
	public function testRequestReadsNothing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$this->assertFalse(EnrollmentLib::isEnrolled($this->eUserReader, $eCourse), 'isEnrolled()');
		$this->assertTrue(EnrollmentLib::getByUserAndCourse($this->eUserReader, $eCourse)->empty(), 'getByUserAndCourse()');
		$this->assertFalse(EnrollmentLib::getByUser($this->eUserReader)->getColumnCollection('course', 'course')->offsetExists($eCourse['id']), 'getByUser()');
		$this->assertTrue(EnrollmentLib::getByCourseAndUsers($eCourse, new \Collection([$this->eUserReader]))->empty(), 'getByCourseAndUsers()');
		$this->assertTrue(EnrollmentLib::getLearners($eCourse)->empty(), 'getLearners()');
		$this->assertTrue(EnrollmentLib::getStructuresByCourse($eCourse)->empty(), 'getStructuresByCourse()');
		$this->assertEquals([], EnrollmentLib::countBySteps($eCourse, '2000-01-01', '2100-12-31', UsageLib::MONTH), 'countBySteps()');

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);
		EnrollmentLib::fillReaders($cCourse);
		$this->assertEquals(0, $eCourse['readers'], 'fillReaders()');

		$this->assertEquals($eEnrollment['id'], EnrollmentLib::getRequestByUserAndCourse($this->eUserReader, $eCourse)['id'], 'getRequestByUserAndCourse() la lit');
		$this->assertEquals([$eEnrollment['id']], EnrollmentLib::getRequestsByCourse($eCourse)->getIds(), 'getRequestsByCourse() la lit');
		$this->assertEquals([$this->eUserReader['id']], array_keys(EnrollmentLib::getOpenByCourseAndUsers($eCourse, new \Collection([$this->eUserReader]))->getArrayCopy()), 'getOpenByCourseAndUsers() la lit');

		$this->actAs($this->eUserReader);

		$eCourseRead = $this->getCourse($eCourse['id']);

		$this->assertFalse($eCourseRead->canRead(), 'la demande ne donne pas la lecture');
		$this->assertTrue($eCourseRead->isEnrollFirst(), 'la fiche reste celle d\'une formation où l\'on s\'inscrit d\'abord');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * Confirmer passe la ligne à `CONFIRMED` et la date, qui la compte au mois de la confirmation et
	 * non de la demande. Deux confirmations, d'un onglet puis d'un autre, n'écrivent qu'une fois : la
	 * seconde lève `Enrollment::changed`.
	 */
	public function testConfirm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$fw = new \FailWatch();

		EnrollmentLib::confirm($this->getEnrollment($eEnrollment['id']));

		$this->assertEquals([], $this->getFails($fw), 'confirmée sans erreur');

		$eEnrollmentConfirmed = $this->getEnrollment($eEnrollment['id']);

		$this->assertEquals(Enrollment::CONFIRMED, $eEnrollmentConfirmed['status'], 'confirmée');
		$this->assertEquals($this->getToday(), substr($eEnrollmentConfirmed['confirmedAt'], 0, 10), 'datée du jour');

		$this->assertTrue(EnrollmentLib::isEnrolled($this->eUserReader, $eCourse), 'la personne est inscrite');
		$this->assertEquals([UsageLib::getStepKey($this->getToday(), UsageLib::WEEK) => 1], EnrollmentLib::countBySteps($eCourse, $this->getToday(), $this->getToday(), UsageLib::WEEK), 'comptée dans la semaine de la confirmation');

		$fw = new \FailWatch();

		EnrollmentLib::confirm($this->getEnrollment($eEnrollment['id']));

		$this->assertEquals([['Enrollment::changed', NULL]], array_map(fn(array $fail) => [$fail[0], NULL], $this->getFails($fw)), 'la seconde confirmation lève changed');
		$this->assertEquals([UsageLib::getStepKey($this->getToday(), UsageLib::WEEK) => 1], EnrollmentLib::countBySteps($eCourse, $this->getToday(), $this->getToday(), UsageLib::WEEK), 'toujours comptée une fois');

	}

	/**
	 * Le réseau annule : la ligne passe à `CANCELED`, ne se confirme plus, et la personne s'inscrit de
	 * nouveau sur la même ligne, rouverte et datée du jour.
	 */
	public function testCancelByCommunityAndEnrollAgain(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		EnrollmentLib::cancelByCommunity($this->getEnrollment($eEnrollment['id']));

		$eEnrollmentCanceled = $this->getEnrollment($eEnrollment['id']);

		$this->assertEquals(Enrollment::CANCELED, $eEnrollmentCanceled['status'], 'annulée');
		$this->assertFalse($eEnrollmentCanceled->acceptConfirm(), 'une demande annulée ne se confirme plus');
		$this->assertFalse($eEnrollmentCanceled->acceptCancel(), 'ni ne s\'annule deux fois');
		$this->assertTrue(EnrollmentLib::getRequestByUserAndCourse($this->eUserReader, $eCourse)->empty(), 'la fiche n\'a plus de demande');
		$this->assertTrue(EnrollmentLib::getRequestsByCourse($eCourse)->empty(), 'le suivi non plus');

		EnrollmentLib::create(new Enrollment([
			'user' => $this->eUserReader,
			'course' => new Course(['id' => $eCourse['id']]),
			'structure' => $this->eStructureFarm
		]));

		$eEnrollmentAgain = $this->getEnrollment($eEnrollment['id']);

		$this->assertEquals(1, Enrollment::model()->whereCourse($eCourse)->count(), 'la même ligne');
		$this->assertEquals(Enrollment::CONFIRMED, $eEnrollmentAgain['status'], 'rouverte et confirmée');
		$this->assertTrue($eEnrollmentAgain['createdAt'] > '2026-08-14 10:00:00', 'datée du jour');

	}

	/**
	 * Les gestes par leurs pages : la personne retire sa seule demande, le réseau confirme et annule
	 * celles de ses formations, et une ligne qui n'est plus une demande ne se touche plus.
	 */
	public function testPages(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$eEnrollmentReader = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');
		$eEnrollmentAdmin = $this->request($eCourse, $this->eUserAdmin, new \structure\Structure(), '2026-08-15 10:00:00');

		$this->actAs($this->eUserReader);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/enrollment:doCancel', ['id' => (string)$eEnrollmentAdmin['id']]), 'la demande d\'une autre personne');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/enrollment:doConfirm', ['id' => (string)$eEnrollmentReader['id']]), 'confirmer sa propre demande');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/enrollment:doCancelByCommunity', ['id' => (string)$eEnrollmentReader['id']]), 'l\'annuler au nom du réseau');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/enrollment:doCancel', ['id' => (string)$eEnrollmentReader['id']]), 'retirer sa demande');
		$this->assertEquals(Enrollment::CANCELED, $this->getEnrollment($eEnrollmentReader['id'])['status'], 'retirée');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/enrollment:doCancel', ['id' => (string)$eEnrollmentReader['id']]), 'une demande retirée ne se retire pas deux fois');

		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/enrollment:doConfirm', ['id' => (string)$eEnrollmentReader['id']]), 'une demande retirée ne se confirme pas');
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/enrollment:doConfirm', ['id' => (string)$eEnrollmentAdmin['id']]), 'le réseau confirme');
		$this->assertEquals(Enrollment::CONFIRMED, $this->getEnrollment($eEnrollmentAdmin['id'])['status'], 'confirmée');
		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/enrollment:doCancelByCommunity', ['id' => (string)$eEnrollmentAdmin['id']]), 'une inscription confirmée ne s\'annule pas comme une demande');

	}

	/**
	 * Le suivi montre les demandes, les plus anciennes d'abord ; la liste du réseau les compte.
	 */
	public function testRequests(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$this->assertEquals('<div id="learning-enrollment-requests"></div>', new EnrollmentUi()->getRequests($eCourse, EnrollmentLib::getRequestsByCourse($eCourse)), 'sans demande, le bloc reste vide pour la réponse ciblée');

		// Une demande écrite sous `ALWAYS` n'a pas de réponse à la question.
		$eEnrollmentAdmin = $this->request($eCourse, $this->eUserAdmin, new \structure\Structure(), '2026-08-15 10:00:00', fundingRequested: NULL);
		$eEnrollmentReader = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$cEnrollment = EnrollmentLib::getRequestsByCourse($eCourse);

		$this->assertEquals([$eEnrollmentReader['id'], $eEnrollmentAdmin['id']], $cEnrollment->getIds(), 'la plus ancienne d\'abord');

		$html = new EnrollmentUi()->getRequests($eCourse, $cEnrollment);

		$this->assertTrue(str_contains($html, 'data-ajax="/learning/enrollment:doConfirm" post-id="'.$eEnrollmentReader['id'].'"'), 'le geste de confirmation');

		$fundingRequested = s("Prise en charge demandée");

		$this->assertEquals([TRUE, FALSE], [str_contains($this->getRow($html, $eEnrollmentReader), $fundingRequested), str_contains($this->getRow($html, $eEnrollmentAdmin), $fundingRequested)], 'la prise en charge demandée se marque seulement sur la demande qui la porte : '.$html);

		$cCourse = new \Collection([$eCourse['id'] => $eCourse]);

		EnrollmentLib::fillRequests($cCourse);
		$this->assertEquals(2, $eCourse['requested'], 'la liste du réseau compte les demandes');
		$this->assertTrue(DateEnrollmentLib::hasRequested($this->eStructureNetwork, $cCourse), 'et garde la colonne « À confirmer »');

		EnrollmentLib::confirm($this->getEnrollment($eEnrollmentReader['id']));

		EnrollmentLib::fillRequests($cCourse);
		$this->assertEquals(1, $eCourse['requested'], 'une confirmée ne compte plus');

	}

	/**
	 * Les demandes qui attendent se voient depuis la formation : un compteur sous le titre de
	 * l'éditeur, et le chiffre de la colonne « À confirmer » de la liste, mènent tous deux au suivi
	 * où elles se confirment.
	 */
	public function testRequestsLeadToTrack(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$url = ProgressUi::urlTrack($eCourse);

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(0, $this->getEditionCourseFor($eCourse)['requested'], 'sans demande, aucun compteur sous le titre');
		$linksBefore = substr_count($this->renderEditionHead($eCourse), '<a href="'.$url.'">');
		$this->assertFalse(str_contains($this->renderNetworkRow($eCourse), '<a href="'.$url.'">'), 'et le zéro de la liste reste du texte');

		$this->request($eCourse, $this->eUserAdmin, new \structure\Structure(), '2026-08-15 10:00:00', fundingRequested: NULL);
		$this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$this->assertEquals(2, $this->getEditionCourseFor($eCourse)['requested'], 'l\'éditeur compte les demandes');
		$this->assertEquals($linksBefore + 1, substr_count($this->renderEditionHead($eCourse), '<a href="'.$url.'">'), 'le compteur sous le titre mène au suivi');
		$this->assertTrue(str_contains($this->renderNetworkRow($eCourse), '<a href="'.$url.'">'), 'le chiffre de la liste aussi');

		$this->actAs($this->eUserReader);

		$this->assertFalse(str_contains(new CourseUi()->getEditionContext($this->getEditionCourseFor($eCourse)), '<a href="'.$url.'">'), 'qui ne modifie pas la formation lit le compteur sans lien');

	}

	/**
	 * La fiche de la personne dont la demande attend porte la demande à la place du geste
	 * d'inscription, et son menu retire la demande.
	 */
	public function testFiche(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$this->actAs($this->eUserReader);

		$enroll = fn(string $fiche): bool => (str_contains($fiche, '/learning/enrollment:create?course='.$eCourse['id']) or str_contains($fiche, 'data-ajax="/learning/enrollment:doCreate"'));

		$fiche = $this->renderFiche($eCourse);

		$this->assertTrue(str_contains($fiche, 'data-ajax="/learning/enrollment:doCancel" post-id="'.$eEnrollment['id'].'"'), 'le menu retire la demande : '.$fiche);
		$this->assertFalse($enroll($fiche), 'sans le geste d\'inscription');

		EnrollmentLib::cancel($this->getEnrollment($eEnrollment['id']));

		$fiche = $this->renderFiche($eCourse);

		$this->assertFalse(str_contains($fiche, 'data-ajax="/learning/enrollment:doCancel"'), 'retirée, la demande ne se retire plus');
		$this->assertTrue($enroll($fiche), 'et la fiche revient au geste d\'inscription');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * La confirmation et l'annulation par le réseau écrivent un e-mail à la personne ; le retrait
	 * par la personne n'en écrit pas. En dev, seul le compte 1 en reçoit (`getLastMail()`).
	 */
	public function testMails(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();

		$eEnrollmentConfirmed = $this->request($eCourse, $this->eUserAdmin, new \structure\Structure(), '2026-08-14 10:00:00');

		$before = $this->getLastMail();
		EnrollmentLib::confirm($this->getEnrollment($eEnrollmentConfirmed['id']));
		$this->assertEquals(1, count(array_keys($this->getMailSubjects($before), s("Votre inscription à la formation « {value} » est confirmée", $eCourse['name']))), 'un e-mail à la confirmation, à côté de l\'annonce aux formateurs : '.implode(' · ', $this->getMailSubjects($before)));

		$eCourseOther = $this->createFundable();

		$eEnrollmentCanceled = $this->request($eCourseOther, $this->eUserAdmin, new \structure\Structure(), '2026-08-14 10:00:00');

		$before = $this->getLastMail();
		EnrollmentLib::cancelByCommunity($this->getEnrollment($eEnrollmentCanceled['id']));
		$this->assertEquals([s("Votre demande d'inscription à la formation « {value} » est annulée", $eCourseOther['name'])], $this->getMailSubjects($before), 'un e-mail à l\'annulation par le réseau');

		$eCourseWithdrawn = $this->createFundable();

		$eEnrollmentWithdrawn = $this->request($eCourseWithdrawn, $this->eUserAdmin, new \structure\Structure(), '2026-08-14 10:00:00');

		$before = $this->getLastMail();
		EnrollmentLib::cancel($this->getEnrollment($eEnrollmentWithdrawn['id']));
		$this->assertEquals($before, $this->getLastMail(), 'aucun e-mail au retrait par la personne');

		$eEnrollment = new Enrollment([
			'course' => new Course(['name' => 'Réussir sa certification bio', 'id' => $eCourse['id'], 'community' => new \structure\Structure(['name' => 'Réseau du test', 'legalEmail' => 'reseau@example.org'])]),
		]);

		$content = new EnrollmentUi()->getCanceledByCommunityMail($eEnrollment, new Supervisor());

		$this->assertTrue(str_contains(implode(' ', array_filter($content, 'is_string')), s("Réseau du test annule votre demande d'inscription à la formation « Réussir sa certification bio ».")), 'le texte de l\'annulation');
		$this->assertTrue(str_contains(implode(' ', array_filter($content, 'is_string')), 'reseau@example.org'), 'le contact du réseau, sans encadrant contact');

		$eEnrollment['course']['name'] = 'Haies & bocage';

		[$subject] = new EnrollmentUi()->getConfirmedMail($eEnrollment);
		$this->assertEquals(s("Votre inscription à la formation « {value} » est confirmée", 'Haies & bocage'), $subject, 'le sujet est du texte, sans entité HTML');

		[$subject] = new EnrollmentUi()->getCanceledByCommunityMail($eEnrollment, new Supervisor());
		$this->assertEquals(s("Votre demande d'inscription à la formation « {value} » est annulée", 'Haies & bocage'), $subject, 'le sujet de l\'annulation nomme la formation, en texte');

	}

	/**
	 * Sous chaque réglage, le compte à une structure : quand l'inscription peut partir en demande, le
	 * bouton ouvre le panneau, qui pose sous `FUNDING` la question de la prise en charge ; sous
	 * `IMMEDIATE`, il garde son clic. Le titre du panneau est le mot de son bouton, et sous `ALWAYS`
	 * seulement ce mot n'est pas celui de l'inscription. Le panneau, ouvert par un compte à
	 * plusieurs structures, n'y coche rien.
	 */
	public function testOnlineConfirm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$oneClick = '<a data-ajax="/learning/enrollment:doCreate" post-course="'.$eCourse['id'].'" post-structure="'.$this->eStructureFarm['id'].'"';
		$panelLink = '<a href="/learning/enrollment:create?course='.$eCourse['id'].'"';
		$send = s("Envoyer la demande");
		$titles = [];

		foreach([
			\structure\Community::IMMEDIATE => [FALSE, FALSE, s("S'inscrire")],
			\structure\Community::ALWAYS => [FALSE, TRUE, $send],
			\structure\Community::FUNDING => [TRUE, FALSE, s("S'inscrire")],
		] as $onlineConfirm => [$question, $note, $label]) {

			$this->actAs($this->eUserAdmin);
			$this->setOnlineConfirm($onlineConfirm);

			$this->actAs($this->eUserReader);

			$fiche = $this->renderFiche($eCourse);

			$this->assertEquals($question or $note, str_contains($fiche, $panelLink), $onlineConfirm.' : le bouton ouvre le panneau');
			$this->assertEquals(($question or $note) === FALSE, str_contains($fiche, $oneClick), $onlineConfirm.' : le bouton inscrit d\'un clic');

			$panel = $this->renderCreate($eCourse);
			$titles[$onlineConfirm] = $panel->title;

			$this->assertTrue(str_contains($fiche, '>'.$panel->title.'</a>'), $onlineConfirm.' : le titre du panneau est le mot de son bouton : '.$fiche);
			$this->assertEquals($question, str_contains($panel->body, 'name="fundingRequested"'), $onlineConfirm.' : la question');
			$this->assertFalse(str_contains($panel->body, 'checked'), $onlineConfirm.' : rien de coché');
			$this->assertEquals($question, str_contains($panel->body, 'data-label="'.encode($label).'" data-label-requested="'.$send.'"'), $onlineConfirm.' : le bouton, et le titre avec lui, prêts à le dire sur « Je demande une prise en charge »');

		}

		$this->assertEquals([FALSE, TRUE], [$titles[\structure\Community::ALWAYS] === $titles[\structure\Community::IMMEDIATE], $titles[\structure\Community::FUNDING] === $titles[\structure\Community::IMMEDIATE]], 'seul ALWAYS dit autre chose que l\'inscription');

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'équipe du réseau qui écrit la formation n'a jamais de demande à faire, même quand le réseau
	 * confirme chaque inscription : ni sa fiche ni son panneau ne parlent de demande, et ce qu'elle
	 * envoie est une inscription acquise.
	 */
	public function testTeamNeverRequests(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$this->actAs($this->eUserAdmin);
		$this->setOnlineConfirm(\structure\Community::ALWAYS);

		$send = s("Envoyer la demande");

		$fiche = $this->renderFiche($eCourse);

		$this->assertFalse(str_contains($fiche, $send), 'sans « Envoyer la demande » : '.$fiche);

		$panel = $this->renderCreate($eCourse);

		$this->assertFalse(str_contains($panel->body, $send), 'et le panneau n\'envoie pas de demande');

		$result = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '']);

		$this->assertEquals([\RedirectAction::class, NULL], $result, 'l\'envoi passe');

		$eEnrollment = Enrollment::model()
			->select(['status', 'confirmedAt'])
			->whereUser($this->eUserAdmin)
			->whereCourse($eCourse)
			->get();

		$this->assertEquals(Enrollment::CONFIRMED, $eEnrollment['status'], 'une inscription acquise, pas une demande');
		$this->assertTrue($eEnrollment['confirmedAt'] !== NULL, 'confirmée à l\'envoi');

		$this->setOnlineConfirm(\structure\Community::FUNDING);

	}

	/**
	 * Sous `ALWAYS`, le compte sans structure n'envoie pas non plus sa demande d'un clic : son bouton
	 * ouvre le panneau, sur la fiche comme sous une leçon.
	 */
	public function testAlwaysWithoutStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$eCourse['onlineConfirm'] = \structure\Community::ALWAYS;

		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$this->assertTrue(str_contains(new EnrollmentUi()->getStart($eCourse, new \Collection()), 'href="/learning/enrollment:create?course='.$eCourse['id'].'"'), 'depuis la fiche');
		$this->assertTrue(str_contains(new EnrollmentUi()->getStart($eCourse, new \Collection(), small: TRUE, eLessonOrigin: $eLesson), 'href="/learning/enrollment:create?course='.$eCourse['id'].'&origin='.$eLesson['id'].'"'), 'sous une leçon');

	}

	/**
	 * La fiche ouverte par `?enroll` ouvre le panneau à l'arrivée, à côté du bouton : c'est là que
	 * revient un compte créé sur place (`AccountCreateTest::testOnlineConfirm()`). Sans l'argument, ou
	 * sans compte connecté, rien ne s'ouvre.
	 */
	public function testPanelOnArrival(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$trigger = new EnrollmentUi()->getPanelOnArrival($eCourse);

		$this->assertTrue(str_contains($trigger, '/learning/enrollment:create?course='.$eCourse['id']), 'le déclencheur charge le panneau de la formation : '.$trigger);

		foreach([\structure\Community::FUNDING, \structure\Community::ALWAYS] as $onlineConfirm) {

			$this->actAs($this->eUserAdmin);
			$this->setOnlineConfirm($onlineConfirm);

			$this->actAs($this->eUserReader);

			$this->assertTrue(str_contains($this->renderFiche($eCourse, ['enroll' => '1']), $trigger), $onlineConfirm.' : avec ?enroll, le panneau s\'ouvre à l\'arrivée');
			$this->assertFalse(str_contains($this->renderFiche($eCourse), $trigger), $onlineConfirm.' : sans, il attend le clic');

			$this->actAs(new \user\User());

			$this->assertFalse(str_contains($this->renderFiche($eCourse, ['enroll' => '1']), $trigger), $onlineConfirm.' : sans compte connecté, rien ne s\'ouvre');

		}

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'inscription envoyée, sous chaque réglage et chaque réponse : la demande attend le réseau sans
	 * lecture, l'inscription acquise est datée de sa confirmation, et toutes deux s'annoncent au
	 * réseau.
	 * Sous `FUNDING`, ne rien répondre est refusé ; ailleurs, une réponse postée à la main ne
	 * s'écrit pas.
	 */
	public function testCreateByOnlineConfirm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$enabledInitial = \notification\NotificationSetting::$enabled;
		\notification\NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

		foreach([
			'immediate, réponse forgée' => [\structure\Community::IMMEDIATE, '1', Enrollment::CONFIRMED, NULL],
			'always, réponse forgée' => [\structure\Community::ALWAYS, '0', Enrollment::REQUESTED, NULL],
			'funding, sans prise en charge' => [\structure\Community::FUNDING, '0', Enrollment::CONFIRMED, FALSE],
			'funding, prise en charge demandée' => [\structure\Community::FUNDING, '1', Enrollment::REQUESTED, TRUE],
		] as $case => [$onlineConfirm, $answer, $status, $fundingRequested]) {

			$this->actAs($this->eUserAdmin);
			$this->setOnlineConfirm($onlineConfirm);

			$eCourse = $this->createFundable();
			$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

			$this->actAs($this->eUserReader);

			$result = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id'], 'fundingRequested' => $answer], redirect: $redirect);

			$this->assertEquals([\RedirectAction::class, NULL], $result, $case.' : l\'envoi passe');
			$this->assertEquals($status === Enrollment::CONFIRMED, $this->getCourse($eCourse['id'])->canRead(), $case.' : la lecture suit la confirmation');

			$this->actAs($this->eUserAdmin);

			$eEnrollment = Enrollment::model()
				->select(['id', 'status', 'fundingRequested', 'confirmedAt'])
				->whereUser($this->eUserReader)
				->whereCourse($eCourse)
				->get();

			$this->assertEquals($status, $eEnrollment['status'], $case.' : l\'état');
			$this->assertEquals($fundingRequested, $eEnrollment['fundingRequested'], $case.' : la réponse');
			$this->assertEquals(1, $this->countTasks($eEnrollment), $case.' : le réseau est prévenu');

			if($status === Enrollment::REQUESTED) {
				$this->assertEquals(CourseUi::url($eCourse).'?success=learning%5CEnrollment%3A%3Arequested', $redirect, $case.' : retour à la fiche, qui dit la demande envoyée');
				$this->assertEquals(NULL, $eEnrollment['confirmedAt'], $case.' : sans confirmation');
			} else {
				$this->assertEquals(CourseUi::url($eCourse), $redirect, $case.' : retour à la fiche');
				$this->assertTrue($eEnrollment['confirmedAt'] !== NULL, $case.' : confirmée');
			}

		}

		$this->setOnlineConfirm(\structure\Community::FUNDING);

		$eCourse = $this->createFundable();

		$this->actAs($this->eUserReader);

		$result = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id']]);

		$this->assertEquals([\FailAction::class, 'Enrollment::fundingRequested.chosen'], $result, 'sous la question, ne rien répondre est refusé');

		$result = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => (string)$this->eStructureFarm['id'], 'fundingRequested' => '']);

		$this->assertEquals([\FailAction::class, 'Enrollment::fundingRequested.chosen'], $result, 'une réponse vide est refusée de même');

		$this->actAs($this->eUserAdmin);

		$this->assertFalse(Enrollment::model()->whereUser($this->eUserReader)->whereCourse($eCourse)->exists(), 'et rien ne s\'écrit');

		\notification\NotificationSetting::$enabled = $enabledInitial;

	}

	/**
	 * Une demande retirée se rouvre à l'envoi suivant avec la nouvelle réponse : sans prise en
	 * charge, la ligne rouverte est confirmée et annoncée.
	 */
	public function testReopenWithAnswer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$this->setOnlineConfirm(\structure\Community::FUNDING);

		$eCourse = $this->createFundable();
		$this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');
		EnrollmentLib::cancel($this->getEnrollment($eEnrollment['id']));

		$this->actAs($this->eUserReader);

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourse['id'], 'structure' => '', 'fundingRequested' => '0']), 'le second envoi passe');

		$this->actAs($this->eUserAdmin);

		$eEnrollmentAfter = Enrollment::model()
			->select(['id', 'status', 'fundingRequested', 'structure', 'confirmedAt'])
			->whereUser($this->eUserReader)
			->whereCourse($eCourse)
			->get();

		$this->assertEquals($eEnrollment['id'], $eEnrollmentAfter['id'], 'la ligne annulée se rouvre');
		$this->assertEquals([Enrollment::CONFIRMED, FALSE, TRUE], [$eEnrollmentAfter['status'], $eEnrollmentAfter['fundingRequested'], $eEnrollmentAfter['structure']->empty()], 'confirmée, avec la nouvelle réponse et la nouvelle structure');
		$this->assertTrue($eEnrollmentAfter['confirmedAt'] !== NULL, 'datée de sa confirmation');

	}

	/**
	 * Une formation que la personne lit sans inscription (Q-C1, le catalogue public) : sous la leçon,
	 * sa demande en attente prend la place de « Commencer la formation », qui ne ferait rien.
	 */
	public function testLessonRequest(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED);
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'zzTest module'), 'zzTest leçon', 10);

		$follow = function() use($eLesson): string {
			$data = $this->executeView('formation/lecon/'.$eLesson['id'], []);
			return new ProgressUi()->getFollow($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged);
		};

		$this->actAs($this->eUserReader);

		$enroll = fn(string $html): bool => (str_contains($html, '/learning/enrollment:create?course='.$eCourse['id']) or str_contains($html, 'data-ajax="/learning/enrollment:doCreate"'));

		$this->assertTrue($enroll($follow()), 'sans demande, la leçon propose de commencer');
		$this->assertFalse(str_contains($follow(), 'data-ajax="/learning/enrollment:doCancel"'), 'et rien à retirer');

		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$this->actAs($this->eUserReader);

		$html = $follow();

		$this->assertTrue(str_contains($html, 'data-ajax="/learning/enrollment:doCancel" post-id="'.$eEnrollment['id'].'"'), 'la demande sous la leçon, qui se retire');
		$this->assertFalse($enroll($html), 'et plus le geste qui commence la formation');

	}

	/**
	 * Un réseau qui n'a jamais enregistré ses réglages pose la question : la colonne vaut `FUNDING`
	 * par défaut en base comme au module, et le panneau des réglages la montre cochée.
	 */
	public function testNewStructureDefault(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mCommunity = \structure\Community::model();

		$default = $mCommunity->pdo()->query('SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = \''.$mCommunity->getDatabase().'\' AND TABLE_NAME = \''.$mCommunity->getTable().'\' AND COLUMN_NAME = \'learningOnlineConfirm\'')->fetchColumn();

		$this->assertEquals(\structure\Community::FUNDING, $default, 'le défaut de la colonne en base');
		$this->assertEquals(\structure\Community::FUNDING, \structure\Community::model()->getDefaultValue('learningOnlineConfirm'), 'le défaut du module');

		$eCommunity = new \structure\Community([
			'learningConfirm' => TRUE,
			'learningOnlineConfirm' => \structure\Community::model()->getDefaultValue('learningOnlineConfirm'),
			'learningConditions' => NULL,
		]);

		$settings = new DateEnrollmentUi()->updateSettings($this->eStructureNetwork, $eCommunity)->body;

		$this->assertEquals(1, preg_match('/<input[^>]*checked="checked"[^>]*name="learningOnlineConfirm"[^>]*value="'.\structure\Community::FUNDING.'"/', $settings), 'le panneau des réglages la montre cochée : '.$settings);

	}

	/**
	 * Q-C3 : la demande part au réseau sous la notification d'inscription, reformulée, seule et
	 * menant à la table « À confirmer ». Le traitement la décrit dans l'état où il la trouve.
	 */
	public function testNotification(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->seed();

		$eCourse = $this->createFundable();
		$eEnrollment = $this->request($eCourse, $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');

		$eNotification = NotificationLib::describe(NotificationLib::ENROLLMENT, $this->eStructureNetwork, $eEnrollment['id']);
		$eNotification['type'] = NotificationLib::ENROLLMENT;

		$this->assertTrue($eNotification['content']['requested'], 'une demande se décrit');
		$this->assertEquals(NULL, $eNotification['group'], 'une demande ne se groupe pas avec les stagiaires qui ont commencé');
		$this->assertEquals(s("La demande de {user} pour la formation {course} attend votre confirmation", ['user' => $this->eUserReader->getName(), 'course' => encode($eCourse['name'])]), new NotificationUi()->getText($eNotification), 'le texte d\'une demande');
		$this->assertEquals(new NotificationUi()->getText($eNotification), new NotificationUi()->getGroupText($eNotification), 'la tête d\'une demande est sa ligne');
		$this->assertEquals(ProgressUi::urlTrack($eCourse).'#learning-enrollment-requests', NotificationLib::getUrl($eNotification), 'le clic mène à la table « À confirmer »');
		$this->assertTrue(NotificationLib::describe(NotificationLib::COMPLETED, $this->eStructureNetwork, $eEnrollment['id'])->empty(), 'une demande n\'a pas de fin');

		EnrollmentLib::confirm($this->getEnrollment($eEnrollment['id']));

		$eNotification = NotificationLib::describe(NotificationLib::ENROLLMENT, $this->eStructureNetwork, $eEnrollment['id']);
		$eNotification['type'] = NotificationLib::ENROLLMENT;

		$this->assertFalse($eNotification['content']['requested'], 'confirmée avant le traitement, elle s\'annonce commencée');
		$this->assertEquals($eCourse['id'], $eNotification['group'], 'et se groupe par formation');
		$this->assertEquals(s("{user} a commencé la formation {course}", ['user' => $this->eUserReader->getName(), 'course' => encode($eCourse['name'])]), new NotificationUi()->getText($eNotification), 'le texte d\'une inscription');
		$this->assertEquals(ProgressUi::urlTrack($eCourse), NotificationLib::getUrl($eNotification), 'le clic mène au suivi');

		$eNotification['content'] = array_diff_key($eNotification['content'], ['requested' => TRUE]);
		$this->assertEquals(s("{user} a commencé la formation {course}", ['user' => $this->eUserReader->getName(), 'course' => encode($eCourse['name'])]), new NotificationUi()->getText($eNotification), 'une notification écrite avant les demandes reste une inscription');

		$eEnrollmentCanceled = $this->request($this->createFundable(), $this->eUserReader, $this->eStructureFarm, '2026-08-14 10:00:00');
		EnrollmentLib::cancel($this->getEnrollment($eEnrollmentCanceled['id']));

		$this->assertTrue(NotificationLib::describe(NotificationLib::ENROLLMENT, $this->eStructureNetwork, $eEnrollmentCanceled['id'])->empty(), 'retirée avant le traitement, elle ne s\'annonce pas');

	}

	/**
	 * La demande telle que `EnrollmentLib::create()` l'écrit, insérée en direct pour poser
	 * `createdAt` et séparer le mois de la demande de celui de sa confirmation : `build()` n'y
	 * vérifierait que la structure du compte et la réponse à la question, choisies ici par le test.
	 */
	private function request(Course $eCourse, \user\User $eUser, \structure\Structure $eStructure, string $createdAt, ?bool $fundingRequested = TRUE): Enrollment {

		$eEnrollment = new Enrollment([
			'user' => $eUser,
			'course' => $eCourse,
			'structure' => $eStructure,
			'status' => Enrollment::REQUESTED,
			'fundingRequested' => $fundingRequested,
			'confirmedAt' => NULL,
			'createdAt' => $createdAt,
		]);

		Enrollment::model()->insert($eEnrollment);

		return $eEnrollment;

	}

	/** Les annonces au réseau en file pour cette inscription (`NotificationLib::enrolled()`). */
	private function countTasks(Enrollment $eEnrollment): int {

		return \queue\Task::model()
			->whereAction(\notification\NotificationLib::getAction(NotificationLib::ENROLLMENT))
			->whereElement($eEnrollment['id'])
			->count();

	}

	private function renderCreate(Course $eCourse): \Panel {

		$data = $this->executeView('learning/enrollment:create', ['course' => (string)$eCourse['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le panneau de '.$eCourse['id'].' se prépare : '.(is_string($data) ? $data : 'vue'));

		if(($data instanceof \stdClass) === FALSE) {
			return new \Panel(id: '', title: '', body: '');
		}

		return new EnrollmentUi()->create($data->e, $data->enrollFirst);

	}

	private function getEnrollment(int $id): Enrollment {

		return Enrollment::model()
			->select(Enrollment::getSelection())
			->whereId($id)
			->get();

	}

	private function renderEditionHead(Course $eCourse): string {
		return new CourseUi()->getEditionHead($this->getEditionCourseFor($eCourse));
	}

	/**
	 * La formation telle que la page de l'éditeur la prépare, sous le compte de l'équipe : la page
	 * refuserait qui ne modifie pas la formation, et le test pèse ensuite le lien sous un autre compte.
	 */
	private function getEditionCourseFor(Course $eCourse): Course {

		$eUser = \user\ConnectionLib::getOnline();

		$this->actAs($this->eUserAdmin);
		$data = $this->executeView('formation/'.$eCourse['id'].'/edition', []);
		$this->actAs($eUser);

		$this->assertTrue($data instanceof \stdClass, 'l\'éditeur de la formation '.$eCourse['id']);

		return $data->eCourse;

	}

	/**
	 * La ligne de la formation dans la liste du réseau, seule.
	 */
	private function renderNetworkRow(Course $eCourse): string {

		$data = $this->executeView('groupe/'.$this->eStructureNetwork['id'].'/formations', []);
		$this->assertTrue($data instanceof \stdClass, 'la liste des formations du réseau');

		$html = new CourseUi()->getCommunityList($data->eStructure, $data->cCourse, $data->types, $data->type, $data->withRequested);

		foreach(explode('<div class="util-table-row">', $html) as $row) {
			if(str_contains($row, 'href="'.CourseUi::urlEdition($eCourse).'"')) {
				return $row;
			}
		}

		throw new \Exception('la formation '.$eCourse['id'].' n\'est pas dans la liste');

	}

	/**
	 * La ligne d'une demande dans le suivi, de son ouverture à l'ouverture de la suivante.
	 */
	private function getRow(string $html, Enrollment $eEnrollment): string {

		$row = strstr($html, 'id="learning-enrollment-'.$eEnrollment['id'].'"');
		$this->assertTrue($row !== FALSE, 'la ligne de la demande '.$eEnrollment['id']);

		$next = strpos($row, 'class="util-table-row"', 1);

		return ($next === FALSE) ? $row : substr($row, 0, $next);

	}

	private function createFundable(): Course {

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);

		$eCourse['offer'] = Course::NETWORK;
		CourseLib::update($eCourse, ['offer']);

		return $this->getCourse($eCourse['id']);

	}

	/**
	 * Le jour de la base, celui que `NOW()` donne à `confirmedAt`.
	 */
	private function getToday(): string {
		return Enrollment::model()->pdo()->query('SELECT CURDATE()')->fetchColumn();
	}

	/**
	 * Les sujets des e-mails partis après les `$after` premiers (`getLastMail()`), dans l'ordre
	 * d'envoi, sans le préfixe que `SendLib` pose hors production (`[dev] `).
	 */
	private function getMailSubjects(int $after): array {

		$subjects = array_column(array_slice(\main\MailLib::getSent(), $after), 'subject');

		return array_map(fn(string $subject) => preg_replace('/^\[[a-z]+\] /', '', $subject), $subjects);

	}

	private function renderFiche(Course $eCourse, array $input = []): string {

		$data = $this->executeView('formation/'.$eCourse['id'], $input);

		$this->assertTrue($data instanceof \stdClass, 'la fiche '.$eCourse['id'].' se prépare : '.(is_string($data) ? $data : 'vue'));

		if(($data instanceof \stdClass) === FALSE) {
			return '';
		}

		return new CourseUi()->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

	}

}
?>
