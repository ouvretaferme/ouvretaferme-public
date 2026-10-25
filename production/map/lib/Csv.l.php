<?php
namespace map;

class CsvLib {

	public static function getExportPlan(\structure\Structure $eStructure, int $season): array {

		$cZone = ZoneLib::getByStructure($eStructure, season: $season);
		PlotLib::putFromZone($cZone, withBeds: TRUE, season: $season);

		$emptyPlot = ['', '', '', '', ''];
		$emptyBed = ['', '', '', '', '', '', ''];

		$output = [];

		foreach($cZone as $eZone) {

			$zone = [$eZone['name'], $eZone['area']];

			$output[] = array_merge(['parcel'], $zone, $emptyPlot, $emptyBed);

			foreach($eZone['cPlot'] as $ePlot) {

				if($ePlot['zoneFill']) {
					$plot = $emptyPlot;
				} else {

					$plot = [
						$ePlot['name'],
						match($ePlot['mode']) {
							Plot::GREENHOUSE => 'greenhouse',
							Plot::OPEN_FIELD => 'open_field'
						},
						\util\TextUi::csvNumber($ePlot['length']),
						\util\TextUi::csvNumber($ePlot['width']),
						$ePlot['area'],
					];

					$output[] = array_merge(['block'], $zone, $plot, $emptyBed);

				}

				$cBed = $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE);

				foreach($cBed as $eBed) {

					$output[] = array_merge(['bed'], $zone, $plot, [
						$eBed['name'],
						\util\TextUi::csvNumber($eBed['length']),
						$eBed['width'],
						$eBed['alleyWidth'],
						\util\TextUi::csvNumber($eBed['area']),
						$eBed['seasonFirst'],
						$eBed['seasonLast'],
					]);

				}

			}

		}

		return $output;

	}

}
?>
