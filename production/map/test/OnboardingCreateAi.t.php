<?php
namespace map;

/**
 * La parcelle que crée le démarrage guidé, avec ou sans le contour dessiné à son étape. Le contour part avec le formulaire
 * tant que l'interrupteur « Dessiner maintenant » est allumé ; éteint, son champ est désactivé et ne part pas, si bien que
 * `OnboardingLib::create()` construit la même liste dans les deux cas (`ZoneLib::getPropertiesCreate('map')`).
 *
 * Le parcours est celui des planches temporaires, qui ne crée ni jardin ni planche : seule la parcelle est éprouvée. Le lieu
 * posté est celui que la structure porte déjà, pour que l'écriture de son système ne change rien.
 *
 * Le décor vit dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=OnboardingCreateAi
 */
class OnboardingCreateAiTest extends \dev\ApiTest {

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];

	const int SEASON = 2026;

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle porte son auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function getInput(array $values): array {

		$eSystem = \structure\SystemLib::getByStructure($this->eStructure);

		return $values + [
			'mode' => OnboardingLib::TEMPORARY,
			'name' => 'Parcelle du démarrage '.uniqid(),
			'place' => $eSystem['place'],
			'placeLngLat' => ($eSystem['placeLngLat'] === NULL) ? '' : json_encode($eSystem['placeLngLat']),
			'altitude' => $eSystem['altitude']
		];

	}

	private function getStored(Zone $eZone): Zone {

		return Zone::model()
			->select(['id', 'area', 'coordinates'])
			->whereId($eZone['id'])
			->get();

	}

	public function testDrawnZoneKeepsItsContour(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([
			'area' => '24750',
			'coordinates' => json_encode(self::SQUARE)
		]), self::SEASON);

		$eZoneStored = $this->getStored($eZone);

		$this->assertEquals(MapLib::getArea(self::SQUARE), $eZoneStored['area'], 'la surface est celle du contour, pas celle du formulaire');
		$this->assertEquals(5, count($eZoneStored['coordinates']), 'le contour est enregistré, refermé à l\'écriture');

	}

	public function testZoneDrawnLaterHasNoContour(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput([
			'area' => '1200'
		]), self::SEASON);

		$eZoneStored = $this->getStored($eZone);

		$this->assertEquals(1200, $eZoneStored['area']);
		$this->assertEquals(NULL, $eZoneStored['coordinates'], 'un contour remis à plus tard ne part pas, la parcelle reste à dessiner');

	}

	/** La construction de `create()` telle quelle : son échec annule toutes les transactions ouvertes, celle du test comprise. */
	public function testBrokenContourIsRefused(): void {

		$fw = new \FailWatch();

		$eZone = new Zone([
			'structure' => $this->eStructure
		]);

		$eZone->build(ZoneLib::getPropertiesCreate('map'), $this->getInput([
			'area' => '1200',
			'coordinates' => json_encode(array_slice(self::SQUARE, 0, 2))
		]), new \PropertyList('create'));

		$this->assertTrue($fw->has('Zone::coordinates.ring'), json_encode($fw->get()));

	}

}
?>
