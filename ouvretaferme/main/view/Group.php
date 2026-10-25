<?php
/**
 * Le gabarit d'un collectif vu de l'intérieur : un bandeau à sa couleur, son nom en titre, sa rangée
 * d'onglets en bas du bandeau, la bande claire qui porte le titre de l'écran, puis l'écran. Un groupe
 * (`CommunityTemplate`) et une AMAP (`AmapTemplate`) le partagent, chacun avec ses onglets, ses
 * commandes, sa teinte et ce qu'il pose sous la rangée : la forme du bandeau et de la bande ne se
 * recopie pas d'un gabarit à l'autre, sinon les deux divergent au premier réglage.
 */
abstract class GroupTemplate extends BaseTemplate {

	public ?string $mainTitle = NULL;

	public string $mainAction = '';

	/**
	 * Ce que la bande d'un écran de groupe pose sous son titre (`\main\DesignUi::getPageMasthead()`) :
	 * les onglets de l'écran en `.tab-item` rendus, leur repli, le filtre à leur droite et les chiffres
	 * de l'écran. Des onglets de `Lime.Tab` nomment dans `mainTabsAttributes` le `.tabs` que le
	 * gabarit pose autour de la bande et du corps, où sont leurs panneaux.
	 */
	public string $mainTabs = '';

	public array $mainTabsAttributes = [];

	public bool $mainTabsFold = FALSE;

	public string $mainFilter = '';

	public string $mainFigures = '';

	public function __construct() {

		parent::__construct();

		\Asset::css('main', 'template-community.css');

		self::buildAssets();

	}

	/** L'attribut `style` du bandeau, qui pose sa couleur, ou la chaîne vide. */
	abstract protected function getBandStyle(): string;

	/** Le surtitre du bandeau, ou la chaîne vide. */
	abstract protected function getBandType(): string;

	/** La rangée d'onglets du bandeau et son repli sous 768 px. */
	abstract protected function getBandTabs(): string;

	/** Le menu de la personne, au bout de la rangée. */
	abstract protected function getBandActions(): string;

	/** Ce que la barre claire sous le bandeau porte, ou la chaîne vide pour ne pas la rendre. */
	abstract protected function getBandScope(): string;

	/** La photo du bandeau, ou la chaîne vide. */
	protected function getBandMedia(): string {
		return '';
	}

	/** Le contenu de l'écran, sous la bande claire. */
	protected function getContent(string $stream): string {
		return $stream;
	}

	/** La vignette de la bande claire, l'icône de l'onglet ouvert, ou la chaîne vide. */
	abstract protected function getMastheadIcon(): string;

	/** La classe de la bande claire, qui pose sa teinte, ou la chaîne vide. */
	abstract protected function getMastheadClass(): string;

	/**
	 * L'attribut `style` qui donne à la bande claire et au corps de l'écran la couleur du collectif,
	 * même sous une photo, où le bandeau ne la porte plus (`getBandStyle()`) : la vignette en tire son
	 * encre, sur le blanc de la vignette et non sur la photo, et une bande util-start du corps son bord.
	 */
	abstract protected function getMastheadStyle(): string;

	/** La classe du bandeau, ou la chaîne vide. */
	protected function getBandClass(): string {
		return '';
	}

	protected function getHeader(): string {
		return '';
	}

	protected function getMain(string $stream): string {

		$screen = $this->getMasthead().$this->getBody($stream);

		// Les onglets de la bande et leurs panneaux, dans le corps, partagent ce `.tabs` : `Lime.Tab`
		// remonte de l'onglet au `.tabs` le plus proche et n'y bascule que ses propres panneaux.
		if($this->mainTabsAttributes !== []) {
			$screen = '<div class="tabs" '.attrs($this->mainTabsAttributes).'>'.$screen.'</div>';
		}

		return $this->getBand().$screen;

	}

	// La bande sort du .container, qui borne la largeur du contenu : l'aplat va d'un bord à l'autre,
	// ce qu'il contient reste aligné sur le reste de la page.
	protected function getBand(): string {

		$class = $this->getBandClass();

		$h = '<div class="community-header'.($class === '' ? '' : ' '.$class).'"'.$this->getBandStyle().'>';

			$h .= $this->getBandMedia();

			$h .= '<div class="container">';

				$h .= '<div class="community-header-inner">';

					// La ligne du surtitre se rend toujours, même vide : un groupe sans type n'en a rien
					// à mettre, et la rendre conditionnellement ferait respirer la bande de six
					// pixels d'un écran au suivant. Sa hauteur plancher est en CSS.
					$h .= '<div class="community-main-back">';

						$type = $this->getBandType();

						if($type !== '') {
							$h .= '<span class="util-scope-line-title util-scope-line-title-sm">'.$type.'</span>';
						}

					$h .= '</div>';

					$h .= '<div class="community-main-actions">';
						$h .= $this->mainAction;
					$h .= '</div>';

				$h .= '</div>';
				$h .= '<h1>'.encode($this->data->eStructure['name']).'</h1>';

				// La rangée est le bas de la bande : son soulignement actif touche la limite de
				// l'aplat et sépare la navigation du contenu, au lieu de flotter au-dessus.
				// Les commandes vivent au bout de la rangée d'onglets, sur la même ligne de base :
				// le coin haut droit du bandeau est rendu au nom du collectif et à sa photo.
				// La pastille claire de l'onglet ouvert annonce la bande claire qui suit (N1, 22/10).
				$h .= '<div class="community-header-tabs community-tabs-pill">';

					$h .= $this->getBandTabs();

					// ⚠️ L'administration a rejoint le menu de la personne : elle avait ici un bouton
					// à elle, qui ressemblait à un onglet sans en être un et faisait deux menus
					// voisins pour un seul lecteur.
					$h .= '<div class="community-header-tabs-actions">';

						if(
							\notification\NotificationSetting::isEnabled($this->data->eStructure) and
							array_key_exists($this->data->eStructure['id'], $this->data->notificationsUnseen)
						) {
							$h .= new \notification\NotificationUi()->getBell($this->data->eStructure, $this->data->notificationsUnseen[$this->data->eStructure['id']]);
						}

						$h .= $this->getBandActions();

					$h .= '</div>';

				$h .= '</div>';

			$h .= '</div>';

			// ⚠️ La barre claire **prolonge le bandeau** : elle est sœur du container et porte le
			// sien, jamais imbriquée dedans — `.container` se dimensionne sur l'écran, et deux
			// imbriqués débordent sans rien dire. Le média du bandeau est en `inset: 0`, donc la
			// photo la traverse déjà sans qu'il y ait rien à faire.
			$scope = $this->getBandScope();

			// La bande ne se rend pas vide : son fond et son filet laisseraient une lisière blanche
			// de vingt pixels sous les onglets, mesurée, là où il n'y a rien à dire.
			if($scope !== '') {

				$h .= '<div class="community-header-scope">';
					$h .= '<div class="container">'.$scope.'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La bande claire en tête de l'écran, qui porte son titre, ou la chaîne vide pour un écran sans
	 * titre : le tableau de bord d'une AMAP en saison pose sa propre bande dans le corps.
	 */
	protected function getMasthead(): string {

		$header = $this->getTitle();

		if($header === '') {
			return '';
		}

		$h = '<div class="community-masthead"'.$this->getMastheadStyle().'>';

			$h .= \main\DesignUi::getPageMasthead(
				$header,
				$this->getMastheadIcon(),
				$this->mainTabs,
				$this->mainFilter,
				$this->mainFigures,
				$this->mainTabsFold,
				$this->getMastheadClass()
			);

		$h .= '</div>';

		return $h;

	}

	// Un écran qui pose ses propres `.container` (`mainContainer`, le tableau de bord de l'AMAP) ne
	// s'enferme pas dans celui du gabarit : `.container` se dimensionne sur l'écran, et deux
	// imbriqués débordent.
	protected function getBody(string $stream): string {

		$h = '<div class="community-wrapper"'.$this->getMastheadStyle().'>';

			$h .= '<div class="community-main">';

				$h .= '<div class="community-main-content">';
					$h .= $this->getContent($stream);
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return ($this->mainContainer === FALSE) ? $h : '<div class="container">'.$h.'</div>';

	}

	/** La tête de l'écran, que la bande claire porte, ou la chaîne vide pour ne pas rendre la bande. */
	public function getTitle(): string {

		return $this->mainTitle ?? (($this->title === NULL) ? '' : $this->getTitleDefault());

	}

	/** La tête d'un écran qui n'a pas posé de `mainTitle`. */
	protected function getTitleDefault(): string {
		return \main\DesignUi::getPageHeader($this->title);
	}

}
?>
