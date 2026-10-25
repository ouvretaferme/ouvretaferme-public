<?php
namespace structure;

class InviteUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('structure', 'invite.css');
		\Asset::js('structure', 'inviteAi.js');

	}

	public function check(Invite $eInvite): string {

		if($eInvite->empty()) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';
				$h .= '<h2>'.s("Cette invitation a expiré ou n'est pas reconnue !").'</h2>';
				$h .= '<h4>'.s("Veuillez demander un nouveau lien.").'</h4>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		return match($eInvite['type']) {
			Invite::CUSTOMER => $this->checkCustomer($eInvite),
			Invite::MEMBER => $this->checkMember($eInvite),
			Invite::PRODUCER => $this->checkProducer($eInvite)
		};

	}

	public function checkCustomer(Invite $eInvite): string {

		$eStructure = $eInvite['structure'];

		if($eInvite->isValid() === FALSE) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';
				$h .= '<h2>'.($eStructure->isAmap() ? s("Le lien que vous avez utilisé pour activer votre compte a expiré !") : s("Le lien que vous avez utilisé pour activer votre compte client a expiré !")).'</h2>';
				$h .= '<h4>'.s("Merci de demander à {structure} de vous envoyer un nouveau lien.", ['structure' => '<b>'.encode($eStructure['name']).'</b>']).'</h4>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';
				$h .= '<h2>'.s("Vous n'êtes pas connecté sur {siteName} !").'</h2>';

				if($eInvite['email'] !== NULL) {

					$arguments = ['structure' => '<b>'.encode($eStructure['name']).'</b>', 'emailCurrent' => '<b>'.encode($eInvite['email']).'</b>'];

					if($eStructure->isAmap()) {
						$h .= '<h4>'.s("Pour rejoindre {structure} comme adhérent, connectez-vous sur {siteName} avec l'adresse e-mail {emailCurrent}, ou créez un compte si vous n'en avez pas.", $arguments).'</h4>';
					} else {
						$h .= '<h4>'.s("Pour créer votre compte client {structure}, veuillez vous connecter sur {siteName} avec l'adresse e-mail {emailCurrent} ou créer un compte si vous n'en disposez pas.", $arguments).'</h4>';
					}

				} else {

					$arguments = ['structure' => '<b>'.encode($eStructure['name']).'</b>'];

					if($eStructure->isAmap()) {
						$h .= '<h4>'.s("Pour rejoindre {structure} comme adhérent, connectez-vous sur {siteName}, ou créez un compte si vous n'en avez pas.", $arguments).'</h4>';
					} else {
						$h .= '<h4>'.s("Pour créer votre compte client {structure}, veuillez vous connecter sur {siteName} ou créer un compte si vous n'en disposez pas.", $arguments).'</h4>';
					}

				}

				$h .= '<div>';
					$h .= '<a href="/inscription?invite='.$eInvite['key'].'" class="btn btn-xl btn-secondary">'.s("Créer un compte").'</a> ';
					$h .= '<a href="/user/log:form?invite='.$eInvite['key'].'" class="btn btn-xl btn-outline-primary">'.s("Me connecter").'</a>';
				$h .= '</div>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		if($eInvite->acceptEmail($eUser['email']) === FALSE) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';

				$arguments = ['emailExpected' => '<b>'.encode($eInvite['email']).'</b>', 'emailCurrent' => encode($eUser['email'])];

				if($eStructure->isAmap()) {
					$h .= '<h2>'.s("Vous devez être connecté sur {siteName} avec {emailExpected} pour activer votre compte ! Vous êtes actuellement connecté avec l'adresse e-mail {emailCurrent}.", $arguments).'</h2>';
				} else {
					$h .= '<h2>'.s("Vous devez être connecté sur {siteName} avec {emailExpected} pour activer votre compte client ! Vous êtes actuellement connecté avec l'adresse e-mail {emailCurrent}.", $arguments).'</h2>';
				}

				$h .= '<h4>'.s("Merci de vous déconnecter de {siteName} et de vous reconnecter avec la bonne adresse e-mail, ou bien de demander à {structure} de vous envoyer un lien sur {emailCurrent}.", ['structure' => '<b>'.encode($eStructure['name']).'</b>', 'emailCurrent' => encode($eUser['email'])]).'</h4>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		return $this->checkDefault($eInvite);

	}

	protected function getExpired(): string {

		$h = '<div class="util-block-empty">';
			$h .= '<h3>'.s("Cette invitation a expiré").'</h3>';
			$h .= '<p>'.s("Demandez-en une nouvelle à la structure qui vous l'a envoyée.").'</p>';
		$h .= '</div>';

		return $h;

	}

	protected function getAmapClosed(): string {

		$h = '<div class="util-block-empty">';
			$h .= '<h3>'.s("Le collectif est fermé").'</h3>';
			$h .= '<p>'.s("Cette invitation ne peut plus être acceptée.").'</p>';
		$h .= '</div>';

		return $h;

	}

	protected function getUsed(): string {

		$h = '<div class="util-block-empty">';
			$h .= '<h3>'.s("Cette invitation a déjà servi").'</h3>';
			$h .= '<p>'.s("Si vous venez de l'accepter, il n'y a plus rien à faire. Sinon, demandez-en une nouvelle à la structure qui vous l'a envoyée.").'</p>';
		$h .= '</div>';

		return $h;

	}

	protected function getNotConnected(Invite $eInvite, string $text): string {

		$h = '<div class="util-block text-center">';
			$h .= '<br/><br/>';
			$h .= '<h2>'.s("Vous n'êtes pas connecté sur {siteName} !").'</h2>';
			$h .= '<h4>'.$text.'</h4>';

			$h .= '<div>';
				$h .= '<a href="/inscription?invite='.$eInvite['key'].'" class="btn btn-xl btn-secondary">'.s("Créer un compte").'</a> ';
				$h .= '<a href="/user/log:form?invite='.$eInvite['key'].'" class="btn btn-xl btn-outline-primary">'.s("Me connecter").'</a>';
			$h .= '</div>';
			$h .= '<br/><br/>';
		$h .= '</div>';

		return $h;

	}

	public function checkMember(Invite $eInvite): string {

		$eStructure = $eInvite['structure'];

		// Une invitation épuisée reste à la même adresse : qui la rouvre, souvent celui qui vient de
		// l'accepter, apprend qu'elle a servi et non qu'elle a expiré.
		if(
			$eInvite['status'] === Invite::PENDING and
			$eInvite->acceptExpired() and
			$eInvite->acceptLimit() === FALSE
		) {
			return $this->getUsed();
		}

		if(
			$eInvite->isValid() === FALSE or
			$eInvite->acceptExpired() === FALSE
		) {
			return $this->getExpired();
		}

		if($eInvite->acceptAmapActive() === FALSE) {
			return $this->getAmapClosed();
		}

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {

			$arguments = [
				'structure' => '<b>'.encode($eStructure['name']).'</b>',
				'emailCurrent' => '<b>'.encode($eInvite['email']).'</b>',
				'type' => StructureUi::getText($eStructure, 'the')
			];

			$text = ($eInvite['email'] !== NULL) ?
				s("Pour rejoindre {type} {structure}, veuillez vous connecter sur {siteName} avec l'adresse e-mail {emailCurrent} ou créer un compte si vous n'en disposez pas.", $arguments) :
				s("Pour rejoindre {type} {structure}, veuillez vous connecter sur {siteName} ou créer un compte si vous n'en disposez pas.", $arguments);

			return $this->getNotConnected($eInvite, $text);

		}

		if($eInvite->acceptEmail($eUser['email']) === FALSE) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';
				$h .= '<h2>'.s("Vous devez être connecté sur {siteName} avec {emailExpected} pour rejoindre {type} ! Vous êtes actuellement connecté avec l'adresse e-mail {emailCurrent}.", ['emailExpected' => '<b>'.encode($eInvite['email']).'</b>', 'emailCurrent' => encode($eUser['email']), 'type' => StructureUi::getText($eStructure, 'the').' '.encode($eStructure['name'])]).'</h2>';
				$h .= '<h4>'.s("Merci de vous déconnecter de {siteName} et de vous reconnecter avec la bonne adresse e-mail, ou bien de demander à {structure} de vous envoyer un lien sur {emailCurrent}.", ['structure' => '<b>'.encode($eStructure['name']).'</b>', 'emailCurrent' => encode($eUser['email'])]).'</h4>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		if($eInvite->isAmapProducer()) {
			return self::notify('getInviteAmapProducer', $eInvite)[0];
		}

		return $this->checkDefault($eInvite);

	}

	protected function getShare(\util\FormUi $form, Invite $eInvite): string {

		$cStructure = $eInvite['cStructureShareable'] ?? new \Collection();

		if($cStructure->empty()) {

			if($eInvite['hasProducer'] ?? FALSE) {
				return '<div class="util-block-info"><p>'.s("Vous rejoignez ce groupe sans partager de ferme. Seul un administrateur peut partager une ferme, et vous n'en administrez aucune.").'</p></div>';
			}

			return '';

		}

		$eStructure = $eInvite['structure'];
		$class = StructureUi::getClass($eStructure);

		$eStructureShared = $this->getShareSingle($eInvite);

		if($eStructureShared->notEmpty()) {

			$h = $form->hidden('structureProducer', $eStructureShared);

			if($eInvite['eSystemPlace']->notEmpty()) {
				$h .= $this->getPlace($form, $eStructure, $eInvite['eSystemPlace'], FALSE);
			}

		} else {

			$values = [];

			foreach($cStructure as $eStructureShareable) {

				$label = '<b>'.encode($eStructureShareable['name']).'</b>';

				if($eStructureShareable['legalCity']) {
					$label .= '<br/><span class="util-annotation">'.encode($eStructureShareable['legalCity']).'</span>';
				}

				$values[$eStructureShareable['id']] = $label;

			}

			if($eStructure->acceptJoinWithoutFarm()) {
				$selected = ($cStructure->count() === 1) ? $cStructure->first() : NULL;
				$values[0] = s("Je ne souhaite pas partager de ferme pour le moment");
				$onrender = 'StructureInvite.watchProducerType(this); StructureInvite.watchPlace(this); StructureInvite.watchRights(this);';
			} else {
				$selected = NULL;
				$onrender = 'StructureInvite.watchProducerType(this); StructureInvite.watchPlace(this);';
			}

			$h = $form->group(
				s("Quelle ferme partagez-vous avec ce groupe ?"),
				$form->radios('structureProducer', $values, $selected, [
					'required' => TRUE,
					'onrender' => $onrender,
					// La catégorie de chaque ferme voyage sur son bouton : une seule question est
					// posée pour toutes, et c'est la ferme cochée qui dit ce qu'elle doit montrer.
					// Un bouton sans attribut masque les deux questions, ce que veut l'option sans ferme.
					'callbackRadioAttributes' => fn($option, $key) => ($key === 0) ? [] : (
						($cStructure[$key]->acceptUpdateProducerType() ? ['data-producer-type' => $cStructure[$key]['producerType']] : []) +
						($cStructure[$key]['isPlaced'] ? [] : ['data-place-missing' => '1'])
					)
				])
			);

			// Un seul champ pour toutes les fermes : l'autocomplétion ne lit de la ferme que le droit
			// de la modifier, et le serveur écrit le lieu sur la ferme cochée.
			if($eInvite['eSystemPlace']->notEmpty()) {
				$h .= $this->getPlace($form, $eStructure, $eInvite['eSystemPlace'], TRUE);
			}

		}

		$h .= $class::getProducerTypeChoice($form, $cStructure);

		// Le même bloc qu'au partage d'une ferme : c'est le même moment et le même engagement, et
		// deux rendus divergeraient au premier changement de libellé.
		$rules = $class::getCharterRules($eStructure, $eInvite['eCommunity']);

		$h .= '<div data-rights-question>';
			$h .= '<h3 class="mt-2">'.s("Les autorisations d'accès aux données de votre ferme").'</h3>';
			$h .= $class::getRightsChoice($form, $eStructure, $eInvite['audienceBringing']);
		$h .= '</div>';

		if($rules !== '') {

			$h .= $rules;

			$h .= $form->group(
				content: $form->checkbox('charter', 1, ['callbackLabel' => fn($input) => $input.' <b>'.s("J'accepte ces règles").'</b>'])
			);

		}

		return $h;

	}

	protected function getShareSingle(Invite $eInvite): Structure {

		$cStructure = $eInvite['cStructureShareable'];

		if(
			$cStructure->count() === 1 and
			$eInvite['structure']->acceptJoinWithoutFarm() === FALSE
		) {
			return $cStructure->first();
		}

		return new Structure();

	}

	protected function getPlace(\util\FormUi $form, Structure $eStructure, System $eSystem, bool $hidden): string {

		$h = '<div data-place-question'.($hidden ? ' class="hide"' : '').'>';
			$h .= '<h3'.($hidden ? ' class="mt-2"' : '').'>'.s("Où produisez-vous ?").'</h3>';
			$h .= '<p class="color-muted">'.s("{the} situe ses fermes sur une carte. La commune suffit, votre adresse n'est pas demandée.", ['the' => mb_ucfirst(StructureUi::getText($eStructure, 'the'))]).'</p>';
			$h .= $form->dynamicGroups($eSystem, ['place', 'placeLngLat']);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que voit un producteur invité sur une boutique.
	 *
	 * Une invitation Producteur rattache une structure : sans structure à rattacher il n'y a rien à
	 * accepter, et l'invité est d'abord envoyé en créer une. L'invitation l'attend, il la retrouve
	 * sur son accueil.
	 */
	public function checkProducer(Invite $eInvite): string {

		$eStructure = $eInvite['structure'];
		$eShop = $eInvite['eShop'];

		if(
			$eInvite->isValid() === FALSE or
			$eInvite->acceptExpired() === FALSE
		) {
			return $this->getExpired();
		}

		$eUser = $eInvite['eUser'];

		if($eUser->empty()) {

			$arguments = [
				'owner' => '<b>'.encode($eStructure['name']).'</b>',
				'shop' => '<b>'.encode($eShop['name']).'</b>',
				'emailCurrent' => '<b>'.encode($eInvite['email']).'</b>'
			];

			$text = ($eInvite['email'] !== NULL) ?
				s("Pour vendre sur la boutique {shop} de {owner}, veuillez vous connecter sur {siteName} avec l'adresse e-mail {emailCurrent} ou créer un compte si vous n'en disposez pas.", $arguments) :
				s("Pour vendre sur la boutique {shop} de {owner}, veuillez vous connecter sur {siteName} ou créer un compte si vous n'en disposez pas.", $arguments);

			return $this->getNotConnected($eInvite, $text);

		}

		if($eInvite->acceptEmail($eUser['email']) === FALSE) {

			$h = '<div class="util-block text-center">';
				$h .= '<br/><br/>';
				$h .= '<h2>'.s("Cette invitation a été envoyée à {emailExpected} !", ['emailExpected' => '<b>'.encode($eInvite['email']).'</b>']).'</h2>';
				$h .= '<h4>'.s("Vous êtes connecté avec {emailCurrent}. Merci de vous reconnecter avec la bonne adresse e-mail, ou de demander à {owner} de vous envoyer un lien sur {emailCurrent}.", ['owner' => '<b>'.encode($eStructure['name']).'</b>', 'emailCurrent' => '<b>'.encode($eUser['email']).'</b>']).'</h4>';
				$h .= '<br/><br/>';
			$h .= '</div>';

			return $h;

		}

		$cStructure = $eInvite['cStructureJoinable'];

		if($cStructure->empty()) {

			$text = $eInvite['hasProducer']
				? s("{owner} vous invite à vendre sur sa boutique {shop}. Vous administrez déjà une structure, mais elle vend déjà sur cette boutique : rien à rattacher ici.", ['owner' => encode($eStructure['name']), 'shop' => encode($eShop['name'])])
				: s("{owner} vous invite à vendre sur sa boutique {shop}. Créez d'abord votre structure : c'est depuis elle que vous y associerez vos catalogues.", ['owner' => encode($eStructure['name']), 'shop' => encode($eShop['name'])]);

			$text .= ' '.s("Vous retrouverez cette invitation sur votre accueil.");

			if($eInvite['expiresAt'] !== NULL) {
				$text .= ' '.s("Elle reste valable jusqu'au {value}.", \util\DateUi::textual($eInvite['expiresAt'], \util\DateUi::DATE));
			}

			$buttons = $eInvite['hasProducer']
				? ''
				: '<a href="/demarrer/ferme" class="btn btn-primary btn-lg">'.s("Créer ma structure").'</a>';

			return \main\DesignUi::getWelcome(
				s("Bienvenue, {value} !", encode($eUser['firstName'])),
				'<p>'.$text.'</p>',
				\Asset::svg('main', 'friends/'.StructureUi::getClass($eStructure)::getWelcomeMascot().'.svg'),
				$buttons
			);

		}

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/invite:doJoin');

			$h .= $form->hidden('key', $eInvite['key']);
			$h .= $form->hidden('do', TRUE);

			$title = s("Vous êtes invité à vendre sur cette boutique");
			$lines = [
				s("Vos produits y seront proposés à côté de ceux de <b>{owner}</b>, et vous préparerez vous-même les commandes qui vous reviennent.", ['owner' => encode($eStructure['name'])])
			];

			if($cStructure->count() === 1) {

				$eStructureProducer = $cStructure->first();

				$lines[] = s("Vous rejoignez la boutique avec <b>{name}</b>.", ['name' => encode($eStructureProducer['name'])]);

				$h .= $form->hidden('structureProducer', $eStructureProducer);
				$h .= self::getCard($eStructure, encode($eShop['name']), $title, $lines, self::getGesture($form, $eInvite, s("Rejoindre la boutique")));

			} else {

				$h .= self::getCard($eStructure, encode($eShop['name']), $title, $lines);
				$h .= '<div class="mt-2">'.$this->getProducerStructure($form, $cStructure).'</div>';
				$h .= self::getGesture($form, $eInvite, s("Rejoindre la boutique"));

			}

		$h .= $form->close();

		return $h;

	}

	public function getProducerText(Invite $eInvite): string {

		return s("{owner} vous invite à vendre sur sa boutique {shop}. Vos produits y seront proposés à côté des siens, et vous préparerez vous-même les commandes qui vous reviennent.", ['owner' => encode($eInvite['structure']['name']), 'shop' => encode($eInvite['eShop']['name'])]);

	}

	protected function getProducerStructure(\util\FormUi $form, \Collection $cStructure): string {

		$values = [];

		foreach($cStructure as $eStructureProducer) {

			$label = '<b>'.encode($eStructureProducer['name']).'</b>';

			if($eStructureProducer['legalCity']) {
				$label .= '<br/><span class="util-annotation">'.encode($eStructureProducer['legalCity']).'</span>';
			}

			$values[$eStructureProducer['id']] = $label;

		}

		return $form->group(
			s("Avec quelle structure rejoignez-vous la boutique ?"),
			$form->radios('structureProducer', $values, NULL, ['required' => TRUE])
		);

	}

	protected function checkDefault(Invite $eInvite): string {

		$eStructure = $eInvite['structure'];

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/invite:doJoin');

			$h .= $form->hidden('key', $eInvite['key']);
			$h .= $form->hidden('do', TRUE);
			$h .= $form->hidden('origin', GET('origin'));

			switch($eInvite['type']) {

				case Invite::MEMBER :

					$title = s("Vous êtes invité à rejoindre {thisStructure}", ['thisStructure' => StructureUi::getText($eStructure, 'this')]);
					$fields = '';

					if($eStructure->isCommunity()) {
						$lines = [s("Vous y serez <b>{role}</b>.", ['role' => encode($eInvite['memberRole']['name'])])];
						$fields = $this->getShare($form, $eInvite);
					} else if($eInvite['memberRole']->empty()) {
						$lines = [];
					} else if($eInvite->isAmapAdmin()) {
						$lines = [s("Vous y serez <b>{role}</b>, pour gérer les adhérents, les adhésions, les producteurs, les distributions et les permanences.", ['role' => encode($eInvite['memberRole']['name'])])];
					} else {
						$lines = [s("Vous y serez <b>{role}</b>.", ['role' => encode($eInvite['memberRole']['name'])])];
					}

					$submit = $eInvite->isAmapAdmin() ? s("Accepter l'invitation") : s("Rejoindre maintenant");
					break;

				case Invite::CUSTOMER :

					// Le bureau d'une AMAP ne sait pas si l'adresse invitée a déjà un compte : l'invitation
					// fait rejoindre l'AMAP avec le compte connecté, sans promettre d'en créer un.
					$title = $eStructure->isAmap() ? s("Vous êtes invité à rejoindre {thisStructure} comme adhérent", ['thisStructure' => StructureUi::getText($eStructure, 'this')]) : s("Vous êtes invité à créer un compte client");
					$lines = [];
					$fields = '';
					$submit = $eStructure->isAmap() ? s("Rejoindre l'AMAP") : s("Créer un compte client");
					break;

			}

			if($fields === '') {

				$h .= self::getCard($eStructure, encode($eStructure['name']), $title, $lines, self::getGesture($form, $eInvite, $submit));
				$h .= $this->getDescription($eStructure, 'mt-2');

			} else {

				$h .= self::getCard($eStructure, encode($eStructure['name']), $title, $lines);

				$eStructureShared = $this->getShareSingle($eInvite);

				$h .= '<div class="util-layout-aside mt-2">';
					$h .= '<div>'.$this->getDescription($eStructure, 'mb-2').$fields.'</div>';
					$h .= '<div class="util-layout-aside-side">';
						$h .= '<div class="util-block">';

							if($eStructureShared->notEmpty()) {
								$h .= '<p>'.s("Vous partagez <b>{name}</b> avec ce groupe.", ['name' => encode($eStructureShared['name'])]).'</p>';
							}

							if($eInvite['tosRequired']) {
								$h .= '<div data-wrapper="tos">'.self::getTos($form).'</div>';
							}

							$h .= '<div class="mt-1">'.self::getCheckButtons($form, $submit).'</div>';

						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= $form->close();

		return $h;

	}

	private function getDescription(Structure $eStructure, string $class): string {

		if($eStructure->isCommunity() === FALSE or $eStructure['description'] === NULL) {
			return '';
		}

		$h = '<div class="util-block '.$class.'">';
			$h .= '<h3>'.StructureUi::getText($eStructure, 'propertyPresentation').'</h3>';
			$h .= new \editor\EditorUi()->value($eStructure['description']);
		$h .= '</div>';

		return $h;

	}

	public static function getCard(Structure $eStructure, string $seed, string $title, array $lines, string $gesture = '', bool $celebrate = FALSE): string {

		$h = '<div class="util-start util-celebrate">';

			if($celebrate) {
				$h .= \main\DesignUi::getCelebrateBits(4);
			}

			$h .= '<div class="util-seed util-seed-torn">';
				$h .= '<div class="util-seed-head">';
					$h .= \Asset::svg('main', 'friends/'.StructureUi::getClass($eStructure)::getWelcomeMascot().'.svg');
					$h .= '<div class="util-seed-name">'.$seed.'</div>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-celebrate-title">'.$title.'</div>';

			foreach($lines as $line) {
				$h .= '<p class="util-celebrate-lines">'.$line.'</p>';
			}

			$h .= $gesture;

		$h .= '</div>';

		return $h;

	}

	public static function getGesture(\util\FormUi $form, Invite $eInvite, string $submit): string {

		if($eInvite['tosRequired']) {
			$h = '<div class="mt-2" data-wrapper="tos">'.self::getTos($form).'</div>';
			$h .= '<div class="mt-1">'.self::getCheckButtons($form, $submit).'</div>';
		} else {
			$h = '<div class="mt-2">'.self::getCheckButtons($form, $submit).'</div>';
		}

		return $h;

	}

	public static function getTos(\util\FormUi $form): string {

		return $form->checkbox('tos', 1, ['callbackLabel' => fn($input) => $input.' <span>'.\user\UserUi::getTosLabel().'</span>']);

	}

	protected static function getCheckButtons(\util\FormUi $form, string $submit): string {

		return $form->submit($submit, ['class' => 'btn btn-lg btn-primary', 'data-waiter' => s("En cours...")]).' '.self::getCancel();

	}

	public static function getCancel(): string {

		return '<a href="/structure/invite:join" class="btn btn-lg btn-outline-primary">'.s("Annuler").'</a>';

	}

	public function accept(Invite $eInvite, Structure $eStructureProducer = new Structure()): string {

		$eStructure = $eInvite['structure'];

		// L'accueil d'un producteur ou d'un adhérent qui vient de rejoindre une AMAP est celui de l'AMAP
		if(StructureSetting::hasCommercialisationApp()) {

			$welcome = self::notify('getInviteWelcome', $eInvite)[0];

			if($welcome !== NULL) {
				return $welcome;
			}

		}

		return match($eInvite['type']) {

			Invite::CUSTOMER => self::getCard(
				$eStructure,
				encode($eStructure['name']),
				s("Votre compte client est activé !"),
				[s("Vous avez désormais accès à l'ensemble des fonctionnalités.")],
				'<a href="/" class="btn btn-primary btn-lg">'.s("Consulter mon compte client").'</a>',
				celebrate: TRUE
			),

			Invite::MEMBER => self::getCard(
				$eStructure,
				encode($eStructure['name']),
				s("Vous avez rejoint {thisStructure} !", ['thisStructure' => StructureUi::getText($eStructure, 'this')]),
				[s("Ce que vous pouvez y faire dépend du rôle que l'équipe vous a donné.")],
				'<a href="'.($eStructure->isCommunity() ? StructureUi::url($eStructure) : '/').'" class="btn btn-primary btn-lg">'.s("Découvrir {value}", ['value' => StructureUi::getText($eStructure, 'the')]).'</a>',
				celebrate: TRUE
			),

			Invite::PRODUCER => $this->acceptProducer($eInvite, $eStructureProducer),

		};

	}

	/**
	 * Le seul geste qui compte après l'acceptation : associer un catalogue. Sans lui, la structure
	 * participe à la boutique sans rien y proposer.
	 */
	protected function acceptProducer(Invite $eInvite, Structure $eStructureProducer): string {

		$eShop = $eInvite['eShop'];

		return self::getCard(
			$eInvite['structure'],
			encode($eShop['name']),
			s("Vous vendez désormais sur cette boutique !"),
			[s("Associez un catalogue de <b>{structure}</b> pour y proposer vos produits.", ['structure' => encode($eStructureProducer['name'])])],
			'<a href="/shop/range:create?structure='.$eStructureProducer['id'].'&shop='.$eShop['id'].'" class="btn btn-primary btn-lg">'.s("Associer un catalogue").'</a>',
			celebrate: TRUE
		);

	}

	public function join(): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/invitation', ['method' => 'get']);

			$h .= '<div class="util-info">';
				$h .= s("Si vous avez été invité à rejoindre une ferme ou un groupe, saisissez ci-dessous le code d'invitation que vous avez reçu !");
			$h .= '</div>';

			$h .= $form->inputGroup(
				$form->addon(s("Code d'invitation")).
				$form->text('key').
				$form->submit(s("Valider"))
			);

		$h .= $form->close();

		return $h;

	}

	public function createMember(Invite $eInvite): \Panel {

		$eStructure = $eInvite['structure'];

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/structure/invite:doCreateMember', ['id' => 'invite-create']);

			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('origin', GET('origin'));

			$eRole = $eInvite['memberRole'];
			$isAmapRoleImposed = $eInvite->isAmapRoleImposed();
			$isAdminImposed = $eInvite->isAmapAdmin();
			$eMember = $eInvite['member'];

			// Une personne sans compte s'invite à son adresse, et l'invitation la rattache : ce qui se
			// passe à l'acceptation ne se voit nulle part ailleurs.
			if($eMember->notEmpty()) {

				$h .= $form->hidden('member', $eMember);

				$h .= $form->group(
					s("Personne"),
					'<b>'.encode($eMember['user']->getName()).'</b>',
					['data-wrapper' => 'member']
				);

				$h .= $form->group(content: '<div class="util-block-info">'.s("En acceptant l'invitation, {value} retrouvera avec son compte son temps de travail, ses présences, ses absences et ses interventions.", encode($eMember['user']->getName())).'</div>');

			} else if($eInvite['eMemberRevoked']->notEmpty()) {

				$h .= $form->group(
					s("Personne"),
					'<b>'.encode($eInvite['eMemberRevoked']['user']->getName()).'</b>'
				);

			} else if($isAdminImposed === FALSE) {

				// L'administrateur d'une AMAP s'invite à une adresse, sans lien à partager ni réglage à
				// vérifier : l'encart ne lui dirait rien de ce qu'il fait.

				$description = '<div class="util-block-info">';
					if($isAmapRoleImposed and $eRole->isProducer()) {
						$description .= '<p>'.s("Vous allez créer un lien pour inviter un producteur à livrer l'AMAP. Il acceptera au nom de l'une de ses structures.").'</p>';
					} else {
						$description .= '<p>'.s("Vous allez créer un lien d'invitation que vous pourrez partager avec la ou les personnes que vous souhaitez inviter dans {value}.", StructureUi::getText($eStructure, 'your')).'</p>';
					}
					$description .= '<p>'.s("Avant de créer l'invitation, nous vous conseillons de vérifier rigoureusement son paramétrage afin de n'inviter que des tiers de confiance.").'</p>';
				$description .= '</div>';

				$h .= $form->group(content: $description);

			}

			if($isAmapRoleImposed) {
				$h .= $form->hidden('memberRole', $eRole);
				$h .= $form->group(
					s("Rôle"),
					'<b>'.encode($eRole['name']).'</b>',
					['data-wrapper' => 'memberRole']
				);
			} else {
				$h .= $form->dynamicGroup($eInvite, 'memberRole');
			}

			if($eInvite['cSection']->notEmpty()) {
				$h .= $form->dynamicGroup($eInvite, 'memberSection');
			}

			$h .= $form->dynamicGroup($eInvite, 'expiresAt');
			$h .= $form->dynamicGroup($eInvite, ($isAdminImposed or $eInvite->designatesMember()) ? 'email*' : 'email');

			// Une invitation à une adresse ne sert qu'une fois (`limit.email`) : le choix n'en serait pas un.
			if(
				$isAdminImposed === FALSE and
				$eMember->empty()
			) {
				$h .= $form->dynamicGroup($eInvite, 'limit');
			}

			$h .= $form->group(
				content: $form->submit(s("Créer l'invitation"))
			);

		$h .= $form->close();

		$title = $isAmapRoleImposed
			? ($eRole['admin'] ? s("Inviter un administrateur") : s("Inviter un producteur"))
			: s("Nouvelle invitation");

		return new \Panel(
			id: 'panel-customer-invite',
			title: $title,
			subTitle: StructureUi::getPanelHeader($eStructure),
			body: $h
		);

	}

	/**
	 * Une personne sans compte invitée le dit sur sa ligne d'équipe et sur sa fiche, à la place du bouton
	 * qui l'inviterait une seconde fois.
	 */
	public static function getSentToMember(Invite $eInvite): string {

		return '<span class="badge badge-outline badge-info">'.s("Invitation envoyée le {value}", \util\DateUi::numeric($eInvite['createdAt'], \util\DateUi::DATE)).'</span>';

	}

	public function updateRole(Invite $eInvite): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/invite:doUpdateRole', ['id' => 'invite-update-role']);

			$h .= $form->hidden('id', $eInvite);

			$h .= $form->dynamicGroup($eInvite, 'memberRole');

			if($eInvite['cSection']->notEmpty()) {
				$h .= $form->dynamicGroup($eInvite, 'memberSection');
			}

			$h .= $form->group(
				content: $form->submit(s("Modifier le rôle"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-invite-update-role',
			title: s("Modifier le rôle"),
			subTitle: StructureUi::getPanelHeader($eInvite['structure']),
			body: $h
		);

	}

	/**
	 * Les invitations qui attendent la réponse de l'utilisateur, sur son accueil. `$decks` porte les
	 * cartes que le code fermé ajoute à la même grille (`main\HomeUi::getInvites()`), rendues par
	 * `getPendingDeck()` pour se lire comme les autres.
	 */
	public function getPending(\Collection $cInvite, string $decks = ''): string {

		$h = '<h2>'.s("Invitations en attente").'</h2>';

		if($cInvite->empty() and $decks === '') {

			$h .= '<div class="util-empty">'.s("Vous n'avez aucune invitation en attente.").'</div>';

			return $h;

		}

		\Asset::css('structure', 'structure.css');

		$h .= '<div class="member-structures util-cards util-cards-3">';

			foreach($cInvite as $eInvite) {

				$eStructure = $eInvite['structure'];

				$body = '<p>'.self::getPendingAs($eInvite).'</p>';

				if($eStructure['legalCity']) {
					$body .= '<p class="color-muted">'.\Asset::icon('geo-fill').' '.encode($eStructure['legalCity']).'</p>';
				}

				$doors = '<a href="/invitation?key='.$eInvite['key'].'&direct" class="btn btn-sm btn-primary">'.s("Accepter l'invitation").'</a>';
				$doors .= '<a data-ajax="/structure/invite:doDelete" post-id="'.$eInvite['id'].'" class="btn btn-sm btn-outline-primary" data-confirm="'.s("Refuser définitivement cette invitation ?").'">'.s("Refuser").'</a>';

				$h .= $this->getPendingDeck($eStructure, $body, $doors);

			}

			$h .= $decks;

		$h .= '</div>';

		return $h;

	}

	/**
	 * À quel titre la structure invite, dans les mots de la page où mène « Accepter l'invitation »
	 * (`checkDefault()`, `checkProducer()`) : le rôle du membre quand l'invitation en porte un,
	 * adhérent pour la fiche client d'une AMAP, client ailleurs, producteur sur une boutique.
	 */
	public static function getPendingAs(Invite $eInvite): string {

		return match($eInvite['type']) {
			Invite::MEMBER => $eInvite['memberRole']->empty()
				? s("Invité comme membre")
				: s("Invité comme {role}", ['role' => encode(mb_strtolower($eInvite['memberRole']['name']))]),
			Invite::CUSTOMER => $eInvite['structure']->isAmap()
				? s("Invité comme adhérent")
				: s("Invité comme client"),
			Invite::PRODUCER => s("Invité comme producteur sur la boutique {shop}", ['shop' => encode($eInvite['eShop']['name'])])
		};

	}

	/** Une carte de la grille des invitations : la structure qui invite, ce qu'elle propose, et deux portes. */
	public function getPendingDeck(Structure $eStructure, string $body, string $doors): string {

		$h = '<div class="util-deck">';

			$h .= '<div class="util-deck-head">';

				$h .= StructureUi::getLogo($eStructure, '4rem');

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.encode($eStructure['name']).'</div>';
					$h .= $body;
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-2">';
				$h .= $doors;
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * `$section` nomme la colonne des sous-groupes, là où la structure en a : c'est la même colonne
	 * que la table des membres rendue en dessous.
	 */
	public function getManage(\Collection $cInvite, ?string $section = NULL): string {

		$h = '<div class="util-overflow-md mb-2">';
			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Invitation").'</th>';
						$h .= '<th>'.s("Rôle").'</th>';
						if($section !== NULL) {
							$h .= '<th>'.$section.'</th>';
						}
						$h .= '<th>'.s("Envoyée le").'</th>';
						$h .= '<th>'.s("Expire le").'</th>';
						$h .= '<th class="text-end">'.s("Utilisations").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					foreach($cInvite as $eInvite) {

						$h .= '<tr>';
							$h .= '<td>';
								$h .= $this->getCode($eInvite);
								if($eInvite->acceptLimit() === FALSE) {
									$h .= ' <span class="badge badge-success">'.s("Terminé").'</span>';
								}
								if($eInvite['email'] !== NULL) {
									$h .= '<div class="util-annotation">'.encode($eInvite['email']).'</div>';
								}
							$h .= '</td>';
							$h .= '<td>';
								if($eInvite['type'] === Invite::PRODUCER) {
									$h .= s("Producteur");
								} else {
									$h .= encode($eInvite['memberRole']['name']);
								}
							$h .= '</td>';
							if($section !== NULL) {

								// Une invitation en attente dit déjà où elle fera entrer : c'est
								// la seule occasion de s'en apercevoir avant qu'elle soit acceptée.
								$h .= '<td>';
									if($eInvite['memberSection']->notEmpty()) {
										$h .= '<span class="badge badge-outline">'.encode($eInvite['memberSection']['name']).'</span>';
									} else {
										$h .= '<span class="color-muted">—</span>';
									}
								$h .= '</td>';

							}
							$h .= '<td>';
								$h .= '<span class="util-annotation">'.\util\DateUi::numeric($eInvite['createdAt'], \util\DateUi::DATE).'</span>';
							$h .= '</td>';
							$h .= '<td>';
								if($eInvite['expiresAt'] !== NULL) {
									$h .= '<span class="util-annotation">'.\util\DateUi::numeric($eInvite['expiresAt']).'</span>';
								} else {
									$h .= '<span class="color-muted">—</span>';
								}
							$h .= '</td>';
							$h .= '<td class="text-end">';
								if($eInvite['limit'] !== NULL) {
									$h .= s("{used} sur {limit}", ['used' => $eInvite['used'], 'limit' => $eInvite['limit']]);
								} else {
									$h .= $eInvite['used'];
								}
							$h .= '</td>';
							$h .= '<td class="text-end">';
								$h .= $this->getActions($eInvite);
							$h .= '</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le code d'une invitation, qui se copie d'un clic.
	 */
	public function getCode(Invite $eInvite): string {
		return '<pre onclick="doCopy(this)" data-selector="#invite-code-'.$eInvite['id'].'" id="invite-code-'.$eInvite['id'].'" data-message="'.s("Code copié !").'" class="invite-code">'.encode($eInvite['key']).'</pre>';
	}

	/**
	 * La roue d'une invitation, avec le lien qu'elle copie : la même dans la liste des invitations et dans le groupe
	 * « Invitations en attente » de l'onglet Personnes.
	 */
	public function getActions(Invite $eInvite): string {

		if($eInvite->assertUpdate() === FALSE) {
			return '';
		}

		$h = '<div class="hide" id="invite-link-'.$eInvite['id'].'">'.$eInvite->getLink().'</div>';

		$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Invitation &laquo; {value} &raquo;", $eInvite['key']).'</div>';
			$h .= ' <a onclick="doCopy(this)" data-selector="#invite-link-'.$eInvite['id'].'" data-message="'.s("Lien copié !").'" class="dropdown-item">'.s("Copier un lien d'invitation").'</a>';
			if($eInvite->acceptUpdateRole()) {
				$h .= ' <a href="/structure/invite:updateRole?id='.$eInvite['id'].'" class="dropdown-item">'.s("Modifier le rôle").'</a>';
			}
			if($eInvite['limit'] === NULL or $eInvite['limit'] > 1) {
				$h .= '<div class="dropdown-divider"></div>';
				if($eInvite['notifyEmail']) {
					$h .= '<div class="dropdown-subtitle">'.\Asset::icon('check-circle-fill').'  '.s("Notifications par e-mail activées").'</div>';
					$h .= ' <a data-ajax="/structure/invite:doUpdateNotifyEmail" post-id="'.$eInvite['id'].'" post-notify-email="0" class="dropdown-item">'.s("Ne plus recevoir d'e-mail lorsque cette invitation est utilisée").'</a>';
				} else {
					$h .= '<div class="dropdown-subtitle">'.\Asset::icon('x-circle-fill').'  '.s("Notifications par e-mail désactivées").'</div>';
					$h .= ' <a data-ajax="/structure/invite:doUpdateNotifyEmail" post-id="'.$eInvite['id'].'" post-notify-email="1" class="dropdown-item">'.s("Recevoir un e-mail lorsque cette invitation est utilisée").'</a>';
				}
			}
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/structure/invite:doDelete" post-id="'.$eInvite['id'].'" class="dropdown-item" data-confirm="'.s("Voulez-vous réellement supprimer cette invitation ?").'">'.s("Supprimer l'invitation").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Invitation à créer son compte
	 */
	public static function getInviteMail(Invite $e): array {

		return match($e['type']) {
			Invite::CUSTOMER => self::getInviteCustomerMail($e),
			Invite::MEMBER => self::getInviteMemberMail($e),
			Invite::PRODUCER => self::getInviteProducerMail($e)
		};

	}

	public static function getInviteCustomerMail(Invite $e): array {

		if($e['structure']->isAmap()) {
			return self::getInviteAmapCustomerMail($e);
		}

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("{siteName} est le logiciel de vente qu'utilise votre producteur. Votre compte client vous permet de retrouver vos commandes et vos documents au même endroit.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{value} vous invite à créer votre compte client", $e['structure']['name']),
			$content,
			heroTitle: s("{value} vous invite à créer votre compte client sur {siteName}", encode($e['structure']['name'])),
			buttonLabel: s("Activer mon compte client"),
			buttonUrl: $e->getLink(),
			footer: FALSE
		);

	}

	public static function getInviteAmapCustomerMail(Invite $e): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("{siteName} est le logiciel qu'utilise votre AMAP. En la rejoignant, vous y retrouvez les points de retrait et les producteurs de l'AMAP, et vous tenez vos coordonnées à jour.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{value} vous invite à la rejoindre", $e['structure']['name']),
			$content,
			heroTitle: s("{value} vous invite à la rejoindre sur {siteName}", encode($e['structure']['name'])),
			buttonLabel: s("Rejoindre l'AMAP"),
			buttonUrl: $e->getLink(),
			footer: FALSE
		);

	}

	public static function getInviteMemberMail(Invite $e): array {

		if($e->isAmapProducer()) {
			return self::getInviteAmapProducerMail($e);
		}

		if($e['structure']->isCommunity()) {
			return self::getInviteCommunityMail($e);
		}

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("{siteName} est le logiciel qui permet aux producteurs et productrices de gérer toute leur ferme au même endroit : les cultures, les ventes, la facturation et la comptabilité.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{value} vous invite à rejoindre son équipe", $e['structure']['name']),
			$content,
			heroTitle: s("{value} vous invite à rejoindre son équipe", encode($e['structure']['name'])),
			buttonLabel: s("Activer mon compte"),
			buttonUrl: $e->getLink()
		);

	}

	/**
	 * L'invitation à un groupe nomme le groupe et son type, le rôle reçu et la présentation. La
	 * présentation vient après le bouton : saisie par le groupe, elle peut être longue, et le geste
	 * attendu de l'invité ne doit pas descendre sous elle.
	 */
	public static function getInviteCommunityMail(Invite $e): array {

		$eStructure = $e['structure'];

		$the = StructureUi::getText($eStructure, 'the');

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("{siteName} est le logiciel qu'utilise <b>{name}</b>.", ['name' => encode($eStructure['name'])]).'</p>';

		if($e['expiresAt'] !== NULL) {
			$content .= '<p>'.s("L'invitation reste valable jusqu'au {value}.", \util\DateUi::textual($e['expiresAt'], \util\DateUi::DATE)).'</p>';
		}

		if($eStructure['description'] === NULL) {
			$after = NULL;
		} else {
			$after = '<p><b>'.StructureUi::getText($eStructure, 'propertyPresentation').'</b></p>';
			$after .= new \editor\ReadorFormatterUi()->getFromXml($eStructure['description'], ['isEmail' => TRUE]);
		}

		return \main\MailSoftwareAiUi::format(
			s("{name} vous invite à rejoindre {the}", ['name' => $eStructure['name'], 'the' => $the]),
			$content,
			heroTitle: s("{name} vous invite à rejoindre {the}", ['name' => encode($eStructure['name']), 'the' => $the]),
			heroText: $e['memberRole']->empty() ? NULL : s("Vous y serez <b>{role}</b>.", ['role' => encode($e['memberRole']['name'])]),
			buttonLabel: s("Voir l'invitation"),
			buttonUrl: $e->getLink(),
			contentAfter: $after
		);

	}

	public static function getInviteAmapProducerMail(Invite $e): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("{siteName} est le logiciel qu'utilise <b>{amap}</b>. En acceptant l'invitation, vous y serez <b>{role}</b> et vous proposerez vos contrats à ses adhérents depuis votre structure.", ['amap' => encode($e['structure']['name']), 'role' => encode(mb_strtolower($e['memberRole']['name']))]).'</p>';

		if($e['expiresAt'] !== NULL) {
			$content .= '<p>'.s("L'invitation reste valable jusqu'au {value}.", \util\DateUi::textual($e['expiresAt'], \util\DateUi::DATE)).'</p>';
		}

		return \main\MailSoftwareAiUi::format(
			s("{value} vous invite à la livrer", $e['structure']['name']),
			$content,
			heroTitle: s("{value} vous invite à la livrer sur {siteName}", encode($e['structure']['name'])),
			buttonLabel: s("Voir l'invitation"),
			buttonUrl: $e->getLink()
		);

	}

	public static function getInviteProducerMail(Invite $e): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("<b>{owner}</b> vous invite à vendre vos produits sur sa boutique en ligne <b>{shop}</b>.", ['owner' => encode($e['structure']['name']), 'shop' => encode($e['eShop']['name'])]).'</p>';
		$content .= '<p>'.s("Vous y associerez vos catalogues, et vous préparerez vous-même les commandes qui vous reviennent.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{owner} vous invite sur la boutique {shop}", ['owner' => $e['structure']['name'], 'shop' => $e['eShop']['name']]),
			$content,
			heroTitle: s("{value} vous invite sur sa boutique", encode($e['structure']['name'])),
			buttonLabel: s("Voir l'invitation"),
			buttonUrl: $e->getLink()
		);

	}

	public static function getNewProducerMail(Invite $e, Structure $eStructureProducer): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("Un nouveau producteur a rejoint votre boutique <b>{shop}</b>.", ['shop' => encode($e['eShop']['name'])]).'</p>';
		$content .= '<p>'.s("Il lui reste à associer un catalogue pour que ses produits apparaissent sur vos livraisons.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{structure} a rejoint la boutique {shop}", ['structure' => $eStructureProducer['name'], 'shop' => $e['eShop']['name']]),
			$content,
			facts: [
				s("Structure") => encode($eStructureProducer['name']),
				s("Boutique") => encode($e['eShop']['name']),
			],
			footer: FALSE
		);

	}

	/**
	 * Invitation à créer son compte
	 */
	public static function getAcceptCustomerMail(Invite $e, \user\User $eUser): array {

		// L'adhérent d'une AMAP reçoit le courriel de l'AMAP, qui le mène à son accueil
		if(StructureSetting::hasCommercialisationApp()) {

			$mail = self::notify('getInviteAcceptCustomerMail', $e)[0];

			if($mail !== NULL) {
				return $mail;
			}

		}

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("Vous avez accepté l'invitation à créer votre compte client <b>{structure}</b> sur {siteName}.", ['structure' => encode($e['structure']['name'])]).'</p>';
		$content .= '<p>'.s("Vous y retrouvez toutes les informations vous concernant.").'</p>';

		return \main\MailSoftwareAiUi::format(
			s("Votre compte client {value} a été créé", $e['structure']['name']),
			$content,
			buttonLabel: s("Accéder à mon compte"),
			buttonUrl: \Lime::getUrl(),
			footer: FALSE
		);

	}

	public static function getNewMemberMail(Invite $e, \user\User $eUser): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("Un nouveau membre a rejoint <b>{structure}</b>.", ['structure' => encode($e['structure']['name'])]).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("{name} a rejoint {structure}", ['name' => $eUser->getName(), 'structure' => $e['structure']['name']]),
			$content,
			facts: [
				s("Nom") => encode($eUser->getName()),
				s("Adresse e-mail") => encode($eUser['email']),
				s("Rôle") => encode($e['memberRole']['name']),
			],
			footer: FALSE
		);

	}

	public static function urlCreateMember(Structure $eStructure, Role $eRole): string {
		return '/structure/invite:createMember?structure='.$eStructure['id'].'&memberRole='.$eRole['id'];
	}

	/**
	 * Masqué par défaut, et révélé par `invite.css` quand le bouton Administrateur est coché. Un rôle
	 * imposé n'a pas de bouton à cocher : l'avertissement se rend visible d'emblée, ou pas du tout.
	 */
	protected static function getEmailAdminWarning(Invite $eInvite): string {

		if($eInvite->isAmapRoleImposed()) {

			if($eInvite['memberRole']['admin'] === FALSE) {
				return '';
			}

			return '<div class="util-block-warning mt-1">'.StructureUi::getText($eInvite['structure'], 'inviteAdmin').'</div>';

		}

		if($eInvite['structure']->isCommunity()) {
			return '<div class="util-block-info hide mt-1 invite-email-admin">'.StructureUi::getText($eInvite['structure'], 'inviteAdminEmail').'</div>'.
				'<div class="util-block-warning hide mt-1 invite-email-admin">'.StructureUi::getText($eInvite['structure'], 'inviteAdmin').'</div>';
		}

		$h = '<div class="util-block-info hide mt-1 invite-email-admin">'.s("Lorsque vous créez un lien pour inviter un administrateur, vous devez obligatoirement indiquer une adresse e-mail pour des raisons de sécurité.").'</div>';

		return $h.'<div class="util-block-warning hide mt-1 invite-email-admin"><p>'.s("En partageant votre compte avec des droits d'administrateur, vous donnez un accès intégral à votre compte. La personne à qui vous partagez cet accès pourrait supprimer définitivement votre ferme et toutes vos données. Elle pourrait récupérer la liste de vos clients. Elle pourrait aussi vous exclure de la liste des administrateurs et vous interdire définitivement l'accès à cette structure.").'</p><p>'.s("<b>N'OUVREZ JAMAIS CET ACCÈS À D'AUTRES PERSONNES QUE LES RESPONSABLES LÉGAUX DE VOTRE STRUCTURE. <u>EN CAS DE LITIGE, VOUS SEREZ SEUL RESPONSABLE SI VOUS AVEZ CHOISI D'OUVRIR VOTRE STRUCTURE À DES TIERS, Y COMPRIS DEVANT L'ADMINISTRATION FISCALE.</u></b>").'</p></div>';

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Invite::model()->describer($property, [
			'email' => fn(Invite $eInvite) => match($eInvite['type']) {
				Invite::MEMBER => ($eInvite->isAmapAdmin() or $eInvite->designatesMember()) ? s("Adresse e-mail") : s("Réserver l'invitation à une adresse e-mail"),
				Invite::PRODUCER => s("Réserver l'invitation à une adresse e-mail"),
				Invite::CUSTOMER => $eInvite['structure']->isAmap() ? s("Adresse e-mail") : s("Adresse e-mail du client")
			},
			'memberRole' => s("Rôle attribué au membre lorsque l'invitation est acceptée"),
			// Le mot du modèle ne va pas à l'écran : sur un organisme de formation tout dit
			// « promotion », et « sous-groupe » y détonnait. Le libellé n'apparaît que sur un
			// groupe, seul type où le champ se rend.
			'memberSection' => fn(Invite $eInvite) => \structure\StructureUi::getText($eInvite['structure'], 'sectionInvite'),
			'expiresAt' => s("Date d'expiration de l'invitation"),
			'limit' => s("Limite d'utilisations de l'invitation"),
		]);

		switch($property) {

			case 'memberRole' :
				$d->field = 'radio';
				$d->required = TRUE;
				$d->values = function(Invite $e) {

					$e->expects(['cRole']);

					$values = [];

					foreach($e['cRole'] as $eRole) {

						$values[] = [
							'value' => $eRole['id'],
							'label' => $eRole['name'],
							'attributes' => [
								'data-admin' => (int)$eRole['admin'],
								// La portée du rôle voyage jusqu'au client : c'est elle qui décide
								// si l'invitation peut entrer sans sous-groupe.
								'data-sections' => $eRole['sections'],
								'data-staff' => (int)$eRole->isStaff()
							],
						];

					}

					return $values;

				};
				break;

			// Un select et non des radios comme le rôle : un organisme de formation porte trois
			// rôles et douze promotions au bout de quatre ans.
			case 'memberSection' :

				$d->values = function(Invite $e) {

					$e->expects(['cSection']);

					$values = [];

					foreach($e['cSection'] as $eSection) {
						$values[$eSection['id']] = $eSection['name'];
					}

					return $values;

				};

				// Le champ se règle sur le rôle choisi, qui vit dans le même formulaire : un rôle
				// borné à ses sous-groupes et rattaché à aucun ne verrait rien, l'option vide
				// disparaît donc. Le contrôle serveur reste le seul qui décide — celui-ci évite
				// seulement de proposer un choix que le produit refusera.
				$d->attributes = [
					'placeholder' => s("Aucun"),
					'id' => 'invite-section',
					'onrender' => 'StructureInvite.watch(this)'
				];

				break;

			case 'expiresAt' :
				$d->labelAfter = \util\FormUi::info(s("Si vous n'indiquez pas de date d'expiration, l'invitation restera toujours valide."));
				break;

			case 'email' :
				$d->required = fn(Invite $eInvite) => match($eInvite['type']) {
					Invite::MEMBER => ($eInvite->isAmapAdmin() or $eInvite->designatesMember()),
					Invite::CUSTOMER, Invite::PRODUCER => FALSE
				};
				$d->placeholder = fn(Invite $eInvite) => match($eInvite['type']) {
					Invite::MEMBER => ($eInvite->isAmapAdmin() or $eInvite->designatesMember()) ? s("Adresse e-mail de la personne") : s("Saisir une adresse e-mail ou laisser vide"),
					Invite::CUSTOMER => $eInvite['structure']->isAmap() ? NULL : s("Saisir une adresse e-mail ou laisser vide"),
					Invite::PRODUCER => s("Saisir une adresse e-mail ou laisser vide")
				};
				$d->labelAfter = fn(Invite $eInvite) => match($eInvite['type']) {
					Invite::MEMBER => ($eInvite->isAmapAdmin() or $eInvite->designatesMember())
						? \util\FormUi::info(s("La personne se connecte avec cette adresse pour accepter l'invitation."))
						: \util\FormUi::info(s("Un e-mail d'invitation sera envoyé à l'adresse que vous aurez renseignée, et l'invitation ne pourra être acceptée qu'avec cette adresse e-mail. Laissez vide si votre invitation n'est pas réservée à une adresse e-mail en particulier.")),
					Invite::CUSTOMER => $eInvite['structure']->isAmap()
						? \util\FormUi::info(s("L'adhérent recevra l'invitation à cette adresse."))
						: \util\FormUi::info(s("Votre client recevra un e-mail d'invitation à l'adresse que vous aurez renseignée.")),
					Invite::PRODUCER => \util\FormUi::info(s("Le producteur recevra un e-mail d'invitation à l'adresse que vous aurez renseignée, et l'invitation ne pourra être acceptée qu'avec cette adresse e-mail. Laissez vide si votre invitation n'est pas réservée à une adresse e-mail en particulier."))
				};

				// Une invitation Producteur ne fait entrer personne dans la structure de la
				// propriétaire : il n'y a pas de droit d'administrateur à avertir.
				$d->after = fn(\util\FormUi $form, Invite $eInvite) => match($eInvite['type']) {
					Invite::MEMBER => self::getEmailAdminWarning($eInvite),
					Invite::CUSTOMER, Invite::PRODUCER => ''
				};
				break;

			case 'limit' :
				$d->prepend = s("Maximum");
				$d->append = s("utilisation(s)");
				$d->placeholder = s("Illimité");
				$d->labelAfter = \util\FormUi::info(s("L'invitation sera supprimée une fois la limite d'utilisation atteinte. Laissez vide pour ne mettre aucune limite d'utilisations."));
				break;

		}

		return $d;

	}

}
?>
