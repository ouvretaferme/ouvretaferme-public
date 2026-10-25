<?php
namespace product;

class Product extends ProductElement {

	// Les champs copiés du principal à la naissance d'une variante, qui vit ensuite sa vie :
	// « dès qu'on crée une variante, on duplique les infos ». Un champ posté à la création prime
	// sur la copie.
	const VARIANT_COPIED = [
		'name',
		'private', 'privatePrice', 'privatePriceInitial', 'privateStep', 'privatePriceSteps',
		'pro', 'proPrice', 'proPriceInitial', 'proStep', 'proPriceSteps', 'discountable',
		'unit', 'proPackaging',
		'additional', 'description', 'vignette', 'variety', 'frozen', 'packaging', 'allergens', 'ingredients', 'compositionVisibility'
	];

	// L'espèce est commune à la famille, la variété distingue les variantes : plant est
	// propagée, variety reste dans le groupe info (« Tomate ancienne » / « Cœur de bœuf »)
	const VARIANT_PROPAGATED = ['vat', 'distribution', 'category', 'organic', 'qualities', 'origin', 'plant', 'preparationDays', 'preparationTime'];

	// Le périmètre du prix de famille (arbitré par le chef de projet le 2026-09-01, élargi
	// aux deux canaux de vente sur demande du 2026-09-02) : quand le principal est
	// en priceMode SHARED, ces champs rejoignent les
	// propagés — copiés à la création, imposés même postés, descendus à chaque écriture du
	// principal. private et pro en sont parce que couper un canal met son prix à NULL : les
	// laisser par variante rendrait à une variante un prix qu'elle ne détient pas. unit
	// reste dehors, un « même prix » entre un kilo et une pièce ne voulant rien dire. Ces
	// champs restent membres de VARIANT_COPIED, leur régime de base : VARIANT_PRICE est un
	// sélecteur, pas un régime.
	const VARIANT_PRICE = ['private', 'privatePrice', 'privatePriceInitial', 'privateStep', 'privatePriceSteps', 'pro', 'proPrice', 'proPriceInitial', 'proStep', 'proPriceSteps', 'proPackaging', 'discountable'];

	const VARIANT_FIXED = ['structure', 'profile'];

	const VARIANT_PRINCIPAL_ONLY = ['variants', 'priceMode', 'stock', 'stockStatus', 'stockLast', 'stockUpdatedAt'];

	const VARIANT_FORBIDDEN = ['privateAccountNumber', 'proAccountNumber'];

	public static function getSelection(): array {

		$selection = parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
			'plant' => ['name', 'fqn', 'vignette'],
			'unit' => \product\Unit::getSelection(),
			// `privatePrice` et `proPrice` portés ici depuis le 2026-09-08 : le prix commun d'une
			// famille est celui du contenant, et une offre de variante doit savoir s'il existe sur son
			// canal (Offer::hasFamilyPrice()). Deux colonnes de plus sur une ligne déjà jointe ne
			// coûtent aucune requête.
			'variantOf' => ['name', 'vignette', 'profile', 'priceMode', 'privatePrice', 'proPrice', 'variants'],
			'stockExpired' => new \Sql('stockUpdatedAt IS NOT NULL AND stockUpdatedAt < NOW() - INTERVAL 7 DAY', 'bool'),
			'proAccountNumber', 'privateAccountNumber'
		];

		if(\structure\StructureSetting::hasAccountingApp()) {
			$selection['proAccount?'] = fn($e) => \account\Account::delegate($e, 'proAccountNumber');
			$selection['privateAccount?'] = fn($e) => \account\Account::delegate($e, 'privateAccountNumber');
		}

		return $selection;

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canSelling();

	}

	public function canCreate(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	public function canWrite(): bool {

		$this->expects(['structure', 'status']);

		return (
			$this['structure']->canManage() and
			$this->isManipulable()
		);

	}

	public function canManage(): bool {
		return $this['structure']->canManage();
	}

	public function canUpdateAccounting(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManageAccounting();

	}

	// Au livre des recettes, toute vente va au compte de ventes : un compte propre au produit n'y serait lu par personne.
	public function acceptUpdateAccounting(): bool {

		$this->expects(['structure']);

		return $this['structure']->isCashReceipts(\Clock::date()) === FALSE;

	}

	public function acceptCreate(): bool {
		return $this['structure']['isLegal'];
	}

	public function acceptWrite(): bool {

		return (
			$this->isManipulable() and
			$this['status'] !== Product::DELETED
		);

	}

	/**
	 * Un contenant ne suit jamais de stock, symétrique de acceptDisableStock() : il ne se vend
	 * pas (acceptSell() exige `variants === 0`) et une quantité posée dessus ne serait celle de
	 * personne, chaque variante tenant la sienne.
	 */
	public function acceptEnableStock(): bool {

		$this->expects(['variants']);

		if($this->acceptStock()) {
			return FALSE;
		}

		return ($this['variants'] === 0);

	}

	public function acceptDisableStock(): bool {

		$this->expects(['variants']);

		if($this['variants'] > 0) {
			return FALSE;
		}

		return $this->acceptStock();

	}

	public function acceptEnableVariants(): bool {

		$this->expects(['variantOf', 'variants']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] === 0
		);

	}

	public function acceptDisableVariants(): bool {

		$this->expects(['variantOf', 'variants']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] === 1
		);

	}

	// Rattacher un produit existant à une famille. Le geste ne vit que sur la fiche du
	// contenant (T3, 2026-09-03) : cet accept* gate le lien, la porte de l'écriture
	// reste variantOf.check, qui pèse la ligne déplacée et non la famille.
	public function acceptAttachVariant(): bool {

		$this->expects(['variantOf', 'variants']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] > 0
		);

	}

	// L'inverse exact de acceptAttachVariant() (retour du 2026-09-03) : la variante quitte sa
	// famille sans y toucher. Le cas d'une seule variante restante n'y passe pas — c'est
	// acceptDisableVariants(), qui dissout la famille entière et vit sur le menu du principal.
	public function acceptDetachVariant(): bool {

		$this->expects(['variantOf', 'variants']);

		if($this['variantOf']->notEmpty()) {
			$this['variantOf']->expects(['variants']);
		}

		return (
			$this['variantOf']->notEmpty() and
			$this['variantOf']['variants'] > 1
		);

	}

	// Ici et non dans variantOf.check : l'écriture, qui passe par build(), et l'écran qui
	// l'annonce avant de la tenter posent la même question par le même code.
	public function acceptVariantOf(Product $eProductPrincipal): bool {

		$this->expects(['structure', 'profile', 'variants']);

		if($eProductPrincipal->empty()) {
			return TRUE;
		}

		$eProductPrincipal->expects(['structure', 'variantOf', 'variants', 'profile', 'status']);

		// La profondeur du rattachement est de un, dans les deux sens.
		if(
			$eProductPrincipal->is($this) or
			$this['variants'] > 0
		) {
			return FALSE;
		}

		// Une famille ne naît que par VariantLib::enable() : la cible doit déjà porter des
		// variantes, sinon un POST forgé ou l'API recrée un produit vendable à variantes, la
		// forme que le modèle M3 élimine.
		return (
			$eProductPrincipal['structure']->is($this['structure']) and
			$eProductPrincipal['variantOf']->empty() and
			$eProductPrincipal['variants'] > 0 and
			$eProductPrincipal['status'] !== Product::DELETED and
			$eProductPrincipal['profile'] === $this['profile']
		);

	}

	// Libérer les prix d'une famille en prix commun : chaque variante repart de ses valeurs
	// du moment.
	public function acceptSeparatePrices(): bool {

		$this->expects(['variantOf', 'variants', 'priceMode']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] > 0 and
			$this['priceMode'] === Product::SHARED
		);

	}

	// Unifier les prix d'une famille : les valeurs du principal s'imposent et descendent.
	// L'état seul est ici ; ce que le geste va écraser se compte par
	// VariantLib::countUnifyPrices(), et les grilles divergentes le refusent.
	public function acceptUnifyPrices(): bool {

		$this->expects(['variantOf', 'variants', 'priceMode']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] > 0 and
			$this['priceMode'] === Product::VARIANT
		);

	}

	// Réordonner les variantes d'une famille. Deux lignes au moins : une famille d'une seule
	// ligne n'a pas d'ordre, et la poignée ne doit pas promettre un geste sans effet.
	public function acceptUpdatePositions(): bool {

		$this->expects(['variantOf', 'variants']);

		return (
			$this['variantOf']->empty() and
			$this['variants'] > 1
		);

	}

	public function acceptSell(): bool {

		$this->expects(['variants']);

		return ($this['variants'] === 0);

	}

	public function canStock(): bool {

		return $this['structure']->canSelling();

	}

	public function acceptStock(): bool {

		return ($this['stockStatus'] === Product::ACTIVE);

	}

	public function acceptDelete(): bool {

		$this->expects(['variantOf', 'variants']);

		if($this['variantOf']->notEmpty()) {
			$this['variantOf']->expects(['variants']);
		}

		return (
			$this->acceptWrite() and
			$this['variants'] === 0 and
			(
				$this['variantOf']->empty() or
				$this['variantOf']['variants'] > 1
			)
		);

	}

	public function acceptDuplicate(): bool {

		return ($this['profile'] !== Product::COMPOSITION);

	}

	public function getWriteProfiles(): array {

		return array_merge(
			[
				Product::PLANT,
				Product::ANIMAL,
				Product::FOOD,
				Product::PRODUCT,
			],
			($this->exists() === FALSE) ? [Product::COMPOSITION] : [],
			[
				Product::OTHER,
				Product::SERVICE,
			]
		);

	}

	// La variété distingue les variantes entre elles : un contenant ne peut pas en porter une
	// qui vaudrait pour toute sa famille, et l'afficher ferait passer celle d'une de ses lignes
	// pour la sienne. Le critère est `variants` et non `variantOf` : une
	// variante n'est pas un contenant et garde la sienne, c'est ce qui sépare « Cœur de bœuf »
	// de « Green Zebra » sous le même nom.
	public function getName(string $mode = 'text', bool $withPrincipal = FALSE): string {

		$this->expects(['name', 'variety', 'frozen', 'variants']);

		$variety = ($this['variants'] === 0) ? $this['variety'] : NULL;

		if($mode === 'text') {

			$h = $this['name'];
			if($variety !== NULL) {
				$h .= ' / '.$variety;
			}

		} else {

			$h = encode($this['name']);
			if($this['frozen']) {
				$h .= ' '.ProductUi::getFrozenIcon();
			}
			if($variety !== NULL) {
				$h .= '<small title="'.s("Variété").'"> / '.encode($variety).'</small>';
			}

		}

		if($withPrincipal === FALSE) {
			return $h;
		}

		return $this->getNameWithPrincipal($h, $mode);

	}

	/**
	 * Un libellé de variante préfixé du nom de sa famille.
	 *
	 * Sortie de getName() parce que deux lectures en ont besoin sans vouloir le même libellé : la
	 * fiche compose le nom **et la variété**, les relevés de prix composent le nom seul — la
	 * variété y changerait la reconnaissance de 9 085 des 28 935 produits cotables, variantes ou
	 * non. Le préfixe, lui, obéit à la même règle des deux côtés, et deux copies de cette règle
	 * finiraient par ne plus rendre la même graphie pour un même produit.
	 *
	 * VariantLib::enable() fait du produit activé la première variante de son contenant, dont il
	 * porte donc le nom : le préfixe ne s'ajoute qu'aux variantes qui s'en sont écartées, sans quoi
	 * l'activation renommerait les lignes vendues de toute la famille. toFqn() borne la comparaison
	 * au mot (Result.u.php:271) : « Chou » ne doit pas se reconnaître dans « Choucroute ».
	 *
	 * ⚠️ $name est une composition de $this['name'], jamais un libellé étranger : la borne se lit
	 * sur le nom du produit, pas sur l'argument, et les deux cesseraient sinon de parler du même.
	 */
	public function getNameWithPrincipal(string $name, string $mode = 'text'): string {

		$this->expects(['name', 'variantOf']);

		if($this['variantOf']->empty()) {
			return $name;
		}

		$principalFqn = toFqn($this['variantOf']['name']);

		if($principalFqn !== '' and preg_match('/(^|-)'.preg_quote($principalFqn, '/').'(-|$)/', toFqn($this['name'])) === 1) {
			return $name;
		}

		$principal = ($mode === 'text') ? $this['variantOf']['name'] : encode($this['variantOf']['name']);

		return $principal.' - '.$name;

	}

	public static function getQualities(): array {

		return \structure\Structure::getQualities() + [
			Product::IGP => 'igp',
			Product::AOP => 'aop',
			Product::STG => 'stg',
			Product::LABEL_ROUGE => 'label-rouge'
		];

	}

	public function calcProMagicPrice(bool $hasVat): ?float {

		$this->expects(['vat', 'privatePrice']);

		if($this['privatePrice'] === NULL) {
			return NULL;
		}

		if($hasVat) {
			return round($this['privatePrice'] - $this->calcPrivateVat(), 2);
		} else {
			return $this['privatePrice'];
		}

	}

	/**
	 * Les paliers d'un canal de vente qui s'appliquent vraiment, cf. `ProductLib::getEffectivePriceSteps()`.
	 *
	 * `$for` est `private` ou `pro`, comme les colonnes qu'il préfixe : les deux canaux portent chacun leur barème
	 * et leur prix de base, et un palier ne se compare qu'au prix de son canal.
	 */
	public function getEffectivePriceSteps(string $for): array {

		$this->expects([$for.'PriceSteps', $for.'Price']);

		return ProductLib::getEffectivePriceSteps($this[$for.'PriceSteps'], $this[$for.'Price']);

	}

	public function calcPrivateMagicPrice(bool $hasVat): ?float {

		$this->expects(['vat', 'proPrice']);

		if($this['proPrice'] === NULL) {
			return NULL;
		}

		if($hasVat) {
			return $this['proPrice'] + $this->calcProVat();
		} else {
			return $this['proPrice'];
		}

	}

	public function calcProVat(): float {

		$this->expects(['vat', 'proPrice']);

		return vat_from_excluding($this['proPrice'], ProductSetting::getVatRate($this['structure'], $this['vat']), precision: 4);

	}

	public function calcPrivateVat(): float {

		$this->expects(['vat', 'privatePrice']);

		return vat_from_including($this['privatePrice'], ProductSetting::getVatRate($this['structure'], $this['vat']), precision: 4);

	}

	public static function checkReference(string $reference): bool {
		return (preg_match('/^[A-Z0-9\-\_]+$/s', $reference) > 0);
	}

	public function isManipulable(): bool {
		return in_array($this['status'], self::getManipulable());
	}

	public static function getManipulable(): array {
		return [Product::ACTIVE, Product::INACTIVE];
	}

	public static function getProfiles(string $property): array {
		return match($property) {
			'profile' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::SERVICE, Product::OTHER],
			'import' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::SERVICE, Product::COMPOSITION],
			'characteristics' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::COMPOSITION],
			'marks' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::COMPOSITION],
			'distribution' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::OTHER],
			'origin' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::COMPOSITION],
			'compositionVisibility' => [Product::COMPOSITION],
			'plant' => [Product::PLANT],
			'variety' => [Product::PLANT],
			'frozen' => [Product::ANIMAL, Product::FOOD],
			'packaging' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT],
			'preparationDays' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::COMPOSITION],
			'allergens' => [Product::FOOD],
			'ingredients' => [Product::FOOD, Product::ANIMAL, Product::PRODUCT],
			'proPackaging' => [Product::PLANT, Product::ANIMAL, Product::FOOD, Product::PRODUCT, Product::COMPOSITION],
		};
	}

	public static function properties(\PropertyList $l): void {

		// Les champs inféodés au principal ne sont jamais construits sur une variante : le
		// formulaire ne les offre pas (P8, 2026-09-01) et prepareCreate()/prepareUpdate()
		// les imposent depuis le principal. Sans ce saut, leurs étapes exigeraient des valeurs
		// que l'écran n'envoie plus et la création échouerait en silence (relecteur du
		// 2026-09-02, n° 1).
		foreach(Product::VARIANT_PROPAGATED as $property) {

			$l->property($property)
				->addBeforeCast('variant', function(&$value, \PropertyList $l): bool {

					// Un rattachement refusé laisse `variantOf` vide sur l'élément : sans ce saut, la
					// ligne serait relue comme un produit ordinaire et réclamerait les champs qu'un
					// formulaire de variante n'envoie pas, ajoutant deux refus de plus au seul qui
					// dit quelque chose au producteur.
					$l->skipIfInvalid('variantOf');

					if($this['variantOf']->notEmpty()) {
						throw new \PropertySkip();
					}

					return TRUE;

				});

		}

		// Même régime pour les prix d'une famille en prix commun, pseudo-propriétés
		// comprises : le prix vit sur le principal, le formulaire d'une variante ne les
		// rend pas. proOrPrivate voyage avec les deux canaux qu'il arbitre — son check lit
		// pro et private, qu'aucune variante en prix commun ne construit plus.
		foreach(array_merge(Product::VARIANT_PRICE, ['privatePriceDiscount', 'proPriceDiscount', 'proOrPrivate', 'proOrPrivatePrice']) as $property) {

			$l->property($property)
				->addBeforeCast('shared', function(&$value): bool {

					if(
						$this['variantOf']->notEmpty() and
						$this['variantOf']['priceMode'] === Product::SHARED
					) {
						throw new \PropertySkip();
					}

					return TRUE;

				});

		}

		foreach(Product::VARIANT_FORBIDDEN as $property) {

			$l->property($property)
				->addBeforeCast('variant', function(&$value, \PropertyList $l, ?string $wrapper) use ($property): bool {

					// Le refus vise la ligne qui était déjà une variante avant ce build : à la
					// création comme à la transformation, ProductLib les met à NULL de toute façon.
					if($this['variantOf']->notEmpty()) {

						if(
							$value !== NULL and
							$this['eProductVariantOfOld']->notEmpty()
						) {
							throw new \FailException('product\Product::variantForbidden', [$this, $property], wrapper: $wrapper);
						}

						throw new \PropertySkip();

					}

					return TRUE;

				});

		}

		$l->property('variantOf')
			->addBeforeCast('given', function(&$value): bool {

				if($value === NULL) {
					throw new \PropertySkip();
				}

				return TRUE;

			})
			->replaceCheck(function(Product $eProductVariantOf): bool {

				$this->expects(['structure', 'profile']);

				if($eProductVariantOf->empty()) {
					return TRUE;
				}

				// Sur un principal hydraté : acceptVariantOf() ne recharge rien pour son compte.
				Product::model()
					->select(['structure', 'variantOf', 'variants', 'profile', 'status', 'priceMode'])
					->get($eProductVariantOf);

				if($eProductVariantOf->empty()) {
					return FALSE;
				}

				return $this->acceptVariantOf($eProductVariantOf);

			})
			->addAfterCheck('last', function(Product $eProductVariantOf, \PropertyList $l): bool {

				// Détacher ou déplacer la dernière variante fabriquerait un principal vide,
				// produit fantôme que rien ne distingue : ce chemin-là est la désactivation.
				if(
					$l->isInvalid('variantOf') or
					$this['eProductVariantOfOld']->empty() or
					$eProductVariantOf->is($this['eProductVariantOfOld'])
				) {
					return TRUE;
				}

				$this['eProductVariantOfOld']->expects(['variants']);

				return ($this['eProductVariantOfOld']['variants'] > 1);

			})
			->addAfterCheck('subscription', fn(Product $eProductVariantOf, \PropertyList $l): bool => (
				$l->isInvalid('variantOf') or
				$eProductVariantOf->empty() or
				$eProductVariantOf->is($this['eProductVariantOfOld']) or
				$this['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS)
			));

		$l->property('variantList')
			->replaceCheck(function(?array $variantList): bool {

				$this['cProductVariant'] = VariantLib::buildFromInput($this, $variantList);

				return TRUE;

			})
			->addAfterCheck('subscription', fn(?array $variantList): bool => (
				$this['cProductVariant']->empty() or
				$this['structure']->acceptFeature(\structure\Structure::FEATURE_VARIANTS)
			));

		$l->property('category')
			->replaceCheck(function(Category $eCategory, \PropertyList $l): bool {

				$this->expects(['structure']);

				if($l->for === 'update') {
					$this['oldCategory'] = $this['category'];
				}

				return (
					$eCategory->empty() or (
						Category::model()
							->select('structure')
							->get($eCategory) and
						$eCategory->canRead()
					)
				);

			});

		$l->property('reference')
			->addAfterCast('uppercase', function(?string &$reference): bool {

				if($reference !== NULL) {
					$reference = strtoupper($reference);
				}

				return TRUE;

			})
			->addAfterCheck('format', function(?string $reference): bool {

				return (
					$reference === NULL or
					Product::checkReference($reference)
				);

			});

		$l->property('distribution')
			->addAfterCast('profile', function(?string &$distribution): bool {

				if(in_array($this['profile'], Product::getProfiles('distribution')) === FALSE) {
					$distribution = NULL;
					return TRUE;
				} else {
					return ($distribution !== NULL);
				}

			});

		$l->property('origin')
			->addAfterCast('profile', function(?string &$origin): bool {

				if(in_array($this['profile'], Product::getProfiles('origin')) === FALSE) {
					$origin = NULL;
				}

				return TRUE;

			});

		$l->property('ingredients')
			->addAfterCast('profile', function(?string &$composition): bool {

				if(in_array($this['profile'], Product::getProfiles('ingredients')) === FALSE) {
					$composition = NULL;
				}

				return TRUE;

			});

		$l->property('allergens')
			->addAfterCast('profile', function(?string &$allergen): bool {

				if(in_array($this['profile'], Product::getProfiles('allergens')) === FALSE) {
					$allergen = NULL;
				}

				return TRUE;

			});

		$l->property('organic')
			->addAfterCast('profile', function(?string &$organic): bool {

				if(in_array($this['profile'], Product::getProfiles('marks')) === FALSE) {
					$organic = Product::NO;
				}

				return TRUE;

			});

		$l->property('qualities')
			->addAfterCast('profile', function(?\Set &$qualities): bool {

				if(
					$qualities === NULL or
					in_array($this['profile'], Product::getProfiles('marks')) === FALSE
				) {
					$qualities = new \Set();
				}

				return TRUE;

			});

		// Le jour et l'heure ne vont pas l'un sans l'autre : sans jour, aucun blocage, et l'heure,
		// qu'un menu poste toujours, n'a plus de sens.
		$l->property('preparationTime')
			->addAfterCast('reset', function(?string &$preparationTime, \PropertyList $l): bool {

				$l->skipIfInvalid(['preparationDays']);
				$l->expectsBuilt('preparationDays');

				if($this['preparationDays'] === NULL) {
					$preparationTime = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('missing', function(?string $preparationTime): bool {
				return ($this['preparationDays'] === NULL or $preparationTime !== NULL);
			});

		$l->property('packaging')
			->addAfterCast('profile', function(?string &$packaging): bool {

				if(in_array($this['profile'], Product::getProfiles('packaging')) === FALSE) {
					$packaging = NULL;
				}

				return TRUE;

			});

		$l->property('profile')
			->addAfterCheck('consistency', function(?string $profile): bool {

				if(in_array($profile, $this->getWriteProfiles())) {
					return TRUE;
				} else {
					// Requis pour que les vérifications suivantes puissent s'exécuter
					$this['profile'] = NULL;
					return FALSE;
				}

			});

		$l->property('frozen')
			->addAfterCast('profile', function(?bool &$frozen): bool {

				if(in_array($this['profile'], Product::getProfiles('frozen')) === FALSE) {
					$frozen = NULL;
				}

				return TRUE;

			});

		$l->property('variety')
			->addAfterCast('profile', function(?string &$variety): bool {

				if(in_array($this['profile'], Product::getProfiles('variety')) === FALSE) {
					$variety = NULL;
				}

				return TRUE;

			});

		$l->property('plant')
			->addAfterCast('profile', function(\plant\Plant &$ePlant): bool {

				if(in_array($this['profile'], Product::getProfiles('plant')) === FALSE) {
					$ePlant = new \plant\Plant();
				}

				return TRUE;

			})
			->replaceCheck(function(\plant\Plant $ePlant): bool {

				return (
					$ePlant->empty() or (
						\plant\Plant::model()
							->select('structure')
							->get($ePlant) and
						$ePlant->canRead()
					)
				);

			});

		$l->property('compositionVisibility')
			->addAfterCast('profile', function(?string &$visibility): bool {

				if(in_array($this['profile'], Product::getProfiles('compositionVisibility')) === FALSE) {
					$visibility = NULL;
					return TRUE;
				} else {
					return in_array($visibility, [Product::PUBLIC, Product::PRIVATE]);
				}

			});

		$l->property('unit')
			->replaceCheck(function(Unit $eUnit): bool {

				$this->expects(['structure']);

				if($eUnit->empty()) {
					return TRUE;
				}

				return Unit::model()
					->whereStructure($this['structure'])
					->exists($eUnit);

			});

		$l->property('vat')
			->replaceCheck(function(?int $vat): bool {
				return array_key_exists($vat, ProductSetting::getVatRates($this['structure']));
			});

		$l->property('proOrPrivate')
			->replaceCheck(function(mixed $value, \PropertyList $l): bool {

				if($l->isBuilt('profile') === FALSE) {
					throw new \PropertySkip();
				}

				if($this['profile'] === Product::COMPOSITION) {
					return ((int)$this['pro'] + (int)$this['private']) === 1;
				} else {
					return TRUE;
				}

			});

		$l->property('privatePrice')
			->addAfterCast('channel', function (?float &$privatePrice): bool {

				if($this['private'] === FALSE) {
					$privatePrice = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('empty', function(?float &$value, \PropertyList $l) {

				if(
					$l->isBuilt('profile') === FALSE or
					$this['private'] === FALSE or
					$this['profile'] !== Product::COMPOSITION or
					$l->isInvalid('proOrPrivate')
				) {
					return TRUE;
				}

				return ($value !== NULL);

			});

		$l->property('privatePriceDiscount')
			->addAfterCast('number', function (?string &$privatePriceDiscount): bool {

				// ⚠️ `NULL` autant que `''` : un champ laissé vide poste la chaîne vide, un champ que le
				// formulaire ne rend pas ne poste rien. L'édition rapide du prix est le second cas, et
				// sans cette branche `(float) NULL` vaut 0, donc une remise à 100 %.
				if(
					$this['private'] === FALSE or
					$privatePriceDiscount === '' or
					$privatePriceDiscount === NULL
				) {
					$privatePriceDiscount = NULL;
				} else {
					$privatePriceDiscount = (float)$privatePriceDiscount;
				}

				return TRUE;

			})
			->addAfterCheck('value', function(?float $privatePriceDiscount, \PropertyList $l): bool {

				if($l->isBuilt('privatePrice') === FALSE) {
					return TRUE;
				}

				if($privatePriceDiscount === NULL) {
					return TRUE;
				}

				return $this['privatePrice'] > $privatePriceDiscount;

			})
			// Set les valeurs dans l'élement
			->addAfterCheck('setValue', function(?float $privatePriceDiscount, \PropertyList $l): bool {

				if($l->isBuilt('privatePrice') === FALSE) {
					throw new \PropertySkip();
				}

				// Reset du prix remisé, privatePrice a déjà été setté correctement
				if($privatePriceDiscount === NULL) {

					if($l->for === 'update' and $this['privatePriceInitial'] !== NULL) {

						$this['privatePriceInitial'] = NULL;
						$l->addBuilt('privatePriceInitial');

					}

					throw new \PropertySkip();
				}

				$this['privatePriceInitial'] = $this['privatePrice'];
				$this['privatePrice'] = $privatePriceDiscount;
				$l->addBuilt('privatePriceInitial');
				$l->addBuilt('privatePrice');

				throw new \PropertySkip();

			});

		$l->property('privatePriceInitial')
			->addAfterCheck('discount', function(?float $privatePriceInitial, \PropertyList $l): bool {

				$l->skipIfInvalid('privatePrice');

				return (
					$privatePriceInitial === NULL or
					$this['privatePrice'] < $privatePriceInitial
				);

			});

		$l->property('privatePriceSteps')
			// Avant le contrôle du cadriciel et par référence : trier la table et en sortir les
			// lignes vides, sans remplacer le contrôle de la colonne.
			->addAfterCast('format', fn(mixed &$steps) => ProductLib::checkPriceSteps($steps))
			// Après le contrôle : la comparaison au prix de base demande un élément, que le contrôle
			// de forme n'a pas, et elle doit tourner après la construction du prix.
			->addAfterCheck('consistency', function(array $steps, \PropertyList $l): bool {

				// Un prix refusé n'est jamais posé sur l'élément : le barème n'a plus rien à quoi se
				// comparer, et le refus qui compte est déjà levé sur le prix.
				$l->skipIfInvalid('privatePrice');

				return ProductLib::checkPriceStepsDecrease($steps, $this['privatePrice']);

			});

		$l->property('proPriceSteps')
			->addAfterCast('format', fn(mixed &$steps) => ProductLib::checkPriceSteps($steps))
			->addAfterCheck('consistency', function(array $steps, \PropertyList $l): bool {

				$l->skipIfInvalid('proPrice');

				return ProductLib::checkPriceStepsDecrease($steps, $this['proPrice']);

			});

		$l->property('proPackaging')
			->addAfterCast('profile', function(?float &$proPackaging): bool {

				if(in_array($this['profile'], Product::getProfiles('proPackaging')) === FALSE) {
					$proPackaging = NULL;
				}

				return TRUE;

			});

		$l->property('proPrice')
			->addAfterCast('channel', function (?float &$proPrice): bool {

				if($this['pro'] === FALSE) {
					$proPrice = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('empty', function(?float &$value, \PropertyList $l) {

				if(
					$l->isBuilt('profile') === FALSE or
					$this['pro'] === FALSE or
					$this['profile'] !== Product::COMPOSITION or
					$l->isInvalid('proOrPrivate')
				) {
					return TRUE;
				}

				return ($value !== NULL);

			});

		$l->property('proPriceDiscount')
			->addAfterCast('number', function (?string &$proPriceDiscount): bool {

				// ⚠️ `NULL` autant que `''` : un champ laissé vide poste la chaîne vide, un champ que le
				// formulaire ne rend pas ne poste rien. L'édition rapide du prix est le second cas, et
				// sans cette branche `(float) NULL` vaut 0, donc une remise à 100 %.
				if(
					$this['pro'] === FALSE or
					$proPriceDiscount === '' or
					$proPriceDiscount === NULL
				) {
					$proPriceDiscount = NULL;
				} else {
					$proPriceDiscount = (float)$proPriceDiscount;
				}

				return TRUE;

			})
			// Vérifie la cohérence des données
			->addAfterCheck('value', function(?float $proPriceDiscount, \PropertyList $l): bool {

				if($l->isBuilt('proPrice') === FALSE) {
					return TRUE;
				}

				if($proPriceDiscount === NULL) {
					return TRUE;
				}

				return $this['proPrice'] > $proPriceDiscount;

			})
			// Set les valeurs dans l'élement
			->addAfterCheck('setValue', function(?float $proPriceDiscount, \PropertyList $l): bool {

				if($l->isBuilt('proPrice') === FALSE) {
					throw new \PropertySkip();
				}

				// proPrice a déjà été setté
				if($proPriceDiscount === NULL) {

					if($l->for === 'update' and $this['proPriceInitial'] !== NULL) {

						$this['proPriceInitial'] = NULL;
						$l->addBuilt('proPriceInitial');

					}

					throw new \PropertySkip();
				}

				$this['proPriceInitial'] = $this['proPrice'];
				$this['proPrice'] = $proPriceDiscount;
				$l->addBuilt('proPriceInitial');
				$l->addBuilt('proPrice');

				throw new \PropertySkip();

			});

		$l->property('proPriceInitial')
			->addAfterCheck('discount', function(?float $proPriceInitial, \PropertyList $l): bool {

				$l->skipIfInvalid('proPrice');

				return (
					$proPriceInitial === NULL or
					$this['proPrice'] < $proPriceInitial
				);

			});

		$l->property('proOrPrivatePrice')
			->addAfterCheck('empty', function(mixed $value, \PropertyList $l) {

				if(
					$l->isInvalid('proPrice') === FALSE and
					$l->isInvalid('privatePrice') === FALSE and
					$l->isInvalid('proOrPrivate') === FALSE and
					($this['pro'] or $this['private'])
				) {
					return $this['proPrice'] !== NULL or $this['privatePrice'] !== NULL;
				} else {
					return TRUE;
				}

			});

		$l->property('proAccountNumber')
			->addAfterCheck('cashReceipts', self::checkAccountNumberCashReceipts('proAccountNumber'))
			->addAfterCheck('class', self::checkAccountNumber())
			->addAfterCheck('checkVatThirdParty', self::checkAccountNumberThirdParty())
			->addAfterCheck('breakdown', self::checkAccountNumberBreakdown());

		$l->property('privateAccountNumber')
			->addAfterCheck('cashReceipts', self::checkAccountNumberCashReceipts('privateAccountNumber'))
			->addAfterCheck('class', self::checkAccountNumber())
			->addAfterCheck('checkVatThirdParty', self::checkAccountNumberThirdParty())
			->addAfterCheck('breakdown', self::checkAccountNumberBreakdown());

		$l->property('duplicateOf')
			->replaceCheck(function(\product\Product $eProduct): bool {

				if($eProduct->notEmpty()) {

					return \product\Product::model()
						->whereStructure($this['structure'])
						->exists($eProduct);

				} else {
					return TRUE;
				}

			});

	}

	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		// Les valeurs que les étapes de variante lisent sur l'élément, posées ici pour qu'un
		// élément neuf les porte comme un élément chargé.
		$this['variantOf'] ??= new Product();
		$this['variants'] ??= 0;
		$this['eProductVariantOfOld'] = $this['variantOf'];

		$l->addStop('profile');

		parent::build($properties, $input, $l);

	}

	protected static function checkAccountNumber(): \Closure {

		return function(?string $accountNumber) {

			if($accountNumber === NULL) {
				return TRUE;
			}

			return (
				\account\AccountLabelLib::isFromClass($accountNumber, \account\AccountSetting::PRODUCT_ACCOUNT_CLASS) or
				\account\AccountLabelLib::isFromClass($accountNumber, \account\AccountSetting::CHARGE_ACCOUNT_CLASS) or
				\account\AccountLabelLib::isFromClass($accountNumber, \account\AccountSetting::ASSET_GENERAL_CLASS) or
				\account\AccountLabelLib::isFromClass($accountNumber, \account\AccountSetting::THIRD_PARTY_GENERAL_CLASS)
			);

		};

	}

	// Le compte déjà posé repart tel quel, pour qu'un passage en comptabilité le retrouve : seul un changement se refuse.
	protected static function checkAccountNumberCashReceipts(string $property): \Closure {

		return fn(?string $accountNumber): bool => (
			$this->acceptUpdateAccounting() or
			$accountNumber === ($this->exists() ? $this[$property] : NULL)
		);

	}

	protected static function checkAccountNumberBreakdown(): \Closure {

		return fn(?string $accountNumber) => \account\SaleBreakdownAiLib::isRefusedForStructure($this['structure'], $accountNumber) === FALSE;

	}

	protected static function checkAccountNumberThirdParty(): \Closure {

		return function(?string $accountNumber) {

			if(
				$accountNumber === NULL or
				\account\AccountLabelLib::isFromClass($accountNumber, \account\AccountSetting::THIRD_PARTY_GENERAL_CLASS) === FALSE or
				$this['structure']->conf()['hasVat'] === FALSE
			) {
				return TRUE;
			}

			$vatRate = (float)(ProductSetting::getVatRates($this['structure'])[$this['vat']] ?? 0.0);

			return ($vatRate === 0.0);

		};

	}

}
?>
