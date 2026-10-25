<?php
namespace main;

/**
 * Les trois morceaux communs à tous les documents PDF : l'en-tête, le bloc de la
 * structure, le pied de page.
 *
 * Ils vivaient jusqu'ici dans chaque package, redessinés à chaque document : la
 * comptabilité avait le sien (`financialYear\PdfUi::getHeader()`), les relevés de prix un
 * autre, les documents de vente un troisième. Trois en-têtes veut dire trois
 * façons de nommer une ferme et trois emplacements pour la pagination.
 *
 * Ce qui distingue les documents entre eux n'est pas leur habillage mais une
 * seule question : appartiennent-ils à une structure ou non ? Les relevés de prix
 * n'appartient à personne et se passe donc du bloc central comme de l'identité
 * légale au pied ; un bilan appartient à une ferme et les porte tous les deux.
 */
class PdfUi {

	/**
	 * Le format et les marges de la page, en A4. Seules une page nommée (`@page plan-print`)
	 * et une page hors A4 (le ticket de caisse) s'écrivent à la main.
	 *
	 * `$margin` est une valeur CSS que le document choisit, jamais une saisie : elle
	 * se pose telle quelle dans la règle.
	 */
	public static function getPage(string $margin = '1cm', bool $landscape = FALSE): string {
		return '<style>@page { size: A4'.($landscape ? ' landscape' : '').'; margin: '.$margin.'; }</style>';
	}

	/**
	 * La marque, en haut à gauche, identique sur tous les documents.
	 *
	 * Le SVG plutôt que le PNG historique : il suit `currentColor`, donc la même
	 * marque sert en couleur sur un document ordinaire et en réserve sur un aplat.
	 */
	public static function getHeader(): string {

		$h = '<div class="pdf-brand">';
			$h .= \Asset::svg('main', 'svg/logo-otf.svg');
			$h .= '<span>'.s("Ouvretaferme").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bloc central : à qui appartient ce document, et lequel est-ce.
	 *
	 * ⚠️ `pdf-structure-heading` et non `-title` : `.pdf-structure-title` est déjà
	 * pris par `accounting/financialYear/static/css/pdf.css`, chargé après celui-ci dans
	 * les documents comptables. Reprendre le nom aurait donné une charte écrasée
	 * dans la moitié des documents, et seulement dans ceux-là.
	 *
	 * Le titre vit ici et non au-dessus : sur un document qui circule, le nom de la
	 * ferme et le nom du document se lisent d'un seul regard, et les séparer oblige
	 * à choisir lequel des deux domine.
	 *
	 * `$meta` porte ce qui date le document : un exercice, une période, un SIRET.
	 * Rien n'est déduit ici, une UI ne calcule pas, elle reçoit.
	 *
	 * ⚠️ Contrat d'échappement, le même dans tout ce fichier : **le titre est du
	 * texte brut** et se fait encoder ici, **la méta est du HTML** et se fait
	 * assembler par l'appelant (`financialYear\PdfUi::getPeriod()` y met un séparateur et
	 * encode lui-même le SIRET). Passer un titre déjà encodé le fait sortir avec ses
	 * entités en toutes lettres.
	 */
	public static function getStructure(\structure\Structure $eStructure, string $title, ?string $meta = NULL): string {

		$h = '<div class="pdf-structure">';

			$h .= self::getLogo($eStructure);

			$h .= '<div class="pdf-structure-name">'.encode($eStructure['legalName'] ?? $eStructure['name'] ?? '').'</div>';
			$h .= '<div class="pdf-structure-heading">'.encode($title).'</div>';

			if($meta !== NULL) {
				$h .= '<div class="pdf-structure-meta">'.$meta.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre d'un document qui n'appartient à aucune structure.
	 *
	 * Il reprend la place laissée libre par le bloc central, à gauche et en grand :
	 * sans ferme à nommer, c'est le document lui-même qui s'annonce.
	 *
	 * Il titre aussi chaque feuille d'un document qui en enchaîne plusieurs, le bloc
	 * de la structure n'étant posé qu'une fois, sur la première.
	 *
	 * Même contrat que `getStructure()` : titre en texte brut, `$lede` en HTML.
	 */
	public static function getTitle(string $title, ?string $lede = NULL): string {

		$h = '<div class="pdf-title-block">';

			$h .= '<div class="pdf-title">'.encode($title).'</div>';

			if($lede !== NULL) {
				$h .= '<p class="pdf-lede">'.$lede.'</p>';
			}

		$h .= '</div>';

		return $h;

	}

	public static function getPagingFooter(): string {

		$h = '<div class="footer-container" style="text-align: center; font-size: 0.25cm; width: 100%;">';
			$h .= '<span class="pageNumber"></span> / <span class="totalPages"></span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le pied : qui édite, de qui c'est le document, où l'on en est.
	 *
	 * La colonne centrale disparaît quand il n'y a pas de structure — pas d'espace
	 * réservé pour rien, la grille se referme d'elle-même.
	 *
	 * ⚠️ La pagination ne se passe **pas** ici pour les documents comptables : ils
	 * la posent déjà sur chaque page par le `footerTemplate` de Puppeteer
	 * (`financialYear\PdfUi::getFooter()`), et l'écrire une seconde fois dans le flux
	 * donnerait deux numéros de page sur la dernière. Le paramètre ne sert qu'aux
	 * documents qui n'ont pas ce mécanisme. Ne l'utiliser que pour un document dont
	 * on sait qu'il tient sur une page : un numéro écrit dans le flux se répète à
	 * l'identique sur toutes les autres. La pagination juste passe par le gabarit de
	 * pied de Puppeteer, que `\main\PdfLib::build()` accepte depuis son paramètre
	 * `$footer`.
	 *
	 * La grille compte ses colonnes : une colonne vide laisserait un tiers de la
	 * largeur en attente de rien.
	 */
	public static function getFooter(?\structure\Structure $eStructure = NULL, ?string $note = NULL, ?string $page = NULL): string {

		$columns = 1 + ($eStructure === NULL ? 0 : 1) + ($page === NULL ? 0 : 1);

		$h = '<div class="pdf-footer pdf-footer-'.$columns.'">';

			$h .= '<div>';
				$h .= '<div class="pdf-footer-brand">';
					$h .= \Asset::svg('main', 'svg/logo-otf.svg');
					$h .= '<span>'.s("Ouvretaferme").'</span>';
				$h .= '</div>';
				$h .= $note ?? '';
			$h .= '</div>';

			if($eStructure !== NULL) {

				// Le nom seul : ni adresse ni SIRET. Ils figurent déjà dans le bloc en
				// tête des documents qui doivent les porter, et les répéter en pied
				// obligeait à les sélectionner partout — d'où un « Undefined array key
				// "legalStreet" » sur les pages qui chargent la structure sans son
				// adresse, comme le plan d'assolement.
				$h .= '<div>'.encode($eStructure['legalName'] ?? $eStructure['name'] ?? '').'</div>';

			}

			if($page !== NULL) {
				$h .= '<div class="pdf-footer-page">'.encode($page).'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La bande de chiffres qui ouvre un document : ce qu'on retient sans lire.
	 *
	 * `$figures` est une liste de paires [valeur, libellé]. Trois entrées est le
	 * bon compte : deux ne font pas une bande, quatre ne se retiennent plus.
	 *
	 * La valeur est du HTML (elle sort d'un formateur, `TextUi::money()` ou
	 * `DateUi::numeric()`), le libellé du texte brut.
	 */
	public static function getFigures(array $figures): string {

		$h = '<div class="pdf-figures">';

			foreach($figures as [$value, $label]) {
				$h .= '<div>';
					$h .= '<div class="pdf-figure-value">'.$value.'</div>';
					$h .= '<div class="pdf-figure-label">'.encode($label).'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le sommaire d'un document qui enchaîne plusieurs feuilles.
	 *
	 * `$sections` est une liste de paires [titre, libellés], chaque section numérotant
	 * ses libellés. Sans numéro de page : la pagination est posée par le gabarit de
	 * pied de Puppeteer, que le flux du document ne connaît pas.
	 *
	 * Le titre et les libellés sont du texte brut, encodé ici.
	 */
	public static function getToc(array $sections): string {

		$h = '<div class="pdf-toc">';

			foreach($sections as [$title, $labels]) {

				$h .= '<div class="pdf-toc-title">'.encode($title).'</div>';
				$h .= '<ol>';
					foreach($labels as $label) {
						$h .= '<li>'.encode($label).'</li>';
					}
				$h .= '</ol>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le logo de la structure, ou rien.
	 *
	 * ⚠️ L'URL du média est absolue en développement : le navigateur sans tête
	 * charge la page depuis le serveur local, où les logos ne sont pas déposés.
	 * Repris de `financialYear\PdfUi::getHeader()`, qui portait déjà ce contournement.
	 */
	private static function getLogo(\structure\Structure $eStructure): string {

		// `??` et non un accès direct : toutes les pages ne chargent pas la structure
		// avec son logo, et une clé absente lèverait une notice qui, en PDF, s'imprime
		// en toutes lettres au milieu du document.
		if(($eStructure['logo'] ?? NULL) === NULL) {
			return '';
		}

		if(LIME_ENV === 'dev') {
			return '<img class="pdf-structure-logo" src="https://media.ouvretaferme.org/'.new \media\StructureLogoUi()->getBasenameByHash($eStructure['logo'], 's').'"/>';
		}

		// `1.7cm` et non `17mm` : `MediaUi::convertToFormat()` connaît px, rem, cm et
		// %, mais pas mm — un `17mm` y lève « Invalid size format », et seulement en
		// production, la branche de développement passant par la balise `img`.
		return '<div class="pdf-structure-logo">'.\structure\StructureUi::getLogo($eStructure, '1.7cm').'</div>';

	}

}
?>
