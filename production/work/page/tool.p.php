<?php
new \work\ToolPage(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'));

	})
	->getCreateElement(function($data) {

		return \work\ToolLib::getNewTool($data->eStructure, REQUEST('routineName', array_keys(\work\RoutineLib::list())), GET('amm', '?string'));

	})
	->create(function($data) {

		$data->e['cAction'] = \work\ActionLib::getByStructure($data->eStructure);

		throw new ViewAction($data);
	})
	->doCreate(fn($data) => throw new ViewAction($data));

new \work\ToolPage()
	->applyElement(function($data, \work\Tool $e) {

		$e->validate('canWrite');

		$data->eStructure = $e['structure'];

		\structure\Structure::model()
			->select('status', 'name')
			->get($data->eStructure);

		$data->eStructure->validate('active');

	})
	->quick(['name', 'action', 'stock'], [
		'action' => function($data) {
			$data->e['cAction'] = \work\ActionLib::getByStructure($data->e['structure']);
		}
	])
	->update(function($data) {

		$data->e['cAction'] = \work\ActionLib::getByStructure($data->eStructure);
		$data->e['eEquipment'] = \toolbox\EquipmentLib::getByTool($data->e);
		$data->e['followed'] = $data->e['eEquipment']->notEmpty();

		// Le matériel devient un produit. Les interventions dont l'action reçoit ce produit le prennent
		// comme produit, les autres le gardent dans leur matériel (`Tool::applyMove()`), et un produit
		// de traitement donne sa dose à celles qui n'en portaient pas.
		$data->e['productRoutine'] = $data->e->getProductRoutine();

		if(
			GET('routineName') !== NULL and
			GET('routineName') === $data->e['productRoutine']
		) {

			if($data->e['productRoutine'] === 'phyto') {
				\work\ToolLib::loadPhytoReprise($data->e, NULL, query: $data->e['name']);
			} else {
				$data->e['routineName'] = $data->e['productRoutine'];
				$data->e['routineValue'] = [];
			}

		} else if($data->e['routineName'] === 'phyto') {
			\work\ToolLib::loadPhyto($data->e, $data->e['routineValue']['amm'], $data->e['routineValue']['amm'] === NULL);
		}

		if($data->e->isStandaloneRoutine()) {
			$data->routines = [$data->e['routineName'] => \work\RoutineLib::get($data->e['routineName'])];
		} else {
			$data->routines = \work\RoutineLib::getByAction($data->e['action'], FALSE);
		}

		throw new ViewAction($data);

	})
	->doUpdate(function($data) {

		if(POST('origin') === 'treatment') {
			throw new RedirectAction(\toolbox\TreatmentUi::url($data->e['structure']).'?success=toolbox\\Treatment::productReprise');
		}

		throw new ViewAction($data);

	})
	->doUpdateProperties('doUpdateStatus', ['status'], function() {
		throw new ReloadAction('work', 'Tool::updated');
	})
	->doUpdateProperties('doRefusePhyto', ['phytoRefused'], fn($data) => throw new ViewAction($data))
	->doDelete(fn($data) => throw new ViewAction($data));

new Page()
	->get('/outil/{id@int}', function($data) {

		$data->eTool = \work\ToolLib::getById(REQUEST('id'))->validate('canRead');
		$data->eTool['followed'] = \toolbox\EquipmentLib::isFollowed($data->eTool);

		$data->eStructure = \structure\StructureLib::getById($data->eTool['structure']);

		if($data->eTool['routineName'] === 'phyto') {
			\work\ToolLib::loadPhyto($data->eTool, $data->eTool['routineValue']['amm'], FALSE);
		}

		throw new ViewAction($data);

	})
	->post('query', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');
		$eAction = \work\ActionLib::getById(POST('action'));

		$data->cTool = \work\ToolLib::getFromQuery(POST('query'), $eStructure, $eAction);

		throw new \ViewAction($data);

	})
	->post('queryProduct', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');
		$eAction = \work\ActionLib::getById(POST('action'))->validateProperty('structure', $eStructure);

		$routineName = \work\RoutineLib::getProductByAction($eAction) ?? throw new NotExpectedAction('No product for this action');

		$exclude = array_map('intval', array_filter(POST('exclude', 'array'), 'ctype_digit'));

		$data->cTool = \work\ToolLib::getProductsFromQuery(POST('query'), $eStructure, $routineName, $exclude);
		\work\ToolLib::fillPhytoProduct($data->cTool);

		if($routineName === 'phyto' and POST('catalog', 'bool')) {
			[
				'cPhytoProduct' => $data->cPhytoProduct,
				'others' => $data->others
			] = \work\ToolLib::getPhytoCatalogFromQuery(POST('query'), $eStructure);
		} else {
			$data->cPhytoProduct = new Collection();
			$data->others = 0;
		}

		$data->query = trim(POST('query'));

		// Le formulaire de l'intervention peut créer le produit cherché, sauf s'il est déjà dans la liste sous ce nom.
		$data->create = (
			$routineName === 'phyto' and
			POST('catalog', 'bool') and
			$data->query !== '' and
			$data->cTool->find(fn($eTool) => mb_strtolower($eTool['name']) === mb_strtolower($data->query))->empty()
		);

		throw new \ViewAction($data);

	})
	->post('queryPhyto', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');

		$data->cPhytoProduct = \work\PhytoProductLib::search(POST('query'), \work\ToolLib::isOrganicOnly($eStructure));
		$data->query = trim(POST('query'));

		throw new \ViewAction($data);

	})
	->post('getPhytoField', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canManage');

		// La carte de reprise du carnet de traitement : le champ est celui d'un matériel qui devient un produit.
		if(POST('tool', '?int') !== NULL) {
			$data->eTool = \work\ToolLib::getById(POST('tool'))->validate('canWrite')->validateProperty('structure', $eStructure);
			\work\ToolLib::loadPhytoReprise($data->eTool, POST('amm', '?string'), POST('manual', 'bool'));
		} else {
			$data->eTool = \work\ToolLib::getNewTool($eStructure, 'phyto');
			\work\ToolLib::loadPhyto($data->eTool, POST('amm', '?string'), POST('manual', 'bool'));
		}

		if(
			$data->eTool['phytoManual'] and
			POST('name', '?string') !== NULL
		) {
			$data->eTool['name'] = POST('name');
		}

		if($data->eTool['ePhytoProduct']->notEmpty()) {
			$data->eTool['routineValue'] = \work\PhytoUsageLib::getDefaultDose($data->eTool['cPhytoUsage']) ?? ['dose' => NULL, 'unit' => \work\PhytoUsageLib::getDefaultUnit($data->eTool['ePhytoProduct'])];
		}

		throw new \ViewAction($data);

	})
	->post('getRoutinesField', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');
		$eAction = \work\ActionLib::getById(POST('action'))->validate('canRead');

		$data->eTool = new \work\Tool([
			'structure' => $eStructure,
			'action' => $eAction,
			'routineName' => NULL,
			'routineValue' => []
		]);

		$data->routines = \work\RoutineLib::getByAction($eAction, FALSE);

		throw new \ViewAction($data);

	})
	->get('manage', function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage');

		$data->search = new Search([
			'name' => GET('name'),
			'action' => GET('action', 'work\Action'),
			'status' => GET('status', [\work\Tool::ACTIVE, \work\Tool::INACTIVE], default: \work\Tool::ACTIVE),
		]);

		$data->routineName = GET('routineName');

		if(
			$data->routineName === NULL or
			\work\RoutineLib::exists($data->routineName) === FALSE or
			\work\RoutineLib::get($data->routineName)['standalone'] === FALSE
		) {
			$data->routineName = NULL;
		}

		$data->tools = \work\ToolLib::countByStructure($data->eStructure, $data->routineName);

		if(
			get_exists('status') and
			$data->tools[\plant\Plant::INACTIVE] === 0
		) {
			throw new RedirectAction(\work\ToolUi::urlManage($data->eStructure, $data->routineName));
		}

		$data->eToolNew = \work\ToolLib::getNewTool($data->eStructure, $data->routineName);
		$data->eToolNew['cAction'] = \work\ActionLib::getByStructure($data->eStructure);

		$data->cTool = \work\ToolLib::getByStructure($data->eStructure, routineName: $data->routineName, search: $data->search);
		\work\ToolLib::fillPhytoProduct($data->cTool);
		\toolbox\EquipmentLib::fillFollowed($data->cTool);

		$data->cActionUsed = \work\ToolLib::getActionsByStructure($data->eStructure);


		throw new \ViewAction($data);

	});
?>
