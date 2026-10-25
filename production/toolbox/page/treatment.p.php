<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

		$data->eAction = \toolbox\TreatmentLib::getAction($data->eStructure);

		// L'état du carnet voyage dans l'adresse, et le panneau « + Produit » le réémet pour rendre le carnet tel qu'il était.
		$data->year = INPUT('year', 'int', \Clock::year());

		$ePlant = \plant\PlantLib::getById(INPUT('plant'));
		$eTool = \work\ToolLib::getById(INPUT('tool'));

		if($ePlant->notEmpty()) {
			$ePlant->validateProperty('structure', $data->eStructure);
		}

		if($eTool->notEmpty()) {
			$eTool->validateProperty('structure', $data->eStructure);
		}

		$data->search = new Search([
			'plant' => $ePlant,
			'tool' => $eTool,
			'without' => INPUT('without', 'bool'),
		]);

	})
	->get('/ferme/{id}/outils/traitement', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->years = \toolbox\TreatmentLib::getYears($data->eStructure, $data->eAction);
		$data->seasons = \series\SeriesLib::getSeasonsAround($data->eStructure, \Clock::year());

		[
			'cTask' => $data->cTask,
			'tasksByMonth' => $data->tasksByMonth,
			'cTaskWithout' => $data->cTaskWithout
		] = \toolbox\TreatmentLib::getBook($data->eStructure, $data->eAction, $data->year, $data->search);

		// La carte de reprise mène à la modification d'un matériel, que seul `work\Tool::canWrite()` ouvre.
		$data->eToolReprise = $data->eStructure->canManage() ? \toolbox\TreatmentLib::getToolReprise($data->eStructure, $data->eAction, $data->cTaskWithout) : new \work\Tool();

		// Sans produit, l'accueil présente le carnet, à moins qu'un matériel noté sur des traitements
		// puisse en devenir un : le carnet s'ouvre alors sur la carte qui le propose. Tous les liens du
		// carnet portent l'année, son bouton compris : une fois ouvert, il le reste, même vide.
		$data->start = (
			get_exists('year') === FALSE and
			\toolbox\TreatmentLib::hasProducts($data->eStructure) === FALSE and
			$data->eToolReprise->empty()
		);

		throw new ViewAction($data);

	})
	->get('updateProduct', function($data) {

		$data->cTaskSelected = \toolbox\TreatmentLib::getTasksByIds($data->eStructure, $data->eAction, GET('ids', 'array'));

		if($data->cTaskSelected->empty()) {
			throw new NotExpectedAction('No task');
		}

		$data->cTaskSelected->validate('canWrite');

		$data->eTask = \toolbox\TreatmentLib::getProductTask($data->eStructure, $data->eAction, $data->cTaskSelected);

		throw new ViewAction($data);

	})
	->post('doUpdateProduct', function($data) {

		$data->cTaskSelected = \toolbox\TreatmentLib::getTasksByIds($data->eStructure, $data->eAction, POST('ids', 'array'));

		if($data->cTaskSelected->empty()) {
			throw new NotExpectedAction('No task');
		}

		$data->cTaskSelected->validate('canWrite');

		$fw = new FailWatch();

		// Un produit pris dans le catalogue ou créé depuis la recherche devient un matériel à la
		// première écriture : les suivantes reçoivent ce matériel, sans quoi un produit créé le serait
		// une fois par traitement, et refusé en doublon dès le deuxième.
		$input = $_POST;

		foreach($data->cTaskSelected as $eTask) {

			$eTask['structure'] = $data->eStructure;
			$eTask->build(['products', 'treatment'], $input);

			$fw->validate();

			\series\TaskLib::update($eTask, ['products', 'treatment']);

			$input['products'] = $eTask['products'];

		}

		[
			'cTask' => $data->cTask,
			'tasksByMonth' => $data->tasksByMonth,
			'cTaskWithout' => $data->cTaskWithout
		] = \toolbox\TreatmentLib::getBook($data->eStructure, $data->eAction, $data->year, $data->search);

		throw new ViewAction($data);

	})
	->get('downloadPdf', function($data) {

		$filename = \toolbox\TreatmentPdfUi::getFilename($data->year);
		$content = \main\PdfLib::build('/toolbox/treatment:getPdf?'.http_build_query(['id' => $data->eStructure['id']] + \toolbox\TreatmentUi::getState($data->year, $data->search)), $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	})
	->get('exportCsv', function($data) {

		$data->eStructure->validate('canManage');

		['cTask' => $cTask] = \toolbox\TreatmentLib::getBook($data->eStructure, $data->eAction, $data->year, $data->search);

		$export = \toolbox\TreatmentLib::getExport($cTask);
		array_unshift($export, new \toolbox\TreatmentUi()->getExportHeader());

		throw new CsvAction($export, 'carnet-traitement-'.$data->year.'.csv');

	});

// Le PDF se rend par une seconde requête, celle du navigateur sans tête de `main\PdfLib::build()`,
// qui n'est connecté sous aucun compte : sa page est une adresse signée (`remote()`), hors du `Page`
// de l'écran dont la garde demande un membre. L'espèce et le produit de la recherche se bornent
// donc ici à la structure, sans compter sur le contrôle que `downloadPdf` a fait avant de réémettre.
new \structure\StructurePage()
	->remote('getPdf', 'selling', function($data) {

		$data->eAction = \toolbox\TreatmentLib::getAction($data->e);
		$data->year = GET('year', 'int');

		$ePlant = \plant\PlantLib::getById(GET('plant'));
		$eTool = \work\ToolLib::getById(GET('tool'));

		if($ePlant->notEmpty()) {
			$ePlant->validateProperty('structure', $data->e);
		}

		if($eTool->notEmpty()) {
			$eTool->validateProperty('structure', $data->e);
		}

		[
			'cTask' => $data->cTask,
			'cTaskWithout' => $data->cTaskWithout
		] = \toolbox\TreatmentLib::getBook($data->e, $data->eAction, $data->year, new Search([
			'plant' => $ePlant,
			'tool' => $eTool,
		]));

		$data->tasksByMonth = \toolbox\TreatmentLib::getRegister($data->cTask);

		throw new ViewAction($data);

	});
?>
