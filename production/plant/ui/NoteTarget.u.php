<?php
namespace plant;

/**
 * Ce qu'une note montre d'une espèce à laquelle elle est rattachée (`NoteTargetLib`).
 */
class NoteTargetUi {

	public static function getGroup(): string {
		return s("Espèce");
	}

	public static function getLabel(Plant $ePlant): string {
		return $ePlant['name'];
	}

	public static function getIcon(Plant $ePlant): ?string {
		return PlantUi::hasVignette($ePlant['fqn']) ? PlantUi::getVignetteHref($ePlant['fqn']) : NULL;
	}

	public static function getDetail(Plant $ePlant): ?string {
		return NULL;
	}

}
?>
