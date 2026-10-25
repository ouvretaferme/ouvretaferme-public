<?php
namespace toolbox;

/**
 * Test bout-en-bout de la ressource toolbox\Rain (production/toolbox/api/Rain_0_1beta.a.php).
 *
 * Chaque cas pose son pluviomètre, sur une zone neuve de la structure du décor, et ses relevés, dans
 * la transaction de la méthode : un pluviomètre neuf n'a relevé aucun jour, les cas qui écrivent
 * partent donc d'un jour vide. Les témoins d'une autre structure se lisent dans la base, le cas ne
 * demandant qu'une ligne que la borne de structure doit écarter.
 *
 * Deux comportements que l'écran porte et que l'API hérite sont éprouvés ici, parce qu'ils
 * surprennent un client : une création **ajoute** au jour au lieu de le remplacer, et une quantité
 * négative peut ramener le jour à zéro, ce qui supprime le relevé. La création rend alors le jour
 * lui-même, en 200 avec son total, et non la ligne écrite.
 *
 * Lancement : dev/test package=toolbox file=RainApi
 */
class RainApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new RainModel();
	}

	/** Un pluviomètre actif sur une zone neuve, posé par l'API : il n'a encore relevé aucun jour. */
	private function createGauge(): Gauge {

		// `createdBy` prend l'utilisateur en ligne, que la colonne exige
		\user\ConnectionLib::setOnline($this->getAdmin());

		$eZone = new \map\Zone([
			'name' => 'Zone API '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build() : il jugerait le nom, la surface, le tracé et les saisons que saisit l'utilisateur, le cas ne demande qu'un lieu où poser un pluviomètre
		\map\ZoneLib::create($eZone);

		$response = $this->callApi('POST', 'gauge', body: ['zone' => $eZone['id']]);

		$this->assertEquals(201, $response['status'], 'le pluviomètre du cas se crée');

		return Gauge::model()
			->select(['id', 'structure', 'zone', 'status'])
			->whereId($response['body']['id'])
			->get();

	}

	/** Un relevé posé par l'API, son identifiant rendu. */
	private function createRain(Gauge $eGauge, string $date, float $rain): int {

		$response = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => $rain]);

		$this->assertEquals(200, $response['status'], 'le relevé du cas se crée');

		return $response['body']['id'];

	}

	/** Un relevé d'une autre structure : la ressource ne doit jamais le rendre. */
	private function getRainForeign(): Rain {

		return Rain::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	/** Un pluviomètre d'une autre structure : l'étape `owned` doit le refuser. */
	private function getGaugeForeign(): Gauge {

		return Gauge::model()
			->select(['id', 'structure'])
			->whereStructure('!=', $this->eStructure)
			->sort(['id' => SORT_ASC])
			->get();

	}

	public function testList(): void {

		$eGauge = $this->createGauge();

		$this->createRain($eGauge, \Clock::date(), 12);
		$this->createRain($eGauge, date('Y-m-d', strtotime(\Clock::date().' -1 day')), 3);
		$this->createRain($this->createGauge(), \Clock::date(), 5);

		$response = $this->callApi('GET', 'rainList', get: ['gauge' => $eGauge['id'], 'limit' => 500]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(2, $response['body']['count'], 'la liste compte les relevés du pluviomètre, et eux seuls');
		$this->assertEquals(['id', 'gauge', 'date', 'rain'], array_keys($response['body']['list'][0]), 'la ressource rend exactement ces champs');
		$this->assertEquals([$eGauge['id']], array_values(array_unique(array_column($response['body']['list'], 'gauge'))));

	}

	public function testListPeriod(): void {

		$eGauge = $this->createGauge();

		$dateFrom = date('Y-m-d', strtotime(\Clock::date().' -365 days'));
		$dateTo = \Clock::date();

		// Les deux bornes de la période et un jour juste avant elle.
		$expected = [
			$this->createRain($eGauge, $dateFrom, 4),
			$this->createRain($eGauge, $dateTo, 6),
		];

		$this->createRain($eGauge, date('Y-m-d', strtotime($dateFrom.' -1 day')), 8);

		$response = $this->callApi('GET', 'rainList', get: [
			'gauge' => $eGauge['id'],
			'dateFrom' => $dateFrom,
			'dateTo' => $dateTo,
			'limit' => 500
		]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($expected, array_column($response['body']['list'], 'id'), 'la période rend exactement les relevés de la période');

	}

	public function testListOutsideStructure(): void {

		$this->createRain($this->createGauge(), \Clock::date(), 12);

		$ids = Rain::model()
			->whereStructure($this->eStructure)
			->getColumn('id');

		$response = $this->callApi('GET', 'rainList', get: ['limit' => 500]);

		$this->assertEquals(200, $response['status']);

		$outside = array_diff(array_column($response['body']['list'], 'id'), $ids);

		$this->assertEquals([], array_values($outside), 'la liste ne rend aucun relevé hors de la structure');
		$this->assertTrue($response['body']['list'] !== [], 'la liste rend le relevé posé par le cas');

	}

	public function testRead(): void {

		$eGauge = $this->createGauge();
		$rain = $this->createRain($eGauge, \Clock::date(), 7.5);

		$response = $this->callApi('GET', 'rain/{id}', get: ['id' => $rain]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals($eGauge['id'], $response['body']['gauge']);
		$this->assertEquals(\Clock::date(), $response['body']['date']);
		$this->assertEquals(7.5, (float)$response['body']['rain']);

	}

	public function testReadForeign(): void {

		$eRainForeign = $this->getRainForeign();

		if($this->assertTrue($eRainForeign->notEmpty(), 'la base porte un relevé hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('GET', 'rain/{id}', get: ['id' => $eRainForeign['id']]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateAdds(): void {

		$eGauge = $this->createGauge();
		$date = \Clock::date();

		$response = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 12]);

		$this->assertEquals(200, $response['status'], 'un ajout au jour n\'est pas la création d\'une ressource de plus');
		$this->assertEquals($date, $response['body']['date']);
		$this->assertEquals(12.0, (float)$response['body']['rain']);

		// Le second relevé du même jour s'ajoute au premier, il ne le remplace pas.
		$again = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 12]);

		$this->assertEquals(200, $again['status']);
		$this->assertEquals($response['body']['id'], $again['body']['id'], 'le jour ne porte qu\'une ligne, et la création rend la sienne');
		$this->assertEquals(24.0, (float)$again['body']['rain']);

		$this->assertEquals(1, Rain::model()->whereGauge($eGauge)->whereDate($date)->count(), 'deux créations n\'ont écrit qu\'une ligne');

	}

	public function testCreateNegative(): void {

		$eGauge = $this->createGauge();
		$date = \Clock::date();

		$this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 30]);

		$response = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => -5]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(25.0, (float)$response['body']['rain'], 'une quantité négative retire des mm au jour');

		// Retirer plus que le jour n'en porte est refusé, comme dans l'écran.
		$tooMuch = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => -40]);

		$this->assertEquals(400, $tooMuch['status']);
		$this->assertEquals('Rain::rain.exceeds', $tooMuch['body'][0]['code'] ?? NULL);

	}

	public function testCreateEmptiesTheDay(): void {

		$eGauge = $this->createGauge();
		$date = \Clock::date();

		$this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 30]);

		// Le jour ramené à zéro est supprimé (`RainLib::create()`), mais l'écriture a bien eu lieu : la
		// réponse dit ce que le jour porte désormais, et non qu'il est introuvable.
		$response = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => -30]);

		$this->assertEquals(200, $response['status'], json_encode($response['body']));
		$this->assertEquals(0.0, (float)$response['body']['rain'], 'le jour vidé rend zéro');
		$this->assertTrue(array_key_exists('id', $response['body']) and $response['body']['id'] === NULL, 'et un identifiant présent mais nul, la ligne du jour n\'existant plus');
		$this->assertEquals($date, $response['body']['date']);
		$this->assertEquals($eGauge['id'], $response['body']['gauge']);

		$this->assertEquals(0, Rain::model()->whereGauge($eGauge)->whereDate($date)->count(), 'le jour vidé ne laisse aucune ligne');

	}

	public function testCreateFuture(): void {

		$eGauge = $this->createGauge();
		$date = date('Y-m-d', strtotime(\Clock::date().' +1 day'));

		$response = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 12]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Rain::date.future', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateGaugeForeign(): void {

		$eGaugeForeign = $this->getGaugeForeign();

		if($this->assertTrue($eGaugeForeign->notEmpty(), 'la base porte un pluviomètre hors de la structure '.static::STRUCTURE) === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'rain', body: [
			'gauge' => $eGaugeForeign['id'],
			'date' => \Clock::date(),
			'rain' => 12
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Rain::gauge.owned', $response['body'][0]['code'] ?? NULL);

	}

	public function testCreateIncomplete(): void {

		// Un corps qui ne porte pas toutes les propriétés de la liste est refusé avant build().
		$response = $this->callApi('POST', 'rain', body: ['gauge' => $this->createGauge()['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals(['Rain::date.missing', 'Rain::rain.missing'], array_column($response['body'], 'code'));

	}

	public function testUpdate(): void {

		$eGauge = $this->createGauge();
		$date = \Clock::date();

		$created = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 12]);

		// La modification pose le total du jour, elle ne s'y ajoute pas : c'est la roue crantée de l'écran.
		$response = $this->callApi('PUT', 'rain/{id}', body: ['rain' => 7], get: ['id' => $created['body']['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(7.0, (float)$response['body']['rain']);

		$this->assertEquals(7.0, (float)Rain::model()->whereId($created['body']['id'])->getValue('rain'), 'le total du jour a été remplacé en base');

	}

	public function testDelete(): void {

		$eGauge = $this->createGauge();
		$date = \Clock::date();

		$created = $this->callApi('POST', 'rain', body: ['gauge' => $eGauge['id'], 'date' => $date, 'rain' => 12]);

		$response = $this->callApi('DELETE', 'rain/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals(0, Rain::model()->whereId($created['body']['id'])->count(), 'le relevé supprimé ne se relit plus');

		$again = $this->callApi('GET', 'rain/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(404, $again['status']);

	}

}
?>
