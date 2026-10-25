<?php
namespace dashboard;

/**
 * Les trois colonnes de l'accueil (chantier dashboard, L45, lot b, et L69) : « À faire », « À suivre »
 * et « Bilan », chaque bloc rangé dans celle que sa déclaration nomme.
 *
 * Ce qui s'y prouve :
 *
 * 1. un bloc qui ne déclare rien se range dans « À suivre », et la répartition lue dans les
 *    déclarations est celle retenue le 2026-10-14 ;
 * 2. l'écran répartit sans retrier : chaque colonne garde l'ordre où le moteur a rendu ses blocs, et la
 *    cellule porte la teinte de son univers ;
 * 3. la file ouvre « À faire », le bilan occupe seul « Bilan », et les nouvelles ne sont nulle part ;
 * 4. une colonne sans cellule n'est pas rendue, ni son titre, et un accueil sans rien ne rend aucune
 *    colonne ;
 * 5. le réglage range les mêmes blocs dans les mêmes colonnes et le même ordre que l'accueil, sous la
 *    rangée des univers, et un univers éteint y garde ses cellules (L45, lot e) ;
 * 6. un bloc à découvert suit les trois colonnes, dans la racine que le réglage compte et remplace
 *    (L69).
 *
 * Le refus d'une colonne inconnue, ou de « Bilan », ne se prouve pas ici : `getBlocks()` balaie les
 * packages réels, et aucun test ne peut y glisser une déclaration fautive. La sentinelle lit la valeur
 * de chaque déclaration (`DashboardBlockSentinel.t.php`).
 *
 * Aucune base ouverte au-delà de la structure F1, qui ne sert qu'à nommer les adresses. Les blocs sont
 * des blocs lourds (`data` à `NULL`) : le socle pose leur emplacement sans appeler le `render()` du
 * package, et l'emplacement porte l'identifiant que les contrôles d'ordre lisent.
 *
 * Lancement : dev/test package=dashboard file=Column
 */
class ColumnTest extends \Test {

	const array TODAY = ['date' => '2026-10-05', 'week' => '2026-W41', 'weekFrom' => '2026-10-05', 'weekTo' => '2026-10-11'];

	public function testABlockFollowsUnlessItDeclaresOtherwise(): void {

		$this->assertEquals(DashboardSetting::TODO, DashboardLib::getBlocks()['planning-reminder']['column'], 'un bloc qui demande un geste le déclare et se range dans « À faire »');
		$this->assertEquals(DashboardSetting::FOLLOW, DashboardLib::getBlocks()['selling-unpaid']['column'], 'un bloc qui ne déclare pas de colonne se range dans « À suivre »');

	}

	public function testBlocksAreSplitWithoutBeingSortedAgain(): void {

		$html = $this->getBoard([
			'planning-harvest' => ['production', DashboardSetting::FOLLOW],
			'planning-reminder' => ['production', DashboardSetting::TODO],
			'selling-deliveries' => ['commercialisation', DashboardSetting::TODO],
			'selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW],
			'bank-reconciliation' => ['accounting', DashboardSetting::TODO]
		]);

		$this->assertEquals(2, substr_count($html, '<div class="dashboard-column">'), 'deux colonnes, « Bilan » n\'ayant rien à montrer');
		$this->assertFalse(str_contains($html, 'dashboard-univers'), 'plus aucune bande sur l\'accueil');

		$this->assertEquals(
			['todo', 'planning-reminder', 'selling-deliveries', 'bank-reconciliation', 'follow', 'planning-harvest', 'selling-unpaid'],
			$this->getOrder($html, ['planning-harvest', 'planning-reminder', 'selling-deliveries', 'selling-unpaid', 'bank-reconciliation']),
			'« À faire » puis « À suivre », chaque colonne dans l\'ordre du moteur'
		);

	}

	/**
	 * Les nouvelles n'ont pas de place dans les colonnes (L46) : posées dans les pièces, elles ne sont
	 * pas rendues, le groupe Bureau les ouvrant en panneau.
	 */
	public function testTheQueueOpensTodoAndTheBilanStandsAlone(): void {

		$contributions = [
			DashboardSetting::QUEUE => ['selling-reminder-first' => ['data' => ['invoices' => 2, 'amount' => 300, 'waitingDays' => 12], 'url' => '/factures']],
			DashboardSetting::BILAN => ['selling-bilan' => ['declaration' => ['section' => 'commercialisation', 'shape' => DashboardSetting::TILE, 'choose' => FALSE], 'data' => NULL, 'url' => '/ventes']],
			DashboardSetting::NEWS => ['website-news' => ['declaration' => ['section' => NULL, 'choose' => FALSE], 'data' => NULL, 'url' => '/']]
		];

		$html = $this->getBoard([
			'selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW],
			'selling-deliveries' => ['commercialisation', DashboardSetting::TODO]
		], $contributions);

		$this->assertEquals(
			['todo', 'selling-reminder-first', 'selling-deliveries', 'follow', 'selling-unpaid', 'review', 'selling-bilan'],
			$this->getOrder($html, ['selling-reminder-first', 'selling-deliveries', 'selling-unpaid', 'selling-bilan']),
			'la file avant les blocs à faire, le bilan seul dans la dernière colonne'
		);

		$this->assertFalse(str_contains($html, 'id="'.DashboardUi::getBlockId('website-news').'"'), 'les nouvelles ne sont dans aucune colonne');

	}

	public function testAnEmptyColumnIsNotRendered(): void {

		$html = $this->getBoard(['selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW]]);

		$this->assertEquals(1, substr_count($html, '<div class="dashboard-column">'), 'une seule colonne');
		$this->assertEquals([FALSE, TRUE], [str_contains($html, s("À faire")), str_contains($html, s("À suivre"))], 'pas de titre au-dessus de rien');

		$html = $this->getBoard(['selling-deliveries' => ['commercialisation', DashboardSetting::TODO]]);

		$this->assertEquals([TRUE, FALSE], [str_contains($html, s("À faire")), str_contains($html, s("À suivre"))], 'et dans l\'autre sens');

		$this->assertEquals('', new DashboardUi()->getColumns($this->getStructure(), [], []), 'sans rien à ranger, la tête seule');

	}

	public function testTheSettingRanksLikeTheHome(): void {

		$blocks = [
			'planning-harvest' => ['production', DashboardSetting::FOLLOW],
			'planning-reminder' => ['production', DashboardSetting::TODO],
			'selling-deliveries' => ['commercialisation', DashboardSetting::TODO],
			'selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW],
			'bank-reconciliation' => ['accounting', DashboardSetting::TODO]
		];

		$home = $this->getBoard($blocks);
		$setting = $this->getBoard($blocks, setting: TRUE, sectionsChosen: ['commercialisation' => TRUE, 'production' => TRUE, 'accounting' => TRUE]);

		$this->assertEquals($this->getOrder($home, array_keys($blocks)), $this->getOrder($setting, array_keys($blocks)), 'les mêmes colonnes, dans le même ordre');
		$this->assertFalse(str_contains($setting, 'dashboard-univers'), 'plus de bande en réglage');

		$this->assertEquals(2, substr_count($setting, 'onrender="DashboardPreference.initColumn(this)"'), 'chaque colonne se range seule');
		$this->assertTrue(str_contains($setting, '<div id="dashboard-block-planning-reminder" class="dashboard-block-pending" data-section="production" data-sort-id="planning-reminder"'), 'un emplacement porte son univers et son rang, que le glisser poste');

		$positions = array_map(fn(string $section) => strpos($setting, 'id="'.PreferenceUi::getSectionId($section).'"'), ['commercialisation', 'production', 'accounting']);

		$this->assertFalse(in_array(FALSE, $positions, TRUE), 'une carte par univers');
		$this->assertTrue($positions[0] < $positions[1] and $positions[1] < $positions[2] and $positions[2] < strpos($setting, 'dashboard-columns'), 'la rangée des univers, dans leur ordre, au-dessus des colonnes');

	}

	public function testAnOpenBlockFollowsTheColumns(): void {

		$html = $this->getBoard([
			'selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW],
			'selling-deliveries' => ['commercialisation', DashboardSetting::TODO]
		], open: ['selling-unpaid']);

		$this->assertEquals(1, substr_count($html, '<div class="dashboard-column">'), '« À suivre » n\'a plus de cellule, elle n\'est pas rendue');

		// Le réglage compte les blocs sous `.dashboard-columns` et la remplace entière : le bloc à
		// découvert doit en être un enfant, après la grille, pas un voisin.
		$document = new \DOMDocument();
		@$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

		$children = array_map(
			fn(\DOMElement $element) => $element->getAttribute('id') ?: $element->getAttribute('class'),
			iterator_to_array(new \DOMXPath($document)->query('//*[contains(concat(" ", @class, " "), " dashboard-columns ")]/*'))
		);

		$this->assertEquals(['dashboard-columns-grid', DashboardUi::getBlockId('selling-unpaid')], $children, 'la grille puis le bloc à découvert, dans la racine que le réglage remplace');

		$alone = $this->getBoard(['selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW]], open: ['selling-unpaid']);

		$this->assertEquals([FALSE, TRUE], [str_contains($alone, 'dashboard-columns-grid'), str_contains($alone, 'id="'.DashboardUi::getBlockId('selling-unpaid').'"')], 'sans colonne, le bloc à découvert reste, sans grille vide au-dessus');

	}

	public function testAHiddenSectionKeepsItsCells(): void {

		$blocks = [
			'planning-reminder' => ['production', DashboardSetting::TODO],
			'selling-deliveries' => ['commercialisation', DashboardSetting::TODO],
			'selling-unpaid' => ['commercialisation', DashboardSetting::FOLLOW]
		];

		$setting = $this->getBoard($blocks, setting: TRUE, sectionsChosen: ['production' => TRUE, 'commercialisation' => FALSE], hidden: ['selling-deliveries', 'selling-unpaid']);

		$this->assertEquals(
			['todo', 'planning-reminder', 'selling-deliveries', 'follow', 'selling-unpaid'],
			$this->getOrder($setting, array_keys($blocks)),
			'les blocs de l\'univers éteint restent à leur place'
		);

		$this->assertEquals(1, preg_match('#id="dashboard-block-selling-deliveries"[^>]* data-section="commercialisation">#', $setting), 'éteint, sans rang à prendre, mais toujours de son univers');
		$this->assertTrue(str_contains($setting, 'id="'.PreferenceUi::getSectionId('commercialisation').'" class="util-deck util-deck-sm dashboard-setting-section is-off"'), 'la carte de l\'univers s\'estompe');
		$this->assertTrue(str_contains($setting, 'id="'.PreferenceUi::getSectionId('production').'" class="util-deck util-deck-sm dashboard-setting-section"'), 'l\'autre non');

	}

	/**
	 * L'accueil rendu sur des blocs lourds : `$blocks` porte le nom du bloc, son univers et sa colonne,
	 * dans l'ordre où le moteur les aurait rangés. En réglage, un bloc de `$hidden` arrive éteint, donc en
	 * cadre, comme `DashboardLib::load()` le rend.
	 */
	private function getBoard(array $blocks, array $contributions = [], bool $setting = FALSE, array $sectionsChosen = [], array $hidden = [], array $open = []): string {

		$entries = [];

		foreach($blocks as $block => [$section, $column]) {

			$chosen = (in_array($block, $hidden, TRUE) === FALSE);

			$entries[$block] = ['declaration' => ['section' => $section, 'column' => $column, 'shape' => in_array($block, $open, TRUE) ? DashboardSetting::OPEN : DashboardSetting::TILE, 'choose' => FALSE], 'url' => '/', 'data' => NULL, 'chosen' => $chosen, 'frame' => ($chosen === FALSE)];

		}

		return new DashboardUi()->getBoard($this->getStructure(), $entries, $contributions, self::TODAY, $setting, $sectionsChosen);

	}

	/**
	 * Les colonnes et les pièces dans l'ordre où le balisage les pose : une colonne est repérée par son
	 * titre, une pièce par son identifiant.
	 */
	private function getOrder(string $html, array $names): array {

		$ui = new DashboardUi();

		$positions = [];

		// Une colonne sans cellule n'est pas rendue, et n'a donc pas de place.
		foreach(DashboardSetting::COLUMNS as $column) {

			$position = strpos($html, '>'.$ui->getColumnName($column).'</h2>');

			if($position !== FALSE) {
				$positions[$column] = $position;
			}

		}

		foreach($names as $name) {
			$positions[$name] = strpos($html, 'id="'.DashboardUi::getBlockId($name).'"');
		}

		asort($positions);

		return array_keys($positions);

	}

	private function getStructure(): \structure\Structure {

		return \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

	}

}
?>
