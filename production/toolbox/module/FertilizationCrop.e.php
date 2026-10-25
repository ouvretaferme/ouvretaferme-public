<?php
namespace toolbox;

/**
 * Une espèce du référentiel des besoins en fertilisation : ce que sa récolte emporte par tonne, et ses
 * rendements de référence (`FertilizationYield`). Le référentiel est commun à toutes les structures et
 * ne s'écrit que par `toolbox/configure/fertilizationReference`, il se lit donc sans condition.
 */
class FertilizationCrop extends FertilizationCropElement {

	// La valeur du champ de l'espèce quand on saisit soi-même ce que la culture mobilise, et l'index
	// de ces trois champs (`nitrogen[manual]`).
	const MANUAL = 'manual';

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
