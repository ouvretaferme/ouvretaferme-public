<?php
namespace user;

/**
 * Les écrans du défi d'un administrateur (`ChallengeAiLib`) : le code à saisir après le mot de
 * passe, le lien du même message, et leurs états expirés. Tous vivent dans le panneau de
 * connexion, avec son formulaire, sa feuille `user.css` et ses classes `login-*`.
 *
 * Planche validée le 23/09/2026 : `.claude/state/securite/lot-4/planche-s4-defi.html`.
 */
class ChallengeAiUi {

	public function __construct() {
		\Asset::css('user', 'user.css');
	}

	/**
	 * Le code attendu, juste après le mot de passe.
	 */
	public function code(Challenge $e): string {

		$e->expects(['user' => ['email'], 'redirect']);

		$form = new \util\FormUi();

		$h = '<div class="login-form">';

			$h .= '<p>'.s("Nous venons d'envoyer un code à <b>{email}</b>. Il est valable dix minutes, comme le lien qui l'accompagne.", ['email' => encode($e['user']['email'])]).'</p>';

			$h .= $form->openAjax('/user/log:doCode');

				$h .= $form->hidden('redirect', $e['redirect']);

				$h .= $form->group(
					s("Code reçu"),
					$form->text('code', NULL, [
						'class' => 'login-code',
						'inputmode' => 'numeric',
						'autocomplete' => 'one-time-code',
						'placeholder' => str_repeat('0', ChallengeAiLib::CODE_LENGTH),
						'autofocus' => TRUE
					])
				);

				$submit = '<div class="login-submit">';
					$submit .= $form->submit(s("Se connecter"));
					$submit .= '<div class="login-forgotten-password">';
						$submit .= '<a data-ajax="/user/log:doResend" data-ajax-method="post">'.s("Recevoir un nouveau code").'</a>';
					$submit .= '</div>';
				$submit .= '</div>';

				$h .= $form->group(content: $submit);

			$h .= $form->close();

			$h .= $this->getBackToLogin();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le code ne vaut plus : dix minutes passées, ou le lien du même message a déjà servi.
	 */
	public function codeExpired(Challenge $e): string {

		$h = '<div class="login-form">';

			$h .= '<div class="util-block-warning">'.s("Ce code n'est plus valable.").'</div>';

			$h .= '<div class="login-submit">';

				if($e['acceptResend']) {
					$h .= '<a data-ajax="/user/log:doResend" data-ajax-method="post" class="btn btn-primary">'.s("Recevoir un nouveau code").'</a>';
				} else {
					$h .= '<a href="/user/log:form" class="btn btn-primary">'.s("Me connecter avec mon mot de passe").'</a>';
				}

				$h .= '<div class="login-forgotten-password">';
					$h .= '<a href="/user/log:form">'.s("Revenir à la connexion").'</a>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien du message, ouvert dans le navigateur qui a saisi le mot de passe : la connexion se
	 * fait par un bouton qui poste, jamais par le seul fait d'avoir suivi le lien.
	 */
	public function link(Challenge $e, string $token): string {

		$e->expects(['user', 'createdAt', 'device', 'ip', 'redirect']);

		$form = new \util\FormUi();

		$h = '<div class="login-form">';

			$h .= '<p>'.s("Vous avez saisi le mot de passe du compte <b>{name}</b> le {date}, depuis {device} à l'adresse {ip}.", [
				'name' => encode($e['user']->getName()),
				'date' => \util\DateUi::textual($e['createdAt'], \util\DateUi::DATE_HOUR_MINUTE),
				'device' => self::p('device')->values[$e['device']],
				'ip' => encode($e['ip'])
			]).'</p>';

			$h .= $form->openAjax('/user/log:doLink');

				$h .= $form->hidden('token', $token);
				$h .= $form->hidden('redirect', $e['redirect']);

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

	/**
	 * Le lien suivi dans un autre navigateur que celui du mot de passe : le code du même message
	 * reste le bon moyen.
	 */
	public function linkElsewhere(Challenge $e): string {

		$e->expects(['expiresAt']);

		$h = '<div class="login-form">';

			$h .= '<div class="util-block-warning">'.s("Ce lien ne s'ouvre que dans le navigateur où vous avez saisi votre mot de passe.").'</div>';

			$h .= '<p>'.s("Retournez-y et saisissez le code qui accompagne le lien dans le même message. Il reste valable jusqu'à {time}.", [
				'time' => \util\DateUi::textual($e['expiresAt'], \util\DateUi::TIME_HOUR_MINUTE)
			]).'</p>';

		$h .= '</div>';

		return $h;

	}

	public function linkExpired(): string {

		$h = '<div class="login-form">';

			$h .= '<div class="util-block-warning">'.s("Ce lien n'est plus valable.").'</div>';

			$h .= '<div class="login-submit">';
				$h .= '<a href="/user/log:form" class="btn btn-primary">'.s("Me connecter avec mon mot de passe").'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getBackToLogin(): string {
		return '<p class="mt-2"><a href="/user/log:form">'.s("Revenir à la connexion").'</a></p>';
	}

	/**
	 * Le message qui porte le code et le lien : sujet, corps texte, corps HTML.
	 *
	 * Le code s'écrit en deux groupes de trois chiffres pour se lire, `ChallengeAiLib::normalizeCode()`
	 * retire l'espace à la saisie.
	 */
	public static function getMail(Challenge $e, string $code, string $token): array {

		$e->expects(['user' => ['email'], 'createdAt', 'device', 'ip']);

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("Vous venez de saisir le mot de passe du compte administrateur <b>{email}</b> depuis {device}, adresse {ip}, le {date}.", [
			'email' => encode($e['user']['email']),
			'device' => self::p('device')->values[$e['device']],
			'ip' => encode($e['ip']),
			'date' => \util\DateUi::textual($e['createdAt'], \util\DateUi::DATE_HOUR_MINUTE)
		]).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("Votre code de connexion"),
			$content,
			valueLabel: s("Votre code"),
			value: self::formatCode($code),
			buttonLabel: s("Me connecter"),
			buttonUrl: self::getLink($token),
			buttonNote: s("Le code et le lien sont valables dix minutes. Le premier utilisé annule l'autre."),
			asideTitle: s("Ce n'est pas vous ?"),
			asideText: s("Quelqu'un connaît votre mot de passe. Ignorez ce message, cette personne ne peut pas se connecter sans ce code, et changez votre mot de passe dès que possible.")
		);

	}

	public static function getLink(string $token): string {
		return \Lime::getUrl().'/user/log:link?token='.$token;
	}

	public static function formatCode(string $code): string {
		return substr($code, 0, 3).' '.substr($code, 3);
	}

	public static function p(string $property): \PropertyDescriber {

		$d = Challenge::model()->describer($property, [
			'device' => s("Appareil")
		]);

		switch($property) {

			case 'device' :
				$d->values = [
					Challenge::WEB => s("un ordinateur"),
					Challenge::APP => s("l'application"),
					Challenge::MOBILE_WEB => s("un mobile"),
					Challenge::TABLET_WEB => s("une tablette"),
					Challenge::CRAWLER => s("un robot")
				];
				break;

		}

		return $d;

	}

}
?>
