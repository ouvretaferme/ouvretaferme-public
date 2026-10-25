<?php
namespace user;

/**
 * La route du skill Claude de l'avatar (`/user/avatarUpload:skill`) suit le drapeau de l'envoi : fermé pour les
 * structures de l'utilisateur, elle refuse ; ouvert, elle sert l'archive. Le test joue la vraie page, pour prouver que
 * la garde y est branchée et non seulement qu'elle sait refuser.
 *
 * Le drapeau se règle par son point de lecture, `UserSetting::$avatarUpload`, reposé sur sa valeur de production (la
 * structure 7 seule) puis remis en place. Aucune écriture en base.
 *
 * Lancement : dev/test package=user file=UserAvatarUploadSkillAi
 */
class UserAvatarUploadSkillAiTest extends \Test {

	// FEATURE_UPLOAD_MODEL en production
	const array OPEN = [7];

	public function testFlag(): void {

		$enabled = UserSetting::$avatarUpload;
		$eUserInitial = ConnectionLib::getOnline();

		UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => in_array($eStructure['id'], self::OPEN, TRUE);
		ConnectionLib::setOnline(new User(['id' => 1]));

		try {

			$closed = $this->runSkill(1);
			$this->assertTrue($closed instanceof \NotExpectedAction, "la route du skill sert l'archive à un utilisateur dont aucune structure n'a l'envoi ouvert : ".get_class($closed));

			$open = $this->runSkill(7);
			$this->assertTrue($open instanceof \DataAction, "la route du skill refuse un utilisateur dont la structure a l'envoi ouvert : ".get_class($open));

		} finally {
			UserSetting::$avatarUpload = $enabled;
			ConnectionLib::setOnline($eUserInitial);
		}

	}

	/** Joue la page sous un utilisateur membre de la seule structure `$structureId`, et rend l'action qu'elle lève. */
	private function runSkill(int $structureId): \Action {

		// La page commence par checkLogged(), dont l'adresse de retour par défaut n'est définie que par une requête HTTP
		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		$data = new \stdClass();
		$data->eUserOnline = ConnectionLib::getOnline();
		$data->hasAvatar = TRUE;
		$data->ccStructureUser = new \Collection([new \Collection([new \structure\Structure(['id' => $structureId])])]);

		try {
			\Page::execute([['user/avatarUpload', 'skill', [], NULL]], $data);
		} catch(\Action $action) {
			return $action;
		} finally {
			$method->setValue(NULL, $methodBefore);
		}

		throw new \Exception('user/avatarUpload:skill n\'a levé aucune action');

	}

}
?>
