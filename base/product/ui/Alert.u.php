<?php
namespace product;

class AlertUi {

	use \Notifiable;

	public static function getError(string $fqn, array $options): mixed {

		return match($fqn) {

			'Product::privatePriceSteps.format' => s("Vos paliers de prix particulier doivent porter une quantité et un prix, trois au maximum, et deux paliers ne peuvent pas démarrer à la même quantité."),
			'Product::proPriceSteps.format' => s("Vos paliers de prix professionnel doivent porter une quantité et un prix, trois au maximum, et deux paliers ne peuvent pas démarrer à la même quantité."),
			'Product::privatePriceSteps.consistency' => s("Une remise sur volume descend : chaque palier doit être moins cher que le prix particulier de base, et moins cher que le palier qui le précède."),
			'Product::proPriceSteps.consistency' => s("Une remise sur volume descend : chaque palier doit être moins cher que le prix professionnel de base, et moins cher que le palier qui le précède."),

			'Product::name.check' => s("Veuillez indiquer un nom pour ce produit."),
			'Product::profile.check' => s("Veuillez choisir le type de ce produit."),
			'Product::privatePrice.check' => s("Veuillez indiquer un prix valide pour ce produit."),

			'Product::compositionVisibility.check', 'Product::compositionVisibility.profile' => s("Veuillez indiquer si la composition de votre produit composé est affichée à vos clients ou non"),
			'Product::proOrPrivate.check' => s("Veuillez déterminer si votre produit composé est vendu aux clients particuliers ou aux clients professionnels. Il ne peut pas à la fois être vendu aux professionnels et aux particuliers"),
			'Product::proOrPrivatePrice.empty' => s("Veuillez indiquer au moins un prix pour ce produit"),
			'Product::privatePrice.empty' => s("Veuillez indiquer le prix de ce produit vendu aux particuliers"),
			'Product::proPrice.empty' => s("Veuillez indiquer le prix de ce produit vendu aux professionnels"),
			'Product::privatePriceDiscount.value' => s("Le prix remisé doit être plus avantageux que le prix de base"),
			'Product::privatePrice.value' => s("Le prix de base doit être plus élevé que le prix remisé"),
			'Product::privatePriceInitial.value' => s("Le prix de base doit être plus élevé que le prix remisé"),
			'Product::proPriceDiscount.value' => s("Le prix remisé doit être plus avantageux que le prix de base"),
			'Product::proPrice.value' => s("Le prix de base doit être plus élevé que le prix remisé"),
			'Product::proPriceInitial.value' => s("Le prix de base doit être plus élevé que le prix remisé"),
			'Product::reference.check', 'Product::reference.format' => s("La référence ne peut contenir que des chiffres, des lettres ou des tirets"),
			'Product::preparationDays.check' => s("Le blocage des commandes se compte en jours avant la livraison"),
			'Product::preparationTime.check', 'Product::preparationTime.missing' => s("Veuillez indiquer l'heure du blocage des commandes"),
			'Product::reference.duplicate' => s("Cette référence est déjà utilisée pour un autre produit"),
			'Product::organic.check' => s("Veuillez indiquer le statut du produit vis-à-vis de l'agriculture biologique"),
			'Product::privateAccountNumber.checkVatThirdParty' => fn(Product $eProduct, ?string $accountNumber) => s("Avec le numéro de compte {value}, le taux de TVA doit être zéro", $accountNumber),
			'Product::proAccountNumber.checkVatThirdParty' => fn(Product $eProduct, ?string $accountNumber) => s("Avec le numéro de compte {value}, le taux de TVA doit être zéro", $accountNumber),
			'Product::privateAccountNumber.cashReceipts', 'Product::proAccountNumber.cashReceipts' => s("Au livre des recettes, toutes vos ventes vont au compte de ventes, vous ne pouvez donc pas choisir de numéro de compte pour un produit."),
			'Product::privateAccountNumber.breakdown', 'Product::proAccountNumber.breakdown' => s("Choisissez un compte de 7011 à 7014 ou une de leurs subdivisions, conformément au plan comptable agricole"),

			'Product::variantOf.check' => s("Ce produit ne peut pas être rattaché à celui-ci : le produit principal doit avoir les variantes activées, appartenir à la même structure, avoir le même type, ne pas être lui-même une variante et ne pas avoir été supprimé"),
			'Product::variantOf.last' => s("Ce produit est la dernière variante de son produit principal : désactivez les variantes plutôt que de la détacher"),
			'Product::variantList.check' => s("Une des variantes envoyées n'appartient pas à ce produit"),
			'Product::variantOf.subscription', 'Product::variantList.subscription' => self::getFeatureMessage(\structure\Structure::FEATURE_VARIANTS),
			'Product::variantForbidden' => s("Les comptes comptables se posent sur le produit principal, pas sur une variante"),
			'Product::unit.check' => s("Cette unité n'appartient pas à votre structure"),
			'Product::deleteVariants' => s("Ce produit ne peut pas être supprimé tant qu'il a des variantes"),
			'Product::deleteLastVariant' => s("La dernière variante d'un produit ne peut pas être supprimée : désactivez plutôt les variantes"),
			'Product::attachFirst.product' => s("Ce produit ne peut pas devenir une variante de celui-ci : il doit appartenir à la même structure, être du même type, ne pas être déjà une variante ni porter lui-même des variantes"),
			'Product::enableVariants' => s("Les variantes ne peuvent être activées que sur un produit qui n'est pas une variante et qui n'en porte pas déjà"),
			'Product::disableVariants' => s("Les variantes ne peuvent être désactivées que lorsqu'il ne reste qu'une seule variante"),
			'Product::separatePrices' => s("Les prix ne peuvent être séparés que sur un produit principal en prix commun"),
			'Product::updatePositions' => s("L'ordre des variantes ne se change que sur un produit principal qui en porte au moins deux"),
			'Product::updatePositionsIncomplete' => s("L'ordre envoyé ne correspond pas aux variantes de ce produit : rechargez la page et recommencez"),
			'Product::unifyPrices' => s("Les prix ne peuvent être unifiés que sur un produit principal dont les variantes ont chacune leurs prix"),
			'Product::unifyPricesGrid' => s("Les prix personnalisés en conflit ne sont plus ceux qui vous ont été présentés, ou l'un d'eux est resté sans choix : rouvrez l'unification des prix de ce produit"),

			'Stock::balance.check' => s("Merci d'indiquer une valeur supérieure ou égale à zéro"),
			'Stock::balance.negative' => s("Le stock ne peut pas être négatif"),
			'Stock::quantity.check' => s("Merci d'indiquer une quantité supérieure ou égale à zéro"),

			'Unit::singular.duplicate' => s("Il y a déjà une unité avec le même nom"),
			'Unit::deleteUsed' => s("Cette unité ne peut pas être supprimée car elle est utilisée dans une vente ou pour un produit"),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn, array $options = []): ?string {

		return match($fqn) {

			'Category::created' => s("La catégorie a bien été créée."),
			'Category::updated' => s("La catégorie a bien été mise à jour."),
			'Category::deleted' => s("La catégorie a bien été supprimée."),

			'Product::created' => s("Le produit a bien été ajouté."),
			'Product::variantCreated' => s("La variante a bien été ajoutée."),
			'Product::variantAttached' => s("Le produit est désormais une variante de ce produit."),
			'Product::variantDetached' => s("La variante est désormais un produit autonome."),
			'Product::updated' => s("Le produit a bien été mis à jour."),
			'Product::variantsEnabled' => s("La variante a bien été ajoutée"),
			'Product::variantsDisabled' => s("Ce produit n'a désormais plus de variante."),
			'Product::pricesSeparated' => s("Chaque variante porte désormais ses propres prix !"),
			'Product::pricesUnified' => s("Toutes les variantes portent désormais les prix du produit principal !"),
			'Product::updatedSeveral' => s("Les produits ont bien été mis à jour."),
			'Product::deleted' => s("Le produit a bien été supprimé."),
			'Product::variantDeleted' => s("La variante a bien été supprimée."),
			'Product::categoryUpdated' => s("La catégorie a bien été modifiée."),

			'Stock::updated' => s("Le stock a bien été mis à jour pour ce produit."),
			'Stock::enabled' => s("Le suivi du stock a bien été activé pour ce produit et vous le retrouverez dans l'onglet de gestion du stock."),
			'Stock::disabled' => s("Le suivi du stock a bien été désactivé pour ce produit."),

			'Unit::created' => s("L'unité a bien été créée."),
			'Unit::updated' => s("L'unité a bien été mise à jour."),
			'Unit::deleted' => s("L'unité a bien été supprimée."),

			default => NULL

		};

	}

	// Le texte vit avec les offres, dans le package d'abonnement. Sans lui, acceptFeature() accepte
	// tout et aucune étape ne lève ce refus.
	private static function getFeatureMessage(string $feature): string {

		if(\structure\StructureSetting::hasSubscriptionPackage() === FALSE) {
			throw new \NotExpectedAction('No subscription refusal without the subscription package');
		}

		return self::notify('getFeatureMessage', $feature)[0];

	}

}
?>
