<?php
namespace user;

/**
 * L'écran du lien de connexion reçu par e-mail (`LoginLinkAiLib`) : le bouton qui connecte. Il vit
 * dans le panneau de connexion, avec sa feuille `user.css` et ses classes `login-*`, comme les
 * écrans du défi (`ChallengeAiUi`). Un lien qui ne connecte plus personne n'a pas d'écran : la page
 * répond 404 (`user/page/log.p.php`).
 */
class LoginLinkAiUi {

	public function __construct() {
		\Asset::css('user', 'user.css');
	}

	/**
	 * Le lien ouvert : rien n'est consommé tant que le bouton n'a pas posté.
	 */
	public function link(LoginLink $e, string $token): string {

		$e->expects(['user' => ['email'], 'createdAt']);

		$form = new \util\FormUi();

		$h = '<div class="login-form">';

			$h .= '<p>'.s("Vous avez demandé un lien de connexion pour <b>{email}</b> le {date}.", [
				'email' => encode($e['user']['email']),
				'date' => \util\DateUi::textual($e['createdAt'], \util\DateUi::DATE_HOUR_MINUTE)
			]).'</p>';

			$h .= $form->openAjax('/user/log:doLoginLink');

				$h .= $form->hidden('token', $token);

				$h .= '<div class="login-submit">';
					$h .= $form->submit(s("Me connecter"));
					$h .= '<div class="login-forgotten-password">';
						$h .= '<a href="/">'.s("Ce n'est pas moi").'</a>';
					$h .= '</div>';
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public static function getLink(string $token): string {
		return \Lime::getUrl().'/user/log:loginLink?token='.urlencode($token);
	}

}
?>
