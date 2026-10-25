<?php
/**
 * Pour les sites internet des fermes
 */
class WebsiteTemplate extends RootTemplate {

	/**
	 * Header content
	 */
	public ?string $header = NULL;

	/**
	 * Main content
	 */
	public ?string $main = NULL;

	/**
	 * Display something in the footer
	 */
	public ?string $footer = NULL;

	protected function buildHtml(string $stream): string {

		$customize = (
			$this->data->eWebsite->canWrite() and
			get_exists('customize')
		);

		$this->base = $customize ?
			\website\WebsiteUi::url($this->data->eWebsite, '/', internalDomain: TRUE) :
			\website\WebsiteUi::url($this->data->eWebsite, '/');

		if($this->data->eWebsite['favicon']) {
			$this->favicon = new \media\WebsiteFaviconUi()->getUrlByElement($this->data->eWebsite, 'm');
		}

		$position = $customize ? \website\Website::GET('customDesignPosition', 'customDesignPosition', $this->data->eWebsite['customDesignPosition']) : $this->data->eWebsite['customDesignPosition'];

		Asset::css('main', 'template-website.css');

		if($position === \website\Website::LEFT) {
			Asset::css('website', 'design-left.css');
		}

		Asset::css('website', 'design-submenu.css');

		Asset::css('website', \website\DesignUi::getCSSFile($this->data->eWebsite));

		Asset::css('website', 'design-content.css');

		Asset::cssContent('<style>
@import url("https://fonts.googleapis.com/css2?family=PT+Serif:ital,wght@0,400;0,700;1,400;1,700&display=swap");
</style>');

		if(
			$this->data->eWebpage->empty() or
			$this->data->eWebpage['public'] === FALSE
		) {
			$this->metaNoindex = TRUE;
		}

		$this->og['siteName'] = $this->data->eWebsite['name'];

		$nav = $this->getNav();
		$header = $this->getHeader();
		$main = $this->getMain($stream);
		$footer = $this->getFooter();


		if($customize) {

			$customizeText = \website\Website::GET('customText', 'customText', $this->data->eWebsite['customText']);
			$customizeBackground = \website\Website::GET('customBackground', 'customBackground', $this->data->eWebsite['customBackground']);

			$customizeAttributes = 'data-text="'.$customizeText.'" data-background="'.strtoupper($customizeBackground).'"';
			$customizePanel = $this->getCustomize($position, $customizeText, $customizeBackground);

		} else {
			$customizeAttributes = '';
			$customizePanel = '';
		}


		$h = '<!DOCTYPE html>';
		$h .= '<html lang="'.$this->lang.'">';

		$h .= '<head>';
			$h .= $this->getHead();
			$h .= Asset::importHtml();
			$h .= \website\DesignUi::getStyles($this->data->eWebsite);
		$h .= '</head>';

		$h .= '<body data-template="'.$this->getTemplate().'" '.$customizeAttributes.' data-position="'.$position.'" data-touch="no">';

			$h .= '<div class="website-wrapper">';
				$h .= '<nav id="root-nav">'.$nav.'</nav>';
				$h .= '<header>'.$header.'</header>';
				$h .= '<main>'.$main.'</main>';
				$h .= '<footer>'.$footer.'</footer>';
			$h .= '</div>';

			$h .= $customizePanel;

		$h .= '</body>';

		$h .= '</html>';

		return $h;

	}

	protected function getCustomize(string $position, string $text, string $background): string {

		$get = fn(string $property) => \website\Website::GET($property, $property, $this->data->eWebsite[$property]);

		return new \website\CustomizeAiUi()->get(
			$this->data->eWebsite,
			\website\DesignLib::getAll(),
			[
				'customDesign' => $this->data->eWebsite['customDesign']['id'],
				'customDesignPosition' => $position,
				'customWidth' => (int)$get('customWidth'),
				'customText' => $text,
				'customColor' => $get('customColor'),
				'customLinkColor' => $get('customLinkColor') ?: NULL,
				'customBackground' => $background,
				'customFont' => $get('customFont'),
				'customTitleFont' => $get('customTitleFont'),
			]
		);

	}

	protected function getHeader(): string {

		$h = '';
		if($this->title !== NULL) {

			if($this->data->eWebpage->notEmpty() and $this->data->eWebpage['banner'] !== NULL) {
				$banner = new \media\WebpageBannerUi()->getUrlByElement($this->data->eWebpage, 'l');
			} else if($this->data->eWebsite['banner'] !== NULL) {
				$banner = new \media\WebsiteBannerUi()->getUrlByElement($this->data->eWebsite, 'l');
			} else {
				$banner = NULL;
			}

			if($banner !== NULL) {

				$h .= '<div class="website-banner" style="background-image: url('.$banner.')">';

			}

				$h .= '<div class="website-header">';

					$h .= '<h1>'.\website\WebsiteUi::getTitleParts($this->title).'</h1>';

					if(
						get_exists('customize') === FALSE and
						$this->data->eWebsite->canWrite() and
						$this->data->eWebpage->notEmpty()
					) {

						$h .= '<div class="website-admin">';

							$h .= '<a href="'.Lime::getUrl().'/website/webpage:updateContent?id='.$this->data->eWebpage['id'].'" class="btn btn-only-icon btn-secondary" title="'.s("Modifier cette page").'">'.Asset::icon('pencil-fill').'</a>';

							if($this->data->eWebpage['template']['fqn'] === 'news') {
								$h .= ' <a href="'.Lime::getUrl().'/website/manage?id='.$this->data->eWebsite['structure']['id'].'&tab=news" class="btn btn-only-icon btn-secondary" title="'.s("Gérer les actualités").'">'.Asset::icon('newspaper').'</a>';
							}

						$h .= '</div>';

					}

				$h .= '</div>';

			if($banner !== NULL) {

				$h .= '</div>';

			}

		} else if($this->header !== '') {
			$h .= $this->header;
		}

		return $h;

	}

	protected function getMain(string $stream):string {

		if($this->main) {
			return $this->main;
		} else {

			$h = $stream;

			$alerts = [];

			if($this->data->eWebsite['status'] === \website\Website::INACTIVE) {
				$alert = '<p>';
					$alert .= Asset::icon('exclamation-triangle-fill').' '.s("Le site de votre ferme est pour le moment hors ligne, vous seul pouvez le consulter !");
				$alert .= '</p>';
				$alerts[] = $alert;
			}

			if(
				$this->data->eWebpage->notEmpty() and
				$this->data->eWebpage['status'] === \website\Webpage::INACTIVE
			) {
				$alert = '<p>';
					$alert .= Asset::icon('exclamation-triangle-fill').' '.s("Cette page est actuellement hors ligne, vous seul pouvez la consulter !");
				$alert .= '</p>';
				$alerts[] = $alert;
			}

			if($this->data->browserObsolete) {
				$alerts[] = $this->getWarningObsoleteBrowser();
			}

			if($alerts) {
				$h .= '<div class="util-block-warning util-block-sticked">'.implode('', $alerts).'</div>';
			}

			return $h;

		}

	}

	protected function getLogo(): string {

		return \website\WebsiteUi::getLogoImage($this->data->eWebsite);

	}

	protected function getMenuLink(\website\Menu $eMenu, bool $toggles = FALSE): string {

		if(
			$eMenu['url'] === NULL and
			$eMenu['webpage']->empty()
		) {

			$h = '<a class="website-menu-item website-menu-item-plain">'.encode($eMenu['label']).'</a>';

			if($toggles) {
				$h = '<label for="website-submenu-'.$eMenu['id'].'" class="website-submenu-row">'.$h.'</label>';
			}

			return $h;

		}

		if($eMenu['url'] !== NULL) {

			return '<a href="'.encode($eMenu['url']).'" class="website-menu-item" target="_blank">'.encode($eMenu['label']).' '.\website\WebsiteUi::getMenuIcon($eMenu['url']).'</a>';

		}

		$class = $this->isCurrentMenu($eMenu) ? 'selected' : '';

		return '<a href="'.\website\WebsiteUi::path($this->data->eWebsite, '/'.$eMenu['webpage']['url']).'" class="website-menu-item '.$class.'">'.encode($eMenu['label']).'</a>';

	}

	protected function isCurrentMenu(\website\Menu $eMenu): bool {

		return (
			$eMenu['webpage']->notEmpty() and
			$this->data->eWebpage->notEmpty() and
			$eMenu['webpage']['id'] === $this->data->eWebpage['id']
		);

	}

	protected function getNav(): string {

		$hasLogo = ($this->data->eWebsite['logo'] !== NULL);

		$h = '<div class="website-nav '.($hasLogo ? 'website-nav-with-logo' : '').'">';

			if($hasLogo) {
				$h .= '<a href="'.$this->data->url.'" class="website-logo">';
					$h .= $this->getLogo();
				$h .= '</a>';
			} else {
				$h .= '<div class="website-name">';
					$h .= '<a href="'.$this->data->url.'">';
						$h .= encode($this->data->eWebsite['name']);
					$h .= '</a>';
				$h .= '</div>';
			}

			$spaceUrl = \website\WebsiteUi::getSpaceUrl($this->data->eWebsite);
			$hasSpace = ($spaceUrl !== '');

			if($this->data->cMenu->notEmpty() or $hasSpace) {

				$h .= '<div class="website-menu-wrapper">';

					$h .= '<label for="website-menu-input" class="website-menu-open">'.Asset::icon('list').' '.s("Menu").'</label>';

					$h .= '<input type="checkbox" id="website-menu-input"/>';

					$h .= '<ul class="website-menu">';

						$position = 0;

						foreach($this->data->cMenu as $eMenu) {

							$position++;

							$classes = [];

							if($position === 1) {
								$classes[] = 'website-menu-list-first';
							} else if($position === $this->data->cMenu->count() and $hasSpace === FALSE) {
								$classes[] = 'website-menu-list-last';
							}

							$cMenuChild = $eMenu['cMenuChild'] ?? new \Collection();

							if($cMenuChild->notEmpty()) {
								$classes[] = 'website-menu-list-parent';
							}

							$h .= '<li class="'.implode(' ', $classes).'">';

								if($cMenuChild->notEmpty()) {

									$openedByDefault = FALSE;

									foreach($cMenuChild as $eMenuChild) {

										if($this->isCurrentMenu($eMenuChild)) {
											$openedByDefault = TRUE;
										}

									}

									$h .= '<input type="checkbox" class="website-submenu-input" id="website-submenu-'.$eMenu['id'].'"'.($openedByDefault ? ' checked' : '').'/>';

								}

								$h .= $this->getMenuLink($eMenu, toggles: $cMenuChild->notEmpty());

								if($cMenuChild->notEmpty()) {

									$h .= '<label for="website-submenu-'.$eMenu['id'].'" class="website-submenu-open" title="'.s("Afficher le sous-menu").'">'.Asset::icon('chevron-down').'</label>';

									$h .= '<ul class="website-menu-sub">';

										foreach($cMenuChild as $eMenuChild) {
											$h .= '<li>'.$this->getMenuLink($eMenuChild).'</li>';
										}

									$h .= '</ul>';

								}

							$h .= '</li>';

						}

						if(
							$this->data->eWebpageNews['status'] === \website\Webpage::ACTIVE and
							($this->data->eWebpageNews['inMenu'] ?? FALSE) === FALSE and
							$this->data->cNews->notEmpty()
						) {
							$h .= '<li class="website-menu-list-news">';
								$h .= new \website\NewsUi()->getForMenu($this->data->eWebsite, $this->data->eWebpage, $this->data->eWebpageNews, $this->data->cNews, 3);
							$h .= '</li>';
						}

						if($hasSpace) {
							$h .= '<li class="website-menu-list-last">';
								$h .= '<a href="'.$spaceUrl.'" class="website-menu-item">'.Asset::icon('person-circle').' '.s("Mon espace").'</a>';
							$h .= '</li>';
						}
					$h .= '</ul>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getFooter(): string {

		if($this->footer === NULL) {

			$h = '';

			if($this->data->eWebsite['footer'] !== NULL) {
				$h .= '<div class="website-footer">';
					$h .= new \editor\EditorUi()->value($this->data->eWebsite['footer']);
				$h .= '</div>';
			}

			if($this->data->eWebsite['customDisabledFooter'] === FALSE) {

				$h .= '<div class="website-ad">';
					$link = '<a href="'.Lime::getUrl().'">'.Lime::getDomain().'</a>';

					$h .= $this->data->eWebsite['structure']->isAmap()
						? s("Ce site a été créé avec {link}<br/><small>Logiciel pour organiser une AMAP, des contrats aux distributions</small>", ['link' => $link])
						: s("Ce site a été créé avec {link}<br/><small>Logiciel pour organiser le travail à la ferme de la production à la vente</small>", ['link' => $link]);
				$h .= '</div>';

			}

			return $h;

		} else {
			return $this->footer;
		}

	}

}
?>
