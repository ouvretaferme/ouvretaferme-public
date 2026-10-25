<?php
namespace api;

class AlertUi {

	public static function getError(string $fqn, array $options): mixed {

		return match($fqn) {

			'ApiKey::scopes.empty' => s("Veuillez choisir au moins un accès"),
			'ApiKey::internal' => s("Une erreur est survenue"),

			'Client::name.check' => s("Veuillez indiquer le nom de l'application"),
			'Client::redirectUris.check', 'Client::redirectUris.empty' => s("Veuillez indiquer au moins une adresse de retour"),
			'Client::redirectUris.url' => s("Une adresse de retour commence par https://, ou par http://localhost pour le poste de développement, et ne porte pas de #"),
			'Client::cScope.check' => s("Un accès choisi n'existe pas"),
			'Client::cScope.empty' => s("Veuillez choisir au moins un accès"),

			'Api::missing_api_version', 'Api::internal_error', 'Api::missing_argument' => s("Une erreur interne est survenue"),
			'Api::invalid_api_version' => s("Cette version de l'API n'existe pas"),
			'Api::bad_request' => s("La requête n'est pas valide"),
			'Api::missing_route' => s("La requête n'indique aucune route"),
			'Api::unknown_route' => s("La route {value} n'existe pas", $options['route']),
			'Api::method_not_allowed' => s("La route {route} ne s'appelle pas en {method}", $options),
			'Api::invalid_server_keys' => s("Les clés de l'API sont absentes ou invalides sur le serveur"),
			'Api::invalid_token' => s("Le jeton d'accès n'est pas valide"),
			'Api::scope_not_allowed' => s("Cette route demande l'accès {value}", $options['scope']),
			'Api::scope_not_open' => s("L'accès {value} n'est pas encore ouvert", $options['scope']),
			'Api::syntax_error' => s("Le corps de la requête n'est pas un JSON valide"),
			'Api::body_not_object' => s("Le corps de la requête doit être un objet JSON"),
			'Api::accept_create' => s("Cet élément ne peut pas être créé"),
			'Api::accept_update' => s("Cet élément ne peut pas être modifié"),
			'Api::accept_delete' => s("Cet élément ne peut pas être supprimé"),
			'Api::not_found' => s("Cet élément n'existe pas"),
			'Api::unexpected_data' => s("Une donnée envoyée n'est pas attendue"),
			'Api::properties_empty' => s("La liste des propriétés à modifier est vide"),
			'Api::property_unknown' => s("La propriété {value} n'existe pas", $options['property']),
			'Api::property_not_allowed' => s("La propriété {value} ne peut pas être modifiée seule", $options['property']),
			'Api::property_together' => s("Les propriétés {value} se modifient ensemble", $options['properties']),
			'Api::rate_limit_second' => s("Vous avez dépassé la limite de {value} appels par seconde", $options['limit']),
			'Api::rate_limit_minute' => s("Vous avez dépassé la limite de {value} appels par minute", $options['limit']),
			'Api::rate_limit_day' => s("Vous avez dépassé la limite de {value} appels par jour, remise à zéro à minuit", $options['limit']),
			'Api::rate_limit_authentication' => s("Trop d'appels ont été refusés à l'authentification depuis cette adresse, réessayez dans quelques minutes"),

			'Api::property.missing' => s("La propriété {value} manque dans le corps de la requête", $options['property']),
			'Api::property.html' => s("La propriété {value} attend du HTML, par exemple <p>Votre texte</p>", $options['property']),
			'Api::property.check' => s("La valeur envoyée pour la propriété {value} n'est pas acceptée", $options['property']),
			'Api::argument.check' => s("La valeur de l'argument {value} n'est pas valide", $options['argument']),
			'Api::argument.max' => s("L'argument {argument} accepte au plus {max} valeurs", $options),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'ApiKey::created' => s("La clé d'API a bien été créée avec les droits sélectionnés. Copiez-la immédiatement car elle ne sera pas affichée une prochaine fois."),
			'ApiKey::revoked' => s("La clé d'API sélectionnée a bien été révoquée"),
			'ApiKey::applicationRevoked' => s("L'application n'a plus accès à l'API"),

			'Client::created' => s("L'application est créée, transmettez son identifiant au développeur"),
			'Client::disabled' => s("L'application est désactivée et tous ses accès sont révoqués"),
			'Client::enabled' => s("L'application est réactivée, les structures devront de nouveau l'autoriser"),

			default => null

		};


	}

}
?>
