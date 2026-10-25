<?php
/**
 * Affichage d'une page de navigation
 */
class BaseTemplate extends RootTemplate {

	use \Notifiable;

	public string $template = 'base ';

	public function __construct() {

		parent::__construct();

		self::buildAssets();

		\Asset::css('main',  'font-bitter.css');
		\Asset::css('main',  'template-base.css');

	}

	/**
	 * Le mot `presentation` est posé par le gabarit (DocTemplate) ou ajouté par la vue, qui s'exécute
	 * après le constructeur : la feuille des pages publiques se charge donc au rendu, avant que les
	 * assets partent avec la page ou la réponse ajax.
	 */
	protected function buildHtml(string $stream): string {

		$this->buildPresentation();

		return parent::buildHtml($stream);

	}

	protected function buildAjax(string $stream): AjaxTemplate {

		$this->buildPresentation();

		return parent::buildAjax($stream);

	}

	private function buildPresentation(): void {

		if(in_array('presentation', explode(' ', $this->template), TRUE)) {
			\Asset::css('main', 'template-presentation.css');
		}

	}

	protected function getHeader(): string {

		$h = '';

		if($this->header === NULL and $this->title !== NULL) {
			$h .= '<div class="container">';
				$h .= '<h1>'.$this->title.'</h1>';
			$h .= '</div>';
		} else  if($this->header !== '') {
			$h .= '<div class="container">';
				$h .= $this->header;
			$h .= '</div>';
		}

		return $h;

	}

	protected function getMain(string $stream):string {

		if($this->main) {

			return $this->main;

		} else {

			$h = '';

			if($this->mainContainer) {
				$h .= '<div class="container">'.$stream.'</div>';
			} else {
				$h .= $stream;
			}

			return $h;

		}

	}

	protected function getNav(): string {

		$h = '<div class="root-nav-wrapper">';

			$h .= '<div class="container">';

				$h .= '<div class="root-nav-title">';
				if(OTF_DEMO) {
					$h .= '&nbsp;&nbsp;<a href="'.Lime::getUrl().'" class="btn btn-outline-primary" style="display: inline-block">'.Asset::icon('escape').' '.s("Quitter la démo").'</a>';
				} else {
					$h .= $this->getLogo();
				}
				$h .= '</div>';

				$h .= '<div class="root-nav-actions">';

				if($this->data->isLogged) {

					if($this->data->userDeletedAt) {

							$h .= '<div class="base-nav-deleted">';
							$h .= '<a href="'.RootTemplate::getSiteUrl('/main/account').'" class="btn btn-danger" title="'.new user\DropUi()->getCloseMessage($this->data->userDeletedAt).'"><span>'.\Asset::icon('exclamation-circle-fill').' '.s("Compte en cours de suppression").'</span></a>';
							$h .= '</div>';

					}

					$h .= '<div class="nav-account">';

						$h .= '<a class="nav-user nav-item" data-dropdown="bottom" data-dropdown-hover="true">';
							$h .= \user\UserUi::getVignette($this->data->eUserOnline, '1.75rem').'  ';
							$h .= \Asset::icon('chevron-down');
						$h .= '</a>';

						$h .= $this->getUserMenu($this->data);

					$h .= '</div>';

				} else {

					// Les fonctionnalités et les offres se présentent dans le package presentation, que l'export public retire
					if(\structure\StructureSetting::hasPresentationPackage()) {

						$h .= '<div class="nav-item">';
							$h .= '<label for="menu-toggler-checkbox" class="base-nav-link">';
								$h .= '<span class="hide-xs-down">';
									$h .= s("Fonctionnalités");
									$h .= Asset::icon('chevron-down', ['class' => 'hide-sm-down']);
								$h .= '</span>';
								$h .= '<span class="hide-sm-up">';
									$h .= Asset::icon('list', ['class' => 'font-xl']);
								$h .= '</span>';
							$h .= '</label>';
						$h .= '</div>';

						$h .= '<span class="hide-xs-down">'.$this->getNavItem(s("Offres"), Lime::getUrl().'/presentation/offres').'</span>';

					}

					if(\learning\LearningSetting::$active) {
						$h .= '<span class="hide-xs-down">'.$this->getNavItem(s("Formations"), Lime::getUrl().'/formations').'</span>';
					}

					$label = '<span class="hide-sm-up">'.s("Inscription").'</span>';
					$label .= '<span class="hide-xs-down">'.s("S'inscrire").'</span>';
					$h .= $this->getNavItem($label, Lime::getUrl()."/inscription", 'base-nav-link-margin-sign-up');

					// Sur la page de connexion, le lien rouvrirait le même formulaire en panneau par-dessus elle
					$logIn = '/user/log:form';

					if(LIME_REQUEST_PATH !== $logIn) {
						$label = '<span class="hide-sm-up">'.s("Connexion").'</span>';
						$label .= '<span class="hide-xs-down">'.s("Se connecter").'</span>';
						$h .= $this->getNavItem($label, $logIn, 'btn btn-primary');
					}

				}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		// Le menu des fonctionnalités vit dans le package presentation, que l'export public retire
		if(\structure\StructureSetting::hasPresentationPackage()) {

			if($this->data->isLogged === FALSE) {
				$h .= '<input type="checkbox" name="menu-toggler-checkbox" id="menu-toggler-checkbox">';
			}

			$h .= implode('', self::notify('main\\getBaseMenu'));

		}

		return $h;

	}

	protected function getNavItem(string $title, string $href, string $extraClassLink = ""): string {

		$h = '<div class="nav-item">';
			$h .= '<a href="'.$href.'" class="base-nav-link '.$extraClassLink.'">';
				$h .= $title;
			$h .= '</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getFooter() {

		$h = '';

		if($this->footer === NULL) {
			$h .= $this->getDefaultFooter();
		} else {
			$h .= $this->footer;
		}

		$h .= parent::getFooter();

		return $h;

	}


}
?>
