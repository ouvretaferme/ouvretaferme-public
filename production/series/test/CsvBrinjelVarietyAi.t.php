<?php
namespace series;

/**
 * L'import d'un plan Brinjel passe-t-il ses variétés ?
 *
 * Signalé le 15/09/2026 : l'import « bloque au niveau des variétés ». Deux causes, toutes deux
 * invisibles à l'analyse, qui annonçait « Vos données sont prêtes à être importées » puis
 * refusait la série entière à l'écriture.
 *
 * - Brinjel donne une longueur par variété, que la conversion ramène à un pourcentage de la
 *   série. Arrondi par défaut, un mètre sur deux cents tombait à 0 %, et `SliceLib::prepare()`
 *   lève `sequence\Crop::variety.partZero`. Une même variété sur plusieurs lignes ne cumulait
 *   pas non plus ses longueurs : seule la première comptait.
 * - Un nom de variété de plus de 50 caractères, que `Variety::build()` refuse
 *   (`sequence\Crop::variety.createName`).
 *
 * Signalé de nouveau le 16/09/2026 : une culture sans aucune variété recevait la variété
 * partagée « inconnue » sous la forme d'un élément là où l'écriture attend une valeur de champ
 * (`sequence\Crop::variety.notExists`).
 *
 * Signalé le 09/10/2026 : « Division by zero » à la conversion. Une longueur décimale de moins
 * d'un mètre (`0.5`, `0,5`, `0.0`) passait le test de chaîne non vide, puis la troncature
 * entière la ramenait à zéro : une série dont toutes les variétés étaient dans ce cas avait une
 * longueur totale nulle, par laquelle le calcul des parts divisait.
 *
 * Une série sur planches est refusée sans largeur de planche par défaut (`Series::bedWidth.required`) :
 * chaque test pose la sienne, dans la transaction qui annule aussi les séries et les variétés que
 * l'import écrit.
 *
 * Lancement : dev/test package=series file=CsvBrinjelVarietyAi
 */
class CsvBrinjelVarietyAiTest extends \dev\TransactionTest {

	/** La structure du décor qui possède l'espèce utilisée ci-dessous. */
	private const STRUCTURE = \structure\FixtureAiLib::F1;
	private const SPECIES = 'Carotte';
	private const BED_WIDTH = 75;

	private \user\User $eUserInitial;

	/** La configuration est mémoïsée pour le processus : le rollback ne la remet pas en état. */
	private ?int $bedWidthInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Series::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		$eConfiguration = $this->getStructure()->conf(\map\Configuration::class);

		$this->bedWidthInitial = $eConfiguration['defaultBedWidth'];

		$eConfiguration['defaultBedWidth'] = self::BED_WIDTH;

		// Sans build() : la largeur est une constante du test, prise dans les bornes du schéma, et
		// aucun formulaire n'est éprouvé ici.
		\map\ConfigurationLib::update($eConfiguration, ['defaultBedWidth']);

	}

	public function tearDown(): void {

		parent::tearDown();

		$this->getStructure()->conf(\map\Configuration::class)['defaultBedWidth'] = $this->bedWidthInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);
		\Cache::redis()->delete('import-cultivations-'.self::STRUCTURE);

	}

	private function getStructure(): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

	}

	/**
	 * Un export Brinjel d'une seule série : une ligne par planche, `[variété, longueur]`.
	 */
	private function getCsv(array $beds): array {

		$season = date('Y');

		$csv = [
			['crop', 'variety', 'length', 'rows', 'spacing_plants', 'planting_type', 'in_greenhouse', 'unit', 'sowing_date', 'planting_date', 'first_harvest_date', 'last_harvest_date', 'finished']
		];

		foreach($beds as [$variety, $length]) {
			$csv[] = [self::SPECIES, $variety, (string)$length, '4', '5', 'direct_seeded', 'false', 'kg', $season.'-03-10', '', $season.'-06-10', $season.'-07-10', 'false'];
		}

		return $csv;

	}

	private function getParts(array $import): array {

		$varieties = array_values($import)[0]['cultivations'][0]['varieties'];

		return array_combine(array_column($varieties, 'variety'), array_column($varieties, 'part'));

	}

	private function connectAdmin(\structure\Structure $eStructure): void {

		$eMember = \structure\Member::model()
			->select(['user', 'role' => ['admin']])
			->whereStructure($eStructure)
			->whereStatus(\structure\Member::IN)
			->getCollection()
			->find(fn($eMember) => $eMember['role']->notEmpty() and $eMember['role']['admin'])
			->first();

		\user\ConnectionLib::setOnline(\user\UserLib::getById($eMember['user'], \user\User::getSelection()));

	}

	private function analyze(array $import): array {

		\Cache::redis()->set('import-cultivations-'.self::STRUCTURE, array_values($import));

		return CsvLib::getCultivations($this->getStructure());

	}

	public function testSmallVarietyKeepsAPart(): void {

		$parts = $this->getParts(CsvLib::convertFromBrinjel($this->getCsv([
			['Nantaise', 160],
			['Touchon', 39],
			['Jaune du Doubs', 1],
		])));

		$this->assertEquals(['Nantaise' => 80, 'Touchon' => 19, 'Jaune du Doubs' => 1], $parts, 'un mètre sur deux cents garde 1 %, le reste va à la plus grande part');

	}

	public function testRepeatedVarietyAddsItsLengths(): void {

		$parts = $this->getParts(CsvLib::convertFromBrinjel($this->getCsv([
			['Nantaise', 50],
			['Touchon', 25],
			['Touchon', 25],
		])));

		$this->assertEquals(['Nantaise' => 50, 'Touchon' => 50], $parts, 'une variété sur deux planches cumule ses deux longueurs');

	}

	public function testSubMeterLengthsKeepAPart(): void {

		$parts = $this->getParts(CsvLib::convertFromBrinjel($this->getCsv([
			['Nantaise', '0.0'],
			['Touchon', '0,5'],
		])));

		$this->assertEquals(['Nantaise' => 50, 'Touchon' => 50], $parts, "des longueurs de moins d'un mètre comptent un mètre, comme une longueur absente");

	}

	public function testDecimalLengthIsRounded(): void {

		$parts = $this->getParts(CsvLib::convertFromBrinjel($this->getCsv([
			['Nantaise', '74,6'],
			['Touchon', '24.6'],
		])));

		$this->assertEquals(['Nantaise' => 75, 'Touchon' => 25], $parts, 'une longueur décimale se lit avec sa virgule et s\'arrondit');

	}

	public function testAnalysisRefusesZeroPart(): void {

		$import = CsvLib::convertFromBrinjel($this->getCsv([['Nantaise', 99], ['Touchon', 1]]));
		$import = array_values($import);
		$import[0]['cultivations'][0]['varieties'][0]['part'] = 100;
		$import[0]['cultivations'][0]['varieties'][1]['part'] = 0;

		$result = $this->analyze($import);

		$this->assertTrue(in_array('varietyPartZero', $result['import'][0]['cultivations'][0]['errors']), "une part nulle est signalée à l'analyse : ".implode(', ', $result['import'][0]['cultivations'][0]['errors']));

	}

	public function testAnalysisRefusesLongVarietyName(): void {

		$name = str_repeat('Touchon ', 7);

		$result = $this->analyze(CsvLib::convertFromBrinjel($this->getCsv([['Nantaise', 50], [$name, 50]])));

		$this->assertTrue(in_array('varietyName', $result['import'][0]['cultivations'][0]['errors']), "un nom trop long est signalé à l'analyse : ".implode(', ', $result['import'][0]['cultivations'][0]['errors']));
		$this->assertFalse(in_array($name, $result['infoGlobal']['varieties']), "il n'est plus annoncé comme une variété à créer");

	}

	/**
	 * Le chemin du produit jusqu'à l'écriture : l'analyse passe, l'import crée la série et ses
	 * variétés neuves, la plus petite comprise.
	 */
	public function testImportCreatesSmallVariety(): void {

		$eStructure = $this->getStructure();

		$this->connectAdmin($eStructure);

		$suffix = uniqid();
		$name = 'Petite variété '.$suffix;

		$import = CsvLib::convertFromBrinjel($this->getCsv([
			['Nantaise '.$suffix, 160],
			['Touchon '.$suffix, 39],
			['Jaune '.$suffix, 1],
		]));
		$import = array_values($import);
		$import[0]['series']['name'] = $name;

		$result = $this->analyze($import);

		$this->assertEquals(0, $result['errorsCount'], "l'analyse ne trouve rien à redire");

		$fw = new \FailWatch();

		CsvLib::importCultivations($eStructure, $result['import']);

		$this->assertTrue($fw->ok(), "l'import passe : ".$fw->getLast());

		$this->assertEquals(1, \plant\Variety::model()
			->whereStructure($eStructure)
			->whereName('Jaune '.$suffix)
			->count(), "la variété d'un mètre est créée");

	}

	/**
	 * Brinjel laisse la colonne `variety` vide quand la planche n'en porte aucune : l'import pose
	 * alors la variété partagée « inconnue ».
	 */
	public function testImportWithoutVarietyUsesUnknown(): void {

		$eStructure = $this->getStructure();

		$this->connectAdmin($eStructure);

		$name = 'Sans variété '.uniqid();

		$import = CsvLib::convertFromBrinjel($this->getCsv([['', 160]]));
		$import = array_values($import);
		$import[0]['series']['name'] = $name;

		$result = $this->analyze($import);

		$this->assertEquals(0, $result['errorsCount'], "l'analyse ne trouve rien à redire");

		$fw = new \FailWatch();

		CsvLib::importCultivations($eStructure, $result['import']);

		$this->assertTrue($fw->ok(), "l'import passe : ".$fw->getLast());

		$eSeries = Series::model()
			->select(['id'])
			->whereStructure($eStructure)
			->whereName($name)
			->get();

		$this->assertTrue($eSeries->notEmpty(), 'la série est écrite');

		$eSlice = Slice::model()
			->select(['variety'])
			->whereSeries($eSeries)
			->get();

		$this->assertTrue($eSlice->notEmpty(), 'la culture porte une variété');
		$this->assertEquals(\plant\PlantSetting::VARIETY_UNKNOWN, $eSlice['variety']['id'], 'et cette variété est la variété inconnue');

	}

}
?>
