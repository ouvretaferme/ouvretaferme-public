<?php
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		// L'avatar se fait pour la scène 3D : seul un membre d'une structure qui a la production y accède (`SceneLib::isOpenByStructures()`, lu par `Page.l.php`)
		if($data->hasAvatar === FALSE) {
			throw new NotExpectedAction('Avatar disabled');
		}

		// Une copie de celui du menu (`Page.l.php`) : la page lui pose une composition par défaut que le menu ne doit pas voir
		$data->eUserAvatar = clone $data->eUserOnline['eUserAvatar'];

	})
	->get('index', function($data) {

		$data->catalog = \user\UserAvatarLib::getCatalog();
		$data->families = \user\UserAvatarLib::getFamilies();

		// Un premier avatar part des défauts du catalogue, et remplace la photo de profil comme le veut le schéma. Celui
		// qu'un import a créé sans composition en part aussi, en gardant le choix de la photo fait avec le modèle.
		// `composed` dit ce qui revient quand on supprime le modèle, que la composition par défaut ne dit plus
		if($data->eUserAvatar->empty()) {
			$data->eUserAvatar['composed'] = FALSE;
			$data->eUserAvatar['composition'] = \user\UserAvatarLib::getDefault();
			$data->eUserAvatar['replacesPhoto'] = TRUE;
		} else if($data->eUserAvatar->hasComposition() === FALSE) {
			$data->eUserAvatar['composed'] = FALSE;
			$data->eUserAvatar['composition'] = \user\UserAvatarLib::getDefault();
		} else {
			$data->eUserAvatar['composed'] = TRUE;
			$data->eUserAvatar['composition'] = \user\UserAvatarLib::completeComposition($data->eUserAvatar['composition']);
		}

		$data->eUserAvatar['headless'] = FALSE;

		// Le modèle téléversé se montre même drapeau fermé, tant qu'il est en place : seul l'envoi d'un nouveau se ferme
		$data->eUserAvatar['eUserAvatarUpload'] = $data->eUserAvatar->exists() ? \user\UserAvatarUploadLib::getByAvatar($data->eUserAvatar) : new \user\UserAvatarUpload();
		$data->eUserAvatar['uploadOpen'] = \user\UserAvatarUploadLib::isOpenByStructures($data->ccStructureUser);
		$data->eUserAvatar['uploadRules'] = \scene\UploadLib::getRules('avatar');

		$data->sharedPalettes = \user\UserAvatarLib::getSharedPalettes($data->eUserAvatar['composition']);

		throw new ViewAction($data);

	})
	/**
	 * La composition, et l'image que l'éditeur en a rendue, dans le même envoi : l'une ne s'enregistre pas sans l'autre.
	 */
	->post('doSave', function($data) {

		$fw = new FailWatch();

		$eUserAvatar = $data->eUserAvatar;
		$eUserAvatar['user'] = $data->eUserOnline;

		$properties = $eUserAvatar->exists() ? \user\UserAvatarLib::getPropertiesUpdate() : \user\UserAvatarLib::getPropertiesCreate();

		$eUserAvatar->build($properties, $_POST);

		$file = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];

		if(
			$file['error'] !== UPLOAD_ERR_OK or
			is_uploaded_file($file['tmp_name']) === FALSE
		) {
			\user\UserAvatar::fail('image');
		}

		$fw->validate();

		\user\UserAvatarLib::save($eUserAvatar, $file['tmp_name']);

		throw new ReloadAction('user', 'UserAvatar::saved');

	})
	->post('doDelete', function($data) {

		if($data->eUserAvatar->empty()) {
			throw new NotExpectedAction('No avatar');
		}

		$data->eUserAvatar['user'] = $data->eUserOnline;

		\user\UserAvatarLib::delete($data->eUserAvatar);

		throw new ReloadAction('user', 'UserAvatar::deleted');

	});

/**
 * L'éditeur d'un avatar enregistré, ouvert par un navigateur sans écran que lance la reprise des images d'avatar
 * (`UserAvatarLib::capture()`) : le serveur ne rend pas la 3D, et l'éditeur y rend l'image de la composition comme à
 * l'enregistrement. Personne n'est connecté, l'adresse signée ouvre ce seul avatar, et la page n'écrit rien.
 */
new \user\UserAvatarPage()
	->remote('render', 'selling', function($data) {

		if(
			$data->e['source'] !== \user\UserAvatar::EDITOR or
			$data->e->hasComposition() === FALSE
		) {
			throw new NotExpectedAction('Not an editor composition');
		}

		$data->catalog = \user\UserAvatarLib::getCatalog();
		$data->families = \user\UserAvatarLib::getFamilies();

		$data->e['composition'] = \user\UserAvatarLib::completeComposition($data->e['composition']);
		$data->e['headless'] = TRUE;

		// L'image de profil est celle de la composition, que montre un avatar téléversé ou non
		$data->e['eUserAvatarUpload'] = new \user\UserAvatarUpload();
		$data->e['uploadOpen'] = FALSE;

		$data->sharedPalettes = \user\UserAvatarLib::getSharedPalettes($data->e['composition']);

		throw new ViewAction($data);

	});
?>
