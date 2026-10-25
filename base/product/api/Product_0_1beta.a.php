<?php
namespace product;

class Product_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'commercialisation';

	public function __construct() {

		$this->list(
			'productList',
			[
				'?category' => Product::getValidator('category'),
				'?status' => Product::getValidator('status'),
			]
		);

		$this->create('product');
		$this->read('product/{id}');
		$this->update('product/{id}');

		$this->delete('product/{id}');

		/**
		 * 'compositionVisibility' ne vaut que pour un panier (profil composition) : il dit si son contenu
		 * est communiqué aux clients de la structure. L'API le rend en lecture — elle sert la structure,
		 * qui voit toujours ses compositions, mais un appelant qui construit une vitrine doit pouvoir
		 * respecter ce choix. Il se fixe à la création et n'est pas modifiable ensuite, comme dans
		 * ProductLib::getPropertiesUpdate().
		 */

		/**
		 * La vignette du produit se dépose en base64 dans le corps, sur sa propre route : la mettre dans
		 * le corps de product/{id} mêlerait treize mégaoctets d'image aux quelques kilooctets des
		 * autres champs, et l'API n'ouvrant pas de transaction, une image posée avant un build qui
		 * échoue resterait en place derrière une réponse 400.
		 *
		 * Poser et remplacer sont le même geste, d'où PUT et non POST. Créer une vignette à la
		 * création du produit n'a pas de sens : la fiche doit exister pour la porter.
		 */
		$this->requestPut('product/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			$content = $data->body['content'] ?? NULL;

			if(is_string($content) === FALSE or $content === '') {
				throw new \ApiException('Missing content', 'missing_content', 400);
			}

			\media\ApiAiLib::put('product-vignette', $data->e, $content);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestDelete('product/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			\media\ApiAiLib::delete('product-vignette', $data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		/**
		 * Le stock d'un produit se pose ou se fait varier, et ce n'est pas la même chose : « j'ai compté
		 * douze kilos en rayon » et « j'en ai rentré douze » écrivent le même nombre pour des raisons
		 * opposées, et seule la seconde se cumule. Le corps porte donc `quantity`, et `sign` quand il
		 * s'agit d'une variation — `+` ou `-`, comme les deux formulaires de l'écran (stock.p.php,
		 * doUpdate). Sans signe, la quantité devient le solde ; `null` efface le solde sans fermer le
		 * suivi.
		 *
		 * Chaque geste laisse une ligne au journal des mouvements, que stockList rend, et recalcule le
		 * solde du produit dans le même passage.
		 */
		$this->requestPatch('product/{id}/stock', [], function(\ApiData $data) {

			if($data->e->acceptStock() === FALSE) {
				throw new \ApiException('This product does not follow its stock', 'accept_stock', 412);
			}

			if(array_key_exists('quantity', $data->body) === FALSE) {
				throw new \ApiException('Missing quantity', 'missing_quantity', 400);
			}

			$sign = $data->body['sign'] ?? NULL;

			if(in_array($sign, [NULL, '+', '-'], TRUE) === FALSE) {
				throw new \ApiException('Unknown sign', 'unknown_sign', 400);
			}

			$fw = new \FailWatch();

			$eStock = new Stock();
			$eStock->build(['quantity', 'comment'], $data->body, new \PropertyList('create', 'api'));

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			if($sign === NULL) {
				StockLib::set($data->e, $eStock['quantity'], $eStock);
			} else {

				// Une variation sans quantité n'a rien à ajouter : l'écran refuse de la même façon
				if($eStock['quantity'] === NULL) {
					Stock::fail('quantity.check');
					throw new \ResourceApiException($fw);
				}

				match($sign) {
					'+' => StockLib::increment($data->e, $eStock['quantity'], $eStock),
					'-' => StockLib::decrement($data->e, $eStock['quantity'], $eStock)
				};

			}

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		/**
		 * Ouvrir et fermer le suivi de stock. Une tête de famille n'en suit jamais : elle ne se vend pas,
		 * et une quantité posée dessus ne serait celle de personne, chaque variante tenant la sienne
		 * (Product::acceptEnableStock()). Fermer le suivi efface le solde et l'historique reste.
		 */
		foreach(['enableStock' => 'acceptEnableStock', 'disableStock' => 'acceptDisableStock'] as $action => $accept) {

			$this->requestPatch('product/{id}/'.$action, [], function(\ApiData $data) use ($action, $accept) {

				if($data->e->$accept() === FALSE) {
					throw new \ApiException('Can not '.$action, 'accept_'.strtolower($action), 412);
				}

				$fw = new \FailWatch();

				match($action) {
					'enableStock' => StockLib::enable($data->e),
					'disableStock' => StockLib::disable($data->e)
				};

				if($fw->ko()) {
					throw new \ResourceApiException($fw);
				}

				$data->pushObject(NULL, $this->getById($data));

			});

		}

		$this->element(
			'product\Product',
			fn() => [
				'id',
				'name', 'additional', 'description',
				'vignette', 'profile', 'distribution', 'category', 'reference', 'compositionVisibility', 'plant', 'variety', 'frozen', 'packaging', 'allergens', 'ingredients', 'origin', 'unit', 'private', 'privatePrice', 'privatePriceInitial', 'privateStep', 'privatePriceSteps', 'pro', 'proPrice', 'proPriceInitial', 'proPackaging', 'proStep', 'proPriceSteps', 'vat',
				'organic', 'qualities', 'stock', 'stockStatus', 'stockLast', 'stockUpdatedAt', 'duplicateOf', 'privateAccountNumber', 'proAccountNumber', 'status'
			],
			[
				'vat' => ProductSetting::getVatApi(),
				'vignette' => fn(\ApiData $data, Product $e) => new \media\ProductVignetteUi()->getUrlByHash($e['vignette'], 'm'),
				'stockLast' => fn(\ApiData $data, Product $e) => $e['stockLast']->empty() ? NULL : $data->exportElement($e['stockLast'])
			]
		);

	}

	// Product::getSelection() (module/Product.e.php) ne sous-sélectionne pas stockLast (juste son
	// id, comme toute propriété déléguée par défaut) — nécessaire ici pour que la corrélation
	// ci-dessus ait de quoi exporter. La liste suit ce que product\Stock déclare rendre : une
	// propriété qui manquerait à la sous-sélection sortirait NULL, avec sa notice. 'stockLast' apparaît déjà comme entrée simple
	// (scalaire) dans le tableau retourné par getSelection() : on la retire avant de la re-déclarer
	// en sous-sélection, sinon les deux entrées coexistent (cf piège getSelection()+[...] du
	// CLAUDE.md, même précaution ici bien que ce ne soit pas un simple '+').
	public function getSelection(\ModuleModel $m): array {

		$select = parent::getSelection($m);
		unset($select[array_search('stockLast', $select, TRUE)]);
		$select['stockLast'] = ['id', 'product', 'balance', 'quantity', 'comment', 'createdAt'];

		return $select;

	}

	public function getList(\ApiData $data): ProductModel {

		return $this->getNewModel($data)
			->whereCategory(fn() => $data->get('category'), if: $data->exists('category'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): ProductModel {

		// Un contenant de famille n'est pas un produit de l'API : il ne se vend jamais, et le
		// contrat n'a aucun champ pour dire ce qu'il est. Sans ce retrait il sortait avec son prix
		// et son stock, indiscernable d'un produit ordinaire — 24 sur les 518 lignes de la
		// structure 7, mesuré le 2026-09-08.
		//
		// Posé ici et non sur getList() : les cinq verbes partagent ce modèle, et un DELETE sur
		// l'id d'un contenant que la liste ne donne pas supprimerait la tête d'une famille. Même
		// raison et même forme que \catalog\Offer_0_1betaApi::getNewModel().
		//
		// `new ProductModel()` et non le singleton : cette lecture s'intercale dans la
		// construction de la requête rendue à l'appelant.
		$productPrincipalIds = new ProductModel()
			->select('id')
			->whereStructure($data->eStructure)
			->whereVariants('>', 0)
			->getColumn('id');

		// Le `if:` n'est pas une précaution : sur une liste vide, le cadriciel rend `(0)` comme
		// condition entière (framework/core/Module.php:1236-1240), pour `NOT IN` comme pour `IN`.
		// Sans lui, une structure sans famille n'exclut pas rien, elle n'expose rien.
		return new ProductModel()
			->whereStructure($data->eStructure)
			->whereId('NOT IN', $productPrincipalIds, if: $productPrincipalIds !== []);

	}

	public function getNewElement(\ApiData $data): Product {

		return new Product([
			'structure' => $data->eStructure
		]);

	}

	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['pro', 'proPrice', 'proPriceInitial', 'private', 'privatePrice', 'privatePriceInitial'],
			],
			'addBuildOnly' => function(&$properties) {

				if(array_intersect($properties, ['pro', 'private'])) {
					$properties[] = 'proOrPrivatePrice';
				}

			}
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
