<?php
namespace planning;

/**
 * La recherche des Interventions (R-Recherche, R-Recherche-2) : ce qu'on peut chercher sur une carte, et ce
 * que portent les cartes que l'écran n'a pas encore chargées, semaines de l'Année et zones de la Semaine.
 *
 * Une seule dérivation des valeurs cherchables (`getValues()`, arbitrage A1 de V-Planning) : elle est posée
 * sur chaque intervention que lisent les écrans (`fill()`, `$eTask['search']`), et la carte la reçoit
 * telle quelle (`TaskCardUi`, `TaskYearUi::getCard()`). L'Année la rejoue sur sa lecture de toute l'année
 * (`getYear()`), d'où viennent ses suggestions et les semaines qui portent un résultat, et la Semaine sur
 * ses zones entières (`getZone()`) : le serveur et la carte ne peuvent donc pas diverger sur ce qu'une
 * intervention propose.
 *
 * Cinq groupes, ceux de l'omnibox (`TabUi::getSearchGroups()`), qui se croisent, et dont les valeurs
 * d'un même groupe s'ajoutent :
 * - `category` : la catégorie de l'intervention ;
 * - `user` : les personnes affectées et celles qui ont du temps dans la semaine de la carte, parmi les
 *   membres ;
 * - `action` : le nom de l'action, et le nom propre de l'intervention quand la carte le prend pour titre ;
 * - `plant` : l'espèce ;
 * - `place` : l'emplacement que la carte écrit (`getPlaces()`).
 * Ce sont des libellés et non des identifiants : la Semaine et le Jour relèvent leurs suggestions sur les
 * cartes, et un libellé est ce que la carte peut donner seule. La vignette d'une suggestion se retrouve par
 * son libellé : les personnes sont les membres, une espèce porte la sienne sur sa carte, et l'Année lit
 * celles de ses suggestions par leur nom (`getPlantsByName()`). Deux homonymes partagent une vignette.
 */
class TaskSearchLib {

	/**
	 * Dans l'ordre des sections de la liste, que l'Année tient de ses suggestions : celui de
	 * `TabUi::getSearchGroups()`.
	 */
	const GROUPS = ['category', 'user', 'action', 'plant', 'place'];

	/**
	 * Pose sur chaque intervention ce que la recherche y lit (`search`). Les interventions doivent porter
	 * `times`, le temps de la semaine par personne, que leur carte affiche : une même intervention propose
	 * d'autres personnes d'une semaine à l'autre. Plusieurs collections, une par semaine, lisent les membres
	 * et les catégories une seule fois.
	 */
	public static function fill(\structure\Structure $eStructure, \Collection ...$cTaskLists): void {

		$cTaskLists = array_filter($cTaskLists, fn(\Collection $cTask) => $cTask->notEmpty());

		if($cTaskLists === []) {
			return;
		}

		$cUser = \hr\TeammateLib::getUsersForPlanning($eStructure);
		$cCategory = self::getCategories($eStructure);

		foreach($cTaskLists as $cTask) {

			foreach($cTask as $eTask) {
				$eTask['search'] = self::getValues($eTask, $cUser, $cCategory);
			}

		}

	}

	/**
	 * Les valeurs cherchables d'une intervention, par groupe. Le nom propre est la première ligne écrite de la
	 * note (`\series\Task::getDescriptionStart()`), quand la carte la prend pour titre, c'est-à-dire sans série
	 * ni espèce (`TaskCardUi::getContent()`). La catégorie se nomme d'après `$cCategory`, les catégories de la
	 * structure (`getCategories()`), indexées par identifiant.
	 */
	public static function getValues(\series\Task $eTask, \Collection $cUser, \Collection $cCategory): array {

		$eTask->expects(['times', 'plannedUsers', 'description', 'cccPlace', 'category']);

		$actions = [$eTask['action']['name']];

		if($eTask['series']->empty() and $eTask['plant']->empty()) {

			$line = $eTask->getDescriptionStart();

			if($line !== '') {
				$actions[] = mb_strimwidth($line, 0, 60, '…');
			}

		}

		// Indexées par personne : une personne affectée qui a aussi du temps n'y paraît qu'une fois. Celles qui
		// ont du temps d'abord, dans l'ordre des membres, pour que l'Année, qui lit le temps sans ordre, rende
		// les mêmes valeurs que la Semaine.
		$users = [];

		foreach($cUser as $user => $eUser) {

			if(($eTask['times'][$user] ?? 0.0) > 0.0) {
				$users[$user] = $eUser->getName();
			}

		}

		foreach($eTask['plannedUsers'] as $user) {

			if($cUser->offsetExists($user)) {
				$users[$user] = $cUser[$user]->getName();
			}

		}

		return [
			'category' => [$cCategory[$eTask['category']['id']]['name']],
			'action' => $actions,
			'user' => array_values($users),
			'plant' => $eTask['plant']->empty() ? [] : [$eTask['plant']['name']],
			'place' => self::getPlaces($eTask),
		];

	}

	/**
	 * L'emplacement que la carte écrit (`TaskCardUi::getPlace()`) : celui de la série quand il y en a une,
	 * celui de l'intervention sinon, en planches. Chaque groupe sous le nom que la carte écrit, suivi de
	 * « groupe › planche » pour chacune de ses planches nommées en planches : un jeton de parcelle garde
	 * donc toutes ses planches, un jeton de planche celle-là seulement, et deux planches homonymes de deux
	 * parcelles restent deux jetons. La carte coupe la liste au-delà de `TaskCardUi::PLACES`, la recherche
	 * la prend entière.
	 */
	protected static function getPlaces(\series\Task $eTask): array {

		if($eTask['series']->notEmpty()) {
			$use = $eTask['series']['use'];
			$cccPlace = $eTask['series']['cccPlace'];
		} else {
			$use = \series\Series::BED;
			$cccPlace = $eTask['cccPlace'];
		}

		$places = [];

		foreach($cccPlace as $ccPlace) {

			foreach($ccPlace as $cPlace) {

				$group = $cPlace->first()->getGroupName();
				$places[$group] = $group;

				if($use !== \series\Series::BED) {
					continue;
				}

				foreach($cPlace as $ePlace) {

					if($ePlace->hasBedName()) {
						$bed = $group.' › '.$ePlace['bed']['name'];
						$places[$bed] = $bed;
					}

				}

			}

		}

		return array_values($places);

	}

	/**
	 * Les cartes de l'Année que la recherche voit, sans rien rendre ni hydrater : celles des semaines de
	 * l'année de `$week`, sous le masque, exactement celles qu'une colonne chargée montre
	 * (`TaskYearLib::getColumns()`).
	 *
	 * Lire les semaines une à une par `TaskWeekLib::get()` coûte 2,7 s sur la structure 7 (3 956
	 * interventions dans l'année, mesure du plan) : trois lectures à l'année sur les conditions des deux
	 * lectures de la Semaine, l'intervention prise une fois, le temps groupé par intervention, personne et
	 * semaine, les récoltes par intervention et semaine. Une intervention paraît dans chaque semaine où elle
	 * est prévue (à faire), faite (faite), ou porte du temps ou une récolte ; le masque la retire de toutes
	 * quand elle est faite, comme la Semaine (`TaskWeekLib::getBlocks()`).
	 *
	 * Les semaines se calculent sur la date, comme les lectures de la Semaine bornent la leur, et non sur la
	 * colonne `week` des temps et des récoltes, qui n'est qu'une copie.
	 *
	 * @param array $tasksShown Les interventions faites que l'écran montre encore sous le masque.
	 * @return array Les valeurs de chaque carte, par semaine puis par intervention.
	 */
	public static function getYear(\structure\Structure $eStructure, string $week, bool $hide, array $tasksShown): array {

		$weeks = TaskYearLib::getWeeks(week_year($week));
		$weekFirst = $weeks[0];
		$weekLast = $weeks[array_key_last($weeks)];

		$cUser = \hr\TeammateLib::getUsersForPlanning($eStructure);
		$cCategory = self::getCategories($eStructure);

		TaskYearLib::applyYear($eStructure, $weekFirst, $weekLast, FALSE);

		$cTask = \series\Task::model()
			->select(self::getSelection($eStructure))
			->getCollection(index: 'id');

		$times = self::getTimes($eStructure, $weeks);
		$harvests = self::getHarvests($eStructure, $weeks);

		$cards = [];

		foreach($cTask as $eTask) {

			if($hide and $eTask->isDone() and in_array($eTask['id'], $tasksShown, TRUE) === FALSE) {
				continue;
			}

			$appears = array_merge(
				array_keys($times[$eTask['id']] ?? []),
				$harvests[$eTask['id']] ?? []
			);

			if($eTask->isTodo() and $eTask['plannedWeek'] !== NULL) {
				$appears[] = $eTask['plannedWeek'];
			}

			if($eTask->isDone() and $eTask['doneWeek'] !== NULL) {
				$appears[] = $eTask['doneWeek'];
			}

			foreach(array_unique($appears) as $weekAppears) {

				if(
					$weekAppears < $weekFirst or
					$weekAppears > $weekLast
				) {
					continue;
				}

				$eTask['times'] = $times[$eTask['id']][$weekAppears] ?? [];

				$cards[$weekAppears][$eTask['id']] = self::getValues($eTask, $cUser, $cCategory);

			}

		}

		ksort($cards);

		return $cards;

	}

	/**
	 * Les cartes d'une zone de la Semaine (« En retard », « Non planifié ») que la recherche voit : toutes celles
	 * de la zone, au-delà du plafond de ses blocs, que l'écran ne charge qu'à la demande
	 * (`TaskLateLib::getZone()`, « Voir les N autres »), et « Non planifié » avant sa première ouverture. Le
	 * temps est celui de la semaine de l'écran, que ses cartes affichent (`TaskLateLib::getTasks()`).
	 *
	 * @return array `cards`, les valeurs de chaque carte par intervention, dans l'ordre de la zone ;
	 *   `actions`, l'action de chacune, qui nomme son bloc.
	 */
	public static function getZone(\structure\Structure $eStructure, string $zone, string $week): array {

		$cTaskKey = TaskLateLib::getKeys($eStructure, $zone, $week);

		$cards = [];
		$actions = [];

		if($cTaskKey->empty()) {
			return [
				'cards' => $cards,
				'actions' => $actions,
			];
		}

		$cUser = \hr\TeammateLib::getUsersForPlanning($eStructure);
		$cCategory = self::getCategories($eStructure);
		$times = self::getTimes($eStructure, [$week]);

		$cTask = \series\Task::model()
			->select(self::getSelection($eStructure))
			->whereStructure($eStructure)
			->whereId('IN', $cTaskKey->getIds())
			->getCollection(index: 'id');

		foreach($cTaskKey as $eTaskKey) {

			if($cTask->offsetExists($eTaskKey['id'])) {

				$eTask = $cTask[$eTaskKey['id']];
				$eTask['times'] = $times[$eTask['id']][$week] ?? [];

				$cards[$eTask['id']] = self::getValues($eTask, $cUser, $cCategory);
				$actions[$eTask['id']] = $eTaskKey['action']['id'];

			}

		}

		return [
			'cards' => $cards,
			'actions' => $actions,
		];

	}

	/**
	 * Les valeurs d'une seule intervention dans la semaine `$week`, pour la réponse d'un geste qui change ses
	 * personnes (`taskWeek:doUser`) : la même lecture et le même temps que `getZone()`, donc les valeurs que le
	 * rechargement de la semaine poserait, que la ligne soit dans la semaine ou dans « En retard ».
	 */
	public static function getTask(\structure\Structure $eStructure, string $week, \series\Task $eTask, \Collection $cUser, \Collection $cCategory): array {

		$eTaskSearch = \series\Task::model()
			->select(self::getSelection($eStructure))
			->whereStructure($eStructure)
			->whereId($eTask['id'])
			->get();

		$eTaskSearch['times'] = self::getTimes($eStructure, [$week])[$eTask['id']][$week] ?? [];

		return self::getValues($eTaskSearch, $cUser, $cCategory);

	}

	/**
	 * Les catégories de la structure, indexées par identifiant, qui nomment le groupe `category` de
	 * `getValues()`.
	 */
	public static function getCategories(\structure\Structure $eStructure): \Collection {
		return \work\CategoryLib::getByStructure($eStructure, index: 'id');
	}

	/**
	 * Ce que `getValues()` lit, et ce qui décide des semaines où une intervention paraît.
	 */
	protected static function getSelection(\structure\Structure $eStructure): array {

		return [
			'id',
			'status',
			'plannedWeek',
			'doneWeek',
			'plannedUsers',
			'description',
			'category',
			'series' => ['use', 'cccPlace' => \series\PlaceLib::delegateBySeries()],
			// Les interventions d'une année se comptent par milliers, et un `IN` de cette taille sur `task` seul
			// fait balayer à MySQL la table de tout le parc : bornée par la structure, la recherche passe par
			// l'index (structure, season).
			'cccPlace' => \series\PlaceLib::delegateByTask()->whereStructure($eStructure),
			'action' => ['name'],
			'plant' => ['name'],
		];

	}

	/**
	 * Le temps des semaines `$weeks`, par intervention, semaine et personne, comme `times` le porte pour une
	 * semaine (`\series\TimesheetLib::delegateByTask()`). Une ligne de temps nul fait quand même paraître
	 * l'intervention dans sa semaine, comme `\series\TimesheetLib::getTasksByWeek()` : elle reste ici, et
	 * `getValues()` n'en tire aucune personne.
	 *
	 * La date se borne par les plages de semaines qui se suivent (`TaskYearLib::getPeriods()`), dans une seule
	 * requête que l'index de la structure et de la date sert plage par plage : deux semaines éloignées ne lisent
	 * pas les semaines qui les séparent. `getHarvests()` de même.
	 */
	public static function getTimes(\structure\Structure $eStructure, array $weeks): array {

		// `or()` sans condition écrit `1` : aucune semaine lirait tout le temps de la structure.
		if($weeks === []) {
			return [];
		}

		$weekDate = new \Sql('DATE_FORMAT(date, \'%x-W%v\')');

		$cTimesheet = \series\Timesheet::model()
			->select([
				'task',
				'user',
				'weekDate' => $weekDate,
				'totalTime' => new \Sql('SUM(time)', 'float')
			])
			->whereStructure($eStructure)
			->or(...array_map(
				fn(array $period) => fn() => $this->whereDate('BETWEEN', TaskYearLib::getPeriodSql(...$period)),
				TaskYearLib::getPeriods($weeks)
			))
			->group(['task', 'user', $weekDate])
			->getCollection();

		$times = [];

		foreach($cTimesheet as $eTimesheet) {
			$times[$eTimesheet['task']['id']][$eTimesheet['weekDate']][$eTimesheet['user']['id']] = $eTimesheet['totalTime'];
		}

		return $times;

	}

	/**
	 * Parmi les semaines `$weeks`, celles où chaque intervention porte une récolte. La date se borne par plages,
	 * comme dans `getTimes()`.
	 */
	public static function getHarvests(\structure\Structure $eStructure, array $weeks): array {

		// `or()` sans condition écrit `1` : aucune semaine lirait toutes les récoltes de la structure.
		if($weeks === []) {
			return [];
		}

		$weekDate = new \Sql('DATE_FORMAT(date, \'%x-W%v\')');

		$cHarvest = \series\Harvest::model()
			->select([
				'task',
				'weekDate' => $weekDate
			])
			->whereStructure($eStructure)
			->or(...array_map(
				fn(array $period) => fn() => $this->whereDate('BETWEEN', TaskYearLib::getPeriodSql(...$period)),
				TaskYearLib::getPeriods($weeks)
			))
			->group(['task', $weekDate])
			->getCollection();

		$harvests = [];

		foreach($cHarvest as $eHarvest) {
			$harvests[$eHarvest['task']['id']][] = $eHarvest['weekDate'];
		}

		return $harvests;

	}

	/**
	 * Les suggestions de l'Année : les valeurs de toutes ses cartes, par groupe, dans l'ordre alphabétique,
	 * réduites à celles qui contiennent ce qui est tapé. C'est au serveur de filtrer, l'omnibox ne retouchant
	 * pas une liste distante (`OmniboxUi::get()`, `suggestionsUrl`).
	 *
	 * @param array $year Ce que rend `getYear()`.
	 */
	public static function getSuggestions(array $year, string $query): array {

		$needle = self::normalize($query);

		$values = array_fill_keys(self::GROUPS, []);

		foreach(self::getCards($year) as $search) {

			foreach(self::GROUPS as $group) {

				foreach($search[$group] as $value) {
					$values[$group][$value] = $value;
				}

			}

		}

		// Les nombres dans l'ordre des nombres, « n8 » avant « n10 », comme la Semaine et le Jour
		// (`PlanningTaskSearch.suggest()`).
		$collator = \L::getCollator(TRUE);

		foreach($values as $group => $valuesGroup) {

			// Les valeurs et non les clés : une clé numérique serait rendue en entier.
			$valuesGroup = array_values($valuesGroup);

			if($needle !== '') {
				$valuesGroup = array_values(array_filter($valuesGroup, fn(string $value) => str_contains(self::normalize($value), $needle)));
			}

			$collator->sort($valuesGroup);

			$values[$group] = $valuesGroup;

		}

		return $values;

	}

	/**
	 * Les espèces des suggestions de l'Année (`getSuggestions()`), par leur nom, unique dans la structure.
	 */
	public static function getPlantsByName(\structure\Structure $eStructure, array $names): \Collection {

		if($names === []) {
			return new \Collection();
		}

		return \plant\Plant::model()
			->select(self::getPlantSelection())
			->whereStructure($eStructure)
			->whereName('IN', $names)
			->getCollection();

	}

	// Ce que la vignette d'une espèce lit (`PlantUi::getVignette()`, `PlantUi::getVignetteHref()`).
	protected static function getPlantSelection(): array {
		return ['id', 'name', 'fqn', 'vignette'];
	}

	/**
	 * Les interventions que les critères retiennent, par semaine : une carte est retenue quand chaque
	 * groupe des critères a au moins une de ses valeurs, comme `PlanningTaskSearch.match()`.
	 *
	 * @param array $year Ce que rend `getYear()`.
	 * @param array $criteria Les valeurs des jetons, par groupe.
	 * @return array Les identifiants retenus par semaine, les semaines sans résultat absentes.
	 */
	public static function getMatches(array $year, array $criteria): array {

		$criteria = self::normalizeCriteria($criteria);

		$weeks = [];

		foreach($year as $week => $cards) {

			$ids = self::match($cards, $criteria);

			if($ids !== []) {
				$weeks[$week] = $ids;
			}

		}

		return $weeks;

	}

	/**
	 * Les interventions que les critères retiennent dans les zones de la Semaine, par zone puis par bloc :
	 * l'écran ouvre « Voir les N autres » d'un bloc dont il ne montre pas tous les résultats, et compte ceux
	 * d'une zone qu'il n'a pas encore chargée.
	 *
	 * @param array $zones Ce que rend `getZone()`, par zone.
	 * @return array Les identifiants retenus, par zone puis par action ; une zone sans résultat est vide.
	 */
	public static function getMatchesZones(array $zones, array $criteria): array {

		$criteria = self::normalizeCriteria($criteria);

		$matches = [];

		foreach($zones as $name => $zone) {

			$matches[$name] = [];

			foreach(self::match($zone['cards'], $criteria) as $id) {
				$matches[$name][$zone['actions'][$id]][] = $id;
			}

		}

		return $matches;

	}

	/**
	 * Des critères reçus de l'écran : chaque groupe est un groupe de la recherche, et porte une liste non vide
	 * de libellés. Une entrée tableau forgée lèverait une notice à la comparaison.
	 */
	public static function isCriteria(array $criteria): bool {

		foreach($criteria as $group => $values) {

			if(
				in_array($group, self::GROUPS, TRUE) === FALSE or
				is_array($values) === FALSE or
				$values === [] or
				count(array_filter($values, 'is_string')) !== count($values)
			) {
				return FALSE;
			}

		}

		return TRUE;

	}

	protected static function normalizeCriteria(array $criteria): array {

		return array_map(fn(array $values) => array_map(fn(string $value) => self::normalize($value), $values), $criteria);

	}

	protected static function match(array $cards, array $criteria): array {

		$ids = [];

		foreach($cards as $id => $search) {

			foreach($criteria as $group => $values) {

				$found = array_filter($search[$group], fn(string $value) => in_array(self::normalize($value), $values, TRUE));

				if($found === []) {
					continue 2;
				}

			}

			$ids[] = $id;

		}

		return $ids;

	}

	/**
	 * Toutes les cartes de `getYear()`, semaine après semaine.
	 */
	protected static function getCards(array $year): \Generator {

		foreach($year as $cards) {
			yield from $cards;
		}

	}

	/**
	 * La forme sous laquelle deux libellés se comparent, celle de `UtilOmnibox.normalize()` : en minuscules,
	 * sans accents ni blancs aux bords, les espaces insécables repliées sur une espace. Une forme différente
	 * du navigateur ferait suggérer par le serveur une valeur que le filtre des colonnes chargées ne
	 * retrouverait pas.
	 */
	public static function normalize(string $value): string {

		return trim(preg_replace(['/[\x{0300}-\x{036f}]/u', '/[\x{00a0}\x{202f}]/u'], ['', ' '], \Normalizer::normalize(mb_strtolower($value), \Normalizer::FORM_D)));

	}

}
?>
