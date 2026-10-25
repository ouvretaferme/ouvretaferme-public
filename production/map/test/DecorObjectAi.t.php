<?php
namespace map;

/**
 * Les éléments qu'une structure importe dans ses rangées Eau, Nature et Matériel (`DecorObject`, `DecorObjectLib`,
 * `DecorUploadLib::createByObject()`), par le chemin d'écriture du produit : `build()` sur la liste du Lib, puis le Lib.
 *
 * Ce qui s'y prouve :
 * 1. un envoi porte exactement un de `building` ou `object`, jamais les deux ni aucun, quel que soit le chemin qui le crée,
 *    et un élément existant ne reçoit pas de second envoi ;
 * 2. la conversion juge le fichier sous les règles de la famille de l'élément (`decor.json`) et range son modèle sous elle ;
 * 2 bis. l'emprise de sa famille (`decor.json`) borne un élément importé, à la cote près sur chaque axe, et les modèles
 *    d'exemple du skill y tiennent ; les kits du catalogue n'y sont pas soumis, la règle ne juge que ce qui s'importe ;
 * 3. au-delà de `MapSetting::DECOR_OBJECT_MAX` éléments, la création est refusée, sans élément ni envoi écrits ;
 * 4. une pose cite un élément de la structure sous le kit de sa rangée (`DecorObject::KITS`), et seulement lui ;
 * 5. la suppression d'un élément emporte ses poses, ses envois et leurs fichiers ;
 * 6. le plan de la scène reçoit l'élément, son modèle et ses poses ;
 * 7. la page (`decorObject.p.php`) refuse le drapeau fermé, le droit absent, une famille hors de la liste et un nom vide
 *    avant de juger l'envoi, sans prendre de place au débit, et ne supprime l'élément que pour qui gère sa structure ; le
 *    fichier de l'élément ne se sert qu'à elle, et plus après la suppression.
 *
 * Décor : `FixtureAiLib::F1`, son administrateur connecté, dans une transaction annulée à la fin de chaque méthode. Les
 * modèles sont écrits par le test ; les fichiers que l'envoi pose dans le stockage sont effacés à la fin.
 *
 * Lancement : dev/test package=map file=DecorObjectAi
 */
class DecorObjectAiTest extends \dev\ApiTest {

	/**
	 * Un modèle par famille, en boites posées à y = 0, sous les pièces que `decor.json` attend de chacune.
	 */
	const MODELS = [
		DecorObject::WATER => [
			'objet' => [[-1, 0, -1], [1, .8, 1], [.3, .5, .8]],
		],
		DecorObject::TREE => [
			'arbre' => [[-.2, 0, -.2], [.2, 3, .2], [.4, .3, .2]],
			'feuillage' => [[-2, 3, -2], [2, 6, 2], [.2, .6, .2]],
		],
		DecorObject::OTHER => [
			'objet' => [[-1.5, 0, -1], [1.5, 1.2, 1], [.7, .2, .1]],
		],
	];

	private array $files = [];
	private array $hashesInitial = [];

	protected function getTransactionModel(): \ModuleModel {
		return new DecorObjectModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Un décor porte son auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
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

	public function testInvariant(): void {

		$eDecorUpload = $this->import(DecorObject::WATER);

		$row = DecorUpload::model()
			->select('building', 'object')
			->whereId($eDecorUpload)
			->get();

		$this->assertTrue($row['building']->empty(), 'envoi d\'un élément qui cite un bâtiment');
		$this->assertEquals($eDecorUpload['object']['id'], $row['object']['id'], 'envoi d\'un élément qui ne le cite pas');

		$this->assertEquals(0, DecorUpload::model()->whereStructure($this->eStructure)->whereBuilding(NULL)->whereObject(NULL)->count(), 'envoi qui ne cite ni bâtiment ni élément');
		$this->assertEquals(0, DecorUpload::model()->whereStructure($this->eStructure)->whereBuilding('!=', NULL)->whereObject('!=', NULL)->count(), 'envoi qui cite un bâtiment et un élément');

		// Un envoi ne remplace pas le modèle d'un élément : on le supprime et on en importe un autre
		$eDecorUploadSecond = new DecorUpload(['structure' => $this->eStructure, 'building' => new DecorBuilding(), 'object' => DecorObjectLib::getById($eDecorUpload['object']['id'])]);
		$fw = new \FailWatch();
		$eDecorUploadSecond->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb(self::MODELS[DecorObject::WATER])), 'commitment' => '1']);
		$fw->validate();

		try {
			DecorUploadLib::createByObject($eDecorUploadSecond);
			$this->assertTrue(FALSE, 'second envoi accepté sur un élément existant');
		} catch(\Exception $e) {
			$this->assertEquals('An upload only creates a new object', $e->getMessage(), 'second envoi refusé pour une autre raison');
		}

		$this->assertEquals(1, DecorUpload::model()->whereObject($eDecorUpload['object'])->count(), 'second envoi écrit sur un élément existant');

	}

	public function testFamilies(): void {

		foreach(self::MODELS as $family => $pieces) {

			$this->clearThrottle();

			$eDecorUpload = $this->buildUpload($family, $pieces, $fw);

			if($this->assertTrue($fw->ok(), $family.' : modèle aux règles refusé : '.json_encode($fw->format())) === FALSE) {
				continue;
			}

			$this->assertTrue(is_array($eDecorUpload['bakedIndex']['models'][$family] ?? NULL), $family.' : modèle non rangé sous sa famille');

		}

		// La pièce `objet` d'une rangée Eau ou Matériel est obligatoire
		$this->clearThrottle();
		$this->buildUpload(DecorObject::WATER, ['caisse' => self::MODELS[DecorObject::WATER]['objet']], $fw);
		$this->assertTrue($fw->has('DecorUpload::file.conformity'), 'élément d\'Eau sans pièce objet accepté');

		// Un arbre de 8 m tient dans l'emprise de Nature, pas dans celle de Matériel (`decor.json`)
		$this->clearThrottle();
		$this->buildUpload(DecorObject::OTHER, ['objet' => [[-1, 0, -1], [1, 8, 1], [.7, .2, .1]]], $fw);
		$this->assertTrue($fw->has('DecorUpload::file.conformity'), 'élément de Matériel au-delà de sa hauteur accepté');

	}

	/**
	 * Un modèle à l'emprise exacte de sa famille passe, et un modèle qui la dépasse de 10 cm sur un seul axe est refusé : la
	 * longueur se mesure en z, la largeur en x, de part et d'autre de l'origine, la hauteur en y depuis le sol. Les modèles
	 * d'exemple que le skill remet passent sous leur famille.
	 */
	public function testBounds(): void {

		$samples = [DecorObject::WATER => 'conforme-eau.glb', DecorObject::TREE => 'conforme-nature.glb', DecorObject::OTHER => 'conforme-materiel.glb'];

		foreach(array_keys(self::MODELS) as $family) {

			$bounds = \scene\UploadLib::getModelRules(DecorUploadLib::PROFILE, $family)['bounds'];

			$this->clearThrottle();
			$this->buildUpload($family, $this->getBox($family, $bounds), $fw);
			$this->assertTrue($fw->ok(), $family.' : modèle à l\'emprise exacte de sa famille refusé : '.json_encode($fw->format()));

			foreach(['length', 'width', 'height'] as $property) {

				$this->clearThrottle();
				$this->buildUpload($family, $this->getBox($family, [$property => $bounds[$property] + .2] + $bounds), $fw);
				$this->assertTrue($fw->has('DecorUpload::file.conformity'), $family.' : modèle au-delà de sa '.$property.' accepté');

			}

			$result = \scene\UploadLib::run(DecorUploadLib::PROFILE, LIME_DIRECTORY.'/base/scene/upload/decor/exemples/'.$samples[$family], $family);
			$this->assertEquals(NULL, $result['refusal'] ?? NULL, $family.' : modèle d\'exemple du skill refusé sous sa famille');

		}

	}

	public function testLimit(): void {

		$existing = DecorObject::model()->whereStructure($this->eStructure)->count();

		for($count = $existing; $count < MapSetting::DECOR_OBJECT_MAX; $count++) {

			$eDecorObject = $this->buildObject(DecorObject::OTHER);

			$fw = new \FailWatch();
			DecorObjectLib::create($eDecorObject);
			$fw->validate();

		}

		$uploads = DecorUpload::model()->count();

		$this->clearThrottle();

		$eDecorUpload = $this->buildUpload(DecorObject::OTHER, self::MODELS[DecorObject::OTHER], $fw);
		$fw->validate();

		$fw = new \FailWatch();
		DecorUploadLib::createByObject($eDecorUpload);

		$this->assertTrue($fw->has('DecorObject::structure.limit'), 'élément créé au-delà de DECOR_OBJECT_MAX');
		$this->assertFalse($eDecorUpload['object']->exists(), 'élément écrit malgré son refus');
		$this->assertEquals($uploads, DecorUpload::model()->count(), 'envoi écrit pour un élément refusé');
		$this->assertEquals(MapSetting::DECOR_OBJECT_MAX, DecorObject::model()->whereStructure($this->eStructure)->count(), 'nombre d\'éléments changé par un refus');

	}

	public function testKit(): void {

		$eDecorObject = $this->import(DecorObject::TREE)['object'];

		$this->assertTrue($this->buildPose('objet-nature', $eDecorObject)->ok(), 'pose d\'un élément de Nature sous son kit refusée');
		$this->assertTrue($this->buildPose('objet-eau', $eDecorObject)->has('Decor::kit.object'), 'élément de Nature posé sous le kit d\'Eau');
		$this->assertTrue($this->buildPose('objet-nature', new DecorObject())->has('Decor::kit.object'), 'kit d\'élément posé sans élément');
		$this->assertTrue($this->buildPose('arbre', $eDecorObject)->has('Decor::kit.object'), 'élément posé sous un kit du catalogue');

	}

	public function testDelete(): void {

		$eDecorUpload = $this->import(DecorObject::WATER);
		$eDecorObject = DecorObjectLib::getById($eDecorUpload['object']['id']);

		$eDecor = $this->createPose('objet-eau', $eDecorObject);

		$this->assertTrue(Decor::model()->whereId($eDecor)->exists(), 'pose de l\'élément non écrite');

		DecorObjectLib::delete($eDecorObject);

		$this->assertFalse(DecorObject::model()->whereId($eDecorObject)->exists(), 'élément gardé à sa suppression');
		$this->assertFalse(Decor::model()->whereId($eDecor)->exists(), 'pose gardée à la suppression de l\'élément');
		$this->assertEquals(0, DecorUpload::model()->whereObject($eDecorObject)->count(), 'envois gardés à la suppression de l\'élément');
		$this->assertEquals(\media\Media::DELETED, $this->getMediaStatus($eDecorUpload['hash']), 'fichier gardé à la suppression de l\'élément');

	}

	public function testScene(): void {

		$eDecorUpload = $this->import(DecorObject::TREE);
		$eDecorObject = DecorObjectLib::getById($eDecorUpload['object']['id']);

		$eDecor = $this->createPose('objet-nature', $eDecorObject);

		$scene = SceneLib::getByStructure($this->eStructure, \Clock::year());

		$objects = array_column($scene['objects'], NULL, 'id');

		if($this->assertTrue(isset($objects[$eDecorObject['id']]), 'élément absent du plan') === FALSE) {
			return;
		}

		$object = $objects[$eDecorObject['id']];

		$this->assertEquals(DecorObject::TREE, $object['family'], 'famille de l\'élément perdue dans le plan');
		$this->assertEquals(DecorUploadLib::getScene(DecorUpload::model()->select(DecorUpload::getSelection())->whereId($eDecorUpload)->get()), $object['upload'], 'modèle de l\'élément différent dans le plan et dans la réponse de l\'envoi');

		$rows = array_column($scene['decor'], NULL, 'id');

		$this->assertEquals($eDecorObject['id'], $rows[$eDecor['id']]['object'] ?? NULL, 'pose de l\'élément sans l\'élément dans le plan');

	}

	/**
	 * Le panneau, « Valider » et « Ajouter cet élément » refusent le drapeau fermé et le droit absent avant de juger l'envoi,
	 * puis une famille hors de `DecorObject::KITS` avec le nom : sans engagement, le refus attendu tombe quand même, et le
	 * débit ne compte rien. Un fichier ne s'envoie pas dans une page jouée en ligne de commande (`is_uploaded_file()`), d'où
	 * l'engagement comme témoin de l'ordre ; le plafond, que le Lib juge après la conversion, s'éprouve dans `testLimit()`.
	 */
	public function testPageCreate(): void {

		$enabled = MapSetting::$decorUploadEnabled;

		$this->clearThrottle();

		$structure = (string)$this->eStructure['id'];

		$cases = [
			['map/decorObject:create', 'GET', ['structure' => $structure, 'family' => DecorObject::WATER]],
			['map/decorObject:doPreview', 'POST', ['structure' => $structure, 'family' => DecorObject::WATER, 'name' => 'Abreuvoir du cas']],
			['map/decorObject:doCreate', 'POST', ['structure' => $structure, 'family' => DecorObject::WATER, 'name' => 'Abreuvoir du cas']],
		];

		foreach($cases as [$path, $method, $input]) {

			MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => FALSE;
			$this->assertEquals(\NotExpectedAction::class, $this->executePage($path, $method, $input), $path.' : drapeau fermé accepté');

			MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => TRUE;

			\user\ConnectionLib::setOnline($this->getEmployee());
			\structure\MemberLib::clearCache();
			$this->assertEquals(\NotAllowedAction::class, $this->executePage($path, $method, $input), $path.' : élément créé par un salarié qui ne gère pas la structure');

			\user\ConnectionLib::setOnline($this->getOtherAdmin());
			\structure\MemberLib::clearCache();
			$this->assertEquals(\NotAllowedAction::class, $this->executePage($path, $method, $input), $path.' : élément créé par l\'administrateur d\'une autre structure');

			\user\ConnectionLib::setOnline($this->getAdmin());
			\structure\MemberLib::clearCache();
			$this->assertEquals(\FailAction::class, $this->executePage($path, $method, ['family' => 'building'] + $input), $path.' : famille hors des rangées d\'éléments acceptée');

		}

		$this->assertEquals(\FailAction::class, $this->executePage('map/decorObject:doCreate', 'POST', $cases[2][2]), 'élément ajouté sans engagement');
		$this->assertEquals(\FailAction::class, $this->executePage('map/decorObject:doPreview', 'POST', ['name' => ''] + $cases[1][2]), 'élément sans nom validé');
		$this->assertEquals(\ViewAction::class, $this->executePage('map/decorObject:create', 'GET', $cases[0][2]), 'panneau refusé à qui gère la structure');

		$this->assertEquals(0, \user\ThrottleAiLib::count('decor-upload', (string)$this->getAdmin()['id']), 'place du débit prise par un envoi refusé avant sa conversion');

		MapSetting::$decorUploadEnabled = $enabled;

	}

	/**
	 * La suppression relit l'élément par son id et valide `canDelete()` : ni un salarié de la structure, ni l'administrateur
	 * d'une autre ne suppriment l'élément, et seul qui gère sa structure le supprime, avec ses envois.
	 */
	public function testPageDelete(): void {

		$eDecorUpload = $this->import(DecorObject::OTHER);
		$eDecorObject = DecorObjectLib::getById($eDecorUpload['object']['id']);
		$input = ['id' => (string)$eDecorObject['id']];
		$model = ['id' => (string)$eDecorUpload['id']];

		$this->assertEquals(\DataAction::class, $this->executePage('map/decorUpload:model', 'GET', $model), 'fichier de l\'élément refusé à qui gère la structure');

		\user\ConnectionLib::setOnline($this->getEmployee());
		\structure\MemberLib::clearCache();
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('map/decorObject:doDelete', 'POST', $input), 'élément supprimé par un salarié qui ne gère pas la structure');

		\user\ConnectionLib::setOnline($this->getOtherAdmin());
		\structure\MemberLib::clearCache();
		$this->assertEquals(\NotAllowedAction::class, $this->executePage('map/decorObject:doDelete', 'POST', $input), 'élément supprimé par l\'administrateur d\'une autre structure');

		// L'administrateur de F2 peut voir le plan de F1 par un groupe qui partage la production : le fichier se refuse à un
		// membre qui ne voit pas le plan et à qui n'est pas dans la structure
		\user\ConnectionLib::setOnline($this->getMember('decor.f1.comptable@example.org'));
		\structure\MemberLib::clearCache();
		$this->assertEquals(\NotExistsAction::class, $this->executePage('map/decorUpload:model', 'GET', $model), 'fichier de l\'élément servi à un membre qui ne voit pas le plan');

		\user\ConnectionLib::setOnline(new \user\User(['id' => $this->getAdmin()['id'] + 1000000]));
		\structure\MemberLib::clearCache();
		$this->assertEquals(\NotExistsAction::class, $this->executePage('map/decorUpload:model', 'GET', $model), 'fichier de l\'élément servi hors de la structure');

		$this->assertTrue(DecorObject::model()->whereId($eDecorObject)->exists(), 'élément supprimé par un refus');

		\user\ConnectionLib::setOnline($this->getAdmin());
		\structure\MemberLib::clearCache();
		$this->assertEquals(\ViewAction::class, $this->executePage('map/decorObject:doDelete', 'POST', $input), 'suppression refusée à qui gère la structure');

		$this->assertFalse(DecorObject::model()->whereId($eDecorObject)->exists(), 'élément gardé par sa suppression');
		$this->assertEquals(0, DecorUpload::model()->whereObject($eDecorObject)->count(), 'envois gardés par la suppression de l\'élément');

		$this->assertEquals(\NotExistsAction::class, $this->executePage('map/decorObject:doDelete', 'POST', $input), 'élément supprimé une seconde fois');
		$this->assertEquals(\NotExistsAction::class, $this->executePage('map/decorUpload:model', 'GET', $model), 'fichier d\'un élément supprimé encore servi');

	}

	private function buildObject(string $family): DecorObject {

		$eDecorObject = new DecorObject(['structure' => $this->eStructure]);

		$fw = new \FailWatch();
		$eDecorObject->build(DecorObjectLib::getPropertiesCreate(), ['family' => $family, 'name' => 'Élément du cas'], new \PropertyList('create'));
		$fw->validate();

		return $eDecorObject;

	}

	// Un envoi construit comme la page le construit (`decorObject.p.php`), sans rien écrire
	private function buildUpload(string $family, array $pieces, ?\FailWatch &$fw): DecorUpload {

		$eDecorUpload = new DecorUpload([
			'structure' => $this->eStructure,
			'building' => new DecorBuilding(),
			'object' => $this->buildObject($family)
		]);

		$fw = new \FailWatch();
		$eDecorUpload->build(DecorUploadLib::getPropertiesCreate(), ['file' => $this->write($this->getGlb($pieces)), 'commitment' => '1']);

		return $eDecorUpload;

	}

	// Un élément neuf de la famille, créé avec son envoi
	private function import(string $family): DecorUpload {

		$this->clearThrottle();

		$eDecorUpload = $this->buildUpload($family, self::MODELS[$family], $fw);
		$fw->validate();

		$fw = new \FailWatch();
		DecorUploadLib::createByObject($eDecorUpload);
		$fw->validate();

		return $eDecorUpload;

	}

	// Les pièces de la famille dans une boite de `$bounds`, centrée sur l'origine et posée au sol ; l'arbre garde un tronc
	// étroit sous son feuillage
	private function getBox(string $family, array $bounds): array {

		$min = [-$bounds['width'] / 2, 0, -$bounds['length'] / 2];
		$max = [$bounds['width'] / 2, $bounds['height'], $bounds['length'] / 2];

		return match($family) {
			DecorObject::WATER, DecorObject::OTHER => ['objet' => [$min, $max, [.5, .5, .5]]],
			DecorObject::TREE => [
				'arbre' => [[-.2, 0, -.2], [.2, $bounds['height'] / 2, .2], [.4, .3, .2]],
				'feuillage' => [[$min[0], $bounds['height'] / 2, $min[2]], $max, [.2, .6, .2]],
			],
		};

	}

	private function buildPose(string $kit, DecorObject $eDecorObject): \FailWatch {

		$fw = new \FailWatch();

		$this->getPose()->build(DecorLib::getPropertiesCreate(), [
			'object' => $eDecorObject->empty() ? '' : $eDecorObject['id'],
			'kit' => $kit,
			'coordinates' => json_encode(DecorLib::getCenter($this->eStructure)),
			'angle' => 0,
			'size' => 1
		], new \PropertyList('create'));

		return $fw;

	}

	private function createPose(string $kit, DecorObject $eDecorObject): Decor {

		$eDecor = $this->getPose();

		$fw = new \FailWatch();

		$eDecor->build(DecorLib::getPropertiesCreate(), [
			'object' => $eDecorObject['id'],
			'kit' => $kit,
			'coordinates' => json_encode(DecorLib::getCenter($this->eStructure)),
			'angle' => 0,
			'size' => 1
		], new \PropertyList('create'));

		$fw->validate();

		DecorLib::create($eDecor);

		return $eDecor;

	}

	// Une pose se juge près du centre des parcelles dessinées (`Decor.coordinates.near`) : le cas en dessine une s'il n'y en a pas
	private function getPose(): Decor {

		if(DecorLib::getCenter($this->eStructure) === NULL) {

			$eZone = new Zone([
				'name' => 'Parcelle des éléments importés '.uniqid(),
				'structure' => $this->eStructure,
				'area' => 10000,
				'coordinates' => [[3.0994, 45.5996], [3.1006, 45.5996], [3.1006, 45.6004], [3.0994, 45.6004], [3.0994, 45.5996]],
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce carré fermé l'est, et le cas
			// ne lit que ses sommets
			ZoneLib::create($eZone);

		}

		return new Decor([
			'structure' => $this->eStructure,
			'center' => DecorLib::getCenter($this->eStructure)
		]);

	}

	private function getEmployee(): \user\User {
		return $this->getMember('decor.f1.planning@example.org');
	}

	// L'administrateur de F2, qui n'a aucun rôle sur F1
	private function getOtherAdmin(): \user\User {
		return \user\UserLib::getById((\api\ApiSetting::$devAccessTokenUser)(\structure\FixtureAiLib::F2)['id']);
	}

	private function getMember(string $email): \user\User {

		$eUser = \user\UserLib::getByEmail($email);

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		return $eUser;

	}

	// La page jouée comme une requête, qui rend la classe de l'action qui la termine
	private function executePage(string $path, string $method, array $input): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = $method === 'GET' ? [$input, [], $input] : [[], $input, $input];

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

		$file = tempnam(sys_get_temp_dir(), 'decor-object');
		file_put_contents($file, $content);

		$this->files[] = $file;

		return $file;

	}

	/**
	 * Un glTF binaire : une boite par pièce, un nœud et un maillage indexé par pièce, nommés d'après la pièce, et une
	 * couleur de matériau chacune. Le même que celui de `DecorUploadAi`.
	 */
	private function getGlb(array $pieces): string {

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
