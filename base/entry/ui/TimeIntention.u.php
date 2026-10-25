<?php
namespace entry;

/**
 * Ce que le temps seul dit à l'écran : son jeton, ses interventions, sa question, sa confirmation et sa
 * pastille. Comme pour la récolte, la liste pose ses libellés en `textContent`, et la pastille
 * (`getSuccess()`, `getCancelled()`, `getToastDetail()`) en `innerHTML` : ce que la structure a nommé y
 * est encodé ici.
 *
 * Le nom d'une action ne dit pas son genre : aucune phrase ne l'accorde, il vient en tête de ligne, dans
 * le détail, ou cité entre guillemets, jamais pris dans la phrase.
 */
class TimeIntentionUi extends IntentionUi {

	public static function getLabel(): string {
		return s("Temps de travail");
	}

	// « Temps de travail » laisserait « travail » à un mot que personne ne lit.
	public static function getToken(): string {
		return s("Temps");
	}

	public static function getChips(): array {
		return [self::getDayGroup()];
	}

	public static function getDays(array $reading): ?array {

		if($reading['outcome'] === EntryLib::WRITE) {
			return $reading['confirmation']['dates'];
		}

		// Avant la durée, les puces disent déjà les jours retenus.
		if($reading['days'] !== []) {
			return array_column(array_filter($reading['days'], fn(array $day) => $day['selected']), 'date');
		}

		return NULL;

	}

	private static function getDayGroup(): string {
		return s("Jours");
	}

	/**
	 * Les interventions de la semaine tant qu'aucune n'est nommée, et les actions que la frappe commence, une ligne
	 * par intervention quand plusieurs répondent au nom, les espèces de la saison sous une action seule, ou une
	 * ligne par série où ajouter celle qui manque, puis les jours de la semaine en puces, sous la ligne qui écrit
	 * une fois la durée dite. Sous l'écriture, sous la question de série et sous le refus (`getRefusalResults()`),
	 * la coche de la même phrase quand elle a quelque chose à écrire (`$reading['discarded']['done']`, lot S4c) : «
	 * Ajouter 2 h et marquer comme faite ». La coche n'écrit que le jour dit : elle se retire dès qu'une puce en
	 * ajoute un autre, sans quoi elle laisserait les autres de côté.
	 */
	public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, \Collection $cAction): array {

		$results = [];

		if($reading['object']->empty()) {

			// Une action seule n'arrive que commencée par la frappe (`TimeIntentionLib::getShown()`) : elle n'est pas
			// une intervention de la semaine.
			foreach($cElement as $eTaskObject) {

				if($eTaskObject['listed'] === FALSE) {
					$group = self::getQuestion();
				} else if($eTaskObject['plannedWeekLate'] === NULL) {
					$group = self::getObjectGroup($reading['date']);
				} else {
					$group = self::getLateGroup();
				}

				$result = [
					'group' => $group,
					'label' => $eTaskObject['name'],
					'value' => 'object:'.$eTaskObject['id'],
				];

				if($eTaskObject['plannedWeekLate'] !== NULL) {
					$result['sub'] = encode(self::getPlannedWeek($eTaskObject['plannedWeekLate']));
				}

				if($eTaskObject['plant']->notEmpty() and $eTaskObject['plant']['fqn'] !== NULL) {
					$result['icon'] = \plant\PlantUi::getVignetteHref($eTaskObject['plant']['fqn']);
				}

				$results[] = $result;

			}

			return $results;

		}

		if($reading['question'] !== NULL and $reading['question']['name'] === 'series') {
			return array_merge(self::getSeriesResults($reading, $cAction, self::getSeriesQuestion()), self::getDoneResults($reading));
		}

		if($reading['question'] !== NULL and $reading['question']['name'] === 'plant') {
			return self::getPlantResults($reading['object']['action'], $reading['question']['cPlant']);
		}

		if($reading['question'] !== NULL) {

			foreach($reading['question']['cTask'] as $eTask) {

				$result = [
					'group' => self::getQuestion(),
					'label' => self::getTaskLabel($eTask),
					'value' => 'task:'.$eTask['id'],
					'sub' => encode(self::getTaskSub($eTask)),
				];

				$detail = self::getTaskDetail($eTask, $reading['date']);

				if($detail !== NULL) {
					$result['detail'] = $detail;
				}

				$results[] = $result;

			}

			return $results;

		}

		if($reading['outcome'] === EntryLib::WRITE) {

			$results[] = self::getWriteResult($reading);
			$results = array_merge($results, self::getDayResults($reading['days']));

			// La coche écrit le jour dit, seul : elle ne se propose que si le temps va sur lui seul, pas sur une puce
			// qui a remplacé aujourd'hui.
			if($reading['confirmation']['dates'] === [$reading['date']]) {
				$results = array_merge($results, self::getDoneResults($reading));
			}

			return $results;

		}

		return self::getDayResults($reading['days']);

	}

	/**
	 * Les jours de la semaine en puces, sous la ligne qui écrit (`TimeIntentionLib::getDays()`). Une puce pose
	 * son jour en jeton, et les jetons s'ajoutent au jour dit.
	 */
	private static function getDayResults(array $days): array {

		$group = self::getDayGroup();

		return array_map(fn(array $day) => [
			'group' => $group,
			'label' => DateComplementUi::getChip($day['date']),
			'value' => 'day:'.$day['date'],
			'selected' => $day['selected'],
		], $days);

	}

	/**
	 * Sous le refus du temps aussi : « hier » un lundi tombe une autre semaine, où le temps ne trouve pas
	 * l'intervention que la coche vise encore.
	 */
	public static function getRefusalResults(\structure\Structure $eStructure, array $reading): array {
		return self::getDoneResults($reading);
	}

	/**
	 * La coche de la même phrase, que la lecture a écartée avec quelque chose à écrire.
	 */
	private static function getDoneResults(array $reading): array {

		$readingDone = $reading['discarded']['done'];

		return ($readingDone === NULL) ? [] : [DoneIntentionUi::getTimedResult($readingDone)];

	}

	/**
	 * Les espèces de la saison où l'action seule peut s'ajouter au planning, celles qu'une intervention de
	 * l'action vise déjà dans la saison en tête (`acted`), sous un seul groupe quand aucune ne l'est.
	 */
	public static function getPlantResults(\work\Action $eAction, \Collection $cPlant): array {

		$acted = $cPlant->find(fn(\plant\Plant $ePlant) => $ePlant['acted'])->notEmpty();

		$results = [];

		foreach([TRUE, FALSE] as $actedGroup) {

			foreach($cPlant->find(fn(\plant\Plant $ePlant) => $ePlant['acted'] === $actedGroup) as $ePlant) {

				$result = [
					'group' => $acted ? self::getPlantGroup($eAction, $actedGroup) : self::getSeasonPlantGroup(),
					'label' => $ePlant['name'],
					'value' => 'plant:'.$ePlant['id'],
				];

				if($ePlant['fqn'] !== NULL) {
					$result['icon'] = \plant\PlantUi::getVignetteHref($ePlant['fqn']);
				}

				$results[] = $result;

			}

		}

		return $results;

	}

	private static function getPlantGroup(\work\Action $eAction, bool $acted): string {

		return $acted ?
			s("{value} cette saison", $eAction['name']) :
			s("Autres espèces de la saison");

	}

	private static function getSeasonPlantGroup(): string {
		return s("Espèces de la saison");
	}

	/**
	 * La question d'une intervention à ajouter au planning : une ligne par culture de l'espèce, comme celles
	 * d'une récolte, puis « Hors série » en dernier. La coche pose la même quand rien n'est prévu, sous son
	 * propre titre (`DoneIntentionUi`).
	 */
	public static function getSeriesResults(array $reading, \Collection $cAction, string $group): array {

		$ePlant = $reading['question']['ePlant'];
		$icon = self::getPlantIcon($ePlant);

		$results = [];

		foreach($reading['question']['cCultivation'] as $eCultivation) {

			$result = [
				'group' => $group,
				'label' => $eCultivation['series']['name'],
				'value' => 'addCultivation:'.$eCultivation['id'],
			];

			$badge = \sequence\CropUi::start($eCultivation, $cAction);

			if($badge !== '') {
				$result['badge'] = $badge;
			}

			if($icon !== NULL) {
				$result['icon'] = $icon;
			}

			// La même seconde ligne que la question de la récolte : deux séries d'un même nom se départagent
			// par leur saison, leur démarrage et leurs lieux.
			$sub = HarvestIntentionUi::getCultivationSub($eCultivation, new \series\Task());

			if($sub !== '') {
				$result['sub'] = $sub;
			}

			$results[] = $result;

		}

		$results[] = [
			'group' => $group,
			'label' => self::getNoSeries(),
			'value' => 'addNoSeries:'.$ePlant['id'],
		];

		return $results;

	}

	public static function getAwaitedStep(): string {
		return 'duration';
	}

	public static function getAwaited(array $reading): ?string {
		return self::getDurationPlaceholder();
	}

	public static function getPlaceholder(array $reading, string $step): string {

		return match($step) {
			'object' => self::getObjectPlaceholder(),
			'question' => match($reading['question']['name']) {
				'series' => self::getSeriesPlaceholder(),
				'plant' => HarvestIntentionUi::getObjectPlaceholder(),
				'task' => self::getQuestion(),
			},
			'duration', 'write' => self::getDurationPlaceholder(),
		};

	}

	public static function getRefusal(array $reading): ?string {

		$refusal = $reading['refusal'];

		return match($refusal['reason']) {
			'none' => self::getNoneRefusal($refusal['name'], $reading['date']),
			'unclaimed' => self::getUnclaimed($reading['complements']['duration'] !== NULL),
			'future' => self::getFutureRefusal(),
			'past' => self::getPastRefusal($refusal['days']),
			'dated' => self::getDatedRefusal($refusal['object']),
			default => NULL,
		};

	}

	/**
	 * Une intervention se montre par l'espèce qu'elle porte.
	 */
	public static function getObjectIcon(\Element $eElement): ?string {
		return self::getPlantIcon($eElement['plant']);
	}

	public static function getWriteLabel(array $reading): string {
		return self::getConfirmation($reading['confirmation']);
	}

	public static function getWriteDetail(array $reading): string {
		return self::getConfirmationDetail($reading['object'], $reading['task']);
	}

	public static function getWriteToast(array $reading): array {

		$confirmation = $reading['confirmation'];

		return [
			'package' => 'entry',
			'fqn' => 'Entry::timed',
			'options' => [
				'minutes' => $confirmation['minutes'],
				'dates' => $confirmation['dates'],
			],
			'icon' => self::getPlantIcon($confirmation['eTask']['plant']),
			'detail' => self::getToastDetail($reading['object'], $confirmation['eTask'], $confirmation['created']),
		];

	}

	public static function getWriteEvent(\structure\Structure $eStructure, array $reading): array {
		return EntryUi::getEvent($eStructure, $reading['confirmation']['eTask'], $reading['confirmation']['date']);
	}

	public static function getCancelToast(array $cancellation): array {

		return [
			'package' => 'entry',
			'fqn' => 'Entry::timeCancelled',
			'options' => [
				'minutes' => $cancellation['minutes'],
				'dates' => $cancellation['dates'],
			],
			'icon' => self::getPlantIcon($cancellation['eTask']['plant']),
		];

	}

	public static function getCancelEvent(\structure\Structure $eStructure, array $cancellation): array {
		return EntryUi::getEvent($eStructure, $cancellation['eTask'], $cancellation['date']);
	}

	public static function getObjectPlaceholder(): string {
		return s("Quelle intervention ?");
	}

	/**
	 * Le groupe des interventions dans la liste : celles de la semaine du jour dit, que le groupe nomme
	 * quand ce n'est pas celle d'aujourd'hui.
	 */
	public static function getObjectGroup(string $date): string {

		return (toWeek($date) === \Clock::week()) ?
			s("Interventions de la semaine") :
			s("Interventions de la semaine {value}", week_number(toWeek($date)));

	}

	/**
	 * Le groupe des interventions restées à faire des semaines passées, sous celles de la semaine, nommé comme
	 * la section du planning qui les montre (`\series\PlanningUi`).
	 */
	public static function getLateGroup(): string {
		return s("Retardé");
	}

	public static function getPlannedWeek(string $week): string {
		return s("prévue en semaine\u{00A0}{value}", week_number($week));
	}

	public static function getDurationPlaceholder(): string {
		return s("Combien de temps ?");
	}

	public static function getQuestion(): string {
		return s("Quelle intervention ?");
	}

	/**
	 * Le titre des séries où ajouter l'intervention que la semaine n'a pas. L'action et l'espèce sont
	 * déjà dans le champ, juste au-dessus.
	 */
	public static function getSeriesQuestion(): string {
		return s("Ajouter au planning");
	}

	public static function getSeriesPlaceholder(): string {
		return s("Sur quelle série ?");
	}

	public static function getNoSeries(): string {
		return s("Hors série");
	}

	/**
	 * Une ligne de la question : la série, sinon la description, sinon l'action. Toutes les lignes d'une
	 * question partagent l'action et l'espèce, que le jeton montre déjà.
	 */
	public static function getTaskLabel(\series\Task $eTask): string {

		if($eTask['series']->notEmpty()) {
			return $eTask['series']['name'];
		}

		return $eTask['description'] ?? $eTask['action']['name'];

	}

	/**
	 * Sa seconde ligne, en texte brut : son état, puis sa description quand la série la nomme déjà, ce qui
	 * départage deux interventions de la même série. La semaine ne s'y dit que d'une intervention en retard,
	 * les autres étant de la semaine du jour dit.
	 */
	public static function getTaskSub(\series\Task $eTask): string {

		$status = match($eTask['status']) {
			\series\Task::TODO => s("à faire"),
			\series\Task::DONE => s("faite"),
		};

		if($eTask['late']) {
			$status .= ', '.self::getPlannedWeek($eTask['plannedWeek']);
		}

		if($eTask['series']->notEmpty() and $eTask['description'] !== NULL) {
			return $status.' · '.$eTask['description'];
		}

		return $status;

	}

	/**
	 * Son détail : le temps que la personne y a déjà ce jour-là, rien sans temps.
	 */
	public static function getTaskDetail(\series\Task $eTask, string $date): ?string {

		if($eTask['timeDay'] <= 0.0) {
			return NULL;
		}

		$duration = DurationComplementUi::getDuration((int)round($eTask['timeDay'] * 60));
		$day = DateComplementUi::getDay($date);

		return ($day === NULL) ?
			s("{value} aujourd'hui", $duration) :
			s("{value} {day}", ['value' => $duration, 'day' => $day]);

	}

	/**
	 * La ligne de confirmation, « Ajouter 2 h », et le jour quand ce n'est pas aujourd'hui, « Ajouter 2 h
	 * hier », ou les jours, avec ce temps sur chacun, « Ajouter 2 h par jour lundi 20 et aujourd'hui ».
	 * L'intervention se dit dans le détail (`getConfirmationDetail()`).
	 */
	public static function getConfirmation(array $confirmation): string {

		$values = [
			'duration' => DurationComplementUi::getDuration($confirmation['minutes']),
			'day' => DateComplementUi::getDays($confirmation['dates']),
		];

		if($values['day'] === NULL) {
			return s("Ajouter {duration}", $values);
		} else if(count($confirmation['dates']) > 1) {
			return s("Ajouter {duration} par jour {day}", $values);
		} else {
			return s("Ajouter {duration} {day}", $values);
		}

	}

	/**
	 * « Désherbage carotte · FREIJA 01 · à faire » : l'intervention qui reçoit le temps, en texte brut.
	 * Celle qu'un temps ajoute au planning se dit par sa série, ou « Hors série », sans l'action ni
	 * l'espèce que le champ montre déjà en jetons.
	 */
	public static function getConfirmationDetail(\series\Task $eTaskObject, \series\Task $eTask): string {

		if($eTask->exists() === FALSE) {
			return self::getAddedDetail($eTask, s("à ajouter au planning"));
		}

		$parts = [self::getObjectName($eTaskObject, $eTask)];

		$label = self::getTaskLabel($eTask);

		if($label !== $eTaskObject['name'] and $label !== $eTask['action']['name']) {
			$parts[] = $label;
		}

		$parts[] = self::getTaskSub($eTask);

		return implode(' · ', $parts);

	}

	/**
	 * La seconde ligne de la pastille, en HTML. `$created` dit que l'écriture a ajouté l'intervention au
	 * planning.
	 */
	public static function getToastDetail(\series\Task $eTaskObject, \series\Task $eTask, bool $created): string {

		return encode($created ?
			self::getObjectName($eTaskObject, $eTask).' · '.self::getAddedDetail($eTask, s("ajoutée au planning")) :
			self::getConfirmationDetail($eTaskObject, $eTask));

	}

	/**
	 * Une action nommée seule ne dit pas l'espèce, que dit alors l'intervention.
	 */
	private static function getObjectName(\series\Task $eTaskObject, \series\Task $eTask): string {

		return ($eTaskObject['plant']->empty() and $eTask['plant']->notEmpty()) ?
			$eTaskObject['name'].' '.mb_strtolower($eTask['plant']['name']) :
			$eTaskObject['name'];

	}

	public static function getAddedDetail(\series\Task $eTask, string $state): string {

		$parts = [];

		if($eTask['series']->empty()) {
			$parts[] = self::getNoSeries();
		} else {

			$parts[] = $eTask['series']['name'];

			if($eTask['nameWithSeason']) {
				$parts[] = SeasonComplementUi::getSeason($eTask['series']['season']);
			}

		}

		$parts[] = $state;

		return implode(' · ', $parts);

	}

	/**
	 * « 2 h de travail ajoutées », titre de la pastille, et le jour quand ce n'est pas aujourd'hui : « 2 h de
	 * travail ajoutées hier », ou les jours, « 2 h de travail ajoutées par jour lundi 20 et aujourd'hui ».
	 */
	public static function getSuccess(array $options): string {

		$minutes = $options['minutes'];

		$values = [
			'duration' => DurationComplementUi::getDuration($minutes),
		];

		$added = DurationComplementUi::isSingular($minutes) ?
			s("{duration} de travail ajoutée", $values) :
			s("{duration} de travail ajoutées", $values);

		$day = DateComplementUi::getDays($options['dates']);

		if($day === NULL) {
			return $added;
		} else if(count($options['dates']) > 1) {
			return s("{added} par jour {day}", ['added' => $added, 'day' => encode($day)]);
		} else {
			return s("{added} {day}", ['added' => $added, 'day' => encode($day)]);
		}

	}

	/**
	 * « 2 h de travail annulées », et son jour quand ce n'est pas aujourd'hui, de la même forme que
	 * `getSuccess()`.
	 */
	public static function getCancelled(array $options): string {

		$minutes = $options['minutes'];

		$values = [
			'duration' => DurationComplementUi::getDuration($minutes),
		];

		$cancelled = DurationComplementUi::isSingular($minutes) ?
			s("{duration} de travail annulée", $values) :
			s("{duration} de travail annulées", $values);

		$day = DateComplementUi::getDays($options['dates']);

		if($day === NULL) {
			return $cancelled;
		} else if(count($options['dates']) > 1) {
			return s("{cancelled} par jour {day}", ['cancelled' => $cancelled, 'day' => encode($day)]);
		} else {
			return s("{cancelled} {day}", ['cancelled' => $cancelled, 'day' => encode($day)]);
		}

	}

	public static function getFutureRefusal(): string {
		return s("Un temps de travail ne se saisit pas à l'avance.");
	}

	public static function getPastRefusal(int $days): string {
		return s("Au-delà de {value} jours, le temps de travail se saisit depuis la fiche de temps.", $days);
	}

	/**
	 * Une intervention nommée que la semaine du jour dit n'a pas.
	 */
	public static function getDatedRefusal(\series\Task $eTaskObject): string {
		return s("Pas d'intervention « {value} » cette semaine-là.", $eTaskObject['name']);
	}

	/**
	 * Une action, suivie de ce que la personne a tapé après elle, sans intervention de la semaine, quand
	 * aucune ne peut s'ajouter au planning : l'action seule, plusieurs espèces, ou une action qui ne porte
	 * pas d'espèce (`TimeIntentionLib::resolveAdd()`).
	 */
	public static function getNoneRefusal(string $name, string $date): string {

		return (toWeek($date) === \Clock::week()) ?
			s("Pas d'intervention « {value} » cette semaine.", $name) :
			s("Pas d'intervention « {value} » cette semaine-là.", $name);

	}

	/**
	 * Un mot que rien ne réclame : la durée manque, ou elle est dite et le mot est de trop.
	 */
	public static function getUnclaimed(bool $duration): string {

		return $duration ?
			s("Un mot de la saisie n'est pas compris.") :
			s("Indiquez une durée, par exemple 30 min ou 1 h 30.");

	}

}
?>
