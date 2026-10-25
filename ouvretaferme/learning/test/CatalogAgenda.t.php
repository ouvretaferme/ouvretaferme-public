<?php
namespace learning;

require_once __DIR__.'/LearningDateFixture.t.php';

/**
 * L'onglet Prochaines dates du catalogue public (lot catalogue-attractif, planche v3) : l'agenda
 * des formations datées, par mois, jusqu'à la fin du mois suivant.
 *
 * L'horloge est figée en octobre 2035 : les dates de la base de dev, toutes antérieures, n'y sont
 * plus à venir, et l'agenda ne montre que celles du test. Les formations et leurs dates s'écrivent
 * dans la transaction du test.
 */
class CatalogAgendaTest extends LearningDateFixtureTest {

	const NOW = '2035-10-05 12:00:00';

	public function setUp(): void {

		parent::setUp();

		$this->setClock(self::NOW);

	}

	/**
	 * Sans date à venir au catalogue, l'onglet ne paraît pas, ni la barre avec lui : la page est la
	 * vue Formations, et la loupe revient dans la tête. `tab=dates` dans l'adresse n'y change rien.
	 */
	public function testNoDates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->executeView('formations', ['tab' => 'dates']);

		if($this->assertDecorPresent($data->catalog > 0, 'un catalogue non vide') === FALSE) {
			return;
		}

		$this->assertFalse($data->hasAgenda, 'aucune date à venir');
		$this->assertEquals('formations', $data->tab, 'la page reste sur les formations');

		$html = $this->renderRoute('formations', ['tab' => 'dates']);

		$this->assertTrue(str_contains($html, 'Lime.Search.toggle(&quot;#learning-catalog-search&quot;)'), 'la loupe dans la tête : '.$html);

	}

	/**
	 * Une date à venir fait paraître l'onglet, et son compteur compte les dates, pas les formations :
	 * une formation à deux dates en vaut deux. Une formation en ligne n'y entre pas, elle reste au
	 * compteur des formations.
	 */
	public function testCounters(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseRemote = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::REMOTE);
		$eCourseOnline = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);

		$this->createDate($eCourseRemote, 15);
		$this->createDate($eCourseRemote, 30);

		$data = $this->executeView('formations', ['tab' => 'dates']);

		$this->assertTrue($data->hasAgenda, 'l\'onglet paraît');
		$this->assertEquals('dates', $data->tab, 'et s\'ouvre');
		$this->assertEquals(2, $data->dates, 'deux dates pour une formation');
		$this->assertEquals($data->catalog, $data->courses, 'le compteur des formations compte tout le catalogue, celle en ligne comprise');

		$courses = [];

		foreach($data->ccDateMonth as $cDate) {
			foreach($cDate as $eDate) {
				$courses[] = $eDate['course']['id'];
			}
		}

		$this->assertEquals([$eCourseRemote['id'], $eCourseRemote['id']], $courses, 'une ligne par date, aucune pour la formation en ligne '.$eCourseOnline['id']);

	}

	/**
	 * L'agenda s'arrête à la fin du mois suivant, rangé par mois, puis « Voir l'autre date » l'ouvre en
	 * entier. Quand rien ne tombe avant la borne, il montre tout plutôt qu'un onglet vide.
	 */
	public function testBound(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::REMOTE);

		$eDateDecember = $this->createDate($eCourse, 59);
		$this->assertEquals('2035-12-03', substr($eDateDecember['startAt'], 0, 10), 'le décor : une date après la borne');

		$data = $this->executeView('formations', ['tab' => 'dates']);

		$this->assertEquals(['2035-12'], $data->ccDateMonth->getKeys(), 'rien avant la borne : tout l\'agenda');
		$this->assertEquals(0, $data->datesRemaining, 'sans bouton');

		$this->createDate($eCourse, 15);
		$this->createDate($eCourse, 54);

		$data = $this->executeView('formations', ['tab' => 'dates']);

		$this->assertEquals(['2035-10', '2035-11'], $data->ccDateMonth->getKeys(), 'octobre et novembre, rangés par mois');
		$this->assertEquals(1, $data->datesRemaining, 'décembre reste derrière le bouton');

		$html = new CatalogUi()->getAgenda($data->ccDateMonth, $data->dates, $data->datesRemaining, $data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected);

		$this->assertTrue(str_contains($html, 'href="/formations?tab=dates&amp;all=1"') or str_contains($html, 'href="/formations?tab=dates&all=1"'), 'le bouton ouvre l\'agenda en entier : '.$html);

		$data = $this->executeView('formations', ['tab' => 'dates', 'all' => '1']);

		$this->assertEquals(['2035-10', '2035-11', '2035-12'], $data->ccDateMonth->getKeys(), 'l\'agenda en entier');
		$this->assertEquals(0, $data->datesRemaining, 'plus rien derrière');

	}

	/**
	 * Les filtres valent pour les deux onglets : posés, ils gardent l'onglet, et l'onglet garde les
	 * filtres. Un filtre qui écarte toutes les dates laisse la barre, le compteur à zéro.
	 */
	public function testFilters(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, Course::ACCEPTED, type: Course::REMOTE);
		$this->createDate($eCourse, 15);

		$cLevelOther = LevelLib::getAll()->find(fn(Level $eLevel) => $eLevel['id'] !== $this->eLevel['id']);

		if($this->assertDecorPresent($cLevelOther->notEmpty(), 'un second niveau') === FALSE) {
			return;
		}

		$eLevelOther = $cLevelOther->first();

		$data = $this->executeView('formations', ['tab' => 'dates', 'level' => $eLevelOther['id']]);

		$this->assertTrue($data->hasAgenda, 'la barre reste');
		$this->assertEquals('dates', $data->tab, 'sur l\'onglet des dates');
		$this->assertEquals(0, $data->dates, 'aucune date à ce niveau');

		$ui = new CatalogUi();

		$tabs = $ui->getTabs($data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected, $data->tab, $data->courses, $data->dates);
		$search = $ui->getSearch($data->cTheme, $data->cSubtheme, $data->cLevel, $data->eThemeSelected, $data->eSubthemeSelected, $data->eLevelSelected, $data->tab, $data->isFiltered);

		$this->assertTrue(str_contains($tabs, 'href="/formations?level='.$eLevelOther['id'].'"'), 'l\'onglet Formations garde le filtre : '.$tabs);
		$this->assertTrue(str_contains($tabs, 'href="/formations?tab=dates&amp;level='.$eLevelOther['id'].'"') or str_contains($tabs, 'href="/formations?tab=dates&level='.$eLevelOther['id'].'"'), 'l\'onglet ouvert aussi');
		$this->assertTrue(str_contains($search, 'name="tab" type="hidden" value="dates"'), 'le formulaire garde l\'onglet : '.$search);
		$this->assertFalse(str_contains($search, 'util-block-search hide'), 'un filtre posé ouvre les filtres');

	}

	/** Une date à cheval sur deux années garde l'année, que le titre du mois ne dirait qu'à moitié. */
	public function testDaysAcrossYears(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eDate = new Date(['startAt' => '2035-12-31 09:00:00', 'endAt' => '2036-01-02 17:00:00']);

		$this->assertEquals(DateUi::getDays($eDate), DateUi::getDays($eDate, withYear: FALSE), 'les deux années restent');

		$eDate = new Date(['startAt' => '2035-11-17 09:00:00', 'endAt' => '2035-11-18 17:00:00']);

		$this->assertEquals('Du samedi 17 au dimanche 18 novembre', DateUi::getDays($eDate, withYear: FALSE), 'sans l\'année dans l\'agenda');

	}

}
?>
