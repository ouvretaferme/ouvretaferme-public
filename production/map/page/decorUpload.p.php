<?php
/**
 * Le modèle 3D qu'une structure téléverse pour en faire un bâtiment neuf de sa rangée. Seuls l'envoi et le skill sont
 * derrière le drapeau (`MapSetting::$decorUploadEnabled`) : drapeau refermé, le modèle en place se lit encore, et il ne se
 * retire qu'avec son bâtiment, par la poubelle de la rangée. Le fichier se lit par qui voit le plan de la structure, comme
 * la scène qui le monte.
 *
 * Valider puis ajouter sont deux envois du même fichier, jugés par la même closure : l'aperçu ne dit pas « Vérifié » à ce
 * que l'ajout refuserait. Elle relit la structure et le hangar, valide le droit de créer, puis le drapeau, puis le
 * bâtiment et, à l'ajout seulement, les cases, et le fichier en dernier : un refus de droit ou de drapeau ne coûte ni une
 * place du débit ni une conversion. Les cases se cochent sous le modèle vérifié, on ne s'engage que sur un fichier qui
 * passe : `$create` les demande. Elle n'écrit rien en base ; le débit, lui, compte chaque envoi, l'aperçu comme l'ajout.
 */
$buildUpload = function(bool $create): \map\DecorUpload {

	$eStructure = \structure\StructureLib::getById(POST('structure'))->validate();

	$eDecorBuilding = new \map\DecorBuilding([
		'structure' => $eStructure,
		'eDecor' => \map\DecorLib::getHangar($eStructure, POST('decor', '?int'))
	]);

	$eDecorBuilding->validate('canCreate');

	if(\map\MapSetting::isDecorUploadEnabled($eStructure) === FALSE) {
		throw new NotExpectedAction('Decor upload disabled');
	}

	$eDecorUpload = new \map\DecorUpload([
		'structure' => $eStructure,
		'building' => $eDecorBuilding
	]);

	$file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
	$path = ($file['error'] === UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) ? $file['tmp_name'] : NULL;

	// Les cases et le bâtiment se jugent seuls et d'abord : un oubli de l'engagement ne coûte ni une conversion ni une place du débit
	$fw = new FailWatch();

	if($create) {
		$eDecorUpload->build(['commitment', 'shared'], ['commitment' => POST('commitment'), 'shared' => POST('shared')]);
	}

	$eDecorBuilding->build(\map\DecorBuildingLib::getPropertiesCreate(), $_POST);

	$fw->validate();

	$fw = new FailWatch();

	// Au-delà du plafond de PHP, aucun fichier n'arrive et seul le poids de la requête dit celui du fichier
	if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) {
		Fail::log(\map\DecorUpload::getRefusal('weight', ['size' => (int)$_SERVER['CONTENT_LENGTH']], \map\DecorUploadLib::FAMILY_BUILDING));
	} else {
		$eDecorUpload->build(['file'], ['file' => $path]);
	}

	$fw->validate();

	return $eDecorUpload;

};

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	/**
	 * Le fichier, celui d'un bâtiment comme celui d'un élément d'une rangée (`decorObject.p.php`), se sert par l'envoi et
	 * jamais par l'adresse du stockage, publique pour qui connaît le hash. Le droit se juge
	 * avant l'état, et tout refus rend la même réponse : un envoi absent, retiré, sans fichier ou hors droit ne se
	 * distinguent pas, et les ids, qui se suivent, ne disent rien des envois des autres structures.
	 */
	->get('model', function($data) {

		$eDecorUpload = \map\DecorUploadLib::getById(GET('id'));

		// L'équipe le lit aussi, pour l'examiner (`admin/decorUpload.p.php`) : elle n'est pas membre de la structure
		if(
			$eDecorUpload->empty() or
			($eDecorUpload->canRead() === FALSE and $eDecorUpload->canModerate() === FALSE) or
			$eDecorUpload->acceptRead() === FALSE
		) {
			throw new NotExistsAction('DecorUpload file');
		}

		$path = \storage\DriverLib::directory().'/'.new \media\MapDecorUploadUi()->getBasenameByHash($eDecorUpload['hash']);

		if(is_file($path) === FALSE) {
			throw new NotExistsAction('DecorUpload file');
		}

		// Le fichier vient de l'utilisateur et se sert sous l'adresse du site : le navigateur ne doit pas deviner un autre type que le binaire annoncé
		header('X-Content-Type-Options: nosniff');

		throw new DataAction(file_get_contents($path), \media\MediaUi::getMimeType($eDecorUpload['hash']));

	})
	/**
	 * Le modèle converti revient à l'aperçu, index et binaire, sans qu'aucune ligne s'écrive : le binaire voyage en base64,
	 * qu'il pèse au plus ce que les règles admettent (`maxBytes`).
	 */
	->post('doPreview', function($data) use($buildUpload) {

		$eDecorUpload = $buildUpload(FALSE);

		$data->upload = [
			'index' => $eDecorUpload['bakedIndex'],
			'buffer' => base64_encode($eDecorUpload['bakedBuffer'])
		];

		throw new ViewAction($data);

	})
	/**
	 * Le bâtiment se crée avec son envoi, sous la composition de l'onglet « Composer en ligne », que le bâtiment garde sans la
	 * montrer, et ne s'écrit qu'après la conversion : un fichier refusé ne laisse aucun bâtiment.
	 */
	->post('doCreate', function($data) use($buildUpload) {

		$eDecorUpload = $buildUpload(TRUE);

		// Un refus de la création du bâtiment (`structure.limit`, `decorChanged`) vient du Lib, sous verrou
		$fw = new FailWatch();

		\map\DecorUploadLib::create($eDecorUpload);

		$fw->validate();

		$eDecorBuilding = $eDecorUpload['building'];

		\map\DecorUploadLib::notify($eDecorUpload);

		$data->decor = $eDecorBuilding['eDecor']->empty() ? NULL : $eDecorBuilding['eDecor']['id'];
		$data->building = \map\DecorBuildingLib::getScene($eDecorBuilding, $eDecorUpload);

		throw new ViewAction($data);

	})
	/**
	 * Le skill Claude qui aide à préparer un bâtiment et le juge avec le validateur du serveur, proposé à côté de l'envoi et
	 * fermé avec lui.
	 */
	->get('skill', function($data) {

		$eStructure = \structure\StructureLib::getById(GET('structure'))->validate('canManage');

		if(\map\MapSetting::isDecorUploadEnabled($eStructure) === FALSE) {
			throw new NotExpectedAction('Decor upload disabled');
		}

		// Une archive servie sous l'adresse du site : le navigateur ne doit pas deviner un autre type que le zip annoncé
		header('X-Content-Type-Options: nosniff');

		throw new DataAction(\scene\UploadSkillLib::getArchive(\map\DecorUploadLib::PROFILE), 'application/zip', \scene\UploadSkillLib::getName(\map\DecorUploadLib::PROFILE).'.zip');

	});
?>
