<?php
namespace toolbox;

/**
 * Le plan de paniers d'une structure.
 *
 * Un plan par structure, ce que porte son `UNIQUE` : les paniers ne se rangent pas par saison. La
 * saison du plan ne dit que le plan de culture qu'il suit, et en changer repart du départ
 * (`getForStart()`), puisque les espèces et les semaines en dépendent. Il ne contient que les trois
 * réponses du questionnaire — le prix vit sur la formule, les semaines livrées et les espèces
 * refusées vivent ici — et tout le reste est la proposition, écrite sous lui.
 */
class CompositionPlanLib extends CompositionPlanCrud {

	/**
	 * Les semaines au plus que la présentation montre du rang de son étape « Vous choisissez le
	 * rythme » (`getNotice()`) : assez pour voir une espèce revenir, assez peu pour qu'une case reste
	 * lisible à 360 px.
	 */
	const NOTICE_WEEKS = 16;

	/**
	 * La saison, en semaines, en deçà de laquelle une espèce passe devant les autres dans l'étape
	 * « Vous cultivez » de la présentation (`getNotice()`) : une demi-année.
	 */
	const NOTICE_SEASON = 26;

	/**
	 * Le départ poste d'où partent les saisons (r48b) : `reference`, la tuile choisie, le calendrier
	 * type ou le plan de culture, et `climate`, le climat du calendrier. Un plan neuf ne suit donc
	 * plus la communauté, que le départ ne propose pas (`CompositionPlan::properties()`,
	 * `reference.start`) ; après le départ, seule la synchronisation sur le plan de culture change
	 * `reference` (`synchronize()`, lot r27).
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['structure', 'season', 'weeks', 'reference', 'climate'];
	}

	/**
	 * Le climat s'écrit aussi après le départ (r48c) : « Ajouter une espèce » le demande à un plan qui
	 * n'en a pas, pour dater l'espèce sur le calendrier type
	 * (`CompositionSpeciesLib::isClimateAsked()`).
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['weeks', 'climate'];
	}

	/**
	 * L'onglet de l'écran (`CompositionPlan::TAB_*`) que nomme `$origin`, tel qu'une adresse ou un
	 * envoi le portent : celui d'une taille pour toute autre valeur, absente comprise. Un geste dit
	 * l'onglet d'où il part, et sa réponse rend le même.
	 */
	public static function getTab(?string $origin): string {

		return match($origin) {
			CompositionPlan::TAB_SYNTHESIS, CompositionPlan::TAB_SPECIES => $origin,
			default => CompositionPlan::TAB_FORMULA,
		};

	}

	public static function getByStructure(\structure\Structure $eStructure): CompositionPlan {

		return CompositionPlan::model()
			->select(CompositionPlan::getSelection())
			->whereStructure($eStructure)
			->get();

	}

	/**
	 * Tout ce que l'écran « Paniers de l'année » lit, écrit sur le plan de la
	 * structure ; vide tant qu'elle n'a pas de plan, et l'écran ouvre alors le départ
	 * (`getForStart()`). En plus de ses colonnes :
	 *
	 * - `cCompositionFormula` : ses tailles de panier, chacune avec ses semaines et leurs lignes
	 *   (`cCompositionWeek`), `summary` (`CompositionLib::getSummary()`) et son chiffre d'affaires de
	 *   l'année (`turnover`, `CompositionLib::getTurnover()`) ;
	 * - `eCompositionFormula` : celle qu'on lit, `$formulaId` s'il désigne une taille du plan et la
	 *   première sinon, avec en plus sa grille (`grid`,
	 *   `CompositionLib::getGrid()`, fenêtre et part de chaque espèce comprises), les lignes de ses
	 *   semaines rangées dans l'ordre de cette grille (`CompositionWeekLib::sortByGrid()`), et ce que
	 *   le rejeu lui ferait perdre (`weeksAmended`, `formulasOther`,
	 *   `CompositionFormulaLib::applyRecompose()`) ;
	 * - `bands` et `seasonWeeks`, qui dessinent la frise de l'année ;
	 * - `weekMonths`, le mois de chaque semaine de la saison, qui dessine l'axe de la grille ;
	 * - `quantities`, l'année en quantités, toutes tailles additionnées
	 *   (`CompositionLib::getQuantities()`) ;
	 * - `week`, `$week` quand c'est une semaine livrée, `NULL` sinon : la semaine d'une case dont on
	 *   ouvre le menu (lot r24), ou d'un geste qui le rend de nouveau (`compositionLine.p.php`). Chaque
	 *   rang de la grille porte alors la ligne que sa case de cette semaine peut recevoir
	 *   (`CompositionLib::applyAddableToGrid()`), que le menu propose dans son champ
	 *   (`CompositionUi::getCellMenu()`).
	 *
	 * La colonne ouverte des lots r8 à r8d, qui vivait dans la page (`data-opened`) et que chaque geste
	 * postait, est partie au lot r24 (retour du 27/09, « il n'y a plus de modification en
	 * colonne ») : une case se règle dans son propre menu, et rien de l'écran ne dépend plus d'une
	 * semaine choisie.
	 *
	 * La grille montre toute l'année (lot r28, retour du 28/09, qui retire les onglets de
	 * période du lot r16) : `monthStarts` sont les semaines de la saison qui ouvrent un mois, devant
	 * lesquelles elle sépare les mois (lot r21).
	 *
	 * La comparaison au plan de culture des lots L5 et r8e est partie au lot r27 : la saison de vente
	 * de chaque espèce se règle sur la grille, et la synchronisation reprend celle du plan de culture.
	 *
	 * Chaque rang de la grille porte en plus son espèce du plan et son prix (`eCompositionSpecies`,
	 * `CompositionSpeciesLib::applyToGrid()`), que le menu du nom affiche et règle, et chaque rang et
	 * chaque groupe ce que la taille en sert sur l'année, en kilos et en euros (`sum`,
	 * `CompositionLib::applySumsToGrid()`), dont la taille porte le total (`gridSum`,
	 * `CompositionLib::getGridSum()`).
	 *
	 * La page et les réponses des trois panneaux le lisent tous par ici : une réponse ciblée ne doit
	 * pas rendre un bloc que la page aurait rendu autrement.
	 *
	 * L'onglet « Synthèse » (`$tab`, `TAB_SYNTHESIS`) n'affiche aucune taille ouverte :
	 * `eCompositionFormula` y est la taille `$formulaId` ou la première, sans grille, soudure, rejeu ni
	 * base (`applyOpened()`), donc sans case dont ouvrir le menu (`$week`) (chantier Performances, P11).
	 * Ce qu'il affiche se lit comme pour un onglet de taille :
	 * `CompositionPanelTest::testSynthesisReadsLess()` compare les deux rendus.
	 *
	 * L'onglet « Espèces » (`TAB_SPECIES`, r48a) n'affiche ni taille ouverte ni quantités de l'année.
	 * Il lit à leur place :
	 * - `cCompositionSpecies` : les espèces livrées dans l'ordre alphabétique, chacune avec son prix,
	 *   sa quantité au panier et sa saison en douze mois (`CompositionSpeciesLib::getForTab()`) ;
	 * - `synchronization` : ce que « Appliquer le plan de culture » ferait (`getSynchronization()`),
	 *   que le deck en tête de l'onglet montre. Elle se lit à l'ouverture de l'onglet et non sur tout l'écran (r28,
	 *   #77).
	 */
	public static function getForScreen(\structure\Structure $eStructure, ?int $formulaId, ?int $week = NULL, string $tab = CompositionPlan::TAB_FORMULA): CompositionPlan {

		$eCompositionPlan = self::getByStructure($eStructure);

		if($eCompositionPlan->empty()) {
			return $eCompositionPlan;
		}

		$season = $eCompositionPlan['season'];

		// Le droit d'écrire une taille se lit sur la structure de son plan : c'est celle que
		// l'appelant a déjà validée.
		$eCompositionPlan['structure'] = $eStructure;

		// Les espèces du plan se lisent une fois, et chaque lecture de l'écran en dérive ce qu'elle
		// lisait seule : fenêtres, unités, prix tapés, pas, compte et espèces cultivées, espèces
		// chiffrées (chantier Performances, P7). Rien ne s'écrit pendant l'écran.
		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		// Les cultures des saisons S-1 et S de la structure, de même : poids des cultures, espèces hors
		// panier, espèces chiffrées, stock et semaines de récolte de la soudure les relisaient chacun
		// (chantier Performances, P8).
		$cultivations = CompositionLib::getCultivations($eStructure, $season);

		$windows = CompositionSpeciesLib::getWindows($cCompositionSpecies);
		$source = CompositionLib::getSourceByPlan($eCompositionPlan, $windows, $cCompositionSpecies, $cultivations);

		// Les tailles du plan et leurs semaines se lisent une fois, avant tout ce qui les parcourt : ce
		// que le rejeu fait perdre et le stock de la soudure, qui lisaient chacun les leurs (chantier
		// Performances, P6). Rien ne s'écrit pendant l'écran.
		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		foreach($cCompositionFormula as $eCompositionFormula) {

			$cCompositionWeek = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies);

			$eCompositionFormula['summary'] = CompositionLib::getSummary($cCompositionWeek);
			$eCompositionFormula['turnover'] = CompositionLib::getTurnover($cCompositionWeek, $eCompositionFormula['baskets']);
			$eCompositionFormula['cCompositionWeek'] = $cCompositionWeek;

		}

		$eCompositionPlan['cCompositionFormula'] = $cCompositionFormula;
		$eCompositionPlan['bands'] = CompositionLib::getBands($eCompositionPlan['weeks']);
		$eCompositionPlan['seasonWeeks'] = CompositionLib::getSeasonWeeks();
		$eCompositionPlan['weekMonths'] = CompositionLib::getSeasonWeekMonths($season);

		$eCompositionPlan['eCompositionFormula'] = $cCompositionFormula->offsetExists($formulaId) ?
			$cCompositionFormula[$formulaId] :
			$cCompositionFormula->first();

		$steps = CompositionSpeciesLib::getSteps($cCompositionSpecies);

		$cCompositionSpeciesPriced = CompositionSpeciesLib::getWithPrices($eCompositionPlan, $source, $cCompositionSpecies, $cultivations);

		if($tab === CompositionPlan::TAB_SPECIES) {

			$cCompositionSpeciesTab = CompositionSpeciesLib::getForTab($eCompositionPlan, $source, $cCompositionSpeciesPriced, $cultivations);

			// Une espèce retirée n'a plus de rang : elle revient par « Ajouter une espèce » (r48c).
			$eCompositionPlan['cCompositionSpecies'] = $cCompositionSpeciesTab->find(fn(CompositionSpecies $e) => $e['status'] !== CompositionSpecies::EXCLUDED);
			$eCompositionPlan['synchronization'] = self::getSynchronization($eCompositionPlan, $eCompositionPlan['cCompositionSpecies']);

			return $eCompositionPlan;

		}

		if($tab === CompositionPlan::TAB_FORMULA) {

			self::applyOpened($eCompositionPlan, $windows, $source, $steps, $cCompositionSpecies, $cCompositionSpeciesPriced, $cultivations);

			// La porte « Mettre en vente » des cartes de semaine (r37b) : les produits composés qu'elle
			// propose, et pour chaque semaine si elle mène droit au panneau de composition.
			$eCompositionPlan['cProductComposition'] = CompositionSaleLib::getCompositions($eStructure);
			CompositionSaleLib::applyRetained($eStructure, $eCompositionPlan['eCompositionFormula']['cCompositionWeek'], $cCompositionSpecies);

		}

		$eCompositionPlan['quantities'] = CompositionSpeciesLib::applyToQuantities(
			CompositionLib::applyMonthsToQuantities(CompositionLib::getQuantities($eCompositionPlan, CompositionSpeciesLib::getGrown($cCompositionSpecies)), $eCompositionPlan),
			$cCompositionSpeciesPriced
		);

		$eCompositionPlan['monthStarts'] = CompositionLib::getMonthStarts($eCompositionPlan['weekMonths']);

		$eCompositionPlan['week'] = in_array($week, $eCompositionPlan['weeks'], TRUE) ? $week : NULL;

		if($eCompositionPlan['week'] !== NULL) {
			$eCompositionPlan['eCompositionFormula']['grid'] = CompositionLib::applyAddableToGrid($eCompositionPlan['eCompositionFormula']['grid'], $eCompositionPlan['week'], $source, $steps);
		}

		return $eCompositionPlan;

	}

	/**
	 * Ce que seul l'onglet de la taille ouverte affiche (`getForScreen()`) : sa grille, sa soudure,
	 * ses lignes rangées et leurs pas, ce que le rejeu lui ferait perdre, et sa base. La synthèse n'en
	 * affiche rien, et s'en passe (chantier Performances, P11).
	 */
	private static function applyOpened(CompositionPlan $eCompositionPlan, array $windows, array $source, array $steps, \Collection $cCompositionSpecies, \Collection $cCompositionSpeciesPriced, array $cultivations): void {

		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];
		$eCompositionFormulaOpened = $eCompositionPlan['eCompositionFormula'];
		$cCompositionWeek = $eCompositionFormulaOpened['cCompositionWeek'];

		CompositionFormulaLib::applyRecompose($eCompositionFormulaOpened, $cCompositionFormula, $cCompositionWeek->find(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['amended']));

		// Seule la taille ouverte a sa grille, sa soudure et ses lignes rangées : les autres onglets
		// n'affichent que leur synthèse.
		$eCompositionFormulaOpened['lean'] = CompositionLib::getLean($eCompositionPlan, $eCompositionFormulaOpened, $windows, $source, $steps, $cCompositionSpeciesPriced, $cultivations);
		$grid = CompositionLib::getGrid($cCompositionWeek, $eCompositionPlan['season'], $windows, $source, $cCompositionSpecies);
		$eCompositionFormulaOpened['grid'] = CompositionLib::applySumsToGrid(CompositionSpeciesLib::applyToGrid($grid, $cCompositionSpeciesPriced), $eCompositionFormulaOpened['baskets'], $source, $cCompositionSpeciesPriced);
		$eCompositionFormulaOpened['gridSum'] = CompositionLib::getGridSum($eCompositionFormulaOpened['grid']);
		CompositionWeekLib::sortByGrid($cCompositionWeek, $grid);
		CompositionWeekLib::applySteps($cCompositionWeek, $steps, $source);

		// La base de chaque taille (lot r17), que l'annotation du prix nomme (r31, #97), et ce que les
		// semaines de la taille ouverte lui doivent (`CompositionWeekLib::applyBase()`).
		foreach($cCompositionFormula as $eCompositionFormula) {
			$eCompositionFormula['eCompositionFormulaBase'] = $eCompositionFormula['base']->empty() ? new CompositionFormula() : $cCompositionFormula[$eCompositionFormula['base']['id']];
			$eCompositionFormula['linesAdded'] = NULL;
		}

		if($eCompositionFormulaOpened['eCompositionFormulaBase']->notEmpty()) {
			$eCompositionFormulaOpened['linesAdded'] = CompositionWeekLib::applyBase($eCompositionFormulaOpened['cCompositionWeek'], $eCompositionFormulaOpened['eCompositionFormulaBase']);
		}

	}

	/**
	 * Ce que le menu d'une case lit (`CompositionUi::getCellMenu()`, `compositionLine:getMenu`), sur
	 * le plan de la semaine `$eCompositionWeek` : la taille de la semaine en `eCompositionFormula`, avec
	 * sa grille, dont chaque ligne porte ses deux quantités voisines (`CompositionWeekLib::applySteps()`)
	 * et chaque rang ce que sa case de la semaine peut recevoir (`CompositionLib::applyAddableToGrid()`),
	 * et `week`, comme `getForScreen()` les pose.
	 *
	 * Le menu est le geste le plus fréquent de l'écran, et `getForScreen()` lui faisait lire l'écran
	 * entier, toutes tailles, soudure, synthèse et pertes comprises, pour 3 Ko de balisage (chantier
	 * Performances, P4). Ce qui reste est ce dont la case dépend. La grille se construit encore sur
	 * toutes les semaines de la taille : c'est elle qui décide qu'une espèce a un rang (servie une
	 * semaine, candidate une autre, ou réglée sur une saison vide), et une espèce sans rang rend un menu
	 * vide. La lire sur la seule semaine de la case ouvrirait le menu d'une espèce que la grille n'a
	 * pas, ou le fermerait sur une espèce qu'elle a.
	 *
	 * `CompositionPanelTest::testCellMenuReadsLess()` compare chaque menu de l'écran à celui que
	 * `getForScreen()` rend : les deux lectures doivent rester d'accord.
	 *
	 * La semaine porte sa taille, et la taille son plan et la structure de celui-ci
	 * (`compositionLine.p.php`) : c'est sur eux que se lit le droit d'écrire le plan et chaque ligne.
	 */
	public static function getForCellMenu(CompositionWeek $eCompositionWeek): CompositionPlan {

		$eCompositionWeek->expects([
			'week',
			'formula' => ['plan' => ['structure', 'season', 'weeks']]
		]);

		$eCompositionFormula = $eCompositionWeek['formula'];
		$eCompositionPlan = $eCompositionFormula['plan'];

		// Les espèces du plan se lisent une fois, comme pour l'écran (chantier Performances, P7).
		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$windows = CompositionSpeciesLib::getWindows($cCompositionSpecies);
		$source = CompositionLib::getSourceByPlan($eCompositionPlan, $windows, $cCompositionSpecies);
		$steps = CompositionSpeciesLib::getSteps($cCompositionSpecies);

		$cCompositionWeek = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies);
		CompositionWeekLib::applySteps($cCompositionWeek, $steps, $source);

		$eCompositionFormula['grid'] = CompositionLib::getGrid($cCompositionWeek, $eCompositionPlan['season'], $windows, $source, $cCompositionSpecies);

		$eCompositionPlan['eCompositionFormula'] = $eCompositionFormula;
		$eCompositionPlan['week'] = in_array($eCompositionWeek['week'], $eCompositionPlan['weeks'], TRUE) ? $eCompositionWeek['week'] : NULL;

		if($eCompositionPlan['week'] !== NULL) {
			$eCompositionFormula['grid'] = CompositionLib::applyAddableToGrid($eCompositionFormula['grid'], $eCompositionPlan['week'], $source, $steps);
		}

		return $eCompositionPlan;

	}

	/**
	 * Ce que « Appliquer le plan de culture » fera (lot r27, r51b), que le deck « Plan de culture » de
	 * l'onglet « Espèces » montre avant l'envoi (planche r51, piste B), sur les espèces
	 * `$cCompositionSpecies` du plan, celles de l'onglet (`CompositionSpeciesLib::getForTab()`) :
	 *
	 * - `cCompositionSpecies` : les espèces que le plan de culture date à d'autres mois que ceux que
	 *   les paniers dessinent, dans l'ordre reçu et indexées par espèce, chacune avec
	 *   `monthsCultivation`, la saison qu'il lui donnerait, en douze mois comme `months` ;
	 * - `plants` : parmi elles, le nombre de celles dont personne n'a choisi les mois, cochées dans
	 *   le deck et comptées par son titre ;
	 * - `custom` : le nombre des autres, réglées à la main, décochées.
	 *
	 * Sans `plants`, le deck ne paraît pas : une saison sans plan de culture n'a rien à dater.
	 *
	 * La liste se fait sur les mois dessinés et non sur les semaines (`getSynchronized()`, `drawn`) :
	 * une espèce qui ne bouge que d'une semaine dans le même mois montrerait deux dessins identiques.
	 *
	 * Elle se lit à l'ouverture de l'onglet « Espèces » et non sur tout l'écran (r28, #77) : la
	 * dater demande les semaines de récolte de tout le plan de culture. La conservation se lit comme
	 * `synchronize()` la lit, sur les espèces du plan, pour que le deck dise ce que l'envoi fera.
	 */
	public static function getSynchronization(CompositionPlan $eCompositionPlan, \Collection $cCompositionSpecies): array {

		$eCompositionPlan->expects(['id', 'structure', 'season']);

		$synchronized = self::getSynchronized($eCompositionPlan, $cCompositionSpecies);

		$plants = 0;
		$custom = 0;
		$cCompositionSpeciesListed = new \Collection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			if(array_key_exists($speciesId, $synchronized) === FALSE or $synchronized[$speciesId]['drawn'] === FALSE) {
				continue;
			}

			$eCompositionSpecies['monthsCultivation'] = $synchronized[$speciesId]['months'];

			$cCompositionSpeciesListed[$speciesId] = $eCompositionSpecies;

			if($eCompositionSpecies['status'] === CompositionSpecies::CUSTOM) {
				$custom++;
			} else {
				$plants++;
			}

		}

		return [
			'cCompositionSpecies' => $cCompositionSpeciesListed,
			'plants' => $plants,
			'custom' => $custom,
		];

	}

	/**
	 * Ce que le plan de culture donne à chacune des espèces `$cCompositionSpecies` dont il change la
	 * saison, `[id d'espèce => ['windowWeeks' => [[début, fin], …], 'months' => [...], 'drawn' => bool]]` :
	 *
	 * - `windowWeeks` : les semaines qu'il lui ouvre (`CompositionLib::getReferenceWindows()`), trous
	 *   d'une semaine bouchés, la saison que `synchronize()` écrit ;
	 * - `months` : cette saison en douze mois (`CompositionLib::getSeasonShares()`), la part que seule
	 *   la conservation ouvre comprise ;
	 * - `drawn` : ses mois ne se dessinent pas comme ceux que l'espèce porte, la hauteur d'une barre
	 *   au moins diffère (r51b). À `FALSE`, la saison ne bouge que de semaines dans les mêmes mois, ou
	 *   l'espèce ne change que d'état.
	 *
	 * Une espèce qu'il ne date pas n'y est pas, ni une espèce retirée, qui n'a pas de saison, ni celle
	 * qui suit déjà ces semaines-là sous `CULTIVATION`. Une variante (r76) prend les semaines de
	 * l'espèce qu'elle lit (`CompositionLib::getReferenceWindows()`).
	 */
	private static function getSynchronized(CompositionPlan $eCompositionPlan, \Collection $cCompositionSpecies): array {

		$plants = CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'];

		$reference = CompositionLib::getReferenceWindows(
			CompositionLib::getHarvestWeeks($eCompositionPlan['structure'], $eCompositionPlan['season']),
			$plants,
			CompositionLib::projectByPlants(CompositionLib::getStorage($eCompositionPlan['structure'], array_values(array_unique($plants))), $plants)
		);

		$seasonMonths = CompositionLib::getSeasonMonths($eCompositionPlan['season']);

		$synchronized = [];

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			if(
				$eCompositionSpecies['status'] === CompositionSpecies::EXCLUDED or
				array_key_exists($speciesId, $reference) === FALSE
			) {
				continue;
			}

			$windowWeeks = CompositionLib::getWindowWeeksFilled(array_keys($reference[$speciesId]));

			if(
				$eCompositionSpecies['status'] === CompositionSpecies::CULTIVATION and
				$eCompositionSpecies['windowWeeks'] === $windowWeeks
			) {
				continue;
			}

			$months = CompositionLib::getSeasonShares($seasonMonths, CompositionLib::getWindowWeeksList($windowWeeks), array_keys($reference[$speciesId], FALSE, TRUE));
			$monthsWritten = CompositionLib::getSeasonShares($seasonMonths, $eCompositionSpecies->getSeasonWeeks(), []);

			$synchronized[$speciesId] = [
				'windowWeeks' => $windowWeeks,
				'months' => $months,
				'drawn' => (array_column($months, 'share') !== array_column($monthsWritten, 'share')),
			];

		}

		return $synchronized;

	}

	/**
	 * Ce que le PDF des paniers de l'année imprime (lot L4) : le plan de la structure avec ses tailles,
	 * chacune avec ses semaines et leurs lignes (`cCompositionWeek`), puis
	 * - `baskets`, le contenu de chaque semaine livrée, toutes tailles côte à côte
	 *   (`CompositionLib::getWeekBaskets()`) ;
	 * - `species`, le nombre d'espèces que l'année sert dans au moins un panier, une espèce et ses
	 *   variantes (r76) comptant pour une.
	 *
	 * Un plan vide quand la structure n'a pas composé ses paniers.
	 */
	public static function getForPdf(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = self::getByStructure($eStructure);

		if($eCompositionPlan->empty()) {
			return $eCompositionPlan;
		}

		$eCompositionPlan['structure'] = $eStructure;

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		foreach($cCompositionFormula as $eCompositionFormula) {
			$eCompositionFormula['cCompositionWeek'] = CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies);
		}

		$eCompositionPlan['cCompositionFormula'] = $cCompositionFormula;
		$eCompositionPlan['baskets'] = CompositionLib::getWeekBaskets($eCompositionPlan);

		$plantIds = [];

		foreach($eCompositionPlan['baskets'] as $rows) {
			foreach($rows as $row) {
				$plantIds[$row['species']['plant']['id']] = TRUE;
			}
		}

		$eCompositionPlan['species'] = count($plantIds);

		return $eCompositionPlan;

	}

	/**
	 * Ce que montrent les cinq étapes de la présentation (r36, planche r36, variante A), choisi sur la
	 * taille que l'écran ouvre en premier d'un plan lu par `getForScreen()` :
	 * - `cultivated`, jusqu'à quatre rangs de saison, étagés d'après leur première semaine de saison,
	 *   et `cultivatedWeeks`, les semaines qui vont de la première à la dernière de leurs saisons.
	 *   Les espèces de saison courte passent devant : une espèce conservée toute l'année remplit son
	 *   rang et ne dit pas « à quelles semaines » ;
	 * - `rhythm`, le rang d'une espèce vendue et `rhythmWeeks`, au plus `NOTICE_WEEKS` semaines de sa
	 *   saison : le rang qui, sur ces semaines, passe le plus souvent d'un panier servi à un panier
	 *   sans elle, celui où l'on voit l'espèce revenir ;
	 * - `filled`, la semaine non corrigée, hors soudure, dont le panier porte le plus d'espèces, la
	 *   première à égalité ;
	 * - `amended`, la première semaine corrigée qui porte un panier ;
	 * - `lean`, la première période de soudure.
	 *
	 * Chacun vaut `NULL` (ou `[]` pour les rangs et les semaines) quand le plan n'a pas de quoi le
	 * montrer : une structure qui n'a pas composé ses paniers, une taille jamais corrigée ou sans
	 * soudure. L'étape garde alors sa phrase, sans illustration, plutôt qu'une illustration inventée
	 * qui décrirait des paniers qui ne sont pas les siens.
	 */
	public static function getNotice(CompositionPlan $eCompositionPlan): array {

		$notice = [
			'cultivated' => [],
			'cultivatedWeeks' => [],
			'rhythm' => NULL,
			'rhythmWeeks' => [],
			'filled' => NULL,
			'amended' => NULL,
			'lean' => NULL,
		];

		if($eCompositionPlan->empty()) {
			return $notice;
		}

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$rows = array_values(array_filter(
			array_merge(...array_column($eCompositionFormula['grid'], 'species')),
			fn(array $row) => $row['weeksSeason'] !== []
		));

		$first = fn(array $row) => array_key_first($row['weeksSeason']);
		$last = fn(array $row) => array_key_last($row['weeksSeason']);

		// Le premier rang à ouvrir sa saison, le dernier, et deux entre eux.
		$rowsShort = array_values(array_filter($rows, fn(array $row) => $last($row) - $first($row) < self::NOTICE_SEASON));
		$rowsCultivated = (count($rowsShort) >= 4) ? $rowsShort : $rows;

		usort($rowsCultivated, fn(array $row1, array $row2) => $first($row1) <=> $first($row2));

		if($rowsCultivated !== []) {

			$position = count($rowsCultivated) - 1;
			$positions = array_unique(array_map(fn(int $step) => (int)round($step * $position / 3), [0, 1, 2, 3]));

			$notice['cultivated'] = array_map(fn(int $position) => $rowsCultivated[$position], $positions);
			$notice['cultivatedWeeks'] = range(min(array_map($first, $notice['cultivated'])), max(array_map($last, $notice['cultivated'])));

		}

		$changes = 0;

		foreach($rows as $row) {

			if($row['eCompositionSpecies']->empty() or $row['eCompositionSpecies']['status'] === CompositionSpecies::EXCLUDED) {
				continue;
			}

			$weeks = range($first($row), min($last($row), $first($row) + self::NOTICE_WEEKS - 1));

			// D'une semaine livrée à la suivante : une semaine non livrée n'a pas de panier à comparer.
			$served = array_map(fn(int $week) => isset($row['lines'][$week]), array_values(array_intersect($weeks, $eCompositionPlan['weeks'])));

			$count = 0;

			foreach(array_keys($served) as $position) {
				if($position > 0 and $served[$position] !== $served[$position - 1]) {
					$count++;
				}
			}

			if($count > $changes) {
				$notice['rhythm'] = $row;
				$notice['rhythmWeeks'] = $weeks;
				$changes = $count;
			}

		}

		$lines = 0;

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			$count = $eCompositionWeek['cCompositionLine']->count();

			if($count === 0) {
				continue;
			}

			if($eCompositionWeek['amended']) {

				if($notice['amended'] === NULL) {
					$notice['amended'] = $eCompositionWeek['week'];
				}

			} else if(array_key_exists($eCompositionWeek['week'], $eCompositionFormula['lean']['weeks']) === FALSE and $count > $lines) {
				$notice['filled'] = $eCompositionWeek['week'];
				$lines = $count;
			}

		}

		if($eCompositionFormula['lean']['periods'] !== []) {
			$notice['lean'] = $eCompositionFormula['lean']['periods'][array_key_first($eCompositionFormula['lean']['periods'])];
		}

		return $notice;

	}

	/**
	 * Les tailles qui portent une correction, semaine par semaine, `[semaine => [nom de taille]]`, dans
	 * l'ordre des tailles : ce que ne plus livrer une semaine ferait perdre, que le panneau
	 * « Semaine par semaine » fait confirmer (`CompositionUi::getWeekLoss()`).
	 */
	public static function getFormulasAmended(CompositionPlan $eCompositionPlan): array {

		$formulasAmended = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			foreach(CompositionWeekLib::getAmendedByFormula($eCompositionFormula) as $eCompositionWeek) {
				$formulasAmended[$eCompositionWeek['week']][] = $eCompositionFormula['name'];
			}
		}

		ksort($formulasAmended);

		return $formulasAmended;

	}

	/**
	 * Ce que le panneau « Modifier les paniers » lit à son ouverture (r31, #96), sur le plan de la
	 * structure : ses tailles (`cCompositionFormula`), chacune avec ce qu'une recomposition lui ferait
	 * perdre (`CompositionFormulaLib::applyRecompose()`), le montant de son écart (`gapAmount`) et les
	 * tailles sur lesquelles elle peut s'appuyer (`baseChoices`), et ce que « Recommencer les paniers »
	 * remplacera (`losses`).
	 */
	public static function loadSettings(CompositionPlan $eCompositionPlan): void {

		$eCompositionPlan->expects(['id', 'structure']);

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		// Les ids et non la collection : `applyRecompose()` et `getBaseChoices()` la parcourent, et une
		// `Collection` n'a qu'un curseur, qu'ils arrêteraient après la première taille.
		foreach($cCompositionFormula->getIds() as $formulaId) {

			$eCompositionFormula = $cCompositionFormula[$formulaId];

			CompositionFormulaLib::applyRecompose($eCompositionFormula, $cCompositionFormula, CompositionWeekLib::getAmendedByFormula($eCompositionFormula));

			$eCompositionFormula['gapAmount'] = CompositionLib::getGapAmount((float)$eCompositionFormula['price'], $eCompositionFormula['gap']);
			$eCompositionFormula['baseChoices'] = CompositionFormulaLib::getBaseChoices($eCompositionFormula, $cCompositionFormula);

		}

		$eCompositionPlan['cCompositionFormula'] = $cCompositionFormula;
		$eCompositionPlan['losses'] = self::getLosses($eCompositionPlan);

	}

	/**
	 * Ce que « Recommencer les paniers » remplacera quand le départ sera envoyé (`restart()`), pour la
	 * confirmation qui le dit : `amended`, le nombre de semaines corrigées à la main, tous paniers
	 * confondus. Les paniers eux-mêmes se recomposent toujours, et la confirmation ne les compte
	 * plus (r51b).
	 *
	 * Les semaines livrées, les espèces retirées, les prix tapés sur une espèce et les saisons réglées
	 * à la main n'y sont pas : le départ les reprend (`restart()`), et ils ne se perdent donc pas.
	 *
	 * Chaque taille porte ses semaines corrigées (`weeksAmended`), que « Modifier les paniers » lit
	 * pour ses propres confirmations (`loadSettings()`) : elles s'y comptent.
	 */
	public static function getLosses(CompositionPlan $eCompositionPlan): array {

		$eCompositionPlan->expects(['id', 'cCompositionFormula']);

		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];

		$amended = 0;

		foreach($cCompositionFormula as $eCompositionFormula) {
			$amended += count($eCompositionFormula['weeksAmended']);
		}

		return [
			'amended' => $amended,
		];

	}

	/**
	 * Le départ, sans rien écrire : les réponses que ses trois étapes montrent déjà posées, sur un
	 * plan neuf qui porte la structure et la saison de son plan de culture. `$season` est celle que
	 * le menu des saisons choisit en recommençant ; sans elle, le départ garde celle du plan de la
	 * structure, ou prend la saison affichée par ailleurs tant qu'il n'y en a pas.
	 *
	 * - `weeks` : celles du plan de la structure, ou aucune tant qu'il n'y en a pas ;
	 * - `cCompositionFormula` : ses tailles de panier, ou une seule, vide : le prix est celui de la
	 *   structure, qu'aucune médiane ne devine (revue v12) ;
	 * - `cCompositionSpecies` : une espèce par espèce proposable sur la saison entière, dans l'ordre
	 *   alphabétique, avec sa fenêtre naturelle semaine par semaine (`window`) et comptée sur les
	 *   semaines posées (`weeks`). Cochées : celles que le plan cultive quand il suit déjà ce plan de
	 *   culture, sinon celles du plan de culture ; quand il est vide, celles que la structure a vendues
	 *   sur douze mois s'il y en a au moins `CompositionLib::LINES_MIN` parmi les proposables, de quoi
	 *   remplir un panier, ou toutes ;
	 * - `restart` : la structure a déjà un plan, que l'envoi remplacera ;
	 * - `seasonAsked` : en recommençant, `$season` est posée par le menu des saisons, et le départ
	 *   rouvre sur l'étape des espèces, où elle a été choisie. Au premier départ, le menu ne rouvre
	 *   rien, et une saison posée par l'adresse laisse les trois étapes à faire dans l'ordre ;
	 * - `followed` : ce plan suit déjà la saison du départ, et les espèces cochées sont les siennes ;
	 * - `cultivated` : le nombre d'espèces cochées depuis le plan de culture, `NULL` quand les espèces
	 *   viennent du plan ou que le plan de culture est vide ;
	 * - `sold` : les espèces cochées depuis les ventes, vide quand elles viennent d'ailleurs. Le menu
	 *   des saisons les reprend pour recocher la saison du départ ;
	 * - `cultivatedSeasons` : les espèces proposables de chaque plan de culture de la structure, par
	 *   saison, celle du départ toujours présente, même vide. Le départ en fait un menu qui recoche
	 *   les espèces depuis une autre saison, et qui, en recommençant, rouvre le départ sur elle pour
	 *   que les paniers la suivent (retour du 26/09 : le plan de culture se choisit quand
	 *   on conçoit les paniers) ;
	 * - `seasonMonths` et `seasonWeeks`, qui dessinent l'étape des semaines et la frise de l'aperçu,
	 *   et `seasonEmpty`, les douze mois d'une saison vide, que dessine le rang d'une espèce qu'aucune
	 *   source ne date (r58) ;
	 * - `tiles` : la structure a un plan de culture, quelle qu'en soit la saison, et le départ lui
	 *   donne le choix entre lui et le calendrier type (r48b). Sans plan de culture, il n'y a rien à
	 *   choisir : les saisons partent du calendrier ;
	 * - `reference` : le choix posé à l'arrivée, celui du plan qu'on recommence, sinon le plan de
	 *   culture quand celui de la saison porte des espèces, sinon le calendrier. Une saison choisie
	 *   au menu des saisons est un plan de culture choisi ;
	 * - `climate` : le climat posé à l'arrivée, celui du plan qu'on recommence, sinon celui de la
	 *   structure (`CompositionLib::getClimate()`), qui ne se déduit qu'en France. `NULL` : il se
	 *   choisit à la main ;
	 * - `climatePlan` : le climat du plan qu'on recommence, que ses espèces gardent sous la tuile du
	 *   plan de culture (`restart()`), `NULL` au premier départ ;
	 * - `sources` : l'ordre dans lequel une espèce prend sa saison sous chacun des deux choix
	 *   (`getStartSources()`).
	 *
	 * Chaque espèce porte aussi ce qu'un rang ajouté par le panneau porte (r48e,
	 * `CompositionSpeciesLib::loadStartAdd()`), à vide : `ePlantStructure`, l'espèce de la structure
	 * que le panneau a choisie, `typed`, et `monthsTraced`, la saison tracée au panneau (r67).
	 *
	 * Chaque espèce porte `seasons`, la saison que chaque source lui donnerait, en douze mois
	 * (`CompositionLib::getSeasonShares()`) : `community`, toujours là, `cultivation` quand le plan de
	 * culture de la saison la date, et une entrée par climat dont le calendrier la date. Le départ
	 * dessine celle du choix en cours sans rien demander au serveur, dans l'ordre que suit
	 * `CompositionSpeciesLib::getSeasonsDefault()` à l'écriture : le plan de culture, puis le
	 * calendrier, puis la communauté.
	 *
	 * Toutes les espèces proposables de la saison sont écrites, et non celles des seules semaines
	 * cochées : une espèce hors des semaines livrées n'entre dans aucun panier, et elle reste là quand
	 * les semaines changent ensuite.
	 *
	 * Un départ rouvert montre aussi les espèces que « Ajouter une espèce » a écrites en plus sur le
	 * plan qu'on recommence (r48c, `CompositionSpeciesLib::getAddedByPlan()`), cochées ou retirées
	 * comme ce plan les porte : elles se comptent, se retirent et se remettent avec les autres. La
	 * communauté ne les date pas, et leurs `seasons` n'ont pas d'entrée `community`.
	 *
	 * Il dessine ce que `restart()` gardera : une espèce dont la saison est réglée à la main sur le
	 * plan qu'on recommence la garde quelle que soit l'origine choisie, et porte donc cette saison
	 * sous chaque source.
	 */
	public static function getForStart(\structure\Structure $eStructure, ?int $season = NULL): CompositionPlan {

		$source = CompositionLib::getSource();

		$eCompositionPlanCurrent = self::getByStructure($eStructure);

		$seasonAsked = ($season !== NULL and $eCompositionPlanCurrent->notEmpty());

		$season ??= $eCompositionPlanCurrent->empty() ?
			\structure\MemberLib::getDynamicSeason($eStructure, 0) :
			$eCompositionPlanCurrent['season'];

		$eCompositionPlan = new CompositionPlan([
			'structure' => $eStructure,
			'season' => $season,
			'restart' => $eCompositionPlanCurrent->notEmpty(),
			'seasonAsked' => $seasonAsked,
			'followed' => ($eCompositionPlanCurrent->notEmpty() and $eCompositionPlanCurrent['season'] === $season),
			'seasonMonths' => CompositionLib::getSeasonMonths($season),
			'seasonWeeks' => CompositionLib::getSeasonWeeks(),
		]);

		$eCompositionPlan['seasonEmpty'] = CompositionLib::getSeasonShares($eCompositionPlan['seasonMonths'], [], []);

		$windows = CompositionLib::getNaturalWindows($eCompositionPlan['seasonWeeks'], $season, $source);

		$cultivated = [];
		$sold = [];

		$cultivatedBySeason = CompositionLib::getCultivatedBySeason($eStructure) + [$season => []];
		krsort($cultivatedBySeason);

		$cCompositionSpeciesAdded = $eCompositionPlanCurrent->empty() ?
			new \Collection() :
			CompositionSpeciesLib::getAddedByPlan($eCompositionPlanCurrent, array_keys($windows));

		$plantIdsAdded = $cCompositionSpeciesAdded->getKeys();
		$plantIdsStart = array_merge(array_keys($windows), $plantIdsAdded);

		$cultivatedBySeason = array_map(fn(array $plantIds) => array_values(array_intersect($plantIds, $plantIdsStart)), $cultivatedBySeason);

		if($eCompositionPlanCurrent->notEmpty()) {

			$eCompositionPlan['weeks'] = $eCompositionPlanCurrent['weeks'];
			$eCompositionPlan['cCompositionFormula'] = CompositionFormulaLib::getByPlan($eCompositionPlanCurrent);

		} else {

			$eCompositionPlan['weeks'] = [];
			$eCompositionPlan['cCompositionFormula'] = new \Collection([
				new CompositionFormula([
					'plan' => $eCompositionPlan,
					'base' => new CompositionFormula(),
					'name' => NULL,
					'price' => NULL,
					'baskets' => NULL,
				])
			]);

		}

		if($eCompositionPlan['followed']) {

			$excluded = CompositionSpeciesLib::getExcludedByPlan($eCompositionPlanCurrent);
			$grown = fn(int $plantId) => in_array($plantId, $excluded) === FALSE;

		} else {

			$cultivated = $cultivatedBySeason[$season];

			if($cultivated === []) {

				$sold = array_values(array_intersect(array_keys(CompositionLib::getSales($eStructure)), array_keys($windows)));

				if(count($sold) < CompositionLib::LINES_MIN) {
					$sold = [];
				}

			}

			$grown = fn(int $plantId) => (
				($cultivated === [] and $sold === []) or
				in_array($plantId, $cultivated) or
				in_array($plantId, $sold)
			);

		}

		$cPlant = \plant\Plant::model()
			->select(['id', 'name', 'fqn', 'vignette'])
			->whereId('IN', $plantIdsStart)
			->getCollection(NULL, NULL, 'id');

		$seasons = self::getStartSeasons($eCompositionPlan, $windows, $plantIdsAdded);

		if($eCompositionPlanCurrent->notEmpty()) {
			$seasons = self::getStartSeasonsCustom($eCompositionPlan, $seasons, CompositionSpeciesLib::getSeasonsByPlant($eCompositionPlanCurrent));
		}

		$cCompositionSpecies = new \Collection();

		foreach(array_keys($windows) as $plantId) {

			$cCompositionSpecies[$plantId] = new CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => $cPlant[$plantId],
				'status' => $grown($plantId) ? CompositionSpecies::NATURAL : CompositionSpecies::EXCLUDED,
				'seasons' => $seasons[$plantId],
				'ePlantStructure' => new \plant\Plant(),
				'typed' => FALSE,
				'monthsTraced' => NULL,
			]);

		}

		// Une espèce ajoutée garde l'état que le plan lui porte, même quand le départ suit une autre
		// saison : le plan de culture d'une autre année ne dit rien de ce qu'on a ajouté à la main.
		foreach($cCompositionSpeciesAdded as $plantId => $eCompositionSpeciesAdded) {

			$cCompositionSpecies[$plantId] = new CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => $cPlant[$plantId],
				'status' => ($eCompositionSpeciesAdded['status'] === CompositionSpecies::EXCLUDED) ? CompositionSpecies::EXCLUDED : CompositionSpecies::NATURAL,
				'seasons' => $seasons[$plantId],
				'ePlantStructure' => new \plant\Plant(),
				'typed' => FALSE,
				'monthsTraced' => NULL,
			]);

		}

		$cCompositionSpecies->sort(['plant' => ['name' => SORT_ASC]], natural: TRUE);

		$tiles = (array_filter($cultivatedBySeason) !== []);

		if($tiles === FALSE) {
			$reference = CompositionPlan::CALENDAR;
		} else if($seasonAsked) {
			$reference = CompositionPlan::CULTIVATION;
		} else if($eCompositionPlanCurrent->notEmpty() and $eCompositionPlanCurrent['reference'] !== CompositionPlan::COMMUNITY) {
			$reference = $eCompositionPlanCurrent['reference'];
		} else {
			$reference = ($cultivatedBySeason[$season] === []) ? CompositionPlan::CALENDAR : CompositionPlan::CULTIVATION;
		}

		$climatePlan = $eCompositionPlanCurrent->empty() ? NULL : $eCompositionPlanCurrent['climate'];

		$eCompositionPlan['tiles'] = $tiles;
		$eCompositionPlan['reference'] = $reference;
		$eCompositionPlan['climatePlan'] = $climatePlan;
		$eCompositionPlan['climate'] = $climatePlan ?? CompositionLib::getClimate($eStructure);
		$eCompositionPlan['sources'] = self::getStartSources($climatePlan);

		$eCompositionPlan['cCompositionSpecies'] = $cCompositionSpecies;
		$eCompositionPlan['cultivatedSeasons'] = $cultivatedBySeason;
		$eCompositionPlan['sold'] = $sold;
		$eCompositionPlan['cultivated'] = ($cultivated === []) ?
			NULL :
			$cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL)->count();

		return $eCompositionPlan;

	}

	/**
	 * L'ordre dans lequel une espèce prend sa saison au départ, pour chacun des deux choix, tel que
	 * `CompositionSpeciesLib::getSeasonsDefault()` l'écrira : sous le calendrier, le climat choisi
	 * puis la communauté ; sous le plan de culture, le plan de culture, puis le climat que le plan
	 * garde quand on le recommence (`$climatePlan`), puis la communauté. `NULL` tient la place du
	 * climat choisi, que seul le formulaire du départ connait.
	 */
	private static function getStartSources(?string $climatePlan): array {

		$sources = [
			CompositionPlan::CALENDAR => [NULL, CompositionPlan::COMMUNITY],
			CompositionPlan::CULTIVATION => [CompositionPlan::CULTIVATION, CompositionPlan::COMMUNITY],
		];

		if($climatePlan !== NULL) {
			$sources[CompositionPlan::CULTIVATION] = [CompositionPlan::CULTIVATION, $climatePlan, CompositionPlan::COMMUNITY];
		}

		return $sources;

	}

	/**
	 * Pose sur le plan que le départ construit, encore sans id, ce que « Ajouter une espèce » lit pour
	 * dater une espèce comme sa liste la date (r48e, `CompositionSpeciesLib::loadStartAdd()`) : les
	 * mois de la saison (`seasonMonths`), ceux d'une saison vide (`seasonEmpty`) et l'ordre des
	 * sources (`sources`, `getStartSources()`), sur le climat du plan qu'on recommence.
	 */
	public static function loadStart(CompositionPlan $e): void {

		$e->expects(['structure', 'season']);

		$eCompositionPlanCurrent = self::getByStructure($e['structure']);

		$e['seasonMonths'] = CompositionLib::getSeasonMonths($e['season']);
		$e['seasonEmpty'] = CompositionLib::getSeasonShares($e['seasonMonths'], [], []);
		$e['sources'] = self::getStartSources($eCompositionPlanCurrent->empty() ? NULL : $eCompositionPlanCurrent['climate']);

	}

	/**
	 * La saison que chaque source donnerait à l'espèce `$plantId` au départ, `[source => mois]`, lue
	 * comme `getForStart()` la lit pour ses rangs : celle de la communauté quand elle la propose, celle
	 * du plan de culture et de chaque climat qui la datent, et sous toutes la saison réglée à la main
	 * que le plan qu'on recommence lui porte. C'est ce que dessine le rang d'une espèce ajoutée par le
	 * panneau (r48e).
	 */
	public static function getStartSeasonsByPlant(CompositionPlan $eCompositionPlan, int $plantId): array {

		$eCompositionPlan->expects(['structure', 'season', 'seasonMonths']);

		$windows = array_intersect_key(
			CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), $eCompositionPlan['season'], CompositionLib::getSource()),
			[$plantId => TRUE]
		);

		$seasons = self::getStartSeasons($eCompositionPlan, $windows, ($windows === []) ? [$plantId] : []);

		$eCompositionPlanCurrent = self::getByStructure($eCompositionPlan['structure']);

		if($eCompositionPlanCurrent->notEmpty()) {
			$seasons = self::getStartSeasonsCustom($eCompositionPlan, $seasons, array_intersect_key(CompositionSpeciesLib::getSeasonsByPlant($eCompositionPlanCurrent), [$plantId => TRUE]));
		}

		return $seasons[$plantId];

	}

	/**
	 * L'id des espèces que l'envoi du départ construit pour la saison `$season` : celles que la
	 * communauté propose (`CompositionLib::getProposable()`), puis celles que le plan qu'on recommence
	 * porte en plus (`CompositionSpeciesLib::getAddedByPlan()`). Ce sont les rangs que `getForStart()`
	 * montre.
	 */
	public static function getStartPlantIds(\structure\Structure $eStructure, int $season): array {

		$plantIds = CompositionLib::getProposable(CompositionLib::getSeasonWeeks(), $season, CompositionLib::getSource());

		$eCompositionPlanCurrent = self::getByStructure($eStructure);

		if($eCompositionPlanCurrent->empty()) {
			return $plantIds;
		}

		return array_merge($plantIds, CompositionSpeciesLib::getAddedByPlan($eCompositionPlanCurrent, $plantIds)->getKeys());

	}

	/**
	 * La saison que chaque source donnerait à chacune des espèces de `$windows` (les fenêtres de la
	 * communauté, `CompositionLib::getNaturalWindows()`) et de `$plantIdsAdded` (celles que le plan
	 * qu'on recommence porte en plus, que la communauté ne date pas), en douze mois :
	 * `[id d'espèce => [source => mois]]`. Les trous courts sont bouchés comme à l'écriture
	 * (`CompositionSpeciesLib::getSeasonsDefault()`), pour que le départ dessine la saison que le plan
	 * portera.
	 *
	 * Le plan de culture se lit sur la saison du départ, celle que le plan suivra, et non sur celle
	 * que le menu des saisons montre au premier départ, qui ne fait que choisir les espèces.
	 */
	private static function getStartSeasons(CompositionPlan $eCompositionPlan, array $windows, array $plantIdsAdded): array {

		$eStructure = $eCompositionPlan['structure'];
		$seasonMonths = $eCompositionPlan['seasonMonths'];
		$plantIds = array_merge(array_keys($windows), $plantIdsAdded);

		// Le départ ne range que des espèces du référentiel, sans plan : chacune se sert sous son id.
		$plants = array_combine($plantIds, $plantIds);

		$sources = [
			CompositionPlan::CULTIVATION => CompositionLib::getReferenceWindows(
				CompositionLib::getHarvestWeeks($eStructure, $eCompositionPlan['season']),
				$plants,
				CompositionLib::getStorage($eStructure, $plantIds)
			),
		];

		foreach(CompositionPlan::model()->getPropertyEnum('climate') as $climate) {
			$sources[$climate] = CompositionLib::getCalendarWindows($climate, $plants);
		}

		$seasons = [];

		foreach($plantIds as $plantId) {

			$seasons[$plantId] = array_key_exists($plantId, $windows) ? [
				CompositionPlan::COMMUNITY => CompositionLib::getSeasonShares($seasonMonths, CompositionLib::getWindowWeeksList(CompositionLib::getWindowWeeksFilled($windows[$plantId])), []),
			] : [];

			foreach($sources as $source => $windowsSource) {

				if(array_key_exists($plantId, $windowsSource)) {

					$windowSource = $windowsSource[$plantId];

					$seasons[$plantId][$source] = CompositionLib::getSeasonShares(
						$seasonMonths,
						CompositionLib::getWindowWeeksList(CompositionLib::getWindowWeeksFilled(array_keys($windowSource))),
						array_keys($windowSource, FALSE, TRUE)
					);

				}

			}

		}

		return $seasons;

	}

	/**
	 * Pose sur `$seasons` (`getStartSeasons()`) la saison des espèces réglées à la main (`CUSTOM`) du
	 * plan qu'on recommence, lue dans `$seasonsWritten` (`CompositionSpeciesLib::getSeasonsByPlant()`) :
	 * `restart()` la garde quelle que soit l'origine choisie, et le départ la dessine donc sous chaque
	 * source, la même partout. Une saison réglée à la main n'a pas de part de conservation à
	 * distinguer, comme dans l'onglet « Espèces ».
	 */
	private static function getStartSeasonsCustom(CompositionPlan $eCompositionPlan, array $seasons, array $seasonsWritten): array {

		$sources = array_merge([CompositionPlan::COMMUNITY, CompositionPlan::CULTIVATION], CompositionPlan::model()->getPropertyEnum('climate'));

		foreach($seasonsWritten as $plantId => $season) {

			if($season['status'] !== CompositionSpecies::CUSTOM or array_key_exists($plantId, $seasons) === FALSE) {
				continue;
			}

			$months = CompositionLib::getSeasonShares($eCompositionPlan['seasonMonths'], CompositionLib::getWindowWeeksList($season['windowWeeks']), []);

			$seasons[$plantId] = array_fill_keys($sources, $months);

		}

		return $seasons;

	}

	/**
	 * Écrit le plan que le départ envoie, construit par la page : le plan et ses semaines, ses
	 * espèces (`cCompositionSpecies`, `EXCLUDED` pour une culture décochée), ses tailles de panier
	 * (`cCompositionFormula`) et la proposition de chacune, composée sur les espèces cultivées.
	 *
	 * Chaque espèce cultivée prend sa saison de vente (lot r27,
	 * `CompositionSpeciesLib::getSeasonsDefault()`) : celle du plan de culture quand le plan le suit
	 * (`reference`), sinon celle du calendrier type quand le plan a un climat (r48b), sinon celle de la
	 * communauté, à moins qu'elle n'arrive avec la sienne, reprise du plan remplacé (`restart()`) ou
	 * réglée par ses mois dans le départ (r48e, `composition:doStart`). Elle se calcule sur les
	 * espèces écrites, dont l'unité décide des semaines où la communauté la vend. Une espèce cochée
	 * qu'aucun des trois ne date n'a pas de saison, et s'écrit retirée.
	 *
	 * Un doublon sur `structure` (deux envois simultanés) annule la transaction et
	 * remonte : `rollBack()` remet toute la pile à zéro, et c'est voulu, un plan à moitié écrit ne
	 * doit pas survivre.
	 *
	 * Deux tailles du même nom dans le départ (lot r40) : le plan n'étant pas encore écrit,
	 * `CompositionFormula::name.duplicate` n'a rien à quoi les comparer, et c'est l'unicité
	 * `(plan, name)` qui refuse la seconde. La transaction s'annule de même, et le refus se lève avec
	 * le message du contrôle.
	 */
	public static function create(CompositionPlan $e): void {

		// Un plan sans climat ne lit aucun calendrier : c'est le défaut d'un plan construit sans passer
		// par le départ.
		$e->add(['climate' => NULL]);

		$e->expects(['structure', 'season', 'reference', 'weeks', 'cCompositionFormula', 'cCompositionSpecies']);

		CompositionPlan::model()->beginTransaction();

			try {
				parent::create($e);
			} catch(\DuplicateException $exception) {
				CompositionPlan::model()->rollBack();
				throw $exception;
			}

			foreach($e['cCompositionSpecies'] as $eCompositionSpecies) {
				$eCompositionSpecies['plan'] = $e;
				CompositionSpeciesLib::create($eCompositionSpecies);
			}

			// Les saisons ne sont pas encore écrites : la source se lit sans elles, pour les unités. Une
			// espèce qui arrive en `CUSTOM`, en `CULTIVATION` ou en `CALENDAR` porte déjà la sienne,
			// reprise du plan remplacé (`restart()`) ou réglée par ses mois dans le départ (`CUSTOM`,
			// r48e) : le formulaire du départ ne poste lui-même que `NATURAL` ou `EXCLUDED`, le contrôle
			// de `status` refusant les trois autres sans saison.
			$cCompositionSpeciesGrown = $e['cCompositionSpecies']->find(fn(CompositionSpecies $eCompositionSpecies) => $eCompositionSpecies['status'] === CompositionSpecies::NATURAL, clone: FALSE);

			$seasons = CompositionSpeciesLib::getSeasonsDefault($e, CompositionSpeciesLib::getPlants($cCompositionSpeciesGrown)['plants'], CompositionLib::getSourceByPlan($e, []));

			foreach($cCompositionSpeciesGrown as $eCompositionSpecies) {

				$speciesId = $eCompositionSpecies['id'];

				if(array_key_exists($speciesId, $seasons)) {
					$eCompositionSpecies->merge($seasons[$speciesId]);
				} else {
					$eCompositionSpecies['status'] = CompositionSpecies::EXCLUDED;
				}

				CompositionSpeciesLib::update($eCompositionSpecies, ['status', 'windowWeeks']);

			}

			// La cascade lit d'abord les prix tapés sur les espèces du plan, et les semaines rayées
			// leurs saisons : les deux se lisent sur le plan écrit, et ses espèces avec lui.
			$source = CompositionLib::getSourceByPlan($e);
			$prices = $source['pricesPlan'];

			$position = 0;

			foreach($e['cCompositionFormula'] as $eCompositionFormula) {
				$eCompositionFormula['plan'] = $e;
				$eCompositionFormula['position'] = $position++;
				$eCompositionFormula->add(['base' => new CompositionFormula()]);
			}

			// Les bases d'abord (lot r17) : une dérivée s'écrit avec l'id de sa base, et se compose sur
			// les semaines que sa base vient d'écrire. Sans `clone: FALSE`, `find()` rendrait des copies :
			// la base écrite recevrait son id sur la copie, et la dérivée, qui pointe l'élément d'origine,
			// s'écrirait sans base.
			$cCompositionFormulaOrdered = $e['cCompositionFormula']
				->find(fn(CompositionFormula $eCompositionFormula) => $eCompositionFormula['base']->empty(), clone: FALSE)
				->appendCollection($e['cCompositionFormula']->find(fn(CompositionFormula $eCompositionFormula) => $eCompositionFormula['base']->notEmpty(), clone: FALSE));

			foreach($cCompositionFormulaOrdered as $eCompositionFormula) {

				try {
					CompositionFormulaLib::create($eCompositionFormula);
				} catch(\DuplicateException) {

					CompositionPlan::model()->rollBack();

					throw new \FailAction('toolbox\CompositionFormula::name.duplicate');

				}

				CompositionLib::compose($e, $eCompositionFormula, $source, $prices);

			}

			// La première composition fait des paniers un outil de la structure (`Feature.hasToolbox`),
			// comme le premier pluviomètre pour la pluviométrie (`GaugeLib::create()`) : la Boîte à
			// outils les range alors sous « Vos outils ». Recommencer les paniers repasse par ici, et
			// l'outil y est déjà.
			if(in_array(Favorite::BASKET, $e['structure']->conf(\structure\Feature::class)['hasToolbox']) === FALSE) {
				\structure\FeatureLib::addToolbox($e['structure'], Favorite::BASKET);
			}

		CompositionPlan::model()->commit();

	}

	/**
	 * « Recommencer les paniers », ou suivre un autre plan de culture : le plan de la structure est
	 * remplacé par celui que le départ envoie
	 * (`create()`), déjà construit par la page. Rien ne s'écrit avant cet envoi, et un départ
	 * abandonné laisse donc le plan tel qu'il était.
	 *
	 * Suppression et création vivent dans la même transaction : un échec à la création laisserait
	 * sinon la structure sans plan du tout. La suppression passe par les Lib, feuille à feuille, comme
	 * toute suppression de plan.
	 *
	 * Le prix tapé sur une espèce passe d'un plan à l'autre (lot N), comme les semaines livrées et les
	 * espèces retirées que le départ montre déjà posées : c'est une réponse, que rien dans le départ ne
	 * redemande. Il se pose sur l'espèce que le départ envoie avant l'écriture, pour que la
	 * proposition de chaque taille le lise (`create()`, `CompositionLib::getPrices()`). Le pas et la
	 * quantité minimale de l'espèce passent de la même façon (revue v12), et pour la même raison, comme
	 * son poids (lot L3) et sa fréquence (lot L9). L'unité choisie passe aussi (lot r14), et avant eux :
	 * le prix, le pas, le minimum et le poids se disent dans cette unité, et la proposition la lit
	 * (`CompositionLib::applyUnits()`) sur les espèces écrites par `create()`.
	 *
	 * D'où partent les saisons est ce que le départ envoie (r48b) : `reference` et `climate` sont ceux
	 * du plan construit par la page. Sous le plan de culture, le départ ne demande pas de climat, et le
	 * plan garde celui qu'il avait : une espèce que le plan de culture ne date pas y reprend la saison
	 * du calendrier, comme avant de recommencer. La saison de vente de chaque espèce que le départ
	 * n'exclut pas passe ainsi (arbitrage r27 du 28/09, étendu au calendrier) :
	 *
	 * - une saison réglée à la main (`CUSTOM`) passe toujours : c'est un choix de l'utilisateur, qui
	 *   ne dépend d'aucune source, et ses semaines se comptent dans l'année des paniers, la même d'une
	 *   saison à l'autre ;
	 * - une saison prise d'une source (`CULTIVATION`, `CALENDAR`, `NATURAL`) passe tant que le plan
	 *   garde sa saison, sa référence et son climat. Dès que l'un des trois change, elle suivait autre
	 *   chose que ce que le plan suit maintenant, et `create()` lui donne la saison de la source
	 *   choisie ;
	 * - une espèce que l'ancien plan n'avait pas prend la saison que `create()` lui donne ;
	 * - une espèce dont les mois sont réglés dans le départ (r48e) arrive avec sa saison, sous
	 *   `CUSTOM`, et la garde : c'est le dernier choix de la personne, qui passe devant ce que l'ancien
	 *   plan portait.
	 *
	 * Le départ envoie les espèces que la communauté propose pour la saison, celles que « Ajouter
	 * une espèce » a écrites en plus (r48c, `getStartPlantIds()`) et celles que son propre panneau
	 * vient d'ajouter (r48e), cochées ou retirées comme son écran les laisse. Toutes gardent leur unité et leur quantité au panier, que le départ ne sait pas
	 * redemander, et leur saison suit la même règle. Une espèce de l'ancien plan qu'un envoi ne
	 * porterait pas passe telle quelle, retirée si elle l'était : aucune ne se perd.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function restart(CompositionPlan $e): void {

		$e->add(['climate' => NULL]);

		$e->expects(['structure', 'season', 'reference', 'cCompositionSpecies']);

		// Le départ n'envoie que des espèces nues (r76) : c'est le nom de chacune qui la rapproche de
		// celle de l'ancien plan, avant que `CompositionSpeciesLib::create()` le pose.
		foreach($e['cCompositionSpecies'] as $eCompositionSpecies) {
			$eCompositionSpecies->add(['name' => NULL]);
		}

		CompositionPlan::model()->beginTransaction();

			$eCompositionPlanOld = self::getByStructure($e['structure']);

			if($eCompositionPlanOld->notEmpty()) {

				if($e['reference'] === CompositionPlan::CULTIVATION) {
					$e['climate'] = $eCompositionPlanOld['climate'];
				}

				$sourceKept = (
					$eCompositionPlanOld['season'] === $e['season'] and
					$eCompositionPlanOld['reference'] === $e['reference'] and
					$eCompositionPlanOld['climate'] === $e['climate']
				);

				$units = CompositionSpeciesLib::getUnitsByPlan($eCompositionPlanOld);
				$prices = CompositionSpeciesLib::getTypedPrices($eCompositionPlanOld);
				$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlanOld);
				$weights = CompositionSpeciesLib::getTypedWeights($eCompositionPlanOld);
				$frequencies = CompositionSpeciesLib::getFrequenciesByPlan($eCompositionPlanOld);
				$seasons = CompositionSpeciesLib::getSeasonsByPlan($eCompositionPlanOld);

				// Le départ n'envoie que des espèces nues : une variante (r76) n'est pas un de ses rangs,
				// et passe donc toujours par la boucle des espèces que l'envoi ne porte pas, nom compris.
				$plantIdsSent = $e['cCompositionSpecies']->getColumnCollection('plant')->getIds();

				$cCompositionSpeciesOld = CompositionSpecies::model()
					->select(['id', 'plant', 'name', 'status'])
					->wherePlan($eCompositionPlanOld)
					->getCollection();

				// `NATURAL` sans saison est ce que le départ envoie d'une espèce cochée : `create()` lui
				// donne celle de la source du plan, quand la boucle ne lui rend pas la sienne.
				foreach($cCompositionSpeciesOld as $eCompositionSpeciesOld) {

					if($eCompositionSpeciesOld['name'] === NULL and in_array($eCompositionSpeciesOld['plant']['id'], $plantIdsSent)) {
						continue;
					}

					$e['cCompositionSpecies'][] = new CompositionSpecies([
						'plan' => $e,
						'plant' => $eCompositionSpeciesOld['plant'],
						'name' => $eCompositionSpeciesOld['name'],
						'status' => ($eCompositionSpeciesOld['status'] === CompositionSpecies::EXCLUDED) ? CompositionSpecies::EXCLUDED : CompositionSpecies::NATURAL,
						'windowWeeks' => NULL,
					]);

				}

				// Les réglages de l'ancien plan sont rangés par id de son espèce, qui disparaît avec lui :
				// une espèce du nouveau plan retrouve la sienne par ce qui la désigne d'un plan à
				// l'autre, l'espèce lue et le nom de la variante.
				$speciesIdsOld = [];

				foreach($cCompositionSpeciesOld as $eCompositionSpeciesOld) {
					$speciesIdsOld[$eCompositionSpeciesOld['plant']['id'].'-'.$eCompositionSpeciesOld['name']] = $eCompositionSpeciesOld['id'];
				}

				foreach($e['cCompositionSpecies'] as $eCompositionSpecies) {

					$key = $eCompositionSpecies['plant']['id'].'-'.$eCompositionSpecies['name'];

					if(array_key_exists($key, $speciesIdsOld) === FALSE) {
						continue;
					}

					$speciesIdOld = $speciesIdsOld[$key];

					if(
						$eCompositionSpecies['status'] === CompositionSpecies::NATURAL and
						array_key_exists($speciesIdOld, $seasons) and
						($seasons[$speciesIdOld]['status'] === CompositionSpecies::CUSTOM or $sourceKept)
					) {
						$eCompositionSpecies->merge($seasons[$speciesIdOld]);
					}

					if(array_key_exists($speciesIdOld, $units)) {
						$eCompositionSpecies['unit'] = new \product\Unit(['id' => $units[$speciesIdOld]['unit']]);
						$eCompositionSpecies['quantityBasket'] = $units[$speciesIdOld]['quantityBasket'];
					}

					if(array_key_exists($speciesIdOld, $prices)) {
						$eCompositionSpecies['price'] = $prices[$speciesIdOld];
					}

					if(array_key_exists($speciesIdOld, $steps)) {
						$eCompositionSpecies['step'] = $steps[$speciesIdOld]['step'];
						$eCompositionSpecies['minimum'] = $steps[$speciesIdOld]['minimum'];
						$eCompositionSpecies['quantity'] = $steps[$speciesIdOld]['quantity'];
					}

					if(array_key_exists($speciesIdOld, $weights)) {
						$eCompositionSpecies['weight'] = $weights[$speciesIdOld];
					}

					if(array_key_exists($speciesIdOld, $frequencies)) {
						$eCompositionSpecies['frequency'] = $frequencies[$speciesIdOld];
					}

				}

				self::applyBases($eCompositionPlanOld, $e['cCompositionFormula']);

				self::delete($eCompositionPlanOld);

			}

			self::create($e);

		CompositionPlan::model()->commit();

	}

	/**
	 * « Recommencer » garde le lien d'une taille à sa base (lot r17) par position : la taille de même
	 * rang que la dérivée d'avant s'appuie sur celle de même rang que sa base, tant que la base reste
	 * la moins chère des deux. Sinon, ou quand le départ n'envoie plus l'une des deux, le lien tombe et
	 * la taille se compose seule. Les tailles du départ sont celles d'avant, dans leur ordre
	 * (`getForStart()`), et c'est ce qui donne un sens au rang.
	 */
	private static function applyBases(CompositionPlan $eCompositionPlanOld, \Collection $cCompositionFormula): void {

		$cCompositionFormulaOld = CompositionFormulaLib::getByPlan($eCompositionPlanOld);
		$ranks = array_flip($cCompositionFormulaOld->getIds());

		$formulas = [];

		foreach($cCompositionFormula as $eCompositionFormula) {
			$formulas[] = $eCompositionFormula;
		}

		foreach($cCompositionFormulaOld as $eCompositionFormulaOld) {

			if($eCompositionFormulaOld['base']->empty()) {
				continue;
			}

			$rank = $ranks[$eCompositionFormulaOld['id']];
			$rankBase = $ranks[$eCompositionFormulaOld['base']['id']];

			if(
				array_key_exists($rank, $formulas) and
				array_key_exists($rankBase, $formulas) and
				(float)$formulas[$rankBase]['price'] < (float)$formulas[$rank]['price']
			) {
				$formulas[$rank]['base'] = $formulas[$rankBase];
			}

		}

	}

	/**
	 * Les semaines livrées sont communes à toutes les formules : les changer refait toutes les
	 * propositions du plan hors des semaines corrigées (`CompositionLib::recomposePlan()`), dans la
	 * transaction qui les écrit.
	 */
	public static function update(CompositionPlan $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');

		CompositionPlan::model()->beginTransaction();

			parent::update($e, array_merge($properties, ['updatedAt']));

			if(in_array('weeks', $properties)) {

				$source = CompositionLib::getSourceByPlan($e);

				CompositionLib::recomposePlan($e, $source, $source['pricesPlan']);

			}

		CompositionPlan::model()->commit();

	}

	/**
	 * « Appliquer le plan de culture » (lot r27, r29 #80, r51b) : chaque espèce du plan de `$speciesIds`, les
	 * cases cochées du deck « Plan de culture », prend la saison de ses semaines de récolte et de
	 * conservation (`getSynchronized()`), sous `CULTIVATION`. Le deck ne liste que les espèces dont
	 * les mois dessinés changent (`getSynchronization()`), et ce qu'il ne liste pas ne se décide pas :
	 *
	 * - une espèce listée et décochée garde ses mois, qui deviennent des mois choisis par la
	 *   personne (`CUSTOM`) : le deck ne la compte plus ;
	 * - une espèce dont la saison ne bouge que dans les mêmes mois suit le plan de culture sans avoir
	 *   été listée, sauf si elle est réglée à la main ;
	 * - une espèce que le plan de culture ne date pas garde la sienne.
	 *
	 * Le plan passe à `CULTIVATION`, ce qui borne ensuite les quantités des espèces datées au stock
	 * prévu et fait reprendre sa saison du plan de culture à une espèce remise, et toutes les
	 * propositions se refont hors des semaines corrigées (`CompositionLib::recomposePlan()`).
	 *
	 * La liste se lit sur le plan, pas sur ce qui est posté : un identifiant qui ne désigne aucune
	 * espèce listée refuse tout l'envoi (`synchronizeListed`), l'écran d'où il part n'étant plus celui
	 * du plan, et un envoi sans espèce cochée n'applique rien (`synchronizeEmpty`).
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function synchronize(CompositionPlan $e, array $speciesIds): void {

		$e->expects(['id', 'structure', 'season', 'weeks']);

		if($speciesIds === []) {
			\Fail::log('toolbox\CompositionPlan::synchronizeEmpty');
			return;
		}

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'plant', 'name', 'status', 'windowWeeks'])
			->wherePlan($e)
			->getCollection();

		$synchronized = self::getSynchronized($e, $cCompositionSpecies);

		$speciesIdsListed = array_keys(array_filter($synchronized, fn(array $values) => $values['drawn']));

		if(array_diff($speciesIds, $speciesIdsListed) !== []) {
			\Fail::log('toolbox\CompositionPlan::synchronizeListed');
			return;
		}

		$e['reference'] = CompositionPlan::CULTIVATION;
		$e['updatedAt'] = new \Sql('NOW()');

		CompositionPlan::model()->beginTransaction();

			parent::update($e, ['reference', 'updatedAt']);

			foreach($cCompositionSpecies as $eCompositionSpecies) {

				$speciesId = $eCompositionSpecies['id'];

				if(array_key_exists($speciesId, $synchronized) === FALSE) {
					continue;
				}

				$custom = ($eCompositionSpecies['status'] === CompositionSpecies::CUSTOM);

				if(
					in_array($speciesId, $speciesIds, TRUE) or
					($synchronized[$speciesId]['drawn'] === FALSE and $custom === FALSE)
				) {

					$eCompositionSpecies['status'] = CompositionSpecies::CULTIVATION;
					$eCompositionSpecies['windowWeeks'] = $synchronized[$speciesId]['windowWeeks'];

					CompositionSpeciesLib::update($eCompositionSpecies, ['status', 'windowWeeks']);

				} else if($custom === FALSE) {

					$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;

					CompositionSpeciesLib::update($eCompositionSpecies, ['status']);

				}

			}

			$source = CompositionLib::getSourceByPlan($e);

			CompositionLib::recomposePlan($e, $source, $source['pricesPlan']);

		CompositionPlan::model()->commit();

	}

	/**
	 * Le plan emporte ses formules et ses espèces, qui n'ont aucun sens sans lui : la base ne porte
	 * pas de suppression en cascade, et ce qui resterait ne se rattraperait plus par aucun écran.
	 */
	public static function delete(CompositionPlan $e): void {

		$e->expects(['id']);

		foreach(CompositionFormulaLib::getByPlan($e) as $eCompositionFormula) {
			CompositionFormulaLib::delete($eCompositionFormula);
		}

		foreach(CompositionSpeciesLib::getByPlan($e) as $eCompositionSpecies) {
			CompositionSpeciesLib::delete($eCompositionSpecies);
		}

		parent::delete($e);

	}

}
?>
