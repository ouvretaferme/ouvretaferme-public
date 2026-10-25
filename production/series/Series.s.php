<?php
namespace series;

class SeriesSetting extends \Settings {

	const MISSING_WEEKS = 8;
	const DUPLICATE_LIMIT = 10;
	const TASK_CREATE_LIMIT = 50;
	const DUPLICATE_INTERVAL = ['min' => -52, 'max' => 52];

}

?>
