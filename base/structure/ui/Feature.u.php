<?php
namespace structure;

class FeatureUi {

	public function __construct() {
		\Asset::css('structure', 'structure.css');
	}

	/**
	 * Les interrupteurs d'un producteur, sur la forme de `StructureCommunityUi::getFeatures()` : une carte par
	 * fonctionnalité, appliquée au geste, confirmée à l'extinction seulement.
	 *
	 * Le temps de travail et la RH dépendent de la production, ce que leur retrait sous sa carte montre. Production
	 * éteinte, leurs interrupteurs restent visibles, éteints et verrouillés, avec la raison : leur valeur reste en
	 * base et revient au rallumage, éteindre la production n'écrivant ni l'une ni l'autre.
	 */
	public function getFeatures(Structure $eStructure, bool $hrSubscription): string {

		$production = $eStructure->conf(Feature::class)['production'];
		$time = ($production and $eStructure->conf()['time']);
		$hr = $eStructure->acceptHr();

		$h = '<div class="util-cards">';

			$h .= $this->getDeck(
				'leaf', s("Production"), s("Le planning, les séries, l'assolement, les espèces, le matériel et les analyses de culture."),
				$production,
				[
					'data-ajax' => '/structure/feature:doUpdateProduction',
					'post-id' => $eStructure->conf(Feature::class)['id'],
					'post-production' => $production ? 0 : 1,
					'data-confirm' => $production ? $this->getConfirmProduction($time, $hr) : NULL,
				]
			);

			$h .= '<div class="util-cards structure-features-dependent">';

			$h .= $this->getDeck(
				'clock', s("Temps de travail"), s("Le temps passé sur chaque intervention du planning, les heures de travail par jour et l'analyse du temps de travail."),
				$time,
				[
					'data-ajax' => '/structure/configuration:doUpdateTime',
					'post-id' => $eStructure->conf()['id'],
					'post-time' => $time ? 0 : 1,
					'data-confirm' => $time ? $this->getConfirmTime($hr) : NULL,
					'disabled' => ($production === FALSE),
				],
				info: $production ? '' : s("Le temps de travail se saisit dans le planning et ne s'active qu'avec la production.")
			);

			if($production === FALSE) {
				$hrInfo = s("La RH ne s'active qu'avec la production.");
			} else if($hrSubscription) {
				$hrInfo = s("À partir du 1<sup>er</sup> novembre, la RH sera réservée aux offres Gestion et Pilotage.");
			} else {
				$hrInfo = '';
			}

			// La valeur part sous `value` : l'analyseur HTML abaisse les noms d'attributs (`feature.p.php`).
			$h .= $this->getDeck(
				'people-fill', s("RH"), s("Les contrats de travail, les absences, les heures de travail par jour, les heures supplémentaires et la synthèse mensuelle à transmettre pour la paie."),
				$hr,
				[
					'data-ajax' => '/structure/feature:doUpdateHr',
					'post-id' => $eStructure->conf(Feature::class)['id'],
					'post-value' => $hr ? 0 : 1,
					'data-confirm' => $hr ? $this->getConfirmHr($time) : NULL,
					'disabled' => ($production === FALSE),
				],
				info: $hrInfo
			);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getDeck(string $icon, string $title, string $text, bool $active, array $attributes, string $info = ''): string {

		$h = '<div class="util-deck">';
			$h .= '<div class="util-deck-head">';

				// Jamais `util-icon-muted` : le gris dit « indisponible », et une fonctionnalité éteinte reste disponible.
				$h .= '<div class="util-icon util-icon-round '.($active ? 'util-icon-active' : 'util-icon-inactive').'">'.\Asset::icon($icon).'</div>';

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.$title.'</div>';
					$h .= '<p>'.$text.'</p>';
					if($info !== '') {
						$h .= \util\FormUi::info($info);
					}
				$h .= '</div>';

				$h .= '<div class="util-deck-aside">'.\util\TextUi::switch($attributes, $active, textOn: s("Oui"), textOff: s("Non")).'</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	// Une confirmation ne nomme que ce qui est allumé : elle dit ce que le geste retire.
	protected function getConfirmProduction(bool $time, bool $hr): string {

		if($time and $hr) {
			$dependent = s("Le temps de travail et la RH s'éteignent avec elle.").' ';
		} else if($time) {
			$dependent = s("Le temps de travail s'éteint avec elle.").' ';
		} else if($hr) {
			$dependent = s("La RH s'éteint avec elle.").' ';
		} else {
			$dependent = '';
		}

		return s("La production ne s'affichera plus.").' '.$dependent.s("Les données déjà saisies sont conservées et reviendront si vous rallumez la production. Continuer ?");

	}

	protected function getConfirmHr(bool $time): string {

		if($time) {
			$removed = s("Les contrats, les absences, les heures supplémentaires et la synthèse mensuelle ne s'afficheront plus.");
		} else {
			$removed = s("Les contrats, les absences, la saisie de la journée, les heures supplémentaires et la synthèse mensuelle ne s'afficheront plus.");
		}

		return $removed.' '.s("Les données déjà saisies sont conservées et reviendront si vous rallumez la RH. Continuer ?");

	}

	protected function getConfirmTime(bool $hr): string {

		if($hr) {
			$removed = s("Le temps passé sur les interventions et l'analyse du temps de travail ne s'afficheront plus ; la saisie de la journée reste ouverte par la RH.");
		} else {
			$removed = s("La saisie des heures et l'analyse du temps de travail ne s'afficheront plus.");
		}

		return $removed.' '.s("Les données déjà saisies sont conservées et reviendront si vous rallumez le temps de travail. Continuer ?");

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Structure::model()->describer($property, [
		]);

		switch($property) {

		}

		return $d;

	}

}
