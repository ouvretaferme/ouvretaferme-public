<?php
/**
 * Les PDF des notes (L47) : une note, ou un carnet entier. `\main\PdfLib::build()` fait lire la route
 * distante par un navigateur sans tête, sans compte connecté : c'est la route qui signe l'adresse qui
 * juge le droit et garde les notes par `isLanding()` comme leurs écrans, la route distante relit
 * seulement ce que le document montre.
 */
new \note\NotePage()
	->read('downloadNote', function($data) {

		$eStructure = \structure\StructureLib::getById($data->e['structure']['id']);

		if(\dashboard\DashboardSetting::isLanding($eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		$filename = \note\PdfUi::getNoteFilename($data->e);
		$content = \main\PdfLib::build('/note/pdf:getNote?id='.$data->e['id'], $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	})
	->remote('getNote', 'selling', function($data) {

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']['id']);

		if($data->e['notebook']->notEmpty()) {
			$data->e['notebook'] = \note\NotebookLib::getById($data->e['notebook']['id']);
		}

		\note\TargetLib::putByNotes($data->eStructure, new Collection([$data->e]));

		throw new ViewAction($data);

	});

new \note\NotebookPage()
	/**
	 * Le carnet emporte les notes que le demandeur y lit, et seulement elles. La route distante n'a
	 * personne de connecté : l'adresse signée nomme le demandeur, et la route relit ses notes comme
	 * son onglet les lit, à la génération.
	 */
	->read('downloadNotebook', function($data) {

		$eStructure = \structure\StructureLib::getById($data->e['structure']['id']);

		if(\dashboard\DashboardSetting::isLanding($eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		if(array_key_exists($data->e['id'], \note\NoteLib::countByNotebook($eStructure)) === FALSE) {
			throw new NotExpectedAction('Cannot export a notebook without any note to read');
		}

		$filename = \note\PdfUi::getNotebookFilename($data->e);
		$content = \main\PdfLib::build('/note/pdf:getNotebook?id='.$data->e['id'].'&user='.$data->eUserOnline['id'], $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	})
	/**
	 * Le demandeur a pu quitter la structure entre la signature et la génération : seul un membre
	 * présent emporte quelque chose.
	 */
	->remote('getNotebook', 'selling', function($data) {

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']['id']);

		$eUser = GET('user', 'user\User');

		if(\structure\MemberLib::isMember($eUser, $data->eStructure) === FALSE) {
			throw new NotAllowedAction('Notebook requester is no longer a member');
		}

		$data->cNote = \note\NoteLib::getByNotebook($data->e, \structure\MemberLib::getByUserAndStructure($eUser, $data->eStructure));

		\note\TargetLib::putByNotes($data->eStructure, $data->cNote);

		throw new ViewAction($data);

	});
?>
