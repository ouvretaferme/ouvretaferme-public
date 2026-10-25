<?php
/**
 * Les encadrants d'un réseau, d'un groupe de formation ou de l'équipe Ouvretaferme, depuis leur
 * liste : on les ajoute, on corrige leur fiche, on les désactive et on les supprime. Une personne
 * nait chez qui la saisit (`community`, vide pour l'équipe) et n'en change plus.
 *
 * Le droit et l'état sont ceux de l'élément (`Supervisor::canRead()`, `acceptWrite()`), que chaque
 * route valide : la page n'a pas de porte à elle.
 */
new \learning\SupervisorPage()
	->getCreateElement(function($data) {

		$community = INPUT('community', '?int');

		// Un groupe inconnu se lirait comme l'équipe : il est refusé ici.
		return \learning\SupervisorLib::getCreateElement(
			$community === NULL ? new \structure\Structure() : \structure\StructureLib::getById($community)->validate()
		);

	})
	// `coursesContact` est lu par l'étape `Supervisor::email.contact` pendant `build()`, et `courses`
	// par la ligne que le changement de statut redessine.
	->applyElement(function($data, \learning\Supervisor $e) {

		\learning\SupervisorLib::fillCourses(new Collection([$e]));

	})
	->create()
	->doCreate(fn() => throw new ReloadAction('learning', 'Supervisor::created'))
	->update(function($data) {

		$data->e['cStaff'] = \learning\StaffLib::getBySupervisor($data->e);

		throw new ViewAction($data);

	})
	->doUpdate(fn() => throw new ReloadAction('learning', 'Supervisor::updated'))
	// Le statut n'est pas un champ de la fiche (`SupervisorLib::getPropertiesUpdate()`) : désactiver
	// et réactiver sont un geste à part, joué depuis la ligne, qui seule est redessinée.
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ViewAction($data))
	->doDelete(fn() => throw new ReloadAction('learning', 'Supervisor::deleted'));
?>
