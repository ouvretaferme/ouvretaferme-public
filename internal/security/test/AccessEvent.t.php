<?php
namespace security;

/**
 * Le cron des événements d'accès lit le journal Nginx depuis son dernier passage, compte par heure,
 * écrit un événement au seuil et le met à jour ensuite, survit à la rotation, et ne prévient Discord
 * que pour ce qui le mérite.
 *
 * Le journal est un décor écrit dans un répertoire temporaire, au format de la production ; la base
 * est rendue par la transaction, le webhook Discord est vidé pour la durée du test.
 *
 * Lancement : dev/test package=security file=AccessEvent
 */
class AccessEventTest extends \Test {

	const SCANNER = '203.0.113.201';
	const FLOODER = '198.51.100.7';
	const VISITOR = '93.23.16.105';

	private string $directory;
	private string $log;
	private string $logRotated;
	private string $accessDirectorySaved;
	private string $accessLogSaved;
	private string $accessLogRotatedSaved;
	private string $webhookSaved;

	public function setUp(): void {

		$this->directory = sys_get_temp_dir().'/security-events-'.uniqid();
		mkdir($this->directory);

		$this->log = $this->directory.'/access.log';
		$this->logRotated = $this->directory.'/access.log.1';

		$this->accessDirectorySaved = SecuritySetting::$accessDirectory;
		$this->accessLogSaved = SecuritySetting::$accessLog;
		$this->accessLogRotatedSaved = SecuritySetting::$accessLogRotated;

		SecuritySetting::$accessDirectory = $this->directory;
		SecuritySetting::$accessLog = $this->log;
		SecuritySetting::$accessLogRotated = $this->logRotated;

		$this->webhookSaved = \communication\CommunicationSetting::$discordSecurityWebhookUrl;
		\communication\CommunicationSetting::$discordSecurityWebhookUrl = '';

		AccessEvent::model()->beginTransaction();

	}

	public function tearDown(): void {

		AccessEvent::model()->rollBack();

		\communication\CommunicationSetting::$discordSecurityWebhookUrl = $this->webhookSaved;

		SecuritySetting::$accessDirectory = $this->accessDirectorySaved;
		SecuritySetting::$accessLog = $this->accessLogSaved;
		SecuritySetting::$accessLogRotated = $this->accessLogRotatedSaved;

		foreach(glob($this->directory.'/*') as $file) {
			unlink($file);
		}

		rmdir($this->directory);

	}

	public function testParse(): void {

		$hit = AccessEventLib::parse(self::line(self::VISITOR, '23/Sep/2026:04:12:07', 'www.ouvretaferme.org', 'GET /presentation/faq?onglet=2 HTTP/2.0', 200));

		$this->assertEquals(self::VISITOR, $hit['ip']);
		$this->assertEquals('2026-09-23 04:00:00', $hit['hour']);
		$this->assertEquals('www.ouvretaferme.org', $hit['host']);
		$this->assertEquals('/presentation/faq', $hit['path'], 'la query string ne fait pas partie du chemin');
		$this->assertEquals(200, $hit['status']);

		$this->assertEquals(NULL, AccessEventLib::parse('2001:db8::7 - - [23/Sep/2026:04:12:07 +0200] "www.ouvretaferme.org" "GET / HTTP/2.0" 200 12 "Mozilla/5.0" 0.001'."\n"), 'une adresse IPv6 est ignorée');
		$this->assertEquals(NULL, AccessEventLib::parse("pas une ligne de journal\n"));

		$hit = AccessEventLib::parse(self::line(self::SCANNER, '23/Sep/2026:04:12:07', 'www.ouvretaferme.org', '\x16\x03\x01\x02', 400));
		$this->assertEquals('\x16\x03\x01\x02', $hit['path'], 'une requête sans verbe ni protocole garde son texte pour chemin');

		$hit = AccessEventLib::parse(self::line(self::SCANNER, '23/Sep/2026:04:12:07', str_repeat('h', 150), 'GET /'.str_repeat('p', 300).' HTTP/1.1', 404));
		$this->assertEquals(AccessEventLib::HOST_LENGTH, mb_strlen($hit['host']));
		$this->assertEquals(AccessEventLib::PATH_LENGTH, mb_strlen($hit['path']));

	}

	public function testNotConfigured(): void {

		SecuritySetting::$accessDirectory = '';

		file_put_contents($this->log, self::scan(AccessEventLib::SCAN_THRESHOLD, '04'));

		$this->assertEquals(NULL, AccessEventLib::collect());
		$this->assertEquals(0, $this->count());
		$this->assertFalse(is_file($this->directory.'/'.AccessEventLib::CURSOR));

	}

	public function testNoLog(): void {

		$this->assertEquals(0, AccessEventLib::collect());
		$this->assertEquals(0, $this->count());

	}

	public function testScanBelowThenAtThreshold(): void {

		file_put_contents($this->log, self::scan(AccessEventLib::SCAN_THRESHOLD - 1, '04'));

		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD - 1, AccessEventLib::collect());
		$this->assertEquals(0, $this->count(), 'sous le seuil, rien ne s\'écrit');

		file_put_contents($this->log, self::line(self::SCANNER, '23/Sep/2026:04:59:59', 'www.ouvretaferme.org', 'GET /wp-login.php HTTP/1.1', 404), FILE_APPEND);

		$this->assertEquals(1, AccessEventLib::collect(), 'le second passage ne lit que la ligne ajoutée');
		$this->assertEquals(1, $this->count());

		$e = $this->getOne();

		$this->assertEquals(AccessEvent::SCAN, $e['type']);
		$this->assertEquals(self::SCANNER, $e['ip']);
		$this->assertEquals('2026-09-23 04:00:00', $e['hour']);
		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD, $e['count']);
		$this->assertEquals('www.ouvretaferme.org', $e['host'], 'le dernier hôte vu');
		$this->assertEquals('/wp-login.php', $e['path'], 'le dernier chemin vu');
		$this->assertEquals(404, $e['status']);
		$this->assertEquals(2, $e['hosts'], 'les hôtes distincts sur l\'heure, dont celui de la dernière ligne');
		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD, $e['paths'], 'un chemin distinct par ligne');
		$this->assertEquals(NULL, $e['notifiedAt'], 'sous NOTIFY_SCAN, Discord n\'est pas prévenu');

	}

	public function testRefusedByNginxIsStillAScan(): void {

		$lines = '';

		for($i = 0; $i < AccessEventLib::SCAN_THRESHOLD; $i++) {
			$lines .= self::line(self::SCANNER, '23/Sep/2026:04:'.sprintf('%02d', $i % 60).':'.sprintf('%02d', $i % 60), 'www.ouvretaferme.org', 'GET /wp-admin/setup-config.php HTTP/1.1', 444);
		}

		file_put_contents($this->log, $lines);

		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD, AccessEventLib::collect());
		// Borné à l'adresse du décor, et non `count()` comme ses voisins : la table porte les
		// événements de la base de développement, qui est une copie de production
		$cAccessEvent = AccessEvent::model()
			->select(AccessEvent::getSelection())
			->whereIp(self::SCANNER)
			->whereHour('2026-09-23 04:00:00')
			->getCollection();

		$this->assertEquals(1, $cAccessEvent->count(), 'un chemin que scan.cfg refuse reste compté, Nginx journalisant son 444 et 444 étant un 4xx');

		$e = $cAccessEvent->first();

		$this->assertEquals(AccessEvent::SCAN, $e['type']);
		$this->assertEquals(444, $e['status'], 'le statut du refus, et non celui d\'une page d\'erreur que PHP n\'a pas rendue');
		$this->assertEquals('/wp-admin/setup-config.php', $e['path'], 'le chemin refusé reste visible sur l\'écran de surveillance');

		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD, AccessEventLib::readCursor()['buckets']['scan|2026-09-23 04:00:00|'.self::SCANNER]['refused'], 'le seau compte ses 444, que l\'alerte écarte');

	}

	/**
	 * Un seau mêlé ne compte comme refusé que ce que Nginx rend seul, le 444 et le 400 d'une requête
	 * illisible : un 404 a atteint PHP, un 400 sur un chemin aussi.
	 */
	public function testRefusedCountsWhatNginxAnswersAlone(): void {

		$lines = self::scan(3, '04');

		for($i = 0; $i < 2; $i++) {
			$lines .= self::line(self::SCANNER, '23/Sep/2026:04:30:0'.$i, 'www.ouvretaferme.org', 'GET /wordpress/ HTTP/1.1', 444);
		}

		$lines .= self::line(self::SCANNER, '23/Sep/2026:04:31:00', 'www.ouvretaferme.org', '-', 400);
		$lines .= self::line(self::SCANNER, '23/Sep/2026:04:31:01', 'www.ouvretaferme.org', 'GET /shop/?page=x HTTP/1.1', 400);

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$bucket = AccessEventLib::readCursor()['buckets']['scan|2026-09-23 04:00:00|'.self::SCANNER];

		$this->assertEquals(7, $bucket['count']);
		$this->assertEquals(3, $bucket['refused'], 'les deux 444 et le 400 sans requête lisible');

	}

	/**
	 * Un curseur écrit avant le compte des refus se relit sans lever : le seau ouvert reprend à zéro
	 * refus, et le passage suivant y ajoute les siens.
	 */
	public function testCursorWithoutRefusedIsRead(): void {

		file_put_contents($this->log, '');

		AccessEventLib::writeCursor([
			'inode' => fileinode($this->log),
			'offset' => 0,
			'latestHour' => '2026-09-23 04:00:00',
			'buckets' => [
				'scan|2026-09-23 04:00:00|'.self::SCANNER => ['type' => AccessEvent::SCAN, 'hour' => '2026-09-23 04:00:00', 'ip' => self::SCANNER, 'count' => 7, 'id' => NULL, 'notified' => FALSE, 'hosts' => [], 'paths' => []],
			],
		]);

		file_put_contents($this->log, self::line(self::SCANNER, '23/Sep/2026:04:40:00', 'www.ouvretaferme.org', 'GET /wordpress/ HTTP/1.1', 444));

		$this->assertEquals(1, AccessEventLib::collect());

		$bucket = AccessEventLib::readCursor()['buckets']['scan|2026-09-23 04:00:00|'.self::SCANNER];

		$this->assertEquals(8, $bucket['count']);
		$this->assertEquals(1, $bucket['refused']);

	}

	public function testScanUpdatesUntilTheHourEnds(): void {

		file_put_contents($this->log, self::scan(AccessEventLib::SCAN_THRESHOLD, '04'));
		AccessEventLib::collect();

		file_put_contents($this->log, self::scan(50, '04', 1000), FILE_APPEND);
		AccessEventLib::collect();

		$this->assertEquals(1, $this->count(), 'le même seau met à jour l\'événement, il n\'en crée pas un second');
		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD + 50, $this->getOne()['count']);

		file_put_contents($this->log, self::scan(AccessEventLib::SCAN_THRESHOLD, '05'), FILE_APPEND);
		AccessEventLib::collect();

		$this->assertEquals(2, $this->count(), 'l\'heure suivante ouvre un nouvel événement');

	}

	public function testHoursAreSeparated(): void {

		file_put_contents($this->log, self::scan(AccessEventLib::SCAN_THRESHOLD - 1, '04').self::scan(AccessEventLib::SCAN_THRESHOLD - 1, '05'));
		AccessEventLib::collect();

		$this->assertEquals(0, $this->count(), 'deux heures sous le seuil ne s\'additionnent pas');

	}

	public function testFloodIsNotAScan(): void {

		$lines = '';

		for($i = 0; $i < AccessEventLib::FLOOD_THRESHOLD; $i++) {
			$lines .= self::line(self::FLOODER, '23/Sep/2026:04:00:'.sprintf('%02d', $i % 60), 'www.ouvretaferme.org', 'GET /ferme/6436/vente HTTP/2.0', 429);
		}

		$lines .= self::scan(AccessEventLib::SCAN_THRESHOLD - 1, '04', 0, self::FLOODER);

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$this->assertEquals(1, $this->count(), 'les 429 ne comptent pas dans le balayage de la même adresse');

		$e = $this->getOne();

		$this->assertEquals(AccessEvent::FLOOD, $e['type']);
		$this->assertEquals(self::FLOODER, $e['ip']);
		$this->assertEquals(AccessEventLib::FLOOD_THRESHOLD, $e['count']);
		$this->assertEquals(429, $e['status']);
		$this->assertEquals('/ferme/6436/vente', $e['path']);
		$this->assertEquals(NULL, $e['hosts']);
		$this->assertEquals(NULL, $e['paths']);

	}

	public function testErrorByHostAndPath(): void {

		$lines = '';

		for($i = 0; $i < AccessEventLib::ERROR_THRESHOLD - 1; $i++) {
			$lines .= self::line('93.23.16.'.($i + 1), '23/Sep/2026:09:0'.($i % 10).':00', 'demo.ouvretaferme.org', 'POST /ferme/6436/caisse HTTP/2.0', 502);
		}

		$lines .= self::line(self::VISITOR, '23/Sep/2026:09:30:00', 'demo.ouvretaferme.org', 'POST /ferme/6436/vente HTTP/2.0', 502);

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$this->assertEquals(0, $this->count(), 'un autre chemin du même hôte ne compte pas avec');

		file_put_contents($this->log, self::line(self::VISITOR, '23/Sep/2026:09:31:00', 'demo.ouvretaferme.org', 'POST /ferme/6436/caisse?x=1 HTTP/2.0', 500), FILE_APPEND);
		AccessEventLib::collect();

		$this->assertEquals(1, $this->count());

		$e = $this->getOne();

		$this->assertEquals(AccessEvent::ERROR, $e['type']);
		$this->assertEquals(NULL, $e['ip'], 'une route en erreur n\'a pas d\'adresse');
		$this->assertEquals('demo.ouvretaferme.org', $e['host']);
		$this->assertEquals('/ferme/6436/caisse', $e['path']);
		$this->assertEquals(500, $e['status'], 'le dernier statut vu');
		$this->assertEquals(AccessEventLib::ERROR_THRESHOLD, $e['count']);
		$this->assertEquals(NULL, $e['notifiedAt']);

	}

	public function testIncompleteLastLineWaitsForTheNextPass(): void {

		$complete = self::scan(AccessEventLib::SCAN_THRESHOLD - 1, '04');
		$partial = rtrim(self::line(self::SCANNER, '23/Sep/2026:04:59:59', 'www.ouvretaferme.org', 'GET /wp-login.php HTTP/1.1', 404));

		file_put_contents($this->log, $complete.$partial);

		$this->assertEquals(AccessEventLib::SCAN_THRESHOLD - 1, AccessEventLib::collect(), 'la ligne en cours d\'écriture n\'est pas lue');
		$this->assertEquals(0, $this->count());

		file_put_contents($this->log, "\n", FILE_APPEND);

		$this->assertEquals(1, AccessEventLib::collect(), 'complétée, elle est lue une fois');
		$this->assertEquals(1, $this->count());

	}

	public function testRotation(): void {

		file_put_contents($this->log, self::scan(60, '04'));
		AccessEventLib::collect();

		file_put_contents($this->log, self::scan(20, '04', 100), FILE_APPEND);

		rename($this->log, $this->logRotated);
		file_put_contents($this->log, self::scan(20, '04', 200));

		$this->assertEquals(40, AccessEventLib::collect(), 'la fin du journal tourné puis le nouveau journal depuis le début');
		$this->assertEquals(1, $this->count());
		$this->assertEquals(100, $this->getOne()['count']);

		file_put_contents($this->log, self::scan(5, '04', 300), FILE_APPEND);

		$this->assertEquals(5, AccessEventLib::collect(), 'le curseur suit le nouveau journal');
		$this->assertEquals(105, $this->getOne()['count']);

	}

	public function testTruncatedLogIsReadFromTheStart(): void {

		file_put_contents($this->log, self::scan(60, '04'));
		AccessEventLib::collect();

		file_put_contents($this->log, self::scan(40, '04', 100));

		$this->assertEquals(40, AccessEventLib::collect(), 'un journal plus court que la position gardée est relu depuis le début');
		$this->assertEquals(1, $this->count());

	}

	public function testCursorIsAtomicAndPruned(): void {

		file_put_contents($this->log, self::scan(10, '04').self::scan(10, '06'));
		AccessEventLib::collect();

		$cursor = AccessEventLib::readCursor();

		$this->assertEquals(fileinode($this->log), $cursor['inode']);
		$this->assertEquals(filesize($this->log), $cursor['offset']);
		$this->assertEquals('2026-09-23 06:00:00', $cursor['latestHour']);
		$this->assertEquals(['scan|2026-09-23 06:00:00|'.self::SCANNER], array_keys($cursor['buckets']), 'les seaux de plus d\'une heure avant la dernière ligne sont oubliés');
		$this->assertFalse(is_file(AccessEventLib::getCursorFile().'.new'));

	}

	public function testIsNotifiable(): void {

		$eAccessEventFlood = new AccessEvent(['type' => AccessEvent::FLOOD, 'count' => AccessEventLib::FLOOD_THRESHOLD, 'refused' => 0, 'notifiedAt' => NULL]);
		$eAccessEventScan = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN - 1, 'refused' => 0, 'notifiedAt' => NULL]);
		$eAccessEventScanBig = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN, 'refused' => 0, 'notifiedAt' => NULL]);
		$eAccessEventScanRefused = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN * 10, 'refused' => AccessEventLib::NOTIFY_SCAN * 10, 'notifiedAt' => NULL]);
		$eAccessEventScanMixedBelow = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN, 'refused' => 1, 'notifiedAt' => NULL]);
		$eAccessEventScanMixed = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN + 300, 'refused' => 300, 'notifiedAt' => NULL]);
		$eAccessEventScanDone = new AccessEvent(['type' => AccessEvent::SCAN, 'count' => AccessEventLib::NOTIFY_SCAN, 'refused' => 0, 'notifiedAt' => '2026-09-23 05:10:00']);
		$eAccessEventError = new AccessEvent(['type' => AccessEvent::ERROR, 'count' => 1000, 'refused' => 0, 'notifiedAt' => NULL]);

		$this->assertTrue(AccessEventLib::isNotifiable($eAccessEventFlood), 'une rafale se signale toujours');
		$this->assertFalse(AccessEventLib::isNotifiable($eAccessEventScan), 'un balayage sous NOTIFY_SCAN ne se signale pas');
		$this->assertTrue(AccessEventLib::isNotifiable($eAccessEventScanBig));
		$this->assertFalse(AccessEventLib::isNotifiable($eAccessEventScanRefused), 'un balayage que Nginx a entièrement refusé ne se signale pas, quelle que soit sa taille');
		$this->assertFalse(AccessEventLib::isNotifiable($eAccessEventScanMixedBelow), 'le seuil ne compte que les réponses qui ont atteint PHP');
		$this->assertTrue(AccessEventLib::isNotifiable($eAccessEventScanMixed));
		$this->assertFalse(AccessEventLib::isNotifiable($eAccessEventScanDone), 'une seule fois');
		$this->assertFalse(AccessEventLib::isNotifiable($eAccessEventError), 'une route en erreur ne se signale jamais');

	}

	public function testGetMessage(): void {

		$eAccessEventFlood = new AccessEvent(['id' => 41, 'type' => AccessEvent::FLOOD, 'ip' => self::FLOODER, 'host' => 'www.ouvretaferme.org', 'path' => '/ferme/6436/vente', 'hour' => '2026-09-23 04:00:00', 'count' => 201, 'hosts' => NULL, 'paths' => NULL]);
		$eAccessEventScan = new AccessEvent(['id' => 42, 'type' => AccessEvent::SCAN, 'ip' => self::SCANNER, 'host' => 'www.lesjardins.exemple', 'path' => '/wp-login.php', 'hour' => '2026-09-23 03:00:00', 'count' => 825, 'hosts' => 3, 'paths' => 825]);
		$eAccessEventScanOne = new AccessEvent(['id' => 43, 'type' => AccessEvent::SCAN, 'ip' => self::SCANNER, 'host' => 'www.lesjardins.exemple', 'path' => '/.env', 'hour' => '2026-09-23 03:00:00', 'count' => 512, 'hosts' => 1, 'paths' => 112]);

		$this->assertEquals('🚦 Rafale de 201 refus de la limitation Nginx sur www.ouvretaferme.org le 23/09 entre 04:00 et 05:00, dernier chemin /ferme/6436/vente. '.\Lime::getUrl().'/security/admin/access?event=41', AccessEventLib::getMessage($eAccessEventFlood));
		$this->assertEquals('🔍 Balayage de 825 réponses 4xx le 23/09 entre 03:00 et 04:00, sur www.lesjardins.exemple et 2 autres hôtes, 825 chemins distincts, dernier /wp-login.php. '.\Lime::getUrl().'/security/admin/access?event=42', AccessEventLib::getMessage($eAccessEventScan));
		$this->assertEquals('🔍 Balayage de 512 réponses 4xx le 23/09 entre 03:00 et 04:00, sur www.lesjardins.exemple, 112 chemins distincts, dernier /.env. '.\Lime::getUrl().'/security/admin/access?event=43', AccessEventLib::getMessage($eAccessEventScanOne));

		// Discord n'est déclaré destinataire dans aucun des deux documents que l'article 13 du RGPD
		// oblige à tenir, et son salon ne purge rien : l'adresse ne traverse ni le texte ni le lien.
		foreach([$eAccessEventFlood, $eAccessEventScan, $eAccessEventScanOne] as $eAccessEvent) {
			$this->assertFalse(str_contains(AccessEventLib::getMessage($eAccessEvent), $eAccessEvent['ip']), 'événement '.$eAccessEvent['id'].' : l\'adresse ne part pas dans Discord');
		}

	}

	public function testGetIpById(): void {

		$lines = '';

		for($i = 0; $i < AccessEventLib::FLOOD_THRESHOLD; $i++) {
			$lines .= self::line(self::FLOODER, '23/Sep/2026:04:00:'.sprintf('%02d', $i % 60), 'www.ouvretaferme.org', 'GET /ferme/6436/vente HTTP/2.0', 429);
		}

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$eAccessEvent = $this->getOne();

		$this->assertEquals(self::FLOODER, AccessEventLib::getIpById($eAccessEvent['id']), 'le lien de l\'alerte retrouve l\'adresse derrière l\'authentification');
		$this->assertEquals(NULL, AccessEventLib::getIpById(NULL), 'un écran ouvert sans identifiant ne filtre rien');
		$this->assertEquals(NULL, AccessEventLib::getIpById($eAccessEvent['id'] + 1000), 'un événement purgé non plus');

	}

	public function testNotifyWithoutWebhookLeavesNotifiedAtEmpty(): void {

		$lines = '';

		for($i = 0; $i < AccessEventLib::FLOOD_THRESHOLD; $i++) {
			$lines .= self::line(self::FLOODER, '23/Sep/2026:04:00:'.sprintf('%02d', $i % 60), 'www.ouvretaferme.org', 'GET /ferme/6436/vente HTTP/2.0', 429);
		}

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$this->assertEquals(NULL, $this->getOne()['notifiedAt'], 'send() rend FALSE sans webhook : la date reste vide pour que le passage suivant réessaie');

	}

	public function testGetAll(): void {

		$lines = self::scan(AccessEventLib::SCAN_THRESHOLD, '04');

		for($i = 0; $i < AccessEventLib::FLOOD_THRESHOLD; $i++) {
			$lines .= self::line(self::FLOODER, '23/Sep/2026:05:00:'.sprintf('%02d', $i % 60), 'www.ouvretaferme.org', 'GET /ferme/6436/vente HTTP/2.0', 429);
		}

		for($i = 0; $i < AccessEventLib::ERROR_THRESHOLD; $i++) {
			$lines .= self::line(self::VISITOR, '23/Sep/2026:09:0'.($i % 10).':00', 'demo.ouvretaferme.org', 'POST /ferme/6436/caisse HTTP/2.0', 502);
		}

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search([]), 0);
		$this->assertEquals(3, $nAccessEvent);
		$this->assertEquals([AccessEvent::ERROR, AccessEvent::FLOOD, AccessEvent::SCAN], array_values($cAccessEvent->getColumn('type')), 'du plus récent au plus ancien');

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['type' => AccessEvent::FLOOD]), 0);
		$this->assertEquals(1, $nAccessEvent);
		$this->assertEquals(self::FLOODER, $cAccessEvent->first()['ip']);

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['ip' => self::SCANNER]), 0);
		$this->assertEquals(1, $nAccessEvent);
		$this->assertEquals(AccessEvent::SCAN, $cAccessEvent->first()['type']);

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['host' => 'demo']), 0);
		$this->assertEquals(1, $nAccessEvent);
		$this->assertEquals(AccessEvent::ERROR, $cAccessEvent->first()['type']);

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['from' => '2026-09-24']), 0);
		$this->assertEquals(0, $nAccessEvent);

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['from' => '2026-09-23', 'to' => '2026-09-23']), 0);
		$this->assertEquals(3, $nAccessEvent, 'la borne « au » inclut la journée entière');

		[$cAccessEvent, $nAccessEvent] = AccessEventLib::getAll(self::search(['to' => '2026-09-22']), 0);
		$this->assertEquals(0, $nAccessEvent);

	}

	public function testClean(): void {

		$old = date('d/M/Y', strtotime('-'.(AccessEventLib::KEEP_DAYS + 1).' days'));
		$recent = date('d/M/Y', strtotime('-1 day'));

		$lines = '';

		for($i = 0; $i < AccessEventLib::SCAN_THRESHOLD; $i++) {
			$lines .= self::line(self::SCANNER, $old.':04:00:00', 'www.ouvretaferme.org', 'GET /old-'.$i.' HTTP/1.1', 404);
		}

		for($i = 0; $i < AccessEventLib::SCAN_THRESHOLD; $i++) {
			$lines .= self::line(self::SCANNER, $recent.':04:00:00', 'www.ouvretaferme.org', 'GET /recent-'.$i.' HTTP/1.1', 404);
		}

		file_put_contents($this->log, $lines);
		AccessEventLib::collect();

		$this->assertEquals(2, $this->count());

		AccessEventLib::clean();

		$this->assertEquals(1, $this->count(), 'l\'événement de plus de KEEP_DAYS jours est purgé');

	}

	private static function search(array $values): \Search {

		return new \Search($values + ['type' => NULL, 'ip' => NULL, 'host' => NULL, 'from' => NULL, 'to' => NULL]);

	}

	/**
	 * La table est vide hors transaction : en dev, rien ne la remplit (`collect()` rend NULL sans
	 * répertoire configuré), et le test rend ses lignes
	 */
	private function count(): int {
		return AccessEvent::model()->count();
	}

	private function getOne(): AccessEvent {
		return AccessEvent::model()->select(AccessEvent::getSelection())->sort(['id' => SORT_DESC])->get();
	}

	/**
	 * Une ligne du journal au format de la production
	 */
	private static function line(string $ip, string $time, string $host, string $request, int $status): string {
		return $ip.' - - ['.$time.' +0200] "'.$host.'" "'.$request.'" '.$status.' 146 "Mozilla/5.0" 0.001'."\n";
	}

	/**
	 * Un balayage : `$n` réponses 404 de la même adresse sur l'heure, un chemin distinct par ligne,
	 * les hôtes alternant entre deux structures
	 */
	private static function scan(int $n, string $hour, int $offset = 0, string $ip = self::SCANNER): string {

		$lines = '';

		for($i = 0; $i < $n; $i++) {
			$host = ($i % 2 === 0) ? 'www.lesjardins.exemple' : 'www.ouvretaferme.org';
			$lines .= self::line($ip, '23/Sep/2026:'.$hour.':'.sprintf('%02d', intdiv($i, 60) % 60).':'.sprintf('%02d', $i % 60), $host, 'GET /probe-'.($offset + $i).' HTTP/1.1', 404);
		}

		return $lines;

	}

}
?>
