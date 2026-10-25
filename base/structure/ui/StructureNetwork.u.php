<?php
namespace structure;

/**
 * Les mots d'un réseau d'accompagnement : un groupe, des membres, des fermes projet.
 *
 * Elle n'existe que pour les mots : tout le reste — l'accueil, le sélecteur, les onglets, les
 * cartes — vit dans `StructureCommunityUi`, qui les rend à l'identique pour les quatre types.
 * Une sous-classe qui n'a rien à dire de plus n'a rien à écrire.
 */
class StructureNetworkUi extends StructureCommunityUi {

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {
			'of' => s("du réseau"),
			'one' => s("un réseau d'accompagnement"),
			'the' => s("le réseau"),
			'this' => s("ce réseau"),
			'your' => s("votre réseau"),
			'my' => p("Mon réseau", "Mes réseaux", $value),
			'propertyLogo' => s("Image du réseau"),
			'propertyBanner' => s("Bandeau du réseau"),
			'propertyName' => s("Nom du réseau"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail du réseau"),
			'propertyPresentation' => s("Présentation du réseau"),
			'farms' => s("Fermes des membres"),
			'farmsTitle' => s("Les fermes du réseau"),
			'farmsTitleScoped' => s("Les fermes de mon groupe"),
			'section' => p("Groupe", "Groupes", $value),
			'sectionsTitle' => s("Les groupes"),
			'sectionsFeature' => s("Faire des groupes de fermes dans votre réseau."),
			'sectionClosed' => p("{value} groupe clos", "{value} groupes clos", $value),
			// Les conseillers rejoignent les administrateurs : ils accompagnent le réseau, ils
			// n'y adhèrent pas comme une ferme membre.
			'staffTitle' => s("Équipe du réseau"),
			'audienceStaff' => s("l'équipe du réseau"),
			'audienceMember' => s("les autres producteurs"),
			'audienceStaffAllow' => s("Permettre l'accès à l'équipe du réseau"),
			'audienceMemberAllow' => s("Permettre l'accès aux producteurs du réseau"),
			'audienceStaffGrant' => s("J'autorise l'accès à l'équipe du réseau"),
			'audienceMemberGrant' => s("J'autorise l'accès aux producteurs du réseau"),
			'dashboardStaff' => s("Tableau de bord de l'équipe du réseau"),
			'dashboardMember' => s("Tableau de bord des producteurs"),
			// Le parent s'appelle « le réseau » partout ailleurs dans cette classe, et c'est ce
			// qui libère le mot « groupe » pour l'enfant. Ces deux chaînes-là le nommaient encore
			// « groupe » : le même mot aurait désigné deux portées sur le même écran.
			'sectionAll' => s("Tout le réseau"),
			'sectionManage' => s("Gérer les groupes"),
			'sectionNew' => s("Nom du groupe"),
			'sectionCreate' => s("Nouveau groupe"),
			'sectionThe' => s("le groupe"),
			'sectionThis' => s("ce groupe"),
			'sectionInvite' => s("Groupe dans lequel l'invitation fait entrer"),
			'sectionStaff' => s("Ce rôle encadre tout le réseau et n'entre dans aucun groupe."),
			'inviteAdminEmail' => s("Lorsque vous créez un lien pour inviter un administrateur, vous devez obligatoirement indiquer une adresse e-mail pour des raisons de sécurité."),
			'inviteAdminEmailRequired' => s("Veuillez réserver l'invitation d'un nouvel administrateur à une adresse e-mail"),
			'inviteAdmin' => '<p>'.s("Un administrateur administre le réseau comme vous : il invite et retire des membres, change leur rôle, gère les groupes, retire des fermes du réseau, modifie son profil et son paramétrage, et peut supprimer le réseau. Il peut aussi vous retirer l'administration ou vous retirer du réseau, et il faudra une nouvelle invitation pour y revenir.").'</p>'.'<p>'.s("Il ne prend en revanche aucune main sur les fermes : le réseau ne fait que lire ce que chaque ferme lui partage, et seul un administrateur d'une ferme décide de ce qu'elle partage.").'</p>'.'<p><b>'.s("N'invitez comme administrateurs que les personnes qui animent réellement le réseau.").'</b></p>',
			'sectionStartTitle' => s("Créez votre premier groupe"),
			'sectionEmpty' => s("Vous n'avez pas encore créé de groupe."),
			'sectionStartText' => s("Un groupe range les membres du réseau. C'est lui qui décide de ce que chacun voit quand son rôle l'y borne."),
			'sectionStartButton' => s("Créer un groupe"),
			'sectionCreated' => s("{value} créé", $value),
			'sectionOther' => s("Créer un autre groupe"),
			'sectionShareText' => s("Votre groupe verra vos données de production. Vous choisissez ce que vous ouvrez, et vous pouvez le refermer à tout moment."),
			'membersInvite' => s("Inviter des membres"),
			'membersInviteAll' => s("Inviter des membres"),
			'membersInviteText' => s("Invitez vos producteurs et vos collègues à rejoindre votre réseau."),
			'sectionDeleteWarning' => p("{value} personne ne sera plus rangée dans aucun groupe.", "{value} personnes ne seront plus rangées dans aucun groupe.", $value),
			'create' => s("Créer un réseau"),
			'createButton' => s("Créer le réseau"),
			'created' => s("Votre réseau est créé !"),
			'ready' => s("Votre réseau est prêt"),
			'delete' => s("Supprimer le réseau")
		};

	}

}
?>
