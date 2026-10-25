<?php
namespace learning;

class LevelUi {

	public static function p(string $property): \PropertyDescriber {

		return Level::model()->describer($property, [
			'name' => s("Nom"),
		]);

	}

}
?>
