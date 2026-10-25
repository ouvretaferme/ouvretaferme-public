<?php
namespace aid;

/**
 * La veille de nuit des sources (aid\WatchLib) sans réseau : chaque test passe à WatchLib::watch() la
 * lecture d'une adresse qu'il simule, et CurlLib reste fermé, en filet, pour qu'une lecture oubliée
 * échoue au lieu de joindre un site. Les sources du décor sont sur des IP publiques écrites en clair,
 * que resolve() contrôle sans interroger le DNS.
 *
 * Tout s'écrit dans la transaction de chaque test et s'annule à la fin.
 *
 * Lancement : dev/test package=aid file=Watch
 */
class WatchTest extends \dev\TransactionTest {

	const NOW = '2026-10-23 01:30:00';
	const MARK = 'zqx-watch';
	const HOST = 'https://93.184.215.14';

	const PAGE = "<html>\n<body>\n<header>Menu</header>\n<main>\n<h1>Aide</h1>\n<p>Taux de 40 %</p>\n</main>\n<script>var t = 1;</script>\n</body>\n</html>";

	// Le corps relevé le 23/10/2026 sur auvergnerhonealpes.fr/shield, réduit à ce qui le fait reconnaître.
	const CHALLENGE = '<!DOCTYPE html><html lang="fr"><body><script>const challenge = Date.now(); const solution = (challenge * 1337) % 1000000; setCookie("shield", challenge + ":" + solution, 1); setTimeout(() => { window.location.assign(u); }, 300);</script></body></html>';

	protected function getTransactionModel(): \ModuleModel {
		return Aid::model();
	}

	public function init(): void {
		\util\CurlLib::setLocked(TRUE);
	}

	public function tearDown(): void {

		parent::tearDown();

		\util\CurlLib::setLocked(FALSE);

	}

	/**
	 * Trois nuits de 404 passent la source DEAD et cachent l'aide, une lecture la rend ; trois nuits de
	 * 403 ne cachent rien (cadrage de L5, test 3, R10, R11, R13, CL-06, CL-26).
	 */
	public function testDeadAndBack(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['dead', 'forbidden', 'a', 'b', 'c']);
		$eAidDead = $this->getSource($cSource, 'dead')['aid'];
		$eAidForbidden = $this->getSource($cSource, 'forbidden')['aid'];

		$codes = ['dead' => 404, 'forbidden' => 403];
		$request = function(string $url, string $ip) use(&$codes): array {
			return $this->response($codes[basename($url)] ?? 200);
		};

		foreach(['2026-10-23', '2026-10-24'] as $night) {
			$this->setClock($night.' 01:30:00');
			WatchLib::watch(WatchLib::getSources()->find(fn(Source $eSource) => $cSource->offsetExists($eSource['id'])), $request);
		}

		$this->assertEquals(Source::ALIVE, $this->getSource($cSource, 'dead')['status'], 'deux nuits ne suffisent pas');
		$this->assertEquals(2, $this->getSource($cSource, 'dead')['failures']);
		$this->assertTrue($this->isDisplayed($eAidDead));

		$this->setClock('2026-10-25 01:30:00');
		$report = $this->watch($cSource, $request);

		$eSource = $this->getSource($cSource, 'dead');
		$this->assertEquals(Source::DEAD, $eSource['status'], 'la troisième nuit');
		$this->assertEquals('2026-10-25 01:30:00', $eSource['statusAt']);
		$this->assertEquals(1, $report['dead']);
		$this->assertFalse($this->isDisplayed($eAidDead), 'sa seule source officielle est morte');

		$this->setClock('2026-10-26 01:30:00');
		$this->watch($cSource, $request);
		$this->assertEquals(4, $this->getSource($cSource, 'dead')['failures'], 'le compteur continue sans effet');

		$eSource = $this->getSource($cSource, 'forbidden');
		$this->assertEquals(Source::ALIVE, $eSource['status'], 'un refus ne dit rien de la page');
		$this->assertEquals(0, $eSource['failures']);
		$this->assertEquals('2026-10-23', $eSource['unreadSince']);
		$this->assertEquals(Source::FORBIDDEN, $eSource['unreadReason']);
		$this->assertTrue($this->isDisplayed($eAidForbidden));

		$codes = [];

		$this->setClock('2026-10-27 01:30:00');
		$report = $this->watch($cSource, $request);

		$eSource = $this->getSource($cSource, 'dead');
		$this->assertEquals(Source::ALIVE, $eSource['status'], 'elle répond de nouveau');
		$this->assertEquals(0, $eSource['failures']);
		$this->assertEquals('2026-10-27 01:30:00', $eSource['checkedAt']);
		$this->assertEquals(1, $report['alive']);
		$this->assertTrue($this->isDisplayed($eAidDead), 'l\'aide revient sans geste');

		$this->assertEquals(NULL, $this->getSource($cSource, 'forbidden')['unreadSince'], 'lue, elle n\'est plus illisible');

	}

	/**
	 * Une nuit où plus de la moitié des adresses ne se lit pas ne fait progresser aucune source, et deux
	 * passages la même nuit ne comptent qu'un échec (cadrage de L5, test 4, CL-10, CL-14).
	 */
	public function testBrokenNightAndTwoPasses(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['dead', 'a', 'b']);

		$report = $this->watch($cSource, fn(string $url, string $ip) => $this->response(404));

		$this->assertTrue($report['broken']);
		$this->assertEquals(0, $this->getSource($cSource, 'dead')['failures'], 'la veille est en panne, pas la page');

		$report = $this->watch($cSource, fn(string $url, string $ip) => ['error' => CURLE_OPERATION_TIMEDOUT] + $this->response(0));

		$this->assertTrue($report['broken']);
		$this->assertEquals(NULL, $this->getSource($cSource, 'a')['unreadSince'], 'aucune suite de non-lecture ne commence');

		$request = fn(string $url, string $ip) => $this->response(basename($url) === 'dead' ? 404 : 200);

		$this->watch($cSource, $request);
		$this->watch($cSource, $request);

		$eSource = $this->getSource($cSource, 'dead');
		$this->assertEquals(1, $eSource['failures'], 'deux passages la même nuit');
		$this->assertEquals('2026-10-23', $eSource['failedAt']);

	}

	/**
	 * Une source dont l'équipe change l'adresse pendant la lecture ne reçoit pas le résultat, et repart
	 * de zéro ; une source supprimée pendant la lecture ne lève rien (cadrage de L5, test 5, R16, CL-11, CL-12).
	 */
	public function testChangedDuringRead(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['moving', 'a', 'b', 'c']);

		// Un relais : la dernière source officielle d'une aide publiée ne se supprime pas.
		$eSourceGone = $this->createSource($this->getSource($cSource, 'a')['aid'], 'gone', Source::RELAY);
		$cSource[$eSourceGone['id']] = $eSourceGone;

		$eSourceMoving = $this->getSource($cSource, 'moving');
		$this->setWatch($eSourceMoving, ['status' => Source::DEAD, 'failures' => 3, 'fingerprint' => str_repeat('a', 64)]);

		$request = function(string $url, string $ip) use($cSource): array {

			if(basename($url) === 'moving') {

				$eSource = SourceLib::getById($this->getSource($cSource, 'moving')['id']);
				$eSource['url'] = self::HOST.'/'.self::MARK.'/moving-elsewhere';
				SourceLib::update($eSource, ['url']);

			}

			if(basename($url) === 'gone') {

				$eSource = SourceLib::getById($this->getSource($cSource, 'gone')['id']);
				SourceLib::delete($eSource);

			}

			return $this->response(200, self::PAGE);

		};

		$this->watch($cSource, $request);

		$eSource = SourceLib::getById($eSourceMoving['id']);
		$this->assertEquals(self::HOST.'/'.self::MARK.'/moving-elsewhere', $eSource['url']);
		$this->assertEquals(Source::ALIVE, $eSource['status'], 'l\'adresse changée repart de zéro');
		$this->assertEquals(0, $eSource['failures']);
		$this->assertEquals(NULL, $eSource['fingerprint']);
		$this->assertEquals(NULL, $eSource['checkedAt'], 'la lecture de l\'ancienne adresse est jetée');

		$this->assertFalse(Source::model()->whereId($eSourceGone)->exists(), 'supprimée pendant la lecture, elle ne reçoit rien et rien ne lève');

	}

	/**
	 * Une redirection de forme ne passe pas MOVED, une page de défi est illisible, une autre page passe
	 * MOVED avec son adresse ; une redirection vers une adresse privée ou hors du web n'est jamais suivie
	 * (cadrage de L5, test 6, R12, CL-16 ; securite.md de L5).
	 */
	public function testRedirections(): void {

		$this->setClock(self::NOW);

		$cases = [
			'https' => 'https://93.184.215.14/'.self::MARK.'/page',
			'slash' => self::HOST.'/'.self::MARK.'/slash/',
			'shield' => self::HOST.'/shield?u=%2F'.self::MARK,
			'moved' => self::HOST.'/autre-page',
			'home' => self::HOST.'/',
		];

		foreach(['127.0.0.1', '169.254.169.254', '[::1]', '0', '2130706433', '100.64.0.1', '10.0.0.1'] as $host) {
			$cases['refused '.$host] = 'http://'.$host.'/latest/meta-data/';
		}

		$cases['javascript'] = 'javascript:alert(1)';
		$cases['port'] = self::HOST.':8080/'.self::MARK;

		$hosts = [];
		$robots = [];

		foreach($cases as $case => $location) {

			$url = ($case === 'https' ? 'http://93.184.215.14/' : self::HOST.'/').self::MARK.'/'.($case === 'slash' ? 'slash' : 'page');

			$request = function(string $requested, string $ip) use($url, $location, &$hosts): array {

				$hosts[] = $ip;

				if(str_ends_with($requested, '/robots.txt')) {
					return $this->response(404);
				}

				if($requested === $url) {
					return $this->response(301, '', $location);
				}

				return $this->response(200, self::PAGE);

			};

			$result = WatchLib::fetch($url, $request, $robots);

			match($case) {
				'https', 'slash' => $this->assertEquals(WatchLib::READ, $result['result'], $case),
				'shield' => $this->assertEquals(['result' => WatchLib::UNREAD, 'reason' => Source::CHALLENGE], $result, $case),
				'moved', 'home' => $this->assertEquals([WatchLib::MOVED, $location], [$result['result'], $result['movedUrl']], $case),
				default => $this->assertEquals(['result' => WatchLib::UNREAD, 'reason' => Source::SERVER], $result, $case),
			};

		}

		$this->assertEquals(['93.184.215.14'], array_values(array_unique($hosts)), 'aucune adresse refusée n\'est jointe');

		// `www.` se lit sur un nom, que resolve() demanderait au DNS : la comparaison s'éprouve seule.
		$this->assertEquals(WatchLib::getComparableUrl('http://www.Example.org/aide/'), WatchLib::getComparableUrl('https://example.org/aide'));
		$this->assertDiffer(WatchLib::getComparableUrl('https://example.org/aide?id=1'), WatchLib::getComparableUrl('https://example.org/aide?id=2'));

		foreach(['127.0.0.1', '169.254.169.254', '0.0.0.0', '100.64.0.1', '100.127.255.255', '10.1.2.3', '192.168.0.1', '172.16.0.1', '224.0.0.1', '198.18.0.1'] as $ip) {
			$this->assertFalse(WatchLib::isPublic($ip), $ip);
		}

		$this->assertTrue(WatchLib::isPublic('93.184.215.14'));
		$this->assertTrue(WatchLib::isPublic('100.128.0.1'), 'hors du partage d\'adresses des opérateurs');

		$this->assertEquals(NULL, WatchLib::resolve('http://[::1]/')['ip']);
		$this->assertEquals(NULL, WatchLib::resolve('http://user:pass@93.184.215.14/')['ip']);
		$this->assertEquals(NULL, WatchLib::resolve('ftp://93.184.215.14/')['ip']);

		$this->assertEquals('https://93.184.215.14/a/c', WatchLib::getAbsoluteUrl('c', 'https://93.184.215.14/a/b'));
		$this->assertEquals('https://93.184.215.14/c', WatchLib::getAbsoluteUrl('/c', 'https://93.184.215.14/a/b'));
		$this->assertEquals('https://93.184.215.15/c', WatchLib::getAbsoluteUrl('//93.184.215.15/c', 'https://93.184.215.14/a/b'));
		$this->assertEquals(NULL, WatchLib::getAbsoluteUrl('data:text/html,x', 'https://93.184.215.14/a/b'));

	}

	/**
	 * La première lecture pose l'empreinte sans rien signaler, un changement du texte de `<main>` pose
	 * `changedAt`, un script qui change n'en pose pas ; une page de défi en 200 est illisible (cadrage de
	 * L5, R14, CL-01, CL-21, CL-22).
	 */
	public function testFingerprint(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['page', 'pdf', 'challenge', 'a', 'b']);

		$bodies = ['page' => self::PAGE, 'pdf' => '%PDF-1.7 octets', 'challenge' => self::CHALLENGE];
		$request = function(string $url, string $ip) use(&$bodies): array {
			return $this->response(200, $bodies[basename($url)] ?? self::PAGE);
		};

		$report = $this->watch($cSource, $request);

		$eSource = $this->getSource($cSource, 'page');
		$this->assertEquals(hash('sha256', 'Aide Taux de 40 %'), $eSource['fingerprint'], 'le texte de <main>');
		$this->assertEquals(NULL, $eSource['changedAt'], 'la première lecture ne signale rien');
		$this->assertEquals(0, $report['changed']);
		$this->assertEquals(hash('sha256', '%PDF-1.7 octets'), $this->getSource($cSource, 'pdf')['fingerprint'], 'un PDF par ses octets');

		$eSource = $this->getSource($cSource, 'challenge');
		$this->assertEquals(Source::CHALLENGE, $eSource['unreadReason']);
		$this->assertEquals(NULL, $eSource['checkedAt'], 'une page de défi n\'est pas la page');

		$bodies['page'] = str_replace('var t = 1', 'var t = 2', self::PAGE);

		$this->setClock('2026-10-24 01:30:00');
		$this->watch($cSource, $request);
		$this->assertEquals(NULL, $this->getSource($cSource, 'page')['changedAt'], 'un script qui change ne change pas la page');

		$bodies['page'] = str_replace('40 %', '50 %', self::PAGE);

		$this->setClock('2026-10-25 01:30:00');
		$report = $this->watch($cSource, $request);
		$this->assertEquals('2026-10-25 01:30:00', $this->getSource($cSource, 'page')['changedAt']);
		$this->assertEquals(1, $report['changed']);

	}

	/**
	 * Le `robots.txt` se lit une fois par hôte et par nuit, et une adresse qu'il interdit est illisible,
	 * jamais en échec ; une adresse saisie avec un accent part telle quelle (cadrage de L5, R15, CL-18, CL-20).
	 */
	public function testRobots(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['private', 'a', 'b']);

		$robotsRead = 0;

		$request = function(string $url, string $ip) use(&$robotsRead): array {

			if(str_ends_with($url, '/robots.txt')) {
				$robotsRead++;
				return $this->response(200, "User-agent: *\nDisallow: /".self::MARK."/private\nAllow: /".self::MARK."/privatement$\n\nUser-agent: Googlebot\nDisallow: /");
			}

			return $this->response(200, self::PAGE);

		};

		$this->watch($cSource, $request);

		$eSource = $this->getSource($cSource, 'private');
		$this->assertEquals(Source::ROBOTS, $eSource['unreadReason']);
		$this->assertEquals(0, $eSource['failures']);
		$this->assertEquals(1, $robotsRead, 'une fois pour l\'hôte');

		$this->assertTrue(WatchLib::acceptPath(WatchLib::parseRobots("User-agent: *\nDisallow: /a\nAllow: /a/b"), '/a/b/c'), 'la règle la plus longue');
		$this->assertFalse(WatchLib::acceptPath(WatchLib::parseRobots("User-agent: ouvretaferme-veille\nDisallow: /\n\nUser-agent: *\nDisallow:"), '/x'), 'le groupe qui nomme la veille');
		$this->assertTrue(WatchLib::acceptPath(WatchLib::parseRobots("User-agent: *\nDisallow: /*.pdf$"), '/a.pdf?x=1'));
		$this->assertFalse(WatchLib::acceptPath(WatchLib::parseRobots("User-agent: *\nDisallow: /*.pdf$"), '/a.pdf'));

		$urls = [];
		$robots = [];

		WatchLib::fetch(self::HOST.'/aide-é', function(string $url, string $ip) use(&$urls): array {
			$urls[] = $url;
			return $this->response(404);
		}, $robots);

		$this->assertEquals(self::HOST.'/aide-é', end($urls), 'telle que l\'équipe l\'a saisie');

	}

	// Les agrégateurs et les aides archivées ne se lisent pas (cadrage de L5, R8, R17, CL-15).
	public function testSources(): void {

		$this->setClock(self::NOW);

		$cSource = $this->createDecor(['primary', 'archived']);
		$eSourceAggregator = $this->createSource($this->getSource($cSource, 'primary')['aid'], 'aggregator', Source::AGGREGATOR);

		AidLib::archive($this->getAid($this->getSource($cSource, 'archived')['aid']));

		$ids = WatchLib::getSources()->getIds();

		$this->assertTrue(in_array($this->getSource($cSource, 'primary')['id'], $ids, TRUE));
		$this->assertFalse(in_array($this->getSource($cSource, 'archived')['id'], $ids, TRUE));
		$this->assertFalse(in_array($eSourceAggregator['id'], $ids, TRUE));

	}

	private function watch(\Collection $cSource, \Closure $request): array {
		return WatchLib::watch(WatchLib::getSources()->find(fn(Source $eSource) => $cSource->offsetExists($eSource['id'])), $request);
	}

	private function response(int $code, string $body = '', ?string $location = NULL): array {
		return ['error' => NULL, 'code' => $code, 'body' => $body, 'contentType' => 'text/html', 'location' => $location];
	}

	/**
	 * Une aide publiée par nom de page, chacune avec sa source officielle `<HOST>/<MARK>/<nom>`,
	 * construite comme le panneau de l'équipe : build(), puis le Lib.
	 */
	private function createDecor(array $names): \Collection {

		$cSource = new \Collection();

		foreach($names as $name) {

			$eAid = new Aid();

			$fw = new \FailWatch();

			$eAid->build(AidLib::getPropertiesCreate('admin'), [
				'name' => self::MARK.' '.$name,
				'organization' => $this->getOrganization()['id'],
				'level' => Aid::REGION,
				'type' => Aid::GRANT,
				'calendar' => Aid::PERMANENT,
				'rate' => '40 %',
				'productions' => [],
				'direct' => '1',
			], new \PropertyList('create'));

			$fw->validate();

			// La vérification ne se saisit pas au panneau ; une source officielle la porte.
			$eAid['verification'] = Aid::PRIMARY;

			AidLib::create($eAid);

			$eSource = $this->createSource($eAid, $name);

			AidLib::publish($this->getAid($eAid));

			$cSource[$eSource['id']] = $eSource;

		}

		return $cSource;

	}

	private function getOrganization(): Organization {

		$eOrganization = OrganizationLib::getByName(self::MARK);

		if($eOrganization->empty()) {

			$eOrganization->build(OrganizationLib::getPropertiesCreate(), [
				'name' => self::MARK,
				'type' => Organization::REGION,
				'level' => Organization::REGION,
				'grants' => '1',
			]);

			OrganizationLib::create($eOrganization);

		}

		return $eOrganization;

	}

	private function getAid(Aid $eAid): Aid {

		return Aid::model()
			->select(Aid::getSelection() + [
				'cSource' => new SourceModel()->select(Source::getSelection())->delegateCollection('aid'),
			])
			->whereId($eAid['id'])
			->get();

	}

	private function createSource(Aid $eAid, string $name, string $kind = Source::PRIMARY): Source {

		$eSource = new Source(['aid' => $eAid]);

		$fw = new \FailWatch();

		$eSource->build(SourceLib::getPropertiesCreate(), [
			'url' => self::HOST.'/'.self::MARK.'/'.$name,
			'kind' => $kind,
		]);

		$fw->validate();

		SourceLib::create($eSource);

		return $eSource;

	}

	private function getSource(\Collection $cSource, string $name): Source {

		$eSource = $cSource->find(fn(Source $eSource) => str_ends_with($eSource['url'], '/'.$name))->first();

		return Source::model()
			->select(Source::getSelection())
			->whereId($eSource)
			->get();

	}

	// Le décor pose ce que la veille écrit, qu'aucun formulaire ne saisit, par le chemin de la veille.
	private function setWatch(Source $eSource, array $values): void {

		$eSource = SourceLib::getById($eSource['id']);
		$eSource->merge($values);

		SourceLib::updateWatch($eSource, $eSource['url'], array_keys($values));

	}

	private function isDisplayed(Aid $eAid): bool {
		return AidLib::applyDisplay(new AidModel())->whereId($eAid)->exists();
	}

}
?>
