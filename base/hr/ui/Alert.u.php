<?php
namespace hr;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'Absence::user.present' => p("Cette personne n'a pas fait partie de l'équipe ce dernier mois", "Cette personne n'a pas fait partie de l'équipe ces {value} derniers mois", Absence::PRESENCE_MONTHS),
			'Absence::type.check' => s("Choisissez le motif de l'absence"),
			'Absence::from.check' => s("Indiquez le début de l'absence"),
			'Absence::to.check' => s("Indiquez la fin de l'absence"),
			'Absence::to.consistency' => s("La fin de l'absence doit venir après son début"),
			'Absence::to.oneDay' => s("Une absence à l'heure tient sur une seule journée"),
			'Absence::to.tooLong' => p("Une absence ne peut pas durer plus de {value} an", "Une absence ne peut pas durer plus de {value} ans", Absence::MAX_YEARS),
			'Absence::duration.check' => s("Indiquez la durée de l'absence"),
			'Absence::duration.positive' => s("Indiquez une durée supérieure à zéro"),
			'Absence::duration.tooLong' => s("La durée dépasse la période de l'absence"),
			'Absence::to.outOfPresence' => s("L'absence doit tenir dans une période de présence de la personne"),
			'Absence::outOfPresence' => s("Cette absence sort de la période de présence de la personne, elle ne peut plus être validée"),
			'Absence::status.decided' => s("Cette demande a déjà reçu une réponse"),

			'Contract::type.check' => s("Choisissez le type de contrat"),
			'Contract::weeklyHours.check' => s("Indiquez un nombre d'heures entre 0 et 48 par semaine"),
			'Contract::annualHours.required' => s("Indiquez les heures par an du contrat annualisé"),
			'Contract::annualHours.check' => s("Indiquez un nombre d'heures entre 0 et 2 300 par an"),
			'Contract::days.variable' => s("Avec des jours variables, ne cochez aucun jour"),

			'Presence::user.teammate' => s("Cette personne ne fait pas partie de l'équipe"),
			'Presence::to.consistency' => s("La date de fin ne peut pas être antérieure à la date de début"),
			'Presence::to.present' => s("L'utilisateur est déjà présent au moins partiellement à la ferme à cette période"),

			'Teammate::user.check' => s("Choisissez une personne"),
			'Teammate::user.member' => s("Cette personne n'a pas d'accès à la structure"),
			'Teammate::user.duplicate' => s("Cette personne fait déjà partie de l'équipe"),

			'WorkingTime::date.check' => s("Vous ne pouvez pas saisir de temps de travail dans le futur"),
			'WorkingTime::date.outOfPresence' => s("Ce jour est en dehors des périodes de présence de la personne"),
			'WorkingTime::date.untracked' => s("Ni le temps de travail ni le suivi RH ne sont activés sur votre structure"),
			'WorkingTime::time.check' => s("Merci d'indiquer un temps de travail valable"),
			'WorkingTime::time.tooLong' => s("Une journée ne peut pas dépasser 24 heures de travail"),
			'WorkingTime::start.check', 'WorkingTime::end.check' => s("Merci d'indiquer une heure valide"),
			'WorkingTime::start.missing' => s("Indiquez l'heure d'arrivée"),
			'WorkingTime::end.missing' => s("Indiquez l'heure de départ"),
			'WorkingTime::end.afterStart' => s("Le départ doit venir après l'arrivée, dans la même journée"),
			'WorkingTime::pause.check' => s("Merci d'indiquer une pause valable"),
			'WorkingTime::pause.tooLong' => s("La pause doit être plus courte que la journée"),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Absence::created' => s("L'absence a bien été ajoutée."),
			'Absence::requested' => s("Votre demande d'absence a bien été envoyée."),
			'Absence::validated' => s("L'absence a bien été validée."),
			'Absence::refused' => s("La demande d'absence a bien été refusée."),
			'Absence::updated' => s("L'absence a bien été mise à jour."),
			'Absence::deleted' => s("L'absence a bien été supprimée."),

			'Presence::created' => s("La présence a bien été ajoutée."),
			'Presence::updated' => s("La présence a bien été mise à jour."),
			'Presence::deleted' => s("La présence a bien été supprimée."),

			'Teammate::created' => s("La personne a bien été ajoutée à l'équipe."),
			'Teammate::left' => s("Cette personne est sortie du planning."),

			default => NULL

		};

	}

}
?>
