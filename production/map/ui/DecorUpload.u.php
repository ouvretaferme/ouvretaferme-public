<?php
namespace map;

/**
 * L'onglet « Importer un modèle 3D » de l'éditeur d'un bâtiment neuf (`DecorBuildingUi`) : le nom, le dépôt et l'aide,
 * ceux de l'avatar (`\scene\UploadUi`), puis le modèle vérifié, que « Valider » montre dans l'aperçu avant qu'on l'ajoute.
 */
class DecorUploadUi {

	// Le binaire se sert par sa route, après contrôle des droits, jamais par l'adresse du stockage
	public static function url(DecorUpload $e): string {
		return '/map/decorUpload:model?id='.$e['id'];
	}

	public function getTab(\util\FormUi $form, DecorBuilding $eDecorBuilding): string {

		$eDecorBuilding->expects(['uploadRules']);

		$h = $form->dynamicGroup($eDecorBuilding, 'name', function(\PropertyDescriber $d) {
			$d->attributes = ['placeholder' => s("Grange")];
		});

		$h .= $this->getDrop($form, $eDecorBuilding);

		return $h;

	}

	protected function getDrop(\util\FormUi $form, DecorBuilding $eDecorBuilding): string {

		$rules = $eDecorBuilding['uploadRules'];

		$uploadUi = new \scene\UploadUi();

		return $uploadUi->getDrop(
			$form,
			'MapSceneBuilding',
			'/map/decorUpload:doCreate',
			'/map/decorUpload:doPreview',
			$rules,
			'map\\DecorUpload::file.conformity',
			FALSE,
			[
				'head' => s("Glissez votre modèle 3D ici"),
				'tooHeavy' => \user\AlertUi::getAvatarUploadWeight(\util\TextUi::size('{size}'), \util\TextUi::size($rules['maxBytes'])),
				'commitment' => s("Ce modèle ne montre aucun logo ni aucune personne réelle."),
				'add' => s("Ajouter ce bâtiment"),
				'adding' => s("Ajout..."),
			],
			$uploadUi->getSkill(
				'/map/decorUpload:skill?structure='.$eDecorBuilding['structure']['id'],
				s("Téléchargez le skill du décor."),
				s("Demandez-lui un bâtiment, d'après une photo ou une description.")
			),
			s("J'accepte que Ouvretaferme propose ce modèle aux autres utilisateurs, sans contrepartie.")
		);

	}

}
?>
