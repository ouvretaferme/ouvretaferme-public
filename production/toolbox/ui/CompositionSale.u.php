<?php
namespace toolbox;

/**
 * Mettre une semaine des paniers en vente dans un produit composé (r37b, planche validée
 * le 01/10, http://127.0.0.1:8750/paniers-maquette-r37b/) : la porte « Mettre en vente » de la carte
 * d'une semaine, et la page « Mettre en vente la semaine » qui dit quel produit en vente porte chaque
 * légume du panier. Le panneau qui suit est celui de la composition de vente, que selling sert
 * prérempli (`urlPanel()`).
 *
 * Tout ce que ces écrans affichent arrive calculé par `CompositionSaleLib` : ce fichier ne fait que
 * placer et écrire.
 */
class CompositionSaleUi {

	use \Notifiable;

	public function __construct() {
		\Asset::css('toolbox', 'composition.css');
		\Asset::js('toolbox', 'composition.js');
	}

	/**
	 * La page « Composition de <produit> » du produit composé `$eProductComposition`.
	 */
	public static function url(\structure\Structure $eStructure, CompositionFormula $eCompositionFormula, int $week, \product\Product $eProductComposition): string {

		return CompositionUi::url($eStructure).'/vente?'.http_build_query([
			'formula' => $eCompositionFormula['id'],
			'week' => $week,
			'composition' => $eProductComposition['id'],
		]);

	}

	/**
	 * Le panneau de composition de vente, prérempli avec la semaine, dont l'adresse vient de selling
	 * (`selling\ToolboxObserverUi`). La porte qui y mène est fermée sans lui
	 * (`CompositionPlan::acceptSell()`).
	 */
	public static function urlPanel(\structure\Structure $eStructure, CompositionFormula $eCompositionFormula, int $week, \product\Product $eProductComposition): string {
		return implode('', self::notify('urlPanel', $eStructure, $eCompositionFormula, $week, $eProductComposition));
	}

	/**
	 * La porte « Utiliser dans un produit » (r45, ex « Mettre en vente » puis « Mettre à jour une composition ») de la carte de la semaine `$eCompositionWeek` (planche r37, variante
	 * A, et r37b) : un menu des produits composés de la structure, chacun avec son prix. Quand chaque
	 * ligne de la semaine a un produit retenu (`retained`, `CompositionSaleLib::applyRetained()`), un
	 * produit composé ouvre droit le panneau de composition prérempli ; sinon la page, qui demande ce
	 * qui manque. Le produit d'une espèce se règle aussi dans « Régler une espèce » (r39), et la page
	 * n'a plus d'entrée sans produit composé.
	 *
	 * Sans produit composé, le menu le dit et propose d'en créer un.
	 */
	public function getDoor(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek): string {

		$eStructure = $eCompositionPlan['structure'];
		$cProductComposition = $eCompositionPlan['cProductComposition'];
		$week = $eCompositionWeek['week'];

		$h = '<a data-dropdown="bottom-start" class="btn btn-light dropdown-toggle">'.\Asset::icon('basket3').' '.s("Utiliser dans un produit").'</a>';
		$h .= '<div class="dropdown-list">';

			if($cProductComposition->empty()) {
				$h .= '<div class="dropdown-title">'.s("Aucun produit composé à vendre").'</div>';
			} else {

				$h .= '<div class="dropdown-title">'.s("Mettre à jour la composition de").'</div>';

				foreach($cProductComposition as $eProductComposition) {

					$url = $eCompositionWeek['retained'] ?
						self::urlPanel($eStructure, $eCompositionFormula, $week, $eProductComposition) :
						self::url($eStructure, $eCompositionFormula, $week, $eProductComposition);

					$h .= '<a href="'.$url.'" class="dropdown-item">';
						$h .= encode($eProductComposition['name']);
						if($eProductComposition['price'] !== NULL) {
							$h .= ' <span class="util-annotation">'.\util\TextUi::money($eProductComposition['price']).'</span>';
						}
					$h .= '</a>';

				}

				$h .= '<div class="dropdown-divider"></div>';

			}

			$h .= '<a href="'.self::urlCreateComposition($eStructure).'" class="dropdown-item">'.\Asset::icon('plus-circle').' '.s("Nouveau produit composé").'</a>';

		$h .= '</div>';

		return $h;

	}

	protected static function urlCreateComposition(\structure\Structure $eStructure): string {
		return '/product/product:create?structure='.$eStructure['id'].'&profile='.\product\Product::COMPOSITION;
	}

	/**
	 * La création d'un produit pour une ligne (r37b) : l'espèce et l'unité de la structure, que la
	 * création de produit reprend, et le prix que les paniers ont employé pour la ligne, posé sur le
	 * canal `$type` de la vente (`CompositionSaleLib::getType()`) : le prix aux professionnels quand
	 * le produit composé se vend à eux, sinon le prix aux particuliers, y compris sans produit composé.
	 * Une espèce dont la structure n'a pas la copie, ou une unité qu'elle n'a pas, se choisit dans le
	 * panneau. Le panneau « Régler une espèce » (r39b) passe par la même adresse, sans canal, avec le
	 * prix que le panier emploie pour l'espèce.
	 */
	public static function urlCreateProduct(\structure\Structure $eStructure, \plant\Plant $ePlant, \product\Unit $eUnit, ?float $unitPrice, ?string $type): string {

		$arguments = [
			'structure' => $eStructure['id'],
			'profile' => \product\Product::PLANT,
		];

		if($ePlant->notEmpty()) {
			$arguments['plant'] = $ePlant['id'];
		}

		if($eUnit->notEmpty()) {
			$arguments['unit'] = $eUnit['id'];
		}

		if($unitPrice !== NULL) {
			$arguments[($type === 'pro') ? 'proPrice' : 'privatePrice'] = round($unitPrice, 2);
		}

		return '/product/product:create?'.http_build_query($arguments);

	}

	/**
	 * La page « Composition de <produit> » (planche r39c arbitrée le 01/10,
	 * http://127.0.0.1:8750/paniers-maquette-r39c/, piste C) : le titre, « Préparer la composition » à
	 * sa droite, la taille et ses jours en contexte atténué, puis une ligne par légume du panier avec
	 * sa quantité et le produit en vente qui le porte. Le jour de livraison n'y est pas : le panneau de
	 * composition qui suit le propose.
	 *
	 * Plus aucun champ ne se voit. Le produit proposé se lit en texte, et ne devient un menu que là où
	 * le légume a plusieurs produits (`getProduct()`). Ce qui part est un champ caché par ligne,
	 * `product[<id de l'espèce>]`, que la page construit sur l'espèce du plan (`buildIndex()`).
	 *
	 * Les écarts de prix se disent en une phrase sous le contexte, sans geste (`getPrices()`).
	 *
	 * Tant qu'aucune ligne n'a de produit proposé, pas de bouton : le panneau refuserait une
	 * composition vide (`product.none`). Chaque ligne porte alors « Créer le produit » (décision 4).
	 *
	 * Seules les lignes sans produit vendu valide se montrent (`linked`, r39) : la première fois,
	 * toutes. Le produit d'une ligne liée part quand même, en champ caché, et se construit comme les
	 * autres : la composition préparée reste celle du panier entier.
	 *
	 * `$type` est le canal de la vente du produit composé.
	 */
	public function getPage(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek, \product\Product $eProductComposition, string $type): string {

		$eStructure = $eCompositionPlan['structure'];
		$week = $eCompositionWeek['week'];

		$form = new \util\FormUi();

		$cCompositionLineLinked = $eCompositionWeek['cCompositionLine']->find(fn(CompositionLine $eCompositionLine) => $eCompositionLine['linked']);
		$cCompositionLineAsked = $eCompositionWeek['cCompositionLine']->find(fn(CompositionLine $eCompositionLine) => $eCompositionLine['linked'] === FALSE);

		$hasProposed = $eCompositionWeek['cCompositionLine']->contains(fn(CompositionLine $eCompositionLine) => $eCompositionLine['eProductProposed']->notEmpty());

		$h = $form->openAjax('/toolbox/compositionSale:doUpdate');

			$h .= $form->hidden('id', $eStructure);
			$h .= $form->hidden('formula', $eCompositionFormula);
			$h .= $form->hidden('week', $week);
			// Le produit composé ne se poste pas en `product` : les choix des lignes s'y postent en
			// `product[<espèce>]`, et PHP, qui lit les deux sous la même clé, garderait le tableau seul.
			$h .= $form->hidden('composition', $eProductComposition);

			$days = \util\DateUi::weekToDays(CompositionUi::getIsoWeek($eCompositionPlan['season'], $week), withYear: FALSE);

			$h .= \main\DesignUi::getPageHeader(
				s("Composition de {product}", ['product' => encode($eProductComposition['name'])]),
				context: s("{formula}, semaine {week}, {days}", ['formula' => encode($eCompositionFormula['name']), 'week' => $week, 'days' => $days]).$this->getPrices($eCompositionFormula, $eCompositionWeek, $eProductComposition),
				back: CompositionUi::urlFormula($eStructure, $eCompositionFormula),
				create: $hasProposed ? $form->submit(s("Préparer la composition"), ['class' => 'btn btn-secondary']) : ''
			);

			if($cCompositionLineAsked->notEmpty()) {

				$h .= '<div class="util-table util-table-bordered toolbox-composition-pick mt-1 mb-1">';

					$h .= '<div class="util-table-head">';
						$h .= '<div>'.s("Légume").'</div>';
						$h .= '<div class="text-end">'.s("Par panier").'</div>';
						$h .= '<div>'.s("Produit vendu").'</div>';
					$h .= '</div>';

					foreach($cCompositionLineAsked as $eCompositionLine) {
						$h .= $this->getLine($form, $eStructure, $eCompositionLine, $type);
					}

				$h .= '</div>';

				$h .= $this->getCreate($eStructure, $cCompositionLineAsked->find(fn(CompositionLine $eCompositionLine) => $eCompositionLine['cProductCandidate']->notEmpty()), $type);

			}

			foreach($cCompositionLineLinked as $eCompositionLine) {
				$h .= $form->hidden('product['.$eCompositionLine['species']['id'].']', $eCompositionLine['eCompositionSpecies']['product']);
			}

		$h .= $form->close();

		return $h;

	}

	/**
	 * Les écarts de prix, en une phrase au poids du contexte (r39c, décision 2, qui remplace les deux
	 * bandeaux `util-warning` de r39) : elle part du prix du produit composé, que paie le client, et
	 * ne garde que les écarts présents, la valeur des légumes de la semaine hors de la tolérance de la
	 * taille (`gapProduct`) et une taille réglée à un autre prix (`priceDiffers`). Seuls les chiffres
	 * qui s'écartent prennent la couleur d'alerte. Rien ne s'y clique.
	 *
	 * Sans prix sur le produit composé, pas de phrase : `CompositionSaleLib::applyAlerts()` n'a rien à
	 * comparer.
	 */
	protected function getPrices(CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek, \product\Product $eProductComposition): string {

		if($eProductComposition['price'] === NULL) {
			return '';
		}

		$arguments = [
			'price' => \util\TextUi::money($eProductComposition['price']),
			'value' => '<span class="color-warning">'.\util\TextUi::money($eCompositionWeek['value']).'</span>',
			'formula' => '<span class="toolbox-composition-pick-formula">'.s("« {value} »", encode($eCompositionFormula['name'])).'</span>',
			'formulaPrice' => '<span class="color-warning">'.\util\TextUi::money($eCompositionFormula['price']).'</span>',
		];

		if($eCompositionWeek['gapProduct'] and $eCompositionWeek['priceDiffers']) {
			$prices = s("Vendu {price}, les légumes de la semaine valent {value} et le panier {formula} est à {formulaPrice}.", $arguments);
		} else if($eCompositionWeek['gapProduct']) {
			$prices = s("Vendu {price}, les légumes de la semaine valent {value}.", $arguments);
		} else if($eCompositionWeek['priceDiffers']) {
			$prices = s("Vendu {price}, le panier {formula} est à {formulaPrice}.", $arguments);
		} else {
			$prices = s("Vendu {price}.", $arguments);
		}

		return '<div class="toolbox-composition-pick-prices">'.$prices.'</div>';

	}

	/**
	 * Une ligne du tableau : le légume, sa quantité au panier, et le produit vendu (`getProduct()`),
	 * ou « Créer le produit » quand aucun produit en vente ne porte le légume.
	 */
	protected function getLine(\util\FormUi $form, \structure\Structure $eStructure, CompositionLine $eCompositionLine, string $type): string {

		$speciesId = $eCompositionLine['species']['id'];

		$h = '<div class="util-table-row">';

			$h .= '<div>';
				$h .= '<div class="util-vignette">';
					$h .= \plant\PlantUi::getVignette($eCompositionLine['species']['plant'], '2rem');
					$h .= '<span>'.encode($eCompositionLine['species']->getLabel()).'</span>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="toolbox-composition-pick-quantity">'.CompositionUi::getQuantity($eCompositionLine['quantity'], $eCompositionLine['unit']).'</div>';

			$h .= '<div class="toolbox-composition-pick-product" data-wrapper="product['.$speciesId.']">';

				if($eCompositionLine['cProductCandidate']->empty()) {
					$h .= '<a href="'.self::urlCreateProduct($eStructure, $eCompositionLine['ePlantCreate'], $eCompositionLine['eUnitCreate'], $eCompositionLine['unitPrice'], $type).'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Créer le produit").'</a>';
				} else {
					$h .= $this->getProduct($form, $eCompositionLine);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le produit vendu d'une ligne (piste C) : celui que la page propose (`eProductProposed`), en
	 * texte, son prix atténué. Quand le légume a plusieurs produits possibles, ce texte ouvre le menu
	 * de ces produits, le retenu marqué, comme le nom de la boutique dans l'analyse des ventes
	 * (`selling\AnalyzeUi::getShop()`). Un légume à un seul produit n'a rien à choisir.
	 *
	 * Choisir dans le menu ne fait aucune requête : `ToolboxCompositionPick` (`composition.js`)
	 * remplace le texte et la valeur du champ caché, que « Préparer la composition » envoie. Le menu
	 * ouvert quitte le formulaire (`Lime.Dropdown.open()` le déplace sous `<body>`) : le champ caché
	 * reste donc dans la ligne, et chaque entrée dit de quelle espèce elle est le choix
	 * (`data-composition-pick`).
	 */
	protected function getProduct(\util\FormUi $form, CompositionLine $eCompositionLine): string {

		$cProductCandidate = $eCompositionLine['cProductCandidate'];
		$eProductProposed = $eCompositionLine['eProductProposed'];
		$speciesId = $eCompositionLine['species']['id'];

		$h = $form->hidden('product['.$speciesId.']', $eProductProposed);

		if($cProductCandidate->count() === 1) {

			$h .= encode(self::getCandidateTitle($eProductProposed)).' <span class="color-muted">'.encode(self::getCandidatePrice($eProductProposed)).'</span>';

			return $h;

		}

		$h .= '<a data-dropdown="bottom-start" class="dropdown-toggle" data-species="'.$speciesId.'">'.encode(self::getCandidateLabel($eProductProposed)).'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Produit vendu").'</div>';
			foreach($cProductCandidate as $eProduct) {
				$h .= '<a data-composition-pick="'.$speciesId.'" data-product="'.$eProduct['id'].'" class="dropdown-item'.($eProduct['id'] === $eProductProposed['id'] ? ' selected' : '').'">'.encode(self::getCandidateLabel($eProduct)).'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * La création d'un produit, en un seul geste sous le tableau (décision 1) pour les légumes
	 * `$cCompositionLine` qui ont déjà un produit proposé : un menu des légumes, et un lien direct
	 * quand il n'en reste qu'un. Un légume sans aucun produit n'y est pas, il porte son bouton dans sa
	 * ligne.
	 */
	protected function getCreate(\structure\Structure $eStructure, \Collection $cCompositionLine, string $type): string {

		if($cCompositionLine->empty()) {
			return '';
		}

		$url = fn(CompositionLine $eCompositionLine) => self::urlCreateProduct($eStructure, $eCompositionLine['ePlantCreate'], $eCompositionLine['eUnitCreate'], $eCompositionLine['unitPrice'], $type);

		$h = '<div class="toolbox-composition-pick-create">';

			if($cCompositionLine->count() === 1) {
				$h .= '<a href="'.$url($cCompositionLine->first()).'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Créer un produit").'</a>';
			} else {

				$h .= '<a data-dropdown="bottom-start" class="btn btn-sm btn-outline-primary dropdown-toggle">'.\Asset::icon('plus-circle').' '.s("Créer un produit").'</a>';
				$h .= '<div class="dropdown-list">';
					$h .= '<div class="dropdown-title">'.s("Créer un produit de").'</div>';
					foreach($cCompositionLine as $eCompositionLine) {
						$h .= '<a href="'.$url($eCompositionLine).'" class="dropdown-item">'.encode($eCompositionLine['species']->getLabel()).'</a>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Carottes, 4,50 € / kg » : le choix d'un produit tel que les menus de la page et la liste du
	 * panneau « Régler une espèce » le disent, son nom (`getCandidateTitle()`) puis son prix
	 * (`getCandidatePrice()`). Non encodé.
	 */
	public static function getCandidateLabel(\product\Product $eProduct): string {

		return s("{name}, {price}", ['name' => self::getCandidateTitle($eProduct), 'price' => self::getCandidatePrice($eProduct)]);

	}

	/**
	 * Le nom d'un produit parmi les candidats d'une espèce : son nom et sa variété
	 * (`getCandidateName()`), suivis de « en revente » quand il est revendu et qu'un autre candidat
	 * porte le même nom (`resaleNamed`, `CompositionSaleLib::applyResale()`, r39c, décision 5).
	 * Non encodé.
	 */
	public static function getCandidateTitle(\product\Product $eProduct): string {

		$name = self::getCandidateName($eProduct);

		return $eProduct['resaleNamed'] ? s("{name}, en revente", ['name' => $name]) : $name;

	}

	/**
	 * « 4,50 € / kg », et « sans prix » pour un produit qui ne se vend pas sur ce canal à un prix fixé.
	 */
	public static function getCandidatePrice(\product\Product $eProduct): string {

		return ($eProduct['price'] === NULL) ?
			s("sans prix") :
			\util\TextUi::money($eProduct['price']).\product\UnitUi::getBy($eProduct['unit']);

	}

	/**
	 * Le nom d'un produit tel que ses choix le disent, la variété après le nom quand il en porte une :
	 * plusieurs produits d'une même espèce ne se distinguent souvent que par elle. Le panneau « Régler
	 * une espèce » (r39b) nomme ainsi le produit dont l'espèce tient son prix. Non encodé.
	 */
	public static function getCandidateName(\product\Product $eProduct): string {

		$eProduct->expects(['name', 'variety']);

		return ($eProduct['variety'] === NULL) ? $eProduct['name'] : s("{name} / {variety}", ['name' => $eProduct['name'], 'variety' => $eProduct['variety']]);

	}

}
?>
