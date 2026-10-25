<?php
namespace planning;

/**
 * La fiche de temps (L6b, `pageFiche()` de la maquette navigable, avec ses reprises k, o, q, r, t, u et v) :
 * sous les onglets, les personnes en sous-onglets, puis la rangée des jours de la personne choisie
 * (R-Retours-0410-soir, C3, ses heures dépendant d'elle), « Les interventions du » jour choisi en titre
 * (R-Retours-0410-soir, H ; 22/10), la journée, le temps par intervention du jour, « Masquer ce qui est
 * fait », et la liste des interventions, celles du jour puis le reste de la semaine, chaque groupe sous le nom de
 * son action. Seuls la réglette de la journée et le temps par intervention sont dans une carte, le reste se pose
 * sur le fond de page (retour 177).
 *
 * Chaque nœud qu'un geste change a son identifiant stable : la pastille d'un jour
 * (`#planning-time-day-<date>`), la journée (`#planning-time-working-day`), le temps par intervention (`#planning-time-tasks-day`), la liste avec « J'ai aussi fait »
 * (`#planning-time-list`), sa table (`#planning-time-table`) et chacune de ses lignes (`#planning-time-task-<id>`).
 *
 * Sous la liste, les deux zones des Interventions (CF, retour 292) dans `#planning-time-zones` : chacune a sa
 * table (`#planning-time-<zone>-board`), ses intitulés d'action et ses « Voir les N autres » préfixés par son
 * nom. Ses lignes gardent `#planning-time-task-<id>` : une intervention n'est jamais à la fois dans la
 * semaine et dans une zone (`TaskLateLib::getKeys()`).
 */
class TimeDayUi {

	/**
	 * Les heures rondes de la journée de travail, de 0 à 13 (retour 199). La fenêtre les range par sept, comme
	 * la fenêtre des pas, sur deux rangées. Au-delà de 13 h, la journée se tape dans la durée lue.
	 */
	const HOURS = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * L'adresse de la fiche. Sans jour, elle s'ouvre sur le jour par défaut de la semaine
	 * (`TimeDayLib::getDefaultDate()`) ; la personne connectée ne s'écrit pas. `$team` ouvre l'onglet de
	 * l'équipe, et `$weekly` choisit la semaine à la place d'un jour.
	 */
	public static function url(\structure\Structure $eStructure, ?string $week = NULL, ?string $date = NULL, \user\User $eUser = new \user\User(), bool $team = FALSE, bool $weekly = FALSE): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/temps';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		$arguments = [];

		if($weekly) {
			$arguments['period'] = TimeWeekLib::WEEK;
		} else if($date !== NULL) {
			$arguments['date'] = $date;
		}

		if($team) {
			$arguments['user'] = TimeWeekLib::TEAM;
		} else if($eUser->notEmpty() and $eUser->isOnline() === FALSE) {
			$arguments['user'] = $eUser['id'];
		}

		if($arguments !== []) {
			$url .= '?'.http_build_query($arguments);
		}

		return $url;

	}

	/**
	 * L'en-tête de semaine du planning actuel, comme les autres écrans. Changer de semaine garde la
	 * personne ou l'équipe et le masque, et ouvre le jour par défaut de la semaine choisie.
	 */
	public function getCalendar(\structure\Structure $eStructure, string $week, \user\User $eUser, bool $team = FALSE): string {

		return new \series\PlanningUi()->getCalendar(
			$eStructure,
			$week,
			fn($week) => self::url($eStructure, $week, NULL, $eUser, $team)
		);

	}

	/**
	 * L'écran entier. Il porte ce que les gestes réémettent (`data-week`, `data-date`, `data-user`),
	 * pour que leur réponse dessine la fiche que l'on regarde ; « Masquer ce qui est fait » et « Uniquement
	 * assigné à moi » sont des préférences du membre, que l'action relit elle-même. `data-selected` est le jour
	 * choisi, vide quand c'est la semaine ou que le suivi RH seul n'a pas de jour à choisir : la journée saisie dans
	 * la semaine en champs le réémet, sa réponse gardant la pastille choisie et le lien de la semaine tels que
	 * l'écran les montre (`getEntryDay()`).
	 *
	 * La semaine choisie (`$weekly`, R-Retours-1210, 326) ne laisse aucun jour choisi, et le tableau de la
	 * semaine (`getWeekTasks()`) prend la place de tout ce qui dit le jour : seules les journées s'y saisissent,
	 * dans la semaine en champs.
	 *
	 * @param array $entry La semaine en champs (`TimeDayLib::getEntry()`).
	 * @param bool $canWorking Si l'on peut écrire la journée de la personne ce jour-là.
	 * @param bool $canTime Si l'on peut écrire son temps sur les interventions.
	 */
	public function get(\structure\Structure $eStructure, string $week, string $date, \Collection $cUser, \user\User $eUser, array $timeDay, array $entry, \structure\Member $eMember, bool $canWorking, bool $canTime, bool $canCreate, array $zones, bool $weekly = FALSE): string {

		$h = '<div id="planning-time" data-structure="'.$eStructure['id'].'" data-week="'.$week.'" data-date="'.$date.'" data-selected="'.(($weekly or $eStructure->hasTime() === FALSE) ? '' : $date).'" data-user="'.$eUser['id'].'">';

			$h .= new TabUi()->get($eStructure, $week, 'temps', $eMember, $this->getUsers($eStructure, $week, $date, $cUser, $eUser, $weekly));

			$h .= '<div class="planning-time-sheet">';

				$h .= $this->getWeek($eStructure, $week, $weekly ? NULL : $date, $eUser, $timeDay['days'], $timeDay['total'], cWorkingTime: $timeDay['cWorkingTime'], entry: $entry);

				// Le suivi RH seul saisit chaque journée sous sa pastille (planche rh-saisie-temps, A) : la semaine est
				// toute sa fiche, sans intervention à lister.
				if($eStructure->hasTime()) {

					if($weekly) {

						$h .= $this->getWeekHead();
						$h .= $this->getWeekTasks($week, $date, $timeDay['tasksWeek']);

					// Un jour à venir n'a pas de temps par intervention, seulement les nœuds que les gestes remplacent.
					} else if($date > \Clock::date()) {

						$h .= $this->getDayHead($date, $timeDay['day']);

						if(self::hasEntry($eStructure) === FALSE) {
							$h .= $this->getWorkingDay($eStructure, $week, $date, $eUser, $timeDay['day'], $timeDay['eWorkingTime'], $canWorking);
						}

						$h .= $this->getTasksDay($eStructure, $timeDay['day'], $timeDay['actions'], $timeDay['cTaskSpent'], $timeDay['timeSpent']);

						$h .= '<div class="util-block-empty">';
							$h .= '<h3>'.s("Ce jour est à venir").'</h3>';
						$h .= '</div>';

					} else {

						$h .= $this->getDayHead($date, $timeDay['day']);

						if(self::hasEntry($eStructure) === FALSE) {
							$h .= $this->getWorkingDay($eStructure, $week, $date, $eUser, $timeDay['day'], $timeDay['eWorkingTime'], $canWorking);
						}

						$h .= $this->getTasksDay($eStructure, $timeDay['day'], $timeDay['actions'], $timeDay['cTaskSpent'], $timeDay['timeSpent']);

						// Le temps de « J'ai aussi fait » va à la personne de l'onglet : sans droit d'écrire son temps, le
						// geste ne se propose pas sur sa fiche.
						$h .= $this->getTools($eStructure, $week, $date, $eUser, $timeDay, $eMember, ($canCreate and $canTime));
						$h .= $this->getList($eStructure, $week, $date, $eUser, $timeDay, $canTime, ($canCreate and $canTime));
						$h .= $this->getZones($week, $date, $zones, $canTime);

					}

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre du jour de la fiche, seul titre des interventions du jour (22/10). Il reste hors de la carte
	 * du temps par intervention : il colle en haut tant que sa liste défile, et un élément collant ne colle que dans
	 * la boîte qui le porte.
	 *
	 * Une personne absente ce jour-là le lit dessous, comme sous le nom du jour de l'ancien planning
	 * (R-Retours-1210, FH), avec la part du jour qu'elle ne travaille pas. Une absence est prévue, pas une
	 * alerte : la mention reste neutre. Elle est hors du titre, dont la hauteur est fixée pour que la liste
	 * colle sous lui (`--planning-time-title`, `planning.css`). Un jour férié le dit au même endroit (18/10).
	 * `$day` est un jour de `TimeWeekLib::getDay()`.
	 */
	protected function getDayHead(string $date, array $day): string {

		$h = '<div class="util-title planning-time-title">';
			$h .= '<h3>'.s("Les interventions du {day}", ['day' => mb_strtolower(self::getDayTitle($date))]).'</h3>';
		$h .= '</div>';

		if($day['holiday']) {
			$h .= '<div class="mb-1">'.\hr\HolidayUi::getHoliday().'</div>';
		}

		if($day['absent'] or $day['absentPart'] !== NULL) {
			$h .= '<div class="mb-1">'.\hr\AbsenceUi::getAbsent($day['absentPart']).'</div>';
		}

		return $h;

	}

	/**
	 * Le titre de la semaine choisie, au niveau et dans la forme du titre du jour dont le tableau de la semaine
	 * prend la place (21/10). Il reste hors de `#planning-time-week-tasks`, qui porte le tableau ou son
	 * état vide.
	 */
	protected function getWeekHead(): string {

		$h = '<div class="util-title planning-time-title">';
			$h .= '<h3>'.s("Les interventions de la semaine").'</h3>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La fiche de l'équipe (M-Temps, retour 139) : les mêmes onglets et la même rangée des jours dessous, la journée
	 * de chacun sous sa pastille étant celle de l'équipe, puis une ligne par personne pour le jour, où sa
	 * journée se saisit (R158, `getTeamLane()`) ; son nom déplie le détail de son jour, sa fiche s'ouvrant par sa
	 * pastille.
	 *
	 * La semaine choisie (`$weekly`, R-Retours-1210, 309) ne laisse aucun jour choisi, et le tableau de la
	 * semaine (`getWeekUsers()`) prend la place de celui du jour : rien ne s'y saisit.
	 *
	 * Les absents de chaque jour se lisent sous sa pastille, dans la rangée de la semaine (R-Retours-1210, FS), et
	 * le tableau du jour ne garde que `$cUserPresent` (`TimeWeekLib::getPresents()`, FH).
	 */
	public function getTeamSheet(\structure\Structure $eStructure, string $week, string $date, array $timeWeek, \Collection $cUserPresent, \structure\Member $eMember, bool $weekly = FALSE, bool $overtime = FALSE, bool $complementary = FALSE): string {

		$h = '<div id="planning-time" data-structure="'.$eStructure['id'].'" data-week="'.$week.'" data-date="'.$date.'" data-user="'.TimeWeekLib::TEAM.'">';

			$h .= new TabUi()->get($eStructure, $week, 'equipe', $eMember, $this->getUsers($eStructure, $week, $date, $timeWeek['cUserTime'], new \user\User(), $weekly));

			$h .= '<div class="planning-time-sheet">';

				$h .= $this->getWeek($eStructure, $week, $weekly ? NULL : $date, new \user\User(), $timeWeek['days'], $timeWeek['total'], TRUE);

				if($weekly) {
					$h .= $this->getWeekUsers($eStructure, $week, $date, $timeWeek['cUserTime'], $timeWeek['total'], $overtime, $complementary);
				} else if($date > \Clock::date()) {

					$h .= '<div class="util-block-empty">';
						$h .= '<h3>'.s("Ce jour est à venir").'</h3>';
					$h .= '</div>';

				} else {
					$h .= $this->getTeamDay($eStructure, $week, $date, $cUserPresent, $timeWeek['days'][$date]);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'onglet d'une ferme qui ne suit pas le temps (décidé le 17/10) : les personnes, puis les jours de
	 * la semaine, sans heures ni journée à ouvrir (`TimeWeekLib::readAbsences()`), et pour l'équipe les absences
	 * de la semaine dessous (`$absences`). `$eUser` vide est l'équipe.
	 */
	public function getWithoutTime(\structure\Structure $eStructure, string $week, \Collection $cUser, \user\User $eUser, array $days, \structure\Member $eMember, string $absences): string {

		$h = '<div id="planning-time">';

			$h .= new TabUi()->get($eStructure, $week, $eUser->empty() ? 'equipe' : 'temps', $eMember, $this->getUsers($eStructure, $week, NULL, $cUser, $eUser));

			$h .= '<div class="planning-time-sheet">';

				$h .= '<div class="planning-time-week">';
					$h .= '<div class="util-days">';

						$uiTimeWeek = new TimeWeekUi();

						foreach($days as $date => $day) {
							$h .= $uiTimeWeek->getDayWithoutTime($date, $day, $eUser->empty() ? $uiTimeWeek->getDayAbsents($day['cUserAbsent']) : '');
						}

					$h .= '</div>';
				$h .= '</div>';

				$h .= $absences;

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte du jour de l'équipe, que la saisie d'une journée redessine quand elle fait sortir un absent du
	 * tableau (`doWorkingTimeTeam`).
	 */
	public function getTeamDay(\structure\Structure $eStructure, string $week, string $date, \Collection $cUser, array $dayTeam): string {

		$h = '<div class="util-card planning-time-day" id="planning-time-team-day">';
			$h .= $this->getTeam($eStructure, $week, $date, $cUser, $dayTeam);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne par personne, puis le jour de toute l'équipe, en deux colonnes à droite des barres (R167-168) :
	 * Journée, puis Interventions, les mots des deux chiffres de la fiche d'une personne (R-Retours-0410-soir, T,
	 * qui reprend les titres de R158 et de R-Retours-0310, point 20). La journée de chacun se saisit sur sa ligne quand on peut
	 * l'écrire, par la même tuile et la même réglette que sur sa fiche, et par sa tuile seule là où la fiche n'a pas
	 * de réglette (`hasRule()`) : la colonne des barres reste alors vide, comme dans le tableau de la semaine. Sous la ligne de
	 * chacun, le détail de ses interventions du jour, le même que dans la carte de sa fiche (R-Retours-0810, I,
	 * piste 3). Il est hors de la ligne, que la saisie de la journée remplace sans rien changer aux interventions.
	 *
	 * Ce détail est replié à l'arrivée, et le nom de la ligne le déplie (R-Retours-1210, 308 et 430) : à plusieurs,
	 * il noyait les journées, que l'écran sert d'abord à comparer. Une personne seule dans le tableau l'a ouvert
	 * d'office. Rien n'est retenu d'un jour à l'autre.
	 *
	 * Chaque personne porte `cTaskSpent` (`TimeWeekLib::fillTasksSpent()`).
	 */
	protected function getTeam(\structure\Structure $eStructure, string $week, string $date, \Collection $cUser, array $dayTeam): string {

		$h = '<div class="planning-week-time-lanes planning-time-team">';

			$h .= new TimeWeekUi()->getRuleHead(columns: TRUE, tasks: $eStructure->hasTime(), axis: self::hasRule($eStructure));

			$open = ($cUser->count() === 1);

			foreach($cUser as $eUser) {

				$h .= '<div class="util-fold planning-time-team-fold '.($open ? 'util-fold-open' : '').'">';
					$h .= $this->getTeamLane($eStructure, $week, $date, $eUser);
					if($eUser['cTaskSpent']->notEmpty()) {
						$h .= '<div class="util-fold-content">'.$this->getTasksSpent($eUser['cTaskSpent']).'</div>';
					}
				$h .= '</div>';

			}

			$h .= $this->getTeamTotal($eStructure, $dayTeam);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La ligne d'une personne dans l'équipe, le jour de la fiche, que la saisie de sa journée remplace
	 * (`doWorkingTimeTeam`). Sa barre n'écrit pas la durée des interventions, que leur colonne porte : un
	 * chiffre une fois par ligne (go de V-Planning sur R158, comme Q1 de R167-168). Des interventions au-delà
	 * de la journée passent la journée en rouge, puisque c'est elle qu'on corrige, et la barre dessine leur
	 * dépassement en pointillés rouges (`overflow`, R-Retours-1210, GJ et GJ-bis).
	 *
	 * La vignette, le prénom et le chevron sont un seul lien, qui déplie le détail (R-Retours-1210, 430) : sa fiche
	 * s'ouvre par sa pastille, au-dessus. Ce lien est rendu sans condition, la saisie d'une journée redessinant la
	 * ligne seule, sans relire les interventions du jour : la feuille le rend inerte et masque son chevron quand
	 * le pli n'a rien à déplier (`planning.css`).
	 *
	 * `$eUser` porte `timeDays` et `cWorkingTime` (`TimeWeekLib::read()`) et `canWorking` (`TimeDayLib::canWorking()`).
	 */
	public function getTeamLane(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser): string {

		$day = $eUser['timeDays'][$date];

		$classes = ['planning-week-time-lane'];

		if($day['working'] === NULL) {
			$classes[] = 'planning-week-time-lane-missing';
		}

		$h = '<div class="'.implode(' ', $classes).'" id="planning-time-team-lane-'.$eUser['id'].'">';

			$h .= '<div class="planning-week-time-lane-name">';
				$h .= '<a class="planning-time-fold-head planning-time-team-user">';
					$h .= $this->getTeamName($eUser);
					$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '</a>';
			$h .= '</div>';

			if(self::hasRule($eStructure)) {
				$h .= new TimeWeekUi()->getRule($day, self::getWorkingKey($date, $eUser), self::canRule($eStructure, $eUser['canWorking']));
			} else {
				$h .= '<div class="planning-week-time-lane-spacer"></div>';
			}

			$h .= '<div class="planning-week-time-lane-field">';
				$h .= $this->getWorking($eStructure, $week, $date, $eUser, TimeWeekLib::TEAM, $day['working'], $eUser['canWorking'], $day['overflow'], $eUser['cWorkingTime'][$date]);
			$h .= '</div>';

			$h .= '<div class="planning-week-time-lane-time">'.($day['time'] > 0.0 ? DurationUi::getSpent($day['time']) : '').'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le jour de l'équipe, ses deux totaux sous leur colonne, nus : le titre de la colonne les nomme, et la ligne ne
	 * porte pas le nom du jour, que la fiche titre déjà (18/10). Un jour où personne n'a saisi sa journée
	 * laisse la colonne Journée vide.
	 */
	public function getTeamTotal(\structure\Structure $eStructure, array $dayTeam): string {

		$h = '<div class="planning-week-time-lane planning-week-time-lane-total" id="planning-time-team-total">';
			$h .= '<div class="planning-week-time-lane-name"></div>';
			$h .= '<div class="planning-week-time-lane-spacer"></div>';
			$h .= '<div class="planning-week-time-lane-field">'.($dayTeam['working'] === NULL ? '' : DurationUi::formatDuration($dayTeam['working'])).'</div>';
			$h .= '<div class="planning-week-time-lane-time">'.($eStructure->hasTime() ? DurationUi::getSpent($dayTeam['time']) : '').'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La semaine : un titre de section, puis son contenu posé sur la page, sans tuile (22/10). Le titre a
	 * la forme de celui du jour sans en être collant : collés au même endroit, ils se recouvriraient au défilement.
	 *
	 * La semaine d'une personne s'intitule « Temps de travail de la semaine » et se borne en largeur
	 * (`planning-time-week-user`), pour que ses sept jours ne flottent pas sur un grand écran (R-Retours-1210 V37).
	 * Saisie en durée (`hasEntry()`), la rangée est la semaine en champs (planche rh-heures-pistes, piste B,
	 * 19/10), qui saisit la journée de chaque jour à son champ, et sa somme suit le titre
	 * (`getEntryTotal()`, V37, piste B). Sous horaires comme pour l'équipe (`$team`), la phrase de la somme précède
	 * la rangée des jours, et l'équipe garde son titre, sa somme étant celle de plusieurs personnes.
	 * La somme mène à la semaine choisie à la place d'un jour, sur la fiche de l'équipe (R-Retours-1210, 309)
	 * comme sur celle d'une personne (326) : `$date` est alors vide, et aucun jour de la rangée n'est choisi.
	 * `$cWorkingTime` sont les journées saisies de la personne (`TimeDayLib::get()`), `$entry` sa semaine en
	 * champs (`TimeDayLib::getEntry()`).
	 */
	protected function getWeek(\structure\Structure $eStructure, string $week, ?string $date, \user\User $eUser, array $days, array $total, bool $team = FALSE, \Collection $cWorkingTime = new \Collection(), array $entry = []): string {

		$entered = ($eUser->notEmpty() and self::hasEntry($eStructure));

		$h = '<div class="planning-time-week'.($eUser->notEmpty() ? ' planning-time-week-user' : '').'">';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.($eUser->notEmpty() ? s("Temps de travail de la semaine") : s("Temps de travail par jour")).'</h3>';
				if($entered) {
					$h .= $this->getEntryTotal($eStructure, $week, $eUser, $total, $entry, $date === NULL);
				}
			$h .= '</div>';

			if($entered) {

				$h .= $this->getDays($eStructure, $week, $date, $eUser, $days, $cWorkingTime, $entry);

			} else {

				$h .= $this->getWeekSum($eStructure, $eUser, $total, self::url($eStructure, $week, eUser: $eUser, team: $team, weekly: TRUE), $date === NULL);
				$h .= $this->getDays($eStructure, $week, $date, $eUser, $days, $cWorkingTime);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La semaine en champs vaut pour une personne sur une structure qui saisit ses journées en durée. Sous horaires,
	 * une journée se dit par son arrivée, son départ et sa pause, que deux champs de durée ne portent pas : elle garde
	 * sa tuile et, sous le temps de travail, sa carte au-dessus des interventions (`getWorkingDay()`).
	 */
	public static function hasEntry(\structure\Structure $eStructure): bool {
		return ($eStructure->hasTimeRange() === FALSE);
	}

	/**
	 * La somme de la semaine en champs, qui suit son titre : la durée saisie ; la jauge, sur l'horaire d'un
	 * contrat à la semaine, la part saisie puis la part à saisir hachurée ; et ce qu'il reste à saisir, la jauge
	 * et la note passant ensemble à la ligne quand le titre ne les tient pas (`util-days-entry-detail`). Sans
	 * contrat lu, ni « sur » ni jauge (`TimeDayLib::getEntry()`). On passe d'une semaine à l'autre par l'en-tête,
	 * que la somme ne redouble pas (19/10).
	 *
	 * Sous le temps de travail, la durée est le lien qui choisit la semaine à la place d'un jour (R-Retours-1210,
	 * 326), sauf quand elle l'est déjà (`$weekly`) : le suivi RH seul n'a pas d'interventions à y détailler.
	 */
	public function getEntryTotal(\structure\Structure $eStructure, string $week, \user\User $eUser, array $total, array $entry, bool $weekly): string {

		$working = DurationUi::formatDuration($total['working'] ?? 0.0);

		$h = '<div class="util-days-entry-total" id="planning-time-week-total">';

			if($eStructure->hasTime() and $weekly === FALSE) {
				$h .= '<a href="'.self::url($eStructure, $week, eUser: $eUser, weekly: TRUE).'" class="util-days-entry-value">'.$working.'</a>';
			} else {
				$h .= '<b class="util-days-entry-value">'.$working.'</b>';
			}

			$detail = '';
			$notes = [];

			if($entry['hours'] !== NULL) {

				$detail .= '<div class="util-progress">';
					$detail .= '<div class="util-progress-part bg-done" style="width: '.$entry['done'].'%"></div>';
					$detail .= '<div class="util-progress-part util-progress-part-todo" style="width: '.$entry['todo'].'%"></div>';
				$detail .= '</div>';

				$notes[] = s("sur {value}", DurationUi::formatDuration($entry['hours']));

			}

			if($entry['missing'] > 0) {
				$notes[] = p("{value} jour à saisir", "{value} jours à saisir", $entry['missing']);
			}

			if($notes !== []) {
				$detail .= '<span class="util-days-entry-note">'.implode(', ', $notes).'</span>';
			}

			if($detail !== '') {
				$h .= '<div class="util-days-entry-detail">'.$detail.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La somme des journées de la semaine (`TimeWeekLib::getTotal()`) dite en une phrase, que chaque saisie d'une
	 * journée ou d'un temps redessine (18/10). Celle des interventions n'y est pas (R-Retours-1210, FS,
	 * retour 425). La somme est le lien qui choisit la semaine à la place d'un jour (`$url`), sauf quand elle l'est
	 * déjà (`$selected`) et sur la fiche d'une personne du suivi RH seul, où la semaine choisie n'a pas
	 * d'interventions à détailler. Sans personne (`$eUser` vide), c'est l'équipe. Une semaine sans journée saisie
	 * n'a pas de somme à lier.
	 */
	public function getWeekSum(\structure\Structure $eStructure, \user\User $eUser, array $total, string $url, bool $selected = FALSE): string {

		$h = '<p class="planning-time-week-sum" id="planning-time-week-total">';

			if($total['working'] === NULL) {

				if($eUser->empty()) {
					$h .= s("L'équipe n'a pas encore indiqué de temps de travail par jour !");
				} else if($eUser->isOnline()) {
					$h .= s("Vous n'avez pas encore indiqué de temps de travail par jour !");
				} else {
					$h .= s("{firstName} n'a pas encore indiqué de temps de travail par jour !", ['firstName' => $eUser->getFirstName()]);
				}

			} else {

				$value = s("{value} cette semaine", DurationUi::formatDuration($total['working']));

				if($selected or ($eUser->notEmpty() and $eStructure->hasTime() === FALSE)) {
					$value = '<b>'.$value.'</b>';
				} else {
					$value = '<a href="'.encode($url).'">'.$value.'</a>';
				}

				if($eUser->empty()) {
					$h .= s("L'équipe a travaillé {value}", ['value' => $value]);
				} else if($eUser->isOnline()) {
					$h .= s("Vous avez travaillé {value}", ['value' => $value]);
				} else {
					$h .= s("{firstName} a travaillé {value}", ['firstName' => $eUser->getFirstName(), 'value' => $value]);
				}

			}

		$h .= '</p>';

		return $h;

	}

	/**
	 * Le tableau de la semaine, à la place de celui du jour quand la semaine est choisie : chaque personne de
	 * l'équipe, son nom, qui ouvre sa fiche, puis ses deux sommes sous les mots de celles de l'équipe, et en pied
	 * la semaine de toute l'équipe (`$total`). Une somme à saisir ou nulle laisse sa case vide, comme au-dessus.
	 * La carte reprend la grille du tableau du jour (`getTeam()`), colonne des barres laissée vide : d'un jour à
	 * la semaine, les deux colonnes ne bougent pas.
	 *
	 * Chaque personne porte `timeTotal` (`TimeWeekLib::read()`).
	 *
	 * Au gérant (`$overtime`), la semaine se compare au contrat (`\hr\OvertimeLib::fillByWeek()`, qui pose
	 * `eContract` et `overtime` sur chaque personne) : le contrat devant la semaine, puis après les interventions
	 * les heures complémentaires, quand un contrat de l'équipe est sous la durée légale (`$complementary`), et
	 * les heures supplémentaires.
	 */
	public function getWeekUsers(\structure\Structure $eStructure, string $week, string $date, \Collection $cUser, array $total, bool $overtime = FALSE, bool $complementary = FALSE): string {

		$classes = ['planning-week-time-lanes'];

		if($overtime) {
			$classes[] = 'planning-time-overtime';
		}

		if($complementary) {
			$classes[] = 'planning-time-overtime-complementary';
		}

		$h = '<div class="util-card planning-time-week-users" id="planning-time-week-users">';
			$h .= '<div class="'.implode(' ', $classes).'">';

				$h .= '<div class="planning-week-time-lane planning-time-rule-head">';
					$h .= '<div class="planning-week-time-lane-name"></div>';
					$h .= '<div class="planning-week-time-lane-spacer"></div>';
					if($overtime) {
						$h .= '<div class="planning-week-time-lane-contract">'.s("Contrat").'</div>';
					}
					$h .= '<div class="planning-week-time-lane-field">'.s("Semaine").'</div>';
					// Vide plutôt qu'absente sans temps de travail : les cellules d'une ligne se posent à la suite dans la
					// grille (`display: contents`), et une de moins décalerait les heures supplémentaires.
					$h .= '<div class="planning-week-time-lane-time">'.($eStructure->hasTime() ? s("Interventions") : '').'</div>';
					if($complementary) {
						$h .= '<div class="planning-week-time-lane-complementary">'.s("Complémentaires").'</div>';
					}
					if($overtime) {
						$h .= '<div class="planning-week-time-lane-extra">'.s("Supplémentaires").'</div>';
					}
				$h .= '</div>';

				foreach($cUser as $eUser) {

					$working = $eUser['timeTotal']['working'] ?? 0.0;
					$time = $eUser['timeTotal']['time'];

					$h .= '<div class="planning-week-time-lane">';
						$h .= '<div class="planning-week-time-lane-name">'.$this->getTeamUser($eStructure, $week, $date, $eUser).'</div>';
						$h .= '<div class="planning-week-time-lane-spacer"></div>';
						if($overtime) {
							$h .= '<div class="planning-week-time-lane-contract">'.$this->getOvertimeContract($eUser['eContract'], $eUser['overtime']).'</div>';
							$h .= '<div class="planning-week-time-lane-field">'.$this->getOvertimeWeek($working).'</div>';
						} else {
							$h .= '<div class="planning-week-time-lane-field">'.($working > 0.0 ? DurationUi::formatDuration($working) : '').'</div>';
						}
						$h .= '<div class="planning-week-time-lane-time">'.($time > 0.0 ? DurationUi::getSpent($time) : '').'</div>';
						if($complementary) {
							$h .= '<div class="planning-week-time-lane-complementary">'.$this->getOvertimeHours($eUser['overtime']['complementary']).'</div>';
						}
						if($overtime) {
							$h .= '<div class="planning-week-time-lane-extra">'.$this->getOvertimeExtra($eUser['overtime']).'</div>';
						}
					$h .= '</div>';

				}

				$working = $total['working'] ?? 0.0;

				$h .= '<div class="planning-week-time-lane planning-week-time-lane-total">';
					$h .= '<div class="planning-week-time-lane-name">'.s("Total").'</div>';
					$h .= '<div class="planning-week-time-lane-spacer"></div>';
					if($overtime) {
						$h .= '<div class="planning-week-time-lane-contract"></div>';
					}
					$h .= '<div class="planning-week-time-lane-field">'.($working > 0.0 ? DurationUi::formatDuration($working) : '').'</div>';
					$h .= '<div class="planning-week-time-lane-time">'.($total['time'] > 0.0 ? DurationUi::getSpent($total['time']) : '').'</div>';
					if($complementary) {
						$h .= '<div class="planning-week-time-lane-complementary"></div>';
					}
					if($overtime) {
						$h .= '<div class="planning-week-time-lane-extra"></div>';
					}
				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le contrat de la semaine, ses heures, et pour un contrat annualisé son solde depuis le début de la période de
	 * référence ou du contrat. Sans contrat ou sans heures, rien.
	 */
	protected function getOvertimeContract(\hr\Contract $eContract, array $overtime): string {

		if($eContract->empty()) {
			return '';
		}

		$hours = match($eContract['hoursPeriod']) {
			\hr\Contract::WEEK => $eContract['weeklyHours'],
			\hr\Contract::YEAR => $eContract['annualHours']
		};

		if($hours === NULL) {
			return '';
		}

		$value = \util\TextUi::number($hours, NULL);

		$h = '<div class="planning-time-overtime-cell">';

			$h .= match($eContract['hoursPeriod']) {
				\hr\Contract::WEEK => s("{value} h", $value),
				\hr\Contract::YEAR => s("{value} h par an, annualisé", $value)
			};

			if($overtime['balance'] !== NULL) {

				$balance = ($overtime['balance'] < 0 ? '-' : '+').DurationUi::formatDuration(abs($overtime['balance']));

				$h .= '<div class="color-muted">'.s("solde {balance} depuis le {date}", ['balance' => $balance, 'date' => \util\DateUi::textual($overtime['balanceStart'], \util\DateUi::DAY_MONTH)]).'</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getOvertimeWeek(float $working): string {
		return '<div class="planning-time-overtime-cell">'.($working > 0.0 ? DurationUi::formatDuration($working) : '').'</div>';
	}

	/**
	 * Des heures complémentaires ou supplémentaires : un tiret quand il n'y en a pas, rien quand le contrat ne
	 * les compte pas.
	 */
	protected function getOvertimeHours(?float $hours): string {

		if($hours === NULL) {
			return '';
		}

		if($hours <= 0.0) {
			return '<span class="color-muted">-</span>';
		}

		return DurationUi::formatDuration($hours);

	}

	protected function getOvertimeExtra(array $overtime): string {

		$h = '<div class="planning-time-overtime-cell">';

			$h .= $this->getOvertimeHours($overtime['extra']);

			if($overtime['extraHigh'] > 0.0) {
				$h .= '<div class="color-muted">'.s("dont {value} au-delà de {high} h", ['value' => DurationUi::formatDuration($overtime['extraHigh']), 'high' => \util\TextUi::number(\hr\OvertimeLib::HIGH, NULL)]).'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le tableau de la semaine d'une personne, à la place de son jour quand la semaine est choisie (R-Retours-1210,
	 * 326) : les interventions où elle a du temps dans la semaine, sous leur action comme dans la liste du jour,
	 * la ligne entière ouvrant la fiche de l'intervention par le lien de son nom, que la feuille étend à elle
	 * (`planning-time-week-row`), puis leur somme en pied. Le temps s'y lit et aucune ligne ne se coche : on saisit
	 * dans un jour ou dans la fiche de l'intervention. Une ligne faite garde son fond.
	 *
	 * `$tasksWeek` vient de `TimeDayLib::getTasksWeek()`.
	 */
	public function getWeekTasks(string $week, string $date, array $tasksWeek): string {

		if($tasksWeek['ccTaskLine']->empty()) {

			$h = '<div class="util-block-empty" id="planning-time-week-tasks">';
				$h .= '<h3>'.s("Aucun temps saisi sur une intervention cette semaine").'</h3>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-table util-table-bordered planning-time-table planning-time-table-wide planning-time-week-tasks" id="planning-time-week-tasks">';

			$h .= '<div class="util-table-head">';
				$h .= '<div class="planning-time-head-name">'.s("Intervention").'</div>';
				$h .= '<div>'.s("Temps passé").'</div>';
			$h .= '</div>';

			foreach($tasksWeek['ccTaskLine'] as $cTaskLine) {

				$h .= $this->getActionTitle($cTaskLine->first()['action'], 'planning-time-week');

				foreach($cTaskLine as $eTask) {

					$h .= '<div class="util-table-row planning-time-row planning-time-week-row'.($eTask['actionFirst'] ? ' util-table-break' : '').($eTask->isDone() ? ' planning-time-row-done' : '').'">';
						$h .= '<div class="planning-time-action">';
							if($eTask['actionFirst']) {
								$h .= '<span class="planning-day-action-name">'.encode($eTask['action']['name']).'</span>';
							}
						$h .= '</div>';
						$h .= '<div class="planning-time-name">';
							$h .= $this->getName($eTask, $week, $this->getLabel($eTask, $date), FALSE);
						$h .= '</div>';
						$h .= '<div class="planning-time-time">';
							$h .= '<span class="planning-time-value">'.DurationUi::getSpent($eTask['timeWeek']).'</span>';
						$h .= '</div>';
					$h .= '</div>';

				}

			}

			$h .= '<div class="util-table-row planning-time-row util-table-break planning-time-week-tasks-total">';
				$h .= '<div class="planning-time-action"></div>';
				$h .= '<div class="planning-time-name">';
					$h .= '<span class="planning-time-vignette"></span>';
					$h .= '<div class="planning-time-label">'.s("Total").'</div>';
				$h .= '</div>';
				$h .= '<div class="planning-time-time">';
					$h .= '<span class="planning-time-value">'.DurationUi::getSpent($tasksWeek['total']).'</span>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une personne de l'équipe, sa vignette et son prénom, qui ouvrent sa fiche du jour.
	 */
	protected function getTeamUser(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser): string {
		return '<a href="'.self::url($eStructure, $week, $date, $eUser).'" class="planning-time-team-user">'.$this->getTeamName($eUser).'</a>';
	}

	/**
	 * La vignette et le prénom d'une personne, les mêmes au tableau du jour, qui déplie son détail, et à celui de
	 * la semaine, qui ouvre sa fiche.
	 */
	protected function getTeamName(\user\User $eUser): string {
		return \user\UserUi::getVignette($eUser, '1.5rem').encode($eUser['firstName'] ?? $eUser['lastName']);
	}

	public static function getDayTitle(string $date, bool $year = FALSE): string {

		return \util\DateUi::getDayName((int)date('N', strtotime($date))).' '.\util\DateUi::textual($date, $year ? \util\DateUi::DATE : \util\DateUi::DAY_MONTH);

	}

	public static function getPanelDay(string $date): string {

		$time = strtotime($date);
		$day = (int)date('j', $time);

		return \util\DateUi::getDayName((int)date('N', $time)).' '.(($day === 1) ? s("1er") : $day);

	}

	/**
	 * La rangée des jours (`TimeWeekUi::getDay()`) : chaque jour sa journée, la colonne de ses interventions et
	 * sa pastille, celle du jour de la fiche remplie. Chaque jour est un lien, un jour à venir compris : on
	 * ouvre sa fiche pour le consulter, la saisie y restant refusée. Sans personne (`$eUser` vide), c'est
	 * l'équipe, dont les liens gardent l'onglet ; sans jour (`$date` vide), la semaine est choisie à leur place.
	 * `$cWorkingTime` sont les journées saisies de la personne, par date (`TimeDayLib::get()`).
	 *
	 * Pour une personne hors horaires, c'est la semaine en champs (`util-days-entry`, `getEntryDay()`), dont
	 * `$entry` dit les jours à saisir (`TimeDayLib::getEntry()`).
	 */
	public function getDays(\structure\Structure $eStructure, string $week, ?string $date, \user\User $eUser, array $days, \Collection $cWorkingTime = new \Collection(), array $entry = []): string {

		$entered = ($eUser->notEmpty() and self::hasEntry($eStructure));

		$h = '<div class="util-days'.($entered ? ' util-days-entry' : '').'">';

			foreach($days as $dayDate => $day) {

				// L'équipe n'a pas de journée à elle, seule une personne en saisit une.
				$eWorkingTime = $eUser->empty() ? new \hr\WorkingTime() : $cWorkingTime[$dayDate];

				$h .= $this->getDay($eStructure, $week, $dayDate, $dayDate === $date, $eUser, $day, $eWorkingTime, $entry);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un jour de la rangée. Un jour de l'équipe (`$eUser` vide) porte ses absents (`cUserAbsent`,
	 * `TimeWeekLib::read()`), montrés sous sa pastille (R-Retours-1210, FS) ; celui d'une personne dit son
	 * absence par sa colonne.
	 *
	 * Pour une personne hors horaires, le jour est celui de la semaine en champs (`getEntryDay()`), qui lit dans
	 * `$entry` s'il est à saisir. Sous horaires et au suivi RH seul, le jour d'une personne est la tuile de sa
	 * journée (planche rh-saisie-temps, A), comme dans le récapitulatif du mois (`\hr\RecapUi::getDay()`) : il
	 * ouvre la saisie au lieu de mener à une fiche qui n'aurait rien d'autre à montrer, et se lit seulement quand
	 * la journée ne s'écrit pas (`canWorking`, `TimeDayLib::get()`). Aucun jour n'y est choisi : celui dont la
	 * saisie est ouverte prend la pastille pleine (`planning.css`). `$eWorkingTime` est sa journée saisie, dont une
	 * structure à horaires ouvre les horaires.
	 */
	public function getDay(\structure\Structure $eStructure, string $week, string $date, bool $selected, \user\User $eUser, array $day, \hr\WorkingTime $eWorkingTime = new \hr\WorkingTime(), array $entry = []): string {

		$uiTimeWeek = new TimeWeekUi();

		$id = 'planning-time-day-'.$date;

		if($eUser->notEmpty() and self::hasEntry($eStructure)) {
			return $this->getEntryDay($eStructure, $week, $date, $selected, $eUser, $day, $entry['warnings'][$date]);
		}

		if($eUser->notEmpty() and $eStructure->hasTime() === FALSE) {

			if($day['canWorking'] === FALSE) {
				return $uiTimeWeek->getDay($date, $day, $id, '', FALSE, '', FALSE);
			}

			// Le nœud que la saisie remplace est la tuile entière, qui porte la journée envoyée.
			$tile = $uiTimeWeek->getDay($date, $day, '', 'data-planning-duration-open', FALSE, '', FALSE);

			return $this->getWorking($eStructure, $week, $date, $eUser, origin: NULL, workingTime: $day['working'], canWorking: TRUE, overflow: $day['overflow'], eWorkingTime: $eWorkingTime, tile: $tile, id: $id);

		}

		$absents = $eUser->empty() ? $uiTimeWeek->getDayAbsents($day['cUserAbsent']) : '';

		return $uiTimeWeek->getDay($date, $day, $id, 'href="'.self::url($eStructure, $week, $date, $eUser, $eUser->empty()).'"', $selected, $absents, $eStructure->hasTime());

	}

	/**
	 * Un jour de la semaine en champs (planche rh-heures-pistes, piste B, 19/10) : sa pastille, puis la
	 * tuile de sa journée, qui ouvre la saisie d'une durée comme partout ailleurs (`getWorking()`, 21/10 :
	 * le champ tapé ouvrait la liste d'autocomplétion du navigateur). Elle s'enregistre par `timeDay:doWorkingTime`,
	 * dont la réponse redessine le jour entier.
	 *
	 * Sous le temps de travail, la pastille choisit le jour, dont la fiche montre les interventions dessous ; le
	 * suivi RH seul n'a pas d'autre page à ouvrir, ni de jour choisi. Un jour à saisir (`$warning`) dit « à saisir »
	 * sur sa tuile, au ton courant (22/10). C'est le jour que compte la somme (`TimeDayLib::getEntry()`) :
	 * une tuile vide qu'elle ne compte pas, aujourd'hui, un jour férié ou hors contrat, garde son crayon seul, pour
	 * que l'écran et la somme disent le même nombre. Un jour d'absence se hachure et se lit « Absent » sans tuile,
	 * sauf si une journée y est saisie, qui reste à corriger. Une journée qui ne s'écrit pas, un jour à venir ou la
	 * journée d'une autre personne sans le planning complet, se lit sans tuile. Des interventions au-delà de la
	 * journée la passent en alerte (`is-over`, R-Retours-1210, GJ).
	 */
	protected function getEntryDay(\structure\Structure $eStructure, string $week, string $date, bool $selected, \user\User $eUser, array $day, bool $warning): string {

		$classes = ['util-day'];

		if($day['today']) {
			$classes[] = 'is-today';
		}

		if($day['future']) {
			$classes[] = 'is-future';
		}

		if($day['absent']) {
			$classes[] = 'is-absent';
		}

		if($day['overflow']) {
			$classes[] = 'is-over';
		}

		$calendar = \util\DateUi::calendarWeekday($date, ($selected and $eStructure->hasTime()), large: TRUE, value: $day['holiday'] ? \hr\HolidayUi::getHoliday() : NULL);

		if($eStructure->hasTime()) {
			$calendar = '<a href="'.self::url($eStructure, $week, $date, $eUser).'" class="util-days-entry-date">'.$calendar.'</a>';
		}

		if($day['canWorking'] and ($day['absent'] === FALSE or $day['working'] !== NULL)) {

			$value = $this->getWorking($eStructure, $week, $date, $eUser, origin: NULL, workingTime: $day['working'], canWorking: TRUE, overflow: $day['overflow'], missing: $warning);

		} else if($day['absent']) {
			$value = '<span class="color-muted">'.\hr\AbsenceUi::getAbsent(NULL).'</span>';
		} else if($day['working'] !== NULL) {
			$value = DurationUi::formatDuration($day['working']);
		} else {
			$value = '&nbsp;';
		}

		$h = '<div id="planning-time-day-'.$date.'" class="'.implode(' ', $classes).'">';
			$h .= $calendar;
			$h .= '<div class="util-day-value">'.$value.'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'équipe en tête, au mot seul, « Tous » pour ne pas redire l'onglet Équipe du dessus (R-Retours-1210, FP),
	 * choisie quand aucune personne ne l'est (M-Temps, retour 139) ; puis une personne par onglet, sa vignette
	 * et son prénom (reprise q). Une fiche d'une seule personne n'a pas d'onglets. Une rangée qui ne tient plus dans sa largeur se replie en un onglet-menu
	 * qui porte l'onglet choisi (`tabs-h-fold`) : au téléphone, seul l'onglet choisi garde ainsi son prénom à
	 * l'écran, et la liste du menu nomme chacun. Pas de `title` : le repli l'ajoute au texte d'un onglet qui ne
	 * le contient pas, et le nom complet aurait redoublé le prénom (« Cassandre Cassandre Chignec »). L'espace
	 * entre la vignette et le prénom ne compte pas dans la rangée, en flex, mais sépare les deux dans le menu et
	 * dans sa liste, que le repli met à plat.
	 *
	 * Dans la semaine choisie (`$weekly`), chaque onglet mène à la semaine de sa personne plutôt qu'à un jour. Sans
	 * jour (`$date` vide), il mène au jour par défaut de la semaine.
	 */
	public function getUsers(\structure\Structure $eStructure, string $week, ?string $date, \Collection $cUser, \user\User $eUserSelected, bool $weekly = FALSE): string {

		if($cUser->count() < 2) {
			return '';
		}

		$h = '<div class="tabs tabs-h tabs-h-fold planning-time-users">';
			$h .= '<div class="tabs-item tabs-sub">';

				$h .= '<a href="'.self::url($eStructure, $week, $date, team: TRUE, weekly: $weekly).'" class="tab-item '.($eUserSelected->empty() ? 'selected' : '').'">'.s("Tous").'</a>';

				foreach($cUser as $eUser) {

					$h .= '<a href="'.self::url($eStructure, $week, $date, $eUser, weekly: $weekly).'" class="tab-item '.($eUser->is($eUserSelected) ? 'selected' : '').'">';
						$h .= \user\UserUi::getVignette($eUser, '2.5rem').' ';
						$h .= '<span>'.encode($eUser['firstName'] ?? $eUser['lastName']).'</span>';
					$h .= '</a>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La journée de la fiche sous horaires, au-dessus du temps par intervention (planche rh-saisie-temps, B), dans
	 * une seule carte (19/10) : ailleurs, elle se saisit dans la semaine en champs (`hasEntry()`). En tête,
	 * tant qu'elle est à saisir, la question, avec le bouton qui ouvre sa saisie ; saisie, le chiffre « Journée de
	 * travail », sa tuile, qui la corrige, et ses horaires dessous. Une journée qu'on lit seulement n'a pas de
	 * question à poser : elle garde le chiffre, « à saisir » quand il manque. La réglette à l'horloge suit dans la
	 * même carte, qui se lit seulement (`canRule()`).
	 *
	 * Des interventions au-delà d'une journée saisie la passent en rouge, précédée d'un triangle, comme l'ancien
	 * planning (`overflow`, R-Retours-1210, GJ et GJ-bis) ; une journée à saisir n'a rien à dépasser.
	 *
	 * Un jour à venir garde le nœud vide, que les gestes remplacent.
	 *
	 * @param \hr\WorkingTime $eWorkingTime La journée saisie du jour, vide à saisir.
	 */
	public function getWorkingDay(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser, array $day, \hr\WorkingTime $eWorkingTime, bool $canWorking): string {

		if($day['future']) {
			return '<div id="planning-time-working-day"></div>';
		}

		$uiTimeWeek = new TimeWeekUi();

		$classes = ['planning-week-time-lane'];

		if($day['working'] === NULL) {
			$classes[] = 'planning-week-time-lane-missing';
		}

		$h = '<div class="util-card planning-time-working-day" id="planning-time-working-day">';

			if($canWorking and $day['working'] === NULL) {

				$weekday = $day['today'] ? s("aujourd'hui") : lcfirst(\util\DateUi::getDayName((int)date('N', strtotime($date))));

				if($eUser->isOnline()) {
					$title = s("Combien de temps avez-vous travaillé {weekday} ?", ['weekday' => $weekday]);
					$open = s("Saisir ma journée");
				} else {
					$title = s("Combien de temps a travaillé {firstName} {weekday} ?", ['firstName' => $eUser->getFirstName(), 'weekday' => $weekday]);
					$open = s("Saisir sa journée");
				}

				$h .= '<div class="util-card-title">'.$title.'</div>';
				$h .= '<div class="util-card-buttons">';
					$h .= $this->getWorking($eStructure, $week, $date, $eUser, origin: NULL, workingTime: NULL, canWorking: TRUE, overflow: FALSE, eWorkingTime: $eWorkingTime, tile: '<a class="btn btn-sm btn-outline-primary" data-planning-duration-open>'.$open.'</a>');
				$h .= '</div>';

			} else {

				$h .= '<div class="util-figures mb-0">';
					$h .= '<div class="util-figure">';
						$h .= '<div class="util-figure-label util-figure-label-top">'.s("Journée de travail").'</div>';
						$h .= '<div class="util-figure-value">';
							if($day['overflow']) {
								$h .= '<span class="color-danger planning-time-working-day-overflow">'.\Asset::icon('exclamation-triangle-fill').'</span>';
							}
							$h .= $this->getWorking($eStructure, $week, $date, $eUser, origin: NULL, workingTime: $day['working'], canWorking: $canWorking, overflow: $day['overflow'], eWorkingTime: $eWorkingTime);
						$h .= '</div>';
						$range = DurationUi::getRangeText($eWorkingTime);
						if($range !== '') {
							$h .= '<div class="util-figure-label">'.$range.'</div>';
						}
					$h .= '</div>';
				$h .= '</div>';

			}

			$h .= '<div class="planning-time-working-rule">';
				$h .= '<div class="planning-week-time-lanes">';
					$h .= $uiTimeWeek->getClockHead();
					$h .= '<div class="'.implode(' ', $classes).'">';
						$h .= $uiTimeWeek->getClockRule($eWorkingTime);
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps par intervention du jour, sous le titre du jour (`getDayHead()`), dans son bloc blanc : la barre des
	 * interventions par action (`getTasks()`, R2 de R-Retours-0410-soir), graduée en heures sur l'échelle commune
	 * (`TimeWeekLib::SCALE`), puis leur détail et sa somme en pied (`getTasksSpent()`). Les deux temps ne se lisent
	 * pas côte à côte comme deux mesures du même jour : la journée, saisie dans la semaine en champs
	 * (`getEntryDay()`), est un repère sur la barre. Il n'est que sous le temps par intervention.
	 *
	 * Le bloc est dans ce nœud, que chaque geste qui change le temps redessine déjà : un jour sans temps passé sur
	 * une intervention n'en a pas (19/10), et la première intervention le fait paraitre. Ce jour-là, comme
	 * un jour à venir, garde le nœud vide que les gestes remplacent.
	 *
	 * Sur une structure à horaires, la réglette est à l'horloge (`TimeWeekUi::getClockRule()`, planche rh-heures,
	 * Q4 A) et la barre des interventions, qui compte en durées depuis zéro, n'a plus d'axe où se poser : elle
	 * cède, leur détail restant seul.
	 *
	 * @param array $actions Le temps du jour par action (`TimeWeekLib::getActionsByDate()`).
	 * @param \Collection $cTaskSpent Les interventions du jour de la personne (`TimeDayLib::getTasksSpent()`).
	 * @param float $timeSpent La somme de leurs lignes, le pied du détail (`timeSpent` de `TimeDayLib::get()`).
	 */
	public function getTasksDay(\structure\Structure $eStructure, array $day, array $actions, \Collection $cTaskSpent, float $timeSpent): string {

		// Le temps d'une intervention à faire d'une série close compte dans la barre sans avoir de ligne au détail
		// (`TimeDayLib::getTasksSpent()`) : le jour n'est vide que si la barre n'a rien à montrer non plus.
		if(
			$day['future'] or
			($cTaskSpent->empty() and ($actions === [] or $eStructure->hasTimeRange()))
		) {
			return '<div id="planning-time-tasks-day"></div>';
		}

		$h = '<div id="planning-time-tasks-day">';

			$h .= '<div class="util-card planning-time-day planning-time-tasks-day">';

				if($actions !== [] and $eStructure->hasTimeRange() === FALSE) {
					$h .= '<div class="util-progress-marked planning-time-tasks-cell">'.$this->getTasks($actions, $day['working']).'</div>';
					$h .= '<div class="planning-time-rule-axis planning-time-tasks-axis">'.new TimeWeekUi()->getRuleMarks().'</div>';
				}

				$h .= $this->getTasksSpent($cTaskSpent, $timeSpent);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre des interventions, sur l'échelle commune (R2 de R-Retours-0410-soir, qui
	 * rend celle du point 21 de R-Retours-0410) : une part par action, dans l'ordre de la liste, à la couleur
	 * de l'action, au-delà de la journée comme en deçà. Pas de largeur minimale : une action d'une
	 * minute se lit dans le détail du jour (`getTasksSpent()`), et un minimum allongerait la barre au-delà du
	 * temps qu'elle porte. La journée saisie y pose son repère (`$working`, 18/10), qui dit si les
	 * interventions la remplissent ou la dépassent.
	 *
	 * @param array $actions Le temps du jour par action (`TimeWeekLib::getActionsByDate()`).
	 */
	protected function getTasks(array $actions, ?float $working): string {

		$h = '<div class="util-progress planning-time-tasks">';

			foreach($actions as $action) {
				$h .= '<div class="util-progress-part" style="background-color: '.$action['eAction']['color'].'; width: '.TimeWeekUi::getPercent($action['time']).'%"></div>';
			}

		$h .= '</div>';

		if($working !== NULL) {
			$h .= '<i class="util-progress-marker" style="left: '.TimeWeekUi::getPercent($working).'%"></i>';
		}

		return $h;

	}

	/**
	 * Le détail du jour d'une personne (R-Retours-0810, I) : une ligne par intervention, la pastille de son action,
	 * l'action et le nom de l'intervention, puis son temps, dans l'ordre de la barre (`TimeDayLib::getTasksSpent()`).
	 * Ouvert d'office dans la carte de la fiche, replié sous la ligne de chacun dans l'équipe (`getTeam()`). Le nom se coupe sur
	 * une ligne (`planning.css`) : il se lit en entier dans la liste.
	 *
	 * La fiche lui donne la somme de ses lignes (`$total`, `timeSpent` de `TimeDayLib::get()`), qu'il écrit en pied
	 * (18/10). L'équipe n'en a pas besoin, sa ligne le porte déjà.
	 */
	public function getTasksSpent(\Collection $cTaskSpent, ?float $total = NULL): string {

		if($cTaskSpent->empty()) {
			return '';
		}

		$h = '<div class="util-legend planning-time-legend">';

			foreach($cTaskSpent as $eTask) {

				$name = $this->getTaskSpentName($eTask);

				$h .= '<span class="util-legend-item">';
					$h .= \work\ActionUi::getCircle($eTask['action']);
					$h .= '<span>'.encode(($name === '') ? $eTask['action']['name'] : $eTask['action']['name'].' · '.$name).'</span>';
					$h .= '<b>'.DurationUi::formatDuration($eTask['timeDay']).'</b>';
				$h .= '</span>';

			}

			if($total !== NULL) {
				$h .= '<span class="util-legend-item planning-time-legend-total">';
					$h .= '<span>'.s("Total").'</span>';
					$h .= '<b>'.DurationUi::formatDuration($total).'</b>';
				$h .= '</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nom d'une intervention dans le détail du jour, en texte seul et dans l'ordre de la liste (`getLabel()`) :
	 * la série, l'espèce, la note (`\series\Task::getDescriptionStart()`), la première méthode. Ni l'icône de l'abri
	 * ni la quantité récoltée : le détail tient sur une ligne par intervention, et la liste dit le reste.
	 */
	protected function getTaskSpentName(\series\Task $eTask): string {

		if($eTask['series']->notEmpty()) {
			return $eTask['series']['name'];
		}

		if($eTask['plant']->notEmpty()) {
			return $eTask['plant']['name'];
		}

		$description = $eTask->getDescriptionStart();

		if($description !== '') {
			return $description;
		}

		$cMethod = $eTask['cMethod?']();

		return $cMethod->empty() ? '' : $cMethod->first()['name'];

	}

	/**
	 * La réglette d'une journée en durée la pose face aux interventions : le suivi RH seul n'en a pas, et la tuile
	 * de la journée suffit à la saisir, sur la fiche d'une personne comme sur celle de l'équipe (R-Retours-1810).
	 * Celle des horaires, qui se lit seulement, reste.
	 */
	protected static function hasRule(\structure\Structure $eStructure): bool {

		return (
			$eStructure->hasTime() or
			$eStructure->hasTimeRange()
		);

	}

	/**
	 * La réglette d'une journée se glisse là où sa tuile s'écrit, sauf sous horaires, où elle se lit seulement :
	 * elle poserait une durée sans arrivée ni départ, que `\hr\WorkingTime::start.missing` refuse.
	 */
	protected static function canRule(\structure\Structure $eStructure, bool $canWorking): bool {

		return (
			$canWorking and
			$eStructure->hasTimeRange() === FALSE
		);

	}

	/**
	 * La clé de la tuile d'une journée, que porte aussi sa réglette (`TimeWeekUi::getRule()`) : le geste de la
	 * réglette retrouve par elle la tuile qui envoie la journée, sur la fiche comme dans l'Équipe.
	 */
	public static function getWorkingKey(string $date, \user\User $eUser): string {
		return 'working-'.$date.'-'.$eUser['id'];
	}

	/**
	 * La tuile du temps de travail, chiffre « Journée » de la fiche et bout de chaque ligne de l'équipe : la
	 * valeur seule, « à saisir » quand il n'y a rien, en clair au-delà de 12 h, où la réglette s'arrête.
	 * Elle ouvre la saisie d'une durée (`DurationUi`) sur les heures de la journée (`HOURS`), rangées par sept,
	 * qui posent encore 13 h ; une journée plus longue se tape. Rien ne part avant son « Enregistrer » (planche
	 * R-Retours-0810, retour 198), la réglette envoyant toujours au lâcher.
	 * Un jour à venir, ou la journée d'une autre personne sans le planning complet, se lit
	 * sans s'ouvrir.
	 *
	 * Ce qu'un geste écrit, la saisie et la réglette le lisent sur ce conteneur, la date, la personne et
	 * l'écran d'où il part (`origin`, `NULL` pour la fiche), que la réponse redessine : elles n'ont pas
	 * d'autre écran à interroger. Le récapitulatif des heures (`\hr\RecapLib::ORIGIN`) l'ouvre par le jour entier,
	 * qu'il passe en `$tile`, comme la semaine du suivi RH seul, qui nomme la tuile (`$id`) pour que la réponse la
	 * remplace, et la question de la fiche du jour, par son bouton (`getWorkingDay()`).
	 *
	 * `$overflow` dit que les interventions du jour dépassent la journée (`TimeWeekLib::getDay()`) : la valeur
	 * passe en rouge, son crayon non. Elle dit la journée enregistrée, que la réponse redessine, et garde sa
	 * couleur pendant qu'une autre durée se choisit dans la grille (`PlanningDuration.write()`).
	 *
	 * `$missing` dit qu'une journée vide est attendue : sa tuile dit alors « à saisir », sinon son crayon seul. La
	 * semaine en champs n'attend que les jours que compte sa somme (`getEntryDay()`).
	 */
	public function getWorking(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser, ?string $origin, ?float $workingTime, bool $canWorking, bool $overflow, \hr\WorkingTime $eWorkingTime = new \hr\WorkingTime(), string $tile = '', string $id = '', bool $missing = TRUE): string {

		$class = 'planning-time-working'.($overflow ? ' planning-time-working-overflow' : '');

		if($canWorking === FALSE) {
			return '<div class="'.$class.'">'.(($workingTime === NULL) ? '<span class="planning-time-working-missing">'.s("à saisir").'</span>' : DurationUi::formatDuration($workingTime)).'</div>';
		}

		$attributes = [
			'data-structure' => $eStructure['id'],
			'data-week' => $week,
			'data-date' => $date,
			'data-user' => $eUser['id'],
			'data-origin' => $origin ?? ''
		];

		if($id !== '') {
			$attributes['id'] = $id;
		}

		// Sous horaires, la journée se saisit par ses horaires, dont la durée est le calcul : le panneau part de ceux
		// de la journée saisie (`$eWorkingTime`), vide quand elle est à saisir.
		if($eStructure->hasTimeRange()) {

			return new DurationUi()->getTiledRange(
				self::getWorkingKey($date, $eUser),
				$eWorkingTime,
				self::getPanelDay($date),
				$missing ? s("à saisir") : '',
				class: $class,
				attributes: $attributes,
				tile: $tile
			);

		}

		return new DurationUi()->getTiled(
			self::getWorkingKey($date, $eUser),
			$workingTime,
			self::HOURS,
			self::getPanelDay($date),
			$missing ? s("à saisir") : '',
			class: $class,
			attributes: $attributes,
			tile: $tile
		);

	}

	/**
	 * La liste (reprise g, retours 136 et 138) : le nom et ce qui le précise, le temps juste après, le
	 * dernier commentaire dans une colonne ouverte seulement quand une ligne de la fiche en porte un
	 * (`TimeDayLib::get()`, `commented`), et « Fait » au bord droit. À partir de 576, l'action a sa colonne, à
	 * gauche, sur la première ligne de son groupe (R-Retours-0810, 211) ; en deçà, son intitulé reste au-dessus
	 * de ses lignes. La table dit sa variante (`data-columns`), que chaque geste
	 * réémet pour que sa ligne s'y rende (`PlanningTimeDay.getBody()`). Les interventions de la semaine sous
	 * le nom de chaque action (reprise f), celles du jour à leur place dans leur groupe (R-Retours-0710, 164).
	 * Une liste sans ligne n'a pas de table (`getList()`).
	 */
	public function getTable(string $week, string $date, array $timeDay, bool $canTime): string {

		return $this->getTableOf('planning-time-table', $week, $date, $timeDay['ccTaskWeek'], $timeDay['commented'], $canTime);

	}

	/**
	 * Une table de la fiche, celle de la semaine ou celle d'une zone, qui préfixe les intitulés de ses actions
	 * (`$prefix`) et place sous chacune ce que rend `$after`, le « Voir les N autres » d'une zone.
	 */
	protected function getTableOf(string $id, string $week, string $date, \Collection $ccTaskLine, bool $commented, bool $canTime, string $prefix = 'planning-time', ?\Closure $after = NULL): string {

		$h = '<div class="util-table util-table-bordered util-table-sticky planning-time-table '.($commented ? 'planning-time-table-comments' : 'planning-time-table-wide').'" id="'.$id.'" data-columns="'.($commented ? 3 : 2).'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div class="planning-time-head-name">'.s("Intervention").'</div>';
				$h .= '<div>'.s("Temps passé").'</div>';
				if($commented) {
					$h .= $this->getCommentsHead();
				}
			$h .= '</div>';

			foreach($ccTaskLine as $cTaskLine) {

				$eAction = $cTaskLine->first()['action'];

				$h .= $this->getActionTitle($eAction, $prefix);

				foreach($cTaskLine as $eTask) {
					$h .= $this->getRow($week, $date, $eTask, $canTime, $commented);
				}

				if($after !== NULL) {
					$h .= $after($eAction['id']);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les zones « En retard » puis « Non planifié » (CF), sous la liste et hors d'elle : « J'ai aussi fait »,
	 * qui redessine la liste, n'y change rien. Le conteneur existe même vide, pour qu'une réponse puisse le
	 * remplacer ; une zone vide n'est pas rendue. Il porte ce que les gestes réémettent : « Non planifié »
	 * lu (`data-unplanned`), les zones montrées et repliées (`data-folded`), les blocs dépliés
	 * (`data-expanded`), et les lignes qu'un geste garde à leur place dans une zone que la base ne leur donne
	 * plus (`data-retained`), aucune au chargement. Un jour à venir n'a pas de zones.
	 */
	public function getZones(string $week, string $date, array $zones, bool $canTime): string {

		if($zones === []) {
			return '';
		}

		$folded = array_keys(array_filter($zones, fn(array $zone) => ($zone['loaded'] and $zone['open'] === FALSE)));

		$expanded = $this->getExpanded(array_map(fn(array $zone) => $zone['expanded'], $zones));

		$h = '<div id="planning-time-zones" data-unplanned="'.($zones[TaskLateLib::UNPLANNED]['loaded'] ? '1' : '').'" data-folded="'.implode(',', $folded).'" data-expanded="'.$expanded.'" data-retained="">';

			foreach($zones as $zone) {

				if($zone['count'] > 0) {
					$h .= $this->getZone($week, $date, $zone, $canTime);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les blocs dépliés des zones, sous la forme que l'écran réémet (`late-12,unplanned-3`).
	 */
	public function getExpanded(array $expanded): string {

		$values = [];

		foreach($expanded as $zone => $actions) {

			foreach($actions as $action) {
				$values[] = $zone.'-'.$action;
			}

		}

		return implode(',', $values);

	}

	/**
	 * Les lignes retenues dans les zones, sous la forme que l'écran réémet (`late-12-345-1`) : la zone,
	 * l'action, l'intervention, et si la ligne ouvre son bloc.
	 */
	public function getRetained(array $retained): string {

		$values = [];

		foreach($retained as $zone => $lines) {

			foreach($lines as $line) {
				$values[] = $zone.'-'.$line['action'].'-'.$line['task'].'-'.($line['first'] ? '1' : '0');
			}

		}

		return implode(',', $values);

	}

	/**
	 * Une zone, comme dans les Interventions (`TaskWeekUi::getZone()`) : son titre et son compte, qui l'ouvrent
	 * et la ferment (`PlanningTimeZone.toggle()`), puis sa table.
	 */
	public function getZone(string $week, string $date, array $zone, bool $canTime): string {

		$name = $zone['zone'];

		$h = '<div class="util-fold mt-2 '.($zone['open'] ? 'util-fold-open' : '').'" id="planning-time-'.$name.'" data-planning-time-zone="'.$name.'">';

			$h .= '<a class="util-fold-head">';
				$h .= '<h3>'.TaskWeekUi::getZoneTitle($name).' <span class="util-counter">'.$zone['count'].'</span></h3>';
				$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-fold-content">';
				$h .= $this->getZoneBoard($week, $date, $zone, $canTime);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La table d'une zone. Non lue, la zone garde un nœud vide que sa première ouverture remplace
	 * (`timeDay:unplanned`).
	 */
	public function getZoneBoard(string $week, string $date, array $zone, bool $canTime): string {

		$id = 'planning-time-'.$zone['zone'].'-board';

		if($zone['loaded'] === FALSE) {
			return '<div id="'.$id.'"></div>';
		}

		$more = fn(int $action): string => $this->getZoneMore($zone['zone'], $action, $zone['blocks'][$action]['count'] - count($zone['blocks'][$action]['shown']));

		return $this->getTableOf($id, $week, $date, $zone['ccTaskLine'], $zone['commented'], $canTime, 'planning-time-'.$zone['zone'], $more);

	}

	/**
	 * « Voir les N autres » d'une action au-delà du plafond (`TaskLateLib::$threshold`), qui ajoute à sa place
	 * les `$rest` lignes qui ne se montraient pas (`PlanningTimeZone.more()`).
	 */
	public function getZoneMore(string $zone, int $action, int $rest): string {

		if($rest === 0) {
			return '';
		}

		$h = '<div class="util-table-row" id="planning-time-'.$zone.'-more-'.$action.'">';
			$h .= '<div class="util-table-full">';
				$h .= '<a data-planning-time-more="'.$action.'" class="btn btn-sm btn-outline-primary">'.p("Voir l'autre", "Voir les {value} autres", $rest).'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'intitulé de la colonne des commentaires, qu'une table ouvre aussi en cours de route
	 * (`timeDay.v.php`, `planningTimeDayZoneVariant()`).
	 */
	public function getCommentsHead(): string {
		return $this->getCommentsCell(s("Commentaires"));
	}

	/**
	 * La cellule du commentaire d'une ligne, vide pour une ligne sans commentaire.
	 */
	public function getCommentsCell(string $comment): string {
		return '<div class="planning-time-comments">'.$comment.'</div>';
	}

	/**
	 * La rangée des outils au-dessus de la liste : les deux interrupteurs, puis « + Fait » en haut à
	 * droite, à la place et sous la forme de celui des Interventions (retour 291), qui ouvre « Nouvelle
	 * intervention » sur Fait, pour la personne de l'onglet et le jour ouvert (retour 293). Comme dans
	 * les Interventions, une semaine sans rien n'a pas ce bouton : son bloc vide porte le geste (`getNone()`).
	 * Celle qu'un filtre vide le garde, son bloc ne proposant rien. La première ligne créée fait apparaître le bouton : le geste redessine
	 * donc la rangée avec la liste (`timeDay:doCreate`).
	 */
	public function getTools(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser, array $timeDay, \structure\Member $eMember, bool $canAlso): string {

		$uiTab = new TabUi();

		$end = $uiTab->getMineSwitch($eMember, $eUser).$uiTab->getHideSwitch($eMember);

		if($canAlso and ($timeDay['ccTaskWeek']->notEmpty() or $timeDay['hidden'])) {
			$end .= $uiTab->getActions(TaskCreateUi::getButtonTo(TaskCreateUi::url($eStructure, 'timeDay', $week, date: $date, status: \series\Task::DONE, eUserTime: $eUser), \series\Task::DONE));
		}

		return $uiTab->getTools('', $end, id: 'planning-time-tools');

	}

	/**
	 * La liste, que « Nouvelle intervention » redessine (`timeDay:doCreate`) : sa première ligne remplace
	 * l'état vide par la table.
	 */
	public function getList(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser, array $timeDay, bool $canTime, bool $canAlso): string {

		$h = '<div id="planning-time-list">';

			if($timeDay['ccTaskWeek']->empty()) {
				$h .= $this->getNone($eStructure, $week, $date, $eUser, $timeDay['hidden'], $canAlso);
			} else {
				$h .= $this->getTable($week, $date, $timeDay, $canTime);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'état vide de la liste, celui des Interventions (retour 274) : une semaine sans rien propose
	 * « J'ai aussi fait » en bouton principal, comme « + À faire » et « + Fait »
	 * (`TaskCreateUi::getEmptyButton()`), et une liste vidée par un filtre dit seulement que c'est le filtre.
	 */
	public function getNone(\structure\Structure $eStructure, string $week, string $date, \user\User $eUser, bool $hidden, bool $canAlso): string {

		$h = '<div id="planning-time-none" class="util-block-empty">';

			if($hidden) {
				$h .= '<h3>'.s("Rien ne reste à montrer cette semaine avec ce filtre").'</h3>';
			} else {

				$h .= '<h3>'.s("Rien de posé ni de fait cette semaine").'</h3>';

				if($canAlso) {
					$h .= '<div class="util-buttons">';
						$h .= '<a href="'.encode(TaskCreateUi::url($eStructure, 'timeDay', $week, date: $date, status: \series\Task::DONE, eUserTime: $eUser)).'" class="btn btn-primary">'.s("J'ai aussi fait").'</a>';
					$h .= '</div>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'intitulé d'une action, au-dessus de ses lignes.
	 */
	public function getActionTitle(\work\Action $eAction, string $prefix): string {

		return '<div class="util-table-title" id="'.$prefix.'-action-'.$eAction['id'].'">'.encode($eAction['name']).'</div>';

	}

	/**
	 * Une ligne : l'action, nommée sur la première ligne de son groupe (`actionFirst`), la vignette de l'espèce
	 * et le nom, le temps, le commentaire, puis la marque de la carte (reprise v, retour 80), seule à dire
	 * qu'elle est faite (retour 111), au bord droit à toutes les largeurs (R-Retours-0810, 211). Le temps
	 * suit le nom (200) : au doigt, il laisse à sa droite la place de glisser la durée. Le temps est celui
	 * de la personne ce jour-là, dans le champ par pas, toujours visible (R-Retours-0410, point 22, « cet
	 * élément reste sur la feuille de saisie de temps ») ; sans droit d'écrire ce temps, il se lit seulement.
	 * La cellule du temps porte la durée, qui ouvre par-dessus la page la fenêtre du champ par pas et de son
	 * « Enregistrer » (`DurationUi::getStepWindow()`) : ouvrir puis fermer une fenêtre ne déplace aucune ligne
	 * (R-Retours-0610, points 2 et 3).
	 * `$commented` est la variante de la table où la ligne se rend.
	 *
	 * La première ligne d'une action ouvre son groupe par un filet plein (`util-table-break`, R-Retours-0810,
	 * 228), les autres gardent le tireté de la table.
	 *
	 * La ligne porte son jour dans la semaine, que chaque geste réémet : la réponse la rend à cette
	 * place, pour qu'un geste ne la déplace pas sous le doigt (R-Place), et le rechargement la range.
	 */
	public function getRow(string $week, string $date, \series\Task $eTask, bool $canTime, bool $commented): string {

		$eTask->expects(['timeDay', 'dayWeek', 'actionFirst', 'cComment']);

		$uiTaskCard = new TaskCardUi();

		$label = $this->getLabel($eTask, $date);

		$h = '<div class="util-table-row planning-time-row'.($eTask['actionFirst'] ? ' util-table-break' : '').($eTask->isDone() ? ' planning-time-row-done' : '').'" id="planning-time-task-'.$eTask['id'].'" data-task="'.$eTask['id'].'" data-day-week="'.($eTask['dayWeek'] ?? '').'" data-action-first="'.($eTask['actionFirst'] ? '1' : '0').'">';

			$h .= '<div class="planning-time-action">';
				if($eTask['actionFirst']) {
					$h .= '<span class="planning-day-action-name">'.encode($eTask['action']['name']).'</span>';
				}
			$h .= '</div>';

			$h .= '<div class="planning-time-name">';
				$h .= $this->getName($eTask, $week, $label);
			$h .= '</div>';

			$h .= '<div class="planning-time-time">';

				if($canTime) {

					$h .= new DurationUi()->getStepWindow($this->getKey($eTask, $date), $eTask['timeDay']);

				} else if($eTask['timeDay'] > 0.0) {
					$h .= '<span class="planning-time-value">'.DurationUi::getSpent($eTask['timeDay']).'</span>';
				}

			$h .= '</div>';

			if($commented) {
				$h .= $this->getCommentsCell($this->getComment($eTask, $week));
			}

			$h .= '<div class="planning-time-validate">';
				$h .= $uiTaskCard->getMark($eTask);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La clé du champ d'une ligne, que sa tuile porte aussi (`DurationUi::getStepWindow()`).
	 */
	protected function getKey(\series\Task $eTask, string $date): string {
		return 'task-'.$eTask['id'].'-'.$date;
	}

	/**
	 * Le nom d'une ligne (`getLabel()`), qui ouvre le panneau de l'intervention comme sa carte dans la Semaine
	 * (R-Retours-0710, 163), et dessous ce qui la précise (`getInfos()`). L'assolement suit, en retrait
	 * (retour 93). Une ligne sans nom n'en reçoit pas d'autre (décision du 06/10) : le lien couvre
	 * alors toute la cellule, sans rien y écrire. Le tableau de la semaine d'une personne ne rend pas la note sous
	 * le nom (`$noted`) : son pense-bête y dessinerait des cases dans un tableau où rien ne se coche. Une ligne que
	 * sa note nomme la garde, cases comprises : sans elle, elle n'aurait pas de nom.
	 */
	protected function getName(\series\Task $eTask, string $week, array $label, bool $noted = TRUE): string {

		$h = '<span class="planning-time-vignette">'.($eTask['plant']->notEmpty() ? \plant\PlantUi::getVignette($eTask['plant'], '1.75rem') : '').'</span>';

		$h .= '<div class="planning-time-label">';
			if($label['title'] !== '') {
				$h .= '<div>'.($eTask->canRead() ? '<a href="'.TaskDetailUi::url($eTask, $week).'" class="planning-time-link">'.$label['title'].'</a>' : $label['title']).'</div>';
			} else if($eTask->canRead()) {
				$h .= '<a href="'.TaskDetailUi::url($eTask, $week).'" class="planning-time-link-empty"></a>';
			}
			if($label['subtitle'] !== '') {
				$h .= '<div class="planning-time-subtitle">'.$label['subtitle'].'</div>';
			}
			$h .= new TaskCardUi()->getPlace($eTask, $week);
			$h .= $this->getInfos($eTask, ($label['noteInName'] or $noted === FALSE), $label['complementInName'], $label['cMethodDetail']);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre et le sous-titre d'une ligne, déjà échappés. Toute ligne est sous le nom de son action
	 * (R-Retours-0710, 164), se nomme par ce qui la précise, et dit dessous le jour d'une à faire posée un
	 * autre jour que celui de la fiche (reprise v, retour 81 : rien pour une à faire sans jour, retour 135 :
	 * rien pour une faite). Elle ne redit pas le nom de l'action sous lequel elle est rangée (retour 137) :
	 * sans rien d'autre pour la nommer, elle prend sa première méthode, qui quitte alors les infos, et sans
	 * méthode non plus, elle reste sans nom. La quantité d'une récolte et la valeur d'une fertilisation
	 * suivent le nom de la série ou de l'espèce, précédées du calibre et de la variété, comme sur la carte
	 * (`TaskCardUi::getNameComplement()`), et quittent alors les infos.
	 */
	protected function getLabel(\series\Task $eTask, string $date): array {

		$note = new \series\TaskUi()->getTaskDescription($eTask);
		$noteInName = FALSE;
		$cMethodDetail = $eTask['cMethod?']();

		$complement = TaskCardUi::getNameComplement($eTask);
		$subject = TaskCardUi::getSubject($eTask);

		if($subject === '' and $note !== '') {
			$subject = $note;
			$noteInName = TRUE;
		}

		$name = $subject;

		if($name === '' and $cMethodDetail->notEmpty()) {
			$eMethodName = $cMethodDetail->first();
			$name = encode($eMethodName['name']);
			$cMethodDetail = $cMethodDetail->find(fn($eMethod) => $eMethod['id'] !== $eMethodName['id']);
		}

		return [
			'title' => $name,
			'subtitle' => $this->getWhen($eTask, $date),
			'noteInName' => $noteInName,
			'complementInName' => ($complement !== ''),
			'cMethodDetail' => $cMethodDetail,
		];

	}

	/**
	 * Le jour d'une ligne à faire, « Mardi », quand elle est posée sur un autre jour que celui de la fiche.
	 */
	protected function getWhen(\series\Task $eTask, string $date): string {

		if($eTask->isDone() or $eTask['dayWeek'] === NULL or $eTask['dayWeek'] === $date) {
			return '';
		}

		return \util\DateUi::getDayName((int)date('N', strtotime($eTask['dayWeek'])));

	}

	/**
	 * Ce qui précise une ligne, sous son nom (retours 136 et 138) : le complément du planning actuel
	 * (variété, calibre, méthodes que le nom ne porte pas) et le prévu d'une à faire, puis la note quand le
	 * nom ne la porte pas.
	 */
	protected function getInfos(\series\Task $eTask, bool $noteInName, bool $complementInName, \Collection $cMethodDetail): string {

		$uiTask = new \series\TaskUi();

		$infos = [];

		// La méthode qui nomme la ligne quitte les infos sans quitter l'élément de la ligne : getMore()
		// lit cMethod?, que seul le clone remplace.
		$eTaskInfos = clone $eTask;
		$eTaskInfos['cMethod?'] = fn() => $cMethodDetail;

		$more = $complementInName ? $uiTask->getTaskMethods($eTaskInfos) : $uiTask->getMore($eTaskInfos);

		if($more !== '') {
			$infos[] = $more;
		}

		if($eTask->isTodo() and $eTask['timeExpected'] > 0.0) {
			$infos[] = s("prévu {value}", \series\TaskUi::convertTime($eTask['timeExpected']));
		}

		$h = '';

		if($infos !== []) {
			$h .= '<div class="planning-time-infos">'.implode(' ', $infos).'</div>';
		}

		if($noteInName === FALSE) {

			$note = $uiTask->getTaskDescription($eTask);

			if($note !== '') {
				$h .= '<div class="planning-time-note">'.$note.'</div>';
			}

		}

		return $h;

	}

	/**
	 * Le dernier commentaire d'une ligne (retour 136, piste A de M-Fiche) : le prénom de son auteur, le texte
	 * coupé à deux lignes par la feuille, et le nombre des autres. Il ouvre l'intervention, dont les
	 * commentaires suivent la carte, pour qui peut la lire (même garde que `TaskCardUi`).
	 */
	protected function getComment(\series\Task $eTask, string $week): string {

		$cComment = $eTask['cComment'];

		if($cComment->empty()) {
			return '';
		}

		$eComment = $cComment->first();
		$eUser = $eComment['createdBy'];

		$tag = $eTask->canRead() ? 'a' : 'span';

		$h = '<'.$tag.' '.($tag === 'a' ? 'href="'.TaskDetailUi::url($eTask, $week).'" ' : '').'class="planning-time-comment">';
			$h .= '<span class="planning-time-comment-text">';
				$h .= '<span class="planning-time-comment-author">'.encode($eUser['firstName'] ?? $eUser['lastName']).'</span> ';
				$h .= encode($eComment['text']);
			$h .= '</span>';
			if($cComment->count() > 1) {
				$h .= '<span class="util-counter">+'.($cComment->count() - 1).'</span>';
			}
		$h .= '</'.$tag.'>';

		return $h;

	}

}
?>
