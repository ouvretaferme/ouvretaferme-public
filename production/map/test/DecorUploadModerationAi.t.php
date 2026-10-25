<?php
namespace map;

/**
 * La modération a posteriori des modèles 3D du décor importés (`DecorUploadLib`, `page/admin/decorUpload.p.php`) : l'équipe
 * garde ou retire un modèle déjà montré sur le plan de sa structure, et le retrait part par e-mail à son auteur. Un modèle
 * dont l'auteur a accepté le partage se sélectionne, et l'auteur en est remercié.
 *
 * Ce qui s'y prouve :
 * 1. garder fait passer un envoi neuf à gardé, une seule fois ; « Tout valider » garde les modèles que la page affichait et
 *    qui sont encore dans la file, ignore un modèle retiré depuis, laisse à examiner celui arrivé après l'affichage, et ne
 *    garde rien d'une liste plus longue que la file ;
 * 2. retirer efface le fichier et écrit le motif, sur un envoi neuf, gardé ou sélectionné ; le bâtiment reprend sa
 *    composition et redevient modifiable, l'élément perd son modèle ; un envoi dont le bâtiment a été supprimé entre-temps
 *    rend le bandeau du Lib et non l'erreur du cadriciel ;
 * 3. un retrait demande un motif de la liste et une précision d'au plus 500 caractères ;
 * 4. seule l'équipe modère, et lit le fichier par la route du binaire tant qu'il n'est pas retiré ;
 * 5. la file ne montre que les envois neufs qui ont un fichier, sans leur hash, celui du lien d'alerte en tête, et
 *    l'historique les retirés et les gardés ; le filtre « Partage accepté » restreint les deux ;
 * 6. sélectionner n'accepte qu'un modèle partagé et montré, une seule fois, garde un modèle encore à examiner sans réécrire
 *    le premier examen d'un modèle déjà gardé, et passe la sélection en tête de l'historique ;
 * 7. l'e-mail de retrait va à l'auteur avec la précision échappée et ne part pas sans adresse, le remerciement de même ;
 *    l'alerte Discord ne nomme pas l'auteur et ne part pas hors production.
 *
 * Décor : l'administrateur de `FixtureAiLib::F1` importe, le compte du décor qui n'a aucune structure modère, son id posé
 * dans `\user\UserSetting::$admin` le temps du test, dans une transaction annulée à la fin de chaque méthode ; les fichiers
 * écrits sont effacés à la fin.
 *
 * Lancement : dev/test package=map file=DecorUploadModerationAi
 */
class DecorUploadModerationAiTest extends \dev\ApiTest {

	const MODERATOR = 'decor.sans.structure@example.org';

	private \user\User $eUserAuthor;
	private \user\User $eUserModerator;

	private array $adminInitial;
	private ?string $contestInitial;
	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return new DecorUploadModel();
	}

	public function setUp(): void {

		$this->adminInitial = \user\UserSetting::$admin;
		$this->contestInitial = \user\UserSetting::$avatarUploadContest;

		parent::setUp();

		$this->hashesInitial = $this->getHashes();

		$this->eUserAuthor = $this->getAdmin();
		$this->eUserModerator = \user\UserLib::getByEmail(self::MODERATOR);

		if($this->eUserModerator->empty()) {
			throw new \Exception('Compte '.self::MODERATOR.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		\user\UserSetting::$admin = [$this->eUserModerator['id']];
		\user\ConnectionLib::setOnline($this->eUserModerator);

	}

	public function tearDown(): void {

		// Les médias écrits par l'envoi disparaissent avec la transaction, pas leurs fichiers : ils se lisent donc avant elle
		$files = array_map(
			fn(string $hash) => \storage\DriverLib::directory().'/'.new \media\MapDecorUploadUi()->getBasenameByHash($hash),
			array_diff($this->getHashes(), $this->hashesInitial)
		);

		parent::tearDown();

		\user\UserSetting::$admin = $this->adminInitial;
		\user\UserSetting::$avatarUploadContest = $this->contestInitial;

		foreach($files as $file) {
			if(is_file($file)) {
				unlink($file);
			}
		}

	}

	public function testKeep(): void {

		$eDecorUpload = $this->createBuilding();

		$this->assertEquals(1, DecorUploadLib::keep($this->get($eDecorUpload)), 'envoi neuf non gardé');

		$eDecorUploadKept = $this->get($eDecorUpload);

		$this->assertEquals(DecorUpload::KEPT, $eDecorUploadKept['status'], 'statut après garder');
		$this->assertEquals($this->eUserModerator['id'], $eDecorUploadKept['moderatedBy']['id'], 'admin du geste non écrit');
		$this->assertTrue($eDecorUploadKept['moderatedAt'] !== NULL, 'date du geste non écrite');
		$this->assertEquals(0, DecorUploadLib::keep($eDecorUploadKept), 'envoi gardé deux fois');

	}

	/**
	 * Un modèle retiré depuis sa carte, puis « Tout valider » sans recharger, la page postant encore son id.
	 */
	public function testKeepAll(): void {

		$eDecorUploadShown = $this->createBuilding();
		$eDecorUploadWithdrawn = $this->createObject();
		$idsShown = [$eDecorUploadShown['id'], $eDecorUploadWithdrawn['id']];

		[, $waiting] = DecorUploadLib::getWaiting(0, FALSE);
		[, $examined] = DecorUploadLib::getExamined(0, FALSE);

		$this->withdraw($eDecorUploadWithdrawn, DecorUpload::BRAND, 'Le logo sur le toit.');

		$eDecorUploadLate = $this->createBuilding();

		$this->assertEquals(\ReloadAction::class, $this->keepAll($this->eUserModerator, $idsShown), 'file affichée non gardée');
		$this->assertEquals(DecorUpload::KEPT, $this->get($eDecorUploadShown)['status'], 'modèle affiché non gardé');
		$this->assertEquals(DecorUpload::WITHDRAWN, $this->get($eDecorUploadWithdrawn)['status'], 'modèle retiré gardé par sa carte restée affichée');
		$this->assertEquals(DecorUpload::NEW, $this->get($eDecorUploadLate)['status'], 'modèle arrivé après l\'affichage gardé sans avoir été vu');

		// Deux sortis de la file (le gardé, le retiré), un entré (le dernier venu) ; l'historique prend le gardé et le retiré
		$this->assertEquals($waiting - 1, DecorUploadLib::getWaiting(0, FALSE)[1], 'compteur de la file');
		$this->assertEquals($examined + 2, DecorUploadLib::getExamined(0, FALSE)[1], 'compteur des examinés');

		$this->assertEquals('map\\DecorUpload::keepAllNone', $this->keepAll($this->eUserModerator, $idsShown), 'file déjà examinée gardée de nouveau');
		$this->assertEquals('map\\DecorUpload::keepAllNone', $this->keepAll($this->eUserModerator, []), 'file vide gardée');

		// Plus d'ids que la file n'en affiche : rien ne part, pas même le seul envoi encore à examiner de la liste
		$idsForged = array_merge([$eDecorUploadLate['id']], range($eDecorUploadLate['id'] + 1, $eDecorUploadLate['id'] + MapSetting::MAX_BY_PAGE));

		$this->assertEquals('map\\DecorUpload::keepAllNone', $this->keepAll($this->eUserModerator, $idsForged), 'liste plus longue que la file acceptée');
		$this->assertEquals(DecorUpload::NEW, $this->get($eDecorUploadLate)['status'], 'liste plus longue que la file gardée');

		// Hors de l'équipe, `validateAdmin()` arrête la page avant toute action
		try {
			$this->keepAll($this->eUserAuthor, [$eDecorUploadLate['id']]);
		} catch(\Exception) {
		}

		$this->assertEquals(DecorUpload::NEW, $this->get($eDecorUploadLate)['status'], 'file gardée hors de l\'équipe');

	}

	public function testWithdrawBuilding(): void {

		$eDecorUpload = $this->createBuilding();
		$hash = $this->get($eDecorUpload)['hash'];

		$this->assertFalse(DecorBuildingLib::getById($eDecorUpload['building']['id'])->acceptUpdate(), 'bâtiment importé qui se modifie');

		$eDecorUploadWithdrawn = $this->withdraw($eDecorUpload, DecorUpload::BRAND, 'Le logo sur le toit.');

		$this->assertEquals(DecorUpload::WITHDRAWN, $eDecorUploadWithdrawn['status'], 'statut après retrait');
		$this->assertEquals(DecorUpload::BRAND, $eDecorUploadWithdrawn['reason'], 'motif non écrit');
		$this->assertEquals('Le logo sur le toit.', $eDecorUploadWithdrawn['reasonText'], 'précision non écrite');
		$this->assertEquals(NULL, $eDecorUploadWithdrawn['hash'], 'hash gardé après retrait');
		$this->assertEquals(NULL, $eDecorUploadWithdrawn['bakedIndex'], 'index gardé après retrait');
		$this->assertEquals($this->eUserModerator['id'], $eDecorUploadWithdrawn['moderatedBy']['id'], 'admin du retrait non écrit');

		$eDecorBuilding = DecorBuildingLib::getById($eDecorUpload['building']['id']);

		$this->assertTrue($eDecorBuilding['upload']->empty(), 'bâtiment encore sur le modèle retiré');
		$this->assertTrue($eDecorBuilding->acceptUpdate(), 'bâtiment sans modèle qui ne se modifie pas');
		// Le fichier quitte le disque au COMMIT de la transaction la plus haute, celle du test, qui s'annule : le média dit l'effacement
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'fichier gardé après retrait');
		$this->assertFalse($eDecorUploadWithdrawn->acceptWithdraw(), 'envoi retiré encore à retirer');

	}

	public function testWithdrawObject(): void {

		$eDecorUpload = $this->createObject();
		$hash = $this->get($eDecorUpload)['hash'];

		$this->assertEquals(DecorUpload::WITHDRAWN, $this->withdraw($eDecorUpload, DecorUpload::OFFENSIVE, 'Un symbole haineux.')['status'], 'élément non retiré');

		$eDecorObject = DecorObjectLib::getById($eDecorUpload['object']['id']);

		$this->assertTrue($eDecorObject->exists(), 'élément supprimé avec son modèle');
		$this->assertTrue($eDecorObject['upload']->empty(), 'élément encore sur le modèle retiré');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'fichier de l\'élément gardé après retrait');

	}

	/**
	 * Le retrait d'un modèle sélectionné vient après la sélection : c'est lui qui range le modèle dans l'historique, devant
	 * un geste plus récent que la sélection. Horloges en 2037, pour la raison de `testChoose()`.
	 */
	public function testWithdrawChosen(): void {

		$this->setClock('2037-10-25 11:00:00');

		$eDecorUpload = $this->createBuilding(TRUE);

		$this->assertEquals(1, DecorUploadLib::choose($this->getWithAuthor($eDecorUpload)), 'modèle partagé non sélectionné');
		$this->assertTrue($this->get($eDecorUpload)->assertWithdraw(), 'modèle sélectionné refusé au retrait');

		$this->setClock('2037-10-25 12:00:00');

		$this->assertEquals(1, DecorUploadLib::choose($this->getWithAuthor($this->createObject(TRUE))), 'modèle témoin non sélectionné');

		$this->setClock('2037-10-25 13:00:00');

		$this->assertEquals(DecorUpload::WITHDRAWN, $this->withdraw($eDecorUpload, DecorUpload::PERSON, 'Le portrait du voisin.')['status'], 'modèle sélectionné non retiré');
		$this->assertFalse($this->get($eDecorUpload)->acceptChoose(), 'modèle retiré encore à sélectionner');
		$this->assertEquals($eDecorUpload['id'], DecorUploadLib::getExamined(0, TRUE)[0]->first()['id'], 'retrait après la sélection rangé à la date de la sélection');

	}

	/**
	 * Le bâtiment supprimé depuis l'affichage de la liste emporte son envoi : le retrait rend le bandeau du Lib, et non
	 * l'erreur du cadriciel, qui reste celle d'un refus du droit.
	 */
	public function testStale(): void {

		$withdraw = ['reason' => DecorUpload::PERSON, 'reasonText' => 'Le portrait du voisin.'];

		$eDecorUploadStale = $this->createBuilding();
		$eDecorUploadStaleRead = $this->getWithAuthor($eDecorUploadStale);

		DecorBuildingLib::delete(DecorBuildingLib::getById($eDecorUploadStale['building']['id']));

		$eDecorUploadStaleRead->build(DecorUploadLib::getPropertiesWithdraw(), $withdraw);

		$this->assertFalse(DecorUploadLib::withdraw($eDecorUploadStaleRead), 'envoi d\'un bâtiment supprimé retiré, lu avant la suppression');

		$eDecorUploadKept = $this->createBuilding();
		DecorUploadLib::keep($this->get($eDecorUploadKept));

		$eDecorUploadWithdrawn = $this->createBuilding();
		$this->withdraw($eDecorUploadWithdrawn, DecorUpload::OTHER, 'Il s\'affiche mal.');

		$this->assertEquals('map\\DecorUpload::stale', $this->post($this->eUserModerator, 'doWithdraw', $eDecorUploadWithdrawn, $withdraw), 'retrait d\'un envoi déjà retiré');

		// Hors de l'équipe, `validateAdmin()` arrête la page avant toute action
		try {
			$refusal = $this->post($this->eUserAuthor, 'doWithdraw', $eDecorUploadKept, $withdraw);
		} catch(\Exception $e) {
			$refusal = $e->getMessage();
		}

		$this->assertTrue($refusal !== 'map\\DecorUpload::stale', 'refus du droit rendu comme un envoi périmé');
		$this->assertEquals(DecorUpload::KEPT, $this->get($eDecorUploadKept)['status'], 'envoi retiré hors de l\'équipe');

		// Vers la liste : le panneau ouvert par son adresse, rechargé, refuserait l'envoi qu'il vient de retirer
		$this->assertEquals('/map/admin/decorUpload?success=map\\DecorUpload::withdrawn', $this->post($this->eUserModerator, 'doWithdraw', $eDecorUploadKept, $withdraw), 'retrait d\'un envoi gardé sans retour à la liste');

	}

	public function testBuild(): void {

		$this->assertTrue($this->build([])->has('DecorUpload::reason.empty'), 'retrait sans motif accepté');
		$this->assertTrue($this->build(['reason' => 'display'])->has('DecorUpload::reason.check'), 'motif hors liste accepté');
		$this->assertTrue($this->build(['reason' => DecorUpload::OTHER])->has('DecorUpload::reasonText.empty'), 'retrait sans précision accepté');
		$this->assertTrue($this->build(['reason' => DecorUpload::OTHER, 'reasonText' => str_repeat('a', 501)])->has('DecorUpload::reasonText.check'), 'précision de 501 caractères acceptée');
		$this->assertFalse($this->build(['reason' => DecorUpload::OTHER, 'reasonText' => str_repeat('a', 500)])->ko(), 'précision de 500 caractères refusée');

	}

	public function testRights(): void {

		$eDecorUpload = $this->createBuilding();

		$this->assertTrue($this->get($eDecorUpload)->canModerate(), 'équipe sans droit de modérer');
		$this->assertEquals(\DataAction::class, $this->read($this->eUserModerator, $eDecorUpload), 'fichier refusé à l\'équipe, qui n\'est pas membre de la structure');

		\user\ConnectionLib::setOnline($this->eUserAuthor);

		$this->assertFalse($this->get($eDecorUpload)->canModerate(), 'auteur avec le droit de modérer');

		\user\ConnectionLib::setOnline($this->eUserModerator);

		$this->withdraw($eDecorUpload, DecorUpload::OTHER, 'Il s\'affiche mal.');

		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserModerator, $eDecorUpload), 'fichier d\'un modèle retiré servi à l\'équipe');

		// Le droit de l'équipe ne vaut que pour l'équipe
		\user\UserSetting::$admin = [];

		$eDecorUploadOther = $this->createBuilding();

		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserModerator, $eDecorUploadOther), 'fichier servi hors de l\'équipe à qui n\'est pas membre de la structure');

	}

	public function testLists(): void {

		$eDecorUploadPrivate = $this->createBuilding();
		$eDecorUploadShared = $this->createObject(TRUE);

		[$cDecorUploadWaiting, $nDecorUploadWaiting] = DecorUploadLib::getWaiting($eDecorUploadShared['id'], FALSE);

		$this->assertEquals($eDecorUploadShared['id'], $cDecorUploadWaiting->first()['id'], 'envoi du lien d\'alerte hors de la tête');
		$this->assertFalse($cDecorUploadWaiting->first()->offsetExists('hash'), 'hash sorti du Lib');
		$this->assertEquals($nDecorUploadWaiting, DecorUploadLib::countWaiting(), 'compte de la file');
		$this->assertTrue($cDecorUploadWaiting->count() <= MapSetting::MAX_BY_PAGE, 'file sans borne');
		$this->assertTrue(in_array($eDecorUploadPrivate['id'], DecorUploadLib::getWaiting($eDecorUploadPrivate['id'], FALSE)[0]->getIds()), 'envoi sans partage absent de la file');

		[$cDecorUploadWaitingShared] = DecorUploadLib::getWaiting($eDecorUploadPrivate['id'], TRUE);

		$this->assertFalse(in_array($eDecorUploadPrivate['id'], $cDecorUploadWaitingShared->getIds()), 'envoi sans partage sous le filtre');
		$this->assertTrue(in_array($eDecorUploadShared['id'], $cDecorUploadWaitingShared->getIds()), 'envoi partagé absent sous le filtre');

		DecorUploadLib::keep($this->get($eDecorUploadPrivate));
		DecorUploadLib::keep($this->get($eDecorUploadShared));

		$this->assertTrue(in_array($eDecorUploadPrivate['id'], DecorUploadLib::getExamined(0, FALSE)[0]->getIds()), 'envoi gardé absent de l\'historique');
		$this->assertFalse(in_array($eDecorUploadPrivate['id'], DecorUploadLib::getExamined(0, TRUE)[0]->getIds()), 'envoi sans partage dans l\'historique filtré');
		$this->assertFalse(in_array($eDecorUploadPrivate['id'], DecorUploadLib::getWaiting($eDecorUploadPrivate['id'], FALSE)[0]->getIds()), 'envoi gardé encore dans la file');

		$this->withdraw($eDecorUploadPrivate, DecorUpload::PERSON, 'Le portrait du voisin.');

		$this->assertTrue(in_array($eDecorUploadPrivate['id'], DecorUploadLib::getExamined(0, FALSE)[0]->getIds()), 'envoi retiré absent de l\'historique');

	}

	/**
	 * Les horloges sont en 2037, au plus loin que MySQL accepte (`SET timestamp`) : l'historique se lit sur toute la table de la dev, où un geste réel passerait sinon devant
	 * ceux du test.
	 *
	 * Le remerciement ne part que si la sélection s'est écrite : `choose()` rend 0 sur chaque refus, et ne prépare alors
	 * aucun e-mail.
	 */
	public function testChoose(): void {

		$this->setClock('2037-10-25 09:00:00');

		$eDecorUploadPrivate = $this->createBuilding();
		$eDecorUploadNew = $this->createBuilding(TRUE);
		$eDecorUploadKept = $this->createObject(TRUE);

		$this->assertFalse($this->get($eDecorUploadPrivate)->acceptChoose(), 'modèle sans partage à sélectionner');
		$this->assertEquals(0, DecorUploadLib::choose($this->getWithAuthor($eDecorUploadPrivate)), 'modèle sans partage sélectionné');
		$this->assertEquals(NULL, $this->get($eDecorUploadPrivate)['chosenAt'], 'sélection écrite sans partage');

		DecorUploadLib::keep($this->get($eDecorUploadKept));
		$moderatedAt = $this->get($eDecorUploadKept)['moderatedAt'];

		$this->setClock('2037-10-25 11:00:00');

		$this->assertEquals(1, DecorUploadLib::choose($this->getWithAuthor($eDecorUploadNew)), 'modèle neuf partagé non sélectionné');
		$this->assertEquals(1, DecorUploadLib::choose($this->getWithAuthor($eDecorUploadKept)), 'modèle gardé partagé non sélectionné');

		$eDecorUploadNewChosen = $this->get($eDecorUploadNew);
		$eDecorUploadKeptChosen = $this->get($eDecorUploadKept);

		$this->assertEquals($this->eUserModerator['id'], $eDecorUploadNewChosen['chosenBy']['id'], 'admin de la sélection non écrit');
		$this->assertEquals('2037-10-25 11:00:00', $eDecorUploadNewChosen['chosenAt'], 'date de la sélection non écrite');
		$this->assertEquals(DecorUpload::KEPT, $eDecorUploadNewChosen['status'], 'modèle neuf sélectionné sans être gardé');
		$this->assertEquals($moderatedAt, $eDecorUploadKeptChosen['moderatedAt'], 'premier examen réécrit par la sélection');

		$this->assertFalse($eDecorUploadNewChosen->acceptChoose(), 'modèle sélectionné encore à sélectionner');

		// Une autre heure : MySQL ne compte pas une ligne réécrite à l'identique, et la seconde sélection passerait pour refusée
		$this->setClock('2037-10-25 11:05:00');

		$this->assertEquals(0, DecorUploadLib::choose($this->getWithAuthor($eDecorUploadNew)), 'modèle sélectionné deux fois');
		$this->assertEquals('2037-10-25 11:00:00', $this->get($eDecorUploadNew)['chosenAt'], 'date de la sélection réécrite');

		// La sélection compte comme le dernier geste : le modèle gardé il y a deux heures passe en tête de l'historique
		$this->assertEquals($eDecorUploadKept['id'], DecorUploadLib::getExamined(0, TRUE)[0]->first()['id'], 'sélection hors de la tête de l\'historique');

		$this->assertEquals('map\\DecorUpload::stale', $this->post($this->eUserModerator, 'doChoose', $eDecorUploadNew), 'seconde sélection sans bandeau');
		$this->assertEquals('map\\DecorUpload::stale', $this->post($this->eUserModerator, 'doChoose', $eDecorUploadPrivate), 'sélection d\'un modèle sans partage sans bandeau');

		$eDecorUploadPage = $this->createBuilding(TRUE);

		try {
			$this->post($this->eUserAuthor, 'doChoose', $eDecorUploadPage);
		} catch(\Exception) {
		}

		$this->assertEquals(NULL, $this->get($eDecorUploadPage)['chosenAt'], 'sélection hors de l\'équipe');
		$this->assertEquals(\ReloadAction::class, $this->post($this->eUserModerator, 'doChoose', $eDecorUploadPage), 'sélection par la page refusée');

	}

	public function testMails(): void {

		$eDecorUpload = $this->getWithAuthor($this->createBuilding(TRUE));
		$eDecorUpload['reason'] = DecorUpload::BRAND;
		$eDecorUpload['reasonText'] = '<b>logo</b>';

		$mails = DecorUploadLib::getWithdrawnMails($eDecorUpload);

		if($this->assertEquals(1, count($mails), 'e-mail de retrait absent') === FALSE) {
			return;
		}

		[['to' => $to, 'content' => [, $text, $html], 'replyTo' => $replyTo]] = $mails;

		$this->assertEquals($this->eUserAuthor['email'], $to, 'e-mail envoyé à un autre que l\'auteur');
		$this->assertTrue(str_contains($html, '&lt;b&gt;logo&lt;/b&gt;'), 'précision non échappée');

		\user\UserSetting::$avatarUploadContest = NULL;

		[['content' => [, $text], 'replyTo' => $replyTo]] = DecorUploadLib::getWithdrawnMails($eDecorUpload);

		$this->assertEquals(NULL, $replyTo, 'adresse de réponse sans adresse de contestation');
		$this->assertFalse(str_contains($text, 'répondez à cet e-mail'), 'invitation à répondre sans adresse de contestation');

		\user\UserSetting::$avatarUploadContest = 'moderation@example.org';

		[['content' => [, $text], 'replyTo' => $replyTo]] = DecorUploadLib::getWithdrawnMails($eDecorUpload);

		$this->assertEquals('moderation@example.org', $replyTo, 'réponse non adressée à la contestation');
		$this->assertTrue(str_contains($text, 'répondez à cet e-mail'), 'contestation sans invitation à répondre');

		$eDecorUpload['building']['name'] = '<b>Grange</b>';

		[['to' => $to, 'content' => [, , $html]]] = DecorUploadLib::getChosenMails($eDecorUpload);

		$this->assertEquals($this->eUserAuthor['email'], $to, 'remerciement envoyé à un autre que l\'auteur');
		$this->assertTrue(str_contains($html, '&lt;b&gt;Grange&lt;/b&gt;'), 'nom du bâtiment non échappé dans le remerciement');

		$eDecorUpload['createdBy'] = new \user\User(['id' => $this->eUserAuthor['id'], 'email' => NULL]);

		$this->assertEquals([], DecorUploadLib::getWithdrawnMails($eDecorUpload), 'e-mail de retrait préparé pour un compte sans adresse');
		$this->assertEquals([], DecorUploadLib::getChosenMails($eDecorUpload), 'remerciement préparé pour un compte sans adresse');

	}

	public function testDiscord(): void {

		$eDecorUpload = $this->createBuilding();

		$message = DecorUploadLib::getMessage($eDecorUpload, 3);

		$this->assertTrue(str_contains($message, '/map/admin/decorUpload?id='.$eDecorUpload['id']), 'lien de la file absent');

		foreach(['email', 'firstName', 'lastName'] as $property) {
			if($this->eUserAuthor[$property] !== NULL) {
				$this->assertFalse(str_contains($message, $this->eUserAuthor[$property]), 'auteur nommé dans Discord ('.$property.')');
			}
		}

		$this->assertFalse(str_contains($message, $this->eStructure['name']), 'structure nommée dans Discord');
		$this->assertFalse(DecorUploadLib::notify($eDecorUpload), 'alerte postée hors production');

	}

	// Un bâtiment neuf et son modèle, importés par l'administrateur de F1
	private function createBuilding(bool $shared = FALSE): DecorUpload {

		$initial = DecorBuildingLib::getChoices()[DecorBuilding::BUILDING]['initial'];

		$eDecorBuilding = new DecorBuilding([
			'structure' => $this->eStructure,
			'eDecor' => new Decor(),
		]);

		$fw = new \FailWatch();

		$eDecorBuilding->build(DecorBuildingLib::getPropertiesCreate(), [
			'type' => DecorBuilding::BUILDING,
			'name' => 'Bâtiment du cas',
			'length' => $initial['length'],
			'width' => $initial['width'],
			'height' => $initial['height'],
			'settings' => json_encode($initial['settings']),
		], new \PropertyList('create'));

		$fw->validate();

		$eDecorUpload = $this->buildUpload($shared);
		$eDecorUpload['building'] = $eDecorBuilding;

		$this->as($this->eUserAuthor, fn() => DecorUploadLib::create($eDecorUpload));

		return $eDecorUpload;

	}

	// Un élément neuf de la rangée Matériel et son modèle, importés par l'administrateur de F1
	private function createObject(bool $shared = FALSE): DecorUpload {

		$eDecorObject = new DecorObject(['structure' => $this->eStructure]);

		$fw = new \FailWatch();

		$eDecorObject->build(DecorObjectLib::getPropertiesCreate(), ['family' => DecorObject::OTHER, 'name' => 'Élément du cas'], new \PropertyList('create'));

		$fw->validate();

		$eDecorUpload = $this->buildUpload($shared);
		$eDecorUpload['object'] = $eDecorObject;

		$this->as($this->eUserAuthor, fn() => DecorUploadLib::createByObject($eDecorUpload));

		return $eDecorUpload;

	}

	/**
	 * Sans `build()` : il lancerait la conversion par node sur un fichier, que `DecorUploadAi` éprouve et que la modération ne
	 * relit pas. Le modèle n'a donc ni pièce ni ancre, et son binaire est une chaîne quelconque.
	 */
	private function buildUpload(bool $shared): DecorUpload {

		return new DecorUpload([
			'structure' => $this->eStructure,
			'shared' => $shared,
			'rules' => \scene\UploadLib::getRules(DecorUploadLib::PROFILE)['version'],
			'bakedIndex' => ['models' => []],
			'bakedBuffer' => 'model'
		]);

	}

	// L'envoi s'écrit sous son auteur, qui en devient `createdBy`, puis la main revient à l'équipe
	private function as(\user\User $eUser, \Closure $callback): void {

		\user\ConnectionLib::setOnline($eUser);

		try {
			$callback();
		} finally {
			\user\ConnectionLib::setOnline($this->eUserModerator);
		}

	}

	private function get(DecorUpload $eDecorUpload): DecorUpload {
		return DecorUploadLib::getById($eDecorUpload['id']);
	}

	// L'élément tel que la page admin le charge, l'auteur complet pour l'e-mail
	private function getWithAuthor(DecorUpload $eDecorUpload): DecorUpload {

		return DecorUploadLib::getById($eDecorUpload['id'], DecorUpload::getSelection() + [
			'createdBy' => \user\User::getSelection(),
			'building' => ['name'],
			'object' => ['name']
		]);

	}

	private function withdraw(DecorUpload $eDecorUpload, string $reason, string $reasonText): DecorUpload {

		$eDecorUploadWithdrawn = $this->getWithAuthor($eDecorUpload);

		$fw = new \FailWatch();

		$eDecorUploadWithdrawn->build(DecorUploadLib::getPropertiesWithdraw(), ['reason' => $reason, 'reasonText' => $reasonText]);

		$this->assertFalse($fw->ko(), 'motif du retrait refusé');
		$this->assertTrue(DecorUploadLib::withdraw($eDecorUploadWithdrawn), 'retrait refusé');

		return $this->get($eDecorUpload);

	}

	private function build(array $input): \FailWatch {

		$fw = new \FailWatch();

		new DecorUpload()->build(DecorUploadLib::getPropertiesWithdraw(), $input);

		return $fw;

	}

	private function read(\user\User $eUser, DecorUpload $eDecorUpload): string {
		return $this->describe($this->execute($eUser, 'GET', 'map/decorUpload:model', ['id' => (string)$eDecorUpload['id']]));
	}

	private function post(\user\User $eUser, string $page, DecorUpload $eDecorUpload, array $input = []): string {
		return $this->describe($this->execute($eUser, 'POST', 'map/admin/decorUpload:'.$page, ['id' => (string)$eDecorUpload['id']] + $input));
	}

	// Les ids de la file affichée, postés comme le formulaire les poste : des chaînes, et rien quand la file est vide
	private function keepAll(\user\User $eUser, array $ids): string {
		return $this->describe($this->execute($eUser, 'POST', 'map/admin/decorUpload:doKeepAll', ($ids === []) ? [] : ['ids' => array_map('strval', $ids)]));
	}

	/**
	 * Le fail que lève un geste de l'administration, l'adresse où il renvoie, ou le nom de l'action qu'il rend à la place.
	 */
	private function describe(?\Action $action): string {

		if($action instanceof \FailAction) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : implode(', ', array_keys($fail->get()));
		}

		if($action instanceof \RedirectAction) {
			return new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);
		}

		return ($action === NULL) ? 'aucune action' : get_class($action);

	}

	/**
	 * La page réelle, sur une requête reconstruite. Même mécanique que `base/user/test/UserAvatarUploadModerationAi.t.php`.
	 */
	private function execute(\user\User $eUser, string $method, string $request, array $input): ?\Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		\user\ConnectionLib::setOnline($eUser);

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = ($method === 'GET') ? [$input, [], $input] : [[], $input, $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $request), new \stdClass());
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
			\user\ConnectionLib::setOnline($this->eUserModerator);
		}

	}

	private function getMediaStatus(string $hash): string {

		return \media\Media::model()
			->select('status')
			->whereType('map-decor-upload')
			->whereHash($hash)
			->getValue('status');

	}

	private function getHashes(): array {

		return \media\Media::model()
			->whereType('map-decor-upload')
			->getColumn('hash');

	}

}
?>
