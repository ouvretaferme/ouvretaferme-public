<?php
namespace main;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'csvSize' => s("Le fichier ne peut pas excéder 1 Mo, merci de réduire la taille de votre fichier."),
			'csvSource' => s("Le fichier que vous avez envoyé n'est pas reconnu, vérifiez qu'il respecte bien le format demandé."),

			'Pdf::fileLocked' => s("Ce fichier est déjà en cours de génération, réactualisez la page d'ici quelques instants !"),

			'DiscordAccess::failed' => s("La connexion avec Discord a échoué, veuillez réessayer."),
			'DiscordAccess::invalidLink' => s("Ce lien n'est plus valide."),
			'WebinaireSubscription::email.duplicate' => s("Vous avez déjà inscrit cette adresse e-mail à une session"),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'DiscordAccess::connected' => s("Vous avez bien rejoint le serveur Discord !"),

			'WebinaireSubscription::created' => s("Vous êtes bien inscrit·e au webinaire !"),
			'WebinaireSubscription::deleted' => s("Votre inscription au webinaire a bien été annulée."),

			default => NULL

		};


	}

}
?>
