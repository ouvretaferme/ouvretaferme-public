<?php
namespace work;

/**
 * Sur un matériel que suit le carnet d'entretien, un corps complet qui porte `stock` est accepté et
 * son `stock` ignoré, là où une modification partielle qui le nomme est refusée (`property_unknown`).
 */
class Tool_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'toolList',
			[
				'?status' => Tool::getValidator('status')
			]
		);

		$this->create('tool');
		$this->read('tool/{id}');
		$this->update('tool/{id}');
		$this->delete('tool/{id}');


		/**
		 * La vignette de l'outil se dépose en base64 dans le corps, sur sa propre route : la mettre dans
		 * le corps de tool/{id} mêlerait treize mégaoctets d'image aux quelques kilooctets des
		 * autres champs, et l'API n'ouvrant pas de transaction, une image posée avant un build qui
		 * échoue resterait en place derrière une réponse 400.
		 *
		 * Poser et remplacer sont le même geste, d'où PUT et non POST. Créer une vignette à la
		 * création de l'outil n'a pas de sens : la fiche doit exister pour la porter.
		 */
		$this->requestPut('tool/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			$content = $data->body['content'] ?? NULL;

			if(is_string($content) === FALSE or $content === '') {
				throw new \ApiException('Missing content', 'missing_content', 400);
			}

			\media\ApiAiLib::put('tool-vignette', $data->e, $content);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestDelete('tool/{id}/vignette', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			\media\ApiAiLib::delete('tool-vignette', $data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->element(
			'work\Tool',
			fn() => [
				'id', 'name', 'action', 'vignette', 'stock', 'routineName', 'routineValue', 'comment', 'status'
			],
			[
				'vignette' => fn(\ApiData $data, Tool $e) => new \media\ToolVignetteUi()->getUrlByHash($e['vignette'], 'm')
			]
		);

	}

	public function getList(\ApiData $data): ToolModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereStatus('!=', Tool::DELETED, if: $data->exists('status') === FALSE);

	}

	public function getNewModel(\ApiData $data): ToolModel {

		return new ToolModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Tool {

		return new Tool([
			'structure' => $data->eStructure
		]);

	}

	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['routineName', 'routineValue'],
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
