<?php
namespace aggregate;

/**
 * La projection du tableau de bord maraîchage rend, ferme par ferme, ce que les tables vives
 * disent.
 *
 * Chaque méthode de lecture est appelée sur les fermes du groupe semé par
 * `series\VegetableDashboardFixtureAiTest` et confrontée à une lecture indépendante des tables
 * vives, écrite sans le Lib. Les méthodes sont protégées et
 * s'éprouvent par réflexion, comme dans CultivationProjectionAiTest : `project()` et `projectAll()`
 * liraient tout le parc et réécriraient les trois tables de la base commune, que les autres
 * sessions lisent, en en tenant les verrous jusqu'au retour arrière.
 *
 * Les lectures indépendantes s'arrêtent à l'année en cours, comme la projection : une saison à
 * venir n'est jamais calculée (19/09/2026).
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=aggregate file=VegetableProjectionAi
 */
class VegetableProjectionAiTest extends \series\VegetableDashboardFixtureAiTest {

	public function setUp(): void {
		VegetablePlan::model()->beginTransaction();
	}

	public function tearDown(): void {
		VegetablePlan::model()->rollBack();
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		return new \ReflectionMethod(VegetableProjectionAiLib::class, $method)->invoke(NULL, ...$arguments);
	}

	private function structures(): \Collection {

		return \structure\AffiliationAiLib::getStructuresByCommunity($this->eStructureCommunity, new \user\User(['id' => NULL]));

	}

	/** Les lignes d'une collection projetée, indexées par ferme et saison (ou année). */
	private function index(\Collection $c, string $period, string ...$properties): array {

		$lines = [];

		foreach($c as $e) {

			$key = $e['structure']['id'].'-'.$e[$period];

			foreach($properties as $property) {
				$lines[$key][$property] = $e[$property];
			}

		}

		ksort($lines);

		return $lines;

	}

	/**
	 * Séries, cultures, récoltes et surfaces de chaque ferme et saison, relues sans le Lib.
	 *
	 * ⚠️ Les emplacements se relisent eux aussi avec `series IS NOT NULL` : c'est la règle de
	 * l'écran avant la projection, et la retirer ici comme là-bas ferait passer le contrôle en
	 * doublant les deux côtés.
	 */
	public function testPlanMatchesLiveTables(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$this->assertTrue($cStructure->count() >= \series\VegetableDashboardAiLib::FLOOR, 'le groupe du décor doit compter au moins '.\series\VegetableDashboardAiLib::FLOOR.' fermes, il en a '.$cStructure->count());

		$cVegetablePlan = $this->invoke('getPlan', $cStructure, FALSE, NULL);

		$expected = [];

		$cSeries = \series\Series::model()
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'series' => new \Sql('COUNT(*)', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', '<=', (int)date('Y'))
			->group([new \Sql('m1.structure'), new \Sql('m1.season')])
			->getCollection();

		foreach($cSeries as $eSeries) {
			$expected[$eSeries['structure'].'-'.$eSeries['season']] = ['series' => $eSeries['series'], 'cultivations' => 0, 'harvested' => 0, 'area' => 0.0];
		}

		foreach(\series\Cultivation::model()->select(['structure', 'season', 'harvested'])->where('m1.structure', 'IN', $cStructure)->where('m1.season', '<=', (int)date('Y'))->getCollection() as $eCultivation) {

			$key = $eCultivation['structure']['id'].'-'.$eCultivation['season'];

			$expected[$key]['cultivations']++;

			if($eCultivation['harvested'] > 0) {
				$expected[$key]['harvested']++;
			}

		}

		foreach(\series\Place::model()->select(['structure', 'season', 'area'])->where('m1.structure', 'IN', $cStructure)->where('m1.season', '<=', (int)date('Y'))->where('m1.series IS NOT NULL')->where('m1.area > 0')->getCollection() as $ePlace) {
			$expected[$ePlace['structure']['id'].'-'.$ePlace['season']]['area'] += $ePlace['area'];
		}

		ksort($expected);

		$lines = $this->index($cVegetablePlan, 'season', 'series', 'cultivations', 'harvested', 'area');

		$this->assertEquals(array_keys($expected), array_keys($lines), 'une ligne par ferme et par saison où elle a une série');

		// Les surfaces se comparent à un centième près et non arrondies : SQL et PHP ne somment pas
		// les flottants dans le même ordre, et un arrondi tombe d'un côté ou de l'autre d'une
		// demi-unité (30 735,3498 contre 30 735,35 sur la ferme 178 en 2025).
		$different = array_keys(array_filter($lines, fn(array $line, string $key) => (
			[$line['series'], $line['cultivations'], $line['harvested']] !== [$expected[$key]['series'], $expected[$key]['cultivations'], $expected[$key]['harvested']] or
			abs($line['area'] - $expected[$key]['area']) > 0.01
		), ARRAY_FILTER_USE_BOTH));

		$this->assertEquals([], $different, 'lignes qui diffèrent des tables vives : '.implode(', ', $different));

		echo "\n  ".$this->eStructureCommunity['id']." : ".count($lines)." saisons de fermes projetées\n";

	}

	/**
	 * `VegetablePlan.species` et les lignes de `VegetableSpecies` disent le même nombre par deux
	 * chemins : la projection les écrit tous deux, et un écart ferait se contredire deux cartes.
	 */
	public function testSpeciesCountMatchesSpeciesLines(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$cVegetablePlan = $this->invoke('getPlan', $cStructure, FALSE, NULL);
		$cVegetableSpecies = $this->invoke('getSpecies', $cStructure, FALSE, NULL);

		$lines = [];

		foreach($cVegetableSpecies as $eVegetableSpecies) {

			$key = $eVegetableSpecies['structure']['id'].'-'.$eVegetableSpecies['season'];

			$lines[$key] ??= 0;
			$lines[$key]++;

		}

		$different = [];

		foreach($cVegetablePlan as $eVegetablePlan) {

			$key = $eVegetablePlan['structure']['id'].'-'.$eVegetablePlan['season'];

			if($eVegetablePlan['species'] !== ($lines[$key] ?? 0)) {
				$different[] = $key.' ('.$eVegetablePlan['species'].' contre '.($lines[$key] ?? 0).')';
			}

		}

		$this->assertTrue($cVegetableSpecies->notEmpty(), 'le groupe doit avoir des espèces, sinon le contrôle ne compare rien');
		$this->assertEquals([], $different, 'nombre d\'espèces qui diffère du nombre de lignes : '.implode(', ', $different));

	}

	/**
	 * Chaque ligne d'espèce porte les cultures et les mois de sa seule saison.
	 *
	 * ⚠️ Les mois de récolte prévus débordent sur l'année suivante. Le contrôle exige qu'un tel
	 * débordement existe dans le groupe : sans lui, il passerait sans avoir éprouvé la borne.
	 */
	public function testSpeciesMonthsStayInSeason(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$cCultivation = \series\Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'season' => new \Sql('m1.season', 'int'),
				'reference' => new \Sql('m2.reference', 'int'),
				'harvestMonthsExpected'
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', '<=', (int)date('Y'))
			->where('m2.reference IS NOT NULL')
			->getCollection();

		$expected = [];
		$overflow = 0;

		foreach($cCultivation as $eCultivation) {

			$key = $eCultivation['structure'].'-'.$eCultivation['season'].'-'.$eCultivation['reference'];

			$expected[$key] ??= ['cultivations' => 0, 'months' => []];
			$expected[$key]['cultivations']++;

			foreach($eCultivation['harvestMonthsExpected'] ?? [] as $month) {

				if((int)substr($month, 0, 4) === $eCultivation['season']) {
					$expected[$key]['months'][] = (int)substr($month, 5, 2);
				} else {
					$overflow++;
				}

			}

		}

		foreach($expected as $key => $line) {
			$months = array_values(array_unique($line['months']));
			sort($months);
			$expected[$key]['months'] = $months;
		}

		ksort($expected);

		$lines = [];

		foreach($this->invoke('getSpecies', $cStructure, FALSE, NULL) as $eVegetableSpecies) {
			$lines[$eVegetableSpecies['structure']['id'].'-'.$eVegetableSpecies['season'].'-'.$eVegetableSpecies['plant']['id']] = [
				'cultivations' => $eVegetableSpecies['cultivations'],
				'months' => $eVegetableSpecies['months']
			];
		}

		ksort($lines);

		$this->assertTrue($overflow > 0, 'aucun mois de récolte hors de la saison de sa culture dans le groupe '.$this->eStructureCommunity['id'].' : la borne n\'est pas éprouvée');

		$this->assertEquals(array_keys($expected), array_keys($lines), 'une ligne par ferme, saison et espèce de référence');

		$different = array_keys(array_filter($lines, fn(array $line, string $key) => $line !== $expected[$key], ARRAY_FILTER_USE_BOTH));

		$this->assertEquals([], $different, 'lignes d\'espèce qui diffèrent des cultures : '.implode(', ', $different));

		echo "  ".$this->eStructureCommunity['id']." : ".count($lines)." lignes d'espèce, ".$overflow." mois laissés à la saison suivante\n";

	}

	/**
	 * Les heures de chaque ferme, année et `fqn`, relues sans le Lib.
	 *
	 * ⚠️ Relues avec la jointure sur la catégorie : le temps d'une intervention sans catégorie
	 * n'est pas projeté, parce que la carte des natures ne pourrait pas le montrer et que le
	 * chiffre de tête doit la totaliser exactement. Le contrôle exige aussi une ligne sans `fqn`,
	 * celle d'une catégorie qu'une ferme s'est créée, qui va dans le reste de l'écran.
	 */
	public function testHoursMatchLiveTables(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$expected = [];

		$cTimesheet = \series\Timesheet::model()
			->join(\series\Task::model(), 'm1.task = m2.id')
			->join(\work\Category::model(), 'm2.category = m3.id')
			->select([
				'structure' => new \Sql('m1.structure', 'int'),
				'date' => new \Sql('m1.date'),
				'fqn' => new \Sql('m3.fqn'),
				'time' => new \Sql('m1.time', 'float')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.date', '<=', date('Y').'-12-31')
			->where('m1.time > 0')
			->getCollection();

		foreach($cTimesheet as $eTimesheet) {

			$key = $eTimesheet['structure'].'-'.substr($eTimesheet['date'], 0, 4).'-'.($eTimesheet['fqn'] ?? '');

			$expected[$key] ??= 0.0;
			$expected[$key] += $eTimesheet['time'];

		}

		$expected = array_map(fn(float $hours) => round($hours, 2), $expected);
		ksort($expected);

		$lines = [];
		$withoutFqn = 0;

		foreach($this->invoke('getHours', $cStructure, FALSE, NULL) as $eVegetableHours) {

			if($eVegetableHours['fqn'] === NULL) {
				$withoutFqn++;
			}

			$lines[$eVegetableHours['structure']['id'].'-'.$eVegetableHours['year'].'-'.($eVegetableHours['fqn'] ?? '')] = round($eVegetableHours['hours'], 2);

		}

		ksort($lines);

		$this->assertTrue($withoutFqn > 0, 'aucune catégorie sans fqn dans le groupe '.$this->eStructureCommunity['id'].' : le reste de l\'écran n\'est pas éprouvé');

		$this->assertEquals(array_keys($expected), array_keys($lines), 'une ligne par ferme, année et fqn');

		$different = array_keys(array_filter($lines, fn(float $hours, string $key) => abs($hours - $expected[$key]) > 0.01, ARRAY_FILTER_USE_BOTH));

		$this->assertEquals([], $different, 'heures qui diffèrent des feuilles de temps : '.implode(', ', $different));

	}

	/**
	 * `projectStructures()` refait en ligne les fermes données, et elles seules.
	 *
	 * La recette et les tests s'en servent sur la base commune : une ferme hors de la liste dont
	 * les lignes bougeraient changerait l'écran d'un autre groupe.
	 */
	public function testProjectStructuresTouchesOnlyItsStructures(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures()->slice(0, 2, TRUE);

		$before = VegetablePlan::model()
			->where('structure', 'NOT IN', $cStructure)
			->count();

		// Une ligne en préparation d'une de ces fermes, laissée par un passage interrompu : elle
		// doit partir avec les autres.
		VegetablePlanAiLib::createCollection(new \Collection([
			new VegetablePlan([
				'structure' => $cStructure->first(),
				'season' => 1999,
				'series' => 0,
				'cultivations' => 0,
				'harvested' => 0,
				'species' => 0,
				'area' => 0.0,
				'greenhouse' => 0.0,
				'online' => FALSE
			])
		]));

		$counts = VegetableProjectionAiLib::projectStructures($cStructure);

		$cVegetablePlan = VegetablePlan::model()
			->select(['structure', 'season', 'online'])
			->whereStructure('IN', $cStructure)
			->getCollection();

		$this->assertTrue($counts['plan'] > 0, 'les deux premières fermes du groupe doivent avoir des séries, sinon le contrôle ne projette rien');
		$this->assertEquals($counts['plan'], $cVegetablePlan->count(), 'les fermes données n\'ont que les lignes de ce passage');
		$this->assertEquals(0, $cVegetablePlan->find(fn(VegetablePlan $e) => $e['online'] === FALSE)->count(), 'toutes en ligne, la ligne en préparation est partie');
		$this->assertEquals($before, VegetablePlan::model()->where('structure', 'NOT IN', $cStructure)->count(), 'les autres fermes gardent leurs lignes');

	}

	/**
	 * Aucune saison ni année à venir n'est écrite (19/09/2026 : « années suivantes jamais
	 * calculées »), alors que le groupe en a déjà planifié.
	 */
	public function testNoFutureSeason(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();
		$year = (int)date('Y');

		$future = \series\Series::model()
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', '>', $year)
			->count();

		$this->assertTrue($future > 0, 'le groupe '.$this->eStructureCommunity['id'].' n\'a planifié aucune saison après '.$year.' : le contrôle ne prouve rien');

		// Du temps saisi l'an prochain, que la dev ne porte pas : une copie d'une feuille de temps
		// du groupe, dont l'intervention a une catégorie. Écriture directe, le temps d'une date à
		// venir n'étant pas un état que le test doit obtenir du produit, seulement de la table.
		$eTimesheet = \series\Timesheet::model()
			->select(['structure', 'user', 'task', 'time', 'createdBy'])
			->join(\series\Task::model(), 'm1.task = m2.id')
			->where('m1.structure', 'IN', $cStructure)
			->where('m2.category IS NOT NULL')
			->where('m1.time > 0')
			->get();

		$this->assertTrue($eTimesheet->notEmpty(), 'aucune feuille de temps dans le groupe '.$this->eStructureCommunity['id']);

		$eTimesheet['date'] = ($year + 1).'-06-01';
		$eTimesheet['week'] = ($year + 1).'-W22';

		\series\Timesheet::model()->insert($eTimesheet);

		foreach(['getPlan' => 'season', 'getSpecies' => 'season', 'getHours' => 'year'] as $method => $period) {

			$periods = $this->invoke($method, $cStructure, FALSE, NULL)->getColumn($period);

			$this->assertTrue($periods !== [], $method.' ne rend rien');
			$this->assertTrue(max($periods) <= $year, $method.' écrit '.max($periods).', après '.$year);

		}

	}

	/**
	 * La nuit refait l'année en cours, et le 1er janvier d'abord l'année écoulée : le calcul du 31/12
	 * passe à 4 h, et ce qui est saisi plus tard ce jour-là manquerait à l'année close (
	 * 19/09/2026, « Le 1er janvier refait l'année écoulée »).
	 */
	public function testNightRedoesClosedYearOnJanuaryFirst(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$this->assertEquals([2026], \aggregate\VegetableProjectionAiLib::getYearsForNight('2026-12-31'));
		$this->assertEquals([2026, 2027], \aggregate\VegetableProjectionAiLib::getYearsForNight('2027-01-01'));
		$this->assertEquals([2027], \aggregate\VegetableProjectionAiLib::getYearsForNight('2027-01-02'));

	}

	/**
	 * Le passage de nuit remplace les lignes de son année, et celles-là seulement.
	 *
	 * ⚠️ `publish($year)` porte sur tout le parc : les lignes 2026 en ligne de toutes les fermes
	 * sont remplacées par celles des fermes du groupe, jusqu'au retour arrière. Une valeur témoin
	 * posée sur une saison passée, qu'aucune projection ne produit, doit sortir intacte.
	 */
	public function testYearPassKeepsPastSeasons(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();
		$year = (int)date('Y');

		// Écritures directes : un témoin est justement une valeur que la projection ne fabrique pas.
		// Un par table, chacune ayant son propre deleteOnline() à borner.
		VegetablePlan::model()
			->whereSeason($year - 1)
			->whereOnline(TRUE)
			->update(['series' => 99999]);

		VegetableHours::model()
			->whereYear($year - 1)
			->whereOnline(TRUE)
			->update(['hours' => 99999.0]);

		VegetableSpecies::model()
			->whereSeason($year - 1)
			->whereOnline(TRUE)
			->update(['cultivations' => 99999]);

		$past = VegetablePlan::model()->whereSeason($year - 1)->whereOnline(TRUE)->count();
		$pastHours = VegetableHours::model()->whereYear($year - 1)->whereOnline(TRUE)->count();
		$pastSpecies = VegetableSpecies::model()->whereSeason($year - 1)->whereOnline(TRUE)->count();

		$this->assertTrue($past > 0 and $pastHours > 0 and $pastSpecies > 0, 'aucune ligne en ligne pour '.($year - 1).' : jouer la reprise aggregate/configure/vegetableAi');

		$counts = $this->invoke('write', $cStructure, FALSE, $year);
		$this->invoke('publish', $year);

		$this->assertTrue($counts['plan'] > 0, 'le groupe n\'a aucune série en '.$year);

		$this->assertEquals($past, VegetablePlan::model()->whereSeason($year - 1)->whereOnline(TRUE)->whereSeries(99999)->count(), 'les lignes '.($year - 1).' sont intactes');
		$this->assertEquals($pastHours, VegetableHours::model()->whereYear($year - 1)->whereOnline(TRUE)->whereHours(99999.0)->count(), 'les heures '.($year - 1).' sont intactes');
		$this->assertEquals($pastSpecies, VegetableSpecies::model()->whereSeason($year - 1)->whereOnline(TRUE)->whereCultivations(99999)->count(), 'les espèces '.($year - 1).' sont intactes');

		$this->assertEquals($counts['plan'], VegetablePlan::model()->whereSeason($year)->whereOnline(TRUE)->count(), 'les lignes '.$year.' sont celles du passage');
		$this->assertEquals($counts['species'], VegetableSpecies::model()->whereSeason($year)->whereOnline(TRUE)->count(), 'les espèces '.$year.' sont celles du passage');
		$this->assertEquals($counts['hours'], VegetableHours::model()->whereYear($year)->whereOnline(TRUE)->count(), 'les heures '.$year.' sont celles du passage');

		$this->assertEquals(0, VegetablePlan::model()->whereOnline(FALSE)->count(), 'rien ne reste en préparation');

		$periods = VegetablePlan::model()->select('season')->whereOnline(TRUE)->whereStructure('IN', $cStructure)->whereSeason('!=', $year)->getColumn('season');
		$this->assertTrue($periods !== [], 'les autres saisons du groupe sont toujours en ligne');

	}

}
?>
