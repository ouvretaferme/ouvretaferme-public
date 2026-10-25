<?php
/**
 * Affichage d'une page de documentation
 */
class DocTemplate extends BaseTemplate {

	use \Notifiable;

	// L'aide est une page publique, au même titre que la FAQ, d'où `presentation` ; LearningAiTemplate
	// partage le châssis `doc` sans ce mot, la fiche d'une formation ayant son propre habillage.
	public string $template = 'doc base presentation ';
	public ?string $header = '';
	public ?string $footer = '';

	public ?string $subTitle = NULL;

	public ?string $stamp = NULL;
	// « Bêta » reprend le cachet neutre que l'application pose sur ce qui est en bêta
	public bool $stampNew = TRUE;

	public ?string $offer = NULL;

	public bool $mainContainer = FALSE;

	public ?string $menuSelected = NULL;

	protected function getMain(string $stream):string {

		Asset::css('main', 'template-doc.css');
		Asset::js('main', 'doc.js');

		$h = '<div class="container">';
			$h .= '<div id="doc-menu-open">';
				$h .= '<a class="btn btn-outline-primary" onclick="Doc.toggleMenu()">'.Asset::icon('list').' '.s("Menu").'</a>';
			$h .= '</div>';
			$h .= '<div class="doc-wrapper">';
				$h .= '<div class="doc-menu">';
					$h .= '<div id="doc-menu-list">';
						$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav util-filter-options-menu">';
							$h .= '<div class="util-filter-options-title">'.s("Généralités").'</div>';
							$h .= $this->menuLink('/doc/', 'mainUse', s("Introduction"));
							$h .= $this->menuLink('/doc/main:help', 'mainHelp', s("Obtenir de l'aide"));
							$h .= $this->menuLink('/doc/main:design', 'mainDesign', s("Principes ergonomiques"));
							$h .= '<div class="util-filter-options-title">'.s("Équipe").'</div>';
							$h .= $this->menuLink('/doc/hr', 'hr', s("Le suivi RH"));
							$h .= '<div class="util-filter-options-title">'.s("Commercialisation").'</div>';
							$h .= implode('', self::notify('main\\getDocMenuSelling', $this));
							$h .= $this->menuLink('/doc/communication:website', 'communicationWebsite', s("Créer un site internet"));
							$h .= implode('', self::notify('main\\getDocMenuShop', $this));
							$h .= implode('', self::notify('main\\getDocMenuAmap', $this, $this->data->eUserOnline));
							if(\structure\StructureSetting::hasAccountingApp()) {
								$h .= implode('', self::notify('main\\getDocMenuInvoicing', $this));
								$h .= implode('', self::notify('main\\getDocMenuAccounting', $this));
							}
							$h .= '<div class="util-filter-options-title">'.s("Importer et exporter des données").'</div>';
							$h .= $this->menuLink('/doc/import:series', 'importSeries', s("Importer un plan de culture"));
							$h .= $this->menuLink('/doc/import:products', 'importProducts', s("Importer des produits"));
							$h .= implode('', self::notify('main\\getDocMenuImport', $this, $this->data->eUserOnline));
							$h .= '<div class="util-filter-options-title">'.s("Divers").'</div>';
							$h .= $this->menuLink('/doc/editor', 'editor', s("Utiliser l'éditeur de texte"));
							$h .= '<div class="util-filter-options-title">'.s("Ressources").'</div>';
							if(\structure\StructureSetting::hasAccountingApp()) {
								$h .= implode('', self::notify('main\\getDocMenuResources'));
							}
							$h .= '<a href="https://ouvretaferme.gitbook.io/ouvretaferme" class="util-filter-option">'.s("Documentation de l'API").'</a>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="doc-main">';
					$h .= '<div class="doc-header">';
						$h .= '<h1>';
							$h .= $this->title;
							if($this->stamp !== NULL) {
								$h .= '<span class="util-stamp'.($this->stampNew ? ' util-stamp-new' : '').' ml-1">'.$this->stamp.'</span>';
							}
							if($this->offer !== NULL) {
								$h .= $this->offer;
							}
						$h .= '</h1>';
						if($this->subTitle !== NULL) {
							$h .= '<h4>'.$this->subTitle.'</h4>';
						}
					$h .= '</div>';
					$h .= '<div class="doc-content">';
						$h .= parent::getMain($stream);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une page de l'aide dans le menu, marquée quand elle est la page lue.
	 */
	public function menuLink(string $url, string $page, string $label): string {

		$selected = ($page === $this->menuSelected);

		return '<a href="'.$url.'" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.$label.'</a>';

	}

	public function anchor(string $id): string {
		return '<a href="'.LIME_REQUEST_PATH.'#'.$id.'" class="doc-anchor">'.Asset::icon('link-45deg').'</a>';
	}

}
?>
