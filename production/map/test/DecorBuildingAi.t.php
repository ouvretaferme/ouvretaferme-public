<?php
namespace map;

/**
 * Les bâtiments composés (`map\DecorBuilding`) et leur pose (`Decor.building`, kit `batiment`), par le chemin d'écriture
 * du produit : `build()` sur les listes du Lib, puis le Lib, comme `page/decorBuilding.p.php` et `page/decor.p.php`.
 *
 * Les cotes et les choix se postent comme l'éditeur les poste : une cote que le type n'a pas n'est pas dans le formulaire,
 * et les choix arrivent en une chaîne JSON. Les valeurs de départ sont celles de `choices.json` (`initial`), lues par
 * `DecorBuildingLib::getChoices()` : le cas ne recopie aucune borne ni aucun choix.
 *
 * Le décor du cas, une parcelle tracée, se pose par `ZoneLib::create()` dans la transaction de la méthode, sur la
 * structure `FixtureAiLib::F1` ; une seconde structure, `FixtureAiLib::F2`, porte le bâtiment et le hangar qu'une pose
 * de la première ne peut pas viser.
 *
 * Lancement : dev/test package=map file=DecorBuildingAi
 */
class DecorBuildingAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new DecorBuildingModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un décor portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	/** Une parcelle carrée de 100 m de côté autour de [3.1, 45.6]. */
	private function createZone(): void {

		$eZone = new Zone([
			'name' => 'Parcelle des bâtiments '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => [[3.0994, 45.5996], [3.1006, 45.5996], [3.1006, 45.6004], [3.0994, 45.6004], [3.0994, 45.5996]],
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce carré
		// fermé l'est, et le cas ne lit que ses sommets
		ZoneLib::create($eZone);

	}

	/** Ce que l'éditeur poste à l'ouverture pour un type : ses valeurs de départ, sans les cotes que le type n'a pas. */
	private function getInput(string $type): array {

		$initial = DecorBuildingLib::getChoices()[$type]['initial'];

		$input = [
			'type' => $type,
			'name' => 'Bâtiment du cas',
			'settings' => $initial['settings'],
		];

		foreach(['length', 'width', 'height'] as $property) {

			if($initial[$property] !== NULL) {
				$input[$property] = $initial[$property];
			}

		}

		return $input;

	}

	/** Le bâtiment tel que l'éditeur le poste, construit et enregistré comme le fait `doCreate`. */
	private function createBuilding(array $input, \FailWatch $fw, Decor $eDecor = new Decor(), \structure\Structure $eStructure = new \structure\Structure()): DecorBuilding {

		$eDecorBuilding = new DecorBuilding([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'eDecor' => $eDecor,
		]);

		$this->assertTrue($eDecorBuilding->acceptCreate());

		$input['settings'] = json_encode($input['settings']);

		$eDecorBuilding->build(DecorBuildingLib::getPropertiesCreate(), $input, new \PropertyList('create'));

		if($fw->ok()) {
			DecorBuildingLib::create($eDecorBuilding);
		}

		return $eDecorBuilding;

	}

	/** Le décor tel que le client le poste, construit et enregistré comme le fait `doCreate`. */
	private function createDecor(array $input, \FailWatch $fw): Decor {

		$eDecor = new Decor([
			'structure' => $this->eStructure,
			'center' => DecorLib::getCenter($this->eStructure)
		]);

		$eDecor->build(DecorLib::getPropertiesCreate(), $input + [
			'kit' => 'hangar',
			'angle' => 0,
			'size' => 1,
			'coordinates' => json_encode($eDecor['center'])
		], new \PropertyList('create'));

		if($fw->ok()) {
			DecorLib::create($eDecor);
		}

		return $eDecor;

	}

	/** Une structure du décor autre que celle du cas, pour ce qu'une pose de celle du cas ne peut pas viser. */
	private function getStructureOther(): \structure\Structure {

		return \structure\Structure::model()
			->select('id')
			->whereId(\structure\FixtureAiLib::F2)
			->get();

	}

	public function testCreateEachType(): void {

		foreach([DecorBuilding::BUILDING, DecorBuilding::HOUSE] as $type) {

			$fw = new \FailWatch();
			$eDecorBuilding = $this->createBuilding($this->getInput($type), $fw);

			$this->assertTrue($fw->ok(), $type.' '.json_encode($fw->get()));

			$scene = DecorBuildingLib::getScene(DecorBuildingLib::getById($eDecorBuilding['id'], DecorBuildingLib::SELECTION_SCENE));

			$this->assertEquals($type, $scene['type']);

			// Une colonne json de MySQL range ses clés à sa façon : seules les valeurs se comparent
			$expected = DecorBuildingLib::getChoices()[$type]['initial']['settings'];
			$settings = $scene['settings'];
			ksort($expected);
			ksort($settings);

			$this->assertEquals($expected, $settings);

		}

		$buildings = DecorBuildingLib::getForScene($this->eStructure);
		$this->assertEquals($eDecorBuilding['id'], end($buildings)['id'], 'la rangée suit l\'ordre de composition');

	}

	public function testHangarReplacesDecor(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor([], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorHangar = DecorLib::getHangar($this->eStructure, $eDecor['id']);
		$this->assertTrue($eDecorHangar->notEmpty());

		$eDecorBuilding = $this->createBuilding($this->getInput(DecorBuilding::HANGAR), $fw, $eDecorHangar);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorPosed = Decor::model()
			->select(['kit', 'building'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals('batiment', $eDecorPosed['kit'], 'le hangar posé passe au kit batiment');
		$this->assertEquals($eDecorBuilding['id'], $eDecorPosed['building']['id'], 'et pose le hangar personnalisé');

		$this->assertTrue(DecorLib::getHangar($this->eStructure, $eDecor['id'])->empty(), 'le hangar converti ne se personnalise plus une seconde fois');

		// Une seconde fenêtre qui enregistre le même hangar le trouve converti
		$fw = new \FailWatch();
		$this->createBuilding($this->getInput(DecorBuilding::HANGAR), $fw, $eDecorHangar);
		$this->assertTrue($fw->has('DecorBuilding::decorChanged'), json_encode($fw->get()));

	}

	/**
	 * Le hangar converti pèse le plafond d'un import au lieu de son modèle : sur un décor qui n'a plus la différence devant lui sous
	 * DECOR_TRIANGLES, la conversion est refusée, le hangar reste posé tel quel et aucun bâtiment n'entre dans la rangée.
	 */
	public function testHangarOverTrianglesStays(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor([], $fw);

		$gap = DecorLib::KIT_TRIANGLES['batiment'] - DecorLib::KIT_TRIANGLES['hangar'];

		// Des bosquets tant qu'il en reste la place au-dessus de l'écart, puis des arbres, plus fins que l'écart, jusqu'à passer dessous
		while(MapSetting::DECOR_TRIANGLES - DecorLib::getTriangles($this->eStructure) - DecorLib::KIT_TRIANGLES['bosquet'] >= $gap) {
			$this->createDecor(['kit' => 'bosquet'], $fw);
		}

		while(MapSetting::DECOR_TRIANGLES - DecorLib::getTriangles($this->eStructure) >= $gap) {
			$this->createDecor(['kit' => 'arbre'], $fw);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertTrue(MapSetting::DECOR_TRIANGLES - DecorLib::getTriangles($this->eStructure) < $gap, "le décor du cas laisse moins que l'écart du hangar converti");

		$buildings = DecorBuilding::model()->whereStructure($this->eStructure)->count();

		$this->createBuilding($this->getInput(DecorBuilding::HANGAR), $fw, DecorLib::getHangar($this->eStructure, $eDecor['id']));
		$this->assertTrue($fw->has('DecorBuilding::structure.triangles'), json_encode($fw->get()));

		$eDecorPosed = Decor::model()
			->select(['kit'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals('hangar', $eDecorPosed['kit'], 'le hangar refusé reste un hangar');
		$this->assertEquals($buildings, DecorBuilding::model()->whereStructure($this->eStructure)->count(), "aucun bâtiment n'entre dans la rangée");

	}

	public function testHangarNeedsDecor(): void {

		$fw = new \FailWatch();
		$this->createBuilding($this->getInput(DecorBuilding::HANGAR), $fw);
		$this->assertTrue($fw->has('DecorBuilding::type.decor'), json_encode($fw->get()));

		$this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor([], $fw);

		$fw = new \FailWatch();
		$this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw, DecorLib::getHangar($this->eStructure, $eDecor['id']));
		$this->assertTrue($fw->has('DecorBuilding::type.decor'), 'seul un hangar part d\'un hangar posé : '.json_encode($fw->get()));

	}

	public function testHangarOfAnotherStructure(): void {

		$eStructureOther = $this->getStructureOther();

		if($this->assertDecorPresent($eStructureOther->notEmpty(), 'la structure FixtureAiLib::F2') === FALSE) {
			return;
		}

		$this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor([], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->assertTrue(DecorLib::getHangar($eStructureOther, $eDecor['id'])->empty(), 'un hangar se relit borné par la structure de la page');

	}

	/**
	 * Une cote hors des bornes du type est refusée par l'étape `bounds` quand le schéma l'admet, par le schéma sinon ; une cote
	 * que le type n'a pas est refusée par l'étape `type`, et une cote due qui manque est refusée.
	 */
	public function testDimensions(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecorHangar = DecorLib::getHangar($this->eStructure, $this->createDecor([], $fw)['id']);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		foreach([DecorBuilding::BUILDING, DecorBuilding::HANGAR, DecorBuilding::HOUSE] as $type) {

			$choices = DecorBuildingLib::getChoices()[$type];
			$eDecor = ($type === DecorBuilding::HANGAR) ? $eDecorHangar : new Decor();

			foreach(['length', 'width', 'height'] as $property) {

				$input = $this->getInput($type);

				if($choices[$property] === NULL) {

					$input[$property] = 5;
					$expected = 'DecorBuilding::'.$property.'.type';

				} else {

					[$min, $max] = $choices[$property];
					[$schemaMin, $schemaMax] = DecorBuilding::model()->getPropertyRange($property);

					if($min - 0.5 >= $schemaMin) {
						$input[$property] = $min - 0.5;
						$expected = 'DecorBuilding::'.$property.'.bounds';
					} else if($max + 0.5 <= $schemaMax) {
						$input[$property] = $max + 0.5;
						$expected = 'DecorBuilding::'.$property.'.bounds';
					} else {
						$input[$property] = $max + 0.5;
						$expected = 'DecorBuilding::'.$property.'.check';
					}

				}

				$fw = new \FailWatch();
				$this->createBuilding($input, $fw, $eDecor);
				$this->assertTrue($fw->has($expected), $type.' '.$property.' '.json_encode(array_column($fw->get(), 1)));

				if($choices[$property] !== NULL) {

					$input = $this->getInput($type);
					unset($input[$property]);

					$fw = new \FailWatch();
					$this->createBuilding($input, $fw, $eDecor);
					$this->assertTrue($fw->ko(), $type.' '.$property.' absente');

				}

			}

		}

	}

	public function testSettings(): void {

		$input = $this->getInput(DecorBuilding::BUILDING);

		$cases = [
			'un choix inconnu' => ['roof' => 'coupole'] + $input['settings'],
			'une teinte inconnue' => ['coverTint' => 'violet'] + $input['settings'],
			'un choix d\'un autre type' => ['levels' => 2] + $input['settings'],
			'un choix manquant' => array_diff_key($input['settings'], ['door' => TRUE]),
			'une liste' => array_values($input['settings']),
			'un nombre en chaîne' => ['windows' => '2'] + $input['settings'],
		];

		foreach($cases as $case => $settings) {

			$fw = new \FailWatch();
			$this->createBuilding(['settings' => $settings] + $input, $fw);
			$this->assertTrue($fw->has('DecorBuilding::settings.check') or $fw->has('DecorBuilding::settings.choices'), $case.' '.json_encode($fw->get()));

		}

		$fw = new \FailWatch();
		$eDecorBuilding = new DecorBuilding(['structure' => $this->eStructure, 'eDecor' => new Decor()]);
		$eDecorBuilding->build(DecorBuildingLib::getPropertiesCreate(), ['settings' => '{"roof"'] + $input, new \PropertyList('create'));
		$this->assertTrue($fw->has('DecorBuilding::settings.check'), 'un JSON invalide : '.json_encode($fw->get()));

	}

	/**
	 * `isExcluded()` lit chaque clé d'une règle sans garde : l'étape `choices`, qui passe avant, n'admet que les clés du
	 * type, donc une règle qui en citerait une autre lirait une clé absente.
	 */
	public function testExcludedKeys(): void {

		foreach(DecorBuildingLib::getChoices() as $type => $choices) {

			$keys = array_keys($choices['settings']);

			foreach($choices['excluded'] as $rule) {
				$this->assertEquals([], array_values(array_diff(array_keys($rule), $keys)), 'clé d\'une règle d\'exclusion hors des choix de '.$type);
			}

		}

	}

	public function testExcluded(): void {

		$input = $this->getInput(DecorBuilding::HOUSE);
		$rule = DecorBuildingLib::getChoices()[DecorBuilding::HOUSE]['excluded'][0];

		$this->assertTrue(DecorBuildingLib::isExcluded(DecorBuilding::HOUSE, $rule + $input['settings']));
		$this->assertFalse(DecorBuildingLib::isExcluded(DecorBuilding::HOUSE, $input['settings']), 'les choix de départ se composent');

		$fw = new \FailWatch();
		$this->createBuilding(['settings' => $rule + $input['settings']] + $input, $fw);
		$this->assertTrue($fw->has('DecorBuilding::settings.excluded'), json_encode($fw->get()));

	}

	public function testUpdateKeepsType(): void {

		$fw = new \FailWatch();
		$eDecorBuilding = $this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->assertFalse(in_array('type', DecorBuildingLib::getPropertiesUpdate(), TRUE), 'le type ne change pas');

		$eDecorBuilding = DecorBuildingLib::getById($eDecorBuilding['id']);
		$length = (float)DecorBuildingLib::getChoices()[DecorBuilding::BUILDING]['length'][0];

		$eDecorBuilding->build(DecorBuildingLib::getPropertiesUpdate(), ['length' => $length, 'settings' => json_encode($eDecorBuilding['settings'])] + $eDecorBuilding->extracts(['name', 'width', 'height']), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorBuildingLib::update($eDecorBuilding, DecorBuildingLib::getPropertiesUpdate());

		$this->assertEquals($length, DecorBuildingLib::getById($eDecorBuilding['id'])['length']);

	}

	public function testLimit(): void {

		$fw = new \FailWatch();

		$count = DecorBuilding::model()->whereStructure($this->eStructure)->count();

		for($i = $count; $i < MapSetting::DECOR_BUILDING_MAX; $i++) {
			$this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw);
		$this->assertTrue($fw->has('DecorBuilding::structure.limit'), json_encode($fw->get()));

	}

	public function testPoseAndDelete(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecorBuilding = $this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecor = $this->createDecor(['kit' => 'batiment', 'building' => $eDecorBuilding['id']], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorBuilding = DecorBuildingLib::getById($eDecorBuilding['id']);

		// La poubelle de la rangée supprime un bâtiment posé, et ses poses avec lui
		DecorBuildingLib::delete($eDecorBuilding);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertTrue(DecorBuildingLib::getById($eDecorBuilding['id'])->empty(), 'bâtiment resté dans la rangée');
		$this->assertTrue(DecorLib::getById($eDecor['id'])->empty(), 'pose gardée après la suppression de son bâtiment');

	}

	public function testPoseKitAndBuilding(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$eDecorBuilding = $this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'batiment'], $fw);
		$this->assertTrue($fw->has('Decor::kit.building'), 'un batiment sans bâtiment : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['building' => $eDecorBuilding['id']], $fw);
		$this->assertTrue($fw->has('Decor::kit.building'), 'un hangar du catalogue qui cite un bâtiment : '.json_encode($fw->get()));

	}

	public function testPoseBuildingOfAnotherStructure(): void {

		$eStructureOther = $this->getStructureOther();

		if($this->assertDecorPresent($eStructureOther->notEmpty(), 'la structure FixtureAiLib::F2') === FALSE) {
			return;
		}

		$this->createZone();

		$fw = new \FailWatch();
		$eDecorBuildingOther = $this->createBuilding($this->getInput(DecorBuilding::BUILDING), $fw, eStructure: $eStructureOther);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createDecor(['kit' => 'batiment', 'building' => $eDecorBuildingOther['id']], $fw);
		$this->assertTrue($fw->has('Decor::building.owned'), json_encode($fw->get()));

	}

}
?>
