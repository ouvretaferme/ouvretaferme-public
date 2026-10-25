<?php
namespace series;

/**
 * Comparer une même espèce entre les fermes d'un groupe, sur une saison.
 *
 * ⚠️ **Aucune donnée nouvelle** : tout ce que l'écran montre est déjà porté par les cultures et
 * par leurs interventions. C'est un écran de lecture, et ce Lib ne fait que rassembler ce qui est
 * dispersé — d'où l'absence de tout `create` ou `update` ici.
 *
 * ⚠️ **L'espèce se compare par `plant.reference` et jamais par son id.** Chaque ferme porte sa
 * propre copie du référentiel (`PlantLib::duplicateForStructure()`), donc « Carotte » a autant
 * d'identifiants que de fermes ; c'est `reference`, qui pend de l'espèce mère, qui les réunit.
 * Mesuré : 6 420 espèces sur 1 007 953 n'en ont pas — celles qu'une ferme s'est créée. Elles ne
 * sont comparables à rien, et sortent donc du sélecteur plutôt que d'y figurer seules.
 */
class CompareAiLib {

	/**
	 * Les espèces qu'au moins une ferme du groupe cultive cette saison-là.
	 *
	 * Proposer une espèce que personne ne cultive rendrait un écran vide ; proposer celles d'une
	 * autre saison ferait chercher ce qui n'y est pas.
	 */
	public static function getSpecies(\Collection $cStructure, int $season): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		$references = Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select(['reference' => new \Sql('m2.reference', 'int')])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', $season)
			->where('m2.reference IS NOT NULL')
			->group(new \Sql('m2.reference'))
			->getCollection()
			->getColumn('reference');

		if($references === []) {
			return new \Collection();
		}

		return \plant\Plant::model()
			->select(['id', 'name', 'vignette', 'fqn'])
			->whereId('IN', $references)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les cultures d'une espèce, ferme par ferme, avec de quoi les mettre en regard.
	 *
	 * ⚠️ Les dates de semis et de plantation viennent des **interventions** et non de la culture :
	 * `Cultivation.startWeek` n'en porte qu'une, celle qui démarre la série, alors qu'un plant
	 * élevé en pépinière a les deux. Mesuré sur une ferme de dev, 767 semis en pépinière sur 784
	 * et 736 plantations sur 754 portent leur culture et leur semaine — l'écart restant est celui
	 * des interventions rattachées à la série seule, qu'aucune date de culture ne remplacerait.
	 *
	 * ⚠️ La récolte obtenue se montre par-dessus la prévue depuis l'arbitrage du 15/09/2026, alors
	 * même qu'elle est saisie irrégulièrement : mesuré en dev, 11,5 % des cultures de 6388 en
	 * portent une, et aucune chez 4 saisons. Une ferme qui ne saisit rien montre une récolte prévue
	 * sans obtenue, et l'écran ne le commente pas.
	 */
	public static function getCultivations(\Collection $cStructure, \plant\Plant $ePlantReference, int $season): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		$cCultivation = Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'id', 'structure', 'season',
				// Le nom de la série et pas seulement son id : le tableau le montre en second rang
				// quand une ferme mène plusieurs séries de la même espèce.
				'series' => ['id', 'name'],
				'distance', 'rows', 'rowSpacing', 'plantSpacing', 'density',
				'area', 'length', 'yieldExpected', 'mainUnit',
				'startWeek', 'startAction', 'seedling',
				'harvestWeeks', 'harvestWeeksExpected', 'harvestMonthsExpected', 'harvestPeriodExpected'
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', $season)
			->where('m2.reference', $ePlantReference['id'])
			->getCollection(index: 'id');

		if($cCultivation->empty()) {
			return new \Collection();
		}

		self::fillActions($cCultivation);

		// Indexées par ferme : c'est la ferme que l'écran met en regard, une ligne de frise par
		// ferme et non par culture. Une ferme qui sème la même espèce trois fois dans la saison
		// porte donc trois traits sur sa ligne.
		$cByStructure = new \Collection();

		foreach($cStructure as $eStructure) {
			$cByStructure[$eStructure['id']] = new \Collection();
		}

		foreach($cCultivation as $eCultivation) {

			$id = $eCultivation['structure']['id'];

			if($cByStructure->offsetExists($id)) {
				$cByStructure[$id][] = $eCultivation;
			}

		}

		// ⚠️ Dans l'ordre où la frise les range, c'est-à-dire du premier geste au dernier : deux
		// représentations du même objet posées l'une sous l'autre s'ordonnent pareil, sinon celui
		// qui suit une bande puis cherche sa ligne dans le tableau ne la trouve pas au même rang.
		//
		// ⚠️ Le semis d'abord, la plantation à défaut : une culture sans semis existe — un plant
		// acheté n'en a pas —, et la placer d'après une valeur absente la rejetterait au bout
		// alors que la frise la montre au milieu. Sans l'un ni l'autre, elle passe en dernier :
		// rien ne la situe, et la mettre en tête ferait commencer le tableau par ce qu'on ne sait
		// pas dater.
		foreach($cByStructure as $cCultivationStructure) {
			$cCultivationStructure->sort(fn(Cultivation $e1, Cultivation $e2) => self::getSortWeek($e1) <=> self::getSortWeek($e2));
		}

		return $cByStructure;

	}

	/** La semaine qui situe une culture dans la saison ; les non datées ferment la marche. */
	private static function getSortWeek(Cultivation $eCultivation): string {

		return $eCultivation['sowingWeek'] ?? $eCultivation['plantingWeek'] ?? '9999-W99';

	}

	/**
	 * Les semaines de semis et de plantation, posées sur chaque culture, avec leur état.
	 *
	 * ⚠️ La plus précoce quand il y en a plusieurs : une intervention peut être répétée, et c'est
	 * le début du geste qui situe la culture dans la saison, pas sa dernière occurrence.
	 *
	 * ⚠️ **Le réalisé d'un semis ou d'une plantation est un état, pas une date.** Une intervention
	 * faite porte `doneWeek`, que `TaskLib::updateDoneCollection()` ne pose jamais avant la semaine
	 * prévue et qu'une intervention créée faite recopie dans `plannedWeek` : sur la plus grande
	 * structure du parc, presque toutes les interventions faites tombent sur la semaine prévue
	 * (mesuré en 2026). La semaine faite remplace
	 * la prévue quand elle existe, et `sowingDone` / `plantingDone` disent si la pastille est pleine.
	 *
	 * ⚠️ Le semis garde le `fqn` de son intervention : direct ou en pépinière, ce sont deux
	 * conduites que la frise distingue par la couleur.
	 */
	private static function fillActions(\Collection $cCultivation): void {

		foreach($cCultivation as $eCultivation) {

			$eCultivation['sowingWeek'] = NULL;
			$eCultivation['sowingAction'] = NULL;
			$eCultivation['sowingDone'] = FALSE;
			$eCultivation['plantingWeek'] = NULL;
			$eCultivation['plantingDone'] = FALSE;

			// Les colonnes sont `?json` : le défaut se pose ici plutôt qu'à chaque lecture de la frise.
			// Les campagnes se découpent ici et non dans l'UI, pour que la vue par espèce et la frise
			// d'une ferme suivent la même règle (`getCampaigns()`) : une récolte saisie en deux temps
			// y fait deux rubans dans les deux vues.
			foreach(['harvestWeeksExpected' => 'harvestExpected', 'harvestWeeks' => 'harvestDone'] as $property => $campaigns) {
				$eCultivation[$property] = $eCultivation[$property] ?? [];
				$eCultivation[$campaigns] = self::getCampaigns($eCultivation[$property]);
			}

		}

		$cTask = Task::model()
			->join(\work\Action::model(), 'm1.action = m2.id')
			->select(['cultivation', 'status', 'plannedWeek', 'doneWeek', 'fqn' => new \Sql('m2.fqn')])
			->where('m1.cultivation', 'IN', $cCultivation)
			->where('m1.plannedWeek IS NOT NULL')
			->where('m2.fqn', 'IN', [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION])
			->getCollection();

		foreach($cTask as $eTask) {

			$id = $eTask['cultivation']['id'];

			if($cCultivation->offsetExists($id) === FALSE) {
				continue;
			}

			$gesture = ($eTask['fqn'] === ACTION_PLANTATION) ? 'planting' : 'sowing';

			$done = ($eTask['status'] === Task::DONE);
			$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'];

			$current = $cCultivation[$id][$gesture.'Week'];

			if($current === NULL or $week < $current) {

				$cCultivation[$id][$gesture.'Week'] = $week;
				$cCultivation[$id][$gesture.'Done'] = $done;

				// ⚠️ Le semis direct et le semis en pépinière se distinguent : ce sont deux conduites,
				// et c'est précisément ce qu'un formateur vient comparer. Le `fqn` était lu puis
				// jeté, la frise ne montrant qu'un « semis » indifférencié.
				if($gesture === 'sowing') {
					$cCultivation[$id]['sowingAction'] = $eTask['fqn'];
				}

			}

		}

	}

	/**
	 * Les espèces d'une ferme, et pour chacune ce que les fermes comparées en ont fait.
	 *
	 * Chaque espèce rendue porte `farms`, indexé par ferme, et seules les fermes qui la cultivent
	 * y figurent : `gesture` (le premier semis ou la première plantation, sa semaine, son
	 * intervention et son état, ou NULL si aucun n'est daté), `harvestExpected` et `harvestDone`
	 * (les campagnes de récolte, prévue et obtenue).
	 *
	 * ⚠️ **Les espèces sont celles de la ferme de départ, jamais l'union des fermes comparées.**
	 * C'est la ferme qu'on regarde : une espèce qu'elle ne cultive pas n'a pas de ligne à mettre
	 * en regard, et l'ajouter ferait lire le réseau à sa place.
	 *
	 * ⚠️ Les gestes se lisent par `fillActions()`, donc par un `IN` sur les cultures, et non par un
	 * `IN` sur les fermes : mesuré le 16/09/2026 sur les 5 421 cultures de 6388, l'index
	 * `(cultivation, action)` lit 27 105 lignes pour 6 015 rendues, en 160 à 200 ms. La forme par
	 * ferme lisait 55 422 lignes pour 972 utiles sur cinq fermes seulement.
	 */
	public static function getSpeciesTimeline(\Collection $cStructure, \structure\Structure $eStructureFarm, int $season): \Collection {

		$cPlant = self::getSpecies(new \Collection([$eStructureFarm['id'] => $eStructureFarm]), $season);

		if($cPlant->empty()) {
			return $cPlant;
		}

		$cCultivation = Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'id', 'structure', 'harvestWeeks', 'harvestWeeksExpected',
				'reference' => new \Sql('m2.reference', 'int')
			])
			->where('m1.structure', 'IN', $cStructure)
			->where('m1.season', $season)
			->where('m2.reference', 'IN', $cPlant)
			->getCollection(index: 'id');

		self::fillActions($cCultivation);

		$farms = [];

		foreach($cCultivation as $eCultivation) {

			$reference = $eCultivation['reference'];
			$structure = $eCultivation['structure']['id'];

			$farms[$reference][$structure] ??= ['gesture' => NULL, 'harvestExpected' => [], 'harvestDone' => []];

			$farm = &$farms[$reference][$structure];

			foreach(['sowing', 'planting'] as $gesture) {

				$week = $eCultivation[$gesture.'Week'];

				if($week !== NULL and ($farm['gesture'] === NULL or $week < $farm['gesture']['week'])) {
					$farm['gesture'] = [
						'week' => $week,
						'action' => ($gesture === 'sowing') ? $eCultivation['sowingAction'] : ACTION_PLANTATION,
						'done' => $eCultivation[$gesture.'Done']
					];
				}

			}

			$farm['harvestExpected'] = array_merge($farm['harvestExpected'], $eCultivation['harvestWeeksExpected']);
			$farm['harvestDone'] = array_merge($farm['harvestDone'], $eCultivation['harvestWeeks']);

			unset($farm);

		}

		foreach($cPlant as $ePlant) {

			$ePlant['farms'] = $farms[$ePlant['id']] ?? [];

			foreach($ePlant['farms'] as $structure => $farm) {
				$ePlant['farms'][$structure]['harvestExpected'] = self::getCampaigns($farm['harvestExpected']);
				$ePlant['farms'][$structure]['harvestDone'] = self::getCampaigns($farm['harvestDone']);
			}

		}

		return $cPlant;

	}

	/**
	 * Des semaines de récolte, rangées en campagnes : `[[première, dernière], …]`.
	 *
	 * ⚠️ Un trou de plus de 45 jours ouvre une campagne, et c'est la règle du plan de culture
	 * (`CultivationUi::getTimeline()`). Les semaines de plusieurs cultures s'additionnent ici, et un
	 * seul ruban du premier au dernier jour ferait lire une récolte continue entre une tomate de
	 * printemps et une d'automne.
	 *
	 * ⚠️ Les semaines débordent sur l'année suivante (`harvestWeeks` est en semaines ISO) : le tri
	 * se fait sur la chaîne, `2026-W52` avant `2027-W01`, et chaque semaine compte.
	 */
	private static function getCampaigns(array $weeks): array {

		$weeks = array_values(array_unique($weeks));
		sort($weeks);

		$campaigns = [];
		$previous = NULL;

		foreach($weeks as $week) {

			$time = strtotime(week_date_starts($week));

			if($previous === NULL or ($time - $previous) > 45 * 86400) {
				$campaigns[] = [$week, $week];
			} else {
				$campaigns[count($campaigns) - 1][1] = $week;
			}

			$previous = $time;

		}

		return $campaigns;

	}

	/**
	 * Les saisons que les fermes du groupe ont en commun, de la plus récente à la plus ancienne.
	 */
	public static function getSeasons(\Collection $cStructure): array {

		if($cStructure->empty()) {
			return [];
		}

		$seasons = Cultivation::model()
			->select(['season'])
			->where('structure', 'IN', $cStructure)
			->group('season')
			->getCollection()
			->getColumn('season');

		$seasons = array_map('intval', $seasons);

		rsort($seasons);

		return $seasons;

	}

}
?>
