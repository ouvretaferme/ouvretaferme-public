<?php
namespace util;

/**
 * CSV package test page
 *
 * @author Laurent Bardin
 */
class CsvTest extends \Test {

	public $csv;

	public function init() {
		$this->csv = new \util\CsvLib();
	}

	public function testCsv() {
		$this->assertEquals("foo\n", $this->csv->toCsv([['foo']]));
		$this->assertEquals("foo;bar\n", $this->csv->toCsv([['foo','bar']]));
		$this->assertEquals("foo\nbar\n", $this->csv->toCsv([['foo'], ['bar']]));

		$this->assertEquals("\"foo;bar\"\n", $this->csv->toCsv([['foo;bar']]));
		$this->assertEquals("\"foo ;bar\"\n", $this->csv->toCsv([['foo ;bar']]));
		$this->assertEquals("\"foo;b ar\"\n", $this->csv->toCsv([['foo;b ar']]));
		$this->assertEquals("foo;\"bar \"\n", $this->csv->toCsv([['foo','bar ']]));
		$this->assertEquals("\"fo\"\"o\"\nbar\n", $this->csv->toCsv([['fo"o'], ['bar']]));

		$this->assertEquals("\"foo\nbar\"\n", $this->csv->toCsv([["foo\nbar"]]));
		$this->assertEquals("\"foo\r\nbar\"\n", $this->csv->toCsv([["foo\r\nbar"]]));
		$this->assertEquals("\"foo;b\"\"ar\"\n", $this->csv->toCsv([['foo;b"ar']]));
	}

	public function testSeparator() {
		$this->assertEquals("foo,bar\n", $this->csv->toCsv([['foo', 'bar']], ','));
		$this->assertEquals("foo,bar;baz\n", $this->csv->toCsv([['foo,bar', 'baz']]), 'la virgule n\'est pas citée avec le point-virgule');
	}

	/**
	 * Un tableur exécute une cellule qui commence par =, +, -, @, une tabulation ou un retour chariot :
	 * un client nommé =HYPERLINK(...) piégeait l'export de la structure.
	 */
	public function testFormulasAreNeutralized() {

		$this->assertEquals("\"'=HYPERLINK(\"\"https://evil.example\"\")\"\n", $this->csv->toCsv([['=HYPERLINK("https://evil.example")']]), 'une formule');
		$this->assertEquals("'+cmd\n", $this->csv->toCsv([['+cmd']]), 'un plus');
		$this->assertEquals("'-cmd\n", $this->csv->toCsv([['-cmd']]), 'un moins');
		$this->assertEquals("'@SUM(A1)\n", $this->csv->toCsv([['@SUM(A1)']]), 'une arobase');
		$this->assertEquals("\"'\tcmd\"\n", $this->csv->toCsv([["\tcmd"]]), 'une tabulation');
		$this->assertEquals("\"'\rcmd\"\n", $this->csv->toCsv([["\rcmd"]]), 'un retour chariot');
		$this->assertEquals("\"'=1;2\"\n", $this->csv->toCsv([['=1;2']]), 'la citation du séparateur reste');

		$this->assertEquals("-12.50;+3;12,5;-7\n", $this->csv->toCsv([['-12.50', '+3', '12,5', -7]]), 'un nombre, en chaîne ou non, reste un nombre');
		$this->assertEquals("Structure;a=b\n", $this->csv->toCsv([['Structure', 'a=b']]), 'un signe ailleurs qu\'en tête ne change rien');
		$this->assertEquals("\"'-10 % de remise\"\n", $this->csv->toCsv([['-10 % de remise']]), 'un libellé qui commence par un moins sans être un nombre est protégé');

	}

	public function testCsvActionNeutralizesFormulasToo() {

		ob_start();
		new \CsvAction([['=cmd', 'ok']], 'zzTest.csv')->run();
		$output = ob_get_clean();

		$this->assertEquals("'=cmd,ok\n", $output, 'l\'action de téléchargement passe par la même écriture, virgule comprise');

	}

}

?>
