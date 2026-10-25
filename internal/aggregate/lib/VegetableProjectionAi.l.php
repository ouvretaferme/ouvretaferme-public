<?php
namespace aggregate;

/**
 * Reconstruit les tables du tableau de bord maraîchage d'un groupe depuis les tables vives.
 *
 * Demandé le 19/09/2026 (« travailler les performances avec une fonctionnalité
 * d'agrégation pour les données qui sont affichées là »). L'écran lisait à chaque affichage les
 * séries, les cultures, les emplacements et les feuilles de temps de toutes les fermes du groupe :
 * 1,3 à 2,2 s mesurées sur 6388 et 6375.
 *
 * Deux passages, demandés le même jour (« Modifier le cron pour qu'il ne recalcule
 * que l'année en cours », « années suivantes jamais calculées ») :
 * - projectNight(), la nuit, ne refait que la saison et l'année civile en cours, et le 1er janvier
 *   aussi l'année écoulée (« Le 1er janvier refait l'année écoulée », 19/09/2026) : le
 *   calcul du 31/12 passe à 4 h, et ce qui est saisi plus tard ce jour-là manquerait à l'année
 *   close. Passé cette nuit, le cron ne touche plus une année close ;
 * - projectAll(), par la reprise `aggregate/configure/vegetableAi`, refait tout jusqu'à l'année en
 *   cours, pour peupler les tables ou les rebâtir quand leur format change.
 * Aucune saison à venir n'est jamais écrite.
 *
 * Le partage des rôles est celui de CultivationProjectionAiLib : ici, ce qui ne dépend que d'une
 * ferme et d'une saison ; dans `series\VegetableDashboardAiLib`, tout ce qui dépend du groupe
 * (population, seuil de séries, plancher). C'est pourquoi aucune ferme n'est écartée ici, ni par
 * son statut, ni par son consentement : l'écran n'en écarte aucune.
 */
class VegetableProjectionAiLib {

	// Fermes lues par passage. Une ferme porte jusqu'à quelques milliers de cultures, et les espèces
	// se regroupent en PHP : un paquet borne la mémoire du passage, pas sa durée.
	private const STRUCTURES = 500;

	/** Le passage de la nuit : chacune des années de getYearsForNight(), l'année écoulée d'abord. */
	public static function projectNight(): array {

		$counts = [];

		foreach(self::getYearsForNight(\Clock::date()) as $year) {
			$counts[$year] = self::project($year);
		}

		return $counts;

	}

	/** L'année de `$date`, précédée de l'année écoulée quand `$date` est un 1er janvier. */
	public static function getYearsForNight(string $date): array {

		$year = (int)substr($date, 0, 4);

		if(substr($date, 5) === '01-01') {
			return [$year - 1, $year];
		}

		return [$year];

	}

	/**
	 * Refait la saison et l'année civile `$year` sans jamais interrompre l'écran.
	 *
	 * Même mécanique que CultivationProjectionAiLib::project() : la version neuve s'écrit hors
	 * ligne, à côté de celle que l'écran sert, et prend sa place d'un coup sous une transaction.
	 * Seules les lignes de `$year` sont remplacées.
	 */
	public static function project(int $year): array {
		return self::rebuild($year);
	}

	/** Refait tout, de la première saison à l'année en cours, sans jamais interrompre l'écran. */
	public static function projectAll(): array {
		return self::rebuild(NULL);
	}

	/**
	 * Refait tout de suite, et en ligne, les lignes de quelques fermes jusqu'à l'année en cours.
	 *
	 * Pour ce qui ne peut pas attendre la nuit : les tests qui lisent l'écran d'un groupe. Les
	 * lignes des autres fermes ne bougent pas.
	 */
	public static function projectStructures(\Collection $cStructure): array {

		if($cStructure->empty()) {
			return ['plan' => 0, 'species' => 0, 'hours' => 0];
		}

		VegetablePlan::model()->beginTransaction();

			VegetablePlanAiLib::deleteByStructures($cStructure);
			VegetableSpeciesAiLib::deleteByStructures($cStructure);
			VegetableHoursAiLib::deleteByStructures($cStructure);

			$counts = self::write($cStructure, TRUE, NULL);

		VegetablePlan::model()->commit();

		return $counts;

	}

	/** La saison et l'année civile `$year`, ou toutes jusqu'à l'année en cours si `$year` est `NULL`. */
	private static function rebuild(?int $year): array {

		// Reliquat d'un passage interrompu, de n'importe quelle saison : une version que personne
		// n'a jamais servie, et qu'un passage borné à une autre saison ne publierait jamais.
		VegetablePlanAiLib::deleteOffline();
		VegetableSpeciesAiLib::deleteOffline();
		VegetableHoursAiLib::deleteOffline();

		$structures = \structure\Structure::model()
			->select('id')
			->getColumn('id');

		$totals = ['plan' => 0, 'species' => 0, 'hours' => 0];

		foreach(array_chunk($structures, self::STRUCTURES) as $chunk) {

			$counts = self::write(new \Collection(array_map(fn(int $id) => new \structure\Structure(['id' => $id]), $chunk)), FALSE, $year);

			$totals['plan'] += $counts['plan'];
			$totals['species'] += $counts['species'];
			$totals['hours'] += $counts['hours'];

		}

		self::publish($year);

		return $totals;

	}

	private static function write(\Collection $cStructure, bool $online, ?int $year): array {

		// Une transaction pour que les lectures d'un paquet voient le même instantané : une série
		// créée entre la lecture des séries et celle des cultures laisserait une culture sans sa
		// ligne de plan.
		VegetablePlan::model()->beginTransaction();

			$cVegetablePlan = self::getPlan($cStructure, $online, $year);
			$cVegetableSpecies = self::getSpecies($cStructure, $online, $year);
			$cVegetableHours = self::getHours($cStructure, $online, $year);

		VegetablePlan::model()->commit();

		VegetablePlanAiLib::createCollection($cVegetablePlan);
		VegetableSpeciesAiLib::createCollection($cVegetableSpecies);
		VegetableHoursAiLib::createCollection($cVegetableHours);

		return [
			'plan' => $cVegetablePlan->count(),
			'species' => $cVegetableSpecies->count(),
			'hours' => $cVegetableHours->count()
		];

	}

	/** L'égalité à `$year`, ou toutes les saisons jusqu'à l'année en cours : jamais une saison à venir. */
	private static function getSeasonCondition(?int $year): array {

		if($year === NULL) {
			return ['<=', \Clock::year()];
		}

		return ['=', $year];

	}

	/**
	 * Les jours de l'année civile `$year`, ou tous jusqu'à la fin de l'année en cours.
	 *
	 * ⚠️ Une borne sur la date et non `YEAR(date)` : l'index `[structure, date]` des feuilles de
	 * temps ne sert pas une fonction appliquée à sa colonne.
	 */
	private static function getDateCondition(?int $year): array {

		if($year === NULL) {
			return ['<=', \Clock::year().'-12-31'];
		}

		$model = \series\Timesheet::model();

		return ['BETWEEN', new \Sql($model->format($year.'-01-01').' AND '.$model->format($year.'-12-31'))];

	}

	/**
	 * Met la version préparée en ligne à la place de la précédente, pour `$year` ou pour tout.
	 *
	 * Sous une seule transaction : entre la suppression de l'ancienne et la promotion de la
	 * nouvelle, l'écran n'aurait rien à lire, et les trois tables doivent changer ensemble pour
	 * que ses cartes décrivent la même nuit.
	 */
	protected static function publish(?int $year): void {

		VegetablePlan::model()->beginTransaction();

			VegetablePlanAiLib::deleteOnline($year);
			VegetableSpeciesAiLib::deleteOnline($year);
			VegetableHoursAiLib::deleteOnline($year);

			VegetablePlanAiLib::publish($year);
			VegetableSpeciesAiLib::publish($year);
			VegetableHoursAiLib::publish($year);

		VegetablePlan::model()->commit();

	}

	/**
	 * Une ligne par ferme et par saison où elle a au moins une série.
	 *
	 * ⚠️ `series IS NOT NULL` écarte les emplacements d'une intervention seule : ils portent la
	 * même surface que la série qui les a produits, et les compter la doublerait.
	 */
	protected static function getPlan(\Collection $cStructure, bool $online, ?int $year): \Collection {

		$cSeries = \series\Series::model()
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'series' => new \Sql('COUNT(*)', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', ...self::getSeasonCondition($year))
			->group([new \Sql('m1.structure'), new \Sql('m1.season')])
			->getCollection();

		$cCultivation = \series\Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'cultivations' => new \Sql('COUNT(*)', 'int'),
				'harvested' => new \Sql('COALESCE(SUM(m1.harvested > 0), 0)', 'int'),
				'species' => new \Sql('COUNT(DISTINCT m2.reference)', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', ...self::getSeasonCondition($year))
			->group([new \Sql('m1.structure'), new \Sql('m1.season')])
			->getCollection();

		$cPlace = \series\Place::model()
			->join(\map\Plot::model(), 'm1.plot = m2.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'area' => new \Sql('SUM(m1.area)', 'float'),
				'greenhouse' => new \Sql('SUM(IF(m2.mode = "'.\map\Plot::GREENHOUSE.'", m1.area, 0))', 'float')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', ...self::getSeasonCondition($year))
			->where('m1.series IS NOT NULL')
			->where('m1.area > 0')
			->group([new \Sql('m1.structure'), new \Sql('m1.season')])
			->getCollection();

		$plan = [];

		foreach($cSeries as $eSeries) {

			$plan[$eSeries['structure'].'-'.$eSeries['season']] = new VegetablePlan([
				'structure' => new \structure\Structure(['id' => $eSeries['structure']]),
				'season' => $eSeries['season'],
				'series' => $eSeries['series'],
				'cultivations' => 0,
				'harvested' => 0,
				'species' => 0,
				'area' => 0.0,
				'greenhouse' => 0.0,
				'online' => $online
			]);

		}

		// Une culture et un emplacement portent toujours la saison de leur série (0 écart mesuré par
		// l'architecte le 19/09/2026) : leur ligne existe donc déjà.
		foreach($cCultivation as $eCultivation) {

			$eVegetablePlan = $plan[$eCultivation['structure'].'-'.$eCultivation['season']];
			$eVegetablePlan['cultivations'] = $eCultivation['cultivations'];
			$eVegetablePlan['harvested'] = $eCultivation['harvested'];
			$eVegetablePlan['species'] = $eCultivation['species'];

		}

		foreach($cPlace as $ePlace) {

			$eVegetablePlan = $plan[$ePlace['structure'].'-'.$ePlace['season']];
			$eVegetablePlan['area'] = $ePlace['area'];
			$eVegetablePlan['greenhouse'] = $ePlace['greenhouse'];

		}

		return new \Collection(array_values($plan));

	}

	/** Une ligne par ferme, saison et espèce de référence cultivée. */
	protected static function getSpecies(\Collection $cStructure, bool $online, ?int $year): \Collection {

		$cCultivation = \series\Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'reference' => new \Sql('m2.reference', 'int'),
				'harvestMonthsExpected'
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', ...self::getSeasonCondition($year))
			->where('m2.reference IS NOT NULL')
			->getCollection();

		$species = [];

		foreach($cCultivation as $eCultivation) {

			$key = $eCultivation['structure'].'-'.$eCultivation['season'].'-'.$eCultivation['reference'];

			$species[$key] ??= [
				'structure' => $eCultivation['structure'],
				'season' => $eCultivation['season'],
				'plant' => $eCultivation['reference'],
				'cultivations' => 0,
				'months' => []
			];

			$species[$key]['cultivations']++;

			foreach(($eCultivation['harvestMonthsExpected'] ?? []) as $month) {

				// Les mois débordent sur l'année suivante : seuls ceux de la saison de la culture
				// vont sur sa ligne, les autres appartiennent au calendrier de la saison voisine.
				if(str_starts_with($month, $eCultivation['season'].'-')) {
					$species[$key]['months'][(int)substr($month, 5, 2)] = TRUE;
				}

			}

		}

		$cVegetableSpecies = new \Collection();

		foreach($species as $values) {

			$months = array_keys($values['months']);
			sort($months);

			$cVegetableSpecies[] = new VegetableSpecies([
				'structure' => new \structure\Structure(['id' => $values['structure']]),
				'season' => $values['season'],
				'plant' => new \plant\Plant(['id' => $values['plant']]),
				'cultivations' => $values['cultivations'],
				'months' => $months,
				'online' => $online
			]);

		}

		return $cVegetableSpecies;

	}

	/**
	 * Une ligne par ferme, année civile et `fqn` de catégorie.
	 *
	 * Le temps d'une intervention sans catégorie n'est pas écrit, la jointure l'écarte : il ne
	 * pourrait pas paraître sur la carte des natures, et le chiffre de tête, qui se lit sur les
	 * mêmes lignes, doit la totaliser exactement.
	 */
	protected static function getHours(\Collection $cStructure, bool $online, ?int $year): \Collection {

		$cTimesheet = \series\Timesheet::model()
			->join(\series\Task::model(), 'm1.task = m2.id')
			->join(\work\Category::model(), 'm2.category = m3.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'year' => new \Sql('YEAR(m1.date)', 'int'),
				'fqn' => new \Sql('m3.fqn'),
				'hours' => new \Sql('SUM(m1.time)', 'float')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.date', ...self::getDateCondition($year))
			->where('m1.time > 0')
			->group([new \Sql('m1.structure'), new \Sql('YEAR(m1.date)'), new \Sql('m3.fqn')])
			->getCollection();

		$cVegetableHours = new \Collection();

		foreach($cTimesheet as $eTimesheet) {

			$cVegetableHours[] = new VegetableHours([
				'structure' => new \structure\Structure(['id' => $eTimesheet['structure']]),
				'year' => $eTimesheet['year'],
				'fqn' => $eTimesheet['fqn'],
				'hours' => $eTimesheet['hours'],
				'online' => $online
			]);

		}

		return $cVegetableHours;

	}

}
?>
