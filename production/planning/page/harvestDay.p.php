<?php
/**
 * L'ancienne adresse des récoltes d'un jour, gardée pour les favoris et les liens déjà partagés : elle
 * mène à la semaine de la date, ce jour ouvert.
 */
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

	})
	->get(['/ferme/{id}/planning-v2/recoltes/jour', '/ferme/{id}/planning-v2/recoltes/jour/{date@date}'], function($data) {

		$date = INPUT('date', fn($value) => Filter::check('date', $value));

		$url = ($date === NULL) ?
			\planning\HarvestWeekUi::url($data->eStructure) :
			\planning\HarvestWeekUi::url($data->eStructure, toWeek($date), open: $date);

		// 302 et non le 301 par défaut de RedirectAction : sans date, l'adresse mène à la semaine du jour,
		// qu'un navigateur ne doit pas mémoriser.
		throw new RedirectAction($url, httpStatus: 'HTTP/1.1 302 Found');

	});
?>
