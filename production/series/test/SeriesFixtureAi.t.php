<?php
namespace series;

/**
 * Le décor des tests du paquet series, monté par le test et non emprunté à la base.
 *
 * Les tests du paquet lisaient la ligne de temps la plus récente de la structure 7, la première
 * intervention en retard de n'importe quelle structure, trois interventions d'une mise en culture
 * réelle : chaque rechargement de la base depuis la production et chaque saisie du jour dans le
 * produit changeaient ce qu'ils mesuraient. Le 16/09, une ligne de temps du jour sur la structure 7
 * a fait passer une répartition de 3 h à 3,25 h. Ils posent désormais leur espèce, leur série, leur
 * mise en culture, leurs interventions et leurs temps ici, dans la transaction de la méthode, que
 * le `tearDown()` emporte.
 *
 * Ce qui reste lu est le décor de la structure hôte, et il se lit par ce qui l'identifie, jamais par
 * un identifiant : ses actions (`fqn` `plantation`, `recolte`...), sa catégorie de culture, son équipe.
 * Une pièce absente se déclare par `requireXxx()`, et le cas s'arrête là.
 *
 * Toutes les tables du paquet vivent dans la base commune, comme celles du parcellaire (`map`), des
 * itinéraires (`sequence`) et des variétés (`plant`) : une seule transaction, sur n'importe quel
 * modèle de cette connexion, couvre l'espèce, la variété, la série, la culture, ses parts, l'intervention,
 * le temps, la récolte, l'emplacement et l'itinéraire.
 *
 * Les témoins d'une autre structure (`createXxxForeign()`) se posent par le modèle et non par le Lib :
 * un Lib recalcule la série, l'espèce ou l'itinéraire de la structure connectée, et un témoin n'a
 * besoin que d'une ligne portant une autre structure, que la borne de structure doit écarter. Leurs
 * délégués non nullables (parcelle, jardin, planche, intervention) sont posés de la même façon, pour
 * que la ligne soit cohérente si un écran la lit.
 *
 * Le créateur d'une série, d'une culture ou d'une intervention est l'utilisateur en ligne
 * (`SPECIAL(user)`), qu'aucune session ne pose en ligne de commande : `setUp()` met en ligne le
 * membre du décor, comme le ferait une session du produit.
 */
abstract class SeriesFixtureAiTest extends \dev\ApiTest {

	/** Les couches du décor de la méthode en cours, vidées à chaque cas : `\Test` garde l'objet d'une méthode à l'autre. */
	private array $fixtures = [];

	protected function getTransactionModel(): \ModuleModel {
		return new TaskModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->fixtures = [];

		$eUser = $this->getMember();

		if($eUser->notEmpty()) {
			\user\ConnectionLib::setOnline($eUser);
		}

	}

	/**
	 * Le rollback ne défait pas la mémoïsation de `conf()` : un cas qui allume la RH (`enableHr()`) la
	 * laisserait allumée pour les suivants.
	 */
	public function tearDown(): void {

		parent::tearDown();

		\structure\FeatureLib::forget($this->eStructure);

	}

	/**
	 * La RH allumée dans la transaction du cas : sans elle, le planning ne lit aucune absence.
	 */
	protected function enableHr(): void {

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

	}

	/** Monte une couche au premier appel et la rend telle quelle ensuite : les cas qui la lisent plusieurs fois parlent de la même. */
	protected function once(string $key, \Closure $build): mixed {

		return $this->fixtures[$key] ??= $build();

	}

	/** La saison courante : une série se pose dans l'année de ses interventions. */
	protected function getSeason(): int {

		return (int)date('Y');

	}

	/**
	 * L0 · Un membre de l'équipe de la structure, le premier par ordre de rôle et de nom : c'est lui
	 * qui saisit les temps. L'équipe est un décor de la structure, elle ne se pose pas.
	 */
	protected function getMember(): \user\User {

		return $this->once('member', function() {

			$cUser = \structure\MemberLib::getUsersByStructure($this->eStructure);

			return $cUser->empty() ? new \user\User() : $cUser->first();

		});

	}

	protected function requireMember(): bool {

		return $this->assertTrue($this->getMember()->notEmpty(), 'la structure '.static::STRUCTURE.' a une équipe, pour saisir un temps');

	}

	/** L0 bis · Une action de la structure, par son `fqn` : un décor de la structure, jamais un identifiant. */
	protected function getAction(string $fqn): \work\Action {

		return $this->once('action-'.$fqn, fn() => \work\ActionLib::getByStructure($this->eStructure, fqn: $fqn));

	}

	protected function requireAction(string $fqn): bool {

		return $this->assertTrue($this->getAction($fqn)->notEmpty(), 'la structure '.static::STRUCTURE.' a une action « '.$fqn.' » active');

	}

	protected function getCategoryCulture(): \work\Category {

		return $this->once('categoryCulture', fn() => \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_CULTURE));

	}

	/** L1 · Une espèce propre à la structure, pour que rien du référentiel partagé ne pèse sur les cas. */
	protected function createPlant(): \plant\Plant {

		$this->stopOnClosedTransaction('avant createPlant()');

		$ePlant = new \plant\Plant([
			'name' => 'Espèce du décor série '.uniqid(),
			'structure' => $this->eStructure,
			'cycle' => \plant\Plant::ANNUAL,
		]);

		\plant\PlantLib::create($ePlant);

		return $ePlant;

	}

	protected function getPlant(): \plant\Plant {

		return $this->once('plant', fn() => $this->createPlant());

	}

	/**
	 * L2 · Une série de la saison, en plein champ, sur une planche : la forme la plus simple que le produit crée.
	 * Une autre saison se passe en second argument, pour un décor qui range les séries par saison.
	 */
	protected function createSeries(string $name = 'Série du décor', ?int $season = NULL): Series {

		$this->stopOnClosedTransaction('avant createSeries()');

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => $name,
			'cycle' => Series::ANNUAL,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $season ?? $this->getSeason(),
			'plants' => 1,
			'areaTarget' => 100,
			'perennialFirst' => new Series(),
			'duplicateOf' => new Series(),
		]);

		SeriesLib::create($eSeries);

		return $eSeries;

	}

	protected function getSeries(): Series {

		return $this->once('series', fn() => $this->createSeries());

	}

	/**
	 * L3 · Une mise en culture de l'espèce du décor dans la série : l'élément est construit à la main
	 * parce que `CultivationLib::create()` attend une série et une saison que seul l'écran pose.
	 */
	protected function createCultivation(Series $eSeries = new Series(), \plant\Plant $ePlant = new \plant\Plant()): Cultivation {

		$this->stopOnClosedTransaction('avant createCultivation()');

		$eCultivation = new Cultivation([
			'structure' => $this->eStructure,
			'season' => $this->getSeason(),
			'series' => $eSeries->notEmpty() ? $eSeries : $this->getSeries(),
			'sequence' => new \sequence\Sequence(),
			'crop' => new \sequence\Crop(),
			'plant' => $ePlant->notEmpty() ? $ePlant : $this->getPlant(),
			'distance' => Cultivation::SPACING,
			'rows' => NULL,
			'rowSpacing' => NULL,
			'plantSpacing' => NULL,
			'density' => NULL,
			'varietiesUnit' => Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'mainUnit' => Cultivation::KG,
			'harvestPeriodExpected' => Cultivation::MONTH,
			'cSlice' => new \Collection(),
		]);

		CultivationLib::create($eCultivation);

		$this->stopOnClosedTransaction('après CultivationLib::create(), sur la série '.$eCultivation['series']['id'].' et l\'espèce '.$eCultivation['plant']['id']);

		// `CultivationLib::create()` ajoute une culture à une série qui en compte déjà, et augmente `plants` d'un ;
		// le produit crée la première avec la série (`SeriesLib::createWithCultivations()`), le décor après elle,
		// donc le compte se reprend ici sur les cultures réelles, que « Partagé » lit (`Series::plants`).
		$eCultivation['series']['plants'] = Cultivation::model()
			->whereSeries($eCultivation['series'])
			->count();

		SeriesLib::update($eCultivation['series'], ['plants']);

		return $eCultivation;

	}

	/**
	 * Arrête le cas quand la transaction ouverte par `setUp()` n'est plus ouverte, avant que le décor
	 * n'écrive hors d'elle. Un `rollBack()` remet la pile à zéro quel que soit l'étage
	 * (`ModuleModel::rollBack()`) : celui d'un Lib qui refuse une écriture (`CultivationLib::create()` sur
	 * `UNIQUE (series, plant)` ou sur une part variétale refusée) ou d'une action refusée appelée par le cas
	 * annule la transaction du test avec la sienne, et tout ce qui s'écrit ensuite part en autocommit dans
	 * la base partagée, sans qu'aucun `tearDown()` puisse le reprendre. Le refus est muet : la culture
	 * rendue n'a pas d'identifiant, ou en porte un qui ne désigne plus rien. Une instruction DDL valide de
	 * même la transaction sans toucher à la pile, d'où la lecture de la connexion en plus.
	 * Appelée en tête de chaque écriture du décor, et juste après `CultivationLib::create()`, seul Lib du
	 * décor qui fasse `rollBack()`, pour que la culture refusée n'aille pas plus loin.
	 * Vu le 2026-10-01 : trois lancements de `Fingerprint::testCrate` (L4b), qui posait deux cultures sur
	 * la série et l'espèce du décor, ont laissé trois interventions orphelines en 2026-W39 de la
	 * structure 7, et 52 échecs dans le planning.
	 * Elle lève au lieu de rendre un booléen comme les `require*()` : la méthode du cas, ou le helper qui
	 * appelle le décor, continuerait d'écrire après un `return`.
	 */
	protected function stopOnClosedTransaction(string $where): void {

		$mTransaction = $this->getTransactionModel();

		if($mTransaction->inTransaction() === FALSE or $mTransaction->pdo()->inTransaction() === FALSE) {
			throw new \Exception('La transaction ouverte par setUp() n\'est plus ouverte '.$where.' (un rollBack() ou une instruction DDL l\'a refermée) : le cas s\'arrête, rien ne s\'écrit plus hors transaction.');
		}

	}

	protected function getCultivation(): Cultivation {

		return $this->once('cultivation', fn() => $this->createCultivation());

	}

	/**
	 * L4 · Une intervention de culture sur la mise en culture, à faire une semaine donnée ou faite un
	 * jour donné. L'action se lit par son `fqn` ; `TaskLib::create()` en tire le recalcul de la série,
	 * et celui de la récolte quand c'est une récolte.
	 */
	protected function createTask(Cultivation $eCultivation = new Cultivation(), string $actionFqn = ACTION_PLANTATION, string $status = Task::TODO, ?string $week = NULL, ?string $date = NULL): Task {

		$this->stopOnClosedTransaction('avant createTask()');

		if($eCultivation->empty()) {
			$eCultivation = $this->getCultivation();
		}

		$eTask = new Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction($actionFqn),
			'category' => $this->getCategoryCulture(),
			'status' => $status,
			'repeatMaster' => new Repeat(),
		]);

		if($status === Task::DONE) {
			$eTask['doneWeek'] = $week ?? \Clock::week();
			$eTask['doneDate'] = $date;
		} else {
			$eTask['plannedWeek'] = $week;
			$eTask['plannedDate'] = $date;
		}

		TaskLib::create($eTask);

		return $eTask;

	}

	protected function getTask(): Task {

		return $this->once('task', fn() => $this->createTask());

	}

	/** L5 · Un temps du membre sur une intervention, tel que la feuille de temps l'écrit : la ligne, puis le cumul de l'intervention. */
	protected function createTimesheet(Task $eTask, float $time, string $date = ''): Timesheet {

		$this->stopOnClosedTransaction('avant createTimesheet()');

		$date = ($date === '') ? \Clock::date() : $date;

		$eTimesheet = new Timesheet([
			'structure' => $this->eStructure,
			'user' => $this->getMember(),
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'plant' => $eTask['plant'],
			'task' => $eTask,
			'time' => $time,
			'date' => $date,
			'week' => toWeek($date),
		]);

		TimesheetLib::create($eTimesheet);

		return $eTimesheet;

	}

	/**
	 * Une autre structure, n'importe laquelle : seule son identité compte, pour un témoin que la borne
	 * de structure doit écarter. Le cas se déclare si la base n'en porte aucune.
	 */
	protected function getStructureForeign(): \structure\Structure {

		return $this->once('structureForeign', fn() => \structure\Structure::model()
			->select(['id'])
			->whereId('!=', static::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->get());

	}

	protected function requireStructureForeign(): bool {

		return $this->assertTrue($this->getStructureForeign()->notEmpty(), 'la base porte une autre structure que la '.static::STRUCTURE.', pour y poser un témoin');

	}

	/**
	 * Le jumeau d'une intervention du décor chez une autre structure, posé par le modèle : `TaskLib::create()`
	 * recalcule la série et l'espèce de la structure connectée, et un témoin n'a besoin que d'une ligne
	 * portant une autre structure. L'action et la catégorie sont celles du décor, la ligne n'étant
	 * jamais lue autrement que par sa structure.
	 */
	protected function createTaskForeign(): Task {

		$this->stopOnClosedTransaction('avant createTaskForeign()');

		$eTask = new Task([
			'structure' => $this->getStructureForeign(),
			'season' => $this->getSeason(),
			'series' => new Series(),
			'cultivation' => new Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction(ACTION_PLANTATION),
			'category' => $this->getCategoryCulture(),
			'status' => Task::TODO,
			'plannedWeek' => \Clock::week(),
			'createdBy' => $this->getMember(),
		]);

		Task::model()->insert($eTask);

		return $eTask;

	}

	/**
	 * L1 bis · Une espèce du référentiel partagé (`structure IS NULL`), par son `fqn` : un décor de
	 * la base, qui se lit par ce qui l'identifie et se déclare, jamais par un identifiant.
	 */
	protected function getPlantShared(string $fqn): \plant\Plant {

		return $this->once('plantShared-'.$fqn, fn() => \plant\Plant::model()
			->select(\plant\Plant::getSelection())
			->whereFqn($fqn)
			->where('structure IS NULL')
			->get());

	}

	protected function requirePlantShared(string $fqn): bool {

		return $this->assertTrue($this->getPlantShared($fqn)->notEmpty(), 'le référentiel partagé porte l\'espèce « '.$fqn.' »');

	}

	/** Une variété du référentiel partagé, par son `fqn`, pour rattacher une variété du décor. */
	protected function getVarietyShared(string $fqn): \plant\Variety {

		return $this->once('varietyShared-'.$fqn, fn() => \plant\Variety::model()
			->select(\plant\Variety::getSelection())
			->whereFqn($fqn)
			->where('structure IS NULL')
			->get());

	}

	protected function requireVarietyShared(string $fqn): bool {

		return $this->assertTrue($this->getVarietyShared($fqn)->notEmpty(), 'le référentiel partagé porte la variété « '.$fqn.' »');

	}

	/**
	 * L1 ter · Une variété propre à la structure sur une espèce du décor. Rattachée au référentiel
	 * quand une variété partagée est donnée : `VarietyLib::create()` ne cherche la référence que par
	 * le nom, et l'espèce du décor n'ayant pas de référence, elle ne trouverait rien.
	 */
	protected function createVariety(\plant\Plant $ePlant = new \plant\Plant(), \plant\Variety $eVarietyReference = new \plant\Variety()): \plant\Variety {

		$this->stopOnClosedTransaction('avant createVariety()');

		$eVariety = new \plant\Variety([
			'name' => 'Variété du décor '.uniqid(),
			'structure' => $this->eStructure,
			'plant' => $ePlant->notEmpty() ? $ePlant : $this->getPlant(),
			'fqn' => $eVarietyReference->notEmpty() ? $eVarietyReference['fqn'] : NULL,
			'reference' => $eVarietyReference,
			'supplierSeed' => new \work\Supplier(),
			'supplierPlant' => new \work\Supplier(),
		]);

		\plant\VarietyLib::create($eVariety);

		return $eVariety;

	}

	protected function getVariety(): \plant\Variety {

		return $this->once('variety', fn() => $this->createVariety());

	}

	/** L2 bis · Une série pérenne : `SeriesLib::create()` en fait sa propre première saison. */
	protected function createSeriesPerennial(string $name = 'Série pérenne du décor'): Series {

		$this->stopOnClosedTransaction('avant createSeriesPerennial()');

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => $name,
			'cycle' => Series::PERENNIAL,
			'perennialLifetime' => 5,
			'use' => Series::BLOCK,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->getSeason(),
			'plants' => 1,
			'areaTarget' => 100,
			'perennialFirst' => new Series(),
			'duplicateOf' => new Series(),
		]);

		SeriesLib::create($eSeries);

		return $eSeries;

	}

	/**
	 * L2 ter · Une série en planches, avec la largeur de planche et d'allée que l'assolement lit :
	 * `PlaceLib::buildFromBeds()` calcule la surface d'un emplacement depuis les deux.
	 */
	protected function createSeriesBed(string $name = 'Série en planches du décor'): Series {

		$this->stopOnClosedTransaction('avant createSeriesBed()');

		$eSeries = new Series([
			'structure' => $this->eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => $name,
			'cycle' => Series::ANNUAL,
			'use' => Series::BED,
			'mode' => Series::OPEN_FIELD,
			'season' => $this->getSeason(),
			'plants' => 1,
			'areaTarget' => NULL,
			'lengthTarget' => 100,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'perennialFirst' => new Series(),
			'duplicateOf' => new Series(),
		]);

		SeriesLib::create($eSeries);

		return $eSeries;

	}

	protected function getSeriesBed(): Series {

		return $this->once('seriesBed', fn() => $this->createSeriesBed());

	}

	/** Clôture une série du décor, comme le geste `doUpdateStatus` de l'écran des séries, avec son dénouement s'il est dit. */
	protected function closeSeries(Series $eSeries, ?string $outcome = NULL): void {

		$this->stopOnClosedTransaction('avant closeSeries()');

		$eSeries['status'] = Series::CLOSED;
		$eSeries['outcome'] = $outcome;

		SeriesLib::update($eSeries, ['status', 'outcome']);

	}

	/** L3 bis · Une part variétale de la mise en culture, à 100 % quand elle est seule. */
	protected function createSlice(Cultivation $eCultivation, \plant\Variety $eVariety, float $partPercent = 100.0): Slice {

		$this->stopOnClosedTransaction('avant createSlice()');

		$eSlice = new Slice([
			'cultivation' => $eCultivation,
			'variety' => $eVariety,
			'partPercent' => $partPercent,
		]);

		SliceLib::create($eSlice);

		return $eSlice;

	}

	/**
	 * L4 bis · Une intervention de culture hors de toute série, sur l'espèce du décor : la forme
	 * que l'assolement d'une intervention (`place:update?task=`) reçoit.
	 */
	protected function createTaskOutOfSeries(string $actionFqn = ACTION_PLANTATION): Task {

		$this->stopOnClosedTransaction('avant createTaskOutOfSeries()');

		$eTask = new Task([
			'structure' => $this->eStructure,
			'series' => new Series(),
			'cultivation' => new Cultivation(),
			'plant' => $this->getPlant(),
			'action' => $this->getAction($actionFqn),
			'category' => $this->getCategoryCulture(),
			'status' => Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new Repeat(),
		]);

		TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * L4 ter · Une récolte terminée sur la mise en culture, dans l'unité de la culture et sans
	 * quantité encore : la quantité s'écrit par `createHarvest()`, comme l'écran et l'API l'écrivent.
	 */
	protected function createTaskHarvest(Cultivation $eCultivation = new Cultivation(), ?string $week = NULL, ?string $date = NULL): Task {

		$this->stopOnClosedTransaction('avant createTaskHarvest()');

		if($eCultivation->empty()) {
			$eCultivation = $this->getCultivation();
		}

		$eTask = new Task([
			'structure' => $this->eStructure,
			'series' => $eCultivation['series'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'action' => $this->getAction(ACTION_RECOLTE),
			'category' => $this->getCategoryCulture(),
			'status' => Task::DONE,
			'doneWeek' => $week ?? \Clock::week(),
			'doneDate' => $date,
			'harvest' => NULL,
			'harvestUnit' => $eCultivation['mainUnit'],
			'repeatMaster' => new Repeat(),
		]);

		TaskLib::create($eTask);

		return $eTask;

	}

	protected function getTaskHarvest(): Task {

		return $this->once('taskHarvest', fn() => $this->createTaskHarvest());

	}

	/**
	 * L6 · Une quantité récoltée sur une intervention de récolte, par le chemin de l'API et de
	 * l'écran : le total de l'intervention, la ligne datée du journal et les cumuls de la culture.
	 * L'intervention est relue entière d'abord, `TaskLib::fillDistribution()` lisant la surface de
	 * sa série et `buildHarvests()` son total.
	 */
	protected function createHarvest(Task $eTask, float $quantity, string $date = ''): Harvest {

		$this->stopOnClosedTransaction('avant createHarvest()');

		$date = ($date === '') ? \Clock::date() : $date;

		$eTask = Task::model()
			->select(Task::getSelection())
			->whereId($eTask)
			->get();

		$cTask = new \Collection([$eTask]);

		TaskLib::fillHarvestDates($cTask);
		TaskLib::fillDistribution($cTask);

		TaskLib::buildHarvests($cTask, $quantity, $date, $eTask['harvestUnit'], 'fair');
		TaskLib::update($eTask, ['harvest', 'harvestUnit']);

		return $eTask['eHarvest'];

	}

	/**
	 * L7 · Le parcellaire : une parcelle, un jardin en plein champ posé dedans (et non le jardin qui
	 * la remplit, que `ZoneLib::create()` ajoute de lui-même), une planche dimensionnée. Une planche
	 * qui remplit son jardin ou sa parcelle ne s'assole pas : l'assolement veut une planche propre.
	 */
	protected function createZone(): \map\Zone {

		$this->stopOnClosedTransaction('avant createZone()');

		$eZone = new \map\Zone([
			'name' => 'Parcelle du décor '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		\map\ZoneLib::create($eZone);

		return $eZone;

	}

	protected function getZone(): \map\Zone {

		return $this->once('zone', fn() => $this->createZone());

	}

	protected function createPlot(\map\Zone $eZone = new \map\Zone()): \map\Plot {

		$this->stopOnClosedTransaction('avant createPlot()');

		$ePlot = new \map\Plot([
			'name' => 'Jardin du décor '.uniqid(),
			'zone' => $eZone->notEmpty() ? $eZone : $this->getZone(),
			'zoneFill' => FALSE,
			'mode' => \map\Plot::OPEN_FIELD,
			'length' => NULL,
			'width' => NULL,
			'area' => 1000,
			'coordinates' => NULL,
			'position' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		\map\PlotLib::create($ePlot);

		return $ePlot;

	}

	protected function getPlot(): \map\Plot {

		return $this->once('plot', fn() => $this->createPlot());

	}

	protected function createBed(\map\Plot $ePlot = new \map\Plot(), int $length = 20, int $width = 80): \map\Bed {

		$this->stopOnClosedTransaction('avant createBed()');

		$eBed = new \map\Bed([
			'name' => 'P'.substr(uniqid(), -6),
			'plot' => $ePlot->notEmpty() ? $ePlot : $this->getPlot(),
			'plotFill' => FALSE,
			'zoneFill' => FALSE,
			'length' => $length,
			'width' => $width,
			'alleyWidth' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
			'status' => \map\Bed::ACTIVE,
		]);

		\map\BedLib::create($eBed);

		return $eBed;

	}

	protected function getBed(): \map\Bed {

		return $this->once('bed', fn() => $this->createBed());

	}

	/**
	 * L8 · Un emplacement d'une série en planches sur une planche, sur la longueur demandée, par le
	 * geste « + Ajouter » de l'assolement, qui recalcule la longueur et la surface de la série.
	 */
	protected function createPlace(Series $eSeries, \map\Bed $eBed, int $size): Place {

		$this->stopOnClosedTransaction('avant createPlace()');

		PlaceLib::addBeds($eSeries, [$eBed['id']], $size);

		return Place::model()
			->select(['id', 'length', 'width', 'area', 'season', 'series', 'bed'])
			->whereSeries($eSeries)
			->whereBed($eBed)
			->get();

	}

	/**
	 * L9 · Un itinéraire technique en planches sur des espèces du décor, une culture par espèce :
	 * c'est `SequenceLib::create()` qui les pose depuis `cPlant`.
	 */
	protected function createSequence(int $plants = 2, string $name = 'Itinéraire du décor'): \sequence\Sequence {

		$this->stopOnClosedTransaction('avant createSequence()');

		$cPlant = new \Collection();

		for($i = 0; $i < $plants; $i++) {
			$cPlant[] = $this->createPlant();
		}

		$eSequence = new \sequence\Sequence([
			'name' => $name,
			'description' => NULL,
			'cycle' => \sequence\Sequence::ANNUAL,
			'perennialLifetime' => NULL,
			'structure' => $this->eStructure,
			'duplicateOf' => new \sequence\Sequence(),
			'use' => \sequence\Sequence::BED,
			'bedWidth' => 80,
			'alleyWidth' => 40,
			'mode' => \sequence\Sequence::OPEN_FIELD,
			'comment' => NULL,
			'visibility' => \sequence\Sequence::PRIVATE,
			'status' => \sequence\Sequence::ACTIVE,
			'cPlant' => $cPlant,
		]);

		\sequence\SequenceLib::create($eSequence);

		return $eSequence;

	}

	protected function getSequence(): \sequence\Sequence {

		return $this->once('sequence', fn() => $this->createSequence());

	}

	/** Les cultures d'un itinéraire du décor, telles que l'itinéraire les a posées. */
	protected function getCrops(\sequence\Sequence $eSequence): \Collection {

		return \sequence\Crop::model()
			->select(\sequence\Crop::getSelection())
			->whereSequence($eSequence)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/** Une intervention modèle de l'itinéraire, sur une de ses cultures, une semaine donnée de l'année de départ. */
	protected function createFlow(\sequence\Sequence $eSequence, \sequence\Crop $eCrop, string $actionFqn = ACTION_PLANTATION, int $weekOnly = 20): \sequence\Flow {

		$this->stopOnClosedTransaction('avant createFlow()');

		$eFlow = new \sequence\Flow([
			'sequence' => $eSequence,
			'crop' => $eCrop,
			'action' => $this->getAction($actionFqn),
			'methods' => [],
			'tools' => [],
			'description' => NULL,
			'fertilizer' => NULL,
			'weekOnly' => $weekOnly,
			'yearOnly' => 0,
			'weekStart' => NULL,
			'yearStart' => NULL,
			'weekStop' => NULL,
			'yearStop' => NULL,
			'seasonOnly' => NULL,
			'seasonStart' => NULL,
			'seasonStop' => NULL,
			'frequency' => NULL,
		]);

		\sequence\FlowLib::create($eFlow);

		return $eFlow;

	}

	/** Une part variétale d'une culture de l'itinéraire. */
	protected function createSliceCrop(\sequence\Crop $eCrop, \plant\Variety $eVariety, float $partPercent = 100.0): \sequence\Slice {

		$this->stopOnClosedTransaction('avant createSliceCrop()');

		$eSlice = new \sequence\Slice([
			'crop' => $eCrop,
			'variety' => $eVariety,
			'partPercent' => $partPercent,
		]);

		\sequence\SliceLib::create($eSlice);

		return $eSlice;

	}

	/** Une espèce d'une autre structure, que la mise en culture et l'itinéraire doivent refuser. */
	protected function createPlantForeign(): \plant\Plant {

		$this->stopOnClosedTransaction('avant createPlantForeign()');

		$ePlant = new \plant\Plant([
			'name' => 'Espèce témoin '.uniqid(),
			'structure' => $this->getStructureForeign(),
			'cycle' => \plant\Plant::ANNUAL,
		]);

		\plant\Plant::model()->insert($ePlant);

		return $ePlant;

	}

	/** Un itinéraire d'une autre structure, sans culture : seule sa structure est lue. */
	protected function createSequenceForeign(): \sequence\Sequence {

		$this->stopOnClosedTransaction('avant createSequenceForeign()');

		$eSequence = new \sequence\Sequence([
			'name' => 'Itinéraire témoin',
			'cycle' => \sequence\Sequence::ANNUAL,
			'structure' => $this->getStructureForeign(),
			'author' => $this->getMember(),
			'plants' => 1,
			'use' => \sequence\Sequence::BED,
		]);

		\sequence\Sequence::model()->insert($eSequence);

		return $eSequence;

	}

	/** Une récolte d'une autre structure, sur une intervention témoin. */
	protected function createHarvestForeign(): Harvest {

		$this->stopOnClosedTransaction('avant createHarvestForeign()');

		$eTaskForeign = $this->createTaskForeign();

		$eHarvest = new Harvest([
			'structure' => $eTaskForeign['structure'],
			'series' => new Series(),
			'cultivation' => new Cultivation(),
			'task' => $eTaskForeign,
			'quantity' => 1.0,
			'unit' => Task::KG,
			'date' => \Clock::date(),
			'week' => \Clock::week(),
			'createdBy' => $this->getMember(),
		]);

		Harvest::model()->insert($eHarvest);

		return $eHarvest;

	}

	/** Une planche d'une autre structure, dans sa propre parcelle et son propre jardin. */
	protected function createBedForeign(): \map\Bed {

		$this->stopOnClosedTransaction('avant createBedForeign()');

		$eStructureForeign = $this->getStructureForeign();

		$eZone = new \map\Zone([
			'name' => 'Parcelle témoin',
			'structure' => $eStructureForeign,
			'area' => 1000,
			'createdBy' => $this->getMember(),
		]);

		\map\Zone::model()->insert($eZone);

		$ePlot = new \map\Plot([
			'name' => 'Jardin témoin',
			'structure' => $eStructureForeign,
			'zone' => $eZone,
			'zoneFill' => TRUE,
			'mode' => \map\Plot::OPEN_FIELD,
			'area' => 1000,
			'createdBy' => $this->getMember(),
		]);

		\map\Plot::model()->insert($ePlot);

		$eBed = new \map\Bed([
			'name' => 'Planche témoin',
			'structure' => $eStructureForeign,
			'zone' => $eZone,
			'plot' => $ePlot,
			'length' => 20,
			'width' => 80,
			'status' => \map\Bed::ACTIVE,
			'createdBy' => $this->getMember(),
		]);

		\map\Bed::model()->insert($eBed);

		return $eBed;

	}

	/** Un emplacement d'une autre structure : une intervention témoin sur une planche témoin. */
	protected function createPlaceForeign(): Place {

		$this->stopOnClosedTransaction('avant createPlaceForeign()');

		$eBedForeign = $this->createBedForeign();

		$ePlace = new Place([
			'structure' => $eBedForeign['structure'],
			'season' => $this->getSeason(),
			'series' => new Series(),
			'task' => $this->createTaskForeign(),
			'zone' => $eBedForeign['zone'],
			'plot' => $eBedForeign['plot'],
			'bed' => $eBedForeign,
			'length' => NULL,
			'width' => NULL,
			'area' => NULL,
			'createdBy' => $this->getMember(),
		]);

		Place::model()->insert($ePlace);

		return $ePlace;

	}

	/** Un calibre de l'espèce, propre à la structure du décor. */
	protected function createSize(\plant\Plant $ePlant): \plant\Size {

		$eSize = new \plant\Size([
			'name' => 'Calibre du décor '.uniqid(),
			'plant' => $ePlant,
			'structure' => $this->eStructure,
			'comment' => NULL,
			'yield' => TRUE,
		]);

		// Sans build(), qui vérifierait le nom et le commentaire postés depuis le formulaire des calibres :
		// le décor pose un nom unique et aucun commentaire, rien qu'un formulaire refuserait.
		\plant\SizeLib::create($eSize);

		return $eSize;

	}

	/** Ventile une intervention du décor, par variété ou par calibre, et pose au besoin son unité, comme le formulaire d'intervention. */
	protected function ventilate(Task $eTask, array $values): void {

		foreach($values as $property => $value) {
			$eTask[$property] = $value;
		}

		// Sans build(), qui vérifierait que la variété est cultivée dans la série (`Task::variety.check`), que
		// le calibre vient de la liste de l'espèce et que l'unité est une valeur de son enum : un décor pose la
		// ventilation que son cas éprouve, y compris celle que les deux premiers contrôles refusent, et la
		// saisie la vérifie elle-même par son couple ; l'unité qu'il pose est une constante de `Task`.
		TaskLib::update($eTask, array_keys($values));

	}

}
?>
