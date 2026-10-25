<?php
namespace series;

/**
 * Test bout-en-bout de la ressource series\Place (production/series/api/Place_0_1beta.a.php) et des
 * actions d'assolement de la ressource series\Series (Series_0_1beta.a.php : addBeds, removeBeds,
 * bedSize, clearBeds).
 *
 * Le décor de `series` pose, dans la transaction de la méthode, une série ouverte en planches qui
 * porte déjà deux emplacements, et des planches neuves qu'elle n'occupe pas encore. Les témoins
 * d'une autre structure se lisent dans la base : le cas ne demande qu'une ligne que la borne de
 * structure doit écarter.
 *
 * Lancement : dev/test package=series file=PlaceApiAi
 */
class PlaceApiAiTest extends SeriesFixtureAiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PlaceModel();
	}

	/** Une série ouverte, annuelle, en planches, posée sur deux planches du décor. */
	private function getSeriesWithPlaces(): Series {

		return $this->once('seriesWithPlaces', function() {

			$eSeries = $this->createSeriesBed('Série assolée du décor');

			$this->createPlace($eSeries, $this->createBed(), 10);
			$this->createPlace($eSeries, $this->createBed(), 12);

			return Series::model()
				->select(Series::getSelection())
				->whereId($eSeries['id'])
				->get();

		});

	}

	/** Une planche neuve du décor, active et dimensionnée, qu'aucune série n'occupe. */
	private function getFreeBed(): \map\Bed {

		return $this->createBed();

	}

	private function getPlace(Series $eSeries, \map\Bed $eBed): Place {

		return Place::model()
			->select(['id', 'length', 'width', 'area'])
			->whereSeries($eSeries)
			->whereBed($eBed)
			->get();

	}

	private function readSeries(Series $eSeries): array {

		$response = $this->callApi('GET', 'series/{id}', get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $response['status']);

		return $response['body'];

	}

	public function testListRequiresSeason(): void {

		$response = $this->callApi('GET', 'placeList');

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('season.check', $response['body'][0]['code'] ?? NULL);

	}

	public function testListMatchesTable(): void {

		$eSeries = $this->getSeriesWithPlaces();
		$season = $this->getSeason();

		$response = $this->callApi('GET', 'placeList', get: ['season' => $season, 'limit' => 500]);

		$this->assertEquals(200, $response['status']);

		$expected = Place::model()
			->whereStructure($this->eStructure)
			->whereSeason($season)
			->count();

		$this->assertTrue($expected > 0);
		$this->assertEquals($expected, $response['body']['count']);

		$posed = Place::model()
			->whereSeries($eSeries)
			->getColumn('id');

		$this->assertEquals([], array_values(array_diff($posed, array_column($response['body']['list'], 'id'))), 'la liste rend les emplacements posés par le cas');
		$this->assertEquals(min($expected, 500), count($response['body']['list']));

		foreach($response['body']['list'] as $place) {
			$this->assertEquals($season, $place['season']);
			$this->assertTrue($place['bed'] > 0);
			$this->assertTrue($place['series'] !== NULL or $place['task'] !== NULL);
		}

	}

	public function testListFilteredBySeries(): void {

		$eSeries = $this->getSeriesWithPlaces();

		$this->assertTrue($eSeries->notEmpty());

		$response = $this->callApi('GET', 'placeList', get: ['season' => $eSeries['season'], 'series' => $eSeries['id']]);

		$this->assertEquals(200, $response['status']);

		$expected = Place::model()->whereSeries($eSeries)->count();

		$this->assertEquals($expected, $response['body']['count']);

		$length = 0;

		foreach($response['body']['list'] as $place) {
			$this->assertEquals($eSeries['id'], $place['series']);
			$length += $place['length'];
		}

		$this->assertEquals($eSeries['length'], $length);

		$first = $response['body']['list'][0];
		$read = $this->callApi('GET', 'place/{id}', get: ['id' => $first['id']]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals($first, $read['body']);

	}

	public function testReadOtherStructureIsNotFound(): void {

		$ePlace = Place::model()
			->select(['id'])
			->where('structure != '.$this->eStructure['id'])
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($ePlace->notEmpty());

		$response = $this->callApi('GET', 'place/{id}', get: ['id' => $ePlace['id']]);

		$this->assertEquals(404, $response['status']);

	}

	public function testAddSizeRemoveClear(): void {

		$eSeries = $this->getSeriesWithPlaces();
		$eBed = $this->getFreeBed();

		$this->assertTrue($eSeries->notEmpty());
		$this->assertTrue($eBed->notEmpty());

		$lengthBefore = $eSeries['length'];
		$size = min(10, $eBed['length']);

		$add = $this->callApi('PATCH', 'series/{id}/addBeds', body: ['beds' => [$eBed['id']], 'size' => $size], get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $add['status']);
		$this->assertEquals($lengthBefore + $size, $add['body']['length']);

		$ePlace = $this->getPlace($eSeries, $eBed);

		$this->assertTrue($ePlace->notEmpty());
		$this->assertEquals($size, $ePlace['length']);
		$this->assertEquals($ePlace['width'], $eBed['width'] ?? $ePlace['width']);

		$sizeAfter = max(1, $size - 1);

		$resize = $this->callApi('PATCH', 'series/{id}/bedSize', body: ['bed' => $eBed['id'], 'size' => $sizeAfter], get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $resize['status']);
		$this->assertEquals($lengthBefore + $sizeAfter, $resize['body']['length']);
		$this->assertEquals($sizeAfter, $this->getPlace($eSeries, $eBed)['length']);

		$remove = $this->callApi('PATCH', 'series/{id}/removeBeds', body: ['beds' => [$eBed['id']]], get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $remove['status']);
		$this->assertEquals($lengthBefore, $remove['body']['length']);
		$this->assertTrue($this->getPlace($eSeries, $eBed)->empty());

		$clear = $this->callApi('PATCH', 'series/{id}/clearBeds', body: [], get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $clear['status']);
		$this->assertEquals(NULL, $clear['body']['length']);
		$this->assertEquals(NULL, $clear['body']['area']);
		$this->assertEquals(0, Place::model()->whereSeries($eSeries)->count());

		$this->assertEquals(NULL, $this->readSeries($eSeries)['length']);

	}

	/**
	 * Une taille plus grande que la planche : l'ajout la ramène à la longueur de la planche
	 * (PlaceLib::changeBeds()), comme le fait l'écran ; le redimensionnement, lui, refuse en nommant
	 * la raison (PlaceLib::setSize() → buildFromBeds()).
	 */
	public function testSizeBeyondBedLength(): void {

		$eSeries = $this->getSeriesWithPlaces();
		$eBed = $this->getFreeBed();

		$add = $this->callApi('PATCH', 'series/{id}/addBeds', body: ['beds' => [$eBed['id']], 'size' => $eBed['length'] + 1], get: ['id' => $eSeries['id']]);

		$this->assertEquals(200, $add['status']);
		$this->assertEquals($eBed['length'], $this->getPlace($eSeries, $eBed)['length']);

		$resize = $this->callApi('PATCH', 'series/{id}/bedSize', body: ['bed' => $eBed['id'], 'size' => $eBed['length'] + 1], get: ['id' => $eSeries['id']]);

		$this->assertEquals(400, $resize['status']);
		$this->assertEquals('Place::bedsExceeded', $resize['body'][0]['code'] ?? NULL);
		$this->assertEquals($eBed['length'], $this->getPlace($eSeries, $eBed)['length']);

	}

	/** Une planche d'une autre structure ne s'assole pas, même par son id. */
	public function testAddBedOfOtherStructure(): void {

		$eSeries = $this->getSeriesWithPlaces();

		$eBed = \map\Bed::model()
			->select(['id'])
			->where('structure != '.$this->eStructure['id'])
			->whereStatus(\map\Bed::ACTIVE)
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($eBed->notEmpty());

		$response = $this->callApi('PATCH', 'series/{id}/addBeds', body: ['beds' => [$eBed['id']]], get: ['id' => $eSeries['id']]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Place::bedsCheck', $response['body'][0]['code'] ?? NULL);

		$resize = $this->callApi('PATCH', 'series/{id}/bedSize', body: ['bed' => $eBed['id'], 'size' => 1], get: ['id' => $eSeries['id']]);

		$this->assertEquals(404, $resize['status']);

	}

	/** Une série clôturée refuse l'assolement, comme l'écran (page/place.p.php, $refusesClosed). */
	public function testClosedSeriesIsRefused(): void {

		$eSeries = $this->createSeriesBed('Série close du décor');
		$this->closeSeries($eSeries);

		$eBed = $this->getFreeBed();

		$response = $this->callApi('PATCH', 'series/{id}/addBeds', body: ['beds' => [$eBed['id']]], get: ['id' => $eSeries['id']]);

		$this->assertEquals(412, $response['status']);
		$this->assertEquals('accept_update', $response['body'][0]['code'] ?? NULL);

		$clear = $this->callApi('PATCH', 'series/{id}/clearBeds', body: [], get: ['id' => $eSeries['id']]);

		$this->assertEquals(412, $clear['status']);

	}

}
?>
