<?php
namespace user;

/**
 * Le modèle téléversé d'un membre, vu par les autres membres dans les scènes d'une structure : ce que la page reçoit
 * (`UserAvatarUploadLib::getByUsers()`) et ce que la route du fichier sert (`avatarUpload.p.php`).
 *
 * Ce qui s'y prouve :
 * 1. un membre de la structure reçoit le modèle d'un autre membre, sans son hash ;
 * 2. il ne le reçoit pas, et la route le refuse, si l'auteur a le rôle fantôme, un compte suspendu ou un bannissement en
 *    cours, si l'envoi est retiré, si le drapeau est éteint pour la structure ou si elle n'a pas de plan ;
 * 3. une personne qui n'est pas membre de la structure nommée, ou qui n'en nomme aucune, n'a pas le fichier ;
 * 4. hors de l'auteur, un envoi absent, retiré, sans fichier ou hors droit rend la même réponse.
 *
 * Décor : la structure F1, son administrateur qui regarde, la personne au planning qui téléverse, et l'administrateur
 * de F2, qui n'est pas de F1. Le modèle est posé par `UserAvatarUploadLib::create()` sur une conversion fictive, dans
 * une transaction annulée à la fin de chaque méthode ; le fichier qu'il écrit est effacé à la fin.
 *
 * Lancement : dev/test package=user file=UserAvatarUploadSceneAi
 */
class UserAvatarUploadSceneAiTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	const READER = 'decor.f1.admin@example.org';
	const AUTHOR = 'decor.f1.planning@example.org';
	const OUTSIDER = 'decor.f2.admin@example.org';

	private User $eUserInitial;
	private \Closure $avatarUploadInitial;

	private \structure\Structure $eStructure;
	private \structure\Structure $eStructureOther;

	private User $eUserReader;
	private User $eUserAuthor;
	private User $eUserOutsider;

	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function setUp(): void {

		$this->eUserInitial = ConnectionLib::getOnline();
		$this->avatarUploadInitial = UserSetting::$avatarUpload;

		parent::setUp();

		$this->hashesInitial = $this->getHashes();

		UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE);
		$this->eStructureOther = \structure\StructureLib::getById(self::STRUCTURE_OTHER);

		$this->eUserReader = UserLib::getByEmail(self::READER);
		$this->eUserAuthor = UserLib::getByEmail(self::AUTHOR);
		$this->eUserOutsider = UserLib::getByEmail(self::OUTSIDER);

	}

	public function tearDown(): void {

		// Les médias écrits par l'envoi disparaissent avec la transaction, pas leurs fichiers : ils se lisent donc avant elle
		$files = array_map(
			fn(string $hash) => \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($hash),
			array_diff($this->getHashes(), $this->hashesInitial)
		);

		parent::tearDown();

		UserSetting::$avatarUpload = $this->avatarUploadInitial;
		ConnectionLib::setOnline($this->eUserInitial);
		\structure\MemberLib::clearCache();

		foreach($files as $file) {
			if(is_file($file)) {
				unlink($file);
			}
		}

	}

	public function testShown(): void {

		$eUserAvatarUpload = $this->create();

		$cUserAvatarUpload = $this->getByUsers($this->eStructure);

		if($this->assertTrue($cUserAvatarUpload->offsetExists($this->eUserAuthor['id']), 'modèle de l\'auteur absent de la scène') === FALSE) {
			return;
		}

		$this->assertEquals($eUserAvatarUpload['id'], $cUserAvatarUpload[$this->eUserAuthor['id']]['id'], 'un autre envoi que celui de l\'avatar');
		$this->assertFalse($cUserAvatarUpload->offsetExists($this->eUserReader['id']), 'modèle prêté à un membre qui n\'en a pas');
		$this->assertFalse($cUserAvatarUpload[$this->eUserAuthor['id']]->offsetExists('hash'), 'hash sorti du Lib');

		$this->assertEquals(\DataAction::class, $this->read($this->eUserReader, $eUserAvatarUpload, $this->eStructure), 'fichier refusé à un membre');
		$this->assertEquals(\DataAction::class, $this->read($this->eUserAuthor, $eUserAvatarUpload, new \structure\Structure()), 'fichier refusé à son auteur');

	}

	public function testOutsider(): void {

		$eUserAvatarUpload = $this->create();

		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserOutsider, $eUserAvatarUpload, $this->eStructure), 'fichier servi à qui n\'est pas membre');
		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserOutsider, $eUserAvatarUpload, $this->eStructureOther), 'fichier servi dans une structure où l\'auteur n\'est pas');
		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserReader, $eUserAvatarUpload, new \structure\Structure()), 'fichier servi à un autre sans structure nommée');

	}

	public function testGhost(): void {

		$eUserAvatarUpload = $this->create();

		// Le rôle se pose sans `build()`, qui refuse le rôle fantôme à un compte : c'est le cas que le test éprouve
		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAuthor, $this->eStructure);
		$eMember['role'] = \structure\RoleLib::getOne($this->eStructure, 'ghost');

		\structure\MemberLib::update($eMember, ['role']);

		$this->assertHidden($eUserAvatarUpload, 'auteur au rôle fantôme');

	}

	public function testSuspended(): void {

		$eUserAvatarUpload = $this->create();

		// Le statut se pose sans `build()` : aucun formulaire ne suspend un compte, c'est une écriture d'admin
		$this->eUserAuthor['status'] = User::SUSPENDED;

		UserLib::update($this->eUserAuthor, ['status']);

		$this->assertHidden($eUserAvatarUpload, 'auteur au compte suspendu');

	}

	public function testBanned(): void {

		$eUserAvatarUpload = $this->create();

		$this->ban('+1 day');

		$this->assertHidden($eUserAvatarUpload, 'auteur banni');

	}

	public function testBanEnded(): void {

		$this->create();

		// Un bannissement fini ne compte plus
		$this->ban('-1 day');

		$this->assertTrue($this->getByUsers($this->eStructure)->offsetExists($this->eUserAuthor['id']), 'bannissement fini qui cache encore le modèle');

	}

	public function testWithdrawn(): void {

		$eUserAvatarUpload = $this->create();

		$this->withdraw($eUserAvatarUpload);

		$this->assertHidden($eUserAvatarUpload, 'envoi retiré');

	}

	public function testFlag(): void {

		$eUserAvatarUpload = $this->create();

		UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => ($eStructure['id'] !== self::STRUCTURE);

		$this->assertHidden($eUserAvatarUpload, 'drapeau éteint pour la structure');

	}

	public function testSceneClosed(): void {

		$eUserAvatarUpload = $this->create();

		// Une structure sans plan, sur les membres de F1 : le type change sur l'élément seul, l'appartenance se lit par l'id
		$eStructureShop = clone $this->eStructure;
		$eStructureShop['type'] = \structure\Structure::SHOP;

		$this->assertTrue($this->getByUsers($eStructureShop)->empty(), 'modèle montré dans une structure sans plan');

		// La route relirait la structure en base : le droit qu'elle interroge se juge donc sur l'élément
		ConnectionLib::setOnline($this->eUserReader);
		\structure\MemberLib::clearCache();

		$this->assertFalse($eUserAvatarUpload->canReadByStructure($eStructureShop), 'fichier lisible dans une structure sans plan');
		$this->assertTrue($eUserAvatarUpload->canReadByStructure($this->eStructure), 'fichier illisible dans la structure ouverte');

	}

	/**
	 * Hors de l'auteur, un envoi absent, retiré, sans fichier ou hors droit rend la même réponse : qui balaie les ids
	 * n'apprend rien de l'existence ni de la modération des envois des autres.
	 */
	public function testSameAnswer(): void {

		$eUserAvatarUpload = $this->create();

		$answers = [];

		$answers['hors droit'] = $this->read($this->eUserOutsider, $eUserAvatarUpload, $this->eStructure);
		$answers['absent'] = $this->read($this->eUserReader, new UserAvatarUpload(['id' => UserAvatarUpload::model()->getValue(new \Sql('MAX(id)', 'int')) + 1]), $this->eStructure);

		$this->withdraw($eUserAvatarUpload);
		$answers['retiré'] = $this->read($this->eUserReader, $eUserAvatarUpload, $this->eStructure);
		$answers['retiré, hors droit'] = $this->read($this->eUserOutsider, $eUserAvatarUpload, $this->eStructure);

		// Le fichier se perd sans `build()`, qui ne porte pas le hash : c'est l'état d'un envoi remplacé
		$eUserAvatarUpload['status'] = UserAvatarUpload::NEW;
		$eUserAvatarUpload['hash'] = NULL;

		UserAvatarUploadLib::update($eUserAvatarUpload, ['status', 'hash']);
		$answers['sans fichier'] = $this->read($this->eUserReader, $eUserAvatarUpload, $this->eStructure);

		foreach($answers as $case => $answer) {
			$this->assertEquals(\NotExistsAction::class, $answer, 'réponse distincte pour un envoi '.$case);
		}

	}

	private function assertHidden(UserAvatarUpload $eUserAvatarUpload, string $case): void {

		$this->assertFalse($this->getByUsers($this->eStructure)->offsetExists($this->eUserAuthor['id']), 'modèle posé dans la scène, '.$case);
		$this->assertEquals(\NotExistsAction::class, $this->read($this->eUserReader, $eUserAvatarUpload, $this->eStructure), 'fichier servi à un membre, '.$case);

	}

	/**
	 * Un bannissement du compte de l'auteur, sans `BanLib::createBan()`, qui coupe aussi ses sessions, que le test
	 * n'éprouve pas, et sans `build()`, que le bannissement n'emprunte pas.
	 */
	private function ban(string $until): void {

		BanLib::create(new Ban([
			'user' => $this->eUserAuthor,
			'reason' => 'test',
			'admin' => $this->eUserReader,
			'until' => date('Y-m-d H:i:s', \Clock::shift($until))
		]));

	}

	/**
	 * Le retrait se pose sans `build()`, qui ne porte pas le statut, que seule la modération du lot 3 écrira.
	 */
	private function withdraw(UserAvatarUpload $eUserAvatarUpload): void {

		$eUserAvatarUpload['status'] = UserAvatarUpload::WITHDRAWN;

		UserAvatarUploadLib::update($eUserAvatarUpload, ['status']);

	}

	private function getByUsers(\structure\Structure $eStructure): \Collection {

		return UserAvatarUploadLib::getByUsers($eStructure, new \Collection([
			$this->eUserReader['id'] => $this->eUserReader,
			$this->eUserAuthor['id'] => $this->eUserAuthor
		]));

	}

	/**
	 * Un modèle pour l'auteur, sur sa composition. La conversion est fictive et ne passe pas par `build()`, qui lancerait
	 * node sur un GLB : la conversion s'éprouve dans `UserAvatarUploadAi`, ici seule compte la lecture du modèle.
	 */
	private function create(): UserAvatarUpload {

		if(UserAvatarLib::getByUser($this->eUserAuthor)->empty()) {
			UserAvatarLib::create(new UserAvatar(['user' => $this->eUserAuthor, 'composition' => UserAvatarLib::getDefault(), 'replacesPhoto' => FALSE]));
		}

		$eUserAvatarUpload = new UserAvatarUpload([
			'user' => $this->eUserAuthor,
			'rules' => \scene\UploadLib::getRules('avatar')['version'],
			'bakedIndex' => ['models' => []],
			'bakedBuffer' => 'model'
		]);

		UserAvatarUploadLib::create($eUserAvatarUpload);

		return $eUserAvatarUpload;

	}

	/**
	 * La route du fichier jouée comme une requête du lecteur donné, forme de `executePage()` dans
	 * `base/hr/test/AbsenceAi.t.php`. Rend la classe de l'action levée.
	 */
	private function read(User $eUser, UserAvatarUpload $eUserAvatarUpload, \structure\Structure $eStructure): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		$input = ['id' => (string)$eUserAvatarUpload['id']] + ($eStructure->empty() ? [] : ['structure' => (string)$eStructure['id']]);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$input, [], $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'user/avatar/modele/'.$eUserAvatarUpload['id']), new \stdClass());
			return 'aucune action';
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
			ConnectionLib::setOnline($this->eUserInitial);
			\structure\MemberLib::clearCache();
		}

	}

	private function getHashes(): array {

		return \media\Media::model()
			->whereType('user-avatar-upload')
			->getColumn('hash');

	}

}
?>
