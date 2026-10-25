<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * L'inscription à une date d'une formation de réseau (lot L10b) : le plafond et la liste
 * d'attente, la place libérée gardée par le réseau, les deux réglages du réseau, la réouverture
 * d'une ligne annulée, les deux annulations et leurs e-mails, l'annulation de la date en cascade,
 * la suppression refusée d'une date qui a eu des inscrits, la suppression d'une formation sans
 * ligne orpheline, les droits, et le verrou de la date prouvé sur deux connexions.
 *
 * Le décor est celui de `LearningNetworkFixtureTest` : le réseau G3, la ferme F3 que
 * l'utilisateur 1 administre et rattache, le lecteur fictif qui y entre. Les autres personnes sont
 * des comptes fictifs en `example.org` créés dans la transaction. Les inscriptions passent par la
 * page (`doCreate`) quand le test porte sur ce que la personne peut faire, par le Lib sinon.
 *
 * Les e-mails se comptent sur `DateEnrollmentLib::getMails()`, ce que chaque écriture envoie mot
 * pour mot : en dev, `SendLib` n'écrit rien pour une adresse hors de `MailSetting::$devSendOnly`,
 * et l'y ajouter ferait partir un vrai e-mail.
 *
 * Lancement : dev/test package=learning file=DateEnrollment
 */
class DateEnrollmentTest extends LearningNetworkFixtureTest {

	private int $people = 0;

	/**
	 * Une personne de la ferme rattachée, qui lit la formation du décor parce qu'elle est retenue au
	 * catalogue public.
	 */
	private function createPerson(): \user\User {

		$this->people++;

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Stagiaire '.$this->people,
			'email' => 'zztest.stagiaire-date-'.$this->people.'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte refusée : '.json_encode($fw->get())));

		$this->join($eUser, $this->eStructureFarm);

		return $this->getUser($eUser['id']);

	}

	/**
	 * Une formation publiée du réseau, retenue au catalogue public, et une date sur place dans dix
	 * jours. L'ouverture aux membres ne donne pas la lecture (lot B, « Perdent l'accès ») : le
	 * catalogue public est ce qui ouvre encore la fiche, et son panneau d'inscription, à quiconque
	 * n'est ni de l'équipe ni confirmé à une date.
	 */
	private function seedDate(?int $seats): Date {

		$eCourse = $this->seed(Course::ONSITE, Course::ACCEPTED);

		return $this->createDate($eCourse, $seats);

	}

	/**
	 * Une journée dans dix jours, écrite par le chemin de `doCreate`, en une séance au mode que la
	 * formation propose. Le lieu et le lien sont postés tous les deux : le mode de la séance décide
	 * lequel reste.
	 */
	private function createDate(Course $eCourse, ?int $seats): Date {

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day(10), '09:00', '17:00', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]) + [
			'link' => 'https://visio.example.org/zztest',
			'seats' => ($seats === NULL) ? '' : (string)$seats,
		], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		return DateLib::getById($eDate['id']);

	}

	/**
	 * Le chemin du panneau, à titre personnel ou au nom d'une ferme de la personne, les cases de
	 * l'étape 2 toutes cochées : `conditions` est de trop quand le réseau n'en a pas écrit, et ne
	 * change alors rien.
	 */
	private function enroll(\user\User $eUser, Date $eDate, \structure\Structure $eStructure = new \structure\Structure()): array {

		$this->actAs($eUser);

		$result = $this->executePage('learning/dateEnrollment:doCreate', [
			'date' => (string)$eDate['id'],
			'commitment' => ['presence', 'cancel', 'conditions'],
			'structure' => $eStructure->empty() ? '' : (string)$eStructure['id'],
			'trainee' => DateEnrollment::FARMER,
		]);

		$this->actAs($this->eUserAdmin);

		return $result;

	}

	private function getLine(Date $eDate, \user\User $eUser): DateEnrollment {
		return DateEnrollmentLib::getByDateAndUser($eDate, $eUser);
	}

	private function getStatus(Date $eDate, \user\User $eUser): ?string {

		$eDateEnrollment = $this->getLine($eDate, $eUser);

		return $eDateEnrollment->empty() ? NULL : $eDateEnrollment['status'];

	}

	private function getCounts(Date $eDate): Date {

		$eDate = DateLib::getById($eDate['id']);

		DateEnrollmentLib::fillCounts(new \Collection([$eDate]));
		$eDate['eLessonQuiz'] = LessonLib::getLastQuiz($eDate['course']);

		return $eDate;

	}

	private function setConfirm(bool $confirm): void {

		$fw = new \FailWatch();

		CommunityLib::updateEnrollment($this->eStructureNetwork, ['learningConfirm' => $confirm ? '1' : '0', 'learningOnlineConfirm' => \structure\Community::FUNDING]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglage refusé : '.json_encode($this->getFails($fw))));

		$this->assertEquals($confirm, \structure\CommunityLib::getByStructure($this->eStructureNetwork)['learningConfirm'], 'réglage relu');

	}

	private function getRecipients(DateEnrollment $e, string $event): array {
		return array_column(DateEnrollmentLib::getMails($e, $event), 'to');
	}

	/**
	 * Règle 1 et plafond : une demande à confirmer tient sa place, et la troisième personne d'une
	 * date à deux places part en attente. Le réglage par défaut est « Demande à confirmer ».
	 */
	public function testCeilingAndWaiting(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(2);

		$this->assertTrue(\structure\CommunityLib::getByStructure($this->eStructureNetwork)['learningConfirm'], 'réglage par défaut : à confirmer');

		[$eUserA, $eUserB, $eUserC] = [$this->createPerson(), $this->createPerson(), $this->createPerson()];

		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUserA, $eDate), 'A s\'inscrit');
		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUserB, $eDate), 'B s\'inscrit');

		$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDate, $eUserA), 'A à confirmer');
		$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDate, $eUserB), 'B à confirmer');

		$eDateCount = $this->getCounts($eDate);
		$this->assertEquals(2, $eDateCount['taken'], 'deux places prises par deux demandes');
		$this->assertEquals(0, $eDateCount['remaining'], 'plus aucune place');

		$this->assertEquals([], $this->getRecipients($this->getLine($eDate, $eUserA), 'created'), 'une demande n\'envoie aucun e-mail');

		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUserC, $eDate), 'C s\'inscrit');
		$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDate, $eUserC), 'C en attente');

		$eDateCount = $this->getCounts($eDate);
		$this->assertEquals([2, 0, 1], [$eDateCount['requested'], $eDateCount['confirmed'], $eDateCount['waiting']], 'comptes par état');

		// Une date sans limite de places n'a jamais d'attente.
		$eDateOpen = $this->createDate($eDate['course'], NULL);

		$this->enroll($eUserA, $eDateOpen);
		$this->enroll($eUserB, $eDateOpen);
		$this->enroll($eUserC, $eDateOpen);

		$eDateOpenCount = $this->getCounts($eDateOpen);
		$this->assertEquals([3, 0, NULL], [$eDateOpenCount['requested'], $eDateOpenCount['waiting'], $eDateOpenCount['remaining']], 'places illimitées, personne n\'attend');

	}

	/**
	 * Règle 2 : une place libérée reste au réseau tant que quelqu'un attend. Une nouvelle
	 * inscription part derrière, et c'est « Donner une place » qui sert l'attente, refusé quand la
	 * date est pleine.
	 */
	public function testFreedSeatStaysWithNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(2);

		[$eUserA, $eUserB, $eUserC, $eUserD] = [$this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);
		$this->enroll($eUserC, $eDate);

		// A annule depuis la fiche : sa place se libère, C attend toujours.
		$this->actAs($eUserA);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'A annule');
		$this->actAs($this->eUserAdmin);

		$eDateCount = $this->getCounts($eDate);
		$this->assertEquals(1, $eDateCount['taken'], 'une place prise');
		$this->assertEquals(0, $eDateCount['remaining'], 'la place libérée reste au réseau');

		$this->enroll($eUserD, $eDate);
		$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDate, $eUserD), 'D part derrière C');

		$cDateEnrollmentWaiting = DateEnrollmentLib::getByDate($eDate)->find(fn(DateEnrollment $eDateEnrollment) => $eDateEnrollment['status'] === DateEnrollment::WAITING);
		$this->assertEquals([$eUserC['id'], $eUserD['id']], array_values($cDateEnrollmentWaiting->getColumnCollection('user')->getIds()), 'la liste d\'attente dans l\'ordre des demandes');

		// La fiche de D porte sa ligne ouverte, qu'il peut quitter.
		$this->actAs($eUserD);
		$cDate = new \Collection([DateLib::getById($eDate['id'])]);
		DateEnrollmentLib::fillForCourse($cDate, $eUserD);
		DateEnrollmentLib::fillLosesLessons($cDate->first()['course'], $cDate->getColumnCollection('eDateEnrollment'));
		$ficheWaiting = new DateEnrollmentUi()->getFicheAction($cDate->first());
		$this->actAs($this->eUserAdmin);
		$this->assertTrue(str_contains($ficheWaiting, 'data-ajax="/learning/dateEnrollment:doCancel" post-id="'.$this->getLine($eDate, $eUserD)['id'].'"'), 'la fiche porte la ligne en attente de D : '.$ficheWaiting);

		// Le réseau donne la place à C.
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doGiveSeat', ['id' => (string)$this->getLine($eDate, $eUserC)['id']]), 'place donnée');

		$eDateEnrollmentC = $this->getLine($eDate, $eUserC);
		$this->assertEquals(DateEnrollment::CONFIRMED, $eDateEnrollmentC['status'], 'C confirmée');
		$this->assertTrue($eDateEnrollmentC['confirmedAt'] !== NULL, 'avec sa date de confirmation');
		$this->assertEquals([$eUserC['email']], $this->getRecipients($eDateEnrollmentC, 'seatGiven'), 'C reçoit « Une place pour vous »');

		// Plus de place pour D : le geste est refusé, avec son message.
		$this->assertEquals([\FailAction::class, 'DateEnrollment::noSeat'], $this->executePage('learning/dateEnrollment:doGiveSeat', ['id' => (string)$this->getLine($eDate, $eUserD)['id']]), 'aucune place libre');
		$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDate, $eUserD), 'D attend toujours');

		// Le réseau confirme B.
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$this->getLine($eDate, $eUserB)['id']]), 'B confirmée');
		$this->assertEquals(DateEnrollment::CONFIRMED, $this->getStatus($eDate, $eUserB), 'B confirmée en base');
		$this->assertEquals([$eUserB['email']], $this->getRecipients($this->getLine($eDate, $eUserB), 'confirmed'), 'B reçoit « Inscription confirmée »');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$this->getLine($eDate, $eUserB)['id']]), 'une ligne confirmée ne se confirme pas deux fois');

	}

	/**
	 * Confirmer ou donner une place sur une ligne qui a changé d'état depuis que l'écran l'a
	 * montrée n'écrit rien, n'envoie rien, et le dit (`changed`) au lieu d'annoncer un succès.
	 */
	public function testStaleLine(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(1);

		[$eUserA, $eUserB] = [$this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);

		$eDateEnrollmentAStale = $this->getLine($eDate, $eUserA);
		$eDateEnrollmentBStale = $this->getLine($eDate, $eUserB);

		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserA), DateEnrollment::USER);
		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserB), DateEnrollment::COMMUNITY);

		$fw = new \FailWatch();
		DateEnrollmentLib::confirm($eDateEnrollmentAStale);
		$this->assertEquals([['DateEnrollment::changed', NULL]], $this->getFails($fw), 'confirmer une ligne annulée entre-temps');

		$fw = new \FailWatch();
		DateEnrollmentLib::giveSeat($eDateEnrollmentBStale);
		$this->assertEquals([['DateEnrollment::changed', NULL]], $this->getFails($fw), 'donner une place à une ligne annulée entre-temps');

		$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::CANCELED], [$this->getStatus($eDate, $eUserA), $this->getStatus($eDate, $eUserB)], 'rien n\'a été réécrit');

	}

	/**
	 * Règle 3 : « Acquise tout de suite » confirme l'inscription et l'annonce par e-mail ; les
	 * demandes déjà envoyées restent à confirmer.
	 */
	public function testImmediate(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(5);

		[$eUserA, $eUserB] = [$this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);

		$this->setConfirm(FALSE);

		$this->enroll($eUserB, $eDate);

		$eDateEnrollmentB = $this->getLine($eDate, $eUserB);

		$this->assertEquals(DateEnrollment::CONFIRMED, $eDateEnrollmentB['status'], 'B acquise tout de suite');
		$this->assertTrue($eDateEnrollmentB['confirmedAt'] !== NULL, 'avec sa date de confirmation');
		$this->assertEquals([$eUserB['email']], $this->getRecipients($eDateEnrollmentB, 'created'), 'B reçoit « Inscription confirmée »');
		$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDate, $eUserA), 'la demande de A, envoyée avant, reste à confirmer');

	}

	/**
	 * L'e-mail que `create()` envoie lui-même, au nom d'une ferme : il se rend sur la ligne relue,
	 * qui porte le nom de la ferme. Rendu sur l'élément sorti de `build()`, où « Au nom de » n'est
	 * qu'un identifiant, il sortait vide avec un avertissement `Undefined array key "name"` : le
	 * test relève les avertissements levés pendant l'envoi.
	 */
	public function testImmediateOnBehalfOfFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(5);

		$this->setConfirm(FALSE);

		$eUserA = $this->createPerson();

		$warnings = [];
		set_error_handler(function(int $level, string $message, string $file, int $line) use (&$warnings) {
			$warnings[] = $message.' ('.$file.':'.$line.')';
			return TRUE;
		}, E_WARNING | E_NOTICE);

		try {
			$result = $this->enroll($eUserA, $eDate, $this->eStructureFarm);
		} finally {
			restore_error_handler();
		}

		$this->assertEquals([\ReloadAction::class, NULL], $result, 'A s\'inscrit au nom de sa ferme');
		$this->assertEquals([], $warnings, 'aucun avertissement pendant l\'inscription : '.implode(' · ', $warnings));

		$eDateEnrollmentA = $this->getLine($eDate, $eUserA);

		$this->assertEquals([DateEnrollment::CONFIRMED, $this->eStructureFarm['id']], [$eDateEnrollmentA['status'], $eDateEnrollmentA['structure']['id']], 'confirmée, au nom de la ferme');

		$mails = DateEnrollmentLib::getMails($eDateEnrollmentA, 'created');
		$this->assertTrue(str_contains(implode(' ', array_map(fn($content) => is_string($content) ? $content : '', $mails[0]['content'])), encode($this->eStructureFarm['name'])), 'l\'e-mail nomme la ferme');

	}

	/**
	 * Lot L10t, R7 : l'e-mail de confirmation dit où se tient la date d'après le type de la
	 * formation. Sur place, le lieu et aucun lien ; en visio, le lien et aucun lieu, même si les deux
	 * ont été postés.
	 *
	 * Lot formulaires-visio, retour 19 : en visio, le lien ne se lit qu'une fois envoyé par le réseau
	 * (`DateLib::sendLink()`). Avant, la confirmation dit qu'il suivra ; après, une personne
	 * confirmée plus tard le reçoit avec sa confirmation.
	 */
	public function testConfirmedMailWhere(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setConfirm(FALSE);

		$eDateOnsite = $this->seedDate(5);

		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::REMOTE);
		$eDateRemote = $this->createDate($eCourseRemote, 5);

		$this->assertEquals(['Salle des fêtes, Crest', NULL], [$eDateOnsite->getPlace(), $eDateOnsite['link']], 'sur place, le lien posté n\'est pas gardé');
		$this->assertEquals([NULL, 'https://visio.example.org/zztest'], [$eDateRemote->getPlace(), $eDateRemote['link']], 'en visio, le lieu posté n\'est pas gardé');

		$mail = function(Date $eDate): string {

			$eUser = $this->createPerson();

			$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUser, $eDate), 'inscription immédiate');

			$mails = DateEnrollmentLib::getMails($this->getLine($eDate, $eUser), 'created');

			return implode(' ', array_map(fn($content) => is_string($content) ? $content : '', $mails[0]['content']));

		};

		$content = $mail($eDateOnsite);
		$this->assertTrue(str_contains($content, encode('Salle des fêtes, Crest')), 'sur place, l\'e-mail donne le lieu');
		$this->assertFalse(str_contains($content, 'visio.example.org'), 'et aucun lien');

		$content = $mail($eDateRemote);
		$this->assertFalse(str_contains($content, 'visio.example.org'), 'en visio, le lien posé et pas encore envoyé ne se lit pas dans l\'e-mail');
		$this->assertTrue(str_contains($content, encode(s("Il vous sera envoyé par e-mail avant la séance."))), 'l\'e-mail dit que le lien suivra');
		$this->assertFalse(str_contains($content, 'Crest'), 'et aucun lieu');

		$this->assertEquals(1, DateLib::sendLink(DateLib::getById($eDateRemote['id'])), 'le réseau envoie le lien à la personne confirmée');

		$content = $mail(DateLib::getById($eDateRemote['id']));
		$this->assertTrue(str_contains($content, 'https://visio.example.org/zztest'), 'une fois le lien envoyé, la confirmation suivante le donne');
		$this->assertFalse(str_contains($content, encode(s("Il vous sera envoyé par e-mail avant la séance."))), 'et ne dit plus qu\'il suivra');
		$this->assertFalse(str_contains($content, 'Crest'), 'toujours aucun lieu');

	}

	/**
	 * Porte V-Learning, précision 4 : en inscription immédiate, une date pleine met en attente, et
	 * aucun e-mail de confirmation ne part.
	 */
	public function testImmediateFullGoesWaitingWithoutMail(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(1);

		$this->setConfirm(FALSE);

		[$eUserA, $eUserB] = [$this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);

		$this->assertEquals(DateEnrollment::CONFIRMED, $this->getStatus($eDate, $eUserA), 'A prend la place');

		$eDateEnrollmentB = $this->getLine($eDate, $eUserB);

		$this->assertEquals(DateEnrollment::WAITING, $eDateEnrollmentB['status'], 'B en attente');
		$this->assertEquals(NULL, $eDateEnrollmentB['confirmedAt'], 'sans date de confirmation');
		$this->assertEquals([], $this->getRecipients($eDateEnrollmentB, 'created'), 'et sans e-mail');

	}

	/**
	 * Ce que la pile d'une date porte à droite sur la fiche, rendu depuis ce que la page prépare
	 * (`fillForCourse()`) : le bouton pour qui n'a pas de ligne, y compris sur une date pleine, et
	 * la ligne ouverte. Le bouton d'un visiteur sans compte, qui mène à la page
	 * publique de la date, est éprouvé par la route dans `DateEnrollmentVisitor.t.php`.
	 */
	public function testFicheAction(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(1);

		[$eUserA, $eUserB] = [$this->createPerson(), $this->createPerson()];

		$render = function(\user\User $eUser) use ($eDate): string {

			$this->actAs($eUser);

			$cDate = new \Collection([DateLib::getById($eDate['id'])]);
			DateEnrollmentLib::fillForCourse($cDate, $eUser);
			DateEnrollmentLib::fillLosesLessons($cDate->first()['course'], $cDate->getColumnCollection('eDateEnrollment'));

			$h = new DateEnrollmentUi()->getFicheAction($cDate->first());

			$this->actAs($this->eUserAdmin);

			return $h;

		};

		$create = 'href="/learning/dateEnrollment:create?date='.$eDate['id'].'"';

		$this->assertTrue(str_contains($render($eUserA), $create), 'sans ligne, le bouton qui inscrit');

		$this->enroll($eUserA, $eDate);

		$this->assertTrue(str_contains($render($eUserA), 'data-ajax="/learning/dateEnrollment:doCancel" post-id="'.$this->getLine($eDate, $eUserA)['id'].'"'), 'la ligne ouverte de la demande');
		$this->assertTrue(str_contains($render($eUserB), $create), 'date pleine, le bouton inscrit en liste d\'attente');

	}

	/**
	 * Question 5 : se réinscrire rouvre la ligne annulée, sous le même id, et une ligne encore
	 * ouverte refuse une seconde inscription.
	 */
	public function testReopen(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(3);

		$eUserA = $this->createPerson();

		$this->enroll($eUserA, $eDate);

		$eDateEnrollment = $this->getLine($eDate, $eUserA);

		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->enroll($eUserA, $eDate), 'une ligne ouverte refuse une seconde inscription');

		$this->actAs($eUserA);
		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->executePage('learning/dateEnrollment:create', ['date' => (string)$eDate['id']], 'GET'), 'le panneau aussi');
		$this->actAs($this->eUserAdmin);

		DateEnrollmentLib::cancel($eDateEnrollment, DateEnrollment::USER);

		$this->assertTrue(DateEnrollmentLib::getCreateElement($eDate, $eUserA)->acceptCreate(), 'une ligne annulée se rouvre');

		// L'engagement se date à la seconde : sans cette attente, la réouverture pourrait tomber
		// dans la même seconde que la première inscription et ne rien prouver.
		sleep(1);

		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUserA, $eDate), 'A se réinscrit');

		$eDateEnrollmentReopened = $this->getLine($eDate, $eUserA);

		$this->assertEquals($eDateEnrollment['id'], $eDateEnrollmentReopened['id'], 'la même ligne');
		$this->assertEquals(DateEnrollment::REQUESTED, $eDateEnrollmentReopened['status'], 'rouverte à confirmer');
		$this->assertEquals([NULL, NULL], [$eDateEnrollmentReopened['canceledBy'], $eDateEnrollmentReopened['canceledAt']], 'sans trace de l\'annulation');
		$this->assertTrue($eDateEnrollmentReopened['committedAt'] > $eDateEnrollment['committedAt'], 'l\'engagement est redaté ('.$eDateEnrollment['committedAt'].' puis '.$eDateEnrollmentReopened['committedAt'].')');
		$this->assertEquals(1, DateEnrollment::model()->whereDate($eDate)->count(), 'une ligne par personne et par date');

	}

	/**
	 * Les cases de l'étape 2, refusées par `build()` une à une, à `doCommit` comme à `doCreate`, qui
	 * les revalide avec le reste. Sans conditions écrites par le réseau, deux cases suffisent ; avec,
	 * la troisième est demandée, et son refus vise son propre groupe du formulaire.
	 */
	public function testCommitment(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(5);
		$eUser = $this->createPerson();

		// « Au nom de » ne se demande qu'à partir de deux structures (`testInscriptionStructure()`) :
		// une seconde ferme garde ici la radio dont le report est éprouvé plus bas.
		$this->join($eUser, $this->getOtherFarm());

		$commit = function(?array $commitments) use($eDate, $eUser): array {

			$this->actAs($eUser);

			$input = ['date' => (string)$eDate['id']];

			if($commitments !== NULL) {
				$input['commitment'] = $commitments;
			}

			$result = $this->executePage('learning/dateEnrollment:doCommit', $input);

			$this->actAs($this->eUserAdmin);

			return $result;

		};

		$this->setConfirm(TRUE);

		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $commit(NULL), 'rien de coché ne poste rien, et c\'est refusé');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $commit(['presence']), 'la présence seule');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $commit(['cancel']), 'l\'annulation seule');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $commit(['presence', 'autre']), 'une valeur inconnue ne compte pas');
		$this->assertEquals([\ViewAction::class, NULL], $commit(['presence', 'cancel']), 'sans conditions, les deux engagements suffisent');

		$this->actAs($eUser);
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $this->executePage('learning/dateEnrollment:doCreate', [
			'date' => (string)$eDate['id'],
			'structure' => '',
			'trainee' => DateEnrollment::FARMER,
		]), 'doCreate refuse aussi une inscription sans engagement');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(NULL, $this->getStatus($eDate, $eUser), 'rien n\'est écrit');

		$fw = new \FailWatch();

		CommunityLib::updateEnrollment($this->eStructureNetwork, ['learningConfirm' => '1', 'learningOnlineConfirm' => \structure\Community::FUNDING, 'learningConditions' => '<p>zzTest conditions VIVEA</p>']);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('conditions refusées : '.json_encode($this->getFails($fw))));

		$conditions = \structure\CommunityLib::getByStructure($this->eStructureNetwork)['learningConditions'];
		$this->assertTrue(str_contains((string)$conditions, 'zzTest conditions VIVEA'), 'les conditions sont écrites par le réglage : '.$conditions);

		$this->assertEquals([\FailAction::class, 'DateEnrollment::conditions.accepted'], $commit(['presence', 'cancel']), 'les conditions écrites, leur case est demandée');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment'], $commit(['conditions']), 'les conditions cochées, seuls les engagements sont refusés');
		$this->assertEquals([\FailAction::class, 'DateEnrollment::committedAt.commitment, DateEnrollment::conditions.accepted'], $commit(NULL), 'rien de coché, les deux refus paraissent ensemble');
		$this->assertEquals([\ViewAction::class, NULL], $commit(['presence', 'cancel', 'conditions']), 'les trois cases cochées');

		$this->actAs($eUser);
		$output = $this->renderCommit(['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel', 'conditions']]);
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(str_contains($output, 'panel-learning-date-enrollment'), 'la réponse au POST repeint le panneau ouvert : '.substr($output, 0, 300));
		$this->assertTrue(str_contains($output, 'dateEnrollment:doCreate'), 'et le repeint sur l\'étape 3, qui envoie à doCreate');
		$this->assertEquals(1, preg_match('/"layer": false/', $output), 'sans couche : servie par un POST, l\'étape 3 n\'empile rien dans l\'historique à chaque passage');

		$this->actAs($eUser);
		$output = $this->renderCommit(['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel', 'conditions'], 'structure' => '', 'trainee' => DateEnrollment::EMPLOYEE]);
		$outputPreselected = $this->renderCommit(['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel', 'conditions']]);
		$forged = $this->executePage('learning/dateEnrollment:doCommit', ['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel', 'conditions'], 'trainee' => 'inconnu']);
		$this->actAs($this->eUserAdmin);

		$this->assertTrue(str_contains($output, 'checked=\\"checked\\" name=\\"trainee\\" type=\\"radio\\" value=\\"employee\\"'), 'revenu de l\'étape 3, le statut choisi est repris');
		$this->assertEquals(1, preg_match('/<input checked=\\\\"checked\\\\" id=\\\\"structure0\\\\"/', $output), 'et « Aucune, à titre personnel » aussi, qu\'un élément vide ne distingue pas de rien');
		$this->assertEquals(0, preg_match('/<input checked=\\\\"checked\\\\" id=\\\\"structure0\\\\"/', $outputPreselected), 'sans choix reporté, « Aucune » n\'est pas cochée');

		$this->actAs($eUser);
		$dataBack = $this->prepareCreate(['date' => (string)$eDate['id'], 'step' => '2', 'commitment' => ['presence', 'cancel', 'conditions'], 'structure' => (string)$this->eStructureNetwork['id'], 'trainee' => DateEnrollment::EMPLOYEE]);
		$dataNone = $this->prepareCreate(['date' => (string)$eDate['id'], 'step' => '2', 'structure' => '']);
		$dataFarm = $this->prepareCreate(['date' => (string)$eDate['id'], 'step' => '2', 'structure' => (string)$this->eStructureFarm['id']]);
		$outputAfter = $this->renderCommit(['date' => (string)$eDate['id'], 'commitment' => $dataBack->commitments] + $dataBack->choices);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals(['trainee' => DateEnrollment::EMPLOYEE], $dataBack->choices, 'une structure reportée qui n\'est plus la sienne ne repart pas du nom d\'étape, le statut si');
		$this->assertEquals(['structure' => ''], $dataNone->choices, '« Aucune, à titre personnel » repart, elle');
		$this->assertEquals(['structure' => (string)$this->eStructureFarm['id']], $dataFarm->choices, 'une structure qui est la sienne repart aussi');
		$this->assertTrue(str_contains($outputAfter, 'dateEnrollment:doCreate'), 'l\'étape 3 s\'ouvre au lieu du refus owned : '.substr($outputAfter, 0, 200));
		$checkedStructure = function(string $output): array {
			preg_match_all('/<input[^>]*checked[^>]*name=\\\\"structure\\\\"[^>]*value=\\\\"([0-9]*)\\\\"/', $output, $matches);
			return $matches[1];
		};
		$this->assertEquals($checkedStructure($outputPreselected), $checkedStructure($outputAfter), 'et sans la structure reportée : la même que si rien n\'était reporté ('.implode(', ', $checkedStructure($outputAfter)).')');

		DateEnrollmentLib::fillCounts(new \Collection([$eDate]));
		$eDateEnrollmentChoices = DateEnrollmentLib::getCreateElement($eDate, $eUser);
		$eDateEnrollmentChoices['date']['course']['eSupervisorContact'] = SupervisorLib::getContactByCourse($eDateEnrollmentChoices['date']['course']);
		$choices = ['structure' => '', 'trainee' => DateEnrollment::EMPLOYEE];
		foreach([
			1 => new DateEnrollmentUi()->createReading($eDateEnrollmentChoices, DateEnrollment::REQUESTED, new \Collection(), new \Collection(), [], $choices)->body,
			2 => new DateEnrollmentUi()->createCommitment($eDateEnrollmentChoices, DateEnrollment::REQUESTED, [], $choices)->body,
		] as $step => $html) {
			$this->assertEquals(1, preg_match('/<input[^>]*name="structure"[^>]*type="hidden"[^>]*value=""/', $html), 'l\'étape '.$step.' garde « Au nom de » en champ caché');
			$this->assertEquals(1, preg_match('/<input[^>]*name="trainee"[^>]*type="hidden"[^>]*value="employee"/', $html), 'et le statut');
		}
		$this->assertEquals([\FailAction::class, 'DateEnrollment::trainee.check'], $forged, 'un statut reporté passe par build(), comme à doCreate');


		$eDateEnrollment = DateEnrollmentLib::getCreateElement($eDate, $eUser);

		$this->assertEquals(['presence', 'cancel', 'conditions'], $eDateEnrollment->getCommitments(), 'l\'étape 3 reporte les trois cases');

		$html = new DateEnrollmentUi()->createCommitment($eDateEnrollment, DateEnrollment::REQUESTED, [], [])->body;

		$this->assertTrue(str_contains($html, 'data-wrapper="committedAt"'), 'les engagements ont leur groupe');
		$this->assertTrue(str_contains($html, 'data-wrapper="conditions"'), 'la case des conditions a le sien, où son refus se pose');
		$this->assertTrue(str_contains($html, 'zzTest conditions VIVEA'), 'le texte des conditions est lu avant la case');
		$this->assertFalse(str_contains($html, 'data-onboarding-'), 'aucun attribut que MainOnboarding, resté chargé, attraperait');
		$this->assertEquals(0, substr_count($html, 'checked'), 'arrivée par « Continuer » de l\'étape 1 sans case cochée, aucune ne l\'est');

		$html = new DateEnrollmentUi()->createCommitment($eDateEnrollment, DateEnrollment::REQUESTED, ['presence', 'conditions'], [])->body;
		$this->assertEquals(2, substr_count($html, 'checked="checked"'), 'revenue d\'une autre étape, les cases rapportées sont rendues cochées, et elles seules');

		DateEnrollmentLib::fillCounts(new \Collection([$eDateEnrollment['date']]));
		$html = new DateEnrollmentUi()->createReading($eDateEnrollment, DateEnrollment::REQUESTED, new \Collection(), new \Collection(), ['presence', 'cancel'], [])->body;
		$this->assertEquals(2, substr_count($html, 'name="commitment[]"'), 'l\'étape 1 garde les cases rapportées en champs cachés, pour les rendre à l\'étape 2');
		$this->assertFalse(str_contains($html, 'post-step="3"') or str_contains($html, 'doCommit'), 'l\'étape 3 ne s\'atteint pas depuis l\'étape 1');

		// La cible de chaque nom de la barre aux trois étapes : on recule par le nom d'une étape faite,
		// et le nom d'une étape suivante n'en a aucune, on n'avance que par « Continuer ».
		$bar = function(string $html): array {
			preg_match('/<div class="onboarding-progress-label onboarding-progress-names">(.*?)<\/div>/s', $html, $names);
			preg_match_all('/<span([^>]*)>/', $names[1], $spansNames);
			return array_map(function(string $attributes) {
				return preg_match('/data-ajax-submit="([^"]+)"(?:.*post-step="(\d)")?/', $attributes, $target) ? $target[1].(isset($target[2]) ? ' '.$target[2] : '') : '';
			}, $spansNames[1]);
		};

		$eDateEnrollmentInscription = DateEnrollmentLib::getCreateElement($eDate, $eUser);
		$eDateEnrollmentInscription['cStructure'] = new \Collection();
		$eDateEnrollmentInscription['structure'] = new \structure\Structure();

		$barReading = $bar($html);
		$barCommitment = $bar(new DateEnrollmentUi()->createCommitment($eDateEnrollment, DateEnrollment::REQUESTED, [], [])->body);
		$barInscription = $bar(new DateEnrollmentUi()->createInscription($eDateEnrollmentInscription, DateEnrollment::REQUESTED)->body);

		$this->assertEquals(['', '', ''], $barReading, 'étape 1 : ni l\'étape 2 ni l\'étape 3 ne s\'atteignent par leur nom');
		$this->assertEquals(['/learning/dateEnrollment:create 1', '', ''], $barCommitment, 'étape 2 : on recule à l\'étape 1 par son nom, l\'étape 3 ne s\'atteint que par « Continuer », qui juge les cases');
		$this->assertEquals(['/learning/dateEnrollment:create 1', '/learning/dateEnrollment:create 2', ''], $barInscription, 'étape 3 : on recule aux deux étapes faites par leur nom');

		// « Continuer », seul chemin vers l'étape suivante depuis que son nom ne mène nulle part.
		$continue = function(string $body): string {
			return preg_match('/<div[^>]*class="form-group[^"]*"[^>]*>\s*<div><a ([^>]*) class="btn btn-primary"[^>]*>/', $body, $match) ? $match[1] : '';
		};

		$panelReading = new DateEnrollmentUi()->createReading($eDateEnrollment, DateEnrollment::REQUESTED, new \Collection(), new \Collection(), [], []);
		$panelCommitment = new DateEnrollmentUi()->createCommitment($eDateEnrollment, DateEnrollment::REQUESTED, [], []);
		$panelInscription = new DateEnrollmentUi()->createInscription($eDateEnrollmentInscription, DateEnrollment::REQUESTED);

		$continueReading = $continue($panelReading->body);
		$continueCommitment = $continue($panelCommitment->body);

		// Chaque « Continuer » réécrit la cible du formulaire du panneau (`form.js`, `data-ajax-submit`) :
		// arrivé par doCommit, un bouton d'envoi sans cible propre posterait encore à doCommit.
		$sendInscription = $continue($panelInscription->body);
		$this->assertTrue(str_contains($sendInscription, 'data-ajax-submit="/learning/dateEnrollment:doCreate"'), 'étape 3 : l\'envoi nomme doCreate lui-même ('.$sendInscription.')');
		$this->assertEquals(0, preg_match('/data-waiter="1"/', $panelInscription->body), 'étape 3 : l\'envoi garde son libellé pendant l\'attente');

		$this->assertTrue(str_contains($continueReading, 'data-ajax-submit="/learning/dateEnrollment:create"') and str_contains($continueReading, 'post-step="2"'), 'étape 1 : « Continuer » mène à l\'étape 2 ('.$continueReading.')');
		$this->assertTrue(str_contains($continueCommitment, 'data-ajax-submit="/learning/dateEnrollment:doCommit"'), 'étape 2 : « Continuer » mène à l\'étape 3 par doCommit, qui juge les cases ('.$continueCommitment.')');

		$this->assertTrue(new DateEnrollmentUi()->createCommitment($eDateEnrollment, DateEnrollment::REQUESTED, [], [])->layer, 'l\'étape 2, servie en GET, est une couche du panneau');

		$eDateFirst = new Date(['course' => new Course(['type' => Course::ONSITE]), 'cDateSequence' => new \Collection([
			new DateSequence(['startAt' => '2026-11-01 09:30:00', 'endAt' => '2026-11-01 17:00:00', 'mode' => DateSequence::ONSITE, 'place' => 'Crest']),
			new DateSequence(['startAt' => '2026-11-02 09:30:00', 'endAt' => '2026-11-02 17:00:00', 'mode' => DateSequence::ONSITE, 'place' => 'Crest']),
		])]);
		$this->assertEquals('Dimanche 1er et lundi 2 novembre, de 09:30 à 17:00, Crest.', DateUi::getCommitmentLine($eDateFirst), 'le premier du mois s\'écrit 1er');

		$this->actAs($eUser);
		$fail = $this->executePage('learning/dateEnrollment:doCommit', ['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel']]);
		$this->actAs($this->eUserAdmin);
		$this->assertEquals([\FailAction::class, 'DateEnrollment::conditions.accepted'], $fail, 'le refus des conditions');
		$message = new AlertUi()->getError('DateEnrollment::conditions.accepted');
		$this->assertTrue(str_contains($message(DateEnrollmentLib::getCreateElement($eDate, $eUser)), encode($this->eStructureNetwork['name'])), 'et son message nomme le réseau, comme la case et le titre');

		$before = \Clock::datetime();

		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($eUser, $eDate), 'inscription envoyée');

		$eDateEnrollmentWritten = $this->getLine($eDate, $eUser);
		$this->assertTrue($eDateEnrollmentWritten['committedAt'] >= $before, 'l\'engagement est daté à l\'envoi : '.$eDateEnrollmentWritten['committedAt']);

		$this->setConfirm(TRUE);

		$this->assertEquals(NULL, \structure\CommunityLib::getByStructure($this->eStructureNetwork)['learningConditions'], 'le réglage sans conditions les efface');

		$html = new DateEnrollmentUi()->createCommitment(DateEnrollmentLib::getCreateElement($eDate, $this->createPerson()), DateEnrollment::REQUESTED, [], [])->body;

		$this->assertFalse(str_contains($html, 'data-wrapper="conditions"'), 'sans conditions, ni le bloc ni la case');

	}

	/**
	 * L12a B4. À l'étape 3, « Au nom de » ne se demande qu'à un compte de plusieurs structures : la
	 * seule structure, ou aucune, part en champ caché, comme le bouton d'une formation en ligne la
	 * poste sans panneau. Quand la radio se montre, les fermes rattachées au réseau de la formation
	 * passent en tête.
	 *
	 * L'ordre ne se prouve que si la ferme rattachée n'est pas déjà première par son id : le test
	 * prend deux fermes que le réseau ne rattache pas et rattache celle d'id le plus grand, que
	 * l'ordre simple mettrait derrière l'autre.
	 */
	public function testInscriptionStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eUser = $this->createPerson();
		$input = ['date' => (string)$eDate['id'], 'commitment' => ['presence', 'cancel', 'conditions']];

		$radios = function(string $output): array {
			preg_match_all('/<input[^>]*name=\\\\"structure\\\\" type=\\\\"radio\\\\" value=\\\\"([0-9]*)\\\\"/', $output, $matches);
			return $matches[1];
		};
		$hidden = function(string $output): array {
			preg_match_all('/<input[^>]*name=\\\\"structure\\\\" type=\\\\"hidden\\\\" value=\\\\"([0-9]*)\\\\"/', $output, $matches);
			return $matches[1];
		};

		$this->actAs($eUser);
		$outputOne = $this->renderCommit($input);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([], $radios($outputOne), 'une seule structure : aucune radio, ni « Aucune, à titre personnel »');
		$this->assertEquals([(string)$this->eStructureFarm['id']], $hidden($outputOne), 'la structure part en champ caché : '.substr($outputOne, 0, 200));

		// Le panneau d'une personne sans structure se rend sur lui-même, tel que la page le prépare,
		// sans repasser par le parcours.
		$eDateEnrollmentNone = DateEnrollmentLib::getCreateElement($eDate, $eUser);
		$eDateEnrollmentNone['cStructure'] = new \Collection();
		$eDateEnrollmentNone['structure'] = new \structure\Structure();
		$htmlNone = new DateEnrollmentUi()->createInscription($eDateEnrollmentNone, DateEnrollment::REQUESTED)->body;

		$this->assertEquals(0, preg_match('/name="structure" type="radio"/', $htmlNone), 'aucune structure : aucune radio');
		$this->assertEquals(1, preg_match('/<input[^>]*name="structure" type="hidden" value=""/', $htmlNone), 'et une structure vide en champ caché, à titre personnel');

		$this->leave($eUser, $this->eStructureFarm);

		$cStructureFree = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->where('m1.type', \structure\Structure::PRODUCER)
			->where('m1.producerType', 'NOT IN', \structure\StructureSetting::PRODUCER_TYPES_COLLECTIVE)
			->where('m1.status', \structure\Structure::ACTIVE)
			->where('NOT '.new \structure\AffiliationModel()
				->where('s1.structureProducer', new \Sql('m1.id'))
				->where('s1.structureCommunity', $this->eStructureNetwork['id'])
				->existsSql())
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		$this->assertEquals(2, $cStructureFree->count(), 'deux fermes que le réseau '.$this->eStructureNetwork['id'].' ne rattache pas');

		if($cStructureFree->count() < 2) {
			return;
		}

		$eStructureFree = $cStructureFree->first();
		$eStructureAffiliated = $cStructureFree->last();

		// Le rattachement se fait au nom de l'admin du décor, qui doit être membre de la ferme.
		$this->join($this->eUserAdmin, $eStructureAffiliated);
		$this->affiliate($eStructureAffiliated);
		$this->join($eUser, $eStructureFree);
		$this->join($eUser, $eStructureAffiliated);

		$this->actAs($eUser);
		$idsSimple = EnrollmentLib::getStructures()->getIds();
		$idsCourse = EnrollmentLib::getStructures($eDate['course'])->getIds();
		$outputTwo = $this->renderCommit($input);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([$eStructureFree['id'], $eStructureAffiliated['id']], $idsSimple, 'sans formation, l\'ordre des ids met la ferme non rattachée devant');
		$this->assertEquals([$eStructureAffiliated['id'], $eStructureFree['id']], $idsCourse, 'pour une formation du réseau, la ferme rattachée passe en tête');
		$this->assertEquals([], $hidden($outputTwo), 'deux structures : plus de champ caché');
		$this->assertEquals([(string)$eStructureAffiliated['id'], (string)$eStructureFree['id'], ''], $radios($outputTwo), 'la radio, rattachée en tête, puis l\'autre, puis « Aucune » : '.implode(', ', $radios($outputTwo)));

	}

	/**
	 * L12a B6. Une formation datée ne se commence qu'une fois l'inscription à l'une de ses dates
	 * confirmée, et si elle a des leçons. Avant, la fiche n'a pas de bouton de tête (le geste est
	 * « S'inscrire » sur la date) et un POST forgé est refusé par `acceptCreate()`.
	 */
	public function testDatedStart(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setConfirm(TRUE);

		$eDate = $this->seedDate(1);
		$eChapter = $this->createChapter($eDate['course'], 'zzTest module');
		$eLesson = $this->getLesson($this->createLesson($eChapter, 'zzTest leçon', 5)['id']);

		[$eUserA, $eUserB, $eUserC] = [$this->createPerson(), $this->createPerson(), $this->createPerson()];

		$accept = function(\user\User $eUser, Course $eCourse): bool {
			$this->actAs($eUser);
			$accepted = new Enrollment(['user' => $eUser, 'course' => $this->getCourse($eCourse['id'])])->acceptCreate();
			$this->actAs($this->eUserAdmin);
			return $accepted;
		};

		$start = function(\user\User $eUser) use($eDate, $eLesson, $accept): string {

			$eCourse = $this->getCourse($eDate['course']['id']);
			$eCourse['lessons'] = 1;
			$eCourse['eLessonFirst'] = $eLesson;
			$eCourse['eLessonResume'] = new Lesson();
			$eCourse['canProgress'] = TRUE;
			$eCourse['acceptEnrollment'] = $accept($eUser, $eCourse);
			$eCourse['isDateConfirmed'] = DateEnrollmentLib::hasConfirmed($eUser, $eCourse);
			$eCourse['eEnrollmentRequest'] = new Enrollment();
			$eCourse['withEnrollPanel'] = FALSE;
			$eCourse['onlineConfirm'] = EnrollmentLib::getOnlineConfirm($eCourse);

			$this->actAs($eUser);
			$eCourse['withEnroll'] = $eCourse->isEnrollFirst();
			ResponseLib::fillCourseQuiz($eUser, $eCourse, ChapterLib::getByCourse($eCourse));
			ReviewLib::fillCourse($eCourse, $eUser);
			$this->actAs($this->eUserAdmin);

			return new \ReflectionMethod(CourseUi::class, 'getStart')->invoke(new CourseUi(), $eCourse, new Enrollment(), new \Collection(), new \Collection(), TRUE);

		};

		$this->assertFalse($accept($eUserA, $eDate['course']), 'sans inscription à une date, la formation ne se commence pas');
		$this->assertEquals('', $start($eUserA), 'et la fiche n\'a pas de bouton de tête');

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);

		$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDate, $eUserA), 'la demande est à confirmer');
		$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDate, $eUserB), 'la seconde personne attend');
		$this->assertFalse($accept($eUserA, $eDate['course']), 'demandée, pas encore');
		$this->assertEquals('', $start($eUserA), 'ni de bouton de tête');
		$this->assertFalse($accept($eUserB, $eDate['course']), 'en attente, pas encore');
		$this->assertEquals('', $start($eUserB), 'ni de bouton de tête');

		DateEnrollmentLib::confirm($this->getLine($eDate, $eUserA));

		$this->assertTrue($accept($eUserA, $eDate['course']), 'confirmée, la formation se commence');
		$this->assertTrue($start($eUserA) !== '', 'et la fiche porte un bouton de tête');
		$this->assertFalse($accept($eUserB, $eDate['course']), 'la confirmation de l\'une n\'ouvre rien à l\'autre');

		$this->actAs($eUserC);
		$forged = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eDate['course']['id'], 'structure' => '']);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\FailAction::class, 'learning\Enrollment::dated'], $forged, 'sans inscription à une date, un POST forgé est refusé, par le message des formations datées');
		$this->assertTrue(AlertUi::getError('Enrollment::dated') !== NULL, 'et ce message est écrit');
		$this->assertEquals(0, Enrollment::model()->whereCourse($eDate['course'])->whereUser($eUserC)->count(), 'et n\'inscrit rien');

		$this->setConfirm(FALSE);

		$eCourseEmpty = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::ONSITE);
		$eDateEmpty = $this->createDate($eCourseEmpty, NULL);

		$this->actAs($eUserC);
		$forgedEmptyBefore = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourseEmpty['id'], 'structure' => '']);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\FailAction::class, 'learning\Enrollment::lessons'], $forgedEmptyBefore, 'sans inscription à une date et sans leçon, le POST forgé dit l\'absence de leçon, qu\'aucune confirmation ne lèverait');

		$this->enroll($eUserC, $eDateEmpty);

		$this->assertEquals(DateEnrollment::CONFIRMED, $this->getStatus($eDateEmpty, $eUserC), 'le réseau ne confirme pas : inscription immédiate');
		$this->assertFalse($accept($eUserC, $eDateEmpty['course']), 'confirmée, une formation sans leçon ne se commence pas');

		$this->actAs($eUserC);
		$forgedEmpty = $this->executePage('learning/enrollment:doCreate', ['course' => (string)$eCourseEmpty['id'], 'structure' => '']);
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\FailAction::class, 'learning\Enrollment::lessons'], $forgedEmpty, 'confirmée sur une formation sans leçon, un POST forgé est refusé, par le message des formations sans leçon');
		$this->assertTrue(AlertUi::getError('Enrollment::lessons') !== NULL, 'et ce message est écrit');
		$this->assertEquals(0, Enrollment::model()->whereCourse($eCourseEmpty)->whereUser($eUserC)->count(), 'et n\'inscrit rien');

	}

	/**
	 * 6 C : chacun annule, et l'autre côté reçoit l'e-mail. La ligne garde qui a annulé.
	 */
	public function testCancelBothSides(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(3);

		[$eUserA, $eUserB] = [$this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);

		// B ne peut pas annuler la ligne de A, ni rien au nom du réseau.
		$this->actAs($eUserB);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'la ligne d\'une autre personne');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancelByCommunity', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'au nom du réseau');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/dateEnrollment:doConfirm', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'confirmer');

		// A annule elle-même : le réseau est prévenu à son adresse de contact.
		$this->actAs($eUserA);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'A annule');
		$this->actAs($this->eUserAdmin);

		$eDateEnrollmentA = $this->getLine($eDate, $eUserA);
		$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::USER], [$eDateEnrollmentA['status'], $eDateEnrollmentA['canceledBy']], 'annulée par la personne');
		$this->assertTrue($eDateEnrollmentA['canceledAt'] !== NULL, 'avec sa date');
		$this->assertEquals([$this->eStructureNetwork['legalEmail']], $this->getRecipients($eDateEnrollmentA, 'canceled'), 'le réseau reçoit l\'e-mail');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancelByCommunity', ['id' => (string)$eDateEnrollmentA['id']]), 'une ligne annulée ne s\'annule pas deux fois');

		// Le réseau annule B : B est prévenue.
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancelByCommunity', ['id' => (string)$this->getLine($eDate, $eUserB)['id']]), 'le réseau annule B');

		$eDateEnrollmentB = $this->getLine($eDate, $eUserB);
		$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::COMMUNITY], [$eDateEnrollmentB['status'], $eDateEnrollmentB['canceledBy']], 'annulée par le réseau');
		$this->assertEquals([$eUserB['email']], $this->getRecipients($eDateEnrollmentB, 'canceled'), 'B reçoit l\'e-mail');

		[$subject] = DateEnrollmentLib::getMails($eDateEnrollmentB, 'canceled')[0]['content'];
		$this->assertTrue(str_contains($subject, 'Votre demande du'), 'l\'e-mail annonce la demande annulée, B n\'ayant pas encore de place, et pas la date : '.$subject);

	}

	/**
	 * Annuler la date annule toutes ses lignes ouvertes, au nom du réseau, et chacun reçoit l'e-mail
	 * de la date annulée. Une ligne déjà annulée par la personne garde son auteur.
	 */
	public function testDateCancelCascade(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(2);

		[$eUserA, $eUserB, $eUserC, $eUserD, $eUserE] = [$this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);
		$this->enroll($eUserC, $eDate);
		$this->enroll($eUserD, $eDate);
		$this->enroll($eUserE, $eDate);

		DateEnrollmentLib::confirm($this->getLine($eDate, $eUserB));
		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserD), DateEnrollment::USER);

		// Annulée à l'unité par le réseau avant que la date ne le soit, E doit garder « Par le
		// réseau » : l'annulation de la date ne relit que les lignes encore ouvertes.
		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserE), DateEnrollment::COMMUNITY);

		$eDate = DateLib::getById($eDate['id']);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doCancel', ['id' => (string)$eDate['id']]), 'date annulée');

		foreach([$eUserA, $eUserB, $eUserC] as $eUser) {

			$eDateEnrollment = $this->getLine($eDate, $eUser);

			$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::DATE], [$eDateEnrollment['status'], $eDateEnrollment['canceledBy']], 'ligne de '.$eUser['id'].' annulée avec sa date');
			$this->assertEquals([$eUser['email']], $this->getRecipients($eDateEnrollment, 'canceled'), 'e-mail à '.$eUser['id']);

			[$subject] = DateEnrollmentLib::getMails($eDateEnrollment, 'canceled')[0]['content'];
			$this->assertTrue(str_contains($subject, 'La date du'), 'l\'e-mail annonce la date annulée : '.$subject);

		}

		$this->assertEquals(DateEnrollment::USER, $this->getLine($eDate, $eUserD)['canceledBy'], 'D garde son annulation à elle');

		$eDateEnrollmentE = $this->getLine($eDate, $eUserE);
		$this->assertEquals(DateEnrollment::COMMUNITY, $eDateEnrollmentE['canceledBy'], 'E garde son annulation par le réseau');
		DateEnrollmentLib::fillCanceled($eDateEnrollmentE);
		$this->assertEquals(new DateEnrollmentUi()->getCanceledByCommunityMail($eDateEnrollmentE, $this->eStructureNetwork, SupervisorLib::getContactByCourse($eDateEnrollmentE['date']['course'])), DateEnrollmentLib::getMails($eDateEnrollmentE, 'canceled')[0]['content'], 'l\'e-mail de E reste celui d\'une inscription annulée par le réseau');
		$this->assertTrue(new DateEnrollmentUi()->getDateCanceledMail($eDateEnrollmentE) !== DateEnrollmentLib::getMails($eDateEnrollmentE, 'canceled')[0]['content'], 'et diffère de celui d\'une date annulée');

		$this->assertEquals([\FailAction::class, 'learning\DateEnrollment::closed'], $this->enroll($this->createPerson(), $eDate), 'une date annulée ne prend plus d\'inscription');

	}

	/**
	 * La tête de la liste des inscrits : le retour à l'éditeur de la formation ; le lien vers les
	 * autres dates seulement quand la formation en a ; l'export seulement quand la liste a des lignes.
	 * Rendue par morceaux, ceux que la vue assemble : la page entière demande en CLI tout le démarrage
	 * de l'application, que le gabarit du groupe lit.
	 */
	public function testListHead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(12);
		$ui = new DateEnrollmentUi();

		$title = $ui->getListTitle($eDate, DateLib::getByCourse($eDate['course']));

		$this->assertTrue(str_contains($title, 'href="'.CourseUi::urlEdition($eDate['course']).'"'), 'le retour à l\'éditeur : '.$title);

		$scope = $ui->getDateFilter($eDate, DateLib::getByCourse($eDate['course']));

		$this->assertEquals('', $scope, 'une seule date : pas de filtre de date');

		$eDate = $this->getCounts($eDate);
		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		DateEnrollmentLib::fillLosesLessons($eDate['course'], $cDateEnrollment);
		$list = $ui->getList($eDate, $cDateEnrollment, new \Collection(), '');

		$this->assertFalse(str_contains($list, DateEnrollmentUi::urlExport($eDate)), 'une liste vide : pas d\'export');
		$this->assertFalse(str_contains($list, 'LearningPublic.copy'), 'sans adresse publique, rien à copier');
		$this->assertTrue(str_contains($ui->getList($eDate, $cDateEnrollment, new \Collection(), 'https://reseau.example.org/zztest/formations/1'), 'data-text="https://reseau.example.org/zztest/formations/1"'), 'avec elle, le bouton qui la copie');

		$eDateOther = $this->createDate($eDate['course'], NULL);
		$this->enroll($this->createPerson(), $eDate);

		$scope = $ui->getDateFilter($eDate, DateLib::getByCourse($eDate['course']));

		$this->assertTrue(str_contains($scope, 'href="'.DateEnrollmentUi::urlList($eDateOther).'"'), 'deux dates : le lien qui mène à l\'autre date');

		$eDate = $this->getCounts($eDate);
		$cDateEnrollment = DateEnrollmentLib::getByDate($eDate);
		DateEnrollmentLib::fillLosesLessons($eDate['course'], $cDateEnrollment);
		$list = $ui->getList($eDate, $cDateEnrollment, new \Collection(), '');

		$this->assertTrue(str_contains($list, DateEnrollmentUi::urlExport($eDate)), 'une liste qui a des lignes : l\'export');
		$this->assertEquals(1, substr_count($list, 'data-ajax="/learning/dateEnrollment:doCancelByCommunity"'), 'l\'annulation par le réseau sur la ligne qui s\'annule');

	}

	/**
	 * Donner une place fait passer la ligne de l'attente aux places ; quand plus personne n'attend,
	 * l'attente est vide, et la date annulée annule toutes ses lignes.
	 */
	public function testWaitingTable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(2);

		[$eUserA, $eUserB, $eUserC, $eUserD] = [$this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson()];

		foreach([$eUserA, $eUserB, $eUserC, $eUserD] as $eUser) {
			$this->enroll($eUser, $eDate);
		}

		$status = fn() => [$this->getStatus($eDate, $eUserA), $this->getStatus($eDate, $eUserB), $this->getStatus($eDate, $eUserC), $this->getStatus($eDate, $eUserD)];

		$this->assertEquals(
			[DateEnrollment::REQUESTED, DateEnrollment::REQUESTED, DateEnrollment::WAITING, DateEnrollment::WAITING],
			$status(),
			'deux demandes, deux en attente'
		);

		// A annule, et le réseau donne sa place à C.
		$this->actAs($eUserA);
		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doCancel', ['id' => (string)$this->getLine($eDate, $eUserA)['id']]), 'A annule');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/dateEnrollment:doGiveSeat', ['id' => (string)$this->getLine($eDate, $eUserC)['id']]), 'place donnée à C, la liste se recharge');

		$this->assertEquals([DateEnrollment::CANCELED, DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED, DateEnrollment::WAITING], $status(), 'C passe aux places, D attend seul, A est annulée');

		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserD), DateEnrollment::COMMUNITY);

		$this->assertEquals(0, $this->getCounts($eDate)['waiting'], 'plus personne n\'attend');

		DateLib::cancel(DateLib::getById($eDate['id']));

		$this->assertEquals(array_fill(0, 4, DateEnrollment::CANCELED), $status(), 'la date annulée : toutes les lignes le sont');

	}

	/**
	 * La colonne « À confirmer » de la liste du réseau : les demandes encore à confirmer, sur les
	 * dates à venir de chaque formation de la liste. Une demande confirmée ou en attente, une date
	 * passée ou annulée, une formation hors de la liste ne comptent pas ; une formation en ligne ne porte pas le compte.
	 */
	public function testFillRequested(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);
		$eDatePast = $this->createDate($eDate['course'], NULL);

		// Le décor ne s'installe qu'une fois par test : les autres formations se créent à côté.
		$eCourseOther = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::ONSITE);
		$eDateOther = $this->createDate($eCourseOther, NULL);

		$eCourseEmpty = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::REMOTE);
		$eCourseOnline = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONLINE);

		[$eUserA, $eUserB, $eUserC, $eUserD, $eUserE] = [$this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson(), $this->createPerson()];

		$this->enroll($eUserA, $eDate);
		$this->enroll($eUserB, $eDate);
		$this->enroll($eUserC, $eDate);
		DateEnrollmentLib::confirm($this->getLine($eDate, $eUserC));

		// Une demande restée à confirmer sur une date désormais passée.
		$this->enroll($eUserD, $eDatePast);
		$eDatePast = $this->writeSequences($eDatePast, [[$this->day(-3).' 09:00:00', $this->day(-3).' 17:00:00']]);

		$this->enroll($eUserE, $eDateOther);

		// Une demande sur une date annulée, et une personne en liste d'attente : ni l'une ni l'autre
		// n'attend de confirmation.
		$eDateCanceled = $this->createDate($eDate['course'], 1);
		[$eUserF, $eUserG] = [$this->createPerson(), $this->createPerson()];
		$this->enroll($eUserF, $eDateCanceled);
		$this->enroll($eUserG, $eDateCanceled);
		$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDateCanceled, $eUserG), 'G en liste d\'attente');
		$eDateFull = $this->createDate($eDate['course'], 1);
		$this->enroll($eUserF, $eDateFull);
		$this->enroll($eUserG, $eDateFull);
		DateLib::cancel(DateLib::getById($eDateCanceled['id']));

		$cCourse = CourseLib::getByCommunity($this->eStructureNetwork)->find(fn(Course $eCourse) => in_array($eCourse['id'], [$eDate['course']['id'], $eCourseEmpty['id'], $eCourseOnline['id']]));

		$this->assertEquals(3, $cCourse->count(), 'trois formations dans la liste');

		DateEnrollmentLib::fillRequested($cCourse);

		$this->assertEquals(3, $cCourse[$eDate['course']['id']]['requested'], 'A et B, ni C confirmée ni D sur une date passée ; F sur la date pleine, ni G en attente ni rien sur la date annulée');
		$this->assertEquals(0, $cCourse[$eCourseEmpty['id']]['requested'], 'une formation à dates sans demande');
		$this->assertFalse($cCourse[$eCourseOnline['id']]->offsetExists('requested'), 'une formation en ligne ne porte pas le compte');

		$cCourseOther = CourseLib::getByCommunity($this->eStructureNetwork)->find(fn(Course $eCourse) => $eCourse['id'] === $eDateOther['course']['id']);

		DateEnrollmentLib::fillRequested($cCourseOther);

		$this->assertEquals(1, $cCourseOther->first()['requested'], 'la demande de E compte pour sa formation, lue seule');

	}

	/**
	 * Une date qui a eu des inscrits, même tous annulés, ne se supprime pas ; sans inscrit, si.
	 * La formation supprimée ne laisse aucune ligne derrière elle.
	 */
	public function testDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(2);
		$eDateEmpty = $this->createDate($eDate['course'], 2);

		$eUserA = $this->createPerson();

		$this->enroll($eUserA, $eDate);
		DateEnrollmentLib::cancel($this->getLine($eDate, $eUserA), DateEnrollment::USER);

		$this->assertEquals([\FailAction::class, 'Date::deleteEnrolled'], $this->executePage('learning/date:doDelete', ['id' => (string)$eDate['id']]), 'refusée avec une inscription annulée');
		$this->assertTrue(DateLib::getById($eDate['id'])->notEmpty(), 'la date est toujours là');

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('learning/date:doDelete', ['id' => (string)$eDateEmpty['id']]), 'une date sans inscrit se supprime');

		CourseLib::delete($eDate['course']);

		$this->assertEquals(0, DateEnrollment::model()->whereDate($eDate)->count(), 'aucune ligne orpheline');
		$this->assertTrue(DateLib::getById($eDate['id'])->empty(), 'la date est partie avec la formation');

	}

	/**
	 * Les droits : une personne qui ne lit pas la formation ne s'inscrit pas ; la liste et son export
	 * ne s'ouvrent qu'à l'équipe du réseau ; le réglage se refuse hors d'un réseau.
	 */
	public function testRights(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::ONSITE);
		$eDate = $this->createDate($eCourse, 2);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->enroll($this->eUserReader, $eDate), 'formation privée : pas d\'inscription par la fiche');

		$this->shareNetwork($eCourse);

		$this->assertEquals([\NotAllowedAction::class, NULL], $this->enroll($this->eUserReader, $eDate), 'ouverte aux membres : la fiche reste fermée, l\'inscription passe par la page publique');

		$eCourse['catalog'] = Course::ACCEPTED;
		CourseLib::updateCatalog($eCourse);

		$this->assertEquals([\ReloadAction::class, NULL], $this->enroll($this->eUserReader, $eDate), 'retenue au catalogue public : inscription');

		$this->actAs($this->eUserReader);
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/dateEnrollment:export', ['id' => (string)$eDate['id']], 'GET'), 'l\'export est au réseau');
		$this->actAs($this->eUserAdmin);

		$this->assertEquals([\CsvAction::class, NULL], $this->executePage('learning/dateEnrollment:export', ['id' => (string)$eDate['id']], 'GET'), 'le réseau exporte');

		// Les comptes que la page pose avant l'export, dont le nombre de confirmées que lit la présence.
		DateEnrollmentLib::fillCounts(new \Collection([$eDate]));

		$lines = new DateEnrollmentUi()->getExport($eDate, DateEnrollmentLib::getByDate($eDate));
		$this->assertEquals(2, count($lines), 'l\'en-tête et une ligne');
		$this->assertEquals(7, count($lines[1]), 'les sept colonnes, sans rang d\'attente');
		$this->assertEquals(self::EMAIL_READER, $lines[1][2], 'l\'e-mail en troisième colonne');

		// Le réglage est à l'administrateur du réseau, comme ceux de la mercuriale : l'utilisateur 1 n'y
		// est que conseiller.
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/community:updateEnrollment', ['id' => (string)self::COMMUNITY_NETWORK], 'GET'), 'un conseiller n\'ouvre pas le réglage');
		$this->assertEquals([\NotAllowedAction::class, NULL], $this->executePage('learning/community:doUpdateEnrollment', ['id' => (string)self::COMMUNITY_NETWORK, 'learningConfirm' => '0']), 'ni ne l\'écrit');

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('learning/community:doUpdateEnrollment', ['id' => (string)self::COMMUNITY_EDUCATION, 'learningConfirm' => '0']), 'un groupe de formation n\'a pas ce réglage');

	}

	/**
	 * Le verrou de la date, sur deux connexions réelles, donc sur des données écrites hors de la
	 * transaction du test et effacées à la fin : une transaction ne se voit pas d'un autre processus.
	 *
	 * Une date à une place. A s'inscrit et garde sa transaction ouverte trois secondes. B, lancé une
	 * seconde après, lit le compte avant d'appeler `create()` (hors transaction, il n'y voit rien),
	 * attend le COMMIT de A sur le verrou, et son compte sous le verrou voit A : B part en attente.
	 *
	 * Le témoin prouve que le test sait échouer : C fait la même chose, mais ouvre sa transaction et
	 * lit avant `create()`. En REPEATABLE READ, cette lecture fige la vue ; C attend le verrou comme
	 * B, puis compte sur sa vue figée, ne voit pas A et prend une place qui n'existe pas.
	 */
	public function testLockTwoConnections(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$isolation = Date::model()->pdo()->select('SELECT @@transaction_isolation')->fetchColumn();
		$this->assertEquals('REPEATABLE-READ', $isolation, 'niveau d\'isolation de la base');

		// Les processus fils ne voient que ce qui est validé : on sort de la transaction du test.
		Course::model()->rollBack();

		$eCourse = new Course();

		try {

			$this->actAs($this->eUserAdmin);

			$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

			$eDateB = $this->createDate($eCourse, 1);
			$eDateC = $this->createDate($eCourse, 1);

			[$eUserA, $eUserB] = [$this->eUserAdmin, $this->eUserReader];

			// B : lecture hors transaction, puis create().
			$waitB = $this->race($eDateB, $eUserA, $eUserB, witness: FALSE);

			$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDateB, $eUserA), 'A a pris la place');
			$this->assertEquals(DateEnrollment::WAITING, $this->getStatus($eDateB, $eUserB), 'B, qui a attendu le verrou, voit A et part en attente');
			$this->assertTrue($waitB > 1.0, 'B a attendu le COMMIT de A ('.round($waitB, 2).' s)');

			// C : lecture dans la transaction avant create(), le témoin.
			$waitC = $this->race($eDateC, $eUserA, $eUserB, witness: TRUE);

			$this->assertTrue($waitC > 1.0, 'C a attendu le verrou lui aussi ('.round($waitC, 2).' s)');
			$this->assertEquals(DateEnrollment::REQUESTED, $this->getStatus($eDateC, $eUserB), 'témoin : une lecture avant le verrou cache A, et C prend une place de trop');

		} finally {

			if($eCourse->notEmpty()) {
				CourseLib::delete(CourseLib::getById($eCourse['id']));
			}

			Course::model()->beginTransaction();

		}

	}

	/**
	 * Lance A puis, une seconde après, l'autre personne, chacun dans son processus, et rend le temps
	 * que la seconde a passé dans `create()`.
	 */
	private function race(Date $eDate, \user\User $eUserA, \user\User $eUserB, bool $witness): float {

		$load = '\\user\\ConnectionLib::setOnline(\\user\\UserLib::getById(%d, \\user\\User::getSelection()));'
			.'$e = \\learning\\DateEnrollmentLib::getCreateElement(\\learning\\DateLib::getById('.$eDate['id'].'), \\user\\ConnectionLib::getOnline());'
			.'$e["id"] = NULL; $e["structure"] = new \\structure\\Structure(); $e["trainee"] = \\learning\\DateEnrollment::FARMER; $e["committedAt"] = \\Clock::datetime();';

		$codeA = sprintf($load, $eUserA['id'])
			.'\\learning\\DateEnrollment::model()->beginTransaction();'
			.'\\learning\\DateEnrollmentLib::create($e);'
			.'sleep(3);'
			.'\\learning\\DateEnrollment::model()->commit();';

		$codeB = sprintf($load, $eUserB['id'])
			.($witness ? '\\learning\\DateEnrollment::model()->beginTransaction();' : '')
			.'\\learning\\DateEnrollment::model()->whereDate('.$eDate['id'].')->count();'
			.'$t = microtime(TRUE);'
			.'\\learning\\DateEnrollmentLib::create($e);'
			.($witness ? '\\learning\\DateEnrollment::model()->commit();' : '')
			.'echo "WAIT:", microtime(TRUE) - $t;';

		$processA = $this->spawn($codeA);

		sleep(1);

		$processB = $this->spawn($codeB);

		$outputB = $this->finish($processB);
		$this->finish($processA);

		$this->assertTrue(preg_match('/WAIT:([0-9.]+)/', $outputB, $match) === 1, 'le second processus a rendu son attente : '.$outputB);

		return (float)($match[1] ?? 0);

	}

	/**
	 * Le code du processus voyage par l'environnement, pas par la commande : celle-ci ne se compose
	 * alors que de littéraux, ce que `ShellCommandSentinel` exige de tout `proc_open()`.
	 */
	private function spawn(string $code): array {

		$process = proc_open(
			['php', 'framework/lime.php', '-a', 'ouvretaferme', '-e', 'dev', '-r', 'eval(getenv(\'DATE_ENROLLMENT_RACE\'));'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			LIME_DIRECTORY,
			['DATE_ENROLLMENT_RACE' => $code] + getenv()
		);

		return [$process, $pipes];

	}

	/**
	 * `doCommit` joué puis sa vue rendue, sous la méthode POST du bouton : une vue de panneau
	 * (`PanelTemplate`) lève sur un POST, ce que la seule classe de l'action ne montre pas.
	 */
	private function renderCommit(array $input): string {

		return $this->runPage('learning/dateEnrollment:doCommit', 'POST', $input, function(\Action $action): string {

			if(($action instanceof \ViewAction) === FALSE) {
				return get_class($action);
			}

			$level = ob_get_level();
			ob_start();
			$action->run();
			$output = '';
			while(ob_get_level() > $level) {
				$output = ob_get_clean().$output;
			}
			return $output;

		});

	}

	/**
	 * La colonne « À confirmer » est celle du réseau, pas celle d'un onglet du filtre « Type » : la
	 * page la décide sur toute la liste, avant de la réduire au type retenu. Une demande envoyée
	 * avant que le réseau cesse de confirmer la garde donc sur l'onglet d'un autre type, où chaque
	 * ligne compte 0.
	 *
	 * Le réseau de la base porte déjà ses propres demandes : la colonne masquée ne s'éprouve pas ici,
	 * mais sur `DateEnrollmentLib::hasRequested()` (`CourseNetworkTable::testHasRequested()`).
	 */
	public function testCommunityColumn(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = $this->seedDate(NULL);

		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::PRIVATE, type: Course::REMOTE);
		$this->createDate($eCourseRemote, NULL);

		// Chaque lecture est une requête neuve : `Structure::community()` garde le groupe en cache
		// statique pour tout le processus, qui ne verrait pas le réglage changé entre deux lectures.
		$read = function(?string $type): \stdClass {
			new \ReflectionProperty(\structure\Structure::class, 'eCommunity')->setValue(NULL, []);
			return $this->runPage('groupe/'.$this->eStructureNetwork['id'].'/formations', 'GET', ['id' => (string)$this->eStructureNetwork['id']] + ($type === NULL ? [] : ['type' => $type]), fn(\Action $action) => new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action));
		};

		$this->setConfirm(TRUE);

		$this->assertTrue($read(Course::REMOTE)->withRequested, 'un réseau qui confirme porte la colonne, sans demande');

		$this->enroll($this->createPerson(), $eDate);
		$this->setConfirm(FALSE);

		$this->assertEquals(DateEnrollment::REQUESTED, DateEnrollment::model()->whereDate($eDate)->getValue('status'), 'la demande reste à confirmer');

		$dataAll = $read(NULL);

		$this->assertTrue($dataAll->withRequested, 'sans confirmation, une demande en attente garde la colonne');

		$dataRemote = $read(Course::REMOTE);

		$this->assertTrue($dataRemote->cCourse->offsetExists($eCourseRemote['id']), 'l\'onglet « Visio » montre la formation en visio');
		$this->assertEquals([Course::REMOTE], array_values(array_unique($dataRemote->cCourse->getColumn('type'))), 'et rien d\'autre');
		$this->assertEquals([0], array_values(array_unique($dataRemote->cCourse->getColumn('requested'))), 'aucune de ses lignes ne porte de demande');
		$this->assertTrue($dataRemote->withRequested, 'la demande portée par une formation en présentiel garde la colonne sur l\'onglet « Visio »');
		$this->assertTrue($read(Course::ONLINE)->withRequested, 'et sur l\'onglet « En ligne », sans formation');

	}

	/**
	 * Ce que `create` prépare pour la vue, comme sur un clic sur un nom d'étape : le rendu ajax du
	 * panneau demande une session que le lanceur de tests n'ouvre pas, et les champs cachés des
	 * étapes 1 et 2 sont ceux de `$data->choices`.
	 */
	private function prepareCreate(array $input): \stdClass {

		return $this->runPage('learning/dateEnrollment:create', 'GET', $input, fn(\Action $action) => new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action));

	}

	/**
	 * Une page jouée sous la méthode et les paramètres d'une vraie requête, l'action qu'elle lève
	 * passée à `$onAction` avant que la requête simulée ne soit défaite : une vue se rend sous la
	 * méthode de sa requête.
	 */
	private function runPage(string $page, string $method, array $input, \Closure $onAction): mixed {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = match($method) {
			'GET' => [$input, [], $input],
			'POST' => [[], $input, $input],
		};

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $page), new \stdClass());
			throw new \Exception($page.' n\'a levé aucune action');
		} catch(\Action $action) {
			return $onAction($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private function finish(array $spawned): string {

		[$process, $pipes] = $spawned;

		$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return $output;

	}

}
?>
