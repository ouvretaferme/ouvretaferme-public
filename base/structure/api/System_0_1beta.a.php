<?php
namespace structure;

class System_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->read('system');
		$this->update('system');

		$this->element(
			'structure\System',
			fn() => [
				'id',
				'place', 'placeLngLat', 'totalArea', 'altitude', 'soil', 'ph',
				'hrFarmer', 'hrEmployee', 'hrOther',
				'sellingDirect', 'sellingSemi', 'sellingLong'
			]
		);

	}

	public function getNewModel(\ApiData $data): SystemModel {

		return new \structure\SystemModel()
			->whereStructure($data->eStructure);

	}

	/**
	 * Sans {id} dans la route, ModuleApi passe par getNewElement() pour charger l'élément et par
	 * getById() pour rendre la réponse : les deux doivent donc désigner la ligne de la structure.
	 */
	public function getNewElement(\ApiData $data): System {
		return $this->getById($data);
	}

	public function getById(\ApiData $data): System {

		$eSystem = $this->getNewModel($data)
			->select(System::getSelection())
			->get();

		if($eSystem->empty()) {
			throw new \ApiException('Not found', 'not_found', 404);
		}

		return $eSystem;

	}

	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				// La répartition doit faire 100 % : la vérifier sur une seule des trois valeurs
				// reviendrait à la comparer aux deux autres telles qu'elles sont en base.
				['sellingDirect', 'sellingSemi', 'sellingLong'],
				// placeLngLat.check exige place, et le vide quand place est nul.
				['place', 'placeLngLat'],
			]
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
