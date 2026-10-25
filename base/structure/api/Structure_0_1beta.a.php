<?php
namespace structure;

class Structure_0_1betaApi extends \Api {

	public string $scopeName = 'general';

	public function __construct() {

		$this->requestGet('me', [], function(\ApiData $data) {

			$data->pushObject(NULL, $data->eStructure);

		});

		$this->element(
			'structure\Structure',
			fn() => [
				'id', 'name', 'type', 'communityType',
				'isLegal', 'legalName', 'legalEmail', 'legalCategory', 'siret',
				'legalStreet1', 'legalStreet2', 'legalPostcode', 'legalCity', 'legalCountry',
				'url', 'description', 'logo',
				'organic', 'qualities', 'startedAt',
				'status'
			],
			[
				'logo' => fn(\ApiData $data, Structure $e) => new \media\StructureLogoUi()->getUrlByHash($e['logo'], 'm')
			]
		);

	}

}
?>
