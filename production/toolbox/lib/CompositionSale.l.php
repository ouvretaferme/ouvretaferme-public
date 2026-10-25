<?php
namespace toolbox;

/**
 * Mettre une semaine des paniers en vente dans un produit composé (r37b, planche validée le 01/10).
 *
 * Les paniers composent des espèces dans l'unité du référentiel partagé ; une composition de vente
 * (`selling\Sale`, profil `COMPOSITION`) compose des produits en vente. Le pont est le produit que
 * chaque espèce du plan retient (`CompositionSpecies.product`) : même espèce, même unité que le
 * panier, choisi une fois sur la page « Mettre en vente la semaine » et repris d'une vente à l'autre.
 *
 * Rien ne s'écrit côté vente ici. La page enregistre le produit retenu de chaque espèce, puis ouvre le
 * panneau de composition existant, prérempli (`selling\CompositionAiLib::fillWithWeek()`) : c'est sa
 * validation qui crée la vente.
 *
 * Un canal de vente se nomme ici par la propriété du produit qui l'ouvre, `private` ou `pro`, comme
 * `product\ProductLib` le lit.
 *
 * Les deux mondes ne se comparent pas par identifiant, parce qu'ils n'en partagent aucun :
 * - une ligne du panier porte l'espèce du référentiel partagé (`structure IS NULL`), un produit
 *   l'espèce de la structure, copie qui renvoie à la première par `plant.reference` ;
 * - une ligne porte l'unité partagée, un produit une unité de la structure, qui ne partage avec elle
 *   que son `fqn`.
 */
class CompositionSaleLib {

	/**
	 * Les produits composés que la porte « Mettre en vente » propose, actifs, par nom, chacun avec son
	 * prix sur le canal où il se vend (`price`) : celui que le panneau de composition annonce
	 * (`selling\CompositionAiLib::getPrice()`).
	 */
	public static function getCompositions(\structure\Structure $eStructure): \Collection {

		$cProduct = \product\Product::model()
			->select(['id', 'name', 'private', 'pro', 'privatePrice', 'proPrice'])
			->whereStructure($eStructure)
			->whereProfile(\product\Product::COMPOSITION)
			->whereStatus(\product\Product::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

		foreach($cProduct as $eProduct) {
			$eProduct['price'] = self::getPriceSold($eProduct, self::getType($eProduct));
		}

		return $cProduct;

	}

	/**
	 * Le produit composé `$id` de la structure, actif, avec son prix (`price`) comme `getCompositions()`
	 * le pose, vide s'il n'en est pas un.
	 */
	public static function getComposition(\structure\Structure $eStructure, int $id): \product\Product {

		$eProduct = \product\ProductLib::getCompositionById($id);

		if(
			$eProduct->empty() or
			$eProduct['structure']['id'] !== $eStructure['id'] or
			$eProduct['status'] !== \product\Product::ACTIVE
		) {
			return new \product\Product();
		}

		$eProduct['price'] = self::getPriceSold($eProduct, self::getType($eProduct));

		return $eProduct;

	}

	/**
	 * Le type de la vente qui compose `$eProductComposition`, comme la page de vente le pose : un
	 * produit composé vendu aux particuliers compose une vente aux particuliers, sinon aux
	 * professionnels.
	 */
	public static function getType(\product\Product $eProductComposition): string {

		$eProductComposition->expects(['private']);

		return $eProductComposition['private'] ? 'private' : 'pro';

	}

	/**
	 * Le prix auquel se vend `$eProduct` sur le canal `$type`, NULL quand il n'en a pas.
	 */
	public static function getPriceSold(\product\Product $eProduct, string $type): ?float {

		$eProduct->expects(['privatePrice', 'proPrice']);

		return match($type) {
			'private' => $eProduct['privatePrice'],
			'pro' => $eProduct['proPrice'],
		};

	}

	/**
	 * Le prix qu'une espèce liée à `$eProduct` prend dans les paniers (r39b) : celui du canal où le
	 * produit se vend (`getType()`), et, quand il porte une promotion, le prix d'avant la promotion
	 * (`privatePriceInitial`, `proPriceInitial`). Une promotion est passagère, la valeur d'un panier
	 * se calcule pour toute la saison. NULL quand le produit n'a pas de prix sur ce canal.
	 */
	public static function getPriceBasket(\product\Product $eProduct): ?float {

		$eProduct->expects(['private', 'privatePrice', 'proPrice', 'privatePriceInitial', 'proPriceInitial']);

		$type = self::getType($eProduct);

		$priceInitial = match($type) {
			'private' => $eProduct['privatePriceInitial'],
			'pro' => $eProduct['proPriceInitial'],
		};

		return $priceInitial ?? self::getPriceSold($eProduct, $type);

	}

	/**
	 * Pose sur chaque semaine `$cCompositionWeek` de la taille ouverte `retained` : chacune de ses
	 * lignes a un produit retenu qui la porte encore (`getRetained()`). La porte « Mettre en vente »
	 * mène alors droit au panneau de composition, la page n'ayant rien à demander ; sinon à la page.
	 */
	public static function applyRetained(\structure\Structure $eStructure, \Collection $cCompositionWeek, \Collection $cCompositionSpecies): void {

		$retained = self::getRetained($eStructure, $cCompositionSpecies);

		foreach($cCompositionWeek as $eCompositionWeek) {

			$eCompositionWeek['retained'] = $eCompositionWeek['cCompositionLine']->notEmpty();

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$speciesId = $eCompositionLine['species']['id'];

				if(
					array_key_exists($speciesId, $retained) === FALSE or
					$retained[$speciesId]['unit'] !== $eCompositionLine['unit']['fqn']
				) {
					$eCompositionWeek['retained'] = FALSE;
					break;
				}

			}

		}

	}

	/**
	 * Les produits que les espèces `$cCompositionSpecies` retiennent et qui les portent encore,
	 * `[id d'espèce du plan => ['product' => id du produit, 'unit' => fqn de son unité, 'price' =>
	 * le prix que l'espèce en prend (`getPriceBasket()`)]]`. Le produit d'une variante (r76) porte
	 * l'espèce qu'elle lit.
	 *
	 * Un produit désactivé, supprimé, devenu famille de variantes ou déplacé sur une autre espèce ne
	 * porte plus l'espèce : il ne compte pas, et la page en proposera un autre. L'unité se rend pour
	 * être comparée à celle de chaque ligne, qui peut encore être restée dans une autre unité que
	 * celle de l'espèce (lot r14).
	 */
	public static function getRetained(\structure\Structure $eStructure, \Collection $cCompositionSpecies): array {

		$productIds = [];
		$plants = [];

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			if($eCompositionSpecies['product']->notEmpty()) {
				$productIds[$eCompositionSpecies['id']] = $eCompositionSpecies['product']['id'];
				$plants[$eCompositionSpecies['id']] = $eCompositionSpecies['plant']['id'];
			}
		}

		if($productIds === []) {
			return [];
		}

		$cProduct = \product\Product::model()
			->select([
				'id', 'private', 'privatePrice', 'proPrice', 'privatePriceInitial', 'proPriceInitial',
				'plant' => ['reference'],
				'unit' => ['fqn'],
			])
			->whereId('IN', array_values($productIds))
			->whereStructure($eStructure)
			->whereStatus(\product\Product::ACTIVE)
			->whereVariants(0)
			->whereProfile('!=', \product\Product::COMPOSITION)
			->getCollection(NULL, NULL, 'id');

		$retained = [];

		foreach($productIds as $speciesId => $productId) {

			if($cProduct->offsetExists($productId) === FALSE) {
				continue;
			}

			$eProduct = $cProduct[$productId];

			if(
				$eProduct['plant']->empty() or
				$eProduct['unit']->empty() or
				CompositionLib::getPlantPlan($eProduct['plant']) !== $plants[$speciesId]
			) {
				continue;
			}

			$retained[$speciesId] = ['product' => $productId, 'unit' => $eProduct['unit']['fqn'], 'price' => self::getPriceBasket($eProduct)];

		}

		return $retained;

	}

	/**
	 * Les deux incohérences que la page signale sans geste (r39, décision 3 : « des messages
	 * d'alerte s'affichent en cas d'incohérence »), posées sur la semaine :
	 *
	 * - `value` : la valeur du panier de la semaine, comme la carte la calcule (`CompositionWeekLib`) ;
	 * - `gapProduct` : cette valeur sort de la tolérance de la taille (`CompositionFormula.gap`) face au
	 *   prix du produit composé, que paie le client ; la règle est celle de la carte (`isGap()`) ;
	 * - `priceDiffers` : le prix du produit composé n'est pas celui de la taille.
	 *
	 * Sans prix sur le produit composé, rien à comparer : les deux restent à FALSE.
	 */
	public static function applyAlerts(CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek, \product\Product $eProductComposition): void {

		$values = [];

		foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
			$values[] = CompositionLib::getLineValue($eCompositionLine['quantity'], $eCompositionLine['unitPrice']);
		}

		$eCompositionWeek['value'] = CompositionLib::getWeekValue($values);

		$price = $eProductComposition['price'];

		$eCompositionWeek['gapProduct'] = (
			$price !== NULL and
			CompositionLib::isGap((float)$price, $eCompositionWeek['value'], $eCompositionFormula['gap'])
		);

		$eCompositionWeek['priceDiffers'] = (
			$price !== NULL and
			round((float)$price, 2) !== round((float)$eCompositionFormula['price'], 2)
		);

	}

	/**
	 * La semaine `$week` de la taille, avec ses lignes par nom d'espèce, vide quand la taille n'y a pas
	 * de panier. Elle porte la taille reçue, sur laquelle se lit le droit de l'écrire.
	 */
	public static function getWeek(CompositionFormula $eCompositionFormula, int $week): CompositionWeek {

		$eCompositionWeek = CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $week);

		if($eCompositionWeek->empty()) {
			return $eCompositionWeek;
		}

		$eCompositionWeek['cCompositionLine'] = CompositionLine::model()
			->select([
				'id', 'quantity', 'unitPrice',
				'species' => ['name', 'plant' => ['name', 'fqn', 'vignette']],
				'unit' => ['fqn', 'singular', 'plural', 'short', 'type'],
			])
			->whereWeek($eCompositionWeek)
			->getCollection()
			->sort(fn(CompositionLine $eCompositionLine1, CompositionLine $eCompositionLine2) => \L::getCollator()->compare($eCompositionLine1['species']->getLabel(), $eCompositionLine2['species']->getLabel()));

		return $eCompositionWeek;

	}

	/**
	 * Pose sur chaque ligne de `$eCompositionWeek` ce que la page « Mettre en vente » en affiche :
	 *
	 * - `eCompositionSpecies`, l'espèce du plan qui retient le produit, et sur elle `cProductCandidate`,
	 *   ce que son contrôle accepte (`CompositionSpecies::properties()`, `product.candidate`) ;
	 * - `cProductCandidate`, les produits en vente de la même espèce et de la même unité, par nom, chacun
	 *   avec son prix sur le canal de la vente (`price`) et ce qui le distingue d'un homonyme
	 *   (`resaleNamed`, `applyResale()`) ;
	 * - `eProductProposed`, le produit que la page propose (`getProposed()`), vide sans candidat ;
	 * - `ePlantCreate` et `eUnitCreate`, l'espèce et l'unité de la structure que « Créer le produit »
	 *   passe à la création de produit, vides quand la structure n'en a pas la copie ;
	 * - `linked`, vrai quand l'espèce porte un produit vendu qui est encore un candidat de la ligne :
	 *   la page ne demande pas ce qu'elle sait déjà (planche r39, piste C, « Ne demander que ce qui
	 *   manque »).
	 *
	 * Les candidats se lisent sur les mêmes critères que la liste du panneau de composition
	 * (`selling\SaleLib::getForSale()`), le canal compris quand la vente en a un (`$type`) : un
	 * produit proposé ici se retrouve donc coché là-bas. Sans produit composé choisi (`$type` NULL),
	 * les deux canaux se proposent, et le prix se lit sur celui où le produit se vend.
	 */
	public static function applyLines(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, CompositionWeek $eCompositionWeek, ?string $type): void {

		$cCompositionLine = $eCompositionWeek['cCompositionLine'];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection())
			->wherePlan($eCompositionPlan)
			->whereId('IN', $cCompositionLine->getColumnCollection('species'))
			->getCollection(NULL, NULL, 'id');

		$cCompositionSpecies->setColumn('plan', $eCompositionPlan);

		$plantIds = array_values(array_unique($cCompositionSpecies->getColumnCollection('plant')->getIds()));
		$fqns = array_values(array_unique($cCompositionLine->getColumnCollection('unit')->getColumn('fqn')));

		// Les espèces de la structure que désignent celles du panier, indexées par l'espèce du plan :
		// les produits en vente les portent, et la création de produit les attend.
		$cPlantStructure = CompositionLib::getPlantsStructure($eStructure, $plantIds);

		$plantsStructure = [];

		foreach($cPlantStructure as $ePlant) {
			$plantsStructure[$ePlant['plantPlan']] ??= $ePlant;
		}

		$cUnitStructure = \product\Unit::model()
			->select(['id', 'fqn'])
			->whereStructure($eStructure)
			->whereFqn('IN', $fqns)
			->getCollection(NULL, NULL, 'fqn');

		$cProduct = self::getProducts($eStructure, $cPlantStructure, $type);

		foreach($cProduct as $eProduct) {
			$eProduct['price'] = self::getPriceSold($eProduct, $type ?? self::getType($eProduct));
		}

		foreach($cCompositionLine as $eCompositionLine) {

			$speciesId = $eCompositionLine['species']['id'];
			$fqn = $eCompositionLine['unit']['fqn'];

			if($cCompositionSpecies->offsetExists($speciesId) === FALSE) {
				throw new \NotExpectedAction('Species #'.$speciesId.' is not in plan #'.$eCompositionPlan['id']);
			}

			$eCompositionSpecies = $cCompositionSpecies[$speciesId];
			$plantId = $eCompositionSpecies['plant']['id'];

			$cProductCandidate = $cProduct->find(fn(\product\Product $eProduct) => (
				$eProduct['plant']->notEmpty() and
				$eProduct['unit']->notEmpty() and
				CompositionLib::getPlantPlan($eProduct['plant']) === $plantId and
				$eProduct['unit']['fqn'] === $fqn
			));

			self::applyResale($cProductCandidate);

			$eCompositionSpecies['cProductCandidate'] = $cProductCandidate;

			$eCompositionLine['eCompositionSpecies'] = $eCompositionSpecies;
			$eCompositionLine['cProductCandidate'] = $cProductCandidate;
			$eCompositionLine['eProductProposed'] = self::getProposed($eCompositionSpecies, $cProductCandidate, $eCompositionLine['unitPrice']);
			$eCompositionLine['ePlantCreate'] = $plantsStructure[$plantId] ?? new \plant\Plant();
			$eCompositionLine['eUnitCreate'] = $cUnitStructure->offsetExists($fqn) ? $cUnitStructure[$fqn] : new \product\Unit();
			$eCompositionLine['linked'] = (
				$eCompositionSpecies['product']->notEmpty() and
				$cProductCandidate->offsetExists($eCompositionSpecies['product']['id'])
			);

		}

	}

	/**
	 * Les produits qui peuvent porter les espèces de la structure `$cPlantStructure`, par nom : en
	 * vente, ni famille de variantes ni produit composé, sur le canal `$type` quand la vente en a un,
	 * sur les deux sinon. C'est la liste du panneau de composition (`selling\SaleLib::getForSale()`),
	 * que la page de vente et le panneau « Régler une espèce » (r39b) filtrent ensuite par espèce et
	 * par unité. Chaque produit porte l'espèce partagée qu'il recopie (`plant.reference`) et son unité.
	 */
	public static function getProducts(\structure\Structure $eStructure, \Collection $cPlantStructure, ?string $type): \Collection {

		if($cPlantStructure->empty()) {
			return new \Collection();
		}

		return \product\Product::model()
			->select([
				'id', 'name', 'variety', 'distribution', 'private', 'pro', 'privatePrice', 'proPrice', 'privatePriceInitial', 'proPriceInitial',
				'plant' => ['reference'],
				'unit' => ['fqn', 'singular', 'short'],
			])
			->whereStructure($eStructure)
			->wherePlant('IN', $cPlantStructure)
			->where($type, TRUE, if: $type !== NULL)
			->whereStatus(\product\Product::ACTIVE)
			->whereVariants(0)
			->whereProfile('!=', \product\Product::COMPOSITION)
			->sort(['name' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	/**
	 * Pose sur chaque candidat d'une espèce `resaleNamed` (r39c, décision 5) : le produit est revendu
	 * (`Product.distribution`) et un autre candidat porte le même nom, variété comprise. Ses choix le
	 * disent alors « en revente », seule façon de distinguer deux produits que tout le reste confond :
	 * les deux poireaux de la structure 7 se lisaient « Poireau, 4,00 € / kg ». Un produit revendu sans
	 * homonyme garde son nom nu.
	 */
	public static function applyResale(\Collection $cProductCandidate): void {

		$names = [];

		foreach($cProductCandidate as $eProduct) {
			$names[] = [$eProduct['name'], $eProduct['variety']];
		}

		foreach($cProductCandidate as $eProduct) {

			$eProduct['resaleNamed'] = (
				$eProduct['distribution'] === \product\Product::RESALE and
				count(array_keys($names, [$eProduct['name'], $eProduct['variety']], TRUE)) > 1
			);

		}

	}

	/**
	 * Le produit que la page propose pour une ligne, parmi ses candidats `$cProductCandidate` :
	 * 1. celui que l'espèce retient, s'il en est toujours un ;
	 * 2. sinon le premier au prix que les paniers ont employé pour la ligne (`$unitPrice`), celui dont
	 *    la valeur du panier a été calculée ;
	 * 3. sinon le premier par nom.
	 * Vide sans candidat : la ligne propose alors de créer le produit, et ne poste rien.
	 */
	public static function getProposed(CompositionSpecies $eCompositionSpecies, \Collection $cProductCandidate, ?float $unitPrice): \product\Product {

		if($cProductCandidate->empty()) {
			return new \product\Product();
		}

		if(
			$eCompositionSpecies['product']->notEmpty() and
			$cProductCandidate->offsetExists($eCompositionSpecies['product']['id'])
		) {
			return $cProductCandidate[$eCompositionSpecies['product']['id']];
		}

		if($unitPrice !== NULL) {

			// Le prix d'une ligne se garde à quatre décimales, celui d'un produit se tape au centime.
			$eProductSame = $cProductCandidate->find(fn(\product\Product $eProduct) => ($eProduct['price'] !== NULL and round($eProduct['price'], 2) === round($unitPrice, 2)), limit: 1, default: new \product\Product());

			if($eProductSame->notEmpty()) {
				return $eProductSame;
			}

		}

		return $cProductCandidate->first();

	}

	/**
	 * Enregistre sur chaque espèce des lignes de `$eCompositionWeek` le produit choisi sur la page,
	 * posté en `product[<id de l'espèce du plan>]`, déjà contrôlé (`CompositionSpecies::properties()`).
	 *
	 * Une ligne qui a un candidat poste toujours un produit, celui que la page propose ou celui que le
	 * menu de la ligne a choisi (r39c, décision 3 : la page ne laisse plus un légume hors de la
	 * composition, le panneau qui suit retire un article pour la vente seule). Une ligne sans candidat
	 * ne poste rien et s'écrit vide : le produit qu'elle retenait ne la porte plus. Seul ce qui change
	 * s'écrit.
	 */
	public static function updateProducts(CompositionWeek $eCompositionWeek): void {

		CompositionSpecies::model()->beginTransaction();

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$eCompositionSpecies = $eCompositionLine['eCompositionSpecies'];

				if(CompositionSpeciesLib::getPropertiesChanged($eCompositionSpecies, ['product']) !== []) {
					CompositionSpeciesLib::update($eCompositionSpecies, ['product']);
				}

			}

		CompositionSpecies::model()->commit();

	}

	/**
	 * Au moins une ligne de la semaine a-t-elle un produit choisi ? Le panneau de composition refuse
	 * une composition vide (`Sale::productsList.required`) : la page ne l'ouvre pas pour rien.
	 */
	public static function hasProducts(CompositionWeek $eCompositionWeek): bool {

		foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
			if($eCompositionLine['eCompositionSpecies']['product']->notEmpty()) {
				return TRUE;
			}
		}

		return FALSE;

	}

}
?>
