<?php
namespace user;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {


			'User::demo.write' => s("Vous ne pouvez pas faire de telles bêtises sur le site de démo !"),
			'User::connectionBanned' => fn($eBan) => new BanUi()->getConnectionBanned($eBan),
			'User::signUpBanned' => fn($eBan) => new BanUi()->getSignUpBanned($eBan),
			'User::tos.accepted' => s("Veuillez accepter les conditions générales d'utilisation."),
			'User::connectionInvalid' => fn(?string $login = NULL, bool $forgotten = FALSE) => $forgotten ?
				s("Impossible de se connecter, car vous avez sans doute saisi un mauvais identifiant ou mot de passe. <link>Vous avez oublié votre mot de passe ?</link>", ['link' => '<a href="'.UserUi::urlForgottenPassword($login).'">']) :
				s("Impossible de se connecter, car vous avez sans doute saisi un mauvais identifiant ou mot de passe."),
			'User::connectionThrottled' => fn(?string $login = NULL) => s("Trop de tentatives de connexion ont échoué. Veuillez réessayer dans un quart d'heure, ou <link>choisir un nouveau mot de passe</link>.", ['link' => '<a href="'.UserUi::urlForgottenPassword($login).'">']),
			'User::connectionChallenge' => s("Ce compte se connecte avec le code envoyé par e-mail après le mot de passe."),
			'User::closePresence' => fn(\Collection $cStructure) => new DropUi()->getClosePresence($cStructure),
			'User::tabToken' => s("Le choix du compte a expiré ou a déjà servi. Choisissez-le de nouveau."),
			'User::browserToken' => s("Cette adresse a expiré ou a déjà servi. Demandez-en une nouvelle."),
			'User::forgottenThrottled' => s("Trop de demandes ont été faites pour cette adresse e-mail. Veuillez réessayer dans une heure."),
			'User::signUpThrottled' => s("Trop de comptes ont été créés récemment depuis votre connexion. Veuillez réessayer dans une heure."),
			'User::phone.empty' => s("Veuillez indiquer un numéro de téléphone"),
			'User::firstName.empty' => s("Veuillez indiquer votre prénom"),
			'User::firstName.required', 'User::firstName.check' => s("Veuillez indiquer votre prénom"),
			'User::lastName.check' => s("Veuillez indiquer votre nom"),
			'User::country.check' => s("Veuillez indiquer votre pays"),
			'User::gender.check' => s("Votre sexe n'existe pas"),
			'User::legalName.check', 'User::legalName.required' => s("Veuillez indiquer le nom de la société"),
			'User::hashConnectedWrongAccount'=> s("Veuillez vous déconnecter de ce compte pour pouvoir confirmer votre adresse e-mail."),
			'User::email.check' => s("L'adresse e-mail n'est pas correcte"),
			'User::email.auth' => s("Vous ne pouvez pas changer d'adresse e-mail car vous n'êtes pas en authentification standard."),
			'User::email.empty' => s("Veuillez indiquer votre adresse e-mail"),
			'User::email.duplicate' => s("Vous ne pouvez pas utiliser cette adresse e-mail, car elle a déjà été utilisée pour s'inscrire sur {siteName} (<link><u>me connecter maintenant ?</u></link>).", ['link' => '<a href="/user/log:form">']),
			'User::invoiceAddress.empty' => s("Vous devez saisir au moins la première ligne de l'adresse, un code postal et une ville pour que votre adresse soit complète !"),
			'User::deliveryAddress.empty' => s("Vous devez saisir au moins la première ligne de l'adresse, un code postal et une ville pour que votre adresse soit complète !"),
			'User::deliveryAddressMandatory.check' => s("Merci de saisir votre adresse !"),
			'User::invalidHash' => s("Désolé, ce code de confirmation n'est pas valide."),
			'User::internal' => s("Une erreur interne est survenue."),
			'User::role.check' => s("Vous devez conserver un rôle lié à la production étant donné que vous êtes intégré à l'équipe d'une ferme ou d'un groupe"),

			'Challenge::code.invalid' => fn($remaining) => p("Ce n'est pas le bon code. Il vous reste {value} essai.", "Ce n'est pas le bon code. Il vous reste {value} essais.", $remaining),
			'Challenge::exceeded' => s("Trop de codes refusés. Recommencez avec votre mot de passe."),
			'Challenge::expired' => s("Ce code n'est plus valable."),
			'Challenge::mailThrottled' => s("Trop de codes ont été envoyés à cette adresse. Veuillez réessayer dans une heure."),
			'Challenge::linkElsewhere' => s("Ce lien ne s'ouvre que dans le navigateur où vous avez saisi votre mot de passe."),
			'Challenge::linkExpired' => s("Ce lien n'est plus valable."),

			'Ban::type.empty' => s("Veuillez choisir l'étendue du bannissement."),
			'Ban::reason.empty' => s("Veuillez indiquer le motif du bannissement."),

			'UserAuth::password.match' => s("Vous avez entré deux mots de passe différents"),
			'UserAuth::password.check', 'UserAuth::password.length' => p("Votre mot de passe doit contenir au minimum {value} caractère", "Votre mot de passe doit contenir au minimum {value} caractères", UserSetting::PASSWORD_SIZE_MIN),
			'UserAuth::passwordOld.invalid' => s("Votre mot de passe actuel n'est pas correct"),

			'UserAvatar::composition.check' => s("Cet avatar ne se compose pas avec le catalogue de l'éditeur."),
			'UserAvatar::composition.tint' => s("Une couleur de cet avatar n'est pas reconnue. Choisissez-en une autre."),
			'UserAvatar::composition.excluded' => s("Certaines pièces de cet avatar ne se portent pas ensemble. Changez l'une d'elles."),
			'UserAvatar::image' => s("L'image de votre avatar n'a pas pu être enregistrée. Réessayez."),

			'UserAvatarUpload::file.check' => s("Choisissez le fichier .glb de votre modèle."),
			// Le nombre s'écrit en lettres : UserAvatarUploadAi::testThrottle() casse si ThrottleAiLib::LIMIT_AVATAR_UPLOAD change
			'UserAvatarUpload::file.throttled' => s("Vous ne pouvez pas envoyer plus de cinquante fichiers en 24 heures, en comptant chaque « Valider » et chaque mise en place. Ce fichier n'a pas été envoyé."),
			'UserAvatarUpload::file.unavailable' => s("L'import est indisponible pour le moment, réessayez plus tard."),
			'UserAvatarUpload::file.conformity' => fn(string $refusal, array $values) => AlertUi::getAvatarUploadRefusal($refusal, $values),
			'UserAvatarUpload::commitment.check' => s("Cochez l'engagement pour envoyer votre modèle."),
			'UserAvatarUpload::reason.check', 'UserAvatarUpload::reason.empty' => s("Choisissez le motif du retrait."),
			'UserAvatarUpload::reasonText.empty' => s("Écrivez la précision que l'auteur lira dans l'e-mail."),
			'UserAvatarUpload::reasonText.check' => s("La précision tient en {value} caractères au plus.", UserAvatarUpload::model()->getPropertyRange('reasonText')[1]),
			'UserAvatarUpload::stale' => s("Ce modèle a été remplacé ou retiré entre-temps."),
			'UserAvatarUpload::keepAllNone' => s("Aucun des modèles affichés n'est encore à examiner. Rechargez la page pour revoir la file."),


			default => NULL

		};


	}

	/**
	 * Le refus d'un modèle 3D téléversé, par son code (`refusals` de `base/scene/upload/avatar.json`) : chaque code a sa
	 * branche, et un code sans branche lève une erreur plutôt qu'un message vide (`UserAvatarUploadAi.t.php`).
	 */
	public static function getAvatarUploadRefusal(string $refusal, array $values): string {

		if($refusal === 'weight') {
			// Le poids s'arrondit au dixième supérieur : arrondi au plus près, un fichier à peine trop lourd pèserait le plafond
			return self::getAvatarUploadWeight(\util\TextUi::size(\util\TextUi::number(ceil($values['size'] / 104857.6) / 10, NULL)), \util\TextUi::size($values['max']));
		}

		$reason = match($refusal) {
			'format' => s("Il ne se lit pas comme un fichier .glb. Exportez votre modèle au format .glb."),
			'external' => s("Il demande des fichiers ou des extensions qu'il ne contient pas. Exportez-le en un seul fichier .glb, sans extension."),
			'texture' => s("Une texture est posée sur une pièce. Chaque pièce prend une seule couleur."),
			'rig' => s("Il porte une armature, une animation ou des formes animées. Exportez des pièces fixes, chacune nommée d'après l'os qui la porte."),
			'nodes' => s("Il a trop de pièces ou trop d'objets imbriqués."),
			'bone' => s("La pièce « {name} » n'est rattachée à aucun os du corps. Nommez-la d'après l'os qui la porte, parmi {bones}.", ['name' => encode($values['name']), 'bones' => $values['bones']]),
			'missing' => s("Aucune pièce n'est rattachée à l'os « {value} ». Chaque os du corps porte au moins une pièce.", $values['bone']),
			'triangles' => s("Il compte {count} triangles, pour {max} au plus.", ['count' => \util\TextUi::number($values['count']), 'max' => \util\TextUi::number($values['max'])]),
			'bounds' => s("Il est plus grand qu'une personne. Vérifiez son échelle : un mètre de votre logiciel vaut un mètre ici."),
			'ground' => s("Ses pieds ne sont pas posés au sol. Placez le bas des pieds à la hauteur 0."),
			'skull' => s("Le dessus de la tête est ouvert, on voit au travers vu d'en haut. Gardez la calotte du crâne sous les cheveux, ou fermez le dessus de la tête."),
		};

		return '<b>'.s("Ce fichier ne peut pas devenir votre avatar.").'</b><br/>'.$reason.'<br/>'.s("Donnez ce message à Claude, il corrigera le modèle.");

	}

	/**
	 * Le refus du poids, que le navigateur montre aussi avant tout envoi, le poids du fichier à la place de `{size}`
	 * (`UserAvatarUi`).
	 */
	public static function getAvatarUploadWeight(string $size, string $max): string {
		return s("Ce fichier pèse {size}. Un modèle pèse {max} au plus.", ['size' => $size, 'max' => $max]);
	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Ban::created' => s("Le bannissement a bien été enregistré."),
			'Ban::ended' => s("Le bannissement a pris fin."),
			'Ban::updated' => s("La fin du bannissement a bien été changée."),

			'Challenge::sent' => s("Un nouveau code vient de vous être envoyé."),

			'Tos::accepted' => s("Merci, vous avez accepté les nouvelles conditions générales de {siteName}."),

			'UserAvatar::saved' => s("Votre avatar a bien été enregistré."),
			'UserAvatar::deleted' => s("Votre avatar a bien été supprimé."),
			'UserAvatarUpload::created' => s("Votre avatar est en place."),
			'UserAvatarUpload::cleared' => s("Le modèle 3D est supprimé."),
			'UserAvatarUpload::keptAll' => s("Les modèles affichés sont gardés."),
			'UserAvatarUpload::withdrawn' => s("Le modèle est retiré."),

			'User::welcomeCreate'=> s("Bienvenue sur {siteName}, votre compte a bien été créé !"),
			'User::welcome' => s("Vous êtes maintenant connecté·e sur {siteName} !"),
			'User::bye' => s("Vous êtes maintenant déconnecté·e de {siteName}."),
			'User::adminUpdated' => s("L'utilisateur a bien bien été mis à jour."),
			'User::updated' => s("Vos informations personnelles ont bien été mises à jour."),
			'User::emailUpdated' => s("Votre adresse e-mail a bien été mise à jour."),
			'User::passwordUpdated' => s("Votre mot de passe a bien été mis à jour."),
			'User::passwordReset' => s("Votre mot de passe a bien été réinitialisé, vous pouvez maintenant vous connecter avec votre adresse e-mail et ce nouveau mot de passe !"),
			'User::forgottenPasswordSend' => s("Un e-mail avec un lien vient de vous être envoyé."),
			'User::forgottenPasswordRequested' => s("Si un compte est inscrit avec cette adresse, un e-mail avec un lien vient de lui être envoyé."),
			'User::invalidLinkForgot'=> s("Le lien pour réinitialiser le mot de passe n'est plus valide."),
			default => NULL

		};


	}

}
?>
