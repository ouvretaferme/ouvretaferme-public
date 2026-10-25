<?php
namespace map;

/**
 * L'éditeur d'un bâtiment de la structure, mis en page comme celui de l'avatar (`util-composer`) : le bâtiment qu'on fait
 * tourner à gauche, ses cotes puis ses choix à droite. Les choix sont ceux du type dans `choices.json`, que le formulaire
 * porte pour `MapSceneBuilding` (`sceneBuilding.js`), qui monte l'aperçu, peint les vignettes et tient `settings` à jour.
 *
 * Quand l'envoi est ouvert, un bâtiment neuf se compose ou s'importe : deux onglets en tête du corps, au-dessus de l'aperçu,
 * qui ne montre sur l'onglet du modèle que le modèle vérifié (`DecorUploadUi`). Le panneau perd alors son sous-titre, qui
 * dirait « Composer » sur l'onglet du modèle. Un bâtiment enregistré se compose seulement : importé, il ne se modifie pas.
 */
class DecorBuildingUi {

	public function __construct() {

		\Asset::css('map', 'building.css');
		\Asset::js('map', 'sceneBuilding.js');

	}

	public function create(DecorBuilding $e, array $choices, array $bake): \Panel {

		$e->expects(['structure', 'eDecor', 'type', 'uploadEnabled']);

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('structure', $e['structure']);

		if($e['eDecor']->notEmpty()) {
			$h .= $form->hidden('decor', $e['eDecor']);
			$h .= $form->hidden('type', $e['type']);
		}

		$h .= $this->getEditor($form, $e, $choices, $bake, $e['uploadEnabled']);

		return new \Panel(
			id: 'panel-map-decor-building-create',
			title: match($e['type']) {
				DecorBuilding::BUILDING, DecorBuilding::HOUSE => s("Nouveau bâtiment"),
				DecorBuilding::HANGAR => s("Hangar"),
			},
			subTitle: $e['uploadEnabled'] ? NULL : $this->getSubTitle($e),
			dialogOpen: $this->getOpen($form, '/map/decorBuilding:doCreate'),
			dialogClose: $form->close(),
			body: $h,
			footer: $this->getFooter($form, $e)
		);

	}

	public function update(DecorBuilding $e, array $choices, array $bake): \Panel {

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('id', $e['id']);
		$h .= $this->getEditor($form, $e, $choices, $bake, FALSE);

		return new \Panel(
			id: 'panel-map-decor-building-update',
			title: encode($e['name']),
			subTitle: $this->getSubTitle($e),
			dialogOpen: $this->getOpen($form, '/map/decorBuilding:doUpdate'),
			dialogClose: $form->close(),
			body: $h,
			footer: $this->getFooter($form, $e)
		);

	}

	protected function getSubTitle(DecorBuilding $e): string {

		return match($e['type']) {
			DecorBuilding::BUILDING => s("Composer un bâtiment"),
			DecorBuilding::HANGAR => s("Personnaliser le hangar"),
			DecorBuilding::HOUSE => s("Composer une maison"),
		};

	}

	protected function getOpen(\util\FormUi $form, string $url): string {

		return $form->openAjax($url, [
			'class' => 'panel-dialog',
			'autocomplete' => 'off',
		]);

	}

	/**
	 * Lime.Panel.paint() peint le pied après le corps, et dialogOpen ne joue pas de onrender : l'éditeur, qui a besoin des
	 * deux, démarre du pied, comme celui de l'avatar. Le pied n'enregistre qu'une composition : l'onglet du modèle a son
	 * geste dans le dépôt, et l'éditeur cache le pied sur cet onglet.
	 */
	protected function getFooter(\util\FormUi $form, DecorBuilding $e): string {

		$h = '<div class="util-action" onrender="MapSceneBuilding.start(this.closest(\'form\'))">';

			$h .= $form->submit(match($e['type']) {
				DecorBuilding::BUILDING => s("Enregistrer le bâtiment"),
				DecorBuilding::HANGAR => s("Enregistrer le hangar"),
				DecorBuilding::HOUSE => s("Enregistrer la maison"),
			});

		$h .= '</div>';

		return $h;

	}

	/**
	 * `$choices` porte `choices.json` entier, que le formulaire passe à `MapSceneBuilding` : les vignettes du type montrent le
	 * bâtiment de départ de chaque type. `$upload` ajoute l'onglet du modèle 3D.
	 */
	protected function getEditor(\util\FormUi $form, DecorBuilding $e, array $choices, array $bake, bool $upload): string {

		// Ce que l'éditeur lit se pose dans le corps et non sur le formulaire : un panneau rouvert sur un autre type garde
		// l'enveloppe du premier (`Lime.Panel.dialog()`), et seul son contenu change
		$h = '<div data-building-editor data-choices="'.encode(json_encode($choices)).'" data-structure="'.$e['structure']['id'].'" data-type="'.$e['type'].'"';
			if($e->exists()) {
				$h .= ' data-building="'.$e['id'].'"';
			}
		$h .= '>';

			$preview = '<div class="util-composer-preview">';
				$preview .= new \scene\SceneUi()->getContainer('map-building-scene', $bake);
				// Le hangar garde le toit, la largeur et le panneau du catalogue : il n'y a pas assez à tirer pour un hasard
				if($e['type'] !== DecorBuilding::HANGAR) {
					$preview .= '<a class="btn btn-outline-primary" data-building-random="true">'.\Asset::icon('shuffle').' '.s("Au hasard").'</a>';
				}
			$preview .= '</div>';

			$compose = $this->getComposition($form, $e, $choices[$e['type']]);

			if($upload) {
				$h .= new \scene\UploadUi()->getTabs('compose', [
					'compose' => [s("Composer en ligne"), $compose],
					'upload' => [s("Importer un modèle 3D"), new DecorUploadUi()->getTab($form, $e)],
				], 'MapSceneBuilding', $preview);
			} else {
				$h .= '<div class="util-composer">'.$preview.'<div>'.$compose.'</div></div>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getComposition(\util\FormUi $form, DecorBuilding $e, array $choicesType): string {

		$h = '';

		// Le type ne change qu'à la création, et un hangar n'en choisit pas : il part du hangar posé
		if($e->exists() === FALSE and $e['type'] !== DecorBuilding::HANGAR) {
			$h .= $form->group(self::p('type')->label, $form->choices('type', $this->getTypes(), $e['type']));
		}

		$h .= '<div class="map-building-dimensions">';
			foreach(['length', 'width', 'height'] as $property) {
				if($choicesType[$property] !== NULL) {
					$h .= $form->dynamicGroup($e, $property, function(\PropertyDescriber $d) use($choicesType, $property) {
						$d->attributes = [
							'min' => $choicesType[$property][0],
							'max' => $choicesType[$property][1],
							// Au décimètre : une cote de départ comme les 4,2 m des murs d'un bâtiment ne tombe pas sur un demi-mètre
							'step' => '0.1',
							'data-building-dimension' => $property,
						];
					});
				}
			}
		$h .= '</div>';

		$h .= $form->dynamicGroup($e, 'name', function(\PropertyDescriber $d) use($e) {
			$d->default = self::p('type')->values[$e['type']];
		});

		$h .= $form->hidden('settings', json_encode($e['settings']));

		$h .= $this->getSettings($form, $e['type'], $choicesType, $e['settings']);

		return $h;

	}

	/**
	 * Le type se change en rouvrant l'éditeur sur ses choix de départ : les deux types n'ont pas les mêmes choix. Les
	 * vignettes, comme toutes celles de l'éditeur, sont peintes par `MapSceneBuilding`.
	 */
	protected function getTypes(): array {

		$values = [];

		foreach([DecorBuilding::BUILDING, DecorBuilding::HOUSE] as $type) {
			$values[$type] = ['label' => self::p('type')->values[$type], 'image' => '<img alt="" draggable="false">'];
		}

		return $values;

	}

	/**
	 * Un groupe par choix du type, dans l'ordre de `choices.json`. Une teinte (`tints`) se range sous le matériau qu'elle
	 * colore, et un choix de couleur sans matériau (les menuiseries) a son groupe de pastilles.
	 */
	protected function getSettings(\util\FormUi $form, string $type, array $choices, array $settings): string {

		$tints = array_flip(array_map(fn(array $tint) => $tint[0], $choices['tints']));

		$h = '';

		foreach($choices['settings'] as $key => $values) {

			if(array_key_exists($key, $choices['tints'])) {
				continue;
			}

			if(array_key_exists($key, $choices['colors'])) {
				$field = $this->getTints($form, $key, $choices['colors'][$key], $settings[$key]);
			} else {

				$field = $form->choices($key, $this->getValues($key, $values), $settings[$key]);

				if(array_key_exists($key, $tints)) {
					$tint = $tints[$key];
					$field .= $this->getTints($form, $tint, $choices['colors'][$tint], $settings[$tint]);
				}

			}

			$h .= $form->group($this->getLabel($type, $key), $field, ['for' => FALSE]);

		}

		return $h;

	}

	protected function getTints(\util\FormUi $form, string $key, array $colors, string $selected): string {
		return '<div class="util-composer-tints">'.$form->colorChoices($key, $colors, $selected).'</div>';
	}

	protected function getValues(string $key, array $values): array {

		$labels = self::p($key)->values;

		$choices = [];

		foreach($values as $value) {
			$choices[$value] = ['label' => $labels[$value], 'image' => '<img alt="" draggable="false">'];
		}

		return $choices;

	}

	// Le hangar nomme ses murs par leur bardage, la maison sa porte et ses fenêtres par ce qu'elles sont chez elle
	protected function getLabel(string $type, string $key): string {

		$property = match([$type, $key]) {
			[DecorBuilding::HANGAR, 'wall'] => 'cladding',
			[DecorBuilding::HOUSE, 'door'] => 'entrance',
			[DecorBuilding::HOUSE, 'windows'] => 'windowsByFacade',
			default => $key,
		};

		return self::p($property)->label;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DecorBuilding::model()->describer($property, [
			'type' => s("Type"),
			'name' => s("Nom"),
			'length' => s("Longueur"),
			'width' => s("Largeur"),
			'height' => s("Hauteur des murs"),
			'roof' => s("Toit"),
			'cover' => s("Couverture"),
			'wall' => s("Murs"),
			'cladding' => s("Bardage"),
			'levels' => s("Niveaux"),
			'windows' => s("Fenêtres"),
			'windowsByFacade' => s("Fenêtres par façade"),
			'shutters' => s("Volets"),
			'door' => s("Grande porte"),
			'entrance' => s("Porte d'entrée"),
			'joinery' => s("Menuiseries"),
			'chimney' => s("Cheminée"),
		]);

		switch($property) {

			case 'type' :
				$d->values = [
					DecorBuilding::BUILDING => s("Bâtiment"),
					DecorBuilding::HANGAR => s("Hangar"),
					DecorBuilding::HOUSE => s("Maison"),
				];
				break;

			case 'length' :
			case 'width' :
			case 'height' :
				$d->append = s("m");
				break;

			case 'roof' :
				$d->values = [
					'deux-pans' => s("Deux pans"),
					'appentis' => s("Un pan"),
					'croupe' => s("Quatre pans"),
				];
				break;

			case 'cover' :
				$d->values = [
					'tole' => s("Tôle"),
					'tuile' => s("Tuile"),
					'ardoise' => s("Ardoise"),
					'panneaux-solaires' => s("Panneaux solaires"),
				];
				break;

			case 'wall' :
				$d->values = [
					'bardage-bois' => s("Bardage bois"),
					'bardage-tole' => s("Bardage tôle"),
					'crepi' => s("Enduit"),
					'pierre' => s("Pierre"),
				];
				break;

			case 'levels' :
				$d->values = [
					1 => s("Plain-pied"),
					2 => s("Un étage"),
				];
				break;

			case 'windows' :
				$d->values = [
					0 => s("Aucune"),
					2 => s("Deux"),
					3 => s("Trois"),
					4 => s("Quatre"),
				];
				break;

			case 'shutters' :
				$d->values = [
					'aucun' => s("Sans"),
					'paire' => s("Ouverts"),
					'clos' => s("Fermés"),
				];
				break;

			case 'door' :
				$d->values = [
					'porte-pleine' => s("Pleine"),
					'porte-coulissante' => s("Coulissante"),
					'porte-vitree' => s("Vitrée"),
					'baie' => s("Ouverte"),
					'aucune' => s("Aucune"),
				];
				break;

			case 'chimney' :
				$d->values = [
					0 => s("Sans"),
					1 => s("Avec"),
				];
				break;

		}

		return $d;

	}

}
?>
