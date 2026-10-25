<?php
namespace plant;

/**
 * Variety basic functions
 */
class VarietyLib extends VarietyCrud {

	/**
	 * Largeur de la frise d'usage : l'année entière, pour que deux variétés se
	 * comparent d'un coup d'œil.
	 */
	private const WEEKS = 52;

	const VOTES_CACHE = 'plant-variety-votes';

	/**
	 * Synthèse de l'usage réel de la variété dans la structure : combien de fois
	 * elle a été cultivée, et ce que les interventions réalisées disent des semis,
	 * plantations et récoltes.
	 *
	 * On s'appuie sur les tâches faites (doneWeek renseignée) plutôt que sur le
	 * prévisionnel : c'est le vécu qui éclaire une note, pas le plan.
	 */
	public static function getUsageByVariety(\structure\Structure $eStructure, Variety $eVariety): array {

		$cSlice = \series\Slice::model()
			->select([
				'cultivation' => ['season', 'harvestWeeks', 'harvestWeeksExpected'],
			])
			->whereStructure($eStructure)
			->whereVariety($eVariety)
			->getCollection();

		// On ne remonte pas au-delà des dernières saisons : les pratiques évoluent,
		// et une culture d'il y a dix ans dirait peu de la variété aujourd'hui.
		$allSeasons = [];

		foreach($cSlice as $eSlice) {

			// La saison plutôt que l'élément : une tranche dont la culture a été supprimée porte
			// une culture réduite à son identifiant (cf. getUsageByStructure()).
			if($eSlice['cultivation']->offsetExists('season')) {
				$allSeasons[$eSlice['cultivation']['season']] = TRUE;
			}

		}

		$allSeasons = array_keys($allSeasons);
		rsort($allSeasons);

		$seasons = array_slice($allSeasons, 0, PlantSetting::RATING_SEASONS);
		sort($seasons);

		$cultivations = 0;
		$cultivationIds = [];
		$harvestWeeks = [];

		foreach($cSlice as $eSlice) {

			$eCultivation = $eSlice['cultivation'];

			if($eCultivation->offsetExists('season') === FALSE or in_array($eCultivation['season'], $seasons, TRUE) === FALSE) {
				continue;
			}

			$cultivations++;
			$cultivationIds[] = $eCultivation['id'];

			// Les semaines de récolte sont portées par la culture, et débordent sur
			// l'année suivante pour un légume d'hiver : les compter une par une est
			// la seule façon de ne pas obtenir une plage qui couvre toute l'année.
			foreach($eCultivation['harvestWeeks'] ?: $eCultivation['harvestWeeksExpected'] ?: [] as $week) {
				$harvestWeeks[] = $week;
			}

		}

		if($cultivationIds === []) {
			return ['cultivations' => 0, 'seasons' => $seasons];
		}

		// Les interventions de semis et de plantation ne portent presque jamais la
		// variété : elles sont posées sur la culture. Or les dates d'une culture
		// valent pour toutes les variétés qu'elle contient, on part donc de là.
		$cTask = \series\Task::model()
			->select([
				'action' => ['fqn'],
				'doneWeek',
			])
			->whereStructure($eStructure)
			->whereCultivation('IN', $cultivationIds)
			->whereStatus(\series\Task::DONE)
			->whereDoneWeek('!=', NULL)
			->getCollection();

		// Une barre par action, avec ses couleurs habituelles : celles que la
		// structure a choisies pour son plan de culture.
		$fqns = [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION, ACTION_RECOLTE];

		$weeks = array_fill_keys($fqns, []);

		// La récolte vient de harvestWeeks, pas des tâches : une culture porte
		// plusieurs semaines de récolte alors qu'une tâche n'en date qu'une.
		foreach($harvestWeeks as $week) {
			$weeks[ACTION_RECOLTE][] = $week;
		}

		foreach($cTask as $eTask) {

			$fqn = $eTask['action']['fqn'] ?? NULL;

			if($fqn === ACTION_RECOLTE or in_array($fqn, $fqns, TRUE) === FALSE) {
				continue;
			}

			$weeks[$fqn][] = $eTask['doneWeek'];

		}

		$cAction = \work\ActionLib::getByStructure($eStructure, fqn: $fqns, index: 'fqn');

		$steps = [];

		foreach($fqns as $fqn) {

			$step = self::summarizeWeeks($weeks[$fqn]);

			if($step === NULL or $cAction->offsetExists($fqn) === FALSE) {
				continue;
			}

			$steps[$fqn] = $step + [
				'label' => $cAction[$fqn]['name'],
				'color' => $cAction[$fqn]['color'],
			];

		}

		return [
			'cultivations' => $cultivations,
			'seasons' => $seasons,
			'steps' => $steps,
		];

	}

	/**
	 * Le même relevé d'usage que getUsageByVariety(), pour toutes les variétés de la
	 * structure d'un coup, sans la frise : combien de cultures et sur quelles
	 * saisons, indexé par variété.
	 *
	 * La liste de notation en montre plusieurs centaines : une requête par variété
	 * en ferait autant d'allers-retours, alors que l'index (structure, variety) de
	 * series\Slice rend les siennes en une fois.
	 *
	 * La fenêtre des saisons est celle du tiroir, à la ligne près : la ligne repliée
	 * et le tiroir qu'elle ouvre affichent le même compte, sans quoi l'écran se
	 * contredirait à l'ouverture.
	 */
	public static function getUsageByStructure(\structure\Structure $eStructure): array {

		$cSlice = \series\Slice::model()
			->select([
				'variety',
				'cultivation' => ['season'],
			])
			->whereStructure($eStructure)
			->getCollection();

		$cultivationsByVariety = [];

		foreach($cSlice as $eSlice) {

			$varietyId = $eSlice['variety']['id'];

			$cultivationsByVariety[$varietyId] ??= [];

			// Une culture supprimée laisse parfois sa tranche derrière elle. La propriété déléguée
			// arrive alors porteuse de son seul identifiant : `notEmpty()` répond vrai, la saison
			// manque, le warning casse le JSON de la réponse ajax et la tranche se compte sous une
			// clé vide. 186 tranches sont dans ce cas au 08/09/2026, toutes sur une même structure
			// et sur deux journées de janvier 2026 ; les chemins de suppression actuels emportent
			// bien les tranches.
			if($eSlice['cultivation']->offsetExists('season') === FALSE) {
				continue;
			}

			// Une culture ne porte qu'une fois la même variété (contrainte d'unicité
			// sur series\Slice) : compter les parts, c'est compter les cultures.
			$season = $eSlice['cultivation']['season'];

			$cultivationsByVariety[$varietyId][$season] ??= 0;
			$cultivationsByVariety[$varietyId][$season]++;

		}

		$usages = [];

		foreach($cultivationsByVariety as $varietyId => $cultivationsBySeason) {

			krsort($cultivationsBySeason);

			$cultivationsBySeason = array_slice($cultivationsBySeason, 0, PlantSetting::RATING_SEASONS, TRUE);

			ksort($cultivationsBySeason);

			$usages[$varietyId] = [
				'cultivations' => array_sum($cultivationsBySeason),
				'seasons' => array_keys($cultivationsBySeason),
			];

		}

		return $usages;

	}

	/**
	 * Dit si la structure s'est prononcée sur cette variété.
	 *
	 * Ce n'est pas la note qui le dit : on peut noter des critères sans donner de note
	 * globale, et l'écran doit alors montrer cinq étoiles vides plutôt que rien. Seul
	 * un drapeau posé à l'écriture distingue « noté sans note globale » de « jamais
	 * ouvert ».
	 */
	public static function updateRatingStatus(Variety $eVariety, string $ratingStatus): void {

		$eVariety['ratingStatus'] = $ratingStatus;

		Variety::model()
			->select(['ratingStatus'])
			->update($eVariety);

		\Cache::redis()->delete(self::VOTES_CACHE);

	}

	/**
	 * Compte les votes de la campagne, toutes structures réunies : un vote est une
	 * variété sur laquelle une structure s'est prononcée.
	 *
	 * Aucun index ne porte ratingStatus et la table est lue en entier : le compte est
	 * gardé une heure, et oublié dès qu'un statut de notation change.
	 */
	public static function countVotes(): int {

		return \Cache::redis()->query(
			self::VOTES_CACHE,
			fn() => Variety::model()
				->whereRatingStatus(Variety::RATED)
				->count(),
			3600
		);

	}

	/**
	 * Répartit des semaines ISO sur l'année sous forme d'intensités.
	 *
	 * Surtout pas une plage min/max : sur des séries échelonnées, ou sur un légume
	 * d'hiver récolté à cheval sur deux années civiles, les extrêmes couvrent
	 * l'année entière et ne disent plus rien. On compte donc chaque semaine
	 * séparément, et on rend une intensité relative au pic.
	 *
	 * L'intensité est calculée ici et non dans l'UI, qui ne doit rien calculer.
	 */
	private static function summarizeWeeks(array $weeks): ?array {

		if($weeks === []) {
			return NULL;
		}

		$counts = array_fill(1, self::WEEKS, 0);

		foreach($weeks as $week) {

			// Format 2026-W31, et la semaine 53 est repliée sur la 52 pour garder
			// une grille alignée sur les mois.
			$number = min((int)substr($week, 6, 2), self::WEEKS);

			if($number >= 1) {
				$counts[$number]++;
			}

		}

		$peak = max($counts);

		$intensities = [];

		foreach($counts as $number => $count) {
			// Plancher à 0,3 pour qu'une semaine isolée reste visible face au pic.
			$intensities[$number] = ($count === 0) ? 0 : round(0.3 + 0.7 * $count / $peak, 2);
		}

		return [
			'count' => count($weeks),
			'weeks' => $intensities,
		];

	}


	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['plant', 'name', 'supplierSeed', 'supplierPlant', 'weightSeed1000', 'numberPlantKilogram'];
	}

	// unit vient avant stock : c'est elle qui dit dans quelle unité lire la quantité saisie.
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'supplierSeed', 'supplierPlant', 'weightSeed1000', 'numberPlantKilogram', 'unit', 'stock'];
	}

	public static function query(\structure\Structure $eStructure, Plant $ePlant, ?string $search = NULL): \Collection {

		return Variety::model()
			->select(Variety::getSelection())
			->or(
				fn() => $this
					->whereStructure($eStructure)
					->wherePlant($ePlant),
				fn() => $this
					->whereId('IN', [\plant\PlantSetting::VARIETY_MIX, \plant\PlantSetting::VARIETY_UNKNOWN]),
			)
			->whereName('LIKE', '%'.$search.'%', if: ($search !== NULL and $search !== ''))
			->sort('name')
			->getCollection();

	}

	public static function getByStructureAndPlant(\structure\Structure $eStructure, Plant $ePlant): \Collection {

		return Variety::model()
			->select(Variety::getSelection())
			->whereStructure($eStructure)
			->wherePlant($ePlant)
			->sort('name')
			->getCollection();

	}

	/**
	 * Rattache une variété à sa variété de référence, si son nom la désigne.
	 *
	 * Posé sur l'élément et non à l'insertion : le sélecteur du plan de culture
	 * construit ses éléments puis les insère en lot par le modèle, sans passer par
	 * create(). Les deux chemins appellent donc ceci.
	 */
	public static function setReference(Variety $e): void {

		$e->expects(['structure', 'plant', 'name']);

		if(
			$e['structure']->empty() or
			($e['fqn'] ?? NULL) !== NULL
		) {
			return;
		}

		$eVarietyReference = VarietyNameAiLib::getVarietyByName($e['plant'], $e['name']);

		if($eVarietyReference->empty()) {
			return;
		}

		$e['fqn'] = $eVarietyReference['fqn'];
		$e['reference'] = $eVarietyReference;

	}

	public static function create(Variety $e): void {

		$e->expects(['structure', 'plant']);

		self::setReference($e);

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Variety::fail('name.duplicate');
		}

	}

	public static function update(Variety $e, array $properties): void {

		Variety::model()->beginTransaction();

		// Propriété virtuelle : elle ne désigne aucune colonne mais l'unité dans laquelle
		// le stock a été saisi, que build() a déjà convertie en graines.
		array_delete($properties, 'unit');

		// Propriété virtuelle : elle ne désigne aucune colonne de la variété mais sa table
		// de notes, que build() a préparée dans $e['cVarietyRating'].
		if(array_delete($properties, 'ratingList')) {

			$e->expects(['structure', 'cVarietyRating']);

			VarietyRatingAiLib::replaceCollection($e['structure'], $e, $e['cVarietyRating']);

		}

		if($properties) {

			try {
				parent::update($e, $properties);
			} catch(\DuplicateException) {
				Variety::fail('name.duplicate');
			}

			if(in_array('ratingStatus', $properties)) {
				\Cache::redis()->delete(self::VOTES_CACHE);
			}

		}

		Variety::model()->commit();

	}

	public static function delete(Variety $e): void {

		$e->expects(['id']);

		if(\series\Slice::model()
				->whereVariety($e)
				->exists() or
			\sequence\Slice::model()
				->whereVariety($e)
				->exists()) {
			Variety::fail('deleteUsed');
			return;
		}

		Variety::model()->beginTransaction();

			Variety::model()->delete($e);

			VarietyRatingAiLib::deleteByVariety($e);

		Variety::model()->commit();

	}

}
?>
