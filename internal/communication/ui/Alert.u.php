<?php
namespace communication;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'DiscordAccess::failed' => s("La connexion à Discord n'a pas abouti. Relancez-la depuis la page d'abonnement de votre structure."),
			'DiscordAccess::invalidLink' => s("Ce lien d'invitation au Discord est réservé à l'équipe d'une structure qui a l'offre Communauté en cours."),

			'Note::title.check' => s("Donnez un titre à la notification."),
			'Note::content.check' => s("Écrivez le texte de la notification."),
			'Note::buttonLabel.check' => s("Le libellé du bouton tient en 30 caractères."),
			'Note::buttonPath.check' => s("L'adresse de la page du bouton est trop longue."),
			'Note::buttonPath.path' => s("La page du bouton est une adresse du site qui commence par « / », comme /x/banque/operations."),
			'Note::buttonLabel.missing' => s("Donnez un libellé au bouton, ou videz sa page."),
			'Note::buttonPath.missing' => s("Indiquez la page où mène le bouton, ou videz son libellé."),
			'Note::structureTypes.check' => s("Cochez au moins un type de structure."),
			'Note::features.check' => s("Cette fonctionnalité ne peut pas servir à choisir les destinataires."),
			'Note::recipients.empty' => s("Aucune structure ne correspond à ces critères."),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'DiscordAccess::connected' => s("Vous avez accès aux salons de la Communauté sur le Discord de Ouvretaferme."),
			'Note::created' => s("La notification est partie."),

			default => null

		};

	}

}
?>
