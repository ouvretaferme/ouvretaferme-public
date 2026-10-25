<?php
namespace sequence;

class Flow extends FlowElement {

	public static function getSelection(): array {

		return [
			'action' => \work\ActionElement::getSelection(),
			'cTool?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['tools'], $e['structure']),
			'cProduct?' => fn($e) => fn() =>\work\ToolLib::askCollection($e['products'], $e['structure']),
			'cMethod?' => fn($e) => fn() =>\work\MethodLib::askCollection($e['methods'], $e['structure']),
		] + parent::getSelection();

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function validateBatch(\Collection $cFlow): void {

		if($cFlow->empty()) {
			throw new \FailAction('series\Flow::flows.check');
		} else {

			$eSequence = $cFlow->first()['sequence'];

			foreach($cFlow as $eFlow) {

				$eFlow->validate('canWrite');

				if($eFlow['sequence']['id'] !== $eSequence['id']) {
					throw new \NotExpectedAction('Different sequences');
				}

			}
		}

	}

	public static function properties(\PropertyList $l): void {

		$prepareYear = function(?int &$year): bool {

			$this->expects([
				'sequence' => ['cycle']
			]);

			if($this['sequence']['cycle'] === Sequence::PERENNIAL) {
				$year = NULL;
			} else {

				if($year < -1 or $year > 1) {
					$year = NULL;
				}

			}

			return TRUE;

		};

		$prepareSeason = function(?int &$season): bool {

			$this->expects([
				'sequence' => ['cycle', 'perennialLifetime']
			]);

			if($this['sequence']['cycle'] === Sequence::ANNUAL) {
				$season = NULL;
			} else {

				if(
					$season < 0 and
					$season >= $this['sequence']['perennialLifetime']
				) {
					$season = NULL;
				}

			}

			return TRUE;

		};

		$l->property('action')
			->addAfterCheck('usable', function(\work\Action $eAction): bool {

				$this->expects([
					'sequence' => ['structure']
				]);

				return \work\ActionLib::canUse($eAction, $this['sequence']['structure']);

			});

		$l->property('crop')
			->addAfterCheck('harvest', function(Crop $eCrop, \PropertyList $l): bool {

				$this->expects([
					'sequence' => ['cCrop']
				]);

				// Action saisie, on vérifie qu'en cas de récolte on ait bien un crop
				if($l->isBuilt('action')) {

					$this->expects([
						'action' => ['fqn']
					]);

					if(
						$eCrop->empty() and
						$this['action']['fqn'] === ACTION_RECOLTE
					) {
						\Fail::log('Flow::action.check');
					}

				}

				return (
					$eCrop->empty() or
					Crop::model()
						->select([
								'id', 'plant'
						])
						->whereSequence($this['sequence'])
						->get($eCrop)
				);

			});

		$l->property('fertilizer')
			->addAfterCast('action', function(?array &$fertilizer, \PropertyList $l): bool {

				if($l->isBuilt('action') === FALSE) {
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

		$l->property('weekOnly')
			->replaceArgument(fn($property) => $property.'Week');

		$l->property('weekStart')
			->replaceArgument(fn($property) => $property.'Week');

		$l->property('weekStop')
			->replaceArgument(fn($property) => $property.'Week');

		$l->property('yearOnly')
			->addAfterCast('cycle', $prepareYear);

		$l->property('yearStart')
			->addAfterCast('cycle', $prepareYear);

		$l->property('yearStop')
			->addAfterCast('cycle', $prepareYear);

		$l->property('seasonOnly')
			->addAfterCast('cycle', $prepareSeason);

		$l->property('seasonStart')
			->addAfterCast('cycle', $prepareSeason);

		$l->property('seasonStop')
			->addAfterCast('cycle', $prepareSeason);

		$l->property('method')
			->replaceCheck(function(\work\Method $eMethod, \PropertyList $l): bool {

				if($l->isBuilt('action') === FALSE) {
					return TRUE;
				}

				return $eMethod->empty() or \work\Method::model()
					->whereAction($this['action'])
					->exists($eMethod);

			});

		$l->property('methods')
			->addAfterCast('action', function(mixed &$methods, \PropertyList $l): bool {

				$l->expectsBuilt('action');

				if($this['action']->empty()) {
					$methods = [];
				} else {

					$methods = \work\Method::model()
						->whereId('IN', (array)($methods ?? []))
						->whereStructure($this['structure'])
						->whereAction($this['action'])
						->getColumn('id');

				}

				return TRUE;

			});

		$l->property('tools')
			->addAfterCast('action', function(mixed &$tools, \PropertyList $l): bool {

				$l->expectsBuilt('action');

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

				$l->expectsBuilt('action');

				$routineName = \work\RoutineLib::getProductByAction($this['action']);

				if($routineName === NULL) {
					$products = [];
					return TRUE;
				}

				$products = \work\ToolLib::filterProducts($this['structure'], $routineName, (array)($products ?? []));

				if($routineName === 'phyto') {
					$products = array_slice($products, 0, 1);
				}

				return TRUE;

			});

	}

	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		$fw = new \FailWatch();

		parent::build($properties, $input, $l);

		if($fw->ok()) {

			$this->buildSeason($properties, $input);
			$this->buildPeriod($properties, $input);

		}

	}

	protected function buildPeriod(array $properties, array $input): void {

		$count = count(array_intersect($properties, ['weekOnly', 'yearOnly', 'weekStart', 'yearStart', 'weekStop', 'yearStop', 'frequency']));

		if($count === 0) {
			return;
		} else if($count < 7) {
			throw new \Exception('Inseparable properties');
		}

		switch($input['period'] ?? NULL) {

			case 'only' :
				$this->buildPeriodOnly();
				break;

			case 'interval' :
				$this->buildPeriodInterval();
				break;

			default :
				Flow::fail('weekOnly.empty');


		}

	}

	protected function buildPeriodOnly() {

		if($this['weekOnly'] === NULL) {
			Flow::fail('weekOnly.empty');
			return;
		}

		$this['weekStart'] = NULL;
		$this['yearStart'] = NULL;

		$this['weekStop'] = NULL;
		$this['yearStop'] = NULL;

		$this['frequency'] = NULL;

		if($this['sequence']['cycle'] === Sequence::PERENNIAL) {

			$this['yearOnly'] = NULL;

		} else {

			if($this['yearOnly'] === NULL) {
				Flow::fail('yearOnly.empty');
				return;
			}

			switch($this['yearOnly']) {

				case -1 :
					if($this['weekOnly'] < SequenceSetting::MIN_WEEK_MINUS_1) {
						Flow::fail('weekOnly.consistency0');
					}
					break;

				case 1 :
					if($this['weekOnly'] > SequenceSetting::MIN_WEEK_PLUS_1) {
						Flow::fail('weekOnly.consistency2');
					}
					break;

			}

		}

	}

	protected function buildPeriodInterval() {

		if($this['weekStart'] === NULL) {
			Flow::fail('weekStart.empty');
			return;
		}

		if($this['weekStop'] === NULL) {
			Flow::fail('weekStop.empty');
			return;
		}

		$this['weekOnly'] = NULL;
		$this['yearOnly'] = NULL;

		if($this['sequence']['cycle'] === Sequence::PERENNIAL) {

			$this['yearStart'] = NULL;
			$this['yearStop'] = NULL;

			if($this['weekStart'] >= $this['weekStop']) {
				Flow::fail('weekStop.consistency');
			}

		} else {

			if($this['yearStart'] === NULL) {
				Flow::fail('yearStart.empty');
				return;
			}

			if($this['yearStop'] === NULL) {
				Flow::fail('yearStop.empty');
				return;
			}

			if(
				$this['yearStart'] > $this['yearStop'] or
				($this['yearStart'] === $this['yearStop'] and $this['weekStart'] >= $this['weekStop'])
			) {
				Flow::fail('weekStop.consistency');
			}

			foreach(['Start', 'Stop'] as $key) {

				switch($this['year'.$key]) {

					case -1 :
						if($this['week'.$key] < SequenceSetting::MIN_WEEK_MINUS_1) {
							Flow::fail('week'.$key.'.consistency0');
						}
						break;

					case 1 :
						if($this['week'.$key] > SequenceSetting::MIN_WEEK_PLUS_1) {
							Flow::fail('week'.$key.'.consistency2');
						}
						break;


				}

			}

		}

		if($this['frequency'] === NULL) {
			Flow::fail('frequency.empty');
		}

	}

	protected function buildSeason(array $properties, array $input): void {

		$this->expects([
			'sequence' => ['cycle']
		]);

		switch($this['sequence']['cycle']) {

			case Sequence::ANNUAL :
				$this['seasonOnly'] = NULL;
				$this['seasonStart'] = NULL;
				$this['seasonStop'] = NULL;
				break;

			case Sequence::PERENNIAL :

				$count = count(array_intersect($properties, ['seasonOnly', 'seasonStart', 'seasonStop']));

				if($count === 0) {
					return;
				} else if($count < 3) {
					throw new \Exception('Inseparable properties');
				}

				switch($input['season'] ?? NULL) {

					case 'only' :
						$this->buildSeasonOnly();
						break;

					case 'interval' :
						$this->buildSeasonInterval();
						break;

					default :
						Flow::fail('seasonOnly.empty');


				}

				break;

		}

	}

	protected function buildSeasonOnly() {

		if($this['seasonOnly'] === NULL) {
			Flow::fail('seasonOnly.empty');
			return;
		}

		$this['seasonStart'] = NULL;
		$this['seasonStop'] = NULL;

	}

	protected function buildSeasonInterval() {

		if($this['seasonStart'] === NULL) {
			Flow::fail('seasonStart.empty');
			return;
		}

		$this['seasonOnly'] = NULL;

		if($this['seasonStop'] !== NULL and $this['seasonStart'] >= $this['seasonStop']) {
			Flow::fail('seasonStop.consistency');
		}

	}

}
?>
