<?php
namespace map;

/**
 * Le panneau « Nouvel élément » d'une rangée Eau, Nature ou Matériel, ouvert par sa tuile « Importer » : le nom en tête, puis
 * le dépôt et l'aide de l'avatar et du bâtiment (`\scene\UploadUi`). L'aperçu, caché jusque-là, montre le modèle vérifié
 * avant qu'on l'ajoute (`MapSceneObject`, `sceneObject.js`). Un élément n'a rien d'autre à régler : il se supprime, et un
 * autre s'importe.
 */
class DecorObjectUi {

	public function __construct() {

		\Asset::css('map', 'building.css');
		\Asset::js('map', 'sceneObject.js');

	}

	public function create(DecorObject $e, array $bake): \Panel {

		$e->expects(['structure', 'family', 'uploadRules']);

		$form = new \util\FormUi();

		$h = $form->hidden('structure', $e['structure']);
		$h .= $form->hidden('family', $e['family']);

		$preview = '<div class="util-composer-preview hide">';
			$preview .= new \scene\SceneUi()->getContainer('map-object-scene', $bake);
		$preview .= '</div>';

		$content = $form->dynamicGroup($e, 'name', function(\PropertyDescriber $d) use($e) {
			$d->attributes = [
				'placeholder' => match($e['family']) {
					DecorObject::WATER => s("Abreuvoir"),
					DecorObject::TREE => s("Noyer"),
					DecorObject::OTHER => s("Broyeur"),
				}
			];
		});

		$content .= $this->getDrop($form, $e);

		// Le panneau n'a pas de pied, qui ne joue pas de onrender dans un dialogue : l'aperçu démarre du corps
		$h .= '<div class="util-composer" onrender="MapSceneObject.start(this.closest(\'form\'))">'.$preview.'<div>'.$content.'</div></div>';

		return new \Panel(
			id: 'panel-map-decor-object-create',
			title: s("Nouvel élément"),
			subTitle: self::p('family')->values[$e['family']],
			dialogOpen: $form->openAjax('/map/decorObject:doCreate', [
				'class' => 'panel-dialog',
				'autocomplete' => 'off',
			]),
			dialogClose: $form->close(),
			body: $h
		);

	}

	protected function getDrop(\util\FormUi $form, DecorObject $e): string {

		$rules = $e['uploadRules'];

		$uploadUi = new \scene\UploadUi();

		return $uploadUi->getDrop(
			$form,
			'MapSceneObject',
			'/map/decorObject:doCreate',
			'/map/decorObject:doPreview',
			$rules,
			'map\\DecorUpload::file.conformity',
			FALSE,
			[
				'head' => s("Glissez votre modèle 3D ici"),
				'tooHeavy' => \user\AlertUi::getAvatarUploadWeight(\util\TextUi::size('{size}'), \util\TextUi::size($rules['maxBytes'])),
				'commitment' => s("Ce modèle ne montre aucun logo ni aucune personne réelle."),
				'add' => s("Ajouter cet élément"),
				'adding' => s("Ajout..."),
			],
			$uploadUi->getSkill(
				'/map/decorUpload:skill?structure='.$e['structure']['id'],
				s("Téléchargez le skill du décor."),
				match($e['family']) {
					DecorObject::WATER => s("Demandez-lui un élément d'eau, d'après une photo ou une description."),
					DecorObject::TREE => s("Demandez-lui un arbre ou une plante, d'après une photo ou une description."),
					DecorObject::OTHER => s("Demandez-lui un matériel, d'après une photo ou une description."),
				}
			),
			s("J'accepte que Ouvretaferme propose ce modèle aux autres utilisateurs, sans contrepartie.")
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DecorObject::model()->describer($property, [
			'family' => s("Rangée"),
			'name' => s("Nom"),
		]);

		switch($property) {

			case 'family' :
				$d->values = [
					DecorObject::WATER => s("Eau"),
					DecorObject::TREE => s("Nature"),
					DecorObject::OTHER => s("Matériel"),
				];
				break;

		}

		return $d;

	}

}
?>
