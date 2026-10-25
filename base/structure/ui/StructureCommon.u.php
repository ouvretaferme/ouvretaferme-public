<?php
namespace structure;

/**
 * Ce que les quatre écrans de création de structure partagent.
 *
 * Dans son propre fichier, et non en bas de Structure.u.php : l'autoload résout une interface
 * par son nom, donc StructureCommunityUi ou StructureShopUi ne se chargeaient qu'après
 * StructureUi. Le produit passait toujours par StructureUi::getInstance() et n'a jamais vu le
 * piège ; le premier appelant qui atteint une de ces classes directement — un e-mail, une
 * commande — le rencontre en fatale.
 */
interface StructureCommonUi {

	public function getCreateForm(\util\FormUi $form, Structure $eStructure): string;

	public function getCreateTitle(Structure $eStructure): string;

	/** L'illustration de l'accueil de ce type, reprise sur ses pages d'invitation. */
	public static function getWelcomeMascot(): string;

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string;

}
?>
