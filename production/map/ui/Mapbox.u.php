<?php
namespace map;

class MapboxUi {

	public static function load() {

		\Asset::js('map', 'cartography.js');
		\Asset::js('map', 'drawing.js');
		\Asset::css('map', 'cartography.css');

		\Asset::lib('map', 'turf-7.2.0.min.js');

		\Asset::jsUrl('https://api.mapbox.com/mapbox-gl-js/v3.31.0/mapbox-gl.js');
		\Asset::js('map', 'mapbox.js');

		\Asset::cssUrl('https://api.mapbox.com/mapbox-gl-js/v3.31.0/mapbox-gl.css');
		\Asset::css('map', 'mapbox.css');

		\Asset::jsContent('<script>
			mapboxToken = "'.MapSetting::$mapboxToken.'";
			mapboxgl.accessToken = "'.MapSetting::$mapboxToken.'";
		</script>');

	}

	/**
	 * La seule classe de la carte, pour un panneau dont l'enregistrement répond à l'onglet Plan par `MapCartography.queryZone()` :
	 * sans carte pour relire le jardin, elle recharge la page, et un panneau rechargé en page entière doit donc la trouver.
	 */
	public static function loadCartography(): void {
		\Asset::js('map', 'cartography.js');
	}

	/**
	 * Les messages que Mapbox affiche lui-même, en anglais par défaut : ceux de cooperativeGestures et les
	 * infobulles du zoom, à passer en option `locale` à la carte.
	 */
	public static function getLocale(): array {

		return [
			'ScrollZoomBlocker.CtrlMessage' => s("Utilisez Ctrl + molette pour zoomer sur la carte"),
			'ScrollZoomBlocker.CmdMessage' => s("Utilisez ⌘ + molette pour zoomer sur la carte"),
			'TouchPanBlocker.Message' => s("Utilisez deux doigts pour déplacer la carte"),
			'NavigationControl.ZoomIn' => s("Zoomer"),
			'NavigationControl.ZoomOut' => s("Dézoomer")
		];

	}

	/**
	 * Une vignette cartographique en une image, sans WebGL ni jeton dans le navigateur.
	 *
	 * L'API statique rend un PNG que le navigateur traite comme n'importe quelle image : pas de
	 * `mapbox-gl.js` à charger, pas de canvas, et la vignette reste visible dans un PDF ou un
	 * navigateur sans WebGL, ce que `load()` ne permet pas.
	 *
	 * ⚠️ **Le jeton part dans l'URL, donc dans le HTML.** Sur les écrans authentifiés, `load()` le
	 * pose déjà en clair ; ici la page peut être lue sans compte, ce qui l'expose à l'indexation.
	 * Le jeton est un `pk.` public, prévu pour cet usage et bornable par domaine côté Mapbox,
	 * mais l'écart avec l'existant est réel et se décide, il ne se déduit pas.
	 *
	 * ⚠️ Ni `logo=false` ni `attribution=false` : les conditions de Mapbox les autorisent à
	 * condition de porter l'attribution ailleurs sur la page. Les laisser dans l'image est ce qui
	 * rend la vignette utilisable sans rien devoir ajouter autour d'elle.
	 *
	 * @param array $lngLat Dans l'ordre de PHP, (longitude, latitude) — l'inverse de ce que rend
	 *                      un `ST_X` sur la même colonne.
	 * @param bool $retina Densité double. Elle triple le poids — 456 Ko contre 152 en 1 200 × 300,
	 *                     mesuré le 2026-09-05 — et ne se justifie que là où l'on regarde l'image
	 *                     pour elle-même, pas là où elle sert de fond.
	 */
	public static function getStaticImageUrl(array $lngLat, int $zoom, int $width, int $height, string $color, bool $retina = TRUE): string {

		[$longitude, $latitude] = $lngLat;

		$marker = 'pin-s+'.ltrim($color, '#').'('.$longitude.','.$latitude.')';
		$center = $longitude.','.$latitude.','.$zoom;

		return 'https://api.mapbox.com/styles/v1/mapbox/outdoors-v12/static/'.$marker.'/'.$center.'/'.$width.'x'.$height.($retina ? '@2x' : '')
			.'?access_token='.MapSetting::$mapboxToken;

	}

	/**
	 * L'aperçu du fond satellite, dans la vignette qui bascule le fond d'une carte : l'emprise des
	 * parcelles, ou le lieu de production tant qu'aucune n'est tracée.
	 *
	 * Sans logo ni attribution, contrairement à getStaticImageUrl() : l'image ne se montre que
	 * posée sur une carte Mapbox, qui porte le logo et la mention, Maxar compris (option `attribution` de
	 * MapCartography), ou sur la page du design system, qui l'écrit sous la vignette. À sa taille, l'un et
	 * l'autre couvriraient la photo.
	 *
	 * @param array|null $bounds [ouest, sud, est, nord], cf. MapLib::getBounds()
	 */
	public static function getStaticSatelliteUrl(?array $bounds, array $center, int $width, int $height): string {

		$position = ($bounds === NULL) ?
			$center[0].','.$center[1].',15' :
			'['.implode(',', $bounds).']';

		return 'https://api.mapbox.com/styles/v1/mapbox/satellite-v9/static/'.$position.'/'.$width.'x'.$height.'@2x'
			.'?'.($bounds === NULL ? '' : 'padding=8&').'attribution=false&logo=false&access_token='.MapSetting::$mapboxToken;

	}

}
?>
