<?php
namespace planning;

/**
 * La page d'une intervention du nouveau planning (L5b) : le détail du planning actuel, sans son tableau du
 * temps de travail, et le « Temps de travail » par personne dessous (reprise t de la maquette
 * navigable, `pageRT()` et `blocTempsRT()`).
 *
 * Le détail se prépare comme sur `/tache/{id}`, par `\series\TaskLib::fillDetail()` : rien n'en est recopié
 * ici. Ce Lib ne prépare que ce que le bloc ajoute, les lignes par personne, et la carte de la semaine que
 * la page remet à jour quand un temps change, et la récolte d'un jour (`writeHarvest()`). Il ne lit ni n'écrit
 * aucune table lui-même (charte, point 10 a).
 */
class TaskDetailLib {

	const HISTORY_CREATED = 'created';
	const HISTORY_COMMENT = 'comment';

	/**
	 * Les membres de la ferme, chacun avec son temps sur l'intervention et ce que le tableau de la semaine
	 * montrée (`$weekShown`) en fait. Le temps se lit par jour : `times`, la durée de chaque jour qui affiche
	 * au moins une minute, toutes semaines confondues, `time`, le total de tout son temps, et `timeWeek`, la
	 * somme de ses jours de la semaine montrée.
	 *
	 * - `canTime` : la personne connectée peut écrire son temps. Le droit est celui du temps
	 *   (`Timesheet::assertWrite()`) : un administrateur écrit le temps de tous, les autres seulement le leur ;
	 * - `assigned` : l'intervention lui est affectée ; `canAssign` : la personne connectée peut l'affecter ou
	 *   lui retirer l'intervention, comme sur `/tache/{id}` (`\series\TaskUi::getDetailActions()`), une
	 *   intervention à faire et planifiée, et une personne présente la semaine planifiée ;
	 * - `added` : sa ligne a été demandée par « Ajouter une personne » (`$userIdsAdded`, que chaque geste
	 *   réémet), et quelque chose peut s'y faire, saisir son temps ou l'affecter. Un numéro qui n'en fait pas
	 *   partie est écarté sans bruit : la liste vient de l'écran, où une ligne a pu perdre ses gestes depuis ;
	 * - `online` : c'est la personne connectée, elle peut écrire son temps et elle est présente la semaine
	 *   montrée, comme pour se proposer (`addable`) : sa ligne se montre d'office, prête à saisir, même quand
	 *   d'autres ont du temps (V23, « soi-même est toujours visible par défaut »). Rien ne s'écrit
	 *   tant qu'aucune durée n'est enregistrée ;
	 * - `line` : elle a une ligne, parce qu'elle est affectée, qu'elle a du temps dans la semaine montrée,
	 *   qu'elle a été ajoutée ou qu'elle se montre d'office ;
	 * - `addable` : sans ligne, « Ajouter une personne » la propose, parce que son temps peut s'écrire et
	 *   qu'elle est présente la semaine montrée, ou qu'elle peut être affectée.
	 *
	 * Un ancien membre qui a du temps sur l'intervention garde sa ligne les semaines où il en a, en lecture
	 * seule. La personne connectée vient d'abord, puis les personnes affectées, puis les autres.
	 */
	public static function getUsers(\series\Task $eTask, string $weekShown, array $userIdsAdded = []): \Collection {

		$eTask->expects(['structure', 'status', 'plannedWeek', 'plannedUsers']);

		$cUserMember = \hr\TeammateLib::getUsersForPlanning($eTask['structure'], withPresenceAbsence: TRUE);

		$ccTimesheet = \series\TimesheetLib::getDaysByTask($eTask);

		$eUserOnline = \user\ConnectionLib::getOnline();

		$canAssign = (
			$eTask->canWrite() and
			$eTask->isTodo() and
			$eTask['plannedWeek'] !== NULL
		);

		foreach($cUserMember as $eUser) {

			$eUser['canTime'] = new \series\Timesheet([
				'structure' => $eTask['structure'],
				'user' => $eUser
			])->assertWrite();

			$eUser['canAssign'] = (
				$canAssign and
				\hr\Presence::isWeekPresent($eUser['cPresence'], $eTask['plannedWeek'])->notEmpty()
			);

			$eUser['isPresent'] = \hr\Presence::isWeekPresent($eUser['cPresence'], $weekShown)->notEmpty();

		}

		// Qui a du temps sur l'intervention sans être encore membre garde sa ligne, en lecture seule : le total
		// compte tout le temps de l'intervention (`Task::$time`), il doit valoir la somme des lignes (arbitrage
		// V-Planning du 2026-09-30). Son temps ne s'écrit plus, `doTime` refuse qui n'est pas membre, et
		// l'intervention ne s'affecte qu'à un membre (`doUser`).
		foreach($ccTimesheet as $userId => $cTimesheet) {

			if($cUserMember->offsetExists($userId) === FALSE) {

				$eUser = $cTimesheet->first()['user'];
				$eUser['canTime'] = FALSE;
				$eUser['canAssign'] = FALSE;
				$eUser['isPresent'] = FALSE;

				$cUserMember[$userId] = $eUser;

			}

		}

		$datesShown = week_dates($weekShown);

		foreach($cUserMember as $userId => $eUser) {

			$time = 0.0;
			$times = [];

			// Le total d'une personne compte tout son temps, brut, la somme des lignes devant valoir celui de
			// l'intervention. Ses jours ne gardent que ceux qui affichent au moins une minute : les cases, la
			// somme de la semaine, le pied et les jours saisis que le total déplie lisent tous `times`.
			if($ccTimesheet->offsetExists($userId)) {
				foreach($ccTimesheet[$userId] as $date => $eTimesheet) {

					$time += $eTimesheet['total'];

					if(self::hasMinute($eTimesheet['total'])) {
						$times[$date] = $eTimesheet['total'];
					}

				}
			}

			$timesWeek = [];

			foreach($datesShown as $date) {
				$timesWeek[$date] = $times[$date] ?? 0.0;
			}

			$eUser['times'] = $times;
			$eUser['time'] = $time;
			$eUser['timesWeek'] = $timesWeek;
			$eUser['timeWeek'] = array_sum($timesWeek);
			$eUser['isOnline'] = $eUser->is($eUserOnline);
			$eUser['assigned'] = in_array($userId, $eTask['plannedUsers']);

		}

		foreach($cUserMember as $userId => $eUser) {

			$eUser['online'] = (
				$eUser['isOnline'] and
				$eUser['canTime'] and
				$eUser['isPresent']
			);
			// Une ligne n'est ajoutée à la main que tant que rien ne la retient : un temps ou une affectation la
			// font tenir seule, et la perdre ensuite la fait sortir, sur l'écran comme au rechargement.
			$eUser['added'] = (
				in_array($userId, $userIdsAdded) and
				$eUser['assigned'] === FALSE and
				$eUser['timeWeek'] === 0.0 and
				($eUser['canTime'] or $eUser['canAssign'])
			);
			$eUser['line'] = (
				$eUser['assigned'] or
				$eUser['timeWeek'] > 0.0 or
				$eUser['added'] or
				$eUser['online']
			);
			$eUser['addable'] = (
				$eUser['line'] === FALSE and
				(($eUser['canTime'] and $eUser['isPresent']) or $eUser['canAssign'])
			);

		}

		$cUser = new \Collection();

		foreach([TRUE, FALSE] as $isOnline) {
			foreach([TRUE, FALSE] as $assigned) {
				foreach($cUserMember as $userId => $eUser) {
					if($eUser['assigned'] === $assigned and $eUser['isOnline'] === $isOnline) {
						$cUser[$userId] = $eUser;
					}
				}
			}
		}

		return $cUser;

	}

	/**
	 * La semaine où l'intervention a été faite, sinon celle où elle est prévue. Une intervention sans semaine
	 * prend la semaine courante.
	 */
	protected static function getWeek(\series\Task $eTask): string {
		return $eTask['doneWeek'] ?? $eTask['plannedWeek'] ?? \Clock::week();
	}

	/**
	 * La semaine que le bloc montre à l'ouverture : la semaine en cours si celle de l'intervention l'est ou
	 * est encore à venir, ses jours refusant tous la saisie, sinon celle de l'intervention si elle porte du
	 * temps ou si l'intervention n'en a aucun, sinon celle du dernier jour saisi. Une intervention qui a du
	 * temps s'ouvre ainsi toujours sur ce temps, même saisi hors de sa semaine.
	 */
	public static function getOpeningWeek(\series\Task $eTask): string {

		$eTask->expects(['doneWeek', 'plannedWeek']);

		$week = self::getWeek($eTask);

		if($week >= \Clock::week()) {
			return \Clock::week();
		}

		$dates = [];

		foreach(\series\TimesheetLib::getDaysByTask($eTask) as $cTimesheet) {
			foreach($cTimesheet as $date => $eTimesheet) {
				if(self::hasMinute($eTimesheet['total'])) {
					$dates[] = $date;
				}
			}
		}

		if(
			$dates === [] or
			array_intersect(week_dates($week), $dates) !== []
		) {
			return $week;
		}

		return toWeek(max($dates));

	}

	/**
	 * Le temps de l'intervention par jour, toutes personnes, sur les jours qui affichent au moins une minute
	 * pour l'une d'elles (`times`), du plus ancien au plus récent.
	 */
	public static function getWorked(\Collection $cUser): array {

		$worked = [];

		foreach($cUser as $eUser) {
			foreach($eUser['times'] as $date => $time) {
				$worked[$date] = ($worked[$date] ?? 0.0) + $time;
			}
		}

		ksort($worked);

		return $worked;

	}

	/**
	 * Les jours saisis, du plus récent au plus ancien, avec le temps de chaque personne ce jour-là, sur les
	 * mêmes jours que les cases (`times`) : que le total déplie (R-Retours-0710, 157).
	 */
	public static function getTimesByDay(\Collection $cUser): array {

		$days = [];

		foreach($cUser as $eUser) {
			foreach($eUser['times'] as $date => $time) {
				$days[$date][$eUser['id']] = $time;
			}
		}

		krsort($days);

		return $days;

	}

	/**
	 * Une semaine que le tableau peut montrer : une semaine que son année a (pas la 53 d'une année de 52),
	 * depuis la première saison du calendrier de la ferme, où la tête du bloc s'arrête (`getNavigation()`).
	 * Une semaine à venir en est une : ce qu'elle montre se décide chez l'appelant.
	 */
	public static function hasWeek(\structure\Structure $eStructure, ?string $week): bool {

		return (
			$week !== NULL and
			toWeek(week_date_day($week, 1)) === $week and
			week_year($week) >= $eStructure->conf(\map\Configuration::class)->getFirstValidSeason()
		);

	}

	/**
	 * La semaine montrée et ses voisines dans la tête du bloc. Avant la première saison du calendrier de la
	 * ferme, comme au-delà de la semaine en cours, où le temps ne s'écrit pas, il n'y a pas de voisine.
	 */
	public static function getNavigation(\structure\Structure $eStructure, string $weekShown): array {

		$weekBefore = date('o-\WW', strtotime($weekShown.' - 1 WEEK'));
		$weekAfter = date('o-\WW', strtotime($weekShown.' + 1 WEEK'));

		return [
			'week' => $weekShown,
			'before' => self::hasWeek($eStructure, $weekBefore) ? $weekBefore : NULL,
			'after' => ($weekAfter <= \Clock::week()) ? $weekAfter : NULL,
			'isCurrent' => ($weekShown === \Clock::week()),
		];

	}

	/**
	 * Les sept jours de la semaine montrée, chacun aujourd'hui (`today`) ou à venir (`future`), avec le temps
	 * de l'intervention ce jour-là (`time`, d'après `getWorked()`) : la tête des colonnes et le pied du
	 * tableau.
	 */
	public static function getDays(string $weekShown, array $worked): array {

		$today = \Clock::date();
		$days = [];

		foreach(week_dates($weekShown) as $day) {

			$days[$day] = [
				'today' => ($day === $today),
				'future' => ($day > $today),
				'time' => $worked[$day] ?? 0.0,
			];

		}

		return $days;

	}

	/**
	 * Le pied du tableau : le temps de l'intervention sur la semaine montrée, toutes personnes (`time`), et
	 * s'il se montre (`shown`). Il ne se montre que sous deux lignes qui ont du temps dans la semaine : sous
	 * une seule, il redirait cette ligne, et une ligne vide, celle de la personne connectée ou une ligne
	 * ajoutée, n'y ajoute rien.
	 */
	public static function getFoot(\Collection $cUser, array $days): array {

		return [
			'shown' => ($cUser->find(fn(\user\User $eUser) => $eUser['timeWeek'] > 0.0)->count() > 1),
			'time' => array_sum(array_column($days, 'time')),
		];

	}

	/**
	 * Une durée affiche-t-elle au moins une minute ? L'arrondi est celui de l'affichage
	 * (`DurationUi::splitTime()`) : un jour qui compte n'affiche jamais 0h00, et un jour qui affiche 0h01
	 * compte toujours.
	 */
	private static function hasMinute(float $time): bool {
		return (round($time * 60) >= 1);
	}

	/**
	 * Ce qu'une écriture redessine dans le tableau, d'après les personnes avant et après elle, lues sur la
	 * même semaine et avec les mêmes lignes ajoutées : `user`, ce qui tient à la seule personne écrite, tant
	 * que les lignes restent les mêmes dans le même ordre, sinon `grid`, le tableau entier. Une personne qui
	 * reçoit son premier temps de la semaine ou son affectation prend sa ligne, celle qui perd les deux la
	 * rend, et une affectation fait remonter la sienne ; la ligne montrée d'office (`online`) garde la sienne à
	 * son premier temps et après le retrait du dernier. Une ligne ajoutée à la main qu'un temps ou une
	 * affectation retient quitte la liste que le tableau porte (`added`), et lui seul la réécrit.
	 */
	public static function getChange(\Collection $cUserBefore, \Collection $cUserAfter): string {

		$getLines = fn(\Collection $cUser) => $cUser->find(fn(\user\User $eUser) => $eUser['line'])->getIds();
		$getAdded = fn(\Collection $cUser) => $cUser->find(fn(\user\User $eUser) => $eUser['added'])->getIds();

		if(
			$getLines($cUserBefore) === $getLines($cUserAfter) and
			$getAdded($cUserBefore) === $getAdded($cUserAfter)
		) {
			return 'user';
		} else {
			return 'grid';
		}

	}

	/**
	 * La section « Historique » (planche 3b, retour 86-1) : la création de l'intervention, chaque geste que
	 * `series\History` a retenu (fait, défait, écart de temps, écart de récolte) et chaque commentaire, du plus
	 * récent au plus ancien. Les gestes antérieurs au lot L5f n'ont pas de ligne : l'historique commence au
	 * déploiement de la table.
	 *
	 * Chaque événement est un tableau `date`, `event` (`HISTORY_CREATED`, `HISTORY_COMMENT` ou une valeur de
	 * `series\History::$event`), `user` et `element`.
	 */
	public static function getHistory(\series\Task $eTask, \Collection $cComment): array {

		$eTask->expects(['createdAt', 'createdBy']);

		$events = [];

		foreach(\series\HistoryLib::getByTask($eTask) as $eHistory) {
			$events[] = ['date' => $eHistory['date'], 'event' => $eHistory['event'], 'user' => $eHistory['user'], 'element' => $eHistory];
		}

		foreach($cComment as $eComment) {
			$events[] = ['date' => $eComment['createdAt'], 'event' => self::HISTORY_COMMENT, 'user' => $eComment['createdBy'], 'element' => $eComment];
		}

		// Tri stable : à la même seconde, l'ordre des lectures ci-dessus tient, et la création, posée en
		// dernier, reste la plus ancienne ligne.
		$events[] = ['date' => $eTask['createdAt'], 'event' => self::HISTORY_CREATED, 'user' => $eTask['createdBy'], 'element' => $eTask];

		usort($events, fn(array $event1, array $event2) => strcmp($event2['date'], $event1['date']));

		return $events;

	}

	/**
	 * La carte de l'intervention dans la semaine d'où la page a été ouverte, lue comme l'écran de la semaine
	 * la lit (`TaskWeekLib::get()`), pour que la carte remplacée derrière la page soit octet pour octet celle
	 * d'un rechargement. Une intervention qui n'est pas dans cette semaine n'a pas de carte à remettre à jour.
	 */
	public static function getCard(\structure\Structure $eStructure, string $week, \series\Task $eTask): \series\Task {

		$cTask = TaskWeekLib::get($eStructure, $week);

		if($cTask->offsetExists($eTask['id'])) {
			return $cTask[$eTask['id']];
		} else {
			return new \series\Task();
		}

	}

	/**
	 * La récolte que la fiche porte au stock : l'intervention dans son unité, ou, tant qu'elle n'a rien
	 * récolté, dans celle que le champ « Unité » propose d'abord, celle de la culture ou le kilo pour une
	 * espèce seule.
	 */
	public static function getHarvestStock(\series\Task $eTask): \series\Task {

		$eTask->expects(['harvestUnit', 'cultivation']);

		$unit = $eTask['harvestUnit'] ?? ($eTask['cultivation']->empty() ? \series\Task::KG : $eTask['cultivation']['mainUnit']);

		return HarvestStockLib::getReference($eTask, $unit);

	}

	/**
	 * Une récolte d'un jour saisie dans la fiche (R-Retours-0710, 155), d'une seule intervention, par
	 * l'écriture du formulaire complet du planning actuel (`\series\TaskLib::buildHarvests()`). La quantité
	 * s'ajoute au jour (`harvestMore`, exigée par la liste `detail`), ou le remplace (`mode=replace`,
	 * R-Retours-0810, 207) : la quantité du jour (`harvestDay`) n'écrit alors que son écart avec ce que le
	 * jour porte, zéro le vide, et la même quantité n'écrit rien.
	 *
	 * L'écart va au stock choisi (`HarvestStockLib`, DG-4), à la baisse comme à la hausse, dans la même
	 * transaction que la récolte : un stock refusé n'écrit aucune récolte.
	 */
	public static function writeHarvest(\series\Task $eTask, array $input): void {

		$eTask->expects(['action' => ['fqn'], 'harvestUnit', 'cultivation']);

		if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
			throw new \NotExpectedAction('Not a harvest');
		}

		$cTask = new \Collection([$eTask['id'] => $eTask]);

		\series\TaskLib::fillHarvestDates($cTask);
		\series\TaskLib::fillDistribution($cTask);

		$harvestDate = var_filter($input['harvestDate'] ?? NULL, 'string');

		$fw = new \FailWatch();

		if(($input['mode'] ?? NULL) === 'replace') {

			$eTask->build(['harvestDay'], $input, new \PropertyList('detail'));

			// Seul un jour déjà récolté a une ligne à modifier : un autre jour est refusé ici, même quand
			// l'écart nul n'appellerait pas `buildHarvests()`, qui contrôle la date.
			if(array_key_exists($harvestDate, $eTask['harvestDates']) === FALSE) {
				\series\Task::fail('harvestDates.check');
			}

			$fw->validate();

			$harvestMore = round($eTask['harvestDay'] - $eTask['harvestDates'][$harvestDate], 3);

		} else {

			$eTask->build(['harvestMore'], $input, new \PropertyList('detail'));

			$fw->validate();

			$harvestMore = $eTask['harvestMore'];

		}

		if($harvestMore !== 0.0) {
			\series\TaskLib::buildHarvests($cTask, $harvestMore, $harvestDate, var_filter($input['harvestUnit'] ?? NULL, 'string'), 'fair');
		}

		$fw->validate();

		$eTaskStock = self::getHarvestStock($eTask);

		[$eProductStock, $stockRemember] = HarvestStockLib::build($eTask['structure'], $eTaskStock, $input);

		$fw->validate();

		\series\Task::model()->beginTransaction();

			if($harvestMore !== 0.0) {
				\series\TaskLib::update($eTask, ['harvest', 'harvestUnit']);
			}

			HarvestStockLib::write($eTaskStock, $eProductStock, $stockRemember, $harvestMore);

		\series\Task::model()->commit();

	}

}
?>
