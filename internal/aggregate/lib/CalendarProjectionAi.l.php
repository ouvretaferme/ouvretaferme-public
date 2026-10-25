<?php
namespace aggregate;

/**
 * Reconstruit le calendrier type des espèces par climat depuis les plans de culture.
 *
 * ⚠️ Cette projection ne tourne pas en production, et aucun cron ne la lance : le calendrier a été
 * calculé une fois en dev, validé, puis figé dans `calendarDataAi.php` (`freeze()`),
 * que chaque environnement sème (CalendarAiLib::seed()). La rejouer sur les plans de culture de
 * production redessinerait des saisons que personne n'a relues (02/10/2026 : « le calcul
 * du plan de culture par climat est fait 1 fois ici en dev, et stocké indéfiniment en prod, pas de
 * cron »). Elle reste dans le dépôt comme l'outil qui a produit le fichier, et qui le reproduira
 * si une règle change : `aggregate/configure/calendarAi`, qui refuse la production.
 *
 * Pour chaque espèce du référentiel et chaque climat, la passe compte, semaine par semaine, les
 * structures dont le plan de culture prévoit une récolte, puis retient les semaines où elles sont
 * assez nombreuses. Une structure compte une fois par semaine, quel que soit le nombre de ses
 * séries ou de ses saisons : c'est une habitude qui se mesure, pas un volume.
 *
 * Les règles sont celles validées le 02/10/2026 sur la page du calendrier par climat,
 * et chacune est une constante de cette classe :
 * - l'assiette, les structures productrices installées, et les trois dernières saisons ;
 * - le climat de chaque structure, déclaré sinon déduit en France (CalendarAiLib) ;
 * - le seuil d'une semaine, relatif à la meilleure semaine de l'espèce et borné par un plancher ;
 * - le nettoyage : les petits trous bouchés, les fenêtres trop courtes retirées, deux fenêtres au
 *   plus, et aucun morceau de moins de trois semaines lu de janvier à décembre ;
 * - le repli sur tous les climats quand trop peu de structures cultivent l'espèce dans le climat ;
 * - la conservation (`plant.storage`), qui prolonge la dernière saison de l'année et elle seule.
 *
 * Ce que la passe lit n'est pas borné par le consentement au partage (`structure.consentData`), à
 * la différence des autres projections du package : la règle validée compte toutes les structures
 * installées, et ce qui sort est une saison par climat tenue par vingt-cinq structures au moins,
 * sans rien qui se rattache à l'une d'elles.
 *
 * Aucune écriture dans `series`, `plant` ni `structure`.
 */
class CalendarProjectionAiLib {

	/**
	 * Le nombre de saisons lues, la dernière étant l'année en cours.
	 *
	 * Trois saisons donnent à une espèce peu cultivée assez de structures pour dessiner une saison,
	 * et une saison à venir n'y entre pas : son plan de culture est encore en train de s'écrire.
	 */
	private const SEASONS = 3;

	/**
	 * Une semaine est retenue quand sa part des structures vaut au moins cette fraction de la part
	 * de la meilleure semaine de l'espèce.
	 */
	private const SHARE_RELATIVE = 0.40;

	/**
	 * Et au moins cette part des structures qui cultivent l'espèce. Sans ce plancher, une espèce
	 * récoltée toute l'année par un dixième des structures serait dite de saison toute l'année.
	 */
	private const SHARE_ABSOLUTE = 0.15;

	/**
	 * Le nombre de structures qui cultivent l'espèce sous lequel un climat ne dessine pas sa propre
	 * saison : la ligne prend alors celle de tous les climats. Sous ce nombre tous climats
	 * confondus, l'espèce n'entre pas au calendrier.
	 */
	public const STRUCTURES_MIN = 25;

	/**
	 * Le seuil d'anonymat des tables de l'agrégé, que `online` traduit.
	 */
	private const CONTRIBUTORS = 10;

	/**
	 * Un trou d'au plus ce nombre de semaines entre deux semaines retenues est bouché.
	 */
	private const GAP = 2;

	/**
	 * Une fenêtre de moins de ce nombre de semaines est retirée.
	 */
	private const WINDOW_MIN = 4;

	/**
	 * Le nombre de fenêtres gardées, les plus fournies d'abord.
	 */
	private const WINDOWS_MAX = 2;

	/**
	 * Lue de janvier à décembre, comme un plan de paniers l'écrit, une saison ne garde aucun morceau
	 * de moins de ce nombre de semaines (02/10/2026 : « vire les semaines isolées »). Une
	 * fenêtre qui passe le nouvel an sur le cercle de l'année se coupe en deux morceaux dans le plan,
	 * et un débord d'une ou deux semaines sur janvier y devenait une semaine 1 isolée, que la grille
	 * des paniers prenait pour le début d'une saison.
	 *
	 * Publique parce que le même seuil vaut dans un plan de paniers (r72) : pour une saison réglée à
	 * la main (`toolbox\CompositionSpecies::properties()`, étape `short` de `windowWeeks`) et pour
	 * celle que le plan de culture ou la communauté ouvre (`toolbox\CompositionLib::getWindowWeeksFilled()`).
	 */
	public const PIECE_MIN = 3;

	private const WEEKS = 52;

	/**
	 * Les espèces du référentiel que le calendrier écarte, par fqn : celles qu'un panier ne livre
	 * pas, bien qu'elles portent un usage qui les ferait entrer (02/10/2026).
	 */
	private const EXCLUDED = ['vigne', 'kiwano', 'achillee-millefeuille', 'calendula', 'bourrache', 'monarde', 'agastache', 'nigelle'];

	/**
	 * Les espèces du référentiel qui entrent sans porter aucun des usages retenus. Le mesclun n'en
	 * coche aucun, et c'est un légume de panier.
	 */
	private const INCLUDED = ['mesclun'];

	/**
	 * Cultures lues par requête. Trois saisons en comptent plus de cent mille.
	 */
	private const CHUNK = 20000;

	public static function project(): array {

		$climates = self::getClimateByStructure();
		$weeks = self::getHarvestWeeks($climates);

		$cCalendar = self::getCalendar(self::getCounts($weeks, $climates), self::getPlants());

		self::publish($cCalendar);

		return [
			'structures' => count($weeks),
			'calendars' => $cCalendar->count(),
			'fallback' => $cCalendar->find(fn(Calendar $eCalendar) => $eCalendar['fallback'])->count(),
			'online' => $cCalendar->find(fn(Calendar $eCalendar) => $eCalendar['online'])->count(),
		];

	}

	/**
	 * Fige la table dans `calendarDataAi.php` : les lignes servies, l'espèce désignée par son fqn,
	 * triées par fqn puis dans l'ordre des climats de l'enum, une ligne de fichier par ligne de
	 * table pour qu'un `git diff` montre ce qu'un nouveau calcul déplace.
	 */
	public static function freeze(): int {

		$cCalendar = Calendar::model()
			->select(['id', 'climate', 'plant' => ['fqn'], 'harvestWeeks', 'weeks', 'structures', 'fallback'])
			->whereOnline(TRUE)
			->getCollection();

		$climates = Calendar::model()->getPropertyEnum('climate');

		$frozen = [];

		foreach($cCalendar as $eCalendar) {
			$frozen[$eCalendar['plant']['fqn']][$eCalendar['climate']] = $eCalendar;
		}

		ksort($frozen);

		$h = "<?php\n";
		$h .= "/**\n";
		$h .= " * Le calendrier type des espèces par climat, figé (aggregate\\Calendar).\n";
		$h .= " *\n";
		$h .= " * Calculé une fois en dev par CalendarProjectionAiLib, validé le 02/10/2026, écrit\n";
		$h .= " * ici par CalendarProjectionAiLib::freeze() et semé dans chaque environnement par\n";
		$h .= " * CalendarAiLib::seed() (aggregate/configure/calendarSeedAi). Ne se retouche pas à la main.\n";
		$h .= " *\n";
		$h .= " * Par fqn d'espèce du référentiel partagé, puis par climat : les semaines de récolte, les\n";
		$h .= " * semaines livrables conservation comprise, les structures derrière la ligne, et le repli sur\n";
		$h .= " * tous les climats.\n";
		$h .= " */\n";
		$h .= "return [\n";

		foreach($frozen as $fqn => $calendars) {

			$h .= "\n\t".var_export($fqn, TRUE)." => [\n";

			foreach($climates as $climate) {

				if(array_key_exists($climate, $calendars) === FALSE) {
					continue;
				}

				$eCalendar = $calendars[$climate];

				$h .= "\t\t".var_export($climate, TRUE)." => ['harvestWeeks' => ".json_encode($eCalendar['harvestWeeks']).", 'weeks' => ".json_encode($eCalendar['weeks']).", 'structures' => ".$eCalendar['structures'].", 'fallback' => ".($eCalendar['fallback'] ? 'TRUE' : 'FALSE')."],\n";

			}

			$h .= "\t],\n";

		}

		file_put_contents(CalendarAiLib::FILE, $h."\n];\n");

		return $cCalendar->count();

	}

	/**
	 * Réécrit la table en place, sous transaction, comme selling\PriceProjectionAiLib::publish() et pour la
	 * même raison : l'UNIQUE (climate, plant) interdit de tenir deux versions côte à côte.
	 */
	protected static function publish(\Collection $cCalendar): void {

		Calendar::model()->beginTransaction();

			CalendarAiLib::deleteOffline();
			CalendarAiLib::deleteOnline();
			CalendarAiLib::createCollection($cCalendar);

		Calendar::model()->commit();

	}

	/**
	 * Le climat de chaque structure de l'assiette, `NULL` quand il n'est ni déclaré ni déductible.
	 *
	 * Une structure sans climat reste dans l'assiette : elle compte dans le profil de tous les
	 * climats, celui des replis.
	 */
	protected static function getClimateByStructure(): array {

		$cStructure = \structure\Structure::model()
			->select(['id', 'legalCountry', 'legalPostcode'])
			->whereId('IN', \structure\StructureLib::getInstalledSql())
			->getCollection();

		$cSystem = \structure\System::model()
			->select(['id', 'structure', 'climate', 'placeLngLat', 'altitude'])
			->whereStructure('IN', \structure\StructureLib::getInstalledSql())
			->getCollection(index: 'structure');

		$climates = [];

		foreach($cStructure as $eStructure) {

			$eSystem = $cSystem->offsetExists($eStructure['id']) ? $cSystem[$eStructure['id']] : new \structure\System();

			$climates[$eStructure['id']] = CalendarAiLib::getClimateBySystem($eStructure, $eSystem);

		}

		return $climates;

	}

	/**
	 * La première et la dernière saison lues.
	 */
	protected static function getSeasons(): array {

		$last = \Clock::year();

		return [$last - self::SEASONS + 1, $last];

	}

	/**
	 * Les semaines de récolte prévues, par structure puis par espèce de référence :
	 * `[structure][espèce][semaine] = TRUE`.
	 *
	 * La semaine est lue sans son année, saisons confondues, et la 53 est rangée en 52 comme partout
	 * dans l'agrégé. Une culture d'une espèce propre à la structure, sans référence, n'y entre pas :
	 * rien ne dit de quelle espèce du calendrier elle parle.
	 */
	protected static function getHarvestWeeks(array $climates): array {

		[$first, $last] = self::getSeasons();

		$weeks = [];
		$lastId = 0;

		do {

			$cCultivation = \series\Cultivation::model()
				->select([
					'id', 'structure', 'harvestWeeksExpected',
					'plant' => ['reference'],
				])
				->whereId('>', $lastId)
				->whereSeason('BETWEEN', new \Sql($first.' AND '.$last))
				->whereHarvestWeeksExpected('!=', NULL)
				->sort(['id' => SORT_ASC])
				->getCollection(0, self::CHUNK);

			foreach($cCultivation as $eCultivation) {

				$lastId = $eCultivation['id'];

				$structureId = $eCultivation['structure']['id'];
				$ePlantReference = $eCultivation['plant']['reference'];

				if(array_key_exists($structureId, $climates) === FALSE or $ePlantReference->empty()) {
					continue;
				}

				foreach($eCultivation['harvestWeeksExpected'] as $week) {
					$weeks[$structureId][$ePlantReference['id']][self::getWeek($week)] = TRUE;
				}

			}

		} while($cCultivation->count() === self::CHUNK);

		return $weeks;

	}

	/**
	 * Le numéro d'une semaine écrite `2026-W14`, la 53 rangée en 52.
	 */
	protected static function getWeek(string $week): int {
		return min((int)substr($week, 6), self::WEEKS);
	}

	/**
	 * Les comptes de structures, par climat et par espèce : `weeks` celles qui récoltent chaque
	 * semaine, `structures` celles qui cultivent l'espèce. La clé `NULL` du premier niveau, écrite
	 * `''`, porte tous les climats, structures sans climat comprises.
	 */
	protected static function getCounts(array $weeks, array $climates): array {

		$counts = [];

		foreach($weeks as $structureId => $weeksByPlant) {

			$keys = [''];

			if($climates[$structureId] !== NULL) {
				$keys[] = $climates[$structureId];
			}

			foreach($weeksByPlant as $plantId => $weeksHarvested) {

				foreach($keys as $key) {

					$counts[$key][$plantId] ??= [
						'weeks' => array_fill(1, self::WEEKS, 0),
						'structures' => 0,
					];

					$counts[$key][$plantId]['structures']++;

					foreach($weeksHarvested as $week => $harvested) {
						$counts[$key][$plantId]['weeks'][$week]++;
					}

				}

			}

		}

		return $counts;

	}

	/**
	 * Les espèces du référentiel qui peuvent entrer dans un panier : légume, aromate, fruit, petit
	 * fruit ou champignon, moins celles que le calendrier écarte.
	 */
	protected static function getPlants(): \Collection {

		// Le modèle lie ses closures à lui-même : `self::` y désignerait PlantModel.
		$fqnsIncluded = self::INCLUDED;

		return \plant\Plant::model()
			->select(['id', 'fqn', 'storage'])
			->whereStructure(NULL)
			->whereFqn('NOT IN', self::EXCLUDED)
			->or(
				fn() => $this->whereUsageVegetable(TRUE),
				fn() => $this->whereUsageAromatic(TRUE),
				fn() => $this->whereUsageFruit(TRUE),
				fn() => $this->whereUsageSmallFruit(TRUE),
				fn() => $this->whereUsageMushroom(TRUE),
				fn() => $this->whereFqn('IN', $fqnsIncluded),
			)
			->getCollection();

	}

	/**
	 * Les lignes du calendrier, une par climat et par espèce assez cultivée.
	 */
	protected static function getCalendar(array $counts, \Collection $cPlant): \Collection {

		$cCalendar = new \Collection();

		foreach($cPlant as $ePlant) {

			$countAll = $counts[''][$ePlant['id']] ?? NULL;

			if($countAll === NULL or $countAll['structures'] < self::STRUCTURES_MIN) {
				continue;
			}

			foreach([Calendar::OCEANIC, Calendar::CONTINENTAL, Calendar::MEDITERRANEAN, Calendar::MOUNTAIN] as $climate) {

				$count = $counts[$climate][$ePlant['id']] ?? NULL;
				$fallback = ($count === NULL or $count['structures'] < self::STRUCTURES_MIN);

				if($fallback) {
					$count = $countAll;
				}

				$windows = self::getWindows($count['weeks'], $count['structures'], $ePlant['storage']);

				if($windows['weeks'] === []) {
					continue;
				}

				$cCalendar[] = new Calendar([
					'climate' => $climate,
					'plant' => $ePlant,
					'harvestWeeks' => $windows['harvestWeeks'],
					'weeks' => $windows['weeks'],
					'structures' => $count['structures'],
					'fallback' => $fallback,
					'online' => ($count['structures'] >= self::CONTRIBUTORS),
				]);

			}

		}

		return $cCalendar;

	}

	/**
	 * Du profil d'une espèce à sa saison : les semaines de récolte retenues (`harvestWeeks`) et les
	 * semaines livrables, conservation comprise (`weeks`).
	 *
	 * `$counts` porte, pour chaque semaine de 1 à 52, le nombre de structures qui récoltent ;
	 * `$structures` celles qui cultivent l'espèce ; `$storage` la conservation en semaines.
	 *
	 * L'année est un cercle : une saison qui passe le nouvel an est une seule fenêtre, et un trou
	 * entre la semaine 52 et la semaine 1 se bouche comme un autre.
	 */
	public static function getWindows(array $counts, int $structures, int $storage): array {

		$shares = [];

		foreach($counts as $week => $count) {
			$shares[$week - 1] = $count / $structures;
		}

		$threshold = max(self::SHARE_ABSOLUTE, self::SHARE_RELATIVE * max($shares));

		$harvest = self::fill(array_map(fn(float $share) => $share >= $threshold, $shares));

		$windows = [];

		foreach(self::getRuns($harvest) as [$start, $length]) {

			if($length < self::WINDOW_MIN) {
				$harvest = self::set($harvest, $start, $length, FALSE);
			} else {
				$windows[] = [$start, $length];
			}

		}

		if(count($windows) > self::WINDOWS_MAX) {

			// Les plus fournies d'abord : la somme des parts d'une fenêtre pèse sa longueur et sa
			// hauteur à la fois. `usort()` est stable, deux fenêtres à égalité gardent l'ordre de
			// l'année.
			usort($windows, fn(array $a, array $b) => self::getWeight($shares, $b) <=> self::getWeight($shares, $a));

			foreach(array_slice($windows, self::WINDOWS_MAX) as [$start, $length]) {
				$harvest = self::set($harvest, $start, $length, FALSE);
			}

		}

		$deliverable = $harvest;

		if($storage > 0) {

			$seasons = self::getRuns($harvest);

			// Avec deux saisons dans l'année, seule la dernière se conserve : la première se mange
			// fraiche, et la prolonger ferait livrer en été la récolte du printemps.
			if(count($seasons) > 1) {
				usort($seasons, fn(array $a, array $b) => $b[0] <=> $a[0]);
				$seasons = [$seasons[0]];
			}

			foreach($seasons as [$start, $length]) {
				$deliverable = self::set($deliverable, $start + 1, $length - 1 + $storage, TRUE);
			}

			$deliverable = self::fill($deliverable);

		}

		return [
			'harvestWeeks' => self::getNumbers(self::trim($harvest)),
			'weeks' => self::getNumbers(self::trim($deliverable)),
		];

	}

	/**
	 * Retire les morceaux de moins de PIECE_MIN semaines, l'année lue de la semaine 1 à la semaine
	 * 52 et non plus en cercle. Les trous ne se bouchent pas ici : `fill()` a déjà bouché sur le
	 * cercle tout trou de GAP semaines au plus, et retirer un morceau court n'en crée pas de plus
	 * petit, les trous qui l'entourent en faisant déjà plus de GAP chacun.
	 */
	private static function trim(array $flags): array {

		$week = 0;

		while($week < self::WEEKS) {

			if($flags[$week] === FALSE) {
				$week++;
				continue;
			}

			$length = 1;

			while($week + $length < self::WEEKS and $flags[$week + $length]) {
				$length++;
			}

			if($length < self::PIECE_MIN) {
				$flags = self::set($flags, $week, $length, FALSE);
			}

			$week += $length;

		}

		return $flags;

	}

	/**
	 * La somme des parts des semaines d'une fenêtre.
	 */
	private static function getWeight(array $shares, array $window): float {

		[$start, $length] = $window;

		$weight = 0.0;

		for($offset = 0; $offset < $length; $offset++) {
			$weight += $shares[($start + $offset) % self::WEEKS];
		}

		return $weight;

	}

	/**
	 * Les plages de semaines retenues sur le cercle de l'année : `[début de 0 à 51, longueur]`,
	 * dans l'ordre de leur début.
	 */
	private static function getRuns(array $flags): array {

		if(in_array(FALSE, $flags, TRUE) === FALSE) {
			return [[0, self::WEEKS]];
		}

		$runs = [];

		for($week = 0; $week < self::WEEKS; $week++) {

			if($flags[$week] === FALSE or $flags[($week + self::WEEKS - 1) % self::WEEKS]) {
				continue;
			}

			$length = 1;

			while($flags[($week + $length) % self::WEEKS]) {
				$length++;
			}

			$runs[] = [$week, $length];

		}

		return $runs;

	}

	/**
	 * Bouche les trous d'au plus GAP semaines.
	 */
	private static function fill(array $flags): array {

		foreach(self::getRuns(array_map(fn(bool $flag) => $flag === FALSE, $flags)) as [$start, $length]) {

			if($length <= self::GAP) {
				$flags = self::set($flags, $start, $length, TRUE);
			}

		}

		return $flags;

	}

	private static function set(array $flags, int $start, int $length, bool $value): array {

		for($offset = 0; $offset < $length; $offset++) {
			$flags[($start + $offset) % self::WEEKS] = $value;
		}

		return $flags;

	}

	/**
	 * Les numéros de semaine, de 1 à 52, des semaines retenues.
	 */
	private static function getNumbers(array $flags): array {

		$weeks = [];

		foreach($flags as $week => $flag) {

			if($flag) {
				$weeks[] = $week + 1;
			}

		}

		return $weeks;

	}

}
?>
