<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canWrite');

	})
	->get('/ferme/{id}/outils/materiel', function($data) {

		// La page de l'historique d'un matériel tient dans l'adresse (`view/equipment.v.php`, `history`) : un
		// geste sur une ligne de la page 2 recharge l'écran, qui y revient.
		$data->cEquipment = \toolbox\EquipmentLib::getByStructure($data->eStructure, [GET('paged', 'int') => GET('page', 'int')]);
		$data->cMaintenanceDue = \toolbox\EquipmentLib::getDue($data->cEquipment);
		$data->eEquipmentSelected = $data->cEquipment->findById(GET('equipment', 'int'), default: new \toolbox\Equipment());

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	});

// « Suivre un matériel » (arbitrage Q5 A de la planche pf-t4-v2) : le matériel, son compteur et son
// premier relevé partent ensemble, pour que l'échéance au compteur parte d'un compteur connu.
new \toolbox\EquipmentPage()
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		return new \toolbox\Equipment([
			'structure' => $data->eStructure,
			'tool' => new \work\Tool(),
			'meter' => NULL
		]);

	})
	->create(function($data) {

		$data->eEquipmentReading = new \toolbox\EquipmentReading([
			'structure' => $data->eStructure
		]);

		$data->hasFollowed = \toolbox\EquipmentLib::hasFollowed($data->eStructure);

		throw new ViewAction($data);

	})
	->create(function($data) {

		$fw = new FailWatch();

		$data->e->build(\toolbox\EquipmentLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

		// Le premier relevé est facultatif : laisser le compteur vide est un choix, que `build()`
		// refuserait sur une colonne qui ne l'est pas. Sa place entre les relevés voisins se vérifie
		// dans `EquipmentLib::follow()`, qui sait seul quel matériel le reçoit.
		if($data->e->hasMeter() and POST('value') !== '') {

			$eEquipmentReading = new \toolbox\EquipmentReading([
				'structure' => $data->eStructure,
				'date' => \Clock::date()
			]);

			$eEquipmentReading->build(['value'], $_POST, new \PropertyList('create'));

		} else {
			$eEquipmentReading = new \toolbox\EquipmentReading();
		}

		$fw->validate();

		\toolbox\EquipmentLib::follow($data->e, $eEquipmentReading, \toolbox\EquipmentReadingLib::isConfirmed($_POST));

		// Un matériel qui revient sous son dernier relevé demande une confirmation, comme « Relever »
		// (`page/equipmentReading.p.php`).
		$fw->validate(onKo: function(\FailWatch $fw) {

			$eEquipmentReading = \toolbox\EquipmentReadingLib::getConflictFromFail($fw);

			if($eEquipmentReading->notEmpty()) {
				throw new ViewAction((object)['eEquipmentReading' => $eEquipmentReading], 'toolbox/equipmentReading:confirm');
			}

		});

		// Le matériel entré s'ouvre sur son onglet, qu'un rechargement ne choisirait pas.
		throw new RedirectAction(\toolbox\EquipmentUi::urlEquipment($data->eStructure, $data->e).'&success=toolbox\\Equipment::followed');

	}, method: 'post', page: 'doFollow')
	// Le matériel de « Mon matériel » que le carnet ne suit pas encore. Un nom absent de la liste se
	// valide pour créer le matériel au même geste, comme une variété (arbitrage Q5 A), seulement
	// pour qui peut régler « Mon matériel » (`work\Tool::canWrite()`).
	->create(function($data) {

		$data->query = trim(POST('query'));
		$data->cTool = \toolbox\EquipmentLib::getToolsFromQuery($data->eStructure, $data->query);
		$data->create = (
			$data->eStructure->canManage() and
			$data->query !== '' and
			$data->cTool->find(fn($eTool) => mb_strtolower($eTool['name']) === mb_strtolower($data->query))->empty()
		);

		throw new ViewAction($data, ':query');

	}, method: 'post', page: 'query');

// Une page de l'historique d'un matériel : seul son tableau revient (`view/equipment.v.php`).
new \toolbox\EquipmentPage()
	->read('history', function($data) {

		$cEquipment = \toolbox\EquipmentLib::getByStructure($data->e['structure'], [$data->e['id'] => GET('page', 'int')]);

		if($cEquipment->offsetExists($data->e['id']) === FALSE) {
			throw new NotExpectedAction('Equipment not followed');
		}

		$data->e = $cEquipment[$data->e['id']];

		throw new ViewAction($data);

	}, validate: ['canRead']);

// Le carnet d'un matériel en PDF, et son historique en CSV. Le PDF est ce que l'écran montre, tout
// membre le télécharge ; le CSV sort des données, ce qui demande de gérer la structure
// (production/toolbox/CLAUDE.md, « Qui écrit dans la Boîte »).
new \toolbox\EquipmentPage()
	->read('downloadPdf', function($data) {

		$eEquipment = \toolbox\EquipmentLib::getWithHistory($data->e['structure'], $data->e['id']);

		if($eEquipment->empty()) {
			throw new NotExpectedAction('Equipment not followed');
		}

		$eEquipment->validate('acceptDownload');

		$filename = \toolbox\EquipmentPdfUi::getFilename($eEquipment);
		$content = \main\PdfLib::build('/toolbox/equipment:getPdf?'.http_build_query(['id' => $data->e['structure']['id'], 'equipment' => $eEquipment['id']]), $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canRead'])
	->read('exportCsv', function($data) {

		$data->e['structure']->validate('canManage');

		$eEquipment = \toolbox\EquipmentLib::getWithHistory($data->e['structure'], $data->e['id']);

		if($eEquipment->empty()) {
			throw new NotExpectedAction('Equipment not followed');
		}

		$eEquipment->validate('acceptExport');

		$export = \toolbox\EquipmentLib::getExport($eEquipment);
		array_unshift($export, new \toolbox\EquipmentUi()->getExportHeader());

		throw new CsvAction($export, \toolbox\EquipmentUi::getExportFilename($eEquipment));

	}, validate: ['canRead']);

// Le PDF se rend par une seconde requête, celle du navigateur sans tête de `main\PdfLib::build()`,
// qui n'est connecté sous aucun compte : sa page est une adresse signée (`remote()`), comme celle du
// registre (`page/treatment.p.php`). Le matériel se borne donc ici à la structure, sans compter sur
// le contrôle que `downloadPdf` a fait avant de réémettre.
new \structure\StructurePage()
	->remote('getPdf', 'selling', function($data) {

		$data->eEquipment = \toolbox\EquipmentLib::getWithHistory($data->e, GET('equipment', 'int'));

		if($data->eEquipment->empty()) {
			throw new NotExpectedAction('Equipment not followed');
		}

		throw new ViewAction($data);

	});

new \toolbox\EquipmentPage()
	->doUpdateProperties('doUpdateStatus', ['status'], fn($data) => throw new ReloadAction('toolbox', 'Equipment::forgotten'))
	// La croix du planning sur un compteur à relever : la réponse ne remplace que les blocs dus de la
	// semaine, comme celle d'un relevé fait depuis le planning (`page/equipmentReading.p.php`).
	->write('doSkipReading', function($data) {

		\toolbox\EquipmentLib::skipReading($data->e);

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());
		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'Equipment::readingSkipped'];

		throw new ViewAction($data, 'planning/due:skip');

	}, validate: ['canUpdate', 'acceptSkipReading'])
	// Le décalage à la semaine suivante d'un compteur à relever (retour 15 du 04/10).
	->write('doPostponeReading', function($data) {

		$data->eStructure = $data->e['structure'];
		$data->origin = POST('origin', fn($value) => \planning\DueLib::isOrigin($value), \planning\DueLib::ORIGIN_BLOCK);
		$data->week = POST('week', fn($value) => \Filter::check('week', $value), \Clock::week());

		\toolbox\EquipmentLib::postponeReading($data->e);

		$data->dues = \planning\DueLib::getForWeek($data->eStructure, $data->week);
		$data->success = ['toolbox', 'Equipment::readingPostponed'];

		throw new ViewAction($data, 'planning/due:postpone');

	}, validate: ['canUpdate', 'acceptPostponeReading']);
?>
