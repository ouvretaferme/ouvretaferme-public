<?php
namespace map;

class DecorUi {

	public static function p(string $property): \PropertyDescriber {

		$d = Decor::model()->describer($property, [
			'kit' => s("Élément"),
			'width' => s("Largeur"),
			'model' => s("Modèle de clôture"),
			'trace' => s("Tracé"),
			'hedge' => s("Haie"),
			'gate' => s("Portail"),
			'diameter' => s("Diamètre"),
			'laying' => s("Pose"),
		]);

		switch($property) {

			case 'model' :
				$d->values = [
					Decor::SHEEP => s("Grillage à mouton"),
					Decor::PALING => s("Ganivelle"),
					Decor::PANEL => s("Panneaux rigides"),
					Decor::ELECTRIC => s("Ruban électrique"),
				];
				break;

			case 'diameter' :
				$d->append = s("mm");
				break;

			case 'laying' :
				$d->values = [
					Decor::SURFACE => s("En surface"),
					Decor::BURIED => s("Enterré"),
				];
				break;

		}

		return $d;

	}

}
?>
