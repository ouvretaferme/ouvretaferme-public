<?php
namespace planning;

/**
 * Le pense-bête de la Semaine (R-PenseBete), dans l'habit retenu sur la planche : une liste
 * punaisée, feuille blanche épinglée et un peu de travers, en tête des piles.
 *
 * Le bloc n'est pas un bloc d'intervention et n'en porte pas la classe (`planning-week-block`) : la
 * recherche de l'écran prend tout bloc de cette classe pour un groupe d'interventions, le masquerait
 * dès qu'un mot est tapé et remettrait son compte à zéro (`PlanningSearch`, `planning.js`). Il garde la
 * tête des blocs d'action (titre, compte, « + »), sans la case de groupe : un rappel ne se prend pas
 * dans un lot.
 *
 * Dans la grille de la Semaine, le pense-bête n'est plus une note : il est le premier groupe du tableau
 * (`getGridGroup()`), sous les mêmes identifiants, et une ligne y garde la case, le texte et ses gestes.
 *
 * Une réponse remplace le bloc entier (`#planning-reminder-block`) quand l'ordre peut bouger, et seulement
 * la ligne et la tête quand un rappel se coche, se décoche ou s'efface (`doReminder`), dans l'un ou l'autre
 * habit selon l'affichage.
 */
class ReminderUi {

	public function __construct() {
		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');
	}

	/**
	 * `$view` est l'affichage de la Semaine qui ouvre le panneau, que le formulaire réémet pour que la réponse
	 * redessine le pense-bête de cet affichage, quelle que soit la préférence du membre.
	 */
	public static function urlCreate(\structure\Structure $eStructure, string $week, string $view): string {

		return '/planning/taskWeek:reminderCreate?'.http_build_query([
			'id' => $eStructure['id'],
			'week' => $week,
			'view' => $view,
		]);

	}

	/**
	 * Le bloc, rangé avant toute action (`data-util-piles-rank` à -1, les blocs d'action partant de 0) :
	 * `MainPiles.stack()` le pose en tête de la première pile. Le compte de la tête est ce qui reste à
	 * faire, restés compris, comme celui d'un bloc d'action.
	 *
	 * @param array $reminders `cReminder`, les rappels de la semaine, `cReminderBefore`, ceux restés à
	 * faire des semaines d'avant, et `todo`, le compte de la tête (`planningTaskWeekReminders()`).
	 */
	public function getBlock(\structure\Structure $eStructure, string $week, array $reminders, bool $canCreate): string {

		$cReminder = $reminders['cReminder'];
		$cReminderBefore = $reminders['cReminderBefore'];
		$todo = $reminders['todo'];

		$h = '<section class="util-note planning-reminder-block" id="planning-reminder-block" data-util-piles-rank="-1" data-util-piles-uncounted>';

			$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';

			$h .= $this->getHead($eStructure, $week, $todo, $canCreate);

			if($cReminder->empty() and $cReminderBefore->empty()) {

				$h .= '<div class="util-empty">'.s("Rien pour cette semaine").'</div>';

			} else {

				foreach($cReminder as $eReminder) {
					$h .= $this->getReminder($eStructure, $week, $eReminder, FALSE);
				}

				if($cReminderBefore->notEmpty()) {

					$h .= '<div class="util-title planning-reminder-restes" id="planning-reminder-restes"><h5>'.s("Restés des semaines passées").'</h5></div>';

					foreach($cReminderBefore as $eReminder) {
						$h .= $this->getReminder($eStructure, $week, $eReminder, TRUE);
					}

				}

			}

		$h .= '</section>';

		return $h;

	}

	/**
	 * Les rappels d'une colonne du Jour, sur la même feuille épinglée que la Semaine, sans tête ni compte :
	 * la colonne a déjà les siens, et un rappel ne s'ajoute pas depuis le Jour. Une colonne sans rappel ne
	 * montre rien. Un resté se reconnaît à sa semaine, qui n'est pas celle de l'écran.
	 */
	public function getDay(\structure\Structure $eStructure, string $week, \Collection $cReminder): string {

		if($cReminder->empty()) {
			return '';
		}

		$h = '<div class="util-note planning-reminder-day">';

			$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';

			foreach($cReminder as $eReminder) {
				$h .= $this->getReminder($eStructure, $week, $eReminder, $eReminder['week'] !== $week, 'taskDay');
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La tête du bloc : le titre, le compte de ce qui reste à faire, et le « + ». Elle se redessine seule
	 * quand un rappel se coche, se décoche ou s'efface.
	 */
	public function getHead(\structure\Structure $eStructure, string $week, int $todo, bool $canCreate): string {

		$h = '<div class="util-title planning-block-head" id="planning-reminder-head">';
			$h .= '<div class="planning-block-name">';
				$h .= '<h4>'.s("Pense-bête").($todo > 0 ? ' <span class="util-counter">'.$todo.'</span>' : '').'</h4>';
			$h .= '</div>';
			$h .= '<div class="planning-block-end">';
				if($canCreate) {
					$h .= '<a href="'.encode(self::urlCreate($eStructure, $week, \structure\Member::CARDS)).'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne : la case, le texte, puis le jour, ou la semaine prévue pour un rappel resté. Coché, le
	 * texte est barré et gris, et le jour ne se dit plus, il n'a plus rien à rappeler.
	 *
	 * La case est celle d'une ligne à cocher d'une description d'intervention (`TaskUi::getDescription()`,
	 * `util-tick`), à la demande : une seule case pour les deux listes de choses à faire. Elle
	 * est un lien seulement pour qui peut faire le geste (`assertCheck()`, `assertUncheck()`) ; pour le
	 * planning limité, elle n'est qu'un dessin, sans `util-tick` comme la case d'une intervention qu'on ne
	 * peut pas modifier, et le texte ne s'ouvre pas.
	 *
	 * `$page` est l'écran qui reçoit le geste, `taskWeek` ou `taskDay`, chacun relisant et redessinant son
	 * pense-bête. Au Jour, la colonne dit le jour : la pastille ne reste que pour la semaine d'un resté. La note
	 * de la Semaine n'est rendue qu'en cartes, et ses gestes le disent (`view`), comme ceux de la grille disent
	 * la grille : deux onglets ouverts sur des affichages différents reçoivent chacun le leur.
	 */
	public function getReminder(\structure\Structure $eStructure, string $week, Reminder $eReminder, bool $before, string $page = 'taskWeek'): string {

		$done = $eReminder['done'];

		$view = match($page) {
			'taskWeek' => \structure\Member::CARDS,
			'taskDay' => NULL
		};

		$h = '<div class="planning-reminder'.($done ? ' planning-reminder-done' : '').'" id="planning-reminder-'.$eReminder['id'].'">';

			$h .= $this->getCheck($eStructure, $week, $eReminder, $page, $view);

			$h .= '<div>';
				$h .= $this->getText($eStructure, $week, $eReminder, $before, $page === 'taskWeek', $page, $view);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La case de l'état vu depuis la semaine de l'écran (`done`), un lien seulement pour qui peut faire le
	 * geste (`isCheckable()`).
	 */
	protected function getCheck(\structure\Structure $eStructure, string $week, Reminder $eReminder, string $page, ?string $view): string {

		$icon = \Asset::icon($eReminder['done'] ? 'check-circle' : 'circle');

		if($this->isCheckable($eReminder)) {
			return '<a data-ajax="/planning/'.$page.':'.($eReminder['done'] ? 'doReminderUncheck' : 'doReminderCheck').'" '.$this->getPost($eStructure, $week, $eReminder, $view).' class="util-tick planning-reminder-check">'.$icon.'</a>';
		} else {
			return '<span class="planning-reminder-check">'.$icon.'</span>';
		}

	}

	/**
	 * Le geste de la case : cocher ce qui se lit à faire, décocher ce qui se lit barré. Une semaine passée
	 * montre ce qui y était vrai, et seul ce qui l'est encore s'y défait : un rappel barré y est barré dans
	 * la semaine de sa coche, son état réel, et se décoche pour réparer une coche faite par erreur. Ce qui
	 * s'y lit à faire l'a été dans cette semaine-là, et se coche dans la semaine en cours, où il s'affiche.
	 */
	protected function isCheckable(Reminder $eReminder): bool {

		if($eReminder['isPast'] and $eReminder['done'] === FALSE) {
			return FALSE;
		}

		return $eReminder['done'] ? $eReminder->assertUncheck() : $eReminder->assertCheck();

	}

	/**
	 * Le texte suivi de la semaine d'un resté, ou de son jour quand `$day` le demande et qu'il reste à faire,
	 * modifiable sur place pour qui peut le changer.
	 */
	protected function getText(\structure\Structure $eStructure, string $week, Reminder $eReminder, bool $before, bool $day, string $page, ?string $view): string {

		$label = '<span class="planning-reminder-label">'.encode($eReminder['text']).'</span>';

		if($before) {
			$label .= ' <span class="badge badge-outline">'.s("s{value}", week_number($eReminder['week'])).'</span>';
		} else if($day and $eReminder['done'] === FALSE and $eReminder['date'] !== NULL) {
			$label .= ' <span class="badge badge-outline">'.\util\DateUi::getDayNameShort((int)date('N', strtotime($eReminder['date']))).'</span>';
		}

		if($this->isEditable($eReminder)) {
			return $this->getEdit($eStructure, $week, $eReminder, $label, $page, $view);
		} else {
			return $label;
		}

	}

	/**
	 * Le texte et le jour se corrigent dans la semaine en cours ou à venir, jamais dans une semaine passée :
	 * un rappel encore à faire se corrige dans la semaine en cours, où il s'affiche (`Reminder::acceptUpdate()`).
	 */
	protected function isEditable(Reminder $eReminder): bool {
		return ($eReminder['isPast'] === FALSE and $eReminder->assertUpdate());
	}

	/**
	 * Le pense-bête dans la grille de la Semaine (DC-bis, retour 334) : le premier groupe du tableau, sous son
	 * intertitre, une ligne par rappel, ceux de la semaine puis les restés. Le groupe et ses lignes portent les
	 * identifiants de la note (`#planning-reminder-block`, `#planning-reminder-head`, `#planning-reminder-<id>`),
	 * pour que les réponses de `doReminder` visent le même nœud dans les deux affichages. Un resté se reconnaît
	 * à sa semaine, sans intertitre à part. Sans rappel, le groupe garde son intertitre et son « + ».
	 *
	 * @param array $reminders Comme pour `getBlock()`.
	 */
	public function getGridGroup(\structure\Structure $eStructure, string $week, array $reminders, bool $canCreate): string {

		$h = '<div class="planning-grid-group planning-grid-reminders" id="planning-reminder-block">';

			$h .= $this->getGridHead($eStructure, $week, $reminders['todo'], $canCreate);

			foreach($reminders['cReminder'] as $eReminder) {
				$h .= $this->getGridRow($eStructure, $week, $eReminder, FALSE);
			}

			foreach($reminders['cReminderBefore'] as $eReminder) {
				$h .= $this->getGridRow($eStructure, $week, $eReminder, TRUE);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'intertitre du groupe, avec le compte de ce qui reste à faire et le « + », redessiné seul comme la tête
	 * de la note. Il n'a rien à cocher, mais garde la place de la case des intertitres d'action
	 * (`TaskWeekGridUi::getGroup()`), pour que les titres restent alignés.
	 */
	public function getGridHead(\structure\Structure $eStructure, string $week, int $todo, bool $canCreate): string {

		$h = '<div class="util-table-title planning-grid-title" id="planning-reminder-head">';
			$h .= '<span class="planning-select"></span>';
			$h .= '<h4>';
				$h .= s("Pense-bête").($todo > 0 ? ' <span class="util-counter">'.$todo.'</span>' : '');
				if($canCreate) {
					$h .= ' <a href="'.encode(self::urlCreate($eStructure, $week, \structure\Member::GRID)).'" class="btn btn-only-icon btn-xs btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
				}
			$h .= '</h4>';
			$h .= TaskWeekGridUi::getTitleDays($week);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne de la grille, dans ses colonnes : le texte sur celles de l'intervention et de la description,
	 * au retrait du nom d'une intervention, rien sous « Affecté à » ni « Prévu », le jour du rappel marqué parmi les sept
	 * tant qu'il reste à faire, rien sous « Récolté » ni « Semaine », et le cachet sous « Fait ». Pour qui peut
	 * l'éditer, un rappel à faire de la semaine se donne un jour depuis aujourd'hui et les jours à venir, et son
	 * point le retire (DP, DY, EZ) : la route de l'édition, réduite au jour (`origin`). Un jour passé n'a pas de
	 * geste, et un rappel posé sur lui garde son point sans geste (retour 365) ; la route accepte pourtant un
	 * jour passé, que les Cartes proposent, la grille seule le retirant à la vue. Un jour passé y prend le gris de
	 * sa colonne ; les jours à venir n'ont pas leur teinte, propre aux interventions (retour 339). Un rappel coché
	 * prend la classe d'une ligne faite, que lit aussi le compte de la recherche (`PlanningTaskSearch`).
	 *
	 * La ligne ne porte pas `data-search` : un rappel n'a ni personne, ni intervention, ni espèce, et la
	 * recherche ne le prend pas pour une intervention (`PlanningTaskSearch.getCards()`).
	 */
	public function getGridRow(\structure\Structure $eStructure, string $week, Reminder $eReminder, bool $before): string {

		$done = $eReminder['done'];

		$h = '<div class="util-table-row planning-grid-row planning-grid-reminder'.($done ? ' planning-grid-row-done planning-reminder-done' : '').'" id="planning-reminder-'.$eReminder['id'].'">';

			// La place de la case et de la vignette d'une intervention (`TaskWeekGridUi::getName()`) : un rappel ne se
			// coche pas, et son texte reste au retrait du nom.
			$h .= '<div class="planning-grid-name planning-grid-reminder-text">';
				$h .= '<span class="planning-select"></span>';
				$h .= '<span class="planning-grid-vignette"></span>';
				$h .= '<div class="planning-grid-label">'.$this->getText($eStructure, $week, $eReminder, $before, FALSE, 'taskWeek', \structure\Member::GRID).'</div>';
			$h .= '</div>';

			// Les cases vides d'« Affecté à » et de « Prévu », dans l'enveloppe d'une ligne d'intervention
			// (`TaskWeekGridUi::getRow()`) : sur un écran étroit, les colonnes qu'elles tiennent disparaissent avec elle.
			$h .= '<div class="planning-grid-assign">';

				$h .= '<div></div>';

				if($eStructure->hasTime()) {
					$h .= '<div></div>';
				}

			$h .= '</div>';

			// Un resté garde sa ligne sans geste : son jour est de sa semaine, que les colonnes ne montrent pas.
			$plan = ($done === FALSE and $before === FALSE and $this->isEditable($eReminder));
			$today = \Clock::date();

			foreach(week_dates($week) as $date) {

				$planDay = ($plan and $date >= $today);

				$h .= '<div class="planning-grid-day'.TaskWeekGridUi::getTodayClass($date).'">';
					if($done === FALSE and $eReminder['date'] === $date) {
						$h .= $planDay ?
							'<a data-ajax="/planning/taskWeek:doReminderUpdate" '.$this->getPost($eStructure, $week, $eReminder, \structure\Member::GRID).' post-origin="date" post-date="" class="planning-grid-cell planning-grid-planned"></a>' :
							'<span class="planning-grid-cell planning-grid-planned"></span>';
					} else if($planDay) {
						$h .= '<a data-ajax="/planning/taskWeek:doReminderUpdate" '.$this->getPost($eStructure, $week, $eReminder, \structure\Member::GRID).' post-origin="date" post-date="'.$date.'" class="planning-grid-cell planning-grid-plannable"></a>';
					}
				$h .= '</div>';

			}

			$h .= '<div></div>';

			if($eStructure->hasTime()) {
				$h .= '<div></div>';
			}

			$h .= $this->getGridCheck($eReminder);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le cachet « Fait » d'une ligne d'intervention (`TaskWeekGridUi::getDone()`), qui coche ou décoche le rappel
	 * par `doReminderCheck` et `doReminderUncheck`. Sans l'un ni l'autre, il se voit éteint, sans geste.
	 */
	protected function getGridCheck(Reminder $eReminder): string {

		$done = $eReminder['done'];
		$canWrite = $this->isCheckable($eReminder);

		$h = '<div class="util-checkbox planning-grid-box">';
			$h .= '<label>';
				$h .= new \util\FormUi()->inputCheckbox(NULL, '1', [
					'checked' => $done,
					'data-planning-grid-reminder' => $eReminder['id'],
				] + ($canWrite ? [] : ['disabled' => 'disabled']));
				$h .= '<span class="planning-grid-stamp">'.\Asset::icon('check').'</span>';
			$h .= '</label>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que postent les liens d'un rappel : l'écran, la semaine, le rappel, et l'affichage de la Semaine quand
	 * le geste y est fait.
	 */
	protected function getPost(\structure\Structure $eStructure, string $week, Reminder $eReminder, ?string $view): string {

		$post = 'post-id="'.$eStructure['id'].'" post-week="'.$week.'" post-reminder="'.$eReminder['id'].'"';

		if($view !== NULL) {
			$post .= ' post-view="'.$view.'"';
		}

		return $post;

	}

	/**
	 * Le texte qui devient champ sur place. Pendant la saisie s'ouvrent dessous les pastilles du jour et
	 * « Effacer ». Une pastille ne fait que choisir le jour : ✓, Entrée ou la sortie de la boîte enregistrent
	 * le texte et le jour ensemble, et Échap rend les deux d'origine. Le jour voyage dans le formulaire de la
	 * boîte, en champ caché : les pastilles vivent hors du formulaire, sous la ligne, et une seule écriture
	 * porte les deux propriétés que `doReminderUpdate` construit.
	 */
	protected function getEdit(\structure\Structure $eStructure, string $week, Reminder $eReminder, string $label, string $page, ?string $view): string {

		$form = new \util\FormUi();

		$id = 'planning-reminder-edit-'.$eReminder['id'];

		$hidden = [
			'id' => $eStructure['id'],
			'week' => $week,
			'reminder' => $eReminder['id'],
		];

		if($view !== NULL) {
			$hidden['view'] = $view;
		}

		$field = '<div data-wrapper="text">'.$form->dynamicField($eReminder, 'text').'</div>';
		$field .= $form->hidden('date', $eReminder['date']);

		$h = \main\QuickInlineAiUi::get($id, \main\QuickInlineAiUi::open($label), '/planning/'.$page.':doReminderUpdate', $hidden, $field, submitOnBlur: TRUE);

		$linked = $this->getDays($eReminder);

		if($eReminder->assertDelete()) {
			$linked .= '<a data-ajax="/planning/'.$page.':doReminderDelete" '.$this->getPost($eStructure, $week, $eReminder, $view).' data-confirm="'.s("Effacer ce rappel ?").'" class="planning-reminder-delete">'.s("Effacer").'</a>';
		}

		$h .= \main\QuickInlineAiUi::linked($id, $linked, 'div', 'planning-reminder-edit');

		return $h;

	}

	/**
	 * « Non défini » puis les sept jours de la semaine du rappel (`$eReminder['days']`, préparés par la
	 * page), le jour retenu mis en avant, comme le jour de « Nouvelle intervention » (`TaskCreateUi::getDays()`,
	 * R-Retours-1210, BM). « Non défini » porte un jour vide : `PlanningReminderDay` (`planning.js`) le choisit
	 * comme les autres et vide le champ caché.
	 */
	public function getDays(Reminder $eReminder): string {

		$h = '<div class="util-days planning-reminder-days">';

			$h .= new TimeWeekUi()->getDayNone(['util-day'], $eReminder['date'] === NULL, 'data-planning-reminder-day=""');

			foreach($eReminder['days'] as $date => $day) {

				$selected = ($date === $eReminder['date']);

				$classes = ['util-day'];

				if($day['today']) {
					$classes[] = 'is-today';
				}

				if($selected) {
					$classes[] = 'is-selected';
				}

				$h .= new TimeWeekUi()->getDayDate($date, $classes, $selected, 'data-planning-reminder-day="'.$date.'"');

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Nouveau rappel » : le texte, puis le jour, facultatif, parmi ceux de la semaine affichée. La
	 * semaine part de l'écran (`week`), que la page relit et valide avant de la poser sur le rappel.
	 */
	public function create(Reminder $eReminder, string $view): \Panel {

		$form = new \util\FormUi();

		$h = $form->hidden('id', $eReminder['structure']);
		$h .= $form->hidden('week', $eReminder['week']);
		$h .= $form->hidden('view', $view);

		$h .= $form->dynamicGroup($eReminder, 'text', function(\PropertyDescriber $d) {
			$d->attributes = ['onrender' => 'this.focus();'];
		});

		$h .= $form->dynamicGroup($eReminder, 'date');

		$h .= $form->group(
			content: $form->submit(s("Ajouter le rappel"))
		);

		return new \Panel(
			id: 'panel-planning-reminder-create',
			title: s("Nouveau rappel"),
			subTitle: s("Semaine {value}", week_number($eReminder['week'])),
			dialogOpen: $form->openAjax('/planning/taskWeek:doReminderCreate', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Reminder::model()->describer($property, [
			'text' => s("Rappel"),
			'date' => s("Jour"),
		]);

		switch($property) {

			case 'date' :
				$d->field = fn(\util\FormUi $form, Reminder $e) => $form->hidden('date', $e['date']).new ReminderUi()->getDays($e);
				break;

		}

		return $d;

	}

}
?>
