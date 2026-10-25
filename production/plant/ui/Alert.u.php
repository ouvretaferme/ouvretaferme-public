<?php
namespace plant;

class AlertUi {

	public static function getError(string $fqn, array $options = []): mixed {

		return match($fqn) {


			'Family::deleteUsed'=> s("Vous ne pouvez pas supprimer cette famille car des espèces y sont encore rattachées."),

			'Forecast::plant.duplicate' => s("Vous avez déjà ajouté une ligne dans le prévisionnel pour cette espèce et cette unité de vente."),
			'Forecast::proPart.consistency' => s("Le total de la répartition des ventes doit être de 100 %."),
			'Forecast::deleteUsed' => s("Vous ne pouvez pas supprimer du prévisionnel une espèce pour laquel des séries ont déjà été créées."),

			'Variety::name.duplicate' => s("Vous avez déjà créé une variété de ce nom pour cette espèce."),
			'Variety::deleteUsed'=> s("Vous ne pouvez pas supprimer cette variété car elle est utilisée dans une série ou un itinéraire technique."),
			'Variety::unit.check' => s("Cette unité de saisie n'existe pas."),
			'Variety::stock.cast' => s("La saisie en grammes demande de connaître le poids de mille graines de cette variété."),
			'Variety::stock.check' => s("Le stock de semences doit être un nombre entier de graines, à partir de zéro."),

			'Size::name.duplicate' => s("Vous avez déjà créé un calibre de ce nom pour cette espèce."),
			'Size::deleteUsed'=> s("Vous ne pouvez pas supprimer ce calibre car il est utilisé dans une série."),

			'Plant::criteria.tooMany' => s("Vous ne pouvez pas retenir plus de {value} critères de notation pour une espèce.", PlantSetting::PLANT_CRITERIA_MAX),
			'Plant::deleteUsed'=> s("Vous ne pouvez pas supprimer cette espèce car elle est encore utilisée dans une série, un itinéraire technique, une intervention ou pour une variété."),
			'Plant::deleteComposition' => s("Vous ne pouvez pas supprimer cette espèce tant que vos paniers la livrent."),
			'Plant::name.check' => s("Donnez un nom à l'espèce."),
			'Plant::name.duplicate' => s("Vous avez déjà créé une espèce de ce nom."),
			'Plant::crateWeight.check' => s("Le poids d'une caisse se donne en kilos, de {min} à {max}.", array_combine(['min', 'max'], array_map(fn($value) => \util\TextUi::number($value, NULL), Plant::model()->getPropertyRange('crateWeight')))),
			'Plant::crateWeight.decimal' => s("Le poids d'une caisse est un nombre de kilos, par exemple 6 ou 6,5."),
			'Plant::crateWeight.missing' => s("Indiquez le poids d'une caisse pour saisir la récolte en caisses."),
			'Plant::crateWeight.denied' => s("Le poids d'une caisse de cette espèce n'est pas encore réglé, et vous n'avez pas les droits pour le régler."),
			'Plant::storage.check' => s("La durée de conservation est un nombre entier de semaines, de {min} à {max}.", array_combine(['min', 'max'], Plant::model()->getPropertyRange('storage'))),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Plant::created' => s("L'espèce a bien été créée."),
			'Plant::updated' => s("L'espèce a bien été mise à jour."),
			'Plant::updatedActive' => s("L'espèce a bien été réactivée."),
			'Plant::updatedInactive' => s("L'espèce a bien été désactivée."),
			'Plant::deleted' => s("L'espèce a bien été supprimée."),

			'Forecast::created' => s("L'espèce a bien été ajoutée au prévisionnel."),
			'Forecast::updated' => s("Le prévisionnel a bien été mis à jour."),
			'Forecast::deleted' => s("L'espèce a bien été supprimée du prévisionnel."),
			
			'Family::updated' => s("La famille a bien été mise à jour."),
			'Family::deleted' => s("La famille a bien été supprimée."),
			
			'Variety::created' => s("La variété a bien été ajoutée !"),
			'Variety::updated' => s("La variété a bien été mise à jour."),
			'Variety::deleted' => s("La variété a bien été supprimée."),
			'Variety::rated' => s("La note a bien été enregistrée."),
			'Variety::ratingDeleted' => s("Votre avis sur cette variété a bien été supprimé."),

			'Size::created' => s("Le calibre a bien été ajouté !"),
			'Size::updated' => s("Le calibre a bien été mis à jour."),
			'Size::deleted' => s("Le calibre a bien été supprimé."),

			default => null

		};

	}

}
?>
