<?php
namespace work;

class AlertUi {

	public static function getError(string $fqn, array $options): mixed {

		return match($fqn) {

			'Action::deleteMandatory' => s("Cette intervention ne peut pas être supprimée car elle est indispensable au bon fonctionnement du site."),
			'Action::deleteUsed' => s("Vous ne pouvez pas supprimer une action qui est déjà utilisée sur les itinéraires techniques ou les séries..."),

			'Category::deleteMandatory' => s("Cette catégorie ne peut pas être supprimée car elle est indispensable au bon fonctionnement du site."),
			'Category::deleteUsed' => s("Vous ne pouvez pas supprimer une catégorie à laquelle sont toujours affectées des interventions..."),
			'Category::limitReached' => s("Vous avez atteint la limite de catégories que vous pouvez créer !"),

			'Method::action.check', 'Method::action.owned' => s("Intervention non reconnue sur votre ferme."),

			'Supplier::deleteUsed' => s("Vous ne pouvez pas supprimer ce fournisseur de semences qui est actuellement utilisé sur un ou plusieurs variétés..."),

			'System::place.check' => s("Veuillez sélectionner une ville dans le menu déroulant."),

			'Tool::name.check' => s("Vous devez indiquer un nom."),
			'Tool::name.duplicate' => s("Vous avez déjà créé un élément de même nom."),
			'Tool::status.check' => s("Ce statut n'existe pas pour un matériel."),
			'Tool::stock.check' => s("Le stock doit être un nombre entier compris entre {min} et {max}.", ['min' => Tool::model()->getPropertyRange('stock')[0], 'max' => Tool::model()->getPropertyRange('stock')[1]]),
			'Tool::stock.followed' => s("Un matériel suivi au carnet d'entretien du matériel n'a pas de stock."),
			'Tool::routineValue.tray' => s("Vous devez indiquer un nombre de mottes par plateau."),
			'Tool::routineValue.phytoAmm' => s("Ce produit n'est plus dans le catalogue E-Phy."),
			'Tool::routineValue.phytoDose' => s("Vous devez indiquer une dose par défaut."),
			'Tool::routineValue.phytoWater' => s("Le volume de bouillie doit être un nombre de litres par hectare."),
			'Tool::routineValue.sprayer' => s("Vous devez indiquer la contenance de la cuve."),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Action::created' => s("L'intervention a bien été créée."),
			'Action::updated' => s("L'intervention a bien été mise à jour."),
			'Action::updatedActive' => s("L'intervention a bien été réactivée."),
			'Action::updatedInactive' => s("L'intervention a bien été désactivée."),
			'Action::deleted' => s("L'intervention a bien été supprimée."),

			'Method::created' => s("La méthode a bien été créée."),
			'Method::deleted' => s("La méthode a bien été supprimée."),

			'Category::created' => s("La catégorie a bien été créée."),
			'Category::updated' => s("La catégorie a bien été mise à jour."),
			'Category::deleted' => s("La catégorie a bien été supprimée."),

			'Tool::created' => s("L'élément a bien été créé."),
			'Tool::updated' => s("L'élément a bien été mis à jour."),
			'Tool::deleted' => s("L'élément a bien été supprimé."),

			'Supplier::created' => s("Le fournisseur a bien été créé."),
			'Supplier::updated' => s("Le fournisseur a bien été mis à jour."),
			'Supplier::deleted' => s("Le fournisseur a bien été supprimé."),

			default => null

		};


	}

}
?>
