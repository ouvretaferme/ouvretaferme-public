<?php
/**
 * L'onglet Absences du planning (décidé le 18/10) : à toute personne au planning
 * (`\hr\Absence::assertPlanning()`), ses absences, puis celles de l'équipe.
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

		$data->week = INPUT('week', fn($value) => Filter::check('week', $value), \Clock::week());

		if($data->eStructure->conf(\map\Configuration::class)->isSeasonValid(week_year($data->week)) === FALSE) {
			throw new NotExpectedAction('Invalid week');
		}

	})
	->get(['/ferme/{id}/planning-v2/absences', '/ferme/{id}/planning-v2/absences/{week@week}'], function($data) {

		$eUserOnline = \user\ConnectionLib::getOnline();
		$eAbsence = new \hr\Absence(['structure' => $data->eStructure, 'user' => $eUserOnline]);

		// Le droit avant l'état, comme ModulePage : qui n'y a pas droit n'apprend pas si la RH est allumée.
		if($eAbsence->canPlanning() === FALSE) {
			throw new NotAllowedAction();
		}

		if($eAbsence->acceptPlanning() === FALSE) {
			throw new NotExpectedAction('HR disabled');
		}

		$data->eMember = $data->eStructure->getMember();
		$data->manage = $data->eStructure->canManage();

		// Des absences de l'équipe, le planning ne montre que qui et quand sur le mois à venir, au gérant comme
		// aux autres (décidé le 19/10) : les demandes et l'année se gèrent dans les paramètres de
		// l'équipe. Le motif, qui peut dire la santé, ne se charge pas.
		$data->upcoming = \hr\AbsenceLib::getUpcoming($data->eStructure);

		$data->cAbsence = \hr\AbsenceLib::getForRequester($data->eStructure, $eUserOnline);
		// L'ajout de « Mes absences » vise la personne connectée, gérant compris : le gérant doit alors être de
		// l'équipe aujourd'hui ou plus tard, ce que `canCreate()` ne lui demande pas (`absence:create`, origin=planning).
		$data->canCreate = (
			$eAbsence->canCreate() and
			\hr\PresenceLib::isPresentBetween($data->eStructure, $eUserOnline, \Clock::date(), NULL)
		);

		throw new ViewAction($data, ':absence');

	});
?>
