<?php
namespace entry;

/**
 * « récolte courgette 12 kg » : une quantité récoltée aujourd'hui sur une culture, ou le jour que la
 * phrase dit (« courgette 12 kg hier »), avec le temps qu'on y a passé (« courgette 12 kg 30 min »), ou le
 * temps seul (« courgette 30 min »), qui laisse l'intervention dans son état.
 *
 * L'intervention se choisit et s'écrit comme dans Consigner (`\planning\HarvestWeekLib::writeEntry()`), pour
 * qu'une même récolte saisie par l'une ou l'autre voie aille sur la même intervention et la laisse dans le
 * même état : `HarvestWeekLib::getTaskForEntry()` la choisit, `TaskLib::prepareHarvestForWeek()` puis
 * `writePreparedHarvestForWeek()` l'écrivent, et terminent une intervention à faire (17/10, Q1).
 * Le temps est celui de l'utilisateur connecté, construit et borné à la journée par les méthodes de
 * Consigner (`HarvestWeekLib::buildTime()`, `checkTimeDay()`), et ajouté sur la même intervention.
 * Le stock reste celui du produit que l'écran de récolte présélectionne.
 *
 * Annuler défait tout ce que la pastille annonce : la récolte, son temps, l'intervention que la saisie a
 * créée, et la remise à faire de celle qu'elle a terminée (Q4). Ce que l'annulation doit rendre est gardé dans un ticket
 * (`setCancel()`), le temps de `EntrySetting::CANCEL_DELAY`. Un temps seul n'a pas de récolte que ce ticket
 * désigne : il prend celui d'un temps (`TimeIntentionLib::setCancel()`), et s'annule comme lui.
 */
class HarvestIntentionLib extends IntentionLib {

	/**
	 * Le mot-clé qui dit, après l'espèce, la part sans variété ou sans calibre, découpé comme la phrase : il se
	 * tape, et la puce le pose sous son libellé, que seul l'écran rend
	 * (`HarvestIntentionUi::getUnspecified()`). Le genre départage les deux familles.
	 */
	const array UNSPECIFIED = [
		'variety' => 'non specifiee',
		'harvestSize' => 'non specifie',
	];

	public static function getWords(): array {
		return ['recolte', 'recoltes', 'recolter', 'recoltee', 'recoltees'];
	}

	// La lecture montre espèces, séries, emplacements et récolté du jour : le droit est celui du planning,
	// pas `canRead()`, qui admet aussi un compte école et l'observateur d'un groupe.
	public static function canWrite(\structure\Structure $eStructure): bool {
		return $eStructure->canPlanning();
	}

	public static function writesPlanning(): bool {
		return TRUE;
	}

	// La récolte de la semaine, et les récoltes que le bloc des ventes à venir met en face de ce qui est vendu.
	public static function getDashboardBlocks(): array {
		return ['planning-harvest', 'planning-sales'];
	}

	public static function getObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead = new \Collection()): \Collection {
		return CultivationResolveLib::getPlants($eStructure, $reading['date'], $cElementRead, $reading['complements']['season']);
	}

	/**
	 * Une récolte ne se saisit pas à l'avance, ni au-delà de `EntrySetting::PAST_DAYS` : plus
	 * loin, elle passe par l'écran de récolte de son intervention.
	 */
	public static function getDateRefusal(string $date): ?array {
		return self::getPastRefusal($date);
	}

	/**
	 * L'espèce dont le nom est la plus longue suite de mots de la phrase (`IntentionLib::readName()`).
	 */
	public static function readObject(\Collection $cPlant, array &$words): \plant\Plant {

		$key = self::readName($cPlant, $words);

		return ($key === NULL) ? new \plant\Plant() : $cPlant[$key];

	}

	/**
	 * La variété et le calibre que la phrase dit après l'espèce, tapés ou posés par une puce, dont le jeton se
	 * relit comme un mot : « tomate cœur de bœuf gros 12 kg ». Chaque famille se lit une fois, par le nom le
	 * plus long, ou par « non spécifiée » (`UNSPECIFIED`), qui dit la part sans variété ou sans calibre.
	 *
	 * La série n'est pas encore retenue : la variété se cherche parmi celles des parts de toutes les cultures de
	 * l'espèce que la lecture a chargées (`CultivationResolveLib::getPlants()`, propriété `cCultivation`), et
	 * `resolve()` la refuse si la série retenue ne la cultive pas. Le calibre, parmi ceux de l'espèce.
	 *
	 * `couple` porte :
	 * - `eVariety`, `eSize` : ce que la phrase dit, vides pour « non spécifiée » comme pour ce qu'elle tait ;
	 * - `said` : les familles qu'elle dit, `variety` et `harvestSize` ;
	 * - `named` : par famille dite, les mots qui la disent, liaisons comprises, qu'une puce remplace ;
	 * - `cVariety`, `cSize` : les variétés et les calibres où la phrase cherche, par id ;
	 * - `varieties` : les variétés de chaque culture, par id de culture ;
	 * - `cVarietyChoice`, `cSizeChoice` : les variétés de la série retenue et les calibres de l'espèce, posés par
	 *   `resolveCouple()` hors de la case d'une part, dont le couple est dit ;
	 * - `chips` : par famille, les puces que la liste propose (`cChoice`, `unspecified` pour « Non spécifiée », et
	 *   `selected`, le nom retenu quand la phrase dit la famille, `UNSPECIFIED[<famille>]` pour « Non spécifiée »,
	 *   NULL quand elle ne la dit pas) ;
	 * - `searching` : les mots en attente commencent un nom de puce (`isSearching()`).
	 */
	public static function readQualifiers(\structure\Structure $eStructure, array &$reading, array &$words, \Collection $cElement): void {

		$ePlant = $reading['object'];

		$cCultivation = $cElement->getProperty('cCultivation')->find(fn(\series\Cultivation $eCultivation) => $eCultivation['plant']['id'] === $ePlant['id']);

		$cVariety = new \Collection();
		$varieties = array_fill_keys($cCultivation->getIds(), []);

		if($cCultivation->notEmpty()) {

			foreach(\series\SliceLib::getByCultivations($cCultivation) as $eSlice) {
				$cVariety[$eSlice['variety']['id']] = $eSlice['variety'];
				$varieties[$eSlice['cultivation']['id']][] = $eSlice['variety']['id'];
			}

			$cVariety->sort(['name'], natural: TRUE);

		}

		$cSize = new \Collection();

		foreach(\plant\SizeLib::getByStructureAndPlant($eStructure, $ePlant) as $eSize) {
			$cSize[$eSize['id']] = $eSize;
		}

		$couple = [
			'eVariety' => new \plant\Variety(),
			'eSize' => new \plant\Size(),
			'said' => [],
			'named' => [],
			'cVariety' => $cVariety,
			'cSize' => $cSize,
			'varieties' => $varieties,
			'cVarietyChoice' => new \Collection(),
			'cSizeChoice' => new \Collection(),
			'chips' => [],
			'searching' => FALSE,
		];

		// Les liaisons se retirent pour lire, comme les noms (« cœur bœuf ») ; les mots lus partent par leur
		// position, celles des liaisons qui les séparaient restant aux compléments, qui les ignorent.
		foreach(['variety' => 'eVariety', 'harvestSize' => 'eSize'] as $family => $key) {

			$cChoice = match($family) {
				'variety' => $cVariety,
				'harvestSize' => $cSize,
			};

			$wordsRead = EntryLib::withoutLinks($words);
			$found = self::readName($cChoice, $wordsRead, withoutLinks: TRUE);

			if($found === NULL) {

				$wordsRead = EntryLib::withoutLinks($words);
				$unspecified = self::readName(new \Collection([new \AnonymousElement(['name' => self::UNSPECIFIED[$family]])]), $wordsRead);

				if($unspecified === NULL) {
					continue;
				}

			} else {
				$couple[$key] = $cChoice[$found];
			}

			$couple['said'][] = $family;

			$wordsBefore = $words;
			$words = array_intersect_key($words, $wordsRead + array_filter($words, fn(string $word) => in_array($word, EntrySetting::LINKS, TRUE)));

			// Les mots du nom, du premier au dernier lu, liaisons comprises : « cœur de bœuf » part entier. Un nom lu
			// a retiré au moins un mot, les bornes existent toujours. Ces mots sont ceux de la phrase, jeton compris :
			// le champ ne les trouve dans sa frappe que lorsqu'ils y sont tapés (`EntryField.pick()`).
			$positions = array_keys(array_diff_key($wordsBefore, $words));
			$first = min($positions);
			$last = max($positions);

			$couple['named'][$family] = array_values(array_filter($wordsBefore, fn(int $position) => ($position >= $first and $position <= $last), ARRAY_FILTER_USE_KEY));

		}

		$reading['couple'] = $couple;

	}

	/**
	 * Un début de nom de variété ou de calibre au mot en frappe, « 12 kg cœ » : la phrase cherche encore une
	 * puce, le moteur ne refuse pas ses mots, et rien ne s'écrit avant qu'ils ne nomment la variété.
	 */
	public static function isSearching(array $reading): bool {
		return $reading['couple']['searching'];
	}

	/**
	 * Les puces de chaque famille que la série propose : toutes, ou, quand les mots en attente finissent par le mot
	 * en frappe et commencent des noms de familles que la phrase ne dit pas, celles qu'ils commencent, et la phrase
	 * cherche alors (`searching`). Une famille que la phrase dit reste en puces, sa valeur retenue (`selected`) :
	 * la choisir de nouveau la retire, comme on décoche une option.
	 *
	 * Une phrase sans aucun mot n'en propose pas : dans la case, où seule elle peut se lire en récolte, le champ vide
	 * demande une quantité, et la liste ne s'ouvre qu'à la première frappe, la ligne qui écrit en tête.
	 */
	private static function setChips(array &$reading): void {

		if($reading['blank']) {
			return;
		}

		$couple = &$reading['couple'];

		$choices = array_filter([
			'variety' => $couple['cVarietyChoice'],
			'harvestSize' => $couple['cSizeChoice'],
		], fn(\Collection $cChoice) => $cChoice->notEmpty());

		$selected = [
			'variety' => $couple['eVariety']->empty() ? self::UNSPECIFIED['variety'] : $couple['eVariety']['name'],
			'harvestSize' => $couple['eSize']->empty() ? self::UNSPECIFIED['harvestSize'] : $couple['eSize']['name'],
		];

		$searched = self::getSearched($reading, array_filter($choices, fn(string $family) => in_array($family, $couple['said'], TRUE) === FALSE, ARRAY_FILTER_USE_KEY));

		$couple['searching'] = ($searched !== []);

		foreach($choices as $family => $cChoice) {

			if($couple['searching'] === FALSE) {
				$couple['chips'][$family] = [
					'cChoice' => $cChoice,
					'unspecified' => TRUE,
					'selected' => in_array($family, $couple['said'], TRUE) ? $selected[$family] : NULL,
				];
			} else if(array_key_exists($family, $searched)) {
				$couple['chips'][$family] = $searched[$family] + ['selected' => NULL];
			}

		}

	}

	/**
	 * Ce que cherchent les mots en attente, quand ils finissent par le mot en frappe et commencent un nom parmi
	 * `$choices` : par famille, les choix trouvés et si « non spécifiée » en est. Vide quand ils ne cherchent rien.
	 */
	private static function getSearched(array $reading, array $choices): array {

		if($reading['pending'] === NULL or $reading['typing'] === NULL) {
			return [];
		}

		$search = EntryLib::split($reading['pending']);

		if(end($search) !== $reading['typing']) {
			return [];
		}

		$searched = [];

		foreach($choices as $family => $cChoice) {

			$cChoiceFound = $cChoice->find(fn(\Element $eChoice) => self::startsName($eChoice['name'], $search));
			$unspecified = self::startsName(self::UNSPECIFIED[$family], $search);

			if($cChoiceFound->notEmpty() or $unspecified) {
				$searched[$family] = ['cChoice' => $cChoiceFound, 'unspecified' => $unspecified];
			}

		}

		return $searched;

	}

	/**
	 * Le nom commence-t-il par les mots cherchés, le dernier en cours de frappe : « cœ », « cœur bœ ».
	 */
	private static function startsName(string $name, array $search): bool {

		$names = array_values(EntryLib::withoutLinks(EntryLib::split($name)));

		if(count($search) > count($names)) {
			return FALSE;
		}

		$last = array_key_last($search);

		foreach($search as $position => $word) {

			$match = ($position === $last) ? str_starts_with($names[$position], $word) : ($names[$position] === $word);

			if($match === FALSE) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * La culture, l'intervention que la récolte complète, l'unité, puis la confirmation.
	 *
	 * Une fois résolue, `complements.unit` porte l'unité qui sera écrite : celle que l'intervention tient
	 * déjà, sinon l'unité dite, sinon l'unité principale de la culture, comme `prepareHarvestForWeek()` la
	 * choisit. Une unité dite autre que celle de la semaine désigne une autre intervention (retour 319,
	 * 17/10, Q3).
	 *
	 * Les caisses restent des caisses dans `complements.unit`, que l'écran lit pour dire ce qui se compte, et
	 * la confirmation porte l'unité écrite, en kilos. Elles se règlent comme dans le panneau Consigner : sur
	 * l'intervention que la récolte compléterait sans unité dite, jamais sur une autre en kilos, et seulement
	 * si elle se compte en kilos (`HarvestEntryUi::getUnitChoices()`) ; au poids de caisse de l'espèce, sans
	 * lequel elles se refusent : la phrase n'a pas d'endroit où le régler, que le panneau demande dans un champ.
	 */
	public static function resolve(\structure\Structure $eStructure, array &$reading, array $answers): void {

		$ePlant = $reading['object'];

		$cell = $answers['cell'];

		// La série de la case n'est pas une réponse parmi d'autres : absente des candidates du jour, elle refuse
		// la phrase au lieu de laisser la résolution en désigner une autre.
		if($cell !== NULL and $ePlant['cCultivation']->offsetExists($cell['eCultivation']['id']) === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'cellSeries']);
			return;
		}

		$resolved = CultivationResolveLib::resolve($ePlant, ($cell === NULL) ? $answers['cultivation'] : $cell['eCultivation']['id'], $answers['all']);

		if($resolved['eCultivation']->empty()) {

			self::fillPlaces($resolved['cCultivation']);

			EntryLib::ask($reading, [
				'name' => 'cultivation',
				'cCultivation' => $resolved['cCultivation'],
				'more' => $resolved['more'],
			]);

			return;

		}

		$eCultivation = $resolved['eCultivation'];

		$reading['cultivation'] = $eCultivation;

		// Une intervention neuve se construit par `build()`, dont un refus vaut pour la lecture comme pour
		// l'écriture : la phrase ne peut pas s'écrire.
		$fw = new \FailWatch();

		$crate = ($reading['complements']['unit'] === UnitComplementLib::CRATE);

		$couple = self::resolveCouple($eStructure, $reading, $eCultivation, $cell);

		if($couple === NULL) {
			return;
		}

		[$eVariety, $eSize] = $couple;

		// L'unité se compare à celle de la part : une part a ses propres interventions, dans leur propre unité.
		$unitOther = \planning\HarvestWeekLib::getOtherUnit($eStructure, $eCultivation, $reading['date'], $crate ? NULL : $reading['complements']['unit'], $eVariety, $eSize);

		$eTask = \planning\HarvestWeekLib::getTaskForEntry($eStructure, $eCultivation, $reading['date'], $unitOther, $eVariety, $eSize);

		$fw->validate();

		$unit = $eTask['harvestUnit'] ?? $unitOther ?? $eCultivation['mainUnit'];

		$reading['complements']['unit'] = $crate ? UnitComplementLib::CRATE : $unit;

		// Refusé dès le mot, avant la quantité : le nombre tapé ensuite ne changerait rien au refus.
		if($crate) {

			if($unit !== \series\Task::KG) {
				EntryLib::refuse($reading, ['reason' => 'crateUnit', 'unit' => $unit]);
				return;
			}

			if($ePlant['crateWeight'] === NULL) {
				EntryLib::refuse($reading, ['reason' => 'crateWeight', 'object' => $ePlant]);
				return;
			}

		}

		$minutes = $reading['complements']['duration'];

		// Refusé dès la lecture, comme `Timesheet::time.untracked` le refuserait à l'écriture.
		if(is_int($minutes) and $eStructure->hasTime() === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'untracked']);
			return;
		}

		$quantity = $reading['complements']['quantity'];

		// Un début de nom de variété ou de calibre cherche encore sa puce : rien ne s'écrit avant qu'il la nomme.
		if(self::isSearching($reading)) {
			$reading['outcome'] = EntryLib::ASK;
			return;
		}

		if($quantity === NULL) {

			if(is_int($minutes)) {
				self::resolveTime($eStructure, $reading, $eCultivation, $eTask, $unit, $unitOther, $cell, $eVariety, $eSize);
			} else {
				// La quantité attendue se dit dans une unité, que les puces choisissent avant le nombre. Comme celles du
				// couple (`setChips()`), aucune sans un mot : la case vide n'ouvre sa liste qu'à la première frappe.
				$reading['units'] = $reading['blank'] ? [] : self::getUnitChoices($eStructure, $reading, $eCultivation, $unit, $unitOther, $eVariety, $eSize);
				$reading['outcome'] = EntryLib::ASK;
			}

			return;

		}

		if($quantity === 0.0) {
			EntryLib::refuse($reading, ['reason' => 'quantity']);
			return;
		}

		// Un retrait se dit en kilos, et seul. Il corrige un poids déjà écrit, alors que des caisses se convertissent
		// au poids d'une caisse de l'espèce, que chaque récolte en caisses peut réécrire (`HarvestWeekLib::writeCrate()`) :
		// retirer des caisses au poids du jour ne retrouverait pas les kilos qu'elles valaient à l'écriture. Le temps
		// passé s'ajoute, il ne se retire pas, et la ligne ne peut pas dire les deux à la fois.
		if($quantity < 0.0) {

			if($crate) {
				EntryLib::refuse($reading, ['reason' => 'crateRemove']);
				return;
			}

			if(is_int($minutes)) {
				EntryLib::refuse($reading, ['reason' => 'removeTime']);
				return;
			}

		}

		// Sur l'unité dite : des caisses se comptent, même si elles s'écrivent en kilos.
		if(
			in_array($reading['complements']['unit'], EntrySetting::DECIMAL_UNITS, TRUE) === FALSE and
			$quantity !== round($quantity)
		) {
			EntryLib::refuse($reading, ['reason' => 'whole', 'unit' => $reading['complements']['unit']]);
			return;
		}

		$crates = $crate ? $quantity : NULL;

		if($crate) {
			$quantity = \planning\HarvestWeekLib::getCrateQuantity($ePlant, $quantity);
		}

		// Le récolté où la saisie s'écrit : celui du couple, dans une case ou quand la phrase en dit un, sinon celui
		// du jour entier, comme `TaskLib::getHarvestedForWrite()` le relira.
		$part = ($cell !== NULL or $reading['couple']['said'] !== []);

		$today = self::getToday($eCultivation, $eTask, $unit, $reading['date'], $part);

		// Refusés dès la lecture, comme `prepareHarvestForWeek()` les refuserait à l'écriture :
		// - sur le jour entier, une part répartie par variété ou par calibre (`Task::harvestMore.ventilated`) ;
		// - un retrait au-delà du récolté (`Task::harvestMore.day`) ;
		// - un retrait sans intervention, qui n'a rien à rendre.
		// Le jour entier se compare à la récolte de la culture : deux interventions le même jour la partagent, et
		// `buildHarvests()` refuse aussi celle qui rendrait plus que sa part (`Task::harvestDates.negative`).
		if($quantity < 0.0) {

			if($part === FALSE and self::isVentilated($eStructure, $eCultivation, $reading['date'], $unit)) {
				EntryLib::refuse($reading, ['reason' => 'removeVentilated']);
				return;
			}

			if($eTask->exists() === FALSE or round($today + $quantity, 2) < 0.0) {
				EntryLib::refuse($reading, ['reason' => 'removeMore', 'today' => $eTask->exists() ? $today : 0.0, 'unit' => $unit]);
				return;
			}

		}

		self::fillPlaces(new \Collection([$eCultivation]));

		$reading['units'] = ($quantity > 0.0) ? self::getUnitChoices($eStructure, $reading, $eCultivation, $unit, $unitOther, $eVariety, $eSize) : [];

		$reading['confirmation'] = [
			'quantity' => $quantity,
			'crates' => $crates,
			'unit' => $unit,
			'minutes' => is_int($minutes) ? $minutes : NULL,
			'date' => $reading['date'],
			'today' => round($today + $quantity, 2),
			'part' => $part,
			'eTask' => $eTask,
			'eTaskDelayed' => CultivationResolveLib::getDelayed($eTask, toWeek($reading['date'])),
			'created' => ($eTask->exists() === FALSE),
			'eProduct' => self::getProduct($eStructure, $eCultivation, $eTask, $unit),
			'cell' => $cell,
			'eVariety' => $eVariety,
			'eSize' => $eSize,
			// Qui saisit, le membre connecté, à qui `prepare()` attribue le temps dit.
			'eUser' => \user\ConnectionLib::getOnline(),
		];

		$reading['outcome'] = EntryLib::WRITE;

	}

	/**
	 * Les unités que la quantité attendue, puis la ligne qui écrit, proposent en puces, dans l'ordre de
	 * l'énumération, les caisses derrière les kilos, celle qui s'écrira marquée (`selected`), avec le mot que sa
	 * puce pose dans la phrase (`word`) : chacune est une phrase que `resolve()` lit, comme si ce mot était tapé,
	 * et aucune ne s'offre seule. Une unité autre que celle de la semaine va sur l'intervention de la semaine dans
	 * cette unité, ou sur une neuve, comme dans le panneau Consigner (`HarvestEntryUi::getUnitChoices()`).
	 *
	 * Les caisses se règlent sur l'unité de la semaine, celle qu'écrit la récolte sans unité dite, et s'offrent
	 * aux conditions où `resolve()` les accepte : en kilos, au poids de caisse de l'espèce. Une quantité décimale
	 * ne s'offre qu'aux unités qui en acceptent (`EntrySetting::DECIMAL_UNITS`) : les autres la refuseraient. Avant
	 * le nombre, toutes s'offrent.
	 *
	 * Un retrait n'en propose pas : il corrige l'intervention où la récolte est écrite, et une autre unité en
	 * désigne une autre.
	 */
	private static function getUnitChoices(\structure\Structure $eStructure, array $reading, \series\Cultivation $eCultivation, string $unit, ?string $unitOther, \plant\Variety $eVariety, \plant\Size $eSize): array {

		$unitWeek = ($unitOther === NULL) ?
			$unit :
			\planning\HarvestWeekLib::getEntryUnit($eCultivation, \planning\HarvestWeekLib::getTaskWeek($eStructure, $eCultivation, toWeek($reading['date']), $eVariety, $eSize));

		$units = [];

		// Les caisses suivent les kilos qu'elles convertissent, comme dans le panneau Consigner.
		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unitChoice) {

			$units[] = $unitChoice;

			if($unitChoice === \series\Task::KG and $unitWeek === \series\Task::KG and $reading['object']['crateWeight'] !== NULL) {
				$units[] = UnitComplementLib::CRATE;
			}

		}

		$quantity = $reading['complements']['quantity'];

		if($quantity !== NULL and $quantity !== round($quantity)) {
			$units = array_intersect($units, EntrySetting::DECIMAL_UNITS);
		}

		if(count($units) < 2) {
			return [];
		}

		return array_map(fn(string $unitChoice) => [
			'unit' => $unitChoice,
			'word' => UnitComplementLib::getWord($unitChoice),
			'selected' => ($unitChoice === $reading['complements']['unit']),
		], array_values($units));

	}

	/**
	 * Le couple où la récolte s'écrit, `NULL` quand la phrase est refusée. La case d'une part fixe le sien, même vide, que
	 * la phrase peut redire mais pas contredire ; la case de la série et le menu prennent celui que la phrase dit,
	 * aucun quand elle n'en dit pas. Une variété se refuse sur une série qui ne la cultive pas ; le calibre lu est
	 * toujours un de ceux de l'espèce. Le couple dit passe ensuite par la sonde du planning
	 * (`HarvestWeekLib::getCoupleProbe()`), comme celui d'une case (`EntryLib::getCell()`) : ses refus restent
	 * dans le `FailWatch` de `resolve()`.
	 *
	 * Pose aussi ce que l'écran propose en puces (`cVarietyChoice`, `cSizeChoice`) : les variétés de la série et
	 * les calibres de l'espèce, aucun dans la case d'une part, dont le couple est dit.
	 */
	private static function resolveCouple(\structure\Structure $eStructure, array &$reading, \series\Cultivation $eCultivation, ?array $cell): ?array {

		$couple = $reading['couple'];

		$said = [
			'variety' => in_array('variety', $couple['said'], TRUE),
			'harvestSize' => in_array('harvestSize', $couple['said'], TRUE),
		];

		if($cell !== NULL and $cell['part']) {

			if(
				($said['variety'] and $couple['eVariety']->is($cell['eVariety']) === FALSE) or
				($said['harvestSize'] and $couple['eSize']->is($cell['eSize']) === FALSE)
			) {
				EntryLib::refuse($reading, ['reason' => 'cellCouple']);
				return NULL;
			}

			return [$cell['eVariety'], $cell['eSize']];

		}

		$cVarietyChoice = self::getVarietyChoices($couple, $eCultivation);

		if($couple['eVariety']->notEmpty() and $cVarietyChoice->offsetExists($couple['eVariety']['id']) === FALSE) {
			EntryLib::refuse($reading, ['reason' => 'variety', 'variety' => $couple['eVariety'], 'cultivation' => $eCultivation]);
			return NULL;
		}

		$reading['couple']['cVarietyChoice'] = $cVarietyChoice;
		$reading['couple']['cSizeChoice'] = $couple['cSize'];

		self::setChips($reading);

		// Sans variété ni calibre, la part « Non spécifiée » n'a rien à vérifier.
		if($couple['eVariety']->notEmpty() or $couple['eSize']->notEmpty()) {

			$eTaskCouple = \planning\HarvestWeekLib::getCoupleProbe($eStructure, $eCultivation);
			$eTaskCouple->build(['variety', 'harvestSize'], [
				'variety' => $couple['eVariety']->empty() ? NULL : $couple['eVariety']['id'],
				'harvestSize' => $couple['eSize']->empty() ? NULL : $couple['eSize']['id'],
			]);

		}

		return [$couple['eVariety'], $couple['eSize']];

	}

	/**
	 * Les variétés que cultive la série retenue, par id. Elle est en général parmi les cultures où la phrase a
	 * cherché (`varieties`) ; un jour dit dans une autre saison en retient une que la lecture n'avait pas
	 * chargée, dont les parts se lisent alors.
	 */
	private static function getVarietyChoices(array $couple, \series\Cultivation $eCultivation): \Collection {

		if(array_key_exists($eCultivation['id'], $couple['varieties'])) {
			return $couple['cVariety']->find(fn(\plant\Variety $eVariety) => in_array($eVariety['id'], $couple['varieties'][$eCultivation['id']], TRUE));
		}

		$cVariety = new \Collection();

		foreach(\series\SliceLib::getByCultivation($eCultivation) as $eSlice) {
			$cVariety[$eSlice['variety']['id']] = $eSlice['variety'];
		}

		return $cVariety->sort(['name'], natural: TRUE);

	}

	/**
	 * Le temps seul sur la récolte, « courgette 30 min » : l'intervention que la récolte compléterait le
	 * reçoit, sans quantité, et reste dans son état (`prepareHarvestForWeek()` ne termine qu'une intervention
	 * qui reçoit une quantité). Une unité autre que celle de la semaine désignerait une intervention neuve qui
	 * ne porterait que du temps dans une unité que rien n'affiche : Consigner la refuse
	 * (`Task::harvestUnit.quantity`), la saisie aussi. `$unit` est l'unité écrite : des caisses dites sans
	 * quantité n'ont rien à convertir, et le temps va sur l'intervention en kilos.
	 */
	private static function resolveTime(\structure\Structure $eStructure, array &$reading, \series\Cultivation $eCultivation, \series\Task $eTask, string $unit, ?string $unitOther, ?array $cell, \plant\Variety $eVariety, \plant\Size $eSize): void {

		if($eTask->exists() === FALSE and $unitOther !== NULL) {
			EntryLib::refuse($reading, ['reason' => 'unitQuantity']);
			return;
		}

		self::fillPlaces(new \Collection([$eCultivation]));

		$reading['confirmation'] = [
			'quantity' => NULL,
			'crates' => NULL,
			'unit' => $unit,
			'minutes' => $reading['complements']['duration'],
			'date' => $reading['date'],
			'today' => NULL,
			'part' => ($cell !== NULL or $reading['couple']['said'] !== []),
			'eTask' => $eTask,
			'eTaskDelayed' => new \series\Task(),
			'created' => ($eTask->exists() === FALSE),
			'eProduct' => new \product\Product(),
			'cell' => $cell,
			'eVariety' => $eVariety,
			'eSize' => $eSize,
			// Qui saisit, le membre connecté, à qui `prepare()` attribue le temps dit.
			'eUser' => \user\ConnectionLib::getOnline(),
		];

		$reading['outcome'] = EntryLib::WRITE;

	}

	/**
	 * La récolte construite et validée ici, hors transaction, avec les droits de Consigner : la création
	 * pour une intervention neuve, l'écriture sinon. `taskBefore` garde ce qu'Annuler rendra d'une
	 * intervention à faire que la récolte va terminer, `NULL` quand il n'y a rien à rendre : sa planification,
	 * et sa récolte et son unité, que la première récolte pose sur une intervention qui n'en avait pas.
	 *
	 * Le temps se construit et se contrôle dans l'ordre de `HarvestWeekLib::writeEntry()` : la personne,
	 * puis la journée de 24 heures, avant l'intervention.
	 */
	public static function prepare(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];
		$eTask = $confirmation['eTask'];

		$fw = new \FailWatch();

		$cTimesheet = new \Collection();

		if($confirmation['minutes'] !== NULL) {

			$eUserOnline = \user\ConnectionLib::getOnline();

			$cTimesheet = \planning\HarvestWeekLib::buildTime($eStructure, $eTask, $reading['date'], 'add', [
				$eUserOnline['id'] => DurationComplementLib::getHours($confirmation['minutes'])
			]);

			$fw->validate();

			\planning\HarvestWeekLib::checkTimeDay($eStructure, $eTask, $cTimesheet, 'add');

			$fw->validate();

			$cTimesheet->validate('canWrite', 'acceptWrite');

		}

		if($confirmation['created']) {

			// Relue entière : `TaskLib::create()` recalcule la récolte de la culture (`recalculateHarvest()`),
			// qui lit ses poids de botte et d'unité.
			$eTask['cultivation'] = \series\CultivationLib::getById($eTask['cultivation']['id']);

			$eTask->validate('canCreate', 'acceptCreate');

		} else {
			$eTask->validate('canWrite', 'acceptWrite');
		}

		// Relevé avant `prepareHarvestForWeek()`, qui pose déjà sur l'élément la récolte et l'unité à écrire. Un retrait
		// ne termine rien (`writePreparedHarvestForWeek()`) : Annuler n'aurait rien à rendre.
		$reading['taskBefore'] = ($eTask->exists() and $eTask['status'] === \series\Task::TODO and ($confirmation['quantity'] === NULL or $confirmation['quantity'] > 0.0)) ?
			$eTask->extracts(['status', 'plannedWeek', 'plannedDate', 'harvest', 'harvestUnit']) :
			NULL;

		// Un temps seul n'a pas de quantité : la chaîne vide est celle que Consigner poste, et `build()` la lit 0.
		// Des caisses se postent comme Consigner les poste, en nombre de caisses avec le poids de l'espèce :
		// `prepareHarvestForWeek()` les convertit et refuse une intervention qui ne se compte pas en kilos.
		$harvestMore = $confirmation['crates'] ?? $confirmation['quantity'];

		$eTask->build(['harvestMore'], ['harvestMore' => ($harvestMore === NULL) ? '' : (string)$harvestMore], new \PropertyList('week'));

		$crateWeight = ($confirmation['crates'] === NULL) ? NULL : $reading['object']['crateWeight'];

		// Un retrait est un ajout négatif, que `prepareHarvestForWeek()` borne au récolté du jour (`Task::harvestMore.day`).
		$reading['harvest'] = \series\TaskLib::prepareHarvestForWeek($eTask, $cTimesheet, $reading['date'], 'add', $confirmation['part'], crateWeight: $crateWeight, harvestUnit: $confirmation['unit']);

	}

	/**
	 * `taskLeft` est l'état où l'écriture laisse l'intervention, relu dans la transaction : Annuler ne la
	 * remet à faire que si elle n'en a pas bougé.
	 */
	public static function write(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		\series\TaskLib::writePreparedHarvestForWeek($reading['harvest']);

		$eTask = $reading['harvest']['eTaskWritten'];

		$reading['taskLeft'] = \series\Task::model()
			->select(['status', 'plannedDate', 'doneWeek', 'harvest'])
			->whereId($eTask['id'])
			->get()
			->extracts(['status', 'plannedDate', 'doneWeek', 'harvest']);

		$reading['taskTouched'] = CancelLib::getTouched($eTask);

		$reading['eHarvest'] = ($confirmation['quantity'] === NULL) ? new \series\Harvest() : $eTask['eHarvest'];

		// Signée : un retrait baisse le stock d'autant, comme dans la feuille (`HarvestStockLib`).
		if($confirmation['eProduct']->notEmpty()) {

			\product\StockLib::increment($confirmation['eProduct'], $confirmation['quantity'], new \product\Stock([
				'comment' => $eTask['action']['name']
			]), zeroIfNegative: TRUE);

		}

		$reading['confirmation']['eTask'] = $eTask;

	}

	/**
	 * Le ticket d'annulation, posé une fois l'écriture validée : posé avant, il survivrait à une
	 * transaction défaite. `undo` dit à la pastille ce que son lien « Annuler » poste, l'intention qui
	 * annule et le jeton de son ticket.
	 */
	public static function afterCommit(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		if($confirmation['quantity'] === NULL) {
			$reading['undo'] = ['intention' => 'time', 'ticket' => TimeIntentionLib::setCancel($eStructure, $confirmation['eTask'], [$reading['date']], $confirmation['minutes'], $confirmation['created'], $reading['taskTouched'])];
			return;
		}

		$reading['undo'] = ['intention' => 'harvest', 'ticket' => self::setCancel($eStructure, $reading)];

	}

	/**
	 * Le ticket d'une récolte écrite par la saisie, et son jeton.
	 */
	private static function setCancel(\structure\Structure $eStructure, array $reading): string {

		$ticket = bin2hex(random_bytes(16));

		\Cache::redis()->set(self::getCancelKey($eStructure, $ticket), [
			'user' => \user\ConnectionLib::getOnline()['id'],
			'harvest' => $reading['eHarvest']['id'],
			'created' => $reading['confirmation']['created'],
			'minutes' => $reading['confirmation']['minutes'],
			'taskBefore' => $reading['taskBefore'],
			'taskLeft' => $reading['taskLeft'],
			'taskTouched' => $reading['taskTouched'],
		], EntrySetting::CANCEL_DELAY);

		return $ticket;

	}

	public static function getModel(): \ModuleModel {
		return \series\Task::model();
	}

	public static function getCancellation(\structure\Structure $eStructure, string $ticket): ?array {

		$eHarvest = self::getCancellable($eStructure, $ticket);

		return $eHarvest->empty() ? NULL : self::prepareCancel($eStructure, $eHarvest);

	}

	public static function getCancelFail(): string {
		return 'entry\Entry::harvest.cancellable';
	}

	/**
	 * La récolte qu'une saisie a écrite et que son auteur peut encore annuler : le jeton désigne un ticket
	 * vivant, qui porte l'utilisateur connecté, et la ligne est une récolte de la structure, écrite par lui :
	 * un ajout, ou un retrait, dont la ligne est négative. Le ticket est porté par la récolte rendue
	 * (`ticket`), avec sa clé (`key`). Une seconde annulation ne la trouve plus.
	 */
	public static function getCancellable(\structure\Structure $eStructure, string $ticket): \series\Harvest {

		if(preg_match('/^[0-9a-f]{32}$/', $ticket) !== 1) {
			return new \series\Harvest();
		}

		$eUserOnline = \user\ConnectionLib::getOnline();

		$key = self::getCancelKey($eStructure, $ticket);
		$value = \Cache::redis()->get($key);

		if($value === FALSE or $value['user'] !== $eUserOnline['id']) {
			return new \series\Harvest();
		}

		$eHarvest = \series\Harvest::model()
			->select(\series\Harvest::getSelection())
			->whereId($value['harvest'])
			->whereStructure($eStructure)
			->whereCreatedBy($eUserOnline)
			->get();

		if($eHarvest->notEmpty()) {
			$eHarvest['ticket'] = $value + ['key' => $key];
		}

		return $eHarvest;

	}

	public static function getCancelKey(\structure\Structure $eStructure, string $ticket): string {
		return 'entry-cancel-harvest-'.$eStructure['id'].'-'.$ticket;
	}

	/**
	 * Le ticket part avec l'annulation validée : elle ne se rejoue pas.
	 */
	public static function afterCancel(\structure\Structure $eStructure, array $cancellation): void {
		\Cache::redis()->delete($cancellation['key']);
	}

	/**
	 * Tout ce que l'annulation défera, lu et contrôlé hors transaction, comme `prepare()` pour
	 * l'écriture :
	 * - `eTask` : l'intervention qui porte la récolte, et `date` le jour de la récolte ;
	 * - `status` : son statut avant l'annulation, qui dit à la réponse si elle était dans À faire ;
	 * - `created` : la saisie l'a créée (le ticket le dit) et rien ne s'y est ajouté depuis, elle part
	 *   avec la récolte ;
	 * - `taskBefore` et `taskLeft` : ce qu'Annuler rend d'une intervention que la saisie a terminée, et
	 *   l'état où la saisie l'a laissée, que la remise à faire exige encore ;
	 * - `time` : le temps en heures que la saisie a posé et que l'annulation retire, 0 sans temps. Il ne
	 *   retire jamais plus que ce que la personne a encore ce jour-là sur l'intervention : un temps négatif
	 *   ferait lever `TimesheetLib::writeByTasks()` dans la transaction ;
	 * - `eProduct` et `eStock` : le produit dont la saisie a rempli le stock, et la ligne d'historique
	 *   qu'elle y a écrite. Cette ligne n'a pas de lien vers la récolte : elle est retrouvée par son
	 *   produit, son auteur, son commentaire et sa date, dans la seconde de la récolte. Aucune ou
	 *   plusieurs, `eStock` reste vide et le stock n'est pas touché ;
	 * - `key` : la clé du ticket, que `afterCancel()` retire.
	 */
	public static function prepareCancel(\structure\Structure $eStructure, \series\Harvest $eHarvest): array {

		$eTask = \series\Task::model()
			->select(\series\Task::getSelection())
			->whereId($eHarvest['task'])
			->whereStructure($eStructure)
			->get();

		$eTask->validate('canWrite');

		$cTask = new \Collection([$eTask]);

		\series\TaskLib::fillHarvestDates($cTask);

		$quantity = (float)$eHarvest['quantity'];

		if(round(($eTask['harvestDates'][$eHarvest['date']] ?? 0.0) - $quantity, 2) < 0) {
			\series\Task::fail('harvestDates.negative');
		}

		if(round(($eTask['harvest'] ?? 0.0) - $quantity, 2) < 0) {
			\series\Task::fail('harvestMore.negative');
		}

		$eProduct = new \product\Product();
		$eStock = new \product\Stock();

		if($eTask['cultivation']->notEmpty()) {

			$eProduct = self::getProduct($eStructure, $eTask['cultivation']->merge(['plant' => $eTask['plant']]), $eTask, $eHarvest['unit']);

		}

		if($eProduct->notEmpty()) {

			$cStock = \product\Stock::model()
				->select(['id', 'quantity'])
				->whereProduct($eProduct)
				->whereCreatedBy($eHarvest['createdBy'])
				->whereComment($eTask['action']['name'])
				->where('createdAt BETWEEN '.\product\Stock::model()->format($eHarvest['createdAt']).' AND '.\product\Stock::model()->format(self::getSecondAfter($eHarvest['createdAt'])))
				->getCollection();

			if($cStock->count() === 1) {
				$eStock = $cStock->first();
			}

		}

		$ticket = $eHarvest['ticket'];

		$time = ($ticket['minutes'] === NULL) ?
			0.0 :
			min(DurationComplementLib::getHours($ticket['minutes']), \series\TimesheetLib::getTimeByDate($eTask, $eHarvest['createdBy'], $eHarvest['date']));

		return [
			'eHarvest' => $eHarvest,
			'eTask' => $eTask,
			'date' => $eHarvest['date'],
			'status' => $eTask['status'],
			'minutes' => $ticket['minutes'],
			'time' => $time,
			'created' => ($ticket['created'] and CancelLib::isCreatedBy($eTask, $eHarvest['createdBy'], [$eHarvest['date'] => $time], $ticket['taskTouched'], $eHarvest)),
			'taskBefore' => $ticket['taskBefore'],
			'taskLeft' => $ticket['taskLeft'],
			'eProduct' => $eProduct,
			'eStock' => $eStock,
			'key' => $ticket['key'],
		];

	}

	/**
	 * Annule dans la transaction ouverte par `EntryLib::cancel()`. La ligne de récolte part d'abord :
	 * si elle n'y est plus, une autre annulation l'a devancée et rien d'autre ne s'écrit.
	 */
	public static function cancel(array $cancellation): bool {

		$eTask = $cancellation['eTask'];

		if(\series\TaskLib::deleteHarvest($eTask, $cancellation['eHarvest']) === FALSE) {
			return FALSE;
		}

		if($cancellation['created']) {

			\series\TaskLib::delete($eTask);

		} else {

			CancelLib::removeTime($eTask, $cancellation['eHarvest']['createdBy'], $cancellation['eHarvest']['date'], $cancellation['time']);

		}

		if($cancellation['created'] === FALSE and $cancellation['taskBefore'] !== NULL) {

			// La récolte vient de partir : l'état laissé se compare sans elle.
			$taskLeft = $cancellation['taskLeft'];
			$taskLeft['harvest'] = round($taskLeft['harvest'] - (float)$cancellation['eHarvest']['quantity'], 2);

			\series\TaskLib::updateTodoIfLeft(
				$eTask->merge($cancellation['taskBefore']),
				$taskLeft
			);

		}

		$eStock = $cancellation['eStock'];

		if(
			$eStock->notEmpty() and
			$eStock['quantity'] !== NULL and
			(float)$eStock['quantity'] !== 0.0
		) {

			// Signée comme la ligne de stock : l'annulation d'un retrait rend au stock ce qu'il avait perdu.
			\product\StockLib::decrement($cancellation['eProduct'], (float)$eStock['quantity'], new \product\Stock([
				'comment' => $eTask['action']['name']
			]), zeroIfNegative: TRUE);

		}

		return TRUE;

	}

	private static function getSecondAfter(string $date): string {
		return date('Y-m-d H:i:s', strtotime($date) + 1);
	}

	/**
	 * Le produit que l'écran de récolte présélectionne (`StockLib::getBookmark()`), à condition qu'il
	 * le propose aussi : un produit mémorisé que l'unité ou le stock ne permettent plus ne se remplit
	 * pas en silence.
	 */
	private static function getProduct(\structure\Structure $eStructure, \series\Cultivation $eCultivation, \series\Task $eTask, string $unit): \product\Product {

		$eTaskProbe = new \series\Task([
			'structure' => $eStructure,
			'cultivation' => $eCultivation,
			'plant' => $eCultivation['plant'],
			'variety' => $eTask->notEmpty() ? $eTask['variety'] : new \plant\Variety(),
			'harvestSize' => $eTask->notEmpty() ? $eTask['harvestSize'] : new \plant\Size(),
			'harvestUnit' => $unit,
		]);

		$eProductBookmark = \product\StockLib::getBookmark($eTaskProbe);

		if($eProductBookmark->empty()) {
			return new \product\Product();
		}

		foreach(\product\StockLib::getCompatibleProducts($eTaskProbe) as $eProduct) {

			if($eProduct['id'] === $eProductBookmark['id']) {
				return $eProduct;
			}

		}

		return new \product\Product();

	}

	/**
	 * Ce que le jour porte déjà dans l'unité écrite, avant la récolte saisie. Le menu dit celui de la culture,
	 * toutes ses parts réunies ; la case, ou une phrase qui dit un couple (`$part`), « Non spécifiée » compris,
	 * celui de l'intervention qu'elle complète, la part que la récolte remplit.
	 */
	private static function getToday(\series\Cultivation $eCultivation, \series\Task $eTask, string $unit, string $date, bool $part): float {

		if($part === FALSE) {
			return $eCultivation['today'][$unit] ?? 0.0;
		}

		if($eTask->exists() === FALSE) {
			return 0.0;
		}

		\series\TaskLib::fillHarvestDates($eTask);

		return $eTask['harvestDates'][$date] ?? 0.0;

	}

	/**
	 * Le jour porte-t-il une récolte répartie par variété ou par calibre, que `TaskLib::getHarvestedForWrite()`
	 * refuse de diminuer sur le jour entier ?
	 */
	private static function isVentilated(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, string $unit): bool {

		$total = \series\TaskLib::applyHarvestedDayVentilated(new \series\HarvestModel(), $eStructure, $eCultivation, $date, $unit)
			->select(['total' => new \Sql('IFNULL(SUM(quantity), 0)', 'float')])
			->get()['total'];

		return (round($total, 3) !== 0.0);

	}

	/**
	 * Les emplacements des séries, que la question et la confirmation nomment.
	 */
	public static function fillPlaces(\Collection $cCultivation): void {

		$cSeries = new \Collection();

		foreach($cCultivation as $eCultivation) {
			$cSeries[$eCultivation['series']['id']] = $eCultivation['series'];
		}

		if($cSeries->notEmpty()) {

			\series\Series::model()
				->select(['cccPlace' => \series\PlaceLib::delegateBySeries()])
				->get($cSeries);

		}

	}

}
?>
