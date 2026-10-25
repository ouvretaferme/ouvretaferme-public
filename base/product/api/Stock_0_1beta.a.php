<?php
namespace product;

/**
 * Les mouvements de stock d'un produit : chaque ligne dit ce qui a été posé ou ajouté, et le solde
 * qui en résulte. Le produit ne porte que le dernier état — `stock`, `stockLast`, `stockUpdatedAt` —
 * là où l'historique dit d'où il vient.
 *
 * Le produit est un filtre obligatoire, comme la saison l'est sur placeList : la table n'a d'index
 * que sur `product`, et une liste bornée à la seule structure balaie la clé primaire
 * (`EXPLAIN` rend `type: index` sans lui, `ref` avec). Un historique de stock se lit d'ailleurs
 * produit par produit, comme StockLib::getByProduct() le fait pour l'écran.
 *
 * Lecture seule ici : un mouvement ne s'écrit pas isolément, il naît d'un geste sur le produit —
 * poser un inventaire, ajouter ou retirer une quantité. Ces gestes vivent sur la ressource product,
 * qui recalcule le solde et la date du dernier mouvement dans le même passage.
 *
 * `createdAt` sort, contrairement à l'usage de l'API : ce n'est pas une trace technique ici mais la
 * date du mouvement, sans laquelle un historique ne se lit pas. `createdBy` reste dehors.
 */
class Stock_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'commercialisation';

	public function __construct() {

		$this->list(
			'stockList',
			[
				'product' => Stock::getValidator('product')
			]
		);

		$this->read('stock/{id}');

		$this->element(
			'product\Stock',
			fn() => [
				'id',
				'product',
				'balance', 'quantity',
				'comment', 'createdAt'
			]
		);

	}

	public function getList(\ApiData $data): StockModel {

		return $this->getNewModel($data)
			->whereProduct($data->get('product'));

	}

	public function getNewModel(\ApiData $data): StockModel {

		return new StockModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Stock {

		return new Stock([
			'structure' => $data->eStructure
		]);

	}

}
?>
