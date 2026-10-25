<?php
namespace aid;

class AlertUi {

	public static function getError(string $fqn, array $options): mixed {

		return match($fqn) {

			'Aid::name.check' => s("Indiquez le nom de l'aide, en 250 caractères au plus."),
			'Aid::rate.check' => s("Le taux ou le montant de l'aide ne doit pas dépasser 1000 caractères."),
			'Aid::organization.check' => s("Vous devez choisir l'organisme qui accorde l'aide."),
			'Aid::summary.check' => s("Le résumé ne doit pas dépasser 250 caractères."),
			'Aid::keywords.check' => s("Les mots-clés ne doivent pas dépasser 250 caractères."),
			'Aid::level.check' => s("Choisissez l'échelon de l'aide."),
			'Aid::type.check' => s("Choisissez la forme de l'aide."),
			'Aid::calendar.check' => s("Choisissez le calendrier de l'aide."),
			'Aid::ratePercentMax.check' => s("Le taux maximal est un pourcentage entre 0 et 100."),
			'Aid::amountMax.check' => s("Le montant maximal doit être un nombre positif."),
			'Aid::amountUnit.check' => s("Choisissez l'unité du montant maximal."),
			'Aid::amountUnit.required' => s("Un montant maximal se donne avec son unité."),
			'Aid::floor.check' => s("Le plancher doit être un nombre positif."),
			'Aid::ceiling.check' => s("Le plafond doit être un nombre positif."),
			'Aid::boundBase.check' => s("Choisissez ce que portent le plancher et le plafond."),
			'Aid::boundPeriod.check' => s("Choisissez la période du plancher et du plafond."),
			'Aid::parent.check' => s("Choisissez le dispositif dans la liste."),
			'Aid::beneficiaries.check' => s("Un des bénéficiaires choisis n'existe pas."),
			'Aid::direct.check' => s("Dites si la structure dépose elle-même."),
			'Aid::productions.check' => s("Les productions choisies ne sont pas valides."),
			'Aid::labels.check', 'Aid::labelsBonus.check' => s("Un des labels choisis n'existe pas."),
			'Aid::conditions.check' => s("Les conditions sont trop longues."),
			'Aid::notes.check' => s("Les notes de relevé sont trop longues."),
			'Aid::checkedAt.check' => s("Cette date de relecture n'est pas valide."),
			'Aid::productions.unknown' => s("Une des productions choisies n'existe pas."),
			'Aid::parent.self' => s("Une aide ne peut pas être un volet d'elle-même."),
			'Aid::rateUnpublished.check' => s("Dites si l'organisme publie un taux."),
			'Aid::rateUnpublished.published' => s("Une aide publiée garde son taux ou son montant, sauf quand l'organisme n'en publie aucun. Dépubliez-la d'abord pour le retirer."),
			'Aid::rateUnpublished.rate' => s("Une aide qui a un taux ou un montant ne peut pas dire que l'organisme n'en publie aucun."),
			'Aid::seedDifferences.changed' => s("Une reprise a changé l'écart depuis que vous l'avez ouvert. Relisez-le avant de trancher."),
			'Aid::publishRefused' => s("Cette aide ne peut plus être publiée, elle a changé depuis que vous l'avez ouverte. Rechargez la page pour la relire."),
			'Aid::unpublishRefused' => s("Cette aide n'est plus publiée. Rechargez la page pour la relire."),
			'Aid::archiveRefused' => s("Cette aide est déjà archivée. Rechargez la page pour la relire."),
			'Aid::checkRefused' => s("Cette aide ne peut plus être marquée relue, elle a changé depuis que vous l'avez ouverte. Rechargez la page pour la relire."),
			'Aid::confirmRefused' => s("Cette aide ne peut plus être confirmée, elle a changé depuis que vous l'avez ouverte. Rechargez la page pour la relire."),
			'Aid::seedSettled' => s("L'écart de cette aide a déjà été tranché. Rechargez la page pour la relire."),
			'Aid::depositUrl.check' => s("Cette adresse n'est pas une adresse web valide."),
			'Aid::commitmentRule.check' => s("Ce qu'il faut attendre avant de signer un devis ou d'acheter ne doit pas dépasser 1000 caractères."),
			'Aid::piecesUnpublished.check' => s("Dites si l'organisme publie la liste des pièces."),
			'Aid::checkPiecesRefused' => s("La structure ne dépose plus elle-même cette aide, ou l'aide n'a plus de dossier. Rechargez la page pour la relire."),

			'AidPiece::moment.check' => s("Choisissez le moment où la pièce se fournit."),
			'AidPiece::piece.check' => s("Cette pièce n'est pas dans la liste des pièces courantes."),
			'AidPiece::name.check' => s("Le nom de la pièce ne doit pas dépasser 250 caractères."),
			'AidPiece::name.exclusive' => s("Choisissez une pièce courante ou écrivez le nom d'une pièce libre, l'un des deux seulement."),
			'AidPiece::name.duplicate' => s("Une pièce de ce nom est déjà demandée au même moment."),
			'AidPiece::note.check' => s("La condition ne doit pas dépasser 250 caractères."),
			'AidPiece::url.check' => s("Cette adresse n'est pas une adresse web valide."),

			'AidLink::target.self' => s("Une aide ne peut pas être liée à elle-même."),
			'AidLink::target.check' => s("Choisissez l'aide à lier."),
			'AidLink::target.duplicate' => s("Ce lien avec cette aide existe déjà."),
			'AidLink::kind.check' => s("Choisissez le lien."),

			'AidOrganization::organization.check' => s("Choisissez un organisme."),
			'AidOrganization::organization.duplicate' => s("Cet organisme tient déjà ce rôle pour l'aide."),
			'AidOrganization::role.check' => s("Choisissez le rôle de l'organisme."),

			'Area::department.check' => s("Ce département n'existe pas."),
			'Area::department.duplicate' => s("Ce département est déjà dans le périmètre de l'aide."),
			'Area::note.check' => s("La précision ne doit pas dépasser 250 caractères."),
			'Area::epci.check' => s("Le SIREN de l'intercommunalité compte neuf chiffres."),
			'Area::commune.check' => s("Le code INSEE de la commune compte cinq caractères."),

			'AidTag::tag.check' => s("Un des tags choisis n'existe pas."),

			'Edition::startDate.check' => s("Cette date d'ouverture n'est pas valide."),
			'Edition::endDate.check' => s("Cette date de clôture n'est pas valide."),
			'Edition::endDate.consistency' => s("La date de clôture doit suivre la date d'ouverture."),
			'Edition::name.check' => s("Le nom de l'édition ne doit pas dépasser 100 caractères."),
			'Edition::state.check' => s("Choisissez l'état de l'édition."),
			'Edition::rate.check' => s("Le taux ou le montant ne doit pas dépasser 1000 caractères."),
			'Edition::ceiling.check' => s("Le plafond doit être un nombre positif."),
			'Edition::url.check' => s("Cette adresse n'est pas une adresse web valide."),

			'Expected::code.check' => s("Indiquez le code de la ligne attendue, en 60 caractères au plus."),
			'Expected::family.check' => s("Choisissez la famille de la ligne attendue."),
			'Expected::department.check' => s("Ce département n'existe pas."),
			'Expected::department.family' => s("Une ligne nationale n'a pas de département, une ligne d'organisme en a un."),
			'Expected::region.check' => s("Cette région n'existe pas."),
			'Expected::region.family' => s("Une ligne nationale n'a pas de région, toute autre ligne en a une."),
			'Expected::name.check' => s("Indiquez l'intitulé de la ligne attendue, en 250 caractères au plus."),
			'Expected::organization.exclusive' => s("Une ligne attendue se résout par une aide ou par un organisme, pas par les deux."),
			'Expected::justification.check' => s("La justification ne doit pas dépasser 250 caractères."),
			'Expected::justificationUrl.check' => s("Cette adresse n'est pas une adresse web valide."),
			'Expected::listUrl.check' => s("Cette adresse n'est pas une adresse web valide."),

			'Organization::name.check' => s("Vous devez indiquer le nom de l'organisme."),
			'Organization::type.check' => s("Choisissez la nature de l'organisme."),
			'Organization::level.check' => s("Choisissez l'échelon de l'organisme."),
			'Organization::url.check' => s("Cette adresse n'est pas une adresse web valide."),
			'Organization::grants.check' => s("Dites si l'organisme accorde des aides en propre."),

			'Source::url.check' => s("Cette adresse n'est pas une adresse web valide."),
			'Source::url.duplicate' => s("Cette adresse est déjà une source de l'aide."),
			'Source::kind.check' => s("Choisissez la nature de la source."),
			'Source::kind.published' => s("Une aide publiée garde au moins une source officielle. Dépubliez-la d'abord pour changer la nature de celle-ci."),
			'Source::deletePrimary' => s("Une aide publiée garde au moins une source officielle. Dépubliez-la d'abord pour retirer celle-ci."),
			'Source::moveRefused' => s("La veille ne voit plus cette source redirigée. Rechargez la page pour lire son état."),

			'Tag::fqn.check' => s("L'identifiant du tag ne s'écrit qu'en lettres sans accent, chiffres, tirets et points."),
			'Tag::name.check' => s("Le nom du tag doit compter entre 1 et 100 caractères."),
			'Tag::projects.check' => s("Un des projets choisis n'existe pas."),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Aid::created' => s("L'aide a bien été créée, en brouillon."),
			'Aid::updated' => s("L'aide a bien été enregistrée."),
			'Aid::published' => s("L'aide est publiée."),
			'Aid::unpublished' => s("L'aide est repassée en brouillon."),
			'Aid::archived' => s("L'aide est archivée."),
			'Aid::checked' => s("L'aide est marquée relue."),
			'Aid::confirmed' => s("L'aide est confirmée sur sa source officielle."),
			'Aid::seedApplied' => s("L'aide reprend les valeurs de la fiche."),
			'Aid::seedKept' => s("L'aide est gardée telle quelle. La reprise ne rapportera plus cet écart."),
			'Aid::tagsUpdated' => s("Ce que finance l'aide a bien été enregistré."),
			'Aid::piecesUpdated' => s("La liste des pièces a bien été enregistrée."),
			'Aid::piecesChecked' => s("La liste des pièces est marquée relue."),

			'AidPiece::created' => s("La pièce a bien été ajoutée."),
			'AidPiece::updated' => s("La pièce a bien été enregistrée."),
			'AidPiece::deleted' => s("La pièce a bien été retirée."),

			'Edition::created' => s("L'édition a bien été ajoutée."),
			'Edition::updated' => s("L'édition a bien été enregistrée."),
			'Edition::deleted' => s("L'édition a bien été supprimée."),

			'Source::created' => s("La source a bien été ajoutée."),
			'Source::updated' => s("La source a bien été enregistrée."),
			'Source::deleted' => s("La source a bien été retirée."),
			'Source::moved' => s("La source a bien pris sa nouvelle adresse."),

			'Area::created' => s("Le département a bien été ajouté au périmètre."),
			'Area::deleted' => s("Le département a bien été retiré du périmètre."),

			'AidOrganization::created' => s("L'organisme a bien été ajouté."),
			'AidOrganization::deleted' => s("L'organisme a bien été retiré."),

			'AidLink::created' => s("Le lien a bien été ajouté."),
			'AidLink::deleted' => s("Le lien a bien été retiré."),

			default => NULL

		};

	}

}
?>
