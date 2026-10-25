<?php
namespace learning;

/**
 * Ce que les formations écoutent du package user.
 *
 * Le nom du fichier n'est pas suffixé : le mécanisme d'observateurs déduit du basename le package
 * qui émet l'événement et le nom de la classe (`framework/dev/lib/Package.l.php`), et un
 * `UserAi.o.php` ne serait jamais appelé (`ObserverNameSentinel`).
 */
class UserObserverLib {

	/**
	 * À chaque connexion, y compris celle qui suit la création du compte (`SignUpLib` connecte par
	 * `ConnectionLib::logInUser()`, une connexion ordinaire) : les inscriptions faites sans compte à
	 * cette adresse sur la page publique d'un réseau prennent le compte (lot L10c, arbitrage 1 a ; retirer
	 * cet appel suffit à le défaire).
	 */
	public static function logIn(\user\User $eUser) {

		if(LearningSetting::isActiveAnywhere() === FALSE) {
			return;
		}

		DateEnrollmentLib::attachByUser($eUser);

	}

}
?>
