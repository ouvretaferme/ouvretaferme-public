<?php
namespace main;

/**
 * L'espace client d'un utilisateur : les producteurs chez qui il commande, en AMAP, en boutique et en
 * vente directe, avec ses dernières commandes et ses dernières factures.
 *
 * Deux écrans le rendent, l'accueil `/` et `/espace-client` : les deux routes préparent la même
 * donnée, et une seule méthode la prépare.
 */
class CustomerSpaceAiLib {

	use \Notifiable;

	/**
	 * Tout ce que l'espace client montre vient du code fermé, ventes, boutiques et AMAP, qui le prépare sur un
	 * événement : la version publique n'a pas d'espace client.
	 */
	public static function load(\stdClass $data): void {
		$data->customerSpace = array_replace([], ...self::notify('loadCustomerSpace', $data->eUserOnline));
	}

	/**
	 * Une fiche client rattachée au compte, chez un producteur : les fiches vivent dans le code fermé.
	 */
	public static function hasCustomer(\user\User $eUser): bool {
		return in_array(TRUE, self::notify('hasCustomer', $eUser), TRUE);
	}

	/**
	 * Une carte de l'espace client peut déjà mener à la fiche client qu'une invitation rattacherait :
	 * l'invitation en serait alors une seconde entrée.
	 */
	public static function filterInvites(\Collection $cInvite, array $customerSpace): \Collection {

		$inviteIds = array_merge(...self::notify('getInvitesWithCard', $cInvite, $customerSpace));

		return $cInvite->find(fn(\structure\Invite $eInvite) => in_array($eInvite['id'], $inviteIds, TRUE) === FALSE);

	}

}
?>
