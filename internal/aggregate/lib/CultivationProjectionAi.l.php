<?php
namespace aggregate;

/**
 * Reconstruit les tables de la bibliothèque de cultures depuis les tables vives.
 *
 * La page agrégeait jusqu'ici les cultures de toutes les structures à chaque
 * affichage : 11 requêtes, 1 086 ms de SQL et 1 120 981 lignes lues, dont un
 * parcours complet de `seriesTask`. Rien dans ce calcul ne dépendait de l'instant,
 * il est donc fait une fois par nuit.
 *
 * Le partage des rôles avec CultivationCropPlanAiLib est strict, et c'est lui qui protège de
 * la dérive : aucune règle n'est écrite des deux côtés. Ici les règles qui ne sont
 * pas des boutons — semaines aberrantes, séries pérennes, fermes fermées, saisons à
 * venir, rattachement variétal. Là-bas tout ce qui se règle — paliers d'altitude,
 * seuil de séries, bornes d'écart de pépinière, seuils d'affichage — appliqué aux
 * colonnes brutes que cette classe dépose.
 */
class CultivationProjectionAiLib {

	// Table semaine → mois, calculée une fois. Elle ne dépend que du calendrier.
	private static ?array $monthByWeek = NULL;

	/**
	 * Reconstruit la projection sans jamais interrompre la page.
	 *
	 * La nouvelle version s'écrit à côté de l'ancienne, en `online = FALSE`, et ne
	 * prend sa place qu'une fois complète. Tant qu'elle se construit — trente-cinq
	 * secondes, mais rien ne garantit que ça ne s'allongera pas — la page continue de
	 * servir la précédente, entière et cohérente. C'est ce qui permet d'écrire espèce
	 * par espèce sans que personne ne voie jamais une table à moitié refaite.
	 *
	 * Un passage interrompu ne laisse donc aucune trace visible : la version en ligne
	 * n'a pas bougé, et la préparation inachevée est jetée au début du passage suivant.
	 */
	public static function project(): array {

		$cPlantReference = self::getPlantReference();

		if($cPlantReference->empty()) {
			return ['plants' => 0, 'cultivations' => 0, 'varieties' => 0];
		}

		// Reliquat d'un passage interrompu : une version que personne n'a jamais servie
		// et qui ne décrit plus l'état des tables vives.
		CultivationVarietyAiLib::deleteOffline();
		CultivationAiLib::deleteOffline();

		// Chaque structure possède sa copie des interventions de référence : le fqn est
		// le seul identifiant commun. Une fois pour toute la boucle, jamais par espèce.
		$seedlingActions = array_flip(
			\work\Action::model()
				->select(['id'])
				->where('fqn', \ACTION_SEMIS_PEPINIERE)
				->getColumn('id')
		);

		$structures = self::getStructures();

		$totals = ['plants' => 0, 'cultivations' => 0, 'varieties' => 0];

		foreach($cPlantReference as $ePlantReference) {

			$counts = self::projectPlant($ePlantReference, $seedlingActions, $structures);

			$totals['plants']++;
			$totals['cultivations'] += $counts['cultivations'];
			$totals['varieties'] += $counts['varieties'];

		}

		self::publish();

		return $totals;

	}

	/**
	 * Met la version préparée en ligne à la place de la précédente.
	 *
	 * Sous transaction, parce que les deux mouvements sont indissociables : entre la
	 * suppression de l'ancienne et la promotion de la nouvelle, la page n'aurait rien
	 * à afficher. Une transaction unique fait passer les lecteurs d'une version
	 * complète à l'autre sans intervalle.
	 *
	 * Les deux tables vivent sur le même serveur, une seule transaction les couvre.
	 */
	protected static function publish(): void {

		Cultivation::model()->beginTransaction();

			CultivationVarietyAiLib::deleteOnline();
			CultivationAiLib::deleteOnline();

			CultivationAiLib::publish();
			CultivationVarietyAiLib::publish();

		Cultivation::model()->commit();

	}

	/**
	 * Les espèces de référence servies par la bibliothèque.
	 *
	 * Le référentiel mêle légumes, fruits, fleurs et grandes cultures ; les créneaux
	 * et la pépinière ne décrivent que la conduite maraîchère. Une espèce pérenne
	 * s'implante une fois pour plusieurs années et n'a pas de créneau d'implantation
	 * dans la saison.
	 */
	protected static function getPlantReference(): \Collection {

		return \plant\Plant::model()
			->select(['id', 'fqn'])
			->where('structure IS NULL')
			->where('fqn IS NOT NULL')
			->whereUsageVegetable(TRUE)
			->whereCycle(\plant\Plant::ANNUAL)
			->whereStatus(\plant\Plant::ACTIVE)
			->getCollection();

	}

	/**
	 * Ce qu'il faut savoir de chaque structure, en une fois.
	 *
	 * Deux réglages qu'on ne cuit pas, l'altitude brute et le nombre de séries,
	 * voyagent jusqu'à la table de faits et se filtrent à la lecture. Deux critères
	 * tranchent ici : une ferme fermée décrit une pratique qui n'a plus cours, et
	 * une ferme qui n'a pas consenti au partage de ses données ne contribue pas à
	 * l'agrégat, si bien qu'aucune ligne n'a à porter ce consentement.
	 */
	protected static function getStructures(): array {

		$cStructure = \structure\Structure::model()
			->select(['id'])
			->whereStatus(\structure\Structure::ACTIVE)
			->whereConsentData(TRUE)
			->whereId('IN', \structure\StructureLib::getInstalledSql())
			->getCollection(index: 'id');

		if($cStructure->empty()) {
			return [];
		}

		$cSeries = \series\Series::model()
			->select([
				'structure',
				'total' => new \Sql('COUNT(*)', 'int'),
			])
			->group('structure')
			->getCollection();

		$series = [];

		foreach($cSeries as $eSeries) {
			$series[$eSeries['structure']['id']] = $eSeries['total'];
		}

		$cSystem = \structure\System::model()
			->select(['structure', 'altitude'])
			->whereAltitude('!=', NULL)
			->getCollection();

		$altitudes = [];

		foreach($cSystem as $eSystem) {
			$altitudes[$eSystem['structure']['id']] = $eSystem['altitude'];
		}

		$structures = [];

		foreach($cStructure as $eStructure) {

			$structures[$eStructure['id']] = [
				'series' => $series[$eStructure['id']] ?? 0,
				'altitude' => $altitudes[$eStructure['id']] ?? NULL,
			];

		}

		return $structures;

	}

	/**
	 * Reconstruit les lignes d'une espèce.
	 */
	protected static function projectPlant(\plant\Plant $ePlantReference, array $seedlingActions, array $structures): array {

		$cCultivationSource = self::getCultivationByPlant($ePlantReference, $structures);

		if($cCultivationSource->empty()) {
			return ['cultivations' => 0, 'varieties' => 0];
		}

		$seriesByCultivation = self::getSeriesByCultivation($cCultivationSource);
		$seedlings = self::getSeedlingIndexByCultivation($cCultivationSource, $seedlingActions);

		$cCultivation = new \Collection();

		foreach($cCultivationSource as $eCultivationSource) {

			// La conduite est portée par la série. Une culture dont la série n'a pas été
			// retenue — série pérenne — n'est pas arrivée jusqu'ici.
			if(isset($seriesByCultivation[$eCultivationSource['id']]) === FALSE) {
				continue;
			}

			$eSeries = $seriesByCultivation[$eCultivationSource['id']];
			$structure = $structures[$eCultivationSource['structure']['id']];

			// La semaine est ramenée dans l'année : une semaine 53, qui n'existe qu'une
			// année sur cinq ou six, est repliée sur la 52 et finit en décembre plutôt
			// que d'écarter la culture.
			$startWeek = min(52, $eCultivationSource['startWeek']);

			[$harvestWeeks, $indexes] = self::getHarvest($eCultivationSource);

			$cCultivation[] = new Cultivation([
				'plant' => $ePlantReference,
				'seriesCultivation' => $eCultivationSource['id'],
				'structure' => $eCultivationSource['structure'],
				'season' => $eCultivationSource['season'],
				'startAction' => $eCultivationSource['startAction'],
				'seedling' => $eCultivationSource['seedling'],
				'mode' => $eSeries['mode'],
				// La conduite, recopiée telle quelle des deux côtés dont elle vient : la
				// planche et son passe-pied sont portés par la série, les rangs et leur
				// écartement par la culture. La lecture les regroupe et en tire ses
				// médianes ; aucun seuil n'est appliqué ici.
				'use' => $eSeries['use'],
				'bedWidth' => $eSeries['bedWidth'],
				'alleyWidth' => $eSeries['alleyWidth'],
				'rows' => $eCultivationSource['rows'],
				'rowSpacing' => $eCultivationSource['rowSpacing'],
				'plantSpacing' => $eCultivationSource['plantSpacing'],
				'density' => $eCultivationSource['density'],
				'startWeek' => $startWeek,
				'month' => self::getMonth($startWeek),
				'seedlingIndex' => $seedlings[$eCultivationSource['id']] ?? NULL,
				'harvestPeriod' => $eCultivationSource['harvestPeriodExpected'],
				// Bornes brutes : c'est la lecture qui décide si une récolte déclarée au
				// mois a le droit de dater quoi que ce soit.
				'harvestFrom' => $indexes ? min($indexes) : NULL,
				'harvestTo' => $indexes ? max($indexes) : NULL,
				'harvestCount' => $indexes ? count($indexes) : NULL,
				'harvestWeeks' => $harvestWeeks,
				// Une densité, par unité de surface : c'est ce qui la rend comparable d'une
				// structure à l'autre, et ce qui interdit d'en lire une quantité sans la
				// surface de la série, qui reste dans la culture source.
				'yieldExpected' => $eCultivationSource['yieldExpected'],
				// L'unité qualifie le rendement : un objectif ne s'agrège qu'avec ceux
				// qui se comptent dans la même.
				'mainUnit' => $eCultivationSource['mainUnit'],
				'altitude' => $structure['altitude'],
				'structureSeries' => $structure['series'],
				// Hors ligne tant que la reconstruction n'est pas terminée.
				'online' => FALSE,
			]);

		}

		// Les variétés pendent des lignes de faits par leur identifiant, qui n'est connu
		// qu'une fois la ligne écrite : les faits d'abord, les variétés ensuite.
		CultivationAiLib::createByPlant($cCultivation);

		$cCultivationVariety = self::getVariety($ePlantReference, $cCultivationSource);

		CultivationVarietyAiLib::createByPlant($cCultivationVariety);

		return [
			'cultivations' => $cCultivation->count(),
			'varieties' => $cCultivationVariety->count(),
		];

	}

	/**
	 * Les cultures d'une espèce sur toutes les structures.
	 *
	 * Les semaines hors 1-53 sont des saisies aberrantes (on trouve des semaines
	 * négatives et des semaines au-delà de 100) : elles fausseraient toutes les
	 * médianes. Les saisons à venir sont écartées de la même façon, ce sont des plans
	 * en cours de rédaction et non des pratiques constatées. Une culture sans conduite
	 * déclarée ne se range dans aucun des deux itinéraires.
	 */
	protected static function getCultivationByPlant(\plant\Plant $ePlantReference, array $structures): \Collection {

		// Chaque structure possède sa propre ligne espèce et toutes celles qui
		// partagent un fqn décrivent la même plante.
		$plantIds = \plant\Plant::model()
			->select(['id'])
			->where('fqn', $ePlantReference['fqn'])
			->getColumn('id');

		if($plantIds === []) {
			return new \Collection();
		}

		$cCultivationSource = \series\Cultivation::model()
			->select([
				'id', 'structure', 'series', 'season',
				'startWeek', 'startAction', 'seedling',
				'harvestPeriodExpected', 'harvestWeeksExpected',
				'yieldExpected', 'mainUnit',
				'rows', 'rowSpacing', 'plantSpacing', 'density',
			])
			->wherePlant('IN', $plantIds)
			->whereStartWeek('BETWEEN', new \Sql('1 AND 53'))
			->whereStartAction('!=', NULL)
			->whereSeason('<=', \Clock::year())
			->getCollection(index: 'id');

		return $cCultivationSource->find(fn($eCultivationSource) => isset($structures[$eCultivationSource['structure']['id']]));

	}

	/**
	 * La série de chaque culture, indexée par identifiant de culture.
	 *
	 * La conduite — le mode, la planche, le passe-pied — est portée par la série et
	 * non par la culture. Les séries pérennes sont écartées ici : une série pérenne —
	 * asperge, rhubarbe — s'implante une fois et produit pendant des années, sa
	 * semaine de départ ne désigne pas un créneau de la saison en cours. Une culture
	 * dont la série manque à l'appel n'est donc pas projetée, et c'est cette absence
	 * que lit la garde de projectPlant().
	 */
	protected static function getSeriesByCultivation(\Collection $cCultivationSource): array {

		$cSeries = \series\Series::model()
			->select(['id', 'mode', 'use', 'bedWidth', 'alleyWidth'])
			->whereId('IN', $cCultivationSource->getColumnCollection('series')->getIds())
			->whereCycle(\series\Series::ANNUAL)
			->getCollection(index: 'id');

		$seriesByCultivation = [];

		foreach($cCultivationSource as $eCultivationSource) {

			if($cSeries->offsetExists($eCultivationSource['series']['id'])) {
				$seriesByCultivation[$eCultivationSource['id']] = $cSeries[$eCultivationSource['series']['id']];
			}

		}

		return $seriesByCultivation;

	}

	/**
	 * La semaine de semis en pépinière de chaque culture, en index continu.
	 *
	 * Elle ne se lit nulle part sur la culture : elle est portée par l'intervention de
	 * semis en pépinière qui lui est rattachée.
	 *
	 * ⚠ L'action ne se filtre pas en SQL. Les identifiants de l'intervention de
	 * référence se comptent par milliers — une copie par structure — et un
	 * `action IN (…)` posé en second membre de l'index `(cultivation, action)` fait
	 * renoncer MySQL à l'accès par intervalle : mesuré, `EXPLAIN` passe de
	 * `type: range` sur 8 700 lignes à `type: index` sur 505 236, soit la table
	 * entière, 614 ms au lieu de 7. Les tâches se lisent donc sur la seule culture et
	 * se trient ici.
	 */
	protected static function getSeedlingIndexByCultivation(\Collection $cCultivationSource, array $seedlingActions): array {

		// Un plant acheté n'a pas de pépinière : c'est ce qui distingue les deux
		// itinéraires. Une culture déclarée en plant acheté qui porte quand même une
		// intervention de semis est une saisie contradictoire — une sur cent, mesuré.
		$cCultivationSourceSeedling = $cCultivationSource->find(fn($eCultivationSource) => $eCultivationSource['seedling'] === \series\Cultivation::YOUNG_PLANT);

		if($cCultivationSourceSeedling->empty() or $seedlingActions === []) {
			return [];
		}

		$cTask = \series\Task::model()
			->select(['id', 'cultivation', 'action', 'plannedWeek', 'doneWeek'])
			->whereCultivation('IN', $cCultivationSourceSeedling->getIds())
			->getCollection();

		$indexes = [];

		foreach($cTask as $eTask) {

			if(isset($seedlingActions[$eTask['action']['id']]) === FALSE) {
				continue;
			}

			// Le fait prime sur le prévu ; et quand une culture porte plusieurs semis
			// échelonnés, c'est le premier qui date la pépinière.
			$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'];

			if($week === NULL) {
				continue;
			}

			$cultivationId = $eTask['cultivation']['id'];

			if($cCultivationSourceSeedling->offsetExists($cultivationId) === FALSE) {
				continue;
			}

			$season = $cCultivationSourceSeedling[$cultivationId]['season'];

			// Index continu relatif à la saison, négatif quand la pépinière a eu lieu
			// l'année civile précédente — le cas de tout ce qui se plante en hiver. Un
			// numéro de semaine nu rendait la semaine 45 « postérieure » à une plantation
			// en semaine 6, et la culture était purement et simplement écartée du calcul.
			$index = min(52, week_number($week)) + 52 * (week_year($week) - $season);

			$indexes[$cultivationId] = min($indexes[$cultivationId] ?? $index, $index);

		}

		return $indexes;

	}

	/**
	 * Les semaines de récolte d'une culture, sur les deux axes dont on a besoin.
	 *
	 * Les semaines sont stockées en `YYYY-Www` et débordent sur l'année civile
	 * suivante pour une culture tardive. Pour mesurer une durée il faut donc les
	 * remettre à plat sur un axe continu, tandis que pour les placer sur un calendrier
	 * c'est bien le numéro de semaine qui compte.
	 *
	 * La récolte est lue sur `harvestWeeksExpected`, le prévisionnel, et jamais sur
	 * `harvestWeeks`, le réalisé. Ce n'est pas un choix par défaut : le réalisé n'est
	 * rempli que par une ferme sur dix et il l'est trop inégalement pour être comparé
	 * d'une ferme à l'autre. Mesuré sur les cultures qui portent les deux, le
	 * prévisionnel situe le début de récolte sans biais (médiane 0 à 1 semaine d'écart)
	 * mais surestime la durée de 3 à 12 semaines selon l'espèce — les mêmes bornes pour
	 * tout le monde valent mieux qu'un mélange de deux sources aux biais différents.
	 */
	protected static function getHarvest(\series\Cultivation $eCultivationSource): array {

		$weeks = [];
		$indexes = [];

		foreach(($eCultivationSource['harvestWeeksExpected'] ?: []) as $week) {

			$number = week_number($week);
			$year = week_year($week);

			if($number === NULL or $year === NULL) {
				continue;
			}

			// Le calendrier n'a que 52 colonnes : une semaine 53 est repliée sur la
			// dernière.
			$weeks[min(52, $number)] = min(52, $number);
			$indexes[] = $number + 52 * ($year - $eCultivationSource['season']);

		}

		return [array_values($weeks), $indexes];

	}

	/**
	 * Les parts variétales des cultures d'une espèce.
	 *
	 * Chaque structure nomme ses variétés à sa façon : « Lingodor », « Lingodor F1 »
	 * et « lingodor » sont la même semence. Le regroupement les réunit sous leur
	 * variété de référence, et c'est son nom arbitré qui est déposé ici.
	 *
	 * Une variété sans référence n'entre pas dans la table : le référentiel a été
	 * arbitré à la main, et ce qu'il n'a pas retenu n'est pas une variété qui lui
	 * aurait échappé — ce sont des types (« hollandais »), des couleurs (« jaune »),
	 * des essais d'une saison.
	 */
	protected static function getVariety(\plant\Plant $ePlantReference, \Collection $cCultivationSource): \Collection {

		// Les lignes de faits viennent d'être écrites : leurs identifiants sont ce qui
		// relie une part variétale à sa culture. Hors ligne, donc — celles qui sont en
		// ligne appartiennent à la version que la page sert encore.
		$cCultivation = CultivationAiLib::getOfflineByPlant($ePlantReference);

		$facts = [];

		foreach($cCultivation as $eCultivation) {
			$facts[$eCultivation['seriesCultivation']] = $eCultivation;
		}

		if($facts === []) {
			return new \Collection();
		}

		$cSlice = \series\Slice::model()
			->select([
				'cultivation', 'structure',
				'variety' => ['id', 'reference' => ['id', 'name']],
			])
			->whereCultivation('IN', $cCultivationSource->getIds())
			->whereVariety('NOT IN', [\plant\PlantSetting::VARIETY_UNKNOWN, \plant\PlantSetting::VARIETY_MIX])
			->getCollection();

		$cCultivationVariety = new \Collection();

		foreach($cSlice as $eSlice) {

			$cultivationId = $eSlice['cultivation']['id'];

			if(isset($facts[$cultivationId]) === FALSE) {
				continue;
			}

			$eVarietyReference = $eSlice['variety']['reference'];

			if($eVarietyReference->empty()) {
				continue;
			}

			$cCultivationVariety[] = new CultivationVariety([
				'cultivation' => $facts[$cultivationId],
				'plant' => $ePlantReference,
				'structure' => $eSlice['structure'],
				'variety' => $eVarietyReference,
				'name' => $eVarietyReference['name'],
				'searchKey' => CultivationVarietyAiLib::getSearchKey($eVarietyReference['name']),
				'online' => FALSE,
			]);

		}

		return $cCultivationVariety;

	}

	/**
	 * Le mois dans lequel tombe une semaine ISO, au sens ISO : celui de son jeudi.
	 *
	 * Publique parce que la page gradue son axe avec elle : les mois stockés dans la
	 * table de faits ont été calculés ici, l'axe qui les affiche doit venir de la même
	 * table, sans quoi une culture pourrait tomber dans une colonne voisine de la
	 * sienne.
	 */
	public static function getMonth(int $week): int {

		return self::getMonthByWeek()[$week];

	}

	/**
	 * Le mois de chacune des 52 semaines, la même table pour toutes les saisons.
	 *
	 * Le découpage ISO d'une année sur l'autre décale les frontières de quelques
	 * jours : la semaine 18 tombe en avril une année et en mai la suivante. Attribuer
	 * chaque culture d'après sa propre saison, mais graduer l'axe d'après l'année en
	 * cours, faisait déborder un créneau sur le mois voisin.
	 *
	 * La table ne dépend que du calendrier, jamais de la structure connectée : la
	 * mémoïser est sans risque.
	 */
	private static function getMonthByWeek(): array {

		if(self::$monthByWeek === NULL) {

			$year = \Clock::year();
			self::$monthByWeek = [];

			for($week = 1; $week <= 52; $week++) {
				$thursday = week_date_day($year.'-W'.str_pad((string)$week, 2, '0', STR_PAD_LEFT), 4);
				self::$monthByWeek[$week] = date_month($thursday);
			}

		}

		return self::$monthByWeek;

	}

}
?>
