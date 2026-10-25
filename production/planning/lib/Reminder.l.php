<?php
namespace planning;

/**
 * Le pense-bête : les rappels qu'une semaine montre, ceux de l'accueil, et les gestes qui les écrivent.
 *
 * Une semaine montre ce qui y était vrai (FX, décidé le 16/10). Un rappel à faire se reporte
 * de sa semaine jusqu'à la semaine en cours, jamais au-delà. Un rappel coché ne se montre, barré, que dans
 * une semaine, la plus tardive de la sienne et de celle de la coche, et à faire dans les semaines qui
 * séparent sa semaine de sa coche. L'accueil montre un rappel coché jusqu'à la fin du jour de la coche.
 *
 * Chaque lecture tient sur un index, sans `OR` entre les deux états, qui ferait lire la table entière :
 * les rappels à faire et ceux cochés en avance sur `(structure, status, week)`, ceux cochés depuis une date
 * sur `(structure, status, doneAt)` (`ReminderExplainAi`).
 */
class ReminderLib extends ReminderCrud {

	/**
	 * La semaine se construit avant le jour, que l'étape `dateInWeek` borne à cette semaine.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['week', 'date', 'text'];
	}

	/**
	 * La semaine ne change pas à la mise à jour : déplacer un rappel d'une semaine à l'autre est un
	 * report, qui ne se confond pas avec une correction du texte. Un jour choisi dans les colonnes de la grille
	 * (`date`, DP) n'écrit que le jour : le texte affiché peut avoir été corrigé depuis par un autre membre.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {

		if($origin === 'date') {
			return ['date'];
		}

		return ['date', 'text'];

	}

	/**
	 * Le pense-bête de `$week` : `cReminder`, les rappels écrits pour elle, et `cReminderBefore`, ceux des
	 * semaines d'avant qu'elle montre encore, chacun indexé par id pour que la réponse d'un geste y retrouve
	 * sa ligne, et `todo`, le compte de ce qui y reste à faire.
	 *
	 * Une semaine à venir ne montre que ses rappels, et n'a pas encore de coche à montrer hors des siens.
	 *
	 * @return array{cReminder: \Collection, cReminderBefore: \Collection, todo: int}
	 */
	public static function getForWeek(\structure\Structure $eStructure, string $week): array {

		$cReminderAll = self::getTodoUntilWeek($eStructure, $week)
			->appendCollection(self::getDoneAheadWeek($eStructure, $week));

		if($week <= \Clock::week()) {
			$cReminderAll->appendCollection(self::getDoneSinceWeek($eStructure, $week));
		}

		self::sort($cReminderAll);
		self::fillDone($cReminderAll, $week);
		self::fillDays($cReminderAll);

		$cReminder = $cReminderAll->find(fn(Reminder $eReminder) => $eReminder['week'] === $week);
		$cReminderBefore = $cReminderAll->find(fn(Reminder $eReminder) => $eReminder['week'] < $week);

		return [
			'cReminder' => $cReminder,
			'cReminderBefore' => $cReminderBefore,
			'todo' => $cReminderAll->find(fn(Reminder $eReminder) => $eReminder['done'] === FALSE)->count(),
		];

	}

	/**
	 * Le pense-bête de l'accueil : ce qui reste à faire jusqu'à la semaine en cours, et ce qui a été coché
	 * aujourd'hui, dans l'ordre de la Semaine. Un rappel d'une semaine à venir coché aujourd'hui ne s'y montre
	 * pas : il n'y était pas avant la coche.
	 */
	public static function getForToday(\structure\Structure $eStructure): \Collection {

		$week = \Clock::week();

		$cReminder = self::getTodoUntilWeek($eStructure, $week)
			->appendCollection(self::getDoneSinceDay($eStructure, \Clock::date(), $week));

		self::sort($cReminder);
		self::fillDone($cReminder, $week);

		return $cReminder;

	}

	/**
	 * Les rappels à faire que `$week` montre : les siens, et ceux des semaines d'avant quand elle n'est pas à
	 * venir.
	 */
	public static function getTodoUntilWeek(\structure\Structure $eStructure, string $week): \Collection {

		return self::applyTodoUntilWeek(Reminder::model()->select(Reminder::getSelection()), $eStructure, $week)
			->getCollection(NULL, NULL, 'id');

	}

	public static function applyTodoUntilWeek(ReminderModel $mReminder, \structure\Structure $eStructure, string $week): ReminderModel {

		return $mReminder
			->whereStructure($eStructure)
			->whereStatus(Reminder::TODO)
			->whereWeek(($week > \Clock::week()) ? '=' : '<=', $week);

	}

	/**
	 * Les rappels de `$week` ou d'avant cochés depuis son lundi : barrés quand la coche tombe dans `$week`, à
	 * faire quand elle est venue plus tard. La borne porte sur `doneAt`, que la semaine du rappel ne borne
	 * pas : un rappel se coche des mois après sa semaine.
	 */
	public static function getDoneSinceWeek(\structure\Structure $eStructure, string $week): \Collection {

		return self::applyDoneSinceWeek(Reminder::model()->select(Reminder::getSelection()), $eStructure, $week)
			->getCollection(NULL, NULL, 'id');

	}

	public static function applyDoneSinceWeek(ReminderModel $mReminder, \structure\Structure $eStructure, string $week): ReminderModel {

		return $mReminder
			->whereStructure($eStructure)
			->whereStatus(Reminder::DONE)
			->whereDoneAt('>=', week_date_starts($week).' 00:00:00')
			->whereWeek('<=', $week);

	}

	/**
	 * Les rappels de `$week` cochés avant son lundi, qui se montrent barrés dans leur semaine et non dans
	 * celle de la coche.
	 */
	public static function getDoneAheadWeek(\structure\Structure $eStructure, string $week): \Collection {

		return self::applyDoneAheadWeek(Reminder::model()->select(Reminder::getSelection()), $eStructure, $week)
			->getCollection(NULL, NULL, 'id');

	}

	public static function applyDoneAheadWeek(ReminderModel $mReminder, \structure\Structure $eStructure, string $week): ReminderModel {

		return $mReminder
			->whereStructure($eStructure)
			->whereStatus(Reminder::DONE)
			->whereWeek($week)
			->whereDoneAt('<', week_date_starts($week).' 00:00:00');

	}

	/**
	 * Les rappels de `$week` ou d'avant cochés depuis le début de `$date`.
	 */
	public static function getDoneSinceDay(\structure\Structure $eStructure, string $date, string $week): \Collection {

		return self::applyDoneSinceDay(Reminder::model()->select(Reminder::getSelection()), $eStructure, $date, $week)
			->getCollection(NULL, NULL, 'id');

	}

	public static function applyDoneSinceDay(ReminderModel $mReminder, \structure\Structure $eStructure, string $date, string $week): ReminderModel {

		return $mReminder
			->whereStructure($eStructure)
			->whereStatus(Reminder::DONE)
			->whereDoneAt('>=', $date.' 00:00:00')
			->whereWeek('<=', $week);

	}

	/**
	 * L'ordre de la Semaine, que la coche ne change pas : les semaines dans l'ordre, et dans une semaine les
	 * rappels datés dans l'ordre des jours, puis ceux sans jour dans l'ordre de leur création. Un rappel
	 * coché reste à sa place, barré, et la coche ne redessine que sa ligne.
	 */
	private static function sort(\Collection $cReminder): void {

		$key = fn(Reminder $eReminder) => [$eReminder['week'], $eReminder['date'] === NULL, (string)$eReminder['date'], $eReminder['id']];

		$cReminder->sort(fn(Reminder $eReminder1, Reminder $eReminder2) => $key($eReminder1) <=> $key($eReminder2));

	}

	/**
	 * L'état de chaque rappel vu depuis `$week` (`done`), qui n'est pas toujours le sien : un rappel coché
	 * plus tard se lit encore à faire dans une semaine passée. Et `isPast`, une semaine passée se lisant
	 * sans ajout ni correction, et sans autre geste que la décoche d'un rappel barré dans la semaine de sa
	 * coche (`ReminderUi::isCheckable()`) : un rappel resté se coche dans la semaine en cours.
	 * Chaque chemin qui rend une ligne passe par ici, les réponses des gestes comprises.
	 */
	public static function fillDone(\Collection $cReminder, string $week): void {

		$isPast = ($week < \Clock::week());

		foreach($cReminder as $eReminder) {

			$eReminder['done'] = (
				$eReminder['status'] === Reminder::DONE and
				max($eReminder['week'], toWeek($eReminder['doneAt'])) === $week
			);

			$eReminder['isPast'] = $isPast;

		}

	}

	/**
	 * Les sept jours de `$week` que les pastilles du jour proposent, chacun disant s'il est aujourd'hui.
	 *
	 * @return array<string, array{today: bool}>
	 */
	public static function getDays(string $week): array {

		$today = \Clock::date();
		$days = [];

		foreach(week_dates($week) as $day) {
			$days[$day] = [
				'today' => ($day === $today),
			];
		}

		return $days;

	}

	/**
	 * Les pastilles du jour de chaque rappel, celles de **sa** semaine : un resté se corrige dans la semaine
	 * où il a été posé, que l'étape `dateInWeek` garde. La Semaine et le Jour les lisent toutes deux.
	 */
	public static function fillDays(\Collection $cReminder): void {

		$days = [];

		foreach($cReminder as $eReminder) {
			$days[$eReminder['week']] ??= self::getDays($eReminder['week']);
			$eReminder['days'] = $days[$eReminder['week']];
		}

	}

	/**
	 * Les rappels du Jour, rangés par colonne (`TaskDayLib::getColumnKeys()`) : un rappel daté dans sa date,
	 * un rappel sans jour dans « Non défini », comme un resté des semaines passées, qui n'a pas de jour dans
	 * celle-ci et suit ceux de la semaine. Chaque colonne a sa collection, vide sans rappel.
	 *
	 * @return array<string, \Collection>
	 */
	public static function getByDay(\structure\Structure $eStructure, string $week): array {

		$columns = [];

		foreach(TaskDayLib::getColumnKeys($week) as $key) {
			$columns[$key] = new \Collection();
		}

		$reminders = self::getForWeek($eStructure, $week);

		foreach([$reminders['cReminder'], $reminders['cReminderBefore']] as $cReminderListed) {

			foreach($cReminderListed as $eReminder) {
				$columns[self::getDayKey($eReminder, $week)][$eReminder['id']] = $eReminder;
			}

		}

		return $columns;

	}

	/**
	 * La colonne du Jour d'un rappel vu depuis `$week` : sa date dans sa semaine, « Non défini » sans jour
	 * ou pour un resté.
	 */
	public static function getDayKey(Reminder $eReminder, string $week): string {

		if($eReminder['week'] !== $week or $eReminder['date'] === NULL) {
			return TaskDayLib::NONE;
		}

		return $eReminder['date'];

	}

	public static function create(Reminder $e): void {

		$e->expects(['structure']);

		parent::create($e);

	}

	public static function update(Reminder $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Cocher n'est pas modifier le rappel : `updatedAt` reste la date de la dernière correction du
	 * texte ou du jour.
	 */
	public static function check(Reminder $e): void {

		$e['status'] = Reminder::DONE;
		$e['doneAt'] = new \Sql('NOW()');
		$e['doneBy'] = \user\ConnectionLib::getOnline();

		parent::update($e, ['status', 'doneAt', 'doneBy']);

	}

	public static function uncheck(Reminder $e): void {

		$e['status'] = Reminder::TODO;
		$e['doneAt'] = NULL;
		$e['doneBy'] = new \user\User();

		parent::update($e, ['status', 'doneAt', 'doneBy']);

	}

}
?>
