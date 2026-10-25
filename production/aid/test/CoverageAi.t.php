<?php
namespace aid;

/**
 * La couverture du catalogue (lot L1-couverture) : l'état d'une ligne attendue lu sur son aide ou sur
 * son organisme, le décompte par région et par famille, les régions les moins couvertes, l'ordre du
 * détail, les règles de `Expected::properties()`, et le nombre d'aides publiées d'une région égal à
 * celui de la liste qu'il ouvre.
 *
 * Le décor est écrit dans la transaction de chaque test et s'annule à la fin. Les lignes attendues du
 * semis sont retirées en tête de transaction : la couverture lit toute la table, et le décompte ne doit
 * voir que les lignes du test.
 *
 * Lancement : dev/test package=aid file=CoverageAi
 */
class CoverageAiTest extends \dev\TransactionTest {

	const NOW = '2026-10-24 12:00:00';

	private Organization $eOrganization;

	protected function getTransactionModel(): \ModuleModel {
		return Aid::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->setClock(self::NOW);

		$cExpected = Expected::model()
			->select('id')
			->getCollection();

		foreach($cExpected as $eExpected) {
			ExpectedLib::delete($eExpected);
		}

		$this->eOrganization = $this->createOrganization('Organisme du test L1 '.uniqid());

	}

	public function testState(): void {

		$eAidShown = $this->createAid('Trouvée', ['01']);
		$eAidClosed = $this->createAid('Close', ['01'], Aid::CALL, editions: [[Edition::CLOSED, '2026-03-31']]);
		$eAidDead = $this->createAid('Source morte', ['01'], sources: [[Source::PRIMARY, Source::DEAD]]);
		$eAidDraft = $this->createAid('Brouillon', ['01'], isPublished: FALSE);

		$eOrganizationEmpty = $this->createOrganization('Organisme sans aide du test L1 '.uniqid());

		$this->createExpected('test-shown', Expected::FEADER, region: '84', aid: $eAidShown);
		$this->createExpected('test-closed', Expected::FEADER, region: '84', aid: $eAidClosed);
		$this->createExpected('test-dead', Expected::FEADER, region: '84', aid: $eAidDead);
		$this->createExpected('test-draft', Expected::FEADER, region: '84', aid: $eAidDraft);
		$this->createExpected('test-justified', Expected::FEADER, region: '84', justification: 'Paie les soldes, sans demande nouvelle.');
		$this->createExpected('test-nothing', Expected::FEADER, region: '84');
		$this->createExpected('test-organization', Expected::DEPARTMENT, department: '01', organization: $this->eOrganization);
		$this->createExpected('test-organization-empty', Expected::DEPARTMENT, department: '03', organization: $eOrganizationEmpty);

		$states = [];

		foreach(ExpectedLib::getByRegion('84') as $cExpected) {
			foreach($cExpected as $eExpected) {
				$states[$this->getCode($eExpected)] = $eExpected->getState();
			}
		}

		ksort($states);

		$this->assertEquals([
			'test-closed' => Expected::JUSTIFIED,
			'test-dead' => Expected::OPEN,
			'test-draft' => Expected::OPEN,
			'test-justified' => Expected::JUSTIFIED,
			'test-nothing' => Expected::OPEN,
			'test-organization' => Expected::FOUND,
			'test-organization-empty' => Expected::OPEN,
			'test-shown' => Expected::FOUND,
		], $states, 'source morte et brouillon restent ouverts, l\'aide close justifie');

		$ccExpected = ExpectedLib::getByRegion('84');

		$eExpectedClosed = $ccExpected->find(fn(Expected $eExpected) => $eExpected['aid']->is($eAidClosed), depth: 2, limit: 1);
		$this->assertEquals($eAidClosed['id'], $eExpectedClosed->getAidClosed()['id'], 'le détail donne l\'aide close');

		$eExpectedOrganization = $ccExpected->find(fn(Expected $eExpected) => $eExpected['organization']->is($this->eOrganization), depth: 2, limit: 1);
		$this->assertEquals([$eAidShown['id']], $eExpectedOrganization->getAidsFound()->getIds(), 'une ligne d\'organisme donne celles de ses aides qui se montrent');

		$eExpectedDraft = $ccExpected->find(fn(Expected $eExpected) => $eExpected['aid']->is($eAidDraft), depth: 2, limit: 1);
		$this->assertEquals($eAidDraft['id'], $eExpectedDraft->getAidDraft()['id'], 'une ligne ouverte signale son aide en brouillon');

		$eExpectedShown = $ccExpected->find(fn(Expected $eExpected) => $eExpected['aid']->is($eAidShown), depth: 2, limit: 1);
		$this->assertTrue($eExpectedShown->getAidDraft()->empty(), 'une aide publiée n\'est pas un brouillon');
		$this->assertTrue($eExpectedOrganization->getAidDraft()->empty(), 'une ligne d\'organisme ne signale aucun brouillon');

	}

	public function testCoverage(): void {

		$eAidShown = $this->createAid('Trouvée', ['01']);

		$this->createExpected('test-84-found', Expected::FEADER, region: '84', aid: $eAidShown);
		$this->createExpected('test-84-open', Expected::FEADER, region: '84');
		$this->createExpected('test-84-department', Expected::DEPARTMENT, department: '01', organization: $this->eOrganization);
		$this->createExpected('test-27-justified', Expected::FEADER, region: '27', justification: 'Aucun appel en cours.');
		$this->createExpected('test-national', Expected::NATIONAL);

		$coverage = ExpectedLib::getCoverage(NULL);

		$this->assertEquals(5, $coverage['total']['expected']);
		$this->assertEquals(2, $coverage['total'][Expected::FOUND]);
		$this->assertEquals(1, $coverage['total'][Expected::JUSTIFIED]);
		$this->assertEquals(3, $coverage['total']['resolved'], 'trouvées et justifiées');
		$this->assertEquals(0.6, $coverage['total']['rate'], 'le national compte dans le taux de tête');
		$this->assertEquals(0.0, $coverage['national']['rate']);
		$this->assertEquals(3, $coverage['regions']['84']['expected'], 'toutes les familles régionales sans famille choisie');
		$this->assertEquals(2 / 3, $coverage['regions']['84']['rate']);
		$this->assertEquals(1.0, $coverage['regions']['27']['rate']);
		$this->assertEquals(NULL, $coverage['regions']['11']['rate'], 'une région sans ligne n\'a pas de taux');
		$this->assertEquals(['84', '27'], array_map(strval(...), $coverage['lowest']), 'seules les régions qui ont des lignes, du plus bas taux au plus haut');
		$this->assertEquals([2 / 3, 1.0], $coverage['scale']);

		$coverage = ExpectedLib::getCoverage(Expected::FEADER);

		$this->assertEquals(5, $coverage['total']['expected'], 'la tête garde tout le catalogue');
		$this->assertEquals(2, $coverage['regions']['84']['expected'], 'la famille choisie seule');
		$this->assertEquals(0.5, $coverage['regions']['84']['rate']);

		$coverage = ExpectedLib::getCoverage(Expected::GAL);

		$this->assertEquals([], $coverage['lowest']);
		$this->assertEquals(NULL, $coverage['scale'], 'une famille sans ligne n\'a pas d\'échelle');
		$this->assertTrue($coverage['isEmpty'], 'une famille sans ligne est vide');

		$families = ExpectedLib::countByFamily(ExpectedLib::getByRegion('84'));

		$this->assertEquals(ExpectedLib::FAMILIES_REGION, array_keys($families), 'chaque famille, même sans ligne');
		$this->assertEquals(0.5, $families[Expected::FEADER]['rate']);
		$this->assertEquals(1.0, $families[Expected::DEPARTMENT]['rate']);
		$this->assertEquals(NULL, $families[Expected::GAL]['rate']);

		$searched = ExpectedLib::getFamiliesSearched();
		sort($searched);

		$this->assertEquals([Expected::DEPARTMENT, Expected::FEADER, Expected::NATIONAL], $searched, 'une famille sans ligne nulle part n\'est pas encore recherchée');

		$coverage = ExpectedLib::getCoverage(Expected::DEPARTMENT);

		$this->assertEquals(array_keys(AidSetting::DEPARTMENTS), array_keys($coverage['departments']), 'chaque département a son décompte');
		$this->assertEquals(1.0, $coverage['departments']['01']['rate'], 'les lignes de département se comptent par département');
		$this->assertEquals(NULL, $coverage['departments']['03']['rate'], 'un département sans ligne n\'a pas de taux');
		$this->assertEquals([1.0, 1.0], $coverage['scale'], 'l\'échelle des départements quand la carte se teint par département');

	}

	public function testLowest(): void {

		// Une région sur trois à 50 %, les autres à 0 % : il en reste plus de huit à 0 % en métropole, l'outre-mer mis à part.
		foreach(array_keys(AidSetting::REGIONS) as $position => $region) {
			$this->createExpected('test-lowest-'.$region, Expected::FEADER, region: (string)$region, justification: ($position % 3 === 0) ? 'Justifiée.' : NULL);
			$this->createExpected('test-lowest-open-'.$region, Expected::FEADER, region: (string)$region);
		}

		$coverage = ExpectedLib::getCoverage(Expected::FEADER);

		$this->assertEquals(ExpectedLib::LOWEST, count($coverage['lowest']), 'huit régions au plus');

		foreach($coverage['lowest'] as $region) {
			$this->assertEquals(0.0, $coverage['regions'][$region]['rate'], 'les moins couvertes d\'abord');
		}

	}

	public function testLowestWithoutOverseas(): void {

		foreach(AidSetting::REGIONS_OVERSEAS as $region) {
			$this->createExpected('test-overseas-feader-'.$region, Expected::FEADER, region: $region);
			$this->createExpected('test-overseas-department-'.$region, Expected::DEPARTMENT, department: '97'.substr($region, 1));
		}

		$this->createExpected('test-overseas-mainland', Expected::FEADER, region: '84', justification: 'Justifiée.');

		foreach([NULL, Expected::FEADER, Expected::DEPARTMENT] as $family) {

			$coverage = ExpectedLib::getCoverage($family);

			foreach(AidSetting::REGIONS_OVERSEAS as $region) {
				$this->assertEquals(0.0, $coverage['regions'][$region]['rate'], 'l\'outre-mer est à 0 % pour '.($family ?? 'toutes'));
			}

			$this->assertEquals([], array_intersect(array_map('strval', $coverage['lowest']), AidSetting::REGIONS_OVERSEAS), 'aucune région d\'outre-mer dans les moins couvertes pour '.($family ?? 'toutes'));

		}

		// Sous « Départements », le décor n'a que des lignes d'outre-mer : rien à classer ni à teindre, mais des lignes à montrer.
		$coverage = ExpectedLib::getCoverage(Expected::DEPARTMENT);

		$this->assertEquals([], $coverage['lowest'], 'aucune région de métropole à classer');
		$this->assertEquals(NULL, $coverage['scale'], 'aucune teinte de métropole');
		$this->assertFalse($coverage['isEmpty'], 'l\'outre-mer garde ses lignes');

	}

	public function testScaleWithoutOverseas(): void {

		foreach(AidSetting::REGIONS_OVERSEAS as $region) {
			$this->createExpected('test-scale-feader-'.$region, Expected::FEADER, region: $region);
			$this->createExpected('test-scale-department-'.$region, Expected::DEPARTMENT, department: '97'.substr($region, 1));
		}

		$this->createExpected('test-scale-84', Expected::FEADER, region: '84', justification: 'Justifiée.');
		$this->createExpected('test-scale-76', Expected::FEADER, region: '76', justification: 'Justifiée.');
		$this->createExpected('test-scale-76-open', Expected::FEADER, region: '76');
		$this->createExpected('test-scale-09', Expected::DEPARTMENT, department: '09', justification: 'Justifiée.');

		$this->assertEquals([0.5, 1.0], ExpectedLib::getCoverage(Expected::FEADER)['scale'], 'l\'outre-mer à 0 % ne tire pas l\'échelle des régions');
		$this->assertEquals([2 / 3, 1.0], ExpectedLib::getCoverage(NULL)['scale'], 'ni sous toutes les familles');
		$this->assertEquals([1.0, 1.0], ExpectedLib::getCoverage(Expected::DEPARTMENT)['scale'], 'ni celle des départements');

	}

	public function testDepartmentsDetailed(): void {

		// Une tranche a détaillé le Calvados : sa ligne department-14 est retirée, ses lignes par objet posées.
		$this->createExpected('department-14-plantation-haies', Expected::DEPARTMENT, department: '14');
		$this->createExpected('department-14-prophylaxie', Expected::DEPARTMENT, department: '14');
		$this->createExpected('department-29', Expected::DEPARTMENT, department: '29');
		$this->createExpected('department-2A', Expected::DEPARTMENT, department: '2A');
		// Une ligne d'une autre famille ne détaille pas son Département.
		$this->createExpected('gal-31-comminges', Expected::GAL, department: '31', name: 'GAL du Comminges');

		// Les clés telles que le semis les écrit : '31' devient un entier.
		$departments = ExpectedLib::getDepartmentsUndetailed([
			'14' => 'Département du Calvados',
			'29' => 'Département du Finistère',
			'2A' => 'Département de la Corse-du-Sud',
			'31' => 'Département de la Haute-Garonne',
		]);

		$this->assertEquals(['29', '2A', '31'], array_map('strval', array_keys($departments)), 'le Calvados détaillé ne retrouve pas sa ligne, les autres gardent la leur');
		$this->assertEquals('Département du Finistère', $departments['29'], 'chaque Département garde son organisme');

	}

	public function testSeed(): void {

		$eOrganizationSeed = $this->createOrganization('Organisme du semis L1 '.uniqid());

		// La tranche a justifié les Hauts-de-Seine et leur a donné son organisme ; le Finistère a dérivé du semis.
		$this->createExpected('department-92', Expected::DEPARTMENT, department: '92', name: 'Hauts-de-Seine, relu', organization: $this->eOrganization, justification: 'Aucune aide agricole du Département.');
		$this->createExpected('department-29', Expected::DEPARTMENT, department: '29', name: 'Nom dérivé', organization: $this->eOrganization);

		$input = fn(string $department, string $name) => [
			'family' => Expected::DEPARTMENT,
			'department' => $department,
			'name' => $name,
			'organization' => $eOrganizationSeed['id'],
		];

		$this->assertEquals(ExpectedLib::SEED_KEPT, ExpectedLib::seed('department-92', $input('92', 'Hauts-de-Seine')));

		$eExpected = ExpectedLib::getByCode('department-92');

		$this->assertEquals('Aucune aide agricole du Département.', $eExpected['justification'], 'la justification de la tranche reste');
		$this->assertEquals($this->eOrganization['id'], $eExpected['organization']['id'], 'son organisme aussi');
		$this->assertEquals('Hauts-de-Seine, relu', $eExpected['name'], 'et son nom');

		$this->assertEquals(ExpectedLib::SEED_UPDATED, ExpectedLib::seed('department-29', $input('29', 'Finistère')));

		$eExpected = ExpectedLib::getByCode('department-29');

		$this->assertEquals('Finistère', $eExpected['name'], 'une ligne non justifiée est remise d\'aplomb');
		$this->assertEquals($eOrganizationSeed['id'], $eExpected['organization']['id']);

		$this->assertEquals(ExpectedLib::SEED_UNCHANGED, ExpectedLib::seed('department-29', $input('29', 'Finistère')), 'un rejeu sans dérive n\'écrit rien');

		$this->assertEquals(ExpectedLib::SEED_CREATED, ExpectedLib::seed('department-31', $input('31', 'Haute-Garonne')));
		$this->assertTrue(ExpectedLib::getByCode('department-31')->notEmpty(), 'une ligne manquante est créée');

	}

	public function testByRegionOrder(): void {

		$this->createExpected('test-order-gal', Expected::GAL, department: '15', name: 'GAL du Cantal');
		$this->createExpected('test-order-department-03', Expected::DEPARTMENT, department: '03');
		$this->createExpected('test-order-department-01', Expected::DEPARTMENT, department: '01');
		$this->createExpected('test-order-feader-3', Expected::FEADER, region: '84', number: '10');
		$this->createExpected('test-order-feader-2', Expected::FEADER, region: '84', number: '2');
		$this->createExpected('test-order-feader-1', Expected::FEADER, region: '84', number: '1');
		$this->createExpected('test-order-other', Expected::FEADER, region: '27', number: '101');

		$codes = [];

		foreach(ExpectedLib::getByRegion('84') as $cExpected) {
			foreach($cExpected as $eExpected) {
				$codes[] = $this->getCode($eExpected);
			}
		}

		$this->assertEquals([Expected::GAL, Expected::FEADER, Expected::DEPARTMENT, Expected::PARK, Expected::EPCI], array_keys(ExpectedLib::getByRegion('84', Expected::GAL)->getArrayCopy()), 'la famille choisie vient en premier');

		$this->assertEquals(['test-order-feader-1', 'test-order-feader-2', 'test-order-feader-3', 'test-order-department-01', 'test-order-department-03', 'test-order-gal'], $codes, 'par famille, puis par département et par numéro dans l\'ordre naturel, la région seule');

	}

	/**
	 * Toute la carte se clique : chaque département mène au détail de sa région, la famille gardée, et
	 * une ligne de département ouverte mène à la liste filtrée sur ce département.
	 */
	public function testLinks(): void {

		foreach([NULL, Expected::DEPARTMENT] as $family) {

			$links = ExpectedUi::getMapLinks($family);

			$this->assertEquals(array_keys(AidSetting::DEPARTMENTS), array_keys($links), 'chaque département porte un lien');

			foreach(AidSetting::DEPARTMENTS as $department => [, $region]) {

				parse_str(parse_url($links[$department], PHP_URL_QUERY), $query);

				$this->assertEquals('/aid/admin/:coverage', parse_url($links[$department], PHP_URL_PATH));
				$this->assertEquals(array_filter(['region' => $region, 'family' => $family]), $query, 'le département '.$department.' mène à sa région');

			}

		}

		$regions = array_unique(array_column(AidSetting::DEPARTMENTS, 1));
		sort($regions);
		$expected = array_map(strval(...), array_keys(AidSetting::REGIONS));
		sort($expected);

		$this->assertEquals($expected, $regions, 'chaque région est atteinte par un de ses départements');

		parse_str(parse_url(ExpectedUi::getDepartmentUrl('09'), PHP_URL_QUERY), $query);

		$this->assertEquals('/aid/admin/', parse_url(ExpectedUi::getDepartmentUrl('09'), PHP_URL_PATH));
		$this->assertEquals(['department' => '09', 'level' => Aid::DEPARTMENT], $query, 'la liste des aides filtrée sur le département, au niveau du Département');

		$eAidDepartment = $this->createAid('Aide test du Département de l\'Ariège', ['09'], level: Aid::DEPARTMENT);
		$eAidRegion = $this->createAid('Aide test de la région sur l\'Ariège', ['09']);

		// Le texte borne la liste aux deux aides du test, que les aides de la base ne repoussent pas hors de la première page.
		[$cAid] = AidLib::getForAdmin(new \Search([
			'department' => $query['department'],
			'level' => $query['level'],
			'text' => 'Aide test d',
		]), 0);

		$this->assertTrue(in_array($eAidDepartment['id'], $cAid->getIds(), TRUE), 'l\'aide du Département est dans la liste');
		$this->assertFalse(in_array($eAidRegion['id'], $cAid->getIds(), TRUE), 'l\'aide régionale qui touche le département n\'y est pas');
		$this->assertEquals([Aid::DEPARTMENT], array_values(array_unique($cAid->getColumn('level'))), 'la liste ne porte que des aides de Département');

	}

	public function testProperties(): void {

		$this->createExpected('test-deduce', Expected::DEPARTMENT, department: '2A');
		$this->assertEquals('94', ExpectedLib::getByCode('test-deduce')['region'], 'la région se déduit du département');

		$this->createExpected('test-contradict', Expected::DEPARTMENT, department: '01', region: '27');
		$this->assertEquals('84', ExpectedLib::getByCode('test-contradict')['region'], 'le département l\'emporte sur une région contraire');

		$eAid = $this->createAid('Exclusive', ['01']);

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-exclusive', Expected::FEADER, region: '84', aid: $eAid, organization: $this->eOrganization));
		$this->assertTrue($fw->has('Expected::organization.exclusive'), 'une aide ou un organisme, pas les deux');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-unknown', Expected::DEPARTMENT, department: '99'));
		$this->assertTrue($fw->has('Expected::department.check'), 'un département hors de la liste officielle');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-unknown-region', Expected::FEADER, region: '99'));
		$this->assertTrue($fw->has('Expected::region.check'), 'une région hors de la liste officielle');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-feader-nowhere', Expected::FEADER));
		$this->assertTrue($fw->has('Expected::region.family'), 'une ligne FEADER a sa région');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-national-region', Expected::NATIONAL, region: '84'));
		$this->assertTrue($fw->has('Expected::region.family'), 'une ligne nationale n\'a pas de région');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-national-department', Expected::NATIONAL, department: '01'));
		$this->assertTrue($fw->has('Expected::department.family'), 'une ligne nationale n\'a pas de département');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-gal-nowhere', Expected::GAL, region: '84'));
		$this->assertTrue($fw->has('Expected::department.family'), 'une ligne d\'organisme a son département');

		$fw = new \FailWatch();
		new Expected()->build(ExpectedLib::getPropertiesCreate(), $this->getInput('test-national', Expected::NATIONAL));
		$this->assertTrue($fw->ok(), 'une ligne nationale sans territoire passe');

	}

	/**
	 * Le nombre d'aides publiées d'une région est celui que rend la liste qu'il ouvre, une aide comptée
	 * une fois quel que soit le nombre de ses départements, et dans chaque région qu'elle touche.
	 */
	public function testPublishedByRegion(): void {

		$this->createAid('Ain et Allier', ['01', '03']);
		$this->createAid('Ain et Côte-d\'Or', ['01', '21']);
		$this->createAid('Close en Côte-d\'Or', ['21'], Aid::CALL, editions: [[Edition::CLOSED, '2026-03-31']]);
		$this->createAid('Brouillon dans l\'Ain', ['01'], isPublished: FALSE);

		$published = AidLib::countPublishedByRegion();

		foreach(['84', '27'] as $region) {

			[, $nAid] = AidLib::getForAdmin(new \Search(['region' => $region, 'status' => Aid::PUBLISHED]), 0);

			$this->assertEquals($nAid, $published[$region], 'la région '.$region.' compte ce que sa liste rend');

		}

	}

	public function testReserve(): void {

		$before = AidLib::countForAdmin(new \Search(['status' => Aid::DRAFT]));

		$this->createAid('Relais', ['01'], isPublished: FALSE, verification: Aid::RELAY);
		$this->createAid('Divergente', ['01'], isPublished: FALSE, verification: Aid::CONFLICT);
		$this->createAid('Relais publiée', ['01'], verification: Aid::RELAY);

		$after = AidLib::countForAdmin(new \Search(['status' => Aid::DRAFT]));

		$this->assertEquals($before['relay'] + 1, $after['relay'], 'un brouillon lu sur un relais, la publiée hors réserve');
		$this->assertEquals($before['conflict'] + 1, $after['conflict']);

	}

	public function testSeedAidByName(): void {

		$eAid84 = $this->createAid('Même nom du test L1', ['01']);
		$eAid27 = $this->createAid('Même nom du test L1', ['21']);

		$this->assertEquals([$eAid84['id']], AidLib::getByNameInRegion('Même nom du test L1', '84')->getIds(), 'le périmètre sépare deux homonymes');
		$this->assertEquals([$eAid27['id']], AidLib::getByNameInRegion('Même nom du test L1', '27')->getIds());
		$this->assertEquals([], AidLib::getByNameInRegion('Même nom du test L1', '11')->getIds());

	}

	/**
	 * La route passe par `validateAdmin()` : un compte du décor y est refusé, puis reçoit l'écran quand
	 * il entre dans la liste des administrateurs, le temps du test. Une famille ou une région hors de
	 * leur liste fermée retombe à NULL.
	 */
	public function testRoute(): void {

		$eUserInitial = \user\ConnectionLib::getOnline();
		$adminInitial = \user\UserSetting::$admin;

		$eUser = \user\TabLib::getByEmails(['decor.f1.admin@example.org'])->first();

		try {

			\user\ConnectionLib::setOnline($eUser);

			$this->assertEquals(\DisabledPage::class, $this->callRoute('aid/admin/index:coverage'), 'hors de l\'équipe, l\'écran est refusé');

			\user\UserSetting::$admin[] = $eUser['id'];

			$data = new \stdClass();
			$this->assertEquals(\ViewAction::class, $this->callRoute('aid/admin/index:coverage', ['family' => Expected::NATIONAL, 'region' => '99'], $data));
			$this->assertEquals(NULL, $data->family, 'le national n\'a pas de carte');
			$this->assertEquals(NULL, $data->region);

			$data = new \stdClass();
			$this->assertEquals(\ViewAction::class, $this->callRoute('aid/admin/index:coverage', ['family' => Expected::FEADER, 'region' => '84'], $data));
			$this->assertEquals(Expected::FEADER, $data->family);
			$this->assertEquals('84', $data->region);

		} finally {
			\user\UserSetting::$admin = $adminInitial;
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function callRoute(string $path, array $input = [], \stdClass $data = new \stdClass()): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$input, [], $input];

		try {
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, $data);
			return 'aucune action';
		} catch(\Action|\DisabledPage $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private function getCode(Expected $eExpected): string {

		return Expected::model()
			->select('code')
			->whereId($eExpected['id'])
			->getValue('code');

	}

	private function getInput(
		string $code,
		string $family,
		?string $region = NULL,
		?string $department = NULL,
		?string $number = NULL,
		?string $name = NULL,
		Aid $aid = new Aid(),
		Organization $organization = new Organization(),
		?string $justification = NULL
	): array {

		return [
			'code' => $code,
			'family' => $family,
			'department' => $department,
			'region' => $region,
			'number' => $number,
			'name' => $name ?? 'Ligne '.$code,
			'aid' => $aid->empty() ? NULL : $aid['id'],
			'organization' => $organization->empty() ? NULL : $organization['id'],
			'justification' => $justification,
			'justificationUrl' => NULL,
			'listUrl' => NULL,
		];

	}

	private function createExpected(
		string $code,
		string $family,
		?string $region = NULL,
		?string $department = NULL,
		?string $number = NULL,
		?string $name = NULL,
		Aid $aid = new Aid(),
		Organization $organization = new Organization(),
		?string $justification = NULL
	): Expected {

		$eExpected = new Expected();

		$fw = new \FailWatch();

		$eExpected->build(ExpectedLib::getPropertiesCreate(), $this->getInput($code, $family, $region, $department, $number, $name, $aid, $organization, $justification));

		$fw->validate();

		ExpectedLib::create($eExpected);

		return $eExpected;

	}

	private function createOrganization(string $name): Organization {

		$eOrganization = new Organization();

		$fw = new \FailWatch();

		$eOrganization->build(OrganizationLib::getPropertiesCreate(), [
			'name' => $name,
			'type' => Organization::DEPARTMENT,
			'level' => Organization::DEPARTMENT,
			'url' => NULL,
			'grants' => TRUE,
		]);

		$fw->validate();

		OrganizationLib::create($eOrganization);

		return $eOrganization;

	}

	/**
	 * Une aide du décor de l'organisme du test, construite par `build()` comme une saisie de l'équipe,
	 * puis publiée. Chaque édition est `[état, clôture]`, chaque source `[nature, état]`.
	 */
	private function createAid(
		string $name,
		array $departments,
		string $calendar = Aid::PERMANENT,
		bool $isPublished = TRUE,
		array $editions = [],
		array $sources = [],
		string $verification = Aid::PRIMARY,
		string $level = Aid::REGION
	): Aid {

		$eAid = new Aid();

		$fw = new \FailWatch();

		$eAid->build(AidLib::getPropertiesCreate(), [
			'name' => $name,
			'organization' => $this->eOrganization,
			'level' => $level,
			'type' => Aid::GRANT,
			'calendar' => $calendar,
			'rate' => '50 %',
			'productions' => [],
			'direct' => TRUE,
			'verification' => $verification,
		]);

		$fw->validate();

		AidLib::create($eAid);

		foreach($departments as $department) {

			$eArea = new Area(['aid' => $eAid]);

			$fw = new \FailWatch();
			$eArea->build(AreaLib::getPropertiesCreate(), ['department' => $department]);
			$fw->validate();

			AreaLib::create($eArea);

		}

		foreach($editions as [$state, $endDate]) {

			$eEdition = new Edition(['aid' => $eAid]);

			$fw = new \FailWatch();
			$eEdition->build(EditionLib::getPropertiesCreate(), ['startDate' => '2026-01-01', 'endDate' => $endDate, 'state' => $state]);
			$fw->validate();

			EditionLib::create($eEdition);

		}

		foreach($sources as [$kind, $status]) {

			$eSource = new Source(['aid' => $eAid]);

			$fw = new \FailWatch();
			$eSource->build(SourceLib::getPropertiesCreate(), [
				'url' => 'https://www.example.org/aide-'.$eAid['id'].'-'.$kind,
				'kind' => $kind,
			]);
			$fw->validate();

			// La veille seule passe une source DEAD (troisième nuit d'échec), aucun formulaire ne la pose.
			$eSource['status'] = $status;

			SourceLib::create($eSource);

		}

		if($isPublished) {
			AidLib::publish($eAid);
		}

		return $eAid;

	}

}
?>
