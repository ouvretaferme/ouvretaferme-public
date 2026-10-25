<?php
namespace work;

/**
 * Les usages du catalogue E-Phy : pour un produit (`work\PhytoProduct`), ce qu'il est autorisé à
 * traiter, sur quelle culture, à quelle dose et avec quel délai avant récolte. Même régime que le
 * produit, et pour les mêmes raisons : lecture seule, aucune borne de structure, un référentiel
 * national réécrit à chaque rechargement du catalogue.
 *
 * Le catalogue écrit un usage par cible, par mode de traitement et par délai, si bien qu'un même
 * produit sur une même culture en porte souvent plusieurs : les rapprocher est le travail de qui
 * les affiche, et la Boîte à outils le fait pour son écran (`PhytoUsageLib::getByProductForPlants()`).
 * Ce que rend cette ressource est la ligne du catalogue, telle qu'elle est publiée.
 *
 * `crop`, `treatment` et `target` sont les libellés de l'ANSES, avec ses pluriels entre parenthèses
 * et ses renvois de note. `plants` porte les espèces du référentiel partagé (`plant\Plant`) que la
 * culture désigne, vide tant que `PhytoUsageLib::CROPS` ne déclare pas cette culture : 8 955 usages
 * sur 18 786 en portaient au 2026-09-30.
 */
class PhytoUsage_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'phytoUsageList',
			[
				'?product' => PhytoUsage::getValidator('product'),
				'?crop' => PhytoUsage::getValidator('crop')
			]
		);

		$this->read('phytoUsage/{id}');

		$this->element(
			'work\PhytoUsage',
			fn() => [
				'id',
				'product',
				'crop', 'treatment', 'target',
				'dose', 'doseUnit', 'harvestDelay',
				'plants'
			]
		);

	}

	public function getList(\ApiData $data): PhytoUsageModel {

		return $this->getNewModel($data)
			->whereProduct(fn() => $data->get('product'), if: $data->exists('product'))
			->whereCrop(fn() => $data->get('crop'), if: $data->exists('crop'));

	}

	public function getNewModel(\ApiData $data): PhytoUsageModel {

		return new PhytoUsageModel();

	}

	public function getNewElement(\ApiData $data): PhytoUsage {

		return new PhytoUsage();

	}

}
?>
