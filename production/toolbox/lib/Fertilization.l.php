<?php
namespace toolbox;

/**
 * Le calculateur des besoins en fertilisation (chantier production-finir, lot T2, contrat validé par
 * le 03/10/2026) : une culture à un rendement de référence, une planche ou une surface libre,
 * et les matières qu'on y apporte. Il rend la quantité de chaque matière et le bilan par élément.
 * Rien ne s'enregistre.
 *
 * Les éléments sont l'azote, le phosphore et le potassium, ces deux derniers en oxydes (P2O5, K2O),
 * l'unité de toutes les sources du référentiel.
 */
class FertilizationLib {

	const ELEMENTS = ['nitrogen', 'phosphate', 'potash'];

	const SERIES = ['use', 'bedWidth', 'alleyWidth', 'lengthTarget', 'areaTarget'];

	// La valeur d'un intrant dans le sélecteur des matières, suivie de son id.
	const TOOL = 'tool-';

	// Les trois niveaux de fiabilité d'un rendement de référence (`getConfidence()`), dans l'ordre des
	// barres allumées de la marque.
	const CONFIDENCE_HIGH = 3;
	const CONFIDENCE_MEDIUM = 2;
	const CONFIDENCE_LOW = 1;

	/**
	 * Même geste que `DensityLib::open()` : la première visite fait du calculateur un outil de la
	 * structure, et l'en-tête de cette visite porte déjà sa roue.
	 */
	public static function open(\structure\Structure $eStructure): void {

		if(in_array(Favorite::FERTILIZATION, $eStructure->conf(\structure\Feature::class)['hasToolbox'])) {
			return;
		}

		\structure\FeatureLib::addToolbox($eStructure, Favorite::FERTILIZATION);

		\structure\FeatureLib::forget($eStructure);

	}

	/**
	 * La planche ou la surface : les champs du panneau de série, construits par le `build()` d'une
	 * série neuve comme dans le calculateur de densités, pour accepter et refuser ce qu'une série
	 * accepterait. Un champ vidé est une saisie en cours et ne se construit pas (`DensityLib::build()`).
	 */
	public static function buildSeries(array $input): \series\Series {

		$eSeries = new \series\Series(array_fill_keys(self::SERIES, NULL));
		$eSeries->build(array_values(array_filter(self::SERIES, fn(string $property) => ($input[$property] ?? '') !== '')), $input);

		return $eSeries;

	}

	/**
	 * La surface fertilisée en m², passe-pied compris sur une planche (« il faut inclure les passepied
	 * dans le calcul de la ferti », le 03/10), comme la surface d'une série
	 * (`series\Series.area`). NULL tant que la saisie ne la donne pas.
	 */
	public static function getArea(\series\Series $eSeries): ?float {

		return match($eSeries['use']) {
			\series\Series::BED => ($eSeries['bedWidth'] === NULL or $eSeries['lengthTarget'] === NULL) ?
				NULL :
				($eSeries['bedWidth'] + ($eSeries['alleyWidth'] ?? 0)) / 100 * $eSeries['lengthTarget'],
			\series\Series::BLOCK => $eSeries['areaTarget'],
			NULL => NULL,
		};

	}

	/**
	 * La composition saisie à la main (« Saisir la composition ») à la place `index` de la liste des
	 * matières : une matière qui n'existe que le temps du calcul, dont les teneurs arrivent en % et se
	 * rangent en kg/t comme celles du référentiel. Le calcul lisant la teneur totale, la saisie ne
	 * demande pas de part disponible (03/10). Les valeurs passent par le `build()` de la matière, qui borne chacune
	 * comme le schéma, et `buildIndex()` nomme ses erreurs d'après le champ (`nitrogen[0]`).
	 */
	public static function buildComposition(array $input, int $index): FertilizationMaterial {

		$values = [];

		foreach(self::ELEMENTS as $property) {

			$value = $input[$property][$index] ?? '';

			if($value === '') {
				continue;
			}

			// Les % de la saisie deviennent les kg/t du référentiel ; une valeur qui n'est pas un nombre
			// passe telle quelle, pour que le schéma la refuse sur son champ.
			$values[$property] = [$index => is_numeric($value) ? (float)$value * 10 : $value];

		}

		$eFertilizationMaterial = new FertilizationMaterial([
			'id' => NULL,
			'name' => NULL,
			'availablePhosphate' => NULL,
			'nitrogen' => NULL,
			'phosphate' => NULL,
			'potash' => NULL,
			'availableNitrogen' => NULL,
		]);

		$eFertilizationMaterial->buildIndex(array_keys($values), $values, $index);

		return $eFertilizationMaterial;

	}

	/**
	 * La saisie du calculateur, telle que le formulaire la poste : la planche, la culture à son
	 * rendement de référence, et les matières dans l'ordre de l'écran, chacune à son index
	 * (`material[<index>]`, la principale d'abord). Un rendement qui n'est pas celui de la culture,
	 * comme après un changement d'espèce, revient au premier de la culture. Une culture saisie à la main
	 * (`FertilizationCrop::MANUAL`, onglet « Saisie manuelle ») n'a pas d'espèce, et son rendement ne
	 * porte que le mobilisé que l'on a saisi (`buildMobilized()`). Une matière vaut l'id d'une
	 * matière du référentiel, `composition` pour une saisie à la main, ou rien pour un complément ajouté
	 * qu'on n'a pas encore choisi.
	 */
	public static function build(array $input, \Collection $cFertilizationCrop, \Collection $cFertilizationMaterial): array {

		$eSeries = self::buildSeries($input);

		if(($input['crop'] ?? '') === FertilizationCrop::MANUAL) {
			$eFertilizationCrop = new FertilizationCrop();
			$eFertilizationYield = self::buildMobilized($input);
		} else {
			$eFertilizationCrop = self::getCrop($cFertilizationCrop, (int)($input['crop'] ?? 0));
			$eFertilizationYield = self::getYield($eFertilizationCrop, (int)($input['yield'] ?? 0));
		}

		$materials = [];

		foreach((array)($input['material'] ?? []) as $index => $value) {
			$materials[(int)$index] = self::getMaterial($input, (int)$index, (string)$value, $cFertilizationMaterial);
		}

		return [$eSeries, $eFertilizationCrop, $eFertilizationYield, $materials];

	}

	/**
	 * Le mobilisé saisi à la main, en kg/ha, dans un rendement qui n'existe que le temps du calcul,
	 * comme la composition d'une matière (`buildComposition()`). Les valeurs passent par le `build()` du
	 * rendement, qui les borne comme le schéma, et `buildIndex()` nomme leurs erreurs d'après le champ
	 * (`nitrogen[manual]`). Un champ vide reste NULL : l'élément est alors inconnu, et non nul.
	 */
	public static function buildMobilized(array $input): FertilizationYield {

		$values = [];

		foreach(self::ELEMENTS as $property) {

			$value = $input[$property][FertilizationCrop::MANUAL] ?? '';

			if($value === '') {
				continue;
			}

			$values[$property] = [FertilizationCrop::MANUAL => $value];

		}

		$eFertilizationYield = new FertilizationYield([
			'id' => NULL,
			'name' => NULL,
			'yield' => NULL,
			'nitrogen' => NULL,
			'phosphate' => NULL,
			'potash' => NULL,
		]);

		$eFertilizationYield->buildIndex(array_keys($values), $values, FertilizationCrop::MANUAL);

		return $eFertilizationYield;

	}

	/**
	 * La culture choisie, si elle est du référentiel. Vide sinon.
	 */
	public static function getCrop(\Collection $cFertilizationCrop, int $cropId): FertilizationCrop {

		return $cFertilizationCrop->offsetExists($cropId) ? $cFertilizationCrop[$cropId] : new FertilizationCrop();

	}

	/**
	 * Le rendement choisi, s'il est de la culture, son premier sinon. Vide sans culture.
	 */
	public static function getYield(FertilizationCrop $eFertilizationCrop, int $yieldId): FertilizationYield {

		if($eFertilizationCrop->empty()) {
			return new FertilizationYield();
		}

		$cFertilizationYield = $eFertilizationCrop['cYield'];

		return $cFertilizationYield->findById($yieldId, default: fn() => $cFertilizationYield->first());

	}

	/**
	 * Les intrants de la structure (`work\Tool`, routine `fertilizer`) qui portent au moins une teneur
	 * en N, P ou K : un intrant qui ne dit que sa magnésie n'apporte rien au calcul. Ses teneurs sont
	 * en %, et P et K s'y lisent en P₂O₅ et K₂O comme sur un sac d'engrais (décidé par M-Prod sous
	 * mandat du 03/10).
	 */
	public static function getTools(\structure\Structure $eStructure): \Collection {

		return \work\ToolLib::getByStructure($eStructure, routineName: 'fertilizer')
			->find(fn(\work\Tool $eTool) => array_filter(self::getToolValues($eTool), fn(?float $value) => $value !== NULL) !== []);

	}

	/**
	 * Les teneurs d'un intrant rangées sous les propriétés de la matière. `routineValue` est nullable
	 * et un intrant créé sans composition le porte vide (`ToolLib::getNewTool()`), d'où la lecture
	 * clé par clé.
	 */
	protected static function getToolValues(\work\Tool $eTool): array {

		$values = $eTool['routineValue'] ?? [];

		return [
			'nitrogen' => $values['n'] ?? NULL,
			'phosphate' => $values['p'] ?? NULL,
			'potash' => $values['k'] ?? NULL,
		];

	}

	/**
	 * Un intrant choisi pré-remplit la saisie manuelle de ses teneurs (piste A, le 03/10). La
	 * matière passe donc en composition, et ses valeurs par le même `build()` que celles que l'on saisit.
	 * Seul son nom reste, pour que l'écran dise d'où vient la composition ; il ne se poste pas et
	 * disparait au premier autre choix du sélecteur.
	 */
	public static function getMaterialFromTool(int $index, \work\Tool $eTool): array {

		$input = array_map(fn(?float $value) => [$index => $value === NULL ? '' : (string)$value], self::getToolValues($eTool));

		$eFertilizationMaterial = self::buildComposition($input, $index);
		$eFertilizationMaterial['name'] = $eTool['name'];

		return [
			'value' => FertilizationMaterial::COMPOSITION,
			'eFertilizationMaterial' => $eFertilizationMaterial,
		];

	}

	/**
	 * Une matière de la saisie : `value` est la valeur de son sélecteur, rendue telle quelle à l'écran,
	 * et `eFertilizationMaterial` la matière à calculer, vide tant qu'elle n'est pas choisie.
	 * Un intrant se poste `tool-<id>` dans le même sélecteur ; il n'est lu que parmi les intrants de la
	 * structure, `cTool`, que seul le choix d'une matière fournit. Le calcul ne le reçoit jamais : la
	 * matière y est déjà devenue une composition.
	 */
	public static function getMaterial(array $input, int $index, string $value, \Collection $cFertilizationMaterial, \Collection $cTool = new \Collection()): array {

		$toolId = str_starts_with($value, self::TOOL) ? (int)substr($value, strlen(self::TOOL)) : NULL;

		if($toolId !== NULL and $cTool->offsetExists($toolId)) {
			return self::getMaterialFromTool($index, $cTool[$toolId]);
		}

		if($value === FertilizationMaterial::COMPOSITION) {
			$eFertilizationMaterial = self::buildComposition($input, $index);
		} else if($cFertilizationMaterial->offsetExists((int)$value)) {
			$eFertilizationMaterial = $cFertilizationMaterial[(int)$value];
		} else {
			$value = '';
			$eFertilizationMaterial = new FertilizationMaterial();
		}

		return [
			'value' => $value,
			'eFertilizationMaterial' => $eFertilizationMaterial,
		];

	}

	/**
	 * La saisie de la première visite : la planche par défaut de la structure, comme le calculateur de
	 * densités, aucune culture, et la saisie manuelle en principale, ses champs vides (« saisie manuelle
	 * sélectionné par défaut », le 03/10). Les compléments ajoutés restent à choisir.
	 */
	public static function getNew(\structure\Structure $eStructure): array {

		[$eSeries] = DensityLib::getNew($eStructure);

		// Une fertilisation sur surface libre se pense à l'hectare (04/10), là où le plan du
		// calculateur de densités garde ses 100 m² pour rester lisible.
		$eSeries['areaTarget'] = 10000;

		return [
			$eSeries,
			new FertilizationCrop(),
			new FertilizationYield(),
			[0 => ['value' => FertilizationMaterial::COMPOSITION, 'eFertilizationMaterial' => self::buildComposition([], 0)]],
		];

	}

	/**
	 * L'exemple de la notice (`FertilizationUi::getNoticePanel()`) : la carotte de garde et le compost
	 * de fumier de bovins du référentiel, calculés sur la planche par défaut de la structure, comme la
	 * première visite du calculateur. Ce sont les deux mesures de référence de `test/Fertilization.t.php`.
	 * Un exemple que le référentiel ne porte plus, ou une planche sans surface, rend ses éléments vides
	 * et son résultat NULL : l'étape garde alors sa phrase seule.
	 */
	public static function getNotice(\structure\Structure $eStructure, \Collection $cFertilizationCrop, \Collection $cFertilizationMaterial): array {

		$eFertilizationCrop = $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] === 'carotte')->first() ?? new FertilizationCrop();

		$eFertilizationYield = $eFertilizationCrop->empty() ?
			new FertilizationYield() :
			($eFertilizationCrop['cYield']->find(fn(FertilizationYield $e) => $e['name'] === 'Garde')->first() ?? new FertilizationYield());

		$eFertilizationMaterial = $cFertilizationMaterial->find(fn(FertilizationMaterial $e) => $e['name'] === 'Compost de fumier de bovins')->first() ?? new FertilizationMaterial();

		$materials = $eFertilizationMaterial->empty() ? [] : [0 => ['value' => (string)$eFertilizationMaterial['id'], 'eFertilizationMaterial' => $eFertilizationMaterial]];

		[$eSeries] = DensityLib::getNew($eStructure);

		return [
			'eFertilizationCrop' => $eFertilizationCrop,
			'eFertilizationYield' => $eFertilizationYield,
			'eFertilizationMaterial' => $eFertilizationMaterial,
			'materials' => $materials,
			'result' => self::getResult($eSeries, $eFertilizationCrop, $eFertilizationYield, $materials),
		];

	}

	/**
	 * Les documents du référentiel pour la notice (« ajoute la liste des sources utilisées dans cette
	 * page », le 03/10), lus en base pour suivre le référentiel : chacun avec ce qu'il donne,
	 * parmi `mobilized`, `exported` et `materials`. Ceux des cultures d'abord, ceux des seules matières
	 * ensuite, chaque groupe dans l'ordre alphabétique de `$cFertilizationSource`.
	 *
	 * @return array<int, array{eFertilizationSource: FertilizationSource, uses: string[]}>
	 */
	public static function getSources(\Collection $cFertilizationSource, \Collection $cFertilizationCrop, \Collection $cFertilizationMaterial): array {

		$uses = [];

		foreach($cFertilizationCrop as $eFertilizationCrop) {

			foreach($eFertilizationCrop['cYield'] as $eFertilizationYield) {
				$uses[$eFertilizationYield['source']['id']]['mobilized'] = TRUE;
			}

			foreach($eFertilizationCrop['exportedSources'] as ['eFertilizationSource' => $eFertilizationSource]) {
				$uses[$eFertilizationSource['id']]['exported'] = TRUE;
			}

		}

		foreach($cFertilizationMaterial as $eFertilizationMaterial) {
			$uses[$eFertilizationMaterial['source']['id']]['materials'] = TRUE;
		}

		$sources = [];

		foreach($cFertilizationSource as $eFertilizationSource) {

			$found = array_keys(array_intersect_key(['mobilized' => TRUE, 'exported' => TRUE, 'materials' => TRUE], $uses[$eFertilizationSource['id']] ?? []));

			$sources[] = [
				'eFertilizationSource' => $eFertilizationSource,
				'uses' => $found,
				'crops' => array_intersect($found, ['mobilized', 'exported']) !== [],
			];

		}

		usort($sources, fn(array $a, array $b) => $b['crops'] <=> $a['crops']);

		return array_map(fn(array $source) => ['eFertilizationSource' => $source['eFertilizationSource'], 'uses' => $source['uses']], $sources);

	}

	/**
	 * Le résultat de la saisie, ou NULL tant qu'il manque la surface, la culture ou une matière.
	 */
	public static function getResult(\series\Series $eSeries, FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield, array $materials): ?array {

		$area = self::getArea($eSeries);

		$cFertilizationMaterial = array_filter(array_map(fn(array $material) => $material['eFertilizationMaterial'], $materials), fn(FertilizationMaterial $e) => $e->notEmpty());

		if($area === NULL or $area <= 0.0 or self::getMobilized($eFertilizationYield) === [] or $cFertilizationMaterial === []) {
			return NULL;
		}

		return self::calculate($area, $eFertilizationYield, $eFertilizationCrop, $cFertilizationMaterial);

	}

	/**
	 * Les éléments que le rendement dit mobilisés, en kg/ha. Vide sans rendement, ou quand la saisie
	 * manuelle n'en donne encore aucun.
	 */
	protected static function getMobilized(FertilizationYield $eFertilizationYield): array {

		if($eFertilizationYield->empty()) {
			return [];
		}

		$mobilized = [];

		foreach(self::ELEMENTS as $element) {
			if($eFertilizationYield[$element] !== NULL) {
				$mobilized[$element] = $eFertilizationYield[$element];
			}
		}

		return $mobilized;

	}

	/**
	 * Ce que la récolte exporte par hectare, par élément : l'exporté par tonne de l'espèce au rendement
	 * de référence. NULL quand l'un des deux manque, et pour les trois sans espèce, une culture saisie à
	 * la main ne disant pas ce qu'elle exporte.
	 */
	public static function getExported(FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): array {

		$exported = [];

		foreach(self::ELEMENTS as $element) {

			if($eFertilizationCrop->empty()) {
				$exported[$element] = NULL;
				continue;
			}

			$exportedPerTon = $eFertilizationCrop['exported'.ucfirst($element)];
			$exported[$element] = ($exportedPerTon === NULL or $eFertilizationYield['yield'] === NULL) ? NULL : $exportedPerTon * $eFertilizationYield['yield'];

		}

		return $exported;

	}

	/**
	 * La fiabilité d'un rendement de référence, un niveau pour le mobilisé et un pour l'exporté
	 * (contrat validé le 03/10, planche production-finir-fiabilite, Q1 A et Q2 B, revu par
	 * lui le 05/10) :
	 *
	 * - assez fiable demande plusieurs sources cohérentes entre elles pour une même valeur, ce que le
	 *   référentiel ne porte pas : chaque élément n'a qu'une source, donc aucune valeur n'atteint ce
	 *   niveau ;
	 * - le mobilisé est moyennement fiable, un rendement n'ayant qu'un document pour ses trois éléments ;
	 * - l'exporté qui dépasse le mobilisé pour au moins un élément est peu fiable : la plante ne peut pas
	 *   exporter plus qu'elle ne mobilise, et c'est l'exporté, agrégé de sources différentes, qui porte
	 *   le doute (« Si mobilisé a une source unique, on considère toujours qu'il est fiable », le
	 *   03/10, qui revient sur les deux groupes peu fiables de la planche) ;
	 * - sinon l'exporté est moyennement fiable, qu'il vienne d'une seule source ou de sources différentes
	 *   qui ne se recouvrent pas ;
	 * - un exporté qui ne se calcule pour aucun élément, faute de source ou de rendement, n'a pas de
	 *   niveau (NULL), et ne peut rien dépasser.
	 *
	 * `exceeded` liste les éléments en dépassement, que l'écran nomme.
	 *
	 * @return array{mobilized: int, exported: ?int, exceeded: string[]}
	 */
	public static function getConfidence(FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): array {

		$exported = self::getExported($eFertilizationCrop, $eFertilizationYield);

		$exceeded = [];

		foreach(self::ELEMENTS as $element) {
			if($exported[$element] !== NULL and $eFertilizationYield[$element] !== NULL and $exported[$element] > $eFertilizationYield[$element]) {
				$exceeded[] = $element;
			}
		}

		$known = array_filter($exported, fn(?float $value) => $value !== NULL);

		if($known === []) {
			$exportedConfidence = NULL;
		} else if($exceeded !== []) {
			$exportedConfidence = self::CONFIDENCE_LOW;
		} else {
			$exportedConfidence = self::CONFIDENCE_MEDIUM;
		}

		return [
			'mobilized' => self::CONFIDENCE_MEDIUM,
			'exported' => $exportedConfidence,
			'exceeded' => $exceeded,
		];

	}

	/**
	 * Ce qu'une tonne de la matière apporte, en kg par élément : sa teneur totale. La part disponible la
	 * première année n'entre pas dans le calcul (« le calcul doit tenir compte du total, pas du disponible
	 * la 1ere année », le 03/10) ; elle reste une donnée du référentiel, affichée à côté. Un
	 * élément que la matière ne publie pas n'apporte rien.
	 */
	public static function getContents(FertilizationMaterial $eFertilizationMaterial): array {

		return [
			'nitrogen' => $eFertilizationMaterial['nitrogen'] ?? 0,
			'phosphate' => $eFertilizationMaterial['phosphate'] ?? 0,
			'potash' => $eFertilizationMaterial['potash'] ?? 0,
		];

	}

	/**
	 * Le calcul, matière par matière dans l'ordre de la saisie, la principale d'abord (contrat,
	 * arbitrages Q4 A et Q5 B du 03/10) : chaque matière se dose pour combler le premier
	 * élément, dans l'ordre N, P2O5, K2O, qui manque encore au regard du mobilisé et qu'elle apporte.
	 * Pour la principale, qui porte presque toujours de l'azote, c'est la dose qui couvre l'azote. Une
	 * matière qui ne comble rien se dose à 0 et reste dans le bilan. Les quantités gardent l'index de
	 * leur matière.
	 *
	 * @param FertilizationMaterial[] $materials
	 */
	public static function calculate(float $area, FertilizationYield $eFertilizationYield, FertilizationCrop $eFertilizationCrop, array $materials): array {

		$mobilized = [];
		$exported = [];

		foreach(self::getExported($eFertilizationCrop, $eFertilizationYield) as $element => $exportedPerHectare) {
			$mobilized[$element] = ($eFertilizationYield[$element] === NULL) ? NULL : $eFertilizationYield[$element] * $area / 10000;
			$exported[$element] = ($exportedPerHectare === NULL) ? NULL : $exportedPerHectare * $area / 10000;
		}

		$brought = array_fill_keys(self::ELEMENTS, 0.0);
		$quantities = [];

		foreach($materials as $index => $eFertilizationMaterial) {

			$contents = self::getContents($eFertilizationMaterial);
			$quantity = 0.0;

			foreach(self::ELEMENTS as $element) {

				$missing = ($mobilized[$element] ?? 0) - $brought[$element];

				if($missing > 0 and $contents[$element] > 0) {
					$quantity = $missing / $contents[$element] * 1000;
					break;
				}

			}

			foreach(self::ELEMENTS as $element) {
				$brought[$element] += $quantity * $contents[$element] / 1000;
			}

			$quantities[$index] = $quantity;

		}

		$balance = [];

		foreach(self::ELEMENTS as $element) {

			$balance[$element] = [
				'mobilized' => $mobilized[$element],
				'brought' => $brought[$element],
				'gap' => ($mobilized[$element] === NULL or $mobilized[$element] <= 0.0) ? NULL : (int)round(($brought[$element] - $mobilized[$element]) / $mobilized[$element] * 100),
				'exported' => $exported[$element],
			];

		}

		return [
			'area' => $area,
			'quantities' => $quantities,
			'balance' => $balance,
		];

	}

}
?>
