<?php
namespace dashboard;

/**
 * Ce qu'un membre règle de son accueil : l'interrupteur d'une pièce, celui d'un univers entier, le
 * rang d'un bloc et celui d'un univers, et l'effacement quand il quitte la structure.
 *
 * Ce test écrit en base, contrairement à `Contribution.t.php` qui n'éprouve que des tris sur des
 * déclarations fabriquées. Il le fait sous **deux utilisateurs fictifs** très hors des identifiants
 * réels : la table n'a aucune clé étrangère (mesuré le 2026-10-01 sur `SHOW CREATE TABLE`), donc un
 * identifiant qui ne désigne personne y écrit sans rien casser, et aucun membre réel ne voit son
 * accueil changer parce qu'un test a tourné. `finalize()` efface les deux jeux de lignes.
 *
 * Le second utilisateur n'est pas un supplément : l'isolement d'un membre à l'autre est la propriété
 * centrale de la table, et un test qui ne poserait qu'un seul utilisateur la croirait vraie même si
 * `getByMember()` oubliait son `whereUser()`.
 *
 * Les noms de pièces sont **réels** et non inventés : `Preference::properties()` refuse par
 * `replaceCheck` un nom qu'aucun package ne déclare, et c'est exactement ce qu'on veut qu'il fasse.
 *
 * Lancement : dev/test package=dashboard file=Preference
 */
class PreferenceTest extends \Test {

	/**
	 * Le décor, et non la structure 7 : `structure/test/RealStructureSentinelAi.t.php` refuse qu'un test
	 * neuf se monte sur une structure réelle, et la raison vaut ici mot pour mot. Ce fichier éprouve ce
	 * que la préférence intercale dans des listes de pièces, donc il a besoin d'une structure qui porte
	 * boutique, ventes et cultures : F1 les porte toutes (`FixtureAiLib::STRUCTURES`, `features`), et
	 * elle ne bouge pas quand quelqu'un travaille sur les données de la 7.
	 */
	const STRUCTURE = \structure\FixtureAiLib::F1;

	/**
	 * Deux utilisateurs qui ne désignent personne. Très au-delà des identifiants réels, pour qu'une
	 * base qui grandit ne les rejoigne pas : `Days.t.php` prend la même précaution sur une structure.
	 */
	const USER = 4000000001;
	const USER_OTHER = 4000000002;

	protected \structure\Structure $eStructure;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;
	private \Closure $enabledInitial;

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();
		$this->enabledInitial = DashboardSetting::$enabled;

		// L'accueil ouvert pour cette structure, quel que soit le drapeau de l'environnement :
		// `acceptWrite()` le lit, et un drapeau fermé ferait échouer chaque écriture de ce fichier.
		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		$this->connectAs(self::USER);
		$this->forget(self::USER);
		$this->forget(self::USER_OTHER);

	}

	public function finalize(): void {

		$this->forget(self::USER);
		$this->forget(self::USER_OTHER);

		foreach(['selling-bilan', 'website-news'] as $name) {
			\Cache::redis()->delete(DashboardLib::getCacheKey($name, $this->eStructure, new \user\User(['id' => self::USER])));
		}

		\Cache::redis()->delete(DashboardLib::getCacheKey('website-news', $this->eStructure, new \user\User(['id' => self::USER_OTHER])));

		// Les caches sont des propriétés de processus, que rien n'annule à la fin d'une méthode.
		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		DashboardSetting::$enabled = $this->enabledInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	/**
	 * Un membre qui n'a rien réglé n'a aucune ligne, et voit ce que les déclarations promettent. C'est le
	 * cas de toute structure avant le lot : la migration ne reprend aucune donnée précisément parce que
	 * le défaut se calcule à la lecture.
	 */
	public function testNothingSetMeansNoRow(): void {

		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "aucun réglage, aucune ligne");

		$choices = DashboardLib::loadChoices($this->eStructure);

		$this->assertEquals(TRUE, $choices['sections']['commercialisation']['selling-unpaid']['chosen'], "un bloc dont la déclaration vaut TRUE est allumé sans ligne");

	}

	/** L'interrupteur d'une pièce : la ligne naît au premier geste, et le second la met à jour. */
	public function testHiddenWritesOneRowThenUpdatesIt(): void {

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);

		$preferences = PreferenceLib::getByMember($this->eStructure);

		$this->assertEquals(1, count($preferences), "un geste, une ligne");
		$this->assertEquals(TRUE, $preferences['selling-unpaid']['hidden'], "la pièce est éteinte");

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', FALSE);

		$preferences = PreferenceLib::getByMember($this->eStructure);

		$this->assertEquals(1, count($preferences), "le second geste met la ligne à jour au lieu d'en ajouter une");
		$this->assertEquals(FALSE, $preferences['selling-unpaid']['hidden'], "la pièce est rallumée");

	}

	/**
	 * `updatedAt` suit chaque écriture, et pas seulement la création.
	 *
	 * Le `SPECIAL(now)` du schéma ne pose la valeur qu'à l'insertion : sans la ligne que `writeAll()`
	 * ajoute, une préférence basculée dix fois gardait l'heure de sa création. L'horloge est figée par
	 * `setClock()`, qui règle d'un même geste `\Clock` et le `NOW()` de la connexion MySQL, donc les deux
	 * instants sont ceux que ce test choisit et non ceux du jour où il tourne.
	 */
	public function testUpdatedAtFollowsEachWrite(): void {

		$this->setClock('2026-06-15 10:00:00');

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);

		$created = PreferenceLib::getByName($this->eStructure, 'selling-unpaid')['updatedAt'];

		$this->assertEquals('2026-06-15 10:00:00', $created, "la création pose l'heure");

		$this->setClock('2026-06-15 11:30:00');

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', FALSE);

		$updated = PreferenceLib::getByName($this->eStructure, 'selling-unpaid')['updatedAt'];

		$this->assertEquals('2026-06-15 11:30:00', $updated, "la mise à jour la rafraîchit, et non ".$created);

	}

	/**
	 * Un nom qu'aucun package ne déclare est refusé par `build()`, donc par la même porte que l'API.
	 * C'est `replaceCheck` sur `name` qui le tient, et non une garde dans le Lib.
	 */
	public function testUnknownNameIsRefused(): void {

		$fw = new \FailWatch();

		$leve = FALSE;

		// `writeAll()` conclut son propre `FailWatch` par `validate()`, qui lève une `FailAction` : c'est
		// l'entrée dans la chaîne d'erreur du cadriciel, et non une sortie. L'attraper ici est ce qui
		// permet d'éprouver le refus sans la couche ajax, qui est seule à savoir la rendre à l'écran.
		try {
			PreferenceLib::updateHidden($this->eStructure, 'piece-inventee', TRUE);
		} catch(\FailAction) {
			$leve = TRUE;
		}

		$this->assertEquals(TRUE, $leve, "un nom qu'aucun package ne déclare est refusé");
		$this->assertEquals(TRUE, $fw->has('Preference::name.check'), "le refus porte le nom que Alert.u.php traduit, ".json_encode($fw->get()));
		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "et rien n'est écrit");

	}

	/**
	 * Le bilan et le chiffre de la semaine ne se règlent pas (L62 pour le second) : le panneau n'offre
	 * que des blocs, et un réglage posté sur leur nom est refusé par la même porte qu'un nom inconnu,
	 * sans rien écrire.
	 */
	public function testFixedSlotsAreNotSettable(): void {

		$this->assertEquals(['sections'], array_keys(DashboardLib::loadChoices($this->eStructure)), "le panneau n'a d'interrupteurs que pour les blocs");

		foreach(['selling-bilan', 'selling-revenue'] as $name) {

			$fw = new \FailWatch();

			$leve = FALSE;

			// Même raison que dans `testUnknownNameIsRefused()` : `validate()` lève une `FailAction`.
			try {
				PreferenceLib::updateHidden($this->eStructure, $name, TRUE);
			} catch(\FailAction) {
				$leve = TRUE;
			}

			$this->assertEquals(TRUE, $leve, "éteindre ".$name." est refusé");
			$this->assertEquals(TRUE, $fw->has('Preference::name.check'), "par la vérification du nom, ".json_encode($fw->get()));

		}

		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "et rien n'est écrit");

	}

	/**
	 * Une ligne écrite au nom d'une pièce quand son emplacement se réglait encore ne la cache pas : il
	 * n'a plus d'interrupteur pour la rallumer. Le chiffre de la semaine se réglait jusqu'au L62, et des
	 * lignes `hidden` restent en base à son nom. Le bilan porte l'épreuve, parce qu'il partage la règle
	 * des emplacements fixes et se lit au cache, où `speakBilan()` pose sa donnée : le décor n'a aucune
	 * vente qui ferait parler le chiffre de la semaine.
	 */
	public function testAnOldRowDoesNotHideAFixedSlot(): void {

		$this->speakBilan();

		$before = DashboardLib::loadContributions($this->eStructure, slots: [DashboardSetting::BILAN]);

		if($this->assertDecorPresent(isset($before[DashboardSetting::BILAN]['selling-bilan']), 'le bilan de la structure #'.self::STRUCTURE.' parle') === FALSE) {
			return;
		}

		// Écrite sans `PreferenceLib`, qui la refuse désormais par `Preference::name.check` : c'est la
		// ligne qu'un membre a pu écrire quand le nom passait encore ce contrôle, et que le test veut
		// telle quelle.
		$ePreference = new Preference([
			'user' => new \user\User(['id' => self::USER]),
			'structure' => $this->eStructure,
			'name' => 'selling-bilan',
			'hidden' => TRUE
		]);

		Preference::model()->insert($ePreference);

		$this->assertEquals(array_keys($before[DashboardSetting::BILAN]), array_keys(DashboardLib::loadContributions($this->eStructure, slots: [DashboardSetting::BILAN])[DashboardSetting::BILAN] ?? []), "la ligne ne cache pas le bilan");

	}

	/**
	 * L'univers s'éteint par ses blocs et non par une ligne qui le nommerait : une ligne par bloc, et
	 * l'état de l'univers se relit dessus. C'est ce qui empêche les deux lectures de divorcer.
	 */
	public function testSectionWritesOneRowPerBlock(): void {

		$choices = DashboardLib::loadChoices($this->eStructure);
		$blocks = array_keys($choices['sections']['commercialisation']);

		PreferenceLib::updateSection($this->eStructure, $blocks, TRUE);

		$preferences = PreferenceLib::getByMember($this->eStructure);

		$this->assertEquals(count($blocks), count($preferences), "une ligne par bloc de l'univers, et aucune pour l'univers");

		foreach($blocks as $block) {
			$this->assertEquals(TRUE, $preferences[$block]['hidden'], "le bloc ".$block." est éteint");
		}

		$choices = DashboardLib::loadChoices($this->eStructure);

		foreach($blocks as $block) {
			$this->assertEquals(FALSE, $choices['sections']['commercialisation'][$block]['chosen'], "l'univers éteint se relit sur ".$block);
		}

	}

	/** Le rang d'un bloc, et le refus d'une liste qui ne dit plus ce que le membre voit. */
	public function testPositionsAreWrittenAndAStaleListIsRefused(): void {

		$choices = DashboardLib::loadChoices($this->eStructure);
		$blocks = array_keys($choices['sections']['commercialisation']);

		$reversed = array_reverse($blocks);

		PreferenceLib::updatePositions($this->eStructure, $blocks, $reversed);

		$preferences = PreferenceLib::getByMember($this->eStructure);

		foreach($reversed as $rank => $block) {
			$this->assertEquals($rank, $preferences[$block]['position'], "le rang de ".$block);
		}

		$sorted = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']['commercialisation']);

		$this->assertEquals($reversed, $sorted, "l'univers se relit dans le rang que le membre a posé");

		$fw = new \FailWatch();

		PreferenceLib::updatePositions($this->eStructure, $blocks, array_slice($reversed, 1));

		$this->assertEquals(TRUE, $fw->ko(), "une liste incomplète est refusée");
		$this->assertEquals(TRUE, $fw->has('Preference::positionsIncomplete'), "le refus porte le nom que Alert.u.php traduit, ".json_encode($fw->get()));

	}

	/**
	 * Sans rang posé par le membre, la position déclarée range la section avant l'ordre des packages :
	 * `planning` passe avant `series` dans `\Package::getList()`, et ses ventes précéderaient sinon les
	 * interventions de la semaine, que les récoltes de la semaine, du même package, précèdent.
	 */
	public function testDeclaredPositionOrdersASectionNeverRanked(): void {

		$expected = ['planning-harvest', 'series-week', 'planning-sales'];

		// Le décor n'a aucune série, et la production de l'accueil ne s'ouvre qu'à une structure qui a
		// planifié la saison (`Structure::acceptSeasonPlanned()`). Sa réponse, gardée par structure, se
		// pose ici plutôt qu'une série dans le décor, qu'aucune transaction ne retirerait après le test.
		$seasonPlanned = new \ReflectionProperty(\structure\Structure::class, 'seasonPlanned');
		$seasonPlannedInitial = $seasonPlanned->getValue();
		$seasonPlanned->setValue(NULL, [self::STRUCTURE => TRUE] + $seasonPlannedInitial);

		$blocks = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']['production'] ?? []);

		$seasonPlanned->setValue(NULL, $seasonPlannedInitial);

		if($this->assertDecorPresent(array_diff($expected, $blocks) === [], 'la structure #'.self::STRUCTURE.' montre au membre du test les trois blocs de la production') === FALSE) {
			return;
		}

		$this->assertEquals($expected, array_values(array_intersect($blocks, $expected)), "la position déclarée ne range pas la section");

	}

	/**
	 * Un membre qui n'a rangé aucun univers les voit dans l'ordre des onglets : c'est l'accueil d'avant
	 * L39, à l'identique.
	 */
	public function testSectionsFollowTheTabsWhenNothingIsSet(): void {

		$sections = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']);

		$this->assertEquals(array_values(array_intersect(DashboardSetting::SECTIONS, $sections)), $sections, "les univers dans l'ordre de DashboardSetting::SECTIONS");

	}

	/**
	 * Le rang posé range l'accueil et le panneau, et chaque bloc reste dans son univers, dans l'ordre
	 * qu'il y avait : le rang d'un univers déplace le groupe, il ne mêle pas deux bandes.
	 */
	public function testSectionsFollowTheRankSet(): void {

		$choices = DashboardLib::loadChoices($this->eStructure);
		$sections = array_keys($choices['sections']);

		if($this->assertDecorPresent(count($sections) >= 2, 'la structure #'.self::STRUCTURE.' montre au moins deux univers au membre du test') === FALSE) {
			return;
		}

		$reversed = array_reverse($sections);

		PreferenceLib::updatePositions($this->eStructure, $sections, $reversed);

		$choicesRanked = DashboardLib::loadChoices($this->eStructure);

		$this->assertEquals($reversed, array_keys($choicesRanked['sections']), "le panneau suit le rang posé");

		foreach($sections as $section) {
			$this->assertEquals(array_keys($choices['sections'][$section]), array_keys($choicesRanked['sections'][$section]), "les blocs de ".$section." gardent leur ordre");
		}

		$sectionsLoaded = [];

		foreach(DashboardLib::load($this->eStructure) as $entry) {
			$sectionsLoaded[$entry['declaration']['section']] = TRUE;
		}

		$this->assertEquals(array_values(array_intersect($reversed, array_keys($sectionsLoaded))), array_keys($sectionsLoaded), "l'accueil suit le rang posé");

	}

	/**
	 * Éteindre un univers écrit ses blocs et ne touche pas à son rang : sa carte garde sa place dans la
	 * rangée, avec sa poignée, et ses blocs la leur dans les colonnes.
	 */
	public function testAHiddenSectionKeepsItsRank(): void {

		$sections = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']);

		if($this->assertDecorPresent(count($sections) >= 2, 'la structure #'.self::STRUCTURE.' montre au moins deux univers au membre du test') === FALSE) {
			return;
		}

		$reversed = array_reverse($sections);

		PreferenceLib::updatePositions($this->eStructure, $sections, $reversed);

		$choices = DashboardLib::loadChoices($this->eStructure);

		PreferenceLib::updateSection($this->eStructure, array_keys($choices['sections'][$reversed[0]]), TRUE);

		$choicesHidden = DashboardLib::loadChoices($this->eStructure);

		$this->assertEquals($reversed, array_keys($choicesHidden['sections']), "l'univers éteint reste à son rang");
		$this->assertEquals([], array_keys(array_filter($choicesHidden['sections'][$reversed[0]], fn(array $choice): bool => $choice['chosen'])), "l'univers est bien éteint");

		// En réglage, l'univers éteint garde sa carte, estompée, et sa poignée : `doUpdatePositions`
		// range tous les univers, éteints compris (L43). Ses blocs restent dans les colonnes, à leur
		// place, éteints (L45, lot e).
		$blocks = DashboardLib::load($this->eStructure, setting: TRUE);

		$sectionsChosen = DashboardLib::getSectionsChosen($blocks);

		$this->assertFalse($sectionsChosen[$reversed[0]], "l'état de l'univers se déduit de ses blocs éteints");
		$this->assertTrue($sectionsChosen[$reversed[1]], "et celui d'un univers allumé aussi");

		$hidden = new DashboardUi()->getSectionCard($this->eStructure, $reversed[0], $sectionsChosen[$reversed[0]]);
		$shown = new DashboardUi()->getSectionCard($this->eStructure, $reversed[1], $sectionsChosen[$reversed[1]]);

		$this->assertTrue(str_contains($hidden, 'dashboard-setting-section is-off'), "un univers éteint s'estompe");
		$this->assertTrue(str_contains($hidden, 'util-drag-handle'), "un univers éteint garde sa poignée");
		$this->assertFalse(str_contains($shown, 'is-off'), "un univers allumé ne s'estompe pas");
		$this->assertTrue(str_contains($shown, 'util-drag-handle'), "un univers allumé a sa poignée");

		$html = new DashboardUi()->getBoard($this->eStructure, $blocks, [], DashboardLib::getToday(), TRUE, $sectionsChosen);

		foreach($blocks as $block => $entry) {

			if($entry['declaration']['section'] === $reversed[0]) {
				$this->assertEquals(1, preg_match('#id="'.DashboardUi::getBlockId($block).'"[^>]* data-section="'.$reversed[0].'">#', $html), "le bloc ".$block." de l'univers éteint reste dans sa colonne, sans rang à prendre");
			}

		}

	}

	/**
	 * Un univers sans rang passe après ceux qui en ont un, et les univers sans rang gardent entre eux
	 * l'ordre des onglets. Éprouvé sur une carte fabriquée : `updatePositions()` écrit toujours la liste
	 * entière, donc seul un univers ouvert après coup arrive sans rang, ce que le décor ne sait pas monter.
	 */
	public function testAnUnrankedSectionComesLast(): void {

		$sortSections = new \ReflectionMethod(DashboardLib::class, 'sortSections');

		$sections = $sortSections->invoke(NULL, ['settings' => ['hidden' => FALSE, 'position' => 0]]);

		$this->assertEquals(['settings', ...array_values(array_diff(DashboardSetting::SECTIONS, ['settings']))], $sections, "l'univers rangé d'abord, les autres dans l'ordre des onglets");

	}

	/** Un interrupteur au nom d'un univers est refusé et n'écrit rien : la ligne d'un univers ne porte que son rang. */
	public function testASectionIsNeverHidden(): void {

		$fw = new \FailWatch();

		PreferenceLib::updateHidden($this->eStructure, 'commercialisation', TRUE);

		$this->assertEquals(TRUE, $fw->has('Preference::sectionHidden'), "un univers ne s'éteint pas par son nom, ".json_encode($fw->get()));
		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "rien n'est écrit");

	}

	/** Une liste d'univers incomplète est refusée comme une liste de blocs, et n'écrit rien. */
	public function testAStaleSectionListIsRefused(): void {

		$sections = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']);

		$fw = new \FailWatch();

		PreferenceLib::updatePositions($this->eStructure, $sections, array_slice($sections, 1));

		$this->assertEquals(TRUE, $fw->has('Preference::positionsIncomplete'), "une liste d'univers incomplète est refusée");
		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "rien n'est écrit");

	}

	/**
	 * Le geste du panneau, par la vraie page : sans `scope`, l'étage posté est celui des univers, il se
	 * refuse s'il ne les porte pas tous, et sa réponse rend les colonnes dans le rang neuf.
	 */
	public function testThePositionsActionRanksTheSections(): void {

		$sections = array_keys(DashboardLib::loadChoices($this->eStructure)['sections']);

		if($this->assertDecorPresent(count($sections) >= 2, 'la structure #'.self::STRUCTURE.' montre au moins deux univers au membre du test') === FALSE) {
			return;
		}

		$reversed = array_reverse($sections);

		$fw = new \FailWatch();
		$action = $this->runPage('dashboard/preference:doUpdatePositions', ['id' => self::STRUCTURE, 'scope' => '', 'names' => json_encode(array_slice($reversed, 1))]);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Preference::positionsIncomplete'), "un étage d'univers incomplet est refusé : ".get_class($action).' '.$fw);

		$action = $this->runPage('dashboard/preference:doUpdatePositions', ['id' => self::STRUCTURE, 'scope' => '', 'names' => json_encode($reversed)]);

		if($this->assertTrue($action instanceof \ViewAction, "l'étage entier s'enregistre et rend les colonnes : ".get_class($action)) === FALSE) {
			return;
		}

		$this->assertEquals($reversed, array_keys(DashboardLib::loadChoices($this->eStructure)['sections']), "le panneau suit le rang posté");

		// Les colonnes rendues portent les blocs dans le rang neuf de leur univers : dans chacune, l'univers
		// d'un bloc ne vient jamais avant celui du bloc qui le précède. Un bloc à découvert
		// (`DashboardSetting::OPEN`) est écarté : il suit les colonnes quel que soit son rang.
		$instructions = $this->renderInstructions('doUpdatePositions', $action);

		[$instruction, $selector, [[$method, [$html]]]] = json_decode($instructions, TRUE)[0];

		$this->assertEquals(['qs', '#dashboard .dashboard-columns', 'outerHtml'], [$instruction, $selector, $method], 'une seule instruction, sur le conteneur des colonnes');
		$columns = array_slice(explode('<div class="dashboard-column">', $html), 1);

		$this->assertEquals(TRUE, $columns !== [], 'la réponse porte les colonnes');

		$ranks = array_flip($reversed);
		$blocksOpen = array_keys(array_filter(DashboardLib::getBlocks(), fn(array $declaration) => $declaration['shape'] === DashboardSetting::OPEN));

		foreach($columns as $index => $column) {

			preg_match_all('/id="dashboard-block-([a-z0-9-]+)"[^>]* data-section="([a-z]+)"/', $column, $matches, PREG_SET_ORDER);

			$sections = array_column(array_filter($matches, fn(array $match) => in_array($match[1], $blocksOpen, TRUE) === FALSE), 2);

			$ranked = array_map(fn(string $section) => $ranks[$section], $sections);
			$sorted = $ranked;
			sort($sorted);

			$this->assertEquals($sorted, $ranked, 'colonne '.$index.' : les blocs suivent le rang neuf des univers, '.implode(' ', $sections));

		}

		$this->assertTrue(str_contains($html, 'DashboardPreference.initColumn(this)'), 'les colonnes rendues réarment leur Sortable');

		// Un rang de bloc ne rend rien : le bloc est déjà à sa place sous le doigt.
		$section = $reversed[0];
		$names = array_keys(array_filter(DashboardLib::loadChoices($this->eStructure)['sections'][$section], fn(array $choice) => $choice['chosen']));

		$action = $this->runPage('dashboard/preference:doUpdatePositions', ['id' => self::STRUCTURE, 'scope' => $section, 'names' => json_encode(array_reverse($names))]);

		$this->assertTrue($action instanceof \VoidAction, "un rang de bloc ne rend rien : ".get_class($action));

	}

	/**
	 * Fait parler le bilan, posé au cache que `DashboardLib::loadData()` lit, sous la clé de
	 * l'utilisateur fictif, que personne d'autre ne lit : le décor n'a aucune vente. `finalize()` retire
	 * la clé.
	 */
	private function speakBilan(): void {
		\Cache::redis()->set(DashboardLib::getCacheKey('selling-bilan', $this->eStructure, \user\ConnectionLib::getOnline()), ['data' => ['week' => 40, 'year' => 2026, 'weeks' => range(31, 40), 'sales' => ['now' => 6, 'before' => 3, 'variation' => 100, 'bars' => array_fill(0, 10, 1)], 'turnover' => ['now' => 200, 'before' => 100, 'variation' => 100, 'bars' => array_fill(0, 10, 20)], 'cumulative' => ['now' => 2000, 'before' => 1000, 'variation' => 100]]], 300);
	}

	/**
	 * Fait paraître une nouvelle du blog, posée au cache que `DashboardLib::loadData()` lit, comme
	 * `speakBilan()` : le blog réel change chaque semaine. `finalize()` retire la clé.
	 */
	private function announce(): void {
		\Cache::redis()->set(DashboardLib::getCacheKey('website-news', $this->eStructure, \user\ConnectionLib::getOnline()), ['data' => [['id' => 1, 'title' => 'Une nouvelle', 'publishedAt' => '2026-10-11 10:00:00', 'card' => FALSE]]], 300);
	}

	/** Les instructions d'une réponse de `view/preference.v.php`, rendues sur les données de l'action. */
	private function renderInstructions(string $name, \ViewAction $action): string {

		$view = $this->getView('dashboard/preference', $name);
		$template = new \AjaxTemplate();
		$template->setData(new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action));

		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $template->getData(), $template);

		return json_encode($template->formatInstructions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	}

	/**
	 * Les nouvelles du blog ne se règlent plus (L46) : le réglage ne les offre pas, le geste d'un bloc
	 * les refuse sous le nom de leur emplacement comme sous un univers, et une ligne `hidden` écrite
	 * quand elles se réglaient ne les cache plus.
	 */
	public function testTheNewsAreNotSet(): void {

		$this->assertEquals(['sections'], array_keys(DashboardLib::loadChoices($this->eStructure)), "le réglage ne les offre pas");

		foreach([[DashboardSetting::NEWS, 'website-news'], ['commercialisation', 'website-news']] as [$scope, $name]) {

			$action = $this->runPage('dashboard/preference:doUpdateHidden', ['id' => self::STRUCTURE, 'scope' => $scope, 'name' => $name, 'hidden' => 1]);

			$this->assertTrue($action instanceof \NotExpectedAction, $name.' sous '.$scope.' est refusé : '.get_class($action));

		}

		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "rien n'est écrit");

		$this->announce();

		// La ligne qu'un membre a pu écrire avant L46, posée sans `build()` : `Preference::properties()`
		// refuse désormais ce nom, l'emplacement ne se réglant plus, et c'est précisément cette ligne
		// d'avant que le cas éprouve.
		$ePreference = new Preference([
			'user' => \user\ConnectionLib::getOnline(),
			'structure' => $this->eStructure,
			'name' => 'website-news',
			'hidden' => TRUE
		]);

		Preference::model()->insert($ePreference);

		$this->assertEquals(['website-news'], array_keys(DashboardLib::loadNews($this->eStructure)), "la ligne n'est plus lue, les nouvelles restent");
		$this->assertTrue(DashboardLib::hasNews($this->eStructure), "l'entrée « Du nouveau » reste");

	}

	/** Un membre sans aucun droit de gestion voit les nouvelles du blog : elles ne disent rien de la structure. */
	public function testAMemberWithoutRightsSeesTheNews(): void {

		$this->connectAs(self::USER_OTHER, manager: FALSE);

		$this->announce();

		$this->assertEquals(['website-news'], array_keys(DashboardLib::loadNews($this->eStructure)), "le panneau les lui montre");

	}

	/**
	 * Le réglage d'un membre ne se voit pas chez un autre, dans la même structure. C'est la propriété
	 * que `whereUser()` tient sur chaque requête du Lib, et le seul endroit où son absence se verrait.
	 */
	public function testOneMemberDoesNotSeeAnother(): void {

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);

		$this->connectAs(self::USER_OTHER);

		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "le second membre ne voit rien du premier");
		$this->assertEquals(TRUE, DashboardLib::loadChoices($this->eStructure)['sections']['commercialisation']['selling-unpaid']['chosen'], "et son accueil garde le bloc que le premier a éteint");

		$this->connectAs(self::USER);

		$this->assertEquals(TRUE, PreferenceLib::getByMember($this->eStructure)['selling-unpaid']['hidden'], "le premier retrouve son réglage");

	}

	/**
	 * Un membre qui quitte la structure n'y laisse aucun réglage. L'assertion éprouve
	 * `PreferenceLib::deleteByUserAndStructure()`, que `MemberLib::deleteInternal()` appelle.
	 */
	public function testLeavingTheStructureErasesTheSettings(): void {

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);
		PreferenceLib::updateHidden($this->eStructure, 'selling-drafts', TRUE);

		$this->assertEquals(2, count(PreferenceLib::getByMember($this->eStructure)), "deux réglages posés");

		PreferenceLib::deleteByUserAndStructure(new \user\User(['id' => self::USER]), $this->eStructure);

		$this->assertEquals([], PreferenceLib::getByMember($this->eStructure), "le départ du membre efface ses réglages");

	}

	/**
	 * Écrire son réglage demande d'être le propriétaire de la ligne **et** de pouvoir ouvrir la
	 * structure : les deux questions de `canWrite()`, que les quatre routes du panneau font poser par
	 * `ModulePage`. Sans la seconde, l'identifiant de structure venant du formulaire suffirait à régler
	 * son accueil dans n'importe quelle structure.
	 */
	public function testWriteNeedsBothTheRowAndTheStructure(): void {

		$ePreference = new Preference([
			'user' => \user\ConnectionLib::getOnline(),
			'structure' => $this->eStructure
		]);

		$this->assertEquals(TRUE, $ePreference->canWrite(), "le membre connecté écrit son réglage sur une structure qu'il lit");

		$ePreferenceOther = new Preference([
			'user' => new \user\User(['id' => self::USER_OTHER]),
			'structure' => $this->eStructure
		]);

		$this->assertEquals(FALSE, $ePreferenceOther->canWrite(), "il n'écrit pas le réglage d'un autre membre");

	}

	/**
	 * En réglage, un bloc éteint garde sa place, réduit à son cadre (L43) : l'accueil ne le montre plus,
	 * le réglage le montre sans sa donnée et sans pied.
	 */
	public function testTheSettingKeepsAnOffBlockAsAFrame(): void {

		if($this->assertDecorPresent(array_key_exists('selling-unpaid', DashboardLib::load($this->eStructure, setting: TRUE)), 'le réglage offre les factures impayées') === FALSE) {
			return;
		}

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);

		$this->assertFalse(array_key_exists('selling-unpaid', DashboardLib::load($this->eStructure)), "l'accueil ne le montre plus");

		$entry = DashboardLib::load($this->eStructure, setting: TRUE)['selling-unpaid'];

		$this->assertEquals([FALSE, TRUE, NULL, NULL], [$entry['chosen'], $entry['frame'], $entry['data'], $entry['url']], "le réglage le rend en cadre éteint");

		$block = new DashboardUi()->getBlock($this->eStructure, 'selling-unpaid', $entry, 'commercialisation', $entry['declaration']['column']);

		$this->assertTrue(str_contains($block, 'dashboard-block-off'), "le cadre s'estompe");
		$this->assertFalse(str_contains($block, 'href=""'), "le cadre n'a pas de pied, faute d'adresse");
		$this->assertFalse(str_contains($block, 'data-sort-id'), "un bloc éteint ne prend pas de rang");

	}

	/** Un bloc éteint ne paie que la lecture des préférences : le réglage ne lit pas ce qu'on a choisi de ne pas voir. */
	public function testAnOffBlockReadsNoData(): void {

		PreferenceLib::updateHidden($this->eStructure, 'selling-unpaid', TRUE);

		// Le droit du bloc lit `structureFeature` une fois par structure et par processus
		// (`ConfLib::getByStructure()`) : lue d'abord, elle ne pèse plus sur le compte, qui ne mesure que
		// ce que le cadre ajoute aux préférences.
		$this->eStructure->conf(\structure\Feature::class);

		$this->assertEquals(
			$this->countQueries(fn() => PreferenceLib::getByMember($this->eStructure)),
			$this->countQueries(fn() => DashboardLib::loadSetting($this->eStructure, 'selling-unpaid')),
			"le cadre d'un bloc éteint ne lit que les préférences"
		);

	}

	/**
	 * L'accueil ordinaire ne porte aucun contrôle du réglage, et le réglage les porte tous : poignées,
	 * étages rangeables, cartes des univers, barre.
	 */
	public function testOnlyTheSettingCarriesTheControls(): void {

		$dashboardUi = new DashboardUi();
		$today = DashboardLib::getToday();

		$board = $dashboardUi->getBoard($this->eStructure, DashboardLib::load($this->eStructure), DashboardLib::loadContributions($this->eStructure), $today);

		foreach(['data-sort-url', 'util-drag-handle', 'dashboard-setting-section', PreferenceUi::ACTION_HIDDEN, 'dashboard-setting-bar'] as $control) {
			$this->assertFalse(str_contains($board, $control), "l'accueil ne porte pas ".$control);
		}

		$blocks = DashboardLib::load($this->eStructure, setting: TRUE);
		$contributions = DashboardLib::loadContributions($this->eStructure, slots: [DashboardSetting::FIGURE, DashboardSetting::BILAN, DashboardSetting::QUEUE]);
		$setting = $dashboardUi->getBoard($this->eStructure, $blocks, $contributions, $today, TRUE, DashboardLib::getSectionsChosen($blocks));

		foreach(['data-sort-url', 'util-drag-handle', 'dashboard-setting-section', PreferenceUi::ACTION_HIDDEN, 'dashboard-setting-bar'] as $control) {
			$this->assertTrue(str_contains($setting, $control), "le réglage porte ".$control);
		}

	}

	/**
	 * La route d'un bloc lourd, appelée depuis le réglage, rend son cadre au lieu de le retirer : sans
	 * lui, un bloc lourd éteint disparaîtrait du réglage et ne se rallumerait plus.
	 */
	public function testTheHeavyRouteKeepsTheFrameInSetting(): void {

		$heavy = NULL;

		foreach(DashboardLib::load($this->eStructure, setting: TRUE) as $name => $entry) {
			if($entry['declaration']['cost'] === DashboardSetting::HEAVY) {
				$heavy = $name;
				break;
			}
		}

		if($this->assertDecorPresent($heavy !== NULL, 'la structure #'.self::STRUCTURE.' offre un bloc lourd') === FALSE) {
			return;
		}

		PreferenceLib::updateHidden($this->eStructure, $heavy, TRUE);

		$this->assertEquals(NULL, DashboardLib::loadBlock($this->eStructure, $heavy), "hors réglage, la route retire le bloc éteint");
		$this->assertTrue(DashboardLib::loadBlock($this->eStructure, $heavy, setting: TRUE)['frame'], "en réglage, elle rend son cadre");

	}

	/**
	 * Le cadre du réglage ne passe pas le droit : un bloc lourd que la structure ne s'offre pas reste
	 * retiré, sinon `?setting=1` montrerait son titre à qui ne le voit pas.
	 */
	public function testTheHeavyRouteRefusesAnUnofferedBlockInSetting(): void {

		$offered = [];

		foreach(DashboardLib::loadChoices($this->eStructure)['sections'] as $blocks) {
			$offered = array_merge($offered, array_keys($blocks));
		}

		$unoffered = NULL;

		foreach(DashboardLib::getBlocks() as $name => $declaration) {
			if($declaration['cost'] === DashboardSetting::HEAVY and in_array($name, $offered, TRUE) === FALSE) {
				$unoffered = $name;
				break;
			}
		}

		if($this->assertDecorPresent($unoffered !== NULL, 'un bloc lourd que la structure #'.self::STRUCTURE.' ne s\'offre pas') === FALSE) {
			return;
		}

		$this->assertEquals(NULL, DashboardLib::loadBlock($this->eStructure, $unoffered, setting: TRUE), "en réglage, un bloc non offert reste retiré");

	}

	/**
	 * Le geste d'un bloc répond par le bloc et l'état de son univers : éteindre le dernier bloc allumé
	 * éteint l'univers, et le rallumer le rallume.
	 */
	public function testTheHiddenActionAnswersWithTheBlockAndItsSection(): void {

		$section = 'commercialisation';
		$sections = DashboardLib::loadChoices($this->eStructure)['sections'];

		if($this->assertDecorPresent(array_key_exists($section, $sections) and array_key_exists('selling-unpaid', $sections[$section]), 'la structure #'.self::STRUCTURE.' offre les factures impayées') === FALSE) {
			return;
		}

		$blocks = array_keys($sections[$section]);

		PreferenceLib::updateSection($this->eStructure, array_values(array_diff($blocks, ['selling-unpaid'])), TRUE);

		$data = new \ReflectionProperty(\ViewAction::class, 'data');

		$action = $this->runPage('dashboard/preference:doUpdateHidden', ['id' => self::STRUCTURE, 'scope' => $section, 'name' => 'selling-unpaid', 'hidden' => 1]);

		$this->assertTrue($action instanceof \ViewAction, "le geste aboutit : ".get_class($action));
		$this->assertEquals([FALSE, TRUE, FALSE], [$data->getValue($action)->entry['chosen'], $data->getValue($action)->entry['frame'], $data->getValue($action)->sectionOn], "le dernier bloc éteint éteint l'univers");

		$action = $this->runPage('dashboard/preference:doUpdateHidden', ['id' => self::STRUCTURE, 'scope' => $section, 'name' => 'selling-unpaid', 'hidden' => 0]);

		$this->assertEquals([TRUE, TRUE], [$data->getValue($action)->entry['chosen'], $data->getValue($action)->sectionOn], "le rallumer rallume l'univers");

	}

	/** Exécute la vraie page et rend ce qu'elle lève. Forme reprise de `payment/test/StripeExpressCardAi.t.php`. */
	private function runPage(string $page, array $post): \Throwable {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'POST');

		$getPages = new \ReflectionMethod(\Page::class, 'getPages');

		$_POST = $post;
		$_REQUEST = $_POST;

		try {
			\Page::execute($getPages->invoke(NULL, $page), new \stdClass());
		} catch(\Throwable $e) {
			return $e;
		} finally {
			$_POST = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		return new \LogicException('l\'action '.$page.' n\'a rien levé');

	}

	/**
	 * L'utilisateur connecté du processus, et son appartenance à la structure de référence.
	 *
	 * Un utilisateur fictif n'est membre de rien, donc tous les `can*()` répondraient comme pour un
	 * visiteur et `loadChoices()` ne rendrait aucun univers : c'est le piège que `.claude/recette/lecteur.php`
	 * décrit, une sonde qui répond avec aplomb à une autre question. L'appartenance s'injecte donc dans
	 * le cache de `MemberLib`, sur la forme de `structure/test/AccessSectionAi.t.php`, avec un rôle
	 * complet : ce fichier éprouve la préférence et non le filtrage par les droits, qui est à
	 * `AccessSectionAi` et à `DashboardLib::isAllowed()`.
	 *
	 * `$manager` à faux donne le rôle le plus pauvre qu'un membre puisse porter, sans aucun droit de
	 * gestion : ce que voit tout membre se prouve sous lui.
	 */
	private function connectAs(int $userId, bool $manager = TRUE): void {

		$eUser = new \user\User(['id' => $userId]);

		\user\ConnectionLib::setOnline($eUser);

		// Les vues d'un membre neuf, celles du schéma : le rendu d'un bloc en lit (l'adresse de son pied
		// suit la vue des clients, par exemple), et une vue absente lèverait au rendu de l'accueil.
		$views = [];

		foreach(\structure\Member::model()->getProperties() as $property) {
			if(str_starts_with($property, 'view')) {
				$views[$property] = \structure\Member::model()->getDefaultValue($property);
			}
		}

		$eMember = new \structure\Member([
			'structure' => $this->eStructure['id'],
			'viewSellingSales' => \structure\Member::SALE,
			'role' => new \structure\Role([
				'admin' => $manager,
				'accounting' => $manager ? \structure\Role::FULL : \structure\Role::DENIED,
				'accountingAnalyze' => $manager,
				'selling' => $manager,
				'planning' => $manager ? \structure\Role::FULL : \structure\Role::DENIED,
				'productionAnalyze' => $manager,
				'productionObserve' => $manager,
				'commercialisationAnalyze' => $manager,
			]),
		] + $views);

		// L'appartenance remplace celle que l'utilisateur portait : le même utilisateur change de rôle d'une
		// méthode à l'autre, et un `+` garderait le premier rôle injecté.
		$property = self::getMemberCache();
		$cMember = $property->getValue();
		$cMember[$eUser['id']] = new \Collection([$this->eStructure['id'] => $eMember]);
		$property->setValue(NULL, $cMember);

	}

	private static function getMemberCache(): \ReflectionProperty {

		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');

	}

	/**
	 * Les lignes d'un utilisateur fictif, effacées sans passer par le Lib : `deleteByUserAndStructure()`
	 * lit l'utilisateur connecté pour ses bornes, et le ménage doit pouvoir tourner quel qu'il soit.
	 * C'est un fichier de test, donc hors du chemin d'écriture du produit (CLAUDE.md, « Validation »).
	 */
	private function forget(int $userId): void {

		$cPreference = Preference::model()
			->select('id')
			->whereUser($userId)
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cPreference as $ePreference) {
			PreferenceLib::delete($ePreference);
		}

	}

}
?>
