<?php
namespace planning;

/**
 * L'écran « Interventions » d'une semaine : la rangée des onglets, celle des outils (la bascule Semaine /
 * Jour à gauche, « Masquer ce qui est fait » à droite, `semaine()` de la maquette
 * navigable), puis un bloc par action, que le navigateur répartit en piles selon la largeur
 * (`util-piles`, `MainPiles.stack()`).
 *
 * Chaque nœud qu'un geste peut changer a son identifiant stable, pour que la réponse le remplace seul :
 * la carte (`#planning-task-<id>`, `TaskCardUi`), le compte de son bloc
 * (`#planning-week-counter-<action>`) et l'écran des blocs
 * (`#planning-week-board`).
 *
 * Sous les blocs de la semaine, deux zones (L8) : « En retard », déplié, puis « Non planifié », replié et
 * chargé à sa première ouverture (`taskWeek:unplanned`). Chacune répète les mêmes nœuds que la semaine,
 * préfixés par son nom (`#planning-week-late-block-<action>`, `#planning-week-late-counter-<action>`), et
 * porte son compte et son écran des blocs (`#planning-week-late-counter`, `#planning-week-late-board`).
 * Les cartes gardent `#planning-task-<id>` : une intervention n'est jamais à la fois dans la semaine et
 * dans une zone (`TaskLateLib::getKeys()`).
 */
class TaskWeekUi {

	/**
	 * La largeur minimale d'une pile, en rem, à chaque densité (`data-pile-width`, lu par `MainPiles.stack()`).
	 * « Normal » est la largeur du socle (`MainPiles.PILE_WIDTH`), mesurée sur la planche : 4, 3 puis 2 piles
	 * à 1199 px, 5, 4 puis 3 à 1854 px.
	 */
	public const PILE_WIDTHS = [
		\structure\Member::DENSE => 16,
		\structure\Member::NORMAL => 20,
		\structure\Member::SPACIOUS => 26,
	];

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');
		\Asset::js('main', 'pilesAi.js');
		\Asset::lib('util', 'Sortable-1.11.0.js');

	}

	public static function url(\structure\Structure $eStructure, ?string $week = NULL, ?string $view = NULL): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/semaine';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		if($view !== NULL) {
			$url .= '?view='.$view;
		}

		return $url;

	}

	public static function urlCustomize(\structure\Structure $eStructure, string $week, string $origin = 'semaine'): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/personnaliser';

		if($week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		if($origin !== 'semaine') {
			$url .= '?origin='.$origin;
		}

		return $url;

	}

	/**
	 * L'en-tête de semaine du planning actuel, comme l'écran des récoltes (`HarvestWeekUi::getCalendar()`).
	 * Changer de semaine garde le masque.
	 */
	public function getCalendar(\structure\Structure $eStructure, string $week): string {

		return new \series\PlanningUi()->getCalendar(
			$eStructure,
			$week,
			fn($week) => self::url($eStructure, $week)
		);

	}

	/**
	 * L'écran entier. Il porte ce que les gestes réémettent (`data-week`),
	 * pour que leur réponse dessine l'écran que l'on regarde, et la sélection de L5d : les cases des cartes
	 * que la barre du lot compte (`data-batch`), et la barre elle-même, en pied. « + À faire » et « + Fait »
	 * ferment la rangée des outils à droite, le reste à gauche (R-Retours-1210 V61) ; en Colonnes,
	 * chaque colonne porte le sien.
	 *
	 * `data-unplanned` dit que « Non planifié » est chargé : les gestes le réémettent pour que la réponse
	 * redessine aussi ses cartes (L8). `data-folded` liste les zones que la personne a repliées, réémises
	 * pour qu'une réponse ne les rouvre pas. `data-view` est l'affichage montré, cartes ou grille (CW), réémis
	 * pour que la réponse redessine cet écran-là, quoi que le membre ait choisi depuis dans un autre onglet.
	 *
	 * Les onglets sont au-dessus, hors de l'écran (`taskWeek.v.php`).
	 *
	 * Un écran sans aucune intervention (`$empty`, `TaskWeekLib::isEmpty()`) ne garde de la rangée des outils
	 * que la période : rien à chercher, masquer ni sélectionner, et « Nouvelle intervention » passe dans
	 * l'état vide des blocs (R-Retours-0410, point 18).
	 *
	 * Les affichages « Cartes », « Deux colonnes » et « Tableau » sont le menu du jeton « Semaine » de la période
	 * (`TabUi::getPeriod()`, DE), au téléphone comme à l'ordinateur (FL).
	 *
	 * En grille (`$grid`, CW), la grille prend la place des blocs dans `#planning-week-board`, et
	 * « Sélectionner » n'est pas rendu : la sélection coche des cartes, que la grille n'a pas. Les zones
	 * restent sous elle, « Non planifié » en cartes et « En retard » en lignes (DZ). En Colonnes, « Sélectionner »
	 * n'est pas rendu non plus : les cases y sont toujours visibles (R-Retours-1210 V42). `$locked` (`TaskWeekLib::hasLocked()`) dit sous la grille pourquoi une
	 * intervention commencée n'a pas de geste sur ses jours (DF), et `$legend` y ajoute celle du point du jour
	 * prévu (DY, EZ).
	 */
	public function get(\structure\Structure $eStructure, string $week, string $weekNext, \Collection $cTask, \structure\Member $eMember, \Collection $ccTask, \Collection $ccTaskTodo, \Collection $ccTaskDone, array $countByStatus, array $countByAction, array $todo, \Collection $cUser, bool $canCreate, array $canCreateByStatus, bool $empty, array $zones, array $folded, array $dues, ?array $reminders, bool $canReminder, bool $canHarvest, float $timeExpected, string $view, bool $locked, bool $legend): string {

		$unplanned = $zones[TaskLateLib::UNPLANNED]['loaded'];
		$grid = ($view === \structure\Member::GRID);
		$columns = ($view === \structure\Member::COLUMNS);

		$h = '<div id="planning-week" class="'.self::getCustomizeClass($eMember).'" data-structure="'.$eStructure['id'].'" data-week="'.$week.'" data-unplanned="'.($unplanned ? '1' : '').'" data-folded="'.implode(',', $folded).'" data-view="'.$view.'"'.($columns ? ' data-pile="'.\series\Task::TODO.'"' : '').' data-batch="#planning-batch" onrender="PlanningTaskSelection.apply()">';

			$uiTab = new TabUi();

			$weekViews = [];

			foreach(\structure\MemberUi::p('viewPlanningWeek')->values as $value => $label) {
				$weekViews[$value] = [$label, self::url($eStructure, $week, $value), $value === $view];
			}

			$period = $uiTab->getPeriod('week', self::url($eStructure, $week), TaskDayUi::url($eStructure, $week), TaskYearUi::url($eStructure, $week), $weekViews);

			if($empty) {

				$h .= $uiTab->getTools($period);

			} else {

				$create = TaskCreateUi::getButton($eStructure, 'taskWeek', $week, $canCreateByStatus, $view);

				// En Colonnes, chaque colonne porte son « + À faire » ou son « + Fait » (`getColumnCreate()`).
				if($columns) {
					$mode = '';
				} else if($grid) {
					$mode = $uiTab->getHideSwitch($eMember).$uiTab->getActions($create);
				} else {
					$mode = $uiTab->getHideSwitch($eMember).$uiTab->getActions(new TaskBatchUi()->getSelectButton().$create);
				}

				$h .= $uiTab->getTools(
					$period.
					$uiTab->getSearch(cUser: $cUser),
					mode: $mode
				);

				$h .= $uiTab->getSearchEmpty();

			}

			$h .= $this->getEstimated($timeExpected);
			if($grid) {
				$uiTaskWeekGrid = new TaskWeekGridUi();
				$h .= $uiTaskWeekGrid->getBoard($eStructure, $week, $cTask, $ccTask, $todo, $cUser, $canCreate, $canCreateByStatus, $empty, $reminders, $canReminder, $canHarvest);
				$h .= $uiTaskWeekGrid->getLocked($locked);
				$h .= $uiTaskWeekGrid->getLegend($legend);
			} else if($columns) {
				$h .= $this->getColumnsTabs($eStructure, $week, $countByStatus, $canCreateByStatus);
				$h .= $this->getColumns($eStructure, $week, $ccTaskTodo, $ccTaskDone, $countByAction, $cUser, $canCreateByStatus, $reminders, $canReminder);
			} else {
				$h .= $this->getBoard($eStructure, $week, $cTask, $ccTask, $todo, $cUser, $canCreate, $canCreateByStatus, $empty, $eMember, $reminders, $canReminder);
			}

			$h .= $this->getSwipe($eStructure, $weekNext, $canHarvest);
			$h .= new DueUi()->getZones($eStructure, $week, $dues, $folded);
			$h .= $this->getZones($eStructure, $week, $zones, $cUser, $grid, $canHarvest);

			$h .= new TaskBatchUi()->get($eStructure, 'taskWeek', $week, ['id' => $eStructure['id'], 'week' => $week, 'unplanned' => $unplanned ? '1' : '', 'folded' => implode(',', $folded), 'view' => $view], $cUser);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le travail estimé de la semaine (`TaskWeekLib::getTimeExpected()`, R-Retours-1210, 312), au-dessus des
	 * blocs. Le nœud reste quand il n'y a rien à dire : un geste qui donne un temps prévu à la semaine le
	 * remplit sur place.
	 */
	public function getEstimated(float $timeExpected): string {

		$h = '<div id="planning-week-estimated" class="util-annotation planning-week-estimated">';
			if($timeExpected > 0.0) {
				$h .= s("Travail estimé à {value} sur la semaine", \series\TaskUi::convertTime($timeExpected));
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que découvre une carte des blocs de la semaine qu'on tire de côté (retour 176, `PlanningTaskSwipe`) :
	 * à droite, la semaine suivante, où la carte part en la lâchant ; à gauche, la suppression, qui passe
	 * par la confirmation du socle (`data-confirm`). Le navigateur les pose sous la carte le temps du geste.
	 * Sans droit d'écrire sur le planning, il n'y a pas de geste.
	 *
	 * Une récolte d'une série découvre en plus « Récolter » (retour 203), qui ouvre la saisie de l'écran des
	 * récoltes : à droite au doigt, avant la semaine suivante, en tirant vers le haut à la souris. Il n'existe
	 * que là où cet écran accepte une saisie (`$canHarvest`), donc pas dans une semaine à venir.
	 */
	public function getSwipe(\structure\Structure $eStructure, string $weekNext, bool $canHarvest): string {

		if($eStructure->canPlanning() === FALSE) {
			return '';
		}

		$h = '<template id="planning-week-swipe">';
			if($canHarvest) {
				$h .= '<div class="planning-task-swipe planning-task-swipe-harvest">';
					$h .= \Asset::icon('basket2').' '.s("Récolter");
				$h .= '</div>';
			}
			$h .= '<div class="planning-task-swipe planning-task-swipe-next" data-increment="1">';
				$h .= \Asset::icon('arrow-right').' '.s("Semaine {value}", week_number($weekNext));
			$h .= '</div>';
			$h .= '<a class="planning-task-swipe planning-task-swipe-delete" data-confirm="'.s("Supprimer cette intervention ?").'">';
				$h .= \Asset::icon('trash').' '.s("Supprimer");
			$h .= '</a>';
		$h .= '</template>';

		return $h;

	}

	/**
	 * La page « Personnaliser le planning » (R-Retours-0410-soir, A) : l'affichage, puis l'ordre des
	 * interventions. Chaque ligne lâchée s'enregistre aussitôt (`taskWeek:doOrder`) et la Semaine se range
	 * à son ouverture, donc la flèche de retour de l'en-tête n'est qu'un lien vers l'écran d'origine
	 * (`TabUi::getUrl()`), qui attend le dernier lâcher (`PlanningOrder.click()`).
	 */
	public function getCustomize(\structure\Structure $eStructure, string $week, \structure\Member $eMember): string {

		$h = '<div id="planning-customize" class="'.self::getColorClass($eMember).'" data-structure="'.$eStructure['id'].'" data-week="'.$week.'">';

			$h .= '<div class="util-title"><h3>'.s("Affichage").'</h3></div>';
			$h .= $this->getCustomizeTools($eMember);

			$h .= '<div class="util-title mt-2"><h3>'.s("Ordre des interventions").' <span class="util-counter">'.$eMember['cAction']->count().'</span></h3></div>';
			$h .= $this->getCustomizeList($eMember);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le picto d'un affichage de la Semaine (DM), le même dans la pastille du jeton « Semaine » et dans les menus
	 * (`TabUi::getPeriod()`).
	 */
	public static function getViewIcon(string $view): string {

		return \Asset::icon(match($view) {
			\structure\Member::CARDS => 'card-list',
			\structure\Member::COLUMNS => 'layout-split',
			\structure\Member::GRID => 'table',
		});

	}

	/**
	 * Les classes que la densité et la couleur du membre posent sur l'écran (R-Personnalisation) ; la feuille
	 * ne lit qu'elles. La couleur vaut aussi pour le Jour et l'Année.
	 * Sans membre, l'écran garde le rendu par défaut.
	 */
	public static function getCustomizeClass(\structure\Member $eMember): string {

		return implode(' ', array_filter(['planning-density-'.self::getDensity($eMember), self::getColorClass($eMember)]));

	}

	private static function getDensity(\structure\Member $eMember): string {

		return $eMember->empty() ? \structure\Member::NORMAL : $eMember['viewPlanningDensity'];

	}

	public static function getColorClass(\structure\Member $eMember): string {

		return ($eMember->notEmpty() and $eMember['viewPlanningColor']) ? 'planning-color' : '';

	}

	/**
	 * Les réglages, nommés comme les filtres d'une liste (`util-filters`) : la densité et les ventes à
	 * venir en jetons, la couleur et le pense-bête en interrupteurs. Chaque geste écrit sa préférence, et la
	 * réponse redessine ce bandeau (`taskWeek:doDensity`, `doColor`, `doReminders`, `doSalesDays`). Les
	 * ventes à venir nomment l'accueil, où elles se lisent (`DashboardLib::getSales()`). Le libellé de la
	 * couleur envoie la même chose que l'interrupteur, pour la raison dite sur `TabUi::getHideSwitch()`.
	 */
	public function getCustomizeTools(\structure\Member $eMember): string {

		$structure = $eMember['structure']['id'];
		$color = $eMember['viewPlanningColor'];
		$reminders = $eMember['viewPlanningReminders'];

		$h = '<div class="util-filters" id="planning-customize-tools">';

			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.s("Densité").'</div>';
				$h .= '<div class="util-filter-options">';
					foreach(\structure\MemberUi::p('viewPlanningDensity')->values as $density => $label) {
						$h .= '<a data-ajax="/planning/taskWeek:doDensity" post-id="'.$structure.'" post-view-planning-density="'.$density.'" class="util-filter-option'.($density === $eMember['viewPlanningDensity'] ? ' is-selected' : '').'">'.$label.'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';

			$attributes = [
				'data-ajax' => '/planning/taskWeek:doColor',
				'post-id' => $structure,
				'post-view-planning-color' => $color ? 0 : 1,
			];

			$h .= '<div class="util-filter">';
				$h .= '<a '.attrs($attributes).' class="util-filter-label">'.s("Couleur des interventions").'</a>';
				$h .= \util\TextUi::switch($attributes, on: $color);
			$h .= '</div>';

			$attributes = [
				'data-ajax' => '/planning/taskWeek:doReminders',
				'post-id' => $structure,
				'post-view-planning-reminders' => $reminders ? 0 : 1,
			];

			$h .= '<div class="util-filter">';
				$h .= '<a '.attrs($attributes).' class="util-filter-label">'.s("Pense-bête").'</a>';
				$h .= \util\TextUi::switch($attributes, on: $reminders);
			$h .= '</div>';

			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.s("Ventes à venir").'</div>';
				$h .= '<div class="util-filter-options">';
					foreach(PlanningSetting::SALES_DAYS as $salesDays) {
						$h .= '<a data-ajax="/planning/taskWeek:doSalesDays" post-id="'.$structure.'" post-view-planning-sales-days="'.$salesDays.'" class="util-filter-option'.($salesDays === $eMember['viewPlanningSalesDays'] ? ' is-selected' : '').'">'.self::getSalesDaysLabel($salesDays).'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une fenêtre des ventes à venir, sans date : le réglage vaut pour toutes les semaines. La roue et l'en-tête
	 * de la colonne des Récoltes (`HarvestWeekUi::getSalesHead()`) la nomment de même.
	 */
	public static function getSalesDaysLabel(int $salesDays): string {

		return match($salesDays) {
			0 => s("Aujourd'hui"),
			default => p("{value} jour", "{value} jours", $salesDays)
		};

	}

	/**
	 * Toutes les actions actives de la structure, une ligne chacune, dans l'ordre de la personne puis dans
	 * l'ordre alphabétique (`TaskWeekLib::getActions()`) : on y range ce qui passe en premier, y compris les
	 * actions absentes de la semaine. Le nom est celui de l'action, tel quel, même quand il n'est qu'un
	 * symbole. La feuille ne montre la pastille que sous `.planning-color`, et une action sans couleur a la
	 * sienne, grise, comme dans le détail du jour de la fiche de temps (`TimeDayUi::getTasksSpent()`).
	 */
	public function getCustomizeList(\structure\Member $eMember): string {

		$h = '<div id="planning-customize-list" class="util-deck util-deck-open" onrender="PlanningOrder.open(this)">';
			$h .= '<div class="util-deck-fold">';

				$rank = 1;

				foreach($eMember['cAction'] as $eAction) {

					$h .= '<div class="util-deck-row planning-customize-row" data-action="'.$eAction['id'].'">';
						$h .= \main\DesignUi::getDragHandle();
						$h .= '<span class="planning-customize-rank">'.$rank++.'</span>';
						$h .= \work\ActionUi::getCircle($eAction);
						$h .= '<span class="util-deck-row-name">'.encode($eAction['name']).'</span>';
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rond de la couleur de l'action, devant son nom dans un groupe du Jour et de l'Année, où il remplace le
	 * liseré que garde un bloc de la Semaine (`getAccentAttributes()`, R-Retours-0810, 230). Une action sans
	 * couleur n'en a pas : le rond y nomme une couleur choisie, alors que dans « Personnaliser le planning » une
	 * pastille grise tient la colonne de la liste (R-Retours-0810, AB, point 12). La feuille ne le montre que sous
	 * `.planning-color`.
	 */
	public static function getActionCircle(\work\Action $eAction): string {

		return $eAction->hasColor() ? \work\ActionUi::getCircle($eAction) : '';

	}

	/**
	 * La classe et la couleur d'un groupe d'action (R-Personnalisation, piste a) : `--accent` est posé dès que
	 * l'action a une couleur choisie, que la couleur soit allumée ou non, pour que l'interrupteur n'ait qu'une
	 * classe à basculer sur l'écran. La feuille ne trace le liseré que sous `.planning-color`.
	 */
	public static function getAccentAttributes(\work\Action $eAction, string $class): string {

		if($eAction->hasColor()) {
			return 'class="'.$class.' planning-accent" style="--accent: '.encode($eAction['color']).'"';
		} else {
			return 'class="'.$class.'"';
		}

	}

	/**
	 * Les blocs, dans l'ordre que la personne a posé, puis les autres dans l'ordre alphabétique
	 * (`TaskWeekLib::getBlocks()`). Sans bloc, l'état vide dit si la semaine est vide ou si ce sont les
	 * filtres qui la vident, et porte « Nouvelle intervention » quand l'écran entier est vide (`$empty`),
	 * la rangée des outils qui la montre d'habitude étant alors retirée.
	 *
	 * Les piles se composent à la largeur que la densité du membre donne (`data-pile-width`, `PILE_WIDTHS`).
	 *
	 * Le pense-bête (`$reminders`, R-PenseBete), quand le membre l'a allumé, est la première carte des piles.
	 * Sur une semaine sans bloc, l'état vide est la carte suivante, que la feuille du planning étend sur les
	 * colonnes à droite du pense-bête, et qui passe dessous à une seule pile (lot CL, retour 296). Il vit
	 * dans ce nœud parce que c'est lui que remplacent les réponses `board`, qui le redessinent avec les blocs.
	 */
	public function getBoard(\structure\Structure $eStructure, string $week, \Collection $cTask, \Collection $ccTask, array $todo, \Collection $cUser, bool $canCreate, array $canCreateByStatus, bool $empty, \structure\Member $eMember, ?array $reminders, bool $canReminder): string {

		$pileWidth = self::PILE_WIDTHS[self::getDensity($eMember)];

		if($ccTask->empty()) {

			$emptyContent = '<h3>'.($cTask->empty() ? s("Aucune intervention cette semaine") : s("Rien ne reste à montrer avec ces filtres")).'</h3>';

			if($empty and $canCreate) {
				$emptyContent .= TaskCreateUi::getEmptyButton($eStructure, 'taskWeek', $week, $canCreateByStatus, \structure\Member::CARDS);
			}

			if($reminders === NULL) {
				return '<div id="planning-week-board" class="util-block-empty" data-pile-width="'.$pileWidth.'">'.$emptyContent.'</div>';
			}

			$h = '<div id="planning-week-board" class="util-piles" data-pile-width="'.$pileWidth.'" onrender="MainPiles.stack(this)">';
				$h .= new ReminderUi()->getBlock($eStructure, $week, $reminders, $canReminder);
				$h .= '<div class="util-block-empty">'.$emptyContent.'</div>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div id="planning-week-board" class="util-piles" data-pile-width="'.$pileWidth.'" onrender="MainPiles.stack(this)">';

			if($reminders !== NULL) {
				$h .= new ReminderUi()->getBlock($eStructure, $week, $reminders, $canReminder);
			}

			$rank = 0;

			foreach($ccTask as $action => $cTaskAction) {
				$h .= $this->getBlock($eStructure, $week, $cTaskAction, $todo[$action], $cUser, $canCreate, $rank++);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La bascule « À faire | Fait » des Colonnes au téléphone (GQ, point 8), où une seule colonne tient : elle
	 * pose la colonne montrée sur l'écran (`data-pile`, `PlanningTaskColumns`), et non sur les colonnes, que les
	 * réponses remplacent. Elle est hors du tableau, et son compte est le seul nœud qu'un geste y change.
	 * À sa droite, le « + À faire » ou le « + Fait » de chaque colonne (`getColumnCreate()`), dont seul celui de la
	 * colonne montrée paraît.
	 */
	public function getColumnsTabs(\structure\Structure $eStructure, string $week, array $countByStatus, array $canCreateByStatus): string {

		$h = '<div class="util-title hide-md-up mb-1">';

			$h .= '<div class="tabs-item planning-week-piles" onrender="PlanningTaskColumns.restore(this)">';

				foreach(\series\TaskUi::p('status')->values as $status => $label) {
					$h .= '<a class="tab-item'.($status === \series\Task::TODO ? ' selected' : '').'" data-planning-pile="'.$status.'">'.$label.' '.$this->getColumnCounter($status, $countByStatus[$status]).'</a>';
				}

			$h .= '</div>';

			foreach($canCreateByStatus as $status => $canCreate) {
				if($canCreate) {
					$h .= '<div class="planning-week-pile-create" data-status="'.$status.'">'.$this->getColumnCreate($eStructure, $week, $status).'</div>';
				}
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « + À faire » ou « + Fait » d'une colonne, qui ouvre le panneau sur son état : c'est le seul geste de création
	 * des Colonnes (V57).
	 */
	public function getColumnCreate(\structure\Structure $eStructure, string $week, string $status): string {

		return TaskCreateUi::getButtonTo(TaskCreateUi::url($eStructure, 'taskWeek', $week, status: $status, view: \structure\Member::COLUMNS), $status);

	}

	public function getColumnCounter(string $status, int $count): string {

		return '<span class="util-counter" id="planning-week-'.$status.'-counter">'.$count.'</span>';

	}

	/**
	 * Les Colonnes (GQ) : « À faire » et « Fait » côte à côte, comme la Semaine de l'ancien planning, chacune
	 * avec les blocs des Cartes réduits aux interventions de son état. Le partage est fait ici et non par
	 * `MainPiles`, qui répartit des blocs selon leur hauteur : l'ordre des blocs est celui du document.
	 */
	public function getColumns(\structure\Structure $eStructure, string $week, \Collection $ccTaskTodo, \Collection $ccTaskDone, array $countByAction, \Collection $cUser, array $canCreateByStatus, ?array $reminders, bool $canReminder): string {

		$h = '<div id="planning-week-board" class="util-cards util-cards-2 util-cards-start">';
			$h .= $this->getColumn($eStructure, $week, \series\Task::TODO, $ccTaskTodo, $countByAction[\series\Task::TODO], $cUser, $canCreateByStatus[\series\Task::TODO], $reminders, $canReminder);
			$h .= $this->getColumn($eStructure, $week, \series\Task::DONE, $ccTaskDone, $countByAction[\series\Task::DONE], $cUser, $canCreateByStatus[\series\Task::DONE], $reminders, $canReminder);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une colonne, rendue seule quand une intervention y arrive : sans `MainPiles`, un bloc neuf n'a sa place
	 * que dans la colonne entière. Le pense-bête ouvre « À faire » et n'est jamais dans « Fait ». Une colonne
	 * vide le dit en une ligne. Le compte de chaque bloc vient de la page (`TaskWeekLib::getColumns()`) : une
	 * carte gardée dans « À faire » n'y compte pas.
	 * Le titre porte à sa droite le « + À faire » ou le « + Fait » de la colonne (`getColumnCreate()`).
	 */
	public function getColumn(\structure\Structure $eStructure, string $week, string $status, \Collection $ccTaskStatus, array $countByAction, \Collection $cUser, bool $canCreate, ?array $reminders, bool $canReminder): string {

		$h = '<div id="planning-week-'.$status.'" class="util-cards util-cards-1 planning-week-pile">';

			// La grille de la colonne espace déjà le titre de son premier bloc, comme deux blocs entre eux.
			$h .= '<div class="util-title mb-0 hide-sm-down">';
				$h .= '<h2>'.\series\TaskUi::p('status')->values[$status].'</h2>';
				if($canCreate) {
					$h .= $this->getColumnCreate($eStructure, $week, $status);
				}
			$h .= '</div>';

			if($status === \series\Task::TODO and $reminders !== NULL) {
				$h .= new ReminderUi()->getBlock($eStructure, $week, $reminders, $canReminder);
			}

			if($ccTaskStatus->empty()) {

				// Sous la colonne vide, « Non planifié » garde l'écart d'un bloc : la grille l'espace déjà.
				$h .= '<div class="util-empty mb-0">'.match($status) {
					\series\Task::TODO => s("Rien à faire cette semaine"),
					\series\Task::DONE => s("Rien de fait cette semaine"),
				}.'</div>';

			} else {

				foreach($ccTaskStatus as $action => $cTaskAction) {
					$h .= $this->getBlock($eStructure, $week, $cTaskAction, $countByAction[$action], $cUser, $canCreate, status: $status);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un bloc, rendu seul quand une intervention neuve y entre (`TaskBatchLib::getCreateChange()`). Sa tête
	 * porte la case qui prend les cartes à faire du bloc, le nom de l'action et son compte, puis à droite
	 * le « + » qui ouvre « Nouvelle intervention » sur cette action (`semaine()` de la maquette
	 * navigable, écart d de L5d : un bloc du produit est une action), récolte comprise (I4).
	 *
	 * Le bloc porte son rang dans l'écran (`data-util-piles-rank`) : `MainPiles.stack()` range les blocs
	 * dans leurs piles, l'ordre du document ne dit donc plus l'ordre voulu, et un bloc rendu seul doit
	 * arriver avec le sien (`pilesAi.js`). Il porte aussi son action (`data-action`) et la couleur de
	 * l'action (`getAccentAttributes()`).
	 */
	public function getBlock(\structure\Structure $eStructure, string $week, \Collection $cTaskAction, int $todo, \Collection $cUser, bool $canCreate, int $rank = 0, ?string $status = NULL): string {

		$uiTaskCard = new TaskCardUi();

		$eAction = $cTaskAction->first()['action'];

		// Une colonne range ses blocs dans l'ordre du document, sans rang, et les nomme par son état : une action
		// a un bloc dans chaque colonne.
		if($status === NULL) {
			$h = '<section '.self::getAccentAttributes($eAction, 'util-card planning-week-block').' id="planning-week-block-'.$eAction['id'].'" data-action="'.$eAction['id'].'" data-util-piles-rank="'.$rank.'">';
		} else {
			$h = '<section '.self::getAccentAttributes($eAction, 'util-card planning-week-block').' id="planning-week-'.$status.'-block-'.$eAction['id'].'" data-action="'.$eAction['id'].'">';
		}

			$h .= '<div class="util-title planning-block-head">';
				$h .= '<div class="planning-block-name">';
					$h .= new TaskBatchUi()->getGroupCheckbox();
					$h .= '<h4>'.encode($eAction['name']).' '.$this->getBlockCounter($eAction['id'], $todo, $status).'</h4>';
				$h .= '</div>';
				$h .= '<div class="planning-block-end">';
					if($canCreate) {
						// Un bloc n'existe qu'en cartes et en colonnes : la grille a ses groupes (`TaskWeekGridUi::getGroup()`).
						// Celui d'une colonne crée dans l'état de sa colonne, comme les deux « Nouvelle intervention » de
						// l'ancien planning.
						$url = ($status === NULL) ?
							TaskCreateUi::url($eStructure, 'taskWeek', $week, eAction: $eAction, view: \structure\Member::CARDS) :
							TaskCreateUi::url($eStructure, 'taskWeek', $week, eAction: $eAction, status: $status, view: \structure\Member::COLUMNS);
						$h .= '<a href="'.encode($url).'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';

			foreach($cTaskAction as $eTask) {
				$h .= $uiTaskCard->get($eStructure, $week, $eTask, $cUser);
			}

		$h .= '</section>';

		return $h;

	}

	public function getBlockCounter(int $action, int $todo, ?string $status = NULL): string {

		return '<span class="util-counter" id="planning-week-'.($status === NULL ? '' : $status.'-').'counter-'.$action.'">'.$todo.'</span>';

	}

	/**
	 * Les deux zones, « En retard » puis « Non planifié ». Le conteneur existe même vide, pour qu'une
	 * réponse puisse toujours le remplacer quand une zone apparaît ou disparaît ; une zone vide n'est pas
	 * rendue. `$grid` et `$canHarvest` sont ceux de l'écran, que lisent les lignes du retard en grille.
	 */
	public function getZones(\structure\Structure $eStructure, string $week, array $zones, \Collection $cUser, bool $grid, bool $canHarvest): string {

		$h = '<div id="planning-week-before">';

			foreach($zones as $zone) {

				if($zone['count'] > 0) {
					$h .= $this->getZone($eStructure, $week, $zone, $cUser, $grid, $canHarvest);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une zone : son titre et son compte, qui l'ouvrent et la ferment (`PlanningTaskZone.toggle()`), puis ses
	 * blocs sur le fond de la page, pour qu'elle se lise comme une section et non comme un bloc de plus.
	 * « En retard » s'ouvre avec la page, « Non planifié » une fois chargé, sauf repliés par la personne
	 * (`open`).
	 */
	public function getZone(\structure\Structure $eStructure, string $week, array $zone, \Collection $cUser, bool $grid, bool $canHarvest): string {

		$name = $zone['zone'];

		$title = self::getZoneTitle($name);

		$h = '<div class="util-fold mt-2 '.($zone['open'] ? 'util-fold-open' : '').'" id="planning-week-'.$name.'">';

			$h .= '<a class="util-fold-head" data-planning-zone="'.$name.'">';
				$h .= '<h3>'.$title.' '.$this->getZoneCounter($name, $zone['count']).'</h3>';
				$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-fold-content">';
				$h .= $this->getZoneBoard($eStructure, $week, $zone, $cUser, $grid, $canHarvest);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre d'une zone, que la fiche de temps reprend (CF).
	 */
	public static function getZoneTitle(string $name): string {

		return match($name) {
			TaskLateLib::LATE => s("En retard"),
			TaskLateLib::UNPLANNED => s("Non planifié"),
		};

	}

	public function getZoneCounter(string $name, int $count): string {

		return '<span class="util-counter" id="planning-week-'.$name.'-counter">'.$count.'</span>';

	}

	/**
	 * Les blocs d'une zone, rangés en piles comme ceux de la semaine. Non chargée, la zone garde un nœud
	 * vide que sa première ouverture remplace (`taskWeek:unplanned`). En grille, « En retard » est un tableau
	 * de la grille (`TaskWeekGridUi::getZoneBoard()`, DZ) ; « Non planifié » reste en cartes.
	 */
	public function getZoneBoard(\structure\Structure $eStructure, string $week, array $zone, \Collection $cUser, bool $grid, bool $canHarvest): string {

		if($grid and $zone['zone'] === TaskLateLib::LATE) {
			return new TaskWeekGridUi()->getZoneBoard($eStructure, $week, $zone, $cUser, $canHarvest);
		}

		$id = 'planning-week-'.$zone['zone'].'-board';

		if($zone['loaded'] === FALSE) {
			return '<div id="'.$id.'"></div>';
		}

		$h = '<div id="'.$id.'" class="util-piles" onrender="MainPiles.stack(this)">';

			foreach($zone['blocks'] as $action => $block) {
				$h .= $this->getZoneBlock($eStructure, $week, $zone, $action, $cUser, $block['rank']);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un bloc d'une zone. Il n'a pas de « + » : une intervention se crée dans la semaine. Au-delà du seuil
	 * (`TaskLateLib::$threshold`), il montre ses plus récentes puis « Voir les N autres », qui le redessine
	 * entier, à son rang (Q1 de L8, piste a).
	 */
	public function getZoneBlock(\structure\Structure $eStructure, string $week, array $zone, int $action, \Collection $cUser, int $rank): string {

		$uiTaskCard = new TaskCardUi();

		$name = $zone['zone'];
		$block = $zone['blocks'][$action];

		$h = '<section '.self::getAccentAttributes($block['eAction'], 'util-card planning-week-block').' id="planning-week-'.$name.'-block-'.$action.'" data-util-piles-rank="'.$rank.'">';

			$h .= '<div class="util-title planning-block-head">';
				$h .= '<div class="planning-block-name">';
					$h .= new TaskBatchUi()->getGroupCheckbox();
					$h .= '<h4>'.encode($block['eAction']['name']).' '.$this->getZoneBlockCounter($name, $action, $block['count']).'</h4>';
				$h .= '</div>';
			$h .= '</div>';

			foreach($block['shown'] as $id) {
				$h .= $uiTaskCard->get($eStructure, $week, $zone['cTask'][$id], $cUser);
			}

			$rest = $block['count'] - count($block['shown']);

			if($rest > 0) {
				$h .= '<div class="planning-week-more">';
					$h .= '<a data-ajax="'.encode(self::urlMore($eStructure, $week, $name, $action, \structure\Member::CARDS)).'" data-ajax-method="get" class="btn btn-sm btn-outline-primary">'.p("Voir l'autre", "Voir les {value} autres", $rest).'</a>';
				$h .= '</div>';
			}

		$h .= '</section>';

		return $h;

	}

	/**
	 * « Voir les N autres » d'un bloc de zone, ou d'un groupe du tableau « En retard » en grille. Le lien porte
	 * l'affichage de ce qui le montre (`view`), pour que la réponse rende un bloc ou un groupe, quoi que le membre
	 * ait choisi depuis dans un autre onglet.
	 */
	public static function urlMore(\structure\Structure $eStructure, string $week, string $name, int $action, string $view): string {

		return '/planning/taskWeek:more?'.http_build_query([
			'id' => $eStructure['id'],
			'week' => $week,
			'zone' => $name,
			'action' => $action,
			'view' => $view,
		]);

	}

	public function getZoneBlockCounter(string $name, int $action, int $count): string {

		return '<span class="util-counter" id="planning-week-'.$name.'-counter-'.$action.'">'.$count.'</span>';

	}

}
?>
