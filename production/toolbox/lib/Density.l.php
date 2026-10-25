<?php
namespace toolbox;

/**
 * Le calculateur des densités (planche pf-t3, arbitrage Q3 A du 2026-09-28) : les champs
 * du panneau de série, une planche ou une surface libre, et ce qui s'y plante. Rien ne s'enregistre.
 *
 * La saisie passe par le `build()` d'une série et d'une culture neuves, jamais par une lecture de
 * `$_POST` : le calculateur accepte et refuse exactement ce que la série et sa culture accepteraient,
 * et sa densité est celle que la culture enregistrerait (`\sequence\DensityPlanAiLib`).
 */
class DensityLib {

	const SERIES = ['use', 'bedWidth', 'alleyWidth', 'lengthTarget', 'areaTarget'];
	const CULTIVATION = ['rows', 'plantSpacing', 'rowSpacing', 'stagger'];

	/**
	 * Ce que le calculateur montre à l'ouverture : les planches de la ferme quand elle en a déclaré,
	 * largeurs et longueur (« on devrait utiliser la longueur des planches par défaut de la ferme si
	 * elle est connue », le 29/09), et la planche de la planche pf-t3 pour le reste, pour qu'un
	 * plan se dessine avant toute saisie.
	 */
	public static function getNew(\structure\Structure $eStructure): array {

		$eSeries = new \series\Series([
			'use' => \series\Series::BED,
			'bedWidth' => $eStructure->conf(\map\Configuration::class)['defaultBedWidth'] ?? 100,
			'alleyWidth' => $eStructure->conf(\map\Configuration::class)['defaultAlleyWidth'] ?? 50,
			'lengthTarget' => $eStructure->conf(\map\Configuration::class)['defaultBedLength'] ?? 20,
			'areaTarget' => 100,
		]);

		$eCultivation = new \series\Cultivation([
			'distance' => \series\Cultivation::SPACING,
			'rows' => 4,
			'plantSpacing' => 30,
			'rowSpacing' => 40,
			'stagger' => FALSE,
		]);

		return [$eSeries, $eCultivation];

	}

	/**
	 * Le calculateur n'enregistre rien : son premier geste est de l'ouvrir. C'est donc la première
	 * visite qui en fait un outil de la structure (`Feature.hasToolbox`), ce qui lui donne sa roue et
	 * son étoile (`ToolUi::getStar()`) et le range sous « Vos outils » dans la Boîte. La fonctionnalité
	 * en mémoire suit l'écriture, pour que l'en-tête de cette même visite porte déjà la roue.
	 */
	public static function open(\structure\Structure $eStructure): void {

		if(in_array(Favorite::DENSITY, $eStructure->conf(\structure\Feature::class)['hasToolbox'])) {
			return;
		}

		\structure\FeatureLib::addToolbox($eStructure, Favorite::DENSITY);

		\structure\FeatureLib::forget($eStructure);

	}

	/**
	 * Un champ vidé est une saisie en cours, pas une erreur : il ne se construit pas, et le résultat
	 * redevient vide (`getPlan()`). Sans cet écart, effacer la largeur de planche pour la retaper
	 * lèverait `bedWidth.required` sous les doigts. La case du quinconce, décochée, n'est pas postée :
	 * elle se construit toujours, `NULL` y valant `FALSE`.
	 */
	public static function build(array $input): array {

		$eSeries = new \series\Series(array_fill_keys(self::SERIES, NULL));
		$eSeries->build(self::getFilled(self::SERIES, $input), $input);

		$eCultivation = new \series\Cultivation(['series' => $eSeries, 'distance' => \series\Cultivation::SPACING] + array_fill_keys(self::CULTIVATION, NULL));
		$eCultivation->build([...self::getFilled(['rows', 'plantSpacing', 'rowSpacing'], $input), 'stagger'], $input);

		return [$eSeries, $eCultivation];

	}

	protected static function getFilled(array $properties, array $input): array {

		return array_values(array_filter($properties, fn(string $property) => ($input[$property] ?? '') !== ''));

	}

	/**
	 * Le plan de `\sequence\DensityPlanAiLib::get()`, et ce que le calculateur en tire : les plants par
	 * mètre de planche, ceux de la longueur ou de la surface saisie. Le quinconce ne change aucun de
	 * ces chiffres (arbitrage Q4 A). Un tableau vide tant que la saisie ne dessine rien.
	 */
	public static function getPlan(\series\Series $eSeries, \series\Cultivation $eCultivation): array {

		if($eSeries['use'] === NULL) {
			return [];
		}

		$plan = \sequence\DensityPlanAiLib::get($eSeries['use'], $eSeries['bedWidth'], $eSeries['alleyWidth'], $eCultivation['rows'], $eCultivation['plantSpacing'], $eCultivation['rowSpacing'], $eCultivation['stagger']);

		if($plan === []) {
			return [];
		}

		switch($plan['use']) {

			case \series\Series::BED :
				$plan['perMeter'] = \sequence\DensityPlanAiLib::getPerMeter($plan);
				$plan['length'] = $eSeries['lengthTarget'];
				$plan['total'] = ($plan['length'] === NULL) ? NULL : $plan['perMeter'] * $plan['length'];
				break;

			case \series\Series::BLOCK :
				$plan['area'] = $eSeries['areaTarget'];
				$plan['total'] = ($plan['area'] === NULL) ? NULL : $plan['density'] * $plan['area'];
				break;

		}

		return $plan;

	}

}
?>
