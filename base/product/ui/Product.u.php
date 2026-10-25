<?php
namespace product;

class ProductUi {

	use \Notifiable;

	// Les deux seuls noms d'onglet qu'une redirection peut demander en ?tab= : ils sortent
	// du fichier, les trois autres ne quittent pas getTabs() et restent des littéraux.
	const TAB_VARIANTS = 'product-variants-tab';
	const TAB_GRID = 'product-grid';

	const ROW_PRODUCT = 'product';
	const ROW_FAMILY = 'family';

	const ROW_VARIANT = 'variant';

	public function __construct() {

		\Asset::css('product', 'product.css');
		\Asset::js('product', 'product.js');

	}

	public static function link(Product $eProduct, bool $newTab = FALSE, bool $withPrincipal = FALSE): string {
		return '<a href="'.self::url($eProduct).'" '.($newTab ? 'target="_blank"' : '').'>'.encode($eProduct->getName(withPrincipal: $withPrincipal)).'</a>';
	}

	/**
	 * La fiche d'une variante n'existe plus pour elle-même : cette
	 * fonction est le seul endroit qui écrit une adresse de fiche produit (24 appels dans 8
	 * fichiers), la faire pointer directement sur la famille évite un aller-retour de
	 * redirection à chacun de ces appels. `offsetExists('variantOf')` et non `expects()` : un élément
	 * délégué (`$eProduct['variantOf']` d'un autre produit, par construction toujours un
	 * principal) ne porte jamais sa propre colonne `variantOf`, et n'a pas à la porter pour
	 * qu'on sache déjà qu'il n'est pas une variante.
	 */
	public static function url(Product $eProduct): string {

		$eProduct->expects(['id']);

		if($eProduct->offsetExists('variantOf') and $eProduct['variantOf']->notEmpty()) {
			return '/produit/'.$eProduct['variantOf']['id'].'?tab='.self::TAB_VARIANTS;
		}

		return '/produit/'.$eProduct['id'];

	}

	public static function getOrganic(): array {
		return \structure\StructureUi::getOrganic();
	}

	public static function getOrganicLogo(string $organic, string $size): string {
		return \structure\StructureUi::getOrganicLogo($organic, $size);
	}

	public static function getQualitiesLabels(): array {
		return \structure\StructureUi::getQualitiesLabels() + [
			Product::IGP => s("IGP"),
			Product::AOP => s("AOP"),
			Product::STG => s("STG"),
			Product::LABEL_ROUGE => s("Label rouge"),
		];
	}

	public static function getQualitiesLogos(?\Set $qualities, string $size): string {

		if($qualities === NULL) {
			return '';
		}

		$list = '';

		foreach(Product::model()->getPropertySet('qualities') as $quality) {

			if($qualities->value($quality)) {
				$list .= self::getQualityLogo($quality, $size);
			}

		}

		return $list;

	}

	public static function getQualityLogo(int $quality, string $size): string {
		return \Asset::image('structure', Product::getQualities()[$quality].'.png', ['style' => 'height: '.$size]);
	}


	public static function getMarksLogos(\Element $e, string $size = '1em'): string {

		\Asset::css('product', 'product.css');

		$e->expects(['organic', 'qualities']);

		$marks = self::getOrganicLogo($e['organic'], $size);
		$marks .= self::getQualitiesLogos($e['qualities'], $size);

		if($marks) {
			return '<div class="product-marks-list">'.$marks.'</div>';
		} else {
			return '';
		}

	}

	public function query(\PropertyDescriber $d, bool $multiple = FALSE) {

		$d->prepend = \Asset::icon('box');
		$d->field = 'autocomplete';

		$d->placeholder ??= s("Tapez un nom de produit");
		$d->multiple = $multiple;
		$d->wrapper = 'product';

		$d->autocompleteUrl = '/product/product:query';
		$d->autocompleteResults = function(Product $e) {
			return self::getAutocomplete($e);
		};

	}

	public function getWarning(\structure\Structure $eStructure): string {

		$h = '<p class="util-empty">'.s("Avant d'aller plus loin, vous devez d'abord constituer votre gamme de produits !").'</p>';
		$h .= '<a href="'.\structure\StructureUi::urlSellingProducts($eStructure).'" class="btn btn-xl btn-primary">'.s("Ajouter un premier produit").'</a>';

		return $h;

	}

	public static function getAutocomplete(Product $eProduct, bool $withPrincipal = FALSE): array {

		\Asset::css('media', 'media.css');

		$details = self::getDetails($eProduct);

		$item = '<div class="flex-align-center">';
			$item .= self::getVignette($eProduct, '2.5rem');
			$item .= '<div>';
				$item .= $eProduct->getName('html', $withPrincipal);
				$item .= '<span style="opacity: 0.75">';
					if($eProduct['distribution'] === Product::RESALE) {
						$item .= ' <span class="badge badge-outline">';
							$item .= self::p('distribution')->shortValues[$eProduct['distribution']];
						$item .= '</span>';
					}
					$item .= ' <span class="badge badge-outline">';
						$item .= mb_ucfirst(\product\UnitUi::getSingular($eProduct['unit']));
					$item .= '</span>';
				$item .= '</span>';
				$item .= '<br/>';
				if($details) {
					$item .= '<small class="color-muted">'.implode(' | ', $details).'</small>';
				}
			$item .= '</div>';
		$item .= '</div>';

		return [
			'value' => $eProduct['id'],
			'itemHtml' => $item,
			'itemText' => $eProduct->getName(withPrincipal: $withPrincipal)
		];

	}

	/**
	 * Les résultats d'une autocomplétion, avec un intertitre au passage aux
	 * produits sans espèce.
	 *
	 * Seulement quand l'appelant a borné les espèces : ailleurs, un produit sans
	 * espèce est le cas ordinaire et l'intertitre n'apprendrait rien. Le tri les
	 * ayant déjà relégués en fin de liste, le basculement se lit sur le premier
	 * d'entre eux.
	 */
	public static function getAutocompleteResults(\Collection $cProduct, bool $withPlantTitle = FALSE, bool $withPrincipal = FALSE): array {

		$results = [];
		$titled = FALSE;

		foreach($cProduct as $eProduct) {

			if($withPlantTitle and $titled === FALSE and $eProduct['plant']->empty()) {

				$results[] = self::getAutocompleteTitle(s("Produits sans espèce"));

				$titled = TRUE;

			}

			$results[] = self::getAutocomplete($eProduct, $withPrincipal);

		}

		return $results;

	}

	public static function getAutocompleteTitle(string $title): array {

		return [
			'type' => 'title',
			'itemHtml' => '<div>'.encode($title).'</div>',
			'itemText' => $title,
		];

	}

	/**
	 * Le dernier rang d'une recherche de produits. Une recherche qui donne son champ (`$autocomplete`)
	 * reçoit le produit créé dans ce champ : le rang nomme alors ce qu'il va créer, et la création
	 * reprend le texte cherché.
	 */
	public static function getAutocompleteCreate(\structure\Structure $eStructure, string $query = '', ?string $autocomplete = NULL, bool $withComposition = TRUE): array {

		$item = \Asset::icon('plus-circle');

		if($autocomplete === NULL) {

			$item .= '<div>'.s("Ajouter un nouveau produit").'</div>';
			$link = '/product/product:create?structure='.$eStructure['id'];

		} else {

			$item .= '<div>'.(($query === '') ? s("Ajouter un nouveau produit") : s("Créer le produit « {value} »", encode($query))).'</div>';
			$link = self::urlCreateAutocomplete($eStructure, $query, $autocomplete, $withComposition);

		}

		return [
			'type' => 'link',
			'link' => $link,
			'itemHtml' => $item
		];

	}

	/**
	 * La création ouverte depuis une recherche, qui rend le produit au champ `$autocomplete`. Le
	 * paramètre ne s'appelle pas `origin` : c'est une propriété du produit, que le même formulaire
	 * poste.
	 */
	public static function urlCreateAutocomplete(\structure\Structure $eStructure, string $name, string $autocomplete, bool $withComposition): string {

		$url = '/product/product:create?structure='.$eStructure['id'].'&name='.urlencode($name).'&autocomplete='.urlencode($autocomplete);

		if($withComposition === FALSE) {
			$url .= '&withComposition=0';
		}

		return $url;

	}

	public static function getPanelHeader(Product $eProduct): string {

		$h = '<div class="panel-header-subtitle">';
			$h .= self::getVignette($eProduct, '2rem').'  ';
			$h .= $eProduct->getName('html');
		$h .= '</div>';

		return $h;

	}

	public static function getProfileIcon(?string $profile, string $size = '2.5rem'): string {

		return '<div class="product-profile-icon" style="background-color: var(--'.$profile.'); width: '.$size.'; height: '.$size.'; font-size: calc('.$size.' / 2)">'.self::p('profile')->icons[$profile].'</div>';

	}

	public static function getProfileDropdown(Product $eProduct, ?\Closure $destination = NULL, array $values = []): string {

		$eProduct->expects(['structure']);

		$destination ??= fn($profile) => 'href="/product/product:create?structure='.$eProduct['structure']['id'].'&profile='.$profile.'"';

		if($values === []) {
			$values = self::p('profile')->values;
		}

		$h = '<div class="dropdown-list">';

			foreach($eProduct->getWriteProfiles() as $profile) {

				if(array_key_exists($profile, $values) === FALSE) {
					continue;
				}

				$label = $values[$profile];
				$examples = self::p('profile')->examples[$profile];

				if($profile === Product::SERVICE) {
					$h .= '<div class="dropdown-divider"></div>';
				}

				$h .= '<a '.$destination($profile).' class="dropdown-item dropdown-item-icon" data-profile="'.$profile.'">';
					$h .= self::getProfileIcon($profile);
					$h .= '<div>';
						$h .= '<span class="product-profile-name">'.$label.'</span>';
						if($examples) {
							$h .= '<br/><small style="color: #fff8">'.$examples.'</small>';
						}
					$h .= '</div>';
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getSearch(\structure\Structure $eStructure, \Search $search): string {

		$form = new \util\FormUi();

		$profiles = self::p('profile')->values;
		unset($profiles[Product::SHIPPING]);

		$h = '<div id="product-search" class="util-block-search '.($search->empty(['category']) ? 'hide' : '').'">';

			$h .= $form->openAjax(\structure\StructureUi::urlSellingProducts($eStructure), ['method' => 'get', 'class' => 'util-search']);
				$h .= $form->hidden('category', $search->get('category'));
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Type").'</legend>';
					$h .= $form->select('profile', $profiles, $search->get('profile'));
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Distribution").'</legend>';
					$h .= $form->select('distribution', self::p('distribution')->values, $search->get('distribution'));
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Référence").'</legend>';
					$h .= $form->text('reference', $search->get('reference'), ['placeholder' => s("Référence du produit")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Nom").'</legend>';
					$h .= $form->text('name', $search->get('name'), ['placeholder' => s("Nom du produit")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Espèce").'</legend>';
					$h .= $form->text('plant', $search->get('plant'));
				$h .= '</fieldset>';

				if(
					$eStructure->hasAccounting() and
					$eStructure->isCashReceipts(\Clock::date()) === FALSE
				) {

					$h .= '<fieldset>';
						$h .= '<legend>'.s("Numéro de compte").'</legend>';
						$accountNumber = $search->get('privateAccountNumber');
						if($search->has('privateAccountNumber')) {
							$eAccount = \account\Account::askByClass($accountNumber);
						}
						if($search->get('noAccount') === TRUE or $accountNumber === NULL) {
							$accountNumber = NULL;
							$eAccount = new \account\Account();
						}
						$h .= $form->dynamicField(new Product(['structure' => $eStructure, 'privateAccountNumber' => $accountNumber, 'privateAccount' => $eAccount]), 'privateAccountNumber', function($d) use($form, $eStructure, $accountNumber) {
							$d->name = 'privateAccountNumber';

							// canHaveNoAccountOption se lit en GET et reste donc dans l'URL ; le reste se
							// lit en POST et n'arrive au serveur que par le corps de la requête, que
							// l'autocomplétion remplit avec le seul autocompleteBody.
							$query = ['canHaveNoAccountOption' => TRUE];
							// Réécrit en entier, autocompleteBody passant par __set
							$d->autocompleteBody = ($d->autocompleteBody ?? []) + [
								'classPrefixes' => [\account\AccountSetting::PRODUCT_SOLD_ACCOUNT_CLASS, \account\AccountSetting::PRODUCT_OTHER_ACCOUNT_CLASS],
								// Champ de recherche : un produit reste configuré sur un compte après sa désactivation
								'withInactive' => 1,
							];
							$d->autocompleteUrl = function() use ($query) {
								return \structure\StructureUi::urlConnected('/account/:query').'&'.http_build_query($query);
							};
						});
					$h .= '</fieldset>';


				}

				$h .= '<div class="util-search-submit">';
					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.\structure\StructureUi::urlSellingProducts($eStructure).'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public function getList(\structure\Structure $eStructure, \Collection $cProduct, array $products, \Collection $cCategory, \Search $search, ?int $page = NULL) {

		$h = '';

		if($cProduct->empty()) {

			if(
				$search->get('category')->empty() and
				$cCategory->notEmpty()
			) {
				$h .= '<div class="util-info">'.s("Sélectionnez une catégorie pour voir les produits associés !").'</div>';
			} else {
				$h .= '<div class="util-block util-block-empty">'.s("Il n'y a aucun produit à afficher.").'</div>';
			}

			return $h;

		}

		$year = \Clock::year();
		$yearBefore = $year - 1;

		$displayStock = $cProduct->match(fn($eProduct) => $eProduct['stock'] !== NULL);
		// Le filtre « sans compte » montre les colonnes de compte pour qu'on y saisisse ce qui manque
		$displayAccounts = ($eStructure->hasAccounting() and $eStructure->isCashReceipts(\Clock::date()) === FALSE and (
			$search->get('noAccount') === TRUE or
			$cProduct->match(fn($eProduct) => (
				$eProduct['proAccountNumber'] !== NULL or
				$eProduct['privateAccountNumber'] !== NULL or
				$eProduct['profileAccountNumber']['proAccountNumber'] !== NULL or
				$eProduct['profileAccountNumber']['privateAccountNumber'] !== NULL
			))
		));

		$columns = [];

		// Les ventes de l'année ne se lisent que là où la commercialisation est installée : sans elle,
		// aucun produit ne porte eItemTotal et la colonne ne se propose pas.
		if(\structure\StructureSetting::hasCommercialisationApp()) {
			$columns[] = \structure\Member::TURNOVER;
		}

		if($displayAccounts) {
			$columns[] = \structure\Member::ACCOUNT;
		}

		$displayColumn = $eStructure->getView('viewSellingProductsColumn');

		if(in_array($displayColumn, $columns) === FALSE) {
			$displayColumn = ($columns === []) ? '' : first($columns);
		}

		if($cProduct->match(fn($eProduct) => $eProduct['variants'] > 0)) {
			\Asset::js('product', 'variantAi.js');
		}

		$h .= '<div class="util-overflow-md stick-md">';

		$h .= '<table class="tbody-even tr-bordered product-list-table" data-batch="#batch-product">';

			$h .= '<thead>';

				$h .= '<tr>';

					$h .= '<th rowspan="2" class="td-checkbox">';
						$h .= '<label title="'.s("Tout cocher / Tout décocher").'">';
							$h .= '<input type="checkbox" class="batch-all" onclick="Product.toggleSelection(this)"/>';
						$h .= '</label>';
					$h .= '</th>';

					$h .= '<th rowspan="2" class="td-vignette"></th>';
					$h .= '<th rowspan="2">'.$search->linkSort('name', s("Nom")).'</th>';
					if($displayStock) {
						$h .= '<th rowspan="2" class="text-end hide-xl-down">'.$search->linkSort('stock', s("Stock"), SORT_DESC).'</th>';
					}
					$h .= '<th rowspan="2">'.s("Unité").'</th>';
					$h .= '<th colspan="2" class="pb-0 text-center t-highlight">'.s("Prix de base").'</th>';
					if($eStructure->conf()['hasVat']) {
						$h .= '<th rowspan="2" class="text-center product-item-vat">'.s("TVA").'</th>';
					}
					if($columns !== []) {
						$h .= '<th colspan="2" class="pb-0 text-center t-highlight hide-lg-down">';

							$label = match($displayColumn) {
								\structure\Member::TURNOVER => s("Ventes"),
								\structure\Member::ACCOUNT => s("Comptes")
							};

							if(count($columns) > 1) {

								$h .= '<a class="dropdown-toggle btn btn-xs btn-outline-primary" data-dropdown="bottom-end">'.$label.'</a>';
								$h .= '<div class="dropdown-list">';
									foreach($columns as $column) {

										$description = match($column) {
											\structure\Member::TURNOVER => s("Afficher les ventes"),
											\structure\Member::ACCOUNT => s("Afficher les numéros de compte")
										};

										$h .= '<a href="'.\util\HttpUi::setArgument(LIME_REQUEST, 'column', $column).'" class="dropdown-item">'.$description.'</a>';

									}
								$h .= '</div>';

							} else {
								$h .= $label;
							}
						$h .= '</th>';
					}
					$h .= '<th rowspan="2" class="text-center">'.s("Activé").'</th>';
					$h .= '<th rowspan="2"></th>';
				$h .= '</tr>';

				$h .= '<tr>';

					$h .= '<th class="text-end t-highlight">'.s("Particulier").'</th>';
					$h .= '<th class="text-end t-highlight">'.s("Pro").'</th>';

					switch($displayColumn) {

						case \structure\Member::TURNOVER :
							$h .= '<th class="text-end t-highlight hide-lg-down">'.$year.'</th>';
							$h .= '<th class="text-end t-highlight product-item-year-before hide-lg-down">'.$yearBefore.'</th>';
							break;

						case \structure\Member::ACCOUNT :
							$h .= '<th class="text-end t-highlight hide-lg-down">'.s("particulier").'</th>';
							$h .= '<th class="text-end t-highlight hide-lg-down">'.s("pro").'</th>';
							break;

					}
				$h .= '</tr>';

			$h .= '</thead>';

			foreach($cProduct as $eProduct) {

				if($eProduct['variants'] > 0) {

					$cProductVariant = $eProduct['cProductVariant'];

					$h .= '<tbody id="product-family-'.$eProduct['id'].'" class="product-family tbody-group'.($cProductVariant->notEmpty() ? ' product-family-open' : '').'">';
						$h .= $this->getListRow($eStructure, $eProduct, self::ROW_FAMILY, $displayStock, $displayColumn);
					$h .= '</tbody>';

					$h .= '<tbody id="product-family-'.$eProduct['id'].'-variants" class="product-family-variants tbody-group-sub">';
						$h .= $this->getListVariants($eStructure, $cProductVariant, $displayStock, $displayColumn);
					$h .= '</tbody>';

				} else {

					$h .= '<tbody>';
						$h .= $this->getListRow($eStructure, $eProduct, self::ROW_PRODUCT, $displayStock, $displayColumn);
					$h .= '</tbody>';

				}

			}

		$h .= '</table>';

		$h .= '</div>';

		if($page !== NULL) {

			$eCategorySelected = $search->get('category');
			$nProduct = ($products[$eCategorySelected->notEmpty() ? $eCategorySelected['id'] : NULL] ?? 0);

			$h .= \util\TextUi::pagination($page, $nProduct / ProductLib::PAGE);

		}

		$h .= $this->getBatch($eStructure, $cCategory);

		return $h;

	}

	/** Un compte que le produit tient des réglages de précomptabilité, faute d'en avoir un. */
	protected function getAccountNumberInherited(string $accountNumber): string {
		return '<span class="color-muted">'.encode($accountNumber).'</span>';
	}

	/** Un compte pour particuliers qu'une vente pro reprend, faute de compte pour professionnels. */
	protected function getAccountNumberPrivateForPro(string $accountNumber): string {
		return '<span class="color-muted" title="'.s("Numéro de compte pour particuliers utilisé pour les professionnels").'">'.\Asset::icon('magic').' '.encode($accountNumber).'</span>';
	}

	public function getListVariants(\structure\Structure $eStructure, \Collection $cProductVariant, bool $displayStock, string $displayColumn): string {

		$h = '';

		foreach($cProductVariant as $eProductVariant) {
			$h .= $this->getListRow($eStructure, $eProductVariant, self::ROW_VARIANT, $displayStock, $displayColumn);
		}

		return $h;

	}

	protected function getListRow(\structure\Structure $eStructure, Product $eProduct, string $role, bool $displayStock, string $displayColumn): string {

		$isFamily = ($role === self::ROW_FAMILY);
		$isVariant = ($role === self::ROW_VARIANT);

		$hasOwnPrice = match($role) {
			self::ROW_PRODUCT => TRUE,
			self::ROW_FAMILY => ($eProduct['priceMode'] === Product::SHARED),
			self::ROW_VARIANT => ($eProduct['variantOf']['priceMode'] !== Product::SHARED)
		};

		// La ligne tient-elle une quantité pour son compte ? Le pendant de $hasOwnPrice pour le
		// stock : le contenant d'une famille ne se vend pas et ne tient rien, chaque variante
		// suit le sien.
		$hasOwnStock = ($isFamily === FALSE);

		$quickPrice = fn(string $property, string $value) => $isFamily ? $value : $eProduct->quick($property, $value);

		$classes = match($role) {
			self::ROW_PRODUCT => '',
			self::ROW_FAMILY => 'tr-title ',
			self::ROW_VARIANT => 'tr-sub '
		};

		$h = '<tr'.($isVariant ? ' data-variant-id="'.$eProduct['id'].'"' : '').' class="'.$classes.($eProduct['status'] === Product::INACTIVE ? 'tr-disabled' : '').'">';

			$h .= '<td class="td-checkbox">';
				if($isVariant === FALSE) {
					$h .= '<label>';
						$h .= '<input type="checkbox" name="batch[]" value="'.$eProduct['id'].'" oninput="Product.changeSelection()"/>';
					$h .= '</label>';
				}
			$h .= '</td>';

			$h .= '<td class="td-vignette">';
				$h .= new \media\ProductVignetteUi()->getCamera($eProduct, size: $isVariant ? '2.5rem' : '3.5rem');
			$h .= '</td>';

			$h .= '<td class="product-item-name">';
				$h .= match($role) {
					self::ROW_PRODUCT => self::getInfos($eProduct),
					self::ROW_FAMILY => VariantAiUi::getListFamilyName($eProduct, $displayStock, $displayColumn),
					self::ROW_VARIANT => VariantAiUi::getListVariantName($eProduct)
				};
			$h .= '</td>';

			if($displayStock) {
				$h .= '<td class="product-item-stock text-end hide-xl-down">';
					if($eProduct->acceptStock()) {
						$h .= \product\StockUi::link($eProduct, withExpired: TRUE);
					} else if($hasOwnStock) {
						// La croix des prix, pour le même fait : cette ligne pourrait tenir une
						// quantité et n'en tient aucune. Une cellule vide se lit comme une
						// donnée manquante.
						$h .= \Asset::icon('x');
					}
				$h .= '</td>';
			}

			$h .= '<td class="product-item-unit">';
				$h .= $isFamily ? '' : \product\UnitUi::getSingular($eProduct['unit']);
			$h .= '</td>';

			$h .= '<td class="product-item-price t-highlight text-end">';

				if($hasOwnPrice === FALSE) {
					$h .= $isFamily ? '' : VariantAiUi::getSameAsFamilyValue();
				} else if($eProduct['private'] === FALSE) {
					$h .= \Asset::icon('x');
				} else {

					$taxes = $eStructure->conf()['hasVat'] ? ' <span class="util-annotation">'.self::getTaxes('private').'</span>' : '';

					if($eProduct['privatePrice'] !== NULL) {
						$value = \util\TextUi::money($eProduct['privatePrice']).$taxes;
						if($eProduct['privatePriceInitial'] !== NULL) {
							$h .= new PriceUi()->priceWithoutDiscount($eProduct['privatePriceInitial'], unit: $taxes);
						}
					} else if($eProduct['proPrice'] !== NULL) {
						$value = '<span class="color-muted" title="'.s("Prix calculé à partir du prix pour les professionnels augmenté de la TVA.").'">'.\Asset::icon('magic').' ';
							$value .= \util\TextUi::money($eProduct->calcPrivateMagicPrice($eStructure->hasVat())).$taxes;
						$value .= '</span>';
					} else {
						$value = \Asset::icon('question');
					}

					$h .= $quickPrice('privatePrice', $value);
					$h .= self::getVolumes($eProduct->getEffectivePriceSteps('private'), $eProduct['unit'], stacked: TRUE);

				}

			$h .= '</td>';

			$h .= '<td class="product-item-price t-highlight text-end">';

				if($hasOwnPrice === FALSE) {
					$h .= $isFamily ? '' : VariantAiUi::getSameAsFamilyValue();
				} else if($eProduct['pro'] === FALSE) {
					$h .= \Asset::icon('x');
				} else {

					$taxes = $eStructure->conf()['hasVat'] ? ' <span class="util-annotation">'.self::getTaxes('pro').'</span>' : '';

					if($eProduct['proPrice']) {
						$value = \util\TextUi::money($eProduct['proPrice']).$taxes;
						if($eProduct['proPriceInitial'] !== NULL) {
							$h .= new PriceUi()->priceWithoutDiscount($eProduct['proPriceInitial'], unit: $taxes);
						}
					} else if($eProduct['privatePrice']) {
						$value = '<span class="color-muted" title="'.s("Prix calculé à partir du prix pour les particuliers diminué de la TVA.").'">'.\Asset::icon('magic').' ';
							$value .= \util\TextUi::money($eProduct->calcProMagicPrice($eStructure->hasVat())).$taxes;
						$value .= '</span>';
					} else {
						$value = \Asset::icon('question');
					}

					$h .= $quickPrice('proPrice', $value);
					$h .= self::getVolumes($eProduct->getEffectivePriceSteps('pro'), $eProduct['unit'], $eProduct['proPackaging'], stacked: TRUE);

				}

			$h .= '</td>';

			if($eStructure->conf()['hasVat']) {

				$h .= '<td class="text-center product-item-vat">';
					$h .= s("{value} %", ProductSetting::getVatRate($eStructure, $eProduct['vat']));
				$h .= '</td>';

			}

			switch($displayColumn) {

				case \structure\Member::TURNOVER :
					$h .= implode('', self::notify('getListTurnover', $eStructure, $eProduct));
					break;

				case \structure\Member::ACCOUNT :
					$h .= '<td class="text-end t-highlight hide-lg-down">';

						if($isVariant === FALSE) {

							if($eProduct['privateAccountNumber'] !== NULL) {
								$value = '<span title="'.encode($eProduct['privateAccount?']()->fetch()['description'] ?? '').'">';
									$value .= $eProduct['privateAccountNumber'];
								$value .= '</span>';
								$h .= $eProduct->quick('privateAccountNumber', $value, validate: ['canUpdateAccounting']);
							} else if($eProduct['profileAccountNumber']['privateAccountNumber'] !== NULL) {
								$h .= $eProduct->quick('privateAccountNumber', $this->getAccountNumberInherited($eProduct['profileAccountNumber']['privateAccountNumber']), validate: ['canUpdateAccounting']);
							} else {
								$h .= \Asset::icon('x');
							}

						}

					$h .= '</td>';
					$h .= '<td class="text-end t-highlight hide-lg-down td-min-content">';

						if($isVariant === FALSE) {

							// Dans l'ordre où l'export cherche le compte d'une vente pro (selling\Item::getAccountNumber())
							if($eProduct['proAccountNumber'] !== NULL) {
								$description = $eProduct['proAccount?']()->fetch($eStructure)['description'] ?? '';
								$value = encode($eProduct['proAccountNumber']);
							} else if($eProduct['profileAccountNumber']['proAccountNumber'] !== NULL) {
								$description = NULL;
								$value = $this->getAccountNumberInherited($eProduct['profileAccountNumber']['proAccountNumber']);
							} else if($eProduct['privateAccountNumber'] !== NULL) {
								$description = $eProduct['privateAccount?']()->fetch($eStructure)['description'] ?? '';
								$value = $this->getAccountNumberPrivateForPro($eProduct['privateAccountNumber']);
							} else if($eProduct['profileAccountNumber']['privateAccountNumber'] !== NULL) {
								$description = NULL;
								$value = $this->getAccountNumberPrivateForPro($eProduct['profileAccountNumber']['privateAccountNumber']);
							} else {
								$description = NULL;
								$value = NULL;
							}

							if($description !== NULL) {
								$value = '<span title="'.encode($description).'">'.$value.'</span>';
							}

							if($value !== NULL) {
								$h .= $eProduct->quick('proAccountNumber', $value, validate: ['canUpdateAccounting']);
							} else {
								$h .= \Asset::icon('x');
							}

						}

					$h .= '</td>';
					break;

			}

			$h .= '<td class="product-item-status td-min-content">';
				$h .= $this->toggle($eProduct);
			$h .= '</td>';

			$h .= '<td class="product-item-actions">';
				$h .= $this->getUpdate($eProduct, 'btn-color-primary');
			$h .= '</td>';

		$h .= '</tr>';

		return $h;

	}

	public function getCategories(\Collection $cCategory, array $products, \Search $search): string {

		if($cCategory->empty()) {
			return '';
		}

		$eCategorySelected = $search->get('category');

		// Chaque catégorie a sa propre pagination, et la page en cours peut ne pas exister dans une autre
		$request = \util\HttpUi::removeArgument(LIME_REQUEST, 'page');

		$tabs = '';

		foreach($cCategory as $eCategory) {

			$url = \util\HttpUi::setArgument($request, 'category', $eCategory['id'], FALSE);

			$tabs .= '<a href="'.$url.'" class="tab-item '.(($eCategorySelected->notEmpty() and $eCategorySelected['id'] === $eCategory['id']) ? 'selected' : '').'"><span class="tab-item-vignette">'.CategoryUi::getVignette($eCategory, '2rem').'</span><span class="tab-item-name">'.encode($eCategory['name']).'</span> <small class="util-counter util-counter-solid">'.($products[$eCategory['id']] ?? 0).'</small></a>';

		}

		$uncategorized = ($products[NULL] ?? 0);

		if($uncategorized > 0) {

			$url = \util\HttpUi::setArgument($request, 'category', '', FALSE);

			$tabs .= '<a href="'.$url.'" class="tab-item '.($eCategorySelected->empty() ? 'selected' : '').'"><span class="tab-item-vignette"></span><span class="tab-item-name">'.s("Non catégorisé").'</span> <small class="util-counter util-counter-solid">'.$uncategorized.'</small></a>';

		}

		return \main\DesignUi::getPageTabs($tabs, fold: TRUE);

	}

	public function getBatch(\structure\Structure $eStructure, \Collection $cCategory): string {

		$menu = '';

		if($cCategory->count() > 0) {

			$menu .= '<a data-dropdown="top-start" class="batch-category batch-item">';
				$menu .= \Asset::icon('tag');
				$menu .= '<span>'.s("Catégorie").'</span>';
			$menu .= '</a>';

			$menu .= '<div class="dropdown-list bg-secondary">';
				$menu .= '<div class="dropdown-title">'.s("Changer de catégorie").'</div>';
				foreach($cCategory as $eCategory) {
					$menu .= '<a data-ajax-submit="/product/product:doUpdateCategoryCollection" data-ajax-target="#batch-product-form" post-category="'.$eCategory['id'].'" class="dropdown-item">'.encode($eCategory['name']).'</a>';
				}
				$menu .= '<a data-ajax-submit="/product/product:doUpdateCategoryCollection" data-ajax-target="#batch-product-form" post-category="" class="dropdown-item"><i>'.s("Non catégorisé").'</i></a>';
			$menu .= '</div>';

		}

		if(
			$eStructure->hasAccounting() and
			$eStructure->canManageAccounting() and
			$eStructure->isCashReceipts(\Clock::date()) === FALSE
		) {
			$menu .= '<a data-ajax-submit="/product/product:updateAccountNumber" data-ajax-method="get" class="batch-item">'.\Asset::icon('journal-text').'<span>'.s("Numéro de compte").'</span></a>';
		}

		$menu .= '<a data-ajax-submit="/product/product:doUpdateStatusCollection" post-status="'.Product::ACTIVE.'" data-confirm="'.s("Activer ces produits ?").'" class="batch-item">';
			$menu .= \Asset::icon('toggle-on');
			$menu .= '<span>'.s("Activer").'</span>';
		$menu .= '</a>';

		$menu .= '<a data-ajax-submit="/product/product:doUpdateStatusCollection" post-status="'.Product::INACTIVE.'" data-confirm="'.s("Désactiver ces produits ?").'" class="batch-item">';
			$menu .= \Asset::icon('toggle-off');
			$menu .= '<span>'.s("Désactiver").'</span>';
		$menu .= '</a>';

		return \util\BatchUi::group('batch-product', $menu, title: s("Pour les produits sélectionnés"));

	}

	public static function getInfos(Product $eProduct, bool $includeQuality = TRUE, bool $includeUnit = FALSE, bool $includeDistribution = TRUE, bool $link = TRUE, bool $withPrincipal = FALSE): string {

		\Asset::css('product', 'product.css');

		$h = '<div class="product-item-label">';

			$h .= '<div>';

				if($link and $eProduct->canWrite()) {
					$h .= '<a href="'.self::url($eProduct).'" class="product-item-label-name">'.$eProduct->getName('html', $withPrincipal).'</a>';
				} else {
					$h .= '<span class="product-item-label-name">'.$eProduct->getName('html', $withPrincipal).'</span>';
				}

				if(
					$includeDistribution and
					$eProduct['distribution'] === Product::RESALE
				) {

					$h .= '<span class="product-item-label-distribution badge badge-outline">';
						$h .= self::p('distribution')->shortValues[$eProduct['distribution']];
					$h .= '</span>';

				}

				if($includeUnit) {

					if($eProduct['unit']->notEmpty()) {
						$h .= '<span class="product-item-label-unit">'.UnitUi::getSingular($eProduct['unit']).'</span>';
					}

				}

			$h .= '</div>';

			if($includeQuality) {
				$h .= ProductUi::getMarksLogos($eProduct, '1.5rem');
			}

		$h .= '</div>';

		$more = self::getDetails($eProduct);

		if($eProduct['discountable'] === FALSE) {
			$more[] = '<span>'.s("Pas de remise").'</span>';
		}

		if($more) {
			$h .= '<div class="product-item-infos">'.implode(' | ', $more).'</div>';
		}

		return $h;


	}

	public static function getDetails(Product $eProduct, array $properties = ['reference', 'additional', 'origin']): array {

		$more = [];

		if(in_array('reference', $properties) and $eProduct['reference']) {
			$more[] = '<span>'.$eProduct['reference'].'</span>';
		}

		if(in_array('additional', $properties) and $eProduct['additional']) {
			$more[] = '<span>'.encode($eProduct['additional']).'</span>';
		}

		if(in_array('origin', $properties) and $eProduct['origin']) {
			$more[] = '<span>'.s("Origine {value}", '<u>'.encode($eProduct['origin']).'</u>').'</span>';
		}

		return $more;

	}

	public static function getVignette(Product $eProduct, string $size, bool $public = FALSE, bool $withComplement = FALSE): string {

		$eProduct->expects(['id', 'vignette', 'profile']);

		$ui = new \media\ProductVignetteUi();

		$class = 'media-circle-view ';
		$style = '';

		if($eProduct['vignette'] === NULL) {

			$class .= ' media-vignette-default';
			$content = match($eProduct['profile']) {
				Product::SHIPPING => \Asset::icon('truck'),
				default => \Asset::icon('box')
			};

		} else {

			$format = $ui->convertToFormat($size);

			$style .= 'background-image: url('.$ui->getUrlByElement($eProduct, $format).');';
			$content = '';

		}

		if($public === FALSE and $withComplement) {
			$content .= self::getVignetteComplement($eProduct);
		}

		return '<div class="'.$class.'" style="'.$ui->getSquareCss($size).'; '.$style.'">'.$content.'</div>';

	}

	public static function getVignetteComplement(Product $eProduct): string {

		if(
			$eProduct['plant']->notEmpty() and
			($eProduct['plant']['fqn'] !== NULL or $eProduct['plant']['vignette'] !== NULL)
		) {
			return self::getVignettePlant($eProduct);
		} else {
			return self::getVignetteProfile($eProduct);
		}

	}

	public static function getVignettePlant(Product $eProduct): string {

		return '<div class="product-vignette-plant" style="border-color: var(--plant);">'.\plant\PlantUi::getVignette($eProduct['plant'], '1.25rem').'</div>';

	}

	public static function getVignetteProfile(Product $eProduct): string {

		\Asset::css('product', 'product.css');

		return '<div class="product-vignette-profile" style="border-color: var(--'.$eProduct['profile'].'); color: var(--'.$eProduct['profile'].');" title="'.self::p('profile')->values[$eProduct['profile']].'">'.self::p('profile')->icons[$eProduct['profile']].'</div>';

	}

	public function toggle(Product $eProduct, ?string $place = NULL) {

		return \util\TextUi::switch([
			'id' => 'product-switch-'.($place !== NULL ? $place.'-' : '').$eProduct['id'],
			'data-ajax' => $eProduct->canWrite() ? '/product/product:doUpdateStatus' : NULL,
			'post-id' => $eProduct['id'],
			'post-status' => ($eProduct['status'] === Product::ACTIVE) ? Product::INACTIVE : Product::ACTIVE
		], $eProduct['status'] === Product::ACTIVE);

	}

	public function displayTitle(Product $eProduct): string {

		$title = $eProduct->getName('html');

		// L'unité est celle d'une ligne vendue : un contenant n'en a pas une qui
		// vaudrait pour sa famille, où le kilo et la pièce cohabitent. Même critère que la variété, portée par getName().
		if($eProduct['unit']->notEmpty() and $eProduct['variants'] === 0) {
			$title .= '<span class="product-display-unit">'.\product\UnitUi::getBy($eProduct['unit']).'</span>';
		}

		return \main\DesignUi::getPageHeader(
			$title,
			context: $eProduct->isManipulable() ? $this->toggle($eProduct) : '',
			logo: new \media\ProductVignetteUi()->getCamera($eProduct, size: '3rem'),
			tools: implode('', self::notifyDisplay('getTitleTools', $eProduct)),
			settings: $this->getUpdate($eProduct, 'btn-color-primary')
		);

	}

	public function display(Product $eProduct): string {

		if($eProduct->isManipulable() === FALSE) {
			return '';
		}

		if($eProduct['variants'] > 0) {
			return $this->displayPrincipal($eProduct);
		}

		$h = '<div class="util-block stick-xs">';
			$h .= '<dl class="util-presentation util-presentation-2">';

				if($eProduct['reference'] !== NULL) {
					$h .= '<dt>'.self::getPropertyLabel('reference', $eProduct).'</dt>';
					$h .= '<dd>'.$eProduct['reference'].'</dd>';
				}

				if($eProduct['distribution'] !== NULL) {
					$h .= '<dt>'.self::p('distribution')->label.'</dt>';
					$h .= '<dd>'.self::p('distribution')->shortValues[$eProduct['distribution']].'</dd>';
				}

				if($eProduct['origin'] !== NULL) {
					$h .= '<dt>'.self::p('origin')->label.'</dt>';
					$h .= '<dd>'.($eProduct['origin'] ? encode($eProduct['origin']) : '').'</dd>';
				}

				$h .= '<dt>'.s("Signe de qualité").'</dt>';
				$h .= '<dd>'.self::getMarksLogos($eProduct, '1.5rem').'</dd>';
				if($eProduct['category']->notEmpty()) {
					$h .= '<dt>'.self::p('category')->label.'</dt>';
					$h .= '<dd>'.encode($eProduct['category']['name']).'</dd>';
				}
				if($eProduct['structure']->conf()['hasVat']) {
					$h .= '<dt>'.self::p('vat')->label.'</dt>';
					$h .= '<dd>'.s("{value} %", ProductSetting::getVatRate($eProduct['structure'], $eProduct['vat'])).'</dd>';
				}

				if($eProduct['profile'] === Product::COMPOSITION) {
					$h .= '<dt>'.s("Composition").'</dt>';
					$h .= '<dd>'.($eProduct['compositionVisibility'] === Product::PRIVATE ? s("surprise") : s("visible")).'</dd>';
				}
				if($eProduct['additional'] !== NULL) {
					$h .= '<dt>'.s("Complément d'information").'</dt>';
					$h .= '<dd>'.encode($eProduct['additional']).'</dd>';
				}
				if($eProduct['plant']->notEmpty()) {
					$h .= '<dt>'.self::p('plant')->label.'</dt>';
					$h .= '<dd>'.\plant\PlantUi::link($eProduct['plant']).'</dd>';
				}
				if($eProduct['ingredients'] !== NULL) {
					$h .= '<dt>'.self::p('ingredients')->label.'</dt>';
					$h .= '<dd>'.nl2br(encode($eProduct['ingredients'])).'</dd>';
				}
				if($eProduct['packaging'] !== NULL) {
					$h .= '<dt>'.self::p('packaging')->label.'</dt>';
					$h .= '<dd>'.encode($eProduct['packaging']).'</dd>';
				}
				if($eProduct['allergens'] !== NULL) {
					$h .= '<dt>'.self::p('allergens')->label.'</dt>';
					$h .= '<dd>'.nl2br(encode($eProduct['allergens'])).'</dd>';
				}
			$h .= '</dl>';
		$h .= '</div>';

		if($eProduct['discountable'] === FALSE) {
			$h .= '<div class="util-info">'.s("Vous avez configuré ce produit pour ne pas appliquer les remises commerciales paramétrées pour vos clients et vos ventes.").'</div>';
		}

		return $h;

	}

	/**
	 * Le pavé de la fiche d'un principal, réduit à ce que la grille des variantes
	 * ne montre pas ; le reste vit sur les lignes de la grille.
	 */
	private function displayPrincipal(Product $eProduct): string {

		$h = '<div class="util-block stick-xs">';
			$h .= '<dl class="util-presentation util-presentation-2">';

				if($eProduct['plant']->notEmpty()) {
					$h .= '<dt>'.self::p('plant')->label.'</dt>';
					$h .= '<dd>'.\plant\PlantUi::link($eProduct['plant']).'</dd>';
				}

				$h .= '<dt>'.s("Signe de qualité").'</dt>';
				$h .= '<dd>'.self::getMarksLogos($eProduct, '1.5rem').'</dd>';

				if($eProduct['distribution'] !== NULL) {
					$h .= '<dt>'.self::p('distribution')->label.'</dt>';
					$h .= '<dd>'.self::p('distribution')->shortValues[$eProduct['distribution']].'</dd>';
				}

				if($eProduct['structure']->conf()['hasVat']) {
					$h .= '<dt>'.self::p('vat')->label.'</dt>';
					$h .= '<dd>'.s("{value} %", ProductSetting::getVatRate($eProduct['structure'], $eProduct['vat'])).'</dd>';
				}

				$h .= '<dt>'.s("Prix").'</dt>';
				$h .= '<dd>'.new VariantAiUi()->getPriceModeBanner($eProduct).'</dd>';

			$h .= '</dl>';
		$h .= '</div>';

		if($eProduct['discountable'] === FALSE) {
			$h .= '<div class="util-info">'.s("Vous avez configuré ce produit pour ne pas appliquer les remises commerciales paramétrées pour vos clients et vos ventes.").'</div>';
		}

		return $h;

	}

	public function getAnalyze(Product $eProduct): string {
		return implode('', self::notifyDisplay('getAnalyze', $eProduct));
	}

	public function getTabs(Product $eProduct): string {

		$isPrincipal = ($eProduct['variants'] > 0);

		// En prix par variante, le prix vit sur les lignes : l'onglet Variantes le porte et
		// remplace Prix. En prix commun, le prix vit sur le principal et Prix revient à côté
		// de Variantes, prix de base et prix personnalisés ensemble.
		$isPriceShared = ($isPrincipal and $eProduct['priceMode'] === Product::SHARED);

		$defaultTab = $isPrincipal ? self::TAB_VARIANTS : self::TAB_GRID;

		// La composition d'un produit composé vient en tête et s'ouvre la première, ses ventes et
		// ses achats suivent les prix.
		$tabsBefore = array_merge([], ...self::notifyDisplay('getTabsBefore', $eProduct));
		$tabsAfter = array_merge([], ...self::notifyDisplay('getTabsAfter', $eProduct));

		$selectedOwn = ($tabsBefore === []);

		$grid = implode('', self::notifyDisplay('getGrid', $eProduct));

		$h = '<div class="tabs tabs-h" id="product-tabs" onrender="'.encode('Lime.Tab.restore(this, "'.$defaultTab.'", "'.GET('tab', [self::TAB_VARIANTS, self::TAB_GRID], '').'")').'">';

			$h .= '<div class="tabs-item">';
				foreach($tabsBefore as $tab) {
					$h .= '<a class="tab-item selected" data-tab="'.$tab['id'].'" onclick="Lime.Tab.select(this)">'.$tab['label'].'</a>';
				}
				if($eProduct->isManipulable()) {
					if($isPrincipal) {
						$h .= '<a class="tab-item '.($selectedOwn ? 'selected' : '').'" data-tab="'.self::TAB_VARIANTS.'" onclick="Lime.Tab.select(this)">'.s("Variantes").'<span class="util-counter util-counter-solid">'.$eProduct['cProductVariant']->count().'</span></a>';
						if($isPriceShared) {
							$h .= '<a class="tab-item" data-tab="'.self::TAB_GRID.'" onclick="Lime.Tab.select(this)">'.s("Prix").'</a>';
						}
					} else {
						$h .= '<a class="tab-item '.($selectedOwn ? 'selected' : '').'" data-tab="'.self::TAB_GRID.'" onclick="Lime.Tab.select(this)">'.s("Prix").'</a>';
					}
				}
				foreach($tabsAfter as $tab) {
					$h .= '<a class="tab-item" data-tab="'.$tab['id'].'" onclick="Lime.Tab.select(this)">'.$tab['label'].'</a>';
				}
			$h .= '</div>';

			foreach($tabsBefore as $tab) {
				$h .= '<div class="tab-panel selected" data-tab="'.$tab['id'].'">'.$tab['panel'].'</div>';
			}

			if($eProduct->isManipulable()) {

				if($isPrincipal) {

					$h .= '<div class="tab-panel '.($selectedOwn ? 'selected' : '').'" data-tab="'.self::TAB_VARIANTS.'">';
						$h .= new VariantAiUi()->getSection($eProduct, $eProduct['cProductVariant']);
						if($isPriceShared === FALSE and $grid !== '') {
							$h .= '<br/>';
							$h .= $grid;
						}
					$h .= '</div>';

					if($isPriceShared) {

						$h .= '<div class="tab-panel" data-tab="'.self::TAB_GRID.'">';
							$h .= $this->getBaseGrid($eProduct);
							if($grid !== '') {
								$h .= '<br/>';
								$h .= $grid;
							}
						$h .= '</div>';

					}

				} else {

					$h .= '<div class="tab-panel '.($selectedOwn ? 'selected' : '').'" data-tab="'.self::TAB_GRID.'">';
						$h .= $this->getBaseGrid($eProduct);
						$h .= $grid;
					$h .= '</div>';

				}

			}

			foreach($tabsAfter as $tab) {
				$h .= '<div class="tab-panel" data-tab="'.$tab['id'].'">'.$tab['panel'].'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que la commercialisation ajoute à la fiche d'un produit, rendu par selling\ProductObserverUi
	 * sur ce que selling\ProductObserverLib a posé (ProductLib::loadDisplay()). La version publique
	 * n'a ni ventes, ni prix personnalisés, ni compositions vendues : la fiche s'y réduit au produit.
	 */
	private static function notifyDisplay(string $event, Product $eProduct): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::notify($event, $eProduct);

	}

	public function getBaseGrid(Product $eProduct): string {

		// Prix de famille : sur une variante en prix commun, le prix vit sur le principal.
		// La valeur reste lue ici, mais sans le geste qui l'éditerait — ProductLib::update()
		// écarte ces colonnes, le quick s'y refermerait sans rien dire.
		$isPriceShared = (
			$eProduct['variantOf']->notEmpty() and
			$eProduct['variantOf']['priceMode'] === Product::SHARED
		);

		$quick = fn(string $property, string $value) => $isPriceShared ? $value : $eProduct->quick($property, $value);

		$h = '<div class="product-item-presentation-wrapper">';

			$h .= '<div class="product-item-presentation">';

				$h .= '<h3>'.s("Vente aux particuliers").'</h3>';

				$h .= '<div class="util-block">';

				$h .= '<dl class="util-presentation util-presentation-1">';

				if($eProduct['private']) {

					$taxes = $eProduct['structure']->conf()['hasVat'] ? self::getTaxes('private') : '';

					$h .= '<dt>'.s("Prix de base").'</dt>';
					$h .= '<dd>';
						if($eProduct['privatePrice']) {
							if($eProduct['privatePriceInitial']) {
								$value = \util\TextUi::money($eProduct['privatePriceInitial']);
							} else {
								$value = \util\TextUi::money($eProduct['privatePrice']);
							}
							$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
						} else if($eProduct['proPrice']) {
							$value = '<span class="color-muted" title="'.s("Prix calculé à partir du prix pour les professionnels augmenté de la TVA, cliquez pour le personnaliser.").'">'.\Asset::icon('magic').' ';
								$value .= \util\TextUi::money($eProduct->calcPrivateMagicPrice($eProduct['structure']->hasVat()));
								$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
							$value .= '</span>';
						} else {
							$value = '/';
						}
						if($eProduct['privatePrice'] and $eProduct['privatePriceInitial']) {
							$h .= new PriceUi()->priceWithoutDiscount($quick('privatePrice', $value), isSmall: FALSE);
						} else {
							$h .= $quick('privatePrice', $value);
						}
					$h .= '</dd>';

					if($eProduct['privatePriceInitial']) {

						$h .= '<dt>'.s("Prix remisé").'</dt>';

						$h .= '<dd>';
							$value = \util\TextUi::money($eProduct['privatePrice']);
							$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
							$h .= $quick('privatePrice', $value);
						$h .= '</dd>';

					}

					$h .= self::getPriceStepsPresentation($eProduct, 'private', $taxes);

					$h .= '<dt>'.self::p('privateStep')->label.'</dt>';
					$h .= '<dd>';
						$value = \product\UnitUi::getValue($eProduct['privateStep'] ?? self::getDefaultPrivateStep($eProduct), $eProduct['unit']);
						$h .= $quick('privateStep', $value);
					$h .= '</dd>';

				} else {
					$h .= '<div class="color-muted">'.s("Pas de vente aux particuliers").'</div>';
				}

				$h .= '</dl>';

				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="product-item-presentation">';

				$h .= '<h3>'.s("Vente aux professionnels").'</h3>';

				$h .= '<div class="util-block">';

				$h .= '<dl class="util-presentation util-presentation-1">';

				if($eProduct['pro']) {

					$taxes = $eProduct['structure']->conf()['hasVat'] ? self::getTaxes('pro') : '';

					$h .= '<dt>'.s("Prix de base").'</dt>';
					$h .= '<dd>';
						if($eProduct['proPrice']) {
							if($eProduct['proPriceInitial']) {
								$value = \util\TextUi::money($eProduct['proPriceInitial']);
							} else {
								$value = \util\TextUi::money($eProduct['proPrice']);
							}
							$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
						} else if($eProduct['privatePrice']) {
							$value = '<span class="color-muted" title="'.s("Prix calculé à partir du prix pour les particuliers diminué de la TVA, cliquez pour le personnaliser.").'">'.\Asset::icon('magic').' ';
								$value .= \util\TextUi::money($eProduct->calcProMagicPrice($eProduct['structure']->hasVat()));
								$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
							$value .= '</span>';
						} else {
							$value = '/';
						}
						if($eProduct['proPrice'] and $eProduct['proPriceInitial']) {
							$h .= new PriceUi()->priceWithoutDiscount($quick('proPrice', $value), isSmall: FALSE);
						} else {
							$h .= $quick('proPrice', $value);
						}
					$h .= '</dd>';


					if($eProduct['proPriceInitial']) {

						$h .= '<dt>'.s("Prix remisé").'</dt>';

						$h .= '<dd>';
							$value = \util\TextUi::money($eProduct['proPrice']);
							$value .= ' '.$taxes.\product\UnitUi::getBy($eProduct['unit']);
							$h .= $quick('proPrice', $value);
						$h .= '</dd>';

					}

					$h .= self::getPriceStepsPresentation($eProduct, 'pro', $taxes);

					if($eProduct['proPackaging']) {
						$h .= '<dt>'.self::p('proPackaging')->label.'</dt>';
						$h .= '<dd>';
							$value = \product\UnitUi::getValue($eProduct['proPackaging'], $eProduct['unit']);
							$h .= $quick('proPackaging', $value);
						$h .= '</dd>';
					}

					$h .= '<dt>'.self::p('proStep')->label.'</dt>';
					$h .= '<dd>';
						$value = \product\UnitUi::getValue($eProduct['proStep'] ?? self::getDefaultProStep($eProduct), $eProduct['unit']);
						$h .= $quick('proStep', $value);
					$h .= '</dd>';

				} else {

					$h .= '<div class="color-muted">'.s("Pas de vente aux professionnels").'</div>';

				}

				$h .= '</dl>';

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getUpdate(Product $eProduct, string $btn): string {

		if($eProduct->canWrite() === FALSE) {
			return '';
		}

		$isVariant = $eProduct['variantOf']->notEmpty();

		$h = '<a data-dropdown="bottom-end" class="dropdown-toggle btn '.$btn.'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eProduct->getName()).'</div>';

			$h .= '<a href="/product/product:update?id='.$eProduct['id'].'" class="dropdown-item">'.($isVariant ? s("Modifier la variante") : s("Modifier le produit")).'</a>';

			$h .= implode('', self::notifyDisplay('getSettingsEntries', $eProduct));
			$h .= '<div class="dropdown-divider"></div>';
			if(
				$isVariant === FALSE and
				$eProduct['variants'] === 0 and
				$eProduct->acceptEnableVariants() and
				$eProduct['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS)
			) {
				$h .= '<a href="'.VariantAiUi::urlCreate($eProduct).'" class="dropdown-item">'.s("Ajouter une variante").'</a>';
			}
			if($eProduct->acceptDisableVariants()) {
				$h .= '<a data-ajax="/product/product:doDisableVariants" post-id="'.$eProduct['id'].'" class="dropdown-item" data-confirm="'.s("La variante restante redeviendra un produit sans variante. Continuer ?").'">'.s("Désactiver les variantes").'</a>';
			}
			if(
				$eProduct->acceptDuplicate() and
				($isVariant === FALSE or $eProduct['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS))
			) {
				$h .= '<a href="/product/product:create?structure='.$eProduct['structure']['id'].'&from='.$eProduct['id'].'" class="dropdown-item">'.($isVariant ? s("Dupliquer la variante") : s("Dupliquer le produit")).'</a>';
			}
			if($eProduct->assertDelete()) {
				$h .= '<a data-ajax="/product/product:doDelete" post-id="'.$eProduct['id'].'" class="dropdown-item" data-confirm="'.($isVariant ? s("Confirmer la suppression de la variante ?") : s("Confirmer la suppression du produit ?")).'">'.($isVariant ? s("Supprimer la variante") : s("Supprimer le produit")).'</a>';
			}
			if(
				$eProduct->acceptEnableStock() or
				$eProduct->acceptDisableStock()
			) {
				$h .= '<div class="dropdown-divider"></div>';
				if($eProduct->acceptEnableStock()) {
					$h .= '<a data-ajax="/product/stock:doEnableStock" post-id="'.$eProduct['id'].'" class="dropdown-item">'.\Asset::icon('box').'  '.s("Activer le suivi du stock").'</a>';
				}
				if($eProduct->acceptDisableStock()) {
					$h .= '<a data-ajax="/product/stock:doDisableStock" post-id="'.$eProduct['id'].'" class="dropdown-item" data-confirm="'.encode(s("Confirmer la désactivation du suivi de stock pour ce produit ?")).'">'.\Asset::icon('box').'  '.s("Désactiver le suivi du stock").'</a>';
				}
			}
		$h .= '</div>';

		return $h;

	}

	public function create(Product $eProduct, ?string $urlCreate = NULL, string $routeCreate = '/product/product:doCreate', array $profilesExcluded = [], ?string $autocomplete = NULL): \Panel {

		$eProduct->expects(['cCategory', 'cUnit', 'duplicateOf', 'variantOf', 'cProductVariant']);

		$eStructure = $eProduct['structure'];

		$form = new \util\FormUi();

		if($eProduct['profile'] === NULL) {

			if($autocomplete !== NULL) {
				$urlCreate ??= self::urlCreateAutocomplete($eStructure, $eProduct['name'] ?? '', $autocomplete, in_array(Product::COMPOSITION, $profilesExcluded) === FALSE);
			}

			return $this->createProfile($eProduct, $urlCreate, $profilesExcluded);

		}

		$isVariant = $eProduct['variantOf']->notEmpty();

		$h = $form->asteriskInfo();

		$h .= $form->hidden('structure', $eStructure['id']);
		$h .= $form->hidden('duplicateOf', $eProduct['duplicateOf']);

		if($autocomplete !== NULL) {
			$h .= $form->hidden('autocomplete', $autocomplete);
		}

		$isFirstVariant = ($isVariant and $eProduct['variantOf']['variants'] === 0);

		if($isVariant) {

			$h .= $form->hidden('variantOf', $eProduct['variantOf']);

			// doCreateFirstVariant() lit le produit en `id`; doCreate() ne construit jamais `id`,
			// l'envoi reste le même dans les deux cas.
			if($isFirstVariant) {

				$h .= $form->hidden('id', $eProduct['variantOf']);

				if($eProduct['isFreeTrial']) {
					$h .= \main\DesignUi::getWelcome('', '<p>'.s("Cette fonctionnalité est proposée gratuitement en essai jusqu'à fin octobre.").'</p>', '');
				}

			}

		} else if($eProduct->exists()) {

			$h .= '<div class="util-block-info">';
				$h .= '<h3>'.s("Dupliquer un produit").'</h3>';
				$h .= '<p>'.s("Vous pouvez maintenant paramétrer le nouveau produit que vous vous apprêtez à créer sur la base de <u>{product}</u>.", ['product' => encode($eProduct['name'])]).'</p>';
			$h .= '</div>';

		}


		if($isVariant) {
			$h .= $form->hidden('profile', $eProduct['profile']);
			// Le radio caché et coché reste nécessaire : l'affichage des sections par
			// profil est du CSS sur :has(input[name=profile]:checked)
			$h .= $form->inputRadio('profile', $eProduct['profile'], attributes: ['checked' => 'checked', 'class' => 'hide']);
		} else {
			$h .= $form->dynamicGroup($eProduct, 'profile', function(\PropertyDescriber $d) use($profilesExcluded) {
				$d->values = array_diff_key($d->values, array_flip($profilesExcluded));
			});
		}

		if($isVariant === FALSE) {
			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('distribution')).'">';
				$h .= $form->dynamicGroup($eProduct, 'distribution');
			$h .= '</div>';
		}

		if($isFirstVariant) {
			$h .= VariantAiUi::getFirstVariantOpen($form, $eProduct['variantOf']);
		}

		$h .= $form->dynamicGroups($eProduct, ['name*', 'reference'], [
			'reference' => VariantAiUi::relabelField($eProduct, 'reference')
		] + ($isFirstVariant ? ['name*' => VariantAiUi::renameField('new', s("Nom de la variante"))] : []));

		if(
			$isVariant === FALSE and
			$eProduct['cCategory']->notEmpty()
		) {
			$h .= $form->dynamicGroup($eProduct, 'category');
		}

		$h .= $form->dynamicGroup($eProduct, 'unit', VariantAiUi::relabelField($eProduct, 'unit', function(\PropertyDescriber $d) {
			$d->attributes += [
				'onchange' => 'Product.changeUnit(this, "product-unit")'
			];
		}));

		if($isVariant === FALSE) {
			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('origin')).'">';
				$h .= $form->dynamicGroup($eProduct, 'origin');
			$h .= '</div>';
		}

		if($isVariant === FALSE) {

			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('marks')).'">';
				$h .= new \structure\StructureUi()->getMarksFields($form, $eProduct);
			$h .= '</div>';

			$h .= $form->dynamicGroup($eProduct, 'vat');

		} else {
			$h .= $this->getFieldVatShared($form, $eProduct);
		}

		$h .= '<br/>';
		$h .= $this->getFieldDescription($form, $eProduct);

		$h .= '<br/>';
		$h .= $this->getFieldProfile($form, $eProduct, 'create');

		$h .= $this->getFieldPrices($form, $eProduct, 'create');

		if(
			$eProduct['duplicateOf']->notEmpty() and
			$isVariant === FALSE
		) {
			$h .= $this->getFieldAccounting($form, $eProduct);
		}

		if($eProduct['cProductVariant']->notEmpty()) {
			$h .= '<br/>';
			$h .= new VariantAiUi()->getDuplicateForm($form, $eProduct['cProductVariant']);
		}

		if($isFirstVariant) {
			$h .= VariantAiUi::getFirstVariantClose($form, $eProduct['variantOf']);
		}

		$footer = $form->group(
			content: $form->submit($isVariant ? s("Créer la variante") : s("Créer le produit"), $isFirstVariant ? [
				'data-label-create' => s("Créer la variante"),
				'data-label-attach' => s("Rattacher ce produit")
			] : [])
		);

		if($isVariant) {
			$subTitle = '<div class="panel-header-subtitle">'.s("Variante de {value}", self::getVignette($eProduct['variantOf'], '1.4rem').' '.encode($eProduct['variantOf']['name'])).'</div>';
		} else if($eProduct['cProductVariant']->notEmpty()) {
			$subTitle = '<div class="panel-header-subtitle">'.p("{value} variante", "{value} variantes", $eProduct['cProductVariant']->count()).'</div>';
		} else {
			$subTitle = NULL;
		}

		$attributes = ['id' => 'product-create', 'class' => 'panel-dialog product-write'];

		if($isVariant) {
			$title = $eProduct->exists() ? s("Dupliquer une variante") : s("Ajouter une variante");
		} else {
			$title = $eProduct->exists() ? s("Dupliquer un produit") : s("Ajouter un produit");
		}

		$route = $isFirstVariant ? '/product/product:doCreateFirstVariant' : $routeCreate;

		return new \Panel(
			id: 'panel-product-create',
			title: $title,
			subTitle: $subTitle,
			dialogOpen: $form->openAjax($route, $attributes),
			dialogClose: $form->close(),
			body: $h,
			footer: $footer
		);

	}

	public function createProfile(Product $eProduct, ?string $urlCreate = NULL, array $profilesExcluded = []): \Panel {

		$urlCreate ??= '/product/product:create?structure='.$eProduct['structure']['id'];

		$h = '<div class="util-buttons util-buttons-dark">';

			foreach(self::p('profile')->values as $profile => $value) {

				if(in_array($profile, $profilesExcluded)) {
					continue;
				}

				$examples = self::p('profile')->examples[$profile];

				$h .= '<a href="'.$urlCreate.(str_contains($urlCreate, '?') ? '&' : '?').'profile='.$profile.'" class="util-button" style="border-color: var(--'.$profile.')">';

					$h .= '<div>';
						$h .= '<h4>'.$value.'</h4>';
						if($examples) {
							$h .= '<div class="util-button-text">'.$examples.'</div>';
						}
					$h .= '</div>';
					$h .= self::getProfileIcon($profile, '4rem');

				$h .= '</a>';

			}

		$h .= '</div>';

		return new \Panel(
			id: 'panel-product-create',
			title: s("Ajouter un produit"),
			body: $h
		);

	}

	public function update(Product $eProduct): \Panel {

		$form = new \util\FormUi();

		$isVariant = $eProduct['variantOf']->notEmpty();

		$h = '';

		$h .= $form->hidden('id', $eProduct['id']);

		if($isVariant) {
			// Le radio caché et coché reste nécessaire : l'affichage des sections par
			// profil est du CSS sur :has(input[name=profile]:checked)
			$h .= $form->inputRadio('profile', $eProduct['profile'], attributes: ['checked' => 'checked', 'class' => 'hide']);
		} else {
			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('profile')).'">';
				$h .= $form->dynamicGroup($eProduct, 'profile');
			$h .= '</div>';
		}

		if($isVariant === FALSE) {
			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('distribution')).'">';
				$h .= $form->dynamicGroup($eProduct, 'distribution');
			$h .= '</div>';
		}

		$h .= $form->dynamicGroup($eProduct, 'name');
		$h .= $form->dynamicGroup($eProduct, 'reference', VariantAiUi::relabelField($eProduct, 'reference'));

		if(
			$isVariant === FALSE and
			$eProduct['cCategory']->notEmpty()
		) {
			$h .= $form->dynamicGroup($eProduct, 'category');
		}

		$h .= $form->group(
			self::p('unit')->label,
			$form->dynamicField($eProduct, 'unit')
		);

		if($isVariant === FALSE) {
			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('origin')).'">';
				$h .= $form->dynamicGroup($eProduct, 'origin');
			$h .= '</div>';
		}

		if($isVariant === FALSE) {

			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('marks')).'">';
				$h .= new \structure\StructureUi()->getMarksFields($form, $eProduct);
			$h .= '</div>';

			$h .= $form->dynamicGroup($eProduct, 'vat');

		} else {
			$h .= $this->getFieldVatShared($form, $eProduct);
		}

		$h .= '<br/>';
		$h .= $this->getFieldDescription($form, $eProduct);

		$h .= '<br/>';
		$h .= $this->getFieldProfile($form, $eProduct, 'update');

		$h .= $this->getFieldPrices($form, $eProduct, 'update');

		if($isVariant === FALSE) {
			$h .= $this->getFieldAccounting($form, $eProduct);
		}

		$h .= $form->group(
			content: $form->submit(s("Enregistrer"))
		);

		if($isVariant) {
			$subTitle = '<div class="panel-header-subtitle">'.s("Variante de {value}", self::getVignette($eProduct['variantOf'], '1.4rem').' '.encode($eProduct['variantOf']['name'])).'</div>';
		} else {
			$subTitle = NULL;
		}

		return new \Panel(
			id: 'panel-product-update',
			title: $isVariant ? s("Modifier une variante") : s("Modifier un produit"),
			subTitle: $subTitle,
			dialogOpen: $form->openAjax('/product/product:doUpdate', ['id' => 'product-update', 'class' => 'panel-dialog product-write']),
			dialogClose: $form->close(),
			body: $h,
		);

	}

	public static function getFieldPriceSteps(\util\FormUi $form, Product $eProduct, string $for): string {

		$unit = $eProduct['unit']->notEmpty() ? encode($eProduct['unit']['singular']) : self::p('unit')->placeholder;
		$taxes = $eProduct['structure']->conf()['hasVat'] ? self::getTaxes($for) : '';

		return self::getPriceStepsTable(
			$form,
			$for.'PriceSteps',
			$eProduct[$for.'PriceSteps'],
			$for.'Price',
			$for.'PriceDiscount',
			($for === 'pro' and $eProduct['proPackaging'] !== NULL) ? s("colis") : $unit,
			'€ '.$taxes.' / '.$unit
		);

	}

	/**
	 * La table et l'état vide sont tous deux rendus : c'est `priceStepAi.js:refreshContainer()` qui
	 * montre l'un ou l'autre selon le nombre de lignes, au rendu comme après chaque ajout.
	 */
	public static function getPriceStepsTable(\util\FormUi $form, string $property, array $steps, string $priceField, string $priceFieldDiscount, string $threshold, string $suffix): string {

		\Asset::js('product', 'priceStepAi.js');

		$add = fn(string $ref, string $class) => '<a class="btn '.$class.'" data-ref="'.$ref.'" onclick="ProductPriceStepAi.add(this)">'.\Asset::icon('plus-circle').' '.s("Ajouter un palier").'</a>';


		// `onrender` et non `navigation.wakeup` : les deux panneaux s'ouvrent en couche ajax, où
		// l'événement de navigation ne repasse pas.
		$h = '<div class="product-price-steps" data-price-field="'.$priceField.'" data-price-field-discount="'.$priceFieldDiscount.'" data-max="'.ProductSetting::PRICE_STEPS_MAX.'" '.attr('onrender', 'ProductPriceStepAi.refreshContainer(this)').'>';

			$h .= '<div class="util-block" data-ref="table">';

				$h .= '<table class="table-unstyled product-price-steps-table">';

					$h .= '<thead><tr>';
						$h .= '<th>'.s("À partir de").'</th>';
						$h .= '<th>'.s("Prix unitaire").'</th>';
						$h .= '<th class="product-price-steps-gap">'.s("Écart").'</th>';
						$h .= '<th></th>';
					$h .= '</tr></thead>';

					$h .= '<tbody data-ref="rows">';

						foreach($steps as $index => $step) {
							$h .= self::getPriceStepRow($form, $property, $index, $threshold, $suffix, $step);
						}

					$h .= '</tbody>';

				$h .= '</table>';

				$h .= $add('add', 'btn-sm btn-outline-primary');

			$h .= '</div>';

			// La règle du calcul ne paraît qu'ici : au-dessus d'une table peuplée, la colonne « Écart »
			// la montre mieux qu'une phrase ne la dit.
			$h .= '<div class="util-block-empty" data-ref="empty">';
				$h .= '<h3>'.s("Aucune remise sur volume").'</h3>';
				$h .= '<p>'.s("Le prix de chaque palier s'applique à toute la quantité commandée.").'</p>';
				$h .= '<div class="util-buttons">';
					$h .= $add('addEmpty', 'btn-outline-brand');
				$h .= '</div>';
			$h .= '</div>';

			// Le patron sert au javascript à ajouter une ligne : il porte l'index `{index}`, que
			// l'ajout remplace par un compteur. `ProductLib::checkPriceSteps()` retrie et resserre,
			// les trous d'index n'ont donc rien à dire.
			$h .= '<template data-ref="template">';
				$h .= self::getPriceStepRow($form, $property, '{index}', $threshold, $suffix, NULL);
			$h .= '</template>';

		$h .= '</div>';

		return $h;

	}

	public static function getPriceStepsPresentation(Product $eProduct, string $for, string $taxes): string {

		$steps = $eProduct->getEffectivePriceSteps($for);

		if($steps === []) {
			return '';
		}

		$threshold = ($for === 'pro' and $eProduct['proPackaging'] !== NULL) ?
			fn(float $from) => s("{value} colis", $from) :
			fn(float $from) => \product\UnitUi::getValue($from, $eProduct['unit'], TRUE);

		$h = '<dt>'.self::p($for.'PriceSteps')->label.'</dt>';
		$h .= '<dd>';

			foreach($steps as $step) {
				$h .= '<div>'.s("{price} à partir de {from}", [
					'price' => \util\TextUi::money($step['price']).' '.$taxes.\product\UnitUi::getBy($eProduct['unit']),
					'from' => $threshold($step['from'])
				]).'</div>';
			}

		$h .= '</dd>';

		return $h;

	}

	public static function getPriceStepsGroupAttributes(Product $eProduct, string $for): array {

		return [
			'data-wrapper' => $for.'PriceSteps',
			'data-price-steps' => 'field',
			'data-price-steps-reference' => self::getPriceStepsIdentifier($eProduct, $for),
			'class' => $eProduct[$for.'PriceSteps'] ? '' : 'hide'
		];

	}

	public static function getPriceDiscountGroup(\util\FormUi $form, Product $eProduct, string $for): string {

		return $form->group(
			self::p($for.'PriceDiscount')->label,
			$form->dynamicField($eProduct, $for.'PriceDiscount'),
			[
				'data-wrapper' => $for.'PriceDiscount',
				'data-price-discount' => 'field',
				'data-price-discount-reference' => self::getPriceDiscountIdentifier($eProduct, $for),
				'class' => ($eProduct[$for.'PriceInitial'] ?? NULL) !== NULL ? '' : 'hide'
			]
		);

	}

	/** Le bouton et le groupe qu'il commande se retrouvent par cet identifiant, et lui seul. */
	public static function getPriceDiscountIdentifier(Product $eProduct, string $for): string {
		return ($eProduct['id'] ?? '').'-'.$for;
	}

	/** Le bouton « Prix remisé » et le groupe qu'il commande se retrouvent par cet identifiant. */
	public static function getPriceStepsIdentifier(Product $eProduct, string $for): string {
		return ($eProduct['id'] ?? '').'-'.$for.'-steps';
	}

	/**
	 * Les bandeaux qu'un package fermé pose en tête de la liste des produits : l'écran décide de leur
	 * place, le package de leur contenu.
	 */
	public static function getIndexBanners(\structure\Structure $eStructure): string {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return '';
		}

		return implode('', self::notify('getIndexBanners', $eStructure));

	}

	public static function getVolumes(array $steps, Unit $eUnit, ?float $packaging = NULL, bool $stacked = FALSE, ?float $appliedPrice = NULL): string {

		if($steps === []) {
			return '';
		}

		$h = '<div class="badges '.($stacked ? 'badges-stacked' : '').'">';

			foreach($steps as $step) {

				$isApplied = ($appliedPrice !== NULL and (float)$step['price'] === $appliedPrice);

				// `merchant.js:markPriceSteps()` retrouve la pastille par `[data-price]` pour y basculer
				// `badge-off` à chaque saisie : retirer l'attribut éteint la bascule sans rien casser ici.
				$h .= '<span class="badge badge-info '.(($appliedPrice === NULL or $isApplied) ? '' : 'badge-off').'" data-price="'.$step['price'].'">';
					$h .= \Asset::icon('graph-down-arrow').' ';
					$h .= self::getVolumeText($step, $eUnit, $packaging);
				$h .= '</span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le texte d'une pastille de palier, sans html : l'écran de vente de l'appareil le garde hors ligne et le pose tel
	 * quel, sans savoir formater une unité.
	 */
	public static function getVolumeText(array $step, Unit $eUnit, ?float $packaging = NULL): string {

		$text = s("dès {value}", ($packaging === NULL) ?
			UnitUi::getValue($step['from'], $eUnit) :
			s("{value} colis", $step['from']));

		return $text.' '.\util\TextUi::money($step['price']).UnitUi::getBy($eUnit, short: TRUE);

	}

	public static function getDefaultPrivateStep(Product $eProduct): float {

		if($eProduct['unit']->empty()) {
			return 1;
		}

		return match($eProduct['unit']['fqn']) {

			'gram' => 100,
			'kg' => 0.5,
			default => 1,

		};

	}

	public static function getDefaultProStep(Product $eProduct): float {
		return 1;
	}

	public static function getShippingName(): string {
		return s("Frais de livraison");
	}

	// Le canal se nomme comme les colonnes du produit qu'il gouverne (`private`, `privatePrice`, `pro`, `proPrice`).
	public static function getTaxes(string $for): string {

		return match($for) {
			'private' => s("TTC"),
			'pro' => s("HT"),
		};

	}

	public static function getVat(\structure\Structure $eStructure, bool $short = FALSE): array {

		$eCountry = $eStructure['legalCountry'];

		if($eCountry['id'] === \user\UserSetting::FR) {

			return [
				// FR
				0 => $short ? s("0 %") : s("0 % - Pas de TVA"),
				1 => $short ? s("2.1 %") : s("2.1 % - Taux particulier"),
				2 => $short ? s("5.5 %") : s("5.5 % - Taux réduit"),
				3 => $short ? s("10 %") : s("10 % - Taux intermédiaire"),
				4 => $short ? s("20 %") : s("20 % - Taux normal"),
			];

		} else if($eCountry['id'] === \user\UserSetting::BE) {

			return [
				// BE
				100 => $short ? s("0 %") : s("0 % - Pas de TVA"),
				101 => $short ? s("6 %") : s("6 % - Taux réduit"),
				102 => $short ? s("12 %") : s("12 % - Taux intermédiaire"),
				103 => $short ? s("21 %") : s("21 % - Taux standard"),
			];

		} else {

			return [

				999 => $short ? s("0 %") : s("0 % - Pas de TVA"),

			];

		}

	}

	public static function getPriceStepsLink(string|int $identifier, bool $hasSteps): string {

		// Statique, donc hors du constructeur qui demande d'ordinaire la feuille : sans cet appel le
		// bouton sort non stylé sur les écrans qui n'ont pas chargé `product.css` par ailleurs.
		\Asset::css('product', 'product.css');

		$label = \Asset::icon('graph-down-arrow').' '.s("Remise sur volume");

		return '<a '.attrs([
			'class' => 'btn btn-xs btn-outline-primary product-price-steps-link',
			'onclick' => 'ProductPriceStepAi.switch("'.$identifier.'");',
			'data-visible' => $hasSteps ? '1' : '0',
			'data-price-steps-reference' => $identifier,
			'data-price-steps' => 'link'
		]).'>'.$label.'</a>';

	}

	private static function getPriceStepRow(\util\FormUi $form, string $property, string|int $index, string $threshold, string $suffix, ?array $step): string {

		$name = $property.'['.$index.']';

		$h = '<tr>';

			$h .= '<td>';
				$h .= $form->inputGroup(
					$form->number($name.'[from]', $step['from'] ?? NULL, ['step' => 0.01, 'min' => 0, 'oninput' => 'ProductPriceStepAi.refresh(this)']).
					$form->addon($threshold)
				);
			$h .= '</td>';

			$h .= '<td>';
				$h .= $form->inputGroup(
					$form->number($name.'[price]', $step['price'] ?? NULL, ['step' => 0.0001, 'min' => 0, 'oninput' => 'ProductPriceStepAi.refresh(this)']).
					$form->addon($suffix)
				);
			$h .= '</td>';

			$h .= '<td class="product-price-steps-gap" data-ref="gap"></td>';

			$h .= '<td>';
				$h .= '<a class="btn btn-only-icon btn-sm btn-outline-danger" onclick="ProductPriceStepAi.remove(this)" title="'.s("Supprimer ce palier").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</td>';

		$h .= '</tr>';

		return $h;

	}

	private function getFieldDescription(\util\FormUi $form, Product $eProduct): string {

		$h = '';
		$h .= '<h3>'.s("Description").'</h3>';
		$h .= '<div class="util-block">';
			$h .= $form->dynamicGroups($eProduct, ['additional', 'description'], [
				'description' => VariantAiUi::relabelField($eProduct, 'description')
			]);
		$h .= '</div>';

		return $h;

	}

	private function getFieldProfile(\util\FormUi $form, Product $eProduct, string $for): string {

		$h = '';

		$h .= '<div class="product-write-details" data-profile="'.implode(' ', Product::getProfiles('characteristics')).'">';

			$h .= '<h3>'.s("Caractéristiques").'</h3>';

			$h .= '<div class="util-block">';

				$h .= '<div data-profile="'.implode(' ', Product::getProfiles('compositionVisibility')).'">';
					$h .= $form->dynamicGroup($eProduct, 'compositionVisibility*', VariantAiUi::relabelField($eProduct, 'compositionVisibility'));
				$h .= '</div>';

				if($eProduct['variantOf']->empty()) {

					$h .= '<div data-profile="'.implode(' ', Product::getProfiles('plant')).'">';

						$h .= $form->group(
							self::p('plant')->label,
							$form->dynamicField($eProduct, 'plant', function($d) {
								$d->autocompleteDispatch = '#product-update';
							})
						);

					$h .= '</div>';

				}

				// Le blocage des commandes ne se règle que sur un produit qui existe : à la création,
				// le producteur nomme son produit, il ne pèse pas encore ses contraintes de
				// fabrication. Sur une variante, il vient du principal, comme l'espèce.
				$properties = ($for === 'create' or $eProduct['variantOf']->notEmpty()) ?
					['variety', 'packaging', 'ingredients', 'frozen', 'allergens'] :
					['variety', 'packaging', 'preparationDays', 'ingredients', 'frozen', 'allergens'];

				foreach($properties as $property) {

					$h .= '<div data-profile="'.implode(' ', Product::getProfiles($property)).'">';
						$h .= $form->dynamicGroup($eProduct, $property);
					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/** Le taux de TVA d'une variante est celui de la famille : montré, jamais posté. */
	private function getFieldVatShared(\util\FormUi $form, Product $eProduct): string {

		if($eProduct['structure']->conf()['hasVat'] === FALSE) {
			return '';
		}

		return $form->group(
			self::p('vat')->label,
			$form->fake(s("{value} %", ProductSetting::getVatRate($eProduct['structure'], $eProduct['vat'])))
		);

	}

	private function getFieldPrices(\util\FormUi $form, Product $eProduct, string $for): string {

		// Prix de famille : sur une variante en prix commun, prix et canaux de vente vivent
		// sur le principal — la section entière disparaît, comme les champs inféodés
		if(
			$eProduct['variantOf']->notEmpty() and
			$eProduct['variantOf']['priceMode'] === Product::SHARED
		) {
			return '';
		}
		// `ModulePage::quick()` rend ces deux champs seuls, sans passer par ici : le défaut se pose
		// donc aussi dans leur describer, sinon l'édition rapide lève sur une propriété non posée.
		$eProduct['displayPriceSteps'] = ($for === 'update');

		// En prix par variante, le prix du principal ne se vend jamais : il ne sert qu'à
		// l'unification. Ces propriétés quittent aussi getPropertiesUpdate() (Product.l.php).
		// Le panneau de création partage cette méthode, et son élément porte encore l'id du
		// produit dupliqué (page/product.p.php, branche ?from=, qui ne l'écarte pas).
		if(
			$for === 'update' and
			$eProduct['variantOf']->empty() and
			$eProduct['variants'] > 0 and
			$eProduct['priceMode'] === Product::VARIANT
		) {
			return '';
		}

		$h = '<br/>';

		$h .= '<h3>'.s("Prix").'</h3>';

		if($for === 'create') {
			$h .= '<div class="util-block-info" data-profile="'.Product::COMPOSITION.'">'.s("Un produit composé peut être vendu soit aux particuliers, soit aux professionnels, mais pas simultanément aux deux. Le choix que vous faites maintenant ne pourra pas être modifié par la suite, et votre produit ne pourra être composé que de produits également vendus à ce type de clientèle.").'</div>';
		}

		$h .= '<div class="util-info" data-not-profile="'.Product::COMPOSITION.'">'.s("Pour une vente aux particuliers et si aucun prix de vente n'a été saisi, le prix de vente pro augmenté de la TVA sera utilisé dans ce cas, et vice-versa pour une vente aux professionnels. Ces données de base pourront toujours être personnalisées pour chaque client et vente.").'</div>';

		$h .= '<div class="hide-panel-out mb-2">'.\Asset::icon('exclamation-circle').' '.s("Les prix de base que vous donnez à vos produits ne sont pas prioritaires par rapport aux prix indiqués dans les catalogues et aux prix personnalisés de vos clients (<link>en savoir plus</link>).", ['link' => '<a href="/doc/selling:pricing">']).'</div>';

		$h .= '<br/>';

		if(
			$for === 'create' or
			$eProduct['profile'] !== Product::COMPOSITION or
			$eProduct['private']
		) {
			$h .= self::getFieldPrivate($form, $eProduct, $for);
		}

		if(
			$for === 'create' or
			$eProduct['profile'] !== Product::COMPOSITION or
			$eProduct['pro']
		) {
			$h .= self::getFieldPro($form, $eProduct, $for);
		}

		if($for === 'update') {
			$h .= $form->dynamicGroup($eProduct, 'discountable');
		}

		$h .= '<br/>';

		return $h;

	}

	private static function getFieldPro(\util\FormUi $form, Product $eProduct, string $for): string {

		$h = '<div class="util-block product-write-pro '.(($for === 'create' or $eProduct['pro']) ? '' : 'product-write-off').'" data-wrapper="'.'pro'.'-block">';

			$h .= $form->group(
				'<h4>'.self::p('pro')->label.'</h4>',
				($for === 'create' or $eProduct['profile'] !== Product::COMPOSITION) ? $form->dynamicField($eProduct, 'pro') : ''
			);

			$h .= $form->group(
				s("Prix de base"),
				$form->dynamicField($eProduct, 'proPrice'),
				['data-wrapper' => 'proPrice']
			);

			$h .= self::getPriceDiscountGroup($form, $eProduct, 'pro');

			if($eProduct['displayPriceSteps']) {

				$h .= $form->group(
					self::p('proPriceSteps')->label,
					self::getFieldPriceSteps($form, $eProduct, 'pro'),
					self::getPriceStepsGroupAttributes($eProduct, 'pro')
				);

			}

			$unit = ($eProduct['unit']->notEmpty() ? encode($eProduct['unit']['singular']) : self::p('unit')->placeholder);

			$h .= '<div data-profile="'.implode(' ', Product::getProfiles('proPackaging')).'">';
				$h .= $form->group(
					s("Colis de base"),
					$form->inputGroup(
												$form->dynamicField($eProduct, 'proPackaging').
						'<div class="input-group-addon" data-ref="product-unit">'.$unit.'</div>'
					)
				);
			$h .= '</div>';

			if($for === 'update') {

				$h .= $form->group(
					self::p('proStep')->label,
					$form->inputGroup(
												$form->dynamicField($eProduct, 'proStep', function($d) {
							$d->placeholder = 1;
						}).
						'<div class="input-group-addon" data-ref="product-unit">'.$unit.'</div>'
					).$form->info(s("Les quantités achetées par les clients dans les boutiques en ligne seront un multiple de cette valeur si vous n'avez pas défini de colis de base."))
				);

			}

		$h .= '</div>';

		return $h;

	}

	private static function getFieldPrivate(\util\FormUi $form, Product $eProduct, string $for): string {

		$h = '<div class="util-block product-write-private '.(($for === 'create' or $eProduct['private']) ? '' : 'product-write-off').'" data-wrapper="'.'private'.'-block">';

			$h .= $form->group(
				'<h4>'.self::p('private')->label.'</h4>',
				($for === 'create' or $eProduct['profile'] !== Product::COMPOSITION) ? $form->dynamicField($eProduct, 'private') : ''
			);

			$h .= $form->group(
				s("Prix de base"),
				$form->dynamicField($eProduct, 'privatePrice'),
				['data-wrapper' => 'privatePrice']
			);

			$h .= self::getPriceDiscountGroup($form, $eProduct, 'private');

			if($eProduct['displayPriceSteps']) {

				$h .= $form->group(
					self::p('privatePriceSteps')->label,
					self::getFieldPriceSteps($form, $eProduct, 'private'),
					self::getPriceStepsGroupAttributes($eProduct, 'private')
				);

			}

			if($for === 'update') {

				$unit = ($eProduct['unit']->notEmpty() ? encode($eProduct['unit']['singular']) : self::p('unit')->placeholder);

				$h .= $form->group(
					self::p('privateStep')->label,
					$form->inputGroup(
												$form->dynamicField($eProduct, 'privateStep', function($d) use($eProduct) {
							if($eProduct->offsetExists('id')) {
								$d->placeholder = self::getDefaultPrivateStep($eProduct);
							}
						}).
						'<div class="input-group-addon" data-ref="product-unit">'.$unit.'</div>'
					).$form->info(s("Les quantités achetées par les clients dans les boutiques en ligne seront un multiple de cette valeur."))
				);

			}

		$h .= '</div>';

		return $h;

	}

	public function updateAccount(\structure\Structure $eStructure, \Collection $cProduct): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/product/product:doUpdateAccountCollection', ['id' => 'product-update-account']);

			$h .= $this->getFieldAccounting($form, new Product(['structure' => $eStructure, 'proAccountNumber' => NULL, 'privateAccountNumber' => NULL]));

			foreach($cProduct as $eProduct) {
				$h .= $form->hidden('ids[]', $eProduct['id']);
			}

			$h .= $form->submit(s("Enregistrer"), ['data-waiter' => s("Enregistrement en cours...")]);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-product-update-account',
			title: s("Numéro de compte des produits sélectionnés"),
			body: $h
		);

	}

	private function getFieldAccounting(\util\FormUi $form, Product $eProduct): string {

		if(
			\structure\StructureSetting::hasAccountingApp() === FALSE or
			($eProduct['structure']->hasAccounting() and $eProduct->acceptUpdateAccounting() === FALSE)
		) {
			return '';
		}

		$h = '<br/>';
		$h .= '<h3><span class="badge badge-solid bg-accounting">'.s("Comptabilité").'</span></h3>';

		if($eProduct['structure']->hasAccounting() === FALSE) {
			$h .= '<div class="util-block-info">'.s("Pour utiliser cette fonctionnalité, activez le module de gestion !").'</div>';
		} else {

			$h .= '<div class="util-block">';

				$genericLabel = s("Numéro de compte");
				$specificLabel = s("Vente aux clients particuliers");

				$hasProAccount = $eProduct['proAccountNumber'] !== NULL;

				$h .= $form->group(
					'<span data-field-label="privateAccountNumber">'.($hasProAccount ? $specificLabel : $genericLabel).'</span>',
					$form->dynamicField($eProduct, 'privateAccountNumber', function($d) use($form) {
						$d->attributes['data-account'] = 'privateAccountNumber';
					}).
					'<div class="form-info product-write-pro">'.$form->checkbox('accountDissociation', '1', [
						'callbackLabel' => fn($input) => $input.' '.s("Dissocier le numéro de compte pour la vente aux particuliers et aux professionnels"),
						'onclick' => 'Product.accountDissociation()',
						'data-field-account-generic-label' => $genericLabel,
						'data-field-account-specific-label' => $specificLabel,
						'checked' => $hasProAccount,
					]).'</div>',
					['data-wrapper' => 'privateAccountNumber']
				);

				$h .= '<div data-field="proAccountNumber" class="product-write-pro '.($hasProAccount ? '' : 'hide').'">'.$form->group(
					s("Vente aux clients professionnels"),
					$form->dynamicField($eProduct, 'proAccountNumber', function($d) use($form) {
						$d->attributes['data-account'] = 'proAccountNumber';
					}),
						['data-wrapper' => 'proAccountNumber']
				).'</div>';


			$h .= '</div>';

		}

		return $h;

	}

	public static function getFrozenIcon(): string {
		return \Asset::icon('snow', ['style' => 'color: dodgerblue']);
	}

	public static function getPreparationField(\util\FormUi $form, Product $eProduct): string {

		$times = [];

		foreach(range(6, 23) as $hour) {
			$times[sprintf('%02d:00:00', $hour)] = s("à {value}", sprintf('%02d:00', $hour));
		}

		$times['23:59:00'] = s("à {value}", '23:59');

		return $form->inputGroup(
			$form->number('preparationDays', $eProduct['preparationDays'], ['min' => 0, 'placeholder' => s("Aucun"), 'oninput' => 'Product.changePreparationDays(this)']).
			$form->addon(s("jours avant la livraison")).
			$form->select('preparationTime', $times, $eProduct['preparationTime'] ?? '23:59:00', ['required' => TRUE, 'class' => ($eProduct['preparationDays'] === NULL) ? 'hide' : ''])
		);

	}

	/**
	 * Le libellé d'une propriété hors formulaire. Ceux de `name` et `reference` sont des
	 * closures, pour que la zone d'édition en ligne d'une variante dise « de la variante »
	 * comme son formulaire : FormUi::quick() lit le describer du module et ne passe jamais
	 * par relabelField(). Form.u.php les résout seul (:250, :386), un affichage nu non.
	 */
	public static function getPropertyLabel(string $property, Product $eProduct): string {

		$label = self::p($property)->label;

		return is_closure($label) ? $label($eProduct) : $label;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Product::model()->describer($property, [
			'category' => s("Catégorie"),
			'vignette' => s("Vignette"),
			'name' => fn(Product $eProduct) => $eProduct['variantOf']->notEmpty() ? s("Nom de la variante") : s("Nom du produit"),
			'reference' => fn(Product $eProduct) => $eProduct['variantOf']->notEmpty() ? s("Référence de la variante") : s("Référence du produit"),
			'additional' => s("Complément d'information"),
			'plant' => s("Espèce"),
			'variety' => s("Variété"),
			'frozen' => s("Surgelé").'  '.self::getFrozenIcon(),
			'ingredients' => s("Composition"),
			'packaging' => s("Conditionnement"),
			'preparationDays' => s("Blocage des commandes"),
			'preparationTime' => s("Heure du blocage des commandes"),
			'allergens' => s("Allergènes"),
			'profile' => s("Type"),
			'distribution' => s("Distribution"),
			'origin' => s("Origine"),
			'description' => s("Présentation du produit"),
			'structure' => s("Ferme"),
			'unit' => s("Unité de vente"),
			'private' => s("Vente aux clients particuliers"),
			'privatePrice' => s("Prix particulier"),
			'privatePriceInitial' => s("Prix particulier de base"),
			'privatePriceDiscount' => s("Prix particulier remisé"),
			'privateStep' => s("Multiple de vente"),
			'privatePriceSteps' => s("Remise sur volume"),
			'pro' => s("Vente aux clients professionnels"),
			'proPrice' => s("Prix professionnel"),
			'proPriceInitial' => s("Prix professionnel de base"),
			'proPriceDiscount' => s("Prix professionnel remisé"),
			'proPackaging' => s("Colis de base"),
			'proStep' => s("Multiple de vente"),
			'proPriceSteps' => s("Remise sur volume"),
			'compositionVisibility' => s("Affichage de la composition aux clients"),
			'vat' => s("Taux de TVA"),
			'discountable' => s("Voulez-vous appliquer les remises commerciales paramétrées pour vos clients et vos ventes à ce produit ?"),
			'statut' => s("Statut"),
			'proAccountNumber' => s("Numéro de compte pour professionnels"),
			'privateAccountNumber' => s("Numéro de compte pour particuliers"),
		]);

		switch($property) {

			case 'id' :
				new ProductUi()->query($d);
				break;

			case 'additional' :
				$d->placeholder = s("Ex. : calibrage, lot, version...");
				break;

			case 'category' :
				$d->placeholder = s("Non catégorisé");
				$d->field = 'radio';
				$d->values = fn(Product $e) => $e['cCategory'] ?? $e->expects(['cCategory']);
				$d->attributes = [
					'columns' => 2,
				];
				break;

			// `dynamicField()` appelle la fermeture par `Closure::call()`, qui rebase la portée : la classe est nommée, jamais `self::`.
			case 'preparationDays' :
				$d->wrapper = 'preparationDays preparationTime';
				$d->labelAfter = \util\FormUi::info(s("Jusqu'à quand vos clients peuvent commander ce produit sur une boutique en ligne. Sans effet sur les ventes en continu, qui n'annoncent pas de livraison."));
				$d->field = fn(\util\FormUi $form, Product $e) => ProductUi::getPreparationField($form, $e);
				break;

			case 'reference' :
				$d->placeholder = s("Uniquement des caractères alphanumériques et tirets");
				$d->prepend = '#';
				$d->after = \util\FormUi::info(s("La référence est facultative, mais si vous l'indiquez, elle doit être différente pour chaque produit"));
				break;

			case 'profile' :
				$d->field = function(\util\FormUi $form, Product $eProduct) use ($d) {

					$h = '<a data-dropdown="bottom-start" class="btn btn-primary dropdown-toggle" data-dropdown-hover="true">';
						$h .= ProductUi::getProfileIcon($eProduct['profile']).'  ';
						$h .= $form->inputRadio('profile', $eProduct['profile'], attributes: ['checked' => 'checked', 'class' => 'hide']);
						$h .= '<span class="product-profile-name">'.$d->values[$eProduct['profile']].'</span>';
					$h .= '</a>';
					$h .= ProductUi::getProfileDropdown($eProduct, fn($h) => attr('onclick', 'Product.changeProfile(this)'), $d->values);

					if($eProduct->exists() === FALSE) {
						$h .= '<div class="mt-1" data-profile="'.Product::COMPOSITION.'">';
							$h .= '<div class="util-block-info">';
								$h .= '<h3>'.s("Qu'est-ce qu'un produit composé ?").'</h3>';
								$h .= '<p>'.s("Un produit composé est un produit qui rassemble plusieurs autres produits. Cela peut être par exemple un panier de légumes dont vous modifiez la composition toutes les semaines, un bouquet de fleurs que vous cultivez, une cagette de légumes pour la ratatouille...").'</p>';
								$h .= '<p>'.s("Vous pouvez choisir la composition de votre produit à l'étape suivante.").'</p>';
							$h .= '</div>';
						$h .= '</div>';
					}

					return $h;
					
				};

				$d->values = [
					Product::PLANT => s("Produit d'origine végétale"),
					Product::ANIMAL => s("Produit d'origine animale"),
					Product::FOOD => s("Produit alimentaire transformé"),
					Product::PRODUCT => s("Produit non alimentaire"),
					Product::COMPOSITION => s("Produit composé"),
					Product::OTHER => s("Autre produit"),
					Product::SERVICE => s("Prestation de service"),
					Product::SHIPPING => s("Frais de livraison"),
				];

				$d->examples = [
					Product::PLANT => s("Fruit, légume, fleur, plant..."),
					Product::ANIMAL => s("Viande, oeuf, lait, animal vivant..."),
					Product::FOOD => s("Pain, crèmerie, confiture..."),
					Product::PRODUCT => s("Savon, lessive..."),
					Product::COMPOSITION => s("Panier de légumes, bouquet de fleurs..."),
					Product::OTHER => NULL,
					Product::SERVICE => NULL,
					Product::SHIPPING => NULL,
				];

				$d->icons = [
					Product::PLANT => \Asset::icon('leaf'),
					Product::ANIMAL => \Asset::icon('egg'),
					Product::FOOD => \Asset::icon('fork-knife'),
					Product::PRODUCT => \Asset::icon('box'),
					Product::COMPOSITION => \Asset::icon('puzzle-fill'),
					Product::OTHER => \Asset::icon('three-dots'),
					Product::SERVICE => \Asset::icon('hand-thumbs-up-fill'),
					Product::SHIPPING => \Asset::icon('truck'),
				];
				break;

			case 'plant' :
				$d->autocompleteBody = function(\util\FormUi $form, Product $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id']
					];
				};
				new \plant\PlantUi()->query($d);
				break;

			case 'variety' :
				$d->placeholder = s("Ex. : Chérie");
				break;

			case 'frozen' :
				$d->field = 'switch';
				break;

			case 'unit' :
				$d->values = fn(Product $e) => isset($e['cUnit']) ? UnitUi::getField($e['cUnit']) : $e->expects(['cUnit']);
				$d->attributes = ['group' => TRUE];
				$d->placeholder = s("&lt; Non applicable &gt;");
				break;

			case 'status' :
				$d->values = [
					Product::ACTIVE => s("Activé"),
					Product::INACTIVE => s("Désactivé"),
					Product::DELETED => s("Supprimé"),
				];
				break;

			case 'private' :
			case 'pro' :
				$d->field = 'switch';
				$d->attributes += [
					'onchange' => 'Product.changeType(input, "'.$property.'")'
				];
				break;

			case 'privateStep' :
				$d->attributes += [
					'disabled' => function(\util\FormUi $form, Product $e) {
						return $e['private'] ? NULL : 'disabled';
					}
				];
				break;

			case 'privatePriceDiscount' :

				$d->field = function(\util\FormUi $form, Product $e) {

					$taxes = $e['structure']->conf()['hasVat'] ? ProductUi::getTaxes('private') : '';
					$unit = ($e['unit']->notEmpty() ? encode($e['unit']['singular']) : ProductUi::p('unit')->placeholder);
					$suffix = '€ '.$taxes.' / <span data-ref="product-unit">'.$unit.'</span>';

					$identifier = ProductUi::getPriceDiscountIdentifier($e, 'private');

					return $form->inputGroup(
						$form->number('privatePriceDiscount', ($e['privatePriceInitial'] ?? NULL) !== NULL ? $e['privatePrice'] ?? '' : '', [
							'step' => 0.0001,
							'disabled' => $e['private'] ? NULL : 'disabled'
						]).
						$form->addon($suffix).
						$form->addon(new PriceUi()->getDiscountTrashAddon($identifier))
					);

				};
				break;

			case 'privatePrice' :

				$d->field = function(\util\FormUi $form, Product $e) {

					$e['displayPriceSteps'] ??= FALSE;

					$taxes = $e['structure']->conf()['hasVat'] ? ProductUi::getTaxes('private') : '';
					$unit = ($e['unit']->notEmpty() ? encode($e['unit']['singular']) : ProductUi::p('unit')->placeholder);
					$suffix = '€ '.$taxes.' / <span data-ref="product-unit">'.$unit.'</span>';

					$price = ($e['privatePriceInitial'] ?? NULL) !== NULL ? $e['privatePriceInitial'] : $e['privatePrice'] ?? '';
					$priceDiscount = ($e['privatePriceInitial'] ?? NULL) !== NULL ? $e['privatePrice'] ?? '' : '';

					// Un prix remisé à 0 € reste une remise à afficher
					$hasDiscountPrice = ($priceDiscount !== '');

					$identifier = ProductUi::getPriceDiscountIdentifier($e, 'private');

					$h = $form->inputGroup(
												$form->number('privatePrice', $price, [
							'step' => 0.0001,
							'onfocus' => 'this.select()',
							'disabled' => $e['private'] ? NULL : 'disabled'
						]).
						$form->addon($suffix),
					);
					// Les deux boutons partagent le même `field-action` : ils règlent tous deux ce que
					// vaut ce prix, et deux rangées de boutons sous un champ se liraient comme deux
					// sujets.
					$h .= \util\FormUi::getFieldAction(
						new PriceUi()->getDiscountLink($identifier, hasDiscountPrice: $hasDiscountPrice)
						.($e['displayPriceSteps'] ? ProductUi::getPriceStepsLink(ProductUi::getPriceStepsIdentifier($e, 'private'), (bool)$e['privatePriceSteps']) : '')
					);

					// Le quick ne rend que la propriété qu'on lui demande, mais il construit aussi
					// `privatePriceDiscount` : sans son champ, le bouton « Remiser » n'ouvre rien et le
					// prix remisé arrive vide à `build()`, qui le lit comme une remise à 0 €.
					if($e->isQuick()) {
						$h .= ProductUi::getPriceDiscountGroup($form, $e, 'private');
					}

					return $h;

				};

				$d->attributes += [
					'disabled' => function(\util\FormUi $form, Product $e) {
						return $e['private'] ? NULL : 'disabled';
					}
				];
				break;

			case 'proPackaging' :
			case 'proStep' :
				$d->attributes += [
					'disabled' => function(\util\FormUi $form, Product $e) {
						return $e['pro'] ? NULL : 'disabled';
					}
				];
				break;

			case 'proPriceDiscount' :

				$d->field = function(\util\FormUi $form, Product $e) {

					$taxes = $e['structure']->conf()['hasVat'] ? ProductUi::getTaxes('pro') : '';
					$unit = ($e['unit']->notEmpty() ? encode($e['unit']['singular']) : ProductUi::p('unit')->placeholder);
					$suffix = '€ '.$taxes.' / <span data-ref="product-unit">'.$unit.'</span>';

					$identifier = ProductUi::getPriceDiscountIdentifier($e, 'pro');

					return $form->inputGroup(
						$form->number('proPriceDiscount', ($e['proPriceInitial'] ?? NULL) !== NULL ? $e['proPrice'] ?? '' : '', [
							'step' => 0.0001,
							'disabled' => $e['pro'] ? NULL : 'disabled'
						]).
						$form->addon($suffix).
						$form->addon(new PriceUi()->getDiscountTrashAddon($identifier))
					);

				};
				break;

			case 'proPrice' :

				$d->field = function(\util\FormUi $form, Product $e) {

					$e['displayPriceSteps'] ??= FALSE;

					$taxes = $e['structure']->conf()['hasVat'] ? ProductUi::getTaxes('pro') : '';
					$unit = ($e['unit']->notEmpty() ? encode($e['unit']['singular']) : ProductUi::p('unit')->placeholder);
					$suffix = '€ '.$taxes.' / <span data-ref="product-unit">'.$unit.'</span>';

					$price = ($e['proPriceInitial'] ?? NULL) !== NULL ? $e['proPriceInitial'] : $e['proPrice'] ?? '';
					$priceDiscount = ($e['proPriceInitial'] ?? NULL) !== NULL ? $e['proPrice'] ?? '' : '';

					// Un prix remisé à 0 € reste une remise à afficher
					$hasDiscountPrice = ($priceDiscount !== '');

					$identifier = ProductUi::getPriceDiscountIdentifier($e, 'pro');

					$h = $form->inputGroup(
												$form->number('proPrice', $price, [
							'step' => 0.0001,
							'onfocus' => 'this.select()',
							'disabled' => $e['pro'] ? NULL : 'disabled'
						]).
						$form->addon($suffix),
					);
					// Les deux boutons partagent le même `field-action` : ils règlent tous deux ce que
					// vaut ce prix, et deux rangées de boutons sous un champ se liraient comme deux
					// sujets.
					$h .= \util\FormUi::getFieldAction(
						new PriceUi()->getDiscountLink($identifier, hasDiscountPrice: $hasDiscountPrice)
						.($e['displayPriceSteps'] ? ProductUi::getPriceStepsLink(ProductUi::getPriceStepsIdentifier($e, 'pro'), (bool)$e['proPriceSteps']) : '')
					);

					// Le quick ne rend que la propriété qu'on lui demande, mais il construit aussi
					// `proPriceDiscount` : sans son champ, le bouton « Remiser » n'ouvre rien et le
					// prix remisé arrive vide à `build()`, qui le lit comme une remise à 0 €.
					if($e->isQuick()) {
						$h .= ProductUi::getPriceDiscountGroup($form, $e, 'pro');
					}

					return $h;

				};

				$d->attributes += [
					'disabled' => function(\util\FormUi $form, Product $e) {
						return $e['pro'] ? NULL : 'disabled';
					}
				];
				break;

			case 'organic' :
				$d->field = 'select';
				$d->values = self::getOrganic();
				break;

			case 'qualities' :
				$d->values = self::getQualitiesLabels();
				$d->field = fn(\util\FormUi $form, Product $e) => $form->checkboxes('qualities[]', $d->values, $e['qualities'] ?? [], ['all' => FALSE, 'columns' => 3]);
				break;

			case 'compositionVisibility' :
				$d->values = [
					Product::PUBLIC => s("Visible").'  <span class="color-muted"><small>'.s("La composition du produit est affichée aux clients").'</small></span>',
					Product::PRIVATE => s("Cachée").'  <span class="color-muted"><small>'.s("Les clients ne voient pas la composition du produit").'</small></span>'
				];
				$d->default = Product::PRIVATE;
				$d->required = TRUE;
				break;

			case 'distribution' :
				$d->field = 'select';
				$d->required = TRUE;
				$d->values = [
					Product::PRODUCTION => s("Votre production"),
					Product::RESALE => s("Produit en achat-revente"),
				];
				$d->shortValues = [
					Product::PRODUCTION => s("Production"),
					Product::RESALE => s("Achat-revente"),
				];
				break;

			case 'discountable' :
				$d->field = 'yesNo';
				$d->after = \util\FormUi::info(s("La modification de ce paramètre n'est pas rétroactive."));
				break;

			case 'origin' :
				$d->attributes = [
					'placeholder' => s("Ex. : Ferme d'à côté (63)"),
				];
				break;

			case 'vat' :
				$d->field = 'select';
				$d->values = function(Product $e) {
					return ProductUi::getVat($e['structure']);
				};
				$d->required = TRUE;
				break;

			case 'proAccountNumber':
			case 'privateAccountNumber':
				\Asset::css('preaccounting', 'preaccounting.css');

				$d->autocompleteDefault = function(Product $e) use($property) {

					if($e[$property] === NULL) {
						return NULL;
					}

					$accountProperty = mb_substr($property, 0, mb_strlen($property) - mb_strlen('Number'));
					if(isset($e[$accountProperty]) === FALSE or $e[$accountProperty]->empty()) {
						return NULL;
					}

					return $e[$accountProperty];

				};
				$d->wrapper = $property;

				$body = [
					'classPrefix' => \account\AccountSetting::PRODUCT_ACCOUNT_CLASS,
				];
				
				if(get_exists($property.'Number') and GET($property.'Number') === '0') {
					$body['noAccount'] = TRUE;
				}
				$body['strictOrPrefix'] = TRUE;
				$body['breakdown'] = \account\SaleBreakdownAiLib::SEARCH_SALE;
				$d->autocompleteBody = $body;
				$d->attributes['data-account-breakdown'] = $property;
				$d->after = fn(\util\FormUi $form, Product $e) => \util\FormUi::info(
					'<div class="color-warning">'.
							s("Vous n'avez pas choisi un compte de produit (classe <b>{value}</b>). Vérifiez votre saisie.", \account\AccountSetting::PRODUCT_ACCOUNT_CLASS).
						'</div>',
					'hide', 'hint-'.$property,
				).(in_array($property, $e['propertiesBreakdown'] ?? []) ? \util\FormUi::info(
					'<div class="color-warning">'.s("Choisissez un compte de 7011 à 7014 ou une de leurs subdivisions, conformément au plan comptable agricole.").'</div>',
					'account-breakdown-hint',
				) : '');
				
				new \account\AccountUi()->query($d, GET('structure', 'structure\Structure'));

				break;

		}

		return $d;

	}

}
?>
