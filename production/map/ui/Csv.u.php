<?php
namespace map;

class CsvUi {

	public function getExportPlanHeader(): array {

		return [
			'type',
			'parcel',
			'parcel_area',
			'block',
			'block_type',
			'block_length',
			'block_width',
			'block_area',
			'bed_name',
			'bed_length',
			'bed_width',
			'bed_alley_width',
			'bed_area',
			'bed_season_first',
			'bed_season_last',
		];

	}

}
?>
