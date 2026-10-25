<?php
namespace scene;

class SceneUi {

	public function __construct() {

		\Asset::css('scene', 'scene.css');
		\Asset::js('scene', 'loader.js');

	}

	/**
	 * Le conteneur d'une scène, que son script passe à `SceneLoader.load()` et `SceneLoader.loadCrop()`.
	 *
	 * Le répertoire du package et ce que rend `BakeLib::getCatalogAndVersions()` descendent par des
	 * attributs et non par un script en ligne : en minifié, un `Asset::jsContent()` s'exécute avant
	 * le lot qui définit `SceneLoader`.
	 */
	public function getContainer(string $id, array $bake): string {
		return '<div id="'.encode($id).'" data-scene-directory="'.encode(\Asset::directory('scene')).'" data-scene-bake="'.encode(json_encode($bake, JSON_UNESCAPED_UNICODE)).'"></div>';
	}

	/**
	 * L'entrée et la sortie du plein écran, cachées au rendu : `SceneFullscreen` (fullscreen.js) montre l'entrée quand le
	 * navigateur le permet. La scène qui s'en sert charge elle-même `fullscreen.js` et `swap.js`, qui passe le focus d'un
	 * bouton à l'autre : `__construct()` sert aussi les scènes qui n'ont pas de plein écran.
	 */
	public function getFullscreen(): string {

		$h = '<button type="button" class="btn btn-only-icon btn-form hide" data-scene-fullscreen="true">'.\Asset::icon('fullscreen').'</button>';
		$h .= '<button type="button" class="btn btn-only-icon btn-form hide" data-scene-fullscreen-exit="true">'.\Asset::icon('fullscreen-exit').'</button>';

		return $h;

	}

	/**
	 * Lecture et Pause, qui prennent la place l'une de l'autre, Pause cachée au rendu. Des boutons, pour que la tabulation
	 * les atteigne. `$labelled` ajoute leur libellé, caché sous 768 px. La scène anime la paire elle-même, et passe le
	 * focus de l'un à l'autre par `SceneSwap` (swap.js), qu'elle charge si elle s'en sert.
	 */
	public function getPlay(bool $labelled): string {

		$class = $labelled ? 'btn btn-form' : 'btn btn-only-icon btn-form';
		$label = fn(string $text) => $labelled ? '<span class="hide-sm-down">'.$text.'</span>' : '';

		$h = '<button type="button" class="'.$class.'" data-scene-play="true">'.\Asset::icon('play-fill').$label(s("Lecture")).'</button>';
		$h .= '<button type="button" class="'.$class.' hide" data-scene-pause="true">'.\Asset::icon('pause-fill').$label(s("Pause")).'</button>';

		return $h;

	}

	/**
	 * Le bouton de la vitesse de lecture et sa liste, que `SceneSpeed` (speed.js) anime. `$speeds` va du facteur que la scène
	 * multiplie à son pas vers son libellé, `$selected` est le facteur retenu au rendu. La scène qui s'en sert charge
	 * `speed.js` et `dropdown.js` elle-même. Une scène dont les libellés disent déjà la vitesse (« ×1 ») se passe de l'icône,
	 * `$icon` à NULL.
	 */
	public function getSpeed(array $speeds, string $selected, ?string $icon = 'speedometer2'): string {

		// Les libellés se superposent dans le bouton (scene.css), qui prend ainsi la largeur du plus long dans toute langue
		$h = '<a class="btn btn-form dropdown-toggle" data-dropdown="top-end" data-scene-speed="true">';
			if($icon !== NULL) {
				$h .= \Asset::icon($icon);
			}
			$h .= '<span class="scene-speed-label">';
				// PHP range la clé '1' en entier : le facteur se compare en chaîne, comme `$selected`
				foreach($speeds as $factor => $label) {
					$h .= '<span'.((string)$factor === $selected ? ' class="selected"' : '').' data-scene-speed-label="'.$factor.'">'.$label.'</span>';
				}
			$h .= '</span>';
		$h .= '</a>';

		$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light scene-speed-list">';
			foreach($speeds as $factor => $label) {
				$h .= '<a class="dropdown-item'.((string)$factor === $selected ? ' selected' : '').'" data-scene-speed-item="'.$factor.'">'.$label.'</a>';
			}
		$h .= '</div>';

		return $h;

	}

}
