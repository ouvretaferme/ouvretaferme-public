<?php
namespace toolbox;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'CompositionPlan::season.check' => s("Merci de choisir le plan de culture que suivent vos paniers."),
			'CompositionPlan::season.configured' => s("Cette saison n'existe pas dans vos plans de culture."),
			'CompositionPlan::reference.check' => s("Merci de choisir d'où partent vos saisons."),
			'CompositionPlan::reference.start' => s("Merci de choisir d'où partent vos saisons."),
			'CompositionPlan::climate.check' => s("Merci de choisir un contexte climatique."),
			'CompositionPlan::climate.required' => s("Merci de choisir un contexte climatique."),
			'CompositionPlan::weeks.check' => s("Merci de choisir au moins une semaine de livraison."),
			'CompositionPlan::formulas.check' => s("Merci d'indiquer au moins un panier."),
			'CompositionPlan::synchronizeEmpty' => s("Cochez au moins une espèce."),
			'CompositionPlan::synchronizeListed' => s("Vos paniers ou votre plan de culture ont changé depuis l'ouverture de cette page. Rechargez-la avant d'appliquer le plan de culture."),

			'CompositionFormula::name.check' => s("Merci de donner un nom à ce panier."),
			'CompositionFormula::name.duplicate' => s("Un autre panier porte déjà ce nom."),
			'CompositionFormula::price.check' => s("Merci d'indiquer le prix du panier, un montant positif."),
			'CompositionFormula::baskets.check' => s("Merci d'indiquer combien vous en livrez chaque semaine."),
			'CompositionFormula::gap.check' => s("L'écart maximum doit être un pourcentage entier compris entre {min} et {max} %.", ['min' => CompositionFormula::model()->getPropertyRange('gap')[0], 'max' => CompositionFormula::model()->getPropertyRange('gap')[1]]),
			'CompositionFormula::linesMin.check' => s("Le nombre minimum de produits doit être un nombre entier compris entre {min} et {max}.", ['min' => CompositionFormula::model()->getPropertyRange('linesMin')[0], 'max' => CompositionFormula::model()->getPropertyRange('linesMin')[1]]),
			'CompositionFormula::linesMin.aboveMax' => s("Le nombre minimum de produits ne peut pas dépasser le nombre maximum."),
			'CompositionFormula::linesMax.check' => s("Le nombre maximum de produits doit être un nombre entier compris entre {min} et {max}.", ['min' => CompositionFormula::model()->getPropertyRange('linesMax')[0], 'max' => CompositionFormula::model()->getPropertyRange('linesMax')[1]]),
			'CompositionFormula::linesMax.belowMin' => s("Le nombre maximum de produits ne peut pas être inférieur au nombre minimum."),
			'CompositionFormula::linesMax.belowBase' => s("Un panier qui s'appuie sur un autre en reprend tous les produits, son nombre maximum de produits ne peut donc pas être inférieur au sien."),
			'CompositionFormula::price.order' => s("Un panier qui s'appuie sur un autre doit rester plus cher que lui."),
			'CompositionFormula::base.check' => s("Merci de choisir le panier sur lequel celui-ci s'appuie."),
			'CompositionFormula::base.plan' => s("Ce panier n'existe plus."),
			'CompositionFormula::base.self' => s("Un panier ne peut pas s'appuyer sur lui-même."),
			'CompositionFormula::base.chain' => s("Un panier ne peut pas s'appuyer sur un panier qui s'appuie déjà sur un autre, ni servir d'appui s'il en prend un."),
			'CompositionFormula::base.cheaper' => s("Choisissez un panier moins cher que celui-ci."),
			'CompositionFormula::base.lines' => s("Choisissez un panier qui n'a pas plus de produits au maximum que celui-ci."),

			'CompositionLine::species.check', 'CompositionLine::species.owned' => s("Merci de choisir une espèce."),
			'CompositionLine::species.unsold' => s("La communauté ne connait pas l'unité de cette espèce, qui ne peut donc entrer dans aucun panier."),
			'CompositionLine::species.duplicate' => s("Ce légume est déjà dans le panier de cette semaine."),
			'CompositionLine::species.twin' => s("Ce légume est déjà dans le panier de cette semaine, sous un autre nom."),
			'CompositionLine::quantity.check' => s("La quantité d'une ligne de panier doit être positive."),
			'CompositionLine::quantity.minimum' => s("Cette quantité est sous le minimum de l'espèce."),
			'CompositionLine::quantity.step' => s("Cette quantité doit être un multiple du pas de l'espèce."),
			'CompositionLine::unit.check' => s("Merci de choisir une unité."),
			'CompositionLine::unit.species' => s("Cette espèce entre au panier dans une autre unité."),
			'CompositionLine::unit.reference' => s("Cette unité n'est pas une unité de référence : les prix de la communauté ne s'y rapportent pas."),

			'CompositionSpecies::status.check' => s("Merci d'indiquer pour chaque espèce si vous la cultivez."),
			'CompositionSpecies::status.window' => s("Une saison de vente réglée à la main doit porter ses semaines."),
			'CompositionSpecies::windowWeeks.check' => s("Une saison de vente doit porter au moins une semaine de la saison."),
			'CompositionSpecies::windowWeeks.months' => s("Réglez la saison de cette espèce."),
			'CompositionSpecies::windowWeeks.delivered' => fn(\plant\Plant $ePlant) => s("La saison de {value} ne croise aucune semaine livrée. Retirez cette espèce, puis ajoutez-la de nouveau pour régler sa saison.", encode($ePlant['name'])),
			'CompositionSpecies::windowWeeks.undated' => fn(\plant\Plant $ePlant) => s("Aucune saison n'est connue pour {value} avec ce choix. Retirez cette espèce, puis ajoutez-la de nouveau pour régler sa saison.", encode($ePlant['name'])),
			'CompositionSpecies::windowWeeks.short' => s("Une période de vente dure au moins trois semaines. Allongez-la ou retirez-la."),
			'CompositionSpecies::plant.addable' => s("Cette espèce ne peut pas être ajoutée à vos paniers."),
			'CompositionSpecies::plant.duplicate' => s("Cette espèce est déjà dans vos paniers sous ce nom."),
			'CompositionSpecies::name.check' => s("Ce nom est trop long."),
			'CompositionSpecies::name.required' => s("Donnez un nom à cette variante."),
			'CompositionSpecies::name.duplicate' => s("Une variante de cette espèce porte déjà ce nom."),
			'CompositionSpecies::unit.chosen' => s("Choisissez l'unité de cette variante."),
			'CompositionSpecies::frequency.seasonal' => s("Choisissez la fréquence de cette variante."),
			'CompositionSpecies::quantityBasket.check' => s("La quantité par panier doit être positive."),
			'CompositionSpecies::quantityBasket.required' => s("Indiquez la quantité que vous mettez dans un panier."),
			'CompositionSpecies::quantityBasket.multiple' => s("La quantité par panier doit être un multiple du pas de son unité."),
			'CompositionSpecies::price.required' => s("Indiquez le prix de cette espèce."),
			'CompositionSpecies::price.check' => s("Le prix d'une espèce doit être un montant positif."),
			'CompositionSpecies::step.check' => s("Le pas d'une espèce doit être une quantité positive."),
			'CompositionSpecies::step.whole' => s("Cette espèce entre au panier dans une unité qui ne se coupe pas, son pas est un nombre entier."),
			'CompositionSpecies::step.minimum' => s("La quantité minimale de cette espèce doit rester un multiple du pas. Changez d'abord la quantité minimale."),
			'CompositionSpecies::minimum.check' => s("La quantité minimale d'une espèce doit être positive."),
			'CompositionSpecies::minimum.multiple' => s("La quantité minimale doit être un multiple du pas."),
			'CompositionSpecies::quantity.check' => s("La quantité par défaut d'une espèce doit être positive."),
			'CompositionSpecies::quantity.minimum' => s("La quantité par défaut ne peut pas être inférieure à la quantité minimale."),
			'CompositionSpecies::quantity.multiple' => s("La quantité par défaut doit être un multiple du pas."),
			'CompositionSpecies::weight.check' => s("Le poids d'une pièce ou d'une botte doit être un poids positif, en kilos."),
			'CompositionSpecies::weight.weighed' => s("Seule une espèce qui entre au panier à la pièce ou à la botte a un poids à régler."),
			'CompositionSpecies::weight.missing' => s("Indiquez le poids d'une unité, que ni vos cultures ni Ouvretaferme ne donnent."),
			'CompositionSpecies::unit.check', 'CompositionSpecies::unit.offered' => s("Choisissez une des unités proposées."),
			'CompositionSpecies::frequency.check' => s("Choisissez une des fréquences proposées, ou laissez-la selon la saison."),
			'CompositionSpecies::product.check', 'CompositionSpecies::product.candidate' => s("Choisissez un des produits proposés pour ce légume."),
			'CompositionSpecies::product.none' => s("Choisissez le produit vendu d'au moins un légume pour préparer la composition."),

			'Equipment::tool.check' => s("Ce matériel n'est pas un matériel de Mon matériel que le carnet peut suivre."),
			'Equipment::tool.unvalidated' => s("Choisissez dans la liste un matériel déjà enregistré, ou tapez le nom d'un nouveau matériel puis cliquez sur « Valider » dans la liste."),
			'Equipment::tool.unselected' => s("Choisissez dans la liste un matériel déjà enregistré. Seul un gestionnaire de la structure peut en ajouter un nouveau."),
			'Equipment::tool.new' => s("Seul un gestionnaire de la structure peut ajouter un matériel à Mon matériel."),
			'Equipment::tool.newApi' => s("Un matériel se crée par POST tool, puis se suit en donnant son identifiant."),
			'Equipment::tool.followed' => s("Ce matériel est déjà dans le carnet."),
			'Equipment::meter.check' => s("Merci d'indiquer si ce matériel a un compteur, en heures ou en kilomètres."),
			'Equipment::meter.locked' => s("Les valeurs de compteur de ce matériel sont dans l'autre unité, qui ne peut plus changer."),
			'Equipment::registration.check' => s("L'immatriculation ne doit pas dépasser {value} caractères.", Equipment::model()->getPropertyRange('registration')[1]),
			'Equipment::status.check' => s("Ce statut n'existe pas pour un matériel suivi."),
			'Equipment::status.deleted' => s("Un matériel suivi ne passe qu'au statut inactive, qui arrête son suivi."),

			'EquipmentReading::equipment.check', 'EquipmentReading::equipment.owned' => s("Ce matériel ne reçoit pas de relevé."),
			'EquipmentReading::date.check' => s("Merci d'indiquer le jour du relevé."),
			'EquipmentReading::date.future' => s("Un relevé ne peut pas porter sur un jour à venir."),
			'EquipmentReading::value.check' => s("Merci d'indiquer ce que le compteur affiche."),
			'EquipmentReading::value.number' => s("Le compteur doit être un nombre entier positif."),
			'EquipmentReading::value.backwards' => s("Le compteur est plus bas qu'un relevé précédent."),
			'EquipmentReading::value.ahead' => s("Le compteur dépasse un relevé plus récent."),

			'Favorite::tool.check' => s("Cet outil n'existe pas."),
			'Favorite::tool.tooMany' => s("Le menu porte déjà {value} outils, retirez-en un avant d'en ajouter un autre.", FavoriteLib::MAX),
			'Favorite::tool.closed' => s("Cet outil ne vous est pas ouvert."),

			'Gauge::zone.check' => s("Merci de choisir où est le pluviomètre."),
			'Gauge::zone.chosen' => s("Merci de choisir où est le pluviomètre."),
			'Gauge::zone.owned' => s("Cette parcelle n'est pas celle de la ferme."),
			'Gauge::zone.placed' => s("Un pluviomètre est déjà posé à cet endroit."),
			'Gauge::status.check' => s("Ce pluviomètre ne peut pas être modifié."),
			'Gauge::status.deleted' => s("Ce pluviomètre ne peut pas être modifié."),

			'Maintenance::equipment.check', 'Maintenance::equipment.owned' => s("Ce matériel n'est pas suivi dans le carnet."),
			'Maintenance::name.check' => s("Merci de nommer l'entretien."),
			'Maintenance::everyValue.check' => s("La fréquence au compteur doit être un nombre entier compris entre {min} et {max}.", ['min' => Maintenance::model()->getPropertyRange('everyValue')[0], 'max' => \util\TextUi::number(Maintenance::model()->getPropertyRange('everyValue')[1], 0)]),
			'Maintenance::everyValue.meter', 'Maintenance::plannedValue.meter' => s("Ce matériel n'a pas de compteur."),
			'Maintenance::everyMonths.check' => s("Le nombre de mois doit être un nombre entier compris entre {min} et {max}.", ['min' => Maintenance::model()->getPropertyRange('everyMonths')[0], 'max' => Maintenance::model()->getPropertyRange('everyMonths')[1]]),
			'Maintenance::everyMonths.frequency' => s("Merci d'indiquer quand revient cet entretien, au compteur ou en mois."),
			'Maintenance::plannedDate.check' => s("Merci d'indiquer une date valide."),
			'Maintenance::plannedDate.planned' => s("Merci d'indiquer quand faire cet entretien, à une date ou au compteur."),
			'Maintenance::plannedValue.check' => s("Le compteur doit être un nombre entier positif."),
			'Maintenance::status.check' => s("Ce statut n'existe pas pour un entretien."),
			'Maintenance::status.deleted' => s("Un entretien ne passe qu'au statut deleted, qui le retire du carnet."),

			'MaintenanceConfiguration::action.check', 'MaintenanceConfiguration::action.usable' => s("Choisissez l'intervention que prend un entretien fait au planning."),
			'MaintenanceConfiguration::readingEveryMonths.check' => s("Choisissez la fréquence de rappel des compteurs."),
			'WaterConfiguration::readingEveryMonths.check' => s("Choisissez la fréquence de rappel des compteurs d'eau."),

			'MaintenanceDone::equipment.check', 'MaintenanceDone::equipment.owned' => s("Ce matériel n'est pas suivi dans le carnet."),
			'MaintenanceDone::maintenance.check', 'MaintenanceDone::maintenance.owned' => s("Cet entretien n'est pas un entretien à faire de ce matériel."),
			'MaintenanceDone::name.check' => s("Le libellé de l'entretien ne doit pas dépasser 60 caractères."),
			'MaintenanceDone::name.required' => s("Merci de décrire l'entretien."),
			'MaintenanceDone::date.check' => s("Merci d'indiquer le jour de l'entretien."),
			'MaintenanceDone::date.future' => s("Un entretien fait ne peut pas porter sur un jour à venir."),
			'MaintenanceDone::value.check', 'MaintenanceDone::value.number' => s("Le compteur doit être un nombre entier positif."),
			'MaintenanceDone::value.meter' => s("Ce matériel n'a pas de compteur."),
			'MaintenanceDone::value.required' => s("Merci d'indiquer le compteur."),
			'MaintenanceDone::value.backwards' => s("Le compteur est plus bas qu'un relevé d'avant ce jour."),
			'MaintenanceDone::value.ahead' => s("Le compteur dépasse un relevé plus récent."),
			'MaintenanceDone::cost.check' => s("Le coût doit être un montant positif, de moins d'un million d'euros."),
			'MaintenanceDone::comment.check' => s("La note est trop longue."),
			'MaintenanceDone::photo.check', 'MaintenanceDone::photo.owned' => s("La photo n'a pas pu être enregistrée, merci de la déposer à nouveau."),

			'Rain::gauge.check' => s("Merci de choisir le pluviomètre."),
			'Rain::gauge.owned' => s("Ce pluviomètre n'est pas celui de la ferme."),
			'Rain::date.check' => s("Merci d'indiquer le jour du relevé."),
			'Rain::date.future' => s("Un relevé ne peut pas porter sur un jour à venir."),
			'Rain::rain.check' => s("Merci d'indiquer la pluie tombée, en mm."),
			'Rain::rain.exceeds' => s("Vous ne pouvez pas retirer plus de pluie que ce jour n'en a reçu."),
			'Rain::rain.negative' => s("Une quantité négative ne s'importe pas, le retrait de pluie se fait depuis la saisie du jour."),
			'Rain::date.duplicate' => s("Ce jour figure déjà plus haut dans le fichier."),
			'Rain::importMissing' => s("Le fichier n'est plus disponible, merci de le choisir à nouveau."),

			'WaterMeter::name.check' => s("Merci de donner un nom au compteur, en 50 caractères au plus."),
			'WaterMeter::name.duplicate' => s("Un autre compteur porte déjà ce nom."),
			'WaterMeter::digits.check' => s("Le nombre de chiffres d'un compteur est compris entre {min} et {max}.", ['min' => WaterMeter::model()->getPropertyRange('digits')[0], 'max' => WaterMeter::model()->getPropertyRange('digits')[1]]),
			'WaterMeter::digits.readings' => s("Un relevé de ce compteur compte déjà plus de chiffres."),
			'WaterMeter::status.check' => s("Ce compteur ne peut pas être modifié."),
			'WaterMeter::status.deleted' => s("Ce compteur ne peut pas être modifié."),

			'WaterReading::meter.check' => s("Merci de choisir le compteur."),
			'WaterReading::meter.owned' => s("Ce compteur n'est pas un compteur de la ferme."),
			'WaterReading::date.check' => s("Merci d'indiquer le jour du relevé."),
			'WaterReading::date.future' => s("Un relevé ne peut pas porter sur un jour à venir."),
			'WaterReading::value.check' => s("Merci d'indiquer ce que le compteur affiche, en m³."),
			'WaterReading::value.number' => s("Merci d'indiquer ce que le compteur affiche, en m³."),
			'WaterReading::value.digits' => s("Ce relevé compte plus de chiffres que le compteur n'en affiche."),
			'WaterReading::changedValue.check' => s("Merci d'indiquer ce que l'ancien compteur affichait, en m³."),
			'WaterReading::changedValue.number' => s("Merci d'indiquer ce que l'ancien compteur affichait, en m³."),
			'WaterReading::changedValue.digits' => s("Ce relevé compte plus de chiffres que l'ancien compteur n'en affichait."),

			'FertilizationMaterial::nitrogen.check' => s("La teneur en azote est un pourcentage positif."),
			'FertilizationMaterial::phosphate.check' => s("La teneur en phosphore est un pourcentage positif."),
			'FertilizationMaterial::potash.check' => s("La teneur en potassium est un pourcentage positif."),

			'FertilizationYield::nitrogen.check' => s("L'azote mobilisé est une quantité réaliste en kg/ha, positive ou nulle."),
			'FertilizationYield::phosphate.check' => s("Le phosphore mobilisé est une quantité réaliste en kg/ha, positive ou nulle."),
			'FertilizationYield::potash.check' => s("Le potassium mobilisé est une quantité réaliste en kg/ha, positive ou nulle."),

			default => NULL

		};

	}

	/**
	 * `$options` porte ce qu'un message dit de plus que son geste : seuls le tour et le changement d'un
	 * compteur d'eau en ont, et un rechargement hors ajax les perd en route (`ReloadAction::run()`), d'où
	 * leurs deux phrases.
	 */
	public static function getSuccess(string $fqn, array $options = []): ?string {

		return match($fqn) {

			'Equipment::followed' => s("Le matériel est entré dans le carnet."),
			'Equipment::forgotten' => s("Le matériel est sorti du carnet, son historique est conservé."),
			'Equipment::readingSkipped' => s("Le relevé est passé, le compteur reviendra au prochain rappel."),
			'Equipment::readingPostponed' => s("Le relevé est décalé à la semaine suivante."),

			'EquipmentReading::created' => s("Le compteur a bien été relevé."),
			'EquipmentReading::updated' => s("Le relevé a bien été corrigé."),
			'EquipmentReading::deleted' => s("Le relevé a bien été supprimé."),

			'Gauge::created' => s("Le pluviomètre a bien été posé."),
			'Gauge::updated' => s("Le lieu du pluviomètre a bien été modifié."),
			'Gauge::deleted' => s("Le pluviomètre a bien été supprimé."),

			'Maintenance::created' => s("L'entretien a bien été planifié."),
			'Maintenance::updated' => s("L'entretien a bien été modifié."),
			'Maintenance::deleted' => s("L'entretien a bien été supprimé."),
			'Maintenance::ignored' => s("Le retard est ignoré jusqu'à la prochaine échéance."),
			'Maintenance::skipped' => s("L'échéance est passée, l'entretien reviendra à la suivante."),
			'Maintenance::postponed' => s("L'entretien est décalé à la semaine suivante."),

			'MaintenanceConfiguration::created' => s("Le carnet d'entretien a bien été paramétré."),
			'WaterConfiguration::created' => s("La consommation d'eau a bien été paramétrée."),

			'MaintenanceDone::created' => s("L'entretien fait a bien été noté."),
			'MaintenanceDone::createdIntervention' => s("L'entretien a bien été noté."),
			'MaintenanceDone::updated' => s("L'entretien fait a bien été modifié."),
			'MaintenanceDone::deleted' => s("L'entretien fait a bien été supprimé."),

			'Rain::created' => s("La pluie a bien été ajoutée."),
			'Rain::removed' => s("La pluie a bien été retirée."),
			'Rain::updated' => s("Le relevé a bien été modifié."),
			'Rain::deleted' => s("Le relevé a bien été supprimé."),
			'Rain::imported' => s("Les relevés ont bien été importés."),

			'Treatment::productUpdated' => s("Le produit a bien été enregistré."),
			'Treatment::productReprise' => s("Les traitements ont reçu leur produit."),

			'Tool::forgotten' => s("L'outil a bien été retiré de vos outils."),
			'Tool::forgottenBasket' => s("Les paniers et leurs réglages ont bien été supprimés."),

			'WaterMeter::created' => s("Le compteur a bien été ajouté."),
			'WaterMeter::updated' => s("Le compteur a bien été paramétré."),
			'WaterMeter::deleted' => s("Le compteur a bien été supprimé."),
			'WaterMeter::readingSkipped' => s("Le relevé est passé, le compteur reviendra au prochain rappel."),
			'WaterMeter::readingPostponed' => s("Le relevé est décalé à la semaine suivante."),

			'WaterReading::created' => s("Le relevé a bien été ajouté."),
			'WaterReading::turned' => ($options === []) ?
				s("Le compteur a fait un tour.") :
				s("Le compteur a fait un tour, {value} m³ comptés depuis le {date}.", ['value' => WaterReadingUi::getNumber($options['consumption']), 'date' => WaterReadingUi::getDay($options['date'])]),
			'WaterReading::alerted' => ($options === []) ?
				s("Le relevé est enregistré, avec un écart inhabituel. Vérifiez-le, ou déclarez le nouveau compteur s'il a été changé.") :
				s("Le relevé est enregistré, avec {value} m³ comptés depuis le {date}. Vérifiez-le, ou déclarez le nouveau compteur s'il a été changé.", ['value' => WaterReadingUi::getNumber($options['consumption']), 'date' => WaterReadingUi::getDay($options['date'])]),
			'WaterReading::changed' => ($options === []) ?
				s("Le compteur est changé.") :
				s("Le compteur est changé, les relevés repartent de {value} m³.", WaterReadingUi::getNumber($options['value'])),
			'WaterReading::updated' => s("Le relevé a bien été modifié."),
			'WaterReading::deleted' => s("Le relevé a bien été supprimé."),

			default => NULL

		};

	}

}
?>
