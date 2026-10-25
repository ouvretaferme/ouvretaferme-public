<?php
namespace plant;

class CriterionUi {

	/**
	 * Describe properties
	 */
	public static function p(string $property): \PropertyDescriber {

		$d = Criterion::model()->describer($property, [
			'name' => s("Critère"),
			'type' => s("Famille"),
		]);

		switch($property) {

			case 'type' :
				// Chaque famille répond à une question que se pose la personne qui
				// choisit une variété, d'où des intitulés de son point de vue.
				$d->values = [
					Criterion::PRODUCTION => s("Production"),
					Criterion::RESISTANCE => s("Résistances"),
					Criterion::WORK => s("Travail"),
					Criterion::QUALITY => s("Commercialisation"),
				];
				$d->required = TRUE;
				break;

		}

		return $d;

	}

}
?>
