<?php
/**
 * Les fichiers à télécharger d'une leçon : déposés, rangés, renommés et retirés depuis l'écran de
 * la leçon, téléchargés depuis sa lecture.
 *
 * Chaque geste de l'auteur répond par la seule liste des fichiers (`view/attachment.v.php`),
 * jamais par l'écran entier, où le contenu de la leçon peut être en cours de saisie.
 */
new Page()
	/**
	 * Le fichier se sert par la leçon et jamais par l'adresse du stockage, publique pour qui connaît
	 * le hash : c'est le droit de lire la leçon qui ouvre le téléchargement.
	 */
	->get('/formation/fichier/{id}', function($data) {

		$eAttachment = \learning\AttachmentLib::getById(GET('id'))->validate();

		if($eAttachment['course']->acceptLearning() === FALSE) {
			throw new NotExistsAction();
		}

		if($eAttachment->assertRead() === FALSE) {
			\user\ConnectionLib::checkLogged();
		}

		$eAttachment->validate('assertRead');

		$path = \storage\DriverLib::directory().'/'.new \media\LearningAttachmentAiUi()->getBasenameByHash($eAttachment['hash']);

		if(is_file($path) === FALSE) {
			throw new NotExistsAction('Attachment file');
		}

		$content = file_get_contents($path);

		throw new DataAction($content, \media\MediaUi::getMimeType($eAttachment['hash']), $eAttachment['name'].'.'.\media\MediaUi::getExtension($eAttachment['hash']));

	})
	/**
	 * Le nom du fichier déposé, sans son extension, devient le nom affiché : il passe par `build()`
	 * comme un nom saisi, borné à la longueur que le schéma accepte.
	 */
	->post('doUpload', function($data) {

		$data->eLesson = \learning\LessonLib::getById(POST('lesson'))->validate('canUpdate', 'acceptUpdate');

		$file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];

		$fw = new FailWatch();

		if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) {
			Fail::log('media\\attachmentSize');
		} else if(
			$file['error'] !== UPLOAD_ERR_OK or
			is_uploaded_file($file['tmp_name']) === FALSE
		) {
			\learning\Attachment::fail('file.check');
		}

		$fw->validate();

		$eAttachment = new \learning\Attachment([
			'lesson' => $data->eLesson,
			'course' => $data->eLesson['course'],
		]);

		$eAttachment->build(\learning\AttachmentLib::getPropertiesCreate(), [
			'name' => \learning\AttachmentLib::getNameByFilename($file['name'])
		], new \PropertyList('create'));

		$fw->validate();

		\learning\AttachmentLib::upload($eAttachment, file_get_contents($file['tmp_name']));

		$fw->validate();

		$data->eLesson['cAttachment'] = \learning\AttachmentLib::getByLesson($data->eLesson);

		throw new ViewAction($data);

	})
	->post('doUpdatePositions', function($data) {

		$eLesson = \learning\LessonLib::getById(POST('lesson'))->validate('canUpdate', 'acceptUpdate');

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		$fw = new FailWatch();

		\learning\AttachmentLib::updatePositions($eLesson, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	});

new \learning\AttachmentPage()
	->update()
	->doUpdate(function($data) {

		$data->eLesson = $data->e['lesson'];
		$data->eLesson['cAttachment'] = \learning\AttachmentLib::getByLesson($data->eLesson);

		throw new ViewAction($data);

	})
	->doDelete(function($data) {

		$data->eLesson = $data->e['lesson'];
		$data->eLesson['cAttachment'] = \learning\AttachmentLib::getByLesson($data->eLesson);

		throw new ViewAction($data);

	});
?>
