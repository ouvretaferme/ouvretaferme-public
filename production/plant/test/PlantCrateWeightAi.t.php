<?php
namespace plant;

/**
 * Le poids d'une caisse de récolte, porté par l'espèce (`Plant.crateWeight`, chantier planning, lot L4).
 *
 * La règle est celle (01/10) : le poids se règle et se modifie par qui peut écrire l'espèce
 * (`Plant::canWrite()`, tout membre de la structure) ; un poids déjà réglé sert à tous, même sans ce
 * droit ; sans poids ni droit, la récolte ne se saisit pas en caisses.
 *
 * - `PlantLib::prepareCrateWeight()` pose le poids posté sous le droit, garde le poids enregistré quand
 *   rien n'est posté, et refuse quand il n'y a de poids ni posté ni enregistré ;
 * - sans le droit, il garde le poids enregistré, et refuse quand il n'y en a pas (`crateWeight.denied`) ;
 * - le schéma borne la valeur de 0,1 à 100 kg ;
 * - la fiche de l'espèce et l'API ne construisent jamais `crateWeight` : seule l'origine `crate` le rend,
 *   sinon chaque enregistrement de la fiche, qui ne le poste pas, l'effacerait.
 *
 * Le décor est la ferme F1 : l'espèce y est créée dans la transaction de la méthode.
 *
 * Lancement : dev/test package=plant file=PlantCrateWeightAi
 */
class PlantCrateWeightAiTest extends PlantFixtureAiTest {

	private \user\User $eUserInitial;

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	/** Connecte un membre actif de la structure du décor : il a `canWrite()` sur ses espèces. */
	private function connectMember(): void {

		$eMember = \structure\Member::model()
			->select(['user'])
			->whereStructure($this->eStructure)
			->whereStatus(\structure\Member::IN)
			->whereUser('!=', NULL)
			->get();

		$this->assertTrue($eMember->notEmpty(), 'la ferme du décor a un membre actif');

		\user\ConnectionLib::setOnline($eMember['user']);

	}

	/** Connecte un utilisateur qui n'est membre d'aucune structure : il n'a pas `canWrite()` sur l'espèce. */
	private function connectStranger(): void {

		\user\ConnectionLib::setOnline(new \user\User());

	}

	/** L'espèce relue comme la page la lit avant de préparer la récolte. */
	private function reload(Plant $ePlant): Plant {

		return PlantLib::getById($ePlant['id']);

	}

	public function testMemberSetsWeight(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());

		$this->assertEquals(NULL, $ePlant['crateWeight'], 'une espèce neuve n\'a pas de poids de caisse');
		$this->assertTrue($ePlant->assertWrite(), 'un membre écrit l\'espèce de sa structure');

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '6.5']);
		$this->assertTrue($fw->ok(), 'un poids dans les bornes : '.($fw->getLast() ?? ''));
		$this->assertEquals(6.5, $ePlant['crateWeight'], 'le poids posté est posé');

		PlantLib::update($ePlant, ['crateWeight']);

		$this->assertEquals(6.5, $this->reload($ePlant)['crateWeight'], 'le poids est écrit sur l\'espèce');

	}

	public function testMemberWithoutWeight(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, []);
		$this->assertTrue($fw->has('Plant::crateWeight.missing'), 'une saisie en caisses sans poids, ni posté ni enregistré, est refusée');

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '']);
		$this->assertTrue($fw->has('Plant::crateWeight.missing'), 'un champ vide aussi');

	}

	/** Un poids enregistré vaut tant qu'aucun autre n'est posté, y compris pour un membre qui pourrait le changer. */
	public function testMemberWithWeight(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());
		$ePlant['crateWeight'] = 8.0;
		PlantLib::update($ePlant, ['crateWeight']);

		foreach([[], ['crateWeight' => '']] as $input) {

			$ePlant = $this->reload($ePlant);

			$fw = new \FailWatch();
			PlantLib::prepareCrateWeight($ePlant, $input);
			$this->assertTrue($fw->ok(), 'le poids enregistré sert quand aucun n\'est posté : '.($fw->getLast() ?? ''));
			$this->assertEquals(8.0, $ePlant['crateWeight'], 'et reste celui de l\'espèce');

		}

	}

	public function testBounds(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());

		foreach(['0.05', '100.01', '-3'] as $weight) {

			$fw = new \FailWatch();
			PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => $weight]);
			$this->assertTrue($fw->has('Plant::crateWeight.check'), $weight.' kg est hors bornes');

		}

		foreach(['0.1', '100'] as $weight) {

			$fw = new \FailWatch();
			PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => $weight]);
			$this->assertTrue($fw->ok(), $weight.' kg est une borne admise');

		}

	}

	/**
	 * La virgule se lit (L4b) : le cast par défaut passerait « 6,5 » au `(float)` de PHP, qui rend 6 sans
	 * échec. L'étape `decimal` la lit comme un point, et refuse ce qui ne se lit pas comme un nombre.
	 */
	public function testComma(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '6,5']);
		$this->assertTrue($fw->ok(), '« 6,5 » est refusé : '.($fw->getLast() ?? ''));
		$this->assertEquals(6.5, $ePlant['crateWeight'], '« 6,5 » n\'est pas lu 6,5');

		$ePlant = $this->reload($this->getPlant());

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '1 000']);
		$this->assertTrue($fw->has('Plant::crateWeight.check'), '« 1 000 » se lit 1000, refusé par la borne du schéma');

		foreach(['6 kg', '6,5,1', ['6']] as $weight) {

			$ePlant = $this->reload($this->getPlant());

			$fw = new \FailWatch();
			PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => $weight]);
			$this->assertTrue($fw->has('Plant::crateWeight.decimal'), json_encode($weight).' n\'est pas refusé par l\'étape decimal');

		}

	}

	/**
	 * Le cas qui sait échouer : sans le droit et sans poids enregistré, la caisse est refusée, que le
	 * poids soit posté ou non. Retirer la garde `assertWrite()` de `prepareCrateWeight()` fait poser le
	 * poids posté, et ce cas rougit.
	 */
	public function testStrangerWithoutWeight(): void {

		$ePlant = $this->getPlant();

		$this->connectStranger();

		$ePlant = $this->reload($ePlant);

		$this->assertFalse($ePlant->assertWrite(), 'un utilisateur hors de la structure n\'écrit pas l\'espèce');

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '6']);
		$this->assertTrue($fw->has('Plant::crateWeight.denied'), 'un poids posté sans le droit est refusé');
		$this->assertEquals(NULL, $ePlant['crateWeight'], 'et n\'est pas posé sur l\'espèce');

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, []);
		$this->assertTrue($fw->has('Plant::crateWeight.denied'), 'sans poids posté non plus');

	}

	public function testStrangerWithWeight(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());
		$ePlant['crateWeight'] = 8.0;
		PlantLib::update($ePlant, ['crateWeight']);

		$this->connectStranger();

		$ePlant = $this->reload($ePlant);

		$fw = new \FailWatch();
		PlantLib::prepareCrateWeight($ePlant, ['crateWeight' => '3']);
		$this->assertTrue($fw->ok(), 'un poids déjà réglé sert sans le droit : '.($fw->getLast() ?? ''));
		$this->assertEquals(8.0, $ePlant['crateWeight'], 'le poids enregistré reste, le poids posté est ignoré');

	}

	public function testProperties(): void {

		$this->connectMember();

		$ePlant = $this->reload($this->getPlant());

		$this->assertEquals(['crateWeight'], PlantLib::getPropertiesUpdate('crate')($ePlant), 'l\'origine crate ne construit que le poids');
		$this->assertFalse(in_array('crateWeight', PlantLib::getPropertiesUpdate()($ePlant), TRUE), 'la fiche de l\'espèce ne le construit pas');
		$this->assertFalse(in_array('crateWeight', PlantLib::getPropertiesUpdate('api')($ePlant), TRUE), 'l\'API non plus');

		foreach(['Plant::crateWeight.check', 'Plant::crateWeight.missing', 'Plant::crateWeight.denied'] as $fail) {
			$this->assertTrue(AlertUi::getError($fail) !== NULL, $fail.' a son message');
		}

	}

}
?>
