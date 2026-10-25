<?php
namespace entry;

/**
 * « intervention désherbage carotte hier 1 h » : une intervention créée depuis le champ, faite ou à
 * faire (HN-B16, piste « Récapitulatif en puces », tranchée). Le geste « Une intervention » du
 * champ vide en pose le verbe.
 *
 * L'action, puis l'espèce, puis la série se choisissent comme sous l'action seule d'un temps (HN-B11) :
 * `TimeIntentionLib::askPlant()`, puis `TimeIntentionLib::resolveSeries()`. Viennent ensuite, en puces,
 * l'état, le jour ou la semaine, et le temps, que la phrase peut aussi dire (« hier », « semaine prochaine »,
 * « 1 h »). Faite, l'intervention est datée du jour dit, et le temps passé s'écrit sur la fiche de temps de qui
 * tape, comme le temps seul l'écrit. À faire, elle est prévue à la semaine dite, ou au jour dit quand la phrase
 * en tape un, et le temps devient le temps prévu (`timeExpected`).
 *
 * Toutes les actions se proposent, hors récolte, qui a son verbe. L'intervention se construit dans la catégorie
 * que l'action lui donne (`TimeIntentionLib::getCategory()`) : en Culture, avec son espèce et sa série ; dans une
 * autre (« RH » en Administration, HN-B14), sans l'une ni l'autre. Ce que le formulaire complet règle de plus
 * (variété, produits, outils, méthodes) se règle ensuite sur l'intervention.
 *
 * L'annulation reprend le ticket du temps seul (`TimeIntentionLib::setCancel()`) : l'intervention part avec son
 * temps tant que rien ne s'y est ajouté depuis.
 */
class InterventionIntentionLib extends IntentionLib {

	public static function getWords(): array {
		return ['intervention'];
	}

	// Le droit du planning, comme le temps et la coche, qui créent aussi des interventions.
	public static function canWrite(\structure\Structure $eStructure): bool {
		return $eStructure->canPlanning();
	}

	public static function writesPlanning(): bool {
		return TRUE;
	}

	public static function getDashboardBlocks(): array {
		return ['series-week'];
	}

	// Ses objets portent le nom des actions, que le temps lit sans verbe : deviner le verbe prendrait l'un pour
	// l'autre.
	public static function isExplicit(): bool {
		return TRUE;
	}

	/**
	 * Une intervention par action, hors récolte, nommée par l'action, sans espèce, dans la catégorie que l'action
	 * lui donne (`TimeIntentionLib::getCategory()`) : en Culture, l'espèce se demande ensuite. Son identifiant est
	 * celui de l'action.
	 */
	public static function getObjects(\structure\Structure $eStructure, array $reading, \Collection $cElementRead = new \Collection()): \Collection {

		$cCategory = \work\CategoryLib::getByStructure($eStructure, index: 'id');

		$cTaskObject = new \Collection();

		foreach(\work\ActionLib::getByStructure($eStructure) as $eAction) {

			$eCategory = TimeIntentionLib::getCategory($cCategory, $eAction);

			if($eAction['fqn'] === ACTION_RECOLTE or $eCategory->empty()) {
				continue;
			}

			$cTaskObject[$eAction['id']] = new \series\Task([
				'id' => $eAction['id'],
				'action' => $eAction,
				'category' => $eCategory,
				'cCategory' => TimeIntentionLib::getCategories($cCategory, $eAction),
				'plant' => new \plant\Plant(),
				'name' => $eAction['name'],
			]);

		}

		return $cTaskObject->sort(fn(\series\Task $e1, \series\Task $e2) => \L::getCollator()->compare($e1['name'], $e2['name']));

	}

	/**
	 * Le nom de l'action, liaisons ignorées de part et d'autre, comme le temps lit le sien : les liaisons prises
	 * entre les mots du nom partent avec lui.
	 */
	public static function readObject(\Collection $cElement, array &$words): \series\Task {

		$wordsUnlinked = EntryLib::withoutLinks($words);
		$wordsUnlinkedRead = $wordsUnlinked;

		$key = self::readName($cElement, $wordsUnlinked, withoutLinks: TRUE);

		if($key === NULL) {
			return new \series\Task();
		}

		$positions = array_keys(array_diff_key($wordsUnlinkedRead, $wordsUnlinked));

		$words = array_filter($words, fn(int $position) => ($position < min($positions) or $position > max($positions)), ARRAY_FILTER_USE_KEY);

		return $cElement[$key];

	}

	/**
	 * Le début d'un nom tapé après l'action cherche l'espèce que la question propose.
	 */
	public static function isSearching(array $reading): bool {
		return ($reading['question'] !== NULL and $reading['question']['name'] === 'plant');
	}

	/**
	 * L'espèce, puis la série, puis l'état, le jour ou la semaine, et le temps. Une action d'une autre catégorie
	 * que Culture n'a ni espèce ni série : elle commence à l'état, ou à sa catégorie quand elle en a plusieurs
	 * (`TimeIntentionLib::resolveCategory()`).
	 *
	 * L'intervention se construit dès la lecture, comme celle qu'un temps ajoute au planning
	 * (`TimeIntentionLib::resolveAdd()`) : un refus de `build()` vaut pour la lecture comme pour l'écriture. Elle
	 * est posée sur la lecture (`task`) avant les refus qui la suivent : sa série reste choisie pendant qu'on
	 * corrige l'état, le jour ou la semaine. Un refus du moteur, avant elle (un mot incompris, une semaine qui
	 * n'existe pas), la laisse à choisir de nouveau, comme sous un temps.
	 */
	public static function resolve(\structure\Structure $eStructure, array &$reading, array $answers): void {

		$eCategory = TimeIntentionLib::resolveCategory($reading, $answers);

		if($reading['question'] !== NULL) {
			return;
		}

		if(TimeIntentionLib::isCulture($eCategory)) {

			$ePlant = self::resolvePlant($eStructure, $reading);

			if($ePlant->empty()) {
				return;
			}

			$eCultivation = TimeIntentionLib::resolveSeries($eStructure, $reading, $answers, $ePlant);

			if($reading['question'] !== NULL) {
				return;
			}

		} else {
			$ePlant = new \plant\Plant();
			$eCultivation = new \series\Cultivation();
		}

		$week = $reading['complements']['week'];
		$minutes = is_int($reading['complements']['duration']) ? $reading['complements']['duration'] : 0;

		$status = self::getStatus($reading, $answers);

		$input = [];

		if($status === \series\Task::TODO) {

			// Un jour tapé dit le jour prévu, sinon la semaine, celle d'aujourd'hui par défaut.
			if($reading['complementsNamed']['date'] !== [] and WeekComplementLib::isWeek($week) === FALSE) {
				$input['planned'] = ['date' => $reading['date']];
			} else {
				$input['planned'] = ['week' => WeekComplementLib::isWeek($week) ? $week : \Clock::week()];
			}

			if($minutes > 0) {
				$input['timeExpected'] = DurationComplementLib::getHours($minutes);
			}

		}

		$fw = new \FailWatch();

		$eTask = TimeIntentionLib::buildTask($eStructure, $eCategory, $reading['object']['action'], $ePlant, $eCultivation, $reading['date'], $status, $input);

		$fw->validate();

		// L'espèce lue, la même que celle que `build()` ne pose que hors série, porte ce que l'écran en montre.
		$eTask['plant'] = $ePlant;

		TimeIntentionLib::fillNameWithSeason($eTask);

		$reading['task'] = $eTask;

		// Un jour sans année se lit au plus récent qui n'est pas à venir (`DateComplementLib`) : à faire, « le 12
		// novembre » serait celui de l'an passé. Le jour prévu se dit à venir, sinon c'est la semaine qui le dit.
		if($status === \series\Task::TODO and isset($input['planned']['date']) and $reading['date'] < \Clock::date()) {
			EntryLib::refuse($reading, ['reason' => 'plannedPast']);
			return;
		}

		if($status === \series\Task::DONE) {

			$refusal = self::getDoneRefusal($eStructure, $reading, $minutes);

			if($refusal !== NULL) {
				EntryLib::refuse($reading, $refusal);
				return;
			}

		}

		$reading['confirmation'] = [
			'eTask' => $eTask,
			'status' => $status,
			'date' => $reading['date'],
			// Le temps passé, que la fiche de temps reçoit au jour dit (`TimeIntentionLib::prepare()`), et le
			// temps prévu, que l'intervention porte.
			'minutes' => ($status === \series\Task::DONE) ? $minutes : 0,
			'dates' => ($status === \series\Task::DONE and $minutes > 0) ? [$reading['date']] : [],
			'expected' => ($status === \series\Task::TODO) ? $minutes : 0,
			// Faite, son temps s'écrit au jour dit, sous la borne de la journée, là où le temps se suit ; à faire, il
			// n'est que prévu.
			'additions' => match($status) {
				\series\Task::DONE => $eStructure->hasTime() ? DurationComplementLib::getAdditions($minutes, TimeIntentionLib::getWorked($eStructure, [$reading['date']])) : [],
				\series\Task::TODO => DurationComplementLib::getAdditions($minutes),
			},
			'tracked' => $eStructure->hasTime(),
			'yesterday' => date('Y-m-d', \Clock::shift('-1 DAY')),
			'day' => self::getDay($reading['date']),
			'weekChoice' => ($status === \series\Task::TODO) ? self::getWeekChoice($eTask['plannedWeek']) : NULL,
			'created' => TRUE,
		];

		$reading['outcome'] = EntryLib::WRITE;

	}

	/**
	 * L'espèce lue après l'action, ou la question qui la demande : celles de la saison (`TimeIntentionLib::askPlant()`),
	 * bornées à celles qu'un début de nom commence, qui reste en attente pour que l'espèce choisie le remplace.
	 * Un début de nom qu'aucune espèce de la saison ne porte les propose toutes. Rien n'est demandé pour des mots
	 * qui ne commencent aucune espèce : le moteur les refuse comme incompris.
	 */
	private static function resolvePlant(\structure\Structure $eStructure, array &$reading): \plant\Plant {

		$pending = $reading['pending'];

		if($pending === NULL) {

			if(TimeIntentionLib::askPlant($eStructure, $reading) === FALSE) {
				EntryLib::refuse($reading, ['reason' => 'plant']);
			}

			return new \plant\Plant();

		}

		$cPlant = TimeIntentionLib::readPlants($eStructure, $pending);

		if($cPlant->count() === 1) {

			$reading['pending'] = NULL;
			$reading['plant'] = $cPlant->first();
			$reading['plantNamed'] = EntryLib::split($pending);

			return $cPlant->first();

		}

		if($cPlant->count() > 1 and TimeIntentionLib::askPlant($eStructure, $reading, $cPlant) === FALSE) {

			foreach($cPlant as $ePlant) {
				$ePlant['acted'] = FALSE;
			}

			EntryLib::ask($reading, ['name' => 'plant', 'cPlant' => $cPlant]);

		}

		return new \plant\Plant();

	}

	/**
	 * L'état choisi en puce, sinon à faire quand la phrase dit une semaine ou un jour à venir, faite sinon : on
	 * saisit d'abord ce qu'on vient de faire.
	 */
	private static function getStatus(array $reading, array $answers): string {

		if(in_array($answers['status'], [\series\Task::DONE, \series\Task::TODO], TRUE)) {
			return $answers['status'];
		}

		if(WeekComplementLib::isWeek($reading['complements']['week']) or $reading['date'] > \Clock::date()) {
			return \series\Task::TODO;
		}

		return \series\Task::DONE;

	}

	/**
	 * Une intervention faite se date d'un jour, pas d'une semaine, et d'un jour passé que le champ admet
	 * (`IntentionLib::getPastRefusal()`) : une plus ancienne se saisit sur le planning.
	 */
	private static function getDoneRefusal(\structure\Structure $eStructure, array $reading, int $minutes): ?array {

		if(WeekComplementLib::isWeek($reading['complements']['week'])) {
			return ['reason' => 'doneWeek'];
		}

		$refusal = self::getPastRefusal($reading['date']);

		if($refusal !== NULL) {
			return $refusal;
		}

		// Refusé dès la lecture, comme `Timesheet::time.untracked` le refuserait à l'écriture.
		if($minutes > 0 and $eStructure->hasTime() === FALSE) {
			return ['reason' => 'untracked'];
		}

		return NULL;

	}

	/**
	 * La puce du jour d'une intervention faite.
	 */
	private static function getDay(string $date): string {

		if($date === \Clock::date()) {
			return 'today';
		}

		if($date === date('Y-m-d', \Clock::shift('-1 DAY'))) {
			return 'yesterday';
		}

		return 'other';

	}

	/**
	 * La puce de la semaine d'une intervention à faire.
	 */
	private static function getWeekChoice(string $week): string {

		if($week === \Clock::week()) {
			return 'this';
		}

		if($week === toWeek(\Clock::shift('+7 DAYS'))) {
			return 'next';
		}

		return 'other';

	}

	/**
	 * Comme une intervention qu'un temps ajoute au planning, avec son temps quand elle est faite.
	 */
	public static function prepare(\structure\Structure $eStructure, array &$reading): void {
		TimeIntentionLib::prepare($eStructure, $reading);
	}

	public static function write(\structure\Structure $eStructure, array &$reading): void {
		TimeIntentionLib::write($eStructure, $reading);
	}

	public static function afterCommit(\structure\Structure $eStructure, array &$reading): void {

		$confirmation = $reading['confirmation'];

		$reading['undo'] = ['intention' => 'intervention', 'ticket' => TimeIntentionLib::setCancel($eStructure, $confirmation['eTask'], $confirmation['dates'], $confirmation['minutes'], TRUE, $reading['taskTouched'])];

	}

	public static function getModel(): \ModuleModel {
		return \series\Task::model();
	}

	/**
	 * L'annulation supprime l'intervention, et rien d'autre : retouchée depuis, elle n'est plus seulement celle de
	 * la saisie, et se corrige sur le planning.
	 */
	public static function getCancellation(\structure\Structure $eStructure, string $ticket): ?array {

		$cancellation = TimeIntentionLib::getCancellation($eStructure, $ticket);

		if($cancellation === NULL or $cancellation['created'] === FALSE) {
			return NULL;
		}

		// Sans temps, le ticket ne dit aucun jour : la réponse redessine le planning là où l'intervention se montre.
		if($cancellation['dates'] === []) {

			$eTask = $cancellation['eTask'];

			$cancellation['date'] = match($eTask['status']) {
				\series\Task::TODO => ($eTask['plannedDate'] === NULL) ? week_date_starts($eTask['plannedWeek']) : $eTask['plannedDate'],
				\series\Task::DONE => $eTask['doneDate'],
			};

		}

		return $cancellation;

	}

	public static function cancel(array $cancellation): bool {
		return TimeIntentionLib::cancel($cancellation);
	}

	// Le message de la coche, qui crée aussi une intervention que l'annulation retire.
	public static function getCancelFail(): string {
		return 'entry\Entry::done.cancellable';
	}

}
?>
