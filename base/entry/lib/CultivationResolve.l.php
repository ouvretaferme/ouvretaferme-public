<?php
namespace entry;

/**
 * Quelle culture une espèce nommée désigne cette semaine (arbre, N10.2, et piste C de S2e, 17/10).
 *
 * Les candidates sont les cultures de l'espèce dans la saison de la semaine, sur une série ouverte,
 * plus celles d'une saison voisine qui sont en phase de récolte : un poireau de la saison passée se
 * récolte en janvier. S'y ajoutent les cultures d'une saison voisine, même hors phase, qui ont une
 * récolte : l'ail de la saison passée dont la récolte est restée à faire. Une saison dite (« ail 2025 »,
 * S2f) remplace ces règles : les candidates sont les cultures de cette saison, toutes.
 *
 * Une culture a une récolte quand Consigner en a une à lui compléter (`eTaskTarget`) : à faire ou faite
 * dans la semaine, ou à faire en retard. Ce sont les cultures qui ont une récolte qui désignent : une
 * seule s'écrit, plusieurs font la question. Sans aucune, une seule candidate est retenue sans question,
 * même hors phase ; à partir de deux, la phase doit en désigner une seule, sinon la question Q2 est
 * posée. Jamais d'écriture silencieuse sur une culture hors phase parmi plusieurs.
 *
 * Toutes les tables lues vivent dans la base commune.
 */
class CultivationResolveLib {

	/**
	 * Les bornes des fenêtres d'une culture : une semaine avant ce qui l'ouvre, vingt jours après sa dernière
	 * semaine de récolte, faite ou prévue, et seize semaines après le départ quand aucune semaine de récolte
	 * n'est connue.
	 */
	const int PHASE_BEFORE_DAYS = 7;
	const int PHASE_AFTER_DAYS = 20;
	const int PHASE_DEFAULT_WEEKS = 16;

	/**
	 * La phase d'un semis ou d'une plantation : autant de semaines avant et après le départ de la culture.
	 */
	const int PHASE_START_WEEKS = 3;

	/**
	 * La fenêtre de fréquence de récolte, qui range les espèces proposées.
	 */
	const int FREQUENCY_DAYS = 14;

	/**
	 * Les cultures candidates de la structure pour une date, rangées par espèce : les espèces en
	 * phase d'abord, par nombre de jours de récolte sur les quatorze derniers jours, puis par nom.
	 *
	 * Chaque espèce porte `cCultivation` (ses candidates, en phase d'abord puis la plus récente),
	 * `inPhase` (le nombre de candidates en phase), `frequency` et `today` (le récolté du jour par
	 * unité). Chaque culture porte `inPhase`, `today`, `eTaskTarget`, la récolte que Consigner lui
	 * compléterait, vide quand elle n'en a pas, `eTaskDelayed`, la même quand elle est en retard, et
	 * `nameWithSeason`, vrai quand sa série n'est pas de la saison de la date : l'écran dit alors sa
	 * saison (26/09), par la règle de Consigner (`\planning\HarvestWeekLib::prepareNames()`).
	 * `eTaskTarget` est la lecture de `\planning\HarvestWeekLib::getTaskForEntry()` sans unité dite, ni
	 * couple, ni carte, `\series\TaskLib::getHarvestForWeek()`, faite ici pour toutes les cultures en une
	 * fois et réduite à ce que la liste lit : la culture retenue relit son intervention entière par
	 * `getTaskForEntry()`.
	 *
	 * La lecture se fait en deux parts. Les cultures des saisons qui entourent la date, avec leurs
	 * séries et leurs espèces, ne dépendent que des saisons lues : la collection rendue les garde
	 * (`cCultivation`, `seasons`), toutes, y compris celles qu'aucune espèce ne propose, et un appel
	 * qui lit les mêmes saisons les reprend de `$cPlantRead`. La phase et le récolté dépendent du
	 * jour : ils se calculent à chaque appel, et le récolté et les récoltes à compléter se reprennent
	 * aussi (`date`, `harvested`, `cTaskTarget`) quand le jour est le même.
	 *
	 * Les éléments repris sont les mêmes objets, et ce second appel réécrit ce qu'ils portent du jour
	 * (`inPhase`, `today`, `eTaskTarget`, `eTaskDelayed`, les `cCultivation` des espèces) : `$cPlantRead` dit ensuite le jour du
	 * second appel, et l'appelant ne lit plus que la collection rendue.
	 *
	 * `$seasonSaid`, la saison dite (`SeasonComplementLib`), fait des cultures de cette saison les seules
	 * candidates, en phase ou non. Elle est toujours une des saisons lues : le complément refuse les autres.
	 */
	public static function getPlants(\structure\Structure $eStructure, string $date, \Collection $cPlantRead = new \Collection(), ?int $seasonSaid = NULL): \Collection {

		$week = toWeek($date);
		$season = week_year($week);

		$seasons = self::getSeasons($date);

		// Un premier appel passe une collection vide, qui ne porte aucune lecture. Une lecture précédente
		// sans espèce se refait avec lui : une phrase qui n'a rien à proposer n'a rien à épargner.
		$reuse = ($cPlantRead->notEmpty() and $cPlantRead->getProperty('seasons') === $seasons);

		// Au même jour, seule une saison dite a changé : elle ne change que les candidates, et le récolté
		// comme les récoltes à compléter se reprennent.
		$reuseDay = ($reuse and $cPlantRead->getProperty('date') === $date);

		$cCultivation = $reuse ? $cPlantRead->getProperty('cCultivation') : self::getCultivations($eStructure, $seasons);

		$harvested = $reuseDay ? $cPlantRead->getProperty('harvested') : self::getHarvested($eStructure, $date);

		// Sans clone : les éléments repris d'un appel à l'autre restent les objets de la collection gardée.
		$cCultivationOpen = $cCultivation->find(fn($eCultivation) => $eCultivation['series']['status'] === \series\Series::OPEN, clone: FALSE);

		$cTaskTarget = $reuseDay ?
			$cPlantRead->getProperty('cTaskTarget') :
			\series\TaskLib::getHarvestForWeek($eStructure, $cCultivationOpen, $week, properties: ['id', 'plannedWeek']);

		$cPlant = new \Collection();
		$cPlant->setProperty('seasons', $seasons);
		$cPlant->setProperty('cCultivation', $cCultivation);
		$cPlant->setProperty('date', $date);
		$cPlant->setProperty('harvested', $harvested);
		$cPlant->setProperty('cTaskTarget', $cTaskTarget);
		$days = [];
		$today = [];

		foreach($cCultivationOpen as $eCultivation) {

			$eCultivation['inPhase'] = self::isInHarvest($eCultivation, $week);
			$eCultivation['nameWithSeason'] = ($eCultivation['series']['season'] !== $season);
			$eCultivation['eTaskTarget'] = $cTaskTarget->offsetExists($eCultivation['id']) ? $cTaskTarget[$eCultivation['id']] : new \series\Task();
			$eCultivation['eTaskDelayed'] = self::getDelayed($eCultivation['eTaskTarget'], $week);

			if($seasonSaid !== NULL) {

				if($eCultivation['season'] !== $seasonSaid) {
					continue;
				}

			} else if(
				$eCultivation['season'] !== $season and
				$eCultivation['inPhase'] === FALSE and
				$eCultivation['eTaskTarget']->empty()
			) {
				continue;
			}

			$eCultivation['today'] = $harvested[$eCultivation['id']]['today'] ?? [];

			$plant = $eCultivation['plant']['id'];

			if($cPlant->offsetExists($plant) === FALSE) {

				$cPlant[$plant] = $eCultivation['plant'];
				$cPlant[$plant]['cCultivation'] = new \Collection();
				$cPlant[$plant]['inPhase'] = 0;

				$days[$plant] = [];
				$today[$plant] = [];

			}

			$cPlant[$plant]['cCultivation'][$eCultivation['id']] = $eCultivation;
			$cPlant[$plant]['inPhase'] += (int)$eCultivation['inPhase'];

			foreach($harvested[$eCultivation['id']]['days'] ?? [] as $day) {
				$days[$plant][$day] = TRUE;
			}

			foreach($eCultivation['today'] as $unit => $quantity) {
				$today[$plant][$unit] = ($today[$plant][$unit] ?? 0.0) + $quantity;
			}

		}

		foreach($cPlant as $plant => $ePlant) {

			$ePlant['frequency'] = count($days[$plant]);
			$ePlant['today'] = $today[$plant];
			$ePlant['cCultivation']->sort(fn($e1, $e2) => self::compare($e1, $e2));

		}

		$cPlant->sort(function(\plant\Plant $e1, \plant\Plant $e2) {

			return (
				(($e2['inPhase'] > 0) <=> ($e1['inPhase'] > 0)) ?:
				($e2['frequency'] <=> $e1['frequency']) ?:
				strcoll($e1['name'], $e2['name'])
			);

		});

		return $cPlant;

	}

	/**
	 * La saison d'une date et les deux qui l'entourent (26/09, élargissement aux saisons 2025
	 * à 2027 en 2026) : celles dont la saisie lit les cultures, et celles où une récolte de ce jour peut
	 * s'écrire, une intervention refusant une semaine à plus d'une saison de la sienne
	 * (`\series\Task::planned.season`). `SeasonComplementLib` refuse une saison dite hors d'elles.
	 */
	public static function getSeasons(string $date): array {

		$season = week_year(toWeek($date));

		return [$season - 1, $season, $season + 1];

	}

	/**
	 * Les cultures de ces saisons, avec ce que la lecture d'une phrase et l'écran lisent de leur série et
	 * de leur espèce, dont le poids d'une caisse, qui convertit en kilos une récolte dite en caisses.
	 */
	private static function getCultivations(\structure\Structure $eStructure, array $seasons): \Collection {

		return \series\Cultivation::model()
			->select([
				'id', 'season', 'startWeek', 'startAction', 'mainUnit',
				'harvestWeeks', 'harvestWeeksExpected',
				'plant' => ['name', 'fqn', 'vignette', 'crateWeight'],
				'series' => ['name', 'status', 'season', 'use']
			])
			->whereStructure($eStructure)
			->whereSeason('IN', $seasons)
			->getCollection(index: 'id');

	}

	/**
	 * Les cultures de l'espèce sur lesquelles un temps peut ajouter son intervention au planning (lot 2c) :
	 * celles des saisons que lit la récolte, dans une série ouverte. La saison du jour vient d'abord, puis
	 * la suivante, puis celle d'avant, et dans une saison la culture qui démarre le plus tard. Une série
	 * close ne s'y propose pas : une intervention à faire n'y apparaît plus nulle part
	 * (`\series\TaskLib::applyOpenSeries()`). `nameWithSeason` dit, comme pour la récolte, qu'une ligne
	 * nomme sa saison. Le cycle de la série sert à l'écriture : un semis direct ou une plantation créés
	 * recalculent leur série, qui le lit (`\series\SeriesLib::recalculate()`).
	 */
	public static function getByPlant(\structure\Structure $eStructure, \plant\Plant $ePlant, string $date): \Collection {

		$season = week_year(toWeek($date));

		return \series\Cultivation::model()
			->select([
				'id', 'season', 'startWeek', 'startAction', 'harvestWeeks', 'harvestWeeksExpected',
				'plant' => ['name', 'fqn'],
				'series' => ['name', 'status', 'season', 'use', 'cycle']
			])
			->whereStructure($eStructure)
			->wherePlant($ePlant)
			->whereSeason('IN', self::getSeasons($date))
			->getCollection(index: 'id')
			->find(fn(\series\Cultivation $eCultivation) => $eCultivation['series']['status'] === \series\Series::OPEN)
			->map(fn(\series\Cultivation $eCultivation) => $eCultivation->merge([
				'nameWithSeason' => ($eCultivation['series']['season'] !== $season),
			]))
			->sort(fn(\series\Cultivation $e1, \series\Cultivation $e2) => (
				(($e1['season'] !== $season) <=> ($e2['season'] !== $season)) ?:
				($e2['season'] <=> $e1['season']) ?:
				(($e2['startWeek'] ?? PHP_INT_MIN) <=> ($e1['startWeek'] ?? PHP_INT_MIN)) ?:
				($e2['id'] <=> $e1['id'])
			));

	}

	/**
	 * Les cultures où créer une intervention de l'action, en phase d'abord, puis la plus récente : l'ordre que
	 * la mesure du choix d'une culture désigne le mieux (C2 puis la plus récente, `S4/culture-adaptee.md`),
	 * l'id tenant lieu de date de création. Chacune porte `inPhase`.
	 *
	 * La phase d'un semis ou d'une plantation est autour du départ, à `PHASE_START_WEEKS` semaines près ;
	 * celle de toute autre action, la culture en terre, du départ à la fin des récoltes (`isInCultivation()`) :
	 * un désherbage se fait avant la première récolte.
	 */
	public static function sortByPhase(\Collection $cCultivation, \work\Action $eAction, string $week): \Collection {

		$start = in_array($eAction['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION], TRUE);

		foreach($cCultivation as $eCultivation) {
			$eCultivation['inPhase'] = $start ? self::isAroundStart($eCultivation, $week) : self::isInCultivation($eCultivation, $week);
		}

		return $cCultivation->sort(fn(\series\Cultivation $e1, \series\Cultivation $e2) => (
			($e2['inPhase'] <=> $e1['inPhase']) ?:
			($e2['id'] <=> $e1['id'])
		));

	}

	/**
	 * La semaine est-elle à `PHASE_START_WEEKS` semaines du départ de la culture. Une culture sans semaine de
	 * départ ne l'est jamais.
	 */
	private static function isAroundStart(\series\Cultivation $eCultivation, string $week): bool {

		if($eCultivation['startWeek'] === NULL) {
			return FALSE;
		}

		$start = week_date_starts($eCultivation->getStartWeek($eCultivation['season']));

		return (
			strcmp(toWeek($start.' - '.self::PHASE_START_WEEKS.' WEEKS'), $week) <= 0 and
			strcmp($week, toWeek($start.' + '.self::PHASE_START_WEEKS.' WEEKS')) <= 0
		);

	}

	/**
	 * La phase C2 d'une récolte : la semaine est-elle dans la fenêtre de récolte de la culture, de sa première
	 * semaine de récolte à sa dernière, faite ou prévue. Elle ne part pas du départ : une culture semée à
	 * l'automne pour le printemps suivant serait sinon proposée à la récolte dès son semis (20/10).
	 * Sans semaine de récolte connue, la fenêtre est celle de la culture en terre.
	 *
	 * ⚠️ Les semaines se comparent en chaînes `YYYY-Www` : une récolte de fin d'année déborde sur la
	 * suivante, et un numéro de semaine seul ferait passer 2027-W02 avant 2026-W50.
	 */
	public static function isInHarvest(\series\Cultivation $eCultivation, string $week): bool {

		$harvestWeeks = self::getHarvestWeeks($eCultivation);

		if($harvestWeeks === []) {
			return self::isInCultivation($eCultivation, $week);
		}

		$from = toWeek(week_date_starts(min($harvestWeeks)).' - '.self::PHASE_BEFORE_DAYS.' DAYS');
		$until = toWeek(week_date_ends(max($harvestWeeks)).' + '.self::PHASE_AFTER_DAYS.' DAYS');

		return (strcmp($from, $week) <= 0 and strcmp($week, $until) <= 0);

	}

	/**
	 * La culture est-elle en terre cette semaine, de son départ à la fin de ses récoltes. Une culture sans
	 * semaine de départ ne l'est jamais.
	 */
	public static function isInCultivation(\series\Cultivation $eCultivation, string $week): bool {

		if($eCultivation['startWeek'] === NULL) {
			return FALSE;
		}

		$start = week_date_starts($eCultivation->getStartWeek($eCultivation['season']));

		$from = toWeek($start.' - '.self::PHASE_BEFORE_DAYS.' DAYS');

		$harvestWeeks = self::getHarvestWeeks($eCultivation);

		if($harvestWeeks === []) {
			$until = toWeek($start.' + '.self::PHASE_DEFAULT_WEEKS.' WEEKS');
		} else {
			$until = toWeek(week_date_ends(max($harvestWeeks)).' + '.self::PHASE_AFTER_DAYS.' DAYS');
		}

		return (strcmp($from, $week) <= 0 and strcmp($week, $until) <= 0);

	}

	/**
	 * Les semaines de récolte faites et prévues, deux colonnes nullables.
	 */
	private static function getHarvestWeeks(\series\Cultivation $eCultivation): array {

		return array_merge($eCultivation['harvestWeeks'] ?? [], $eCultivation['harvestWeeksExpected'] ?? []);

	}

	/**
	 * Retient la culture d'une espèce, ou rend les choix de la question.
	 *
	 * `$answer` est l'id d'une culture déjà choisie à la question, `$all` l'élargissement de la
	 * question à toutes les candidates. Rend `['eCultivation' => …, 'cCultivation' => …, 'more' => …]` :
	 * la culture retenue, vide s'il faut demander ; les choix de la question ; le nombre de candidates
	 * que « Toutes les séries » ajouterait.
	 *
	 * Les cultures qui ont une récolte désignent, et la phase seulement quand aucune n'en a : une récolte
	 * terminée dans la semaine garde sa culture parmi celles qui désignent, sinon la saisie du lendemain
	 * écrirait sans question sur la seule autre récolte, en retard (architecte, point 5).
	 */
	public static function resolve(\plant\Plant $ePlant, ?int $answer, bool $all): array {

		$cCultivation = $ePlant['cCultivation'];

		if($answer !== NULL and $cCultivation->offsetExists($answer)) {
			return self::getResolved($cCultivation[$answer]);
		}

		if($cCultivation->count() === 1) {
			return self::getResolved($cCultivation->first());
		}

		$cCultivationTarget = $cCultivation->find(fn($eCultivation) => $eCultivation['eTaskTarget']->notEmpty());

		if($cCultivationTarget->notEmpty()) {
			return self::designate($cCultivation, $cCultivationTarget, $all);
		}

		return self::designate($cCultivation, $cCultivation->find(fn($eCultivation) => $eCultivation['inPhase']), $all);

	}

	/**
	 * Une seule culture désignée est retenue. Plusieurs font la question, et « Toutes les séries »
	 * l'élargit aux autres candidates ; aucune fait la question sur toutes.
	 */
	private static function designate(\Collection $cCultivation, \Collection $cCultivationDesignated, bool $all): array {

		if($cCultivationDesignated->count() === 1) {
			return self::getResolved($cCultivationDesignated->first());
		}

		if($all or $cCultivationDesignated->count() < 2) {
			return self::getResolved(new \series\Cultivation(), $cCultivation);
		}

		return self::getResolved(new \series\Cultivation(), $cCultivationDesignated, $cCultivation->count() - $cCultivationDesignated->count());

	}

	/**
	 * La récolte elle-même quand elle est en retard sur la semaine, à faire et prévue avant elle, vide
	 * sinon : l'écran dit alors la semaine où elle était prévue.
	 */
	public static function getDelayed(\series\Task $eTask, string $week): \series\Task {

		if(
			$eTask->notEmpty() and
			$eTask['status'] === \series\Task::TODO and
			$eTask['plannedWeek'] < $week
		) {
			return $eTask;
		}

		return new \series\Task();

	}

	private static function getResolved(\series\Cultivation $eCultivation, \Collection $cCultivation = new \Collection(), int $more = 0): array {

		return [
			'eCultivation' => $eCultivation,
			'cCultivation' => $cCultivation,
			'more' => $more
		];

	}

	/**
	 * Le récolté par culture sur les quatorze derniers jours : les jours de récolte, et les quantités
	 * du jour par unité.
	 */
	private static function getHarvested(\structure\Structure $eStructure, string $date): array {

		$cHarvest = \series\Harvest::model()
			->select([
				'cultivation', 'date', 'unit',
				'total' => new \Sql('SUM(quantity)', 'float')
			])
			->whereStructure($eStructure)
			->whereDate('>', date('Y-m-d', strtotime($date.' - '.self::FREQUENCY_DAYS.' DAYS')))
			->whereDate('<=', $date)
			->where('cultivation IS NOT NULL')
			->group(['cultivation', 'date', 'unit'])
			->getCollection();

		$harvested = [];

		foreach($cHarvest as $eHarvest) {

			$cultivation = $eHarvest['cultivation']['id'];

			$harvested[$cultivation]['days'][] = $eHarvest['date'];

			if($eHarvest['date'] === $date) {
				$harvested[$cultivation]['today'][$eHarvest['unit']] = round($eHarvest['total'], 2);
			}

		}

		return $harvested;

	}

	/**
	 * L'ordre des choix : en phase d'abord, puis la culture la plus récente.
	 */
	private static function compare(\series\Cultivation $e1, \series\Cultivation $e2): int {

		return (
			($e2['inPhase'] <=> $e1['inPhase']) ?:
			($e2['season'] <=> $e1['season']) ?:
			(($e2['startWeek'] ?? PHP_INT_MIN) <=> ($e1['startWeek'] ?? PHP_INT_MIN)) ?:
			($e2['id'] <=> $e1['id'])
		);

	}

}
?>
