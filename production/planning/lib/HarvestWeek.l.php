<?php
namespace planning;

/**
 * L'écran des récoltes d'une semaine : les cultures en récolte prévue cette semaine, celles qui ont été
 * récoltées cette semaine hors de leur période prévue, et celles qui restent en récolte au-delà (règles R1
 * et R2, R-Retours-0710) : commencées et pas encore au bout de leur période prévue, finies de
 * prévoir ou récoltées la semaine d'avant.
 *
 * La donnée vient de `\series\CultivationLib::getForHarvestWeek()`, qui porte la règle de ce qui entre
 * dans la liste (dont la série close, qui n'y reste que si elle a une récolte cette semaine) et les parts
 * de chaque culture par couple variété · calibre, et de `\series\TaskLib::getHarvestForWeek()` pour
 * l'intervention de récolte de la semaine. Seuls les calibres de l'espèce se lisent ici, en une requête
 * pour la liste (`prepareChoices()`). Ici, on range et on prépare ce que l'écran affiche sans le calculer.
 *
 * La saisie écrit par `\series\TaskLib::writeHarvestForWeek()`, qui tient la récolte, le temps et le
 * statut de l'intervention : ce Lib ne prépare que l'intervention à créer, le stock après écriture et
 * la réponse ciblée qui suit.
 */
class HarvestWeekLib {

	/**
	 * Les cultures de la semaine, rangées par espèce puis par nom de série, chacune avec ce qu'il
	 * faut pour se nommer et se situer (`nameWithSeason`, `nameWithStartWeek`, `nameWithVarieties`,
	 * `expectedFirst`, `expectedLast`, `harvestedWeek`), en plus du récolté jour par jour
	 * (`harvestedDays`) que pose `getForHarvestWeek()`.
	 *
	 * `$salesDays` vaut la fenêtre des ventes à venir quand l'écran les montre, celui des Récoltes pour qui les a
	 * allumées et que la structure ouvre à la vente (`harvestWeek.p.php`, V47), ou le tableau de bord
	 * (`DashboardLib`), `NULL` sinon : chaque culture porte alors `sales`, vide quand l'option est éteinte
	 * (`prepareSales()`).
	 *
	 * Chaque culture porte aussi le temps passé sur ses récoltes de la semaine et les personnes qui l'ont
	 * passé (`prepareTime()`).
	 *
	 * `$previous` lit le récolté de la semaine d'avant (`harvestedWeekPrevious`, et `weekPrevious` par part),
	 * que seul l'écran des Récoltes montre : le tableau de bord s'en passe, et sa requête avec.
	 *
	 * `$cPlant` borne la liste à ces espèces, vide pour toute la semaine (`getPlantsAround()`). Chaque
	 * préparation lit ligne par ligne, ou espèce par espèce pour les noms (`prepareNames()`) et les ventes
	 * (`prepareSales()`) : une espèce relue entière y est préparée comme dans la semaine.
	 */
	public static function get(\structure\Structure $eStructure, string $week, ?int $salesDays = NULL, bool $previous = FALSE, \Collection $cPlant = new \Collection()): \Collection {

		$cCultivation = \series\CultivationLib::getForHarvestWeek($eStructure, $week, $cPlant, previous: $previous);

		self::sortList($cCultivation);

		// La pastille de la semaine de départ (`HarvestWeekUi::getRow()`) prend la couleur du semis direct ou
		// de la plantation, que `\sequence\CropUi::start()` lit dans les actions principales.
		\work\ActionLib::getMainByStructure($eStructure);

		self::prepareNames($cCultivation, $week);
		self::prepareExpected($cCultivation, $week);
		self::prepareHarvestedByUnit($cCultivation);
		self::prepareTask($eStructure, $cCultivation, $week);
		self::preparePlanned($eStructure, $cCultivation, $week);
		self::prepareChoices($eStructure, $cCultivation);
		self::prepareTime($eStructure, $cCultivation, $week);
		self::keepParts($cCultivation);
		self::prepareParts($eStructure, $cCultivation, $week);
		self::prepareSales($eStructure, $cCultivation, $salesDays);
		self::prepareColor($eStructure, $cCultivation);

		return $cCultivation;

	}

	/**
	 * La sonde du couple variété · calibre qu'une saisie désigne : une intervention de récolte de la
	 * culture, sur laquelle la page construit `variety` et `harvestSize` (`build()`), avec les mêmes
	 * vérifications que le formulaire d'intervention. Vides, ils désignent la part « Non spécifiée ».
	 */
	public static function getCoupleProbe(\structure\Structure $eStructure, \series\Cultivation $eCultivation): \series\Task {

		return new \series\Task([
			'structure' => $eStructure,
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE),
			'variety' => new \plant\Variety(),
			'harvestSize' => new \plant\Size(),
		]);

	}

	/**
	 * La part du couple dans la semaine, vide (`days` à `[]`) quand le couple n'a encore rien récolté :
	 * le « + » d'un couple qu'on commence à ventiler, ou le tiroir ouvert sur « Non spécifiée ». Elle
	 * prend alors l'unité de son intervention de la semaine (`getEntryUnits()`), l'unité principale
	 * de la culture quand le couple n'en a pas encore.
	 */
	public static function getPart(\series\Cultivation $eCultivation, \plant\Variety $eVariety, \plant\Size $eSize, array $units): array {

		$key = \series\CultivationLib::getHarvestPartKey($eVariety, $eSize);

		return $eCultivation['harvestedParts'][$key] ?? [
			'key' => $key,
			'eVariety' => $eVariety,
			'eSize' => $eSize,
			'days' => [],
			'week' => [],
			'unit' => $units[$key] ?? $eCultivation['mainUnit'],
			'writable' => TRUE,
			'timeDays' => [],
			'timeTasks' => [],
		];

	}

	/**
	 * Le droit de saisir, une fois pour l'écran : l'intervention de récolte ne porte aucune règle d'état
	 * (`\series\Task` n'a ni `acceptWrite()` ni `acceptCreate()` propres), et son droit ne dépend que
	 * de la structure. Une sonde vaut donc pour toutes les lignes, qu'elles aient déjà une intervention
	 * (`assertWrite()`) ou non (`assertCreate()`, qui retombe sur les mêmes questions).
	 */
	public static function canHarvest(\structure\Structure $eStructure): bool {

		$eTask = new \series\Task(['structure' => $eStructure]);

		return ($eTask->assertWrite() and $eTask->assertCreate());

	}

	/**
	 * Le jour ouvert en arrivant sur la semaine : aujourd'hui quand la semaine le contient, le lundi d'une
	 * semaine passée, rien pour une semaine à venir, où l'on ne récolte pas encore. La Semaine des récoltes
	 * n'ouvre pas ce lundi (`getDays()`, `$defaultOpen`).
	 */
	public static function getEntryDate(array $days): ?string {

		$today = array_find_key($days, fn($day) => $day['today']);

		if($today !== NULL) {
			return $today;
		}

		$first = min(array_keys($days));

		return $days[$first]['future'] ? NULL : $first;

	}

	/**
	 * Le jour que propose « Nouvelle récolte » : aujourd'hui dans la semaine courante, le dernier jour
	 * d'une semaine passée, et rien pour une semaine à venir, où le panneau n'est pas proposé. La saisie
	 * du panneau appartient à la semaine affichée, celle de son sous-titre (arbitrage V-Planning du
	 * 2026-09-30) ; `doHarvest` refuse toute date qui n'y tombe pas.
	 *
	 * C'est aussi le jour que propose le geste de ligne quand aucun jour n'est ouvert (`proposed`,
	 * `getDays()`) : sans colonne ouverte, la ligne prend comme le panneau le jour le plus proche
	 * d'aujourd'hui.
	 */
	public static function getCreateDate(array $days): ?string {

		$today = array_find_key($days, fn($day) => $day['today']);

		if($today !== NULL) {
			return $today;
		}

		$last = max(array_keys($days));

		return $days[$last]['future'] ? NULL : $last;

	}

	/**
	 * Le jour que propose une saisie ouverte depuis une carte de la Semaine. Dans la semaine en cours, c'est
	 * aujourd'hui, que l'intervention soit faite ou non et quel que soit son jour (V29) : on récolte le jour où
	 * l'on saisit. Dans une semaine passée, c'est celui où l'intervention a été faite, à défaut celui où elle
	 * est planifiée, quand il tombe dans la semaine (retour 203). Une carte qui n'a pas de tel jour propose
	 * celui de « Nouvelle récolte » (`getCreateDate()`). Une semaine à venir ne propose aucun jour (`NULL`),
	 * comme « Nouvelle récolte ».
	 */
	public static function getTaskDate(\series\Task $eTask, array $days): ?string {

		$today = array_find_key($days, fn($day) => $day['today']);

		if($today !== NULL) {
			return $today;
		}

		$date = $eTask->isDone() ? $eTask['doneDate'] : $eTask['plannedDate'];

		if($date !== NULL and array_key_exists($date, $days) and $days[$date]['future'] === FALSE) {
			return $date;
		}

		return self::getCreateDate($days);

	}

	/**
	 * Les séries que propose la frappe dans « Nouvelle récolte » (R-Retours-0610, point 9), rangées comme la liste
	 * à champ vide (`getSuggested()`) : celles de la grille de la semaine, puis celles dont une semaine prévue tombe
	 * dans la fenêtre « proche » (`isNear()`), puis les autres. La coupe à `PlanningSetting::HARVEST_QUERY_SERIES`
	 * vient après ce rangement : une coupe en SQL écarterait des séries de la semaine au profit de l'alphabet.
	 *
	 * La grille, c'est la prévision de la semaine ou une récolte dans la semaine, lue dans les lignes de récolte
	 * comme le fait la grille (`getHarvestedInWeek()`), et non dans `harvestWeeks`, qui suit les semaines où les
	 * interventions sont faites. Seules les séries ouvertes se cherchent : une série close récoltée cette semaine,
	 * que la liste à champ vide montre, ne se trouve pas en tapant.
	 */
	public static function getForQuery(\structure\Structure $eStructure, string $week, string $query): \Collection {

		$cCultivationCandidate = \series\CultivationLib::getForTaskQuery($eStructure, $week, NULL, $query);

		$cultivationsHarvested = \series\CultivationLib::getHarvestedInWeek($eStructure, $week);

		$candidates = [];

		foreach($cCultivationCandidate as $eCultivation) {

			$weeks = $eCultivation['harvestWeeksExpected'] ?? [];

			if(in_array($week, $weeks, TRUE) or in_array($eCultivation['id'], $cultivationsHarvested, TRUE)) {
				$group = 'week';
			} else if(self::isNear($weeks, $week)) {
				$group = 'near';
			} else {
				$group = 'other';
			}

			$candidates[] = self::getCandidate($eCultivation, $group, $weeks);

		}

		$ranked = self::rank($candidates, $week, PlanningSetting::HARVEST_QUERY_SERIES);

		$cCultivationFound = \series\CultivationLib::getByIdsForHarvestWeek($eStructure, array_keys($ranked));

		$cCultivation = new \Collection();

		foreach($ranked as $id => $group) {
			$eCultivation = $cCultivationFound[$id];
			$eCultivation['harvestSuggestion'] = $group;
			$cCultivation[$id] = $eCultivation;
		}

		self::prepareNames($cCultivation, $week);

		return $cCultivation;

	}

	/**
	 * Ce que propose le panneau « Nouvelle récolte » avant toute frappe (R-Retours-0410, point 7, décision de
	 * V-Planning du 04/10) : toutes les cultures de la grille de la semaine en tête, dans l'ordre de la grille
	 * (`harvestSuggestion` à `week`), puis au plus `PlanningSetting::SUGGESTION_NEAR` séries ouvertes dont la
	 * récolte prévue est la plus proche (`near`), dans la fenêtre de `isNear()` (R-Retours-0610, V-Planning :
	 * « Récolte proche » s'arrête à 3 mois, à champ vide comme à la frappe). Un plafond sur la semaine couperait
	 * par l'alphabet des cultures récoltées cette semaine. Les deux groupes sont nommés ensemble, comme une seule
	 * liste.
	 */
	public static function getSuggested(\structure\Structure $eStructure, string $week): \Collection {

		$cCultivationWeek = \series\CultivationLib::getForHarvestWeek($eStructure, $week);

		$cCultivationCandidate = \series\CultivationLib::getForHarvestSuggestion($eStructure, $week, $cCultivationWeek->getIds());

		$candidates = [];

		foreach($cCultivationWeek as $eCultivation) {
			$candidates[] = self::getCandidate($eCultivation, 'week', []);
		}

		foreach($cCultivationCandidate as $eCultivation) {

			if(self::isNear($eCultivation['harvestWeeksExpected'], $week)) {
				$candidates[] = self::getCandidate($eCultivation, 'near', $eCultivation['harvestWeeksExpected']);
			}

		}

		$ranked = self::rank($candidates, $week, NULL, ['near' => PlanningSetting::SUGGESTION_NEAR]);

		$cCultivationNear = \series\CultivationLib::getByIdsForHarvestWeek($eStructure, array_keys(array_filter($ranked, fn(string $group) => $group === 'near')));

		$cCultivation = new \Collection();

		foreach($ranked as $id => $group) {
			$eCultivation = ($group === 'week') ? $cCultivationWeek[$id] : $cCultivationNear[$id];
			$eCultivation['harvestSuggestion'] = $group;
			$cCultivation[$id] = $eCultivation;
		}

		self::prepareNames($cCultivation, $week);

		return $cCultivation;

	}

	/**
	 * Les cultures d'une série, telles qu'« Espèce ou série » les propose (`getForTaskQuery()`), pour le panneau
	 * « Nouvelle intervention » ouvert depuis la fiche d'une série du plan d'assolement, qui part rempli de toute la
	 * série (GM).
	 */
	public static function getForTaskSeries(\structure\Structure $eStructure, \series\Series $eSeries, string $week): \Collection {

		$cCultivation = \series\CultivationLib::getBySeriesForHarvestWeek($eStructure, $eSeries);

		self::prepareNames($cCultivation, $week);

		return $cCultivation;

	}

	/**
	 * Les séries que propose la recherche « Espèce ou série » (retour du 05/10, lot J), rangées
	 * par distance entre la semaine affichée et leur récolte, puis comme la liste de la semaine.
	 *
	 * La récolte d'une culture, ce sont ses semaines prévues et ses semaines récoltées ensemble : une série
	 * récoltée en retard sur sa prévision reste proche de ce qu'on fait. Sans saison, seules restent les
	 * cultures dont une semaine tombe dans la fenêtre de `isNear()`, ce qui écarte celles qui n'ont aucune
	 * semaine. La saison choisie dans la rangée en tête de la liste (J-193) lève cette fenêtre et remplace
	 * les trois saisons autour de la semaine : ce qui en sort passe après, en `other`, dont le rangement
	 * retombe sur la distance puisque toutes ses séries ont la même saison, et ses cultures sans semaine
	 * passent en dernier. `$all` lève la coupe (« Voir les N autres », retour 382), pas la fenêtre.
	 *
	 * @return array [les séries retenues, combien d'autres la coupe laisse de côté]
	 */
	public static function getForTaskQuery(\structure\Structure $eStructure, string $week, string $name, ?int $season, array $cultivationsExcluded = [], bool $all = FALSE): array {

		$cCultivationCandidate = \series\CultivationLib::getForTaskQuery($eStructure, $week, $season, $name, $cultivationsExcluded);

		$candidates = [];

		foreach($cCultivationCandidate as $eCultivation) {

			$weeks = array_values(array_unique(array_merge($eCultivation['harvestWeeksExpected'] ?? [], $eCultivation['harvestWeeks'] ?? [])));

			if(self::isNear($weeks, $week)) {
				$candidates[] = self::getCandidate($eCultivation, 'near', $weeks);
			} else if($season !== NULL) {
				$candidates[] = self::getCandidate($eCultivation, 'other', $weeks);
			}

		}

		$ranked = self::rank($candidates, $week, $all ? NULL : PlanningSetting::TASK_QUERY_SERIES);

		$cCultivationFound = \series\CultivationLib::getByIdsForHarvestWeek($eStructure, array_keys($ranked));

		$cCultivation = new \Collection();

		foreach(array_keys($ranked) as $id) {
			$cCultivation[$id] = $cCultivationFound[$id];
		}

		self::prepareNames($cCultivation, $week);

		return [$cCultivation, count($candidates) - count($ranked)];

	}

	/**
	 * Un candidat des trois listes de séries (`getSuggested()`, `getForQuery()`, `getForTaskQuery()`) : son groupe,
	 * posé par l'appelant, et les semaines qui comptent pour sa distance, qui ne sont pas les mêmes d'une liste à
	 * l'autre. Une culture de la grille n'en a pas besoin : elle se range par son nom.
	 */
	private static function getCandidate(\series\Cultivation $eCultivation, string $group, array $weeks): array {

		return [
			'id' => $eCultivation['id'],
			'group' => $group,
			'weeks' => $weeks,
			'season' => $eCultivation['season'],
			'plant' => $eCultivation['plant']['name'],
			'series' => $eCultivation['series']['name'],
		];

	}

	/**
	 * Le seul rangement des trois listes de séries, et leur seule coupe (R-Retours-0610, architecte, point 5).
	 *
	 * L'ordre : le groupe, `week` puis `near` puis `other`. Dans `week`, l'ordre de la grille, par espèce puis série.
	 * Dans `near`, la distance de `getSuggestionDistance()`, puis le nom. Dans `other`, la saison la plus proche de
	 * celle de la semaine (à écart égal, la plus ancienne, comme le tri SQL qu'il remplace), puis la distance, les
	 * séries sans semaine en dernier, puis le nom.
	 *
	 * La coupe vient après l'ordre, par groupe (`$limitsByGroup`) puis sur l'ensemble (`$limit`), et les intitulés
	 * de l'autocomplétion n'y comptent pas, n'étant pas des candidats.
	 *
	 * @return array [id de culture => groupe], dans l'ordre
	 */
	private static function rank(array $candidates, string $week, ?int $limit, array $limitsByGroup = []): array {

		$groups = ['week' => 0, 'near' => 1, 'other' => 2];
		$season = week_year($week);

		$keyed = [];

		foreach($candidates as $candidate) {

			$distance = ($candidate['weeks'] === []) ? NULL : self::getSuggestionDistance($candidate['weeks'], $week);

			$candidate['key'] = match($candidate['group']) {
				'week' => [$groups['week']],
				'near' => [$groups['near'], abs($distance), ($distance < 0)],
				'other' => [$groups['other'], abs($candidate['season'] - $season), $candidate['season'], ($distance === NULL), abs($distance ?? 0), ($distance < 0)],
			};

			$keyed[] = $candidate;

		}

		usort($keyed, fn(array $candidate1, array $candidate2) => (
			$candidate1['key'] <=> $candidate2['key'] ?:
			\L::getCollator(TRUE)->compare($candidate1['plant'], $candidate2['plant']) ?:
			\L::getCollator(TRUE)->compare($candidate1['series'], $candidate2['series'])
		));

		$ranked = [];
		$counts = [];

		foreach($keyed as $candidate) {

			$group = $candidate['group'];

			if(isset($limitsByGroup[$group]) and ($counts[$group] ?? 0) >= $limitsByGroup[$group]) {
				continue;
			}

			if($limit !== NULL and count($ranked) >= $limit) {
				break;
			}

			$ranked[$candidate['id']] = $group;
			$counts[$group] = ($counts[$group] ?? 0) + 1;

		}

		return $ranked;

	}

	/**
	 * La fenêtre « proche » des trois listes : une semaine à moins de `PlanningSetting::TASK_QUERY_MONTHS` mois de
	 * la semaine affichée, d'un côté ou de l'autre. Les semaines ISO se comparent comme des chaînes.
	 */
	private static function isNear(array $weeks, string $week): bool {

		$weekFrom = toWeek(week_date_starts($week).' - '.PlanningSetting::TASK_QUERY_MONTHS.' months');
		$weekTo = toWeek(week_date_starts($week).' + '.PlanningSetting::TASK_QUERY_MONTHS.' months');

		return array_any($weeks, fn(string $weekHarvest) => ($weekHarvest >= $weekFrom and $weekHarvest <= $weekTo));

	}

	/**
	 * L'écart en semaines entre la semaine affichée et la plus proche des semaines prévues, positif quand la
	 * récolte est à venir, négatif quand elle est passée. À écart égal, celle qui commence passe avant celle
	 * qui finit.
	 */
	private static function getSuggestionDistance(array $weeks, string $week): int {

		$distances = array_map(fn(string $weekExpected) => self::getWeekRank($week, $weekExpected), $weeks);

		usort($distances, fn(int $distance1, int $distance2) => [abs($distance1), ($distance1 < 0)] <=> [abs($distance2), ($distance2 < 0)]);

		return $distances[0];

	}

	/**
	 * La culture choisie dans le panneau, nommée seule : le champ rappelle ce qu'on a choisi, sans les
	 * voisines qui départageaient les résultats de l'autocomplétion. Elle porte aussi ses choix de
	 * variété et de calibre, comme une ligne de la semaine.
	 */
	public static function prepareName(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week): void {

		$cCultivation = new \Collection([$eCultivation['id'] => $eCultivation]);

		self::prepareNames($cCultivation, $week);
		self::prepareChoices($eStructure, $cCultivation);

		$eCultivation['choiceVariety'] = ($eCultivation['cVarietyChoice']->count() > 1);

	}

	/**
	 * Le poids d'une caisse de l'espèce et le droit de le régler, que la saisie lit pour proposer les
	 * caisses : à toute espèce qui a un poids, et à celle qui n'en a pas quand on peut le régler. Ils se posent
	 * sur l'espèce reçue, celle d'une culture ou une espèce seule de « Nouvelle intervention » (BY-bis).
	 */
	public static function prepareCrate(\plant\Plant $ePlant): void {

		$ePlantCrate = \plant\PlantLib::getById($ePlant['id']);

		$ePlant['crateWeight'] = $ePlantCrate['crateWeight'];
		$ePlant['crateWrite'] = $ePlantCrate->assertWrite();
		$ePlant['crate'] = ($ePlantCrate['crateWeight'] !== NULL or $ePlant['crateWrite']);

	}

	/**
	 * L'unité de chaque couple de la culture dans la semaine, `0-0` pour « Non spécifiée » : le tiroir
	 * et le panneau changent l'unité affichée quand on change de couple, pour écrire là où
	 * `\series\TaskLib::writeHarvestForWeek()` écrira.
	 */
	public static function getEntryUnits(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, string $unit): array {

		$units = \series\TaskLib::getHarvestUnitsForWeek($eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $week)[$eCultivation['id']] ?? [];
		$units = array_map(fn($unitCouple) => $unitCouple ?? $eCultivation['mainUnit'], $units);

		$units[\series\CultivationLib::getHarvestPartKey(new \plant\Variety(), new \plant\Size())] = $unit;

		return $units;

	}

	/**
	 * Les autres unités d'une récolte que celle de la saisie (`$unit`), chacune avec l'intervention de
	 * récolte de la culture sur la semaine dans cette unité, vide quand la semaine n'en porte pas :
	 * « Nouvelle récolte » les range dans son choix, où une unité désigne son intervention (R-Retours-0810,
	 * T, piste A) ou en annonce une neuve (retour 319).
	 */
	public static function getEntryTasks(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, string $unit): array {

		$tasks = self::getTasksByUnit($eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $week)[$eCultivation['id']];

		return self::getTasksOther($tasks, $unit);

	}

	/**
	 * Les interventions de la semaine par unité (`getTasksByUnit()`) rangées dans l'ordre de l'énumération,
	 * sans `$unit`, vides pour une unité que la semaine ne porte pas.
	 */
	private static function getTasksOther(array $tasks, string $unit): array {

		$tasksByUnit = [];

		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unitOther) {

			if($unitOther !== $unit) {
				$tasksByUnit[$unitOther] = array_key_exists($unitOther, $tasks) ? $tasks[$unitOther] : new \series\Task();
			}

		}

		return $tasksByUnit;

	}

	/**
	 * Les autres unités d'une récolte que celle de la saisie (`$unit`), que « Nouvelle intervention » propose à
	 * la quantité d'une récolte faite (retour 319), dans l'ordre de l'énumération, pour une série comme pour
	 * une espèce seule : l'intervention qu'il crée les reçoit, et aucune autre n'est désignée.
	 */
	public static function getEntryTasksForCreate(string $unit): array {

		$tasksByUnit = [];

		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unitOther) {

			if($unitOther !== $unit) {
				$tasksByUnit[$unitOther] = new \series\Task();
			}

		}

		return $tasksByUnit;

	}

	/**
	 * L'intervention de récolte de la semaine de chaque culture dans chaque unité, pour un couple variété · calibre
	 * (aucun par défaut), par la règle de `\series\TaskLib::getHarvestForWeek()` appliquée unité par unité : une intervention à faire
	 * passe devant une faite, et la dernière faite devant les précédentes. L'unité d'une intervention qui n'a
	 * encore rien récolté est celle que sa saisie prendrait (`getEntryUnit()`). Chaque culture a sa liste,
	 * vide quand la semaine ne porte aucune de ses récoltes.
	 */
	private static function getTasksByUnit(\structure\Structure $eStructure, \Collection $cCultivation, string $week, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): array {

		if($cCultivation->empty()) {
			return [];
		}

		$cTask = \series\TaskLib::applyHarvestWeek(\series\Task::model(), $eStructure, $cCultivation, \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE), $week, $eVariety, $eSize)
			->select(['id', 'cultivation', 'harvestUnit', 'status'])
			->getCollection();

		$tasks = array_fill_keys($cCultivation->getIds(), []);

		foreach($cTask as $eTask) {

			$cultivation = $eTask['cultivation']['id'];
			$unit = self::getEntryUnit($cCultivation[$cultivation], $eTask);

			if(
				array_key_exists($unit, $tasks[$cultivation]) === FALSE or
				$tasks[$cultivation][$unit]['status'] === \series\Task::DONE
			) {
				$tasks[$cultivation][$unit] = $eTask;
			}

		}

		return $tasks;

	}

	/**
	 * L'intervention de récolte de la culture sur la semaine, pour un couple variété · calibre (aucun par
	 * défaut), vide quand la semaine n'en porte aucune.
	 *
	 * La carte de la Semaine d'où part une saisie (`$eTaskCard`, retour 203), ou l'intervention que désigne la
	 * saisie de l'écran des récoltes (`getTaskChosen()`), passe avant la règle quand elle en est une réponse
	 * possible : une récolte de cette culture et de cette structure, dans la semaine, du couple choisi. Deux
	 * récoltes du même couple dans la semaine ne se distinguent que par elle (point 1 du relecteur, tranché
	 * par V-Planning le 2026-10-09). L'écriture et la saisie qui l'annonce la lisent toutes deux ici :
	 * l'unité affichée est celle de l'intervention écrite.
	 */
	public static function getTaskWeek(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), \series\Task $eTaskCard = new \series\Task()): \series\Task {

		if($eTaskCard->notEmpty()) {

			$eTaskPreferred = \series\TaskLib::applyHarvestWeek(\series\Task::model(), $eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $eTaskCard['action'], $week, $eVariety, $eSize)
				->whereId($eTaskCard)
				->select(\series\Task::getSelection())
				->get();

			if($eTaskPreferred->notEmpty()) {
				return $eTaskPreferred;
			}

		}

		$cTask = \series\TaskLib::getHarvestForWeek($eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $week, $eVariety, $eSize);

		return $cTask->offsetExists($eCultivation['id']) ? $cTask[$eCultivation['id']] : new \series\Task();

	}

	/**
	 * Les personnes dont une saisie de récolte peut écrire le temps (R-Retours-0810, 224) : la personne
	 * connectée en tête, puis celles que propose le temps d'une équipe dans la semaine
	 * (`TimeTeamLib::getUsersForWeek()`, retour 254) : les personnes présentes, et celles qui ont déjà du
	 * temps sur `$cTask`, l'intervention que la saisie écrit. La personne connectée y est même quand la
	 * structure ne suit pas le temps : sa saisie de récolte en a toujours porté un, que `writeEntry()` valide
	 * par le même droit.
	 */
	public static function getTimeTeam(\structure\Structure $eStructure, string $week, \Collection $cTask = new \Collection()): \Collection {

		$eUserOnline = \user\ConnectionLib::getOnline();

		$cUserTeam = TimeTeamLib::getUsersForWeek($eStructure, $week, $cTask);

		// L'élément de la connexion sert à toute la requête : la personne connectée prend celui de l'équipe, ou
		// une copie, sur lesquels `getTimeUsers()` pose son temps.
		$cUser = new \Collection([
			$eUserOnline['id'] => $cUserTeam->offsetExists($eUserOnline['id']) ? $cUserTeam[$eUserOnline['id']] : clone $eUserOnline
		]);

		foreach($cUserTeam as $userId => $eUser) {

			if($cUser->offsetExists($userId) === FALSE) {
				$cUser[$userId] = $eUser;
			}

		}

		return $cUser;

	}

	/**
	 * Les personnes du temps d'une saisie (`getTimeTeam()`), sur le modèle de la page d'une intervention
	 * (`TaskDetailLib::getUsers()`), chacune avec :
	 * - `spent` : son temps, chaque jour de la semaine, sur l'intervention qu'écrirait la saisie (`$eTask`,
	 *   `getTaskForChoice()` ou `getTaskWeek()`), que remplace sa tuile en remplacement (R-Retours-0810, 219), 0
	 *   chaque jour quand la saisie créerait l'intervention ;
	 * - `worked` : elle a du temps sur cette intervention dans la semaine ;
	 * - `isOnline` : c'est la personne connectée ;
	 * - `timeLine` : `shown` pour la personne connectée, et en remplacement pour qui a du temps sur
	 *   l'intervention, dont on corrige alors le temps ; `hidden` sinon, et « Ajouter une personne » la montre.
	 *   En ajout, seule la personne connectée est montrée (arbitrage V-Planning du 2026-10-10) : la tuile d'un
	 *   autre partirait de zéro et n'apprendrait rien.
	 *
	 * Vide quand la structure ne suit pas le temps : la saisie n'a pas de bloc Temps, et `buildTime()` refuse
	 * tout temps posté (`Timesheet::time.untracked`).
	 *
	 * L'intervention est aussi posée sur la culture (`eTaskEntry`, pas encore écrite quand la saisie la
	 * créerait) : `getTimeKept()` doit savoir si la case du jour la compte.
	 */
	public static function getTimeUsers(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, array $days, string $mode, \series\Task $eTask = new \series\Task()): \Collection {

		if($eStructure->hasTime() === FALSE) {
			$eCultivation['eTaskEntry'] = new \series\Task();
			return new \Collection();
		}

		$eUserOnline = \user\ConnectionLib::getOnline();

		$eCultivation['eTaskEntry'] = $eTask;

		$cUser = self::getTimeTeam($eStructure, $week, $eTask->exists() ? new \Collection([$eTask['id'] => $eTask]) : new \Collection());

		$ccTimesheet = $eTask->exists() ? \series\TimesheetLib::getDaysByTask($eTask) : new \Collection();

		foreach($cUser as $userId => $eUser) {

			$spent = array_fill_keys(array_keys($days), 0.0);

			if($ccTimesheet->offsetExists($userId)) {

				foreach($ccTimesheet[$userId] as $date => $eTimesheet) {

					if(array_key_exists($date, $spent)) {
						$spent[$date] = $eTimesheet['total'];
					}

				}

			}

			$eUser['spent'] = $spent;
			$eUser['worked'] = (array_sum($spent) > 0.0);
			$eUser['isOnline'] = $eUser->is($eUserOnline);

			if($eUser['isOnline'] or ($mode === 'replace' and $eUser['worked'])) {
				$eUser['timeLine'] = 'shown';
			} else {
				$eUser['timeLine'] = 'hidden';
			}

		}

		return $cUser;

	}

	/**
	 * Le temps qu'un jour compte et que le tiroir ne remplace pas, en heures. La case du jour dit le temps de
	 * toutes les personnes sur toutes les interventions récoltées ce jour-là (`prepareTime()`), le tiroir ne
	 * montre et ne remplace que celui de ses personnes sur la sienne (`getTimeUsers()`, `spent`) : il dit le
	 * reste, et ses durées se rapprochent alors de la case. Ce reste n'est pas attribué aux seules autres
	 * interventions, parce que `getTimeTeam()` ne rend à un membre que les personnes dont il peut écrire le
	 * temps : celui d'un collègue sur l'intervention du tiroir en fait partie. `$part` est la part visée, vide
	 * pour la culture entière.
	 *
	 * La case ne compte pas toute intervention : celle d'une carte de la Semaine peut n'être ni récoltée ce
	 * jour-là ni celle que la liste désigne (`harvestTimeTasks`, `timeTasks`), et son temps, que le tiroir
	 * montre, n'a alors rien à retrancher de la case. Quand elle la compte, chaque temps se retranche arrondi
	 * au centième, comme la case l'a sommé (`prepareTime()`) : les temps de l'ancien planning ne le sont pas,
	 * et l'écart des arrondis passerait pour un reste d'une minute.
	 */
	public static function getTimeKept(\series\Cultivation $eCultivation, array $part, string $date, \Collection $cUserTime): float {

		$time = ($part === []) ? ($eCultivation['harvestTimeDays'][$date] ?? 0.0) : ($part['timeDays'][$date] ?? 0.0);
		// Une part que le couple n'a pas encore récoltée (`getPart()`) n'a aucun jour.
		$tasks = ($part === []) ? $eCultivation['harvestTimeTasks'][$date] : ($part['timeTasks'][$date] ?? []);

		$eTask = $eCultivation['eTaskEntry'];

		if($eTask->exists() === FALSE or in_array($eTask['id'], $tasks, TRUE) === FALSE) {
			return $time;
		}

		foreach($cUserTime as $eUser) {
			$time -= round($eUser['spent'][$date], 2);
		}

		return round($time, 2);

	}

	/**
	 * Ce que « Supprimer » du tiroir retirerait ce jour-là (`deleteEntry()`) : une quantité du jour dans l'une
	 * de ses unités, ou dans celle du tiroir seule quand la case désigne son intervention (`$designated`), ou le
	 * temps de ses personnes sur son intervention (`getTimeUsers()`, `spent`). Sans l'un ni l'autre, le geste
	 * n'écrirait rien, et le tiroir ne le propose pas. `$part` est la part visée, vide pour la culture entière.
	 */
	public static function hasEntry(\series\Cultivation $eCultivation, array $part, string $date, \Collection $cUserTime, bool $designated = FALSE): bool {

		$quantities = self::getDayQuantities($eCultivation, $part, $date);

		if($designated) {
			$unit = ($part === []) ? $eCultivation['harvestUnitWeek'] : $part['unit'];
			$quantities = array_intersect_key($quantities, [$unit => NULL]);
		}

		if(array_any($quantities, fn($quantity) => $quantity > 0.0)) {
			return TRUE;
		}

		return $cUserTime->find(fn(\user\User $eUser) => $eUser['spent'][$date] > 0.0)->notEmpty();

	}

	/**
	 * L'unité dite, si elle n'est pas celle de la saisie (retour 319), `NULL` sinon : l'unité de la saisie
	 * elle-même ne choisit rien, et ne vaut pas demande d'une intervention dans une autre unité. L'unité de la
	 * saisie d'un couple variété · calibre est celle de son intervention de la semaine, pas celle de la culture.
	 */
	public static function getOtherUnit(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, ?string $unit, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): ?string {

		if($unit === NULL or $unit === self::getEntryUnit($eCultivation, self::getTaskWeek($eStructure, $eCultivation, toWeek($date), $eVariety, $eSize))) {
			return NULL;
		}

		return $unit;

	}

	/**
	 * Les autres unités d'un jour que remplace le tiroir ou la feuille, chacune avec sa quantité, dans l'ordre de
	 * la case (`sortUnits()`) : la tête garde l'unité de la ligne ou de la part (`$unit`), et chaque autre unité
	 * récoltée ce jour-là a sa rangée (M-Planning, 21/10, HN-B2c). `prepareEntry()` les relit pour borner ce
	 * qu'une saisie poste (`harvestOther`).
	 */
	public static function getOthers(\series\Cultivation $eCultivation, array $part, string $date, string $unit): array {

		return array_filter(
			self::getDayQuantities($eCultivation, $part, $date),
			fn($quantity, $unitDay) => ($unitDay !== $unit and $quantity !== 0.0),
			ARRAY_FILTER_USE_BOTH
		);

	}

	/**
	 * Les quantités d'un jour de la ligne ou de la part, par unité.
	 */
	private static function getDayQuantities(\series\Cultivation $eCultivation, array $part, string $date): array {

		return ($part === []) ?
			($eCultivation['harvestedDays'][$date] ?? []) :
			($part['days'][$date] ?? []);

	}

	/**
	 * L'unité et l'intervention que désigne le choix d'une saisie, le couple construit (`$eTaskCouple`, avec
	 * `harvestUnit`) et la carte ou la case d'où elle part (`$eTaskCard`) : l'ouverture (`harvestWeek:open`) et
	 * l'écriture (`writeEntry()`) les lisent ici toutes deux, pour que le tiroir montre les durées de
	 * l'intervention qui les recevra. L'unité est celle de `getOtherUnit()`, `NULL` pour l'unité de la saisie.
	 *
	 * Une autre unité que celle de la saisie (retour 319) se choisit pour la culture entière comme pour un couple
	 * (21/10, « Autoriser », HN-B2c), qui la compare à l'unité de sa part (`getOtherUnit()`), jamais
	 * depuis une intervention désignée, qui a la sienne. Le couple se lit construit, le panneau postant ses deux
	 * listes même laissées sur « Toutes ». Le refus reste dans le `FailWatch` de l'appelant, qui le valide avant
	 * de lire l'intervention : elle est alors vide.
	 *
	 * @return array L'unité, puis l'intervention de `getTaskForEntry()`.
	 */
	public static function getTaskForChoice(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, \series\Task $eTaskCouple, \series\Task $eTaskCard = new \series\Task()): array {

		$harvestUnit = $eTaskCouple['harvestUnit'];

		if($harvestUnit !== NULL) {

			if($eTaskCard->notEmpty()) {
				\series\Task::fail('harvestUnit.couple');
				return [NULL, new \series\Task()];
			} else {
				$harvestUnit = self::getOtherUnit($eStructure, $eCultivation, $date, $harvestUnit, $eTaskCouple['variety'], $eTaskCouple['harvestSize']);
			}

		}

		return [
			$harvestUnit,
			self::getTaskForEntry($eStructure, $eCultivation, $date, $harvestUnit, $eTaskCouple['variety'], $eTaskCouple['harvestSize'], $eTaskCard)
		];

	}

	/**
	 * L'intervention qu'une saisie de récolte complète, au jour dit : une seule règle pour toute écriture de
	 * récolte à la semaine, faute de quoi deux écrans saisissant la même récolte écriraient sur deux interventions.
	 *
	 * `$unitOther` est l'unité que rend `getOtherUnit()`. Sans elle, l'intervention est celle de
	 * `getTaskForWrite()` ; avec elle, celle de `getTaskForUnit()`, du même couple, qui ne connaît pas de
	 * carte : une carte a son unité, et `getTaskForChoice()` refuse ce cas avant (`harvestUnit.couple`).
	 */
	public static function getTaskForEntry(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, ?string $unitOther, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), \series\Task $eTaskCard = new \series\Task()): \series\Task {

		if($unitOther === NULL) {
			return self::getTaskForWrite($eStructure, $eCultivation, $date, $eVariety, $eSize, $eTaskCard);
		} else {
			return self::getTaskForUnit($eStructure, $eCultivation, $date, $unitOther, $eVariety, $eSize);
		}

	}

	/**
	 * L'intervention que la saisie complète : celle de la semaine du jour saisi, du couple variété · calibre
	 * choisi (aucun par défaut), si elle existe, sinon une intervention neuve de ce couple, construite comme `\entry\HarvestIntentionLib::prepare()`
	 * la construit (`buildTask()`), dans l'unité principale de la culture. Les refus de `build()` restent
	 * dans le `FailWatch` de la page.
	 *
	 * La neuve est toujours à faire, planifiée au jour saisi, avant même que la quantité soit connue :
	 * `\series\TaskLib::writeHarvestForWeek()` la termine par sa règle ordinaire dès qu'une quantité positive
	 * lui est ajoutée, et `updateDoneCollection()` la date alors du jour planifié. Sur un temps seul,
	 * elle reste à faire dans la semaine, où `\series\TaskLib::getHarvestForWeek()` la retrouve
	 * (arbitrage V-Planning du 2026-09-30, option A, dans la forme demandée par l'architecte).
	 *
	 * La carte de la Semaine d'où part la saisie (`$eTaskCard`) passe avant la règle, à la condition que
	 * pose `getTaskWeek()` sur la semaine du jour saisi.
	 */
	public static function getTaskForWrite(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), \series\Task $eTaskCard = new \series\Task()): \series\Task {

		$eTaskWeek = self::getTaskWeek($eStructure, $eCultivation, toWeek($date), $eVariety, $eSize, $eTaskCard);

		if($eTaskWeek->notEmpty()) {
			return $eTaskWeek;
		}

		return self::buildTask($eStructure, $eCultivation, $date, $eCultivation['mainUnit'], $eVariety, $eSize);

	}

	/**
	 * La valeur de la case d'une ligne des Récoltes (FD, retour 405) : sa culture et son couple variété · calibre,
	 * `<culture>-<variété>-<calibre>`, zéro pour ce qui manque. La culture entière, sans part, vaut `<culture>-0-0`.
	 * `getBatchCouples()` la relit.
	 */
	public static function getBatchValue(\series\Cultivation $eCultivation, \plant\Variety $eVariety, \plant\Size $eSize): string {
		return $eCultivation['id'].'-'.\series\CultivationLib::getHarvestPartKey($eVariety, $eSize);
	}

	/**
	 * Les couples que cochent les cases de « Récolter » (`getBatchValue()`), par valeur. Chacun se relit en entiers
	 * et se réécrit par `getBatchValue()` avant que les doublons ne s'écartent : `05-0-0` et `5-0-0` désignent la
	 * même intervention, qui recevrait deux parts de la quantité et du temps.
	 *
	 * @return array Par valeur, la culture, la variété et le calibre en identifiants, `NULL` pour ce qui manque, et la clé de la part (`part`).
	 */
	public static function getBatchCouples(array $values): array {

		$couples = [];

		foreach($values as $value) {

			if(is_string($value) === FALSE or preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)$/', $value, $match) === 0) {
				throw new \NotExpectedAction('Invalid couple');
			}

			$eCultivation = new \series\Cultivation(['id' => (int)$match[1]]);
			$eVariety = ((int)$match[2] === 0) ? new \plant\Variety() : new \plant\Variety(['id' => (int)$match[2]]);
			$eSize = ((int)$match[3] === 0) ? new \plant\Size() : new \plant\Size(['id' => (int)$match[3]]);

			$couples[self::getBatchValue($eCultivation, $eVariety, $eSize)] = [
				'cultivation' => $eCultivation['id'],
				'variety' => $eVariety->empty() ? NULL : $eVariety['id'],
				'harvestSize' => $eSize->empty() ? NULL : $eSize['id'],
				'part' => \series\CultivationLib::getHarvestPartKey($eVariety, $eSize),
			];

		}

		if($couples === []) {
			throw new \NotExpectedAction('No couple');
		}

		return $couples;

	}

	/**
	 * La sonde de chaque couple coché (`getCoupleProbe()`), par valeur, sur sa culture de la liste de la semaine.
	 * Le couple est une ligne que l'écran montre et qui se saisit : une part `writable` de sa culture, « Non
	 * spécifiée » comprise quand elle y est, ou la culture entière quand elle n'a aucune part. Puis la variété et
	 * le calibre passent par `build()`, comme la saisie d'une part (`writeEntry()`).
	 */
	public static function getBatchProbes(\structure\Structure $eStructure, \Collection $cCultivation, array $couples): \Collection {

		$fw = new \FailWatch();

		$cTaskCouple = new \Collection();

		foreach($couples as $value => $couple) {

			$eCultivation = $cCultivation[$couple['cultivation']];
			$parts = $eCultivation['harvestedParts'];

			if($parts === []) {
				$shown = ($couple['part'] === \series\CultivationLib::getHarvestPartKey(new \plant\Variety(), new \plant\Size()));
			} else {
				$shown = (array_key_exists($couple['part'], $parts) and $parts[$couple['part']]['writable']);
			}

			if($shown === FALSE) {
				throw new \FailAction('series\Cultivation::cultivations.part');
			}

			$eTaskCouple = self::getCoupleProbe($eStructure, $eCultivation);
			$eTaskCouple->build(['variety', 'harvestSize'], $couple);

			// `build()` ne pose que les identifiants : la part porte les mêmes, avec les noms que le panneau dit.
			if($parts !== []) {
				$eTaskCouple['variety'] = $parts[$couple['part']]['eVariety'];
				$eTaskCouple['harvestSize'] = $parts[$couple['part']]['eSize'];
			}

			$cTaskCouple[$value] = $eTaskCouple;

		}

		$fw->validate();

		return $cTaskCouple;

	}

	/**
	 * Le couple que « Récolter » propose de choisir aux Récoltes (HM2), porté par une culture sonde comme celle de
	 * la feuille (`HarvestEntryUi::getChoiceFields()`) : les variétés communes à toutes les cultures cochées
	 * (`cVarietyChoice`), les calibres de leur espèce (`cSizeChoice`) et le couple des lignes (`eVariety`,
	 * `eSize`), qui pré-remplit les champs. Elle est vide quand les lignes portent des couples différents : chacune
	 * garde alors le sien, que sa part nomme déjà (FD), et vide aussi quand il n'y a rien à choisir. Des espèces
	 * différentes n'offrent rien non plus : les calibres sont ceux d'une espèce, et `TaskBatchLib::validateHarvestsCouples()`
	 * refuse ensuite le mélange.
	 */
	public static function getBatchChoices(\Collection $cTaskCouple): \series\Cultivation {

		$eTaskReference = $cTaskCouple->first();

		$mixed = $cTaskCouple->contains(fn($eTaskCouple) => (
			$eTaskCouple['plant']->is($eTaskReference['plant']) === FALSE or
			$eTaskCouple['variety']->is($eTaskReference['variety']) === FALSE or
			$eTaskCouple['harvestSize']->is($eTaskReference['harvestSize']) === FALSE
		));

		if($mixed) {
			return new \series\Cultivation();
		}

		$cVariety = $eTaskReference['cultivation']['cVarietyChoice'];

		foreach($cTaskCouple as $eTaskCouple) {
			$cVariety = $cVariety->find(fn($eVariety) => $eTaskCouple['cultivation']['cVarietyChoice']->offsetExists($eVariety['id']));
		}

		$cSize = $eTaskReference['cultivation']['cSizeChoice'];

		if($cVariety->empty() and $cSize->empty()) {
			return new \series\Cultivation();
		}

		return new \series\Cultivation([
			'plant' => $eTaskReference['plant'],
			'cVarietyChoice' => $cVariety,
			'cSizeChoice' => $cSize,
			'choiceVariety' => $cVariety->notEmpty(),
			'eVariety' => $eTaskReference['variety'],
			'eSize' => $eTaskReference['harvestSize'],
		]);

	}

	/**
	 * Le couple choisi dans « Récolter » (HM2) remplace celui de chaque ligne, avant que `getTasksForBatch()` ne
	 * lise l'intervention qu'il vise. Seuls les champs que le panneau montre se construisent (`$eCultivationChoice`) :
	 * `build()` lirait un champ absent comme un champ vidé. Le schéma refuse une variété qu'une culture n'a pas et
	 * un calibre d'une autre espèce ; la variété et le calibre reprennent ensuite leur nom, que `build()` ne pose pas.
	 */
	public static function applyBatchCouple(\Collection $cTaskCouple, \series\Cultivation $eCultivationChoice, array $input): void {

		if($eCultivationChoice->empty()) {
			return;
		}

		$properties = [];

		if($eCultivationChoice['choiceVariety']) {
			$properties[] = 'variety';
		}

		if($eCultivationChoice['cSizeChoice']->notEmpty()) {
			$properties[] = 'harvestSize';
		}

		$fw = new \FailWatch();

		foreach($cTaskCouple as $eTaskCouple) {
			$eTaskCouple->build($properties, $input);
		}

		$fw->validate();

		foreach($cTaskCouple as $eTaskCouple) {

			if($eTaskCouple['variety']->notEmpty()) {
				$eTaskCouple['variety'] = $eCultivationChoice['cVarietyChoice'][$eTaskCouple['variety']['id']];
			}

			if($eTaskCouple['harvestSize']->notEmpty()) {
				$eTaskCouple['harvestSize'] = $eCultivationChoice['cSizeChoice'][$eTaskCouple['harvestSize']['id']];
			}

		}

	}

	/**
	 * Les interventions qu'écrit « Récolter » sur les lignes cochées de l'écran (DG-2, FD), une par couple et
	 * indexées par sa valeur (`getBatchValue()`) : celle que la saisie de la ligne écrirait (`getTaskForWrite()`),
	 * sinon une neuve planifiée à `$date`, le jour où la semaine crée (`getCreateDate()`), que
	 * `\series\TaskLib::writeHarvestForWeek()` créera puis replanifiera au jour récolté. Les cultures entières se
	 * lisent ensemble, chaque autre couple par sa propre lecture. Chacune passe le droit de la saisie
	 * (`writeEntry()`), la création pour une neuve, l'écriture pour une existante.
	 *
	 * Une neuve n'a encore rien récolté : `harvestDates` est vide, ce que `\series\TaskLib::fillHarvestDates()`
	 * ne peut pas lire en base pour une intervention sans identifiant.
	 */
	public static function getTasksForBatch(\structure\Structure $eStructure, \Collection $cTaskCouple, string $week, string $date): \Collection {

		$fw = new \FailWatch();

		$cCultivationWhole = new \Collection();

		foreach($cTaskCouple as $eTaskCouple) {
			if($eTaskCouple['variety']->empty() and $eTaskCouple['harvestSize']->empty()) {
				$cCultivationWhole[$eTaskCouple['cultivation']['id']] = $eTaskCouple['cultivation'];
			}
		}

		$cTaskWeek = \series\TaskLib::getHarvestForWeek($eStructure, $cCultivationWhole, $week);

		$cTask = new \Collection();

		foreach($cTaskCouple as $value => $eTaskCouple) {

			$eCultivation = $eTaskCouple['cultivation'];

			if($eTaskCouple['variety']->empty() and $eTaskCouple['harvestSize']->empty()) {
				$eTask = $cTaskWeek->offsetExists($eCultivation['id']) ? $cTaskWeek[$eCultivation['id']] : new \series\Task();
			} else {
				$eTask = self::getTaskWeek($eStructure, $eCultivation, $week, $eTaskCouple['variety'], $eTaskCouple['harvestSize']);
			}

			if($eTask->notEmpty()) {

				$eTask->validate('canWrite', 'acceptWrite');

			} else {

				$eTask = self::buildTask($eStructure, $eCultivation, $date, $eCultivation['mainUnit'], $eTaskCouple['variety'], $eTaskCouple['harvestSize']);
				// Les éléments du couple, qui portent leur nom (`getBatchProbes()`), là où `build()` n'a posé que leur identifiant.
				$eTask['variety'] = $eTaskCouple['variety'];
				$eTask['harvestSize'] = $eTaskCouple['harvestSize'];
				$eTask['harvestDates'] = [];
				$eTask->validate('canCreate', 'acceptCreate');

			}

			$cTask[$value] = $eTask;

		}

		$fw->validate();

		return $cTask;

	}

	/**
	 * L'unité de « Récolter » sur les lignes cochées : celle où chaque ligne saisit (`getEntryUnit()`), lue sur
	 * l'intervention de son couple, qui doit être commune. Elle ne se choisit pas : une autre unité poserait sur
	 * l'intervention de la semaine une unité qui la ferait sortir de sa ligne (retour 319,
	 * `\series\TaskLib::getHarvestForWeek()`).
	 */
	public static function getBatchUnit(\Collection $cTaskCouple, \Collection $cTask): string {

		$units = [];

		foreach($cTaskCouple as $value => $eTaskCouple) {
			$units[] = self::getEntryUnit($eTaskCouple['cultivation'], $cTask[$value]);
		}

		$units = array_unique($units);

		if(count($units) > 1) {
			throw new \FailAction('series\Task::harvestConsistency.check');
		}

		return $units[0];

	}

	/**
	 * Ce qui fonde la part de chaque ligne cochée aux Récoltes (arbitrage du 16/10, « partage par
	 * surface ») : `\planning\TaskBatchLib::fillBases()` donne à une récolte la surface de sa variété, ou celle de
	 * la série sans variété, et ses plants. Les calibres d'une même variété d'une culture se partagent donc cette
	 * surface et ces plants à parts égales, sans quoi la variété compterait autant de fois qu'elle a de calibres.
	 * Les bases se lisent après `fillTimes()`, qui les pose.
	 */
	public static function fillBatchBases(\Collection $cTask): void {

		$counts = [];

		foreach($cTask as $eTask) {
			$key = self::getBatchBaseKey($eTask);
			$counts[$key] = ($counts[$key] ?? 0) + 1;
		}

		foreach($cTask as $eTask) {

			$count = $counts[self::getBatchBaseKey($eTask)];

			if($eTask['distributionArea'] !== NULL) {
				$eTask['distributionArea'] /= $count;
			}

			if($eTask['distributionPlant'] !== NULL) {
				$eTask['distributionPlant'] /= $count;
			}

		}

	}

	private static function getBatchBaseKey(\series\Task $eTask): string {
		return $eTask['cultivation']['id'].'-'.($eTask['variety']->empty() ? '0' : $eTask['variety']['id']);
	}

	/**
	 * L'intervention que complète une saisie dans une autre unité que celle de la semaine (retour 319), de la
	 * culture entière ou d'un couple variété · calibre (HN-B2b, 21/10, « Autoriser »), où
	 * l'intervention de la semaine n'est jamais (`\series\TaskLib::getHarvestForWeek()`) : celle que la
	 * semaine du jour saisi porte déjà dans cette unité pour ce couple, lue ici et non crue de la saisie ;
	 * sinon l'intervention de la semaine du couple tant qu'elle n'a rien récolté, qui prend l'unité à sa
	 * première quantité comme dans le logiciel classique (`\series\TaskLib::buildHarvests()`) ; sinon une
	 * neuve du couple, dans l'unité.
	 */
	public static function getTaskForUnit(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, string $unit, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): \series\Task {

		$week = toWeek($date);

		$tasks = self::getTasksByUnit($eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $week, $eVariety, $eSize)[$eCultivation['id']];

		if(array_key_exists($unit, $tasks)) {
			return self::getTaskWeek($eStructure, $eCultivation, $week, $eVariety, $eSize, self::getTaskCard($eStructure, $eCultivation, $tasks[$unit]['id']));
		}

		$eTaskWeek = self::getTaskWeek($eStructure, $eCultivation, $week, $eVariety, $eSize);

		if($eTaskWeek->notEmpty() and $eTaskWeek['harvestUnit'] === NULL) {
			return $eTaskWeek;
		}

		return self::buildTask($eStructure, $eCultivation, $date, $unit, $eVariety, $eSize);

	}

	/**
	 * Une intervention de récolte neuve de la culture, pas encore écrite, dans l'unité donnée.
	 */
	private static function buildTask(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, string $unit, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size()): \series\Task {

		$eSeries = \series\SeriesLib::getById($eCultivation['series']['id']);

		$eTask = new \series\Task([
			'structure' => $eStructure,
			'category' => \work\CategoryLib::getByStructure($eStructure, fqn: CATEGORIE_CULTURE),
			'series' => $eSeries,
			'season' => $eSeries['season'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'status' => \series\Task::TODO,
			'harvest' => NULL,
		]);

		$eTask->build(\series\TaskLib::getPropertiesCreate()($eTask), [
			'action' => \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE)['id'],
			'planned' => ['date' => $date],
			'harvestUnit' => $unit,
			'variety' => $eVariety->empty() ? NULL : $eVariety['id'],
			'harvestSize' => $eSize->empty() ? NULL : $eSize['id'],
		], new \PropertyList('create'));

		return $eTask;

	}

	/**
	 * La carte de la Semaine d'où part une saisie (retour 203). Une carte d'une autre structure, ou que
	 * l'utilisateur ne peut pas écrire, lève dès la lecture. Une carte qui n'est pas une récolte de cette
	 * culture rend un élément vide, que la page refuse : elle n'est jamais ignorée en silence.
	 */
	public static function getTaskCard(\structure\Structure $eStructure, \series\Cultivation $eCultivation, int $id): \series\Task {

		$eTask = \series\TaskLib::getById($id)
			->validate('canWrite')
			->validateProperty('structure', $eStructure);

		if(
			$eTask['action']['fqn'] !== ACTION_RECOLTE or
			$eCultivation->empty() or
			$eTask['cultivation']->is($eCultivation) === FALSE
		) {
			return new \series\Task();
		}

		return $eTask;

	}

	/**
	 * L'intervention qu'une saisie de l'écran des récoltes désigne (`task`), vide quand elle n'en désigne
	 * aucune : la case d'un jour dans une autre unité que celle de la semaine (`prepareTask()`), ou une autre
	 * unité de « Nouvelle récolte » (`getEntryTasks()`). Elle se lit comme la carte de la Semaine
	 * (`getTaskCard()`), et se refuse dans le `FailWatch` de la page, qui le valide, quand elle n'est pas une
	 * récolte de la culture entière dans la semaine de l'écran. Au contraire d'une carte en retard, elle ne
	 * retombe jamais sur la règle de la semaine : l'unité choisie est la sienne, et une autre intervention
	 * recevrait la quantité dans une autre unité.
	 *
	 * Une autre unité choisie sur un couple ne désigne rien (HN-B2c) : l'intervention de l'unité est celle du
	 * couple (`getTaskForChoice()`), jamais celle de la culture entière que l'option du champ porte.
	 */
	public static function getTaskChosen(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, array $input): \series\Task {

		$id = var_filter($input['task'] ?? NULL, '?int');
		$couple = (($input['variety'] ?? '') !== '' or ($input['harvestSize'] ?? '') !== '');

		if($id === NULL or ($couple and ($input['harvestUnit'] ?? '') !== '')) {
			return new \series\Task();
		}

		$eTask = self::getTaskCard($eStructure, $eCultivation, $id);

		if(
			$eTask->empty() or
			\series\TaskLib::applyHarvestWeek(\series\Task::model(), $eStructure, new \Collection([$eCultivation['id'] => $eCultivation]), $eTask['action'], $week)
				->exists($eTask) === FALSE
		) {
			\series\Task::fail('cultivation.harvest');
			return new \series\Task();
		}

		return $eTask;

	}

	/**
	 * Une saisie de récolte, telle que la postent le tiroir, la feuille et le panneau « Nouvelle récolte » :
	 * depuis l'écran des récoltes (`harvestWeek:doHarvest`) comme depuis une carte de la Semaine
	 * (`taskWeek:doHarvest`, retour 203), deux routes qui ne répondent pas le même écran, et une seule
	 * écriture. `$days` est la semaine affichée, à laquelle toute saisie appartient (arbitrage V-Planning du
	 * 2026-09-30) : ailleurs, elle s'écrirait sans que rien ne la montre.
	 *
	 * Chaque étape lit ce que la précédente a construit, d'où un `FailWatch` validé à chaque étape : une
	 * étape refusée arrête la saisie avant la suivante, et ses refus remontent à la page comme ceux de
	 * `build()`.
	 *
	 * Rend l'intervention écrite (`eTask`), si la saisie l'a créée (`created`), le jour de la récolte
	 * (`date`), le mode de la saisie (`mode`), l'unité de la saisie (`unit`), la quantité écrite
	 * (`quantity`) et le couple variété · calibre visé (`eTaskCouple`). `$eTaskCard` est l'intervention que
	 * la saisie désigne, déjà lue : la carte de la Semaine (`getTaskCard()`), ou la case et l'unité choisies
	 * sur l'écran des récoltes (`getTaskChosen()`), vide quand rien n'est désigné. Une unité que la semaine
	 * ne porte pas encore ne désigne rien et se poste (`harvestUnit`, `getTaskForUnit()`).
	 *
	 * La quantité écrite, signée, va au stock que la saisie a choisi (`HarvestStockLib`, DG-4) : un retrait ou un
	 * remplacement qui baisse le jour le baisse d'autant. Sans `$stock`, la saisie n'en touche aucun, son appelant
	 * l'écrivant lui-même (`deleteEntry()`).
	 */
	public static function writeEntry(\structure\Structure $eStructure, \series\Cultivation $eCultivation, array $days, array $input, \series\Task $eTaskCard = new \series\Task(), bool $stock = TRUE, bool $others = FALSE): array {

		return self::writePreparedEntry(self::prepareEntry($eStructure, $eCultivation, $days, $input, $eTaskCard, $stock, $others));

	}

	/**
	 * Tout ce que `writeEntry()` lit et refuse, sans rien écrire : un refus levé dans une transaction ouverte la
	 * laisserait ouverte, `rollBack()` remettant à zéro toute la pile.
	 *
	 * @return array ce que `writePreparedEntry()` écrit
	 */
	protected static function prepareEntry(\structure\Structure $eStructure, \series\Cultivation $eCultivation, array $days, array $input, \series\Task $eTaskCard = new \series\Task(), bool $stock = TRUE, bool $others = FALSE): array {

		$fw = new \FailWatch();

		$mode = var_filter($input['mode'] ?? NULL, ['add', 'replace'], 'add');

		$property = match($mode) {
			'add' => 'harvestMore',
			'replace' => 'harvestDay',
		};

		[
			'date' => $date,
			'part' => $part,
			'eTaskCouple' => $eTaskCouple,
			'harvestUnit' => $harvestUnit,
			'eTask' => $eTask,
			'cTimesheet' => $cTimesheet,
			'created' => $created,
		] = self::buildEntryTask($eStructure, $eCultivation, $days, $input, $eTaskCard, $mode);

		$eTask->build([$property], $input, new \PropertyList('week'));

		$fw->validate();

		// Un ajout sans quantité ni temps n'écrirait rien qu'une intervention vide, quand un remplacement par 0
		// vide le jour.
		$empty = match($mode) {
			'add' => ($eTask[$property] === 0.0 and $cTimesheet->empty()),
			'replace' => FALSE,
		};

		// Une intervention neuve dans une autre unité, sans quantité, ne porterait que du temps : rien ne la
		// montrerait, l'écran lisant l'intervention de la semaine et les jours récoltés.
		if($empty) {
			\series\Task::fail($property.'.empty');
		} else if($created and $harvestUnit !== NULL and $eTask[$property] === 0.0) {
			\series\Task::fail('harvestUnit.quantity');
		}

		$fw->validate();

		$ePlantCrate = self::buildCrate($eCultivation['plant'], $input);

		$unit = $eTask['harvestUnit'] ?? $harvestUnit ?? $eCultivation['mainUnit'];

		// La récolte que le champ du stock a lue à l'ouverture (`harvestWeek:open`) : l'intervention désignée, sinon
		// le couple choisi, dans l'unité de la saisie.
		$eTaskStock = HarvestStockLib::getReference($eTaskCard->empty() ? $eTaskCouple : $eTask, $unit);

		[$eProductStock, $stockRemember] = $stock ?
			HarvestStockLib::build($eStructure, $eTaskStock, $input) :
			[new \product\Product(), NULL];

		$fw->validate();

		$harvest = \series\TaskLib::prepareHarvestForWeek($eTask, $cTimesheet, $date, $mode, $part, $ePlantCrate->empty() ? NULL : $ePlantCrate['crateWeight'], $harvestUnit);

		$harvestsOther = self::prepareOthers($eStructure, $eCultivation, $input, $date, $mode, $part, $eTaskCouple, $eTask, $unit, $others);

		return [
			'harvest' => $harvest,
			'harvestsOther' => $harvestsOther,
			'ePlantCrate' => $ePlantCrate,
			// La quantité saisie, que la préparation a convertie de caisses en kilos.
			'quantityEntered' => $eTask[$property],
			'eTaskStock' => $eTaskStock,
			'eProductStock' => $eProductStock,
			'stockRemember' => $stockRemember,
			'eTask' => $eTask,
			'created' => $created,
			'date' => $date,
			'mode' => $mode,
			'unit' => $unit,
			'eTaskCouple' => $eTaskCouple,
		];

	}

	/**
	 * Les rangées des autres unités du remplacement d'un jour (`getOthers()`, HN-B2c), postées en
	 * `harvestOther[<unité>]` : chacune remplace la quantité du jour de la ligne ou de la part dans son unité, sur
	 * l'intervention de son unité (`getTaskForEntry()`), sans temps, caisses ni stock, que la tête porte seule. Seul
	 * l'écran des récoltes les rend (`$others`) : une carte de la Semaine ou de l'emplacement écrit dans
	 * l'intervention qu'elle désigne. Les unités se relisent en base, l'écran pouvant dater, et une rangée qui
	 * retomberait sur l'intervention de la tête écrirait deux fois sur elle : l'écran ne les poste pas, elles
	 * se refusent sans message.
	 *
	 * @return array les récoltes préparées par `TaskLib::prepareHarvestForWeek()`, une par unité
	 */
	private static function prepareOthers(\structure\Structure $eStructure, \series\Cultivation $eCultivation, array $input, string $date, string $mode, bool $part, \series\Task $eTaskCouple, \series\Task $eTask, string $unit, bool $others): array {

		$posted = $input['harvestOther'] ?? [];

		if($posted === []) {
			return [];
		}

		if(is_array($posted) === FALSE or $others === FALSE or $mode !== 'replace') {
			throw new \NotExpectedAction('Other units not expected');
		}

		$units = array_diff_key(self::getHarvestedUnits($eStructure, $eCultivation, $date, $eTaskCouple, $part), [$unit => NULL]);

		if(array_diff_key($posted, $units) !== []) {
			throw new \NotExpectedAction('Other unit not harvested this day');
		}

		$harvestsOther = [];

		foreach(array_keys($posted) as $unitOther) {

			$eTaskOther = self::getTaskForEntry($eStructure, $eCultivation, $date, $unitOther, $eTaskCouple['variety'], $eTaskCouple['harvestSize']);

			if($eTaskOther->exists() === FALSE or $eTaskOther->is($eTask)) {
				throw new \NotExpectedAction('Other unit on the task of the head');
			}

			$eTaskOther->validate('canWrite', 'acceptWrite');

			$fw = new \FailWatch();

			$eTaskOther->buildIndex(['harvestDay'], ['harvestDay' => $posted], $unitOther, new \PropertyList('week'));

			$fw->validate();

			$harvestsOther[] = \series\TaskLib::prepareHarvestForWeek($eTaskOther, new \Collection(), $date, 'replace', $part, wrapper: 'harvestDay['.$unitOther.']');

		}

		return $harvestsOther;

	}

	/**
	 * Les quantités récoltées d'un jour par unité, en base, pour la culture entière ou pour le couple (`$part`).
	 */
	private static function getHarvestedUnits(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, \series\Task $eTaskCouple, bool $part): array {

		$units = [];

		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unit) {

			$mHarvest = $part ?
				\series\TaskLib::applyHarvestedDayCouple(\series\Harvest::model(), $eStructure, $eCultivation, $date, $unit, $eTaskCouple['variety'], $eTaskCouple['harvestSize']) :
				\series\TaskLib::applyHarvestedDay(\series\Harvest::model(), $eStructure, $eCultivation, $date, $unit);

			$quantity = round($mHarvest->select(['total' => new \Sql('IFNULL(SUM(quantity), 0)', 'float')])->get()['total'], 3);

			if($quantity !== 0.0) {
				$units[$unit] = $quantity;
			}

		}

		return $units;

	}

	/**
	 * L'écriture d'une saisie préparée par `prepareEntry()`, qui ne refuse plus rien. Rend ce que rend `writeEntry()`.
	 */
	protected static function writePreparedEntry(array $entry): array {

		\series\Task::model()->beginTransaction();

			$quantity = \series\TaskLib::writePreparedHarvestForWeek($entry['harvest']);

			foreach($entry['harvestsOther'] as $harvestOther) {
				\series\TaskLib::writePreparedHarvestForWeek($harvestOther);
			}

			self::writeCrate($entry['ePlantCrate'], $entry['quantityEntered']);

			HarvestStockLib::write($entry['eTaskStock'], $entry['eProductStock'], $entry['stockRemember'], $quantity);

		\series\Task::model()->commit();

		return [
			'eTask' => $entry['eTask'],
			'created' => $entry['created'],
			'date' => $entry['date'],
			'mode' => $entry['mode'],
			'unit' => $entry['unit'],
			'quantity' => $quantity,
			'eTaskCouple' => $entry['eTaskCouple'],
		];

	}

	/**
	 * Ce que `prepareEntry()` construit avant la quantité : le jour, le couple visé, l'unité et l'intervention que
	 * la saisie écrit, et le temps de chacun sur elle, avec les droits sur l'intervention.
	 * `$mode` est celui que le temps applique (`buildTime()`, `checkTimeDay()`).
	 *
	 * Chaque étape lit ce que la précédente a construit, d'où un `FailWatch` validé à chaque étape.
	 */
	protected static function buildEntryTask(\structure\Structure $eStructure, \series\Cultivation $eCultivation, array $days, array $input, \series\Task $eTaskCard, string $mode): array {

		$fw = new \FailWatch();

		[$date, $eTimesheet] = self::buildTimeDate($eStructure, $days, $input);

		$eTaskCouple = self::getCoupleProbe($eStructure, $eCultivation);
		$eTaskCouple->build(['variety', 'harvestSize', 'harvestUnit'], $input);

		$fw->validate();

		$part = (isset($input['variety']) or isset($input['harvestSize']));

		[$harvestUnit, $eTask] = self::getTaskForChoice($eStructure, $eCultivation, $date, $eTaskCouple, $eTaskCard);

		$fw->validate();

		// Le temps se construit sur l'intervention que la saisie écrit : qui y a déjà du temps garde le droit de
		// le corriger, présent ou non dans la semaine (`getTimeTeam()`).
		$cTimesheet = self::buildTime($eStructure, $eTask, $eTimesheet['date'], $mode, $input['time'] ?? []);

		$fw->validate();

		self::checkTimeDay($eStructure, $eTask, $cTimesheet, $mode);

		$fw->validate();

		$created = ($eTask->exists() === FALSE);

		if($created) {
			$eTask->validate('canCreate', 'acceptCreate');
		} else {
			$eTask->validate('canWrite', 'acceptWrite');
		}

		return [
			'date' => $date,
			'part' => $part,
			'eTaskCouple' => $eTaskCouple,
			'harvestUnit' => $harvestUnit,
			'eTask' => $eTask,
			'cTimesheet' => $cTimesheet,
			'created' => $created,
		];

	}

	/**
	 * « Supprimer » du tiroir d'un jour : la quantité du jour part de chaque intervention qui la porte, dans
	 * chacune de ses unités, ou dans celle de l'intervention que la case désigne, et pour son couple, puis le
	 * temps de chaque personne que le tiroir montre avec du temps ce jour-là (`getTimeUsers()`, `spent`). Le tiroir affiche le total du jour, que plusieurs interventions de
	 * la culture peuvent se partager : un remplacement par 0 poserait tout l'écart sur une seule, qui ne peut
	 * pas rendre plus que sa part (`Task::harvestDates.negative`). Chaque intervention rend donc la sienne, par
	 * un ajout négatif.
	 *
	 * Tout passe par `writeEntry()` : la même validation et les mêmes droits. Le stock que montre le tiroir rend
	 * la somme des quantités parties dans l'unité de la saisie, en une fois (`HarvestStockLib::write()`). Le temps que le
	 * tiroir ne montre pas (`getTimeKept()`) reste. Ce qui peut refuser une intervention se vérifie sur toutes
	 * avant la première écriture, et non dans une transaction qui les couvrirait : `rollBack()` remet à zéro
	 * toute la pile, celle de l'appelant comprise.
	 *
	 * Une intervention que la suppression laisse sans rien part ensuite du planning
	 * (`TaskLib::deleteHarvestShells()`, consigne du 2026-10-19) ; celle qui garde du temps un autre
	 * jour, une note ou du matériel reste. Le stock est déjà écrit quand elle part : une panne entre les deux
	 * laisse l'intervention vide, comme avant cette suppression.
	 *
	 * Une case sans intervention ni quantité n'a rien à supprimer, et `writeEntry()` en créerait une pour y
	 * écrire 0. Sans intervention de la semaine, la quantité du jour est celle d'interventions d'une autre
	 * semaine, que la boucle refuse une à une comme les autres.
	 */
	public static function deleteEntry(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $week, array $days, array $input, \series\Task $eTaskChosen = new \series\Task()): void {

		$fw = new \FailWatch();

		[$date] = self::buildTimeDate($eStructure, $days, $input);

		$eTaskCouple = self::getCoupleProbe($eStructure, $eCultivation);
		$eTaskCouple->build(['variety', 'harvestSize'], $input);

		$fw->validate();

		$eTask = self::getTaskWeek($eStructure, $eCultivation, $week, $eTaskCouple['variety'], $eTaskCouple['harvestSize'], $eTaskChosen);

		$couple = array_intersect_key($input, ['variety' => NULL, 'harvestSize' => NULL]);
		$unit = self::getEntryUnit($eCultivation, $eTask);

		$tasks = [];

		// « Supprimer » vide le jour dans toutes ses unités (M-Planning, 21/10, HN-B2c), sauf depuis une case qui
		// désigne son intervention, qui ne montre que la sienne.
		$units = $eTaskChosen->empty() ?
			\series\Task::model()->getPropertyEnum('harvestUnit') :
			[$unit];

		foreach($units as $unitDay) {

			if($couple === []) {
				$mHarvest = \series\TaskLib::applyHarvestedDay(\series\Harvest::model(), $eStructure, $eCultivation, $date, $unitDay);
			} else {
				$mHarvest = \series\TaskLib::applyHarvestedDayCouple(\series\Harvest::model(), $eStructure, $eCultivation, $date, $unitDay, $eTaskCouple['variety'], $eTaskCouple['harvestSize']);
			}

			$cHarvest = $mHarvest
				->select([
					'task',
					'total' => new \Sql('SUM(quantity)', 'float'),
				])
				->group('task')
				->getCollection();

			foreach($cHarvest as $eHarvest) {

				$quantity = round($eHarvest['total'], 3);

				if($quantity === 0.0) {
					continue;
				}

				$eTaskHarvested = self::getTaskCard($eStructure, $eCultivation, $eHarvest['task']['id']);

				// `writeEntry()` n'écrit sur l'intervention désignée que si elle répond à la semaine et au couple
				// (`getTaskWeek()`), et retombe sinon sur une autre, qui rendrait une quantité qu'elle ne porte pas.
				if(self::getTaskWeek($eStructure, $eCultivation, $week, $eTaskCouple['variety'], $eTaskCouple['harvestSize'], $eTaskHarvested)->is($eTaskHarvested) === FALSE) {

					if($couple === [] and ($eTaskHarvested['variety']->notEmpty() or $eTaskHarvested['harvestSize']->notEmpty())) {
						\series\Task::fail('harvestMore.ventilated');
					} else {
						\series\Task::fail('harvestDates.negative');
					}

				}

				$tasks[] = [$eTaskHarvested, $quantity, $unitDay];

			}

		}

		$fw->validate();

		if($eTask->empty() and $tasks === []) {
			throw new \NotExpectedAction('No harvest to delete');
		}

		$time = [];

		foreach(self::getTimeUsers($eStructure, $eCultivation, $week, $days, 'replace', $eTask) as $userId => $eUser) {

			if($eUser['spent'][$date] > 0.0) {
				$time[$userId] = '0';
			}

		}

		// Le stock que montre le tiroir, lu sur la récolte qu'il a ouverte (`harvestWeek:open`) : chaque intervention
		// rend sa part sans y toucher, et le stock reçoit leur somme une seule fois.
		$eTaskStock = HarvestStockLib::getReference($eTaskChosen->empty() ? $eTaskCouple : $eTaskChosen, $unit);

		[$eProductStock, $stockRemember] = HarvestStockLib::build($eStructure, $eTaskStock, $input);

		$fw->validate();

		$quantity = 0.0;
		$cTaskEmptied = new \Collection();

		foreach($tasks as [$eTaskHarvested, $quantityHarvested, $unitDay]) {

			$quantityWritten = self::writeEntry($eStructure, $eCultivation, $days, [
				'date' => $date,
				'mode' => 'add',
				'harvestMore' => (string)(0.0 - $quantityHarvested),
			] + $couple, $eTaskHarvested, stock: FALSE)['quantity'];

			// Le stock du tiroir est dans l'unité de la saisie, et lui seul (`getReference()`).
			if($unitDay === $unit) {
				$quantity += $quantityWritten;
			}

			$cTaskEmptied[$eTaskHarvested['id']] = $eTaskHarvested;

		}

		// La quantité du jour est partie : ce remplacement par 0 n'écrit plus que le temps.
		$eTaskTime = self::writeEntry($eStructure, $eCultivation, $days, [
			'date' => $date,
			'mode' => 'replace',
			'harvestDay' => '0',
			'time' => $time,
		] + $couple, $eTaskChosen, stock: FALSE)['eTask'];

		$cTaskEmptied[$eTaskTime['id']] = $eTaskTime;

		HarvestStockLib::write($eTaskStock, $eProductStock, $stockRemember, round($quantity, 3));

		\series\TaskLib::deleteHarvestShells($cTaskEmptied);

	}

	/**
	 * Le jour de la saisie (`date`) et celui de son temps, refusés hors de la semaine affichée, et le droit
	 * de la personne connectée sur son propre temps : le temps de chaque personne se construit ensuite sur ce
	 * jour (`buildTime()`). Rend le jour de la saisie et le temps qui porte le jour du temps.
	 *
	 * Le tiroir laisse le temps changer de jour dans la semaine (retour 202) : il poste `timeDate`, que le
	 * temps prend avec les mêmes refus que la récolte, rattachés à son champ. Sans lui, le temps reste sur le
	 * jour de la saisie.
	 */
	protected static function buildTimeDate(\structure\Structure $eStructure, array $days, array $input): array {

		$fw = new \FailWatch();

		$eTimesheet = new \series\Timesheet([
			'structure' => $eStructure,
			'user' => \user\ConnectionLib::getOnline(),
		]);

		$eTimesheet->build(['date'], $input);

		$fw->validate();

		$date = $eTimesheet['date'];

		if(isset($input['timeDate'])) {
			$eTimesheet->buildProperty('date', $input['timeDate'], new \PropertyList()->setWrapper(fn() => 'timeDate'));
		}

		$fw->validate();

		if(isset($days[$date]) === FALSE) {
			\series\Timesheet::fail('date.week');
		} else if(isset($days[$eTimesheet['date']]) === FALSE) {
			\series\Timesheet::fail('date.week', wrapper: 'timeDate');
		}

		$fw->validate();

		$eTimesheet->validate('canWrite', 'acceptWrite');

		return [$date, $eTimesheet];

	}

	/**
	 * Le temps de chacun s'ajoute à ce que sa journée porte déjà, ou en remplace la part de l'intervention : la
	 * borne de 24 heures porte sur la journée qui en résulte, comme dans `TimesheetLib::writeTimeForDay()`, et
	 * seul le serveur la connaît (R-Retours-0810, 210). Elle se vérifie avant l'écriture et non pendant :
	 * `TimesheetLib::writeByTasks()` annule toute la pile de transactions sur un refus. Les refus restent dans
	 * le `FailWatch` de l'appelant.
	 */
	public static function checkTimeDay(\structure\Structure $eStructure, \series\Task $eTask, \Collection $cTimesheet, string $mode): void {

		foreach($cTimesheet as $userId => $eTimesheetUser) {

			// Une intervention que la saisie crée n'a encore aucun temps à remplacer : sa durée s'ajoute.
			if($mode === 'replace' and $eTask->exists()) {
				$exceeds = \series\TimesheetLib::exceedsDay($eTask, $eTimesheetUser);
			} else {
				$exceeds = (
					$eTimesheetUser['time'] > 0.0 and
					round(\series\TimesheetLib::getTimeByUserDate($eStructure, $eTimesheetUser['user'], $eTimesheetUser['date']) + $eTimesheetUser['time'], 2) > 24.0
				);
			}

			if($exceeds) {
				\series\Timesheet::fail('time.tooLong', wrapper: 'time['.$userId.']');
			}

		}

	}

	/**
	 * Le temps de chaque personne postée sous `time[<user>]` (en heures), au jour du temps, par `build()` de
	 * `Timesheet` (R-Retours-0810, 224), comme le temps d'une équipe (`TimeTeamLib::prepare()`) : `buildIndex()`
	 * nomme les refus `time[<user>]`, le wrapper de la ligne de la personne. Une personne que la saisie ne
	 * propose pas (`getTimeTeam()`, dans la semaine du temps et sur `$eTask`) est refusée, comme le planning
	 * refuse le temps d'un autre ; elle n'a pas de ligne, et son refus se lit sous le temps entier (`time`).
	 *
	 * Un remplacement garde chaque durée postée, 0 compris : elle remplace le temps de la personne sur
	 * l'intervention ce jour-là (R-Retours-0810, 219). Un ajout ajoute le temps : une durée nulle n'y écrit
	 * rien.
	 */
	public static function buildTime(\structure\Structure $eStructure, \series\Task $eTask, string $date, string $mode, mixed $times): \Collection {

		$cTimesheet = new \Collection();

		// Une seule durée, que postait l'écran d'avant l'équipe, ne dit pas à qui elle s'écrit : elle se refuse
		// plutôt que de se perdre.
		if(is_array($times) === FALSE) {
			\series\Timesheet::fail('time.check');
			return $cTimesheet;
		}

		$cUser = self::getTimeTeam($eStructure, toWeek($date), $eTask->exists() ? new \Collection([$eTask['id'] => $eTask]) : new \Collection());

		foreach($times as $user => $time) {

			// Une personne hors de l'équipe se refuse sous le temps, que le tiroir et la feuille affichent : le
			// refus de `buildIndex()` se nommerait d'après l'index posté (`user[abc]`), qu'aucun écran ne porte.
			if($cUser->offsetExists($user) === FALSE) {
				\series\Timesheet::fail('user.check', wrapper: 'time');
				continue;
			}

			// Le jour est celui que `writeEntry()` a déjà construit et refusé hors de la semaine.
			$eTimesheet = new \series\Timesheet([
				'structure' => $eStructure,
				'date' => $date,
			]);

			// Une liste par personne : un refus marque la sienne, et la suivante se construit sur une liste neuve.
			$l = new \PropertyList('create');

			$eTimesheet->buildIndex(['user', 'time'], [
				'user' => [$user => $user],
				'time' => [$user => $time]
			], $user, $l);

			if($l->isInvalid('user') or $l->isInvalid('time')) {
				continue;
			}

			if($mode !== 'replace' and $eTimesheet['time'] <= 0.0) {
				continue;
			}

			$cTimesheet[$eTimesheet['user']['id']] = $eTimesheet;

		}

		return $cTimesheet;

	}

	/**
	 * Le poids d'une caisse d'une saisie en caisses (`crate`), construit sur l'espèce saisie
	 * (`PlantLib::prepareCrateWeight()`), ou une espèce vide hors caisses. Il ne s'écrit sur l'espèce qu'après une
	 * récolte acceptée (`writeCrate()`) : `writeHarvestForWeek()` lève avant toute écriture, et une saisie refusée
	 * ne laisse pas de poids derrière elle. L'espèce rendue dit donc si le poids posté change le sien
	 * (`crateWeightChanged`). Le tiroir (`writeEntry()`) et « Nouvelle intervention »
	 * (`TaskCreateLib::createCollection()`, retour 258) passent par ici.
	 */
	public static function buildCrate(\plant\Plant $ePlant, array $input): \plant\Plant {

		if(var_filter($input['crate'] ?? NULL, 'bool') === FALSE) {
			return new \plant\Plant();
		}

		$ePlantCrate = \plant\PlantLib::getById($ePlant['id']);
		$crateWeightBefore = $ePlantCrate['crateWeight'];

		\plant\PlantLib::prepareCrateWeight($ePlantCrate, $input);

		$ePlantCrate['crateWeightChanged'] = ($ePlantCrate['crateWeight'] !== $crateWeightBefore);

		return $ePlantCrate;

	}

	/**
	 * La quantité saisie, en kilos quand elle l'est en caisses (`buildCrate()`), à 0,1 près comme
	 * `\series\TaskLib::prepareHarvestForWeek()` la convertit. Une saisie partagée entre plusieurs séries se
	 * convertit avant le partage : partagée en caisses, chaque part s'arrondirait avant la conversion.
	 */
	public static function getCrateQuantity(\plant\Plant $ePlantCrate, float $quantity): float {

		if($ePlantCrate->empty()) {
			return $quantity;
		}

		return round($quantity * $ePlantCrate['crateWeight'], 1);

	}

	/**
	 * Le poids de `buildCrate()`, écrit sur l'espèce une fois la récolte écrite. Des caisses à 0, avec un temps
	 * seul, ne se sont pas servies du poids : il ne s'écrit pas.
	 */
	public static function writeCrate(\plant\Plant $ePlant, float $quantity): void {

		if(
			$ePlant->notEmpty() and
			$ePlant['crateWeightChanged'] and
			$quantity > 0.0
		) {
			\plant\PlantLib::update($ePlant, ['crateWeight']);
		}

	}

	/**
	 * Ce qu'une saisie change à l'écran, entre la liste d'avant l'écriture et celle d'après : les lignes
	 * sorties, les lignes entrées et les lignes dont le rendu change. La ligne saisie en fait toujours
	 * partie, puisqu'elle porte le tiroir ouvert ; les autres n'y entrent que si ce qu'elles affichent a
	 * bougé, ce qui arrive à leur nom quand une culture de la même espèce entre ou sort de la liste
	 * (`prepareNames()`). « Récolter » sur des séries cochées (DG-2) n'ouvre aucun tiroir : `$eCultivation`
	 * est alors vide, et seules les lignes qui ont bougé y entrent.
	 *
	 * Chaque espèce a son intertitre au-dessus de ses séries (`HarvestWeekUi::getSection()`, R-Retours-0810,
	 * 225), qui seul porte ses ventes à venir et son stock : `sections` dit les intertitres qui sortent avec
	 * la dernière série de leur espèce (`removed`) et ceux dont le rendu change (`updated`, avec la culture
	 * qui les porte). Une ligne entrée dit après quoi elle se place, dans l'ordre de la liste : `section`
	 * quand son espèce entre avec elle, son intertitre se plaçant alors après la culture `after` (`NULL` en
	 * tête du tableau) et la ligne sous lui ; sinon après la culture `after` de la même espèce, ou sous
	 * l'intertitre de l'espèce quand elle en devient la première (`NULL`).
	 *
	 * Une liste qui se vide ou qui naît se rend entière : l'écran passe du bloc vide à la grille.
	 */
	public static function getChange(\Collection $cCultivationBefore, \Collection $cCultivationAfter, \series\Cultivation $eCultivation): array {

		if($cCultivationBefore->empty() or $cCultivationAfter->empty()) {
			return [
				'list' => ($cCultivationBefore->empty() !== $cCultivationAfter->empty()),
				'removed' => [],
				'inserted' => [],
				'updated' => [],
				'sections' => [
					'removed' => [],
					'updated' => [],
				],
			];
		}

		return self::compare($cCultivationBefore, $cCultivationAfter, $eCultivation);

	}

	/**
	 * Le changement d'une saisie relue sur les seules espèces `$cPlant` (`getPlantsAround()`), vides quand la
	 * saisie a relu toute la semaine. L'espèce de la culture est à l'écran avant l'écriture
	 * (`hasPlant()`) : une liste bornée vide après l'écriture ne dit donc pas que la semaine se vide, ce que
	 * seule la semaine entière dit (`isEmpty()`). Sinon, les lignes et les intertitres se comparent comme
	 * sur la semaine entière : chaque espèce y est entière et à sa place.
	 */
	public static function getChangeAround(\structure\Structure $eStructure, string $week, \Collection $cCultivationBefore, \Collection $cCultivationAfter, \series\Cultivation $eCultivation, \Collection $cPlant): array {

		if(
			$cPlant->empty() or
			($cCultivationAfter->empty() and self::isEmpty($eStructure, $week))
		) {
			return self::getChange($cCultivationBefore, $cCultivationAfter, $eCultivation);
		}

		return self::compare($cCultivationBefore, $cCultivationAfter, $eCultivation);

	}

	/**
	 * Le changement d'une saisie faite dans la case d'une culture listée (HN-B) : la récolte n'y vise que cette
	 * culture, qui reste à sa place (l'ordre ne tient qu'aux noms, `getRank()`). Sa ligne et l'intertitre de
	 * son espèce, qui porte le stock, se redessinent ; aucune ligne n'entre ni ne sort.
	 */
	public static function getChangeCell(\series\Cultivation $eCultivation): array {

		return [
			'list' => FALSE,
			'removed' => [],
			'inserted' => [],
			'updated' => [$eCultivation['id']],
			'sections' => [
				'removed' => [],
				'updated' => [$eCultivation['plant']['id'] => $eCultivation['id']],
			],
		];

	}

	/**
	 * Le changement d'une saisie faite hors de l'écran (le champ du menu, `entryWrite`), dont la liste d'avant
	 * n'est plus lisible : l'écriture a déjà eu lieu. Les espèces `$cPlant` (`getPlantsAround()`) sont reposées
	 * entières. Leurs intertitres partent (`sections.removed`), leurs lignes aussi, par la vue, puis chaque culture
	 * de ces espèces revient à sa place dans la semaine entière (`getRank()`) : après celle qui la précède,
	 * restée à l'écran ou déjà reposée. Une semaine qui ne compte que ces espèces, ou aucune, se rend entière :
	 * c'est le seul cas où l'écran a pu passer du vide au plein, ou l'inverse.
	 */
	public static function getChangePlants(\structure\Structure $eStructure, string $week, \Collection $cPlant): array {

		$change = [
			'list' => FALSE,
			'removed' => [],
			'inserted' => [],
			'updated' => [],
			'sections' => [
				'removed' => $cPlant->getIds(),
				'updated' => [],
			],
		];

		$cCultivationRank = self::getRank($eStructure, $week);

		if($cCultivationRank->contains(fn($eCultivation) => $cPlant->offsetExists($eCultivation['plant']['id']) === FALSE) === FALSE) {
			$change['list'] = TRUE;
			return $change;
		}

		$previous = NULL;
		$previousPlant = NULL;

		foreach($cCultivationRank as $eCultivation) {

			$plant = $eCultivation['plant']['id'];

			if($cPlant->offsetExists($plant)) {
				$change['inserted'][$eCultivation['id']] = [
					'after' => $previous,
					'section' => ($plant !== $previousPlant),
				];
			}

			$previous = $eCultivation['id'];
			$previousPlant = $plant;

		}

		return $change;

	}

	private static function compare(\Collection $cCultivationBefore, \Collection $cCultivationAfter, \series\Cultivation $eCultivation): array {

		$before = [];
		$sectionsBefore = [];

		foreach($cCultivationBefore as $eCultivationBefore) {
			$before[$eCultivationBefore['id']] = self::getSignature($eCultivationBefore);
			$sectionsBefore[$eCultivationBefore['plant']['id']] ??= self::getSectionSignature($eCultivationBefore);
		}

		$removed = array_values(array_diff(array_keys($before), $cCultivationAfter->getIds()));
		$inserted = [];
		$updated = [];
		$sectionsAfter = [];
		$sectionsUpdated = [];

		$previous = NULL;
		$previousPlant = NULL;

		foreach($cCultivationAfter as $eCultivationAfter) {

			$id = $eCultivationAfter['id'];
			$plant = $eCultivationAfter['plant']['id'];
			$first = ($plant !== $previousPlant);
			$plantShown = array_key_exists($plant, $sectionsBefore);

			if($first) {

				$sectionsAfter[$plant] = self::getSectionSignature($eCultivationAfter);

				if(
					$plantShown and
					$sectionsBefore[$plant] !== $sectionsAfter[$plant]
				) {
					$sectionsUpdated[$plant] = $id;
				}

			}

			if(array_key_exists($id, $before) === FALSE) {
				$inserted[$id] = [
					'after' => ($first and $plantShown) ? NULL : $previous,
					'section' => ($first and $plantShown === FALSE),
				];
			} else if(
				$eCultivation->is($eCultivationAfter) or
				$before[$id] !== self::getSignature($eCultivationAfter)
			) {
				$updated[] = $id;
			}

			$previous = $id;
			$previousPlant = $plant;

		}

		return [
			'list' => FALSE,
			'removed' => $removed,
			'inserted' => $inserted,
			'updated' => $updated,
			'sections' => [
				'removed' => array_values(array_diff(array_keys($sectionsBefore), array_keys($sectionsAfter))),
				'updated' => $sectionsUpdated,
			],
		];

	}

	/**
	 * La case qu'une saisie vient de changer, que la réponse éclaire : la culture, le jour, et la part quand
	 * la saisie vise un couple que la culture montre en sous-ligne. La case montre les quantités du jour et,
	 * dessous, le temps de la récolte et les personnes qui l'ont passé (`prepareTime()`). Vide quand elle montre
	 * après la saisie ce qu'elle montrait avant, ou ne montre plus rien : la récolte se répartit au dixième sur
	 * les interventions (`TaskLib::distribute()`), et un écart demandé peut s'y annuler (6,3 ressaisi en 6,32).
	 * La culture écrite peut aussi sortir de la semaine relue, quand la pesée compense le seul retrait qui
	 * l'y faisait figurer.
	 */
	public static function getWritten(\Collection $cCultivationBefore, \Collection $cCultivationAfter, \series\Cultivation $eCultivation, string $date, \series\Task $eTaskCouple): array {

		if($cCultivationAfter->offsetExists($eCultivation['id']) === FALSE) {
			return [];
		}

		$partKey = \series\CultivationLib::getHarvestPartKey($eTaskCouple['variety'], $eTaskCouple['harvestSize']);
		$part = array_key_exists($partKey, $cCultivationAfter[$eCultivation['id']]['harvestedParts']) ? $partKey : NULL;

		// Une case vide n'est ni dans `harvestedDays` ni dans le temps du jour, qui écartent les jours à zéro.
		$read = function(\Collection $cCultivation) use($eCultivation, $date, $part): array {

			if($cCultivation->offsetExists($eCultivation['id']) === FALSE) {
				return [];
			}

			$eCultivationRead = $cCultivation[$eCultivation['id']];

			if($part === NULL) {
				[$days, $timeDays, $cUserTimeDays] = [$eCultivationRead['harvestedDays'], $eCultivationRead['harvestTimeDays'], $eCultivationRead['cUserTimeDays']];
			} else if(array_key_exists($part, $eCultivationRead['harvestedParts'])) {
				$partRead = $eCultivationRead['harvestedParts'][$part];
				[$days, $timeDays, $cUserTimeDays] = [$partRead['days'], $partRead['timeDays'], $partRead['cUserTimeDays']];
			} else {
				return [];
			}

			if(array_key_exists($date, $days) === FALSE and array_key_exists($date, $timeDays) === FALSE) {
				return [];
			}

			return [
				$days[$date] ?? [],
				$timeDays[$date] ?? 0.0,
				array_key_exists($date, $cUserTimeDays) ? $cUserTimeDays[$date]->getIds() : [],
			];

		};

		$shown = $read($cCultivationAfter);

		if($shown === [] or $shown === $read($cCultivationBefore)) {
			return [];
		}

		return [
			'cultivation' => $eCultivation['id'],
			'date' => $date,
			'part' => $part,
		];

	}

	/**
	 * La ligne qui suit la culture dans la liste, celle qui prend le curseur après une saisie au clavier.
	 * Vide après la dernière.
	 */
	public static function getNext(\Collection $cCultivation, \series\Cultivation $eCultivation): \series\Cultivation {

		$ids = $cCultivation->getIds();
		$position = array_search($eCultivation['id'], $ids, TRUE);

		if($position === FALSE or isset($ids[$position + 1]) === FALSE) {
			return new \series\Cultivation();
		}

		return $cCultivation[$ids[$position + 1]];

	}

	/**
	 * La ligne suivante dans une liste relue sur les seules espèces `$cPlant` (`getPlantsAround()`). Après la
	 * dernière ligne de son espèce, la suivante est la première de l'espèce qui suit à l'écran, que la liste
	 * bornée ne porte pas : le rang de toute la semaine la donne, sans rien préparer.
	 */
	public static function getNextAround(\structure\Structure $eStructure, string $week, \Collection $cCultivation, \series\Cultivation $eCultivation, \Collection $cPlant): \series\Cultivation {

		$eCultivationNext = self::getNext($cCultivation, $eCultivation);

		if(
			$cPlant->empty() or
			$cCultivation->offsetExists($eCultivation['id']) === FALSE or
			($eCultivationNext->notEmpty() and $eCultivationNext['plant']['id'] === $eCultivation['plant']['id'])
		) {
			return $eCultivationNext;
		}

		return self::getNext(self::getRank($eStructure, $week), $eCultivation);

	}

	/**
	 * Les espèces qu'une saisie des cultures `$cultivations` peut changer à l'écran : les leurs, dont elle change
	 * les lignes (`nameWithStartWeek` compte les lignes de l'espèce) et l'intertitre (le stock), et celles des
	 * séries qui portent le nom d'une série de leurs espèces, dont la clé de nom peut alors rencontrer une autre
	 * (`getNameKey()`, `nameWithVarieties`). Les espèces sont entières : chaque nom se prépare comme sur la
	 * semaine. Toutes saisons, une culture récoltée dans la semaine entrant dans la liste quelle que soit
	 * la sienne (`CultivationLib::getForHarvestWeek()`). Aucune espèce quand aucune culture n'est de la
	 * structure, ce qu'un appelant ne doit pas prendre pour « toute la semaine ».
	 */
	public static function getPlantsAround(\structure\Structure $eStructure, array $cultivations): \Collection {

		$cPlantCultivation = \series\CultivationLib::applyByIds(new \series\CultivationModel()->select('plant'), $eStructure, $cultivations)
			->group('plant')
			->getColumn('plant', index: 'plant');

		if($cPlantCultivation->empty()) {
			return new \Collection();
		}

		$names = \series\SeriesLib::applyByPlant(new \series\SeriesModel()->select('name'), $eStructure, $cPlantCultivation)->getColumn('name');

		// Chaque culture est dans l'une de ces homonymes : son espèce en fait partie.
		return \series\CultivationLib::applyBySeriesName(new \series\CultivationModel(), $eStructure, $names)
			->group('plant')
			->getColumn('plant', index: 'plant');

	}

	/**
	 * Si la liste porte une ligne de l'espèce, son intertitre étant alors à l'écran.
	 */
	public static function hasPlant(\Collection $cCultivation, \plant\Plant $ePlant): bool {
		return $cCultivation->contains(fn($eCultivation) => $eCultivation['plant']['id'] === $ePlant['id']);
	}

	/**
	 * La semaine sans aucune ligne, lue sur son rang sans rien préparer.
	 */
	private static function isEmpty(\structure\Structure $eStructure, string $week): bool {
		return self::getRank($eStructure, $week)->empty();
	}

	/**
	 * Les lignes de toute la semaine dans l'ordre de l'écran, avec ce que la règle d'entrée et le tri lisent.
	 */
	private static function getRank(\structure\Structure $eStructure, string $week): \Collection {

		$cCultivation = \series\CultivationLib::getForHarvestWeek($eStructure, $week, properties: [
			'id',
			'series' => ['name', 'status'],
			'plant' => ['name']
		]);

		self::sortList($cCultivation);

		return $cCultivation;

	}

	/**
	 * L'ordre de l'écran. Une culture ne porte qu'une espèce de sa structure (`owned`, `Cultivation.e.php`), dont
	 * le nom y est unique (`plant.yml`) : ce tri rend donc contiguës les séries d'une même espèce.
	 * `HarvestWeekUi::getGrid()` et `getChange()` posent un intertitre à chaque changement d'espèce et en
	 * dépendent. L'identifiant départage deux séries homonymes d'une espèce : une liste bornée à des espèces
	 * (`getPlantsAround()`) les range alors comme la semaine entière, quel que soit l'ordre de lecture.
	 */
	private static function sortList(\Collection $cCultivation): void {

		$cCultivation->sort([
			'plant' => ['name'],
			'series' => ['name'],
			'id'
		], natural: TRUE);

	}

	/**
	 * Tout ce que `HarvestWeekUi::getRow()` lit d'une culture : deux signatures égales rendent la même
	 * ligne. Les ventes à venir et le stock n'en font pas partie : l'intertitre de l'espèce les porte
	 * (`getSectionSignature()`).
	 */
	private static function getSignature(\series\Cultivation $eCultivation): string {

		return json_encode([
			self::getNameKey($eCultivation),
			$eCultivation['series']['season'],
			$eCultivation['series']['use'],
			$eCultivation['plant']['id'],
			$eCultivation['nameWithSeason'],
			$eCultivation['nameWithStartWeek'],
			$eCultivation['nameWithVarieties'],
			$eCultivation['cSlice']->makeArray(fn($eSlice) => $eSlice['variety']['name']),
			$eCultivation['expectedFirst'],
			$eCultivation['expectedLast'],
			$eCultivation['expectedFrieze'],
			$eCultivation['harvestedDays'],
			$eCultivation['harvestedWeek'],
			$eCultivation['harvestedWeekPrevious'],
			$eCultivation['harvestUnitWeek'],
			$eCultivation['plannedDays'],
			array_map(fn($part) => [$part['key'], $part['days'], $part['unit'], $part['writable'], $part['timeDays'], self::getUsersSignature($part['cUserTimeDays'])], array_values($eCultivation['harvestedParts'])),
			$eCultivation['harvestTimeDays'],
			self::getUsersSignature($eCultivation['cUserTimeDays']),
		]);

	}

	/**
	 * Les personnes d'une ligne du temps, jour par jour (`cUserTimeDays`), par leur identifiant : la ligne ne
	 * montre d'elles que leur vignette.
	 */
	private static function getUsersSignature(array $cUserTimeDays): array {
		return array_map(fn(\Collection $cUser) => $cUser->getIds(), $cUserTimeDays);
	}

	/**
	 * Tout ce que `HarvestWeekUi::getSection()` lit de l'espèce d'une culture : son nom, sa vignette, et
	 * ses ventes à venir et son stock, que toutes ses séries portent à l'identique (`prepareSales()`). Les
	 * jours, qu'il lit aussi pour poser ses plis, sont ceux de la requête : l'avant et l'après d'une réponse
	 * ciblée les partagent.
	 */
	private static function getSectionSignature(\series\Cultivation $eCultivation): string {

		$ePlant = $eCultivation['plant'];

		return json_encode([
			$ePlant['name'],
			$ePlant['fqn'],
			$ePlant['vignette'],
			array_map(fn($byUnit) => array_map(fn($entry) => $entry['value'], $byUnit), $eCultivation['sales']),
		]);

	}

	/**
	 * Les jours d'une semaine de récolte demandée par une requête, celle de l'écran des récoltes ou celle de la fiche d'une
	 * planche (`series/place:doHarvest`) : une semaine hors des saisons de la structure ne s'ouvre pas.
	 */
	public static function getWeekDays(\structure\Structure $eStructure, string $week, ?string $open, bool $defaultOpen = TRUE): array {

		if($eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($week)) === FALSE) {
			throw new \NotExpectedAction('Invalid week');
		}

		return self::getDays($week, $open, $defaultOpen);

	}

	/**
	 * Les jours de la semaine de lundi à dimanche, avec leur rang (1 pour lundi) et leur numéro dans le mois, s'ils sont aujourd'hui
	 * ou à venir, et lequel est ouvert (`open`) : le pli de `util-bellows` qui montre son contenu, et le jour
	 * qu'ouvre la ligne au clavier.
	 *
	 * Le jour ouvert est celui que l'écran a ouvert (`$open`), que chaque requête de la semaine renvoie pour
	 * que la réponse redessine ses lignes dans la même colonne. Un jour hors de la semaine ou à venir n'est
	 * pas un jour qu'on ouvre : on revient alors à `getEntryDate()`, aujourd'hui ou le lundi d'une semaine
	 * passée. Une semaine à venir n'a aucun jour ouvert. Sans `$defaultOpen`, que les Récoltes ne passent
	 * qu'en saisie rapide, une semaine passée s'ouvre sans jour ouvert (R-Retours-1210, HD) : on y lit la
	 * semaine entière avant d'en choisir un.
	 *
	 * Le jour proposé (`proposed`) est celui que prend le geste d'une ligne : le jour ouvert, à défaut celui de
	 * « Nouvelle récolte » (`getCreateDate()`), aucun sur une semaine à venir.
	 *
	 * Les voisins (`previous`, `next`) sont les jours qu'ouvrent les chevrons du titre d'un pli : le jour d'à
	 * côté dans la semaine s'il n'est pas à venir, `NULL` sinon. C'est la règle du geste au téléphone
	 * (`PlanningHarvestSwipe.getNext()`, `planning.js`) : l'une ne change pas sans l'autre.
	 */
	public static function getDays(string $week, ?string $open = NULL, bool $defaultOpen = TRUE): array {

		$today = \Clock::date();
		$days = [];

		foreach(week_dates($week) as $position => $date) {
			$days[$date] = [
				'day' => $position + 1,
				'number' => (int)substr($date, 8, 2),
				'today' => ($date === $today),
				'future' => ($date > $today),
			];
		}

		if($open === NULL or isset($days[$open]) === FALSE or $days[$open]['future']) {
			$open = $defaultOpen ? self::getEntryDate($days) : array_find_key($days, fn($day) => $day['today']);
		}

		$proposed = $open ?? self::getCreateDate($days);

		$dates = array_keys($days);

		foreach($dates as $position => $date) {

			$previous = $dates[$position - 1] ?? NULL;
			$next = $dates[$position + 1] ?? NULL;

			$days[$date]['open'] = ($date === $open);
			$days[$date]['proposed'] = ($date === $proposed);
			$days[$date]['previous'] = $previous;
			$days[$date]['next'] = ($next === NULL or $days[$next]['future']) ? NULL : $next;

		}

		return $days;

	}

	/**
	 * La règle de nom des lignes : la série, sa saison quand ce n'est pas celle de la
	 * semaine, son lieu, la semaine de départ quand l'espèce compte plusieurs cultures dans la liste,
	 * et les variétés en dernier recours, quand deux lignes restent identiques malgré tout cela.
	 */
	private static function prepareNames(\Collection $cCultivation, string $week): void {

		$byPlant = [];

		foreach($cCultivation as $eCultivation) {
			$plant = $eCultivation['plant']['id'];
			$byPlant[$plant] = ($byPlant[$plant] ?? 0) + 1;
		}

		$byKey = [];

		foreach($cCultivation as $eCultivation) {

			$eCultivation['nameWithSeason'] = ($eCultivation['series']['season'] !== week_year($week));
			$eCultivation['nameWithStartWeek'] = ($byPlant[$eCultivation['plant']['id']] > 1 and $eCultivation['startWeek'] !== NULL);

			$key = self::getNameKey($eCultivation);
			$byKey[$key] = ($byKey[$key] ?? 0) + 1;

		}

		foreach($cCultivation as $eCultivation) {
			$eCultivation['nameWithVarieties'] = ($byKey[self::getNameKey($eCultivation)] > 1 and $eCultivation['cSlice']->notEmpty());
		}

	}

	/**
	 * Ce qui rend deux noms identiques à l'écran : le nom de série, les planches, et la semaine de
	 * départ quand elle est affichée.
	 */
	private static function getNameKey(\series\Cultivation $eCultivation): string {

		$beds = [];

		$eCultivation['series']['cccPlace']->map(function(\series\Place $ePlace) use(&$beds) {
			$beds[] = $ePlace['bed']['id'];
		}, depth: 3);

		sort($beds);

		return implode('|', [
			$eCultivation['series']['name'],
			implode(',', $beds),
			$eCultivation['nameWithStartWeek'] ? $eCultivation->getStartWeek($eCultivation['season']) : ''
		]);

	}

	/**
	 * La première et la dernière semaine de la récolte prévue, pour l'annotation « prévue s37 à s42 », et
	 * leur place sur la frise (`expectedFrieze`, en pourcentages). L'échelle est la même pour toute la
	 * grille, `PlanningSetting::FRIEZE_WEEKS` de chaque côté de la semaine affichée : une période qui en
	 * sort s'arrête au bord (`cutStart`, `cutEnd`), et une période entièrement dehors n'a pas de barre
	 * (`width` nul). Les semaines s'écrivent `2026-W37`, que l'ordre alphabétique range comme le
	 * calendrier, y compris d'une année sur l'autre.
	 */
	private static function prepareExpected(\Collection $cCultivation, string $week): void {

		$span = 2 * PlanningSetting::FRIEZE_WEEKS + 1;
		$weekFrom = toWeek(week_date_starts($week).' - '.PlanningSetting::FRIEZE_WEEKS.' weeks');

		foreach($cCultivation as $eCultivation) {

			$weeks = $eCultivation['harvestWeeksExpected'] ?? [];
			sort($weeks);

			$eCultivation['expectedFirst'] = $weeks[0] ?? NULL;
			$eCultivation['expectedLast'] = ($weeks === []) ? NULL : $weeks[count($weeks) - 1];

			if($weeks === []) {
				$eCultivation['expectedFrieze'] = [];
				continue;
			}

			$first = self::getWeekRank($weekFrom, $eCultivation['expectedFirst']);
			$last = self::getWeekRank($weekFrom, $eCultivation['expectedLast']);
			$now = PlanningSetting::FRIEZE_WEEKS;

			if($now > $last) {
				$state = 'late';
			} else if($now < $first) {
				$state = 'early';
			} else {
				$state = 'now';
			}

			$firstShown = max($first, 0);
			$lastShown = min($last, $span - 1);
			$shown = ($firstShown <= $lastShown);

			$eCultivation['expectedFrieze'] = [
				'left' => $shown ? round($firstShown / $span * 100, 2) : 0.0,
				'width' => $shown ? round(($lastShown - $firstShown + 1) / $span * 100, 2) : 0.0,
				'cutStart' => ($shown and $first < 0),
				'cutEnd' => ($shown and $last > $span - 1),
				'mark' => round(($now + 0.5) / $span * 100, 2),
				'state' => $state
			];

		}

	}

	/**
	 * Le rang d'une semaine comptée depuis une autre, négatif avant elle : `week_number()` repart à 1 en janvier.
	 */
	private static function getWeekRank(string $weekFrom, string $week): int {

		return intdiv((int)new \DateTime(week_date_starts($weekFrom))->diff(new \DateTime(week_date_starts($week)))->format('%r%a'), 7);

	}

	/**
	 * Les ventes à venir et le stock de chaque culture : les ventes livrées d'aujourd'hui inclus aux
	 * `$salesDays` jours qui suivent (`\product\ProductLib::getSoldForComingDays()`, R-Retours-0510, W ; la page
	 * Stocks, elle, commence demain), et le stock des produits dont le
	 * suivi est actif et la quantité saisie. Le rapprochement se fait par l'espèce seule : `Product.variety`
	 * est un texte libre, qui ne désigne pas une variété de l'espèce (décision V-Planning du 2026-10-05).
	 * Toutes les séries d'une même espèce portent donc les mêmes chiffres.
	 *
	 * Les quantités se cumulent par unité de produit, jamais d'une unité à l'autre : `sold` et `stock`
	 * portent `[unité => ['unit' => Unit, 'value' => quantité]]`, dans l'ordre des produits, la clé `0`
	 * rangeant les produits sans unité, dont le `Unit` vide s'écrit sans unité par `UnitUi::getValue()`. Les quantités
	 * s'arrondissent au dixième une fois cumulées, comme sur la planche validée. Une option éteinte laisse
	 * les deux vides, sans aucune requête.
	 */
	private static function prepareSales(\structure\Structure $eStructure, \Collection $cCultivation, ?int $salesDays): void {

		$sales = [];

		if($salesDays !== NULL and $cCultivation->notEmpty()) {

			$cProduct = \product\ProductLib::getByPlants($eStructure, $cCultivation->getColumnCollection('plant'));

			$cItem = $cProduct->empty() ?
				new \Collection() :
				\product\ProductLib::getSoldForComingDays($eStructure, $salesDays);

			foreach($cProduct as $eProduct) {

				$plant = $eProduct['plant']['id'];
				$eUnit = $eProduct['unit'];
				$unit = $eUnit->empty() ? 0 : $eUnit['id'];

				$sales[$plant] ??= ['sold' => [], 'stock' => []];

				if($cItem->offsetExists($eProduct['id']) and $cItem[$eProduct['id']]['quantity'] > 0.0) {
					$sales[$plant]['sold'][$unit] ??= ['unit' => $eUnit, 'value' => 0.0];
					$sales[$plant]['sold'][$unit]['value'] += $cItem[$eProduct['id']]['quantity'];
				}

				if($eProduct['stockStatus'] === \product\Product::ACTIVE and $eProduct['stock'] !== NULL) {
					$sales[$plant]['stock'][$unit] ??= ['unit' => $eUnit, 'value' => 0.0];
					$sales[$plant]['stock'][$unit]['value'] += $eProduct['stock'];
				}

			}

			foreach($sales as $plant => $byKey) {
				foreach($byKey as $key => $byUnit) {
					foreach($byUnit as $unit => $entry) {
						$sales[$plant][$key][$unit]['value'] = round($entry['value'], 1);
					}
				}
			}

		}

		foreach($cCultivation as $eCultivation) {
			$eCultivation['sales'] = $sales[$eCultivation['plant']['id']] ?? ['sold' => [], 'stock' => []];
		}

	}

	/**
	 * La colonne « Semaine » : la somme des récoltes de la semaine, unité par unité, dans l'ordre où
	 * les jours les ont vues arriver. `harvestedDays` porte `[date][unit] => quantité`. Les noms ne sont
	 * ni `harvested` ni `harvestedByUnit`, deux colonnes de la culture qui cumulent toute la campagne et
	 * que la saisie lit sur les mêmes éléments.
	 */
	private static function prepareHarvestedByUnit(\Collection $cCultivation): void {

		foreach($cCultivation as $eCultivation) {

			$byUnit = [];

			$harvested = $eCultivation['harvestedDays'];
			ksort($harvested);

			foreach($harvested as $units) {
				foreach($units as $unit => $quantity) {
					$byUnit[$unit] = ($byUnit[$unit] ?? 0.0) + $quantity;
				}
			}

			$eCultivation['harvestedWeek'] = $byUnit;

		}

	}

	/**
	 * Les récoltes à faire de chaque culture sur les jours à venir de la semaine (`plannedDays`, `[date => nombre]`),
	 * dont la ligne marque la bande de chaque jour (`HarvestWeekUi::getPlanned()`).
	 */
	private static function preparePlanned(\structure\Structure $eStructure, \Collection $cCultivation, string $week): void {

		$planned = \series\TaskLib::getHarvestPlannedForWeek($eStructure, $cCultivation, $week);

		foreach($cCultivation as $eCultivation) {
			$eCultivation['plannedDays'] = $planned[$eCultivation['id']] ?? [];
		}

	}

	/**
	 * La couleur de l'action Récolte, dont le récolté de la semaine se souligne comme sur une carte de la
	 * Semaine (`\sequence\FlowUi::getMore()`).
	 */
	private static function prepareColor(\structure\Structure $eStructure, \Collection $cCultivation): void {

		if($cCultivation->empty()) {
			return;
		}

		$color = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE)['color'];

		foreach($cCultivation as $eCultivation) {
			$eCultivation['harvestColor'] = $color;
		}

	}

	/**
	 * L'intervention de récolte de la semaine de chaque culture, et l'unité de sa saisie
	 * (`getEntryUnit()`).
	 *
	 * Un jour récolté se rouvre dans la première unité qui le porte (R-Retours-0810, T, piste A ; GY), celle de
	 * la semaine puis les autres dans l'ordre de l'enum. Hors de l'unité de la semaine, il désigne l'intervention
	 * de cette unité (`harvestTaskDays`), que son remplacement et son ajout écrivent, par le tiroir qu'il ouvre et
	 * le temps qu'on y lit (`prepareTime()`). Un jour récolté dans l'unité de la semaine ne désigne rien.
	 *
	 * La feuille propose les caisses quand l'espèce en a le poids : elle n'a pas de champ pour le régler, que
	 * le tiroir porte (`prepareCrate()`).
	 */
	private static function prepareTask(\structure\Structure $eStructure, \Collection $cCultivation, string $week): void {

		$cTask = \series\TaskLib::getHarvestForWeek($eStructure, $cCultivation, $week);
		$tasksByUnit = self::getTasksByUnit($eStructure, $cCultivation, $week);

		foreach($cCultivation as $eCultivation) {

			$eTask = $cTask->offsetExists($eCultivation['id']) ? $cTask[$eCultivation['id']] : new \series\Task();

			$eCultivation['eTaskWeek'] = $eTask;
			$eCultivation['harvestUnitWeek'] = self::getEntryUnit($eCultivation, $eTask);

			$tasks = $tasksByUnit[$eCultivation['id']];

			$eCultivation['plant']['crate'] = ($eCultivation['plant']['crateWeight'] !== NULL);

			$eCultivation['harvestTaskDays'] = [];
			$eCultivation['harvestedDays'] = array_map(fn($quantities) => self::sortUnits($quantities, $eCultivation['harvestUnitWeek']), $eCultivation['harvestedDays']);

			foreach($eCultivation['harvestedDays'] as $date => $quantities) {

				$unit = array_find_key($quantities, fn($quantity) => $quantity > 0.0) ?? $eCultivation['harvestUnitWeek'];

				$eCultivation['harvestTaskDays'][$date] = ($unit !== $eCultivation['harvestUnitWeek'] and array_key_exists($unit, $tasks)) ? $tasks[$unit] : new \series\Task();

			}

		}

	}

	/**
	 * Les variétés de la culture (`cVarietyChoice`, dans l'ordre de ses parts variétales) et les calibres
	 * de son espèce (`cSizeChoice`), parmi lesquels une saisie choisit son couple. Les calibres de toute la
	 * liste se lisent en une requête.
	 */
	private static function prepareChoices(\structure\Structure $eStructure, \Collection $cCultivation): void {

		$plants = [];

		foreach($cCultivation as $eCultivation) {
			$plants[$eCultivation['plant']['id']] = $eCultivation['plant']['id'];
		}

		$sizes = [];

		if($plants !== []) {

			$cSize = \plant\Size::model()
				->select(['id', 'name', 'plant'])
				->whereStructure($eStructure)
				->wherePlant('IN', array_values($plants))
				->sort(['name' => SORT_ASC])
				->getCollection();

			foreach($cSize as $eSize) {
				$sizes[$eSize['plant']['id']][$eSize['id']] = $eSize;
			}

		}

		foreach($cCultivation as $eCultivation) {

			$cVariety = new \Collection();

			foreach($eCultivation['cSlice'] as $eSlice) {
				$cVariety[$eSlice['variety']['id']] = $eSlice['variety'];
			}

			$eCultivation['cVarietyChoice'] = $cVariety;
			$eCultivation['cSizeChoice'] = new \Collection($sizes[$eCultivation['plant']['id']] ?? []);

		}

	}

	/**
	 * Les parts qui ont une ligne, parmi les candidates de `\series\CultivationLib::getForHarvestWeek()` : celles
	 * qui portent une quantité ou le temps que l'écran somme (`prepareTime()`) dans la semaine. Une part
	 * récoltée la semaine d'avant garde aussi la sienne, vide sur la semaine,
	 * pour que la colonne « Semaine précédente » la dise : la ligne de la série ne la dit plus (M-Planning, 21/10).
	 * Elle la garde à toute largeur d'écran, même où cette colonne ne se montre pas. Une saisie ne disparaît
	 * jamais de l'écran qui l'a reçue, et une part sans rien n'en a pas (21/10, « si j'ajoute des kg
	 * sur annina pas de raison que "non spécifiée apparaisse" » ; M-Planning, 21/10). Une part gardée garde ses
	 * jours de la semaine, comme la culture, et « Non spécifiée » restée seule rend la culture entière.
	 */
	private static function keepParts(\Collection $cCultivation): void {

		foreach($cCultivation as $eCultivation) {

			$parts = [];

			foreach($eCultivation['harvestedParts'] as $key => $part) {

				if($part['days'] !== [] or $part['timeDays'] !== [] or $part['weekPrevious'] !== []) {
					$parts[$key] = $part;
				}

			}

			$eCultivation['harvestedParts'] = \series\CultivationLib::filterVentilated($parts);

		}

	}

	/**
	 * Les parts de chaque culture ventilée (`harvestedParts`, gardées par `keepParts()`), dans
	 * l'ordre de l'écran : les variétés dans l'ordre de leurs parts variétales, puis les calibres seuls,
	 * puis « Non spécifiée ». Chacune porte son total de la semaine (`week`), l'unité de sa saisie
	 * (`unit`), et si elle se saisit encore (`writable`) : une variété sortie de la culture ou un calibre
	 * supprimé ne passerait plus `build()`, la part reste à lire.
	 *
	 * Le choix de la variété paraît quand la culture en a plusieurs ou qu'une part en porte une
	 * (`choiceVariety`), celui du calibre quand l'espèce en déclare (`cSizeChoice`).
	 */
	private static function prepareParts(\structure\Structure $eStructure, \Collection $cCultivation, string $week): void {

		$units = \series\TaskLib::getHarvestUnitsForWeek($eStructure, $cCultivation->find(fn($eCultivation) => $eCultivation['harvestedParts'] !== []), $week);

		foreach($cCultivation as $eCultivation) {

			$varieties = $eCultivation['cVarietyChoice']->getIds();
			$sizes = $eCultivation['cSizeChoice']->getIds();

			$parts = $eCultivation['harvestedParts'];

			foreach($parts as $key => $part) {

				$byUnit = [];

				foreach($part['days'] as $quantities) {
					foreach($quantities as $unit => $quantity) {
						$byUnit[$unit] = round(($byUnit[$unit] ?? 0.0) + $quantity, 3);
					}
				}

				$specified = ($part['eVariety']->notEmpty() or $part['eSize']->notEmpty());

				$parts[$key]['unit'] = $specified ? ($units[$eCultivation['id']][$key] ?? $eCultivation['mainUnit']) : $eCultivation['harvestUnitWeek'];
				$parts[$key]['week'] = self::sortUnits(array_filter($byUnit, fn($quantity) => $quantity !== 0.0), $parts[$key]['unit']);
				$parts[$key]['days'] = array_map(fn($quantities) => self::sortUnits($quantities, $parts[$key]['unit']), $part['days']);
				$parts[$key]['writable'] = (
					($part['eVariety']->empty() or in_array($part['eVariety']['id'], $varieties)) and
					($part['eSize']->empty() or in_array($part['eSize']['id'], $sizes))
				);
				$parts[$key]['batch'] = self::getBatchValue($eCultivation, $part['eVariety'], $part['eSize']);
				if($part['eVariety']->notEmpty()) {
					$position = array_search($part['eVariety']['id'], $varieties);
					$parts[$key]['rank'] = [0, ($position === FALSE) ? count($varieties) : $position, $part['eVariety']['name'], $part['eSize']->empty() ? '' : $part['eSize']['name']];
				} else if($part['eSize']->notEmpty()) {
					$parts[$key]['rank'] = [1, 0, '', $part['eSize']['name']];
				} else {
					$parts[$key]['rank'] = [2, 0, '', ''];
				}

			}

			uasort($parts, fn($part1, $part2) => $part1['rank'] <=> $part2['rank']);

			$eCultivation['harvestedParts'] = $parts;
			$eCultivation['batch'] = self::getBatchValue($eCultivation, new \plant\Variety(), new \plant\Size());
			$eCultivation['choiceVariety'] = (
				count($varieties) > 1 or
				($varieties !== [] and array_any($parts, fn($part) => $part['eVariety']->notEmpty()))
			);

		}

	}

	/**
	 * Les quantités d'une ligne, l'unité de la ligne (`$unit`) en tête puis les autres dans l'ordre de
	 * l'énumération : une case empile « 40 p. / 4 kg » sur une ligne en pièces et « 4 kg / 40 p. » sur une
	 * ligne en kilos, quel que soit l'ordre que rend la base (M-Planning, 21/10).
	 */
	private static function sortUnits(array $quantities, string $unit): array {

		$sorted = [];

		foreach([$unit, ...array_diff(\series\Task::model()->getPropertyEnum('harvestUnit'), [$unit])] as $unitSorted) {
			if(array_key_exists($unitSorted, $quantities)) {
				$sorted[$unitSorted] = $quantities[$unitSorted];
			}
		}

		return $sorted;

	}

	/**
	 * Le temps passé sur les récoltes de la semaine, lu en une requête sur les interventions que les saisies
	 * de l'écran écrivent, en heures :
	 * - `harvestTimeDays`, `[date]`, le temps de toutes les personnes que la case du jour dit sous sa quantité.
	 *   La quantité somme toutes les interventions récoltées ce jour-là, le temps aussi (`getTasksHarvested()`),
	 *   avec celui de l'intervention qu'ouvre le tiroir du jour (`harvestTaskDays`, sinon `eTaskWeek`), où il a
	 *   pu être noté avant la pesée ;
	 * - `harvestTimeTasks`, `[date]`, les interventions que ce temps somme, chaque jour de la semaine ;
	 * - `cUserTimeDays`, `[date]`, les personnes de cette somme que la case montre en vignettes
	 *   (`getUsersOfDay()`) ;
	 * - pour chaque part, `eTask`, l'intervention de son couple (vide quand la semaine n'en porte pas), et
	 *   `timeDays`, `timeTasks` et `cUserTimeDays`, lus de même sur les interventions de son couple.
	 *
	 * Une structure qui ne suit pas le temps n'en lit aucun : le tiroir ne lui propose pas de temps non plus.
	 */
	private static function prepareTime(\structure\Structure $eStructure, \Collection $cCultivation, string $week): void {

		$hasTime = $eStructure->hasTime();

		$tasksPart = $hasTime ? self::getTasksByPart($eStructure, $cCultivation, $week) : [];

		$tasks = [];

		if($hasTime) {

			foreach($cCultivation as $eCultivation) {

				if($eCultivation['eTaskWeek']->notEmpty()) {
					$tasks[] = $eCultivation['eTaskWeek']['id'];
				}

				foreach($eCultivation['harvestTaskDays'] as $eTask) {
					if($eTask->notEmpty()) {
						$tasks[] = $eTask['id'];
					}
				}

			}

			foreach($tasksPart as $tasksCultivation) {
				foreach($tasksCultivation as $eTask) {
					$tasks[] = $eTask['id'];
				}
			}

		}

		$tasksHarvested = $hasTime ? self::getTasksHarvested($eStructure, $cCultivation, $week) : [];

		foreach($tasksHarvested as $tasksCultivation) {
			foreach($tasksCultivation as $tasksCouple) {
				$tasks = array_merge($tasks, ...array_values($tasksCouple));
			}
		}

		$times = [];

		if($tasks !== []) {

			$cTimesheet = \series\Timesheet::model()
				->select([
					'task', 'user', 'date',
					'total' => new \Sql('SUM(time)', 'float')
				])
				->whereStructure($eStructure)
				->whereTask('IN', array_values(array_unique($tasks)))
				->where('user IS NOT NULL')
				->whereDate('>=', week_date_starts($week))
				->whereDate('<=', week_date_ends($week))
				->group(['task', 'user', 'date'])
				->getCollection();

			foreach($cTimesheet as $eTimesheet) {
				$times[$eTimesheet['task']['id']][$eTimesheet['date']][$eTimesheet['user']['id']] = round($eTimesheet['total'], 2);
			}

		}

		$cUserMember = ($times === []) ? new \Collection() : \hr\TeammateLib::getUsersForPlanning($eStructure);

		$dates = [];

		for($day = 1; $day <= 7; $day++) {
			$dates[] = week_date_day($week, $day);
		}

		foreach($cCultivation as $eCultivation) {

			$eTaskWeek = $eCultivation['eTaskWeek'];

			$timeDays = [];
			$timeTasks = [];
			$cUserTimeDays = [];

			foreach($dates as $date) {

				$eTaskDay = (array_key_exists($date, $eCultivation['harvestTaskDays']) and $eCultivation['harvestTaskDays'][$date]->notEmpty()) ?
					$eCultivation['harvestTaskDays'][$date] :
					$eTaskWeek;

				$timeTasks[$date] = self::getTasksOfDay($eTaskDay, array_merge(...array_values($tasksHarvested[$eCultivation['id']][$date] ?? [])));

				$time = self::sumTimeOfDay($times, $date, $timeTasks[$date]);

				if($time > 0.0) {
					$timeDays[$date] = $time;
					$cUserTimeDays[$date] = self::getUsersOfDay($times, $date, $timeTasks[$date], $cUserMember);
				}

			}

			$eCultivation['harvestTimeDays'] = $timeDays;
			$eCultivation['harvestTimeTasks'] = $timeTasks;
			$eCultivation['cUserTimeDays'] = $cUserTimeDays;

			$parts = $eCultivation['harvestedParts'];

			foreach($parts as $key => $part) {

				$eTask = $tasksPart[$eCultivation['id']][$key] ?? new \series\Task();

				$parts[$key]['eTask'] = $eTask;
				$parts[$key]['timeDays'] = [];
				$parts[$key]['timeTasks'] = [];
				$parts[$key]['cUserTimeDays'] = [];

				foreach($dates as $date) {

					$parts[$key]['timeTasks'][$date] = self::getTasksOfDay($eTask, $tasksHarvested[$eCultivation['id']][$date][$key] ?? []);

					$time = self::sumTimeOfDay($times, $date, $parts[$key]['timeTasks'][$date]);

					if($time > 0.0) {
						$parts[$key]['timeDays'][$date] = $time;
						$parts[$key]['cUserTimeDays'][$date] = self::getUsersOfDay($times, $date, $parts[$key]['timeTasks'][$date], $cUserMember);
					}

				}

			}

			$eCultivation['harvestedParts'] = $parts;

		}

	}

	/**
	 * Les interventions dont la case d'un jour somme le temps : celles récoltées ce jour-là et celle qu'ouvre
	 * le tiroir, chacune une fois.
	 */
	private static function getTasksOfDay(\series\Task $eTask, array $tasksHarvested): array {

		$tasks = $tasksHarvested;

		if($eTask->notEmpty()) {
			$tasks[] = $eTask['id'];
		}

		return array_values(array_unique($tasks));

	}

	/**
	 * Le temps de toutes les personnes, un jour, sur les interventions de la case (`getTasksOfDay()`).
	 * `$times` porte `[task][date][user]`.
	 */
	private static function sumTimeOfDay(array $times, string $date, array $tasks): float {

		$time = 0.0;

		foreach($tasks as $task) {
			$time += array_sum($times[$task][$date] ?? []);
		}

		return round($time, 2);

	}

	/**
	 * Les personnes dont la case d'un jour somme le temps (`sumTimeOfDay()`), parmi les membres de la structure
	 * (`$cUserMember`), dans leur ordre : celles que montre le pied d'une carte de la Semaine
	 * (`TaskCardUi::getUsers()`). Le temps d'une personne partie reste dans la somme, sans sa vignette.
	 */
	private static function getUsersOfDay(array $times, string $date, array $tasks, \Collection $cUserMember): \Collection {

		$users = [];

		foreach($tasks as $task) {

			foreach($times[$task][$date] ?? [] as $user => $time) {

				if($time > 0.0) {
					$users[$user] = TRUE;
				}

			}

		}

		return $cUserMember->find(fn(\user\User $eUser) => array_key_exists($eUser['id'], $users));

	}

	/**
	 * Les interventions récoltées dans la semaine, `[cultivation][date][couple]`, le couple variété · calibre
	 * étant celui de l'intervention : ce sont elles dont l'écran somme les quantités d'un jour
	 * (`\series\CultivationLib::getForHarvestWeek()`), et dont il somme donc le temps. Une requête pour toute
	 * la liste.
	 */
	private static function getTasksHarvested(\structure\Structure $eStructure, \Collection $cCultivation, string $week): array {

		if($cCultivation->empty()) {
			return [];
		}

		$cHarvest = self::applyTasksHarvested(\series\Harvest::model(), $eStructure, $cCultivation, $week)->getCollection();

		$tasks = [];

		foreach($cHarvest as $eHarvest) {

			$key = \series\CultivationLib::getHarvestPartKey($eHarvest['variety'], $eHarvest['harvestSize']);

			$tasks[$eHarvest['cultivation']['id']][$eHarvest['date']][$key][] = $eHarvest['task']['id'];

		}

		return $tasks;

	}

	/**
	 * La requête de `getTasksHarvested()`, posée sur le modèle reçu pour que `HarvestExplainAiTest` mesure
	 * celle du produit et non une copie.
	 */
	public static function applyTasksHarvested(\series\HarvestModel $mHarvest, \structure\Structure $eStructure, \Collection $cCultivation, string $week): \series\HarvestModel {

		return $mHarvest
			->select(['cultivation', 'task', 'date'])
			->join(\series\Task::model()
				->select(['variety', 'harvestSize']), 'm2.id = m1.task')
			->where('m1.structure', $eStructure)
			->where('m1.week', $week)
			->where('m1.cultivation', 'IN', $cCultivation->getIds())
			->group([new \Sql('m1.cultivation'), new \Sql('m1.task'), new \Sql('m1.date')]);

	}

	/**
	 * L'intervention de chaque part de la liste, `[cultivation][couple]`, celle que
	 * `\series\TaskLib::getHarvestForWeek()` désigne pour son couple variété · calibre et qu'écrit la saisie
	 * de la part. La règle est la sienne, appliquée à tous les couples à la fois : dans la semaine, la
	 * première à faire, sinon la dernière faite ; sans intervention dans la semaine, la plus ancienne en retard.
	 * Elle se lit en deux requêtes pour toute la liste, et non en une série par couple : une semaine compte
	 * jusqu'à une trentaine de couples sur une structure.
	 */
	private static function getTasksByPart(\structure\Structure $eStructure, \Collection $cCultivation, string $week): array {

		$cCultivationParts = $cCultivation->find(fn($eCultivation) => $eCultivation['harvestedParts'] !== []);

		if($cCultivationParts->empty()) {
			return [];
		}

		$eAction = \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE);
		$properties = ['id', 'cultivation', 'variety', 'harvestSize', 'status'];

		$cTask = \series\TaskLib::applyHarvestWeekAll(\series\Task::model(), $eStructure, $cCultivationParts, $eAction, $week)
			->select($properties)
			->getCollection();

		$tasks = [];

		foreach($cTask as $eTask) {

			$key = \series\CultivationLib::getHarvestPartKey($eTask['variety'], $eTask['harvestSize']);
			$eTaskKept = $tasks[$eTask['cultivation']['id']][$key] ?? new \series\Task();

			if($eTaskKept->empty() or $eTaskKept['status'] === \series\Task::DONE) {
				$tasks[$eTask['cultivation']['id']][$key] = $eTask;
			}

		}

		$mTaskDelayed = \series\Task::model();

		if(\series\TaskLib::applyHarvestDelayedAll($mTaskDelayed, $eStructure, $cCultivationParts, $eAction, $week)) {

			$cTaskDelayed = $mTaskDelayed
				->select($properties)
				->getCollection();

			foreach($cTaskDelayed as $eTask) {

				$key = \series\CultivationLib::getHarvestPartKey($eTask['variety'], $eTask['harvestSize']);
				$tasks[$eTask['cultivation']['id']][$key] ??= $eTask;

			}

		}

		return $tasks;

	}

	/**
	 * L'unité de la saisie, celle de l'intervention de récolte de la semaine quand elle existe, sinon
	 * l'unité principale de la culture, que l'intervention créée prendra (`getTaskForWrite()`). Une
	 * seule règle pour la grille, le tiroir et le panneau : elle doit être celle où
	 * `writeHarvestForWeek()` écrira. `harvestUnit` reste nul tant que rien n'est récolté.
	 */
	public static function getEntryUnit(\series\Cultivation $eCultivation, \series\Task $eTask): string {

		if($eTask->empty()) {
			return $eCultivation['mainUnit'];
		}

		return $eTask['harvestUnit'] ?? $eCultivation['mainUnit'];

	}

}
?>
