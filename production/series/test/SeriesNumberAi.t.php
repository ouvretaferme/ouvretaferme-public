<?php
namespace series;

/**
 * « Numéroter » dans la barre des séries sélectionnées : le numéro s'écrit en fin de `Series.name`.
 *
 * Le panneau regroupe la sélection par nom dénudé de son numéro (`SeriesLib::getBareName()`, qui
 * reconnaît les formes « X », « #X » et « n°X » en fin de nom et ne touche jamais le reste), puis
 * range chaque groupe par semaine de mise en place (`Cultivation.startWeek`), une série sans semaine
 * fermant son groupe. Le compteur repart à 1 sur chaque groupe de la sélection, et le reste du plan
 * n'est pas regardé. Le nom proposé reste un champ libre : l'écriture reprend ce que chaque ligne
 * poste (`name[<id>]`) et le passe par `build()`, qui refuse un nom vide comme ailleurs. Une série
 * close se renumérote comme les autres (décidé le 24/09) : le geste ne pose pas
 * `acceptUpdate()`, qui fige le reste d'une série clôturée.
 *
 * Lancement : dev/test package=series file=SeriesNumberAi
 */
class SeriesNumberAiTest extends SeriesFixtureAiTest {

	public function testTheNumberIsRecognizedOnlyAtTheEndOfTheName(): void {

		$this->assertEquals('Carotte', SeriesLib::getBareName('Carotte 3'));
		$this->assertEquals('Carotte', SeriesLib::getBareName('Carotte #12'));
		$this->assertEquals('Carotte', SeriesLib::getBareName('Carotte n°4'));
		$this->assertEquals('Carotte', SeriesLib::getBareName('Carotte N° 4'));
		$this->assertEquals('Carotte', SeriesLib::getBareName('Carotte'));
		$this->assertEquals('Maïs + Tournesol', SeriesLib::getBareName('Maïs + Tournesol'));
		$this->assertEquals('Carotte 7 (conservation)', SeriesLib::getBareName('Carotte 7 (conservation)'), 'un nombre au milieu du nom reste où il est');
		$this->assertEquals('Radis3', SeriesLib::getBareName('Radis3'), 'un nombre collé au mot fait partie du nom');
		$this->assertEquals('3', SeriesLib::getBareName('3'), 'un nom fait d\'un seul nombre ne se vide pas');

	}

	public function testTheSelectionIsGroupedByBareNameAndOrderedByStartWeek(): void {

		$eSeriesLate = $this->createSeriesStarting('Radis 2', 30);
		$eSeriesEarly = $this->createSeriesStarting('Radis', 20);
		$eSeriesUnplanned = $this->createSeriesStarting('Radis #1', NULL);
		$eSeriesCabbage = $this->createSeriesStarting('Chou 1', 10);

		$cSeries = Series::model()
			->select(Series::getSelection())
			->whereId('IN', [$eSeriesLate['id'], $eSeriesEarly['id'], $eSeriesUnplanned['id'], $eSeriesCabbage['id']])
			->getCollection(NULL, NULL, 'id');

		$numbering = SeriesLib::getNumbering($cSeries);

		$this->assertEquals(['Chou', 'Radis'], array_keys($numbering), 'un groupe par nom dénudé, rangés par nom');
		$this->assertEquals([$eSeriesCabbage['id']], $numbering['Chou']->getIds());
		$this->assertEquals([$eSeriesEarly['id'], $eSeriesLate['id'], $eSeriesUnplanned['id']], $numbering['Radis']->getIds(), 'l\'ordre est la mise en place, une série sans semaine en dernier');

	}

	public function testEachSeriesTakesTheNamePostedOnItsLine(): void {

		$eSeriesFirst = $this->createSeries('Radis');
		$eSeriesSecond = $this->createSeries('Radis');

		$cSeries = Series::model()
			->select(Series::getSelection())
			->whereId('IN', [$eSeriesFirst['id'], $eSeriesSecond['id']])
			->getCollection(NULL, NULL, 'id');

		$input = [
			'name' => [
				$eSeriesFirst['id'] => 'Radis 1',
				$eSeriesSecond['id'] => 'Radis (tunnel)',
			]
		];

		$fw = new \FailWatch();

		foreach($cSeries as $eSeries) {
			$eSeries->buildIndex(['name'], $input, $eSeries['id']);
		}

		$this->assertTrue($fw->ok());

		SeriesLib::updateNameCollection($cSeries);

		$this->assertEquals(
			['Radis 1', 'Radis (tunnel)'],
			Series::model()->select(['id', 'name'])->whereId('IN', [$eSeriesFirst['id'], $eSeriesSecond['id']])->sort('id')->getCollection()->getColumn('name')
		);

	}

	public function testAClosedSeriesIsRenumberedToo(): void {

		$eSeries = $this->createSeries('Radis');
		$this->closeSeries($eSeries);

		$eSeries = Series::model()->select(Series::getSelection())->whereId($eSeries['id'])->get();

		$fw = new \FailWatch();
		$eSeries->buildIndex(['name'], ['name' => [$eSeries['id'] => 'Radis 4']], $eSeries['id']);

		$this->assertTrue($fw->ok(), 'une série close se renumérote, sa clôture ne fige pas son nom');

		SeriesLib::updateNameCollection(new \Collection([$eSeries['id'] => $eSeries]));

		$eSeriesRead = Series::model()->select(['name', 'status'])->whereId($eSeries['id'])->get();

		$this->assertEquals('Radis 4', $eSeriesRead['name']);
		$this->assertEquals(Series::CLOSED, $eSeriesRead['status'], 'et reste close');

	}

	public function testAnEmptyNameIsRefused(): void {

		$eSeries = $this->createSeries('Radis');

		$fw = new \FailWatch();
		$eSeries->buildIndex(['name'], ['name' => [$eSeries['id'] => '']], $eSeries['id']);

		$this->assertEquals(['Series::name.check'], array_map(fn($fail) => $fail[1], $fw->get()));

	}

	private function createSeriesStarting(string $name, ?int $startWeek): Series {

		$eSeries = $this->createSeries($name);

		if($startWeek !== NULL) {

			$eCultivation = $this->createCultivation($eSeries);

			// Le décor pose la semaine sans passer par les interventions qui la calculent à l'écran.
			Cultivation::model()->update($eCultivation, ['startWeek' => $startWeek]);

		}

		return $eSeries;

	}

}
