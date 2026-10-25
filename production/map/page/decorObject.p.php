<?php
/**
 * Les éléments qu'une structure importe dans les rangées Eau, Nature et Matériel de son décor. Un élément n'a pas de
 * composition de repli : il naît de son envoi, et se retire par la poubelle de sa vignette, ses poses avec lui. L'import est
 * derrière le drapeau des modèles téléversés (`MapSetting::isDecorUploadEnabled()`) ; drapeau refermé, un élément en place se
 * montre et se supprime encore.
 *
 * Valider puis ajouter sont deux envois du même fichier, jugés par la même closure : l'aperçu ne dit pas « Vérifié » à ce
 * que l'ajout refuserait. Elle relit la structure, valide le droit de créer, puis le drapeau, puis la famille et le nom et,
 * à l'ajout seulement, les cases, et le fichier en dernier : un nom refusé ne coûte ni une place du débit ni une conversion.
 * Elle n'écrit rien en base ; le débit, lui, compte chaque envoi, l'aperçu comme l'ajout.
 */
$buildUpload = function(bool $create): \map\DecorUpload {

	$eStructure = \structure\StructureLib::getById(POST('structure'))->validate();

	$eDecorObject = new \map\DecorObject([
		'structure' => $eStructure
	]);

	$eDecorObject->validate('canCreate');

	if(\map\MapSetting::isDecorUploadEnabled($eStructure) === FALSE) {
		throw new NotExpectedAction('Decor upload disabled');
	}

	$eDecorUpload = new \map\DecorUpload([
		'structure' => $eStructure,
		'building' => new \map\DecorBuilding(),
		'object' => $eDecorObject
	]);

	$file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
	$path = ($file['error'] === UPLOAD_ERR_OK and is_uploaded_file($file['tmp_name'])) ? $file['tmp_name'] : NULL;

	// La famille donne les règles du fichier : elle se juge avec le nom et les cases, avant lui
	$fw = new FailWatch();

	$eDecorObject->build(\map\DecorObjectLib::getPropertiesCreate(), $_POST);

	if($create) {
		$eDecorUpload->build(['commitment', 'shared'], ['commitment' => POST('commitment'), 'shared' => POST('shared')]);
	}

	$fw->validate();

	$fw = new FailWatch();

	// Au-delà du plafond de PHP, aucun fichier n'arrive et seul le poids de la requête dit celui du fichier
	if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], TRUE)) {
		Fail::log(\map\DecorUpload::getRefusal('weight', ['size' => (int)$_SERVER['CONTENT_LENGTH']], $eDecorObject['family']));
	} else {
		$eDecorUpload->build(['file'], ['file' => $path]);
	}

	$fw->validate();

	return $eDecorUpload;

};

// La suppression est l'action de ModulePage, qui valide canDelete() sur l'élément relu par son id
new \map\DecorObjectPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	/**
	 * Le panneau « Nouvel élément » d'une rangée, ouvert par sa tuile « Importer ». La famille se juge comme à l'envoi, sur la
	 * même liste de valeurs.
	 */
	->get('create', function($data) {

		$eStructure = \structure\StructureLib::getById(GET('structure'))->validate();

		$data->e = new \map\DecorObject([
			'structure' => $eStructure,
			'name' => NULL
		]);

		$data->e->validate('canCreate');

		if(\map\MapSetting::isDecorUploadEnabled($eStructure) === FALSE) {
			throw new NotExpectedAction('Decor upload disabled');
		}

		$fw = new FailWatch();

		$data->e->build(['family'], ['family' => GET('family')]);

		$fw->validate();

		\map\DecorObjectLib::fillEditor($data->e);

		$data->bake = \scene\BakeLib::getCatalogAndVersions();

		throw new ViewAction($data);

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
	 * L'élément se crée avec son envoi, après la conversion : un fichier refusé ne laisse aucun élément.
	 */
	->post('doCreate', function($data) use($buildUpload) {

		$eDecorUpload = $buildUpload(TRUE);

		// Un refus de la création de l'élément (`structure.limit`) vient du Lib, sous verrou
		$fw = new FailWatch();

		\map\DecorUploadLib::createByObject($eDecorUpload);

		$fw->validate();

		\map\DecorUploadLib::notify($eDecorUpload);

		$data->object = \map\DecorObjectLib::getScene($eDecorUpload['object'], $eDecorUpload);

		throw new ViewAction($data);

	})
	->doDelete(fn($data) => throw new ViewAction($data));
?>
