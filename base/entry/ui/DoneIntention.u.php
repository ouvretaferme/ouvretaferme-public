<?php
namespace entry;

/**
 * Ce que la coche dit à l'écran (lot S4a, direction A de la planche) : la ligne d'écriture change de verbe,
 * « Marquer comme faite », l'intervention en seconde ligne. Tant que l'objet n'est pas nommé, la liste est
 * celle du temps, dont la coche lit la phrase (`TimeIntentionUi`).
 *
 * Rien de prévu : la question des séries où la créer faite est celle du temps qui ajoute la sienne au planning
 * (`TimeIntentionUi::getSeriesResults()`), et la ligne d'écriture reste « Marquer comme faite » (lot S4b).
 *
 * Plusieurs séries : la question finit par « Toutes les séries », qui coche la plus proche de chacune ou les
 * crée sur chaque série en phase, et la ligne d'écriture dit « Marquer comme faites » (lot S4d).
 *
 * Sous la ligne qui coche, et sous l'intervention déjà faite, les durées mènent au temps de la même
 * intervention : la phrase avec une durée n'est plus une coche (hypothèse sur « rien n'empêche de mettre de
 * l'autocomplétion pour montrer qu'on peut mettre du temps de travail », 19/10).
 */
class DoneIntentionUi extends IntentionUi {

	public static function getLabel(): string {
		return s("Intervention faite");
	}

	// « Intervention faite » ne se relit pas comme la coche, qui ne connaît que ses mots (`DoneIntentionLib::getWords()`).
	public static function getToken(): string {
		return s("Fait");
	}

	public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, \Collection $cAction): array {

		if($reading['object']->empty()) {
			return TimeIntentionUi::getResults($eStructure, $reading, $cElement, $cAction);
		}

		if($reading['question'] !== NULL and $reading['question']['name'] === 'plant') {
			return TimeIntentionUi::getPlantResults($reading['object']['action'], $reading['question']['cPlant']);
		}

		if($reading['question'] !== NULL and $reading['question']['name'] === 'series') {

			$results = TimeIntentionUi::getSeriesResults($reading, $cAction, self::getWrite());
			$cCultivationAll = $reading['question']['cCultivationAll'];

			// Juste sous les séries en phase qu'elle reprend, que la question range en tête : en dernier, sous les
			// autres saisons et « Hors série », elle sortait de la liste visible au bureau.
			if($cCultivationAll->notEmpty()) {
				array_splice($results, $cCultivationAll->count(), 0, [[
					'group' => self::getWrite(),
					'label' => HarvestIntentionUi::getAll(),
					'value' => 'all',
					'sub' => encode(implode(', ', $cCultivationAll->getColumnCollection('series')->getColumn('name'))),
				] + self::getIcon($reading['question']['ePlant'])]);
			}

			return $results;

		}

		if($reading['question'] !== NULL) {

			$results = [];

			foreach($reading['question']['cTask'] as $eTask) {
				$results[] = [
					'group' => TimeIntentionUi::getQuestion(),
					'label' => TimeIntentionUi::getTaskLabel($eTask),
					'value' => 'task:'.$eTask['id'],
					'sub' => encode(self::getSituation($eTask)),
				] + self::getIcon($eTask['plant']);
			}

			$cTaskAll = $reading['question']['cTaskAll'];

			if($cTaskAll->notEmpty()) {
				$results[] = [
					'group' => TimeIntentionUi::getQuestion(),
					'label' => HarvestIntentionUi::getAll(),
					'value' => 'all',
					'sub' => encode(self::getSeriesNames($cTaskAll)),
				] + self::getIcon($cTaskAll->first()['plant']);
			}

			return $results;

		}

		$eTask = $reading['task'];

		if($reading['outcome'] === EntryLib::WRITE) {

			$results = [[
				'group' => self::getLabel(),
				'label' => self::getWriteLabel($reading),
				'value' => 'write',
				'sub' => encode(self::getWriteDetail($reading)),
				'cursor' => TRUE,
			] + self::getIcon($reading['confirmation']['cTask']->first()['plant'])];

		} else if($eTask->notEmpty()) {

			// Déjà faite : rien à cocher, la ligne ne mène nulle part.
			$results = [[
				'group' => self::getLabel(),
				'label' => TimeIntentionUi::getTaskLabel($eTask),
				'value' => 'done',
				'sub' => encode(self::getDoneDay($eTask)),
			] + self::getIcon($eTask['plant'])];

		} else {
			return [];
		}

		// Plusieurs interventions ne portent aucune durée : le temps seul ne se pose que sur une.
		if($eTask->notEmpty() and $eTask['timed']) {
			$results = array_merge($results, DurationComplementUi::getAdditionResults(TimeIntentionUi::getLabel(), $eTask['additions']));
		}

		return $results;

	}

	private static function getIcon(\plant\Plant $ePlant): array {

		$icon = self::getPlantIcon($ePlant);

		return ($icon === NULL) ? [] : ['icon' => $icon];

	}

	/**
	 * « Carotte 9 SA, Carotte 10 SA, hors série » : la série de chacune, dans l'ordre de la question.
	 */
	private static function getSeriesNames(\Collection $cTask): string {

		$names = [];

		foreach($cTask as $eTask) {
			$names[] = $eTask['series']->empty() ? mb_strtolower(TimeIntentionUi::getNoSeries()) : $eTask['series']['name'];
		}

		return implode(', ', $names);

	}

	/**
	 * La coche ne dit qu'une étape d'elle-même : une intervention déjà faite, qui n'attend plus rien.
	 */
	public static function getAwaitedStep(): string {
		return 'done';
	}

	public static function getPlaceholder(array $reading, string $step): string {

		return match($step) {
			'object' => TimeIntentionUi::getObjectPlaceholder(),
			'question' => match($reading['question']['name']) {
				'series' => TimeIntentionUi::getSeriesPlaceholder(),
				'plant' => HarvestIntentionUi::getObjectPlaceholder(),
				'task' => TimeIntentionUi::getQuestion(),
			},
			'done' => s("Déjà faite"),
			'write' => self::getWrite(),
		};

	}

	public static function getRefusal(array $reading): ?string {

		$refusal = $reading['refusal'];

		return match($refusal['reason']) {
			'unplanned' => s("Pas d'intervention « {name} » prévue de la semaine {from} à la semaine {to}.", [
				'name' => $refusal['name'],
				'from' => week_number($refusal['from']),
				'to' => week_number($refusal['to']),
			]),
			'unclaimed' => s("Un mot de la saisie n'est pas compris."),
			'future' => s("Une intervention ne se marque pas comme faite à l'avance."),
			'past' => s("Au-delà de {value} jours, l'intervention se marque comme faite depuis le planning.", $refusal['days']),
			default => NULL,
		};

	}

	public static function getObjectIcon(\Element $eElement): ?string {
		return self::getPlantIcon($eElement['plant']);
	}

	public static function getWrite(): string {
		return s("Marquer comme faite");
	}

	/**
	 * « Marquer comme faite », et le jour quand ce n'est pas aujourd'hui : « Marquer comme faite hier ». Avec
	 * une durée, la ligne du temps qu'elle complète : « Ajouter 2 h hier et marquer comme faite ». Lu sur la
	 * phrase, que la coche écrive ou demande encore laquelle : seule une coche qui écrit sait qu'elle en
	 * marque plusieurs, « Marquer comme faites ».
	 */
	public static function getWriteLabel(array $reading): string {

		$minutes = $reading['complements']['duration'];
		$day = DateComplementUi::getDay($reading['date']);
		$several = ($reading['outcome'] === EntryLib::WRITE and $reading['confirmation']['cTask']->count() > 1);

		if(is_int($minutes)) {

			$values = [
				'duration' => DurationComplementUi::getDuration($minutes),
				'day' => $day,
			];

			if($several) {
				return ($day === NULL) ?
					s("Ajouter {duration} et marquer comme faites", $values) :
					s("Ajouter {duration} {day} et marquer comme faites", $values);
			}

			return ($day === NULL) ?
				s("Ajouter {duration} et marquer comme faite", $values) :
				s("Ajouter {duration} {day} et marquer comme faite", $values);

		}

		if($several) {
			return ($day === NULL) ? s("Marquer comme faites") : s("Marquer comme faites {day}", ['day' => $day]);
		}

		return ($day === NULL) ? self::getWrite() : s("Marquer comme faite {day}", ['day' => $day]);

	}

	/**
	 * Sous l'écriture du temps, sous sa question de série ou sous son refus, la coche de la même phrase (`$readingDone`, que
	 * la précédence a écartée, lot S4c). La choisir pose le verbe de la coche en jeton (`getVerb()`) : elle
	 * écrit tout de suite si elle n'a rien à demander, sinon la phrase se relit et pose sa question.
	 */
	public static function getTimedResult(array $readingDone): array {

		$result = [
			'group' => self::getLabel(),
			'label' => self::getWriteLabel($readingDone),
			'value' => 'timeDone:'.self::getTimedStep($readingDone),
		];

		$icon = self::getPlantIcon(self::getTimedPlant($readingDone));

		if($icon !== NULL) {
			$result['icon'] = $icon;
		}

		if($readingDone['outcome'] === EntryLib::WRITE) {
			$result['sub'] = encode(self::getWriteDetail($readingDone));
		}

		return $result;

	}

	/**
	 * L'espèce de la coche : celle de l'intervention qu'elle écrit, sinon celle de sa question, l'action ayant
	 * pu être nommée sans elle (« désherbage » puis « courgette »).
	 */
	private static function getTimedPlant(array $readingDone): \plant\Plant {

		if($readingDone['outcome'] === EntryLib::WRITE) {
			return $readingDone['confirmation']['cTask']->first()['plant'];
		}

		return match($readingDone['question']['name']) {
			'series' => $readingDone['question']['ePlant'],
			'task' => $readingDone['question']['cTask']->first()['plant'],
		};

	}

	/**
	 * Ce que fait le geste qui ajoute la coche au temps, et le verbe qu'il pose : `write:fait` ou `ask:fait`
	 * (`EntryField.markDone()`).
	 */
	public static function getTimedStep(array $readingDone): string {
		$step = match($readingDone['outcome']) {
			EntryLib::WRITE => 'write',
			EntryLib::ASK => 'ask',
		};

		return $step.':'.self::getVerb();
	}

	/**
	 * Le jeton du verbe que pose ce geste. Il n'est pas traduit : la phrase se relit avec lui, et c'est le
	 * premier mot que la coche lit (`DoneIntentionLib::getWords()`, `DoneIntentionTest::testVerb()`).
	 */
	public static function getVerb(): string {
		return 'fait';
	}

	/**
	 * « Carotte 9 SA · prévue en semaine 45 », en texte brut : l'action et l'espèce sont dans le champ. Celle
	 * que la coche crée se dit comme celle qu'un temps ajoute au planning, « Carotte 9 SA · à ajouter au
	 * planning ». Plusieurs se disent par leurs séries, « Carotte 9 SA, Carotte 10 SA ».
	 */
	public static function getWriteDetail(array $reading): string {
		return self::getTasksDetail($reading['confirmation'], s("à ajouter au planning"));
	}

	private static function getTasksDetail(array $confirmation, string $added): string {

		$cTask = $confirmation['cTask'];

		if($cTask->count() > 1) {
			// L'ajout au planning avant les séries : la pastille coupe son détail à deux lignes, et c'est lui qui
			// distingue une création d'une coche.
			return $confirmation['created'] ? $added.' · '.self::getSeriesNames($cTask) : self::getSeriesNames($cTask);
		}

		$eTask = $cTask->first();

		return $confirmation['created'] ?
			TimeIntentionUi::getAddedDetail($eTask, $added) :
			self::getTaskDetail($eTask);

	}

	private static function getTaskDetail(\series\Task $eTask): string {

		$parts = [];

		$label = TimeIntentionUi::getTaskLabel($eTask);

		if($label !== $eTask['action']['name']) {
			$parts[] = $label;
		}

		$parts[] = self::getSituation($eTask);

		return implode(' · ', $parts);

	}

	/**
	 * Sa semaine, précédée hors de Culture de sa catégorie, qui départage celles d'une action de plusieurs
	 * catégories : « Administration · prévue en semaine 44 ».
	 */
	private static function getSituation(\series\Task $eTask): string {

		return ($eTask['category']['fqn'] === CATEGORIE_CULTURE) ?
			self::getPlanned($eTask) :
			$eTask['category']['name'].' · '.self::getPlanned($eTask);

	}

	public static function getPlanned(\series\Task $eTask): string {
		return TimeIntentionUi::getPlannedWeek($eTask['plannedWeek']);
	}

	/**
	 * « faite aujourd'hui », « faite hier », « faite le jeudi 9 octobre ».
	 */
	public static function getDoneDay(\series\Task $eTask): string {

		$day = DateComplementUi::getDay($eTask['doneDate']);

		return ($day === NULL) ? s("faite aujourd'hui") : s("faite {day}", ['day' => $day]);

	}

	/**
	 * Une intervention hors de Culture n'a pas d'espèce : l'action seule la nomme.
	 */
	private static function getTaskName(\series\Task $eTask): string {

		return $eTask['plant']->empty() ?
			$eTask['action']['name'] :
			$eTask['action']['name'].' '.mb_strtolower($eTask['plant']['name']);

	}

	public static function getWriteToast(array $reading): array {

		$confirmation = $reading['confirmation'];
		$eTask = $confirmation['cTask']->first();

		return [
			'package' => 'entry',
			'fqn' => 'Entry::done',
			'options' => [
				'date' => $confirmation['date'],
				'minutes' => $confirmation['minutes'],
				'count' => $confirmation['cTask']->count(),
			],
			'icon' => self::getPlantIcon($eTask['plant']),
			'detail' => encode(self::getTaskName($eTask).' · '.self::getTasksDetail($confirmation, ($confirmation['cTask']->count() > 1) ? s("ajoutées au planning") : s("ajoutée au planning"))),
		];

	}

	/**
	 * L'événement de la première : ses écouteurs (`sale.js`, `planning.js`) ne lisent que la structure, la
	 * culture et la récolte, et une coche ne touche aucune récolte.
	 */
	public static function getWriteEvent(\structure\Structure $eStructure, array $reading): array {
		return EntryUi::getEvent($eStructure, $reading['confirmation']['cTask']->first(), $reading['confirmation']['date']);
	}

	/**
	 * Ce que l'annulation défait vraiment (`cTaskReverted`), une intervention retouchée depuis restant faite.
	 */
	public static function getCancelToast(array $cancellation): array {

		return [
			'package' => 'entry',
			'fqn' => $cancellation['created'] ? 'Entry::doneRemoved' : 'Entry::doneCancelled',
			'options' => [
				'count' => $cancellation['cTaskReverted']->count(),
			],
			'icon' => self::getPlantIcon($cancellation['cTask']->first()['plant']),
		];

	}

	public static function getCancelEvent(\structure\Structure $eStructure, array $cancellation): array {
		return EntryUi::getEvent($eStructure, $cancellation['cTask']->first(), $cancellation['date']);
	}

	/**
	 * « Intervention faite », titre de la pastille, et le jour quand ce n'est pas aujourd'hui : « Intervention
	 * faite hier ». Le temps écrit avec elle y entre aussi, le détail étant coupé à deux lignes : « Intervention
	 * faite hier avec 2 h de travail ».
	 */
	public static function getSuccess(array $options): string {

		$day = DateComplementUi::getDay($options['date']);

		if($options['count'] > 1) {
			return self::getSuccessSeveral($options['count'], $day, $options['minutes']);
		}

		if($options['minutes'] !== NULL) {

			$duration = encode(DurationComplementUi::getDuration($options['minutes']));

			return ($day === NULL) ?
				s("Intervention faite avec {duration} de travail", ['duration' => $duration]) :
				s("Intervention faite {day} avec {duration} de travail", ['day' => encode($day), 'duration' => $duration]);

		}

		return ($day === NULL) ? s("Intervention faite") : s("Intervention faite {day}", ['day' => encode($day)]);

	}

	/**
	 * « 2 interventions faites hier avec 2 h de travail », le temps dit en tout, réparti entre elles.
	 */
	private static function getSuccessSeveral(int $count, ?string $day, ?int $minutes): string {

		$values = ['count' => $count];

		if($day !== NULL) {
			$values['day'] = encode($day);
		}

		if($minutes !== NULL) {

			$values['duration'] = encode(DurationComplementUi::getDuration($minutes));

			return ($day === NULL) ?
				s("{count} interventions faites avec {duration} de travail", $values) :
				s("{count} interventions faites {day} avec {duration} de travail", $values);

		}

		return ($day === NULL) ? s("{count} interventions faites", $values) : s("{count} interventions faites {day}", $values);

	}

	public static function getCancelled(array $options): string {
		return p("Intervention remise à faire", "{value} interventions remises à faire", $options['count']);
	}

	/**
	 * L'annulation d'une intervention que la coche a créée : elle quitte le planning.
	 */
	public static function getRemoved(array $options): string {
		return p("Intervention retirée du planning", "{value} interventions retirées du planning", $options['count']);
	}

}
?>
