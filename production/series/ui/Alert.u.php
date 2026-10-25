<?php
namespace series;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'csvVariety' => s("Dans votre fichier CSV, chaque colonne <i>variety_name</i> doit être suivie par une colonne <i>variety_part</i>."),

			'Series::plantsCheck' => s("Veuillez sélectionner au moins une espèce !"),
			'Series::plantsDuplicate' => s("Vous ne pouvez pas ajouter deux fois la même espèce à une série !"),
			'Series::cropsCheck' => s("Merci d'indiquer à quelle place de la succession chaque espèce se cultive."),
			'Series::perennialLifetime.consistency' => s("La durée de vie de la culture ne peut pas être réduite autant car elle a été mise en place depuis plus longtemps"),
			'Series::duplicatePlaceConsistency' => s("Il n'est pas possible de continuer cette culture cette saison car un ou plusieurs emplacements qu'elle utilise (parcelle, jardin ou planche) ne sont pas utilisables cette année. Veuillez vérifier votre plan d'assolement !"),
			'Series::series.check' => s("Merci de sélectionner au moins une série"),
			'Series::name.check' => s("Merci d'indiquer un nom pour la série"),
			'Series::season.tasks' => s("Des interventions de la série auraient lieu plus d'un an avant ou après la nouvelle saison. Décalez-les d'abord, ou dupliquez la série sur la nouvelle saison."),
			'Series::taskInterval.check' => s("Les interventions ne doivent pas être décalées de plus d'un an"),
			'Series::closed' => s("Cette série est clôturée : son assolement ne peut plus être modifié."),
			'Series::outcome.check' => s("Ce dénouement n'existe pas."),
			'Series::outcome.closed' => s("Seule une série clôturée peut avoir un dénouement."),

			'Cultivation::plant.check', 'Cultivation::plant.owned' => s("Vous n'avez pas choisi d'espèce !"),
			'Cultivation::plant.unused' => s("Vous ne pouvez pas choisir cette espèce car elle est déjà utilisée par ailleurs dans cette série !"),
			'Cultivation::plant.duplicate' => s("Vous avez déjà ajouté cette espèce à la série !"),
			'Cultivation::canNotDelete' => s("Il doit rester au moins une production sur la série."),
			'Cultivation::cultivations.check' => s("Merci de sélectionner au moins une série"),
			'Cultivation::cultivations.listed' => s("Une série cochée n'est plus dans les récoltes de la semaine. Rechargez la page puis recommencez."),
			'Cultivation::cultivations.part' => s("Une variété ou un calibre coché ne se récolte plus sur sa série. Rechargez la page puis recommencez."),
			'Cultivation::variety.duplicate' => s("Vous avez indiqué des variétés en doublon pour une culture."),
			'Cultivation::plantSpacing.check', 'Cultivation::rowSpacing.check' => s("Un espacement se donne en centimètres, au-dessus de zéro."),
			'Cultivation::rows.check' => s("Indiquez au moins un rang."),
			'Cultivation::varietiesUnit.check' => s("Les informations de la culture n'ont pas été transmises. Rechargez la page puis recommencez."),
			'Series::use.check' => s("Indiquez si la série est cultivée en planches ou en surface libre."),
			'Series::bedWidth.check', 'Series::bedWidth.required' => s("Indiquez la largeur des planches, en centimètres."),
			'Series::alleyWidth.check' => s("La largeur des passe-pieds se donne en centimètres, au-dessus de zéro."),
			'Series::lengthTarget.check' => s("La longueur de planches se donne en mètres, au-dessus de zéro."),
			'Series::areaTarget.check' => s("La surface se donne en m², au-dessus de zéro."),
			'Cultivation::harvestPeriod.check' => s("La période de récolte attendue n'est pas valable : elle ne peut pas finir avant d'avoir commencé, ni s'étaler sur plus de deux ans."),

			'Place::bedsDuplicate' => s("Vous avez sélectionné des emplacements en doublon."),
			'Place::bedsCheck' => s("Veuillez sélectionner au moins un emplacement pour cette série !"),
			'Place::bedsSize' => s("Vous avez choisi une utilisation incorrecte sur certains emplacements !"),
			'Place::bedsExceeded' => s("Vous avez choisi un linéaire supérieur à la longueur de la planche sur certains emplacements !"),
			'Place::plotsPermutation' => s("Chaque jardin du groupe doit recevoir les séries d'un seul jardin."),

			'Repeat::stop.future' => s("La fin de répétition de l'intervention doit être dans le futur"),
			'Repeat::stop.season' => s("Vous devez indiquer une fin de répétition qui corresponde à l'année de la saison de la série, l'année précédente ou l'année suivante"),

			'Task::actions.check' => s("Merci de sélectionner des interventions identiques"),
			'Task::action.check', 'Task::action.usable' => s("Choisissez une intervention"),
			'Task::cultivation.check', 'Task::cultivation.series' => s("Vous n'avez pas saisi de production pour cette intervention"),
			'Task::cultivation.category' => s("Une série ne se choisit que pour une intervention de culture"),
			'Task::plant.category' => s("Une espèce ne se choisit que pour une intervention de culture"),
			'Task::cultivation.unknown' => s("Cette série n'existe plus, choisissez-en une autre"),
			'Task::cultivation.tooMany' => s("Choisissez au plus {value} séries ou espèces à la fois", SeriesSetting::TASK_CREATE_LIMIT),
			'Task::cultivation.harvest' => s("Cette intervention n'est pas une récolte de cette série"),
			'Task::done.check' => s("Merci d'indiquer une date de réalisation cohérente"),
			'Task::done.day' => s("Choisissez le jour où l'intervention a été faite"),
			'Task::done.future' => s("Une intervention ne peut pas être faite un jour à venir"),
			'Task::done.interval' => s("Vous ne pouvez pas saisir d'intervention plus de cinq ans dans le passé ou le futur"),
			'Task::planned.season', 'Task::done.season' => s("L'intervention doit avoir lieu pendant l'année de la saison de la série, l'année précédente ou l'année suivante"),
			'Task::planned.started' => s("Cette intervention est commencée, elle garde son jour"),
			'Task::planned.interval' => s("Vous ne pouvez pas planifier d'intervention plus de cinq ans dans le passé ou le futur"),
			'Task::planned.check', 'Task::plannedDate.check' => s("Merci d'indiquer une date de planification cohérente"),
			'Task::timeExpected.check' => s("Indiquez un temps prévu positif"),
			'Task::harvest.check', 'Task::harvest.action' => s("La récolte ne peut pas être négative"),
			'Task::treatment.dose' => s("La dose doit être un nombre positif"),
			'Task::treatment.water' => s("Le volume de bouillie doit être un nombre entier de litres par hectare"),
			'Task::treatment.newProduct' => s("Indiquez la dose de ce produit, elle deviendra sa dose par défaut"),
			'Task::products.action' => s("Ce produit n'est pas dans le catalogue E-Phy"),
			'Task::products.unknown' => fn(Task $eTask, string $query) => s("« {value} » n'est pas dans vos produits, choisissez-le dans la liste pour le créer", encode($query)),
			'Task::harvestMore.negative' => s("La récolte totale ne peut pas être négative sur une intervention"),
			'Task::harvestMore.cast', 'Task::harvestDay.cast' => s("La quantité récoltée est un nombre, par exemple 12 ou 12,5"),
			'Task::harvestMore.day' => s("Vous ne pouvez pas retirer plus que la récolte de ce jour"),
			'Task::harvestMore.time' => s("Un retrait ne porte pas de temps de travail, saisissez le temps à part."),
			'Task::harvestMore.ventilated' => s("La récolte de ce jour est en partie répartie par variété ou par calibre et se retire part par part"),
			'Task::harvestMore.empty' => s("Indiquez une quantité récoltée ou un temps de travail"),
			'Task::harvestMore.missing' => s("Indiquez la quantité récoltée"),
			'Task::harvestDay.negative' => s("Indiquez une quantité récoltée positive ou nulle"),
			'Task::harvestDay.ventilated' => s("La récolte de ce jour est en partie répartie par variété ou par calibre et se remplace part par part"),
			'Task::stock.check' => s("Il n'est pas possible d'ajouter la récolte au stock que vous avez choisi"),
			'Task::harvestConsistency.plant' => s("Vous devez choisir une espèce pour saisir une récolte"),
			'Task::harvestConsistency.check' => s("Vous ne pouvez saisir de récoltes partagées que pour des productions identiques"),
			'Task::harvestConsistency.unspecified' => s("« Non spécifiée » ne se récolte pas en même temps qu'une variété de sa série"),
			'Task::harvestDates.check' => s("Merci de saisir une date de récolte valable"),
			'Task::harvestDates.negative' => s("La récolte ne peut pas être négative sur une date donnée"),
			'Task::harvestUnit.crate' => s("Une récolte en caisses se convertit en kilos et ne vaut que pour une récolte en kilos"),
			'Task::harvestUnit.check' => s("Merci de choisir une unité de récolte"),
			'Task::harvestUnit.couple' => s("Une intervention se récolte dans son unité. Pour une autre unité, passez par le planning des récoltes."),
			'Task::harvestUnit.quantity' => s("Saisissez une quantité pour récolter dans cette unité"),
			'Task::plant.action' => s("Vous devez choisir une espèce pour saisir une récolte"),
			'Task::plant.owned' => s("Cette espèce n'est pas une espèce de votre ferme"),
			'Task::harvestSize.action' => s("Ce calibre n'est pas proposé pour cette espèce"),
			'Task::variety.check' => s("Cette variété n'est pas cultivée dans cette série"),
			'Task::repeatMaster.consistency' => s("La date de fin de répétition de l'intervention doit être postérieure à la date de démarrage"),
			'Task::tasks.check' => s("Merci de sélectionner au moins une intervention"),
			'Task::tasks.unit' => s("Il n'est pas possible de faire une récolte groupée car toutes les interventions n'ont pas la même unité de récolte"),
			'Task::tasks.area' => s("Il n'est possible de choisir une répartition par surface car vous n'avez pas saisi l'espace occupé pour au moins une des cultures"),
			'Task::tasks.density' => s("Il n'est possible de choisir une répartition par nombre de plants car vous n'avez pas saisi la densité pour au moins une des cultures"),
			'Task::tasks.notSeries' => s("Il n'est possible de choisir cette répartition car certaines interventions ne sont pas liées à des séries"),
			'Task::distribution.harvestZero' => s("Il n'est possible de choisir cette répartition car il n'y a aucune récolte pour ce jour de travail"),

			'Timesheet::user.check' => s("Vous ne pouvez pas gérer le temps de travail de cet utilisateur !"),
			'Timesheet::time.negative' => s("Le temps de travail d'un utilisateur ne peut pas être négatif sur une journée"),
			'Timesheet::time.missing' => s("Indiquez le temps de travail de ce jour"),
			'Timesheet::time.tooLong' => s("Une journée ne peut pas dépasser 24 heures de travail"),
			'Timesheet::time.check' => s("Merci d'indiquer un temps de travail valable"),
			'Timesheet::time.untracked' => s("Le suivi du temps de travail n'est pas activé sur votre structure"),
			'Timesheet::time.harvestless' => s("Saisissez d'abord la récolte du jour"),
			'Timesheet::date.check' => s("Merci d'indiquer un jour de travail valable"),
			'Timesheet::date.future' => s("Vous ne pouvez pas saisir de travail dans le futur"),
			'Timesheet::date.week' => s("Choisissez un jour de la semaine affichée"),
			'Timesheet::duplicate' => s("Cet utilisateur a déjà été affecté à cette intervention"),
			default => NULL

		};


	}

	public static function getSuccess(string $fqn, array $options = []): ?string {

		return match($fqn) {

			'Cultivation::deleted' => s("La plante a bien été supprimée de la série."),

			'Task::cultivationUpdated' => s("L'intervention a bien été affectée à sa nouvelle série !"),
			'Task::position.check' => s("L'ordre reçu ne correspond pas aux interventions de la semaine."),
			'Task::deleted' => s("L'intervention a bien été supprimée !"),
			'Task::treatmentUpdated' => s("Le traitement a bien été enregistré."),

			'Place::plotsMoved' => s("Les séries de {list} et {last} ont changé de jardin.", ['list' => implode(', ', array_slice($options, 0, -1)), 'last' => $options[array_key_last($options)]]),

			'Series::created' => s("La série a bien été créée !"),
			'Series::updatedSoil' => s("L'assolement a bien été enregistré"),
			'Series::movedSoil' => s("La série a bien été déplacée sur sa nouvelle planche."),
			'Series::updatedSeason' => s("La série a bien été changée de saison."),
			'Series::updatedSeasonCollection' => s("Les séries ont bien été changées de saison."),
			'Series::duplicated' => s("La série a bien été dupliquée ici !"),
			'Series::duplicatedCollection' => s("Les séries ont bien été dupliquées ici !"),
			'Series::deleted' => s("La série a bien été supprimée."),
			'Series::deletedCollection' => s("Les séries ont bien été supprimées."),
			'Series::closedCollection' => s("Les séries ont bien été clôturées."),
			'Series::numberedCollection' => s("Les séries ont bien été renommées."),
			'Series::plannedStretched' => s("La série a bien été allongée : son début n'a pas bougé, la suite s'est écartée."),

			default => NULL

		};

	}

}
?>
