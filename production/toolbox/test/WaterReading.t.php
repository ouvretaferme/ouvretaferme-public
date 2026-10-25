<?php
namespace toolbox;

/**
 * Les compteurs d'eau et leurs relevés : ce qui se consomme d'un relevé à l'autre, le compteur qui
 * fait le tour (arbitrage Q2 C de la planche de l'eau), le compteur qu'on remplace, les chiffres de
 * l'année, et ce que `build()` refuse.
 *
 * Isolation : une transaction par méthode, annulée par `TransactionTest`. Les compteurs se posent sur une
 * structure qui n'en porte aucun, pour que `getMeters()` ne lise que ceux du test. L'heure est figée
 * au 2 octobre 2026 : les chiffres de l'année se lisent sur des dates écrites en clair.
 *
 * Lancement : dev/test package=toolbox file=WaterReading
 */
class WaterReadingTest extends \dev\TransactionTest {

	private \user\User $eUserInitial;

	/**
	 * `createdBy` lit le compte connecté : le premier compte de la base l'est le temps des tests.
	 */
	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		\user\ConnectionLib::setOnline(\user\User::model()
			->select('id')
			->sort(['id' => SORT_ASC])
			->get());

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	protected function getTransactionModel(): \ModuleModel {
		return WaterReading::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->setClock('2026-10-02 10:00:00');

	}

	/**
	 * Les deux premières structures sans compteur, actif ou non : la seconde sert de structure voisine.
	 */
	private static function getStructures(): array {

		$structures = WaterMeter::model()
			->select('structure')
			->getCollection()
			->getColumnCollection('structure')
			->getIds();

		$ids = \structure\Structure::model()
			->select('id')
			->whereId('NOT IN', $structures, if: $structures)
			->whereStatus(\structure\Structure::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2)
			->getIds();

		return array_map(fn(int $id) => \structure\StructureLib::getById($id), $ids);

	}

	private function place(\structure\Structure $eStructure, string $name, int $digits = 5): WaterMeter {

		// Pas de `build()` : le décor veut exactement ce nom et ce nombre de chiffres, que
		// `testMeterBuild()` éprouve par ailleurs.
		$eWaterMeter = new WaterMeter([
			'structure' => $eStructure,
			'name' => $name,
			'digits' => $digits
		]);

		WaterMeterLib::create($eWaterMeter);

		$eWaterMeter['status'] = WaterMeter::ACTIVE;

		return $eWaterMeter;

	}

	private function read(WaterMeter $eWaterMeter, string $date, float $value): WaterReading {

		// Pas de `build()` : un relevé du décor porte une date et une valeur choisies, dont
		// `testReadingBuild()` éprouve les refus.
		$eWaterReading = new WaterReading([
			'structure' => $eWaterMeter['structure'],
			'meter' => $eWaterMeter,
			'date' => $date,
			'value' => $value
		]);

		WaterReadingLib::create($eWaterReading);

		return $eWaterReading;

	}

	/**
	 * Remplace le compteur au jour `$date` : `$changedValue` à la dépose de l'ancien, `$value` à la
	 * pose du nouveau, qui affiche `$digits` chiffres.
	 */
	private function change(WaterMeter $eWaterMeter, string $date, ?float $changedValue, float $value, int $digits): WaterReading {

		// Pas de `build()` : le décor veut exactement ces deux relevés et ce nombre de chiffres, dont
		// `testChangeBuild()` éprouve les refus.
		$eWaterMeter['digits'] = $digits;

		$eWaterReading = new WaterReading([
			'structure' => $eWaterMeter['structure'],
			'meter' => $eWaterMeter,
			'date' => $date,
			'changedValue' => $changedValue,
			'value' => $value
		]);

		WaterReadingLib::createChange($eWaterReading);

		return $eWaterReading;

	}

	private function buildsChange(WaterMeter $eWaterMeter, array $input): array {

		$fw = new \FailWatch();

		$eWaterMeter->build(WaterMeterLib::getPropertiesUpdate('change'), $input, new \PropertyList('update', 'change'));

		$eWaterReading = new WaterReading([
			'structure' => $eWaterMeter['structure'],
			'meter' => $eWaterMeter
		]);

		$eWaterReading->build(WaterReadingLib::getPropertiesCreate('change'), $input, new \PropertyList('create', 'change'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function buildsReading(WaterReading $eWaterReading, array $input): array {

		$fw = new \FailWatch();
		$eWaterReading->build(WaterReadingLib::getPropertiesCreate(), $input, new \PropertyList('create'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function buildsMeter(WaterMeter $eWaterMeter, array $properties, array $input, string $for): array {

		$fw = new \FailWatch();
		$eWaterMeter->build($properties, $input, new \PropertyList($for));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	/** `Collection::getColumn()` écarte les valeurs nulles, qui sont ici ce qu'on vérifie. */
	private function column(\Collection $c, string $property): array {

		$values = [];

		foreach($c as $e) {
			$values[] = $e[$property];
		}

		return $values;

	}

	private function getMeter(\structure\Structure $eStructure, WaterMeter $eWaterMeter): WaterMeter {
		return WaterReadingLib::getMeters($eStructure)[$eWaterMeter['id']];
	}

	public function testConsumptionIsReadBetweenTwoReadings(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2026-09-15', 12386);
		$this->read($eWaterMeter, '2026-09-01', 12274);
		$this->read($eWaterMeter, '2026-09-29', 12482.5);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);
		$cWaterReading = $eWaterMeter['cWaterReading'];

		$this->assertEquals(['2026-09-01', '2026-09-15', '2026-09-29'], $cWaterReading->getColumn('date'), 'les relevés se lisent du plus ancien au plus récent');
		$this->assertEquals([NULL, 112.0, 96.5], $this->column($cWaterReading, 'consumption'), 'le premier relevé n\'a rien consommé, les suivants l\'écart avec celui d\'avant');
		$this->assertEquals([FALSE, FALSE, FALSE], $this->column($cWaterReading, 'turned'));
		$this->assertEquals([0.0, 112.0, 208.5], $this->column($cWaterReading, 'cumulative'), 'le cumul part du premier relevé');
		$this->assertEquals(12482.5, $eWaterMeter['eWaterReadingLast']['value']);
		$this->assertEquals(12386.0, $eWaterMeter['eWaterReadingPrevious']['value']);

	}

	public function testMeterWithoutReading(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->getMeter($eStructure, $this->place($eStructure, 'Forage'));

		$this->assertTrue($eWaterMeter['cWaterReading']->empty(), 'un compteur existe avant son premier relevé');
		$this->assertTrue($eWaterMeter['eWaterReadingLast']->empty());
		$this->assertEquals(NULL, $eWaterMeter['year']);

		$this->read($eWaterMeter, '2026-09-29', 40);
		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertTrue($eWaterMeter['eWaterReadingPrevious']->empty(), 'un seul relevé n\'a pas de précédent');
		$this->assertEquals([NULL, NULL, NULL], [$eWaterMeter['year'], $eWaterMeter['yearSince'], $eWaterMeter['yearPrevious']], 'un seul relevé ne dit rien de l\'année');

	}

	/**
	 * Un écart de plus de la moitié de ce que le compteur peut afficher se signale (retour du
	 * 03/10, 5 000 m³ sur quatre chiffres), qu'il vienne d'un tour (1 000 puis 50) ou d'un relevé qui
	 * monte trop. Il reste compté : on le suggère, on ne réécrit rien. Un jour de changement compte
	 * jusqu'à la dépose, qui peut aussi s'éloigner trop du relevé d'avant.
	 */
	public function testGapAlert(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Puits', 4);

		$this->read($eWaterMeter, '2026-09-01', 1000);
		$eWaterReadingTurned = $this->read($eWaterMeter, '2026-09-02', 50);
		$eWaterReadingHigh = $this->read($eWaterMeter, '2026-09-10', 5050.1);
		$eWaterReadingUnder = $this->read($eWaterMeter, '2026-09-20', 50.1);

		$this->assertEquals(['consumption' => 9050.0, 'date' => '2026-09-01', 'turned' => TRUE, 'alert' => TRUE], WaterReadingLib::getGap($eWaterReadingTurned), 'le tour de 9 050 m³ est signalé');
		$this->assertTrue(WaterReadingLib::getGap($eWaterReadingHigh)['alert'], 'un relevé qui monte de 5 000,1 m³ est signalé');
		$this->assertEquals(['consumption' => 5000.0, 'date' => '2026-09-10', 'turned' => TRUE, 'alert' => FALSE], WaterReadingLib::getGap($eWaterReadingUnder), '5 000 m³ tout rond ne dépasse pas le seuil');

		$this->assertEquals(500000.0, WaterReadingLib::getAlertLimit(6), 'le seuil suit les chiffres du compteur');

		$eWaterReadingChange = $this->change($eWaterMeter, '2026-09-25', 9000, 0, 5);

		$cWaterReading = $this->getMeter($eStructure, $eWaterMeter)['cWaterReading'];

		$this->assertEquals([FALSE, TRUE, TRUE, FALSE, TRUE], $this->column($cWaterReading, 'alert'), 'la dépose trop loin du relevé d\'avant est signalée aussi');
		$this->assertEquals([5000.0, 5000.0, 5000.0, 5000.0, 5000.0], $this->column($cWaterReading, 'alertLimit'), 'le jour du changement se mesure encore sur les quatre chiffres de l\'ancien');
		$this->assertEquals(9050.0, $cWaterReading[1]['consumption'], 'l\'écart reste compté tant qu\'il n\'est pas corrigé');

		$this->assertEquals($eWaterReadingTurned['id'], WaterReadingLib::getAlertable($eWaterMeter, $eWaterReadingTurned['id'])['id'], 'le relevé signalé préremplit le nouveau compteur');
		$this->assertTrue(WaterReadingLib::getAlertable($eWaterMeter, $eWaterReadingChange['id'])->empty(), 'pas un jour qui est déjà un changement');
		$this->assertTrue(WaterReadingLib::getAlertable($this->place($eStructure, 'Forage'), $eWaterReadingTurned['id'])->empty(), 'ni le relevé d\'un autre compteur');
		$this->assertTrue(WaterReadingLib::getAlertable($eWaterMeter, 0)->empty(), 'ni rien sans relevé désigné');

	}

	/**
	 * Le relevé plus bas que le précédent s'enregistre, et compte ce que le compteur a compté en
	 * passant par zéro : 10 000 − 9 987 + 14 sur un compteur à quatre chiffres.
	 */
	public function testMeterTurns(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Réseau', 4);

		$eWaterReadingFirst = $this->read($eWaterMeter, '2026-08-01', 9952);
		$eWaterReadingHigher = $this->read($eWaterMeter, '2026-09-02', 9987);
		$eWaterReadingTurned = $this->read($eWaterMeter, '2026-10-01', 14);

		$this->assertEquals([], WaterReadingLib::getGap($eWaterReadingFirst), 'le premier relevé n\'a pas d\'écart');
		$this->assertEquals(['consumption' => 35.0, 'date' => '2026-08-01', 'turned' => FALSE, 'alert' => FALSE], WaterReadingLib::getGap($eWaterReadingHigher), 'un relevé plus haut que le précédent n\'est pas un tour');
		$this->assertEquals(['consumption' => 27.0, 'date' => '2026-09-02', 'turned' => TRUE, 'alert' => FALSE], WaterReadingLib::getGap($eWaterReadingTurned), 'le tour dit ce qui a été compté et depuis quand');

		$cWaterReading = $this->getMeter($eStructure, $eWaterMeter)['cWaterReading'];

		$this->assertEquals([FALSE, FALSE, TRUE], $this->column($cWaterReading, 'turned'));
		$this->assertEquals([NULL, 35.0, 27.0], $this->column($cWaterReading, 'consumption'));
		$this->assertEquals([0.0, 35.0, 62.0], $this->column($cWaterReading, 'cumulative'), 'le cumul ne retombe pas au tour du compteur');

	}

	/**
	 * Le tour n'est écrit nulle part : un relevé corrigé le fait disparaître, un relevé ajouté dans le
	 * passé au-dessus du suivant le pose sur le suivant.
	 */
	public function testTurnFollowsTheReadings(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Réseau', 4);

		$this->read($eWaterMeter, '2026-09-01', 100);
		$eWaterReading = $this->read($eWaterMeter, '2026-09-20', 20);

		$this->assertEquals([FALSE, TRUE], $this->column($this->getMeter($eStructure, $eWaterMeter)['cWaterReading'], 'turned'));

		$eWaterReading['value'] = 200.0;
		WaterReadingLib::update($eWaterReading, ['value']);

		$cWaterReading = $this->getMeter($eStructure, $eWaterMeter)['cWaterReading'];
		$this->assertEquals([FALSE, FALSE], $this->column($cWaterReading, 'turned'), 'la faute de frappe corrigée retire la marque');
		$this->assertEquals([NULL, 100.0], $this->column($cWaterReading, 'consumption'));

		$this->read($eWaterMeter, '2026-09-10', 300);

		$cWaterReading = $this->getMeter($eStructure, $eWaterMeter)['cWaterReading'];
		$this->assertEquals([FALSE, FALSE, TRUE], $this->column($cWaterReading, 'turned'), 'le relevé ajouté dans le passé marque celui qui le suit');
		$this->assertEquals([NULL, 200.0, 9900.0], $this->column($cWaterReading, 'consumption'));

	}

	public function testSecondReadingOfTheDayReplacesTheFirst(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$eWaterReadingFirst = $this->read($eWaterMeter, '2026-09-29', 12482);
		$eWaterReadingSecond = $this->read($eWaterMeter, '2026-09-29', 12484.5);

		$this->assertEquals(1, WaterReading::model()->whereMeter($eWaterMeter)->count(), 'une ligne par compteur et par jour');
		$this->assertEquals(12484.5, WaterReading::model()->whereMeter($eWaterMeter)->getValue('value'), 'le second relevé du jour remplace le premier');
		$this->assertEquals($eWaterReadingFirst['id'], $eWaterReadingSecond['id'], 'le relevé qui en remplace un autre porte l\'id de la ligne du jour');

		$eWaterMeterOther = $this->place($eStructure, 'Puits');
		$this->read($eWaterMeterOther, '2026-09-29', 3);

		$this->assertEquals(12484.5, WaterReading::model()->whereMeter($eWaterMeter)->getValue('value'), 'le relevé d\'un autre compteur le même jour ne touche pas celui-ci');

	}

	/**
	 * Le compteur au 1er janvier se lit sur la droite entre le dernier relevé de l'année d'avant et le
	 * premier de celle-ci : 1 000 le 22 décembre, 1 200 le 11 janvier, donc 1 100 au 1er janvier.
	 */
	public function testYearCountsFromTheFirstOfJanuary(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2025-12-22', 1000);
		$this->read($eWaterMeter, '2026-01-11', 1200);
		$this->read($eWaterMeter, '2026-09-30', 1500);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(400.0, $eWaterMeter['year'], '1 500 au dernier relevé, 1 100 au 1er janvier');
		$this->assertEquals('2026-01-01', $eWaterMeter['yearSince']);
		$this->assertEquals(NULL, $eWaterMeter['yearPrevious'], 'l\'année d\'avant n\'est pas couverte depuis son 1er janvier');

	}

	/**
	 * « En 2025 à la même date » : du 1er janvier 2025 au 30 septembre 2025, jour du dernier relevé de
	 * 2026, les deux lus sur la droite entre deux relevés.
	 */
	public function testYearPreviousStopsOnTheSameDay(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2024-12-22', 0);
		$this->read($eWaterMeter, '2025-01-11', 200);
		$this->read($eWaterMeter, '2025-09-20', 600);
		$this->read($eWaterMeter, '2025-10-10', 800);
		$this->read($eWaterMeter, '2025-12-22', 1000);
		$this->read($eWaterMeter, '2026-01-11', 1200);
		$this->read($eWaterMeter, '2026-09-30', 1500);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(400.0, $eWaterMeter['year']);
		$this->assertEquals(600.0, $eWaterMeter['yearPrevious'], '700 au 30 septembre 2025, à mi-chemin de deux relevés, 100 au 1er janvier 2025');

		$this->setClock('2027-02-01 10:00:00');

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals([NULL, NULL, NULL], [$eWaterMeter['year'], $eWaterMeter['yearSince'], $eWaterMeter['yearPrevious']], 'une année sans relevé n\'a pas de chiffre');

	}

	/**
	 * La première année d'usage, le compteur ne sait pas ce qu'il affichait au 1er janvier : le chiffre
	 * compte depuis son premier relevé, et le dit (doute A du contrat).
	 */
	public function testFirstYearCountsFromTheFirstReading(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Puits');

		$this->read($eWaterMeter, '2026-03-05', 50);
		$this->read($eWaterMeter, '2026-09-30', 170.4);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(120.0, $eWaterMeter['year'], 'le chiffre de l\'année est en m³ entiers');
		$this->assertEquals('2026-03-05', $eWaterMeter['yearSince']);
		$this->assertEquals(NULL, $eWaterMeter['yearPrevious']);

	}

	public function testSummaryAddsTheMeters(): void {

		[$eStructure] = self::getStructures();

		$this->assertEquals(
			[TRUE, NULL, NULL, NULL, TRUE],
			$this->summarize(WaterReadingLib::getSummary($eStructure)),
			'une structure sans compteur'
		);

		$eWaterMeterForage = $this->place($eStructure, 'Forage');
		$this->read($eWaterMeterForage, '2025-12-22', 1000);
		$this->read($eWaterMeterForage, '2026-01-11', 1200);
		$this->read($eWaterMeterForage, '2026-09-30', 1500);

		$eWaterMeterPuits = $this->place($eStructure, 'Puits');
		$this->read($eWaterMeterPuits, '2026-03-05', 50);
		$eWaterReadingLast = $this->read($eWaterMeterPuits, '2026-10-01', 170);

		$eWaterMeterEmpty = $this->place($eStructure, 'Réseau', 4);

		$eWaterMeterDeleted = $this->place($eStructure, 'Ancien');
		$this->read($eWaterMeterDeleted, '2026-03-05', 10);
		$this->read($eWaterMeterDeleted, '2026-10-02', 900);
		$eWaterMeterDeleted['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeterDeleted, ['status']);

		$waters = WaterReadingLib::getSummary($eStructure);

		$this->assertEquals([$eWaterMeterForage['id'], $eWaterMeterPuits['id'], $eWaterMeterEmpty['id']], $waters['cWaterMeter']->getIds(), 'les compteurs actifs, dans l\'ordre de leur création');
		$this->assertEquals(520.0, $waters['year'], 'les compteurs s\'additionnent, le compteur supprimé n\'y entre pas');
		$this->assertEquals('2026-01-01', $waters['yearSince'], 'le plus ancien des jours d\'où ils comptent');
		$this->assertEquals(NULL, $waters['yearPrevious']);
		$this->assertEquals($eWaterReadingLast['id'], $waters['eWaterReadingLast']['id'], 'le relevé le plus récent, tous compteurs confondus');
		$this->assertEquals([77, 23, NULL], $this->column($waters['cWaterMeter'], 'share'), 'la part de chacun, absente sans chiffre de l\'année');

		$this->assertEquals(2, WaterReading::model()->whereMeter($eWaterMeterDeleted)->count(), 'le compteur supprimé garde ses relevés en base');

	}

	/**
	 * Les courbes d'un compteur : un point par relevé, comptés depuis le 1er janvier quand un relevé
	 * le précède, et une année que d'autres relevés suivent se ferme au 31 décembre. Le dernier point
	 * de l'année en cours vaut le chiffre de l'année.
	 */
	public function testYearsDrawOnePointPerReading(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2026-09-30', 1500);

		$this->assertEquals([], $this->getMeter($eStructure, $eWaterMeter)['years'], 'un seul relevé ne trace rien');

		$this->read($eWaterMeter, '2024-12-22', 0);
		$this->read($eWaterMeter, '2025-01-11', 200);
		$this->read($eWaterMeter, '2025-09-20', 600);
		$this->read($eWaterMeter, '2025-10-10', 800);
		$this->read($eWaterMeter, '2025-12-22', 1000);
		$this->read($eWaterMeter, '2026-01-11', 1200);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(
			[
				2025 => ['2025-01-01' => 0, '2025-01-11' => 100, '2025-09-20' => 500, '2025-10-10' => 700, '2025-12-22' => 900, '2025-12-31' => 990],
				2026 => ['2026-01-01' => 0, '2026-01-11' => 100, '2026-09-30' => 400]
			],
			$eWaterMeter['years'],
			'un seul relevé en 2024 n\'en fait pas une année, les suivantes partent du 1er janvier, une année passée va au 31 décembre'
		);

		$this->assertEquals($eWaterMeter['year'], (float)end($eWaterMeter['years'][2026]), 'le dernier point de l\'année vaut son chiffre');

		$html = html_entity_decode(new WaterReadingUi()->getOne($eStructure, $eWaterMeter));

		$this->assertTrue(str_contains($html, 'UtilChart.createTimeLine(this, [0,100,400], ["2026-01-01","2026-01-11","2026-09-30"], '.json_encode('m³').', ["2026-01-01","2026-12-31"])'), 'l\'année en cours, un point par relevé, sur un axe qui va au 31 décembre');
		$this->assertTrue(str_contains($html, '"dates":["2026-01-01","2026-01-11","2026-09-30"],"values":[0,100,400]},{"label":"2025 '), 'une courbe par année, la plus récente d\'abord');
		$this->assertTrue(str_contains($html, '"values":[0,100,500,700,900,990]}], '.json_encode('m³').', true)'), 'sur un même axe, du 1er janvier au 31 décembre');

	}

	/**
	 * Une première année n'a qu'une courbe, sans bascule.
	 */
	public function testFirstYearHasNoYearsTab(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Puits');

		$this->read($eWaterMeter, '2026-03-05', 50);

		$this->read($eWaterMeter, '2026-10-01', 170.5);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals([2026 => ['2026-03-05' => 0, '2026-10-01' => 121]], $eWaterMeter['years'], 'des m³ entiers, comme le chiffre de l\'année');

		$html = html_entity_decode(new WaterReadingUi()->getOne($eStructure, $eWaterMeter));

		$this->assertTrue(str_contains($html, 'UtilChart.createTimeLine(this, [0,121], ["2026-03-05","2026-10-01"], '.json_encode('m³').', ["2026-01-01","2026-12-31"])'), 'l\'axe part du 1er janvier même quand le premier relevé est plus tard');

	}

	/**
	 * Le total lit chaque compteur sur sa propre courbe aux jours des autres : rien avant son premier
	 * point, la droite entre deux relevés, sa dernière valeur ensuite.
	 */
	public function testTotalYearsAddTheMeters(): void {

		[$eStructure] = self::getStructures();

		$eWaterMeterForage = $this->place($eStructure, 'Forage');
		$this->read($eWaterMeterForage, '2025-12-22', 1000);
		$this->read($eWaterMeterForage, '2026-01-11', 1200);
		$this->read($eWaterMeterForage, '2026-09-30', 1500);

		$eWaterMeterPuits = $this->place($eStructure, 'Puits');
		$this->read($eWaterMeterPuits, '2026-03-05', 50);
		$this->read($eWaterMeterPuits, '2026-10-01', 170);

		$this->place($eStructure, 'Réseau', 4);

		$waters = WaterReadingLib::getSummary($eStructure);

		$this->assertEquals(
			[
				2026 => ['2026-01-01' => 0, '2026-01-11' => 100, '2026-03-05' => 161, '2026-09-30' => 519, '2026-10-01' => 520]
			],
			$waters['years'],
			'le Puits vaut 0 avant son premier relevé, le Forage reste à 400 après son dernier'
		);

		$this->assertEquals($waters['year'], (float)end($waters['years'][2026]), 'le dernier point du total vaut son chiffre');

		$html = html_entity_decode(new WaterReadingUi()->getSeveral($eStructure, $waters, new WaterMeter()));

		$this->assertTrue(str_contains($html, 'UtilChart.createTimeLines(this, [{"label":"Total","dates":["2026-01-01","2026-01-11","2026-03-05","2026-09-30","2026-10-01"],"values":[0,100,161,519,520]},{"label":"Forage",'), 'le total d\'abord, puis chaque compteur');
		$this->assertTrue(str_contains($html, '{"label":"Puits","dates":["2026-03-05","2026-10-01"],"values":[0,120]}], '.json_encode('m³').', false, ["2026-01-01","2026-12-31"])'), 'un compteur sans relevé n\'a pas de courbe, et l\'axe va au 31 décembre');

	}

	/**
	 * L'export sort ce que l'écran montre : chaque relevé avec sa consommation, tour du compteur
	 * compris, compteur après compteur dans l'ordre des onglets et du plus ancien au plus récent.
	 */
	/**
	 * Le compteur remplacé : ce que l'ancien a consommé jusqu'à sa dépose se compte le jour du
	 * changement, le relevé suivant part de la pose du nouveau, et rien n'est lu comme un tour.
	 */
	public function testChangeKeepsCounting(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2026-07-01', 12400);
		$this->read($eWaterMeter, '2026-08-01', 12450);
		$eWaterReadingChange = $this->change($eWaterMeter, '2026-09-01', 12480, 0, 5);
		$eWaterReadingAfter = $this->read($eWaterMeter, '2026-10-01', 25);

		$this->assertEquals([], WaterReadingLib::getGap($eWaterReadingChange), 'un compteur neuf à zéro n\'est pas un tour');
		$this->assertEquals(['consumption' => 25.0, 'date' => '2026-09-01', 'turned' => FALSE, 'alert' => FALSE], WaterReadingLib::getGap($eWaterReadingAfter), 'le relevé suivant se compare à la pose');

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);
		$cWaterReading = $eWaterMeter['cWaterReading'];

		$this->assertEquals([FALSE, FALSE, TRUE, FALSE], $this->column($cWaterReading, 'changed'));
		$this->assertEquals([NULL, 50.0, 30.0, 25.0], $this->column($cWaterReading, 'consumption'), 'l\'ancien jusqu\'à sa dépose, puis le nouveau depuis sa pose');
		$this->assertEquals([FALSE, FALSE, FALSE, FALSE], $this->column($cWaterReading, 'turned'));
		$this->assertEquals([0.0, 50.0, 80.0, 105.0], $this->column($cWaterReading, 'cumulative'), 'le cumul ne retombe pas au changement');
		$this->assertEquals(105.0, $eWaterMeter['year']);
		$this->assertEquals(['2026-07-01' => 0, '2026-08-01' => 50, '2026-09-01' => 80, '2026-10-01' => 105], $eWaterMeter['years'][2026], 'la courbe non plus');

	}

	/**
	 * Sans relevé de l'ancien compteur à sa dépose, le jour du changement ne compte rien : ce qu'il a
	 * consommé depuis le relevé d'avant n'est pas connu, et n'est pas inventé.
	 */
	public function testChangeWithoutLastReading(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2026-08-01', 12450);
		$this->change($eWaterMeter, '2026-09-01', NULL, 3.5, 5);
		$this->read($eWaterMeter, '2026-10-01', 25);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals([NULL, 0.0, 21.5], $this->column($eWaterMeter['cWaterReading'], 'consumption'), 'le nouveau compte depuis sa pose à 3,5');
		$this->assertEquals([0.0, 0.0, 21.5], $this->column($eWaterMeter['cWaterReading'], 'cumulative'));

		$html = new WaterReadingUi()->getOne($eStructure, $eWaterMeter);

		$this->assertFalse(str_contains($html, '+ 0 m³'), 'une consommation inconnue ne s\'affiche pas comme nulle');

	}

	/**
	 * Un changement posé le premier jour relevé n'a rien à quoi se comparer, et un changement posé un
	 * jour déjà relevé remplace le relevé du jour.
	 */
	public function testChangeOnADayAlreadyRead(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');

		$this->read($eWaterMeter, '2026-08-01', 12450);
		$eWaterReading = $this->read($eWaterMeter, '2026-09-01', 12470);
		$eWaterReadingChange = $this->change($eWaterMeter, '2026-09-01', 12480, 0, 5);

		$this->assertEquals($eWaterReading['id'], $eWaterReadingChange['id'], 'le relevé du jour devient le changement');

		$cWaterReading = $this->getMeter($eStructure, $eWaterMeter)['cWaterReading'];

		$this->assertEquals(2, $cWaterReading->count());
		$this->assertEquals([NULL, 30.0], $this->column($cWaterReading, 'consumption'));

		$eWaterMeterNew = $this->place($eStructure, 'Puits');
		$this->change($eWaterMeterNew, '2026-09-01', 800, 0, 5);
		$this->read($eWaterMeterNew, '2026-10-01', 12);

		$this->assertEquals([NULL, 12.0], $this->column($this->getMeter($eStructure, $eWaterMeterNew)['cWaterReading'], 'consumption'), 'un changement sans relevé avant lui ne compte rien');

	}

	/**
	 * Le nombre de chiffres ne se réécrit pas sur le passé : le changement garde celui de l'ancien
	 * compteur, qui compte son dernier tour et borne ses relevés, et le compteur porte celui du nouveau.
	 */
	public function testChangeKeepsTheDigitsOfThePast(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Réseau', 4);

		$this->read($eWaterMeter, '2026-07-01', 9990);
		$this->change($eWaterMeter, '2026-08-01', 5, 100000, 6);
		$this->read($eWaterMeter, '2026-09-01', 100040);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(6, $eWaterMeter['digits'], 'le compteur porte les chiffres du nouveau');
		$this->assertEquals(4, WaterReadingLib::getDigits($eWaterMeter, '2026-07-15'), 'avant le changement, ceux de l\'ancien');
		$this->assertEquals(6, WaterReadingLib::getDigits($eWaterMeter, '2026-08-01'), 'le jour du changement, le nouveau est en place');
		$this->assertEquals([NULL, 15.0, 40.0], $this->column($eWaterMeter['cWaterReading'], 'consumption'), 'l\'ancien a fait le tour sur ses quatre chiffres avant sa dépose');
		$this->assertEquals([FALSE, TRUE, FALSE], $this->column($eWaterMeter['cWaterReading'], 'turned'));

		$new = fn() => new WaterReading(['structure' => $eStructure]);

		$this->assertEquals(['WaterReading::value.digits'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-07-15', 'value' => '10000']), 'un relevé d\'avant le changement reste sous les quatre chiffres de l\'ancien');
		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-07-15', 'value' => '9995']));
		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-09-15', 'value' => '100060']), 'un relevé d\'après a les six du nouveau');

		$this->assertEquals(['WaterMeter::digits.readings'], $this->buildsMeter($eWaterMeter, ['name', 'digits'], ['name' => 'Réseau', 'digits' => '5'], 'update'), 'le compteur en place a affiché 100 040');

		// Un second changement, vers un compteur à quatre chiffres : seuls ses relevés le bornent.
		$this->change($eWaterMeter, '2026-09-20', NULL, 0, 4);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(4, $eWaterMeter['digits']);
		$this->assertEquals(6, WaterReadingLib::getDigits($eWaterMeter, '2026-09-15'), 'le second changement garde les six chiffres de celui qu\'il remplace');
		$this->assertEquals(4, WaterReadingLib::getDigits($eWaterMeter, '2026-07-15'));
		$this->assertEquals([], $this->buildsMeter($eWaterMeter, ['name', 'digits'], ['name' => 'Réseau', 'digits' => '5'], 'update'), 'les relevés des compteurs d\'avant ne bornent pas celui qui est en place');

		// Un changement déclaré dans le passé, entre les deux : les chiffres du compteur ne bougent pas.
		$this->change($eWaterMeter, '2026-08-15', 100020, 0, 5);

		$this->assertEquals(4, $this->getMeter($eStructure, $eWaterMeter)['digits']);
		$this->assertEquals(6, WaterReadingLib::getDigits($eWaterMeter, '2026-08-10'));
		$this->assertEquals(5, WaterReadingLib::getDigits($eWaterMeter, '2026-08-15'), 'le compteur posé le 15 août a cinq chiffres jusqu\'au changement suivant');
		$this->assertEquals(4, WaterReadingLib::getDigits($eWaterMeter, '2026-07-15'));

	}

	/**
	 * Supprimer le changement rend les relevés suivants à l'ancien cycle : un relevé plus bas y
	 * redevient un tour de compteur, compté sur le plus grand des deux nombres de chiffres.
	 */
	public function testDeletingTheChangeJoinsTheCycles(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Réseau', 4);

		$this->read($eWaterMeter, '2026-07-01', 9990);
		$eWaterReadingChange = $this->change($eWaterMeter, '2026-08-01', 9995, 0, 5);
		$this->read($eWaterMeter, '2026-09-01', 40);

		$eWaterReadingChange = WaterReadingLib::getById($eWaterReadingChange['id'], ['id', 'structure', 'meter', 'date', 'changed', 'changedDigits']);
		WaterReadingLib::delete($eWaterReadingChange);

		$eWaterMeter = $this->getMeter($eStructure, $eWaterMeter);

		$this->assertEquals(5, $eWaterMeter['digits'], 'les deux cycles réunis prennent le plus grand nombre de chiffres');
		$this->assertEquals([NULL, 90050.0], $this->column($eWaterMeter['cWaterReading'], 'consumption'));
		$this->assertEquals([FALSE, TRUE], $this->column($eWaterMeter['cWaterReading'], 'turned'), 'sans changement déclaré, un relevé plus bas est un tour');

		// Le relevé simple d'un jour ne touche pas aux chiffres en le supprimant.
		$eWaterReading = WaterReadingLib::getById($eWaterMeter['cWaterReading']->last()['id'], ['id', 'structure', 'meter', 'date', 'changed', 'changedDigits']);
		WaterReadingLib::delete($eWaterReading);

		$this->assertEquals(5, $this->getMeter($eStructure, $eWaterMeter)['digits']);

	}

	public function testChangeBuild(): void {

		[$eStructure] = self::getStructures();
		$eWaterMeter = $this->place($eStructure, 'Forage');
		$this->read($eWaterMeter, '2026-09-01', 12450);

		$this->assertEquals([], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480.5', 'value' => '0', 'digits' => '5']));
		$this->assertEquals([], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '', 'value' => '0', 'digits' => '5']), 'la dépose de l\'ancien compteur peut ne pas avoir été relevée');
		$this->assertEquals([], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480', 'value' => '0', 'digits' => '4']), 'le nouveau compteur a moins de chiffres que les relevés de l\'ancien');

		$eWaterMeter['digits'] = 5;

		$this->assertEquals(['WaterReading::value.digits'], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480', 'value' => '10000', 'digits' => '4']), 'la pose se borne par les chiffres du nouveau');

		$eWaterMeter['digits'] = 5;

		$this->assertEquals(['WaterReading::changedValue.digits'], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '100000', 'value' => '0', 'digits' => '6']), 'la dépose par ceux de l\'ancien');

		$eWaterMeter['digits'] = 5;

		$this->assertEquals([], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480,5', 'value' => '0', 'digits' => '5']), 'la dépose se lit avec une virgule');
		$this->assertEquals(['WaterReading::value.check'], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480', 'value' => '', 'digits' => '5']), 'le nouveau compteur a un premier relevé');
		$this->assertEquals(['WaterReading::date.future'], $this->buildsChange($eWaterMeter, ['date' => '2026-10-03', 'changedValue' => '12480', 'value' => '0', 'digits' => '5']));
		$this->assertEquals(['WaterMeter::digits.check'], $this->buildsChange($eWaterMeter, ['date' => '2026-10-02', 'changedValue' => '12480', 'value' => '0', 'digits' => '3']));

		// La modification d'un changement : ses deux relevés, bornés chacun par son compteur.
		$eWaterMeter['digits'] = 5;
		$eWaterReadingChange = $this->change($eWaterMeter, '2026-10-01', 12480, 0, 4);
		$eWaterReadingChange = WaterReadingLib::getById($eWaterReadingChange['id'], ['id', 'structure', 'date', 'value', 'changed', 'changedValue', 'changedDigits', 'meter' => ['name', 'digits', 'status']]);

		$properties = WaterReadingLib::getPropertiesUpdate()($eWaterReadingChange);

		$this->assertEquals(['changedValue', 'value'], $properties);

		$builds = function(array $input) use($eWaterReadingChange, $properties): array {

			$fw = new \FailWatch();
			$eWaterReadingChange->build($properties, $input, new \PropertyList('update'));

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		$this->assertEquals([], $builds(['changedValue' => '99999', 'value' => '9999']));
		$this->assertEquals(['WaterReading::changedValue.digits'], $builds(['changedValue' => '100000', 'value' => '0']), 'l\'ancien avait cinq chiffres');
		$this->assertEquals(['WaterReading::value.digits'], $builds(['changedValue' => '12480', 'value' => '10000']), 'le nouveau en a quatre');

		$eWaterReading = WaterReadingLib::getById($this->read($eWaterMeter, '2026-10-02', 12)['id'], ['id', 'structure', 'date', 'value', 'changed', 'changedValue', 'changedDigits', 'meter' => ['name', 'digits', 'status']]);

		$this->assertEquals(['value'], WaterReadingLib::getPropertiesUpdate()($eWaterReading), 'un relevé simple n\'a pas de dépose à corriger');
		$this->assertEquals(['value'], WaterReadingLib::getPropertiesUpdate('api'), 'et l\'API ne corrige que le relevé');

	}

	public function testExportListsEveryReading(): void {

		[$eStructure, $eStructureOther] = self::getStructures();

		$eWaterMeterNetwork = $this->place($eStructure, 'Réseau', 4);
		$eWaterMeterWell = $this->place($eStructure, 'Puits');
		$this->place($eStructure, 'Forage');

		$this->read($eWaterMeterWell, '2026-09-01', 1204.5);
		$this->read($eWaterMeterWell, '2026-08-01', 1158);
		$this->read($eWaterMeterNetwork, '2026-08-15', 9987);
		$this->read($eWaterMeterNetwork, '2026-09-15', 14);
		$this->change($eWaterMeterWell, '2026-09-20', 1210.5, 2, 5);
		$this->change($eWaterMeterNetwork, '2026-09-20', NULL, 0, 4);

		$this->read($this->place($eStructureOther, 'Ailleurs'), '2026-09-01', 50);

		$eWaterMeterDeleted = $this->place($eStructure, 'Ancien');
		$this->read($eWaterMeterDeleted, '2026-09-01', 300);
		$eWaterMeterDeleted['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeterDeleted, ['status']);

		$this->assertEquals([
			['Réseau', '2026-08-15', '9987', NULL, 'no', NULL],
			['Réseau', '2026-09-15', '14', '27', 'no', NULL],
			['Réseau', '2026-09-20', '0', '0', 'yes', NULL],
			['Puits', '2026-08-01', '1158', NULL, 'no', NULL],
			['Puits', '2026-09-01', '1204,5', '46,5', 'no', NULL],
			['Puits', '2026-09-20', '2', '6', 'yes', '1210,5'],
		], WaterReadingLib::getExport(WaterReadingLib::getMeters($eStructure)), 'un compteur sans relevé, un compteur supprimé et une autre structure n\'y entrent pas');

		$this->assertEquals(6, count(new WaterReadingUi()->getExportHeader()), 'l\'en-tête nomme autant de colonnes qu\'une ligne en porte');

	}

	private function summarize(array $waters): array {
		return [$waters['cWaterMeter']->empty(), $waters['year'], $waters['yearSince'], $waters['yearPrevious'], $waters['eWaterReadingLast']->empty()];
	}

	public function testReadingBuild(): void {

		[$eStructure, $eStructureOther] = self::getStructures();

		$eWaterMeter = $this->place($eStructure, 'Forage');
		$eWaterMeterOther = $this->place($eStructureOther, 'Forage');

		$new = fn() => new WaterReading(['structure' => $eStructure]);

		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '12482.5']), 'le relevé du jour passe, au dixième de m³');
		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '12482,5']), 'une virgule se lit comme un point');
		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '0']), 'un compteur à zéro est un relevé');
		$this->assertEquals([], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '99999.9']), 'le plus haut relevé d\'un compteur à cinq chiffres');

		$this->assertEquals(['WaterReading::date.future'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-03', 'value' => '4']), 'demain n\'a pas encore de relevé');
		$this->assertEquals(['WaterReading::value.number'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => 'abc']), 'ce qui n\'est pas un nombre ne pose pas un compteur à zéro');
		$this->assertEquals(['WaterReading::value.check'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '']), 'un relevé a une valeur');
		$this->assertEquals(['WaterReading::value.check'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '-1']), 'un compteur n\'affiche rien sous zéro');
		$this->assertEquals(['WaterReading::value.digits'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '100000']), 'un compteur à cinq chiffres n\'affiche pas 100 000');

		$this->assertEquals(['WaterReading::meter.check'], $this->buildsReading($new(), ['date' => '2026-10-02', 'value' => '4']), 'un relevé a un compteur, et seul le compteur est signalé');
		$this->assertEquals(['WaterReading::meter.owned'], $this->buildsReading($new(), ['meter' => $eWaterMeterOther['id'], 'date' => '2026-10-02', 'value' => '4']), 'le compteur d\'une autre structure est refusé');

		$eWaterMeter['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeter, ['status']);

		$this->assertEquals(['WaterReading::meter.owned'], $this->buildsReading($new(), ['meter' => $eWaterMeter['id'], 'date' => '2026-10-02', 'value' => '4']), 'un compteur supprimé ne reçoit plus de relevé');

	}

	public function testReadingOfADeletedMeterIsClosed(): void {

		[$eStructure] = self::getStructures();

		$eWaterMeter = $this->place($eStructure, 'Forage');
		$eWaterReading = $this->read($eWaterMeter, '2026-09-29', 40);

		$this->assertTrue($eWaterReading->acceptUpdate());
		$this->assertTrue($eWaterReading->acceptDelete());
		$this->assertTrue($eWaterMeter->acceptUpdate());

		$eWaterMeter['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeter, ['status']);

		$this->assertFalse($eWaterReading->acceptUpdate(), 'le relevé d\'un compteur supprimé ne se corrige plus');
		$this->assertFalse($eWaterReading->acceptDelete(), 'ni ne se supprime');
		$this->assertFalse($eWaterMeter->acceptUpdate(), 'un compteur supprimé ne revient pas');

	}

	public function testMeterBuild(): void {

		[$eStructure, $eStructureOther] = self::getStructures();

		$eWaterMeter = $this->place($eStructure, 'Forage');
		$this->place($eStructureOther, 'Puits');
		$this->read($eWaterMeter, '2026-09-29', 12482);

		$new = fn() => new WaterMeter(['structure' => $eStructure]);
		$properties = WaterMeterLib::getPropertiesCreate();

		$this->assertEquals([], $this->buildsMeter($new(), $properties, ['name' => 'Puits', 'digits' => '4'], 'create'), 'le nom d\'un compteur d\'une autre structure est libre');
		$this->assertEquals([], $this->buildsMeter($new(), $properties, ['name' => 'Réseau', 'digits' => '9'], 'create'));

		$this->assertEquals(['WaterMeter::name.duplicate'], $this->buildsMeter($new(), $properties, ['name' => 'Forage', 'digits' => '5'], 'create'), 'deux compteurs du même nom feraient deux onglets qu\'on ne distingue pas');
		$this->assertEquals(['WaterMeter::name.check'], $this->buildsMeter($new(), $properties, ['name' => '', 'digits' => '5'], 'create'), 'un compteur a un nom');
		$this->assertEquals(['WaterMeter::digits.check'], $this->buildsMeter($new(), $properties, ['name' => 'Réseau', 'digits' => '3'], 'create'), 'quatre chiffres au moins');
		$this->assertEquals(['WaterMeter::digits.check'], $this->buildsMeter($new(), $properties, ['name' => 'Réseau', 'digits' => '10'], 'create'), 'neuf au plus');

		$this->assertEquals([], $this->buildsMeter($eWaterMeter, ['name', 'digits'], ['name' => 'Forage', 'digits' => '6'], 'update'), 'un compteur qui garde son nom ne se compte pas lui-même');
		$this->assertEquals(['WaterMeter::digits.readings'], $this->buildsMeter($eWaterMeter, ['name', 'digits'], ['name' => 'Forage', 'digits' => '4'], 'update'), 'un compteur qui a affiché 12 482 a au moins cinq chiffres');

		$this->assertEquals(['WaterMeter::status.deleted'], $this->buildsMeter($eWaterMeter, ['status'], ['status' => WaterMeter::ACTIVE], 'update'), 'le seul changement de statut est la suppression');
		$this->assertEquals([], $this->buildsMeter($eWaterMeter, ['status'], ['status' => WaterMeter::INACTIVE], 'update'));

		$eWaterMeter['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeter, ['status']);

		$this->assertEquals([], $this->buildsMeter($new(), $properties, ['name' => 'Forage', 'digits' => '5'], 'create'), 'le nom d\'un compteur supprimé redevient libre');

	}

	/**
	 * Le premier compteur fait de la consommation d'eau un outil de la structure, qui le reste quand
	 * le compteur est supprimé.
	 */
	public function testFirstMeterAddsTheTool(): void {

		[$eStructure] = self::getStructures();

		$hasWater = fn() => in_array(Favorite::WATER, \structure\Feature::model()
			->whereStructure($eStructure)
			->getValue('hasToolbox'));

		$this->assertFalse($hasWater(), 'pas d\'outil avant le premier compteur');

		$eWaterMeter = $this->place($eStructure, 'Forage');
		$this->assertTrue($hasWater(), 'le premier compteur inscrit l\'outil, avant tout relevé');

		$eWaterMeter['status'] = WaterMeter::INACTIVE;
		WaterMeterLib::update($eWaterMeter, ['status']);

		$this->assertTrue($hasWater(), 'la suppression laisse l\'outil parmi ceux de la structure');

	}

}
?>
