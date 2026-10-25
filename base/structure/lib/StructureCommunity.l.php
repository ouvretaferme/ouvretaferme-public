<?php
namespace structure;

/**
 * Ce qu'un groupe propose de faire à qui l'ouvre, et dans quel ordre.
 *
 * L'état se calcule ici, le rendu vit dans `StructureCommunityUi` : l'écran n'a qu'à traduire un
 * état en composants, il n'a pas à savoir pourquoi c'est celui-là. Chaque type de groupe qui
 * enchaîne autrement redéfinit `getState()` dans sa propre sous-classe — `StructureEducationLib`
 * le fait pour poser la promotion avant l'invitation — et `StructureLib::getCommunityClass()`
 * choisit laquelle répond.
 *
 * Cette classe porte l'enchaînement commun, qui est aussi celui d'une communauté de producteurs :
 * on invite, et les membres apportent leurs fermes.
 */
class StructureCommunityLib {

	/** Rien à proposer : le groupe vit, ou le lecteur n'a aucun geste à faire ici. */
	const NOTHING = '';

	/** Créer un premier sous-groupe, préalable à toute invitation là où un rôle est borné. */
	const SECTION = 'section';

	/** Inviter les premiers membres. */
	const INVITE = 'invite';

	/** Un sous-groupe vient d'être créé : le bandeau le nomme et propose d'y inviter. */
	const SECTION_CREATED = 'sectionCreated';

	/** Partager une ferme qu'on administre déjà. */
	const SHARE = 'share';

	/** Créer une ferme, faute d'en avoir une. */
	const FARM = 'farm';

	/**
	 * Ce que la ferme écrit sur elle-même en entrant dans ce groupe.
	 *
	 * Rien pour la plupart des groupes : entrer ne change pas la ferme. Le groupe de formation y
	 * ajoute sa catégorie, et la règle ne s'écrit qu'ici parce que deux portes y mènent —
	 * l'invitation et le partage depuis le groupe. Une conjonction recopiée sur la seconde est
	 * exactement ce que la troisième oubliera.
	 */
	public static function getPropertiesShare(Structure $eStructureProducer): array {
		return [];
	}

	/**
	 * L'état de l'accueil et ce qu'il faut pour le rendre.
	 *
	 * Les deux éléments voyagent avec l'état parce que le rendu les nomme : le sous-groupe qu'on
	 * vient de créer, et le rôle borné vers lequel l'invitation pointera.
	 *
	 * `$members` est `MemberLib::countByStructure()`, que le gabarit du groupe a déjà lu.
	 */
	public static function getOnboarding(Structure $eStructure, \user\User $eUser, int $members): array {

		$cSection = SectionLib::getByStructure($eStructure);

		$cRoleOwn = RoleLib::getByStructure($eStructure)
			->find(fn(Role $eRole) => $eRole['sections'] === Role::OWN);

		return [
			'state' => static::getState($eStructure, $eUser, $cSection, $cRoleOwn, $members),
			'eSection' => $cSection->notEmpty() ? $cSection->last() : new Section(),
			'eRole' => $cRoleOwn->first() ?? new Role()
		];

	}

	protected static function getState(Structure $eStructure, \user\User $eUser, \Collection $cSection, \Collection $cRoleOwn, int $members): string {

		if($eStructure->canManage()) {
			return static::getStateManage($eStructure, $cSection, $members);
		}

		if($eStructure->getMember()->isStaff()) {
			return self::NOTHING;
		}

		// ⚠️ Ni administrateur, ni borné dans un groupe qui borne : personne ne lui demande de
		// ferme, et le seul message honnête serait « attendez ». C'est le cas de l'enseignant, et
		// ce sera celui du rôle non borné qu'un groupe se donnera demain — aucun nom de rôle ni
		// aucun type n'est testé, seule la portée répond.
		if($cRoleOwn->notEmpty() and SectionLib::getScope($eStructure) === NULL) {
			return self::NOTHING;
		}

		if(AffiliationAiLib::getByUser($eUser, $eStructure)->notEmpty()) {
			return self::NOTHING;
		}

		// Jamais les deux gestes à égalité : qui a déjà une ferme n'a rien à créer, qui n'en a pas
		// ne peut rien partager. Le produit sait lequel proposer.
		return StructureLib::getShareableByUser($eUser, $eStructure)->notEmpty()
			? self::SHARE
			: self::FARM;

	}

	/**
	 * L'enchaînement commun : on invite, et rien d'autre. Les sous-groupes y sont facultatifs, on
	 * ne les impose jamais.
	 */
	protected static function getStateManage(Structure $eStructure, \Collection $cSection, int $members): string {

		// Seul dans son groupe, il reste à le peupler. Au-delà, le groupe vit et l'écran redevient
		// des chiffres et une ventilation.
		if($members > 1) {
			return self::NOTHING;
		}

		return $cSection->empty() ? self::INVITE : self::SECTION_CREATED;

	}

}
?>
