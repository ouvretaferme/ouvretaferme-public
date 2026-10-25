<?php
namespace planning;

/**
 * La grille de la Semaine (CW, cadre `#b` de la planche planning-cw-vue-grille, retenu le 14/10) :
 * l'affichage « Tableau » du menu de la Semaine, après « Cartes » et « Deux colonnes », les mêmes interventions que les blocs, une
 * ligne chacune sous l'intertitre de son action, dans l'ordre des blocs (`TaskWeekLib::getBlocks()`).
 *
 * Elle remplace les piles dans `#planning-week-board`, le nœud que redessinent les réponses `board`. Le
 * pense-bête allumé y est le premier groupe (`ReminderUi::getGridGroup()`, DC-bis), et la grille se rend pour
 * lui seul quand aucune intervention n'est à montrer, l'état vide passant dessous. Chaque nœud qu'un geste
 * change a son identifiant stable : le groupe d'une action (`#planning-grid-block-<action>`), dont le compte garde celui du bloc
 * (`TaskWeekUi::getBlockCounter()`), et la ligne (`#planning-grid-task-<id>`).
 *
 * Les colonnes : l'intervention, les personnes affectées, le temps prévu, les sept jours, le récolté, le temps
 * de la semaine, et « Fait ». Une structure qui ne suit pas le temps n'a ni le temps prévu ni celui de la
 * semaine.
 */
class TaskWeekGridUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');
		\Asset::js('main', 'stickyColumnAi.js');

	}

	public function getBoard(\structure\Structure $eStructure, string $week, \Collection $cTask, \Collection $ccTask, array $todo, \Collection $cUser, bool $canCreate, array $canCreateByStatus, bool $empty, ?array $reminders, bool $canReminder, bool $canHarvest): string {

		$h = '<div id="planning-week-board" class="planning-grid-board">';

			if($ccTask->notEmpty() or $reminders !== NULL) {

				$time = $eStructure->hasTime();

				$h .= '<div id="planning-grid" class="util-table util-table-sticky util-table-sticky-column planning-grid'.($time ? '' : ' planning-grid-without-time').'" data-own-scroll-left onrender="PlanningTaskGridScroll.start(this)">';

					$h .= $this->getHead($week, $time);

					if($reminders !== NULL) {
						$h .= new ReminderUi()->getGridGroup($eStructure, $week, $reminders, $canReminder);
					}

					foreach($ccTask as $action => $cTaskAction) {
						$h .= $this->getGroup($eStructure, $week, $cTaskAction, $todo[$action], $cUser, $canHarvest);
					}

				$h .= '</div>';

			}

			if($ccTask->empty()) {

				$h .= '<div class="util-block-empty">';
					$h .= '<h3>'.($cTask->empty() ? s("Aucune intervention cette semaine") : s("Rien ne reste à montrer avec ces filtres")).'</h3>';
					if($empty and $canCreate) {
						$h .= TaskCreateUi::getEmptyButton($eStructure, 'taskWeek', $week, $canCreateByStatus, \structure\Member::GRID);
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les cases des colonnes qui suivent ce qu'elles portent nomment leur colonne (`data-planning-grid-track`) :
	 * « En retard » en prend la largeur de la semaine, et la semaine celle du retard (`PlanningTaskGridTracks`).
	 */
	protected function getHead(string $week, bool $time): string {

		$h = '<div class="util-table-head">';

			$h .= '<div>'.s("Intervention").'</div>';
			$h .= '<div class="planning-grid-head-assign" data-planning-grid-track="assign">'.s("Affecté à").'</div>';

			if($time) {
				$h .= '<div class="planning-grid-end planning-grid-head-assign" data-planning-grid-track="expected">'.s("Prévu").'</div>';
			}

			$h .= $this->getDays($week);
			$h .= '<div class="planning-grid-end" data-planning-grid-track="harvested">'.s("Récolté").'</div>';

			if($time) {
				$h .= '<div class="planning-grid-end" data-planning-grid-track="period">'.s("Semaine").'</div>';
			}

			$h .= '<div class="planning-grid-end" data-planning-grid-track="done">'.s("Fait").'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les sept jours de l'en-tête, en pastilles de date, aujourd'hui pleine dans la teinte de la production,
	 * comme aux Récoltes (`HarvestWeekUi`).
	 */
	protected function getDays(string $week): string {

		$h = '';

		foreach(week_dates($week) as $date) {

			$today = ($date === \Clock::date());

			$h .= '<div class="planning-grid-head-day'.($today ? ' planning-grid-head-today planning-grid-today' : '').'">';
				$h .= \util\DateUi::calendarWeekday($date, selected: $today);
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Les sept jours d'un intertitre, vides : ils portent sur sa hauteur le filet qui sépare les jours dans les
	 * lignes (retour 367) et la teinte d'aujourd'hui. Ils suivent le titre, que la case garde contre elle, et la
	 * feuille les peint avant lui.
	 */
	public static function getTitleDays(string $week): string {

		$h = '';

		foreach(week_dates($week) as $position => $date) {
			$h .= '<span class="planning-grid-title-day'.self::getTodayClass($date).'" style="grid-column: '.($position + 1).' day"></span>';
		}

		return $h;

	}

	/**
	 * Aujourd'hui se teinte sur toute sa colonne, comme aux Récoltes : tête, intertitres, rappels et lignes, faites
	 * comprises (retour 388). La colonne ne se découpe donc pas au fil des lignes, et les jours passés gardent le
	 * fond de leur ligne.
	 */
	public static function getTodayClass(string $date): string {
		return ($date === \Clock::date()) ? ' planning-grid-today' : '';
	}

	/**
	 * La raison des jours sans geste d'une intervention commencée, sous la grille (DF). Le bloc reste en place,
	 * vide, quand aucune intervention n'est verrouillée : cocher, décocher ou saisir un temps change ce qui
	 * est commencé, et la réponse du geste remplace ce bloc sans recharger l'écran.
	 */
	public function getLocked(bool $locked): string {

		$h = '<div id="planning-grid-locked">';

			if($locked) {
				$h .= \util\FormUi::info(s("Une intervention commencée garde son jour."));
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La légende du point du jour prévu, sous la grille, tant que la semaine a un jour à prévoir (DY, retour
	 * 370, demandée).
	 */
	public function getLegend(bool $legend): string {

		if($legend === FALSE) {
			return '';
		}

		$h = '<div id="planning-grid-legend" class="color-muted font-sm">';
			$h .= '<span class="planning-grid-legend-planned"></span>';
			$h .= s("Jour prévu");
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le groupe d'une action, rendu seul quand une intervention neuve y entre (`TaskBatchLib::getCreateChange()`) :
	 * l'intertitre, avec la case qui prend les lignes à faire du groupe et le compte de ce qui reste à faire,
	 * puis une ligne par intervention.
	 */
	public function getGroup(\structure\Structure $eStructure, string $week, \Collection $cTaskAction, int $todo, \Collection $cUser, bool $canHarvest): string {

		$eAction = $cTaskAction->first()['action'];

		$h = '<div class="planning-grid-group" id="planning-grid-block-'.$eAction['id'].'" data-action="'.$eAction['id'].'">';

			$h .= '<div class="util-table-title planning-grid-title">';
				$h .= new TaskBatchUi()->getGroupCheckbox();
				$h .= '<h4>'.TaskWeekUi::getActionCircle($eAction).encode($eAction['name']).' '.new TaskWeekUi()->getBlockCounter($eAction['id'], $todo).'</h4>';
				$h .= self::getTitleDays($week);
			$h .= '</div>';

			foreach($cTaskAction as $eTask) {
				$h .= $this->getRow($eStructure, $week, $eTask, $cUser, $canHarvest);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « En retard » en grille (DZ, retour du 14/10) : un tableau de la grille sous le titre de sa zone, avec son
	 * en-tête, puisque celui de la semaine colle à son propre tableau et s'arrête avec lui, puis un groupe par
	 * action, dans l'ordre des blocs de la zone (`TaskLateLib::getZone()`). Ses colonnes de jour sont celles de la
	 * semaine affichée : une ligne s'y prévoit ou s'y saisit comme une ligne de la semaine, et la rejoint alors.
	 *
	 * Le nœud remplacé est celui des blocs de la zone en cartes (`#planning-week-late-board`), et les groupes en
	 * gardent l'identifiant et les comptes (`TaskWeekUi::getZoneBlockCounter()`), que lisent les réponses et la
	 * recherche (`PlanningTaskSearch.getZoneBlock()`).
	 */
	public function getZoneBoard(\structure\Structure $eStructure, string $week, array $zone, \Collection $cUser, bool $canHarvest): string {

		$time = $eStructure->hasTime();

		$h = '<div id="planning-week-late-board" class="planning-grid-board">';

			$h .= '<div id="planning-grid-late" class="util-table util-table-sticky util-table-sticky-column planning-grid'.($time ? '' : ' planning-grid-without-time').'" data-own-scroll-left onrender="PlanningTaskGridTracks.start(this); PlanningTaskGridScroll.start(this)">';

				$h .= $this->getHead($week, $time);

				foreach(array_keys($zone['blocks']) as $action) {
					$h .= $this->getZoneGroup($eStructure, $week, $zone, $action, $cUser, $canHarvest);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le groupe d'une action dans « En retard », rendu seul par « Voir les N autres » et par les gestes qui le
	 * changent : l'intertitre d'un groupe de la semaine, puis ses lignes. Au-delà du plafond de la zone
	 * (`TaskLateLib::$threshold`), il montre ses plus récentes et finit par une ligne pleine qui charge les autres,
	 * comme le bloc en cartes (`TaskWeekUi::getZoneBlock()`).
	 */
	public function getZoneGroup(\structure\Structure $eStructure, string $week, array $zone, int $action, \Collection $cUser, bool $canHarvest): string {

		$name = $zone['zone'];
		$block = $zone['blocks'][$action];

		$h = '<div class="planning-grid-group" id="planning-week-'.$name.'-block-'.$action.'" data-action="'.$action.'">';

			$h .= '<div class="util-table-title planning-grid-title">';
				$h .= new TaskBatchUi()->getGroupCheckbox();
				$h .= '<h4>'.TaskWeekUi::getActionCircle($block['eAction']).encode($block['eAction']['name']).' '.new TaskWeekUi()->getZoneBlockCounter($name, $action, $block['count']).'</h4>';
				$h .= self::getTitleDays($week);
			$h .= '</div>';

			foreach($block['shown'] as $id) {
				$h .= $this->getRow($eStructure, $week, $zone['cTask'][$id], $cUser, $canHarvest);
			}

			$rest = $block['count'] - count($block['shown']);

			if($rest > 0) {
				$h .= '<a class="util-table-row util-table-add" data-ajax="'.encode(TaskWeekUi::urlMore($eStructure, $week, $name, $action, \structure\Member::GRID)).'" data-ajax-method="get">';
					$h .= '<div><span class="util-table-add-icon">'.\Asset::icon('chevron-down').'</span><span>'.p("Voir l'autre", "Voir les {value} autres", $rest).'</span></div>';
				$h .= '</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne, que la réponse d'un geste rend seule : elle se rend à partir de l'intervention et des
	 * membres, comme la carte (`TaskCardUi`). Le nom mène à la page de l'intervention et porte ce que lit la
	 * recherche (`data-search`).
	 *
	 * Une récolte de série porte sa culture (`data-cultivation`), que la saisie de la colonne « Récolté »
	 * écrit, comme la carte (`TaskCardUi::get()`).
	 *
	 * « Affecté à » et « Prévu » sont rendus une seule fois, dans `.planning-grid-assign` : la feuille en fait
	 * deux cases de la ligne, ou un rang sous le nom sur un écran étroit (DQ), sans doubler la fenêtre de durée.
	 */
	public function getRow(\structure\Structure $eStructure, string $week, \series\Task $eTask, \Collection $cUser, bool $canHarvest): string {

		$eTask->expects(['timesByDate', 'cUserPlanned', 'timePeriod', 'search', 'cComment', 'harvestPeriod']);

		$done = $eTask->isDone();
		$harvest = ($eTask['action']['fqn'] === ACTION_RECOLTE and $eTask['cultivation']->notEmpty());

		// L'espèce que coche un clic sur la vignette (R-Retours-1210 V44), comme sur la carte.
		$plant = $eTask['plant']->notEmpty() ? ' data-plant="'.$eTask['plant']['id'].'"' : '';

		$h = '<div class="util-table-row planning-grid-row'.($done ? ' planning-grid-row-done' : '').'" id="planning-grid-task-'.$eTask['id'].'" data-task="'.$eTask['id'].'"'.$plant.($harvest ? ' data-cultivation="'.$eTask['cultivation']['id'].'"' : '').'>';

			$h .= $this->getName($week, $eTask);

			$h .= '<div class="planning-grid-assign">';

				$h .= $this->getUsers($eTask, $cUser);

				if($eStructure->hasTime()) {
					$h .= $this->getTimeExpected($eTask);
				}

			$h .= '</div>';

			foreach(week_dates($week) as $date) {
				$h .= $this->getDay($eStructure, $week, $eTask, $date);
			}

			$h .= $this->getHarvested($eTask, $harvest and $canHarvest and $eTask->canWrite());

			if($eStructure->hasTime()) {
				$h .= $this->getWeekSum($eTask);
			}

			$h .= $this->getDone($eTask);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre, du plus parlant au dernier recours comme sur la carte (`TaskCardUi::getContent()`) : la série ou
	 * l'espèce, sinon la note, sinon ce que le planning actuel écrit de l'intervention. Le nom de l'action n'en est
	 * jamais un, l'intertitre du groupe le porte déjà (retour 376) : une ligne qui n'a rien de tout cela garde
	 * « Sans description », le lien vers sa page. Sous le titre, dans le balisage de la carte et dans son ordre, le
	 * lieu sur une ligne, ce qui précise l'intervention et ses outils, les variétés d'un semis et son espacement
	 * (FF), la note puis les commentaires (retours 374 et 375), sauf ce que le titre a déjà pris. La semaine prévue
	 * d'une intervention à faire, quand ce n'est pas celle de l'écran, ouvre ce qui précise l'intervention, comme
	 * au pied de la carte (`TaskCardUi::getOtherWeek()`, DZ et FZ). Un droit limité lit le titre sans lien, la
	 * page de l'intervention lui étant refusée (`Task::canRead()`).
	 *
	 * La carte n'a pas de titre quand un membre a passé du temps sur une intervention qui n'a rien d'autre : la
	 * ligne en garde un, seul lien vers la page, et ses personnes ont leur colonne.
	 *
	 * La case de sélection (DG-3) précède la vignette, comme sur la carte, et reste visible : la grille n'a pas
	 * de mode « Sélectionner ». Une colonne à elle déplacerait les gabarits et les seuils de la grille.
	 */
	protected function getName(string $week, \series\Task $eTask): string {

		$uiTask = new \series\TaskUi();

		$subject = TaskCardUi::getSubject($eTask, harvested: FALSE);
		$note = $uiTask->getTaskDescription($eTask);
		$more = TaskCardUi::getMoreOutsideName($eTask);
		$tools = TaskCardUi::hasFacts($eTask) ? '' : $uiTask->getTaskTools($eTask);
		$otherWeek = TaskCardUi::getOtherWeek($eTask, $week);

		if($subject !== '') {
			$title = $subject;
		} else if($note !== '') {
			$title = $note;
			$note = '';
		} else if($more !== '') {
			$title = $more;
			$more = '';
		} else {
			$title = '<span class="color-muted">'.s("Sans description").'</span>';
		}

		$tag = $eTask->canRead() ? 'a' : 'span';

		$h = '<div class="planning-grid-name">';

			$h .= new TaskBatchUi()->getCheckbox($eTask);
			$h .= '<span class="planning-grid-vignette">'.($eTask['plant']->notEmpty() ? \plant\PlantUi::getVignette($eTask['plant'], '1.75rem') : '').'</span>';

			$h .= '<div class="planning-grid-label">';
				$h .= '<'.$tag.($eTask->canRead() ? ' href="'.TaskDetailUi::url($eTask, $week).'"' : '').' '.TaskCardUi::getSearch($eTask).TaskCardUi::getSearchPlant($eTask).'>';
					$h .= $title;
				$h .= '</'.$tag.'>';
				$h .= new TaskCardUi()->getPlace($eTask, $week);

				if($otherWeek !== '' or $more !== '' or $tools !== '') {
					$h .= '<div class="planning-task-more">'.$otherWeek.$more.$tools.'</div>';
				}

				$h .= TaskCardUi::getFacts($eTask);

				if($note !== '') {
					$h .= '<div class="planning-task-note">'.$note.'</div>';
				}

				if($eTask['cComment']->notEmpty()) {
					$h .= new TaskCardUi()->getComments($eTask);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les personnes affectées, dans le déclencheur d'« Affecter à... » (`TaskBatchUi::getUsersDropdown()`)
	 * pour une intervention à faire que la personne connectée peut écrire. Une intervention faite garde ses
	 * vignettes, sans geste.
	 * La liste arrive vide : `PlanningTaskGrid.load()` la fait rendre par `taskWeek:users` à la première
	 * ouverture du menu, une liste de tous les membres sur chaque ligne pesant plus que la grille.
	 */
	protected function getUsers(\series\Task $eTask, \Collection $cUser): string {

		$h = '<div class="planning-grid-users">';

			if($eTask->isTodo() and $eTask->canWrite()) {
				$h .= TaskBatchUi::getUsersDropdown('planning-grid', $eTask['id'], $eTask['cUserPlanned'], $cUser);
			} else if($eTask['cUserPlanned']->notEmpty()) {
				$h .= TaskBatchUi::getUsersVignettes($eTask['cUserPlanned']);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps prévu (DF), réglé dans la cellule par la fenêtre de durée (`taskWeek:doTimeExpected`) tant que
	 * l'intervention reste à faire, comme ses personnes. Une intervention faite garde la valeur qu'elle avait ;
	 * sans valeur, sa case reste vide, comme le pied de la carte (ED, point 4).
	 */
	protected function getTimeExpected(\series\Task $eTask): string {

		$h = '<div class="planning-grid-end planning-grid-expected">';

			if($eTask->isTodo() and $eTask->canWrite()) {
				// La colonne est nullable : une intervention sans temps prévu ouvre la fenêtre à zéro.
				$h .= new DurationUi()->getStepWindow('grid-'.$eTask['id'], $eTask['timeExpected'] ?? 0.0, 'timeExpected', spent: FALSE);
			} else if($eTask['timeExpected'] > 0.0) {
				$h .= \series\TaskUi::convertTime($eTask['timeExpected']);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le contenu de la liste : son titre, puis chaque personne que la personne connectée peut affecter ou
	 * retirer (`canAssign`, `TaskDetailLib::getUsers()`), celles de l'intervention marquées.
	 */
	public function getUsersList(\series\Task $eTask, \Collection $cUser): string {

		$h = '<div class="dropdown-title">'.s("Affecter à...").'</div>';

		foreach($cUser as $eUser) {
			if($eUser['canAssign']) {
				$h .= $this->getUser($eTask, $eUser);
			}
		}

		return $h;

	}

	public function getUser(\series\Task $eTask, \user\User $eUser): string {

		return TaskBatchUi::getUser('planning-grid', $eTask['id'], $eUser, in_array($eUser['id'], $eTask['plannedUsers']));

	}

	/**
	 * La case d'un jour : jusqu'à aujourd'hui, le temps que toutes les personnes y ont passé, rien ensuite.
	 * Le jour prévu porte un point tant qu'il n'est pas passé et que l'intervention reste à faire (DY, EH, EZ,
	 * retours 370 et 379). Sans suivi du temps, une case passée reste vide, sur le fond des jours passés.
	 *
	 * Un jour à venir d'une intervention à faire, pour qui peut l'écrire, la prévoit ce jour-là
	 * (`taskWeek:doPlanned`), et son survol montre le point qu'il prendra ; le jour déjà prévu retire le jour
	 * et garde la semaine (`taskWeek:doUnplanned`, DN), et son survol estompe le point. Une intervention
	 * commencée garde son jour (`Task::acceptPlanned()`) : son point reste, sans geste, et ses autres jours
	 * à venir n'en ont pas.
	 *
	 * Un jour passé ou aujourd'hui, pour qui peut écrire l'intervention, ouvre le tableau de l'équipe de la
	 * fiche réduit à ce jour (`TaskDetailUi::getGrid()`, DC), sur le fond clair d'un menu. Comme celle des
	 * personnes, la liste arrive vide et se rend à l'ouverture (`taskWeek:time`) : elle porte le temps de
	 * chacun, que la case additionne. Aujourd'hui garde ce seul geste, deux cibles dans une case étant trop
	 * petites au téléphone : on l'y prévoit (`getTimeList()`), et un autre jour de la ligne prend sa place. Son
	 * point se range à côté du « + » ou dans le coin du temps. Sans suivi du temps, aujourd'hui n'a pas de menu
	 * et se prévoit comme un jour à venir.
	 *
	 * La case porte un identifiant : l'« Enregistrer » du menu redessine les autres jours de la ligne, et le
	 * contenu du déclencheur du sien, qu'il ne remplace pas (`taskWeek:doTimeTeam`).
	 */
	public function getDay(\structure\Structure $eStructure, string $week, \series\Task $eTask, string $date): string {

		$today = \Clock::date();
		$time = ($eStructure->hasTime() and $date <= $today) ? $eTask['timesByDate'][$date] : 0.0;
		// Le point dit le jour où l'intervention reste à faire : un jour passé porte déjà le temps qu'on y a
		// passé, et une intervention faite n'attend plus aucun jour (retour 335).
		$planned = ($eTask['plannedWeek'] === $week and $eTask['plannedDate'] === $date and $date >= $today and $eTask->isTodo());
		$plan = (
			($date > $today or ($date === $today and $eStructure->hasTime() === FALSE)) and
			$eTask->acceptPlanned() and
			$eTask->canWrite()
		);
		$spend = ($eStructure->hasTime() and $date <= $today and $eTask->canWrite());

		// Un jour passé garde le fond de la ligne.
		if($date > $today) {
			$class = ' planning-grid-future';
		} else {
			$class = self::getTodayClass($date);
		}

		$cell = 'planning-grid-cell'.($planned ? ' planning-grid-planned' : '');
		$spent = ($time > 0.0 ? DurationUi::getSpent($time) : '');

		$h = '<div class="planning-grid-day'.$class.'" id="planning-grid-day-'.$eTask['id'].'-'.$date.'">';

			if($planned and $plan) {
				$h .= '<a class="'.$cell.'" data-planning-grid-unplan></a>';
			} else if($plan) {
				$h .= '<a class="'.$cell.' planning-grid-plannable" data-planning-grid-plan="'.$date.'"></a>';
			} else if($spend) {
				$h .= '<a class="util-quick '.$cell.'" data-dropdown="bottom-start" data-planning-grid-time="'.$date.'">'.$this->getDayTrigger($eTask, $date).'</a>';
				$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light planning-grid-time-list" id="planning-grid-time-'.$eTask['id'].'-'.$date.'" data-task="'.$eTask['id'].'" data-date="'.$date.'" data-dropdown-keep></div>';
			} else {
				$h .= '<span class="'.$cell.'">'.$spent.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que montre le déclencheur du menu d'un jour : le temps que toutes les personnes y ont passé, ou « + ».
	 * Le déclencheur reste quand l'« Enregistrer » du menu écrit (`taskWeek:doTimeTeam`) : `Lime.Dropdown`
	 * fermerait le menu en le voyant quitter la page, seul son contenu se redessine.
	 */
	public function getDayTrigger(\series\Task $eTask, string $date): string {

		$time = $eTask['timesByDate'][$date];

		return ($time > 0.0) ? DurationUi::getSpent($time) : \Asset::icon('plus');

	}

	/**
	 * Le temps de la semaine sur l'intervention, toutes personnes, au bout de la ligne.
	 */
	public function getWeekSum(\series\Task $eTask): string {

		return '<div class="planning-grid-end" id="planning-grid-week-'.$eTask['id'].'">'.($eTask['timePeriod'] > 0.0 ? DurationUi::formatDuration($eTask['timePeriod']) : '').'</div>';

	}

	/**
	 * Le menu d'un jour (`getDay()`), rendu à son ouverture (`taskWeek:time`) et à chaque personne affectée
	 * depuis lui (`taskWeek:doUser`) : les deux réponses réécrivent le même nœud, le titre et la ligne qui
	 * prévoit doivent donc venir des deux. Le menu ne porte qu'un jour, que son titre nomme en entier : le
	 * tableau n'a plus de tête qui le redise (`TaskDetailUi::getGrid()`, retour 404).
	 */
	public function getTimeList(\series\Task $eTask, string $date, \Collection $cUser, array $days, array $foot): string {

		$h = '<div class="dropdown-title">'.s("{weekday} {day}", [
			'weekday' => \util\DateUi::getDayName((int)date('N', strtotime($date))),
			'day' => (int)substr($date, 8, 2)
		]).'</div>';
		$h .= $this->getTimePlan($eTask, $date);
		$h .= new TaskDetailUi()->getGrid($eTask, $cUser, $days, $foot, TaskDetailUi::getGridWeek($eTask, $date));

		return $h;

	}

	/**
	 * La ligne qui prévoit l'intervention aujourd'hui, sous le titre du menu de la case d'aujourd'hui, dont c'est
	 * le seul geste (EH, retour 379). Un jour passé ne se prévoit pas (retour 365), et une intervention commencée
	 * garde son jour (`Task::acceptPlanned()`). Aujourd'hui déjà prévu, le menu n'a rien à prévoir : on change de
	 * jour en cliquant un autre jour de la ligne (retour 404).
	 */
	protected function getTimePlan(\series\Task $eTask, string $date): string {

		if(
			$date !== \Clock::date() or
			$eTask['plannedDate'] === $date or
			$eTask->acceptPlanned() === FALSE
		) {
			return '';
		}

		$h = '<a class="dropdown-item" data-planning-grid-plan="'.$date.'"><span class="planning-grid-legend-planned"></span>'.s("Prévoir aujourd'hui").'</a>';
		$h .= '<div class="dropdown-divider"></div>';

		return $h;

	}

	/**
	 * Le récolté de la semaine. Une récolte de série se saisit de là, par la saisie de l'écran des récoltes
	 * sur sa culture, comme depuis la carte (`PlanningTaskSwipe.harvest()`), quand la semaine accepte une
	 * récolte (`$canHarvest`, lu par la page).
	 *
	 * La quantité prend la forme qu'elle a sur la carte (`\sequence\FlowUi::getMore()`) et aux Récoltes
	 * (`HarvestWeekUi::getHarvested()`), une annotation à la couleur de l'action (ED, point 3) : elle porte son
	 * propre trait, et le lien qui l'entoure n'ajoute pas le pointillé de `util-quick`, que garde le « + ».
	 */
	protected function getHarvested(\series\Task $eTask, bool $canHarvest): string {

		if($eTask['harvestPeriod'] !== NULL and $eTask['harvestPeriod'] > 0.0) {
			$harvested = '<span class="annotation" style="color: '.$eTask['action']['color'].'">'.$eTask->format('harvestPeriod').'</span>';
		} else {
			$harvested = '';
		}

		$h = '<div class="planning-grid-end">';

			if($canHarvest and $harvested !== '') {
				$h .= '<a class="planning-grid-harvest" data-planning-grid-harvest>'.$harvested.'</a>';
			} else if($canHarvest) {
				$h .= '<a class="util-quick planning-grid-harvest" data-planning-grid-harvest>'.\Asset::icon('plus').'</a>';
			} else {
				$h .= $harvested;
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le cachet « Fait » (planche, cadre `#b`) : il coche ou décoche par `doDone` et
	 * `doTodo`, que valide `Task::canWrite()`. Un droit limité le voit éteint, coché ou non, sans geste.
	 */
	protected function getDone(\series\Task $eTask): string {

		$h = '<div class="util-checkbox planning-grid-box">';
			$h .= '<label>';
				$h .= new \util\FormUi()->inputCheckbox(NULL, '1', [
					'checked' => $eTask->isDone(),
					'data-planning-grid-done' => $eTask['id'],
				] + ($eTask->canWrite() ? [] : ['disabled' => 'disabled']));
				$h .= '<span class="planning-grid-stamp">'.\Asset::icon('check').'</span>';
			$h .= '</label>';
		$h .= '</div>';

		return $h;

	}

}
?>
