<?php
namespace user;

/**
 * La photo de profil d'un avatar qui montre un modèle 3D téléversé (`UserAvatarLib::placeImage()`, `updatePhoto()`,
 * `recompose()`, `UserAvatarUploadLib::create()`, `clear()` et `withdraw()`).
 *
 * Ce qui s'y prouve :
 * 1. un modèle qui remplace la photo pose son image sur `User.vignette` et met la photo de côté, un second change la
 *    version du même média ;
 * 2. un modèle posé sans avatar passé, une copie de script, laisse la photo et son image telles quelles ;
 * 3. supprimer le modèle pose l'image de la composition à la place de la sienne, et ne touche rien quand la photo n'est
 *    pas remplacée ;
 * 4. l'interrupteur rend la photo puis la remplace de nouveau par l'image du modèle, et ne fait rien sans modèle en place ;
 * 5. après un retrait par l'équipe, la composition rendue reprend la place de l'image du modèle, et la photo revient si
 *    le rendu a échoué ; un avatar qui montre de nouveau un modèle ou ne remplace plus la photo est laissé tel quel ;
 * 6. le retrait d'un modèle qui faisait la photo ne la laisse jamais à l'image du modèle ;
 * 7. la page refuse l'interrupteur sans modèle en place, une photo remplacée sans image, et la suppression comme
 *    l'interrupteur à qui n'a pas d'avatar, sans rien écrire.
 *
 * Décor : un utilisateur sans avatar, dont la photo de profil devient un média fictif, et à qui le test compose un avatar
 * qui ne remplace pas sa photo, dans une transaction annulée à la fin de chaque méthode. Les modèles sont posés sur une
 * conversion fictive, sans `build()`, qui lancerait node sur un GLB : la conversion s'éprouve dans `UserAvatarUploadAi`.
 * Les fichiers que les images et les modèles posent dans le stockage sont effacés à la fin.
 *
 * Lancement : dev/test package=user file=UserAvatarUploadPhotoAi
 */
class UserAvatarUploadPhotoAiTest extends \dev\TransactionTest {

	const PHOTO = 'zztestavatarphotoaij';

	private User $eUserInitial;

	private array $files = [];
	private array $hashesInitial = [];
	private array $vignettes = [];

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function setUp(): void {

		$this->eUserInitial = ConnectionLib::getOnline();

		parent::setUp();

		$this->hashesInitial = $this->getHashes();

	}

	public function tearDown(): void {

		// Les médias écrits disparaissent avec la transaction, pas leurs fichiers : ceux des modèles se lisent donc avant elle
		foreach(array_diff($this->getHashes(), $this->hashesInitial) as $hash) {
			$this->files[] = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($hash);
		}

		parent::tearDown();

		ConnectionLib::setOnline($this->eUserInitial);

		foreach($this->files as $file) {
			if(is_file($file)) {
				unlink($file);
			}
		}

		foreach(array_unique($this->vignettes) as $hash) {
			array_map(unlink(...), glob(\storage\ServerLib::getDirectory('user-vignette').'/{,*/}'.$hash.'.*', GLOB_BRACE));
		}

	}

	public function testCreate(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->create($eUser, TRUE);

		$vignette = $this->getVignette($eUser);

		$this->assertDiffer(self::PHOTO, substr($vignette, 0, 20), 'image du modèle posée sur le média de la photo');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getAvatar($eUser)['photo'], 'photo de profil non mise de côté');
		$this->assertTrue($this->getAvatar($eUser)['replacesPhoto'], 'choix de remplacer la photo perdu');

		$this->create($eUser, TRUE);

		$vignetteSecond = $this->getVignette($eUser);

		$this->assertEquals(substr($vignette, 0, 20), substr($vignetteSecond, 0, 20), 'média neuf pour l\'image du second modèle');
		$this->assertDiffer($vignette, $vignetteSecond, 'version inchangée pour l\'image du second modèle');

		$this->create($eUser, FALSE);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue');
		$this->assertEquals(NULL, $this->getAvatar($eUser)['photo'], 'photo gardée de côté après l\'avoir rendue');

	}

	public function testCreateWithoutAvatar(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		UserAvatarUploadLib::create($this->getUpload($eUser));

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil touchée par une copie');
		$this->assertFalse($this->getAvatar($eUser)['replacesPhoto'], 'choix de la photo changé par une copie');
		$this->assertTrue($this->getAvatar($eUser)['upload']->notEmpty(), 'copie non montrée');

	}

	public function testClear(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->create($eUser, TRUE);

		$vignette = $this->getVignette($eUser);

		$image = $this->getImage();
		UserAvatarUploadLib::clear($this->getAvatar($eUser), $image);
		unlink($image);

		$vignetteCleared = $this->getVignette($eUser);

		$this->assertTrue($this->getAvatar($eUser)['upload']->empty(), 'modèle gardé');
		$this->assertEquals(substr($vignette, 0, 20), substr($vignetteCleared, 0, 20), 'média neuf pour l\'image de la composition');
		$this->assertDiffer($vignette, $vignetteCleared, 'image du modèle gardée après sa suppression');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getAvatar($eUser)['photo'], 'photo de côté perdue');

		$this->create($eUser, FALSE);

		UserAvatarUploadLib::clear($this->getAvatar($eUser), NULL);

		$this->assertTrue($this->getAvatar($eUser)['upload']->empty(), 'modèle gardé sans photo remplacée');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil touchée sans être remplacée');

	}

	public function testUpdatePhoto(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->create($eUser, TRUE);

		$this->assertTrue(UserAvatarLib::updatePhoto($this->getPosted($eUser, FALSE), NULL), 'interrupteur refusé avec un modèle en place');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue');
		$this->assertFalse($this->getAvatar($eUser)['replacesPhoto'], 'interrupteur sans effet');

		$image = $this->getImage();
		$this->assertTrue(UserAvatarLib::updatePhoto($this->getPosted($eUser, TRUE), $image), 'interrupteur refusé avec un modèle en place');
		unlink($image);

		$vignette = $this->getVignette($eUser);
		$this->vignettes[] = substr($vignette, 0, 20);

		$this->assertDiffer(self::PHOTO, substr($vignette, 0, 20), 'image du modèle posée sur le média de la photo');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getAvatar($eUser)['photo'], 'photo de profil non mise de côté');

		UserAvatarUploadLib::clear($this->getAvatar($eUser), $this->getImage());

		$this->assertFalse(UserAvatarLib::updatePhoto($this->getPosted($eUser, FALSE), NULL), 'interrupteur accepté sans modèle en place');
		$this->assertTrue($this->getAvatar($eUser)['replacesPhoto'], 'interrupteur appliqué sans modèle en place');

	}

	public function testRecompose(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->create($eUser, TRUE);

		// Un modèle en place a son image : rien à recomposer
		UserAvatarLib::recompose($eUser, NULL, $this->getVignette($eUser));

		$this->assertTrue($this->getAvatar($eUser)['replacesPhoto'], 'image d\'un modèle en place recomposée');

		$this->withdrawShown($eUser);

		$vignette = $this->getVignette($eUser);

		$image = $this->getImage();
		UserAvatarLib::recompose($eUser, $image, $this->getVignette($eUser));
		unlink($image);

		$this->assertTrue($this->getAvatar($eUser)['replacesPhoto'], 'photo rendue malgré l\'image de la composition');
		$this->assertDiffer($vignette, $this->getVignette($eUser), 'image du modèle retiré gardée');

		// Une image changée pendant le rendu, une composition enregistrée entre-temps, n'est pas recouverte
		UserAvatarLib::recompose($eUser, NULL, self::PHOTO.'#000000099');

		$this->assertTrue($this->getAvatar($eUser)['replacesPhoto'], 'image changée pendant le rendu recouverte');

		UserAvatarLib::recompose($eUser, NULL, $this->getVignette($eUser));

		$this->assertFalse($this->getAvatar($eUser)['replacesPhoto'], 'photo non rendue sans image de la composition');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue');

		// La photo rendue, il n'y a plus d'image à recomposer
		UserAvatarLib::recompose($eUser, $this->getImage(), $this->getVignette($eUser));

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'image posée sur un avatar qui ne remplace plus la photo');

	}

	public function testWithdraw(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$eUserAvatarUpload = $this->create($eUser, TRUE);

		$vignette = $this->getVignette($eUser);

		$eUserAvatarUploadWithdrawn = UserAvatarUploadLib::getById($eUserAvatarUpload['id'], UserAvatarUpload::getSelection() + ['user' => User::getSelection()]);
		$eUserAvatarUploadWithdrawn['user']['email'] = NULL;

		$fw = new \FailWatch();
		$eUserAvatarUploadWithdrawn->build(UserAvatarUploadLib::getPropertiesWithdraw(), ['reason' => UserAvatarUpload::OTHER, 'reasonText' => 'Il s\'affiche mal.']);
		$this->assertFalse($fw->ko(), 'motif refusé');

		$this->assertTrue(UserAvatarUploadLib::withdraw($eUserAvatarUploadWithdrawn), 'retrait refusé');

		// Le rendu par Chrome dépend du poste, et l'avatar du test n'existe que dans sa transaction : l'une ou l'autre issue
		$eUserAvatar = $this->getAvatar($eUser);

		if($eUserAvatar['replacesPhoto']) {
			$this->assertDiffer($vignette, $this->getVignette($eUser), 'image du modèle retiré gardée');
		} else {
			$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue');
		}

	}

	public function testPage(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->assertEquals('user\\UserAvatarUpload::stale', $this->post($eUser, 'doPhoto', ['replacesPhoto' => '0']), 'interrupteur accepté sans modèle en place');

		$this->create($eUser, FALSE);

		$this->assertEquals('user\\UserAvatar::image', $this->post($eUser, 'doPhoto', ['replacesPhoto' => '1']), 'photo remplacée sans image');
		$this->assertFalse($this->getAvatar($eUser)['replacesPhoto'], 'choix de la photo écrit malgré le refus');

		$this->create($eUser, TRUE);

		$this->assertEquals('user\\UserAvatar::image', $this->post($eUser, 'doClear', []), 'modèle supprimé sans image sur une photo remplacée');
		$this->assertTrue($this->getAvatar($eUser)['upload']->notEmpty(), 'modèle supprimé malgré le refus');

		$this->assertEquals('ViewAction', $this->post($eUser, 'doPhoto', ['replacesPhoto' => '0']), 'photo non rendue par l\'interrupteur');
		$this->assertEquals('ReloadAction', $this->post($eUser, 'doClear', []), 'modèle non supprimé sans photo remplacée');
		$this->assertTrue($this->getAvatar($eUser)['upload']->empty(), 'modèle gardé');

		$eUserAvatar = $this->getAvatar($eUser);
		UserAvatarLib::delete($eUserAvatar);

		$this->assertEquals('NotExpectedAction', $this->post($eUser, 'doPhoto', ['replacesPhoto' => '0']), 'interrupteur accepté sans avatar');
		$this->assertEquals('NotExpectedAction', $this->post($eUser, 'doClear', []), 'suppression acceptée sans avatar');

	}

	/**
	 * Une action de `avatarUpload.p.php` jouée en POST comme une requête de `$eUser`, forme de `execute()` dans
	 * `UserAvatarUploadModerationAi`. Rend le fail d'une `FailAction`, sinon la classe de l'action levée.
	 */
	private function post(User $eUser, string $page, array $input): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		ConnectionLib::setOnline($eUser);

		// Ce que `\main\PageLib::common()` pose pour un utilisateur connecté, que la ligne de commande saute
		$data = new \stdClass();
		$data->eUserOnline = UserLib::getById($eUser['id']);
		$data->eUserOnline['eUserAvatar'] = UserAvatarLib::getByUser($eUser);
		$data->ccStructureUser = new \Collection();
		$data->hasAvatar = TRUE;

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'user/avatarUpload:'.$page), $data);
			return 'aucune action';
		} catch(\FailAction $action) {
			$fail = new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action);
			return is_string($fail) ? $fail : $fail->getLast();
		} catch(\Action $action) {
			return new \ReflectionClass($action)->getShortName();
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Un modèle posé sur l'avatar de `$eUser`, sur une conversion fictive, avec le choix de remplacer la photo et, quand
	 * il la remplace, une image écrite par le test.
	 */
	private function create(User $eUser, bool $replacesPhoto): UserAvatarUpload {

		$eUserAvatarUpload = $this->getUpload($eUser);
		$image = $replacesPhoto ? $this->getImage() : NULL;

		UserAvatarUploadLib::create($eUserAvatarUpload, $this->getPosted($eUser, $replacesPhoto), $image);

		if($image !== NULL) {
			unlink($image);
		}

		$vignette = $this->getVignette($eUser);

		if($vignette !== NULL) {
			$this->vignettes[] = substr($vignette, 0, 20);
		}

		return $eUserAvatarUpload;

	}

	// Sans `build()` : la conversion par node, que `build()` lance sur le fichier, n'est pas ce que ce test éprouve
	private function getUpload(User $eUser): UserAvatarUpload {

		return new UserAvatarUpload([
			'user' => $eUser,
			'rules' => \scene\UploadLib::getRules('avatar')['version'],
			'bakedIndex' => ['models' => []],
			'bakedBuffer' => 'model'
		]);

	}

	// L'avatar tel que la page le pose après `build()` de la case
	private function getPosted(User $eUser, bool $replacesPhoto): UserAvatar {

		$eUserAvatar = $this->getAvatar($eUser);
		$eUserAvatar['replacesPhoto'] = $replacesPhoto;

		return $eUserAvatar;

	}

	private function getAvatar(User $eUser): UserAvatar {

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		return $eUserAvatar;

	}

	// L'avatar revient à sa composition sans que l'image du modèle quitte la photo, comme juste après un retrait
	private function withdrawShown(User $eUser): void {

		$eUserAvatar = $this->getAvatar($eUser);
		$eUserAvatar['upload'] = new UserAvatarUpload();

		UserAvatarLib::update($eUserAvatar, ['upload']);

	}

	private function getHashes(): array {

		return \media\Media::model()
			->whereType('user-avatar-upload')
			->getColumn('hash');

	}

	private function getImage(): string {

		$file = tempnam(sys_get_temp_dir(), 'avatar');

		$draw = new \ImagickDraw();
		$draw->setFillColor('#2fbb75');
		$draw->rectangle(128, 128, 383, 383);

		$resource = new \Imagick();
		$resource->newImage(512, 512, 'transparent');
		$resource->drawImage($draw);
		$resource->setImageFormat('png');
		$resource->writeImage('png:'.$file);

		return $file;

	}

	private function getVignette(User $eUser): ?string {

		return User::model()
			->select('vignette')
			->whereId($eUser['id'])
			->getValue('vignette');

	}

	/**
	 * Un utilisateur sans avatar, dont la photo de profil devient un média fictif : la vraie n'est ni lue ni écrite. Son
	 * avatar composé ne remplace pas la photo.
	 */
	private function getUser(): User {

		$eUser = User::model()
			->select('id', 'vignette')
			->join(UserAvatar::model(), 'm1.id = m2.user', 'LEFT')
			->where('m2.id IS NULL')
			->sort(['m1.id' => SORT_ASC])
			->get();

		if($this->assertTrue($eUser->notEmpty(), 'aucun utilisateur sans avatar en base') === FALSE) {
			return new User();
		}

		// Un média fictif, sans fichier : build() n'a rien à vérifier sur une ligne que seul le test lit
		$eMedia = new \media\Media([
			'user' => $eUser,
			'type' => 'user-vignette',
			'hash' => self::PHOTO,
			'status' => \media\Media::ACTIVE,
		]);

		\media\Media::model()->insert($eMedia);

		$eUser['vignette'] = self::PHOTO.'#000000001';
		UserLib::update($eUser, ['vignette']);

		UserAvatarLib::create(new UserAvatar(['user' => $eUser, 'composition' => UserAvatarLib::getDefault(), 'replacesPhoto' => FALSE]));

		ConnectionLib::setOnline($eUser);

		return $eUser;

	}

}
?>
