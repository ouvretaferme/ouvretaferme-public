<?php
namespace sequence;

/**
 * Les plans des densités (`DensityPlanAiUi`) : ce qu'un plan dessine, et les chiffres qui
 * l'accompagnent. La série, les interventions et le calculateur de la Boîte à outils passent
 * tous par ici, pour que la densité d'un plan soit toujours celle que la culture enregistre.
 */
class DensityPlanAiLib {

	/**
	 * Le plan d'une planche ou d'une surface libre, ou un tableau vide quand il n'y a rien à
	 * dessiner : un espacement ou un nombre de rangs qui manque, ou une saisie dont le dessin ne
	 * voudrait rien dire. Sous un centimètre, l'espacement tient de la faute de frappe, et plus
	 * de rangs que de centimètres de planche aussi ; sans cette borne, un mètre de surface libre
	 * à 0,01 cm entre les rangs poserait dix mille cotes dans la page.
	 *
	 * `density` est celle de `CropLib::calculateDensity()`, passe-pieds compris : la case du
	 * quinconce n'y change rien (arbitrage Q4 A de la planche pf-t3).
	 */
	public static function get(string $use, ?int $bedWidth, ?int $alleyWidth, ?int $rows, ?float $plantSpacing, ?float $rowSpacing, bool $stagger): array {

		if($plantSpacing === NULL or $plantSpacing < 1) {
			return [];
		}

		switch($use) {

			case Sequence::BED :
				if($bedWidth === NULL or $rows === NULL or $rows > $bedWidth) {
					return [];
				}
				$rowSpacing = NULL;
				$alleyWidth ??= 0;
				break;

			case Sequence::BLOCK :
				if($rowSpacing === NULL or $rowSpacing < 1) {
					return [];
				}
				$bedWidth = NULL;
				$alleyWidth = NULL;
				$rows = NULL;
				break;

		}

		$plan = [
			'use' => $use,
			'bedWidth' => $bedWidth,
			'alleyWidth' => $alleyWidth,
			'rows' => $rows,
			'plantSpacing' => $plantSpacing,
			'rowSpacing' => $rowSpacing,
			'stagger' => $stagger,
		];

		$plan['density'] = CropLib::calculateDensity(
			new \series\Cultivation($plan),
			new \series\Series(['use' => $use, 'bedWidth' => $bedWidth, 'alleyWidth' => $alleyWidth])
		);

		return $plan;

	}

	/**
	 * Le nombre de plants sur un mètre de planche, tous rangs compris.
	 */
	public static function getPerMeter(array $plan): float {

		return $plan['rows'] * 100 / $plan['plantSpacing'];

	}

}
?>
