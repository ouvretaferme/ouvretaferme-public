<?php
namespace toolbox;

/**
 * Un rendement de référence d'une espèce et ce que la plante entière mobilise pour l'atteindre. Le
 * besoin dépend du rendement visé (l'Agroscope publie la carotte de garde à 60 et à 90 t/ha), d'où une
 * ligne par rendement et non par espèce. Même régime que `FertilizationCrop`.
 */
class FertilizationYield extends FertilizationYieldElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
