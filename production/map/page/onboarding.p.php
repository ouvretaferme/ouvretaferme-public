<?php
new \structure\StructurePage()
	->read([['/ferme/{id}/plan/demarrer', '@priority' => 1]], function($data) {

		\map\OnboardingLib::load($data, $data->e);

		throw new \ViewAction($data, ':start');

	})
	->write('doCreate', function($data) {

		$data->season = \map\SeasonLib::getOnline(POST('season', 'int'));

		$fw = new \FailWatch();

		\map\OnboardingLib::create($data->e, $_POST, $data->season);

		$fw->validate();

		throw new RedirectAction(\map\PlanUi::url($data->e, $data->season).'?onboarded=1');

	}, addValidate: ['canManage']);
?>
