<?php
namespace plant;

/**
 * La durée de conservation d'une espèce (`Plant.storage`, lot r9 des paniers) : toujours renseignée
 * sur le référentiel partagé, NULL sur la copie d'une structure tant qu'elle suit sa référence.
 *
 * - la copie d'une structure naît à NULL, quelle que soit la durée de sa référence
 *   (`PlantLib::duplicateForStructure()`) ;
 * - la durée que suit une espèce sans réglage est celle de sa référence, et 0 pour une espèce propre
 *   (`PlantLib::getStorageDefault()`) ;
 * - la fiche de l'espèce la règle dans les deux branches de `getPropertiesUpdate()`, et l'API ne
 *   l'exige pas ;
 * - le schéma borne la valeur, et un champ vide remet la copie à NULL.
 *
 * Lancement : dev/test package=plant file=PlantStorageAi
 */
class PlantStorageAiTest extends PlantFixtureAiTest {

	public function testDuplicate(): void {

		// Une structure sans aucune espèce : l'identifiant suit le plus grand de la base, et la
		// transaction emporte les copies.
		$eStructure = new \structure\Structure([
			'id' => \structure\Structure::model()->select(['id' => new \Sql('MAX(id)', 'int')])->get()['id'] + 1000,
		]);

		$this->assertEquals(0, Plant::model()->whereStructure($eStructure)->count(), 'la structure n\'a encore aucune espèce');

		$shared = Plant::model()
			->select(['id', 'storage'])
			->whereStructure(NULL)
			->getCollection(index: 'id');

		$this->assertEquals(0, $shared->find(fn(Plant $ePlant) => $ePlant['storage'] === NULL)->count(), 'le référentiel partagé porte une durée sur chaque espèce');
		$this->assertTrue($shared->find(fn(Plant $ePlant) => $ePlant['storage'] > 0)->count() > 0, 'et des durées non nulles, que la copie ne doit pas reprendre');

		PlantLib::duplicateForStructure($eStructure);

		$cPlantCopy = Plant::model()
			->select(['reference', 'storage'])
			->whereStructure($eStructure)
			->getCollection();

		$this->assertEquals($shared->count(), $cPlantCopy->count(), 'une copie par espèce partagée');

		$copied = [];

		foreach($cPlantCopy as $ePlant) {

			if($ePlant['storage'] !== NULL) {
				$copied[] = $ePlant['reference']['id'].' : '.$ePlant['storage'];
			}

		}

		$this->assertEquals([], $copied, 'durées recopiées : '.implode(', ', $copied));

	}

	public function testDefault(): void {

		$ePlantShared = $this->getPlantGlobal();

		Plant::model()->update($ePlantShared, ['storage' => 7]);

		$ePlantCopy = new Plant([
			'name' => 'Copie du décor '.uniqid(),
			'structure' => $this->eStructure,
			'reference' => $ePlantShared,
			'cycle' => Plant::ANNUAL,
		]);

		PlantLib::create($ePlantCopy);

		$this->assertEquals(7, PlantLib::getStorageDefault(PlantLib::getById($ePlantCopy['id'])), 'une copie suit la durée de sa référence');
		$this->assertEquals(0, PlantLib::getStorageDefault(PlantLib::getById($this->getPlant()['id'])), 'une espèce propre, sans référence, ne se conserve pas');

	}

	/**
	 * Le libellé dit ce que la durée mesure, le temps qu'une récolte se garde (retour,
	 * lot r23c des paniers).
	 */
	public function testLabel(): void {

		$this->assertEquals('Durée de conservation après récolte', PlantUi::p('storage')->label);

	}

	public function testProperties(): void {

		$ePlantOwn = PlantLib::getById($this->getPlant()['id']);

		$ePlantCopy = new Plant([
			'name' => 'Copie du décor '.uniqid(),
			'structure' => $this->eStructure,
			'reference' => $this->getPlantGlobal(),
			'fqn' => 'copie-'.uniqid(),
			'cycle' => Plant::ANNUAL,
		]);

		PlantLib::create($ePlantCopy);

		$ePlantCopy = PlantLib::getById($ePlantCopy['id']);

		// La branche du propriétaire demande un membre qui gère la structure (`Plant::isOwner()`).
		$eMember = \structure\Member::model()
			->select(['user'])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->where('m1.structure', $this->eStructure)
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->get();

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		$this->assertTrue($ePlantOwn->isOwner(), 'une espèce propre, que la structure possède');
		$this->assertFalse($ePlantCopy->isOwner(), 'une copie du référentiel, qu\'elle ne possède pas');

		$this->assertTrue(in_array('storage', PlantLib::getPropertiesUpdate()($ePlantOwn), TRUE), 'la fiche règle la durée d\'une espèce propre');
		$this->assertTrue(in_array('storage', PlantLib::getPropertiesUpdate()($ePlantCopy), TRUE), 'et celle d\'une copie');
		$this->assertFalse(in_array('storage', PlantLib::getPropertiesUpdate('api')($ePlantOwn), TRUE), 'l\'API ne l\'exige pas');

		\user\ConnectionLib::setOnline($eUserInitial);

	}

	public function testBuild(): void {

		$ePlant = PlantLib::getById($this->getPlant()['id']);

		$fw = new \FailWatch();
		$ePlant->build(['storage'], ['storage' => '53']);
		$this->assertTrue($fw->has('Plant::storage.check'), 'au-delà de 52 semaines, refusée');

		$fw = new \FailWatch();
		$ePlant->build(['storage'], ['storage' => '12']);
		$this->assertTrue($fw->ok(), 'une durée dans les bornes');
		$this->assertEquals(12, $ePlant['storage']);

		$fw = new \FailWatch();
		$ePlant->build(['storage'], ['storage' => '']);
		$this->assertTrue($fw->ok(), 'un champ vide');
		$this->assertEquals(NULL, $ePlant['storage'], 'remet l\'espèce sur sa durée courante');

		$this->assertTrue(\plant\AlertUi::getError('Plant::storage.check') !== NULL, 'le refus a son message');

	}

}
?>
