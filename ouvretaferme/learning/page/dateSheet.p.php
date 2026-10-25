<?php
/**
 * La feuille signée d'une date sur place (lot L8e) : déposée et retirée depuis la liste des inscrits
 * de la date, téléchargée par l'équipe seule, puisqu'elle porte des signatures.
 *
 * Le dépôt et le retrait répondent par la seule section de la feuille (`view/dateSheet.v.php`),
 * jamais par la liste entière.
 */
new Page()
	/**
	 * Le fichier se sert par la date et jamais par l'adresse du stockage, publique pour qui connaît le
	 * hash : c'est le droit d'écrire la formation qui ouvre le téléchargement.
	 */
	->get('/formation/date/feuille/{id}', function($data) {

		\user\ConnectionLib::checkLogged();

		$eDateSheet = \learning\DateSheetLib::getById(GET('id'))->validate('canRead');

		$path = \storage\DriverLib::directory().'/'.new \media\LearningDateSheetAiUi()->getBasenameByHash($eDateSheet['hash']);

		if(is_file($path) === FALSE) {
			throw new NotExistsAction('DateSheet file');
		}

		$content = file_get_contents($path);

		throw new DataAction($content, \media\MediaUi::getMimeType($eDateSheet['hash']), $eDateSheet['name'].'.'.\media\MediaUi::getExtension($eDateSheet['hash']));

	})
	/**
	 * Un fichier par requête : `LearningDateSheet.upload()` envoie l'un après l'autre ceux qui sont
	 * choisis ensemble. Le nom du fichier déposé, sans son extension, devient le nom affiché, borné
	 * comme celui d'une pièce jointe de leçon.
	 */
	->post('doUpload', function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eDate = \learning\DateLib::getById(POST('date'))->validate();
		$data->eDate['sheets'] = \learning\DateSheetLib::countByDate($data->eDate);
		$data->eDate->validate('canWrite', 'acceptSheetUpload');

		$file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];

		$fw = new FailWatch();

		if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) {
			Fail::log('media\\attachmentSize');
		} else if(
			$file['error'] !== UPLOAD_ERR_OK or
			is_uploaded_file($file['tmp_name']) === FALSE
		) {
			\learning\DateSheet::fail('file.check');
		}

		$fw->validate();

		$eDateSheet = new \learning\DateSheet([
			'date' => $data->eDate,
			'course' => $data->eDate['course'],
		]);

		$eDateSheet->build(\learning\DateSheetLib::getPropertiesCreate(), [
			'name' => \learning\AttachmentLib::getNameByFilename($file['name'])
		], new \PropertyList('create'));

		$fw->validate();

		\learning\DateSheetLib::upload($eDateSheet, file_get_contents($file['tmp_name']));

		$fw->validate();

		$data->cDateSheet = \learning\DateSheetLib::getByDate($data->eDate);
		$data->eDate['sheets'] = $data->cDateSheet->count();

		throw new ViewAction($data);

	});

new \learning\DateSheetPage()
	->doDelete(function($data) {

		$data->eDate = $data->e['date'];
		$data->cDateSheet = \learning\DateSheetLib::getByDate($data->eDate);
		$data->eDate['sheets'] = $data->cDateSheet->count();

		throw new ViewAction($data);

	});
?>
