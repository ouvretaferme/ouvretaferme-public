<?php
namespace learning;

/**
 * La page publique des formations d'un réseau, sur son adresse `reseau.<domaine>/{fqn}` : la liste
 * des dates à venir, l'inscription à une date sans compte en trois étapes, l'écran qui suit l'envoi,
 * et la page d'une inscription ouverte par le lien de l'e-mail.
 *
 * Le même écran s'embarque sur le site du réseau (`?embed`) : ni navigation ni bandeau, et chaque
 * lien, chaque formulaire et chaque redirection garde le paramètre, faute de quoi le parcours
 * sortirait de l'iframe au premier clic. Ce qui sort volontairement de l'iframe (se connecter, créer
 * un compte) s'ouvre dans un nouvel onglet : une connexion dans une iframe tierce n'a pas de cookie.
 *
 * En mode personnalisation, le lien d'une date garde le mode et les réglages essayés
 * (`$customizeQuery`) : la date renvoie le gérant à la fiche, qu'il doit voir avec le même thème.
 *
 * Planche : .claude/state/e-learning/L10/L10c/planche (R1, L1, P1, E1).
 */
class DatePublicUi {

	public function __construct(
		protected \structure\Structure $eStructureCommunity,
		protected \structure\Community $eCommunity,
		protected bool $embed,
		protected array $customizeQuery = []
	) {
		\Asset::css('main', 'template-community.css');
		\Asset::css('main', 'onboarding.css');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'public.js');
	}

	public static function urlList(\structure\Community $eCommunity): string {
		return \structure\CommunityUi::url($eCommunity).'/formations';
	}

	public static function urlFaq(\structure\Community $eCommunity): string {
		return \structure\CommunityUi::url($eCommunity).'/faq';
	}

	public static function urlDate(\structure\Community $eCommunity, Date $eDate): string {
		return self::urlList($eCommunity).'/'.$eDate['id'];
	}

	public static function urlEnrollment(\structure\Community $eCommunity, string $token): string {
		return \structure\CommunityUi::url($eCommunity).'/inscription/'.$token;
	}

	/**
	 * Le code que le réseau colle sur son site : un script qui pose l'iframe de la liste dans le bloc
	 * qui le suit, modèle de la boutique (`ShopUi::getEmbedScript()`).
	 */
	public static function getEmbedScript(\structure\Structure $eStructureCommunity): string {

		$target = 'otf-learning-'.$eStructureCommunity['id'];

		return '<script type="text/javascript">(function() { const element = document.createElement("script"); element.src = "'.\structure\CommunityUi::domain().'/embed-learning.js?id='.$eStructureCommunity['id'].'&target='.$target.'"; document.getElementsByTagName("head")[0].appendChild(element); })()</script><div id="'.$target.'"></div>';

	}

	/**
	 * L'adresse `$url` avec `$query`, et `embed` quand la page est embarquée : un lien ou une
	 * redirection qui le perdrait sortirait le parcours de l'iframe. La page s'en sert pour ses
	 * redirections, l'écran par `url()`.
	 */
	public static function urlQuery(string $url, array $query, bool $embed): string {

		if($embed) {
			$query['embed'] = 1;
		}

		return ($query === []) ? $url : $url.'?'.http_build_query($query);

	}

	public function url(string $url, array $query = []): string {
		return self::urlQuery($url, $query, $this->embed);
	}

	/**
	 * Un écran des formations dans la portée du thème du réseau (`CustomizeUi::getPublic()`), bandeau
	 * compris hors intégration. Un écran qui s'ouvre sur sa bande d'accueil (`$band`), comme la FAQ,
	 * la pose collée au bandeau, sur toute la largeur : il porte ses propres conteneurs.
	 */
	public function getPage(string $content, ?string $design, bool $band = FALSE): string {

		$h = '';

		if($this->embed === FALSE) {
			$h .= $this->getBanner(self::getBannerSubtitle(FALSE));
		}

		if($band) {
			$h .= $content;
		} else {
			$h .= '<div class="container mt-2">';
				$h .= $content;
			$h .= '</div>';
		}

		return CustomizeUi::getPublic($design, $h);

	}

	/**
	 * Le bandeau du réseau, celui de la page publique des relevés (`ResponseUi::getBanner()`), avec
	 * ce que la page propose sous le nom. Sans logo, le nom reprend le bord. La photo du bandeau de
	 * l'espace membres s'y pose comme là-bas (`main/view/Community.php`) : sous elle, le bandeau
	 * garde ses jetons par défaut, l'encre de la couleur se perdant dans le dégradé de l'image.
	 */
	public function getBanner(string $subtitle): string {

		$type = \structure\StructureUi::getCommunityType($this->eStructureCommunity);

		$hasBanner = (
			$this->eStructureCommunity->acceptBanner() and
			$this->eStructureCommunity['banner'] !== NULL
		);

		$style = $hasBanner ? '' : \structure\StructureUi::getCommunityColorStyle($this->eStructureCommunity, $this->eCommunity);

		$h = '<div class="community-header is-public"'.$style.'>';

			if($hasBanner) {
				$h .= '<div class="community-header-media" style="background-image: url('.new \media\StructureBannerUi()->getUrlByElement($this->eStructureCommunity, 'l').')"></div>';
			}

			$h .= '<div class="container">';

				$h .= '<div class="community-header-inner">';
					$h .= '<div class="community-main-back">';
						if($type !== '') {
							$h .= '<span class="util-scope-line-title util-scope-line-title-sm">'.$type.'</span>';
						}
					$h .= '</div>';
				$h .= '</div>';

				$title = '<div>';
					$title .= '<h1>'.encode($this->eStructureCommunity['name']).'</h1>';
					$title .= '<div>'.$subtitle.'</div>';
				$title .= '</div>';

				if($this->eStructureCommunity['logo'] === NULL) {
					$h .= $title;
				} else {
					$h .= '<div class="util-vignette">';
						$h .= \structure\StructureUi::getLogo($this->eStructureCommunity, '4rem');
						$h .= $title;
					$h .= '</div>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public static function getBannerSubtitle(bool $withQuotation): string {
		return $withQuotation ? s("Formations et relevés de prix des fermes du réseau") : s("Formations");
	}

	/**
	 * « Prochaines formations » : une pile par date, dans l'ordre du calendrier, la formation en
	 * titre puisque la liste en mêle plusieurs. Les comptes et la ligne du compte connecté sur chaque
	 * date (`eDateEnrollment`) sont posés par `DateEnrollmentLib::fillForCourse()`.
	 *
	 * « Formations en ligne » vient dessous, en cartes (planche personnes, 1 A) : la carte mène à la
	 * fiche, où l'on s'inscrit. Un réseau qui n'a que des formations en ligne n'a pas de dates à
	 * annoncer : la première section ne parait pas, plutôt que de dire « Aucune formation à venir »
	 * au-dessus de formations qui se suivent dès aujourd'hui.
	 */
	public function getList(\Collection $cDate, \Collection $cCourse = new \Collection()): string {

		$h = '';

		if(
			$cDate->notEmpty() or
			$cCourse->empty()
		) {
			$h .= '<div class="util-title"><h2>'.s("Prochaines formations").'</h2></div>';
		}

		if(
			$cDate->empty() and
			$cCourse->empty()
		) {
			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune formation à venir").'</h3>';
			$h .= '</div>';
			return $h;
		}

		foreach($cDate as $eDate) {
			$h .= $this->getListDeck($eDate);
		}

		if($cCourse->notEmpty()) {
			$h .= $this->getListOnline($cCourse, $cDate->notEmpty());
		}

		return $h;

	}

	/**
	 * L'accès aux questions fréquentes du réseau, sous ses formations et sur sa racine, à côté des
	 * relevés de prix et sous la même forme (`ResponseUi::getCommunityAccess()`). Rien sans question
	 * publiée. Le lien ne garde pas les réglages essayés : la FAQ n'a pas la barre qui les
	 * enregistre, le gérant y verrait un thème qu'il ne peut ni garder ni quitter.
	 */
	public function getFaqAccess(int $faqs): string {

		if($faqs === 0) {
			return '';
		}

		$h = '<div class="util-title mt-2"><h2>'.s("Questions fréquentes").'</h2></div>';

		$h .= '<div class="util-buttons">';
			$h .= '<a href="'.self::urlFaq($this->eCommunity).'" class="util-button">';
				$h .= '<div>';
					$h .= '<h4>'.s("Lire les réponses du réseau").'</h4>';
					$h .= '<div class="util-button-text">'.p("{value} question", "{value} questions", $faqs).'</div>';
				$h .= '</div>';
				$h .= \Asset::icon('arrow-right');
			$h .= '</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte est celle du catalogue lue par un visiteur, sans bande de progression. Son lien sort du
	 * domaine du réseau, où la fiche n'existe pas, et de l'iframe quand la page est intégrée.
	 */
	protected function getListOnline(\Collection $cCourse, bool $afterDates): string {

		$h = '<div class="util-title'.($afterDates ? ' mt-2' : '').'"><h2>'.s("Formations en ligne").'</h2></div>';

		$h .= '<div class="util-cards util-cards-3">';

			$courseUi = new CourseUi();
			$position = 0;

			foreach($cCourse as $eCourse) {
				$h .= $courseUi->getCard($eCourse, FALSE, FALSE, position: $position++, host: \Lime::getUrl(), blank: $this->embed, withAuthor: FALSE);
			}

		$h .= '</div>';

		return $h;

	}

	protected function getListDeck(Date $eDate): string {

		$eCourse = $eDate['course'];

		$eDateEnrollment = $eDate['eDateEnrollment'];

		$url = $this->url(self::urlDate($this->eCommunity, $eDate), $this->customizeQuery);
		$title = encode($eCourse['name']).' '.CourseUi::getTypeMark($eCourse['type']);

		// Une ligne ouverte du compte connecté sur la date : son état, comme sur la page de la ligne, et
		// la même adresse, qui rend cette page au lieu des étapes (`public.p.php`) ; proposer de
		// s'inscrire mènerait à une ligne que `acceptPublicCreate()` refuse.
		// « S'inscrire à cette date » et non « S'inscrire » : la navigation publique du socle propose
		// déjà « S'inscrire » pour créer un compte, deux gestes sous le même mot (porte de planche).
		// Inscriptions en pause, la date n'a pas de porte, ses chiffres disent la pause.
		if($eDateEnrollment->isOpen()) {

			[$label, $class] = DateEnrollmentUi::getMineTexts($eDateEnrollment['status']);
			$title .= ' <span class="badge '.$class.'">'.$label.'</span>';

			$door = '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('person-check').' '.self::getMineLink($eDateEnrollment['status']).'</a>';

		} else if($eDate->isEnrollmentPaused()) {
			$door = '';
		} else if($eCourse->isExternal()) {
			$door = CourseUi::getExternalLink($eDate->getExternalUrl());
		} else if($eDate['remaining'] === 0) {
			$door = '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('hourglass-split').' '.s("Liste d'attente").'</a>';
		} else {
			$door = '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('person-plus').' '.s("S'inscrire à cette date").'</a>';
		}

		$h = '<div class="util-deck mb-1">';
			$h .= '<div class="util-deck-head">';

				$h .= \util\DateUi::calendar($eDate['startAt']);

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.$title.'</div>';
					// Des séances qui diffèrent prennent une ligne chacune, sous le jour.
					$h .= '<p>'.DateUi::getDays($eDate).((count(DateUi::getDistinctSequences($eDate)) > 1) ? '<br/>' : ' · ').DateUi::getLine($eDate, withLink: FALSE).'</p>';
					$h .= self::getFigures($eDate, withPause: $eDateEnrollment->isOpen() === FALSE);
					$h .= DateUi::getExternalLine($eDate);
				$h .= '</div>';

				if($door !== '') {
					$h .= '<div class="util-deck-doors">'.$door.'</div>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien vers la page d'une ligne ouverte, sous le libellé du bouton de l'accusé de réception
	 * (`DateEnrollmentUi::getReceivedMail()`), pour qu'un même geste porte un seul nom.
	 */
	protected static function getMineLink(string $status): string {

		return match($status) {
			DateEnrollment::REQUESTED => s("Voir ma demande"),
			DateEnrollment::CONFIRMED, DateEnrollment::WAITING => s("Voir mon inscription"),
		};

	}

	protected static function getFigures(Date $eDate, bool $withPause = FALSE): string {
		return DateUi::getPublicFigures($eDate, withPause: $withPause);
	}

	/**
	 * Les trois étapes sur une page, dans le composant `onboarding` de la mise en route d'un groupe,
	 * leurs corps étant ceux du panneau du membre (`DateEnrollmentUi`). On avance par le bouton du
	 * pied et on recule par le nom d'une étape faite, qui porte les cases déjà cochées dans son
	 * adresse ; rien n'est acquis pour autant, `doCommit` et `doCreate` les jugent.
	 *
	 * `$online` : un compte connecté qui ne lit pas la formation, dont l'identité ne se redemande pas.
	 */
	public function getSteps(DateEnrollment $eDateEnrollment, int $step, string $status, array $commitments, \Collection $cChapter, \Collection $cStaff, bool $online): string {

		$eDate = $eDateEnrollment['date'];
		$urlDate = self::urlDate($this->eCommunity, $eDate);

		// Inscriptions en pause, ou sur le site de l'organisme : seule l'étape 1 se sert
		// (`public.p.php`), sans progression ni étape suivante. La pause n'a aucun geste, ses chiffres
		// la disent ; l'organisme a son lien sortant à la place de « Continuer ».
		$paused = $eDate->isEnrollmentPaused();
		$external = $eDate['course']->isExternal();

		$form = new \util\FormUi(['columns' => 1]);

		$content = '';

		switch($step) {

			case 1 :
				$content .= new DateEnrollmentUi()->getReading($eDate, $cChapter, $cStaff, withSummary: TRUE);
				if($paused) {
					$footer = '';
				} else if($external) {
					$footer = CourseUi::getExternalLink($eDate->getExternalUrl(), 'btn btn-primary');
				} else {
					$footer = '<a href="'.$this->url($urlDate, ['step' => 2, 'commitment' => $commitments]).'" class="btn btn-primary">'.s("Continuer").' '.\Asset::icon('arrow-right').'</a>';
				}
				break;

			case 2 :
				$content .= new DateEnrollmentUi()->getCommitmentFields($form, $eDateEnrollment, $commitments);
				$footer = $form->submit(s("Continuer").' '.\Asset::icon('arrow-right'), ['class' => 'btn btn-primary']);
				break;

			case 3 :
				[$note, $submit] = DateEnrollmentUi::getSendTexts($eDateEnrollment, $status);
				$content .= $this->getIdentityFields($form, $eDateEnrollment, $commitments, $online);
				if($note !== NULL) {
					$content .= \util\FormUi::info($note);
				}
				$footer = $form->submit($submit, ['class' => 'btn btn-primary', 'data-waiter' => s("Envoi en cours...")]);
				break;

		}

		$h = '<div id="learning-public-steps" class="onboarding learning-public-steps">';
			$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';

				$body = '<div class="onboarding-steps">';

					$body .= $this->getCourseTitle($eDate['course']);

					if(
						$paused === FALSE and
						$external === FALSE
					) {
						$body .= DateEnrollmentUi::getProgress($step, fn(int $position): string => 'data-href="'.encode($this->url($urlDate, ($position === 1) ? ['commitment' => $commitments] : ['step' => $position, 'commitment' => $commitments])).'"');
					}

					$body .= '<section class="onboarding-step is-current"><div>'.$content.'</div></section>';

					if($footer !== '') {
						$body .= '<div class="onboarding-footer"><div class="onboarding-footer-main">'.$footer.'</div></div>';
					}

				$body .= '</div>';

				// Les étapes 2 et 3 postent : le formulaire enveloppe la colonne pour que le bouton du
				// pied en soit.
				if($step === 1) {
					$h .= $body;
				} else {
					$h .= $form->openAjax($urlDate.'/:'.(($step === 2) ? 'doCommit' : 'doCreate'));
						if($this->embed) {
							$h .= $form->hidden('embed', 1);
						}
						$h .= $body;
					$h .= $form->close();
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'étape 3 : l'encart du compte, l'identité, la ferme en texte libre et le statut. Les cases
	 * acceptées à l'étape 2 sont reportées telles que l'élément les demande. Un compte connecté
	 * garde son identité, que la ligne lit (`DateEnrollment::getName()`) : une phrase la nomme avec
	 * son adresse, là où la personne s'engage, sans lien ni bouton (mineur 8 de l'ui, tranché par
	 * V-Learning), puisque rien d'autre ne dirait sous quel compte la ligne part.
	 */
	protected function getIdentityFields(\util\FormUi $form, DateEnrollment $eDateEnrollment, array $commitments, bool $online): string {

		$h = '';

		foreach($commitments as $commitment) {
			$h .= $form->hidden('commitment[]', $commitment);
		}

		if($online) {
			$h .= '<p>'.s("Vous vous inscrivez avec votre compte, {name}, {email}.", ['name' => $eDateEnrollment->getName(), 'email' => encode($eDateEnrollment->getEmail())]).'</p>';
		} else {
			$h .= $this->getAccountNotice($eDateEnrollment['date']);
			$h .= $form->dynamicGroups($eDateEnrollment, ['firstName', 'lastName', 'email']);
		}

		$h .= $form->dynamicGroups($eDateEnrollment, ['structureName', 'trainee']);

		return $h;

	}

	/**
	 * « Se connecter » revient sur cette date, où le compte d'un membre est renvoyé à la fiche de la
	 * formation. `btn-outline-muted`, comme sur les relevés : il ne lit pas `--primary`.
	 */
	protected function getAccountNotice(Date $eDate): string {

		$redirect = self::urlDate($this->eCommunity, $eDate);

		$h = '<div class="util-card util-card-accent util-card-accent-info mb-2">';
			$h .= '<div class="util-card-title">'.s("Vous avez déjà un compte ?").'</div>';
			$h .= '<p>'.s("Connectez-vous pour vous inscrire avec votre compte, puis vous reviendrez à cette date.").'</p>';
			$h .= '<a href="'.\Lime::getUrl().'/user/log:form?redirect='.urlencode($redirect).'" class="btn btn-outline-muted"'.$this->getBlank().'>'.s("Se connecter").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getBlank(): string {
		return $this->embed ? ' target="_blank"' : '';
	}

	/**
	 * La formation en titre, le retour vers la liste, et la marque du type dessous : dans le titre,
	 * le badge prendrait sa taille.
	 */
	public function getCourseTitle(Course $eCourse): string {

		$h = '<div>';
			$h .= '<h2 class="mb-0"><a href="'.$this->url(self::urlList($this->eCommunity)).'" class="h-back">'.\Asset::icon('arrow-left').'</a>'.encode($eCourse['name']).'</h2>';
			$h .= '<p class="mt-1 mb-0">'.CourseUi::getTypeMark($eCourse['type']).'</p>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'écran qui suit l'envoi, à la place des étapes : ce qu'est devenue la demande, la pile de la
	 * date avec ses places, et la carte du compte. Ni jeton ni geste d'annulation : ils ne partent
	 * que dans l'e-mail. L'état dit est celui que `createPublic()` a posé, le même pour une adresse
	 * déjà inscrite que pour une neuve.
	 */
	public function getSent(DateEnrollment $eDateEnrollment): string {

		$eDateEnrollment->expects(['date' => ['course' => ['lessons']]]);

		$eDate = $eDateEnrollment['date'];

		$community = encode($this->eStructureCommunity['name']);
		$email = encode($eDateEnrollment->getEmail());
		$firstName = encode($eDateEnrollment->getFirstName() ?? $eDateEnrollment->getLastName());

		[$title, $text] = match($eDateEnrollment['status']) {
			DateEnrollment::REQUESTED => [
				s("Demande envoyée, merci {value}", $firstName),
				s("{community} confirme chaque inscription. Un e-mail vous le dira à {email}, avec le lien pour annuler si vous ne pouvez plus venir.", ['community' => $community, 'email' => $email])
			],
			DateEnrollment::CONFIRMED => [
				s("Votre place est réservée, merci {value}", $firstName),
				s("Un e-mail vient de partir à {email}, avec le lien pour annuler si vous ne pouvez plus venir.", ['email' => $email])
			],
			DateEnrollment::WAITING => [
				s("Vous êtes sur la liste d'attente, merci {value}", $firstName),
				s("La date est complète. Si une place se libère, {community} peut vous la donner, et un e-mail vous le dira à {email}.", ['community' => $community, 'email' => $email])
			],
		};

		$h = '<div id="learning-public-steps">';

			$h .= $this->getCourseTitle($eDate['course']);

			$h .= '<div class="util-block-success mt-2 mb-2">';
				$h .= '<h3>'.$title.'</h3>';
				$h .= '<p>'.$text.'</p>';
			$h .= '</div>';

			$h .= '<div class="util-deck mb-1">';
				$h .= '<div class="util-deck-head">';
					$h .= \util\DateUi::calendar($eDate['startAt']);
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.DateUi::getDays($eDate).'</div>';
						$h .= '<p>'.DateUi::getLine($eDate, withLink: FALSE).'</p>';
						$h .= self::getFigures($eDate);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			if(
				$eDateEnrollment['user']->empty() and
				$eDate['course']['lessons'] > 0
			) {
				$h .= $this->getAccountCard($eDateEnrollment);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La page d'une inscription ouverte par le lien de l'e-mail : l'état de la ligne en badge sur la
	 * pile de la date, comme sur la fiche du membre, et « Annuler » tant que la ligne l'accepte (un
	 * lien d'e-mail n'écrit rien, c'est ce bouton qui poste). Une ligne annulée dit par qui et quand,
	 * et rend le chemin vers les autres formations.
	 *
	 * Le compte connecté hors du réseau y arrive aussi depuis la date où il a une ligne ouverte, que
	 * cette ligne ait un jeton ou non, venue de la fiche du temps où il était du réseau : sans jeton,
	 * il l'annule par la date, sous son compte (`public.p.php`), et la page se repeint sous son `id`.
	 */
	public function getEnrollment(DateEnrollment $eDateEnrollment): string {

		$eDate = $eDateEnrollment['date'];

		$h = '<div id="learning-public-enrollment">';

			$h .= $this->getCourseTitle($eDate['course']);

			$h .= '<div class="mt-2">';

				if($eDateEnrollment['status'] === DateEnrollment::CANCELED) {
					$h .= '<div class="util-block mb-2">'.$this->getCanceled($eDateEnrollment).'</div>';
				}

				$h .= $this->getEnrollmentDeck($eDateEnrollment);

				if($eDateEnrollment['status'] === DateEnrollment::CANCELED) {
					$h .= '<a href="'.$this->url(self::urlList($this->eCommunity)).'" class="btn btn-outline-primary mt-1">'.s("Voir les prochaines formations").'</a>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'avis par le lien d'une inscription (lot L15c), pour une personne sans compte ou non connectée :
	 * le formulaire quand la ligne ouvre l'avis, sinon la raison, jamais une erreur, le lien venant
	 * d'un e-mail. Un avis déjà donné s'y modifie et s'y retire.
	 */
	public function getReview(DateEnrollment $eDateEnrollment, Review $eReview): string {

		$eDate = $eDateEnrollment['date'];
		$url = ReviewUi::urlPublic($this->eCommunity, $eDateEnrollment['token']);

		$h = '<div id="learning-public-review">';

			$h .= $this->getCourseTitle($eDate['course']);

			$refusal = ReviewUi::getRefusal($eDateEnrollment, $eReview);

			if($refusal !== NULL) {

				$h .= '<div class="util-block-info mt-2">';
					$h .= \Asset::icon('info-circle', ['class' => 'util-block-icon']);
					$h .= '<div class="util-block-info-text">'.$refusal.'</div>';
				$h .= '</div>';

			} else {

				// Le lieu, seulement quand toutes les séances s'y sont tenues.
				$place = ($eDate->getModes() === [DateSequence::ONSITE]) ? DateUi::getWhere($eDate) : NULL;
				$day = mb_strtolower(DateUi::getDays($eDate));

				$h .= '<p class="mt-2">'.(($place === NULL)
					? s("Vous avez suivi la formation {day}.", ['day' => $day])
					: s("Vous avez suivi la formation {day} à {place}.", ['day' => $day, 'place' => $place])
				).'</p>';

				// Hors catalogue public, l'avis n'est lu que par le réseau (lot glacons-parcours, choix 4 bis B).
				$catalog = $eDate['course']->isInCatalog();

				if($eReview->exists()) {
					$h .= '<p class="util-annotation">'.($catalog
						? s("Publié le {value}.", \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE))
						: s("Donné le {date}, lu par {network}.", ['date' => \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE), 'network' => encode($this->eStructureCommunity['name'])])
					).'</p>';
				}

				$form = new \util\FormUi();

				$h .= '<div class="util-block">';

					$h .= $form->openAjax($url.'/:doReview', ['class' => 'form-columns form-columns-33 form-columns-xs']);

						if($this->embed) {
							$h .= $form->hidden('embed', 1);
						}

						$h .= new ReviewUi()->getFields($form, $eReview);

						$h .= $form->group(
							content: $form->submit($eReview->exists() ? s("Enregistrer") : ($catalog ? s("Publier l'avis") : s("Envoyer l'avis")))
						);

					$h .= $form->close();

				$h .= '</div>';

				if($eReview->exists()) {
					$h .= '<a data-ajax="'.$url.'/:doDelete" '.($this->embed ? 'post-embed="1" ' : '').'class="btn btn-sm btn-outline-primary mt-1" data-confirm="'.($catalog ? s("Retirer votre avis ? Votre note et votre commentaire seront supprimés de la fiche de la formation.") : s("Retirer votre avis ? Votre note et votre commentaire seront supprimés.")).'" data-waiter>'.s("Retirer mon avis").'</a>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qui suit l'envoi ou le retrait, à la place du formulaire : le lien reste valable, il se
	 * rouvre pour modifier ou pour donner un nouvel avis.
	 */
	public function getReviewSent(DateEnrollment $eDateEnrollment, Review $eReview, bool $deleted): string {

		$firstName = $eDateEnrollment->getFirstName();
		$catalog = $eDateEnrollment['date']['course']->isInCatalog();

		$h = '<div id="learning-public-review">';

			$h .= $this->getCourseTitle($eDateEnrollment['date']['course']);

			$h .= '<div class="util-block-success mt-2 mb-2">';

				if($deleted) {
					$h .= '<h3>'.s("Votre avis est retiré").'</h3>';
					$h .= '<p class="mb-0">'.($catalog
						? s("Il ne paraît plus sur la fiche de la formation. Ce lien reste valable pour en donner un nouveau.")
						: s("Ce lien reste valable pour en donner un nouveau.")
					).'</p>';
				} else if($catalog) {
					$h .= '<h3>'.(($firstName === NULL) ? s("Merci, votre avis est publié") : s("Merci {value}, votre avis est publié", encode($firstName))).'</h3>';
					$h .= '<p class="mb-0">'.s("Il paraît sur la fiche de la formation, sous « {value} ». Ce lien reste valable pour le modifier.", encode($eReview->getAuthorName())).'</p>';
				} else {
					$h .= '<h3>'.(($firstName === NULL) ? s("Merci, votre avis est enregistré") : s("Merci {value}, votre avis est enregistré", encode($firstName))).'</h3>';
					$h .= '<p class="mb-0">'.s("{network} le lit. Ce lien reste valable pour le modifier.", ['network' => encode($this->eStructureCommunity['name'])]).'</p>';
				}

			$h .= '</div>';

			$h .= '<a href="'.$this->url(ReviewUi::urlPublic($this->eCommunity, $eDateEnrollment['token'])).'" class="btn btn-outline-primary">'.($deleted ? s("Donner un avis") : s("Modifier mon avis")).'</a>';

		$h .= '</div>';

		return $h;

	}

	protected function getCanceled(DateEnrollment $eDateEnrollment): string {

		$community = encode($this->eStructureCommunity['name']);
		$day = \util\DateUi::numeric($eDateEnrollment['canceledAt'], \util\DateUi::DATE);

		$isRequest = $eDateEnrollment->isRequest();
		$values = ['community' => $community, 'day' => $day];

		return match($eDateEnrollment['canceledBy']) {
			DateEnrollment::USER => $isRequest
				? s("Vous avez annulé votre demande le {value}.", $day)
				: s("Vous avez annulé votre inscription le {value}.", $day),
			DateEnrollment::COMMUNITY => $isRequest
				? s("{community} a annulé votre demande le {day}.", $values)
				: s("{community} a annulé votre inscription le {day}.", $values),
			DateEnrollment::DATE => s("{community} a annulé cette date le {day}.", $values)
		};

	}

	/**
	 * La pile de la date, l'état d'une ligne ouverte en badge près du titre, et le geste
	 * d'annulation : par le jeton quand la ligne en a un, par la date sinon, ce qui n'arrive qu'au
	 * compte connecté qui la tient, seul à qui la page la montre. Le lien d'une visio ne se montre
	 * qu'à une place confirmée, comme sur la fiche.
	 */
	protected function getEnrollmentDeck(DateEnrollment $eDateEnrollment): string {

		$eDate = $eDateEnrollment['date'];

		$h = '<div class="util-deck mb-1">';
			$h .= '<div class="util-deck-head">';

				$h .= \util\DateUi::calendar($eDate['startAt']);

				$title = DateUi::getDays($eDate);

				if($eDateEnrollment->isOpen()) {
					[$label, $class] = DateEnrollmentUi::getMineTexts($eDateEnrollment['status']);
					$title .= ' <span class="badge '.$class.'">'.$label.'</span>';
				}

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.$title.'</div>';
					$h .= '<p>'.DateUi::getLine($eDate, withLink: $eDateEnrollment['status'] === DateEnrollment::CONFIRMED).'</p>';
				$h .= '</div>';

				if($eDateEnrollment->acceptCancel()) {

					[, , $action, $confirm] = DateEnrollmentUi::getMineTexts($eDateEnrollment['status'], $eDateEnrollment['losesLessons']);

					$urlCancel = ($eDateEnrollment['token'] === NULL)
						? self::urlDate($this->eCommunity, $eDate).'/:doCancel'
						: self::urlEnrollment($this->eCommunity, $eDateEnrollment['token']).'/:doCancel';

					$h .= '<div class="util-deck-doors">';
						$h .= '<a data-ajax="'.$urlCancel.'" '.($this->embed ? 'post-embed="1" ' : '').'class="btn btn-outline-primary" data-confirm="'.$confirm.'" data-waiter>'.$action.'</a>';
					$h .= '</div>';

				}

				// L'attestation de présence d'une formation sans leçon (lot L8f) se télécharge par le jeton
				// de la place, son seul accès sans compte. Une date pointée est finie : la place ne
				// s'annule plus, les deux portes ne se croisent pas.
				if(
					$eDateEnrollment['token'] !== NULL and
					$eDateEnrollment->acceptPresenceAttestation()
				) {

					$h .= '<div class="util-deck-doors">';
						$h .= '<a href="'.self::urlEnrollment($this->eCommunity, $eDateEnrollment['token']).'/attestation" class="btn btn-light" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Attestation de présence").'</a>';
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les leçons se suivent avec un compte : créé à la même adresse, il reprend l'inscription à la
	 * première connexion (`UserObserverLib::logIn()`, arbitrage 1 a), et une place confirmée ouvre
	 * la formation (`Course::canRead()`, arbitrage 3 a). La carte tient à ces deux arbitrages et se
	 * retirerait avec eux. Elle ne paraît que sur une formation qui a des leçons (`course.lessons`,
	 * posé par `DateEnrollmentLib::fillLessons()`) : un présentiel sans leçon se suit sans compte.
	 */
	/**
	 * Une adresse qui a déjà un compte est invitée à se connecter, jamais à en créer un second
	 * (`accountExists`, posé par la page comme pour l'e-mail).
	 */
	protected function getAccountCard(DateEnrollment $eDateEnrollment): string {

		$eDateEnrollment->expects(['accountExists']);

		if($eDateEnrollment['accountExists']) {

			$h = '<div class="util-card util-card-accent util-card-accent-info mt-2">';
				$h .= '<div class="util-card-title">'.s("Vous avez déjà un compte").'</div>';
				$h .= '<p>'.s("Connectez-vous avec l'adresse {value}. Votre compte reprendra cette inscription, et les leçons de la formation s'ouvriront dès que votre place sera confirmée.", encode($eDateEnrollment->getEmail())).'</p>';
				$h .= '<a href="'.\Lime::getUrl().'/user/log:form?redirect='.urlencode(self::urlDate($this->eCommunity, $eDateEnrollment['date'])).'" class="btn btn-outline-primary"'.$this->getBlank().'>'.s("Se connecter").'</a>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-card util-card-accent util-card-accent-info mt-2">';
			$h .= '<div class="util-card-title">'.s("Les leçons se suivent avec un compte").'</div>';
			$h .= '<p>'.s("Créez votre compte avec l'adresse {value}. Il reprendra cette inscription, et les leçons de la formation s'ouvriront dès que votre place sera confirmée.", encode($eDateEnrollment->getEmail())).'</p>';
			$h .= '<a href="'.\Lime::getUrl().'/inscription" class="btn btn-outline-primary"'.$this->getBlank().'>'.s("Créer mon compte").'</a>';
		$h .= '</div>';

		return $h;

	}

}
?>
