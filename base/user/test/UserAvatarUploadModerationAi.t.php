<?php
namespace user;

/**
 * La modération a posteriori des modèles 3D téléversés : l'équipe garde ou retire un modèle déjà montré, et le retrait
 * part par e-mail à son auteur.
 *
 * Ce qui s'y prouve :
 * 1. garder fait passer un envoi neuf à gardé, une seule fois, et jamais un envoi remplacé entre-temps ; « Tout valider »
 *    garde les modèles que la page affichait et qui sont encore dans la file, ignore un modèle retiré depuis et laisse à
 *    examiner celui arrivé après l'affichage ;
 * 2. retirer efface le fichier, rend l'avatar à sa composition et écrit le motif, sur un envoi neuf comme gardé ; un envoi
 *    remplacé entre-temps n'est pas touché, et l'auteur réimporte aussitôt après un retrait ;
 * 3. un retrait demande un motif de la liste et une précision d'au plus 500 caractères ;
 * 4. seul un admin modère, et lit le fichier par la route du binaire tant qu'il n'est pas retiré ;
 * 5. la file ne montre que les envois neufs qui ont un fichier, celui du lien d'alerte en tête, et l'historique les retirés
 *    et les gardés encore montrés ;
 * 6. l'e-mail va à l'auteur avec la précision échappée, n'invite à répondre qu'avec une adresse de contestation, et ne part
 *    pas sans adresse ; l'alerte Discord ne nomme pas l'auteur et ne part pas hors production.
 *
 * Décor : la personne au planning de F1 téléverse, l'observateur de F1 aussi quand il faut deux modèles dans la file (un
 * auteur n'en a qu'un, le suivant remplace le précédent), l'administrateur de F1 modère, son id posé dans
 * `UserSetting::$admin` le temps du test. Les modèles sont posés par `UserAvatarUploadLib::create()` sur une conversion fictive, dans une
 * transaction annulée à la fin de chaque méthode ; les fichiers écrits sont effacés à la fin.
 *
 * Lancement : dev/test package=user file=UserAvatarUploadModerationAi
 */
class UserAvatarUploadModerationAiTest extends \dev\TransactionTest {

	const ADMIN = 'decor.f1.admin@example.org';
	const AUTHOR = 'decor.f1.planning@example.org';
	const AUTHOR_OTHER = 'decor.f1.observateur@example.org';

	private User $eUserInitial;
	private array $adminInitial;
	private ?string $contestInitial;

	private User $eUserAdmin;
	private User $eUserAuthor;
	private User $eUserAuthorOther;

	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function setUp(): void {

		$this->eUserInitial = ConnectionLib::getOnline();
		$this->adminInitial = UserSetting::$admin;
		$this->contestInitial = UserSetting::$avatarUploadContest;

		parent::setUp();

		$this->hashesInitial = $this->getHashes();

		$this->eUserAdmin = UserLib::getByEmail(self::ADMIN);
		$this->eUserAuthor = UserLib::getByEmail(self::AUTHOR);
		$this->eUserAuthorOther = UserLib::getByEmail(self::AUTHOR_OTHER);

		UserSetting::$admin = [$this->eUserAdmin['id']];
		ConnectionLib::setOnline($this->eUserAdmin);

	}

	public function tearDown(): void {

		// Les médias écrits par l'envoi disparaissent avec la transaction, pas leurs fichiers : ils se lisent donc avant elle
		$files = array_map(
			fn(string $hash) => $this->getPath($hash),
			array_diff($this->getHashes(), $this->hashesInitial)
		);

		parent::tearDown();

		UserSetting::$admin = $this->adminInitial;
		UserSetting::$avatarUploadContest = $this->contestInitial;
		ConnectionLib::setOnline($this->eUserInitial);

		foreach($files as $file) {
			if(is_file($file)) {
				unlink($file);
			}
		}

	}

	public function testKeep(): void {

		$eUserAvatarUpload = $this->create();

		$this->assertEquals(1, UserAvatarUploadLib::keep($this->get($eUserAvatarUpload)), 'envoi neuf non gardé');

		$eUserAvatarUploadKept = $this->get($eUserAvatarUpload);

		$this->assertEquals(UserAvatarUpload::KEPT, $eUserAvatarUploadKept['status'], 'statut après garder');
		$this->assertEquals($this->eUserAdmin['id'], $eUserAvatarUploadKept['moderatedBy']['id'], 'admin du geste non écrit');
		$this->assertTrue($eUserAvatarUploadKept['moderatedAt'] !== NULL, 'date du geste non écrite');
		$this->assertEquals(0, UserAvatarUploadLib::keep($eUserAvatarUploadKept), 'envoi gardé deux fois');

	}

	public function testKeepReplaced(): void {

		$eUserAvatarUploadFirst = $this->create();
		$eUserAvatarUploadStale = $this->get($eUserAvatarUploadFirst);

		$this->create();

		$this->assertEquals(0, UserAvatarUploadLib::keep($eUserAvatarUploadStale), 'envoi remplacé gardé, lu avant le remplacement');
		$this->assertEquals(UserAvatarUpload::NEW, $this->get($eUserAvatarUploadFirst)['status'], 'statut de l\'envoi remplacé changé');

	}

	/**
	 * La recette de V-3D : un modèle retiré depuis la carte, puis « Tout valider » sans recharger, la page postant encore son
	 * id. Le retiré reste retiré, l'envoi arrivé après l'affichage reste à examiner, et les compteurs de la page suivent.
	 */
	public function testKeepAll(): void {

		$eUserAvatarUploadShown = $this->create();
		$eUserAvatarUploadWithdrawn = $this->create($this->eUserAuthorOther);
		$idsShown = [$eUserAvatarUploadShown['id'], $eUserAvatarUploadWithdrawn['id']];

		[, $waiting] = UserAvatarUploadLib::getWaiting(0);
		[, $examined] = UserAvatarUploadLib::getExamined(0);

		$this->withdraw($eUserAvatarUploadWithdrawn, UserAvatarUpload::BRAND, 'Le logo du tee-shirt.');

		// L'observateur réimporte aussitôt : son nouvel envoi n'était pas sur la page
		$eUserAvatarUploadLate = $this->create($this->eUserAuthorOther);

		$this->assertEquals(\ReloadAction::class, $this->keepAll($this->eUserAdmin, $idsShown), 'file affichée non gardée');
		$this->assertEquals(UserAvatarUpload::KEPT, $this->get($eUserAvatarUploadShown)['status'], 'modèle affiché non gardé');
		$this->assertEquals($this->eUserAdmin['id'], $this->get($eUserAvatarUploadShown)['moderatedBy']['id'], 'admin du geste non écrit');
		$this->assertEquals(UserAvatarUpload::WITHDRAWN, $this->get($eUserAvatarUploadWithdrawn)['status'], 'modèle retiré gardé par sa carte restée affichée');
		$this->assertEquals(UserAvatarUpload::NEW, $this->get($eUserAvatarUploadLate)['status'], 'modèle arrivé après l\'affichage gardé sans avoir été vu');
		$this->assertTrue(in_array($eUserAvatarUploadLate['id'], UserAvatarUploadLib::getWaiting($eUserAvatarUploadLate['id'])[0]->getIds()), 'modèle arrivé après l\'affichage sorti de la file');

		// Deux sortis de la file (le gardé, le retiré), un entré (le dernier venu) ; l'historique prend le gardé et le retiré
		$this->assertEquals($waiting - 1, UserAvatarUploadLib::getWaiting(0)[1], 'compteur de la file');
		$this->assertEquals($examined + 2, UserAvatarUploadLib::getExamined(0)[1], 'compteur des examinés');

		$this->assertEquals('user\\UserAvatarUpload::keepAllNone', $this->keepAll($this->eUserAdmin, $idsShown), 'file déjà examinée gardée de nouveau');
		$this->assertEquals('user\\UserAvatarUpload::keepAllNone', $this->keepAll($this->eUserAdmin, []), 'file vide gardée');

		// Plus d'ids que la file n'en affiche : rien ne part, pas même le seul envoi encore à examiner de la liste
		$idsForged = array_merge([$eUserAvatarUploadLate['id']], range($eUserAvatarUploadLate['id'] + 1, $eUserAvatarUploadLate['id'] + UserSetting::MAX_BY_PAGE));

		$this->assertEquals('user\\UserAvatarUpload::keepAllNone', $this->keepAll($this->eUserAdmin, $idsForged), 'liste plus longue que la file acceptée');
		$this->assertEquals(UserAvatarUpload::NEW, $this->get($eUserAvatarUploadLate)['status'], 'liste plus longue que la file gardée');

		// Hors de l'équipe, `validateAdmin()` arrête la page avant toute action
		try {
			$this->keepAll($this->eUserAuthor, [$eUserAvatarUploadLate['id']]);
		} catch(\Exception) {
		}

		$this->assertEquals(UserAvatarUpload::NEW, $this->get($eUserAvatarUploadLate)['status'], 'file gardée hors de l\'équipe');

	}

	public function testWithdraw(): void {

		$eUserAvatarUpload = $this->create();
		$hash = $this->get($eUserAvatarUpload)['hash'];

		$eUserAvatarUploadWithdrawn = $this->withdraw($eUserAvatarUpload, UserAvatarUpload::BRAND, 'Le logo du tee-shirt.');

		$this->assertEquals(UserAvatarUpload::WITHDRAWN, $eUserAvatarUploadWithdrawn['status'], 'statut après retrait');
		$this->assertEquals(UserAvatarUpload::BRAND, $eUserAvatarUploadWithdrawn['reason'], 'motif non écrit');
		$this->assertEquals('Le logo du tee-shirt.', $eUserAvatarUploadWithdrawn['reasonText'], 'précision non écrite');
		$this->assertEquals(NULL, $eUserAvatarUploadWithdrawn['hash'], 'hash gardé après retrait');
		$this->assertEquals(NULL, $eUserAvatarUploadWithdrawn['bakedIndex'], 'index gardé après retrait');
		$this->assertEquals($this->eUserAdmin['id'], $eUserAvatarUploadWithdrawn['moderatedBy']['id'], 'admin du retrait non écrit');
		$this->assertTrue(UserAvatarLib::getByUser($this->eUserAuthor)['upload']->empty(), 'avatar encore sur le modèle retiré');
		// Le fichier quitte le disque au COMMIT de la transaction la plus haute, celle du test, qui s'annule : le média dit l'effacement
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hash), 'fichier gardé après retrait');
		$this->assertFalse($eUserAvatarUploadWithdrawn->acceptWithdraw(), 'envoi retiré encore à retirer');

		// Le retrait ne ferme pas l'envoi : l'auteur réimporte, et le nouveau modèle revient dans la file
		$eUserAvatarUploadNext = $this->create();

		$this->assertEquals(UserAvatarUpload::NEW, $this->get($eUserAvatarUploadNext)['status'], 'nouvel envoi hors de la file');
		$this->assertTrue(in_array($eUserAvatarUploadNext['id'], UserAvatarUploadLib::getWaiting($eUserAvatarUploadNext['id'])[0]->getIds()), 'nouvel envoi absent de la file');

	}

	public function testWithdrawKept(): void {

		$eUserAvatarUpload = $this->create();

		UserAvatarUploadLib::keep($this->get($eUserAvatarUpload));

		$this->assertTrue($this->get($eUserAvatarUpload)->assertWithdraw(), 'envoi gardé refusé au retrait');
		$this->assertEquals(UserAvatarUpload::WITHDRAWN, $this->withdraw($eUserAvatarUpload, UserAvatarUpload::OFFENSIVE, 'Un symbole haineux.')['status'], 'envoi gardé non retiré');

	}

	public function testWithdrawReplaced(): void {

		$eUserAvatarUploadFirst = $this->create();
		$eUserAvatarUploadStale = $this->getWithAuthor($eUserAvatarUploadFirst);

		$eUserAvatarUploadSecond = $this->create();
		$hashSecond = $this->get($eUserAvatarUploadSecond)['hash'];

		$eUserAvatarUploadStale->build(UserAvatarUploadLib::getPropertiesWithdraw(), ['reason' => UserAvatarUpload::PERSON, 'reasonText' => 'Une autre personne.']);

		$this->assertFalse(UserAvatarUploadLib::withdraw($eUserAvatarUploadStale), 'envoi remplacé retiré, lu avant le remplacement');

		$eUserAvatarUploadFirstAfter = $this->get($eUserAvatarUploadFirst);

		$this->assertEquals(UserAvatarUpload::NEW, $eUserAvatarUploadFirstAfter['status'], 'statut de l\'envoi remplacé changé');
		$this->assertEquals(NULL, $eUserAvatarUploadFirstAfter['reason'], 'motif écrit sur l\'envoi remplacé');
		$this->assertEquals($eUserAvatarUploadSecond['id'], UserAvatarLib::getByUser($this->eUserAuthor)['upload']['id'], 'avatar détaché du modèle qui le remplace');
		$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($hashSecond), 'fichier du modèle qui remplace effacé');

	}

	/**
	 * Un envoi remplacé depuis l'affichage de la liste : le retrait rend le bandeau du Lib, et non l'erreur du cadriciel,
	 * qui reste celle d'un refus du droit.
	 */
	public function testStale(): void {

		$withdraw = ['reason' => UserAvatarUpload::PERSON, 'reasonText' => 'Une autre personne.'];

		$eUserAvatarUploadReplaced = $this->create();
		$this->create();

		$this->assertEquals('user\\UserAvatarUpload::stale', $this->post($this->eUserAdmin, 'doWithdraw', $eUserAvatarUploadReplaced, $withdraw), 'retrait d\'un envoi remplacé');

		$eUserAvatarUploadKept = $this->create();
		UserAvatarUploadLib::keep($this->get($eUserAvatarUploadKept));

		// Hors de l'équipe, `validateAdmin()` arrête la page avant toute action
		try {
			$refusal = $this->post($this->eUserAuthor, 'doWithdraw', $eUserAvatarUploadReplaced, $withdraw);
		} catch(\Exception $e) {
			$refusal = $e->getMessage();
		}

		$this->assertTrue($refusal !== 'user\\UserAvatarUpload::stale', 'refus du droit rendu comme un envoi périmé');

		// Vers la liste : le panneau ouvert par son adresse, rechargé, refuserait l'envoi qu'il vient de retirer
		$this->assertEquals('/user/admin/avatarUpload?success=user\\UserAvatarUpload::withdrawn', $this->post($this->eUserAdmin, 'doWithdraw', $eUserAvatarUploadKept, $withdraw), 'retrait d\'un envoi gardé sans retour à la liste');

	}

	public function testBuild(): void {

		$this->assertTrue($this->build([])->has('UserAvatarUpload::reason.empty'), 'retrait sans motif accepté');
		$this->assertTrue($this->build(['reason' => 'display'])->has('UserAvatarUpload::reason.check'), 'motif hors liste accepté');
		$this->assertTrue($this->build(['reason' => UserAvatarUpload::OTHER])->has('UserAvatarUpload::reasonText.empty'), 'retrait sans précision accepté');
		$this->assertTrue($this->build(['reason' => UserAvatarUpload::OTHER, 'reasonText' => str_repeat('a', 501)])->has('UserAvatarUpload::reasonText.check'), 'précision de 501 caractères acceptée');
		$this->assertFalse($this->build(['reason' => UserAvatarUpload::OTHER, 'reasonText' => str_repeat('a', 500)])->ko(), 'précision de 500 caractères refusée');

	}

	public function testRights(): void {

		$eUserAvatarUpload = $this->create();

		$this->assertTrue($this->get($eUserAvatarUpload)->canModerate(), 'admin sans droit de modérer');
		$this->assertEquals(\DataAction::class, $this->read($this->eUserAdmin, $eUserAvatarUpload), 'fichier refusé à l\'admin');

		ConnectionLib::setOnline($this->eUserAuthor);

		$this->assertFalse($this->get($eUserAvatarUpload)->canModerate(), 'auteur avec le droit de modérer');

		ConnectionLib::setOnline($this->eUserAdmin);

		$this->withdraw($eUserAvatarUpload, UserAvatarUpload::OTHER, 'Il s\'affiche mal dans les scènes.');

		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserAdmin, $eUserAvatarUpload), 'fichier d\'un modèle retiré servi à l\'admin');

	}

	public function testLists(): void {

		$eUserAvatarUploadReplaced = $this->create();
		$eUserAvatarUploadShown = $this->create();

		[$cUserAvatarUploadWaiting, $nUserAvatarUploadWaiting] = UserAvatarUploadLib::getWaiting($eUserAvatarUploadShown['id']);

		$this->assertFalse(in_array($eUserAvatarUploadReplaced['id'], $cUserAvatarUploadWaiting->getIds()), 'envoi remplacé dans la file');
		$this->assertEquals($eUserAvatarUploadShown['id'], $cUserAvatarUploadWaiting->first()['id'], 'envoi du lien d\'alerte hors de la tête');
		$this->assertFalse($cUserAvatarUploadWaiting->first()->offsetExists('hash'), 'hash sorti du Lib');
		$this->assertEquals($nUserAvatarUploadWaiting, UserAvatarUploadLib::countWaiting(), 'compte de la file');
		$this->assertTrue($cUserAvatarUploadWaiting->count() <= UserSetting::MAX_BY_PAGE, 'file sans borne');

		[$cUserAvatarUploadExamined] = UserAvatarUploadLib::getExamined(0);

		$this->assertFalse(in_array($eUserAvatarUploadShown['id'], $cUserAvatarUploadExamined->getIds()), 'envoi neuf dans l\'historique');

		UserAvatarUploadLib::keep($this->get($eUserAvatarUploadShown));

		[$cUserAvatarUploadExamined] = UserAvatarUploadLib::getExamined(0);

		$this->assertTrue(in_array($eUserAvatarUploadShown['id'], $cUserAvatarUploadExamined->getIds()), 'envoi gardé absent de l\'historique');
		$this->assertFalse(in_array($eUserAvatarUploadShown['id'], UserAvatarUploadLib::getWaiting($eUserAvatarUploadShown['id'])[0]->getIds()), 'envoi gardé encore dans la file');

		$this->withdraw($eUserAvatarUploadShown, UserAvatarUpload::PERSON, 'Une autre personne.');

		[$cUserAvatarUploadExamined] = UserAvatarUploadLib::getExamined(0);

		$this->assertTrue(in_array($eUserAvatarUploadShown['id'], $cUserAvatarUploadExamined->getIds()), 'envoi retiré absent de l\'historique');

	}

	public function testMail(): void {

		$eUserAvatarUpload = $this->getWithAuthor($this->create());
		$eUserAvatarUpload['reason'] = UserAvatarUpload::BRAND;
		$eUserAvatarUpload['reasonText'] = '<b>logo</b>';

		$mails = UserAvatarUploadLib::getMails($eUserAvatarUpload);

		if($this->assertEquals(1, count($mails), 'e-mail de retrait absent') === FALSE) {
			return;
		}

		[['to' => $to, 'content' => [, $text, $html], 'replyTo' => $replyTo]] = $mails;

		$this->assertEquals($this->eUserAuthor['email'], $to, 'e-mail envoyé à un autre que l\'auteur');
		$this->assertEquals(NULL, $replyTo, 'adresse de réponse sans adresse de contestation');
		$this->assertTrue(str_contains($html, '&lt;b&gt;logo&lt;/b&gt;'), 'précision non échappée');
		$this->assertFalse(str_contains($text, 'répondez à cet e-mail'), 'invitation à répondre sans adresse de contestation');

		UserSetting::$avatarUploadContest = 'moderation@example.org';

		[['content' => [, $text], 'replyTo' => $replyTo]] = UserAvatarUploadLib::getMails($eUserAvatarUpload);

		$this->assertEquals('moderation@example.org', $replyTo, 'réponse non adressée à la contestation');
		$this->assertTrue(str_contains($text, 'répondez à cet e-mail'), 'contestation sans invitation à répondre');

		$eUserAvatarUpload['user'] = new User(['id' => $this->eUserAuthor['id'], 'email' => NULL]);

		$this->assertEquals([], UserAvatarUploadLib::getMails($eUserAvatarUpload), 'e-mail préparé pour un compte sans adresse');

	}

	public function testDiscord(): void {

		$eUserAvatarUpload = $this->create();

		$message = UserAvatarUploadLib::getMessage($eUserAvatarUpload, 3);

		$this->assertTrue(str_contains($message, '/user/admin/avatarUpload?id='.$eUserAvatarUpload['id']), 'lien de la file absent');

		foreach(['email', 'firstName', 'lastName'] as $property) {
			if($this->eUserAuthor[$property] !== NULL) {
				$this->assertFalse(str_contains($message, $this->eUserAuthor[$property]), 'auteur nommé dans Discord ('.$property.')');
			}
		}

		$this->assertFalse(UserAvatarUploadLib::notify($eUserAvatarUpload), 'alerte postée hors production');

	}

	private function create(User $eUser = new User()): UserAvatarUpload {

		if($eUser->empty()) {
			$eUser = $this->eUserAuthor;
		}

		if(UserAvatarLib::getByUser($eUser)->empty()) {
			UserAvatarLib::create(new UserAvatar(['user' => $eUser, 'composition' => UserAvatarLib::getDefault(), 'replacesPhoto' => FALSE]));
		}

		// Sans `build()` : la conversion par node, que `build()` lance sur le fichier, n'est pas ce que ce test éprouve
		$eUserAvatarUpload = new UserAvatarUpload([
			'user' => $eUser,
			'rules' => \scene\UploadLib::getRules('avatar')['version'],
			'bakedIndex' => ['models' => []],
			'bakedBuffer' => 'model'
		]);

		UserAvatarUploadLib::create($eUserAvatarUpload);

		return $eUserAvatarUpload;

	}

	private function get(UserAvatarUpload $eUserAvatarUpload): UserAvatarUpload {
		return UserAvatarUploadLib::getById($eUserAvatarUpload['id']);
	}

	// L'élément tel que la page du retrait le charge, l'auteur complet pour l'e-mail
	private function getWithAuthor(UserAvatarUpload $eUserAvatarUpload): UserAvatarUpload {
		return UserAvatarUploadLib::getById($eUserAvatarUpload['id'], UserAvatarUpload::getSelection() + ['user' => User::getSelection()]);
	}

	private function withdraw(UserAvatarUpload $eUserAvatarUpload, string $reason, string $reasonText): UserAvatarUpload {

		$eUserAvatarUploadWithdrawn = $this->getWithAuthor($eUserAvatarUpload);

		$fw = new \FailWatch();

		$eUserAvatarUploadWithdrawn->build(UserAvatarUploadLib::getPropertiesWithdraw(), ['reason' => $reason, 'reasonText' => $reasonText]);

		$this->assertFalse($fw->ko(), 'motif du décor refusé');
		$this->assertTrue(UserAvatarUploadLib::withdraw($eUserAvatarUploadWithdrawn), 'retrait refusé');

		return $this->get($eUserAvatarUpload);

	}

	private function build(array $input): \FailWatch {

		$fw = new \FailWatch();

		new UserAvatarUpload()->build(UserAvatarUploadLib::getPropertiesWithdraw(), $input);

		return $fw;

	}

	private function read(User $eUser, UserAvatarUpload $eUserAvatarUpload): string {

		$action = $this->execute($eUser, 'GET', 'user/avatar/modele/'.$eUserAvatarUpload['id'], ['id' => (string)$eUserAvatarUpload['id']]);

		return ($action === NULL) ? 'aucune action' : get_class($action);

	}

	private function post(User $eUser, string $page, UserAvatarUpload $eUserAvatarUpload, array $input = []): string {
		return $this->describe($this->execute($eUser, 'POST', 'user/admin/avatarUpload:'.$page, ['id' => (string)$eUserAvatarUpload['id']] + $input));
	}

	// Les ids de la file affichée, postés comme le formulaire les poste : des chaînes, et rien quand la file est vide
	private function keepAll(User $eUser, array $ids): string {
		return $this->describe($this->execute($eUser, 'POST', 'user/admin/avatarUpload:doKeepAll', ($ids === []) ? [] : ['ids' => array_map('strval', $ids)]));
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

	private function execute(User $eUser, string $method, string $request, array $input): ?\Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		ConnectionLib::setOnline($eUser);

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
			ConnectionLib::setOnline($this->eUserAdmin);
		}

	}

	private function getPath(string $hash): string {
		return \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($hash);
	}

	private function getMediaStatus(string $hash): string {

		return \media\Media::model()
			->select('status')
			->whereType('user-avatar-upload')
			->whereHash($hash)
			->getValue('status');

	}

	private function getHashes(): array {

		return \media\Media::model()
			->whereType('user-avatar-upload')
			->getColumn('hash');

	}

}
?>
