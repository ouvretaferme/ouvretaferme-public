<?php
namespace hr;

class Contract_0_1betaApi extends \ModuleApi {

	// Le contrat appartient à une période de présence et ne s'écrit qu'avec elle, depuis l'écran d'équipe :
	// l'API le lit, sans création, modification ni suppression.
	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'contractList',
			[
				'?presence' => Contract::getValidator('presence')
			]
		);

		$this->read('contract/{id}');

		$this->element(
			'hr\Contract',
			fn() => [
				'id', 'presence', 'type', 'weeklyHours', 'annualHours', 'hoursPeriod', 'days', 'hasVariableDays'
			]
		);

	}

	/**
	 * RH éteinte, la ressource est fermée en lecture comme en écriture. Le refus vient avant le chargement de
	 * l'élément, pour qu'un 404 ne dise pas quel identifiant existe.
	 */
	public function load(\ApiData $data, array $routeInfo): void {

		if($data->eStructure->acceptHr() === FALSE) {
			throw new \ApiException('HR is disabled', 'accept_hr', 412);
		}

		parent::load($data, $routeInfo);

	}

	public function getList(\ApiData $data): ContractModel {

		return $this->getNewModel($data)
			->wherePresence(fn() => $data->get('presence'), if: $data->exists('presence'));

	}

	public function getNewModel(\ApiData $data): ContractModel {

		return new \hr\ContractModel()
			->whereStructure($data->eStructure);

	}

	// Exigé par `ModuleApi`, et lu par aucune route : l'API ne crée pas de contrat.
	public function getNewElement(\ApiData $data): Contract {

		return new \hr\Contract([
			'structure' => $data->eStructure
		]);

	}

}
?>
