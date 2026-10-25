<?php
/**
 * Le récapitulatif d'un mois, qui prépare la paie, réservé au gérant : l'équipe en une ligne par personne, et le
 * détail d'une personne jour par jour (`user`), en panneau. Le mois (`month`) se lit jusqu'à celui dont la période
 * contient aujourd'hui (`\hr\RecapLib::getMonth()`), dans les saisons de la structure. La personne est l'une de
 * celles du mois (`\hr\RecapLib::getUsers()`).
 *
 * Ce constructeur garde la page, le détail d'une personne et l'export. La saisie d'une journée passe par la fiche
 * de temps (`planning/timeDay:doWorkingTime`, `origin=recap`), qui refait les mêmes vérifications.
 */
new Page(function($data) {

	$data->eStructure = \structure\StructureLib::getById(INPUT('id'))->validate('canManage', 'acceptHr');

	if(input_exists('month')) {

		$data->month = INPUT('month', 'string');

		if(\hr\RecapLib::isMonthValid($data->eStructure, $data->month) === FALSE) {
			throw new NotExpectedAction('Invalid month');
		}

	} else {
		$data->month = \hr\RecapLib::getMonth($data->eStructure, \Clock::date());
	}

	$data->cUser = \hr\RecapLib::getUsers($data->eStructure, $data->month);

})
	->get('/ferme/{id}/equipe/recapitulatif', function($data) {

		$data->eStructure['absencesPending'] = \hr\AbsenceLib::countPending($data->eStructure);

		$data->holidays = \hr\RecapLib::getHolidays($data->eStructure, $data->month);
		$data->navigation = \hr\RecapLib::getNavigation($data->eStructure, $data->month);

		\hr\RecapLib::fillByMonth($data->eStructure, $data->cUser, $data->month, withMissing: TRUE);

		$data->total = \hr\RecapLib::getTotal($data->cUser);
		$data->columns = \hr\RecapLib::getColumns($data->cUser);

		throw new ViewAction($data);

	})
	->get('user', function($data) {

		$user = INPUT('user', 'int');

		if($data->cUser->offsetExists($user) === FALSE) {
			throw new NotExpectedAction('User not in the recap');
		}

		$data->eUserSelected = $data->cUser[$user];

		$data->navigation = \hr\RecapLib::getNavigation($data->eStructure, $data->month);

		\hr\RecapLib::fillByMonth($data->eStructure, new Collection([$data->eUserSelected['id'] => $data->eUserSelected]), $data->month);

		$data->cPresence = \hr\PresenceLib::getByUserBetween($data->eStructure, $data->eUserSelected, \hr\RecapLib::getStart($data->eStructure, $data->month), \hr\RecapLib::getStop($data->eStructure, $data->month));
		$data->weeks = \hr\RecapLib::getByUser($data->eStructure, $data->eUserSelected, $data->month, $data->cPresence);
		$data->eMember = \structure\MemberLib::getByUserAndStructure($data->eUserSelected, $data->eStructure);

		throw new ViewAction($data);

	})
	->get('export', function($data) {

		\hr\RecapLib::fillByMonth($data->eStructure, $data->cUser, $data->month);

		$start = \hr\RecapLib::getStart($data->eStructure, $data->month);
		$stop = \hr\RecapLib::getStop($data->eStructure, $data->month);

		throw new CsvAction(new \hr\RecapUi()->getExport($data->cUser, \hr\RecapLib::getColumns($data->cUser), $start, $stop), 'recapitulatif-'.$data->month.'.csv');

	});
?>
