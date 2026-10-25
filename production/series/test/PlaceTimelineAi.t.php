<?php
namespace series;

/**
 * Les lignes de la vue Liste de l'assolement.
 *
 * Signalé sur la ferme démo (séries 3233846 et 3233847, saison 2027) : deux séries de
 * l'automne précédent, semées en s39 et récoltées en s49 sur la même planche, se
 * superposaient sur une seule ligne. getWeeksShown() codait la première semaine affichée
 * en « 0 - semaine » alors que positionPlace() code la saison précédente en
 * « semaine - 100 » : les séries de l'automne précédent étaient jugées hors écran, et
 * getTimelineLines() range toutes les séries hors écran sur la première ligne.
 *
 * Le test est pur : les séries sont construites en mémoire, seule la configuration de
 * la structure est lue en base puis surchargée le temps du test.
 *
 * Lancement : dev/test package=series file=PlaceTimelineAi
 */
class PlaceTimelineAiTest extends \Test {

	private const SEASON = 2027;

	private \structure\Structure $eStructure;
	private array $calendar;

	public function setUp(): void {

		$this->eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

		$eConfiguration = $this->eStructure->conf(\map\Configuration::class);
		$this->calendar = [$eConfiguration['calendarMonthStart'], $eConfiguration['calendarMonthStop']];

		// Octobre de l'année précédente, comme la ferme démo
		$eConfiguration['calendarMonthStart'] = 10;
		$eConfiguration['calendarMonthStop'] = NULL;

	}

	public function tearDown(): void {

		$eConfiguration = $this->eStructure->conf(\map\Configuration::class);
		[$eConfiguration['calendarMonthStart'], $eConfiguration['calendarMonthStop']] = $this->calendar;

	}

	private function place(int $id, int $start, int $stop): Place {

		return new Place([
			'id' => $id,
			'series' => new Series([
				'id' => $id,
				'season' => self::SEASON,
				'cycle' => Series::ANNUAL,
				'perennialSeason' => NULL,
				'perennialLifetime' => NULL,
				'bedStartCalculated' => $start,
				'bedStopCalculated' => $stop,
			]),
			'task' => new Task(),
		]);

	}

	private function lines(Place ...$places): array {

		$cPlace = new \Collection();

		foreach($places as $ePlace) {
			$cPlace[$ePlace['id']] = $ePlace;
		}

		return array_map(
			fn(array $line) => array_map(fn(Place $ePlace) => $ePlace['id'], $line),
			new PlaceUi()->getTimelineLines($this->eStructure, $cPlace, self::SEASON)
		);

	}

	public function testFirstWeekShownIsCodedLikeThePreviousSeason(): void {

		[$firstWeekShown] = new PlaceUi()->getWeeksShown($this->eStructure, self::SEASON);

		$week = (int)date('W', strtotime((self::SEASON - 1).'-10-01'));

		$this->assertEquals($week - 100, $firstWeekShown, 'la semaine du 1er octobre de l\'année précédente, codée comme positionPlace() code la saison précédente');

	}

	public function testTwoSeriesOfThePreviousAutumnOnTheSameBedGetTwoLines(): void {

		$lines = $this->lines(
			$this->place(1, -61, -51),
			$this->place(2, -61, -51),
		);

		$this->assertEquals([[1], [2]], $lines, 'deux séries s39 à s49 de l\'année précédente se partagent la planche, chacune sur sa ligne');

	}

	public function testASeriesOfThePreviousSummerStaysOutOfTheLines(): void {

		// s20 à s30 de l'année précédente, avant le 1er octobre : hors écran, elle ne
		// doit pas pousser une série affichée sur une seconde ligne
		$lines = $this->lines(
			$this->place(1, -80, -70),
			$this->place(2, -61, -51),
		);

		$this->assertEquals([[1, 2]], $lines, 'une série hors écran ne réserve pas de ligne');

	}

	public function testASeriesEndingJustBeforeTheWindowDoesNotPushTheNextOne(): void {

		// Cas de la structure 772, planche 53695 : une carotte s12 à s39 de l'année
		// précédente, hors écran d'une semaine, et un chou s27 à s52 qui la chevauche.
		// Le chou se comparait à la carotte invisible et partait seul sur une seconde
		// ligne, laissant la première vide.
		$lines = $this->lines(
			$this->place(1, -88, -61),
			$this->place(2, -73, -48),
		);

		$this->assertEquals([[1, 2]], $lines, 'une série hors écran ne décide pas de la ligne de la suivante');

	}

}
?>
