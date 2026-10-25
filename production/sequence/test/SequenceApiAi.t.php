<?php
namespace sequence;

/**
 * Test bout-en-bout des trois ressources de l'itinéraire technique : sequence\Sequence,
 * sequence\Crop et sequence\Flow (production/sequence/api/).
 *
 * Le cas central est la création d'un itinéraire, qui n'est pas un create ordinaire : les espèces
 * s'envoient avec lui sous `plantsList`, et SequenceLib::create() en fait autant de cultures.
 *
 * L'itinéraire, ses espèces et ses interventions se posent par le décor de `series`
 * (`\series\SeriesFixtureAiTest`), dans la transaction de la méthode. Les témoins d'une autre
 * structure se lisent dans la base : le cas ne demande qu'une ligne que la borne de structure doit
 * écarter.
 *
 * Lancement : dev/test package=sequence file=SequenceApiAi
 */
class SequenceApiAiTest extends \series\SeriesFixtureAiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new SequenceModel();
	}

	/**
	 * Un itinéraire du décor qui porte deux espèces, et sur chacune une intervention et une part
	 * variétale : la liste des cultures ne vérifie l'identifiant des parts que s'il y en a. Il est relu
	 * par la sélection que la ressource expose, le compteur `plants` étant celui que
	 * `SequenceLib::create()` a posé.
	 */
	private function getSequenceComplete(): Sequence {

		return $this->once('sequenceComplete', function() {

			$eSequence = $this->createSequence();

			foreach($this->getCrops($eSequence) as $eCrop) {
				$this->createFlow($eSequence, $eCrop);
				$this->createSliceCrop($eCrop, $this->createVariety($eCrop['plant']));
			}

			return Sequence::model()
				->select(Sequence::getSelection())
				->whereId($eSequence['id'])
				->get();

		});

	}

	private function getPlants(int $count): \Collection {

		return \plant\Plant::model()
			->select(['id'])
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_DESC])
			->getCollection(0, $count);

	}

	public function testList(): void {

		$this->getSequenceComplete();

		$response = $this->callApi('GET', 'sequenceList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0, 'la structure de référence a des itinéraires');

	}

	public function testRead(): void {

		$eSequence = $this->getSequenceComplete();

		$response = $this->callApi('GET', 'sequence/{id}', get: ['id' => $eSequence['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eSequence['name'], $response['body']['name'] ?? NULL);
		$this->assertEquals($eSequence['plants'], $response['body']['plants'] ?? NULL);
		$this->assertFalse(array_key_exists('createdAt', $response['body']));
		$this->assertFalse(array_key_exists('author', $response['body']));

	}

	/** Un itinéraire d'une autre structure ne se lit pas, fût-il public. */
	public function testReadIsScopedToStructure(): void {

		$eSequenceOther = Sequence::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($eSequenceOther->notEmpty(), 'une autre structure a des itinéraires');

		$response = $this->callApi('GET', 'sequence/{id}', get: ['id' => $eSequenceOther['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	/** Les espèces envoyées avec l'itinéraire deviennent ses cultures, et le compteur les compte. */
	public function testCreate(): void {

		$cPlant = $this->getPlants(2);

		$this->assertEquals(2, $cPlant->count(), 'la structure a au moins deux espèces');

		$create = $this->callApi('POST', 'sequence', body: [
			'name' => 'Itinéraire de test API '.uniqid(),
			'cycle' => Sequence::ANNUAL,
			'perennialLifetime' => NULL,
			'description' => NULL,
			'use' => Sequence::BED,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'plantsList' => $cPlant->getIds()
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals(2, $create['body']['plants'] ?? NULL, 'le compteur suit les espèces envoyées');

		$id = $create['body']['id'];

		$crops = $this->callApi('GET', 'sequence/{id}/cropsList', get: ['id' => $id]);

		$this->assertEquals(200, $crops['status']);
		$this->assertEquals(2, $crops['body']['count'], 'une culture par espèce');

		// Les cultures sortent triées par nom d'espèce (CropLib::sortByPlant()), pas dans l'ordre envoyé
		$plants = array_column($crops['body']['list'], 'plant');
		sort($plants);

		$expected = $cPlant->getIds();
		sort($expected);

		$this->assertEquals($expected, $plants, 'les cultures portent les espèces envoyées');

	}

	/**
	 * Une espèce d'une autre structure ne fonde pas un itinéraire. Le contrôle est ici et non dans
	 * `plantsList.check`, dont le canRead() ne peut rien dire sans membre connecté.
	 */
	public function testCreateRejectsForeignPlant(): void {

		$ePlantOther = \plant\Plant::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->where('structure IS NOT NULL')
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($ePlantOther->notEmpty(), 'une autre structure a des espèces');

		$response = $this->callApi('POST', 'sequence', body: [
			'name' => 'Itinéraire à espèce étrangère',
			'cycle' => Sequence::ANNUAL,
			'perennialLifetime' => NULL,
			'description' => NULL,
			'use' => Sequence::BED,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'plantsList' => [$ePlantOther['id']]
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_plant', $response['body'][0]['code'] ?? NULL);

	}

	/** Un itinéraire sans espèce n'a rien à dérouler : la construction le refuse. */
	public function testCreateRejectsWithoutPlants(): void {

		$response = $this->callApi('POST', 'sequence', body: [
			'name' => 'Itinéraire sans espèce',
			'cycle' => Sequence::ANNUAL,
			'perennialLifetime' => NULL,
			'description' => NULL,
			'use' => Sequence::BED,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'plantsList' => []
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testUpdate(): void {

		$eSequence = $this->getSequenceComplete();

		$name = 'Itinéraire modifié '.uniqid();

		$response = $this->callApi('PUT', 'sequence/{id}', body: [
			'properties' => ['name'],
			'name' => $name
		], get: ['id' => $eSequence['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($name, $response['body']['name'] ?? NULL);

	}

	/** Les cultures d'un itinéraire, avec la répartition de leurs variétés. */
	public function testCropsList(): void {

		$eSequence = $this->getSequenceComplete();

		$response = $this->callApi('GET', 'sequence/{id}/cropsList', get: ['id' => $eSequence['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0);

		$crop = $response['body']['list'][0];

		$this->assertEquals($eSequence['id'], $crop['sequence']);
		$this->assertTrue(array_key_exists('varieties', $crop), 'la répartition des variétés accompagne la culture');

		// Sans identifiant dans la sous-sélection, une collection fille rend NULL et une notice
		foreach($crop['varieties'] as $slice) {
			$this->assertTrue(($slice['id'] ?? NULL) > 0, 'chaque part porte son identifiant');
		}

	}

	/** Une culture naît dans un itinéraire désigné, et pas dans celui d'une autre structure. */
	public function testCropCreateRejectsForeignSequence(): void {

		$eSequenceOther = Sequence::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

		$response = $this->callApi('POST', 'sequenceCrop', body: [
			'sequence' => $eSequenceOther['id'],
			'plant' => $this->getPlants(1)->first()['id'],
			'distance' => Crop::SPACING,
			'density' => NULL,
			'rows' => NULL,
			'plantSpacing' => NULL,
			'rowSpacing' => NULL,
			'seedling' => NULL,
			'seedlingSeeds' => NULL,
			'yieldExpected' => NULL,
			'mainUnit' => Crop::KG,
			'varieties' => ['variety' => [], 'varietyCreate' => [], 'varietyPartPercent' => []],
			'actions' => []
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_sequence', $response['body'][0]['code'] ?? NULL);

	}

	/** Les interventions d'un itinéraire, avec leur fenêtre en semaines relatives. */
	public function testFlowsList(): void {

		$eSequence = $this->getSequenceComplete();

		$response = $this->callApi('GET', 'sequence/{id}/flowsList', get: ['id' => $eSequence['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue($response['body']['count'] > 0, 'l\'itinéraire porte des interventions');

		$flow = $response['body']['list'][0];

		$this->assertEquals($eSequence['id'], $flow['sequence']);
		$this->assertTrue(array_key_exists('action', $flow));
		$this->assertTrue(array_key_exists('weekOnly', $flow) and array_key_exists('weekStart', $flow));

	}

	/** Une intervention rattachée à une culture d'un autre itinéraire est refusée. */
	public function testFlowCreateRejectsForeignCrop(): void {

		$eSequence = $this->getSequenceComplete();

		$eCropOther = $this->getCrops($this->createSequence(1, 'Autre itinéraire du décor'))->first();

		$this->assertTrue($eCropOther->notEmpty(), 'une autre culture existe dans la structure');

		$eAction = \work\ActionLib::getByStructure($this->eStructure, fqn: ACTION_RECOLTE);

		$response = $this->callApi('POST', 'sequenceFlow', body: [
			'sequence' => $eSequence['id'],
			'crop' => $eCropOther['id'],
			'action' => $eAction['id'],
			'methods' => [],
			'tools' => [],
			'description' => NULL,
			'fertilizer' => NULL,
			'seasonOnly' => NULL,
			'seasonStart' => NULL,
			'seasonStop' => NULL,
			'weekOnly' => 20,
			'yearOnly' => 0,
			'weekStart' => NULL,
			'yearStart' => NULL,
			'weekStop' => NULL,
			'yearStop' => NULL,
			'frequency' => NULL
		]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('unknown_crop', $response['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'sequence/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
