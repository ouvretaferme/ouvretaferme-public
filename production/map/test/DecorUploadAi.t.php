<?php
namespace map;

/**
 * Le modèle 3D téléversé à la place d'un bâtiment composé (`DecorUpload::properties()`, `DecorUploadLib`,
 * `page/decorUpload.p.php`), par le chemin d'écriture du produit : `build()` sur la liste du Lib, puis le Lib. La
 * conversion elle-même, ses pièces, ses ancres et chacun de ses refus, s'éprouve dans `base/scene/test/UploadBuildingAi`.
 *
 * Ce qui s'y prouve :
 * 1. chaque code de refus de `decor.json` a son message, qui échappe ce que le fichier nomme, et chaque endroit qui refuse
 *    passe à ce message les mêmes paramètres ;
 * 2. `build()` refuse un envoi sans fichier, sans engagement ou au mauvais en-tête, et pose l'index d'un envoi conforme sans
 *    rien écrire, ce sur quoi « Valider » (`doPreview`) repose ;
 * 3. au-delà de `MapSetting::DECOR_UPLOAD_LIMIT` envois par jour, l'envoi est refusé à côté du champ du fichier ;
 * 4. un envoi crée son bâtiment, qui le montre et ne se modifie plus, n'en reçoit jamais un second, et part avec ses envois
 *    et leurs fichiers à sa suppression ;
 * 5. un refus de la création du bâtiment n'écrit ni le bâtiment ni l'envoi ;
 * 6. la case de partage pose la date de l'accord sur cet envoi seul, et ne refuse rien ;
 * 7. un salarié qui voit le plan lit l'envoi sans pouvoir l'écrire, ni un membre qui ne voit pas le plan ni un utilisateur
 *    hors de la structure ne le lisent, et un envoi sans fichier ne se lit plus ;
 * 8. ce que l'éditeur reçoit (`fillEditor()`) suit le drapeau, le poids et les triangles de `decor.json` ;
 * 9. l'emprise de `decor.json` couvre les bornes du bâtiment composé ;
 * 10. ni la démonstration ni la ferme école ne copient un envoi, dont le hash ouvrirait le fichier de la source ;
 * 11. la scène du plan reçoit le modèle en place à la place de la composition, salarié compris, et seulement celui que sa
 *     route servirait, sous la même forme à la lecture du plan et dans la réponse d'un envoi, et sait qu'il est importé ;
 * 12. la page refuse le drapeau fermé et le droit absent avant de juger l'envoi, sans prendre de place au débit.
 *
 * Décor : un bâtiment composé sur `FixtureAiLib::F1`, son administrateur connecté, dans une transaction annulée à la fin
 * de chaque méthode. Les modèles sont écrits par le test ; les fichiers que l'envoi pose dans le stockage sont effacés à
 * la fin.
 *
 * Lancement : dev/test package=map file=DecorUploadAi
 */
class DecorUploadAiTest extends \dev\ApiTest {

	/**
	 * Un bâtiment en deux boites, posé à y = 0 : le corps et sa vitre.
	 */
	const BUILDING = [
		'batiment' => [[-5, 0, -3], [5, 4, 3], [.6, .3, .2]],
		'vitre' => [[-1, 1, 3], [1, 2, 3.05], [.4, .6, .8]],
	];

	private array $files = [];
	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return new DecorBuildingModel();
	}

	public function setUp(): void {

		parent::setUp();

		\user\ConnectionLib::setOnline($this->getAdmin());

		$this->hashesInitial = $this->getHashes();

	}

	public function tearDown(): void {

		// Les médias écrits par l'envoi disparaissent avec la transaction, pas leurs fichiers : ils se lisent donc avant elle
		foreach(array_diff($this->getHashes(), $this->hashesInitial) as $hash) {
			$this->files[] = \storage\DriverLib::directory().'/'.new \media\MapDecorUploadUi()->getBasenameByHash($hash);
		}

		$this->clearThrottle();

		parent::tearDown();

		foreach($this->files as $file) {
			if(is_file($file)) {
				unlink($file);
			}
		}

	}

	public function testRefusalMessages(): void {

		$values = ['size' => 25480000, 'max' => 1048576, 'name' => '<b>x</b>', 'pieces' => 'batiment', 'piece' => 'batiment', 'count' => 4812, 'length' => 32, 'width' => 18];

		foreach(\scene\UploadLib::getRules(DecorUploadLib::PROFILE)['refusals'] as $refusal) {
			$this->assertTrue(AlertUi::getDecorUploadRefusal($refusal, $values) !== '', 'refus '.$refusal.' sans message');
		}

		$this->assertFalse(str_contains(AlertUi::getDecorUploadRefusal('piece', $values), '<b>x</b>'), 'nom de pièce non échappé');
		$this->assertFalse(str_contains(AlertUi::getDecorUploadRefusal('anchor', $values), '<b>x</b>'), 'nom d\'ancre non échappé');

		// Chaque endroit qui refuse passe à la closure du message les mêmes paramètres nommés : un oubli lève `Unknown named parameter`
		$fw = new \FailWatch();

		\Fail::log(DecorUpload::getRefusal('weight', ['size' => 25480000]));
		\Fail::log(DecorUpload::getRefusal('format'));
		\Fail::log(DecorUpload::getRefusal('bounds', ['length' => 32, 'width' => 18]));

		$messages = array_column($fw->format(), 1);

		$this->assertEquals(3, count(array_filter($messages, 'is_string')), 'refus sans message');

	}

	public function testBuild(): void {

		$eDecorBuilding = $this->createBuilding();

		$this->assertTrue($this->build($eDecorBuilding, NULL, '1')->has('DecorUpload::file.check'), 'envoi sans fichier accepté');
		$this->assertTrue($this->build($eDecorBuilding, $this->write($this->getGlb()), '')->has('DecorUpload::commitment.check'), 'envoi sans engagement accepté');
		$this->assertTrue($this->build($eDecorBuilding, $this->write('PK zip'), '1')->has('DecorUpload::file.conformity'), 'en-tête autre que glTF accepté');

		$this->clearThrottle();

		$eDecorUpload = new DecorUpload(['structure' => $eDecorBuilding['structure'], 'building' => $eDecorBuilding]);
		$fw = new \FailWatch();
		$eDecorUpload->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1']);

		if($this->assertTrue($fw->ok(), 'envoi aux règles refusé : '.json_encode($fw->format())) === FALSE) {
			return;
		}

		$this->assertTrue(is_array($eDecorUpload['bakedIndex']['models'][DecorUploadLib::FAMILY_BUILDING] ?? NULL), 'index de la conversion non posé sous la famille du bâtiment');
		$this->assertEquals(\scene\UploadLib::getRules(DecorUploadLib::PROFILE)['version'], $eDecorUpload['rules'], 'version des règles non posée');
		$this->assertEquals(0, DecorUpload::model()->whereBuilding($eDecorBuilding)->count(), 'envoi écrit par la seule conversion');

	}

	public function testThrottle(): void {

		if($this->assertDecorPresent(\Cache::isDisabled() === FALSE, 'le cache actif sur ce poste, qui compte les envois') === FALSE) {
			return;
		}

		$eDecorBuilding = $this->createBuilding();

		for($i = 0; $i < MapSetting::DECOR_UPLOAD_LIMIT; $i++) {
			\user\ThrottleAiLib::increment('decor-upload', (string)$this->getAdmin()['id'], MapSetting::DECOR_UPLOAD_WINDOW);
		}

		$fw = $this->build($eDecorBuilding, $this->write($this->getGlb()), '1');
		$this->assertTrue($fw->has('DecorUpload::file.throttled'), 'envoi au-delà du débit accepté');

		// L'écran ne refuse le fichier que sur un refus du champ `file` : le débit doit tomber à côté, sur `throttled`
		[[, $message, $wrapper]] = $fw->format();
		$this->assertEquals('throttled', $wrapper, 'le débit tombe sur le champ du fichier');
		$this->assertTrue($message !== NULL, 'le débit sans message');

		$this->clearThrottle();

		[[, , $wrapper]] = $this->build($eDecorBuilding, $this->write('pas un GLB'), '1')->format();
		$this->assertEquals('file', $wrapper, 'un refus du fichier tombe ailleurs que sur son champ');

	}

	/**
	 * Un node qui ne démarre pas est un défaut du serveur : l'envoi se dit indisponible, sur `throttled` comme le débit,
	 * pour que l'écran garde le fichier au lieu de le dire refusé.
	 */
	public function testNodeUnavailable(): void {

		$eDecorBuilding = $this->createBuilding();

		$fw = $this->buildUnderDeadNode(fn() => $this->build($eDecorBuilding, $this->write($this->getGlb()), '1'));
		$this->assertTrue($fw->has('DecorUpload::file.unavailable'), 'node qui ne démarre pas rendu comme un refus du fichier');

		[[, $message, $wrapper]] = $fw->format();
		$this->assertEquals('throttled', $wrapper, 'l\'import indisponible tombe sur le champ du fichier');
		$this->assertTrue($message !== NULL, 'l\'import indisponible sans message');

	}

	public function testLifecycle(): void {

		$eDecorUpload = $this->import();
		$eDecorBuilding = $eDecorUpload['building'];

		$this->assertTrue($eDecorBuilding->exists(), 'bâtiment non créé par l\'envoi');
		$this->assertEquals($eDecorUpload['id'], $this->getBuilding($eDecorBuilding)['upload']['id'], 'envoi non montré par le bâtiment');
		$this->assertEquals(\media\Media::ACTIVE, $this->getMediaStatus($eDecorUpload['hash']), 'média de l\'envoi inactif');

		$this->assertFalse($this->getBuilding($eDecorBuilding)->acceptUpdate(), 'bâtiment importé qui se modifie');
		$this->assertTrue($this->getBuilding($this->createBuilding())->acceptUpdate(), 'bâtiment composé qui ne se modifie plus');

		// Un envoi ne remplace pas le modèle d'un bâtiment : on supprime le bâtiment et on en importe un autre
		$eDecorUploadSecond = new DecorUpload(['structure' => $this->eStructure, 'building' => $this->getBuilding($eDecorBuilding)]);
		$fw = new \FailWatch();
		$eDecorUploadSecond->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1']);
		$fw->validate();

		try {
			DecorUploadLib::create($eDecorUploadSecond);
			$this->assertTrue(FALSE, 'second envoi accepté sur un bâtiment existant');
		} catch(\Exception $e) {
			$this->assertEquals('An upload only creates a new building', $e->getMessage(), 'second envoi refusé pour une autre raison');
		}

		$this->assertEquals(1, DecorUpload::model()->whereBuilding($eDecorBuilding)->count(), 'second envoi écrit sur un bâtiment existant');

		DecorBuildingLib::delete($this->getBuilding($eDecorBuilding));

		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eDecorUpload['hash']), 'fichier gardé à la suppression du bâtiment');
		$this->assertEquals(0, DecorUpload::model()->whereBuilding($eDecorBuilding)->count(), 'envois gardés après la suppression du bâtiment');

	}

	/**
	 * Un bâtiment neuf se crée avec son premier envoi, qui le montre aussitôt, et un refus de sa création n'écrit ni le
	 * bâtiment ni l'envoi.
	 */
	public function testCreateWithBuilding(): void {

		$eDecorBuilding = $this->buildBuilding(DecorBuilding::BUILDING, new Decor());

		$eDecorUpload = $this->create($eDecorBuilding);

		$this->assertTrue($eDecorBuilding->exists(), 'bâtiment neuf non créé par l\'envoi');
		$this->assertEquals($eDecorUpload['id'], $this->getBuilding($eDecorBuilding)['upload']['id'], 'bâtiment créé par l\'envoi sans le montrer');
		// La colonne JSON ne garde pas l'ordre des clés
		$settingsExpected = DecorBuildingLib::getChoices()[DecorBuilding::BUILDING]['initial']['settings'];
		$settingsRead = $this->getBuilding($eDecorBuilding)['settings'];
		ksort($settingsExpected);
		ksort($settingsRead);
		$this->assertEquals($settingsExpected, $settingsRead, 'bâtiment créé sans la composition qu\'il reprendra');

		// Un hangar qui n'est plus posé refuse la création du bâtiment, donc l'envoi
		$count = DecorUpload::model()->count();

		$eDecorBuildingRefused = $this->buildBuilding(DecorBuilding::HANGAR, new Decor(['id' => 999999999]));

		$this->clearThrottle();

		$eDecorUploadRefused = new DecorUpload(['structure' => $this->eStructure, 'building' => $eDecorBuildingRefused]);
		$fw = new \FailWatch();
		$eDecorUploadRefused->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1']);

		DecorUploadLib::create($eDecorUploadRefused);

		$this->assertTrue($fw->has('DecorBuilding::decorChanged'), 'bâtiment neuf créé sur un hangar retiré');
		$this->assertFalse($eDecorBuildingRefused->exists(), 'bâtiment neuf écrit malgré son refus');
		$this->assertEquals($count, DecorUpload::model()->count(), 'envoi écrit pour un bâtiment refusé');

	}

	/**
	 * La case de partage vaut une date posée par le Lib à l'heure de l'envoi, et pour ce fichier seul : l'envoi suivant
	 * sans la case part sans accord, et une valeur autre que la case cochée ne vaut pas accord.
	 */
	public function testShared(): void {

		$this->setClock('2026-10-24 10:00:00');

		$eDecorUploadShared = $this->import('1');
		$this->assertEquals('2026-10-24 10:00:00', $this->getSharedAt($eDecorUploadShared), 'accord coché sans date');

		$eDecorUploadOther = $this->import();
		$this->assertEquals(NULL, $this->getSharedAt($eDecorUploadOther), 'envoi sans la case qui reprend l\'accord du précédent');
		$this->assertEquals('2026-10-24 10:00:00', $this->getSharedAt($eDecorUploadShared), 'accord du premier envoi effacé par le suivant');

		$this->assertEquals(NULL, $this->getSharedAt($this->import('on')), 'valeur autre que la case cochée prise pour un accord');

		// Facultative, la case ne refuse aucun envoi
		$fw = new \FailWatch();
		new DecorUpload()->build(['commitment', 'shared'], ['commitment' => '1']);
		$this->assertTrue($fw->ok(), 'envoi sans accord de partage refusé');

	}

	private function getSharedAt(DecorUpload $eDecorUpload): ?string {

		return DecorUpload::model()
			->select('sharedAt')
			->whereId($eDecorUpload['id'])
			->getValue('sharedAt');

	}

	public function testRights(): void {

		$eDecorUpload = DecorUploadLib::getById($this->import()['id']);

		$this->assertTrue($eDecorUpload->canRead() and $eDecorUpload->acceptRead(), 'envoi illisible par qui gère la structure');
		$this->assertTrue($eDecorUpload->canWrite(), 'envoi que qui gère la structure ne peut pas écrire');

		\user\ConnectionLib::setOnline($this->getEmployee());
		$this->assertTrue($eDecorUpload->canRead(), 'envoi illisible par un salarié qui voit le plan');
		$this->assertFalse($eDecorUpload->canWrite(), 'envoi écrit par un salarié qui ne gère pas la structure');

		\user\ConnectionLib::setOnline($this->getAccountant());
		$this->assertFalse($eDecorUpload->canRead(), 'envoi lu par un membre qui ne voit pas le plan');

		\user\ConnectionLib::setOnline(new \user\User(['id' => $this->getAdmin()['id'] + 1000000]));
		$this->assertFalse($eDecorUpload->canRead(), 'envoi lu par un utilisateur hors de la structure');

		\user\ConnectionLib::setOnline($this->getAdmin());

		$eDecorUpload['hash'] = NULL;
		$this->assertFalse($eDecorUpload->acceptRead(), 'envoi sans fichier encore lisible');

		$eDecorUpload['hash'] = 'x';
		$eDecorUpload['status'] = DecorUpload::WITHDRAWN;
		$this->assertFalse($eDecorUpload->acceptRead(), 'envoi retiré encore lisible');

	}

	public function testEditor(): void {

		$enabled = MapSetting::$decorUploadEnabled;

		$eDecorBuilding = new DecorBuilding(['structure' => $this->eStructure]);

		MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => FALSE;
		DecorUploadLib::fillEditor($eDecorBuilding);

		$this->assertFalse($eDecorBuilding['uploadEnabled'], 'drapeau fermé qui ouvre l\'envoi');

		MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => TRUE;
		DecorUploadLib::fillEditor($eDecorBuilding);

		$rules = \scene\UploadLib::getModelRules(DecorUploadLib::PROFILE, DecorUploadLib::FAMILY_BUILDING);

		$this->assertTrue($eDecorBuilding['uploadEnabled'], 'drapeau ouvert qui ferme l\'envoi');
		$this->assertEquals(['maxBytes' => $rules['maxBytes'], 'maxTriangles' => $rules['maxTriangles']], $eDecorBuilding['uploadRules'], 'poids ou triangles du dépôt pris ailleurs que dans decor.json');

		MapSetting::$decorUploadEnabled = $enabled;

	}

	/**
	 * « Valider » et « Ajouter ce bâtiment » refusent le drapeau fermé et le droit absent avant de juger l'envoi : sans
	 * engagement, le refus attendu tombe quand même, et le débit ne compte rien. Un fichier ne s'envoie pas dans une page
	 * jouée en ligne de commande (`is_uploaded_file()`), d'où l'engagement comme témoin de l'ordre.
	 */
	public function testPageRefusals(): void {

		$enabled = MapSetting::$decorUploadEnabled;

		$this->clearThrottle();

		$input = ['structure' => (string)$this->eStructure['id'], 'type' => DecorBuilding::BUILDING, 'name' => 'Bâtiment du cas'];

		foreach(['map/decorUpload:doPreview', 'map/decorUpload:doCreate'] as $path) {

			MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => FALSE;
			$this->assertEquals(\NotExpectedAction::class, $this->executePage($path, $input), $path.' : drapeau fermé jugé après l\'envoi');

			MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => TRUE;

			\user\ConnectionLib::setOnline($this->getEmployee());
			\structure\MemberLib::clearCache();
			$this->assertEquals(\NotAllowedAction::class, $this->executePage($path, $input), $path.' : droit de créer jugé après l\'envoi');

			\user\ConnectionLib::setOnline($this->getAdmin());
			\structure\MemberLib::clearCache();
			$this->assertEquals(\FailAction::class, $this->executePage($path, $input), $path.' : envoi sans engagement accepté');

		}

		$this->assertEquals(0, \user\ThrottleAiLib::count('decor-upload', (string)$this->getAdmin()['id']), 'place du débit prise par un envoi refusé avant sa conversion');

		MapSetting::$decorUploadEnabled = $enabled;

	}

	/**
	 * L'emprise du modèle ne doit pas refuser ce que l'éditeur compose : elle couvre les bornes du bâtiment composé, toit et
	 * débord compris pour la hauteur, que `decor.json` ne recopie pas sans ce contrôle.
	 */
	public function testBounds(): void {

		$bounds = \scene\UploadLib::getModelRules(DecorUploadLib::PROFILE, DecorUploadLib::FAMILY_BUILDING)['bounds'];

		foreach(['length', 'width', 'height'] as $property) {

			[, $max] = DecorBuilding::model()->getPropertyRange($property);

			$this->assertTrue($bounds[$property] >= $max, 'emprise de decor.json plus étroite que la borne '.$property.' du bâtiment composé ('.$bounds[$property].' < '.$max.')');

		}

	}

	public function testCopies(): void {

		$this->assertTrue(in_array('map\DecorUpload', \structure\DemoLib::getModulesExclude(), TRUE), 'envoi copié par la démonstration');
		$this->assertEquals(['upload'], array_values(array_intersect(\structure\DemoLib::getPropertiesExclude()['map\DecorBuilding'] ?? [], ['upload'])), 'bâtiment copié par la démonstration avec l\'envoi qu\'il désigne');
		$this->assertTrue(in_array('map\DecorUpload', \structure\SchoolAiLib::MODULES_EXCLUDE, TRUE), 'envoi copié par la ferme école');

	}

	/**
	 * La scène monte ce que la rangée lui donne : un bâtiment qui montre un modèle doit le lui donner, sans quoi elle monte la
	 * composition sous un modèle téléversé. Elle ne reçoit pas un modèle dont la route refuserait le fichier.
	 */
	public function testScene(): void {

		$eDecorBuildingComposed = $this->createBuilding();

		$this->assertEquals(NULL, $this->getSceneUpload($eDecorBuildingComposed), 'modèle donné à la scène pour un bâtiment composé');
		$this->assertFalse($this->getSceneBuilding($eDecorBuildingComposed)['imported'], 'bâtiment composé dit importé');

		$eDecorUpload = DecorUploadLib::getById($this->import()['id']);
		$eDecorBuilding = $eDecorUpload['building'];

		$sceneUpload = ['url' => DecorUploadUi::url($eDecorUpload), 'index' => $eDecorUpload['bakedIndex']];

		$this->assertEquals($sceneUpload, $this->getSceneUpload($eDecorBuilding), 'modèle en place absent de la scène du plan');

		$this->assertTrue($this->getSceneBuilding($eDecorBuilding)['imported'], 'bâtiment importé dit composé');

		$this->assertEquals($this->getSceneUpload($eDecorBuilding), DecorBuildingLib::getScene($this->getBuilding($eDecorBuilding), $eDecorUpload)['upload'], 'réponse d\'un envoi qui ne donne pas à la scène le modèle de la lecture du plan');

		\user\ConnectionLib::setOnline($this->getEmployee());
		$this->assertEquals($sceneUpload, $this->getSceneUpload($eDecorBuilding), 'modèle refusé à la scène d\'un salarié qui voit le plan');

		\user\ConnectionLib::setOnline($this->getAccountant());
		$this->assertEquals(NULL, $this->getSceneUpload($eDecorBuilding), 'modèle donné à la scène d\'un membre qui ne voit pas le plan');

		\user\ConnectionLib::setOnline(new \user\User(['id' => $this->getAdmin()['id'] + 1000000]));
		$this->assertEquals(NULL, $this->getSceneUpload($eDecorBuilding), 'modèle donné à la scène d\'un utilisateur hors de la structure');

		\user\ConnectionLib::setOnline($this->getAdmin());

		$eDecorUpload['status'] = DecorUpload::WITHDRAWN;
		DecorUploadLib::update($eDecorUpload, ['status']);

		$this->assertEquals(NULL, $this->getSceneUpload($eDecorBuilding), 'modèle retiré donné à la scène');
		$this->assertTrue($this->getSceneBuilding($eDecorBuilding)['imported'], 'bâtiment importé dit composé quand son modèle est retiré');

	}

	// Le rôle de planning que le décor lui donne sur F1 (`FixtureAiLib::ROLE_PLANNING`) voit le plan sans gérer la structure
	private function getEmployee(): \user\User {
		return $this->getMember('decor.f1.planning@example.org');
	}

	// Le rôle de comptable que le décor lui donne sur F1 (`FixtureAiLib::ROLE_ACCOUNTANT`) n'a aucun droit sur la production,
	// donc pas le plan
	private function getAccountant(): \user\User {
		return $this->getMember('decor.f1.comptable@example.org');
	}

	private function getMember(string $email): \user\User {

		$eUser = \user\UserLib::getByEmail($email);

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		return $eUser;

	}

	private function getSceneUpload(DecorBuilding $eDecorBuilding): ?array {
		return $this->getSceneBuilding($eDecorBuilding)['upload'];
	}

	private function getSceneBuilding(DecorBuilding $eDecorBuilding): array {

		$buildings = array_column(DecorBuildingLib::getForScene($this->eStructure), NULL, 'id');

		return $buildings[$eDecorBuilding['id']];

	}

	private function createBuilding(): DecorBuilding {

		$eDecorBuilding = $this->buildBuilding(DecorBuilding::BUILDING, new Decor());

		DecorBuildingLib::create($eDecorBuilding);

		$eDecorBuilding['upload'] = new DecorUpload();

		return $eDecorBuilding;

	}

	// Un bâtiment neuf sous la composition de départ de son type, construit comme la page le construit, sans être écrit
	private function buildBuilding(string $type, Decor $eDecor): DecorBuilding {

		$initial = DecorBuildingLib::getChoices()[$type]['initial'];

		$eDecorBuilding = new DecorBuilding([
			'structure' => $this->eStructure,
			'eDecor' => $eDecor,
		]);

		$fw = new \FailWatch();

		$eDecorBuilding->build(DecorBuildingLib::getPropertiesCreate(), [
			'type' => $type,
			'name' => 'Bâtiment du cas',
			'length' => $initial['length'],
			'width' => $initial['width'],
			'height' => $initial['height'],
			'settings' => json_encode($initial['settings']),
		], new \PropertyList('create'));

		$fw->validate();

		return $eDecorBuilding;

	}

	private function getBuilding(DecorBuilding $eDecorBuilding): DecorBuilding {
		return DecorBuildingLib::getById($eDecorBuilding['id']);
	}

	// Un envoi qui crée un bâtiment neuf sous la composition de départ
	private function import(?string $shared = NULL): DecorUpload {
		return $this->create($this->buildBuilding(DecorBuilding::BUILDING, new Decor()), $shared);
	}

	// Un envoi qui crée le bâtiment construit par l'appelant
	private function create(DecorBuilding $eDecorBuilding, ?string $shared = NULL): DecorUpload {

		$this->clearThrottle();

		$eDecorUpload = new DecorUpload(['structure' => $eDecorBuilding['structure'], 'building' => $eDecorBuilding]);
		$fw = new \FailWatch();
		$eDecorUpload->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb()), 'commitment' => '1', 'shared' => $shared]);
		$fw->validate();

		DecorUploadLib::create($eDecorUpload);

		return $eDecorUpload;

	}

	private function build(DecorBuilding $eDecorBuilding, ?string $file, string $commitment): \FailWatch {

		$fw = new \FailWatch();

		new DecorUpload(['structure' => $eDecorBuilding['structure'], 'building' => $eDecorBuilding])->build(DecorUploadLib::getPropertiesCreate(), ['file' => $file, 'commitment' => $commitment]);

		return $fw;

	}

	/**
	 * La page réelle, sur une requête reconstruite : la classe de l'action levée dit la réponse. Même mécanique que
	 * `base/mail/test/CustomizeAi.t.php`, `executePage()`.
	 */
	private function executePage(string $path, array $input): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), new \stdClass());
			return 'aucune action';
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

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

	private function clearThrottle(): void {
		\user\ThrottleAiLib::clear('decor-upload', (string)$this->getAdmin()['id']);
	}

	private function getHashes(): array {

		return \media\Media::model()
			->whereType('map-decor-upload')
			->getColumn('hash');

	}

	private function getMediaStatus(string $hash): string {

		return \media\Media::model()
			->select('status')
			->whereType('map-decor-upload')
			->whereHash($hash)
			->getValue('status');

	}

	private function write(string $content): string {

		$file = tempnam(sys_get_temp_dir(), 'decor-upload');
		file_put_contents($file, $content);

		$this->files[] = $file;

		return $file;

	}

	/**
	 * Un glTF binaire : une boite par pièce, un nœud et un maillage indexé par pièce, nommés d'après la pièce, et une
	 * couleur de matériau chacune.
	 */
	private function getGlb(array $pieces = self::BUILDING): string {

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

			$indices = [0, 2, 1, 1, 2, 3, 4, 5, 6, 5, 7, 6, 0, 1, 4, 1, 5, 4, 2, 6, 3, 3, 6, 7, 0, 4, 2, 2, 4, 6, 1, 3, 5, 3, 7, 5];

			$positionOffset = strlen($bin);
			$bin .= pack('g*', ...$positions);
			$indexOffset = strlen($bin);
			$bin .= pack('v*', ...$indices);
			$bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);

			$json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => $positionOffset, 'byteLength' => count($positions) * 4];
			$json['bufferViews'][] = ['buffer' => 0, 'byteOffset' => $indexOffset, 'byteLength' => count($indices) * 2];
			$json['accessors'][] = ['bufferView' => 2 * $k, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC3', 'min' => $min, 'max' => $max];
			$json['accessors'][] = ['bufferView' => 2 * $k + 1, 'componentType' => 5123, 'count' => count($indices), 'type' => 'SCALAR'];
			$json['materials'][] = ['pbrMetallicRoughness' => ['baseColorFactor' => [...$color, 1]]];
			$json['meshes'][] = ['primitives' => [['attributes' => ['POSITION' => 2 * $k], 'indices' => 2 * $k + 1, 'material' => $k]]];
			$json['nodes'][] = ['name' => $name, 'mesh' => $k];
			$json['scenes'][0]['nodes'][] = $k;

			$k++;

		}

		$json['buffers'] = [['byteLength' => strlen($bin)]];

		$text = json_encode($json);
		$text .= str_repeat(' ', (4 - strlen($text) % 4) % 4);

		$body = pack('VV', strlen($text), 0x4e4f534a).$text.pack('VV', strlen($bin), 0x004e4942).$bin;

		return pack('VVV', 0x46546c67, 2, 12 + strlen($body)).$body;

	}

}
?>
