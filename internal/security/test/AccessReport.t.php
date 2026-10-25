<?php
namespace security;

/**
 * Le rapport GoAccess se produit dans le répertoire configuré, depuis les journaux lisibles, et ne
 * laisse jamais un fichier à moitié écrit à la place du précédent.
 *
 * Le test appelle réellement GoAccess : il fait partie de l'installation (Dockerfile du conteneur
 * PHP, paquet goaccess sur le serveur), et un test qui passerait sans lui ne prouverait rien.
 *
 * Lancement : dev/test package=security file=AccessReport
 */
class AccessReportTest extends \Test {

	const LINES = [
		'93.23.16.105 - - [23/Sep/2026:00:00:04 +0200] "www.ouvretaferme.org" "GET /presentation/faq HTTP/2.0" 200 1710 "Mozilla/5.0 (Linux; Android 10) Chrome/140.0" 0.012',
		'203.0.113.7 - - [23/Sep/2026:00:00:05 +0200] "www.ouvretaferme.org" "GET /wp-login.php HTTP/1.1" 404 146 "Mozilla/5.0" 0.000',
		'198.51.100.9 - - [23/Sep/2026:00:01:12 +0200] "demo.ouvretaferme.org" "POST /ferme/1/caisse HTTP/2.0" 500 0 "Mozilla/5.0 (Macintosh)" 0.431',
	];

	private string $directory;
	private string $log;
	private string $logRotated;
	private string $accessDirectorySaved;
	private string $accessLogSaved;
	private string $accessLogRotatedSaved;

	public function setUp() {

		$this->directory = sys_get_temp_dir().'/security-access-'.uniqid();
		mkdir($this->directory);

		$this->log = $this->directory.'/access.log';
		$this->logRotated = $this->directory.'/access.log.1';

		$this->accessDirectorySaved = SecuritySetting::$accessDirectory;
		$this->accessLogSaved = SecuritySetting::$accessLog;
		$this->accessLogRotatedSaved = SecuritySetting::$accessLogRotated;

		SecuritySetting::$accessDirectory = $this->directory;
		SecuritySetting::$accessLog = $this->log;
		SecuritySetting::$accessLogRotated = $this->logRotated;

	}

	public function tearDown() {

		SecuritySetting::$accessDirectory = $this->accessDirectorySaved;
		SecuritySetting::$accessLog = $this->accessLogSaved;
		SecuritySetting::$accessLogRotated = $this->accessLogRotatedSaved;

		foreach(glob($this->directory.'/*') as $file) {
			unlink($file);
		}

		rmdir($this->directory);

	}

	public function testGoAccessIsInstalled(): void {

		exec('goaccess --version 2>&1', $output, $code);

		$this->assertEquals(0, $code, 'goaccess absent : apt install goaccess sur le serveur et dans le conteneur PHP, brew install goaccess sur un poste');

	}

	public function testNotConfigured(): void {

		SecuritySetting::$accessDirectory = '';

		$this->assertFalse(AccessReportLib::isConfigured());
		$this->assertFalse(AccessReportLib::exists());
		$this->assertFalse(AccessReportLib::generate());

	}

	public function testNoReadableLogKeepsThePreviousReport(): void {

		file_put_contents(AccessReportLib::getFile(), 'rapport précédent');

		$this->assertEquals([], AccessReportLib::getLogs());
		$this->assertFalse(AccessReportLib::generate());
		$this->assertEquals('rapport précédent', file_get_contents(AccessReportLib::getFile()));
		$this->assertFalse(is_file(AccessReportLib::getTemporaryFile()));

	}

	public function testGenerateFromBothLogs(): void {

		file_put_contents($this->log, self::LINES[0]."\n");
		file_put_contents($this->logRotated, self::LINES[1]."\n".self::LINES[2]."\n");

		$this->assertEquals([$this->log, $this->logRotated], AccessReportLib::getLogs());
		$this->assertTrue(AccessReportLib::generate());
		$this->assertTrue(AccessReportLib::exists());
		$this->assertFalse(is_file(AccessReportLib::getTemporaryFile()), 'le fichier temporaire est renommé');

		$this->assertEquals(3, self::readValidRequests(), 'les trois lignes des deux journaux sont lues');

	}

	public function testGenerateFromTheCurrentLogOnly(): void {

		file_put_contents($this->log, implode("\n", self::LINES)."\n");

		$this->assertEquals([$this->log], AccessReportLib::getLogs(), 'la rotation absente est écartée sans erreur');
		$this->assertTrue(AccessReportLib::generate());
		$this->assertEquals(3, self::readValidRequests());

	}

	public function testGenerateReplacesThePreviousReport(): void {

		file_put_contents(AccessReportLib::getFile(), 'rapport précédent');
		file_put_contents($this->log, self::LINES[0]."\n");

		$this->assertTrue(AccessReportLib::generate());
		$this->assertEquals(1, self::readValidRequests());

	}

	/**
	 * Le rapport HTML embarque ses données en JSON, dont le compte des lignes lues : c'est ce qui
	 * prouve que le log-format est le bon, une ligne non reconnue n'étant pas comptée.
	 */
	private static function readValidRequests(): ?int {

		if(preg_match('/"valid_requests":\s*(\d+)/', file_get_contents(AccessReportLib::getFile()), $match) === 1) {
			return (int)$match[1];
		}

		return NULL;

	}

}
?>
