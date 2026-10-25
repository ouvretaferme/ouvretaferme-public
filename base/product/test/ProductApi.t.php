<?php
namespace product;

/**
 * Test bout-en-bout de l'API publique product\Product (base/product/api/Product_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Lancement : dev/test package=product file=ProductApi
 */
class ProductApiTest extends \dev\ApiTest {

	// 'vat' est exposé/attendu en API comme le libellé texte (cf ProductSetting::getVatApi()), pas l'entier stocké en base.
	const VAT = 'FR_VAT_NORMAL';

	protected function getTransactionModel(): \ModuleModel {
		return new ProductModel();
	}

	protected function getCreateBody(array $override = []): array {

		return array_merge([
			'unit' => NULL, 'name' => 'Test API Product', 'category' => NULL, 'profile' => 'other',
			'reference' => NULL, 'compositionVisibility' => NULL, 'plant' => NULL,
			'variety' => NULL, 'packaging' => NULL, 'ingredients' => NULL,
			'frozen' => NULL, 'allergens' => NULL, 'distribution' => 'production', 'origin' => NULL,
			'additional' => NULL, 'description' => NULL, 'organic' => 'no', 'qualities' => [],
			'duplicateOf' => NULL, 'pro' => TRUE, 'private' => FALSE, 'proOrPrivate' => NULL,
			'proPrice' => 15.0, 'proPriceDiscount' => NULL, 'proPackaging' => NULL, 'privatePrice' => NULL,
			'privatePriceDiscount' => NULL, 'vat' => self::VAT, 'proOrPrivatePrice' => NULL,
			'proAccountNumber' => NULL, 'privateAccountNumber' => NULL
		], $override);

	}

	public function testList(): void {

		$response = $this->callApi('GET', 'productList', get: ['start' => 1, 'limit' => 20]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));

	}

	/**
	 * Un contenant de famille ne sort jamais de l'API.
	 *
	 * Il ne se vend pas et le contrat n'a aucun champ pour dire ce qu'il est : rendu tel quel il
	 * porte un prix et un stock, indiscernable d'un produit ordinaire pour l'intégrateur qui lit
	 * la liste. Mesuré le 2026-09-08 avant correction : 24 contenants sur les 518 lignes de la
	 * structure 7.
	 *
	 * Le retrait est posé sur getNewModel() et non sur getList(), donc les cinq verbes le
	 * partagent : la lecture d'un contenant par son id doit rendre 404, et sa suppression aussi,
	 * sans quoi un DELETE effacerait la tête d'une famille que la liste ne montre pas.
	 */
	public function testFamilyHeadIsNeverExposed(): void {

		$response = $this->callApi('POST', 'product', body: $this->getCreateBody(['name' => 'Produit de famille']));
		$this->assertEquals(201, $response['status']);

		$productId = $response['body']['id'];

		// Le geste réel : le produit devient sa première variante, un contenant naît autour.
		$eProductPrincipal = \product\VariantLib::enable(\product\ProductLib::getById($productId));
		$this->assertTrue($eProductPrincipal->notEmpty(), 'les variantes se sont activées');

		$principalId = $eProductPrincipal['id'];

		// La liste ne le donne pas, la variante si. 500 est le plafond du cadriciel
		// (framework/core/Api.php:503) ; au-delà la réponse ne porte pas de clé `list`.
		$ids = [];

		for($start = 1; $start < 2000; $start += 500) {

			$response = $this->callApi('GET', 'productList', get: ['start' => $start, 'limit' => 500]);
			$this->assertEquals(200, $response['status']);

			$page = array_column($response['body']['list'], 'id');
			$ids = array_merge($ids, $page);

			if($page === []) {
				break;
			}

		}

		$this->assertFalse(in_array($principalId, $ids), 'le contenant ne sort pas de la liste');
		$this->assertTrue(in_array($productId, $ids), 'sa variante, si : le retrait ne prend pas la famille entière');

		// Et les verbes qui portent un id le refusent aussi.
		$this->assertEquals(404, $this->callApi('GET', 'product/{id}', get: ['id' => $principalId])['status'], 'lecture refusée');
		$this->assertEquals(404, $this->callApi('DELETE', 'product/{id}', get: ['id' => $principalId])['status'], 'suppression refusée');

		// Le contrôle dans l'autre sens : la variante reste lisible par son id.
		$this->assertEquals(200, $this->callApi('GET', 'product/{id}', get: ['id' => $productId])['status'], 'la variante reste lisible');

	}

	/**
	 * Une structure qui n'a aucune famille voit quand même ses produits.
	 *
	 * getNewModel() écarte les contenants par `whereId('NOT IN', $ids)`. Sur une liste vide, le
	 * cadriciel rend `(0)` comme condition entière (framework/core/Module.php:1236-1240), pour
	 * `NOT IN` comme pour `IN` : le filtre ne se relâche pas, il vide le résultat. Mesuré le
	 * 2026-09-09 avant le `if:` — 27 échecs sur ce fichier, 17 sur ProductVignetteApiAi, et aucune
	 * structure de la base de dev ne portant alors de famille, l'API produit répondait 404 partout.
	 *
	 * Le cas ne se joue plus tout seul : product/configure/variantFixtureAi donne une famille à la
	 * structure 7, donc une liste non vide, et masque le défaut. Ce test la retire le temps de sa
	 * transaction. Il sait échouer : sans le `if:`, il rend 404.
	 */
	public function testReadSurvivesStructureWithoutFamily(): void {

		Product::model()
			->whereStructure($this->eStructure)
			->whereVariants('>', 0)
			->update(['variants' => 0]);

		$created = $this->callApi('POST', 'product', body: $this->getCreateBody());

		$this->assertEquals(201, $created['status']);

		$response = $this->callApi('GET', 'product/{id}', get: ['id' => $created['body']['id']]);

		$this->assertEquals(200, $response['status'], 'la lecture par id survit');
		$this->assertEquals(200, $this->callApi('GET', 'productList', get: ['start' => 1, 'limit' => 20])['status'], 'la liste aussi');

	}

	public function testCreateReadUpdateDelete(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody());

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Test API Product', $create['body']['name'] ?? NULL);
		$this->assertEquals(self::VAT, $create['body']['vat'] ?? NULL);
		$this->assertTrue(($create['body']['id'] ?? NULL) > 0);

		$id = $create['body']['id'];

		$read = $this->callApi('GET', 'product/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Test API Product', $read['body']['name'] ?? NULL);

		// PUT remplace la totalité des propriétés retournées par ProductLib::getPropertiesUpdate('api')
		// pour ce profil ('other', hors COMPOSITION) : contrairement à getPropertiesCreate(), la liste
		// inclut 'profile'/'distribution'/'pro'/'private' et remplace proPriceDiscount/privatePriceDiscount
		// par proPriceInitial/privatePriceInitial (mapping fait par ProductLib::getPropertiesUpdate() quand
		// $origin === 'api'), et ajoute 'discountable'/'proStep'/'privateStep' (absents de la création).
		$update = $this->callApi('PUT', 'product/{id}', body: [
			'profile' => 'other', 'distribution' => 'production', 'pro' => TRUE, 'private' => FALSE,
			'name' => 'Test API Product 2', 'category' => NULL, 'reference' => NULL, 'unit' => NULL,
			'plant' => NULL, 'variety' => NULL, 'ingredients' => NULL, 'frozen' => NULL, 'packaging' => NULL,
			'allergens' => NULL, 'origin' => NULL, 'additional' => NULL, 'description' => NULL,
			'organic' => 'no', 'qualities' => [],
			'proPrice' => 20.0, 'proPriceInitial' => NULL, 'proPackaging' => NULL, 'proStep' => NULL,
			'privatePrice' => NULL, 'privatePriceInitial' => NULL, 'privateStep' => NULL,
			'vat' => self::VAT, 'proOrPrivatePrice' => NULL,
			'proAccountNumber' => NULL, 'privateAccountNumber' => NULL,
			'discountable' => TRUE
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Product 2', $update['body']['name'] ?? NULL);
		$this->assertEquals(20.0, $update['body']['proPrice'] ?? NULL);

		$delete = $this->callApi('DELETE', 'product/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'product/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);
		$this->assertEquals('not_found', $readAfterDelete['body'][0]['code'] ?? NULL);

	}

	/**
	 * Product_0_1betaApi::getStandalonePropertiesUpdate() groupe
	 * ['pro', 'proPrice', 'proPriceInitial', 'private', 'privatePrice', 'privatePriceInitial'] : un PUT
	 * standalone doit pouvoir mettre à jour un champ hors groupe seul (ici 'name'), sans toucher aux
	 * champs déjà en base (ex. 'proPrice').
	 */
	public function testStandaloneUpdateSingleField(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody());
		$id = $create['body']['id'];

		$update = $this->callApi('PUT', 'product/{id}', body: [
			'properties' => ['name'],
			'name' => 'Test API Product Renamed Alone'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Test API Product Renamed Alone', $update['body']['name'] ?? NULL);
		// proPrice n'a pas été touché par le PUT standalone (seul 'name' était dans 'properties')
		$this->assertEquals($create['body']['proPrice'] ?? NULL, $update['body']['proPrice'] ?? NULL);

	}

	/**
	 * 'pro' fait partie d'un groupe de 6 champs devant être mis à jour ensemble : un PUT standalone qui
	 * n'en cite qu'un seul doit être rejeté, cf ModuleApi::validateStandalonePropertiesUpdate().
	 */
	public function testStandaloneUpdateGroupPartialIsRejected(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody());
		$id = $create['body']['id'];

		$response = $this->callApi('PUT', 'product/{id}', body: [
			'properties' => ['pro'],
			'pro' => FALSE
		], get: ['id' => $id]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_together', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * Un panier créé par l'API garde la visibilité qu'on lui a donnée, et la rend en lecture : c'est
	 * elle qui dit si son contenu est communiqué aux clients de la structure, quand l'API, elle, le
	 * rend toujours (cf selling\Item_0_1betaApi). Sur tout autre profil, 'compositionVisibility.check'
	 * la remet à NULL.
	 */
	public function testReadCompositionVisibility(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody([
			'name' => 'Test API Panier',
			'profile' => 'composition',
			'compositionVisibility' => 'private',
			'pro' => FALSE,
			'private' => TRUE,
			'proPrice' => NULL,
			'privatePrice' => 20.0
		]));

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('private', $create['body']['compositionVisibility'] ?? NULL);

		$read = $this->callApi('GET', 'product/{id}', get: ['id' => $create['body']['id']]);

		$this->assertEquals('private', $read['body']['compositionVisibility'] ?? NULL);

		// Le contrôle sait trouver : sur un produit ordinaire, la visibilité n'a pas de sens
		$other = $this->callApi('POST', 'product', body: $this->getCreateBody(['compositionVisibility' => 'private']));

		$this->assertEquals(201, $other['status']);
		$this->assertTrue(array_key_exists('compositionVisibility', $other['body']));
		$this->assertEquals(NULL, $other['body']['compositionVisibility']);

	}

	/**
	 * Un panier doit dire si son contenu est communiqué aux clients : le formulaire web en fait un champ
	 * requis, et 'compositionVisibility.check' refuse la valeur vide sur ce profil. L'échec porte un
	 * message, faute de quoi l'appelant reçoit un code sans explication et l'écran web un bandeau vide.
	 */
	public function testCreateCompositionRequiresVisibility(): void {

		$response = $this->callApi('POST', 'product', body: $this->getCreateBody([
			'name' => 'Test API Panier sans visibilité',
			'profile' => 'composition',
			'compositionVisibility' => NULL,
			'pro' => FALSE,
			'private' => TRUE,
			'proPrice' => NULL,
			'privatePrice' => 20.0
		]));

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('Product::compositionVisibility.profile', $response['body'][0]['code'] ?? NULL);
		$this->assertTrue(($response['body'][0]['message'] ?? NULL) !== NULL);

	}

	/**
	 * 'compositionVisibility' fait partie de ProductLib::getPropertiesCreate() mais est explicitement
	 * exclu de getPropertiesUpdate() quand $origin === 'api' (ProductLib::getPropertiesUpdate()) :
	 * doit être rejeté en standalone, pas silencieusement accepté.
	 */
	public function testStandaloneUpdateUnknownPropertyIsRejected(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody());
		$id = $create['body']['id'];

		$response = $this->callApi('PUT', 'product/{id}', body: [
			'properties' => ['compositionVisibility'],
			'compositionVisibility' => 'public'
		], get: ['id' => $id]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('property_unknown', $response['body'][0]['code'] ?? NULL);

	}

	/**
	 * stockLast est embarqué en objet complet (balance/quantity), pas juste son id comme toute
	 * propriété déléguée par défaut — cf Product_0_1betaApi::getSelection()/correlation 'stockLast'.
	 */
	public function testStockLastEmbedded(): void {

		$create = $this->callApi('POST', 'product', body: $this->getCreateBody());
		$id = $create['body']['id'];

		$this->assertTrue(array_key_exists('stockLast', $create['body']));
		$this->assertEquals(NULL, $create['body']['stockLast']);

		$eProduct = Product::model()->select('id', 'structure')->whereId($id)->get();
		StockLib::enable($eProduct);
		// set() d'abord : stock démarre à NULL après enable(), un premier increment() dessus
		// calculerait NULL + 12.5 = NULL en SQL (balance/quantity resteraient NULL, cf write()).
		StockLib::set($eProduct, 0.0);
		StockLib::increment($eProduct, 12.5);

		$read = $this->callApi('GET', 'product/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(12.5, $read['body']['stockLast']['balance'] ?? NULL);
		$this->assertEquals(12.5, $read['body']['stockLast']['quantity'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'product/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('api_error', $response['body'][0]['type'] ?? NULL);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		// profile 'other' requiert 'distribution' (Product::build(), callback 'distribution.prepare')
		$response = $this->callApi('POST', 'product', body: $this->getCreateBody(['distribution' => NULL]));

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	/**
	 * La correlation 'vignette' doit rendre une URL du bon média : un ProductVignetteUi mal choisi
	 * (ToolVignetteUi n'a ni le même répertoire ni les mêmes formats) produit une URL vers un fichier
	 * qui n'existe pas, sans que rien ne le signale.
	 */
	public function testVignetteUrl(): void {

		$productId = $this->callApi('POST', 'product', body: $this->getCreateBody())['body']['id'];

		$image = new \Imagick();
		$image->newImage(600, 600, new \ImagickPixel('#4a7c2f'));
		$image->setImageFormat('jpeg');
		$content = base64_encode($image->getImageBlob());
		$image->clear();

		$this->callApi('PUT', 'product/{id}/vignette', get: ['id' => $productId], body: ['content' => $content]);

		$response = $this->callApi('GET', 'product/{id}', get: ['id' => $productId]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(str_contains($response['body']['vignette'], 'product-vignette/'));

	}

}
?>
