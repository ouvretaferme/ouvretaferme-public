<?php
namespace map;

/**
 * Les planches que crée le démarrage guidé, posées en rang sur la carte de leur support à l'étape des planches. Le rang part dans
 * `bedDraw[<index>]`, la place de chaque planche sous sa position, et `OnboardingLib::create()` l'écrit comme la Carte
 * (`BedLib::buildDraw()`, puis `BedLib::createCollectionDrawn()`) sur la saison de l'envoi.
 *
 * Le parcours est celui des planches permanentes. Le lieu posté est celui que la structure porte déjà, pour que l'écriture de son
 * système ne change rien.
 *
 * Le décor vit dans la transaction de la méthode, sur la structure `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=map file=OnboardingBedAi
 */
class OnboardingBedAiTest extends \dev\ApiTest {

	const int SEASON = 2026;

	const array ZONE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010]];

	const array GARDEN = [[3.0995, 45.5995], [3.1005, 45.5995], [3.1005, 45.6005], [3.0995, 45.6005]];

	// Le même jardin sans son quart nord-est, en L
	const array GARDEN_L = [[3.0995, 45.5995], [3.1005, 45.5995], [3.1005, 45.6000], [3.1000, 45.6000], [3.1000, 45.6005], [3.0995, 45.6005]];

	// Trois planches de 20 m sur 80 cm, à 1,3 m l'une de l'autre, depuis le haut du jardin vers le sud
	const array DRAW = [
		'corner' => ['[3.0996,45.6004]', '[3.099617,45.6004]', '[3.099634,45.6004]'],
		'angle' => [1.5708, 1.5708, 1.5708]
	];

	protected function getTransactionModel(): \ModuleModel {
		return new ZoneModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle, un jardin et un rang portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function getInput(array $values): array {

		$eSystem = \structure\SystemLib::getByStructure($this->eStructure);

		return $values + [
			'mode' => OnboardingLib::PERMANENT,
			'name' => 'Parcelle du démarrage '.uniqid(),
			'area' => '24750',
			'coordinates' => json_encode(self::ZONE),
			'place' => $eSystem['place'],
			'placeLngLat' => ($eSystem['placeLngLat'] === NULL) ? '' : json_encode($eSystem['placeLngLat']),
			'altitude' => $eSystem['altitude']
		];

	}

	private function getBeds(int $index, array $values = []): array {

		return $values + [
			'beds' => [$index => '3'],
			'bedLength' => [$index => '20'],
			'bedWidth' => [$index => '80'],
			'bedAlleyWidth' => [$index => '50']
		];

	}

	private function getDraws(Zone $eZone): \Collection {

		return Draw::model()
			->select(['season', 'corner', 'angle', 'bed' => ['name']])
			->whereZone($eZone)
			->whereBed('!=', NULL)
			->getCollection();

	}

	public function testBedsDrawnInAGardenTakeTheirPlace(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput($this->getBeds(1, [
			'plotName' => [1 => 'Jardin du haut'],
			'plotMode' => [1 => Plot::OPEN_FIELD],
			'plotCoordinates' => [1 => json_encode(self::GARDEN)],
			'bedDraw' => [1 => json_encode(self::DRAW)]
		])), self::SEASON);

		$cDraw = $this->getDraws($eZone);

		$this->assertEquals(3, $cDraw->count(), 'une place par planche');
		$this->assertEquals([self::SEASON], array_values(array_unique($cDraw->getColumn('season'))), 'sur la saison de l\'envoi');
		$this->assertEquals([3.0996, 45.6004], $cDraw->find(fn($eDraw) => $eDraw['bed']['name'] === '1')->first()['corner'], 'la première planche est au premier coin du rang');

	}

	public function testBedsDrawnInAGardenThatIsNotARectangleTakeTheirPlace(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput($this->getBeds(1, [
			'plotName' => [1 => 'Jardin en L'],
			'plotMode' => [1 => Plot::OPEN_FIELD],
			'plotCoordinates' => [1 => json_encode(self::GARDEN_L)],
			'bedDraw' => [1 => json_encode(self::DRAW)]
		])), self::SEASON);

		$this->assertEquals(3, $this->getDraws($eZone)->count(), 'un rang qui tient dans la branche du L');

	}

	public function testBedsDrawnOnTheZoneTakeTheirPlace(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput($this->getBeds(0, [
			'bedDraw' => [0 => json_encode(self::DRAW)]
		])), self::SEASON);

		$this->assertEquals(3, $this->getDraws($eZone)->count(), 'sans jardin, le rang se pose dans la parcelle');

	}

	public function testBedsNotDrawnStayToDraw(): void {

		$eZone = OnboardingLib::create($this->eStructure, $this->getInput($this->getBeds(1, [
			'plotName' => [1 => 'Jardin du haut'],
			'plotMode' => [1 => Plot::OPEN_FIELD],
			'plotCoordinates' => [1 => json_encode(self::GARDEN)]
		])), self::SEASON);

		$this->assertEquals(0, $this->getDraws($eZone)->count(), 'sans rang posté, les planches restent à dessiner');
		$this->assertEquals(3, Bed::model()->whereZone($eZone)->wherePlotFill(FALSE)->count(), 'les planches sont créées');

	}

	/**
	 * Les deux refus qui suivent passent par `BedLib::buildDraw()`, que `create()` appelle, sans passer par lui : son échec annulerait
	 * toutes les transactions ouvertes, celle du test comprise.
	 */
	private function buildDraw(Plot $ePlot, string $json, \Collection $cBed = new \Collection()): array {

		$fw = new \FailWatch();

		BedLib::buildDraw($ePlot, self::SEASON, $cBed, $json, 'bedDraw[1]');

		return array_map(fn(array $fail) => $fail[1].' '.$fail[3], $fw->get());

	}

	public function testDrawWithoutDrawnSupportIsRefused(): void {

		$ePlot = new Plot([
			'structure' => $this->eStructure,
			'zoneFill' => FALSE,
			'zone' => new Zone(['coordinates' => NULL]),
			'coordinates' => NULL
		]);

		$this->assertEquals(['Bed::canNotDraw bedDraw[1]'], $this->buildDraw($ePlot, json_encode(self::DRAW)), 'un jardin sans forme ne reçoit pas de rang');

	}

	/**
	 * Deux cents planches, le plafond, posées d'office en rang, s'étendent sur 260 m : le navigateur les pose malgré tout contre le
	 * plus grand côté (`MapDrawingLine.getDefault()`), et le rang qui dépasse se refuse une fois, sur son champ.
	 */
	public function testDrawOutOfItsGardenIsRefusedOnce(): void {

		$ePlot = new Plot([
			'structure' => $this->eStructure,
			'zoneFill' => FALSE,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
			'zone' => new Zone(['coordinates' => self::ZONE, 'seasonFirst' => NULL, 'seasonLast' => NULL]),
			'coordinates' => self::GARDEN
		]);

		$cBed = new \Collection();
		$draw = ['corner' => [], 'angle' => []];

		foreach(range(0, 199) as $position) {
			$cBed[$position] = new Bed(['length' => 20, 'width' => 80]);
			$draw['corner'][$position] = json_encode(MapLib::getDestination([3.0996, 45.6004], $position * 1.3, 90));
			$draw['angle'][$position] = M_PI / 2;
		}

		$this->assertEquals(['Draw::angle.inside bedDraw[1]'], $this->buildDraw($ePlot, json_encode($draw), $cBed), 'un seul refus pour le rang qui sort du jardin');

	}

	public function testUnreadableDrawIsRefused(): void {

		$ePlot = new Plot([
			'structure' => $this->eStructure,
			'zoneFill' => FALSE,
			'zone' => new Zone(['coordinates' => self::ZONE]),
			'coordinates' => self::GARDEN
		]);

		$this->assertEquals(['Bed::draw.invalid bedDraw[1]'], $this->buildDraw($ePlot, '{"corner":'), 'un rang illisible est refusé sur son champ');

	}

}
?>
