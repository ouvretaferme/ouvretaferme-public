<?php
namespace main;

class PlaceUi {

	public function __construct() {
		\Asset::css('main', 'place.css');
		\Asset::js('main', 'place.js');
	}

	public static function querySiret(\PropertyDescriber $d, string $prefix): void {

		\Asset::js('main', 'place.js');

		$d->placeholder = s("14 chiffres");

		$h = '<div class="util-block siret-found hide mt-1">';
			$h .= '<h4>'.s("Nous avons trouvé ce SIRET dans la base de données de l'administration :").'</h4>';
			$h .= '<dl class="util-presentation util-presentation-1">';
				$h .= '<dt>'.s("Raison sociale").'</dt>';
				$h .= '<dd class="siret-name"></dd>';
				$h .= '<dt>'.s("Adresse de l'établissement").'</dt>';
				$h .= '<dd>';
					$h .= '<div class="siret-street1"></div>';
					$h .= '<div class="siret-street2"></div>';
					$h .= '<div>';
						$h .= '<span class="siret-postcode"></span> ';
						$h .= '<span class="siret-city"></span>';
						$h .= '<span class="siret-siren hide"></span>';
					$h .= '</div>';
				$h .= '</dd>';
			$h .= '</dl>';
			$h .= '<a '.attr('onclick', 'MainPlace.fillCompany("siret", this, "'.$prefix.'")').' class="btn btn-secondary btn-sm mt-1">'.s("Utiliser ces informations").'</a>';
		$h .= '</div>';
		$h .= '<div class="siret-unknown hide mt-1">';
			$h .= '<div class="util-warning">'.s("Nous n'avons pas trouvé ce SIRET dans la base de données de l'administration. Nous vous incitons à vérifier votre saisie mais vous pouvez toujours l'utiliser si vous pensez qu'il est correct.").'</div>';
		$h .= '</div>';

		$d->after = $h;
		$d->labelAfter = \util\FormUi::info(s("Commencez par saisir le numéro de SIRET pour que {siteName} trouve automatiquement les autres informations."));
		$d->attributes['oninput'] = fn(\util\FormUi $form, \Element $e) => 'MainPlace.querySiret(this);';

	}

	public static function querySiren(\PropertyDescriber $d, string $prefix): void {

		\Asset::js('main', 'place.js');

		$d->placeholder = s("9 chiffres");

		$h = '<div class="util-block siren-found hide mt-1">';
			$h .= '<h4>'.s("Nous avons trouvé ce SIREN dans la base de données de l'administration :").'</h4>';
			$h .= '<dl class="util-presentation util-presentation-1">';
				$h .= '<dt>'.s("Raison sociale").'</dt>';
				$h .= '<dd class="siren-name"></dd>';
				$h .= '<dt>'.s("Adresse du siège social").'</dt>';
				$h .= '<dd>';
					$h .= '<div class="siren-street1"></div>';
					$h .= '<div class="siren-street2"></div>';
					$h .= '<div>';
						$h .= '<span class="siren-postcode"></span> ';
						$h .= '<span class="siren-city"></span>';
						$h .= '<span class="siren-siret hide"></span>';
					$h .= '</div>';
				$h .= '</dd>';
			$h .= '</dl>';
			$h .= '<a '.attr('onclick', 'MainPlace.fillCompany("siren", this, "'.$prefix.'")').' class="btn btn-secondary btn-sm mt-1">'.s("Utiliser ces informations").'</a>';
		$h .= '</div>';
		$h .= '<div class="siren-unknown hide mt-1">';
			$h .= '<div class="util-warning">'.s("Nous n'avons pas trouvé ce SIREN dans la base de données de l'administration. Nous vous incitons à vérifier votre saisie mais vous pouvez toujours l'utiliser si vous pensez qu'il est correct.").'</div>';
		$h .= '</div>';

		$d->after = $h;
		$d->labelAfter = \util\FormUi::info(s("Commencez par saisir le numéro de SIREN pour que {siteName} trouve automatiquement les autres informations."));
		$d->attributes['oninput'] = fn(\util\FormUi $form, \Element $e) => 'MainPlace.querySiren(this);';

	}

	public static function queryAddress(\PropertyDescriber $d, \structure\Structure $eStructure, string $prefix): void {

		\Asset::js('main', 'place.js');

		$d->field = 'autocomplete';
		$d->prepend = \Asset::icon('geo-fill');
		$d->placeholder = s("Adresse");
		$d->autocompleteTextual = TRUE;
		$d->autocompleteUrl = '/main/place:addressesByName';
		$d->autocompleteBody = ['structure' => $eStructure['id']];
		$d->autocompleteResults = fn(string $street) => [
			'value' => $street,
			'itemText' => $street
		];
		$d->attributes['data-place-address'] = $prefix;

	}

	public static function queryCity(\PropertyDescriber $d, \structure\Structure $eStructure, string $prefix): void {

		\Asset::js('main', 'place.js');

		$d->field = 'autocomplete';
		$d->prepend = \Asset::icon('geo-fill');
		$d->placeholder = s("Commune ou code postal");
		$d->autocompleteTextual = TRUE;
		$d->autocompleteUrl = '/main/place:citiesByName';
		$d->autocompleteBody = ['structure' => $eStructure['id']];
		$d->autocompleteResults = fn(string $city) => [
			'value' => $city,
			'itemText' => $city
		];
		$d->attributes['data-place-city'] = $prefix;

	}

	public function query(\PropertyDescriber $d, bool $draggable = FALSE) {

		$mapId = uniqid('place-map-');
		$lngLatId = uniqid('place-reference-');

		if($draggable) {
			$d->labelAfter = \util\FormUi::info(s("Tapez votre ville ou un code postal et déplacez le repère sur la carte pour préciser le lieu de production."));
		}

		$d->prepend = \Asset::icon('geo-fill');
		$d->field = 'autocomplete';
		$d->wrapper = 'place';

		$d->placeholder = s("Ville ou code postal...");
		$d->attributes = [
			'data-autocomplete-map' => $mapId,
			'data-autocomplete-lnglat' => $lngLatId
		];

		$d->autocompleteUrl = '/main/place:citiesByName';
		$d->autocompleteResults = function($place) {
			return [
				'itemText' => $place,
				'value' => $place
			];
		};

		$d->after = function(\util\FormUi $form, \Element $e) use($mapId, $lngLatId, $draggable) {

			$valueLngLat = $e['placeLngLat'] ?? NULL;

			// Doit être décrit en premier pour que ce soit le champ 'place' qui communique son nom au groupe
			$h = $form->hidden('placeLngLat', $valueLngLat ? json_encode($valueLngLat) : '', ['id' => $lngLatId]);

			\map\MapboxUi::load();

			$h .= '<div id="'.$mapId.'" class="place-map"'.($draggable ? ' data-place-draggable' : '').'></div>';

			if($valueLngLat) {

				$h .= '<script>';
					$h .= 'document.ready(() => setTimeout(() => {';
						$h .= 'MainPlace.updateMap("'.$mapId.'", ['.implode(',', $valueLngLat).']);';
					$h .= '}, 100));';
				$h .= '</script>';

			}

			return $h;

		};

	}

}
