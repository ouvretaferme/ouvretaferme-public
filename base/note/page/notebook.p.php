<?php
/**
 * Les carnets des notes, que les administrateurs créent, renomment, ordonnent et
 * suppriment depuis la liste des notes. Gardés par `isLanding()` comme les notes.
 */
new \note\NotebookPage(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'));

		if($data->eStructure->empty()) {
			throw new NotExistsAction('structure #'.INPUT('structure', '?int'));
		}

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

	})
	->getCreateElement(function($data) {

		return new \note\Notebook([
			'structure' => $data->eStructure
		]);

	})
	->create(function($data) {

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		throw new RedirectAction(\note\NotebookUi::url($data->e).'&success=note\\Notebook::created');

	});

new \note\NotebookPage()
	->applyElement(function($data, \note\Notebook $e) {

		$data->eStructure = \structure\StructureLib::getById($e['structure']['id']);

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

	})
	->update(function($data) {

		throw new ViewAction($data);

	})
	->doUpdate(function($data) {

		throw new ReloadAction('note', 'Notebook::updated');

	})
	->write('doIncrementPosition', function($data) {

		$increment = POST('increment', 'int');

		if(in_array($increment, [-1, 1], TRUE) === FALSE) {
			throw new NotExpectedAction('Increment must be -1 or 1');
		}

		\note\NotebookLib::incrementPosition($data->e, $increment);

		throw new ReloadAction();

	})
	->doDelete(function($data) {

		throw new RedirectAction(\note\NoteUi::urlStructure($data->eStructure).'?success=note\\Notebook::deleted');

	});
?>
