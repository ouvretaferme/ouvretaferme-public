<?php
namespace toolbox;

/**
 * L'import d'un fichier CSV de relevés sur un pluviomètre (planche pf-l8-v9, arbitrée le 24/09) :
 * le fichier donne le total du jour (Q23 A), les lignes fausses sont écartées et les autres
 * s'importent (Q24 B), une ligne à 0 est ignorée sans erreur.
 *
 * Le fichier passe par main\CsvLib::upload(), qui lit `$_FILES['csv']` : chaque test y pose un
 * fichier temporaire, comme le ferait le formulaire.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(), et le cache de l'import vidé.
 *
 * Lancement : dev/test package=toolbox file=RainImport
 */
class RainImportTest extends \Test {

	private Gauge $eGauge;

	public function setUp(): void {

		Rain::model()->beginTransaction();

		$eZone = RainTest::getZone();
		$this->eGauge = RainTest::place($eZone['structure'], $eZone);

	}

	public function tearDown(): void {

		RainLib::resetImport($this->eGauge);
		unset($_FILES['csv']);

		Rain::model()->rollBack();

	}

	private function upload(string $content): bool {

		$file = tempnam(sys_get_temp_dir(), 'rain');
		file_put_contents($file, $content);

		$_FILES['csv'] = ['tmp_name' => $file];

		$fw = new \FailWatch();
		$uploaded = RainLib::uploadImport($this->eGauge);

		return ($uploaded and $fw->ok());

	}

	private function note(string $date, float $rain): void {

		RainLib::create(new Rain([
			'structure' => $this->eGauge['structure'],
			'gauge' => $this->eGauge,
			'date' => $date,
			'rain' => $rain
		]));

	}

	private function getDays(): array {

		return Rain::model()
			->select(['date', 'rain'])
			->whereGauge($this->eGauge)
			->sort(['date' => SORT_ASC])
			->getCollection()
			->makeArray(fn(Rain $eRain, &$key) => ($key = $eRain['date']) ? $eRain['rain'] : NULL);

	}

	public function testFileWithoutTheTwoHeadersIsRefused(): void {

		$this->assertFalse($this->upload("day,rain\n2025-10-03,4.2"));
		$this->assertEquals(NULL, RainLib::getImport($this->eGauge));

	}

	public function testBothDateFormatsAndDecimalCommaAreRead(): void {

		$this->assertTrue($this->upload("station;date;quantity\nx;2025-10-03;4,2\nx;04/10/2025;12.6\n;;\n"));

		$import = RainLib::getImport($this->eGauge);

		$this->assertEquals(2, count($import['lines']));
		$this->assertEquals(['2025-10-03', '2025-10-04'], array_column($import['lines'], 'date'));
		$this->assertEquals([4.2, 12.6], array_column($import['lines'], 'rain'));
		$this->assertEquals(2, $import['added']);
		$this->assertEquals('2025-10-03', $import['first']);
		$this->assertEquals('2025-10-04', $import['last']);

	}

	public function testPreviewSortsEachLine(): void {

		$this->note('2025-10-04', 26);

		$this->assertTrue($this->upload(implode("\n", [
			'date,quantity',
			'2025-10-03,4.2',
			'2025-10-04,5.3',
			'2025-10-05,0',
			'2025-10-06,trois',
			'2025-10-07,-2',
			'2025-10-03,1',
			'2099-10-02,4',
			'32/10/2025,4',
		])));

		$import = RainLib::getImport($this->eGauge);

		$this->assertEquals(1, $import['added']);
		$this->assertEquals(1, $import['replaced']);
		$this->assertEquals(1, $import['ignored']);
		$this->assertEquals(5, $import['errors']);

		$errors = array_column($import['lines'], 'error');

		$this->assertEquals(NULL, $errors[0]);
		$this->assertEquals(NULL, $errors[1]);
		$this->assertEquals(NULL, $errors[2]);
		$this->assertEquals('Rain::rain.check', $errors[3]);
		$this->assertEquals('Rain::rain.negative', $errors[4]);
		$this->assertEquals('Rain::date.duplicate', $errors[5]);
		$this->assertEquals('Rain::date.future', $errors[6]);
		$this->assertEquals('Rain::date.check', $errors[7]);

		$this->assertEquals(26.0, $import['lines'][1]['eRain']['rain']);

		// Chaque refus a son message : un refus sans entrée ne s'afficherait pas dans l'aperçu.
		foreach(array_filter($errors) as $error) {
			$this->assertTrue(AlertUi::getError($error) !== NULL, $error);
		}

	}

	public function testFileReplacesTheDayAndSkipsWrongLines(): void {

		$this->note('2025-10-04', 26);
		$this->note('2025-10-05', 3);
		$this->note('2025-10-20', 8);

		$this->assertTrue($this->upload("date,quantity\n2025-10-03,4.2\n2025-10-04,5.3\n2025-10-05,0\n2025-10-06,trois"));

		// Les lignes écartées ne font pas échouer l'action qui importe les autres.
		$fw = new \FailWatch();

		$this->assertTrue(RainLib::import($this->eGauge));
		$this->assertTrue($fw->ok());

		$this->assertEquals([
			'2025-10-03' => 4.2,
			'2025-10-04' => 5.3,
			'2025-10-05' => 3.0,
			'2025-10-20' => 8.0,
		], $this->getDays());

		// Le fichier en attente est consommé.
		$this->assertEquals(NULL, RainLib::getImport($this->eGauge));

	}

	public function testSameFileTwiceChangesNothing(): void {

		$file = "date,quantity\n2025-10-03,4.2\n2025-10-04,5.3";

		$this->assertTrue($this->upload($file));
		RainLib::import($this->eGauge);

		$this->assertTrue($this->upload($file));

		$import = RainLib::getImport($this->eGauge);
		$this->assertEquals(0, $import['added']);
		$this->assertEquals(2, $import['replaced']);

		RainLib::import($this->eGauge);

		$this->assertEquals(['2025-10-03' => 4.2, '2025-10-04' => 5.3], $this->getDays());

	}

	public function testImportWithoutFileIsRefused(): void {

		$fw = new \FailWatch();

		$this->assertFalse(RainLib::import($this->eGauge));
		$this->assertTrue($fw->has('Rain::importMissing'));

	}

}
?>
