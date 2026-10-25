<?php
namespace plant;

/**
 * Les chiffres clés du prévisionnel financier (`ForecastUi::getFigures()`) et le chiffre d'affaires d'une prévision
 * (`Forecast::getSales()`), que la page somme avant de les faire descendre à l'interface.
 *
 * Rien n'est lu ni écrit en base : les prévisions sont construites à la main.
 *
 * Lancement : dev/test package=plant file=ForecastFiguresAi
 */
class ForecastFiguresAiTest extends \Test {

	public function testSales(): void {

		$eForecast = new Forecast([
			'privatePrice' => 4.0,
			'privatePart' => 25,
			'proPrice' => 2.0,
			'proPart' => 75,
		]);

		$this->assertEquals(250.0, $eForecast->getSales(100), '100 kg, 25 % à 4 € et 75 % à 2 €');

		$eForecast = new Forecast([
			'privatePrice' => 3.0,
			'privatePart' => 100,
			'proPrice' => NULL,
			'proPart' => 0,
		]);

		$this->assertEquals(300.0, $eForecast->getSales(100), 'sans prix professionnel, tout part aux particuliers');

	}

	public function testFigures(): void {

		$ccForecast = new \Collection([
			1 => new \Collection(),
			2 => new \Collection(),
		]);

		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.new ForecastUi()->getFigures($ccForecast, 1250.0, 0.0), LIBXML_NOERROR);
		$xpath = new \DOMXPath($document);

		$figures = $xpath->query('//div[@class="util-figures mb-2"]/div[@class="util-figure"]');

		$this->assertEquals(3, $figures->length, 'les cultures, le total prévisionnel et le total planifié');

		foreach($figures as $figure) {
			$this->assertEquals(1, $xpath->query('div[@class="util-figure-label util-figure-label-top"]', $figure)->length, 'l\'intitulé au-dessus de la valeur');
		}

		$values = [];

		foreach($xpath->query('//div[@class="util-figure-value"]') as $value) {
			$values[] = trim($value->textContent);
		}

		$this->assertEquals('2', $values[0], 'le nombre d\'espèces');
		$this->assertEquals('/', $values[2], 'un total nul rend « / »');

		$this->assertEquals('', new ForecastUi()->getFigures(new \Collection([1 => new \Collection()]), 1250.0, 1250.0), 'rien pour une seule espèce');

	}

}
?>
