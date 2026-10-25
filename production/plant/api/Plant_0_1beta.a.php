<?php
namespace plant;

class Plant_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		/**
		 * Le référentiel national des espèces n'a pas de structure, donc pas de `whereStructure()` qui
		 * le borne : une liste de l'API ne s'en passe que pour un référentiel national. Il se lit parce
		 * que `reference` de `plantList` le désigne, il ne s'écrit jamais (`getNewModel()`).
		 */
		$this->list(
			'plantListGlobal',
			get:
				fn($data) => $this->getNewModel($data)
					->whereStructure(NULL)
		);

		$this->list(
			'plantList',
			get:
				fn($data) => $this->getNewModel($data)
					->whereStructure($data->eStructure)
		);

		$this->create('plant');
		$this->read('plant/{id}');
		$this->update('plant/{id}');
		$this->delete('plant/{id}');


		/**
		 * La vignette de la culture se dépose en base64 dans le corps, sur sa propre route : la mettre dans
		 * le corps de plant/{id} mêlerait treize mégaoctets d'image aux quelques kilooctets des
		 * autres champs, et l'API n'ouvrant pas de transaction, une image posée avant un build qui
		 * échoue resterait en place derrière une réponse 400.
		 *
		 * Poser et remplacer sont le même geste, d'où PUT et non POST. Créer une vignette à la
		 * création de la culture n'a pas de sens : la fiche doit exister pour la porter.
		 */
		$this->requestPut('plant/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			$content = $data->body['content'] ?? NULL;

			if(is_string($content) === FALSE or $content === '') {
				throw new \ApiException('Missing content', 'missing_content', 400);
			}

			\media\ApiAiLib::put('plant-vignette', $data->e, $content);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestDelete('plant/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			\media\ApiAiLib::delete('plant-vignette', $data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->element(
			'plant\Plant',
			fn(\ApiData $data, Plant $e) => array_merge(
				[
					'id', 'fqn', 'name', 'cycle', 'color', 'family'
				],
				$e['structure']->empty()
					? ['usageVegetable', 'usageFruit', 'usageSmallFruit', 'usageFlower', 'usageAromatic', 'usageMushroom', 'usageField']
					: ['reference', 'vignette', 'plantsSafetyMargin', 'seedsSafetyMargin', 'status']
			),
			[
				'vignette' => fn(\ApiData $data, Plant $e) => new \media\PlantVignetteUi()->getUrlByHash($e['vignette'], 'm')
			]
		);

	}

	/**
	 * Le référentiel partagé (structure NULL) se lit mais ne s'écrit pas : getById() sert aussi à
	 * update() et delete(), l'élargir sans distinguer la méthode laisserait n'importe quelle clé API
	 * renommer une culture pour toutes les structures.
	 */
	public function getNewModel(\ApiData $data): PlantModel {

		if($data->routeInfo['requestMethod'] === 'GET') {

			return new PlantModel()
				->or(
					fn() => $this->whereStructure($data->eStructure),
					fn() => $this->whereStructure(NULL),
				);

		} else {

			return new PlantModel()->whereStructure($data->eStructure);

		}

	}

	public function getNewElement(\ApiData $data): Plant {

		return new Plant([
			'structure' => $data->eStructure
		]);

	}

}
?>
