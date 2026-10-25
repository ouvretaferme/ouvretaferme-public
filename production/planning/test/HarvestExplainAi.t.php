<?php
namespace planning;

/**
 * Les plans d'exécution des requêtes de l'écran des récoltes, sortis de `HarvestWeek` quand ce fichier
 * est passé sur le décor (lot 6.2f, V3, sous-lot 4). Celui de la dernière récolte avant un jour est
 * parti avec la vue Jour (R143).
 *
 * Un `EXPLAIN` ne vaut que sur un volume réel : sur les quelques cultures que pose un décor,
 * l'optimiseur préfère balayer la table, et le test dirait faux dans les deux sens. Ce fichier reste
 * donc sur la structure 7 et ses propres cultures, emprunt inventorié dans `BorrowedAiLib`
 * (`7-cultivation`), dont `assert()` lève dans `init()` si la structure ou ses cultures manquent.
 *
 * Il n'écrit rien et n'hérite d'aucun décor : celui de `HarvestFixtureTest` écrirait sur la 7 (les
 * préférences du membre, l'horloge figée). Toutes les tables lues vivent dans la base commune, la base
 * de la structure n'est donc pas connectée.
 *
 * Lancement : dev/test package=planning file=HarvestExplainAi
 */
class HarvestExplainAiTest extends \Test {

	const STRUCTURE = 7;

	private \structure\Structure $eStructure;

	public function init(): void {

		\structure\BorrowedAiLib::assert('production/planning/test/HarvestExplainAi.t.php');

		$this->eStructure = \structure\StructureLib::getById(static::STRUCTURE);

	}

	/**
	 * Les requêtes de `CultivationLib::getForHarvestWeek()` lisent un index, jamais la table entière.
	 * Chacune est construite par la méthode `apply*` que son Lib appelle, pour que l'`EXPLAIN` mesure la
	 * requête du produit et non une copie, sur la semaine courante de la structure 7 et ses propres
	 * cultures, pour que l'optimiseur juge sur un volume réel. Q4 et Q5 font exception : ce sont les
	 * délégués de `getHarvestWeekSelection()`, dont le cadriciel pose seul la condition `IN`, et elles
	 * reprennent cette condition :
	 * - Q1, les cultures prévues : `(structure, season)`, en `range` puisque la saison est un `IN` ;
	 * - Q2, le récolté de la semaine par couple variété · calibre : `(structure, week)`, en `ref`, joint à
	 *   ses interventions par la clé primaire ;
	 * - Q3, les cultures récoltées hors période : la clé primaire ;
	 * - Q4, les emplacements des séries : `UNIQUE (series, bed)`, en `range` sur un `IN` ;
	 * - Q5, les parts variétales des cultures : `UNIQUE (cultivation, variety)`, en `range` sur un `IN`.
	 *
	 * Et celles de la saisie :
	 * - Q6, `CultivationLib::getForTaskQuery()`, les cultures que cherchent « Nouvelle récolte » et « Nouvelle
	 *   intervention », lues sans limite avant d'être rangées (`HarvestWeekLib::rank()`) :
	 *   `(structure, season)` ; ses citations de séries et d'espèces sont bornées par la liste qu'elles
	 *   filtrent, la recherche d'espèce par nom balaie le référentiel des espèces (déclaré à la livraison) ;
	 * - Q7, `TaskLib::getHarvestForWeek()`, l'intervention de récolte de la semaine de chaque culture :
	 *   `(cultivation, action)` ou `(structure, …)`, jamais la table ;
	 * - Q8, le récolté d'un jour que remplace `TaskLib::writeHarvestForWeek()` : `(structure, date)` ;
	 * - Q9, l'intervention en retard que `TaskLib::getHarvestForWeek()` prend à défaut de celle de la
	 *   semaine, bornée par `TaskLib::applyDelayed()` comme le planning la borne ;
	 * - Q10, la part ventilée de ce récolté, qui refuse le remplacement : `(structure, date)` comme Q8
	 *   pour le récolté, `(cultivation, action)` ou la clé primaire pour les interventions ventilées ;
	 * - Q11, le récolté d'un couple que remplace la saisie d'une part, comme Q10 ;
	 * - Q12, les unités des couples de la semaine, comme Q7.
	 *
	 * Et celle de la colonne « Semaine précédente », que la vue Semaine montre (R-Retours-1210, 304) :
	 * - Q13, `HarvestLib::getTotalInWeek()`, le récolté de la semaine d'avant : `(structure, week)`, comme Q2.
	 *
	 * Et celles des cultures qui restent listées hors de leur période (règles R1 et R2, R-Retours-0710) :
	 * - Q14, `CultivationLib::applyHarvestWindow()`, les cultures des saisons voisines dont la période prévue
	 *   peut encadrer la semaine : l'optimiseur part des séries ouvertes, lues par leur index en `range`, et
	 *   rejoint leurs cultures par `UNIQUE (series, …)` en `ref` ;
	 * - Q15, `CultivationLib::applyHarvestedInWeekPrevious()`, les cultures récoltées la semaine d'avant :
	 *   `(structure, week)`, comme Q2.
	 *
	 * Q14 et Q15 sont les deux requêtes ajoutées par ce lot.
	 *
	 * Et celle du temps que la case d'un jour somme (R-Retours-1210) :
	 * - Q16, `HarvestWeekLib::applyTasksHarvested()`, les interventions récoltées dans la semaine :
	 *   `(structure, week)`, comme Q2, jointes à leurs interventions par la clé primaire, sans table entière.
	 *
	 * Et celles qu'une saisie relit, bornée aux espèces qu'elle peut changer (R-Retours-1210, EX) :
	 * - Q17 à Q20, Q1, Q2, Q14 et Q15 bornées à ces espèces : le même index que la requête entière, l'espèce
	 *   s'ajoutant en condition, citation comprise pour le récolté ;
	 * - Q23, la première lecture de `HarvestWeekLib::getPlantsAround()`, les espèces des cultures saisies
	 *   (une seule, ou les séries cochées de « Récolter », EX-2) : la clé primaire ;
	 * - Q21, `SeriesLib::applyByPlant()`, les séries de ces espèces : l'optimiseur part de sa citation
	 *   des cultures, lue par `(structure, plant)`, et rejoint les séries par la clé primaire ;
	 * - Q22, `CultivationLib::applyBySeriesName()` groupée par espèce comme dans `HarvestWeekLib::getPlantsAround()`,
	 *   les espèces des séries homonymes : `UNIQUE (series, …)`, sa citation des séries par nom lisant
	 *   `(structure, season)` sur sa structure, faute d'index sur le nom.
	 *
	 * Et celle des parts qui ne portent que du temps (R-Retours-1210, V22) :
	 * - Q24, `CultivationLib::applyHarvestTimedInWeek()`, le temps de la semaine sur les interventions de récolte
	 *   des cultures : `(structure, date)` en `range`, jointes à leurs interventions par la clé primaire.
	 *
	 * Aucune ligne d'`EXPLAIN` de Q14 à Q24 ne lit la table entière.
	 */
	public function testExplainWeek(): void {

		$week = \Clock::week();
		$season = week_year($week);

		$cCultivation = \series\Cultivation::model()
			->select(['id', 'series', 'plant'])
			->whereStructure($this->eStructure)
			->whereSeason($season)
			->getCollection(0, 20);

		if($this->assertTrue($cCultivation->notEmpty(), 'la structure '.static::STRUCTURE.' n\'a aucune culture en '.$season.', l\'EXPLAIN ne mesurerait pas un volume réel') === FALSE) {
			return;
		}

		$eAction = \work\ActionLib::getByStructure($this->eStructure, fqn: ACTION_RECOLTE);

		$cPlant = HarvestWeekLib::getPlantsAround($this->eStructure, $cCultivation->getIds());
		$cPlantCultivation = $cCultivation->getColumnCollection('plant');
		$names = \series\SeriesLib::applyByPlant(new \series\SeriesModel()->select('name'), $this->eStructure, $cPlantCultivation)->getColumn('name');

		$queries = [
			'Q1' => \series\CultivationLib::applyExpectedInWeek(new \series\CultivationModel()->select(['id']), $this->eStructure, $week),
			'Q2' => \series\CultivationLib::applyHarvestedInWeek(new \series\HarvestModel(), $this->eStructure, $week),
			'Q3' => \series\CultivationLib::applyByIds(new \series\CultivationModel()->select(['id']), $this->eStructure, $cCultivation->getIds()),
			'Q4' => new \series\PlaceModel()
				->select(['id'])
				->whereSeries('IN', $cCultivation->getColumnCollection('series')),
			'Q5' => new \series\SliceModel()
				->select(['id'])
				->whereCultivation('IN', $cCultivation),
			'Q6' => \series\CultivationLib::applyTaskQuery(new \series\CultivationModel()->select(['id']), $this->eStructure, $week, NULL, 'to'),
			'Q7' => \series\TaskLib::applyHarvestWeek(new \series\TaskModel()->select(['id']), $this->eStructure, $cCultivation, $eAction, $week),
			'Q8' => \series\TaskLib::applyHarvestedDay(new \series\HarvestModel()->select(['id']), $this->eStructure, $cCultivation->first(), \Clock::date(), \series\Harvest::KG),
			'Q10' => \series\TaskLib::applyHarvestedDayVentilated(new \series\HarvestModel()->select(['id']), $this->eStructure, $cCultivation->first(), \Clock::date(), \series\Harvest::KG),
			'Q11' => \series\TaskLib::applyHarvestedDayCouple(new \series\HarvestModel()->select(['id']), $this->eStructure, $cCultivation->first(), \Clock::date(), \series\Harvest::KG, new \plant\Variety(['id' => 1]), new \plant\Size()),
			'Q13' => \series\HarvestLib::applyTotalInWeek(new \series\HarvestModel(), $this->eStructure, $cCultivation, toWeek(week_date_starts($week).' - 7 days')),
			'Q14' => \series\CultivationLib::applyHarvestWindow(new \series\CultivationModel()->select(['id']), $this->eStructure, $week),
			'Q15' => \series\CultivationLib::applyHarvestedInWeekPrevious(new \series\HarvestModel(), $this->eStructure, $week),
			'Q16' => HarvestWeekLib::applyTasksHarvested(new \series\HarvestModel(), $this->eStructure, $cCultivation, $week),
			'Q12' => \series\TaskLib::applyHarvestWeekAll(new \series\TaskModel()->select(['id']), $this->eStructure, $cCultivation, $eAction, $week)
				->where('variety IS NOT NULL OR harvestSize IS NOT NULL'),
			'Q17' => \series\CultivationLib::applyExpectedInWeek(new \series\CultivationModel()->select(['id']), $this->eStructure, $week, $cPlant),
			'Q18' => \series\CultivationLib::applyHarvestedInWeek(new \series\HarvestModel(), $this->eStructure, $week, $cPlant),
			'Q19' => \series\CultivationLib::applyHarvestWindow(new \series\CultivationModel()->select(['id']), $this->eStructure, $week, $cPlant),
			'Q20' => \series\CultivationLib::applyHarvestedInWeekPrevious(new \series\HarvestModel(), $this->eStructure, $week, $cPlant),
			'Q21' => \series\SeriesLib::applyByPlant(new \series\SeriesModel()->select(['id']), $this->eStructure, $cPlantCultivation),
			'Q22' => \series\CultivationLib::applyBySeriesName(new \series\CultivationModel()->select(['plant']), $this->eStructure, $names)
				->group('plant'),
			'Q23' => \series\CultivationLib::applyByIds(new \series\CultivationModel()->select(['plant']), $this->eStructure, $cCultivation->getIds())
				->group('plant'),
			'Q24' => \series\CultivationLib::applyHarvestTimedInWeek(new \series\TimesheetModel(), $this->eStructure, $week, $cCultivation->getIds()),
		];

		$mTaskDelayed = new \series\TaskModel()->select(['id']);

		$this->assertTrue(\series\TaskLib::applyHarvestDelayed($mTaskDelayed, $this->eStructure, $cCultivation, $eAction, $week), 'la semaine courante ne pose pas la condition du retard, Q9 ne mesurerait rien');
		$queries['Q9'] = $mTaskDelayed;

		foreach($queries as $name => $query) {

			// Q2 nomme ses tables `m1` et `m2`, que `getSql()` renommerait `s1` et `s2` par défaut.
			$sql = (string)$query->getSql('m');
			$rows = \series\Cultivation::model()->pdo()->query('EXPLAIN '.$sql)->fetchAll(\PDO::FETCH_ASSOC);

			$this->assertTrue(in_array($rows[0]['type'], ['const', 'eq_ref', 'ref', 'range'], TRUE), $name.' ne lit pas d\'index : '.$sql.' '.json_encode($rows));
			$this->assertTrue($rows[0]['key'] !== NULL, $name.' ne nomme aucun index : '.$sql.' '.json_encode($rows));

			if(in_array($name, ['Q14', 'Q15', 'Q16', 'Q17', 'Q18', 'Q19', 'Q20', 'Q21', 'Q22', 'Q23', 'Q24'], TRUE)) {
				$this->assertTrue(array_any($rows, fn($row) => $row['type'] === 'ALL') === FALSE, $name.' lit une table entière : '.$sql.' '.json_encode($rows));
			}

		}

	}

}
