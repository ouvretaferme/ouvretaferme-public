<?php
namespace series;

/**
 * Les règles de masquage du tableau de bord maraîchage d'un groupe.
 *
 * Le lot L6 n'ajoute aucune écriture : ce qu'il faut tenir, c'est qu'aucun chiffre affiché ne
 * puisse être ramené à une ferme. Cinq règles portent cette garantie, et chacune tombe d'une façon
 * qui ne se voit pas à l'œil — une tranche de deux fermes affichée, un total mensuel qui rend par
 * soustraction la case qu'on vient de vider, un regroupement de travail qui rend une ligne par
 * ferme. Ce fichier les éprouve une par une.
 *
 * Le groupe qu'il lit est celui que `VegetableDashboardFixtureAiTest` sème, calibré pour sa
 * population : il passe le plancher, et ses premières fermes, prises seules, ne le passent pas.
 *
 * L'écran lit les tables agrégées que la projection de nuit reconstruit : chaque méthode les
 * refait pour les fermes du décor dans sa transaction (`setUp()`), annulée en `tearDown()`.
 * Sans cela, ce fichier éprouverait l'état de la dernière projection jouée en dev, et rougirait
 * après chaque rechargement de la base. Les lectures indépendantes relisent, elles, les tables
 * vives : c'est ce qui prouve que l'agrégat rend les mêmes chiffres.
 *
 * Lancement : dev/test package=series file=VegetableDashboardAi
 */
class VegetableDashboardAiTest extends VegetableDashboardFixtureAiTest {

	public function setUp(): void {

		\aggregate\VegetablePlan::model()->beginTransaction();

		\aggregate\VegetableProjectionAiLib::projectStructures($this->affiliated());

	}

	public function tearDown(): void {
		\aggregate\VegetablePlan::model()->rollBack();
	}

	private function community(): \structure\Structure {

		return $this->eStructureCommunity;

	}

	/**
	 * La population du tableau de bord d'un réseau : les fermes rattachées en production.
	 *
	 * ⚠️ Les lectures indépendantes de ce fichier se font sur elle, et non sur tous les rattachements,
	 * pour comparer ce que l'écran compte à ce qu'il doit compter. Le filtre lui-même est éprouvé par
	 * `testNetworkCountsOnlyProductionFarms()` et `testEducationCountsEveryFarm()`.
	 */
	private function structures(): \Collection {

		return $this->affiliated()->find(fn(\structure\Structure $eStructure) => $eStructure['producerType'] === \structure\Structure::REAL);

	}

	private function affiliated(): \Collection {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereId(self::USER)
			->get();

		return \structure\AffiliationAiLib::getStructuresByCommunity($this->community(), $eUser);

	}

	/**
	 * Une tranche sous le plancher n'existe jamais dans le résultat.
	 *
	 * ⚠️ Masquer ne suffirait pas : le total des fermes redonnerait par soustraction ce que la
	 * tranche masquée contient. C'est pour cela que `applyFloor()` fusionne au lieu de cacher.
	 */
	public function testNoBinBelowFloor(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$bounds = [[0, 10], [10, 20], [20, 30], [30, NULL]];

		// 6 fermes sous 10, 2 entre 10 et 20, 7 entre 20 et 30, 5 au-delà.
		$values = [1, 2, 3, 4, 5, 6, 11, 12, 21, 22, 23, 24, 25, 26, 27, 31, 32, 33, 34, 35];

		$bins = VegetableDashboardAiLib::applyFloor(VegetableDashboardAiLib::getBins($values, $bounds));

		foreach($bins as $bin) {
			$this->assertTrue($bin['farms'] >= VegetableDashboardAiLib::FLOOR, 'tranche '.$bin['low'].'-'.($bin['high'] ?? '∞').' à '.$bin['farms'].' fermes, sous le plancher');
		}

		$this->assertEquals(count($values), array_sum(array_column($bins, 'farms')), 'les tranches gardent toute la population, sinon une ferme a disparu en route');
		$this->assertEquals(3, count($bins), 'la tranche à 2 fermes a fusionné avec une voisine, et une seule');

	}

	/**
	 * La fusion prend la plus petite des deux voisines.
	 *
	 * Prendre la plus grosse dissoudrait la tranche sous le plancher dans un bloc où elle ne se
	 * lit plus ; prendre systématiquement celle de gauche déplacerait la borne du mauvais côté.
	 */
	public function testMergeTakesTheSmallestNeighbour(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$bounds = [[0, 10], [10, 20], [20, NULL]];

		// 12 fermes sous 10, 2 entre 10 et 20, 6 au-delà : la voisine de droite est la plus petite.
		$values = array_merge(
			array_fill(0, 12, 5),
			array_fill(0, 2, 15),
			array_fill(0, 6, 25)
		);

		$bins = VegetableDashboardAiLib::applyFloor(VegetableDashboardAiLib::getBins($values, $bounds));

		$this->assertEquals(2, count($bins), 'deux tranches restent');
		$this->assertEquals(12, $bins[0]['farms'], 'la tranche de 12 n\'a pas bougé');
		$this->assertEquals(8, $bins[1]['farms'], 'la tranche de 2 a fusionné avec celle de 6, la plus petite de ses voisines');
		$this->assertEquals(10, $bins[1]['low'], 'la borne basse est celle de la tranche de gauche des deux fusionnées');
		$this->assertEquals(NULL, $bins[1]['high'], 'la borne haute est celle de la tranche de droite');

	}

	/**
	 * Une seule tranche sous le plancher fait disparaître la répartition entière.
	 *
	 * Elle n'a personne avec qui fusionner : affichée seule, elle dirait la valeur des quelques
	 * fermes qui la portent.
	 */
	public function testLonelyBinBelowFloorDisappears(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$bins = VegetableDashboardAiLib::applyFloor(
			VegetableDashboardAiLib::getBins(range(1, VegetableDashboardAiLib::FLOOR - 1), [[0, 10], [10, NULL]])
		);

		$this->assertEquals([], $bins, 'une population sous le plancher ne fait aucune répartition');

	}

	/**
	 * Chaque valeur tombe dans une tranche et une seule.
	 *
	 * ⚠️ Deux tranches se recouvrent volontairement dans le produit : `[0, 0]` isole les fermes à
	 * zéro et `[0, 25]` ouvre la suivante à la même borne basse. Une affectation par « toutes les
	 * tranches qui correspondent » comptait alors deux fois les fermes à zéro, et la somme des
	 * tranches dépassait la population — mesuré à 77 pour 50 fermes.
	 */
	public function testOverlappingBinsCountOnce(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$bounds = [[0, 0], [0, 25], [25, NULL]];
		$values = [0, 0, 0, 10, 20, 30, 40];

		$bins = VegetableDashboardAiLib::getBins($values, $bounds);

		$this->assertEquals(count($values), array_sum(array_column($bins, 'farms')), 'chaque ferme compte une fois');
		$this->assertEquals(3, $bins[0]['farms'], 'les trois fermes à zéro sont dans la tranche qui les isole');
		$this->assertEquals(2, $bins[1]['farms'], 'et pas dans la suivante, qui part pourtant de zéro');

	}

	/** La médiane se pose sur la tranche qui la contient, et sur une seule. */
	public function testMedianMarksOneBin(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$values = [1, 2, 3, 4, 5, 26, 27, 28, 29, 30];

		$median = VegetableDashboardAiLib::getMedian($values);

		$bins = VegetableDashboardAiLib::getBins($values, [[0, 10], [10, 20], [20, NULL]], $median);

		$this->assertEquals(15.5, $median, 'la médiane d\'un effectif pair est la moyenne des deux valeurs centrales');
		$this->assertEquals(1, count(array_filter($bins, fn($bin) => $bin['median'])), 'une seule tranche porte la médiane');

	}

	/**
	 * La matrice de récolte ne porte aucun total par mois, et ses cases masquées sont vides.
	 *
	 * ⚠️ Une case sous le plancher rend NULL et jamais 0 : zéro est une mesure, le vide dit qu'il
	 * n'y a rien à dire. Et aucun total mensuel n'est calculé — il redonnerait par soustraction ce
	 * que le plancher vient de masquer.
	 */
	public function testCalendarHidesCellsBelowFloor(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$species = VegetableDashboardAiLib::getSpecies($this->structures(), 2026);

		$this->assertTrue($species['aboveFloor'] > 0, 'le groupe a des espèces au-dessus du plancher, sinon le contrôle ne prouve rien');

		$masked = 0;

		foreach($species['list'] as $entry) {

			$this->assertTrue($entry['farms'] >= VegetableDashboardAiLib::FLOOR, $entry['ePlant']['name'].' est listée avec '.$entry['farms'].' fermes');
			$this->assertEquals(12, count($entry['months']), 'douze mois, pas un de plus');

			foreach($entry['months'] as $month => $farms) {

				if($farms === NULL) {
					$masked++;
					continue;
				}

				$this->assertTrue($farms >= VegetableDashboardAiLib::FLOOR, $entry['ePlant']['name'].', mois '.$month.' : '.$farms.' fermes affichées');

			}

		}

		echo "\n  Décor, saison 2026 : ".$species['count']." espèces, ".$species['aboveFloor']." au-dessus du plancher, ".$masked." cases masquées\n";

		$this->assertTrue($masked > 0, 'des cases sont masquées, sinon le plancher n\'a rien eu à faire et le contrôle ne prouve rien');

	}

	/**
	 * Le travail se regroupe par `fqn` de catégorie et jamais par identifiant.
	 *
	 * ⚠️ `work\Category` se duplique ferme par ferme : un regroupement par identifiant rend une
	 * ligne par ferme et par catégorie — 60 groupes sur les 64 fermes du décor —, ce qui est
	 * exactement ce que l'écran s'interdit. Le résultat porte donc exactement les natures que
	 * `getCategories()` nomme, dans son ordre, plus le reste.
	 */
	public function testWorkGroupsByFqn(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();
		$categories = VegetableDashboardAiLib::get($this->community(), $cStructure, 2026)['hoursByCategory'];

		$this->assertTrue($categories !== [], 'le groupe a du temps saisi, sinon le contrôle ne prouve rien');
		$this->assertEquals([CATEGORIE_CULTURE, CATEGORIE_PRODUCTION, CATEGORIE_COMMERCIALISATION, CATEGORIE_ADMINISTRATION], array_column($categories['natures'], 'fqn'), 'une nature par catégorie de départ, jamais une par ferme');

		$lines = [];

		foreach($categories['natures'] as $nature) {

			if($nature['state'] === VegetableDashboardAiLib::NATURE_SHOWN) {
				$this->assertTrue($nature['farms'] >= VegetableDashboardAiLib::FLOOR, 'la nature '.$nature['fqn'].' est affichée sur '.$nature['farms'].' fermes');
				$lines[] = $nature['fqn'].' '.round($nature['hours']).' h sur '.$nature['farms'].' fermes';
			} else {
				$lines[] = $nature['fqn'].' '.$nature['state'];
			}

		}

		if($categories['others'] !== NULL) {
			$this->assertTrue($categories['others']['farms'] >= VegetableDashboardAiLib::FLOOR, 'le reste est affiché sur '.$categories['others']['farms'].' fermes');
			$lines[] = 'autres '.round($categories['others']['hours']).' h sur '.$categories['others']['farms'].' fermes';
		}

		echo "  Décor, saison 2026 : ".implode(', ', $lines)."\n";

	}

	/**
	 * La carte des natures totalise les heures du chiffre de tête, saison après saison.
	 *
	 * ⚠️ C'est ce qui empêche de retrouver une nature masquée par soustraction : un écart entre les
	 * deux sommes serait exactement la valeur que le plancher a retirée. Le chiffre de tête n'est
	 * masqué par rien, il porte toutes les heures de l'année civile.
	 *
	 * ⚠️ **Ce contrôle a trouvé un défaut le 17/09/2026**, quand la carte était encore bornée aux
	 * mois que le graphique mensuel montrait : en 2025 la tête disait 1 446 h et les natures 355,
	 * et la différence rendait les heures des dix mois que le plancher mensuel avait masqués. Le
	 * bornage par mois est tombé avec ce graphique, et les deux sommes se rejoignent.
	 */
	public function testWorkTotalsMatch(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$checked = 0;

		foreach([2024, 2025, 2026] as $season) {

			$dashboard = VegetableDashboardAiLib::get($this->community(), $cStructure, $season);

			if($dashboard['hoursByCategory'] === []) {
				continue;
			}

			$figure = $dashboard['plan']['hours'];
			$categories = $this->sumCategories($dashboard['hoursByCategory']);

			$checked++;

			$this->assertEquals(round($figure, 2), round($categories, 2), 'saison '.$season.' : '.round($figure, 2).' h en tête, '.round($categories, 2).' h dans les natures');

		}

		$this->assertTrue($checked > 0, 'au moins une saison montre la carte des natures, sinon le contrôle ne prouve rien');

	}

	/**
	 * Une nature sous le plancher rejoint le reste, un reste sous le plancher reçoit la nature
	 * affichée la moins peuplée, et un reste qui ne peut plus rien recevoir tait la carte.
	 *
	 * ⚠️ Sans ces règles, la somme des mois visibles moins les natures affichées rendrait les
	 * heures d'une nature ou d'un reste saisis par deux fermes. Les lignes sont écrites à la main
	 * pour tenir les trois cas.
	 */
	public function testCategoryBelowFloorJoinsTheRest(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$rows = [];

		$this->assertEquals(3, VegetableDashboardAiLib::FLOOR, 'les lignes ci-dessous sont écrites pour un plancher à trois');

		foreach([1, 2, 3, 4] as $structure) {
			$rows[] = ['structure' => $structure, 'fqn' => CATEGORIE_CULTURE, 'hours' => 10.0];
		}

		// Deux fermes sur l'outil de production, une troisième sur une catégorie sans `fqn`.
		$rows[] = ['structure' => 1, 'fqn' => CATEGORIE_PRODUCTION, 'hours' => 7.0];
		$rows[] = ['structure' => 2, 'fqn' => CATEGORIE_PRODUCTION, 'hours' => 7.0];
		$rows[] = ['structure' => 3, 'fqn' => NULL, 'hours' => 1.0];

		$categories = VegetableDashboardAiLib::applyCategoryFloor($rows);

		$this->assertEquals(VegetableDashboardAiLib::NATURE_MERGED, $categories['natures'][1]['state'], 'la production, à deux fermes, n\'apparaît plus sous son nom');
		$this->assertEquals(NULL, $categories['natures'][1]['hours'], 'une nature versée ne porte plus ses heures');
		$this->assertEquals([CATEGORIE_PRODUCTION], $categories['others']['merged'], 'le reste sait qu\'il contient la production');
		$this->assertEquals(3, $categories['others']['farms'], 'le reste compte trois fermes distinctes une fois la production versée');
		$this->assertEquals(array_sum(array_column($rows, 'hours')), $this->sumCategories($categories), 'aucune heure ne se perd, sinon la différence avec les mois la rendrait');

		// Sans la ferme sur une catégorie sans `fqn`, le reste tombe aux deux fermes de la
		// production : la culture, seule nature encore affichée, le rejoint.
		unset($rows[6]);

		$categories = VegetableDashboardAiLib::applyCategoryFloor($rows);

		$this->assertEquals([CATEGORIE_PRODUCTION, CATEGORIE_CULTURE], $categories['others']['merged'], 'un reste sous le plancher reçoit la nature affichée la moins peuplée');
		$this->assertEquals(4, $categories['others']['farms'], 'le reste passe le plancher avec la culture');
		$this->assertEquals(array_sum(array_column($rows, 'hours')), $this->sumCategories($categories), 'aucune heure ne se perd');

		// Un reste de deux fermes sans aucune nature à recevoir tait la carte.
		$rows = [
			['structure' => 1, 'fqn' => NULL, 'hours' => 1.0],
			['structure' => 2, 'fqn' => CATEGORIE_PRODUCTION, 'hours' => 1.0],
			['structure' => 2, 'fqn' => CATEGORIE_CULTURE, 'hours' => 1.0]
		];

		$this->assertEquals([], VegetableDashboardAiLib::applyCategoryFloor($rows), 'un reste sous le plancher sans nature à recevoir tait toute la carte');

		// Aucune ligne jointe : rien à montrer, et l'UI n'a pas d'échelle à calculer sur une liste vide.
		$this->assertEquals([], VegetableDashboardAiLib::applyCategoryFloor([]), 'une saison sans heure jointe tait la carte');

	}

	/**
	 * Les quatre natures sortent chacune avec son état, et l'écran n'écrit jamais 0 h.
	 *
	 * ⚠️ Une nature que personne n'a saisie n'est pas une nature à zéro : la ferme qui a supprimé
	 * « Commercialisation » vend quand même, et range ce temps ailleurs. Les lignes sont écrites à la
	 * main pour tenir les trois états à la fois, affichée, versée et absente, ce que la base de dev
	 * ne donne pas sur une même saison.
	 */
	public function testFourNaturesKeepTheirState(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$rows = [];

		foreach([1, 2, 3, 4, 5, 6] as $structure) {
			$rows[] = ['structure' => $structure, 'fqn' => CATEGORIE_CULTURE, 'hours' => 10.0];
			$rows[] = ['structure' => $structure, 'fqn' => CATEGORIE_ADMINISTRATION, 'hours' => 2.0];
		}

		// Trois fermes sur la commercialisation, sous le plancher ; personne sur l'outil de production.
		foreach([1, 2, 3] as $structure) {
			$rows[] = ['structure' => $structure, 'fqn' => CATEGORIE_COMMERCIALISATION, 'hours' => 4.0];
		}

		// Deux fermes sur leurs propres catégories : avec la commercialisation, le reste en réunit cinq.
		$rows[] = ['structure' => 4, 'fqn' => NULL, 'hours' => 1.0];
		$rows[] = ['structure' => 5, 'fqn' => NULL, 'hours' => 1.0];

		$categories = VegetableDashboardAiLib::applyCategoryFloor($rows);

		$this->assertEquals(
			[
				CATEGORIE_CULTURE => VegetableDashboardAiLib::NATURE_SHOWN,
				CATEGORIE_PRODUCTION => VegetableDashboardAiLib::NATURE_ABSENT,
				CATEGORIE_COMMERCIALISATION => VegetableDashboardAiLib::NATURE_MERGED,
				CATEGORIE_ADMINISTRATION => VegetableDashboardAiLib::NATURE_SHOWN
			],
			array_column($categories['natures'], 'state', 'fqn'),
			'chaque nature connue sort avec son état, dans l\'ordre de lecture'
		);

		$this->assertEquals(12.0, $categories['natures'][3]['hours'], 'l\'administration garde ses heures');
		$this->assertEquals(['farms' => 5, 'merged' => [CATEGORIE_COMMERCIALISATION], 'own' => TRUE], array_intersect_key($categories['others'], ['farms' => TRUE, 'merged' => TRUE, 'own' => TRUE]), 'le reste dit ce qu\'il contient');
		$this->assertEquals(6, $categories['farms'], 'la population de la carte est le plus grand nombre de fermes d\'une ligne, reste compris');
		$this->assertEquals(array_sum(array_column($rows, 'hours')), $this->sumCategories($categories), 'aucune heure ne se perd');

		$dashboard = [
			'farms' => 6,
			'season' => 2026,
			'hoursByCategory' => $categories
		];

		$html = new \ReflectionMethod(VegetableDashboardAiUi::class, 'getHoursByCategory')->invoke(new VegetableDashboardAiUi(), $dashboard);

		$this->assertTrue(str_contains($html, encode('UtilChart.createPie(this, [')), 'l\'anneau est celui du produit');
		$this->assertEquals(0, preg_match('/(^|[^0-9 ])0 h/u', strip_tags($html)), 'aucune ligne n\'écrit 0 h');

		$dashboardWithoutOwn = $dashboard;
		$dashboardWithoutOwn['hoursByCategory']['others']['own'] = FALSE;

		$this->assertTrue($html !== new \ReflectionMethod(VegetableDashboardAiUi::class, 'getHoursByCategory')->invoke(new VegetableDashboardAiUi(), $dashboardWithoutOwn), 'la phrase de « Autres » ne dit pas si le reste contient les catégories des fermes');

		// Sans catégorie propre, le reste ne réunit que des natures versées : deux fermes passent sur
		// l'outil de production.
		array_splice($rows, -2, 2, [
			['structure' => 4, 'fqn' => CATEGORIE_PRODUCTION, 'hours' => 1.0],
			['structure' => 5, 'fqn' => CATEGORIE_PRODUCTION, 'hours' => 1.0]
		]);

		$categories = VegetableDashboardAiLib::applyCategoryFloor($rows);

		$this->assertEquals([CATEGORIE_PRODUCTION, CATEGORIE_COMMERCIALISATION], $categories['others']['merged'], 'le reste réunit les deux natures versées');
		$this->assertFalse($categories['others']['own'], 'le reste ne contient plus de catégorie propre');

	}

	/**
	 * Les heures de la carte des natures : les natures affichées et le reste, qui porte les natures versées.
	 */
	private function sumCategories(array $categories): float {

		$hours = array_sum(array_map(fn(array $nature) => $nature['hours'] ?? 0.0, $categories['natures']));

		return $hours + ($categories['others']['hours'] ?? 0.0);

	}

	/**
	 * Les fermes qui ont au moins `SERIES_MINIMUM` séries, comptées sans le Lib.
	 *
	 * Le compte indépendant relit `Series` : c'est ce qui distingue ce contrôle d'une relecture du
	 * code. Il rend aussi, par saison, le nombre de fermes au-dessus du seuil.
	 */
	private function countAboveMinimum(\Collection $cStructure): array {

		$cSeries = Series::model()
			->select([
				'season',
				'structure' => new \Sql('m1.structure', 'int'),
				'series' => new \Sql('COUNT(*)', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->group(new \Sql('m1.season, m1.structure'))
			->getCollection();

		$structures = [];

		foreach($cSeries as $eSeries) {

			if($eSeries['series'] >= VegetableDashboardAiLib::SERIES_MINIMUM) {
				$structures[$eSeries['season']][] = $eSeries['structure'];
			}

		}

		return $structures;

	}

	/**
	 * Les chiffres du plan de culture ne comptent que les fermes au seuil de séries, les heures
	 * toutes les fermes.
	 *
	 * ⚠️ Les deux sens se vérifient : un seuil oublié rend 50 fermes au lieu de 43, un seuil appliqué
	 * aussi aux heures en perd. La première espèce se compare à un compte indépendant, sa valeur
	 * n'étant plus bornée par le nombre de fermes du plan (43 sur toutes, 42 au seuil en 2026).
	 */
	public function testPlanCountsFarmsAboveSeriesMinimum(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$season = 2026;

		$cStructure = $this->structures();
		$aboveMinimum = $this->countAboveMinimum($cStructure)[$season] ?? [];

		$cCultivation = Cultivation::model()
			->select([
				'structure' => new \Sql('m1.structure', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', $season)
			->group(new \Sql('m1.structure'))
			->getCollection();

		$planned = array_column($cCultivation->getArrayCopy(), 'structure');

		// Le temps d'une intervention sans catégorie n'est pas projeté : la carte des natures ne
		// pourrait pas le montrer, et le chiffre de tête doit la totaliser exactement.
		$timesheet = Timesheet::model()
			->join(Task::model(), 'm1.task = m2.id')
			->join(\work\Category::model(), 'm2.category = m3.id')
			->select([
				'hours' => new \Sql('SUM(m1.time)', 'float'),
				'farms' => new \Sql('COUNT(DISTINCT m1.structure)', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.date', 'BETWEEN', new \Sql(Timesheet::model()->format($season.'-01-01').' AND '.Timesheet::model()->format($season.'-12-31')))
			->where('m1.time > 0')
			->get();

		$dashboard = VegetableDashboardAiLib::get($this->community(), $cStructure, $season);

		echo "  Décor, ".$season." : ".count($planned)." fermes avec une culture, ".count($aboveMinimum)." au seuil, ".$dashboard['plan']['placedFarms']." placées, ".$dashboard['plan']['hoursFarms']." avec du temps\n";

		$this->assertTrue(count($aboveMinimum) < count($planned), 'le seuil écarte des fermes qui ont une culture, sinon le contrôle ne distingue rien');
		$this->assertEquals(count(array_intersect($aboveMinimum, $planned)), $dashboard['plan']['farms'], 'le plan de culture se compte sur les fermes au seuil');
		$this->assertTrue($dashboard['plan']['placedFarms'] <= $dashboard['plan']['farms'], 'les surfaces aussi : '.$dashboard['plan']['placedFarms'].' fermes placées pour '.$dashboard['plan']['farms'].' au seuil');
		$this->assertEquals($timesheet['farms'], $dashboard['plan']['hoursFarms'], 'les heures se comptent sur toutes les fermes');
		$this->assertEquals(round($timesheet['hours'], 2), round($dashboard['plan']['hours'], 2), 'et leur somme aussi');

		$first = $dashboard['species']['list'][0];

		$farms = Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'farms' => new \Sql('COUNT(DISTINCT m1.structure)', 'int')
			])
			->where('m1.structure', 'IN', $aboveMinimum)
			->where('m1.season', $season)
			->where('m2.reference', $first['ePlant']['id'])
			->get()['farms'];

		$this->assertEquals($farms, $first['farms'], 'la première espèce, '.$first['ePlant']['name'].', se compte sur les fermes au seuil');

	}

	/**
	 * Une saison se propose si `FLOOR` fermes au moins y atteignent le seuil de séries, et seulement
	 * alors. Jamais une saison à venir, qui n'est pas calculée (19/09/2026).
	 */
	public function testSeasonsNeedFarmsAboveSeriesMinimum(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$aboveMinimum = array_filter($this->countAboveMinimum($cStructure), fn(int $season) => $season <= (int)date('Y'), ARRAY_FILTER_USE_KEY);

		$expected = array_keys(array_filter(
			$aboveMinimum,
			fn(array $structures) => count($structures) >= VegetableDashboardAiLib::FLOOR
		));

		rsort($expected);

		$seasons = VegetableDashboardAiLib::getSeasons($this->community(), $cStructure);

		echo "  Décor : saisons ".implode(', ', $seasons)."\n";

		$this->assertEquals($expected, $seasons, 'les saisons sont celles où '.VegetableDashboardAiLib::FLOOR.' fermes ont '.VegetableDashboardAiLib::SERIES_MINIMUM.' séries');
		$this->assertTrue(count($aboveMinimum) > count($seasons), 'une saison au moins tombe sous le plancher, sinon le contrôle ne distingue rien');

	}

	/**
	 * La population du tableau de bord est faite des fermes rattachées, partageuses ou non.
	 *
	 * ⚠️ C'est ce qui distingue cet écran de la comparaison, qui se borne aux partageuses. Le test
	 * retire le partage à toutes les fermes, dans une transaction annulée : borner comme la
	 * comparaison rendrait alors un écran entièrement vide. Le décor ne pose aucun partage, le test ne s'y fie donc pas.
	 */
	public function testPopulationIsEveryAffiliatedFarm(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		\structure\Affiliation::model()->beginTransaction();

		try {
			$this->checkPopulationIsEveryAffiliatedFarm();
		} finally {
			\structure\Affiliation::model()->rollBack();
		}

	}

	private function checkPopulationIsEveryAffiliatedFarm(): void {

		$eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($this->community()['id'])
			->get();

		$cStructure = $this->structures();

		foreach($cStructure as $eStructureProducer) {
			\structure\AffiliationAiLib::setRights($eStructureProducer, $eStructure, ['shareProduction' => []]);
		}

		// ⚠️ Le partage se lit sur la colonne et non par `findSharing()` : celui-ci pèse
		// le rôle du **lecteur connecté**, qu'un test en ligne de commande n'a pas. Ce qu'il faut
		// prouver ici ne dépend d'aucun lecteur : la ferme n'a accordé la lecture à personne.
		$sharing = \structure\Affiliation::model()
			->whereStructureCommunity($eStructure)
			->whereStructureProducer('IN', $cStructure)
			->where('shareProduction != 0')
			->count();

		$dashboard = VegetableDashboardAiLib::get($this->community(), $cStructure, 2026);

		echo "  Décor : ".$cStructure->count()." fermes rattachées, ".$sharing." partagent leur plan, ".$dashboard['plan']['farms']." en ont un\n";

		$this->assertEquals($cStructure->count(), $dashboard['farms'], 'le tableau de bord se dit sur les fermes rattachées en production');
		$this->assertEquals(0, $sharing, 'aucune ferme ne partage plus, sinon le contrôle ne distingue rien');
		$this->assertTrue($dashboard['plan']['farms'] > $sharing, 'et il lit des plans de culture que la comparaison, bornée aux partageuses, ne verrait pas');

	}

	/**
	 * Dans un réseau, une ferme qui n'est pas en production ne compte pas.
	 *
	 * le 19/09/2026 : « Fermes de production uniquement pour les réseaux ». La ferme est passée
	 * à l'essai en mémoire, sans écriture : le Lib lit le type sur la collection qu'il reçoit, et la
	 * projection du `setUp()` a écrit ses lignes, donc seul le filtre peut l'écarter.
	 */
	public function testNetworkCountsOnlyProductionFarms(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		[$eStructureMoved, $cStructure] = $this->withOneFarmOnTrial(2026);

		$before = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);
		$after = VegetableDashboardAiLib::get($this->community(), $cStructure, 2026);

		echo "  Décor : ".$eStructureMoved['id']." passée à l'essai, ".$before['plan']['farms']." → ".$after['plan']['farms']." fermes au plan\n";

		$this->assertEquals($before['farms'] - 1, $after['farms'], 'la ferme à l\'essai sort de la population du réseau');
		$this->assertEquals($before['plan']['farms'] - 1, $after['plan']['farms'], 'et de celles qui ont un plan de culture');

		$affiliated = $this->affiliated();

		$this->assertTrue($affiliated->count() > $this->structures()->count(), 'le décor porte une ferme hors production, que le réseau écarte déjà');
		$this->assertEquals($this->structures()->count(), VegetableDashboardAiLib::get($this->community(), $affiliated, 2026)['farms'], 'donc tous les rattachements rendent la même population que les seules fermes en production');

	}

	/**
	 * Dans une formation, toutes les fermes rattachées comptent.
	 *
	 * le 19/09/2026 : « Toutes fermes pour les formations ». Les fermes d'apprenants y sont la
	 * population même. Même collection que le cas du réseau, groupe passé en formation en mémoire.
	 */
	public function testEducationCountsEveryFarm(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		[, $cStructure] = $this->withOneFarmOnTrial(2026);

		$eStructureEducation = $this->community();
		$eStructureEducation['communityType'] = \structure\Structure::EDUCATION;

		$dashboard = VegetableDashboardAiLib::get($eStructureEducation, $cStructure, 2026);
		$network = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$this->assertEquals($cStructure->count(), $dashboard['farms'], 'la formation compte la ferme à l\'essai');
		$this->assertEquals($network['plan']['farms'], $dashboard['plan']['farms'], 'y compris dans le plan de culture');

	}

	/** Les fermes en production du décor, dont une qui a un plan de culture en `$season` passe à l'essai. */
	private function withOneFarmOnTrial(int $season): array {

		$cStructure = $this->structures();
		$aboveMinimum = $this->countAboveMinimum($cStructure)[$season] ?? [];

		if($aboveMinimum === []) {
			$this->assertTrue(FALSE, 'le décor porte en '.$season.' une ferme au-dessus de '.VegetableDashboardAiLib::SERIES_MINIMUM.' séries, sinon le contrôle ne distingue rien');
			return [new \structure\Structure(), $cStructure];
		}

		$eStructureMoved = $cStructure[$aboveMinimum[0]];
		$eStructureMoved['producerType'] = \structure\Structure::TEST;

		return [$eStructureMoved, $cStructure];

	}

	/**
	 * Une population sous le plancher bascule sur l'écran vide.
	 *
	 * ⚠️ La bascule ne se déduit d'aucun bloc en particulier : un groupe peut n'avoir aucun plan
	 * de culture et cinq ateliers déclarés, et il a alors quelque chose à montrer.
	 *
	 * La population est prise dans le décor, une ferme de moins que le plancher, et non dans un petit
	 * groupe de la base de dev : un groupe de recette se remplit, et ces fermes ont des plans de culture, donc elles tombent sous le plancher par leur nombre
	 * et non faute de données.
	 */
	public function testFewFarmsFallBelowFloor(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructureLarge = $this->structures();

		// `slice()` lève au-delà de la collection : le cas se nomme avant, et le contrôle s'arrête là.
		if($cStructureLarge->count() < VegetableDashboardAiLib::FLOOR - 1) {
			$this->assertFail('le décor n\'a que '.$cStructureLarge->count().' fermes, il en faut '.(VegetableDashboardAiLib::FLOOR - 1));
			return;
		}

		// Triées par id : la lecture des rattachements ne garantit aucun ordre, et le choix doit
		// rester le même d'un lancement à l'autre.
		$cStructure = $cStructureLarge
			->sort('id')
			->slice(0, VegetableDashboardAiLib::FLOOR - 1, TRUE);

		$dashboard = VegetableDashboardAiLib::get($this->community(), $cStructure, 2026);

		echo "  Décor, ".$cStructure->count()." premières fermes : ".$dashboard['plan']['farms']." avec un plan de culture, ".$dashboard['declared']['workshopFarms']." ateliers maraîchage\n";

		$this->assertTrue($dashboard['plan']['farms'] > 0, 'ces fermes ont des plans de culture, sinon le contrôle ne distingue pas le nombre de l\'absence de données');
		$this->assertFalse(VegetableDashboardAiLib::hasAboveFloor($dashboard), 'aucune valeur n\'atteint le plancher');
		$this->assertEquals([], VegetableDashboardAiLib::getSeasons($this->community(), $cStructure), 'et aucune saison ne se propose, elles ouvriraient toutes sur un écran masqué');

	}

	/**
	 * Le tableau de bord du décor, lui, s'affiche.
	 *
	 * Pendant du contrôle précédent : sans lui, une bascule toujours vraie passerait au vert.
	 */
	public function testLargeCommunityIsAboveFloor(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$this->assertTrue(VegetableDashboardAiLib::hasAboveFloor($dashboard), 'le tableau de bord du décor s\'affiche');

	}

	/**
	 * La SAU se lit sur la fiche système, jamais sur la surface de l'atelier maraîchage (L12, Q1-C).
	 *
	 * ⚠️ Les deux comptent cinq fermes sur le décor, par coïncidence, et c'est ce qui a fait passer
	 * `Production.area` pour la SAU sur la première planche. Seule la médiane les distingue : le
	 * contrôle la recalcule depuis `System.totalArea` et exige qu'elle diffère de l'autre.
	 */
	public function testTotalAreaIsSystemSau(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$cStructure = $this->structures();

		$declared = VegetableDashboardAiLib::getDeclared($cStructure);

		$totalAreas = [];

		foreach(\structure\SystemLib::getByStructures($cStructure) as $eSystem) {

			if($eSystem['totalArea'] !== NULL) {
				$totalAreas[] = (float)$eSystem['totalArea'];
			}

		}

		echo "  Décor : SAU renseignée par ".count($totalAreas)." fermes, médiane ".$declared['totalAreaMedian']." ha ; surface maraîchère médiane ".$declared['areaMedian']." ha\n";

		$this->assertTrue(count($totalAreas) >= VegetableDashboardAiLib::FLOOR, 'au moins '.VegetableDashboardAiLib::FLOOR.' fermes ont renseigné leur SAU, sinon la médiane est masquée et le contrôle ne prouve rien');
		$this->assertEquals(count($totalAreas), $declared['totalAreaFarms']);
		$this->assertEquals(VegetableDashboardAiLib::getMedian($totalAreas), $declared['totalAreaMedian']);
		$this->assertTrue($declared['totalAreaMedian'] !== $declared['areaMedian'], 'la SAU et la surface de l\'atelier sont deux chiffres');

		$cStructureFew = $cStructure
			->sort('id')
			->slice(0, VegetableDashboardAiLib::FLOOR - 1, TRUE);

		$this->assertEquals(NULL, VegetableDashboardAiLib::getDeclared($cStructureFew)['totalAreaMedian'], 'sous le plancher, la médiane de la SAU se tait');

	}

	/**
	 * La diversité par mois ne compte que des espèces dont la case est déjà affichée (L12).
	 *
	 * ⚠️ Un nombre de fermes par mois redonnerait par soustraction une case que le plancher vient
	 * de vider : le décompte porte sur les espèces, et une case masquée n'y entre pas.
	 */
	public function testMonthsCountOnlyShownCells(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$species = VegetableDashboardAiLib::getSpecies($this->structures(), 2026);

		$this->assertEquals(range(1, 12), array_keys($species['byMonth']), 'douze mois');

		foreach($species['byMonth'] as $month => $count) {

			$shown = count(array_filter($species['list'], fn($entry) => $entry['months'][$month] !== NULL));

			$this->assertEquals($shown, $count, 'mois '.$month);

		}

		$this->assertTrue(max($species['byMonth']) > 0, 'des mois comptent des espèces, sinon le contrôle ne prouve rien');

	}

	/** Les ateliers nommés le sont au-dessus du plancher, les autres se comptent sans se nommer. */
	public function testWorkshopsBelowFloorAreCountedNotNamed(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$declared = VegetableDashboardAiLib::getDeclared($this->structures());

		foreach($declared['workshops'] as $fqn => $farms) {
			$this->assertTrue($farms >= VegetableDashboardAiLib::FLOOR, 'l\'atelier '.$fqn.' est nommé sur '.$farms.' fermes');
		}

		echo "  Décor : ".count($declared['workshops'])." ateliers nommés, ".$declared['workshopsBelowFloor']." comptés sans être nommés\n";

		$this->assertTrue($declared['workshopsBelowFloor'] > 0, 'des ateliers sont sous le plancher, sinon le contrôle ne prouve rien');

	}

	/**
	 * Chaque population de l'écran l'ouvre seule, et aucune ne l'ouvre sous le plancher.
	 *
	 * ⚠️ Les ateliers déclarés restent le témoin : c'est le cas vu sur 6251, où cinq ateliers et
	 * rien d'autre au plancher ouvrent le tableau de bord. La SAU s'y ajoute depuis que sa carte est
	 * entrée dans l'écran (L13) : un groupe qui ne passe le plancher que par elle tombait sur le bloc
	 * sous le plancher.
	 */
	public function testEveryPopulationOpensTheScreen(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$below = VegetableDashboardAiLib::FLOOR - 1;

		$dashboard = [
			'plan' => ['farms' => $below, 'placedFarms' => $below, 'hoursFarms' => $below],
			'bins' => [VegetableDashboardAiLib::BED_AREA => ['farms' => $below]],
			'declared' => ['workshopFarms' => $below, 'areaFarms' => $below, 'totalAreaFarms' => $below]
		];

		$this->assertFalse(VegetableDashboardAiLib::hasAboveFloor($dashboard), 'le témoin : aucune population au plancher, l\'écran reste fermé');

		$populations = [
			['plan', 'farms'],
			['plan', 'placedFarms'],
			['plan', 'hoursFarms'],
			['declared', 'workshopFarms'],
			['declared', 'areaFarms'],
			['declared', 'totalAreaFarms']
		];

		foreach($populations as [$block, $population]) {

			$dashboardOpen = $dashboard;
			$dashboardOpen[$block][$population] = VegetableDashboardAiLib::FLOOR;

			$this->assertTrue(VegetableDashboardAiLib::hasAboveFloor($dashboardOpen), $block.'.'.$population.' au plancher ouvre l\'écran');

		}

		$dashboardOpen = $dashboard;
		$dashboardOpen['bins'][VegetableDashboardAiLib::BED_AREA]['farms'] = VegetableDashboardAiLib::FLOOR;

		$this->assertTrue(VegetableDashboardAiLib::hasAboveFloor($dashboardOpen), 'les planches seules au plancher ouvrent l\'écran (L14)');

	}

	/**
	 * Un groupe qui ne passe le plancher que par la SAU voit sa carte (L13).
	 *
	 * ⚠️ Parti d'un tableau de bord réel dont on baisse les populations : `get()` lit le plan, les
	 * espèces, les tranches et le calendrier, et un tableau partiel lèverait avant de rien prouver.
	 */
	public function testTotalAreaAloneShowsItsCard(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$this->assertTrue($dashboard['declared']['totalAreaFarms'] >= VegetableDashboardAiLib::FLOOR, 'au moins '.VegetableDashboardAiLib::FLOOR.' fermes du décor ont renseigné leur SAU, sinon le cas ne se pose pas');

		$below = VegetableDashboardAiLib::FLOOR - 1;

		$dashboard['plan']['farms'] = $below;
		$dashboard['plan']['placedFarms'] = $below;
		$dashboard['plan']['hoursFarms'] = $below;
		$dashboard['declared']['workshopFarms'] = $below;
		$dashboard['declared']['areaFarms'] = $below;
		$dashboard['bins'][VegetableDashboardAiLib::BED_AREA]['farms'] = $below;

		$this->assertTrue(VegetableDashboardAiLib::hasAboveFloor($dashboard), 'la SAU seule ouvre l\'écran');

		$html = new VegetableDashboardAiUi()->get(new \structure\Structure(['id' => $this->community()['id'], 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]), $dashboard, FALSE, FALSE);

		$this->assertTrue(str_contains($html, new \ReflectionMethod(VegetableDashboardAiUi::class, 'getNetworkTotalArea')->invoke(new VegetableDashboardAiUi(), $dashboard)), 'et sa carte s\'affiche');

	}

	/**
	 * Sous le plancher, le bloc dit le constat en un titre et une phrase (planche
	 * production-finir-statistiques-vide, Q1 A), selon les fermes rattachées dans la portée et celles
	 * que le tableau de bord retient : chaque cas rend son propre bloc, le nom du sous-groupe y est
	 * échappé, et le groupe se dit avec le mot de son type.
	 */
	public function testBelowFloorSaysWhatIsMissing(): void {

		$eStructure = $this->getCommunityForUrl();
		$eStructureEducation = new \structure\Structure(['id' => 6388, 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::EDUCATION]);
		$eSection = new \structure\Section(['id' => 12, 'name' => 'BPREA <2027>']);

		$cases = [
			'sous-groupe sans ferme' => [0, 0, $eSection],
			'groupe sans ferme' => [0, 0, new \structure\Section()],
			'sous-groupe à une ferme' => [1, 1, $eSection],
			'sous-groupe à deux fermes' => [2, 2, $eSection],
			'groupe à deux fermes' => [2, 2, new \structure\Section()],
			'sous-groupe dont une ferme n\'est pas retenue' => [3, 2, $eSection],
			'sous-groupe dont une seule ferme est retenue' => [2, 1, $eSection],
			'sous-groupe dont la seule ferme n\'est pas retenue' => [1, 0, $eSection],
			'groupe dont deux fermes ne sont pas retenues' => [4, 2, new \structure\Section()],
			'sous-groupe au plancher, sans données' => [5, 4, $eSection],
			'groupe au plancher, sans données' => [3, 3, new \structure\Section()],
		];

		$htmls = [];

		foreach($cases as $case => [$structures, $farms, $eSectionCase]) {

			$html = new VegetableDashboardAiUi()->getBelowFloor($eStructure, ['farms' => $farms, 'lastDay' => NULL], $structures, FALSE, FALSE, $eSectionCase);

			if($eSectionCase->notEmpty()) {
				$this->assertTrue(str_contains($html, 'BPREA &lt;2027&gt;') and str_contains($html, '<2027>') === FALSE, $case.' : le nom du sous-groupe n\'est pas échappé');
			}

			$this->assertTrue($html !== new VegetableDashboardAiUi()->getBelowFloor($eStructureEducation, ['farms' => $farms, 'lastDay' => NULL], $structures, FALSE, FALSE, $eSectionCase), $case.' : un groupe de formation se dit comme un réseau');

			$htmls[] = $html;

		}

		$this->assertEquals(count($cases), count(array_unique($htmls)), 'deux cas rendent le même bloc');

		$this->assertTrue(
			new VegetableDashboardAiUi()->getBelowFloor($eStructure, ['farms' => 2, 'lastDay' => NULL], 4, FALSE, FALSE) !== new VegetableDashboardAiUi()->getBelowFloor($eStructure, ['farms' => 1, 'lastDay' => NULL], 4, FALSE, FALSE),
			'le nombre de fermes retenues ne change rien au bloc, le nombre rattaché est lu à sa place'
		);

	}

	/**
	 * Au plus un bouton, et il mène là où il y a quelque chose à lire : la comparaison quand une ferme
	 * l'ouvre, sinon les statistiques de tout le groupe quand elles s'affichent.
	 */
	public function testBelowFloorHasAtMostOneButton(): void {

		$eStructure = $this->getCommunityForUrl();
		$eSection = new \structure\Section(['id' => 12, 'name' => 'BPREA']);
		$dashboard = ['farms' => 0, 'lastDay' => NULL];

		$compare = 'href="'.\structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'"';
		$community = 'href="'.\structure\StructureUi::urlCommunityVegetable($eStructure).'"';

		$this->assertTrue(str_ends_with(\structure\StructureUi::urlCommunityCompare($eStructure, $eSection), '/comparer/12'), 'le témoin : la comparaison garde le sous-groupe');
		$this->assertTrue(str_ends_with(\structure\StructureUi::urlCommunityVegetable($eStructure), '/maraichage'), 'le témoin : les statistiques de tout le groupe le quittent');

		$cases = [
			'une ferme ouvre la comparaison' => [TRUE, FALSE, [$compare], [$community]],
			'la comparaison passe devant le groupe entier' => [TRUE, TRUE, [$compare], [$community]],
			'le groupe entier a des statistiques' => [FALSE, TRUE, [$community], [$compare]],
			'nulle part où aller' => [FALSE, FALSE, [], [$compare, $community]],
		];

		foreach($cases as $case => [$hasSharing, $hasFiguresCommunity, $expected, $refused]) {

			$html = new VegetableDashboardAiUi()->getBelowFloor($eStructure, $dashboard, 2, $hasSharing, $hasFiguresCommunity, $eSection);

			foreach($expected as $fragment) {
				$this->assertTrue(str_contains($html, $fragment), $case.' : '.$html);
			}

			foreach($refused as $fragment) {
				$this->assertFalse(str_contains($html, $fragment), $case.', sans « '.$fragment.' » : '.$html);
			}

		}

		// Le bloc ne montre aucun chiffre : le jour où s'arrêtent ceux des plans de culture ne le suit pas.
		$html = new VegetableDashboardAiUi()->getBelowFloor($eStructure, ['farms' => 0, 'lastDay' => '2026-02-28'], 2, FALSE, FALSE, $eSection);

		$this->assertFalse(str_contains($html, '28/02/2026'), 'le bloc sous le plancher porte la date des plans : '.$html);

	}

	/**
	 * L'onglet « Cultures » d'un sous-groupe sans ferme rattachée dit ce que disent les statistiques
	 * et les fermes du même sous-groupe. Partout ailleurs, il garde ses deux raisons.
	 */
	public function testNoCultivationInAnEmptySection(): void {

		$eStructure = $this->getCommunityForUrl();
		$eStructureEducation = new \structure\Structure(['id' => 6388, 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::EDUCATION]);
		$eSection = new \structure\Section(['id' => 12, 'name' => 'BPREA <2027>']);

		$empty = new CompareAiUi()->getNoCultivation($eStructure, FALSE, 0, $eSection);
		$silentSection = new CompareAiUi()->getNoCultivation($eStructure, FALSE, 2, $eSection);
		$bareSection = new CompareAiUi()->getNoCultivation($eStructure, TRUE, 2, $eSection);
		$silent = new CompareAiUi()->getNoCultivation($eStructure, FALSE, 2);
		$bare = new CompareAiUi()->getNoCultivation($eStructure, TRUE, 3);

		foreach([$empty, $silentSection, $bareSection] as $html) {
			$this->assertTrue(str_contains($html, 'BPREA &lt;2027&gt;') and str_contains($html, '<2027>') === FALSE, 'le nom du sous-groupe n\'est pas échappé : '.$html);
		}

		$this->assertEquals(5, count(array_unique([$empty, $silentSection, $bareSection, $silent, $bare])), 'le sous-groupe sans ferme, le sous-groupe et le groupe entier ne disent pas chacun leur raison');
		$this->assertEquals($silent, new CompareAiUi()->getNoCultivation($eStructure, FALSE, 0), 'le groupe entier garde ses deux raisons, même sans ferme');

		// Le groupe se dit avec le mot de son type, comme dans les blocs vides des statistiques.
		$this->assertTrue($silent !== new CompareAiUi()->getNoCultivation($eStructureEducation, FALSE, 2), 'un groupe de formation dont aucune ferme n\'accorde la lecture se dit comme un réseau');
		$this->assertTrue($bare !== new CompareAiUi()->getNoCultivation($eStructureEducation, TRUE, 2), 'un groupe de formation dont les fermes n\'ont pas de plan de culture se dit comme un réseau');

		// La sortie d'un sous-groupe : la comparaison de tout le groupe, sans le sous-groupe dans
		// l'adresse, sur les deux blocs.
		$button = 'href="'.\structure\StructureUi::urlCommunityCompare($eStructure).'"';

		$this->assertTrue(str_ends_with(\structure\StructureUi::urlCommunityCompare($eStructure), '/comparer'), 'le témoin : l\'adresse quitte le sous-groupe');
		$this->assertTrue(str_contains(new CompareAiUi()->getNoCultivation($eStructure, FALSE, 0, $eSection, TRUE), $button), 'le bouton sous « Aucune ferme »');
		$this->assertTrue(str_contains(new CompareAiUi()->getNoCultivation($eStructure, TRUE, 2, $eSection, TRUE), $button), 'le bouton sous « Rien à comparer pour l\'instant »');
		$this->assertTrue(str_contains(new CompareAiUi()->getNoCultivation($eStructureEducation, TRUE, 2, $eSection, TRUE), 'href="'.\structure\StructureUi::urlCommunityCompare($eStructureEducation).'"'), 'dans un groupe de formation, le bouton mène à la comparaison du groupe');
		$this->assertFalse(str_contains(new CompareAiUi()->getNoCultivation($eStructure, TRUE, 2, $eSection, FALSE), $button), 'sans comparaison de tout le groupe, aucun bouton');

	}

	private function getCommunityForUrl(): \structure\Structure {
		return new \structure\Structure(['id' => 6251, 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]);
	}

	/**
	 * Les tranches de planches ne comptent que les fermes au-delà de `BED_AREA_MINIMUM` (L14).
	 *
	 * ⚠️ La ferme à exactement 100 m² est le cas qui départage `>` de `>=` : la demande était « plus
	 * de 100 m² ». La moyenne et la médiane sont choisies différentes pour qu'une lecture à la place
	 * de l'autre se voie.
	 */
	public function testBedBinsKeepFarmsAboveMinimum(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$farms = [
			1 => ['area' => 50.0, 'greenhouse' => 50.0],
			2 => ['area' => (float)VegetableDashboardAiLib::BED_AREA_MINIMUM, 'greenhouse' => 0.0],
			3 => ['area' => 2000.0, 'greenhouse' => 0.0],
			4 => ['area' => 3000.0, 'greenhouse' => 300.0],
			5 => ['area' => 4000.0, 'greenhouse' => 1000.0],
			6 => ['area' => 19000.0, 'greenhouse' => 3800.0]
		];

		$bins = VegetableDashboardAiLib::getBedBins($farms);

		$area = $bins[VegetableDashboardAiLib::BED_AREA];
		$greenhouse = $bins[VegetableDashboardAiLib::BED_GREENHOUSE];

		$this->assertEquals(4, $area['farms'], 'les fermes à 50 et à 100 m² sont écartées, la ferme à 100 m² pile comprise');
		$this->assertEquals(4, $greenhouse['farms'], 'la part sous abri se lit sur la même population');
		$this->assertEquals(3500.0, $area['median'], 'médiane des quatre surfaces');
		$this->assertEquals(7000.0, $area['mean'], 'moyenne des quatre surfaces');
		$this->assertEquals(15.0, $greenhouse['median'], 'médiane des parts : 10 % et 20 % au milieu');
		$this->assertEquals(13.75, $greenhouse['mean'], 'moyenne des parts, et non part de la somme (5 100 / 28 000)');
		$this->assertEquals(VegetableDashboardAiLib::applyFloor(VegetableDashboardAiLib::getBins([2000.0, 3000.0, 4000.0, 19000.0], VegetableDashboardAiLib::BINS[VegetableDashboardAiLib::AREA], 3500.0)), $area['bins'], 'les bornes et le plancher de la surface développée');

		$empty = VegetableDashboardAiLib::getBedBins([]);

		$this->assertEquals(0, $empty[VegetableDashboardAiLib::BED_AREA]['farms'], 'aucune ferme');
		$this->assertEquals([], $empty[VegetableDashboardAiLib::BED_AREA]['bins'], 'aucune tranche, la carte se tait');
		$this->assertEquals(NULL, $empty[VegetableDashboardAiLib::BED_AREA]['mean'], 'et aucune moyenne');

	}

	/**
	 * Sur le décor, les planches se relisent sans le Lib, la parcelle bornée par la saison comme la planche.
	 *
	 * ⚠️ Le témoin prouve que la borne de la parcelle compte : sans elle, la moyenne diffère. Mesuré
	 * le 19/09/2026 : 64 planches actives, 7 761 m² sur 5 fermes, sous une parcelle hors saison.
	 */
	public function testBedAreaReadsSeasonalPlots(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$season = 2026;

		$cStructure = $this->structures();

		$read = function(bool $plotSeason) use($cStructure, $season): array {

			$cBed = \map\Bed::model()
				->join(\map\Plot::model(), 'm1.plot = m2.id')
				->select([
					'structure' => new \Sql('m1.structure', 'int'),
					'area' => new \Sql('SUM(m1.area)', 'float')
				])
				->where('m1.structure', 'IN', $cStructure)
				->where('m1.status', \map\Bed::ACTIVE)
				->where('m1.plotFill', FALSE)
				->where('m1.zoneFill', FALSE)
				->where('(m1.seasonFirst IS NULL OR m1.seasonFirst <= '.$season.') AND (m1.seasonLast IS NULL OR m1.seasonLast >= '.$season.')')
				->where('(m2.seasonFirst IS NULL OR m2.seasonFirst <= '.$season.') AND (m2.seasonLast IS NULL OR m2.seasonLast >= '.$season.')', if: $plotSeason)
				->group(new \Sql('m1.structure'))
				->getCollection();

			$areas = array_filter(array_column($cBed->getArrayCopy(), 'area'), fn(float $area) => $area > VegetableDashboardAiLib::BED_AREA_MINIMUM);

			return $areas;

		};

		$areas = $read(TRUE);
		$areasWithoutPlotSeason = $read(FALSE);

		// La moyenne se calcule sur une population non vide : le cas se nomme avant, et le contrôle s'arrête là.
		if(count($areas) < VegetableDashboardAiLib::FLOOR) {
			$this->assertFail('seules '.count($areas).' fermes du décor ont plus de '.VegetableDashboardAiLib::BED_AREA_MINIMUM.' m² de planches en '.$season.', il en faut '.VegetableDashboardAiLib::FLOOR.' pour que le contrôle lise quelque chose');
			return;
		}

		$farms = count($areas);
		$mean = array_sum($areas) / $farms;
		$meanWithoutPlotSeason = array_sum($areasWithoutPlotSeason) / count($areasWithoutPlotSeason);

		$bins = VegetableDashboardAiLib::get($this->community(), $cStructure, $season)['bins'][VegetableDashboardAiLib::BED_AREA];

		echo "  Décor, ".$season." : ".$farms." fermes à plus de ".VegetableDashboardAiLib::BED_AREA_MINIMUM." m² de planches, moyenne ".round($mean)." m² (".round($meanWithoutPlotSeason)." sans la saison de la parcelle)\n";

		$this->assertTrue(round($mean) !== round($meanWithoutPlotSeason), 'le témoin : la saison de la parcelle change la moyenne, sinon le contrôle ne distingue rien. S\'il n\'y a plus en base aucune planche active sous une parcelle close, le témoin est à monter dans le test (une parcelle close et sa planche, dans la transaction)');
		$this->assertEquals($farms, $bins['farms'], 'la population des planches');
		$this->assertEquals(round($mean, 2), round($bins['mean'], 2), 'la moyenne, parcelle bornée par la saison');

	}

	/**
	 * La carte des espèces s'arrête à douze, le titre de la section des plans se tait avec ses
	 * cartes, et la carte de la SAU sans ferme renseignée.
	 */
	public function testSectionTitleGoesWithItsCards(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$eStructure = new \structure\Structure(['id' => $this->community()['id'], 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]);

		$htmlFull = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE);

		// Douze espèces au plus dans la carte : une treizième ne s'y ajoute pas.
		$this->assertTrue($dashboard['species']['list'] !== [], 'le décor n\'a aucune espèce, le cas ne prouve rien');

		$getSpecies = new \ReflectionMethod(VegetableDashboardAiUi::class, 'getSpecies');
		$dashboardSpecies = $dashboard;

		$dashboardSpecies['species']['list'] = array_fill(0, 12, $dashboard['species']['list'][0]);
		$twelve = $getSpecies->invoke(new VegetableDashboardAiUi(), $eStructure, $dashboardSpecies, FALSE, new \structure\Section());

		$dashboardSpecies['species']['list'] = array_fill(0, 13, $dashboard['species']['list'][0]);
		$thirteen = $getSpecies->invoke(new VegetableDashboardAiUi(), $eStructure, $dashboardSpecies, FALSE, new \structure\Section());

		$this->assertEquals($twelve, $thirteen, 'une treizième espèce entre dans la carte');

		// Toutes les cartes de la section se taisent : les rangées vides ne se rendent pas, et le
		// titre ne reste pas seul au-dessus du vide.
		$dashboard['plan']['placedFarms'] = 0;
		$dashboard['declared']['totalAreaFarms'] = 0;
		$dashboard['species']['list'] = [];
		$dashboard['species']['byMonth'] = array_fill(1, 12, 0);
		$dashboard['hoursByCategory'] = [];

		foreach(array_keys($dashboard['bins']) as $indicator) {
			$dashboard['bins'][$indicator]['bins'] = [];
		}

		$html = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE);

		$this->assertEquals(substr_count($htmlFull, '<h3>') - 1, substr_count($html, '<h3>'), 'sans carte, le titre de la section reste');
		$this->assertEquals('', new \ReflectionMethod(VegetableDashboardAiUi::class, 'getNetworkTotalArea')->invoke(new VegetableDashboardAiUi(), $dashboard), 'la SAU s\'affiche sans ferme renseignée');

	}

	/**
	 * Les titres disent le mot du groupe, jamais « réseau » écrit en dur (18/09/2026).
	 *
	 * ⚠️ Le même tableau rendu pour deux types : un réseau et un groupe de formation lisent les mêmes
	 * chiffres, seuls les mots changent. Rendu pour le seul réseau, une chaîne restée en dur
	 * passerait au vert.
	 */
	public function testTitlesSayTheGroupWord(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$htmls = [];

		foreach([\structure\Structure::NETWORK, \structure\Structure::EDUCATION] as $communityType) {

			$eStructure = new \structure\Structure([
				'id' => $this->community()['id'],
				'type' => \structure\Structure::COMMUNITY,
				'communityType' => $communityType
			]);

			$htmls[] = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE);

		}

		$this->assertTrue($htmls[0] !== $htmls[1], 'un réseau et un groupe de formation lisent les mêmes mots');

	}

	/**
	 * Le pied dit le dernier jour complet des chiffres tirés des plans : la veille du passage de
	 * 4 h, comme les statistiques de l'administration (planche 9, 19/09/2026).
	 *
	 * ⚠️ Les deux écrans le portent, le tableau de bord comme le bloc sous le plancher, qui cite lui
	 * aussi un nombre tiré des plans. Avant la première projection, la ligne se tait.
	 */
	public function testFooterSaysTheLastFullDay(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		// Des dates de calcul fixées, pour que l'attendu ne se recalcule pas par l'expression du Lib :
		// le passage du 1er mars couvre la veille qui change de mois. Écritures directes, dans la
		// transaction de setUp() : aucun Lib ne pose une date de calcul, c'est la projection qui la tient.
		\aggregate\VegetablePlan::model()
			->whereSeason(2026)
			->whereOnline(TRUE)
			->update(['createdAt' => '2026-03-01 04:00:12']);

		// 2025 calculée plus tôt, comme une saison passée que seule la reprise a écrite : sa date
		// ne doit pas remonter sur 2026.
		\aggregate\VegetablePlan::model()
			->whereSeason(2025)
			->whereOnline(TRUE)
			->update(['createdAt' => '2020-01-01 04:00:12']);

		$this->assertEquals('2019-12-31', VegetableDashboardAiLib::get($this->community(), $this->structures(), 2025)['lastDay'], 'la saison 2025 lit sa propre date');

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$this->assertEquals('2026-02-28', $dashboard['lastDay'], 'la veille du jour de calcul de 2026');

		$eStructure = new \structure\Structure(['id' => $this->community()['id'], 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]);

		$html = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE);
		$this->assertTrue(str_contains($html, '28/02/2026'), 'le tableau de bord ne dit pas la date de ses chiffres');

		$html = new VegetableDashboardAiUi()->getBelowFloor($eStructure, $dashboard, $dashboard['farms'], FALSE, FALSE);
		$this->assertFalse(str_contains($html, '28/02/2026'), 'le bloc sous le plancher ne montre aucun chiffre, donc pas leur date');

		\aggregate\VegetablePlanAiLib::deleteOnline(2026);

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);

		$this->assertEquals(NULL, $dashboard['lastDay'], 'aucune projection en ligne pour 2026, aucune date, même si 2025 en a une');
		$this->assertEquals('', new \ReflectionMethod(VegetableDashboardAiUi::class, 'getLastDay')->invoke(new VegetableDashboardAiUi(), $dashboard), 'le tableau de bord se tait');

	}

	/**
	 * Un groupe qui passe le plancher par ses seules fiches système : aucun plan de culture ne rend
	 * de chiffre, l'écran le dit en tête puis montre le déclaré en tuiles, SAU comprise (planche
	 * production-finir-statistiques-mince, direction C, validée le 02/10/2026).
	 *
	 * Les populations sont posées sur le tableau du décor et non relues en base : ce qui s'éprouve
	 * est ce que l'écran fait de chaque nombre, et le décor ne porte pas ces cas.
	 */
	public function testWithoutPlan(): void {

		if($this->requireFixtures() === FALSE) {
			return;
		}

		$dashboard = VegetableDashboardAiLib::get($this->community(), $this->structures(), 2026);
		$dashboard['lastDay'] = '2026-02-28';

		$eStructure = new \structure\Structure(['id' => $this->community()['id'], 'type' => \structure\Structure::COMMUNITY, 'communityType' => \structure\Structure::NETWORK]);

		$full = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, TRUE);

		$this->assertTrue(str_contains($full, '28/02/2026'), 'le témoin : avec des plans de culture, leur date ferme l\'écran');

		$dashboard['plan']['farms'] = 1;
		$dashboard['plan']['placedFarms'] = 0;
		$dashboard['plan']['greenhouseFarms'] = 0;
		$dashboard['species']['list'] = [];
		$dashboard['species']['byMonth'] = array_fill(1, 12, 0);
		$dashboard['hoursByCategory'] = [];

		foreach(array_keys($dashboard['bins']) as $indicator) {
			$dashboard['bins'][$indicator]['bins'] = [];
		}

		$dashboard['farms'] = 6;

		$dashboard['declared']['workshopFarms'] = 5;
		$dashboard['declared']['organic'][\structure\Production::YES] = 4;
		$dashboard['declared']['organic'][\structure\Production::CONVERSION] = 1;
		$dashboard['declared']['totalAreaFarms'] = 4;
		$dashboard['declared']['totalAreaMedian'] = 2.0;
		$dashboard['declared']['areaFarms'] = 4;
		$dashboard['declared']['areaMedian'] = 1.5;
		$dashboard['declared']['startedFarms'] = 2;
		$dashboard['declared']['startedMedian'] = NULL;
		$dashboard['declared']['workforceFarms'] = 0;
		$dashboard['declared']['workforceMedian'] = NULL;

		$this->assertTrue(VegetableDashboardAiLib::hasAboveFloor($dashboard), 'le témoin : le déclaré seul ouvre l\'écran');

		$html = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, TRUE);

		$compare = 'href="'.\structure\StructureUi::urlCommunityCompare($eStructure, new \structure\Section()).'"';

		$this->assertTrue(str_contains($html, $compare), 'le constat ne mène pas à la comparaison');
		$this->assertFalse(str_contains($html, '28/02/2026'), 'la date des plans ne date rien de ce que l\'écran montre');

		// Le bouton n'existe que si la comparaison a une espèce à proposer.
		$html = new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE);

		$this->assertFalse(str_contains($html, $compare), 'sans comparaison, un bouton y mène encore');

		// Sans conversion, l'intitulé du bio ne la mentionne pas.
		$dashboardWithoutConversion = $dashboard;
		$dashboardWithoutConversion['declared']['organic'][\structure\Production::CONVERSION] = 0;

		$this->assertTrue($html !== new VegetableDashboardAiUi()->get($eStructure, $dashboardWithoutConversion, FALSE, FALSE), 'la conversion ne se dit pas');

		// Moins d'ateliers que le plancher : la part en bio ne sort pas de l'écran.
		$dashboard['declared']['workshopFarms'] = 2;

		$dashboardWithoutOrganic = $dashboard;
		$dashboardWithoutOrganic['declared']['organic'][\structure\Production::YES] = 0;
		$dashboardWithoutOrganic['declared']['organic'][\structure\Production::CONVERSION] = 0;

		$this->assertEquals(new VegetableDashboardAiUi()->get($eStructure, $dashboardWithoutOrganic, FALSE, FALSE), new VegetableDashboardAiUi()->get($eStructure, $dashboard, FALSE, FALSE), 'sous le plancher, la part en bio se lit sur l\'écran');

		// Ce qui manque se dit, et rien ne se dit quand rien ne manque.
		$getMissing = new \ReflectionMethod(VegetableDashboardAiUi::class, 'getMissing');

		$this->assertTrue($getMissing->invoke(new VegetableDashboardAiUi(), VegetableDashboardAiLib::FLOOR - 1, []) !== '', 'il manque un atelier, rien ne le dit');
		$this->assertTrue($getMissing->invoke(new VegetableDashboardAiUi(), VegetableDashboardAiLib::FLOOR, [1 => ['la main-d\'œuvre']]) !== '', 'il manque une réponse, rien ne le dit');
		$this->assertEquals('', $getMissing->invoke(new VegetableDashboardAiUi(), VegetableDashboardAiLib::FLOOR, []), 'rien ne manque, rien à dire');

	}

}
?>
