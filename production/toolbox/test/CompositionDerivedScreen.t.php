<?php
namespace toolbox;

/**
 * L'écran d'une taille qui s'appuie sur une autre (lot r17, planche paniers-maquette-r16, question 3,
 * option B) : la base en annotation du prix (r31, #97), son choix dans « Modifier les paniers » (r31,
 * #96), le champ du panneau « Ajouter une taille », et la carte de la semaine qui range les espèces
 * reprises de la base, puis « En plus de <base> ». La grille ne distingue rien.
 *
 * La règle du lien est éprouvée par `CompositionDerivedTest` ; ce fichier éprouve ce que l'écran en
 * lit (`CompositionFormulaLib::getBaseChoices()`, `CompositionWeekLib::applyBase()`) et ce qu'il en
 * écrit. Chaque attendu est recalculé depuis les lignes écrites, et non relu sur ce que la Lib a
 * préparé, et chaque cas a son pendant : la semaine corrigée à côté de celle qui suit la base, la
 * taille sans base à côté de la dérivée.
 *
 * Le plan est celui d'une structure qui n'en a pas en 2026, écrit par les Lib comme l'écrit la page,
 * sur huit semaines. La roue de « Modifier les paniers » ne se rend qu'à qui gère la structure
 * (`Element::assertUpdate()`) : la structure est celle d'un administrateur, connecté le temps du
 * rendu.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=CompositionDerivedScreen
 */
class CompositionDerivedScreenTest extends \Test {

	const SEASON = 2026;
	const WEEKS = [20, 21, 22, 23, 24, 25, 26, 27];

	/**
	 * La semaine de la dérivée corrigée à la main, qui ne suit plus sa base.
	 */
	const AMENDED = 22;

	public function setUp(): void {
		CompositionPlan::model()->beginTransaction();
	}

	public function tearDown(): void {
		CompositionPlan::model()->rollBack();
	}

	/**
	 * La dérivée ouverte : chaque carte d'une semaine qui suit la base range d'abord les espèces que
	 * la base sert cette semaine-là, puis « En plus de Le panier » et les espèces ajoutées. La semaine
	 * corrigée ne suit plus la base : sa carte le dit sous ses dates, sans le badge « corrigée », et
	 * porte en bas « Revenir à la proposition » (lot r24, qui l'a sorti de la colonne ouverte). Le
	 * prix nomme la base et le nombre médian d'espèces ajoutées (r31, #97).
	 */
	public function testDerivedScreen(): void {

		[$eStructure, $eUser, $small, $large] = $this->createPlan();

		if($eStructure->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$this->raise($large, self::AMENDED);

		$eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, $large['id']);
		$xpath = $this->getXpath($this->render($eUser, fn() => new CompositionUi()->getFormulaPanel($eCompositionPlan)));

		// La semaine corrigée.
		$card = '//div[@id="toolbox-composition-card-'.self::AMENDED.'"]';
		$this->assertEquals(1, $xpath->query($card.'//div[contains(@class, "util-deck-band-doors")]/a[@data-ajax="/toolbox/compositionWeek:doReset"]')->length, 'et qui revient à la proposition');

	}

	/**
	 * La base se choisit dans « Modifier les paniers » (r31, #96), qu'ouvre la roue à droite de « Les
	 * paniers », sur une taille comme sur la synthèse, et se lit ensuite sous le prix (#97). La roue de
	 * l'en-tête de l'outil ne porte plus rien des paniers (#98) :
	 * - la plus petite taille et une taille sans base ne nomment rien sous leur prix ;
	 * - la roue ouvre le panneau, en disant qu'elle part de la synthèse quand c'est le cas ;
	 * - le panneau propose la petite taille à la grande, et ferme la grande à la petite, plus chère ;
	 * - la grande, une fois appuyée sur la petite par l'envoi du panneau, la nomme sous son prix.
	 */
	public function testBaseSettings(): void {

		[$eStructure, $eUser] = $this->getManager();

		if($eStructure->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createStarted($eStructure);
		$small = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$large = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Grand panier', 'price' => 30.0, 'baskets' => 10]);
		CompositionFormulaLib::add($large);

		$cFavorite = FavoriteLib::getByStructure($eStructure);

		$screen = function(CompositionFormula $e, bool $synthesis = FALSE) use($eStructure, $eUser, $cFavorite): \DOMXPath {
			$tab = $synthesis ? CompositionPlan::TAB_SYNTHESIS : CompositionPlan::TAB_FORMULA;
			$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, $synthesis ? NULL : $e['id'], tab: $tab);
			return $this->getXpath($this->render($eUser, fn() => (
				new CompositionUi()->getHeader($eStructure, $cFavorite).
				new CompositionUi()->getProposal($eCompositionPlanScreen, $tab)
			)));
		};

		// Une seule roue à droite de « Les paniers » (r32), dont le menu porte le réglage (r33) puis le
		// PDF. L'ajout d'un panier a quitté la rangée des onglets (r51a) puis ce menu (r51b) : il vit en
		// tête de « Modifier les paniers ».
		$menu = '//div[contains(@class, "toolbox-composition-heading")]/div[@class="dropdown-list"]';
		$gear = $menu.'/a[@class="dropdown-item" and text()="Modifier les paniers"]';

		$xpath = $screen($small);
		$this->assertEquals(1, $xpath->query($menu.'/a[@data-ajax-download and @data-waiter]')->length, 'le PDF garde son téléchargement et son attente');
		$this->assertEquals(['/toolbox/composition:settings?id='.$eStructure['id'].'&formula='.$small['id'].'&origin=formula'], array_map(fn(\DOMElement $a) => $a->getAttribute('href'), iterator_to_array($xpath->query($gear))), 'le menu ouvre « Modifier les paniers » sur la taille ouverte');

		$xpath = $screen($large, TRUE);
		$this->assertEquals(1, $xpath->query($gear.'[contains(@href, "&origin=synthesis")]')->length, 'la synthèse a le menu, qui dit d\'où il part');

		// Le panneau, préparé comme la page `composition:settings` le prépare.
		$eCompositionPlanSettings = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanSettings['structure'] = $eStructure;
		CompositionPlanLib::loadSettings($eCompositionPlanSettings);

		$xpath = $this->getXpath($this->render($eUser, fn() => new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, NULL, NULL)->body));
		$this->assertEquals(1, $xpath->query('//input[@type="radio" and @name="base['.$large['id'].']" and @value="'.$small['id'].'" and not(@disabled)]')->length, 'la petite taille se choisit comme base de la grande');
		$this->assertEquals(1, $xpath->query('//input[@type="radio" and @name="base['.$small['id'].']" and @value="'.$large['id'].'" and @disabled]')->length, 'la grande, plus chère, se refuse comme base de la petite');

		// « Ajouter un panier » (r51b, planche r51, piste A) : en tête du panneau, hors de son formulaire,
		// il ouvre le panneau d'ajout en lui disant le panier ouvert et l'onglet d'où l'on vient. Sa
		// question n'est posée en confirmation que par le script, quand un réglage a changé.
		$xpath = $this->getXpath($this->render($eUser, fn() => new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, $small['id'], CompositionPlan::TAB_SYNTHESIS)->body));
		$add = $xpath->query('//a[@id="toolbox-composition-settings-add"]');

		$this->assertEquals(
			['/toolbox/compositionFormula:create?structure='.$eStructure['id'].'&formula='.$small['id'].'&origin=synthesis', FALSE, TRUE],
			[$add->item(0)->getAttribute('href'), $add->item(0)->hasAttribute('data-confirm'), $add->item(0)->getAttribute('data-question') !== ''],
			'qui ouvre le panneau d\'ajout, sans confirmation tant que rien n\'a changé'
		);

		// Après l'ajout, la vue rend « Modifier les paniers » de nouveau, avec la colonne du panier ajouté :
		// les données sont préparées comme la page `compositionFormula:doCreate` les prépare.
		$extra = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Panier ajouté', 'price' => 40.0, 'baskets' => 5]);
		CompositionFormulaLib::add($extra);

		require_once __DIR__.'/../view/compositionFormula.v.php';

		$data = new \stdClass();
		$data->eStructure = $eStructure;
		$data->formula = $small['id'];
		$data->origin = CompositionPlan::TAB_FORMULA;
		$data->tab = CompositionPlanLib::getTab($data->origin);

		$output = $this->render($eUser, function() use($data, $eStructure) {

			$data->eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, $data->formula, tab: $data->tab);

			$data->eCompositionPlanSettings = CompositionPlanLib::getByStructure($eStructure);
			$data->eCompositionPlanSettings['structure'] = $eStructure;
			CompositionPlanLib::loadSettings($data->eCompositionPlanSettings);

			ob_start();
			\View::get('doCreate', new \ViewAction())->render($data, new \AjaxTemplate());

			return ob_get_clean();

		});

		$panels = [];
		$closed = [];

		foreach(json_decode($output, TRUE)['__instructions'] as $entry) {

			if($entry[0] !== 'js') {
				continue;
			}

			foreach($entry[1] as [$method, $arguments]) {

				if($method === 'pushPanel') {
					$panels[] = $arguments[0];
				} else if($method === 'closePanel') {
					$closed[] = $arguments[0];
				}

			}

		}

		$this->assertEquals(['#toolbox-composition-formula-create'], $closed, 'le panneau d\'ajout se ferme');
		$this->assertEquals(['toolbox-composition-settings-panel'], array_column($panels, 'id'), 'et « Modifier les paniers » se rend de nouveau');

		$xpath = $this->getXpath($panels[0]['body']);

		$this->assertEquals(1, $xpath->query('//input[@name="name['.$extra['id'].']" and @value="Panier ajouté"]')->length, 'avec la colonne du panier ajouté');
		$this->assertEquals('/toolbox/compositionFormula:create?structure='.$eStructure['id'].'&formula='.$small['id'].'&origin=formula', $xpath->query('//a[@id="toolbox-composition-settings-add"]')->item(0)->getAttribute('href'), 'et son bouton d\'ajout, qui garde le panier ouvert et l\'onglet');

		// L'envoi, lu comme la page `composition:doSettings` le lit.
		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlanSettings);
		$cCompositionFormula->setColumn('plan', $eCompositionPlanSettings);

		$input = [];
		foreach($cCompositionFormula as $formulaId => $eCompositionFormula) {
			foreach(['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax'] as $property) {
				$input[$property][$formulaId] = $eCompositionFormula[$property];
			}
			$input['base'][$formulaId] = ($formulaId === $large['id']) ? $small['id'] : '';
		}

		// Un prix posté avec la base se compare à elle tel qu'il est posté : la page construit le prix
		// avant la base. La grande à 10 € ne s'appuie pas sur la petite à 18 €.
		$fw = new \FailWatch();
		$eCompositionFormulaCheaper = CompositionFormulaLib::getByPlan($eCompositionPlanSettings)[$large['id']];
		$eCompositionFormulaCheaper['plan'] = $eCompositionPlanSettings;
		$eCompositionFormulaCheaper->buildIndex(['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'base'], ['price' => [$large['id'] => '10']] + $input, $large['id']);
		$this->assertEquals(['base['.$large['id'].']'], array_column($fw->get(), 3), 'une base plus chère que le prix posté est refusée, sous son champ');

		$fw = new \FailWatch();
		foreach($cCompositionFormula as $formulaId => $eCompositionFormula) {
			$eCompositionFormula->buildIndex(['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'base'], $input, $formulaId);
		}
		$this->assertEquals([], array_column($fw->get(), 1), 'la petite taille est acceptée comme base');

	}

	/**
	 * La carte des paniers de l'année, sur la page des outils, n'a plus de portes vers « Composition »
	 * et « Synthèse » (r29, « enlève les boutons sur les paniers de l'année ») : sa tête et son chevron
	 * mènent à l'outil, qui porte ces deux onglets, et ses chiffres restent.
	 */
	public function testToolCardWithoutDoors(): void {

		[$eStructure, $eUser] = $this->getManager(producer: TRUE, vegetable: TRUE);

		if($eStructure->empty()) {
			$this->assertFail('aucune structure de légumes sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$this->createStarted($eStructure);

		$eStructureRead = \structure\StructureLib::getById($eStructure['id']);
		$cCompositionFormula = CompositionFormulaLib::getByStructure($eStructureRead);

		$xpath = $this->getXpath($this->render($eUser, fn() => new ToolUi()->getList($eStructureRead, new \Collection(), new \Collection(), $cCompositionFormula, [], ['equipments' => 0, 'overdue' => 0])));

		$url = CompositionUi::url($eStructureRead);
		$card = '//div[contains(@class, "util-deck ")][.//div[@class="util-deck-title"][text()="'.s("Paniers de l'année").'"]]';

		$this->assertEquals(1, $xpath->query($card.'//a[@class="util-deck-body"][@href="'.$url.'"]')->length, 'sa tête mène à l\'outil');
		$this->assertEquals(1, $xpath->query($card.'//a[@class="util-deck-arrow"][@href="'.$url.'"]')->length, 'son chevron aussi');

	}

	/**
	 * Les paniers s'ouvrent à toute structure qui a la production, qu'elle ait déclaré des légumes ou
	 * non (lot r42, « active paniers pour tout le monde ») : un producteur qui n'a déclaré ni le
	 * maraichage ni les légumes trouve la carte parmi les outils de la Boîte, et elle mène à l'outil.
	 */
	public function testToolListWithoutVegetable(): void {

		[$eStructure, $eUser] = $this->getManager(producer: TRUE, vegetable: FALSE);

		if($eStructure->empty()) {
			$this->assertFail('aucun producteur sans légumes n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$this->assertEquals([TRUE, FALSE], [$eStructure->acceptProduction(), $eStructure->acceptVegetable()], 'le cas est monté : un producteur qui n\'a déclaré ni le maraichage ni les légumes');

		$xpath = $this->getXpath($this->render($eUser, fn() => new ToolUi()->getList($eStructure, new \Collection(), new \Collection(), new \Collection(), [], ['equipments' => 0, 'overdue' => 0])));

		$card = '//div[contains(concat(" ", @class, " "), " util-deck ")][not(contains(@class, "toolbox-planned"))][.//div[@class="util-deck-title"][text()="'.s("Paniers de l'année").'"]]';

		$this->assertEquals(1, $xpath->query($card.'//a[@class="util-deck-body"][@href="'.CompositionUi::url($eStructure).'"]')->length, 'et mène à l\'outil');

	}

	/**
	 * Les tailles proposées et la raison de chaque refus, une par règle de
	 * `CompositionFormula::properties()` : une base ne prend pas de base (`serving`), une dérivée ne
	 * sert pas de base (`derived`), une base coûte moins (`pricier`, `priceSame`) et ne va pas jusqu'à
	 * plus de légumes (`lines`). « Modifier les paniers » les écrit, désactivées, sous leur nom.
	 */
	public function testBaseChoices(): void {

		[$eStructure, $eUser, $small, $large, $extra] = $this->createPlan();

		if($eStructure->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

		$refusals = fn(CompositionFormula $e) => array_map(fn(array $choice) => [$choice['refusal'], $choice['cited']], CompositionFormulaLib::getBaseChoices($e, CompositionFormulaLib::getByPlan($e['plan'])));

		$this->assertEquals([
			$large['id'] => ['serving', ['Grand panier']],
			$extra['id'] => ['serving', ['Grand panier']],
		], $refusals($this->getFormula($eCompositionPlan, $small)), 'la base ne prend pas de base');

		$this->assertEquals([
			$small['id'] => [NULL, []],
			$extra['id'] => ['pricier', ['Grand panier']],
		], $refusals($this->getFormula($eCompositionPlan, $large)), 'la dérivée peut garder sa base, pas la plus chère');

		$this->assertEquals([
			$small['id'] => [NULL, []],
			$large['id'] => ['derived', ['Le panier']],
		], $refusals($this->getFormula($eCompositionPlan, $extra)), 'la très grande peut s\'appuyer sur la base, pas sur la dérivée');

		$eCompositionFormulaSame = $this->getFormula($eCompositionPlan, $extra);
		$eCompositionFormulaSame['price'] = $small['price'];

		$this->assertEquals(['priceSame', ['Très grand panier']], $refusals($eCompositionFormulaSame)[$small['id']], 'une base au même prix');

		$eCompositionFormulaNarrow = $this->getFormula($eCompositionPlan, $extra);
		$eCompositionFormulaNarrow['linesMax'] = 5;

		$this->assertEquals(['lines', ['Très grand panier']], $refusals($eCompositionFormulaNarrow)[$small['id']], 'une base qui va jusqu\'à plus de légumes');

		$eCompositionFormulaNew = new CompositionFormula(['plan' => $eCompositionPlan, 'base' => new CompositionFormula()]);

		$this->assertEquals([
			$small['id'] => [NULL, []],
			$large['id'] => ['derived', ['Le panier']],
			$extra['id'] => [NULL, []],
		], $refusals($eCompositionFormulaNew), 'une taille neuve ne se compare qu\'à l\'envoi, sauf à une dérivée');

		// « Modifier les paniers » : pour la très grande, « Aucune » cochée, la base proposée, la dérivée
		// désactivée avec sa raison.
		$settings = function() use($eStructure, $eUser): \DOMXPath {
			$eCompositionPlanSettings = CompositionPlanLib::getByStructure($eStructure);
			$eCompositionPlanSettings['structure'] = $eStructure;
			CompositionPlanLib::loadSettings($eCompositionPlanSettings);
			return $this->getXpath($this->render($eUser, fn() => new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, NULL, NULL)->body));
		};

		$xpath = $settings();

		$radios = [];

		foreach($xpath->query('//input[@type="radio"][@name="base['.$extra['id'].']"]') as $input) {
			$radios[$input->getAttribute('value')] = [
				'checked' => $input->hasAttribute('checked'),
				'disabled' => $input->hasAttribute('disabled'),
			];
		}

		$this->assertEquals([
			'' => ['checked' => TRUE, 'disabled' => FALSE],
			(string)$small['id'] => ['checked' => FALSE, 'disabled' => FALSE],
			(string)$large['id'] => ['checked' => FALSE, 'disabled' => TRUE],
		], $radios, 'les choix de la très grande');

		// La dérivée : sa base est cochée, et sans semaine corrigée, rien à confirmer.
		$this->assertEquals([(string)$small['id']], array_map(fn(\DOMElement $input) => $input->getAttribute('value'), iterator_to_array($xpath->query('//input[@name="base['.$large['id'].']"][@checked]'))), 'la base de la dérivée cochée');
		$this->assertEquals(0, $xpath->query('//*[@data-loss]')->length, 'aucune perte annoncée sans semaine corrigée');

		// Le pendant : une semaine corrigée, et la colonne de la dérivée annonce ce qu'une recomposition
		// lui ferait perdre, en la nommant.
		$this->raise($large, self::AMENDED);

		$xpath = $settings();
		$loss = $xpath->query('//div[@data-loss]');

		$this->assertEquals(1, $loss->length, 'une seule colonne annonce une perte');
		$this->assertEquals('name['.$large['id'].']', $loss->item(0)->getAttribute('data-wrapper'), 'celle de la dérivée, sur la cellule de son nom');
		$this->assertEquals((string)$large['id'], $loss->item(0)->getAttribute('data-formula'), 'qui dit quelle taille elle règle, pour le script');

	}

	/**
	 * « Ajouter une taille » avec une base : la taille ajoutée prend les bornes du nombre de légumes
	 * de sa base, que le panneau ne demande pas, et reprend ses lignes. Le prix se compare à l'envoi,
	 * et le refus tombe sous le champ de la base.
	 */
	public function testCreateWithBase(): void {

		[$eStructure] = $this->getManager();

		if($eStructure->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eCompositionPlan = $this->createStarted($eStructure);
		$small = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		// Un plafond de base au-dessus du défaut du schéma : la taille ajoutée doit le prendre, et non
		// garder le défaut, que la règle `lines` refuserait ensuite.
		$eCompositionFormula = $this->getFormula($eCompositionPlan, $small);
		$eCompositionFormula['linesMax'] = 10;
		CompositionFormulaLib::update($eCompositionFormula, ['linesMax']);

		$build = function(array $input) use($eCompositionPlan): array {

			$eCompositionFormula = new CompositionFormula([
				'id' => NULL,
				'plan' => $eCompositionPlan,
				'base' => new CompositionFormula(),
			]);

			$fw = new \FailWatch();

			$eCompositionFormula->build(['name', 'price', 'baskets', 'base'], $input, new \PropertyList('create'));

			return [$eCompositionFormula, array_map(fn($fail) => $fail[1], $fw->get())];

		};

		[, $fails] = $build(['name' => 'Petit', 'price' => '12', 'baskets' => '10', 'base' => $small['id']]);
		$this->assertEquals(['CompositionFormula::base.cheaper'], $fails, 'une base plus chère que le prix tapé');

		[$eCompositionFormulaLarge, $fails] = $build(['name' => 'Grand panier', 'price' => '30', 'baskets' => '10', 'base' => $small['id']]);
		$this->assertEquals([], $fails, 'une base moins chère');

		CompositionFormulaLib::add($eCompositionFormulaLarge);

		$large = $this->getFormula($eCompositionPlan, $eCompositionFormulaLarge);

		$this->assertEquals($small['id'], $large['base']['id'], 'la base écrite');
		$this->assertEquals([4, 10], [$large['linesMin'], $large['linesMax']], 'les bornes de la base');

		$linesSmall = $this->getLines($small);
		$linesLarge = $this->getLines($large);

		$this->assertTrue($linesSmall !== [], 'la base a des semaines');

		foreach($linesSmall as $week => $lines) {
			$this->assertEquals([], array_diff_key($lines, $linesLarge[$week] ?? []), 'semaine '.$week.', toutes les espèces de la base reprises');
		}

		[, $fails] = $build(['name' => 'Très grand', 'price' => '40', 'baskets' => '10', 'base' => $large['id']]);
		$this->assertEquals(['CompositionFormula::base.chain'], $fails, 'une dérivée ne sert pas de base');

		[$eCompositionFormulaAlone, $fails] = $build(['name' => 'Seul', 'price' => '40', 'baskets' => '10', 'base' => '']);
		$this->assertEquals([], $fails, 'sans base');

		CompositionFormulaLib::add($eCompositionFormulaAlone);

		$alone = $this->getFormula($eCompositionPlan, $eCompositionFormulaAlone);

		$this->assertTrue($alone['base']->empty(), 'aucune base écrite');
		$this->assertEquals([4, 8], [$alone['linesMin'], $alone['linesMax']], 'le pendant : sans base, les bornes du schéma');

	}

	private function render(\user\User $eUser, \Closure $callback): string {

		$eUserInitial = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($eUser);

		try {
			return $callback();
		} finally {
			\user\ConnectionLib::setOnline($eUserInitial);
		}

	}

	private function getXpath(string $html): \DOMXPath {

		// libxml ne connaît pas les balises SVG des vignettes, et le dit en avertissements.
		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return new \DOMXPath($document);

	}

	/**
	 * Les lignes écrites d'une formule, `[semaine => [espèce du plan => quantité]]`.
	 */
	private function getLines(CompositionFormula $e): array {

		$lines = [];

		foreach(CompositionLineLib::getByFormula($e) as $eCompositionLine) {
			$lines[$eCompositionLine['week']['week']][$eCompositionLine['species']['id']] = (float)$eCompositionLine['quantity'];
		}

		ksort($lines);

		return $lines;

	}

	/**
	 * Une correction à la main : la première ligne de la semaine `$week` prend une unité de plus, par
	 * le geste de la grille (`CompositionLineLib::update()`).
	 */
	private function raise(CompositionFormula $e, int $week): void {

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($e, $week);
		$eCompositionLine = CompositionLineLib::getByWeek($eCompositionWeek)->first();

		$eCompositionLine['quantity'] = $eCompositionLine['quantity'] + 1;
		$eCompositionLine['week'] = $eCompositionWeek;

		CompositionLineLib::update($eCompositionLine, ['quantity']);

	}

	private function getFormula(CompositionPlan $eCompositionPlan, CompositionFormula $e): CompositionFormula {
		return CompositionFormulaLib::getByPlanAndId($eCompositionPlan, $e['id']);
	}

	/**
	 * La structure d'un administrateur, sans plan en 2026, et cet administrateur ; une structure vide
	 * quand il n'y en a aucune. `$producer` la veut productrice, à qui la page des outils montre les
	 * paniers (`Structure::acceptComposition()`), ou d'un autre type quand il vaut `FALSE`.
	 * `$vegetable` la veut déclarée en maraichage ou en légumes, ou ni l'un ni l'autre quand il vaut
	 * `FALSE`. `NULL` ne regarde pas.
	 */
	private function getManager(?bool $producer = NULL, ?bool $vegetable = NULL): array {

		$mMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure AND m3.season = '.self::SEASON, 'LEFT')
			->join(\structure\Structure::model(), 'm1.structure = m4.id');

		if($producer !== NULL) {
			$mMember->where('m4.type', $producer ? '=' : '!=', \structure\Structure::PRODUCER);
		}

		if($vegetable === TRUE) {
			$mMember->join(\structure\Production::model(), 'm1.structure = m5.structure AND m5.fqn IN (\'maraichage\', \'legume\')');
		} else if($vegetable === FALSE) {
			$mMember
				->join(\structure\Production::model(), 'm1.structure = m5.structure AND m5.fqn IN (\'maraichage\', \'legume\')', 'LEFT')
				->where('m5.id IS NULL');
		}

		$eMember = $mMember
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		if($eMember->empty()) {
			return [new \structure\Structure(), new \user\User()];
		}

		return [\structure\StructureLib::getById($eMember['structure']['id']), $eMember['user']];

	}

	/**
	 * Le départ d'une taille, « Le panier » à 18 €, écrit comme la page l'écrit.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = self::WEEKS;
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * Trois tailles : « Le panier » à 18 €, « Grand panier » à 30 € qui s'appuie sur lui, ajouté par
	 * `CompositionFormulaLib::add()` comme depuis le panneau, et « Très grand panier » à 40 €, sans
	 * base. Rend `[structure, administrateur, petite, grande, très grande]`, une structure vide quand
	 * aucune ne convient.
	 */
	private function createPlan(): array {

		[$eStructure, $eUser] = $this->getManager();

		if($eStructure->empty()) {
			return [$eStructure, $eUser, new CompositionFormula(), new CompositionFormula(), new CompositionFormula()];
		}

		$eCompositionPlan = $this->createStarted($eStructure);
		$small = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$large = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Grand panier', 'price' => 30.0, 'baskets' => 10, 'base' => $small]);
		CompositionFormulaLib::add($large);

		$extra = new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Très grand panier', 'price' => 40.0, 'baskets' => 10]);
		CompositionFormulaLib::add($extra);

		return [$eStructure, $eUser, $small, $large, $extra];

	}

}
?>
