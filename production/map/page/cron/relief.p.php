<?php
/**
 * Le relief des structures qui ont tracé des zones (ReliefLib::getStructures()), lu à l'IGN par tuiles
 * (ReliefLib::synchronize()), en série et MapSetting::RELIEF_CALLS_MAX appels au plus par passage : ce qui reste attend le
 * passage suivant.
 */
new Page()
	->cron('index', function($data) {

		$calls = map\MapSetting::RELIEF_CALLS_MAX;

		foreach(map\ReliefLib::getStructures() as $structureId) {

			if($calls <= 0) {
				break;
			}

			$calls -= map\ReliefLib::synchronize(new structure\Structure(['id' => $structureId]), $calls);

		}

	}, interval: '30 2 * * *');
?>
