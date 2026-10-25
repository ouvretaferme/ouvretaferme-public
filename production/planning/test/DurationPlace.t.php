<?php
namespace planning;

/**
 * La saisie derrière une tuile (`PlanningDuration.place()`) et la fenêtre d'un champ par pas
 * (`PlanningDurationStep.place()`) se décalent de côté pour tenir entières dans l'écran que la page leur
 * laisse : `main`, qui commence après le menu, ou le panneau qui les porte. Posées sur un bord de ce qui les
 * ouvre, elles débordaient à droite dans le tiroir de récolte (contre-recette de N2), à gauche sous le menu
 * sur la fiche de temps (relecture de N3, contre-recette de M2 vers 2400 px).
 *
 * Les deux classes tournent dans node, extraites telles quelles de `planning.js`, sur des nœuds factices qui
 * ne portent que ce que le placement mesure. Une boîte se décale avec le décalage posé sur son style, comme
 * `translate` la décale à l'écran. Le rem vaut 16 px et `--space-xs` 0,5 rem, soit 8 px de marge aux bords.
 *
 * Lancement : dev/test package=planning file=DurationPlace
 */
class DurationPlaceTest extends \Test {

	/**
	 * Le tiroir de récolte : la saisie, posée à gauche de sa tuile, dépasse le bord droit de l'écran. Elle
	 * revient à 8 px de lui.
	 */
	public function testPanelRightOverflow(): void {

		$result = $this->place('panel', main: [191, 1199], node: [1000, 1384]);

		$this->assertEquals('-193px', $result['shift'], 'la saisie qui dépasse à droite ne revient pas dans l\'écran');
		$this->assertEquals([807, 1191], $result['bounds'], 'la saisie décalée ne finit pas à 8 px du bord droit');

	}

	/**
	 * La fiche de temps : la fenêtre, posée à droite de sa cellule, passe sous le menu. Elle revient à 8 px du
	 * bord gauche de `main`.
	 */
	public function testStepLeftOverflow(): void {

		$result = $this->place('step', main: [191, 1199], node: [100, 464]);

		$this->assertEquals('99px', $result['shift'], 'la fenêtre qui passe sous le menu ne revient pas dans l\'écran');
		$this->assertEquals([199, 563], $result['bounds'], 'la fenêtre décalée ne commence pas à 8 px du menu');

	}

	/**
	 * Le même débord à gauche pour la saisie, et le même à droite pour la fenêtre : la règle est commune.
	 */
	public function testBothSides(): void {

		$this->assertEquals('48px', $this->place('panel', main: [0, 1199], node: [-40, 344])['shift'], 'la saisie qui sort à gauche ne revient pas');
		$this->assertEquals('-73px', $this->place('step', main: [191, 1199], node: [900, 1264])['shift'], 'la fenêtre qui sort à droite ne revient pas');

	}

	/**
	 * Entière dans l'écran, rien ne bouge, et un décalage laissé par l'ouverture précédente s'efface.
	 */
	public function testInside(): void {

		$result = $this->place('panel', main: [191, 1199], node: [400, 784]);

		$this->assertEquals(NULL, $result['shift'], 'une saisie entière dans l\'écran se décale');

		$result = $this->place('step', main: [191, 1199], node: [400, 764], current: -120);

		$this->assertEquals(NULL, $result['shift'], 'le décalage d\'une ouverture précédente reste posé');
		$this->assertEquals([400, 764], $result['bounds'], 'la fenêtre ne revient pas à sa place');

	}

	/**
	 * Un décalage déjà posé ne compte pas dans la mesure : la même tuile rouverte donne le même décalage.
	 */
	public function testReopen(): void {

		$result = $this->place('panel', main: [191, 1199], node: [1000, 1384], current: -193);

		$this->assertEquals('-193px', $result['shift'], 'une saisie rouverte cumule le décalage précédent');

	}

	/**
	 * Dans un panneau, ce sont ses bords qui comptent, pas ceux de `main` ; plus large que l'écran, la fenêtre
	 * se cale sur le bord gauche. `main` plus large que la fenêtre est borné à elle.
	 */
	public function testPanelAndNarrow(): void {

		$this->assertEquals('-108px', $this->place('step', main: [191, 1199], node: [800, 1164], panel: [600, 1064])['shift'], 'la fenêtre d\'un panneau en sort');
		$this->assertEquals('8px', $this->place('step', main: [0, 360], node: [0, 400])['shift'], 'plus large que l\'écran, la fenêtre ne se cale pas à gauche');
		$this->assertEquals('-212px', $this->place('panel', main: [0, 2000], node: [1000, 1384], width: 1180)['shift'], 'la saisie suit `main` au-delà de la fenêtre');

	}

	/**
	 * La barre de défilement qu'un ordinateur dessine dans le panneau prend sur sa largeur : la fenêtre
	 * s'arrête à 8 px de la barre, et non du bord du panneau (passe ui de R, remarque 1).
	 */
	public function testPanelScrollbar(): void {

		$result = $this->place('step', main: [0, 576], node: [200, 564], panel: [0, 576], width: 576, scrollbar: 20);

		$this->assertEquals('-16px', $result['shift'], 'la fenêtre passe sous la barre de défilement du panneau');
		$this->assertEquals([184, 548], $result['bounds'], 'la fenêtre décalée ne finit pas à 8 px de la barre');

	}

	/**
	 * En feuille au bas de l'écran, la saisie ne se décale pas.
	 */
	public function testSheet(): void {

		$result = $this->place('panel', main: [0, 360], node: [200, 584], phone: TRUE);

		$this->assertEquals(NULL, $result['shift'], 'une feuille se décale');
		$this->assertTrue($result['sheet'], 'la saisie au téléphone n\'est pas une feuille');

	}

	/**
	 * Un écran à 844×390, page en haut (constat de P, 3a, relevé sur Répartir, retiré depuis) : entre un en-tête
	 * collant et la barre du pouce, il reste 31 px pour une fenêtre de 104. Elle monte au-dessus de sa ligne,
	 * entre les seules barres fixes, et passe devant ce qui colle : sa tuile reste découverte, et rien ne défile.
	 */
	public function testStepOverStickyHeads(): void {

		$result = $this->placeVertical(
			height: 390,
			bars: [[0, 0, 844, 44], [0, 287, 844, 339], [0, 339, 844, 391]],
			title: [0, 44, 823, 137],
			head: [20, 196, 804, 256],
			row: [20, 256, 809, 309],
			table: [20, 196, 809, 554],
			window: 104
		);

		$this->assertTrue($result['up'], 'la fenêtre sans place ne monte pas au-dessus de sa ligne');
		$this->assertTrue($result['over'], 'la fenêtre sans place reste derrière l\'en-tête collant');
		$this->assertEquals(NULL, $result['scroll'], 'la page défile sous la fenêtre posée entre les barres');

	}

	/**
	 * Récoltes à 1199×600, en-tête de la liste collé (constat de P, 3b) : la fenêtre ne monte pas sous lui, elle
	 * se pose sous sa tuile, où l'écran la loge. Le menu et sa barre sont des colonnes, pas des barres du haut.
	 */
	public function testStepUnderStickyHead(): void {

		$result = $this->placeVertical(
			height: 600,
			bars: [[0, 0, 68, 600], [68, 0, 247, 600]],
			title: [247, -236, 1177, -156],
			head: [267, 0, 1158, 105],
			row: [795, 155, 845, 179],
			table: [537, 114, 1017, 197],
			window: 103
		);

		$this->assertFalse($result['up'], 'la fenêtre monte sous l\'en-tête collant de la liste');
		$this->assertFalse($result['over'], 'la fenêtre logée passe devant l\'en-tête');

	}

	/**
	 * La page d'une intervention, deux lignes de temps (constat de P, 3c) : monter couvrirait la rangée des
	 * jours, hors de la table. La fenêtre reste sous sa ligne, le panneau ayant la place.
	 */
	public function testStepStaysInTable(): void {

		$result = $this->placeVertical(
			height: 765,
			panel: [0, 0, 1199, 765],
			panelHead: [20, 0, 1165, 69],
			row: [39, 277, 585, 314],
			table: [39, 277, 585, 348],
			window: 103
		);

		$this->assertFalse($result['up'], 'la fenêtre monte sur la rangée des jours, hors de sa table');

		// Une table assez haute la loge au-dessus de sa dernière ligne quand le panneau manque dessous.
		$result = $this->placeVertical(
			height: 765,
			panel: [0, 0, 1199, 700],
			panelHead: [20, 0, 1165, 69],
			row: [39, 600, 585, 637],
			table: [39, 100, 585, 671],
			window: 103
		);

		$this->assertTrue($result['up'], 'la fenêtre de la dernière ligne d\'une table haute ne monte plus quand la place manque dessous');

	}

	/**
	 * La grille de la Semaine sous 53rem, filtrée sur une ligne, à 360×900 (R-Retours-1210, FT) : elle défile
	 * dans sa boîte et finit à 634, 41 px sous la case. La fenêtre descend tout de même, l'écran ayant la place,
	 * et sort de la boîte qui la couperait (`is-fixed`), posée d'après sa case.
	 */
	public function testStepLeavesClippingGrid(): void {

		$result = $this->placeVertical(
			height: 900,
			row: [6, 551, 338, 593],
			table: [6, 301, 338, 634],
			window: 100,
			cell: [63, 551, 106, 593]
		);

		$this->assertFalse($result['up'], 'la fenêtre monte alors que l\'écran la loge sous la case');
		$this->assertTrue($result['fixed'], 'la fenêtre reste dans la grille qui la coupe');
		$this->assertEquals(['551px', '593px', '106px'], $result['cell'], 'la fenêtre n\'est pas posée d\'après sa case');

		// Une table qui ne coupe pas garde la fenêtre dans son flux.
		$result = $this->placeVertical(
			height: 900,
			row: [6, 551, 338, 593],
			table: [6, 301, 338, 634],
			window: 100
		);

		$this->assertFalse($result['fixed'], 'la fenêtre d\'une table qui ne coupe pas sort de son flux');

	}

	/**
	 * La même grille défilée de côté, fenêtre ouverte (R-Retours-1210, FT). La case n'en garde que 6 px visibles
	 * à gauche de la grille : la fenêtre la suit et se décale pour revenir à 8 px du bord de l'écran, « Enregistrer »
	 * compris. Sortie entière, la case ferme sa fenêtre.
	 */
	public function testStepFollowsCell(): void {

		$result = $this->follow(cell: [-31, 12]);

		$this->assertFalse($result['closed'], 'la fenêtre d\'une case encore visible se ferme');
		$this->assertEquals('12px', $result['right'], 'la fenêtre ne suit pas sa case');
		$this->assertEquals([8, 286], $result['bounds'], 'la fenêtre qui suit sa case sort de l\'écran');

		$result = $this->follow(cell: [-137, -94]);

		$this->assertTrue($result['closed'], 'la fenêtre d\'une case sortie de la grille reste ouverte');

	}

	/**
	 * Le tiroir de Récoltes à 1199×800, une seconde personne ajoutée sous la première (R-Retours-0810, AB,
	 * point 5) : la fenêtre descend sous le bas du formulaire (649, soit 55 sous la ligne), sur la culture
	 * suivante de la liste, au lieu de couvrir « Annuler » sous la ligne ou de monter sur la quantité et le temps
	 * de la première personne. Sur un écran de 650, elle ne monte pas davantage : la page défile.
	 */
	public function testStepBelowInDrawer(): void {

		$result = $this->placeVertical(
			height: 800,
			row: [544, 557, 611, 594],
			table: [432, 414, 725, 649],
			window: 105,
			drawer: TRUE
		);

		$this->assertFalse($result['up'], 'la fenêtre du tiroir monte sur la saisie en cours alors que l\'écran la loge dessous');
		$this->assertEquals('55px', $result['drop'], 'la fenêtre du tiroir ne descend pas sous le bas du formulaire');

		$result = $this->placeVertical(
			height: 650,
			row: [544, 557, 611, 594],
			table: [432, 414, 725, 649],
			window: 105,
			drawer: TRUE
		);

		$this->assertFalse($result['up'], 'la fenêtre du tiroir monte sur la saisie en cours faute de place dans l\'écran');
		$this->assertFalse($result['over'], 'la fenêtre du tiroir passe devant ce qui colle');
		$this->assertEquals('55px', $result['drop'], 'la fenêtre du tiroir ne descend pas sous le bas du formulaire');
		$this->assertTrue($result['scroll'] > 0, 'la page ne défile pas jusqu\'à la fenêtre du tiroir');

	}

	/**
	 * Le tiroir de la dernière culture (constat de T, point 3) s'ouvre sous la ligne, sous les barres du bas. La
	 * page défile jusqu'à montrer le bas de son formulaire, et la ligne reste sous le titre collant. À 844×390,
	 * le formulaire se pose par-dessus ce qui suit et le tiroir n'a pas de hauteur (relevé dans Chrome) : c'est
	 * le formulaire qui se mesure.
	 */
	public function testDrawerScrollsPage(): void {

		$result = $this->openDrawer(
			height: 700,
			bars: [[0, 0, 360, 44], [0, 601, 340, 652], [0, 652, 340, 700]],
			title: [0, 44, 360, 137],
			row: [6, 495, 333, 829],
			drawer: [6, 576, 333, 829]
		);

		$this->assertEquals(228, $result['scroll'], 'la page ne défile pas jusqu\'au bas du tiroir');

		$result = $this->openDrawer(
			height: 390,
			bars: [[0, 0, 844, 44], [0, 287, 844, 339], [0, 339, 844, 391]],
			title: [0, 44, 823, 137],
			row: [20, 215, 804, 254],
			drawer: [20, 254, 804, 254],
			form: [20, 254, 804, 300]
		);

		$this->assertEquals(13, $result['scroll'], 'à 844×390, la page ne défile pas jusqu\'au bas du formulaire posé par-dessus');

	}

	/**
	 * Une culture à parts, ouverte sur sa dernière part (relecteur de T, point 1) : le haut de la ligne de la
	 * culture est loin au-dessus de l'écran. Le tiroir défile jusqu'au bas de son formulaire, borné par la
	 * part qui l'a ouvert, et jamais vers le haut.
	 */
	public function testDrawerUnderPart(): void {

		$result = $this->openDrawer(
			height: 700,
			bars: [[0, 0, 360, 44], [0, 601, 340, 652], [0, 652, 340, 700]],
			title: [0, 44, 360, 137],
			row: [6, -200, 333, 750],
			part: [6, 495, 333, 576],
			drawer: [6, 576, 333, 750]
		);

		$this->assertEquals(149, $result['scroll'], 'la page défile d\'après le haut de la culture, et non de la part qui a ouvert le tiroir');

	}

	/**
	 * À 767×640, page en haut (relevé dans Chrome) : l'en-tête de la liste n'a pas encore atteint son `top` et
	 * finit à 342, quand il finira à 148 une fois collé. La page défile jusqu'au bas du formulaire, la ligne
	 * restant sous l'en-tête collé.
	 */
	public function testDrawerHeadNotStuck(): void {

		$result = $this->openDrawer(
			height: 640,
			bars: [[0, 0, 767, 44], [0, 536, 767, 588], [0, 588, 767, 640]],
			title: [0, 44, 745, 137],
			head: [13, 237, 732, 342],
			row: [13, 499, 732, 749],
			drawer: [13, 538, 732, 749],
			form: [13, 546, 732, 735]
		);

		$this->assertEquals(199, $result['scroll'], 'la page s\'arrête où l\'en-tête de la liste est encore, pas où il collera');

	}

	/**
	 * Sans place pour tout, la ligne passe d'abord : la page défile jusqu'à poser son haut sous le titre collant.
	 */
	public function testDrawerKeepsRow(): void {

		$result = $this->openDrawer(
			height: 390,
			bars: [[0, 0, 844, 44], [0, 287, 844, 339], [0, 339, 844, 391]],
			title: [0, 44, 823, 137],
			row: [20, 150, 804, 254],
			drawer: [20, 254, 804, 254],
			form: [20, 254, 804, 365]
		);

		$this->assertEquals(13, $result['scroll'], 'la page fait passer la ligne sous le titre collant');

	}

	/**
	 * Un tiroir qui tient dans l'écran ne fait rien défiler (1199, constat de T), et un redimensionnement ne fait
	 * que reposer la pointe, même quand le tiroir est sous les barres.
	 */
	public function testDrawerFits(): void {

		$result = $this->openDrawer(
			height: 765,
			title: [247, 0, 1177, 80],
			row: [267, 300, 1158, 439],
			drawer: [267, 355, 1158, 439]
		);

		$this->assertEquals(NULL, $result['scroll'], 'la page défile sous un tiroir qui tient dans l\'écran');
		$this->assertEquals('0px', $result['center'], 'le tiroir ne pose plus sa pointe');

		$result = $this->openDrawer(
			height: 700,
			bars: [[0, 0, 360, 44], [0, 601, 340, 652], [0, 652, 340, 700]],
			title: [0, 44, 360, 137],
			row: [6, 495, 333, 829],
			drawer: [6, 576, 333, 829],
			resize: TRUE
		);

		$this->assertEquals(NULL, $result['scroll'], 'la page défile quand l\'écran change de taille');
		$this->assertEquals('0px', $result['center'], 'le redimensionnement ne repose pas la pointe');

	}

	private function getSource(): string {
		return file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');
	}

	private function getClass(string $source, string $name): ?string {

		$start = strpos($source, "\nclass ".$name." {");

		if($start === FALSE) {
			return NULL;
		}

		$end = strpos($source, "\n}\n", $start);

		return ($end === FALSE) ? NULL : substr($source, $start, $end - $start + 2);

	}

	/**
	 * Ouvre une saisie (`panel`) ou une fenêtre (`step`) dont la boîte, sans décalage, va de `$node[0]` à
	 * `$node[1]`, sous une ligne, dans `main` qui va de `$main[0]` à `$main[1]`, ou dans un panneau. `$current`
	 * est le décalage laissé par l'ouverture précédente, `$width` la largeur de la fenêtre du navigateur,
	 * `$scrollbar` la largeur de la barre de défilement du panneau.
	 */
	private function place(string $kind, array $main, array $node, int $current = 0, ?array $panel = NULL, int $width = 1199, bool $phone = FALSE, int $scrollbar = 0): array {

		$source = $this->getSource();

		$duration = $this->getClass($source, 'PlanningDuration');
		$step = $this->getClass($source, 'PlanningDurationStep');

		if($this->assertDecorPresent($duration !== NULL and $step !== NULL, 'planning.js déclare PlanningDuration et PlanningDurationStep') === FALSE) {
			return ['shift' => NULL, 'bounds' => NULL, 'sheet' => NULL];
		}

		$script = '
			const vm = require("vm");

			const box = (left, right, top, bottom) => ({left, right, top, bottom, width: right - left, height: bottom - top});

			const makeStyle = current => {
				const properties = new Map(current === 0 ? [] : [["--planning-duration-shift", current +"px"]]);
				return {
					getPropertyValue: name => properties.get(name) ?? "",
					setProperty: (name, value) => properties.set(name, value),
					removeProperty: name => properties.delete(name),
					properties
				};
			};

			const makeClassList = () => {
				const set = new Set();
				return {contains: name => set.has(name), add: (...list) => list.forEach(name => set.add(name)), remove: (...list) => list.forEach(name => set.delete(name)), toggle: (name, force) => force ? set.add(name) : set.delete(name)};
			};

			const nodeLeft = '.(int)$node[0].';
			const nodeRight = '.(int)$node[1].';

			const panelNode = '.json_encode($panel !== NULL).' ? {getBoundingClientRect: () => box('.(int)($panel[0] ?? 0).', '.(int)($panel[1] ?? 0).', 0, 800), clientLeft: 0, clientWidth: '.((int)($panel[1] ?? 0) - (int)($panel[0] ?? 0) - $scrollbar).', qs: selector => (selector === ".panel-header") ? {getBoundingClientRect: () => box(0, 0, 0, 60)} : null} : null;
			const main = {getBoundingClientRect: () => box('.(int)$main[0].', '.(int)$main[1].', 0, 2000), clientLeft: 0, clientWidth: '.((int)$main[1] - (int)$main[0]).'};
			const row = {getBoundingClientRect: () => box(0, 1000, 300, 340)};
			const table = {getBoundingClientRect: () => box(0, 1000, 100, 900)};

			const node = {
				classList: makeClassList(),
				style: makeStyle('.$current.'),
				parentElement: {classList: makeClassList()},
				offsetParent: row,
				closest: selector => {
					if(selector === ".panel") {
						return panelNode;
					}
					if(selector.startsWith(".planning-time-row")) {
						return row;
					}
					if(selector.startsWith(".planning-time-table")) {
						return table;
					}
					return null;
				}
			};
			node.getBoundingClientRect = () => {
				const shift = parseFloat(node.style.getPropertyValue("--planning-duration-shift")) || 0;
				return box(nodeLeft + shift, nodeRight + shift, 350, 450);
			};

			const tokens = {"--planning-time-panel-gap": "0.5", "--space-xs": "0.5rem", "--mainSticky": "0", "--nav-main-sticked": "0", "--nav-structure": "0", "--nav-main": "0"};

			const context = vm.createContext({
				rem: () => 16,
				qs: selector => (selector === "body > main") ? main : null,
				qsa: () => undefined,
				getComputedStyle: () => ({overflowY: "visible", getPropertyValue: name => tokens[name] ?? ""}),
				window: {innerHeight: 800, matchMedia: () => ({matches: '.json_encode($phone).'}), scrollBy: () => {}},
				document: {documentElement: {clientWidth: '.$width.'}},
				Structure: {hasBar: () => false},
				node
			});

			vm.runInContext('.json_encode($duration."\n".$step).' + '.json_encode("\n".($kind === 'panel' ? 'PlanningDuration' : 'PlanningDurationStep').'.place(node);').', context);

			const bounds = node.getBoundingClientRect();

			process.stdout.write(JSON.stringify({
				shift: node.style.properties.get("--planning-duration-shift") ?? null,
				bounds: [bounds.left, bounds.right],
				sheet: node.classList.contains("is-sheet")
			}));
		';

		return $this->runNode($script);

	}

	/**
	 * Le tiroir de Récoltes à 360×700 (relecteur de P, point 2) : la fenêtre rendue au flux s'ouvre sur sa ligne,
	 * sous la barre du pouce et le rail. La page défile jusqu'à montrer son bas, sa tuile restant sous le titre.
	 */
	public function testFlowScrollsPage(): void {

		$result = $this->placeVertical(
			height: 700,
			bars: [[0, 0, 360, 44], [0, 548, 360, 600], [0, 600, 360, 700]],
			title: [0, 44, 360, 137],
			row: [18, 561, 327, 710],
			table: [18, 400, 327, 720],
			window: 101,
			flow: [18, 609, 327, 710]
		);

		$this->assertEquals(162, $result['scroll'], 'la page ne défile pas jusqu\'au bas de la fenêtre du tiroir');
		$this->assertFalse($result['up'], 'la fenêtre rendue au flux se déplace');

	}

	/**
	 * Ouvre une fenêtre des pas haute de `$window` sous sa ligne (`$row`), dans sa table (`$table`), sur un écran
	 * haut de `$height`, et rend où elle se pose. Les rectangles vont de gauche à droite puis de haut en bas,
	 * comme `constat.md` de P les a relevés dans Chrome :
	 * - `$bars` : les barres fixes du gabarit (`body > header`, la barre du haut, le rail) ;
	 * - `$title`, `$head` : le titre de la page et l'en-tête de la table, collants ;
	 * - `$panel`, `$panelHead` : le panneau qui porte la fenêtre et son en-tête ;
	 * - `$flow` : la fenêtre rendue au flux, du tiroir sous 768 ;
	 * - `$cell` : la case qui porte la tuile, donnée quand la table coupe ce qui la dépasse ; sans elle, la table
	 *   laisse tout voir.
	 */
	private function placeVertical(int $height, array $row, array $table, int $window, array $bars = [], ?array $title = NULL, ?array $head = NULL, ?array $panel = NULL, ?array $panelHead = NULL, ?array $flow = NULL, bool $drawer = FALSE, ?array $cell = NULL): array {

		$source = $this->getSource();

		$duration = $this->getClass($source, 'PlanningDuration');
		$step = $this->getClass($source, 'PlanningDurationStep');

		if($this->assertDecorPresent($duration !== NULL and $step !== NULL, 'planning.js déclare PlanningDuration et PlanningDurationStep') === FALSE) {
			return ['up' => NULL, 'over' => NULL, 'scroll' => NULL];
		}

		$script = '
			const vm = require("vm");

			const box = ([left, top, right, bottom]) => ({left, right, top, bottom, width: right - left, height: bottom - top});
			const element = (bounds, position, more = {}) => ({getBoundingClientRect: () => box(bounds), position, ...more});

			const classes = new Set();
			const scrolled = [];
			const properties = new Map();

			const row = element('.json_encode($row).');
			const cell = '.json_encode($cell).' === null ? null : element('.json_encode($cell).');
			const table = element('.json_encode($table).', "static", {overflowY: (cell === null) ? "visible" : "auto"});
			const title = '.json_encode($title).' === null ? null : element('.json_encode($title).', "sticky");
			const head = '.json_encode($head).' === null ? null : element('.json_encode($head).', "sticky");
			const utilTable = (head === null) ? null : {qs: selector => (selector === ":scope > .util-table-head") ? head : null};
			const bars = '.json_encode($bars).'.map(bounds => element(bounds, "fixed"));
			const panel = '.json_encode($panel).' === null ? null : element('.json_encode($panel).', "fixed", {clientLeft: 0, clientWidth: '.(int)(($panel[2] ?? 0) - ($panel[0] ?? 0)).', qs: selector => (selector === ".panel-header") ? element('.json_encode($panelHead).') : null});
			const flow = '.json_encode($flow).';
			const drawer = '.json_encode($drawer).' ? {} : null;
			const tile = element('.json_encode($row).');

			const rowBounds = box('.json_encode($row).');
			const below = [rowBounds.left, rowBounds.bottom + 6, rowBounds.left + 364, rowBounds.bottom + 6 + '.$window.'];

			const node = {
				classList: {contains: name => classes.has(name), add: (...list) => list.forEach(name => classes.add(name)), remove: (...list) => list.forEach(name => classes.delete(name)), toggle: (name, force) => force ? classes.add(name) : classes.delete(name)},
				style: {getPropertyValue: name => properties.get(name) ?? "", setProperty: (name, value) => properties.set(name, value), removeProperty: name => properties.delete(name)},
				position: (flow === null) ? "absolute" : "static",
				previousElementSibling: tile,
				parentElement: cell,
				getBoundingClientRect: () => box(flow ?? below),
				closest: selector => {
					const found = {".panel": panel, ".util-table": utilTable, ".planning-harvest-drawer": drawer};
					if(selector in found) {
						return found[selector];
					}
					if(selector.startsWith(".planning-time-row")) {
						return row;
					}
					if(selector.startsWith(".planning-time-table")) {
						return table;
					}
					return null;
				}
			};

			const main = element([0, 0, 1199, 2000], "static", {clientLeft: 0, clientWidth: 1199});
			const tokens = {"--space-xs": "0.5rem", "--mainSticky": "0", "--nav-main-sticked": "0", "--nav-structure": "3.25rem", "--nav-main": "3rem"};

			const context = vm.createContext({
				rem: () => 16,
				qs: selector => ({"body > main": main, ".structure-template-main-title": title})[selector] ?? null,
				qsa: (selector, each) => selector.includes("body > header") ? bars.forEach(each) : undefined,
				getComputedStyle: target => ({position: target?.position ?? "static", overflowY: target?.overflowY ?? "visible", getPropertyValue: name => tokens[name] ?? ""}),
				window: {innerHeight: '.$height.', matchMedia: () => ({matches: false}), scrollBy: (x, y) => scrolled.push(y)},
				document: {documentElement: {clientWidth: 1199}},
				node
			});

			vm.runInContext('.json_encode($duration."\n".$step."\nPlanningDurationStep.place(node);").', context);

			process.stdout.write(JSON.stringify({
				up: classes.has("is-up"),
				over: classes.has("is-over"),
				scroll: scrolled.length === 0 ? null : Math.round(scrolled[0]),
				drop: properties.get("--planning-duration-step-drop") ?? null,
				fixed: classes.has("is-fixed"),
				cell: ["top", "bottom", "right"].map(side => properties.get("--planning-duration-step-cell-" + side) ?? null)
			}));
		';

		return $this->runNode($script) + ['up' => NULL, 'over' => NULL, 'scroll' => NULL, 'drop' => NULL, 'fixed' => NULL, 'cell' => NULL];

	}

	/**
	 * Fait défiler la grille de la Semaine sous une fenêtre `is-fixed` ouverte à 360 de large
	 * (`PlanningDurationStep.follow()`), et rend où elle se pose et si elle se ferme. Avant le défilement, la case
	 * finissait à 106 et la fenêtre, large de 278, allait de 6 à 284, décalée de 178. `$cell` est la case après
	 * le défilement, de gauche à droite ; la grille va de 6 à 338, et `main` de 0 à 360.
	 */
	private function follow(array $cell): array {

		$source = $this->getSource();

		$duration = $this->getClass($source, 'PlanningDuration');
		$step = $this->getClass($source, 'PlanningDurationStep');

		if($this->assertDecorPresent($duration !== NULL and $step !== NULL, 'planning.js déclare PlanningDuration et PlanningDurationStep') === FALSE) {
			return ['closed' => NULL, 'right' => NULL, 'bounds' => NULL];
		}

		$script = '
			const vm = require("vm");

			const box = (left, right, top, bottom) => ({left, right, top, bottom, width: right - left, height: bottom - top});

			const properties = new Map([["--planning-duration-shift", "178px"], ["--planning-duration-step-cell-right", "106px"]]);
			const closed = [];

			const main = {getBoundingClientRect: () => box(0, 360, 0, 900), clientLeft: 0, clientWidth: 360};
			const table = {getBoundingClientRect: () => box(6, 338, 301, 634)};
			const cell = {getBoundingClientRect: () => box('.(int)$cell[0].', '.(int)$cell[1].', 551, 593)};
			const field = {};

			const stepWindow = {
				style: {getPropertyValue: name => properties.get(name) ?? "", setProperty: (name, value) => properties.set(name, value), removeProperty: name => properties.delete(name)},
				parentElement: cell,
				qs: selector => (selector === ".planning-duration-step") ? field : null,
				closest: selector => selector.includes("#planning-grid") ? table : null
			};
			stepWindow.getBoundingClientRect = () => {
				const right = parseFloat(properties.get("--planning-duration-step-cell-right")) + (parseFloat(properties.get("--planning-duration-shift")) || 0);
				return box(right - 278, right, 599, 699);
			};

			const tokens = {"--space-xs": "0.5rem"};

			const context = vm.createContext({
				rem: () => 16,
				qs: selector => ({"body > main": main, ".planning-duration-step-window.is-open.is-fixed": stepWindow})[selector] ?? null,
				getComputedStyle: () => ({getPropertyValue: name => tokens[name] ?? ""}),
				window: {innerHeight: 900},
				document: {documentElement: {clientWidth: 360}},
				closed
			});

			vm.runInContext('.json_encode($duration."\n".$step."\nPlanningDurationStep.close = field => closed.push(field);\nPlanningDurationStep.follow();").', context);

			const bounds = stepWindow.getBoundingClientRect();

			process.stdout.write(JSON.stringify({
				closed: closed.length === 1 && closed[0] === field,
				right: properties.get("--planning-duration-step-cell-right") ?? null,
				bounds: [Math.round(bounds.left), Math.round(bounds.right)]
			}));
		';

		return $this->runNode($script) + ['closed' => NULL, 'right' => NULL, 'bounds' => NULL];

	}

	/**
	 * Ouvre le tiroir de récolte d'une ligne (`PlanningHarvestDrawer.start()`), ou le repose comme au
	 * redimensionnement (`replace()`, `$resize`), sur un écran haut de `$height`, et rend de combien la page
	 * défile et où se pose la pointe. Les rectangles se lisent comme ceux de `placeVertical()` : `$row` est la
	 * ligne de la culture, tiroir compris, qui s'y pose en dernier, `$drawer` le tiroir et `$form` son
	 * formulaire, qui le remplit quand il est absent. `$part` est la part qui a ouvert le tiroir, posé juste
	 * après elle ; sans part, c'est la ligne qui l'a ouvert. `$head` est l'en-tête de la liste, qui colle à 44 px du
	 * haut. La colonne du jour commence au bord gauche du tiroir.
	 */
	private function openDrawer(int $height, array $row, array $drawer, ?array $form = NULL, ?array $part = NULL, array $bars = [], ?array $title = NULL, ?array $head = NULL, bool $resize = FALSE): array {

		$source = $this->getSource();

		$duration = $this->getClass($source, 'PlanningDuration');
		$step = $this->getClass($source, 'PlanningDurationStep');
		$harvestDrawer = $this->getClass($source, 'PlanningHarvestDrawer');

		if($this->assertDecorPresent($duration !== NULL and $step !== NULL and $harvestDrawer !== NULL, 'planning.js déclare PlanningDuration, PlanningDurationStep et PlanningHarvestDrawer') === FALSE) {
			return ['scroll' => NULL, 'center' => NULL];
		}

		$script = '
			const vm = require("vm");

			const box = ([left, top, right, bottom]) => ({left, right, top, bottom, width: right - left, height: bottom - top});
			const element = (bounds, position, more = {}) => ({getBoundingClientRect: () => box(bounds), position, ...more});

			const scrolled = [];
			const properties = new Map();

			const row = element('.json_encode($row).', "static");
			const title = '.json_encode($title).' === null ? null : element('.json_encode($title).', "sticky");
			const bars = '.json_encode($bars).'.map(bounds => element(bounds, "fixed"));
			const day = element('.json_encode($drawer).', "static");
			// Sans liste des unités, `start()` ne trouve rien à éteindre (`PlanningHarvestCrate.allow()`, lot GN).
			const form = element('.json_encode($form ?? $drawer).', "absolute", {qs: () => null, elements: {mode: {value: "add"}}});
			const head = '.json_encode($head).' === null ? null : element('.json_encode($head).', "sticky", {top: "44px"});
			const table = {qs: selector => (selector === ":scope > .util-table-head") ? head : null};
			const part = '.json_encode($part).' === null ? null : element('.json_encode($part).', "static");
			const previous = part ?? element([0, 0, 0, 0], "static");
			previous.matches = selector => (selector === ".planning-harvest-open" && part !== null);

			const drawer = element('.json_encode($drawer).', "static", {
				isConnected: true,
				parentNode: row,
				previousElementSibling: previous,
				style: {setProperty: (name, value) => properties.set(name, value)},
				qs: selector => ({":scope > .planning-harvest-drawer-day": day, ":scope > .planning-harvest-drawer-form": form})[selector] ?? null,
				closest: selector => ({".util-table-row": row, ".panel": null, ".util-table": table})[selector] ?? null
			});

			const tokens = {"--space-xs": "0.5rem", "--mainSticky": "0", "--nav-main-sticked": "0", "--nav-structure": "3.25rem", "--nav-main": "3rem"};

			const context = vm.createContext({
				rem: () => 16,
				qs: selector => ({".structure-template-main-title": title})[selector] ?? null,
				qsa: (selector, each) => {
					if(selector.includes("body > header")) {
						bars.forEach(each);
					} else if(selector.includes("planning-harvest-drawer")) {
						each(drawer);
					}
				},
				getComputedStyle: target => ({position: target?.position ?? "static", top: target?.top ?? "auto", getPropertyValue: name => tokens[name] ?? ""}),
				window: {innerHeight: '.$height.', matchMedia: () => ({matches: false}), scrollBy: (x, y) => scrolled.push(y)},
				document: {documentElement: {clientWidth: 1199}},
				ResizeObserver: class { observe() {} },
				drawer
			});

			vm.runInContext('.json_encode($duration."\n".$step."\n".$harvestDrawer."\n".($resize ? 'PlanningHarvestDrawer.replace();' : 'PlanningHarvestDrawer.start(drawer);')).', context);

			process.stdout.write(JSON.stringify({
				scroll: scrolled.length === 0 ? null : Math.round(scrolled[0]),
				center: properties.get("--planning-harvest-drawer-center") ?? null
			}));
		';

		return $this->runNode($script) + ['scroll' => NULL, 'center' => NULL];

	}

	private function runNode(string $script): array {

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? ['shift' => NULL, 'bounds' => NULL, 'sheet' => NULL];

	}

}
?>
