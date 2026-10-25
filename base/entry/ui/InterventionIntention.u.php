<?php
namespace entry;

/**
 * Ce que l'intervention dit à l'écran : ses actions, ses questions d'espèce et de série, que le temps pose de
 * même (`TimeIntentionUi`), puis son récapitulatif en puces, à la manière de la vente (`SaleIntentionUi`) :
 * l'état, le jour d'une intervention faite ou la semaine d'une intervention à faire, le temps passé ou prévu,
 * et la ligne qui écrit.
 *
 * Les mots que les puces de semaine posent en jeton ne se traduisent pas : la phrase se relit avec eux
 * (`WeekComplementLib`). « Cette semaine » se dit aussi en jeton, bien qu'elle soit la semaine par défaut : la
 * semaine dite garde l'intervention à faire, que la phrase sans elle rendrait faite (`getStatus()`).
 */
class InterventionIntentionUi extends IntentionUi {

	public static function getLabel(): string {
		return s("Nouvelle intervention");
	}

	// « Nouvelle » n'est pas un mot du verbe (`InterventionIntentionLib::getWords()`) : le jeton se relit sans lui.
	public static function getToken(): string {
		return s("Intervention");
	}

	public static function getChips(): array {
		return [self::getStatusGroup(), self::getDateGroup(), self::getWeekGroup(), self::getSpentGroup(), self::getExpectedGroup()];
	}

	private static function getStatusGroup(): string {
		return s("État");
	}

	private static function getDateGroup(): string {
		return s("Date");
	}

	private static function getWeekGroup(): string {
		return s("Semaine");
	}

	private static function getSpentGroup(): string {
		return s("Temps passé");
	}

	private static function getExpectedGroup(): string {
		return s("Temps prévu");
	}

	/**
	 * Les actions tant qu'aucune n'est nommée, les espèces de la saison sous l'action, les séries de l'espèce,
	 * puis le récapitulatif.
	 */
	public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, \Collection $cAction): array {

		if($reading['object']->empty()) {

			$results = [];

			foreach($cElement as $eTaskObject) {

				$results[] = [
					'group' => TimeIntentionUi::getQuestion(),
					'label' => $eTaskObject['name'],
					'value' => 'object:'.$eTaskObject['id'],
				];

			}

			return $results;

		}

		if($reading['question'] !== NULL) {

			return match($reading['question']['name']) {
				'plant' => TimeIntentionUi::getPlantResults($reading['object']['action'], $reading['question']['cPlant']),
				'series' => TimeIntentionUi::getSeriesResults($reading, $cAction, TimeIntentionUi::getSeriesPlaceholder()),
			};

		}

		if($reading['outcome'] === EntryLib::WRITE) {
			return self::getRecapResults($reading);
		}

		return [];

	}

	/**
	 * L'état, puis le jour d'une intervention faite ou la semaine d'une intervention à faire, le temps passé (quand
	 * la structure le suit) ou prévu, et la ligne qui écrit.
	 */
	private static function getRecapResults(array $reading): array {

		$confirmation = $reading['confirmation'];

		$results = self::getStatusResults($confirmation['status']);

		if($confirmation['status'] === \series\Task::DONE) {

			$results = array_merge($results, self::getDateResults($confirmation));

			if($confirmation['tracked']) {
				$results = array_merge($results, self::getDurationResults(self::getSpentGroup(), $confirmation['minutes']));
			}

		} else {

			$results = array_merge(
				$results,
				self::getWeekResults($confirmation),
				self::getDurationResults(self::getExpectedGroup(), $confirmation['expected'])
			);

		}

		$results[] = self::getWriteResult($reading);

		return $results;

	}

	private static function getStatusResults(string $statusSelected): array {

		$results = [];

		foreach([\series\Task::DONE, \series\Task::TODO] as $status) {

			$results[] = [
				'group' => self::getStatusGroup(),
				'label' => match($status) {
					\series\Task::DONE => s("Faite"),
					\series\Task::TODO => s("À faire"),
				},
				'value' => 'status:'.$status,
				'selected' => ($status === $statusSelected),
			];

		}

		return $results;

	}

	/**
	 * Aujourd'hui, hier, ou un autre jour, qui se tape avec le vocabulaire du champ et que la puce dit ensuite.
	 */
	private static function getDateResults(array $confirmation): array {

		return [
			[
				'group' => self::getDateGroup(),
				'label' => s("Aujourd'hui"),
				'value' => 'date:',
				'selected' => ($confirmation['day'] === 'today'),
			],
			[
				'group' => self::getDateGroup(),
				'label' => s("Hier"),
				'value' => 'date:'.DateComplementUi::getToken($confirmation['yesterday']),
				'selected' => ($confirmation['day'] === 'yesterday'),
			],
			[
				'group' => self::getDateGroup(),
				'label' => ($confirmation['day'] === 'other') ? DateComplementUi::getDate($confirmation['date']) : s("Autre jour"),
				'value' => 'dateOther',
				'selected' => ($confirmation['day'] === 'other'),
			],
		];

	}

	/**
	 * Cette semaine, la prochaine, ou une autre, qui se tape (« semaine 46 ») et que la puce dit ensuite.
	 */
	private static function getWeekResults(array $confirmation): array {

		return [
			[
				'group' => self::getWeekGroup(),
				'label' => s("Cette semaine"),
				'value' => 'week:'.WeekComplementLib::THIS.' '.WeekComplementLib::WORD,
				'selected' => ($confirmation['weekChoice'] === 'this'),
			],
			[
				'group' => self::getWeekGroup(),
				'label' => s("Semaine prochaine"),
				'value' => 'week:'.WeekComplementLib::WORD.' '.WeekComplementLib::NEXT[0],
				'selected' => ($confirmation['weekChoice'] === 'next'),
			],
			[
				'group' => self::getWeekGroup(),
				'label' => ($confirmation['weekChoice'] === 'other') ? WeekComplementUi::getWeek($confirmation['eTask']['plannedWeek']) : s("Autre semaine"),
				'value' => 'weekOther',
				'selected' => ($confirmation['weekChoice'] === 'other'),
			],
		];

	}

	/**
	 * Les durées courantes (`DurationComplementUi::getOptions()`) : la puce choisie se décoche, et toute autre
	 * durée se tape.
	 */
	private static function getDurationResults(string $group, int $minutesSelected): array {

		return array_map(fn(int $minutes) => [
			'group' => $group,
			'label' => DurationComplementUi::getDuration($minutes),
			'value' => 'duration:'.$minutes,
			'selected' => ($minutes === $minutesSelected),
		], DurationComplementUi::getOptions());

	}

	/**
	 * Sous le refus d'un jour ou d'une semaine qu'une intervention faite n'admet pas, l'état, qui la passe à
	 * faire d'un geste.
	 */
	public static function getRefusalResults(\structure\Structure $eStructure, array $reading): array {

		if(in_array($reading['refusal']['reason'], ['future', 'doneWeek'], TRUE)) {
			return self::getStatusResults(\series\Task::DONE);
		}

		return [];

	}

	public static function getAwaitedStep(): string {
		return 'duration';
	}

	public static function getPlaceholder(array $reading, string $step): string {

		return match($step) {
			'object' => TimeIntentionUi::getObjectPlaceholder(),
			'question' => match($reading['question']['name']) {
				'plant' => HarvestIntentionUi::getObjectPlaceholder(),
				'series' => TimeIntentionUi::getSeriesPlaceholder(),
			},
			'duration', 'write' => TimeIntentionUi::getDurationPlaceholder(),
		};

	}

	public static function getRefusal(array $reading): ?string {

		$refusal = $reading['refusal'];

		return match($refusal['reason']) {
			'plant' => s("Tapez le nom de l'espèce."),
			'unclaimed' => s("Un mot de la saisie n'est pas compris."),
			'future' => s("Une intervention faite ne se saisit pas à l'avance. Choisissez « À faire » pour la planifier."),
			'past' => s("Au-delà de {value} jours, une intervention faite se saisit depuis le planning.", $refusal['days']),
			'doneWeek' => s("Une intervention faite se date d'un jour, hier ou lundi par exemple."),
			'plannedPast' => s("Une intervention à faire se planifie à un jour à venir, ou à une semaine, semaine 46 par exemple."),
			default => NULL,
		};

	}

	public static function getObjectIcon(\Element $eElement): ?string {
		return NULL;
	}

	public static function getWriteLabel(array $reading): string {

		return match($reading['confirmation']['status']) {
			\series\Task::DONE => s("Ajouter l'intervention"),
			\series\Task::TODO => s("Planifier l'intervention"),
		};

	}

	/**
	 * « Faite aujourd'hui · 1 h », « Semaine 44 · 30 min », « Semaine 44 · mardi 28 octobre » : le jour ou la
	 * semaine, puis le temps quand il est dit. L'action, l'espèce et la série sont dans le champ.
	 */
	public static function getWriteDetail(array $reading): string {

		$confirmation = $reading['confirmation'];
		$eTask = $confirmation['eTask'];

		if($confirmation['status'] === \series\Task::DONE) {
			$parts = [ucfirst(DoneIntentionUi::getDoneDay($eTask))];
			$minutes = $confirmation['minutes'];
		} else {

			$parts = [WeekComplementUi::getWeek($eTask['plannedWeek'])];

			if($eTask['plannedDate'] !== NULL) {
				$parts[] = DateComplementUi::getDate($eTask['plannedDate']);
			}

			$minutes = $confirmation['expected'];

		}

		if($minutes > 0) {
			$parts[] = DurationComplementUi::getDuration($minutes);
		}

		return implode(' · ', $parts);

	}

	public static function getWriteToast(array $reading): array {

		$confirmation = $reading['confirmation'];
		$eTask = $confirmation['eTask'];

		$toast = match($confirmation['status']) {
			\series\Task::DONE => [
				'fqn' => 'Entry::done',
				'options' => [
					'date' => $confirmation['date'],
					'minutes' => ($confirmation['minutes'] > 0) ? $confirmation['minutes'] : NULL,
					'count' => 1,
				],
			],
			\series\Task::TODO => [
				'fqn' => 'Entry::planned',
				'options' => [
					'week' => $eTask['plannedWeek'],
				],
			],
		};

		return $toast + [
			'package' => 'entry',
			'icon' => self::getPlantIcon($eTask['plant']),
			'detail' => encode($eTask['action']['name'].' '.mb_strtolower($eTask['plant']['name']).' · '.TimeIntentionUi::getAddedDetail($eTask, s("ajoutée au planning"))),
		];

	}

	public static function getWriteEvent(\structure\Structure $eStructure, array $reading): array {
		return EntryUi::getEvent($eStructure, $reading['confirmation']['eTask'], $reading['confirmation']['date']);
	}

	public static function getCancelToast(array $cancellation): array {

		return [
			'package' => 'entry',
			'fqn' => 'Entry::doneRemoved',
			'options' => [
				'count' => 1,
			],
			'icon' => self::getPlantIcon($cancellation['eTask']['plant']),
		];

	}

	public static function getCancelEvent(\structure\Structure $eStructure, array $cancellation): array {
		return EntryUi::getEvent($eStructure, $cancellation['eTask'], $cancellation['date']);
	}

	/**
	 * « Intervention planifiée en semaine 44 », titre de la pastille d'une intervention à faire.
	 */
	public static function getPlanned(array $options): string {
		return s("Intervention planifiée en semaine\u{00A0}{value}", week_number($options['week']));
	}

}
?>
