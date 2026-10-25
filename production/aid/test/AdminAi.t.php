<?php
namespace aid;

/**
 * Le catalogue tenu par l'équipe dans l'administration : ce que le panneau d'une aide saisit, l'état
 * de publication, la relecture, les tags, les doublons des listes filles et la recherche de la liste.
 *
 * Chaque test crée ses aides dans la transaction qui s'annule à sa fin, le catalogue vivant dans la
 * base commune. Leur nom porte une marque que la recherche par texte retrouve seule.
 *
 * Lancement : dev/test package=aid file=AdminAi
 */
class AdminAiTest extends \dev\TransactionTest {

	const NOW = '2026-10-22 12:00:00';
	const MARK = 'zqx-admin-ai';

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

	public function testPropertyLists(): void {

		$this->assertTrue(in_array('verification', AidLib::getPropertiesCreate(), TRUE), 'le semis reprend la vérification');
		$this->assertTrue(in_array('checkedAt', AidLib::getPropertiesUpdate(), TRUE));
		$this->assertFalse(in_array('verification', AidLib::getPropertiesCreate('admin'), TRUE), 'la confiance vient du semis, aucun panneau ne la saisit');
		$this->assertFalse(in_array('checkedAt', AidLib::getPropertiesUpdate('admin'), TRUE), 'une date de relecture ne se saisit pas sans son auteur');
		$this->assertTrue(in_array('notes', AidLib::getPropertiesUpdate('admin'), TRUE), 'les notes de relevé se relisent et se corrigent au panneau');

		$this->assertTrue(in_array('checkedAt', EditionLib::getPropertiesCreate(), TRUE));
		$this->assertFalse(in_array('checkedAt', EditionLib::getPropertiesCreate('admin'), TRUE), 'une édition n\'a pas d\'auteur de relecture');

	}

	public function testRights(): void {

		if($this->setAdmin() === FALSE) {
			return;
		}

		$adminIds = \user\UserSetting::$admin;

		\user\ConnectionLib::setOnline(new \user\User(['id' => max($adminIds) + 1]));

		foreach([new Aid(), new Edition(), new Source(), new Area(), new AidOrganization(), new AidLink(), new AidTag(), new AidPiece(), new Organization(), new Tag(), new Piece()] as $element) {
			$this->assertFalse($element->canWrite(), $element::class.' ne s\'écrit que par l\'équipe');
		}

		$this->setAdmin();

		foreach([new Aid(), new Edition(), new Source(), new Area(), new AidOrganization(), new AidLink(), new AidTag(), new AidPiece()] as $element) {
			$this->assertTrue($element->canWrite(), $element::class.' s\'écrit par l\'équipe');
		}

	}

	/**
	 * Une aide publiée garde son taux sous la main de l'équipe, mais le semis peut le lui retirer : il la
	 * repasse alors lui-même en brouillon.
	 */
	public function testRatePublished(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('taux');
		$this->createSource($eAid, Source::PRIMARY);

		$eAid = $this->getAid($eAid);
		AidLib::publish($eAid);

		$input = $this->getInput(['rate' => '']);

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $input, new \PropertyList('update'));
		$this->assertTrue($fw->has('Aid::rateUnpublished.published'), 'l\'équipe ne vide pas le taux d\'une aide publiée');

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate(), $input);
		$this->assertFalse($fw->has('Aid::rateUnpublished.published'), 'le semis ne construit pas la case et repasse l\'aide en brouillon lui-même');

		AidLib::unpublish($eAid);

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $input, new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'un brouillon se passe de taux, lu : '.json_encode($fw->get()));
		$this->assertEquals(NULL, $eAid['rate']);

	}

	/**
	 * Le panneau de l'équipe porte un champ pour chaque propriété que son enregistrement construit :
	 * build() lit une propriété absente du POST comme vide, et l'enregistrement l'effacerait sans rien dire.
	 */
	public function testUpdatePanelPostsEveryProperty(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$body = new AdminUi()->update(AidLib::getCreateElement())->body;

		foreach(AidLib::getPropertiesUpdate('admin') as $property) {
			// Une saisie assistée poste sa valeur sous le nom de data-autocomplete-field.
			$this->assertTrue(preg_match('/ (name|data-autocomplete-field)="'.$property.'(\\[\\])?"/', $body) === 1, 'le panneau poste '.$property);
		}

	}

	/**
	 * Le panneau de création se dessine sur l'aide neuve de la page, puis la crée par build() : une
	 * propriété que le panneau lit et que l'aide neuve ne porte pas lève avant tout POST.
	 */
	public function testCreatePanel(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = AidLib::getCreateElement();

		// Les cases des labels lisent leur propriété sur l'aide neuve : absente, le rendu lève un avertissement
		// que le lanceur compte sans échouer.
		foreach(['labels', 'labelsBonus'] as $property) {
			$this->assertTrue($eAid->offsetExists($property), 'l\'aide neuve porte '.$property);
		}

		// Le rendu n'est pas éprouvé : seul compte qu'il aille au bout sur l'aide neuve.
		new AdminUi()->create($eAid);

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesCreate('admin'), $this->getInput(['name' => self::MARK.' créée']), new \PropertyList('create'));
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));

		AidLib::create($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertEquals(NULL, $eAid['labels'], 'aucune case cochée, aucun label');
		$this->assertEquals(NULL, $eAid['labelsBonus']);

	}

	public function testAmountUnit(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->getAid($this->createAid('unite'));

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['amountMax' => '5000', 'amountUnit' => '']), new \PropertyList('update'));
		$this->assertTrue($fw->has('Aid::amountUnit.required'), 'un montant maximal sans unité ne dit rien');

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['amountMax' => '5000', 'amountUnit' => Aid::PER_HECTARE]), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['amountMax' => '', 'amountUnit' => '']), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'ni montant ni unité, lu : '.json_encode($fw->get()));

	}

	public function testPublishCycle(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->getAid($this->createAid('cycle', rate: NULL));

		$this->assertEquals([Aid::MISSING_RATE, Aid::MISSING_SOURCE], $eAid->getPublishMissing());
		$this->assertFalse($eAid->acceptPublish());

		AidLib::update($eAid->merge(['rate' => '40 % des dépenses']), ['rate']);
		$eSource = $this->createSource($eAid, Source::PRIMARY);

		$eAid = $this->getAid($eAid);
		$this->assertEquals([], $eAid->getPublishMissing());
		$this->assertTrue($eAid->assertPublish());
		$this->assertFalse($eAid->acceptUnpublish());

		AidLib::publish($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertEquals(Aid::PUBLISHED, $eAid['status']);
		$this->assertEquals(self::NOW, $eAid['publishedAt']);
		$this->assertTrue($eAid->acceptUnpublish());
		$this->assertFalse($eAid->acceptPublish(), 'une aide publiée ne se republie pas');

		AidLib::unpublish($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertEquals(Aid::DRAFT, $eAid['status']);
		$this->assertEquals(NULL, $eAid['publishedAt']);

		// Une source officielle morte retient la publication. Le décor pose l'état que la veille écrit,
		// qu'aucun formulaire ne saisit.
		$eSource['status'] = Source::DEAD;
		SourceLib::update($eSource, ['status']);

		$eAid = $this->getAid($eAid);
		$this->assertEquals([Aid::MISSING_SOURCE_ALIVE], $eAid->getPublishMissing());

		// La relecture, puis l'archive
		$this->assertTrue($eAid->acceptCheck());

		AidLib::check($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertEquals(Aid::TO_CHECK, $eAid['verification'], 'la relecture ne pose pas la confiance');
		$this->assertEquals('2026-10-22', $eAid['checkedAt']);
		$this->assertEquals(\user\ConnectionLib::getOnline()['id'], $eAid['checkedBy']['id'], 'la relecture dit qui l\'a faite');

		AidLib::archive($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertFalse($eAid->acceptCheck(), 'une aide archivée ne se relit plus');
		$this->assertFalse($eAid->acceptArchive());

	}

	public function testCheckKeepsVerification(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		// Une aide lue sur un relais seul, sans source officielle
		$eAid = $this->createAid('relais relu', verification: Aid::RELAY);
		$this->createSource($eAid, Source::RELAY);

		AidLib::check($this->getAid($eAid));

		$eAid = $this->getAid($eAid);
		$this->assertEquals(Aid::RELAY, $eAid['verification'], 'relue, elle n\'est pas vérifiée pour autant');
		$this->assertEquals([Aid::MISSING_SOURCE], $eAid->getPublishMissing());

		// L'outil ne dit « Vérifiée le … » que d'une aide lue sur sa source officielle et relue par l'équipe
		$eAidConflict = $this->createAid('divergente relue', verification: Aid::CONFLICT);
		AidLib::check($this->getAid($eAidConflict));
		$this->assertFalse(AidLib::getForAdminPage($eAidConflict['id'])->isVerifiedByTeam(), 'sources divergentes, relue');

		$eAidPrimary = $this->createAid('officielle', verification: Aid::PRIMARY);
		$this->assertFalse(AidLib::getForAdminPage($eAidPrimary['id'])->isVerifiedByTeam(), 'officielle, jamais relue');

		AidLib::check($this->getAid($eAidPrimary));
		$this->assertTrue(AidLib::getForAdminPage($eAidPrimary['id'])->isVerifiedByTeam(), 'officielle, relue');

		// La fiche de l'outil lit la confiance par isVerifiedByTeam() : sa sélection doit la porter
		$this->createSource($eAidPrimary, Source::PRIMARY);
		AidLib::publish($this->getAid($eAidPrimary));

		$eAidSheet = AidLib::getSheet($eAidPrimary['id'], NULL);
		$this->assertTrue($eAidSheet->isVerifiedByTeam(), 'la fiche');

		$eStructure = new \structure\Structure([
			'id' => \structure\FixtureAiLib::F1,
			'legalPostcode' => NULL,
			'legalCountry' => new \user\Country(),
			'organic' => \structure\Structure::NO,
		]);

		new AidUi()->getSheet($eStructure, AidLib::getFilters($eStructure, []), $eAidSheet);

		AidLib::unpublish($this->getAid($eAidPrimary));

	}

	public function testHiddenReason(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('masquee', calendar: Aid::CALL);
		$this->createSource($eAid, Source::PRIMARY);

		// Le décor pose l'édition sans build(), qui ne vérifierait que ses bornes de saisie : le test
		// veut une édition déjà close, que le panneau laisserait saisir de même.
		EditionLib::create(new Edition([
			'aid' => $eAid,
			'name' => 'Appel 2025',
			'startDate' => '2025-01-01',
			'endDate' => '2025-06-30',
			'state' => Edition::OPEN,
		]));

		AidLib::publish($this->getAid($eAid));

		$eAid = AidLib::getForAdminPage($eAid['id']);

		$this->assertEquals(Aid::HIDDEN_CLOSED, $eAid['hidden'], 'son seul appel est clos par sa date');
		$this->assertEquals('2025-06-30', $eAid['endDate']);

		[$cAid] = AidLib::getForAdmin(new \Search(['text' => self::MARK.' masquee', 'closed' => TRUE]), 0);
		$this->assertEquals([$eAid['id']], $cAid->getIds(), 'le compteur des éditions closes la trouve');

	}

	/**
	 * Un appel dont la seule édition n'ouvre qu'après le jour reste affiché et n'est pas clos : la liste
	 * le date par son ouverture (`eEditionUpcoming`), pas par sa seule clôture. Une édition ouverte de la
	 * même aide l'emporte, et la liste reprend sa clôture.
	 */
	public function testUpcomingEdition(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('a venir', calendar: Aid::CALL);
		$this->createSource($eAid, Source::PRIMARY);

		// Le décor pose l'édition sans build(), qui ne vérifierait que ses bornes de saisie : le test veut
		// une ouverture postérieure au jour figé.
		EditionLib::create(new Edition([
			'aid' => $eAid,
			'name' => 'Émergence 2027',
			'startDate' => '2026-12-15',
			'endDate' => '2027-01-15',
			'state' => Edition::OPEN,
		]));

		AidLib::publish($this->getAid($eAid));

		$search = fn(array $criteria) => AidLib::getForAdmin(new \Search(['text' => self::MARK.' a venir'] + $criteria), 0)[0];

		$eAidListed = $search([])->first();
		$this->assertEquals(NULL, $eAidListed['hidden'], 'affichée avant son ouverture');
		$this->assertEquals('2026-12-15', $eAidListed['eEditionUpcoming']['startDate'], 'datée par son ouverture');
		$this->assertTrue($search(['closed' => TRUE])->empty(), 'une édition à venir n\'est pas close');

		// À venir et proche de sa clôture : la raison « une édition ferme » la compte, et la fiche la date.
		$eAidClosing = $this->createAid('a venir proche', calendar: Aid::CALL);
		$this->createSource($eAidClosing, Source::PRIMARY);

		// Même décor sans build() que plus haut : une ouverture et une clôture exactes, comptées du jour figé.
		EditionLib::create(new Edition([
			'aid' => $eAidClosing,
			'name' => 'Appel éclair',
			'startDate' => '2026-10-27',
			'endDate' => '2026-11-01',
			'state' => Edition::OPEN,
		]));

		AidLib::publish($this->getAid($eAidClosing));

		$eAidPage = AidLib::getForAdminPage($eAidClosing['id']);
		$this->assertEquals(Aid::REVIEW_CLOSING, $eAidPage['review'], 'à venir, elle ferme dans les quinze jours');
		$this->assertEquals('2026-11-01', $eAidPage['endDate'], 'la clôture de l\'édition à venir');
		$this->assertTrue(is_array(new \ReflectionMethod(AdminUi::class, 'getCheck')->invoke(new AdminUi(), $eAidPage)), 'la case Relecture se rend, sa clôture posée');

		// Une édition ouverte sans date de fin à côté : la clôture reste celle de l'édition à venir, comme dans la file.
		EditionLib::create(new Edition([
			'aid' => $eAidClosing,
			'name' => 'Appel permanent',
			'startDate' => '2026-01-01',
			'endDate' => NULL,
			'state' => Edition::OPEN,
		]));

		$eAidPage = AidLib::getForAdminPage($eAidClosing['id']);
		$this->assertEquals(Aid::REVIEW_CLOSING, $eAidPage['review'], 'avec une ouverte sans fin, elle ferme encore');
		$this->assertEquals('2026-11-01', $eAidPage['endDate'], 'avec une ouverte sans fin, la clôture de l\'à venir');
		$this->assertTrue(is_array(new \ReflectionMethod(AdminUi::class, 'getCheck')->invoke(new AdminUi(), $eAidPage)), 'la case Relecture se rend, avec une ouverte sans fin');

		// Ce que lit EditionUi::getState() pour dire « À venir » : les dates de l'édition, son état en base restant « ouverte ».
		$this->assertTrue($eAidPage['cEdition']->find(fn(Edition $eEdition) => $eEdition['name'] === 'Appel éclair')->first()->isUpcoming(), 'ouverte en base, à venir dans la fiche');
		$this->assertFalse($eAidPage['cEdition']->find(fn(Edition $eEdition) => $eEdition['name'] === 'Appel permanent')->first()->isUpcoming(), 'commencée, elle n\'est pas à venir');

		$this->setClock('2026-10-27 08:00:00');
		$this->assertFalse(AidLib::getForAdminPage($eAidClosing['id'])['cEdition']->find(fn(Edition $eEdition) => $eEdition['name'] === 'Appel éclair')->first()->isUpcoming(), 'le jour de son ouverture, elle n\'est plus à venir');
		$this->setClock(self::NOW);

		$this->createEditionClosing($eAid, '2026-11-30');

		$eAidListed = $search([])->first();
		$this->assertTrue($eAidListed['eEditionUpcoming']->empty(), 'l\'édition ouverte l\'emporte');
		$this->assertEquals('2026-11-30', $eAidListed['endDate'], 'la clôture de l\'édition ouverte');

	}

	/**
	 * La case de relecture ne montre que l'état le plus urgent : à revoir, avec sa raison, sinon relue,
	 * sinon jamais relue.
	 */
	public function testReviewReason(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('relecture');

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(NULL, $eAidPage['checkedAt'], 'jamais relue');
		$this->assertEquals(NULL, $eAidPage['review'], 'une aide jamais relue n\'a pas de relecture trop ancienne');

		AidLib::check($this->getAid($eAid));

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals('2026-10-22', $eAidPage['checkedAt']);
		$this->assertEquals(NULL, $eAidPage['review'], 'relue le jour même');

		$this->setClock('2027-04-22 12:00:00');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'relue il y a six mois tout juste');

		$this->setClock('2027-04-23 12:00:00');
		$this->assertEquals(Aid::REVIEW_STALE, AidLib::getForAdminPage($eAid['id'])['review'], 'relue il y a plus de six mois');

		// Une édition qui ferme, sur une aide publiée relue avant que la fenêtre des quinze jours ne s'ouvre
		$eAid = $this->createAid('relecture clôture');
		$this->publishAid($eAid);

		$this->setClock('2026-10-01 12:00:00');
		AidLib::check($this->getAid($eAid));

		$this->setClock(self::NOW);
		$this->createEditionClosing($eAid, '2026-11-06');

		$this->assertEquals(Aid::REVIEW_CLOSING, AidLib::getForAdminPage($eAid['id'])['review'], 'une édition ouverte ferme dans quinze jours');

		$this->setClock('2026-10-21 12:00:00');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'elle ferme dans seize jours');

		$this->setClock(self::NOW);
		AidLib::check($this->getAid($eAid));

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(NULL, $eAidPage['review'], 'relue dans la fenêtre, la clôture a été vue');
		$this->assertEquals('2026-10-22', $eAidPage['checkedAt']);

		$this->setClock('2027-05-01 12:00:00');
		$this->assertEquals(Aid::REVIEW_STALE, AidLib::getForAdminPage($eAid['id'])['review'], 'une édition close ne presse plus');

		// En brouillon, la clôture ne presse pas : les structures ne la voient pas
		$this->setClock(self::NOW);
		$eAid = $this->createAid('relecture brouillon');
		$this->createEditionClosing($eAid, '2026-11-06');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'un brouillon qui ferme');

		$this->publishAid($eAid);
		$this->assertEquals(Aid::REVIEW_CLOSING, AidLib::getForAdminPage($eAid['id'])['review'], 'publiée, elle ferme');

	}

	/**
	 * Une ligne de pièce nomme une pièce courante ou une pièce libre, jamais les deux ni aucune, et la
	 * pièce courante existe (AidPiece::properties(), arbitrage du .yml de L6).
	 */
	public function testPieceExclusive(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces exclusives');
		$ePiece = $this->createPiece(1);

		$this->assertTrue($this->buildPiece($eAid, ['piece' => $ePiece['id']])->ok(), 'une pièce courante seule');
		$this->assertTrue($this->buildPiece($eAid, ['name' => 'Plan du bâtiment'])->ok(), 'une pièce libre seule');
		$this->assertTrue($this->buildPiece($eAid, ['piece' => $ePiece['id'], 'name' => 'Plan du bâtiment'])->has('AidPiece::name.exclusive'), 'les deux');
		$this->assertTrue($this->buildPiece($eAid, [])->has('AidPiece::name.exclusive'), 'aucune');
		$this->assertTrue($this->buildPiece($eAid, ['name' => ''])->has('AidPiece::name.exclusive'), 'un nom vide vaut aucun');
		$this->assertTrue($this->buildPiece($eAid, ['piece' => $ePiece['id'] + 1000000])->has('AidPiece::name.exclusive'), 'une pièce courante qui n\'existe pas');

		$fw = $this->buildPiece($eAid, ['piece' => 'abc']);
		$this->assertTrue($fw->has('AidPiece::piece.check'), 'une pièce mal postée se refuse par son message');
		$this->assertFalse($fw->has('AidPiece::name.exclusive'), 'sans que l\'étape du nom la relise');

	}

	/**
	 * Une pièce libre ne se demande qu'une fois à un moment : la fiche écrirait deux fois la même ligne
	 * (glaçon 1 de L6). Le même nom reste possible à l'autre moment, et une pièce se modifie sans se
	 * heurter à elle-même.
	 */
	public function testPieceDuplicate(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces en double');
		$eAidPiece = $this->createAidPiece($eAid, ['name' => 'Devis du matériel']);

		$this->assertTrue($this->buildPiece($eAid, ['name' => 'Devis du matériel'])->has('AidPiece::name.duplicate'), 'le même nom au même moment');
		$this->assertTrue($this->buildPiece($eAid, ['name' => 'Devis du matériel', 'moment' => AidPiece::PAYMENT])->ok(), 'le même nom à l\'autre moment');
		$this->assertTrue($this->buildPiece($this->createAid('pièces en double, autre aide'), ['name' => 'Devis du matériel'])->ok(), 'le même nom sur une autre aide');

		$this->assertTrue($this->buildPieceUpdate($eAidPiece, ['name' => 'Devis du matériel', 'note' => 'signé'])->ok(), 'la pièce modifiée garde son nom');

		$eAidPieceOther = $this->createAidPiece($eAid, ['name' => 'Factures acquittées', 'moment' => AidPiece::PAYMENT]);
		$this->assertTrue($this->buildPieceUpdate($eAidPieceOther, ['name' => 'Devis du matériel', 'moment' => AidPiece::APPLICATION])->has('AidPiece::name.duplicate'), 'une autre pièce rejoint son nom et son moment');

	}

	/**
	 * Le panneau d'une pièce change ce que la ligne d'ajout a posé, sauf la pièce courante, dont le nom
	 * est celui de la liste commune (glaçon 1 de L6).
	 */
	public function testPieceUpdate(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces modifiées');

		$eAidPiece = $this->createAidPiece($eAid, ['name' => 'Plan du batiment']);

		$fw = $this->buildPieceUpdate($eAidPiece, ['name' => 'Plan du bâtiment', 'moment' => AidPiece::PAYMENT]);
		$this->assertTrue($fw->ok());
		AidPieceLib::update($eAidPiece, AidPieceLib::getPropertiesUpdate());

		$eAidPieceSaved = AidLib::getForAdminPage($eAid['id'])['cAidPiece']->first();
		$this->assertEquals('Plan du bâtiment', $eAidPieceSaved['name'], 'le nom d\'une pièce libre');
		$this->assertEquals(AidPiece::PAYMENT, $eAidPieceSaved['moment'], 'le moment');

		$eAidPieceCommon = $this->createAidPiece($eAid, ['piece' => $this->createPiece(1)['id']]);

		$this->assertTrue($this->buildPieceUpdate($eAidPieceCommon, ['moment' => AidPiece::PAYMENT])->ok(), 'une pièce courante change de moment');
		$this->assertTrue($this->buildPieceUpdate($eAidPieceCommon, ['name' => 'Un autre nom'])->has('AidPiece::name.exclusive'), 'une pièce courante ne prend pas de nom');
		$this->assertTrue($this->buildPieceUpdate($eAidPiece, ['name' => ''])->has('AidPiece::name.exclusive'), 'une pièce libre garde un nom');

	}

	/**
	 * Une pièce ne s'ajoute qu'à une aide que la structure dépose elle-même et qui a un dossier : ni une
	 * aide déposée par un tiers, ni une aide automatique, ni un crédit d'impôt (Aid::acceptPieces()).
	 */
	public function testPieceAccepted(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces acceptées');
		$this->assertTrue(new AidPiece(['aid' => $this->getAid($eAid)])->acceptCreate(), 'déposée par la structure');

		$eAidAutomatic = $this->createAid('pièces automatique', calendar: Aid::AUTOMATIC);
		$this->assertFalse(new AidPiece(['aid' => $this->getAid($eAidAutomatic)])->acceptCreate(), 'versée sans dossier');

		// Le décor écrit la forme de l'aide sans build(), qui ne vérifierait que l'enum : le test veut
		// les formes que la règle écarte.
		$eAid['direct'] = FALSE;
		AidLib::update($eAid, ['direct']);
		$this->assertFalse(new AidPiece(['aid' => $this->getAid($eAid)])->acceptCreate(), 'déposée par un tiers');

		$eAid['direct'] = TRUE;
		$eAid['type'] = Aid::TAX;
		AidLib::update($eAid, ['direct', 'type']);
		$this->assertFalse(new AidPiece(['aid' => $this->getAid($eAid)])->acceptCreate(), 'un crédit d\'impôt');

	}

	/**
	 * Seul « Marquer la liste relue » date la liste : ajouter ou retoucher une pièce ne dit pas que la
	 * liste entière a été relue contre sa source (constat 1 de l'arbitrage de L6).
	 */
	public function testCheckPieces(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces relues');

		$eAidPiece = new AidPiece(['aid' => $eAid]);
		$fw = new \FailWatch();
		$eAidPiece->build(AidPieceLib::getPropertiesCreate(), ['moment' => AidPiece::APPLICATION, 'name' => 'Plan du bâtiment']);
		$fw->validate();
		AidPieceLib::create($eAidPiece);

		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['piecesCheckedAt'], 'une pièce ajoutée');

		$fw = new \FailWatch();
		$eAidPiece->build(AidPieceLib::getPropertiesUpdate(), ['moment' => AidPiece::APPLICATION, 'name' => 'Plan du bâtiment', 'note' => 'pour un bâtiment neuf', 'url' => NULL]);
		$fw->validate();
		AidPieceLib::update($eAidPiece, AidPieceLib::getPropertiesUpdate());

		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['piecesCheckedAt'], 'une pièce retouchée');

		AidLib::checkPieces($this->getAid($eAid));

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals('2026-10-22', $eAidPage['piecesCheckedAt']);
		$this->assertEquals(NULL, $eAidPage['checkedAt'], 'relire la liste ne relit pas la fiche');
		$this->assertEquals(1, $eAidPage['cAidPiece']->count());

	}

	// Le semis de la liste commune se rejoue sans doublon, et reprend le nom d'une pièce déjà semée.
	public function testSeedPieces(): void {

		if($this->setAdmin() === FALSE) {
			return;
		}

		$pieces = [self::MARK.'-seed' => 'Pièce semée'];

		$this->assertEquals(['created' => 1, 'updated' => 0], PieceLib::seed($pieces));
		$this->assertEquals(['created' => 0, 'updated' => 1], PieceLib::seed([self::MARK.'-seed' => 'Pièce renommée']));

		$cPiece = Piece::model()->select(['name'])->whereFqn(self::MARK.'-seed')->getCollection();
		$this->assertEquals(1, $cPiece->count(), 'sans doublon');
		$this->assertEquals('Pièce renommée', $cPiece->first()['name']);

	}

	/**
	 * La page de la liste des pièces que la veille voit mourir met l'aide en file sans la masquer, et
	 * seul « Marquer la liste relue » l'en sort : « Marquer relue » relit la fiche, pas la liste
	 * (règle 10 et CL-15 de L6).
	 */
	public function testReviewPieces(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('pièces veille');
		$this->publishAid($eAid);
		AidLib::check($this->getAid($eAid));

		// Des IP publiques écrites en clair, que la veille contrôle sans interroger le DNS (Watch.t.php).
		// Deux relais qui répondent font une nuit lisible : une nuit dont la plupart des pages manquent
		// ne fait progresser aucune source (WatchLib::watch()).
		$cSource = new \Collection();

		foreach(['pieces' => Source::PIECES, 'relay-1' => Source::RELAY, 'relay-2' => Source::RELAY] as $name => $kind) {

			$eSourceCreated = new Source(['aid' => $eAid]);
			$fw = new \FailWatch();
			$eSourceCreated->build(SourceLib::getPropertiesCreate(), ['url' => 'https://93.184.215.14/'.self::MARK.'/'.$name, 'kind' => $kind]);
			$fw->validate();
			SourceLib::create($eSourceCreated);

			$cSource[$eSourceCreated['id']] = $eSourceCreated;

		}

		$eSource = $cSource->first();

		$request = fn(string $url, string $ip): array => [
			'error' => NULL,
			'code' => str_ends_with($url, '/pieces') ? 404 : 200,
			'body' => '',
			'contentType' => 'text/html',
			'location' => NULL,
		];

		// Le réseau reste fermé, en filet : la lecture simulée seule répond.
		\util\CurlLib::setLocked(TRUE);

		try {

			foreach(['2026-10-23', '2026-10-24', '2026-10-25'] as $night) {
				$this->setClock($night.' 01:30:00');
				WatchLib::watch(WatchLib::getSources()->find(fn(Source $eSourceWatched) => $cSource->offsetExists($eSourceWatched['id'])), $request);
			}

		} finally {
			\util\CurlLib::setLocked(FALSE);
		}

		$this->setClock('2026-10-25 09:00:00');

		$this->assertEquals(Source::DEAD, SourceLib::getById($eSource['id'])['status'], 'la troisième nuit');

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidPage['review'], 'la liste ne répond plus');
		$this->assertFalse($eAidPage['reviewSourcePrimary'], 'sa source officielle répond');
		$this->assertTrue($eAidPage['reviewSourcePieces']);
		$this->assertTrue(AidLib::applyDisplay(new AidModel())->whereId($eAid)->exists(), 'la liste ne masque pas l\'aide');

		// La raison se lit dans la case de la liste, seule en alerte : la relecture de la fiche n'a rien à éteindre.
		$this->assertTrue($eAidPage->isReviewPiecesOnly(), 'à revoir pour sa seule liste');
		$this->assertEquals(['pieces'], $eAidPage['alerts'], 'la case de la liste passe en alerte, pas la relecture');
		$this->assertEquals(NULL, $eAidPage->getCheckReason(), 'la fiche n\'a rien d\'autre à revoir');

		// Relue il y a plus de six mois, la fiche se redit à revoir derrière sa liste.
		$this->setClock('2027-05-01 09:00:00');
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidPage['review'], 'la file la range sous sa liste');
		$this->assertEquals(Aid::REVIEW_STALE, $eAidPage->getCheckReason(), 'la relecture dit la raison qui suit');
		$this->assertEquals(['check', 'pieces'], $eAidPage['alerts'], 'les deux cases passent en alerte');
		$this->setClock('2026-10-25 09:00:00');

		AidLib::check($this->getAid($eAid));
		$this->assertEquals(Aid::REVIEW_SOURCE, AidLib::getForAdminPage($eAid['id'])['review'], 'relire la fiche ne relit pas la liste');

		// Le décor écrit la forme de l'aide sans build(), qui ne vérifierait que le booléen : le test veut
		// une aide qui cesse d'accepter des pièces, dont « Marquer la liste relue » disparaît.
		$eAid['direct'] = FALSE;
		AidLib::update($eAid, ['direct']);
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'une aide sans pièces ne reste pas en file pour sa liste');

		$eAid['direct'] = TRUE;
		AidLib::update($eAid, ['direct']);
		$this->assertEquals(Aid::REVIEW_SOURCE, AidLib::getForAdminPage($eAid['id'])['review'], 'elle y revient en acceptant de nouveau des pièces');

		AidLib::checkPieces($this->getAid($eAid));
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'la liste relue');

	}

	/**
	 * Une aide masquée par la veille se lit masquée dans la file, comme le chiffre la compte, même quand
	 * sa source officielle est relue et que seule sa liste des pièces la garde en file (V-Sub, relecture
	 * de L5). La ligne se dit masquée parce que `hidden` passe devant les deux moitiés de la raison
	 * (AdminUi::getReviewSource()).
	 */
	public function testReviewHiddenPieces(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('masquée et pièces');
		$eSourcePrimary = $this->createSource($eAid, Source::PRIMARY);
		$eSourcePieces = $this->createSource($eAid, Source::PIECES);
		AidLib::publish($this->getAid($eAid));

		$this->setWatch($eSourcePrimary, ['status' => Source::DEAD, 'statusAt' => '2026-10-22 01:30:00', 'failures' => 3]);
		$this->setWatch($eSourcePieces, ['status' => Source::DEAD, 'statusAt' => '2026-10-22 01:30:00', 'failures' => 3]);

		// Relue le matin qui suit la nuit, la source officielle ne garde plus l'aide en file.
		AidLib::check($this->getAid($eAid));

		$cAidHidden = AidLib::getReview(0, Aid::HIDDEN_SOURCE_DEAD)[0];

		$this->assertTrue(in_array($eAid['id'], $cAidHidden->getIds(), TRUE), 'le chiffre la compte parmi les masquées');

		$eAidRow = $cAidHidden->find(fn(Aid $eAidHidden) => $eAidHidden['id'] === $eAid['id'])->first();
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidRow['review']);
		$this->assertEquals(Aid::HIDDEN_SOURCE_DEAD, $eAidRow['hidden'], 'la ligne la dit masquée');
		$this->assertFalse($eAidRow['reviewSourcePrimary'], 'sa source officielle est relue');
		$this->assertTrue($eAidRow['reviewSourcePieces'], 'sa liste des pièces reste à revoir');
		$this->assertFalse(in_array($eAid['id'], AidLib::getReview(0, Aid::REVIEW_SOURCE)[0]->getIds(), TRUE), 'elle ne se compte pas parmi les sources à revoir');

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertTrue($eAidPage['reviewSourcePieces'], 'la page de l\'aide propose de relire la liste');

		// Masquée, l'aide le dit dans « Aux structures » ; sa fiche relue n'a rien d'autre à revoir, et la
		// liste garde sa raison dans sa case. « Marquer relue » n'y éteindrait rien.
		$this->assertTrue($eAidPage->isReviewPiecesOnly(), 'seule la liste reste à revoir');
		$this->assertEquals(NULL, $eAidPage->getCheckReason(), 'la relecture de la fiche ne propose rien');
		$this->assertFalse($eAidPage->isCheckDue(), 'la fiche n\'offre pas « Marquer relue »');
		$this->assertTrue($eAidPage->assertPieces(), 'la case de la liste offre « Marquer la liste relue »');
		$this->assertEquals(['display', 'pieces'], $eAidPage['alerts'], 'l\'affichage et la liste en alerte, pas la relecture');

	}

	public function testReviewFilter(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		// Relue avant la fenêtre de clôture
		$eAidBefore = $this->createAid('filtre a');
		$this->setClock('2026-10-01 12:00:00');
		AidLib::check($this->getAid($eAidBefore));

		// Relue il y a plus de six mois
		$eAidStale = $this->createAid('filtre c');
		$this->setClock('2026-04-01 12:00:00');
		AidLib::check($this->getAid($eAidStale));

		$this->setClock(self::NOW);

		// Une édition qui ferme ne presse que sur une aide publiée : les aides qui en ont une le sont,
		// sauf le brouillon « filtre i »
		$this->publishAid($eAidBefore);
		$this->createEditionClosing($eAidBefore, '2026-11-06');

		// Relue dans la fenêtre de clôture
		$eAidWithin = $this->createAid('filtre b');
		AidLib::check($this->getAid($eAidWithin));
		$this->publishAid($eAidWithin);
		$this->createEditionClosing($eAidWithin, '2026-11-06');

		// Jamais relues, sans puis avec une édition qui ferme
		$eAidNever = $this->createAid('filtre d');

		$eAidNeverClosing = $this->createAid('filtre e');
		$this->publishAid($eAidNeverClosing);
		$this->createEditionClosing($eAidNeverClosing, '2026-11-06');

		// Jamais relue, une édition qui ferme, en brouillon
		$eAidDraftClosing = $this->createAid('filtre i');
		$this->createEditionClosing($eAidDraftClosing, '2026-11-06');

		// Trois aides semées, datées par leur fiche sans que personne de l'équipe les ait relues. La date ne
		// se saisit pas au panneau (liste 'admin') : le décor pose celle que le semis reprend.

		// Semée il y a trois semaines
		$eAidSeeded = $this->createAid('filtre f');
		$eAidSeeded['checkedAt'] = '2026-10-01';
		AidLib::update($eAidSeeded, ['checkedAt']);

		// Semée dans la fenêtre d'une édition qui ferme : la date de la reprise ne vaut pas relecture
		$eAidSeededClosing = $this->createAid('filtre h');
		$eAidSeededClosing['checkedAt'] = '2026-10-22';
		AidLib::update($eAidSeededClosing, ['checkedAt']);
		$this->publishAid($eAidSeededClosing);
		$this->createEditionClosing($eAidSeededClosing, '2026-11-06');

		// Semée il y a plus de six mois : à revoir, et toujours jamais relue
		$eAidSeededStale = $this->createAid('filtre g');
		$eAidSeededStale['checkedAt'] = '2026-03-01';
		AidLib::update($eAidSeededStale, ['checkedAt']);

		$search = fn(string $review) => AidLib::getForAdmin(new \Search(['text' => self::MARK.' filtre', 'review' => $review]), 0)[0]->getIds();

		$idsDue = $search(Aid::REVIEW_DUE);
		$idsNever = $search(Aid::REVIEW_NEVER);

		$this->assertEquals([$eAidBefore['id'], $eAidStale['id'], $eAidNeverClosing['id'], $eAidSeededStale['id'], $eAidSeededClosing['id']], $idsDue);
		$this->assertEquals([$eAidNever['id'], $eAidNeverClosing['id'], $eAidSeeded['id'], $eAidSeededStale['id'], $eAidSeededClosing['id'], $eAidDraftClosing['id']], $idsNever, 'une aide semée jamais relue en est');
		$this->assertEquals(Aid::REVIEW_CLOSING, AidLib::getForAdminPage($eAidSeededClosing['id'])['review'], 'semée dans la fenêtre, elle ferme sans que personne l\'ait relue');

		// Le filtre et la case de la fiche disent la même chose
		foreach([$eAidBefore, $eAidWithin, $eAidStale, $eAidNever, $eAidNeverClosing, $eAidSeeded, $eAidSeededStale, $eAidSeededClosing, $eAidDraftClosing] as $eAid) {

			$eAidPage = AidLib::getForAdminPage($eAid['id']);

			$this->assertEquals($eAidPage['review'] !== NULL, in_array($eAid['id'], $idsDue, TRUE), $eAidPage['name']);
			$this->assertEquals($eAidPage['reviewNever'], in_array($eAid['id'], $idsNever, TRUE), $eAidPage['name']);
			$this->assertEquals($eAidPage['checkedBy']->empty(), $eAidPage['reviewNever'], $eAidPage['name']);

		}

	}

	/**
	 * La file de revue : une aide par raison, dans l'ordre de la file (clôture, écart de la reprise,
	 * sources divergentes jamais relues, ancienneté), et ni une aide archivée ni une aide divergente
	 * relue. La file, le filtre « À revoir » de la liste et la case Relecture disent les mêmes aides.
	 */
	/**
	 * Ce que la veille relève sur une source officielle met l'aide dans la file jusqu'à la relecture qui
	 * suit : déplacée ou morte, illisible depuis sept jours, et une seule source morte ne la masque pas
	 * tant qu'une autre répond (cadrage de L5, Q1 A, Q2 a et b ; arbitrage de la planche).
	 */
	public function testReviewWatch(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		// Déplacée ou morte : la relecture du matin qui suit la nuit l'éteint
		$eAid = $this->createAid('veille morte');
		$eSource = $this->createSource($eAid, Source::PRIMARY);
		$eSourceOther = $this->createSource($eAid, Source::PRIMARY);
		AidLib::publish($this->getAid($eAid));
		AidLib::check($this->getAid($eAid));

		$this->setWatch($eSource, ['status' => Source::DEAD, 'statusAt' => '2026-10-23 01:30:00']);

		$this->setClock('2026-10-23 09:00:00');

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidPage['review']);
		$this->assertEquals(NULL, $eAidPage['hidden'], 'une seconde source officielle tient l\'aide affichée');

		AidLib::check($this->getAid($eAid));
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'relue le matin qui suit la nuit');

		$this->setWatch($eSourceOther, ['status' => Source::DEAD, 'statusAt' => '2026-10-24 01:30:00']);

		$this->setClock('2026-10-24 09:00:00');

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidPage['review']);
		$this->assertEquals(Aid::HIDDEN_SOURCE_DEAD, $eAidPage['hidden'], 'toutes ses sources officielles sont mortes');
		$this->assertFalse(AidLib::applyDisplay(new AidModel())->whereId($eAid)->exists(), 'la requête d\'affichage la retire aussi');

		// Illisible : sept jours sans lecture, éteinte par la relecture, rallumée par une nouvelle suite
		$this->setClock(self::NOW);

		$eAid = $this->createAid('veille illisible');
		$eSource = $this->createSource($eAid, Source::PRIMARY);
		AidLib::publish($this->getAid($eAid));
		AidLib::check($this->getAid($eAid));

		$this->setWatch($eSource, ['unreadSince' => '2026-10-23', 'unreadReason' => Source::FORBIDDEN]);

		$this->setClock('2026-10-29 09:00:00');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'six jours sans lecture');

		$this->setClock('2026-10-30 09:00:00');
		$this->assertEquals(Aid::REVIEW_UNREADABLE, AidLib::getForAdminPage($eAid['id'])['review'], 'sept jours sans lecture');

		AidLib::check($this->getAid($eAid));

		$this->setClock('2026-11-05 09:00:00');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'relue, tant que le site refuse sans interruption');

		$this->setWatch($eSource, ['unreadSince' => '2026-11-06', 'unreadReason' => Source::CHALLENGE]);

		$this->setClock('2026-11-13 09:00:00');
		$this->assertEquals(Aid::REVIEW_UNREADABLE, AidLib::getForAdminPage($eAid['id'])['review'], 'lue entre-temps puis de nouveau refusée');

	}

	/**
	 * Les chiffres de la veille en tête de la file, et la file qu'ils restreignent à leur raison : une
	 * aide masquée ne se compte pas sous les sources à revoir, les deux chiffres sont disjoints
	 * (arbitrage de V-Sub sur le glaçon 1 de L5).
	 */
	public function testReviewFigures(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$figuresBefore = AidLib::getReviewFigures();

		$eAidHidden = $this->createAid('veille masquée');
		$eSourceHidden = $this->createSource($eAidHidden, Source::PRIMARY);
		AidLib::publish($this->getAid($eAidHidden));
		$this->setWatch($eSourceHidden, ['status' => Source::DEAD, 'statusAt' => '2026-10-22 01:30:00', 'failures' => 3]);

		$eAidMoved = $this->createAid('veille déplacée');
		$eSourceMoved = $this->createSource($eAidMoved, Source::PRIMARY);
		$this->createSource($eAidMoved, Source::PRIMARY);
		AidLib::publish($this->getAid($eAidMoved));
		$this->setWatch($eSourceMoved, ['status' => Source::MOVED, 'statusAt' => '2026-10-22 01:30:00', 'movedUrl' => 'https://example.org/'.self::MARK.'/arrivée']);

		$eAidUnreadable = $this->createAid('veille illisible');
		$eSourceUnreadable = $this->createSource($eAidUnreadable, Source::PRIMARY);
		$eSourceChanged = $this->createSource($eAidUnreadable, Source::RELAY);
		AidLib::publish($this->getAid($eAidUnreadable));
		$this->setWatch($eSourceUnreadable, ['unreadSince' => '2026-10-15', 'unreadReason' => Source::ROBOTS]);
		$this->setWatch($eSourceChanged, ['checkedAt' => '2026-10-22 01:30:00', 'fingerprint' => str_repeat('a', 64), 'changedAt' => '2026-10-22 01:30:00']);

		$figures = AidLib::getReviewFigures();

		$this->assertEquals(1, $figures[Aid::HIDDEN_SOURCE_DEAD] - $figuresBefore[Aid::HIDDEN_SOURCE_DEAD]);
		$this->assertEquals(1, $figures[Aid::REVIEW_SOURCE] - $figuresBefore[Aid::REVIEW_SOURCE], 'la déplacée, sans la masquée');
		$this->assertEquals(1, $figures[Aid::REVIEW_UNREADABLE] - $figuresBefore[Aid::REVIEW_UNREADABLE]);
		$this->assertEquals(1, $figures['changed'] - $figuresBefore['changed']);
		$this->assertEquals(1, $figures['changedAids'] - $figuresBefore['changedAids']);
		$this->assertTrue($figures['watched']);

		$getIds = fn(?string $reason) => AidLib::getReview(0, $reason)[0]->getIds();

		$this->assertTrue(in_array($eAidHidden['id'], $getIds(Aid::HIDDEN_SOURCE_DEAD), TRUE));
		$this->assertFalse(in_array($eAidMoved['id'], $getIds(Aid::HIDDEN_SOURCE_DEAD), TRUE), 'une seconde source la tient affichée');
		$this->assertTrue(in_array($eAidMoved['id'], $getIds(Aid::REVIEW_SOURCE), TRUE));
		$this->assertFalse(in_array($eAidHidden['id'], $getIds(Aid::REVIEW_SOURCE), TRUE), 'la masquée a son propre chiffre');
		$this->assertFalse(in_array($eAidUnreadable['id'], $getIds(Aid::REVIEW_SOURCE), TRUE));
		$this->assertTrue(in_array($eAidUnreadable['id'], $getIds(Aid::REVIEW_UNREADABLE), TRUE));

		[$cAid, $nFound] = AidLib::getReview(0, Aid::REVIEW_UNREADABLE);
		$this->assertEquals($cAid->count(), $nFound, 'le compte est celui de la file restreinte');
		$this->assertEquals($figures[Aid::REVIEW_UNREADABLE], $nFound, 'le chiffre annonce la file qu\'il ouvre');

		$eAidPage = AidLib::getForAdminPage($eAidUnreadable['id'], 0, Aid::REVIEW_UNREADABLE);
		$this->assertEquals([$eSourceUnreadable['id']], $eAidPage['cSourceIssue']->getIds(), 'seule la source illisible est en cause, pas le relais changé');
		$this->assertEquals(Aid::REVIEW_UNREADABLE, $eAidPage['queue']['reason'], 'la file garde sa raison d\'une aide à l\'autre');
		$this->assertEquals($eSourceUnreadable['id'], $eAidPage->getSourceUnreadable()['id']);

		AidLib::check($this->getAid($eAidHidden));
		$this->assertFalse(in_array($eAidHidden['id'], $getIds(Aid::HIDDEN_SOURCE_DEAD), TRUE), 'relue, elle sort du chiffre avec la file');

	}

	/**
	 * La raison qui restreint la file voyage entre la file et la page de l'aide (glaçon 1 de L5) : le
	 * lien de la colonne de côté, la flèche de retour et l'aide suivante de « Marquer relue » restent
	 * dans le filtre. Les adresses sont celles que suit le clic, seul contrat lu dans le rendu.
	 */
	public function testReviewReasonTravels(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$ids = [];

		foreach(['a', 'b'] as $letter) {

			$eAid = $this->createAid('filtre '.$letter);
			$eSource = $this->createSource($eAid, Source::PRIMARY);
			$this->createSource($eAid, Source::PRIMARY);
			AidLib::publish($this->getAid($eAid));
			$this->setWatch($eSource, ['status' => Source::MOVED, 'statusAt' => '2026-10-22 01:30:00', 'movedUrl' => 'https://example.org/'.self::MARK.'/'.$letter]);

			$ids[] = $eAid['id'];

		}

		[$cAid, $nFound] = AidLib::getReview(0, Aid::REVIEW_SOURCE);
		$idsFilter = $cAid->getIds();

		$this->assertTrue(array_diff($ids, $idsFilter) === [], 'les deux aides sont dans le filtre');

		// La colonne de côté de la file filtrée mène à la page de l'aide avec le filtre
		$eAidSide = AidLib::getForAdminPage($ids[0]);
		$eAidSide['queue'] = AidLib::getReviewQueue($cAid, $eAidSide, 0, 'review', Aid::REVIEW_SOURCE);

		$html = new AdminUi()->getReview($cAid, $nFound, 0, Aid::REVIEW_SOURCE, $eAidSide);
		$this->assertTrue(str_contains($html, 'href="/aid/admin/aid?id='.$ids[0].'&origin=review&page=0&reason='.Aid::REVIEW_SOURCE.'"'), 'le lien vers la page de l\'aide');

		// La page de l'aide ouverte ainsi garde la raison, pour l'aide suivante et pour le retour
		$eAidPage = AidLib::getForAdminPage($ids[0], 0, Aid::REVIEW_SOURCE);
		$this->assertEquals(Aid::REVIEW_SOURCE, $eAidPage['queue']['reason']);
		$this->assertTrue(in_array($eAidPage['queue']['next'], $idsFilter, TRUE), 'l\'aide suivante est dans le filtre');

		$html = new AdminUi()->getAid($eAidPage);
		$this->assertTrue(str_contains($html, 'post-reason="'.Aid::REVIEW_SOURCE.'"'), 'Marquer relue poste la raison');
		$this->assertTrue(str_contains($html, '/aid/admin/:review?page=0&reason='.Aid::REVIEW_SOURCE.'"'), 'la flèche de retour');

		// Sans filtre, aucune raison ne s'invente
		$eAidPage = AidLib::getForAdminPage($ids[0], 0);
		$this->assertTrue(str_contains(new AdminUi()->getAid($eAidPage), '/aid/admin/:review?page=0"'), 'retour à toute la file');

	}

	/**
	 * L'adresse d'une aide publiée qui ne s'affiche pas, gardée par une structure, dit pourquoi sans
	 * fiche : masquée par la veille, elle reviendra ; close, elle ne reviendra pas, même quand ses sources
	 * sont mortes aussi. Une aide affichée ou en brouillon n'y répond pas (arbitrages de V-Sub sur le
	 * glaçon 1 de L5).
	 */
	public function testHidden(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('masquée par la veille');
		$eSource = $this->createSource($eAid, Source::PRIMARY);
		AidLib::publish($this->getAid($eAid));

		$this->assertTrue(AidLib::getHidden($eAid['id'])->empty(), 'affichée');

		$this->setWatch($eSource, ['status' => Source::DEAD, 'statusAt' => '2026-10-22 01:30:00', 'failures' => 3]);

		$this->assertTrue(AidLib::getSheet($eAid['id'], NULL)->empty(), 'pas de fiche');
		$this->assertEquals(Aid::HIDDEN_SOURCE_DEAD, AidLib::getHidden($eAid['id'])['hidden'], 'masquée par la veille');

		$this->assertEquals(Aid::HIDDEN_SOURCE_DEAD, AidLib::getForAdmin(new \Search(['text' => self::MARK.' masquée']), 0)[0]->first()['hidden'], 'la liste de l\'équipe la marque');

		// Close par son seul appel, elle le dit, que sa source réponde ou non
		$eAidClosed = $this->createAid('close', calendar: Aid::CALL);
		$eSourceClosed = $this->createSource($eAidClosed, Source::PRIMARY);
		$this->createEdition($eAidClosed, '2026-06-30');
		AidLib::publish($this->getAid($eAidClosed));

		$this->assertEquals(Aid::HIDDEN_CLOSED, AidLib::getHidden($eAidClosed['id'])['hidden'], 'close');

		$this->setWatch($eSourceClosed, ['status' => Source::DEAD, 'statusAt' => '2026-10-22 01:30:00', 'failures' => 3]);
		$this->assertEquals(Aid::HIDDEN_CLOSED, AidLib::getHidden($eAidClosed['id'])['hidden'], 'close, sa source morte ne la fait pas revenir');

		// L'équipe lit la même raison, et sa source morte reste à revoir sans compter parmi les masquées
		$this->assertEquals(Aid::HIDDEN_CLOSED, AidLib::getForAdmin(new \Search(['text' => self::MARK.' close']), 0)[0]->first()['hidden'], 'la liste de l\'équipe la dit close');
		$this->assertFalse(in_array($eAidClosed['id'], AidLib::getReview(0, Aid::HIDDEN_SOURCE_DEAD)[0]->getIds(), TRUE), 'close, elle n\'est pas masquée par la veille');
		$this->assertTrue(in_array($eAidClosed['id'], AidLib::getReview(0, Aid::REVIEW_SOURCE)[0]->getIds(), TRUE), 'sa source morte reste à revoir');

		// Une ligne de la file porte la raison que le chiffre compte
		$getRow = fn(?string $reason, Aid $eAidSearched) => AidLib::getReview(0, $reason)[0]->find(fn(Aid $eAidRow) => $eAidRow['id'] === $eAidSearched['id'])->first();

		$this->assertEquals(Aid::HIDDEN_CLOSED, $getRow(Aid::REVIEW_SOURCE, $eAidClosed)['hidden'], 'la file la dit close');
		$this->assertEquals(Aid::HIDDEN_SOURCE_DEAD, $getRow(Aid::HIDDEN_SOURCE_DEAD, $eAid)['hidden'], 'la file la dit masquée');

		AidLib::unpublish($this->getAid($eAid));
		$this->assertTrue(AidLib::getHidden($eAid['id'])->empty(), 'un brouillon ne s\'annonce pas');

		$this->assertTrue(AidLib::getHidden(0)->empty(), 'aucune aide');

	}

	/**
	 * « Prendre la nouvelle adresse » : l'adresse est celle que la veille a relevée, validée comme une
	 * adresse saisie, et la source repart de zéro pour la veille. Le test rejoue les deux étapes de
	 * l'action (page admin/source.p.php, doMove) sans passer par la page : un test ne lance pas de
	 * route, et le geste lui-même s'éprouve dans Chrome.
	 */
	public function testMove(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('source redirigée');
		$eSource = $this->createSource($eAid, Source::PRIMARY);
		$eSourceOther = $this->createSource($eAid, Source::RELAY);

		$getSource = fn(Source $eSource) => SourceLib::getById($eSource['id'], SourceLib::getSelectionWrite());

		$this->assertFalse($getSource($eSource)->acceptMove(), 'une source qui répond');

		$movedUrl = 'https://example.org/'.self::MARK.'/nouvelle';
		$this->setWatch($eSource, ['status' => Source::MOVED, 'statusAt' => '2026-10-22 01:30:00', 'movedUrl' => $movedUrl, 'checkedAt' => '2026-10-22 01:30:00']);

		$eSourceMoved = $getSource($eSource);
		$this->assertTrue($eSourceMoved->acceptMove());
		$this->assertTrue($eSourceMoved->assertMove(), 'l\'équipe peut le faire');

		$fw = new \FailWatch();
		$eSourceMoved->build(['url'], ['url' => $eSourceMoved['movedUrl']]);
		$this->assertTrue($fw->ok());
		SourceLib::update($eSourceMoved, ['url']);

		$eSourceAfter = $getSource($eSource);
		$this->assertEquals($movedUrl, $eSourceAfter['url']);
		$this->assertEquals(Source::ALIVE, $eSourceAfter['status']);
		$this->assertEquals(NULL, $eSourceAfter['movedUrl']);
		$this->assertEquals(NULL, $eSourceAfter['checkedAt'], 'une page jamais lue pour la veille');
		$this->assertFalse($eSourceAfter->acceptMove(), 'le geste ne se refait pas');

		// Une redirection vers une adresse que l'aide cite déjà se refuse comme une saisie
		$this->setWatch($eSource, ['status' => Source::MOVED, 'statusAt' => '2026-10-23 01:30:00', 'movedUrl' => $eSourceOther['url']]);

		$fw = new \FailWatch();
		$getSource($eSource)->build(['url'], ['url' => $eSourceOther['url']]);
		$this->assertTrue($fw->has('Source::url.duplicate'), 'déjà une source de l\'aide');

		$this->setUser();
		$this->assertFalse($getSource($eSource)->assertMove(), 'hors de l\'équipe');

	}

	/**
	 * Les deux raisons de date (cadrage de L5, Q5) : un appel publié dont l'édition ouverte n'a pas de
	 * date de fin, éteint par une relecture du jour de création de l'édition, et un appel clos depuis un
	 * an, qui revient à chaque anniversaire de sa clôture.
	 */
	public function testReviewDates(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('date sans fin', calendar: Aid::CALL);
		$this->publishAid($eAid);
		$eEdition = $this->createEdition($eAid, NULL);

		$this->assertEquals(Aid::REVIEW_UNDATED, AidLib::getForAdminPage($eAid['id'])['review']);

		AidLib::check($this->getAid($eAid));
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'relue le jour même de la création de l\'édition');

		// Une date de fin posée puis retirée ne rallume rien (CL-28) : `createdAt` ne suit que la création
		$eEdition['endDate'] = '2026-12-31';
		EditionLib::update($eEdition, ['endDate']);
		$eEdition['endDate'] = NULL;
		EditionLib::update($eEdition, ['endDate']);

		$this->setClock('2026-10-25 12:00:00');
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'la date de fin retirée ne rallume pas');

		$this->createEdition($eAid, NULL);
		$this->assertEquals(Aid::REVIEW_UNDATED, AidLib::getForAdminPage($eAid['id'])['review'], 'une nouvelle édition sans date de fin la rallume');

		// Close depuis un an
		$this->setClock(self::NOW);

		$eAid = $this->createAid('date close', calendar: Aid::CALL);
		$this->publishAid($eAid);
		$this->createEdition($eAid, '2025-10-23');

		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'close depuis moins d\'un an');

		$this->setClock('2026-10-23 12:00:00');
		$this->assertEquals(Aid::REVIEW_CLOSED, AidLib::getForAdminPage($eAid['id'])['review'], 'close depuis un an');

		AidLib::check($this->getAid($eAid));
		$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review']);

		$this->setClock('2027-10-23 12:00:00');
		$this->assertEquals(Aid::REVIEW_CLOSED, AidLib::getForAdminPage($eAid['id'])['review'], 'l\'anniversaire suivant');

	}

	public function testReviewQueue(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAidStale = $this->createAid('file d ancienne');
		$this->setClock('2026-04-01 12:00:00');
		AidLib::check($this->getAid($eAidStale));
		$this->setClock(self::NOW);

		$eAidConflict = $this->createAid('file c divergente', verification: Aid::CONFLICT);

		$eAidConflictChecked = $this->createAid('file e divergente relue', verification: Aid::CONFLICT);
		AidLib::check($this->getAid($eAidConflictChecked));

		$eAidSeed = $this->createAid('file b écart');
		AidLib::check($this->getAid($eAidSeed));
		$this->setSeedDifferences($eAidSeed, ['properties' => ['rate' => '50 %']]);

		$eAidArchived = $this->createAid('file f archivée');
		$this->setSeedDifferences($eAidArchived, ['properties' => ['rate' => '50 %']]);
		AidLib::archive($this->getAid($eAidArchived));

		$eAidClosing = $this->createAid('file a clôture');
		$this->publishAid($eAidClosing);
		$this->createEditionClosing($eAidClosing, '2026-11-06');

		$ids = [$eAidClosing['id'], $eAidSeed['id'], $eAidConflict['id'], $eAidStale['id']];
		$idsAll = array_merge($ids, [$eAidConflictChecked['id'], $eAidArchived['id']]);

		// La file entière, page après page, réduite aux aides du test
		$idsQueue = [];
		$reasons = [];

		for($page = 0; ; $page++) {

			[$cAid, $nReview] = AidLib::getReview($page);

			if($cAid->empty()) {
				break;
			}

			foreach($cAid as $eAid) {

				if(in_array($eAid['id'], $idsAll, TRUE)) {
					$idsQueue[] = $eAid['id'];
					$reasons[] = $eAid['review'];
				}

			}

		}

		$this->assertEquals($ids, $idsQueue, 'une aide par raison, dans l\'ordre de la file');
		$this->assertEquals([Aid::REVIEW_CLOSING, Aid::REVIEW_SEED, Aid::REVIEW_CONFLICT, Aid::REVIEW_STALE], $reasons);
		$this->assertEquals($nReview, AidLib::countReview(), 'l\'onglet compte la file');

		$idsDue = AidLib::getForAdmin(new \Search(['text' => self::MARK.' file', 'review' => Aid::REVIEW_DUE]), 0)[0]->getIds();
		sort($idsDue);

		$idsSorted = $ids;
		sort($idsSorted);

		$this->assertEquals($idsSorted, $idsDue, 'le filtre de la liste dit la file');

		foreach([$eAidConflictChecked, $eAidArchived] as $eAid) {
			$this->assertEquals(NULL, AidLib::getForAdminPage($eAid['id'])['review'], 'hors de la file');
		}

		// La rangée Relecture n'appelle un geste que sur une aide de la file
		foreach($ids as $id) {
			$this->assertTrue(in_array('check', AidLib::getForAdminPage($id)['alerts'], TRUE), 'dans la file');
		}

		$eAidNever = $this->createAid('file g jamais relue');
		$this->assertTrue(AidLib::getForAdminPage($eAidNever['id'])['reviewNever']);
		$this->assertFalse(in_array('check', AidLib::getForAdminPage($eAidNever['id'])['alerts'], TRUE), 'jamais relue, hors de la file');

		// Un geste de la colonne revient sur l'aide qui suit, celle qui précède en fin de file, aucune seule
		$cAid = new \Collection();

		foreach($ids as $id) {
			$cAid[$id] = new Aid(['id' => $id]);
		}

		$this->assertEquals(['view' => 'review', 'page' => 2, 'reason' => NULL, 'next' => $ids[2]], AidLib::getReviewQueue($cAid, new Aid(['id' => $ids[1]]), 2, 'review'), 'la suivante');
		$this->assertEquals($ids[2], AidLib::getReviewQueue($cAid, new Aid(['id' => $ids[3]]), 0, 'review')['next'], 'la précédente pour la dernière');
		$this->assertEquals($ids[0], AidLib::getReviewQueue($cAid, new Aid(['id' => $eAidNever['id']]), 0, 'review')['next'], 'hors de la page, la première');
		$this->assertEquals(NULL, AidLib::getReviewQueue(new \Collection([$ids[0] => new Aid(['id' => $ids[0]])]), new Aid(['id' => $ids[0]]), 0, 'review')['next'], 'seule dans la file');

		// Ouverte en page pleine depuis la file (origin=review), l'aide prépare son retour dans la file ;
		// ouverte ailleurs, aucun
		$eAidPage = AidLib::getForAdminPage($ids[0], 0);
		$this->assertEquals('aid', $eAidPage['queue']['view']);
		$this->assertEquals(0, $eAidPage['queue']['page']);
		$this->assertTrue($eAidPage['queue']['next'] !== NULL and $eAidPage['queue']['next'] !== $ids[0], 'une autre aide de la file');
		$this->assertEquals(NULL, AidLib::getForAdminPage($ids[0])['queue']);

	}

	/**
	 * « Confirmer sur la source officielle » pose la confiance avec la relecture, et seulement sur une
	 * aide dont la source officielle répond.
	 */
	public function testConfirm(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('confirmée', verification: Aid::CONFLICT);
		$this->assertFalse($this->getAid($eAid)->acceptConfirm(), 'sans source officielle');

		$eSource = $this->createSource($eAid, Source::PRIMARY);
		$this->assertTrue($this->getAid($eAid)->assertConfirm());

		// Le décor pose l'état que la veille écrit, qu'aucun formulaire ne saisit.
		$eSource['status'] = Source::DEAD;
		SourceLib::update($eSource, ['status']);
		$this->assertFalse($this->getAid($eAid)->acceptConfirm(), 'source officielle morte');

		$eSource['status'] = Source::ALIVE;
		SourceLib::update($eSource, ['status']);

		AidLib::confirm($this->getAid($eAid));

		$eAid = $this->getAid($eAid);
		$this->assertEquals(Aid::PRIMARY, $eAid['verification']);
		$this->assertEquals('2026-10-22', $eAid['checkedAt']);
		$this->assertEquals(\user\ConnectionLib::getOnline()['id'], $eAid['checkedBy']['id'], 'confirmée par qui l\'a lue');
		$this->assertFalse($eAid->acceptConfirm(), 'déjà vérifiée');

		$eAidArchived = $this->createAid('confirmée archivée', verification: Aid::TO_CHECK);
		$this->createSource($eAidArchived, Source::PRIMARY);
		AidLib::archive($this->getAid($eAidArchived));
		$this->assertFalse($this->getAid($eAidArchived)->acceptConfirm(), 'archivée');

		$this->setUser();
		$this->assertFalse($this->getAid($eAidArchived)->assertConfirm(), 'hors de l\'équipe');

	}

	/**
	 * La case « L'organisme ne publie pas de taux » remplace le taux pour publier, et la règle « publiée
	 * ⇒ taux ou case » se juge sur les deux valeurs postées.
	 */
	public function testRateUnpublished(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('sans taux', rate: NULL);
		$this->createSource($eAid, Source::PRIMARY);

		$this->assertEquals(FALSE, $this->getAid($eAid)['rateUnpublished'], 'une aide neuve publie un taux');
		$this->assertFalse($this->getAid($eAid)->acceptPublish());

		// La fiche marque ce qui manque là où il se saisit : le taux sur sa rangée, pas sur « Aux structures »
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals([Aid::MISSING_RATE], $eAidPage['publishMissing']);
		$this->assertTrue(in_array('rate', $eAidPage['alerts'], TRUE), 'la rangée du taux');
		$this->assertFalse(in_array('display', $eAidPage['alerts'], TRUE), 'pas la rangée « Aux structures »');

		// Une source qui manque n'a pas de rangée : « Aux structures » la dit
		$eAidSourceless = $this->createAid('sans source');
		$eAidPage = AidLib::getForAdminPage($eAidSourceless['id']);
		$this->assertEquals([Aid::MISSING_SOURCE], $eAidPage['publishMissing']);
		$this->assertEquals(['display'], $eAidPage['alerts'], 'la source ; jamais relue, l\'aide n\'est pas dans la file');

		$eAid = $this->getAid($eAid);
		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['rate' => '', 'rateUnpublished' => '1']), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));
		AidLib::update($eAid, AidLib::getPropertiesUpdate('admin'));

		$eAid = $this->getAid($eAid);
		$this->assertTrue($eAid['rateUnpublished']);
		$this->assertEquals([], $eAid->getPublishMissing(), 'la case tient lieu de taux');
		$this->assertFalse(in_array('rate', AidLib::getForAdminPage($eAid['id'])['alerts'], TRUE), 'la rangée du taux n\'attend plus rien');

		// La liste propose « Publier » depuis la roue de chaque ligne (AdminUi::getAidGear()) : sa
		// sélection porte ce que la règle lit.
		[$cAid] = AidLib::getForAdmin(new \Search(['text' => $eAid['name'], 'status' => Aid::DRAFT]), 0);
		$this->assertEquals([$eAid['id']], $cAid->getIds());
		$this->assertTrue($cAid->first()->acceptPublish(), 'la liste lit la case');

		// Une case décochée n'est pas postée : build() la lit à FALSE
		$eAidDraft = $this->getAid($eAid);
		$fw = new \FailWatch();
		$eAidDraft->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['rate' => '']), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'un brouillon se passe de taux et de case');
		$this->assertEquals(FALSE, $eAidDraft['rateUnpublished'], 'la case non postée vaut FALSE');

		AidLib::publish($eAid);

		// Décochée sur l'aide publiée, qui resterait sans rien
		$eAid = $this->getAid($eAid);
		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['rate' => '']), new \PropertyList('update'));
		$this->assertTrue($fw->has('Aid::rateUnpublished.published'), 'décochée sur une aide publiée sans taux');

		$eAid = $this->getAid($eAid);
		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['rate' => '30 %', 'rateUnpublished' => '1']), new \PropertyList('update'));
		$this->assertTrue($fw->has('Aid::rateUnpublished.rate'), 'un taux saisi dément la case');

		$eAid = $this->getAid($eAid);
		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['rate' => '30 %']), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'le taux remplace la case, lu : '.json_encode($fw->get()));

	}

	/**
	 * « Reprendre la fiche » construit l'écart en base par build(), comme la page : un organisme, un
	 * ensemble et des conditions, puis ce que la liste de l'équipe refuse.
	 */
	public function testSeedApply(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eOrganizationOther = $this->getOrganization(self::MARK.' autre');

		$eAid = $this->createAid('écart repris');
		AidLib::check($this->getAid($eAid));

		$differences = [
			'properties' => [
				'organization' => $eOrganizationOther['id'],
				'labels' => [Aid::AB],
				'conditions' => '<p>Être installé depuis moins de cinq ans.</p>',
			],
			'children' => [
				'Area' => ['added' => [['department' => '53']], 'removed' => []],
			],
			'held' => FALSE,
		];

		$this->setSeedDifferences($eAid, $differences);

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertFalse($eAidPage->acceptCheck(), 'un écart ne se relit pas d\'un « Marquer relue »');
		$this->assertTrue(in_array('check', $eAidPage['alerts'], TRUE), 'un écart fait entrer l\'aide dans la file');
		$this->assertEquals(Aid::REVIEW_SEED, $eAidPage['review']);
		$this->assertEquals($eOrganizationOther['name'], $eAidPage['cOrganizationSeed'][$eOrganizationOther['id']]['name'], 'la revue nomme l\'organisme de la fiche');

		$this->applySeed($eAid);

		$eAid = $this->getAid($eAid);
		$this->assertEquals($eOrganizationOther['id'], $eAid['organization']['id']);
		$this->assertEquals([Aid::AB], $eAid['labels']->values());
		$this->assertTrue(str_contains($eAid['conditions'], '<p>Être installé depuis moins de cinq ans.</p>'), 'les conditions passent par l\'éditeur');
		$this->assertEquals(NULL, $eAid['seedDifferences'], 'l\'écart est tranché');
		$this->assertEquals(0, Area::model()->whereAid($eAid)->count(), 'les listes se reprennent à la main');
		$this->assertFalse($eAid->acceptSeed());
		$this->assertTrue($eAid->acceptCheck());

		// Un écart de lignes filles seules n'a rien à reprendre
		$this->setSeedDifferences($eAid, ['children' => ['Area' => ['added' => [['department' => '53']], 'removed' => []]]]);
		$this->assertTrue($this->getAid($eAid)->acceptSeed());
		$this->assertFalse($this->getAid($eAid)->acceptSeedApply(), 'rien à reprendre');

		// Ce que la liste de l'équipe refuse
		$this->setSeedDifferences($eAid, ['properties' => ['name' => '']]);
		$this->assertTrue($this->applySeed($eAid)->has('Aid::name.check'), 'un nom vide');

		// Un écart qui retire le taux d'une aide publiée sans la case
		$this->publishAid($eAid);
		$this->setSeedDifferences($eAid, ['properties' => ['rate' => NULL]]);
		$this->assertTrue($this->applySeed($eAid)->has('Aid::rateUnpublished.published'), 'publiée sans taux ni case');
		$this->assertEquals('30 %', $this->getAid($eAid)['rate']);

		// Un écart changé par une reprise entre l'affichage et le geste : la page lit au clic le nouvel
		// écart, le geste poste l'empreinte de celui qu'on a lu
		$this->setSeedDifferences($eAid, ['properties' => ['rate' => '20 %']]);

		$fingerprint = AidLib::getForAdminPage($eAid['id'])['seedFingerprint'];

		$this->setSeedDifferences($eAid, ['properties' => ['rate' => '25 %']]);

		$this->assertTrue($this->applySeed($eAid, $fingerprint)->has('Aid::seedDifferences.changed'));
		$this->assertEquals('30 %', $this->getAid($eAid)['rate'], 'rien n\'est écrit');
		$this->assertEquals('25 %', $this->getAid($eAid)['seedDifferences']['properties']['rate'], 'l\'écart reste à trancher');

		// Changé vers une valeur que la liste refuserait : le refus dit l'écart changé, pas la valeur
		$fingerprint = AidLib::getForAdminPage($eAid['id'])['seedFingerprint'];
		$this->setSeedDifferences($eAid, ['properties' => ['rate' => NULL]]);

		$fw = $this->applySeed($eAid, $fingerprint);
		$this->assertTrue($fw->has('Aid::seedDifferences.changed'));
		$this->assertFalse($fw->has('Aid::rateUnpublished.published'), 'pas le refus d\'un taux jamais affiché');

		$this->setUser();
		$this->assertFalse($this->getAid($eAid)->assertSeed(), 'hors de l\'équipe');

	}

	// L'écart revient de la colonne JSON avec ses propriétés dans l'ordre de MySQL : la revue les montre
	// dans celui du formulaire.
	public function testSeedPropertiesOrder(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('écart ordonné');

		$this->setSeedDifferences($eAid, ['properties' => [
			'notes' => 'Relevé',
			'name' => self::MARK.' autre nom',
			'conditions' => '<p>Être installé.</p>',
			'floor' => 5000.0,
			'organization' => $this->getOrganization(self::MARK.' autre')['id'],
		]]);

		$this->assertTrue(array_keys(AidLib::getForAdminPage($eAid['id'])['seedProperties']) === ['name', 'organization', 'floor', 'conditions', 'notes'], 'l\'ordre du formulaire');

	}

	// Un côté de l'écart sans département ne se dit national que sur une aide nationale ou PAC, la seule
	// que la liste d'une structure montre alors partout.
	public function testSeedNational(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('écart national');

		$this->setSeedDifferences($eAid, ['children' => ['Area' => ['added' => [['department' => '53']], 'removed' => []]]]);
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(['aid' => FALSE, 'seed' => FALSE], $eAidPage['seedNational'], 'régionale sans département, la fiche en ajoute un');
		$this->assertEquals([], $eAidPage['seedChildren']['Area']['aid'], 'un côté vide');

		$this->setSeedDifferences($eAid, ['properties' => ['level' => Aid::NATIONAL], 'children' => ['Area' => ['added' => [['department' => '53']], 'removed' => []]]]);
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(['aid' => FALSE, 'seed' => FALSE], $eAidPage['seedNational'], 'la fiche la dit nationale, mais bornée à la Mayenne');

		// Sans build() : il ne vérifierait que le code de département, pris ici dans la liste officielle.
		AreaLib::create(new Area(['aid' => $eAid, 'department' => '29']));

		// Chaque côté montre toute sa liste, pas seulement ce qui diffère
		$this->setSeedDifferences($eAid, ['children' => ['Area' => ['added' => [['department' => '53']], 'removed' => []]]]);
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals([['key' => ['department' => '29'], 'changed' => FALSE]], $eAidPage['seedChildren']['Area']['aid'], 'l\'aide reste bornée au Finistère');
		$this->assertEquals([['key' => ['department' => '29'], 'changed' => FALSE], ['key' => ['department' => '53'], 'changed' => TRUE]], $eAidPage['seedChildren']['Area']['seed'], 'la fiche ajoute la Mayenne');

		$this->setSeedDifferences($eAid, ['properties' => ['level' => Aid::NATIONAL], 'children' => ['Area' => ['added' => [], 'removed' => [['department' => '29']]]]]);
		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$this->assertEquals(['aid' => FALSE, 'seed' => TRUE], $eAidPage['seedNational'], 'la fiche retire le Finistère');
		$this->assertEquals([['key' => ['department' => '29'], 'changed' => TRUE]], $eAidPage['seedChildren']['Area']['aid']);
		$this->assertEquals([], $eAidPage['seedChildren']['Area']['seed']);

		// Les autres listes filles suivent la même règle, et la revue nomme aussi les organismes communs.
		// L'écart revient de la colonne JSON avec ses clés dans l'ordre de MySQL, `role` avant
		// `organization` : une ligne retirée doit retrouver celle de l'aide.
		$eOrganizationCofunder = $this->getOrganization(self::MARK.' cofinanceur');
		$eOrganizationOther = $this->getOrganization(self::MARK.' autre');

		// Sans build() : il vérifierait l'organisme et le rôle choisis dans le panneau, ici deux lignes
		// fixées par le décor, que l'écart doit retrouver.
		AidOrganizationLib::create(new AidOrganization(['aid' => $eAid, 'organization' => $eOrganizationCofunder, 'role' => AidOrganization::COFUNDER]));
		AidOrganizationLib::create(new AidOrganization(['aid' => $eAid, 'organization' => $eAid['organization'], 'role' => AidOrganization::FUNDER]));

		$keyCommon = ['organization' => $eAid['organization']['id'], 'role' => AidOrganization::FUNDER];
		$keyRemoved = ['organization' => $eOrganizationCofunder['id'], 'role' => AidOrganization::COFUNDER];
		$keyAdded = ['organization' => $eOrganizationOther['id'], 'role' => AidOrganization::INSTRUCTOR];

		$this->setSeedDifferences($eAid, ['children' => ['AidOrganization' => [
			'added' => [['role' => $keyAdded['role'], 'organization' => $keyAdded['organization']]],
			'removed' => [['role' => $keyRemoved['role'], 'organization' => $keyRemoved['organization']]],
		]]]);

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$cofunderFirst = ($eOrganizationCofunder['id'] < $eAid['organization']['id']);

		$this->assertTrue($eAidPage['seedChildren']['AidOrganization']['aid'] === ($cofunderFirst ?
			[['key' => $keyRemoved, 'changed' => TRUE], ['key' => $keyCommon, 'changed' => FALSE]] :
			[['key' => $keyCommon, 'changed' => FALSE], ['key' => $keyRemoved, 'changed' => TRUE]]), 'l\'aide garde son financeur, le cofinanceur part');

		$this->assertTrue(in_array(['key' => $keyCommon, 'changed' => FALSE], $eAidPage['seedChildren']['AidOrganization']['seed'], TRUE), 'le financeur reste commun');
		$this->assertTrue(in_array(['key' => $keyAdded, 'changed' => TRUE], $eAidPage['seedChildren']['AidOrganization']['seed'], TRUE), 'la fiche ajoute l\'instructeur');
		$this->assertEquals(2, count($eAidPage['seedChildren']['AidOrganization']['seed']), 'le cofinanceur n\'est plus dans la fiche');

		foreach([$eAid['organization']['id'], $eOrganizationCofunder['id'], $eOrganizationOther['id']] as $organization) {
			$this->assertTrue($eAidPage['cOrganizationSeed']->offsetExists($organization), 'chaque organisme est nommé');
		}

		// Deux éditions communes se lisent dans le même ordre des deux côtés
		$this->createEditionClosing($eAid, '2026-12-31');
		// Sans build() : il vérifierait le nom, l'état, les deux dates et leur ordre, que le décor pose valides.
		EditionLib::create(new Edition(['aid' => $eAid, 'name' => 'Appel 2027', 'startDate' => '2027-09-01', 'endDate' => '2027-10-31', 'state' => Edition::OPEN]));

		$this->setSeedDifferences($eAid, ['children' => ['Edition' => [
			'added' => [['state' => Edition::CLOSED, 'endDate' => '2025-10-31', 'startDate' => '2025-09-01']],
			'removed' => [],
		]]]);

		$eAidPage = AidLib::getForAdminPage($eAid['id']);
		$editionsCommon = fn(array $lines) => array_values(array_filter($lines, fn(array $line) => $line['changed'] === FALSE));

		$this->assertTrue($editionsCommon($eAidPage['seedChildren']['Edition']['aid']) === $editionsCommon($eAidPage['seedChildren']['Edition']['seed']), 'les éditions communes dans le même ordre');
		$this->assertEquals(2, count($editionsCommon($eAidPage['seedChildren']['Edition']['aid'])));

	}

	public function testSeedKeep(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$this->assertEquals(
			AidLib::getSeedFingerprint(['properties' => ['rate' => '50 %', 'labels' => [2, 1]], 'children' => ['Area' => ['added' => [['department' => '53'], ['department' => '35']]]], 'held' => FALSE]),
			AidLib::getSeedFingerprint(['children' => ['Area' => ['added' => [['department' => '35'], ['department' => '53']]]], 'properties' => ['labels' => [1, 2], 'rate' => '50 %'], 'held' => FALSE]),
			'l\'empreinte ne dépend pas de l\'ordre'
		);

		$this->assertEquals(
			hash('sha256', json_encode(['children' => [], 'properties' => ['rate' => '50 %']])),
			AidLib::getSeedFingerprint(['properties' => ['rate' => '50 %'], 'children' => [], 'held' => FALSE]),
			'un écart sans la mention garde l\'empreinte qu\'il avait avant la clé held'
		);

		$this->assertDiffer(
			AidLib::getSeedFingerprint(['properties' => ['rate' => '50 %'], 'children' => [], 'held' => FALSE]),
			AidLib::getSeedFingerprint(['properties' => ['rate' => '50 %'], 'children' => [], 'held' => TRUE])
		);

		$eAid = $this->createAid('écart gardé');
		AidLib::check($this->getAid($eAid));

		$differences = ['properties' => ['rate' => '50 %'], 'children' => [], 'held' => FALSE];
		$this->setSeedDifferences($eAid, $differences);

		// Un écart changé par une reprise entre l'affichage et le geste
		$fingerprint = AidLib::getForAdminPage($eAid['id'])['seedFingerprint'];
		$this->setSeedDifferences($eAid, ['properties' => ['rate' => '45 %']]);

		$eAid = $this->getAid($eAid);

		$fw = new \FailWatch();
		AidLib::keepSeed($eAid, $eAid['seedDifferences'], $fingerprint);
		$this->assertTrue($fw->has('Aid::seedDifferences.changed'));
		$this->assertEquals(NULL, $this->getAid($eAid)['seedKept'], 'rien n\'est gardé');
		$this->assertEquals('45 %', $this->getAid($eAid)['seedDifferences']['properties']['rate'], 'l\'écart reste à trancher');

		$this->setSeedDifferences($eAid, $differences);

		$eAid = $this->getAid($eAid);

		$fw = new \FailWatch();
		AidLib::keepSeed($eAid, $eAid['seedDifferences'], AidLib::getForAdminPage($eAid['id'])['seedFingerprint']);
		$this->assertTrue($fw->ok());

		$eAid = $this->getAid($eAid);
		$this->assertEquals('30 %', $eAid['rate'], 'l\'aide reste telle quelle');
		$this->assertEquals(NULL, $eAid['seedDifferences']);
		$this->assertEquals(AidLib::getSeedFingerprint($differences), $eAid['seedKept']);

	}

	// Comme la page : l'écart lu en base, construit, puis écrit par le Lib avec l'empreinte de l'écart
	// affiché, celui qu'on vient de lire faute d'autre.
	private function applySeed(Aid $eAid, ?string $fingerprint = NULL): \FailWatch {

		$eAid = $this->getAid($eAid);
		$differences = $eAid['seedDifferences'];
		$fingerprint ??= AidLib::getSeedFingerprint($differences);

		$fw = new \FailWatch();

		if(AidLib::getSeedFingerprint($differences) !== $fingerprint) {
			Aid::fail('seedDifferences.changed');
			return $fw;
		}

		$eAid->build(AidLib::getPropertiesFromSeed($differences), AidLib::getInputFromSeed($eAid, $differences), new \PropertyList('update'));

		if($fw->ok()) {
			AidLib::updateFromSeed($eAid, $differences, $fingerprint);
		}

		return $fw;

	}

	// Le décor pose l'écart qu'une reprise écrit, dans sa forme (ImportLib::getDifferences()) : aucun
	// formulaire ne le saisit.
	private function setSeedDifferences(Aid $eAid, array $differences): void {

		$eAid['seedDifferences'] = $differences + ['properties' => [], 'children' => [], 'held' => FALSE];
		AidLib::update($eAid, ['seedDifferences']);

	}

	// Un utilisateur hors de l'équipe.
	private function setUser(): void {
		\user\ConnectionLib::setOnline(new \user\User(['id' => max(\user\UserSetting::$admin) + 1]));
	}

	private function publishAid(Aid $eAid): void {

		$this->createSource($eAid, Source::PRIMARY);
		AidLib::publish($this->getAid($eAid));

	}

	// Le décor pose ce que la veille écrit, qu'aucun formulaire ne saisit, par le chemin de la veille.
	private function setWatch(Source $eSource, array $values): void {

		$eSource = SourceLib::getById($eSource['id']);
		$eSource->merge($values);

		SourceLib::updateWatch($eSource, $eSource['url'], array_keys($values));

	}

	// Le décor pose l'édition sans build(), qui ne vérifierait que ses bornes de saisie : le test veut
	// une date de fin exacte, absente ou déjà passée.
	private function createEdition(Aid $eAid, ?string $endDate): Edition {

		$eEdition = new Edition([
			'aid' => $eAid,
			'name' => 'Appel',
			'startDate' => '2025-01-01',
			'endDate' => $endDate,
			'state' => Edition::OPEN,
		]);

		EditionLib::create($eEdition);

		return $eEdition;

	}

	// Le décor pose l'édition sans build(), qui ne vérifierait que ses bornes de saisie : le test veut
	// une clôture exacte, comptée depuis le jour figé.
	private function createEditionClosing(Aid $eAid, string $endDate): void {

		EditionLib::create(new Edition([
			'aid' => $eAid,
			'name' => 'Appel 2026',
			'startDate' => '2026-09-01',
			'endDate' => $endDate,
			'state' => Edition::OPEN,
		]));

	}

	public function testProductionsEmptied(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->getAid($this->createAid('productions', productions: ['maraichage']));
		$this->assertEquals(['maraichage'], $eAid['productions']);

		// Tout décoché ne poste rien
		$input = $this->getInput([]);
		unset($input['productions']);

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $input, new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));
		$this->assertEquals([], $eAid['productions'], 'aucune production cochée vaut toutes');

		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput(['labels' => [Aid::AB], 'labelsBonus' => [Aid::HVE]]), new \PropertyList('update'));
		$this->assertTrue($eAid['labels']->value(Aid::AB));

		// Les labels tout décochés ne postent rien non plus, et leur colonne nullable revient à NULL
		$fw = new \FailWatch();
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput([]), new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));
		$this->assertEquals(NULL, $eAid['labels'], 'aucun label exigé');
		$this->assertEquals(NULL, $eAid['labelsBonus'], 'aucun label bonifié');

		// Enregistrer le panneau sans toucher aux labels les laisse à NULL, la valeur du semis
		AidLib::update($eAid, AidLib::getPropertiesUpdate('admin'));

		$fw = new \FailWatch();
		$eAid = $this->getAid($eAid);
		$eAid->build(AidLib::getPropertiesUpdate('admin'), $this->getInput([]), new \PropertyList('update'));
		AidLib::update($eAid, AidLib::getPropertiesUpdate('admin'));

		$eAid = $this->getAid($eAid);
		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));
		$this->assertEquals(NULL, $eAid['labels']);
		$this->assertEquals(NULL, $eAid['labelsBonus']);

	}

	public function testSynchronizeTags(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		[$tag1, $tag2, $tag3] = array_map(fn(int $position) => $this->createTag($position)['id'], [1, 2, 3]);

		$eAid = $this->createAid('tags');

		AidTagLib::synchronize($eAid, TagLib::getByIds([$tag1, $tag2], index: 'id'));
		$this->assertEquals([$tag1, $tag2], $this->getTagIds($eAid));

		$eAidTagKept = AidTag::model()->select('id')->whereAid($eAid)->whereTag($tag2)->get();

		AidTagLib::synchronize($eAid, TagLib::getByIds([$tag2, $tag3], index: 'id'));
		$this->assertEquals([$tag2, $tag3], $this->getTagIds($eAid));
		$this->assertTrue(AidTag::model()->whereId($eAidTagKept['id'])->exists(), 'un tag gardé ne se recrée pas');

		AidTagLib::synchronize($eAid, TagLib::getByIds([], index: 'id'));
		$this->assertEquals([], $this->getTagIds($eAid), 'tout décoché retire tous les tags');

	}

	public function testDuplicates(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('doublons');
		$eAidOther = $this->createAid('doublons autre');

		$this->assertDuplicate(fn() => AreaLib::create(new Area(['aid' => $eAid, 'department' => '29'])), 'Area::department.duplicate');
		$this->assertDuplicate(fn() => AidOrganizationLib::create(new AidOrganization(['aid' => $eAid, 'organization' => $eAid['organization'], 'role' => AidOrganization::PAYER])), 'AidOrganization::organization.duplicate');
		$this->assertDuplicate(fn() => AidLinkLib::create(new AidLink(['aid' => $eAid, 'target' => $eAidOther, 'kind' => AidLink::COMPLEMENTS])), 'AidLink::target.duplicate');

		// Le même organisme dans un autre rôle n'est pas un doublon
		$fw = new \FailWatch();
		AidOrganizationLib::create(new AidOrganization(['aid' => $eAid, 'organization' => $eAid['organization'], 'role' => AidOrganization::INSTRUCTOR]));
		$this->assertTrue($fw->ok());

		// Une adresse ne se cite qu'une fois par aide, à la création comme à la modification
		$eSource = $this->createSource($eAid, Source::PRIMARY);
		$eSourceRelay = $this->createSource($eAid, Source::RELAY);

		$fw = new \FailWatch();
		new Source(['aid' => $eAid])->build(SourceLib::getPropertiesCreate(), ['url' => $eSource['url'], 'kind' => Source::RELAY]);
		$this->assertTrue($fw->has('Source::url.duplicate'), 'même adresse à la création');

		$fw = new \FailWatch();
		new Source(['aid' => $eAid])->build(SourceLib::getPropertiesCreate(), ['url' => $eSource['url'].'/', 'kind' => Source::RELAY]);
		$this->assertTrue($fw->has('Source::url.duplicate'), 'la même adresse suivie d\'une barre');

		$fw = new \FailWatch();
		$eSourceRelay->build(SourceLib::getPropertiesUpdate(), ['url' => $eSource['url'], 'kind' => Source::RELAY]);
		$this->assertTrue($fw->has('Source::url.duplicate'), 'même adresse à la modification');

		$fw = new \FailWatch();
		$eSource->build(SourceLib::getPropertiesUpdate(), ['url' => $eSource['url'], 'kind' => Source::PRIMARY]);
		$this->assertTrue($fw->ok(), 'une source garde sa propre adresse');

		$fw = new \FailWatch();
		new Source(['aid' => $eAidOther])->build(SourceLib::getPropertiesCreate(), ['url' => $eSource['url'], 'kind' => Source::RELAY]);
		$this->assertTrue($fw->ok(), 'la même adresse sur une autre aide');

	}

	// Les dispositifs numérotés d'un organisme se suivent dans l'ordre de leur numéro.
	public function testSortNatural(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$ids = [];

		foreach(['tri A10 promotion', 'tri A2 élevage', 'tri A1 installation', 'tri Abattement'] as $name) {
			$ids[$name] = $this->createAid($name)['id'];
		}

		$this->assertEquals(
			[$ids['tri A1 installation'], $ids['tri A2 élevage'], $ids['tri A10 promotion'], $ids['tri Abattement']],
			AidLib::getForAdmin(new \Search(['text' => self::MARK.' tri']), 0)[0]->getIds()
		);

	}

	public function testSearch(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAidBreizh = $this->createAid('recherche bretagne', verification: Aid::CONFLICT);
		AreaLib::create(new Area(['aid' => $eAidBreizh, 'department' => '29']));

		$eAidNational = $this->createAid('recherche nationale', verification: Aid::RELAY);

		$search = fn(array $criteria) => AidLib::getForAdmin(new \Search($criteria + ['text' => self::MARK.' recherche']), 0)[0]->getIds();

		$this->assertEquals([$eAidBreizh['id'], $eAidNational['id']], $search([]), 'le texte trouve les deux, par ordre de nom');
		$this->assertEquals([$eAidBreizh['id']], $search(['region' => '53']), 'le Finistère est en Bretagne');
		$this->assertEquals([$eAidBreizh['id']], $search(['department' => '29']));
		$this->assertEquals([], $search(['department' => '35']));
		$this->assertEquals([$eAidNational['id']], $search(['verification' => Aid::RELAY]));
		$this->assertEquals([], $search(['status' => Aid::PUBLISHED]));

		$figures = AidLib::countForAdmin(new \Search(['text' => self::MARK.' recherche', 'verification' => Aid::RELAY]));

		$this->assertEquals(['conflict' => 1, 'relay' => 1, 'closed' => 0], $figures, 'chaque compteur remplace le critère de vérification posé');

	}

	private function assertDuplicate(\Closure $create, string $fail): void {

		$create();

		$fw = new \FailWatch();
		$create();
		$this->assertTrue($fw->has($fail), $fail.', lu : '.json_encode($fw->get()));

	}

	private function setAdmin(): bool {

		$adminIds = \user\UserSetting::$admin;

		if($this->assertDecorPresent($adminIds !== [], 'un administrateur de Ouvretaferme déclaré dans UserSetting::$admin') === FALSE) {
			return FALSE;
		}

		\user\ConnectionLib::setOnline(\user\UserLib::getById($adminIds[0]));

		return TRUE;

	}

	private function getInput(array $input): array {

		return $input + [
			'name' => self::MARK.' modifiée',
			'organization' => $this->getOrganization()['id'],
			'level' => Aid::REGION,
			'type' => Aid::GRANT,
			'calendar' => Aid::PERMANENT,
			'rate' => '30 %',
			'productions' => [],
			'direct' => '1',
		];

	}

	/**
	 * « À revoir, ses sources divergent » dit sur quoi : la phrase des notes de relevé qui a fait poser la
	 * confiance au semis, prise telle quelle au milieu des autres, qu'elle finisse sur un point ou sur sa
	 * ligne. Sans phrase qui réponde, ou sur une aide d'une autre confiance, rien n'est cité.
	 */
	public function testConflictSentence(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$cases = [
			'phrase au milieu' => [Aid::CONFLICT, "Taux : non publié\n\nPlafond relevé sur le règlement. Bornes d'âge divergentes selon la source. Cumul possible.", "Bornes d'âge divergentes selon la source."],
			'phrase sur sa ligne' => [Aid::CONFLICT, "Plafond relevé sur le règlement\nMontant contradictoire entre la fiche et l'arrêté\nCumul possible", "Montant contradictoire entre la fiche et l'arrêté"],
			'aucune phrase' => [Aid::CONFLICT, "Plafond relevé sur le règlement.", NULL],
			'sans notes' => [Aid::CONFLICT, NULL, NULL],
			'autre confiance' => [Aid::TO_CHECK, "Bornes d'âge divergentes selon la source.", NULL],
		];

		foreach($cases as $case => [$verification, $notes, $expected]) {
			$eAid = $this->createAid('divergence '.$case, verification: $verification, notes: $notes);
			$this->assertEquals($expected, AidLib::getForAdminPage($eAid['id'])['conflictSentence'], $case);
		}

	}

	// Construit comme le panneau de l'équipe : build(), puis le Lib.
	private function createAid(string $name, ?string $rate = '30 %', string $calendar = Aid::PERMANENT, array $productions = [], string $verification = Aid::TO_CHECK, ?string $notes = NULL): Aid {

		$eAid = new Aid();

		$fw = new \FailWatch();

		$eAid->build(AidLib::getPropertiesCreate('admin'), [
			'name' => self::MARK.' '.$name,
			'organization' => $this->getOrganization()['id'],
			'level' => Aid::REGION,
			'type' => Aid::GRANT,
			'calendar' => $calendar,
			'rate' => $rate,
			'productions' => $productions,
			'direct' => '1',
			'notes' => $notes,
		], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));

		// La vérification ne se saisit pas au panneau ; le décor pose celle que le test éprouve.
		$eAid['verification'] = $verification;

		AidLib::create($eAid);

		return $eAid;

	}

	/**
	 * Une aide publiée garde au moins une source officielle, qu'on la retire ou qu'on en change la
	 * nature ; en brouillon, l'équipe en fait ce qu'elle veut.
	 */
	public function testSourcePrimaryKept(): void {

		$this->setClock(self::NOW);

		if($this->setAdmin() === FALSE) {
			return;
		}

		$eAid = $this->createAid('source gardée');
		$eSourcePrimary = $this->createSource($eAid, Source::PRIMARY);
		$eSourceRelay = $this->createSource($eAid, Source::RELAY);
		AidLib::publish($this->getAid($eAid));

		$getSource = fn(Source $eSource) => SourceLib::getById($eSource['id'], SourceLib::getSelectionWrite());

		$this->assertFalse($getSource($eSourcePrimary)->acceptDelete(), 'la seule source officielle d\'une aide publiée');
		$this->assertTrue($getSource($eSourceRelay)->acceptDelete(), 'un relais');

		$fw = new \FailWatch();
		SourceLib::delete($getSource($eSourcePrimary));
		$this->assertTrue($fw->has('Source::deletePrimary'), 'refusé par la Lib, sous verrou');
		$this->assertTrue(Source::model()->whereId($eSourcePrimary)->exists(), 'elle est toujours là');

		$fw = new \FailWatch();
		$getSource($eSourcePrimary)->build(SourceLib::getPropertiesUpdate(), ['url' => $eSourcePrimary['url'], 'kind' => Source::RELAY]);
		$this->assertTrue($fw->has('Source::kind.published'), 'changer sa nature la retire autant');

		$fw = new \FailWatch();
		$eSourceChanged = $getSource($eSourcePrimary);
		$eSourceChanged['kind'] = Source::RELAY;
		SourceLib::update($eSourceChanged, ['kind']);
		$this->assertTrue($fw->has('Source::kind.published'), 'refusé par la Lib, sous verrou');
		$this->assertEquals(Source::PRIMARY, $getSource($eSourcePrimary)['kind'], 'elle est restée officielle');

		// Une seconde source officielle rend la première libre
		$eSourceSecond = $this->createSource($eAid, Source::PRIMARY);
		$this->assertTrue($getSource($eSourcePrimary)->acceptDelete(), 'une autre source officielle reste');

		$fw = new \FailWatch();
		SourceLib::delete($getSource($eSourcePrimary));
		$this->assertTrue($fw->ok());
		$this->assertFalse($getSource($eSourceSecond)->acceptDelete(), 'la seconde est désormais la dernière');

		// En brouillon, rien ne retient la dernière
		AidLib::unpublish($this->getAid($eAid));
		$this->assertTrue($getSource($eSourceSecond)->acceptDelete(), 'une aide en brouillon');

		$fw = new \FailWatch();
		SourceLib::delete($getSource($eSourceSecond));
		$this->assertTrue($fw->ok());
		$this->assertFalse(Source::model()->whereId($eSourceSecond)->exists());

	}

	private function createSource(Aid $eAid, string $kind): Source {

		$eSource = new Source(['aid' => $eAid]);

		$eSource->build(SourceLib::getPropertiesCreate(), [
			'url' => 'https://example.org/'.uniqid(),
			'kind' => $kind,
		]);

		SourceLib::create($eSource);

		return $eSource;

	}

	private function createPiece(int $position): Piece {

		$ePiece = new Piece();

		$fw = new \FailWatch();

		$ePiece->build(PieceLib::getPropertiesCreate(), [
			'fqn' => self::MARK.'-'.$position,
			'name' => self::MARK.' '.$position,
		]);

		$fw->validate();

		PieceLib::create($ePiece);

		return $ePiece;

	}

	// Construit comme la ligne d'ajout de la table des pièces, sans l'écrire.
	private function buildPiece(Aid $eAid, array $input): \FailWatch {

		$fw = new \FailWatch();

		new AidPiece(['aid' => $eAid])->build(AidPieceLib::getPropertiesCreate(), $input + ['moment' => AidPiece::APPLICATION]);

		return $fw;

	}

	private function createAidPiece(Aid $eAid, array $input): AidPiece {

		$eAidPiece = new AidPiece(['aid' => $eAid]);

		$fw = new \FailWatch();
		$eAidPiece->build(AidPieceLib::getPropertiesCreate(), $input + ['moment' => AidPiece::APPLICATION]);
		$fw->validate();

		AidPieceLib::create($eAidPiece);

		return AidPieceLib::getById($eAidPiece['id'], AidPieceLib::getSelectionAdmin());

	}

	// Construit comme le panneau « Modifier la pièce », sans l'écrire. Le panneau poste ce que la pièce
	// porte déjà, sauf ce que le cas change.
	private function buildPieceUpdate(AidPiece $eAidPiece, array $input): \FailWatch {

		$fw = new \FailWatch();

		$eAidPiece->build(AidPieceLib::getPropertiesUpdate(), $input + [
			'moment' => $eAidPiece['moment'],
			'name' => $eAidPiece['name'],
			'note' => $eAidPiece['note'],
			'url' => $eAidPiece['url'],
		]);

		return $fw;

	}

	private function createTag(int $position): Tag {

		$eTag = new Tag();

		$fw = new \FailWatch();

		$eTag->build(TagLib::getPropertiesCreate(), [
			'fqn' => self::MARK.'-'.$position,
			'name' => self::MARK.' '.$position,
			'projects' => [Tag::INVEST],
		]);

		$this->assertTrue($fw->ok(), 'lu : '.json_encode($fw->get()));

		TagLib::create($eTag);

		return $eTag;

	}

	private function getOrganization(string $name = self::MARK): Organization {

		$eOrganization = OrganizationLib::getByName($name);

		if($eOrganization->empty()) {

			$eOrganization->build(OrganizationLib::getPropertiesCreate(), [
				'name' => $name,
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

	private function getTagIds(Aid $eAid): array {
		return AidTag::model()
			->select('tag')
			->whereAid($eAid)
			->sort(['tag' => SORT_ASC])
			->getCollection()
			->getColumnCollection('tag')
			->getIds();
	}

}
?>
