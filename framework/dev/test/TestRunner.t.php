<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=TestRunner
 */
class TestRunnerTest extends \Test {

	const COUNTERS = ['files', 'classes', 'missingFixtures', 'notApplicable', 'assertions', 'warnings', 'warningsInFailedTests', 'failures', 'warningsBySource', 'warningsInFailedTestsBySource', 'warningsWithoutSource', 'missingFixtureClasses', 'notApplicableClasses'];

	private array $counters = [];
	private bool $summary = FALSE;
	private ?string $running = NULL;
	private array $resets = [];
	private ?\Closure $token = NULL;
	private array $calls = [];

	public function setUp(): void {

		foreach(self::COUNTERS as $counter) {
			$this->counters[$counter] = TestLib::$$counter;
		}

		$this->resets = new \ReflectionProperty(TestLib::class, 'resets')->getValue();
		$this->token = \api\ApiSetting::$devAccessToken;
		$this->calls = [];
		$this->summary = TestLib::$summary;
		$this->running = TestLib::$running;

		TestLib::$summary = FALSE;

	}

	public function tearDown(): void {

		foreach(self::COUNTERS as $counter) {
			TestLib::$$counter = $this->counters[$counter];
		}

		new \ReflectionProperty(TestLib::class, 'resets')->setValue(NULL, $this->resets);

		TestLib::$summary = $this->summary;
		TestLib::$running = $this->running;

		\api\ApiSetting::$devAccessToken = $this->token;
		\user\ConnectionLib::setOnline(new \user\User());

		TestRunnerSubjectTest::$withheld = FALSE;
		TestRunnerSubjectTest::$inapplicable = FALSE;
		TestRunnerSubjectTest::$inapplicableMethod = FALSE;
		TestRunnerSubjectTest::$failing = FALSE;
		TestRunnerSubjectTest::$noticing = FALSE;
		TestRunnerSubjectTest::$noticingSecond = FALSE;
		TestRunnerSubjectTest::$calls = [];

		putenv(TestRunnerSubjectTest::STOP);

	}

	public function testRunningFileIsCounted(): void {

		$this->assertTrue(TestLib::$files > 0, 'le fichier en cours est compté');
		$this->assertTrue(TestLib::$classes > 0, 'la classe en cours est comptée');
		$this->assertEquals(__FUNCTION__, $this->getTestMethod());

	}

	public function testPatternWithoutMatch(): void {

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::run(new \ReflectionPackage('dev', \Package::getApp('dev')), 'AucunFichierDeCeNom');

		$this->assertEquals(0, TestLib::$files);
		$this->assertEquals(0, TestLib::$classes);

		[$code, $output] = $this->finish();

		$this->assertEquals(2, $code);
		$this->assertEquals(PHP_EOL.'Total: 0 classes, 0 missing fixtures, 0 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

	}

	public function testExitCode(): void {

		$this->setCounters(3, 3, 0, 12, 0);
		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertEquals(PHP_EOL.'Total: 3 classes, 0 missing fixtures, 12 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

		$this->setCounters(3, 3, 0, 12, 2);
		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals(PHP_EOL.'Total: 3 classes, 0 missing fixtures, 12 assertions, 0 warnings, 2 failures, 0 s'.PHP_EOL, $output);

		$this->setCounters(1, 0, 1, 0, 0);
		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals(PHP_EOL.'Total: 0 classes, 1 missing fixture, 0 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

	}

	public function testWarningLeavesExitCode(): void {

		$this->setCounters(3, 3, 0, 12, 0, 1);
		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertEquals(PHP_EOL.'Total: 3 classes, 0 missing fixtures, 12 assertions, 1 warning, 0 failures, 0 s'.PHP_EOL, $output);

		ob_start();
		TestLib::addWarning('transaction left open');
		$output = ob_get_clean();

		$this->assertEquals(2, TestLib::$warnings);
		$this->assertEquals("\033[33mWarning: transaction left open\033[00m".PHP_EOL, $output);

	}

	public function testRunCallsResets(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		$output = $this->runSubject();

		$this->assertEquals([
			'reset file', 'init',
			'reset method', 'setUp', 'testFirst', 'tearDown',
			'reset method', 'setUp', 'testSecond', 'tearDown',
			'finalize',
		], TestRunnerSubjectTest::$calls);

		$this->assertEquals(1, TestLib::$files);
		$this->assertEquals(1, TestLib::$classes);
		$this->assertEquals(0, TestLib::$missingFixtures);
		$this->assertEquals(3, TestLib::$assertions);
		$this->assertEquals(0, TestLib::$failures);
		$this->assertTrue(str_contains($output, 'Passed: '.bold(3, '32')), 'la classe jouée imprime son cadre');

	}

	public function testRunSumsFailedAssertions(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$failing = TRUE;

		$this->runSubject();

		$this->assertEquals(3, TestLib::$assertions, 'une assertion rouge compte dans le total des assertions');
		$this->assertEquals(1, TestLib::$failures);

	}

	public function testRunCountsMissingFixture(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$withheld = TRUE;

		$output = $this->runSubject();

		$this->assertEquals(['reset file', 'init'], TestRunnerSubjectTest::$calls);

		$this->assertEquals(1, TestLib::$files);
		$this->assertEquals(0, TestLib::$classes);
		$this->assertEquals(1, TestLib::$missingFixtures);
		$this->assertEquals(0, TestLib::$assertions);
		$this->assertEquals(
			"\033[31mMissing fixture: dev\\TestRunnerSubjectTest, fixture withheld by TestRunnerTest\033[00m".PHP_EOL,
			$output
		);

		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals(
			PHP_EOL.'Total: 0 classes, 1 missing fixture, 0 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL.
			"\033[31mNot run, missing fixture: dev\\TestRunnerSubjectTest\033[00m".PHP_EOL,
			$output
		);

	}

	public function testRunCountsNotApplicable(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$inapplicable = TRUE;

		$output = $this->runSubject();

		$this->assertEquals(['reset file', 'init'], TestRunnerSubjectTest::$calls);

		$this->assertEquals(0, TestLib::$classes);
		$this->assertEquals(0, TestLib::$missingFixtures);
		$this->assertEquals(1, TestLib::$notApplicable);
		$this->assertEquals(0, TestLib::$failures);
		$this->assertEquals(
			"\033[33mNot applicable: dev\\TestRunnerSubjectTest, resource withheld by TestRunnerTest\033[00m".PHP_EOL,
			$output
		);

		[$code, $output] = $this->finish();

		$this->assertEquals(2, $code, 'une passe dont rien ne s\'applique n\'a joué aucune classe');
		$this->assertEquals(
			PHP_EOL.'Total: 0 classes, 0 missing fixtures, 1 not applicable, 0 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL.
			"\033[33mNot run, not applicable here: dev\\TestRunnerSubjectTest (resource withheld by TestRunnerTest)\033[00m".PHP_EOL,
			$output
		);

		$counters = TestLib::getCounters();

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::addCounters($counters);
		TestLib::addCounters($counters);

		$this->assertEquals(2, TestLib::$notApplicable, 'les sentinelles non applicables de deux processus s\'additionnent');
		$this->assertEquals(['dev\\TestRunnerSubjectTest (resource withheld by TestRunnerTest)', 'dev\\TestRunnerSubjectTest (resource withheld by TestRunnerTest)'], TestLib::$notApplicableClasses);

	}

	public function testRunCountsNotApplicableInMethod(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$inapplicableMethod = TRUE;

		$this->runSubject();

		$this->assertTrue(in_array('testFirst', TestRunnerSubjectTest::$calls, TRUE), 'la méthode qui manque sa ressource est jouée');
		$this->assertFalse(in_array('testSecond', TestRunnerSubjectTest::$calls, TRUE), 'les méthodes suivantes ne le sont pas');
		$this->assertTrue(in_array('tearDown', TestRunnerSubjectTest::$calls, TRUE), 'le décor est quand même défait');
		$this->assertEquals(1, TestLib::$classes, 'commencée, la classe reste comptée');
		$this->assertEquals(1, TestLib::$notApplicable);
		$this->assertEquals(0, TestLib::$failures, 'une ressource absente n\'est pas un échec');
		$this->assertEquals(['dev\\TestRunnerSubjectTest (resource withheld in testFirst)'], TestLib::$notApplicableClasses);

	}

	public function testRunCountsPhpNotices(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$noticing = TRUE;

		$output = $this->runSubject();

		$this->assertEquals(2, TestLib::$warnings, 'chaque notice d\'une méthode compte, une par appel');
		$this->assertEquals(0, TestLib::$failures, 'une notice ne fait pas rougir');
		$this->assertEquals(1, count(TestLib::$warningsBySource), 'deux notices de la même ligne se regroupent');

		$source = array_key_first(TestLib::$warningsBySource);

		$this->assertTrue(str_starts_with($source, 'framework/dev/test/TestRunnerSubject.t.php:'), 'la source est le fichier et la ligne de la notice : '.$source);
		$this->assertTrue(str_contains($output, 'Undefined variable $absent'), 'la notice reste imprimée');

		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertTrue(str_contains($output, '2 warnings'), 'le bilan compte les notices');
		$this->assertTrue(str_contains($output, '2 × '.$source), 'le bilan nomme la ligne qui les lève');

	}

	public function testRunSeparatesNoticesOfFailedTests(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$noticing = TRUE;
		TestRunnerSubjectTest::$noticingSecond = TRUE;
		TestRunnerSubjectTest::$failing = TRUE;

		$this->runSubject();

		$this->assertEquals(3, TestLib::$warnings, 'les notices du test vert et du test rouge comptent toutes');
		$this->assertEquals(1, TestLib::$warningsInFailedTests, 'seule la notice du test rouge est mise à part');
		$this->assertEquals(1, count(TestLib::$warningsInFailedTestsBySource), 'la ligne du test vert n\'est pas imputée au test rouge');

		$source = array_key_first(TestLib::$warningsInFailedTestsBySource);

		$this->assertTrue(str_contains($source, 'Undefined variable $missing'), 'la source mise à part est celle du test rouge : '.$source);

		[, $output] = $this->finish();

		$this->assertTrue(str_contains($output, '3 warnings (1 in failed tests), 1 failure'), 'le bilan dit la part des tests en échec : '.$output);
		$this->assertTrue(str_contains($output, '1 × '.$source.' (1 in failed tests)'), 'la ligne du test rouge porte sa part');
		$this->assertFalse(str_contains($output, '$absent (') , 'la ligne du test vert ne porte aucune part');

	}

	public function testRunLeavesNoticesOfPassedTests(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$noticing = TRUE;
		TestRunnerSubjectTest::$failing = TRUE;

		$this->runSubject();

		$this->assertEquals(2, TestLib::$warnings);
		$this->assertEquals(1, TestLib::$failures);
		$this->assertEquals(0, TestLib::$warningsInFailedTests, 'un échec dans une autre méthode ne reprend pas les notices d\'un test vert');

	}

	public function testSummaryOfGreenRunIsTotalOnly(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;

		$this->assertEquals('', $this->runSubject(), 'une classe verte n\'imprime rien');

		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertEquals('Total: 1 class, 0 missing fixtures, 3 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

	}

	public function testSummaryOfRedClassComesWithItsPackage(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;
		TestRunnerSubjectTest::$failing = TRUE;

		$this->assertEquals(
			'dev\\TestRunnerSubjectTest, 1 failure'.PHP_EOL.
			' testSecond() in framework/dev/test/TestRunnerSubject.t.php:'.$this->getSubjectLine('échec demandé par TestRunnerTest').PHP_EOL.
			'  * Expected: false'.PHP_EOL.
			'  * Received: true'.PHP_EOL.
			'  * Message: échec demandé par TestRunnerTest'.PHP_EOL.
			PHP_EOL,
			$this->runSubject(),
			'la classe rouge s\'imprime sans attendre la fin de la passe'
		);

		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals('Total: 1 class, 0 missing fixtures, 3 assertions, 0 warnings, 1 failure, 0 s'.PHP_EOL, $output);

	}

	public function testCriticalErrorNamesItsClass(): void {

		$this->setCounters(0, 0, 0, 0, 0);

		new \ReflectionProperty(TestLib::class, 'resets')->setValue(NULL, [
			TestLib::FILE => [function() {
				throw new \Exception('reset broken by TestRunnerTest');
			}],
			TestLib::METHOD => [],
		]);

		$this->assertEquals("\033[31mCRITICAL ERROR: dev\\TestRunnerSubjectTest, reset broken by TestRunnerTest\033[00m".PHP_EOL, $this->runSubject());

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;

		$this->assertEquals('CRITICAL ERROR: dev\\TestRunnerSubjectTest, reset broken by TestRunnerTest'.PHP_EOL.PHP_EOL, $this->runSubject());

		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals('Total: 0 classes, 0 missing fixtures, 0 assertions, 0 warnings, 1 failure, 0 s'.PHP_EOL, $output);

	}

	public function testRunningNamesTheMethod(): void {

		$running = 'framework/dev/test/TestRunner.t.php, dev\\TestRunnerTest::testRunningNamesTheMethod()';

		$this->assertEquals($running, TestLib::$running);

		$this->setCounters(0, 0, 0, 0, 0);
		$this->runSubject();

		$this->assertEquals($running, TestLib::$running, 'un run() imbriqué rend le test qui l\'a lancé');

	}

	public function testFatalErrorText(): void {

		$memory = [
			'type' => E_ERROR,
			'message' => 'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
			'file' => LIME_DIRECTORY.'/framework/dev/test/TestRunnerSubject.t.php',
			'line' => 12,
		];

		$this->assertEquals(NULL, TestLib::getFatalErrorText(NULL));
		$this->assertEquals(NULL, TestLib::getFatalErrorText(['type' => E_WARNING] + $memory), 'un avertissement n\'arrête pas la passe');

		TestLib::$running = 'framework/dev/test/TestRunnerSubject.t.php, dev\\TestRunnerSubjectTest::testFirst()';
		TestLib::$summary = TRUE;

		$this->assertEquals(
			'FATAL ERROR: the run stopped without a verdict, in framework/dev/test/TestRunnerSubject.t.php, dev\\TestRunnerSubjectTest::testFirst().'.PHP_EOL.
			'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in framework/dev/test/TestRunnerSubject.t.php:12 (memory_limit '.ini_get('memory_limit').')'.PHP_EOL,
			TestLib::getFatalErrorText($memory)
		);

		TestLib::$running = NULL;
		TestLib::$summary = FALSE;

		$this->assertEquals(
			"\033[31mFATAL ERROR: the run stopped without a verdict, outside any test.\033[00m".PHP_EOL.
			'Maximum execution time of 30 seconds exceeded in framework/dev/test/TestRunnerSubject.t.php:12'.PHP_EOL,
			TestLib::getFatalErrorText(['message' => 'Maximum execution time of 30 seconds exceeded'] + $memory)
		);

	}

	public function testChangedFileText(): void {

		// Un fichier réel, pour que l'heure lue soit celle que la passe rendrait ; l'état est donné à la
		// place de git, que l'essai ne doit pas faire dépendre de ce que l'arbre porte à l'instant.
		$path = 'framework/dev/test/TestRunnerSubject.t.php';
		$written = date('Y-m-d H:i:s', filemtime(LIME_DIRECTORY.'/'.$path));

		$this->assertEquals('', TestLib::getChangedFileText($path, NULL), 'git n\'a pas répondu');
		$this->assertEquals('', TestLib::getChangedFileText($path, []), 'un fichier que l\'arbre porte tel que suivi');

		$this->assertEquals(
			$path.' is untracked in the working tree (git status "??"), last written on '.$written.'. If another session is still writing it, run the tests again.'.PHP_EOL,
			TestLib::getChangedFileText($path, ['?? '.$path])
		);

		$this->assertEquals(
			$path.' is changed in the working tree (git status " M"), last written on '.$written.'. If another session is still writing it, run the tests again.'.PHP_EOL,
			TestLib::getChangedFileText($path, [' M '.$path])
		);

	}

	public function testOutputIsWrittenAsItComes(): void {

		$stream = fopen('php://memory', 'w+');

		ob_start();

		TestLib::startOutput($stream);

		echo 'rapport du premier fichier';
		$retained = ob_get_contents();

		ob_end_flush();

		$passed = ob_get_clean();

		rewind($stream);

		$this->assertEquals('', $retained, 'rien ne reste dans le tampon');
		$this->assertEquals('', $passed, 'rien ne passe à PHP, qui tiendrait les en-têtes pour envoyés');
		$this->assertEquals('rapport du premier fichier', stream_get_contents($stream));

	}

	public function testSummaryNamesMissingFixture(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;
		TestRunnerSubjectTest::$withheld = TRUE;

		$this->assertEquals('Missing fixture: dev\\TestRunnerSubjectTest, fixture withheld by TestRunnerTest'.PHP_EOL.PHP_EOL, $this->runSubject());

		[$code, $output] = $this->finish();

		$this->assertEquals(1, $code);
		$this->assertEquals('Total: 0 classes, 1 missing fixture, 0 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

	}

	public function testSummaryGroupsWarningsBeforeTotal(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;
		TestRunnerSubjectTest::$noticing = TRUE;

		$this->assertEquals('', $this->runSubject(), 'les notices ne s\'impriment pas au fil de la passe');

		ob_start();
		TestLib::addWarning('transaction left open');
		ob_end_clean();

		$source = array_key_first(TestLib::$warningsBySource);

		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertEquals(
			'2 × '.$source.PHP_EOL.
			'1 × transaction left open'.PHP_EOL.
			PHP_EOL.
			'Total: 1 class, 0 missing fixtures, 3 assertions, 3 warnings, 0 failures, 0 s'.PHP_EOL,
			$output
		);

	}

	public function testAssertErrorStillCatchesInsideRun(): void {

		$caught = $this->assertError(fn() => trigger_error('attendue', E_USER_WARNING), FALSE, 'attendue');

		$this->assertTrue($caught, 'assertError() attrape une erreur malgré le gestionnaire du lanceur');

	}

	public function testResetsRunInOrder(): void {

		new \ReflectionProperty(TestLib::class, 'resets')->setValue(NULL, [
			TestLib::FILE => [],
			TestLib::METHOD => [],
		]);

		TestLib::addReset(function() {
			$this->calls[] = 'first';
		});
		TestLib::addReset(function() {
			$this->calls[] = 'method';
		}, TestLib::METHOD);
		TestLib::addReset(function() {
			$this->calls[] = 'second';
		});

		TestLib::reset(TestLib::FILE);
		$this->assertEquals(['first', 'second'], $this->calls);

		TestLib::reset(TestLib::METHOD);
		$this->assertEquals(['first', 'second', 'method'], $this->calls);

		try {
			TestLib::addReset(fn() => NULL, 'campaign');
			$this->assertFail('une portée inconnue est refusée');
		} catch(\Exception $e) {
			$this->assertException($e, 'Unknown reset scope \'campaign\'');
		}

	}

	public function testResetRestoresTokenAndUser(): void {

		$token = fn() => new \api\AccessToken();

		\api\ApiSetting::$devAccessToken = $token;
		\user\ConnectionLib::setOnline(new \user\User(['id' => 1]));

		TestLib::reset(TestLib::FILE);

		$this->assertTrue(\api\ApiSetting::$devAccessToken !== $token, 'le jeton posé par un test ne survit pas au fichier');
		$this->assertTrue(\api\ApiSetting::$devAccessToken === $this->token, 'le jeton est celui que le fichier a trouvé en commençant');
		$this->assertTrue(in_array(\api\ApiSetting::$devAccessToken, $this->getResetValues(), TRUE), 'le jeton rendu est une valeur retenue au démarrage par une remise à zéro');
		$this->assertTrue(\user\ConnectionLib::getOnline()->empty(), 'l\'utilisateur connecté est vidé');

	}

	public function testUnknownPackageExitsNonZero(): void {

		[$code, $output] = $this->runCommand(['package' => 'zzzpkg', 'file' => 'TestRunnerSubject']);

		$this->assertEquals(2, $code, 'un package inconnu ne sort pas en code 0, qu\'un script lirait comme vert');
		$this->assertEquals("Error: Package 'zzzpkg' does not exist\n", $output);

	}

	public function testJobsRefusedOutsideSentinels(): void {

		[$code, $output] = $this->runCommand(['package' => 'dev', 'file' => 'TestRunnerSubject', 'jobs' => 3]);

		$this->assertEquals(2, $code, 'jobs est refusé hors des sentinelles, les autres tests écrivant en base');
		$this->assertEquals("Error: jobs only runs with file='*Sentinel*', the other tests write in the database\n", $output);

		[$code, $output] = $this->runCommand(['package' => 'dev', 'file' => TestJobLib::PATTERN, 'jobs' => 0]);

		$this->assertEquals(2, $code);
		$this->assertEquals("Error: jobs must be a positive number\n", $output);

	}

	public function testGetFilesSkipsAbstractClass(): void {

		$files = TestLib::getFiles(new \ReflectionPackage('dev', \Package::getApp('dev')), TestJobLib::PATTERN);

		$this->assertTrue(in_array('ClockSentinel', $files), 'une sentinelle est dans la liste');
		$this->assertFalse(in_array('Sentinel', $files), 'Sentinel.t.php ne porte qu\'une classe abstraite, qu\'aucun processus ne jouerait');

		$this->assertEquals(['TestRunnerSubject'], TestLib::getFiles(new \ReflectionPackage('dev', \Package::getApp('dev')), 'AucunFichierDeCeNom,TestRunnerSubject'));

	}

	public function testCountersAddUp(): void {

		$this->setSubjectResets();
		$this->setCounters(0, 0, 0, 0, 0);

		TestRunnerSubjectTest::$noticing = TRUE;
		TestRunnerSubjectTest::$failing = TRUE;

		$this->runSubject();

		TestRunnerSubjectTest::$noticing = FALSE;
		TestRunnerSubjectTest::$failing = FALSE;
		TestRunnerSubjectTest::$withheld = TRUE;

		$this->runSubject();

		ob_start();
		TestLib::addWarning('transaction left open');
		ob_end_clean();

		$counters = TestLib::getCounters();

		[, $serial] = $this->finish();

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::addCounters($counters);
		TestLib::addCounters($counters);

		$this->assertEquals(2, TestLib::$classes);
		$this->assertEquals(2, TestLib::$missingFixtures);
		$this->assertEquals(6, TestLib::$assertions);
		$this->assertEquals(2, TestLib::$failures);
		$this->assertEquals(6, TestLib::$warnings);
		$this->assertEquals([4], array_values(TestLib::$warningsBySource), 'deux processus qui lèvent la même notice la disent en une ligne');
		$this->assertEquals(['transaction left open' => 2], TestLib::$warningsWithoutSource);
		$this->assertEquals(['dev\\TestRunnerSubjectTest', 'dev\\TestRunnerSubjectTest'], TestLib::$missingFixtureClasses);

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::addCounters($counters);

		[, $added] = $this->finish();

		$this->assertEquals($serial, $added, 'les compteurs d\'un seul processus rendent le bilan de la passe série');

	}

	public function testJobsRunFilesInProcesses(): void {

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;

		[$stopped, $output] = $this->runJobs(2);

		$this->assertFalse($stopped);
		$this->assertEquals('', $output, 'une classe verte n\'imprime rien, pas plus dans un autre processus');

		[$code, $output] = $this->finish();

		$this->assertEquals(0, $code);
		$this->assertEquals('Total: 2 classes, 0 missing fixtures, 6 assertions, 0 warnings, 0 failures, 0 s'.PHP_EOL, $output);

	}

	public function testJobsNameAFatalError(): void {

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;

		putenv(TestRunnerSubjectTest::STOP.'=memory');

		[$stopped, $output] = $this->runJobs(1);

		$this->assertTrue($stopped, 'un processus arrêté laisse la passe sans verdict');
		$this->assertTrue(str_contains($output, 'FATAL ERROR: the run stopped without a verdict, in framework/dev/test/TestRunnerSubject.t.php, dev\\TestRunnerSubjectTest::testFirst().'.PHP_EOL), 'le processus nomme le test arrêté : '.$output);
		$this->assertEquals(2, substr_count($output, 'FATAL ERROR: '), 'chaque processus arrêté se nomme, et une seule fois : '.$output);
		$this->assertTrue(str_contains($output, 'Allowed memory size'), 'la cause est dite');
		$this->assertEquals(0, TestLib::$classes, 'un processus arrêté ne rend aucun compteur');

	}

	public function testJobsNameAKilledProcess(): void {

		$this->setCounters(0, 0, 0, 0, 0);

		TestLib::$summary = TRUE;

		putenv(TestRunnerSubjectTest::STOP.'=kill');

		[$stopped, $output] = $this->runJobs(1);

		$this->assertTrue($stopped);
		$this->assertEquals(2, substr_count($output, 'FATAL ERROR: the run stopped without a verdict, in framework/dev/test/TestRunnerSubject.t.php, whose process was killed by signal 9.'), 'un processus tué sans rien dire est nommé par celui qui l\'a lancé : '.$output);

	}

	/**
	 * Joue deux fois `TestRunnerSubject`, chacun dans son processus.
	 */
	private function runJobs(int $jobs): array {

		$package = new \ReflectionPackage('dev', \Package::getApp('dev'));

		ob_start();

		$stopped = TestJobLib::run([[$package, 'TestRunnerSubject'], [$package, 'TestRunnerSubject']], $jobs, ['summary' => 1]);

		return [$stopped, ob_get_clean()];

	}

	/**
	 * Lance `dev/test` dans un autre processus, pour lire son code de sortie.
	 */
	private function runCommand(array $arguments): array {

		// Le tableau s'écrit en littéral pour que ShellCommandSentinel y lise la forme sans shell.
		$process = proc_open([...TestJobLib::getCommand($arguments)], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $output];

	}

	/**
	 * Les valeurs que les remises à zéro du fichier ont retenues par `use` en s'inscrivant.
	 */
	private function getResetValues(): array {

		$values = [];

		foreach($this->resets[TestLib::FILE] as $reset) {
			$values = array_merge($values, array_values(new \ReflectionFunction($reset)->getStaticVariables()));
		}

		return $values;

	}

	private function getSubjectLine(string $text): int {

		foreach(file(__DIR__.'/TestRunnerSubject.t.php') as $position => $line) {

			if(str_contains($line, $text)) {
				return $position + 1;
			}

		}

		throw new \Exception('Text \''.$text.'\' not found in TestRunnerSubject.t.php');

	}

	private function setSubjectResets(): void {

		new \ReflectionProperty(TestLib::class, 'resets')->setValue(NULL, [
			TestLib::FILE => [function() {
				TestRunnerSubjectTest::$calls[] = 'reset file';
			}],
			TestLib::METHOD => [function() {
				TestRunnerSubjectTest::$calls[] = 'reset method';
			}],
		]);

		TestRunnerSubjectTest::$calls = [];

	}

	private function runSubject(): string {

		ob_start();

		TestLib::run(new \ReflectionPackage('dev', \Package::getApp('dev')), 'TestRunnerSubject');

		return ob_get_clean();

	}

	private function setCounters(int $files, int $classes, int $missingFixtures, int $assertions, int $failures, int $warnings = 0): void {

		TestLib::$files = $files;
		TestLib::$classes = $classes;
		TestLib::$missingFixtures = $missingFixtures;
		TestLib::$assertions = $assertions;
		TestLib::$failures = $failures;
		TestLib::$warnings = $warnings;
		TestLib::$warningsInFailedTests = 0;
		TestLib::$warningsBySource = [];
		TestLib::$warningsInFailedTestsBySource = [];
		TestLib::$warningsWithoutSource = [];
		TestLib::$missingFixtureClasses = [];
		TestLib::$notApplicable = 0;
		TestLib::$notApplicableClasses = [];

	}

	private function finish(): array {

		ob_start();

		$code = TestLib::finish(microtime(TRUE));

		return [$code, ob_get_clean()];

	}

}
?>
