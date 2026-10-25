<?php
namespace planning;

/**
 * Les composants du temps de la semaine, que la fiche de temps (`TimeDayUi`), la rangée des jours du
 * détail d'une intervention (`TaskDetailUi`) et les cartes réutilisent : la personne qui saisit, un jour
 * de `util-days` et la réglette d'une journée.
 *
 * Ils servaient d'abord le bandeau du temps de la Semaine et du Jour (L6c), retiré par
 * R-Retours-0410-soir (I1) : la fiche de temps en est désormais la seule surface.
 */
class TimeWeekUi {

	/**
	 * Les vignettes d'absents qu'un jour de l'équipe montre sous sa pastille. Deux vignettes et leur écart font la
	 * largeur intérieure d'un jour au bureau (3,25rem), le plus étroit de toutes les largeurs : une troisième
	 * l'élargirait. Au-delà, la première reste et les autres se comptent (R-Retours-1210, FS).
	 */
	const ABSENTS = 2;

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	public function getWho(\user\User $eUser, string $size = '1.5rem'): string {

		if($eUser->empty()) {
			return '<span class="planning-week-time-who">'.\Asset::icon('people').' '.s("Équipe").'</span>';
		}

		return '<span class="planning-week-time-who">'.\user\UserUi::getVignette($eUser, $size).'<span class="planning-week-time-who-name">'.$eUser->getName().'</span></span>';

	}

	/**
	 * Un jour du composant `util-days`, le même sur toutes les surfaces : la journée en gras, puis la
	 * colonne, dont la hauteur suit la journée sur `TimeWeekLib::SCALE` et dont le bas se remplit des
	 * interventions, puis la pastille de date. Un jour passé ou d'aujourd'hui sans journée est sans valeur, et sa
	 * colonne ne porte que ce qui y a été fait (R-Retours-0510, X) ; sans rien du tout, il n'a pas de colonne, et sa
	 * pastille remonte sous sa valeur (18/10). Un jour à venir est hachuré et sans valeur. Un jour d'absence dit « Absent » à la place de
	 * sa valeur, à venir compris, comme l'ancienne feuille de temps (R-Retours-1210, FH), et sa colonne se hachure
	 * de rouge sous la journée saisie (`is-absent`, FS). Une journée que les interventions dépassent s'écrit en
	 * rouge (`overflow`, R-Retours-1210, GJ). `$day` est un jour de `TimeWeekLib::getDay()`.
	 * Une absence d'une part du jour laisse le jour travaillé, que la fiche du jour précise. Une journée qui manque
	 * (`unfilled`) porte un « + » à la place de la valeur, l'emplacement à remplir du design system (`getUnfilled()`,
	 * 18/10) ; un jour de l'équipe qui a déjà une journée garde sa barre.
	 *
	 * Sans le temps des interventions (`$tasks`, le suivi RH seul), rien ne remplit le bas de la colonne : la
	 * journée se peint alors pleine, de la couleur de ce qui est fait (R-Retours-1810). Un jour férié porte « Férié »
	 * dans sa pastille, comme le récapitulatif du mois (18/10).
	 *
	 * Le jour est un lien, dont `$link` porte les attributs : vers la fiche de ce jour, qu'un jour à venir garde
	 * consultable, ou vers la saisie de sa journée quand il en est la tuile (`TimeDayUi::getDay()`). Un jour de
	 * l'équipe porte sous sa pastille la rangée de ses absents (`$absents`, `getDayAbsents()`), dans le nœud du
	 * jour, que la saisie d'une journée remplace entier.
	 */
	public function getDay(string $date, array $day, string $id, string $link, bool $selected, string $absents, bool $tasks): string {

		$classes = ['util-day'];

		if($day['today']) {
			$classes[] = 'is-today';
		}

		if($selected) {
			$classes[] = 'is-selected';
		}

		if($day['future']) {
			$classes[] = 'is-future';
		}

		if($day['absent']) {
			$classes[] = 'is-absent';
			$value = \hr\AbsenceUi::getAbsent(NULL);
		} else if($day['unfilled'] and $day['working'] === NULL) {
			$value = $this->getUnfilled();
		} else if($day['future'] or $day['working'] === NULL) {
			$value = '&nbsp;';
		} else if($day['overflow']) {
			$value = '<span class="color-danger">'.DurationUi::formatDuration($day['working']).'</span>';
		} else {
			$value = DurationUi::formatDuration($day['working']);
		}

		$column = NULL;

		if($day['working'] !== NULL and $tasks === FALSE) {
			$column = '<span class="util-day-done" style="height: '.self::getPercent($day['working']).'%"></span>';
		} else if($day['working'] !== NULL) {
			$part = ($day['working'] > 0.0) ? min(100, round(100 * $day['time'] / $day['working'])) : 0;
			$column = '<span class="util-day-worked" style="height: '.self::getPercent($day['working']).'%">';
				$column .= '<span class="util-day-done" style="height: '.$part.'%"></span>';
			$column .= '</span>';
		} else if($day['time'] > 0.0) {
			$column = '<span class="util-day-done" style="height: '.self::getPercent($day['time']).'%"></span>';
		} else if($day['future'] or $day['absent']) {
			$column = '';
		}

		return $this->getDayCell($date, $classes, $value, $column, $id, $selected, $link, $absents, $day['holiday'] ? \hr\HolidayUi::getHoliday() : NULL);

	}

	/**
	 * Le « + » d'une journée qui manque, en bouton pointillé (`btn-dashed`, l'emplacement à remplir) : il se lit
	 * comme une case à remplir de la même rangée, et non comme une valeur. C'est un `span`, le jour entier étant
	 * déjà le lien qui l'ouvre.
	 */
	protected function getUnfilled(): string {
		return '<span class="btn btn-only-icon btn-dashed">'.\Asset::icon('plus-lg').'</span>';
	}

	/**
	 * Un jour d'une ferme qui ne suit pas le temps : sa pastille, « Férié » dedans un jour férié, « Absent » au-dessus
	 * pour une personne absente toute la journée, et les absents de l'équipe dessous (`$absents`). Sans journée à ouvrir, le jour n'est pas un
	 * lien. Sa valeur vide garde la rangée alignée.
	 */
	public function getDayWithoutTime(string $date, array $day, string $absents): string {

		$classes = ['util-day'];

		if($day['today']) {
			$classes[] = 'is-today';
		}

		$content = '<b class="util-day-value">'.($day['absent'] ? \hr\AbsenceUi::getAbsent(NULL) : '&nbsp;').'</b>';
		$content .= \util\DateUi::calendarWeekday($date, FALSE, value: $day['holiday'] ? \hr\HolidayUi::getHoliday() : NULL);
		$content .= $absents;

		return $this->wrapDay($content, $classes, '', '');

	}

	/**
	 * La rangée des absents d'un jour de l'équipe, sous sa pastille : la vignette de chacun, cerclée de rouge, ou
	 * la première puis le nombre des autres au-delà de `ABSENTS`. Un jour sans absent garde sa rangée vide, pour
	 * que les pastilles restent alignées.
	 */
	public function getDayAbsents(\Collection $cUserAbsent): string {

		$h = '<div class="util-day-absents">';

			if($cUserAbsent->count() > self::ABSENTS) {
				$h .= \user\UserUi::getVignette($cUserAbsent->first(), '1.25rem');
				$h .= '<span class="util-day-absents-more">'.s("+{value}", $cUserAbsent->count() - 1).'</span>';
			} else {
				foreach($cUserAbsent as $eUser) {
					$h .= \user\UserUi::getVignette($eUser, '1.25rem');
				}
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le balisage d'un jour de `util-days`, partagé par toutes les surfaces qui en rendent un : la valeur en
	 * gras, la colonne, déjà remplie par l'appelant et absente à `NULL`, puis la pastille de date, avec la valeur qu'elle porte
	 * (`$calendarValue`, `DateUi::calendarWeekday()`), et ce qui la suit (`$after`). Le jour
	 * est un lien quand `$link` porte ses attributs (`href`, `data-ajax`…), et se lit seulement sinon.
	 */
	public function getDayCell(string $date, array $classes, string $value, ?string $column, string $id, bool $selected, string $link, string $after, ?string $calendarValue = NULL): string {

		$content = '<b class="util-day-value">'.$value.'</b>';

		if($column !== NULL) {
			$content .= '<span class="util-day-column">'.$column.'</span>';
		}

		$content .= \util\DateUi::calendarWeekday($date, $selected, value: $calendarValue);
		$content .= $after;

		return $this->wrapDay($content, $classes, $id, $link);

	}

	/**
	 * Un jour de `util-days` réduit à sa pastille de date, sans colonne : une rangée qui sert à choisir un jour.
	 * `$value` s'écrit dans la pastille, sous le nom du jour (`DateUi::calendarWeekday()`).
	 */
	public function getDayDate(string $date, array $classes, bool $selected = FALSE, string $link = '', ?string $value = NULL): string {

		return $this->wrapDay(\util\DateUi::calendarWeekday($date, $selected, value: $value), $classes, '', $link);

	}

	/**
	 * La pastille « Non défini » qui ouvre une rangée de `getDayDate()` quand le jour est facultatif
	 * (`util-calendar-none`, page design system) : choisie, la chose n'a pas de jour dans sa semaine.
	 */
	public function getDayNone(array $classes, bool $selected, string $link): string {

		if($selected) {
			$classes[] = 'is-selected';
		}

		$content = '<div class="util-calendar util-calendar-none'.($selected ? ' util-calendar-selected' : '').'">';
			$content .= '<span class="util-calendar-month">'.s("Non défini").'</span>';
		$content .= '</div>';

		return $this->wrapDay($content, $classes, '', $link);

	}

	protected function wrapDay(string $content, array $classes, string $id, string $link): string {

		$attributes = ($id === '' ? '' : 'id="'.$id.'" ').'class="'.implode(' ', $classes).'"';

		if($link === '') {
			return '<div '.$attributes.'>'.$content.'</div>';
		} else {
			return '<a '.$attributes.' '.$link.'>'.$content.'</a>';
		}

	}

	/**
	 * L'axe des heures au-dessus des réglettes de l'équipe, un repère toutes les deux heures, sur la colonne des
	 * barres, après celle des noms. Le tableau de l'équipe nomme ses deux
	 * colonnes de droite (`$columns`) des mêmes mots que ces chiffres, « Journée » et « Interventions ». Sans
	 * réglette sous lui (`$axis`), l'axe laisse sa colonne vide.
	 */
	public function getRuleHead(bool $columns = FALSE, bool $tasks = TRUE, bool $axis = TRUE): string {

		$h = '<div class="planning-week-time-lane planning-time-rule-head">';
			$h .= '<div></div>';
			if($axis) {
				$h .= '<div class="planning-time-rule-axis">'.$this->getRuleMarks().'</div>';
			} else {
				$h .= '<div class="planning-week-time-lane-spacer"></div>';
			}
			if($columns) {
				$h .= '<div class="planning-week-time-lane-field">'.s("Journée").'</div>';
				// Vide plutôt qu'absente sans temps de travail : les cellules d'une ligne se posent à la suite dans la
				// grille (`display: contents`), et une de moins décalerait celles qui suivent.
				$h .= '<div class="planning-week-time-lane-time">'.($tasks ? s("Interventions") : '').'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les repères de l'axe des heures, un toutes les deux heures sur l'échelle commune : au-dessus des réglettes
	 * (`getRuleHead()`), et sous la barre des interventions de la fiche (`TimeDayUi::getTasksDay()`, 19/10),
	 * qui se lit ainsi sur la même graduation que la réglette.
	 */
	public function getRuleMarks(): string {

		$h = '';

		for($hour = 0; $hour <= TimeWeekLib::SCALE; $hour += 2) {
			$h .= '<span style="left: '.self::getPercent($hour).'%">'.s("{value}h", $hour).'</span>';
		}

		return $h;

	}

	/**
	 * L'axe de la réglette d'une journée en horaires : les heures de l'horloge, de `TimeWeekLib::CLOCK_START` à
	 * `CLOCK_STOP`, au lieu des durées de `getRuleHead()`.
	 */
	public function getClockHead(): string {

		$h = '<div class="planning-week-time-lane planning-time-rule-head">';
			$h .= '<div class="planning-time-rule-axis">';
				for($hour = TimeWeekLib::CLOCK_START; $hour <= TimeWeekLib::CLOCK_STOP; $hour += 2) {
					$h .= '<span style="left: '.self::getClockPercent($hour).'%">'.s("{value}h", $hour).'</span>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La réglette d'une journée en horaires (planche rh-heures, Q4 A) : la plage de l'arrivée au départ sur
	 * l'horloge, à lire seulement, la journée se saisissant par son panneau. La pause n'y est pas dessinée : seule
	 * sa durée est connue, pas l'heure où elle tombe. Une journée sans horaires, à saisir ou saisie en durée
	 * avant l'option, laisse la réglette vide.
	 */
	public function getClockRule(\hr\WorkingTime $eWorkingTime): string {

		$h = '<div class="planning-week-time-lane-track planning-time-rule planning-time-rule-read">';

			if($eWorkingTime->notEmpty() and $eWorkingTime['start'] !== NULL) {

				$from = self::getClockPercent(self::getClockHour($eWorkingTime['start']));
				$to = self::getClockPercent(self::getClockHour($eWorkingTime['end']));

				$h .= '<span class="planning-week-time-lane-worked" style="left: '.$from.'%; width: '.round($to - $from, 2).'%"></span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une heure de l'horloge (« 07:30:00 ») en heures décimales.
	 */
	protected static function getClockHour(string $time): float {
		return (int)substr($time, 0, 2) + (int)substr($time, 3, 2) / 60;
	}

	/**
	 * Une heure de l'horloge en pourcentage de la réglette en horaires, bornée à ses deux bouts : une arrivée
	 * avant 6 h part du bord.
	 */
	public static function getClockPercent(float $hour): float {

		$span = TimeWeekLib::CLOCK_STOP - TimeWeekLib::CLOCK_START;
		$hour = max(TimeWeekLib::CLOCK_START, min(TimeWeekLib::CLOCK_STOP, $hour));

		return round(100 * ($hour - TimeWeekLib::CLOCK_START) / $span, 2);

	}

	/**
	 * La réglette d'un jour (M-Temps, Q2) : la journée sur `TimeWeekLib::SCALE`, les interventions par-dessus,
	 * sans leur durée, que la colonne voisine porte. Quand on peut écrire la journée (`$write`), la poignée en
	 * marque le bout et la barre se saisit au clic ou au glissé (`PlanningTimeRule`) ; la tuile au bout de la
	 * ligne reste pour ce que la réglette ne pose pas, une journée hors du quart d'heure ou de plus de 12 h.
	 *
	 * La réglette inscriptible porte la clé de la tuile de sa journée (`$key`, `TimeDayUi::getWorkingKey()`),
	 * par laquelle le geste retrouve la tuile qui l'envoie, au bout de sa ligne dans l'Équipe.
	 *
	 * La bulle de la poignée dit ce qu'elle pose, « Journée », au-dessus de la durée (R-Retours-0810, 179) : la
	 * réglette lui donne son libellé (`data-label`), le script n'écrivant aucun texte du produit.
	 *
	 * Une journée au-delà de l'échelle remplit la réglette et se dit par `planning-time-rule-beyond` (T) : la
	 * poignée se cale dans le bout de la réglette, qui est hachuré, la journée continuant au-delà de 12h. Sa
	 * valeur exacte est celle de la tuile. Le hachuré est son propre nœud (`planning-time-rule-end`), posé après
	 * les interventions et avant la poignée : l'ordre du balisage le peint par-dessus la barre des interventions,
	 * qui atteint le bout dès 11h15 dans l'Équipe, et sous la poignée, sans `z-index`.
	 */
	public function getRule(array $day, string $key, bool $write): string {

		$classes = ['planning-week-time-lane-track', 'planning-time-rule'];

		if($write === FALSE) {
			$classes[] = 'planning-time-rule-read';
		}

		$beyond = ($day['working'] !== NULL and $day['working'] > TimeWeekLib::SCALE);

		if($beyond) {
			$classes[] = 'planning-time-rule-beyond';
		}

		$h = '<div class="'.implode(' ', $classes).'" '.($write ? 'data-planning-time-rule data-working="'.$key.'" data-scale="'.TimeWeekLib::SCALE.'" data-time="'.($day['working'] ?? '').'" data-label="'.s("Journée").'"' : '').'>';

			if($day['working'] !== NULL) {
				$h .= '<span class="planning-week-time-lane-worked" style="width: '.self::getPercent($day['working']).'%"></span>';
			}

			if($day['working'] !== NULL or $day['time'] > 0.0) {

				$done = ($day['working'] === NULL) ? $day['time'] : min($day['working'], $day['time']);

				$h .= '<span class="planning-week-time-lane-done" style="width: '.self::getPercent($done).'%"></span>';

			}

			if($day['overflow']) {

				$from = self::getPercent($day['working']);
				$to = self::getPercent($day['time']);

				if($to > $from) {
					$h .= '<span class="planning-week-time-lane-overflow" style="left: '.$from.'%; width: '.round($to - $from, 2).'%"></span>';
				}

			}

			if($beyond) {
				$h .= '<span class="planning-time-rule-end"></span>';
			}

			if($write) {
				if($day['working'] === NULL) {
					$h .= '<span class="planning-time-rule-knob planning-time-rule-knob-empty" style="left: 0%"></span>';
				} else {
					$h .= '<span class="planning-time-rule-knob" style="left: '.self::getPercent($day['working']).'%"></span>';
				}
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une durée en pourcentage de l'échelle, bornée à 100 : une journée de plus de 12 h remplit la barre.
	 */
	public static function getPercent(float $time): float {

		return round(100 * min($time, TimeWeekLib::SCALE) / TimeWeekLib::SCALE, 2);

	}

}
?>
