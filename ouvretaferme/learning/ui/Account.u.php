<?php
namespace learning;

/**
 * Le formulaire d'un compte pour se former, dans le panneau ouvert depuis une formation comme dans
 * la page de /inscription : les champs de l'inscription d'une personne, le mot de passe saisi une
 * fois avec son œil, et les conditions d'utilisation.
 */
class AccountUi {

	/**
	 * Sur une formation où l'on s'inscrit avant de lire (`$enrollFirst`, posé par la page), le compte
	 * créé revient à la fiche, inscrit, et commence quand il veut : le bouton ne promet que
	 * l'inscription.
	 */
	public function create(\user\User $eUser, Course $eCourse, bool $enrollFirst): \Panel {

		return new \Panel(
			id: 'panel-learning-account',
			title: s("Créer votre compte"),
			subTitle: CourseUi::getTypeBadge($eCourse).encode($eCourse['name']),
			body: $this->getForm($eUser, $eCourse, $enrollFirst ? s("S'inscrire") : s("S'inscrire et commencer"), CourseUi::url($eCourse))
		);

	}

	public function getPage(\user\User $eUser): string {

		return $this->getForm($eUser, new Course(), s("S'inscrire"), '/formations');

	}

	private function getForm(\user\User $eUser, Course $eCourse, string $submit, string $redirect): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/account:doCreate', ['class' => 'form-columns form-columns-33 form-columns-xs', 'autocomplete' => 'off']);

			if($eCourse->notEmpty()) {
				$h .= $form->hidden('course', $eCourse);
			}

			$h .= $form->hidden('type', \user\User::PRIVATE);

			$h .= $form->dynamicGroups($eUser, ['firstName', 'lastName', 'email', 'deliveryCountry']);

			$h .= $form->group(
				s("Mot de passe"),
				$form->password('password', NULL, ['autocomplete' => 'new-password'])
			);

			$h .= $form->group(
				\user\UserUi::getTosLabel(),
				$form->inputCheckbox('tos', 1, ['id' => 'tos'])
			);

			$h .= $form->group(
				content: $form->submit($submit)
			);

			// « Se connecter » renvoie le formulaire à `logIn`, qui bascule le panneau sur la connexion
			// avec l'adresse saisie : un lien n'emporterait pas ce qui est tapé dans le champ.
			$h .= $form->group(
				content: '<p>'.s("Vous avez déjà un compte ?").' <a data-ajax-submit="/learning/account:logIn" post-redirect="'.encode($redirect).'">'.s("Se connecter").'</a></p>'
			);

		$h .= $form->close();

		return $h;

	}

	/**
	 * La connexion dans le panneau de la création de compte, qu'elle remplace, l'adresse déjà saisie
	 * reprise.
	 */
	public function logIn(?string $email, Course $eCourse): \Panel {

		return new \Panel(
			id: 'panel-learning-account',
			title: s("Connectez-vous !"),
			subTitle: $eCourse->empty() ? NULL : CourseUi::getTypeBadge($eCourse).encode($eCourse['name']),
			body: new \user\UserUi()->logInBasic($email)
		);

	}

	/**
	 * L'e-mail de bienvenue d'un compte créé pour se former (variante B de la planche
	 * bienvenue-apprenant). Depuis une formation, la formation d'abord, sur le modèle de l'e-mail
	 * de partage (`ShareUi`) : la leçon est déjà ouverte quand il arrive, il sert à y revenir.
	 * Depuis le catalogue, les « Premiers pas ».
	 *
	 * Le bouton dit « Retrouver » et non « Reprendre » : une formation datée sans place confirmée
	 * n'est pas commencée quand l'e-mail arrive.
	 */
	public function getSignUpMail(\user\User $eUser, Course $eCourse, \Collection $cCourseFeatured): array {

		$eUser->expects(['firstName', 'email']);

		$url = \Lime::getUrl();

		$asideTitle = s("Vérifiez votre adresse");
		$asideText = s("Nous devons confirmer que <b>{email}</b> est bien à vous. <link>Vérifier mon adresse e-mail</link>", [
			'email' => encode($eUser['email']),
			'link' => \main\MailSoftwareAiUi::linkTag(\user\UserUi::getVerifyLink($eUser)),
		]);

		if($eCourse->notEmpty()) {

			$volume = CourseUi::getVolume($eCourse);

			return \main\MailSoftwareAiUi::format(
				s("Votre formation « {value} »", $eCourse['name']),
				content: ($eCourse['summary'] === NULL) ? '' : '<p>'.encode($eCourse['summary']).'</p>',
				heroTitle: encode($eCourse['name']),
				heroText: s("Votre compte est créé. Votre avancement dans la formation y reste enregistré, d'un appareil à l'autre."),
				facts: ($volume === NULL) ? NULL : [s("Durée") => $volume],
				buttonLabel: s("Retrouver la formation"),
				buttonUrl: $url.CourseUi::url($eCourse),
				buttonNote: s("Elle se retrouve aussi dans <link>Mes formations</link>", [
					'link' => \main\MailSoftwareAiUi::linkTag($url.'/mes-formations'),
				]),
				asideTitle: $asideTitle,
				asideText: $asideText
			);

		}

		if($eUser['firstName'] === NULL) {
			$title = s("Bienvenue sur {siteName} !");
		} else {
			$title = s("{value}, bienvenue sur {siteName} !", $eUser['firstName']);
		}

		$cards = [];

		foreach($cCourseFeatured as $eCourseFeatured) {
			$cards[] = [
				'title' => encode($eCourseFeatured['name']),
				'text' => $this->getSignUpCardText($eCourseFeatured),
				'label' => s("Voir la formation"),
				'url' => $url.CourseUi::url($eCourseFeatured)
			];
		}

		return \main\MailSoftwareAiUi::format(
			$title,
			heroTitle: s("Votre compte est créé"),
			heroText: s("Il vous ouvre les <catalog>formations du catalogue</catalog>. Celles que vous commencez se retrouvent dans <mine>Mes formations</mine>.", [
				'catalog' => \main\MailSoftwareAiUi::linkTag($url.'/formations'),
				'mine' => \main\MailSoftwareAiUi::linkTag($url.'/mes-formations'),
			]),
			cardsTitle: ($cards === []) ? NULL : s("Premiers pas"),
			cards: ($cards === []) ? NULL : $cards,
			asideTitle: $asideTitle,
			asideText: $asideText
		);

	}

	private function getSignUpCardText(Course $eCourse): string {

		$lines = [];

		if($eCourse['summary'] !== NULL) {
			$lines[] = encode($eCourse['summary']);
		}

		$volume = CourseUi::getVolume($eCourse);

		if($volume !== NULL) {
			$lines[] = $volume;
		}

		return implode('<br/>', $lines);

	}

}
?>
