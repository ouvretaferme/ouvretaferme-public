<?php
namespace map;

/**
 * Le jardin ou l'abri que crée le démarrage guidé, tracé à son étape dans la parcelle tracée à l'étape d'avant. Son contour part
 * dans `plotCoordinates[<index>]` tant que son interrupteur est allumé, et `OnboardingLib::create()` ne le construit que sous une
 * parcelle tracée, comme la Carte (`Plot::acceptUpdateShape()`) : la surface d'un jardin et les dimensions d'un abri viennent
 * alors du tracé, par les étapes `drawn` de `Plot::properties()`.
 *
 * Le parcours est celui des planches temporaires, qui ne crée pas de planche. Le lieu posté est celui que la structure porte
 * déjà, pour que l'écriture de son système ne change rien.
 *
 * Le décor vit dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=OnboardingPlotAi
 */
class OnboardingPlotAiTest extends \dev\ApiTest {

	const array ZONE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];

	// Un rectangle d'environ 78 m sur 111 m, au milieu de la parcelle
	const array GARDEN = [[3.0995, 45.5995], [3.1005, 45.5995], [3.1005, 45.6005], [3.0995, 45.6005]];

	// Un rectangle de 30 m sur 8 m : 0,000385° de longitude et 0,000072° de latitude à 45,6° de latitude
	const array SHELTER = [[3.1000, 45.6000], [3.100385, 45.6000], [3.100385, 45.600072], [3.1000, 45.600072]];

	// Le même abri, large de 30 cm, sous la largeur minimale du schéma
	const array SHELTER_NARROW = [[3.1000, 45.6000], [3.100385, 45.6000], [3.100385, 45.6000027], [3.1000, 45.6000027]];

	const int SEASON = 2026;

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function getInput(array $values): array {

		$eSystem = \structure\SystemLib::getByStructure($this->eStructure);

		return $values + [
			'mode' => OnboardingLib::TEMPORARY,
			'name' => 'Parcelle du démarrage '.uniqid(),
			'area' => '24750',
			'coordinates' => json_encode(self::ZONE),
			'place' => $eSystem['place'],
			'placeLngLat' => ($eSystem['placeLngLat'] === NULL) ? '' : json_encode($eSystem['placeLngLat']),
			'altitude' => $eSystem['altitude']
		];

	}

	private function getPlot(Zone $eZone): Plot {

		return Plot::model()
			->select(['mode', 'coordinates', 'length', 'width', 'area'])
			->whereZone($eZone)
			->whereZoneFill(FALSE)
			->get();

	}

	public function testDrawnGardenTakesItsAreaFromTheDrawing(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([
			'plotName' => [1 => 'Jardin du haut'],
			'plotMode' => [1 => Plot::OPEN_FIELD],
			'plotCoordinates' => [1 => json_encode(self::GARDEN)],
			'plotArea' => [1 => '1200']
		]), self::SEASON);

		$ePlot = $this->getPlot($eZone);

		$this->assertEquals(5, count($ePlot['coordinates']), 'le contour du jardin est enregistré, refermé à l\'écriture');
		$this->assertEquals(MapLib::getArea(self::GARDEN), $ePlot['area'], 'la surface est celle du tracé, pas celle du formulaire');

	}

	public function testDrawnShelterTakesItsDimensionsFromTheDrawing(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([
			'plotName' => [1 => 'Tunnel du bas'],
			'plotMode' => [1 => Plot::GREENHOUSE],
			'plotCoordinates' => [1 => json_encode(self::SHELTER)],
			'plotLength' => [1 => '50'],
			'plotWidth' => [1 => '20']
		]), self::SEASON);

		$ePlot = $this->getPlot($eZone);

		$this->assertEquals(5, count($ePlot['coordinates']), 'le contour de l\'abri est enregistré');
		$this->assertEquals([30.0, 8.0], [round($ePlot['length']), round($ePlot['width'])], 'les dimensions sont celles du tracé, pas celles du formulaire');
		$this->assertEquals((int)round($ePlot['length'] * $ePlot['width']), $ePlot['area'], 'la surface d\'un abri vaut ses dimensions');

	}

	public function testGardenUnderAZoneDrawnLaterKeepsNoContour(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([
			'coordinates' => NULL,
			'plotName' => [1 => 'Jardin du haut'],
			'plotMode' => [1 => Plot::OPEN_FIELD],
			'plotCoordinates' => [1 => json_encode(self::GARDEN)],
			'plotArea' => [1 => '1200']
		]), self::SEASON);

		$ePlot = $this->getPlot($eZone);

		$this->assertEquals(NULL, $ePlot['coordinates'], 'sous une parcelle sans contour, le contour posté d\'un jardin est ignoré');
		$this->assertEquals(1200, $ePlot['area'], 'la surface reste celle du formulaire');

	}

	/**
	 * Les deux refus qui suivent construisent l'abri comme `create()`, par sa liste (`OnboardingLib::getPropertiesPlot()`), sans
	 * passer par lui : son échec annulerait toutes les transactions ouvertes, celle du test comprise.
	 */
	private function buildShelter(Zone $eZone, bool $zoneDrawn, array $coordinates): array {

		$fw = new \FailWatch();

		$ePlot = new Plot([
			'zone' => $eZone,
			'structure' => $this->eStructure
		]);

		$ePlot->build(OnboardingLib::getPropertiesPlot($zoneDrawn), [
			'mode' => Plot::GREENHOUSE,
			'name' => 'Tunnel du bas',
			'coordinates' => json_encode($coordinates),
			'length' => '30',
			'width' => '8'
		], new \PropertyList('create')->setWrapper(fn(string $property) => 'plot'.ucfirst($property).'[1]'));

		return [$ePlot, array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get())];

	}

	public function testNarrowShelterIsRefusedOnItsLength(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([]), self::SEASON);

		[, $fails] = $this->buildShelter($eZone, TRUE, self::SHELTER_NARROW);

		$this->assertEquals(['Plot::length.drawn plotLength[1]'], $fails, 'un seul refus, posé sous la longueur de la ligne, qui dit de redessiner');

	}

	/** Une parcelle refusée n'est pas créée : l'abri tracé dedans se construit sans contour, sur ses dimensions saisies, sans lever */
	public function testShelterUnderARefusedZoneKeepsItsTypedDimensions(): void {

		$fw = new \FailWatch();

		$eZone = new Zone([
			'structure' => $this->eStructure
		]);

		$eZone->build(ZoneLib::getPropertiesCreate('map'), $this->getInput([
			'coordinates' => json_encode(array_slice(self::ZONE, 0, 2))
		]), new \PropertyList('create'));

		$this->assertTrue($fw->has('Zone::coordinates.ring'), 'la parcelle est refusée');

		[$ePlot, $fails] = $this->buildShelter($eZone, FALSE, self::SHELTER);

		$this->assertEquals([], $fails, 'aucun refus sur la ligne de l\'abri');
		$this->assertFalse($ePlot->offsetExists('coordinates'), 'le contour posté n\'est pas construit');
		$this->assertEquals([30.0, 8.0], [$ePlot['length'], $ePlot['width']], 'les dimensions sont celles saisies');

	}

}
?>
