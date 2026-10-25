<?php
namespace planning;

/**
 * Ce que le planning apporte à l'accueil d'une structure : les récoltes de la semaine, le pense-bête,
 * les récoltes face aux ventes à venir, et le lundi le bilan des récoltes de la semaine passée.
 *
 * Le bloc résume l'écran Récoltes et son interrupteur « Ventes à venir et stock », sans rien calculer
 * à côté : la donnée est celle de `HarvestWeekLib::get()`, qui fait foi pour cet écran (V-Planning, le
 * 2026-10-06). Il vit ici et non dans la paire de `series` parce que `planning` dépend de `series`, et
 * pas l'inverse.
 */
class DashboardLib {

	/** Une ligne par espèce vendue : au-delà, le pied mène à l'écran qui les porte toutes. */
	const int MAX = 6;

	/**
	 * `get()` lit les cultures de la semaine et huit compléments, 14 à 29 requêtes et jusqu'à 34 ms de dev
	 * sur les plus grosses structures (mesuré le 2026-10-06) : le bloc est lourd. Un quart d'heure de cache
	 * laisse une commande ou une récolte saisie paraître dans la demi-journée.
	 */
	const int CACHE = 900;

	/** Les espèces que le bilan nomme, derrière le chiffre. */
	const int BILAN_PLANTS = 3;

	/**
	 * Une semaine passée ne change plus qu'à la marge, une récolte saisie en retard : une heure de cache,
	 * comme le bilan des ventes (`selling\DashboardLib::BILAN_CACHE`).
	 */
	const int BILAN_CACHE = 3600;

	public static function getBlocks(): array {

		return [
			// En tête de la production, avant les interventions de la semaine de `series` (5) : c'est la
			// cellule que la planche range à gauche. Léger, les récoltes vivant dans la base commune.
			'planning-harvest' => [
				'section' => 'production',
				'feature' => 'production',
				'right' => 'canPlanningLimited',
				'accept' => ['acceptSeasonPlanned'],
				'position' => 0
			],
			// Juste après les récoltes et avant les interventions de la semaine de `series` (5), comme le
			// pense-bête se range en tête des piles de la Semaine. Léger : deux lectures sur l'index
			// `(structure, status, week)` de la base commune. Pas de `max` : le bloc montre ce que l'écran
			// de la Semaine montre, rien de moins.
			'planning-reminder' => [
				'section' => 'production',
				'column' => \dashboard\DashboardSetting::TODO,
				'shape' => \dashboard\DashboardSetting::NOTE,
				'feature' => 'production',
				'right' => 'canPlanningLimited',
				'accept' => ['acceptSeasonPlanned'],
				'position' => 1
			],
			// Après les deux cellules de la semaine. Les droits sont ceux de l'écran Récoltes et de ses ventes
			// à venir, que le bloc résume. Son pied mène aux ventes : « Récoltes de la semaine », sa voisine,
			// mène déjà aux récoltes.
			'planning-sales' => [
				'section' => 'production',
				'feature' => 'production',
				'right' => ['canPlanningLimited', 'canSelling'],
				'accept' => ['acceptSeasonPlanned'],
				'cost' => \dashboard\DashboardSetting::HEAVY,
				'cache' => self::CACHE,
				'max' => self::MAX,
				'position' => 20
			]
		];

	}

	/**
	 * Le bilan des récoltes, à côté de celui des ventes dans la colonne « Bilan ». Les droits de l'écran
	 * Récoltes, où son pied mène. Léger : les récoltes vivent dans la base commune.
	 */
	public static function getContributions(): array {

		return [
			'planning-bilan' => [
				'slot' => 'bilan',
				'section' => 'production',
				'feature' => 'production',
				'right' => 'canPlanningLimited',
				'cache' => self::BILAN_CACHE,
				'position' => 1
			]
		];

	}

	/** Aucune pièce ne se centre sur quoi que ce soit : `$focus` est dans la signature commune, sans lecteur. */
	public static function load(string $name, \structure\Structure $eStructure, ?string $focus = NULL): ?array {

		return match($name) {
			'planning-harvest' => self::getHarvest($eStructure),
			'planning-bilan' => self::getBilan($eStructure),
			'planning-reminder' => self::getReminders($eStructure),
			'planning-sales' => self::getSales($eStructure)
		};

	}

	public static function getUrl(string $name, \structure\Structure $eStructure, ?array $data): string {

		return match($name) {
			'planning-harvest' => HarvestWeekUi::url($eStructure),
			'planning-bilan' => HarvestWeekUi::url($eStructure, $data['week']),
			'planning-sales' => \structure\StructureUi::urlSellingSales($eStructure),
			// Le libellé choisit la cible (L38, A9) : le pense-bête ne s'affiche que dans la Semaine de ce
			// planning (`taskWeek.v.php`), et la Semaine du menu Planning ne le montre pas.
			'planning-reminder' => TaskWeekUi::url($eStructure)
		};

	}

	/**
	 * Le pense-bête de l'accueil. Sans rappel, la liste vide reste un bloc pour qui écrit au
	 * planning : sa ligne d'ajout est le seul endroit de l'accueil où écrire le premier rappel. Pour qui le
	 * lit seulement, `NULL`, et le bloc ne paraît pas.
	 *
	 * Le droit se lit ici et non dans le rendu parce que le socle pose le cadre, son titre et son pied avant
	 * d'appeler `render()` : seul `load()` peut retirer un bloc. La réponse vaut donc pour le membre qui la
	 * demande, ce qu'un cache partagé entre les membres d'une structure ne garderait pas.
	 */
	private static function getReminders(\structure\Structure $eStructure): ?array {

		$reminders = self::getRemindersToday($eStructure);

		if($reminders === [] and $eStructure->canPlanning() === FALSE) {
			return NULL;
		}

		return [
			'reminders' => $reminders
		];

	}

	/**
	 * Le pense-bête de l'accueil (`ReminderLib::getForToday()`) : ce qui reste à faire, et ce qui a été coché
	 * aujourd'hui, barré jusqu'à la fin du jour (FX, décidé le 16/10).
	 *
	 * Le bloc ne regarde pas `viewPlanningReminders`, qui règle l'affichage du pense-bête sur le planning
	 * de chaque membre, pas l'existence des rappels.
	 */
	private static function getRemindersToday(\structure\Structure $eStructure): array {

		$reminders = [];

		foreach(ReminderLib::getForToday($eStructure) as $eReminder) {
			$reminders[] = self::getReminder($eReminder);
		}

		return $reminders;

	}

	/**
	 * Un rappel tel que le bloc le rend. `before` dit un rappel resté d'une semaine passée, qui affiche sa
	 * semaine au lieu de son jour ; `done`, un rappel coché aujourd'hui ; `days` porte les sept jours de sa
	 * semaine, que ses pastilles proposent quand il se corrige sur place.
	 */
	private static function getReminder(Reminder $eReminder): array {

		return [
			'id' => $eReminder['id'],
			'text' => $eReminder['text'],
			'week' => $eReminder['week'],
			'date' => $eReminder['date'],
			'before' => ($eReminder['week'] < \Clock::week()),
			'done' => $eReminder['done'],
			'days' => ReminderLib::getDays($eReminder['week'])
		];

	}

	/**
	 * Où un rappel se range dans le bloc après un geste qui a pu le déplacer : le rappel tel que le bloc le
	 * rend, l'identifiant de celui qui le suit, `NULL` en fin de liste, et la liste entière, que la réponse
	 * d'un changement de rang redessine. `NULL` quand le rappel a quitté le bloc, coché un autre jour.
	 *
	 * @return ?array{reminder: array, next: ?int, reminders: array}
	 */
	public static function getReminderPlace(\structure\Structure $eStructure, Reminder $eReminder): ?array {

		$reminders = self::getRemindersToday($eStructure);

		foreach($reminders as $position => $reminder) {

			if($reminder['id'] === $eReminder['id']) {

				return [
					'reminder' => $reminder,
					'next' => $reminders[$position + 1]['id'] ?? NULL,
					'reminders' => $reminders
				];

			}

		}

		return NULL;

	}

	/**
	 * Ce qui a été récolté cette semaine, unité par unité et espèce par espèce, ce qui l'a été la semaine
	 * passée dans l'unité du chiffre en grand, et le nombre de cultures que le plan met en récolte cette
	 * semaine. Toujours une valeur, jamais `NULL` : une semaine sans récolte saisie le dit et dit ce que
	 * le plan attend, et c'est l'écran de la plupart des structures.
	 *
	 * Le kilo, la botte et l'unité ne se somment jamais l'un avec l'autre : chaque unité a son total. Le
	 * chiffre en grand est celui du kilo quand il y en a, sinon de la première unité récoltée.
	 *
	 * Chaque rang porte de l'espèce ce que sa vignette demande, en tableau : le socle refuse un élément dans
	 * la donnée d'un bloc (`DashboardLib::assertScalars()`), que le rendu reconstruit.
	 */
	private static function getHarvest(\structure\Structure $eStructure): array {

		$week = \Clock::week();

		$cHarvest = \series\Harvest::model()
			->select([
				'task' => ['plant' => ['name', 'fqn', 'vignette']],
				'unit', 'date',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereWeek($week)
			->group(['task', 'unit', 'date'])
			->getCollection();

		[$totals, $main, $plants] = self::sumHarvest($cHarvest);

		$lines = array_map(fn(array $plant) => [
			'plant' => $plant['plant'],
			'unit' => $plant['unit'],
			'quantity' => round($plant['quantity'], 2),
			'date' => (count($plant['dates']) === 1) ? array_key_first($plant['dates']) : NULL
		], $plants);

		return [
			'main' => $main,
			'totals' => array_map(fn(float $total) => round($total, 2), $totals),
			'previous' => ($main === NULL) ? NULL : self::getHarvestTotal($eStructure, toWeek(week_date_starts($week).' - 7 days'), $main),
			'plants' => array_slice($lines, 0, self::MAX),
			'hidden' => max(count($lines) - self::MAX, 0),
			'expected' => \series\CultivationLib::applyExpectedInWeek(new \series\CultivationModel(), $eStructure, $week)->count()
		];

	}

	/**
	 * Le bilan des récoltes de la semaine passée, rendu le lundi seulement, comme celui des ventes
	 * (`selling\DashboardLib::getBilan()`, dont il reprend la règle du jour et du cache) : le total de
	 * chaque unité, puis les premières espèces, chacune dans son unité.
	 *
	 * Le chiffre en grand est le kilo quand il y en a, sinon la première unité récoltée, comme la carte
	 * de la semaine (`getHarvest()`), et c'est lui seul qui se compare : les unités ne se somment jamais.
	 * La comparaison porte sur la même semaine ISO de l'an passé, et n'existe que si la structure y
	 * récoltait dans cette unité (`getVariation()` rend `NULL` sur un 0).
	 *
	 * Une semaine passée sans récolte rend `NULL` : le bilan ne dit pas qu'on n'a rien récolté, la
	 * carte de la semaine le dit déjà quand c'est le cas.
	 */
	private static function getBilan(\structure\Structure $eStructure): ?array {

		if(\Clock::format('N') !== '1') {
			return NULL;
		}

		$week = toWeek(week_date_starts(\Clock::week()).' - 7 days');

		$cHarvest = \series\Harvest::model()
			->select([
				'task' => ['plant' => ['name', 'fqn', 'vignette']],
				'unit', 'date',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereWeek($week)
			->group(['task', 'unit', 'date'])
			->getCollection();

		if($cHarvest->empty()) {
			return NULL;
		}

		[$totals, $main, $plants] = self::sumHarvest($cHarvest);

		$year = week_year($week);

		// Une 53e semaine n'a pas toujours de pendant l'an passé.
		if(week_number($week) <= getWeeksInYear($year - 1)) {
			$before = self::getHarvestTotal($eStructure, ($year - 1).'-W'.sprintf('%02d', week_number($week)), $main);
		} else {
			$before = 0.0;
		}

		$now = round($totals[$main], 2);

		return [
			'week' => $week,
			'year' => $year,
			'main' => $main,
			'totals' => array_map(fn(float $total) => round($total, 2), $totals),
			'variation' => \dashboard\DashboardLib::getVariation($now, $before),
			'plants' => array_map(fn(array $plant) => [
				'plant' => $plant['plant'],
				'unit' => $plant['unit'],
				'quantity' => round($plant['quantity'], 2)
			], array_slice($plants, 0, self::BILAN_PLANTS))
		];

	}

	/**
	 * Les récoltes d'une semaine, groupées par intervention, unité et jour, réunies par espèce et par
	 * unité, pour la carte de la semaine comme pour le bilan : le total de chaque unité, l'unité du
	 * chiffre en grand (le kilo quand il y en a, sinon la première récoltée), et les espèces de la plus
	 * grosse quantité à la plus petite.
	 *
	 * Le rang compare des quantités d'unités différentes, que rien ne convertit l'une en l'autre : il
	 * dit ce qui a le plus occupé la semaine, et chaque quantité s'affiche dans son unité.
	 */
	private static function sumHarvest(\Collection $cHarvest): array {

		$totals = [];
		$plants = [];

		foreach($cHarvest as $eHarvest) {

			$unit = $eHarvest['unit'];
			$key = $eHarvest['task']['plant']['id'].'-'.$unit;

			$totals[$unit] ??= 0.0;
			$totals[$unit] += $eHarvest['total'];

			$plants[$key] ??= [
				'plant' => $eHarvest['task']['plant']->extracts(['id', 'name', 'fqn', 'vignette']),
				'unit' => $unit,
				'quantity' => 0.0,
				'dates' => []
			];

			$plants[$key]['quantity'] += $eHarvest['total'];
			$plants[$key]['dates'][$eHarvest['date']] = TRUE;

		}

		$main = array_key_exists(\series\Harvest::KG, $totals) ? \series\Harvest::KG : array_key_first($totals);

		usort($plants, fn(array $plant1, array $plant2) => $plant2['quantity'] <=> $plant1['quantity']);

		return [$totals, $main, $plants];

	}

	private static function getHarvestTotal(\structure\Structure $eStructure, string $week, string $unit): float {

		return round(\series\Harvest::model()
			->whereStructure($eStructure)
			->whereWeek($week)
			->whereUnit($unit)
			->getValue(new \Sql('COALESCE(SUM(quantity), 0)', 'float')), 2);

	}

	/**
	 * Oublie le bloc des ventes à venir de `$eUser`, que l'accueil garde `CACHE` secondes : une fenêtre
	 * changée se voit au rechargement suivant, et non à l'expiration.
	 */
	public static function forgetSales(\structure\Structure $eStructure, \user\User $eUser): void {

		\Cache::redis()->delete(\dashboard\DashboardLib::getCacheKey('planning-sales', $eStructure, $eUser));

	}

	/**
	 * Les espèces de la semaine qui ont des ventes à livrer dans la fenêtre du membre
	 * (`viewPlanningSalesDays`), chacune avec ce qui est à livrer, ce qui est en stock et ce qui a été
	 * récolté cette semaine, ou `NULL` quand aucune n'a de vente.
	 *
	 * `canSelling()`, la garde des ventes, est dans la déclaration et non ici : lu dans `load()`, il ne
	 * jouerait qu'après la pose de la cellule différée.
	 *
	 * `prepareSales()` rapproche par l'espèce seule et pose les mêmes chiffres sur toutes les séries d'une
	 * espèce : la vente et le stock se prennent donc sur la première culture de l'espèce, jamais cumulés. Le
	 * récolté, lui, est propre à chaque culture, et s'additionne unité par unité, jamais d'une unité à
	 * l'autre. Aucune soustraction entre vente et récolte : leurs unités ne sont pas les mêmes.
	 *
	 * Les unités de vente partent au cache en tableau et non en `Unit`, ce que `load()` ne rend jamais.
	 */
	private static function getSales(\structure\Structure $eStructure): ?array {

		// Un membre de l'équipe qui visite la structure n'en est pas membre : il lit la fenêtre par défaut.
		$eMember = $eStructure->getMember();
		$days = $eMember->empty() ?
			\structure\Member::model()->getDefaultValue('viewPlanningSalesDays') :
			$eMember['viewPlanningSalesDays'];

		$cCultivation = HarvestWeekLib::get($eStructure, \Clock::week(), $days);

		$plants = [];

		foreach($cCultivation as $eCultivation) {

			$plant = $eCultivation['plant']['id'];

			if($eCultivation['sales']['sold'] === []) {
				continue;
			}

			$plants[$plant] ??= [
				'name' => $eCultivation['plant']['name'],
				'sold' => self::getUnitValues($eCultivation['sales']['sold']),
				'stock' => self::getUnitValues($eCultivation['sales']['stock']),
				'harvested' => []
			];

			foreach($eCultivation['harvestedWeek'] as $unit => $quantity) {
				$plants[$plant]['harvested'][$unit] ??= 0.0;
				$plants[$plant]['harvested'][$unit] += $quantity;
			}

		}

		if($plants === []) {
			return NULL;
		}

		return [
			'end' => date('Y-m-d', \Clock::shift('+'.$days.' days')),
			'plants' => array_slice(array_values($plants), 0, self::MAX),
			'hidden' => max(count($plants) - self::MAX, 0)
		];

	}

	private static function getUnitValues(array $byUnit): array {

		return array_map(fn(array $entry): array => [
			'value' => $entry['value'],
			'unit' => $entry['unit']->empty() ? NULL : $entry['unit']->extracts(['singular', 'plural', 'short'])
		], array_values($byUnit));

	}

}
?>
