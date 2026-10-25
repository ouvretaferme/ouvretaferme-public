<?php
namespace structure;

class Configuration_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'general';

	public function __construct() {

		$this->read('configuration');

		$this->element(
			'structure\Configuration',
			fn(\ApiData $data, Configuration $e) => array_merge(
				[
					'id',
					'hasVat', 'vatNumber', 'vatChargeability', 'vatDebitsOption',
					'stockNotes', 'stockNotesUpdatedAt', 'stockNotesUpdatedBy'
				],
				// Saisons et planches n'ont de sens que pour une structure qui produit : une boutique ou
				// une AMAP n'a pas de plan de culture. isProducer() plutôt
				// qu'une comparaison sur la propriété : ApiDocLib lit le source de cette closure et
				// prend tout littéral entre quotes pour un nom de champ, commentaires compris.
				$data->eStructure->isProducer() ? [
					'seasonFirst', 'seasonLast',
					'defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth',
					'organicCertifier',
				] : []
			)
		);

	}

	public function getNewModel(\ApiData $data): ConfigurationModel {

		return new \structure\ConfigurationModel()
			->whereStructure($data->eStructure);

	}

	/**
	 * Sans {id} dans la route, ModuleApi charge l'élément par getNewElement() et rend la réponse par
	 * getById() : les deux doivent désigner la ligne de la structure (cf. System_0_1beta).
	 */
	public function getNewElement(\ApiData $data): Configuration {
		return $this->getById($data);
	}

	public function getById(\ApiData $data): Configuration {

		$eConfiguration = $this->getNewModel($data)
			->select(Configuration::getSelection())
			->get();

		if($eConfiguration->empty()) {
			throw new \ApiException('Not found', 'not_found', 404);
		}

		// Les saisons et les planches vivent dans le réglage de production du plan : la réponse les y lit, sous le même contrat
		$eConfigurationMap = \map\ConfigurationLib::getByStructure($data->eStructure);

		foreach(['seasonFirst', 'seasonLast', 'defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth'] as $property) {
			$eConfiguration[$property] = $eConfigurationMap[$property];
		}

		// Les notes de la page des stocks vivent dans le réglage des produits, de même
		$eConfigurationProduct = \product\ConfigurationLib::getByStructure($data->eStructure);

		foreach(['stockNotes', 'stockNotesUpdatedAt', 'stockNotesUpdatedBy'] as $property) {
			$eConfiguration[$property] = $eConfigurationProduct[$property];
		}

		return $eConfiguration;

	}

}
?>
