<?php
namespace entry;

/**
 * Ce que la récolte dit à l'écran : son jeton, sa question, sa confirmation et son succès. La liste
 * de l'omnibox pose ses libellés en `textContent`, ils sont donc rendus en texte brut. Sont du HTML,
 * et encodent ce que la structure a nommé : la seconde ligne d'un choix de Q2
 * (`getCultivationSub()`), qui porte l'icône des lieux du planning, et tout ce que porte la pastille
 * (`getSuccess()`, `getCancelled()`, `getToastDetail()`), que `Lime.Alert.showToast()` pose en
 * `innerHTML`.
 */
class HarvestIntentionUi extends IntentionUi {

	public static function getLabel(): string {
		return s("Récolte");
	}

	public static function getChips(): array {
		return [self::getVarietyGroup(), self::getSizeGroup(), self::getUnitGroup()];
	}

	private static function getVarietyGroup(): string {
		return s("Variété");
	}

	private static function getSizeGroup(): string {
		return s("Calibre");
	}

	private static function getUnitGroup(): string {
		return s("Unité");
	}

	/**
	 * La part sans variété ou sans calibre, en puce. Ce n'est qu'un libellé : la puce pose dans la phrase
	 * le mot-clé que la lecture compare (`HarvestIntentionLib::UNSPECIFIED`), quel que soit ce texte.
	 */
	public static function getUnspecified(string $family): string {

		return match($family) {
			'variety' => s("Non spécifiée"),
			'harvestSize' => s("Non spécifié"),
		};

	}

	/**
	 * Les espèces tant que l'objet manque, les séries de Q2, puis les puces du couple et des unités, sous la ligne
	 * qui écrit une fois la quantité dite.
	 */
	public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, \Collection $cAction): array {

		$results = [];

		if($reading['object']->empty()) {

			foreach($cElement as $ePlant) {

				$result = [
					'group' => self::getObjectGroup($ePlant['inPhase'] > 0, $reading['date']),
					'label' => $ePlant['name'],
					'value' => 'plant:'.$ePlant['id'],
				];

				if($ePlant['fqn'] !== NULL) {
					$result['icon'] = \plant\PlantUi::getVignetteHref($ePlant['fqn']);
				}

				$detail = self::getToday($ePlant['today'], $reading['date']);

				if($detail !== NULL) {
					$result['detail'] = $detail;
				}

				$results[] = $result;

			}

			return $results;

		}

		if($reading['question'] !== NULL) {

			$group = self::getQuestion($reading['object'], $reading['question']['cCultivation']);

			foreach($reading['question']['cCultivation'] as $eCultivation) {

				$result = [
					'group' => $group,
					'label' => $eCultivation['series']['name'],
					'value' => 'cultivation:'.$eCultivation['id'],
				];

				$badge = \sequence\CropUi::start($eCultivation, $cAction);

				if($badge !== '') {
					$result['badge'] = $badge;
				}

				if($reading['object']['fqn'] !== NULL) {
					$result['icon'] = \plant\PlantUi::getVignetteHref($reading['object']['fqn']);
				}

				$sub = self::getCultivationSub($eCultivation, $eCultivation['eTaskDelayed']);

				if($sub !== '') {
					$result['sub'] = $sub;
				}

				$results[] = $result;

			}

			if($reading['question']['more'] > 0) {

				$results[] = [
					'group' => $group,
					'label' => self::getAll(),
					'value' => 'all',
					'detail' => self::getAllDetail($reading['question']['cCultivation']->count() + $reading['question']['more']),
				];

			}

			return $results;

		}

		if($reading['outcome'] === EntryLib::WRITE) {

			$result = self::getWriteResult($reading);

			// Dans une case, la ligne qui écrit montre qui saisit, comme la case montre qui a récolté.
			if($reading['confirmation']['cell'] !== NULL) {
				$result['vignette'] = \user\UserUi::getVignette($reading['confirmation']['eUser'], '1.25rem');
			}

			return array_merge([$result], self::getCoupleResults($reading), self::getUnitResults($reading['object'], $reading['units']));

		}

		return array_merge(self::getCoupleResults($reading), self::getUnitResults($reading['object'], $reading['units']));

	}

	/**
	 * Sous le refus d'un retrait, les familles du couple restent : le retrait se lit sur la part dite, et
	 * choisir une variété ou un calibre après le nombre, comme pour un ajout, est ce qui le corrige.
	 */
	public static function getRefusalResults(\structure\Structure $eStructure, array $reading): array {

		return match($reading['refusal']['reason']) {
			'removeMore', 'removeVentilated' => self::getCoupleResults($reading),
			default => [],
		};

	}

	/**
	 * Les familles du couple en puces, sous la ligne qui écrit ou sous le refus d'un retrait : les variétés de la
	 * série et les calibres de l'espèce, chacune avec sa part « Non spécifiée », que
	 * `HarvestIntentionLib::resolveCouple()` a retenues. Une famille que la phrase dit garde ses puces, la retenue
	 * marquée ; un début de nom tapé ne laisse que ce qu'il commence.
	 */
	private static function getCoupleResults(array $reading): array {

		$results = [];

		foreach($reading['couple']['chips'] as $family => $chip) {

			$group = match($family) {
				'variety' => self::getVarietyGroup(),
				'harvestSize' => self::getSizeGroup(),
			};

			foreach($chip['cChoice'] as $eChoice) {

				$results[] = [
					'group' => $group,
					'label' => $eChoice['name'],
					'value' => $family.':'.$eChoice['name'],
					'selected' => ($chip['selected'] === $eChoice['name']),
				];

			}

			if($chip['unspecified']) {

				$results[] = [
					'group' => $group,
					'label' => self::getUnspecified($family),
					'value' => $family.':'.HarvestIntentionLib::UNSPECIFIED[$family],
					'selected' => ($chip['selected'] === HarvestIntentionLib::UNSPECIFIED[$family]),
				];

			}

		}

		return $results;

	}

	/**
	 * Les unités en puces, après le couple (`HarvestIntentionLib::getUnitChoices()`). Une puce pose le mot de son
	 * unité dans la phrase, qui se relit comme s'il était tapé.
	 */
	private static function getUnitResults(\plant\Plant $ePlant, array $units): array {

		$group = self::getUnitGroup();

		return array_map(fn(array $unit) => [
			'group' => $group,
			'label' => UnitComplementUi::getChip($unit['unit'], $ePlant),
			'value' => 'unit:'.$unit['word'],
			'selected' => $unit['selected'],
		], $units);

	}

	public static function getAwaitedStep(): string {
		return 'quantity';
	}

	public static function getAwaited(array $reading): ?string {
		return self::getQuantityPlaceholder($reading);
	}

	public static function getPlaceholder(array $reading, string $step): string {

		return match($step) {
			'object' => self::getObjectPlaceholder(),
			'question' => self::getQuestion($reading['object'], $reading['question']['cCultivation']),
			'quantity', 'write' => self::getQuantityPlaceholder($reading),
		};

	}

	/**
	 * Une récolte lue sans aucun mot (`blank`) ne vient que d'une case d'un jour de récolte, qui dit le verbe et la
	 * série : elle pose la même question au focus qu'au repos (`EntryUi::getHarvestCell()`).
	 */
	private static function getQuantityPlaceholder(array $reading): string {

		if($reading['cultivation']->empty()) {
			return self::getObjectPlaceholder();
		}

		if($reading['blank']) {
			return QuantityComplementUi::getBlankPlaceholder();
		}

		return QuantityComplementUi::getPlaceholder($reading['complements']['unit']);

	}

	public static function getRefusal(array $reading): ?string {

		$refusal = $reading['refusal'];

		return match($refusal['reason']) {
			'quantity' => self::getQuantityRefusal(),
			'unitQuantity' => self::getUnitQuantityRefusal(),
			'crateUnit' => self::getCrateUnitRefusal($refusal['unit']),
			'crateWeight' => self::getCrateWeightRefusal($refusal['object']),
			'unclaimed' => s("Comptez {value}.", UnitComplementUi::getVocabulary($refusal['vocabulary']['unit'])),
			'future' => self::getFutureRefusal(),
			'past' => self::getPastRefusal($refusal['days']),
			'dated' => self::getDatedRefusal($refusal['object']),
			'season' => self::getSeasonRefusal($refusal['object'], $refusal['value']),
			'seasonDate' => self::getSeasonDateRefusal($refusal['value']),
			'variety' => s("La série {series} ne cultive pas la variété {variety}.", ['series' => $refusal['cultivation']['series']['name'], 'variety' => $refusal['variety']['name']]),
			'cellCouple' => s("Pour une autre variété ou un autre calibre, saisissez dans sa ligne."),
			'crateRemove' => s("Une récolte se retire en kilos, pas en caisses."),
			'removeTime' => s("Un retrait ne porte pas de temps de travail, saisissez le temps à part."),
			'removeMore' => self::getRemoveMoreRefusal($refusal['today'], $refusal['unit']),
			'removeVentilated' => s("La récolte de ce jour est en partie répartie par variété ou par calibre, précisez la variété ou le calibre à retirer."),
			default => NULL,
		};

	}

	public static function getObjectIcon(\Element $eElement): ?string {
		return self::getPlantIcon($eElement);
	}

	public static function getWriteLabel(array $reading): string {

		return ($reading['confirmation']['cell'] === NULL) ?
			self::getConfirmation($reading['object'], $reading['confirmation']) :
			self::getCellConfirmation($reading['confirmation']);

	}

	/**
	 * Le couple où la récolte s'écrit, ses deux membres dits dès que l'un est choisi : « Butternut · Calibre non
	 * spécifié » dit que le calibre n'a pas été oublié, il n'est pas précisé. Une famille que la série ne propose
	 * pas ne se nomme pas. Sans rien de choisi, « Non spécifiée », comme la grille nomme une part sans variété ni
	 * calibre (`\planning\HarvestWeekUi::getCoupleName()`). Dans la case d'un jour, il est seul : la série et le
	 * jour, la case les dit déjà. Au menu, il suit la série dès que la série propose une famille. La case d'une part
	 * ne propose aucune famille, son couple est celui de sa ligne : le détail le redit comme la grille nomme la
	 * ligne, sans y ajouter l'absent.
	 */
	public static function getWriteDetail(array $reading): string {

		$confirmation = $reading['confirmation'];

		$couple = self::getCoupleDetail($reading['couple'], $confirmation['eVariety'], $confirmation['eSize']);

		if($confirmation['cell'] !== NULL) {
			return $couple ?? s("Non spécifiée");
		}

		$detail = self::getConfirmationDetail($reading['cultivation'], $confirmation['eTaskDelayed']);

		return ($couple === NULL) ? $detail : $detail.' · '.$couple;

	}

	/**
	 * `NULL` quand la série ne propose ni variété ni calibre et que rien n'est dit.
	 */
	private static function getCoupleDetail(array $couple, \plant\Variety $eVariety, \plant\Size $eSize): ?string {

		$families = [
			'variety' => ($eVariety->notEmpty() or $couple['cVarietyChoice']->notEmpty()),
			'harvestSize' => ($eSize->notEmpty() or $couple['cSizeChoice']->notEmpty()),
		];

		if($eVariety->empty() and $eSize->empty()) {
			return ($families['variety'] or $families['harvestSize']) ? s("Non spécifiée") : NULL;
		}

		$names = [];

		if($families['variety']) {
			$names[] = $eVariety->notEmpty() ? $eVariety['name'] : s("Variété non spécifiée");
		}

		if($families['harvestSize']) {
			$names[] = $eSize->notEmpty() ? $eSize['name'] : s("Calibre non spécifié");
		}

		return implode(' · ', $names);

	}

	/**
	 * Le temps seul sur la récolte n'écrit pas de quantité : sa pastille ne parle que de temps.
	 */
	public static function getWriteToast(array $reading): array {

		$confirmation = $reading['confirmation'];

		return [
			'package' => 'entry',
			'fqn' => ($confirmation['quantity'] === NULL) ? 'Entry::harvestTimed' : 'Entry::harvested',
			'options' => [
				'quantity' => $confirmation['quantity'],
				'crates' => $confirmation['crates'],
				'unit' => $confirmation['unit'],
				'minutes' => $confirmation['minutes'],
				'plant' => $reading['object']['name'],
				'date' => $confirmation['date'],
			],
			'icon' => self::getPlantIcon($confirmation['eTask']['plant']),
			'detail' => self::getToastDetail($reading['cultivation']),
		];

	}

	public static function getWriteEvent(\structure\Structure $eStructure, array $reading): array {

		$confirmation = $reading['confirmation'];

		return EntryUi::getEvent($eStructure, $confirmation['eTask'], $confirmation['date'], $reading['eHarvest'], $confirmation['quantity'], $confirmation['unit']);

	}

	/**
	 * Le stock n'est signalé que s'il est suivi et que la ligne écrite par la saisie n'a pas été retrouvée.
	 */
	public static function getCancelToast(array $cancellation): array {

		$eHarvest = $cancellation['eHarvest'];
		$ePlant = $cancellation['eTask']['plant'];

		return [
			'package' => 'entry',
			'fqn' => 'Entry::cancelled',
			'options' => [
				'quantity' => (float)$eHarvest['quantity'],
				'unit' => $eHarvest['unit'],
				'minutes' => $cancellation['minutes'],
				'plant' => $ePlant['name'],
				'date' => $eHarvest['date'],
				'product' => ($cancellation['eProduct']->notEmpty() and $cancellation['eStock']->empty()) ? $cancellation['eProduct']['name'] : NULL,
			],
			'icon' => self::getPlantIcon($ePlant),
		];

	}

	public static function getCancelEvent(\structure\Structure $eStructure, array $cancellation): array {

		$eHarvest = $cancellation['eHarvest'];

		return EntryUi::getEvent($eStructure, $cancellation['eTask'], $eHarvest['date'], $eHarvest, (float)$eHarvest['quantity'], $eHarvest['unit']);

	}

	public static function getObjectPlaceholder(): string {
		return s("Quelle espèce ?");
	}

	/**
	 * Le groupe d'une espèce dans la liste. La phase se lit à la semaine du jour dit, que le groupe
	 * nomme quand ce n'est pas celle d'aujourd'hui.
	 */
	public static function getObjectGroup(bool $inPhase, string $date): string {

		if($inPhase === FALSE) {
			return s("Autres espèces de la saison");
		}

		return (toWeek($date) === \Clock::week()) ?
			s("En récolte cette semaine") :
			s("En récolte en semaine {value}", week_number(toWeek($date)));

	}

	public static function getUnitQuantityRefusal(): string {
		return s("Une autre unité se dit avec une quantité.");
	}

	/**
	 * Des caisses sur une récolte qui ne se compte pas en kilos, que le panneau Consigner refuse aussi
	 * (`Task::harvestUnit.crate`).
	 */
	public static function getCrateUnitRefusal(string $unit): string {
		return s("Les caisses se convertissent en kilos, mais cette récolte se compte {value}.", UnitComplementUi::getCountedIn($unit));
	}

	/**
	 * Des caisses d'une espèce sans poids de caisse. Le poids se règle dans le panneau Consigner, à la
	 * première récolte en caisses, par qui peut écrire l'espèce : tout membre, donc quiconque lit une récolte
	 * (`Plant::canRead()`, `HarvestIntentionLib::canWrite()`).
	 */
	public static function getCrateWeightRefusal(\plant\Plant $ePlant): string {
		return s("Le poids d'une caisse {plant} se règle en récoltant en caisses depuis le planning.", ['plant' => self::of($ePlant['name'])]);
	}

	public static function getQuantityRefusal(): string {
		return s("Indiquez une quantité autre que zéro.");
	}

	/**
	 * Un retrait qui passerait sous zéro, que `Task::harvestMore.day` refuserait à l'écriture.
	 */
	public static function getRemoveMoreRefusal(float $today, string $unit): string {

		return ($today === 0.0) ?
			s("Rien n'a été récolté ce jour, il n'y a rien à retirer.") :
			s("La récolte de ce jour n'est que de {value}, vous ne pouvez pas en retirer davantage.", self::getQuantity($today, $unit));

	}

	public static function getFutureRefusal(): string {
		return s("Une récolte ne se saisit pas à l'avance.");
	}

	public static function getPastRefusal(int $days): string {
		return s("Au-delà de {value} jours, la récolte se saisit depuis l'écran de récolte de son intervention.", $days);
	}

	/**
	 * Une espèce nommée qui n'a aucune série à proposer au jour dit.
	 */
	public static function getDatedRefusal(\plant\Plant $ePlant): string {
		return s("Aucune série {plant} à cette date.", ['plant' => self::of($ePlant['name'])]);
	}

	/**
	 * Une saison dite où l'espèce n'a aucune série : écrire l'année en quantité serait le défaut que la
	 * lecture de la saison corrige.
	 */
	public static function getSeasonRefusal(\plant\Plant $ePlant, int $season): string {
		return s("Aucune série {plant} sur la saison {season}.", ['plant' => self::of($ePlant['name']), 'season' => $season]);
	}

	/**
	 * Une saison dite à plus d'une saison de celle du jour dit, où aucune récolte ne s'écrit.
	 */
	public static function getSeasonDateRefusal(int $season): string {
		return s("Une série de la saison {value} ne se récolte pas à cette date.", $season);
	}

	/**
	 * Le récolté du jour d'une espèce, en détail de sa suggestion : « 45 kg aujourd'hui », ou du jour
	 * dit, « 45 kg hier ».
	 */
	public static function getToday(array $today, string $date): ?string {

		if($today === []) {
			return NULL;
		}

		$quantities = [];

		foreach($today as $unit => $quantity) {
			$quantities[] = self::getQuantity($quantity, $unit);
		}

		$day = DateComplementUi::getDay($date);

		return ($day === NULL) ?
			s("{value} aujourd'hui", implode(', ', $quantities)) :
			s("{value} {day}", ['value' => implode(', ', $quantities), 'day' => $day]);

	}

	/**
	 * Q2, sans accord en genre, l'espèce ne le portant pas : deux choix se distinguent par leur semaine
	 * de départ quand elles diffèrent, sinon la question reste ouverte.
	 */
	public static function getQuestion(\plant\Plant $ePlant, \Collection $cCultivation): string {

		if($cCultivation->count() === 2) {

			[$week1, $week2] = $cCultivation->makeArray(fn($eCultivation) => self::getStartWeekNumber($eCultivation));

			if($week1 !== NULL and $week2 !== NULL and $week1 !== $week2) {
				return s("{plant} de la semaine {week1} ou de la semaine {week2} ?", ['plant' => $ePlant['name'], 'week1' => $week1, 'week2' => $week2]);
			}

		}

		return s("Quelle série {plant} ?", ['plant' => self::of($ePlant['name'])]);

	}

	public static function getAll(): string {
		return s("Toutes les séries");
	}

	public static function getAllDetail(int $count): string {
		return p("{value} série", "{value} séries", $count);
	}

	/**
	 * La seconde ligne de la pastille d'une récolte, en HTML : la série, puis sa semaine et ses lieux
	 * comme dans la question.
	 */
	public static function getToastDetail(\series\Cultivation $eCultivation): string {

		$h = encode(s("Série {value}", $eCultivation['series']['name']));

		$sub = self::getCultivationSub($eCultivation, new \series\Task());

		if($sub !== '') {
			$h .= ' · '.$sub;
		}

		return $h;

	}

	/**
	 * La seconde ligne d'un choix de Q2, en HTML : la saison quand ce n'est pas celle de la date, la
	 * semaine de départ, la semaine où était prévue la récolte en retard que la saisie terminerait
	 * (`$eTaskDelayed`), puis les lieux tels que le planning les écrit sous le nom d'une série
	 * (parcelle › planches, parcelles jointes par +), que `HarvestIntentionLib::fillPlaces()` a chargés
	 * pour toutes les candidates. La pastille la reprend sans le retard, que la récolte écrite a terminé.
	 */
	public static function getCultivationSub(\series\Cultivation $eCultivation, \series\Task $eTaskDelayed): string {

		$parts = [];

		if($eCultivation['nameWithSeason']) {
			$parts[] = encode(SeasonComplementUi::getSeason($eCultivation['series']['season']));
		}

		$week = self::getStartWeekNumber($eCultivation);

		if($week !== NULL) {
			$parts[] = encode(s("s{value}", $week));
		}

		if($eTaskDelayed->notEmpty()) {
			$parts[] = encode(self::getDelayedLabel($eTaskDelayed));
		}

		$places = new \series\CultivationUi()->displayPlaces($eCultivation['series']['use'], $eCultivation['series']['cccPlace']);

		if($places !== '') {
			$parts[] = $places;
		}

		return implode(' · ', $parts);

	}

	/**
	 * La ligne de confirmation : « Ajouter 12 kg de courgette », avec le temps dit, « Ajouter 12 kg de
	 * courgette et 30 min », et le total du jour quand une récolte l'a déjà commencé, « Ajouter 12 kg de
	 * courgette, 57 kg aujourd'hui ». Sans récolte antérieure, le total redirait la quantité. Un autre jour
	 * se dit après la récolte, et le total devient celui de la journée : « Ajouter 12 kg de courgette hier,
	 * 57 kg sur la journée ». Le temps seul se pose sur la récolte : « Ajouter 30 min à la récolte de
	 * courgette ».
	 */
	public static function getConfirmation(\plant\Plant $ePlant, array $confirmation): string {

		if($confirmation['quantity'] === NULL) {

			$values = [
				'duration' => DurationComplementUi::getDuration($confirmation['minutes']),
				'plant' => self::of($ePlant['name']),
				'day' => DateComplementUi::getDay($confirmation['date']),
			];

			return ($values['day'] === NULL) ?
				s("Ajouter {duration} à la récolte {plant}", $values) :
				s("Ajouter {duration} {day} à la récolte {plant}", $values);

		}

		return self::getAddition(self::getHarvest(self::getQuantity(abs($confirmation['quantity']), $confirmation['unit']), self::of($ePlant['name']), $confirmation['minutes'], $confirmation['crates']), $confirmation);

	}

	/**
	 * La ligne de confirmation dans la case d'un jour de récolte (`EntryUi::getHarvestCell()`) : celle du champ
	 * du menu sans l'espèce, que la ligne de la case nomme déjà, « Ajouter 12 kg et 0h30 », avec le temps écrit
	 * comme la Semaine l'affiche (`\planning\DurationUi::formatDuration()`). Le total du jour est celui de la
	 * case, l'intervention que la récolte complète.
	 */
	public static function getCellConfirmation(array $confirmation): string {

		$duration = ($confirmation['minutes'] === NULL) ? NULL : \planning\DurationUi::formatDuration($confirmation['minutes'] / 60);
		$day = DateComplementUi::getDay($confirmation['date']);

		if($confirmation['quantity'] === NULL) {

			return ($day === NULL) ?
				s("Ajouter {duration} à la récolte", ['duration' => $duration]) :
				s("Ajouter {duration} {day} à la récolte", ['duration' => $duration, 'day' => $day]);

		}

		$quantity = self::getQuantity(abs($confirmation['quantity']), $confirmation['unit']);

		if($confirmation['crates'] !== NULL) {
			$quantity = s("{crates} ({kilos})", ['crates' => self::getCrates($confirmation['crates']), 'kilos' => $quantity]);
		}

		return self::getAddition(($duration === NULL) ? $quantity : s("{quantity} et {duration}", ['quantity' => $quantity, 'duration' => $duration]), $confirmation);

	}

	/**
	 * « Ajouter <récolte> », suivi du jour quand ce n'est pas aujourd'hui, et du total du jour quand une récolte
	 * l'a déjà commencé : sans récolte antérieure, le total redirait la quantité. Une quantité négative retire,
	 * « Retirer 12 kg, 45 kg aujourd'hui », et dit toujours ce qui reste, zéro compris.
	 */
	private static function getAddition(string $harvest, array $confirmation): string {

		$values = [
			'harvest' => $harvest,
			'today' => self::getQuantity($confirmation['today'], $confirmation['unit']),
			'day' => DateComplementUi::getDay($confirmation['date']),
		];

		if($confirmation['quantity'] < 0.0) {

			return ($values['day'] === NULL) ?
				s("Retirer {harvest}, {today} aujourd'hui", $values) :
				s("Retirer {harvest} {day}, {today} sur la journée", $values);

		}

		$started = ($confirmation['today'] > $confirmation['quantity']);

		if($values['day'] === NULL) {

			return $started ?
				s("Ajouter {harvest}, {today} aujourd'hui", $values) :
				s("Ajouter {harvest}", $values);

		} else {

			return $started ?
				s("Ajouter {harvest} {day}, {today} sur la journée", $values) :
				s("Ajouter {harvest} {day}", $values);

		}

	}

	/**
	 * « 12 kg de courgette », et le temps quand la phrase en dit un : « 12 kg de courgette et 30 min ».
	 * Ses deux morceaux arrivent prêts, encodés ou non selon ce que l'appelant rend.
	 *
	 * Une récolte dite en caisses (`$crates`) se dit en caisses, les kilos écrits entre parenthèses :
	 * « 3 caisses de courgette (24 kg) ». Pas « , soit 24 kg » : la virgule de la confirmation porte déjà
	 * le total du jour (« , 57 kg aujourd'hui »).
	 */
	private static function getHarvest(string $quantity, string $plant, ?int $minutes, ?float $crates = NULL): string {

		if($crates !== NULL) {

			$values = [
				'crates' => self::getCrates($crates),
				'plant' => $plant,
				'kilos' => $quantity,
			];

			return ($minutes === NULL) ?
				s("{crates} {plant} ({kilos})", $values) :
				s("{crates} {plant} ({kilos}) et {duration}", $values + ['duration' => DurationComplementUi::getDuration($minutes)]);

		}

		$values = [
			'quantity' => $quantity,
			'plant' => $plant,
		];

		return ($minutes === NULL) ?
			s("{quantity} {plant}", $values) :
			s("{quantity} {plant} et {duration}", $values + ['duration' => DurationComplementUi::getDuration($minutes)]);

	}

	/**
	 * « 3 caisses », au nombre entier que la lecture exige.
	 */
	private static function getCrates(float $crates): string {
		return p("{value} caisse", "{value} caisses", $crates, ['value' => \util\TextUi::number($crates, NULL)]);
	}

	/**
	 * Son détail : où la récolte va, la série, sa saison quand ce n'est pas celle de la date, et le lieu,
	 * puis la semaine où elle était prévue quand elle termine une récolte en retard. Ni le stock qu'elle
	 * remplit ni l'intervention qu'elle crée ne s'y disent : c'est la façon dont elle est rangée, pas ce
	 * qu'on fait.
	 */
	public static function getConfirmationDetail(\series\Cultivation $eCultivation, \series\Task $eTaskDelayed): string {

		$parts = [
			$eCultivation['series']['name'],
		];

		if($eCultivation['nameWithSeason']) {
			$parts[] = SeasonComplementUi::getSeason($eCultivation['series']['season']);
		}

		$place = self::getPlace($eCultivation['series']);

		if($place !== NULL) {
			$parts[] = $place;
		}

		if($eTaskDelayed->notEmpty()) {
			$parts[] = self::getDelayedLabel($eTaskDelayed);
		}

		return implode(' · ', $parts);

	}

	/**
	 * « prévue en semaine 26 », en texte brut : la récolte en retard que la saisie terminerait.
	 */
	public static function getDelayedLabel(\series\Task $eTask): string {
		return s("prévue en semaine {value}", week_number($eTask['plannedWeek']));
	}

	/**
	 * « 12 kg de courgette ajoutés », titre de la pastille, et le jour quand ce n'est pas aujourd'hui :
	 * « 12 kg de courgette ajoutés hier ». Le participe s'accorde à l'unité : des kilos, des pièces, des
	 * bottes. Avec le temps, « 12 kg de courgette et 30 min ajoutés » : la durée est masculine, et le
	 * pluriel d'un nom masculin et d'un nom féminin est masculin.
	 */
	public static function getSuccess(array $options): string {

		$values = [
			'harvest' => self::getHarvest(self::getQuantity(abs($options['quantity']), $options['unit']), encode(self::of($options['plant'])), $options['minutes'], $options['crates']),
		];

		$added = ($options['quantity'] < 0.0) ? self::getRemoved($options, $values) : self::getAdded($options, $values);

		$day = DateComplementUi::getDay($options['date']);

		return ($day === NULL) ? $added : s("{added} {day}", ['added' => $added, 'day' => encode($day)]);

	}

	/**
	 * « 12 kg de courgette retirés » : un retrait ne porte ni caisses ni temps
	 * (`HarvestIntentionLib::resolve()`), le participe ne s'accorde qu'à l'unité.
	 */
	private static function getRemoved(array $options, array $values): string {

		return match($options['unit']) {
			\series\Task::KG => ($options['quantity'] <= -2) ?
				s("{harvest} retirés", $values) :
				s("{harvest} retiré", $values),
			\series\Task::UNIT, \series\Task::BUNCH => ($options['quantity'] <= -2) ?
				s("{harvest} retirées", $values) :
				s("{harvest} retirée", $values),
		};

	}

	/**
	 * Le participe s'accorde à ce que la phrase a compté : des caisses, féminines, plutôt que les kilos
	 * écrits entre parenthèses.
	 */
	private static function getAdded(array $options, array $values): string {

		if($options['minutes'] !== NULL) {
			return s("{harvest} ajoutés", $values);
		}

		if($options['crates'] !== NULL) {
			return ($options['crates'] >= 2) ?
				s("{harvest} ajoutées", $values) :
				s("{harvest} ajoutée", $values);
		}

		return match($options['unit']) {
			\series\Task::KG => ($options['quantity'] >= 2) ?
				s("{harvest} ajoutés", $values) :
				s("{harvest} ajouté", $values),
			\series\Task::UNIT, \series\Task::BUNCH => ($options['quantity'] >= 2) ?
				s("{harvest} ajoutées", $values) :
				s("{harvest} ajoutée", $values),
		};

	}

	/**
	 * Le temps seul posé sur la récolte : « 30 min ajoutées à la récolte de courgette », et son jour.
	 */
	public static function getTimeSuccess(array $options): string {

		$values = [
			'duration' => DurationComplementUi::getDuration($options['minutes']),
			'plant' => encode(self::of($options['plant'])),
		];

		$added = DurationComplementUi::isSingular($options['minutes']) ?
			s("{duration} ajoutée à la récolte {plant}", $values) :
			s("{duration} ajoutées à la récolte {plant}", $values);

		$day = DateComplementUi::getDay($options['date']);

		return ($day === NULL) ? $added : s("{added} {day}", ['added' => $added, 'day' => encode($day)]);

	}

	/**
	 * « Récolte de 12 kg de courgette annulée. », avec son temps, « Récolte de 12 kg de courgette et 30 min
	 * annulée. », et son jour quand ce n'est pas aujourd'hui, dit comme la pastille de l'écriture le disait :
	 * « Récolte de 12 kg de courgette faite hier annulée. » Un retrait annulé se dit comme tel, « Retrait de
	 * 12 kg de courgette annulé. ». Quand la ligne de stock que la saisie avait écrite n'a pas été retrouvée,
	 * le stock n'a pas bougé et le message le dit, pour qu'il se corrige à la main.
	 */
	public static function getCancelled(array $options): string {

		$values = [
			'harvest' => self::getHarvest(self::getQuantity(abs($options['quantity']), $options['unit']), encode(self::of($options['plant'])), $options['minutes']),
		];

		$day = DateComplementUi::getDay($options['date']);

		if($options['quantity'] < 0.0) {
			$h = ($day === NULL) ?
				s("Retrait de {harvest} annulé.", $values) :
				s("Retrait de {harvest} fait {day} annulé.", $values + ['day' => encode($day)]);
		} else {
			$h = ($day === NULL) ?
				s("Récolte de {harvest} annulée.", $values) :
				s("Récolte de {harvest} faite {day} annulée.", $values + ['day' => encode($day)]);
		}

		if($options['product'] !== NULL) {
			$h .= ' '.s("Le stock de {value} n'a pas été modifié.", encode($options['product']));
		}

		return $h;

	}

	/**
	 * La quantité avec la virgule que le champ affiche, quelle que soit l'unité : `UnitUi::getValue()`
	 * écrit le nombre tel que PHP le rend, « 12.5 », et le champ montrerait « 12,5 » au-dessus d'un
	 * bouton qui dit « 12.5 ». Son rappel ne sert qu'aux kilos et aux pièces : les bottes passent par
	 * `p()`, qui reçoit le nombre brut, et s'écrivent donc ici.
	 */
	public static function getQuantity(float $quantity, string $unit): string {

		$value = \util\TextUi::number($quantity, NULL);

		return match($unit) {
			\series\Task::KG, \series\Task::UNIT => \product\UnitUi::getValue($quantity, $unit, callback: fn($number, $word) => $value.' '.$word),
			\series\Task::BUNCH => p("{value} botte", "{value} bottes", $quantity, ['value' => $value]),
		};

	}

	private static function getStartWeekNumber(\series\Cultivation $eCultivation): ?int {

		if($eCultivation['startWeek'] === NULL) {
			return NULL;
		}

		return week_number($eCultivation->getStartWeek($eCultivation['season']));

	}

	/**
	 * Le lieu d'une série en texte : ses jardins, ou sa parcelle quand le jardin la remplit.
	 */
	private static function getPlace(\series\Series $eSeries): ?string {

		$places = [];

		foreach($eSeries['cccPlace'] as $ccPlace) {

			foreach($ccPlace as $cPlace) {

				$ePlace = $cPlace->first();

				$places[] = ($ePlace['plot']['zoneFill'] === FALSE) ? $ePlace['plot']['name'] : $ePlace['zone']['name'];

			}

		}

		return ($places === []) ? NULL : implode(' + ', array_unique($places));

	}

}
?>
