<?php
namespace product;

/**
 * Écrans du lot L1 du chantier « variantes de produits » : la grille des
 * variantes sur la fiche d'un principal, le tableau des variantes en fin du
 * formulaire du principal (B1) et sa déclinaison au choix des variantes d'une
 * duplication.
 *
 * Depuis la dépose des verrous (2026-09-01) : une variante naît copie
 * de son principal et vit sa vie, seuls les champs inféodés
 * (Product::VARIANT_PROPAGATED) continuent de suivre. Aucun état « suit /
 * propre » à représenter : les formulaires d'une variante sont des formulaires
 * produit ordinaires, au vocabulaire près.
 */
class VariantAiUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('product', 'product.css');
		// Déclarée écran par écran, comme \website\ManageUi le fait : le cadriciel la porte
		// (framework/util/static/lib/), aucune page ne la charge globalement
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::js('product', 'variantAi.js');

	}

	public static function urlCreate(Product $eProductPrincipal): string {
		return '/product/product:create?structure='.$eProductPrincipal['structure']['id'].'&variantOf='.$eProductPrincipal['id'];
	}

	/**
	 * Sur une famille : le produit choisi la rejoint (doUpdateVariantOf). Un produit sans variantes
	 * rattache sa première depuis l'onglet « Produit existant » du formulaire d'ajout.
	 */
	public function attach(Product $eProductPrincipal): \Panel {

		$form = new \util\FormUi();

		$h = self::getAttachField($form, $eProductPrincipal, 'id', s("Produit à rattacher"));

		$h .= $form->group(
			content: $form->submit(s("Rattacher ce produit"))
		);

		return new \Panel(
			id: 'panel-product-attach-variant',
			title: s("Rattacher un produit existant"),
			subTitle: '<div class="panel-header-subtitle">'.encode($eProductPrincipal['name']).'</div>',
			dialogOpen: $form->openAjax('/product/product:doUpdateVariantOf', ['id' => 'product-attach-variant', 'class' => 'panel-dialog']).$form->hidden('variantOf', $eProductPrincipal),
			dialogClose: $form->close(),
			body: $h,
		);

	}

	/**
	 * Les résultats de l'autocomplétion du rattachement. Un produit qui porte déjà ses propres
	 * variantes y figure sans être sélectionnable : la profondeur du rattachement est de un
	 * (`variantOf.check`), et le montrer grisé vaut mieux que de le faire disparaître sans
	 * dire pourquoi. `type: 'title'` est ce que le cadriciel rend en
	 * `autocomplete-not-selectable` (framework/util/static/js/form.js).
	 */
	public static function getAttachAutocompleteResults(\Collection $cProduct): array {

		$results = [];

		foreach($cProduct as $eProduct) {

			if($eProduct['variants'] > 0) {

				$results[] = [
					'type' => 'title',
					'itemHtml' => '<div>'.$eProduct->getName('html').' <span class="util-annotation">'.s("a déjà ses variantes").'</span></div>',
					'itemText' => $eProduct->getName()
				];

			} else {
				$results[] = ProductUi::getAutocomplete($eProduct);
			}

		}

		return $results;

	}

	/**
	 * Le bandeau montré sur la fiche de la famille quand on y atterrit après avoir demandé la
	 * fiche d'une variante (product.p.php, redirection du 2026-09-04) : la fiche d'une
	 * variante n'existe plus pour elle-même, ce bandeau nomme ce qui a changé.
	 *
	 * Nom nu de la variante (getName(), sans withPrincipal) : même convention que la
	 * sous-ligne de la liste des produits (getListVariantName()) — la famille est déjà
	 * nommée par le titre de la page, la répéter (« Courge Butternut - Petite ») serait
	 * une redite.
	 */
	public function getRedirectedBanner(Product $eProductPrincipal, Product $eProductVariant): string {

		$h = '<div class="util-info">';
			$h .= s("Le produit {variant} est une variante de {principal}.", [
				'variant' => '<b>'.$eProductVariant->getName('html').'</b>',
				'principal' => '<b>'.$eProductPrincipal->getName('html').'</b>'
			]);
		$h .= '</div>';

		return $h;

	}

	/**
	 * La section Variantes de la fiche d'un principal, dans son onglet : grille
	 * sur grand écran, cartes sous 767 px, lien d'ajout en pied. Le principal
	 * n'y figure pas : il ne se vend plus, la grille ne porte que les lignes
	 * vendables (décision du 01/09).
	 */
	public function getSection(Product $eProduct, \Collection $cProductVariant): string {

		// En prix commun, le prix vit sur le principal et se lit dans l'onglet Prix : la
		// grille de la fiche perd ses colonnes de prix exactement comme le tableau du
		// formulaire les a déjà perdues, les deux écrans disent la même chose du même état
		// (maquette du 2026-09-02, écran 5)
		// Deux réponses et non une : une famille peut ne vendre qu'à un seul canal, et la colonne
		// de l'autre n'y montrait qu'une file de croix — 183 px pour deux « × » sur /produit/108942,
		// mesuré le 2026-09-02. Le régime de prix commande d'abord (en prix de famille, aucune
		// variante ne porte le sien), puis chaque canal se demande s'il est vendu quelque part.
		$withPrices = ($eProduct['priceMode'] !== Product::SHARED);
		$withPrivate = ($withPrices and $cProductVariant->match(fn($eProductVariant) => $eProductVariant['private']));
		$withPro = ($withPrices and $cProductVariant->match(fn($eProductVariant) => $eProductVariant['pro']));

		// La poignée ne s'arme que là où l'ordre peut s'écrire : une famille d'une seule ligne
		// n'a pas d'ordre, et une poignée qui ne mène à rien est une promesse en l'air
		$sortable = ($eProduct->canWrite() and $eProduct->acceptUpdatePositions());

		$addActions = ($eProduct->canWrite() and $eProduct['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS));

		$h = '<div id="product-variants" data-product="'.$eProduct['id'].'"'.($sortable ? ' onrender="ProductVariant.initSort(this)"' : '').'>';

			$h .= '<div class="hide-sm-down">';
				$h .= $this->getTable($eProduct, $cProductVariant, $withPrivate, $withPro, $sortable);
				if($addActions) {
					$h .= '<div class="mt-1 product-variant-add-actions">';
						$h .= self::getAddActions($eProduct);
					$h .= '</div>';
				}
			$h .= '</div>';

			$h .= $this->getCards($eProduct, $cProductVariant, $withPrivate, $withPro, $sortable);

			if($addActions) {
				$h .= '<div class="hide-md-up mt-1 product-variant-add-actions">';
					$h .= self::getAddActions($eProduct);
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les deux gestes qui agrandissent une famille, côte à côte (retour du 2026-09-03) : ils ne
	 * vivent plus au-dessus du tableau, en double du titre de l'onglet (retour 6), mais en pied,
	 * là où « + Ajouter une variante » vivait déjà.
	 */
	private static function getAddActions(Product $eProduct): string {

		$h = '<a href="'.self::urlCreate($eProduct).'" class="btn btn-outline-primary">'.\Asset::icon('plus').' '.s("Ajouter une variante").'</a>';

		if($eProduct->acceptAttachVariant()) {
			$h .= ' <a href="/product/product:attachVariant?id='.$eProduct['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus').' '.s("Rattacher un produit existant comme variante").'</a>';
		}

		return $h;

	}

	private function getTable(Product $eProduct, \Collection $cProductVariant, bool $withPrivate, bool $withPro, bool $sortable = FALSE): string {

		$h = '<div class="util-overflow-md">';
			// Le conteneur triable est la table elle-même : ses éléments sont les <tbody> des
			// variantes, un par ligne, et le <thead> en est exclu par le sélecteur draggable
			$h .= '<table class="tbody-even product-variant-grid"'.($sortable ? ' data-variant-sort' : '').'>';

				$h .= '<thead>';
					 $h .= '<tr>';
						$h .= '<th class="td-min-content"></th>';
						$h .= '<th class="td-vignette"></th>';
						$h .= '<th>'.s("Nom de la variante").'</th>';
						$h .= '<th>'.s("Référence").'</th>';
						$h .= '<th>'.s("Unité").'</th>';
						$h .= '<th>'.s("Stock").'</th>';
						if($withPrivate) {
							$h .= '<th class="text-end">'.s("Particulier").'</th>';
						}
						if($withPro) {
							$h .= '<th class="text-end">'.s("Pro").'</th>';
						}
						$h .= '<th>'.s("Caractéristiques").'</th>';
						$h .= '<th class="text-center">'.s("Activé").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				foreach($cProductVariant as $eProductVariant) {
					$h .= $this->getVariantRow($eProduct, $eProductVariant, $withPrivate, $withPro, $sortable);
				}

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	private function getVariantRow(Product $eProduct, Product $eProductVariant, bool $withPrivate, bool $withPro, bool $sortable = FALSE): string {

		$h = '<tbody data-variant-id="'.$eProductVariant['id'].'"><tr class="'.($eProductVariant['status'] === Product::INACTIVE ? 'tr-disabled' : '').'">';

			$h .= '<td class="td-min-content">'.($sortable ? \main\DesignUi::getDragHandle() : '').'</td>';
			$h .= '<td class="td-vignette">'.new \media\ProductVignetteUi()->getCamera($eProductVariant, size: '3.5rem').'</td>';
			$h .= '<td class="product-item-name">'.$eProductVariant->quick('name', $eProductVariant->getName('html')).'</td>';
			$h .= '<td class="product-variant-quick">'.$eProductVariant->quick('reference', self::getReference($eProductVariant)).'</td>';
			$h .= '<td class="product-item-unit">'.$eProductVariant->quick('unit', UnitUi::getSingular($eProductVariant['unit'])).'</td>';
			// Chaque variante tient son propre stock : celle qui n'en tient aucun le dit par la
			// croix des prix plutôt que par un vide.
			$h .= '<td class="product-item-stock">'.($eProductVariant->acceptStock() ? StockUi::link($eProductVariant) : \Asset::icon('x')).'</td>';
			if($withPrivate) {
				$h .= '<td class="text-end t-highlight product-variant-quick">'.$eProductVariant->quick('privatePrice', self::getPrice($eProductVariant, 'private')).'</td>';
			}
			if($withPro) {
				$h .= '<td class="text-end t-highlight product-variant-quick">'.$eProductVariant->quick('proPrice', self::getPrice($eProductVariant, 'pro')).'</td>';
			}
			$h .= '<td class="font-sm color-muted">'.self::getInfosSummary($eProductVariant).'</td>';
			$h .= '<td class="text-center">'.new ProductUi()->toggle($eProductVariant).'</td>';
			$h .= '<td class="text-end">'.$this->getVariantMenu($eProduct, $eProductVariant).'</td>';

		$h .= '</tr></tbody>';

		return $h;

	}

	private function getCards(Product $eProduct, \Collection $cProductVariant, bool $withPrivate, bool $withPro, bool $sortable = FALSE): string {

		$h = '<div class="util-cards util-cards-1 hide-md-up"'.($sortable ? ' data-variant-sort' : '').'>';

			foreach($cProductVariant as $eProductVariant) {

				$h .= '<div class="util-deck'.($eProductVariant['status'] === Product::INACTIVE ? ' product-variant-card-disabled' : '').'" data-variant-id="'.$eProductVariant['id'].'">';
					$h .= '<div class="util-deck-head">';
						if($sortable) {
							$h .= \main\DesignUi::getDragHandle();
						}
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$eProductVariant->quick('name', $eProductVariant->getName('html')).'</div>';
						$h .= '</div>';
						$h .= '<div class="util-deck-aside">'.$this->getVariantMenu($eProduct, $eProductVariant).'</div>';
					$h .= '</div>';
					$h .= '<div class="util-deck-content">';
						$h .= '<dl class="util-presentation">';
							$h .= '<dt>'.s("Unité").'</dt><dd>'.$eProductVariant->quick('unit', UnitUi::getSingular($eProductVariant['unit'])).'</dd>';
							$h .= '<dt>'.s("Stock").'</dt><dd>'.($eProductVariant->acceptStock() ? StockUi::link($eProductVariant) : \Asset::icon('x')).'</dd>';
							if($withPrivate or $withPro) {

								$prices = [];

								if($withPrivate) {
									$prices[] = $eProductVariant->quick('privatePrice', self::getPrice($eProductVariant, 'private'));
								}

								if($withPro) {
									$prices[] = $eProductVariant->quick('proPrice', self::getPrice($eProductVariant, 'pro'));
								}

								$h .= '<dt>'.s("Prix").'</dt><dd>'.implode(' · ', $prices).'</dd>';

							}
							// La carte porte les mêmes lignes que la grille en toutes circonstances :
							// le tiret cadratin remplit un dd qui serait resté vide, ce qui écarte du
							// même geste le « / » que component.css:2625 y substituerait.
							$h .= '<dt>'.s("Infos").'</dt><dd class="font-sm">'.self::getInfosSummary($eProductVariant).'</dd>';
							$h .= '<dt>'.s("Référence").'</dt><dd class="product-variant-reference">'.$eProductVariant->quick('reference', self::getReference($eProductVariant)).'</dd>';
							$h .= '<dt>'.s("Activé").'</dt><dd>'.new ProductUi()->toggle($eProductVariant, 'card').'</dd>';
						$h .= '</dl>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le menu d'engrenage d'une ligne de variante. `$eProduct` est le principal de la
	 * famille : c'est lui qui porte `priceMode`, jamais la variante (retour du 2026-09-04).
	 */
	private function getVariantMenu(Product $eProduct, Product $eProductVariant): string {

		if($eProductVariant->canWrite() === FALSE) {
			return '';
		}

		$h = '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';

			$h .= '<div class="dropdown-title">'.encode($eProductVariant->getName()).'</div>';

			$h .= '<a href="/product/product:update?id='.$eProductVariant['id'].'" class="dropdown-item">'.s("Modifier la variante").'</a>';

			if(\structure\StructureSetting::hasCommercialisationApp()) {
				$h .= implode('', self::notify('getVariantEntries', $eProduct, $eProductVariant));
			}

			if(
				$eProductVariant->acceptDuplicate() and
				$eProductVariant['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS)
			) {
				$h .= '<a href="/product/product:create?structure='.$eProductVariant['structure']['id'].'&from='.$eProductVariant['id'].'" class="dropdown-item">'.s("Dupliquer la variante").'</a>';
			}

			// L'inverse exact de « Rattacher un produit existant » (attach()) : même route
			// (doUpdateVariantOf), `variantOf` posté vide plutôt qu'absent — un champ non posté
			// ne s'écrit pas (CLAUDE.md), c'est la valeur vide et non son omission qui fait la
			// différence entre « inchangé » et « détaché ».
			//
			// Le nom de l'attribut s'écrit en tirets et jamais en casse mêlée : l'analyseur HTML
			// abaisse les noms d'attributs, et `Element.prototype.post()` (lime.js:591) ne
			// remonte en camel que ce qui suit un tiret. Un `post-variantOf` arrivait donc en
			// `variantof`, le geste postait sans jamais poster `variantOf`, et la route écrivait
			// la valeur inchangée sans lever la moindre erreur (2026-09-06).
			if($eProductVariant->acceptDetachVariant()) {
				$h .= '<a data-ajax="/product/product:doUpdateVariantOf" post-id="'.$eProductVariant['id'].'" post-variant-of="" class="dropdown-item" data-confirm="'.s("Cette variante quittera la famille et redeviendra un produit autonome. Continuer ?").'">'.s("Transformer en produit").'</a>';
			}

			// post-origin : la route rend la main sur cette grille plutôt que sur la liste des
			// produits, la famille survivant à la suppression d'une de ses lignes.
			if($eProductVariant->assertDelete()) {
				$h .= '<a data-ajax="/product/product:doDelete" post-id="'.$eProductVariant['id'].'" post-origin="variants" class="dropdown-item" data-confirm="'.s("Confirmer la suppression de la variante ?").'">'.s("Supprimer la variante").'</a>';
			}

			// Le stock en bas, derrière un séparateur, comme dans le menu d'un produit : ce sont
			// des gestes sur ce que la variante suit, pas sur ce qu'elle est. Le suivi ne
			// s'atteignait plus nulle part depuis que la fiche d'une variante redirige vers celle
			// de sa famille (2026-09-06).
			$stock = '';

			if($eProductVariant->acceptEnableStock()) {
				$stock .= '<a data-ajax="/product/stock:doEnableStock" post-id="'.$eProductVariant['id'].'" class="dropdown-item">'.\Asset::icon('box').'  '.s("Activer le suivi du stock").'</a>';
			}

			if($eProductVariant->acceptDisableStock()) {
				$stock .= '<a data-ajax="/product/stock:doDisableStock" post-id="'.$eProductVariant['id'].'" class="dropdown-item" data-confirm="'.s("Confirmer la désactivation du suivi de stock pour cette variante ?").'">'.\Asset::icon('box').'  '.s("Désactiver le suivi du stock").'</a>';
			}

			if($stock !== '') {
				$h .= '<div class="dropdown-divider"></div>'.$stock;
			}

		$h .= '</div>';

		return $h;

	}

	private static function getPrice(Product $eProduct, string $type): string {

		$sold = match($type) {
			'private' => $eProduct['private'],
			'pro' => $eProduct['pro']
		};

		if($sold === FALSE) {
			return '<span class="color-muted">'.\Asset::icon('x').'</span>';
		}

		$price = match($type) {
			'private' => $eProduct['privatePrice'],
			'pro' => $eProduct['proPrice']
		};

		if($price === NULL) {
			return '';
		}

		$h = \util\TextUi::money($price);

		if($eProduct['structure']->conf()['hasVat']) {
			$h .= ' <span class="util-annotation">'.ProductUi::getTaxes($type).'</span>';
		}

		return $h;

	}

	/**
	 * La variété n'est plus ici : elle est accolée au nom de la variante par getName('html'),
	 * comme sur la page des produits (2026-09-02). L'y laisser aussi la ferait lire
	 * deux fois sur la même ligne.
	 */
	private static function getInfosSummary(Product $eProduct): string {

		$infos = implode(' · ', array_filter([
			$eProduct['packaging'] !== NULL ? encode($eProduct['packaging']) : NULL,
			$eProduct['additional'] !== NULL ? encode($eProduct['additional']) : NULL
		]));

		return ($infos === '') ? self::getEmptyValue() : $infos;

	}

	private static function getReference(Product $eProduct): string {

		return ($eProduct['reference'] === NULL) ? self::getEmptyValue() : encode($eProduct['reference']);

	}

	/**
	 * Le glyphe d'une valeur absente dans une cellule de grille (2026-09-02).
	 * CLAUDE.md proscrit le tiret cadratin dans les textes rédigés : ce n'en est pas un,
	 * c'est la forme que le dépôt emploie déjà pour dire « rien ici » sans laisser un blanc
	 * qu'on lit comme un défaut d'affichage — 9 occurrences identiques dans
	 * quotation/ui/Table.u.php et financialYear/ui/FinancialYear.u.php.
	 * Il se pose dans la zone quick() sans risque : le champ d'édition est rendu par le
	 * serveur depuis l'élément (Data.php:1474 n'habille que l'affichage, FormUi::quick()
	 * appelle dynamicField()), jamais depuis le contenu affiché.
	 */
	public static function getEmptyValue(): string {
		return '<span class="color-muted">—</span>';
	}

	/**
	 * Le glyphe d'un prix hérité de la famille, sur une sous-ligne en prix commun (retour du
	 * 2026-09-03) : `=` dit « la même valeur que la mère », le tiret cadratin dirait « pas de
	 * valeur » — ce ne sont pas les mêmes faits, la ligne porte physiquement le prix (copié à
	 * chaque écriture du principal, `VariantLib::propagate()`), elle ne fait qu'y renoncer à
	 * l'éditer pour son compte.
	 */
	public static function getSameAsFamilyValue(): string {
		return '<span class="color-muted" title="'.s("Identique au prix de la famille").'">=</span>';
	}

	/**
	 * La cellule Nom d'une ligne de tête, sur la liste des produits.
	 *
	 * Le nom du contenant, son compteur de variantes, et rien de ses variantes : ni unité ni
	 * fourchette de prix (2026-09-03 — « la ligne de tête ne dit que ce qui vaut pour
	 * toute la famille », et l'unité est du ressort de la variante, sans condition). C'est ce
	 * qui dispense la requête de liste de tout agrégat. `additional` sort de `getDetails()`
	 * pour la même raison : copié à l'activation des variantes, c'est un champ de variante qui
	 * peut diverger d'une ligne à l'autre, pas une caractéristique de la famille (retour du
	 * 2026-09-03, capture « Ail frais » — « Autour de 170 g » descendu depuis une variante).
	 */
	public static function getListFamilyName(Product $eProduct, bool $displayStock, string $displayColumn): string {

		$h = '<div class="product-item-label">';

			$h .= '<div>';

				if($eProduct->canWrite()) {
					$h .= '<a href="'.ProductUi::url($eProduct).'" class="product-item-label-name">'.$eProduct->getName('html').'</a>';
				} else {
					$h .= '<span class="product-item-label-name">'.$eProduct->getName('html').'</span>';
				}

				$h .= ' '.self::getListVariantsToggle($eProduct, $displayStock, $displayColumn);

			$h .= '</div>';

			$h .= ProductUi::getMarksLogos($eProduct, '1.5rem');

		$h .= '</div>';

		$details = ProductUi::getDetails($eProduct, ['reference', 'origin']);

		if($details) {
			$h .= '<div class="product-item-infos">'.implode(' | ', $details).'</div>';
		}

		return $h;

	}

	/**
	 * Le déclencheur « X variantes », seule cible du dépliage (retour du 2026-09-03 : le
	 * chevron de début de ligne est retiré, celui qui reste vit ici, orienté vers le bas).
	 *
	 * Sa forme reprend le dropdown-toggle du gear-menu voisin de la même ligne
	 * (`ProductUi::getUpdate()`) — pas `data-dropdown`, qui armerait
	 * `Lime.Dropdown` : son délégué `stopImmediatePropagation()` sur `[data-dropdown]`
	 * (CLAUDE.md) avalerait le clic avant `data-ajax`, pour ouvrir un menu flottant qui
	 * n'existe pas ici. Le geste reste un dépliage de lignes, la chrome du bouton et son caret
	 * sont repris, pas le composant : le caret est redessiné en CSS sur
	 * `.product-family-toggle`, sans l'attribut qui l'arme.
	 *
	 * `btn-sm` et non `btn-xs` : mesuré le 2026-09-03, `btn-xs` rendait 19,4 px de haut à 360 px de large
	 * (`getBoundingClientRect()`, racine à 12px) — sous la cible tactile de 36 px du chantier,
	 * sur le seul chemin vers les variantes d'une famille depuis la liste. `btn-sm` rend
	 * 23,6 px : mieux, encore sous 36, mais c'est la taille voisine de la même famille que
	 * demande le retour, pas la plus petite — sans coût pour la ligne, dont la
	 * hauteur (61 px) reste celle de la vignette dans les deux tailles (arbitrage du chef de
	 * projet, 2026-09-03).
	 *
	 * Toujours deux liens rendus par le serveur : celui qui déplie porte la requête, celui qui
	 * replie n'en a pas besoin, et `.product-family-open` sur le `<tbody>` décide lequel se
	 * voit. Un seul lien obligerait la réponse ajax à réécrire son propre déclencheur, ou le
	 * repli à fabriquer du HTML en JS.
	 *
	 * `stock` et `column` voyagent dans la requête parce que le nombre de colonnes de la table
	 * est décidé sur toute la page (`ProductUi::getList()`) et qu'une sous-ligne qui n'en
	 * porterait pas autant décalerait la table. C'est le serveur qui a écrit ces valeurs dans
	 * le lien, comme il écrit `&origin=` ailleurs.
	 */
	private static function getListVariantsToggle(Product $eProduct, bool $displayStock, string $displayColumn): string {

		$url = '/product/variantAi:list?id='.$eProduct['id'].'&stock='.(int)$displayStock.'&column='.$displayColumn;
		$count = p("{value} variante", "{value} variantes", $eProduct['variants']);

		$h = '<a class="btn btn-outline-primary btn-sm product-family-toggle product-family-expand" href="'.\util\HttpUi::setArgument(LIME_REQUEST, 'variants', $eProduct['id'], FALSE).'" data-ajax="'.$url.'" data-ajax-method="get" title="'.s("Afficher les variantes").'">';
			$h .= $count.' '.\Asset::icon('chevron-down');
		$h .= '</a>';

		$h .= '<a class="btn btn-outline-primary btn-sm product-family-toggle product-family-collapse" onclick="ProductVariant.collapseFamily(this)" title="'.s("Masquer les variantes").'">';
			$h .= $count.' '.\Asset::icon('chevron-up');
		$h .= '</a>';

		return $h;

	}

	/**
	 * La cellule Nom d'une sous-ligne : le nom de la variante, sa variété accolée par
	 * getName('html'), puis ce qui la sépare de ses sœurs — sa référence et son complément.
	 * `additional` est justement le champ que getListFamilyName() refuse à la ligne de tête
	 * parce qu'il est propre à chaque variante : c'est ici qu'il distingue (D4, validé le 29/08).
	 *
	 * $includeUnit pour l'appelant qui n'a pas de colonne Unité : la liste des produits en a une
	 * (`td.product-item-unit`), la table des catalogues n'en a pas et y remplace le prix d'une
	 * sous-ligne rattachée par « = » — sans cela l'unité, seule différence entre deux variantes
	 * homonymes, n'y paraîtrait nulle part.
	 */
	public static function getListVariantName(Product $eProductVariant, bool $includeUnit = FALSE): string {

		$h = '<div class="product-item-label product-variant-name">';

			$h .= '<div>';

				if($eProductVariant->canWrite()) {
					$h .= '<a href="'.ProductUi::url($eProductVariant).'" class="product-item-label-name">'.$eProductVariant->getName('html').'</a>';
				} else {
					$h .= '<span class="product-item-label-name">'.$eProductVariant->getName('html').'</span>';
				}

				if($includeUnit and $eProductVariant['unit']->notEmpty()) {
					$h .= '<span class="product-item-label-unit">'.UnitUi::getSingular($eProductVariant['unit']).'</span>';
				}

			$h .= '</div>';

		$h .= '</div>';

		$details = ProductUi::getDetails($eProductVariant, ['reference', 'additional']);

		if($details) {
			$h .= '<div class="product-item-infos product-variant-name">'.implode(' | ', $details).'</div>';
		}

		return $h;

	}

	/**
	 * Ce qu'un écran de variante change dans un champ, hors libellé : les libellés de `name`
	 * et `reference` sont désormais des closures du describer (ProductUi::p()), pour que la
	 * zone d'édition en ligne les dise comme le formulaire — FormUi::quick() lit le describer
	 * du module et ne passe jamais par ici (relecteur du 2026-09-03).
	 */
	/**
	 * Le haut du formulaire d'une première variante, qui dessine la famille qu'il va créer :
	 * « La famille » et son nom, « Variante 1 » (le produit d'aujourd'hui, montré et non rempli),
	 * puis « Variante 2 », qui se crée ou se rattache par deux onglets. Planche « Nommer la famille
	 * à sa naissance », v4 validée le 2026-09-11.
	 *
	 * Ouvre l'onglet « Nouveau produit » et son fieldset : le reste du formulaire de création s'y
	 * écrit, et getFirstVariantClose() le referme. Un fieldset désactivé ne poste rien (FormData,
	 * et Element.prototype.form() pour les éditeurs) : l'onglet caché n'envoie donc pas ses champs,
	 * et `variantSource` dit au serveur lequel des deux gestes lire.
	 */
	public static function getFirstVariantOpen(\util\FormUi $form, Product $eProduct): string {

		$eProduct->expects(['name', 'vignette', 'unit', 'privatePrice', 'proPrice']);

		\Asset::js('product', 'variantAi.js');

		$price = $eProduct['privatePrice'] ?? $eProduct['proPrice'];

		$summary = array_filter([
			$eProduct['unit']->notEmpty() ? UnitUi::getSingular($eProduct['unit']) : NULL,
			($price !== NULL) ? \util\TextUi::money($price) : NULL
		]);

		$h = '<h3>'.s("La famille").'</h3>';

		$h .= $form->dynamicGroups($eProduct, ['name*'], [
			'name*' => self::renameField('family', s("Nom de la famille"))
		]);

		$h .= '<br/>';
		$h .= '<h3>'.s("Variante 1").'</h3>';
		$h .= '<div class="util-block">';
			$h .= '<div class="util-vignette">';
				$h .= ProductUi::getVignette($eProduct, '3rem');
				$h .= '<div>';
					$h .= '<b>'.$eProduct->getName('html').'</b>';
					if($summary !== []) {
						$h .= '<div class="util-annotation">'.implode(' · ', $summary).'</div>';
					}
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<br/>';
		$h .= '<h3>'.s("Variante 2").'</h3>';

		$h .= '<div class="tabs tabs-h tabs-exclusive">';
			$h .= '<div class="tabs-item tabs-form">';
				$h .= '<a class="tab-item selected" data-tab="create" onclick="ProductVariant.selectSource(this)">'.s("Nouveau produit").'</a>';
				$h .= '<a class="tab-item" data-tab="attach" onclick="ProductVariant.selectSource(this)">'.s("Produit existant").'</a>';
			$h .= '</div>';
			$h .= '<div class="tabs-panel">';
				$h .= '<div class="tab-panel selected" data-tab="create">';
					$h .= '<fieldset>';
						$h .= $form->hidden('variantSource', 'create');

		return $h;

	}

	/** Referme l'onglet « Nouveau produit » et rend « Produit existant », désactivé au départ. */
	public static function getFirstVariantClose(\util\FormUi $form, Product $eProduct): string {

					$h = '</fieldset>';
				$h .= '</div>';
				$h .= '<div class="tab-panel" data-tab="attach">';
					$h .= '<fieldset disabled>';
						$h .= $form->hidden('variantSource', 'attach');
						$h .= self::getAttachField($form, $eProduct, 'product', s("Produit à rattacher"));
					$h .= '</fieldset>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/** Le champ de recherche du produit à rattacher, partagé par le panneau et l'onglet. */
	public static function getAttachField(\util\FormUi $form, Product $eProduct, string $name, string $label): string {

		[
			'query' => $query,
			'results' => $results
		] = $form->autocomplete(
			$name,
			'/product/variantAi:query',
			['id' => $eProduct['id']],
			NULL,
			[],
			FALSE,
			FALSE,
			['placeholder' => s("Tapez un nom de produit")]
		);

		return $form->group(
			$label,
			$form->inputGroup($form->addon(\Asset::icon('box')).$query).$results
		);

	}

	/** Un champ `name` de plus dans le même formulaire : `name[<index>]`, que buildIndex() relit. */
	public static function renameField(string $index, string $label, ?string $after = NULL): \Closure {

		return function(\PropertyDescriber $d) use($index, $label, $after) {
			$d->label = $label;
			$d->name = 'name['.$index.']';
			$d->wrapper = 'name['.$index.']';
			if($after !== NULL and $after !== '') {
				$d->after = \util\FormUi::info(encode($after));
			}
		};

	}

	public static function relabelField(Product $eProduct, string $property, ?\Closure $dCallback = NULL): ?\Closure {

		if($eProduct['variantOf']->empty() or $eProduct->isQuick()) {
			return $dCallback;
		}

		$relabel = match($property) {
			'reference' => function(\PropertyDescriber $d) {
				$d->after = \util\FormUi::info(s("La référence est facultative, mais si vous l'indiquez, elle doit être différente pour chaque produit et variante"));
			},
			'description' => function(\PropertyDescriber $d) {
				$d->label = s("Présentation de la variante");
			},
			'compositionVisibility' => function(\PropertyDescriber $d) {
				$d->values = [
					Product::PUBLIC => s("Visible").'  <span class="color-muted"><small>'.s("La composition de la variante est affichée aux clients").'</small></span>',
					Product::PRIVATE => s("Cachée").'  <span class="color-muted"><small>'.s("Les clients ne voient pas la composition de la variante").'</small></span>'
				];
			},
			'unit' => function(\PropertyDescriber $d) {
				$after = $d->after;
				$d->after = fn(\util\FormUi $form, Product $e) => $e->exists() ?
					(is_closure($after) ? $after($form, $e) : $after) :
					\util\FormUi::info(s("Les unités de vente à la quantité ne peuvent pas être modifiées par la suite, vous devrez créer une autre variante si vous changez d'avis."));
			}
		};

		return function(\PropertyDescriber $d) use($dCallback, $relabel) {

			if($dCallback !== NULL) {
				$dCallback($d);
			}

			$relabel($d);

		};

	}

	/**
	 * Le choix des variantes qu'une duplication emporte. Des cases et de l'identification,
	 * aucun champ de saisie : « on ne modifie les variantes que sur la fiche produit »
	 * (2026-09-02) vaut ici aussi, et le panneau ne pose qu'une question binaire.
	 *
	 * Le balisage est celui que FormUi::checkboxes() rend (Form.u.php:1023), reproduit à la
	 * main pour une seule raison : checkboxes() pose le même `name` sur toutes les cases et
	 * ne sait pas produire variantList[<id>][duplicate]. Le « Tout cocher / Tout décocher »
	 * réemploie le CheckboxField.check() du cadriciel, qui filtre par PRÉFIXE de nom.
	 */
	public function getDuplicateForm(\util\FormUi $form, \Collection $cProductVariant): string {

		$h = '<h3>'.s("Variantes à dupliquer").' <span class="util-counter">'.$cProductVariant->count().'</span></h3>';

		$h .= '<div class="form-control field-radio-group field-radio-group-1">';

			$h .= '<div class="field-radio-group-action">';
				$h .= '<a '.attr('onclick', 'CheckboxField.check(this, "variantList[", true)').'>'.s("Tout cocher").'</a>';
				$h .= ' / ';
				$h .= '<a '.attr('onclick', 'CheckboxField.check(this, "variantList[", false)').'>'.s("Tout décocher").'</a>';
			$h .= '</div>';

			foreach($cProductVariant as $eProductVariant) {
				$h .= $this->getDuplicateLine($form, $eProductVariant);
			}

		$h .= '</div>';

		return $h;

	}

	private function getDuplicateLine(\util\FormUi $form, Product $eProductVariant): string {

		// Le nom porte déjà la variété (getName), comme partout ailleurs sur les variantes
		// depuis le retour du 2026-09-02 : la référence est donc seule sous le nom.
		$label = $eProductVariant->getName('html');

		// Deux variantes d'une même famille peuvent porter le même nom, l'unité étant tout ce
		// qui les sépare (2026-09-06) : sans elle, les cases à cocher d'une duplication
		// sont indiscernables et le choix se fait à l'aveugle. Même glyphe que la liste des
		// produits (ProductUi::getInfos()), à quoi il sert déjà de repère.
		if($eProductVariant['unit']->notEmpty()) {
			$label .= '<span class="product-item-label-unit">'.UnitUi::getSingular($eProductVariant['unit']).'</span>';
		}

		if($eProductVariant['status'] === Product::INACTIVE) {
			$label .= ' <span class="badge badge-solid bg-secondary">'.s("Inactive").'</span>';
		}

		if($eProductVariant['reference'] !== NULL) {
			$label .= '<small>'.encode($eProductVariant['reference']).'</small>';
		}

		return $form->checkbox('variantList['.$eProductVariant['id'].'][duplicate]', 1, [
			'checked' => ($eProductVariant['status'] === Product::ACTIVE),
			'callbackFieldAttributes' => ['class' => 'field-radio-group-item'],
			'callbackLabel' => fn($input) => $input.'<div>'.$label.'</div>'
		]);

	}

	/**
	 * Le régime de prix de la famille, dans le pavé de la fiche du principal et non dans son
	 * formulaire (2026-09-10, comme le stock avant lui) : une phrase et une action, rien
	 * d'autre (maquette « Le régime de prix, sans tableau », 2026-09-02, et arbitrage le
	 * même jour : « On ne garde que ça »).
	 *
	 * Ce n'est pas un champ, et ce n'est pas un oubli : priceMode reste hors de
	 * getPropertiesUpdate(), un bouton radio n'écrirait donc rien, et les deux gestes agissent
	 * de toute façon avant Enregistrer, par un panneau qui chiffre ce qu'il va écraser. Un
	 * contrôle qui ment sur le moment où il agit n'a pas sa place ici. C'est aussi pourquoi la
	 * méthode ne prend pas de FormUi : elle ne pose aucun champ.
	 */
	public function getPriceModeBanner(Product $eProduct): string {

		if(
			$eProduct['variantOf']->notEmpty() or
			$eProduct['variants'] === 0
		) {
			return '';
		}

		$h = '<div class="product-price-mode">';

					if($eProduct['priceMode'] === Product::SHARED) {

						$h .= '<span>'.s("Identique pour les variantes").'</span>';
						$h .= '<a data-ajax="/product/product:doSeparatePrices" post-id="'.$eProduct['id'].'" class="btn btn-outline-primary btn-sm" data-confirm="'.s("Chaque variante gardera ses prix actuels et vous pourrez ensuite les modifier librement. Continuer ?").'">'.s("Chaque variante a son prix").'</a>';

					} else {

						$h .= '<span>'.s("Chaque variante a son prix").'</span>';
						$h .= '<a href="/product/product:unifyPrices?id='.$eProduct['id'].'" class="btn btn-outline-primary btn-sm">'.s("Unifier les prix").'</a>';

					}

		$h .= '</div>';

		return $h;

	}

	private static function getPriceWithTaxes(Product $eProduct, float $price, string $type): string {

		$h = \util\TextUi::money($price);

		if($eProduct['structure']->conf()['hasVat']) {
			$h .= ' '.ProductUi::getTaxes($type);
		}

		return $h;

	}

	/**
	 * L'écran 7 de la maquette : ce que l'unification va écrire, ligne par ligne et
	 * cellule par cellule, avant de l'écrire. Un tableau et pas une phrase, parce qu'une
	 * phrase générique cacherait ce que le producteur doit voir — sur la famille de
	 * mesure, six variantes sur huit ne se vendent pas aux particuliers et le
	 * deviendraient.
	 *
	 * $unify vient de VariantLib::countUnifyPrices(), préparé en page.
	 */
	public function unifyPrices(Product $eProduct, array $unify): \Panel {

		$form = new \util\FormUi();

		$conflicts = array_filter($unify['grids'], fn(array $divergence) => $divergence['cGrid']->count() > 1);
		$extended = count($unify['grids']) - count($conflicts);

		$h = '<div id="product-unify-prices">';

			$h .= $form->hidden('id', $eProduct['id']);

			$detail = '';

			if($unify['values'] > 0) {
				$detail .= $this->getUnifyTable($eProduct, $unify);
			}

			if($conflicts !== []) {
				$detail .= $this->getUnifyConflicts($form, $eProduct, $conflicts);
			}

			$product = '<b>'.encode($eProduct['name']).'</b>';

			// Le compte est celui du tableau des prix : une variante qui porte déjà les prix du
			// produit ne prend rien, la compter ferait dire au bloc un autre nombre que le tableau.
			$consequences = [
				['icon' => 'tags', 'text' => p(
					"<b>La variante</b> prendra les prix de {product}.",
					"<b>Les {value} variantes</b> prendront les prix de {product}.",
					$unify['values'] > 0 ? $unify['variants'] : $eProduct['variants'],
					['product' => $product]
				)]
			];

			if($unify['values'] > 0) {
				$consequences[] = ['icon' => 'eraser', 'text' => p(
					"<b>{value} valeur</b> saisie sur les variantes sera remplacée.",
					"<b>{value} valeurs</b> saisies sur les variantes seront remplacées.",
					$unify['values']
				)];
			}

			if($extended > 0) {
				$consequences[] = ['icon' => 'arrows-expand', 'text' => p(
					"<b>Un prix personnalisé</b> posé sur une partie des variantes s'étendra à toute la famille.",
					"<b>{value} prix personnalisés</b> posés sur une partie des variantes s'étendront à toute la famille.",
					$extended
				)];
			}

			// Rien d'écrasé, rien de perdu : la famille se redifférencie à la main comme avant.
			$h .= \main\DesignUi::getWeigh(
				$unify['values'] > 0 ? 'danger' : 'info',
				'tags',
				s("Unifier les prix de {product}", ['product' => $product]),
				NULL,
				$consequences,
				[s("Les prix de {product} ne changent pas.", ['product' => $product])],
				$unify['values'] === 0
			);

			$h .= $detail;

		$h .= '</div>';

		// Un panneau qui écrase des prix saisis ne laisse pas la croix du coin comme seule façon de renoncer.
		$footer = $form->submit(s("Unifier les prix"), ['class' => 'btn '.($unify['values'] > 0 ? 'btn-danger' : 'btn-primary')]);
		$footer .= ' <a onclick="Lime.Panel.closeLast()" class="btn btn-outline-primary">'.s("Annuler").'</a>';

		return new \Panel(
			id: 'panel-product-unify-prices',
			title: s("Unifier les prix de la famille"),
			subTitle: '<div class="panel-header-subtitle">'.encode($eProduct['name']).' · '.p("{value} variante", "{value} variantes", $eProduct['variants']).'</div>',
			dialogOpen: $form->openAjax('/product/product:doUnifyPrices', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $footer
		);

	}

	/**
	 * Une colonne n'apparaît que si au moins une ligne y diverge : sur la famille de
	 * mesure, 5 colonnes au lieu de 10, et 70 cellules dont 41 sans intérêt en moins.
	 * VariantLib::countUnifyPrices() rend la liste, dans l'ordre de VARIANT_PRICE.
	 */
	private function getUnifyTable(Product $eProduct, array $unify): string {

		$h = '<div class="util-overflow-sm">';
			$h .= '<table class="tr-even product-unify-table">';

				$h .= '<thead><tr>';
					$h .= '<th>'.s("Variante").'</th>';
					foreach($unify['properties'] as $property) {
						$h .= '<th class="text-end">'.self::getUnifyColumn($property).'</th>';
					}
				$h .= '</tr></thead>';

				$h .= '<tbody>';

					foreach($unify['cProductVariant'] as $eProductVariant) {

						$h .= '<tr>';
							$h .= '<td>'.encode($eProductVariant->getName()).'</td>';

							foreach($unify['properties'] as $property) {

								$change = $eProductVariant['changes'][$property];
								$to = self::getUnifyValue($eProduct, $property, $change['to']);

								if($change['changed'] === FALSE) {
									$h .= '<td class="text-end color-muted">'.$to.'</td>';
									continue;
								}

								$from = self::getUnifyValue($eProduct, $property, $change['from']);

								// Ce que la variante n'avait pas et va recevoir n'est pas une
								// destruction : c'est ce que le producteur doit voir en premier,
								// à commencer par un canal de vente fermé qui se rouvre.
								$opened = ($change['from'] === NULL or $change['from'] === FALSE);

								$h .= '<td class="text-end '.($opened ? 'product-unify-open' : 'product-unify-change').'">';
									$h .= '<span class="product-unify-was">'.$from.'</span>';
									$h .= '<span class="product-unify-arrow">'.\Asset::icon('arrow-right').'</span>';
									$h .= '<span class="product-unify-now">'.$to.'</span>';
								$h .= '</td>';

							}

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';
		$h .= '</div>';

		// La légende nomme ce que la couleur marque VRAIMENT. « un canal de vente s'ouvre »
		// (maquette du 2026-09-02) ne valait que pour deux des dix champs : sur une famille
		// mesurée, neuf cellules vertes sur onze étaient des prix, des multiples ou un colis
		// (relecteur du 2026-09-02, n° 3). La règle et les couleurs ne bougent pas.
		$h .= '<div class="product-unify-legend">';
			$h .= '<span class="badge badge-success">'.s("une valeur s'ajoute").'</span>';
			$h .= '<span class="badge badge-danger">'.s("une valeur est remplacée ou retirée").'</span>';
			$h .= '<span class="color-muted">'.s("une valeur qui ne change pas").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête d'une colonne du tableau d'unification. Les libellés du describer disent
	 * le champ dans un formulaire (« Vente aux clients particuliers », et « Multiple de
	 * vente » deux fois) : dans un tableau qui met les deux canaux côte à côte, il faut
	 * un libellé court qui nomme son canal.
	 */
	private static function getUnifyColumn(string $property): string {

		return match($property) {
			'private' => s("Vente aux particuliers"),
			'privatePrice' => s("Prix particulier"),
			'privatePriceInitial' => s("Prix particulier de base"),
			'privateStep' => s("Multiple particulier"),
			'privatePriceSteps' => s("Remise sur volume particulier"),
			'pro' => s("Vente aux pros"),
			'proPrice' => s("Prix pro"),
			'proPriceInitial' => s("Prix pro de base"),
			'proStep' => s("Multiple pro"),
			'proPriceSteps' => s("Remise sur volume pro"),
			'proPackaging' => s("Colis pro"),
			'discountable' => s("Remisable")
		};

	}

	private static function getUnifyValue(Product $eProduct, string $property, mixed $value): string {

		if($value === NULL) {
			return s("aucun");
		}

		return match($property) {

			'private', 'pro', 'discountable' => $value ? s("oui") : s("non"),

			'privatePrice', 'privatePriceInitial' => self::getPriceWithTaxes($eProduct, $value, 'private'),
			'proPrice', 'proPriceInitial' => self::getPriceWithTaxes($eProduct, $value, 'pro'),

			'privateStep', 'proStep', 'proPackaging' => UnitUi::getValue($value, $eProduct['unit']),

			'privatePriceSteps' => ($value === []) ? s("aucun") : ProductUi::getVolumes($value, $eProduct['unit'], stacked: TRUE),
			'proPriceSteps' => ($value === []) ? s("aucun") : ProductUi::getVolumes($value, $eProduct['unit'], $eProduct['proPackaging'], stacked: TRUE)

		};

	}

	/**
	 * Les prix personnalisés que l'unification ne peut pas trancher seule : une carte par
	 * client, les prix en présence et qui les porte, un choix (direction G1 du
	 * maquetteur, bornée par sa mesure — au maximum trois clients en conflit sur
	 * toute la base, et 79 % des familles ne voient jamais cette section).
	 * Le prix majoritaire est en tête et présélectionné : ce n'est pas un choix par
	 * défaut, c'est le nombre de lignes qui le porte, et la carte le dit.
	 */
	private function getUnifyConflicts(\util\FormUi $form, Product $eProduct, array $conflicts): string {

		$h = '<h3>'.s("Prix personnalisés en conflit").' <span class="util-counter">'.count($conflicts).'</span></h3>';

		$h .= '<p>'.p(
			"Un de vos clients ne paie pas le même prix sur toutes les variantes de {product}. En prix commun, un client paie un seul prix pour la famille : choisissez lequel.",
			"{value} de vos clients ne paient pas le même prix sur toutes les variantes de {product}. En prix commun, un client paie un seul prix pour la famille : choisissez lequel.",
			count($conflicts),
			['product' => '<b>'.encode($eProduct['name']).'</b>']
		).'</p>';

		foreach($conflicts as $target => $divergence) {

			$values = [];

			foreach($divergence['cGrid'] as $eGrid) {

				$price = '';

				if($eGrid['priceInitial'] !== NULL) {
					$price .= new PriceUi()->priceWithoutDiscount($eGrid['priceInitial']);
				}

				$price .= \util\TextUi::money($eGrid['price']);

				$values[$eGrid['id']] = $price.'  <span class="color-muted"><small>'.encode(implode(', ', $eGrid['cProduct']->getColumn('name'))).' '.p("({value} variante)", "({value} variantes)", $eGrid['cProduct']->count()).'</small></span>';

			}

			$h .= '<div class="util-block">';

				$h .= '<div class="product-unify-target">';
					// Un conflit ne vient que des grilles de selling (VariantLib::getGridDivergences()),
					// qui seul sait nommer le client ou le groupe qui le porte.
					$h .= implode('', self::notify('getUnifyTarget', $divergence));
				$h .= '</div>';

				$h .= $form->radios('grid['.$target.']', $values, array_key_first($values), ['required' => TRUE]);

			$h .= '</div>';

		}

		return $h;

	}

}
?>
