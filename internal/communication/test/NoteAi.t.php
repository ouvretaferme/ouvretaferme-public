<?php
namespace communication;

/**
 * Les notes de l'équipe : qui les reçoit, ce qu'elles figent à l'envoi, et ce que le formulaire
 * refuse.
 *
 * Le décor est celui des notifications (`NotificationFixtureTest`) : une ferme installée avec
 * boutiques, administrée par un compte fictif et portant deux membres qui ne l'administrent pas, et
 * un groupe de formation administré par le même compte et par le permanent de la ferme, son formateur.
 *
 * ⚠️ La base de dev est une copie de la production : 5 704 fermes installées actives le 23/09/2026.
 * Chaque cas borne donc les destinataires au décor par `NotificationSetting::$enabled`, le seul
 * filtre que `getRecipients()` applique après sa requête. Sans cette borne, un envoi écrirait une
 * ligne chez chaque administrateur du parc de dev.
 */
class NoteAiTest extends \notification\NotificationFixtureTest {

	const TITLE = 'zzTest note de l\'équipe';

	public function setUp(): void {

		parent::setUp();

		$this->restrictToFixtures();
		$this->cleanNotes();

	}

	public function finalize(): void {

		$this->cleanNotes();

		parent::finalize();

	}

	public function testRecipientsAreTheAdministrators(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertAdministrators();

		$cMember = NoteLib::getRecipients($this->getNote(['producer-real']));

		$this->assertEquals([$this->eUserAdmin['id']], $this->getUserIds($cMember), 'seul l\'administrateur de la ferme reçoit la note, pas ses deux autres membres');
		$this->assertEquals([$this->eStructure['id']], $this->getStructureIds($cMember), 'et seulement dans la ferme, le groupe n\'étant pas coché');

	}

	public function testRecipientsFollowTheStructureTypes(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals([$this->eStructureCommunity['id']], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['community-education']))), 'un groupe de formation reçoit ce qui vise les groupes de formation');
		$this->assertEquals([], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['producer-test', 'community-network']))), 'aucune structure du décor n\'est une ferme de test ni un réseau');
		$this->assertEquals([$this->eStructure['id'], $this->eStructureCommunity['id']], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['producer-real', 'community-education']))), 'deux types cochés, deux structures');

	}

	public function testRecipientsUseEveryCheckedFeature(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertEquals([$this->eStructure['id']], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['producer-real'], ['hasShops']))), 'la ferme du décor a des boutiques');
		$this->assertEquals([], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['producer-real'], ['hasShops', 'hasPdp']))), 'elle n\'a pas la facturation électronique, et une fonctionnalité manquante suffit à l\'écarter');

	}

	public function testRecipientsRespectEnabled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		\notification\NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => $eStructure['id'] === $this->eStructureCommunity['id'];

		$this->assertEquals([$this->eStructureCommunity['id']], $this->getStructureIds(NoteLib::getRecipients($this->getNote(['producer-real', 'community-education']))), 'une structure où les notifications sont fermées ne reçoit rien');

	}

	public function testCreateFreezesTheRecipients(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertAdministrators();

		$unseenStructure = $this->getUnseen($this->eUserAdmin);
		$unseenCommunity = $this->getUnseen($this->eUserAdmin, $this->eStructureCommunity);
		$unseenPermanent = $this->getUnseen($this->eUserPermanent);
		$unseenPermanentCommunity = $this->getUnseen($this->eUserPermanent, $this->eStructureCommunity);

		$eNote = $this->getNote(['producer-real', 'community-education']);
		$eNote['title'] = self::TITLE;
		$eNote['content'] = '<p>zzTest</p>';

		\user\ConnectionLib::setOnline($this->eUserAdmin);

		$fw = new \FailWatch();

		NoteLib::create($eNote);

		\user\ConnectionLib::setOnline(new \user\User());

		$this->assertTrue($fw->ok(), 'la note part : '.implode(', ', array_column($fw->get(), 1)));

		$eNoteSaved = NoteLib::getById($eNote['id']);

		$this->assertEquals(2, $eNoteSaved['structures'], 'deux structures, figées sur la note');
		$this->assertEquals(2, $eNoteSaved['users'], 'l\'administrateur des deux structures compte pour une personne, le formateur du groupe pour une autre');
		$this->assertEquals($this->eUserAdmin['id'], $eNoteSaved['createdBy']['id'], 'l\'auteur est le compte connecté');

		$cNoteRecipient = NoteRecipient::model()
			->select(['user', 'structure'])
			->whereNote($eNote)
			->getCollection();

		$recipients = [];

		foreach($cNoteRecipient as $eNoteRecipient) {
			$recipients[] = [$eNoteRecipient['structure']['id'], $eNoteRecipient['user']['id']];
		}

		sort($recipients);

		$recipientsExpected = [
			[$this->eStructure['id'], $this->eUserAdmin['id']],
			[$this->eStructureCommunity['id'], $this->eUserAdmin['id']],
			[$this->eStructureCommunity['id'], $this->eUserPermanent['id']],
		];

		sort($recipientsExpected);

		$this->assertEquals($recipientsExpected, $recipients, 'une ligne par administrateur et par structure qu\'il administre');
		$this->assertEquals(0, NoteRecipient::model()->whereNote($eNote)->where('seenAt IS NOT NULL OR readAt IS NOT NULL')->count(), 'ni vue ni lue à l\'envoi');

		$this->assertEquals($unseenStructure + 1, $this->getUnseen($this->eUserAdmin), 'le compteur de la ferme monte d\'un cran');
		$this->assertEquals($unseenCommunity + 1, $this->getUnseen($this->eUserAdmin, $this->eStructureCommunity), 'celui du groupe aussi');
		$this->assertEquals($unseenPermanent, $this->getUnseen($this->eUserPermanent), 'celui du permanent sur la ferme, qu\'il n\'administre pas, ne bouge pas');
		$this->assertEquals($unseenPermanentCommunity + 1, $this->getUnseen($this->eUserPermanent, $this->eStructureCommunity), 'celui du permanent sur le groupe, qu\'il administre, monte d\'un cran');

	}

	public function testCreateWithoutRecipientsIsRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eNote = $this->getNote(['producer-test']);
		$eNote['title'] = self::TITLE;
		$eNote['content'] = '<p>zzTest</p>';

		$fw = new \FailWatch();

		NoteLib::create($eNote);

		$this->assertTrue($fw->has('Note::recipients.empty'), 'une note sans destinataire est refusée');
		$this->assertEquals(0, Note::model()->whereTitle(self::TITLE)->count(), 'et n\'est pas écrite');

	}

	public function testBuild(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$input = [
			'title' => self::TITLE,
			'content' => '<p>zzTest</p>',
			'buttonLabel' => '',
			'buttonPath' => '',
			'structureTypes' => ['producer-real', 'shop'],
			'features' => ['hasShops'],
		];

		$fw = new \FailWatch();
		$eNote = new Note();
		$eNote->build(NoteLib::getPropertiesCreate(), $input);

		$this->assertTrue($fw->ok(), 'un formulaire complet passe : '.implode(', ', array_column($fw->get(), 1)));
		$this->assertEquals(['producer-real', 'shop'], $eNote['structureTypes'], 'les types sont gardés tels que cochés');
		$this->assertEquals(NULL, $eNote['buttonLabel'], 'sans bouton, le libellé est vide');

		// Un POST forgé peut nommer ses clés et répéter une case : la note stocke une liste.
		$fw = new \FailWatch();
		$eNote = new Note();
		$eNote->build(NoteLib::getPropertiesCreate(), ['structureTypes' => ['x' => 'amap', 'y' => 'shop', 'z' => 'amap'], 'features' => ['a' => 'hasShops']] + $input);

		$this->assertTrue($fw->ok(), 'des clés nommées et un doublon passent');
		$this->assertEquals(['amap', 'shop'], $eNote['structureTypes'], 'les types sont rangés en liste, sans doublon');
		$this->assertEquals(['hasShops'], $eNote['features'], 'les fonctionnalités sont rangées en liste');

		$this->assertBuildRefused(['structureTypes' => []] + $input, 'Note::structureTypes.check', 'aucun type coché');
		$this->assertBuildRefused(['structureTypes' => ['producer-real', 'community-accounting']] + $input, 'Note::structureTypes.check', 'un type hors de la liste');
		$this->assertBuildRefused(['features' => ['hasShops', 'production']] + $input, 'Note::features.check', 'une fonctionnalité hors de la liste');
		$this->assertBuildRefused(['buttonLabel' => 'Voir'] + $input, 'Note::buttonPath.missing', 'un libellé sans page, sur le champ de la page,');
		$this->assertBuildRefused(['buttonPath' => '/x/banque/operations'] + $input, 'Note::buttonLabel.missing', 'une page sans libellé, sur le champ du libellé,');

		$fw = new \FailWatch();
		new Note()->build(NoteLib::getPropertiesCreate(), ['buttonPath' => '/x/banque/operations'] + $input);
		$this->assertFalse($fw->has('Note::buttonPath.missing'), 'une page sans libellé ne se reproche pas aussi à la page');

		$fw = new \FailWatch();
		$eNote = new Note();
		$eNote->build(NoteLib::getPropertiesCreate(), ['buttonLabel' => 'Voir', 'buttonPath' => '/x/banque/operations'] + $input);
		$this->assertTrue($fw->ok(), 'un bouton complet passe : '.implode(', ', array_column($fw->get(), 1)));
		$this->assertBuildRefused(['buttonLabel' => 'Voir', 'buttonPath' => 'https://www.ouvretaferme.org/'] + $input, 'Note::buttonPath.path', 'une adresse externe');
		$this->assertBuildRefused(['buttonLabel' => 'Voir', 'buttonPath' => '/'.str_repeat('a', 100)] + $input, 'Note::buttonPath.check', 'une page trop longue');

	}

	public function testIsPath(): void {

		$this->assertTrue(NoteLib::isPath(NULL), 'sans bouton');
		$this->assertTrue(NoteLib::isPath('/x/banque/operations'), 'une page de la structure');
		$this->assertTrue(NoteLib::isPath('/presentation/formations?tri=date#haut'), 'une page du site, avec paramètres et ancre');

		$this->assertFalse(NoteLib::isPath('https://www.ouvretaferme.org/'), 'une adresse complète');
		$this->assertFalse(NoteLib::isPath('//evil.example/'), 'une adresse sans protocole, que le navigateur lit comme un autre domaine');
		$this->assertFalse(NoteLib::isPath('/\\evil.example/'), 'une barre inverse, que le navigateur lit comme « // »');
		$this->assertFalse(NoteLib::isPath('banque/operations'), 'une adresse relative');
		$this->assertFalse(NoteLib::isPath('/x/banque operations'), 'une espace');

	}

	/**
	 * Les deux formes de `StructureSentinelAiLib` prennent l'id du destinataire, et rien d'autre ne
	 * bouge : un « x » ailleurs dans l'adresse est une donnée, pas la sentinelle.
	 */
	public function testButtonUrlTakesTheRecipientStructure(): void {

		$eStructure = new \structure\Structure(['id' => \structure\FixtureAiLib::A1]);

		$this->assertEquals('/'.$eStructure['id'].'/banque/operations', NoteLib::getButtonUrl('/x/banque/operations', $eStructure));
		$this->assertEquals('/ferme/'.$eStructure['id'], NoteLib::getButtonUrl('/ferme/x', $eStructure));
		$this->assertEquals('/ferme/'.$eStructure['id'].'/notifications/reglages', NoteLib::getButtonUrl('/ferme/x/notifications/reglages', $eStructure));
		$this->assertEquals('/ferme/'.$eStructure['id'].'/x', NoteLib::getButtonUrl('/ferme/x/x', $eStructure), 'seul le premier x est la sentinelle');

		$this->assertEquals('/x', NoteLib::getButtonUrl('/x', $eStructure), '/x seul n\'a pas de cible');
		$this->assertEquals('/xavier/banque', NoteLib::getButtonUrl('/xavier/banque', $eStructure), 'un segment qui commence par x');
		$this->assertEquals('/presentation/x', NoteLib::getButtonUrl('/presentation/x', $eStructure), 'un x plus loin dans l\'adresse');

	}

	/**
	 * Le formulaire range les types par famille, dans une liste à lui : un type ajouté aux réglages
	 * sans famille ne serait proposé nulle part, et un type retiré laisserait une case qui lève. Les
	 * libellés et les fonctionnalités viennent des mêmes listes que la règle de `build()`.
	 */
	public function testFormOffersEveryTarget(): void {

		$families = array_merge(...array_values(NoteUi::getStructureTypeFamilies()));
		$structureTypes = array_keys(CommunicationSetting::NOTE_STRUCTURE_TYPES);

		$this->assertEquals([], array_values(array_diff($structureTypes, $families)), 'types sans famille dans le formulaire : '.implode(', ', array_diff($structureTypes, $families)));
		$this->assertEquals([], array_values(array_diff($families, $structureTypes)), 'familles qui citent un type inconnu : '.implode(', ', array_diff($families, $structureTypes)));
		$this->assertEquals(count($families), count(array_unique($families)), 'un type rangé dans deux familles');

		$this->assertEquals($structureTypes, array_keys(NoteUi::p('structureTypes')->values), 'un libellé par type, dans l\'ordre des réglages');
		$this->assertEquals(CommunicationSetting::NOTE_FEATURES, array_keys(NoteUi::p('features')->values), 'un libellé par fonctionnalité, dans l\'ordre des réglages');

	}

	public function testCentreMergesTheNote(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->emitSale(1200, 900);
		$eNote = $this->sendNote(['producer-real', 'community-education']);

		$cNotification = \notification\NotificationLib::getByStructure($this->eStructure, $this->eUserAdmin);

		$cNotificationCommon = $cNotification->find(fn(\notification\Notification $eNotification) => $eNotification['source'] === 'communication');

		$this->assertEquals(2, $cNotification->count(), 'la commande et la note, dans le même centre');
		$this->assertEquals(1, $cNotificationCommon->count(), 'chaque ligne porte sa source');
		$this->assertEquals($eNote['id'], $cNotificationCommon->first()['reference'], 'la note renvoie à son envoi');
		$this->assertEquals(2, $this->getUnseen($this->eUserAdmin), 'le compteur additionne les deux');

		$this->assertEquals(1, \notification\NotificationLib::getByStructure($this->eStructureCommunity, $this->eUserAdmin)->count(), 'le groupe reçoit la note seule');
		$this->assertEquals(0, \notification\NotificationLib::getByStructure($this->eStructure, $this->eUserPermanent)->count() - $this->countNotifications($this->eUserPermanent), 'un membre qui n\'administre pas ne la voit pas');

	}

	/**
	 * Le clic d'une même note mène chaque destinataire dans sa propre structure : la ferme et le
	 * groupe du décor ne reçoivent pas la même adresse.
	 */
	public function testClickLeadsIntoTheRecipientStructure(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eNote = $this->sendNote(['producer-real', 'community-education'], '/ferme/x/notifications/reglages');

		foreach([$this->eStructure, $this->eStructureCommunity] as $eStructure) {

			$eNotification = \notification\NotificationLib::getByUser($eStructure, $this->eUserAdmin, $this->getNoteRecipientId($eNote, $eStructure), 'communication');

			$this->assertEquals('/ferme/'.$eStructure['id'].'/notifications/reglages', NotificationLib::getUrl($eNotification), 'le clic depuis la structure '.$eStructure['id']);

		}

	}

	public function testSeenInOneStructureIsSeenInTheOthers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->emitLearning(\learning\NotificationLib::COMMENT, 1300);
		$this->sendNote(['producer-real', 'community-education'], '/x/banque/operations');

		$this->assertEquals(2, $this->getUnseen($this->eUserAdmin, $this->eStructureCommunity), 'contrôle du décor : le commentaire et la note, non vus dans le groupe');

		$this->assertTrue(\notification\NotificationLib::see($this->eStructure, $this->eUserAdmin, 1), 'l\'ouverture de la ferme éteint son compteur');

		$this->assertEquals(0, $this->getUnseen($this->eUserAdmin), 'rien de non vu dans la ferme');
		$this->assertEquals(1, $this->getUnseen($this->eUserAdmin, $this->eStructureCommunity), 'la note vue dans la ferme l\'est dans le groupe, le commentaire reste à voir');

		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereSeenAt(NULL)->count(), 'les deux lignes de la note sont vues');
		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->where('readAt IS NOT NULL')->count(), 'et aucune n\'est lue, c\'est son bouton qui la lira');

	}

	public function testSeenWithoutButtonIsRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->sendNote(['producer-real', 'community-education']);
		$this->sendNote(['producer-real', 'community-education'], '/x/banque/operations');

		$this->assertEquals(4, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereReadAt(NULL)->count(), 'contrôle du décor : deux notes non lues, dans la ferme et dans le groupe');

		\notification\NotificationLib::see($this->eStructure, $this->eUserAdmin, 2);

		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereSeenAt(NULL)->count(), 'les deux notes sont vues partout');
		$this->assertEquals(2, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereReadAt(NULL)->count(), 'seule la note avec bouton reste à lire, dans ses deux structures');
		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereReadAt(NULL)->whereNote('IN', Note::model()->select('id')->whereButtonLabel(NULL)->getSql())->count(), 'la note sans bouton est lue dans la ferme et dans le groupe');

	}

	public function testReadInOneStructureIsReadInTheOthers(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->sendNote(['producer-real', 'community-education']);

		$eNotificationCommon = \notification\NotificationLib::getByStructure($this->eStructure, $this->eUserAdmin)->first();

		$this->assertTrue(\notification\NotificationLib::getByUser($this->eStructure, $this->eUserAdmin, $eNotificationCommon['id'])->empty(), 'sans sa source, l\'id de la note est cherché dans la base de la ferme, vide pour ce compte');
		$this->assertTrue(\notification\NotificationLib::getByUser($this->eStructure, $this->eUserAdmin, $eNotificationCommon['id'], 'inconnue')->empty(), 'une source que le moteur ne connaît pas ne trouve rien');
		$this->assertTrue(\notification\NotificationLib::getByUser($this->eStructure, $this->eUserPermanent, $eNotificationCommon['id'], 'communication')->empty(), 'la note d\'un autre ne se retrouve pas');

		$eNotification = \notification\NotificationLib::getByUser($this->eStructure, $this->eUserAdmin, $eNotificationCommon['id'], 'communication');

		\notification\NotificationLib::read($this->eStructure, $eNotification);

		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereReadAt(NULL)->count(), 'lue dans la ferme, elle l\'est dans le groupe');
		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereSeenAt(NULL)->count(), 'et lue vaut vue');
		$this->assertEquals(0, $this->getUnseen($this->eUserAdmin, $this->eStructureCommunity), 'le compteur du groupe descend avec');

		$this->assertEquals(\notification\NotificationUi::url($this->eStructure), \communication\NotificationLib::getUrl($eNotification), 'sans bouton, le clic mène au centre');

	}

	public function testReadAllReachesTheOtherStructures(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->emitSale(1400, 910);
		$this->sendNote(['producer-real', 'community-education']);

		\notification\NotificationLib::readAll($this->eStructureCommunity, $this->eUserAdmin);

		$this->assertEquals(0, NoteRecipient::model()->whereUser($this->eUserAdmin)->whereReadAt(NULL)->count(), 'tout lire dans le groupe lit la note dans la ferme');
		$this->assertEquals(1, $this->getUnseen($this->eUserAdmin), 'la commande de la ferme reste à voir');

	}

	public function testGroupKeepsTheSourcesApart(): void {

		// Un cas précédent a pu substituer les doubles du décor, qui ne connaissent pas la note.
		\notification\NotificationSetting::$resolve = $this->resolveInitial;

		$eStructure = new \structure\Structure(['id' => 1]);

		$cNotification = new \Collection([
			new \notification\Notification(['id' => 5, 'source' => 'communication', 'type' => NotificationLib::NOTE, 'group' => NULL, 'content' => [], 'eStructure' => $eStructure, 'seenAt' => NULL, 'readAt' => NULL, 'createdAt' => \Clock::datetime()]),
			new \notification\Notification(['id' => 5, 'source' => NULL, 'type' => NotificationLib::NOTE, 'group' => NULL, 'content' => [], 'eStructure' => $eStructure, 'seenAt' => NULL, 'readAt' => NULL, 'createdAt' => \Clock::datetime()]),
		]);

		$this->assertEquals(2, \notification\NotificationLib::group($cNotification)->count(), 'deux lignes de même id et de sources différentes restent deux');

	}

	public function testCleanCommon(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertAdministrators();

		$eNote = $this->sendNote(['producer-real', 'community-education']);

		NoteRecipient::model()
			->whereNote($eNote)
			->whereStructure($this->eStructureCommunity)
			->update([
				'createdAt' => new \Sql('NOW() - INTERVAL '.(\notification\NotificationSetting::DAYS + 1).' DAY')
			]);

		$users = array_map(function(array $userIds) {
			sort($userIds);
			return $userIds;
		}, NotificationLib::cleanCommon());

		$usersExpected = [$this->eUserAdmin['id'], $this->eUserPermanent['id']];
		sort($usersExpected);

		$this->assertEquals([$this->eStructureCommunity['id'] => $usersExpected], $users, 'seules les lignes passées, celles du groupe, rendent leur adhésion à recaler');
		$this->assertEquals(1, NoteRecipient::model()->whereNote($eNote)->count(), 'celles du groupe sont effacées, celle de la ferme reste');
		$this->assertEquals(1, Note::model()->whereId($eNote)->count(), 'la note reste dans la liste des envois');

	}

	public function testViewsAndReadsSurviveTheClean(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->assertAdministrators();

		$eNote = $this->sendNote(['producer-real', 'community-education'], '/x/banque/operations');

		$this->assertEquals([0, 0], $this->getCounts($eNote), 'personne n\'a encore vu ni lu la note');

		\notification\NotificationLib::see($this->eStructure, $this->eUserAdmin, 1);

		$this->assertEquals([1, 0], $this->getCounts($eNote), 'l\'administrateur a vu la note à bouton sans la lire');

		\notification\NotificationLib::readAll($this->eStructureCommunity, $this->eUserAdmin);

		$this->assertEquals([1, 1], $this->getCounts($eNote), 'il l\'a vue et lue dans ses deux structures et compte une fois, le permanent ne l\'a ni vue ni lue');

		NoteRecipient::model()
			->whereNote($eNote)
			->update([
				'createdAt' => new \Sql('NOW() - INTERVAL '.(\notification\NotificationSetting::DAYS + 1).' DAY')
			]);

		NotificationLib::cleanCommon();

		$this->assertEquals(0, NoteRecipient::model()->whereNote($eNote)->count(), 'les lignes de la note sont effacées');
		$this->assertEquals([1, 1], $this->getCounts($eNote), 'et la liste des envois garde les deux nombres');

	}

	private function getCounts(Note $eNote): array {

		$eNoteListed = NoteLib::getList()->find(fn(Note $eNoteListed) => $eNoteListed['id'] === $eNote['id'])->first();

		return [$eNoteListed['views'], $eNoteListed['reads']];

	}

	private function sendNote(array $structureTypes, ?string $buttonPath = NULL): Note {

		$eNote = $this->getNote($structureTypes);
		$eNote['title'] = self::TITLE;
		$eNote['content'] = '<p>zzTest</p>';
		$eNote['buttonLabel'] = ($buttonPath === NULL) ? NULL : 'zzTest';
		$eNote['buttonPath'] = $buttonPath;

		\user\ConnectionLib::setOnline($this->eUserAdmin);

		NoteLib::create($eNote);

		\user\ConnectionLib::setOnline(new \user\User());

		return $eNote;

	}

	private function getNoteRecipientId(Note $eNote, \structure\Structure $eStructure): int {

		return NoteRecipient::model()
			->select('id')
			->whereNote($eNote)
			->whereUser($this->eUserAdmin)
			->whereStructure($eStructure)
			->get()['id'];

	}

	private function assertBuildRefused(array $input, string $failName, string $message): void {

		$fw = new \FailWatch();
		new Note()->build(NoteLib::getPropertiesCreate(), $input);

		$this->assertTrue($fw->has($failName), $message.' est refusé en '.$failName.' (rendu : '.implode(', ', array_column($fw->get(), 1)).')');

	}

	private function getNote(array $structureTypes, array $features = []): Note {

		return new Note([
			'structureTypes' => $structureTypes,
			'features' => $features,
		]);

	}

	private function restrictToFixtures(): void {

		\notification\NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => in_array($eStructure['id'], [$this->eStructure['id'], $this->eStructureCommunity['id']], TRUE);

	}

	/**
	 * Les destinataires attendus se déclarent d'après le décor et non d'après la requête du produit,
	 * qui est ce que le test éprouve : cette prémisse vérifie que le décor dit bien ce qu'on en déclare.
	 */
	private function assertAdministrators(): void {

		$this->assertTrue($this->getMemberOf($this->eUserAdmin, $this->eStructure)->isAdmin(), 'prémisse : le compte fictif administre la ferme');
		$this->assertTrue($this->getMemberOf($this->eUserAdmin, $this->eStructureCommunity)->isAdmin(), 'prémisse : il administre aussi le groupe');
		$this->assertTrue($this->getMemberOf($this->eUserPermanent, $this->eStructureCommunity)->isAdmin(), 'prémisse : le permanent de la ferme est formateur, administrateur du groupe');
		$this->assertFalse($this->getMemberOf($this->eUserPermanent, $this->eStructure)->isAdmin(), 'prémisse : il n\'administre pas la ferme');

	}

	private function getUserIds(\Collection $cMember): array {
		return array_values(array_unique($cMember->getColumnCollection('user')->getIds()));
	}

	private function getStructureIds(\Collection $cMember): array {

		$ids = array_values(array_unique($cMember->getColumnCollection('structure')->getIds()));
		sort($ids);

		return $ids;

	}

	/**
	 * Hors Lib : une note ne s'efface jamais par le produit, la liste des envois gardant tout.
	 */
	private function cleanNotes(): void {

		$cNote = Note::model()
			->select('id')
			->whereTitle(self::TITLE)
			->getCollection();

		if($cNote->empty()) {
			return;
		}

		NoteRecipient::model()
			->whereNote('IN', $cNote)
			->delete();

		Note::model()
			->whereId('IN', $cNote)
			->delete();

	}

}
?>
