<?php
/**
 * Structure commune pour OTF
 */
class RootTemplate extends SmartTemplate {

	use \Notifiable;

	/**
	 * Lang
	 */
	public string $lang = 'fr';

	/**
	 * Template
	 */
	public string $template = 'default';

	/**
	 * Page title
	 */
	public ?string $title = NULL;

	/**
	 * Text for the description meta tag
	 */
	public ?string $metaDescription = NULL;

	/**
	 * Enable noindex meta tag
	 */
	public bool $metaNoindex = FALSE;

	/**
	 * Canonical URL
	 */
	public ?string $canonical = NULL;

	/**
	 * OpenGraph data
	 */
	public array $og = [];

	/**
	 * Base URL
	 */
	public ?string $base = NULL;

	/**
	 * Header content
	 */
	public ?string $header = NULL;

	/**
	 * Main content
	 */
	public ?string $main = NULL;

	/**
	 * Main container ?
	 */
	public bool $mainContainer = TRUE;

	/**
	 * Display something in the footer
	 */
	public ?string $footer = NULL;

	/**
	 * Admin page
	 */
	public ?string $admin = NULL;

	/**
	 * Favicon
	 */
	public string $favicon;

	public ?string $section = NULL;

	public function __construct() {

		\Asset::css('util', 'lime.css');

		\Asset::css('main', 'reboot.css');
		\Asset::css('main', 'component.css');
		\Asset::css('main', 'juicinessAi.css');

		parent::__construct();

		\Asset::js('main', 'juicinessAi.js');

		$this->base = \Lime::getProtocol().'://'.SERVER('HTTP_HOST');
		$this->favicon = \Asset::getPath('main', LIME_ENV === 'dev' ? 'favicon-dev.png' : 'favicon.png', 'image');

		$script = '';

		$success = GET('success');

		if($success) {
			$script .= 'Lime.Alert.showStaticSuccess("'.addcslashes(util\TextUi::success($success), '"').'");';
		}

		$error = GET('error', '?string');

		if($error) {

			$options = $this->data->errorOptions ?? [];
			$script .= 'Lime.Alert.showStaticError("'.addcslashes(util\TextUi::error($error, $options), '"').'");';

		}

		if($script) {
			\Asset::jsContent('<script>
				document.addEventListener("DOMContentLoaded", () => {
					'.$script.'
				})
			</script>');
		}

	}

	protected function buildAssets(): void {

		\Asset::css('main', 'root.css');

		\Asset::css('util', 'font-open-sans.css');
		\Asset::css('util', 'font-roboto-mono.css');
		\Asset::css('main', 'font-bricolage-grotesque.css');
		\Asset::css('main',  'font-dm-sans.css');

	}

	protected function getTemplate(): string {
		return $this->template;
	}

	protected function buildHtml(string $stream): string {

		$nav = $this->getNav();
		$header = $this->getHeader();
		$main = $this->getMain($stream);
		$footer = $this->getFooter();

		$h = '<!DOCTYPE html>';
		$h .= '<html lang="'.$this->lang.'">';

		$h .= '<head>';
			$h .= $this->getHead();
			$h .= Asset::importHtml();
		$h .= '</head>';

		$h .= '<body data-template="'.$this->getTemplate().'" '.(OTF_DEMO ? 'data-demo' : '').' '.($this->admin !== NULL ? 'data-admin' : '').' '.($this->data->eUserOnline->notEmpty() ? 'data-nav="'.$this->data->eUserOnline['nav'].'"' : '').'>';

			$h .= '<nav id="root-nav">';
				$h .= $nav;
			$h .= '</nav>';

			$h .= '<header>';
				$h .= $header;
			$h .= '</header>';
			$h .= '<main>';
			 	$h .= $main;
			$h .= '</main>';
			$h .= '<footer>';
				$h .= $footer;
			$h .= '</footer>';

		$h .= '</body>';

		$h .= '</html>';

		return $h;

	}

	protected function buildAjax(string $stream): AjaxTemplate {

		[$ogSiteName, $ogDescription, $ogTitle, $ogType, $ogUrl, $ogImg] = $this->getOg();

		$t = new AjaxTemplate();
		$t->copyInstructions($this);
		
		$t->qs('body')->setAttribute('data-template', $this->getTemplate());
		$t->qs('title')->innerHtml(encode($this->title));
		$t->qs('meta[name="description"]')->setAttribute('content', $this->metaDescription);
		$t->qs('meta[property="og:title"]')->setAttribute('content', $ogTitle);
		$t->qs('meta[property="og:description"]')->setAttribute('content', $ogDescription);
		$t->qs('meta[property="og:url"]')->setAttribute('content', $ogUrl);
		$t->qs('meta[property="og:img"]')->setAttribute('content', $ogImg);
		$t->qs('meta[property="og:site_name"]')->setAttribute('content', $ogSiteName);
		$t->qs('meta[property="og:type"]')->setAttribute('content', $ogType);
		$t->qs('nav')->innerHtml($this->getNav());
		$t->qs('main')->innerHtml($this->getMain($stream));
		$t->qs('footer')->innerHtml($this->getFooter());

		$this->buildAjaxHeader($t);
		$this->buildAjaxScroll($t);

		return $t;

	}

	protected function buildAjaxScroll(AjaxTemplate $t): void {

		if(server_exists('HTTP_X_REQUESTED_HISTORY') === FALSE) {
			$t->package('main')->resetScroll();
		}

	}

	protected function buildAjaxHeader(AjaxTemplate $t): void {
		$t->qs('header')->innerHtml($this->getHeader());
	}

	/**
	 * Build metadata for HTML document
	 * Must not be called within AJAX navigation query
	 *
	 * @param stdClass $data
	 * @return string
	 */
	protected function getHead(): string {

		[$ogSiteName, $ogDescription, $ogTitle, $ogType, $ogUrl, $ogImg] = $this->getOg();

		$this->canonical ??= LIME_REQUEST_PATH;

		$h = '';
		if($this->base !== NULL) {
			$h .= '<base href="'.$this->base.'">';
		}
		$h .= '<link rel="canonical" href="'.$this->canonical.'"/>';
		$h .= '<title>'.encode($this->title ?? '').'</title>';
		$h .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

		if($this->metaDescription !== NULL) {
			$h .= '<meta name="description" content="'.encode($this->metaDescription).'" />';
		}

		$h .= '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';

		if($this->favicon !== NULL) {
			$h .= '<link rel="icon" href="'.$this->favicon.'"/>';
			$h .= '<link rel="apple-touch-icon-precomposed" type="image/png" href="'.$this->favicon.'"/>';
		}


		if($this->metaNoindex) {
			$h .= '<meta name="robots" content="noindex"/>';
		}

		// Open graph data
		$h .= '<meta property="og:title" content="'.encode($ogTitle).'"/>';
		$h .= '<meta property="og:site_name" content="'.encode($ogSiteName).'"/>';
		$h .= '<meta property="og:url" content="'.$ogUrl.'"/>';
		$h .= '<meta property="og:image" content="'.$ogImg.'"/>';
		$h .= '<meta property="og:type" content="'.$ogType.'"/>';
		$h .= '<meta property="og:description" content="'.encode($ogDescription).'"/>';
		$h .= '<meta property="og:locale" content="'.L::getLang().'"/>';

		return $h;

	}

	protected function getOg(): array {

		$ogUrl = Lime::getUrl().LIME_REQUEST_PATH;
		$ogSiteName = $this->og['siteName'] ?? \Lime::getName();
		$ogImg = $this->og['image'] ?? NULL;

		$ogType = $this->og['type'] ?? 'website';
		$ogDescription = $this->og['description'] ?? $this->metaDescription;
		$ogTitle = $this->og['title'] ?? $this->title;

		return [$ogSiteName, $ogDescription, $ogTitle, $ogType, $ogUrl, $ogImg];

	}

	protected function getNav(): string {
		return '';
	}

	protected function getHeader(): string {
		return '';
	}

	protected function getMain(string $stream): string {
		return '';
	}

	protected function getFooter() {

		$h = '';

		if(
			$this->data->eUserOnline->notEmpty() and
			$this->data->logInExternal !== NULL
		) {
			$h .= new user\UserUi()->logOutExternal($this->data->logInExternal[0]);
		}

		if($this->data->isTab) {
			$h .= new user\TabUi()->badge($this->data->eUserOnline);
		}

		if($this->data->browserObsolete) {
			$h .= '<div class="util-block-warning ml-1 mr-1">'.$this->getWarningObsoleteBrowser().'</div>';
		}

		$h .= $this->getTos();

		return $h;

	}

	protected function getTos(): string {

		if($this->data->tos === NULL) {
			return '';
		}

		return new \user\TosAiUi()->getTrigger();

	}

	protected function getFooterUsageLinks(): array {

		return [
			['title' => s("Qui sommes-nous ?"), 'href' => \main\MainSetting::URL_ASSOCIATION],
			['title' => s("Mentions légales"), 'href' => self::getSiteUrl('/presentation/mentions-legales')],
			['title' => s("Nous contacter"), 'href' => \main\MainSetting::URL_ASSOCIATION.'/nous-contacter'],
			['title' => s("Conditions d'utilisation"), 'href' => self::getSiteUrl('/presentation/service')],
			['title' => s("Conformité légale"), 'href' => self::getSiteUrl('/presentation/conformite')],
		];

	}

	protected function getDefaultFooter() {

		$h = '';

		if($this->footer === NULL) {
			$h .= '<div class="util-footer font-sm">';

				$h .= \main\DesignUi::getBrushSeparator('top');

				$h .= '<div class="container">';

					$h .= '<div class="footer-content">';

						$h .= '<div class="footer-content-text">';
							$h .= $this->getLogo();
						$h .= '</div>';

						$h .= '<div class="footer-content-links">';

							$h .= '<div>';

								$h .= $this->getFooterBlocLinks(s("Ressources"), [
									['title' => s("Blog"), 'href' => 'https://blog.ouvretaferme.org', 'target' => '_blank'],
									['title' => s("Documentation"), 'href' => self::getSiteUrl('/doc'), 'target' => '_blank'],
									['title' => s("Aide à la comptabilité"), 'href' => self::getSiteUrl('/articles/')],
									['title' => s("Questions fréquentes"), 'href' => self::getSiteUrl('/presentation/faq')],
									['title' => s("Explorer la ferme démo"), 'href' => \structure\Structure::DEMO_URL.'/series?view=area'],
								]);

							$h .= '</div>';

							$h .= '<div>';

								$h .= $this->getFooterBlocLinks(s("Infolettre"), [
									['title' => s("S'inscrire à l'infolettre"), 'href' => self::getSiteUrl('/infolettre')],
								]);

								$h .= $this->getFooterBlocLinks(s("Discord"), [
									['title' => s("Salon de discussion"), 'href' => \main\MainSetting::URL_DISCORD_CHANNEL, 'target' => '_blank'],
									['title' => s("Recevoir une invitation"), 'href' => \main\MainSetting::URL_DISCORD_INVITE, 'target' => '_blank'],
								]);

							$h .= '</div>';

							$h .= '<div>';

								$h .= $this->getFooterBlocLinks(s("Utilisation"), $this->getFooterUsageLinks());

								$h .= '</div>';

						$h .= '</div>';

					$h .= '</div>';

				$h .= '</div>';
			$h .= '</div>';

		} else {
			$h .= $this->footer;
		}

		return $h;

	}

	public static function getSiteUrl(string $path, ?string $host = LIME_HOST): string {
		return \main\SiteAiUi::url($path, $host);
	}

	protected function getLogo(): string {

		$h = '<a class="footer-logo" href="'.Lime::getUrl().'">';
			$h .= \Asset::svg('main', 'svg/logo-otf.svg');
			$h .= s('{siteName}');
		$h .= '</a>';

		return $h;

	}

	protected function getUserMenu($data): string {

		// Dans une structure, son nom ouvre la liste des structures, recherche comprise
		// (`StructureTemplate::getNav()`) : le menu du compte ne la répète pas.
		$selector = ($this instanceof StructureTemplate);

		$h = '<div class="dropdown-list '.($selector ? '' : self::getStructureColumns($data->nStructureUser)).' bg-primary">';

			$h .= '<div class="dropdown-title">'.$data->eUserOnline->getName().'</div>';

			$h .= '<a href="'.Lime::getUrl().'" class="dropdown-item">'.Asset::icon('house-fill').'  '.s("Accueil").'</a>';

			if(Lime::getHost() === LIME_HOST) {
				$h .= '<a href="'.self::getSiteUrl('/main/account').'" class="dropdown-item">'.Asset::icon('person-fill').'  '.s("Mon compte").'</a>';
			} else {
				$h .= '<a href="'.self::getSiteUrl('/main/account').'" class="dropdown-item" target="_blank">'.Asset::icon('person-fill').'  '.s("Mon compte").'</a>';
			}

			if($data->hasLearning) {
				$h .= '<a href="'.self::getSiteUrl('/mes-formations').'" class="dropdown-item">'.Asset::icon('mortarboard-fill').'  '.s("Mes formations").'</a>';
			}

			if(
				$selector === FALSE and
				$data->nStructureUser > 1
			) {
				$h .= '<div class="dropdown-divider"></div>';
				$h .= $this->getStructureLinks();
			}

			if(OTF_DEMO === FALSE) {

				$h .= '<div class="dropdown-divider"></div>';

				$h .= '<form method="post" action="'.Lime::getUrl().'/user/log:out">';
					$h .= '<button type="submit" class="dropdown-item">'.Asset::icon('escape').'  '.s("Me déconnecter").'</button>';

					if(Lime::getHost() === LIME_HOST) {
						$redirect = Lime::getProtocol().'://'.SERVER('HTTP_HOST');
					} else if(\structure\StructureSetting::hasCommercialisationApp() and isset($data->eShop) /* 404 */) {
						// Le domaine des boutiques appartient au package shop, que l'export public retire
						$redirect = self::notify('main\\getLogoutRedirect', $data->eShop)[0] ?? Lime::getUrl();
					} else {
						$redirect = Lime::getUrl();
					}

					$h .= '<input type="hidden" name="redirect" value="'.$redirect.'"/>';
				$h .='</form>';

				// Les postes de caisse que cet appareil tient se rendent avant la déconnexion. La caisse appartient au
				// package selling, que l'export public retire
				if(\structure\StructureSetting::hasCommercialisationApp()) {
					$h .= implode('', self::notify('main\\getLogoutGuard', $data->eUserOnline, $data->ccStructureUser));
				}

				if(\user\ConnectionLib::getOnline()->isAdmin()) {
					$h .= '<div class="dropdown-divider"></div>';
					$h .= '<a href="'.Lime::getUrl().'/user/admin/" class="dropdown-item">'.\Asset::icon('server').' '.s("Administrer").'</a>';
				}

			}

		$h .= '</div>';

			return $h;

	}

	protected function getStructureLinks(): string {

		$h = '';

		foreach($this->data->ccStructureUser as $cStructure) {

			if($h !== '') {
				$h .= '<div class="dropdown-divider"></div>';
			}

			$h .= '<div class="dropdown-subtitle">'.\structure\StructureUi::getText($cStructure->first(), 'my', $cStructure->count()).'</div>';

			foreach($cStructure as $eStructure) {

				$url = \structure\StructureUi::getMenuUrl($eStructure, $this->section);

				if($url !== NULL) {
					$h .= \structure\StructureUi::getDropdownItem($eStructure, $url, FALSE);
				}

			}

		}

		return $h;

	}

	protected static function getStructureColumns(int $number): string {

		if($number > 10) {
			return 'dropdown-list-3';
		} else if($number > 5) {
			return 'dropdown-list-2';
		} else {
			return '';
		}

	}

	protected function getWarningObsoleteBrowser(): string {

		$alert = '<div>';
			$alert .= s("Attention, vous utilisez un vieux navigateur qui n'est pas compatible avec ce site. Merci d'utiliser un navigateur plus récent : <linkChrome>Google Chrome 111+</linkChrome>, <linkFirefox>Firefox 113+</linkFirefox> ou <linkEdge>Edge 111+</linkEdge>. Sur iPhone et iPad, c'est la version d'iOS qui compte : iOS 16 au minimum.",
			['linkChrome' => '<a href="https://www.google.com/chrome/">', 'linkFirefox' => '<a href="https://www.getfirefox.com/">', 'linkEdge' => '<a href="https://www.microsoft.com/edge">']);
		$alert .= '</div>';

		return $alert;

	}

	protected function getFooterBlocLinks(string $titre, array $links): string {

		$h = '<div class="footer-block-links">';

			$h .= '<h3>'.$titre.'</h3>';

			$h .= '<ul>';

			foreach($links as $link) {
				$h .= '<li><a '.(isset($link['target'])?'target="'.$link['target'].'"':'').'href="'.$link['href'].'">'.$link['title'].'</a></li>';
			}

			$h .= '</ul>';

		$h .= '</div>';

		return $h;

	}

}

?>
