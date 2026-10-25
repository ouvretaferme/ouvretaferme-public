<?php
namespace series;

/**
 * Le décor du tableau de bord maraîchage : un groupe, ses fermes, leurs plans de culture et leurs
 * temps, posés par le test et non empruntés à la base.
 *
 * `VegetableDashboardAiTest` lisait un groupe de la copie de production par son identifiant. Les
 * identifiants d'une base de dev changent à chaque rechargement : le 21/09/2026, `6388` désignait
 * « La Ferme de Nolwenn », une ferme ordinaire, et les vingt-huit cas repartaient sur des tableaux
 * vides, rendant quarante-huit échecs et huit warnings qui accusaient le code au lieu du décor.
 *
 * ⚠️ **Semé hors de toute transaction, et c'est forcé.** `StructureLib::create()` émet un
 * `CREATE DATABASE`, qui valide implicitement la transaction ouverte ; un décor posé dans une
 * transaction disparaîtrait au `tearDown()` du premier cas. Le semis est donc rejouable : ce qui
 * existe se garde, ce qui manque se crée. Un premier lancement sur une base neuve sème tout, les
 * suivants ne font que vérifier. C'est le régime de `structure\CommunityFixtureAiTest`, dont ce
 * fichier reprend la forme.
 *
 * ⚠️ **Le décor est calibré sur les seuils de la Lib**, et son calibrage est ce qui donne du sens
 * aux cas : `FLOOR` fermes au-dessus de `SERIES_MINIMUM` pour que les valeurs s'affichent, et une
 * ferme en dessous pour que le seuil se voie écarter quelqu'un. Changer ces nombres sans relire les
 * cas les rend verts pour la mauvaise raison.
 *
 * Ce qui se lit sans être posé se limite à ce que la copie de production garantit : l'utilisateur 1,
 * propriétaire du décor et créateur de ses séries (`Series.createdBy` est un `SPECIAL(user)` non
 * nul, qu'aucune ligne de commande ne pose d'elle-même).
 */
abstract class VegetableDashboardFixtureAiTest extends \Test {

	/** Propriétaire du décor, et lecteur connecté pendant le semis */
	const USER = 1;

	const NAME_COMMUNITY = 'zzTest réseau maraîchage';

	const NAME_ZONE = 'Parcelle du décor';

	const NAME_PLOT_CLOSED = 'Jardin clos du décor';

	/**
	 * Les fermes du décor, et ce que chacune prouve.
	 *
	 * Trois au-dessus de `SERIES_MINIMUM`, ce qui est exactement `FLOOR` : une de moins et plus aucune
	 * valeur ne s'affiche, ce qui est le cas éprouvé par `testFewFarmsFallBelowFloor()`. La quatrième
	 * porte une seule série : elle a un plan de culture sans atteindre le seuil, et c'est elle qui
	 * fait la différence que `testPlanCountsFarmsAboveSeriesMinimum()` mesure.
	 */
	const FARMS = [
		'zzTest maraîchage nord' => 12,
		'zzTest maraîchage sud' => 11,
		'zzTest maraîchage est' => 10,
		'zzTest maraîchage ouest' => 1
	];

	/** Espèces du référentiel partagé reprises par chaque ferme, assez pour peupler les tranches d'espèces. */
	const SPECIES = 8;

	/**
	 * La ferme hors production du groupe, rattachée comme les autres.
	 *
	 * Un réseau ne compte que ses fermes en production : sans elle, le filtre serait vert faute
	 * d'avoir quoi que ce soit à écarter (`testNetworkCountsOnlyProductionFarms()`).
	 */
	const FARM_TRIAL = 'zzTest maraîchage à l\'essai';

	/**
	 * Les fermes qui portent aussi un plan sur la saison précédente, et combien de séries.
	 *
	 * Deux, soit une de moins que `FLOOR` : la saison existe donc dans les plans sans paraître dans
	 * la liste des saisons de l'écran, et c'est cet écart que `testSeasonsNeedFarmsAboveSeriesMinimum()`
	 * mesure. Elle sert aussi de saison passée au pied de page, qui doit lire sa propre date de calcul.
	 */
	const SEASON_PAST_FARMS = ['zzTest maraîchage nord' => 10, 'zzTest maraîchage sud' => 10];

	/**
	 * La SAU de la fiche système et la surface de l'atelier maraîchage, en hectares.
	 *
	 * ⚠️ Les deux médianes doivent différer : c'est le seul contrôle qui distingue la SAU de la
	 * surface de l'atelier, longtemps confondues sur la première planche (`testTotalAreaIsSystemSau()`).
	 */
	const DECLARED = [
		'zzTest maraîchage nord' => ['totalArea' => 12.0, 'area' => 2.0],
		'zzTest maraîchage sud' => ['totalArea' => 14.0, 'area' => 3.0],
		'zzTest maraîchage est' => ['totalArea' => 16.0, 'area' => 4.0],
		'zzTest maraîchage ouest' => ['totalArea' => 18.0, 'area' => 5.0]
	];

	/**
	 * L'atelier que la seule ferme du nord déclare, en plus du maraîchage.
	 *
	 * Sous le plancher, donc compté sans être nommé : sans lui, `testWorkshopsBelowFloorAreCountedNotNamed()`
	 * n'aurait rien à voir masquer.
	 */
	const WORKSHOP_BELOW_FLOOR = 'vigne';

	/**
	 * La catégorie de travail que la ferme s'est écrite, sans nom de référence.
	 *
	 * Les quatre catégories natives en portent un ; sans celle-ci, la ligne d'heures sans `fqn`
	 * n'existerait pas et la projection la rendrait verte faute de cas
	 * (`aggregate\VegetableProjectionAiTest::testHoursMatchLiveTables()`).
	 */
	const CATEGORY_OWN = 'zzTest catégorie propre';

	protected \structure\Structure $eStructureCommunity;

	/** Les fermes du décor, indexées par leur nom */
	protected array $structures = [];

	/** La raison du refus, quand le semis n'a pas pu aller au bout */
	private ?string $seedFailure = NULL;

	/** État à restaurer : le semis connecte un utilisateur dans un cache de processus. */
	private \user\User $eUserInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// ⚠️ Une exception qui sort de init() interrompt le fichier sans afficher de bilan, et le
		// pre-commit, qui ne trouve alors aucun « Passed: », prend le fichier pour non exécuté. Le
		// refus se garde donc, pour que chaque cas le rende en échec nommé par requireFixtures().
		try {
			$this->seed();
		} catch(\Throwable $e) {
			$this->seedFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	/** Le décor est-il en place ? Un cas qui répond non s'arrête là, en nommant ce qui manque. */
	protected function requireFixtures(): bool {

		return $this->assertTrue(
			$this->seedFailure === NULL,
			'le décor du tableau de bord est en place'.($this->seedFailure === NULL ? '' : ' : '.$this->seedFailure)
		);

	}

	private function seed(): void {

		$eUser = \user\UserLib::getById(self::USER, \user\User::getSelection());

		if($eUser->empty()) {
			throw new \Exception('utilisateur '.self::USER.' introuvable dans la copie de production');
		}

		\user\ConnectionLib::setOnline($eUser);

		$this->eStructureCommunity = $this->seedStructure(\structure\Structure::COMMUNITY, self::NAME_COMMUNITY, \structure\Structure::PRODUCER, $eUser);

		// La structure de groupe et sa ligne de groupe se créent l'une après l'autre : une création
		// interrompue entre les deux laisse une structure que `seedStructure()` retrouve par son nom,
		// sans rien derrière.
		if(\structure\CommunityLib::getByStructure($this->eStructureCommunity)->empty()) {
			\structure\CommunityLib::createForStructure($this->eStructureCommunity);
		}

		$this->seedAdmin($eUser, $this->eStructureCommunity);

		foreach(self::FARMS as $name => $series) {

			$eStructure = $this->seedStructure(\structure\Structure::PRODUCER, $name, \structure\Structure::REAL, $eUser);

			$this->seedAdmin($eUser, $eStructure);
			$this->seedAffiliation($this->eStructureCommunity, $eUser, $eStructure);

			$this->seedFarmPlan($eStructure, $name, $series);
			$this->seedDeclared($eStructure, $name);

			$this->structures[$name] = $eStructure;

		}

		$eStructureTrial = $this->seedStructure(\structure\Structure::PRODUCER, self::FARM_TRIAL, \structure\Structure::TEST, $eUser);

		$this->seedAdmin($eUser, $eStructureTrial);
		$this->seedAffiliation($this->eStructureCommunity, $eUser, $eStructureTrial);

		$this->structures[self::FARM_TRIAL] = $eStructureTrial;

		$this->seedProjection();

	}

	/**
	 * Les tables d'agrégat que l'écran lit, posées sur le décor.
	 *
	 * ⚠️ Les cas qui refont la projection dans leur transaction ne suffisent pas : celui qui éprouve
	 * le passage d'une année lit ce que les saisons passées ont laissé en ligne, et n'a rien à lire
	 * tant qu'aucune projection n'a couru sur ces fermes. `projectStructures()` ne touche qu'elles,
	 * et remplace ce qu'elle trouve, donc le semis reste rejouable.
	 */
	private function seedProjection(): void {

		$cStructure = new \Collection();

		foreach($this->structures as $eStructure) {
			$cStructure[$eStructure['id']] = $eStructure;
		}

		\aggregate\VegetableProjectionAiLib::projectStructures($cStructure);

	}

	/**
	 * Ce que la ferme déclare d'elle-même : sa SAU, son atelier maraîchage, et pour une seule un
	 * second atelier.
	 *
	 * Rejouable par la présence de l'atelier maraîchage, que `Production` tient en `UNIQUE (structure, fqn)`.
	 */
	private function seedDeclared(\structure\Structure $eStructure, string $name): void {

		$eSystem = \structure\SystemLib::getByStructure($eStructure);

		if($eSystem->empty()) {

			\structure\SystemLib::createForStructure($eStructure);

			$eSystem = \structure\SystemLib::getByStructure($eStructure);

		}

		if($eSystem['totalArea'] === NULL) {

			$eSystem['totalArea'] = self::DECLARED[$name]['totalArea'];

			\structure\SystemLib::update($eSystem, ['totalArea']);

		}

		$workshops = [\series\VegetableDashboardAiLib::WORKSHOP => self::DECLARED[$name]['area']];

		if($name === array_key_first(self::DECLARED)) {
			$workshops[self::WORKSHOP_BELOW_FLOOR] = NULL;
		}

		foreach($workshops as $fqn => $area) {

			if(\structure\Production::model()->whereStructure($eStructure)->whereFqn($fqn)->exists()) {
				continue;
			}

			\structure\ProductionLib::create(new \structure\Production([
				'structure' => $eStructure,
				'fqn' => $fqn,
				'area' => $area,
				'areaGreenhouse' => NULL,
				'mechanisation' => NULL,
				'organic' => \structure\Production::YES,
				'startedAt' => NULL,
				'description' => NULL,
				'status' => \structure\Production::ACTIVE
			]));

		}

	}

	/**
	 * Le plan de culture et les temps d'une ferme du décor.
	 *
	 * ⚠️ Les espèces sont celles du référentiel partagé, et c'est leur `reference` qui compte : la
	 * projection regroupe les cultures par `plant.reference`, et une espèce propre à la ferme, sans
	 * référence, ne paraîtrait sur aucune ligne d'espèce (`VegetableProjectionAiLib::getSpecies()`,
	 * `m2.reference IS NOT NULL`). Les mêmes références pour toutes les fermes, pour qu'une espèce
	 * soit cultivée par plusieurs d'entre elles.
	 */
	private function seedFarmPlan(\structure\Structure $eStructure, string $name, int $series): void {

		$season = (int)date('Y');

		$plants = $this->getPlants($eStructure);
		$plots = $this->seedGeography($eStructure, $season);

		$this->seedSeason($eStructure, $plants, $plots, $season, $series);

		if(array_key_exists($name, self::SEASON_PAST_FARMS)) {
			$this->seedSeason($eStructure, $plants, $plots, $season - 1, self::SEASON_PAST_FARMS[$name]);
		}

		// La saison à venir, sur une seule ferme et une seule série : la projection s'arrête à
		// l'année en cours, et sans elle rien ne le prouverait
		// (`aggregate\VegetableProjectionAiTest::testNoFutureSeason()`). Une série ne pèse pas assez
		// pour changer la liste des saisons de l'écran, qui ne retient que celles au plancher.
		if($name === array_key_first(self::FARMS)) {
			$this->seedSeason($eStructure, $plants, $plots, $season + 1, 1);
		}

		$this->seedHours($eStructure, $plants[0], $season);

	}

	/**
	 * Les parcelles de la ferme : une de plein champ, une sous abri, et une close.
	 *
	 * La projection sépare les deux premières surfaces par le mode de la parcelle, et les tranches
	 * de serre seraient toutes nulles sans la seconde. La troisième porte une planche active sous
	 * une parcelle fermée avant la saison : c'est le témoin de `testBedAreaReadsSeasonalPlots()`,
	 * qui n'a rien à distinguer si la saison de la parcelle n'écarte aucune planche.
	 *
	 * Rejouable par le nom de la zone, une ferme n'en portant qu'une.
	 *
	 * @return \map\Plot[] Les deux parcelles ouvertes, celles que le plan de culture occupe.
	 */
	private function seedGeography(\structure\Structure $eStructure, int $season): array {

		$eZone = \map\Zone::model()
			->select(\map\Zone::getSelection())
			->whereStructure($eStructure)
			->whereName(self::NAME_ZONE)
			->get();

		if($eZone->empty()) {

			$eZone = new \map\Zone([
				'name' => self::NAME_ZONE,
				'structure' => $eStructure,
				'area' => 20000,
				'coordinates' => NULL,
				'seasonFirst' => NULL,
				'seasonLast' => NULL,
			]);

			\map\ZoneLib::create($eZone);

		}

		$plots = [];

		foreach([\map\Plot::OPEN_FIELD, \map\Plot::GREENHOUSE] as $mode) {
			$plots[] = $this->seedPlot($eZone, 'Jardin '.$mode.' du décor', $mode, ($mode === \map\Plot::GREENHOUSE) ? 2000 : 8000, NULL);
		}

		$ePlotClosed = $this->seedPlot($eZone, self::NAME_PLOT_CLOSED, \map\Plot::OPEN_FIELD, 1000, $season - 3);

		// ⚠️ `plotFill` borne le garde : `PlotLib::create()` pose déjà sur chaque parcelle une planche
		// qui la remplit entièrement, et la lecture des surfaces l'écarte précisément par ce drapeau.
		if(\map\Bed::model()->wherePlot($ePlotClosed)->wherePlotFill(FALSE)->exists() === FALSE) {
			$this->seedBed($ePlotClosed, 'Close');
		}

		return $plots;

	}

	private function seedPlot(\map\Zone $eZone, string $name, string $mode, int $area, ?int $seasonLast): \map\Plot {

		$ePlot = \map\Plot::model()
			->select(\map\Plot::getSelection())
			->whereZone($eZone)
			->whereName($name)
			->get();

		if($ePlot->notEmpty()) {
			return $ePlot;
		}

		$ePlot = new \map\Plot([
			'name' => $name,
			'zone' => $eZone,
			'zoneFill' => FALSE,
			'mode' => $mode,
			'length' => NULL,
			'width' => NULL,
			'area' => $area,
			'coordinates' => NULL,
			'position' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => $seasonLast,
		]);

		\map\PlotLib::create($ePlot);

		return $ePlot;

	}

	/**
	 * Les séries d'une saison, leurs cultures et leurs emplacements.
	 *
	 * Rejouable par la présence d'une série de la saison : une ferme déjà semée n'est pas resemée,
	 * sinon chaque lancement doublerait ses chiffres.
	 *
	 * @param \plant\Plant[] $plants
	 * @param \map\Plot[] $plots
	 */
	private function seedSeason(\structure\Structure $eStructure, array $plants, array $plots, int $season, int $series): void {

		if(\series\Series::model()->whereStructure($eStructure)->whereSeason($season)->exists()) {
			return;
		}

		for($index = 0; $index < $series; $index++) {

			$eSeries = new \series\Series([
				'structure' => $eStructure,
				'sequence' => new \sequence\Sequence(),
				'name' => 'Série '.($index + 1).' du décor '.$season,
				'cycle' => \series\Series::ANNUAL,
				'use' => \series\Series::BLOCK,
				'mode' => \series\Series::OPEN_FIELD,
				'season' => $season,
				'plants' => 1,
				'areaTarget' => 500,
				'perennialFirst' => new \series\Series(),
				'duplicateOf' => new \series\Series(),
			]);

			\series\SeriesLib::create($eSeries);

			$ePlant = $plants[$index % count($plants)];

			$eCultivation = new \series\Cultivation([
				'structure' => $eStructure,
				'season' => $season,
				'series' => $eSeries,
				'sequence' => new \sequence\Sequence(),
				'crop' => new \sequence\Crop(),
				'plant' => $ePlant,
				'distance' => \series\Cultivation::SPACING,
				'rows' => NULL,
				'rowSpacing' => NULL,
				'plantSpacing' => NULL,
				'density' => NULL,
				'varietiesUnit' => \series\Cultivation::PERCENT,
				'varietiesTool' => new \work\Tool(),
				'mainUnit' => \series\Cultivation::KG,
				'harvestPeriodExpected' => \series\Cultivation::MONTH,
				// La dernière culture récolte en janvier de l'année suivante : une saison déborde sur
				// l'année civile, et la projection doit rendre ce mois à sa saison
				// (`aggregate\VegetableProjectionAiTest::testSpeciesMonthsStayInSeason()`).
				'harvestMonthsExpected' => ($index === $series - 1) ? [($season + 1).'-01'] : [$season.'-0'.(($index % 6) + 4)],
				'cSlice' => new \Collection(),
			]);

			\series\CultivationLib::create($eCultivation);

			$this->seedPlace($eSeries, $plots[$index % count($plots)], $season.'-'.($index + 1), $season);

		}

	}

	/**
	 * Un emplacement porteur de surface, sans lequel aucune tranche de surface n'existe.
	 *
	 * Sa planche lui est propre : `seriesPlace` porte un `UNIQUE (series, bed)`, et la surface que la
	 * projection somme est celle de l'emplacement, pas celle de la planche.
	 */
	private function seedPlace(\series\Series $eSeries, \map\Plot $ePlot, string $bed, int $season): void {

		$eBed = $this->seedBed($ePlot, $bed);

		$ePlace = new \series\Place([
			'structure' => $eSeries['structure'],
			'season' => $season,
			'series' => $eSeries,
			'task' => new \series\Task(),
			'zone' => $ePlot['zone'],
			'plot' => $ePlot,
			'bed' => $eBed,
			'length' => NULL,
			'width' => NULL,
			'area' => 500.0,
		]);

		\series\Place::model()->insert($ePlace);

	}

	/** Une planche de 500 m², la surface que `BED_AREA_MINIMUM` compare. */
	private function seedBed(\map\Plot $ePlot, string $name): \map\Bed {

		$eBed = new \map\Bed([
			'name' => $name,
			'plot' => $ePlot,
			'plotFill' => FALSE,
			'length' => 100,
			'width' => 500,
			'alleyWidth' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		\map\BedLib::create($eBed);

		return $eBed;

	}

	/**
	 * Du temps sur chacune des quatre catégories natives d'une ferme.
	 *
	 * Rejouable par la présence d'une feuille de temps : sans ce garde, chaque lancement doublerait
	 * les heures de toutes les fermes, et les cartes de travail rendraient des chiffres qui montent.
	 *
	 * La projection ne retient que le temps d'une intervention **catégorisée**
	 * (`VegetableProjectionAiLib::getHours()`, jointure sur `work\Category`) : une heure sans
	 * catégorie ne paraîtrait sur aucune carte.
	 */
	private function seedHours(\structure\Structure $eStructure, \plant\Plant $ePlant, int $season): void {

		if(\series\Timesheet::model()->whereStructure($eStructure)->exists()) {
			return;
		}

		$eUser = \user\ConnectionLib::getOnline();

		if(\work\Category::model()->whereStructure($eStructure)->whereName(self::CATEGORY_OWN)->exists() === FALSE) {

			\work\CategoryLib::create(new \work\Category([
				'name' => self::CATEGORY_OWN,
				'fqn' => NULL,
				'structure' => $eStructure
			]));

		}

		$cCategory = \work\Category::model()
			->select(\work\Category::getSelection())
			->whereStructure($eStructure)
			->getCollection();

		$eAction = \work\Action::model()
			->select(\work\Action::getSelection())
			->whereStructure($eStructure)
			->get();

		$hours = 4.0;

		foreach($cCategory as $eCategory) {

			$eTask = new \series\Task([
				'structure' => $eStructure,
				'series' => new \series\Series(),
				'cultivation' => new \series\Cultivation(),
				'plant' => $ePlant,
				'action' => $eAction,
				'category' => $eCategory,
				'status' => \series\Task::DONE,
				'doneWeek' => $season.'-W26',
				'doneDate' => $season.'-06-25',
				'repeatMaster' => new \series\Repeat(),
			]);

			\series\Task::model()->insert($eTask);

			$eTimesheet = new \series\Timesheet([
				'structure' => $eStructure,
				'user' => $eUser,
				'series' => new \series\Series(),
				'cultivation' => new \series\Cultivation(),
				'plant' => $ePlant,
				'task' => $eTask,
				'time' => $hours,
				'date' => $season.'-06-25',
				'week' => $season.'-W26',
			]);

			\series\Timesheet::model()->insert($eTimesheet);

			$hours += 2.0;

		}

	}

	/**
	 * Les espèces de la ferme qui portent une référence au référentiel partagé.
	 *
	 * ⚠️ Elles ne sont pas créées ici : `StructureLib::create()` sème déjà tout le référentiel dans
	 * la ferme neuve (169 espèces le 21/09/2026), chacune référencée. En créer d'autres ferait
	 * doublon de nom, et la projection ne regroupe que par `plant.reference`.
	 *
	 * @return \plant\Plant[]
	 */
	private function getPlants(\structure\Structure $eStructure): array {

		$cPlant = \plant\Plant::model()
			->select(['id', 'fqn', 'reference'])
			->whereStructure($eStructure)
			->where('reference IS NOT NULL')
			->sort(['fqn' => SORT_ASC])
			->limit(self::SPECIES)
			->getCollection();

		if($cPlant->count() < self::SPECIES) {
			throw new \Exception('la ferme '.$eStructure['id'].' porte '.$cPlant->count().' espèces référencées, '.self::SPECIES.' attendues');
		}

		$plants = [];

		foreach($cPlant as $ePlant) {
			$plants[] = $ePlant;
		}

		return $plants;

	}

	private function seedStructure(string $type, string $name, string $subType, \user\User $eUserOwner): \structure\Structure {

		$eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereName($name)
			->get();

		if($eStructure->notEmpty()) {
			return $eStructure;
		}

		$eStructure = new \structure\Structure(['type' => $type, 'owner' => $eUserOwner]);

		$propertyType = match($type) {
			\structure\Structure::COMMUNITY => 'communityType',
			\structure\Structure::PRODUCER => 'producerType'
		};

		$input = [
			'name' => $name,
			'isLegal' => 0,
			'legalEmail' => 'zztest+'.uniqid().'@ouvretaferme.org',
			// L'identifiant du pays et non son code : la colonne est un `?user\Country`.
			'legalCountry' => 75,
			'startedAt' => date('Y'),
			'organic' => \structure\Structure::NO,
			'qualities' => [],
			$propertyType => $subType
		];

		$fw = new \FailWatch();

		$eStructure->build(\structure\StructureLib::getPropertiesCreate()($eStructure), $input, new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création de « '.$name.' » refusée : '.json_encode($fw->get())));

		\structure\StructureLib::create($eStructure);

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($eStructure['id'])
			->get();

	}

	/** Remet aussi d'aplomb une adhésion sortie ou sans rôle, que `MemberLib::create()` refuserait en doublon. */
	private function seedAdmin(\user\User $eUser, \structure\Structure $eStructure): void {

		$eMember = \structure\Member::model()
			->select(\structure\Member::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->get();

		$eRole = \structure\RoleLib::getOne($eStructure, 'admin');

		if($eMember->empty()) {

			$fw = new \FailWatch();

			\structure\MemberLib::create(new \structure\Member([
				'user' => $eUser,
				'structure' => $eStructure,
				'role' => $eRole,
				'status' => \structure\Member::IN
			]));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('adhésion de l\'utilisateur '.$eUser['id'].' à la ferme '.$eStructure['id'].' refusée : '.json_encode($fw->get())));

			return;

		}

		if($eMember['status'] !== \structure\Member::IN or $eMember['role']->is($eRole) === FALSE) {

			$eMember['status'] = \structure\Member::IN;
			$eMember['role'] = $eRole;

			\structure\MemberLib::update($eMember, ['status', 'role']);

		}

	}

	private function seedAffiliation(\structure\Structure $eStructureCommunity, \user\User $eUser, \structure\Structure $eStructureProducer): void {

		$eAffiliation = \structure\Affiliation::model()
			->select(['id', 'user', 'shareProduction'])
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->get();

		if($eAffiliation->empty()) {

			$fw = new \FailWatch();

			\structure\AffiliationAiLib::create(new \structure\Affiliation([
				'user' => $eUser,
				'structureProducer' => $eStructureProducer,
				'structureCommunity' => $eStructureCommunity,
				'contributesQuotation' => TRUE
			]));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('rattachement de la ferme '.$eStructureProducer['id'].' au groupe '.$eStructureCommunity['id'].' refusé : '.json_encode($fw->get())));

		}

	}

}
?>
