<?php
namespace aggregate;

/**
 * Agrégation des cultures d'une espèce sur l'ensemble des structures.
 *
 * La question à laquelle cette classe répond est celle que se pose quelqu'un
 * devant son plan de culture : « à quelle période les autres implantent-ils cette
 * espèce, et qu'est-ce que ça leur donne à la récolte ? ». Les réponses sont
 * regroupées en créneaux — un créneau est un mois d'implantation — parce que c'est
 * l'unité dans laquelle un plan de culture se raisonne.
 *
 * Elle ne lit pas les tables vives : CultivationProjectionAiLib y dépose chaque nuit
 * une ligne par culture, et tout ce qui suit part de là. La frontière entre les deux
 * n'est pas « nettoyage » contre « affichage », c'est qu'une colonne de la
 * projection ne dépend jamais d'un réglage ni de l'échantillon. Tous les seuils de
 * CultivationAiSetting s'appliquent donc ici, sur des valeurs brutes, et se déplacent
 * sans reconstruire quoi que ce soit.
 */
class CultivationCropPlanAiLib {

	/**
	 * Le statut d'une semaine d'implantation au survol : sa cohorte est montrée, trop
	 * peu de structures y récoltent pour qu'on en dise quoi que ce soit, ou personne n'y
	 * a rien implanté.
	 */
	const COHORT_SHOWN = 'shown';
	const COHORT_FEW = 'few';
	const COHORT_EMPTY = 'empty';

	/**
	 * Le plan de culture agrégé de l'espèce, sous la forme attendue par l'interface.
	 *
	 * $filters porte le mode de culture (abri / plein champ), la conduite retenue,
	 * l'origine du plant et la recherche par variété.
	 *
	 * $bedWidth est la largeur de planche du groupe à ouvrir. Les conduites se prennent
	 * sur la collection **déjà filtrée**, pour que le bandeau de filtres agisse sur
	 * elles comme sur le calendrier : un écran qui montrerait les conduites de toute
	 * l'espèce sous un calendrier de plein champ ferait mentir les deux.
	 */
	public static function getCropPlan(\plant\Plant $ePlant, array $filters, ?int $bedWidth): array {

		// Les deux seuls filtres qui portent sur une colonne indexée partent en SQL, le
		// reste se joue sur les lignes rendues : ce sont des réglages, et les appliquer
		// ici plutôt que dans la projection permet de les bouger sans reconstruire.
		$cCultivation = CultivationAiLib::getByPlant($ePlant, $filters['action'], $filters['seedling']);

		// Les variétés de l'espèce entière, toujours : elles servent à la fois de filtre
		// et de classement par créneau, et l'espèce tient en quelques milliers de lignes.
		$cCultivationVariety = CultivationVarietyAiLib::getByPlant($ePlant);

		$cCultivation = self::findByFilters($cCultivation, $cCultivationVariety, $filters);

		$conducts = CultivationConductAiLib::getConducts($cCultivation, $bedWidth);

		if($cCultivation->empty()) {
			return [
				'action' => $filters['action'],
				'structures' => 0,
				'cultivations' => 0,
				'months' => self::getMonthAxis(),
				'series' => [
					'implantation' => self::getEmptySerie(),
					'seedling' => self::getEmptySerie(),
					'harvest' => self::getEmptySerie(),
				],
				'slots' => [],
				'cohorts' => [],
				'aside' => 0,
				'varieties' => 0,
				'rangePeak' => [],
				'conducts' => $conducts,
			];
		}

		// Chaque culture est réduite à ce qui la situe dans l'année : sa semaine
		// d'implantation, son mois, l'intervalle de semaines qu'elle récolte, le mode
		// de conduite de sa série et — si elle est plantée — son semis en pépinière.
		$cultivations = self::getTimings($cCultivation, $filters['action']);

		// Les délais invraisemblables sont neutralisés avant tout comptage, pour que le
		// calendrier, les créneaux et les médianes reposent tous sur le même jeu.
		$cultivations = self::findByGapFloor($cultivations);

		// Les créneaux portent tout le traitement — plancher d'espèce, plafond par
		// créneau, redistribution des récoltes déclarées au mois — et les courbes
		// d'espèce en sont la somme. Le calendrier et les créneaux reposent ainsi sur
		// exactement le même jeu, ce qui n'était pas le cas quand le calendrier
		// comptait de son côté.
		$structures = [];

		foreach($cultivations as $timing) {
			$structures[$timing['structureId']] = TRUE;
		}

		$slots = self::getSlots($cultivations, $cCultivationVariety, count($structures));

		$series = [];

		foreach($slots['counts'] as $type => $weeks) {
			$series[$type] = self::getSerie($weeks, $slots['totals'][$type]);
		}

		return [
			'action' => $filters['action'],
			'structures' => count($structures),
			'cultivations' => count($cultivations),
			'months' => self::getMonthAxis(),
			'series' => $series,
			'slots' => $slots['kept'],
			'cohorts' => $slots['cohorts'],
			'aside' => $slots['aside'],
			'varieties' => $slots['varieties'],
			'rangePeak' => $slots['rangePeak'],
			'conducts' => $conducts,
		];

	}

	/**
	 * Une courbe lissée, prête à être tracée.
	 *
	 * Les effectifs bruts sautent d'une semaine à l'autre — 327 en S20, 128 en S22 —
	 * parce que les structures calent leurs interventions sur des semaines rondes et
	 * sur la météo, pas parce que la pratique change. Ces à-coups masquent la seule
	 * chose qu'on cherche ici, la forme de la saison ; un lissage sur cinq semaines
	 * les efface sans déplacer les pics.
	 */
	protected static function getSerie(array $counts, int $total, bool $smooth = TRUE): array {

		if($smooth === FALSE) {
			return [
				'total' => $total,
				'max' => $counts ? max($counts) : 0,
				'curve' => $counts,
				'counts' => $counts,
			];
		}

		// Le lissage est borné aux semaines réellement occupées : il adoucit l'intérieur
		// de la série sans jamais déborder de son support. Sans cette borne, le noyau
		// étalait l'implantation d'un créneau deux semaines de part et d'autre et la
		// faisait mordre sur les mois voisins, alors qu'elle n'appartient qu'au sien.
		$occupied = array_keys(array_filter($counts, fn($count) => $count > 0));

		if($occupied === []) {
			return self::getEmptySerie();
		}

		$first = min($occupied);
		$last = max($occupied);

		$kernel = CultivationAiSetting::SMOOTHING_KERNEL;
		$centre = (int)floor(count($kernel) / 2);
		$curve = [];

		for($week = 1; $week <= 52; $week++) {

			if($week < $first or $week > $last) {
				$curve[$week] = 0;
				continue;
			}

			$sum = 0;
			$weight = 0;

			foreach($kernel as $position => $factor) {

				$neighbour = $week + $position - $centre;

				// Les voisines hors support ne comptent pas comme des zéros : elles ne
				// comptent pas du tout, et le poids est renormalisé. Sinon les deux
				// semaines de bord seraient artificiellement écrasées.
				if($neighbour >= $first and $neighbour <= $last) {
					$sum += $counts[$neighbour] * $factor;
					$weight += $factor;
				}

			}

			$curve[$week] = ($weight > 0) ? $sum / $weight : 0;

		}

		return [
			'total' => $total,
			'max' => $curve ? max($curve) : 0,
			'curve' => $curve,
			'counts' => $counts,
		];

	}

	protected static function getEmptySerie(): array {

		return [
			'total' => 0,
			'max' => 0,
			'curve' => array_fill(1, 52, 0),
			'counts' => array_fill(1, 52, 0),
		];

	}

	/**
	 * Les cultures que les réglages laissent passer.
	 *
	 * Aucune requête ici : tout se joue sur des colonnes déposées par la projection.
	 * Ces filtres sont dans ce Lib et non dans le job de nuit parce qu'ils dépendent
	 * tous d'un seuil de CultivationAiSetting, et qu'un seuil se déplace sans reconstruction.
	 */
	protected static function findByFilters(\Collection $cCultivation, \Collection $cCultivationVariety, array $filters): \Collection {

		$cCultivation = self::findByStructureSeries($cCultivation);

		if($cCultivation->empty()) {
			return $cCultivation;
		}

		if($filters['altitude'] !== NULL) {

			$cCultivation = self::findByAltitude($cCultivation, $filters['altitude']);

			if($cCultivation->empty()) {
				return $cCultivation;
			}

		}

		if($filters['mode'] !== NULL) {
			$cCultivation = self::findByMode($cCultivation, $filters['mode']);
		}

		if($filters['variety'] !== NULL) {
			$cCultivation = self::findByVariety($cCultivation, $cCultivationVariety, $filters['variety']);
		}

		return $cCultivation;

	}

	/**
	 * Les cultures qui emploient une variété dont le nom contient la recherche.
	 *
	 * La recherche porte sur le nom de la variété de référence, celui-là même que la
	 * page affiche : chercher ce qu'on voit est la seule attente raisonnable, et une
	 * culture dont aucune variété n'est rattachée ne montrerait de toute façon rien.
	 *
	 * Elle compare des noms réduits par sous-chaîne, ce qui laisse « beauty »
	 * retrouver « Black Beauty » et absout les accents et la casse. La comparaison
	 * reste en PHP : un `LIKE '%…%'` n'utilise aucun index et coûte dix fois la
	 * lecture des quelques milliers de lignes de l'espèce, déjà chargées.
	 */
	protected static function findByVariety(\Collection $cCultivation, \Collection $cCultivationVariety, string $variety): \Collection {

		$key = CultivationVarietyAiLib::getSearchKey($variety);

		if($key === '') {
			return $cCultivation;
		}

		$matching = [];

		foreach($cCultivationVariety as $eCultivationVariety) {

			if(str_contains($eCultivationVariety['searchKey'], $key)) {
				$matching[$eCultivationVariety['cultivation']['id']] = TRUE;
			}

		}

		return $cCultivation->find(fn($eCultivation) => isset($matching[$eCultivation['id']]));

	}

	/**
	 * Les cultures des structures qui cultivent dans le palier d'altitude demandé.
	 *
	 * L'altitude est celle du système de production, saisie une fois pour la
	 * structure : une culture n'en porte pas. Celles qui ne l'ont pas renseignée
	 * sortent de l'échantillon — les placer d'office en plaine reviendrait à inventer
	 * une donnée sur laquelle la page fait ensuite des médianes.
	 */
	public static function findByAltitude(\Collection $cCultivation, string $altitude): \Collection {

		[$min, $max] = CultivationAiSetting::ALTITUDES[$altitude];

		return $cCultivation->find(function($eCultivation) use ($min, $max) {

			if($eCultivation['altitude'] === NULL) {
				return FALSE;
			}

			return (
				$eCultivation['altitude'] >= $min and
				($max === NULL or $eCultivation['altitude'] < $max)
			);

		});

	}

	/**
	 * Les cultures des fermes qui se servent vraiment de l'outil.
	 *
	 * Une ferme qui n'a monté qu'une poignée de séries est en train d'essayer le
	 * logiciel, pas de décrire sa saison : ses semaines sont des valeurs par défaut
	 * qu'elle n'a jamais reprises, et elles pèsent ici du même poids que celles d'une
	 * ferme qui planifie tout son maraîchage.
	 *
	 * Le tri se fait sur la ferme entière et non sur l'espèce regardée : c'est l'usage
	 * général de l'outil qui dit si la saisie est sérieuse, et une ferme installée n'a
	 * de toute façon qu'une ou deux séries d'aubergines.
	 */
	protected static function findByStructureSeries(\Collection $cCultivation): \Collection {

		return $cCultivation->find(fn($eCultivation) => $eCultivation['structureSeries'] > CultivationAiSetting::STRUCTURE_SERIES_MIN);

	}

	/**
	 * Les cultures dont la série est conduite dans le mode demandé.
	 *
	 * Une série `mix` est retenue dans les deux modes, elle mélange abri et plein
	 * champ.
	 */
	protected static function findByMode(\Collection $cCultivation, string $mode): \Collection {

		return $cCultivation->find(fn($eCultivation) => in_array($eCultivation['mode'], [$mode, \series\Series::MIX], TRUE));

	}

	/**
	 * Réduit chaque ligne de la projection à sa place dans l'année.
	 *
	 * La projection dépose des valeurs brutes ; c'est ici que les réglages s'y
	 * appliquent. Deux d'entre eux décident si une étape a le droit d'exister :
	 * l'origine du plant pour la pépinière, la précision de la déclaration pour la
	 * récolte. Une culture écartée d'une étape reste comptée dans son créneau, elle
	 * cesse seulement d'alimenter cette étape.
	 */
	protected static function getTimings(\Collection $cCultivation, ?string $action): array {

		$timings = [];

		foreach($cCultivation as $eCultivation) {

			$startWeek = $eCultivation['startWeek'];

			// La question de la pépinière ne se pose qu'en plantation : un semis direct
			// n'en a pas. La colonne est remplie dès que la culture déclare un plant
			// produit sur place, c'est la vue qui décide de la lire.
			$seedlingIndex = ($action === \series\Cultivation::PLANTING) ? $eCultivation['seedlingIndex'] : NULL;

			// Un écart d'une semaine ou moins entre le semis et la mise en place ne
			// décrit aucun légume repiqué, et un écart de plus d'un semestre ne décrit
			// plus la même culture. Hors de ces bornes, l'écart est laissé vide plutôt
			// que de tirer les médianes.
			$seedlingGap = ($seedlingIndex !== NULL) ? $startWeek - $seedlingIndex : NULL;

			if(
				$seedlingGap !== NULL and (
					$seedlingGap < CultivationAiSetting::SEEDLING_GAP_MIN or
					$seedlingGap > CultivationAiSetting::SEEDLING_GAP_MAX
				)
			) {
				$seedlingGap = NULL;
			}

			// Réglage désactivé : toutes les cultures valent pour les intervalles, quelle
			// que soit la précision de leur déclaration.
			$isWeekly = (
				CultivationAiSetting::HARVEST_WEEKLY_ONLY === FALSE or
				$eCultivation['harvestPeriod'] === \series\Cultivation::WEEK
			);

			$hasHarvest = ($eCultivation['harvestFrom'] !== NULL and $isWeekly);

			$timings[] = [
				'cultivationId' => $eCultivation['id'],
				// L'identifiant de la culture d'origine sert à départager les égalités.
				// Celui de la ligne de projection ne le pourrait pas : il est réattribué
				// à chaque reconstruction, et l'affichage changerait d'une nuit à l'autre
				// sans qu'aucune donnée ait bougé.
				'seriesCultivationId' => $eCultivation['seriesCultivation'],
				'structureId' => $eCultivation['structure']['id'],
				'month' => $eCultivation['month'],
				'startWeek' => $startWeek,
				'startAction' => $eCultivation['startAction'],
				'mode' => $eCultivation['mode'],
				// L'index sert aux médianes et aux écarts, la semaine calendaire au
				// relief : un semis de décembre doit se dessiner en décembre.
				'seedlingIndex' => ($seedlingGap !== NULL) ? $seedlingIndex : NULL,
				'seedlingWeek' => ($seedlingGap !== NULL) ? self::getCalendarWeek($seedlingIndex) : NULL,
				'seedlingGap' => $seedlingGap,
				// Une récolte déclarée au mois a des bornes calées sur des débuts et des
				// fins de mois : elles ne datent rien à la semaine près et fausseraient
				// les intervalles. Ces cultures ne servent donc qu'au comptage, où leur
				// volume compte, jamais aux médianes ni aux délais.
				'period' => $eCultivation['harvestPeriod'],
				'harvestWeeks' => $eCultivation['harvestWeeks'],
				'harvestFrom' => $hasHarvest ? $eCultivation['harvestFrom'] : NULL,
				'harvestTo' => $hasHarvest ? $eCultivation['harvestTo'] : NULL,
				'delay' => $hasHarvest ? max(0, $eCultivation['harvestFrom'] - $startWeek) : NULL,
				'duration' => $hasHarvest ? $eCultivation['harvestCount'] : NULL,
			];

		}

		return $timings;

	}
	/**
	 * Ramène un index continu sur les 52 semaines d'une année civile.
	 *
	 * Un index négatif désigne l'année précédente — la pépinière d'un poireau planté
	 * en février —, un index au-delà de 52 l'année suivante.
	 */
	protected static function getCalendarWeek(int $index): int {

		return ((($index - 1) % 52) + 52) % 52 + 1;

	}

	/**
	 * Les créneaux, un par mois d'implantation.
	 *
	 * Un créneau ne retient que des médianes et des quartiles : sur des pratiques
	 * aussi dispersées, une moyenne serait tirée par les quelques cultures très
	 * précoces ou très tardives et ne décrirait plus personne.
	 */
	protected static function getSlots(array $timings, \Collection $cCultivationVariety, int $structuresTotal): array {

		$grouped = [];

		foreach($timings as $timing) {
			$grouped[$timing['month']][] = $timing;
		}

		ksort($grouped);

		$varieties = self::getVarietyByMonth($timings, $cCultivationVariety);

		$kept = [];
		$aside = 0;

		// Les courbes d'espèce sont la somme de celles des créneaux : plafond par
		// créneau et redistribution des récoltes au mois s'y appliquent donc aussi, et
		// le calendrier repose sur exactement le même jeu que les créneaux. Les
		// créneaux sous le seuil d'affichage y comptent quand même — le seuil décide de
		// ce qu'on montre, pas de ce que l'espèce est.
		$counts = [
			'implantation' => array_fill(1, 52, 0),
			'seedling' => array_fill(1, 52, 0),
			'harvest' => array_fill(1, 52, 0),
		];

		$totals = ['implantation' => 0, 'seedling' => 0, 'harvest' => 0];

		$cohorts = [];

		foreach($grouped as $month => $cTiming) {

			// La pépinière et la récolte perdent leur queue haute, l'implantation non :
			// c'est elle qui définit le créneau, et ses semaines sont déjà celles du
			// mois. L'élagage porte sur l'**écart** à l'implantation et non sur la date
			// absolue : c'est cet écart que la frise affiche, ce sont donc ses valeurs
			// extrêmes qui faussent la lecture.
			$cTimingSeedling = self::findByCeiling($cTiming, 'seedlingGap');
			$cTimingHarvest = self::findByCeiling($cTiming, 'delay');

			// Les récoltes déclarées au mois sont écartées des intervalles mais gardées
			// pour le comptage : elles sont bien réelles, seule leur datation est floue.
			// Réglage désactivé : elles sont déjà dans $cTimingHarvest avec leurs bornes,
			// donc rien à redistribuer — les compter ici les compterait deux fois.
			$cTimingMonth = (CultivationAiSetting::HARVEST_WEEKLY_ONLY === FALSE) ? [] : array_values(array_filter(
				$cTiming,
				fn($timing) => $timing['period'] === \series\Cultivation::MONTH and $timing['harvestWeeks'] !== []
			));

			$structures = [];
			$startWeeks = [];
			$harvestFroms = [];
			$harvestTos = [];
			$delays = [];
			$durations = [];
			$seedlingWeeks = [];
			$seedlingGaps = [];
			$modes = [];

			foreach($cTiming as $timing) {

				$structures[$timing['structureId']] = TRUE;
				$startWeeks[] = $timing['startWeek'];

				if($timing['mode'] !== NULL) {
					$modes[$timing['mode']] = ($modes[$timing['mode']] ?? 0) + 1;
				}

			}

			foreach($cTimingSeedling as $timing) {
				$seedlingWeeks[] = $timing['seedlingIndex'];
				$seedlingGaps[] = $timing['seedlingGap'];
			}

			foreach($cTimingHarvest as $timing) {
				$harvestFroms[] = $timing['harvestFrom'];
				$harvestTos[] = $timing['harvestTo'];
				$delays[] = $timing['delay'];
				$durations[] = $timing['duration'];
			}

			$curves = self::getSlotCurves($cTiming, $cTimingSeedling, $cTimingHarvest, $cTimingMonth);

			$cohorts = self::addCohorts($cohorts, $cTiming, $cTimingHarvest, $cTimingMonth);

			foreach($curves as $type => $serie) {
				foreach($serie['counts'] as $week => $count) {
					$counts[$type][$week] += $count;
				}
			}

			$totals['implantation'] += count($cTiming);
			$totals['seedling'] += count($cTimingSeedling);
			$totals['harvest'] += count($cTimingHarvest) + count($cTimingMonth);

			// Sous l'un des deux seuils, le créneau n'est pas supprimé de l'échantillon,
			// il est seulement sorti de la liste : le compte total doit rester juste.
			$share = ($structuresTotal > 0) ? count($structures) / $structuresTotal * 100 : 0;

			if(
				count($structures) < CultivationAiSetting::SLOT_STRUCTURES_MIN or
				$share < CultivationAiSetting::SLOT_STRUCTURES_SHARE_MIN
			) {
				$aside += count($cTiming);
				continue;
			}

			$kept[] = [
				'month' => $month,
				'structures' => count($structures),
				'cultivations' => count($cTiming),
				'modes' => self::getModeShares($modes),
				'curves' => $curves,
				'startWeek' => self::getRange($startWeeks, CultivationAiSetting::RANGE_SHARE_IMPLANTATION),
				'seedlingWeek' => $seedlingWeeks ? self::getRange($seedlingWeeks, CultivationAiSetting::RANGE_SHARE_SEEDLING) : NULL,
				'seedlingGap' => $seedlingGaps ? self::getPercentile($seedlingGaps, 0.5) : NULL,
				'harvestFrom' => $harvestFroms ? self::getRange($harvestFroms, CultivationAiSetting::RANGE_SHARE_HARVEST) : NULL,
				'harvestTo' => $harvestTos ? self::getRange($harvestTos, CultivationAiSetting::RANGE_SHARE_HARVEST) : NULL,
				'delay' => $delays ? self::getPercentile($delays, 0.5) : NULL,
				'duration' => $durations ? self::getPercentile($durations, 0.5) : NULL,
				'varieties' => array_slice($varieties['byMonth'][$month] ?? [], 0, CultivationAiSetting::SLOT_VARIETIES),
			];

		}

		foreach($kept as $position => $slot) {
			$kept[$position]['ranges'] = self::getSlotRanges($slot);
		}

		return [
			'kept' => $kept,
			'cohorts' => self::getCohorts($cohorts),
			'aside' => $aside,
			'varieties' => $varieties['total'],
			'counts' => $counts,
			'totals' => $totals,
			'rangePeak' => self::getRangePeak($kept),
		];

	}

	/**
	 * Les intervalles d'un créneau, prêts à dessiner : une étape, sa part de fermes,
	 * ses bornes et l'effectif de chacune de ses semaines.
	 *
	 * Trois étapes possibles, dont deux conditionnelles — pas de pépinière en semis
	 * direct ni sur du plant acheté, pas de récolte si aucune ferme du créneau ne l'a
	 * datée à la semaine.
	 *
	 * La récolte est la seule étape qui dure : sa barre court du début de récolte à sa
	 * fin, et son épaisseur compte les cultures *en* récolte. Les deux autres sont des
	 * instants, et leur épaisseur compte les cultures qui les franchissent cette
	 * semaine-là. Le point, lui, reste sur le début dans les trois cas — c'est la date
	 * qu'on pose dans un plan de culture.
	 */
	protected static function getSlotRanges(array $slot): array {

		$ranges = [];

		if($slot['seedlingWeek'] !== NULL and $slot['seedlingGap'] !== NULL) {
			$ranges[] = ['type' => 'seedling', 'serie' => 'seedling'] + $slot['seedlingWeek'];
		}

		$ranges[] = ['type' => 'implantation', 'serie' => 'implantation'] + $slot['startWeek'];

		if($slot['harvestFrom'] !== NULL) {

			// La barre s'ouvre là où les fermes commencent et se ferme là où elles
			// finissent : les deux bornes viennent de deux distributions différentes, et
			// la part écartée est la même de chaque côté.
			$from = $slot['harvestFrom']['from'];
			$to = ($slot['harvestTo'] !== NULL) ? $slot['harvestTo']['to'] : $slot['harvestFrom']['to'];

			$ranges[] = [
				'type' => 'harvest',
				'serie' => 'harvest',
				'from' => $from,
				'median' => $slot['harvestFrom']['median'],
				// Une récolte ne fait pas deux fois le tour de l'année : au-delà, la barre
				// repasserait sur elle-même et ne dirait plus rien.
				'to' => min($to, $from + 51),
			];

		}

		foreach($ranges as $position => $range) {

			$curve = $slot['curves'][$range['serie']]['curve'];
			$values = [];

			for($index = $range['from']; $index <= $range['to']; $index++) {
				$values[$index] = $curve[self::getCalendarWeek($index)] ?? 0;
			}

			$ranges[$position]['share'] = match($range['type']) {
				'seedling' => CultivationAiSetting::RANGE_SHARE_SEEDLING,
				'implantation' => CultivationAiSetting::RANGE_SHARE_IMPLANTATION,
				'harvest' => CultivationAiSetting::RANGE_SHARE_HARVEST,
			};

			$ranges[$position]['values'] = $values;

		}

		return $ranges;

	}

	/**
	 * L'effectif hebdomadaire le plus élevé de tous les créneaux, étape par étape.
	 *
	 * C'est lui qui donne l'épaisseur maximale dans la vue simplifiée. Une échelle par
	 * créneau faisait culminer chaque mois à la même épaisseur : un créneau de trente
	 * fermes paraissait aussi fourni qu'un créneau de deux cent cinquante, et seule sa
	 * longueur les distinguait. Sur toute la page, la comparaison redevient directe —
	 * un mois secondaire se lit comme un trait fin.
	 *
	 * Une échelle par étape et non une seule pour les trois, parce qu'elles ne
	 * comptent pas la même chose : le semis et l'implantation comptent les cultures
	 * qui franchissent cette semaine-là, la récolte celles qui sont en récolte, et une
	 * culture y pèse une semaine d'un côté contre une dizaine de l'autre. Sous une
	 * échelle commune, la récolte écrasait les deux autres jusqu'au trait plat.
	 */
	protected static function getRangePeak(array $kept): array {

		$peaks = [];

		foreach($kept as $slot) {

			foreach($slot['ranges'] as $range) {

				if($range['values'] !== []) {
					$peaks[$range['type']] = max($peaks[$range['type']] ?? 0, max($range['values']));
				}

			}

		}

		return $peaks;

	}

	/**
	 * Neutralise les délais que la distribution de l'espèce désigne comme
	 * invraisemblables.
	 *
	 * Deux semaines de pépinière pour un poireau, cinq semaines du semis à la récolte
	 * pour une carotte : ce sont des saisies fausses, mais aucune borne fixe ne les
	 * reconnaît, parce que le seuil dépend de l'espèce — et une borne par espèce
	 * demanderait deux cent treize valeurs à maintenir. La distribution les donne.
	 *
	 * Le seuil est calculé sur l'espèce entière et non par créneau : trente cultures
	 * ne suffisent pas à situer un centile, et c'est précisément sur les petits
	 * créneaux que les aberrations pèsent le plus. Un plancher seulement : le haut de
	 * la distribution est saisonnier et se traite dans le créneau, où janvier n'est
	 * comparé qu'à janvier.
	 *
	 * Neutraliser plutôt qu'écarter : la culture reste comptée dans son créneau et
	 * dans le nombre de fermes, elle cesse seulement d'alimenter l'étape en cause.
	 */
	protected static function findByGapFloor(array $cultivations): array {

		foreach(['seedlingGap', 'delay'] as $property) {

			$values = [];

			foreach($cultivations as $timing) {

				if($timing[$property] !== NULL) {
					$values[] = $timing[$property];
				}

			}

			if($values === []) {
				continue;
			}

			$floor = self::getPercentile($values, CultivationAiSetting::GAP_FLOOR_SHARE / 100);

			foreach($cultivations as $position => $timing) {

				if($timing[$property] === NULL or $timing[$property] >= $floor) {
					continue;
				}

				if($property === 'seedlingGap') {
					$cultivations[$position]['seedlingGap'] = NULL;
					$cultivations[$position]['seedlingIndex'] = NULL;
					$cultivations[$position]['seedlingWeek'] = NULL;
				} else {
					$cultivations[$position]['delay'] = NULL;
					$cultivations[$position]['duration'] = NULL;
					$cultivations[$position]['harvestFrom'] = NULL;
					$cultivations[$position]['harvestTo'] = NULL;
					$cultivations[$position]['harvestWeeks'] = [];
				}

			}

		}

		return $cultivations;

	}

	/**
	 * Rogne les semaines de début et de fin d'une étape où l'effectif est négligeable.
	 *
	 * Une récolte de créneau traîne souvent sur plusieurs semaines avec une ou deux
	 * fermes : dessinées comme le reste, ces semaines allongent la période bien au-delà
	 * de ce qu'elle est. On remonte donc depuis chaque extrémité tant que la semaine
	 * reste sous le seuil, et on s'arrête à la première qui compte.
	 *
	 * Les extrémités seulement : un creux au milieu de la période — une interruption
	 * entre deux vagues de récolte — est un fait qu'il faut montrer, pas une traîne.
	 *
	 * ⚠ Les extrémités ne sont pas les semaines 1 et 52. Une récolte de courgette
	 * implantée en août court de la semaine 44 à la semaine 6 de l'année suivante :
	 * partir de la semaine 1 attaquerait le milieu de la période. Le vrai début est
	 * donc cherché sur le cycle, après le plus long trou de semaines vides.
	 */
	protected static function findByTail(array $counts): array {

		$peak = $counts ? max($counts) : 0;

		if($peak <= 0) {
			return $counts;
		}

		// Deux exigences, le plus fort des deux seuils l'emportant : une part du pic,
		// qui suit l'ampleur du créneau, et un effectif plancher, qui l'empêche de
		// devenir dérisoire sur un petit créneau.
		$threshold = max(
			$peak * CultivationAiSetting::RELIEF_TAIL_MIN_SHARE / 100,
			CultivationAiSetting::RELIEF_TAIL_MIN_COUNT
		);

		$start = self::getCycleStart($counts, $threshold);

		if($start === NULL) {
			return $counts;
		}

		// Depuis le début de la période, en avançant sur le cycle.
		for($step = 0; $step < 52; $step++) {

			$week = ($start - 1 + $step) % 52 + 1;

			if($counts[$week] >= $threshold) {
				break;
			}

			$counts[$week] = 0;

		}

		// Puis depuis la fin, en reculant. La fin est la semaine qui précède le début.
		for($step = 1; $step <= 52; $step++) {

			$week = ($start - 1 - $step + 52 * 2) % 52 + 1;

			if($counts[$week] >= $threshold) {
				break;
			}

			$counts[$week] = 0;

		}

		return $counts;

	}

	/**
	 * La semaine où commence une période posée sur le cycle des 52 semaines.
	 *
	 * C'est celle qui suit le plus long creux, un creux étant une suite de semaines
	 * sous le seuil. Sur une période ordinaire ce creux est le reste de l'année et le
	 * début tombe naturellement ; sur une période à cheval sur le Nouvel An il se
	 * trouve au milieu de l'année et le début est retrouvé de la même façon.
	 *
	 * Le seuil plutôt que le vide : une courgette implantée en août compte quelques
	 * fermes en récolte sur les cinquante-deux semaines, si bien qu'aucune semaine
	 * n'est vide et qu'un creux défini par des zéros n'existerait pas. Le seuil, lui,
	 * retrouve le creux hivernal.
	 *
	 * Renvoie NULL quand aucune semaine ne passe sous le seuil : la période fait
	 * vraiment le tour de l'année, elle n'a ni début ni fin à rogner.
	 */
	protected static function getCycleStart(array $counts, float $threshold): ?int {

		$longest = 0;
		$after = NULL;
		$length = 0;

		// Deux tours, pour qu'un creux à cheval sur le Nouvel An soit vu d'un seul tenant.
		for($step = 0; $step < 104; $step++) {

			$week = $step % 52 + 1;

			if($counts[$week] >= $threshold) {
				$length = 0;
				continue;
			}

			$length++;

			if($length > $longest and $length < 52) {
				$longest = $length;
				$after = $week % 52 + 1;
			}

		}

		return $after;

	}

	/**
	 * Les cultures d'un créneau privées de leur queue haute sur une étape donnée.
	 *
	 * Sur cent fermes, les cinq qui attendent le plus longtemps sortent du calcul :
	 * une culture ratée et resemée, un plant qui patiente en godet, une saisie
	 * approximative. Elles étiraient le relief sur la moitié de l'année et faisaient
	 * croire à une pratique étalée là où elle est groupée. Les médianes n'en
	 * souffraient pas, les formes si.
	 *
	 * En haut seulement, et calculé dans le créneau : c'est là que le seuil doit se
	 * juger, une mâche de janvier passant légitimement plus de temps en pépinière
	 * qu'une mâche de juillet. Le bas est tenu par le plancher d'espèce, qui dispose
	 * du grand échantillon dont un créneau de trente cultures manque.
	 *
	 * Arrondi vers le bas : sous vingt cultures il ne retire rien, ce qui est la bonne
	 * réponse quand il n'y a de toute façon pas de queue à parler.
	 */
	protected static function findByCeiling(array $cTiming, string $property): array {

		$cTimingSorted = array_values(array_filter($cTiming, fn($timing) => $timing[$property] !== NULL));

		// L'élagage coupe un nombre de cultures, pas une valeur : quand plusieurs
		// cultures portent le délai qui tombe sur la coupe, il faut bien en garder
		// certaines et en écarter d'autres. Les départager sur la culture d'origine rend
		// ce choix arbitraire au moins reproductible — sans quoi le simple ordre des
		// lignes rendues par la base déciderait, et changerait à chaque reconstruction.
		usort($cTimingSorted, fn($a, $b) => [$a[$property], $a['seriesCultivationId']] <=> [$b[$property], $b['seriesCultivationId']]);

		$ceiling = (int)floor(count($cTimingSorted) * CultivationAiSetting::CEILING_SHARE / 100);

		if($ceiling === 0) {
			return $cTimingSorted;
		}

		return array_slice($cTimingSorted, 0, count($cTimingSorted) - $ceiling);

	}

	/**
	 * Le profil hebdomadaire d'un créneau : combien de fermes font quoi, semaine
	 * par semaine.
	 *
	 * L'intervalle interquartile décrivait bien le cœur d'un créneau, mais il le
	 * découpait au couteau : la plantation de mars s'arrêtait à la semaine 13 et
	 * celle d'avril reprenait à la 15, laissant un trou là où la pratique est
	 * continue. Un profil rend cette continuité — les créneaux voisins se recouvrent,
	 * parce que c'est ce que font les fermes.
	 *
	 * Le compte est en cultures, comme les courbes d'espèce en tête de page : une
	 * ferme qui échelonne six séries la même semaine pèse donc six fois. C'est un
	 * choix assumé — le relief décrit l'activité, pas le nombre de fermes, et
	 * l'effectif en fermes est donné à part dans l'en-tête du créneau.
	 */
	protected static function getSlotCurves(array $cTiming, array $cTimingSeedling, array $cTimingHarvest, array $cTimingMonth): array {

		$counts = [];

		foreach(['seedling', 'implantation', 'harvest'] as $type) {
			$counts[$type] = array_fill(1, 52, 0);
		}

		foreach($cTiming as $timing) {
			$counts['implantation'][$timing['startWeek']]++;
		}

		foreach($cTimingSeedling as $timing) {
			$counts['seedling'][$timing['seedlingWeek']]++;
		}

		$reference = self::countHarvestWeeks($cTimingHarvest);

		$counts['harvest'] = self::addMonthHarvests($reference, $cTimingMonth, $reference);

		// La pépinière et la récolte perdent leurs traînes : les semaines de début et de
		// fin où presque personne n'est encore, ou déjà, à l'œuvre. L'implantation n'y
		// est pas soumise, elle définit le créneau et tient dans les semaines de son
		// mois.
		foreach(['seedling', 'harvest'] as $type) {
			$counts[$type] = self::findByTail($counts[$type]);
		}

		$curves = [];

		// Lissé, mais borné au support : la courbe démarre et s'arrête sur les semaines
		// réellement occupées, et n'est adoucie qu'entre les deux. L'implantation d'un
		// créneau reste donc dans les semaines de son mois.
		foreach($counts as $type => $weeks) {
			$curves[$type] = self::getSerie($weeks, 0);
		}

		return $curves;

	}

	/**
	 * Les semaines de récolte des cultures datées à la semaine, une entrée par culture
	 * et par semaine.
	 *
	 * Les semaines de récolte sont déjà ramenées sur 1-52 : une récolte qui déborde
	 * sur l'année suivante repasse d'elle-même par janvier, sans avoir à découper quoi
	 * que ce soit.
	 */
	protected static function countHarvestWeeks(array $cTimingHarvest): array {

		$counts = array_fill(1, 52, 0);

		foreach($cTimingHarvest as $timing) {
			foreach($timing['harvestWeeks'] as $week) {
				$counts[$week]++;
			}
		}

		return $counts;

	}

	/**
	 * Ajoute à `$counts` les cultures déclarées au mois.
	 *
	 * Une culture déclarée au mois couvre ses mois d'un bloc, du premier au dernier
	 * jour : répartie telle quelle, elle dessinerait des marches et gonflerait les
	 * bords de mois. Son volume est donc redistribué sur les mêmes semaines, mais au
	 * prorata de `$reference`, le profil des cultures datées à la semaine de son
	 * créneau — le relief garde la forme des fermes précises et l'effectif de toutes.
	 *
	 * Le profil de référence est figé avant toute redistribution, et les apports
	 * s'accumulent à part : les lire dans le tableau qu'on est en train de remplir
	 * ferait répartir chaque culture sur un profil déjà gonflé par les précédentes, et
	 * l'effet se composerait de proche en proche.
	 */
	protected static function addMonthHarvests(array $counts, array $cTimingMonth, array $reference): array {

		$extra = array_fill(1, 52, 0);

		foreach($cTimingMonth as $timing) {

			$weeks = $timing['harvestWeeks'];
			$total = array_sum(array_map(fn($week) => $reference[$week], $weeks));

			foreach($weeks as $week) {

				// Sans profil de référence sur ces semaines — aucune ferme précise n'y
				// récolte — il n'y a rien à imiter : la répartition reste uniforme.
				$extra[$week] += ($total > 0)
					? count($weeks) * $reference[$week] / $total
					: 1;

			}

		}

		foreach($extra as $week => $value) {
			$counts[$week] += $value;
		}

		return $counts;

	}

	/**
	 * Ajoute aux cohortes les cultures d'un créneau qui comptent dans la courbe des
	 * récoltes, rangées par semaine d'implantation.
	 *
	 * Une cohorte, ce sont les cultures implantées la même semaine, et ce qu'on en
	 * montre, c'est quand elles récoltent. Elle est prise sur exactement le jeu de la
	 * courbe des récoltes de l'espèce (plafond de délai du créneau, récoltes au mois
	 * redistribuées sur le profil du créneau), pour que la cohorte survolée et la
	 * courbe qu'elle remplace le temps du survol ne disent pas deux choses. Une
	 * semaine d'implantation peut appartenir à deux créneaux, la S14 à mars et à
	 * avril : ses cultures s'additionnent d'un créneau à l'autre.
	 *
	 * Toute semaine qui porte une implantation a sa cohorte, même sans aucune récolte :
	 * l'écran distingue la semaine où personne n'a rien implanté de celle où trop peu
	 * de structures récoltent pour qu'on en montre quelque chose.
	 *
	 * Les délais jusqu'à la première récolte ne viennent que des récoltes datées à la
	 * semaine, comme ceux des créneaux : une récolte déclarée au mois commence le
	 * premier du mois, quel que soit le jour réel.
	 */
	protected static function addCohorts(array $cohorts, array $cTiming, array $cTimingHarvest, array $cTimingMonth): array {

		$reference = self::countHarvestWeeks($cTimingHarvest);

		$weeks = array_unique(array_column($cTiming, 'startWeek'));

		foreach($weeks as $week) {

			$harvestTimings = array_filter($cTimingHarvest, fn(array $timing) => $timing['startWeek'] === $week);
			$monthTimings = array_filter($cTimingMonth, fn(array $timing) => $timing['startWeek'] === $week);

			$counts = self::addMonthHarvests(self::countHarvestWeeks($harvestTimings), $monthTimings, $reference);

			if(array_key_exists($week, $cohorts) === FALSE) {
				$cohorts[$week] = [
					'counts' => array_fill(1, 52, 0),
					'cultivations' => 0,
					'delays' => [],
					'structureIds' => [],
				];
			}

			foreach($harvestTimings as $timing) {
				$cohorts[$week]['delays'][] = $timing['delay'];
			}

			foreach($counts as $harvestWeek => $count) {
				$cohorts[$week]['counts'][$harvestWeek] += $count;
			}

			$cohorts[$week]['cultivations'] += count($harvestTimings) + count($monthTimings);

			foreach(array_merge($harvestTimings, $monthTimings) as $timing) {
				$cohorts[$week]['structureIds'][$timing['structureId']] = TRUE;
			}

		}

		return $cohorts;

	}

	/**
	 * Les cohortes à rendre à la page, une par semaine de l'année : l'effectif, la
	 * courbe, rognée de ses traînes et lissée comme celle de l'espèce, et le délai
	 * médian jusqu'à la première récolte, `NULL` sans récolte datée à la semaine.
	 *
	 * Une cohorte n'est montrée qu'à partir de COHORT_STRUCTURES_MIN structures qui
	 * récoltent : celle d'une seule structure serait son calendrier de récolte, semaine
	 * par semaine. En deçà, la semaine ne rend que son statut, sans aucun chiffre. Les
	 * identifiants ne servent qu'à ce compte et ne sortent pas.
	 */
	protected static function getCohorts(array $cohorts): array {

		$kept = array_fill(1, 52, ['status' => self::COHORT_EMPTY]);

		foreach($cohorts as $week => $cohort) {

			if(count($cohort['structureIds']) < CultivationAiSetting::COHORT_STRUCTURES_MIN) {
				$kept[$week] = ['status' => self::COHORT_FEW];
				continue;
			}

			$serie = self::getSerie(self::findByTail($cohort['counts']), $cohort['cultivations']);

			$kept[$week] = [
				'status' => self::COHORT_SHOWN,
				'cultivations' => $serie['total'],
				'max' => $serie['max'],
				'curve' => $serie['curve'],
				'delay' => ($cohort['delays'] === []) ? NULL : self::getPercentile($cohort['delays'], 0.5),
			];

		}

		return $kept;

	}

	/**
	 * Les variétés employées dans chaque créneau, les plus répandues d'abord, et le
	 * nombre total de variétés distinctes de l'échantillon.
	 *
	 * Le classement se fait au nombre de fermes et non au nombre de cultures : une
	 * ferme qui échelonne dix séries sur la même variété ne doit pas peser dix fois
	 * dans un choix collectif.
	 *
	 * Le total, lui, compte toutes les variétés sans seuil : il dit l'ampleur du choix
	 * variétal en jeu, là où les listes par créneau écartent ce qui n'est qu'un essai.
	 */
	protected static function getVarietyByMonth(array $timings, \Collection $cCultivationVariety): array {

		$monthByCultivation = [];

		foreach($timings as $timing) {
			$monthByCultivation[$timing['cultivationId']] = $timing['month'];
		}

		// Le regroupement est déjà fait : la projection n'a déposé que des variétés de
		// référence, et deux saisies d'une même semence y portent le même identifiant.
		$structuresByVariety = [];
		$names = [];

		foreach($cCultivationVariety as $eCultivationVariety) {

			$month = $monthByCultivation[$eCultivationVariety['cultivation']['id']] ?? NULL;

			// Une part variétale dont la culture n'a pas survécu aux filtres n'est pas de
			// l'échantillon : c'est ce test, et lui seul, qui restreint les variétés à ce
			// que la page montre.
			if($month === NULL) {
				continue;
			}

			$varietyId = $eCultivationVariety['variety']['id'];

			$structuresByVariety[$month][$varietyId][$eCultivationVariety['structure']['id']] = TRUE;
			$names[$varietyId] = $eCultivationVariety['name'];

		}

		$cVarietyByMonth = [];

		foreach($structuresByVariety as $month => $references) {

			$totals = array_map('count', $references);

			// Le classement se coupe à un nombre de variétés : sur un créneau où dix
			// variétés sont employées par le même nombre de fermes, l'ordre décide seul
			// de celles qui s'affichent. Le nom départage, faute de quoi la liste
			// changerait d'une reconstruction à l'autre.
			uksort($totals, fn($a, $b) => [-$totals[$a], $names[$a]] <=> [-$totals[$b], $names[$b]]);

			$cVarietyByMonth[$month] = [];

			foreach($totals as $reference => $total) {

				if($total < CultivationAiSetting::SLOT_VARIETY_STRUCTURES_MIN) {
					break;
				}

				$cVarietyByMonth[$month][] = [
					'name' => $names[$reference],
					'structures' => $total,
				];

			}

		}

		// Les références font office d'ensemble : deux saisies d'une même semence y
		// ont déjà fusionné, ce qui est exactement ce qu'on veut compter.
		return ['byMonth' => $cVarietyByMonth, 'total' => count($names)];

	}

	/**
	 * La répartition d'un créneau entre plein champ et sous abri, en pourcents.
	 *
	 * C'est la question qui se pose avant toute autre sur un créneau précoce : une
	 * plantation de mars n'a pas le même sens dehors ou sous tunnel.
	 *
	 * Deux parts, et deux seulement, qui font exactement cent. La version précédente
	 * en comptait trois, écartait celles sous 5 % et arrondissait chacune dans son
	 * coin : il en sortait des « 52 % / 46 % » dont les six points manquants
	 * n'allaient nulle part.
	 *
	 * Une série « mixte » associe les deux conduites, elle compte donc pour moitié
	 * dans chacune — c'est déjà ainsi que le filtre par mode la traite, en la
	 * retenant des deux côtés. Le complément est calculé par soustraction plutôt
	 * qu'arrondi à son tour, seule façon de garantir la somme.
	 */
	protected static function getModeShares(array $modes): array {

		$total = array_sum($modes);

		if($total === 0) {
			return [];
		}

		$mixed = ($modes[\series\Series::MIX] ?? 0) / 2;
		$openField = (int)round((($modes[\series\Series::OPEN_FIELD] ?? 0) + $mixed) / $total * 100);

		return [
			\series\Series::OPEN_FIELD => $openField,
			\series\Series::GREENHOUSE => 100 - $openField,
		];

	}

	/**
	 * Le cœur d'une série de valeurs : sa médiane et l'intervalle qui l'entoure.
	 *
	 * L'intervalle contient la part de fermes demandée, prise à parts égales de chaque
	 * côté de la médiane — les fermes écartées sont donc les plus précoces et les plus
	 * tardives, en nombre égal. À 100 %, il court du minimum au maximum.
	 */
	protected static function getRange(array $values, int $share): array {

		$margin = (1 - $share / 100) / 2;

		return [
			'from' => self::getPercentile($values, $margin),
			'median' => self::getPercentile($values, 0.5),
			'to' => self::getPercentile($values, 1 - $margin),
		];

	}

	protected static function getPercentile(array $values, float $percentile): int {

		sort($values);

		$position = min(count($values) - 1, (int)floor(count($values) * $percentile));

		return (int)round($values[$position]);

	}

	/**
	 * Le découpage de l'axe des 52 semaines en mois, pour graduer les graphiques.
	 *
	 * Un mois ne compte pas un nombre entier de semaines ISO et la répartition varie
	 * d'une année sur l'autre ; elle est calculée sur l'année en cours, qui est celle
	 * que le lecteur a en tête.
	 */
	protected static function getMonthAxis(): array {

		$months = [];

		for($week = 1; $week <= 52; $week++) {

			$month = CultivationProjectionAiLib::getMonth($week);

			if(isset($months[$month])) {
				$months[$month]['span']++;
			} else {
				$months[$month] = ['from' => $week, 'span' => 1];
			}

		}

		ksort($months);

		return $months;

	}

}
?>
