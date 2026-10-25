<?php
namespace map;

/**
 * Un jardin montre, pour une saison, les planches de la dernière saison tracée jusqu'à elle. Poser ou effacer une planche dans une
 * saison qui hérite d'une saison antérieure ne doit pas y faire disparaitre les autres : `DrawLib`, par où passe toute écriture du
 * plan, recopie d'abord les lignes héritées. Une saison vidée garde une marque, une ligne sans planche, quand elle hérite de planches.
 *
 * Le décor est monté par les Lib dans la transaction de la méthode, sur `FixtureAiLib::F1` : les tables du
 * plan vivent dans la base commune.
 *
 * Lancement : dev/test package=map file=DrawSeason
 */
class DrawSeasonTest extends \dev\ApiTest {

	const int SEASON = 2026;

	const array SQUARE = [[3.0990, 45.5990], [3.1010, 45.5990], [3.1010, 45.6010], [3.0990, 45.6010], [3.0990, 45.5990]];

	const array ROW_START = [3.0995, 45.5995];

	// Ce que getRows() écrit pour la marque d'une saison effacée
	const string MARK = '-';

	protected function getTransactionModel(): \ModuleModel {
		return new PlotModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un jardin portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	private function createPlot(): Plot {

		$eZone = new Zone([
			'name' => 'Parcelle de saison '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => self::SQUARE,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : le carré est fixe, et le cas ne lit que sa présence
		ZoneLib::create($eZone);

		$ePlot = new Plot([
			'name' => 'Jardin de saison',
			'zone' => $eZone,
			'mode' => Plot::OPEN_FIELD,
			'area' => 100,
			'coordinates' => self::SQUARE,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait le polygone posté et sa place dans la parcelle : le carré est fixe, et le cas ne lit que sa présence
		PlotLib::create($ePlot);

		return $ePlot;

	}

	// Les quatre coins du rectangle d'une ligne : la largeur part à l'angle, la longueur à l'angle + 90°
	private function getCorners(Draw $eDraw, Bed $eBed): array {

		$angle = rad2deg($eDraw['angle']);
		$across = MapLib::getDestination($eDraw['corner'], $eBed['width'] / 100, $angle);

		return [
			$eDraw['corner'],
			$across,
			MapLib::getDestination($across, $eBed['length'], $angle + 90),
			MapLib::getDestination($eDraw['corner'], $eBed['length'], $angle + 90)
		];

	}

	private function createBeds(Plot $ePlot, int $number, ?int $width = 80): \Collection {

		$cBed = new \Collection();

		for($position = 1; $position <= $number; $position++) {

			$eBed = new Bed([
				'name' => 'n'.$position,
				'plot' => $ePlot,
				'zoneFill' => FALSE,
				'plotFill' => FALSE,
				'length' => 20,
				'width' => $width,
				'alleyWidth' => NULL,
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			// Une planche sans largeur porte sa surface, comme celle qui remplit un jardin : la colonne se calcule sinon des deux dimensions
			if($width === NULL) {
				$eBed['area'] = 16;
			}

			// Sans build(), qui vérifierait les bornes des dimensions postées et refuserait une planche sans largeur : le cas veut les siennes
			BedLib::create($eBed);

			$cBed[$eBed['id']] = $eBed;

		}

		return $cBed;

	}

	private function getDraw(Plot $ePlot, int $season): Draw {

		return new Draw([
			'plot' => $ePlot,
			'season' => $season
		]);

	}

	// Le mouvement d'un contour tiré d'un bloc de `$shift` degrés, comme MapLib::getMotion() le lit
	private function getMotion(array $shift): array {
		return MapLib::getMotion(self::SQUARE, array_map(fn(array $point) => [$point[0] + $shift[0], $point[1] + $shift[1]], self::SQUARE));
	}

	private function draw(Plot $ePlot, \Collection $cBed, int $season, array $start = self::ROW_START): void {

		// Sans build(), qui vérifierait la saison postée : elle est celle que le cas veut, passée comprise
		DrawLib::createByBeds($this->getDraw($ePlot, $season), $this->getPlaces($cBed, $start));

	}

	/**
	 * La place de chaque planche de `$cBed`, sous son id, dans un rang qui part de `$start` vers l'est : les planches côte à côte
	 * dans l'ordre de la collection, séparées par un passe-pied de 60 cm et longues vers le nord.
	 */
	private function getPlaces(\Collection $cBed, array $start): \Collection {

		$cDraw = new \Collection();
		$position = 0;

		foreach($cBed as $eBed) {

			$position += $eBed['width'] / 100;

			// Large vers l'ouest depuis son bout est, une planche s'allonge vers le nord, à cap + 90°
			$cDraw[$eBed['id']] = new Draw([
				'bed' => $eBed
			] + DrawLib::getCanonical(MapLib::getDestination($start, $position, 90), 270, $eBed));

			$position += 0.6;

		}

		return $cDraw;

	}

	private function findBeds(\Collection $cBed, string ...$names): \Collection {
		return $cBed->find(fn($eBed) => in_array($eBed['name'], $names));
	}

	/**
	 * Les lignes enregistrées d'un jardin pour une saison, par les noms de leurs planches dans l'ordre naturel, et MARK pour la marque
	 * d'une saison effacée : ce que la base porte, sans l'héritage que les lecteurs y ajoutent.
	 */
	private function getRows(Plot $ePlot, \Collection $cBed, int $season): array {

		$names = Draw::model()
			->select(['bed'])
			->wherePlot($ePlot)
			->whereSeason($season)
			->getCollection()
			->makeArray(fn($eDraw) => $eDraw['bed']->empty() ? self::MARK : $cBed[$eDraw['bed']['id']]['name']);

		natsort($names);

		return array_values($names);

	}

	public function testMissingBedDrawnInALaterSeasonKeepsTheInheritedOnes(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 22);
		$cBedEarlier = $cBed->find(fn($eBed) => $eBed['name'] !== 'n4');

		$this->draw($ePlot, $cBedEarlier, self::SEASON - 1);
		$this->draw($ePlot, $this->findBeds($cBed, 'n4'), self::SEASON);

		$this->assertEquals($cBedEarlier->getColumn('name'), $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la saison précédente garde ses 21 planches');
		$this->assertEquals($cBed->getColumn('name'), $this->getRows($ePlot, $cBed, self::SEASON), 'la saison reçoit la recopie des 21 planches héritées, puis la planche posée');

	}

	public function testSecondGestureDoesNotDuplicateAgain(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 4);

		$this->draw($ePlot, $this->findBeds($cBed, 'n1', 'n2', 'n3'), self::SEASON - 1);

		// Le même geste envoyé deux fois de suite : le second lit une saison déjà tracée. Les deux appels partagent la transaction du test, le verrou du jardin ne s'éprouve donc pas ici
		$this->draw($ePlot, $this->findBeds($cBed, 'n4'), self::SEASON);
		$this->draw($ePlot, $this->findBeds($cBed, 'n4'), self::SEASON);

		$this->assertEquals(['n1', 'n2', 'n3', 'n4'], $this->getRows($ePlot, $cBed, self::SEASON), 'la saison porte une seule recopie et une seule ligne par planche');

	}

	public function testDeletingABedOfAnInheritedDrawingRemovesItFromTheSeasonOnly(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $this->findBeds($cBed, 'n2'));

		$this->assertEquals(['n1', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON), 'la planche effacée d\'un tracé hérité quitte la saison, les autres y sont recopiées');
		$this->assertEquals(['n1', 'n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la saison d\'origine garde les trois');

	}

	public function testLaterSeasonAlreadyDrawnKeepsItsOwnDrawing(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $this->findBeds($cBed, 'n1'), self::SEASON + 1);
		$this->draw($ePlot, $this->findBeds($cBed, 'n2', 'n3'), self::SEASON);

		$this->assertEquals(['n1'], $this->getRows($ePlot, $cBed, self::SEASON + 1), 'un geste dans une saison ne touche pas la saison suivante, déjà tracée');
		$this->assertEquals(['n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON), 'et la saison du geste n\'en reçoit rien');

	}

	public function testPastSeasonReceivesTheDrawingItInherited(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $this->findBeds($cBed, 'n1'), self::SEASON - 2);
		$this->draw($ePlot, $this->findBeds($cBed, 'n2'), self::SEASON);

		// Un geste dans une saison passée, entre deux saisons tracées
		$this->draw($ePlot, $this->findBeds($cBed, 'n3'), self::SEASON - 1);

		$this->assertEquals(['n1', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la saison passée reçoit les planches dont elle héritait, puis la planche posée');
		$this->assertEquals(['n1'], $this->getRows($ePlot, $cBed, self::SEASON - 2), 'la saison d\'origine ne change pas');
		$this->assertEquals(['n1', 'n2'], $this->getRows($ePlot, $cBed, self::SEASON), 'la saison postérieure garde les siennes');

	}

	public function testSeasonWithoutEarlierDrawingReceivesNothing(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $this->findBeds($cBed, 'n1'), self::SEASON);

		$this->assertEquals(['n1'], $this->getRows($ePlot, $cBed, self::SEASON), 'un jardin vierge ne recopie rien');

	}

	public function testErasingEveryBedOfAnInheritedSeasonKeepsTheSeasonErased(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		$this->assertEquals([self::MARK], $this->getRows($ePlot, $cBed, self::SEASON), 'la saison garde une ligne sans planche, qui la marque tracée');
		$this->assertEquals(['n1', 'n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la saison d\'origine garde ses planches');

	}

	public function testErasedSeasonIsNotCopiedAgain(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $this->findBeds($cBed, 'n1'));

		$this->assertEquals([self::MARK], $this->getRows($ePlot, $cBed, self::SEASON), 'un second effacement ne recopie pas le tracé hérité, la saison porte déjà sa marque');

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON + 1), $this->findBeds($cBed, 'n1'));

		$this->assertEquals([], $this->getRows($ePlot, $cBed, self::SEASON + 1), 'la saison suivante ne recopie pas une marque, et n\'en reçoit pas : elle hérite d\'une saison qui ne montre rien');

		$this->draw($ePlot, $this->findBeds($cBed, 'n2'), self::SEASON);

		$this->assertEquals(['n2'], $this->getRows($ePlot, $cBed, self::SEASON), 'une planche posée dans la saison effacée y remplace la marque');

	}

	public function testErasingEveryBedWithoutEarlierSeasonLeavesNoDraw(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $cBed, self::SEASON);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		$this->assertEquals([], $this->getRows($ePlot, $cBed, self::SEASON), 'sans saison antérieure tracée, rien n\'est à cacher : la saison n\'a plus de ligne');

	}

	public function testMovingBedsRewritesOnlyTheirRows(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 5);

		$cBedFirst = $this->findBeds($cBed, 'n1', 'n2');

		$this->draw($ePlot, $cBedFirst, self::SEASON);
		$this->draw($ePlot, $this->findBeds($cBed, 'n3', 'n4', 'n5'), self::SEASON);

		$getRows = function() use($ePlot): array {

			$rows = [];

			foreach(Draw::model()
				->select(['id', 'bed', 'corner', 'angle'])
				->wherePlot($ePlot)
				->whereSeason(self::SEASON)
				->getCollection() as $eDraw) {
				$rows[$eDraw['bed']['id']] = $eDraw->extracts(['id', 'corner', 'angle']);
			}

			return $rows;

		};

		$rowsBefore = $getRows();

		// Le rang reposé ailleurs, chaque planche à sa nouvelle place
		$start = [3.0996, 45.5996];
		$this->draw($ePlot, $cBedFirst, self::SEASON, $start);

		$rowsAfter = $getRows();

		$this->assertEquals(5, count($rowsAfter), 'le jardin garde une ligne par planche');

		foreach($this->findBeds($cBed, 'n3', 'n4', 'n5') as $eBed) {
			$this->assertEquals($rowsBefore[$eBed['id']], $rowsAfter[$eBed['id']], 'la planche '.$eBed['name'].' que le geste ne porte pas garde sa ligne, son coin et son angle');
		}

		$expected = $this->getPlaces($cBedFirst, $start);

		foreach($cBedFirst as $eBed) {
			$this->assertTrue(MapLib::getDistance($rowsAfter[$eBed['id']]['corner'], $expected[$eBed['id']]['corner']) < 0.01, 'la planche '.$eBed['name'].' reposée prend le coin du nouveau rang');
		}

	}

	public function testErasingOneOfTwoInheritedRanksKeepsTheOther(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 4);

		$this->draw($ePlot, $this->findBeds($cBed, 'n1', 'n2'), self::SEASON - 1);
		$this->draw($ePlot, $this->findBeds($cBed, 'n3', 'n4'), self::SEASON - 1);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $this->findBeds($cBed, 'n1', 'n2'));

		$this->assertEquals(['n3', 'n4'], $this->getRows($ePlot, $cBed, self::SEASON), 'la saison garde les planches qui restent, sans marque : elles suffisent à ce qu\'elle ne relise pas la saison antérieure');
		$this->assertEquals(['n1', 'n2', 'n3', 'n4'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la saison d\'origine garde les quatre');

	}

	public function testMovingABedToAnotherPlotKeepsAnErasedSeasonErased(): void {

		$ePlot = $this->createPlot();
		$ePlotOther = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);

		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		// Par le Lib du plan, appelé par BedLib::update() quand une planche change de jardin : le cas lit ce qu'il laisse dans le jardin quitté
		DrawLib::deleteByBedsOutsidePlot($this->findBeds($cBed, 'n1'), $ePlotOther);

		$this->assertEquals([self::MARK], $this->getRows($ePlot, $cBed, self::SEASON), 'la marque de la saison effacée reste');
		$this->assertEquals(['n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la planche partie quitte la saison antérieure');

	}

	public function testMovingTheLastBedOfASeasonToAnotherPlotKeepsTheSeasonOnItsOwnDrawing(): void {

		$ePlot = $this->createPlot();
		$ePlotOther = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $this->findBeds($cBed, 'n2', 'n3'));

		$this->assertEquals(['n1'], $this->getRows($ePlot, $cBed, self::SEASON), 'la saison ne garde que « n1 »');

		DrawLib::deleteByBedsOutsidePlot($this->findBeds($cBed, 'n1'), $ePlotOther);

		$this->assertEquals([self::MARK], $this->getRows($ePlot, $cBed, self::SEASON), 'vidée par le départ de la planche, la saison garde une marque : « n2 » et « n3 », effacées, ne reviennent pas de la saison antérieure');
		$this->assertEquals(['n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'qui garde les siennes');

	}

	public function testDeletedBedLeavesEverySeason(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 3);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $this->findBeds($cBed, 'n2', 'n3'));

		// Sans culture, la planche se supprime pour de bon (BedLib::delete())
		BedLib::delete($this->findBeds($cBed, 'n1')->first());

		$this->assertEquals(['n2', 'n3'], $this->getRows($ePlot, $cBed, self::SEASON - 1), 'la planche supprimée quitte la saison antérieure');
		$this->assertEquals([self::MARK], $this->getRows($ePlot, $cBed, self::SEASON), 'et la saison qu\'elle était seule à occuper garde une marque');

	}

	public function testDeletedZoneLeavesNoRow(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		ZoneLib::delete($ePlot['zone']);

		$this->assertFalse(Draw::model()->wherePlot($ePlot)->exists(), 'la parcelle supprimée ne laisse aucune ligne à ses jardins, marques comprises');

	}

	public function testDeletedPlotLeavesNoRow(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		PlotLib::delete($ePlot);

		$this->assertFalse(Draw::model()->wherePlot($ePlot)->exists(), 'le jardin supprimé n\'a plus aucune ligne, marques comprises');

	}

	public function testBedWithoutWidthIsRefused(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 1, width: NULL);

		$fw = new \FailWatch();

		$this->draw($ePlot, $cBed, self::SEASON);

		$this->assertTrue($fw->has('Draw::bed.sized'), 'une planche sans largeur n\'a pas de rectangle à poser : '.json_encode($fw->get()));
		$this->assertEquals([], $this->getRows($ePlot, $cBed, self::SEASON), 'et rien n\'est écrit');

	}

	public function testMoveMovesCornersAndLeavesMarks(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		$getCorners = fn() => Draw::model()
			->select(['id', 'corner'])
			->wherePlot($ePlot)
			->getCollection()
			->makeArray(fn($eDraw) => $eDraw['corner']);

		$cornersBefore = $getCorners();

		// Par le jardin, comme PlotLib::update() quand son contour se déplace
		$ePlot['structure'] = $this->eStructure;
		DrawLib::moveByPlot($ePlot, $this->getMotion([0.001, 0.0005]));

		foreach($getCorners() as $id => $corner) {

			if($cornersBefore[$id] === NULL) {
				$this->assertEquals(NULL, $corner, 'la marque n\'a pas de coin, et n\'en reçoit pas');
			} else {
				// Au millionième de degré près, celui du `point` relu en base
				$this->assertTrue(abs($cornersBefore[$id][0] + 0.001 - $corner[0]) < 0.000001 and abs($cornersBefore[$id][1] + 0.0005 - $corner[1]) < 0.000001, 'le coin de chaque planche suit le décalage');
			}

		}

	}

	public function testMoveByZoneMovesOnlyItsOwnRows(): void {

		$ePlot = $this->createPlot();
		$ePlotOther = $this->createPlot();

		$this->draw($ePlot, $this->createBeds($ePlot, 2), self::SEASON);
		$this->draw($ePlotOther, $this->createBeds($ePlotOther, 2), self::SEASON);

		$getCorners = fn(Plot $ePlot) => Draw::model()
			->select(['id', 'corner'])
			->wherePlot($ePlot)
			->getCollection()
			->makeArray(fn($eDraw) => $eDraw['corner']);

		$cornersBefore = $getCorners($ePlot);
		$cornersOtherBefore = $getCorners($ePlotOther);

		// Par la parcelle, comme ZoneLib::update() quand son contour se déplace
		DrawLib::moveByZone(new Zone(['id' => $ePlot['zone']['id'], 'structure' => $this->eStructure]), $this->getMotion([0.001, 0.0005]));

		foreach($getCorners($ePlot) as $id => $corner) {
			$this->assertTrue(abs($cornersBefore[$id][0] + 0.001 - $corner[0]) < 0.000001 and abs($cornersBefore[$id][1] + 0.0005 - $corner[1]) < 0.000001, 'le coin de chaque planche de la parcelle suit le décalage');
		}

		$this->assertEquals($cornersOtherBefore, $getCorners($ePlotOther), 'les planches d\'une autre parcelle restent en place');

	}

	public function testDuplicatedPlotTurnsCornersAndAngles(): void {

		$ePlot = $this->createPlot();
		$cBed = $this->createBeds($ePlot, 2);

		$this->draw($ePlot, $cBed, self::SEASON - 1);
		DrawLib::deleteByBeds($this->getDraw($ePlot, self::SEASON), $cBed);

		$ePlotCopy = $this->createPlot();
		$ePlotCopy['structure'] = $this->eStructure;
		// Un angle quelconque : un demi-tour rendrait le même résultat dans un sens ou dans l'autre
		$ePlotCopy['placement'] = ['origin' => self::ROW_START, 'shift' => [0, 0], 'angle' => 37];

		$cBedCopy = new \Collection();

		foreach($cBed as $eBed) {
			$cBedCopy[$eBed['id']] = $this->createBeds($ePlotCopy, 1)->first();
		}

		DrawLib::duplicateByPlot($ePlot, $ePlotCopy, $cBedCopy);

		$this->assertEquals([self::MARK], $this->getRows($ePlotCopy, $cBedCopy, self::SEASON), 'la marque de la saison effacée se recopie');

		$getRows = function(Plot $ePlot): array {

			$rows = [];

			foreach(Draw::model()
				->select(['bed', 'corner', 'angle'])
				->wherePlot($ePlot)
				->whereSeason(self::SEASON - 1)
				->getCollection() as $eDraw) {
				$rows[$eDraw['bed']['id']] = $eDraw;
			}

			return $rows;

		};

		$rowsSource = $getRows($ePlot);
		$rowsCopy = $getRows($ePlotCopy);

		$this->assertEquals(2, count($rowsCopy), 'chaque planche se recopie sous sa copie');

		foreach($cBed as $bed => $eBed) {

			$eDrawSource = $rowsSource[$bed];
			$eDrawCopy = $rowsCopy[$cBedCopy[$bed]['id']];

			$this->assertTrue($eDrawCopy['angle'] >= 0 and $eDrawCopy['angle'] < M_PI, 'l\'angle de la copie est ramené à [0, π[');

			// La copie couvre l'image de la planche tournée, à 1 cm près, quel que soit le coin d'où elle part
			$cornersExpected = MapLib::getTransformed($this->getCorners($eDrawSource, $eBed), self::ROW_START, [0, 0], 37);

			foreach($this->getCorners($eDrawCopy, $cBedCopy[$bed]) as $corner) {
				$distance = min(array_map(fn(array $cornerExpected) => MapLib::getDistance($corner, $cornerExpected), $cornersExpected));
				$this->assertTrue($distance < 0.01, 'chaque coin de la copie est un coin de l\'image de la planche ('.round($distance, 3).' m)');
			}

		}

	}

}
?>
