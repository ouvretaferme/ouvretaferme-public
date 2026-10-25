<?php
namespace work;

class ToolLib extends ToolCrud {

	use \ModuleDeferred;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'action', 'stock', 'comment', 'routineName', 'routineValue'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {
		return function(Tool $e) use($origin) {

			if($e->isStandaloneRoutine()) {
				$properties = ['name', 'stock', 'comment', 'routineName', 'routineValue'];
			} else {
				$properties = ToolLib::getPropertiesCreate();
			}

			if(\toolbox\EquipmentLib::isFollowed($e)) {
				$properties = array_values(array_diff($properties, ['stock']));

				// L'API ne connaît pas le carnet : un corps sans compteur le couperait.
				if($origin !== 'api') {
					$properties[] = 'meter';
				}
			}

			return $properties;

		};
	}

	public static function deferred(\structure\Structure $eStructure): \Collection {

		$callback = fn() => Tool::model()
			->select([
				'id', 'name', 'vignette',
				'routineName', 'routineValue'
			])
			->whereStructure($eStructure)
			->getCollection(index: 'id');

		return self::getCache($eStructure['id'], $callback);

	}

	public static function countByStructure(\structure\Structure $eStructure, ?string $routineName): array {

		self::applyWhereRoutineName($routineName);

		return Tool::model()
			->select([
				Tool::ACTIVE => new \Sql('SUM(status = "'.Tool::ACTIVE.'")', 'int'),
				Tool::INACTIVE => new \Sql('SUM(status = "'.Tool::INACTIVE.'")', 'int')
			])
			->whereStructure($eStructure)
			->get()
			->getArrayCopy() ?: [Tool::ACTIVE => 0, Tool::INACTIVE => 0];

	}

	public static function getNewTool(\structure\Structure $eStructure, ?string $routineName, ?string $amm = NULL): Tool {

		$eTool = new \work\Tool([
			'structure' => $eStructure,
			'name' => NULL,
			'routineName' => $routineName,
			'routineValue' => []
		]);

		if($eTool->isStandaloneRoutine()) {
			$eTool['action'] = $eTool->getActionFromRoutine($eStructure);
		}

		if($routineName === 'phyto') {
			self::loadPhyto($eTool, $amm, FALSE);
		}

		return $eTool;

	}

	/**
	 * Ce que le champ d'un produit de traitement affiche : le produit du catalogue choisi, ses
	 * usages autorisés sur les espèces de la structure, ou la saisie à la main.
	 */
	public static function loadPhyto(Tool $eTool, ?string $amm, bool $manual, ?string $query = NULL): void {

		$eTool->expects(['structure']);

		$eTool->add([
			'phytoReprise' => FALSE,
			'phytoTasks' => NULL,
			'phytoTasksKept' => NULL,
		]);

		$eTool['phytoQuery'] = $query;

		$eTool['ePhytoProduct'] = ($amm === NULL) ? new PhytoProduct() : PhytoProductLib::getByAmm($amm);
		$eTool['phytoManual'] = ($manual and $eTool['ePhytoProduct']->empty());

		if($eTool['ePhytoProduct']->empty()) {
			$eTool['cPhytoUsage'] = new \Collection();
		} else {
			$eTool['cPhytoUsage'] = PhytoUsageLib::getByProductForPlants($eTool['ePhytoProduct'], \plant\PlantLib::getByStructure($eTool['structure']));
		}

		if($eTool['phytoReprise'] and $eTool['ePhytoProduct']->notEmpty()) {
			$eTool['eToolPhytoSame'] = self::getPhytoByAmm($eTool['structure'], $eTool['ePhytoProduct']['amm'], $eTool);
		} else {
			$eTool['eToolPhytoSame'] = new Tool();
		}

	}

	/**
	 * Un matériel noté sur des traitements devient leur produit (carte de reprise du carnet de
	 * traitement). Si le produit choisi est déjà enregistré sous la même AMM, c'est lui que les
	 * traitements reçoivent (`ToolLib::update()`), et le formulaire le dit.
	 */
	public static function loadPhytoReprise(Tool $eTool, ?string $amm, bool $manual = FALSE, ?string $query = NULL): void {

		$eTool['routineName'] = 'phyto';
		$eTool['routineValue'] = [];
		$eTool['phytoReprise'] = TRUE;
		[
			'product' => $eTool['phytoTasks'],
			'tool' => $eTool['phytoTasksKept']
		] = \series\TaskLib::countByTool($eTool);

		self::loadPhyto($eTool, $amm, $manual, $query);

	}

	public static function getFromQuery(string $query, \structure\Structure $eStructure, Action $eAction, ?array $properties = []): \Collection {

		if($query !== '') {
			Tool::model()->whereName('LIKE', '%'.$query.'%');
		}

		if($eAction->notEmpty()) {
			Tool::model()->where('action IS NULL or action = '.$eAction['id']);
			self::applyWhereRoutineName(NULL);
		}

		return Tool::model()
			->select($properties ?: Tool::getSelection())
			->whereStructure($eStructure)
			->whereStatus(Tool::ACTIVE)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	public static function getOneByStructure(\structure\Structure $eStructure, mixed $id): Tool {

		return Tool::model()
			->select(Tool::getSelection())
			->whereStructure($eStructure)
			->whereId($id)
			->get();

	}

	public static function getTraysByStructure(\structure\Structure $eStructure): \Collection|\Element {

		return \work\ToolLib::getByStructure($eStructure, routineName: 'tray');

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, ?string $routineName = NULL, \Search $search = new \Search()): \Collection|\Element {

		$expects = 'collection';

		if($id !== NULL) {
			$expects = 'element';
			Tool::model()->whereId($id);
		}

		if($search->get('name')) {
			Tool::model()->whereName('LIKE', '%'.$search->get('name').'%');
		}

		self::applyWhereRoutineName($routineName);

		if($search->get('action') and $search->get('action')->notEmpty()) {
			Tool::model()->whereAction($search->get('action'));
		}

		if($search->get('status')) {
			Tool::model()->whereStatus($search->get('status'));
		} else {
			Tool::model()->whereStatus(Tool::ACTIVE);
		}

		Tool::model()
			->select(Tool::getSelection())
			->whereStructure($eStructure);

		if($expects === 'element') {
			return Tool::model()->get();
		} else {
			return Tool::model()
				->getCollection(NULL, NULL, 'id')
				->sort('name', natural: TRUE);
		}

	}

	private static function applyWhereRoutineName(?string $routineName): void {

		if($routineName) {
			Tool::model()->whereRoutineName($routineName);
		} else {
			self::whereNotProduct()->call(Tool::model());
		}

	}

	/**
	 * Le matériel qui n'est pas un produit : sans routine, ou d'une routine qui n'est pas autonome.
	 */
	private static function whereNotProduct(): \Closure {

		return function() {

			$this->or(
				fn() => $this->whereRoutineName(NULL),
				fn() => $this->whereRoutineName('NOT IN', RoutineLib::getProducts())
			);

		};

	}

	/**
	 * Le formulaire ne rend le champ que si cette liste a au moins une ligne, et un champ absent ne
	 * poste rien, si bien que build() viderait le matériel de l'élément. Le matériel qu'il porte
	 * déjà est donc proposé avec le matériel actif de l'action, même archivé, supprimé ou rangé là
	 * par une fusion, pour que le champ reposte ce qu'il montre.
	 */
	public static function getForWork(\structure\Structure $eStructure, Action $eAction, array $tools = []): \Collection {

		if($eAction->empty()) {
			return new \Collection();
		}

		$whereForAction = self::whereForAction($eAction);

		return Tool::model()
			->select([
				'id', 'name'
			])
			->whereStructure($eStructure)
			->or(
				function() use($whereForAction) {
					$this->whereStatus(Tool::ACTIVE);
					$whereForAction->call($this);
				},
				fn() => $this->whereId('IN', $tools)
			)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	/**
	 * `$toolsKept` est ce que l'élément porte en base : `build()` le garde tel quel, même quand une
	 * fusion y a rangé un produit qui n'a pas d'autre place dans cette action.
	 */
	public static function filterForWork(\structure\Structure $eStructure, Action $eAction, array $tools, array $toolsKept): array {

		$whereForAction = self::whereForAction($eAction);

		return Tool::model()
			->whereId('IN', $tools)
			->whereStructure($eStructure)
			->or(
				fn() => $whereForAction->call($this),
				fn() => $this->whereId('IN', $toolsKept)
			)
			->getColumn('id');

	}

	/**
	 * Le matériel qu'une action propose : rattaché à elle ou à aucune, et qui n'est pas un produit.
	 */
	private static function whereForAction(Action $eAction): \Closure {

		// Lue ici et non dans la fonction rendue : le modèle qui l'exécute ne voit pas les méthodes privées de ce Lib.
		$whereNotProduct = self::whereNotProduct();

		return function() use($eAction, $whereNotProduct) {

			$whereNotProduct->call($this);
			$this->where('action IS NULL or action = '.$eAction['id']);

		};

	}

	public static function filterProducts(\structure\Structure $eStructure, string $routineName, array $products): array {

		return Tool::model()
			->whereId('IN', array_map('intval', array_diff($products, [''])))
			->whereStructure($eStructure)
			->whereRoutineName($routineName)
			->getColumn('id');

	}

	/**
	 * Le formulaire ne rend le champ que si cette liste a au moins une ligne, et un champ absent ne
	 * poste rien, si bien que build() viderait les produits de l'élément. Les produits qu'il porte
	 * déjà, même archivés ou supprimés, sont donc proposés avec les produits actifs, pour que le
	 * champ reposte ce qu'il montre.
	 */
	public static function getProductsForWork(\structure\Structure $eStructure, Action $eAction, array $products = []): \Collection {

		$routineName = RoutineLib::getProductByAction($eAction);

		if($routineName === NULL) {
			return new \Collection();
		}

		return Tool::model()
			->select(Tool::getSelection())
			->whereStructure($eStructure)
			->or(
				fn() => $this->whereStatus(Tool::ACTIVE),
				fn() => $this->whereId('IN', $products)
			)
			->whereRoutineName($routineName)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	/**
	 * Une structure en agriculture biologique, ou en conversion, ne se voit proposer que les produits
	 * que le catalogue E-Phy dit utilisables en bio.
	 */
	public static function isOrganicOnly(\structure\Structure $eStructure): bool {

		$eStructure->expects(['organic']);

		return in_array($eStructure['organic'], [\structure\Structure::YES, \structure\Structure::CONVERSION], TRUE);

	}

	public static function getProductsFromQuery(string $query, \structure\Structure $eStructure, string $routineName, array $exclude = []): \Collection {

		if($query !== '') {

			$conditions = [
				fn() => $this->whereName('LIKE', '%'.$query.'%'),
			];

			// « Essenciel » doit trouver « Essen'ciel », comme dans le catalogue (PhytoProductLib).
			$compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $query);

			if($compact !== '') {
				$conditions[] = fn() => $this->where('REGEXP_REPLACE(name, "[^[:alnum:]]", "") LIKE '.Tool::model()->format('%'.mb_strtolower($compact).'%'));
			}

			Tool::model()->or(...$conditions);

		}

		// Un produit saisi hors catalogue n'a pas d'AMM, donc rien qui dise s'il est utilisable en bio : il reste à qui l'a saisi.
		$organic = ($routineName === 'phyto' and self::isOrganicOnly($eStructure));

		return Tool::model()
			->select(Tool::getSelection())
			->whereId('NOT IN', $exclude, if: $exclude !== [])
			->where('(JSON_TYPE(JSON_EXTRACT(routineValue, "$.amm")) = "NULL" OR JSON_EXTRACT(routineValue, "$.organic") = TRUE)', if: $organic)
			->whereStructure($eStructure)
			->whereStatus(Tool::ACTIVE)
			->whereRoutineName($routineName)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	/**
	 * Pose sur chaque produit de traitement sa ligne du catalogue (`ePhytoProduct`), qui dit si son
	 * AMM a été retirée depuis qu'il a été noté. Vide pour un produit saisi hors catalogue, et pour un
	 * produit dont l'AMM n'y est plus : il n'est plus utilisable.
	 */
	public static function fillPhytoProduct(\Collection $cTool): void {

		$amms = [];

		foreach($cTool as $eTool) {
			if($eTool['routineName'] === 'phyto' and $eTool['routineValue']['amm'] !== NULL) {
				$amms[] = $eTool['routineValue']['amm'];
			}
		}

		$cPhytoProduct = ($amms === []) ? new \Collection() : PhytoProduct::model()
			->select(['amm', 'status', 'usableUntil'])
			->whereAmm('IN', $amms)
			->getCollection(index: 'amm');

		foreach($cTool as $eTool) {

			$amm = ($eTool['routineName'] === 'phyto') ? $eTool['routineValue']['amm'] : NULL;

			$eTool['ePhytoProduct'] = ($amm !== NULL and $cPhytoProduct->offsetExists($amm)) ? $cPhytoProduct[$amm] : new PhytoProduct();

		}

	}

	/**
	 * Les produits du catalogue que la structure n'a pas encore, quelques-uns par ordre alphabétique
	 * quand rien n'est saisi, et combien d'autres la liste laisse de côté (`others`).
	 */
	public static function getPhytoCatalogFromQuery(string $query, \structure\Structure $eStructure): array {

		$amms = array_keys(self::getPhytoIdsByAmm($eStructure));

		$organic = self::isOrganicOnly($eStructure);
		$limit = (trim($query) === '') ? 5 : 10;

		$cPhytoProduct = PhytoProductLib::getCatalog($query, $organic, $limit, $amms);
		$others = ($cPhytoProduct->count() < $limit) ? 0 : PhytoProductLib::countCatalog($query, $organic, $amms) - $cPhytoProduct->count();

		self::fillPhytoCatalog($eStructure, $cPhytoProduct);

		return [
			'cPhytoProduct' => $cPhytoProduct,
			'others' => $others,
		];

	}

	// Un NULL dans la liste d'un NOT IN écarterait toutes les lignes : un produit saisi hors catalogue n'y entre pas.
	public static function getPhytoIdsByAmm(\structure\Structure $eStructure): array {

		$cTool = Tool::model()
			->select([
				'id',
				'amm' => new \Sql('JSON_UNQUOTE(JSON_EXTRACT(routineValue, "$.amm"))', 'string')
			])
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->whereStatus('!=', Tool::DELETED)
			->where('JSON_TYPE(JSON_EXTRACT(routineValue, "$.amm")) = "STRING"')
			->getCollection();

		$ids = [];

		foreach($cTool as $eTool) {
			$ids[$eTool['amm']] = $eTool['id'];
		}

		return $ids;

	}

	private static function fillPhytoCatalog(\structure\Structure $eStructure, \Collection $cPhytoProduct): void {

		if($cPhytoProduct->empty()) {
			return;
		}

		$cPlant = \plant\PlantLib::getByStructure($eStructure);

		foreach($cPhytoProduct as $ePhytoProduct) {
			$ePhytoProduct['treatment'] = (PhytoUsageLib::getDefaultDose(PhytoUsageLib::getByProductForPlants($ePhytoProduct, $cPlant)) ?? ['dose' => NULL, 'unit' => PhytoUsageLib::getDefaultUnit($ePhytoProduct)]) + [
				'water' => NULL,
				'readyToUse' => $ePhytoProduct->isReadyToUse()
			];
		}

	}

	public static function getPhytoByAmm(\structure\Structure $eStructure, string $amm, Tool $eToolExclude = new Tool()): Tool {

		return Tool::model()
			->select(Tool::getSelection())
			->whereStructure($eStructure)
			->whereRoutineName('phyto')
			->whereStatus('!=', Tool::DELETED)
			->whereId('!=', $eToolExclude, if: $eToolExclude->notEmpty())
			->where('JSON_UNQUOTE(JSON_EXTRACT(routineValue, "$.amm")) = '.Tool::model()->format($amm))
			->get();

	}

	/**
	 * Le doublon se refuse à la construction : l'index unique ne lèverait qu'au milieu de
	 * l'enregistrement de l'intervention, qui crée le produit (TaskLib::prepareProducts()).
	 */
	public static function getNewPhyto(\structure\Structure $eStructure, array $input): Tool {

		$eTool = self::getNewTool($eStructure, 'phyto');
		$eTool->build(['name'], $input);

		if(
			$eTool['name'] !== NULL and
			Tool::model()
				->whereStructure($eStructure)
				->whereName($eTool['name'])
				->whereStatus(Tool::ACTIVE)
				->exists()
		) {
			Tool::fail('name.duplicate');
		}

		return $eTool;

	}

	/**
	 * La dose de l'intervention devient celle du produit, comme pour un produit du catalogue.
	 * Des interventions construites ensemble (plusieurs séries, plusieurs traitements) portent
	 * chacune le produit à créer : la première le crée, les suivantes le retrouvent par son nom,
	 * que l'index unique réserve.
	 */
	public static function createPhytoNew(Tool $eTool, ?array $treatment): Tool {

		$eToolCreated = Tool::model()
			->select(Tool::getSelection())
			->whereStructure($eTool['structure'])
			->whereRoutineName('phyto')
			->whereName($eTool['name'])
			->whereStatus(Tool::ACTIVE)
			->get();

		if($eToolCreated->notEmpty()) {
			return $eToolCreated;
		}

		$fw = new \FailWatch();

		$eTool->build(['routineValue'], [
			'routineValue' => [
				'dose' => $treatment['dose'] ?? NULL,
				'unit' => $treatment['unit'] ?? NULL,
				'water' => $treatment['water'] ?? NULL,
			]
		]);

		// La construction de l'intervention a déjà exigé la dose et l'unité : un refus ici est un défaut du code.
		$fw->validate(onKo: fn() => throw new \Exception('Phyto product '.$eTool['name'].' could not be built'));

		self::create($eTool);

		return $eTool;

	}

	public static function createPhytoFromCatalog(\structure\Structure $eStructure, PhytoProduct $ePhytoProduct, ?array $treatment): Tool {

		$eTool = self::getPhytoByAmm($eStructure, $ePhytoProduct['amm']);

		if($eTool->notEmpty()) {
			return $eTool;
		}

		$eTool = self::getNewTool($eStructure, 'phyto');

		$fw = new \FailWatch();

		$eTool->build(['name', 'routineValue'], [
			'name' => self::getFreeName($eStructure, $ePhytoProduct),
			'routineValue' => [
				'amm' => $ePhytoProduct['amm'],
				'dose' => $treatment['dose'] ?? NULL,
				'unit' => $treatment['unit'] ?? NULL,
				'water' => $treatment['water'] ?? NULL,
			]
		]);

		// La construction de l'intervention a déjà exigé la dose et l'unité : un refus ici est un défaut du code.
		$fw->validate(onKo: fn() => throw new \Exception('Phyto product '.$ePhytoProduct['amm'].' could not be built'));

		self::create($eTool);

		return $eTool;

	}

	private static function getFreeName(\structure\Structure $eStructure, PhytoProduct $ePhytoProduct): string {

		[, $max] = Tool::model()->getPropertyRange('name');

		$name = mb_substr($ePhytoProduct->getDisplayName(), 0, $max);

		$exists = Tool::model()
			->whereStructure($eStructure)
			->whereName($name)
			->whereStatus('!=', Tool::DELETED)
			->exists();

		if($exists) {
			$suffix = ' '.$ePhytoProduct['amm'];
			$name = mb_substr($ePhytoProduct->getDisplayName(), 0, $max - mb_strlen($suffix)).$suffix;
		}

		return $name;

	}

	public static function getActionsByStructure(\structure\Structure $eStructure): \Collection {

		return Tool::model()
			->select([
				'action' => ['name']
			])
			->whereStructure($eStructure)
			->whereAction('!=', NULL)
			->group('action')
			->getColumn('action')
			->sort('name');

	}

	public static function create(Tool $e): void {

		// Un produit qui porte le nom d'un matériel de la structure ne crée pas un doublon : le
		// matériel devient ce produit et ses interventions le gardent, comme lorsqu'on modifie le
		// matériel en produit. Un stock ou une observation laissés vides ne l'effacent pas.
		if($e['routineName'] === 'phyto') {

			$eToolMaterial = Tool::model()
				->select('id')
				->whereStructure($e['structure'])
				->whereName($e['name'])
				->whereRoutineName(NULL)
				->whereStatus(Tool::ACTIVE)
				->get();

			if($eToolMaterial->notEmpty()) {

				$e['id'] = $eToolMaterial['id'];

				$properties = array_values(array_filter(
					['name', 'action', 'stock', 'comment', 'routineName', 'routineValue'],
					fn(string $property) => $e[$property] !== NULL
				));

				self::update($e, $properties);
				return;

			}

		}

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Tool::fail('name.duplicate');
			return;
		}

		if($e['routineName'] === 'phyto') {
			\structure\FeatureLib::addToolbox($e['structure'], \toolbox\Favorite::TREATMENT);
		}

	}

	public static function update(Tool $e, array $properties): void {

		$updateMeter = in_array('meter', $properties);
		$properties = array_values(array_diff($properties, ['meter']));

		// Un matériel qui devient un produit déjà enregistré sous la même AMM ne crée pas un doublon :
		// ses interventions reçoivent ce produit, et il s'efface.
		if(
			in_array('routineName', $properties) and
			$e['routineName'] === 'phyto' and
			$e['routineValue']['amm'] !== NULL and
			Tool::model()->whereId($e)->whereRoutineName(NULL)->exists()
		) {

			$eToolProduct = self::getPhytoByAmm($e['structure'], $e['routineValue']['amm'], $e);

			if($eToolProduct->notEmpty()) {
				self::mergeProduct($e, $eToolProduct);
				return;
			}

		}

		try {
			parent::update($e, $properties);
		} catch(\DuplicateException) {
			Tool::fail('name.duplicate');
			return;
		}

		if($updateMeter) {
			\toolbox\EquipmentLib::update($e['eEquipment'], ['meter', 'registration']);
		}

		if(in_array('routineName', $properties)) {

			\series\TaskLib::synchronizeProduct($e);
			\series\RepeatLib::synchronizeProduct($e);
			\sequence\FlowLib::synchronizeProduct($e);

			if($e['routineName'] === 'phyto') {
				\structure\FeatureLib::addToolbox($e['structure'], \toolbox\Favorite::TREATMENT);
			}

		}

	}

	private static function mergeProduct(Tool $e, Tool $eToolProduct): void {

		Tool::model()->beginTransaction();

			\series\TaskLib::mergeProduct($e, $eToolProduct);
			\series\RepeatLib::mergeProduct($e, $eToolProduct);
			\sequence\FlowLib::mergeProduct($e, $eToolProduct);

			self::delete($e);

		Tool::model()->commit();

	}

	public static function delete(Tool $e): void {

		$e->expects(['id', 'structure', 'routineName']);

		if(
			\series\Task::model()
				->whereStructure($e['structure'])
				->where('JSON_CONTAINS(tools, \''.$e['id'].'\') OR JSON_CONTAINS(products, \''.$e['id'].'\')')
				->exists() or
			\sequence\Flow::model()
				->whereStructure($e['structure'])
				->where('JSON_CONTAINS(tools, \''.$e['id'].'\') OR JSON_CONTAINS(products, \''.$e['id'].'\')')
				->exists() or
			(
				$e['routineName'] === 'tray' and
				\series\Cultivation::model()
					->whereStructure($e['structure'])
					->whereVarietiesTool($e)
					->exists()
			) or
			\toolbox\EquipmentLib::isRecorded($e)
		) {

			Tool::model()->beginTransaction();

				Tool::model()->update($e, [
					'status' => Tool::DELETED
				]);

				\toolbox\EquipmentLib::forget($e);

			Tool::model()->commit();

		} else {
			parent::delete($e);
		}

	}

}
?>
