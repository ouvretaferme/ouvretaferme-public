<?php
namespace product;

class ProductLib extends ProductCrud {

	use \Notifiable;

	const PAGE = 100;

	public static function getPropertiesCreate(?string $origin = NULL): \Closure {

		return function(Product $eProduct) use ($origin) {

			$properties = [
				'unit', 'name', 'category', 'profile',
				'reference',
				'compositionVisibility', 'plant', 'variety', 'packaging', 'ingredients', 'frozen', 'allergens',
				'distribution', 'origin', 'additional', 'description', 'organic', 'qualities', 'duplicateOf',
				'pro', 'private', 'proOrPrivate',
				'proPrice', 'proPriceDiscount', 'proPackaging', 'privatePrice', 'privatePriceDiscount', 'vat',
				'proOrPrivatePrice',
				'proAccountNumber', 'privateAccountNumber'
			];

			// \ModuleApi::convertProperties() exige que le corps porte toute la liste :
			// une propriété nouvelle refuserait tous les appelants existants.
			if($origin === 'api') {
				return $properties;
			}

			// `variantOf` se construit après `profile` (variantOf.check l'exige) et AVANT
			// les champs inféodés : leurs callbacks `.raw` lisent le rattachement en cours
			// de construction pour ne jamais construire ce que le formulaire n'offre pas.
			// `preparationDays` et `preparationTime` ne sont pas de la liste : le blocage des commandes se règle après coup,
			// sur un produit qui existe. Le formulaire de création
			// ne l'offre donc pas, et `build()` refuserait de toute façon ce qu'il ne poste pas.
			return array_merge(
				['unit', 'name', 'profile', 'variantOf', 'category'],
				array_slice($properties, 4),
				['privatePriceSteps', 'proPriceSteps', 'variantList']
			);

		};

	}

	/**
	 * ⚠️ `$steps` est pris par référence et réécrit : l'appelant récupère la table normalisée, pas
	 * celle qu'il a passée.
	 */
	public static function checkPriceSteps(mixed &$steps): bool {

		if(is_array($steps) === FALSE) {
			return FALSE;
		}

		$checked = [];

		foreach($steps as $step) {

			if(is_array($step) === FALSE) {
				return FALSE;
			}

			$from = $step['from'] ?? '';
			$price = $step['price'] ?? '';

			// Une ligne entièrement vide est une ligne ajoutée puis abandonnée.
			if(
				($from === '' or $from === NULL) and
				($price === '' or $price === NULL)
			) {
				continue;
			}

			if(
				is_numeric($from) === FALSE or
				is_numeric($price) === FALSE or
				(float)$from <= 0 or
				(float)$price < 0
			) {
				return FALSE;
			}

			$checked[] = [
				'from' => round((float)$from, 2),
				'price' => round((float)$price, 4)
			];

		}

		if(count($checked) > ProductSetting::PRICE_STEPS_MAX) {
			return FALSE;
		}

		usort($checked, fn(array $first, array $second) => $first['from'] <=> $second['from']);

		if(count(array_unique(array_column($checked, 'from'))) !== count($checked)) {
			return FALSE;
		}

		$steps = $checked;

		return TRUE;

	}

	public static function checkPriceStepsDecrease(array $steps, ?float $price): bool {

		if($price === NULL) {
			return TRUE;
		}

		foreach($steps as $step) {

			if($step['price'] >= $price) {
				return FALSE;
			}

			$price = $step['price'];

		}

		return TRUE;

	}

	/**
	 * Ce filtre ne fait pas double emploi avec `checkPriceStepsDecrease()`, qui garde la saisie : le
	 * prix de base peut baisser **après** l'écriture et passer sous un palier déjà enregistré.
	 */
	public static function getEffectivePriceSteps(array $steps, ?float $price): array {

		if($price === NULL) {
			return [];
		}

		$effective = [];

		foreach($steps as $step) {

			if($step['price'] < $price) {

				$effective[] = $step;
				$price = $step['price'];

			}

		}

		return $effective;

	}

	public static function createForStructure(\structure\Structure $eStructure): void {

		$eProduct = new Product([
			'name' => ProductUi::getShippingName(),
			'structure' => $eStructure,
			'profile' => Product::SHIPPING,
			'unit' => NULL,
			'private' => TRUE,
			'pro' => TRUE,
			'vat' => ProductSetting::VAT_UNKNOWN,
			'organic' => Product::NO,
			'qualities' => new \Set(),
			'status' => Product::SPECIAL,
			'discountable' => FALSE,
			'duplicateOf' => new Product()
		]);

		self::create($eProduct);

	}

	// Un envoi d'image réécrit le fichier du hash que porte le produit (media/index reprend ce hash) :
	// une vignette reprise d'un autre produit reçoit donc son propre fichier, sans quoi changer
	// l'image de l'un changerait celle de l'autre
	public static function copyVignette(string $vignette): string {

		$hash = NULL;
		$basename = \media\MediaUi::getInstance('product-vignette')->getBasenameByHash($vignette);

		new \media\ProductVignetteLib()->copy(new Product(), $hash, 'product-vignette', $basename);

		return $hash.substr($vignette, 20);

	}

	public static function create(Product $e): void {

		// create() accepte un élément construit à la main, hors build() : createForStructure(),
		// l'import CSV et les adhésions n'ont pas de raison de connaître ces colonnes.
		$e['variantOf'] ??= new Product();
		$e['cProductVariant'] ??= new \Collection();
		$e['duplicateOf'] ??= new Product();

		// Dupliquer copie une configuration, dont le régime de prix. `priceMode` n'est jamais
		// posté, la copie retomberait sinon sur le défaut de colonne. Avant l'insertion :
		// prepareCreate() relit le principal en base pour chaque ligne copiée.
		if(
			$e['variantOf']->empty() and
			$e['duplicateOf']->notEmpty() and
			$e['cProductVariant']->notEmpty()
		) {
			$e['priceMode'] = self::getById($e['duplicateOf']['id'], ['id', 'priceMode'])['priceMode'];
		}

		if($e['variantOf']->notEmpty()) {
			VariantLib::prepareCreate($e);
		}

		// Posé après prepareCreate(), qui ne copie du principal que les champs absents
		$e['vignette'] ??= NULL;

		if($e['vignette'] !== NULL) {
			$e['vignette'] = self::copyVignette($e['vignette']);
		}

		Product::model()->beginTransaction();

		try {
			parent::create($e);
		}
		catch(\DuplicateException) {
			Product::fail('reference.duplicate');
			// L'écriture refusée n'a rien écrit, et rollBack() viderait toute la pile, transaction de l'appelant comprise
			Product::model()->commit();
			return;
		}

		if($e['variantOf']->notEmpty()) {

			// `variants + 1` est atomique sur une ligne qu'on ne détient pas ; self::update()
			// rejouerait de surcroît toute la propagation aux variantes pour une colonne.
			Product::model()
				->whereId($e['variantOf'])
				->update([
					'variants' => new \Sql('variants + 1')
				]);

			if(\structure\StructureSetting::hasCommercialisationApp()) {
				self::notify('createVariant', $e);
			}

		} else {

			foreach($e['cProductVariant'] as $eProductVariant) {

				$eProductVariant['variantOf'] = $e;

				self::create($eProductVariant);

			}

			$e['variants'] = $e['cProductVariant']->count();

		}

		Product::model()->commit();

	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {

		return function(Product $eProduct) use ($origin) {

			$eProduct->expects(['profile', 'variantOf', 'variants', 'priceMode']);

			$isVariant = $eProduct['variantOf']->notEmpty();

			$properties = [];

			if($origin !== 'api') {
				$properties[] = 'variantOf';
			}

			if(
				$isVariant === FALSE and
				in_array($eProduct['profile'], Product::getProfiles('profile'))
			) {
				$properties[] = 'profile';
			}

			if($eProduct['profile'] !== Product::COMPOSITION) {
				$properties[] = 'distribution';
				$properties[] = 'pro';
				$properties[] = 'private';
			}

			$properties = array_merge($properties, [
				'name', 'category', 'reference', 'unit',
				'plant', 'variety', 'ingredients', 'frozen', 'packaging', 'allergens',
				'origin', 'additional', 'description', 'organic', 'qualities',
				'proPrice', 'proPriceDiscount', 'proPackaging', 'proStep', 'privatePrice', 'privatePriceDiscount', 'privateStep', 'vat',
				'proOrPrivatePrice',
			]);

			if($origin !== 'api') {
				$properties[] = 'compositionVisibility';
				$properties[] = 'preparationDays';
				$properties[] = 'preparationTime';
			} else {
				$properties[array_search('proPriceDiscount', $properties)] = 'proPriceInitial';
				$properties[array_search('privatePriceDiscount', $properties)] = 'privatePriceInitial';
			}

			// Au livre des recettes, le formulaire ne porte pas les comptes et ne doit pas effacer ceux déjà posés.
			// L'API garde sa liste, et l'étape `cashReceipts` y refuse un changement.
			if(
				$eProduct['structure']->hasAccounting() and
				($origin === 'api' or $eProduct->acceptUpdateAccounting())
			) {
				$properties = array_merge($properties, ['proAccountNumber', 'privateAccountNumber']);
			}

			$properties[] = 'discountable';

			// `ModuleApi::convertProperties()` (`framework/core/Api.php:405`) échoue sur toute
			// propriété de la liste absente du corps : une propriété ajoutée ici refuserait tous les
			// appelants existants de l'API, à la modification comme à la création. Les paliers y sont
			// donc en lecture seule.
			if($origin !== 'api') {
				$properties[] = 'privatePriceSteps';
				$properties[] = 'proPriceSteps';
			}

			if($isVariant) {

				foreach(array_merge(Product::VARIANT_PROPAGATED, Product::VARIANT_FIXED, Product::VARIANT_FORBIDDEN) as $property) {
					array_delete($properties, $property);
				}

			}

			// Une ligne ne construit son prix que si c'est le sien à vendre : sinon un formulaire
			// qui ne rend plus ces champs les écraserait avec NULL. `proOrPrivatePrice` part avec
			// eux, il arbitre des prix que le POST n'a plus. Même liste que
			// Product::addVariantCallbacks(), à `proOrPrivate` près, absent d'ici.
			$hasOwnPrice = $isVariant ?
				($eProduct['variantOf']['priceMode'] !== Product::SHARED) :
				($eProduct['variants'] === 0 or $eProduct['priceMode'] === Product::SHARED);

			if($hasOwnPrice === FALSE) {

				foreach(array_merge(Product::VARIANT_PRICE, ['privatePriceDiscount', 'proPriceDiscount', 'proOrPrivatePrice']) as $property) {
					array_delete($properties, $property);
				}

			}

			return $properties;

		};

	}

	public static function update(Product $e, array $properties): void {

		$e->expects(['variantOf', 'variants']);

		if(array_delete($properties, 'privatePriceDiscount')) {
			$properties[] = 'privatePriceInitial';
		}

		if(array_delete($properties, 'proPriceDiscount')) {
			$properties[] = 'proPriceInitial';
		}

		array_delete($properties, 'proOrPrivatePrice');

		// Inféodés et figés : un appelant qui donne sa propre liste (import CSV) ne peut pas
		// les écrire sur une variante, seul propagate() les y descend. La garde vient APRÈS la
		// conversion des pseudo-propriétés : avant, un privatePriceDiscount converti
		// réintégrait privatePriceInitial dans la liste et fabriquait un prix barré.
		if($e['variantOf']->notEmpty()) {

			$properties = array_values(array_diff($properties, Product::VARIANT_PROPAGATED, Product::VARIANT_FIXED));

			if(
				array_intersect(Product::VARIANT_PRICE, $properties) !== [] and
				self::getById($e['variantOf']['id'], ['id', 'priceMode'])['priceMode'] === Product::SHARED
			) {
				$properties = array_values(array_diff($properties, Product::VARIANT_PRICE));
			}

		}

		// Toute la liste pouvait relever de champs qu'une variante ne détient pas (un quick
		// de prix sur une famille en prix commun) : rien à écrire, et un UPDATE sans SET
		// serait une erreur SQL
		if($properties === []) {
			return;
		}

		$e['eProductVariantOfOld'] ??= $e['variantOf'];

		$isVariantOfChanged = ($e['variantOf']->is($e['eProductVariantOfOld']) === FALSE);

		Product::model()->beginTransaction();

		if($e['variantOf']->notEmpty()) {
			VariantLib::prepareUpdate($e, $properties);
		}

		try {
			parent::update($e, $properties);
		}
		catch(\DuplicateException) {
			Product::fail('reference.duplicate');
			// L'écriture refusée n'a rien écrit, et rollBack() viderait toute la pile, transaction de l'appelant comprise
			Product::model()->commit();
			return;
		}

		self::synchronizeDependencies($e, $properties);

		if($e['variantOf']->empty()) {
			VariantLib::propagate($e, $properties);
		}

		if($isVariantOfChanged) {
			VariantLib::synchronizeVariantOf($e, $e['eProductVariantOfOld']);
		}

		Product::model()->commit();

	}

	// Les conséquences d'une écriture sur les autres modules : offres catalogue, relations de
	// composition, mercuriale. Appelée pour le produit écrit, et par VariantLib::propagate()
	// pour chaque variante que la propagation vient de toucher.
	public static function synchronizeDependencies(Product $e, array $properties): void {

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('synchronizeDependencies', $e, $properties);
		}

	}

	public static function getCompositionById(mixed $id, array $properties = []): Product {

		Product::model()->whereProfile(Product::COMPOSITION);

		return self::getById($id, $properties);

	}

	/** Si un produit de la collection porte ce nom, à la casse et aux espaces près. */
	public static function hasName(\Collection $cProduct, string $name): bool {

		$normalize = fn(string $value): string => mb_strtolower(preg_replace('/\s+/u', '', $value));

		$name = $normalize($name);

		return $cProduct->find(fn(Product $eProduct) => $normalize($eProduct['name']) === $name)->notEmpty();

	}

	public static function getFromQuery(string $query, \structure\Structure $eStructure, ?string $type = NULL, ?array $excludeIds = [], ?array $onlyIds = [], ?string $stock = NULL, bool $withComposition = TRUE, ?array $properties = [], ?string $distribution = NULL, ?array $unitIds = NULL, ?array $plantIds = NULL): \Collection {

		$match = NULL;

		if(strpos($query, '#') === 0 and ctype_digit(substr($query, 1))) {

			Product::model()->whereId(substr($query, 1));

		} else if($query !== '') {

			$keywords = [];

			$query = trim(preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $query));

			$words = array_filter(preg_split('/\s+/', $query));

			if(count($words) > 0) {

				foreach(preg_split('/\s+/', $query) as $word) {
					$keywords[] = '*'.$word.'*';
				}

				$match = 'MATCH(name, origin, variety) AGAINST ('.Product::model()->format(implode(' ', $keywords)).' IN BOOLEAN MODE)';

				Product::model()
					->where($match.' > 0')
					->sort(self::getQuerySort([
						new \Sql($match.' DESC'),
						'name' => SORT_ASC
					], $plantIds));
			}

		} else {
			Product::model()->sort(self::getQuerySort(['name' => SORT_ASC], $plantIds));
		}

		$cProduct = self::getQueryCollection($eStructure, $type, $excludeIds, $onlyIds, $stock, $withComposition, $properties, $distribution, $unitIds, $plantIds);

		if($match === NULL) {
			return $cProduct;
		}

		if(self::existsPrincipalByStructure($eStructure) === FALSE) {
			return $cProduct;
		}

		$productPrincipalIds = self::getPrincipalIdsFromQuery($match, $eStructure);

		if($productPrincipalIds === []) {
			return $cProduct;
		}

		Product::model()
			->whereVariantOf('IN', $productPrincipalIds)
			->sort(self::getQuerySort(['name' => SORT_ASC], $plantIds));

		$cProductVariant = self::getQueryCollection($eStructure, $type, $excludeIds, $onlyIds, $stock, $withComposition, $properties, $distribution, $unitIds, $plantIds);

		$productIds = $cProduct->getIds();

		foreach($cProductVariant as $eProductVariant) {

			if(in_array($eProductVariant['id'], $productIds) === FALSE) {
				$cProduct[] = $eProductVariant;
			}

		}

		return $cProduct;

	}

	/**
	 * Sort tôt une structure sans aucune famille (l'immense majorité tant que l'ouverture se
	 * fait ferme par ferme) du coût de la requête fulltext ci-dessous, sept fois plus
	 * chère (mesuré : 6-8 ms contre 41-64 ms) que ce contrôle d'existence sur product_unique_0.
	 * Un modèle neuf, comme getPrincipalIdsFromQuery() ci-dessous et pour la même raison.
	 */
	private static function existsPrincipalByStructure(\structure\Structure $eStructure): bool {

		return new ProductModel()
			->whereStructure($eStructure)
			->whereVariants('>', 0)
			->exists();

	}

	/**
	 * Les contenants que la recherche trouve, pour atteindre leurs variantes par le nom de leur
	 * famille. Bornée à la structure, au statut et au texte cherché : l'éligibilité d'une ligne à
	 * la vente se juge sur la variante, pas sur le contenant, qui n'a ni offre ni canal propre.
	 * Un modèle neuf plutôt que le singleton, dont les conditions de la recherche en cours sont
	 * déjà posées.
	 */
	private static function getPrincipalIdsFromQuery(string $match, \structure\Structure $eStructure): array {

		return new ProductModel()
			->select('id')
			->whereStructure($eStructure)
			->whereStatus(Product::ACTIVE)
			->whereVariants('>', 0)
			->where($match.' > 0')
			->getCollection()
			->getIds();

	}

	private static function getQueryCollection(\structure\Structure $eStructure, ?string $type, ?array $excludeIds, ?array $onlyIds, ?string $stock, bool $withComposition, ?array $properties, ?string $distribution, ?array $unitIds, ?array $plantIds): \Collection {

		switch($type) {
			case 'pro' :
				Product::model()->wherePro(TRUE);
				break;
			case 'private' :
				Product::model()->wherePrivate(TRUE);
				break;
		}

		return Product::model()
			->select($properties ?: Product::getSelection())
			->whereStructure($eStructure)
			->whereId('NOT IN', $excludeIds, if: $excludeIds !== [])
			->whereId('IN', $onlyIds, if: $onlyIds !== [])
			->whereStatus(Product::ACTIVE)
			->whereVariants(0)
			->whereProfile('!=' , Product::COMPOSITION, if: $withComposition === FALSE)
			->whereStock(NULL, if: $stock === 'enable')
			->whereDistribution($distribution, if: $distribution !== NULL)
			->whereUnit('IN', $unitIds, if: $unitIds !== NULL)
			// Les produits sans espèce restent proposés : eux seuls n'ont rien à comparer
			// à l'espèce attendue par l'appelant.
			->where(new \Sql('plant IS NULL OR plant IN ('.implode(',', array_map('intval', $plantIds ?: [0])).')'), if: $plantIds !== NULL)
			->getCollection();

	}

	/**
	 * Le tri d'une recherche, les produits sans espèce relégués en second.
	 *
	 * Quand l'appelant borne les espèces, un produit qui n'en porte aucune reste
	 * proposé mais n'est plus une réponse de premier rang : il n'a pas été retenu
	 * parce qu'il correspond, seulement parce que rien ne permet de l'écarter.
	 */
	private static function getQuerySort(array $sort, ?array $plantIds): array {

		if($plantIds === NULL) {
			return $sort;
		}

		return array_merge([new \Sql('plant IS NULL')], $sort);

	}

	public static function getByPlant(\plant\Plant $ePlant, mixed $index = NULL): \Collection {

		return Product::model()
			->select(Product::getSelection())
			->wherePlant($ePlant)
			->whereVariants(0)
			->sort('name')
			->getCollection(index: $index);

	}

	public static function getByPlants(\structure\Structure $eStructure, \Collection $cPlant): \Collection {

		if($cPlant->empty()) {
			return new \Collection();
		}

		return Product::model()
			->select([
				'id', 'plant', 'stock', 'stockStatus',
				'unit' => Unit::getSelection()
			])
			->whereStructure($eStructure)
			->wherePlant('IN', $cPlant)
			->whereVariants(0)
			->whereStatus('!=', Product::DELETED)
			->sort('name')
			->getCollection();

	}

	/**
	 * Les quantités vendues de chaque produit, indexées par produit, sur les ventes livrées d'aujourd'hui
	 * inclus aux `$days` jours qui suivent. Elles vivent dans selling, que la version publique retire.
	 */
	public static function getSoldForComingDays(\structure\Structure $eStructure, int $days): \Collection {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return new \Collection();
		}

		return self::notify('getSoldForComingDays', $eStructure, $days)[0];

	}

	public static function existsByStructure(\structure\Structure $eStructure): bool {

		return Product::model()
			->whereStructure($eStructure)
			->whereStatus('IN', Product::getManipulable())
			->exists();

	}

	public static function countByStructure(\structure\Structure $eStructure, \Search $search = new \Search()): array {

		self::applySearch($eStructure, $search);

		return Product::model()
			->select([
				'category',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereStructure($eStructure)
			->whereVariantOf(NULL)
			->whereStatus('IN', Product::getManipulable())
			->group('category')
			->getCollection()
			->makeArray(function($eProduct, ?int &$key) {
				$key = $eProduct['category']->empty() ? '' : $eProduct['category']['id'];
				return $eProduct['count'];
			});

	}

	/**
	 * Les produits actifs de la structure, têtes de famille seulement.
	 *
	 * ⚠️ `whereVariantOf(NULL)` et non l'ensemble : une variante ne porte pas de numéro de compte, elle
	 * tient celui de sa tête (`ProductVariantAi::testAccountNumberIsRefusedOnAVariant()`). La compter
	 * gonflerait un décompte dont le seul usage est de dire combien de comptes restent à poser.
	 */
	public static function countActiveByStructure(\structure\Structure $eStructure): int {

		return Product::model()
			->whereStructure($eStructure)
			->whereStatus(Product::ACTIVE)
			->whereVariantOf(NULL)
			->count();

	}

	/**
	 * Un produit actif porte-t-il déjà son propre numéro de compte ?
	 *
	 * ⚠️ Les deux colonnes et non la première seule : une structure qui ne vend qu'aux professionnels
	 * ne renseigne que `proAccountNumber`, et la lire seule la dirait non paramétrée.
	 */
	public static function hasAccountByStructure(\structure\Structure $eStructure): bool {

		return Product::model()
			->whereStructure($eStructure)
			->whereStatus(Product::ACTIVE)
			->whereVariantOf(NULL)
			->where('privateAccountNumber IS NOT NULL OR proAccountNumber IS NOT NULL')
			->exists();

	}

	/**
	 * Retire des produits de la structure les numéros de compte que `$classesKept` ne contient pas, à la
	 * réinitialisation de la comptabilité (`ResetLib::reset()`) : le plan qui les portait est effacé.
	 *
	 * Écriture de masse en tableau, une par colonne : le critère se juge colonne par colonne, et le geste
	 * retire une valeur de fiche sans rien changer d'autre au produit.
	 */
	public static function removeAccountNumbers(\structure\Structure $eStructure, array $classesKept): void {

		foreach(['privateAccountNumber', 'proAccountNumber'] as $property) {

			Product::model()
				->whereStructure($eStructure)
				->where($property, 'NOT IN', $classesKept)
				->update([$property => NULL]);

		}

	}

	public static function getWithReferenceByStructure(\structure\Structure $eStructure): \Collection {

		return Product::model()
			->select(Product::getSelection())
			->whereStructure($eStructure)
			->whereStatus('IN', Product::getManipulable())
			->whereVariants(0)
			->whereReference('!=', NULL)
			->getCollection();

	}

	public static function getByStructure(\structure\Structure $eStructure, ?Category $eCategory = NULL, bool $selectSales = FALSE, \Search $search = new \Search(), ?int $page = NULL): \Collection {

		$select = self::selectList($eStructure, $selectSales, 'productVariantOf');

		self::applySearch($eStructure, $search);

		$search->validateSort(['name', 'id', 'stock', 'stockUpdatedAt']);

		$cProduct = Product::model()
			->select($select)
			->whereCategory($eCategory, if: $eCategory !== NULL)
			->whereStructure($eStructure)
			->whereVariantOf(NULL)
			->whereStatus('IN', Product::getManipulable())
			->sort($search->buildSort())
			->getCollection($page === NULL ? NULL : $page * self::PAGE, $page === NULL ? NULL : self::PAGE);

		return $cProduct;

	}

	public static function getVariantsByPrincipal(\structure\Structure $eStructure, \Collection $cProductPrincipal, bool $selectSales = FALSE): \Collection {

		if($cProductPrincipal->empty()) {
			return new \Collection();
		}

		$select = self::selectList($eStructure, $selectSales, 'product');

		return Product::model()
			->select($select)
			->whereStructure($eStructure)
			->whereVariantOf('IN', $cProductPrincipal)
			->whereStatus('IN', Product::getManipulable())
			->sort(['variantOf' => SORT_ASC, 'variantPosition' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

	}

	private static function selectList(\structure\Structure $eStructure, bool $selectSales, string $turnoverProperty): array {

		$select = Product::getSelection();

		if(
			$selectSales and
			\structure\StructureSetting::hasCommercialisationApp()
		) {
			$select += array_merge([], ...self::notify('selectSales', $eStructure, $turnoverProperty));
		}

		if($eStructure->hasAccounting()) {

			\structure\StructureLib::connectDatabase($eStructure);

			$select['proAccount?'] = fn($e) => \account\Account::delegate($e, 'proAccountNumber');
			$select['privateAccount?'] = fn($e) => \account\Account::delegate($e, 'privateAccountNumber');

		}

		return $select;

	}

	public static function getShippingByStructure(\structure\Structure $eStructure): Product {

		// La clé porte le numéro de la sélection : un élément mis en cache avant l'ajout d'une
		// colonne n'a pas la propriété, et `expects()` échoue pendant les 24 h du cache.
		return \Cache::redis()->query('product-shipping-2-'.$eStructure['id'], function() use ($eStructure) {

			$eProduct = self::getShipping($eStructure);

			// Seule la création de la structure pose ce produit, et une structure n'a aucun geste pour
			// le reposer : sans lui, toute vente qui porte des frais de livraison lève.
			if($eProduct->empty()) {
				self::createForStructure($eStructure);
				$eProduct = self::getShipping($eStructure);
			}

			return $eProduct;

		}, 86400);

	}

	private static function getShipping(\structure\Structure $eStructure): Product {

		return Product::model()
			->select(ProductElement::getSelection())
			->whereStructure($eStructure)
			->whereProfile(Product::SHIPPING)
			->get();

	}

	/**
	 * Les comptes qu'un produit sans compte propre reçoit de la configuration de la structure, par
	 * profil, les deux clés posées pour chacun. La configuration vit dans accounting/, absent de la
	 * version publique : sans observateur, aucun profil n'a de compte.
	 */
	public static function getAccountNumbersByProfile(\structure\Structure $eStructure): array {

		$profileAccountNumber = array_merge([], ...self::notify('getProfileAccountNumber', $eStructure));

		$accountNumbers = [];

		foreach(Product::model()->getPropertyEnum('profile') as $profile) {
			$accountNumbers[$profile] = [
				'privateAccountNumber' => $profileAccountNumber[$profile]['privateAccountNumber'] ?? NULL,
				'proAccountNumber' => $profileAccountNumber[$profile]['proAccountNumber'] ?? NULL,
			];
		}

		return $accountNumbers;

	}

	/**
	 * Le taux de TVA proposé à la création d'un produit. Il se règle dans la configuration des
	 * ventes, absente de la version publique : le formulaire s'ouvre alors sans taux choisi, comme
	 * lorsque aucun package n'observe l'événement.
	 */
	public static function getDefaultVat(\structure\Structure $eStructure): ?int {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return NULL;
		}

		return self::notify('getDefaultVat', $eStructure)[0] ?? NULL;

	}

	// Le nombre de groupes de produits encore à convertir (`offerGroups`) est posé par
	// catalog\ProductObserverLib, et seul catalog\ProductObserverUi le lit : aucun défaut ne le précède.
	public static function loadIndex(\structure\Structure $eStructure): void {

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('loadIndex', $eStructure);
		}

	}

	// Les ventes, les grilles et les compositions du produit sont posées sur lui par
	// selling\ProductObserverLib, et seul selling\ProductObserverUi les lit : la version publique n'en a pas.
	public static function loadDisplay(\structure\Structure $eStructure, Product $e): void {

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			self::notify('loadDisplay', $eStructure, $e);
		}

	}

	public static function applySearch(\structure\Structure $eStructure, \Search $search): void {

		if($search->get('name')) {
			self::whereLikeWithVariants($eStructure, 'name', '%'.$search->get('name').'%');
		}

		if($search->get('reference')) {
			self::whereLikeWithVariants($eStructure, 'reference', '%'.strtoupper(toFqn($search->get('reference'))).'%');
		}

		if($search->get('stock')) {
			Product::model()->whereStockStatus(Product::ACTIVE);
		}

		if($search->get('profile')) {
			Product::model()->whereProfile($search->get('profile'));
		}

		if($search->get('distribution')) {
			Product::model()->whereDistribution($search->get('distribution'));
		}

		if($search->get('plant')) {
			$cPlant = \plant\PlantLib::getFromQuery($search->get('plant'), $eStructure);
			Product::model()->wherePlant('IN', $cPlant);
		}

		if($eStructure->hasAccounting()) {
			if($search->get('noAccount') === TRUE) {

				$profiles = array_keys(array_filter(self::getAccountNumbersByProfile($eStructure), fn(array $accountNumbers) => $accountNumbers['privateAccountNumber'] !== NULL));

				Product::model()
					->where('privateAccountNumber IS NULL')
					->whereProfile('NOT IN', $profiles, if: $profiles !== []);

			} else if($search->get('privateAccountNumber')) {

				$accountNumber = $search->get('privateAccountNumber');
				$accountNumbersByProfile = self::getAccountNumbersByProfile($eStructure);

				// Un produit sans compte propre est trouvé par celui qu'il hérite de la configuration
				$wheres = [
					fn() => $this->wherePrivateAccountNumber($accountNumber),
					fn() => $this->whereProAccountNumber($accountNumber)
				];

				foreach(['privateAccountNumber', 'proAccountNumber'] as $property) {

					$profiles = array_keys(array_filter($accountNumbersByProfile, fn(array $accountNumbers) => $accountNumbers[$property] === $accountNumber));

					if($profiles !== []) {
						$wheres[] = fn() => $this
							->where($property.' IS NULL')
							->whereProfile('IN', $profiles);
					}

				}

				Product::model()->or(...$wheres);

			}
		}

	}

	private static function whereLikeWithVariants(\structure\Structure $eStructure, string $property, string $value): void {

		// `new ProductModel()` et non le singleton : cette lecture s'intercale dans la construction
		// de la requête de recherche, qu'une requête sur le même model effacerait.
		$cProductVariant = new ProductModel()
			->select(['variantOf'])
			->whereStructure($eStructure)
			->where('variantOf IS NOT NULL')
			->where($property, 'LIKE', $value)
			->getCollection();

		Product::model()->or(
			fn() => $this->where($property, 'LIKE', $value),
			fn() => $this->whereId('IN', $cProductVariant->getColumnCollection('variantOf'))
		);

	}

	/**
	 * Range les variantes d'une même famille les unes sous les autres, en gardant l'indexation par
	 * id dont dépend OfferLib::prepareCollection().
	 *
	 * Le contenant n'est jamais dans la liste — getForType() ne le propose pas —, il est posé sur
	 * chaque variante en `eProductFamily`, pour que l'écran écrive son nom une fois au-dessus d'elles.
	 */
	public static function orderByFamily(\Collection $cProduct): \Collection {

		$families = [];

		// Le défaut est posé pour tout le monde, y compris quand la liste ne porte aucune famille :
		// l'écran lit alors la clé sans garde.
		foreach($cProduct as $eProduct) {

			$eProduct['eProductFamily'] = new Product();

			if($eProduct['variantOf']->notEmpty()) {
				$families[$eProduct['variantOf']['id']][] = $eProduct['id'];
			}

		}

		if($families === []) {
			return $cProduct;
		}

		$cProductOrdered = new \Collection();
		$placed = [];

		foreach($cProduct as $eProduct) {

			if($eProduct['variantOf']->empty()) {

				$cProductOrdered[$eProduct['id']] = $eProduct;

				continue;

			}

			$principal = $eProduct['variantOf']['id'];

			if(isset($placed[$principal])) {
				continue;
			}

			$placed[$principal] = TRUE;

			// L'ordre de la famille est celui que le producteur a rangé sur la fiche produit, et non
			// l'ordre alphabétique du reste de la liste : « Toute petite » vient avant « Grosse ».
			usort(
				$families[$principal],
				fn($first, $second) => $cProduct[$first]['variantPosition'] <=> $cProduct[$second]['variantPosition']
			);

			foreach($families[$principal] as $variant) {

				$eProductVariant = $cProduct[$variant];

				$eProductVariant['eProductFamily'] = $eProductVariant['variantOf'];

				$cProductOrdered[$variant] = $eProductVariant;

			}

		}

		return $cProductOrdered;

	}

	public static function getForType(\structure\Structure $eStructure, string $type, ?array $ids = NULL): \Collection {

		$cProduct = Product::model()
			->select(Product::getSelection())
			->whereStructure($eStructure)
			->whereId('IN', $ids, if: $ids !== NULL)
			->where($type, TRUE)
			->whereStatus(Product::ACTIVE)
			->whereVariants(0)
			->sort(['name' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

		// Le regroupement est posé ici et non chez chaque appelant : les quatre sont des panneaux de
		// choix de produits, et deux variantes d'une même famille portent le même nom, donc se
		// mélangent aux voisines sans lui. Il pose aussi les défauts que ces panneaux lisent.
		return self::orderByFamily($cProduct);

	}

	/**
	 * Le produit porté par un rapport d'analyse, une offre de catalogue, un article vendu ou un
	 * contrat d'AMAP. Les packages analyze, catalog, selling et amap y répondent, et ils sont absents
	 * de la version publique.
	 */
	public static function isUsed(Product $e): bool {

		if(
			\structure\StructureSetting::hasAnalyzePackage() === FALSE and
			\structure\StructureSetting::hasCommercialisationApp() === FALSE
		) {
			return FALSE;
		}

		return in_array(TRUE, self::notify('isUsed', $e), TRUE);

	}

	public static function delete(Product $e): void {

		$e->expects(['id', 'profile', 'variantOf', 'variants']);

		if($e['variants'] > 0) {
			Product::fail('deleteVariants');
			return;
		}

		if(
			$e['variantOf']->notEmpty() and
			$e['variantOf']['variants'] === 1
		) {
			Product::fail('deleteLastVariant');
			return;
		}

		Product::model()->beginTransaction();

			\product\StockLib::disable($e);

			// Avant l'aiguillage : les deux branches valent disparition des prix personnalisés et de
			// la mercuriale, que la ligne parte vraiment ou passe en DELETED.
			if(\structure\StructureSetting::hasCommercialisationApp()) {
				self::notify('detachProduct', $e);
			}

			if(
				// La ligne elle-même : une variante supprimée en douceur garde son
				// rattachement, la faire pointer vers un néant serait rompre une clé sans
				// que rien ne le signale. La famille disparaît des écrans, son passé reste
				// lisible tel qu'il a été vécu.
				//
				// `new ProductModel()` et non le singleton `Product::model()` : les deux
				// écritures qui suivent le réemploient sur le même élément, et une condition
				// posée ici y survivrait.
				new ProductModel()
					->whereVariantOf($e)
					->exists() or
				self::isUsed($e)
			) {

				if(\structure\StructureSetting::hasCommercialisationApp()) {
					self::notify('deleteProduct', $e);
				}

				Product::model()->update($e, [
					'reference' => NULL,
					'status' => Product::DELETED
				]);

			} else {

				Product::model()->delete($e);

			}

			if($e['variantOf']->notEmpty()) {

				// Même raison qu'à la création : le décrément est atomique.
				Product::model()
					->whereId($e['variantOf'])
					->update([
						'variants' => new \Sql('variants - 1')
					]);

			}

		Product::model()->commit();

	}

}
?>
