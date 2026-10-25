<?php
namespace learning;

/**
 * Les phases des formations du groupe de la clé, qui rangent leurs modules (`Chapter.phase`). Le
 * groupe est gardé par `Course_0_1betaApi::validateStructure()`, la formation d'une phase par l'étape
 * `Phase::course.owned`, qui cherche la formation postée parmi celles du groupe.
 *
 * Les phases d'une formation se listent dans `Course_0_1betaApi` (`course/{id}/phasesList`), pour la
 * raison que donne cette classe de ses modules.
 */
class Phase_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->create('phase');
		$this->read('phase/{id}');
		$this->update('phase/{id}');
		$this->delete('phase/{id}');

		// `position` se lit : les phases se rangent dans l'ordre de leur création.
		$this->element(
			'learning\Phase',
			fn() => [
				'id', 'course',
				'name', 'summary',
				'position'
			]
		);

	}

	public function getNewModel(\ApiData $data): PhaseModel {

		Course_0_1betaApi::validateStructure($data);

		return new PhaseModel()
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql());

	}

	public function getNewElement(\ApiData $data): Phase {

		Course_0_1betaApi::validateStructure($data);

		return new Phase([
			'cCourse' => CourseLib::getByCommunity($data->eStructure)
		]);

	}

}
?>
