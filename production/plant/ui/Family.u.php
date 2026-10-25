<?php
namespace plant;

/**
 * Ui class for families
 *
 */
class FamilyUi {

	public static function getLetterVignette(Family $eFamily): string {

		\Asset::css('plant', 'family.css');

		if($eFamily->empty()) {

			return '<div title="'.s("Famille non renseignée").'" class="family-vignette-letter family-vignette-letter-empty">–</div>';

		} else {

			$eFamily->expects(['name', 'color']);

			return '<div title="'.encode($eFamily['name']).'" class="family-vignette-letter" style="background-color: '.$eFamily['color'].'">'.mb_substr($eFamily['name'], 0, 2).'</div>';

		}


	}

	/**
	 * Describe properties
	 */
	public static function p(string $property): \PropertyDescriber {

		$d = Family::model()->describer($property, [
			'name' => s("Nom"),
			'fqn' => s("Nom qualifié"),
			'color' => s("Couleur"),
		]);

		return $d;

	}

}
?>
