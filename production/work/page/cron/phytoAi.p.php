<?php
/**
 * Recharge le catalogue E-Phy (work\PhytoProduct, work\PhytoUsage). L'ANSES le publie une fois par
 * semaine, en général le lundi ou le mardi : le mercredi matin prend la publication de la semaine.
 */
new Page()
	->cron('index', function($data) {

		$crops = \work\PhytoProductLib::reload();

		if($crops !== []) {
			echo 'Cultures E-Phy absentes de PhytoUsageLib::CROPS et CROPS_OUTSIDE : '.implode(', ', $crops)."\n";
		}

	}, interval: '15 5 * * 3');
?>
