<?php
namespace series;

/**
 * Test bout-en-bout de l'API publique series\Cultivation (production/series/api/Cultivation_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=series file=CultivationApi
 */
class CultivationApiTest extends \dev\ApiTest {

	/**
	 * La saison sur laquelle le fichier travaille, lue sur la structure et non écrite en dur.
	 *
	 * `Configuration::checkSeason()` refuse une saison hors de [`seasonFirst`, `seasonLast`], et
	 * `MemberLib::getDynamicSeason()` retombe alors en silence sur `seasonLast` : une année figée
	 * finit par écrire sur une saison et relire sur une autre, sans que rien ne le signale.
	 * `seasonLast` est la seule valeur qu'aucune horloge ne périme.
	 */
	protected int $season;

	public function init(): void {

		parent::init();

		$this->season = $this->eStructure->conf(\map\Configuration::class)['seasonLast'];

	}


	protected function getTransactionModel(): \ModuleModel {
		return new CultivationModel();
	}

	protected function createPlant(): \plant\Plant {

		$plant = $this->callApi('POST', 'plant', body: [
			'name' => 'Test API Plant for Cultivation '.uniqid(),
			'color' => '#123456',
			'family' => NULL,
			'cycle' => 'annual',
			'plantsSafetyMargin' => 10,
			'seedsSafetyMargin' => 5
		]);

		return \plant\Plant::model()->select(['id'])->whereId($plant['body']['id'])->get();

	}

	protected function createSeries(): Series {

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => 'Test API Series for Cultivation',
			'cycle' => 'annual',
			'use' => 'block',
			'mode' => 'open-field',
			'season' => $this->season,
			'plants' => 1,
			'areaTarget' => 100,
			'perennialFirst' => new Series(),
			'duplicateOf' => new Series()
		]);

		SeriesLib::create($eSeries);

		return $eSeries;

	}

	/**
	 * Contourne CultivationLib::create() via l'ORM directement (pas l'API) pour fournir 'series'/'season',
	 * cf. testCreateIsCurrentlyBroken() ci-dessous.
	 */
	protected function createCultivation(\plant\Plant $ePlant, ?Series $eSeries = NULL): Cultivation {

		$eSeries ??= $this->createSeries();

		$eCultivation = new Cultivation([
			'structure' => $this->eStructure,
			'season' => $this->season,
			'series' => $eSeries,
			'sequence' => new \sequence\Sequence(),
			'crop' => new \sequence\Crop(),
			'plant' => $ePlant,
			'distance' => Cultivation::SPACING,
			'rows' => NULL,
			'rowSpacing' => NULL,
			'plantSpacing' => NULL,
			'density' => NULL,
			'varietiesUnit' => Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'mainUnit' => Cultivation::KG,
			'harvestPeriodExpected' => Cultivation::MONTH,
			'cSlice' => new \Collection()
		]);

		CultivationLib::create($eCultivation);

		return $eCultivation;

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'seriesCultivationList', get: ['start' => 1, 'limit' => 20, 'season' => $this->season]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	/**
	 * BUG connu (trouvé en écrivant ce test, non corrigé ici) : la correlation 'varieties' déclarée dans
	 * Cultivation_0_1betaApi::__construct() (production/series/api/Cultivation_0_1beta.a.php:44,
	 * `fn(\ApiData $data, Cultivation $e) => $data->exportCollection($e['cSlice'])`) n'a de sens que pour
	 * la direction 'out' (ApiInternalData::exportElement()). Or 'varieties' fait aussi partie de
	 * CultivationLib::getPropertiesCreate()/getPropertiesUpdate() : dès qu'un POST/PUT inclut 'varieties'
	 * dans le corps, ApiInternalData::convertProperties() appelle la même closure pour la direction 'in'
	 * avec la valeur brute du body (un array), pas une Cultivation -> TypeError, non catché par
	 * ApiRoute::run() (seuls Authentification/Resource/ApiException sont couverts) : 500 brut.
	 * Un vrai POST/PUT plante donc avant même d'atteindre Cultivation::build() (qui a par ailleurs son
	 * propre bug : 'series'/'season' ne sont ni dans getPropertiesCreate() ni initialisés par
	 * getNewElement(), alors que Cultivation::build() fait `$this->expects(['series' => ['use']])` en
	 * première ligne — cf. createCultivation() ci-dessous qui contourne les deux problèmes via l'ORM direct).
	 */
	public function testCreateIsCurrentlyBroken(): void {

		$ePlant = $this->createPlant();

		try {

			$this->callApi('POST', 'seriesCultivation', body: [
				'plant' => $ePlant['id'],
				'distance' => 'spacing',
				'density' => NULL,
				'rows' => NULL,
				'plantSpacing' => NULL,
				'rowSpacing' => NULL,
				'seedling' => NULL,
				'seedlingSeeds' => NULL,
				'yieldExpected' => NULL,
				'mainUnit' => 'kg',
				'harvestPeriodExpected' => 'month',
				'harvestMonthsExpected' => NULL,
				'harvestWeeksExpected' => NULL,
				'varietiesUnit' => 'percent',
				'varietiesTool' => NULL,
				'varieties' => []
			]);

			$this->assertFail('POST /seriesCultivation was expected to throw (cf the \'varieties\' correlation bug)');

		} catch(\TypeError $e) {
			$this->assertTrue(str_contains($e->getMessage(), 'must be of type series\Cultivation, array given'));
		}

	}

	public function testUpdateIsCurrentlyBroken(): void {

		$eCultivation = $this->createCultivation($this->createPlant());

		try {

			$this->callApi('PUT', 'seriesCultivation/{id}', body: [
				'yieldExpected' => 12.5,
				'mainUnit' => 'kg',
				'unitWeight' => NULL,
				'bunchWeight' => NULL,
				'harvestPeriodExpected' => 'month',
				'harvestMonthsExpected' => NULL,
				'harvestWeeksExpected' => NULL,
				'plant' => $eCultivation['plant']['id'],
				'distance' => 'spacing',
				'density' => NULL,
				'rows' => NULL,
				'plantSpacing' => NULL,
				'rowSpacing' => NULL,
				'seedling' => NULL,
				'seedlingSeeds' => NULL,
				'varietiesUnit' => 'percent',
				'varietiesTool' => NULL,
				'varieties' => []
			], get: ['id' => $eCultivation['id']]);

			$this->assertFail('PUT /seriesCultivation/{id} was expected to throw (cf the \'varieties\' correlation bug)');

		} catch(\TypeError $e) {
			$this->assertTrue(str_contains($e->getMessage(), 'must be of type series\Cultivation, array given'));
		}

	}

	/**
	 * Cultivation_0_1betaApi::getStandalonePropertiesUpdate() déclare 3 groupes, dont
	 * ['plant', 'varietiesUnit', 'varietiesTool', 'varieties'] : un PUT standalone qui ne cite PAS
	 * 'varieties' dans ses 'properties' n'invoque jamais la correlation cassée (cf
	 * testCreateIsCurrentlyBroken()/testUpdateIsCurrentlyBroken() ci-dessus) — donc, contrairement à une
	 * mise à jour complète, une mise à jour standalone d'un champ hors de ce groupe (ici 'yieldExpected')
	 * fonctionne réellement aujourd'hui.
	 */
	public function testStandaloneUpdateSingleField(): void {

		$eCultivation = $this->createCultivation($this->createPlant());

		$update = $this->callApi('PUT', 'seriesCultivation/{id}', body: [
			'properties' => ['yieldExpected'],
			'yieldExpected' => 12.5
		], get: ['id' => $eCultivation['id']]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals(12.5, $update['body']['yieldExpected'] ?? NULL);

	}

	/**
	 * 'harvestPeriodExpected' est groupé avec 'harvestMonthsExpected'/'harvestWeeksExpected' : un PUT
	 * standalone qui n'en cite qu'un seul doit être rejeté, cf ModuleApi::validateStandalonePropertiesUpdate().
	 */
	public function testStandaloneUpdateGroupPartialIsRejected(): void {

		$eCultivation = $this->createCultivation($this->createPlant());

		$response = $this->callApi('PUT', 'seriesCultivation/{id}', body: [
			'properties' => ['harvestPeriodExpected'],
			'harvestPeriodExpected' => 'week'
		], get: ['id' => $eCultivation['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * 'actions' ne fait partie de CultivationLib::getPropertiesUpdate() que pour $origin !== 'api' :
	 * côté API, doit être rejeté en standalone, pas silencieusement accepté.
	 */
	public function testStandaloneUpdateUnknownPropertyIsRejected(): void {

		$eCultivation = $this->createCultivation($this->createPlant());

		$response = $this->callApi('PUT', 'seriesCultivation/{id}', body: [
			'properties' => ['actions'],
			'actions' => []
		], get: ['id' => $eCultivation['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	public function testReadDelete(): void {

		// CultivationLib::canDelete() interdit de supprimer la dernière culture d'une série : on en crée
		// une seconde dans la même série pour pouvoir tester une suppression qui aboutit.
		$eSeries = $this->createSeries();
		$eCultivation = $this->createCultivation($this->createPlant(), $eSeries);
		$this->createCultivation($this->createPlant(), $eSeries);
		$id = $eCultivation['id'];

		$read = $this->callApi('GET', 'seriesCultivation/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($eCultivation['plant']['id'], $read['body']['plant'] ?? NULL);
		$this->assertEquals([], $read['body']['varieties'] ?? NULL);

		// PUT complet est atteint par le même bug que testCreateIsCurrentlyBroken() ('varieties' fait
		// aussi partie de CultivationLib::getPropertiesUpdate()) : cf testUpdateIsCurrentlyBroken()
		// ci-dessus. Un PUT standalone qui évite ce champ fonctionne : cf testStandaloneUpdateSingleField().

		$delete = $this->callApi('DELETE', 'seriesCultivation/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'seriesCultivation/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'seriesCultivation/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

}
?>
