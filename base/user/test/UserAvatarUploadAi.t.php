<?php
namespace user;

/**
 * Le modèle 3D téléversé à la place d'un avatar composé (`UserAvatarUpload::properties()`, `UserAvatarUploadLib`,
 * `\scene\UploadLib`, `base/scene/upload/upload.mjs`).
 *
 * Ce qui s'y prouve :
 * 1. un modèle aux règles se convertit au format que lit `sceneBuildModel()`, ses pivots et ses parents pris dans les
 *    règles, chaque pose de l'éditeur avec la descente de ses propres pieds ;
 * 2. chaque règle a son refus : fichier qui n'est pas un glTF binaire, trop lourd, qui demande un fichier ou une
 *    extension, qui porte une texture, une armature ou une forme animée, des nœuds qui se bouclent, une pièce qui ne
 *    nomme pas un os (`constructor` compris), un os sans pièce, trop de triangles, un modèle trop grand ou qui ne
 *    pose pas les pieds au sol, un nombre non fini ou un indice hors du tampon ;
 * 3. chaque code de refus des règles a son message ;
 * 4. `build()` refuse un envoi sans fichier, sans engagement, au mauvais en-tête ou trop lourd avant de lancer node ;
 * 5. au-delà de cinquante envois par 24 heures, refus compris, l'utilisateur est refusé ;
 * 6. un envoi se montre à la place de la composition, un second remplace le premier, dont le fichier part, et le
 *    retour à la composition comme la suppression de l'avatar retirent le fichier en gardant la ligne ;
 * 7. seul l'auteur lit son envoi, et seul un utilisateur qui a un avatar composé téléverse ;
 * 8. le drapeau fermé ferme l'envoi ;
 * 9. un envoi garde la version des règles sous laquelle il a été coché, et donc le texte de l'engagement, quand un
 *    nouvel envoi prend la version courante.
 *
 * Décor : un utilisateur sans avatar, à qui le test compose un avatar qui ne remplace pas sa photo, dans une
 * transaction annulée à la fin de chaque méthode. Les modèles sont écrits par le test, aucun fichier binaire n'est
 * suivi ; les fichiers que l'envoi pose dans le stockage sont effacés à la fin.
 *
 * Lancement : dev/test package=user file=UserAvatarUploadAi
 */
class UserAvatarUploadAiTest extends \dev\TransactionTest {

	/**
	 * Un corps en boites, une par os, les pieds à y = 0, au gabarit des pivots de `avatar.json`.
	 */
	const BODY = [
		'bassin' => [[-.17, .6, -.1], [.17, .75, .1], [.2, .2, .5]],
		'torse' => [[-.18, .75, -.11], [.18, 1.15, .11], [.8, .1, .1]],
		'tete' => [[-.1, 1.18, -.11], [.1, 1.45, .11], [.9, .6, .4]],
		'brasG' => [[.19, .85, -.05], [.25, 1.1, .05], [.8, .1, .1]],
		'brasD' => [[-.25, .85, -.05], [-.19, 1.1, .05], [.8, .1, .1]],
		'avantBrasG' => [[.19, .6, -.05], [.25, .85, .05], [.9, .6, .4]],
		'avantBrasD' => [[-.25, .6, -.05], [-.19, .85, .05], [.9, .6, .4]],
		'jambeG' => [[.05, .37, -.07], [.16, .68, .07], [.2, .2, .5]],
		'jambeD' => [[-.16, .37, -.07], [-.05, .68, .07], [.2, .2, .5]],
		'tibiaG' => [[.06, 0, -.06], [.15, .37, .12], [.1, .1, .1]],
		'tibiaD' => [[-.15, 0, -.06], [-.06, .37, .12], [.1, .1, .1]],
	];

	private User $eUserInitial;

	private array $files = [];
	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function setUp(): void {

		$this->eUserInitial = ConnectionLib::getOnline();

		parent::setUp();

		$this->hashesInitial = $this->getHashes();

	}

	public function tearDown(): void {

		// Les médias écrits par l'envoi disparaissent avec la transaction, pas leurs fichiers : ils se lisent donc avant
		// elle, et seulement ceux du test, la base de dev pouvant porter des envois qui ne sont pas les siens
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

	}

	public function testBake(): void {

		$rules = \scene\UploadLib::getRules('avatar');
		$result = \scene\UploadLib::run('avatar', $this->write($this->getGlb()));

		if($this->assertFalse(array_key_exists('refusal', $result), 'modèle aux règles refusé : '.json_encode($result)) === FALSE) {
			return;
		}

		$model = $result['index']['models']['avatar'];

		$this->assertEquals($rules['version'], $result['rules'], 'version des règles perdue');
		$this->assertEquals(count(self::BODY) * 12, $result['triangles'], 'triangles mal comptés');
		$this->assertEquals($result['triangles'] * 3 * 9, strlen($result['buffer']), 'tampon d\'une autre taille que l\'index');
		$this->assertEquals($rules['pivots'], $model['pivots'], 'pivots pris ailleurs que dans les règles');
		$this->assertEquals($rules['parents'], $model['parents'], 'parents pris ailleurs que dans les règles');
		$this->assertEquals(array_keys($rules['poses']), array_keys($model['poses']), 'poses de l\'éditeur perdues');
		$this->assertEquals(count(self::BODY), count($model['parts']), 'une pièce par os et par couleur attendue');
		$this->assertEquals(0.0, (float)$model['box'][0][1], 'pieds hors du sol dans la boite');

		foreach($model['parts'] as $part) {
			$this->assertEquals($this->srgb(self::BODY[$part['name']][2]), $part['color'], 'couleur du matériau de '.$part['name'].' perdue');
		}

		// Assis, la cuisse à l'horizontale et le tibia d'aplomb : le corps descend de la longueur de la cuisse, qui fait ici
		// toute la descente prévue par `descenteAssis`, au millimètre de la boite du pied près
		$this->assertTrue(abs($model['poses']['assis']['descente'] - 1) < .25, 'descente assise hors de mesure : '.$model['poses']['assis']['descente']);
		$this->assertTrue(abs($model['poses']['marche']['descente']) < .25, 'descente de marche hors de mesure : '.$model['poses']['marche']['descente']);

	}

	public function testRefusals(): void {

		$refused = fn(string $glb) => \scene\UploadLib::run('avatar', $this->write($glb))['refusal'] ?? NULL;

		$this->assertEquals('format', $refused('glTF n\'importe quoi'), 'en-tête seul accepté');
		$this->assertEquals('format', $refused(substr($this->getGlb(), 0, -8)), 'fichier tronqué accepté');
		$this->assertEquals('external', $refused($this->getGlb(alter: function(array &$json) { $json['extensionsUsed'] = ['KHR_draco_mesh_compression']; })), 'extension acceptée');
		$this->assertEquals('external', $refused($this->getGlb(alter: function(array &$json) { $json['buffers'][0]['uri'] = 'file:///etc/passwd'; })), 'adresse externe acceptée');
		$this->assertEquals('external', $refused($this->getGlb(alter: function(array &$json) { $json['buffers'][0]['uri'] = '/etc/passwd'; })), 'chemin du serveur accepté');
		$this->assertEquals('texture', $refused($this->getGlb(alter: function(array &$json) { $json['images'] = [['uri' => 'x.png']]; })), 'image acceptée');
		$this->assertEquals('rig', $refused($this->getGlb(alter: function(array &$json) { $json['skins'] = [['joints' => [0]]]; })), 'armature acceptée');
		$this->assertEquals('rig', $refused($this->getGlb(alter: function(array &$json) { $json['meshes'][0]['primitives'][0]['targets'] = [['POSITION' => 0]]; })), 'forme animée acceptée');
		$this->assertEquals('format', $refused($this->getGlb(alter: function(array &$json) { $json['nodes'][0]['children'] = [0]; })), 'nœud qui se contient accepté');
		$this->assertEquals('bone', $refused($this->getGlb(['constructor' => self::BODY['tete']] + self::BODY)), 'os `constructor` accepté');
		$this->assertEquals('bone', $refused($this->getGlb(['pied' => self::BODY['tete']] + self::BODY)), 'os inconnu accepté');
		$this->assertEquals('missing', $refused($this->getGlb(array_diff_key(self::BODY, ['tete' => TRUE]))), 'corps sans tête accepté');
		$this->assertEquals('triangles', $refused($this->getGlb(repeat: ['tete' => 270])), 'modèle au-dessus du plafond de triangles accepté');
		$this->assertEquals('bounds', $refused($this->getGlb(['tete' => [[-2, 1.18, -.11], [.1, 1.45, .11], [.9, .6, .4]]] + self::BODY)), 'modèle trop large accepté');
		$this->assertEquals('ground', $refused($this->getGlb(alter: function(array &$json) { foreach($json['nodes'] as &$node) { $node['translation'] = [0, .1, 0]; } })), 'modèle qui flotte accepté');
		$this->assertEquals('format', $refused($this->getGlb(nan: TRUE)), 'nombre non fini accepté');
		$this->assertEquals('format', $refused($this->getGlb(alter: function(array &$json) { $json['accessors'][1]['count'] = 1000000; })), 'accesseur hors du tampon accepté');
		$this->assertEquals('format', $refused($this->getGlb(outOfRange: TRUE)), 'indice hors des sommets accepté');

		$this->assertEquals('weight', \scene\UploadLib::run('avatar', $this->write(str_repeat('x', \scene\UploadLib::getRules('avatar')['maxBytes'] + 1)))['refusal'] ?? NULL, 'fichier trop lourd accepté');

	}

	public function testRefusalMessages(): void {

		$values = ['size' => 25480000, 'max' => 1048576, 'name' => '<b>x</b>', 'bones' => 'tete', 'bone' => 'tete', 'count' => 4812];

		foreach(\scene\UploadLib::getRules('avatar')['refusals'] as $refusal) {
			$this->assertTrue(AlertUi::getAvatarUploadRefusal($refusal, $values) !== '', 'refus '.$refusal.' sans message');
		}

		$this->assertFalse(str_contains(AlertUi::getAvatarUploadRefusal('bone', $values), '<b>x</b>'), 'nom de pièce non échappé');

		// Chaque endroit qui refuse passe à la closure du message les mêmes paramètres nommés : un oubli lève `Unknown named parameter`
		$fw = new \FailWatch();

		\Fail::log(UserAvatarUpload::getRefusal('weight', ['size' => 25480000]));
		\Fail::log(UserAvatarUpload::getRefusal('format'));
		\Fail::log(UserAvatarUpload::getRefusal('triangles', ['count' => 4812, 'max' => 3200]));

		$messages = array_column($fw->format(), 1);

		$this->assertEquals(3, count(array_filter($messages, 'is_string')), 'refus sans message');

	}

	public function testBuild(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$this->assertTrue($this->build($eUser, NULL, '1')->has('UserAvatarUpload::file.check'), 'envoi sans fichier accepté');
		$this->assertTrue($this->build($eUser, $this->write($this->getGlb()), '')->has('UserAvatarUpload::commitment.check'), 'envoi sans engagement accepté');
		$this->assertTrue($this->build($eUser, $this->write('PK zip'), '1')->has('UserAvatarUpload::file.conformity'), 'en-tête autre que glTF accepté');

		$eUserAvatarUpload = new UserAvatarUpload(['user' => $eUser]);
		$fw = new \FailWatch();
		$eUserAvatarUpload->build(UserAvatarUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1']);

		$this->assertTrue($fw->ok(), 'envoi aux règles refusé');
		$this->assertTrue(is_array($eUserAvatarUpload['bakedIndex']), 'index de la conversion non posé');
		$this->assertEquals(\scene\UploadLib::getRules('avatar')['version'], $eUserAvatarUpload['rules'], 'version des règles non posée');

		$this->clearThrottle($eUser);

	}

	public function testThrottle(): void {

		// Le message du débit écrit la limite en lettres (« cinquante », AlertUi) : une autre limite le rend faux
		$this->assertEquals(50, ThrottleAiLib::LIMIT_AVATAR_UPLOAD, 'limite du débit changée sans réécrire son message dans Alert.u.php');

		$eUser = $this->getUser();

		if($eUser->empty() or $this->assertDecorPresent(\Cache::isDisabled() === FALSE, 'le cache actif sur ce poste, qui compte les envois') === FALSE) {
			return;
		}

		$this->clearThrottle($eUser);

		for($i = 0; $i < ThrottleAiLib::LIMIT_AVATAR_UPLOAD; $i++) {
			ThrottleAiLib::acceptAvatarUpload($eUser);
		}

		$fw = $this->build($eUser, $this->write($this->getGlb()), '1');
		$this->assertTrue($fw->has('UserAvatarUpload::file.throttled'), 'cinquante et unième envoi accepté');

		// L'écran ne refuse le fichier que sur un refus du champ `file` : le débit doit tomber à côté, sur `throttled`
		[[, $message, $wrapper]] = $fw->format();
		$this->assertEquals('throttled', $wrapper, 'le débit tombe sur le champ du fichier');
		$this->assertTrue($message !== NULL, 'le débit sans message');

		$this->clearThrottle($eUser);

		[[, , $wrapper]] = $this->build($eUser, $this->write('pas un GLB'), '1')->format();
		$this->assertEquals('file', $wrapper, 'un refus du fichier tombe ailleurs que sur son champ');

		$this->clearThrottle($eUser);

	}

	/**
	 * Un node qui ne démarre pas est un défaut du serveur : l'envoi se dit indisponible, sur `throttled` comme le débit,
	 * pour que l'écran garde le fichier au lieu de le dire refusé.
	 */
	public function testNodeUnavailable(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$fw = $this->buildUnderDeadNode(fn() => $this->build($eUser, $this->write($this->getGlb()), '1'));
		$this->assertTrue($fw->has('UserAvatarUpload::file.unavailable'), 'node qui ne démarre pas rendu comme un refus du fichier');

		[[, $message, $wrapper]] = $fw->format();
		$this->assertEquals('throttled', $wrapper, 'l\'import indisponible tombe sur le champ du fichier');
		$this->assertTrue($message !== NULL, 'l\'import indisponible sans message');

		$this->clearThrottle($eUser);

	}

	public function testLifecycle(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$eUserAvatarUploadFirst = $this->create($eUser);
		$eUserAvatar = UserAvatarLib::getByUser($eUser);

		$this->assertEquals($eUserAvatarUploadFirst['id'], $eUserAvatar['upload']['id'], 'envoi non montré par l\'avatar');
		$this->assertEquals('b', $eUserAvatarUploadFirst['hash'][19], 'fichier converti sans la lettre des binaires');
		$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($eUserAvatarUploadFirst['hash']), 'média de l\'envoi inactif');
		$this->assertEquals($eUserAvatarUploadFirst['id'], UserAvatarUploadLib::getByAvatar($eUserAvatar)['id'], 'envoi montré introuvable');

		$eUserAvatarUploadSecond = $this->create($eUser);

		$this->assertEquals($eUserAvatarUploadSecond['id'], UserAvatarLib::getByUser($eUser)['upload']['id'], 'second envoi non montré');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eUserAvatarUploadFirst['hash']), 'fichier du premier envoi gardé');
		$eUserAvatarUploadFirstRead = UserAvatarUpload::model()->select('hash', 'bakedIndex')->whereId($eUserAvatarUploadFirst['id'])->get();

		$this->assertTrue($eUserAvatarUploadFirstRead->notEmpty(), 'ligne du premier envoi perdue');
		$this->assertEquals([NULL, NULL], [$eUserAvatarUploadFirstRead['hash'], $eUserAvatarUploadFirstRead['bakedIndex']], 'premier envoi gardé avec son fichier');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarUploadLib::clear($eUserAvatar, NULL);

		$this->assertTrue(UserAvatarLib::getByUser($eUser)['upload']->empty(), 'retour à la composition sans effet');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eUserAvatarUploadSecond['hash']), 'fichier gardé au retour à la composition');
		$this->assertEquals(2, UserAvatarUpload::model()->whereUser($eUser)->count(), 'lignes des envois perdues');

		// Enregistrer une composition la choisit, et retire le modèle en place
		$eUserAvatarUploadReplaced = $this->create($eUser);

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::save($eUserAvatar, '');

		$this->assertTrue(UserAvatarLib::getByUser($eUser)['upload']->empty(), 'modèle gardé à l\'enregistrement d\'une composition');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eUserAvatarUploadReplaced['hash']), 'fichier gardé à l\'enregistrement d\'une composition');

		$eUserAvatarUploadThird = $this->create($eUser);

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);

		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eUserAvatarUploadThird['hash']), 'fichier gardé à la suppression de l\'avatar');

		$this->clearThrottle($eUser);

	}

	public function testRulesVersion(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$rules = \scene\UploadLib::getRules('avatar')['version'];
		$rulesOther = $rules + 1;

		$eUserAvatarUploadOld = $this->create($eUser);

		// Un envoi coché sous une autre version que la courante, donc sous un autre texte de l'engagement : build() ne
		// pose que la version que la conversion rend, celle des règles du dépôt, et ne peut pas fabriquer cet envoi
		UserAvatarUpload::model()->update($eUserAvatarUploadOld, ['rules' => $rulesOther]);

		$eUserAvatarUploadNew = $this->create($eUser);

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarUploadLib::clear($eUserAvatar, NULL);

		$this->assertEquals($rulesOther, UserAvatarUpload::model()->select('rules')->whereId($eUserAvatarUploadOld['id'])->getValue('rules'), 'version de l\'engagement d\'un envoi passé réécrite');
		$this->assertEquals($rules, UserAvatarUpload::model()->select('rules')->whereId($eUserAvatarUploadNew['id'])->getValue('rules'), 'nouvel envoi sans la version courante des règles');

		$this->clearThrottle($eUser);

	}

	public function testRights(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$eUserAvatarUpload = $this->create($eUser);

		$this->assertTrue($eUserAvatarUpload->canRead(), 'auteur sans droit de lecture');

		ConnectionLib::setOnline(new User(['id' => $eUser['id'] + 1]));
		$this->assertFalse($eUserAvatarUpload->canRead(), 'envoi lu par un autre utilisateur');

		$eUserOther = new User(['id' => $eUser['id'], 'eUserAvatar' => new UserAvatar()]);
		$this->assertFalse(new UserAvatarUpload(['user' => $eUserOther])->acceptCreate(), 'envoi sans avatar composé accepté');

		$this->clearThrottle($eUser);

	}

	/**
	 * Un modèle converti avant que la conversion lisse ses normales se reprend depuis son binaire : seules les normales et
	 * le hash changent, l'ancien fichier part, et un second passage ne fait rien.
	 */
	public function testSmooth(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$this->clearThrottle($eUser);

		$eUserAvatarUpload = $this->create($eUser);
		$hashBaked = $eUserAvatarUpload['hash'];

		// L'index se relit en base avant comme après : MySQL range les clés d'une colonne json à sa façon
		$bakedIndex = UserAvatarUpload::model()->select('bakedIndex')->whereId($eUserAvatarUpload['id'])->getValue('bakedIndex');
		$path = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($hashBaked);
		$bufferBaked = file_get_contents($path);

		// Le binaire d'un modèle d'avant le lissage, que la conversion ne sait plus produire : ses normales se remettent à
		// zéro sur le disque, puisque la reprise les refait des seules positions sans jamais lire celles qu'il porte
		$vertices = $eUserAvatarUpload['bakedIndex']['vertices'];
		file_put_contents($path, substr($bufferBaked, 0, $vertices * 6).str_repeat("\0", $vertices * 3));

		[$cUserAvatarUpload] = UserAvatarUploadLib::getUnsmoothed([$eUser['id']]);
		$this->assertTrue($cUserAvatarUpload->offsetExists($eUserAvatarUpload['id']), 'envoi aux normales d\'avant non relevé pour la reprise');

		$this->assertTrue(UserAvatarUploadLib::smooth($eUserAvatarUpload), 'envoi aux normales d\'avant non repris');

		$eUserAvatarUploadRead = UserAvatarUpload::model()
			->select('id', 'hash', 'bakedIndex')
			->whereId($eUserAvatarUpload['id'])
			->get();

		$this->assertTrue($eUserAvatarUploadRead['hash'] !== $hashBaked, 'reprise écrite sous l\'ancien hash');
		$this->assertEquals($eUserAvatarUploadRead['hash'], $eUserAvatarUpload['hash'], 'nouveau hash absent de l\'élément repris');
		$this->assertEquals($bakedIndex, $eUserAvatarUploadRead['bakedIndex'], 'index changé par la reprise');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($hashBaked), 'ancien fichier gardé après la reprise');

		$pathSmoothed = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($eUserAvatarUploadRead['hash']);
		$this->assertTrue(is_file($pathSmoothed) and file_get_contents($pathSmoothed) === $bufferBaked, 'reprise différente de la conversion du même modèle');

		$this->assertFalse(UserAvatarUploadLib::smooth($eUserAvatarUpload), 'envoi déjà repris écrit une seconde fois');
		$this->assertEquals($eUserAvatarUploadRead['hash'], UserAvatarUpload::model()->select('hash')->whereId($eUserAvatarUpload['id'])->getValue('hash'), 'hash changé par un second passage');

		[$cUserAvatarUpload] = UserAvatarUploadLib::getUnsmoothed([$eUser['id']]);
		$this->assertFalse($cUserAvatarUpload->offsetExists($eUserAvatarUpload['id']), 'envoi repris encore relevé pour la reprise');

		// Une écriture qui lève au milieu de la reprise : la transaction se referme à son étage, sans emporter celle du test,
		// et l'envoi cite toujours un fichier présent et actif
		file_put_contents($pathSmoothed, substr($bufferBaked, 0, $vertices * 6).str_repeat("\0", $vertices * 3));

		$stack = $this->getTransactionStack();
		$driver = \media\MediaSetting::$mediaDriver;

		\media\MediaSetting::$mediaDriver = new class extends \storage\DriverLib {
			public static function sendBinary(string $data, string $path): void {
				throw new \Exception('Disque plein');
			}
		};

		try {
			UserAvatarUploadLib::smooth($eUserAvatarUploadRead);
			$raised = FALSE;
		} catch(\Exception) {
			$raised = TRUE;
		} finally {
			\media\MediaSetting::$mediaDriver = $driver;
		}

		$this->assertTrue($raised, 'échec d\'écriture tu par la reprise');
		$this->assertEquals($stack, $this->getTransactionStack(), 'transaction de la reprise laissée ouverte après un échec');
		$this->assertEquals($eUserAvatarUploadRead['hash'], UserAvatarUpload::model()->select('hash')->whereId($eUserAvatarUpload['id'])->getValue('hash'), 'envoi détaché de son fichier par une reprise en échec');
		$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($eUserAvatarUploadRead['hash']), 'fichier de l\'envoi marqué à effacer par une reprise en échec');
		$this->assertTrue(is_file($pathSmoothed), 'fichier de l\'envoi retiré par une reprise en échec');

		$this->assertTrue(UserAvatarUploadLib::smooth($eUserAvatarUploadRead), 'envoi non repris après un échec');
		$pathSmoothed = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($eUserAvatarUploadRead['hash']);

		// Un envoi retiré par un admin ne se montre plus : il ne se reprend pas, même aux normales d'avant
		file_put_contents($pathSmoothed, substr($bufferBaked, 0, $vertices * 6).str_repeat("\0", $vertices * 3));
		$eUserAvatarUploadRead['status'] = UserAvatarUpload::WITHDRAWN;
		UserAvatarUploadLib::update($eUserAvatarUploadRead, ['status']);

		[$cUserAvatarUpload, $cUserAvatarUploadMissing] = UserAvatarUploadLib::getUnsmoothed([$eUser['id']]);
		$this->assertFalse($cUserAvatarUpload->offsetExists($eUserAvatarUpload['id']), 'envoi retiré relevé pour la reprise');

		// Un fichier absent du disque est une panne, comptée à part et jamais reprise
		$eUserAvatarUploadRead['status'] = UserAvatarUpload::NEW;
		UserAvatarUploadLib::update($eUserAvatarUploadRead, ['status']);
		unlink($pathSmoothed);

		[$cUserAvatarUpload, $cUserAvatarUploadMissing] = UserAvatarUploadLib::getUnsmoothed([$eUser['id']]);
		$this->assertFalse($cUserAvatarUpload->offsetExists($eUserAvatarUpload['id']), 'envoi sans fichier relevé pour la reprise');
		$this->assertTrue($cUserAvatarUploadMissing->offsetExists($eUserAvatarUpload['id']), 'envoi sans fichier non signalé');

		// Un binaire que la conversion ne relit pas est une panne aussi : rangé à part, sans arrêter le relevé
		file_put_contents($pathSmoothed, substr($bufferBaked, 0, 9));

		[$cUserAvatarUpload, $cUserAvatarUploadMissing, $cUserAvatarUploadBroken] = UserAvatarUploadLib::getUnsmoothed([$eUser['id']]);
		$this->assertFalse($cUserAvatarUpload->offsetExists($eUserAvatarUpload['id']), 'binaire cassé relevé pour la reprise');
		$this->assertTrue($cUserAvatarUploadBroken->offsetExists($eUserAvatarUpload['id']), 'binaire cassé non signalé');

		unlink($pathSmoothed);

		$this->clearThrottle($eUser);

	}

	public function testFlag(): void {

		$eStructure = new \structure\Structure(['id' => 1]);
		$ccStructure = new \Collection([new \Collection([$eStructure])]);

		$enabled = UserSetting::$avatarUpload;

		UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => FALSE;
		$this->assertFalse(UserAvatarUploadLib::isOpenByStructures($ccStructure), 'drapeau fermé qui ouvre l\'envoi');

		UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => ($eStructure['id'] === 1);
		$this->assertTrue(UserAvatarUploadLib::isOpenByStructures($ccStructure), 'drapeau ouvert à la structure qui ferme l\'envoi');
		$this->assertFalse(UserAvatarUploadLib::isOpenByStructures(new \Collection()), 'envoi ouvert à un compte sans structure');

		UserSetting::$avatarUpload = $enabled;

	}

	/**
	 * Un utilisateur sans avatar, connecté, à qui le test compose un avatar qui ne remplace pas la photo : ni la photo ni
	 * les médias de vignette ne sont touchés.
	 */
	private function getHashes(): array {

		return \media\Media::model()
			->whereType('user-avatar-upload')
			->getColumn('hash');

	}

	// Le cadriciel n'expose pas l'étage de sa pile de transactions, que la reprise doit rendre tel qu'elle l'a trouvé
	private function getTransactionStack(): array {
		return new \ReflectionProperty(\ModuleModel::class, 'dbTransactionStack')->getValue();
	}

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

		UserAvatarLib::create(new UserAvatar(['user' => $eUser, 'composition' => UserAvatarLib::getDefault(), 'replacesPhoto' => FALSE]));

		$eUser['eUserAvatar'] = UserAvatarLib::getByUser($eUser);

		ConnectionLib::setOnline($eUser);

		return $eUser;

	}

	private function create(User $eUser): UserAvatarUpload {

		$eUserAvatarUpload = new UserAvatarUpload(['user' => $eUser]);
		$fw = new \FailWatch();
		$eUserAvatarUpload->build(UserAvatarUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1']);
		$fw->validate();

		UserAvatarUploadLib::create($eUserAvatarUpload);

		return $eUserAvatarUpload;

	}

	private function build(User $eUser, ?string $file, string $commitment): \FailWatch {

		$fw = new \FailWatch();

		new UserAvatarUpload(['user' => $eUser])->build(UserAvatarUploadLib::getPropertiesCreate(), ['file' => $file, 'commitment' => $commitment]);

		return $fw;

	}

	// La conversion sous un node factice qui meurt avant la première ligne, comme celui qui ne trouve pas la réserve de V8
	// sous son plafond : l'exception que l'envoi journalise n'est ni consignée en base ni écrite dans la sortie du test
	private function buildUnderDeadNode(\Closure $build): \FailWatch {

		$node = new \ReflectionProperty(\scene\UploadLib::class, 'node');
		$nodeInitial = $node->getValue();
		$saveErrorInitial = \dev\ErrorPhpLib::$saveError;

		$nodeDead = $this->write("#!/bin/sh\nexit 133\n");
		chmod($nodeDead, 0700);

		$node->setValue(NULL, $nodeDead);
		\dev\ErrorPhpLib::saveError(FALSE);
		ob_start();

		try {
			return $build();
		} finally {
			ob_end_clean();
			\dev\ErrorPhpLib::saveError($saveErrorInitial);
			$node->setValue(NULL, $nodeInitial);
		}

	}

	private function clearThrottle(User $eUser): void {
		ThrottleAiLib::clear('avatar-upload', (string)$eUser['id']);
	}

	private function getMediaStatus(string $hash): string {

		return \media\Media::model()
			->select('status')
			->whereType('user-avatar-upload')
			->whereHash($hash)
			->getValue('status');

	}

	private function write(string $content): string {

		$file = tempnam(sys_get_temp_dir(), 'avatar-upload');
		file_put_contents($file, $content);

		$this->files[] = $file;

		return $file;

	}

	// Une couleur linéaire de glTF en hexadécimal sRGB, comme la cuisson la garde
	private function srgb(array $linear): string {

		return implode('', array_map(function(float $value): string {
			$srgb = ($value <= .0031308) ? $value * 12.92 : 1.055 * $value ** (1 / 2.4) - .055;
			return str_pad(dechex((int)round($srgb * 255)), 2, '0', STR_PAD_LEFT);
		}, $linear));

	}

	/**
	 * Un glTF binaire : une boite par pièce, un nœud et un maillage indexé par pièce, nommés d'après la pièce, et une
	 * couleur de matériau chacune. `repeat` répète les triangles d'une pièce, `nan` glisse un nombre non fini dans la
	 * première pièce, `outOfRange` un indice au-delà de ses sommets, et `alter` retouche le JSON.
	 */
	private function getGlb(array $pieces = self::BODY, array $repeat = [], bool $nan = FALSE, bool $outOfRange = FALSE, ?\Closure $alter = NULL): string {

		$json = ['asset' => ['version' => '2.0'], 'scene' => 0, 'scenes' => [['nodes' => []]], 'nodes' => [], 'meshes' => [], 'materials' => [], 'accessors' => [], 'bufferViews' => []];
		$bin = '';
		$k = 0;

		foreach($pieces as $name => [$min, $max, $color]) {

			$positions = [];

			foreach([0, 1] as $z) {
				foreach([0, 1] as $y) {
					foreach([0, 1] as $x) {
						array_push($positions, $x ? $max[0] : $min[0], $y ? $max[1] : $min[1], $z ? $max[2] : $min[2]);
					}
				}
			}

			if($nan and $k === 0) {
				$positions[0] = NAN;
			}

			$box = [0, 2, 1, 1, 2, 3, 4, 5, 6, 5, 7, 6, 0, 1, 4, 1, 5, 4, 2, 6, 3, 3, 6, 7, 0, 4, 2, 2, 4, 6, 1, 3, 5, 3, 7, 5];
			$indices = array_merge(...array_fill(0, $repeat[$name] ?? 1, $box));

			if($outOfRange and $k === 0) {
				$indices[0] = 8;
			}

			$positionOffset = strlen($bin);
			$bin .= pack('g*', ...$positions);
			$indexOffset = strlen($bin);
			$bin .= pack('v*', ...$indices);
			$bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);

			$json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => $positionOffset, 'byteLength' => count($positions) * 4];
			$json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => $indexOffset, 'byteLength' => count($indices) * 2];
			$json['accessors'][] = ['bufferView' => 2 * $k, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC3'];
			$json['accessors'][] = ['bufferView' => 2 * $k + 1, 'componentType' => 5123, 'count' => count($indices), 'type' => 'SCALAR'];
			$json['materials'][] = ['pbrMetallicRoughness' => ['baseColorFactor' => [...$color, 1]]];
			$json['meshes'][] = ['primitives' => [['attributes' => ['POSITION' => 2 * $k], 'indices' => 2 * $k + 1, 'material' => $k]]];
			$json['nodes'][] = ['name' => $name, 'mesh' => $k];
			$json['scenes'][0]['nodes'][] = $k;

			$k++;

		}

		$json['buffers'] = [['byteLength' => strlen($bin)]];

		if($alter !== NULL) {
			$alter($json);
		}

		$text = json_encode($json);
		$text .= str_repeat(' ', (4 - strlen($text) % 4) % 4);

		$body = pack('VV', strlen($text), 0x4e4f534a).$text.pack('VV', strlen($bin), 0x004e4942).$bin;

		return pack('VVV', 0x46546c67, 2, 12 + strlen($body)).$body;

	}

}
?>
