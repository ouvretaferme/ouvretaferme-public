<?php
new \structure\StructurePage()
	->read(['/ferme/{id}/especes', '/ferme/{id}/especes/{status}'], function($data) {

		$data->plants = \plant\PlantLib::countByStructure($data->e);

		if(
			get_exists('status') and
			$data->plants[\plant\Plant::INACTIVE] === 0
		) {
			throw new RedirectAction(\plant\PlantUi::urlManage($data->e));
		}

		$data->search = new Search([
			'cFamily' => \plant\FamilyLib::getList(),
			'id' => get_exists('plantId') ? \plant\PlantLib::getById(GET('plantId')) : NULL,
			'family' => GET('family', 'plant\Family'),
			'status' => GET('status', default: \plant\Plant::ACTIVE),
		]);

		$data->cPlant = \plant\PlantLib::getByStructure($data->e, selectMetadata: TRUE, search: $data->search);

		if(
			$data->search->get('id') and
			$data->cPlant->notEmpty()
		) {
			$data->search->set('id', $data->cPlant->first());
		}

		// Pour le template Structure
		$data->eStructure = $data->e;

		throw new ViewAction($data, ':plant');

	}, validate: ['canWrite']);

new \plant\PlantPage()
	->applyElement(function($data, \plant\Plant $e) {

		$data->eStructure = \structure\StructureLib::getById($e['structure']);

	})
	->read('/espece/{id@int}', function($data) {

		$data->cActionMain = \work\ActionLib::getMainByStructure($data->eStructure);

		$data->search = new Search([
			'plant' => $data->e,
			'status' => \sequence\Sequence::ACTIVE
		]);

		$data->ccCrop = \sequence\CropLib::getByStructure($data->eStructure, $data->cActionMain, TRUE, search: $data->search);

		\plant\PlantLib::loadDisplay($data->eStructure, $data->e);

		$data->cNoteLinked = \note\NoteLib::getByTargets($data->eStructure, [
			\plant\NoteTargetLib::getType() => [$data->e['id']]
		]);

		throw new ViewAction($data);

	})
	->read('analyzeTime', function($data) {

		$data->eStructure->validate('canAnalyzeProduction');

		$data->year = GET('year', 'int', \Clock::year());

		$data->cPlantTimesheet = \series\AnalyzeLib::getPlantTimesheet($data->e, $data->year);
		[$data->cTimesheetByAction, $data->cTimesheetByUser] = \series\AnalyzeLib::getActionTimesheetByPlant($data->e, $data->year);

		$cCultivationYear = \series\AnalyzeLib::getPlantCultivations($data->e, $data->year);
		$cCultivationYearBefore = \series\AnalyzeLib::getPlantCultivations($data->e, $data->year - 1);

		$data->cPlantMonth = \series\AnalyzeLib::getPlantMonths($data->e, $cCultivationYear, $data->year);
		$data->cPlantMonthBefore = \series\AnalyzeLib::getPlantMonths($data->e, $cCultivationYearBefore, $data->year - 1);

		$data->cPlantWeek = \series\AnalyzeLib::getPlantWeeks($data->e, $cCultivationYear, $data->year);
		$data->cPlantWeekBefore = \series\AnalyzeLib::getPlantWeeks($data->e, $cCultivationYearBefore, $data->year - 1);

		throw new ViewAction($data);

	}, validate: []);

new \plant\PlantPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \plant\Plant([
			'structure' => $data->eStructure,
		]);

	})
	->create(function($data) {

		$data->cFamily = \plant\FamilyLib::getList();

		throw new \ViewAction($data);

	})
	->doCreate(fn($data) => throw new ViewAction($data));

new \plant\PlantPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->applyElement(function($data, \plant\Plant $e) {

		$e->validate('canWrite');

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$data->eStructure->validate('active');

		$e['cFamily'] = \plant\FamilyLib::getList();

	})
	->doUpdateProperties('doUpdateStatus', ['status'], function($data) {
		throw new ViewAction($data);
	})
	->update(function($data) {

		$data->e['storageDefault'] = \plant\PlantLib::getStorageDefault($data->e);

		if($data->e->acceptRating()) {
			$data->e['cCriterionByType'] = \plant\CriterionLib::getAllByType();
		}

		throw new ViewAction($data);

	})
	->doUpdate(function($data) {

		if(POST('origin') === 'composition') {
			throw new ReloadAction();
		}

		throw new ViewAction($data);

	})
	->doDelete(fn($data) => throw new ViewAction($data));
?>
