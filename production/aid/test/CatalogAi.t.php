<?php
namespace aid;

/**
 * Le socle du catalogue des aides (charte, point 8) : la reprise d'un extrait réel de la recherche,
 * le périmètre ramené au département (2A et 2B, 974, un parc naturel sur deux régions), la condition
 * d'affichage et le droit d'écrire.
 *
 * Le décor `fixture/` est un extrait tel quel de la recherche L0 : seize fiches choisies pour leurs
 * cas, les organismes qu'elles citent et la taxonomie entière. Tout s'écrit dans la transaction de
 * chaque test et s'annule à la fin, le catalogue vivant dans la base commune.
 *
 * Lancement : dev/test package=aid file=CatalogAi
 */
class CatalogAiTest extends \dev\TransactionTest {

	const NOW = '2026-10-22 12:00:00';

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Aid::model();
	}

	public function init(): void {
		$this->eUserInitial = \user\ConnectionLib::getOnline();
	}

	public function tearDown(): void {

		parent::tearDown();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testSeed(): void {

		$this->setClock(self::NOW);

		// Le semis de dev a pu poser ces aides : elles partent, dans la transaction du test, pour que la
		// reprise éprouve la création et pas seulement la mise à jour.
		$this->deleteFixtureAids();

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(16, $report['aidsRead']);
		$this->assertEquals(14, $report['aidsMerged'], 'les fiches du parc Normandie-Maine et celles de l\'agence Loire-Bretagne font chacune une seule aide');
		$this->assertEquals([], $report['refused']);
		$this->assertEquals([], $report['waterAgenciesUnknown'], 'chaque agence de l\'eau du décor a son bassin');
		$this->assertEquals(14, $report['created']);

		// Le parc naturel régional Normandie-Maine, saisi par la Normandie et par les Pays de la Loire
		$eAid = $this->getAid('52-pnr-normandie-maine-plantation');

		$this->assertEquals('28-pnr-normandie-maine-plantation-haies', $eAid['aliases'][0], 'la fiche absorbée retrouve l\'aide gardée');
		$this->assertEquals(Aid::INTERCOMMUNAL, $eAid['level']);
		$this->assertEquals(['50', '53', '61', '72'], $this->getDepartments($eAid));

		$this->assertEquals(['2A', '2B'], $this->getDepartments($this->getAid('94-odarc-7309-materiel')));
		$this->assertEquals(['974'], $this->getDepartments($this->getAid('974-departement-feader-75-011-dja')));
		$this->assertEquals([], $this->getDepartments($this->getAid('national-masa-aita-installation')), 'une aide nationale n\'a aucun périmètre');

		// L'agence de l'eau Loire-Bretagne, saisie en national et par les Pays de la Loire : son bassin
		// entier, et rien au-delà
		$eAid = $this->getAid('52-aelb-agr1-accompagnement');

		$this->assertEquals(AidSetting::BASINS['04'][1], $this->getDepartments($eAid), 'le bassin Loire-Bretagne entier, les Pays de la Loire compris');
		$this->assertFalse(in_array('05', $this->getDepartments($eAid), TRUE), 'les Hautes-Alpes relèvent de Rhône-Méditerranée');
		$this->assertFalse(in_array('31', $this->getDepartments($eAid), TRUE), 'la Haute-Garonne relève d\'Adour-Garonne');

		$this->assertEquals(['51'], $this->getDepartments($this->getAid('44-aesn-maec-ciff')), 'une MAEC d\'aire de captage dans la Marne garde la Marne seule');

		// Le label qu'une aide exige et celui qui la bonifie
		$eAid = $this->getAid('53-region-agri-bio');

		$this->assertTrue($this->hasLabel($eAid['labels'], Aid::AB), 'AGRI Bio est réservée au bio');
		$this->assertFalse($this->hasLabel($eAid['labelsBonus'], Aid::AB));
		$this->assertEquals(NULL, $eAid['labelsBonus'], 'aucun label s\'écrit NULL, comme un groupe de cases vide au panneau de l\'équipe');

		$eAid = $this->getAid('24-region-siap-modernisation');

		$this->assertFalse($this->hasLabel($eAid['labels'], Aid::AB), 'la modernisation est ouverte à tous');
		$this->assertTrue($this->hasLabel($eAid['labelsBonus'], Aid::AB), '« +10 points bio »');

		$this->assertEquals(Aid::SOCIAL, $this->getAid('national-msa-exoneration-jeune-agriculteur')['type']);
		$this->assertTrue(in_array('other', $this->getAid('32-region-genetique-cunicole')['productions'], TRUE), 'la cuniculture n\'a pas de production à elle');

		// La confiance : seule une fiche lue sur sa source officielle se publie
		$eAid = $this->getAid('11-region-feader-hydraulique-exploitations-7301');

		$this->assertEquals(Aid::CONFLICT, $eAid['verification']);
		$this->assertEquals(Aid::DRAFT, $eAid['status']);

		$eAid = $this->getAid('24-region-siap-risques-climatiques');

		$this->assertEquals(Aid::RELAY, $eAid['verification']);
		$this->assertEquals(Aid::DRAFT, $eAid['status']);

		$this->assertEquals(Aid::PUBLISHED, $this->getAid('national-masa-aita-installation')['status']);

		// Une reprise rejouée ne crée rien, ni aide ni ligne fille : Edition et Source n'ont pas d'index
		// unique qui refuserait un doublon
		$cAid = AidLib::applyDisplay(new AidModel())->select('id')->getCollection();
		$children = $this->countChildren();
		$published = $this->countFixturePublished();

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(0, $report['created']);
		$this->assertEquals(14 - $published, $report['updated'], 'seuls les brouillons jamais relus se réécrivent');
		$this->assertEquals($published, $report['kept'], 'une aide publiée ne se réécrit pas');
		$this->assertEquals(0, $report['differences'], 'une fiche inchangée ne rapporte aucun écart');
		$this->assertEquals($cAid->getIds(), AidLib::applyDisplay(new AidModel())->select('id')->getCollection()->getIds());
		$this->assertEquals($children, $this->countChildren(), 'les lignes filles sont remplacées, pas ajoutées');

	}

	/**
	 * Une fiche régionale qui ne nomme aucun département prend ceux de sa région, même quand le code de
	 * la région est aussi celui d'un département : 76, l'Occitanie et la Seine-Maritime. La fiche du
	 * décor nomme ses départements, elle est donc vidée dans une copie du décor.
	 */
	public function testRegionWithoutDepartments(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$report = $this->seedCopy('76-region-biogaz', fn(array $aid) => ['departements' => []] + $aid);

		$this->assertEquals([], $report['refused']);
		$this->assertEquals(AidSetting::getDepartmentsByRegion('76'), $this->getDepartments($this->getAid('76-region-biogaz')), 'l\'Occitanie, pas la Seine-Maritime');

	}

	/**
	 * Un département ou un tag répété dans une fiche ne compte qu'une fois, au lieu de faire refuser
	 * l'aide par l'index unique de Area ou d'AidTag.
	 */
	public function testRepeatedValues(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$report = $this->seedCopy('53-region-agri-bio', fn(array $aid) => ['departements' => ['22', '29', '22', '35', '56']] + $aid, ['maintien-bio', 'maintien-bio']);

		$this->assertEquals([], $report['refused']);

		$eAid = $this->getAid('53-region-agri-bio');

		$this->assertEquals(['22', '29', '35', '56'], $this->getDepartments($eAid));
		$this->assertEquals(1, AidTag::model()->whereAid($eAid)->count());

	}

	public function testDepartments(): void {

		$this->assertEquals(['2A', '2B'], AidSetting::getDepartmentsByRegion('94'));
		$this->assertEquals(['974'], AidSetting::getDepartmentsByRegion('04'));
		$this->assertEquals(['01', '03', '07', '15', '26', '38', '42', '43', '63', '69', '73', '74'], AidSetting::getDepartmentsByRegion('84'));

		// Un département partagé entre deux bassins est dans les deux, une seule fois quand une agence
		// couvre les deux
		$this->assertTrue(in_array('48', AidSetting::BASINS['04'][1], TRUE) and in_array('48', AidSetting::BASINS['05'][1], TRUE) and in_array('48', AidSetting::BASINS['06'][1], TRUE), 'la Lozère sur trois bassins');
		$this->assertEquals(1, count(array_keys(AidSetting::getDepartmentsByBasins(['04', '05']), '48')));
		$this->assertEquals(['2A', '2B'], array_values(array_intersect(AidSetting::getDepartmentsByBasins(['06', '12']), ['2A', '2B'])), 'Rhône Méditerranée Corse couvre la Corse');

		// Chaque département appartient à un bassin au moins, et chaque bassin ne cite que des départements connus
		$this->assertEquals([], array_diff(array_map(strval(...), array_keys(AidSetting::DEPARTMENTS)), AidSetting::getDepartmentsByBasins(array_map(strval(...), array_keys(AidSetting::BASINS)))));
		$this->assertEquals([], array_diff(AidSetting::getDepartmentsByBasins(array_map(strval(...), array_keys(AidSetting::BASINS))), array_map(strval(...), array_keys(AidSetting::DEPARTMENTS))));

		// Chaque agence de l'eau de la reprise nomme des bassins connus
		foreach(ImportLib::WATER_AGENCIES as $organization => $basins) {
			$this->assertEquals([], array_diff($basins, array_map(strval(...), array_keys(AidSetting::BASINS))), $organization);
		}

		foreach(['2A' => TRUE, '2B' => TRUE, '972' => TRUE, '974' => TRUE, '976' => TRUE, '20' => FALSE, '977' => FALSE, '1' => FALSE] as $department => $accepted) {

			$fw = new \FailWatch();

			new Area()->build(AreaLib::getPropertiesCreate(), ['department' => $department]);

			$this->assertEquals($accepted, $fw->ok(), 'département '.$department);

		}

	}

	/**
	 * La condition d'affichage, sur une requête (AidLib::applyDisplay()) comme sur une aide chargée
	 * (Aid::acceptDisplay()) : les deux doivent garder exactement les mêmes aides.
	 */
	public function testDisplay(): void {

		$this->setClock(self::NOW);

		$eOrganization = $this->createOrganization();

		$cases = [
			'brouillon' => [Aid::PERMANENT, FALSE, [], [], FALSE],
			'permanente' => [Aid::PERMANENT, TRUE, [], [], TRUE],
			'permanente jusqu\'à la fin de l\'année' => [Aid::PERMANENT, TRUE, [[Edition::OPEN, '2026-12-31']], [], TRUE],
			'permanente, dispositif clos' => [Aid::PERMANENT, TRUE, [[Edition::OPEN, '2026-10-21']], [], FALSE],
			'appel ouvert sans fin' => [Aid::CALL, TRUE, [[Edition::OPEN, NULL]], [], TRUE],
			'appel ouvert jusqu\'à ce jour' => [Aid::CALL, TRUE, [[Edition::OPEN, '2026-10-22']], [], TRUE],
			'appel ouvert, date passée' => [Aid::CALL, TRUE, [[Edition::OPEN, '2026-10-21']], [], FALSE],
			'appel clos' => [Aid::CALL, TRUE, [[Edition::CLOSED, NULL]], [], FALSE],
			'appel épuisé puis rouvert' => [Aid::CALL, TRUE, [[Edition::EXHAUSTED, NULL], [Edition::OPEN, NULL]], [], TRUE],
			'appel sans édition' => [Aid::CALL, TRUE, [], [], FALSE],
			'source principale morte' => [Aid::PERMANENT, TRUE, [], [[Source::PRIMARY, Source::DEAD]], FALSE],
			'relais mort' => [Aid::PERMANENT, TRUE, [], [[Source::RELAY, Source::DEAD]], TRUE],
		];

		$expected = [];

		foreach($cases as $name => [$calendar, $isPublished, $editions, $sources, $isDisplayed]) {

			$eAid = $this->createAid($eOrganization, $name, $calendar, $isPublished, $editions, $sources);

			$expected[$eAid['id']] = $isDisplayed;

		}

		$ids = array_keys($expected);

		$displayedByQuery = AidLib::applyDisplay(new AidModel())
			->select('id')
			->whereId('IN', $ids)
			->getCollection()
			->getIds();

		$cAid = Aid::model()
			->select(Aid::getSelection() + [
				'cEdition' => new EditionModel()->select(Edition::getSelection())->delegateCollection('aid'),
				'cSource' => new SourceModel()->select(Source::getSelection())->delegateCollection('aid'),
			])
			->whereId('IN', $ids)
			->getCollection();

		foreach($cAid as $eAid) {

			$this->assertEquals($expected[$eAid['id']], $eAid->acceptDisplay(), $eAid['name'].', sur l\'aide chargée');
			$this->assertEquals($expected[$eAid['id']], in_array($eAid['id'], $displayedByQuery), $eAid['name'].', sur la requête');

		}

	}

	public function testPublish(): void {

		$this->setClock(self::NOW);

		$eAid = $this->createAid($this->createOrganization(), 'sans taux', Aid::PERMANENT, FALSE, [], [], rate: NULL);

		$eAid['cSource'] = new \Collection([new Source(['kind' => Source::PRIMARY, 'status' => Source::ALIVE])]);

		$this->assertFalse($eAid->acceptPublish(), 'une aide sans taux ne se publie pas');

		$eAid['rate'] = '40 %';

		$this->assertTrue($eAid->acceptPublish());

		$eAid['cSource'] = new \Collection([new Source(['kind' => Source::PRIMARY, 'status' => Source::DEAD]), new Source(['kind' => Source::RELAY, 'status' => Source::ALIVE])]);

		$this->assertFalse($eAid->acceptPublish(), 'une aide dont la source principale est morte ne se publie pas, même avec un relais vivant');

		$eAid['cSource'] = new \Collection([new Source(['kind' => Source::AGGREGATOR, 'status' => Source::ALIVE])]);

		$this->assertFalse($eAid->acceptPublish(), 'une aide sans source principale ne se publie pas');

		$eAid['cSource'] = new \Collection([new Source(['kind' => Source::PRIMARY, 'status' => Source::ALIVE]), new Source(['kind' => Source::PRIMARY, 'status' => Source::DEAD])]);

		$this->assertTrue($eAid->acceptPublish(), 'une source principale qui répond tient l\'aide, l\'autre fût-elle morte (arbitrage Q2 b de L5)');

		$eAid['cSource'] = new \Collection([new Source(['kind' => Source::PRIMARY, 'status' => Source::DEAD]), new Source(['kind' => Source::PRIMARY, 'status' => Source::DEAD])]);

		$this->assertFalse($eAid->acceptPublish(), 'toutes les sources principales mortes refusent, comme à l\'affichage');

	}

	public function testRights(): void {

		$adminIds = \user\UserSetting::$admin;

		if($this->assertDecorPresent($adminIds !== [], 'un administrateur de Ouvretaferme déclaré dans UserSetting::$admin') === FALSE) {
			return;
		}

		$eAid = new Aid();

		\user\ConnectionLib::setOnline(new \user\User());

		$this->assertFalse($eAid->canRead(), 'le catalogue ne se lit qu\'une fois connecté');

		\user\ConnectionLib::setOnline(new \user\User(['id' => max($adminIds) + 1]));

		$this->assertTrue($eAid->canRead(), 'un utilisateur connecté lit le catalogue');
		$this->assertFalse($eAid->canWrite(), 'seule l\'équipe le tient');

		\user\ConnectionLib::setOnline(new \user\User(['id' => $adminIds[0]]));

		$this->assertTrue($eAid->canWrite());

	}

	private function deleteFixtureAids(): void {

		foreach(file(__DIR__.'/fixture/fixture.aides.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

			$eAid = AidLib::getByAlias(json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR)['id']);

			if($eAid->empty()) {
				continue;
			}

			// Une aide publiée garde sa dernière source officielle (Source::acceptDelete()) : le décor la
			// dépublie avant de tout retirer.
			AidLib::unpublish($eAid);

			foreach([Area::model(), Edition::model(), Source::model(), AidOrganization::model(), AidTag::model()] as $mModule) {

				$lib = $mModule->getModule().'Lib';

				foreach($mModule->select(['id', 'aid'])->whereAid($eAid)->getCollection() as $element) {
					$lib::delete($element);
				}

			}

			AidLib::delete($eAid);

		}

	}

	/**
	 * Deux organismes de la recherche que seule la casse distingue sont un seul organisme en base
	 * (Organization.name en @collate(general)) : cités tous deux en cofinanceurs, ils ne donnent qu'un rôle.
	 */
	public function testCofundersSameOrganization(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$report = $this->seedCopy(
			'53-region-agri-bio',
			fn(array $aid) => ['cofinanceurs' => ['Région Normandie', 'région normandie']] + $aid,
			modifyOrganizations: function(array $organizations) {
				$organization = array_find($organizations, fn(array $organization) => $organization['organisme'] === 'Région Normandie');
				$organizations[] = ['organisme' => 'région normandie', 'variantes' => []] + $organization;
				return $organizations;
			}
		);

		$this->assertEquals([], $report['refused']);

		$eAid = $this->getAid('53-region-agri-bio');

		$this->assertEquals(1, AidOrganization::model()->whereAid($eAid)->whereRole(AidOrganization::COFUNDER)->count(), 'un seul cofinanceur pour les deux casses');

	}

	/**
	 * Une aide publiée que sa fiche resemée classe en relais garde sa source officielle et reste publiée :
	 * la reprise ne réécrit pas une aide publiée et ne la dépublie jamais (cadrage de L5, R1 et R3). Le
	 * changement de sources devient son écart.
	 */
	public function testReseedAsRelay(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status']);

		$state = $this->getState($eAid);

		$this->seedCopy($id, fn(array $aid) => ['particularites' => 'Taux lu sur un relais.', 'conditions' => ''] + $aid);

		$eAid = $this->getAid($id);

		$this->assertEquals($state, $this->getState($eAid), 'rien n\'est réécrit, ni la confiance ni les sources');
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'elle reste publiée');
		$this->assertTrue(array_key_exists('Source', $eAid['seedDifferences']['children']), 'le changement de sources est son écart');

	}

	/**
	 * Une reprise sur une aide publiée que personne n'a relue ne change aucune propriété, aucune ligne
	 * fille ni son état, un guichet saisi à la main compris ; une fiche qui change en fait un écart, et
	 * la même fiche rejouée n'écrit plus rien (cadrage de L5, test 1, R2 et R6 ; inventaire de L6).
	 */
	public function testReseedPublished(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status']);
		$this->assertTrue($eAid['checkedBy']->empty(), 'jamais relue, comme les aides publiées après l\'export');

		$eAidOrganization = new AidOrganization(['aid' => $eAid]);

		$fw = new \FailWatch();

		$eAidOrganization->build(AidOrganizationLib::getPropertiesCreate(), [
			'organization' => $this->createOrganization()['id'],
			'role' => AidOrganization::DESK,
		]);

		$fw->validate();

		AidOrganizationLib::create($eAidOrganization);

		$state = $this->getState($eAid);

		ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals($state, $this->getState($eAid), 'rien n\'est réécrit, le guichet saisi à la main compris');
		$this->assertEquals(Aid::PUBLISHED, $this->getAid($id)['status']);

		$renamed = fn(array $aid) => ['nom' => 'AITA renommée'] + $aid;

		$this->seedCopy($id, $renamed);

		$eAid = $this->getAid($id);
		$this->assertEquals($state, $this->getState($eAid), 'l\'écart ne réécrit rien');
		$this->assertEquals('AITA renommée', $eAid['seedDifferences']['properties']['name']);
		$this->assertEquals(Aid::REVIEW_SEED, $this->getReviewReason($eAid));

		$this->setClock('2026-10-23 12:00:00');

		$this->seedCopy($id, $renamed);

		$this->assertEquals($eAid['updatedAt'], $this->getAid($id)['updatedAt'], 'la même fiche rejouée n\'écrit rien');

	}

	/**
	 * Une aide archivée ne reçoit rien d'une reprise, pas même un écart ; un brouillon relu par l'équipe
	 * reçoit un écart et jamais une réécriture (cadrage de L5, R4 ; arbitrage Q4 B).
	 */
	public function testReseedArchivedAndChecked(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';
		$renamed = fn(array $aid) => ['nom' => 'AITA renommée'] + $aid;

		ImportLib::seed(__DIR__.'/fixture');

		AidLib::archive($this->getAid($id));

		$state = $this->getState($this->getAid($id));

		$report = $this->seedCopy($id, $renamed);

		$eAid = $this->getAid($id);
		$this->assertEquals(1, $report['archivedSkipped']);
		$this->assertEquals($state, $this->getState($eAid));
		$this->assertEquals(NULL, $eAid['seedDifferences'], 'aucun écart sur une aide qui ne se relit plus');

		// Un brouillon relu : une aide que l'équipe a dépubliée pour la refondre
		$eAid['status'] = Aid::DRAFT;
		AidLib::update($eAid, ['status']);
		AidLib::check($eAid);

		$state = $this->getState($eAid);

		$this->seedCopy($id, $renamed);

		$eAid = $this->getAid($id);
		$this->assertEquals($state, $this->getState($eAid), 'ce que l\'équipe a écrit pendant la refonte reste');
		$this->assertEquals('AITA renommée', $eAid['seedDifferences']['properties']['name']);

	}

	/**
	 * Règle M1 : une reprise ne réécrit rien d'une aide relue, hors ses alias. Ce que la fiche dirait
	 * d'autre devient son écart, mesuré sur ce que l'équipe et la fiche écrivent toutes deux ; une fiche
	 * inchangée n'en rapporte aucun, et un écart gardé ne revient pas.
	 */
	public function testSeedChecked(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->deleteFixtureAids();

		ImportLib::seed(__DIR__.'/fixture');

		foreach($this->getFixtureIds() as $id) {
			AidLib::check(AidLib::getByAlias($id));
		}

		$id = 'national-masa-aita-installation';
		$eAid = $this->getAid($id);

		// Ce qu'aucune fiche ne porte ne se mesure pas : la case, le résumé, les mots-clés, le montant
		// plafond, l'assiette et la période des bornes. Sans build() : il refuserait la case sur cette aide, qui a un taux
		// (Aid::rateUnpublished.rate), et le cas veut justement une valeur que la reprise ne rapporte
		// pas, pour vérifier qu'une fiche inchangée ne la réécrit pas.
		$eAid['rateUnpublished'] = TRUE;
		$eAid['summary'] = 'Résumé de l\'équipe';
		$eAid['keywords'] = 'installation';
		$eAid['amountMax'] = 30000;
		$eAid['amountUnit'] = Aid::EURO;
		$eAid['boundBase'] = Aid::AID;
		$eAid['boundPeriod'] = Aid::YEAR;
		AidLib::update($eAid, ['rateUnpublished', 'summary', 'keywords', 'amountMax', 'amountUnit', 'boundBase', 'boundPeriod']);

		$state = $this->getState($eAid);

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(14, $report['kept'], 'toutes relues');
		$this->assertEquals(0, $report['differences'], 'une fiche inchangée ne rapporte aucun écart');
		$this->assertEquals(0, $report['updated']);
		$this->assertEquals($state, $this->getState($eAid), 'rien n\'est réécrit');

		// L'équipe corrige le taux, puis la fiche change de nom et d'adresse. Sans build() : il ne
		// vérifierait que la longueur du texte, que ce taux tient.
		$eAid['rate'] = 'Taux relu par l\'équipe';
		AidLib::update($eAid, ['rate']);

		$state = $this->getState($eAid);

		$renamed = fn(array $aid) => ['nom' => 'AITA renommée', 'url_source' => 'https://example.org/aita'] + $aid;

		$this->seedCopy($id, $renamed);

		$this->assertEquals($state, $this->getState($eAid), 'l\'écart ne réécrit rien');
		$this->assertEquals(Aid::PUBLISHED, $this->getAid($id)['status'], 'ni l\'état de publication');

		$differences = $this->getAid($id)['seedDifferences'];

		$this->assertEquals(['name', 'rate'], array_keys($differences['properties']));
		$this->assertEquals('AITA renommée', $differences['properties']['name']);
		$this->assertEquals('Variable selon le volet et les priorités régionales', $differences['properties']['rate'], 'la valeur que la fiche donne en entrée');
		$this->assertEquals([['url' => 'https://example.org/aita', 'kind' => Source::PRIMARY]], $differences['children']['Source']['added']);
		$this->assertEquals([['url' => 'https://mesdemarches.agriculture.gouv.fr/production-fili%C3%A8res/aide-candidats-a-l-installation', 'kind' => Source::PRIMARY]], $differences['children']['Source']['removed'], 'une adresse sans sa barre finale');

		// Garder l'aide : le même écart ne revient pas, un autre oui, et le premier qui revient efface le second
		AidLib::keepSeed($this->getAid($id), $differences, AidLib::getSeedFingerprint($differences));

		$this->seedCopy($id, $renamed);
		$this->assertEquals(NULL, $this->getAid($id)['seedDifferences'], 'l\'écart gardé ne revient pas');

		$this->seedCopy($id, fn(array $aid) => ['nom' => 'AITA autrement'] + $renamed($aid));
		$this->assertEquals('AITA autrement', $this->getAid($id)['seedDifferences']['properties']['name'], 'un écart neuf');

		$this->seedCopy($id, $renamed);
		$this->assertEquals(NULL, $this->getAid($id)['seedDifferences'], 'la fiche revenue à l\'écart gardé');

	}

	/**
	 * Un écart gardé se reconnaît à son empreinte, calculée sur l'écart relu en base. Un montant y fait
	 * l'aller-retour par la colonne JSON de MySQL : la même fiche rejouée ne doit pas le faire revenir.
	 */
	public function testSeedKeptAmount(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->deleteFixtureAids();

		ImportLib::seed(__DIR__.'/fixture');

		$id = 'national-masa-aita-installation';

		AidLib::check($this->getAid($id));

		foreach([7000.0, 7100, 7200.5, '7300'] as $floor) {

			$floored = fn(array $aid) => ['plancher_eur' => $floor] + $aid;

			$this->seedCopy($id, $floored);

			$differences = $this->getAid($id)['seedDifferences'];
			$this->assertTrue(array_key_exists('floor', $differences['properties']), 'un plancher de '.var_export($floor, TRUE).' fait un écart');
			$this->assertEquals(gettype($floor), gettype($differences['properties']['floor']), 'l\'écart relu porte le plancher tel que la fiche le donne');

			AidLib::keepSeed($this->getAid($id), $differences, AidLib::getSeedFingerprint($differences));

			$this->seedCopy($id, $floored);
			$this->assertEquals(NULL, $this->getAid($id)['seedDifferences'], 'un plancher de '.var_export($floor, TRUE).' gardé ne revient pas');

		}

	}

	/**
	 * La reprise ne retire pas une liste des pièces que l'équipe a saisie et qu'aucune fiche ne porte,
	 * et l'écart ne la compte pas comme retirée. Une adresse que la fiche cite garde une seule ligne et
	 * prend la nature de la fiche (CL-17 de L6, SourceLib::synchronize()).
	 */
	public function testSeedSparesPieces(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		// Le brouillon que la reprise réécrit : une aide dépubliée que personne n'a relue.
		AidLib::unpublish($this->getAid($id));

		$eAid = $this->getAid($id);

		$eSourcePieces = new Source(['aid' => $eAid]);

		$fw = new \FailWatch();

		$eSourcePieces->build(SourceLib::getPropertiesCreate(), [
			'url' => 'https://example.org/zqx-catalog-ai/pieces',
			'kind' => Source::PIECES,
		]);

		$fw->validate();

		SourceLib::create($eSourcePieces);

		ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(Source::PIECES, SourceLib::getById($eSourcePieces['id'])['kind'], 'la liste des pièces reste');
		$this->assertFalse(in_array($eSourcePieces['url'], array_column(ImportLib::getChildKeys($eAid, 'Source'), 'url'), TRUE), 'l\'écart ne la compte pas');
		$seedDifferences = $this->getAid($id)['seedDifferences'];
		$this->assertTrue($seedDifferences === NULL or array_key_exists('Source', $seedDifferences['children']) === FALSE, 'aucun écart de sources');

		// Le décor change la nature sans build(), qui la laisserait passer : le test veut une liste des
		// pièces à l'adresse même que la fiche donne pour officielle.
		$eSourcePrimary = Source::model()
			->select(['id', 'aid', 'url', 'kind'])
			->whereAid($eAid)
			->whereKind(Source::PRIMARY)
			->get();

		$eSourcePrimary['kind'] = Source::PIECES;
		SourceLib::update($eSourcePrimary, ['kind']);

		ImportLib::seed(__DIR__.'/fixture');

		$cSource = Source::model()
			->select(['id', 'kind'])
			->whereAid($eAid)
			->whereUrl($eSourcePrimary['url'])
			->getCollection();

		$this->assertEquals(1, $cSource->count(), 'appariée sans doublon');
		$this->assertEquals(Source::PRIMARY, $cSource->first()['kind'], 'elle prend la nature de la fiche');

	}

	/**
	 * Un brouillon jamais relu se réécrit comme avant, sauf ce que la veille a relevé de ses sources, et
	 * la case du taux non publié : un taux rendu la décoche, sans taux elle tient lieu de taux à la
	 * publication. Publiée, l'aide ne se réécrit plus et ne se dépublie jamais (cadrage de L5, R1 et R3).
	 */
	public function testSeedUnchecked(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		// Le brouillon que la reprise réécrit : une aide dépubliée que personne n'a relue.
		AidLib::unpublish($this->getAid($id));

		// Le décor pose ce que la veille écrit, qu'aucun formulaire ne saisit.
		$eSource = Source::model()
			->select(Source::getSelection())
			->whereAid($this->getAid($id))
			->whereKind(Source::PRIMARY)
			->get();

		$eSource['status'] = Source::DEAD;
		$eSource['failures'] = 3;
		SourceLib::update($eSource, ['status', 'failures']);

		// Sans build() : il refuserait la case sur une aide qui a un taux (Aid::rateUnpublished.rate),
		// et c'est cet état que la reprise doit défaire.
		$eAid = $this->getAid($id);
		$eAid['rateUnpublished'] = TRUE;
		AidLib::update($eAid, ['rateUnpublished']);

		ImportLib::seed(__DIR__.'/fixture');

		$eSourceAfter = SourceLib::getById($eSource['id']);
		$this->assertTrue($eSourceAfter->notEmpty(), 'la source gardée par son adresse');
		$this->assertEquals(Source::DEAD, $eSourceAfter['status']);
		$this->assertEquals(3, $eSourceAfter['failures']);

		$this->assertEquals(FALSE, $this->getAid($id)['rateUnpublished'], 'un taux rendu par la fiche décoche la case');
		$this->assertEquals(Aid::DRAFT, $this->getAid($id)['status'], 'sa seule source officielle morte la retient en brouillon');

		$eSourceAfter['status'] = Source::ALIVE;
		$eSourceAfter['failures'] = 0;
		SourceLib::update($eSourceAfter, ['status', 'failures']);

		// Sans taux dans la fiche, la case publie l'aide. Sans build() : même refus, l'aide porte encore
		// son taux, que la reprise suivante retire.
		$eAid = $this->getAid($id);
		$eAid['rateUnpublished'] = TRUE;
		AidLib::update($eAid, ['rateUnpublished']);

		$withoutRate = fn(array $aid) => ['taux' => 'non publié'] + $aid;

		$this->seedCopy($id, $withoutRate);

		$eAid = $this->getAid($id);
		$this->assertEquals(NULL, $eAid['rate']);
		$this->assertTrue($eAid['rateUnpublished']);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'la case tient lieu de taux');

		// Sans build() : il refuserait de décocher la case d'une aide publiée sans taux
		// (Aid::rateUnpublished.published), et c'est ce retrait que la reprise laisse à l'équipe.
		$eAid['rateUnpublished'] = FALSE;
		AidLib::update($eAid, ['rateUnpublished']);

		$this->seedCopy($id, $withoutRate);

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'la reprise ne dépublie jamais');
		$this->assertFalse($eAid['rateUnpublished'], 'ni ne réécrit la case d\'une aide publiée');

	}

	/**
	 * Ce qu'une reprise pourrait réécrire d'une aide : ses colonnes hors de ce que la reprise d'une aide
	 * relue écrit (alias, écart, date de mise à jour), et ses lignes filles, identifiants compris.
	 */
	private function getState(Aid $eAid): array {

		$read = function(\Element $element): array {

			$values = [];

			foreach($element as $property => $value) {

				if($value instanceof \Element) {
					$value = $value->empty() ? NULL : $value['id'];
				} else if($value instanceof \Set) {
					$value = (string)$value;
				}

				$values[$property] = $value;

			}

			return $values;

		};

		$state = [
			'Aid' => $read(Aid::model()
				->select(array_diff(Aid::getSelection(), ['aliases', 'seedDifferences', 'seedKept', 'updatedAt']))
				->whereId($eAid)
				->get())
		];

		foreach([Area::model(), Edition::model(), Source::model(), AidOrganization::model(), AidTag::model()] as $mModule) {

			$cElement = $mModule
				->select($mModule->getProperties())
				->whereAid($eAid)
				->sort(['id' => SORT_ASC])
				->getCollection();

			$state[$mModule->getModule()] = [];

			foreach($cElement as $element) {
				$state[$mModule->getModule()][] = $read($element);
			}

		}

		return $state;

	}

	/**
	 * Sème une copie du décor sur des aides neuves : une fiche qu'on relit pour éprouver la lecture, et
	 * non la reprise d'une aide publiée, que la reprise ne réécrit pas.
	 */
	private function seedFresh(string $id, \Closure $modify): array {

		$this->deleteFixtureAids();

		return $this->seedCopy($id, $modify);

	}

	private function countFixturePublished(): int {

		$statuses = [];

		foreach($this->getFixtureIds() as $id) {
			$eAid = AidLib::getByAlias($id);
			$statuses[$eAid['id']] = $eAid['status'];
		}

		return count(array_filter($statuses, fn(string $status) => $status === Aid::PUBLISHED));

	}

	private function getReviewReason(Aid $eAid): ?string {

		$eAidReview = Aid::model()
			->select(array_merge(['status'], AidLib::getSelectionReview()))
			->whereId($eAid)
			->get();

		return $eAidReview->getReviewReason();

	}

	private function getFixtureIds(): array {
		return array_map(
			fn(string $line) => json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR)['id'],
			file(__DIR__.'/fixture/fixture.aides.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
		);
	}

	private function setAdmin(): bool {

		$adminIds = \user\UserSetting::$admin;

		if($this->assertDecorPresent($adminIds !== [], 'un administrateur de Ouvretaferme déclaré dans UserSetting::$admin') === FALSE) {
			return FALSE;
		}

		\user\ConnectionLib::setOnline(\user\UserLib::getById($adminIds[0]));

		return TRUE;

	}

	/**
	 * Sème une copie du décor dont une fiche est modifiée, et ses tags remplacés si `$tags` est donné.
	 */
	private function seedCopy(string $id, \Closure $modify, ?array $tags = NULL, ?\Closure $modifyOrganizations = NULL): array {

		$path = sys_get_temp_dir().'/aid-fixture-'.uniqid();
		mkdir($path);

		foreach(glob(__DIR__.'/fixture/*') as $file) {
			copy($file, $path.'/'.basename($file));
		}

		$this->rewriteLines($path.'/fixture.aides.jsonl', fn(array $aid) => ($aid['id'] === $id) ? $modify($aid) : $aid);

		if($tags !== NULL) {
			$this->rewriteLines($path.'/tags.jsonl', fn(array $line) => ($line['id'] === $id) ? ['tags' => $tags] + $line : $line);
		}

		if($modifyOrganizations !== NULL) {
			$organizations = json_decode(file_get_contents($path.'/organismes.json'), TRUE, 512, JSON_THROW_ON_ERROR);
			file_put_contents($path.'/organismes.json', json_encode($modifyOrganizations($organizations), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		}

		try {
			return ImportLib::seed($path);
		} finally {
			array_map(unlink(...), glob($path.'/*'));
			rmdir($path);
		}

	}

	private function rewriteLines(string $file, \Closure $map): void {

		$lines = [];

		foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			// Un montant flottant reste flottant dans la fiche réécrite (testSeedKeptAmount()).
			$lines[] = json_encode($map(json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR)), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
		}

		file_put_contents($file, implode("\n", $lines)."\n");

	}

	/**
	 * Les deux mentions de la relecture de la recherche (ImportLib::MENTION_CONFIRM et MENTION_HOLD) :
	 * un brouillon dont il ne reste qu'un chiffre à faire confirmer se publie en « à vérifier », un
	 * brouillon à vérifier avant toute publication attend l'équipe. Sur une aide publiée, la mention fait
	 * un écart à elle seule, sans rien réécrire ni dépublier, et la même mention gardée ne revient pas
	 * (cadrage de L5, R3 ; arbitrage de V-Sub sur le plan, point 4).
	 */
	public function testMentions(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status']);

		$state = $this->getState($eAid);
		$held = fn(array $aid) => ['particularites' => ImportLib::MENTION_HOLD.' (L4) : provenance interne affichée.'] + $aid;

		$report = $this->seedCopy($id, $held);

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'une aide publiée qui porte la mention le reste');
		$this->assertEquals($state, $this->getState($eAid), 'rien n\'est réécrit');
		$this->assertTrue($eAid['seedDifferences']['held'], 'la mention fait un écart');
		$this->assertEquals(1, $report['heldKept']);

		AidLib::keepSeed($eAid, $eAid['seedDifferences'], AidLib::getSeedFingerprint($eAid['seedDifferences']));

		$this->seedCopy($id, $held);
		$this->assertEquals(NULL, $this->getAid($id)['seedDifferences'], 'la mention gardée ne revient pas');

		// Un brouillon jamais relu se réécrit
		AidLib::unpublish($this->getAid($id));

		$this->seedCopy($id, fn(array $aid) => ['particularites' => 'Montant à vérifier sur la page.'] + $aid);

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::TO_CHECK, $eAid['verification']);
		$this->assertEquals(Aid::DRAFT, $eAid['status'], 'une aide à vérifier sans la mention attend l\'équipe');

		$report = $this->seedCopy($id, fn(array $aid) => ['particularites' => ImportLib::MENTION_CONFIRM.' : chiffre tenu d\'un relais.'] + $aid);

		$eAid = $this->getAid($id);
		$this->assertEquals(Aid::TO_CHECK, $eAid['verification']);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'il ne reste qu\'un chiffre à faire confirmer');
		$this->assertEquals(0, $report['heldKept']);

		AidLib::unpublish($this->getAid($id));

		$report = $this->seedCopy($id, $held);

		$this->assertEquals(Aid::DRAFT, $this->getAid($id)['status'], 'un brouillon qui porte la mention attend l\'équipe');
		$this->assertEquals(0, $report['heldKept'], 'réécrit, il n\'a pas d\'écart');

	}

	/**
	 * Une chambre d'agriculture n'est pas une exploitation : une aide qui ne s'adresse qu'à elle et à
	 * des collectivités passe par un tiers.
	 */
	public function testBeneficiariesChamber(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = '53-region-agri-bio';

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Porteurs publics : collectivités (hors région), parcs naturels, chambres d\'agriculture'] + $aid);

		$eAid = $this->getAid($id);
		$this->assertEquals([Aid::COMMUNITY], $eAid['beneficiaries']->values());
		$this->assertFalse($eAid['direct']);

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Éleveurs, via la chambre d\'agriculture'] + $aid);

		$eAid = $this->getAid($id);
		$this->assertEquals([Aid::FARM], $eAid['beneficiaries']->values(), 'l\'éleveur reste une exploitation');
		$this->assertTrue($eAid['direct']);

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Chambre d\'agriculture ou structure collective'] + $aid);
		$this->assertEquals([Aid::GROUP], $this->getAid($id)['beneficiaries']->values());

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Propriétaires de parcelles agricoles'] + $aid);

		$eAid = $this->getAid($id);
		$this->assertEquals([Aid::LANDOWNER], $eAid['beneficiaries']->values());
		$this->assertTrue($eAid['direct'], 'le propriétaire dépose lui-même');

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Propriétaires ou détenteurs de chiens de protection de troupeau, éleveurs'] + $aid);
		$this->assertEquals([Aid::FARM], $this->getAid($id)['beneficiaries']->values(), 'le propriétaire d\'un chien n\'est pas un propriétaire foncier');

		$this->seedFresh($id, fn(array $aid) => ['beneficiaires' => 'Habitants, agriculteurs avec accord du propriétaire'] + $aid);
		$this->assertEquals([Aid::FARM], $this->getAid($id)['beneficiaries']->values(), 'le propriétaire qui donne son accord ne dépose pas');

	}

	/**
	 * Un organisme qui prend un nom court garde l'ancien en variante : la reprise retrouve sa ligne par
	 * elle et la renomme, sans en créer une seconde, et les fiches qui citent l'ancien nom le suivent.
	 */
	public function testOrganizationRenamed(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = 'national-masa-aita-installation';

		// Des noms propres au test : ceux du décor peuvent déjà avoir été renommés par le semis de dev.
		$name = 'Organisme du test au nom long '.uniqid();
		$nameShort = 'Organisme du test '.uniqid();

		$withName = fn(array $organizations, array $values) => [
			...$organizations,
			$values + array_find($organizations, fn(array $organization) => $organization['organisme'] === 'MSA')
		];

		$report = $this->seedCopy($id, fn(array $aid) => ['organisme' => $name] + $aid, modifyOrganizations: fn(array $organizations) => $withName($organizations, ['organisme' => $name, 'variantes' => []]));

		$this->assertEquals([], $report['fundersUnknown']);

		$eOrganization = OrganizationLib::getByName($name);
		$this->assertTrue($eOrganization->notEmpty());

		$report = $this->seedCopy(
			$id,
			fn(array $aid) => ['organisme' => $name] + $aid,
			modifyOrganizations: fn(array $organizations) => $withName($organizations, ['organisme' => $nameShort, 'variantes' => [$name]])
		);

		$this->assertEquals([$name => $nameShort], $report['organizations']['renamed']);
		$this->assertEquals(0, $report['organizations']['created']);
		$this->assertEquals($eOrganization['id'], OrganizationLib::getByName($nameShort)['id'], 'la même ligne, renommée');
		$this->assertTrue(OrganizationLib::getByName($name)->empty());
		$this->assertEquals([], $report['fundersUnknown']);

		$this->assertEquals($eOrganization['id'], $this->getAid($id)['organization']['id'], 'la fiche cite l\'ancien nom');

	}

	/**
	 * Une agence de l'eau saisie en national, en PAC ou hors échelon prend l'échelon « autre », qu'elle
	 * soit ou non dans DUPLICATES ; saisie en région, elle garde la région.
	 */
	public function testWaterAgencyLevel(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(Aid::OTHER, $this->getAid('national-aelb-accompagnement-agriculteurs')['level']);
		$this->assertEquals(Aid::REGION, $this->getAid('44-aesn-maec-ciff')['level']);

		$this->seedFresh('44-aesn-maec-ciff', fn(array $aid) => ['echelon' => 'national'] + $aid);
		$this->assertEquals(Aid::OTHER, $this->getAid('44-aesn-maec-ciff')['level'], 'hors DUPLICATES');

	}

	/**
	 * Un organisme absent du fichier des organismes fait refuser sa fiche et se compte, au lieu
	 * d'arrêter la reprise ; un identifiant absorbé absent d'un extrait ne l'arrête pas non plus.
	 */
	public function testFunderUnknown(): void {

		$this->setClock(self::NOW);
		$this->deleteFixtureAids();

		$id = '53-region-agri-bio';

		$report = $this->seedCopy($id, fn(array $aid) => ['organisme' => 'Organisme absent du décor'] + $aid);

		$this->assertEquals(['Organisme absent du décor' => [$id]], $report['fundersUnknown']);
		$this->assertTrue(AidLib::getByAlias($id)->empty());
		$this->assertEquals(13, $report['created'], 'les autres fiches entrent');

		$kept = '28-pnr-normandie-maine-plantation-haies';
		$aids = ImportLib::merge([$kept => ['echelon' => 'intercommunal', 'departements' => ['61']]]);

		$this->assertEquals([$kept, '52-pnr-normandie-maine-plantation'], $aids[$kept]['aliases']);
		$this->assertEquals(['61'], $aids[$kept]['departements']);

	}

	/**
	 * Une aide créée sous un identifiant qu'une autre absorbe depuis est archivée par la reprise, sauf
	 * relue par l'équipe, que la reprise nomme sans y toucher.
	 */
	public function testAbsorbedArchived(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->deleteFixtureAids();

		ImportLib::seed(__DIR__.'/fixture');

		$absorbed = '52-pnr-normandie-maine-plantation';

		// Les alias ne se saisissent pas (ImportLib::seedAid()) : seule une reprise les pose, et le décor
		// rejoue celle qui a créé l'aide avant que DUPLICATES la fasse absorber.
		$eAidOld = $this->createAid($this->createOrganization(), 'Aide semée avant la fusion', Aid::PERMANENT, FALSE, [], [[Source::PRIMARY, Source::ALIVE]]);
		$eAidOld['aliases'] = [$absorbed];
		AidLib::update($eAidOld, ['aliases']);

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals([$absorbed], $report['absorbedArchived']);
		$this->assertEquals(Aid::ARCHIVED, AidLib::getById($eAidOld['id'])['status']);

		$report = ImportLib::seed(__DIR__.'/fixture');
		$this->assertEquals([], $report['absorbedArchived'], 'une aide archivée ne l\'est qu\'une fois');

		$eAidChecked = $this->createAid($this->createOrganization(), 'Aide relue avant la fusion', Aid::PERMANENT, FALSE, [], [[Source::PRIMARY, Source::ALIVE]]);
		$eAidChecked['aliases'] = [$absorbed];
		AidLib::update($eAidChecked, ['aliases']);
		AidLib::check($eAidChecked);

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals([$absorbed], $report['absorbedKept']);
		$this->assertEquals(Aid::DRAFT, AidLib::getById($eAidChecked['id'])['status'], 'l\'équipe l\'archive elle-même');

		// Une aide publiée absorbée n'est pas archivée non plus (cadrage de L5, R5)
		AidLib::archive($eAidChecked);

		$eAidPublished = $this->createAid($this->createOrganization(), 'Aide publiée avant la fusion', Aid::PERMANENT, TRUE, [], [[Source::PRIMARY, Source::ALIVE]]);
		$eAidPublished['aliases'] = [$absorbed];
		AidLib::update($eAidPublished, ['aliases']);

		$report = ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals([$absorbed], $report['absorbedKept']);
		$this->assertEquals(Aid::PUBLISHED, AidLib::getById($eAidPublished['id'])['status']);

	}

	/**
	 * Les étapes de properties() que la reprise ne rencontre pas, une par build().
	 */
	public function testRules(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eOrganization = $this->createOrganization();
		$eAid = $this->createAid($eOrganization, 'Aide des règles', Aid::CALL, FALSE, [], [[Source::PRIMARY, Source::ALIVE]]);
		$eAidOther = $this->createAid($eOrganization, 'Autre aide des règles', Aid::CALL, FALSE, [], [[Source::PRIMARY, Source::ALIVE]]);

		$fw = new \FailWatch();
		new Edition(['aid' => $eAid])->build(['startDate', 'endDate'], ['startDate' => '2026-10-01', 'endDate' => '2026-09-30']);
		$this->assertTrue($fw->has('Edition::endDate.consistency'), 'une édition close avant de s\'ouvrir');

		$fw = new \FailWatch();
		new Edition(['aid' => $eAid])->build(['startDate', 'endDate'], ['startDate' => '2026-10-01', 'endDate' => '2026-10-01']);
		$this->assertFalse($fw->has('Edition::endDate.consistency'), 'une édition d\'un jour');

		$fw = new \FailWatch();
		new AidLink(['aid' => $eAid])->build(['target'], ['target' => $eAid['id']]);
		$this->assertTrue($fw->has('AidLink::target.self'), 'une aide liée à elle-même');

		$fw = new \FailWatch();
		new AidLink(['aid' => $eAid])->build(['target'], ['target' => $eAidOther['id']]);
		$this->assertFalse($fw->has('AidLink::target.self'));

		$fw = new \FailWatch();
		AidLib::getById($eAid['id'])->build(['parent'], ['parent' => $eAid['id']]);
		$this->assertTrue($fw->has('Aid::parent.self'), 'une aide parente d\'elle-même');

		$fw = new \FailWatch();
		AidLib::getById($eAid['id'])->build(['parent'], ['parent' => $eAidOther['id']]);
		$this->assertFalse($fw->has('Aid::parent.self'));

		$fw = new \FailWatch();
		new Aid()->build(['productions'], ['productions' => ['maraichage', 'licorne']]);
		$this->assertTrue($fw->has('Aid::productions.unknown'), 'une production hors de structure\\Production');

		$fw = new \FailWatch();
		new Aid()->build(['productions'], ['productions' => ['maraichage']]);
		$this->assertFalse($fw->has('Aid::productions.unknown'));

		$eAid = AidLib::getById($eAid['id']);
		$this->assertTrue($eAid->acceptArchive());

		AidLib::archive($eAid);
		$this->assertFalse(AidLib::getById($eAid['id'])->acceptArchive(), 'une aide archivée ne s\'archive plus');

	}

	/**
	 * Les lignes filles des aides du décor, par module.
	 */
	private function countChildren(): array {

		$ids = [];

		foreach(file(__DIR__.'/fixture/fixture.aides.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

			$eAid = AidLib::getByAlias(json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR)['id']);

			if($eAid->notEmpty()) {
				$ids[$eAid['id']] = $eAid['id'];
			}

		}

		$children = [];

		foreach([Area::model(), Edition::model(), Source::model(), AidOrganization::model(), AidTag::model()] as $mModule) {
			$children[$mModule->getModule()] = $mModule->whereAid('IN', array_values($ids))->count();
		}

		return $children;

	}

	private function getAid(string $alias): Aid {

		$eAid = AidLib::getByAlias($alias);

		$this->assertDecorPresent($eAid->notEmpty(), 'l\'aide '.$alias.' reprise du décor');

		return $eAid;

	}

	private function getDepartments(Aid $eAid): array {

		$departments = Area::model()
			->whereAid($eAid)
			->getColumn('department');

		sort($departments);

		return $departments;

	}

	private function hasLabel(?\Set $labels, int $label): bool {
		return ($labels !== NULL and $labels->value($label));
	}

	private function createOrganization(): Organization {

		$eOrganization = new Organization();

		$fw = new \FailWatch();

		$eOrganization->build(OrganizationLib::getPropertiesCreate(), [
			'name' => 'Organisme du test '.uniqid(),
			'type' => Organization::REGION,
			'level' => Organization::REGION,
			'url' => NULL,
			'grants' => TRUE,
		]);

		$fw->validate();

		OrganizationLib::create($eOrganization);

		return $eOrganization;

	}

	private function createAid(Organization $eOrganization, string $name, string $calendar, bool $isPublished, array $editions, array $sources, ?string $rate = '50 %'): Aid {

		$eAid = new Aid();

		$fw = new \FailWatch();

		$eAid->build(AidLib::getPropertiesCreate(), [
			'name' => $name,
			'organization' => $eOrganization,
			'level' => Aid::REGION,
			'type' => Aid::GRANT,
			'calendar' => $calendar,
			'rate' => $rate,
			'productions' => [],
			'direct' => TRUE,
			'verification' => Aid::PRIMARY,
		]);

		$fw->validate();

		AidLib::create($eAid);

		foreach($editions as [$state, $endDate]) {

			$eEdition = new Edition(['aid' => $eAid]);

			$fw = new \FailWatch();

			$eEdition->build(EditionLib::getPropertiesCreate(), [
				'startDate' => '2026-01-01',
				'endDate' => $endDate,
				'state' => $state,
			]);

			$fw->validate();

			EditionLib::create($eEdition);

		}

		foreach($sources as [$kind, $status]) {

			$eSource = new Source(['aid' => $eAid]);

			$fw = new \FailWatch();

			$eSource->build(SourceLib::getPropertiesCreate(), [
				'url' => 'https://www.example.org/aide-'.$eAid['id'],
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
