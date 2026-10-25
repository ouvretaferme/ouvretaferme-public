<?php
namespace learning;

/**
 * Le compte créé pour se former (`page/account.p.php`), et l'e-mail de bienvenue qui lui est
 * propre : celui des autres inscriptions (`\user\UserUi::getSignUpMail()`) lui proposerait de
 * commander auprès de producteurs.
 */
class AccountLib {

	/**
	 * Envoyé par la page une fois le compte créé, `$eCourse` vide pour un compte venu du catalogue.
	 */
	public static function sendSignUp(\user\User $eUser, Course $eCourse): void {

		$eUser->expects(['id', 'email']);

		\main\MailLib::getInstance()
			->setTo($eUser['email'])
			->setContent(...self::getSignUpMail($eUser, $eCourse))
			->send();

	}

	/**
	 * La formation d'où vient le compte, avec sa durée ; depuis le catalogue, les « Premiers pas »
	 * avec la leur.
	 */
	public static function getSignUpMail(\user\User $eUser, Course $eCourse): array {

		if($eCourse->empty()) {
			$cCourseFeatured = CourseLib::getFeatured();
			CourseLib::fillCounts($cCourseFeatured);
		} else {
			$cCourseFeatured = new \Collection();
			CourseLib::fillCounts(new \Collection([$eCourse['id'] => $eCourse]));
		}

		return new AccountUi()->getSignUpMail($eUser, $eCourse, $cCourseFeatured);

	}

}
?>
