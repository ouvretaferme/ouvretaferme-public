<?php
namespace sequence;

/**
 * Le plan des densités : une planche ou une surface libre en perspective (`DensityViewAiUi`),
 * sur trois mètres de long.
 *
 * Né du plan des conduites de la bibliothèque de cultures (`CommunityAiUi`), il sert aussi la
 * page d'une série, les interventions de plantation et de semis direct, et le calculateur des
 * densités de la Boîte à outils (planche pf-t3, arbitrée le 2026-09-28). Tous
 * dessinent la même géométrie (`getGeometry()`). Le plan coté vu de dessus qu'il rendait d'abord
 * a cédé la place à la perspective partout (retours du 28/09, « cette vue utilise le
 * nouvel affichage », « et cette vue aussi », puis « faut utiliser la nouvelle vue » sur la
 * surface libre).
 *
 * Un plan est un tableau `use`, `bedWidth`, `alleyWidth`, `rows`, `plantSpacing`, `rowSpacing`,
 * `stagger`, les propriétés de la série et de la culture qu'il dessine.
 */
class DensityPlanAiUi {

	/**
	 * Le plafond de plants dessinés. La borne basse du schéma est d'un centimètre sur
	 * l'écartement, et le nombre de rangs monte à 255 : sans plafond, une saisie fausse poserait
	 * des dizaines de milliers de nœuds dans la page. Au-delà, les rangs se dessinent en trait
	 * continu, jamais coupé en chemin, ce qui se lirait comme une planche qui s'arrête.
	 */
	const PLANTS = 400;

	public function __construct() {

		\Asset::css('sequence', 'densityPlanAi.css');

	}

	/**
	 * La longueur dessinée : trois mètres (retour du 28/09, « mettre sur 3m », le double
	 * de la largeur rendait six mètres pour une planche de 200 cm et son passe-pied, et le plan
	 * n'était plus qu'une bande), davantage seulement pour ne jamais dessiner plus haut que long.
	 * Des plans posés côte à côte, qui reçoivent tous la même longueur, partagent la même échelle :
	 * une planche de 100 cm s'y lit deux fois moins large qu'une planche de 200.
	 */
	public static function getLength(array $plans): int {

		$widths = array_map(fn(array $plan) => self::getWidth($plan), $plans);

		return max(300, (int)ceil(max($widths) / 100) * 100);

	}

	/**
	 * La largeur dessinée en travers : la planche et un demi passe-pied de chaque côté, ou, en
	 * surface libre, le nombre entier d'écarts entre rangs le plus proche d'un mètre, pour que la
	 * bande dessinée commence et finisse à un demi-écart d'un rang.
	 */
	protected static function getWidth(array $plan): float {

		return match($plan['use']) {
			Sequence::BED => $plan['bedWidth'] + ($plan['alleyWidth'] ?? 0),
			Sequence::BLOCK => max(1, round(100 / $plan['rowSpacing'])) * $plan['rowSpacing']
		};

	}

	/**
	 * La géométrie du plan, en centimètres : la largeur dessinée (`total`), le demi passe-pied de
	 * chaque côté (`half`, nul en surface libre), la planche ou la bande cultivée entre les deux
	 * (`bed`), et la position de chaque rang en travers (`positions`), au milieu de son intervalle.
	 */
	public static function getGeometry(array $plan, int $length): array {

		$total = self::getWidth($plan);

		[$half, $interval] = match($plan['use']) {
			Sequence::BED => [($plan['alleyWidth'] ?? 0) / 2, $plan['bedWidth'] / $plan['rows']],
			Sequence::BLOCK => [0, $plan['rowSpacing']]
		};

		$bed = $total - 2 * $half;
		$positions = [];

		for($position = $half + $interval / 2; $position < $half + $bed; $position += $interval) {
			$positions[] = $position;
		}

		return [
			'use' => $plan['use'],
			'length' => $length,
			'total' => $total,
			'half' => $half,
			'bed' => $bed,
			'positions' => $positions,
		];

	}

	/**
	 * `$reference` est la largeur du plan le plus large posé à côté, pour que les perspectives
	 * partagent l'échelle.
	 */
	public function get(array $plan, int $length, float $reference = 0): string {

		return new DensityViewAiUi()->getIsometric($plan, $length, $reference);

	}

	/**
	 * Les plans d'une culture, un par format de planche, à la même échelle, la coupe sous la
	 * perspective pour une planche comme dans le calculateur, chacun légendé de son
	 * format, de sa densité et de ce que la série en occupe, de même taille sur la série et sur
	 * l'intervention (arbitrage Q2 A de pf-t3). Visibles sur l'intervention, ils s'ouvrent au clic sur
	 * la densité de la série (retour du 28/09, variante Q1 B, `CropUi::getPresentationPlans()`).
	 *
	 * `$plans` vient de `\series\CultivationLib::getDensityPlans()`, qui y ajoute à chaque plan
	 * `density`, et `beds` et `length` pour une planche, `area` pour une surface libre.
	 */
	public function getFigures(array $plans): string {

		if($plans === []) {
			return '';
		}

		$length = self::getLength($plans);
		$reference = max(array_map(fn(array $plan) => self::getWidth($plan), $plans));

		$h = '<div class="density-plan-figures">';

			foreach($plans as $plan) {

				$h .= '<div class="density-plan-figure">';
					$h .= $this->get($plan, $length, $reference);
					if($plan['use'] === Sequence::BED) {
						$h .= new DensityViewAiUi()->getCut($plan, $length, $reference);
					}
					$h .= '<div class="density-plan-caption util-block-inside">';
						$h .= '<b>'.self::getTitle($plan).'</b><br/>';
						$h .= '<span>'.implode(' · ', self::getDetails($plan)).'</span>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public static function getTitle(array $plan): string {

		if($plan['use'] === Sequence::BLOCK) {
			return s("Surface libre");
		}

		if($plan['alleyWidth'] > 0) {
			return s("Planches {bedWidth} cm, passe-pied {alleyWidth} cm", $plan);
		} else {
			return s("Planches {value} cm", $plan['bedWidth']);
		}

	}

	protected static function getDetails(array $plan): array {

		$details = [s("{value} / m²", self::getDensity($plan['density']))];

		if($plan['use'] === Sequence::BED) {

			if($plan['beds'] !== NULL) {
				$details[] = p("{value} planche", "{value} planches", $plan['beds']);
			}

			if($plan['length'] !== NULL) {
				$details[] = s("{value} mL", $plan['length']);
			}

		} else if($plan['area'] !== NULL) {
			$details[] = s("{value} m²", $plan['area']);
		}

		return $details;

	}

	/**
	 * L'arrondi de la présentation d'une culture (`CropUi::getPresentationSpacing()`) : une
	 * décimale sous 10 plants au m², aucune au-delà, et pas de décimale nulle.
	 */
	public static function getDensity(float $density): string {

		$value = ($density < 10) ? round($density, 1) : round($density);

		return \util\TextUi::number($value, (floor($value) === $value) ? 0 : 1);

	}

}
?>
