<?php
namespace series;

/**
 * Test bout-en-bout de l'API publique series\Series (production/series/api/Series_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=series file=SeriesApi
 */
class SeriesApiTest extends SeriesFixtureAiTest {

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
		return new SeriesModel();
	}

	public function testList(): void {

		$response = $this->callApi('GET', 'seriesList', get: ['start' => 1, 'limit' => 20, 'season' => $this->season]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	/**
	 * Une liste demandée sans saison ni début de page, comme le fait un client qui découvre l'API.
	 * Relevé en production le 2026-10-07 : `seriesList?limit=3` rendait une erreur 500.
	 */
	public function testListWithoutSeason(): void {

		$response = $this->callApi('GET', 'seriesList', get: ['limit' => 3]);

		$this->assertEquals(200, $response['status'], json_encode($response['body']));

	}

	/**
	 * Créer une série, c'est créer ce qu'elle met en culture : le corps porte une liste indexée
	 * d'espèces sous `plant`, et c'est de leur nombre que vient la colonne `plants`, qu'aucune
	 * propriété ne construit. Le POST plantait sur une erreur SQL brute avant que la ressource ne
	 * reproduise le geste du produit (SeriesLib::prepareCreate() puis createWithCultivations()).
	 */
	public function testCreate(): void {

		$cPlant = \plant\Plant::model()
			->select(['id'])
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_DESC])
			->getCollection(0, 2);

		$this->assertEquals(2, $cPlant->count(), 'la structure a au moins deux espèces');

		$create = $this->callApi('POST', 'series', body: [
			'sequence' => NULL,
			'name' => 'Test API Series',
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'areaTarget' => 100,
			'lengthTarget' => NULL,
			'bedWidth' => NULL,
			'alleyWidth' => NULL,
			'perennialLifetime' => NULL,
			'plant' => $cPlant->getIds(),
			'varietiesUnit' => array_fill(0, 2, Cultivation::PERCENT),
			'varietiesTool' => array_fill(0, 2, ''),
			'varieties' => array_fill(0, 2, ['variety' => [], 'varietyCreate' => [], 'varietyPartPercent' => []]),
			'distance' => array_fill(0, 2, Cultivation::SPACING),
			'density' => array_fill(0, 2, NULL),
			'rows' => array_fill(0, 2, NULL),
			'plantSpacing' => array_fill(0, 2, NULL),
			'rowSpacing' => array_fill(0, 2, NULL),
			'seedling' => array_fill(0, 2, NULL),
			'seedlingSeeds' => array_fill(0, 2, NULL),
			'yieldExpected' => array_fill(0, 2, NULL),
			'mainUnit' => array_fill(0, 2, Cultivation::KG),
			'actions' => array_fill(0, 2, []),
			// Une période mensuelle veut des mois : un tableau de semaines vide n'est pas NULL, et
			// Cultivation::getHarvestBounds() y appelle min()
			'harvestPeriodExpected' => array_fill(0, 2, Cultivation::MONTH),
			'harvestMonthsExpected' => array_fill(0, 2, [$this->season.'-06', $this->season.'-07'])
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);
		$this->assertEquals($this->season, $create['body']['season'] ?? NULL);

		$id = $create['body']['id'];

		$this->assertEquals(2, Series::model()->whereId($id)->getValue('plants'), 'la colonne obligatoire vient du nombre d\'espèces');
		$this->assertEquals(2, Cultivation::model()->whereSeries($id)->count(), 'une mise en culture par espèce');

	}

	/**
	 * Dérouler un itinéraire technique : c'est ce pour quoi un itinéraire existe. `sequence` le
	 * désigne, `crop` dit à chaque index de quelle espèce de l'itinéraire la mise en culture est
	 * l'exemplaire, et les interventions modèles deviennent des tâches datées.
	 */
	public function testCreateFromSequence(): void {

		if($this->requireAction(ACTION_PLANTATION) === FALSE) {
			return;
		}

		$eSequence = $this->createSequence();
		$cCrop = $this->getCrops($eSequence);

		$this->assertTrue($cCrop->notEmpty(), 'l\'itinéraire porte des espèces');

		foreach($cCrop as $eCrop) {
			$this->createFlow($eSequence, $eCrop);
		}

		$flows = \sequence\Flow::model()->whereSequence($eSequence)->count();

		$count = $cCrop->count();

		$create = $this->callApi('POST', 'series', body: [
			'sequence' => $eSequence['id'],
			'name' => 'Test API Series depuis itinéraire',
			'cycle' => Series::ANNUAL,
			'use' => $eSequence['use'],
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'areaTarget' => 100,
			'lengthTarget' => NULL,
			'bedWidth' => $eSequence['bedWidth'],
			'alleyWidth' => $eSequence['alleyWidth'],
			'perennialLifetime' => NULL,
			'startWeek' => 10,
			'startYear' => 0,
			'crop' => $cCrop->getIds(),
			'plant' => $cCrop->getColumnCollection('plant')->getIds(),
			'varietiesUnit' => array_fill(0, $count, Cultivation::PERCENT),
			'varietiesTool' => array_fill(0, $count, ''),
			'varieties' => array_fill(0, $count, ['variety' => [], 'varietyCreate' => [], 'varietyPartPercent' => []]),
			'distance' => array_fill(0, $count, Cultivation::SPACING),
			'density' => array_fill(0, $count, NULL),
			'rows' => array_fill(0, $count, NULL),
			'plantSpacing' => array_fill(0, $count, NULL),
			'rowSpacing' => array_fill(0, $count, NULL),
			'seedling' => array_fill(0, $count, NULL),
			'seedlingSeeds' => array_fill(0, $count, NULL),
			'yieldExpected' => array_fill(0, $count, NULL),
			'mainUnit' => array_fill(0, $count, Cultivation::KG),
			'actions' => array_fill(0, $count, [])
		]);

		$this->assertEquals(201, $create['status']);

		$id = $create['body']['id'];

		$this->assertEquals($eSequence['id'], $create['body']['sequence'] ?? NULL, 'la série garde le lien vers son itinéraire');
		$this->assertEquals($count, Cultivation::model()->whereSeries($id)->count(), 'une mise en culture par espèce de l\'itinéraire');

		// Les interventions modèles de l'itinéraire deviennent des tâches de la série
		$this->assertTrue($flows > 0, 'l\'itinéraire porte des interventions à dérouler');
		$this->assertTrue(Task::model()->whereSeries($id)->count() > 0, 'les interventions sont devenues des tâches');

	}

	/**
	 * Une espèce d'une autre structure ne se met pas en culture ici. Le contrôle de
	 * Cultivation::plant.check compare les structures depuis le 9 septembre, là où il pesait
	 * Plant::canRead() : il refusait déjà ce cas pour un membre d'une seule structure, il le refuse
	 * maintenant pour tout le monde, API comprise.
	 */
	public function testCreateRejectsForeignPlant(): void {

		$ePlantOther = \plant\Plant::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->where('structure IS NOT NULL')
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($ePlantOther->notEmpty(), 'une autre structure a des espèces');

		$response = $this->callApi('POST', 'series', body: [
			'sequence' => NULL,
			'name' => 'Test API Series',
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'areaTarget' => 100,
			'lengthTarget' => NULL,
			'bedWidth' => NULL,
			'alleyWidth' => NULL,
			'perennialLifetime' => NULL,
			'plant' => [$ePlantOther['id']],
			'varietiesUnit' => [Cultivation::PERCENT],
			'varietiesTool' => [''],
			'varieties' => [['variety' => [], 'varietyCreate' => [], 'varietyPartPercent' => []]],
			'distance' => [Cultivation::SPACING],
			'density' => [NULL],
			'rows' => [NULL],
			'plantSpacing' => [NULL],
			'rowSpacing' => [NULL],
			'seedling' => [NULL],
			'seedlingSeeds' => [NULL],
			'yieldExpected' => [NULL],
			'mainUnit' => [Cultivation::KG],
			'actions' => [[]],
			'harvestPeriodExpected' => [Cultivation::MONTH],
			'harvestMonthsExpected' => [[$this->season.'-06']]
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Cultivation::plant.owned', $response['body'][0]['code'] ?? NULL);

	}

	/** Une série sans espèce n'a rien à mettre en terre. */
	public function testCreateRejectsWithoutPlant(): void {

		$response = $this->callApi('POST', 'series', body: [
			'sequence' => NULL,
			'name' => 'Test API Series',
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'areaTarget' => 100,
			'lengthTarget' => NULL,
			'bedWidth' => NULL,
			'alleyWidth' => NULL,
			'perennialLifetime' => NULL,
			'plant' => []
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Series::plantsCheck', $response['body'][0]['code'] ?? NULL);

	}

	public function testReadUpdateDelete(): void {

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => 'Test API Series',
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

		$id = $eSeries['id'];

		$read = $this->callApi('GET', 'series/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Series', $read['body']['name'] ?? NULL);

		$update = $this->callApi('PUT', 'series/{id}', body: [
			'name' => 'Test API Series 2',
			'use' => 'block',
			'mode' => 'open-field',
			'sequence' => NULL,
			'areaTarget' => 200,
			'lengthTarget' => NULL,
			'bedWidth' => NULL,
			'alleyWidth' => NULL,
			'season' => $this->season
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Series 2', $update['body']['name'] ?? NULL);

		$delete = $this->callApi('DELETE', 'series/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'series/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	public function testCloseAndOpen(): void {

		$id = $this->createSeries('Série à clôturer')['id'];

		$close = $this->callApi('PATCH', 'series/{id}/close', body: ['outcome' => Series::SUCCESS, 'comment' => '<p>Bonne saison</p>'], get: ['id' => $id]);

		$this->assertEquals(200, $close['status']);
		$this->assertEquals(Series::CLOSED, $close['body']['status'] ?? NULL);
		$this->assertEquals(Series::SUCCESS, $close['body']['outcome'] ?? NULL);
		$this->assertEquals('<main><p>Bonne saison</p></main>', $close['body']['comment'] ?? NULL);

		$closeAgain = $this->callApi('PATCH', 'series/{id}/close', body: [], get: ['id' => $id]);

		$this->assertEquals(412, $closeAgain['status']);
		$this->assertEquals('accept_close', $closeAgain['body'][0]['code'] ?? NULL);

		$open = $this->callApi('PATCH', 'series/{id}/open', get: ['id' => $id]);

		$this->assertEquals(200, $open['status']);
		$this->assertEquals(Series::OPEN, $open['body']['status'] ?? NULL);
		$this->assertTrue(array_key_exists('outcome', $open['body']));
		$this->assertEquals(NULL, $open['body']['outcome']);

		$openAgain = $this->callApi('PATCH', 'series/{id}/open', get: ['id' => $id]);

		$this->assertEquals(412, $openAgain['status']);
		$this->assertEquals('accept_open', $openAgain['body'][0]['code'] ?? NULL);

		$closeEmpty = $this->callApi('PATCH', 'series/{id}/close', body: [], get: ['id' => $id]);

		$this->assertEquals(200, $closeEmpty['status']);
		$this->assertTrue(array_key_exists('outcome', $closeEmpty['body']));
		$this->assertEquals(NULL, $closeEmpty['body']['outcome']);
		$this->assertEquals('<main><p>Bonne saison</p></main>', $closeEmpty['body']['comment'] ?? NULL);

	}

	public function testCloseRejectsUnknownOutcome(): void {

		$response = $this->callApi('PATCH', 'series/{id}/close', body: ['outcome' => 'perhaps'], get: ['id' => $this->createSeries('Série au dénouement inconnu')['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Series::outcome.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testCloseRejectsPlainTextComment(): void {

		$id = $this->createSeries('Série aux notes en texte brut')['id'];

		$response = $this->callApi('PATCH', 'series/{id}/close', body: ['comment' => 'Bonne saison'], get: ['id' => $id]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Series::comment.html', $response['body'][0]['code'] ?? NULL);
		$this->assertTrue(str_contains($response['body'][0]['message'] ?? '', 'HTML'));
		$this->assertEquals(Series::OPEN, Series::model()->select('status')->whereId($id)->getValue('status'));

	}

	public function testCloseRejectsPerennial(): void {

		$response = $this->callApi('PATCH', 'series/{id}/close', body: [], get: ['id' => $this->createSeriesPerennial()['id']]);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_close', $response['body'][0]['code'] ?? NULL);

	}

	public function testCloseIsScopedToStructure(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		// Insertion directe : createSeries() pose la structure du décor, et le cas veut une série d'une
		// autre structure, dont build() n'aurait aucune valeur à refuser.
		$eSeriesForeign = new Series([
			'structure' => $this->getStructureForeign(),
			'name' => 'Série étrangère',
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'plants' => 0
		]);

		Series::model()->insert($eSeriesForeign);

		$response = $this->callApi('PATCH', 'series/{id}/close', body: [], get: ['id' => $eSeriesForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);
		$this->assertEquals(Series::OPEN, Series::model()->select('status')->whereId($eSeriesForeign['id'])->getValue('status'));

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'series/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		// 'use' => 'bed' requiert 'bedWidth' (cf Series::build(), callback 'bedWidth.prepare')
		$response = $this->callApi('POST', 'series', body: [
			'sequence' => NULL,
			'name' => 'Test API Series',
			'cycle' => 'annual',
			'use' => 'bed',
			'mode' => 'open-field',
			'season' => $this->season,
			'areaTarget' => NULL,
			'lengthTarget' => 100,
			'bedWidth' => NULL,
			'alleyWidth' => NULL
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * Une série sans `use` est refusée sur `use`. Relevé en production le 2026-10-06 : les largeurs
	 * de planche et de passe-pied lisaient `use` resté non posé, et la création levait.
	 */
	public function testCreateRejectsWithoutUse(): void {

		$response = $this->callApi('POST', 'series', body: [
			'sequence' => NULL,
			'name' => 'Test API Series',
			'cycle' => Series::ANNUAL,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->season,
			'areaTarget' => NULL,
			'lengthTarget' => 100,
			'bedWidth' => 120,
			'alleyWidth' => 40
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['Series::use.check'], array_column($response['body'], 'code'));
		$this->assertTrue(AlertUi::getError('Series::use.check') !== NULL, 'le refus a un message à afficher');

	}

}
?>
