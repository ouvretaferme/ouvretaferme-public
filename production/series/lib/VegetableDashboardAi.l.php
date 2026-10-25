<?php
namespace series;

/**
 * Le tableau de bord maraîchage d'un groupe : ce que ses fermes cultivent, placent et travaillent
 * sur une saison, en sommes, parts, médianes et nombres de fermes par tranche.
 *
 * ⚠️ **Lib d'écran et non de module**, comme `CompareAiLib` : il ne porte ni `create` ni `update`,
 * ne possède aucune table, et compose les accesseurs des modules qu'il lit — y compris ceux de
 * `structure`, pour les ateliers et la fiche système.
 *
 * ⚠️ **Aucune valeur ne sort d'ici rattachée à une ferme.** C'est la consigne de l'arbitrage
 * (« jamais de point par ferme ») et c'est ce qui rend le plancher efficace : les lignes par ferme
 * vivent dans les méthodes privées, l'appelant ne reçoit que des agrégats. Rendre `getPlan()`
 * public rouvrirait le chemin qu'on vient de fermer.
 *
 * ⚠️ **Deux référentiels se comparent par leur clé partagée, jamais par un identifiant de ferme.**
 * Chaque ferme porte sa copie des espèces et des catégories de travail : c'est `plant.reference`
 * qui réunit les premières et `work\Category.fqn` les secondes. Un regroupement par identifiant
 * rendrait autant de groupes que de fermes.
 */
class VegetableDashboardAiLib {

	/**
	 * Fermes requises pour qu'une valeur s'affiche.
	 *
	 * Sous ce seuil, un contributeur retrouve la valeur de son voisin à partir de la sienne et de
	 * l'agrégat. Trois, décidé le 19/09/2026 (cinq auparavant). La constante ne lit pas
	 * `Community.quotationMinimum` : celui-ci est un réglage que l'administrateur du groupe modifie
	 * dans le formulaire des relevés, et un tableau de bord qui masquerait plus ou moins selon un
	 * réglage de mercuriale mentirait sur ce qu'il montre.
	 *
	 * ⚠️ **Le plancher borne chaque chiffre, pas l'écart entre deux lectures.** Le groupe entier et
	 * un sous-groupe qui n'en diffère que d'une ferme passent tous deux le plancher, et leur
	 * différence rend la valeur de cette ferme. Accepté le 16/09/2026, l'écran étant
	 * réservé à l'équipe du réseau. Refuser un sous-groupe dont le complément dans le groupe est
	 * sous le plancher fermerait ce chemin, si le lectorat s'élargit.
	 */
	const FLOOR = 3;

	/**
	 * Séries qu'une ferme doit porter dans la saison pour entrer dans les chiffres tirés du plan de
	 * culture : chiffres de tête, tranches, espèces, calendrier et choix des saisons.
	 *
	 * le 17/09/2026 : « il faudrait qu'une ferme ait fait au moins 10 séries pour être
	 * incluse dans ces chiffres ». Une ferme qui n'a saisi que quelques séries n'a pas fait son plan
	 * dans le produit, et ses surfaces ou ses espèces tireraient les médianes vers le bas.
	 *
	 * ⚠️ Les séries closes comptent : une série terminée a été cultivée, et la saison passée n'en
	 * porte presque plus d'autres. Les heures, elles, ne dépendent pas de ce seuil : la feuille de
	 * temps ne vient pas du plan de culture, et une ferme qui saisit son temps sans planifier reste
	 * comptée.
	 */
	const SERIES_MINIMUM = 10;

	/**
	 * Surface de planches, en m², au-delà de laquelle une ferme entre dans les tranches de planches.
	 *
	 * le 19/09/2026 : « on compte uniquement les fermes qui ont plus de 100 m² de planche
	 * sur ce bloc ». Indépendant de `SERIES_MINIMUM`, comme le fixe la planche 8.
	 */
	const BED_AREA_MINIMUM = 100;

	/**
	 * L'état d'une nature de travail dans `getHoursByCategory()`.
	 *
	 * Une nature que personne n'a saisie et une nature versée dans « Autres » ne se lisent pas
	 * pareil : la première est « non renseigné », la seconde a des heures, comptées ailleurs.
	 */
	const NATURE_SHOWN = 'shown';
	const NATURE_MERGED = 'merged';
	const NATURE_ABSENT = 'absent';

	/**
	 * L'atelier dont ce tableau de bord parle.
	 *
	 * `Production.fqn` est un texte libre dont `Production::CROP` tient la liste, sans constante :
	 * le dépôt écrit le littéral, on le nomme ici plutôt que de le semer dans les méthodes.
	 */
	const WORKSHOP = 'maraichage';

	const AREA = 'area';
	const GREENHOUSE = 'greenhouse';
	const SPECIES = 'species';
	const HARVESTED = 'harvested';
	const BED_AREA = 'bedArea';
	const BED_GREENHOUSE = 'bedGreenhouse';

	/**
	 * Bornes des tranches, par indicateur : `[borne basse incluse, borne haute exclue]`.
	 *
	 * Une tranche dont les deux bornes sont égales vaut « exactement cette valeur » : elle isole
	 * les fermes à zéro, que la maquette montre à part parce que c'est l'information même —
	 * 27 fermes sur 50 n'ont saisi aucune récolte.
	 *
	 * ⚠️ Les bornes ne sont pas traduites ici : le libellé se compose dans l'UI à partir des
	 * bornes, et il le doit, parce qu'`applyFloor()` fusionne deux tranches et fabrique une borne
	 * qu'aucune constante ne porte.
	 */
	const BINS = [
		self::AREA => [[0, 1000], [1000, 5000], [5000, 10000], [10000, 20000], [20000, NULL]],
		self::GREENHOUSE => [[0, 0], [0, 10], [10, 25], [25, 50], [50, NULL]],
		self::SPECIES => [[0, 20], [20, 40], [40, 60], [60, NULL]],
		self::HARVESTED => [[0, 0], [0, 25], [25, 50], [50, NULL]]
	];

	/**
	 * Les saisons que le groupe peut remplir, de la plus récente à la plus ancienne.
	 *
	 * ⚠️ **Bornées par le plancher et par le seuil de séries**, contrairement à
	 * `CompareAiLib::getSeasons()` : une saison où moins de `FLOOR` fermes atteignent
	 * `SERIES_MINIMUM` ne rendrait qu'un plan de culture vide, et proposer une commande qui ne mène
	 * qu'à du vide est un défaut. Mesuré sur 6388 : 2026 (43 fermes), 2025 (21), 2024 (7) et 2023 (3)
	 * restent, 2022 (2) sort.
	 *
	 * Une saison à venir ne se propose jamais : les tables agrégées n'en portent pas (
	 * 19/09/2026, « années suivantes jamais calculées »).
	 */
	public static function getSeasons(\structure\Structure $eStructureCommunity, \Collection $cStructure): array {

		$cStructure = self::getPopulation($eStructureCommunity, $cStructure);

		if($cStructure->empty()) {
			return [];
		}

		$cVegetablePlan = \aggregate\VegetablePlanAiLib::getByStructures($cStructure);

		$farms = [];

		foreach($cVegetablePlan as $eVegetablePlan) {

			if($eVegetablePlan['series'] >= self::SERIES_MINIMUM) {
				$farms[$eVegetablePlan['season']] ??= 0;
				$farms[$eVegetablePlan['season']]++;
			}

		}

		krsort($farms);

		return array_keys(array_filter($farms, fn(int $count) => $count >= self::FLOOR));

	}

	/**
	 * Tout ce que l'écran montre, en une fois.
	 *
	 * Les blocs se lisent sur la même population et la même saison : les composer ici évite qu'un
	 * appelant en borne un autrement, et c'est la ligne de population affichée sur chaque carte
	 * qui doit rester vraie.
	 *
	 * Le plan de culture, les espèces et le temps de travail se lisent dans les tables que
	 * `aggregate\VegetableProjectionAiLib` écrit (19/09/2026 : « travailler les
	 * performances avec une fonctionnalité d'agregation ») : chaque nuit pour l'année en cours, et
	 * le 1er janvier pour l'année écoulée, par la reprise pour le reste du passé. `lastDay` est le
	 * dernier jour complet que couvre la saison affichée, la veille du passage qui l'a calculée,
	 * comme les statistiques de l'administration ; `NULL` avant tout calcul. Les planches et le
	 * déclaré restent lus en direct.
	 */
	public static function get(\structure\Structure $eStructureCommunity, \Collection $cStructure, int $season): array {

		$cStructure = self::getPopulation($eStructureCommunity, $cStructure);

		$cVegetablePlan = \aggregate\VegetablePlanAiLib::getByStructures($cStructure, $season);
		$cStructureAboveMinimum = self::getStructuresAboveMinimum($cStructure, $cVegetablePlan);

		$cVegetableHours = \aggregate\VegetableHoursAiLib::getByStructures($cStructure, $season);

		$plan = self::getPlan($cStructure, $cStructureAboveMinimum, $cVegetablePlan, $cVegetableHours);

		return [
			'farms' => $cStructure->count(),
			'season' => $season,
			'lastDay' => self::getLastDay($cStructure, $season),
			'plan' => self::getPlanFigures($plan),
			'bins' => self::getPlanBins($plan) + self::getBedBins(self::getBedAreaByStructure($cStructure, $season)),
			'species' => self::getSpecies($cStructureAboveMinimum, $season),
			'hoursByCategory' => self::getHoursByCategory($cVegetableHours),
			'declared' => self::getDeclared($cStructure)
		];

	}

	/**
	 * Les fermes que le tableau de bord compte, parmi celles rattachées au groupe.
	 *
	 * le 19/09/2026 : « Fermes de production uniquement pour les réseaux, toutes fermes pour
	 * les formations ». Une ferme d'essai ou un projet d'installation tirerait les chiffres d'un réseau
	 * vers ce qui ne se cultive pas ; dans une formation, les fermes d'apprenants sont la population
	 * même, et les écarter viderait l'écran. Les groupements de producteurs suivent la règle des réseaux.
	 *
	 * ⚠️ Filtré à la lecture et non dans la projection : le type se change dans les réglages de la
	 * ferme, et l'écran le suit aussitôt, sans attendre la nuit ni la reprise pour une année close.
	 */
	private static function getPopulation(\structure\Structure $eStructureCommunity, \Collection $cStructure): \Collection {

		if($eStructureCommunity->isEducation()) {
			return $cStructure;
		}

		return $cStructure->find(fn(\structure\Structure $eStructure) => $eStructure['producerType'] === \structure\Structure::REAL);

	}

	private static function getLastDay(\Collection $cStructure, int $season): ?string {

		$computedAt = \aggregate\VegetablePlanAiLib::getComputedAt($cStructure, $season);

		if($computedAt === NULL) {
			return NULL;
		}

		return date('Y-m-d', strtotime(substr($computedAt, 0, 10).' - 1 day'));

	}

	/**
	 * Au moins une valeur de l'écran atteint le plancher.
	 *
	 * ⚠️ C'est la seule condition qui décide de ce que l'écran affiche : fausse, le tableau de bord
	 * cède la place à un bloc vide qui renvoie vers la comparaison des cultures. Elle ne se déduit pas
	 * d'un bloc en particulier — un groupe peut n'avoir aucun plan de culture et cinq ateliers
	 * déclarés, et il a alors quelque chose à montrer.
	 */
	public static function hasAboveFloor(array $dashboard): bool {

		$populations = [
			$dashboard['plan']['farms'],
			$dashboard['plan']['placedFarms'],
			$dashboard['plan']['hoursFarms'],
			$dashboard['bins'][self::BED_AREA]['farms'],
			$dashboard['declared']['workshopFarms'],
			$dashboard['declared']['areaFarms'],
			$dashboard['declared']['totalAreaFarms']
		];

		foreach($populations as $farms) {

			if($farms >= self::FLOOR) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/** Les fermes de la population qui portent au moins `SERIES_MINIMUM` séries dans la saison. */
	private static function getStructuresAboveMinimum(\Collection $cStructure, \Collection $cVegetablePlan): \Collection {

		$structures = [];

		foreach($cVegetablePlan as $eVegetablePlan) {

			if($eVegetablePlan['series'] >= self::SERIES_MINIMUM) {
				$structures[$eVegetablePlan['structure']['id']] = TRUE;
			}

		}

		return $cStructure->find(fn(\structure\Structure $eStructure) => isset($structures[$eStructure['id']]));

	}

	/**
	 * Une ligne par ferme : ce que chacune cultive, place et travaille.
	 *
	 * Privée, et elle doit le rester : c'est le seul endroit du lot où une valeur est rattachée à
	 * une ferme, et l'écran n'en montre jamais.
	 *
	 * ⚠️ Deux populations : les cultures et les surfaces se lisent sur les fermes au-dessus du seuil
	 * de séries, les heures sur tout le groupe (cf. `SERIES_MINIMUM`).
	 */
	private static function getPlan(\Collection $cStructure, \Collection $cStructureAboveMinimum, \Collection $cVegetablePlan, \Collection $cVegetableHours): array {

		if($cStructure->empty()) {
			return [];
		}

		$plan = [];

		foreach($cVegetableHours as $eVegetableHours) {

			$structure = $eVegetableHours['structure']['id'];

			$plan[$structure] ??= self::getEmptyFarm();
			$plan[$structure]['hours'] += $eVegetableHours['hours'];

		}

		foreach($cVegetablePlan as $eVegetablePlan) {

			$structure = $eVegetablePlan['structure']['id'];

			if($cStructureAboveMinimum->offsetExists($structure) === FALSE) {
				continue;
			}

			$plan[$structure] ??= self::getEmptyFarm();
			$plan[$structure]['cultivations'] = $eVegetablePlan['cultivations'];
			$plan[$structure]['harvested'] = $eVegetablePlan['harvested'];
			$plan[$structure]['species'] = $eVegetablePlan['species'];
			$plan[$structure]['area'] = $eVegetablePlan['area'];
			$plan[$structure]['greenhouse'] = $eVegetablePlan['greenhouse'];

		}

		return $plan;

	}

	private static function getEmptyFarm(): array {

		return [
			'cultivations' => 0,
			'harvested' => 0,
			'species' => 0,
			'area' => 0.0,
			'greenhouse' => 0.0,
			'hours' => 0.0
		];

	}

	/** Les sommes, parts et populations de la carte des chiffres du réseau. */
	private static function getPlanFigures(array $plan): array {

		$planned = array_filter($plan, fn($farm) => $farm['cultivations'] > 0);
		$placed = array_filter($plan, fn($farm) => $farm['area'] > 0);
		$timed = array_filter($plan, fn($farm) => $farm['hours'] > 0);

		$area = array_sum(array_column($placed, 'area'));

		return [
			'farms' => count($planned),
			'harvested' => array_sum(array_column($planned, 'harvested')),
			'harvestFarms' => count(array_filter($planned, fn($farm) => $farm['harvested'] > 0)),
			'placedFarms' => count($placed),
			'area' => $area,
			'openFieldArea' => $area - array_sum(array_column($placed, 'greenhouse')),
			'greenhouseArea' => array_sum(array_column($placed, 'greenhouse')),
			'greenhouseShare' => ($area > 0) ? array_sum(array_column($placed, 'greenhouse')) / $area * 100 : NULL,
			'greenhouseFarms' => count(array_filter($placed, fn($farm) => $farm['greenhouse'] > 0)),
			'hoursFarms' => count($timed),
			'hours' => array_sum(array_column($timed, 'hours'))
		];

	}

	/** Les quatre répartitions par tranche, plancher déjà appliqué. */
	private static function getPlanBins(array $plan): array {

		$planned = array_filter($plan, fn($farm) => $farm['cultivations'] > 0);
		$placed = array_filter($plan, fn($farm) => $farm['area'] > 0);

		$values = [
			self::AREA => array_column($placed, 'area'),
			self::GREENHOUSE => array_map(fn($farm) => $farm['greenhouse'] / $farm['area'] * 100, $placed),
			self::SPECIES => array_column($planned, 'species'),
			self::HARVESTED => array_map(fn($farm) => $farm['harvested'] / $farm['cultivations'] * 100, $planned)
		];

		$bins = [];

		foreach($values as $indicator => $indicatorValues) {

			$bins[$indicator] = [
				'farms' => count($indicatorValues),
				'median' => self::getMedian($indicatorValues),
				'bins' => self::applyFloor(self::getBins($indicatorValues, self::BINS[$indicator], self::getMedian($indicatorValues)))
			];

		}

		return $bins;

	}

	/**
	 * La surface de planches de chaque ferme dans la saison, et sa part sous abri.
	 *
	 * ⚠️ Une planche de la saison est dans la saison **et** sous une parcelle qui l'est aussi, la
	 * définition de `map\BedLib::getByStructure()` : une planche dont la parcelle est close ne
	 * paraît plus sur la cartographie de la ferme, et la compter ici ferait lire au groupe une
	 * surface que la ferme ne voit pas. Mesuré sur 6388 en 2026 : 64 planches, 7 761 m² sur 5 fermes.
	 *
	 * ⚠️ Les planches de remplissage (`plotFill`, `zoneFill`) portent la surface d'une parcelle ou
	 * d'une zone sans planches dessinées : ce ne sont pas des planches déclarées.
	 */
	private static function getBedAreaByStructure(\Collection $cStructure, int $season): array {

		if($cStructure->empty()) {
			return [];
		}

		\map\SeasonLib::whereSeason(\map\Plot::model(), $season);

		$cPlot = \map\Plot::model()
			->select(['id', 'structure', 'mode'])
			->where('structure', 'IN', $cStructure)
			->getCollection(index: 'id');

		if($cPlot->empty()) {
			return [];
		}

		\map\SeasonLib::whereSeason(\map\Bed::model(), $season);

		$cBed = \map\Bed::model()
			->select([
				'plot' => new \Sql('plot', 'int'),
				'area' => new \Sql('SUM(area)', 'float')
			])
			->where('plot', 'IN', $cPlot)
			->where('plotFill', FALSE)
			->where('zoneFill', FALSE)
			->whereStatus(\map\Bed::ACTIVE)
			->group(new \Sql('plot'))
			->getCollection();

		$farms = [];

		foreach($cBed as $eBed) {

			$ePlot = $cPlot[$eBed['plot']];
			$structure = $ePlot['structure']['id'];

			$farms[$structure] ??= ['area' => 0.0, 'greenhouse' => 0.0];
			$farms[$structure]['area'] += $eBed['area'];

			if($ePlot['mode'] === \map\Plot::GREENHOUSE) {
				$farms[$structure]['greenhouse'] += $eBed['area'];
			}

		}

		return $farms;

	}

	/**
	 * Les deux répartitions des planches, sur les fermes au-delà de `BED_AREA_MINIMUM`, plancher
	 * déjà appliqué.
	 *
	 * Les bornes sont celles de la surface développée et de sa part sous abri, comme le fixe la
	 * planche 8. La moyenne s'ajoute à la médiane, choisie le 19/09/2026 pour ces deux
	 * cartes.
	 */
	public static function getBedBins(array $farms): array {

		$farms = array_filter($farms, fn($farm) => $farm['area'] > self::BED_AREA_MINIMUM);

		$values = [
			self::BED_AREA => [array_column($farms, 'area'), self::BINS[self::AREA]],
			self::BED_GREENHOUSE => [array_values(array_map(fn($farm) => $farm['greenhouse'] / $farm['area'] * 100, $farms)), self::BINS[self::GREENHOUSE]]
		];

		$bins = [];

		foreach($values as $indicator => [$indicatorValues, $bounds]) {

			$median = self::getMedian($indicatorValues);

			$bins[$indicator] = [
				'farms' => count($indicatorValues),
				'median' => $median,
				'mean' => ($indicatorValues === []) ? NULL : array_sum($indicatorValues) / count($indicatorValues),
				'bins' => self::applyFloor(self::getBins($indicatorValues, $bounds, $median))
			];

		}

		return $bins;

	}

	/**
	 * Le nombre de fermes par tranche, la tranche de la médiane repérée.
	 *
	 * `$high === $low` vaut « exactement cette valeur » et isole les fermes à zéro ; `$high` à NULL
	 * ouvre la dernière tranche.
	 */
	public static function getBins(array $values, array $bounds, ?float $median = NULL): array {

		$bins = [];

		foreach($bounds as [$low, $high]) {
			$bins[] = ['low' => $low, 'high' => $high, 'farms' => 0, 'median' => FALSE];
		}

		foreach($values as $value) {

			$index = self::getBinIndex((float)$value, $bounds);

			if($index !== NULL) {
				$bins[$index]['farms']++;
			}

		}

		if($median !== NULL) {

			$index = self::getBinIndex($median, $bounds);

			if($index !== NULL) {
				$bins[$index]['median'] = TRUE;
			}

		}

		return $bins;

	}

	/**
	 * La tranche d'une valeur : **la première qui l'accepte**, jamais toutes celles qui l'acceptent.
	 *
	 * ⚠️ Les bornes se chevauchent par construction : une tranche « exactement zéro » est suivie
	 * d'une tranche qui part de zéro, et une valeur nulle satisfait les deux. Compter tranche par
	 * tranche la comptait donc deux fois — mesuré avant correction, 77 fermes réparties pour 50
	 * qui existent, et la médiane marquée sur deux tranches. Ranger chaque valeur une seule fois
	 * est ce qui rend la somme des tranches égale à la population.
	 */
	private static function getBinIndex(float $value, array $bounds): ?int {

		foreach($bounds as $index => [$low, $high]) {

			if($high === NULL) {
				$matches = ($value >= $low);
			} else if($high === $low) {
				$matches = ($value === (float)$low);
			} else {
				$matches = ($value >= $low and $value < $high);
			}

			if($matches) {
				return $index;
			}

		}

		return NULL;

	}

	/**
	 * Masque ce qui tient sur moins de `FLOOR` fermes, sans laisser le total le redonner.
	 *
	 * ⚠️ **Masquer une tranche ne suffit pas** : le total des fermes la redonnerait par
	 * soustraction. Une tranche sous le plancher fusionne donc avec la plus petite de ses
	 * voisines, et la fusion se rejoue tant qu'il en reste une — deux tranches à trois fermes
	 * fusionnées en font six, mais trois tranches à une ferme en font trois, toujours sous le
	 * plancher.
	 *
	 * Une tranche vide disparaît : elle ne cache rien et n'a rien à montrer.
	 */
	public static function applyFloor(array $bins): array {

		$bins = array_values(array_filter($bins, fn($bin) => $bin['farms'] > 0));

		while(count($bins) > 1) {

			$index = NULL;

			foreach($bins as $position => $bin) {

				if($bin['farms'] < self::FLOOR) {
					$index = $position;
					break;
				}

			}

			if($index === NULL) {
				break;
			}

			$neighbour = self::getSmallestNeighbour($bins, $index);

			$first = min($index, $neighbour);
			$second = max($index, $neighbour);

			array_splice($bins, $first, 2, [[
				'low' => $bins[$first]['low'],
				'high' => $bins[$second]['high'],
				'farms' => $bins[$first]['farms'] + $bins[$second]['farms'],
				'median' => ($bins[$first]['median'] or $bins[$second]['median'])
			]]);

		}

		// Une tranche unique sous le plancher n'a personne avec qui fusionner : la répartition
		// entière disparaît, sinon elle dirait la valeur des quelques fermes qui la portent.
		if($bins !== [] and $bins[0]['farms'] < self::FLOOR) {
			return [];
		}

		return $bins;

	}

	private static function getSmallestNeighbour(array $bins, int $index): int {

		if($index === 0) {
			return 1;
		}

		if($index === count($bins) - 1) {
			return $index - 1;
		}

		return ($bins[$index - 1]['farms'] <= $bins[$index + 1]['farms']) ? $index - 1 : $index + 1;

	}

	public static function getMedian(array $values): ?float {

		sort($values);

		$count = count($values);

		if($count === 0) {
			return NULL;
		}

		return ($count % 2)
			? (float)$values[intdiv($count, 2)]
			: ($values[$count / 2 - 1] + $values[$count / 2]) / 2;

	}

	/**
	 * Les espèces cultivées par le groupe, celles au-dessus du plancher détaillées.
	 *
	 * Les espèces se lisent dans `aggregate\VegetableSpecies`, une ligne par ferme, saison et
	 * espèce, que `aggregate\VegetableProjectionAiLib` prépare la nuit avec ses mois de récolte.
	 * Les fermes se comptent ici, sur le groupe du moment.
	 *
	 * ⚠️ Une culture dont l'espèce n'a pas de `reference` n'est comparable à rien — la ferme se
	 * l'est créée. Elle compte dans le total des cultures et dans aucune ligne d'espèce, comme sur
	 * l'écran de comparaison. Mesuré : 300 cultures de 29 fermes à 6388 en 2026.
	 */
	public static function getSpecies(\Collection $cStructure, int $season): array {

		if($cStructure->empty()) {
			return ['count' => 0, 'aboveFloor' => 0, 'list' => [], 'byMonth' => array_fill(1, 12, 0)];
		}

		$species = [];

		foreach(\aggregate\VegetableSpeciesAiLib::getByStructures($cStructure, $season) as $eVegetableSpecies) {

			$reference = $eVegetableSpecies['plant']['id'];
			$structure = $eVegetableSpecies['structure']['id'];

			$species[$reference] ??= ['cultivations' => 0, 'structures' => [], 'months' => []];

			$species[$reference]['cultivations'] += $eVegetableSpecies['cultivations'];
			$species[$reference]['structures'][$structure] = TRUE;

			foreach($eVegetableSpecies['months'] as $month) {
				$species[$reference]['months'][$month][$structure] = TRUE;
			}

		}

		if($species === []) {
			return ['count' => 0, 'aboveFloor' => 0, 'list' => [], 'byMonth' => array_fill(1, 12, 0)];
		}

		$cPlant = \plant\Plant::model()
			->select(['id', 'name', 'vignette', 'fqn'])
			->whereId('IN', array_keys($species))
			->getCollection(index: 'id');

		$list = [];

		foreach($species as $reference => $values) {

			$farms = count($values['structures']);

			if($farms < self::FLOOR or $cPlant->offsetExists($reference) === FALSE) {
				continue;
			}

			$months = [];

			for($month = 1; $month <= 12; $month++) {

				$monthFarms = count($values['months'][$month] ?? []);

				// Une case sous le plancher reste vide, et jamais à zéro : la matrice n'affiche
				// aucun total par mois, donc une case masquée ne se déduit d'aucune colonne.
				$months[$month] = ($monthFarms >= self::FLOOR) ? $monthFarms : NULL;

			}

			$list[] = [
				'ePlant' => $cPlant[$reference],
				'farms' => $farms,
				'cultivations' => $values['cultivations'],
				'months' => $months
			];

		}

		usort($list, fn($first, $second) => [$second['farms'], $second['cultivations']] <=> [$first['farms'], $first['cultivations']]);

		// Le seul agrégat par mois qui ne divulgue rien : un nombre d'espèces dont la case passe
		// déjà le plancher, et non un nombre de fermes, qui redonnerait par soustraction une case
		// masquée.
		$byMonth = array_fill(1, 12, 0);

		foreach($list as $line) {

			foreach($line['months'] as $month => $farms) {

				if($farms !== NULL) {
					$byMonth[$month]++;
				}

			}

		}

		return [
			'count' => count($species),
			'aboveFloor' => count($list),
			'list' => $list,
			'byMonth' => $byMonth
		];

	}


	/**
	 * Le temps saisi par catégorie d'intervention, sur l'année civile de la feuille de temps.
	 *
	 * ⚠️ **L'année civile et non la saison, faute de pouvoir faire autrement.** `series\Timesheet`
	 * ne porte pas de saison, et celle de l'intervention dont elle pend est nullable : mesuré,
	 * 236 551 lignes et 225 120 h de la base de dev tiennent à une intervention sans saison ni
	 * série, soit 59 % des heures, qu'une borne par saison jetterait. C'est aussi la borne que
	 * `work\AnalyzeLib` emploie partout. Elle approche la saison sans la valoir : la préparation
	 * d'hiver d'une saison tombe dans l'année civile suivante, et l'écran le dit.
	 *
	 * ⚠️ **Les natures se réunissent par `fqn`, jamais par identifiant.** Chaque ferme porte sa
	 * copie des catégories (`CategoryLib::duplicateForStructure()`) : un regroupement par
	 * identifiant rend 60 groupes sur 64 fermes. Les quatre catégories de départ ont chacune leur
	 * `fqn` depuis la reprise du lot L9. Une ferme peut en avoir supprimé une (en dev le
	 * 16/09/2026, 27 fermes sur 64 de 6388 n'ont pas « Commercialisation ») et s'en créer
	 * d'autres, sans `fqn` : leurs heures vont dans un reste, « Autres », que l'écran nomme et dont
	 * il dit le contenu.
	 *
	 * ⚠️ **La carte totalise exactement les heures du chiffre de tête**, et c'est ce qui tient le
	 * plancher : une différence entre les deux serait une valeur masquée, que la soustraction
	 * rendrait. Elle porte donc la même borne que `getPlan()`, l'année civile entière, et masque
	 * par nature et non par mois. D'où trois règles :
	 * - une nature sous le plancher rejoint le reste au lieu de disparaître ;
	 * - un reste sous le plancher reçoit la nature affichée la moins peuplée, jusqu'à le passer ;
	 * - s'il n'y a plus de nature à lui verser, la carte entière se tait.
	 *
	 * Les fermes se comptent distinctes sur les groupes fusionnés : c'est pourquoi
	 * `aggregate\VegetableHours` porte une ligne par ferme et par `fqn`, et non une ligne par `fqn` dont on ne pourrait pas
	 * additionner les populations.
	 *
	 * @return array `[]` quand la carte se tait, sinon `natures`, `others` et `farms` (voir `applyCategoryFloor()`).
	 */
	private static function getHoursByCategory(\Collection $cVegetableHours): array {

		$rows = [];

		foreach($cVegetableHours as $eVegetableHours) {
			$rows[] = ['structure' => $eVegetableHours['structure']['id'], 'fqn' => $eVegetableHours['fqn'], 'hours' => $eVegetableHours['hours']];
		}

		return self::applyCategoryFloor($rows);

	}

	/**
	 * Réunit les heures par nature et y applique le plancher, sans rien perdre des heures.
	 *
	 * Séparée de la lecture des lignes pour que les deux dernières règles de `getHoursByCategory()` s'éprouvent
	 * sur des lignes écrites à la main : la base de dev n'a aucune saison qui les déclenche.
	 *
	 * ⚠️ **Chaque nature connue sort avec son état**, y compris quand elle n'a rien à montrer :
	 * une nature absente s'écrit « non renseigné » et jamais 0 h, une nature versée dans le reste
	 * doit être nommée par la phrase qui dit ce que le reste contient. Une nature qui disparaîtrait
	 * en silence ne permettrait ni l'un ni l'autre. Une nature absente ou versée ne porte pas
	 * d'heures : celles d'une nature versée sont comptées dans le reste, et n'en sortent pas.
	 *
	 * @param array $rows Une ligne par ferme et par `fqn` : `structure`, `fqn`, `hours`.
	 * @return array `[]` quand le reste est sous le plancher ou que rien n'est à montrer, sinon :
	 * - `natures` : une entrée par nature de `getCategories()`, dans cet ordre, avec `fqn`, `state`
	 *   (`NATURE_*`), et `hours` et `farms` pour une nature affichée, `NULL` sinon ;
	 * - `others` : `NULL` si le reste est vide, sinon `hours`, `farms`, `merged` (les `fqn` versés)
	 *   et `own` (vrai si des catégories sans nature connue y sont) ;
	 * - `farms` : le nombre de fermes de la ligne affichée la plus peuplée, qui donne la population
	 *   de la carte.
	 */
	public static function applyCategoryFloor(array $rows): array {

		$fqnCategories = self::getCategories();

		$natures = [];

		foreach($fqnCategories as $fqn) {
			$natures[$fqn] = ['hours' => 0.0, 'structures' => [], 'merged' => FALSE];
		}

		$others = ['hours' => 0.0, 'structures' => [], 'merged' => [], 'own' => FALSE];

		foreach($rows as $row) {

			// Une catégorie qu'une ferme s'est créée n'a pas de `fqn`, ou pas l'un des nôtres.
			if(in_array($row['fqn'], $fqnCategories, TRUE)) {
				$natures[$row['fqn']]['hours'] += $row['hours'];
				$natures[$row['fqn']]['structures'][$row['structure']] = TRUE;
			} else {
				$others['hours'] += $row['hours'];
				$others['structures'][$row['structure']] = TRUE;
				$others['own'] = TRUE;
			}

		}

		foreach($fqnCategories as $fqn) {

			if($natures[$fqn]['structures'] !== [] and count($natures[$fqn]['structures']) < self::FLOOR) {

				$others['hours'] += $natures[$fqn]['hours'];
				$others['structures'] += $natures[$fqn]['structures'];
				$others['merged'][] = $fqn;

				$natures[$fqn]['merged'] = TRUE;

			}

		}

		// Un reste non vide sous le plancher se déduirait des mois moins les natures affichées : la
		// nature affichée la moins peuplée le rejoint, jusqu'à ce qu'il passe le plancher. Mesuré
		// sur 6388 en 2026 après la reprise de L9 : deux fermes seulement dans le reste, que la
		// commercialisation (six fermes) porte au-dessus du plancher au lieu de taire la carte.
		while($others['structures'] !== [] and count($others['structures']) < self::FLOOR) {

			$smallest = NULL;

			foreach($fqnCategories as $fqn) {

				if($natures[$fqn]['structures'] === [] or $natures[$fqn]['merged']) {
					continue;
				}

				if($smallest === NULL or count($natures[$fqn]['structures']) <= count($natures[$smallest]['structures'])) {
					$smallest = $fqn;
				}

			}

			if($smallest === NULL) {
				return [];
			}

			$others['hours'] += $natures[$smallest]['hours'];
			$others['structures'] += $natures[$smallest]['structures'];
			$others['merged'][] = $smallest;

			$natures[$smallest]['merged'] = TRUE;

		}

		$result = [
			'natures' => [],
			'others' => NULL
		];

		foreach($fqnCategories as $fqn) {

			if($natures[$fqn]['structures'] === []) {
				$state = self::NATURE_ABSENT;
			} else if($natures[$fqn]['merged']) {
				$state = self::NATURE_MERGED;
			} else {
				$state = self::NATURE_SHOWN;
			}

			$result['natures'][] = [
				'fqn' => $fqn,
				'state' => $state,
				'hours' => ($state === self::NATURE_SHOWN) ? $natures[$fqn]['hours'] : NULL,
				'farms' => ($state === self::NATURE_SHOWN) ? count($natures[$fqn]['structures']) : NULL
			];

		}

		if($others['structures'] !== []) {
			$result['others'] = [
				'hours' => $others['hours'],
				'farms' => count($others['structures']),
				'merged' => $others['merged'],
				'own' => $others['own']
			];
		}

		$lines = array_filter($result['natures'], fn(array $nature) => $nature['state'] === self::NATURE_SHOWN);

		if($result['others'] !== NULL) {
			$lines[] = $result['others'];
		}

		if($lines === []) {
			return [];
		}

		$result['farms'] = max(array_column($lines, 'farms'));

		return $result;

	}


	/**
	 * Les natures de travail, dans l'ordre de lecture : les quatre catégories que le produit donne
	 * à chaque ferme, reconnues par leur `fqn`.
	 *
	 * ⚠️ Une nature ajoutée ici demande son libellé dans `VegetableDashboardAiUi::getNatureName()`,
	 * dont le `match` sans `default` échoue sur une nature qu'il ne connaît pas.
	 */
	private static function getCategories(): array {
		return [CATEGORIE_CULTURE, CATEGORIE_PRODUCTION, CATEGORIE_COMMERCIALISATION, CATEGORIE_ADMINISTRATION];
	}

	/**
	 * Les ateliers et la fiche système des fermes du groupe.
	 *
	 * ⚠️ **Aucun filtre de partage.** Système et productions sont toujours partagés avec le groupe
	 * depuis l'arbitrage du 15/09/2026 ; seuls le plan de culture et l'export restent au choix de
	 * la ferme. Le filtre par `shareProduction` que porte encore `ProfileAiLib` est l'ancien
	 * régime, que L3-L4 redresse.
	 */
	public static function getDeclared(\Collection $cStructure): array {

		$ccProduction = \structure\ProductionLib::getByStructures($cStructure);
		$cSystem = \structure\SystemLib::getByStructures($cStructure);

		$workshops = [];
		$areas = [];
		$started = [];
		$organic = [\structure\Production::YES => 0, \structure\Production::CONVERSION => 0];

		foreach($ccProduction as $cProduction) {
			foreach($cProduction as $eProduction) {

				// Un atelier fermé ne dit plus ce que la ferme fait aujourd'hui. Aucune ligne
				// `inactive` en dev : la règle ne s'y éprouve pas, seule la production la posera.
				if($eProduction['status'] !== \structure\Production::ACTIVE) {
					continue;
				}

				$workshops[$eProduction['fqn']] ??= 0;
				$workshops[$eProduction['fqn']]++;

				if($eProduction['fqn'] !== self::WORKSHOP) {
					continue;
				}

				if(isset($organic[$eProduction['organic']])) {
					$organic[$eProduction['organic']]++;
				}

				if($eProduction['area'] !== NULL) {
					$areas[] = (float)$eProduction['area'];
				}

				if($eProduction['startedAt'] !== NULL) {
					$started[] = (float)$eProduction['startedAt'];
				}

			}
		}

		// La main-d'œuvre se lit sur `hrFarmer` seul : c'est le champ que la fiche demande en
		// premier, et une ferme qui ne l'a pas renseigné n'a rien renseigné du reste. Les deux
		// autres lignes se saisissent sans lui, donc les sommer poserait un total sur une part.
		$workforces = [];

		// La SAU de la fiche système, toutes productions confondues : ce n'est pas `areas`, la
		// surface en plein champ que la ferme déclare pour son seul atelier maraîchage.
		$totalAreas = [];

		foreach($cSystem as $eSystem) {

			if($eSystem['hrFarmer'] !== NULL) {
				$workforces[] = (float)$eSystem['hrFarmer'] + (float)$eSystem['hrEmployee'] + (float)$eSystem['hrOther'];
			}

			if($eSystem['totalArea'] !== NULL) {
				$totalAreas[] = (float)$eSystem['totalArea'];
			}

		}

		// Le plancher vaut aussi pour la liste des ateliers : un atelier déclaré par deux fermes
		// les désignerait presque. Ceux qu'il masque se comptent sans se nommer, comme les espèces.
		$listed = array_filter($workshops, fn($farms) => $farms >= self::FLOOR);

		arsort($listed);

		return [
			'workshops' => $listed,
			'workshopsBelowFloor' => count($workshops) - count($listed),
			'workshopFarms' => $workshops[self::WORKSHOP] ?? 0,
			'organic' => $organic,
			'areaFarms' => count($areas),
			'areaMedian' => (count($areas) >= self::FLOOR) ? self::getMedian($areas) : NULL,
			'startedFarms' => count($started),
			'startedMedian' => (count($started) >= self::FLOOR) ? self::getMedian($started) : NULL,
			'workforceFarms' => count($workforces),
			'workforceMedian' => (count($workforces) >= self::FLOOR) ? self::getMedian($workforces) : NULL,
			'totalAreaFarms' => count($totalAreas),
			'totalAreaMedian' => (count($totalAreas) >= self::FLOOR) ? self::getMedian($totalAreas) : NULL
		];

	}

}
