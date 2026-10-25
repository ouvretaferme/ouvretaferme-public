<?php
namespace work;

/**
 * Le catalogue E-Phy des produits phytopharmaceutiques, publié par l'ANSES sur data.gouv.fr sous
 * licence ouverte et rechargé chaque semaine (`PhytoProductLib::reload()`). Il porte les produits
 * autorisés, et les produits retirés tant qu'un de leurs usages reste utilisable (`usableUntil`),
 * le temps d'écouler un stock.
 *
 * En lecture seule, et sans borne de structure : ce n'est pas un cadrage oublié. La table ne porte
 * aucune colonne de structure, c'est un référentiel national identique pour tous, et son seul auteur
 * est le rechargement hebdomadaire (`PhytoProduct::canWrite()` rend FALSE). Le produit qu'une
 * structure note est un `work\Tool` à routine phyto, qui recopie ce qu'il en prend et ne garde que
 * l'AMM : c'est lui, et non ce catalogue, qui porte une donnée de la structure.
 *
 * `amm` est le numéro d'autorisation de mise sur le marché, unique et stable d'une publication à
 * l'autre : c'est par lui qu'un produit se retrouve, l'identifiant d'une ligne n'ayant de sens qu'ici.
 *
 * Pas de borne obligatoire sur la liste : 1 798 produits au 2026-09-30, que la pagination par
 * identifiant parcourt sans peine, et c'est ainsi qu'un client recopie le catalogue chez lui.
 */
class PhytoProduct_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'phytoProductList',
			[
				'?amm' => PhytoProduct::getValidator('amm'),
				'?status' => PhytoProduct::getValidator('status'),
				'?organic' => new \FilterValidator('bool'),
				'?amateur' => new \FilterValidator('bool'),
				'?search' => new \FilterValidator(['text8', 'min' => 1, 'max' => 50])
			]
		);

		$this->read('phytoProduct/{id}');

		$this->element(
			'work\PhytoProduct',
			fn() => [
				'id',
				'amm', 'name', 'otherNames',
				'functions', 'formulation',
				'organic', 'amateur',
				'substances',
				'status', 'usableUntil'
			]
		);

	}

	public function getList(\ApiData $data): PhytoProductModel {

		$mPhytoProduct = $this->getNewModel($data)
			->whereAmm(fn() => $data->get('amm'), if: $data->exists('amm'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereOrganic(fn() => $data->get('organic'), if: $data->exists('organic'))
			->whereAmateur(fn() => $data->get('amateur'), if: $data->exists('amateur'));

		if($data->exists('search')) {

			// Chercher un produit par son nom, un second nom ou le début de son AMM est la recherche de
			// la Boîte à outils : ses conditions vivent dans le Lib, qui les sert aux deux.
			$mPhytoProduct->or(...PhytoProductLib::getNameConditions($data->get('search')));

		}

		return $mPhytoProduct;

	}

	public function getNewModel(\ApiData $data): PhytoProductModel {

		return new PhytoProductModel();

	}

	public function getNewElement(\ApiData $data): PhytoProduct {

		return new PhytoProduct();

	}

}
?>
