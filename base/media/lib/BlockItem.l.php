<?php
namespace media;

/**
 * L'image d'un enfant d'un bloc parent (`block\Block.media`), une par bloc. Une copie de bloc prend la
 * sienne sous un hash neuf, et un bloc retiré retire la sienne (`BlockLib`) : aucun hash n'est porté
 * par deux lignes.
 */
class BlockItemLib extends MediaLib {

	/**
	 * Aucun dépôt par `/media/:put` : le bloc et l'état de l'objet qui le porte se jugent sur la route qui
	 * pose l'image (`Block_0_1betaApi`, `block/{id}/media`), et une route web qui la poserait ferait ces
	 * deux contrôles ici.
	 */
	public function buildElement(): \block\Block {
		throw new \NotAllowedAction();
	}

}
?>
