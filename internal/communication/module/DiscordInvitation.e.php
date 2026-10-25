<?php
namespace communication;

class DiscordInvitation extends DiscordInvitationElement {

	// Le lien sert à toute l'équipe de la structure (R-45) : un partage d'école ou de communauté n'en fait pas partie.
	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->isMember();

	}

}
?>
