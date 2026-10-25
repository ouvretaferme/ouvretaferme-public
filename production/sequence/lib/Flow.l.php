<?php
namespace sequence;

class FlowLib extends FlowCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['action', 'methods', 'crop', 'description', 'fertilizer', 'seasonOnly', 'seasonStart', 'seasonStop', 'weekOnly', 'yearOnly', 'weekStart', 'yearStart', 'weekStop', 'yearStop', 'frequency', 'products', 'tools'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate();
	}

	public static function synchronizeProduct(\work\Tool $eTool): void {

		$eTool->expects(['id', 'structure', 'routineName']);

		self::moveTool($eTool, $eTool);

	}

	public static function mergeProduct(\work\Tool $eTool, \work\Tool $eToolProduct): void {

		$eTool->expects(['id', 'structure']);

		self::moveTool($eTool, $eToolProduct);

	}

	private static function moveTool(\work\Tool $eToolFrom, \work\Tool $eToolTo): void {

		$cFlow = Flow::model()
			->select(['id', 'tools', 'products', 'action' => ['fqn']])
			->whereStructure($eToolFrom['structure'])
			->where('JSON_CONTAINS(tools, \''.$eToolFrom['id'].'\') OR JSON_CONTAINS(products, \''.$eToolFrom['id'].'\')')
			->getCollection();

		foreach($cFlow as $eFlow) {

			$eToolTo->applyMove($eFlow, $eToolFrom);

			Flow::model()
				->select(['tools', 'products'])
				->update($eFlow);

		}

	}

	public static function getBySequence(Sequence $eSequence, ?int $season = NULL, array $properties = []): \Collection {

		if($season !== NULL) {

			Flow::model()->where('
				seasonOnly = '.$season.' OR
				'.$season.' BETWEEN seasonStart AND seasonStop OR
				('.$season.' >= seasonStart AND seasonStop IS NULL)
			');

		}

		return Flow::model()
			->select(Flow::getSelection())
			->select($properties)
			->sort([
				new \Sql('IF(weekOnly IS NOT NULL, CAST(weekOnly AS SIGNED) + CAST(yearOnly AS SIGNED) * 100, CAST(weekStart AS SIGNED) + CAST(yearStart AS SIGNED) * 100)'),
				'id' => SORT_ASC
			])
			->whereSequence($eSequence)
			->getCollection();

	}

	public static function getByCrops(\Collection $cCrop, ?int $season, array $properties = []): \Collection {

		if($season !== NULL) {

			Flow::model()->where('
				seasonOnly = '.$season.' OR
				'.$season.' BETWEEN seasonStart AND seasonStop OR
				('.$season.' >= seasonStart AND seasonStop IS NULL)
			');

		}

		return Flow::model()
			->select(Flow::getSelection())
			->select($properties)
			->whereCrop('IN', $cCrop)
			->sort([
				'weekStart' => SORT_ASC,
				'id' => SORT_ASC
			])
			->getCollection();

	}

	public static function hasCropActions(Crop $eCrop, \Collection $cAction): bool {

		return Flow::model()
			->whereCrop($eCrop)
			->whereAction('IN', $cAction)
			->exists();

	}

	/**
	 * Pas nécessaire de recalculer les semaines de démarrage étant donné que ces champs sont dupliqués tel quel
	 */
	public static function duplicateFromSequence(Sequence $eSequence, Sequence $eSequenceNew, \Collection $cCrop): \Collection {

		$cFlow = Flow::model()
			->select(Flow::model()->getProperties())
			->whereSequence($eSequence)
			->getCollection(NULL, NULL, 'id');

		// Copie des tâches
		foreach($cFlow as $eFlow) {

			$eFlow['id'] = NULL;
			$eFlow['sequence'] = $eSequenceNew;

			if($eFlow['crop']->notEmpty()) {
				$eFlow['crop'] = $cCrop[$eFlow['crop']['id']];
			}

			Flow::model()->insert($eFlow);

		}

		return $cFlow;

	}

	public static function changeWeekStart(\Collection $cFlow, int $startWeek): void {

		if($cFlow->empty()) {
			return;
		}

		$weekDifference = ($cFlow->first()['weekOnly'] ?? $cFlow->first()['weekStart']) - $startWeek;

		foreach($cFlow as $eFlow) {

			if($eFlow['weekOnly']) {

				$eFlow['weekOnly'] -= $weekDifference;
				self::moveWeek($eFlow, 'weekOnly', 'yearOnly');

			} else {

				$eFlow['weekStart'] -= $weekDifference;
				self::moveWeek($eFlow, 'weekStart', 'yearStart');

				$eFlow['weekStop'] -= $weekDifference;
				self::moveWeek($eFlow, 'weekStop', 'yearStop');

			}

		}

	}

	private static function moveWeek(Flow $eFlow, string $weekProperty, string $yearProperty): void {

		if($eFlow[$weekProperty] < 1) {
			$eFlow[$weekProperty] += 52;
			$eFlow[$yearProperty]--;
		} else if($eFlow[$weekProperty] > 52) {
			$eFlow[$weekProperty] -= 52;
			$eFlow[$yearProperty]++;
		}

	}

	public static function reorder(Sequence $eSequence, \Collection $cFlow): array {

		$eSequence->expects(['cycle']);

		if($cFlow->empty()) {
			return [];
		}

		$events = [];

		foreach($cFlow as $eFlow) {

			if($eSequence['cycle'] === Sequence::PERENNIAL) {

				if($eFlow['seasonOnly'] !== NULL) {
					$seasons = [$eFlow['seasonOnly']];
				} else {
					$seasons = [];
					$lastSeason = $eFlow['seasonStop'] ?? SequenceSetting::MAX_SEASON_STOP;
					for($season = $eFlow['seasonStart']; $season <= $lastSeason; $season++) {
						$seasons[] = $season;
					}
				}

			} else {
				$seasons = [0];
			}

			$endless = ($eFlow['seasonStart'] !== NULL and $eFlow['seasonStop'] === NULL);

			foreach($seasons as $season) {

				if($eSequence['cycle'] === Sequence::PERENNIAL) {
					$year = 0;
				} else {
					$year = NULL; // Sera écrasé par la bonne valeur
				}

				if($eFlow['weekOnly'] !== NULL) {

					$events[$season][$year ?? $eFlow['yearOnly']][$eFlow['weekOnly']][] = [
						'position' => $eFlow['positionOnly'],
						'field' => 'only',
						'flow' => $eFlow,
						'endless' => $endless
					];

				} else {

					$events[$season][$year ?? $eFlow['yearStart']][$eFlow['weekStart']][] = [
						'position' => $eFlow['positionStart'],
						'field' => 'start',
						'flow' => $eFlow,
						'endless' => $endless
					];

					$events[$season][$year ?? $eFlow['yearStop']][$eFlow['weekStop']][] = [
						'position' => $eFlow['positionStop'],
						'field' => 'stop',
						'flow' => $eFlow,
						'endless' => $endless
					];

				}

			}

		}


		foreach($events as $season => $years) {

			foreach($years as $year => $weeks) {

				foreach($weeks as $week => $flows) {

					uasort($events[$season][$year][$week], function($a, $b) {

						if($b['position'] === NULL) {
							return -1;
						} else if($a['position'] === NULL) {
							return 1;
						} else {
							return ($a['position'] < $b['position']) ? -1 : 1;
						}

					});

				}

				ksort($events[$season][$year]);

			}

			ksort($events[$season]);

		}

		ksort($events);

		return $events;

	}

	public static function create(Flow $e): void {

		$e->expects([
			'crop',
			'sequence' => ['structure', 'cycle']
		]);

		Flow::model()->beginTransaction();

		$e['structure'] = $e['sequence']['structure'];

		if($e['crop']->notEmpty()) {

			$e['crop']->expects(['plant']);
			$e['plant'] = $e['crop']['plant'];

		}

		Flow::model()->insert($e);

		SequenceLib::recalculate($e['structure'], $e['sequence']);

		Flow::model()->commit();

	}

	public static function update(Flow $e, array $properties): void {

		Flow::model()->beginTransaction();

		if(in_array('crop', $properties)) {

			if($e['crop']->notEmpty()) {

				$e['crop']->expects(['plant']);
				$e['plant'] = $e['crop']['plant'];

			} else {
				$e['plant'] = new \plant\Plant();
			}

			$properties[] = 'plant';

		}

		parent::update($e, $properties);

		if(array_intersect(['weekOnly', 'weekStart', 'weekStop'], $properties)) {
			SequenceLib::recalculate($e['structure'], $e['sequence']);
		}

		Flow::model()->commit();

	}

	public static function updatePosition(Sequence $eSequence, array $positions): void {

		foreach($positions as $position => [$flow, $field]) {

			$update = [
				'only' => 'positionOnly',
				'start' => 'positionStart',
				'stop' => 'positionStop',
			][$field];

			Flow::model()
				->whereId($flow)
				->whereSequence($eSequence)
				->update([
					$update => $position
				]);

		}

	}
	public static function incrementWeekCollection(\Collection $c, int $increment): void {

		// Traitement en PHP, impossible à faire en une seule requête
		foreach($c as $e) {
			self::incrementWeek($e, $increment);
		}

	}


	public static function incrementWeek(Flow $e, int $increment): bool {

		if($increment < -26 or $increment > 26) {
			return FALSE;
		}

		$e->expects([
			'sequence' => ['cycle'],
			'weekOnly', 'weekStart', 'weekStop',
			'yearOnly', 'yearStart', 'yearStop',
		]);

		foreach(['Only', 'Start', 'Stop'] as $property) {

			if($e['week'.$property] === NULL) {
				continue;
			}

			if(self::shiftWeek($e, $property, $increment) === FALSE) {
				return FALSE;
			}

		}

		Flow::model()
			->select([
				'weekOnly', 'weekStart', 'weekStop',
				'yearOnly', 'yearStart', 'yearStop'
			])
			->update($e);

		SequenceLib::recalculate($e['structure'], $e['sequence']);

		return TRUE;

	}

	/**
	 * Déplace une seule borne d'une période, sans toucher à l'autre.
	 *
	 * C'est le geste des poignées : étendre ou raccourcir un trait par un bout.
	 * Les bornes ne peuvent pas se croiser ni se rejoindre, une période durant au
	 * moins deux semaines (cf. Flow::buildPeriodInterval()).
	 *
	 * Étirer une intervention ponctuelle la transforme en période : c'est le seul
	 * moyen d'allonger une récolte d'une seule semaine sans passer par le
	 * formulaire.
	 */
	public static function incrementWeekBound(Flow $e, string $bound, int $increment): bool {

		if($increment < -26 or $increment > 26) {
			return FALSE;
		}

		$e->expects([
			'sequence' => ['cycle'],
			'weekOnly', 'weekStart', 'weekStop',
			'yearOnly', 'yearStart', 'yearStop',
			'positionOnly', 'positionStart', 'positionStop',
			'frequency',
		]);

		if($e['weekOnly'] !== NULL) {

			$e['weekStart'] = $e['weekOnly'];
			$e['weekStop'] = $e['weekOnly'];
			$e['yearStart'] = $e['yearOnly'];
			$e['yearStop'] = $e['yearOnly'];
			$e['positionStart'] = $e['positionOnly'];
			$e['positionStop'] = $e['positionOnly'];

			$e['weekOnly'] = NULL;
			$e['yearOnly'] = NULL;
			$e['positionOnly'] = NULL;

			// Une période a toujours une fréquence, une intervention ponctuelle non.
			if($e['frequency'] === NULL) {
				$e['frequency'] = Flow::W1;
			}

		}

		if($e['weekStart'] === NULL or $e['weekStop'] === NULL) {
			return FALSE;
		}

		$property = match($bound) {
			'start' => 'Start',
			'stop' => 'Stop'
		};

		if(self::shiftWeek($e, $property, $increment) === FALSE) {
			return FALSE;
		}

		if($e['sequence']['cycle'] === Sequence::PERENNIAL) {
			$crossed = ($e['weekStart'] >= $e['weekStop']);
		} else {
			$crossed = (
				$e['yearStart'] > $e['yearStop'] or
				($e['yearStart'] === $e['yearStop'] and $e['weekStart'] >= $e['weekStop'])
			);
		}

		if($crossed) {
			Flow::fail('weekStop.consistency');
			return FALSE;
		}

		Flow::model()
			->select([
				'weekOnly', 'weekStart', 'weekStop',
				'yearOnly', 'yearStart', 'yearStop',
				'positionOnly', 'positionStart', 'positionStop',
				'frequency'
			])
			->update($e);

		SequenceLib::recalculate($e['structure'], $e['sequence']);

		return TRUE;

	}

	private static function shiftWeek(Flow $e, string $property, int $increment): bool {

		$propertyWeek = 'week'.$property;
		$propertyYear = 'year'.$property;

		$e[$propertyWeek] += $increment;

		if($e[$propertyWeek] < 1) {

			if($e['sequence']['cycle'] === Sequence::PERENNIAL) {
				Flow::fail('weekTooSoonPerennial');
				return FALSE;
			} else {

				$e[$propertyWeek] += 52;
				$e[$propertyYear]--;

			}

		} else if($e[$propertyWeek] > 52) {

			if($e['sequence']['cycle'] === Sequence::PERENNIAL) {
				Flow::fail('weekTooLatePerennial');
				return FALSE;
			} else {

				$e[$propertyWeek] -= 52;
				$e[$propertyYear]++;

			}

		}

		if($e['sequence']['cycle'] === Sequence::ANNUAL) {

			if(
				$e[$propertyYear] < -1 or
				($e[$propertyYear] === -1 and $e[$propertyWeek] < SequenceSetting::MIN_WEEK_MINUS_1)
			) {
				Flow::fail('weekTooSoonAnnual');
				return FALSE;
			}

			if(
				$e[$propertyYear] > 1 or
				($e[$propertyYear] === 1 and $e[$propertyWeek] > SequenceSetting::MIN_WEEK_PLUS_1)
			) {
				Flow::fail('weekTooLateAnnual');
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Décale une intervention et tout ce qui la suit dans l'itinéraire.
	 *
	 * « Ce qui suit », c'est l'ordre du diagramme : la saison, puis l'année, puis
	 * la semaine. Une intervention qui tombe la même semaine que celle qu'on
	 * déplace suit aussi — sinon décaler un semis laisserait sur place la
	 * fertilisation qui l'accompagne.
	 */
	public static function incrementWeekFollowing(Flow $e, int $increment): void {

		$e->expects(['sequence', 'structure']);

		$rank = fn(Flow $eFlow) => [
			$eFlow['seasonOnly'] ?? $eFlow['seasonStart'] ?? 0,
			$eFlow['yearOnly'] ?? $eFlow['yearStart'] ?? 0,
			$eFlow['weekOnly'] ?? $eFlow['weekStart'] ?? 0
		];

		$from = $rank($e);

		// incrementWeek() a besoin du cycle de l'itinéraire pour savoir si une
		// semaine peut déborder sur l'année suivante : la sélection par défaut ne
		// le porte pas.
		$cFlow = Flow::model()
			->select(Flow::getSelection() + ['sequence' => ['cycle']])
			->whereSequence($e['sequence'])
			->getCollection();

		foreach($cFlow as $eFlowFollowing) {

			if($rank($eFlowFollowing) >= $from) {
				self::incrementWeek($eFlowFollowing, $increment);
			}

		}

	}

	/**
	 * Conçu pour supprimer des interventions issus du même itinéraire technique
	 */
	public static function deleteCollection(\Collection $c): void {

		Flow::model()->beginTransaction();

			Flow::model()->delete($c);

			SequenceLib::recalculate($c->first()['structure'], $c->first()['sequence']);

		Flow::model()->commit();

	}

	public static function delete(Flow $e): void {

		$e->expects(['id', 'sequence', 'structure']);

		Flow::model()->beginTransaction();

			Flow::model()->delete($e);

			SequenceLib::recalculate($e['structure'], $e['sequence']);

		Flow::model()->commit();

	}

}
?>
