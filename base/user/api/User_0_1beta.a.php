<?php
namespace user;

/**
 * Objet partagé, sans route : un utilisateur n'est pas une ressource de l'API (on ne le liste ni ne
 * le modifie), mais d'autres modules le référencent — structure\Member — et doivent pouvoir en
 * rendre l'identité. Déclarer l'élément suffit à ce que \ApiData::exportElement() sache le
 * sérialiser, sans exposer la fiche complète (adresses de facturation, statut, ancienneté…).
 */
class User_0_1betaApi extends \Api {

	public string $scopeName = 'general';

	public function __construct() {

		$this->element(
			'user\User',
			fn() => [
				'id', 'firstName', 'lastName', 'email', 'vignette'
			],
			[
				// L'équipe d'une structure n'affiche l'adresse que d'un compte public (MemberUi::getUser())
				'email' => fn(\ApiData $data, User $e) => $e->isPublic() ? $e['email'] : NULL,
				'vignette' => fn(\ApiData $data, User $e) => new \media\UserVignetteUi()->getUrlByHash($e['vignette'], 'm')
			]
		);

	}

}
?>
