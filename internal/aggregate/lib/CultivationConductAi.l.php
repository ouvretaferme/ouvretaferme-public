<?php
namespace aggregate;

/**
 * Les conduites d'une espèce : comment les structures montent leurs planches.
 *
 * Une **conduite** est un quadruplet — largeur de planche, nombre de rangs, écartement
 * sur le rang, mode — que plusieurs structures pratiquent à l'identique. Ce n'est pas
 * une moyenne : c'est un cas réel, et le nombre de structures qui le pratiquent dit
 * tout ce qu'il y a à en dire.
 *
 * Rien ici n'écrit ni ne requête. Tout se joue sur les colonnes que la projection a
 * déposées (`CultivationProjectionAiLib`), sur la collection **déjà filtrée** par
 * `CultivationCropPlanAiLib::findByFilters()` : les conduites se comptent donc sur
 * exactement le même échantillon que les chiffres affichés au-dessus d'elles, et le
 * bandeau de filtres agit sur les deux. Les seuils viennent de `CultivationAiSetting`
 * et se déplacent sans rien reconstruire.
 */
class CultivationConductAiLib {

	/**
	 * Les modes que l'écran sait dire, dans l'ordre où il les montre (le
	 * 2026-09-21 : « le top 3 PC et le top 3 sous abri »). Chacun a sa couleur dans
	 * le socle, `--open-field` et `--greenhouse`.
	 */
	const MODES = [Cultivation::OPEN_FIELD, Cultivation::GREENHOUSE];

	/**
	 * Les conduites d'une largeur de planche, et de quoi en choisir une autre.
	 *
	 * `$bedWidth` est la largeur demandée par l'adresse ou, à défaut, celle que la
	 * structure connectée a réglée. Elle se ramène toujours à une largeur de
	 * `CultivationAiSetting::BED_WIDTHS` (`resolveBedWidth()`), et c'est la largeur
	 * résolue qui revient à l'appelant : la page la repose dans ses filtres, pour que
	 * ses liens la portent telle quelle.
	 *
	 * `CultivationAiSetting::BED_WIDTH_ALL` réunit les conduites de toutes les largeurs de
	 * la liste. Elles ne servent alors qu'aux bandes : `modes` reste vide.
	 *
	 * Rendu :
	 * - `bedWidth` : la largeur ouverte, `BED_WIDTH_ALL` pour toutes, `NULL` si l'espèce n'a aucune conduite ;
	 * - `widths` : pour chaque largeur de la liste, ses conduites et ses structures ;
	 * - `modes` : par mode, les plus pratiquées (`top`) et leur nombre total ;
	 * - `bands` : l'axe des densités et sa grille de fond, et par mode son histogramme, son échelle et sa médiane ;
	 * - `map` : les rangs et écartements rencontrés, et toutes les conduites ;
	 * - `peak` : l'effectif de la conduite la plus pratiquée, qui fixe les tailles ;
	 * - `yieldUnit` : l'unité des objectifs de rendement (`getYieldUnit()`).
	 */
	public static function getConducts(\Collection $cCultivation, ?int $bedWidth): array {

		$cultivations = self::getOnBed($cCultivation);
		$yieldUnit = self::getYieldUnit($cultivations);

		$groups = [];
		$widths = [];

		foreach(CultivationAiSetting::BED_WIDTHS as $width => $bedWidths) {

			$groups[$width] = self::getByBedWidth($cultivations, $bedWidths, $yieldUnit);

			// Les largeurs de la liste fermée sont toujours proposées, y compris celles
			// qui ne portent aucune conduite : le choix ne change pas d'une espèce à
			// l'autre, et une largeur vide dit quelque chose de l'espèce.
			$widths[$width] = [
				'bedWidths' => $bedWidths,
				'conducts' => count($groups[$width]),
				'structures' => self::countStructures($groups[$width]),
			];

		}

		$bedWidth = self::resolveBedWidth($bedWidth, $widths);

		if($bedWidth === NULL) {
			$conducts = [];
		} else if($bedWidth === CultivationAiSetting::BED_WIDTH_ALL) {
			$conducts = array_merge(...array_values($groups));
		} else {
			$conducts = $groups[$bedWidth];
		}

		$modes = ($bedWidth === CultivationAiSetting::BED_WIDTH_ALL) ? [] : self::getModes($conducts);

		return [
			'bedWidth' => $bedWidth,
			'widths' => $widths,
			'modes' => $modes,
			'bands' => self::getBands($conducts),
			'map' => self::getMap($conducts),
			'peak' => ($conducts === []) ? 0 : max(array_column($conducts, 'structures')),
			'yieldUnit' => $yieldUnit,
		];

	}

	/**
	 * L'unité dans laquelle l'espèce annonce le plus ses objectifs de rendement, comptée en
	 * cultures qui en portent un, toutes largeurs confondues, la première de l'enum à égalité.
	 *
	 * Une médiane ne mêle pas deux unités, et l'espèce entière n'en garde qu'une pour que
	 * ses cartes se comparent d'une largeur à l'autre. Le kilo seul ne convient pas : mesuré
	 * le 2026-09-21, la laitue porte 3 999 objectifs en pièces contre 264 en kg, le radis
	 * botte 2 080 en bottes contre 108 en kg (« a priori on parle plutôt de pièces
	 * non ? »).
	 */
	protected static function getYieldUnit(array $cultivations): string {

		$counts = array_fill_keys([Cultivation::KG, Cultivation::UNIT, Cultivation::BUNCH], 0);

		foreach($cultivations as $cultivation) {

			if($cultivation['yieldExpected'] !== NULL) {
				$counts[$cultivation['mainUnit']]++;
			}

		}

		return array_search(max($counts), $counts, TRUE);

	}

	/**
	 * La largeur ouverte.
	 *
	 * Une largeur donnée ouvre le groupe qui porte la largeur de la liste la plus proche,
	 * la plus étroite à égale distance (90 ouvre 75 - 80, 110 ouvre 100) : une structure réglée hors liste
	 * voit ainsi la planche qui ressemble le plus à la sienne (2026-09-21).
	 * Une largeur de la liste qui ne porte rien reste ouverte, et l'écran le dit :
	 * basculer seul sur une autre ferait croire que c'est celle qu'on a demandée.
	 *
	 * Sans largeur, la plus pratiquée en structures, la plus étroite à égalité, et
	 * `NULL` quand aucune ne porte de conduite. Toutes les largeurs restent ouvertes
	 * tant qu'une seule porte une conduite, sans quoi l'écran dirait « aucune sur
	 * cette largeur » d'une largeur qui n'existe pas.
	 */
	protected static function resolveBedWidth(?int $bedWidth, array $widths): ?int {

		if($bedWidth === CultivationAiSetting::BED_WIDTH_ALL) {
			return (array_sum(array_column($widths, 'structures')) > 0) ? $bedWidth : NULL;
		}

		if($bedWidth !== NULL) {

			$nearest = NULL;
			$nearestGroup = NULL;

			foreach($widths as $width => $group) {

				foreach($group['bedWidths'] as $member) {

					if($nearest === NULL or abs($member - $bedWidth) < abs($nearest - $bedWidth)) {
						$nearest = $member;
						$nearestGroup = $width;
					}

				}

			}

			return $nearestGroup;

		}

		$best = NULL;

		foreach($widths as $width => $group) {

			if($group['structures'] > 0 and ($best === NULL or $group['structures'] > $widths[$best]['structures'])) {
				$best = $width;
			}

		}

		return $best;

	}

	/**
	 * Les plus pratiquées de chaque mode, la liste étant déjà triée par
	 * `getByBedWidth()`. Un mode sans conduite n'a pas d'entrée : le filtre de mode de
	 * la page, qui vide l'autre en amont, n'en laisse ainsi qu'un.
	 */
	protected static function getModes(array $conducts): array {

		$modes = [];

		foreach(self::MODES as $mode) {

			$conductsMode = self::forget(self::findByMode($conducts, $mode));

			if($conductsMode === []) {
				continue;
			}

			$modes[$mode] = [
				'top' => array_slice($conductsMode, 0, CultivationAiSetting::CONDUCT_TOP),
				'total' => count($conductsMode),
			];

		}

		return $modes;

	}

	/**
	 * La répartition des cultures selon la densité, un histogramme par mode sur un axe
	 * commun aux deux, dont les barres suivent les intervalles de la grille de fond.
	 * L'échelle verticale est propre à chaque mode : sous abri pèse souvent dix fois
	 * moins que plein champ, et sur une échelle commune sa forme disparaîtrait.
	 *
	 * Rien de ce qui sort ne se rattache à une culture. L'histogramme se compte sur les
	 * **médianes des conduites** passées le seuil, chacune pesant son nombre de
	 * cultures, et l'axe se borne sur ces mêmes médianes : une densité extrême saisie
	 * par une seule structure ne déplace ni l'un ni l'autre. La médiane affichée, elle,
	 * se prend sur les cultures retenues, jamais sur les médianes des conduites, qui
	 * donneraient à une conduite de six cultures le poids d'une de quatre-vingts.
	 */
	protected static function getBands(array $conducts): array {

		if($conducts === []) {
			return ['axis' => NULL, 'modes' => []];
		}

		$axis = self::getAxis(array_column($conducts, 'density'));
		$axis['grid'] = self::getGrid($axis);

		$step = self::getGridStep($axis);

		$bands = [];

		foreach(self::MODES as $mode) {

			$conductsMode = self::findByMode($conducts, $mode);

			if($conductsMode === []) {
				continue;
			}

			$densities = array_merge(...array_column($conductsMode, 'densityValues'));
			$bars = self::getBars($conductsMode, $axis, $step);

			$bands[$mode] = [
				'conducts' => count($conductsMode),
				'cultivations' => count($densities),
				'density' => self::getMedian($densities),
				'bars' => $bars,
				'scale' => self::getScale(max(array_column($bars, 'cultivations'))),
			];

		}

		return ['axis' => $axis, 'modes' => $bands];

	}

	/**
	 * L'axe des densités : des bornes rondes qui encadrent les médianes des conduites
	 * d'une marge, et les graduations intérieures.
	 *
	 * La marge laisse à l'histogramme de l'air de part et d'autre de sa première et
	 * de sa dernière conduite. Le pas est 1, 2, 2,5 ou 5 fois une puissance de dix, le
	 * plus petit qui ne dépasse pas `CONDUCT_AXIS_TICKS` graduations : l'écran en
	 * écrit autant que la largeur de la bande en laisse lire (PlantCommunity.fitAxis).
	 */
	protected static function getAxis(array $densities): array {

		$low = min($densities);
		$high = max($densities);

		$span = ($high > $low) ? $high - $low : max($high, 1.0);
		$low = max(0.0, $low - $span * 0.2);
		$high = $high + $span * 0.2;

		$raw = ($high - $low) / CultivationAiSetting::CONDUCT_AXIS_TICKS;
		$power = 10 ** floor(log10($raw));

		$step = $power * 10;

		foreach([1, 2, 2.5, 5] as $factor) {

			if($raw <= $power * $factor) {
				$step = $power * $factor;
				break;
			}

		}

		$min = floor($low / $step) * $step;
		$max = ceil($high / $step) * $step;

		$ticks = [];

		for($tick = $min + $step; $tick < $max - $step / 2; $tick += $step) {
			$ticks[] = round($tick, 6);
		}

		return [
			'min' => round($min, 6),
			'max' => round($max, 6),
			'step' => round($step, 6),
			'ticks' => $ticks,
		];

	}

	/**
	 * Les traits de fond de la bande, qui découpent l'axe comme les semaines découpent le
	 * calendrier : chaque intervalle entre deux graduations est partagé en parts égales,
	 * autant que l'axe en porte sans dépasser `CONDUCT_GRID_MAX` intervalles. Chaque
	 * graduation porte donc un trait de la grille, par construction.
	 *
	 * Les bornes de l'axe n'ont pas de trait : il doublerait le bord de la bande. Un trait
	 * posé sur une graduation le dit, l'écran le marque davantage, comme un début de mois.
	 */
	protected static function getGrid(array $axis): array {

		$span = $axis['max'] - $axis['min'];
		$step = self::getGridStep($axis);

		$ticks = array_flip(array_map('strval', $axis['ticks']));
		$lines = [];

		for($line = 1; $line < round($span / $step); $line++) {

			$value = round($axis['min'] + $line * $step, 6);

			$lines[] = [
				'value' => $value,
				'tick' => array_key_exists((string)$value, $ticks),
			];

		}

		return $lines;

	}

	/**
	 * Le pas de la grille : celui des graduations divisé par 10, 5, 4 ou 2, la division
	 * la plus fine qui tienne. L'axe compte entre 3 et 12 intervalles de graduation, ce
	 * qui donne toujours entre 15 et 30 intervalles de grille.
	 */
	protected static function getGridStep(array $axis): float {

		$intervals = (int)round(($axis['max'] - $axis['min']) / $axis['step']);

		foreach([10, 5, 4, 2] as $parts) {

			if($intervals * $parts <= CultivationAiSetting::CONDUCT_GRID_MAX) {
				return round($axis['step'] / $parts, 6);
			}

		}

		return $axis['step'];

	}

	/**
	 * Les barres d'un mode, une par intervalle de la grille qui porte des cultures :
	 * chaque conduite verse ses cultures dans l'intervalle de sa densité médiane. Un
	 * intervalle se ferme à gauche et s'ouvre à droite, sauf le dernier, fermé des deux
	 * côtés pour ne pas perdre une conduite posée sur la borne de l'axe.
	 */
	protected static function getBars(array $conducts, array $axis, float $step): array {

		$intervals = (int)round(($axis['max'] - $axis['min']) / $step);
		$counts = array_fill(0, $intervals, 0);

		foreach($conducts as $conduct) {
			$interval = min($intervals - 1, (int)floor(($conduct['density'] - $axis['min']) / $step + 1e-9));
			$counts[$interval] += $conduct['cultivations'];
		}

		$bars = [];

		foreach($counts as $interval => $cultivations) {

			if($cultivations === 0) {
				continue;
			}

			$bars[] = [
				'from' => round($axis['min'] + $interval * $step, 6),
				'to' => round($axis['min'] + ($interval + 1) * $step, 6),
				'cultivations' => $cultivations,
			];

		}

		return $bars;

	}

	/**
	 * L'échelle verticale d'un histogramme, en nombre de cultures : un pas de 1, 2 ou 5
	 * fois une puissance de dix, le plus petit qui couvre la plus haute barre en au plus
	 * `CONDUCT_SCALE_TICKS` graduations. Le haut de l'échelle est sa dernière graduation,
	 * pour que la plus haute barre se lise contre un trait de la grille.
	 */
	protected static function getScale(int $top): array {

		$power = 1;

		while(TRUE) {

			foreach([1, 2, 5] as $factor) {

				$step = $power * $factor;

				if(ceil($top / $step) <= CultivationAiSetting::CONDUCT_SCALE_TICKS) {

					$max = (int)(ceil($top / $step) * $step);

					return [
						'max' => $max,
						'ticks' => range($step, $max, $step),
					];

				}

			}

			$power *= 10;

		}

	}

	/**
	 * La grille de la vue experte : les nombres de rangs et les écartements que portent
	 * les conduites, croissants, et les conduites elles-mêmes, chacune placée par ses
	 * deux valeurs.
	 */
	protected static function getMap(array $conducts): array {

		$rows = array_values(array_unique(array_column($conducts, 'rows')));
		$spacings = array_values(array_unique(array_column($conducts, 'plantSpacing')));

		sort($rows);
		sort($spacings);

		return [
			'modes' => array_values(array_intersect(self::MODES, array_column($conducts, 'mode'))),
			'rows' => $rows,
			'spacings' => $spacings,
			'conducts' => self::forget($conducts),
		];

	}

	protected static function findByMode(array $conducts, string $mode): array {
		return array_values(array_filter($conducts, fn(array $conduct) => $conduct['mode'] === $mode));
	}

	/**
	 * Ce qui a servi à compter ne sort pas de ce Lib.
	 *
	 * Les identifiants de structure ne servent qu'à compter sans doublon
	 * (`countStructures()`), et les valeurs de chaque culture qu'à prendre les médianes
	 * : les rendre à l'appelant offrirait à n'importe quel écran de quoi défaire
	 * l'anonymat que le seuil pose, en recoupant deux espèces ou en lisant une série de
	 * valeurs dont plusieurs n'appartiennent qu'à une structure.
	 */
	protected static function forget(array $conducts): array {

		foreach($conducts as &$conduct) {
			unset($conduct['structureIds'], $conduct['densityValues'], $conduct['yieldValues']);
		}

		// La référence survit à la boucle : une écriture ultérieure sur $conduct
		// écraserait la dernière conduite du tableau.
		unset($conduct);

		return $conducts;

	}

	/**
	 * Les cultures qui décrivent une planche, réduites à ce qui fait une conduite.
	 *
	 * Une culture en bloc n'a pas de planche, et une culture dont l'un des quatre
	 * termes manque ne peut se ranger nulle part : le remplissage mesuré le 2026-09-20
	 * va de 91 % (`rows`) à 98 % (`bedWidth`) des lignes en ligne. La densité est
	 * exigée en plus des quatre, parce que c'est elle qui place la conduite sur la
	 * courbe.
	 *
	 * Une culture en mode mixte est écartée : l'écran ne parle que de plein champ et
	 * de sous abri, chacun dans sa couleur, et mesuré le 2026-09-21 sur les cultures
	 * en ligne en planche, le mixte en porte 554 sur 45 241.
	 */
	protected static function getOnBed(\Collection $cCultivation): array {

		$cultivations = [];

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['use'] !== Cultivation::BED) {
				continue;
			}

			if(in_array($eCultivation['mode'], self::MODES, TRUE) === FALSE) {
				continue;
			}

			if(
				$eCultivation['bedWidth'] === NULL or
				$eCultivation['rows'] === NULL or
				$eCultivation['plantSpacing'] === NULL or
				$eCultivation['density'] === NULL
			) {
				continue;
			}

			$cultivations[] = [
				'bedWidth' => (int)$eCultivation['bedWidth'],
				'rows' => (int)$eCultivation['rows'],
				'plantSpacing' => (float)$eCultivation['plantSpacing'],
				'mode' => $eCultivation['mode'],
				'density' => (float)$eCultivation['density'],
				'structure' => $eCultivation['structure']['id'],
				'yieldExpected' => $eCultivation['yieldExpected'],
				'mainUnit' => $eCultivation['mainUnit'],
			];

		}

		return $cultivations;

	}

	/**
	 * Les conduites d'un groupe de largeurs de planche, la plus pratiquée en tête. Une
	 * conduite réunit les cultures de toutes les largeurs du groupe. Seuls les objectifs
	 * dans `$yieldUnit` entrent dans la médiane.
	 *
	 * Toutes les conduites d'un groupe se dessinent sur la plus large de ses planches
	 * (2026-09-25, « afficher les distances entre rangs sur la base 80cm ») :
	 * une largeur tirée de la majorité de chaque conduite faisait passer la référence de
	 * 75 à 80 cm d'une carte à la suivante, et les cotes entre rangs ne se comparaient
	 * plus. La densité, elle, reste la médiane des cultures à leur propre largeur.
	 */
	protected static function getByBedWidth(array $cultivations, array $bedWidths, string $yieldUnit): array {

		$bedWidthDrawn = max($bedWidths);

		$buckets = [];

		foreach($cultivations as $cultivation) {

			if(in_array($cultivation['bedWidth'], $bedWidths, TRUE) === FALSE) {
				continue;
			}

			$key = $cultivation['rows'].'/'.$cultivation['plantSpacing'].'/'.$cultivation['mode'];

			$buckets[$key] ??= [
				'rows' => $cultivation['rows'],
				'plantSpacing' => $cultivation['plantSpacing'],
				'mode' => $cultivation['mode'],
				'structures' => [],
				'densities' => [],
				'yields' => [],
			];

			$buckets[$key]['structures'][$cultivation['structure']] = TRUE;
			$buckets[$key]['densities'][] = $cultivation['density'];

			if($cultivation['yieldExpected'] !== NULL and $cultivation['mainUnit'] === $yieldUnit) {
				$buckets[$key]['yields'][] = (float)$cultivation['yieldExpected'];
			}

		}

		$conducts = [];

		foreach($buckets as $bucket) {

			if(count($bucket['structures']) < CultivationAiSetting::CONDUCT_STRUCTURES_MIN) {
				continue;
			}

			$density = self::getMedian($bucket['densities']);
			$yields = self::trimYields($bucket['yields']);

			// Sous le seuil, l'objectif se tait plutôt que de s'afficher comme les
			// autres. Le seuil se compte sur les cultures qui annoncent un objectif,
			// avant l'écartement des déciles : c'est ce nombre que l'écran énonce.
			$yieldExpected = (count($bucket['yields']) >= CultivationAiSetting::CONDUCT_YIELD_CULTIVATIONS_MIN) ?
				self::getMedian($yields) :
				NULL;

			$conducts[] = [
				'bedWidth' => $bedWidthDrawn,
				'rows' => $bucket['rows'],
				'plantSpacing' => $bucket['plantSpacing'],
				'mode' => $bucket['mode'],
				'structures' => count($bucket['structures']),
				'structureIds' => array_keys($bucket['structures']),
				'cultivations' => count($bucket['densities']),
				'density' => $density,
				'densityValues' => $bucket['densities'],
				'yieldExpected' => $yieldExpected,
				'yieldValues' => $yields,
				'yieldCultivations' => count($yields),
			];

		}

		usort($conducts, function(array $first, array $second) {
			return [$second['structures'], $second['cultivations'], $first['rows']]
				<=> [$first['structures'], $first['cultivations'], $second['rows']];
		});

		return $conducts;

	}

	/**
	 * Les objectifs de rendement, la même part écartée en bas et en haut. Les bornes
	 * sont des valeurs et non des rangs : une valeur égale à la borne reste, sans quoi
	 * deux cultures au même objectif seraient traitées différemment selon leur place
	 * dans le tri.
	 */
	protected static function trimYields(array $yields): array {

		if($yields === []) {
			return [];
		}

		sort($yields);

		$trim = (int)(CultivationAiSetting::CONDUCT_YIELD_TRIM * count($yields));

		$low = $yields[$trim];
		$high = $yields[count($yields) - 1 - $trim];

		return array_values(array_filter($yields, fn(float $yield) => $yield >= $low and $yield <= $high));

	}

	/**
	 * Le nombre de structures d'un ensemble de conduites.
	 *
	 * Additionner les effectifs des conduites donnerait un nombre plus grand que le
	 * parc : une structure qui pratique deux conduites de la même largeur compte dans
	 * les deux. Mesuré le 2026-09-21 sur la tomate plantée, groupe de 75 : ses quatre
	 * conduites additionnent 26 structures, le groupe n'en compte que 20.
	 */
	protected static function countStructures(array $conducts): int {

		$structures = [];

		foreach($conducts as $conduct) {

			foreach($conduct['structureIds'] as $structureId) {
				$structures[$structureId] = TRUE;
			}

		}

		return count($structures);

	}

	/**
	 * La médiane d'une liste de valeurs, vide si la liste l'est.
	 */
	protected static function getMedian(array $values): ?float {

		if($values === []) {
			return NULL;
		}

		sort($values);

		$count = count($values);
		$middle = intdiv($count, 2);

		return ($count % 2 === 1) ?
			$values[$middle] :
			($values[$middle - 1] + $values[$middle]) / 2;

	}

}
?>
