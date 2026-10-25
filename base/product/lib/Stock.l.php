<?php
namespace product;

class StockLib extends StockCrud {

	use \Notifiable;

	public static function getByProduct(Product $eProduct): \Collection {

		$cStock = Stock::model()
			->select(Stock::getSelection() + [
				'createdBy' => [
					'firstName', 'lastName', 'vignette'
				]
			])
			->whereProduct($eProduct)
			->sort([
				'id' => SORT_DESC
			])
			->getCollection(0, 20);

		// La vente qui a causé un mouvement est posée sur lui par selling\ProductObserverLib, et seul
		// selling\ProductObserverUi la lit : la version publique n'en a pas.
		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('loadStockHistory', $cStock);
		}

		return $cStock;

	}

	/**
	 * Rattache visuellement les variantes d'une famille à leur contenant,
	 * comme la liste des produits le fait déjà : le contenant devient une ligne de tête, ses
	 * variantes la suivent. Il ne porte jamais de quantité — c'est la raison même du regroupement,
	 * plusieurs porteurs de stock appartenant au même produit.
	 */
	private static function applyFamilyGrouping(\Collection $cProduct, \Collection $cProductVariant, \structure\Structure $eStructure, \Search $search): \Collection {

		// Posé sur chaque ligne, y compris quand aucune famille n'est en jeu : l'UI lit le rôle
		// sans garde, et une liste sans famille n'est pas un cas particulier.
		foreach($cProduct as $eProductLine) {
			$eProductLine['familyRole'] = 'alone';
		}

		if($cProductVariant->empty()) {
			return $cProduct;
		}

		// Sans selectStockColumns() : une ligne de tête ne rend que sa vignette et son nom, elle
		// ne lit ni quantité, ni unité de stock, ni dernier mouvement. Les demander coûtait trois
		// requêtes déléguées de plus pour des valeurs que rien n'affiche.
		$cProductPrincipal = Product::model()
			->select(Product::getSelection())
			->whereStructure($eStructure)
			->whereId('IN', $cProductVariant->getColumnCollection('variantOf'))
			->getCollection(index: 'id');

		[$property, $direction] = self::getSortCriterion($search);

		// Une famille se range sur UNE valeur, pas sur celles de ses lignes prises séparément.
		// Par nom, c'est celui du contenant, qui est le nom de la famille. Sur toute autre
		// colonne, c'est l'extrême que le tri demande — la variante mise à jour le plus
		// anciennement en ordre croissant, le plus récemment en ordre décroissant : la famille se place donc là où sa première ligne se serait placée.
		$keys = [];
		$members = [];
		$singles = [];

		foreach($cProduct as $eProductLine) {

			$principalId = $eProductLine['variantOf']->notEmpty() ? $eProductLine['variantOf']['id'] : NULL;

			if(
				$principalId === NULL or
				$cProductPrincipal->offsetExists($principalId) === FALSE
			) {
				$keys['p'.$eProductLine['id']] = $eProductLine[$property];
				$singles[$eProductLine['id']] = $eProductLine;
				continue;
			}

			$eProductLine['familyRole'] = 'member';
			$members[$principalId][] = $eProductLine;

			$key = 'f'.$principalId;
			$value = $eProductLine[$property];

			if($property === 'name') {
				$keys[$key] = $cProductPrincipal[$principalId]['name'];
			} else if(array_key_exists($key, $keys) === FALSE) {
				$keys[$key] = $value;
			} else if($direction === SORT_DESC) {
				$keys[$key] = max($keys[$key], $value);
			} else {
				$keys[$key] = min($keys[$key], $value);
			}

		}

		uasort($keys, fn($value1, $value2) => ($direction === SORT_DESC) ? ($value2 <=> $value1) : ($value1 <=> $value2));

		$cProductGrouped = new \Collection();

		foreach($keys as $key => $value) {

			if(str_starts_with($key, 'p')) {
				$cProductGrouped[] = $singles[(int)substr($key, 1)];
				continue;
			}

			$principalId = (int)substr($key, 1);

			$eProductPrincipal = $cProductPrincipal[$principalId];
			$eProductPrincipal['familyRole'] = 'head';
			$cProductGrouped[] = $eProductPrincipal;

			foreach($members[$principalId] as $eProductMember) {
				$cProductGrouped[] = $eProductMember;
			}

		}

		return $cProductGrouped;

	}

	/**
	 * La propriété et le sens du tri demandé. `buildSort()` rend un `Sql` quand aucune colonne
	 * n'a été cliquée (le tri par défaut de l'écran) : on range alors par nom, comme lui.
	 */
	private static function getSortCriterion(\Search $search): array {

		$sort = $search->buildSort();

		if(is_array($sort) === FALSE) {
			return ['name', SORT_ASC];
		}

		$property = array_key_first($sort);

		return [$property, $sort[$property]];

	}

	private static function selectStockColumns(): array {

		return [
			'stockExpired' => new \Sql('stockUpdatedAt IS NOT NULL AND stockUpdatedAt < NOW() - INTERVAL 7 DAY', 'bool'),
			'stockLast' => [
				'quantity', 'comment',
				'createdAt',
				'createdBy' => ['firstName', 'lastName', 'vignette']
			],
			'last' => Stock::model()
				->select([
					'minus' => new \Sql('MAX(IF(quantity < 0, DATE(createdAt), null))'),
					'plus' => new \Sql('MAX(IF(quantity > 0, DATE(createdAt), null))')
				])
				->group('product')
				->delegateElement('product')
		];

	}

	public static function getProductsByStructure(\structure\Structure $eStructure, \Search $search = new \Search()): \Collection {

		$search->set('stock', TRUE);
		$search->defaultSort(new \Sql('stock = 0.0, name ASC'));

		Product::model()->select(self::selectStockColumns());

		// Cet écran répond à une seule question, combien j'ai de quoi : il liste les porteurs de
		// stock, rien d'autre.
		$cProduct = ProductLib::getByStructure($eStructure, search: $search);

		// Pas de getByStructure() ici pour porter Product::getSelection() : elle-même exclurait
		// la variante (whereVariantOf(NULL)), donc une sélection posée à la main.
		Product::model()->select(Product::getSelection() + self::selectStockColumns());

		ProductLib::applySearch($eStructure, $search);

		$cProductVariant = Product::model()
			->whereStructure($eStructure)
			->whereStatus('IN', Product::getManipulable())
			->whereVariantOf('!=', NULL)
			->sort($search->buildSort())
			->getCollection();

		// reindex: TRUE (par défaut) et non FALSE : ni $cProduct ni $cProductVariant ne sont
		// indexées par id ici (getCollection() sans index: rend des clés séquentielles), un
		// reindex à FALSE écraserait donc une ligne déjà présente au même index numérique.
		$cProduct->mergeCollection($cProductVariant);

		$cProduct = self::applyFamilyGrouping($cProduct, $cProductVariant, $eStructure, $search);

		// Association des produits avec même nom, variété, calibre mais avec unité différente
		$ccccProduct = Product::model()
			->select([
				'id',
				'name', 'plant', 'variety', 'origin',
				'unit' => Unit::getSelection(),
			])
			->whereStructure($eStructure)
			->whereId('NOT IN', $cProduct)
			->whereStockStatus(Product::INACTIVE)
			// Ces lignes sont proposées au producteur pour qu'il y ouvre un suivi, et
			// acceptEnableStock() dit lesquelles l'acceptent : pas un contenant, qui ne se vend
			// jamais et ne tient donc aucune quantité.
			->whereVariants(0)
			->getCollection(index: ['plant', 'name', 'variety', NULL]);

		foreach($cProduct as $eProduct) {
			$plant = $eProduct['plant']->empty() ? NULL : $eProduct['plant']['id'];
			$eProduct['cProductSiblings'] = $ccccProduct[$plant][$eProduct['name']][$eProduct['variety']] ?? new \Collection();
		}

		return $cProduct;

	}

	public static function getCompatibleProducts(\series\Task $eTask): \Collection {

		$eTask->expects([
			'cultivation',
			'plant', 'variety', 'harvestUnit', 'harvestSize'
		]);

		$unit = $eTask['harvestUnit'] ?? ($eTask['cultivation']->notEmpty() ? $eTask['cultivation']['mainUnit'] : NULL);

		if(
			$eTask['plant']->empty() or
			$unit === NULL
		) {
			return new \Collection();
		}

		if($eTask['variety']->notEmpty()) {
			$eTask['variety']->expects(['name']);
		}

		$eUnit = UnitLib::getOneByStructure($eTask['structure'], $unit);

		$sortVariety = $eTask['variety']->notEmpty() ? 'IF(variety = "'.Product::model()->format($eTask['variety']['name']).'", 1, 0) DESC,' : '';

		return Product::model()
			->select(ProductElement::getSelection() + [
				'unit' => Unit::getSelection(),
			])
			->wherePlant($eTask['plant'])
			->whereUnit($eUnit)
			->whereStock('!=', NULL)
			// Écarte le contenant d'une famille : une récolte versée dessus ne serait le stock
			// de personne, chaque variante tenant le sien.
			->whereVariants(0)
			->sort(new \Sql('
				'.$sortVariety.'
				name ASC
			'))
			->getCollection();

	}

	public static function set(Product $eProduct, ?float $quantity, Stock $eStock = new Stock()): void {

		self::write($eProduct, ($quantity === NULL) ? fn() => NULL : new \Sql($quantity), $eStock);
	}

	public static function increment(Product $eProduct, float $quantity, Stock $eStock = new Stock(), bool $zeroIfNegative = FALSE): void {

		if($quantity === 0.0) {
			return;
		}

		self::write($eProduct, new \Sql('stock + '.$quantity), $eStock, $zeroIfNegative);

	}

	public static function decrement(Product $eProduct, float $quantity, Stock $eStock = new Stock(), bool $zeroIfNegative = FALSE): void {

		if($quantity === 0.0) {
			return;
		}

		self::write($eProduct, new \Sql('stock - '.$quantity), $eStock, $zeroIfNegative);
	}

	protected static function write(Product $eProduct, mixed $sqlValue, Stock $eStock, bool $zeroIfNegative = FALSE): void {

		$eProduct->expects(['structure']);

		$eProductCalculated = Product::model()
			->select([
				'stock',
				'newStock' => $sqlValue
			])
			->whereId($eProduct)
			->get();

		if($eProductCalculated['newStock'] < 0) {

			if($zeroIfNegative) {
				$eProductCalculated['newStock'] = 0;
			} else {

				Stock::fail('balance.negative');
				return;

			}

		}

		$eStock->merge([
			'product' => $eProduct,
			'structure' => $eProduct['structure'],
			'balance' => $eProductCalculated['newStock'],
		]);

		if(
			$eProductCalculated['newStock'] === NULL or
			$eProductCalculated['stock'] === NULL
		) {
			$eStock['quantity'] = NULL;
		} else {
			$eStock['quantity'] = $eProductCalculated['newStock'] - $eProductCalculated['stock'];
		}

		Product::model()->beginTransaction();

		Stock::model()->insert($eStock);

		Product::model()->update($eProduct, [
			'stockLast' => $eStock,
			'stock' => $eProductCalculated['newStock'],
			'stockUpdatedAt' => new \Sql('NOW()')
		]);

		Product::model()->commit();

	}

	public static function enable(Product $eProduct) {

		$eProduct->expects(['structure']);

		Product::model()->beginTransaction();

		Product::model()->update($eProduct, [
			'stock' => NULL,
			'stockStatus' => Product::ACTIVE,
			'stockUpdatedAt' => new \Sql('NOW()')
		]);

		\structure\FeatureLib::updateFeature($eProduct['structure'], 'hasStock', TRUE);

		Product::model()->commit();

	}

	public static function disable(Product $eProduct) {

		Product::model()->beginTransaction();

		Product::model()->update($eProduct, [
			'stock' => NULL,
			'stockStatus' => Product::INACTIVE,
			'stockLast' => NULL,
			'stockUpdatedAt' => NULL
		]);

		self::deleteByProduct($eProduct);

		if(
			Product::model()
				->whereStructure($eProduct['structure'])
				->whereStock('!=', NULL)
				->exists() === FALSE
		) {

			\structure\FeatureLib::updateFeature($eProduct['structure'], 'hasStock', FALSE);

		}

		Product::model()->commit();

	}

	public static function getBookmarksByStructure(\structure\Structure $eStructure): \Collection {

		return StockBookmark::model()
			->select([
				'product',
				'number' => new \Sql('COUNT(*)', 'int')
			])
			->whereStructure($eStructure)
			->group('product')
			->getCollection(index: 'product');

	}

	public static function getBookmarksByProduct(Product $eProduct): \Collection {

		return StockBookmark::model()
			->select(StockBookmark::getSelection() + [
				'plant' => ['name', 'fqn', 'vignette'],
				'variety' => ['name'],
				'createdBy' => ['vignette', 'firstName', 'lastName']
			])
			->whereProduct($eProduct)
			->sort('id')
			->getCollection();

	}

	public static function deleteByProduct(Product $eProduct): void {

		$eProduct->expects(['structure']);

		Stock::model()->beginTransaction();

			StockBookmarkLib::deleteByProduct($eProduct);

			if(\structure\StructureSetting::hasCommercialisationApp()) {
				self::notify('deleteStock', $eProduct);
			}

			Stock::model()
				->whereStructure($eProduct['structure'])
				->whereProduct($eProduct)
				->delete();

		Stock::model()->commit();

	}

	public static function getBookmark(\series\Task $eTask): Product {

		$eTask->expects(['structure', 'plant', 'harvestUnit', 'variety', 'harvestSize', 'cultivation']);

		if($eTask['harvestUnit'] === NULL and $eTask['cultivation']->notEmpty()) {
			$unit = $eTask['cultivation']['mainUnit'];
		} else {
			$unit = $eTask['harvestUnit'];
		}

		$eStockBookmark = StockBookmark::model()
			->select('product')
			->wherePlant($eTask['plant'])
			->whereUnit($unit)
			->whereVariety($eTask['variety'])
			->get();

		if($eStockBookmark->empty()) {
			return new Product();
		} else {
			return $eStockBookmark['product'];
		}

	}

	public static function remember(\series\Task $eTask, Product $eProduct): void {

		$eTask->expects(['structure', 'plant', 'harvestUnit', 'variety', 'harvestSize']);

		StockBookmark::model()->beginTransaction();

			self::forget($eTask);

			if($eProduct->notEmpty()) {

				$eStockBookmark = new StockBookmark([
					'structure' => $eTask['structure'],
					'plant' => $eTask['plant'],
					'unit' => $eTask['harvestUnit'],
					'variety' => $eTask['variety'],
					'size' => $eTask['harvestSize'],
					'product' => $eProduct,
				]);

				StockBookmark::model()->insert($eStockBookmark);

			}

		StockBookmark::model()->commit();

	}

	public static function forget(\series\Task $eTask): void {

		$eTask->expects(['plant', 'harvestUnit', 'variety', 'harvestSize']);

		StockBookmark::model()
			->wherePlant($eTask['plant'])
			->whereUnit($eTask['harvestUnit'])
			->whereVariety($eTask['variety'])
			->delete();

	}

}
?>
