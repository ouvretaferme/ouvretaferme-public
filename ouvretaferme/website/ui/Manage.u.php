<?php
namespace website;

class ManageUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('website', 'manage.css');
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::js('website', 'manage.js');

	}

	public function create(\structure\Structure $eStructure): string {

		$form = new \util\FormUi();

		$eWebsite = new Website([
			'structure' => $eStructure,
			'name' => $eStructure['name']
		]);

		$start = '<ul>';
		$start .= '<li>'.s("Créez un nombre illimité de pages").'</li>';
			$start .= '<li>'.s("Publiez les actualités de votre activité").'</li>';
			$start .= '<li>'.s("Intégrez vos boutiques en ligne").'</li>';
			$start .= '<li>'.s("Compatibilité avec les ordinateurs et les mobiles").'</li>';
		$start .= '</ul>';
		$start .= '<p>'.s("Ce module de création de site internet est fait pour vous si vous désirez un site simple et facile à mettre à jour. Il n'est pas fait pour vous si vous avez des besoins spécifiques qui vont au delà des fonctionnalités de base. Dans ce cas, nous vous invitons à vous tourner vers d'autres solutions techniques.").'</p>';

		$h = new \main\DesignUi()->getStart(
			\Asset::icon('megaphone'),
			s("Créez facilement votre site internet avec {siteName}"),
			$start
		);

		$h .= $form->openAjax('/website/manage:doCreate');

			$h .= $form->hidden('structure', $eStructure['id']);

			$h .= $form->dynamicGroups($eWebsite, ['internalDomain*', 'name*', 'description']);

			$h .= $form->group(
				content: $form->submit(s("Démarrer le site internet"))
			);

		$h .= $form->close();

		return $h;

	}

	public function update(Website $eWebsite): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/website/manage:doUpdate');

			$h .= $form->hidden('id', $eWebsite['id']);

			$h .= $form->group(
				s("Logo").\util\FormUi::info(s("Affiché en haut de toutes les pages du site, à la place du titre")),
				new \media\WebsiteLogoUi()->getCamera($eWebsite, size: '6rem')
			);

			$h .= $form->group(
				s("Icône").\util\FormUi::info(s("La petite image affichée par le navigateur dans son onglet et dans les favoris")),
				new \media\WebsiteFaviconUi()->getCamera($eWebsite, size: '3rem')
			);

			$h .= $form->group(
				s("Bandeau").\util\FormUi::info(s("Le titre de la page sera incrusté dans ce bandeau qui sera affiché en haut de toutes les pages du site qui n'ont pas de bandeau personnalisé")),
				new \media\WebsiteBannerUi()->getCamera($eWebsite, width: '24rem', height: '8rem')
			);

			$h .= $form->dynamicGroups($eWebsite, ['internalDomain', 'domain', 'name', 'description']);

			$h .= $form->dynamicGroups($eWebsite, ['footer']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-website-update',
			title: s("Paramétrer le site internet"),
			body: $h,
			close: 'reload'
		);

	}

	public function contact(Website $eWebsite): \Panel {

		$structureEmail = $eWebsite['structure']['legalEmail'];

		$h = '<p>'.s("Vous pouvez facilement intégrer un formulaire de contact sur le site internet de votre activité. Lorsqu'un client vous contactera par ce biais, vous recevrez un e-mail avec le message laissé par le client.").'</p>';

		$h .= '<dl class="util-presentation util-presentation-1 mb-2">';
			$h .= '<dt>'.s("Adresse e-mail de la structure").'</dt>';
			$h .= '<dd>'.($structureEmail ?? s("non renseignée")).'</dd>';
			$h .= '<dt>'.s("Site internet de la structure").'</dt>';
			$h .= '<dd>'.\website\WebsiteUi::link($eWebsite).'</dd>';
		$h .= '</dl>';

		if($structureEmail === NULL) {

			$h .= '<div class="util-block-danger">';
				$h .= '<p>'.s("Pour intégrer un formulaire de contact sur votre site internet, veuillez d'abord renseigner l'adresse e-mail de votre structure.").'</p>';
				$h .= '<a href="/structure/structure:update?id='.$eWebsite['structure']['id'].'" class="btn btn-transparent">'.s("Configurer maintenant").'</a>';
			$h .= '</div>';

		} else {

			$h .= '<p>'.s("Pour réaliser l'intégration du formulaire, il vous suffit d'ajouter le code ci-dessous sur n'importe quelle page de votre site").'</p>';

			$h .= '<code>@contactForm</code>';

			$h .= '<br/>';

			$h .= '<h3>'.s("Rendu sur votre site internet").'</h3>';

			$h .= '<div class="util-block-info">';
				$h .= s("L'envoi du message est volontairement désactivé sur cette page de rendu.");
			$h .= '</div>';

			$h .= '<div>';
				$h .= new ContactUi()->getForm($eWebsite, TRUE);
			$h .= '</div>';

		}

		return new \Panel(
			id: 'panel-website-contact',
			title: s("Intégrer un formulaire de contact"),
			body: $h
		);

	}

	public function newsletter(Website $eWebsite): \Panel {

		$structureEmail = $eWebsite['structure']['legalEmail'];

		$h = '<p>'.s("Vous pouvez facilement intégrer un formulaire d'inscription à votre lettre d'information sur le site internet de votre activité. Lorsqu'un client s'inscrira à votre lettre d'information, vous recevrez un e-mail avec les coordonnées laissées par le client et celui-ci sera ajouté à <link>votre liste de contacts</link>.", ['link' => '<a href="'.\structure\StructureUi::urlCommunicationsContact($eWebsite['structure']).'">']).'</p>';

		$h .= '<dl class="util-presentation util-presentation-1 mb-2">';
			$h .= '<dt>'.s("Adresse e-mail de la structure").'</dt>';
			$h .= '<dd>'.($structureEmail ?? s("non renseignée")).'</dd>';
			$h .= '<dt>'.s("Site internet de la structure").'</dt>';
			$h .= '<dd>'.\website\WebsiteUi::link($eWebsite).'</dd>';
		$h .= '</dl>';

		if($structureEmail === NULL) {

			$h .= '<div class="util-block-danger">';
				$h .= '<p>'.s("Pour intégrer un formulaire d'inscription à votre lettre d'information sur votre site internet, veuillez d'abord renseigner l'adresse e-mail de votre activité.").'</p>';
				$h .= '<a href="/structure/structure:update?id='.$eWebsite['structure']['id'].'" class="btn btn-transparent">'.s("Configurer maintenant").'</a>';
			$h .= '</div>';

		} else {

			$h .= '<p>'.s("Pour réaliser l'intégration du formulaire, il vous suffit d'ajouter le code ci-dessous sur n'importe quelle page de votre site").'</p>';

			$h .= '<code>@newsletterForm</code>';

			$h .= '<br/>';

			$h .= '<h3>'.s("Rendu sur votre site internet").'</h3>';

			$h .= '<div class="util-block-info">';
				$h .= s("La validation du formulaire est volontairement désactivée sur cette page de rendu.");
			$h .= '</div>';

			$h .= '<div>';
				$h .= new NewsletterUi()->getForm($eWebsite, TRUE);
			$h .= '</div>';

		}

		return new \Panel(
			id: 'panel-website-newsletter',
			title: s("Intégrer un formulaire d'inscription à votre lettre d'information"),
			body: $h
		);

	}

	public function displayTitle(Website $eWebsite): string {

		$h = '<div class="util-action">';

			$h .= '<div class="website-title">';
				$h .= new \media\WebsiteLogoUi()->getCamera($eWebsite, size: '5rem');
				$h .= '<h1 class="mb-0">';
					$h .= s("Site internet");
				$h .= '</h1>';
			$h .= '</div>';

			$h .= '<div>';
				$h .= '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
				$h .= '<div class="dropdown-list bg-primary">';
					$h .= '<div class="dropdown-title">'.s("Site internet").'</div>';
					$h .= '<a href="/website/manage:update?id='.$eWebsite['id'].'" class="dropdown-item">'.s("Paramétrer le site").'</a>';
					if($eWebsite['customizable']) {
						$h .= '<a href="'.WebsiteUi::url($eWebsite, internalDomain: TRUE).'?customize=1" class="dropdown-item">'.s("Personnaliser l'apparence du site").'</a>';
					}
					$h .= match($eWebsite['status']) {
						Website::INACTIVE => '<a data-ajax="/website/manage:doUpdateStatus" post-id="'.$eWebsite['id'].'" post-status="'.Website::ACTIVE.'" class="dropdown-item" data-confirm="'.s("Le site sera rendu accessible au public, voulez-vous continuer ?").'">'.s("Mettre en ligne le site").'</a>',
						Website::ACTIVE => '<a data-ajax="/website/manage:doUpdateStatus" post-id="'.$eWebsite['id'].'" post-status="'.Website::INACTIVE.'" class="dropdown-item" data-confirm="'.s("Le site deviendra inaccessible au public, voulez-vous continuer ?").'">'.s("Mettre hors ligne le site").'</a>'
					};
					$h .= '<div class="dropdown-subtitle">'.s("Intégrer un formulaire sur le site internet").'</div>';
					$h .= '<a href="/website/manage:contact?id='.$eWebsite['id'].'" class="dropdown-item">'.\Asset::icon('arrow-right').' '.s("Formulaire de contact").'</a>';
					if(\structure\StructureSetting::hasMailPackage()) {
						$h .= '<a href="/website/manage:newsletter?id='.$eWebsite['id'].'" class="dropdown-item">'.\Asset::icon('arrow-right').' '.s("Formulaire d'inscription à votre lettre d'information").'</a>';
					}
					$h .= implode('', self::notify('getManageLinks', $eWebsite));
					$h .= '<div class="dropdown-divider"></div>';
					$h .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
					$h .= '<a data-ajax="/website/manage:doDelete" post-id="'.$eWebsite['id'].'" class="dropdown-item" data-confirm="'.s("Le site ainsi que toutes les données seront supprimées, êtes-vous sûr de vouloir continuer ?").'">'.s("Supprimer définitivement le site").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function display(Website $eWebsite): string {

		\Asset::css('sequence', 'crop.css');

		$h = '';

		if(
			$eWebsite['domain'] !== NULL and
			$eWebsite['domainStatus'] !== Website::PINGED_SECURED
		) {

			$analyze = analyze_url('https://'.$eWebsite['domain']);

			$h .= '<div class="util-block-info">';
				$h .= '<h4>'.s("Configurer votre nom de domaine {value}", encode($eWebsite['domain'])).'</h4>';
				$h .= '<p>'.s("Pour démarrer l'activation de votre nom de domaine, configurez les enregistrements DNS de votre nom de domaine pour qu'ils pointent vers {siteName} en utilisant un type <b>CNAME</b> :").'</p>';
				$h .= '<pre class="mb-1">';
					$h .= '<b>www 10800 IN CNAME www.'.\Lime::getDomain().'.</b>';
				$h .= '</pre>';
				$h .= '<p>'.s("Le plus souvent, le fournisseur chez qui vous avez acheté votre nom de domaine vous propose un formulaire simplifié pour ajouter un type <b>CNAME</b>. Si vous n'êtes pas à l'aise avec ces instructions, nous vous conseillons de vous faire aider. Une fois les DNS modifiés et propagés, la configuration de votre nom de domaine se terminera automatiquement. Cette étape peut prendre plusieurs heures. Il est possible de demander de l'aide sur le <b>Discord</b> de {siteName}.").'</p>';
				$h .= '<p>'.s("Pour configurer votre domaine principal <b>{value}</b> vers votre site, vous pouvez utiliser une redirection, fonctionnalité proposée par la plupart des fournisseurs de noms de domaine.", $analyze['domain']).'</p>';
			$h .= '</div>';

		}

		$h .= '<div class="util-block stick-xs" style="margin-bottom: 2rem">';
			$h .= '<dl class="util-presentation util-presentation-2">';
				$h .= '<dt>'.s("Adresse du site").'</dt>';
				$h .= '<dd class="util-presentation-fill">';
					$h .= '<a href="'.WebsiteUi::url($eWebsite).'" id="website-url">'.WebsiteUi::url($eWebsite).'</a>';
					$h .= ' <a class="btn btn-sm btn-outline-primary dropdown-toggle" data-dropdown="bottom-start">'.\Asset::icon('share').' '.s("Partager").'</a>';
					$h .= '<div class="dropdown-list">';
						$h .= '<a onclick="doCopy(this)" data-selector="#website-url" data-message="'.s("Copié !").'" class="dropdown-item">'.s("Copier le lien").'</a>';
						$h .= '<a href="/website/manage:qrCode?id='.$eWebsite['id'].'" class="dropdown-item">'.s("Télécharger un QR code").'</a>';
					$h .= '</div>';
				$h .= '</dd>';
				$h .= '<dt>'.s("Statut").'</dt>';
				$h .= '<dd>';
					$h .= match($eWebsite['status']) {
						Website::ACTIVE => '<span class="color-success">'.\Asset::icon('check-lg').' '.s("Site en ligne").'</span>',
						Website::INACTIVE => '<span class="color-danger">'.\Asset::icon('pause-fill').' '.s("Site hors ligne").'</span>'
					};
				$h .= '</dd>';
				$h .= '<dt>'.s("Nom de domaine").'</dt>';
				$h .= '<dd class="website-info-domain">';
					if($eWebsite['domain'] === NULL) {
						$h .= '<a href="/website/manage:update?id='.$eWebsite['id'].'">'.s("Configurer").'</a>';
					} else {
						if($eWebsite['domainStatus'] !== Website::PINGED_SECURED) {
							$h .= encode($eWebsite['domain']).' ';
						}
						$h .= match($eWebsite['domainStatus']) {
							Website::FAILURE_UNSECURED => \Asset::icon('chevron-right').' <b class="color-danger">'.s("Non actif - Impossible d'atteindre le nom de domaine").'</b>',
							Website::FAILURE_SECURED => \Asset::icon('chevron-right').' <b class="color-danger">'.s("Non actif - Impossible de sécuriser le nom de domaine").'</b>',
							Website::FAILURE_CERTIFICATE_CREATED => \Asset::icon('chevron-right').' <b class="color-danger">'.s("Non actif - Impossible de créer le certificat de sécurité").'</b>',
							Website::PENDING => \Asset::icon('chevron-right').' <b>'.s("Non actif - En cours de configuration").'</b>',
							Website::CONFIGURED_UNSECURED => \Asset::icon('chevron-right').' <b>'.s("Non actif - Vérification du domaine").'</b>',
							Website::CONFIGURED_SECURED => \Asset::icon('chevron-right').' <b>'.s("Non actif - Vérification du domaine sécurisé").'</b>',
							Website::CERTIFICATE_CREATED => \Asset::icon('chevron-right').' <b>'.s("Non actif - Sécurisation du domaine en cours").'</b>',
							Website::PINGED_UNSECURED => \Asset::icon('chevron-right').' <b>'.s("Non actif - Domaine vérifié et en attente de sécurisation").'</b>',
							Website::PINGED_SECURED => '<span class="color-success">'.\Asset::icon('check-lg').' '.s("Configuré").'</span>',
						};
						if(in_array($eWebsite['domainStatus'], [Website::FAILURE_UNSECURED, Website::FAILURE_CERTIFICATE_CREATED, Website::FAILURE_SECURED])) {

							if($eWebsite['domainTry'] >= WebsiteSetting::DOMAIN_MAX_TRY) {
								$h .= ' '.s("(nombre maximal de tentatives atteint)");
								$h .= ' <a data-ajax="/website/manage:doUpdateDomainTry" post-id="'.$eWebsite['id'].'" post-domain-try="0" class="btn btn-danger">'.s("Réessayer").'</a>';
							} else {
								$h .= ' '.s("(essai {current} / {max})", ['current' => $eWebsite['domainTry'], 'max' => WebsiteSetting::DOMAIN_MAX_TRY]);
							}

						}
					}
				$h .= '</dd>';
				$h .= '<dt>'.s("Titre du site").'</dt>';
				$h .= '<dd>';
					$h .= encode($eWebsite['name'] ?? '');
				$h .= '</dd>';
				$h .= '<dt>'.s("Icône").'</dt>';
				$h .= '<dd>';
					$h .= '<div>';
						$h .= new \media\WebsiteFaviconUi()->getCamera($eWebsite, size: '2rem');
					$h .= '</div>';
				$h .= '</dd>';
				$h .= '<dt>'.s("Description").'</dt>';
				$h .= '<dd class="util-presentation-fill">';
					if($eWebsite['description']) {
						$h .= '<span class="website-info-description">'.encode($eWebsite['description']).'</span>';
					}
				$h .= '</dd>';
			$h .= '</dl>';
		$h .= '</div>';

		$h .= $this->getAppearance($eWebsite);

		return $h;

	}

	protected function getAppearance(Website $eWebsite): string {

		$eDesign = $eWebsite['customDesign'];

		if(
			$eWebsite['customizable'] === FALSE or
			$eDesign['fqn'] !== 'classique'
		) {
			return '';
		}

		$h = '<div class="util-cards util-cards-1 website-appearance-wrapper">';

			$h .= '<a href="'.WebsiteUi::url($eWebsite, internalDomain: TRUE).'?customize=1" class="util-deck">';

				$h .= '<div class="util-deck-head">';

					$h .= '<div class="util-icon util-icon-commercialisation util-icon-round util-icon-lg">'.\Asset::icon('palette-fill').'</div>';

					$h .= '<div class="util-deck-body">';

						$h .= '<div class="util-deck-title">'.s("Personnalisez l'apparence de votre site").'</div>';

						$h .= '<p>'.s("+10 thèmes disponibles et des millions de combinaisons possibles ! Essai gratuit jusqu'à fin octobre.").'</p>';

					$h .= '</div>';

					$h .= '<div class="util-deck-colors">';
						foreach([
							s("Couleur d'arrière plan") => $eWebsite['customBackground'],
							s("Couleur contrastante") => $eWebsite['customColor'],
							s("Couleur des liens") => $eWebsite['customLinkColor'] ?? $eWebsite['customBackground']
						] as $label => $color) {
							$h .= '<span class="util-deck-color" style="background-color: '.encode($color).'" title="'.$label.'"></span>';
						}
					$h .= '</div>';

					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';

				$h .= '</div>';

			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	public function configure(Website $eWebsite, \Collection $cWebpage, \Collection $cMenu, \Collection $cNews): string {

		$h = '<div class="tabs tabs-h" id="website-configure" onrender="'.encode('Lime.Tab.restore(this, "pages", '.(get_exists('tab') ? '"'.GET('tab', ['pages', 'news'], 'pages').'"' : 'null').')').'">';

			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected" data-tab="pages" onclick="Lime.Tab.select(this)">'.s("Pages").'</a>';
				$h .= '<a class="tab-item" data-tab="menu" onclick="Lime.Tab.select(this)">'.s("Menu").'</a>';
				$h .= '<a class="tab-item" data-tab="news" onclick="Lime.Tab.select(this)">'.s("Actualités").'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel selected" data-tab="pages">';
				$h .= $this->getPages($eWebsite, $cWebpage);
			$h .= '</div>';

			$h .= '<div class="tab-panel" data-tab="menu">';
				$h .= $this->getMenu($eWebsite, $cWebpage, $cMenu);
			$h .= '</div>';

		$h .= '<div class="tab-panel" data-tab="news">';
			$h .= $this->getNews($eWebsite, $cNews);
		$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getPages(Website $eWebsite, \Collection $cWebpage): string {

		if($cWebpage->empty()) {
			return '<div class="util-empty">'.s("Il n'y a aucune page à afficher.").'</div>';
		}

		$h = '<div class="mb-1">';
			$h .= '<a href="/website/webpage:create?website='.$eWebsite['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Nouvelle page").'</a>';
		$h.= '</div>';

		$h .= '<div class="website-page-wrapper util-overflow-md stick-xs">';

		$h .= '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th class="td-min-content website-page-url">'.s("Adresse").'</th>';
					$h .= '<th class="td-min-content">'.s("Bandeau").'</th>';
					$h .= '<th>'.s("Titre").'</th>';
					$h .= '<th class="website-page-content">'.s("Contenu").'</th>';
					$h .= '<th>'.s("Statut").'</th>';
					$h .= '<th></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($cWebpage as $eWebpage) {

				$h .= '<tr>';

					$h .= '<td class="td-min-content website-page-url">';
						$h .= '/'.$eWebpage['url'];
					$h .= '</td>';

					$h .= '<td class="td-min-content">';
						$h .= new \media\WebpageBannerUi()->getCamera($eWebpage, width: '12rem', height: '4rem');
					$h .= '</td>';

					$h .= '<td>';
						$h .= '<a href="'.WebsiteUi::url($eWebsite, '/'.$eWebpage['url']).'" class="btn btn-outline-primary">'.encode($eWebpage['title']).'</a> ';
					$h .= '</td>';

					$h .= '<td class="website-page-content">';
						if($eWebpage['content'] === NULL) {
							$h .= '<a href="/website/webpage:updateContent?id='.$eWebpage['id'].'" class="btn btn-primary">'.\Asset::icon('pencil-fill').' '.s("Rédiger").'</a>';
						} else {
							$h .= '<a href="/website/webpage:updateContent?id='.$eWebpage['id'].'" class="btn btn-primary">'.\Asset::icon('pencil-fill').'&nbsp;&nbsp;<small>'.s("{value} Ko", round(strlen($eWebpage['content']) / 1024, 2)).'</small></a>';
						}
					$h .= '</td>';

					$h .= '<td class="td-min-content">';
						$h .= match($eWebpage['status']) {
							Webpage::ACTIVE => '<span class="color-success">'.\Asset::icon('check-lg').' '.s("En ligne").'</span>',
							Webpage::INACTIVE => '<span class="color-muted">'.\Asset::icon('pause-fill').' '.s("Hors ligne").'</span>',
						};
					$h .= '</td>';

					$h .= '<td class="text-end">';
						$h .= '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';
							$h .= '<div class="dropdown-title">'.s("Page").'</div>';
							$h .= '<a href="/website/webpage:update?id='.$eWebpage['id'].'" class="dropdown-item">'.s("Paramétrer la page").'</a>';
							$h .= '<a href="/website/webpage:updateContent?id='.$eWebpage['id'].'" class="dropdown-item">'.s("Rédiger le contenu").'</a>';
							if($eWebpage['template']['fqn'] !== 'homepage') {
								$h .= match($eWebpage['status']) {
									Webpage::INACTIVE => '<a data-ajax="/website/webpage:doUpdateStatus" post-id="'.$eWebpage['id'].'" post-status="'.Webpage::ACTIVE.'" class="dropdown-item">'.s("Mettre en ligne la page").'</a>',
									Webpage::ACTIVE => '<a data-ajax="/website/webpage:doUpdateStatus" post-id="'.$eWebpage['id'].'" post-status="'.Webpage::INACTIVE.'" class="dropdown-item" data-confirm="'.s("La page deviendra inaccessible au public, voulez-vous continuer ?").'">'.s("Mettre hors ligne la page").'</a>'
								};
							}
							if($eWebpage->assertDelete()) {
								$h .= '<div class="dropdown-divider"></div>';
								$h .= '<a data-ajax="/website/webpage:doDelete" post-id="'.$eWebpage['id'].'" class="dropdown-item" data-confirm="'.s("La page ainsi que toutes les données qui s'y trouvent seront supprimées, êtes-vous sûr de vouloir continuer ?").'">'.s("Supprimer définitivement la page").'</a>';
							}
						$h .= '</div>';
					$h .= '</td>';

				$h .= '</tr>';

			}

		$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function getMenu(Website $eWebsite, \Collection $cWebpage, \Collection $cMenu): string {

		if($cWebpage->empty()) {
			return '<div class="util-empty">'.s("Il n'y a aucune page à afficher.").'</div>';
		}

		$h = '';

		if(
			$cMenu->notEmpty() and
			$cMenu->count() < 5
		) {

			$h .= '<div class="util-info">';
				$h .= s("Faites glisser une entrée par sa poignée pour la déplacer, ou déposez-la sous une autre pour en faire un sous-menu.");
			$h .= '</div>';

		}

		$h .= '<div id="website-menu-editor" class="website-menu-editor" data-website="'.$eWebsite['id'].'" onrender="WebsiteManage.initMenuEditor()">';

			if($cMenu->empty()) {
				$h .= '<div class="util-empty">'.s("Votre menu est vide.").'</div>';
			}

			$h .= '<ul class="website-menu-editor-list">';

				foreach($cMenu as $eMenu) {

					$h .= '<li class="website-menu-editor-item" data-id="'.$eMenu['id'].'">';

						$h .= $this->getMenuEntry($eWebsite, $eMenu, $cWebpage);

						$h .= '<div class="website-menu-editor-branch">';

							$h .= '<ul class="website-menu-editor-list website-menu-editor-children" data-parent="'.$eMenu['id'].'" data-label="'.s("Déposer ici pour placer comme sous-menu de « {value} »", encode($eMenu['label'])).'">';

								foreach($eMenu['cMenuChild'] as $eMenuChild) {

									$h .= '<li class="website-menu-editor-item" data-id="'.$eMenuChild['id'].'">';
										$h .= $this->getMenuEntry($eWebsite, $eMenuChild, $cWebpage);
									$h .= '</li>';

								}

							$h .= '</ul>';

						$h .= '</div>';

					$h .= '</li>';

				}

			$h .= '</ul>';

			$h .= $this->getMenuAdd($eWebsite);

		$h .= '</div>';

		return $h;

	}

	protected function getMenuAdd(Website $eWebsite): string {

		$h = '<div class="website-menu-editor-add">';

			$h .= '<a data-dropdown="bottom-start" class="btn btn-lg btn-secondary dropdown-toggle">';
				$h .= s("Ajouter une entrée dans le menu");
			$h .= '</a>';

			$h .= $this->getMenuAddList($eWebsite);

		$h .= '</div>';

		return $h;

	}

	protected function getMenuAddChild(Website $eWebsite, Menu $eMenuParent): string {

		$h = '<div class="website-menu-editor-add-child">';

			$h .= '<a data-dropdown="bottom-end" class="btn btn-outline-primary dropdown-toggle" title="'.s("Ajouter un sous-menu").'">';
				$h .= \Asset::icon('plus').' '.s("Sous-menu");
			$h .= '</a>';

			$h .= $this->getMenuAddList($eWebsite, $eMenuParent);

		$h .= '</div>';

		return $h;

	}

	protected function getMenuAddList(Website $eWebsite, Menu $eMenuParent = new Menu()): string {

		$parent = $eMenuParent->empty() ? '' : '&parent='.$eMenuParent['id'];
		$url = '/website/menu:create?website='.$eWebsite['id'];

		$h = '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">';
				if($eMenuParent->notEmpty()) {
					$h .= s("Ajouter à ce sous-menu");
				} else {
					$h .= s("Ajouter au menu");
				}
			$h .= '</div>';
			$h .= '<a href="'.$url.'&for=webpage'.$parent.'" class="dropdown-item">'.s("Un lien une page de votre site").'</a>';
			$h .= '<a href="'.$url.'&for=url'.$parent.'" class="dropdown-item">'.s("Un lien un autre site internet").'</a>';
			$h .= '<a href="'.$url.'&for=label'.$parent.'" class="dropdown-item">'.s("Un texte sans lien").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getMenuEntry(Website $eWebsite, Menu $eMenu, \Collection $cWebpage): string {

		$badge = '';
		$target = '';

		if($eMenu['webpage']->notEmpty()) {

			$eWebpage = $cWebpage->offsetExists($eMenu['webpage']['id']) ? $cWebpage[$eMenu['webpage']['id']] : $eMenu['webpage'];

			$target = '/'.encode($eWebpage['url']);

			if($eWebpage->offsetExists('status')) {

				$badge = match($eWebpage['status']) {
					Webpage::ACTIVE => '',
					Webpage::INACTIVE => ' <span class="badge badge-neutral" title="'.s("Cette page est hors ligne").'">'.s("Page hors ligne").'</span>',
				};

			}

		} else if($eMenu['url'] !== NULL) {
			$target = \Asset::icon('box-arrow-up-right').' <a href="'.encode($eMenu['url']).'" target="_blank">'.encode($eMenu['url']).'</a>';
		}

		$h = '<div class="util-deck website-menu-editor-entry">';

			$h .= '<div class="util-deck-head">';

				$h .= '<span class="website-menu-editor-handle" title="'.s("Déplacer").'">'.\Asset::icon('grip-vertical').'</span>';

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.$eMenu->quick('label', encode($eMenu['label'])).$badge.'</div>';

					if($target !== '') {
						$h .= '<p class="website-menu-editor-target">'.$target.'</p>';
					}

				$h .= '</div>';

				$h .= '<div class="util-deck-aside website-menu-editor-buttons">';

					$h .= $this->getMenuAddChild($eWebsite, $eMenu);

					$h .= '<a data-ajax="/website/menu:doDelete" post-id="'.$eMenu['id'].'" class="btn btn-only-icon btn-outline-primary" title="'.s("Retirer cette entrée du menu").'">'.\Asset::icon('trash').'</a>';

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getNews(Website $eWebsite, \Collection $cNews): string {

		$h = '<div class="text-end" style="margin-bottom: 1rem;">';
			$h .= '<a href="/website/news:create?website='.$eWebsite['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Nouvelle actualité").'</a>';
		$h.= '</div>';

		if($cNews->empty()) {
			$h .= '<div class="util-empty">'.s("Il n'y a aucune actualité à afficher pour le moment.").'</div>';
		} else {

			$h .= '<div class="website-news-wrapper stick-xs">';

			$h .= '<table class="tr-even website-news-table">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Titre").'</th>';
						$h .= '<th class="website-page-content">'.s("Contenu").'</th>';
						$h .= '<th>'.s("Date de publication").'</th>';
						$h .= '<th>'.s("Statut").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';
					foreach($cNews as $eNews) {

						$h .= '<tr>';

							$h .= '<td>';
								$h .= $eNews->quick('title', encode($eNews['title']));
							$h .= '</td>';

							$h .= '<td class="website-page-content">';
								if($eNews['content'] === NULL) {
									$h .= '<a href="/website/news:update?id='.$eNews['id'].'" class="btn btn-primary">'.\Asset::icon('pencil-fill').' '.s("Rédiger").'</a>';
								} else {
									$h .= '<a href="/website/news:update?id='.$eNews['id'].'" class="btn btn-primary">'.\Asset::icon('pencil-fill').'&nbsp;&nbsp;<small>'.s("{value} Ko", round(strlen($eNews['content']) / 1024, 2)).'</small></a>';
								}
							$h .= '</td>';

							$h .= '<td '.($eNews['isPublished'] ? '' : 'title="'.s("Pas encore publié").'"').'>';
								$h .= $eNews->quick('publishedAt', \util\DateUi::numeric($eNews['publishedAt'], \util\DateUi::DATE_HOUR_MINUTE));
								if($eNews['isPublished'] === FALSE) {
									$h .= ' '.\Asset::icon('watch');
								}
							$h .= '</td>';

							$h .= '<td>';
								$h .= match($eNews['status']) {
									News::READY => '<span class="color-success">'.\Asset::icon('check-lg').' '.s("Prête").'</span>',
									News::DRAFT => '<span class="color-muted">'.\Asset::icon('pencil-fill').' '.s("Brouillon").'</span>',
								};
							$h .= '</td>';

							$h .= '<td class="text-end">';
								$h .= '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
								$h .= '<div class="dropdown-list bg-primary">';
									$h .= '<div class="dropdown-title">'.s("Actualité").'</div>';
									$h .= '<a href="/website/news:update?id='.$eNews['id'].'" class="dropdown-item">'.s("Modifier").'</a>';
									$h .= match($eNews['status']) {
										News::DRAFT => '<a data-ajax="/website/news:doUpdateStatus" post-id="'.$eNews['id'].'" post-status="'.News::READY.'" class="dropdown-item">'.s("Passer de brouillon à prête").'</a>',
										News::READY => '<a data-ajax="/website/news:doUpdateStatus" post-id="'.$eNews['id'].'" post-status="'.News::DRAFT.'" class="dropdown-item" data-confirm="'.s("L'actualité deviendra inaccessible au public, voulez-vous continuer ?").'">'.s("Revenir en brouillon").'</a>'
									};
									$h .= '<div class="dropdown-divider"></div>';
									$h .= '<a data-ajax="/website/news:doDelete" post-id="'.$eNews['id'].'" class="dropdown-item" data-confirm="'.s("L'actualité sera supprimée, êtes-vous sûr de vouloir continuer ?").'">'.s("Supprimer définitivement").'</a>';
								$h .= '</div>';
							$h .= '</td>';

						$h .= '</tr>';

					}
				$h .= '</tbody>';
			$h .= '</table>';

			$h .= '</div>';

		}

		return $h;

	}

}
?>
