<?php
namespace planning;

/**
 * Les deux saisies d'une durée du planning. La première, derrière une tuile (`getTiled()`), est celle de
 * R-Saisie (images 159 à 163) : la durée lue en grand, les ronds des heures, puis les minutes, et
 * « Enregistrer ». Elle ne sert plus que la journée de travail, « Journée », qui garde les ronds
 * (M2j). La durée lue se tape au clic, heures et minutes dans le champ d'une durée du planning actuel
 * (`TimesheetUi::p('timeAdd')`, image 163). `planning.js` la pilote (`PlanningDuration`). Le conteneur porte
 * la valeur (`data-time`) et une clé stable (`data-duration`), par laquelle une saisie restée ouverte se
 * retrouve dans la réponse qui la redessine.
 *
 * La seconde est le champ par pas (`getStepped()`) : -1h, -15, -5, la durée tapée, +5, +15, +1h, à toutes les
 * largeurs (`getStep()`).
 * `planning.js` la pilote (`PlanningDurationStep`). Il vit dans une fenêtre avec « Enregistrer », qu'ouvre
 * une tuile, seule à rester dans la ligne (`getStepWindow()`, R-Retours-0610, retour sur les points 2 et 3 ;
 * planche R-Retours-0810, Enregistrer), ou toujours ouverte dans l'écran qui la porte (`getStepBare()`).
 * C'est la saisie du temps de toute intervention (R-Retours-0810, 212) : la fiche de temps, la page d'une
 * intervention, la bulle de la Semaine, la carte du Jour, la saisie par lot, la grille de la Semaine, la
 * récolte, « Nouvelle intervention » et l'entretien « Fait ».
 *
 * Les fenêtres sont posées par-dessus la page sans pousser ce qui suit, et toutes ont une croix qui ferme
 * (R-Retours-0610, point 3). Le temps d'une saisie de récolte fait exception : ses pas sont en ligne, dans
 * le panneau, sur la ligne de la personne, sans pied (`getStepInline()`, lot BW, retours 280 et 290). Le menu
 * d'un jour de la grille aussi, dans la case de chaque personne, sous un seul « Enregistrer » (ET, retour 398).
 */
class DurationUi {

	const MINUTES = [0, 15, 30, 45];

	/**
	 * Le nombre de ronds d'heures d'une rangée de la fenêtre de « Journée » (`TimeDayUi::HOURS`), qui en range
	 * deux.
	 */
	const HOURS = 7;

	const SPENT = 'badge badge-time-spent';

	/**
	 * Le champ tapé est celui d'une durée du planning actuel, avec ses gestes (`timesheet.js`) : les écrans
	 * qui portent une saisie ne le chargent pas d'eux-mêmes.
	 */
	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');
		\Asset::css('series', 'timesheet.css');
		\Asset::js('series', 'timesheet.js');

	}

	/**
	 * Une durée comme la saisie l'écrit (`PlanningDuration.format()`) : « 1h30 ».
	 */
	public static function formatDuration(float $time): string {

		[$hour, $minute] = self::splitTime($time);

		return $hour.'h'.sprintf('%02d', $minute);

	}

	/**
	 * Un temps passé sur une intervention, dans la marque qui le distingue d'un temps déclaré (R-Retours-0410,
	 * point 12) : partout où le planning l'écrit, et sur la valeur d'une tuile (`getStepWindow()`, `$spent`). Un
	 * zéro ne marque rien, il reste du texte.
	 */
	public static function getSpent(float $time): string {

		if($time <= 0.0) {
			return self::formatDuration(0.0);
		}

		return '<span class="'.self::SPENT.'">'.self::formatDuration($time).'</span>';

	}

	/**
	 * Un écart de temps passé, signé, dans la marque du temps passé (`getSpent()`) : un geste de l'historique
	 * d'une intervention, qui a pu ajouter ou retirer.
	 */
	public static function getSpentChange(float $time): string {

		return '<span class="'.self::SPENT.'">'.($time < 0 ? '-' : '+').self::formatDuration(abs($time)).'</span>';

	}

	/**
	 * Une durée en heures et minutes entières, arrondie à la minute.
	 */
	public static function splitTime(float $time): array {

		$minutes = (int)round($time * 60);

		return [intdiv($minutes, 60), $minutes % 60];

	}

	/**
	 * La saisie derrière une tuile, qui dit la durée et ouvre le panneau, sous elle, au-dessus, ou en feuille
	 * au téléphone (`PlanningDuration.place()`). `$head` est la tête de la feuille, qui rappelle ce qu'on saisit
	 * quand la tuile est hors de vue. `$missing` remplace une durée vide dans la tuile. `$class` et
	 * `$attributes` complètent le conteneur, que la journée de travail charge de ce qu'elle poste.
	 *
	 * La fenêtre se clôt par « Enregistrer », seul geste qui écrive (planche R-Retours-0810, retour 198) : le
	 * conteneur porte la dernière durée écrite (`data-sent`), que la fenêtre fermée sans enregistrer reprend
	 * (`PlanningDuration.hide()`).
	 *
	 * Un écran qui dessine lui-même ce qui ouvre la saisie le passe en `$tile`, qui porte `data-planning-duration-open`
	 * et remplace la tuile : le jour entier du récapitulatif des heures.
	 */
	public function getTiled(string $key, ?float $time, array $hours, string $head, string $missing, string $class = '', array $attributes = [], string $tile = ''): string {

		$attributes['data-sent'] = $time ?? '';

		$h = $this->getOpen($key, $time, 'planning-duration-tiled'.($class === '' ? '' : ' '.$class), $attributes);

			if($tile === '') {
				$h .= '<a class="util-quick planning-duration-tile" data-planning-duration-open>';
					$h .= '<span class="planning-duration-tile-value'.($this->isEmpty($time) ? ' planning-duration-missing' : '').'" data-missing="'.encode($missing).'">'.($this->isEmpty($time) ? $missing : self::formatDuration($time)).'</span>';
					$h .= \Asset::icon('pencil-fill');
				$h .= '</a>';
			} else {
				$h .= $tile;
			}

			$h .= '<div class="planning-time-panel">';
				$h .= '<div class="planning-time-panel-head">'.$head.'</div>';
				$h .= $this->getGrid($time, $hours);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La saisie d'une journée par ses horaires, derrière la même tuile que `getTiled()`, sur une structure à
	 * horaires (`\structure\Structure::hasTimeRange()`) : l'arrivée, le départ et la pause, dans le champ d'une
	 * durée du planning actuel (`TimesheetUi::p('timeAdd')`), puis « Enregistrer », et « Vider » pour une journée
	 * déjà saisie. La durée lue en tête est celle que les horaires donnent, que le serveur recalcule
	 * (`\hr\WorkingTimeLib::prepareTime()`) : elle ne se tape pas. La tuile s'ouvre et se place comme celle d'une
	 * durée (`PlanningDuration`) ; ce qu'elle poste et l'action qui l'écrit sont ceux du conteneur, que `$class`
	 * et `$attributes` désignent à `PlanningTimeRange` comme à `getTiled()`.
	 *
	 * Publique pour servir ailleurs que sur la fiche de temps : le récapitulatif des heures y saisit la même
	 * journée, derrière son jour (`$tile`, comme pour `getTiled()`).
	 */
	public function getTiledRange(string $key, \hr\WorkingTime $eWorkingTime, string $head, string $missing, string $class = '', array $attributes = [], string $tile = ''): string {

		$time = $eWorkingTime->empty() ? NULL : $eWorkingTime['time'];

		// La durée envoyée ne vient pas du panneau mais des horaires : `PlanningDuration.discard()` n'a rien à
		// reprendre.
		$attributes['data-sent'] = $time ?? '';

		$h = $this->getOpen($key, $time, 'planning-duration-tiled planning-duration-range'.($class === '' ? '' : ' '.$class), $attributes);

			if($tile === '') {
				$h .= '<a class="util-quick planning-duration-tile" data-planning-duration-open>';
					$h .= '<span class="planning-duration-tile-value'.($this->isEmpty($time) ? ' planning-duration-missing' : '').'">'.($this->isEmpty($time) ? $missing : self::formatDuration($time)).'</span>';
					$h .= \Asset::icon('pencil-fill');
				$h .= '</a>';
			} else {
				$h .= $tile;
			}

			$h .= '<div class="planning-time-panel">';
				$h .= '<div class="planning-time-panel-head">'.$head.'</div>';
				$h .= $this->getRange($eWorkingTime);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une heure du jour comme le planning l'écrit : « 7h30 ».
	 */
	public static function formatClock(string $time): string {

		return (int)substr($time, 0, 2).'h'.substr($time, 3, 2);

	}

	/**
	 * Les horaires d'une journée saisie, en une ligne : « 7h30 à 15h30, pause 1h00 ».
	 */
	public static function getRangeText(\hr\WorkingTime $eWorkingTime): string {

		if($eWorkingTime->empty() or $eWorkingTime['start'] === NULL) {
			return '';
		}

		$start = self::formatClock($eWorkingTime['start']);
		$end = self::formatClock($eWorkingTime['end']);

		if($eWorkingTime['pause'] > 0.0) {
			return s("{start} à {end}, pause {pause}", ['start' => $start, 'end' => $end, 'pause' => self::formatDuration($eWorkingTime['pause'])]);
		}

		return s("{start} à {end}", ['start' => $start, 'end' => $end]);

	}

	/**
	 * La durée lue et la croix, puis les trois champs sous leur nom, puis « Enregistrer » et « Vider ». Chaque champ
	 * porte son `data-wrapper`, où s'écrit le refus de son étape (`\hr\WorkingTime::properties()`).
	 */
	protected function getRange(\hr\WorkingTime $eWorkingTime): string {

		$form = new \util\FormUi();

		$empty = $eWorkingTime->empty();
		[$hour, $minute] = $empty ? [0, 0] : self::splitTime($eWorkingTime['time']);

		$start = $empty ? NULL : $eWorkingTime['start'];
		$end = $empty ? NULL : $eWorkingTime['end'];
		$pause = $empty ? NULL : $eWorkingTime['pause'];

		$h = '<div class="planning-time-panel-grid">';

			$h .= '<div class="planning-time-panel-readout'.($empty ? ' planning-time-panel-readout-empty' : '').'">';
				$h .= '<span class="planning-time-panel-title"><b>'.$hour.'</b><span>'.s("h").'</span><span>'.sprintf('%02d', $minute).'</span></span>';
				$h .= '<a class="util-sheet-close planning-time-panel-close" data-planning-duration-close>'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';

			$h .= '<div class="planning-time-range">';

				$h .= '<div class="planning-time-range-field" data-wrapper="start">';
					$h .= '<label class="planning-time-panel-label">'.s("Arrivée").'</label>';
					$h .= $form->time('start', $start === NULL ? NULL : substr($start, 0, 5));
				$h .= '</div>';

				$h .= '<div class="planning-time-range-field" data-wrapper="end">';
					$h .= '<label class="planning-time-panel-label">'.s("Départ").'</label>';
					$h .= $form->time('end', $end === NULL ? NULL : substr($end, 0, 5));
				$h .= '</div>';

				$h .= '<div class="planning-time-range-field" data-wrapper="pause">';
					$h .= '<label class="planning-time-panel-label">'.s("Pause").'</label>';
					$h .= $form->dynamicField(new \series\Timesheet(['timeAdd' => $pause]), 'timeAdd');
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="planning-time-range-actions">';
				$h .= '<a class="btn btn-primary" data-planning-range-save>'.s("Enregistrer").'</a>';
				if($empty === FALSE) {
					$h .= '<a class="btn btn-outline-primary" data-planning-range-clear>'.s("Vider").'</a>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La durée d'une ligne et sa fenêtre (R-Retours-0610, retour sur les points 2 et 3) : la tuile, toujours
	 * dans la ligne, dit la durée et ouvre la fenêtre, par-dessus la page : ouvrir puis fermer une fenêtre ne
	 * déplace aucune ligne. La fenêtre porte le champ par pas (`getStepped()`) puis « Enregistrer », son
	 * action pleine et le seul geste qui écrive (planche R-Retours-0810, Enregistrer et 197), qui la ferme une
	 * fois la durée partie (FJ, retour 411). Elle se ferme sans rien écrire par sa croix, à droite
	 * d'« Enregistrer » sur la même rangée pour ne pas grandir, comme toutes les fenêtres de temps
	 * (R-Retours-0610, point 3), par sa tuile, Échap ou un appui dehors. Elle se
	 * place sous la ligne ou au-dessus (`PlanningDurationStep.place()`). La tuile ne prend pas le focus : au
	 * téléphone, le clavier recouvrirait la fenêtre. Son crayon ne se montre que sur « 0h00 », seul signe qu'on peut y écrire (R-Retours-0710,
	 * 161) : une durée posée se touche déjà.
	 * Sur la fiche de temps, la durée se glisse aussi depuis la tuile (R-Retours-0810, 201), qui l'écrit à
	 * chaque pas sans attendre le serveur (`PlanningDurationStep.drag()`) : la tuile porte donc toujours sa
	 * valeur et son crayon, et c'est la feuille qui cache le crayon à côté d'un temps passé.
	 *
	 * `$wrapper` est le nom du champ dont un refus s'écrit sous la rangée des pas (`getStepped()`), `NULL` quand rien
	 * n'est envoyé de la fenêtre. `$spent` dit un temps passé, dont la valeur prend la marque du point 12
	 * (`getSpent()`) ; un temps prévu, celui de la grille ou de « Nouvelle intervention », n'en prend pas, et sa
	 * valeur le dit (`planning-duration-tile-expected`) pour que `PlanningDurationStep.tile()` ne la marque pas ;
	 * non nul, il prend `planning-duration-tile-set`, qui cache le crayon comme la marque le cache.
	 *
	 * `$icon` est ce que la tuile montre à côté d'une valeur nulle : le crayon, ou le « + » d'une case du
	 * tableau de l'équipe (`TaskDetailUi::getGridCell()`), où la valeur nulle ne s'écrit pas.
	 *
	 * `$erase` pose la poubelle d'une durée déjà écrite (`getStepPanel()`).
	 */
	public function getStepWindow(string $key, float $time, ?string $wrapper = 'time', bool $spent = TRUE, string $icon = 'pencil-fill', bool $erase = FALSE): string {

		$h = $this->getTile($key, $time, $spent, $icon);
		$h .= $this->getStepPanel('planning-duration-step-window', $key, $time, $wrapper, $spent, erase: $erase);

		return $h;

	}

	/**
	 * La tuile d'une durée, sa valeur et son icône, qui ouvre la fenêtre `$key` (`getStepWindow()`).
	 */
	protected function getTile(string $key, float $time, bool $spent, string $icon): string {

		$value = ['planning-duration-tile-value'];

		if($spent === FALSE) {
			$value[] = 'planning-duration-tile-expected';
			if($time > 0.0) {
				$value[] = 'planning-duration-tile-set';
			}
		} else if($time > 0.0) {
			$value[] = self::SPENT;
		}

		$h = '<a class="util-quick planning-duration-tile planning-duration-step-tile" data-planning-duration-step-open="'.$key.'">';
			$h .= '<span class="'.implode(' ', $value).'">'.self::formatDuration(max(0.0, $time)).'</span>';
			$h .= \Asset::icon($icon);
		$h .= '</a>';

		return $h;

	}

	/**
	 * La fenêtre sans tuile, toujours ouverte dans l'écran qui la porte et la montre lui-même : la bulle de la
	 * Semaine, la carte du Jour qu'on édite (R-Retours-0810, 212). Elle porte un temps passé, dont un refus
	 * s'écrit sous la rangée des pas. « Enregistrer » envoie puis retire l'écran une fois le temps accepté, la croix le
	 * retire sans rien envoyer (`dismiss` de son consommateur, `PlanningDurationStep`).
	 *
	 * Sans pied (`$foot`), la fenêtre n'a ni « Enregistrer » ni croix : elle vit dans un formulaire qui a son
	 * propre envoi, le temps total de « Temps » sur un lot (lot BG). Ce formulaire range le champ dans un groupe
	 * qui porte déjà le nom du champ (`FormUi::group()`) : la fenêtre n'en porte pas (`$wrapper` à `NULL`), sans
	 * quoi un refus s'écrirait deux fois.
	 */
	public function getStepBare(string $key, float $time, ?string $wrapper = 'time', bool $foot = TRUE, bool $close = TRUE): string {

		return $this->getStepPanel('planning-duration-step-window planning-duration-step-bare is-open', $key, $time, $wrapper, TRUE, $foot, close: $close);

	}

	/**
	 * Les pas toujours ouverts d'une saisie de récolte (lot BW, retours 280 et 290, « intègre le
	 * temps de travail en inline », « et immédiatement présente ») : sans tuile, dans le flux du panneau, sous
	 * la personne, comme la fenêtre sans tuile (`getStepBare()`). Ils n'ont pas de pied : sans « Enregistrer »,
	 * chaque pas et chaque frappe reportent aussitôt la durée (`PlanningDurationStep.report()`), et l'envoi du
	 * formulaire qui les porte, « Ajouter », reste le seul geste qui écrive. Ils ne portent pas de `data-wrapper`
	 * : la personne porte le sien, sous lequel s'écrit le refus de sa durée.
	 *
	 * Le menu d'un jour de la grille fait le même geste (`TaskDetailUi::getGridCell()`), sous un « Enregistrer »
	 * commun à toutes les personnes. Sa case n'a pas de wrapper : les pas portent celui de la personne
	 * (`$wrapper`), sous lequel s'écrit le refus de sa durée.
	 *
	 * Le temps total de « Temps » sur un lot fait le même geste avec `getStepBare(foot: FALSE)`
	 * (`TaskBatchUi`), qui reporte par ses propres délégués (`PlanningTaskBatchTime`, `planning.js`) et pourrait
	 * passer par cette méthode : il l'a précédée, et ne se réécrit pas avec la récolte.
	 *
	 * `$erase` pose la poubelle d'une durée déjà écrite (`getStepPanel()`).
	 */
	public function getStepInline(string $key, float $time, ?string $wrapper = NULL, bool $erase = FALSE): string {

		return $this->getStepPanel('planning-duration-step-window planning-duration-step-bare planning-duration-step-inline is-open', $key, $time, $wrapper, TRUE, foot: FALSE, erase: $erase);

	}

	/**
	 * Le champ par pas puis, sauf sans pied (`$foot`), la rangée d'« Enregistrer » et de la croix. Une saisie
	 * dont le conteneur a déjà sa croix, comme la feuille du temps des Colonnes (`TaskCardUi::getSheet()`), rend
	 * son pied sans elle (`$close`).
	 *
	 * La poubelle (`$erase`, FJ, retour 412) efface la durée du jour d'une case du tableau de l'équipe, la seule
	 * saisie qui la demande. Elle n'existe que sur une durée déjà écrite : une case vide n'a rien à effacer. Dans
	 * la fenêtre, elle se range entre « Enregistrer » et la croix ; sans pied, sous les pas, seule sur sa rangée.
	 * Le geste écrit une durée nulle, qui supprime le temps du jour (`TimesheetLib::writeTimeForDay()`) : la case
	 * effacée est celle d'une case jamais saisie. Elle ne demande pas de confirmation, la durée effacée se reposant en
	 * un pas.
	 */
	protected function getStepPanel(string $class, string $key, float $time, ?string $wrapper, bool $spent, bool $foot = TRUE, bool $erase = FALSE, bool $close = TRUE): string {

		$trash = ($erase and $time > 0.0) ?
			'<a class="btn btn-sm btn-outline-danger btn-only-icon planning-duration-step-erase" data-planning-duration-step-erase>'.\Asset::icon('trash').'</a>' :
			'';

		$h = '<div class="'.$class.'">';
			$h .= $this->getStepped($key, $time, $wrapper, $spent);
			if($foot) {
				$h .= '<div class="planning-duration-step-foot">';
					$h .= '<a class="btn btn-primary" data-planning-duration-step-save>'.s("Enregistrer").'</a>';
					$h .= $trash;
					if($close) {
						$h .= '<a class="util-sheet-close" data-planning-duration-step-close>'.\Asset::icon('x-lg').'</a>';
					}
				$h .= '</div>';
			} else if($trash !== '') {
				$h .= '<div class="planning-duration-step-foot">'.$trash.'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le champ par pas, repris de `TaskCardUi::getStepField()` d'avant `f8b4a4e61`, sans sa réglette
	 * (décision de V-Planning du 04/10, point 22). Les pas ajoutent au temps tapé, « Enregistrer » l'écrit ;
	 * `data-sent` porte la dernière valeur écrite, qui ne repart pas tant qu'elle ne change pas.
	 *
	 * La durée se tape dans les deux cases de la durée lue (`getTyped()`), les heures puis les minutes
	 * (retour 188) : les deux fenêtres de saisie du temps ont le même champ.
	 *
	 * Le conteneur porte la clé de l'écran (`data-duration`), par laquelle le champ se retrouve dans la ligne
	 * redessinée, mais ni la classe `planning-duration` ni `getOpen()` : les délégués de `PlanningDuration`
	 * le prendraient pour une tuile. Il porte aussi `data-wrapper` (`$wrapper`) : le message d'un refus s'y
	 * ajoute en dernier enfant (`Lime.Alert`, `lime.js`), sous la rangée et non dedans. Une saisie de récolte
	 * n'en pose pas : la durée part avec le formulaire, et son refus s'écrit sous la ligne de la personne, qui
	 * porte le sien, là où il se lit aussi quand les pas de la feuille du tactile sont fermés (R-Retours-0810,
	 * 208). Les pas et la durée forment un seul bloc, un `btn-group` du socle aux filets partagés, arrondi à ses
	 * deux bouts (retour 390, qui défait les pastilles détachées de R-Retours-0810, 227). Un temps passé non nul
	 * prend la teinte du point 12 sur sa durée ; un temps nul s'éteint. Un temps prévu (`$spent` à `FALSE`) n'a
	 * pas cette teinte, et le champ le dit (`planning-duration-step-expected`) pour que `PlanningDurationStep.write()` ne la pose pas.
	 */
	public function getStepped(string $key, float $time, ?string $wrapper = 'time', bool $spent = TRUE): string {

		$form = new \util\FormUi();

		$classes = ['planning-duration-step'];

		if($spent === FALSE) {
			$classes[] = 'planning-duration-step-expected';
		}

		if($time <= 0.0) {
			$classes[] = 'planning-duration-step-zero';
		} else if($spent) {
			$classes[] = 'planning-duration-step-spent';
		}

		$h = '<div class="'.implode(' ', $classes).'" data-duration="'.$key.'" data-sent="'.$time.'"'.($wrapper === NULL ? '' : ' data-wrapper="'.$wrapper.'"').'>';
			$h .= '<div class="btn-group planning-duration-step-glue">';
				$h .= $this->getStep(-60).$this->getStep(-15).$this->getStep(-5);
				$h .= $this->getTyped($form, $time);
				$h .= $this->getStep(5).$this->getStep(15).$this->getStep(60);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les deux cases d'une durée, les heures, « h », les minutes : le champ `timeAdd` de la fiche de temps
	 * (`TimesheetUi::p('timeAdd')`), avec ses gestes (`timesheet.js`), que la durée lue de `getGrid()` rend
	 * aussi. `form=""` sort les trois cases du formulaire qui loge la fenêtre : la durée part dans le champ
	 * caché de l'écran, et aucune route du planning ne lit `timeAdd` (planche de P, point 2).
	 */
	protected function getTyped(\util\FormUi $form, float $time): string {
		return '<div class="planning-duration-step-value">'.$form->dynamicField(new \series\Timesheet(['timeAdd' => $time]), 'timeAdd', function(\PropertyDescriber $d) {
			$d->attributes = ['form' => ''];
		}).'</div>';
	}

	/**
	 * Un pas du champ, en minutes, pour qu'une suite de pas de 5 minutes ne dérive pas avec l'arrondi des
	 * durées au centième d'heure (`PlanningDurationStep.add()`). Chaque pas a la largeur de son libellé, et un pas
	 * en minutes porte « min » en petit sous le nombre, sur une seconde ligne (retour 402), ce qui le rend plus
	 * étroit que sur une seule. `tabindex="-1"` laisse la tabulation aller de champ en champ.
	 *
	 * La durée reste au milieu des six pas sur une seule rangée, au téléphone comme au bureau (retour 386), et
	 * « min » se lit à toutes les largeurs (V28b) : empilé, il ne rend pas le pas plus large que son nombre.
	 */
	protected function getStep(int $minutes): string {

		$sign = ($minutes < 0) ? '−' : '+';
		$duration = abs($minutes);

		if($duration >= 60) {
			$label = $sign.intdiv($duration, 60).s("h");
		} else {
			$label = $sign.$duration.'<small>'.s("min").'</small>';
		}

		return '<a class="btn btn-sm btn-outline-primary planning-duration-step-button" data-planning-duration-step="'.$minutes.'" tabindex="-1">'.$label.'</a>';

	}

	protected function getOpen(string $key, ?float $time, string $class, array $attributes): string {

		return '<div class="planning-duration '.$class.'" data-duration="'.$key.'" data-time="'.($time ?? '').'"'.($attributes === [] ? '' : ' '.attrs($attributes)).'>';

	}

	/**
	 * Zéro se lit comme rien : aucun rond n'est allumé, et la durée lue s'éteint.
	 */
	protected function isEmpty(?float $time): bool {

		return ($time === NULL or $time <= 0.0);

	}

	/**
	 * La ligne de la durée lue, puis les heures, les minutes et « Enregistrer ». Le champ tapé vit dans un
	 * `fieldset` désactivé, que `PlanningDuration.type()` n'active que le temps de la frappe : le formulaire
	 * qui loge la saisie n'en poste jamais rien.
	 *
	 * La ligne de la durée lue porte aussi « Remettre à zéro » et la croix (R-Retours-0610). « Remettre à zéro »
	 * ramène la durée à 0 et laisse la grille ouverte, les ronds ne montrant pas toujours le 0. Il disparaît à
	 * zéro, la ligne gardant sa hauteur (`planning.css`) : la fenêtre ne change pas de taille.
	 *
	 * Choisir n'est pas agir : chaque heure et chaque minute est une pastille à contour, la valeur retenue est un
	 * état, la même pastille en blanc (`btn-chosen`), et « Enregistrer » reste le seul plein de la fenêtre (planche
	 * « Journée, les boutons pleins », v2 ; DT, sans le rail vert). `PlanningDuration.write()` la rallume.
	 */
	protected function getGrid(?float $time, array $hours): string {

		$empty = $this->isEmpty($time);
		[$hour, $minute] = $empty ? [NULL, NULL] : self::splitTime($time);

		$h = '<div class="planning-time-panel-grid">';

			$h .= '<div class="planning-time-panel-readout'.($empty ? ' planning-time-panel-readout-empty' : '').'" data-wrapper="time">';

				$title = '<b>'.($hour ?? 0).'</b><span>'.s("h").'</span><span>'.sprintf('%02d', $minute ?? 0).'</span>';

				$h .= '<a class="planning-time-panel-title" data-planning-duration-title>'.$title.'</a>';
				$h .= '<fieldset class="planning-time-panel-type" disabled>'.new \util\FormUi()->dynamicField(new \series\Timesheet(['timeAdd' => NULL]), 'timeAdd').'</fieldset>';

				$h .= '<a class="btn btn-sm btn-outline-primary planning-time-panel-reset" data-planning-time-reset'.($empty ? ' data-empty' : '').'>';
					$h .= \Asset::icon('arrow-counterclockwise');
					$h .= '<span class="planning-time-panel-reset-label">'.s("Remettre à zéro").'</span>';
				$h .= '</a>';

				$h .= '<a class="util-sheet-close planning-time-panel-close" data-planning-duration-close>'.\Asset::icon('x-lg').'</a>';

			$h .= '</div>';

			$h .= '<div class="planning-time-panel-row">';
				$h .= '<span class="planning-time-panel-label">'.s("Heures").'</span>';
				$h .= '<div class="planning-time-panel-hours">';
					foreach($hours as $hourGrid) {
						$h .= '<a class="'.self::getOptionClass($hourGrid === $hour).'" data-planning-time-hour="'.$hourGrid.'">'.$hourGrid.'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="planning-time-panel-row">';
				$h .= '<span class="planning-time-panel-label">'.s("Minutes").'</span>';
				$h .= '<div class="planning-time-panel-minutes">';
					foreach(self::MINUTES as $minuteGrid) {
						$h .= '<a class="'.self::getOptionClass($minuteGrid === $minute).'" data-planning-time-minute="'.$minuteGrid.'">'.sprintf('%02d', $minuteGrid).'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<a class="btn btn-primary" data-planning-duration-save>'.s("Enregistrer").'</a>';

		$h .= '</div>';

		return $h;

	}

	private static function getOptionClass(bool $selected): string {
		return 'btn btn-sm btn-outline-primary'.($selected ? ' btn-chosen' : '');
	}

}
?>
