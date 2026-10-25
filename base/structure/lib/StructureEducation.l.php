<?php
namespace structure;

/**
 * L'enchaînement d'un organisme de formation : la promotion d'abord, l'invitation ensuite.
 *
 * ⚠️ L'ordre ne vient pas d'un jugement d'ergonomie mais du modèle : le rôle d'étudiant est borné
 * à ses sous-groupes, et `Invite::build()` exige donc un `memberSection`. Proposer d'inviter avant
 * qu'une promotion existe mènerait à un formulaire que le produit refuse.
 *
 * Tout le reste — ce qu'on propose à qui apporte une ferme, à qui n'a rien à faire — est celui de
 * `StructureCommunityLib` et n'est pas redit ici.
 */
class StructureEducationLib extends StructureCommunityLib {

	/**
	 * L'entrée dans une promotion est le moment où une ferme de test devient une ferme d'étudiant :
	 * le groupe la lui redemande, et c'est la seule occasion où elle y repense.
	 *
	 * Rien à écrire sur une ferme qui n'accepte plus de changer de catégorie — une ferme installée
	 * est figée partout, et sa correction passe par l'équipe.
	 */
	public static function getPropertiesShare(Structure $eStructureProducer): array {

		if($eStructureProducer->acceptUpdateProducerType() === FALSE) {
			return [];
		}

		return ['producerType'];

	}

	protected static function getStateManage(Structure $eStructure, \Collection $cSection, int $members): string {

		if($cSection->empty()) {
			return self::SECTION;
		}

		return parent::getStateManage($eStructure, $cSection, $members);

	}

}
?>
