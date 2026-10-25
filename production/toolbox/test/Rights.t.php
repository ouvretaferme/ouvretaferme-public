<?php
namespace toolbox;

/**
 * Les droits de la Boîte à outils : un observateur lit, il n'écrit pas (retour du 29/09,
 * « un observateur peur agir en écriture », sur la pluviométrie de la 368 en production).
 *
 * Les pages gardaient l'écriture par `Structure::canWrite()`, qui répond « est membre » : un membre
 * qui n'a que la production en observation la passait. L'écriture demande désormais le droit du
 * planning, comme une intervention (`series\Task::canWrite()`), ou celui d'administrer quand elle
 * touche un matériel (`work\Tool::canWrite()`) ou un import.
 *
 * Chaque action est jouée par sa route, telle que `Page::execute()` la résout : l'observateur doit
 * y rencontrer `NotAllowedAction`, et la même route jouée par un membre au planning complet ne la
 * rencontre pas, ce qui prouve que le refus tient au rôle et non à une requête mal formée. Chaque
 * méthode tourne dans une transaction annulée (`TransactionTest`) : ce que le planning écrit, et ce
 * qu'une régression ferait écrire à l'observateur, n'en reste pas.
 *
 * Le membre connecté est injecté dans le cache de `MemberLib` par réflexion, comme
 * `structure\AccessSectionAiTest`. La structure de référence est F2 du décor : chaque méthode y pose
 * dans sa transaction un pluviomètre et son relevé, un traitement fait, et les paniers quand elle
 * les éprouve (`createCompositionDecor()`).
 *
 * Lancement : dev/test package=toolbox file=Rights
 */
class RightsTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F2;

	private \structure\Structure $eStructure;
	private Gauge $eGauge;
	private Rain $eRain;
	private \series\Task $eTask;

	/** Un identifiant par membre injecté : `MemberLib::$cMemberOnline` est indexé par utilisateur. */
	private int $userId = 930000;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Rain::model();
	}

	public function init(): void {

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE);

		if($this->eStructure->empty()) {
			throw new \Exception('Structure du décor #'.self::STRUCTURE.' introuvable : jouer structure/configure/fixtureAi');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();

	}

	/**
	 * Un pluviomètre relevé et un traitement fait, que la plupart des routes éprouvées désignent.
	 * Écrits par les Lib sans `build()` : le décor veut exactement ces valeurs, et c'est la garde des
	 * routes qu'on éprouve, pas la saisie. `createdBy` lit le compte connecté, d'où le membre posé
	 * d'abord.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->connectAs(planning: \structure\Role::FULL);

		$this->eGauge = new Gauge(['structure' => $this->eStructure, 'zone' => new \map\Zone()]);
		GaugeLib::create($this->eGauge);

		$this->eRain = new Rain(['structure' => $this->eStructure, 'gauge' => $this->eGauge, 'date' => \Clock::date(), 'rain' => 4]);
		RainLib::create($this->eRain);

		$this->eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'action' => TreatmentLib::getAction($this->eStructure),
			'category' => \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_PRODUCTION),
			'status' => \series\Task::DONE,
			'doneWeek' => \Clock::week(),
			'doneDate' => \Clock::date(),
			'repeatMaster' => new \series\Repeat(),
		]);

		\series\TaskLib::create($this->eTask);

		// Le premier pluviomètre inscrit la pluviométrie parmi les outils de la structure, que
		// `Structure::conf()` a pu garder d'une lecture antérieure.
		$this->forgetFeatures();

	}

	/** La fonctionnalité lue pendant la méthode peut porter ce que la transaction vient d'annuler. */
	public function tearDown(): void {

		parent::tearDown();

		$this->forgetFeatures();

	}

	/** Les caches sont des propriétés de processus : la transaction ne les annule pas. */
	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testRainActions(): void {

		$this->assertRefusedToObserver('POST', 'toolbox/rain:doCreate', [
			'structure' => self::STRUCTURE,
			'gauge' => $this->eGauge['id'],
			'date' => \Clock::date(),
			'rain' => '4',
		]);

		$this->assertRefusedToObserver('GET', 'toolbox/rain:create', ['structure' => self::STRUCTURE]);
		$this->assertRefusedToObserver('GET', 'toolbox/rain:update', ['id' => $this->eRain['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/rain:doUpdate', ['id' => $this->eRain['id'], 'rain' => '4']);
		$this->assertRefusedToObserver('POST', 'toolbox/rain:doDelete', ['id' => $this->eRain['id']]);

	}

	public function testGaugeActions(): void {

		$this->assertRefusedToObserver('POST', 'toolbox/gauge:doCreate', ['structure' => self::STRUCTURE, 'zone' => '']);
		$this->assertRefusedToObserver('GET', 'toolbox/gauge:update', ['id' => $this->eGauge['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/gauge:doUpdate', ['id' => $this->eGauge['id'], 'zone' => '']);
		$this->assertRefusedToObserver('POST', 'toolbox/gauge:doUpdateStatus', ['id' => $this->eGauge['id'], 'status' => Gauge::INACTIVE]);

	}

	public function testForget(): void {

		$this->assertRefusedToObserver('POST', 'toolbox/tool:doForget', ['id' => self::STRUCTURE, 'tool' => Favorite::RAIN]);

	}

	public function testTreatmentProduct(): void {

		$this->assertRefusedToObserver('GET', 'toolbox/treatment:updateProduct', ['id' => self::STRUCTURE, 'ids' => [$this->eTask['id']]]);
		$this->assertRefusedToObserver('POST', 'toolbox/treatment:doUpdateProduct', ['id' => self::STRUCTURE, 'ids' => [$this->eTask['id']]]);

	}

	/**
	 * Ouvrir le calculateur ou la recherche inscrit l'outil parmi ceux de la structure : un observateur
	 * qui les consulte n'inscrit rien. L'outil est d'abord retiré, pour que l'inscription soit
	 * observable.
	 */
	public function testOpenWritesOnlyWithPlanning(): void {

		foreach([Favorite::DENSITY => 'ferme/'.self::STRUCTURE.'/outils/densites', Favorite::CATALOG => 'ferme/'.self::STRUCTURE.'/outils/produits', Favorite::FERTILIZATION => 'ferme/'.self::STRUCTURE.'/outils/fertilisation'] as $tool => $path) {

			$hasTool = fn() => in_array($tool, \structure\Feature::model()
				->whereStructure($this->eStructure)
				->getValue('hasToolbox'));

			\structure\FeatureLib::removeToolbox($this->eStructure, $tool);

			$this->connectAs(observe: TRUE);
			$this->assertEquals(\ViewAction::class, $this->callRoute('GET', $path, ['open' => '1']), 'un observateur lit '.$tool);
			$this->assertFalse($hasTool(), 'un observateur qui ouvre '.$tool.' ne l\'inscrit pas');

			$this->connectAs(planning: \structure\Role::FULL);
			$this->assertEquals(\ViewAction::class, $this->callRoute('GET', $path, ['open' => '1']), 'le planning ouvre '.$tool);
			$this->assertTrue($hasTool(), 'le planning qui ouvre '.$tool.' l\'inscrit');

		}

	}

	/**
	 * Le rollback ne défait pas la mémoïsation de `conf()`, qui garde ce qu'elle a lu dans la
	 * transaction : sans l'oublier, l'outil retiré serait encore lu comme inscrit, et l'annulation ne
	 * serait pas vue.
	 */
	private function forgetFeatures(): void {

		\structure\FeatureLib::forgetAll();

	}

	public function testPagesAreReadable(): void {

		foreach(['pluviometrie', 'traitement', 'densites', 'fertilisation', 'produits', 'materiel', ''] as $page) {

			$this->connectAs(observe: TRUE);
			$this->assertEquals(\ViewAction::class, $this->callRoute('GET', rtrim('ferme/'.self::STRUCTURE.'/outils/'.$page, '/')), 'un observateur lit la page « '.$page.' »');

		}

	}

	/**
	 * Le calculateur des besoins en fertilisation n'enregistre rien : calculer, changer d'espèce ou de
	 * matière sont des lectures, ouvertes à l'observateur (tableau du CLAUDE.md du package).
	 */
	public function testFertilizationCalculates(): void {

		$eFertilizationCrop = FertilizationCropLib::getList()->first();
		$eFertilizationMaterial = FertilizationMaterialLib::getList()->first();

		$this->connectAs(observe: TRUE);

		$input = [
			'id' => self::STRUCTURE,
			'use' => \series\Series::BED,
			'bedWidth' => '80',
			'alleyWidth' => '40',
			'lengthTarget' => '30',
			'crop' => $eFertilizationCrop['id'],
			'material' => [0 => $eFertilizationMaterial['id']],
		];

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/fertilization:calculate', $input), 'un observateur calcule');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/fertilization:crop', ['id' => self::STRUCTURE, 'crop' => $eFertilizationCrop['id']]), 'change d\'espèce');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/fertilization:material', ['id' => self::STRUCTURE, 'index' => 1, 'material' => FertilizationMaterial::COMPOSITION, 'add' => 1]), 'et ajoute un complément');

	}

	/**
	 * L'accueil mène aussi aux besoins des cultures (03/10, « met en avant le fait qu'on peut
	 * aussi consulter les besoins des cultures sourcés »). Son second bouton nomme l'onglet dans
	 * l'adresse, que l'écran passe à `Lime.Tab.restore()` à la place de l'onglet retenu.
	 */
	public function testFertilizationStartOpensCrops(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();
		$cFertilizationMaterial = FertilizationMaterialLib::getList();

		$this->connectAs(observe: TRUE);

		$start = new FertilizationUi()->getStart($this->eStructure);

		$this->assertTrue(str_contains($start, 'href="'.FertilizationUi::url($this->eStructure).'?open&tab=fertilization-crops"'), 'l\'accueil ouvre les besoins des cultures');

		[$eSeries, $eFertilizationCrop, $eFertilizationYield, $materials] = FertilizationLib::getNew($this->eStructure);

		$remembered = new FertilizationUi()->get($this->eStructure, $cFertilizationCrop, $cFertilizationMaterial, new \Collection(), $eSeries, $eFertilizationCrop, $eFertilizationYield, $materials, NULL);
		$named = new FertilizationUi()->get($this->eStructure, $cFertilizationCrop, $cFertilizationMaterial, new \Collection(), $eSeries, $eFertilizationCrop, $eFertilizationYield, $materials, NULL, 'fertilization-crops');

		$this->assertTrue(str_contains($remembered, encode('Lime.Tab.restore(this, "fertilization-calculate", null)')), 'sans onglet nommé, le dernier ouvert');
		$this->assertTrue(str_contains($named, encode('Lime.Tab.restore(this, "fertilization-calculate", "fertilization-crops")')), 'l\'onglet nommé par l\'adresse');

	}

	/**
	 * Le sélecteur des matières ouvre sur la saisie manuelle, puis propose les intrants de la structure,
	 * sous « Mes intrants », et le référentiel ensuite (03/10). Sans intrant, il reste une
	 * liste simple, la saisie manuelle en tête. Aucun interrupteur ne la double.
	 */
	public function testFertilizationToolsInSelect(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();
		$cFertilizationMaterial = FertilizationMaterialLib::getList();

		$this->connectAs(observe: TRUE);

		[$eSeries, $eFertilizationCrop, $eFertilizationYield, $materials] = FertilizationLib::getNew($this->eStructure);

		$cTool = new \Collection([
			12 => new \work\Tool(['id' => 12, 'name' => 'Guanor', 'routineValue' => ['n' => 7.0, 'p' => 6.0, 'k' => 8.0, 'mgo' => NULL]]),
		]);

		$without = new FertilizationUi()->get($this->eStructure, $cFertilizationCrop, $cFertilizationMaterial, new \Collection(), $eSeries, $eFertilizationCrop, $eFertilizationYield, $materials, NULL);

		$this->assertTrue(str_contains($without, 'name="material[0]"><option value="composition" selected="selected">Saisie manuelle</option>'), 'la saisie manuelle en première option, choisie à l\'ouverture');
		$this->assertEquals(1, preg_match('/name="nitrogen\[0\]" type="number"\/>/', $without), 'ses champs vides');

		$material = FertilizationLib::getMaterial([], 0, FertilizationLib::TOOL.'12', $cFertilizationMaterial, $cTool);
		$html = new FertilizationUi()->getMaterial(new \util\FormUi(['columns' => 1]), $cFertilizationMaterial, $cTool, 0, $material);

		$this->assertEquals(1, preg_match('/name="nitrogen\[0\]" type="number" value="7"/', $html), 'et la pré-remplit en %');

	}

	/**
	 * La notice du calculateur (03/10) se lit comme l'écran, observateur compris : la roue
	 * la propose, l'action l'ouvre, et ses trois premières étapes montrent l'exemple de la carotte de
	 * garde et du compost de fumier de bovins, en morceaux inertes de l'écran. Rouge sur une base où
	 * `toolbox/configure/fertilizationReference` n'a pas tourné.
	 */
	public function testFertilizationNotice(): void {

		$this->connectAs(observe: TRUE);

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/fertilization:notice', ['id' => self::STRUCTURE]), 'l\'observateur ouvre la notice');

		$header = new FertilizationUi()->getHeader($this->eStructure, new \Collection());
		$this->assertTrue(str_contains($header, FertilizationUi::urlNotice($this->eStructure)), 'la roue propose la notice');

		$notice = FertilizationLib::getNotice($this->eStructure, FertilizationCropLib::getList(), FertilizationMaterialLib::getList());

		$this->assertEquals('Garde', $notice['eFertilizationYield']['name'], 'l\'exemple est la carotte de garde');
		$this->assertEquals('Compost de fumier de bovins', $notice['eFertilizationMaterial']['name'], 'et le compost de fumier de bovins');
		$this->assertTrue($notice['result'] !== NULL, 'calculés sur la planche par défaut');

		$cFertilizationSource = FertilizationSourceLib::getList();
		$sources = FertilizationLib::getSources($cFertilizationSource, FertilizationCropLib::getList(), FertilizationMaterialLib::getList());

		// Les documents du référentiel, lus en base (03/10, « ajoute la liste des sources
		// utilisées dans cette page ») : tous, ceux des cultures avant ceux des seules matières.
		$this->assertEquals($cFertilizationSource->count(), count($sources), 'tous les documents');
		$crops = array_map(fn(array $source) => array_intersect($source['uses'], ['mobilized', 'exported']) !== [], $sources);
		$this->assertEquals(array_fill(0, count(array_filter($crops)), TRUE), array_slice($crops, 0, count(array_filter($crops))), 'les cultures d\'abord');
		$this->assertTrue(in_array(['eFertilizationSource' => $cFertilizationSource->find(fn($e) => $e['name'] === 'RMT 2019')->first(), 'uses' => ['materials']], $sources), 'le RMT ne donne que les teneurs des matières');

	}

	/**
	 * L'écran ne propose pas ce que l'action refuse : ni « Il a plu ? », ni roue crantée, ni « Ne plus
	 * utiliser cet outil » pour l'observateur, et tout cela pour le planning.
	 */
	public function testRainScreen(): void {

		$cGauge = RainLib::getGauges($this->eStructure);
		$eGauge = $cGauge->first();

		// Sans lieu libre : « Nouveau pluviomètre » reste dans la roue (retour du 02/10).
		$render = fn() => new RainUi()->getOne($this->eStructure, $eGauge).new RainUi()->getHeader($this->eStructure, $cGauge, new \Collection(), new \Collection());

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'rain:doCreate'), 'l\'observateur n\'a pas la saisie « Il a plu ? »');
		$this->assertFalse(str_contains($html, 'rain:update'), 'l\'observateur n\'a pas la roue d\'un relevé');
		$this->assertFalse(str_contains($html, 'gauge:update'), 'l\'observateur n\'a pas la roue du pluviomètre');
		$this->assertFalse(str_contains($html, 'tool:doForget'), 'l\'observateur ne retire pas l\'outil');
		$this->assertFalse(str_contains($html, 'gauge:create'), 'l\'observateur ne pose pas de pluviomètre');
		$this->assertFalse(str_contains($html, 'rain:exportCsv'), 'ni n\'exporte les relevés');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'rain:doCreate'), 'le planning a la saisie');
		$this->assertTrue(str_contains($html, 'rain:update'), 'le planning a la roue d\'un relevé');
		$this->assertTrue(str_contains($html, 'gauge:update'), 'le planning a la roue du pluviomètre');
		$this->assertTrue(str_contains($html, 'tool:doForget'), 'le planning retire l\'outil');
		$this->assertTrue(str_contains($html, 'gauge:create'), 'le planning pose un pluviomètre, même sans lieu libre');
		$this->assertFalse(str_contains($html, 'rain:exportCsv'), 'et n\'exporte pas les relevés, ce qui demande de gérer la structure');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->assertTrue(str_contains($render(), 'rain:exportCsv'), 'qui administre exporte les relevés depuis la roue');

	}

	/**
	 * Les relevés de pluie en CSV sortent des données : ils demandent de gérer la structure, comme ceux
	 * de l'eau.
	 */
	public function testRainExport(): void {

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/rain:exportCsv', ['id' => self::STRUCTURE]), 'un observateur n\'exporte pas les relevés');

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/rain:exportCsv', ['id' => self::STRUCTURE]), 'le planning non plus');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\CsvAction::class, $this->callRoute('GET', 'toolbox/rain:exportCsv', ['id' => self::STRUCTURE]), 'qui administre reçoit le CSV');

	}

	public function testTreatmentScreen(): void {

		$eAction = TreatmentLib::getAction($this->eStructure);
		$search = new \Search(['plant' => new \plant\Plant(), 'tool' => new \work\Tool(), 'without' => FALSE]);
		$year = (int)substr(\series\Task::model()->whereId($this->eTask)->getValue('doneWeek') ?? (string)\Clock::year(), 0, 4);

		['cTask' => $cTask, 'tasksByMonth' => $tasksByMonth, 'cTaskWithout' => $cTaskWithout] = TreatmentLib::getBook($this->eStructure, $eAction, $year, $search);

		$render = fn() => (
			new TreatmentUi()->getHeader($this->eStructure, new \Collection()).
			new TreatmentUi()->getYears($this->eStructure, $eAction, [$year], [$year], $year, $search).
			new TreatmentUi()->getBook($this->eStructure, $year, $search, $cTask, $tasksByMonth, $cTaskWithout)
		);

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'task:createFrom'), 'l\'observateur ne saisit pas de traitement');
		$this->assertFalse(str_contains($html, 'batch[]'), 'l\'observateur n\'a pas les cases');
		$this->assertFalse(str_contains($html, 'task:update?'), 'l\'observateur ne modifie pas une intervention');
		$this->assertFalse(str_contains($html, 'tool:create'), 'l\'observateur n\'ajoute pas de produit');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'task:createFrom'), 'le planning saisit un traitement');
		$this->assertTrue(str_contains($html, 'task:update?'), 'le planning modifie une intervention');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->assertTrue(str_contains($render(), 'tool:create'), 'qui administre ajoute un produit');

	}

	public function testCatalogProduct(): void {

		$amm = \work\PhytoProduct::model()->sort(['id' => SORT_ASC])->getValue('amm');
		$ePhytoProduct = CatalogLib::getProduct($this->eStructure, $amm, new \plant\Plant());

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertFalse(str_contains(new CatalogUi()->displayProduct($this->eStructure, $ePhytoProduct)->body, 'tool:create'), 'sans administrer, pas d\'« Ajouter à mes produits »');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertTrue(str_contains(new CatalogUi()->displayProduct($this->eStructure, $ePhytoProduct)->body, 'tool:create'), 'qui administre ajoute le produit');

	}

	/**
	 * « Modifier les paniers » (r31, #96) garde les droits du réglage d'une taille qu'il remplace : la page
	 * des paniers ne s'ouvre qu'à qui gère la structure (`Structure::canManage()`), donc l'observateur
	 * n'atteint ni le panneau ni son envoi, et ne voit ni l'entrée du menu « Les paniers » qui
	 * l'ouvre, ni la roue de « Semaines livrées » : il garde le PDF. L'envoi de qui administre
	 * porte un prix refusé sur chaque taille : il passe la garde et rencontre le refus de saisie,
	 * sans rien écrire sur la structure de référence.
	 *
	 * « Ajouter un panier » (r51b) vit en tête de ce panneau, que l'observateur n'ouvre pas, et le
	 * panneau d'ajout comme son envoi lui sont refusés aussi. Qui administre l'ouvre, et son envoi
	 * sans nom passe la garde et rencontre le refus de saisie.
	 */
	public function testCompositionSettings(): void {

		$eCompositionPlan = $this->createCompositionDecor();

		$formulaIds = CompositionFormulaLib::getByPlan($eCompositionPlan)->getIds();
		$input = ['id' => self::STRUCTURE, 'price' => array_fill_keys($formulaIds, '-1')];

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/composition:settings', ['id' => self::STRUCTURE]), 'un observateur n\'ouvre pas « Modifier les paniers »');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:doSettings', $input), 'ni ne l\'envoie');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:settings', ['id' => self::STRUCTURE]), 'qui administre ouvre « Modifier les paniers »');
		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doSettings', $input), 'qui administre passe la garde, et le prix refusé n\'écrit rien');

		// « Ajouter un panier », en tête de « Modifier les paniers » (r51b).
		$formulas = CompositionFormula::model()->wherePlan($eCompositionPlan)->count();
		$inputCreate = ['structure' => self::STRUCTURE, 'name' => ''];

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/compositionFormula:create', ['structure' => self::STRUCTURE]), 'un observateur n\'ouvre pas « Ajouter un panier »');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/compositionFormula:doCreate', $inputCreate), 'ni n\'en ajoute un');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/compositionFormula:create', ['structure' => self::STRUCTURE]), 'qui administre ouvre « Ajouter un panier »');
		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/compositionFormula:doCreate', $inputCreate), 'et passe la garde de l\'envoi, où un panier sans nom est refusé');
		$this->assertEquals($formulas, CompositionFormula::model()->wherePlan($eCompositionPlan)->count(), 'rien n\'est écrit sur la structure de référence');

		$eCompositionPlanSettings = CompositionPlanLib::getByStructure($this->eStructure);
		$eCompositionPlanSettings['structure'] = $this->eStructure;
		CompositionPlanLib::loadSettings($eCompositionPlanSettings);

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getSettingsPanel($eCompositionPlanSettings, $formulaIds[0], CompositionPlan::TAB_FORMULA)->body.'</body></html>', LIBXML_NOERROR);

		$this->assertEquals(
			['/toolbox/compositionFormula:create?structure='.self::STRUCTURE.'&formula='.$formulaIds[0].'&origin='.CompositionPlan::TAB_FORMULA],
			array_map(fn(\Dom\Element $a) => $a->getAttribute('href'), iterator_to_array($document->querySelectorAll('a#toolbox-composition-settings-add'))),
			'son panneau porte le bouton d\'ajout, qui dit le panier ouvert et l\'onglet d\'où il part'
		);

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($this->eStructure, NULL);

		// Le menu « Les paniers » (r32) : l'observateur n'y garde que le PDF, « Modifier les paniers »
		// ne s'affiche pas pour lui.
		$menu = function() use($eCompositionPlanScreen): array {
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getProposal($eCompositionPlanScreen).'</body></html>', LIBXML_NOERROR);
			return array_map(fn(\Dom\Element $item) => $item->getAttribute('href'), iterator_to_array($document->querySelectorAll('.toolbox-composition-heading .dropdown-list > .dropdown-item')));
		};

		$pdf = CompositionUi::urlPdf($this->eStructure);

		$this->connectAs(observe: TRUE);
		$this->assertEquals([$pdf], $menu(), 'l\'observateur n\'a que le PDF');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals([
			'/toolbox/composition:settings?id='.self::STRUCTURE.'&formula='.$eCompositionPlanScreen['eCompositionFormula']['id'].'&origin=formula',
			$pdf,
		], $menu(), 'qui administre a le réglage puis le PDF, l\'ajout d\'un panier vivant dans « Modifier les paniers » (r51b)');

	}

	/**
	 * La fréquence d'une espèce se choisit dans le menu de son picto (lot r34c), qui poste le quick
	 * de la fréquence (`ModulePage::quick()`), gardé par le droit de l'espèce, celui de gérer la
	 * structure. L'observateur ne voit que l'image du picto, sans menu, et le quick qu'il posterait
	 * quand même lui est refusé. Qui administre passe la garde : sa fréquence inconnue rencontre le
	 * refus du schéma et n'écrit rien sur la structure de référence.
	 */
	public function testCompositionFrequency(): void {

		$eCompositionPlan = $this->createCompositionDecor();

		$eCompositionSpecies = CompositionSpecies::model()
			->select(['id'])
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->sort(['id' => SORT_ASC])
			->get();

		$input = [
			'id' => $eCompositionSpecies['id'],
			'structure' => self::STRUCTURE,
			'property' => 'frequency',
			'frequency' => 'chaque-semaine',
		];

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', '@module/toolbox/CompositionSpecies/doQuick', $input), 'un observateur ne règle pas une fréquence');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\FailAction::class, $this->callRoute('POST', '@module/toolbox/CompositionSpecies/doQuick', $input), 'qui administre passe la garde, et la fréquence inconnue n\'écrit rien');

	}

	/**
	 * « Utiliser dans un produit » (r37b, r39, r45) enregistre le produit retenu sur les espèces du plan et prépare une
	 * vente : ni la porte de la carte, ni la page, ni son envoi, ni le panneau ne s'offrent à
	 * l'observateur. Qui administre passe les trois gardes, et son envoi, où aucune ligne n'a de
	 * produit, rencontre le refus de la page sans rien écrire.
	 */
	public function testCompositionSale(): void {

		$eCompositionPlan = $this->createCompositionDecor();
		$eProductComposition = $this->createProductComposition();

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		$eCompositionWeek = CompositionWeek::model()
			->select(['id', 'week'])
			->whereFormula($eCompositionFormula)
			->where(new CompositionLineModel()->whereWeek(new \Sql('m1.id'))->existsSql())
			->sort(['week' => SORT_ASC])
			->get();

		$week = $eCompositionWeek['week'];

		$input = ['id' => self::STRUCTURE, 'formula' => $eCompositionFormula['id'], 'week' => $week, 'composition' => $eProductComposition['id']];

		$door = function() use($eCompositionFormula, $week): int {
			$eCompositionPlanScreen = CompositionPlanLib::getForScreen($this->eStructure, $eCompositionFormula['id']);
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getWeekCard($eCompositionPlanScreen, $eCompositionPlanScreen['eCompositionFormula'], $week).'</body></html>', LIBXML_NOERROR);
			// La porte mène à la page ou, quand tout est retenu, droit au panneau.
			return $document->querySelectorAll('a[href^="'.CompositionUi::url($this->eStructure).'/vente?"], a[href^="/selling/compositionSale:panel?"]')->length;
		};

		$this->connectAs(observe: TRUE);
		$this->assertEquals(0, $door(), 'l\'observateur n\'a pas la porte « Utiliser dans un produit »');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE.'/outils/paniers/vente', $input), 'ni la page');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/compositionSale:doUpdate', $input), 'ni son envoi');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'selling/compositionSale:panel', $input), 'ni le panneau');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertTrue($door() > 0, 'qui administre a la porte');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE.'/outils/paniers/vente', $input), 'et ouvre la page');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'selling/compositionSale:panel', $input), 'et le panneau');

		$fw = new \FailWatch();

		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/compositionSale:doUpdate', $input), 'son envoi passe la garde');
		$this->assertEquals(['CompositionSpecies::product.none'], array_column($fw->get(), 1), 'et rencontre le refus d\'une composition sans produit');

	}

	/**
	 * Le « Produit vendu » de « Régler une espèce » (r39b) lie l'espèce à un produit, dont elle suit
	 * le prix : l'envoi est refusé à l'observateur. Qui administre passe la garde, et un produit
	 * d'une autre structure, qui n'est jamais candidat, rencontre le refus du lien sans rien écrire
	 * sur la structure de référence.
	 */
	public function testCompositionSpeciesProduct(): void {

		$eCompositionPlan = $this->createCompositionDecor();

		$eCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'product'])
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->sort(['id' => SORT_ASC])
			->get();

		$eProductForeign = \product\Product::model()
			->select(['id'])
			->whereStructure('!=', $this->eStructure)
			->whereStatus(\product\Product::ACTIVE)
			->sort(['id' => SORT_ASC])
			->get();

		$input = [
			'id' => $eCompositionSpecies['id'],
			'structure' => self::STRUCTURE,
			'unit' => '',
			'frequency' => '',
			'product' => $eProductForeign['id'],
		];

		$query = ['id' => $eCompositionSpecies['id'], 'structure' => self::STRUCTURE, 'query' => ''];

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', $input), 'un observateur ne lie pas une espèce à un produit');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:queryProduct', $query), 'ni ne cherche le produit à lier (r53)');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:queryProduct', $query), 'qui administre cherche le produit à lier');

		$fw = new \FailWatch();

		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', $input), 'qui administre passe la garde');
		$this->assertTrue(in_array('CompositionSpecies::product.candidate', array_column($fw->get(), 1)), 'et rencontre le refus d\'un produit d\'une autre structure');

		$eProductRead = CompositionSpecies::model()->select(['product'])->whereId($eCompositionSpecies['id'])->get()['product'];

		$this->assertEquals($eCompositionSpecies['product']->empty() ? NULL : $eCompositionSpecies['product']['id'], $eProductRead->empty() ? NULL : $eProductRead['id'], 'rien n\'est écrit sur la structure de référence');

	}

	/**
	 * L'onglet « Espèces » des paniers (r48a) est refusé à l'observateur comme le reste de l'outil,
	 * et ses deux gestes avec lui : la corbeille (`doExclude`) et « Ajouter une espèce » (r48c,
	 * `composition:addSpecies`, `composition:doAddSpecies`, et la recherche de son produit vendu,
	 * `composition:queryAddProduct`, r60). Son rendu ne lui en offre aucun : ni
	 * corbeille, ni bouton d'ajout, ni lien vers « Régler une espèce », que sa page lui refuse, ni par
	 * le nom ni par la roue crantée (r59).
	 *
	 * Qui administre passe la garde sans rien écrire sur la structure de référence : une espèce livrée
	 * ne s'ajoute pas (`CompositionSpeciesLib::getForAdd()`). Son rendu porte les gestes.
	 *
	 * Le deck « Plan de culture » (r51b) et son envoi (`composition:doSynchronize`) suivent la même
	 * garde : l'observateur ne voit ni le deck ni ses cases, et l'envoi lui est refusé. Le deck se
	 * rend sur une liste posée en mémoire, pour que le contrôle ne dépende pas de ce que le plan de
	 * culture de la structure de référence date aujourd'hui. L'envoi de qui administre ne coche
	 * aucune espèce : il passe la garde et rencontre le refus, sans rien écrire.
	 */
	public function testCompositionSpeciesTab(): void {

		$eCompositionPlan = $this->createCompositionDecor();

		$eCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'plant'])
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->sort(['id' => SORT_ASC])
			->get();

		$input = ['id' => $eCompositionSpecies['id'], 'structure' => self::STRUCTURE];
		// « Ajouter une espèce » poste l'espèce de la structure, pas celle du référentiel que le plan écrit.
		$plantStructureId = \plant\Plant::model()
			->whereStructure($this->eStructure)
			->or(
				fn() => $this->whereReference($eCompositionSpecies['plant']),
				fn() => $this->whereId($eCompositionSpecies['plant']['id'])
			)
			->whereStatus(\plant\Plant::ACTIVE)
			->getValue('id');

		$this->assertTrue($plantStructureId !== NULL, 'la structure porte l\'espèce que ses paniers livrent, sans quoi l\'envoi serait refusé pour une autre raison');

		$inputAdd = ['id' => self::STRUCTURE, 'plant' => $plantStructureId];
		$url = 'ferme/'.self::STRUCTURE.'/outils/paniers/especes';

		$gestures = fn(): array => array_map(
			fn(string $mark) => substr_count(new CompositionUi()->getSpecies(CompositionPlanLib::getForScreen($this->eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)), $mark),
			[
				'corbeille' => 'compositionSpecies:doExclude',
				'ajout' => 'composition:addSpecies',
				'réglage' => 'compositionSpecies:update',
			]
		);

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', $url), 'un observateur n\'ouvre pas l\'onglet « Espèces »');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/compositionSpecies:doExclude', $input), 'ni ne retire une espèce');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => self::STRUCTURE]), 'ni n\'ouvre « Ajouter une espèce »');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $inputAdd), 'ni n\'en ajoute une');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => self::STRUCTURE, 'species' => $eCompositionSpecies['id']]), 'ni n\'ouvre l\'ajout d\'une variante par le « + » d\'un rang (r76)');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $inputAdd + ['name' => 'Vrac']), 'ni n\'ajoute une variante nommée (r76)');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:queryAddProduct', $inputAdd + ['query' => '']), 'ni ne cherche le produit vendu d\'une espèce à ajouter (r60)');
		$this->assertEquals(['corbeille' => 0, 'ajout' => 0, 'réglage' => 0], $gestures(), 'et le rendu de l\'onglet ne lui offre aucun geste, ni le « + » d\'une variante (r76)');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', $url), 'qui administre lit l\'onglet');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => self::STRUCTURE]), 'qui administre ouvre « Ajouter une espèce »');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => self::STRUCTURE, 'species' => $eCompositionSpecies['id']]), 'et l\'ajout d\'une variante par le « + » d\'un rang (r76)');
		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $inputAdd), 'et passe la garde de l\'envoi, où une espèce déjà livrée ne s\'ajoute qu\'en variante nommée (r76), donc sans rien écrire ici');
		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/composition:queryAddProduct', $inputAdd + ['query' => '']), 'et celle de la recherche du produit vendu, qui cherche pour la variante de l\'espèce livrée (r76)');

		// Le deck « Plan de culture » (r51b), sur deux espèces de l'onglet posées en mémoire.
		$deck = function(): array {

			$eCompositionPlanTab = CompositionPlanLib::getForScreen($this->eStructure, NULL, tab: CompositionPlan::TAB_SPECIES);

			$cCompositionSpeciesListed = new \Collection();

			foreach(array_slice($eCompositionPlanTab['cCompositionSpecies']->getKeys(), 0, 2) as $speciesId) {
				$eCompositionSpeciesListed = $eCompositionPlanTab['cCompositionSpecies'][$speciesId];
				$eCompositionSpeciesListed['monthsCultivation'] = $eCompositionSpeciesListed['months'];
				$cCompositionSpeciesListed[$speciesId] = $eCompositionSpeciesListed;
			}

			$eCompositionPlanTab['synchronization'] = ['cCompositionSpecies' => $cCompositionSpeciesListed, 'plants' => 2, 'custom' => 0];

			$html = new CompositionUi()->getSpecies($eCompositionPlanTab);

			return array_map(fn(string $mark) => substr_count($html, $mark), ['envoi' => 'composition:doSynchronize', 'cases' => 'name="species[]"']);

		};

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:doSynchronize', ['id' => self::STRUCTURE]), 'un observateur n\'applique pas le plan de culture');
		$this->assertEquals(['envoi' => 0, 'cases' => 0], $deck(), 'et ne voit ni le deck ni ses cases');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doSynchronize', ['id' => self::STRUCTURE]), 'qui administre passe la garde, et l\'envoi sans espèce cochée n\'écrit rien');
		$this->assertEquals(['envoi' => 1, 'cases' => 2], $deck(), 'son onglet porte le deck, son envoi et une case par espèce listée');

	}

	/**
	 * « Ajouter une espèce » au départ des paniers (r48e, `composition:addStartSpecies`,
	 * `composition:doAddStartSpecies`) : le panneau n'écrit rien, et se garde pourtant comme le départ
	 * qu'il sert, que l'observateur n'ouvre pas.
	 *
	 * Qui administre l'ouvre, et son envoi passe la garde sans rien écrire sur la structure de
	 * référence : une espèce qui n'est pas la sienne ne s'ajoute pas.
	 */
	public function testCompositionStartSpecies(): void {

		$eCompositionPlan = $this->createCompositionDecor();

		$input = [
			'id' => self::STRUCTURE,
			'season' => $eCompositionPlan['season'],
			'reference' => CompositionPlan::CALENDAR,
			'climate' => CompositionPlan::OCEANIC,
			'delivered' => '',
		];

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/composition:addStartSpecies', $input), 'un observateur n\'ouvre pas « Ajouter une espèce » au départ');
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $input + ['plant' => 0]), 'ni n\'y ajoute une espèce');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:addStartSpecies', $input), 'qui administre l\'ouvre');

		$fw = new \FailWatch();

		$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $input + ['plant' => 0]), 'et passe la garde de l\'envoi');
		$this->assertEquals(['CompositionSpecies::plant.addable'], array_column($fw->get(), 1), 'où une espèce qui n\'est pas la sienne ne s\'ajoute pas');

	}

	/**
	 * La présentation de l'outil (r35, r36) se lit comme l'écran : l'entrée ouvre la roue de l'en-tête
	 * pour l'observateur comme pour qui administre, en première ligne, et son panneau est servi aux
	 * deux, là où « Modifier les paniers » refuse l'observateur (`testCompositionSettings()`).
	 *
	 * L'observateur y voit les mêmes illustrations, menu de la fréquence compris, que la grille ne
	 * lui ouvre pas, et aucun geste ; qui administre y voit les gestes de l'écran, que l'illustration
	 * rend inertes.
	 */
	public function testCompositionNotice(): void {

		$this->createCompositionDecor();

		$cFavorite = FavoriteLib::getByStructure($this->eStructure);

		$first = function() use($cFavorite): array {
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getHeader($this->eStructure, $cFavorite).'</body></html>', LIBXML_NOERROR);
			$item = $document->querySelector('.util-page-header-aside .dropdown-list > .dropdown-item');
			return ($item === NULL) ? [] : [$item->getAttribute('href')];
		};

		$notice = ['/toolbox/composition:notice?id='.self::STRUCTURE];

		$figures = function(): array {
			$eCompositionPlan = CompositionPlanLib::getForScreen($this->eStructure, NULL);
			$notice = CompositionPlanLib::getNotice($eCompositionPlan);
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new CompositionUi()->getNoticePanel($eCompositionPlan, $notice)->body.'</body></html>', LIBXML_NOERROR);
			return [
				'gestures' => $document->querySelectorAll('[data-ajax]')->length,
			];
		};

		$this->connectAs(observe: TRUE);
		$this->assertEquals($notice, $first(), 'l\'observateur ouvre la roue sur la notice');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:notice', ['id' => self::STRUCTURE]), 'et la lit');

		$observer = $figures();
		$this->assertEquals(0, $observer['gestures'], 'sans aucun geste');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals($notice, $first(), 'qui administre aussi, en première ligne');
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/composition:notice', ['id' => self::STRUCTURE]), 'et la lit');

	}

	/**
	 * Le carnet du matériel : suivre un matériel, relever son compteur, régler ses entretiens et les
	 * noter faits demandent le planning. Le décor se monte dans la transaction de la méthode.
	 *
	 * Les suppressions viennent en dernier : le planning qui les joue retire ce que les routes
	 * suivantes auraient à lire.
	 */
	public function testMaintenanceActions(): void {

		['eEquipment' => $eEquipment, 'eMaintenance' => $eMaintenance, 'eMaintenanceDone' => $eMaintenanceDone] = $this->createMaintenanceDecor();

		$this->assertRefusedToObserver('GET', 'toolbox/equipment:create', ['structure' => self::STRUCTURE]);
		$this->assertRefusedToObserver('POST', 'toolbox/equipment:query', ['structure' => self::STRUCTURE, 'query' => 'Trac']);
		$this->assertRefusedToObserver('POST', 'toolbox/equipment:doFollow', ['structure' => self::STRUCTURE, 'tool' => '', 'meter' => Equipment::HOURS, 'value' => '']);

		$this->assertRefusedToObserver('GET', 'toolbox/equipmentReading:create', ['structure' => self::STRUCTURE, 'equipment' => $eEquipment['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/equipmentReading:doCreate', [
			'structure' => self::STRUCTURE,
			'equipment' => $eEquipment['id'],
			'date' => \Clock::date(),
			'value' => '1300',
		]);

		$eEquipmentReading = EquipmentReading::model()
			->select('id')
			->whereEquipment($eEquipment)
			->sort(['date' => SORT_ASC])
			->get();

		$this->assertRefusedToObserver('GET', 'toolbox/equipmentReading:update', ['id' => $eEquipmentReading['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/equipmentReading:doUpdate', ['id' => $eEquipmentReading['id'], 'value' => '10']);
		$this->assertRefusedToObserver('POST', 'toolbox/equipmentReading:doDelete', ['id' => $eEquipmentReading['id']]);

		$this->assertRefusedToObserver('GET', 'toolbox/maintenance:create', ['equipment' => $eEquipment['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenance:doCreate', ['equipment' => $eEquipment['id'], 'name' => 'Graissage', 'frequency' => 'regular', 'everyMonths' => '3', 'plannedDate' => \Clock::date()]);
		$this->assertRefusedToObserver('GET', 'toolbox/maintenance:update', ['id' => $eMaintenance['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenance:doUpdate', ['id' => $eMaintenance['id'], 'name' => 'Vidange']);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenance:doIgnore', ['id' => $eMaintenance['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenance:doSkip', ['id' => $eMaintenance['id'], 'week' => \Clock::week()]);
		$this->assertRefusedToObserver('POST', 'toolbox/equipment:doSkipReading', ['id' => $eEquipment['id'], 'week' => \Clock::week()]);

		$this->assertRefusedToObserver('GET', 'toolbox/maintenanceDone:create', ['maintenance' => $eMaintenance['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceDone:next', ['maintenance' => $eMaintenance['id'], 'date' => \Clock::date()]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceDone:doCreate', ['maintenance' => $eMaintenance['id'], 'date' => \Clock::date()]);
		$this->assertRefusedToObserver('GET', 'toolbox/maintenanceDone:create', ['equipment' => $eEquipment['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceDone:doCreate', ['equipment' => $eEquipment['id'], 'name' => 'Courroie', 'date' => \Clock::date()]);

		// L'intervention que prend un entretien fait au planning et le rythme du rappel des compteurs
		// règlent la ferme pour toute l'équipe : ils suivent le travail des cultures, donc le droit du
		// planning.
		$this->assertRefusedToObserver('GET', 'toolbox/maintenanceConfiguration:create', ['structure' => self::STRUCTURE]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceConfiguration:doCreate', ['structure' => self::STRUCTURE, 'action' => '', 'readingEveryMonths' => '1']);

		// La photo d'une ligne se lit comme le carnet : l'observateur l'ouvre, et une ligne sans photo
		// n'a rien à ouvrir. Joué avant la suppression, que le planning réussit plus bas.
		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotExistsAction::class, $this->callRoute('GET', 'toolbox/maintenanceDone:photo', ['id' => $eMaintenanceDone['id']]), 'une ligne sans photo n\'ouvre aucun panneau');

		// Sans `build()` : l'empreinte est le décor, aucune image n'est lue par la route.
		$eMaintenanceDone['photo'] = 'photodroitsducarne0j';
		MaintenanceDone::model()->select('photo')->update($eMaintenanceDone);

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/maintenanceDone:photo', ['id' => $eMaintenanceDone['id']]), 'un observateur ouvre la photo d\'une ligne');

		// Un compte qui n'est membre d'aucune structure, pour que la garde de lecture ait de quoi refuser.
		$eUserStranger = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUserStranger);
		self::getMemberCache()->setValue(NULL, self::getMemberCache()->getValue() + [$eUserStranger['id'] => new \Collection()]);

		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/maintenanceDone:photo', ['id' => $eMaintenanceDone['id']]), 'qui n\'est pas membre de la structure ne l\'ouvre pas');

		$this->assertRefusedToObserver('GET', 'toolbox/maintenanceDone:update', ['id' => $eMaintenanceDone['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceDone:doUpdate', ['id' => $eMaintenanceDone['id'], 'cost' => '20']);
		$this->assertRefusedToObserver('POST', 'toolbox/maintenanceDone:doDelete', ['id' => $eMaintenanceDone['id']]);

		// L'historique en CSV sort des données : il demande de gérer la structure, que le planning seul
		// n'a pas. Le PDF, que tout membre télécharge, n'est pas joué ici : sa route lance le navigateur
		// sans tête, et c'est l'écran qui dit qui le reçoit (`testMaintenanceScreen()`).
		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/equipment:exportCsv', ['id' => $eEquipment['id']]), 'un observateur n\'exporte pas l\'historique');

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/equipment:exportCsv', ['id' => $eEquipment['id']]), 'le planning non plus');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\CsvAction::class, $this->callRoute('GET', 'toolbox/equipment:exportCsv', ['id' => $eEquipment['id']]), 'qui administre reçoit le CSV');

		$this->assertRefusedToObserver('POST', 'toolbox/maintenance:doUpdateStatus', ['id' => $eMaintenance['id'], 'status' => Maintenance::DELETED]);
		$this->assertRefusedToObserver('POST', 'toolbox/equipment:doUpdateStatus', ['id' => $eEquipment['id'], 'status' => Equipment::INACTIVE]);

	}

	/**
	 * L'historique d'un matériel qu'on ne suit plus ne s'écrit plus à l'écran non plus : le planning,
	 * qui a le droit, est refusé sur l'état de la ligne (`MaintenanceDone::acceptUpdate()`), et la
	 * ligne reste ce qu'elle était.
	 */
	public function testMaintenanceDoneOnUnfollowedEquipment(): void {

		['eEquipment' => $eEquipment, 'eMaintenanceDone' => $eMaintenanceDone] = $this->createMaintenanceDecor();

		$routes = [
			['GET', 'toolbox/maintenanceDone:update', []],
			['POST', 'toolbox/maintenanceDone:doUpdate', ['cost' => '20']],
			['POST', 'toolbox/maintenanceDone:doDelete', []],
		];

		$this->connectAs(planning: \structure\Role::FULL);

		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'toolbox/maintenanceDone:update', ['id' => $eMaintenanceDone['id']]), 'tant que le matériel est suivi, la ligne s\'ouvre');

		$eEquipment['status'] = Equipment::INACTIVE;
		EquipmentLib::update($eEquipment, ['status']);

		foreach($routes as [$method, $path, $input]) {
			$this->assertEquals(\NotExpectedAction::class, $this->callRoute($method, $path, $input + ['id' => $eMaintenanceDone['id']]), $path.' est refusé sur un matériel qui n\'est plus suivi');
		}

		$eMaintenanceDoneRead = MaintenanceDone::model()
			->select(['cost'])
			->whereId($eMaintenanceDone['id'])
			->get();

		$this->assertTrue($eMaintenanceDoneRead->notEmpty(), 'la ligne est restée');
		$this->assertEquals(80.0, $eMaintenanceDoneRead['cost'], 'et son coût aussi');

	}

	/**
	 * L'écran de l'observateur n'a ni la ligne « Compteur », ni « Fait », ni roue, ni « + Entretien »,
	 * et ses tableaux perdent la colonne des gestes (arbitrage Q8 A de la planche pf-t4-v2). « Modifier
	 * le matériel » change « Mon matériel » : il ne s'offre qu'à qui administre.
	 */
	public function testMaintenanceScreen(): void {

		$this->createMaintenanceDecor();

		$render = function(): string {

			$cEquipment = EquipmentLib::getByStructure($this->eStructure);

			return (
				new EquipmentUi()->getHeader($this->eStructure, $cEquipment, new \Collection()).
				new EquipmentUi()->getDue($this->eStructure, EquipmentLib::getDue($cEquipment), $cEquipment, new Equipment()).
				new EquipmentUi()->getEquipments($this->eStructure, $cEquipment, new Equipment())
			);

		};

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'equipment:create'), 'l\'observateur ne suit pas de matériel');
		$this->assertFalse(str_contains($html, 'equipmentReading:doCreate'), 'l\'observateur n\'a pas la bande du relevé');
		$this->assertFalse(str_contains($html, 'maintenanceDone:create'), 'l\'observateur ne note pas un entretien fait');
		$this->assertFalse(str_contains($html, 'maintenance:create'), 'l\'observateur n\'ajoute pas d\'entretien');
		$this->assertFalse(str_contains($html, 'maintenance:update'), 'l\'observateur n\'a pas la roue d\'un entretien');
		$this->assertFalse(str_contains($html, 'maintenanceDone:update'), 'l\'observateur n\'a pas la roue de l\'historique');
		$this->assertFalse(str_contains($html, 'maintenance:doUpdateStatus'), 'l\'observateur ne supprime pas d\'entretien');
		$this->assertFalse(str_contains($html, 'maintenanceDone:doDelete'), 'l\'observateur ne retire rien de l\'historique');
		$this->assertFalse(str_contains($html, 'maintenanceDone:create?equipment='), 'l\'observateur n\'ajoute pas d\'intervention');
		$this->assertFalse(str_contains($html, 'equipmentReading:update'), 'l\'observateur ne corrige pas de relevé');
		$this->assertFalse(str_contains($html, 'maintenanceConfiguration:create'), 'l\'observateur ne règle pas le carnet d\'entretien');
		$this->assertTrue(str_contains($html, 'equipment:downloadPdf'), 'l\'observateur télécharge le carnet en PDF, qu\'il lit déjà à l\'écran');
		$this->assertFalse(str_contains($html, 'equipment:exportCsv'), 'l\'observateur n\'exporte pas l\'historique en CSV');
		$this->assertFalse(str_contains($html, 'equipment:doUpdateStatus'), 'le bouton du PDF ne lui donne pas la roue du matériel');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'equipment:create'), 'le planning suit un matériel');
		$this->assertTrue(str_contains($html, 'equipmentReading:doCreate'), 'le planning relève le compteur');
		$this->assertTrue(str_contains($html, 'maintenanceDone:create'), 'le planning note un entretien fait');
		$this->assertTrue(str_contains($html, 'maintenanceConfiguration:create'), 'le planning règle le carnet d\'entretien : l\'intervention que prend un entretien fait, le rythme du rappel des compteurs');
		$this->assertTrue(str_contains($html, 'maintenance:create'), 'le planning ajoute un entretien');
		$this->assertTrue(str_contains($html, 'maintenance:update'), 'le planning a la roue d\'un entretien');
		$this->assertTrue(str_contains($html, 'maintenance:doUpdateStatus'), 'la roue d\'un entretien le supprime');
		$this->assertTrue(str_contains($html, 'maintenanceDone:doDelete'), 'la roue d\'une ligne de l\'historique la retire');
		$this->assertTrue(str_contains($html, 'maintenanceDone:create?equipment='), 'le planning ajoute une intervention');
		$this->assertTrue(str_contains($html, 'equipmentReading:update'), 'le planning corrige un relevé');
		$this->assertFalse(str_contains($html, '/work/tool:update'), 'le planning ne modifie pas « Mon matériel »');
		$this->assertTrue(str_contains($html, 'equipment:downloadPdf'), 'la roue du planning porte le PDF');
		$this->assertFalse(str_contains($html, 'equipment:exportCsv'), 'et pas le CSV, qui demande de gérer la structure');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->assertTrue(str_contains($render(), '/work/tool:update'), 'qui administre modifie le matériel');
		$this->assertTrue(str_contains($render(), 'equipment:exportCsv'), 'et exporte l\'historique en CSV');

	}

	/**
	 * Le bloc « Entretien du matériel » du planning de la semaine reprend les rangées du carnet :
	 * l'observateur les lit sans la colonne de « Fait », le planning les note faites.
	 */
	public function testMaintenancePlanningBlock(): void {

		['eEquipment' => $eEquipment, 'eMaintenance' => $eMaintenance] = $this->createMaintenanceDecor();

		// La vidange du décor est en retard : la semaine en cours la liste.
		$render = fn(): string => new \planning\DueUi()->getWeek(
			$this->eStructure,
			\Clock::week(),
			\planning\DueLib::getForWeek($this->eStructure, \Clock::week())
		);

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'maintenanceDone:create'), 'ni le geste');
		$this->assertFalse(str_contains($html, 'maintenance:doSkip'), 'ni la croix');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'maintenanceDone:create'), 'le planning note l\'entretien fait depuis le planning');
		$this->assertTrue(str_contains($html, 'data-ajax="/toolbox/maintenance:doSkip" post-id="'.$eMaintenance['id'].'" post-week="'.\Clock::week().'"'), 'et passe l\'échéance par la croix, qui emporte la semaine');

		// Le compteur du matériel entre au planning le 5 de son mois de relevé, souvent une semaine à
		// venir : le bloc se lit sur la semaine de ce jour-là.
		$eDue = \planning\Due::model()
			->select(['dueDate'])
			->whereType(PlanningObserverLib::READING)
			->whereReference($eEquipment['id'])
			->get();

		$week = toWeek($eDue['dueDate']);
		$render = fn(): string => new \planning\DueUi()->getWeek($this->eStructure, $week, \planning\DueLib::getForWeek($this->eStructure, $week));

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'equipmentReading:create'), 'sans « Relever »');
		$this->assertFalse(str_contains($html, 'equipment:doSkipReading'), 'ni la croix');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'equipmentReading:create'), 'le planning relève depuis le planning');
		$this->assertTrue(str_contains($html, 'data-ajax="/toolbox/equipment:doSkipReading" post-id="'.$eEquipment['id'].'" post-week="'.$week.'"'), 'ou passe le relevé par la croix');

	}

	/**
	 * Un geste fait depuis le bloc « Rappels » du planning ne recharge que ce bloc
	 * (`planning/due:refresh`), quand le même geste fait dans le carnet ou l'écran de l'eau garde sa
	 * réponse : c'est l'origine postée qui choisit, jamais la page d'où vient la requête.
	 */
	public function testPlanningGesturesAnswerWithTheirBlock(): void {

		['eEquipment' => $eEquipment, 'eMaintenance' => $eMaintenance] = $this->createMaintenanceDecor();
		['eWaterMeter' => $eWaterMeter] = $this->createWaterDecor();

		$this->connectAs(planning: \structure\Role::FULL);

		$planning = ['origin' => 'planning', 'week' => \Clock::week()];

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/equipmentReading:doCreate', [
			'structure' => self::STRUCTURE,
			'equipment' => $eEquipment['id'],
			'meterConfirmed' => '0',
			'date' => \Clock::date(),
			'value' => '1300',
		] + $planning), 'un relevé fait au planning rend le bloc');

		$this->assertEquals(\ReloadAction::class, $this->callRoute('POST', 'toolbox/equipmentReading:doCreate', [
			'structure' => self::STRUCTURE,
			'equipment' => $eEquipment['id'],
			'meterConfirmed' => '0',
			'date' => \Clock::date(),
			'value' => '1350',
		]), 'fait dans le carnet, il recharge l\'écran');

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/waterReading:doCreate', [
			'structure' => self::STRUCTURE,
			'meter' => $eWaterMeter['id'],
			'date' => \Clock::date(),
			'value' => '12500',
		] + $planning), 'un relevé d\'eau fait au planning rend le bloc');

		// Le premier « Fait » au planning choisit l'intervention qu'il prend, comme le panneau la demande ;
		// la structure de ce test n'a pas celle du modèle, toute intervention de production convient.
		$eAction = MaintenanceConfigurationLib::getActions($this->eStructure)->first();

		$this->assertEquals(\ViewAction::class, $this->callRoute('POST', 'toolbox/maintenanceDone:doCreate', [
			'maintenance' => $eMaintenance['id'],
			'date' => \Clock::date(),
			'action' => (string)$eAction['id'],
		] + $planning), 'l\'entretien fait au planning aussi, aligné sur les relevés');

	}

	/**
	 * « Suivre un matériel » envoyé sans matériel choisi dans la liste : le refus nomme les gestes que
	 * la liste offre à la personne. « Valider » un nom neuf ne s'offre qu'à qui gère la structure
	 * (retour du 01/10, « message trop réducteur »).
	 */
	public function testFollowWithoutToolSaysWhatTheListOffers(): void {

		$builds = function(mixed $tool): array {

			$fw = new \FailWatch();
			new Equipment(['structure' => $this->eStructure])->build(['tool'], ['tool' => $tool]);

			return array_map(fn($fail) => $fail[1], $fw->get());

		};

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(['Equipment::tool.unvalidated'], $builds(''), 'qui gère la structure peut valider un nom neuf');
		$this->assertEquals(['Equipment::tool.check'], $builds('0'), 'un identifiant inconnu reste le refus du contrôle');

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertEquals(['Equipment::tool.unselected'], $builds(''), 'le planning choisit un matériel qui existe');

		$this->assertTrue(\toolbox\AlertUi::getError('Equipment::tool.unvalidated') !== NULL, 'le refus du gestionnaire a son message');
		$this->assertTrue(\toolbox\AlertUi::getError('Equipment::tool.unselected') !== NULL, 'le refus du planning a son message');

	}

	/**
	 * Un matériel sans entretien planifié ni historique n'a rien à sortir (relecture de carnet-export
	 * du 01/10) : le PDF ne rendrait qu'une page de zéros, le CSV sa ligne d'en-tête. Ni l'écran ni
	 * l'action ne les proposent, et l'observateur n'a pas de bouton dont la place resterait vide. Un
	 * premier relevé ouvre les deux.
	 */
	public function testEquipmentWithNothingHasNoExport(): void {

		$eTool = \work\ToolLib::getNewTool($this->eStructure, NULL);
		// Sans `build()` : le nom est choisi par le test, et « Mon matériel » n'est pas ce qu'il éprouve.
		$eTool['name'] = 'Motoculteur des droits '.$this->userId;
		\work\ToolLib::create($eTool);

		$eEquipment = new Equipment([
			'structure' => $this->eStructure,
			'tool' => $eTool,
			'meter' => Equipment::HOURS,
			'eToolNew' => new \work\Tool(),
		]);

		EquipmentLib::follow($eEquipment, new EquipmentReading());

		$render = function() use ($eEquipment): string {

			// Ce matériel seul : la barre d'onglets rend le bloc de chaque matériel de la structure.
			$cEquipment = EquipmentLib::getByStructure($this->eStructure)->find(fn(Equipment $eEquipmentFollowed) => $eEquipmentFollowed['id'] === $eEquipment['id']);

			return new EquipmentUi()->getEquipments($this->eStructure, $cEquipment, new Equipment());

		};

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'equipment:downloadPdf'), 'un carnet vide ne se télécharge pas');
		$this->assertFalse(str_contains($html, 'equipment:exportCsv'), 'et son historique ne s\'exporte pas');
		$this->assertTrue(str_contains($html, 'equipment:doUpdateStatus'), 'la roue garde ses autres gestes');
		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('GET', 'toolbox/equipment:exportCsv', ['id' => $eEquipment['id']]), 'l\'action refuse aussi le CSV d\'un carnet vide');

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'equipment:downloadPdf'), 'l\'observateur n\'a pas de bouton PDF sur un carnet vide');

		// Un décor n'a pas de `$_POST` à valider : `build()` vérifierait la date et le compteur, que le test choisit.
		EquipmentReadingLib::create(new EquipmentReading(['structure' => $this->eStructure, 'equipment' => $eEquipment, 'date' => \Clock::date(), 'value' => 120]));

		$html = $render();

		$this->assertTrue(str_contains($html, 'equipment:downloadPdf'), 'un premier relevé ouvre le PDF à l\'observateur');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$html = $render();

		$this->assertTrue(str_contains($html, 'equipment:exportCsv'), 'et le relevé ouvre le CSV à qui administre');
		$this->assertEquals(\CsvAction::class, $this->callRoute('GET', 'toolbox/equipment:exportCsv', ['id' => $eEquipment['id']]));

	}

	/**
	 * La consommation d'eau : relever un compteur, le poser, le régler ou le supprimer demandent le
	 * planning, comme un relevé de pluie. La suppression du compteur vient en dernier, elle ferme
	 * les autres gestes.
	 */
	public function testWaterActions(): void {

		['eWaterMeter' => $eWaterMeter, 'eWaterReading' => $eWaterReading] = $this->createWaterDecor();

		// Les relevés en CSV sortent des données : ils demandent de gérer la structure, que le planning
		// seul n'a pas. Joué d'abord : le planning, qui passe chaque garde ci-dessous, supprime le relevé
		// puis le compteur, et il n'y aurait plus rien à sortir.
		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/waterReading:exportCsv', ['id' => self::STRUCTURE]), 'un observateur n\'exporte pas les relevés');

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute('GET', 'toolbox/waterReading:exportCsv', ['id' => self::STRUCTURE]), 'le planning non plus');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$this->assertEquals(\CsvAction::class, $this->callRoute('GET', 'toolbox/waterReading:exportCsv', ['id' => self::STRUCTURE]), 'qui administre reçoit le CSV');

		$this->assertRefusedToObserver('GET', 'toolbox/waterReading:create', ['structure' => self::STRUCTURE, 'meter' => $eWaterMeter['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterReading:doCreate', [
			'structure' => self::STRUCTURE,
			'meter' => $eWaterMeter['id'],
			'date' => \Clock::date(),
			'value' => '12500',
		]);

		$this->assertRefusedToObserver('GET', 'toolbox/waterReading:update', ['id' => $eWaterReading['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterReading:doUpdate', ['id' => $eWaterReading['id'], 'value' => '12400']);
		$this->assertRefusedToObserver('POST', 'toolbox/waterReading:doDelete', ['id' => $eWaterReading['id']]);

		$this->assertRefusedToObserver('GET', 'toolbox/waterMeter:create', ['structure' => self::STRUCTURE]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterMeter:doCreate', ['structure' => self::STRUCTURE, 'name' => 'Puits des droits', 'digits' => '5']);
		$this->assertRefusedToObserver('GET', 'toolbox/waterMeter:update', ['id' => $eWaterMeter['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterMeter:doUpdate', ['id' => $eWaterMeter['id'], 'name' => 'Forage des droits', 'digits' => '6']);
		$this->assertRefusedToObserver('GET', 'toolbox/waterMeter:change', ['id' => $eWaterMeter['id']]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterMeter:doChange', ['id' => $eWaterMeter['id'], 'date' => \Clock::date(), 'changedValue' => '12600', 'value' => '0', 'digits' => '5']);
		$this->assertRefusedToObserver('POST', 'toolbox/waterMeter:doSkipReading', ['id' => $eWaterMeter['id'], 'week' => \Clock::week()]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterMeter:doUpdateStatus', ['id' => $eWaterMeter['id'], 'status' => WaterMeter::INACTIVE]);

		// Le rythme du rappel des compteurs d'eau règle la structure pour toute l'équipe, au droit du
		// réglage du carnet.
		$this->assertRefusedToObserver('GET', 'toolbox/waterConfiguration:create', ['structure' => self::STRUCTURE]);
		$this->assertRefusedToObserver('POST', 'toolbox/waterConfiguration:doCreate', ['structure' => self::STRUCTURE, 'readingEveryMonths' => '1']);

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\ViewAction::class, $this->callRoute('GET', 'ferme/'.self::STRUCTURE.'/outils/eau'), 'un observateur lit la consommation d\'eau');

	}

	/**
	 * L'écran de l'eau ne propose pas ce que l'action refuse : ni ligne de saisie, ni colonne « Relevé
	 * d'aujourd'hui », ni roue, ni « Nouveau compteur » pour l'observateur, à un compteur comme à
	 * plusieurs, sur l'accueil et sur la carte de la Boîte. Ni l'écart signalé du « Réseau des
	 * droits » et ses corrections, qu'il ne peut pas faire.
	 */
	public function testWaterScreen(): void {

		['eWaterMeterGap' => $eWaterMeterGap, 'eWaterReadingGap' => $eWaterReadingGap] = $this->createWaterDecor();

		$render = function(): string {

			$waters = WaterReadingLib::getSummary($this->eStructure);

			return
				new WaterReadingUi()->getHeader($this->eStructure, new \Collection(), $waters).
				new WaterReadingUi()->getOne($this->eStructure, $waters['cWaterMeter']->first()).
				new WaterReadingUi()->getSeveral($this->eStructure, $waters, new WaterMeter()).
				new WaterReadingUi()->getStart($this->eStructure).
				$this->renderWaterCard($waters);

		};

		$this->connectAs(observe: TRUE);
		$html = $render();

		$this->assertFalse(str_contains($html, 'waterReading:doCreate'), 'l\'observateur n\'a ni la ligne de saisie ni la colonne « Relevé d\'aujourd\'hui »');
		$this->assertFalse(str_contains($html, 'waterReading:update'), 'l\'observateur n\'a pas la roue d\'un relevé');
		$this->assertFalse(str_contains($html, 'waterMeter:update'), 'l\'observateur n\'a pas la roue du compteur');
		$this->assertFalse(str_contains($html, 'waterMeter:change'), 'ni son changement');
		$this->assertFalse(str_contains($html, 'waterMeter:doUpdateStatus'), 'ni sa suppression');
		$this->assertFalse(str_contains($html, 'waterMeter:create'), 'l\'observateur n\'ajoute pas de compteur, depuis l\'écran comme depuis l\'accueil');
		$this->assertFalse(str_contains($html, 'waterConfiguration:create'), 'ni ne règle le rappel');
		$this->assertFalse(str_contains($html, 'waterReading:exportCsv'), 'ni n\'exporte les relevés');

		$this->connectAs(planning: \structure\Role::FULL);
		$html = $render();

		$this->assertTrue(str_contains($html, 'waterReading:doCreate'), 'le planning a la saisie');
		$this->assertTrue(str_contains($html, 'waterReading:update'), 'le planning a la roue d\'un relevé');
		$this->assertTrue(str_contains($html, 'waterMeter:update'), 'le planning a la roue du compteur');
		$this->assertTrue(str_contains($html, 'waterMeter:change'), 'et « Nouveau compteur installé »');
		$this->assertTrue(str_contains($html, 'waterMeter:change?id='.$eWaterMeterGap['id'].'&reading='.$eWaterReadingGap['id']), 'et la déclaration du nouveau compteur sur le relevé signalé');
		$this->assertTrue(str_contains($html, 'waterMeter:doUpdateStatus'));
		$this->assertTrue(str_contains($html, 'waterMeter:create'), 'le planning ajoute un compteur');
		$this->assertTrue(str_contains($html, 'waterConfiguration:create'), 'et règle le rappel');
		$this->assertFalse(str_contains($html, 'waterReading:exportCsv'), 'et n\'exporte pas les relevés, ce qui demande de gérer la structure');

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);
		$html = $render();

		$this->assertEquals(1, substr_count($html, 'waterReading:exportCsv'), 'qui administre a « Télécharger », dans la roue de l\'outil');
		$this->assertTrue(str_contains($this->renderWaterCard(WaterReadingLib::getSummary($this->eStructure)), 'waterReading:doCreate'), 'la carte de la Boîte porte la saisie du planning');

		$this->connectAs(observe: TRUE);
		$html = $this->renderWaterCard(WaterReadingLib::getSummary($this->eStructure));

		$this->assertFalse(str_contains($html, 'waterReading:doCreate'), 'et pas celle de l\'observateur');
		$this->assertTrue(str_contains($html, WaterReadingUi::url($this->eStructure)), 'qui garde la carte de l\'outil');

	}

	/**
	 * Avant le premier relevé il n'y a rien à sortir : « Télécharger » ne se propose pas à qui
	 * administre, et l'action refuse ce que l'écran ne propose pas.
	 */
	public function testWaterExportNeedsAReading(): void {

		$this->connectAs(planning: \structure\Role::FULL, admin: TRUE);

		$this->deleteWater();

		// Un décor n'a pas de `$_POST` à valider : `build()` vérifierait le nom et le nombre de chiffres, que le test choisit.
		WaterMeterLib::create(new WaterMeter(['structure' => $this->eStructure, 'name' => 'Forage des droits', 'digits' => 5]));

		$waters = WaterReadingLib::getSummary($this->eStructure);
		$html = new WaterReadingUi()->getHeader($this->eStructure, new \Collection(), $waters);

		$this->assertFalse(str_contains($html, 'waterReading:exportCsv'), 'sans relevé, « Télécharger » ne se propose pas');
		$this->assertTrue(str_contains($html, 'waterMeter:create'), 'la roue garde « Nouveau compteur »');
		$this->assertEquals(\NotExpectedAction::class, $this->callRoute('GET', 'toolbox/waterReading:exportCsv', ['id' => self::STRUCTURE]), 'et l\'action refuse aussi le CSV sans relevé');

	}

	/**
	 * La Boîte à outils d'une structure qui n'aurait que le premier compteur : c'est à un seul
	 * compteur que la carte porte la ligne de saisie.
	 */
	private function renderWaterCard(array $waters): string {

		$waters['cWaterMeter'] = $waters['cWaterMeter']->find(fn(WaterMeter $eWaterMeter) => $eWaterMeter['id'] === $waters['cWaterMeter']->first()['id']);

		// Le premier compteur vient d'inscrire l'outil : la structure se relit hors du cache du processus.
		$this->forgetFeatures();
		$eStructure = \structure\StructureLib::getById(self::STRUCTURE);

		return new ToolUi()->getList(
			$eStructure,
			new \Collection(),
			RainLib::getGauges($eStructure),
			new \Collection(),
			TreatmentLib::getSummary($eStructure),
			EquipmentLib::getSummary($eStructure),
			$waters
		);

	}

	/**
	 * Le décor part d'une structure sans eau : ce qu'elle porterait se retire, et la transaction du
	 * test le rend.
	 */
	private function deleteWater(): void {

		$cWaterReading = WaterReading::model()
			->select(['id', 'structure', 'meter', 'date', 'changed', 'changedDigits'])
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cWaterReading as $eWaterReading) {
			WaterReadingLib::delete($eWaterReading);
		}

		$cWaterMeter = WaterMeter::model()
			->select(['id', 'structure'])
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cWaterMeter as $eWaterMeter) {
			WaterMeterLib::delete($eWaterMeter);
		}

	}

	/**
	 * Deux compteurs sur la structure de référence, l'un relevé trois fois, l'autre deux fois avec un
	 * écart signalé. Écrit par les Lib sans
	 * `build()` : le décor veut exactement ces valeurs, et c'est la garde des routes qu'on éprouve.
	 * `createdBy` lit le compte connecté, d'où le membre posé d'abord.
	 */
	private function createWaterDecor(): array {

		$this->connectAs(planning: \structure\Role::FULL);

		$this->deleteWater();

		$eWaterMeter = new WaterMeter(['structure' => $this->eStructure, 'name' => 'Forage des droits', 'digits' => 5]);
		WaterMeterLib::create($eWaterMeter);

		$eWaterMeterGap = new WaterMeter(['structure' => $this->eStructure, 'name' => 'Réseau des droits', 'digits' => 4]);
		WaterMeterLib::create($eWaterMeterGap);

		// Un compteur à quatre chiffres relevé à 1 000 puis à 50 a compté 9 050 m³ d'après ses chiffres, plus
		// que les 5 000 au-delà desquels l'écran signale l'écart.
		WaterReadingLib::create(new WaterReading(['structure' => $this->eStructure, 'meter' => $eWaterMeterGap, 'date' => date('Y-m-d', \Clock::timestamp() - 86400 * 5), 'value' => 1000]));

		$eWaterReadingGap = new WaterReading(['structure' => $this->eStructure, 'meter' => $eWaterMeterGap, 'date' => date('Y-m-d', \Clock::timestamp() - 86400), 'value' => 50]);
		WaterReadingLib::create($eWaterReadingGap);

		$eWaterReading = new WaterReading();

		foreach([20 => 12274, 10 => 12386, 3 => 12482] as $days => $value) {

			$eWaterReading = new WaterReading([
				'structure' => $this->eStructure,
				'meter' => $eWaterMeter,
				'date' => date('Y-m-d', \Clock::timestamp() - 86400 * $days),
				'value' => $value,
			]);

			WaterReadingLib::create($eWaterReading);

		}

		return ['eWaterMeter' => $eWaterMeter, 'eWaterReading' => $eWaterReading, 'eWaterMeterGap' => $eWaterMeterGap, 'eWaterReadingGap' => $eWaterReadingGap];

	}

	/**
	 * Des paniers sur la structure de référence, comme le départ les écrit (`CompositionDerivedTest`) :
	 * deux tailles composées sur les semaines 20 à 40 de l'année en cours, d'après la référence
	 * commune, toutes les espèces proposées gardées. Écrit par le Lib sans `build()` : le décor veut
	 * ces valeurs, et c'est la garde des routes qu'on éprouve, pas le départ.
	 */
	private function createCompositionDecor(): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($this->eStructure, \Clock::year());
		$eCompositionPlan['weeks'] = range(20, 40);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$formulas = [];

		foreach(['Petit panier' => [16.0, 6], 'Grand panier' => [25.0, 10]] as $name => [$price, $linesMax]) {

			$formulas[] = new CompositionFormula([
				'plan' => $eCompositionPlan,
				'name' => $name,
				'price' => $price,
				'baskets' => 10,
				'gap' => 5,
				'linesMin' => 4,
				'linesMax' => $linesMax,
				'version' => 0,
			]);

		}

		$eCompositionPlan['cCompositionFormula'] = new \Collection($formulas);

		CompositionPlanLib::create($eCompositionPlan);

		return CompositionPlanLib::getByStructure($this->eStructure);

	}

	/**
	 * Le produit composé que « Utiliser dans un produit » propose. Construit à la main : ce test
	 * n'éprouve rien du produit, que `build()` vérifierait.
	 */
	private function createProductComposition(): \product\Product {

		$eProduct = new \product\Product([
			'structure' => $this->eStructure,
			'status' => \product\Product::ACTIVE,
			'profile' => \product\Product::COMPOSITION,
			'compositionVisibility' => \product\Product::PRIVATE,
			'distribution' => \product\Product::PRODUCTION,
			'name' => 'Panier des droits',
			'unit' => NULL,
			'plant' => NULL,
			'variety' => NULL,
			'vat' => 2,
			'organic' => \product\Product::NO,
			'qualities' => new \Set(),
			'duplicateOf' => new \product\Product(),
			'private' => TRUE,
			'privatePrice' => 16.0,
			'pro' => FALSE,
			'proPrice' => NULL,
		]);

		\product\ProductLib::create($eProduct);

		return $eProduct;

	}

	/**
	 * Un matériel à compteur, un entretien et un entretien fait sur la structure de référence. Écrit
	 * par les Lib sans `build()` : le décor veut exactement ces valeurs, et c'est la garde des routes
	 * qu'on éprouve, pas la saisie.
	 */
	private function createMaintenanceDecor(): array {

		$eTool = \work\ToolLib::getNewTool($this->eStructure, NULL);
		$eTool['name'] = 'Tracteur des droits '.$this->userId;
		\work\ToolLib::create($eTool);

		$eEquipment = new Equipment([
			'structure' => $this->eStructure,
			'tool' => $eTool,
			'meter' => Equipment::HOURS,
			'eToolNew' => new \work\Tool(),
		]);

		EquipmentLib::follow($eEquipment, new EquipmentReading([
			'structure' => $this->eStructure,
			'date' => \Clock::date(),
			'value' => 1250,
		]));

		$eMaintenance = new Maintenance([
			'structure' => $this->eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => 400,
			'everyMonths' => NULL,
			'plannedDate' => NULL,
			'plannedValue' => 1100,
		]);

		MaintenanceLib::create($eMaintenance);

		// La veille à 800 h, relevé que l'entretien fait reprend : l'échéance repart à 1 200 h, sous
		// le relevé du jour, et la vidange reste en retard pour que le bloc de ce qui est dû s'affiche.
		EquipmentReadingLib::create(new EquipmentReading([
			'structure' => $this->eStructure,
			'equipment' => $eEquipment,
			'date' => date('Y-m-d', \Clock::timestamp() - 86400),
			'value' => 800,
		]));

		$eMaintenanceDone = new MaintenanceDone([
			'structure' => $this->eStructure,
			'equipment' => $eEquipment,
			'maintenance' => $eMaintenance,
			'date' => date('Y-m-d', \Clock::timestamp() - 86400),
			'cost' => 80.0,
			'comment' => NULL,
		]);

		MaintenanceDoneLib::create($eMaintenanceDone);

		return ['eEquipment' => $eEquipment, 'eMaintenance' => $eMaintenance, 'eMaintenanceDone' => $eMaintenanceDone];

	}

	/**
	 * L'observateur rencontre `NotAllowedAction` ; le planning, sur la même requête, rencontre autre
	 * chose, succès ou refus de saisie.
	 */
	private function assertRefusedToObserver(string $method, string $path, array $input): void {

		$this->connectAs(observe: TRUE);
		$this->assertEquals(\NotAllowedAction::class, $this->callRoute($method, $path, $input), 'un observateur est refusé sur '.$path);

		$this->connectAs(planning: \structure\Role::FULL);
		$this->assertDiffer(\NotAllowedAction::class, $this->callRoute($method, $path, $input), 'le planning passe la garde de '.$path);

	}

	private function callRoute(string $method, string $path, array $input = []): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];

		if($method === 'GET') {
			[$_GET, $_POST, $_REQUEST] = [$input, [], $input];
		} else {
			[$_GET, $_POST, $_REQUEST] = [[], $input, $input];
		}

		try {
			// Les routes déclarées d'abord, puis la page que désigne le chemin, comme `Page::getPages()`
			// les range pour une requête réelle : les actions (`toolbox/rain:doCreate`) ne sont que là.
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, new \stdClass());
			return 'aucune action';
		} catch(\Action $action) {
			return get_class($action);
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
	}

	private function connectAs(string $planning = \structure\Role::DENIED, bool $observe = FALSE, bool $admin = FALSE): void {

		$eUser = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUser);

		$eMember = new \structure\Member([
			'structure' => $this->eStructure['id'],
			// Le chargement de la structure lit l'exercice et la vue de ventes du membre, que le produit
			// pose toujours en base.
			'viewSellingSales' => \structure\Member::SALE,
			// Le chargement note la visite du jour et l'écrit en base quand elle manque : déjà datée
			// d'aujourd'hui, ce membre sans ligne en base n'est pas écrit.
			'visitedAt' => \Clock::datetime(),
			'role' => new \structure\Role([
				'admin' => $admin,
				'accounting' => \structure\Role::DENIED,
				'accountingAnalyze' => FALSE,
				'selling' => FALSE,
				'planning' => $planning,
				'productionAnalyze' => FALSE,
				'productionObserve' => $observe,
				'commercialisationAnalyze' => FALSE,
			]),
		]);

		$property = self::getMemberCache();
		$property->setValue(NULL, $property->getValue() + [
			$eUser['id'] => new \Collection([$this->eStructure['id'] => $eMember]),
		]);

	}

}
?>
