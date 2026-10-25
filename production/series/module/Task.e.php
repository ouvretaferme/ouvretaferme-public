<?php
namespace series;

class Task extends TaskElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => ['name', 'fqn', 'vignette', 'seedsSafetyMargin', 'plantsSafetyMargin'],
			'structure' => ['name'],
			'cultivation' => ['startWeek', 'startAction', 'mainUnit', 'density', 'bunchWeight', 'unitWeight'],
			'cTool?' => fn($e) => fn() => \work\ToolLib::askCollection($e['tools'], $e['structure']),
			'cProduct?' => fn($e) => fn() => \work\ToolLib::askCollection($e['products'], $e['structure']),
			'cMethod?' => fn($e) => fn() => \work\MethodLib::askCollection($e['methods'], $e['structure']),
			'variety' => ['name'],
			'category' => ['fqn', 'name'],
			'series' => ['name', 'cycle', 'mode', 'season', 'status', 'area', 'areaTarget', 'length', 'lengthTarget', 'bedWidth', 'alleyWidth', 'use'],
			'action' => \work\ActionElement::getSelection(),
			'repeat' => ['frequency', 'current', 'stop', 'completed'],
			'delayed' => function(Task $e): bool {

				$week = \Clock::week();

				return (
					$e['status'] === Task::TODO and
					$e['plannedWeek'] !== NULL and
					strcmp($e['plannedWeek'], $week) < 0
				);

			},
			'harvestSize' => ['name'],
			'createdBy' => ['firstName', 'lastName', 'visibility'],
		];

	}

	public static function validateBatch(\Collection $cTask): void {

		if($cTask->empty()) {
			throw new \FailAction('series\Task::tasks.check');
		} else {

			$eStructure = $cTask->first()['structure'];

			foreach($cTask as $eTask) {

				if($eTask['structure']['id'] !== $eStructure['id']) {
					throw new \NotExpectedAction('Different structures');
				}

			}
		}

	}

	public static function validateSameAction(\Collection $cTask, ?\work\Action $eAction = NULL): void {

		if($cTask->empty()) {
			return;
		}

		$eAction ??= $cTask->first()['action'];

		foreach($cTask as $eTask) {

			if($eTask['action']['id'] !== $eAction['id']) {
				throw new \FailAction('series\Task::actions.check');
			}

		}

	}

	public static function validateSameHarvest(\Collection $cTask): void {

		if($cTask->empty()) {
			return;
		}

		$eTaskReference = $cTask->first();

		foreach($cTask as $eTask) {

			if(
				$eTask['plant']->empty() or
				$eTaskReference['plant']->empty()
			) {
				throw new \FailAction('series\Task::harvestConsistency.plant');
			}

			if(
				$eTask['plant']['id'] !== $eTaskReference['plant']['id'] or
				$eTask['variety']->is($eTaskReference['variety']) === FALSE or
				$eTask['harvestUnit'] !== $eTaskReference['harvestUnit'] or
				$eTask['harvestSize']->is($eTaskReference['harvestSize']) === FALSE
			) {
				throw new \FailAction('series\Task::harvestConsistency.check');
			}

		}

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public function notInSeries(): bool {
		return $this['series']->empty();
	}

	public function isTodo(): bool {
		return ($this['status'] === Task::TODO);
	}

	public function neverDone(): bool {
		return ($this['doneWeek'] === NULL);
	}

	public function isDone(): bool {
		return ($this['status'] === Task::DONE);
	}

	public function isStarted(): bool {

		$this->expects(['time', 'harvest']);

		return (
			$this['time'] > 0.0 or
			$this['harvest'] > 0.0
		);

	}

	/**
	 * Une récolte faite qui ne porte plus rien : ni quantité un jour, ni temps, ni note, commentaire, matériel,
	 * fertilisation, traitement, personne prévue, temps prévu, photo ni emplacement, et qui ne revient pas
	 * d'elle-même par une répétition. Une récolte encore à faire est une prévision, jamais une coquille. Les
	 * lignes rattachées se lisent sur leurs tables (`TaskLib::getHarvestShellSelection()`), seules sources : le
	 * total `harvest`, décrémenté en SQL, peut garder un reste que leur somme arrondie n'a pas.
	 */
	public function isHarvestShell(): bool {

		$this->expects([
			'status', 'description', 'tools', 'methods', 'products', 'fertilizer', 'treatment', 'plannedUsers', 'timeExpected', 'repeat',
			'hasHarvest', 'hasTimesheet', 'hasComment', 'hasPhoto', 'hasPlace'
		]);

		return (
			$this['status'] === Task::DONE and
			$this['hasHarvest'] === FALSE and
			$this['hasTimesheet'] === FALSE and
			$this['description'] === NULL and
			$this['hasComment'] === FALSE and
			$this['tools'] === [] and
			$this['methods'] === [] and
			$this['products'] === [] and
			$this['fertilizer'] === NULL and
			$this['treatment'] === NULL and
			$this['plannedUsers'] === [] and
			$this['timeExpected'] === NULL and
			$this['repeat']->empty() and
			$this['hasPhoto'] === FALSE and
			$this['hasPlace'] === FALSE
		);

	}

	/**
	 * La première ligne écrite de la note, sans la marque d'une case (« o » à cocher, « x » cochée, que
	 * `TaskUi::getDescription()` dessine en case) ni le tiret d'une liste, que suit un blanc comme la marque :
	 * un tiret collé est un signe (« -5 °C »). C'est le nom d'une intervention qui n'a ni série ni espèce, lu en
	 * un seul endroit pour qu'elle se cherche par le nom qu'elle affiche. Vide sans note.
	 */
	public function getDescriptionStart(): string {

		$this->expects(['description']);

		if($this['description'] === NULL) {
			return '';
		}

		foreach(preg_split('/\R/', $this['description']) as $line) {

			$line = trim(preg_replace('/^\s*[oOxX-]\s/', '', $line));

			if($line !== '') {
				return $line;
			}

		}

		return '';

	}

	/**
	 * Une intervention commencée garde son jour : son temps ou sa récolte ont été saisis ce jour-là. Un commentaire
	 * ne la fige pas, il ne dit rien du jour où l'on a travaillé (retour 366).
	 */
	public function acceptPlanned(): bool {

		return (
			$this->isTodo() and
			$this->isStarted() === FALSE
		);

	}

	public function acceptPostpone(): bool {

		$this->expects(['series']);

		return (
			$this->isTodo() and
			$this['plannedWeek'] !== NULL and
			($this['series']->empty() or $this['series']->isOpen())
		);

	}

	public function acceptSoil(): bool {

		$this->expects([
			'series',
			'category' => ['fqn']
		]);

		return (
			$this['series']->empty() and
			in_array($this['category']['fqn'], [CATEGORIE_CULTURE, CATEGORIE_PRODUCTION])
		);

	}

	public function format(string $property, array $options = []): ?string {

		switch($property) {

			case 'harvest' :
			case 'harvestPeriod' :
				if($this[$property] > 0) {
					return \sequence\CropUi::getYield($this, $property, 'harvestUnit', $options);
				} else {
					return NULL;
				}

			case 'fertilizer' :
				if($this[$property] !== NULL) {

					[$major, $minor] = new \sequence\FlowUi()->getFertilizer($this);

					$h = implode('&nbsp;&nbsp;', $major);
					if($major and $minor) {
						$h .= ' + ';
					}
					$h .= implode('&nbsp;&nbsp;', $minor);

					return $h;

				} else {
					return NULL;
				}

			default :
				return parent::format($property, $options);

		}

	}

	/**
	 * Une quantité saisie à la main : la virgule vaut un point, et seuls des chiffres passent. Le cast par
	 * défaut la donnerait au `(float)` de PHP, qui lit « 6,5 » comme 6, « 1 000 » comme 1, « 1e400 » comme
	 * INF, sans échec et avant le contrôle du schéma. `Filter::check('float')` refuserait « .5 » et « 6. »,
	 * qu'un champ `number` poste.
	 */
	private static function castDecimal(string &$value): bool {

		$value = str_replace(',', '.', $value);

		if(preg_match('/^-?(\d+\.?\d*|\.\d+)$/', $value) !== 1) {
			return FALSE;
		}

		$value = (float)$value;

		return is_finite($value);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('planned')
			->replaceCheck(function(?array $planned): bool {

				$this->expects(['status']);

				if($planned !== NULL and isset($planned['date'])) {

					if(\Filter::check('date', $planned['date'])) {
						$this['plannedDate'] = $planned['date'];
						$this['plannedWeek'] = toWeek($planned['date']);
					} else {
						return FALSE;
					}

				} else if($planned !== NULL and isset($planned['week'])) {

					if(\Filter::check('week', $planned['week'])) {
						$this['plannedDate'] = NULL;
						$this['plannedWeek'] = $planned['week'];
					} else {
						return FALSE;
					}

				} else {

					$this['plannedDate'] = NULL;
					$this['plannedWeek'] = NULL;

				}

				return TRUE;

			})
			->addAfterCheck('season', function(): bool {

				if($this['season'] === NULL) {
					return TRUE;
				}

				return (
					$this['plannedWeek'] === NULL or
					abs(week_year($this['plannedWeek']) - $this['season']) <= 1
				);

			})
			->addAfterCheck('interval', function(): bool {

				if($this['season'] !== NULL) {
					return TRUE;
				}

				$interval = 86400 * 365 * 5;

				return (
					$this['plannedWeek'] === NULL or (
						$this['plannedWeek'] > toDate(\Clock::timestamp() - $interval) and
						$this['plannedWeek'] < toDate(\Clock::timestamp() + $interval)
					)
				);

			});

		$l->property('done')
			->replaceCheck(function(?array $done): bool {

				$this->expects(['status']);

				if(isset($done['date'])) {

					if(\Filter::check('date', $done['date'])) {
						$this['doneDate'] = $done['date'];
						$this['doneWeek'] = toWeek($done['date']);
					} else {
						return FALSE;
					}

				} else if(isset($done['week'])) {

					if(\Filter::check('week', $done['week'])) {
						$this['doneDate'] = NULL;
						$this['doneWeek'] = $done['week'];
					} else {
						return FALSE;
					}

				} else {
					return FALSE;
				}

				return TRUE;

			})
			->addAfterCheck('season', function(): bool {

				if($this['season'] === NULL) {
					return TRUE;
				}

				return abs(week_year($this['doneWeek']) - $this['season']) <= 1;

			})
			->addAfterCheck('interval', function(): bool {

				if($this['season'] !== NULL) {
					return TRUE;
				}

				$interval = 86400 * 365 * 5;

				return (
					$this['doneWeek'] > toDate(\Clock::timestamp() - $interval) and
					$this['doneWeek'] < toDate(\Clock::timestamp() + $interval)
				);

			});

		$l->property('timeExpected')
			->addAfterCast('empty', function(?float &$timeExpected): bool {

				// Une estimation à zéro n'en est pas une : elle s'écrit NULL, que les écrans lisent
				// comme « pas d'estimation » au lieu d'afficher 0h00.
				if($timeExpected === 0.0) {
					$timeExpected = NULL;
				}

				return TRUE;

			});

		$l->property('action')
			->addAfterCheck('usable', function(\work\Action $eAction): bool {

				$this->expects(['structure', 'category']);

				return (
					\work\ActionLib::canUse($eAction, $this['structure']) and
					in_array($this['category']['id'], $eAction['categories'])
				);

			})
			->addAfterCheck('harvest', function(\work\Action $eAction, \PropertyList $l): bool {

				if($l->for === 'update') {

					$this->expects([
						'action' => ['fqn'],
					]);

					return (
						$this['action']['fqn'] !== ACTION_RECOLTE and
						$eAction['fqn'] !== ACTION_RECOLTE
					);

				} else {
					return TRUE;
				}

			});

		$l->property('repeatMaster')
			->replaceCheck(function(mixed $value, \PropertyList $l) {

				// Pas de fréquence, pas de répétition
				$frequency = var_filter($l->input['frequency'] ?? NULL, '?string');

				if($frequency === NULL) {
					$this['repeatMaster'] = new Repeat();
					return TRUE;
				}

				$eRepeat = new Repeat([
					'task' => $this
				]);

				$fw = new \FailWatch();

				$eRepeat->build(['frequency', 'stop'], $l->input);

				if($fw->ok()) {
					$this['repeatMaster'] = $eRepeat;
					return TRUE;
				} else {
					return FALSE;
				}

			})
			->addAfterCheck('consistency', function(mixed $value, \PropertyList $l) {

				if(
					$l->isInvalid('repeatMaster') or
					$this['repeatMaster']->empty()
				) {
					return TRUE;
				}

				switch($this['status']) {

					case Task::TODO :
						if($l->isInvalid('planned')) {
							return TRUE;
						}
						return ($this['repeatMaster']['stop'] > $this['plannedWeek']);

					case Task::DONE :
						if($l->isInvalid('done')) {
							return TRUE;
						}
						return ($this['repeatMaster']['stop'] > $this['doneWeek']);

				}

			});

		$l->property('harvest')
			->addAfterCast('action', function(?float &$harvest, \PropertyList $l): bool {

				if($l->isInvalid('action')) { // L'action génère déjà une erreur
					return TRUE;
				}

				$this->expects([
					'action' => ['fqn'],
					'status',
				]);

				if($this['action']['fqn'] !== ACTION_RECOLTE) {
					$harvest = NULL;
					return TRUE;
				}

				if($this['action']['fqn'] === ACTION_RECOLTE and $this['status'] === Task::DONE and $harvest === NULL) {
					return FALSE;
				} else {
					return TRUE;
				}

			});

		$l->property('harvestSize')
			->addAfterCast('action', function(\plant\Size &$eSize, \PropertyList $l): bool {

				if($l->isInvalid('action') or $l->isInvalid('plant')) { // L'action ou l'espèce génère déjà une erreur
					return TRUE;
				}

				$this->expects([
					'action' => ['fqn'],
				]);

				if($this['action']['fqn'] !== ACTION_RECOLTE) {
					$eSize = new \plant\Size();
					return TRUE;
				}

				return (
					$eSize->empty() or
					\plant\Size::model()
						->whereStructure($this['structure'])
						->wherePlant($this['plant'])
						->exists($eSize)
				);

			});

		// La ligne de récolte du panneau d'une intervention (liste `detail`) n'écrit qu'une quantité : nulle, ce
		// que le cast fait aussi d'une valeur vide ou d'un champ non posté, elle n'ajouterait rien sans le dire.
		$l->property('harvestMore')
			->replaceCast(function(?string &$harvest): bool {

				if($harvest === NULL or $harvest === '') {
					$harvest = 0.0;
					return TRUE;
				}

				return self::castDecimal($harvest);

			})
			->addAfterCast('missing', function(float $harvest, \PropertyList $l): bool {
				return ($l->for !== 'detail' or $harvest !== 0.0);
			})
			->addAfterCheck('negative', function(?float $harvest, \PropertyList $l): bool {

				// La saisie de la semaine retire du jour saisi, jusqu'à sa récolte : ce plancher-là est le sien
				// (`TaskLib::writeHarvestForWeek()`, `harvestMore.day`), et le refus doit nommer le jour, pas le
				// total d'une intervention que la saisie ne choisit pas. `buildHarvests()` garde ce total ensuite.
				// L'action n'y a pas à être vérifiée : la saisie n'écrit que dans une récolte (`HarvestWeekLib::getTaskForWrite()`).
				if($l->for === 'week') {
					$this['harvestMore'] = $harvest;
					return TRUE;
				}

				$this->expects([
					'harvest',
				]);

				if(
					$this['action']['fqn'] === ACTION_RECOLTE and
					$this['harvest'] + $harvest >= 0
				) {
					$this['harvestMore'] = $harvest;
					return TRUE;
				} else {
					return FALSE;
				}

			});

		$l->property('harvestDay')
			->replaceCast(function(?string &$harvest): bool {

				if($harvest === NULL or $harvest === '') {
					$harvest = NULL;
					return TRUE;
				}

				return self::castDecimal($harvest);

			})
			->addAfterCheck('negative', function(?float $harvest): bool {

				if($harvest !== NULL and $harvest >= 0.0) {
					$this['harvestDay'] = $harvest;
					return TRUE;
				} else {
					return FALSE;
				}

			});

		$l->property('fertilizer')
			->addAfterCast('action', function(?array &$fertilizer, \PropertyList $l): bool {

				if($l->isInvalid('action')) { // L'action génère déjà une erreur
					return TRUE;
				}

				$this->expects([
					'action' => ['fqn'],
				]);

				if($this['action']['fqn'] !== ACTION_FERTILISATION) {
					$fertilizer = NULL;
					return TRUE;
				}

				if($fertilizer !== NULL) {
					$fertilizer = \work\RoutineLib::checkFertilizer($fertilizer);
				}

				return TRUE;

			});

		$l->property('treatment')
			->addAfterCast('action', function(?array &$treatment, \PropertyList $l): bool {

				if($l->isInvalid('action')) { // L'action génère déjà une erreur
					return TRUE;
				}

				$this->expects([
					'action' => ['fqn'],
				]);

				if($this['action']['fqn'] !== ACTION_TRAITEMENT or $treatment === NULL) {
					$treatment = NULL;
					return TRUE;
				}

				$treatment = \work\RoutineLib::checkTreatment($treatment);

				return TRUE;

			})
			->addAfterCheck('newProduct', function(?array $treatment, \PropertyList $l): bool {

				if($l->hasProperty('products') === FALSE or $l->isInvalid('products')) {
					return TRUE;
				}

				$l->expectsBuilt('products');

				if($this['ePhytoProductNew']->empty() and $this['eToolNew']->empty()) {
					return TRUE;
				}

				return ($treatment['dose'] ?? NULL) !== NULL and ($treatment['unit'] ?? NULL) !== NULL;

			});

		$l->property('cultivation')
			->addAfterCheck('series', function(Cultivation $eCultivation, \PropertyList $l): bool {

				$this->expects([
					'category' => ['fqn']
				]);

				if($this['category']['fqn'] !== CATEGORIE_CULTURE) {
					return FALSE;
				}

				switch($l->for) {

					case 'create':

						$this->expects(['series']);

						return (
							$eCultivation->empty() or
							Cultivation::model()
								->select(['mainUnit'])
								->whereSeries($this['series'])
								->get($eCultivation)
						);

					case 'update':

						$this->expects(['plant', 'action', 'season', 'cultivation', 'series']);

						if($this['series']->empty()) {
							return FALSE;
						}

						// On peut changer de cultivation dans deux cas

						// Cas 1
						// Sur la même espèce et sur la même saison...

						if(
							$eCultivation->notEmpty() and
							Cultivation::model()
								->select(['series', 'plant', 'mainUnit', 'bunchWeight', 'unitWeight'])
								->wherePlant($this['plant']) // On peut changer de Cultivation, mais sur la même espèce...
								->whereSeason($this['season']) // ... et sur la même saison
								->get($eCultivation)
						) {
							$this['oldCultivation'] = $this['cultivation'];
							return TRUE;
						}

						// Cas 2
						// Dans la même série et pas sur les récoltes
						if(
							$this['action']['fqn'] !== ACTION_RECOLTE and
							(
								$eCultivation->empty() or
								Cultivation::model()
									->select([
										'series' => ['season'],
										'plant'
									])
									->whereSeries($this['series'])
									->get($eCultivation)
							)
						) {
							$this['oldCultivation'] = $this['cultivation'];
							return TRUE;
						}

						return FALSE;

				}

			});

		$l->property('variety')
			->replaceCheck(function(\plant\Variety $eVariety): bool {

				$this->expects(['cultivation']);

				return $eVariety->empty() or Slice::model()
					->whereCultivation($this['cultivation'])
					->whereVariety($eVariety)
					->exists();

			});

		$l->property('size')
			->replaceCheck(function(\plant\Size $eSize): bool {

				$this->expects(['plant']);

				return $eSize->empty() or \plant\Size::model()
					->wherePlant($this['plant'])
					->exists($eSize);

			});

		$l->property('methods')
			->addAfterCast('action', function(mixed &$methods, \PropertyList $l): bool {

				if($l->hasProperty('action')) {
					$l->expectsBuilt('action');
				} else {
					$this->expects(['action']);
				}

				if($this['action']->empty()) {
					$methods = [];
				} else {

					$methods = \work\Method::model()
						->select('id', 'structure')
						->whereId('IN', (array)($methods ?? []))
						->whereAction($this['action'])
						->getCollection()
						->filter(fn($eMethod) => $eMethod->canRead())
						->getIds();

				}

				return TRUE;

			});

		$l->property('tools')
			->addAfterCast('action', function(mixed &$tools, \PropertyList $l): bool {

				if($l->hasProperty('action')) {
					$l->expectsBuilt('action');
				} else {
					$this->expects(['action']);
				}

				if($this['action']->empty()) {
					$tools = [];
				} else {

					// Ce que l'élément porte en base reste accepté, même rangé là par une fusion. Un élément
					// neuf n'a rien en base, et seul le matériel de l'action y entre.
					$tools = \work\ToolLib::filterForWork($this['structure'], $this['action'], (array)($tools ?? []), $this->exists() ? $this['tools'] : []);

				}

				return TRUE;

			});

		$l->property('products')
			->addAfterCast('action', function(mixed &$products, \PropertyList $l): bool {

				$this['ePhytoProductNew'] = new \work\PhytoProduct();
				$this['eToolNew'] = new \work\Tool();

				if($l->isInvalid('action')) { // L'action génère déjà une erreur
					return TRUE;
				}

				if($l->hasProperty('action')) {
					$l->expectsBuilt('action');
				} else {
					$this->expects(['action']);
				}

				$routineName = \work\RoutineLib::getProductByAction($this['action']);

				if($routineName === NULL) {
					$products = [];
					return TRUE;
				}

				$values = array_values(array_diff((array)($products ?? []), ['']));

				// Un produit du catalogue arrive sous son AMM, et ne devient un matériel de la structure qu'à l'enregistrement.
				if($routineName === 'phyto') {

					$values = array_slice($values, 0, 1);

					// Le texte saisi dans la recherche sans qu'un produit soit choisi ensuite (Task.js).
					$query = $l->input['productQuery'] ?? '';

					if($query !== '') {
						throw new \FailException('series\Task::products.unknown', [$this, $query], wrapper: 'products');
					}

					// Un produit créé depuis la recherche n'est enregistré qu'avec l'intervention, qui lui donne sa dose.
					if($values === ['new']) {
						$this['eToolNew'] = \work\ToolLib::getNewPhyto($this['structure'], $l->input['productNew'] ?? []);
						$products = [];
						return TRUE;
					}

					if($values !== [] and str_starts_with((string)$values[0], 'amm-')) {

						$amm = substr($values[0], 4);
						$eTool = \work\ToolLib::getPhytoByAmm($this['structure'], $amm);

						if($eTool->notEmpty()) {
							$values = [$eTool['id']];
						} else {
							$this['ePhytoProductNew'] = \work\PhytoProductLib::getByAmm($amm);
							$products = [];
							return $this['ePhytoProductNew']->notEmpty();
						}

					}

				}

				$products = \work\ToolLib::filterProducts($this['structure'], $routineName, $values);

				return TRUE;

			});

		$l->property('plant')
			->addAfterCheck('action', function(\plant\Plant $ePlant, \PropertyList $l): bool {

				if($l->isInvalid('action')) { // L'action génère déjà une erreur
					return TRUE;
				}

				$this->expects([
					'category' => ['fqn'],
					'action' => ['fqn']
				]);

				// Plante obligatoire pour les récoltes facultative sinon
				if($ePlant->empty()) {

					if(
						$this['category']['fqn'] === CATEGORIE_CULTURE and
						$this['action']['fqn'] === ACTION_RECOLTE
					) {
						return FALSE;
					} else {
						return TRUE;
					}

				}

				return (
					$ePlant->empty() or
					\plant\Plant::model()
						->select('name') // Requis pour traitement futur
						->get($ePlant)
				);
			})
			->addAfterCheck('owned', function(\plant\Plant $ePlant): bool {

				$this->expects(['structure']);

				return (
					$ePlant->empty() or
					\plant\Plant::model()
						->whereStructure($this['structure'])
						->exists($ePlant)
				);

			});

		$l->property('status')
			->replaceCheck(function(string $status): bool {

				$this->expects(['status']);

				if(empty($this['id'])) {
					return in_array($status, [Task::DONE, Task::TODO]);
				} else {

					switch($status) {

						case Task::DONE :
							return FALSE;

						case Task::TODO :
							return ($this['status'] === Task::DONE);

						default :
							return FALSE;

					}

				}

			});

	}

}
?>
