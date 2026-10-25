<?php
namespace series;

/**
 * Le chiffre d'affaires que `plant\ForecastLib::getByStructure()` pose sur chaque prévision et chaque culture,
 * et que le Prévisionnel somme en chiffres clés.
 *
 * Une espèce cultivée sans prévision en reçoit une, écrite par `getByStructure()` lui-même : ses parts
 * sont celles du schéma (100 % aux particuliers, 0 % aux professionnels), posées par `insert()`.
 * Le cas le prouve en relisant la ligne écrite, puis vérifie le chiffre d'affaires une fois un prix
 * et un volume posés.
 *
 * Lancement : dev/test package=series file=ForecastSalesAi
 */
class ForecastSalesAiTest extends SeriesFixtureAiTest {

	private function getForecast(Cultivation $eCultivation): \plant\Forecast {

		$cccCultivation = CultivationLib::getForForecast($this->eStructure, $this->getSeason());
		$ccForecast = \plant\ForecastLib::getByStructure($this->eStructure, $this->getSeason(), $cccCultivation);

		return $ccForecast[$eCultivation['plant']['id']][Cultivation::KG];

	}

	public function testSales(): void {

		$eCultivation = $this->createCultivation();

		$eCultivation['yieldExpected'] = 2.0;
		CultivationLib::update($eCultivation, ['yieldExpected']);

		$eForecast = $this->getForecast($eCultivation);

		$eForecastWritten = \plant\Forecast::model()
			->select(['privatePart', 'proPart', 'quantity'])
			->whereId($eForecast['id'])
			->get();

		$this->assertEquals(100, $eForecastWritten['privatePart'], 'la prévision créée ne porte pas la part du schéma pour les particuliers');
		$this->assertEquals(0, $eForecastWritten['proPart'], 'la prévision créée ne porte pas la part du schéma pour les professionnels');
		$this->assertEquals(NULL, $eForecastWritten['quantity'], 'la prévision créée porte un volume');

		$this->assertEquals(100.0, $eForecast['area'], 'la surface n\'est pas celle visée par la série');
		$this->assertEquals(0.0, $eForecast['salesForecast'], 'sans volume, le chiffre d\'affaires prévisionnel n\'est pas nul');
		$this->assertEquals(0.0, $eForecast['salesSeries'], 'sans prix, le chiffre d\'affaires planifié n\'est pas nul');

		// Un test monte son décor : `build()` vérifierait les bornes du prix et du volume, que ces valeurs respectent.
		$eForecast['privatePrice'] = 3.0;
		$eForecast['quantity'] = 50;
		\plant\ForecastLib::update($eForecast, ['privatePrice', 'quantity']);

		$eForecast = $this->getForecast($eCultivation);

		$this->assertEquals(150.0, $eForecast['salesForecast'], '50 kg à 3 € aux particuliers');
		$this->assertEquals(600.0, $eForecast['salesSeries'], '200 kg planifiés (2 kg/m² sur 100 m²) à 3 €');
		$this->assertEquals(600.0, $eForecast['cCultivation']->first()['sales'], 'la culture ne porte pas son chiffre d\'affaires');

	}

}
?>
