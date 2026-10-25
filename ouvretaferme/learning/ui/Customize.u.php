<?php
namespace learning;

/**
 * La barre de personnalisation des pages publiques d'un réseau, posée sur l'une d'elles, et le
 * thème qu'elle règle.
 *
 * Le réseau habille ses pages en les regardant, comme la boutique et le site : tout se joue dans
 * l'adresse jusqu'à l'enregistrement. Il règle un gabarit (`LearningSetting::DESIGNS`), sa
 * couleur, un fond et deux polices.
 *
 * Le thème vaut pour toutes les pages publiques du réseau (lot theme-reseau) : son accueil, ses
 * formations, y compris intégrées à son site, et ses relevés de prix ; et pour la fiche et les
 * leçons d'une formation du réseau. La barre, jamais en intégration. La carte du paramétrage
 * (`getCard()`) l'ouvre sur l'accueil, ou sur la fiche d'un tutoriel pour le réseau des tutoriels,
 * et le réglage vaut pour toutes ces pages.
 */
class CustomizeUi extends \main\CustomizeUi {

	use \Notifiable;

	/**
	 * Les deux portées du thème : `public`, le conteneur de la page du réseau, de ses étapes
	 * (`DatePublicUi::getPage()`) et de ses leçons (`getPublic()`), et `doc`, le conteneur de la
	 * fiche, menu compris (`LearningAiTemplate`).
	 */
	const SCOPES = [
		'public' => '.learning-public',
		'doc' => '.learning-doc'
	];

	/**
	 * Les pages publiques qui portent la barre, nommées par `origin` dans l'adresse de l'essai, qui
	 * y renvoie : l'accueil du réseau, la liste de ses formations, ses relevés de prix.
	 */
	const ORIGINS = ['home', 'learning', 'quotation'];

	public function __construct() {

		parent::__construct();

		\Asset::js('learning', 'customize.js');

	}

	protected function getJs(): string {
		return 'LearningCustomize';
	}

	/**
	 * L'essai d'une couleur et l'enregistrement rejouent l'état complet porté par `data-settings`, et
	 * non les seuls réglages présents dans l'adresse : un réglage qu'on n'a pas touché ne doit pas être
	 * effacé au passage.
	 *
	 * La barre ne connaît pas la page qui la porte : la liste du réseau, la fiche ou une leçon lui
	 * donnent leurs propres adresses d'aperçu et d'enregistrement, sur leur domaine, pour qu'aucune
	 * requête ne traverse de `reseau.` à `www.`, et celle où « Quitter » ramène.
	 */
	public function get(string $urlPreview, string $urlSave, string $urlExit, array $settings): string {

		$h = '<div id="learning-customize-bar" class="customize-bar customize-bar-bottom" data-url="'.encode($urlSave).'" data-preview="'.encode($urlPreview).'" data-exit="'.encode($urlExit).'" data-settings="'.encode(json_encode($settings['input'])).'">';

			$h .= '<a class="customize-unfold" onclick="LearningCustomize.toggle()">'.\Asset::icon('sliders').'</a>';

			$h .= '<div class="customize-panel">';

				$h .= '<div class="customize-options">';
					$h .= $this->getDesignList(s("Thème"), ['' => s("Classique")] + \structure\CommunityUi::p('customDesign')->values, $settings['customDesign'] ?? '');
					$h .= $this->getColors($settings);
					$h .= $this->getFonts($settings, $settings['designFonts']);
				$h .= '</div>';

				$h .= '<div class="customize-actions">';
					$h .= '<a class="btn btn-lg btn-success customize-save" onclick="LearningCustomize.save()">'.s("Enregistrer").'</a>';
					$h .= '<a class="btn btn-sm customize-cancel" onclick="LearningCustomize.toggle()">'.s("Replier").'</a>';
				$h .= '</div>';

				$h .= '<a class="customize-close" onclick="LearningCustomize.cancel()" title="'.s("Quitter").'">'.\Asset::icon('x-circle').'</a>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre d'une page publique du réseau : l'essai et l'enregistrement sur l'adresse du réseau,
	 * qui vaut pour toutes ses pages, l'essai renvoyant à celle d'où il part (`$origin`), et
	 * « Quitter » à la carte du paramétrage qui l'a ouverte.
	 */
	public function getNetwork(\structure\Structure $eStructureCommunity, \structure\Community $eCommunity, string $origin, array $settings): string {

		return $this->get(
			self::urlPreview($eCommunity, $origin),
			self::urlSave($eCommunity),
			\Lime::getUrl().\structure\StructureUi::urlStructureGeneral($eStructureCommunity).'&tab=structure-administrative',
			$settings
		);

	}

	public static function urlPreview(\structure\Community $eCommunity, string $origin): string {
		return \structure\CommunityUi::url($eCommunity).'/:doPreview?origin='.$origin;
	}

	public static function urlSave(\structure\Community $eCommunity): string {
		return \structure\CommunityUi::url($eCommunity).'/:doCustomize';
	}

	/**
	 * La page publique où un essai renvoie, d'après la barre qui l'a envoyé. Les relevés de prix vivent
	 * dans le code fermé, qui donne leur adresse : la version publique n'en a pas, et une adresse qui
	 * les nomme encore ramène aux formations.
	 */
	public static function urlOrigin(\structure\Community $eCommunity, string $origin): string {

		$urls = array_replace([
			'home' => \structure\CommunityUi::url($eCommunity),
			'learning' => DatePublicUi::urlList($eCommunity),
		], ...self::notify('getCustomizeOrigins', $eCommunity));

		return $urls[$origin] ?? $urls['learning'];

	}

	/**
	 * Le conteneur d'une page publique du réseau, sur lequel le thème pose ses jetons et son gabarit
	 * ses règles : rien sur `body`, dont la navigation et le pied gardent les couleurs du produit, et
	 * dont les jetons survivent à une navigation ajax vers un autre écran. Le bandeau en fait partie,
	 * puisque le gabarit l'habille. La page qui le pose ajoute `learning-public` à son gabarit, pour
	 * que la portée descende jusqu'au pied (`learning.css`). Le thème redéfinit ici la marque :
	 * `util-brand-scope` en recalcule le filet et l'encre des titres, qui sinon garderaient la
	 * couleur de Ouvretaferme résolue sur `:root`, et teinte à la couleur du réseau la bande d'accueil
	 * posée dedans, par le `--primary-on-light` que le thème pose avec la marque
	 * (`.util-brand-scope .util-page-hero`, `reboot.css`).
	 */
	public static function getPublic(?string $design, string $content): string {

		\Asset::css('learning', 'learning.css');

		return '<div class="learning-public util-brand-scope"'.($design === NULL ? '' : ' data-learning-design="'.encode($design).'"').'>'.$content.'</div>';

	}

	/**
	 * La carte « Personnaliser l'apparence » du profil d'un réseau, dans son paramétrage, pour tout
	 * réseau qui a des pages publiques et une page où ouvrir la barre (`CommunityLib::hasCustomize()`) :
	 * elle ouvre la barre sur son accueil, qui mène le gérant aux relevés ou aux formations quand il n'a
	 * rien à montrer, ou sur la fiche d'un tutoriel pour le réseau des tutoriels (`$eCourse`,
	 * `urlCard()`), dont la phrase dit alors ce que le thème habille vraiment, puisque le sommaire
	 * `/tutoriels` ne le lit pas. Elle vit dans le profil parce que la couleur qu'elle règle peint
	 * aussi le bandeau des écrans du groupe sans photo (`StructureUi::getCommunityColorStyle()`), et
	 * elle est le seul endroit où cette couleur se règle : les réglages des relevés ne la proposent
	 * plus.
	 *
	 * Les pastilles montrent les réglages du réseau : sa couleur, ou quand il n'en a pas posé le jeton
	 * `--community` que le bandeau du groupe garde alors à sa valeur par défaut, et le fond choisi de
	 * `$settings`, défaut compris, tel que la barre le montre ; la page le pose adouci. Le socle les
	 * masque sous 767 px.
	 */
	public static function getCard(\structure\Community $eCommunity, array $settings, Course $eCourse = new Course()): string {

		$color = ($settings['color'] === NULL) ? 'var(--community)' : encode($settings['color']);

		$h = '<div class="util-cards util-cards-1">';

			$h .= '<a href="'.self::urlCard($eCommunity, $eCourse).'" class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-icon util-icon-round util-icon-lg util-icon-settings">'.\Asset::icon('palette-fill').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.s("Personnaliser l'apparence").'</div>';
						if($eCourse->empty()) {
							$h .= '<p>'.s("S'applique au bandeau de vos écrans et à toutes vos pages publiques.").'</p>';
						} else {
							$h .= '<p>'.s("S'applique au bandeau de vos écrans, aux fiches et aux leçons de vos tutoriels.").'</p>';
						}
					$h .= '</div>';
					$h .= '<div class="util-deck-colors">';
						$h .= '<span class="util-deck-color" style="background-color: '.$color.'"></span>';
						$h .= '<span class="util-deck-color" style="background-color: '.encode($settings['background']).'"></span>';
					$h .= '</div>';
					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
				$h .= '</div>';
			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La page où la carte ouvre la barre : la fiche `$eCourse` quand le réseau en désigne une
	 * (`CommunityLib::getCustomizeCourse()`), son accueil sinon.
	 */
	public static function urlCard(\structure\Community $eCommunity, Course $eCourse = new Course()): string {

		if($eCourse->notEmpty()) {
			return CourseUi::url($eCourse, ['customize' => 1]);
		}

		return \structure\CommunityUi::url($eCommunity).'?customize=1';

	}

	/**
	 * Sous 768px, les deux sélecteurs passent derrière un bouton qui rappelle les couleurs en cours,
	 * et le CSS du socle décide lequel des deux affichages s'applique. Sans couleur choisie, la
	 * pastille lit celle du bandeau du groupe (`--community`, par `data-customize-default`). Le fond
	 * se prend dans la palette du socle ; sans fond choisi, la pastille montre celui du gabarit et
	 * aucune teinte n'est sélectionnée.
	 */
	protected function getColors(array $settings): string {

		$background = $settings['background'];

		$h = '<div class="customize-colors">';

			$h .= '<a class="customize-colors-toggle" onclick="Customize.toggleColors(this)">';
				$h .= '<span class="customize-colors-preview">';
					if($settings['color'] === NULL) {
						$h .= '<span class="learning-customize-swatch" data-customize-default="--community"></span>';
					} else {
						$h .= '<span class="learning-customize-swatch" style="--learning-swatch: '.encode($settings['color']).'"></span>';
					}
					$h .= '<span class="learning-customize-swatch" style="--learning-swatch: '.encode($background).'"></span>';
				$h .= '</span>';
				$h .= s("Couleurs");
				$h .= \Asset::icon('chevron-up');
			$h .= '</a>';

			$h .= '<div class="customize-popover">';

				// Le changement part au relâchement et non à chaque mouvement : la page est rendue
				// par le serveur, un rechargement par pixel parcouru serait intenable.
				$h .= '<div class="customize-group" data-wrapper="color">';
					$h .= '<span class="customize-label">'.s("Couleur").'</span>';
					$h .= '<div class="customize-choices">';
						$h .= '<label class="customize-color">';
							$h .= '<input type="color" value="'.encode($settings['color'] ?? '#000000').'" '.($settings['color'] === NULL ? 'data-customize-default="--community"' : '').' onchange="LearningCustomize.set(\'color\', this.value)"/>';
						$h .= '</label>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= '<div class="customize-group">';
					$h .= '<span class="customize-label">'.s("Arrière-plan").'</span>';
					$h .= '<div class="customize-choices">';
						$h .= $this->getBackgrounds($settings);
						if($settings['customBackground'] !== NULL) {
							$h .= '<a class="customize-reset" onclick="LearningCustomize.set(\'customBackground\', \'\')" title="'.s("Revenir à l'arrière-plan par défaut").'">'.\Asset::icon('arrow-counterclockwise').'</a>';
						}
					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien des polices choisies et les jetons du thème, posés sur la page.
	 *
	 * Les polices passent par `--customFont` et `--customTitleFont` sur `:root`, que
	 * `Customize.setFont()` réécrit pour montrer une police sans recharger, et que `learning.css` lit
	 * avec repli sur les polices du produit. Une police que le réseau n'a pas choisie est celle du
	 * gabarit, posée et chargée de la même façon ; sans l'une ni l'autre, elle n'est pas posée.
	 *
	 * La couleur se pose sur la portée seulement (`SCOPES`) : le conteneur de la page du réseau, de ses
	 * étapes et de ses leçons, dont la navigation et le pied gardent celle du produit ; le conteneur de
	 * la fiche, menu compris, dont la barre de personnalisation reste au catalogue. Les aplats prennent
	 * la couleur pure ; le texte prend `textInk`, la couleur foncée jusqu'à se lire sur le fond et les
	 * cartes, par `--primary-on-light` et `--secondary-on-light` que les feuilles du socle lisent avec
	 * repli sur la couleur.
	 *
	 * Les badges ne lisent pas ces encres-là : leur pastille se mêle toujours au blanc, quel que soit
	 * le fond de la portée, et une encre jugée sur le fond de la portée peut ne pas tenir sur le
	 * blanc. Ils lisent `whiteInk`,
	 * la couleur jugée contre le seul blanc, par `--primary-on-white` et `--secondary-on-white`.
	 *
	 * Le fond passe par `--learning-background`, que `learning.css` prolonge de la portée jusqu'au
	 * bord de l'écran. Avec lui, `--muted` prend `mutedInk`, pour que le gris des annotations tienne
	 * 4,5:1 sur la teinte.
	 *
	 * Un gabarit lit le fond, le gris et les trois encres sans repli : dès qu'il est choisi, les cinq
	 * sont posés, calculés pour la portée sur les couleurs que fixe sa feuille, et sur la couleur
	 * du produit quand le réseau n'en a pas, sans que la page la change. Sa feuille pose son propre
	 * fond par-dessus, sous un sélecteur plus fort que celui-ci.
	 *
	 * Statique : la fiche et les leçons n'ont pas de barre, et l'instance chargerait ses scripts.
	 */
	public static function getStyles(array $settings, string $scope): string {

		$font = ($settings['fonts']['text'] === NULL) ? NULL : self::getFont($settings['fonts']['text']);
		$titleFont = ($settings['fonts']['title'] === NULL) ? NULL : self::getTitleFont($settings['fonts']['title']);

		$families = [];

		if($font !== NULL) {
			$families[] = 'family='.($font['link'] ?? $font['label']);
		}

		if($titleFont !== NULL) {
			$families[] = 'family='.($titleFont['link'] ?? $titleFont['label']);
		}

		$h = '';

		if($families) {
			$h .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
			$h .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
			$h .= '<link href="https://fonts.googleapis.com/css2?'.implode('&', $families).'&display=swap" rel="stylesheet">';
		}

		$h .= '<style>';

			$h .= ':root {';
				if($font !== NULL) {
					$h .= '--customFont: '.$font['value'].';';
				}
				$h .= '--customFontAdjust: '.($font['adjust'] ?? 'none').';';
				if($titleFont !== NULL) {
					$h .= '--customTitleFont: '.$titleFont['value'].';';
				}
				$h .= '--customTitleFontAdjust: '.($titleFont['adjust'] ?? 'none').';';
			$h .= '}';

			$selector = self::SCOPES[$scope];

			if($settings['customDesign'] !== NULL) {

				\Asset::css('learning', 'design-'.$settings['customDesign'].'.css');

				$inks = $settings['inks'][$scope];

				$h .= $selector.' {';
					// Une feuille qui mêle le fond choisi à autre chose (blanc, `--primary`) lit ce second nom : écrite sur
					// `--learning-background` elle-même, la valeur se lirait elle-même, ce cycle la
					// rendrait invalide et la portée resterait sans fond. Les deux portent le fond
					// choisi tel qu'il se pose sur la page, adouci, celui sur lequel les encres sont
					// calculées.
					$h .= '--learning-background: '.encode($settings['backgroundSoftened']).';';
					$h .= '--learning-background-chosen: '.encode($settings['backgroundSoftened']).';';
					$h .= '--muted: '.$inks['mutedInk'].';';
					if($settings['color'] !== NULL) {
						$h .= self::getColorTokens($settings['color']);
						$h .= self::getWhiteTokens($settings['whiteInk']);
					}
					$h .= '--primary-ink: '.encode($inks['ink']).';';
					$h .= '--primary-on-light: '.encode($inks['textInk']).';';
					$h .= '--secondary-on-light: '.encode($inks['textInk']).';';
					$h .= '--learning-tint: '.encode($settings['tint']['background']).';';
					$h .= '--learning-tint-muted: '.encode($settings['tint']['muted']).';';
				$h .= '}';

			} else {

				if($settings['customBackground'] !== NULL) {
					$h .= $selector.' { --learning-background: '.encode($settings['backgroundSoftened']).'; --muted: '.$settings['mutedInk'].'; }';
				}

				if($settings['color'] !== NULL) {
					$h .= $selector.' {';
						$h .= self::getColorTokens($settings['color']);
						$h .= '--primary-ink: '.encode($settings['ink']).';';
						$h .= '--primary-on-light: '.encode($settings['textInk']).';';
						$h .= '--secondary-on-light: '.encode($settings['textInk']).';';
						$h .= self::getWhiteTokens($settings['whiteInk']);
					$h .= '}';
				}

			}

		$h .= '</style>';

		return $h;

	}

	/**
	 * La requête d'une page en mode personnalisation : `customize` et tous les réglages, vides pour
	 * ceux que le réseau n'a pas, une valeur absente de l'adresse se lisant comme celle qui est
	 * enregistrée. Les liens et les redirections la passent par `http_build_query()` : une couleur
	 * porte `#`, une police des guillemets et une virgule.
	 */
	public static function getQuery(array $values): array {
		return ['customize' => 1] + array_map(fn(?string $value) => $value ?? '', $values);
	}

	private static function getColorTokens(string $color): string {
		return '--primary: '.encode($color).';--secondary: '.encode($color).';--color-brand: '.encode($color).';';
	}

	private static function getWhiteTokens(string $whiteInk): string {
		return '--primary-on-white: '.encode($whiteInk).';--secondary-on-white: '.encode($whiteInk).';';
	}

}
?>
