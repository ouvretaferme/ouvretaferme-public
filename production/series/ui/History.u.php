<?php
namespace series;

class HistoryUi {

	public static function p(string $property): \PropertyDescriber {

		$d = History::model()->describer($property);

		switch($property) {

			case 'event' :
				$d->values = [
					History::DONE => s("Marquée comme faite"),
					History::TODO => s("Remise à faire"),
					History::TIME => s("Temps de travail"),
					History::HARVEST => s("Récolte"),
				];
				break;

		}

		return $d;

	}

}
?>
