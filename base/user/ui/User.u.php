<?php
namespace user;

class UserUi {

	use \Notifiable;

	public function __construct() {
		\Asset::css('user', 'user.css');
		\Asset::js('user', 'user.js');
	}

	public static function name(User $eUser): string {

		$eUser->expects(['firstName', 'lastName']);

		if($eUser['firstName'] === NULL) {
			return encode($eUser['lastName']);
		} else {
			return encode($eUser['firstName']).' '.encode($eUser['lastName']);
		}

	}

	/**
	 * Get a login form
	 *
	 * @return string
	 */
	public function logInBasic(?string $email = NULL, bool $withSignUp = FALSE): string {

		$redirect = REQUEST('redirect', 'string', LIME_URL);

		$form = new \util\FormUi();

		$h = '<div class="login-form">';

			$h .= $form->openAjax('/user/log:in');

				$h .= $form->hidden('redirect', $redirect);

				$h .= $form->group(
					s("Adresse e-mail"),
					$form->email('login', $email)
				);

				$h .= $form->group(
					s("Mot de passe"),
					$form->password('password')
				);


				$h .= $form->group(
					NULL,
					$form->checkbox('remember', 1, ['checked' => TRUE, 'callbackLabel' => fn($input) => $input.' '.s("Se souvenir de moi")]),
					['class' => 'login-remember']
				);

				$submit = '<div class="login-submit">';
					$submit .= $form->submit(s("Se connecter"));
					$submit .= '<div class="login-forgotten-password">
						<a href="/user/log:forgottenPassword" target="_blank">'.s("Mot de passe oublié ?").'</a>
					</div>';
				$submit .= '</div>';

				$h .= $form->group(content: $submit);

			$h .= $form->close();

		$h .= '</div>';

		if($withSignUp === FALSE) {
			return $h;
		}

		$columns = '<div class="util-board-columns util-board-columns-1">';
			$columns .= '<div class="util-board-column">'.$h.'</div>';
			$columns .= '<div class="util-board-column text-center">';
				$columns .= '<h4>'.s("Vous n'êtes pas encore inscrit ?").'</h4>';
				$columns .= '<a href="/inscription" class="btn btn-outline-primary">'.s("Créer un compte").'</a>';
			$columns .= '</div>';
		$columns .= '</div>';

		return $columns;

	}

	/**
	 * L'encart qui précède le formulaire d'inscription.
	 *
	 * Ici et non dans la vue : le tunnel des relevés de prix sert le même formulaire sur
	 * sa dernière étape, et deux copies du texte auraient divergé au premier
	 * changement.
	 */
	public function getSignUpStart(User $eUser): string {

		if($eUser['roleCustomer']) {
			return '';
		}

		if($eUser['roleProducer']) {
			$start = '<p>'.s("Pour travailler avec le logiciel comme producteur, nous vous suggérons de créer un compte personnel avec vos nom et prénom.").'</p>';
			$start .= '<p>'.s("Vous pourrez créer votre ferme ou rejoindre une ferme existante et profiter de toutes les fonctionnalités juste après !<br/>Vous pourrez également inviter autant de collègues que nécessaire dans votre équipe.").'</p>';
		} else if($eUser['roleConsultant']) {
			$start = '<p>'.s("Pour travailler avec le logiciel comme structure d'accompagnement, nous vous suggérons de créer un compte personnel avec vos nom et prénom.").'</p>';
		} else if($eUser['roleTeacher']) {
			$start = '<p>'.s("Pour travailler avec le logiciel comme organisme de formation, nous vous suggérons de créer un compte personnel avec vos nom et prénom.").'</p>';
		} else {
			return '';
		}

		return new \main\DesignUi()->getStart(
			\Asset::icon('lightbulb'),
			s("Démarrer avec {siteName}"),
			$start
		);

	}

	/**
	 * Groups all the social signup forms
	 *
	 * @return string
	 */
	public function signUp(string $page, User $eUser, \structure\Invite $eInvite, ?string $redirect = NULL): string {

		$form = new \util\FormUi([
			'firstColumnSize' => 40
		]);

		$h = $form->openAjax($page, ['id' => 'user-signup', 'autocomplete' => 'off', 'style' => 'max-width: 768px; margin: auto']);

			$h .= $form->hidden('redirect', $redirect);

			$h .= '<div id="user-signup-fields">';
				$h .= $this->getSignUpFields($eUser, $eInvite);
			$h .= '</div>';

			$h .= $form->group(
				self::getTosLabel(),
				$form->inputCheckbox('tos', 1, ['id' => 'tos'])
			);
	
			$h .= $form->group(
				content: $form->submit(s("S'inscrire"))
			);

		$h .= $form->close();

		return $h;

	}

	public static function getTosLabel(): string {

		return s("J'accepte les <link>conditions générales d'utilisation</link>", ['link' => '<a href="/presentation/service" target="_blank">']);

	}

	public function getSignUpFields(User $e, \structure\Invite $eInvite): string {

		$form = new \util\FormUi();

		$h = '';

		if($eInvite->isValid()) {
			$h .= $form->hidden('invite', $eInvite['key']);
		}

		if($e['roleCustomer']) {
			$h .= $form->dynamicGroup($e, 'type');
			$h .= $form->dynamicGroups($e, ['firstName', 'lastName', 'email']);

			if($e['type'] === \user\User::PRIVATE) {
				$h .= $form->dynamicGroups($e, ['deliveryCountry']);
			}

		} else {
			$h .= $form->hidden('type', \user\User::PRIVATE);
			$h .= $form->dynamicGroups($e, ['firstName', 'lastName', 'email', 'deliveryCountry']);
		}

		$h .= new \user\UserUi()->getPasswordFields($form);

		if(
			$e['roleCustomer'] and
			$e['type'] === \user\User::PRO
		) {

			$h .= '<div class="user-signup-pro">';

				$h .= '<h3 class="mt-2 mb-2">'.s("Ma société").'</h3>';

				$h .= $form->dynamicGroup($e, 'siret');
				$h .= $form->dynamicGroup($e, 'legalName');
				$h .= $form->addressGroup(s("Adresse de livraison"), 'delivery', $e);

				$h .= '<div class="user-form-address">';

					$before = $form->checkbox(NULL, '1', [
						'onclick' => 'User.changeAddress(this);',
						'checked' => TRUE,
						'callbackLabel' => fn($input) => $input.' '.s("Utiliser la même adresse que la livraison")
					]);

					$h .= $form->addressGroup(s("Adresse de facturation"), 'invoice', $e, before: $before);

				$h .= '</div>';

			$h .= '</div>';

		}

		return $h;
	}

	/**
	 * Le lien vers la réinitialisation du mot de passe, qui reprend l'identifiant tenté s'il est une
	 * adresse e-mail.
	 */
	public static function urlForgottenPassword(?string $login): string {

		$url = '/user/log:forgottenPassword';

		if($login !== NULL and filter_var($login, FILTER_VALIDATE_EMAIL) !== FALSE) {
			$url .= '?email='.urlencode($login);
		}

		return $url;

	}

	/**
	 * Get a forgotten password form
	 *
	 */
	public function forgottenPassword(string $email = ''): string {

		$form = new \util\FormUi();

		$h = '<div class="util-info">'.s("Si vous avez oublié votre mot de passe, indiquez l'adresse e-mail avec laquelle vous vous êtes inscrit sur {siteName}.
Vous recevrez alors un e-mail contenant un lien vous permettant d'en choisir un nouveau.").'</div>';

		$h .= $form->openAjax('/user/forgotten:do');

		$h .= $form->group(
			s("Votre adresse e-mail"),
			$form->email('email', $email)
		);

		$h .= $form->group(
			content: $form->submit(s("Recevoir les instructions par e-mail"))
		);

		$h .= $form->close();

		$h .= '<div class="util-signature-mascot">'.\Asset::svg('main', 'friends/oiseau.svg').'</div>';

		return $h;
	}

	/**
	 * Change personal data
	 */
	public function update(User $eUser): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/user/update:doUpdate');

		$h .= $form->group(
			self::p('email')->label,
			$form->inputGroup(
				$form->addon(\Asset::icon('envelope-fill')).
				'<div class="form-control">'.encode($eUser['email']).'</div>'.
				'<a href="/user/settings:updateEmail" class="btn btn-only-icon btn-primary">'.\Asset::icon('pencil-fill').'</a>'
			)
		);

		$h .= $form->dynamicGroups($eUser, ['firstName', 'lastName', 'phone']);

		if($eUser['type'] === User::PRO) {
			$h .= $form->dynamicGroup($eUser, 'siret');
			$h .= $form->dynamicGroup($eUser, 'legalName');
		}

		$h .= $form->addressGroup(s("Adresse de livraison"), 'delivery', $eUser);

		$h .= '<div class="user-form-address">';

			$before = $form->checkbox(NULL, '1', [
				'onclick' => 'User.changeAddress(this);',
				'checked' => $eUser->hasInvoiceAddress() === FALSE,
				'callbackLabel' => fn($input) => $input.' '.s("Utiliser la même adresse que la livraison")
			]);

			$h .= $form->addressGroup(s("Adresse de facturation"), 'invoice', $eUser, before: $before);

		$h .= '</div>';

		$h .= $form->group(
			content: $form->submit(s("Enregistrer"))
		);

		$h .= $form->close();

		return $h;

	}


	/**
	 * Change an email
	 */
	public function updateEmail(User $eUser): string {

		if($eUser['bounce'] === TRUE) {
			$h = '<div class="util-warning">'.s("Le dernier e-mail que nous avons tenté de vous adresser a été rejeté, car votre adresse e-mail semble incorrecte. Veuillez s'il vous plait la mettre à jour avant de continuer sur {siteName}.").'</div>';
		} else {
			$h = '';
		}
		$form = new \util\FormUi();

		$h .= $form->openAjax('/user/update:doEmail');

		if($eUser['canUpdate']['hasPassword']) {

			$h .= $form->group(
				s("Mot de passe actuel"),
				$form->password('passwordOld')
			);

		}

		$h .= $form->group(
			s("Nouvelle adresse e-mail"),
			$form->email('email', $eUser['email'])
		);

		if($eUser['bounce']) {
			$h .= '<br/>';
			$h .= '<p class="color-danger">'.\Asset::icon('exclamation-triangle-fill').'&nbsp;'.s("Cette adresse e-mail ne fonctionne pas, merci de renseigner une nouvelle adresse e-mail.").'</p>';
		}

		$h .= $form->group(
			content: $form->submit(s("Modifier mon e-mail"))
		);

		$h .= $form->close();

		if($eUser['email'] !== NULL and $eUser['bounce'] === FALSE) {

			$isVerified = $eUser->isVerified();

			$h .= '<br/><br/>';

			$h .= '<div class="util-block">';

			if($isVerified) {
				$h .= '<p class="color-success">'.\Asset::icon('check').'&nbsp;'.s("Adresse e-mail validée").'</p>';
			} else {
				$h .= $this->sendConfirmationMail();
			}

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Send my confirmation email again
	 */
	protected function sendConfirmationMail(): string {

		$h = '<p class="color-warning">';
			$h .= \Asset::icon('exclamation-triangle-fill').'&nbsp;'.s("Vous n'avez pas encore vérifié votre adresse e-mail !");
		$h .= '</p>';

		$h .= '<a data-ajax="/user/verify:doSend" class="btn btn-warning">'.s("Recevoir le mail de confirmation").'</a>';

		return $h;

	}


	/**
	 * Change a password
	 */
	public function updatePassword(User $eUser, ?string $hash = NULL, ?string $email = NULL): string {

		$form = new \util\FormUi();

		if($hash === NULL) {
			$url = '/user/update:doPassword';
		} else {
			$url = '/user/forgotten:doReset';
		}

		$h = $form->openAjax($url);

		$passwordText1 = s("Nouveau mot de passe");
		$passwordText2 = s("Encore mon nouveau mot de passe");
		$textButton = s("Modifier mon mot de passe");

		if($hash !== NULL and $email !== NULL) {

			$h .= $form->hidden('hash', $hash);
			$h .= $form->hidden('email', $email);

		} else if($eUser['canUpdate']['hasPassword'] === FALSE) {

			$passwordText1 = s("Mon mot de passe");
			$passwordText2 = s("Encore mon mot de passe");

			$textButton = s("Créer mon mot de passe");

			if($eUser['email'] === NULL) {

				$h .= $form->group(
					s("Mon adresse e-mail"),
					$form->email('email')
				);

			} else {

				$h .= $form->group(
					s("Mon adresse e-mail"),
					'<u>'.encode($eUser['email']).'</u>'
				);
				$h .= $form->hidden('email', $eUser['email']);

			}

		} else {

			$h .= $form->group(
				s("Mot de passe actuel"),
				$form->password('passwordOld')
			);

		}

		$h .= $form->group(
			$passwordText1,
			$form->password('password')
		);

		$h .= $form->group(
			$passwordText2,
			$form->password('passwordBis')
		);

		$h .= $form->group(
			content: $form->submit($textButton)
		);

		$h .= $form->close();

		return $h;

	}

	public function getPasswordFields(\util\FormUi $form): string {

		$h = '<input type="password" name="passwordFake" style="display:none">';

		$h .= $form->group(
			s("Votre mot de passe"),
			$form->password('password', NULL, ['placeholder' => s("Mot de passe"), 'autocomplete' => 'new-password'])
		);

		$h .= $form->group(
			s("Retapez le mot de passe"),
			$form->password('passwordBis', attributes: ['autocomplete' => 'new-password'])
		);

		return $h;

	}

	public function logOutExternal(User $eUser): string {

		$h = '<div class="logout-external">';
			$h .= \Asset::icon('person-fill').' '.encode($eUser['email']);
			$h .= '<br/>';
			$h .= '<a data-ajax="/user/log:doLogoutExternal">'.s("Revenir sur votre compte").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function toggleRole(User $eUser, $role) {

		$property = match($role) {
			'roleProducer' => 'role-producer',
			'roleConsultant' => 'role-consultant',
			'roleTeacher' => 'role-teacher',
			default => throw new \UnsupportedException()
		};

		return \util\TextUi::switch([
			'id' => 'product-switch-'.$eUser['id'],
			'data-ajax' => '/user/settings:doUpdateRole',
			'post-id' => $eUser['id'],
			'post-property' => $role,
			'post-'.$property => ($eUser[$role] === TRUE) ? FALSE : TRUE
		], $eUser[$role] === TRUE);

	}

	public function getNav(User $eUser): string {

		$h = '<div class="util-cards util-cards-2 mb-2">';
			$h .= '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-settings">'.\Asset::icon('moon-fill').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Menu sombre").'</div>';
						$h .= '<p>'.s("Le menu de gauche prend la couleur de l'univers, sur ordinateur et sur tablette").'</p>';
					$h .= '</div>';
					$h .= '<div class="util-deck-aside">'.$this->toggleNav($eUser).'</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function toggleNav(User $eUser): string {

		return \util\TextUi::switch([
			'id' => 'user-nav-switch',
			'data-ajax' => '/user/settings:doUpdateNav',
			'post-nav' => match($eUser['nav']) {
				User::DARK => User::LIGHT,
				User::LIGHT => User::DARK
			}
		], $eUser['nav'] === User::DARK);

	}

	public static function getVignette(User $eUser, string $size): string {

		$class = 'media-circle-view';
		$style = '';

		$ui = new \media\UserVignetteUi();

		if($eUser->empty()) {

			$class .= ' media-vignette-default '.$class;
			$style .= 'background-color: #EEE;';
			$content = '@';
			$title = '';

		} else {

			$eUser->expects(['id', 'vignette', 'firstName', 'lastName']);

			if($eUser['vignette'] !== NULL) {

				$format = $ui->convertToFormat($size);

				$style .= 'background-image: url('.$ui->getUrlByElement($eUser, $format).');';
				$class .= ' media-vignette-image';

				$content = '';

			} else {

				list(
					$color,
					$content
				) = self::getDefault($eUser);

				$class .= ' media-vignette-default';
				$style .= 'background-color:'.$color.';';

			}

			$title = self::name($eUser);

		}

		return '<div class="'.$class.'" style="'.$ui->getSquareCss($size).'; '.$style.'" title="'.$title.'">'.encode($content).'</div>';

	}

	/**
	 * Get the subject the text and html body of the email to reset the password mail.
	 *
	 * @param string $hash
	 * @param string $email
	 *
	 * @return [string, string, string]
	 */
	public static function getForgottenPasswordMail(string $hash, string $email, ?string $loginToken = NULL): array {

		$content = '<p>'.s("Bonjour,").'</p>';

		if($loginToken === NULL) {

			$content .= '<p>'.s("Vous avez demandé à changer le mot de passe du compte {siteName} rattaché à <b>{email}</b>.", ['email' => encode($email)]).'</p>';

			return \main\MailSoftwareAiUi::format(
				s("Réinitialiser votre mot de passe"),
				$content,
				buttonLabel: s("Choisir un nouveau mot de passe"),
				buttonUrl: self::getForgottenPasswordLink($hash, $email),
				buttonNote: s("Ce lien est valable 24 heures."),
				asideTitle: s("Ce n'est pas vous ?"),
				asideText: s("Ignorez ce message. Votre mot de passe reste celui que vous connaissez, et personne ne peut le changer sans ce lien.")
			);

		}

		$content .= '<p>'.s("Vous avez demandé à vous connecter au compte {siteName} rattaché à <b>{email}</b>.", ['email' => encode($email)]).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("Votre lien de connexion à {siteName}"),
			$content,
			buttonLabel: s("Me connecter maintenant"),
			buttonUrl: LoginLinkAiUi::getLink($loginToken),
			buttonNote: s("Ce lien est valable dix minutes et ne sert qu'une fois.").'<br/><br/>'.s("<link>Choisir un nouveau mot de passe</link> (valable 24 heures)", ['link' => '<a href="'.encode(self::getForgottenPasswordLink($hash, $email)).'" style="color: '.\main\MailSoftwareAiUi::ACCENT.';">']),
			asideTitle: s("Ce n'est pas vous ?"),
			asideText: s("Ignorez ce message. Sans ces liens, personne ne peut se connecter à votre place ni changer votre mot de passe.")
		);

	}

	public static function getForgottenPasswordLink(string $hash, string $email): string {
		return \Lime::getUrl().'/user/forgotten:set?hash='.$hash.'&email='.urlencode($email);
	}

	/**
	 * Mail to verify the email address after
	 * - the user asked to receive the confirmation email
	 * - the user changed his email address
	 *
	 */
	public static function getVerifyMail(User $eUser, string $hash, bool $change): array {

		$content = '<p>'.s("Bonjour,").'</p>';

		if($change === TRUE) {

			$title = s("Confirmez votre nouvelle adresse e-mail");
			$label = s("Confirmer mon adresse");

			$content .= '<p>'.s("Vous avez choisi <b>{email}</b> comme nouvelle adresse sur {siteName}.", ['email' => encode($eUser['email'])]).'</p>';

		} else {

			$title = s("Vérifiez votre adresse e-mail");
			$label = s("Vérifier mon adresse");

			$content .= '<p>'.s("Vous avez demandé à vérifier l'adresse <b>{email}</b> sur {siteName}.", ['email' => encode($eUser['email'])]).'</p>';

		}

		return \main\MailSoftwareAiUi::format(
			$title,
			$content,
			buttonLabel: $label,
			buttonUrl: \Lime::getUrl().'/user/verify:check?hash='.urlencode($hash)
		);

	}

	/**
	 * Get the subject the text and html body of the email confirmation mail.
	 *
	 * @param string $hash
	 * @return array
	 */
	public static function getSignUpMail(User $eUser): array {

		if($eUser->isProducer()) {
			return self::getSignUpProducerMail($eUser);
		}

		return self::getSignUpDefaultMail($eUser);

	}

	protected static function getSignUpProducerMail(User $eUser): array {

		$eUser->expects(['firstName']);

		if($eUser['firstName'] === NULL) {
			$title = s("Bienvenue sur {siteName} !");
		} else {
			$title = s("{value}, bienvenue sur {siteName} !", $eUser['firstName']);
		}

		return \main\MailSoftwareAiUi::format(
			$title,
			heroTitle: s("Votre compte est créé.<br/>Bienvenue sur {siteName}."),
			heroText: s("Créez votre ferme, si ce n'est pas déjà fait, et toutes les fonctionnalités s'ouvriront en même temps."),
			heroImage: \main\MailSoftwareAiUi::image('mascot.png'),
			buttonLabel: s("Créer ma ferme"),
			buttonUrl: \Lime::getUrl().'/',
			chips: self::getSignUpChips(),
			asideTitle: s("Vérifiez votre adresse"),
			asideText: s("Nous devons confirmer que <b>{email}</b> est bien à vous. <link>Vérifier mon adresse e-mail</link>", [
				'email' => encode($eUser['email']),
				'link' => \main\MailSoftwareAiUi::linkTag(self::getVerifyLink($eUser)),
			])
		);

	}

	protected static function getSignUpChips(): array {

		$url = \Lime::getUrl().'/demarrer/ferme';

		return [
			['image' => \main\MailSoftwareAiUi::image('selling.png'), 'label' => s("Vendre<br/>en ligne"), 'url' => $url],
			['image' => \main\MailSoftwareAiUi::image('invoicing.png'), 'label' => s("Facturer"), 'url' => $url],
			['image' => \main\MailSoftwareAiUi::image('market.png'), 'label' => s("Encaisser<br/>au marché"), 'url' => $url],
			['image' => \main\MailSoftwareAiUi::image('accounting.png'), 'label' => s("Tenir sa<br/>comptabilité"), 'url' => $url],
			['image' => \main\MailSoftwareAiUi::image('cultivation.png'), 'label' => s("Planifier<br/>ses cultures"), 'url' => $url],
			['image' => \main\MailSoftwareAiUi::image('website.png'), 'label' => s("Créer son<br/>site internet"), 'url' => $url],
		];

	}

	protected static function getSignUpDefaultMail(User $eUser): array {

		$title = s("Bienvenue sur {siteName} !");

		if($eUser->isMember()) {
			$role = s("Vous pouvez désormais créer la page de votre ferme pour commencer à utiliser le service !");
		} else {
			$role = s("Vous pouvez désormais commander en ligne les produits de vos producteurs locaux préférés !");
		}

		$text = s("Bonjour,

{how}
{role}

{url}

À tout de suite sur {siteName},
L'équipe", ['how' => \user\UserUi::getSignUpType($eUser), 'role' => $role, 'url' => \Lime::getUrl()]);

		return [
			$title,
			$text
		];

	}

	/**
	 * Send my confirmation email again
	 */
	public static function getSignUpType(User $eUser): string {

		$eUser->expects([
			'email',
			'auth' => ['type']
		]);

		switch($eUser['auth']['type']) {

			case UserAuth::BASIC :
				return s("Vous venez de vous inscrire sur {siteName} avec votre adresse e-mail {value}.<br />Cliquez sur ce lien pour vérifier votre adresse e-mail : <br /><link>Vérifier mon adresse e-mail</link><br /><br />", [
					'value' => encode($eUser['email']), 'link' => '<a href="'.self::getVerifyLink($eUser).'">'
				]);

		}

	}

	public static function getVerifyLink(User $eUser): string {
		return \Lime::getUrl().'/user/verify:check?hash='.urlencode(EmailLib::computeHash($eUser));
	}

	public static function getCloseMail(): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("Vous avez décidé de fermer votre compte sur {siteName}. Nous avons bien enregistré votre demande.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("Fermeture de votre compte {siteName}"),
			$content,
			asideTitle: s("Vous avez {value} jours pour changer d'avis", UserSetting::CLOSE_TIMEOUT),
			asideText: s("Passé ce délai, vos données seront définitivement supprimées. Pensez à faire une sauvegarde si vous souhaitez les conserver."),
			footer: FALSE
		);

	}

	protected static function getDefault(User $eUser): array {

		if($eUser['firstName'] === NULL) {
			$letters = mb_substr($eUser['lastName'], 0, 2);
		} else {
			$letters = mb_substr($eUser['firstName'], 0, 1).mb_substr($eUser['lastName'], 0, 1);
		}

		return [
			self::getDefaultColor($eUser['id']),
			mb_strtoupper($letters)
		];
	}

	/**
	 * Une couleur de vignette tirée d'un identifiant, toujours la même pour lui.
	 */
	public static function getDefaultColor(int $id): string {

		$colors = [
			'#606ec9',
			'#9dd53a',
			'#f0b7a1',
			'#c4c960',
			'#cc6163',
			'#cb60b3',
			'#b361cc',
			'#60c4c9',
			'#8829fb',
			'#c9a460',
			'#08c08c',
			'#3585de',
			'#b3a6de',
			'#cea17f'
		];

		return $colors[crc32($id) % count($colors)];

	}

	public static function p(string $property): \PropertyDescriber {

		$d = User::model()->describer($property, [
			'type' => s("Je suis"),
			'invoiceCountry' => s("Pays"),
			'deliveryCountry' => s("Pays"),
			'email' => s("Adresse e-mail"),
			'phone' => s("Numéro de téléphone"),
			'lastName' => s("Nom"),
			'firstName' => s("Prénom"),
			'legalName' => s("Nom de la société"),
			'siret' => s("SIRET"),
			'invoiceStreet' => s("Adresse"),
			'invoicePostcode' => s("Code postal"),
			'invoiceCity' => s("Ville"),
			'deliveryStreet' => s("Adresse"),
			'deliveryPostcode' => s("Code postal"),
			'deliveryCity' => s("Ville"),
		]);

		switch($property) {

			case 'type' :
				$d->values = [
					User::PRIVATE => s("Particulier"),
					User::PRO => s("Professionnel"),
				];
				$d->attributes = [
					'columns' => 2,
					'callbackRadioAttributes' => fn() => [
						'onclick' => 'User.changeCustomerType(this);',
					]
				];
				break;

			case 'siret' :
				\main\PlaceUi::querySiret($d, 'delivery');
				break;

			case 'deliveryCountry' :
				$d->values = fn(User $e) => \user\Country::form();
				$d->attributes = fn(\util\FormUi $form, User $e) => [
					'group' => is_array(\user\Country::form()),
					'onchange' => 'User.changeDeliveryCountry(this)'
				];
				$d->required = TRUE;
				break;

			case 'invoiceCountry' :
				$d->values = fn(User $e) => \user\Country::form();
				$d->attributes = fn(\util\FormUi $form, User $e) => [
					'group' => is_array(\user\Country::form()),
				];
				$d->required = TRUE;
				break;

			case 'firstName' :
			case 'lastName' :
			case 'email' :
				$d->placeholder = $d->label;
				break;

			case 'phone' :
				$d->placeholder = $d->label;
				$d->prepend = \Asset::icon('telephone');
				break;

		}

		return $d;

	}

}
?>
