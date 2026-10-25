<?php
/**
 * Le modèle 3D qu'un utilisateur téléverse comme avatar, qu'il en ait composé un ou non. Seul l'envoi est derrière le
 * drapeau (`UserSetting::$avatarUpload`) : un drapeau refermé laisse à chacun la suppression de son modèle et sa lecture,
 * sans quoi un avatar resterait montré sans qu'on puisse le retirer.
 */
$checkUploadOpen = function(stdClass $data): void {

	if(
		$data->hasAvatar === FALSE or
		\user\UserAvatarUploadLib::isOpenByStructures($data->ccStructureUser) === FALSE
	) {
		throw new NotExpectedAction('Avatar upload disabled');
	}

};

/**
 * Valider puis faire son avatar sont deux envois du même fichier, jugés par la même closure : l'aperçu ne dit pas
 * « Vérifié » à ce que l'envoi refuserait. Le drapeau et le droit d'abord, puis l'engagement, à l'envoi seulement, puis le
 * fichier : un refus de droit ou de drapeau ne coûte ni une place du débit ni une conversion, un oubli de la case non plus.
 * La case se coche sous le modèle vérifié, on ne s'engage que sur un fichier qui passe : `$create` la demande. Elle n'écrit
 * rien en base ; le débit, lui, compte chaque envoi, l'aperçu comme l'import.
 *
 * Le fichier passe par `build()` sous son chemin, que la page seule pose, et seulement pour un envoi complet reçu par PHP :
 * la conversion et ses refus vivent dans `properties()`, le test les éprouve sur un fichier qu'il écrit lui-même.
 */
$buildUpload = function(stdClass $data, bool $create) use($checkUploadOpen): \user\UserAvatarUpload {

	$checkUploadOpen($data);

	$eUserAvatarUpload = new \user\UserAvatarUpload([
		'user' => $data->eUserOnline
	]);

	$eUserAvatarUpload->validate('canCreate', 'acceptCreate');

	$file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
	$path = ($file['error'] === UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) ? $file['tmp_name'] : NULL;

	if($create) {

		$fw = new FailWatch();

		$eUserAvatarUpload->build(['commitment'], ['commitment' => POST('commitment')]);

		$fw->validate();

	}

	$fw = new FailWatch();

	// Au-delà du plafond de PHP, aucun fichier n'arrive et seul le poids de la requête dit celui du fichier
	if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) {
		Fail::log(\user\UserAvatarUpload::getRefusal('weight', ['size' => (int)$_SERVER['CONTENT_LENGTH']]));
	} else {
		$eUserAvatarUpload->build(['file'], ['file' => $path]);
	}

	$fw->validate();

	return $eUserAvatarUpload;

};

/**
 * L'image que l'éditeur a rendue de ce que l'avatar montre, postée avec le choix de remplacer la photo, NULL sans envoi
 * complet reçu par PHP. Elle n'est due que si l'avatar remplace la photo.
 */
$getImage = function(\user\UserAvatar $eUserAvatar): ?string {

	$file = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
	$image = ($file['error'] === UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) ? $file['tmp_name'] : NULL;

	if($eUserAvatar['replacesPhoto'] and $image === NULL) {
		\user\UserAvatar::fail('image');
	}

	return $image;

};

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	/**
	 * Le fichier se sert par l'envoi et jamais par l'adresse du stockage, publique pour qui connaît le hash, qui ne sort
	 * donc pas du serveur. L'auteur le lit toujours, l'équipe de Ouvretaferme pour le juger (`canModerate()`), les autres
	 * membres dans la structure que la scène nomme en `structure`, jamais devinée (`UserAvatarUpload::canReadByStructure()`),
	 * jugée en dernier parce qu'elle seule coûte des requêtes.
	 *
	 * Le droit se juge avant l'état, et tout refus rend la même réponse : un envoi absent, retiré, sans fichier ou hors
	 * droit ne se distinguent pas, et les ids, qui se suivent, ne disent rien des envois des autres.
	 */
	->get('/user/avatar/modele/{id}', function($data) {

		$eUserAvatarUpload = \user\UserAvatarUploadLib::getById(GET('id'));

		if(
			$eUserAvatarUpload->empty() or
			(
				$eUserAvatarUpload->canRead() === FALSE and
				$eUserAvatarUpload->canModerate() === FALSE and
				$eUserAvatarUpload->canReadByStructure(\structure\StructureLib::getById(GET('structure'))) === FALSE
			) or
			$eUserAvatarUpload->acceptRead() === FALSE
		) {
			throw new NotExistsAction('UserAvatarUpload file');
		}

		$path = \storage\DriverLib::directory().'/'.new \media\UserAvatarUploadUi()->getBasenameByHash($eUserAvatarUpload['hash']);

		if(is_file($path) === FALSE) {
			throw new NotExistsAction('UserAvatarUpload file');
		}

		// Le fichier vient de l'utilisateur et se sert sous l'adresse du site : le navigateur ne doit pas deviner un autre type que le binaire annoncé
		header('X-Content-Type-Options: nosniff');

		throw new DataAction(file_get_contents($path), \media\MediaUi::getMimeType($eUserAvatarUpload['hash']));

	})
	/**
	 * Le modèle converti revient à l'aperçu de l'éditeur, index et binaire, sans qu'aucune ligne s'écrive : le binaire
	 * voyage en base64, qu'il pèse au plus ce que les règles admettent (`maxBytes`).
	 */
	->post('doPreview', function($data) use($buildUpload) {

		$eUserAvatarUpload = $buildUpload($data, FALSE);

		$data->upload = [
			'index' => $eUserAvatarUpload['bakedIndex'],
			'buffer' => base64_encode($eUserAvatarUpload['bakedBuffer'])
		];

		throw new ViewAction($data);

	})
	/**
	 * L'onglet du modèle se repeint sur le modèle en place, avec un message qui dit qui le voit désormais : le reste de
	 * l'éditeur, la composition et son catalogue, ne change pas.
	 */
	->post('doCreate', function($data) use($buildUpload, $getImage) {

		$eUserAvatarUpload = $buildUpload($data, TRUE);

		$eUserAvatar = $data->eUserOnline['eUserAvatar'];

		$fw = new FailWatch();

		$eUserAvatar->build(['replacesPhoto'], $_POST);

		$image = $getImage($eUserAvatar);

		$fw->validate();

		\user\UserAvatarUploadLib::create($eUserAvatarUpload, $eUserAvatar, $image);

		// Après `create()` et non dedans : l'alerte suit un envoi validé, et Discord ne fait pas attendre son COMMIT
		\user\UserAvatarUploadLib::notify($eUserAvatarUpload);

		// `$buildUpload` a passé `$checkUploadOpen` : l'envoi est ouvert
		$data->eUserAvatar = \user\UserAvatarLib::getByUser($data->eUserOnline);
		$data->eUserAvatar['composed'] = $data->eUserAvatar->hasComposition();
		$data->eUserAvatar['eUserAvatarUpload'] = \user\UserAvatarUploadLib::getByAvatar($data->eUserAvatar);
		$data->eUserAvatar['uploadOpen'] = TRUE;
		$data->eUserAvatar['uploadRules'] = \scene\UploadLib::getRules('avatar');

		throw new ViewAction($data);

	})
	->post('doClear', function($data) use($getImage) {

		$eUserAvatar = $data->eUserOnline['eUserAvatar'];

		if($eUserAvatar->empty()) {
			throw new NotExpectedAction('No avatar');
		}

		$eUserAvatar['user'] = $data->eUserOnline;

		$fw = new FailWatch();

		// Un avatar sans composition part avec son modèle : il n'a pas d'image à rendre
		$image = $eUserAvatar->hasComposition() ? $getImage($eUserAvatar) : NULL;

		$fw->validate();

		\user\UserAvatarUploadLib::clear($eUserAvatar, $image);

		// Comme l'enregistrement d'une composition dont il ramène l'avatar : le panneau se ferme sur la page rechargée

		throw new ReloadAction('user', 'UserAvatarUpload::cleared');

	})
	/**
	 * L'interrupteur de la photo, sous un modèle en place, s'applique tout de suite : l'éditeur poste l'image du modèle
	 * quand il se met à remplacer la photo.
	 */
	->post('doPhoto', function($data) use($getImage) {

		$eUserAvatar = $data->eUserOnline['eUserAvatar'];

		if($eUserAvatar->empty()) {
			throw new NotExpectedAction('No avatar');
		}

		$eUserAvatar['user'] = $data->eUserOnline;

		$fw = new FailWatch();

		$eUserAvatar->build(['replacesPhoto'], $_POST);

		$image = $getImage($eUserAvatar);

		$fw->validate();

		if(\user\UserAvatarLib::updatePhoto($eUserAvatar, $image) === FALSE) {
			throw new FailAction('user\UserAvatarUpload::stale');
		}

		// Relu : la photo mise de côté, dont dépend la confirmation de la suppression, s'est écrite sur la copie verrouillée
		$data->eUserAvatar = \user\UserAvatarLib::getByUser($data->eUserOnline);

		throw new ViewAction($data);

	})
	/**
	 * Le skill Claude qui aide à préparer un modèle et le juge avec le validateur du serveur, proposé à côté de l'envoi
	 * et fermé avec lui.
	 */
	->get('skill', function($data) use($checkUploadOpen) {

		$checkUploadOpen($data);

		// Une archive servie sous l'adresse du site : le navigateur ne doit pas deviner un autre type que le zip annoncé
		header('X-Content-Type-Options: nosniff');

		throw new DataAction(\scene\UploadSkillLib::getArchive('avatar'), 'application/zip', \scene\UploadSkillLib::getName('avatar').'.zip');

	});
?>
