<?php
namespace series;

/**
 * La marque d'état de la fiche d'une série, à côté de son titre (B3 L8c, question 7 tranchée en a).
 *
 * Une série ouverte n'en porte aucune, une série close en porte une, et son dénouement, quand elle
 * en a un, s'y ajoute.
 *
 * La lecture se borne à ce qui suit le titre jusqu'à la ligne de contexte.
 *
 * Lancement : dev/test package=series file=SeriesHeaderStateAi
 */
class SeriesHeaderStateAiTest extends SeriesFixtureAiTest {

	public function testOpenSeriesHasNoState(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$this->assertEquals('', $this->getState($this->readSeries($this->createSeries('Série ouverte du décor'))), 'une série ouverte porte une marque d\'état');

	}

	public function testClosedSeriesWithoutOutcome(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eSeries = $this->createSeries('Série close sans dénouement');
		$this->closeSeries($eSeries);

		$this->assertTrue($this->getState($this->readSeries($eSeries)) !== '', 'une série close ne porte pas de marque d\'état');

	}

	public function testClosedSeriesWithOutcome(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eSeries = $this->createSeries('Série close et réussie');
		$this->closeSeries($eSeries, Series::SUCCESS);

		$eSeriesRead = $this->readSeries($eSeries);
		$state = $this->getState($eSeriesRead);

		$eSeriesRead['outcome'] = NULL;

		$this->assertTrue(strlen($state) > strlen($this->getState($eSeriesRead)), 'le dénouement ne s\'ajoute pas à la marque de clôture');

	}

	private function readSeries(Series $eSeries): Series {

		return Series::model()
			->select(Series::getSelection())
			->whereId($eSeries['id'])
			->get();

	}

	private function getState(Series $eSeriesRead): string {

		$head = new SeriesUi()->getHeader($eSeriesRead);

		$start = strpos($head, '</h1>');
		$end = strpos($head, '<div class="util-page-header-context">');

		return trim(strip_tags(substr($head, $start, $end - $start)));

	}

}
?>
