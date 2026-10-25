<?php
namespace aid;

/**
 * L'outil « Aides agricoles » de la Boîte à outils (lot L3-outil) : le département lu dans le code
 * postal, les critères de l'adresse ramenés à leurs listes fermées, ce que le lieu, les productions
 * et le bio laissent, les comptes par projet et par tag, la fiche, et la bêta qui garde les quatre
 * routes.
 *
 * Le décor est écrit dans la transaction de chaque test et s'annule à la fin : un organisme, des
 * aides aux noms préfixés, deux tags neufs. Le catalogue de dev en porte d'autres, que chaque
 * assertion écarte en ne lisant que les aides du décor.
 *
 * Lancement : dev/test package=aid file=ToolAi
 */
class ToolAiTest extends \dev\TransactionTest {

	const NOW = '2026-10-22 12:00:00';

	const POINT_GAP = [6.08, 44.56];

	// Une structure du décor dans la bêta en dev, et une qui n'y est pas.
	const STRUCTURE_BETA = \structure\FixtureAiLib::F1;
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	private \structure\Structure $eStructure;
	private Organization $eOrganization;
	private Tag $eTagInstall;
	private Tag $eTagInvest;

	/** Un identifiant par membre injecté : `MemberLib::$cMemberOnline` est indexé par utilisateur. */
	private int $userId = 940000;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Aid::model();
	}

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

	}

	public function setUp(): void {

		parent::setUp();

		// F1 est située par le décor, à Tallende : chaque cas part sans lieu, le département venant du
		// code postal qu'il construit, et pose lui-même le lieu qu'il éprouve.
		$this->setPlace(NULL, NULL);

		$this->eOrganization = $this->createOrganization();
		$this->eTagInstall = $this->createTag('test-l3-installation', [Tag::INSTALL]);
		$this->eTagInvest = $this->createTag('test-l3-materiel', [Tag::INVEST, Tag::INSTALL]);

	}

	public function tearDown(): void {

		parent::tearDown();

		\user\ConnectionLib::setOnline($this->eUserInitial);
		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		// Le lieu posé par `setPlace()` vit dans le cache du processus, que les fichiers suivants liraient.
		$this->forgetFeatures();

	}

	public function testDepartmentByPostcode(): void {

		$cases = [
			'31000' => '31',
			'05400' => '05',
			'20090' => '2A',
			'20200' => '2B',
			'97110' => '971',
			'97411' => '974',
			'65700]' => '65',
			' 63450' => '63',
			'[NON-DIFFUSIBLE]' => NULL,
			'1234' => NULL,
			'98000' => NULL,
			'' => NULL,
		];

		foreach($cases as $postcode => $department) {
			$this->assertEquals($department, AidSetting::getDepartmentByPostcode((string)$postcode), 'code postal « '.$postcode.' »');
		}

		$this->assertEquals(NULL, AidSetting::getDepartmentByPostcode(NULL));

	}

	/**
	 * Une aide qu'un tiers dépose ne le redit pas quand son public est ce tiers. Un public qui compte
	 * une exploitation garde la mention : elle apprend à l'exploitation qu'elle ne dépose pas.
	 */
	public function testApplicantNamed(): void {

		$isApplicantNamed = function(array $beneficiaries, bool $direct): bool {

			$eAid = $this->createAid('déposant '.implode(' ', $beneficiaries).($direct ? ' direct' : ''));
			$eAid['beneficiaries'] = new \Set($beneficiaries);
			$eAid['direct'] = $direct;
			AidLib::update($eAid, ['beneficiaries', 'direct']);

			return AidLib::getSheet($eAid['id'], NULL)['isApplicantNamed'];

		};

		$this->assertTrue($isApplicantNamed([Aid::CUMA], FALSE), 'des CUMA, déposé par une CUMA');
		$this->assertTrue($isApplicantNamed([Aid::GROUP, Aid::ASSOCIATION], FALSE), 'aucune exploitation dans le public');
		$this->assertFalse($isApplicantNamed([Aid::FARM, Aid::CUMA], FALSE), 'des exploitations et des CUMA, déposé par une CUMA');
		$this->assertFalse($isApplicantNamed([Aid::YOUNG_FARMER, Aid::GROUP], FALSE), 'un jeune agriculteur compte comme une exploitation');
		$this->assertFalse($isApplicantNamed([Aid::NEW_FARMER, Aid::CUMA], FALSE), 'un nouvel installé aussi');
		$this->assertFalse($isApplicantNamed([Aid::LANDOWNER, Aid::COMMUNITY], FALSE), 'un propriétaire foncier aussi');
		$this->assertFalse($isApplicantNamed([Aid::FARM], FALSE), 'déposé par un intermédiaire absent du public');
		$this->assertFalse($isApplicantNamed([], FALSE), 'un public vide ne désigne aucun déposant');
		$this->assertFalse($isApplicantNamed([Aid::CUMA], TRUE), 'une CUMA qui dépose pour elle-même n\'a pas de déposant à nommer');

	}

	public function testRateShort(): void {

		$this->assertEquals(NULL, AidLib::getRateShort(NULL, Aid::GRANT));
		$this->assertEquals('40 % des dépenses', AidLib::getRateShort('40 % des dépenses ; 60 % pour les jeunes agriculteurs', Aid::GRANT));

		$this->assertEquals(NULL, AidLib::getRateShort('De 4 500 à 11 500 € selon la zone et trois modulations ; 2 000 € de plus', Aid::GRANT), 'une proposition trop longue pour une ligne ne se coupe pas, elle se tait');
		$this->assertEquals(str_repeat('1', AidSetting::RATE_SHORT_LENGTH), AidLib::getRateShort(str_repeat('1', AidSetting::RATE_SHORT_LENGTH).' ; b', Aid::GRANT), 'la borne comprise');

		$this->assertEquals(NULL, AidLib::getRateShort('Prise en charge partielle ; 80 % des frais', Aid::GRANT), 'une proposition sans chiffre se tait, elle se lirait comme un montant');
		$this->assertEquals('Forfait de 1 500 €', AidLib::getRateShort('Forfait de 1 500 €', Aid::GRANT));

		$this->assertEquals('Conseil gratuit', AidLib::getRateShort('Conseil gratuit ; sur rendez-vous', Aid::SERVICE), 'la gratuité d\'un service est ce que la structure y gagne');
		$this->assertEquals(NULL, AidLib::getRateShort('Gratuit', Aid::GRANT), 'hors d\'un service, la gratuité ne dit pas un montant');
		$this->assertEquals(NULL, AidLib::getRateShort('Forfait, montant non publié', Aid::SERVICE), 'un service sans chiffre ni gratuité se tait');

	}

	/**
	 * Absent, un critère vaut le profil ; vide, il est retiré ; inconnu, il est ignoré comme s'il
	 * n'avait pas été posé.
	 */
	public function testFilters(): void {

		$eStructure = $this->getStructure('05400', \structure\Structure::YES);

		$filters = AidLib::getFilters($eStructure, []);

		$this->assertEquals('05', $filters['department']);
		$this->assertTrue($filters['organic'], 'une structure en bio garde les aides réservées au bio');
		$this->assertEquals(NULL, $filters['project']);
		$this->assertTrue($filters['tag']->empty());
		$this->assertFalse($filters['all']);

		$this->assertEquals(NULL, AidLib::getFilters($eStructure, ['department' => ''])['department'], 'le département retiré');
		$this->assertEquals('05', AidLib::getFilters($eStructure, ['department' => '99'])['department'], 'un département inconnu laisse le profil');
		$this->assertEquals('2A', AidLib::getFilters($eStructure, ['department' => '2A'])['department']);
		$this->assertEquals([], AidLib::getFilters($eStructure, ['productions' => ''])['productions'], 'les productions retirées');
		$this->assertEquals(['vigne'], AidLib::getFilters($eStructure, ['productions' => 'vigne,inconnue'])['productions']);
		$this->assertFalse(AidLib::getFilters($eStructure, ['organic' => '0'])['organic']);
		$this->assertEquals(NULL, AidLib::getFilters($eStructure, ['project' => '3'])['project'], 'deux projets à la fois ne sont pas un projet');
		$this->assertEquals(Tag::INSTALL, AidLib::getFilters($eStructure, ['project' => (string)Tag::INSTALL])['project']);
		$this->assertTrue(AidLib::getFilters($eStructure, ['tag' => 'inconnu'])['tag']->empty());
		$this->assertEquals($this->eTagInstall['id'], AidLib::getFilters($eStructure, ['tag' => 'test-l3-installation'])['tag']['id']);
		$this->assertEquals(0, $filters['page']);
		$this->assertEquals(2, AidLib::getFilters($eStructure, ['page' => '2'])['page']);
		$this->assertEquals(0, AidLib::getFilters($eStructure, ['page' => '-1'])['page'], 'une page négative est la première');
		$this->assertEquals(0, AidLib::getFilters($eStructure, ['page' => ['2']])['page']);
		$this->assertEquals(NULL, AidLib::getFilters($eStructure, ['project' => [(string)Tag::INSTALL]])['project'], 'un projet en tableau n\'est pas un projet');

		$this->assertFalse(AidLib::getFilters($this->getStructure('05400', \structure\Structure::NO), [])['organic'], 'hors bio, le filtre est retiré d\'office');
		$this->assertEquals(NULL, AidLib::getFilters($this->getStructure(NULL, \structure\Structure::NO), [])['department'], 'sans code postal, aucun département');

	}

	/**
	 * Le retour d'une liste mène à l'accueil de l'outil, qui garde le lieu, les productions et le bio ;
	 * celui de l'accueil mène à la Boîte.
	 */
	public function testHome(): void {

		$eStructure = $this->getStructure('05400', \structure\Structure::YES);

		$this->assertTrue(AidUi::isHome(AidLib::getFilters($eStructure, [])));
		$this->assertTrue(AidUi::isHome(AidLib::getFilters($eStructure, ['department' => '', 'organic' => '0'])), 'le lieu et le bio ne font pas une liste');

		foreach([
			'un projet' => ['project' => (string)Tag::INSTALL],
			'un tag' => ['tag' => 'test-l3-installation'],
			'toutes les aides' => ['all' => '1'],
		] as $label => $input) {

			$filters = AidLib::getFilters($eStructure, $input + ['department' => '2A', 'organic' => '0', 'page' => '2']);

			$this->assertFalse(AidUi::isHome($filters), $label.' ouvre une liste');
			$this->assertEquals(
				AidUi::url($eStructure, AidLib::getFilters($eStructure, ['department' => '2A', 'organic' => '0'])),
				AidUi::urlHome($eStructure, $filters),
				$label.' : le retour mène à l\'accueil, département et bio gardés, sans page'
			);

		}

	}

	/**
	 * Une liste se lit par pages de `AidSetting::PER_LIST`, dans son ordre ; une liste vide a une page.
	 */
	public function testPages(): void {

		$cAid = new \Collection();

		$this->assertEquals(1, AidLib::countPages($cAid));
		$this->assertTrue(AidLib::getPage($cAid, 0)->empty());

		for($id = 1; $id <= AidSetting::PER_LIST * 2 + 1; $id++) {
			$cAid[$id] = new Aid(['id' => $id]);
		}

		$this->assertEquals(3, AidLib::countPages($cAid));
		$this->assertEquals(range(1, AidSetting::PER_LIST), AidLib::getPage($cAid, 0)->getIds());
		$this->assertEquals([AidSetting::PER_LIST * 2 + 1], AidLib::getPage($cAid, 2)->getIds(), 'la dernière page porte le reste');
		$this->assertTrue(AidLib::getPage($cAid, 3)->empty(), 'au-delà de la dernière, rien');

	}

	/**
	 * Le lieu, les productions et le bio, sur des aides affichées ; et ce qui n'est pas affiché n'entre
	 * nulle part.
	 */
	public function testByFilters(): void {

		$this->setClock(self::NOW);

		$national = $this->createAid('nationale', level: Aid::NATIONAL);
		$alps = $this->createAid('Hautes-Alpes', departments: ['05']);
		$garonne = $this->createAid('Haute-Garonne', departments: ['31']);
		$guadeloupe = $this->createAid('État en Guadeloupe', level: Aid::NATIONAL, departments: ['971']);
		$vine = $this->createAid('vigne', level: Aid::NATIONAL, productions: ['vigne']);
		$organic = $this->createAid('bio', level: Aid::NATIONAL, labels: [Aid::AB]);
		$draft = $this->createAid('brouillon', isPublished: FALSE);
		$closed = $this->createAid('appel clos', calendar: Aid::CALL, editions: [[Edition::CLOSED, NULL]]);

		$profile = [
			'department' => '05',
			'productions' => ['maraichage'],
			'organic' => TRUE,
		];

		$this->assertAids([$national, $alps, $organic], $profile, 'le profil');
		$this->assertAids([$national, $alps], ['organic' => FALSE] + $profile, 'sans le bio');
		$this->assertAids([$national, $alps, $garonne, $guadeloupe, $organic], ['department' => NULL] + $profile, 'sans le département');
		$this->assertAids([$national, $alps, $vine, $organic], ['productions' => []] + $profile, 'sans les productions');
		$this->assertAids([$national, $guadeloupe, $organic], ['department' => '971'] + $profile, 'une mesure de l\'État bornée à la Guadeloupe');

		$cAid = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]);

		$this->assertFalse($cAid->offsetExists($draft['id']), 'un brouillon n\'est jamais listé');
		$this->assertFalse($cAid->offsetExists($closed['id']), 'un appel clos n\'est jamais listé');

	}

	public function testCounts(): void {

		$this->setClock(self::NOW);

		$both = $this->createAid('installation et matériel', tags: [$this->eTagInstall, $this->eTagInvest]);
		$install = $this->createAid('installation', tags: [$this->eTagInstall]);
		$this->createAid('sans tag');

		$cAid = $this->getDecor(AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]));

		$this->assertEquals(Tag::INSTALL | Tag::INVEST, $cAid[$both['id']]['projects']);

		$projects = AidLib::countByProject($cAid);

		$this->assertEquals(2, $projects[Tag::INSTALL], 'une aide à cheval sur deux projets compte dans les deux');
		$this->assertEquals(1, $projects[Tag::INVEST]);
		$this->assertEquals(0, $projects[Tag::LAND]);

		$cTag = AidLib::countByTag($cAid, Tag::INSTALL);

		$this->assertEquals([$this->eTagInstall['id'], $this->eTagInvest['id']], array_values($cTag->getIds()), 'du plus fourni au moins fourni');
		$this->assertEquals(2, $cTag[$this->eTagInstall['id']]['count']);

		$this->assertEquals([$both['id']], array_values(AidLib::filterByProject($cAid, Tag::INSTALL, $this->eTagInvest)->getIds()));
		$ids = AidLib::filterByProject($cAid, Tag::INSTALL, new Tag())->getIds();
		sort($ids);

		$this->assertEquals([$both['id'], $install['id']], $ids);

	}

	/**
	 * La fiche : vide hors affichage, à faire confirmer sur une aide CONFLICT ou TO_CHECK, le taux
	 * d'une édition ouverte, et les aides qu'elle complète, affichées seulement.
	 */
	public function testSheet(): void {

		$this->setClock(self::NOW);

		$this->assertTrue(AidLib::getSheet($this->createAid('brouillon', isPublished: FALSE)['id'], NULL)->empty(), 'un brouillon ne s\'ouvre pas, même par son adresse');
		$this->assertTrue(AidLib::getSheet($this->createAid('source morte', sources: [[Source::PRIMARY, Source::DEAD]])['id'], NULL)->empty());

		$complement = $this->createAid('complément');
		$complementDraft = $this->createAid('complément en brouillon', isPublished: FALSE);

		$eAid = $this->createAid('contredite',
			calendar: Aid::CALL,
			editions: [[Edition::OPEN, '2026-10-23', '30 % en 2026']],
			sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]],
			departments: ['04', '05', '06', '13', '83', '84', '31'],
			verification: Aid::CONFLICT,
			complements: [$complement, $complementDraft]
		);

		// Créés à rebours de l'ordre alphabétique, pour que seul le tri de getSheet() les remette en ordre
		$eOrganizationSecond = $this->createOrganization('Organisme du test L3 Z '.uniqid());
		$eOrganizationFirst = $this->createOrganization('Organisme du test L3 A '.uniqid());

		foreach([$eOrganizationSecond, $eOrganizationFirst] as $eOrganization) {

			$eAidOrganization = new AidOrganization(['aid' => $eAid]);

			$fw = new \FailWatch();
			$eAidOrganization->build(AidOrganizationLib::getPropertiesCreate(), ['organization' => $eOrganization['id'], 'role' => AidOrganization::COFUNDER]);
			$fw->validate();

			AidOrganizationLib::create($eAidOrganization);

		}

		$eAidSheet = AidLib::getSheet($eAid['id'], NULL);

		$this->assertTrue($eAidSheet->notEmpty());
		$this->assertEquals(
			array_values(array_map(fn(Organization $eOrganization) => $eOrganization['name'], [$eOrganizationFirst, $eOrganizationSecond])),
			array_values($eAidSheet['cOrganizationCofunder']->getColumn('name')),
			'les cofinanceurs en éléments, rangés par nom'
		);
		$this->assertEquals('30 % en 2026', $eAidSheet['rate'], 'le taux de l\'édition ouverte l\'emporte');
		$this->assertEquals('2026-10-23', $eAidSheet['endDate']);
		$this->assertTrue($eAidSheet['endsTomorrow']);
		$this->assertEquals('https://www.example.org/aide-'.$eAid['id'].'-primary', $eAidSheet['eSourcePrimary']['url']);
		$this->assertTrue($eAidSheet['isUnconfirmed'], 'sources contradictoires');
		$this->assertEquals(['basins' => [], 'regions' => ['93'], 'departments' => ['31']], $eAidSheet['places'], 'la région entière se nomme par la région');
		$this->assertEquals([$complement['id']], array_values($eAidSheet['cAidComplement']->getIds()), 'un complément en brouillon ne se montre pas');

		$eAidPrimary = $this->createAid('lue sur sa source', sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]]);
		$eAidToCheck = $this->createAid('à confirmer', sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]], verification: Aid::TO_CHECK);

		$this->assertFalse(AidLib::getSheet($eAidPrimary['id'], NULL)['isUnconfirmed'], 'lue sur sa source officielle');
		$this->assertTrue(AidLib::getSheet($eAidToCheck['id'], NULL)['isUnconfirmed'], 'aucune source n\'a pu confirmer');

	}

	/**
	 * La fiche montre les pièces d'une aide que la structure dépose elle-même, la demande d'aide avant
	 * la demande de paiement, et aucune d'une aide déposée par un tiers ou versée sans dossier, même
	 * saisies avant que sa forme change (Aid::acceptPieces()).
	 */
	public function testSheetPieces(): void {

		$eAid = $this->createAid('pièces');
		$eAidAutomatic = $this->createAid('pièces automatique', calendar: Aid::AUTOMATIC);

		// Saisies à rebours, pour que seul le tri de getSheet() remette la demande d'aide en tête.
		foreach([[AidPiece::PAYMENT, 'Facture'], [AidPiece::APPLICATION, 'Devis'], [AidPiece::APPLICATION, 'Plan']] as [$moment, $name]) {

			foreach([$eAid, $eAidAutomatic] as $eAidPieces) {

				$eAidPiece = new AidPiece(['aid' => $eAidPieces]);

				$fw = new \FailWatch();

				$eAidPiece->build(AidPieceLib::getPropertiesCreate(), ['moment' => $moment, 'name' => $name]);

				$fw->validate();

				AidPieceLib::create($eAidPiece);

			}

		}

		$eStructure = $this->getStructure('05400', \structure\Structure::NO);

		$eAidSheet = AidLib::getSheet($eAid['id'], NULL);
		$this->assertEquals(['Devis', 'Plan', 'Facture'], array_values($eAidSheet['cAidPiece']->getColumn('name')), 'par moment, puis dans l\'ordre de saisie');

		new AidUi()->getSheet($eStructure, AidLib::getFilters($eStructure, []), $eAidSheet);

		$this->assertTrue(AidLib::getSheet($eAidAutomatic['id'], NULL)['cAidPiece']->empty(), 'versée sans dossier');

		$eAid['direct'] = FALSE;
		AidLib::update($eAid, ['direct']);

		$eAidSheet = AidLib::getSheet($eAid['id'], NULL);
		$this->assertTrue($eAidSheet['cAidPiece']->empty(), 'déposée par un tiers');

		new AidUi()->getSheet($eStructure, AidLib::getFilters($eStructure, []), $eAidSheet);

	}

	/**
	 * La provenance d'un chiffre reste à l'équipe : une parenthèse qui nomme un relevé, un agrégateur ou
	 * une base tierce tombe, sur le taux de la carte comme sur la fiche.
	 */
	public function testProvenance(): void {

		$this->setClock(self::NOW);

		$this->assertEquals('40 à 60 %', AidLib::cleanProvenance('40 à 60 % (fourchette relevée par l\'agrégateur, non affichée sur la page officielle)'));
		$this->assertEquals('50 % des dépenses éligibles', AidLib::cleanProvenance('50 % des dépenses éligibles (Fève)'));
		$this->assertEquals('<p>Incompatible avec certaines MAEC et PSE.</p>', AidLib::cleanProvenance('<p>Incompatible avec certaines MAEC et PSE (selon l\'agrégateur).</p>'));
		$this->assertEquals('Plafond 50 000 €', AidLib::cleanProvenance('Plafond 50 000 € (d\'après aides-territoires)'));
		$this->assertEquals('40 % (eau prélevée en rivière)', AidLib::cleanProvenance('40 % (eau prélevée en rivière)'), 'prélevé n\'est pas un relevé');
		$this->assertEquals('80 % (études)', AidLib::cleanProvenance('80 % (études)'), 'une parenthèse du texte officiel reste');
		$this->assertEquals(NULL, AidLib::cleanProvenance(NULL));

		$eAid = $this->createAid('taux relevé', editions: [[Edition::OPEN, '2026-12-31', '30 % (montant relevé sur Fève)']]);

		$this->assertEquals('30 %', AidLib::getSheet($eAid['id'], NULL)['rate'], 'la fiche');
		$this->assertEquals('30 %', AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE])[$eAid['id']]['rateShort'], 'la carte');

	}

	/**
	 * Les productions dans l'ordre du catalogue, sans « autre production » à côté de productions nommées,
	 * et ce que la carte tait au-delà de AidSetting::PRODUCTIONS_CARD, jamais une seule.
	 */
	public function testProductionsShown(): void {

		$this->setClock(self::NOW);

		$this->assertEquals(['bovin', 'caprin', 'ovin'], AidLib::getProductionsShown(['ovin', 'bovin', 'caprin']));
		$this->assertEquals(['bovin', 'caprin', 'ovin'], AidLib::getProductionsShown(['caprin', 'ovin', 'bovin']), 'un seul ordre, quel que soit celui de la saisie');
		$this->assertEquals(['vigne'], AidLib::getProductionsShown(['other', 'vigne']), 'autre production à côté d\'une production nommée');
		$this->assertEquals(['other'], AidLib::getProductionsShown(['other']), 'autre production seule reste');
		$this->assertEquals([], AidLib::getProductionsShown([]));

		$eAidMany = $this->createAid('cinq productions', level: Aid::NATIONAL, productions: ['volaille', 'other', 'maraichage', 'bovin', 'vigne', 'ovin']);
		$eAidFew = $this->createAid('trois productions', level: Aid::NATIONAL, productions: ['ovin', 'bovin', 'caprin']);
		$eAidFour = $this->createAid('quatre productions', level: Aid::NATIONAL, productions: ['ovin', 'bovin', 'caprin', 'vigne']);

		$cAid = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]);

		$this->assertEquals(['maraichage', 'vigne', 'bovin', 'ovin', 'volaille'], $cAid[$eAidMany['id']]['productionsShown']);
		$this->assertEquals(['maraichage', 'vigne', 'bovin'], $cAid[$eAidMany['id']]['productionsCard']);
		$this->assertEquals(2, $cAid[$eAidMany['id']]['productionsMore']);
		$this->assertEquals(0, $cAid[$eAidFew['id']]['productionsMore'], 'trois productions se nomment toutes');
		$this->assertEquals(['vigne', 'bovin', 'caprin', 'ovin'], $cAid[$eAidFour['id']]['productionsCard'], 'quatre aussi, « et 1 autre » ne raccourcirait rien');
		$this->assertEquals(0, $cAid[$eAidFour['id']]['productionsMore']);

	}

	/**
	 * Les compléments suivent le département de la liste quittée, comme la liste elle-même, mais ni ses
	 * productions ni le bio.
	 */
	public function testSheetComplementsByTerritory(): void {

		$this->setClock(self::NOW);

		$eAidAlps = $this->createAid('complément des Hautes-Alpes', departments: ['05']);
		$eAidVine = $this->createAid('complément national pour la vigne', level: Aid::NATIONAL, productions: ['vigne']);

		$eAid = $this->createAid('complétée', level: Aid::NATIONAL, complements: [$eAidAlps, $eAidVine]);

		$this->assertEquals([$eAidAlps['id'], $eAidVine['id']], array_values(AidLib::getSheet($eAid['id'], NULL)['cAidComplement']->getIds()), 'toute la France');
		$this->assertEquals([$eAidAlps['id'], $eAidVine['id']], array_values(AidLib::getSheet($eAid['id'], '05')['cAidComplement']->getIds()), 'le département du complément');
		$this->assertEquals([$eAidVine['id']], array_values(AidLib::getSheet($eAid['id'], '31')['cAidComplement']->getIds()), 'un autre département écarte le complément local, pas celui d\'une production');

	}

	/**
	 * La carte dit aussi qu'une aide est à faire confirmer, dans la liste comme sous « Se complète par » :
	 * l'agriculteur l'apprend avant d'ouvrir la fiche.
	 */
	public function testCardUnconfirmed(): void {

		$this->setClock(self::NOW);

		$eAidConflict = $this->createAid('contredite', sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]], verification: Aid::CONFLICT);
		$eAidToCheck = $this->createAid('à confirmer', sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]], verification: Aid::TO_CHECK);
		$eAidPrimary = $this->createAid('lue sur sa source', sources: [[Source::PRIMARY, Source::ALIVE], [Source::RELAY, Source::ALIVE]]);

		$cAid = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]);

		$this->assertTrue($cAid[$eAidConflict['id']]['isUnconfirmed'], 'sources contradictoires, dans la liste');
		$this->assertTrue($cAid[$eAidToCheck['id']]['isUnconfirmed'], 'aucune source n\'a pu confirmer, dans la liste');
		$this->assertFalse($cAid[$eAidPrimary['id']]['isUnconfirmed'], 'lue sur sa source officielle, dans la liste');

		$eAid = $this->createAid('complétée', complements: [$eAidConflict, $eAidPrimary]);
		$cAidComplement = AidLib::getSheet($eAid['id'], NULL)['cAidComplement'];

		$this->assertTrue($cAidComplement[$eAidConflict['id']]['isUnconfirmed'], 'sous « Se complète par »');
		$this->assertFalse($cAidComplement[$eAidPrimary['id']]['isUnconfirmed'], 'complément lu sur sa source officielle');

	}

	/**
	 * Les chiffres de la carte : ceux d'une subvention, sauf quand une édition ouverte publie son taux,
	 * qui l'emporte alors comme sur la fiche ; jamais ceux d'un prêt ni d'une exonération.
	 */
	public function testFigure(): void {

		$this->setClock(self::NOW);

		$eAidEdition = $this->createAid('plafond et édition', editions: [[Edition::OPEN, '2026-12-31', '30 % en 2026']], ratePercentMax: '40');
		$eAidPlain = $this->createAid('plafond seul', editions: [[Edition::OPEN, '2026-12-31']], ratePercentMax: '40');

		$eAidLoan = $this->createAid('prêt plafonné', editions: [[Edition::OPEN, '2026-12-31']], ratePercentMax: '40', type: Aid::LOAN);
		$eAidTax = $this->createAid('exonération plafonnée', editions: [[Edition::OPEN, '2026-12-31']], ratePercentMax: '100', type: Aid::TAX);

		$eAidSheet = AidLib::getSheet($this->createAid('fiche', complements: [$eAidEdition, $eAidPlain, $eAidLoan, $eAidTax])['id'], NULL);

		$eAidEditionCard = $eAidSheet['cAidComplement'][$eAidEdition['id']];
		$this->assertEquals('30 % en 2026', $eAidEditionCard['rate']);
		$this->assertEquals(NULL, $eAidEditionCard['ratePercentMax'], 'le plafond de l\'aide s\'efface devant le taux de l\'édition');
		$this->assertEquals('30 % en 2026', $eAidEditionCard['rateShort']);
		$this->assertEquals('30 % en 2026', AidLib::getSheet($eAidEdition['id'], NULL)['rate'], 'la fiche lit le même taux que la carte');

		$eAidPlainCard = $eAidSheet['cAidComplement'][$eAidPlain['id']];
		$this->assertEquals('50 %', $eAidPlainCard['rate']);
		$this->assertEquals(40.0, (float)$eAidPlainCard['ratePercentMax'], 'sans taux d\'édition, le plafond de l\'aide reste');

		foreach([$eAidLoan, $eAidTax] as $eAid) {

			$eAidCard = $eAidSheet['cAidComplement'][$eAid['id']];
			$this->assertEquals(NULL, $eAidCard['ratePercentMax'], $eAid['name'].' : seule une subvention montre son pourcentage');
			$this->assertEquals('50 %', $eAidCard['rateShort'], $eAid['name'].' : la carte dit la première clause du taux');

		}

	}

	/**
	 * Une subvention sans taux écrit dit son plafond sur la carte : la fiche, qui en est le détail, dit
	 * le même chiffre, tiré de la même préparation, puis le plancher et le plafond de la dépense.
	 */
	public function testFigureSheet(): void {

		$this->setClock(self::NOW);

		$eAid = $this->createAid('taux vide, plafonnée', ratePercentMax: '80', rate: NULL, floor: '5000', ceiling: '20000', boundBase: Aid::EXPENSE);

		$eAidCard = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE])[$eAid['id']];
		$eAidSheet = AidLib::getSheet($eAid['id'], NULL);

		$this->assertEquals(NULL, $eAidSheet['rate']);
		$this->assertEquals(80.0, (float)$eAidCard['ratePercentMax'], 'la carte');

		foreach(['ratePercentMax', 'amountMax', 'amountUnit', 'rateShort'] as $property) {
			$this->assertEquals($eAidCard[$property], $eAidSheet[$property], 'la fiche lit '.$property.' comme la carte');
		}

		$this->assertEquals(5000.0, (float)$eAidSheet['floor'], 'le plancher');
		$this->assertEquals(20000.0, (float)$eAidSheet['ceiling'], 'le plafond');

	}

	/**
	 * Une subvention sans taux chiffré dit sur sa carte le plafond de l'aide, jamais celui de la dépense,
	 * qui borne ce que la structure engage et non ce qu'elle reçoit. Un montant maximal et une proposition
	 * chiffrée du taux passent avant lui, et le plafond d'une édition ouverte l'emporte sur celui de l'aide.
	 */
	public function testFigureCeiling(): void {

		$this->setClock(self::NOW);

		$eAidPaid = $this->createAid('plafond de l\'aide', rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);
		$eAidExpense = $this->createAid('plafond de la dépense', rate: 'forfait', ceiling: '30000', boundBase: Aid::EXPENSE);
		$eAidAmount = $this->createAid('montant maximal et plafond', rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);
		$eAidEdition = $this->createAid('plafond d\'édition', editions: [[Edition::OPEN, '2026-12-31', NULL, '12000']], rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);
		$eAidLoan = $this->createAid('prêt plafonné sur l\'aide', type: Aid::LOAN, rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);
		$eAidEditionRate = $this->createAid('édition à taux', editions: [[Edition::OPEN, '2026-12-31', '40 % en 2026', '12000']], rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);
		$eAidFlat = $this->createAid('forfait chiffré', rate: '5 000 € par projet', ceiling: '30000', boundBase: Aid::AID);
		$eAidPercent = $this->createAid('taux maximal et plafond', ratePercentMax: '40', rate: 'forfait', ceiling: '30000', boundBase: Aid::AID);

		$fw = new \FailWatch();
		$eAidAmount->build(['amountMax'], ['amountMax' => '5000']);
		$fw->validate();
		AidLib::update($eAidAmount, ['amountMax']);

		$cAid = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]);

		$this->assertEquals(30000.0, (float)$cAid[$eAidPaid['id']]['ceilingAid'], 'le plafond de l\'aide est ce que la structure reçoit');
		$this->assertEquals(NULL, $cAid[$eAidExpense['id']]['ceilingAid'], 'le plafond de la dépense ne se lit pas comme un montant reçu');
		$this->assertEquals(NULL, $cAid[$eAidAmount['id']]['ceilingAid'], 'le montant maximal passe avant le plafond');
		$this->assertEquals(5000.0, (float)$cAid[$eAidAmount['id']]['amountMax']);
		$this->assertEquals(12000.0, (float)$cAid[$eAidEdition['id']]['ceilingAid'], 'le plafond de l\'édition ouverte l\'emporte');
		$this->assertEquals(NULL, $cAid[$eAidLoan['id']]['ceilingAid'], 'hors subvention, pas de plafond sur la carte');
		$this->assertEquals(NULL, $cAid[$eAidEditionRate['id']]['ceilingAid'], 'le taux d\'une édition ouverte l\'emporte aussi sur le plafond');
		$this->assertEquals(NULL, $cAid[$eAidFlat['id']]['ceilingAid'], 'la proposition chiffrée du taux passe avant le plafond');
		$this->assertEquals('5 000 € par projet', $cAid[$eAidFlat['id']]['rateShort']);
		$this->assertEquals(NULL, $cAid[$eAidPaid['id']]['rateShort'], 'un taux sans chiffre laisse la place au plafond');
		$this->assertEquals(NULL, $cAid[$eAidPercent['id']]['ceilingAid'], 'le taux maximal passe avant le plafond');

	}

	/**
	 * Des bornes annuelles arrivent avec leur période sur la carte et sur la fiche, qui la disent à côté
	 * du montant. Un montant par animal n'est pas une borne : il passe par le montant maximal et son
	 * unité, et le plafond ne paraît plus sur la carte. Ce que la carte et la fiche écrivent de ces
	 * données se juge dans Chrome, le test juge ce que la préparation leur donne.
	 */
	public function testBoundPeriod(): void {

		$this->setClock(self::NOW);

		$eAidYear = $this->createAid('plafond annuel', rate: 'forfait', floor: '1000', ceiling: '4000', boundBase: Aid::AID, boundPeriod: Aid::YEAR);
		$eAidWhole = $this->createAid('plafond sur toute l\'aide', rate: 'forfait', ceiling: '4000', boundBase: Aid::AID);
		$eAidHead = $this->createAid('montant par animal', rate: 'Par animal : débourrage 270 €, conservation 540 €');

		$fw = new \FailWatch();
		$eAidHead->build(['amountMax', 'amountUnit'], ['amountMax' => '540', 'amountUnit' => Aid::PER_HEAD]);
		$fw->validate();
		AidLib::update($eAidHead, ['amountMax', 'amountUnit']);

		$cAid = AidLib::getByFilters(['department' => NULL, 'productions' => [], 'organic' => TRUE]);

		$this->assertEquals(4000.0, (float)$cAid[$eAidYear['id']]['ceilingAid']);
		$this->assertEquals(Aid::YEAR, $cAid[$eAidYear['id']]['boundPeriod'], 'la carte reçoit la période du plafond');
		$this->assertEquals(NULL, $cAid[$eAidWhole['id']]['boundPeriod'], 'un plafond sur toute l\'aide n\'a pas de période');

		$this->assertEquals(540.0, (float)$cAid[$eAidHead['id']]['amountMax'], 'le montant par animal');
		$this->assertEquals(Aid::PER_HEAD, $cAid[$eAidHead['id']]['amountUnit']);
		$this->assertEquals(NULL, $cAid[$eAidHead['id']]['ceilingAid'], 'sans plafond, rien d\'autre ne se dit');

		$eAidSheet = AidLib::getSheet($eAidYear['id'], NULL);
		$this->assertEquals(Aid::YEAR, $eAidSheet['boundPeriod'], 'la fiche reçoit la période des bornes');
		$this->assertEquals(1000.0, (float)$eAidSheet['floor']);
		$this->assertEquals(4000.0, (float)$eAidSheet['ceiling']);

		$fw = new \FailWatch();
		new Aid()->build(['boundPeriod'], ['boundPeriod' => 'month']);
		$this->assertTrue($fw->has('Aid::boundPeriod.check'), 'le mois n\'est pas une période connue');

	}

	/**
	 * Les bornes d'une subvention paraissent sur la fiche avec leur assiette, à côté d'un taux écrit qui
	 * ne les reprend pas toujours, et pas du tout sans assiette ; sur une aide qui n'est pas une
	 * subvention, `fill()` retire les chiffres de l'aide. Le plafond d'une édition ouverte l'emporte sur
	 * celui de l'aide.
	 */
	public function testBoundsSheet(): void {

		$this->setClock(self::NOW);

		$eAidWritten = $this->createAid('taux écrit, plafonnée', rate: '20 % ; +5 % bio', floor: '1000', ceiling: '10000', boundBase: Aid::EXPENSE);
		$eAidSheet = AidLib::getSheet($eAidWritten['id'], NULL);
		$this->assertEquals(1000.0, (float)$eAidSheet['floor'], 'taux écrit : le plancher reste');
		$this->assertEquals(10000.0, (float)$eAidSheet['ceiling'], 'taux écrit : le plafond reste');

		$eAidNoBase = $this->createAid('bornes sans assiette', rate: '20 %', floor: '5000', ceiling: '8000');
		$eAidSheet = AidLib::getSheet($eAidNoBase['id'], NULL);
		$this->assertEquals(NULL, $eAidSheet['floor'], 'sans assiette : pas de plancher');
		$this->assertEquals(NULL, $eAidSheet['ceiling'], 'sans assiette : pas de plafond');

		$eAidTax = $this->createAid('exonération plafonnée', type: Aid::TAX, ratePercentMax: '100', rate: NULL, ceiling: '4500');
		$eAidSheet = AidLib::getSheet($eAidTax['id'], NULL);
		$this->assertEquals(NULL, $eAidSheet['ratePercentMax'], 'fill() retire le taux maximal hors subvention');
		$this->assertEquals(NULL, $eAidSheet['ceiling'], 'le plafond masqué hors subvention ne revient pas par la fiche');

		$eAidEdition = $this->createAid('plafond d\'édition', editions: [[Edition::OPEN, '2026-12-31', NULL, '15000']], ratePercentMax: '80', rate: NULL, floor: '5000', ceiling: '20000', boundBase: Aid::EXPENSE);
		$eAidSheet = AidLib::getSheet($eAidEdition['id'], NULL);
		$this->assertEquals(5000.0, (float)$eAidSheet['floor'], 'le plancher de l\'aide');
		$this->assertEquals(15000.0, (float)$eAidSheet['ceiling'], 'le plafond de l\'édition l\'emporte');

		$eAidSuspended = $this->createAid('plafond d\'édition suspendue', editions: [[Edition::OPEN, '2026-12-31'], [Edition::SUSPENDED, '2026-11-30', NULL, '15000']], ratePercentMax: '80', rate: NULL, ceiling: '20000', boundBase: Aid::EXPENSE);
		$this->assertEquals(20000.0, (float)AidLib::getSheet($eAidSuspended['id'], NULL)['ceiling'], 'une édition qui n\'est pas ouverte ne change pas le plafond');

	}

	public function testSuggestions(): void {

		$this->setClock(self::NOW);

		$eAid = $this->createAid('serre bioclimatique');
		$draft = $this->createAid('serre en brouillon', isPublished: FALSE);

		$filters = ['department' => NULL, 'productions' => [], 'organic' => TRUE, 'project' => NULL, 'tag' => new Tag()];

		$suggestions = AidLib::getSuggestions($filters, 'serre bioclim');

		$this->assertTrue($suggestions['cAid']->offsetExists($eAid['id']));

		$suggestions = AidLib::getSuggestions($filters, 'serre en brouillon');

		$this->assertFalse($suggestions['cAid']->offsetExists($draft['id']), 'la recherche ne propose que des aides affichées');
		$this->assertTrue(AidLib::getSuggestions($filters, 's')['cAid']->empty(), 'une lettre ne propose aucune aide');
		$this->assertTrue(AidLib::getSuggestions($filters, '!!')['cAid']->empty(), 'une saisie sans lettre ni chiffre ne propose aucune aide');

		// Un nom d'aide qui ne dit ni son étiquette ni son organisme : seule la comparaison à ceux-ci la trouve
		$eAidTagged = $this->createAid('dispositif '.uniqid(), tags: [$this->eTagInstall]);

		$this->assertTrue(AidLib::getSuggestions($filters, $this->eTagInstall['name'])['cAid']->offsetExists($eAidTagged['id']), 'la saisie trouve une aide par son étiquette');
		$this->assertTrue(AidLib::getSuggestions($filters, $this->eOrganization['name'])['cAid']->offsetExists($eAidTagged['id']), 'la saisie trouve une aide par son organisme');

		$this->assertFalse(array_key_exists('departments', AidLib::getSuggestions($filters, 'Hautes-Alpes')), 'le département se choisit à côté du titre, plus dans la recherche');

	}

	// « Un autre département » : par le début du nom ou du code, sans tenir compte des accents ni de la casse.
	public function testDepartments(): void {

		$this->assertEquals(['05'], AidLib::getDepartments('hautes-alp'));
		$this->assertEquals(['05'], AidLib::getDepartments('05'));
		$this->assertEquals(['2A', '2B', '21', '22', '23', '24', '25', '26', '27', '28', '29'], AidLib::getDepartments('2'), 'la Corse parmi les codes qui commencent par 2, dans l\'ordre de AidSetting::DEPARTMENTS');
		$this->assertEquals(['974'], AidLib::getDepartments('La Réu'));
		$this->assertEquals(['974'], AidLib::getDepartments('974'));
		$this->assertEquals(['2A'], AidLib::getDepartments('2a'));
		$this->assertEquals(count(AidSetting::DEPARTMENTS), count(AidLib::getDepartments('')));
		$this->assertEquals([], AidLib::getDepartments('zzz'));
		$this->assertEquals(['67', '68'], AidLib::getDepartments('rhin'), 'au début de chaque mot du nom');
		$this->assertEquals(['2A', '2B'], AidLib::getDepartments('corse'));
		$this->assertEquals(['04', '05', '06'], AidLib::getDepartments('alpes'));
		$this->assertEquals([], AidLib::getDepartments('hin'), 'pas au milieu d\'un mot');

	}

	/**
	 * Le lieu d'une carte : le bassin d'une agence de l'eau quand ses départements sont exactement les
	 * siens, sinon les régions entières puis les départements.
	 */
	public function testPlaces(): void {

		$loireBretagne = AidSetting::BASINS['04'][1];

		$this->assertEquals(['basins' => ['04'], 'regions' => [], 'departments' => []], AidLib::getPlaces($loireBretagne, TRUE));
		$this->assertEquals([], AidLib::getPlaces($loireBretagne, FALSE)['basins'], 'seule une agence de l\'eau se lit par son bassin');
		$this->assertEquals([], AidLib::getPlaces([...$loireBretagne, '05'], TRUE)['basins'], 'un département de plus, et le bassin ne se nomme plus');
		$this->assertEquals(['06', '12'], AidLib::getPlaces(AidSetting::getDepartmentsByBasins(['06', '12']), TRUE)['basins'], 'Rhône Méditerranée Corse, ses deux bassins');
		$this->assertEquals(['basins' => [], 'regions' => ['01'], 'departments' => []], AidLib::getPlaces(['971'], TRUE), 'un bassin d\'un seul département se nomme par sa région');
		$this->assertEquals(['basins' => [], 'regions' => ['32'], 'departments' => []], AidLib::getPlaces(AidSetting::BASINS['01'][1], FALSE), 'Artois-Picardie, qui recouvre les Hauts-de-France, se lit en région hors d\'une agence');
		$this->assertEquals(['basins' => [], 'regions' => [], 'departments' => ['05', '2A']], AidLib::getPlaces(['2A', '05', '05'], FALSE));

	}

	// La ligne grise d'une carte nomme l'institution sans le territoire que la ligne de lieu dit déjà.
	public function testInstitution(): void {

		$this->assertEquals('Région', AidUi::getInstitution('Région Bretagne'));
		$this->assertEquals('Département', AidUi::getInstitution('Département de la Meuse'));
		$this->assertEquals('Agence de l\'eau', AidUi::getInstitution('Agence de l\'eau Loire-Bretagne'));
		$this->assertEquals('Collectivité territoriale', AidUi::getInstitution('Collectivité territoriale de Martinique (CTM)'));
		$this->assertEquals('Régions (autorités de gestion régionales du FEADER)', AidUi::getInstitution('Régions (autorités de gestion régionales du FEADER)'), 'le préfixe se compare au mot entier');
		$this->assertEquals('Département-Région de Mayotte', AidUi::getInstitution('Département-Région de Mayotte'));
		$this->assertEquals('FranceAgriMer', AidUi::getInstitution('FranceAgriMer'));

	}

	/**
	 * La carte ne nomme un public que pour une aide qui lui est réservée : ouverte aux exploitations,
	 * elle ne dit rien des jeunes agriculteurs ni des propriétaires fonciers qu'elle cite en plus.
	 */
	public function testScopeReserved(): void {

		$scope = fn(array $beneficiaries): string => new \ReflectionMethod(AidUi::class, 'getScope')->invoke(new AidUi(), new Aid([
			'organization' => new Organization(['name' => 'Région Grand Est']),
			'beneficiaries' => new \Set($beneficiaries),
			'direct' => TRUE,
			'productionsCard' => [],
			'productionsMore' => 0,
			'labels' => NULL,
		]), '');

		$this->assertEquals('Région Grand Est', $scope([Aid::FARM, Aid::LANDOWNER]), 'ouverte aux exploitations et aux propriétaires fonciers');
		$this->assertEquals('Région Grand Est', $scope([Aid::FARM, Aid::YOUNG_FARMER]), 'ouverte aux exploitations et aux jeunes agriculteurs');
		$this->assertEquals('Région Grand Est · pour les propriétaires fonciers', $scope([Aid::LANDOWNER]), 'réservée aux propriétaires fonciers');
		$this->assertEquals('Région Grand Est · pour les jeunes agriculteurs', $scope([Aid::YOUNG_FARMER, Aid::NEW_FARMER]), 'réservée aux jeunes et nouveaux installés');

	}

	/**
	 * Une aide annuelle revient chaque année : entre deux campagnes, elle reste affichée, après les
	 * aides ouvertes et sans édition où déposer, quand un appel clos disparaît.
	 */
	public function testBetweenCampaigns(): void {

		$this->setClock(self::NOW);

		$open = $this->createAid('ouverte', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::OPEN, '2026-12-31']]);
		$permanent = $this->createAid('permanente', level: Aid::NATIONAL);
		$waiting = $this->createAid('entre deux campagnes', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-05-15']]);
		$unknown = $this->createAid('jamais datée', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN);
		$call = $this->createAid('appel clos', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::CLOSED, '2026-05-15']]);

		$filters = ['department' => NULL, 'productions' => [], 'organic' => TRUE];

		$this->assertAids([$open, $permanent, $waiting, $unknown], $filters, 'l\'aide annuelle reste, l\'appel clos part');

		$ids = $this->getDecor(AidLib::getByFilters($filters))->getIds();
		$this->assertEquals([$open['id'], $permanent['id']], array_slice($ids, 0, 2), 'les aides ouvertes d\'abord');

		$eAidSheet = AidLib::getSheet($waiting['id'], NULL);
		$this->assertTrue($eAidSheet->notEmpty(), 'la fiche s\'ouvre');
		$this->assertTrue($eAidSheet['isBetweenCampaigns'], 'entre deux campagnes');
		$this->assertTrue($eAidSheet['eEditionDeposit']->empty(), 'aucune édition où déposer');
		$this->assertEquals(NULL, $eAidSheet['endDate'], 'aucune clôture annoncée');
		$this->assertTrue(AidLib::getSheet($call['id'], NULL)->empty(), 'l\'appel clos ne s\'ouvre pas');

		$deadline = fn(Aid $eAid): string => new \ReflectionMethod(AidUi::class, 'getDeadline')->invoke(new AidUi(), $eAid);

		$this->assertEquals('Dernière campagne du 01/01/2026 au 15/05/2026', $deadline($eAidSheet), 'la dernière campagne');
		$this->assertEquals('Campagne annuelle, dates non publiées', $deadline(AidLib::getSheet($unknown['id'], NULL)), 'aucune édition');

	}

	/**
	 * Une édition dont le dépôt n'ouvre que plus tard laisse l'aide affichée, la structure préparant son
	 * dossier avant l'ouverture, mais ne compte pas comme ouverte : la carte la date par son ouverture
	 * (`isUpcoming`), le tri la range après les aides où déposer aujourd'hui, et parmi les aides à venir par
	 * leur ouverture, et une édition ouverte de la même aide passe devant elle. Une aide annuelle dont la
	 * campagne est annoncée n'est pas entre deux campagnes. Avant l'ouverture, l'adresse de dépôt se donne
	 * sans lien ; le jour de l'ouverture, l'aide est ouverte et le lien revient.
	 */
	public function testUpcomingEdition(): void {

		$this->setClock(self::NOW);

		$open = $this->createAid('ouverte', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::OPEN, '2027-03-31']]);
		$openNoEnd = $this->createAid('ouverte sans fin', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::OPEN, NULL]]);
		$upcoming = $this->createAid('à venir', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::OPEN, '2027-01-15', '30 %', NULL, '2026-12-15']]);
		$upcomingNoEnd = $this->createAid('à venir sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::OPEN, NULL, NULL, NULL, '2027-01-01']]);
		$upcomingFirst = $this->createAid('à venir tôt', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::OPEN, '2027-06-30', NULL, NULL, '2026-11-15']]);
		$both = $this->createAid('ouverte et à venir', level: Aid::NATIONAL, calendar: Aid::CALL, editions: [[Edition::OPEN, '2027-06-30', NULL, NULL, '2027-05-01'], [Edition::OPEN, '2027-02-28', '20 %', NULL, '2026-09-01']]);
		$waiting = $this->createAid('entre deux campagnes', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-05-15']]);

		$filters = ['department' => NULL, 'productions' => [], 'organic' => TRUE];

		$this->assertAids([$open, $openNoEnd, $upcoming, $upcomingNoEnd, $upcomingFirst, $both, $waiting], $filters, 'une édition à venir laisse l\'aide affichée');

		$cAid = $this->getDecor(AidLib::getByFilters($filters));

		$this->assertEquals(
			[$both['id'], $open['id'], $openNoEnd['id'], $upcomingFirst['id'], $upcoming['id'], $upcomingNoEnd['id'], $waiting['id']],
			$cAid->getIds(),
			'les aides où déposer aujourd\'hui d\'abord, par leur clôture, puis celles à venir, par leur ouverture, puis celles entre deux campagnes'
		);

		$this->assertFalse($cAid[$open['id']]['isUpcoming'], 'ouverte');
		$this->assertTrue($cAid[$upcoming['id']]['isUpcoming'], 'à venir');
		$this->assertEquals('2026-12-15', $cAid[$upcoming['id']]['eEditionDeposit']['startDate'], 'à venir : son ouverture');
		$this->assertEquals('2027-01-15', $cAid[$upcoming['id']]['endDate'], 'à venir : sa clôture');
		$this->assertEquals('2026-12-15', $cAid[$upcoming['id']]['nextDate'], 'à venir : la prochaine date est son ouverture');
		$this->assertEquals('2027-01-01', $cAid[$upcomingNoEnd['id']]['nextDate'], 'à venir sans fin : son ouverture');
		$this->assertEquals('2027-03-31', $cAid[$open['id']]['nextDate'], 'ouverte : la prochaine date est sa clôture');
		$this->assertEquals(NULL, $cAid[$openNoEnd['id']]['nextDate'], 'ouverte sans fin : aucune date');
		$this->assertEquals('30 %', $cAid[$upcoming['id']]['rate'], 'à venir : son taux l\'emporte');
		$this->assertTrue($cAid[$upcomingNoEnd['id']]['isUpcoming'], 'à venir sans fin');
		$this->assertFalse($cAid[$upcomingNoEnd['id']]['isBetweenCampaigns'], 'campagne annoncée : pas entre deux campagnes');
		$this->assertFalse($cAid[$both['id']]['isUpcoming'], 'l\'ouverte passe devant l\'à venir');
		$this->assertEquals('2027-02-28', $cAid[$both['id']]['endDate'], 'la clôture de l\'ouverte');
		$this->assertEquals('20 %', $cAid[$both['id']]['rate'], 'le taux de l\'ouverte');

		// Le décor pose l'adresse de dépôt sans build(), qui n'en vérifierait que la forme d'URL : le test
		// veut seulement une adresse présente.
		$upcoming['depositUrl'] = 'https://www.example.org/depot';
		AidLib::update($upcoming, ['depositUrl']);

		$isDepositOffered = fn(Aid $eAid): bool => new \ReflectionMethod(AidUi::class, 'isDepositOffered')->invoke(new AidUi(), $eAid);

		$eAidSheet = AidLib::getSheet($upcoming['id'], NULL);
		$this->assertTrue($eAidSheet->notEmpty(), 'la fiche s\'ouvre');
		$this->assertTrue($eAidSheet['isUpcoming'], 'la fiche la dit à venir');
		$this->assertFalse($isDepositOffered($eAidSheet), 'avant l\'ouverture, le dépôt en ligne ne s\'offre pas');

		// Un texte-contrat : avant l'ouverture, l'adresse se donne sans rien qui y mène, le domaine à l'écran
		// et l'adresse entière sur le papier.
		$deposit = new \ReflectionMethod(AidUi::class, 'getDeposit')->invoke(new AidUi(), $eAidSheet);
		$this->assertTrue(str_contains($deposit, 'Dépôt en ligne à partir du 15 décembre 2026, sur example.org'), 'avant l\'ouverture, le domaine du dépôt à l\'écran');
		$this->assertTrue(str_contains($deposit, 'Dépôt en ligne à partir du 15 décembre 2026, sur https://www.example.org/depot'), 'avant l\'ouverture, l\'adresse entière sur le papier');
		$this->assertFalse(str_contains($deposit, 'href='), 'avant l\'ouverture, aucun lien vers le dépôt');

		$this->setClock('2026-12-15 08:00:00');

		$eAidSheet = AidLib::getSheet($upcoming['id'], NULL);
		$this->assertFalse($eAidSheet['isUpcoming'], 'le jour de l\'ouverture, elle est ouverte');
		$this->assertTrue($isDepositOffered($eAidSheet), 'le jour de l\'ouverture, le dépôt en ligne s\'offre');
		$this->assertTrue(str_contains(new \ReflectionMethod(AidUi::class, 'getDeposit')->invoke(new AidUi(), $eAidSheet), 'href="https://www.example.org/depot"'), 'le jour de l\'ouverture, le lien vers le dépôt');
		$this->assertEquals('2027-01-15', $eAidSheet['endDate'], 'ouverte : sa clôture');
		$this->assertEquals('2027-01-15', $eAidSheet['nextDate'], 'ouverte : la prochaine date redevient sa clôture');

	}

	/**
	 * La dernière campagne est une campagne passée, finie au plus tard aujourd'hui ou, sans date de fin,
	 * close ou épuisée. Une édition à fin future n'en est pas une, quel que soit son état, ni une édition
	 * suspendue sans date de fin, qui peut reprendre ; une édition ouverte sans date de fin garde l'aide
	 * ouverte. Sans date de fin, une campagne se date et se classe par son ouverture, et deux campagnes
	 * à la même date se départagent par la dernière saisie.
	 */
	public function testLastCampaign(): void {

		$this->setClock(self::NOW);

		$suspended = $this->createAid('suspendue', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-05-15'], [Edition::SUSPENDED, '2026-12-31']]);
		$openNoEnd = $this->createAid('ouverte sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::OPEN, NULL]]);
		$closedNoEnd = $this->createAid('close sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, NULL]]);

		$deadline = fn(Aid $eAid): string => new \ReflectionMethod(AidUi::class, 'getDeadline')->invoke(new AidUi(), $eAid);

		$this->assertEquals('Dernière campagne du 01/01/2026 au 15/05/2026', $deadline(AidLib::getSheet($suspended['id'], NULL)), 'une édition suspendue à fin future n\'est pas la dernière campagne');

		$eAidSheet = AidLib::getSheet($openNoEnd['id'], NULL);
		$this->assertFalse($eAidSheet['isBetweenCampaigns'], 'ouverte sans fin : pas entre deux campagnes');
		$this->assertTrue($eAidSheet['eEditionDeposit']->notEmpty(), 'ouverte sans fin : le dépôt reste proposé');
		$this->assertEquals('Chaque année', $deadline($eAidSheet), 'ouverte sans fin : aucune dernière campagne');

		$this->assertEquals('Dernière campagne ouverte le 01/01/2026', $deadline(AidLib::getSheet($closedNoEnd['id'], NULL)), 'close sans fin');

		// Une édition récente sans date de fin passe devant une édition plus ancienne, datée.
		$recentNoEnd = $this->createAid('récente sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-03-15'], [Edition::CLOSED, NULL, NULL, NULL, '2026-04-01']]);
		$this->assertEquals('Dernière campagne ouverte le 01/04/2026', $deadline(AidLib::getSheet($recentNoEnd['id'], NULL)), 'la plus récente, datée ou non');

		// Épuisée sans date de fin, la campagne est passée ; suspendue sans date de fin, elle peut reprendre.
		$exhaustedNoEnd = $this->createAid('épuisée sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-03-15'], [Edition::EXHAUSTED, NULL, NULL, NULL, '2026-04-01']]);
		$this->assertEquals('Dernière campagne ouverte le 01/04/2026', $deadline(AidLib::getSheet($exhaustedNoEnd['id'], NULL)), 'épuisée sans fin');

		$suspendedNoEnd = $this->createAid('suspendue sans fin', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-05-15'], [Edition::SUSPENDED, NULL, NULL, NULL, '2026-06-01']]);
		$this->assertEquals('Dernière campagne du 01/01/2026 au 15/05/2026', $deadline(AidLib::getSheet($suspendedNoEnd['id'], NULL)), 'suspendue sans fin');

		$closedToday = $this->createAid('close aujourd\'hui', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-10-22']]);
		$this->assertEquals('Dernière campagne du 01/01/2026 au 22/10/2026', $deadline(AidLib::getSheet($closedToday['id'], NULL)), 'close aujourd\'hui');

		// Les deux éditions se classent au 15/05/2026 : la dernière saisie l'emporte.
		$sameDate = $this->createAid('même date', level: Aid::NATIONAL, calendar: Aid::CAMPAIGN, editions: [[Edition::CLOSED, '2026-05-15'], [Edition::CLOSED, NULL, NULL, NULL, '2026-05-15']]);
		$this->assertEquals('Dernière campagne ouverte le 15/05/2026', $deadline(AidLib::getSheet($sameDate['id'], NULL)), 'même date');

	}

	/**
	 * Une aide sans démarche s'obtient sans rien déposer : elle s'affiche, sur la liste comme en fiche,
	 * et sa ligne de dépôt dit qu'il n'y a rien à faire, jamais un rythme de dépôt.
	 */
	public function testAutomatic(): void {

		$this->setClock(self::NOW);

		$automatic = $this->createAid('sans démarche', level: Aid::NATIONAL, calendar: Aid::AUTOMATIC, type: Aid::TAX);

		$this->assertAids([$automatic], ['department' => NULL, 'productions' => [], 'organic' => TRUE], 'listée');

		$eAidSheet = AidLib::getSheet($automatic['id'], NULL);
		$this->assertTrue($eAidSheet->notEmpty(), 'la fiche s\'ouvre');

		$deadline = fn(Aid $eAid): string => new \ReflectionMethod(AidUi::class, 'getDeadline')->invoke(new AidUi(), $eAid);
		$this->assertEquals('Sans démarche', $deadline($eAidSheet), 'aucun rythme de dépôt');

	}

	/**
	 * Hors bio, une aide ne disparaît que si le bio est son seul label : `labels` se lit « l'un ou
	 * l'autre », et la structure peut avoir les autres.
	 */
	public function testByFiltersLabels(): void {

		$this->setClock(self::NOW);

		$organic = $this->createAid('bio', level: Aid::NATIONAL, labels: [Aid::AB]);
		$organicOrHve = $this->createAid('bio ou HVE', level: Aid::NATIONAL, labels: [Aid::AB, Aid::HVE]);
		$hve = $this->createAid('HVE', level: Aid::NATIONAL, labels: [Aid::HVE]);

		// Le catalogue de dev porte déjà le tag de conversion, que son fqn rend unique.
		$eTagConversion = TagLib::getByFqn(Tag::CONVERSION);

		if($eTagConversion->empty()) {
			$eTagConversion = $this->createTag(Tag::CONVERSION, [Tag::ORGANIC]);
		}

		$conversion = $this->createAid('conversion', level: Aid::NATIONAL, labels: [Aid::AB], tags: [$eTagConversion]);
		$organicInstall = $this->createAid('bio, installation', level: Aid::NATIONAL, labels: [Aid::AB], tags: [$this->eTagInstall]);

		$filters = ['department' => NULL, 'productions' => []];

		$this->assertAids([$organic, $organicOrHve, $hve, $conversion, $organicInstall], ['organic' => TRUE] + $filters, 'en bio');
		$this->assertAids([$organicOrHve, $hve, $conversion], ['organic' => FALSE] + $filters, 'hors bio, l\'aide qui admet un autre label reste, et celle de conversion aussi');

		// Sans tag de conversion, le filtre ne garde plus que ce que les labels laissent.
		TagLib::delete($eTagConversion);

		$this->assertAids([$organicOrHve, $hve], ['organic' => FALSE] + $filters, 'sans tag de conversion, l\'aide au bio seul part');

	}

	/**
	 * La fiche lit les labels comme la carte, « l'un ou l'autre ».
	 */
	public function testLabelsAlternative(): void {

		$labels = fn(array $labels): string => new \ReflectionMethod(AidUi::class, 'getLabels')->invoke(NULL, new \Set($labels));

		$this->assertEquals('Agriculture biologique', $labels([Aid::AB]), 'un label');
		$this->assertEquals('Agriculture biologique ou Haute valeur environnementale', $labels([Aid::AB, Aid::HVE]), 'deux labels');

	}

	/**
	 * La carte nomme jusqu'à trois labels, puis les résume ; HVE, certification environnementale,
	 * ne se range pas sous les signes de qualité.
	 */
	public function testScopeLabels(): void {

		$scope = fn(array $labels): string => new \ReflectionMethod(AidUi::class, 'getScope')->invoke(new AidUi(), new Aid([
			'organization' => new Organization(['name' => 'Région Grand Est']),
			'beneficiaries' => new \Set([Aid::FARM]),
			'direct' => TRUE,
			'productionsCard' => [],
			'productionsMore' => 0,
			'labels' => new \Set($labels),
		]), '');

		$this->assertEquals('Région Grand Est · bio uniquement', $scope([Aid::AB]), 'AB seul');
		$this->assertEquals('Région Grand Est · en Agriculture biologique ou IGP', $scope([Aid::AB, Aid::IGP]), 'deux labels');
		$this->assertEquals('Région Grand Est · en Agriculture biologique, AOP ou IGP', $scope([Aid::AB, Aid::AOP, Aid::IGP]), 'trois labels');
		$this->assertEquals('Région Grand Est · en Agriculture biologique, AOP ou Haute valeur environnementale', $scope([Aid::AB, Aid::AOP, Aid::HVE]), 'trois labels dont HVE');
		$this->assertEquals('Région Grand Est · en AOP', $scope([Aid::AOP]), 'un label seul autre que AB');
		$this->assertEquals('Région Grand Est · sous signe de qualité', $scope([Aid::AB, Aid::AOP, Aid::IGP, Aid::LABEL_ROUGE]), 'plus de trois');
		$this->assertEquals('Région Grand Est · sous signe de qualité ou HVE', $scope([Aid::AB, Aid::AOP, Aid::IGP, Aid::HVE]), 'plus de trois avec HVE');

	}

	/**
	 * Une note de relevé à la place du taux (« non publié ») n'est pas un taux : elle passe en tête des
	 * notes telle qu'écrite, et l'aide reste sans taux, donc en brouillon.
	 */
	public function testRateUnknown(): void {

		$this->assertEquals(['rate' => NULL, 'notes' => 'Taux : Non relevé (règlement PDF)'], ImportLib::getRate('Non relevé (règlement PDF)', NULL));
		$this->assertEquals(['rate' => NULL, 'notes' => "Taux : Taux non lu sur la fiche\n\nBonifié en zone de montagne"], ImportLib::getRate('Taux non lu sur la fiche', 'Bonifié en zone de montagne'));
		$this->assertEquals(['rate' => NULL, 'notes' => 'Taux : non publié'], ImportLib::getRate('non publié', NULL));
		$this->assertEquals(['rate' => '40 % des dépenses', 'notes' => 'Bonifié'], ImportLib::getRate('40 % des dépenses', 'Bonifié'));
		$this->assertEquals(['rate' => 'Nonante pour cent', 'notes' => NULL], ImportLib::getRate('Nonante pour cent', NULL), 'le mot entier, pas son début');
		$this->assertEquals(['rate' => NULL, 'notes' => NULL], ImportLib::getRate(NULL, NULL));

	}

	/**
	 * Une aide publiée dont la fiche ne porte plus qu'une note de relevé à la place du taux garde son taux
	 * et reste publiée : la perte devient son écart, que l'équipe tranche (cadrage de L5, R3). Le décor
	 * de CatalogAi, copié pour remplacer le taux d'une fiche.
	 */
	public function testReseedWithoutRate(): void {

		$this->setClock(self::NOW);

		$id = 'national-masa-aita-installation';

		ImportLib::seed(__DIR__.'/fixture');

		$this->assertEquals(Aid::PUBLISHED, AidLib::getByAlias($id)['status']);

		$path = sys_get_temp_dir().'/aid-reseed-'.uniqid();
		mkdir($path);

		foreach(glob(__DIR__.'/fixture/*') as $file) {
			copy($file, $path.'/'.basename($file));
		}

		$lines = [];

		foreach(file($path.'/fixture.aides.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

			$aid = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);

			if($aid['id'] === $id) {
				$aid['taux'] = 'Non relevé (règlement PDF)';
			}

			$lines[] = json_encode($aid, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

		}

		file_put_contents($path.'/fixture.aides.jsonl', implode("\n", $lines)."\n");

		try {
			ImportLib::seed($path);
		} finally {
			array_map(unlink(...), glob($path.'/*'));
			rmdir($path);
		}

		$eAid = AidLib::getByAlias($id);

		$this->assertEquals('Variable selon le volet et les priorités régionales', $eAid['rate'], 'le taux de l\'aide reste');
		$this->assertEquals(Aid::PUBLISHED, $eAid['status'], 'la reprise ne dépublie jamais');
		$this->assertTrue(array_key_exists('rate', $eAid['seedDifferences']['properties']), 'le taux perdu est son écart');

	}

	/**
	 * Les quatre routes ne s'ouvrent qu'à la bêta, et l'ouverture n'inscrit l'outil que sous le droit
	 * du planning.
	 */
	public function testRoutes(): void {

		$this->setClock(self::NOW);

		$eAid = $this->createAid('ouverte');

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE_OTHER);

		if($this->assertDecorPresent($this->eStructure->notEmpty(), 'la structure F2 du décor') === FALSE) {
			return;
		}

		$this->assertFalse(AidSetting::isActive($this->eStructure));

		$this->connectAs(planning: \structure\Role::FULL);

		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_OTHER.'/outils/aides'), 'hors bêta, la liste est refusée');
		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_OTHER.'/outils/aides/'.$eAid['id']), 'hors bêta, la fiche est refusée');
		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('POST', 'aid/aid:query', ['id' => self::STRUCTURE_OTHER, 'query' => 'ouverte']), 'hors bêta, la recherche est refusée');
		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('POST', 'aid/aid:departments', ['id' => self::STRUCTURE_OTHER, 'query' => 'meu']), 'hors bêta, aucun département ne se propose');
		$this->assertFalse(\toolbox\Favorite::isOpen($this->eStructure, \toolbox\Favorite::AID), 'hors bêta, l\'outil n\'est pas dans la Boîte');

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE_BETA);

		if($this->assertDecorPresent($this->eStructure->notEmpty(), 'la structure F1 du décor') === FALSE) {
			return;
		}

		$this->assertTrue(\toolbox\Favorite::isOpen($this->eStructure, \toolbox\Favorite::AID));

		$hasTool = fn() => in_array(\toolbox\Favorite::AID, \structure\Feature::model()
			->whereStructure($this->eStructure)
			->getValue('hasToolbox'));

		\structure\FeatureLib::removeToolbox($this->eStructure, \toolbox\Favorite::AID);
		$this->forgetFeatures();

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides'), 'un observateur lit la liste');
		$this->assertFalse($hasTool(), 'un observateur qui ouvre l\'outil ne l\'inscrit pas');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides/'.$eAid['id']), 'un observateur lit la fiche');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:query', ['id' => self::STRUCTURE_BETA, 'query' => 'ouverte']));
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:departments', ['id' => self::STRUCTURE_BETA, 'query' => 'meu']), 'un observateur choisit un autre département');

		// Lecture pour tout membre (m3) : un comptable sans planning lit comme l'observateur.
		$this->connectAs(accounting: \structure\Role::FULL);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides'), 'un comptable lit la liste');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:query', ['id' => self::STRUCTURE_BETA, 'query' => 'ouverte']), 'un comptable cherche');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:departments', ['id' => self::STRUCTURE_BETA, 'query' => 'meu']), 'un comptable choisit un autre département');

		$draft = $this->createAid('brouillon', isPublished: FALSE);

		$this->assertEquals(\NotExistsAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides/'.$draft['id']), 'la fiche d\'un brouillon est introuvable');

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides'));
		$this->assertTrue($hasTool(), 'le planning qui ouvre l\'outil l\'inscrit');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides', ['all' => '1', 'page' => '99']), 'une page au-delà de la dernière montre la dernière');

		$this->connectAs();
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides', []), 'hors de la structure, rien ne s\'ouvre');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'aid/aid:departments', ['id' => self::STRUCTURE_BETA, 'query' => 'meu']), 'hors de la structure, aucun département ne se propose');

	}

	/**
	 * Une structure de la bêta sans code postal, cas de la majorité des structures de dev, que le
	 * formulaire ne laisse plus créer : l'outil s'ouvre sur toute la France, sans département de la
	 * structure à proposer.
	 */
	public function testWithoutPostcode(): void {

		$this->setClock(self::NOW);

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE_BETA);

		if($this->assertDecorPresent($this->eStructure->notEmpty(), 'la structure F1 du décor') === FALSE) {
			return;
		}

		// Le décor vide la colonne que le formulaire rend obligatoire : build() refuserait ce cas, que
		// le test éprouve justement. L'écriture s'annule avec la transaction du test.
		\structure\Structure::model()->update($this->eStructure, ['legalPostcode' => NULL]);

		$this->setPlace(NULL, NULL);

		$eStructure = \structure\StructureLib::getById(self::STRUCTURE_BETA);

		$this->assertEquals(NULL, $eStructure['legalPostcode']);

		$filters = AidLib::getFilters($eStructure, []);

		$this->assertEquals(NULL, $filters['profile']['department']);
		$this->assertFalse($filters['profile']['isPlaced'], 'ni lieu');
		$this->assertEquals(NULL, $filters['department'], 'toute la France');

		$this->connectAs(planning: \structure\Role::FULL);

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides'));
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:query', ['id' => self::STRUCTURE_BETA, 'query' => 'ouverte']));
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'aid/aid:departments', ['id' => self::STRUCTURE_BETA, 'query' => 'meu']));

		// L'en-tête se rend sans département de la structure à proposer.
		$this->assertTrue(new AidUi()->getHeader($eStructure, new \Collection(), $filters) !== '');

	}

	/**
	 * Règle 3 de V-Sub : le département est celui du lieu de production, même quand le siège est dans
	 * un autre. Tant que le lieu n'a pas de département, le code postal du siège en tient lieu.
	 */
	public function testPlaceDepartment(): void {

		$eStructure = $this->getStructure('34000', \structure\Structure::NO);

		$this->setPlace(self::POINT_GAP, '05');

		$profile = AidLib::getProfile($eStructure);

		$this->assertEquals('05', $profile['department'], 'lieu dans les Hautes-Alpes, siège dans l\'Hérault : le lieu l\'emporte');
		$this->assertTrue($profile['isPlaced']);
		$this->assertEquals('05', AidLib::getFilters($eStructure, [])['department'], 'la liste et la fiche suivent le lieu');

		$this->setPlace(self::POINT_GAP, NULL);

		$profile = AidLib::getProfile($eStructure);

		$this->assertEquals('34', $profile['department'], 'un lieu dont le département reste à déduire laisse celui du siège');
		$this->assertTrue($profile['isPlaced'], 'le lieu est posé, rien n\'est à renseigner');

		$profile = AidLib::getProfile($this->getStructure(NULL, \structure\Structure::NO));

		$this->assertEquals(NULL, $profile['department'], 'sans code postal, le département attend la reprise');
		$this->assertTrue($profile['isPlaced']);
		$this->assertTrue($profile['isFrance']);

	}

	/**
	 * Arbitrage de V-Sub : une structure déclarée à l'étranger n'est pas invitée à renseigner un
	 * département. La carte de `AidUi::get()` ne s'affiche que pour `isFrance` sans département, et
	 * c'est ce que le profil préparé doit refuser, que le lieu soit posé ou non. Un département resté
	 * d'avant le changement de pays ne la fait pas revenir pour autant.
	 */
	public function testAbroadNotInvited(): void {

		$eStructureAbroad = $this->getStructure('34000', \structure\Structure::NO);
		$eStructureAbroad['legalCountry'] = new \user\Country(['id' => \user\UserSetting::BE]);

		foreach([[NULL, NULL], [self::POINT_GAP, NULL], [self::POINT_GAP, '05']] as [$lngLat, $department]) {

			$this->setPlace($lngLat, $department);

			$profile = AidLib::getProfile($eStructureAbroad);

			$this->assertFalse($profile['isFrance'], 'une structure en Belgique n\'est pas en France');
			$this->assertEquals(NULL, $profile['department'], 'ni le lieu ni le siège ne lui donnent de département');

		}

		$eStructureFrance = $this->getStructure('34000', \structure\Structure::NO);
		$eStructureFrance['legalCountry'] = new \user\Country(['id' => \user\UserSetting::FR]);

		$this->setPlace(NULL, NULL);

		$profile = AidLib::getProfile($eStructureFrance);

		$this->assertTrue($profile['isFrance'], 'le pendant accepté : une structure déclarée en France l\'est');
		$this->assertEquals('34', $profile['department']);

	}

	/**
	 * Glaçon 2 de L2, U2 : la carte qui invite à situer la structure ne se montre que tant que la liste
	 * n'a aucun département. Un département choisi à la main dans l'outil la retire.
	 */
	public function testPlaceMissingUntilDepartmentChosen(): void {

		$eStructure = $this->getStructure(NULL, \structure\Structure::NO);

		$this->setPlace(NULL, NULL);

		$this->assertTrue(AidLib::getFilters($eStructure, [])['placeMissing'], 'sans lieu ni code postal, la carte invite à situer la structure');
		$this->assertTrue(AidLib::getFilters($eStructure, ['department' => ''])['placeMissing'], 'toute la France choisie, elle reste');
		$this->assertFalse(AidLib::getFilters($eStructure, ['department' => '55'])['placeMissing'], 'un département choisi à la main la retire');

		$this->setPlace(self::POINT_GAP, NULL);

		$this->assertTrue(AidLib::getFilters($eStructure, [])['placeMissing'], 'un repère sans département la garde');
		$this->assertFalse(AidLib::getFilters($eStructure, ['department' => '55'])['placeMissing'], 'jusqu\'au choix d\'un département');

		$this->setPlace(self::POINT_GAP, '05');

		$this->assertFalse(AidLib::getFilters($eStructure, [])['placeMissing'], 'un lieu situé n\'en a pas besoin');

		$eStructure['legalCountry'] = new \user\Country(['id' => \user\UserSetting::BE]);

		$this->setPlace(NULL, NULL);

		$this->assertFalse(AidLib::getFilters($eStructure, [])['placeMissing'], 'une structure à l\'étranger n\'a pas de département à donner');

	}

	/**
	 * Règle 1 de V-Sub : sans lieu, seule une personne qui gère la structure reçoit le bouton vers le
	 * panneau « Lieu de production », que `System::canWrite()` lui réserve.
	 */
	public function testPlaceMissingRights(): void {

		$this->setClock(self::NOW);

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE_BETA);

		if($this->assertDecorPresent($this->eStructure->notEmpty(), 'la structure F1 du décor') === FALSE) {
			return;
		}

		$this->setPlace(NULL, NULL);

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$data = new \stdClass();

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides', data: $data));
		$this->assertTrue($data->eStructure['canUpdatePlace'], 'qui gère la structure renseigne le lieu');
		$this->assertEquals(\structure\SystemLib::getByStructure($this->eStructure)['id'], $data->eStructure['eSystem']['id'], 'le bouton mène au lieu de cette structure');

		$this->connectAs(planning: \structure\Role::FULL);

		$data = new \stdClass();

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE_BETA.'/outils/aides', data: $data));
		$this->assertFalse($data->eStructure['canUpdatePlace'], 'un membre qui ne gère pas lit la carte sans bouton');

	}

	/**
	 * Une structure construite à la main pour le profil : le code postal, le pays et le bio sont tout
	 * ce que `AidLib::getProfile()` en lit, avec les productions de F1, qui n'en a aucune.
	 */
	private function getStructure(?string $postcode, string $organic): \structure\Structure {

		return new \structure\Structure([
			'id' => self::STRUCTURE_BETA,
			'legalPostcode' => $postcode,
			'legalCountry' => new \user\Country(),
			'organic' => $organic,
		]);

	}

	/**
	 * Le lieu de production de F1, tel que `Structure::system()` le rend pour le reste du processus :
	 * le profil le lit là. Rien ne s'écrit, et `tearDown()` l'oublie.
	 */
	private function setPlace(?array $lngLat, ?string $department): void {

		$eSystem = \structure\SystemLib::getByStructure(new \structure\Structure(['id' => self::STRUCTURE_BETA]));
		$eSystem['structure'] = new \structure\Structure(['id' => self::STRUCTURE_BETA]);
		$eSystem['placeLngLat'] = $lngLat;
		$eSystem['placeDepartment'] = $department;

		$property = new \ReflectionProperty(\structure\Structure::class, 'eSystem');
		$property->setValue(NULL, [self::STRUCTURE_BETA => $eSystem] + $property->getValue());

	}

	private function assertAids(array $expected, array $filters, string $message): void {

		$ids = $this->getDecor(AidLib::getByFilters($filters))->getIds();

		sort($ids);

		$expectedIds = array_map(fn(Aid $eAid) => $eAid['id'], $expected);

		sort($expectedIds);

		$this->assertEquals($expectedIds, $ids, $message);

	}

	// Les seules aides du décor, que leur organisme distingue du catalogue de dev.
	private function getDecor(\Collection $cAid): \Collection {

		return $cAid->find(fn(Aid $eAid) => $eAid['organization']['name'] === $this->eOrganization['name']);

	}

	private function createOrganization(string $name = ''): Organization {

		$eOrganization = new Organization();

		$fw = new \FailWatch();

		$eOrganization->build(OrganizationLib::getPropertiesCreate(), [
			'name' => ($name !== '') ? $name : 'Organisme du test L3 '.uniqid(),
			'type' => Organization::REGION,
			'level' => Organization::REGION,
			'url' => NULL,
			'grants' => TRUE,
		]);

		$fw->validate();

		OrganizationLib::create($eOrganization);

		return $eOrganization;

	}

	private function createTag(string $fqn, array $projects): Tag {

		$eTag = new Tag();

		$fw = new \FailWatch();

		$eTag->build(TagLib::getPropertiesCreate(), [
			'fqn' => $fqn,
			'name' => 'Tag '.$fqn,
			'projects' => $projects,
		]);

		$fw->validate();

		TagLib::create($eTag);

		return $eTag;

	}

	/**
	 * Une aide du décor, créée comme la reprise la crée : construite par `build()`, ses enfants par le
	 * leur, puis publiée. Chaque édition est `[état, clôture, taux, plafond, ouverture]`, chaque source `[nature, état]`.
	 */
	private function createAid(
		string $name,
		string $level = Aid::REGION,
		string $calendar = Aid::PERMANENT,
		bool $isPublished = TRUE,
		array $departments = [],
		array $productions = [],
		array $labels = [],
		array $tags = [],
		array $editions = [],
		array $sources = [],
		string $verification = Aid::PRIMARY,
		array $complements = [],
		?string $ratePercentMax = NULL,
		string $type = Aid::GRANT,
		?string $rate = '50 %',
		?string $floor = NULL,
		?string $ceiling = NULL,
		?string $boundBase = NULL,
		?string $boundPeriod = NULL
	): Aid {

		$eAid = new Aid();

		$fw = new \FailWatch();

		$eAid->build(AidLib::getPropertiesCreate(), [
			'name' => $name,
			'organization' => $this->eOrganization,
			'level' => $level,
			'type' => $type,
			'calendar' => $calendar,
			'rate' => $rate,
			'ratePercentMax' => $ratePercentMax,
			'floor' => $floor,
			'ceiling' => $ceiling,
			'boundBase' => $boundBase,
			'boundPeriod' => $boundPeriod,
			'productions' => $productions,
			'labels' => $labels,
			'direct' => TRUE,
			'verification' => $verification,
		]);

		$fw->validate();

		AidLib::create($eAid);

		$children = [
			...array_map(fn(string $department) => [new Area(['aid' => $eAid]), ['department' => $department]], $departments),
			...array_map(fn(Tag $eTag) => [new AidTag(['aid' => $eAid]), ['tag' => $eTag['id']]], $tags),
			...array_map(fn(array $edition) => [new Edition(['aid' => $eAid]), ['startDate' => $edition[4] ?? '2026-01-01', 'endDate' => $edition[1], 'state' => $edition[0], 'rate' => $edition[2] ?? NULL, 'ceiling' => $edition[3] ?? NULL]], $editions),
			...array_map(fn(Aid $eAidTarget) => [new AidLink(['aid' => $eAid]), ['target' => $eAidTarget['id'], 'kind' => AidLink::COMPLEMENTS]], $complements),
		];

		foreach($children as [$element, $input]) {

			$fw = new \FailWatch();

			$element->build(($element->getModule().'Lib')::getPropertiesCreate(), $input);

			$fw->validate();

			($element->getModule().'Lib')::create($element);

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

	private function callRoute(string $method, string $path, array $input = [], \stdClass $data = new \stdClass()): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];

		if($method === 'GET') {
			[$_GET, $_POST, $_REQUEST] = [$input, [], $input];
		} else {
			[$_GET, $_POST, $_REQUEST] = [[], $input, $input];
		}

		try {
			// Les routes déclarées d'abord, puis la page que désigne le chemin, comme `Page::getPages()`
			// les range pour une requête réelle : `aid/aid:query` n'est que là.
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, $data);
			return 'aucune action';
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
	}

	/**
	 * `Structure::conf()` garde la fonctionnalité lue pour le processus : sans l'oublier, l'outil
	 * retiré dans la transaction serait encore lu comme inscrit.
	 */
	private function forgetFeatures(): void {

		foreach(new \ReflectionClass(\structure\Structure::class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

			if($property->getDeclaringClass()->getName() === \structure\Structure::class and $property->hasDefaultValue()) {
				$property->setValue(NULL, $property->getDefaultValue());
			}

		}

	}

	/**
	 * Un membre injecté dans le cache de `MemberLib`, comme `toolbox\RightsTest` ; sans rôle, un
	 * utilisateur connecté qui n'est pas de la structure.
	 */
	private function connectAs(?string $planning = NULL, bool $observe = FALSE, ?string $accounting = NULL, bool $admin = FALSE): void {

		$eUser = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUser);

		if($planning === NULL and $observe === FALSE and $accounting === NULL and $admin === FALSE) {
			return;
		}

		$eMember = new \structure\Member([
			'structure' => $this->eStructure['id'],
			'viewSellingSales' => \structure\Member::SALE,
			'visitedAt' => \Clock::datetime(),
			'role' => new \structure\Role([
				'admin' => $admin,
				'accounting' => $accounting ?? \structure\Role::DENIED,
				'accountingAnalyze' => FALSE,
				'selling' => FALSE,
				'planning' => $planning ?? \structure\Role::DENIED,
				'productionAnalyze' => FALSE,
				'productionObserve' => $observe,
				'commercialisationAnalyze' => FALSE,
			]),
		]);

		$property = self::getMemberCache();
		$property->setValue(NULL, $property->getValue() + [
			$eUser['id'] => new \Collection([$this->eStructure['id'] => $eMember]),
		]);

	}

}
?>
