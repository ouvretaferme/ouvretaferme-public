<?php
namespace media;

class UserVignetteLib extends MediaLib {

	/**
	 * Un avatar qui a pris la place de la photo de profil la garde de côté : en envoyer une remplacerait son image.
	 */
	public function buildElement(): \Element {

		$eUser = \user\ConnectionLib::getOnline();

		if(\user\UserAvatarLib::getByUser($eUser)->isReplacingPhoto()) {
			throw new \NotAllowedAction('Avatar');
		}

		return $eUser;

	}

	/**
	 * La photo qu'un avatar remplace n'est plus sur `User.vignette` mais sur `UserAvatar.photo`, d'où elle revient
	 * quand l'avatar cesse de la remplacer ou disparait : elle reste active tant qu'elle y est.
	 */
	public function clean(): bool {

		if(parent::clean() === FALSE) {
			return FALSE;
		}

		$photos = \user\UserAvatar::model()
			->wherePhoto('!=', NULL)
			->getColumn('photo');

		foreach(array_chunk($photos, 100) as $photosChunk) {
			$this->activeMedias($photosChunk);
		}

		return TRUE;

	}

}
?>
