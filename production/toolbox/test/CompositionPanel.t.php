<?php
namespace toolbox;

/**
 * Les panneaux de l'écran « Paniers de l'année » et ce qu'ils rendent : les semaines
 * livrées, les espèces livrées, l'ajout d'une taille de panier, la grille, les cartes des semaines, le rejeu et
 * la roue crantée. Tous s'ouvrent sur un plan écrit par le départ (`createStarted()`), et
 * chacun écrit par `build()` et par le Lib, comme la page.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Les structures éprouvées n'ont pas
 * de plan pour la saison : sur un doublon, `CompositionPlanLib::create()` annulerait toute la pile de
 * transactions, et celle du test avec.
 *
 * Lancement : dev/test package=toolbox file=CompositionPanel
 */
class CompositionPanelTest extends \Test {

	/**
	 * Les requêtes de l'écran par référence du plan (`testScreenQueries()`), mesurées le 2026-09-30
	 * après P8 (32 et 36 après P2, 28 et 31 après P6, 21 et 24 après P7). Se baisse quand un lot en
	 * retire. r37b en ajoute une le 2026-10-01 : les produits composés que liste la porte « Mettre en
	 * vente » (`CompositionSaleLib::getCompositions()`) ; les produits retenus ne se lisent que si
	 * une espèce en retient un (`getRetained()`). r76 en retire deux le 2026-10-04 : les lignes de
	 * chaque taille et les rangs jamais servis de la grille prennent leur espèce dans celles du plan,
	 * que l'écran a déjà lues, au lieu de lire la plante de chaque ligne et celle de chaque rang.
	 */
	private const SCREEN_QUERIES = [
		CompositionPlan::COMMUNITY => 17,
		CompositionPlan::CULTIVATION => 17,
	];

	/**
	 * Les requêtes de l'ouverture du menu d'une case, route et page comprises
	 * (`testCellMenuReadsLess()`), mesurées le 2026-09-30 après P7 (20 après P4), sur un plan
	 * `COMMUNITY`. Se baisse quand un lot en retire : 16 depuis r76, le 2026-10-04, pour la même
	 * raison que `SCREEN_QUERIES`.
	 */
	private const MENU_QUERIES = 16;

	/**
	 * Les requêtes d'une lecture de la source de la communauté, l'agrégé en cache
	 * (`testSourceQueries()`), mesurées le 2026-09-30 après P5 : dix avant.
	 */
	private const SOURCE_QUERIES = 4;

	public function setUp(): void {
		CompositionPlan::model()->beginTransaction();
	}

	public function tearDown(): void {
		CompositionPlan::model()->rollBack();
	}

	/**
	 * La présentation de l'outil (r36, planche r36, variante A), qui remplace la notice de r35 : ce
	 * qu'il apporte et les cinq étapes, sans ce qu'il ne fait pas (r55), d'abord pour une structure
	 * qui n'a pas composé ses paniers, dont les étapes gardent leur phrase sans illustration, puis
	 * pour la même une fois ses paniers composés, où chaque étape que le plan peut montrer porte son
	 * morceau d'écran, sans geste qu'on puisse déclencher.
	 */
	public function testNoticePanel(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$notice = CompositionPlanLib::getNotice($eCompositionPlan);

		$this->assertTrue($eCompositionPlan->empty(), 'une structure sans paniers');
		$this->assertEquals(['cultivated' => [], 'cultivatedWeeks' => [], 'rhythm' => NULL, 'rhythmWeeks' => [], 'filled' => NULL, 'amended' => NULL, 'lean' => NULL], $notice, 'rien à montrer');

		$panel = new CompositionUi()->getNoticePanel($eCompositionPlan, $notice);

		$this->assertEquals('toolbox-composition-notice-panel', $panel->id, 'l\'identifiant du panneau');

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$notice = CompositionPlanLib::getNotice($eCompositionPlan);

		$this->assertTrue(count($notice['cultivated']) >= 1 and count($notice['cultivated']) <= 4, 'jusqu\'à quatre rangs de saison');
		$this->assertEquals(range(min(array_map(fn(array $row) => array_key_first($row['weeksSeason']), $notice['cultivated'])), max(array_map(fn(array $row) => array_key_last($row['weeksSeason']), $notice['cultivated']))), $notice['cultivatedWeeks'], 'sur les semaines de leurs saisons, de la première à la dernière');
		$this->assertTrue($notice['filled'] !== NULL, 'une semaine remplie');
		$this->assertTrue($eCompositionPlan['eCompositionFormula']['cCompositionWeek']->find(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['week'] === $notice['filled'] and $eCompositionWeek['amended'] === FALSE and $eCompositionWeek['cCompositionLine']->notEmpty())->notEmpty(), 'non corrigée, avec un panier');
		$this->assertEquals(NULL, $notice['amended'], 'aucune semaine corrigée sur un départ');

		if($notice['rhythm'] !== NULL) {
			$this->assertTrue(count($notice['rhythmWeeks']) <= CompositionPlanLib::NOTICE_WEEKS and $notice['rhythmWeeks'][0] === array_key_first($notice['rhythm']['weeksSeason']), 'le rythme sur le début de la saison de son espèce');
		}

		$panel = new CompositionUi()->getNoticePanel($eCompositionPlan, $notice);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

		$figures = iterator_to_array($document->querySelectorAll('.toolbox-composition-notice-figure'));

		$this->assertEquals([], array_values(array_filter($figures, fn(\Dom\Element $figure) => $figure->hasAttribute('inert') === FALSE)), 'toutes inertes');
		$this->assertEquals(0, $document->querySelectorAll('.toolbox-composition-notice-figure tr[id], #toolbox-composition-lean, #toolbox-composition-grid')->length, 'sans les identifiants que les réponses ciblées remplacent sur l\'écran');

	}

	/**
	 * Une semaine se range au mois de son jeudi : la semaine 1 de 2026 commence le 29 décembre et
	 * se range en janvier, la 52 finit le 27 décembre. Aucune ne se perd ni ne se compte deux fois,
	 * et la 53 de cette année ISO longue n'existe pas.
	 */
	public function testSeasonMonths(): void {

		$months = CompositionLib::getSeasonMonths(2026);

		$this->assertEquals(range(1, 12), array_keys($months), 'douze mois dans l\'ordre');
		$this->assertEquals(range(1, 52), array_merge(...array_values($months)), 'chaque semaine une fois');
		$this->assertEquals(1, array_search(1, array_map(fn(array $weeks) => $weeks[0], $months)), 'semaine 1 en janvier');
		$this->assertEquals([49, 50, 51, 52], $months[12], 'décembre 2026, sans semaine 53');
		$this->assertEquals([14, 15, 16, 17, 18], $months[4], 'avril 2026, du 30 mars au 3 mai : le jeudi 30 avril range la 18');

	}

	/**
	 * L'axe de la grille : les 52 semaines de la saison, chacune au mois de son jeudi, comme les
	 * semaines du panneau.
	 */
	public function testSeasonWeekMonths(): void {

		$weekMonths = CompositionLib::getSeasonWeekMonths(2026);

		$this->assertEquals(52, count($weekMonths), '52 semaines');
		$this->assertEquals('2026-01', $weekMonths['2026-W01'], 'la semaine du 29 décembre en janvier');
		$this->assertEquals('2026-04', $weekMonths['2026-W18'], 'la semaine du jeudi 30 avril en avril');
		$this->assertEquals('2026-12', $weekMonths['2026-W52'], 'la dernière en décembre');

		$counts = array_map(fn(array $weeks) => count($weeks), CompositionLib::getSeasonMonths(2026));

		$this->assertEquals(array_values($counts), array_values(array_count_values($weekMonths)), 'autant de semaines par mois que le panneau');

	}

	/**
	 * Les semaines qui ouvrent un mois (lot r21, retour du 27/09, « lignes verticales pour
	 * séparer les mois ») : celle du premier jeudi de chaque mois, sauf le premier de la liste, qui n'a
	 * pas de mois avant lui pour s'en séparer.
	 */
	public function testMonthStarts(): void {

		$weekMonths = CompositionLib::getSeasonWeekMonths(2026);

		$this->assertEquals([6, 10, 14, 19, 23, 27, 32, 36, 40, 45, 49], CompositionLib::getMonthStarts($weekMonths), 'onze traits sur l\'année 2026, au premier jeudi de chaque mois');
		$this->assertEquals([], CompositionLib::getMonthStarts([]), 'aucun sans semaine');

	}

	/**
	 * La grille de la formule ouverte, telle que l'écran la rend : une colonne par semaine de la
	 * saison sous l'axe, une semaine non livrée sans case, une case servie pleine, une case de la
	 * saison réglée de l'espèce en vert, d'un seul ton, une case hors de sa saison à la trame.
	 *
	 * Et ce que la semaine montre, rendu d'avance : chaque case porte sa semaine, chaque semaine
	 * qui a un panier a sa bulle (É10) ; une semaine non livrée n'a ni bulle ni panier. Chaque semaine
	 * livrée est nommée sur la grille (`data-weeks`), où le script pose sa pastille (lot r8c) ; la
	 * carte de chaque semaine qui a un panier liste une ligne par espèce servie. Les quantités
	 * réglables et le menu d'une case (lot r24), que seul un gestionnaire voit, sont éprouvés par
	 * `testCardGestures()` et `testGestureRender()`.
	 *
	 * Chaque espèce du plan s'annonce réglable par une roue au bout de son nom, qui ouvre le même menu que lui, et la carte de tête se règle par une roue
	 * à droite de son titre (retours du 26/09 sur le lot L5).
	 */
	public function testGridRender(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, array_values(array_diff(range(10, 40), [20])));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$html = new CompositionUi()->getProposal($eCompositionPlan);

		// L'analyseur HTML de libxml ne connaît pas les balises SVG des vignettes, et le dit en
		// avertissements, que le cadriciel lève.
		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);
		$xpath = new \DOMXPath($document);

		$grid = $eCompositionPlan['eCompositionFormula']['grid'];
		$plants = array_merge(...array_map(fn(array $group) => $group['species'], $grid));

		$rows = $xpath->query('//div[@id="toolbox-composition-grid"]/table/tbody/tr[not(contains(@class, "toolbox-composition-grid-group"))]');

		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);

		$states = ['off' => 0, 'served' => 0, 'season' => 0, 'out' => 0, 'outOpen' => 0];
		$errors = [];

		foreach($plants as $index => $row) {

			$cells = $xpath->query('./td', $rows->item($index));

			// Le nom, les 52 semaines, puis la somme de l'année (lot r13), qui ferme la ligne.
			if($cells->length !== 54) {
				$errors[] = $row['species']->getLabel().' : '.$cells->length.' cellules';
				continue;
			}

			foreach(range(1, 52) as $week) {

				$cell = $cells->item($week);

				// Le vert ne suit que la saison réglée de l'espèce, d'un seul ton (r29, #87) : la
				// référence est la saison du plan, pas le rang de la grille.
				$season = in_array($week, $windows[$row['species']['id']] ?? [], TRUE) ? 'toolbox-composition-grid-season ' : '';
				$open = isset($row['open'][$week]) ? 'toolbox-composition-grid-cell' : '';

				if(in_array($week, $eCompositionPlan['weeks'], TRUE) === FALSE) {
					$state = 'off';
				} else if(isset($row['lines'][$week])) {
					$state = 'served';
				} else if($season !== '') {
					$state = 'season';
				} else {
					// Hors saison, la case d'une semaine livrée s'ouvre aussi (r29, #83).
					$state = ($open === '') ? 'out' : 'outOpen';
				}

				$states[$state]++;

				if(($state === 'served') !== $cell->hasAttribute('data-served')) {
					$errors[] = $row['species']->getLabel().', semaine '.$week.' ('.$state.') : data-served';
				}

				// Le rond de la ligne ou de la colonne survolée écrit la quantité, sans unité (lot r22).
				if($state === 'served' and $cell->getAttribute('data-quantity') !== \util\TextUi::number($row['lines'][$week]['quantity'], NULL)) {
					$errors[] = $row['species']->getLabel().', semaine '.$week.' : data-quantity '.$cell->getAttribute('data-quantity');
				}

				if($cell->getAttribute('data-week') !== (string)$week) {
					$errors[] = $row['species']->getLabel().', semaine '.$week.' : data-week '.$cell->getAttribute('data-week');
				}

			}

		}

		$this->assertEquals([], $errors, 'cases fausses : '.implode(', ', array_slice($errors, 0, 5)));

		// Une case fermée hors saison n'est plus que celle d'une espèce sans unité au panier, depuis
		// qu'un chou s'ouvre à côté d'un autre (r34d) : un plan monté au départ n'en porte pas.
		foreach(array_diff_key($states, ['out' => 0]) as $state => $count) {
			$this->assertTrue($count > 0, 'au moins une case '.$state);
		}

		// La roue de chaque espèce du plan, au bout de son nom, et le panneau « Régler une espèce »
		// que les deux ouvrent (lot r22e), celui de cette espèce, sur la taille lue.
		$gears = 0;
		$errors = [];

		foreach($plants as $index => $row) {

			$name = $xpath->query('./td[1]//*[contains(@class, "toolbox-composition-grid-plant")]', $rows->item($index))->item(0);
			$gear = $xpath->query('./td[1]//a[contains(@class, "toolbox-composition-grid-species")]', $rows->item($index));

			if($row['eCompositionSpecies']->empty()) {
				continue;
			}

			$gears++;
			$url = '/toolbox/compositionSpecies:update?structure='.$eCompositionPlan['structure']['id'].'&formula='.$eCompositionPlan['eCompositionFormula']['id'].'&id='.$row['eCompositionSpecies']['id'];

			if(
				$name->getAttribute('href') !== $url or
				$gear->length !== 1 or
				$gear->item(0)->getAttribute('href') !== $url or
				$xpath->query('./td[1]//*[@data-dropdown]', $rows->item($index))->length !== 0
			) {
				$errors[] = $row['species']->getLabel().' : roue '.$gear->length.', panneau '.$name->getAttribute('href');
			}

		}

		$this->assertTrue($gears > 0, 'des espèces du plan dans la grille');
		$this->assertEquals([], $errors, 'roues fausses : '.implode(', ', array_slice($errors, 0, 5)));

		$this->assertEquals(count($plants) * 22, $states['off'], 'la semaine 20 et les semaines hors de 10 à 40 sans case');

		$tips = [];

		foreach($xpath->query('//div[@id="toolbox-composition-grid"]/div[contains(concat(" ", @class, " "), " util-tip ")]') as $tip) {
			$tips[] = (int)$tip->getAttribute('data-week');
			$this->assertTipLines($eCompositionPlan, (int)$tip->getAttribute('data-week'), $document->saveHTML($tip));
		}

		$this->assertEquals($eCompositionPlan['eCompositionFormula']['cCompositionWeek']->getColumn('week'), $tips, 'une bulle par semaine qui a un panier, hors du tableau');
		$this->assertEquals([], array_diff($tips, $eCompositionPlan['weeks']), 'aucune bulle pour une semaine non livrée');

		// Les semaines livrées se lisent sur la grille, où le script pose leur pastille sur l'axe (lot
		// r8c). Une case se modifie par le menu qu'elle ouvre (lot r24), à qui gère la structure : sans
		// lui, la grille ne s'annonce pas modifiable et ne porte pas la liste du menu. Le cas du gérant
		// est dans `checkCardGestures()`.
		$this->assertFalse($eCompositionPlan->assertUpdate(), 'personne n\'est connecté : la grille se lit sans se modifier');
		$this->assertFalse($xpath->query('//div[@id="toolbox-composition-grid"]')->item(0)->hasAttribute('data-editable'), 'la grille ne s\'annonce pas modifiable');
		$this->assertEquals(implode(' ', $eCompositionPlan['weeks']), $xpath->query('//div[@id="toolbox-composition-grid"]')->item(0)->getAttribute('data-weeks'), 'les semaines livrées sur la grille');

		$prices = $xpath->query('//div[@id="toolbox-composition-grid"]//thead/tr[2][@id="toolbox-composition-grid-prices"]/td');

		foreach(range(1, 52) as $week) {

			$cell = $prices->item($week);

			$this->assertEquals((string)$week, $cell->getAttribute('data-week'), 'semaine '.$week.' : sa case de prix');

		}

		$decks = [];
		$errors = [];

		foreach($eCompositionPlan['eCompositionFormula']['cCompositionWeek'] as $eCompositionWeek) {

			$week = $eCompositionWeek['week'];

			$libxmlErrors = libxml_use_internal_errors(TRUE);
			$documentWeek = new \DOMDocument();
			$documentWeek->loadHTML('<?xml encoding="utf-8"?>'.new CompositionUi()->getWeekCard($eCompositionPlan, $eCompositionPlan['eCompositionFormula'], $week));
			libxml_clear_errors();
			libxml_use_internal_errors($libxmlErrors);

			$xpathWeek = new \DOMXPath($documentWeek);
			$deck = $xpathWeek->query('//body/div')->item(0);

			$decks[] = $week;

			if($deck->getAttribute('id') !== 'toolbox-composition-card-'.$week) {
				$errors[] = 'semaine '.$week.' : carte '.$deck->getAttribute('id');
			}

		}

		$this->assertEquals($eCompositionPlan['eCompositionFormula']['cCompositionWeek']->getColumn('week'), $decks, 'une carte par semaine qui a un panier');
		$this->assertTrue(count($decks) > 0, 'des cartes à éprouver');
		$this->assertEquals([], $errors, 'cartes fausses : '.implode(', ', array_slice($errors, 0, 5)));

	}

	/**
	 * La grille montre toujours toute l'année (lot r28, retour du 28/09) : les onglets de
	 * semestre du lot r16 sont partis, et rien de l'écran ne porte plus de période. Chaque semaine de
	 * la saison a sa colonne, et chaque semaine qui a un panier sa bulle. Le rang des prix n'y garde
	 * que l'alerte, la case d'une semaine étant trop étroite pour deux chiffres : la valeur reste dans
	 * la bulle.
	 */
	public function testYear(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(1, 52));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, 30);

		$this->assertEquals(30, $eCompositionPlan['week'], 'la semaine de la case');
		$this->assertEquals(NULL, CompositionPlanLib::getForScreen($eStructure, NULL, 53)['week'], 'une semaine qui n\'est pas livrée ne porte pas de case');

		$html = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$grid = $document->getElementById('toolbox-composition-grid');

		$weeksShown = array_values(array_unique(array_map(fn(\Dom\Element $cell) => (int)$cell->getAttribute('data-week'), iterator_to_array($document->querySelectorAll('#toolbox-composition-grid tbody tr[data-species] > td[data-week]')))));

		$this->assertEquals(range(1, 52), $weeksShown, 'les rangs portent toutes les semaines de la saison');
		$this->assertEquals('1', $grid->getAttribute('data-season-first'), 'la graduation de la semaine 1 ouvre l\'axe, d\'où le script compte les autres');

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$tips = array_map(fn(\Dom\Element $tip) => (int)$tip->getAttribute('data-week'), iterator_to_array($document->querySelectorAll('#toolbox-composition-grid > .util-tip[data-week]')));
		$weeksBasket = array_map(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['week'], iterator_to_array($eCompositionFormula['cCompositionWeek']));

		$this->assertTrue(count($weeksBasket) > 26, 'des paniers sur les deux semestres, sans quoi le contrôle des bulles ne verrait rien');
		$this->assertEquals(array_values($weeksBasket), $tips, 'une bulle par semaine qui a un panier');

	}

	/**
	 * L'écart de la formule se règle, recompose la formule, et ne se lit plus que dans la légende de
	 * l'orange (r32, retour du 30/09, « vire ce bloc ») : réglé à 10 %, la légende dit
	 * « ± 10 % », les tuiles ne le disent plus, ni ne comptent les semaines au-delà, et les semaines
	 * en alerte sont celles que `isGap()` trouve à 10 %.
	 */
	public function testSettingsGap(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];

		$this->assertEquals(5, $eCompositionFormula['gap'], 'l\'écart par défaut');

		$weekIdsBefore = CompositionWeekLib::getByFormula($eCompositionFormula)->getIds();

		$eCompositionFormula['gap'] = 10;
		CompositionFormulaLib::update($eCompositionFormula, ['gap']);

		$this->assertEquals([], array_intersect($weekIdsBefore, CompositionWeekLib::getByFormula($eCompositionFormula)->getIds()), 'l\'écart recompose la formule');

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
			$this->assertEquals(CompositionLib::isGap((float)$eCompositionFormula['price'], $eCompositionWeek['value'], 10), $eCompositionWeek['gap'], 'la semaine '.$eCompositionWeek['week'].' en alerte à 10 %');
		}

		$this->assertFalse(array_key_exists('weeksOver', $eCompositionFormula['summary']), 'plus de décompte des semaines au-delà');

	}

	/**
	 * « Modifier les paniers » (r31, #96) : un seul envoi pour toutes les tailles, chacune sur ses champs
	 * (`price[<id>]`), lus par `buildIndex()`. Seule la taille dont un champ qui recompose a changé
	 * refait ses semaines : un nom seul ne recompose rien, et l'autre taille garde les siennes. Un
	 * refus se range sous le champ de sa taille, et rien ne s'écrit, pas même le changement valide de
	 * l'autre taille. La réponse ferme le panneau et rend les paniers, sur l'onglet d'où part la roue.
	 */
	public function testSettingsSubmit(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSettingsSubmit(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSettingsSubmit(\structure\Structure $eStructure): void {

		require_once __DIR__.'/../view/composition.v.php';

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$small = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$large = $this->addFormula(CompositionPlanLib::getByStructure($eStructure), 'Le grand panier', 30.0);

		$weekIds = fn(CompositionFormula $e) => CompositionWeekLib::getByFormula($e)->getIds();
		$read = fn(CompositionFormula $e) => CompositionFormulaLib::getByPlan($eCompositionPlan)[$e['id']];

		// Ce que le panneau enverrait sans rien toucher, relu en base, et les changements par-dessus.
		$input = function(array $changes, string $origin = '') use($eStructure, $eCompositionPlan): array {

			$input = ['id' => $eStructure['id'], 'origin' => $origin];

			foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $formulaId => $eCompositionFormula) {

				foreach(['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax'] as $property) {
					$input[$property][$formulaId] = (string)$eCompositionFormula[$property];
				}

				$input['base'][$formulaId] = $eCompositionFormula['base']->empty() ? '' : (string)$eCompositionFormula['base']['id'];

				foreach($changes[$formulaId] ?? [] as $property => $value) {
					$input[$property][$formulaId] = $value;
				}

			}

			return $input;

		};

		$weeksSmall = $weekIds($small);
		$weeksLarge = $weekIds($large);

		$action = $this->callRoute('POST', 'toolbox/composition:doSettings', $input([
			$small['id'] => ['name' => 'Le petit panier'],
			$large['id'] => ['price' => '32'],
		]));

		$this->assertTrue($action instanceof \ViewAction, 'l\'envoi est accepté : '.($action === NULL ? 'aucune action' : get_class($action)));
		$this->assertEquals(['Le petit panier', 32.0], [$read($small)['name'], (float)$read($large)['price']], 'chaque taille prend ses propres champs');
		$this->assertEquals($weeksSmall, $weekIds($small), 'un nom seul ne recompose rien');
		$this->assertEquals([], array_intersect($weeksLarge, $weekIds($large)), 'le prix recompose la grande taille');

		$data = new \ReflectionProperty($action, 'data')->getValue($action);
		$response = $this->getViewChanges('composition', 'doSettings', $data, ['js']);

		$this->assertEquals(['#toolbox-composition-proposal'], array_keys($response['outer']), 'la réponse rend les paniers, et eux seuls');
		$this->assertFalse(str_contains($response['outer']['#toolbox-composition-proposal'], 'id="toolbox-composition-synthesis"'), 'sur l\'onglet d\'une taille');

		$action = $this->callRoute('POST', 'toolbox/composition:doSettings', $input([], 'synthesis'));
		$data = new \ReflectionProperty($action, 'data')->getValue($action);

		$this->assertTrue(str_contains($this->getViewChanges('composition', 'doSettings', $data, ['js'])['outer']['#toolbox-composition-proposal'], 'id="toolbox-composition-synthesis"'), 'ou sur la synthèse, d\'où part la roue');
		$this->assertEquals($weeksSmall, $weekIds($small), 'un envoi sans changement ne recompose rien');

		// Un refus.
		$weeksLarge = $weekIds($large);

		$action = $this->callRoute('POST', 'toolbox/composition:doSettings', $input([
			$small['id'] => ['name' => 'Un autre nom'],
			$large['id'] => ['price' => '-1'],
		]));

		$this->assertTrue($action instanceof \FailAction, 'un prix négatif est refusé');

		// `[espace de noms, nom, arguments, champ]` (`Fail::log()`).
		$fails = array_map(fn(array $fail) => $fail[1].' '.$fail[3], new \ReflectionProperty($action, 'fail')->getValue($action)->get());

		$this->assertEquals(['CompositionFormula::price.check price['.$large['id'].']'], $fails, 'le refus se range sous le prix de la grande taille');
		$this->assertEquals('Le petit panier', $read($small)['name'], 'rien ne s\'écrit, pas même le nom valide de la petite');
		$this->assertEquals($weeksLarge, $weekIds($large), 'ni ne se recompose');

		// Le prix se construit avant la base : la grande à 10 €, appuyée dans le même envoi sur la
		// petite à 18 €, est refusée sur sa base, et non comparée au prix qu'elle avait.
		$action = $this->callRoute('POST', 'toolbox/composition:doSettings', $input([
			$large['id'] => ['price' => '10', 'base' => (string)$small['id']],
		]));

		$this->assertTrue($action instanceof \FailAction, 'une base plus chère que le prix posté est refusée');
		$this->assertEquals(['base['.$large['id'].']'], array_column(new \ReflectionProperty($action, 'fail')->getValue($action)->get(), 3), 'sous le champ de la base de la grande taille');
		$this->assertEquals(32.0, (float)$read($large)['price'], 'et rien ne s\'écrit');

	}

	/**
	 * Le nombre de légumes se règle comme l'écart (r11) : 4 à 8 par défaut, chaque borne dans « Régler
	 * les paniers » (r31, #96), et un plafond abaissé recompose la formule, dont aucune semaine ne
	 * dépasse ensuite le nouveau plafond. La tuile dit les bornes, et l'annotation ce que les paniers
	 * portent.
	 */
	public function testSettingsLines(): void {

		// La roue de « Modifier les paniers » ne se rend qu'à qui gère la structure (`assertUpdate`).
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$this->assertEquals([4, 8], [$eCompositionFormula['linesMin'], $eCompositionFormula['linesMax']], 'les bornes par défaut');
		$this->assertTrue($eCompositionFormula['summary']['linesMin'] >= 4 and $eCompositionFormula['summary']['linesMax'] <= 8, 'les paniers dans les bornes par défaut');
		$this->assertEquals(8, $eCompositionFormula['summary']['linesMax'], 'le plafond atteint à 18 €');

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$html = new CompositionUi()->getSettings($eCompositionPlan, $eCompositionFormula);
			$eCompositionPlan['structure'] = $eStructure;
			CompositionPlanLib::loadSettings($eCompositionPlan);
			$panelSettings = new CompositionUi()->getSettingsPanel($eCompositionPlan, NULL, NULL);
			$panel = $panelSettings->body;
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

		$this->assertFalse(str_contains($html, 'post-property='), 'la tuile ne se règle plus sur place');

		foreach(['linesMin', 'linesMax'] as $property) {
			$this->assertTrue(str_contains($panel, 'name="'.$property.'['.$eCompositionFormula['id'].']"'), 'le champ de '.$property.' dans « Modifier les paniers »');
		}

		$weekIdsBefore = CompositionWeekLib::getByFormula($eCompositionFormula)->getIds();

		$eCompositionFormula['linesMax'] = 5;
		CompositionFormulaLib::update($eCompositionFormula, ['linesMax']);

		$this->assertEquals([], array_intersect($weekIdsBefore, CompositionWeekLib::getByFormula($eCompositionFormula)->getIds()), 'le plafond recompose la formule');

		$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];

		$this->assertEquals(5, $eCompositionFormula['linesMax'], 'le plafond écrit');
		$this->assertTrue($eCompositionFormula['summary']['linesMax'] <= 5, 'aucune semaine au-delà du plafond');

		$weekIdsBefore = CompositionWeekLib::getByFormula($eCompositionFormula)->getIds();

		$eCompositionFormula['name'] = 'Le panier renommé';
		CompositionFormulaLib::update($eCompositionFormula, ['name']);

		$this->assertEquals($weekIdsBefore, CompositionWeekLib::getByFormula($eCompositionFormula)->getIds(), 'un autre réglage ne recompose rien');

	}

	/**
	 * La fenêtre naturelle d'une espèce se compte sur les semaines demandées, et les espèces qu'elle
	 * rend sont exactement celles qu'un plan peut proposer.
	 */
	public function testNaturalWeeks(): void {

		$source = CompositionLib::getSource();

		$natural = CompositionLib::getNaturalWeeks(range(1, 52), 2026, $source);
		$naturalSpring = CompositionLib::getNaturalWeeks([14, 15], 2026, $source);

		$this->assertTrue($natural !== [], 'une base qui propose quelque chose');

		$plantIds = array_keys($natural);
		sort($plantIds);

		$this->assertEquals(CompositionLib::getProposable(range(1, 52), 2026, $source), $plantIds, 'les espèces proposables');

		foreach($naturalSpring as $plantId => $weeks) {
			$this->assertTrue($weeks >= 1 and $weeks <= 2, 'deux semaines au plus : '.$plantId);
			$this->assertTrue($natural[$plantId] >= $weeks, 'moins qu\'à l\'année : '.$plantId);
		}

	}

	/**
	 * Un formulaire poste les semaines en chaînes : les chiffres deviennent des entiers, le reste est
	 * refusé par le contrôle, et une liste vide aussi.
	 */
	public function testBuildWeeks(): void {

		$eCompositionPlan = CompositionPlanLib::getForStart($this->getStructureWithoutPlan(), 2026);

		$this->assertEquals([], $this->buildWeeks($eCompositionPlan, ['14', '15', '52']), 'chiffres admis');
		$this->assertEquals([14, 15, 52], $eCompositionPlan['weeks'], 'convertis en entiers');

		$this->assertEquals(['CompositionPlan::weeks.check'], $this->buildWeeks($eCompositionPlan, ['52', '53']), 'semaine 53 d\'une année ISO longue');

		$this->assertEquals(['CompositionPlan::weeks.check'], $this->buildWeeks($eCompositionPlan, ['14', '15a']), 'autre chose qu\'un chiffre');
		$this->assertEquals(['CompositionPlan::weeks.check'], $this->buildWeeks($eCompositionPlan, NULL), 'aucune semaine cochée');
		$this->assertEquals(['CompositionPlan::weeks.check'], $this->buildWeeks($eCompositionPlan, ['0']), 'semaine 0');

	}

	/**
	 * Changer les semaines livrées recompose chaque taille du plan sur les semaines retenues, et
	 * seulement sur elles, sans écrire d'autre plan.
	 */
	public function testUpdateWeeksRecomposes(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$this->assertEquals([], $this->buildWeeks($eCompositionPlan, ['14', '15', '16']), 'semaines admises');

		CompositionPlanLib::update($eCompositionPlan, ['weeks']);

		$this->assertEquals([14, 15, 16], CompositionPlanLib::getByStructure($eStructure)['weeks'], 'semaines enregistrées');

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);

		$this->assertEquals(2, $cCompositionFormula->count(), 'deux tailles');

		foreach($cCompositionFormula as $eCompositionFormula) {
			$this->assertEquals([14, 15, 16], CompositionWeek::model()->whereFormula($eCompositionFormula)->sort(['week' => SORT_ASC])->getColumn('week'), 'recomposée sur les semaines retenues : '.$eCompositionFormula['name']);
		}

		$this->buildWeeks($eCompositionPlan, ['20']);
		CompositionPlanLib::update($eCompositionPlan, ['weeks']);

		$this->assertEquals(1, CompositionPlan::model()->whereStructure($eStructure)->whereSeason(2026)->count(), 'un seul plan');
		$this->assertEquals([20], CompositionWeek::model()->whereFormula(CompositionFormulaLib::getByPlan($eCompositionPlan)->first())->getColumn('week'), 'semaines remplacées');

	}

	/**
	 * Le tri sur les octets range un nom accentué après tous ceux qui ne le sont pas : celui de
	 * l'onglet « Espèces » range « Céleri-rave » avant « Chou ».
	 */
	public function testSpeciesOrderAccents(): void {

		$cCompositionSpecies = new \Collection([
			1 => new CompositionSpecies(['plant' => new \plant\Plant(['id' => 1, 'name' => 'Chou'])]),
			2 => new CompositionSpecies(['plant' => new \plant\Plant(['id' => 2, 'name' => 'Céleri-rave'])]),
			3 => new CompositionSpecies(['plant' => new \plant\Plant(['id' => 3, 'name' => 'Carotte'])]),
		]);

		$cCompositionSpecies->sort(['plant' => ['name' => SORT_ASC]], natural: TRUE);

		$this->assertEquals([3, 2, 1], $cCompositionSpecies->getKeys(), 'Carotte, Céleri-rave, Chou');

	}

	/**
	 * Le panneau « Semaine par semaine » épargne les semaines corrigées (lot N), comme le menu d'une
	 * semaine : une semaine corrigée qu'on livre encore garde ses lignes, une semaine corrigée qu'on
	 * ne livre plus part, et le panneau le fait confirmer en la nommant. Les semaines non corrigées
	 * reprennent la proposition.
	 */
	public function testUpdateWeeksSparesAmended(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->find(fn(CompositionFormula $e) => $e['name'] === 'Le panier', limit: 1);

		CompositionWeekLib::amend(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 21));
		CompositionWeekLib::amend(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 30));

		$this->assertEquals([21 => ['Le panier'], 30 => ['Le panier']], CompositionPlanLib::getFormulasAmended($eCompositionPlan), 'deux semaines corrigées dans une taille');

		// Le panneau porte la perte sur les deux jetons corrigés, et eux seuls.
		$eCompositionPlanPanel = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanPanel['structure'] = $eStructure;
		$eCompositionPlanPanel['seasonMonths'] = CompositionLib::getSeasonMonths(2026);
		$eCompositionPlanPanel['formulasAmended'] = CompositionPlanLib::getFormulasAmended($eCompositionPlanPanel);

		$panel = new CompositionUi()->getWeeksPanel($eCompositionPlanPanel, NULL);
		$html = $panel->body.$panel->footer;

		$this->assertEquals(2, substr_count($html, 'data-loss="'), 'deux jetons portent leur perte');

		$before = $this->getPlanLines($eCompositionPlan);

		$this->assertEquals([], $this->buildWeeks($eCompositionPlan, array_map('strval', array_values(array_diff(range(10, 40), [30])))), 'semaines admises');

		CompositionPlanLib::update($eCompositionPlan, ['weeks']);

		$after = $this->getPlanLines($eCompositionPlan);

		$weekLines = fn(array $lines, string $formula, int $week) => array_keys(array_filter($lines, fn(array $line) => ($line['formula'] === $formula and $line['week'] === $week)));

		$this->assertTrue($weekLines($before, 'Le panier', 21) !== [], 'la semaine 21 servait des espèces : le contrôle voit quelque chose');
		$this->assertEquals($weekLines($before, 'Le panier', 21), $weekLines($after, 'Le panier', 21), 'la semaine corrigée garde ses lignes, les mêmes');
		$this->assertTrue(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 21)['amended'], 'et reste corrigée');

		$this->assertEquals([], array_filter($after, fn(array $line) => $line['week'] === 30), 'la semaine 30, corrigée mais plus livrée, part dans les deux tailles');

		$this->assertEquals([21 => ['Le panier']], CompositionPlanLib::getFormulasAmended($eCompositionPlan), 'seule la semaine 21 reste corrigée');

		foreach([['Le grand panier', 21], ['Le panier', 22]] as [$formula, $week]) {
			$this->assertTrue(array_intersect($weekLines($before, $formula, $week), $weekLines($after, $formula, $week)) === [], 'semaine '.$week.' de '.$formula.', non corrigée : elle reprend la proposition');
		}

	}

	/**
	 * Une espèce retirée (`CompositionSpeciesLib::exclude()`) épargne les semaines corrigées (lot N) :
	 * elle quitte les semaines non corrigées et reste dans la semaine corrigée, qui ne bouge pas.
	 */
	public function testExcludeSparesAmended(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$before = $this->getPlanLines($eCompositionPlan);

		$speciesId = $this->getSpeciesServedEverywhere($before);

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		$lineAmended = array_values(array_filter($before, fn(array $line) => $line['species'] === $speciesId))[0];

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->find(fn(CompositionFormula $e) => $e['name'] === $lineAmended['formula'], limit: 1);

		CompositionWeekLib::amend(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $lineAmended['week']));

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['status'], ['status' => CompositionSpecies::EXCLUDED]);
		$this->assertEquals([], array_column($fw->get(), 1), 'retrait admis');

		CompositionSpeciesLib::exclude($eCompositionSpecies);

		$after = $this->getPlanLines($eCompositionPlan);

		$isAmended = fn(array $line) => ($line['formula'] === $lineAmended['formula'] and $line['week'] === $lineAmended['week']);

		$this->assertEquals(array_keys(array_filter($before, $isAmended)), array_keys(array_filter($after, $isAmended)), 'la semaine corrigée garde ses lignes, les mêmes');
		$this->assertEquals([$lineAmended['formula'].'-'.$lineAmended['week']], array_values(array_unique(array_map(fn(array $line) => $line['formula'].'-'.$line['week'], array_filter($after, fn(array $line) => $line['species'] === $speciesId)))), 'l\'espèce ne reste que dans la semaine corrigée');

	}

	/**
	 * Une case est verte exactement quand sa semaine est dans la saison réglée de l'espèce
	 * (`CompositionSpeciesLib::getWindowsByPlan()`, r29, #87), et chaque candidate d'une semaine que
	 * la semaine ne sert pas a une case verte qui s'ouvre. La référence est
	 * `CompositionLib::getCandidates()`, lu avec les exclusions et les fenêtres du plan, moins les
	 * espèces que la semaine sert déjà. Une case qui s'ouvre sans être candidate
	 * est de saison : c'est une semaine où la communauté ne relève pas de prix. Éprouvé sur les
	 * semaines 10 à 45 d'un plan monté par le contrôle, au départ, après le retrait d'une espèce, une
	 * fenêtre réglée à la main et une case allumée à la main.
	 */
	public function testCandidatesAreSeasonCells(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 45));

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$this->checkCandidatesAreSeasonCells($eStructure, $source, 'au départ');

		// Une espèce retirée : ni candidate, ni case de saison.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$speciesIdServed = $eCompositionPlanScreen['eCompositionFormula']['cCompositionWeek']->first()['cCompositionLine']->first()['species']['id'];

		CompositionSpeciesLib::exclude($this->getSpecies($eCompositionPlan, $speciesIdServed));

		$this->checkCandidatesAreSeasonCells($eStructure, $source, 'une espèce retirée');

		// Une fenêtre réglée à la main, sur une espèce servie la dernière semaine : ses cases hors de
		// la fenêtre perdent leur teinte, et elle n'y est plus candidate.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$speciesIdWindow = $eCompositionPlanScreen['eCompositionFormula']['cCompositionWeek']->last()['cCompositionLine']->first()['species']['id'];

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesIdWindow);
		$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpecies['windowWeeks'] = [[10, 20]];

		CompositionSpeciesLib::update($eCompositionSpecies, ['status', 'windowWeeks']);

		$this->checkCandidatesAreSeasonCells($eStructure, $source, 'une fenêtre réglée à la main');

		// Une case allumée à la main : elle sert l'espèce, qui n'est plus candidate cette semaine.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionWeek = $eCompositionPlanScreen['eCompositionFormula']['cCompositionWeek']->first();
		$speciesIds = $this->getCandidatesUnserved($eCompositionPlanScreen, $eCompositionWeek, $source);

		if($speciesIds === []) {
			$this->assertFail('la semaine '.$eCompositionWeek['week'].' n\'a aucune candidate : la case allumée ne serait pas éprouvée');
			return;
		}

		// Comme la case de la grille (`compositionLine:doCreate`) : l'espèce seule, le reste vient de la règle.
		CompositionLineLib::create(new CompositionLine([
			'week' => CompositionWeekLib::getByFormulaAndWeek($eCompositionPlanScreen['eCompositionFormula'], $eCompositionWeek['week']),
			'species' => new CompositionSpecies(['id' => $speciesIds[0]]),
			'origin' => CompositionLine::USER,
		]));

		$this->checkCandidatesAreSeasonCells($eStructure, $source, 'une case allumée à la main');

	}

	/**
	 * Une quantité posée hors saison (r29, #83, retour du 29/09, « permettre d'ajouter des
	 * produits même hors planning ») : la case d'une semaine livrée hors de la saison de l'espèce
	 * s'ouvre, le champ de son menu crée la ligne comme `compositionLine:doCreate`, et une
	 * recomposition du plan (`CompositionLib::recomposePlan()`, celle d'un réglage d'espèce ou d'une
	 * exclusion) la laisse telle quelle, avec sa quantité, parce que sa semaine est corrigée. La case
	 * reste grise sous son rond.
	 */
	public function testOutOfSeasonLineKept(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 45));

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlanScreen['eCompositionFormula'];

		$found = NULL;

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			foreach($eCompositionFormula['grid'] as $group) {
				foreach($group['species'] as $row) {

					if(
						$found === NULL and
						CompositionLib::isSold($row['species']['id'], $source) and
						isset($row['lines'][$eCompositionWeek['week']]) === FALSE and
						isset($row['weeksSeason'][$eCompositionWeek['week']]) === FALSE
					) {
						$found = [$eCompositionWeek, $row];
					}

				}
			}

		}

		if($found === NULL) {
			$this->assertFail('aucune case hors saison sur le plan : le contrôle ne verrait rien');
			return;
		}

		[$eCompositionWeek, $row] = $found;

		$week = $eCompositionWeek['week'];
		$speciesId = $row['species']['id'];

		// Le champ de son menu est éprouvé par `checkCellMenuRender()`, avec un gestionnaire connecté.
		$this->assertTrue(isset($row['open'][$week]), 'la case hors saison s\'ouvre');

		// Deux pas au-dessus du minimum arrondi au pas : une quantité que la proposition n'écrit pas.
		$sourcePlan = CompositionLib::getSourceByPlan($eCompositionPlan);
		$step = CompositionLib::getStep($speciesId, $sourcePlan, CompositionSpeciesLib::getStepsByPlan($eCompositionPlan));
		$quantity = round((ceil($step['minimum'] / $step['step']) + 2) * $step['step'], 2);

		$fw = new \FailWatch();

		$eCompositionLine = new CompositionLine([
			'week' => $eCompositionWeek,
			'origin' => CompositionLine::USER,
		]);

		$eCompositionLine->build(['species', 'unit', 'quantity'], [
			'species' => $speciesId,
			'unit' => $sourcePlan['units'][$speciesId]['unit'],
			'quantity' => $quantity,
		], new \PropertyList('create'));

		$this->assertEquals([], array_map(fn($fail) => $fail[1], $fw->get()), 'le serveur ne refuse pas une espèce hors saison');

		CompositionLineLib::create($eCompositionLine);

		CompositionLib::recomposePlan($eCompositionPlan, $source, CompositionLib::getPrices($eCompositionPlan, $source));

		$eCompositionLineKept = CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, new CompositionSpecies(['id' => $speciesId]));

		$this->assertTrue($eCompositionLineKept->notEmpty(), 'la ligne hors saison survit à la recomposition');
		$this->assertEquals([$quantity, CompositionLine::USER], [(float)$eCompositionLineKept['quantity'], $eCompositionLineKept['origin']], 'avec sa quantité, posée à la main');

		$grid = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['grid'];
		$rowAfter = array_values(array_filter(array_merge(...array_column($grid, 'species')), fn(array $row) => $row['species']['id'] === $speciesId))[0];

		$this->assertTrue(isset($rowAfter['lines'][$week]), 'la grille la sert');
		$this->assertFalse(isset($rowAfter['weeksSeason'][$week]), 'sur une case restée hors saison, grise sous son rond');

	}

	/**
	 * Un seul chou par panier dans la proposition (`CompositionLib::CABBAGE`) : dans une semaine qui
	 * sert un chou, un autre chou de saison n'a pas de part et la semaine est dans ses `refused`. Sa
	 * case reste verte et s'ouvre pourtant sur son ajout (`CompositionLib::applyAddableToGrid()`) : à la
	 * main, le panier accepte un second chou (r34d, retour du 30/09, « le user fait ce qu'il
	 * veut »).
	 */
	public function testGridCabbage(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);
		$this->createStarted($eStructure, 2026, range(1, 52));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

		$refused = 0;

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			$week = $eCompositionWeek['week'];
			$speciesIdsServed = $eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds();
			$cabbagesServed = array_filter($speciesIdsServed, fn(int $speciesId) => CompositionLib::isCabbage($speciesId, $source));

			$this->assertTrue(count($cabbagesServed) <= 1, 'semaine '.$week.' : un chou au plus');

			foreach($eCompositionFormula['grid'] as $group) {

				foreach($group['species'] as $row) {

					if(in_array($week, $row['refused'], TRUE) === FALSE) {
						continue;
					}

					$refused++;

					$this->assertTrue(CompositionLib::isCabbage($row['species']['id'], $source), 'semaine '.$week.' : seul un chou est refusé');
					$this->assertTrue($cabbagesServed !== [] and in_array($row['species']['id'], $cabbagesServed, TRUE) === FALSE, 'semaine '.$week.' : refusé parce qu\'un autre chou est servi');
					$this->assertTrue(isset($row['weeksSeason'][$week]), 'semaine '.$week.' : vert, puisque de saison (r29, #87)');
					$this->assertTrue(isset($row['open'][$week]), 'semaine '.$week.' : et ouvert');

					$grid = CompositionLib::applyAddableToGrid($eCompositionFormula['grid'], $week, $source, $steps);

					$this->assertTrue(in_array($row['species']['id'], $this->getAddableSpeciesIds($grid), TRUE), 'semaine '.$week.' : sa case le propose');

				}

			}

		}

		$this->assertTrue($refused > 0, 'aucun chou refusé sur l\'année : le contrôle ne verrait rien');

		$this->checkToggledCabbages($eCompositionPlan, $source);

	}

	/**
	 * La bulle de la semaine `$week` (r32, option A de la planche) : en tête, « Semaine N » et ses
	 * jours, puis deux chiffres côte à côte, le nombre de produits de son panier (r30, #89) et ce que
	 * vaut ce panier, dont le libellé rappelle le prix de la taille quand la semaine s'en écarte.
	 * Nombre et valeur sont relus sur les lignes écrites, et non sur ce que l'écran a préparé.
	 * Appelé sur la grille entière puis sur la bulle que rend une case allumée, qui gagne une ligne :
	 * les chiffres suivent le geste.
	 */
	private function assertTipLines(CompositionPlan $eCompositionPlan, int $week, string $html): void {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$eCompositionWeek = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan))->find(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['week'] === $week)->first();
		$lines = CompositionLine::model()
			->whereWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week))
			->count();

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
		$texts = fn(string $selector) => array_map(fn(\Dom\Element $node) => $node->textContent, iterator_to_array($document->querySelectorAll($selector)));

		$this->assertEquals([(string)$lines, \util\TextUi::money($eCompositionWeek['value'], precision: 2)], $texts('.toolbox-composition-tip-figures > div > .toolbox-composition-tip-value'), 'bulle de la semaine '.$week.', '.$lines.' produit(s) et la valeur du panier');

	}

	/**
	 * La réponse d'un chou allumé ou éteint (`CompositionUi::getToggled()`) ne rend que son rang, comme
	 * celle de toute espèce : un autre chou servi ne ferme aucune case (r34d). La semaine retenue sert
	 * un chou et en porte d'autres dans sa grille, sans quoi le contrôle ne distinguerait pas la règle
	 * d'avant, qui rendait aussi leurs rangs.
	 */
	private function checkToggledCabbages(CompositionPlan $eCompositionPlan, array $source): void {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$cabbagesGrid = [];

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				if(CompositionLib::isCabbage($row['species']['id'], $source)) {
					$cabbagesGrid[] = $row['species']['id'];
				}
			}
		}

		$rows = fn(array $blocks) => array_values(array_unique(array_map(fn(string $selector) => explode(' ', $selector)[0], array_filter(array_keys($blocks['inner']), fn(string $selector) => str_starts_with($selector, '#toolbox-composition-grid-species-')))));

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			$week = $eCompositionWeek['week'];
			$cabbagesServed = array_values(array_filter($eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds(), fn(int $speciesId) => CompositionLib::isCabbage($speciesId, $source)));

			if($cabbagesServed === [] or array_diff($cabbagesGrid, $cabbagesServed) === []) {
				continue;
			}

			$speciesId = $cabbagesServed[0];

			$this->assertEquals(['#toolbox-composition-grid-species-'.$speciesId], $rows(new CompositionUi()->getToggled($eCompositionPlan, $week, $speciesId)), 'semaine '.$week.' : le chou allumé ou éteint ne rend que son rang');

			return;

		}

		$this->assertFail('aucune semaine ne sert un chou à côté d\'un autre chou de la grille : le contrôle ne verrait rien');

	}

	private function checkCandidatesAreSeasonCells(\structure\Structure $eStructure, array $source, string $state): void {

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);

		$candidates = 0;
		$openOut = 0;

		$weeksBasket = $eCompositionFormula['cCompositionWeek']->getColumn('week');

		foreach($eCompositionPlan['weeks'] as $week) {

			$eCompositionWeek = $eCompositionFormula['cCompositionWeek']->find(fn(CompositionWeek $e) => $e['week'] === $week, limit: 1, default: new CompositionWeek(['week' => $week, 'cCompositionLine' => new \Collection()]));

			$speciesIdsCandidate = $this->getCandidatesUnserved($eCompositionPlan, $eCompositionWeek, $source);

			$speciesIdsOpen = [];
			$speciesIdsWrong = [];
			$green = [];

			foreach($eCompositionFormula['grid'] as $group) {
				foreach($group['species'] as $row) {

					$inWindow = in_array($week, $windows[$row['species']['id']] ?? [], TRUE);

					// Une semaine livrée sans panier n'a pas de case (`getGrid()`).
					if(
						$eCompositionWeek['cCompositionLine']->notEmpty() and
						isset($row['weeksSeason'][$week]) !== $inWindow
					) {
						$green[] = $row['species']['id'];
					}

					if(isset($row['lines'][$week]) === FALSE) {

						$open = isset($row['open'][$week]);

						if($open) {
							$speciesIdsOpen[] = $row['species']['id'];
						}

						// Toute case d'une semaine livrée s'ouvre, de saison ou non (r29, #83), sauf celle
						// dont l'ajout serait refusé, l'espèce sans unité au panier
						// (`CompositionLine::properties()`). Un chou s'ouvre à côté d'un autre (r34d).
						$expected = (
							in_array($week, $weeksBasket, TRUE) and
							CompositionLib::isSold($row['species']['id'], $source)
						);

						if($open !== $expected) {
							$speciesIdsWrong[] = $row['species']['id'];
						}

						if($open and $inWindow === FALSE) {
							$openOut++;
						}

					}

				}
			}

			$missing = array_diff($speciesIdsCandidate, $speciesIdsOpen);

			$this->assertEquals([], $green, $state.', semaine '.$week.' : vert qui ne suit pas la saison réglée, '.implode(', ', $green));
			$this->assertEquals([], $missing, $state.', semaine '.$week.' : candidates sans case ouverte, '.implode(', ', $missing));
			$this->assertEquals([], $speciesIdsWrong, $state.', semaine '.$week.' : cases ouvertes ou fermées à tort, '.implode(', ', $speciesIdsWrong));

			$candidates += count($speciesIdsCandidate);

		}

		$this->assertTrue($candidates > 0, $state.' : aucune candidate sur toute l\'année, le contrôle ne verrait rien');
		$this->assertTrue($openOut > 0, $state.' : aucune case ouverte hors de la saison, le contrôle ne verrait pas #83');

	}

	/**
	 * Les espèces qu'un clic peut allumer dans la semaine : les candidates du plan moins celles que
	 * la semaine sert déjà. Un chou en est même quand la semaine en sert un autre (r34d).
	 */
	private function getCandidatesUnserved(CompositionPlan $eCompositionPlan, CompositionWeek $eCompositionWeek, array $source): array {

		$candidates = CompositionLib::getCandidates(
			$eCompositionWeek['week'],
			$eCompositionPlan['season'],
			CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan),
			$source
		);

		return array_values(array_diff($candidates, $eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds()));

	}

	/**
	 * Un statut absent de l'envoi est refusé, et une espèce ne passe pas à la fenêtre réglée sans sa
	 * fenêtre : l'écran ne poste jamais ni l'un ni l'autre, un envoi forgé si.
	 */
	public function testBuildStatus(): void {

		$eCompositionPlan = $this->createStarted($this->getStructureWithoutPlan(), 2026, range(10, 40));

		// Une espèce retirée, sans saison écrite : une espèce cultivée porte déjà la sienne (lot r27).
		$eCompositionSpeciesExcluded = CompositionSpeciesLib::getByPlan($eCompositionPlan)->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::EXCLUDED)->first();

		$build = function(array $input) use($eCompositionSpeciesExcluded): array {
			$fw = new \FailWatch();
			$eCompositionSpeciesExcluded->build(['status'], $input);
			return array_column($fw->get(), 1);
		};

		$this->assertEquals(['CompositionSpecies::status.check'], $build([]), 'statut absent de l\'envoi');
		$this->assertEquals(['CompositionSpecies::status.window'], $build(['status' => CompositionSpecies::CUSTOM]), 'réglée sans fenêtre');

		// Avec sa fenêtre dans le même envoi, c'est le contrôle de la fenêtre qui en répond. Il lit la
		// saison du plan en base.
		$fw = new \FailWatch();

		$eCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan)->first();
		$eCompositionSpecies->build(['status', 'windowWeeks'], ['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[14, 20]]]);

		$this->assertEquals([], array_column($fw->get(), 1), 'réglée avec sa fenêtre');

	}

	/**
	 * Décocher une espèce réglée à la main efface sa fenêtre : le contrôle de `windowWeeks` exige ce
	 * `NULL` de toute espèce qui n'est pas réglée.
	 */
	public function testUpdateStatusClearsWindow(): void {

		$eCompositionPlan = $this->createStarted($this->getStructureWithoutPlan(), 2026, range(10, 40));

		$eCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan)->first();
		$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpecies['windowWeeks'] = [[14, 20]];

		CompositionSpeciesLib::update($eCompositionSpecies, ['status', 'windowWeeks']);

		$this->assertEquals([[14, 20]], CompositionSpecies::model()->select('windowWeeks')->whereId($eCompositionSpecies['id'])->get()['windowWeeks'], 'fenêtre réglée');

		$eCompositionSpecies['status'] = CompositionSpecies::EXCLUDED;

		CompositionSpeciesLib::update($eCompositionSpecies, ['status']);

		$eCompositionSpeciesRead = CompositionSpecies::model()->select(['status', 'windowWeeks'])->whereId($eCompositionSpecies['id'])->get();

		$this->assertEquals(CompositionSpecies::EXCLUDED, $eCompositionSpeciesRead['status'], 'décochée');
		$this->assertEquals(NULL, $eCompositionSpeciesRead['windowWeeks'], 'fenêtre effacée');

	}

	/**
	 * Une taille ajoutée vient derrière celles du départ, à son prix et avec son nombre de paniers, et
	 * a sa proposition sur chaque semaine livrée.
	 */
	public function testAdd(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionFormula = new CompositionFormula(['id' => NULL, 'plan' => $eCompositionPlan]);

		$this->assertEquals([], $this->buildFormula($eCompositionFormula, ['name' => 'Le grand panier', 'price' => '30', 'baskets' => '12']), 'taille admise');

		CompositionFormulaLib::add($eCompositionFormula);

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);

		$this->assertEquals([['Le panier', 0], ['Le grand panier', 1]], $cCompositionFormula->makeArray(fn(CompositionFormula $e) => [$e['name'], $e['position']]), 'ajoutée derrière');
		$this->assertEquals(30.0, (float)$cCompositionFormula->last()['price'], 'prix de la taille ajoutée');
		$this->assertEquals(12, $cCompositionFormula->last()['baskets'], 'et son nombre de paniers');

		foreach($cCompositionFormula as $eCompositionFormulaRead) {
			$this->assertEquals(count($eCompositionPlan['weeks']), CompositionWeek::model()->whereFormula($eCompositionFormulaRead)->count(), 'une semaine par semaine livrée : '.$eCompositionFormulaRead['name']);
		}

	}

	/**
	 * Le nom, le prix et le nombre de paniers sont exigés, chacun refusé sur son champ. Le prix est
	 * positif, et une taille livre au moins un panier par semaine.
	 */
	public function testBuildFormula(): void {

		$eCompositionPlan = CompositionPlanLib::getForStart($this->getStructureWithoutPlan(), 2026);

		$fw = new \FailWatch();

		$eCompositionFormula = new CompositionFormula(['id' => NULL, 'plan' => $eCompositionPlan]);
		$eCompositionFormula->build(['name', 'price', 'baskets'], ['name' => '', 'price' => '', 'baskets' => ''], new \PropertyList('create'));

		$fails = array_map(fn(array $fail) => $fail[1].' → '.$fail[3], $fw->get());

		$this->assertEquals([
			'CompositionFormula::name.check → name',
			'CompositionFormula::price.check → price',
			'CompositionFormula::baskets.check → baskets',
		], $fails, 'trois refus, chacun sur son champ');

		$this->assertEquals(['CompositionFormula::price.check'], $this->buildFormula(new CompositionFormula(['id' => NULL, 'plan' => $eCompositionPlan]), ['name' => 'Le grand panier', 'price' => '-5', 'baskets' => '12']), 'prix négatif');
		$this->assertEquals(['CompositionFormula::baskets.check'], $this->buildFormula(new CompositionFormula(['id' => NULL, 'plan' => $eCompositionPlan]), ['name' => 'Le grand panier', 'price' => '30', 'baskets' => '0']), 'aucun panier');

	}

	/**
	 * L'écran lit une taille à la fois : celle demandée si elle est du plan, la première sinon. Sans
	 * plan, il ne lit rien, et la page ouvre le départ.
	 */
	public function testForScreen(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$this->assertTrue(CompositionPlanLib::getForScreen($eStructure, NULL)->empty(), 'rien sans plan');

		$eCompositionPlanWritten = $this->createStarted($eStructure, 2026, range(10, 40));
		$eCompositionFormula = $this->addFormula($eCompositionPlanWritten, 'Le grand panier', 30.0);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, $eCompositionFormula['id']);

		$this->assertEquals($eCompositionFormula['id'], $eCompositionPlan['eCompositionFormula']['id'], 'la taille demandée');
		$this->assertTrue($eCompositionPlan['eCompositionFormula']->offsetExists('grid'), 'avec sa grille');

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, 0);

		$this->assertEquals('Le panier', $eCompositionPlan['eCompositionFormula']['name'], 'une taille hors du plan rend la première');

	}

	/**
	 * L'année en quantités (Q6, Q7) : chaque total est la somme, sur les semaines livrées, de la
	 * quantité du panier par le nombre de paniers de sa taille, par espèce et par unité, et se relit
	 * à l'identique par une requête écrite à la main. Le chiffre d'affaires est le prix de chaque
	 * taille par ses paniers et par les semaines. Une espèce cochée que la règle n'a servie dans
	 * aucun panier reste, sans quantité.
	 */
	public function testQuantities(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlanWritten = $this->createStarted($eStructure, 2026, range(10, 40));
		$eCompositionFormulaLarge = $this->addFormula($eCompositionPlanWritten, 'Le grand panier', 30.0);
		$eCompositionFormulaLarge['plan'] = $eCompositionPlanWritten;
		$eCompositionFormulaLarge['baskets'] = 12;

		CompositionFormulaLib::update($eCompositionFormulaLarge, ['baskets']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$quantities = $eCompositionPlan['quantities'];

		$this->assertEquals(22, $quantities['baskets'], 'les paniers d\'une semaine, toutes tailles');
		$this->assertEquals(31, $quantities['weeks'], 'les semaines livrées');
		$this->assertEquals(22 * 31, $quantities['basketsYear'], 'les paniers de l\'année');
		$this->assertTrue(abs(CompositionLib::getTurnover($eCompositionPlan['cCompositionFormula']->first()['cCompositionWeek'], 10) + CompositionLib::getTurnover($eCompositionPlan['cCompositionFormula']->last()['cCompositionWeek'], 12) - $quantities['turnover']) < 0.001, 'la valeur des semaines de chaque taille par ses paniers (r12)');

		$rows = CompositionLine::model()->pdo()->select(
			'SELECT l.species species, l.unit unit, f.id formula, SUM(l.quantity * f.baskets) quantity'
			.' FROM '.CompositionLine::model()->getDatabase().'.toolboxCompositionLine l'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionWeek w ON w.id = l.week'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionFormula f ON f.id = w.formula'
			.' WHERE f.plan = '.$eCompositionPlan['id']
			.' GROUP BY l.species, l.unit, f.id'
			// Sans tri, les groupes sortent dans l'ordre de jointure que MySQL choisit, et les tailles
			// d'une espèce dans celui de leurs noms quand la formule est lue en tête : la comparaison
			// stricte échouait alors sur le seul ordre des clés.
			.' ORDER BY f.position'
		)->fetchAll(\PDO::FETCH_ASSOC);

		$expected = [];

		foreach($rows as $row) {
			$key = $row['species'].'-'.$row['unit'];
			$expected[$key]['formulas'][(int)$row['formula']] = round((float)$row['quantity'], 2);
			$expected[$key]['total'] = round(($expected[$key]['total'] ?? 0.0) + (float)$row['quantity'], 2);
		}

		$read = [];
		$never = [];

		foreach($quantities['groups'] as $group) {

			foreach($group['rows'] as $row) {

				$this->assertEquals($group['organ'], $row['species']['plant']['organ'], 'rangée dans sa famille : '.$row['species']->getLabel());
				$this->assertEquals($eCompositionPlan['cCompositionFormula']->getIds(), array_keys($row['formulas']), 'toutes les tailles du plan : '.$row['species']->getLabel());

				if($row['unit']->empty()) {
					$never[] = $row['species']['id'];
					continue;
				}

				$read[$row['species']['id'].'-'.$row['unit']['id']] = [
					'formulas' => array_map(fn(float $quantity) => round($quantity, 2), array_filter($row['formulas'], fn(?float $quantity) => $quantity !== NULL)),
					'total' => round($row['total'], 2),
				];

			}

		}

		ksort($expected);
		ksort($read);

		if(count(array_filter($expected, fn(array $row) => count($row['formulas']) === 2)) === 0) {
			$this->assertFail('aucune espèce servie par les deux tailles : l\'addition des colonnes ne se verrait pas');
			return;
		}

		$this->assertEquals($expected, $read, 'chaque total se relit par la requête');

		$speciesIdsServed = array_unique(array_column($rows, 'species'));
		$speciesIdsGrown = CompositionSpecies::model()
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->getColumn('id');

		$speciesIdsNever = array_values(array_diff($speciesIdsGrown, $speciesIdsServed));

		sort($never);
		sort($speciesIdsNever);

		$this->assertEquals($speciesIdsNever, $never, 'les espèces cochées servies dans aucun panier');

		if($speciesIdsNever === []) {
			$this->assertFail('toutes les espèces cochées sont servies : le rang grisé ne se verrait pas');
			return;
		}

	}

	/**
	 * Les douze mois de la Synthèse (lot r19) : chaque espèce servie répartit son année par mois, le
	 * mois le plus chargé en haut, et la somme des mois refait l'année. Les barres paraissent deux
	 * fois, en colonne et sous le nom, jamais pour une espèce servie dans aucun panier.
	 */
	public function testQuantitiesMonths(): void {

		$season = 2026;
		$eStructure = $this->getStructureCultivated($season);

		$eCompositionPlanStarted = $this->createStarted($eStructure, $season, range(10, 40));

		// Sept paniers et non dix : des quantités par panier au dixième donnent alors des mois qui ne
		// tombent pas rond, et l'arrondi de l'échelle (lot r19b) s'éprouve.
		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlanStarted)->first();
		$eCompositionFormula['plan'] = $eCompositionPlanStarted;
		$eCompositionFormula['baskets'] = 7;
		CompositionFormulaLib::update($eCompositionFormula, ['baskets']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$errors = [];
		$counts = ['served' => 0, 'never' => 0];

		foreach($eCompositionPlan['quantities']['groups'] as $group) {

			foreach($group['rows'] as $row) {

				if($row['unit']->empty()) {

					$counts['never']++;

					if($row['months'] !== array_fill(1, 12, ['quantity' => 0.0, 'bar' => 0.0])) {
						$errors[] = $row['species']->getLabel().' jamais servie porte des mois';
					}

					continue;

				}

				$counts['served']++;

				$name = $row['species']->getLabel().'-'.$row['unit']['fqn'];
				$months = $row['months'];

				if(array_keys($months) !== range(1, 12)) {
					$errors[] = $name.' mois '.implode(',', array_keys($months));
					continue;
				}

				if(abs(array_sum(array_column($months, 'quantity')) - $row['total']) > 0.001) {
					$errors[] = $name.' somme des mois '.array_sum(array_column($months, 'quantity')).' pour '.$row['total'];
				}

				$peak = max(array_column($months, 'quantity'));

				if(max(array_column($months, 'bar')) !== 1.0) {
					$errors[] = $name.' aucun mois en haut';
				}

				foreach($months as $month => $values) {

					if(abs($values['bar'] - round($values['quantity'] / $peak, 3)) > 0.0001) {
						$errors[] = $name.' mois '.$month.' barre '.$values['bar'];
					}

				}

			}

		}

		$this->assertEquals([], $errors, 'mois faux : '.implode(', ', array_slice($errors, 0, 5)));
		$this->assertTrue($counts['served'] > 0 and $counts['never'] > 0, 'des espèces servies et jamais servies : '.json_encode($counts));

	}

	/**
	 * Un panier de plus par semaine ne recompose rien (Q6) : aucune ligne ne bouge, la colonne de sa
	 * taille et le total suivent le nouveau nombre, l'autre taille garde sa colonne. Chaque réponse
	 * ciblée de la grille rend la section.
	 */
	public function testQuantitiesBaskets(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$eCompositionPlanWritten = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlanWritten, 'Le grand panier', 30.0);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$eCompositionFormula['baskets'] = 20;

		CompositionFormulaLib::update($eCompositionFormula, ['baskets']);

		$eCompositionPlanBefore = CompositionPlanLib::getForScreen($eStructure, NULL);
		$linesBefore = $this->getLines($eCompositionPlanBefore);

		$eCompositionFormula['baskets'] = 21;

		CompositionFormulaLib::update($eCompositionFormula, ['baskets']);

		$eCompositionPlanAfter = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertEquals($linesBefore, $this->getLines($eCompositionPlanAfter), 'aucune ligne ne bouge');

		$before = $this->getQuantitiesRows($eCompositionPlanBefore);
		$after = $this->getQuantitiesRows($eCompositionPlanAfter);

		$this->assertEquals(array_keys($before), array_keys($after), 'les mêmes espèces');

		$formulaId = $eCompositionFormula['id'];
		$moved = 0;

		foreach($before as $key => $row) {

			foreach($row['formulas'] as $id => $quantity) {

				if($quantity === NULL) {
					$this->assertEquals(NULL, $after[$key]['formulas'][$id], 'une taille qui ne sert pas l\'espèce ne la sert toujours pas : '.$key.' / '.$id);
					continue;
				}

				$expected = ($id === $formulaId) ? $quantity / 20 * 21 : $quantity;

				$this->assertEquals(round($expected, 2), round($after[$key]['formulas'][$id], 2), 'la colonne suit ses paniers : '.$key.' / '.$id);

			}

			$this->assertEquals(round(array_sum($after[$key]['formulas']), 2), round($after[$key]['total'], 2), 'le total additionne les colonnes : '.$key);

			if($row['formulas'][$formulaId] !== NULL) {
				$moved++;
			}

		}

		if($moved === 0) {
			$this->assertFail('aucune espèce servie par la taille réglée : le contrôle ne verrait rien bouger');
		}

		$this->assertEquals(21 + 10, $eCompositionPlanAfter['quantities']['baskets'], 'les paniers d\'une semaine');

	}

	/**
	 * Une case allumée à la main (É11) : sa case est servie comme une case proposée, sans plus porter
	 * de cercle (lot r21, retour du 27/09, « enlève cette distinction »), et sa semaine est
	 * « corrigée » et offre de revenir à la proposition, le prix de la formule demande
	 * alors confirmation dans « Modifier les paniers ». La réponse ne rend que le rang, la tête de son groupe, la bulle et la carte
	 * de la semaine, la tête de la formule et la ligne du rejeu, dont la confirmation compte les
	 * semaines corrigées. Le rang de l'espèce garde ses cases, que le menu de l'une d'elles ancre
	 * (lot r24) : son nom et sa somme se rendent par leur contenu, sa case par ses attributs. Une case
	 * proposée qu'on éteint rend la même chose, et ne touche aucune autre case, le vert ne tenant qu'à
	 * la saison (r29, #87), sans jamais rendre la grille entière : un geste ne réordonne pas la grille
	 * (E-Paniers, R1).
	 */
	public function testAmendRender(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];

		$this->assertEquals(NULL, CompositionFormulaUi::getRecomposeLoss($eCompositionFormula), 'rien à confirmer sans correction');

		$week = NULL;
		$speciesId = NULL;

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				foreach(array_keys($row['open']) as $openWeek) {
					if($speciesId === NULL and isset($row['lines'][$openWeek]) === FALSE and CompositionLib::isCandidate($openWeek, $row['species']['id'], 2026, NULL, $source)) {
						$week = $openWeek;
						$speciesId = $row['species']['id'];
					}
				}
			}
		}

		if($speciesId === NULL) {
			$this->assertFail('aucune case de saison à allumer : le contrôle ne verrait rien');
			return;
		}

		CompositionLineLib::create(new CompositionLine([
			'week' => CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week),
			'species' => new CompositionSpecies(['id' => $speciesId]),
			'origin' => CompositionLine::USER,
		]));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$blocks = new CompositionUi()->getToggled($eCompositionPlan, $week, $speciesId);

		$this->assertEquals([
			'#toolbox-composition-settings',
			'#toolbox-composition-counts',
			'#toolbox-composition-grid > .util-tip[data-week="'.$week.'"]',
			'#toolbox-composition-card-'.$week,
		], array_keys($blocks['outer']), 'une case allumée ne rend que ce qu\'elle change, sans la grille');
		$selector = '#toolbox-composition-grid-species-'.$speciesId;

		$this->assertEquals([$selector.' > td.toolbox-composition-grid-name', $selector.' > td.toolbox-composition-grid-sum'], array_values(array_filter(array_keys($blocks['inner']), fn(string $key) => str_starts_with($key, $selector))), 'le nom et la somme de l\'année de son rang, qui garde ses cases (lot r24)');
		$this->assertEquals(5, count($blocks['inner']), 'avec le contenu de la tête de son groupe et du pied de la grille, qui portent leur somme de l\'année, et du rang des prix (lot r21)');
		$this->assertTrue(str_contains($blocks['inner']['#toolbox-composition-grid-prices'], 'data-week="'.$week.'"'), 'dont le prix de la semaine allumée');

		$cell = $blocks['attributes'][$selector.' > td[data-week="'.$week.'"]'];

		$this->assertEquals([$selector.' > td[data-week="'.$week.'"]'], array_keys($blocks['attributes']), 'et la seule case du geste, par ses attributs');
		$this->assertTrue($cell['data-served'] === '' and preg_match('/^[0-9,]+$/', $cell['data-quantity']) === 1, 'la case allumée est servie, sans marque de correction (lot r21) : '.$cell['class']);

		$blocks = $blocks['outer'];

		$this->assertTipLines($eCompositionPlan, $week, $blocks['#toolbox-composition-grid > .util-tip[data-week="'.$week.'"]']);

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$eCompositionLineProposed = CompositionLine::model()
			->select(['id', 'species'])
			->whereWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week))
			->whereOrigin(CompositionLine::PROPOSED)
			->get();

		$blocks = new CompositionUi()->getToggled($eCompositionPlan, $week, $eCompositionLineProposed['species']['id']);

		$this->assertFalse(isset($blocks['outer']['#toolbox-composition-grid']), 'une case proposée éteinte ne rend pas la grille entière');
		$selectorProposed = '#toolbox-composition-grid-species-'.$eCompositionLineProposed['species']['id'].' > td[data-week="'.$week.'"]';

		$this->assertTrue(isset($blocks['inner']['#toolbox-composition-grid-species-'.$eCompositionLineProposed['species']['id'].' > td.toolbox-composition-grid-name']), 'mais le nom de son rang, resté à sa place');
		$this->assertTrue(isset($blocks['attributes'][$selectorProposed]), 'et sa case, par ses attributs');

		// Le vert des autres cases ne tient qu'à la saison de leur espèce (r29, #87) : aucune ne se
		// reteinte.
		$this->assertEquals([$selectorProposed], array_keys($blocks['attributes']), 'et aucune autre case');

		$cards = array_values(array_filter(array_keys($blocks['outer']), fn(string $selector) => str_starts_with($selector, '#toolbox-composition-card-')));

		$this->assertEquals(['#toolbox-composition-card-'.$week], $cards, 'et la seule carte de sa semaine, les autres gardant l\'ordre de leurs lignes');

	}

	/**
	 * Garde-fou de « une action ne renvoie que ce qui change » (CLAUDE.md, Architecture), sur les deux
	 * gestes qui reteintent une semaine : une case proposée qu'on éteint, d'un chou puis d'une espèce
	 * ordinaire, et le retour à la proposition de sa semaine. La réponse, posée sur l'écran d'avant
	 * dans l'ordre où `ajax.js` la joue, doit rendre l'écran d'un rechargement complet. Un geste ne
	 * réordonne pas la grille (E-Paniers, R1) : les rangs se comparent par leur contenu et jamais par
	 * leur suite, et de même les lignes d'une carte, rangées dans l'ordre de la grille.
	 *
	 * La comparaison sait échouer : l'écran d'avant et celui d'après, comparés de la même façon,
	 * doivent différer, sans quoi elle ne verrait pas une réponse qui oublie un changement.
	 */
	public function testGestureRender(): void {

		// Le menu de chaque semaine et ses liens ne se rendent qu'à qui gère la structure
		// (`assertUpdate`) : sans lui, la réponse n'exercerait pas le lien qu'elle remplace.
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkGestureRender($eStructure);
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkGestureRender(\structure\Structure $eStructure): void {

		$this->createStarted($eStructure, 2026, range(1, 52));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		// Un chou proposé, puis une espèce ordinaire proposée d'une autre semaine : le retour de la
		// première ne touche pas la seconde.
		$cases = [];

		foreach([TRUE, FALSE] as $cabbage) {

			foreach($eCompositionPlan['eCompositionFormula']['cCompositionWeek'] as $eCompositionWeek) {

				if(isset($cases[(int)$cabbage]) or in_array($eCompositionWeek['week'], array_column($cases, 'week'), TRUE)) {
					continue;
				}

				$eCompositionLine = $eCompositionWeek['cCompositionLine']->find(fn(CompositionLine $e) => $e['origin'] === CompositionLine::PROPOSED and CompositionLib::isCabbage($e['species']['id'], $source) === $cabbage, limit: 1);

				if($eCompositionLine->notEmpty()) {
					$cases[(int)$cabbage] = ['week' => $eCompositionWeek['week'], 'speciesId' => $eCompositionLine['species']['id'], 'label' => $cabbage ? 'chou' : 'espèce ordinaire'];
				}

			}

		}

		if(count($cases) < 2) {
			$this->assertFail('ni chou ni espèce ordinaire proposés sur deux semaines : le contrôle ne verrait rien');
			return;
		}

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		foreach($cases as ['week' => $week, 'speciesId' => $speciesId, 'label' => $label]) {

			$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

			// La case proposée éteinte, comme `compositionLine.p.php` (`doDelete`).
			$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

			CompositionLineLib::delete(CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, new CompositionSpecies(['id' => $speciesId])));

			$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

			$this->checkScreenAfter($before, $eStructure, $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $week, $speciesId)), $label.', case proposée éteinte');

			// Le retour à la proposition, comme `compositionWeek.p.php` (`doReset`).
			$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

			$speciesIds = CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds();

			CompositionLib::recomposeWeek($eCompositionPlan, $eCompositionPlan['eCompositionFormula'], $eCompositionWeek, $source, CompositionLib::getPrices($eCompositionPlan, $source));

			$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

			$speciesIds = array_merge($speciesIds, CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds());

			$this->checkScreenAfter($before, $eStructure, $this->getRequestRender(fn() => new CompositionUi()->getReset($eCompositionPlan, $week, array_values(array_unique($speciesIds)))), $label.', retour à la proposition');

		}

		// Le plus d'une quantité (planche r5), comme `compositionLine.p.php` (`doQuick`) : la carte de
		// la semaine, sa bulle, le rang de l'espèce et la tête de la formule suivent, rien d'autre.
		$eCompositionWeekStep = $eCompositionPlan['eCompositionFormula']['cCompositionWeek']->find(fn(CompositionWeek $e) => $e['cCompositionLine']->notEmpty(), limit: 1);
		$eCompositionLineStep = $eCompositionWeekStep['cCompositionLine']->first();

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		$this->assertTrue(str_contains($before, 'id="toolbox-composition-card-'.$eCompositionWeekStep['week'].'"'), 'la carte de la semaine est sous la grille');

		$eCompositionLineStep['quantity'] = $eCompositionLineStep['quantityMore'];

		CompositionLineLib::update($eCompositionLineStep, ['quantity']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$changes = $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $eCompositionWeekStep['week'], $eCompositionLineStep['species']['id']));

		$this->assertEquals(['#toolbox-composition-grid-species-'.$eCompositionLineStep['species']['id']], array_values(array_unique(array_map(fn(string $selector) => explode(' ', $selector)[0], array_filter(array_keys($changes['inner'] + $changes['attributes']), fn(string $selector) => str_starts_with($selector, '#toolbox-composition-grid-species-'))))), 'un pas de plus ne rend que le rang de l\'espèce');

		$this->checkScreenAfter($before, $eStructure, $changes, 'quantité d\'un pas de plus');

		$this->checkCellMenuRender($eStructure);

	}

	/**
	 * Le menu d'une case (lot r24, retour du 27/09, « quand on clique sur une case, ça
	 * permet de modifier la quantité pour la case ou la supprimer du panier »), tel que le rend
	 * `compositionLine:getMenu` (`CompositionUi::getCellMenu()`), sur la semaine `$week` :
	 *
	 * - une case de saison que la semaine ne sert pas porte le champ de sa quantité (lot r24b, retour
	 *   du 27/09, « mets directement un champ avec la valeur à ajouter et un bouton
	 *   valide »), dans l'unité de l'espèce, prérempli à son minimum et non à sa quantité de
	 *   référence, que garde une ligne créée sans quantité. Ce que le formulaire poste se construit
	 *   comme le fait `compositionLine:doCreate`, qui refuse sur le champ une quantité sous le
	 *   minimum ou hors du pas, et une autre unité que celle de l'espèce ;
	 * - une case servie porte la poubelle « Retirer du panier » (`compositionLine:doDelete`), le
	 *   moins, éteint au minimum, la quantité et le plus, et la case retirée reprend son champ ;
	 * - une case hors de la fenêtre de l'espèce et une espèce sans unité au panier n'ont pas de menu,
	 *   qui ne s'ouvre pas ;
	 * - chaque geste rend la case par ses attributs, sans toucher au rang qui la porte, que
	 *   `Lime.Dropdown` refermerait, et sa réponse vaut un rechargement de l'écran
	 *   (`checkScreenAfter()`).
	 */
	private function checkCellMenuRender(\structure\Structure $eStructure): void {

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);
		$prices = CompositionLib::getPrices($eCompositionPlan, $source);

		// Une semaine et une espèce qu'elle ne sert pas mais peut recevoir, pas un chou, dont le
		// minimum n'est pas la quantité de référence : sans quoi le contrôle ne distinguerait pas les
		// deux.
		$week = NULL;
		$eCompositionSpecies = new CompositionSpecies();

		foreach($eCompositionPlan['weeks'] as $weekDelivered) {

			foreach($this->getAddableSpecies(CompositionLib::applyAddableToGrid($eCompositionPlan['eCompositionFormula']['grid'], $weekDelivered, $source, $steps)) as $eCompositionSpeciesAddable) {

				if(
					$eCompositionSpecies->empty() and
					CompositionLib::isCabbage($eCompositionSpeciesAddable['id'], $source) === FALSE and
					CompositionLib::getStep($eCompositionSpeciesAddable['id'], $source, $steps)['minimum'] < CompositionLib::getLineReference($weekDelivered, $eCompositionSpeciesAddable['id'], $source, $prices, $steps)['quantity']
				) {
					$week = $weekDelivered;
					$eCompositionSpecies = $eCompositionSpeciesAddable;
				}

			}

		}

		if($eCompositionSpecies->empty()) {
			$this->assertFail('aucune espèce ajoutable dont le minimum diffère de la référence : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

		$this->assertEquals($week, $eCompositionPlan['week'], 'le plan est lu pour la semaine de la case');

		$minimum = CompositionLib::getStep($eCompositionSpecies['id'], $source, $steps)['minimum'];

		$menu = $this->getCellMenuDocument($eCompositionPlan, $eCompositionSpecies['id'], $week);
		$form = $menu->querySelector('.toolbox-composition-cell-menu > form');

		$this->assertEquals('/toolbox/compositionLine:doCreate', $form?->getAttribute('data-ajax-form'), 'mais un formulaire qui ajoute l\'espèce');

		$posted = [];

		foreach($form->querySelectorAll('input') as $input) {
			$posted[$input->getAttribute('name')] = $input->getAttribute('value');
		}

		$eUnit = \product\UnitLib::getById($source['units'][$eCompositionSpecies['id']]['unit'], ['id', 'singular', 'short']);

		$this->assertEquals([
			'structure' => (string)$eStructure['id'],
			'formula' => (string)$eCompositionFormula['id'],
			'week' => (string)$week,
			'species' => (string)$eCompositionSpecies['id'],
			'unit' => (string)$eUnit['id'],
		], array_diff_key($posted, ['quantity' => TRUE]), 'le formulaire désigne la case comme le fait la grille, avec l\'unité de l\'espèce');
		$this->assertEquals($minimum, (float)$posted['quantity'], 'le champ est prérempli au minimum de l\'espèce');

		$group = $menu->querySelector('.toolbox-composition-cell-menu [data-wrapper="quantity"]');

		$this->assertTrue($group?->querySelector('.input-group > input[name="quantity"]') !== NULL, 'le champ est dans le groupe que le refus de la quantité désigne');

		// Ce que le formulaire poste, construit comme `compositionLine:doCreate`.
		$build = function(array $input) use($eCompositionWeek): array {

			$fw = new \FailWatch();

			$eCompositionLine = new CompositionLine([
				'week' => $eCompositionWeek,
				'origin' => CompositionLine::USER,
			]);

			$eCompositionLine->build(['species', 'unit', 'quantity'], $input, new \PropertyList('create'));

			return [$eCompositionLine, array_map(fn($fail) => $fail[1], $fw->get())];

		};

		$step = CompositionLib::getStep($eCompositionSpecies['id'], $source, $steps)['step'];

		$eUnitOther = \product\Unit::model()
			->select(['id'])
			->whereStructure(NULL)
			->whereId('!=', $eUnit)
			->sort(['id' => SORT_ASC])
			->get();

		$this->assertEquals(['CompositionLine::quantity.minimum'], $build(['quantity' => $minimum - $step / 2] + $posted)[1], 'sous le minimum, refusé sur le champ');
		$this->assertEquals(['CompositionLine::quantity.step'], $build(['quantity' => $minimum + $step / 2] + $posted)[1], 'hors du pas, refusé sur le champ');
		$this->assertEquals(['CompositionLine::unit.species'], $build(['unit' => $eUnitOther['id']] + $posted)[1], 'une autre unité que celle de l\'espèce');
		$this->assertEquals(['CompositionLine::species.check'], $build(['species' => ''] + $posted)[1], 'sans espèce, seule l\'espèce est refusée');

		[$eCompositionLineTyped, $failed] = $build(['quantity' => $minimum + $step] + $posted);

		$this->assertEquals([], $failed, 'une quantité tapée au-dessus du minimum passe');
		$this->assertEquals($minimum + $step, $eCompositionLineTyped['quantity'], 'et la ligne la garde');

		// Une case hors saison ouvre le même champ qu'une case de saison (r29, #83) ; seule une espèce
		// sans unité au panier n'ouvre rien.
		$out = 0;

		foreach($eCompositionFormula['grid'] as $group) {

			foreach($group['species'] as $row) {

				if(isset($row['lines'][$week])) {
					continue;
				}

				$menu = new CompositionUi()->getCellMenu($eCompositionPlan, $row['species']['id'], $week);

				if(CompositionLib::isSold($row['species']['id'], $source) and isset($row['weeksSeason'][$week]) === FALSE) {
					$out++;
					$this->assertTrue(str_contains($menu, 'data-ajax-form="/toolbox/compositionLine:doCreate"'), 'hors saison, le champ d\'ajout : '.$row['species']->getLabel());
				}

			}

		}

		$this->assertTrue($out > 0, 'aucune case hors saison dans la semaine '.$week.' : le contrôle ne verrait rien');

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		[$eCompositionLine, $failed] = $build($posted);

		$this->assertEquals([], $failed, 'le champ prérempli passe tel quel');

		CompositionLineLib::create($eCompositionLine);

		$eCompositionLineWritten = CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies);

		$this->assertEquals([$minimum, $eUnit['id'], NULL, CompositionLib::getLineReference($week, $eCompositionSpecies['id'], $source, $prices, $steps)['unitPrice']], [$eCompositionLineWritten['quantity'], $eCompositionLineWritten['unit']['id'], $eCompositionLineWritten['quantityProposed'], $eCompositionLineWritten['unitPrice']], 'la ligne prend la quantité du champ, l\'unité de l\'espèce et le prix de la règle');

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);

		$changes = $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $week, $eCompositionSpecies['id']));

		$selector = '#toolbox-composition-grid-species-'.$eCompositionSpecies['id'];

		$this->assertFalse(isset($changes['inner'][$selector]), 'le rang de la case garde ses cases, et le menu qui s\'y ancre');
		$this->assertEquals('', $changes['attributes'][$selector.' > td[data-week="'.$week.'"]']['data-served'], 'la case se dit servie par son attribut');

		$this->checkScreenAfter($before, $eStructure, $changes, 'menu d\'une case, espèce servie par son champ');

		$menu = html_entity_decode(new CompositionUi()->getCellMenu($eCompositionPlan, $eCompositionSpecies['id'], $week), ENT_QUOTES);

		$this->assertTrue(str_contains($menu, '<a data-ajax="/toolbox/compositionLine:doDelete" '), 'le menu de la case servie porte la poubelle qui retire l\'espèce');

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		CompositionLineLib::delete(CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);

		$changes = $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $week, $eCompositionSpecies['id']));

		$this->assertEquals(NULL, $changes['attributes'][$selector.' > td[data-week="'.$week.'"]']['data-served'], 'la case retirée perd son attribut de case servie');

		$this->checkScreenAfter($before, $eStructure, $changes, 'menu d\'une case, espèce retirée par sa poubelle');
		$this->assertTrue(str_contains(new CompositionUi()->getCellMenu($eCompositionPlan, $eCompositionSpecies['id'], $week), 'data-ajax-form="/toolbox/compositionLine:doCreate"'), 'la case retirée reprend son champ');

		// Écrite sans quantité, la ligne prend sa quantité de référence.
		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => $eCompositionSpecies,
			'origin' => CompositionLine::USER,
		]));

		$this->assertEquals(CompositionLib::getLineReference($week, $eCompositionSpecies['id'], $source, CompositionLib::getPrices($eCompositionPlan, $source), $steps)['quantity'], CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies)['quantity'], 'sans quantité, la ligne prend celle de référence');

		// Au-dessus du minimum, la poubelle reste devant le moins, qui retire un pas (lot r8c).
		$menu = $this->getCellMenuDocument(CompositionPlanLib::getForScreen($eStructure, NULL, $week), $eCompositionSpecies['id'], $week);
		$number = '.toolbox-composition-cell-menu > .toolbox-composition-number';

		$this->assertEquals('quantity', $menu->querySelector($number.' .toolbox-composition-number-decrease')->getAttribute('post-property'), 'au-dessus du minimum, le moins retire un pas');

		CompositionLineLib::delete(CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies));

		$this->checkCellMenuCabbage($eStructure);

	}

	/**
	 * Un chou servi par le menu de sa case dans une semaine qui n'en sert aucun : un autre chou garde
	 * sa case ouverte sur son ajout (r34d, `CompositionLib::applyAddableToGrid()`), et la réponse
	 * ciblée, qui ne rend pas son rang, donne le même écran que la grille rechargée.
	 */
	private function checkCellMenuCabbage(\structure\Structure $eStructure): void {

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

		// Une semaine qui peut recevoir deux choux : elle n'en sert donc aucun.
		$week = NULL;

		foreach($eCompositionPlan['weeks'] as $weekDelivered) {

			$cabbages = array_filter($this->getAddableSpecies(CompositionLib::applyAddableToGrid($eCompositionPlan['eCompositionFormula']['grid'], $weekDelivered, $source, $steps)), fn(CompositionSpecies $eCompositionSpecies) => CompositionLib::isCabbage($eCompositionSpecies['id'], $source));

			if($week === NULL and count($cabbages) >= 2) {
				$week = $weekDelivered;
			}

		}

		if($week === NULL) {
			$this->assertFail('aucune semaine n\'a deux choux de saison à servir : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

		$cabbages = array_values(array_filter($this->getAddableSpecies($eCompositionFormula['grid']), fn(CompositionSpecies $eCompositionSpecies) => CompositionLib::isCabbage($eCompositionSpecies['id'], $source)));

		$this->assertEquals([], array_values(array_filter(CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds(), fn(int $speciesId) => CompositionLib::isCabbage($speciesId, $source))), 'la semaine '.$week.' ne sert aucun chou');

		[$eCompositionSpeciesServed, $eCompositionSpeciesOther] = $cabbages;

		$this->assertTrue(str_contains(new CompositionUi()->getCellMenu($eCompositionPlan, $eCompositionSpeciesOther['id'], $week), 'compositionLine:doCreate'), 'avant, l\'autre chou a son champ');

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeek,
			'species' => $eCompositionSpeciesServed,
			'origin' => CompositionLine::USER,
		]));

		try {

			$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);

			$changes = $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $week, $eCompositionSpeciesServed['id']));

			$this->checkScreenAfter($before, $eStructure, $changes, 'menu d\'une case, un chou servi');

			$this->assertFalse(isset($changes['inner']['#toolbox-composition-grid-species-'.$eCompositionSpeciesOther['id']]), 'la réponse ne rend pas l\'autre chou');
			$this->assertTrue(str_contains(new CompositionUi()->getCellMenu($eCompositionPlan, $eCompositionSpeciesOther['id'], $week), 'compositionLine:doCreate'), 'dont la case garde son champ');

		} finally {
			CompositionLineLib::delete(CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpeciesServed));
		}

	}

	private function getCellMenuDocument(CompositionPlan $eCompositionPlan, int $speciesId, int $week): \Dom\HTMLDocument {

		$menu = new CompositionUi()->getCellMenu($eCompositionPlan, $speciesId, $week);

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$menu.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Le chiffre d'affaires de l'année (r12) : la tuile de la tête vaut la somme des valeurs des
	 * semaines livrées par le nombre de paniers, la Synthèse additionne celui de chaque taille, et
	 * chaque geste qui le change le rend dans sa réponse ciblée, à l'identique d'un rechargement
	 * (`checkScreenAfter()`) : une quantité, un prix, le nombre de paniers, une semaine livrée.
	 */
	public function testTurnover(): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkTurnover($eStructure);
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkTurnover(\structure\Structure $eStructure): void {

		$eCompositionPlanWritten = $this->createStarted($eStructure, 2026, range(10, 40));

		$getTurnover = function(CompositionFormula $eCompositionFormula) use($eCompositionPlanWritten): float {

			$value = 0.0;

			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlanWritten)) as $eCompositionWeek) {
				$value += $eCompositionWeek['value'];
			}

			return $value * CompositionFormula::model()->select('baskets')->whereId($eCompositionFormula['id'])->get()['baskets'];

		};

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$turnover = $getTurnover($eCompositionFormula);

		$this->assertTrue($turnover > 0, 'des paniers valorisés');
		$this->assertTrue(abs($eCompositionFormula['turnover'] - $turnover) < 0.001, 'la valeur des semaines par les paniers');
		$this->assertDiffer(round(18.0 * 10 * 31, 2), round($eCompositionFormula['turnover'], 2), 'et non le prix visé par les semaines');

		// La Synthèse additionne les tailles.
		$eCompositionFormulaLarge = $this->addFormula($eCompositionPlanWritten, 'Le grand panier', 30.0);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$turnoverLarge = $getTurnover($eCompositionFormulaLarge);

		$this->assertTrue(abs($eCompositionPlan['quantities']['turnover'] - ($turnover + $turnoverLarge)) < 0.001, 'la Synthèse, somme des tailles');

		// Le nombre de paniers, comme `compositionFormula.v.php` (`doQuick`).
		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$eCompositionFormula['baskets'] = 11;
		CompositionFormulaLib::update($eCompositionFormula, ['baskets']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertTrue(abs($eCompositionPlan['eCompositionFormula']['turnover'] - $turnover * 11 / 10) < 0.001, 'onze paniers au lieu de dix');

		$changes = $this->getRequestRender(fn() => ['outer' => ['#toolbox-composition-settings' => new CompositionUi()->getSettings($eCompositionPlan, $eCompositionPlan['eCompositionFormula'])], 'inner' => new CompositionUi()->getGridSums($eCompositionPlan), 'attributes' => []]);

		$this->checkScreenAfter($before, $eStructure, $changes, 'chiffre d\'affaires, nombre de paniers');

		// Une quantité, comme `compositionLine.p.php` (`doQuick`).
		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));
		$turnoverBefore = $eCompositionPlan['eCompositionFormula']['turnover'];

		$eCompositionWeek = CompositionWeekLib::getByFormulaWithLines($eCompositionPlan['eCompositionFormula'], CompositionSpeciesLib::getByPlan($eCompositionPlan))->find(fn(CompositionWeek $e) => $e['cCompositionLine']->find(fn(CompositionLine $eLine) => $eLine['unitPrice'] !== NULL)->notEmpty(), limit: 1);
		$eCompositionLine = $eCompositionWeek['cCompositionLine']->find(fn(CompositionLine $e) => $e['unitPrice'] !== NULL, limit: 1);
		$eCompositionLine['quantity'] = $eCompositionLine['quantity'] + 1;
		CompositionLineLib::update($eCompositionLine, ['quantity']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertTrue($eCompositionPlan['eCompositionFormula']['turnover'] > $turnoverBefore, 'une quantité de plus, un chiffre plus haut');
		$this->checkScreenAfter($before, $eStructure, $this->getRequestRender(fn() => new CompositionUi()->getToggled($eCompositionPlan, $eCompositionWeek['week'], $eCompositionLine['species']['id'])), 'chiffre d\'affaires, quantité');

		// Un prix d'espèce, comme `compositionSpecies.v.php` (`doQuick`).
		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));
		$turnoverBefore = $eCompositionPlan['eCompositionFormula']['turnover'];

		$eCompositionSpecies = $this->getSpecies($eCompositionPlanWritten, $eCompositionLine['species']['id']);
		$eCompositionSpecies['price'] = 99.5;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertTrue($eCompositionPlan['eCompositionFormula']['turnover'] > $turnoverBefore, 'un prix plus haut, un chiffre plus haut');
		// `getPriced()` ne reteinte aucune case : sa réponse n'a pas d'attributs.
		$this->checkScreenAfter($before, $eStructure, $this->getRequestRender(fn() => new CompositionUi()->getPriced($eCompositionPlan, $eCompositionLine['species']['id'])) + ['attributes' => []], 'chiffre d\'affaires, prix');

		// Une semaine de moins, comme `composition.p.php` (`doWeeks`), par le réglage des semaines
		// livrées, dont la réponse rend les paniers.
		$turnoverBefore = $eCompositionPlan['eCompositionFormula']['turnover'];

		$eCompositionPlanWeeks = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWeeks['structure'] = $eStructure;
		$eCompositionPlanWeeks['weeks'] = array_values(array_diff($eCompositionPlanWeeks['weeks'], [$eCompositionWeek['week']]));
		CompositionPlanLib::update($eCompositionPlanWeeks, ['weeks']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertFalse(in_array($eCompositionWeek['week'], $eCompositionPlan['eCompositionFormula']['cCompositionWeek']->getColumn('week'), TRUE), 'la semaine n\'est plus livrée');
		$this->assertTrue($eCompositionPlan['eCompositionFormula']['turnover'] < $turnoverBefore, 'une semaine de moins, un chiffre plus bas');
		$this->assertTrue(abs($eCompositionPlan['eCompositionFormula']['turnover'] - $getTurnover($eCompositionPlan['eCompositionFormula'])) < 0.001, 'toujours la valeur des semaines livrées');

	}

	/**
	 * Un rendu tel qu'une requête neuve le produit. Les attributs qu'un `util-quick` poste
	 * (`Element::setQuickAttribute()`) vivent dans une propriété statique qui survit d'un rendu au
	 * suivant dans le même processus : sans cette remise à zéro, un rendu hériterait de la semaine et
	 * de l'espèce de la dernière case rendue avant lui, ce qu'aucune requête ne voit.
	 */
	private function getRequestRender(\Closure $render): mixed {

		new \ReflectionProperty(\Element::class, 'quickAttributes')->setValue(NULL, []);

		return $render();

	}

	/**
	 * La somme de l'année en fin de ligne (lot r13, planche r9, piste A de ses questions 6 et 7) :
	 * chaque rang, chaque tête de groupe et le pied de la grille finissent par ce que la taille sert
	 * sur l'année, en kilos puis en euros.
	 *
	 * Les sommes sont recomptées ici sur les lignes relues de la base, sans passer par la grille :
	 * la valeur de chaque ligne par les paniers, les kilos par le poids de l'unité que le menu de
	 * l'espèce dit. Le pied retombe sur le chiffre d'affaires de la taille (r12). Un poids réglé
	 * change les kilos de l'espèce, de son groupe et du pied, et la réponse ciblée rend l'écran d'un
	 * rechargement (`checkScreenAfter()`) ; le nombre de paniers, le prix et la quantité sont
	 * éprouvés de même par `testTurnover()`.
	 */
	public function testGridSums(): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkGridSums(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkGridSums(\structure\Structure $eStructure): void {

		$eCompositionPlanWritten = $this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];
		$baskets = $eCompositionFormula['baskets'];

		// Les poids que le menu de chaque espèce dit, relus de la grille, dans l'unité où elle entre au
		// panier : c'est la seule chose que ce recomptage emprunte à l'écran.
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$weights = [];

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				if($row['eCompositionSpecies']->notEmpty()) {
					$weights[$row['species']['id']] = [$source['units'][$row['species']['id']]['fqn'], $row['eCompositionSpecies']['weightReference']['weight']];
				}
			}
		}

		$expected = [];
		$converted = 0;

		foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$speciesId = $eCompositionLine['species']['id'];
				$expected[$speciesId] ??= ['kilos' => 0.0, 'quantity' => 0.0, 'unit' => $eCompositionLine['unit']['id'], 'value' => 0.0];

				$expected[$speciesId]['quantity'] += $eCompositionLine['quantity'] * $baskets;

				$expected[$speciesId]['value'] += ($eCompositionLine['unitPrice'] === NULL) ? 0.0 : round($eCompositionLine['quantity'] * $eCompositionLine['unitPrice'], 2) * $baskets;

				if($eCompositionLine['unit']['fqn'] === 'kg') {
					$unitKilos = 1.0;
				} else {
					$this->assertEquals($weights[$speciesId][0], $eCompositionLine['unit']['fqn'], 'une ligne à la pièce ou à la botte dans l\'unité de son espèce');
					$unitKilos = $weights[$speciesId][1];
					$converted++;
				}

				$expected[$speciesId]['kilos'] = ($expected[$speciesId]['kilos'] === NULL or $unitKilos === NULL) ? NULL : $expected[$speciesId]['kilos'] + $eCompositionLine['quantity'] * $unitKilos * $baskets;

			}

		}

		$this->assertTrue($converted > 0, 'des lignes à la pièce ou à la botte, que le poids convertit');

		$errors = [];
		$never = 0;
		$total = ['kilos' => 0.0, 'value' => 0.0];

		foreach($eCompositionFormula['grid'] as $group) {

			$sumGroup = ['kilos' => 0.0, 'value' => 0.0];

			foreach($group['species'] as $row) {

				$speciesId = $row['species']['id'];

				if(array_key_exists($speciesId, $expected) === FALSE) {
					$never++;
					if(array_diff_key($row['sum'], ['eUnit' => TRUE]) !== ['served' => FALSE, 'kilos' => 0.0, 'quantity' => 0.0, 'value' => 0.0]) {
						$errors[] = $row['species']->getLabel().' : jamais servie, '.json_encode($row['sum']);
					}
					continue;
				}

				if(
					$row['sum']['served'] === FALSE or
					abs($row['sum']['value'] - $expected[$speciesId]['value']) > 0.001 or
					($expected[$speciesId]['kilos'] === NULL) !== ($row['sum']['kilos'] === NULL) or
					abs(($row['sum']['kilos'] ?? 0.0) - ($expected[$speciesId]['kilos'] ?? 0.0)) > 0.001 or
					// Toutes les lignes sont dans l'unité de l'espèce (vérifié plus haut) : sa quantité de
					// l'année est leur somme, sans conversion.
					abs($row['sum']['quantity'] - $expected[$speciesId]['quantity']) > 0.001 or
					$row['sum']['eUnit']['id'] !== $expected[$speciesId]['unit']
				) {
					$errors[] = $row['species']->getLabel().' : '.json_encode($row['sum']).' au lieu de '.json_encode($expected[$speciesId]);
				}

				$sumGroup['value'] += $expected[$speciesId]['value'];
				$sumGroup['kilos'] += $expected[$speciesId]['kilos'] ?? 0.0;

			}

			if(abs($group['sum']['value'] - $sumGroup['value']) > 0.001 or abs($group['sum']['kilos'] - $sumGroup['kilos']) > 0.001) {
				$errors[] = 'groupe '.$group['organ'].' : '.json_encode($group['sum']).' au lieu de '.json_encode($sumGroup);
			}

			$total['value'] += $sumGroup['value'];
			$total['kilos'] += $sumGroup['kilos'];

		}

		$this->assertEquals([], $errors, 'sommes fausses : '.implode(', ', array_slice($errors, 0, 5)));
		$this->assertTrue($never > 0, 'des candidates jamais servies, dont la somme reste vide');
		$this->assertTrue(abs($eCompositionFormula['gridSum']['value'] - $total['value']) < 0.001, 'le pied, somme des groupes en euros');
		$this->assertTrue(abs($eCompositionFormula['gridSum']['kilos'] - $total['kilos']) < 0.001, 'et en kilos');
		$this->assertTrue(abs($eCompositionFormula['gridSum']['value'] - $eCompositionFormula['turnover']) < 0.001, 'le pied retombe sur le chiffre d\'affaires de la taille');

		// Un poids réglé change les kilos de l'espèce, de son groupe et du pied, et d'eux seuls.
		$speciesIdWeighed = NULL;

		foreach($weights as $speciesId => [$unitFqn, $weight]) {
			if($unitFqn !== 'kg' and array_key_exists($speciesId, $expected) and $expected[$speciesId]['kilos'] !== NULL) {
				$speciesIdWeighed = $speciesId;
				break;
			}
		}

		if($speciesIdWeighed === NULL) {
			$this->assertFail('aucune espèce servie à la pièce ou à la botte n\'a de poids : le poids réglé ne se verrait pas');
			return;
		}

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan));

		$eCompositionSpecies = $this->getSpecies($eCompositionPlanWritten, $speciesIdWeighed);
		$eCompositionSpecies['weight'] = round($weights[$speciesIdWeighed][1] * 2 + 0.1, 3);
		CompositionSpeciesLib::update($eCompositionSpecies, ['weight']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$rowAfter = $this->getGridRow($eCompositionPlan['eCompositionFormula'], $speciesIdWeighed);
		$this->assertTrue(abs($rowAfter['sum']['kilos'] - $expected[$speciesIdWeighed]['kilos'] / $weights[$speciesIdWeighed][1] * $eCompositionSpecies['weight']) < 0.001, 'les kilos de l\'espèce suivent son poids');
		$this->assertTrue(abs($rowAfter['sum']['value'] - $expected[$speciesIdWeighed]['value']) < 0.001, 'et ses euros n\'en bougent pas');

		$this->checkScreenAfter($before, $eStructure, $this->getRequestRender(fn() => new CompositionUi()->getWeighed($eCompositionPlan, $speciesIdWeighed, FALSE)) + ['attributes' => []], 'somme de l\'année, poids');

	}

	/**
	 * La somme de l'année d'une espèce dont une ligne n'a pas de poids (`CompositionLib::applySumsToGrid()`) :
	 * ses kilos sont inconnus et non partiels, son groupe additionne les kilos des autres et la
	 * compte à part. Une grille écrite à la main, sur une espèce comptée au kilo dans le référentiel
	 * que rien ne pèse à la botte : c'est le seul moyen de tenir une ligne sans poids sans dépendre
	 * de la base.
	 */
	public function testGridSumsUnweighed(): void {

		$source = CompositionLib::getSource();

		$speciesIdKilo = NULL;

		foreach($source['units'] as $speciesId => $eUnit) {
			if($eUnit['fqn'] === 'kg' and isset($source['weights'][$speciesId]['bunch']) === FALSE) {
				$speciesIdKilo = $speciesId;
				break;
			}
		}

		if($speciesIdKilo === NULL) {
			$this->assertFail('aucune espèce au kilo que rien ne pèse à la botte : le contrôle ne verrait rien');
			return;
		}

		$units = array_column($source['offered'], 'unit', 'fqn');

		$line = fn(string $unitFqn, float $quantity, ?float $value) => new CompositionLine(['unit' => new \product\Unit(['id' => $units[$unitFqn], 'fqn' => $unitFqn, 'short' => $unitFqn]), 'quantity' => $quantity, 'value' => $value]);

		$grid = [[
			'organ' => NULL,
			'species' => [
				['species' => new CompositionSpecies(['id' => $speciesIdKilo]), 'lines' => [10 => $line('kg', 2.0, 6.0), 11 => $line('bunch', 1.0, 2.5)]],
				// Deux rangs au kilo seul, que `getUnitKilos()` ne cherche pas dans la source : un id
				// fictif suffit, et distinct pour que l'écran les rende chacun sous le sien.
				['species' => new CompositionSpecies(['id' => 0]), 'lines' => [10 => $line('kg', 1.5, NULL), 11 => $line('kg', 0.5, 1.0)]],
				['species' => new CompositionSpecies(['id' => -1]), 'lines' => []],
			],
		]];

		$grid = CompositionLib::applySumsToGrid($grid, 10, $source, new \Collection());

		// Des espèces que le plan ne règle pas (une collection vide) : la quantité se dit dans l'unité
		// de leur première ligne.
		$sum = fn(array $row) => array_diff_key($row['sum'], ['eUnit' => TRUE]) + ['unit' => $row['sum']['eUnit']->empty() ? NULL : $row['sum']['eUnit']['fqn']];

		$this->assertEquals(['served' => TRUE, 'kilos' => NULL, 'quantity' => NULL, 'value' => 85.0, 'unit' => 'kg'], $sum($grid[0]['species'][0]), 'une botte sans poids : kilos et quantité inconnus, euros comptés');
		$this->assertEquals(['served' => TRUE, 'kilos' => 20.0, 'quantity' => 20.0, 'value' => 10.0, 'unit' => 'kg'], $sum($grid[0]['species'][1]), 'une ligne sans prix compte pour zéro euro, pas pour zéro kilo');
		$this->assertEquals(['served' => FALSE, 'kilos' => 0.0, 'quantity' => 0.0, 'value' => 0.0, 'unit' => NULL], $sum($grid[0]['species'][2]), 'un rang sans ligne');
		$this->assertEquals(['kilos' => 20.0, 'value' => 95.0, 'unweighed' => 1], $grid[0]['sum'], 'le groupe additionne les kilos connus et compte l\'espèce sans poids');
		$this->assertEquals(['kilos' => 20.0, 'value' => 95.0, 'unweighed' => 1], CompositionLib::getGridSum($grid), 'et le pied les groupes');

	}

	/**
	 * Ce que la vue `$name` de `view/$file.v.php` répond vraiment, relu dans la forme de
	 * `checkScreenAfter()` : les instructions `outerHtml`, `innerHtml`, `setAttribute` et
	 * `removeAttribute` de la réponse json, dans leur ordre.
	 *
	 * `setAttribute` se rejoue comme le navigateur le fait : une valeur `NULL` y devient la chaîne
	 * `null`, `TRUE` la chaîne `true`, et une valeur `NULL` est aussi relevée dans `null` (sélecteur et
	 * attribut), pour qu'un test la nomme.
	 * Un `removeAttribute` redevient la valeur `NULL` que `checkScreenAfter()` retire, et un `remove`
	 * se relève sous `remove` (P9). Toute autre instruction échoue : le rejeu ne saurait pas la jouer,
	 * et l'écran comparé serait faux.
	 *
	 * `$outside` nomme ce que la réponse touche hors des paniers, que le rejeu ne compare pas et laisse
	 * donc passer : un sélecteur (le lien « Recommencer à zéro »), ou
	 * `js` pour la fermeture du panneau.
	 */
	private function getViewChanges(string $file, string $name, \stdClass $data, array $outside = []): array {

		require_once __DIR__.'/../view/'.$file.'.v.php';

		return $this->getOutputChanges($name, $this->getViewOutput(\View::get($name, new \ViewAction()), $data), $outside);

	}

	private function getViewOutput(\View $view, \stdClass $data): string {

		return $this->getRequestRender(function() use($view, $data) {
			ob_start();
			$view->render($data, new \AjaxTemplate());
			return ob_get_clean();
		});

	}

	/**
	 * La réponse json `$output` de la vue `$name`, relue comme le décrit `getViewChanges()`.
	 */
	private function getOutputChanges(string $name, string $output, array $outside): array {

		$changes = ['remove' => [], 'outer' => [], 'inner' => [], 'attributes' => [], 'null' => []];

		foreach(json_decode($output, TRUE)['__instructions'] ?? [] as $entry) {

			// Une instruction `js` n'a pas de sélecteur : `[mode, instructions]`.
			if($entry[0] === 'js') {
				if(in_array('js', $outside, TRUE) === FALSE) {
					$this->assertFail($name.' : instruction js que le rejeu ne sait pas jouer');
				}
				continue;
			}

			[$mode, $selector, $instructions] = $entry;

			if(in_array($selector, $outside, TRUE)) {
				continue;
			}

			foreach($instructions as [$instruction, $arguments]) {

				if($mode !== 'qs') {
					$this->assertFail($name.' : instruction '.$mode.' '.$instruction.' que le rejeu ne sait pas jouer');
					continue;
				}

				switch($instruction) {

					case 'outerHtml' :
						$changes['outer'][$selector] = $arguments[0];
						break;

					case 'innerHtml' :
						$changes['inner'][$selector] = $arguments[0];
						break;

					case 'setAttribute' :
						[$attribute, $value] = $arguments;
						if($value === NULL) {
							$changes['null'][] = $selector.' '.$attribute;
						}
						// `setAttribute()` du navigateur écrit la valeur en chaîne, et `NULL` en `null`.
						$changes['attributes'][$selector][$attribute] = is_string($value) ? $value : json_encode($value);
						break;

					case 'removeAttribute' :
						$changes['attributes'][$selector][$arguments[0]] = NULL;
						break;

					case 'remove' :
						$changes['remove'][] = $selector;
						break;

					default :
						$this->assertFail($name.' : instruction '.$instruction.' que le rejeu ne sait pas jouer');

				}

			}

		}

		return $changes;

	}

	/**
	 * L'écran `$before`, sur lequel se jouent `remove`, `outer`, `inner` puis `attributes` de
	 * `$changes`, dans l'ordre de `toolboxCompositionSpeciesRender()`, comparé à l'écran que
	 * rechargerait la structure `$eStructure`.
	 */
	private function checkScreenAfter(string $before, \structure\Structure $eStructure, array $changes, string $label): void {

		$this->assertFalse(isset($changes['outer']['#toolbox-composition-grid']), $label.' : la grille ne se rend pas entière');

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$before.'</body></html>', LIBXML_NOERROR);

		foreach($changes['remove'] ?? [] as $selector) {

			$node = $document->querySelector($selector);

			if($node === NULL) {
				$this->assertFail($label.' : la réponse retire un nœud absent de l\'écran, '.$selector);
				continue;
			}

			$node->remove();

		}

		foreach($changes['outer'] as $selector => $html) {

			$node = $document->querySelector($selector);

			if($node === NULL) {
				$this->assertFail($label.' : la réponse vise un nœud absent de l\'écran, '.$selector);
				continue;
			}

			// `ajax.js` analyse le html reçu hors de tout contexte : un conteneur neutre fait de même.
			$container = $document->createElement('div');
			$container->innerHTML = $html;

			$node->replaceWith(...iterator_to_array($container->childNodes));

		}

		foreach($changes['inner'] as $selector => $html) {

			$node = $document->querySelector($selector);

			if($node === NULL) {
				$this->assertFail($label.' : la réponse vise un nœud absent de l\'écran, '.$selector);
				continue;
			}

			$node->innerHTML = $html;

		}

		foreach($changes['attributes'] as $selector => $attributes) {

			$node = $document->querySelector($selector);

			if($node === NULL) {
				$this->assertFail($label.' : la réponse vise un nœud absent de l\'écran, '.$selector);
				continue;
			}

			// Une valeur `NULL` retire l'attribut, comme `compositionLine.v.php` (lot r24).
			foreach($attributes as $name => $value) {
				if($value === NULL) {
					$node->removeAttribute($name);
				} else {
					$node->setAttribute($name, $value);
				}
			}

		}

		$targeted = $this->getScreenPrint($document->body->innerHTML);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$full = $this->getScreenPrint($this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlan)));

		$this->assertTrue($this->getScreenPrint($before) !== $full, $label.' : le geste change l\'écran, sans quoi la comparaison ne prouverait rien');

		$differences = array_merge(
			array_keys(array_diff_assoc($targeted, $full)),
			array_keys(array_diff_key($full, $targeted))
		);

		$this->assertEquals([], array_values(array_unique($differences)), $label.' : la réponse ciblée rend l\'écran d\'un rechargement, sauf là');

	}

	/**
	 * L'empreinte d'un écran, `[clé => html]` : chaque rang de tableau qui porte un `id` sous son
	 * `id`, les autres rangs de chaque tableau triés sous le tableau qui les porte, puis ce qui reste.
	 * Ce qui change d'un rendu à l'autre sans que rien ne change est neutralisé : les `id` de champ
	 * que `FormUi` tire au hasard (`uniqid('field-')`), et la position du trait d'aujourd'hui sur la
	 * frise, calculée à la seconde. Les attributs de chaque élément se lisent triés : une case que
	 * la réponse sert reçoit `data-served` après ses autres attributs (`setAttribute()`), là où le
	 * rendu complet le pose à sa place, et le navigateur ne fait pas de différence entre les deux.
	 */
	private function getScreenPrint(string $html): array {

		$html = preg_replace('/field-[0-9a-f]{13}/', 'field-X', $html);
		$html = preg_replace('/(class="series-grid-now") style="[^"]*"/', '$1', $html);

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		foreach($document->body->querySelectorAll('*') as $node) {

			$attributes = [];

			foreach(iterator_to_array($node->attributes) as $attribute) {
				$attributes[$attribute->name] = $attribute->value;
				$node->removeAttribute($attribute->name);
			}

			ksort($attributes);

			foreach($attributes as $name => $value) {
				$node->setAttribute($name, $value);
			}

		}

		$print = [];

		foreach($document->querySelectorAll('tr[id]') as $node) {
			$print[$node->getAttribute('id')] = $node->innerHTML;
			$node->remove();
		}

		foreach($document->querySelectorAll('tbody') as $position => $node) {

			$rows = [];

			foreach(iterator_to_array($node->childNodes) as $row) {
				if($row instanceof \Dom\Element and $row->localName === 'tr') {
					$rows[] = $document->saveHtml($row);
					$row->remove();
				}
			}

			sort($rows);

			$print['tbody-'.$position] = implode("\n", $rows);

		}

		$print['screen'] = $document->body->innerHTML;

		return $print;

	}

	/**
	 * Une quantité changée sur une ligne proposée est une correction, que la carte de sa semaine dit
	 * « corrigée », et encore quand la quantité revient à celle de la règle : la semaine garde sa
	 * correction jusqu'au retour à la proposition (`CompositionWeekLib::amend()`). La case, elle,
	 * ne s'en distingue plus d'une case proposée (lot r21, retour du 27/09, « enlève cette
	 * distinction ») : sa classe reste celle d'une case servie dans les trois états. Le rang et la
	 * carte rendus sont ceux que la réponse du réglage de la quantité remplace (`getToggled()`).
	 */
	public function testQuantityCorrection(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];
		$eCompositionWeek = $eCompositionFormula['cCompositionWeek']->first();

		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->find(fn(CompositionLine $e) => $e['origin'] === CompositionLine::PROPOSED, limit: 1);
		$eCompositionLine['week'] = $eCompositionWeek;

		$week = $eCompositionWeek['week'];
		$speciesId = $eCompositionLine['species']['id'];

		$render = function() use($eStructure, $week, $speciesId): bool {

			$changes = new CompositionUi()->getToggled(CompositionPlanLib::getForScreen($eStructure, NULL), $week, $speciesId, FALSE);

			$cell = $changes['attributes']['#toolbox-composition-grid-species-'.$speciesId.' > td[data-week="'.$week.'"]'];

			$this->assertTrue($cell['data-served'] === '' and preg_match('/^[0-9,]+$/', $cell['data-quantity']) === 1, 'la case du geste est servie, avec sa quantité');

			return str_contains($changes['outer']['#toolbox-composition-card-'.$week], '>corrigée</span>');

		};

		$corrected = $render();

		$this->assertFalse($corrected, 'dans une semaine qui n\'est pas corrigée');

		$eCompositionLine['quantity'] = $eCompositionLine['quantityProposed'] + 1;

		CompositionLineLib::update($eCompositionLine, ['quantity']);

		$corrected = $render();

		$this->assertTrue($corrected, 'et la carte dit la semaine corrigée');

		$eCompositionLine['quantity'] = $eCompositionLine['quantityProposed'];

		CompositionLineLib::update($eCompositionLine, ['quantity']);

		$corrected = $render();

		$this->assertTrue($corrected, 'et la semaine reste corrigée, jusqu\'au retour à la proposition');

	}

	/**
	 * « Modifier les paniers » en colonnes (r32, planche paniers-maquette-r32, point 1) : un rang par
	 * réglage, titré une fois, et une cellule par taille, dans l'ordre des tailles, qui porte le
	 * `data-wrapper` de son champ pour que l'erreur se range sous la bonne taille. Les unités
	 * « paniers » et « produits » et les rappels de bornes quittent les cellules, trop étroites ;
	 * seul reste, sous l'écart, ce qu'il vaut en euros.
	 */
	public function testSettingsColumns(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));
		$large = $this->addFormula(CompositionPlanLib::getByStructure($eStructure), 'Le grand panier', 30.0);

		$eCompositionPlanSettings = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanSettings['structure'] = $eStructure;
		CompositionPlanLib::loadSettings($eCompositionPlanSettings);

		$formulaIds = array_keys($eCompositionPlanSettings['cCompositionFormula']->getArrayCopy());
		$this->assertEquals(2, count($formulaIds), 'deux tailles, deux colonnes');
		$this->assertTrue(in_array($large['id'], $formulaIds), 'dont la grande');

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, NULL, NULL)->body.'</body></html>', LIBXML_NOERROR);
		$form = $document->querySelector('form');

		$properties = ['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'base'];
		$rows = iterator_to_array($form->querySelectorAll('.toolbox-composition-settings-row'));

		foreach($properties as $index => $property) {

			$row = $rows[$index];
			$group = $row->parentElement->closest('.form-group');

			$this->assertEquals($property, $group->getAttribute('data-wrapper'), $property.', le rang ne prend l\'erreur d\'aucune taille');

			$cells = $this->getChildElements($row);

			$this->assertEquals(array_map(fn(int $formulaId) => $property.'['.$formulaId.']', $formulaIds), array_map(fn(\Dom\Element $cell) => $cell->getAttribute('data-wrapper'), $cells), $property.', une cellule par taille, dans leur ordre');

			foreach($cells as $position => $cell) {

				$name = $property.'['.$formulaIds[$position].']';

				$this->assertTrue($cell->querySelector('[name="'.$name.'"]') !== NULL, $name.', son champ dans sa cellule');
				$this->assertEquals(0, $cell->querySelectorAll('[name]:not([name="'.$name.'"])')->length, $name.', et aucun autre');

			}

		}

	}

	/**
	 * « Recommencer les paniers » (r31, #96) est en bas de « Modifier les paniers », dans un encadré à
	 * part et hors du formulaire : il rouvre le départ, où le plan de culture se choisit comme la
	 * première fois. Le lien ne touche pas au plan, et sa confirmation dit, au futur, ce que le
	 * prochain envoi du départ remplacera, les corrections comprises dès qu'il y en a une. Le panneau
	 * se lit à chaque ouverture (`CompositionPlanLib::loadSettings()`), donc le message suit les gestes
	 * du plan sans qu'aucun ne le repose. Recommencer rend un plan sans correction, et garde le prix
	 * tapé sur une espèce (lot N).
	 */
	public function testRestartRender(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$settings = function() use($eStructure): array {
			$eCompositionPlanSettings = CompositionPlanLib::getByStructure($eStructure);
			$eCompositionPlanSettings['structure'] = $eStructure;
			CompositionPlanLib::loadSettings($eCompositionPlanSettings);
			return [$eCompositionPlanSettings['losses'], html_entity_decode(new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, NULL, NULL)->body, ENT_QUOTES)];
		};

		[$losses, $html] = $settings();

		$this->assertEquals(['amended' => 0], $losses, 'un panier, aucune correction');

		$restart = substr($html, strpos($html, '</form>'));

		$this->assertTrue(str_contains($restart, '<a href="'.CompositionUi::urlStart($eStructure).'" data-confirm='), 'le lien rouvre le départ, après confirmation');

		$eCompositionFormula = CompositionFormulaLib::getByPlan(CompositionPlanLib::getByStructure($eStructure))->first();
		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 20);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::delete($eCompositionLine);

		$this->addFormula(CompositionPlanLib::getByStructure($eStructure), 'Le grand panier', 30.0);

		[$losses, $html] = $settings();

		$this->assertEquals(['amended' => 1], $losses, 'deux paniers, une semaine corrigée');

		// Un prix tapé sur une espèce (lot N), son pas et son minimum (revue v12) : des réponses que le
		// départ ne redemande pas.
		$eCompositionPlanOld = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanOld['structure'] = $eStructure;

		$speciesIdPriced = $eCompositionLine['species']['id'];

		$eCompositionSpecies = $this->getSpecies($eCompositionPlanOld, $speciesIdPriced);
		$eCompositionSpecies['price'] = 7.25;
		$eCompositionSpecies['step'] = 1.0;
		$eCompositionSpecies['minimum'] = 2.0;

		CompositionSpeciesLib::update($eCompositionSpecies, ['price', 'step', 'minimum']);

		$this->assertEquals([$speciesIdPriced => 7.25], array_map('floatval', CompositionSpeciesLib::getTypedPrices($eCompositionPlanOld)), 'une espèce porte un prix');

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, 2026);
		CompositionPlanLib::restart($eCompositionPlanStart);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		// Le plan qui remplace l'ancien écrit ses propres espèces : la réglée se retrouve par sa plante
		// et son nom, sous un autre id.
		$speciesIdPriced = $this->getSpeciesRestarted($eCompositionPlan, $eCompositionSpecies)['id'];

		$this->assertEquals(['amended' => 0], $settings()[0], 'recommencer efface les corrections');
		$this->assertEquals(1, CompositionPlan::model()->whereStructure($eStructure)->whereSeason(2026)->count(), 'un seul plan');
		$this->assertEquals([$speciesIdPriced => 7.25], array_map('floatval', CompositionSpeciesLib::getTypedPrices($eCompositionPlan)), 'et le prix tapé sur l\'espèce, elle seule');
		$this->assertEquals([$speciesIdPriced => ['step' => 1.0, 'minimum' => 2.0, 'quantity' => NULL]], CompositionSpeciesLib::getStepsByPlan($eCompositionPlan), 'et son pas et son minimum');

		$quantities = CompositionLine::model()
			->whereWeek('IN', CompositionWeekLib::getByFormulas(CompositionFormulaLib::getByPlan($eCompositionPlan)))
			->whereSpecies($speciesIdPriced)
			->getColumn('quantity');

		$this->assertTrue($quantities !== [], 'l\'espèce réglée est proposée');
		$this->assertEquals([], array_filter($quantities, fn(float $quantity) => $quantity < 2.0 or CompositionLib::isStepMultiple($quantity, 1.0) === FALSE), 'en pas entiers, jamais sous 2');


		// Recommencer : le départ garde les réponses du plan et ses espèces, et propose le menu des
		// saisons comme au premier départ.
		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure);

		$this->assertEquals(2026, $eCompositionPlanStart['season'], 'sans saison, le départ garde celle du plan');
		$this->assertTrue($eCompositionPlanStart['followed'], 'et ses espèces');
		$seasonsExpected = array_keys(CompositionLib::getCultivatedBySeason($eStructure) + [2026 => []]);
		rsort($seasonsExpected);

		$this->assertEquals($seasonsExpected, array_keys($eCompositionPlanStart['cultivatedSeasons']), 'le menu des saisons, comme au premier départ');
		$this->assertTrue(count($seasonsExpected) > 1, 'avec les autres plans de culture de la structure : '.implode(', ', $seasonsExpected));
		$html = new CompositionStartUi()->getStart($eCompositionPlanStart);

		$this->assertTrue(str_contains($html, 'id="toolbox-composition-start" class="onboarding toolbox-composition-start" data-step="1"'), 'ouvert sur la première étape');

		// Une autre saison du menu rouvre le départ sur elle (`?season`), et les paniers la suivront :
		// c'est le seul geste qui change la saison du plan depuis que la roue n'en porte plus.
		$season = array_values(array_diff(array_keys($eCompositionPlanStart['cultivatedSeasons']), [2026]))[0];

		$this->assertTrue(str_contains($html, 'data-url="'.encode(CompositionUi::urlStart($eStructure, $season)).'"'), 'la saison '.$season.' rouvre le départ sur elle');

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, $season);

		$this->assertTrue($eCompositionPlanStart['seasonAsked'], 'la saison est choisie');

		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.new CompositionStartUi()->getStart($eCompositionPlanStart));
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);
		$xpath = new \DOMXPath($document);

		$this->assertEquals('3', $xpath->query('//*[@id="toolbox-composition-start"]')->item(0)->getAttribute('data-step'), 'et le départ rouvre sur l\'étape des espèces, où elle l\'a été');
		$this->assertEquals((string)$season, $xpath->query('//form[@id="toolbox-composition-start-form"]//input[@name="season"]')->item(0)->getAttribute('value'), 'son envoi poste la saison choisie');
		$this->assertFalse($eCompositionPlanStart['followed'], 'un autre plan de culture');
		$this->assertTrue($eCompositionPlanStart['restart'], 'remplace le plan');
		$this->assertEquals($eCompositionPlan['weeks'], $eCompositionPlanStart['weeks'], 'avec ses semaines');
		$this->assertEquals(2, $eCompositionPlanStart['cCompositionFormula']->count(), 'et ses tailles');
		$this->assertTrue(array_key_exists($season, $eCompositionPlanStart['cultivatedSeasons']), 'le menu des saisons coche celle-ci');

		$cultivated = CompositionLib::getCultivatedBySeason($eStructure)[$season] ?? [];
		$natural = $eCompositionPlanStart['cCompositionSpecies']->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL)->getKeys();
		$expected = array_values(array_intersect(array_keys($eCompositionPlanStart['cCompositionSpecies']->getArrayCopy()), $cultivated));

		$this->assertEquals($expected === [] ? $eCompositionPlanStart['cCompositionSpecies']->getKeys() : $expected, $natural, 'les espèces du plan de culture '.$season);

		CompositionPlanLib::restart($eCompositionPlanStart);

		$this->assertEquals([$season], CompositionPlan::model()->whereStructure($eStructure)->getColumn('season'), 'un seul plan, qui suit le plan de culture '.$season);

	}

	/**
	 * Le panier de chaque semaine en carte sous la grille (planche r5, question 1) : une carte par
	 * semaine livrée, sous son propre identifiant, sans `onrender`, et sans portes tant que la semaine
	 * n'est pas corrigée (`testCardReset()`) ; ses quantités se règlent d'un pas et se tapent en
	 * place, sous des identifiants que le menu d'une case de la même semaine ne reprend pas.
	 */
	public function testWeekCards(): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {

			$this->createStarted($eStructure, 2026, range(10, 40));

			$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
			$cCompositionWeek = $eCompositionPlan['eCompositionFormula']['cCompositionWeek'];

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getProposal($eCompositionPlan).'</body></html>', LIBXML_NOERROR);

			$cards = iterator_to_array($document->querySelectorAll('#toolbox-composition-formula > .util-cards > .util-deck'));

			$this->assertEquals(
				array_map(fn(int $week) => 'toolbox-composition-card-'.$week, $cCompositionWeek->getColumn('week')),
				array_map(fn(\Dom\Element $node) => $node->getAttribute('id'), $cards),
				'une carte par semaine livrée, dans l\'ordre des semaines'
			);

			// Une carte de départ n'est pas corrigée : sa seule porte est « Mettre en vente » (r37b), que
			// l'administrateur d'une structure qui peut créer une vente voit sur chaque semaine qui a un
			// panier.
			$this->assertEquals([], array_values(array_filter($cards, fn(\Dom\Element $node) => $node->hasAttribute('onrender') or $node->querySelector('.util-deck-band a[data-ajax]') !== NULL)), 'ni onrender ni porte de retour à la proposition');

			$eCompositionWeek = $cCompositionWeek->find(fn(CompositionWeek $e) => $e['cCompositionLine']->notEmpty(), limit: 1);
			$speciesId = $eCompositionWeek['cCompositionLine']->first()['species']['id'];

			$card = $document->getElementById('toolbox-composition-card-'.$eCompositionWeek['week']);

			$this->assertTrue($card->querySelector('#toolbox-composition-quantity-card-'.$eCompositionWeek['week'].'-'.$speciesId) !== NULL, 'et se tape en place');

			// Le menu de la case de la même semaine porte la même quantité sous son propre identifiant :
			// la carte et le menu sont dans l'écran ensemble (lot r24).
			$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $eCompositionWeek['week']);
			$menu = new CompositionUi()->getCellMenu($eCompositionPlan, $speciesId, $eCompositionWeek['week']);

			$this->assertTrue(str_contains($menu, 'id="toolbox-composition-quantity-menu-'.$eCompositionWeek['week'].'-'.$speciesId.'"'), 'le menu de la case a ses propres identifiants');
			$this->assertFalse(str_contains($menu, 'id="toolbox-composition-quantity-card-'), 'sans reprendre ceux des cartes');

		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	/**
	 * Le moins et le plus d'une ligne (`CompositionWeekLib::applySteps()`) : un pas de l'espèce de
	 * chaque côté, sans reliquat binaire, et pas de moins au minimum. Le moins s'éteint alors. Sous le
	 * minimum, le plus mène au minimum.
	 */
	public function testWeekSteps(): void {

		$speciesId = array_key_first(CompositionLib::getSource()['units']);

		$eUnit = new \product\Unit(['id' => CompositionLib::getSource()['units'][$speciesId]['unit']]);

		$line = fn(float $quantity) => new CompositionLine(['species' => new CompositionSpecies(['id' => $speciesId]), 'unit' => $eUnit, 'quantity' => $quantity]);

		$cCompositionLine = new \Collection([$line(0.3), $line(0.2), $line(1.0)]);
		$cCompositionWeek = new \Collection([new CompositionWeek(['cCompositionLine' => $cCompositionLine])]);

		CompositionWeekLib::applySteps($cCompositionWeek, [$speciesId => ['step' => 0.1, 'minimum' => 0.2]], CompositionLib::getSource());

		// `getColumn()` passe les valeurs nulles : les lignes se lisent une à une.
		$this->assertEquals([0.2, NULL, 0.9], $cCompositionLine->makeArray(fn(CompositionLine $e) => $e['quantityLess']), 'un pas de moins, jusqu\'au minimum');
		$this->assertEquals([0.4, 0.3, 1.1], $cCompositionLine->makeArray(fn(CompositionLine $e) => $e['quantityMore']), 'un pas de plus');
		$this->assertTrue($cCompositionLine[0]['quantityLess'] === 0.2, 'sans reliquat binaire');

		// Une ligne écrite sous le minimum que la communauté a donné depuis (lot r25) monte d'un coup
		// au minimum : un pas de plus resterait dessous, et la quantité le refuserait.
		$cCompositionLineBelow = new \Collection([$line(0.1)]);

		CompositionWeekLib::applySteps(new \Collection([new CompositionWeek(['cCompositionLine' => $cCompositionLineBelow])]), [$speciesId => ['step' => 0.1, 'minimum' => 0.5]], CompositionLib::getSource());

		$this->assertEquals([NULL, 0.5], [$cCompositionLineBelow[0]['quantityLess'], $cCompositionLineBelow[0]['quantityMore']], 'sous le minimum, pas de moins, et le plus mène au minimum');

	}

	/**
	 * Les gestes d'une semaine. Sa carte, sous la grille, liste son panier dans l'ordre de la grille,
	 * chaque quantité réglable d'un pas et tapée en place, sans retirer ni ajouter d'espèce. Le menu
	 * de chaque case servie porte la même quantité, postée au même quick sous son propre identifiant
	 * (lot r24), et celui d'une case de saison un plus, qui sert son espèce à son minimum. La grille
	 * n'a plus de colonne ouverte : ses prix sont tous à l'euro, et « Revenir à la proposition » vit
	 * dans la carte d'une semaine corrigée, alors seulement, et pour qui peut écrire le plan. Une
	 * semaine non livrée n'a pas de case.
	 */
	public function testCardGestures(): void {

		// Une quantité ne se règle en place que pour celui qui gère la structure (`assertUpdate`) :
		// sans lui, la grille rend le texte seul et le contrôle ne verrait rien.
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkCardGestures($eStructure);
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkCardGestures(\structure\Structure $eStructure): void {

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$eCompositionWeek = $eCompositionFormula['cCompositionWeek']->find(fn($e) => $e['cCompositionLine']->notEmpty(), limit: 1);
		$week = $eCompositionWeek['week'];

		$eCompositionLine = $eCompositionWeek['cCompositionLine']->first();
		$speciesId = $eCompositionLine['species']['id'];

		$card = html_entity_decode(new CompositionUi()->getReset($eCompositionPlan, $week, [])['outer']['#toolbox-composition-card-'.$week], ENT_QUOTES);

		$this->assertTrue(str_contains($card, 'compositionLine:doDelete') === FALSE, 'ni ne retire de ligne');
		$this->assertTrue(str_contains($card, 'compositionLine:doCreate') === FALSE, 'ni n\'ajoute d\'espèce');
		$this->assertTrue(str_contains($card, 'compositionWeek:doReset') === FALSE, 'ni ne revient à la proposition');
		$this->assertTrue(str_contains($card, 'composition:doWeek') === FALSE, 'ni ne retire la semaine');

		$posts = [
			'structure' => $eStructure['id'],
			'formula' => $eCompositionFormula['id'],
			'week' => $week,
			'species' => $speciesId,
		];

		// La quantité de l'espèce se règle en place dans la carte (planche r5, question 2), puis dans
		// le menu de sa case (lot r24), au même quick et sous l'identifiant de chacun.
		$this->checkQuantityPosts($card, 'card', $week, $speciesId, $posts, $eCompositionLine['quantityMore'], 'dans la carte');

		$this->checkQuantityTemplate(html_entity_decode(new CompositionUi()->getFormulaPanel($eCompositionPlan), ENT_QUOTES), $eCompositionPlan);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);

		$this->checkQuantityPosts(html_entity_decode(new CompositionUi()->getCellMenu($eCompositionPlan, $speciesId, $week), ENT_QUOTES), 'menu', $week, $speciesId, $posts, $eCompositionLine['quantityMore'], 'dans le menu de la case');

		$grid = html_entity_decode(new CompositionUi()->getGrid($eCompositionPlan, $eCompositionPlan['eCompositionFormula']), ENT_QUOTES);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$grid.'</body></html>', LIBXML_NOERROR);

		// Le gérant a une grille qui s'annonce modifiable et porte l'unique liste du menu, que chaque
		// case remplit à son ouverture (`ToolboxCompositionGrid.menu()`). La case servie reste un rond,
		// sans geste dans la grille.
		$this->assertTrue($document->getElementById('toolbox-composition-grid')->hasAttribute('data-editable'), 'la grille du gérant s\'annonce modifiable');
		$this->assertEquals(1, $document->querySelectorAll('#toolbox-composition-grid > #toolbox-composition-cell-menu[data-dropdown-id="toolbox-composition-cell-menu-list"]')->length, 'et porte la liste du menu');

		$cell = $document->querySelector('#toolbox-composition-grid-species-'.$speciesId.' > td[data-week="'.$week.'"]');

		$this->assertTrue($cell->hasAttribute('data-served'), 'la case servie s\'ouvre');

		$this->assertTrue(str_contains($grid, 'composition:doWeek') === FALSE, 'et la semaine ne se retire pas de la grille');

		// Le menu d'une case de saison porte le champ de sa quantité, prérempli à son minimum (lot r8b,
		// repris aux lots r24 et r24b).
		$rowSeason = NULL;

		foreach($eCompositionPlan['eCompositionFormula']['grid'] as $group) {
			foreach($group['species'] as $rowGrid) {
				if($rowSeason === NULL and isset($rowGrid['open'][$week]) and isset($rowGrid['lines'][$week]) === FALSE) {
					$rowSeason = $rowGrid;
				}
			}
		}

		if($rowSeason === NULL) {
			$this->assertFail('aucune case de saison dans la semaine '.$week.' : le contrôle ne verrait rien');
			return;
		}

		$menu = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getCellMenu($eCompositionPlan, $rowSeason['species']['id'], $week).'</body></html>', LIBXML_NOERROR);

		$this->assertEquals('/toolbox/compositionLine:doCreate', $menu->querySelector('form')?->getAttribute('data-ajax-form'), 'mais le formulaire qui ajoute l\'espèce');
		$this->assertEquals([
			'structure' => (string)$eStructure['id'],
			'formula' => (string)$eCompositionFormula['id'],
			'week' => (string)$week,
			'species' => (string)$rowSeason['species']['id'],
			'quantity' => (string)$this->getGridRow($eCompositionPlan['eCompositionFormula'], $rowSeason['species']['id'])['eCompositionLineAddable']['quantity'],
		], array_combine(['structure', 'formula', 'week', 'species', 'quantity'], array_map(fn(string $name) => $menu->querySelector('form input[name="'.$name.'"]')?->getAttribute('value'), ['structure', 'formula', 'week', 'species', 'quantity'])), 'la case et la quantité que la grille lui prépare');

		// Une quantité réglée corrige la semaine, que sa carte offre alors de ramener à la proposition
		// (lot r24) : la porte était en tête de la colonne ouverte, partie avec elle.
		$eCompositionLine['quantity'] = (float)$eCompositionLine['quantityProposed'] + 1;

		CompositionLineLib::update($eCompositionLine, ['quantity']);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, $week);

		$changes = new CompositionUi()->getReset($eCompositionPlan, $week, []);
		$card = html_entity_decode($changes['outer']['#toolbox-composition-card-'.$week], ENT_QUOTES);

		$this->assertEquals(1, preg_match('/<a data-ajax="\/toolbox\/compositionWeek:doReset" ([^>]*)>/', $card, $matches), 'la carte offre de revenir à la proposition');

		foreach(['structure' => $eStructure['id'], 'formula' => $eCompositionFormula['id'], 'week' => $week] as $name => $value) {
			$this->assertTrue(str_contains($matches[1], 'post-'.$name.'="'.$value.'"'), 'qui poste '.$name);
		}

		$this->assertTrue(str_contains($card, 'composition:doWeek') === FALSE, 'sans retirer la semaine');

		// La porte ne se montre qu'à qui peut écrire le plan.
		$eUserManager = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline(new \user\User());

		try {
			$this->assertTrue(str_contains(new CompositionUi()->getReset(CompositionPlanLib::getForScreen($eStructure, NULL, $week), $week, [])['outer']['#toolbox-composition-card-'.$week], 'compositionWeek:doReset') === FALSE, 'personne de connecté, pas de porte');
		} finally {
			\user\ConnectionLib::setOnline($eUserManager);
		}

		// Une semaine non livrée n'a pas de case, quoi que poste la page.
		$this->assertEquals(NULL, CompositionPlanLib::getForScreen($eStructure, NULL, 5)['week'], 'une semaine non livrée n\'a pas de case');

	}

	/**
	 * La quantité de l'espèce `$speciesId` à la semaine `$week`, rendue à `$place` : le plus poste un pas
	 * de plus au quick de la ligne, et la valeur tapée y poste en util-quick-inline, sous
	 * l'identifiant de `$place`.
	 */
	private function checkQuantityPosts(string $html, string $place, int $week, int $speciesId, array $posts, float $quantityMore, string $label): void {

		$this->assertEquals(1, preg_match('/<a data-ajax="\/@module\/toolbox\/CompositionLine\/doQuick" ([^>]*post-species="'.$speciesId.'"[^>]*)title="Plus">/', $html, $matches), $label.' : la quantité se règle d\'un pas en place');
		$this->assertTrue(str_contains($matches[1], 'post-property="quantity"'), $label.' : sur sa quantité');
		$this->assertTrue(str_contains($matches[1], 'post-quantity="'.$quantityMore.'"'), $label.' : un pas au-dessus');

		foreach($posts as $name => $value) {
			$this->assertTrue(str_contains($matches[1], 'post-'.$name.'="'.$value.'"'), $label.' : qui poste '.$name);
		}

		// La carte ne porte que ce qui distingue sa ligne, que `composition.js` pose dans le formulaire
		// de `checkQuantityTemplate()` au premier clic (lot P10). Le menu d'une case porte le sien.
		if($place === 'card') {

			$this->assertEquals(1, preg_match('/ id="toolbox-composition-quantity-card-'.$week.'-'.$speciesId.'" ([^>]*)>/', $html, $matches), $label.' : et se tape en place');

			foreach(['week' => $week, 'species' => $speciesId] as $name => $value) {
				$this->assertTrue(str_contains($matches[1], 'data-'.$name.'="'.$value.'"'), $label.' : le champ recevra '.$name);
			}

			return;

		}

		$this->assertEquals(1, preg_match('/ id="toolbox-composition-quantity-'.$place.'-'.$week.'-'.$speciesId.'">(.*?)<\/form>/s', $html, $matches), $label.' : et se tape en place');
		$this->assertTrue(str_contains($matches[1], 'data-ajax-form="/@module/toolbox/CompositionLine/doQuick"'), $label.' : au quick de la ligne');

		foreach($posts + ['property' => 'quantity'] as $name => $value) {
			$this->assertEquals(1, preg_match('/name="'.$name.'" type="hidden" value="'.$value.'"/', $matches[1]), $label.' : le champ poste '.$name);
		}

	}

	/**
	 * Le formulaire de la quantité des cartes, rendu une fois dans l'onglet de la taille
	 * (`CompositionUi::getWeekQuantityTemplate()`, lot P10) : il poste au quick de la ligne ce qui
	 * vaut pour toutes les lignes de l'onglet, et laisse vides la semaine, l'espèce, la valeur et
	 * l'unité, que `composition.js` y pose depuis la ligne ouverte. Chaque ligne de chaque carte porte
	 * ces quatre valeurs.
	 */
	private function checkQuantityTemplate(string $html, CompositionPlan $eCompositionPlan): void {

		$this->assertEquals(1, preg_match_all('/<template id="toolbox-composition-quantity-template">(.*?)<\/template>/s', $html, $matches), 'l\'onglet porte un seul formulaire de quantité');

		$template = $matches[1][0];

		$this->assertTrue(str_contains($template, 'data-ajax-form="/@module/toolbox/CompositionLine/doQuick"'), 'qui poste au quick de la ligne');

		$posts = [
			'property' => 'quantity',
			'structure' => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionPlan['eCompositionFormula']['id'],
			'week' => '',
			'species' => '',
		];

		foreach($posts as $name => $value) {
			$this->assertEquals(1, preg_match('/name="'.$name.'" type="hidden" value="'.$value.'"/', $template), 'le formulaire poste '.$name);
		}

		$this->assertEquals(1, preg_match('/<input [^>]*name="quantity" type="number"\/>/', $template), 'et la quantité, sans valeur');

		$lines = 0;

		foreach($eCompositionPlan['eCompositionFormula']['cCompositionWeek'] as $eCompositionWeek) {

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$attributes = 'id="toolbox-composition-quantity-card-'.$eCompositionWeek['week'].'-'.$eCompositionLine['species']['id'].'" data-week="'.$eCompositionWeek['week'].'" data-species="'.$eCompositionLine['species']['id'].'" data-quantity="'.$eCompositionLine['quantity'].'" data-unit="'.\product\UnitUi::getSingular($eCompositionLine['unit'], short: TRUE).'"';

				$this->assertTrue(str_contains($html, $attributes), 'chaque ligne porte sa semaine, son espèce, sa quantité et son unité');
				$lines++;

			}

		}

		$this->assertEquals($lines, substr_count($html, 'data-composition-quantity>'), 'et toutes les lignes s\'ouvrent au clic');
		$this->assertTrue($lines > 0, 'sur un onglet qui a des lignes');

	}

	/**
	 * La ligne que la case de chaque rang peut recevoir, que son menu propose dans son champ
	 * (`CompositionLib::applyAddableToGrid()`, lots r8 à r8c, repris aux lots r24 et r24b), sur une
	 * grille et une source faites à la main : l'espèce, son unité au panier et sa quantité minimum
	 * pour une case ouverte cette semaine que la semaine ne sert pas, aucune pour une espèce servie
	 * et pour une case fermée (lot r24). La grille dit quelles cases s'ouvrent (`open`,
	 * `CompositionLib::getGrid()`) : ni une espèce sans unité au panier, ni une case hors de la
	 * saison de l'espèce ; la grille faite à la main le rend comme elle. Un chou s'ouvre à côté d'un
	 * autre (r34d).
	 *
	 * Le minimum est celui que la structure a réglé quand elle en a un, même quand la communauté en
	 * donne un autre, sinon celui de la communauté arrondi au pas, sinon le pas de l'unité
	 * (`CompositionLib::getStep()`, lot r25) : l'épinard, le poireau et l'ail distinguent les trois.
	 */
	public function testAddable(): void {

		$species = fn(int $id, string $name) => new CompositionSpecies(['id' => $id, 'name' => NULL, 'plant' => new \plant\Plant(['id' => $id, 'name' => $name])]);
		$line = new CompositionLine();

		$cUnit = \product\Unit::model()
			->select(['id'])
			->whereStructure(NULL)
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		[$unitFirst, $unitSecond] = $cUnit->getIds();

		$source = [
			'units' => array_fill_keys([1, 4, 5, 6, 7], ['unit' => $unitFirst, 'quantity' => 1.0, 'minimum' => NULL, 'step' => 0.5]) + [
				2 => ['unit' => $unitFirst, 'quantity' => 1.0, 'minimum' => 0.6, 'step' => 0.5],
				3 => ['unit' => $unitSecond, 'quantity' => 1.0, 'minimum' => NULL, 'step' => 1.0],
				9 => ['unit' => $unitFirst, 'quantity' => 2.0, 'minimum' => 1.2, 'step' => 0.5],
			],
			'plants' => [],
			'speciesBare' => [],
		];

		$steps = [2 => ['step' => 0.5, 'minimum' => 1.5]];

		$grid = [
			['species' => [
				['species' => $species(1, 'Radis'), 'lines' => [20 => $line], 'open' => [20 => 20]],
				['species' => $species(2, 'Épinard'), 'lines' => [], 'open' => [20 => 20]],
				['species' => $species(3, 'Ail'), 'lines' => [], 'open' => [20 => 20]],
			]],
			['species' => [
				['species' => $species(4, 'Tomate'), 'lines' => [21 => $line], 'open' => []],
				['species' => $species(5, 'Chou rouge'), 'lines' => [], 'open' => [20 => 20, 21 => 21]],
				['species' => $species(6, 'Chou-fleur'), 'lines' => [20 => $line], 'open' => []],
				['species' => $species(7, 'Courgette'), 'lines' => [], 'open' => []],
				['species' => $species(8, 'Aubergine'), 'lines' => [], 'open' => []],
				['species' => $species(9, 'Poireau'), 'lines' => [], 'open' => [20 => 20]],
			]],
		];

		$addable = function(int $week) use($grid, $source, $steps): array {

			$rows = array_merge(...array_column(CompositionLib::applyAddableToGrid($grid, $week, $source, $steps), 'species'));

			return array_combine(
				array_map(fn(array $row) => $row['species']->getLabel(), $rows),
				array_map(fn(array $row) => $row['eCompositionLineAddable']->empty() ? NULL : [$row['eCompositionLineAddable']['species']['id'], $row['eCompositionLineAddable']['unit']['id'], $row['eCompositionLineAddable']['quantity']], $rows)
			);

		};

		$this->assertEquals([
			'Radis' => NULL, 'Épinard' => [2, $unitFirst, 1.5], 'Ail' => [3, $unitSecond, 1.0],
			'Tomate' => NULL, 'Chou rouge' => [5, $unitFirst, 0.5], 'Chou-fleur' => NULL, 'Courgette' => NULL, 'Aubergine' => NULL, 'Poireau' => [9, $unitFirst, 1.0],
		], $addable(20), 'semaine 20 : le radis et le chou-fleur servis, le chou rouge ouvert à côté du chou-fleur, la tomate et la courgette hors saison, l\'aubergine sans unité, fermés par la grille (`open`), le poireau au minimum de la communauté arrondi au pas');

		$this->assertEquals([
			'Radis' => NULL, 'Épinard' => NULL, 'Ail' => NULL,
			'Tomate' => NULL, 'Chou rouge' => [5, $unitFirst, 0.5], 'Chou-fleur' => NULL, 'Courgette' => NULL, 'Aubergine' => NULL, 'Poireau' => NULL,
		], $addable(21), 'semaine 21 : seul le chou rouge s\'ouvre');

		$rows = array_merge(...array_column(CompositionLib::applyAddableToGrid($grid, 20, $source, $steps), 'species'));

		$this->assertEquals(['singular', 'plural', 'short', 'type'], array_values(array_diff(array_keys($rows[1]['eCompositionLineAddable']['unit']->getArrayCopy()), ['id'])), 'l\'unité porte ce que le champ en écrit');

	}

	/**
	 * Les espèces des rangs de `$grid` que leur case peut recevoir
	 * (`CompositionLib::applyAddableToGrid()`), dans l'ordre de la grille.
	 */
	private function getAddableSpecies(array $grid): array {

		$rows = array_merge(...array_column($grid, 'species'));

		return array_values(array_map(fn(array $row) => $row['species'], array_filter($rows, fn(array $row) => $row['eCompositionLineAddable']->notEmpty())));

	}

	private function getAddableSpeciesIds(array $grid): array {
		return array_map(fn(CompositionSpecies $eCompositionSpecies) => $eCompositionSpecies['id'], $this->getAddableSpecies($grid));
	}

	/**
	 * Le rang de l'espèce `$speciesId` dans la grille de la formule.
	 */
	private function getGridRow(CompositionFormula $eCompositionFormula, int $speciesId): array {

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				if($row['species']['id'] === $speciesId) {
					return $row;
				}
			}
		}

		throw new \Exception('Plant '.$speciesId.' not in the grid');

	}

	/**
	 * Le sélecteur de la tête du groupe de l'espèce `$speciesId` dans la grille, écrit ici en toutes
	 * lettres plutôt que relu de `CompositionUi::getOrganKey()` : c'est l'identifiant que l'écran
	 * porte qu'on vérifie.
	 */
	private function getGroupHead(CompositionFormula $eCompositionFormula, int $speciesId): string {

		$key = match($this->getGridRow($eCompositionFormula, $speciesId)['species']['plant']['organ']) {
			\plant\Plant::LEAF => 'leaf',
			\plant\Plant::ROOT => 'root',
			\plant\Plant::FRUIT => 'fruit',
			NULL => 'other',
		};

		return '#toolbox-composition-grid-'.$key.'-head';

	}

	/**
	 * « Rejouer les paniers » (É13) au bout de la ligne des semaines, pour celui qui gère la
	 * structure : sans correction, il part sans confirmation ; avec une semaine corrigée, le message
	 * dit combien de semaines il recompose, laquelle perd sa correction, et que l'autre taille ne
	 * bouge pas. Dans les deux cas, le bouton dit « Composition en cours » pendant le rejeu (r62),
	 * le waiter ne partant qu'après une confirmation acceptée. Sans le droit d'écrire, pas de bouton.
	 */
	public function testReplayRender(): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertTrue(str_contains(new CompositionUi()->getProposal($eCompositionPlan), 'toolbox-composition-replay') === FALSE,'sans le droit d\'écrire, pas de rejeu');

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkReplayRender($eStructure);
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkReplayRender(\structure\Structure $eStructure): void {

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$html = new CompositionUi()->getProposal($eCompositionPlan);

		$this->assertTrue(preg_match('/<a [^>]*compositionFormula:doReplay[^>]*>/', $html, $match) === 1, 'la taille se rejoue');
		$this->assertTrue(str_contains($match[0], 'data-confirm') === FALSE, 'sans confirmation quand rien n\'est corrigé');
		$this->assertTrue(str_contains($match[0], 'post-id="'.$eCompositionFormula['id'].'"'), 'la taille se désigne par son id');
		$this->assertTrue(str_contains($match[0], 'data-waiter='), 'le bouton dit que la composition tourne');

		$this->addFormula(CompositionPlanLib::getByStructure($eStructure), 'Le grand panier', 30.0);

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 20);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::delete($eCompositionLine);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, $eCompositionFormula['id']);

		$html = html_entity_decode(new CompositionUi()->getProposal($eCompositionPlan), ENT_QUOTES);

		$this->assertTrue(preg_match('/<a [^>]*compositionFormula:doReplay[^>]*>/', $html, $match) === 1 and str_contains($match[0], 'data-confirm='), 'une correction demande confirmation');
		$this->assertTrue(str_contains($match[0], 'data-waiter='), 'la confirmation garde le message d\'attente');

	}

	/**
	 * Le prix tapé depuis le menu d'une espèce (lot M) : il se reporte sur les lignes de l'espèce
	 * dans toutes les tailles, et la valeur des semaines où elle entre bouge ; ni une quantité ni une
	 * correction ne bouge (Q9). Vidé, il rend aux lignes le prix de la cascade. Le menu le lit.
	 */
	public function testSpeciesPrice(): void {

		// Les gestes du menu ne se rendent que pour celui qui gère la structure (`assertUpdate`).
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSpeciesPrice(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSpeciesPrice(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$before = $this->getPlanLines($eCompositionPlan);

		$speciesId = $this->getSpeciesServedEverywhere($before);

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		$valuesBefore = $this->getWeekValues($eStructure);

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['price'], ['price' => '-1'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::price.check'], array_column($fw->get(), 1), 'un prix négatif refusé');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['price'], ['price' => '99.5'], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'prix admis');

		CompositionSpeciesLib::update($eCompositionSpecies, ['price']);

		$after = $this->getPlanLines($eCompositionPlan);

		$this->assertEquals(array_keys($before), array_keys($after), 'aucune ligne ajoutée ni retirée');

		$changed = 0;

		foreach($after as $lineId => $line) {

			$this->assertEquals($before[$lineId]['quantity'], $line['quantity'], 'ligne '.$lineId.' : la quantité ne bouge pas');
			$this->assertEquals($before[$lineId]['amended'], $line['amended'], 'ligne '.$lineId.' : la correction ne bouge pas');

			if($line['species'] === $speciesId) {
				$changed++;
				$this->assertEquals(99.5, $line['unitPrice'], 'ligne '.$lineId.' : le prix tapé, dans la taille '.$line['formula']);
			} else {
				$this->assertEquals($before[$lineId]['unitPrice'], $line['unitPrice'], 'ligne '.$lineId.' : une autre espèce garde son prix');
			}

		}

		$this->assertTrue($changed > 1, 'des lignes dans les deux tailles');

		$valuesAfter = $this->getWeekValues($eStructure);

		foreach($before as $line) {
			if($line['species'] === $speciesId) {
				$this->assertTrue($valuesAfter[$line['formula']][$line['week']] > $valuesBefore[$line['formula']][$line['week']], 'semaine '.$line['week'].' de '.$line['formula'].' : sa valeur monte');
			}
		}

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);

		$blocks = new CompositionUi()->getPriced($eCompositionPlanScreen, $speciesId);

		$panel = $this->getSpeciesPanel($this->getGridRow($eCompositionPlanScreen['eCompositionFormula'], $speciesId)['eCompositionSpecies']);

		$this->assertEquals(1, preg_match('/<input [^>]*name="price"[^>]*>/', $panel, $matches), 'le panneau a son champ du prix');
		$this->assertTrue(str_contains($matches[0], 'value="99.5"'), 'qui porte le prix tapé : '.$matches[0]);
		$this->assertTrue(isset($blocks['outer']['#toolbox-composition-grid']) === FALSE, 'sans rendre la grille entière');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['price'], ['price' => ''], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'prix vidé admis');
		$this->assertEquals(NULL, $eCompositionSpecies['price'], 'vidé, le prix est nul');

		CompositionSpeciesLib::update($eCompositionSpecies, ['price']);

		$cleared = $this->getPlanLines($eCompositionPlan);

		foreach($cleared as $lineId => $line) {
			$this->assertEquals($before[$lineId]['unitPrice'], $line['unitPrice'], 'ligne '.$lineId.' : le prix de la cascade revient');
			$this->assertEquals($before[$lineId]['quantity'], $line['quantity'], 'ligne '.$lineId.' : la quantité ne bouge toujours pas');
		}

	}

	/**
	 * Le pas de vente et la quantité minimale d'une espèce, réglés depuis son panneau (planche r1) : les
	 * lignes de l'espèce dans les semaines non corrigées de toutes les tailles s'arrondissent au pas et
	 * montent au minimum, sans recomposer ; une semaine corrigée garde sa quantité. Un minimum qui
	 * n'est pas un nombre entier de pas est refusé, dans les deux sens. Le panneau dit la vente de
	 * l'espèce, et ne propose le pas que sur une unité qui se coupe.
	 */
	public function testSpeciesStep(): void {

		// Les gestes du menu ne se rendent que pour celui qui gère la structure (`assertUpdate`).
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSpeciesStep(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSpeciesStep(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		// Une espèce qui se pèse, au pas de 0,1 : 0,3 n'en est pas l'arrondi, et des lignes bougent.
		$speciesId = $this->getSpeciesServedEverywhere(array_filter($this->getPlanLines($eCompositionPlan), fn(array $line) => $source['units'][$line['species']]['step'] !== 1.0));

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce pesée servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		// Une semaine corrigée à la main, sur une quantité qui n'est pas un multiple du pas à venir.
		$eCompositionLineAmended = CompositionLine::model()
			->select(CompositionLine::getSelection())
			->whereWeek('IN', CompositionWeekLib::getByFormula(CompositionFormulaLib::getByPlan($eCompositionPlan)->first()))
			->whereSpecies($speciesId)
			->get();

		$eCompositionLineAmended['quantity'] = 0.7;

		CompositionLineLib::update($eCompositionLineAmended, ['quantity']);

		$before = $this->getPlanLines($eCompositionPlan);

		$this->assertTrue($before[$eCompositionLineAmended['id']]['amended'], 'la semaine de la ligne réglée est corrigée');

		// Le pas.
		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['step'], ['step' => '0.3'], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'pas admis');

		CompositionSpeciesLib::update($eCompositionSpecies, ['step']);

		$this->checkSpeciesStepLines($eCompositionPlan, $speciesId, $before, ['step' => 0.3, 'minimum' => 0.3], 'au pas de 0,3');

		// Le minimum.
		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['minimum'], ['minimum' => '1'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::minimum.multiple'], array_column($fw->get(), 1), 'un minimum entre deux pas refusé');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['minimum'], ['minimum' => '0.9'], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'minimum admis');

		CompositionSpeciesLib::update($eCompositionSpecies, ['minimum']);

		$this->checkSpeciesStepLines($eCompositionPlan, $speciesId, $before, ['step' => 0.3, 'minimum' => 0.9], 'au minimum de 0,9');

		// Un pas qui ne divise plus le minimum en place.
		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['step'], ['step' => '0.2'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::step.minimum'], array_column($fw->get(), 1), 'un pas qui ne divise pas le minimum refusé');

		// Ce que l'écran en dit.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlanScreen['eCompositionFormula'];

		$eCompositionSpeciesScreen = $this->getGridRow($eCompositionFormula, $speciesId)['eCompositionSpecies'];
		$eUnit = $eCompositionSpeciesScreen['eUnit'];

		$panel = $this->getSpeciesPanel($eCompositionSpeciesScreen);

		$this->assertEquals(1, preg_match('/<input [^>]*name="step"[^>]*>/', $panel, $step), 'le panneau a le champ du pas');
		$this->assertEquals(1, preg_match('/<input [^>]*name="minimum"[^>]*>/', $panel, $minimum), 'et celui du minimum');
		$this->assertTrue(str_contains($step[0], 'value="0.3"'), 'le panneau dit le pas dans son champ : '.$step[0]);
		$this->assertTrue(str_contains($minimum[0], 'value="0.9"'), 'et le minimum : '.$minimum[0]);

		$this->assertTrue(str_contains(new \util\FormUi()->quick($eCompositionSpeciesScreen, 'step'), 'placeholder="'.\util\TextUi::number(CompositionLib::getSourceByPlan($eCompositionPlanScreen)['units'][$speciesId]['step'], NULL).'"'), 'vide, le pas est celui que le plan donne à l\'unité');
		$minimumDefault = CompositionLib::getMinimumCommunity(CompositionLib::getSourceByPlan($eCompositionPlanScreen)['units'][$speciesId]['minimum'], 0.3);

		$this->assertEquals($minimumDefault, $eCompositionSpeciesScreen['minimumDefault'], 'vide, le minimum est celui de la communauté au pas réglé');
		$this->assertTrue(str_contains(new \util\FormUi()->quick($eCompositionSpeciesScreen, 'minimum'), 'placeholder="'.\util\TextUi::number($minimumDefault, NULL).'"'), 'et le champ vide le propose');

		$eCompositionSpeciesWhole = new CompositionSpecies();

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				if($eCompositionSpeciesWhole->empty() and $row['eCompositionSpecies']->notEmpty() and $row['eCompositionSpecies']['divisible'] === FALSE) {
					$eCompositionSpeciesWhole = $row['eCompositionSpecies'];
				}
			}
		}

		if($eCompositionSpeciesWhole->empty()) {
			$this->assertFail('aucune espèce à la pièce ou à la botte dans la grille : le contrôle ne verrait rien');
		} else {
			$panel = $this->getSpeciesPanel($eCompositionSpeciesWhole);
			$this->assertEquals(1, preg_match('/<input [^>]*name="minimum"[^>]*>/', $panel, $minimum), 'une espèce à la pièce a le champ du minimum');
			$this->assertTrue(str_contains($minimum[0], 'placeholder="'.\util\TextUi::number($eCompositionSpeciesWhole['minimumDefault'], NULL).'"'), 'qui dit son minimum en attente : '.$minimum[0]);
			// Le pas reste dans le formulaire (r60), pour qu'une unité qui se coupe le rende (`composition.js`).
			$this->assertEquals(1, preg_match('/<div [^>]*data-follow="step"[^>]*>/', $panel, $step), 'le groupe du pas est posé');
			$this->assertTrue(preg_match('/class="[^"]*\\bhide\\b/', $step[0]) === 1 and preg_match('/<input [^>]*name="step"[^>]*>/', $panel, $stepInput) === 1 and str_contains($stepInput[0], 'disabled="disabled"'), 'sans son pas, ni à lire ni à changer : masqué, il ne poste rien');
			$this->assertTrue(str_contains($panel, 'name="minimum"'), 'mais règle son minimum');
		}

		$blocks = new CompositionUi()->getPriced($eCompositionPlanScreen, $speciesId);

		$weeksServed = array_keys($this->getGridRow($eCompositionFormula, $speciesId)['lines']);

		foreach($weeksServed as $week) {
			$this->assertTrue(isset($blocks['outer']['#toolbox-composition-card-'.$week]), 'la carte de la semaine '.$week.', où l\'espèce entre');
		}

	}

	/**
	 * Après un pas ou un minimum `$step` : les lignes de l'espèce des semaines non corrigées sont celles
	 * d'avant arrondies au pas et relevées au minimum, proposées comme servies ; toutes les autres,
	 * dont la ligne de la semaine corrigée, n'ont pas bougé, et aucune ligne n'est née ni partie.
	 */
	private function checkSpeciesStepLines(CompositionPlan $eCompositionPlan, int $speciesId, array $before, array $step, string $case): void {

		$after = $this->getPlanLines($eCompositionPlan);

		$this->assertEquals(array_keys($before), array_keys($after), $case.' : rien ne se recompose');

		$changed = [];

		foreach($after as $lineId => $line) {

			$this->assertEquals($before[$lineId]['amended'], $line['amended'], $case.', ligne '.$lineId.' : la correction ne bouge pas');

			if($line['species'] === $speciesId and $line['amended'] === FALSE) {

				$this->assertEquals(CompositionLib::roundToStep($before[$lineId]['quantity'], $step), $line['quantity'], $case.', ligne '.$lineId.' : arrondie');
				$this->assertTrue(CompositionLib::isStepMultiple($line['quantity'], $step['step']) and $line['quantity'] >= $step['minimum'], $case.', ligne '.$lineId.' : un nombre entier de pas, au moins le minimum');

				if($line['quantity'] !== $before[$lineId]['quantity']) {
					$changed[$line['formula']] = TRUE;
				}

			} else {
				$this->assertEquals($before[$lineId]['quantity'], $line['quantity'], $case.', ligne '.$lineId.' : ne bouge pas');
			}

		}

		$this->assertEquals(2, count($changed), $case.' : des lignes arrondies dans les deux tailles');

		$quantities = CompositionLine::model()
			->select(['quantity', 'quantityProposed'])
			->whereWeek('IN', CompositionWeek::model()
				->select('id')
				->whereFormula('IN', CompositionFormulaLib::getByPlan($eCompositionPlan))
				->whereAmended(FALSE)
				->getSql())
			->whereSpecies($speciesId)
			->getCollection();

		foreach($quantities as $eCompositionLine) {
			$this->assertEquals($eCompositionLine['quantity'], $eCompositionLine['quantityProposed'], $case.' : la proposition suit la quantité servie');
		}

	}

	/**
	 * « Retirer de mes cultures » (lot M) : l'espèce quitte les semaines non corrigées de toutes les
	 * tailles et reste dans les semaines corrigées, qui ne bougent pas.
	 */
	public function testSpeciesExclude(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$before = $this->getPlanLines($eCompositionPlan);

		$speciesId = $this->getSpeciesServedEverywhere($before);

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		$lineAmended = NULL;

		foreach($before as $line) {
			if($lineAmended === NULL and $line['species'] === $speciesId) {
				$lineAmended = $line;
			}
		}

		$eCompositionWeekAmended = CompositionWeekLib::getByFormulaAndWeek(CompositionFormulaLib::getByPlan($eCompositionPlan)->find(fn(CompositionFormula $e) => $e['name'] === $lineAmended['formula'], limit: 1), $lineAmended['week']);

		CompositionWeekLib::amend($eCompositionWeekAmended);

		$linesAmended = array_filter($before, fn(array $line) => ($line['formula'] === $lineAmended['formula'] and $line['week'] === $lineAmended['week']));

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['status'], ['status' => CompositionSpecies::EXCLUDED]);
		$this->assertEquals([], array_column($fw->get(), 1), 'retrait admis');

		CompositionSpeciesLib::exclude($eCompositionSpecies);

		$this->assertEquals(CompositionSpecies::EXCLUDED, $this->getSpecies($eCompositionPlan, $speciesId)['status'], 'l\'espèce est retirée');

		$after = $this->getPlanLines($eCompositionPlan);

		$served = array_filter($after, fn(array $line) => $line['species'] === $speciesId);

		$this->assertEquals(array_keys($linesAmended), array_keys(array_filter($after, fn(array $line) => ($line['formula'] === $lineAmended['formula'] and $line['week'] === $lineAmended['week']))), 'la semaine corrigée garde ses lignes, les mêmes');
		$this->assertEquals([$lineAmended['formula'].'-'.$lineAmended['week']], array_values(array_unique(array_map(fn(array $line) => $line['formula'].'-'.$line['week'], $served))), 'l\'espèce ne reste que dans la semaine corrigée');

		$weeksServed = array_unique(array_map(fn(array $line) => $line['formula'].'-'.$line['week'], array_filter($before, fn(array $line) => $line['species'] === $speciesId)));
		$weeksOther = array_filter($before, fn(array $line) => in_array($line['formula'].'-'.$line['week'], $weeksServed) === FALSE);

		foreach($weeksOther as $lineId => $line) {
			$this->assertTrue(array_key_exists($lineId, $after), 'semaine '.$line['week'].' de '.$line['formula'].', qui ne la servait pas : sa ligne '.$lineId.' reste');
		}

	}

	/**
	 * Une fréquence choisie puis rendue (`doQuickFrequency`, lot r28) recompose toutes les tailles, et
	 * la réponse ne rend que ce que la taille ouverte a changé (`CompositionUi::getRecomposed()`) :
	 *
	 * - `CompositionSpeciesLib::update()` pose sur l'espèce les semaines changées de chaque taille,
	 *   avec les espèces dont la ligne a bougé, et une semaine qui n'y est pas garde ses lignes ;
	 * - la réponse rend l'écran d'un rechargement (`checkScreenAfter()`), sans rendre la grille ni
	 *   les cartes des semaines restées identiques.
	 */
	public function testSpeciesFrequency(): void {
		$this->runAsManager(fn(\structure\Structure $eStructure) => $this->checkSpeciesFrequency($eStructure));
	}

	private function checkSpeciesFrequency(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$speciesId = $this->getSpeciesServedEverywhere($this->getPlanLines($eCompositionPlan));

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		foreach([CompositionSpecies::BASE, NULL] as $frequency) {

			$label = 'fréquence '.($frequency ?? 'rendue');

			$eCompositionPlanBefore = CompositionPlanLib::getForScreen($eStructure, NULL);
			$formulaId = $eCompositionPlanBefore['eCompositionFormula']['id'];
			$weeksDelivered = $eCompositionPlanBefore['eCompositionFormula']['cCompositionWeek']->getColumn('week');

			$before = $this->getLinesByWeek($eCompositionPlan);
			$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlanBefore));

			$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

			$fw = new \FailWatch();
			$eCompositionSpecies->build(['frequency'], ['frequency' => $frequency]);
			$this->assertEquals([], array_column($fw->get(), 1), $label.' admise');

			CompositionSpeciesLib::update($eCompositionSpecies, ['frequency']);

			$this->assertEquals(CompositionFormulaLib::getByPlan($eCompositionPlan)->getIds(), array_keys($eCompositionSpecies['recomposed']), $label.' : chaque taille dit ce qu\'elle a changé');

			$after = $this->getLinesByWeek($eCompositionPlan);

			// Ce que le Lib annonce, confronté aux lignes relues : une semaine annoncée a changé, pour
			// les espèces qu'elle nomme et elles seules, et une semaine tue est restée la même.
			foreach($eCompositionSpecies['recomposed'] as $changedFormulaId => $changes) {

				$weeks = array_unique(array_merge(array_keys($before[$changedFormulaId] ?? []), array_keys($after[$changedFormulaId] ?? [])));

				foreach($weeks as $week) {

					$linesBefore = $before[$changedFormulaId][$week] ?? [];
					$linesAfter = $after[$changedFormulaId][$week] ?? [];

					$speciesIds = array_keys(array_diff_key($linesBefore, $linesAfter) + array_diff_key($linesAfter, $linesBefore));

					foreach(array_intersect_key($linesBefore, $linesAfter) as $linePlantId => $line) {
						if($line !== $linesAfter[$linePlantId]) {
							$speciesIds[] = $linePlantId;
						}
					}

					sort($speciesIds);

					if(array_key_exists($week, $changes)) {
						$this->assertEquals($speciesIds, $changes[$week], $label.' : taille '.$changedFormulaId.', semaine '.$week.', les espèces annoncées sont celles dont la ligne a bougé');
					} else {
						$this->assertEquals([], $speciesIds, $label.' : taille '.$changedFormulaId.', semaine '.$week.', tue parce qu\'identique');
					}

				}

			}

			$changes = $eCompositionSpecies['recomposed'][$formulaId];

			$this->assertTrue($changes !== [], $label.' : la taille ouverte change, sans quoi le contrôle ne prouverait rien');
			$this->assertTrue(count($changes) < count($weeksDelivered), $label.' : une semaine au moins reste la même ('.count($changes).' sur '.count($weeksDelivered).'), sans quoi le ciblage ne se verrait pas');

			// La réponse de la vue elle-même (`compositionSpecies.v.php`), et non celle de
			// `getRecomposed()` : la vue traduit ce qu'il prépare en instructions, et c'est elle qui
			// posait `data-served="null"` sur chaque case qui cesse d'être servie (P1).
			$response = $this->getViewChanges('compositionSpecies', 'doQuickFrequency', (object)[
				'eCompositionPlan' => CompositionPlanLib::getForScreen($eStructure, NULL),
				'e' => $eCompositionSpecies,
				'changes' => $changes,
			]);

			// Les rangs touchés se rendent entiers, en une instruction chacun, et non case par case (P9) :
			// 1 027 instructions pour une fréquence sur 999970 avant le lot.
			$this->assertEquals([], $response['attributes'], $label.' : aucun attribut posé case par case');
			$this->assertTrue(isset($response['inner']['#toolbox-composition-grid-species-'.$speciesId]), $label.' : le rang de l\'espèce se rend');

			$cards = array_filter(array_keys($response['outer']), fn(string $selector) => str_starts_with($selector, '#toolbox-composition-card-') and str_starts_with($selector, '#toolbox-composition-card-lean-') === FALSE);
			$this->assertEquals(count($changes), count($cards), $label.' : une carte par semaine changée, et pas une de plus');
			$this->assertFalse(isset($response['outer']['#toolbox-composition-proposal']), $label.' : les paniers ne se rendent pas entiers');

			$this->checkScreenAfter($screenBefore, $eStructure, $response, $label);

			// Une fréquence qui ne change aucune semaine de la taille ouverte change encore le picto du
			// rang : le rang de l'espèce se rend sans que ses semaines le nomment.
			$recomposed = $this->getRequestRender(fn() => new CompositionUi()->getRecomposed(CompositionPlanLib::getForScreen($eStructure, NULL), $speciesId, []));
			$this->assertTrue(isset($recomposed['inner']['#toolbox-composition-grid-species-'.$speciesId]), $label.' : le rang de l\'espèce se rend quand aucune semaine de la taille ouverte ne change');

		}

	}

	/**
	 * Une unité choisie depuis le panneau « Régler une espèce » ouvert sur la grille (`doUpdate`)
	 * recompose les paniers comme une fréquence, et la réponse ne rend que ce que la taille ouverte a
	 * changé (`CompositionUi::getRecomposed()`, chantier Performances, P9) : elle rendait les paniers
	 * entiers, 629 Ko sur 999970. La réponse rend l'écran d'un rechargement (`checkScreenAfter()`).
	 */
	public function testSpeciesUnitResponse(): void {

		$this->runAsManager(function(\structure\Structure $eStructure) {

			$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
			$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

			$speciesId = $this->getSpeciesServedEverywhere($this->getPlanLines($eCompositionPlan));

			if($speciesId === NULL) {
				$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
				return;
			}

			$eCompositionPlanBefore = CompositionPlanLib::getForScreen($eStructure, NULL);
			$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlanBefore));

			$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

			$this->assertTrue($eCompositionSpecies['unit']->empty(), 'l\'espèce part de l\'unité de la communauté, sans quoi la pièce ne changerait rien');

			$oneId = array_column(CompositionLib::getSource()['offered'], 'unit', 'fqn')['one'];

			// Le panneau construit l'unité seule, puis ce qu'elle laisse construire
			// (`compositionSpecies.p.php`, `doUpdate`).
			$fw = new \FailWatch();
			CompositionSpeciesLib::loadPriced($eCompositionSpecies);
			$eCompositionSpecies->build(['unit'], ['unit' => (string)$oneId]);
			$eCompositionSpecies->build(['weight'], ['weight' => '0.1']);
			$this->assertEquals([], array_column($fw->get(), 1), 'la pièce admise');

			CompositionSpeciesLib::update($eCompositionSpecies, ['unit', 'weight']);

			$eCompositionPlanAfter = CompositionPlanLib::getForScreen($eStructure, NULL);
			$changes = $eCompositionSpecies['recomposed'][$eCompositionPlanAfter['eCompositionFormula']['id']];

			$this->assertTrue($changes !== [], 'la taille ouverte change, sans quoi le contrôle ne prouverait rien');

			$response = $this->getViewChanges('compositionSpecies', 'doUpdate', (object)[
				'eStructure' => $eStructure,
				'eCompositionPlan' => $eCompositionPlanAfter,
				'e' => $eCompositionSpecies,
				'tab' => CompositionPlan::TAB_FORMULA,
				'seasoned' => FALSE,
				'weeks' => [],
				'recomposed' => TRUE,
				'priced' => FALSE,
				'weighed' => TRUE,
				'changes' => $changes,
			], ['js']);

			$this->assertFalse(isset($response['outer']['#toolbox-composition-proposal']), 'les paniers ne se rendent pas entiers');
			$this->assertTrue(isset($response['inner']['#toolbox-composition-grid-species-'.$speciesId]), 'le rang de l\'espèce se rend');
			$this->assertEquals([], $response['null'], 'aucun attribut posé à NULL');

			$this->checkScreenAfter($screenBefore, $eStructure, $response, 'unité choisie');

		});

	}

	/**
	 * Une saison raccourcie depuis le panneau « Régler une espèce » ouvert sur la grille (`doUpdate`,
	 * r61) rend ce que le même geste rend depuis la grille (`CompositionUi::getSeasoned()`) : le rang
	 * de l'espèce et les semaines qu'elle quitte, jamais les paniers entiers. La réponse rend l'écran
	 * d'un rechargement (`checkScreenAfter()`).
	 */
	public function testSpeciesSeasonPanelResponse(): void {

		$this->runAsManager(function(\structure\Structure $eStructure) {

			$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

			$speciesId = $this->getSpeciesServedEverywhere($this->getPlanLines($eCompositionPlan));

			if($speciesId === NULL) {
				$this->assertFail('aucune espèce servie : le contrôle ne verrait rien');
				return;
			}

			$eCompositionPlanBefore = CompositionPlanLib::getForScreen($eStructure, NULL);
			$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlanBefore));

			$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

			// La moitié de la saison, comme les poignées la postent, en chaînes.
			$weeks = $eCompositionSpecies->getSeasonWeeks();
			$windowWeeks = [[(string)min($weeks), (string)$weeks[intdiv(count($weeks), 2)]]];

			$fw = new \FailWatch();
			$eCompositionSpecies->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonUpdated($eCompositionSpecies, $windowWeeks));
			$this->assertEquals([], array_column($fw->get(), 1), 'la saison raccourcie admise');

			$weeksSeasoned = CompositionSpeciesLib::updatePanel($eCompositionSpecies, [], TRUE);

			$this->assertTrue($weeksSeasoned !== [], 'des semaines quittées se refont, sans quoi le contrôle ne prouverait rien');

			$response = $this->getViewChanges('compositionSpecies', 'doUpdate', (object)[
				'eStructure' => $eStructure,
				'eCompositionPlan' => CompositionPlanLib::getForScreen($eStructure, NULL),
				'e' => $eCompositionSpecies,
				'tab' => CompositionPlan::TAB_FORMULA,
				'seasoned' => TRUE,
				'weeks' => $weeksSeasoned,
				'recomposed' => FALSE,
				'priced' => FALSE,
				'weighed' => FALSE,
			], ['js']);

			$this->assertFalse(isset($response['outer']['#toolbox-composition-proposal']), 'les paniers ne se rendent pas entiers');
			$this->assertEquals([], $response['null'], 'aucun attribut posé à NULL');

			$this->checkScreenAfter($screenBefore, $eStructure, $response, 'saison raccourcie depuis le panneau');

		});

	}

	/**
	 * « Retirer de mes cultures » depuis le panneau ouvert sur la grille (`doExclude`) ne rend que ce
	 * que la taille ouverte a changé (`CompositionUi::getExcluded()`, chantier Performances, P9), dans
	 * les deux cas que la grille connaît :
	 *
	 * - l'espèce ne sert plus aucune semaine, et son rang se retire, la tête de son groupe suivant ;
	 * - une semaine corrigée la sert encore, et son rang reste, sa saison vidée.
	 *
	 * La réponse rend l'écran d'un rechargement (`checkScreenAfter()`).
	 */
	public function testSpeciesExcludeResponse(): void {

		$this->runAsManager(function(\structure\Structure $eStructure) {

			$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
			$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

			$lines = $this->getPlanLines($eCompositionPlan);
			$speciesIdRemoved = $this->getSpeciesServedEverywhere($lines);

			if($speciesIdRemoved === NULL) {
				$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
				return;
			}

			$this->checkSpeciesExcluded($eStructure, $eCompositionPlan, $speciesIdRemoved, TRUE);

			// Une espèce qui a son rang sans servir aucune semaine de la taille ouverte : son retrait
			// ne recompose rien dans cette taille, aucune autre espèce ne bouge, et la tête de son
			// groupe, qui la comptait, ne se rend que parce que le rang se retire.
			$speciesIdIdle = NULL;

			foreach(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['grid'] as $group) {
				foreach($group['species'] as $row) {
					if(
						$speciesIdIdle === NULL and
						count($group['species']) > 1 and
						$row['lines'] === [] and
						$row['eCompositionSpecies']->notEmpty()
					) {
						$speciesIdIdle = $row['species']['id'];
					}
				}
			}

			if($speciesIdIdle === NULL) {
				$this->assertFail('aucune espèce au rang vide dans un groupe qui en compte d\'autres : le contrôle ne verrait rien');
				return;
			}

			$this->checkSpeciesExcluded($eStructure, $eCompositionPlan, $speciesIdIdle, TRUE, idle: TRUE);

			$speciesIdKept = $this->getSpeciesServedEverywhere($this->getPlanLines($eCompositionPlan));

			if($speciesIdKept === NULL) {
				$this->assertFail('aucune seconde espèce servie dans les deux tailles : le contrôle ne verrait rien');
				return;
			}

			$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];

			$lineKept = NULL;

			foreach($this->getPlanLines($eCompositionPlan) as $line) {
				if($lineKept === NULL and $line['species'] === $speciesIdKept and $line['formula'] === $eCompositionFormula['name']) {
					$lineKept = $line;
				}
			}

			CompositionWeekLib::amend(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $lineKept['week']));

			$this->checkSpeciesExcluded($eStructure, $eCompositionPlan, $speciesIdKept, FALSE);

		});

	}

	private function checkSpeciesExcluded(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, int $speciesId, bool $removed, bool $idle = FALSE): void {

		if($idle) {
			$label = 'espèce retirée sans servir la taille ouverte';
		} else if($removed) {
			$label = 'espèce retirée, rang retiré';
		} else {
			$label = 'espèce retirée, rang gardé par une semaine corrigée';
		}

		$eCompositionPlanBefore = CompositionPlanLib::getForScreen($eStructure, NULL);
		$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal($eCompositionPlanBefore));

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['status'], ['status' => CompositionSpecies::EXCLUDED]);
		$this->assertEquals([], array_column($fw->get(), 1), $label.', retrait admis');

		CompositionSpeciesLib::exclude($eCompositionSpecies);

		$eCompositionPlanAfter = CompositionPlanLib::getForScreen($eStructure, NULL);

		$response = $this->getViewChanges('compositionSpecies', 'doExclude', (object)[
			'eStructure' => $eStructure,
			'eCompositionPlan' => $eCompositionPlanAfter,
			'e' => $eCompositionSpecies,
			'tab' => CompositionPlan::TAB_FORMULA,
			'changes' => $eCompositionSpecies['recomposed'][$eCompositionPlanAfter['eCompositionFormula']['id']],
		], ['js']);

		$this->assertFalse(isset($response['outer']['#toolbox-composition-proposal']), $label.', les paniers ne se rendent pas entiers');
		$this->assertEquals($removed ? ['#toolbox-composition-grid-species-'.$speciesId] : [], $response['remove'], $label.', ce qui se retire');

		if($removed === FALSE) {
			$this->assertTrue(isset($response['attributes']['#toolbox-composition-grid-species-'.$speciesId]['data-season']), $label.', la saison du rang se pose');
		}

		if($idle) {
			$this->assertEquals([], $eCompositionSpecies['recomposed'][$eCompositionPlanAfter['eCompositionFormula']['id']], $label.', la taille ouverte ne change pas, sans quoi une autre espèce rendrait la tête du groupe');
		}

		$this->checkScreenAfter($screenBefore, $eStructure, $response, $label);

	}

	/**
	 * `$check` joué par un administrateur d'une structure sans plan en 2026, connecté le temps du
	 * contrôle : c'est à lui que l'écran offre les gestes d'écriture.
	 */
	private function runAsManager(\Closure $check): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$check(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	/**
	 * Les lignes du plan par taille et par semaine, `[id de formule => [semaine => [id d'espèce =>
	 * ligne]]]` : une recomposition réécrit les lignes sous de nouveaux ids, seule l'espèce les
	 * rapproche d'une écriture à l'autre.
	 */
	private function getLinesByWeek(CompositionPlan $eCompositionPlan): array {

		$lines = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {

				$lines[$eCompositionFormula['id']][$eCompositionWeek['week']] = [];

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					$lines[$eCompositionFormula['id']][$eCompositionWeek['week']][$eCompositionLine['species']['id']] = [
						$eCompositionLine['unit']['id'],
						(float)$eCompositionLine['quantity'],
						($eCompositionLine['quantityProposed'] === NULL) ? NULL : (float)$eCompositionLine['quantityProposed'],
						($eCompositionLine['unitPrice'] === NULL) ? NULL : (float)$eCompositionLine['unitPrice'],
						$eCompositionLine['origin'],
					];
				}

			}
		}

		return $lines;

	}

	/**
	 * La saison de vente réglée sur la grille (lot r27, `CompositionSpeciesLib::updateSeason()`,
	 * `doUpdateSeason`), par un administrateur, comme la page la reçoit :
	 *
	 * - raccourcie par le début, elle passe à `CUSTOM`, et seules les semaines non corrigées qu'elle
	 *   quitte et qui servaient l'espèce se refont, dans chaque taille : l'espèce en sort, la semaine
	 *   corrigée garde ses lignes, et toute autre semaine garde les siennes ;
	 * - rallongée jusqu'à sa saison d'avant, elle ne refait rien : ses cases passent au vert, et les
	 *   autres espèces ne changent pas (r29, #87) ;
	 * - dans les deux cas, la réponse ciblée (`CompositionUi::getSeasoned()`) rend l'écran d'un
	 *   rechargement (`checkScreenAfter()`).
	 */
	public function testSpeciesSeason(): void {

		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSpeciesSeason(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSpeciesSeason(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$before = $this->getPlanLines($eCompositionPlan);

		$speciesId = $this->getSpeciesServedEverywhere($before);

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie dans les deux tailles : le contrôle ne verrait rien');
			return;
		}

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$seasonBefore = $eCompositionSpecies['windowWeeks'];
		$seasonWeeks = $eCompositionSpecies->getSeasonWeeks();

		// La coupe laisse derrière elle les deux premières semaines servies de la petite taille, dont
		// la première se corrige : l'une doit se refaire, l'autre non.
		$linesServed = array_filter($before, fn(array $line) => $line['species'] === $speciesId);
		$weeksServed = array_values(array_unique(array_column($linesServed, 'week')));
		sort($weeksServed);

		$this->assertTrue(count($weeksServed) > 2, 'l\'espèce est servie au moins trois semaines, sans quoi la coupe ne laisserait rien');

		$cut = $weeksServed[2];

		$lineAmended = array_find($linesServed, fn(array $line) => $line['week'] === $weeksServed[0]);

		$eCompositionWeekAmended = CompositionWeekLib::getByFormulaAndWeek(CompositionFormulaLib::getByPlan($eCompositionPlan)->find(fn(CompositionFormula $e) => $e['name'] === $lineAmended['formula'], limit: 1), $lineAmended['week']);
		CompositionWeekLib::amend($eCompositionWeekAmended);

		$before = $this->getPlanLines($eCompositionPlan);

		// Raccourcie : la saison part de `$cut`, postée comme le navigateur la poste, en chaînes.
		$seasonShort = CompositionLib::getBands(array_values(array_filter($seasonWeeks, fn(int $week) => $week >= $cut)));

		$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['status', 'windowWeeks'], [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => array_map(fn(array $interval) => array_map(strval(...), $interval), $seasonShort),
		]);
		$this->assertEquals([], array_column($fw->get(), 1), 'saison admise');

		$weeks = CompositionSpeciesLib::updateSeason($eCompositionSpecies);

		$eCompositionSpeciesWritten = $this->getSpecies($eCompositionPlan, $speciesId);
		$this->assertEquals([CompositionSpecies::CUSTOM, $seasonShort], [$eCompositionSpeciesWritten['status'], $eCompositionSpeciesWritten['windowWeeks']], 'la saison est écrite, réglée à la main');

		$weeksLeft = array_values(array_diff($seasonWeeks, $eCompositionSpeciesWritten->getSeasonWeeks()));

		// Une semaine servie par l'espèce, dans une taille, et que la coupe quitte sans qu'on l'ait
		// corrigée ; la correction se lit sur les lignes relues après elle.
		$weeksExpected = [];

		foreach($before as $line) {
			if($line['species'] === $speciesId and $line['amended'] === FALSE and in_array($line['week'], $weeksLeft, TRUE)) {
				$weeksExpected[] = $line['week'];
			}
		}

		$weeksExpected = array_values(array_unique($weeksExpected));
		sort($weeksExpected);

		$this->assertTrue($weeksExpected !== [], 'une semaine quittée à refaire, sans quoi le contrôle ne prouverait rien');
		$this->assertEquals($weeksExpected, $weeks, 'se refont les semaines non corrigées que la saison quitte et qui servaient l\'espèce, et elles seules');

		$after = $this->getPlanLines($eCompositionPlan);

		$servedLeft = array_filter($after, fn(array $line) => $line['species'] === $speciesId and in_array($line['week'], $weeksLeft, TRUE));
		$this->assertEquals([$lineAmended['formula'].'-'.$lineAmended['week']], array_values(array_unique(array_map(fn(array $line) => $line['formula'].'-'.$line['week'], $servedLeft))), 'hors de sa saison, l\'espèce ne reste que dans la semaine corrigée');

		$formulaWeeksServed = array_map(fn(array $line) => $line['formula'].'-'.$line['week'], $linesServed);

		foreach($before as $lineId => $line) {

			$recomposed = ($line['amended'] === FALSE and in_array($line['formula'].'-'.$line['week'], $formulaWeeksServed, TRUE) and in_array($line['week'], $weeks, TRUE));

			if($recomposed === FALSE) {
				$this->assertEquals($line, $after[$lineId] ?? NULL, 'semaine '.$line['week'].' de '.$line['formula'].', que le geste ne refait pas : sa ligne '.$lineId.' reste');
			}

		}

		$changes = $this->getRequestRender(fn() => new CompositionUi()->getSeasoned(CompositionPlanLib::getForScreen($eStructure, NULL), $speciesId, $weeks));
		// Sans les prix des paniers partagés, comme le rechargement de `checkScreenAfter()`.
		$this->checkScreenAfter($screenBefore, $eStructure, $changes, 'saison raccourcie');

		// Rallongée jusqu'à sa saison d'avant : rien ne se refait.
		$before = $this->getPlanLines($eCompositionPlan);
		$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['status', 'windowWeeks'], ['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => $seasonBefore]);
		$this->assertEquals([], array_column($fw->get(), 1), 'saison rallongée admise');

		$weeks = CompositionSpeciesLib::updateSeason($eCompositionSpecies);

		$this->assertEquals([], $weeks, 'rallongée, aucune semaine ne se refait');
		$this->assertEquals($seasonBefore, $this->getSpecies($eCompositionPlan, $speciesId)['windowWeeks'], 'la saison d\'avant est écrite');
		$this->assertEquals($before, $this->getPlanLines($eCompositionPlan), 'et aucune ligne ne bouge');

		$changes = $this->getRequestRender(fn() => new CompositionUi()->getSeasoned(CompositionPlanLib::getForScreen($eStructure, NULL), $speciesId, $weeks));
		$this->assertEquals([], $changes['outer'], 'rallongée, la réponse ne rend que le rang et la soudure');
		$this->checkScreenAfter($screenBefore, $eStructure, $changes, 'saison rallongée');

	}

	/**
	 * Un intervalle retiré de la saison sur la grille (r30, #93) : la poignée tirée au-delà de l'autre
	 * bout l'efface du tracé (`ToolboxCompositionSeason.compute()`), et le navigateur poste la saison
	 * sans lui. Joué par la route `doUpdateSeason`, comme le navigateur la poste :
	 *
	 * - une barre d'une semaine se retire, les deux autres intervalles restent ;
	 * - un intervalle sur deux se retire, l'autre reste ;
	 * - le dernier se retire : aucun `windowWeeks[…]` n'est posté, la saison s'écrit vide et réglée à
	 *   la main, l'espèce reste cultivée, n'est plus candidate d'aucune semaine et sort des paniers ;
	 * - elle garde pourtant son rang dans la grille (r30, #93b), toutes cases hors saison, et la
	 *   réponse ne rend que lui, identique au rechargement ;
	 * - un glisser sur ce rang y recrée un intervalle, et la réponse reste ciblée ;
	 * - une saison vide n'est admise que réglée à la main : celle de la communauté ou du plan de
	 *   culture en a toujours une.
	 */
	public function testSpeciesSeasonRemoved(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSpeciesSeasonRemoved(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSpeciesSeasonRemoved(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$speciesId = $this->getSpeciesServedEverywhere($this->getPlanLines($eCompositionPlan));

		if($speciesId === NULL) {
			$this->assertFail('aucune espèce servie : le contrôle ne verrait rien');
			return;
		}

		$formulaId = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['id'];

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		// Trois intervalles, dont une barre d'une semaine, écrits par le Lib de la grille : les
		// semaines que la saison quitte se refont, et l'espèce n'est plus servie qu'en saison.
		// Sans build() : le contrôle `short` (r72) refuse désormais une barre d'une semaine, et le décor
		// doit porter celle qu'une espèce enregistrée avant lui garde. Les intervalles sont déjà triés,
		// disjoints et dans la saison, seule leur longueur échappe au contrôle.
		$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpecies['windowWeeks'] = [[12, 20], [26, 26], [30, 36]];
		CompositionSpeciesLib::updateSeason($eCompositionSpecies);

		$post = fn(array $windowWeeks) => [
			'id' => $eCompositionSpecies['id'],
			'structure' => $eStructure['id'],
			'formula' => $formulaId,
		] + (($windowWeeks === []) ? [] : ['windowWeeks' => array_map(fn(array $interval) => array_map(strval(...), $interval), $windowWeeks)]);

		$season = fn() => $this->getSpecies($eCompositionPlan, $speciesId)->extracts(['status', 'windowWeeks']);

		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[12, 20], [26, 26], [30, 36]]], $season(), 'le décor porte la barre d\'une semaine');

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdateSeason', $post([[12, 20], [30, 36]]))::class, 'la barre d\'une semaine retirée, la saison s\'enregistre');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[12, 20], [30, 36]]], $season(), 'la barre d\'une semaine est retirée, les deux autres restent');

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdateSeason', $post([[30, 36]]))::class, 'un intervalle sur deux retiré, la saison s\'enregistre');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[30, 36]]], $season(), 'un intervalle sur deux est retiré, l\'autre reste');

		$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));

		$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdateSeason', $post([]));
		$this->assertEquals(\ViewAction::class, $action::class, 'le dernier intervalle retiré, sans aucun windowWeeks posté, la saison s\'enregistre');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => []], $season(), 'la saison s\'écrit vide, réglée à la main');

		$this->assertFalse(in_array($speciesId, CompositionSpeciesLib::getExcludedByPlan($eCompositionPlan), TRUE), 'l\'espèce reste cultivée');
		$this->assertEquals([], CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan)[$speciesId], 'elle n\'a plus aucune semaine');
		$this->assertEquals([], array_filter($this->getPlanLines($eCompositionPlan), fn(array $line) => $line['species'] === $speciesId), 'et sort de tous les paniers, aucune semaine n\'étant corrigée');

		// Ni servie ni candidate, l'espèce garde son rang (`CompositionLib::getGrid()`, r30, #93b), sans
		// aucune case de saison ni barre, et c'est sur lui qu'un glisser en recrée une : la réponse du
		// geste ne rend donc que ce rang (`CompositionUi::getSeasoned()`), celle que la route a
		// calculée, rejouée sur l'écran d'avant.
		$row = $this->getGridRow(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'], $speciesId);

		$this->assertEquals([], $row['lines'], 'aucune case servie');
		$this->assertEquals([], $row['weeksSeason'], 'aucune case de saison');
		$this->assertEquals([], $row['season'], 'aucune barre de saison');

		$this->checkSeasonRow($eStructure, $speciesId, '', [], 'saison vidée');
		$this->checkScreenAfter($screenBefore, $eStructure, $this->getRouteSeasoned($action, $speciesId), 'saison vidée');

		// Un glisser sur le rang vide recrée un intervalle (`ToolboxCompositionSeason`, mode `create`).
		$screenBefore = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));

		$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdateSeason', $post([[20, 24]]));
		$this->assertEquals(\ViewAction::class, $action::class, 'un intervalle recréé sur le rang vide, la saison s\'enregistre');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[20, 24]]], $season(), 'l\'intervalle recréé est écrit');
		$this->assertEquals(range(20, 24), array_values($this->getGridRow(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'], $speciesId)['weeksSeason']), 'ses cases repassent au vert');

		$this->checkSeasonRow($eStructure, $speciesId, '20-24', range(20, 24), 'intervalle recréé');
		$this->checkScreenAfter($screenBefore, $eStructure, $this->getRouteSeasoned($action, $speciesId), 'intervalle recréé');

		// Seule une saison réglée à la main peut être vide.
		foreach([CompositionSpecies::NATURAL, CompositionSpecies::CULTIVATION] as $status) {

			$eCompositionSpeciesSource = $this->getSpecies($eCompositionPlan, $speciesId);

			$fw = new \FailWatch();
			$eCompositionSpeciesSource->build(['status', 'windowWeeks'], ['status' => $status, 'windowWeeks' => []]);
			$this->assertEquals(['CompositionSpecies::windowWeeks.check'], array_column($fw->get(), 1), 'une saison vide refusée sous '.$status);

		}

	}

	/**
	 * Le rang de l'espèce `$speciesId` tel que l'écran rechargé le dessine : réglable (`data-species`),
	 * sa saison `$season` dans `data-season` (vide pour une saison vide, ce qui met un glisser en mode
	 * `create`), et vert exactement aux semaines `$weeksSeason`. La barre n'y est pas : le navigateur la
	 * dessine d'après `data-season` (`ToolboxCompositionSeason.draw()`, chantier Performances, P13).
	 */
	private function checkSeasonRow(\structure\Structure $eStructure, int $speciesId, string $season, array $weeksSeason, string $label): void {

		$screen = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$screen.'</body></html>', LIBXML_NOERROR);

		$node = $document->querySelector('#toolbox-composition-grid-species-'.$speciesId);

		if($node === NULL) {
			$this->assertFail($label.' : le rang manque à l\'écran');
			return;
		}

		$this->assertTrue($node->hasAttribute('data-species'), $label.' : le rang se règle');
		$this->assertEquals($season, $node->getAttribute('data-season'), $label.' : sa saison');

		$weeksGreen = array_map(fn(\Dom\Element $cell) => (int)$cell->getAttribute('data-week'), iterator_to_array($node->querySelectorAll('td.toolbox-composition-grid-season')));

		$this->assertEquals($weeksSeason, $weeksGreen, $label.' : ses cases vertes');

	}

	/**
	 * Ce que la vue de `doUpdateSeason` rend du plan et des semaines que la route `$action` a posés
	 * (`CompositionUi::getSeasoned()`), sans la tête de la réponse, qui ne touche pas la grille.
	 */
	private function getRouteSeasoned(\ViewAction $action, int $speciesId): array {

		$data = new \ReflectionProperty($action, 'data')->getValue($action);

		return $this->getRequestRender(fn() => new CompositionUi()->getSeasoned($data->eCompositionPlan, $speciesId, $data->weeks));

	}

	/**
	 * Un administrateur d'une structure qui n'a pas de plan pour `$season` et que la route des paniers
	 * accepte (`Structure::acceptComposition()`, une structure qui a la production) : la page la valide
	 * avant toute écriture, là où `getManagerWithoutPlan()` sert les tests qui appellent le Lib.
	 */
	private function getComposerWithoutPlan(int $season): \structure\Member {

		$cMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure AND m3.season = '.$season, 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->getCollection(0, 200);

		foreach($cMember as $eMember) {
			if(\structure\StructureLib::getById($eMember['structure']['id'])->acceptComposition()) {
				return $eMember;
			}
		}

		return new \structure\Member();

	}

	/**
	 * La route jouée par son adresse, comme `RightsTest::callRoute()` : l'action qu'elle lève,
	 * `ViewAction` pour une écriture réussie, `FailAction` pour un refus de saisie, NULL si elle n'en
	 * lève aucune. L'action rendue porte les données que sa vue reçoit (`getRouteSeasoned()`).
	 */
	private function callRoute(string $method, string $path, array $input = []): ?\Action {

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
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, new \stdClass());
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * La cascade du poids d'une pièce ou d'une botte (lot L3), sur une source et des cultures posées
	 * à la main : le poids tapé passe devant, puis la médiane des cultures dans l'unité où l'espèce
	 * entre au panier, jamais celle d'une autre unité, puis le poids courant de l'espèce dans cette
	 * unité, puis rien. Une espèce hors de la liste des poids courants, ou qui n'y est que dans une
	 * autre unité, reste sans poids. Une espèce au kilo n'a pas de poids, même tapé.
	 */
	public function testWeightReference(): void {

		$source = [
			'units' => [
				1 => ['fqn' => 'bunch'],
				2 => ['fqn' => 'kg'],
				3 => ['fqn' => 'one'],
				4 => ['fqn' => 'bunch'],
			],
			'weights' => [
				1 => ['bunch' => 0.45],
				2 => ['bunch' => 0.45],
				3 => ['bunch' => 0.3],
			],
		];

		$cultivated = [
			1 => ['bunch' => 0.5, 'one' => 0.2],
			3 => ['bunch' => 0.4],
		];

		$this->assertEquals(['weighed' => TRUE, 'weight' => 0.7, 'from' => CompositionLib::WEIGHT_TYPED], CompositionLib::getWeightReference(1, 0.7, $source, $cultivated), 'le poids tapé passe devant les cultures');
		$this->assertEquals(['weighed' => TRUE, 'weight' => 0.5, 'from' => CompositionLib::WEIGHT_CULTIVATED], CompositionLib::getWeightReference(1, NULL, $source, $cultivated), 'sans lui, celui des cultures à la botte, devant le poids courant');
		$this->assertEquals(['weighed' => TRUE, 'weight' => 0.45, 'from' => CompositionLib::WEIGHT_BASE], CompositionLib::getWeightReference(1, NULL, $source, []), 'sans cultures, le poids courant');
		$this->assertEquals(['weighed' => TRUE, 'weight' => NULL, 'from' => NULL], CompositionLib::getWeightReference(3, NULL, $source, $cultivated), 'une espèce à la pièce ne prend ni le poids de ses bottes, ni le poids courant de la botte');
		$this->assertEquals(['weighed' => TRUE, 'weight' => NULL, 'from' => NULL], CompositionLib::getWeightReference(4, NULL, $source, $cultivated), 'une espèce hors de la liste reste sans poids');
		$this->assertEquals(['weighed' => FALSE, 'weight' => NULL, 'from' => NULL], CompositionLib::getWeightReference(2, 0.7, $source, $cultivated), 'une espèce au kilo n\'a pas de poids');
		$this->assertEquals(['weighed' => FALSE, 'weight' => NULL, 'from' => NULL], CompositionLib::getWeightReference(2, NULL, $source, $cultivated), 'ni le poids courant d\'une unité qu\'elle ne sert pas');

	}

	/**
	 * La liste des poids courants (`ToolboxSetting::COMPOSITION_WEIGHTS`) ne nomme que des espèces du
	 * référentiel partagé et des unités qui ont un poids, et la source la relie aux espèces par leur
	 * `fqn` : une faute de frappe laisserait une espèce sans poids sans que rien ne le dise.
	 */
	public function testWeightBase(): void {

		$cPlant = \plant\Plant::model()
			->select(['id', 'fqn'])
			->whereStructure(NULL)
			->whereFqn('IN', array_keys(ToolboxSetting::COMPOSITION_WEIGHTS))
			->getCollection(index: 'fqn');

		$this->assertEquals([], array_values(array_diff(array_keys(ToolboxSetting::COMPOSITION_WEIGHTS), array_keys($cPlant->getArrayCopy()))), 'espèces inconnues du référentiel partagé');

		foreach(ToolboxSetting::COMPOSITION_WEIGHTS as $fqn => $weights) {
			$this->assertEquals([], array_values(array_diff(array_keys($weights), array_keys(CompositionLib::WEIGHED))), 'unités sans poids pour '.$fqn);
			$this->assertEquals([], array_filter($weights, fn(float $weight) => $weight <= 0.0), 'poids positifs pour '.$fqn);
		}

		$source = CompositionLib::getSource();

		$expected = [];

		foreach($cPlant as $fqn => $ePlant) {
			if(array_key_exists($ePlant['id'], $source['units'])) {
				$expected[$ePlant['id']] = ToolboxSetting::COMPOSITION_WEIGHTS[$fqn];
			}
		}

		ksort($expected);
		$weights = $source['weights'];
		ksort($weights);

		$this->assertTrue(count($expected) > 0, 'le contrôle voit des espèces de la liste dans la source');
		$this->assertEquals($expected, $weights, 'chaque espèce de la liste relue par son id, et aucune autre');

		$served = array_filter($expected, fn(array $weights, int $plantId) => array_key_exists($source['units'][$plantId]['fqn'], $weights), ARRAY_FILTER_USE_BOTH);

		$this->assertTrue(count($served) > 0, 'le contrôle voit des espèces servies dans une unité de la liste');

		foreach($served as $plantId => $weights) {
			$this->assertEquals(['weighed' => TRUE, 'weight' => $weights[$source['units'][$plantId]['fqn']], 'from' => CompositionLib::WEIGHT_BASE], CompositionLib::getWeightReference($plantId, NULL, $source, []), 'le poids courant sur la source réelle, espèce '.$plantId);
		}

	}

	/**
	 * Le poids des cultures d'une structure est la médiane, par espèce de référence et par unité, des
	 * poids posés sur ses cultures de la saison. Éprouvé sur la saison et la structure qui portent le
	 * plus de poids de bottes pour une même espèce, relus ici par une requête à part.
	 */
	public function testCultivatedWeights(): void {

		$eCultivation = \series\Cultivation::model()
			->select([
				'structure',
				'season',
				'plant' => ['reference'],
				'count' => new \Sql('COUNT(*)', 'int'),
			])
			->where('bunchWeight IS NOT NULL')
			->group(['structure', 'season', 'plant'])
			->sort(new \Sql('COUNT(*) DESC, m1.structure ASC, m1.season ASC, m1.plant ASC'))
			->get();

		if($eCultivation->empty() or $eCultivation['count'] < 3) {
			$this->assertFail('aucune culture ne porte trois poids de bottes : le contrôle ne verrait pas de médiane');
			return;
		}

		$ePlant = $eCultivation['plant'];
		$plantId = $ePlant['reference']->notEmpty() ? $ePlant['reference']['id'] : $ePlant['id'];

		$values = \series\Cultivation::model()
			->whereStructure($eCultivation['structure'])
			->whereSeason($eCultivation['season'])
			->wherePlant($ePlant)
			->where('bunchWeight IS NOT NULL')
			->getColumn('bunchWeight');

		$values = array_map(fn($value) => round((float)$value, 3), $values);
		sort($values);

		$middle = intdiv(count($values), 2);
		$expected = (count($values) % 2 === 1) ? $values[$middle] : round(($values[$middle - 1] + $values[$middle]) / 2, 3);

		$eStructure = \structure\StructureLib::getById($eCultivation['structure']['id']);

		$weights = CompositionLib::getCultivatedWeights($eStructure, $eCultivation['season']);

		// Une autre espèce de la structure peut partager la même référence : la médiane les compte
		// toutes, et le contrôle ne s'applique qu'à une espèce seule sous sa référence.
		$plants = \series\Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->where('m1.structure', $eCultivation['structure'])
			->where('m1.season', $eCultivation['season'])
			->where('m1.bunchWeight IS NOT NULL')
			->where(new \Sql('IFNULL(m2.reference, m2.id) = '.$plantId))
			->count();

		if($plants !== count($values)) {
			$this->assertFail('l\'espèce la plus pesée partage sa référence : le contrôle ne compare pas la même liste');
			return;
		}

		$this->assertEquals($expected, $weights[$plantId]['bunch'], 'la médiane des '.count($values).' poids de bottes de l\'espèce '.$plantId);

	}

	/**
	 * Les poids des cultures tirés des cultures des saisons S-1 et S que l'écran lit une fois
	 * (`CompositionLib::getCultivations()`, chantier Performances, P8) sont ceux que la lecture des
	 * seules cultures pesées de la saison rend (`getCultivatedWeights()` sans cultures, que le menu
	 * d'une case emprunte). Éprouvé sur la structure qui porte le plus de cultures pesées sur deux
	 * saisons de suite, pour que les poids de la saison précédente, que la lecture de l'écran ramène
	 * aussi, aient de quoi se glisser dans ceux de la saison.
	 */
	public function testCultivatedWeightsFromCultivations(): void {

		// Une citation du même module part d'un modèle neuf : `Cultivation::model()` est un singleton.
		$mCultivationPrevious = new \series\CultivationModel()
			->where('s1.structure', new \Sql('m1.structure'))
			->where('s1.season', new \Sql('m1.season - 1'))
			->where('s1.unitWeight IS NOT NULL OR s1.bunchWeight IS NOT NULL');

		$eCultivation = \series\Cultivation::model()
			->select([
				'structure',
				'season',
				'count' => new \Sql('COUNT(*)', 'int'),
			])
			->where('m1.unitWeight IS NOT NULL OR m1.bunchWeight IS NOT NULL')
			->where($mCultivationPrevious->existsSql())
			->group(['structure', 'season'])
			->sort(new \Sql('COUNT(*) DESC, m1.structure ASC, m1.season ASC'))
			->get();

		if($eCultivation->empty()) {
			$this->assertFail('aucune structure ne pèse ses cultures deux saisons de suite : le contrôle ne verrait pas la saison précédente');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eCultivation['structure']['id']);
		$season = $eCultivation['season'];

		$weights = CompositionLib::getCultivatedWeights($eStructure, $season);

		$this->assertDiffer([], $weights, 'la saison porte des poids');
		$this->assertDiffer($weights, CompositionLib::getCultivatedWeights($eStructure, $season - 1), 'la saison précédente porte d\'autres poids');

		$this->assertEquals($weights, CompositionLib::getCultivatedWeights($eStructure, $season, CompositionLib::getCultivations($eStructure, $season)), 'les poids tirés des cultures des deux saisons sont ceux de la saison');

	}

	/**
	 * Le poids d'une espèce réglé depuis son panneau (lot L3) : refusé négatif ou sur une espèce au kilo,
	 * vidé à `NULL`. Il ne touche à aucune ligne du plan, se dit dans le panneau de l'espèce et en kilos
	 * sous sa quantité de l'année, et passe au plan suivant quand on recommence.
	 */
	public function testSpeciesWeight(): void {

		// Les gestes du menu ne se rendent que pour celui qui gère la structure (`assertUpdate`).
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSpeciesWeight(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSpeciesWeight(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$before = $this->getPlanLines($eCompositionPlan);

		$served = array_values(array_unique(array_column($before, 'species')));
		$weighed = array_values(array_filter($served, fn(int $speciesId) => CompositionLib::isWeighed($speciesId, $source)));
		$unweighed = array_values(array_filter($served, fn(int $speciesId) => CompositionLib::isWeighed($speciesId, $source) === FALSE));

		if($weighed === [] or $unweighed === []) {
			$this->assertFail('le plan ne sert pas à la fois une espèce à la pièce ou à la botte et une espèce au kilo : le contrôle ne verrait rien');
			return;
		}

		$speciesId = $weighed[0];

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['weight'], ['weight' => '-1'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::weight.check'], array_column($fw->get(), 1), 'un poids négatif refusé');

		$eCompositionSpeciesUnweighed = $this->getSpecies($eCompositionPlan, $unweighed[0]);

		$fw = new \FailWatch();
		$eCompositionSpeciesUnweighed->build(['weight'], ['weight' => '0.35'], new \PropertyList('update'));
		$this->assertEquals(['CompositionSpecies::weight.weighed'], array_column($fw->get(), 1), 'une espèce au kilo n\'a pas de poids');

		$fw = new \FailWatch();
		$eCompositionSpeciesUnweighed->build(['weight'], ['weight' => ''], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'mais peut toujours rester sans poids');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['weight'], ['weight' => '0.35'], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'poids admis');

		CompositionSpeciesLib::update($eCompositionSpecies, ['weight']);

		$this->assertEquals(0.35, $this->getSpecies($eCompositionPlan, $speciesId)['weight'], 'le poids est écrit');
		$this->assertEquals($before, $this->getPlanLines($eCompositionPlan), 'aucune ligne ne bouge');

		// Le rang de l'espèce, depuis la grille.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);

		$eCompositionSpeciesScreen = $this->getGridRow($eCompositionPlanScreen['eCompositionFormula'], $speciesId)['eCompositionSpecies'];
		$this->assertEquals(['weighed' => TRUE, 'weight' => 0.35, 'from' => CompositionLib::WEIGHT_TYPED], $eCompositionSpeciesScreen['weightReference'], 'l\'écran lit le poids tapé');

		$blocks = new CompositionUi()->getWeighed($eCompositionPlanScreen, $speciesId, FALSE);

		$this->assertEquals([], array_keys($blocks['outer']), 'depuis la grille, rien d\'autre que le rang : le décompte des espèces non couvertes est parti avec la comparaison (lot r27)');
		$this->assertEquals(['#toolbox-composition-grid-species-'.$speciesId, $this->getGroupHead($eCompositionPlanScreen['eCompositionFormula'], $speciesId), '#toolbox-composition-grid-sum-total'], array_keys($blocks['inner']), 'le rang de l\'espèce, et les kilos de son groupe et de la taille');

		$row = $blocks['inner']['#toolbox-composition-grid-species-'.$speciesId];

		// Le panneau « Régler une espèce » (lot r22e) : le champ du poids de l'unité où l'espèce entre,
		// seul actif, porte le poids tapé ; plus de tuile qui le répète (r29, #86).
		$panel = $this->getSpeciesPanel($eCompositionSpeciesScreen);

		$this->assertEquals(1, $this->countWeightInputs($panel), 'le panneau offre de changer le poids');
		$this->assertEquals(1, preg_match('/<input (?![^>]*disabled)[^>]*name="weight"[^>]*value="0.35"[^>]*>/', $panel), 'dans le champ actif, qui porte le poids tapé');

		$menus = 0;

		foreach($eCompositionPlanScreen['eCompositionFormula']['grid'] as $group) {
			foreach($group['species'] as $gridRow) {
				if(in_array($gridRow['species']['id'], $unweighed, TRUE)) {
					$menus++;
					$panel = $this->getSpeciesPanel($gridRow['eCompositionSpecies']);
					$this->assertTrue($this->countWeightInputs($panel) === 0, 'une espèce au kilo n\'a pas de poids dans son panneau, '.$gridRow['species']['id']);
				}
			}
		}

		$this->assertTrue($menus > 0, 'la grille porte des espèces au kilo');

		// Les kilos de l'année, sous la quantité de la synthèse.
		$blocks = new CompositionUi()->getWeighed($eCompositionPlanScreen, $speciesId, TRUE);

		$this->assertEquals([], $blocks['inner'], 'depuis la synthèse, rien de la grille');

		$eUnit = $eCompositionSpeciesScreen['eUnit'];
		$selector = '#toolbox-composition-quantities-'.$speciesId.'-'.$eUnit['id'];

		$this->assertTrue(array_key_exists($selector, $blocks['outer']), 'le rang de l\'espèce dans son unité');

		$total = $this->getQuantitiesRows($eCompositionPlanScreen)[$speciesId.'-'.$eUnit['id']]['total'];

		$this->assertEquals(1, preg_match('/≈ '.preg_quote(\util\TextUi::number(round($total * 0.35), 0), '/').'\p{Zs}kg/u', $blocks['outer'][$selector]), 'les kilos de l\'année, '.$total.' × 0,35');

		$synthesis = new CompositionUi()->getQuantities($eCompositionPlanScreen);

		$this->assertEquals(1, substr_count($synthesis, 'id="'.substr($selector, 1).'"'), 'le rang que la réponse remplace est dans la synthèse, une fois');

		// Recommencer garde le poids tapé.
		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, 2026);
		$eCompositionPlanStart['weeks'] = range(10, 40);
		$eCompositionPlanStart['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::restart($eCompositionPlanStart);

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		$speciesId = $this->getSpeciesRestarted($eCompositionPlan, $eCompositionSpeciesScreen)['id'];

		$this->assertEquals(0.35, $this->getSpecies($eCompositionPlan, $speciesId)['weight'], 'recommencer garde le poids');

		// Vidé, le poids retombe sur la cascade : celui des cultures, à défaut le poids courant, ou
		// rien, et la synthèse offre alors de l'indiquer.
		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();
		$eCompositionSpecies->build(['weight'], ['weight' => ''], new \PropertyList('update'));
		$this->assertEquals([], array_column($fw->get(), 1), 'le poids vidé admis');

		CompositionSpeciesLib::update($eCompositionSpecies, ['weight']);

		$this->assertEquals(NULL, $this->getSpecies($eCompositionPlan, $speciesId)['weight'], 'vidé, le poids est nul');

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$reference = $this->getGridRow($eCompositionPlanScreen['eCompositionFormula'], $speciesId)['eCompositionSpecies']['weightReference'];

		// Les poids des cultures se lisent par plante, la source du plan par espèce du plan.
		$sourceRestarted = CompositionLib::getSourceByPlan($eCompositionPlan);
		$unitFqn = $sourceRestarted['units'][$speciesId]['fqn'];
		$expected = CompositionLib::getCultivatedWeights($eStructure, 2026)[$sourceRestarted['plants'][$speciesId]][$unitFqn] ?? $sourceRestarted['weights'][$speciesId][$unitFqn] ?? NULL;

		$this->assertEquals($expected, $reference['weight'], 'la cascade reprend le poids des cultures, à défaut le poids courant, s\'il y en a un');

		$outer = new CompositionUi()->getWeighed($eCompositionPlanScreen, $speciesId, TRUE)['outer']['#toolbox-composition-quantities-'.$speciesId.'-'.$eUnit['id']];

		if($expected === NULL) {
			$this->assertEquals(1, preg_match('/<a [^>]*post-origin="synthesis"[^>]*>/', $outer), 'sans poids, la synthèse offre de l\'indiquer, et dit d\'où part le geste');
		} else {
			$this->assertTrue(str_contains($outer, '≈ '), 'avec un poids, la synthèse dit les kilos');
		}

	}

	/**
	 * Le retour à la proposition (lot r8b, déplacé au lot r24) : la carte d'une semaine corrigée le
	 * porte en bas, pour qui gère la structure seulement, et celle d'une semaine qui ne l'est pas
	 * non. Il était en tête de la colonne ouverte, partie au lot r24 (retour du 27/09,
	 * « il n'y a plus de modification en colonne »). « Ne pas livrer » reste retiré (retour de
	 * du 27/09, « enlève ne pas livrer, les gens passeront par la conf en haut ») : les
	 * semaines livrées se règlent dans la carte des semaines. Un geste qui corrige la semaine rend sa
	 * carte, qui gagne alors son retour à la proposition.
	 *
	 * Le prix d'une espèce se règle au bout de son nom (lot r21, « prix modifiable en quick »), et sa
	 * fréquence depuis son picto : ni l'un ni l'autre ne porte la semaine ou l'espèce qu'une quantité
	 * a posées avant lui.
	 */
	public function testCardReset(): void {

		// Le retour à la proposition et les prix réglables ne se rendent que pour celui qui gère la
		// structure (`assertUpdate`).
		$eMember = $this->getManagerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		try {
			$this->checkCardReset($eStructure, $eMember['user']);
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkCardReset(\structure\Structure $eStructure, \user\User $eUserManager): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$this->addFormula($eCompositionPlan, 'Le grand panier', 30.0);

		$card = function(int $week) use($eStructure): string {
			$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
			return new CompositionUi()->getReset($eCompositionPlanScreen, $week, [])['outer']['#toolbox-composition-card-'.$week];
		};

		$html = new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL));

		$this->assertTrue(str_contains($html, 'compositionWeek:doReset') === FALSE, 'sans retour à la proposition, aucune taille n\'y porte de correction');
		$this->assertTrue(str_contains($html, 'composition:doWeek') === FALSE, 'et aucune semaine ne se retire de la grille');

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->find(fn(CompositionFormula $e) => $e['name'] === 'Le panier', limit: 1);

		CompositionWeekLib::amend(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 21));

		$this->assertEquals(1, preg_match('/<a data-ajax="\/toolbox\/compositionWeek:doReset" [^>]*post-week="21"[^>]*>/', $card(21)), 'la carte de la semaine 21, corrigée, revient à la proposition');
		$this->assertTrue(str_contains($card(22), 'compositionWeek:doReset') === FALSE, 'celle de la semaine 22 non');

		// Une case allumée, éteinte ou réglée corrige sa semaine : la réponse rend sa carte, qui gagne
		// alors son retour à la proposition. Une espèce servie de chaque semaine : une espèce absente
		// de la grille la ferait rendre entière.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);

		$speciesIdServed = fn(CompositionPlan $eCompositionPlan, int $week) => $eCompositionPlan['eCompositionFormula']['cCompositionWeek']->find(fn(CompositionWeek $e) => $e['week'] === $week, limit: 1)['cCompositionLine']->first()['species']['id'];

		$outer = new CompositionUi()->getToggled($eCompositionPlanScreen, 21, $speciesIdServed($eCompositionPlanScreen, 21))['outer'];

		$this->assertTrue(str_contains($outer['#toolbox-composition-card-21'], 'compositionWeek:doReset'), 'le geste rend la carte de sa semaine, qui revient à la proposition');

		$outer = new CompositionUi()->getToggled($eCompositionPlanScreen, 22, $speciesIdServed($eCompositionPlanScreen, 22))['outer'];

		$this->assertTrue(str_contains($outer['#toolbox-composition-card-22'], 'compositionWeek:doReset') === FALSE, 'et n\'y revient pas sur une semaine qui n\'est pas corrigée');

		// Le prix d'une espèce se règle au bout de son nom (lot r21), et sa fréquence depuis le menu de
		// son picto (lot r34c). Aucun des deux ne porte la semaine ou l'espèce qu'une quantité a posées
		// avant lui (`Data::setQuickAttribute()`, commun à tous les éléments) : la grille entière et un
		// rang rendu seul écrivent alors le même quick. Les cartes sous la grille rendent leurs quantités
		// après les quicks des espèces.
		$html = new CompositionUi()->getProposal($eCompositionPlanScreen);

		$this->assertTrue(preg_match_all('/<div class="util-annotation toolbox-composition-grid-price hide-sm-down"><div class="util-quick" ([^>]*)>/', $html, $quicks) > 0, 'le contrôle voit les prix des espèces');
		$this->assertEquals([], array_values(array_filter($quicks[1], fn(string $attributes) => preg_match('/post-(season|week|plant)=/', $attributes) === 1)), 'aucun prix ne poste de semaine');

		$this->assertTrue(preg_match_all('/<a data-ajax="[^"]*" ([^>]*post-property="frequency"[^>]*)>/', $html, $frequencies) > 0, 'le contrôle voit les menus des fréquences');
		$this->assertEquals([], array_values(array_filter($frequencies[1], fn(string $attributes) => preg_match('/post-(season|week|plant)=/', $attributes) === 1)), 'aucun choix de fréquence ne poste de semaine');

		$posts = 'post-structure="'.$eCompositionPlanScreen['structure']['id'].'" post-formula="'.$eCompositionPlanScreen['eCompositionFormula']['id'].'"';
		$this->assertEquals([], array_values(array_filter(array_merge($quicks[1], $frequencies[1]), fn(string $attributes) => str_contains($attributes, $posts) === FALSE)), 'tous postent la structure et la taille lue');

		// Qui ne fait que lire ne défait rien depuis la carte, et lit le prix sans le régler.
		\user\ConnectionLib::setOnline(new \user\User());

		try {

			$html = new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL));

			$this->assertTrue(str_contains($card(21), 'compositionWeek:doReset') === FALSE, 'en lecture, la carte corrigée ne revient pas à la proposition');
			$this->assertTrue(str_contains($html, 'toolbox-composition-grid-price hide-sm-down"><div class="util-quick"') === FALSE, 'ni ne règle un prix');

		} finally {
			\user\ConnectionLib::setOnline($eUserManager);
		}

	}

	/**
	 * Le PDF de l'année (L4) : une entrée par semaine livrée, dans l'ordre de l'année, et dans chacune
	 * les espèces servies ce jour-là, relues à l'identique par une requête écrite à la main. Chaque
	 * espèce porte une quantité par taille du plan, NULL quand la taille ne la sert pas cette semaine.
	 * Les espèces d'une semaine se rangent comme les lignes de l'année en quantités, et le compte des
	 * espèces est celui des espèces servies au moins une fois.
	 */
	public function testPdfBaskets(): void {

		$eStructure = $this->getStructureWithoutPlan();

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionFormulaLarge = $this->addFormula($eCompositionPlanWritten, 'Le grand panier', 28.0);

		$eCompositionPlan = CompositionPlanLib::getForPdf($eStructure);

		$this->assertEquals(range(10, 40), array_keys($eCompositionPlan['baskets']), 'une entrée par semaine livrée, dans l\'ordre');

		$rows = CompositionLine::model()->pdo()->select(
			'SELECT w.week week, l.species species, l.unit unit, f.id formula, l.quantity quantity'
			.' FROM '.CompositionLine::model()->getDatabase().'.toolboxCompositionLine l'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionWeek w ON w.id = l.week'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionFormula f ON f.id = w.formula'
			.' WHERE f.plan = '.$eCompositionPlan['id']
		)->fetchAll(\PDO::FETCH_ASSOC);

		$formulaIds = $eCompositionPlan['cCompositionFormula']->getIds();

		$this->assertEquals([$eCompositionPlan['cCompositionFormula']->first()['id'], $eCompositionFormulaLarge['id']], $formulaIds, 'les deux tailles, dans leur ordre');

		$expected = [];
		$speciesIds = [];

		foreach($rows as $row) {
			$expected[(int)$row['week']][$row['species'].'-'.$row['unit']] ??= array_fill_keys($formulaIds, NULL);
			$expected[(int)$row['week']][$row['species'].'-'.$row['unit']][(int)$row['formula']] = round((float)$row['quantity'], 2);
			$speciesIds[$row['species']] = TRUE;
		}

		$this->assertTrue(count($rows) > 0, 'le contrôle voit des lignes');

		$order = array_keys($this->getQuantitiesRowsOrdered($eStructure));
		$read = [];
		$missing = 0;

		foreach($eCompositionPlan['baskets'] as $week => $weekRows) {

			$keys = array_map(fn(array $row) => $row['species']['id'].'-'.$row['unit']['id'], $weekRows);

			$this->assertEquals(array_values(array_intersect($order, $keys)), $keys, 'rangées comme l\'année en quantités : semaine '.$week);

			foreach($weekRows as $row) {
				$this->assertEquals($formulaIds, array_keys($row['formulas']), 'toutes les tailles du plan : '.$row['species']->getLabel());
				$read[$week][$row['species']['id'].'-'.$row['unit']['id']] = array_map(fn(?float $quantity) => $quantity === NULL ? NULL : round($quantity, 2), $row['formulas']);
				$missing += count(array_filter($row['formulas'], fn(?float $quantity) => $quantity === NULL));
			}

		}

		ksort($expected);
		array_walk($expected, fn(array &$weekRows) => ksort($weekRows));
		array_walk($read, fn(array &$weekRows) => ksort($weekRows));

		$this->assertEquals($expected, $read, 'les paniers relus par une requête à la main');
		$this->assertEquals(count($speciesIds), $eCompositionPlan['species'], 'les espèces servies au moins une fois');

		// Rien ne garantit qu'une taille laisse une espèce que l'autre sert : on en retire une pour
		// que la case vide soit éprouvée à coup sûr. L'espèce est servie par les deux tailles deux
		// semaines de suite, pour la suite du test : une espèce faible ou de diversification ne l'est
		// jamais (`CompositionLib::FREQUENCY_GAPS`), la première servie de l'année peut en être une.
		[$week, $speciesId] = $this->getServedTwice($expected, $formulaIds);

		$eCompositionSpecies = new CompositionSpecies(['id' => $speciesId]);

		CompositionLineLib::delete(CompositionLineLib::getByWeekAndSpecies(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormulaLarge, $week), $eCompositionSpecies));

		$eCompositionPlan = CompositionPlanLib::getForPdf($eStructure);

		$rowPdf = array_values(array_filter($eCompositionPlan['baskets'][$week], fn(array $row) => $row['species']['id'] === $speciesId))[0];

		$this->assertEquals(NULL, $rowPdf['formulas'][$eCompositionFormulaLarge['id']], 'la taille qui ne sert pas l\'espèce laisse sa case vide');
		$this->assertTrue($rowPdf['formulas'][$formulaIds[0]] > 0, 'l\'autre garde sa quantité');

		// Une espèce servie dans deux unités selon les semaines reste une espèce : la semaine suivante
		// la sert dans l'unité d'une autre espèce, et le compte ne bouge pas.
		$species = $eCompositionPlan['species'];
		$weekNext = $week + 1;

		$eCompositionWeekNext = CompositionWeekLib::getByFormulaAndWeek($eCompositionPlan['cCompositionFormula']->first(), $weekNext);
		$eCompositionLine = CompositionLineLib::getByWeekAndSpecies($eCompositionWeekNext, $eCompositionSpecies);

		$this->assertTrue($eCompositionLine->notEmpty(), 'l\'espèce est servie la semaine suivante');

		$unitIds = array_unique(array_map(fn(string $key) => (int)explode('-', $key)[1], array_merge(...array_map(fn(array $weekRows) => array_keys($weekRows), $expected))));
		$unitOther = array_values(array_diff($unitIds, [$eCompositionLine['unit']['id']]))[0];

		CompositionLineLib::delete($eCompositionLine);
		CompositionLineLib::create(new CompositionLine([
			'week' => $eCompositionWeekNext,
			'species' => $eCompositionSpecies,
			'unit' => new \product\Unit(['id' => $unitOther]),
			'quantity' => 1.0,
			'quantityProposed' => 1.0,
			'unitPrice' => 1.0,
			'origin' => CompositionLine::PROPOSED,
		]));

		$eCompositionPlan = CompositionPlanLib::getForPdf($eStructure);

		$this->assertTrue(in_array($speciesId.'-'.$unitOther, array_map(fn(array $row) => $row['species']['id'].'-'.$row['unit']['id'], $eCompositionPlan['baskets'][$weekNext])), 'servie dans l\'autre unité la semaine suivante');
		$this->assertEquals($species, $eCompositionPlan['species'], 'une espèce dans deux unités compte une fois');

		$this->assertTrue(CompositionPlanLib::getForPdf($this->getStructureWithoutPlan())->empty(), 'rien sans plan');

	}

	/**
	 * La première semaine dont une espèce, servie par toutes les tailles, l'est encore la semaine
	 * suivante par la première, lue dans les paniers relus à la main (`testPdfBaskets()`).
	 */
	private function getServedTwice(array $expected, array $formulaIds): array {

		foreach($expected as $week => $weekRows) {

			foreach($weekRows as $key => $quantities) {

				[$speciesId] = explode('-', $key);

				$servedNext = array_filter($expected[$week + 1] ?? [], fn(array $quantitiesNext, string $keyNext) => explode('-', $keyNext)[0] === $speciesId and $quantitiesNext[$formulaIds[0]] !== NULL, ARRAY_FILTER_USE_BOTH);

				if(in_array(NULL, $quantities, TRUE) === FALSE and $servedNext !== []) {
					return [$week, (int)$speciesId];
				}

			}

		}

		throw new \Exception('Aucune espèce servie deux semaines de suite');

	}

	/**
	 * Le document : une carte par semaine, datée de ses jours ; une colonne de quantité par taille
	 * titrée de son prix quand il y en a plusieurs, aucune sinon ; les prix dans les chiffres ; le nom
	 * de fichier que le bouton de l'écran annonce, et ce bouton, à côté de « Les paniers ».
	 */
	public function testPdfRender(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$this->createStarted($eStructure, 2026, range(10, 40));

		$ui = new CompositionPdfUi();

		$eCompositionPlan = CompositionPlanLib::getForPdf($eStructure);

		$xpath = $this->getPdfXpath($ui->getWeeks($eCompositionPlan));

		$this->assertEquals(31, $xpath->query('//div[@class="toolbox-pdf-week"]')->length, 'une carte par semaine');
		$this->assertEquals(0, $xpath->query('//div[contains(@class, "toolbox-pdf-weeks-sizes")]')->length, 'une taille : pas de colonnes de prix');
		$this->assertEquals(0, $xpath->query('//div[contains(@class, "toolbox-pdf-week-sizes")]')->length, 'ni d\'en-tête de prix');

		$figures = $ui->getFigures($eCompositionPlan);

		$this->assertTrue(str_contains($figures, "18\u{a0}€"), 'le prix du panier : '.strip_tags($figures));
		$this->assertTrue(str_contains($figures, '>Le panier<'), 'titré du nom de la taille');
		$this->assertTrue(str_contains($figures, '>'.$eCompositionPlan['species'].'<'), 'les espèces');

		$this->addFormula(CompositionPlanLib::getByStructure($eStructure), 'Le grand panier', 28.0);

		$eCompositionPlan = CompositionPlanLib::getForPdf($eStructure);

		$xpath = $this->getPdfXpath($ui->getWeeks($eCompositionPlan));

		$headings = array_map(fn(\DOMNode $node) => $node->textContent, iterator_to_array($xpath->query('//div[@class="toolbox-pdf-week"]/h4')));

		$this->assertEquals(31, count($headings), 'toujours une carte par semaine');
		$this->assertEquals('Du 2 au 8 mars', $headings[0], 'la semaine 10 de 2026');
		$this->assertEquals('Du 30 mars au 5 avril', $headings[4], 'une semaine à cheval sur deux mois');
		$this->assertEquals(1, $xpath->query('//div[@class="toolbox-pdf-weeks toolbox-pdf-weeks-sizes"]')->length, 'deux tailles : les colonnes de prix');

		foreach($xpath->query('//div[contains(@class, "toolbox-pdf-week-sizes")]') as $sizes) {
			$this->assertEquals(['', "18\u{a0}€", "28\u{a0}€"], array_map(fn(\DOMNode $node) => $node->textContent, iterator_to_array($xpath->query('./span', $sizes))), 'une colonne par taille, titrée de son prix');
			$this->assertEquals('toolbox-pdf-week-plant', $xpath->query('./span', $sizes)->item(0)->getAttribute('class'), 'les prix se calent au-dessus des quantités, derrière la place du nom');
		}

		$this->assertEquals(31, $xpath->query('//div[contains(@class, "toolbox-pdf-week-sizes")]')->length, 'un en-tête par carte');

		foreach($xpath->query('//div[@class="toolbox-pdf-week-row"]') as $row) {
			$this->assertEquals(3, $xpath->query('./span', $row)->length, 'l\'espèce et une quantité par taille');
		}

		$figures = $ui->getFigures($eCompositionPlan);

		$this->assertTrue(str_contains($figures, "18\u{a0}et\u{a0}28\u{a0}€"), 'les prix des tailles : '.strip_tags($figures));
		$this->assertTrue(str_contains($figures, '>2 paniers<'), 'titrés de leur nombre');

		$this->assertEquals('Paniers 2026.pdf', CompositionPdfUi::getFilename(2026), 'le nom du fichier');

		$html = new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL));

		$this->assertEquals(1, preg_match('/<a href="\/toolbox\/composition:downloadPdf\?id='.$eStructure['id'].'" data-ajax-download="Paniers 2026\.pdf" data-waiter=/', $html), 'le PDF, sur le lien de téléchargement et son attente');

	}

	/**
	 * La récolte que le plan de culture prévoit (lot L5, `CompositionLib::getPlanned()`), sur les
	 * cultures réelles d'une structure, face à un calcul fait par la base : le nombre de cultures et
	 * de cultures sans rendement de chaque espèce, et, pour les espèces dont toutes les cultures sont
	 * comptées au kilo et chiffrées, la somme du rendement par la surface, au prorata des semaines de
	 * récolte qui tombent dans la saison. Les cultures de la saison précédente qui récoltent dans
	 * celle-ci comptent au même prorata, les autres pas du tout. Une espèce propre à la structure se
	 * range sous son espèce du référentiel partagé.
	 */
	public function testPlanned(): void {

		$season = 2026;

		[$compared, $crossing] = $this->checkPlanned($this->getStructureCultivated($season), $season);

		$this->assertTrue($compared >= 5, 'au moins cinq espèces comparées en kilos, '.$compared);
		$this->assertTrue($crossing > 0, 'des cultures dont la récolte déborde d\'une saison sur l\'autre');

		[$compared, , $previous] = $this->checkPlanned($this->getStructureHarvestingPrevious($season), $season);

		$this->assertTrue($previous > 0, 'des cultures de la saison précédente qui récoltent dans celle-ci, '.$previous);
		$this->assertTrue($compared > 0, 'et des espèces comparées en kilos, '.$compared);

	}

	/**
	 * La récolte de `getPlanned()` sur une structure, face au calcul de la base. Rend le nombre
	 * d'espèces comparées en kilos, de cultures dont la récolte déborde de la saison et de cultures de
	 * la saison précédente comptées.
	 */
	private function checkPlanned(\structure\Structure $eStructure, int $season): array {

		$source = CompositionLib::getSource();
		$source['storage'] = CompositionLib::getStorage($eStructure, array_keys($source['units']));

		$planned = CompositionLib::getPlanned($season, $source, new \Collection(), CompositionLib::getCultivations($eStructure, $season));

		$cCultivation = \series\Cultivation::model()
			->select([
				'plant' => ['reference'],
				'season', 'mainUnit', 'harvestWeeksExpected',
				'complete' => new \Sql('m1.yieldExpected IS NOT NULL AND COALESCE(m2.area, m2.areaTarget) IS NOT NULL', 'bool'),
				'harvest' => new \Sql('m1.yieldExpected * COALESCE(m2.area, m2.areaTarget)', 'float'),
			])
			->join(\series\Series::model(), 'm1.series = m2.id')
			->where('m1.structure', $eStructure)
			->where('m1.season', 'IN', [$season - 1, $season])
			->getCollection();

		$expected = [];
		$simple = [];
		$crossing = 0;
		$previous = 0;

		foreach($cCultivation as $eCultivation) {

			$ePlant = $eCultivation['plant'];
			$plantId = $ePlant['reference']->notEmpty() ? $ePlant['reference']['id'] : $ePlant['id'];

			if(array_key_exists($plantId, $source['units']) === FALSE) {
				continue;
			}

			$weeks = $eCultivation['harvestWeeksExpected'] ?? [];
			$weeksInSeason = count(array_filter($weeks, fn(string $week) => str_starts_with($week, $season.'-W')));

			// Une récolte de l'année d'avant que la conservation porte jusqu'à la semaine 1 (lot r9) :
			// la semaine 52 d'une espèce qui se conserve une semaine, par exemple.
			$storage = $source['storage'][$plantId]['weeks'];
			$weeksCarried = count(array_filter($weeks, fn(string $week) => str_starts_with($week, ($season - 1).'-W') and min((int)substr($week, 6), 52) + $storage >= 53));

			if($eCultivation['season'] < $season) {

				if($weeksInSeason === 0 and $weeksCarried === 0) {
					continue;
				}

				if($weeksInSeason > 0) {
					$previous++;
				}

			}

			$expected[$plantId] ??= ['cultivations' => 0, 'incomplete' => 0, 'harvest' => 0.0];
			$expected[$plantId]['cultivations']++;

			if($eCultivation['complete'] === FALSE) {
				$expected[$plantId]['incomplete']++;
			}

			$simple[$plantId] = (
				($simple[$plantId] ?? TRUE) and
				$eCultivation['mainUnit'] === \series\Cultivation::KG and
				$eCultivation['complete']
			);

			if($weeks === []) {
				continue;
			}

			if($weeksInSeason < count($weeks)) {
				$crossing++;
			}

			$expected[$plantId]['harvest'] += (float)$eCultivation['harvest'] * $weeksInSeason / count($weeks);

		}

		$this->assertEquals(array_keys($expected), array_keys($planned), 'une entrée par espèce servie au panier que le plan de culture porte');

		$compared = 0;

		foreach($expected as $plantId => $values) {

			$this->assertEquals([$values['cultivations'], $values['incomplete']], [$planned[$plantId]['cultivations'], $planned[$plantId]['incomplete']], 'les cultures de l\'espèce '.$plantId.' et celles sans rendement');

			if($simple[$plantId]) {
				$this->assertTrue(abs(array_sum($planned[$plantId]['harvest']) - $values['harvest']) < 0.001, 'la récolte de l\'espèce '.$plantId.' : '.array_sum($planned[$plantId]['harvest']).' pour '.$values['harvest']);
				$compared++;
			}

		}

		return [$compared, $crossing, $previous];

	}

	private function getPdfXpath(string $html): \DOMXPath {

		// libxml ne connaît pas les balises SVG des vignettes, et le dit en avertissements.
		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return new \DOMXPath($document);

	}

	/**
	 * Les lignes de l'année en quantités dans leur ordre d'affichage, `['plante-unité' => TRUE]`.
	 */
	private function getQuantitiesRowsOrdered(\structure\Structure $eStructure): array {

		$rows = [];

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL)['quantities']['groups'] as $group) {
			foreach($group['rows'] as $row) {
				if($row['unit']->notEmpty()) {
					$rows[$row['species']['id'].'-'.$row['unit']['id']] = TRUE;
				}
			}
		}

		return $rows;

	}

	/**
	 * Les lignes du plan, toutes tailles, `[id => ['formula', 'week', 'species', 'quantity', 'unitPrice', 'amended']]`.
	 */
	private function getPlanLines(CompositionPlan $eCompositionPlan): array {

		$lines = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					$lines[$eCompositionLine['id']] = [
						'formula' => $eCompositionFormula['name'],
						'week' => $eCompositionWeek['week'],
						'species' => $eCompositionLine['species']['id'],
						'quantity' => (float)$eCompositionLine['quantity'],
						'unitPrice' => ($eCompositionLine['unitPrice'] === NULL) ? NULL : (float)$eCompositionLine['unitPrice'],
						'amended' => $eCompositionWeek['amended'],
					];
				}
			}
		}

		ksort($lines);

		return $lines;

	}

	/**
	 * Une espèce servie dans chaque taille, en plus d'une semaine : `NULL` s'il n'y en a pas.
	 */
	private function getSpeciesServedEverywhere(array $lines): ?int {

		$formulasBySpecies = [];
		$countBySpecies = [];

		foreach($lines as $line) {
			$formulasBySpecies[$line['species']][$line['formula']] = TRUE;
			$countBySpecies[$line['species']] = ($countBySpecies[$line['species']] ?? 0) + 1;
		}

		$formulas = count(array_unique(array_column($lines, 'formula')));

		foreach($formulasBySpecies as $speciesId => $served) {
			if(count($served) === $formulas and $countBySpecies[$speciesId] > $formulas) {
				return $speciesId;
			}
		}

		return NULL;

	}

	/**
	 * La valeur de chaque semaine, `[taille => [semaine => valeur]]`.
	 */
	private function getWeekValues(\structure\Structure $eStructure): array {

		$values = [];

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL)['cCompositionFormula'] as $eCompositionFormula) {
			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
				$values[$eCompositionFormula['name']][$eCompositionWeek['week']] = $eCompositionWeek['value'];
			}
		}

		return $values;

	}

	private function getSpeciesPanel(CompositionSpecies $eCompositionSpecies): string {

		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);

		$panel = new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, NULL);

		return $panel->body.$panel->footer;

	}

	/**
	 * Les champs du poids qui postent : ceux des autres unités sont désactivés.
	 */
	private function countWeightInputs(string $html): int {

		preg_match_all('/<input [^>]*name="weight"[^>]*>/', $html, $inputs);

		return count(array_filter($inputs[0], fn(string $input) => str_contains($input, 'disabled') === FALSE));

	}

	/**
	 * L'espèce du plan `$eCompositionPlan` qui reprend `$eCompositionSpecies`, celle d'un plan que
	 * « Recommencer les paniers » a remplacé (`CompositionPlanLib::restart()`) : la même plante et le
	 * même nom, une variante (r76) restant distincte de son espèce nue.
	 */
	private function getSpeciesRestarted(CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies): CompositionSpecies {

		return CompositionSpecies::model()
			->select(['id'])
			->wherePlan($eCompositionPlan)
			->wherePlant($eCompositionSpecies['plant'])
			->whereName($eCompositionSpecies['name'])
			->get();

	}

	private function getSpecies(CompositionPlan $eCompositionPlan, int $speciesId): CompositionSpecies {

		$eCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
			])
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->get();

		$eCompositionSpecies['plan']['structure'] = $eCompositionPlan['structure'];

		return $eCompositionSpecies;

	}


	/**
	 * Un administrateur d'une structure qui n'a pas de plan pour `$season`, avec son adhésion en
	 * cours : c'est à lui que l'écran offre les gestes d'écriture.
	 */
	private function getManagerWithoutPlan(int $season): \structure\Member {

		return \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure AND m3.season = '.$season, 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

	}

	/**
	 * Chaque ligne de chaque taille, `[id => quantité]` : ce qu'un réglage qui ne recompose rien
	 * doit laisser intact.
	 */
	private function getLines(CompositionPlan $eCompositionPlan): array {

		$lines = [];

		foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {
			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					$lines[$eCompositionLine['id']] = (float)$eCompositionLine['quantity'];
				}
			}
		}

		ksort($lines);

		return $lines;

	}

	/**
	 * Les espèces servies de l'année en quantités, `['plante-unité' => ['formulas' => […], 'total' => float]]`.
	 */
	private function getQuantitiesRows(CompositionPlan $eCompositionPlan): array {

		$rows = [];

		foreach($eCompositionPlan['quantities']['groups'] as $group) {
			foreach($group['rows'] as $row) {
				if($row['unit']->notEmpty()) {
					$rows[$row['species']['id'].'-'.$row['unit']['id']] = ['formulas' => $row['formulas'], 'total' => $row['total']];
				}
			}
		}

		ksort($rows);

		return $rows;

	}

	private function buildWeeks(CompositionPlan $eCompositionPlan, ?array $weeks): array {

		$fw = new \FailWatch();

		$eCompositionPlan->build(['weeks'], ['weeks' => $weeks]);

		return array_column($fw->get(), 1);

	}

	private function buildFormula(CompositionFormula $eCompositionFormula, array $input): array {

		$fw = new \FailWatch();

		$eCompositionFormula->build(['name', 'price', 'baskets'], $input, new \PropertyList('create'));

		return array_column($fw->get(), 1);

	}

	/**
	 * Le plan que le départ écrit sur `$weeks`, avec une taille « Le panier » à 18 € et 10 paniers
	 * par semaine, et les espèces que le départ coche : relu en base, avec sa structure.
	 */
	/**
	 * Ce que coûte l'écran des paniers (`CompositionPlanLib::getForScreen()`), en requêtes, sur un plan
	 * de départ qui suit la communauté puis sur le même plan synchronisé, dont la soudure lit le stock
	 * (chantier Performances, P2) :
	 *
	 * - la cascade des prix de la structure (`CompositionLib::getPrices()`) ne tourne qu'une fois, dans
	 *   `getSourceByPlan()`, et non trois : `CompositionSpeciesLib::getWithPrices()` la reprend de la
	 *   source, et le stock reçoit les espèces chiffrées de l'écran. Sa lecture du prévisionnel, que
	 *   rien d'autre ne lit, se compte donc une fois (la cascade dans l'unité de la communauté, pour
	 *   une espèce changée d'unité, reprend les mêmes sources : `CompositionUnitTest::testPrices()`) ;
	 * - les espèces du plan se lisent une fois (P7) : les fenêtres, les unités, les prix tapés, les pas,
	 *   le compte et les espèces cultivées en dérivent ;
	 * - les cultures de la structure se lisent une fois (P8) : les poids des cultures, les espèces hors
	 *   panier, le stock et les semaines de récolte de la soudure en dérivent ;
	 * - le nombre total de requêtes est celui que déclare `SCREEN_QUERIES`, et il ne peut que
	 *   descendre : un lot qui en retire en baisse la déclaration, un lot qui en ajoute se voit ici.
	 */
	public function testScreenQueries(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$this->checkScreenQueries($eStructure, CompositionPlan::COMMUNITY);

		// `reference` ne se poste jamais (`CompositionPlanLib`) et seule la synchronisation le fait
		// passer à `CULTIVATION` ; elle demande un plan de culture qui change une saison, que le décor
		// n'a pas. Seule la lecture de l'écran est mesurée ici : le Lib écrit la valeur telle quelle.
		$eCompositionPlan['reference'] = CompositionPlan::CULTIVATION;
		CompositionPlanLib::update($eCompositionPlan, ['reference']);

		$this->checkScreenQueries($eStructure, CompositionPlan::CULTIVATION);

	}

	private function checkScreenQueries(\structure\Structure $eStructure, string $reference): void {

		// Une passe à vide : la source de la communauté se garde d'une lecture à l'autre
		// (`CompositionLib::getSource()`), et c'est l'écran d'une requête de geste qu'on compte.
		CompositionPlanLib::getForScreen($eStructure, NULL);

		$queries = $this->getQueries(fn() => CompositionPlanLib::getForScreen($eStructure, NULL));

		$this->assertEquals($reference, CompositionPlanLib::getByStructure($eStructure)['reference'], $reference.' : le plan mesuré');

		$forecast = array_filter($queries, fn(string $sql) => str_contains($sql, '`plantForecast`'));
		$this->assertEquals(1, count($forecast), $reference.' : la cascade des prix tourne une fois par écran, '.count($forecast).' fois ici');

		$species = array_filter($queries, fn(string $sql) => str_contains($sql, '`toolboxCompositionSpecies`'));
		$this->assertEquals(1, count($species), $reference.' : les espèces du plan se lisent une fois par écran, '.count($species).' fois ici');

		$cultivations = array_filter($queries, fn(string $sql) => str_contains($sql, '`seriesCultivation`'));
		$this->assertEquals(1, count($cultivations), $reference.' : les cultures de la structure se lisent une fois par écran, '.count($cultivations).' fois ici');

		$this->assertEquals(self::SCREEN_QUERIES[$reference], count($queries), $reference.' : l\'écran fait '.count($queries).' requêtes pour '.self::SCREEN_QUERIES[$reference].' déclarées ; SCREEN_QUERIES se baisse quand un lot en retire, c\'est la déclaration qui se corrige, et une hausse est à expliquer');

	}

	/**
	 * La source de la communauté (`CompositionLib::getSource()`) se lit en `SOURCE_QUERIES` requêtes à
	 * chaque requête HTTP des paniers quand l'agrégé est déjà en cache : la version de l'agrégé en une
	 * (`aggregate\CompositionSourceAiLib::getVersion()`), puis les unités et les espèces du
	 * référentiel, qui se relisent à chaque fois (chantier Performances, P5). La lecture gardée pour
	 * l'exécution est effacée avant, comme au début d'une requête.
	 *
	 * Ce qu'elle rend se vérifie sur ce que la lecture commune pourrait perdre : les unités offertes,
	 * du référentiel partagé et rangées par `fqn`, et le poids courant d'une espèce qui a une unité.
	 */
	public function testSourceQueries(): void {

		$source = new \ReflectionProperty(CompositionLib::class, 'source');

		$source->setValue(NULL, NULL);
		CompositionLib::getSource();

		$source->setValue(NULL, NULL);
		$queries = $this->countQueries(fn() => CompositionLib::getSource());

		$this->assertEquals(self::SOURCE_QUERIES, $queries, 'la source se lit en '.$queries.' requêtes pour '.self::SOURCE_QUERIES.' déclarées ; SOURCE_QUERIES se baisse quand un lot en retire, c\'est la déclaration qui se corrige, et une hausse est à expliquer');

		$offered = CompositionLib::getSource()['offered'];

		$this->assertEquals(['bunch', 'kg', 'one'], array_values(array_column($offered, 'fqn')), 'les unités offertes sont la botte, le kilo et la pièce, rangées par fqn');
		$cUnitOffered = \product\Unit::model()
			->select(['id', 'structure'])
			->whereId('IN', array_keys($offered))
			->getCollection();

		$this->assertEquals(count($offered), $cUnitOffered->find(fn(\product\Unit $eUnit) => $eUnit['structure']->empty())->count(), 'toutes du référentiel partagé');

		$weights = CompositionLib::getSource()['weights'];
		$units = CompositionLib::getSource()['units'];

		$this->assertTrue($weights !== [], 'des espèces portent un poids courant');
		$this->assertEquals([], array_diff_key($weights, $units), 'le poids courant ne se lit que sur une espèce qui a une unité');

	}

	/**
	 * L'onglet « Synthèse » se lit sans taille ouverte (`CompositionPlanLib::getForScreen()`,
	 * `$synthesis`, chantier Performances, P11) : ce qu'il rend doit être ce que rendrait la lecture
	 * entière, en-tête, semaines livrées et paniers, pour chaque taille que la page peut lui passer (« Ajouter
	 * une taille » depuis la synthèse poste celle qu'on lisait). Une taille appuyée sur une autre fait
	 * passer la lecture entière par sa base, que la synthèse saute. La lecture de la synthèse fait
	 * moins de requêtes que l'entière.
	 */
	public function testSynthesisReadsLess(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkSynthesisReadsLess(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkSynthesisReadsLess(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$eCompositionFormulaLarge = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Grand panier', 'price' => 30.0, 'baskets' => 10]);
		CompositionFormulaLib::add($eCompositionFormulaLarge);

		$eCompositionFormulaLarge = CompositionFormulaLib::getById($eCompositionFormulaLarge['id'], CompositionFormula::getSelection() + [
			'plan' => CompositionPlan::getSelection(),
		]);
		$eCompositionFormulaLarge['plan']['structure'] = $eStructure;

		$fw = new \FailWatch();
		$eCompositionFormulaLarge->build(['base'], ['base' => $eCompositionFormulaSmall['id']], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), 'la grande taille s\'appuie sur la petite');
		CompositionFormulaLib::update($eCompositionFormulaLarge, ['base']);

		$cFavorite = FavoriteLib::getByStructure($eStructure);

		// Les identifiants de champ sont tirés au hasard à chaque rendu (`FormUi`), et le trait du jour
		// de la frise se place à la seconde près : deux rendus à une seconde d'écart diffèrent.
		$render = fn(CompositionPlan $eCompositionPlanScreen) => preg_replace(['/field-[0-9a-f]{13}/', '/(class="series-grid-now") style="[^"]*"/'], ['field', '$1'], $this->getRequestRender(fn() => (
			new CompositionUi()->getHeader($eStructure, $cFavorite).
			new CompositionUi()->getWeeks($eCompositionPlanScreen).
			new CompositionUi()->getProposal($eCompositionPlanScreen, CompositionPlan::TAB_SYNTHESIS)
		)));

		$bases = 0;

		foreach([NULL, $eCompositionFormulaSmall['id'], $eCompositionFormulaLarge['id']] as $formulaId) {

			$eCompositionPlanFull = CompositionPlanLib::getForScreen($eStructure, $formulaId);

			if($eCompositionPlanFull['eCompositionFormula']['linesAdded'] !== NULL) {
				$bases++;
			}

			$this->assertEquals($render($eCompositionPlanFull), $render(CompositionPlanLib::getForScreen($eStructure, $formulaId, tab: CompositionPlan::TAB_SYNTHESIS)), 'taille '.($formulaId ?? 'par défaut').' : la synthèse lue sans taille ouverte rend ce que rend la lecture entière');

		}

		$this->assertEquals(1, $bases, 'la comparaison a rencontré une taille dont les semaines suivent leur base');

		$queriesFull = $this->countQueries(fn() => CompositionPlanLib::getForScreen($eStructure, NULL));
		$queriesSynthesis = $this->countQueries(fn() => CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SYNTHESIS));

		$this->assertTrue($queriesSynthesis < $queriesFull, 'la synthèse fait '.$queriesSynthesis.' requêtes, la lecture entière '.$queriesFull);

		// Les deux pages qui rendent la synthèse la lisent ainsi, et « Ajouter une taille » depuis un
		// onglet de taille lit toujours la taille ouverte.
		$opened = function(?\Action $action): bool {
			$this->assertTrue($action instanceof \ViewAction, 'la route rend sa vue');
			return new \ReflectionProperty($action, 'data')->getValue($action)->eCompositionPlan['eCompositionFormula']->offsetExists('lean');
		};

		$this->assertFalse($opened($this->callRoute('GET', 'ferme/'.$eStructure['id'].'/outils/paniers/synthese')), 'l\'onglet « Synthèse » se lit sans taille ouverte');

		$create = fn(string $origin, string $name) => $this->callRoute('POST', 'toolbox/compositionFormula:doCreate', [
			'structure' => $eStructure['id'],
			'formula' => $eCompositionFormulaLarge['id'],
			'origin' => $origin,
			'name' => $name,
			'price' => '40',
			'baskets' => '5',
		]);

		$this->assertFalse($opened($create('synthesis', 'Panier de la synthèse')), 'une taille ajoutée depuis la synthèse aussi');
		$this->assertTrue($opened($create('', 'Panier de l\'onglet')), 'une taille ajoutée depuis un onglet de taille lit la taille ouverte');

	}

	/**
	 * Un onglet ouvert sans recharger la page (`composition:tab`, chantier Performances, P14) rend,
	 * pour chaque onglet, ce que la page entière rend à la même adresse : les semaines livrées
	 * et les paniers, ou le tableau des espèces. L'en-tête de l'outil, que la réponse ne touche pas,
	 * doit être le même quel que soit l'onglet, sinon il garderait la taille qu'on quitte : il ne
	 * porte plus rien d'une taille depuis que « Modifier les paniers » a quitté sa roue (r31). Rien
	 * d'autre n'est à l'écran au-dessus des paniers depuis que les semaines livrées sont le seul bloc
	 * des questions (r51a). La réponse pousse l'adresse de la page dans l'historique et en fait le
	 * contexte qu'un rechargement relit.
	 */
	public function testTabRendersPage(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkTabRendersPage(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	/**
	 * Le retour arrière après un onglet rouvre l'onglet quitté : l'adresse que pousse la réponse
	 * (`pushHistory`) doit passer par `Lime.History.push()`, qui marque l'historique comme parcouru.
	 * Par `pushState()` seul, le premier retour vers la page chargée est pris pour le popstate initial
	 * que certains navigateurs émettent au chargement, et ignoré : l'adresse revient, l'onglet affiché
	 * reste (vu dans Chrome le 30/09 sur 999970, reproduit sur le code du cadriciel par
	 * `scratchpad/perf/p14b/history.js`). Le contrat vit dans le cadriciel, qu'aucun test ne lit
	 * côté navigateur : il se vérifie ici sur le texte des deux fonctions.
	 */
	public function testTabBackNavigation(): void {

		$ajax = file_get_contents(__DIR__.'/../../../framework/util/static/js/ajax.js');
		$lime = file_get_contents(__DIR__.'/../../../framework/util/static/js/lime.js');

		$this->assertEquals(1, preg_match("/case 'pushHistory' :\n(.*?)return;/s", $ajax, $instruction), 'l\'instruction pushHistory est aiguillée par ajax.js');
		$this->assertEquals(1, preg_match('/^\s*Lime\.History\.push\(data\[0\]\);$/m', $instruction[1]), 'l\'instruction passe par Lime.History.push(), et non pushState() seul : '.trim($instruction[1]));

		$this->assertEquals(1, preg_match('/static push\(url\) \{(.*?)\};/s', $lime, $push), 'Lime.History.push() existe');
		$this->assertTrue(str_contains($push[1], 'this.pop()'), 'Lime.History.push() marque l\'historique comme parcouru : '.trim($push[1]));

	}

	private function checkTabRendersPage(\structure\Structure $eStructure): void {

		require_once __DIR__.'/../view/composition.v.php';

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$eCompositionFormulaLarge = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Grand panier', 'price' => 30.0, 'baskets' => 10]);
		CompositionFormulaLib::add($eCompositionFormulaLarge);

		$data = fn(?\Action $action) => new \ReflectionProperty($action, 'data')->getValue($action);

		// Les identifiants de champ sont tirés au hasard à chaque rendu (`FormUi`), et le trait du jour
		// de la frise se place à la seconde près : deux rendus à une seconde d'écart diffèrent.
		$clean = fn(string $html) => preg_replace(['/field-[0-9a-f]{13}/', '/(class="series-grid-now") style="[^"]*"/'], ['field', '$1'], $html);

		$tabs = [
			'petite taille' => ['ferme/'.$eStructure['id'].'/outils/paniers', ['formula' => $eCompositionFormulaSmall['id']], ['formula' => $eCompositionFormulaSmall['id']]],
			'grande taille' => ['ferme/'.$eStructure['id'].'/outils/paniers', ['formula' => $eCompositionFormulaLarge['id']], ['formula' => $eCompositionFormulaLarge['id']]],
			'synthèse' => ['ferme/'.$eStructure['id'].'/outils/paniers/synthese', [], ['tab' => CompositionPlan::TAB_SYNTHESIS]],
			'espèces' => ['ferme/'.$eStructure['id'].'/outils/paniers/especes', [], ['tab' => CompositionPlan::TAB_SPECIES]],
		];

		$headers = [];

		foreach($tabs as $label => [$path, $inputPage, $inputTab]) {

			$dataPage = $data($this->callRoute('GET', $path, $inputPage));
			$actionTab = $this->callRoute('GET', 'toolbox/composition:tab', ['id' => $eStructure['id']] + $inputTab);

			$this->assertTrue($actionTab instanceof \ViewAction, $label.' : la route rend sa vue');

			$dataTab = $data($actionTab);

			// La synthèse se lit sans taille ouverte (P11), comme sur sa page.
			$this->assertEquals($dataPage->eCompositionPlan['eCompositionFormula']->offsetExists('lean'), $dataTab->eCompositionPlan['eCompositionFormula']->offsetExists('lean'), $label.' : la taille ouverte se lit comme sur la page');

			$uiComposition = new CompositionUi();

			$expected = $this->getRequestRender(fn() => [
				'#toolbox-composition-weeks' => $clean($uiComposition->getWeeks($dataPage->eCompositionPlan, $dataPage->tab)),
				'#toolbox-composition-proposal' => $clean($uiComposition->getProposal($dataPage->eCompositionPlan, $dataPage->tab)),
			]);

			$headers[$label] = $clean($this->getRequestRender(fn() => $uiComposition->getHeader($eStructure, $dataPage->cFavorite)));

			$output = $this->getRequestRender(function() use($dataTab) {
				ob_start();
				\View::get('tab', new \ViewAction())->render($dataTab, new \AjaxTemplate());
				return ob_get_clean();
			});

			$outer = [];
			$js = [];
			$attributes = [];

			foreach(json_decode($output, TRUE)['__instructions'] as $entry) {

				if($entry[0] === 'js') {
					$js = array_merge($js, $entry[1]);
					continue;
				}

				[, $selector, $instructions] = $entry;

				foreach($instructions as [$instruction, $arguments]) {
					match($instruction) {
						'outerHtml' => $outer[$selector] = $clean($arguments[0]),
						'setAttribute' => $attributes[$selector][$arguments[0]] = $arguments[1],
					};
				}

			}

			$this->assertEquals(array_keys($expected), array_keys($outer), $label.' : la réponse rend les semaines livrées et les paniers');

			foreach($expected as $selector => $html) {
				$this->assertEquals($html, $outer[$selector] ?? NULL, $label.' : '.$selector.' rendu comme la page entière');
			}

			$url = match($dataPage->tab) {
				CompositionPlan::TAB_FORMULA => CompositionUi::urlFormula($eStructure, $dataPage->eCompositionPlan['eCompositionFormula']),
				CompositionPlan::TAB_SYNTHESIS => CompositionUi::urlSynthesis($eStructure),
				CompositionPlan::TAB_SPECIES => CompositionUi::urlSpecies($eStructure),
			};

			$this->assertEquals($label === 'espèces', str_contains($outer['#toolbox-composition-proposal'], 'id="toolbox-composition-species-table"'), $label.' : le tableau des espèces ne se rend que dans son onglet');

			$this->assertEquals([['pushHistory', [$url]]], $js, $label.' : l\'adresse de la page entre dans l\'historique');
			$this->assertEquals(['body' => ['data-context' => $url]], $attributes, $label.' : et devient le contexte de la page');

		}

		$this->assertEquals(1, count(array_unique($headers)), 'l\'en-tête de l\'outil est le même pour chaque onglet');

	}

	/**
	 * L'ouverture du menu d'une case (`compositionLine:getMenu`) ne lit que ce que la case affiche
	 * (`CompositionPlanLib::getForCellMenu()`, chantier Performances, P4), et rend pourtant le menu
	 * que l'écran entier donnerait : sur chaque semaine livrée, le menu de chaque rang de la grille,
	 * et celui d'une espèce que la grille n'a pas, sont comparés à ceux que rend `getForScreen()` lu
	 * pour la même semaine, celui que chaque geste rend encore.
	 *
	 * La route est jouée par son adresse, avec un administrateur connecté : sans lui, tous les menus
	 * seraient vides (`assertUpdate()`) et la comparaison ne verrait rien. Elle doit avoir rencontré
	 * une case servie, une case à ajouter et une case sans menu.
	 *
	 * Le nombre de requêtes de l'ouverture est déclaré (`MENU_QUERIES`), comme celui de l'écran.
	 */
	public function testCellMenuReadsLess(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkCellMenuReadsLess(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkCellMenuReadsLess(\structure\Structure $eStructure): void {

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$formulaId = $eCompositionPlanScreen['eCompositionFormula']['id'];

		$speciesIds = array_column(array_merge(...array_column($eCompositionPlanScreen['eCompositionFormula']['grid'], 'species')), 'species');
		$speciesIds = array_map(fn(CompositionSpecies $eCompositionSpecies) => $eCompositionSpecies['id'], $speciesIds);

		// Une espèce que la grille n'a pas : son menu est vide, et doit le rester.
		$speciesIdAbsent = CompositionSpecies::model()
			->whereId('NOT IN', $speciesIds)
			->getValue('id');

		$speciesIds[] = $speciesIdAbsent;

		// Les identifiants de champ sont tirés au hasard à chaque rendu (`FormUi`).
		$render = fn(CompositionPlan $eCompositionPlan, int $speciesId, int $week) => preg_replace('/field-[0-9a-f]{13}/', 'field', $this->getRequestRender(fn() => new CompositionUi()->getCellMenu($eCompositionPlan, $speciesId, $week)));

		$kinds = ['served' => 0, 'addable' => 0, 'empty' => 0];
		$differences = [];

		foreach($eCompositionPlan['weeks'] as $week) {

			$action = $this->callRoute('POST', 'toolbox/compositionLine:getMenu', [
				'structure' => $eStructure['id'],
				'formula' => $formulaId,
				'week' => $week,
				'species' => $speciesIds[0],
			]);

			if(($action instanceof \ViewAction) === FALSE) {
				$this->assertFail('semaine '.$week.' : la route du menu ne rend pas sa vue');
				return;
			}

			$eCompositionPlanMenu = new \ReflectionProperty($action, 'data')->getValue($action)->eCompositionPlan;
			$eCompositionPlanWeek = CompositionPlanLib::getForScreen($eStructure, $formulaId, $week);

			foreach($speciesIds as $speciesId) {

				$menu = $render($eCompositionPlanMenu, $speciesId, $week);

				if($menu !== $render($eCompositionPlanWeek, $speciesId, $week)) {
					$differences[] = $week.'/'.$speciesId;
				}

				if($menu === '') {
					$kinds['empty']++;
				} else if(str_contains($menu, 'compositionLine:doCreate')) {
					$kinds['addable']++;
				} else {
					$kinds['served']++;
				}

			}

		}

		$this->assertEquals([], $differences, 'le menu ouvert diffère de celui de l\'écran lu pour sa semaine (semaine/espèce) : '.implode(', ', $differences));
		$this->assertEquals([], array_keys(array_filter($kinds, fn(int $count) => $count === 0)), 'la comparaison a rencontré chaque sorte de case, servie, à ajouter et sans menu : '.json_encode($kinds));

		// L'ouverture comptée est la route entière, sa page comprise : c'est elle qui choisit la
		// lecture. La source de la communauté se garde d'une lecture à l'autre
		// (`CompositionLib::getSource()`), et les passes de la boucle l'ont déjà lue.
		$queries = $this->countQueries(fn() => $this->callRoute('POST', 'toolbox/compositionLine:getMenu', [
			'structure' => $eStructure['id'],
			'formula' => $formulaId,
			'week' => $eCompositionPlan['weeks'][0],
			'species' => $speciesIds[0],
		]));

		$this->assertEquals(self::MENU_QUERIES, $queries, 'l\'ouverture du menu fait '.$queries.' requêtes pour '.self::MENU_QUERIES.' déclarées ; MENU_QUERIES se baisse quand un lot en retire, c\'est la déclaration qui se corrige, et une hausse est à expliquer');

	}

	/**
	 * La quantité posée dans une case vide termine le geste (r32, retour du 30/09, « ne pas
	 * afficher ça », sur la poubelle, le moins, la quantité et le plus que la réponse rendait à la
	 * place du champ) : la réponse de `compositionLine:doCreate` referme le menu au lieu de le rendre de
	 * nouveau, et la case se dit servie, avec sa quantité, comme au rechargement. La poubelle le
	 * referme aussi. Une quantité réglée dans une case servie le garde ouvert, et le rend sur la
	 * quantité nouvelle (lot r24).
	 */
	public function testCellCreateClosesMenu(): void {

		$eMember = $this->getComposerWithoutPlan(2026);

		if($eMember->empty()) {
			$this->assertFail('aucune structure maraîchère sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$this->checkCellCreateClosesMenu(\structure\StructureLib::getById($eMember['structure']['id']));
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function checkCellCreateClosesMenu(\structure\Structure $eStructure): void {

		$this->createStarted($eStructure, 2026, range(10, 40));

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

		// Une case vide qui s'ouvre sur le champ d'ajout.
		$week = NULL;
		$eCompositionSpecies = new CompositionSpecies();

		foreach($eCompositionPlan['weeks'] as $weekDelivered) {

			foreach($this->getAddableSpecies(CompositionLib::applyAddableToGrid($eCompositionFormula['grid'], $weekDelivered, $source, $steps)) as $eCompositionSpeciesAddable) {

				if($eCompositionSpecies->empty()) {
					$week = $weekDelivered;
					$eCompositionSpecies = $eCompositionSpeciesAddable;
				}

			}

		}

		if($eCompositionSpecies->empty()) {
			$this->assertFail('aucune case vide à remplir : le contrôle ne verrait rien');
			return;
		}

		// Les vues de `compositionLine.v.php` se lisent à part : le registre des vues est commun à
		// toutes, et `doQuick` y est déjà pris par `compositionSpecies.v.php`, que d'autres tests
		// chargent avant celui-ci.
		$registry = new \ReflectionProperty(\View::class, 'views');
		$registered = $registry->getValue();
		$registry->setValue(NULL, []);

		try {
			require_once __DIR__.'/../view/compositionLine.v.php';
			$views = $registry->getValue();
		} finally {
			$registry->setValue(NULL, $registered);
		}

		if(array_keys($views) !== ['doCreate', 'doDelete', 'doQuick', 'getMenu']) {
			$this->assertFail('les vues de compositionLine.v.php ne se lisent pas à part : '.implode(', ', array_keys($views)));
			return;
		}

		// La réponse, et ce qu'elle fait du menu, que `getOutputChanges()` ne relève pas.
		$render = function(string $name, \stdClass $data) use($views): array {

			$output = $this->getViewOutput($views[$name], $data);
			$instructions = json_decode($output, TRUE)['__instructions'] ?? [];

			return [
				'closed' => count(array_filter($instructions, fn(array $entry) => $entry[0] === 'js' and str_contains(json_encode($entry), 'closeDropdowns'))),
				'menu' => array_values(array_filter($instructions, fn(array $entry) => $entry[0] === 'qs' and $entry[1] === '#toolbox-composition-cell-menu')),
				'changes' => $this->getOutputChanges($name, $output, ['js', '#toolbox-composition-cell-menu']),
			];

		};

		$posts = [
			'structure' => $eStructure['id'],
			'formula' => $eCompositionFormula['id'],
			'week' => $week,
			'species' => $eCompositionSpecies['id'],
		];

		$minimum = CompositionLib::getStep($eCompositionSpecies['id'], $source, $steps)['minimum'];

		$before = $this->getRequestRender(fn() => new CompositionUi()->getProposal(CompositionPlanLib::getForScreen($eStructure, NULL)));

		$action = $this->callRoute('POST', 'toolbox/compositionLine:doCreate', $posts + [
			'unit' => $source['units'][$eCompositionSpecies['id']]['unit'],
			'quantity' => (string)$minimum,
		]);

		$this->assertTrue($action instanceof \ViewAction, 'la quantité posée est acceptée : '.($action === NULL ? 'aucune action' : get_class($action)));

		$data = new \ReflectionProperty($action, 'data')->getValue($action);

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

		$this->assertEquals($minimum, CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies)['quantity'], 'la ligne est écrite à la quantité posée');

		$created = $render('doCreate', $data);

		$this->assertEquals(1, $created['closed'], 'la quantité posée referme le menu');
		$this->assertEquals([], $created['menu'], 'et ne rend ni la poubelle, ni le moins, ni la quantité, ni le plus');

		$changes = $created['changes'];
		$cell = '#toolbox-composition-grid-species-'.$eCompositionSpecies['id'].' > td[data-week="'.$week.'"]';

		$this->assertEquals('', $changes['attributes'][$cell]['data-served'], 'la case se dit servie');
		$this->assertEquals(\util\TextUi::number($minimum, NULL), $changes['attributes'][$cell]['data-quantity'], 'avec sa quantité');

		$this->checkScreenAfter($before, $eStructure, $changes, 'quantité posée dans une case vide');

		// Une quantité réglée sur la case servie garde le menu ouvert, sur la quantité nouvelle.
		$data->switched = FALSE;

		$quick = $render('doQuick', $data);

		$this->assertEquals(0, $quick['closed'], 'une quantité réglée garde le menu ouvert');
		$this->assertEquals(1, count($quick['menu']), 'et le rend de nouveau');

		// La poubelle referme le menu.
		$action = $this->callRoute('POST', 'toolbox/compositionLine:doDelete', $posts);

		$this->assertTrue($action instanceof \ViewAction, 'la poubelle est acceptée : '.($action === NULL ? 'aucune action' : get_class($action)));
		$this->assertTrue(CompositionLineLib::getByWeekAndSpecies($eCompositionWeek, $eCompositionSpecies)->empty(), 'la ligne est retirée');

		$deleted = $render('doDelete', new \ReflectionProperty($action, 'data')->getValue($action));

		$this->assertEquals([1, []], [$deleted['closed'], $deleted['menu']], 'la poubelle referme le menu');

	}

	/**
	 * Les requêtes SQL que `$run` fait, dans l'ordre, relevées par le mode debug de `Database`, qui
	 * les écrit sur la sortie en ligne de commande.
	 */
	private function getQueries(\Closure $run): array {

		$debug = \Database::getDebug();

		\Database::setDebug(TRUE);
		ob_start();

		try {
			$run();
		} finally {
			$output = ob_get_clean();
			\Database::setDebug($debug);
		}

		preg_match_all('/ : (.*) \(\S*?[\d.]+ s\.\S*\)$/m', $output, $matches);

		return $matches[1];

	}

	private function createStarted(\structure\Structure $eStructure, int $season, array $weeks): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, $season);
		$eCompositionPlan['weeks'] = $weeks;
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * Les éléments enfants d'un nœud, sans ses textes : `\Dom\Element` n'expose pas `children`, et
	 * son `querySelectorAll()` ne connaît pas `:scope`.
	 */
	private function getChildElements(\Dom\Element $node): array {
		return array_values(array_filter(iterator_to_array($node->childNodes), fn(\Dom\Node $child) => $child instanceof \Dom\Element));
	}

	private function addFormula(CompositionPlan $eCompositionPlan, string $name, float $price): CompositionFormula {

		$eCompositionFormula = new CompositionFormula([
			'plan' => $eCompositionPlan,
			'name' => $name,
			'price' => $price,
			'baskets' => 10,
		]);

		CompositionFormulaLib::add($eCompositionFormula);

		return $eCompositionFormula;

	}

	/**
	 * La reprise des compositions écrites avant la règle d'un seul chou par panier
	 * (`migration/2026-09-23-paniers-toolbox-un-chou.md`, qui dit comment remonter son script) :
	 * `countCabbageWeeks()` trouve une semaine qui en sert deux, la range selon qu'elle est corrigée
	 * ou non, et `recomposePlan()` ramène à zéro les seules semaines non corrigées. La semaine
	 * corrigée garde ses deux choux, et un second passage n'a donc plus rien à faire.
	 */
	public function testCabbageReprise(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$eCompositionPlan = $this->createStarted($eStructure, 2026, range(10, 40));
		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$this->assertEquals(['unamended' => 0, 'amended' => 0], CompositionLib::countCabbageWeeks($eCompositionPlan, $source), 'un plan composé sous la règle ne sert jamais deux choux');

		$cabbages = array_keys($source['cabbages']);

		$this->assertTrue(count($cabbages) > 1, 'le relevé porte au moins deux choux : '.count($cabbages));

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		// Deux choux posés par le modèle, comme les écrivait la règle d'avant : les Lib marqueraient la
		// semaine corrigée, et c'est justement une semaine non corrigée qu'il faut ici.
		$lines = [];

		foreach([20 => FALSE, 30 => TRUE] as $week => $amended) {

			$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);
			$cCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek);

			$this->assertTrue($cCompositionLine->count() > 1, 'la semaine '.$week.' sert au moins deux espèces');

			$position = 0;

			foreach($cCompositionLine as $eCompositionLine) {

				if($position < 2) {
					CompositionLine::model()->update($eCompositionLine, ['species' => new CompositionSpecies(['id' => $cabbages[$position]])]);
				}

				$position++;

			}

			CompositionWeek::model()->update($eCompositionWeek, ['amended' => $amended]);

			$lines[$week] = CompositionLineLib::getByWeek($eCompositionWeek)->getColumnCollection('species')->getIds();

		}

		$this->assertEquals(['unamended' => 1, 'amended' => 1], CompositionLib::countCabbageWeeks($eCompositionPlan, $source), 'une semaine à deux choux de chaque sorte');

		CompositionLib::recomposePlan($eCompositionPlan, $source, CompositionLib::getPrices($eCompositionPlan, $source));

		$this->assertEquals(['unamended' => 0, 'amended' => 1], CompositionLib::countCabbageWeeks($eCompositionPlan, $source), 'recomposée, la semaine non corrigée ne sert plus qu\'un chou, la corrigée ne bouge pas');
		$this->assertEquals($lines[30], CompositionLineLib::getByWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 30))->getColumnCollection('species')->getIds(), 'la semaine corrigée garde ses lignes');

		$cabbagesServed = array_intersect(CompositionLineLib::getByWeek(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, 20))->getColumnCollection('species')->getIds(), $cabbages);

		$this->assertTrue(count($cabbagesServed) < 2, 'la semaine 20 sert au plus un chou : '.implode(', ', $cabbagesServed));

	}

	/**
	 * Une structure active sans plan.
	 */
	/**
	 * La première composition fait des paniers un outil de la structure (`Feature.hasToolbox`), que la
	 * Boîte à outils range alors sous « Vos outils » ; recommencer les paniers ne l'inscrit pas une
	 * seconde fois.
	 */
	public function testFirstCompositionMakesATool(): void {

		$eStructure = \structure\StructureLib::getById($this->getStructureWithoutPlan()['id']);

		$hasToolbox = fn() => \structure\Feature::model()
			->whereStructure($eStructure)
			->getValue('hasToolbox');

		$this->assertFalse(in_array(Favorite::BASKET, $hasToolbox()), 'sans composition, les paniers ne sont pas un outil de la structure');

		$this->createStarted($eStructure, 2026, range(10, 40));

		$this->assertEquals(1, count(array_keys($hasToolbox(), Favorite::BASKET, TRUE)), 'la première composition les inscrit');

		$eCompositionPlan = CompositionPlanLib::getForStart(\structure\StructureLib::getById($eStructure['id']), 2026);
		$eCompositionPlan['weeks'] = range(12, 38);
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::restart($eCompositionPlan);

		$this->assertEquals(1, count(array_keys($hasToolbox(), Favorite::BASKET, TRUE)), 'recommencer ne les inscrit pas une seconde fois');

	}

	private function getStructureWithoutPlan(): \structure\Structure {

		return \structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(CompositionPlan::model(), 'm1.id = m2.structure', 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.status', \structure\Structure::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

	}

	/**
	 * La structure dont le plus de cultures de la saison précédente récoltent dans `$season`, au kilo,
	 * rendement et surface posés : un poireau ou une mâche de l'an passé qui servent les paniers de
	 * l'hiver (arbitrage L5 du 25/09), et dont la récolte se compare sans poids.
	 */
	private function getStructureHarvestingPrevious(int $season): \structure\Structure {

		$eCultivation = \series\Cultivation::model()
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
			])
			->join(\series\Series::model(), 'm1.series = m2.id')
			->where('m1.season', $season - 1)
			->where('m1.mainUnit', \series\Cultivation::KG)
			->where('m1.yieldExpected IS NOT NULL AND COALESCE(m2.area, m2.areaTarget) IS NOT NULL')
			->where('JSON_SEARCH(m1.harvestWeeksExpected, \'one\', \''.$season.'-W%\') IS NOT NULL')
			->group(new \Sql('m1.structure'))
			->sort(new \Sql('COUNT(*) DESC, m1.structure ASC'))
			->get();

		return new \structure\Structure(['id' => $eCultivation['structure']]);

	}

	/**
	 * La structure sans plan qui cultive le plus d'espèces au kilo, rendement et surface posés, sur la
	 * saison : un plan de culture assez riche pour que chaque état de la couverture s'y rencontre, et
	 * dont la récolte se compare sans poids. Les plus grandes en nombre d'espèces comptent à la pièce
	 * ou sans rendement (483 et 3772 en dev le 25/09), et ne comparent rien.
	 */
	private function getStructureCultivated(int $season): \structure\Structure {

		$planned = CompositionPlan::model()
			->select('structure')
			->getColumn('structure')
			->getIds();

		$count = 'COUNT(DISTINCT IF(m1.mainUnit = \''.\series\Cultivation::KG.'\' AND m1.yieldExpected IS NOT NULL AND COALESCE(m2.area, m2.areaTarget) IS NOT NULL, m1.plant, NULL))';

		$eCultivation = \series\Cultivation::model()
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'count' => new \Sql($count, 'int'),
			])
			->join(\series\Series::model(), 'm1.series = m2.id')
			->where('m1.season', $season)
			->where('m1.structure', 'NOT IN', $planned, if: $planned !== [])
			->group(new \Sql('m1.structure'))
			->sort(new \Sql($count.' DESC, m1.structure ASC'))
			->get();

		return \structure\StructureLib::getById($eCultivation['structure']);

	}

}
?>
