<?php
new \structure\SurveyPage(function($data) {


	})
	->getCreateElement(function($data) {

		if(FEATURE_SURVEY === FALSE) {
			throw new NotExistsAction();
		}

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		if($data->eStructure->notEmpty()) {
			$data->eStructure->validate('acceptSurvey', 'canManage');
		}

		return new \structure\Survey([
			'structure' => $data->eStructure,
		]);

	})
	->create(function($data) {

		if($data->eStructure->empty()) {

			$data->cStructure = \structure\StructureLib::getByUser($data->eUserOnline, \structure\Structure::PRODUCER)->find(fn($eStructure) => (
				$eStructure->canManage()
			));

			throw new \ViewAction($data, ':surveyMain');

		} else {

			$data->eSurvey = \structure\SurveyLib::getByStructure($data->eStructure);

			throw new \ViewAction($data, ':surveyStructure');

		}


	}, page: 'index')
	->doCreate(function($data) {

		throw new RedirectAction('/structure/survey?structure='.$data->e['structure']['id']);

	})
	->doUpdate(function($data) {

		throw new RedirectAction('/structure/survey?structure='.$data->e['structure']['id']);

	});
?>
