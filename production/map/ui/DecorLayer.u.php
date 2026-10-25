<?php
namespace map;

/**
 * La carte des calques, posée sur la scène sous le bouton « Calques », qui sert à la fois à montrer
 * le décor et à choisir où l'on pose : une ligne par calque, que `sceneLayer.js` (`MapSceneLayer`)
 * remplit depuis `layers` du format de la scène, et à sa place le formulaire d'un geste (créer,
 * renommer, supprimer).
 */
class DecorLayerUi {

	public function __construct() {

		\Asset::css('util', 'form.css');

	}

	public function getButton(): string {

		return '<button type="button" class="btn btn-form" data-scene-layers="true">'.\Asset::icon('layers').'<span class="hide-sm-down">'.s("Calques").'</span></button>';

	}

	/**
	 * La carte flotte sur la scène (`plan-mapview-zones`, `plan-item`), et chaque geste y prend la place de la
	 * liste sous le même titre de colonne. Le calque « Icônes des séries » est sur toutes les scènes, à côté des
	 * calques du décor, dont les gestes ne sont qu'à qui gère la structure.
	 */
	public function getCard(\structure\Structure $eStructure, \user\User $eUser, bool $relief): string {

		$manage = $eStructure->canManage();

		$types = DecorLayerUi::p('type')->values;

		if(MapSetting::isIrrigationEnabled($eStructure) === FALSE) {
			unset($types[DecorLayer::IRRIGATION]);
		}

		$create = ($manage and $types !== []);

		$eDecorLayer = new DecorLayer(['type' => array_key_first($types)]);

		$texts = [
			'base' => s("Décor"),
		];

		$h = '<div class="util-card plan-mapview-zones map-scene-layers hide" data-scene-layers-card="true" data-structure="'.$eStructure['id'].'" data-user="'.$eUser['id'].'" data-texts="'.encode(json_encode($texts, JSON_UNESCAPED_UNICODE)).'">';

			$h .= '<div data-scene-layers-list="true">';
				$h .= '<div class="plan-column-title plan-column-title-action">';
					$h .= s("Calques");
					if($create) {
						$h .= '<a class="btn btn-sm btn-outline-primary" data-scene-layers-new="true">'.\Asset::icon('plus-circle').' '.s("Ajouter").'</a>';
					}
				$h .= '</div>';
				// `MapSceneLayer` range les lignes des séries et du relief sous celle du décor ; sans décor, elles sont seules
				$h .= '<div data-scene-layers-rows="true">';
					$h .= '<div class="plan-item" data-scene-series="true">';
						$h .= '<div class="plan-item-title">';
							$h .= '<span class="plan-item-text">';
								$h .= '<span class="plan-item-name">'.s("Icônes des séries").'</span>';
							$h .= '</span>';
							$h .= \util\TextUi::switch(['class' => 'field-switch-sm']);
							// Les séries ne se renomment ni ne se suppriment : la place de la roue aligne leur interrupteur sur les autres
							if($manage) {
								$h .= '<span class="btn btn-sm btn-color-primary map-scene-layers-gear-away">'.\Asset::icon('gear-fill').'</span>';
							}
						$h .= '</div>';
					$h .= '</div>';
					// Sous quelques mètres de dénivelé, le relief ne se verrait pas : la scène ne le reçoit pas, et rien ne s'allume
					if($relief) {
						$h .= '<div class="plan-item" data-scene-relief="true">';
							$h .= '<div class="plan-item-title">';
								$h .= '<span class="plan-item-text">';
									$h .= '<span class="plan-item-name">'.s("Relief").'</span>';
								$h .= '</span>';
								$h .= \util\TextUi::switch(['class' => 'field-switch-sm']);
								if($manage) {
									$h .= '<span class="btn btn-sm btn-color-primary map-scene-layers-gear-away">'.\Asset::icon('gear-fill').'</span>';
								}
							$h .= '</div>';
						$h .= '</div>';
					}
				$h .= '</div>';
			$h .= '</div>';

			// La ligne est un div et non un lien comme dans la vue Carte : l'interrupteur et la roue qu'elle porte sont des liens. Elle n'est
			// pas non plus un .btn, dont la scène fait un bouton de son prototype
			$h .= '<template data-scene-layers-row="true">';
				$h .= '<div class="plan-item" data-do="pick">';
					$h .= '<div class="plan-item-title">';
						$h .= '<span class="plan-item-text">';
							$h .= '<span class="plan-item-name" data-scene-layers-name="true"></span>';
						$h .= '</span>';
						$h .= \util\TextUi::switch(['class' => 'field-switch-sm', 'data-do' => 'switch']);
						if($manage) {
							$h .= '<a class="btn btn-sm btn-color-primary dropdown-toggle" data-dropdown="bottom-end" data-scene-layers-gear="true">'.\Asset::icon('gear-fill').'</a>';
							$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light" data-dropdown-background="no">';
								$h .= '<a class="dropdown-item" data-do="rename">'.s("Renommer").'</a>';
								$h .= '<div class="dropdown-divider"></div>';
								$h .= '<a class="dropdown-item" data-do="delete">'.s("Supprimer").'</a>';
							$h .= '</div>';
						}
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</template>';

			// Sans le droit de gérer la structure, la carte ne fait que montrer et masquer
			if($manage) {

				$form = new \util\FormUi(['columns' => 1]);

				if($create) {

					$h .= $form->open(NULL, ['class' => 'hide', 'data-scene-layers-form' => 'create', 'autocomplete' => 'off']);
						$h .= '<div class="plan-column-title">'.s("Nouveau calque").'</div>';
						$h .= '<div class="map-scene-layers-form">';
							$h .= $form->hidden('structure', $eStructure);
							$h .= $form->dynamicGroups($eDecorLayer, ['type', 'name'], ['type' => function(\PropertyDescriber $d) use($types) {
								$d->values = $types;
							}]);
							$h .= $this->getActions(s("Créer"));
						$h .= '</div>';
					$h .= $form->close();

				}

				$h .= $form->open(NULL, ['class' => 'hide', 'data-scene-layers-form' => 'rename', 'autocomplete' => 'off']);
					$h .= '<div class="plan-column-title">'.s("Renommer le calque").'</div>';
					$h .= '<div class="map-scene-layers-form">';
						$h .= $form->hidden('id', '');
						$h .= $form->dynamicGroup($eDecorLayer, 'name');
						$h .= $this->getActions(s("Enregistrer"));
					$h .= '</div>';
				$h .= $form->close();

				// Le corps du panneau de suppression du produit (`DesignUi::getDeletePanel()`), dans la carte : le nom du calque et
				// ce qu'il emporte se remplissent à l'ouverture, le compte venant du serveur
				$h .= $form->open(NULL, ['class' => 'hide', 'data-scene-layers-form' => 'delete']);
					$h .= '<div class="plan-column-title">'.s("Supprimer le calque").'</div>';
					$h .= '<div class="map-scene-layers-form">';
						$h .= $form->hidden('id', '');
						$h .= \main\DesignUi::getWeigh(
							'danger',
							'trash',
							'<span data-scene-layers-delete-name="true"></span>',
							NULL,
							[['icon' => 'x-lg', 'text' => '<span data-scene-layers-delete-count="true"></span>']],
							[s("Vos autres calques et leurs éléments restent en place.")],
							FALSE
						);
						$h .= $this->getActions(s("Supprimer"), 'btn-danger');
					$h .= '</div>';
				$h .= $form->close();

			}

		$h .= '</div>';

		return $h;

	}

	public function getDeleteDecor(int $decor): string {

		if($decor === 0) {
			return s("Il ne contient aucun élément.");
		} else if($decor === 1) {
			return s("Son élément sera supprimé avec lui.");
		} else {
			return s("Ses {value} éléments seront supprimés avec lui.", $decor);
		}

	}

	protected function getActions(string $submit, string $class = 'btn-primary'): string {

		$h = '<div class="map-scene-layers-actions">';
			$h .= '<a class="btn btn-outline-primary" data-do="cancel">'.s("Annuler").'</a>';
			$h .= '<button type="submit" class="btn '.$class.'">'.$submit.'</button>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DecorLayer::model()->describer($property, [
			'name' => s("Nom"),
			'type' => s("Type"),
		]);

		switch($property) {

			case 'type' :
				$d->field = 'radio';
				$d->attributes = ['required' => TRUE];
				$d->values = [
					DecorLayer::IRRIGATION => s("Irrigation"),
				];
				break;

		}

		return $d;

	}

}
?>
