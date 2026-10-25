<?php
namespace structure;

/**
 * Les mots d'une structure comptable : une antenne, des membres.
 *
 * Elle n'existe que pour les mots : tout le reste — l'accueil, le sélecteur, les onglets, les
 * cartes — vit dans `StructureCommunityUi`, qui les rend à l'identique pour les quatre types.
 * Une sous-classe qui n'a rien à dire de plus n'a rien à écrire.
 */
class StructureAccountingUi extends StructureCommunityUi {

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {
			'of' => s("de la structure comptable"),
			'one' => s("une structure comptable"),
			'the' => s("la structure comptable"),
			'this' => s("cette structure comptable"),
			'your' => s("votre structure comptable"),
			'my' => p("Ma structure comptable", "Mes structures comptables", $value),
			'propertyLogo' => s("Image de la structure comptable"),
			'propertyBanner' => s("Bandeau de la structure comptable"),
			'propertyName' => s("Nom de la structure comptable"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail de la structure comptable"),
			'propertyPresentation' => s("Présentation de la structure comptable"),
			'farms' => s("Fermes des membres"),
			'farmsTitle' => s("Les fermes de la structure comptable"),
			'farmsTitleScoped' => s("Les fermes de mon antenne"),
			'section' => p("Antenne", "Antennes", $value),
			'sectionsTitle' => s("Les antennes"),
			'sectionsFeature' => s("Répartir les membres de votre structure comptable en antennes."),
			'sectionClosed' => p("{value} antenne close", "{value} antennes closes", $value),
			'staffTitle' => p("Administrateur", "Administrateurs", $value),
			// Une structure comptable ne porte aucun droit de partage : les deux clés existent pour
			// que le `match` reste exhaustif, elles ne se rendent nulle part.
			'audienceStaff' => s("l'équipe"),
			'audienceMember' => s("les autres producteurs"),
			'audienceStaffAllow' => s("Permettre l'accès à l'équipe"),
			'audienceMemberAllow' => s("Permettre l'accès aux producteurs"),
			'audienceStaffGrant' => s("J'autorise l'accès à l'équipe"),
			'audienceMemberGrant' => s("J'autorise l'accès aux producteurs"),
			'sectionAll' => s("Tout le groupe"),
			'sectionManage' => s("Gérer les antennes"),
			'sectionNew' => s("Nom de l'antenne"),
			'sectionCreate' => s("Nouvelle antenne"),
			'sectionThe' => s("l'antenne"),
			'sectionThis' => s("cette antenne"),
			'sectionInvite' => s("Antenne dans laquelle l'invitation fait entrer"),
			'sectionStaff' => s("Ce rôle encadre toute la structure comptable et n'entre dans aucune antenne."),
			'inviteAdminEmail' => s("Lorsque vous créez un lien pour inviter un administrateur, vous devez obligatoirement indiquer une adresse e-mail pour des raisons de sécurité."),
			'inviteAdminEmailRequired' => s("Veuillez réserver l'invitation d'un nouvel administrateur à une adresse e-mail"),
			'inviteAdmin' => '<p>'.s("Un administrateur administre la structure comptable comme vous : il invite et retire des membres, change leur rôle, gère les antennes, retire des fermes de la structure comptable, modifie son profil et son paramétrage, et peut supprimer la structure comptable. Il peut aussi vous retirer l'administration ou vous retirer de la structure comptable, et il faudra une nouvelle invitation pour y revenir.").'</p>'.'<p>'.s("Il ne prend en revanche aucune main sur les fermes : la structure comptable ne fait que lire ce que chaque ferme lui partage, et seul un administrateur d'une ferme décide de ce qu'elle partage.").'</p>'.'<p><b>'.s("N'invitez comme administrateurs que les personnes qui animent réellement la structure comptable.").'</b></p>',
			'sectionStartTitle' => s("Créez votre première antenne"),
			'sectionEmpty' => s("Vous n'avez pas encore créé d'antenne."),
			'sectionStartText' => s("Une antenne range les membres de la structure comptable. C'est elle qui décide de ce que chacun voit quand son rôle l'y borne."),
			'sectionStartButton' => s("Créer une antenne"),
			'sectionCreated' => s("{value} créée", $value),
			'sectionOther' => s("Créer une autre antenne"),
			'sectionShareText' => s("Votre antenne verra vos données de production. Vous choisissez ce que vous ouvrez, et vous pouvez le refermer à tout moment."),
			'membersInvite' => s("Inviter des membres"),
			'membersInviteAll' => s("Inviter des membres"),
			'membersInviteText' => s("Invitez les membres à la rejoindre : le lien d'invitation les y rangera tout seul."),
			'sectionDeleteWarning' => p("{value} personne ne sera plus rangée dans aucune antenne.", "{value} personnes ne seront plus rangées dans aucune antenne.", $value),
			// Une structure comptable n'a ni panneau de création ni mise en route (`acceptCreateType()`,
			// `acceptCommunityStart()`) : ces quatre clés gardent le `match` exhaustif, elles ne se
			// rendent nulle part.
			'create' => s("Créer une structure comptable"),
			'createButton' => s("Créer la structure comptable"),
			'created' => s("Votre structure comptable est créée !"),
			'ready' => s("Votre structure comptable est prête"),
			'delete' => s("Supprimer la structure comptable")
		};

	}

}
?>
