<?php
namespace toolbox;

/**
 * Une matière du calculateur des besoins en fertilisation, avec ses teneurs et la part disponible la
 * première année. Même régime que `FertilizationCrop` : commune à toutes les structures, lue sans
 * condition, écrite par le seul script du référentiel.
 */
class FertilizationMaterial extends FertilizationMaterialElement {

	// La valeur du sélecteur de matière qui ouvre la saisie de la composition à la main.
	const COMPOSITION = 'composition';

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
