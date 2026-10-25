<?php
namespace structure;

class AlertUi {

	public static function getError(string $fqn, array $options): mixed {

		return match($fqn) {

			'Configuration::hasVat.required' => s("Indiquez si la structure est redevable de la TVA."),
			'Configuration::hasVat.historyConfirmed', 'Configuration::vatChargeability.historyConfirmed' => s("Cochez la case pour confirmer."),
			'Configuration::vatNumber.check' => fn() => \structure\AlertUi::getErrorVatNumber($options[0]),
			'Configuration::vatNumber.country' => s("Vous ne pouvez pas saisir de numéro de TVA intracommunautaire si votre structure n'est ni en France, ni en Belgique."),
			'Configuration::vatDebitsOption.checkConsistency' => s("L'option du paiement de TVA d'après les débits n'est disponible que pour une exigibilité de la TVA sur les débits."),

			'Feature::production.producer' => s("Seul un producteur peut activer la production."),
			'Feature::hasHr.production' => s("La RH ne s'active qu'avec la production."),
			'Configuration::time.production' => s("Le temps de travail ne s'active qu'avec la production."),
			'Configuration::timePeriodDay.check' => s("Choisissez un jour entre le 1er et le 28 du mois."),
			'Feature::hasCommunityLearning.enrolled' => fn(\Collection $cDate) => s("Des personnes sont inscrites ou attendent une réponse à leur demande pour {value}. Annulez ces dates ou attendez qu'elles soient passées pour désactiver les formations.", \structure\AlertUi::getLearningDates($cDate)),

			'Production::fqn.check' => s("Ce type d'atelier n'existe pas."),
			'Production::fqn.duplicate' => s("Vous avez déjà un atelier de ce type."),
			'Production::createCollectionError' => s("Il y a des erreurs à vérifier sur vos ateliers."),
			'Production::general.createCollectionEmpty' => s("Veuillez renseigner au moins un atelier."),

			'Structure::disabled' => s("Vous avez désactivé cette fonctionnalité sur votre ferme."),
			'Structure::demo.delete' => s("Vous ne pouvez pas supprimer la démo !"),
			'Structure::legalEmail.empty' => s("Merci de renseigner une adresse e-mail !"),
			'Structure::legalEmail.check' => s("Cette adresse e-mail n'est pas valide."),
			'Structure::legalCountry.check', 'Structure::legalCountry.legal' => s("Merci de choisir un pays dans la liste : il ne peut plus changer une fois vérifié."),
			'Affiliation::structureCommunity.check' => s("Ce groupe ne permet pas de partager une ferme."),
			'Affiliation::structureCommunity.notMember' => s("Vous n'êtes pas membre de ce groupe."),
			'Affiliation::structureProducer.notMember' => s("Vous n'êtes plus membre de cette ferme, vous ne pouvez pas la partager."),
			'Affiliation::structureProducer.duplicate' => s("Vous partagez déjà cette ferme avec ce groupe."),
			'Affiliation::structureProducer.check' => s("Merci de choisir une ferme à partager !"),
			'Affiliation::structureProducer.remove' => s("Cette ferme ne peut pas être retirée de ce groupe."),
			'Structure::charter.accepted' => s("Merci d'accepter les règles du groupe pour partager une ferme."),
			'Affiliation::structureProducer.notAdmin' => s("Seul un administrateur de cette ferme peut la partager avec un groupe."),
			'Community::quotationResponseMinimum.check' => s("Le nombre minimal de prix doit être compris entre {min} et {max}.", ['min' => Community::model()->getPropertyRange('quotationResponseMinimum')[0], 'max' => Community::model()->getPropertyRange('quotationResponseMinimum')[1]]),
			'Community::quotationMinimum.check' => s("Le nombre de fermes requis doit être compris entre {min} et {max}.", ['min' => Community::model()->getPropertyRange('quotationMinimum')[0], 'max' => Community::model()->getPropertyRange('quotationMinimum')[1]]),

			'Community::quotationTrimLow.check' => s("La part écartée en bas doit être comprise entre {min} et {max} %.", ['min' => Community::model()->getPropertyRange('quotationTrimLow')[0], 'max' => Community::model()->getPropertyRange('quotationTrimLow')[1]]),
			'Community::quotationTrimHigh.check' => s("La part écartée en haut doit être comprise entre {min} et {max} %.", ['min' => Community::model()->getPropertyRange('quotationTrimHigh')[0], 'max' => Community::model()->getPropertyRange('quotationTrimHigh')[1]]),
			// Un message par facteur, et chacun nomme le sien : les deux fails visent le même
			// groupe de champs, et une phrase commune s'y imprimerait deux fois de suite sans
			// que le lecteur sache laquelle des deux valeurs reprendre.
			'Community::quotationRatioHigh.check' => s("Le facteur au-dessus de la moyenne doit être compris entre {min} et {max}.", ['min' => \util\TextUi::number(Community::model()->getPropertyRange('quotationRatioHigh')[0]), 'max' => \util\TextUi::number(Community::model()->getPropertyRange('quotationRatioHigh')[1])]),
			'Community::quotationRatioLow.check' => s("Le facteur en dessous de la moyenne doit être compris entre {min} et {max}.", ['min' => \util\TextUi::number(Community::model()->getPropertyRange('quotationRatioLow')[0]), 'max' => \util\TextUi::number(Community::model()->getPropertyRange('quotationRatioLow')[1])]),
			'Community::quotationRegion.check' => s("Cette région n'est pas couverte par les relevés de prix."),
			'Community::quotationRatioHigh.paired',
			'Community::quotationRatioLow.paired' => s("Les deux facteurs s'activent ensemble : renseignez-les tous les deux, ou décochez le filtre."),
			'Community::fqn.check', 'Community::fqn.format' => s("L'adresse ne peut contenir que des chiffres, des lettres ou des tirets."),
			'Community::fqn.reserved' => s("Cette adresse est réservée, merci d'en choisir une autre."),
			'Community::fqn.duplicate' => s("Un groupe existe déjà avec la même adresse."),
			'Community::color.contrast' => s("Choisissez une couleur plus foncée, pour que le texte blanc posé dessus se lise."),
			'Community::customBackground.check' => s("Cette couleur de fond n'est pas reconnue."),
			'Community::customTitleFont.check', 'Community::customFont.check' => s("Cette police ne fait pas partie de celles proposées."),
			'Community::customDesign.check' => s("Ce thème n'est pas reconnu."),

			// Les deux seuls refus possibles sur un périmètre sont un public inconnu et une valeur
			// hors du masque : l'écran ne propose que les deux publics du groupe, donc seul un POST
			// forgé passe par ici. Le message reste utile, faute de quoi le refus n'apparaît pas.
			'Community::shareProduction.check', 'Community::shareProductionExport.check', 'Community::shareProductionDuplicate.check', 'Community::shareSellingDuplicate.check',
			'Affiliation::shareProduction.check', 'Affiliation::shareProductionExport.check', 'Affiliation::shareProductionDuplicate.check', 'Affiliation::shareSellingDuplicate.check' => s("Ce public n'existe pas pour ce groupe."),
			'Affiliation::contributesQuotation.check' => s("Cette autorisation n'accepte que oui ou non."),
			'Community::learningOnlineConfirm.check' => s("Ce choix d'inscription n'existe pas."),
			'Community::learningConditions.check' => s("Vos conditions de participation sont trop longues, merci de les raccourcir à {value} caractères au maximum.", Community::model()->getPropertyRange('learningConditions')[1]),
			'Community::rules.check' => s("Vos règles sont trop longues, merci de les raccourcir à {value} caractères au maximum.", Community::model()->getPropertyRange('rules')[1]),
			'Member::leaveLastAdmin' => s("Vous êtes le seul administrateur de ce groupe : nommez-en un autre avant de le quitter."),
			'Member::leaveNotCommunity' => s("Ce geste ne vaut que pour un groupe."),
			'Structure::communityType.check', 'Structure::communityType.type' => s("Ce type de groupe ne peut pas être créé ici."),
			'Structure::producerType.check', 'Structure::producerType.type' => s("Cette catégorie ne peut pas être choisie."),
			'Structure::producerType.chosen' => s("Merci d'indiquer la catégorie de votre ferme."),
			'Structure::name.check' => s("Merci de renseigner un nom !"),
			'Structure::copyProduction.none' => s("Choisissez de dupliquer au moins la production ou la commercialisation."),
			'Structure::copyProduction.refused' => s("Cette ferme n'a pas autorisé la duplication de sa production."),
			'Structure::copySelling.refused' => s("Cette ferme n'a pas autorisé la duplication de sa commercialisation."),
			'Structure::organic.check', 'Structure::organic.required' => s("Veuillez indiquer votre statut vis-à-vis de l'agriculture biologique"),
			'Structure::siret.empty' => s("Un numéro de SIRET à 14 chiffres est obligatoire pour utiliser le module de vente."),
			'Structure::siret.check' => self::getErrorSiret(),
			'Structure::legalCategory.check', 'Structure::legalCategory.known' => s("Choisissez la forme juridique de votre structure dans la liste."),
			'Structure::legalCity.empty' => s("Indiquez la commune de votre structure."),
			'Structure::legalStreet1.empty' => s("Indiquez l'adresse de votre structure."),
			'Structure::legalPostcode.empty' => s("Indiquez le code postal de votre structure."),
			'Structure::createEmpty' => s("Indiquez au moins une production !"),
			'Structure::notLegal' => '<p>'.s("Veuillez indiquer l'adresse de votre structure pour continuer !").'</p><a href="/structure/structure:updateLegal?id='.$options['structure']['id'].'" class="btn '.($options['btn'] ?? 'btn-transparent').'">'.s("Compléter mes informations").'</a>',
			'Structure::notVatAccounting' => '<p>'.s("Veuillez paramétrer la TVA de votre structure pour continuer !").'</p><a href="/structure/configuration:updateVat?id='.$options['structure']['id'].'" class="btn '.($options['btn'] ?? 'btn-transparent').'">'.s("Compléter mes informations").'</a>',
			'Configuration::vatChargeability.check', 'Configuration::vatChargeability.required' => s("Veuillez indiquer l'exigibilité de votre TVA."),
			'Structure::notInstalled' => match($options['producerType']) {
				Structure::TEST => s("Une ferme de test ne peut pas envoyer de documents à valeur légale."),
				Structure::LEARNER => s("Une ferme d'étudiant ne peut pas envoyer de documents à valeur légale."),
				Structure::INSTALLATION => s("Un projet d'installation ne peut pas envoyer de documents à valeur légale."),
				Structure::PROJECT => s("Une ferme projet d'un groupe ne peut pas envoyer de documents à valeur légale."),
				Structure::SCHOOL => s("Une ferme école ne peut pas envoyer de documents à valeur légale."),
				Structure::TRAINING => s("Une structure de formation ne peut pas envoyer de documents à valeur légale."),
			},
			'Structure::notVerified' => '<p>'.s("Vous devez vérifier le pays de votre structure pour continuer.").'</p>',

			'System::area.check' => s("La SAU ne peut excéder {value}", \structure\System::model()->getPropertyRange('area')[1]),
			'System::place.check' => s("Choisissez une commune dans la liste proposée"),
			'System::productionsList.duplicate' => s("Vous ne pouvez pas renseigner deux fois la même production"),
			'System::sellingLong.repartition' => s("La répartition de vos ventes doit totaliser 100 %"),

			'Terms::terms.check' => s("Veuillez accepter les conditions générales de vente pour continuer."),
			'Terms::terms.external' => s("Les conditions générales de vente s'acceptent depuis le compte de l'administrateur, pas depuis une connexion en tant que lui."),

			'Member::demo.write' => s("Vous ne pouvez pas modifier l'équipe sur la démo !"),
			'Member::user.check' => s("Vous n'avez pas sélectionné d'utilisateur."),
			'Member::email.check' => s("Cette adresse e-mail est invalide."),
			'Member::email.duplicate' => s("Il y a déjà un utilisateur rattaché à votre ferme avec cette adresse e-mail..."),
			'Member::deleteGhost' => s("Le rôle d'une personne sans compte ne se retire pas."),
			'Member::deleteGhostTeammate' => s("Vous ne pouvez pas supprimer une personne sans compte qui fait partie de l'équipe !"),
			'Member::ghostGone' => s("La personne sans compte que cette invitation désignait ne fait plus partie de l'équipe. Demandez une nouvelle invitation à la structure qui vous l'a envoyée."),
			'Member::ghostConflict' => s("Votre compte a déjà du temps de travail ou des présences dans cette équipe, et ne peut pas reprendre celui d'une personne sans compte. Demandez à la structure de corriger l'équipe à la main."),
			// Le même refus sert les équipes de ferme et les groupes : la phrase ne nomme donc
			// aucun des deux.
			'Member::deleteItself' => s("Vous ne pouvez pas vous retirer vous-même."),
			'Member::role.check', 'Member::role.known' => s("Ce rôle ne peut pas être attribué à ce membre."),
			'Member::notifications.check', 'Member::notifications.type' => s("Ce réglage de notifications n'a pas pu être enregistré."),
			'Member::favorite.check' => s("Cette structure n'a pas pu être retenue comme votre structure préférée."),
			'Member::viewPlanningOrder.check', 'Member::viewPlanningOrder.actions' => s("L'ordre des interventions n'a pas pu être enregistré."),
			'Member::viewDashboardActions.check', 'Member::viewDashboardActions.actions' => s("Le choix des interventions n'a pas pu être enregistré."),
			'Member::viewPlanningOrder.inactive' => s("Cette intervention a été désactivée, la liste a été rétablie."),
			'Member::viewPlanningDensity.check' => s("Cette densité n'existe pas."),
			'Member::viewPlanningSalesDays.check', 'Member::viewPlanningSalesDays.days' => s("Ce nombre de jours ne peut pas être choisi."),

			'Affiliation::structureProducer.export' => s("Ce groupe doit d'abord pouvoir lire la production de cette ferme."),
			'Section::name.duplicate' => s("Il y a déjà un sous-groupe qui porte ce nom."),
			'Section::name.check' => s("Merci de donner un nom à ce sous-groupe."),
			'Section::name.first' => s("Merci de nommer votre première promotion."),

			'Role::deleteUsed' => s("Vous ne pouvez pas supprimer ce rôle car il est encore utilisé dans votre équipe."),
			'Role::name.duplicate' => s("Il y a déjà un rôle qui porte ce nom"),

			'Invite::email.duplicate' => s("Une invitation a déjà été lancée pour cette adresse e-mail..."),
			'Invite::email.duplicateCustomer' => s("Cette adresse e-mail est déjà utilisée pour un autre de vos clients..."),
			'Invite::email.duplicateMember' => s("Il y a déjà un membre avec cette adresse e-mail dans votre équipe..."),
			'Invite::email.required' => s("Indiquez l'adresse e-mail de la personne à inviter."),
			'Invite::email.duplicateTeammate' => s("Le compte de cette adresse e-mail a déjà du temps de travail ou des présences dans votre équipe, il ne peut pas reprendre ceux de cette personne."),
			'Invite::member.check' => s("Cette personne sans compte ne fait pas partie de votre équipe."),
			'Invite::member.duplicate' => s("Une invitation est déjà en attente pour cette personne."),
			'Invite::email.admin', 'Invite::memberRole.admin' => fn(Invite $e) => $e['structure']->isCommunity()
				? StructureUi::getText($e['structure'], 'inviteAdminEmailRequired')
				: s("Veuillez réserver l'invitation d'un nouvel administrateur à une adresse e-mail"),
			'Invite::memberSection.check' => s("Ce sous-groupe n'appartient pas à ce groupe."),
			'Invite::memberSection.staff', 'Member::role.staff' => fn(Invite|Member $e) => StructureUi::getText($e['structure'], 'sectionStaff'),
			'Invite::memberSection.required' => s("Ce rôle ne voit que les sous-groupes où il est rangé : choisissez-en un, sinon l'invitation créera un compte qui ne verra rien."),
			'Invite::expiresAt.past' => s("Une invitation ne peut pas expirer dans le passé"),
			'Invite::duplicateKey' => s("Une erreur lors de la génération du code d'invitation est survenue, veuillez réessayer !"),
			'Invite::invalidKey' => s("Le code d'invitation que vous avez saisi n'est pas reconnu ou a expiré."),
			'Invite::structureProducer.check' => s("Merci de choisir la structure avec laquelle vous rejoignez la boutique !"),
			'Invite::invalidLimit' => s("Cette invitation a déjà servi. Si vous venez de l'accepter, il n'y a plus rien à faire. Sinon, demandez-en une nouvelle à la structure qui vous l'a envoyée."),
			'Invite::invalidExpired' => s("Cette invitation a expiré. Demandez-en une nouvelle à la structure qui vous l'a envoyée."),
			'Invite::amapClosed' => s("Le collectif est fermé et n'accepte plus cette invitation."),
			'Invite::invalidEmail' => s("Le code d'invitation que vous avez saisi n'est pas associé à cette adresse e-mail."),
			'Invite::alreadyJoined' => fn($eStructure) => s("Vous ne pouvez pas accepter cette invitation car vous êtes déjà membre de {value}", encode($eStructure['name'])),
			'Invite::invalidUnknown' => s("Vous ne pouvez plus utiliser ce code d'invitation, veuillez obtenir un nouveau code de la part de la structure que vous souhaitez rejoindre."),

			'Survey::structure.duplicate' => s("Vous avez déjà répondu pour cette structure"),
			'Survey::production.check', 'Survey::commercialisation.check', 'Survey::accounting.check' => s("Indiquez à quelle fréquence vous utilisez cet univers."),
			'Survey::missing.check' => s("Écrivez ce qui manque au logiciel, un besoin par ligne."),
			'Survey::missing.empty' => s("Dites-nous au moins une chose qui manque au logiciel."),
			'Survey::missing.max' => s("Gardez au plus {value} lignes, les plus importantes.", Survey::MISSING_MAX),
			'Survey::missing.length' => s("Une ligne dépasse {value} caractères, merci de la raccourcir.", Survey::MISSING_LENGTH),
			'Survey::productionMissing.check', 'Survey::commercialisationMissing.check', 'Survey::accountingMissing.check', 'Survey::useful.check', 'Survey::otherSoftware.check', 'Survey::comment.check' => s("Votre réponse est trop longue, merci de la raccourcir à {value} caractères au maximum.", Survey::model()->getPropertyRange(strstr(substr($fqn, strlen('Survey::')), '.', TRUE))[1]),

			default => NULL

		};

	}

	public static function getErrorSiret(): string {
		return s("Le numéro de SIRET doit contenir exactement 14 chiffres.");
	}

	public static function getErrorVatNumber(string $country): string {
		return match($country) {
			'FR' => s("Le numéro de TVA intracommunautaire pour une entité française doit commencer par FR et contenir 11 caractères"),
			'BE' => s("Le numéro de TVA intracommunautaire pour une entité belge doit commencer par BE et contenir 10 chiffres"),
		};
	}

	/**
	 * « Nom » le 12 octobre, « Nom » le 14 octobre et « Nom » le 3 novembre : les dates qui
	 * empêchent d'éteindre les formations d'un groupe. Publique, et appelée par son nom complet : le
	 * message est une closure que `FailWatch` exécute liée à lui-même, où `self` le désignerait.
	 */
	public static function getLearningDates(\Collection $cDate): string {

		$dates = [];

		foreach($cDate as $eDate) {
			$dates[] = s("« {course} » le {day}", [
				'course' => encode($eDate['course']['name']),
				'day' => \util\DateUi::textual($eDate['startAt'], \util\DateUi::DAY_MONTH)
			]);
		}

		$last = array_pop($dates);

		return ($dates === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $dates), 'last' => $last]);

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Affiliation::created' => s("La ferme est maintenant partagée avec le groupe."),
			'Affiliation::updated' => s("Ce groupe peut désormais copier le plan de culture de cette ferme."),
			// ⚠️ Une clé à part et non `Affiliation::updated` : celle-ci annonce une autorisation
			// **accordée**, et le panneau des droits sert aussi à en retirer une. Elle disait donc
			// le contraire du geste une fois sur deux.
			'Affiliation::rightsUpdated' => s("Les autorisations de cette ferme ont été enregistrées."),
			'Affiliation::deleted' => s("La ferme a été retirée du groupe."),

			'Community::updated' => s("Le groupe a bien été mis à jour."),

			'Member::left' => s("Vous avez quitté le groupe."),

			'Configuration::updated' => s("La configuration a bien été mise à jour."),
			'Configuration::updatedEmail' => s("Les données ont bien été mises à jour !"),
			'Configuration::disabledTime' => s("Le temps de travail a bien été désactivé, et vous pourrez le réactiver à tout moment."),
			'Configuration::enabledTime' => s("Le temps de travail a bien été activé."),

			'ConfigurationHistory::deleted' => s("La modification a bien été supprimée"),

			'Feature::disabledProduction' => s("Le module de production a bien été désactivé, et vous pourrez le réactiver à tout moment."),
			'Feature::enabledProduction' => s("Le module de production a bien été activé."),
			'Feature::disabledHr' => s("Le suivi RH a bien été désactivé, et vous pourrez le réactiver à tout moment."),
			'Feature::enabledHr' => s("Le suivi RH a bien été activé."),

			'Structure::updated' => s("Les modifications ont été enregistrées !"),
			'Structure::updatedInfo' => s("Les informations de votre ferme ont bien été mises à jour !"),
			'Structure::updatedConsentData' => s("Nous avons bien enregistré votre choix."),
			'Structure::updatedLegal' => s("Votre structure est désormais enregistrée comme ayant une existence juridique, vous pouvez terminer le paramétrage."),
			'Structure::closed' => s("La structure a bien été supprimée !"),
			'Structure::closedAmap' => s("L'AMAP est fermée. Les souscriptions déjà signées sont servies jusqu'à la fin de leur saison."),
			'Structure::duplicated' => s("La ferme a bien été dupliquée !"),
			'Structure::requalified' => s("La catégorie a bien été enregistrée."),

			'Member::userUpdated' => s("L'utilisateur a bien été mis à jour !"),
			'Member::userDeleted' => s("L'utilisateur a bien été supprimé !"),
			'Member::updated' => s("Les modifications ont bien été enregistrées !"),
			'Member::deleted' => s("L'utilisateur a bien été retiré de votre équipe !"),
			'Member::deletedAmap' => s("L'utilisateur a bien été retiré de l'administration !"),
			'Member::deletedRole' => s("Cette personne n'a plus de rôle."),

			'Section::created' => s("Le sous-groupe a bien été créé !"),
			'Section::updated' => s("Le sous-groupe a bien été mis à jour !"),
			'Section::deleted' => s("Le sous-groupe a bien été supprimé !"),
			'Section::composed' => s("Les membres du sous-groupe ont bien été enregistrés !"),

			'Role::created' => s("Le rôle a bien été créé et peut désormais être affecté aux membres de votre équipe !"),
			'Role::deleted' => s("Le rôle a bien été effacé !"),
			'Role::updated' => s("Le rôle a bien été mis à jour !"),

			'Invite::join' => s("Votre invitation a été acceptée !"),
			'Invite::created' => s("L'invitation a bien été créée !"),
			'Invite::resent' => s("L'invitation a bien été renvoyée."),
			'Invite::updatedRole' => s("Le rôle de l'invitation a bien été modifié."),
			'Invite::deleted' => s("L'invitation à rejoindre la ferme a bien été supprimée !"),
			'Invite::notification' => s("Vos préférences de notification ont été mises à jour"),

			'System::updated' => s("Les modifications sur votre système ont bien été mises à jour"),
			'System::updatedPlace' => s("Le lieu de production a bien été mis à jour"),

			default => null

		};


	}

}
?>
